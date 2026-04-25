<?php
/**
 * Factures mensuelles HT (clients B2B — regroupement BL)
 */
require_once __DIR__ . '/../conn/conn.php';

function factures_mensuelles_table_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT 1 FROM factures_mensuelles LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * BL validés pour ce client, pas encore rattachés à une facture mensuelle
 * (statut validé côté BL — une fois liés à une FM, ils ne sont plus proposés)
 */
function get_bl_valides_non_factures($client_b2b_id) {
    global $db;
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0 || !factures_mensuelles_table_ok()) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT b.*
            FROM bons_livraison b
            LEFT JOIN facture_mensuelle_bl f ON f.bl_id = b.id
            WHERE b.client_b2b_id = :cid
              AND b.statut IN (\'valide\', \'paye\')
              AND f.id IS NULL
            ORDER BY b.date_creation ASC, b.id ASC
        ');
        $stmt->execute(['cid' => $client_b2b_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[get_bl_valides_non_factures] ' . $e->getMessage());
        return [];
    }
}

/**
 * Compte les BL validés (comptabilité) et combien sont déjà liés à une facture mensuelle
 *
 * @return array{eligible:int, deja_lies:int, sans_lien:int, brouillon:int, brouillon_total:int}
 */
function facture_mensuelle_compte_bl_client($client_b2b_id) {
    global $db;
    $client_b2b_id = (int) $client_b2b_id;
    $empty = ['eligible' => 0, 'deja_lies' => 0, 'sans_lien' => 0, 'brouillon' => 0, 'brouillon_total' => 0];
    if ($client_b2b_id <= 0 || !factures_mensuelles_table_ok()) {
        return $empty;
    }
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM bons_livraison WHERE client_b2b_id = :cid');
        $stmt->execute(['cid' => $client_b2b_id]);
        $brouillon_total = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('
            SELECT COUNT(*) FROM bons_livraison
            WHERE client_b2b_id = :cid AND statut IN (\'valide\', \'paye\')
        ');
        $stmt->execute(['cid' => $client_b2b_id]);
        $eligible = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('
            SELECT COUNT(*) FROM bons_livraison b
            INNER JOIN facture_mensuelle_bl f ON f.bl_id = b.id
            WHERE b.client_b2b_id = :cid AND b.statut IN (\'valide\', \'paye\')
        ');
        $stmt->execute(['cid' => $client_b2b_id]);
        $deja_lies = (int) $stmt->fetchColumn();

        $sans_lien = max(0, $eligible - $deja_lies);

        $stmt = $db->prepare('
            SELECT COUNT(*) FROM bons_livraison WHERE client_b2b_id = :cid AND statut = \'brouillon\'
        ');
        $stmt->execute(['cid' => $client_b2b_id]);
        $brouillon = (int) $stmt->fetchColumn();

        return [
            'eligible' => $eligible,
            'deja_lies' => $deja_lies,
            'sans_lien' => $sans_lien,
            'brouillon' => $brouillon,
            'brouillon_total' => $brouillon_total,
        ];
    } catch (PDOException $e) {
        error_log('[facture_mensuelle_compte_bl_client] ' . $e->getMessage());
        return $empty;
    }
}

/**
 * Message explicite lorsqu’aucun BL n’est disponible pour générer / mettre à jour la FM
 */
function facture_mensuelle_message_aucun_bl($client_b2b_id) {
    $c = facture_mensuelle_compte_bl_client($client_b2b_id);
    if ($c['eligible'] === 0) {
        if ($c['brouillon_total'] > 0) {
            return 'Aucun bon de livraison au statut « Validé (comptabilité) ». Validez d’abord le ou les BL depuis le détail du bon ; les brouillons ne sont pas inclus dans la facture mensuelle.';
        }
        return 'Aucun bon de livraison au statut « Validé (comptabilité) » pour ce client.';
    }
    if ($c['sans_lien'] === 0 && $c['deja_lies'] > 0) {
        return 'Tous les bons de livraison validés sont déjà rattachés à une facture mensuelle. Ils ne peuvent figurer qu’une seule fois. Utilisez « Voir la facture » (brouillon du mois) ou ouvrez la facture concernée dans l’onglet Comptabilité — il n’y a rien de nouveau à ajouter.';
    }
    return 'Aucun bon de livraison validé en attente de facturation.';
}

function get_facture_mensuelle_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !factures_mensuelles_table_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM factures_mensuelles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Facture du mois en cours pour ce client (tout statut)
 */
function get_facture_mensuelle_by_client_month($client_b2b_id, $annee, $mois) {
    global $db;
    $client_b2b_id = (int) $client_b2b_id;
    $annee = (int) $annee;
    $mois = (int) $mois;
    if ($client_b2b_id <= 0 || $annee < 2000 || $mois < 1 || $mois > 12 || !factures_mensuelles_table_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT * FROM factures_mensuelles
            WHERE client_b2b_id = :c AND annee = :a AND mois = :m
            LIMIT 1
        ');
        $stmt->execute(['c' => $client_b2b_id, 'a' => $annee, 'm' => $mois]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Brouillon du mois civil en cours (si existe)
 */
function get_facture_mensuelle_brouillon_mois_courant($client_b2b_id) {
    $annee = (int) date('Y');
    $mois = (int) date('n');
    $fm = get_facture_mensuelle_by_client_month($client_b2b_id, $annee, $mois);
    if ($fm && ($fm['statut'] ?? '') === 'brouillon') {
        return $fm;
    }
    return false;
}

/**
 * Facture mensuelle du mois civil en cours pour ce client (tout statut : brouillon, validée, payée).
 * Sert au lien « Voir la facture » sur la fiche client après validation de la FM.
 */
function get_facture_mensuelle_mois_courant($client_b2b_id) {
    $annee = (int) date('Y');
    $mois = (int) date('n');
    $fm = get_facture_mensuelle_by_client_month($client_b2b_id, $annee, $mois);
    return $fm ?: false;
}

function generate_numero_facture_mensuelle() {
    global $db;
    try {
        $stmt = $db->query('SELECT MAX(id) AS m FROM factures_mensuelles');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $n = ($row && !empty($row['m'])) ? (int) $row['m'] + 1 : 1;
        return 'FM' . date('Ym') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'FM' . date('Ym') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
}

/**
 * Recalcule total_ht à partir des BL liés
 */
function recalc_total_facture_mensuelle($facture_mensuelle_id) {
    global $db;
    $facture_mensuelle_id = (int) $facture_mensuelle_id;
    if ($facture_mensuelle_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT COALESCE(SUM(b.total_ht), 0) AS t
            FROM facture_mensuelle_bl f
            INNER JOIN bons_livraison b ON b.id = f.bl_id
            WHERE f.facture_mensuelle_id = :fid
        ');
        $stmt->execute(['fid' => $facture_mensuelle_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (float) ($row['t'] ?? 0);
        $stmt = $db->prepare('UPDATE factures_mensuelles SET total_ht = :t, date_modification = NOW() WHERE id = :id');
        return $stmt->execute(['t' => $total, 'id' => $facture_mensuelle_id]);
    } catch (PDOException $e) {
        error_log('[recalc_total_facture_mensuelle] ' . $e->getMessage());
        return false;
    }
}

/**
 * Ajoute les BL validés non facturés au brouillon du mois, ou crée le brouillon.
 *
 * @return array{success:bool, facture_mensuelle_id?:int, message?:string}
 */
function generer_ou_maj_facture_mensuelle($client_b2b_id, $admin_id) {
    global $db;
    if (!factures_mensuelles_table_ok()) {
        return ['success' => false, 'message' => 'Tables factures mensuelles absentes. Exécutez la migration B2B.'];
    }
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0) {
        return ['success' => false, 'message' => 'Client invalide.'];
    }

    require_once __DIR__ . '/model_clients_b2b.php';
    if (!get_client_b2b_by_id($client_b2b_id)) {
        return ['success' => false, 'message' => 'Client introuvable.'];
    }

    $annee = (int) date('Y');
    $mois = (int) date('n');
    $bls = get_bl_valides_non_factures($client_b2b_id);

    $fm = get_facture_mensuelle_by_client_month($client_b2b_id, $annee, $mois);

    if (empty($bls)) {
        if ($fm && ($fm['statut'] ?? '') === 'brouillon') {
            return ['success' => true, 'facture_mensuelle_id' => (int) $fm['id'], 'message' => 'redirect_existing'];
        }
        if ($fm && in_array(($fm['statut'] ?? ''), ['validee', 'payee'], true)) {
            return ['success' => true, 'facture_mensuelle_id' => (int) $fm['id'], 'message' => 'redirect_existing_validee'];
        }
        return ['success' => false, 'message' => facture_mensuelle_message_aucun_bl($client_b2b_id)];
    }

    if ($fm && ($fm['statut'] ?? '') !== 'brouillon') {
        return ['success' => false, 'message' => 'Une facture pour ce mois existe déjà (validée ou payée). Contactez la comptabilité.'];
    }

    try {
        $db->beginTransaction();

        if (!$fm) {
            $numero = generate_numero_facture_mensuelle();
            $stmt = $db->prepare('
                INSERT INTO factures_mensuelles (
                    numero_facture, client_b2b_id, annee, mois, statut, total_ht,
                    date_emission, admin_createur_id, date_creation
                ) VALUES (
                    :numero, :cid, :an, :mo, \'brouillon\', 0,
                    NULL, :aid, NOW()
                )
            ');
            $stmt->execute([
                'numero' => $numero,
                'cid' => $client_b2b_id,
                'an' => $annee,
                'mo' => $mois,
                'aid' => $admin_id ? (int) $admin_id : null,
            ]);
            $fm_id = (int) $db->lastInsertId();
        } else {
            $fm_id = (int) $fm['id'];
        }

        $ins = $db->prepare('
            INSERT INTO facture_mensuelle_bl (facture_mensuelle_id, bl_id) VALUES (:fid, :bid)
        ');
        foreach ($bls as $bl) {
            $ins->execute(['fid' => $fm_id, 'bid' => (int) $bl['id']]);
        }

        recalc_total_facture_mensuelle($fm_id);

        $db->commit();
        return ['success' => true, 'facture_mensuelle_id' => $fm_id];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[generer_ou_maj_facture_mensuelle] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Enregistrement impossible (conflit ou données invalides).'];
    }
}

/**
 * IDs des BL liés à une facture mensuelle
 */
function get_bl_ids_facture_mensuelle($facture_mensuelle_id) {
    global $db;
    $facture_mensuelle_id = (int) $facture_mensuelle_id;
    if ($facture_mensuelle_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('SELECT bl_id FROM facture_mensuelle_bl WHERE facture_mensuelle_id = :id ORDER BY id ASC');
        $stmt->execute(['id' => $facture_mensuelle_id]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'bl_id'));
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Détail pour affichage : BL + lignes bl_lignes
 *
 * @return array{bl: array, lignes: array}[]
 */
function get_bls_et_lignes_facture_mensuelle($facture_mensuelle_id) {
    global $db;
    require_once __DIR__ . '/model_bl.php';
    $facture_mensuelle_id = (int) $facture_mensuelle_id;
    if ($facture_mensuelle_id <= 0) {
        return [];
    }
    $ids = get_bl_ids_facture_mensuelle($facture_mensuelle_id);
    $out = [];
    foreach ($ids as $bid) {
        $bl = get_bl_by_id($bid);
        if (!$bl) {
            continue;
        }
        $out[] = [
            'bl' => $bl,
            'lignes' => get_lignes_bl($bid),
        ];
    }
    return $out;
}

/**
 * Passe la facture en validée (transmission comptabilité)
 */
function valider_facture_mensuelle($facture_mensuelle_id) {
    global $db;
    $facture_mensuelle_id = (int) $facture_mensuelle_id;
    if ($facture_mensuelle_id <= 0) {
        return false;
    }
    $fm = get_facture_mensuelle_by_id($facture_mensuelle_id);
    if (!$fm || ($fm['statut'] ?? '') !== 'brouillon') {
        return false;
    }
    try {
        $stmt = $db->prepare('
            UPDATE factures_mensuelles
            SET statut = \'validee\',
                date_emission = CURDATE(),
                date_modification = NOW()
            WHERE id = :id AND statut = \'brouillon\'
        ');
        $stmt->execute(['id' => $facture_mensuelle_id]);
        $fm2 = get_facture_mensuelle_by_id($facture_mensuelle_id);
        return $fm2 && ($fm2['statut'] ?? '') === 'validee';
    } catch (PDOException $e) {
        error_log('[valider_facture_mensuelle] ' . $e->getMessage());
        return false;
    }
}

/**
 * Liste récente pour l’espace comptabilité
 */
function get_factures_mensuelles_recentes($limit = 30) {
    global $db;
    if (!factures_mensuelles_table_ok()) {
        return [];
    }
    $limit = max(1, min(200, (int) $limit));
    try {
        $stmt = $db->prepare('
            SELECT f.*, c.raison_sociale
            FROM factures_mensuelles f
            INNER JOIN clients_b2b c ON c.id = f.client_b2b_id
            ORDER BY f.date_creation DESC
            LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Factures mensuelles pour une période (annee + mois)
 */
function get_factures_mensuelles_par_mois($annee, $mois) {
    global $db;
    if (!factures_mensuelles_table_ok()) {
        return [];
    }
    $annee = (int) $annee;
    $mois = (int) $mois;
    if ($annee < 2000 || $mois < 1 || $mois > 12) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT f.*, c.raison_sociale
            FROM factures_mensuelles f
            INNER JOIN clients_b2b c ON c.id = f.client_b2b_id
            WHERE f.annee = :a AND f.mois = :m
            ORDER BY f.date_creation DESC, f.id DESC
        ');
        $stmt->execute(['a' => $annee, 'm' => $mois]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Somme HT et nombre de factures mensuelles pour une période
 *
 * @return array{somme_ht:float,nb_factures:int}
 */
function get_somme_et_nb_factures_mensuelles_mois($annee, $mois) {
    global $db;
    if (!factures_mensuelles_table_ok()) {
        return ['somme_ht' => 0.0, 'nb_factures' => 0];
    }
    $annee = (int) $annee;
    $mois = (int) $mois;
    if ($annee < 2000 || $mois < 1 || $mois > 12) {
        return ['somme_ht' => 0.0, 'nb_factures' => 0];
    }
    try {
        $stmt = $db->prepare('
            SELECT COALESCE(SUM(total_ht), 0) AS s, COUNT(*) AS n
            FROM factures_mensuelles
            WHERE annee = :a AND mois = :m
        ');
        $stmt->execute(['a' => $annee, 'm' => $mois]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'somme_ht' => (float) ($row['s'] ?? 0),
            'nb_factures' => (int) ($row['n'] ?? 0),
        ];
    } catch (PDOException $e) {
        return ['somme_ht' => 0.0, 'nb_factures' => 0];
    }
}
