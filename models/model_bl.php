<?php
/**
 * Bons de livraison (HT) + lignes
 */
require_once __DIR__ . '/../conn/conn.php';

function bl_tables_available() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT 1 FROM bons_livraison LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Libellé affichage admin pour le statut d'un BL
 */
function bl_libelle_statut($st)
{
    switch ($st) {
        case 'valide':
            return 'Validé (comptabilité)';
        case 'paye':
            return 'Validé (comptabilité)';
        default:
            return 'Brouillon';
    }
}

/**
 * Libellés courts pour documents imprimés (facture BL — sans mention « comptabilité »)
 */
function bl_libelle_statut_facture($st)
{
    switch ($st) {
        case 'valide':
        case 'paye':
            return 'Validé';
        default:
            return 'Brouillon';
    }
}

/**
 * Libellé court (badges listes)
 */
function bl_libelle_statut_court($st)
{
    switch ($st) {
        case 'valide':
        case 'paye':
            return 'Validé (compta)';
        default:
            return 'Brouillon';
    }
}

/**
 * Après un JOIN clients_b2b, PDO peut mélanger les clés ; on force le statut du BL.
 */
function bl_row_apply_statut_bl(array $row)
{
    if (isset($row['bl_statut']) && $row['bl_statut'] !== '') {
        $row['statut'] = $row['bl_statut'];
    }
    return $row;
}

/**
 * BL verrouillés (plus de modification des lignes / en-tête) : validés pour la comptabilité.
 */
function bl_est_statut_verrouille($st) {
    $st = (string) $st;
    return $st === 'valide' || $st === 'paye';
}

/**
 * Aligne l’ENUM sur brouillon + valide et fusionne l’ancien « paye » vers « valide » si besoin.
 * @return bool
 */
function bl_ensure_statut_enum_bl() {
    global $db;
    static $cached = null;
    if ($cached === true) {
        return true;
    }
    if ($cached === false) {
        return false;
    }
    if (!$db || !bl_tables_available()) {
        $cached = false;
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `bons_livraison` LIKE 'statut'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $type = (string) ($row['Type'] ?? '');
        if ($type === '') {
            $cached = false;
            return false;
        }
        if (strpos($type, "'paye'") !== false) {
            $db->exec("UPDATE `bons_livraison` SET `statut` = 'valide' WHERE `statut` = 'paye'");
            $db->exec(
                "ALTER TABLE `bons_livraison` MODIFY COLUMN `statut` ENUM('brouillon','valide') NOT NULL DEFAULT 'brouillon'"
            );
        } elseif (strpos($type, "'valide'") === false) {
            $db->exec(
                "ALTER TABLE `bons_livraison` MODIFY COLUMN `statut` ENUM('brouillon','valide') NOT NULL DEFAULT 'brouillon'"
            );
        }
        $cached = true;
        return true;
    } catch (PDOException $e) {
        error_log('[bl_ensure_statut_enum_bl] ' . $e->getMessage());
        $cached = false;
        return false;
    }
}

function generate_numero_bl() {
    global $db;
    try {
        $stmt = $db->query('SELECT MAX(id) AS m FROM bons_livraison');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $n = ($row && $row['m']) ? (int) $row['m'] + 1 : 1;
        return 'BL' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'BL' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}

function bl_exists_for_devis($devis_id) {
    global $db;
    if (!bl_tables_available()) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT id FROM bons_livraison WHERE devis_id = :d LIMIT 1');
        $stmt->execute(['d' => (int) $devis_id]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function get_all_bl_with_clients() {
    global $db;
    if (!bl_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT b.*, c.raison_sociale, c.telephone AS client_telephone, c.email AS client_email,
                   b.statut AS bl_statut
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON b.client_b2b_id = c.id
            ORDER BY b.date_creation DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $i => $r) {
            $rows[$i] = bl_row_apply_statut_bl($r);
        }
        return $rows;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Clients B2B ayant au moins un bon de livraison (pour l’onglet BL : liste par contact)
 */
function get_clients_b2b_avec_bl() {
    global $db;
    if (!bl_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT c.id, c.raison_sociale, c.nom_contact, c.prenom_contact, c.telephone, c.email, c.adresse,
                   COUNT(b.id) AS nb_bl,
                   MAX(b.date_creation) AS dernier_bl_date
            FROM clients_b2b c
            INNER JOIN bons_livraison b ON b.client_b2b_id = c.id
            GROUP BY c.id, c.raison_sociale, c.nom_contact, c.prenom_contact, c.telephone, c.email, c.adresse
            ORDER BY c.raison_sociale ASC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[get_clients_b2b_avec_bl] ' . $e->getMessage());
        return [];
    }
}

/**
 * Tous les BL d’un client B2B (liste détaillée)
 */
function get_all_bl_for_client_b2b($client_b2b_id) {
    global $db;
    if (!bl_tables_available()) {
        return [];
    }
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT b.*, c.raison_sociale, c.telephone AS client_telephone, c.email AS client_email,
                   b.statut AS bl_statut
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON b.client_b2b_id = c.id
            WHERE b.client_b2b_id = :cid
            ORDER BY b.date_creation DESC
        ');
        $stmt->execute(['cid' => $client_b2b_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $i => $r) {
            $rows[$i] = bl_row_apply_statut_bl($r);
        }
        return $rows;
    } catch (PDOException $e) {
        return [];
    }
}

function get_bl_by_id($id) {
    global $db;
    if (!bl_tables_available()) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT b.*, c.raison_sociale, c.nom_contact, c.prenom_contact, c.email AS client_email, c.telephone AS client_telephone, c.adresse AS client_adresse,
                   b.statut AS bl_statut
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON b.client_b2b_id = c.id
            WHERE b.id = :id
        ');
        $stmt->execute(['id' => (int) $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return false;
        }
        $r = bl_row_apply_statut_bl($r);
        return $r;
    } catch (PDOException $e) {
        return false;
    }
}

function get_lignes_bl($bl_id) {
    global $db;
    if (!bl_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->prepare('SELECT * FROM bl_lignes WHERE bl_id = :id ORDER BY ordre ASC, id ASC');
        $stmt->execute(['id' => (int) $bl_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function update_bl_statut($bl_id, $statut) {
    global $db;
    if (!bl_tables_available() || !in_array($statut, ['brouillon', 'valide'], true)) {
        return false;
    }
    if (!bl_ensure_statut_enum_bl()) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE bons_livraison SET statut = :s, date_modification = NOW() WHERE id = :id');
        $stmt->execute(['s' => $statut, 'id' => (int) $bl_id]);
        $chk = $db->prepare('SELECT statut FROM bons_livraison WHERE id = :id LIMIT 1');
        $chk->execute(['id' => (int) $bl_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        $ok = $row && (string) ($row['statut'] ?? '') === $statut;
        if (!$ok) {
            error_log('[update_bl_statut] Échec persistance statut BL id=' . (int) $bl_id . ' attendu=' . $statut . ' lu=' . ($row['statut'] ?? 'null'));
        }
        return $ok;
    } catch (PDOException $e) {
        error_log('[update_bl_statut] ' . $e->getMessage());
        return false;
    }
}

function delete_bl($bl_id) {
    global $db;
    if (!bl_tables_available()) {
        return false;
    }
    $bl = get_bl_by_id($bl_id);
    if (!$bl || ($bl['statut'] ?? '') !== 'brouillon') {
        return false;
    }
    try {
        $stmt = $db->prepare('DELETE FROM bons_livraison WHERE id = :id');
        return $stmt->execute(['id' => (int) $bl_id]);
    } catch (PDOException $e) {
        error_log('[delete_bl] ' . $e->getMessage());
        return false;
    }
}

/**
 * Crée un BL à partir d'un devis (client B2B créé ou retrouvé par téléphone)
 * @return array{success:bool, message?:string, bl_id?:int}
 */
function create_bl_from_devis($devis_id, $admin_id) {
    global $db;
    if (!bl_tables_available()) {
        return ['success' => false, 'message' => 'Tables BL non installées. Exécutez la migration B2B.'];
    }
    require_once __DIR__ . '/model_devis.php';
    require_once __DIR__ . '/model_clients_b2b.php';

    $devis_id = (int) $devis_id;
    if (bl_exists_for_devis($devis_id)) {
        return ['success' => false, 'message' => 'Ce devis a déjà été converti en bon de livraison.'];
    }
    $devis = get_devis_by_id($devis_id);
    if (!$devis) {
        return ['success' => false, 'message' => 'Devis introuvable.'];
    }
    $produits = get_produits_by_devis($devis_id);
    if (empty($produits)) {
        return ['success' => false, 'message' => 'Aucune ligne produit sur ce devis.'];
    }

    $client = find_client_b2b_by_telephone($devis['client_telephone']);
    if (!$client) {
        $rs = trim($devis['client_prenom'] . ' ' . $devis['client_nom']);
        $cid = create_client_b2b([
            'raison_sociale' => $rs !== '' ? $rs : 'Client ' . $devis['numero_devis'],
            'nom_contact' => trim($devis['client_nom'] ?? ''),
            'prenom_contact' => trim($devis['client_prenom'] ?? ''),
            'email' => $devis['client_email'] ?? '',
            'telephone' => $devis['client_telephone'] ?? '',
            'adresse' => $devis['adresse_livraison'] ?? '',
            'notes' => 'Créé depuis devis ' . $devis['numero_devis'],
            'statut' => 'actif',
            'admin_createur_id' => $admin_id && (int) $admin_id > 0 ? (int) $admin_id : null,
        ]);
        if (!$cid) {
            return ['success' => false, 'message' => 'Impossible de créer la fiche client B2B.'];
        }
        $client = get_client_b2b_by_id($cid);
    }

    $total_ht = 0;
    foreach ($produits as $p) {
        $total_ht += (float) $p['prix_total'];
    }
    $total_ht += (float) ($devis['frais_livraison'] ?? 0);

    $numero = generate_numero_bl();
    try {
        $db->beginTransaction();
        $stmt = $db->prepare('
            INSERT INTO bons_livraison (numero_bl, client_b2b_id, devis_id, admin_createur_id, statut, date_bl, total_ht, notes, date_creation)
            VALUES (:numero_bl, :client_b2b_id, :devis_id, :admin_id, :statut, CURDATE(), :total_ht, :notes, NOW())
        ');
        $stmt->execute([
            'numero_bl' => $numero,
            'client_b2b_id' => (int) $client['id'],
            'devis_id' => $devis_id,
            'admin_id' => $admin_id ? (int) $admin_id : null,
            'statut' => 'brouillon',
            'total_ht' => round($total_ht, 2),
            'notes' => 'Issu du devis ' . $devis['numero_devis'],
        ]);
        $bl_id = (int) $db->lastInsertId();

        $ins = $db->prepare('
            INSERT INTO bl_lignes (bl_id, produit_id, designation, quantite, prix_unitaire_ht, total_ligne_ht, ordre)
            VALUES (:bl_id, :produit_id, :designation, :quantite, :pu, :total, :ordre)
        ');
        $ord = 0;
        foreach ($produits as $p) {
            $designation = $p['produit_nom'] ?? $p['nom_produit'] ?? 'Produit';
            $q = (float) $p['quantite'];
            $pu = (float) $p['prix_unitaire'];
            $tl = (float) $p['prix_total'];
            $ins->execute([
                'bl_id' => $bl_id,
                'produit_id' => (int) $p['produit_id'],
                'designation' => $designation,
                'quantite' => $q,
                'pu' => $pu,
                'total' => $tl,
                'ordre' => $ord++,
            ]);
        }
        $frais = (float) ($devis['frais_livraison'] ?? 0);
        if ($frais > 0) {
            $ins->execute([
                'bl_id' => $bl_id,
                'produit_id' => null,
                'designation' => 'Frais de livraison',
                'quantite' => 1,
                'pu' => $frais,
                'total' => $frais,
                'ordre' => $ord,
            ]);
        }

        $db->commit();
        return ['success' => true, 'bl_id' => $bl_id, 'numero_bl' => $numero];
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[create_bl_from_devis] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur technique lors de la création du BL.'];
    }
}

/**
 * Création manuelle d'un BL avec lignes
 * @param array $lignes [['produit_id'=>, 'designation'=>, 'quantite'=>, 'prix_unitaire_ht'=>], ...]
 */
function create_bl_manuel($client_b2b_id, $date_bl, $notes, $lignes, $admin_id, $statut = 'brouillon') {
    global $db;
    if (!bl_tables_available()) {
        return ['success' => false, 'message' => 'Tables BL absentes.'];
    }
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0) {
        return ['success' => false, 'message' => 'Client B2B requis.'];
    }
    if (!in_array($statut, ['brouillon', 'valide'], true)) {
        $statut = 'brouillon';
    }
    $total_ht = 0;
    $clean = [];
    foreach ($lignes as $i => $l) {
        $des = trim($l['designation'] ?? '');
        $q = (float) ($l['quantite'] ?? 0);
        $pu = (float) ($l['prix_unitaire_ht'] ?? 0);
        if ($des === '' || $q <= 0 || $pu < 0) {
            continue;
        }
        $tl = round($q * $pu, 2);
        $total_ht += $tl;
        $clean[] = [
            'produit_id' => !empty($l['produit_id']) ? (int) $l['produit_id'] : null,
            'designation' => $des,
            'quantite' => $q,
            'pu' => $pu,
            'total' => $tl,
            'ordre' => $i,
        ];
    }
    if (empty($clean)) {
        return ['success' => false, 'message' => 'Ajoutez au moins une ligne valide.'];
    }

    if ($statut === 'valide' && !bl_ensure_statut_enum_bl()) {
        return [
            'success' => false,
            'message' => 'Le statut « validé » ne peut pas être enregistré : vérifiez la colonne SQL ou exécutez migrations/bl_statut_unify_valide.sql.',
        ];
    }

    $numero = generate_numero_bl();
    try {
        $db->beginTransaction();
        $stmt = $db->prepare('
            INSERT INTO bons_livraison (numero_bl, client_b2b_id, devis_id, admin_createur_id, statut, date_bl, total_ht, notes, date_creation)
            VALUES (:numero_bl, :client_b2b_id, NULL, :admin_id, :statut, :date_bl, :total_ht, :notes, NOW())
        ');
        $stmt->execute([
            'numero_bl' => $numero,
            'client_b2b_id' => $client_b2b_id,
            'admin_id' => $admin_id ? (int) $admin_id : null,
            'statut' => $statut,
            'date_bl' => $date_bl ?: date('Y-m-d'),
            'total_ht' => round($total_ht, 2),
            'notes' => $notes !== '' ? $notes : null,
        ]);
        $bl_id = (int) $db->lastInsertId();

        $ins = $db->prepare('
            INSERT INTO bl_lignes (bl_id, produit_id, designation, quantite, prix_unitaire_ht, total_ligne_ht, ordre)
            VALUES (:bl_id, :produit_id, :designation, :quantite, :pu, :total, :ordre)
        ');
        foreach ($clean as $l) {
            $ins->execute([
                'bl_id' => $bl_id,
                'produit_id' => $l['produit_id'],
                'designation' => $l['designation'],
                'quantite' => $l['quantite'],
                'pu' => $l['pu'],
                'total' => $l['total'],
                'ordre' => $l['ordre'],
            ]);
        }
        $db->commit();
        return ['success' => true, 'bl_id' => $bl_id, 'numero_bl' => $numero];
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[create_bl_manuel] ' . $e->getMessage());
        $msg = 'Erreur lors de la création du BL.';
        if ($statut === 'valide' && (stripos($e->getMessage(), 'truncated') !== false || stripos($e->getMessage(), '1265') !== false)) {
            $msg = 'Le statut « validé » est refusé par la base : exécutez migrations/bl_statut_unify_valide.sql sur MySQL.';
        }
        return ['success' => false, 'message' => $msg];
    }
}

/**
 * Remplace toutes les lignes d'un BL et recalcule le total HT (réajustement commercial / compta)
 */
/**
 * Supprime une ligne de BL (si le BL n'est pas payé et qu'il reste au moins une ligne après)
 */
function delete_bl_ligne($ligne_id, $bl_id) {
    global $db;
    if (!bl_tables_available()) {
        return ['success' => false, 'message' => 'Tables BL absentes.'];
    }
    $ligne_id = (int) $ligne_id;
    $bl_id = (int) $bl_id;
    if ($ligne_id <= 0 || $bl_id <= 0) {
        return ['success' => false, 'message' => 'Paramètres invalides.'];
    }
    $bl = get_bl_by_id($bl_id);
    if (!$bl) {
        return ['success' => false, 'message' => 'BL introuvable.'];
    }
    if (bl_est_statut_verrouille($bl['statut'] ?? '')) {
        return ['success' => false, 'message' => 'BL validé : suppression de ligne impossible.'];
    }
    $lignes = get_lignes_bl($bl_id);
    if (count($lignes) <= 1) {
        return ['success' => false, 'message' => 'Conservez au moins une ligne sur le bon.'];
    }
    $found = false;
    foreach ($lignes as $l) {
        if ((int) ($l['id'] ?? 0) === $ligne_id) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        return ['success' => false, 'message' => 'Ligne introuvable.'];
    }
    try {
        $stmt = $db->prepare('DELETE FROM bl_lignes WHERE id = :lid AND bl_id = :bid');
        $stmt->execute(['lid' => $ligne_id, 'bid' => $bl_id]);
        if ($stmt->rowCount() < 1) {
            return ['success' => false, 'message' => 'Suppression impossible.'];
        }
        update_bl_total_from_lignes($bl_id);
        return ['success' => true];
    } catch (PDOException $e) {
        error_log('[delete_bl_ligne] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur technique.'];
    }
}

function replace_bl_lignes($bl_id, $lignes) {
    global $db;
    if (!bl_tables_available()) {
        return ['success' => false, 'message' => 'Tables BL absentes.'];
    }
    $bl_id = (int) $bl_id;
    $bl = get_bl_by_id($bl_id);
    if (!$bl) {
        return ['success' => false, 'message' => 'BL introuvable.'];
    }
    if (bl_est_statut_verrouille($bl['statut'] ?? '')) {
        return ['success' => false, 'message' => 'BL validé : modification des lignes impossible.'];
    }
    $clean = [];
    $total_ht = 0;
    foreach ($lignes as $i => $l) {
        $des = trim($l['designation'] ?? '');
        $q = (float) ($l['quantite'] ?? 0);
        $pu = (float) ($l['prix_unitaire_ht'] ?? 0);
        if ($des === '' || $q <= 0 || $pu < 0) {
            continue;
        }
        $tl = round($q * $pu, 2);
        $total_ht += $tl;
        $clean[] = [
            'produit_id' => !empty($l['produit_id']) ? (int) $l['produit_id'] : null,
            'designation' => $des,
            'quantite' => $q,
            'pu' => $pu,
            'total' => $tl,
            'ordre' => $i,
        ];
    }
    if (empty($clean)) {
        return ['success' => false, 'message' => 'Ajoutez au moins une ligne valide.'];
    }
    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM bl_lignes WHERE bl_id = :id')->execute(['id' => $bl_id]);
        $ins = $db->prepare('
            INSERT INTO bl_lignes (bl_id, produit_id, designation, quantite, prix_unitaire_ht, total_ligne_ht, ordre)
            VALUES (:bl_id, :produit_id, :designation, :quantite, :pu, :total, :ordre)
        ');
        foreach ($clean as $l) {
            $ins->execute([
                'bl_id' => $bl_id,
                'produit_id' => $l['produit_id'],
                'designation' => $l['designation'],
                'quantite' => $l['quantite'],
                'pu' => $l['pu'],
                'total' => $l['total'],
                'ordre' => $l['ordre'],
            ]);
        }
        $stmt = $db->prepare('UPDATE bons_livraison SET total_ht = :t, date_modification = NOW() WHERE id = :id');
        $stmt->execute(['t' => round($total_ht, 2), 'id' => $bl_id]);
        $db->commit();
        return ['success' => true];
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[replace_bl_lignes] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour des lignes.'];
    }
}

function update_bl_entete($bl_id, $date_bl, $notes) {
    global $db;
    if (!bl_tables_available()) {
        return false;
    }
    $bl = get_bl_by_id($bl_id);
    if ($bl && bl_est_statut_verrouille($bl['statut'] ?? '')) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE bons_livraison SET date_bl = :d, notes = :n, date_modification = NOW() WHERE id = :id');
        return $stmt->execute([
            'd' => $date_bl ?: date('Y-m-d'),
            'n' => $notes !== '' && $notes !== null ? trim($notes) : null,
            'id' => (int) $bl_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function update_bl_total_from_lignes($bl_id) {
    global $db;
    $lignes = get_lignes_bl($bl_id);
    $t = 0;
    foreach ($lignes as $l) {
        $t += (float) $l['total_ligne_ht'];
    }
    try {
        $stmt = $db->prepare('UPDATE bons_livraison SET total_ht = :t, date_modification = NOW() WHERE id = :id');
        return $stmt->execute(['t' => round($t, 2), 'id' => (int) $bl_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Mois (année-mois) contenant au moins un BL (selon date_bl), du plus récent au plus ancien
 *
 * @return array<int, array{value:string,label:string,annee:int,mois:int}>
 */
function get_mois_distincts_avec_bl() {
    global $db;
    if (!bl_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT DISTINCT YEAR(date_bl) AS y, MONTH(date_bl) AS m
            FROM bons_livraison
            ORDER BY y DESC, m DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $mois_noms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        $out = [];
        foreach ($rows as $row) {
            $y = (int) ($row['y'] ?? 0);
            $m = (int) ($row['m'] ?? 0);
            if ($y < 2000 || $m < 1 || $m > 12) {
                continue;
            }
            $val = sprintf('%04d-%02d', $y, $m);
            $out[] = [
                'value' => $val,
                'label' => $mois_noms[$m] . ' ' . $y,
                'annee' => $y,
                'mois' => $m,
            ];
        }
        return $out;
    } catch (PDOException $e) {
        error_log('[get_mois_distincts_avec_bl] ' . $e->getMessage());
        return [];
    }
}

/**
 * BL du mois (date_bl), avec client — pour suivi comptable
 */
function get_bl_compta_par_mois($annee, $mois) {
    global $db;
    if (!bl_tables_available()) {
        return [];
    }
    $annee = (int) $annee;
    $mois = (int) $mois;
    if ($annee < 2000 || $mois < 1 || $mois > 12) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT b.*, c.raison_sociale, c.telephone AS client_telephone, c.email AS client_email,
                   b.statut AS bl_statut
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON c.id = b.client_b2b_id
            WHERE YEAR(b.date_bl) = :a AND MONTH(b.date_bl) = :m
              AND b.statut IN (\'valide\', \'paye\')
            ORDER BY b.date_bl DESC, b.id DESC
        ');
        $stmt->execute(['a' => $annee, 'm' => $mois]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $i => $r) {
            $rows[$i] = bl_row_apply_statut_bl($r);
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[get_bl_compta_par_mois] ' . $e->getMessage());
        return [];
    }
}

/**
 * Statistiques BL pour un mois (date_bl) — compta : BL validés (comptabilité)
 *
 * @return array{nb_bl:int,nb_clients:int,somme_bl_ht:float,nb_valide:int}
 */
function get_stats_bl_compta_mois($annee, $mois) {
    global $db;
    $empty = ['nb_bl' => 0, 'nb_clients' => 0, 'somme_bl_ht' => 0.0, 'nb_valide' => 0];
    if (!bl_tables_available()) {
        return $empty;
    }
    $annee = (int) $annee;
    $mois = (int) $mois;
    if ($annee < 2000 || $mois < 1 || $mois > 12) {
        return $empty;
    }
    try {
        $stmt = $db->prepare('
            SELECT
                COUNT(*) AS nb_bl,
                COUNT(DISTINCT client_b2b_id) AS nb_clients,
                COALESCE(SUM(total_ht), 0) AS somme_bl_ht,
                COALESCE(SUM(CASE WHEN statut IN (\'valide\', \'paye\') THEN 1 ELSE 0 END), 0) AS nb_valide
            FROM bons_livraison
            WHERE YEAR(date_bl) = :a AND MONTH(date_bl) = :m
              AND statut IN (\'valide\', \'paye\')
        ');
        $stmt->execute(['a' => $annee, 'm' => $mois]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $empty;
        }
        return [
            'nb_bl' => (int) ($row['nb_bl'] ?? 0),
            'nb_clients' => (int) ($row['nb_clients'] ?? 0),
            'somme_bl_ht' => (float) ($row['somme_bl_ht'] ?? 0),
            'nb_valide' => (int) ($row['nb_valide'] ?? 0),
        ];
    } catch (PDOException $e) {
        error_log('[get_stats_bl_compta_mois] ' . $e->getMessage());
        return $empty;
    }
}
