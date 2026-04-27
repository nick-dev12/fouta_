<?php
/**
 * Modèle pour les mouvements de stock (entrées, sorties, inventaires)
 * Stock géré uniquement par produits.stock (table stock_articles supprimée)
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Colonne présente sur stock_mouvements (cache SHOW COLUMNS)
 */
function stock_mouvements_has_column($name) {
    static $cols = null;
    global $db;
    if ($cols === null) {
        $cols = [];
        if (!$db) {
            return false;
        }
        try {
            $stmt = $db->query('SHOW COLUMNS FROM stock_mouvements');
            if ($stmt) {
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[$r['Field']] = true;
                }
            }
        } catch (PDOException $e) {
            $cols = [];
        }
    }
    return isset($cols[$name]);
}

/**
 * Enregistre un mouvement de stock
 * @param array $data ['type', 'produit_id'?, 'quantite', 'quantite_avant'?, 'quantite_apres'?, 'reference_type'?, 'reference_id'?, 'reference_numero'?, 'notes'?, 'admin_id'?]
 * @return int|false ID du mouvement ou False
 */
function create_stock_mouvement($data)
{
    global $db;

    try {
        $cols = 'type, produit_id, quantite, quantite_avant, quantite_apres, reference_type, reference_id, reference_numero, date_mouvement, notes';
        $ph = ':type, :produit_id, :quantite, :quantite_avant, :quantite_apres, :reference_type, :reference_id, :reference_numero, NOW(), :notes';
        $params = [
            'type' => $data['type'],
            'produit_id' => $data['produit_id'] ?? null,
            'quantite' => (int) $data['quantite'],
            'quantite_avant' => isset($data['quantite_avant']) ? (int) $data['quantite_avant'] : null,
            'quantite_apres' => isset($data['quantite_apres']) ? (int) $data['quantite_apres'] : null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_numero' => $data['reference_numero'] ?? null,
            'notes' => $data['notes'] ?? null
        ];
        if (stock_mouvements_has_column('admin_id') && !empty($data['admin_id'])) {
            $cols = 'type, produit_id, quantite, quantite_avant, quantite_apres, reference_type, reference_id, reference_numero, date_mouvement, notes, admin_id';
            $ph = ':type, :produit_id, :quantite, :quantite_avant, :quantite_apres, :reference_type, :reference_id, :reference_numero, NOW(), :notes, :admin_id';
            $params['admin_id'] = (int) $data['admin_id'];
        }
        $stmt = $db->prepare("INSERT INTO stock_mouvements ($cols) VALUES ($ph)");
        $stmt->execute($params);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les mouvements avec filtres (produits uniquement)
 * @param int|null $stock_article_id Ignoré (conservé pour compatibilité)
 * @param int|null $produit_id Filtrer par produit
 * @param int|null $categorie_id Filtrer par catégorie
 * @param string|null $type Filtrer par type (entree, sortie, inventaire)
 * @param int $limit Nombre max
 * @return array
 */
function get_stock_mouvements($stock_article_id = null, $produit_id = null, $categorie_id = null, $type = null, $limit = 100)
{
    global $db;

    try {
        $sql = "SELECT m.*, p.nom as produit_nom, p.categorie_id as produit_categorie_id
                FROM stock_mouvements m
                LEFT JOIN produits p ON m.produit_id = p.id
                WHERE 1=1";
        $params = ['limit' => (int) $limit];

        if ($produit_id !== null && $produit_id > 0) {
            $sql .= " AND m.produit_id = :produit_id";
            $params['produit_id'] = (int) $produit_id;
        }
        if ($categorie_id !== null && $categorie_id > 0) {
            $sql .= " AND m.produit_id IS NOT NULL AND p.categorie_id = :categorie_id";
            $params['categorie_id'] = (int) $categorie_id;
        }
        if ($type !== null && in_array($type, ['entree', 'sortie', 'inventaire'])) {
            $sql .= " AND m.type = :type";
            $params['type'] = $type;
        }

        $sql .= " ORDER BY m.date_mouvement DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}
