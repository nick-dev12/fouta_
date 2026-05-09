<?php
/**
 * Enregistre un bulletin de paie (POST) — redirection vers la vue imprimable
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh', 'informaticien', 'developpeur'], true)) {
    header('Location: ../../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../../models/model_employes.php';
require_once __DIR__ . '/../../../models/model_bulletin_paie.php';
require_once __DIR__ . '/../../../models/model_employe_absences.php';
require_once __DIR__ . '/../../../models/model_employe_transport.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$employe_id = isset($_POST['employe_id']) ? (int) $_POST['employe_id'] : 0;
if ($employe_id <= 0) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['generer_bulletin_paie'])) {
    header('Location: details.php?id=' . $employe_id . '&tab=bp');
    exit;
}

$tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
    $_SESSION['bp_flash_err'] = 'Session expirée. Rechargez la page puis réessayez.';
    header('Location: details.php?id=' . $employe_id . '&tab=bp');
    exit;
}

if (!bp_tables_parametres_disponibles() || !bp_tables_bulletins_disponibles()) {
    $_SESSION['bp_flash_err'] = 'Tables bulletins absentes — exécutez la migration bulletin de paie.';
    header('Location: details.php?id=' . $employe_id . '&tab=bp');
    exit;
}

$emp = get_employe_by_id($employe_id);
if (!$emp) {
    header('Location: index.php');
    exit;
}

$params = bp_get_parametres_effectifs();
$rub = $params['rubriques'];
$taux_cfg = $params['retenues_taux'];
$pct_codes = bp_retenues_codes_taux_brut();

$mois = isset($_POST['mois_paie']) ? trim((string) $_POST['mois_paie']) : '';
if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
    $_SESSION['bp_flash_err'] = 'Mois de paie invalide.';
    header('Location: details.php?id=' . $employe_id . '&tab=bp');
    exit;
}

$dp_raw = isset($_POST['date_paiement']) ? trim((string) $_POST['date_paiement']) : '';
if ($dp_raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dp_raw)) {
    $_SESSION['bp_flash_err'] = 'Date de paiement obligatoire (format AAAA-MM-JJ).';
    header('Location: details.php?id=' . $employe_id . '&tab=bp');
    exit;
}
$date_paiement = $dp_raw;

$salaire_base = bp_parse_montant_post($_POST['salaire_base'] ?? null);
if ($salaire_base <= 0) {
    $_SESSION['bp_flash_err'] = 'Le salaire de base doit être renseigné (montant supérieur à 0).';
    header('Location: details.php?id=' . $employe_id . '&tab=bp');
    exit;
}

foreach ($pct_codes as $pc) {
    if (empty($rub['retenues'][$pc])) {
        continue;
    }
    $pctv = (float) ($taux_cfg[$pc] ?? 0);
    if (bp_colonne_retenues_taux_disponible() && $pctv <= 0) {
        $_SESSION['bp_flash_err'] = 'Paramètres bulletin : renseignez un taux supérieur à 0 % pour chaque retenue en pourcentage activée (TRIMF, IPRES RG, IPRES cadre, CSS).';
        header('Location: details.php?id=' . $employe_id . '&tab=bp');
        exit;
    }
}

$lg = bp_labels_gains();
$lr = bp_labels_retenues();

$gains_detail = [];
$gains_detail[] = ['code' => 'salaire_base', 'label' => $lg['salaire_base'], 'montant' => $salaire_base];

$gain_codes = ['heures_sup', 'prime_performance', 'prime_transport', 'assurance_maladie', 'sursalaire', 'indemnite_transport', 'indemnite_logement', 'indemnite_fonction'];
foreach ($gain_codes as $code) {
    if (empty($rub['gains'][$code])) {
        continue;
    }
    if ($code === 'prime_transport' && employe_transport_tables_disponibles()) {
        $prime_cfg = max(0.0, (float) ($params['prime_transport_mensuelle'] ?? 0.0));
        $tot_tr = employe_transport_retraits_totaux_mois($employe_id, $mois);
        $m = max(0.0, round($prime_cfg - (float) ($tot_tr['montant'] ?? 0), 2));
        $gains_detail[] = [
            'code' => $code,
            'label' => $lg[$code] ?? $code,
            'montant' => $m,
            'prime_mensuelle' => $prime_cfg,
            'retrait_transport_jours' => (int) ($tot_tr['jours'] ?? 0),
            'retrait_transport_montant' => round((float) ($tot_tr['montant'] ?? 0), 2),
        ];
    } elseif ($code === 'sursalaire') {
        $m = max(0.0, round((float) ($params['forfait_heures_sup_mensuel'] ?? 0), 2));
        $gains_detail[] = [
            'code' => $code,
            'label' => $lg[$code] ?? $code,
            'montant' => $m,
            'forfait_hs_mensuel' => $m,
        ];
    } else {
        $f = 'g_' . $code;
        $m = bp_parse_montant_post($_POST[$f] ?? null);
        $gains_detail[] = ['code' => $code, 'label' => $lg[$code] ?? $code, 'montant' => $m];
    }
}

$brut = 0;
foreach ($gains_detail as $g) {
    $brut += (float) $g['montant'];
}
$brut = round($brut, 2);

$retenues_detail = [];
$montant_irpp_st = null;
$montant_ipres_st = null;
$montant_css_st = null;

$ret_codes = ['irpp', 'trimf', 'ipres_rg', 'ipres_cadre', 'css', 'accident_travail', 'pret_salaire', 'autres_retenues'];
foreach ($ret_codes as $code) {
    if (empty($rub['retenues'][$code])) {
        continue;
    }
    if ($code === 'irpp') {
        $m = max(0.0, round((float) ($emp['montant_irpp_mensuel'] ?? 0), 2));
        $retenues_detail[] = [
            'code' => $code,
            'label' => $lr[$code] ?? $code,
            'montant' => $m,
            'base_calcul' => 'fiche_employe',
        ];
        $montant_irpp_st = $m;
        continue;
    }
    if (in_array($code, $pct_codes, true)) {
        $pct = (float) ($taux_cfg[$code] ?? 0);
        $m = round($brut * $pct / 100, 2);
        $retenues_detail[] = [
            'code' => $code,
            'label' => $lr[$code] ?? $code,
            'montant' => $m,
            'pourcent' => $pct,
            'base_calcul' => 'brut',
        ];
        if ($code === 'css') {
            $montant_css_st = $m;
        }
    } else {
        $f = 'r_' . $code;
        $m = bp_parse_montant_post($_POST[$f] ?? null);
        $retenues_detail[] = ['code' => $code, 'label' => $lr[$code] ?? $code, 'montant' => $m];
    }
}
$m_ipres_sum = 0.0;
foreach ($retenues_detail as $r) {
    $cc = (string) ($r['code'] ?? '');
    if ($cc === 'ipres_rg' || $cc === 'ipres_cadre') {
        $m_ipres_sum += (float) ($r['montant'] ?? 0);
    }
}
$m_ipres_sum = round($m_ipres_sum, 2);
$montant_ipres_st = $m_ipres_sum > 0 ? $m_ipres_sum : null;

$jp_base_presence = 0;
if (!empty($rub['travail']['jours_presence'])) {
    if (bp_colonne_jours_presence_defaut_disponible()) {
        $jp_base_presence = (int) ($params['jours_presence_defaut'] ?? 0);
        if ($jp_base_presence < 1) {
            $_SESSION['bp_flash_err'] = 'Paramètres bulletin : renseignez le nombre de jours de présence (référence mensuelle pour tous les employés).';
            header('Location: details.php?id=' . $employe_id . '&tab=bp');
            exit;
        }
    } else {
        $jp_base_presence = isset($_POST['t_jours_presence']) ? (int) $_POST['t_jours_presence'] : 0;
        if ($jp_base_presence < 1) {
            $_SESSION['bp_flash_err'] = 'Indiquez au moins 1 jour de présence.';
            header('Location: details.php?id=' . $employe_id . '&tab=bp');
            exit;
        }
    }
}

$jp_for_prorata = $jp_base_presence;
if ($jp_for_prorata < 1 && bp_colonne_jours_presence_defaut_disponible()) {
    $jp_for_prorata = max(0, (int) ($params['jours_presence_defaut'] ?? 0));
}

$pen_pack = employe_absences_penalites_en_attente_pour_employe($employe_id, $mois);
$pen_ids = $pen_pack['ids'] ?? [];
$nb_jours_abs_ret = (int) ($pen_pack['nb_jours'] ?? count($pen_ids));
$taux_jour = ($jp_for_prorata >= 1 && $salaire_base > 0) ? ($salaire_base / $jp_for_prorata) : 0.0;
$montant_penalites = 0.0;
foreach (($pen_pack['lignes'] ?? []) as $ln) {
    $p = round((float) ($ln['penalite_montant'] ?? 0), 2);
    if ($p > 0) {
        $montant_penalites += $p;
    } elseif ($taux_jour > 0) {
        $montant_penalites += round($taux_jour, 2);
    }
}
$montant_penalites = round($montant_penalites, 2);
if ($montant_penalites > 0 && !empty($pen_ids)) {
    $retenues_detail[] = [
        'code' => 'penalites_absence',
        'label' => $lr['penalites_absence'],
        'montant' => round($montant_penalites, 2),
        'absence_ids' => $pen_ids,
    ];
}

$total_retenues = 0;
foreach ($retenues_detail as $r) {
    $total_retenues += (float) $r['montant'];
}
$total_retenues = round($total_retenues, 2);

$cotis = 0;
foreach ($retenues_detail as $r) {
    $c = (string) $r['code'];
    if ($c === 'ipres_rg' || $c === 'ipres_cadre' || $c === 'css' || $c === 'accident_travail') {
        $cotis += (float) $r['montant'];
    }
}
$net_imposable = round(max(0, $brut - $cotis), 2);

$irpp = 0;
$trimf = 0;
$pret = 0;
$autres = 0;
$pen_post = 0;
foreach ($retenues_detail as $r) {
    $c = (string) $r['code'];
    if ($c === 'irpp') {
        $irpp = (float) $r['montant'];
    } elseif ($c === 'trimf') {
        $trimf = (float) $r['montant'];
    } elseif ($c === 'pret_salaire') {
        $pret = (float) $r['montant'];
    } elseif ($c === 'autres_retenues') {
        $autres = (float) $r['montant'];
    } elseif ($c === 'penalites_absence') {
        $pen_post = (float) $r['montant'];
    }
}
$net_a_payer = round(max(0, $net_imposable - $irpp - $trimf - $pret - $autres - $pen_post), 2);

$travail = [];
if (!empty($rub['travail']['heures_travaillees'])) {
    $travail['heures_travaillees'] = isset($_POST['t_heures_travaillees']) ? (float) str_replace(',', '.', (string) $_POST['t_heures_travaillees']) : 0;
}
if (!empty($rub['travail']['heures_sup'])) {
    $travail['heures_sup_nombre'] = isset($_POST['t_heures_sup_nombre']) ? (float) str_replace(',', '.', (string) $_POST['t_heures_sup_nombre']) : 0;
}
if (!empty($rub['travail']['jours_presence'])) {
    $travail['jours_presence_reference'] = $jp_base_presence;
    $travail['jours_presence'] = max(0, $jp_base_presence - $nb_jours_abs_ret);
}
if ($nb_jours_abs_ret > 0) {
    $travail['jours_absence_retenus'] = $nb_jours_abs_ret;
}

$mode_paiement = '';
if (!empty($rub['mentions']['mode_paiement'])) {
    $mode_paiement = mb_substr(trim((string) ($_POST['mode_paiement'] ?? '')), 0, 80);
}

$mois_label_fr = bp_mois_annee_libelle($mois);

$snapshot = [
    'version' => 2,
    'employeur' => [
        'nom' => $params['employeur_nom'],
        'adresse' => $params['employeur_adresse'],
        'ninea' => $params['employeur_ninea'],
        'rc' => $params['employeur_rc'],
        'cnss_ref' => $params['employeur_cnss_ref'],
    ],
    'employe' => [
        'nom' => (string) ($emp['nom'] ?? ''),
        'prenom' => (string) ($emp['prenom'] ?? ''),
        'poste' => (string) ($emp['poste'] ?? ''),
        'matricule' => (string) ($emp['matricule'] ?? ''),
        'categorie' => (string) ($emp['categorie_paie'] ?? ''),
    ],
    'periode' => [
        'mois_paie' => $mois,
        'mois_label' => $mois_label_fr,
        'date_paiement' => $date_paiement,
    ],
    'gains' => $gains_detail,
    'retenues' => $retenues_detail,
    'totaux' => [
        'montant_brut' => $brut,
        'total_retenues' => $total_retenues,
        'net_imposable' => $net_imposable,
        'net_a_payer' => $net_a_payer,
    ],
    'travail' => $travail,
    'mentions' => [
        'date_paiement' => $date_paiement,
        'mode_paiement' => $mode_paiement,
        'afficher_signature' => !empty($rub['mentions']['signature']),
    ],
    'rubriques_config' => $rub,
    'retenues_taux_config' => $taux_cfg,
];

$totaux_insert = [
    'mois_paie' => $mois,
    'date_paiement' => $date_paiement,
    'salaire_base' => $salaire_base,
    'montant_brut' => $brut,
    'total_retenues' => $total_retenues,
    'net_imposable' => $net_imposable,
    'net_a_payer' => $net_a_payer,
    'montant_irpp' => $montant_irpp_st,
    'montant_ipres' => $montant_ipres_st,
    'montant_css' => $montant_css_st,
    'montant_penalites_absence' => $montant_penalites > 0 ? round($montant_penalites, 2) : null,
];

$bid = bp_insert_bulletin(
    $employe_id,
    (int) ($_SESSION['admin_id'] ?? 0),
    $totaux_insert,
    $snapshot
);

if (!$bid) {
    $_SESSION['bp_flash_err'] = 'Enregistrement du bulletin impossible.';
    header('Location: details.php?id=' . $employe_id . '&tab=bp');
    exit;
}

if (!empty($pen_ids)) {
    employe_absences_marquer_penalites_deduites($pen_ids, $bid);
}

header('Location: bulletin_paie_voir.php?id=' . (int) $bid);
exit;
