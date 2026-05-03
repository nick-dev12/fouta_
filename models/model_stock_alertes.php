<?php
/**
 * Seuils d'alerte stock (paramètres + usage notifications)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return bool
 */
function stock_alertes_tables_ok()
{
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $stmt = $db->query("SELECT 1 FROM stock_alertes_regles LIMIT 1");
        $stmt->fetchColumn();
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @return array<int, array{id:int,niveau:string,seuil:int,date_creation:?string}>
 */
function stock_alertes_get_all_regles()
{
    global $db;
    if (!stock_alertes_tables_ok()) {
        return [];
    }
    try {
        $stmt = $db->query(
            'SELECT id, niveau, seuil, date_creation FROM stock_alertes_regles ORDER BY FIELD(niveau, \'standard\',\'moyen\',\'haut\'), seuil ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param string $niveau standard|moyen|haut
 * @return string
 */
function stock_alertes_libelle_niveau($niveau)
{
    $n = (string) $niveau;
    $map = [
        'standard' => 'Niveau standard',
        'moyen' => 'Niveau moyen',
        'haut' => 'Niveau haut',
    ];
    return $map[$n] ?? $n;
}

/**
 * Gravité pour comparer (plus grand = plus critique)
 */
function stock_alertes_gravite_niveau($niveau)
{
    $n = (string) $niveau;
    if ($n === 'haut') {
        return 3;
    }
    if ($n === 'moyen') {
        return 2;
    }
    return 1;
}

/**
 * @param string $niveau
 * @param int $seuil
 * @return array{success:bool, message:string}
 */
function stock_alertes_enregistrer_regle($niveau, $seuil)
{
    global $db;
    if (!stock_alertes_tables_ok()) {
        return ['success' => false, 'message' => 'Table absente — exécutez migrations/run_create_stock_alertes.php'];
    }
    $niveau = (string) $niveau;
    if (!in_array($niveau, ['standard', 'moyen', 'haut'], true)) {
        return ['success' => false, 'message' => 'Niveau d’alerte invalide.'];
    }
    $seuil = (int) $seuil;
    if ($seuil < 0 || $seuil > 2147483646) {
        return ['success' => false, 'message' => 'Seuil de stock invalide.'];
    }
    try {
        $stmt = $db->prepare(
            'INSERT INTO stock_alertes_regles (niveau, seuil, date_creation)
             VALUES (:niveau, :seuil, NOW())
             ON DUPLICATE KEY UPDATE seuil = VALUES(seuil), date_modification = NOW()'
        );
        $stmt->execute(['niveau' => $niveau, 'seuil' => $seuil]);
        return ['success' => true, 'message' => 'Seuil enregistré.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement.'];
    }
}

/**
 * @param int $id
 * @return bool
 */
function stock_alertes_supprimer_regle($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !stock_alertes_tables_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare('DELETE FROM stock_alertes_regles WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Règles dont le seuil est franchi à la baisse (ancien > seuil et nouveau <= seuil)
 *
 * @param int $stock_avant
 * @param int $stock_apres
 * @param array $regles
 * @return list<array{id:int,niveau:string,seuil:int}>
 */
function stock_alertes_regles_franchies($stock_avant, $stock_apres, array $regles)
{
    $avant = (int) $stock_avant;
    $apres = (int) $stock_apres;
    if ($apres >= $avant) {
        return [];
    }
    $out = [];
    foreach ($regles as $r) {
        $s = (int) ($r['seuil'] ?? 0);
        if ($avant > $s && $apres <= $s) {
            $out[] = $r;
        }
    }
    return $out;
}

/**
 * Produits actuellement sous au moins un seuil (aperçu popup)
 *
 * @return array{items: list<array>, total: int}
 */
function stock_alertes_resume_pour_popup($limit = 30)
{
    $limit = max(1, min(100, (int) $limit));
    if (!stock_alertes_tables_ok()) {
        return ['items' => [], 'total' => 0];
    }
    $regles = stock_alertes_get_all_regles();
    if (empty($regles)) {
        return ['items' => [], 'total' => 0];
    }
    $max_seuil = 0;
    foreach ($regles as $r) {
        $max_seuil = max($max_seuil, (int) $r['seuil']);
    }
    global $db;
    try {
        $stmt = $db->prepare(
            "SELECT id, nom, stock FROM produits WHERE stock <= :mx ORDER BY stock ASC, nom ASC LIMIT 250"
        );
        $stmt->execute(['mx' => $max_seuil]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return ['items' => [], 'total' => 0];
    }

    $items_full = [];
    foreach ($rows as $row) {
        $sid = (int) $row['id'];
        $nom = (string) $row['nom'];
        $stock = (int) $row['stock'];
        $pire = null;
        foreach ($regles as $r) {
            $seuil = (int) $r['seuil'];
            if ($stock > $seuil) {
                continue;
            }
            if ($pire === null || stock_alertes_gravite_niveau($r['niveau']) > stock_alertes_gravite_niveau($pire['niveau'])) {
                $pire = $r;
            }
        }
        if ($pire !== null) {
            $items_full[] = [
                'produit_id' => $sid,
                'nom' => $nom,
                'stock' => $stock,
                'seuil_ref' => (int) $pire['seuil'],
                'niveau' => (string) $pire['niveau'],
                'niveau_libelle' => stock_alertes_libelle_niveau($pire['niveau']),
            ];
        }
    }
    $total = count($items_full);
    $items = array_slice($items_full, 0, $limit);
    return ['items' => $items, 'total' => $total];
}
