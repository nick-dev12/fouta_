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
 * Colonne tva_incluse (migration add_factures_mensuelles_tva_incluse)
 */
function factures_mensuelles_tva_incluse_column_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!factures_mensuelles_table_ok()) {
        return false;
    }
    try {
        $db->query('SELECT tva_incluse FROM factures_mensuelles LIMIT 1');
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
    if (!function_exists('bl_sql_exclure_factures_payees')) {
        require_once __DIR__ . '/model_bl.php';
    }
    $filtre_payee = bl_sql_exclure_factures_payees('b');
    try {
        $stmt = $db->prepare('
            SELECT b.*
            FROM bons_livraison b
            LEFT JOIN facture_mensuelle_bl f ON f.bl_id = b.id
            WHERE b.client_b2b_id = :cid
              AND b.statut IN (\'valide\', \'paye\')
              AND f.id IS NULL
              ' . $filtre_payee . '
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
    if (!function_exists('bl_sql_exclure_factures_payees')) {
        require_once __DIR__ . '/model_bl.php';
    }
    $filtre_payee = bl_sql_exclure_factures_payees('b');
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM bons_livraison WHERE client_b2b_id = :cid');
        $stmt->execute(['cid' => $client_b2b_id]);
        $brouillon_total = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('
            SELECT COUNT(*) FROM bons_livraison b
            WHERE b.client_b2b_id = :cid AND b.statut IN (\'valide\', \'paye\')
            ' . $filtre_payee . '
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
        return 'Tous les bons de livraison validés sont déjà rattachés à une facture mensuelle ou marqués payés individuellement. Utilisez « Voir la facture » (brouillon du mois) ou ouvrez la facture concernée dans l’onglet Comptabilité — il n’y a rien de nouveau à ajouter.';
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

/**
 * Dernière facture mensuelle du client (tout statut), par date de création.
 */
function get_facture_mensuelle_derniere_pour_client($client_b2b_id) {
    global $db;
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0 || !factures_mensuelles_table_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT * FROM factures_mensuelles
            WHERE client_b2b_id = :c
            ORDER BY date_creation DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute(['c' => $client_b2b_id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        error_log('[get_facture_mensuelle_derniere_pour_client] ' . $e->getMessage());
        return false;
    }
}

/**
 * Prochain couple (année, mois) utilisable : slot vide ou facture en brouillon uniquement.
 * Les mois déjà validés ou payés sont ignorés en avançant mois par mois.
 *
 * @return array{annee:int, mois:int}|null
 */
function facture_mensuelle_trouver_periode_libre_pour_client($client_b2b_id, $annee_depart, $mois_depart) {
    $client_b2b_id = (int) $client_b2b_id;
    $annee = (int) $annee_depart;
    $mois = (int) $mois_depart;
    if ($client_b2b_id <= 0 || $annee < 2000 || $mois < 1 || $mois > 12) {
        return null;
    }
    for ($i = 0; $i < 48; $i++) {
        $fm = get_facture_mensuelle_by_client_month($client_b2b_id, $annee, $mois);
        if (!$fm) {
            return ['annee' => $annee, 'mois' => $mois];
        }
        $st = (string) ($fm['statut'] ?? '');
        if ($st === 'brouillon') {
            return ['annee' => $annee, 'mois' => $mois];
        }
        $mois++;
        if ($mois > 12) {
            $mois = 1;
            $annee++;
        }
    }
    return null;
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
 * Ajoute les BL validés non facturés au brouillon, ou crée le brouillon pour la période cible.
 *
 * Période :
 * - Si $annee_cible et $mois_cible sont fournis (1–12, année ≥ 2000), la facture porte cette étiquette mois/année.
 * - Sinon : mois civil en cours ; si une facture validée/payée occupe déjà ce créneau et qu’il reste des BL à facturer,
 *   la première période libre (mois suivants) est utilisée automatiquement (contrainte unique client + mois en base).
 *
 * @param int|null $annee_cible
 * @param int|null $mois_cible
 * @param bool $tva_incluse Si true : TTC = somme HT des BL + TVA en sus. Si false : montant facturé = somme HT, TVA décomposée « incluse » pour mention légale.
 * @return array{success:bool, facture_mensuelle_id?:int, message?:string}
 */
function generer_ou_maj_facture_mensuelle($client_b2b_id, $admin_id, $annee_cible = null, $mois_cible = null, $tva_incluse = false) {
    global $db;
    if (!factures_mensuelles_table_ok()) {
        return ['success' => false, 'message' => 'Tables factures mensuelles absentes. Exécutez la migration B2B.'];
    }
    $tva_flag = (bool) $tva_incluse;
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0) {
        return ['success' => false, 'message' => 'Client invalide.'];
    }

    require_once __DIR__ . '/model_clients_b2b.php';
    if (!get_client_b2b_by_id($client_b2b_id)) {
        return ['success' => false, 'message' => 'Client introuvable.'];
    }

    $bls = get_bl_valides_non_factures($client_b2b_id);

    $periode_manuelle = $annee_cible !== null && $mois_cible !== null
        && (int) $annee_cible >= 2000
        && (int) $mois_cible >= 1
        && (int) $mois_cible <= 12;

    if ($periode_manuelle) {
        $annee = (int) $annee_cible;
        $mois = (int) $mois_cible;
    } else {
        $annee = (int) date('Y');
        $mois = (int) date('n');
    }

    $fm = get_facture_mensuelle_by_client_month($client_b2b_id, $annee, $mois);

    if (!$periode_manuelle && !empty($bls) && $fm && in_array((string) ($fm['statut'] ?? ''), ['validee', 'payee'], true)) {
        $mois_suiv = $mois + 1;
        $an_suiv = $annee;
        if ($mois_suiv > 12) {
            $mois_suiv = 1;
            $an_suiv++;
        }
        $libre = facture_mensuelle_trouver_periode_libre_pour_client($client_b2b_id, $an_suiv, $mois_suiv);
        if (!$libre) {
            return ['success' => false, 'message' => 'Aucune période libre trouvée pour de nouveaux brouillons (plage dépassée). Contactez l’administrateur.'];
        }
        $annee = $libre['annee'];
        $mois = $libre['mois'];
        $fm = get_facture_mensuelle_by_client_month($client_b2b_id, $annee, $mois);
    }

    if ($periode_manuelle && !empty($bls) && $fm && in_array((string) ($fm['statut'] ?? ''), ['validee', 'payee'], true)) {
        return ['success' => false, 'message' => 'Ce mois a déjà une facture validée ou payée. Choisissez un autre mois ou utilisez « Générer » sans période pour laisser le système proposer le prochain créneau libre.'];
    }

    if (empty($bls)) {
        if ($fm && ($fm['statut'] ?? '') === 'brouillon') {
            return ['success' => true, 'facture_mensuelle_id' => (int) $fm['id'], 'message' => 'redirect_existing'];
        }
        if ($fm && in_array(($fm['statut'] ?? ''), ['validee', 'payee'], true)) {
            return ['success' => true, 'facture_mensuelle_id' => (int) $fm['id'], 'message' => 'redirect_existing_validee'];
        }
        if (!$fm && !$periode_manuelle) {
            $derniere = get_facture_mensuelle_derniere_pour_client($client_b2b_id);
            if ($derniere) {
                return ['success' => true, 'facture_mensuelle_id' => (int) $derniere['id'], 'message' => 'redirect_derniere'];
            }
        }
        return ['success' => false, 'message' => facture_mensuelle_message_aucun_bl($client_b2b_id)];
    }

    if ($fm && ($fm['statut'] ?? '') !== 'brouillon') {
        return ['success' => false, 'message' => 'Une facture pour cette période existe déjà (validée ou payée). Choisissez un autre mois ou régénérez sans date pour utiliser le prochain créneau libre.'];
    }

    try {
        $db->beginTransaction();

        if (!$fm) {
            $numero = generate_numero_facture_mensuelle();
            if (factures_mensuelles_tva_incluse_column_ok()) {
                $stmt = $db->prepare('
                    INSERT INTO factures_mensuelles (
                        numero_facture, client_b2b_id, annee, mois, statut, total_ht, tva_incluse,
                        date_emission, admin_createur_id, date_creation
                    ) VALUES (
                        :numero, :cid, :an, :mo, \'brouillon\', 0, :tva,
                        NULL, :aid, NOW()
                    )
                ');
                $stmt->execute([
                    'numero' => $numero,
                    'cid' => $client_b2b_id,
                    'an' => $annee,
                    'mo' => $mois,
                    'tva' => $tva_flag ? 1 : 0,
                    'aid' => $admin_id ? (int) $admin_id : null,
                ]);
            } else {
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
            }
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

        if (factures_mensuelles_tva_incluse_column_ok()) {
            $db->prepare('UPDATE factures_mensuelles SET tva_incluse = :t, date_modification = NOW() WHERE id = :id')
                ->execute(['t' => $tva_flag ? 1 : 0, 'id' => $fm_id]);
        }

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
 * Depuis un brouillon : marque la facture comme payée (comptabilité), fixe date d’émission et date de paiement.
 *
 * @return bool
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
            SET statut = \'payee\',
                date_emission = COALESCE(date_emission, CURDATE()),
                date_paiement = CURDATE(),
                date_modification = NOW()
            WHERE id = :id AND statut = \'brouillon\'
        ');
        $stmt->execute(['id' => $facture_mensuelle_id]);
        $fm2 = get_facture_mensuelle_by_id($facture_mensuelle_id);
        return $fm2 && ($fm2['statut'] ?? '') === 'payee';
    } catch (PDOException $e) {
        error_log('[valider_facture_mensuelle] ' . $e->getMessage());
        return false;
    }
}

/**
 * Facture déjà « validée » historique (statut validee) : enregistre le paiement.
 *
 * @return bool
 */
function marquer_facture_mensuelle_comme_payee($facture_mensuelle_id) {
    global $db;
    $facture_mensuelle_id = (int) $facture_mensuelle_id;
    if ($facture_mensuelle_id <= 0) {
        return false;
    }
    $fm = get_facture_mensuelle_by_id($facture_mensuelle_id);
    if (!$fm || ($fm['statut'] ?? '') !== 'validee') {
        return false;
    }
    try {
        $stmt = $db->prepare('
            UPDATE factures_mensuelles
            SET statut = \'payee\',
                date_paiement = CURDATE(),
                date_modification = NOW()
            WHERE id = :id AND statut = \'validee\'
        ');
        $stmt->execute(['id' => $facture_mensuelle_id]);
        $fm2 = get_facture_mensuelle_by_id($facture_mensuelle_id);
        return $fm2 && ($fm2['statut'] ?? '') === 'payee';
    } catch (PDOException $e) {
        error_log('[marquer_facture_mensuelle_comme_payee] ' . $e->getMessage());
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

/**
 * Factures mensuelles dont le mois de facturation chevauche [date_debut, date_fin]
 * (période = premier jour du mois (annee, mois) … dernier jour du même mois).
 *
 * @return array<int, array<string, mixed>>
 */
function get_factures_mensuelles_chevauchant_periode($date_debut, $date_fin, $limit = 500) {
    global $db;
    if (!factures_mensuelles_table_ok()) {
        return [];
    }
    $d1 = trim((string) $date_debut);
    $d2 = trim((string) $date_fin);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d2)) {
        return [];
    }
    if (strcmp($d1, $d2) > 0) {
        $t = $d1;
        $d1 = $d2;
        $d2 = $t;
    }
    $limit = max(1, min(5000, (int) $limit));
    try {
        $stmt = $db->prepare('
            SELECT f.*, c.raison_sociale
            FROM factures_mensuelles f
            INNER JOIN clients_b2b c ON c.id = f.client_b2b_id
            WHERE LAST_DAY(STR_TO_DATE(CONCAT(f.annee, \'-\', LPAD(f.mois, 2, \'0\'), \'-01\'), \'%Y-%m-%d\')) >= :d1
              AND STR_TO_DATE(CONCAT(f.annee, \'-\', LPAD(f.mois, 2, \'0\'), \'-01\'), \'%Y-%m-%d\') <= :d2
            ORDER BY f.annee DESC, f.mois DESC, f.id DESC
            LIMIT ' . $limit
        );
        $stmt->execute(['d1' => $d1, 'd2' => $d2]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[get_factures_mensuelles_chevauchant_periode] ' . $e->getMessage());
        return [];
    }
}

/**
 * Somme HT et nombre de factures mensuelles sur une plage (même règle de chevauchement)
 *
 * @return array{somme_ht:float,nb_factures:int}
 */
function get_somme_et_nb_factures_mensuelles_periode($date_debut, $date_fin) {
    global $db;
    if (!factures_mensuelles_table_ok()) {
        return ['somme_ht' => 0.0, 'nb_factures' => 0];
    }
    $d1 = trim((string) $date_debut);
    $d2 = trim((string) $date_fin);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d2)) {
        return ['somme_ht' => 0.0, 'nb_factures' => 0];
    }
    if (strcmp($d1, $d2) > 0) {
        $t = $d1;
        $d1 = $d2;
        $d2 = $t;
    }
    try {
        $stmt = $db->prepare('
            SELECT COALESCE(SUM(total_ht), 0) AS s, COUNT(*) AS n
            FROM factures_mensuelles f
            WHERE LAST_DAY(STR_TO_DATE(CONCAT(f.annee, \'-\', LPAD(f.mois, 2, \'0\'), \'-01\'), \'%Y-%m-%d\')) >= :d1
              AND STR_TO_DATE(CONCAT(f.annee, \'-\', LPAD(f.mois, 2, \'0\'), \'-01\'), \'%Y-%m-%d\') <= :d2
        ');
        $stmt->execute(['d1' => $d1, 'd2' => $d2]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'somme_ht' => (float) ($row['s'] ?? 0),
            'nb_factures' => (int) ($row['n'] ?? 0),
        ];
    } catch (PDOException $e) {
        error_log('[get_somme_et_nb_factures_mensuelles_periode] ' . $e->getMessage());
        return ['somme_ht' => 0.0, 'nb_factures' => 0];
    }
}

/**
 * Nombre de BL du client rattachés à une facture mensuelle validée ou payée (hors brouillon).
 */
function count_bl_fm_validees_ou_payees_pour_client($client_b2b_id) {
    global $db;
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0 || !factures_mensuelles_table_ok()) {
        return 0;
    }
    try {
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT b.id) FROM bons_livraison b
            INNER JOIN facture_mensuelle_bl fmb ON fmb.bl_id = b.id
            INNER JOIN factures_mensuelles fm ON fm.id = fmb.facture_mensuelle_id
            WHERE b.client_b2b_id = :cid
              AND fm.statut IN (\'validee\', \'payee\')
        ');
        $stmt->execute(['cid' => $client_b2b_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[count_bl_fm_validees_ou_payees_pour_client] ' . $e->getMessage());
        return 0;
    }
}

/**
 * Nombre de BL du client rattachés à une facture mensuelle (tout statut FM : brouillon, impayée, payée).
 */
function count_bl_lies_fm_tout_statut_pour_client($client_b2b_id) {
    global $db;
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0 || !factures_mensuelles_table_ok()) {
        return 0;
    }
    try {
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT b.id) FROM bons_livraison b
            INNER JOIN facture_mensuelle_bl fmb ON fmb.bl_id = b.id
            INNER JOIN factures_mensuelles fm ON fm.id = fmb.facture_mensuelle_id
            WHERE b.client_b2b_id = :cid
              AND fm.statut IN (\'brouillon\', \'validee\', \'payee\')
        ');
        $stmt->execute(['cid' => $client_b2b_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[count_bl_lies_fm_tout_statut_pour_client] ' . $e->getMessage());
        return 0;
    }
}

/**
 * BL rattachés à une facture mensuelle (brouillon, validée ou payée), groupés par client B2B.
 *
 * @return list<array{client: array<string, mixed>, bls: list<array<string, mixed>>}>
 */
function get_bl_fm_archive_groupes_par_client() {
    global $db;
    if (!factures_mensuelles_table_ok()) {
        return [];
    }
    require_once __DIR__ . '/model_bl.php';
    if (!function_exists('bl_tables_available') || !bl_tables_available()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT b.*, b.statut AS bl_statut,
                   c.id AS client_b2b_id,
                   c.raison_sociale, c.nom_contact, c.prenom_contact, c.telephone, c.email, c.adresse,
                   fm.id AS facture_mensuelle_id, fm.numero_facture AS fm_numero_facture,
                   fm.statut AS fm_statut, fm.annee AS fm_annee, fm.mois AS fm_mois,
                   fm.tva_incluse AS fm_tva_incluse, fm.total_ht AS fm_total_ht
            FROM bons_livraison b
            INNER JOIN facture_mensuelle_bl fmb ON fmb.bl_id = b.id
            INNER JOIN factures_mensuelles fm ON fm.id = fmb.facture_mensuelle_id
            INNER JOIN clients_b2b c ON c.id = b.client_b2b_id
            WHERE fm.statut IN (\'brouillon\', \'validee\', \'payee\')
            ORDER BY c.raison_sociale ASC, b.date_creation DESC, b.id DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $i => $r) {
            $rows[$i] = bl_row_apply_statut_bl($r);
        }
        $groupes = [];
        foreach ($rows as $r) {
            $cid = (int) ($r['client_b2b_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (!isset($groupes[$cid])) {
                $groupes[$cid] = [
                    'client' => [
                        'id' => $cid,
                        'raison_sociale' => $r['raison_sociale'] ?? '',
                        'nom_contact' => $r['nom_contact'] ?? '',
                        'prenom_contact' => $r['prenom_contact'] ?? '',
                        'telephone' => $r['telephone'] ?? '',
                        'email' => $r['email'] ?? '',
                        'adresse' => $r['adresse'] ?? '',
                    ],
                    'bls' => [],
                ];
            }
            $groupes[$cid]['bls'][] = $r;
        }
        return array_values($groupes);
    } catch (PDOException $e) {
        error_log('[get_bl_fm_archive_groupes_par_client] ' . $e->getMessage());
        return [];
    }
}

/**
 * BL liés à une facture mensuelle (brouillon, validée ou payée) pour une facture et un client donnés.
 *
 * @return list<array<string, mixed>>
 */
function get_bl_fm_archive_pour_fm_et_client($facture_mensuelle_id, $client_b2b_id) {
    global $db;
    $facture_mensuelle_id = (int) $facture_mensuelle_id;
    $client_b2b_id = (int) $client_b2b_id;
    if ($facture_mensuelle_id <= 0 || $client_b2b_id <= 0 || !factures_mensuelles_table_ok()) {
        return [];
    }
    require_once __DIR__ . '/model_bl.php';
    if (!function_exists('bl_tables_available') || !bl_tables_available()) {
        return [];
    }
    $fm = get_facture_mensuelle_by_id($facture_mensuelle_id);
    if (!$fm) {
        return [];
    }
    if ((int) ($fm['client_b2b_id'] ?? 0) !== $client_b2b_id) {
        return [];
    }
    if (!in_array((string) ($fm['statut'] ?? ''), ['brouillon', 'validee', 'payee'], true)) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT b.*, b.statut AS bl_statut,
                   c.id AS client_b2b_id,
                   c.raison_sociale, c.nom_contact, c.prenom_contact, c.telephone, c.email, c.adresse,
                   fm.id AS facture_mensuelle_id, fm.numero_facture AS fm_numero_facture,
                   fm.statut AS fm_statut, fm.annee AS fm_annee, fm.mois AS fm_mois,
                   fm.tva_incluse AS fm_tva_incluse, fm.total_ht AS fm_total_ht
            FROM bons_livraison b
            INNER JOIN facture_mensuelle_bl fmb ON fmb.bl_id = b.id
            INNER JOIN factures_mensuelles fm ON fm.id = fmb.facture_mensuelle_id
            INNER JOIN clients_b2b c ON c.id = b.client_b2b_id
            WHERE fmb.facture_mensuelle_id = :fid
              AND b.client_b2b_id = :cid
              AND fm.statut IN (\'brouillon\', \'validee\', \'payee\')
            ORDER BY b.date_creation DESC, b.id DESC
        ');
        $stmt->execute(['fid' => $facture_mensuelle_id, 'cid' => $client_b2b_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $i => $r) {
            $rows[$i] = bl_row_apply_statut_bl($r);
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[get_bl_fm_archive_pour_fm_et_client] ' . $e->getMessage());
        return [];
    }
}
