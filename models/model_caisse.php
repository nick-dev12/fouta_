<?php
/**
 * Caisse magasin (panier en session, enregistrement vente + stock)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_produits.php';
require_once __DIR__ . '/model_mouvements_stock.php';

/** @var string Clé session panier caisse */
define('CAISSE_SESSION_KEY', 'caisse_cart_v1');

/**
 * Taux TVA pour affichage HT / TVA / TTC (prix produits considérés TTC)
 * Modifiez selon votre fiscalité.
 */
if (!defined('CAISSE_TVA_TAUX_POURCENT')) {
    define('CAISSE_TVA_TAUX_POURCENT', 18.0);
}

/**
 * Décompose un montant TTC en HT + TVA (TVA incluse)
 *
 * @return array{ht:float, tva:float, ttc:float}
 */
function caisse_decomposer_ttc($montant_ttc)
{
    $ttc = max(0, round((float) $montant_ttc, 2));
    $t = (float) CAISSE_TVA_TAUX_POURCENT / 100.0;
    if ($t <= 0) {
        return ['ht' => $ttc, 'tva' => 0.0, 'ttc' => $ttc];
    }
    $ht = round($ttc / (1 + $t), 2);
    $tva = round($ttc - $ht, 2);
    return ['ht' => $ht, 'tva' => $tva, 'ttc' => $ttc];
}

/**
 * Prix unitaire affiché en caisse (promotion si applicable)
 */
function caisse_prix_unitaire_produit(array $p)
{
    $promo = isset($p['prix_promotion']) && $p['prix_promotion'] !== '' && (float) $p['prix_promotion'] > 0
        ? (float) $p['prix_promotion']
        : null;
    $base = (float) ($p['prix'] ?? 0);
    return $promo !== null ? $promo : $base;
}

/**
 * Clé de ligne unique par produit (une ligne fusionnée par article)
 */
function caisse_line_key($produit_id)
{
    return 'p' . (int) $produit_id;
}

function caisse_cart_get()
{
    if (!isset($_SESSION[CAISSE_SESSION_KEY]) || !is_array($_SESSION[CAISSE_SESSION_KEY])) {
        $_SESSION[CAISSE_SESSION_KEY] = [
            'lines' => [],
            'remise_globale_pct' => 0.0,
        ];
    }
    if (!isset($_SESSION[CAISSE_SESSION_KEY]['lines']) || !is_array($_SESSION[CAISSE_SESSION_KEY]['lines'])) {
        $_SESSION[CAISSE_SESSION_KEY]['lines'] = [];
    }
    if (!isset($_SESSION[CAISSE_SESSION_KEY]['remise_globale_pct'])) {
        $_SESSION[CAISSE_SESSION_KEY]['remise_globale_pct'] = 0.0;
    }
    return $_SESSION[CAISSE_SESSION_KEY];
}

function caisse_cart_save(array $cart)
{
    $_SESSION[CAISSE_SESSION_KEY] = $cart;
}

function caisse_cart_clear()
{
    unset($_SESSION[CAISSE_SESSION_KEY]);
}

/**
 * Sous-total ligne après remise ligne, puis total après remise globale
 */
function caisse_compute_totals(array $cart)
{
    $sous = 0.0;
    foreach ($cart['lines'] as $line) {
        $pu = (float) ($line['prix_unitaire'] ?? 0);
        $q = max(0, (int) ($line['quantite'] ?? 0));
        $rl = min(100, max(0, (float) ($line['remise_ligne_pct'] ?? 0)));
        $ligne_ht = $pu * $q * (1 - $rl / 100);
        $sous += $ligne_ht;
    }
    $rg = min(100, max(0, (float) ($cart['remise_globale_pct'] ?? 0)));
    $total_ttc = $sous * (1 - $rg / 100);
    $total_ttc = round($total_ttc, 2);
    $dec = caisse_decomposer_ttc($total_ttc);
    return [
        'sous_total' => round($sous, 2),
        'remise_globale_pct' => $rg,
        'total' => $total_ttc,
        'total_ttc' => $total_ttc,
        'total_ht' => $dec['ht'],
        'montant_tva' => $dec['tva'],
        'taux_tva_pourcent' => (float) CAISSE_TVA_TAUX_POURCENT,
    ];
}

/**
 * Tables caisse présentes en base
 */
function caisse_tables_exist()
{
    global $db;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'caisse_ventes'");
        $cache = $stmt && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Résout un code saisi : ID numérique, FPLxxxxxx, 5 derniers chiffres, ou recherche nom (1 seul résultat requis pour auto-ajout)
 *
 * @return array{ok:bool, produit?:array, error?:string, ambigus?:array}
 */
function caisse_resoudre_produit_par_code($input)
{
    $t = trim((string) $input);
    if ($t === '') {
        return ['ok' => false, 'error' => 'Code ou recherche vide.'];
    }

    if (ctype_digit($t) && strlen($t) <= 9) {
        $id = (int) $t;
        if ($id > 0) {
            $p = get_produit_by_id($id);
            if ($p && ($p['statut'] ?? '') === 'actif') {
                return ['ok' => true, 'produit' => $p];
            }
        }
    }

    $found = search_produits($t, 0, 15);
    if (count($found) === 1) {
        return ['ok' => true, 'produit' => $found[0]];
    }
    if (count($found) === 0) {
        return ['ok' => false, 'error' => 'Aucun produit actif trouvé pour ce code ou cette recherche.'];
    }
    return ['ok' => false, 'error' => 'Plusieurs produits correspondent : affinez la recherche ou choisissez dans la liste.', 'ambigus' => $found];
}

/**
 * Ajoute ou incrémente une ligne produit
 *
 * @return array{ok:bool, error?:string}
 */
function caisse_cart_add_produit(array &$cart, array $produit, $quantite = 1)
{
    $quantite = max(1, (int) $quantite);
    if (($produit['statut'] ?? '') !== 'actif') {
        return ['ok' => false, 'error' => 'Ce produit n\'est pas disponible à la vente.'];
    }
    $stock = (int) ($produit['stock'] ?? 0);
    $key = caisse_line_key((int) $produit['id']);
    $ex = isset($cart['lines'][$key]) ? (int) $cart['lines'][$key]['quantite'] : 0;
    if ($stock < $ex + $quantite) {
        return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($produit['nom'] ?? '') . ' » (disponible : ' . $stock . ').'];
    }

    $pu = caisse_prix_unitaire_produit($produit);
    if ($pu <= 0) {
        return ['ok' => false, 'error' => 'Prix invalide pour ce produit.'];
    }

    if (isset($cart['lines'][$key])) {
        $cart['lines'][$key]['quantite'] = $ex + $quantite;
        $cart['lines'][$key]['prix_unitaire'] = $pu;
        $cart['lines'][$key]['nom'] = $produit['nom'] ?? '';
    } else {
        $cart['lines'][$key] = [
            'produit_id' => (int) $produit['id'],
            'nom' => $produit['nom'] ?? '',
            'prix_unitaire' => $pu,
            'quantite' => $quantite,
            'remise_ligne_pct' => 0.0,
        ];
    }
    return ['ok' => true];
}

/**
 * Numéro provisoire (INSERT) — remplacé par caisse_ventes_appliquer_numero_officiel() juste après création
 */
function caisse_generer_numero_ticket_provisoire()
{
    return 'TMP-' . strtoupper(bin2hex(random_bytes(8)));
}

/**
 * Applique le numéro définitif : TKT + AAAAMMJJ + id (6 chiffres), ex. TKT20260331000006
 *
 * @return string Numéro final
 */
function caisse_ventes_appliquer_numero_officiel($vente_id)
{
    global $db;
    $vente_id = (int) $vente_id;
    if ($vente_id <= 0) {
        return '';
    }
    try {
        $stmt = $db->prepare("
            UPDATE caisse_ventes SET
                numero_ticket = CONCAT('TKT', DATE_FORMAT(date_vente, '%Y%m%d'), LPAD(id, 6, '0'))
            WHERE id = :id
        ");
        $stmt->execute(['id' => $vente_id]);
        $st = $db->prepare('SELECT numero_ticket FROM caisse_ventes WHERE id = :id LIMIT 1');
        $st->execute(['id' => $vente_id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? (string) $r['numero_ticket'] : '';
    } catch (PDOException $e) {
        error_log('[caisse_ventes_appliquer_numero_officiel] ' . $e->getMessage());
        return '';
    }
}

/**
 * Colonne reference (recherche caisse 5 chiffres) présente
 */
function caisse_reference_caisse_column_exists()
{
    global $db;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        if (!caisse_tables_exist()) {
            return $cache;
        }
        $st = $db->query("SHOW COLUMNS FROM caisse_ventes LIKE 'reference'");
        $cache = $st && $st->rowCount() > 0;
    } catch (PDOException $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Extrait une référence caisse 5 chiffres (complétée par zéros à gauche si 1–4 chiffres saisis)
 *
 * @return string|null null si la saisie ne correspond pas (ex. contient des lettres ou plus de 5 chiffres)
 */
function caisse_normaliser_saisie_reference_caisse($input)
{
    $d = preg_replace('/\D/u', '', (string) $input);
    if ($d === '' || strlen($d) > 5) {
        return null;
    }
    return str_pad($d, 5, '0', STR_PAD_LEFT);
}

/**
 * Ticket en attente par référence courte (encaissement)
 */
function caisse_get_vente_par_reference_caisse($ref_5)
{
    if (!preg_match('/^\d{5}$/', (string) $ref_5) || !caisse_tables_exist() || !caisse_reference_caisse_column_exists()) {
        return null;
    }
    global $db;
    try {
        $stmt = $db->prepare('SELECT id FROM caisse_ventes WHERE reference = :r AND statut = \'en_attente\' LIMIT 1');
        $stmt->execute(['r' => $ref_5]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return caisse_get_vente_by_id((int) $row['id']);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Attribue une référence aléatoire unique (00000–99999) à un ticket en attente
 *
 * @return string Référence ou chaîne vide si échec / colonne absente
 */
function caisse_ventes_assigner_reference_caisse($vente_id)
{
    global $db;
    $vente_id = (int) $vente_id;
    if ($vente_id <= 0 || !caisse_reference_caisse_column_exists()) {
        return '';
    }
    for ($try = 0; $try < 64; $try++) {
        $r = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        try {
            $st = $db->prepare("
                UPDATE caisse_ventes SET reference = :r
                WHERE id = :id AND statut = 'en_attente' AND (reference IS NULL OR reference = '')
            ");
            $st->execute(['r' => $r, 'id' => $vente_id]);
            if ($st->rowCount() === 1) {
                return $r;
            }
            $chk = $db->prepare('SELECT reference FROM caisse_ventes WHERE id = :id LIMIT 1');
            $chk->execute(['id' => $vente_id]);
            $ex = $chk->fetch(PDO::FETCH_ASSOC);
            if ($ex && $ex['reference'] !== null && (string) $ex['reference'] !== '') {
                return (string) $ex['reference'];
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '1062') !== false || stripos($msg, 'Duplicate') !== false) {
                continue;
            }
            error_log('[caisse_ventes_assigner_reference_caisse] ' . $msg);
            return '';
        }
    }
    return '';
}

/**
 * Affichage court demandé : TKT + date (AAAAMMJJ), ex. TKT20260331
 */
function caisse_ticket_numero_date_public(array $row)
{
    $d = $row['date_vente'] ?? '';
    if ($d !== '') {
        return 'TKT' . date('Ymd', strtotime($d));
    }
    return 'TKT';
}

/**
 * Valeur encodée dans le code-barres (scan à la caisse) — alignée sur numero_ticket officiel ou legacy
 */
function caisse_ticket_valeur_code_barres(array $row)
{
    $n = strtoupper(trim((string) ($row['numero_ticket'] ?? '')));
    if ($n !== '' && strpos($n, 'TMP-') !== 0) {
        return $n;
    }
    $id = (int) ($row['id'] ?? 0);
    $d = $row['date_vente'] ?? '';
    if ($id > 0 && $d !== '') {
        return 'TKT' . date('Ymd', strtotime($d)) . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
    return $n;
}

/**
 * Scan zone A : si le code commence par TKT et correspond à un ticket, retourne l’id vente (sinon 0)
 */
function caisse_trouver_vente_id_par_code_scan($input)
{
    if (!caisse_tables_exist()) {
        return 0;
    }
    $raw = trim((string) $input);
    $compact = caisse_normaliser_saisie_numero_ticket($raw);
    if ($compact === '' || strncmp($compact, 'TKT', 3) !== 0) {
        return 0;
    }
    $v = caisse_get_vente_by_numero($raw);
    return $v ? (int) $v['id'] : 0;
}

/**
 * Statut ticket (ligne BDD) — rétrocompatible si colonne absente
 */
function caisse_vente_statut(array $row)
{
    $s = $row['statut'] ?? null;
    return ($s === 'en_attente') ? 'en_attente' : 'paye';
}

/**
 * Ticket en attente d’encaissement : lignes en BDD, pas de mouvement de stock
 *
 * @return array{ok:bool, vente_id?:int, numero_ticket?:string, error?:string}
 */
function caisse_creer_ticket_en_attente($admin_id, array $cart)
{
    global $db;

    if (!caisse_tables_exist()) {
        return ['ok' => false, 'error' => 'Tables caisse absentes : exécutez la migration create_caisse_tables.sql.'];
    }

    $totals = caisse_compute_totals($cart);
    if ($totals['total'] <= 0 || empty($cart['lines'])) {
        return ['ok' => false, 'error' => 'Panier vide ou total invalide.'];
    }

    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return ['ok' => false, 'error' => 'Session administrateur invalide.'];
    }

    foreach ($cart['lines'] as $line) {
        $pid = (int) ($line['produit_id'] ?? 0);
        $q = (int) ($line['quantite'] ?? 0);
        if ($pid <= 0 || $q <= 0) {
            return ['ok' => false, 'error' => 'Ligne de panier invalide.'];
        }
        $p = get_produit_by_id($pid);
        if (!$p || ($p['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Produit introuvable ou inactif.'];
        }
        $stock = (int) ($p['stock'] ?? 0);
        if ($stock < $q) {
            return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($p['nom'] ?? '#') . ' ».'];
        }
    }

    $numero_provisoire = caisse_generer_numero_ticket_provisoire();
    $montant_total = $totals['total'];
    $rg = (float) ($cart['remise_globale_pct'] ?? 0);

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO caisse_ventes (
                admin_id, caissier_id, numero_ticket, montant_total, remise_globale_pct, mode_paiement,
                montant_especes, montant_carte, montant_mobile_money, montant_recu, monnaie_rendue, notes,
                statut, date_vente, date_encaissement
            ) VALUES (
                :admin_id, NULL, :numero_ticket, :montant_total, :remise_globale_pct, 'especes',
                NULL, NULL, NULL, NULL, NULL, NULL,
                'en_attente', NOW(), NULL
            )
        ");
        $stmt->execute([
            'admin_id' => $admin_id,
            'numero_ticket' => $numero_provisoire,
            'montant_total' => $montant_total,
            'remise_globale_pct' => $rg,
        ]);
        $vente_id = (int) $db->lastInsertId();

        $stmtL = $db->prepare("
            INSERT INTO caisse_vente_lignes (vente_id, produit_id, designation, quantite, prix_unitaire, remise_ligne_pct, total_ligne)
            VALUES (:vente_id, :produit_id, :designation, :quantite, :prix_unitaire, :remise_ligne_pct, :total_ligne)
        ");

        foreach ($cart['lines'] as $line) {
            $pid = (int) ($line['produit_id'] ?? 0);
            $q = (int) ($line['quantite'] ?? 0);
            $pu = (float) ($line['prix_unitaire'] ?? 0);
            $rl = min(100, max(0, (float) ($line['remise_ligne_pct'] ?? 0)));
            $designation = $line['nom'] ?? '';
            $total_ligne = round($pu * $q * (1 - $rl / 100), 2);

            $stmtL->execute([
                'vente_id' => $vente_id,
                'produit_id' => $pid,
                'designation' => $designation,
                'quantite' => $q,
                'prix_unitaire' => $pu,
                'remise_ligne_pct' => $rl,
                'total_ligne' => $total_ligne,
            ]);
        }

        $numero_final = caisse_ventes_appliquer_numero_officiel($vente_id);
        $ref_caisse = caisse_ventes_assigner_reference_caisse($vente_id);
        $db->commit();
        return [
            'ok' => true,
            'vente_id' => $vente_id,
            'numero_ticket' => ($numero_final !== '' ? $numero_final : $numero_provisoire),
            'reference_caisse' => $ref_caisse,
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_creer_ticket_en_attente] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de la création du ticket. Vérifiez la migration alter_caisse_ventes_statut_encaissement.sql.'];
    }
}

/**
 * Finalise un ticket en_attente : paiement, statut paye, stock, mouvements
 *
 * @return array{ok:bool, vente_id?:int, numero_ticket?:string, error?:string}
 */
function caisse_finaliser_vente_en_attente($vente_id, $caissier_admin_id, $mode_paiement, array $paiement_details)
{
    global $db;

    if (!caisse_tables_exist()) {
        return ['ok' => false, 'error' => 'Tables caisse absentes.'];
    }

    $vente_id = (int) $vente_id;
    $caissier_admin_id = (int) $caissier_admin_id;
    if ($vente_id <= 0 || $caissier_admin_id <= 0) {
        return ['ok' => false, 'error' => 'Références invalides.'];
    }

    $v = caisse_get_vente_by_id($vente_id);
    if (!$v) {
        return ['ok' => false, 'error' => 'Ticket introuvable.'];
    }
    if (caisse_vente_statut($v) !== 'en_attente') {
        return ['ok' => false, 'error' => 'Ce ticket est déjà encaissé ou n\'est pas en attente.'];
    }

    $cart = [
        'lines' => [],
        'remise_globale_pct' => (float) ($v['remise_globale_pct'] ?? 0),
    ];
    foreach ($v['lignes'] as $lg) {
        $key = caisse_line_key((int) $lg['produit_id']);
        $cart['lines'][$key] = [
            'produit_id' => (int) $lg['produit_id'],
            'nom' => $lg['designation'] ?? '',
            'prix_unitaire' => (float) ($lg['prix_unitaire'] ?? 0),
            'quantite' => (int) ($lg['quantite'] ?? 0),
            'remise_ligne_pct' => (float) ($lg['remise_ligne_pct'] ?? 0),
        ];
    }

    $totals = caisse_compute_totals($cart);
    $montant_total = $totals['total'];
    if ($montant_total <= 0 || empty($cart['lines'])) {
        return ['ok' => false, 'error' => 'Ticket invalide.'];
    }

    $modes_ok = ['especes', 'carte', 'mobile_money', 'cheque', 'mixte', 'autre'];
    if (!in_array($mode_paiement, $modes_ok, true)) {
        return ['ok' => false, 'error' => 'Mode de paiement invalide.'];
    }

    foreach ($cart['lines'] as $line) {
        $pid = (int) ($line['produit_id'] ?? 0);
        $q = (int) ($line['quantite'] ?? 0);
        $p = get_produit_by_id($pid);
        if (!$p || ($p['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Produit introuvable ou inactif.'];
        }
        $stock = (int) ($p['stock'] ?? 0);
        if ($stock < $q) {
            return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($p['nom'] ?? '#') . ' ».'];
        }
    }

    $montant_especes = isset($paiement_details['montant_especes']) ? (float) $paiement_details['montant_especes'] : null;
    $montant_carte = isset($paiement_details['montant_carte']) ? (float) $paiement_details['montant_carte'] : null;
    $montant_mobile = isset($paiement_details['montant_mobile_money']) ? (float) $paiement_details['montant_mobile_money'] : null;
    $montant_recu = isset($paiement_details['montant_recu']) ? (float) $paiement_details['montant_recu'] : null;
    $monnaie = isset($paiement_details['monnaie_rendue']) ? (float) $paiement_details['monnaie_rendue'] : null;
    $notes_in = isset($paiement_details['notes']) ? trim((string) $paiement_details['notes']) : '';
    $notes_val = $notes_in !== '' ? $notes_in : ($v['notes'] ?? null);

    if ($mode_paiement === 'especes' && $montant_recu !== null && $montant_recu + 0.001 < $montant_total) {
        return ['ok' => false, 'error' => 'Montant reçu inférieur au total à payer.'];
    }

    if ($mode_paiement === 'mixte') {
        $sum = ($montant_especes ?? 0) + ($montant_carte ?? 0) + ($montant_mobile ?? 0);
        if ($sum + 0.01 < $montant_total) {
            return ['ok' => false, 'error' => 'La somme des règlements (espèces + carte + mobile) doit couvrir le total.'];
        }
    }

    $numero = (string) ($v['numero_ticket'] ?? '');

    try {
        $db->beginTransaction();

        $sqlUp = "
            UPDATE caisse_ventes SET
                mode_paiement = :mode_paiement,
                montant_especes = :montant_especes,
                montant_carte = :montant_carte,
                montant_mobile_money = :montant_mobile_money,
                montant_recu = :montant_recu,
                monnaie_rendue = :monnaie_rendue,
                notes = :notes,
                statut = 'paye',
                caissier_id = :caissier_id,
                date_encaissement = NOW()";
        if (caisse_reference_caisse_column_exists()) {
            $sqlUp .= ",
                reference = NULL";
        }
        $sqlUp .= '
            WHERE id = :id AND statut = \'en_attente\'';
        $stmtU = $db->prepare($sqlUp);
        $stmtU->execute([
            'mode_paiement' => $mode_paiement,
            'montant_especes' => $montant_especes,
            'montant_carte' => $montant_carte,
            'montant_mobile_money' => $montant_mobile,
            'montant_recu' => $montant_recu,
            'monnaie_rendue' => $monnaie,
            'notes' => $notes_val !== null && $notes_val !== '' ? $notes_val : null,
            'caissier_id' => $caissier_admin_id,
            'id' => $vente_id,
        ]);
        if ($stmtU->rowCount() !== 1) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Impossible de finaliser le ticket (déjà traité ?).'];
        }

        foreach ($v['lignes'] as $line) {
            $pid = (int) $line['produit_id'];
            $q = (int) $line['quantite'];
            $produit = get_produit_by_id($pid);
            $quantite_avant = (int) ($produit['stock'] ?? 0);
            decrement_produit_stock($pid, $q);
            $quantite_apres = max(0, $quantite_avant - $q);

            create_stock_mouvement([
                'type' => 'sortie',
                'produit_id' => $pid,
                'quantite' => $q,
                'quantite_avant' => $quantite_avant,
                'quantite_apres' => $quantite_apres,
                'reference_type' => 'caisse_vente',
                'reference_id' => $vente_id,
                'reference_numero' => $numero,
                'notes' => 'Vente caisse (encaissement)',
            ]);
        }

        $db->commit();
        return ['ok' => true, 'vente_id' => $vente_id, 'numero_ticket' => $numero];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_finaliser_vente_en_attente] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de l\'encaissement.'];
    }
}

/**
 * Enregistre la vente, décrémente le stock, mouvements de stock
 *
 * @return array{ok:bool, vente_id?:int, numero_ticket?:string, error?:string}
 */
function caisse_enregistrer_vente($admin_id, array $cart, $mode_paiement, array $paiement_details)
{
    global $db;

    if (!caisse_tables_exist()) {
        return ['ok' => false, 'error' => 'Tables caisse absentes : exécutez la migration create_caisse_tables.sql.'];
    }

    $totals = caisse_compute_totals($cart);
    if ($totals['total'] <= 0 || empty($cart['lines'])) {
        return ['ok' => false, 'error' => 'Panier vide ou total invalide.'];
    }

    $modes_ok = ['especes', 'carte', 'mobile_money', 'cheque', 'mixte', 'autre'];
    if (!in_array($mode_paiement, $modes_ok, true)) {
        return ['ok' => false, 'error' => 'Mode de paiement invalide.'];
    }

    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return ['ok' => false, 'error' => 'Session administrateur invalide.'];
    }

    foreach ($cart['lines'] as $key => $line) {
        $pid = (int) ($line['produit_id'] ?? 0);
        $q = (int) ($line['quantite'] ?? 0);
        if ($pid <= 0 || $q <= 0) {
            return ['ok' => false, 'error' => 'Ligne de panier invalide.'];
        }
        $p = get_produit_by_id($pid);
        if (!$p || ($p['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Produit introuvable ou inactif.'];
        }
        $stock = (int) ($p['stock'] ?? 0);
        if ($stock < $q) {
            return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($p['nom'] ?? '#') . ' ».'];
        }
    }

    $numero_provisoire = caisse_generer_numero_ticket_provisoire();
    $montant_total = $totals['total'];
    $rg = (float) ($cart['remise_globale_pct'] ?? 0);

    $montant_especes = isset($paiement_details['montant_especes']) ? (float) $paiement_details['montant_especes'] : null;
    $montant_carte = isset($paiement_details['montant_carte']) ? (float) $paiement_details['montant_carte'] : null;
    $montant_mobile = isset($paiement_details['montant_mobile_money']) ? (float) $paiement_details['montant_mobile_money'] : null;
    $montant_recu = isset($paiement_details['montant_recu']) ? (float) $paiement_details['montant_recu'] : null;
    $monnaie = isset($paiement_details['monnaie_rendue']) ? (float) $paiement_details['monnaie_rendue'] : null;
    $notes = isset($paiement_details['notes']) ? trim((string) $paiement_details['notes']) : null;

    if ($mode_paiement === 'especes' && $montant_recu !== null && $montant_recu + 0.001 < $montant_total) {
        return ['ok' => false, 'error' => 'Montant reçu inférieur au total à payer.'];
    }

    if ($mode_paiement === 'mixte') {
        $sum = ($montant_especes ?? 0) + ($montant_carte ?? 0) + ($montant_mobile ?? 0);
        if ($sum + 0.01 < $montant_total) {
            return ['ok' => false, 'error' => 'La somme des règlements (espèces + carte + mobile) doit couvrir le total.'];
        }
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO caisse_ventes (
                admin_id, caissier_id, numero_ticket, montant_total, remise_globale_pct, mode_paiement,
                montant_especes, montant_carte, montant_mobile_money, montant_recu, monnaie_rendue, notes,
                statut, date_vente, date_encaissement
            ) VALUES (
                :admin_id, :caissier_id, :numero_ticket, :montant_total, :remise_globale_pct, :mode_paiement,
                :montant_especes, :montant_carte, :montant_mobile_money, :montant_recu, :monnaie_rendue, :notes,
                'paye', NOW(), NOW()
            )
        ");
        $stmt->execute([
            'admin_id' => $admin_id,
            'caissier_id' => $admin_id,
            'numero_ticket' => $numero_provisoire,
            'montant_total' => $montant_total,
            'remise_globale_pct' => $rg,
            'mode_paiement' => $mode_paiement,
            'montant_especes' => $montant_especes,
            'montant_carte' => $montant_carte,
            'montant_mobile_money' => $montant_mobile,
            'montant_recu' => $montant_recu,
            'monnaie_rendue' => $monnaie,
            'notes' => $notes !== '' ? $notes : null,
        ]);
        $vente_id = (int) $db->lastInsertId();
        $numero_final = caisse_ventes_appliquer_numero_officiel($vente_id);
        if ($numero_final === '') {
            $numero_final = $numero_provisoire;
        }

        $stmtL = $db->prepare("
            INSERT INTO caisse_vente_lignes (vente_id, produit_id, designation, quantite, prix_unitaire, remise_ligne_pct, total_ligne)
            VALUES (:vente_id, :produit_id, :designation, :quantite, :prix_unitaire, :remise_ligne_pct, :total_ligne)
        ");

        foreach ($cart['lines'] as $line) {
            $pid = (int) ($line['produit_id'] ?? 0);
            $q = (int) ($line['quantite'] ?? 0);
            $pu = (float) ($line['prix_unitaire'] ?? 0);
            $rl = min(100, max(0, (float) ($line['remise_ligne_pct'] ?? 0)));
            $designation = $line['nom'] ?? '';
            $total_ligne = round($pu * $q * (1 - $rl / 100), 2);

            $stmtL->execute([
                'vente_id' => $vente_id,
                'produit_id' => $pid,
                'designation' => $designation,
                'quantite' => $q,
                'prix_unitaire' => $pu,
                'remise_ligne_pct' => $rl,
                'total_ligne' => $total_ligne,
            ]);

            $produit = get_produit_by_id($pid);
            $quantite_avant = (int) ($produit['stock'] ?? 0);
            decrement_produit_stock($pid, $q);
            $quantite_apres = max(0, $quantite_avant - $q);

            create_stock_mouvement([
                'type' => 'sortie',
                'produit_id' => $pid,
                'quantite' => $q,
                'quantite_avant' => $quantite_avant,
                'quantite_apres' => $quantite_apres,
                'reference_type' => 'caisse_vente',
                'reference_id' => $vente_id,
                'reference_numero' => $numero_final,
                'notes' => 'Vente caisse',
            ]);
        }

        $db->commit();
        return ['ok' => true, 'vente_id' => $vente_id, 'numero_ticket' => $numero_final];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_enregistrer_vente] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de l\'enregistrement de la vente.'];
    }
}

/**
 * Détail d'une vente pour réimpression ticket
 */
function caisse_get_vente_by_id($vente_id)
{
    global $db;
    if (!caisse_tables_exist() || (int) $vente_id <= 0) {
        return null;
    }
    try {
        $stmt = $db->prepare("
            SELECT v.*, a.nom AS admin_nom, a.prenom AS admin_prenom,
                   c.nom AS caissier_nom, c.prenom AS caissier_prenom
            FROM caisse_ventes v
            LEFT JOIN admin a ON a.id = v.admin_id
            LEFT JOIN admin c ON c.id = v.caissier_id
            WHERE v.id = :id
        ");
        $stmt->execute(['id' => (int) $vente_id]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) {
            return null;
        }
        $stmt2 = $db->prepare("SELECT * FROM caisse_vente_lignes WHERE vente_id = :id ORDER BY id ASC");
        $stmt2->execute(['id' => (int) $vente_id]);
        $v['lignes'] = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $v;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Normalise une saisie « numéro ticket » : espaces supprimés, majuscules (scan / saisie manuelle)
 */
function caisse_normaliser_saisie_numero_ticket($s)
{
    $s = trim((string) $s);
    if ($s === '') {
        return '';
    }
    return strtoupper(preg_replace('/\s+/u', '', $s));
}

/**
 * Détail vente par numéro de ticket : correspondance exacte sur la valeur en base,
 * après normalisation (espaces, casse). Il faut le code complet (ex. TKT20260331000008),
 * comme sous le code-barres — pas seulement la date TKT20260331 ni les 6 derniers chiffres seuls.
 */
function caisse_get_vente_by_numero($numero_ticket)
{
    if (!caisse_tables_exist()) {
        return null;
    }
    $brut = trim((string) $numero_ticket);
    if ($brut === '') {
        return null;
    }
    $compact = caisse_normaliser_saisie_numero_ticket($brut);
    global $db;
    try {
        $stmt = $db->prepare('SELECT id FROM caisse_ventes WHERE numero_ticket = :n OR numero_ticket = :n2 LIMIT 1');
        $stmt->execute(['n' => $compact, 'n2' => $brut]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return caisse_get_vente_by_id((int) $row['id']);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Aperçu des tickets en attente d’encaissement (liste caisse)
 *
 * @param int $limit Max. lignes (plafonné)
 * @return list<array<string,mixed>>
 */
function caisse_list_ventes_en_attente_apercu($limit = 200)
{
    if (!caisse_tables_exist()) {
        return [];
    }
    $limit = max(1, min(500, (int) $limit));
    $refSql = caisse_reference_caisse_column_exists() ? ', v.reference' : '';
    global $db;
    try {
        $sql = "
            SELECT v.id, v.numero_ticket, v.montant_total, v.date_vente, v.statut{$refSql},
                   a.nom AS admin_nom, a.prenom AS admin_prenom
            FROM caisse_ventes v
            LEFT JOIN admin a ON a.id = v.admin_id
            WHERE v.statut = 'en_attente'
            ORDER BY v.date_vente ASC, v.id ASC
        ";
        $stmt = $db->prepare($sql . ' LIMIT ' . $limit);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (PDOException $e) {
        error_log('[caisse_list_ventes_en_attente_apercu] ' . $e->getMessage());
        return [];
    }
}
