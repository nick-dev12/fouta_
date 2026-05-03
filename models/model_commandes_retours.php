<?php
/**
 * Retours marchandises sur commandes e-commerce (livrées / payées).
 * Distinct des bons de retour B2B (bons_retour ↔ BL).
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_commandes_admin.php';

function crc_retour_tables_available()
{
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!$db) {
        $ok = false;
        return false;
    }
    try {
        $db->query('SELECT 1 FROM commandes_retours LIMIT 1');
        $db->query('SELECT 1 FROM commandes_retours_lignes LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function crc_generate_numero_retour()
{
    global $db;
    if (!crc_retour_tables_available()) {
        return 'CRT' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
    try {
        $stmt = $db->query('SELECT MAX(id) AS m FROM commandes_retours');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $n = ($row && $row['m']) ? (int) $row['m'] + 1 : 1;
        return 'CRT' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'CRT' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}

function crc_quantite_deja_retournee_ligne_cp($commande_produit_id)
{
    global $db;
    if (!crc_retour_tables_available()) {
        return 0.0;
    }
    $commande_produit_id = (int) $commande_produit_id;
    if ($commande_produit_id <= 0) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare('
            SELECT COALESCE(SUM(l.quantite_retour), 0) AS s
            FROM commandes_retours_lignes l
            INNER JOIN commandes_retours r ON r.id = l.retour_commande_id
            WHERE l.commande_produit_id = :cid
        ');
        $stmt->execute(['cid' => $commande_produit_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($row['s'] ?? 0);
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Quantité encore retournable sur une ligne commande_produits
 *
 * @param array $ligne_cp id, quantite
 */
function crc_quantite_disponible_retour_ligne(array $ligne_cp)
{
    $q_cmd = (float) ($ligne_cp['quantite'] ?? 0);
    $lid = (int) ($ligne_cp['id'] ?? 0);
    $deja = crc_quantite_deja_retournee_ligne_cp($lid);
    return max(0, $q_cmd - $deja);
}

function crc_commande_est_eligible_retour(array $commande)
{
    $st = $commande['statut'] ?? '';
    return in_array($st, ['livree', 'paye'], true);
}

/**
 * @return array{success:bool,message?:string,retour_id?:int,numero_retour?:string}
 */
function crc_create_retour_commande($commande_id, $admin_id, $notes, array $quantites_par_cp_id)
{
    global $db;
    if (!crc_retour_tables_available()) {
        return ['success' => false, 'message' => 'Tables retours absentes. Exécutez la migration commandes_retours.'];
    }
    $commande_id = (int) $commande_id;
    $commande = get_commande_by_id($commande_id);
    if (!$commande) {
        return ['success' => false, 'message' => 'Commande introuvable.'];
    }
    if (!crc_commande_est_eligible_retour($commande)) {
        return ['success' => false, 'message' => 'Retour réservé aux commandes livrées ou payées.'];
    }

    $lignes = get_produits_by_commande($commande_id);
    if (empty($lignes)) {
        return ['success' => false, 'message' => 'Aucune ligne sur cette commande.'];
    }

    $by_id = [];
    foreach ($lignes as $l) {
        $by_id[(int) $l['id']] = $l;
    }

    $rows_insert = [];
    $total_ht = 0.0;

    foreach ($quantites_par_cp_id as $cp_id => $qty_in) {
        $cp_id = (int) $cp_id;
        $qty = (float) str_replace(',', '.', (string) $qty_in);
        if ($cp_id <= 0 || $qty <= 0) {
            continue;
        }
        if (empty($by_id[$cp_id])) {
            return ['success' => false, 'message' => 'Ligne produit invalide (# ' . $cp_id . ').'];
        }
        $lc = $by_id[$cp_id];
        $dispo = crc_quantite_disponible_retour_ligne($lc);
        if ($qty > $dispo + 1e-6) {
            $nom = $lc['produit_nom'] ?? $lc['nom_produit'] ?? '';
            return [
                'success' => false,
                'message' => 'Quantité trop élevée pour « ' . $nom . ' » : max. ' . rtrim(rtrim(sprintf('%.4F', $dispo), '0'), '.') . ' retournable.',
            ];
        }
        $pu = (float) ($lc['prix_unitaire'] ?? 0);
        if ($pu <= 0 && !empty($lc['quantite'])) {
            $pu = round((float) ($lc['prix_total'] ?? 0) / max(1, (int) $lc['quantite']), 4);
        }
        $tl = round($qty * $pu, 2);
        $total_ht += $tl;
        $designation = (string) ($lc['produit_nom'] ?? $lc['nom_produit'] ?? 'Produit');
        $rows_insert[] = [
            'commande_produit_id' => $cp_id,
            'produit_id' => !empty($lc['produit_id']) ? (int) $lc['produit_id'] : null,
            'designation' => $designation,
            'quantite_retour' => $qty,
            'prix_unitaire' => $pu,
            'total_ligne' => $tl,
        ];
    }

    if (empty($rows_insert)) {
        return ['success' => false, 'message' => 'Indiquez au moins une quantité retournée (&gt; 0).'];
    }

    $numero = crc_generate_numero_retour();
    $moment = date('Y-m-d H:i:s');

    try {
        $db->beginTransaction();
        $stmt = $db->prepare('
            INSERT INTO commandes_retours (numero_retour, commande_id, admin_createur_id, date_retour, notes, montant_total_retour, date_creation)
            VALUES (:numero_retour, :commande_id, :admin_id, :date_retour, :notes, :total_ht, :date_creation)
        ');
        $stmt->execute([
            'numero_retour' => $numero,
            'commande_id' => $commande_id,
            'admin_id' => $admin_id && (int) $admin_id > 0 ? (int) $admin_id : null,
            'date_retour' => $moment,
            'notes' => trim((string) $notes) !== '' ? trim($notes) : null,
            'total_ht' => round($total_ht, 2),
            'date_creation' => $moment,
        ]);
        $retour_id = (int) $db->lastInsertId();

        $ins = $db->prepare('
            INSERT INTO commandes_retours_lignes (retour_commande_id, commande_produit_id, produit_id, designation, quantite_retour, prix_unitaire, total_ligne)
            VALUES (:rid, :cpid, :pid, :designation, :q, :pu, :total)
        ');
        foreach ($rows_insert as $r) {
            $ins->execute([
                'rid' => $retour_id,
                'cpid' => $r['commande_produit_id'],
                'pid' => $r['produit_id'],
                'designation' => $r['designation'],
                'q' => $r['quantite_retour'],
                'pu' => $r['prix_unitaire'],
                'total' => $r['total_ligne'],
            ]);
        }
        $db->commit();
        return ['success' => true, 'retour_id' => $retour_id, 'numero_retour' => $numero];
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[crc_create_retour_commande] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement du retour.'];
    }
}

function crc_get_by_id($id)
{
    global $db;
    if (!crc_retour_tables_available()) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT r.*,
                   c.numero_commande, c.statut AS commande_statut, c.montant_total AS commande_montant_total,
                   c.date_commande, c.telephone_livraison, c.adresse_livraison,
                   COALESCE(u.nom, c.client_nom) AS user_nom,
                   COALESCE(u.prenom, c.client_prenom) AS user_prenom,
                   COALESCE(u.email, c.client_email) AS user_email,
                   COALESCE(u.telephone, c.client_telephone, c.telephone_livraison) AS user_telephone
            FROM commandes_retours r
            INNER JOIN commandes c ON c.id = r.commande_id
            LEFT JOIN users u ON c.user_id = u.id
            WHERE r.id = :id
        ');
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function crc_get_lignes($retour_id)
{
    global $db;
    if (!crc_retour_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT * FROM commandes_retours_lignes WHERE retour_commande_id = :id ORDER BY id ASC
        ');
        $stmt->execute(['id' => (int) $retour_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Tous les retours boutique (liste admin)
 */
function crc_liste_admin()
{
    global $db;
    if (!crc_retour_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->query("
            SELECT r.*,
                   c.numero_commande, c.statut AS commande_statut,
                   TRIM(CONCAT(COALESCE(u.prenom, c.client_prenom, ''), ' ', COALESCE(u.nom, c.client_nom, ''))) AS client_nom_complet,
                   COALESCE(u.email, c.client_email) AS client_email
            FROM commandes_retours r
            INNER JOIN commandes c ON c.id = r.commande_id
            LEFT JOIN users u ON c.user_id = u.id
            ORDER BY r.date_creation DESC, r.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[crc_liste_admin] ' . $e->getMessage());
        return [];
    }
}

/**
 * Retours liés à une commande (plus récent en premier)
 */
function crc_get_retours_par_commande($commande_id)
{
    global $db;
    if (!crc_retour_tables_available()) {
        return [];
    }
    $commande_id = (int) $commande_id;
    if ($commande_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT * FROM commandes_retours WHERE commande_id = :cid ORDER BY date_creation DESC, id DESC
        ');
        $stmt->execute(['cid' => $commande_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function crc_count_retours_admin()
{
    global $db;
    if (!crc_retour_tables_available()) {
        return 0;
    }
    try {
        $stmt = $db->query('SELECT COUNT(*) AS n FROM commandes_retours');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['n'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}
