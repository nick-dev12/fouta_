<?php
/**
 * Bons de retour (référence un BL + quantités retirées par ligne)
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_bl.php';

function br_retour_tables_available()
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
        $db->query('SELECT 1 FROM bons_retour LIMIT 1');
        $db->query('SELECT 1 FROM bons_retour_lignes LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function generate_numero_br()
{
    global $db;
    if (!br_retour_tables_available()) {
        return 'BR' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
    try {
        $stmt = $db->query('SELECT MAX(id) AS m FROM bons_retour');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $n = ($row && $row['m']) ? (int) $row['m'] + 1 : 1;
        return 'BR' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'BR' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}

/**
 * Quantité déjà indiquée sur des bons de retour pour une ligne de BL
 */
function br_quantite_deja_retournee_bl_ligne($bl_ligne_id)
{
    global $db;
    if (!br_retour_tables_available()) {
        return 0.0;
    }
    $bl_ligne_id = (int) $bl_ligne_id;
    if ($bl_ligne_id <= 0) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare('
            SELECT COALESCE(SUM(l.quantite_retour), 0) AS s
            FROM bons_retour_lignes l
            INNER JOIN bons_retour b ON b.id = l.bon_retour_id
            WHERE l.bl_ligne_id = :lid
        ');
        $stmt->execute(['lid' => $bl_ligne_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($row['s'] ?? 0);
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Quantité encore retournable sur une ligne de BL
 */
function br_quantite_disponible_retour_bl_ligne(array $ligne_bl)
{
    $qte_bl = (float) ($ligne_bl['quantite'] ?? 0);
    $lid = (int) ($ligne_bl['id'] ?? 0);
    $deja = br_quantite_deja_retournee_bl_ligne($lid);
    return max(0, $qte_bl - $deja);
}

/**
 * @return array{success:bool,message?:string,br_id?:int,numero_br?:string}
 */
function br_create_bon_retour($bl_id, $admin_id, $notes, array $quantites_par_ligne_id)
{
    global $db;
    if (!br_retour_tables_available() || !bl_tables_available()) {
        return ['success' => false, 'message' => 'Tables bons de retour ou BL non disponibles. Exécutez la migration.'];
    }
    $bl_id = (int) $bl_id;
    $bl = get_bl_by_id($bl_id);
    if (!$bl) {
        return ['success' => false, 'message' => 'Bon de livraison introuvable.'];
    }
    $lignes = get_lignes_bl($bl_id);
    if (empty($lignes)) {
        return ['success' => false, 'message' => 'Aucune ligne sur ce bon de livraison.'];
    }

    $by_id = [];
    foreach ($lignes as $l) {
        $by_id[(int) $l['id']] = $l;
    }

    $rows_insert = [];
    $total_ht = 0.0;

    foreach ($quantites_par_ligne_id as $ligne_id => $qty_in) {
        $ligne_id = (int) $ligne_id;
        $qty = (float) $qty_in;
        if ($ligne_id <= 0 || $qty <= 0) {
            continue;
        }
        if (empty($by_id[$ligne_id])) {
            return ['success' => false, 'message' => 'Ligne invalide (# ' . $ligne_id . ').'];
        }
        $lb = $by_id[$ligne_id];
        $dispo = br_quantite_disponible_retour_bl_ligne($lb);
        if ($qty > $dispo + 1e-6) {
            return [
                'success' => false,
                'message' => 'Quantité trop élevée pour « ' . ($lb['designation'] ?? '') . ' » : max. ' . rtrim(rtrim(sprintf('%.4F', $dispo), '0'), '.') . ' disponible.',
            ];
        }
        $pu = (float) ($lb['prix_unitaire_ht'] ?? 0);
        $tl = round($qty * $pu, 2);
        $total_ht += $tl;
        $rows_insert[] = [
            'bl_ligne_id' => $ligne_id,
            'produit_id' => !empty($lb['produit_id']) ? (int) $lb['produit_id'] : null,
            'designation' => (string) ($lb['designation'] ?? ''),
            'quantite_retour' => $qty,
            'prix_unitaire_ht' => $pu,
            'total_ligne_ht' => $tl,
        ];
    }

    if (empty($rows_insert)) {
        return ['success' => false, 'message' => 'Saisissez au moins une quantité à retourner (&gt; 0).'];
    }

    $numero = generate_numero_br();
    /* Horodatage unique = instant exact de génération du bon de retour */
    $moment = date('Y-m-d H:i:s');

    try {
        $db->beginTransaction();
        $stmt = $db->prepare('
            INSERT INTO bons_retour (numero_br, bl_id, admin_createur_id, date_retour, notes, total_ht_retour, date_creation)
            VALUES (:numero_br, :bl_id, :admin_id, :date_retour, :notes, :total_ht, :date_creation)
        ');
        $stmt->execute([
            'numero_br' => $numero,
            'bl_id' => $bl_id,
            'admin_id' => $admin_id && (int) $admin_id > 0 ? (int) $admin_id : null,
            'date_retour' => $moment,
            'notes' => trim((string) $notes) !== '' ? trim($notes) : null,
            'total_ht' => round($total_ht, 2),
            'date_creation' => $moment,
        ]);
        $br_id = (int) $db->lastInsertId();

        $ins = $db->prepare('
            INSERT INTO bons_retour_lignes (bon_retour_id, bl_ligne_id, produit_id, designation, quantite_retour, prix_unitaire_ht, total_ligne_ht)
            VALUES (:bon_retour_id, :bl_ligne_id, :produit_id, :designation, :quantite_retour, :pu, :total)
        ');
        foreach ($rows_insert as $r) {
            $ins->execute([
                'bon_retour_id' => $br_id,
                'bl_ligne_id' => $r['bl_ligne_id'],
                'produit_id' => $r['produit_id'],
                'designation' => $r['designation'],
                'quantite_retour' => $r['quantite_retour'],
                'pu' => $r['prix_unitaire_ht'],
                'total' => $r['total_ligne_ht'],
            ]);
        }

        require_once __DIR__ . '/model_produits.php';
        require_once __DIR__ . '/model_mouvements_stock.php';

        foreach ($rows_insert as $r) {
            $pid = !empty($r['produit_id']) ? (int) $r['produit_id'] : 0;
            if ($pid <= 0) {
                continue;
            }
            $q = (int) round((float) $r['quantite_retour']);
            if ($q <= 0) {
                continue;
            }
            $produit = get_produit_by_id($pid);
            if (!$produit) {
                throw new PDOException('Produit introuvable pour le retour stock (#' . $pid . ').');
            }
            $avant = (int) ($produit['stock'] ?? 0);
            $apres = increment_produit_stock($pid, $q);
            if ($apres === false) {
                throw new PDOException('Impossible de réintégrer le stock pour « ' . ($r['designation'] ?? '') . ' ».');
            }
            $mv = [
                'type' => 'entree',
                'produit_id' => $pid,
                'quantite' => $q,
                'quantite_avant' => $avant,
                'quantite_apres' => (int) $apres,
                'reference_type' => 'bon_retour',
                'reference_id' => $br_id,
                'reference_numero' => $numero,
                'notes' => 'Retour B2B — bon ' . $numero,
            ];
            if ($admin_id && (int) $admin_id > 0) {
                $mv['admin_id'] = (int) $admin_id;
            }
            create_stock_mouvement($mv);
        }

        $db->commit();
        return ['success' => true, 'br_id' => $br_id, 'numero_br' => $numero];
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[br_create_bon_retour] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement du bon de retour.'];
    }
}

function br_get_all_with_bl_client()
{
    global $db;
    if (!br_retour_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT br.*,
                   bl.numero_bl, bl.date_bl AS bl_date_bl,
                   c.raison_sociale AS client_nom
            FROM bons_retour br
            INNER JOIN bons_livraison bl ON bl.id = br.bl_id
            INNER JOIN clients_b2b c ON c.id = bl.client_b2b_id
            ORDER BY br.date_creation DESC, br.id DESC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[br_get_all_with_bl_client] ' . $e->getMessage());
        return [];
    }
}

/**
 * Clients B2B ayant au moins un bon de retour (liste groupée comme l’onglet BL)
 */
function get_clients_b2b_avec_bons_retour()
{
    global $db;
    if (!br_retour_tables_available() || !bl_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT c.id, c.raison_sociale, c.nom_contact, c.prenom_contact, c.telephone, c.email, c.adresse,
                   COUNT(br.id) AS nb_br,
                   MAX(br.date_creation) AS dernier_br_date
            FROM clients_b2b c
            INNER JOIN bons_livraison bl ON bl.client_b2b_id = c.id
            INNER JOIN bons_retour br ON br.bl_id = bl.id
            GROUP BY c.id, c.raison_sociale, c.nom_contact, c.prenom_contact, c.telephone, c.email, c.adresse
            ORDER BY c.raison_sociale ASC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[get_clients_b2b_avec_bons_retour] ' . $e->getMessage());
        return [];
    }
}

/**
 * Tous les bons de retour liés aux BL d’un client B2B
 */
function br_get_all_for_client_b2b($client_b2b_id)
{
    global $db;
    if (!br_retour_tables_available() || !bl_tables_available()) {
        return [];
    }
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT br.*,
                   bl.numero_bl, bl.date_bl AS bl_date_bl,
                   c.raison_sociale AS client_nom
            FROM bons_retour br
            INNER JOIN bons_livraison bl ON bl.id = br.bl_id
            INNER JOIN clients_b2b c ON c.id = bl.client_b2b_id
            WHERE bl.client_b2b_id = :cid
            ORDER BY br.date_creation DESC, br.id DESC
        ');
        $stmt->execute(['cid' => $client_b2b_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[br_get_all_for_client_b2b] ' . $e->getMessage());
        return [];
    }
}

function br_get_by_id($id)
{
    global $db;
    if (!br_retour_tables_available()) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT br.*,
                   bl.numero_bl, bl.date_bl AS bl_date_bl,
                   bl.client_b2b_id,
                   c.raison_sociale, c.telephone AS client_telephone, c.email AS client_email, c.adresse AS client_adresse
            FROM bons_retour br
            INNER JOIN bons_livraison bl ON bl.id = br.bl_id
            INNER JOIN clients_b2b c ON c.id = bl.client_b2b_id
            WHERE br.id = :id
        ');
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function br_get_lignes($bon_retour_id)
{
    global $db;
    if (!br_retour_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT l.*
            FROM bons_retour_lignes l
            INNER JOIN bons_retour br ON br.id = l.bon_retour_id
            INNER JOIN bl_lignes bll ON bll.id = l.bl_ligne_id AND bll.bl_id = br.bl_id
            WHERE l.bon_retour_id = :id AND l.quantite_retour > 0
            ORDER BY l.id ASC
        ');
        $stmt->execute(['id' => (int) $bon_retour_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}
