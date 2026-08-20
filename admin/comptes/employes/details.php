<?php
/**
 * Détail employé — infos, QR badge, absences et justificatifs (fiche employes uniquement).
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
require_once __DIR__ . '/../../../models/model_employe_absences.php';
require_once __DIR__ . '/../../../models/model_employe_documents.php';
require_once __DIR__ . '/../../../models/model_bulletin_paie.php';
require_once __DIR__ . '/../../../models/model_employe_transport.php';
require_once __DIR__ . '/../../../models/model_employe_conges.php';
require_once __DIR__ . '/../../../includes/carte_employe_rh.php';
require_once __DIR__ . '/../../../controllers/controller_employes.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = (string) $_SESSION['admin_csrf'];

$flash_ok = '';
$flash_err = '';
$doc_form_err = '';
$doc_panel_open = false;
$sanction_form_err = '';
$sanction_modal_open = false;
$auth_form_err = '';
$auth_modal_open = false;
$pret_form_err = '';
$pret_modal_open = false;
$pret_remb_form_err = '';
$pret_remb_modal_open = false;
$conge_form_err = '';
$conge_modal_open = false;
if (isset($_GET['contrat_supprime']) && $_GET['contrat_supprime'] === '1') {
    $flash_ok = 'Le fichier PDF du contrat a été supprimé.';
} elseif (isset($_GET['doc_ajoute']) && $_GET['doc_ajoute'] === '1') {
    $flash_ok = 'Document ajouté avec succès.';
} elseif (isset($_GET['doc_supprime']) && $_GET['doc_supprime'] === '1') {
    $flash_ok = 'Document supprimé.';
} elseif (isset($_GET['sanction_ajoutee']) && $_GET['sanction_ajoutee'] === '1') {
    $flash_ok = 'Sanction ou mesure disciplinaire enregistrée.';
} elseif (isset($_GET['autorisation_ajoutee']) && $_GET['autorisation_ajoutee'] === '1') {
    $flash_ok = 'Autorisation d’absence enregistrée.';
} elseif (isset($_GET['pret_ajoute']) && $_GET['pret_ajoute'] === '1') {
    $flash_ok = 'Prêt enregistré.';
} elseif (isset($_GET['pret_remb_ajoute']) && $_GET['pret_remb_ajoute'] === '1') {
    $flash_ok = 'Versement / remboursement enregistré.';
} elseif (isset($_GET['penalite_ok']) && $_GET['penalite_ok'] === '1') {
    $flash_ok = 'La pénalité sera déduite du salaire lors du prochain bulletin de paie généré.';
} elseif (isset($_GET['penalite_err']) && $_GET['penalite_err'] === '1') {
    $flash_err = 'Action impossible (vérifiez le montant de la pénalité ou l’état de l’absence).';
} elseif (isset($_GET['transport_ajoute']) && $_GET['transport_ajoute'] === '1') {
    $flash_ok = 'Déduction de prime transport enregistrée.';
} elseif (isset($_GET['transport_err']) && $_GET['transport_err'] === '1') {
    $flash_err = 'Impossible d’enregistrer la déduction transport.';
} elseif (isset($_GET['conge_ajoute']) && $_GET['conge_ajoute'] === '1') {
    $flash_ok = 'Congé enregistré.';
} elseif (isset($_GET['conge_err']) && $_GET['conge_err'] === '1') {
    $flash_err = 'Impossible d’enregistrer le congé.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['supprimer_document_id'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $flash_err = 'Session expirée. Rechargez la page puis réessayez.';
    } else {
        $r = employe_process_supprimer_document_piece_fiche($id);
        if (!empty($r['handled']) && !empty($r['ok'])) {
            header('Location: details.php?id=' . $id . '&doc_supprime=1&tab=docs');
            exit;
        }
        if (!empty($r['handled'])) {
            $flash_err = (string) ($r['msg'] ?? 'Action impossible.');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajouter_document'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $doc_form_err = 'Session expirée. Rechargez la page puis réessayez.';
        $doc_panel_open = true;
    } else {
        $r = employe_process_ajout_document_fiche($id);
        if (!empty($r['handled']) && !empty($r['ok'])) {
            header('Location: details.php?id=' . $id . '&doc_ajoute=1&tab=docs');
            exit;
        }
        if (!empty($r['handled'])) {
            $doc_form_err = (string) ($r['msg'] ?? 'Enregistrement impossible.');
            $doc_panel_open = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajouter_sanction'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $sanction_form_err = 'Session expirée. Rechargez la page puis réessayez.';
        $sanction_modal_open = true;
    } else {
        $r = employe_process_ajouter_sanction_fiche($id);
        if (!empty($r['handled']) && !empty($r['ok'])) {
            header('Location: details.php?id=' . $id . '&sanction_ajoutee=1&tab=san');
            exit;
        }
        if (!empty($r['handled'])) {
            $sanction_form_err = (string) ($r['msg'] ?? 'Enregistrement impossible.');
            $sanction_modal_open = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajouter_autorisation_absence'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $auth_form_err = 'Session expirée. Rechargez la page puis réessayez.';
        $auth_modal_open = true;
    } else {
        $r = employe_process_ajouter_autorisation_absence_fiche($id);
        if (!empty($r['handled']) && !empty($r['ok'])) {
            header('Location: details.php?id=' . $id . '&autorisation_ajoutee=1&tab=abs&abs_sub=auth');
            exit;
        }
        if (!empty($r['handled'])) {
            $auth_form_err = (string) ($r['msg'] ?? 'Enregistrement impossible.');
            $auth_modal_open = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajouter_pret'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $pret_form_err = 'Session expirée. Rechargez la page puis réessayez.';
        $pret_modal_open = true;
    } else {
        $r = employe_process_ajouter_pret_fiche($id);
        if (!empty($r['handled']) && !empty($r['ok'])) {
            header('Location: details.php?id=' . $id . '&pret_ajoute=1&tab=pret');
            exit;
        }
        if (!empty($r['handled'])) {
            $pret_form_err = (string) ($r['msg'] ?? 'Enregistrement impossible.');
            $pret_modal_open = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajouter_remboursement_pret'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $pret_remb_form_err = 'Session expirée. Rechargez la page puis réessayez.';
        $pret_remb_modal_open = true;
    } else {
        $r = employe_process_ajouter_remboursement_pret_fiche($id);
        if (!empty($r['handled']) && !empty($r['ok'])) {
            header('Location: details.php?id=' . $id . '&pret_remb_ajoute=1&tab=pret');
            exit;
        }
        if (!empty($r['handled'])) {
            $pret_remb_form_err = (string) ($r['msg'] ?? 'Enregistrement impossible.');
            $pret_remb_modal_open = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['absence_retenir_penalite'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        header('Location: details.php?id=' . $id . '&tab=abs&abs_sub=list&penalite_err=1');
        exit;
    }
    $aid_ret = isset($_POST['absence_id_retenir']) ? (int) $_POST['absence_id_retenir'] : 0;
    if ($aid_ret <= 0 || !employe_absence_marquer_retenir_penalite($aid_ret, $id)) {
        header('Location: details.php?id=' . $id . '&tab=abs&abs_sub=list&penalite_err=1');
        exit;
    }
    header('Location: details.php?id=' . $id . '&tab=abs&abs_sub=list&penalite_ok=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajouter_retrait_transport'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        header('Location: details.php?id=' . $id . '&tab=transport&transport_err=1');
        exit;
    }
    $r = employe_process_ajouter_retrait_transport_fiche($id);
    if (!empty($r['handled']) && !empty($r['ok'])) {
        header('Location: details.php?id=' . $id . '&tab=transport&transport_ajoute=1');
        exit;
    }
    if (!empty($r['handled'])) {
        $flash_err = (string) ($r['msg'] ?? 'Enregistrement impossible.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajouter_conge'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        header('Location: details.php?id=' . $id . '&tab=conges&conge_err=1');
        exit;
    }
    $r = employe_process_ajouter_conge_fiche($id);
    if (!empty($r['handled']) && !empty($r['ok'])) {
        header('Location: details.php?id=' . $id . '&tab=conges&conge_ajoute=1');
        exit;
    }
    if (!empty($r['handled'])) {
        $conge_form_err = (string) ($r['msg'] ?? 'Enregistrement impossible.');
        $conge_modal_open = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['supprimer_contrat_pdf'])) {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $flash_err = 'Session expirée. Rechargez la page puis réessayez.';
    } else {
        $r = employe_process_supprimer_contrat_pdf_fiche($id);
        if (!empty($r['handled']) && !empty($r['ok'])) {
            header('Location: details.php?id=' . $id . '&contrat_supprime=1&tab=docs');
            exit;
        }
        if (!empty($r['handled'])) {
            $flash_err = (string) ($r['msg'] ?? 'Action impossible.');
        }
    }
}

$carte_prep = employes_carte_rh_preparer_variables($id);
if (!$carte_prep) {
    header('Location: index.php');
    exit;
}

$f = $carte_prep['f'];
$file_abs = (string) $carte_prep['upload_disk'];
$photo_rel = (string) $carte_prep['photo_rel'];
$photo_disk_ok = !empty($carte_prep['photo_disk_ok']);
$upload_public = (string) $carte_prep['upload_public'];
$carte_matricule = (string) $carte_prep['matricule'];
$carte_html_ecran = employes_carte_rh_rendre_html($carte_prep, '');

$lignes_abs_brutes = employe_absences_detail_pour_fiche_employe($id);
$fusion_abs = [];
foreach ($lignes_abs_brutes as $r) {
    $aid = (int) ($r['absence_id'] ?? 0);
    if ($aid <= 0) {
        continue;
    }
    if (!isset($fusion_abs[$aid])) {
        $fusion_abs[$aid] = $r;
    }
    if (!empty($r['justif_id'])) {
        $fusion_abs[$aid]['justif_id'] = $r['justif_id'];
    }
}
$lignes_abs = array_values($fusion_abs);
usort($lignes_abs, function ($a, $b) {
    $da = (string) ($a['date_absence'] ?? '');
    $db = (string) ($b['date_absence'] ?? '');
    if ($da === $db) {
        return (int) ($b['absence_id'] ?? 0) <=> (int) ($a['absence_id'] ?? 0);
    }
    return strcmp($db, $da);
});
$nb_absences = count($lignes_abs);

$sf_slug = isset($f['statut_familial']) && $f['statut_familial'] !== null && $f['statut_familial'] !== ''
    ? (string) $f['statut_familial'] : '';
$tc_slug = isset($f['type_contrat']) && $f['type_contrat'] !== null && $f['type_contrat'] !== ''
    ? (string) $f['type_contrat'] : '';
$sf_choices = employe_statuts_familiaux_choices();
$tc_choices = employe_types_contrat_choices();
$sf_label = ($sf_slug !== '' && isset($sf_choices[$sf_slug])) ? $sf_choices[$sf_slug] : '—';
$tc_label = ($tc_slug !== '' && isset($tc_choices[$tc_slug])) ? $tc_choices[$tc_slug] : '—';

$contrat_rel = trim((string) ($f['contrat_pdf_chemin'] ?? ''));
$upload_disk_root = (string) ($carte_prep['upload_disk'] ?? (__DIR__ . '/../../../upload/'));
$contrat_disk_ok = $contrat_rel !== '' && strpos($contrat_rel, '..') === false
    && is_file($upload_disk_root . str_replace('/', DIRECTORY_SEPARATOR, $contrat_rel));

$documents_extra = employe_documents_list($id);

$lignes_sanctions = employe_sanctions_list_for_employe($id);
$nb_sanctions = count($lignes_sanctions);
$sanction_types_choices = employe_sanctions_types_choices();

$justifs_par_id = [];
foreach ($lignes_abs_brutes as $r) {
    if (empty($r['justif_id'])) {
        continue;
    }
    $jid = (int) $r['justif_id'];
    if ($jid <= 0 || isset($justifs_par_id[$jid])) {
        continue;
    }
    $lib = '';
    if (!empty($r['justif_nom_fichier'])) {
        $lib = (string) $r['justif_nom_fichier'];
    } elseif (!empty($r['justif_texte'])) {
        $lib = 'Texte : ' . mb_strimwidth(trim((string) $r['justif_texte']), 0, 80, '…', 'UTF-8');
    } else {
        $lib = 'Justificatif';
    }
    $justifs_par_id[$jid] = [
        'absence_id'   => (int) $r['absence_id'],
        'date_absence' => $r['date_absence'],
        'libelle'      => $lib,
        'fichier_rel'  => $r['justif_fichier_chemin'] ?? '',
        'snippet'      => $r['justif_texte'] ?? '',
        'date_justif'  => $r['justif_creation'] ?? '',
    ];
}
$lignes_justifs = array_values($justifs_par_id);

$lignes_autorisations = employe_autorisations_absence_list_for_employe($id);
$nb_autorisations_abs = count($lignes_autorisations);

$lignes_prets = employe_prets_list_for_employe($id);
$nb_prets = count($lignes_prets);
$nb_prets_en_cours = 0;
foreach ($lignes_prets as $_pr) {
    if (($pr_stat = (string) ($_pr['statut'] ?? '')) === 'en_cours') {
        $nb_prets_en_cours++;
    }
}
$pret_statuts_choices = employe_prets_statuts_choices();

$prets_remb_par_pret = employe_pret_remboursements_groupes_par_pret($id);

$bp_tables_ok = bp_tables_parametres_disponibles() && bp_tables_bulletins_disponibles();
$bp_def_mois = date('Y-m');
$bp_def_date_paiement = date('Y-m-t', strtotime($bp_def_mois . '-01'));
$bp_params = $bp_tables_ok ? bp_get_parametres_effectifs() : null;
$bp_list = $bp_tables_ok ? bp_list_bulletins_employe($id) : [];
$bp_pen_en_attente = $bp_tables_ok ? employe_absences_penalites_en_attente_pour_employe($id, $bp_def_mois) : ['total' => 0.0, 'ids' => [], 'nb_jours' => 0];
$bp_lg = bp_labels_gains();
$bp_lr = bp_labels_retenues();
$bp_flash_err = '';
if (!empty($_SESSION['bp_flash_err'])) {
    $bp_flash_err = (string) $_SESSION['bp_flash_err'];
    unset($_SESSION['bp_flash_err']);
}
$bp_sal_pref = '';
$bp_has_salaire_fiche = false;
$bp_salaire_post_value = '';
if (isset($f['salaire_base']) && $f['salaire_base'] !== null && (string) $f['salaire_base'] !== '') {
    $raw_sb = is_numeric($f['salaire_base'])
        ? (float) $f['salaire_base']
        : (float) str_replace(',', '.', str_replace([' ', "\xc2\xa0"], '', trim((string) $f['salaire_base'])));
    if ($raw_sb > 0) {
        $bp_has_salaire_fiche = true;
        $bp_salaire_post_value = number_format($raw_sb, 2, '.', '');
        $bp_sal_pref = $bp_salaire_post_value;
    }
}

$bp_irpp_montant_fiche = max(0.0, round((float) ($f['montant_irpp_mensuel'] ?? 0), 2));
$bp_trimf_montant_fiche = max(0.0, round((float) ($f['montant_trimf_mensuel'] ?? 0), 2));

$transport_mois_def = date('Y-m');
$transport_prime_mensuelle = $bp_tables_ok ? (float) ($bp_params['prime_transport_mensuelle'] ?? 0) : 0.0;
$transport_jours_ref = $bp_tables_ok ? (int) ($bp_params['jours_presence_defaut'] ?? 0) : 0;
$transport_lignes = employe_transport_tables_disponibles() ? employe_transport_retraits_list_for_employe($id) : [];
$transport_map = [];
foreach ($transport_lignes as $trw) {
    $m = (string) ($trw['mois_paie'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $m)) {
        continue;
    }
    if (!isset($transport_map[$m])) {
        $transport_map[$m] = ['jours' => 0, 'montant' => 0.0];
    }
    $transport_map[$m]['jours'] += max(0, (int) ($trw['nb_jours'] ?? 0));
    $transport_map[$m]['montant'] += max(0.0, (float) ($trw['montant_deduit'] ?? 0));
}
foreach ($transport_map as $k => $v) {
    $transport_map[$k]['montant'] = round((float) $v['montant'], 2);
}
$transport_tot_mois_def = $transport_map[$transport_mois_def] ?? ['jours' => 0, 'montant' => 0.0];
$bp_prime_transport_net_def = max(0.0, $transport_prime_mensuelle - (float) ($transport_tot_mois_def['montant'] ?? 0));
$bp_forfait_hs_cfg = $bp_tables_ok ? max(0.0, round((float) ($bp_params['forfait_heures_sup_mensuel'] ?? 0), 2)) : 0.0;
$conges_quota_global = $bp_tables_ok ? max(0, (int) ($bp_params['conges_annuels_global'] ?? 0)) : 0;
$conges_lignes = employe_conges_table_disponible() ? employe_conges_list_for_employe($id) : [];
$conges_totaux_par_annee = employe_conges_table_disponible() ? employe_conges_totaux_par_annee($id) : [];
$conges_annee_def = date('Y');
$conges_pris_annee_def = max(0, (int) ($conges_totaux_par_annee[$conges_annee_def] ?? 0));
$conges_restant_annee_def = max(0, $conges_quota_global - $conges_pris_annee_def);

$detail_tab = isset($_GET['tab']) ? strtolower(preg_replace('/[^a-z]/', '', (string) $_GET['tab'])) : '';
if (!in_array($detail_tab, ['infos', 'docs', 'abs', 'san', 'pret', 'transport', 'conges', 'bp'], true)) {
    $detail_tab = 'infos';
}
if (!empty($doc_panel_open)) {
    $detail_tab = 'docs';
}
if (!empty($sanction_modal_open)) {
    $detail_tab = 'san';
}
if (!empty($auth_modal_open)) {
    $detail_tab = 'abs';
}
if (!empty($pret_modal_open)) {
    $detail_tab = 'pret';
}
if (!empty($pret_remb_modal_open)) {
    $detail_tab = 'pret';
}
if (!empty($conge_modal_open)) {
    $detail_tab = 'conges';
}
$detail_tab_chk = ['infos' => '', 'docs' => '', 'abs' => '', 'san' => '', 'pret' => '', 'transport' => '', 'conges' => '', 'bp' => ''];
$detail_tab_chk[$detail_tab] = ' checked';

$bp_modal_open = ($detail_tab === 'bp' && !empty($bp_tables_ok) && $bp_flash_err !== '');

$abs_sub = isset($_GET['abs_sub']) ? strtolower(preg_replace('/[^a-z_]/', '', (string) $_GET['abs_sub'])) : '';
if (!in_array($abs_sub, ['list', 'justif', 'auth'], true)) {
    $abs_sub = 'list';
}
if (!empty($auth_modal_open)) {
    $abs_sub = 'auth';
}
$abs_sub_chk = ['list' => '', 'justif' => '', 'auth' => ''];
$abs_sub_chk[$abs_sub] = ' checked';

$titre = htmlspecialchars(trim(($f['prenom'] ?? '') . ' ' . ($f['nom'] ?? ''))) . ' — Détails';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titre; ?></title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-comptes-page.css'); ?>
    <?php fpl_css_link('admin-employes-rh.css'); ?>
</head>
<body class="page-comptes page-employes-rh page-employes-detail<?php
    echo $doc_panel_open ? ' er-docs-modal-open' : '';
    echo $sanction_modal_open ? ' er-sanction-modal-open' : '';
    echo $auth_modal_open ? ' er-abs-auth-modal-open' : '';
    echo $pret_modal_open ? ' er-pret-modal-open' : '';
    echo $pret_remb_modal_open ? ' er-pret-remb-modal-open' : '';
    echo $conge_modal_open ? ' er-conge-modal-open' : '';
    echo $bp_modal_open ? ' er-bp-modal-open' : '';
?>">
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="page-comptes-wrap er-page">
        <?php if ($flash_ok !== ''): ?>
            <div class="message success page-comptes-flash" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($flash_ok); ?></div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="message error page-comptes-flash" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($flash_err); ?></div>
        <?php endif; ?>
        <?php if ($bp_flash_err !== ''): ?>
            <div class="message error page-comptes-flash" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($bp_flash_err); ?></div>
        <?php endif; ?>
        <header class="er-detail-hero">
            <?php if ($photo_disk_ok): ?>
                <div class="er-detail-hero__avatar er-detail-hero__avatar--photo" aria-hidden="true">
                    <img src="<?php echo htmlspecialchars($upload_public . $photo_rel); ?>" alt=""
                        class="er-detail-hero__photo-img" width="108" height="108" decoding="async">
                </div>
            <?php else: ?>
                <div class="er-detail-hero__avatar" aria-hidden="true"><?php echo strtoupper(substr((string) ($f['prenom'] ?? '?'), 0, 1)); ?></div>
            <?php endif; ?>
            <div class="er-detail-hero__intro">
                <p class="page-comptes-eyebrow">Fiche employé</p>
                <h1><?php echo htmlspecialchars(trim(($f['prenom'] ?? '') . ' ' . ($f['nom'] ?? ''))); ?></h1>
                <p class="er-detail-hero__poste"><?php echo htmlspecialchars(($f['poste'] ?? '') !== '' ? $f['poste'] : '—'); ?></p>
                <div class="er-detail-hero__rh-meta" role="group" aria-label="Contrat et statut familial">
                    <div class="er-detail-hero__rh-row er-detail-hero__rh-row--contrat">
                        <span class="er-detail-hero__rh-label"><i class="fas fa-file-contract" aria-hidden="true"></i> Type de contrat</span>
                        <strong class="er-detail-hero__rh-value er-detail-hero__rh-value--contrat"><?php echo htmlspecialchars($tc_label); ?></strong>
                    </div>
                    <div class="er-detail-hero__rh-row">
                        <span class="er-detail-hero__rh-label"><i class="fas fa-heart" aria-hidden="true"></i> Statut familial</span>
                        <span class="er-detail-hero__rh-value"><?php echo htmlspecialchars($sf_label); ?></span>
                    </div>
                </div>
            </div>
            <div class="er-detail-hero__actions">
                <button type="button" class="er-hero-chip er-hero-chip--docs" id="erDetailDocsAddBtn">
                    <span class="er-hero-chip__ic" aria-hidden="true"><i class="fas fa-file-circle-plus"></i></span>
                    <span class="er-hero-chip__label">Ajouter un document</span>
                </button>
                <a href="carte_imprimer.php?id=<?php echo (int) $id; ?>" class="er-hero-chip er-hero-chip--print" target="_blank" rel="noopener">
                    <span class="er-hero-chip__ic" aria-hidden="true"><i class="fas fa-print"></i></span>
                    <span class="er-hero-chip__label">Imprimer la carte</span>
                </a>
                <a href="modifier.php?id=<?php echo (int) $id; ?>" class="er-hero-chip er-hero-chip--edit">
                    <span class="er-hero-chip__ic" aria-hidden="true"><i class="fas fa-pen"></i></span>
                    <span class="er-hero-chip__label">Modifier</span>
                </a>
                <a href="index.php" class="er-hero-chip er-hero-chip--list">
                    <span class="er-hero-chip__ic" aria-hidden="true"><i class="fas fa-list"></i></span>
                    <span class="er-hero-chip__label">Liste</span>
                </a>
            </div>
        </header>

        <div class="er-detail-kpis">
            <div class="er-detail-kpi"><span class="er-detail-kpi__v"><?php echo $nb_absences; ?></span><span class="er-detail-kpi__l">Absence(s) enregistrée(s)</span></div>
            <div class="er-detail-kpi er-detail-kpi--orange"><span class="er-detail-kpi__v"><?php echo count($lignes_justifs); ?></span><span class="er-detail-kpi__l">Justificatif(s)</span></div>
            <div class="er-detail-kpi er-detail-kpi--discipline"><span class="er-detail-kpi__v"><?php echo $nb_sanctions; ?></span><span class="er-detail-kpi__l">Sanction(s) / discipline</span></div>
            <div class="er-detail-kpi er-detail-kpi--pret"><span class="er-detail-kpi__v"><?php echo (int) $nb_prets_en_cours; ?></span><span class="er-detail-kpi__l">Prêt(s) en cours</span></div>
        </div>

        <div class="er-fiche-tabs">
            <input type="radio" name="er_fiche_tab" id="er_fiche_tab_infos" class="er-fiche-tabs__state"<?php echo $detail_tab_chk['infos']; ?>>
            <input type="radio" name="er_fiche_tab" id="er_fiche_tab_docs" class="er-fiche-tabs__state"<?php echo $detail_tab_chk['docs']; ?>>
            <input type="radio" name="er_fiche_tab" id="er_fiche_tab_abs" class="er-fiche-tabs__state"<?php echo $detail_tab_chk['abs']; ?>>
            <input type="radio" name="er_fiche_tab" id="er_fiche_tab_san" class="er-fiche-tabs__state"<?php echo $detail_tab_chk['san']; ?>>
            <input type="radio" name="er_fiche_tab" id="er_fiche_tab_pret" class="er-fiche-tabs__state"<?php echo $detail_tab_chk['pret']; ?>>
            <input type="radio" name="er_fiche_tab" id="er_fiche_tab_transport" class="er-fiche-tabs__state"<?php echo $detail_tab_chk['transport']; ?>>
            <input type="radio" name="er_fiche_tab" id="er_fiche_tab_conges" class="er-fiche-tabs__state"<?php echo $detail_tab_chk['conges']; ?>>
            <input type="radio" name="er_fiche_tab" id="er_fiche_tab_bp" class="er-fiche-tabs__state"<?php echo $detail_tab_chk['bp']; ?>>

            <nav class="er-fiche-tabs__bar" role="tablist" aria-label="Sections de la fiche employé">
                <label for="er_fiche_tab_infos" class="er-fiche-tabs__tab er-fiche-tabs__tab--infos" role="tab">
                    <span class="er-fiche-tabs__ic" aria-hidden="true"><i class="fas fa-id-card-alt"></i></span>
                    <span class="er-fiche-tabs__txt">
                        <span class="er-fiche-tabs__label" id="er-tab-lbl-infos">Informations personnelles</span>
                        <span class="er-fiche-tabs__hint">Identité, contact, contrat &amp; carte</span>
                    </span>
                </label>
                <label for="er_fiche_tab_docs" class="er-fiche-tabs__tab er-fiche-tabs__tab--docs" role="tab">
                    <span class="er-fiche-tabs__ic" aria-hidden="true"><i class="fas fa-folder-open"></i></span>
                    <span class="er-fiche-tabs__txt">
                        <span class="er-fiche-tabs__label" id="er-tab-lbl-docs">Documents</span>
                        <span class="er-fiche-tabs__hint">Contrat &amp; pièces jointes</span>
                    </span>
                </label>
                <label for="er_fiche_tab_abs" class="er-fiche-tabs__tab er-fiche-tabs__tab--abs" role="tab">
                    <span class="er-fiche-tabs__ic" aria-hidden="true"><i class="fas fa-calendar-day"></i></span>
                    <span class="er-fiche-tabs__txt">
                        <span class="er-fiche-tabs__label" id="er-tab-lbl-abs">Absences</span>
                        <span class="er-fiche-tabs__hint">Liste &amp; justificatifs</span>
                    </span>
                </label>
                <label for="er_fiche_tab_san" class="er-fiche-tabs__tab er-fiche-tabs__tab--san" role="tab">
                    <span class="er-fiche-tabs__ic" aria-hidden="true"><i class="fas fa-gavel"></i></span>
                    <span class="er-fiche-tabs__txt">
                        <span class="er-fiche-tabs__label" id="er-tab-lbl-san">Sanctions &amp; discipline</span>
                        <span class="er-fiche-tabs__hint">Mesures et historique</span>
                    </span>
                </label>
                <label for="er_fiche_tab_pret" class="er-fiche-tabs__tab er-fiche-tabs__tab--pret" role="tab">
                    <span class="er-fiche-tabs__ic" aria-hidden="true"><i class="fas fa-hand-holding-dollar"></i></span>
                    <span class="er-fiche-tabs__txt">
                        <span class="er-fiche-tabs__label" id="er-tab-lbl-pret">Prêts</span>
                        <span class="er-fiche-tabs__hint">Avances &amp; remboursements</span>
                    </span>
                </label>
                <label for="er_fiche_tab_transport" class="er-fiche-tabs__tab er-fiche-tabs__tab--transport" role="tab">
                    <span class="er-fiche-tabs__ic" aria-hidden="true"><i class="fas fa-bus"></i></span>
                    <span class="er-fiche-tabs__txt">
                        <span class="er-fiche-tabs__label" id="er-tab-lbl-transport">Transport</span>
                        <span class="er-fiche-tabs__hint">Déductions prime transport</span>
                    </span>
                </label>
                <label for="er_fiche_tab_conges" class="er-fiche-tabs__tab er-fiche-tabs__tab--conges" role="tab">
                    <span class="er-fiche-tabs__ic" aria-hidden="true"><i class="fas fa-umbrella-beach"></i></span>
                    <span class="er-fiche-tabs__txt">
                        <span class="er-fiche-tabs__label" id="er-tab-lbl-conges">Congés</span>
                        <span class="er-fiche-tabs__hint">Quota annuel &amp; prises</span>
                    </span>
                </label>
                <label for="er_fiche_tab_bp" class="er-fiche-tabs__tab er-fiche-tabs__tab--bp" role="tab">
                    <span class="er-fiche-tabs__ic" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                    <span class="er-fiche-tabs__txt">
                        <span class="er-fiche-tabs__label" id="er-tab-lbl-bp">Bulletins de paie</span>
                        <span class="er-fiche-tabs__hint">Génération &amp; historique</span>
                    </span>
                </label>
            </nav>

            <section class="er-fiche-tabs__panel er-fiche-tabs__panel--infos" role="tabpanel" aria-labelledby="er-tab-lbl-infos">
                <div class="er-detail-grid er-detail-grid--tab-infos">
                    <section class="er-detail-card">
                        <h2 class="er-detail-card__title"><i class="fas fa-address-card" aria-hidden="true"></i> Informations</h2>
                        <ul class="er-detail-infos">
                            <li><span class="l">Nom</span><span class="v"><?php echo htmlspecialchars($f['nom'] ?? ''); ?></span></li>
                            <li><span class="l">Prénom</span><span class="v"><?php echo htmlspecialchars($f['prenom'] ?? ''); ?></span></li>
                            <li><span class="l">Matricule</span><span class="v"><?php echo htmlspecialchars($carte_matricule); ?></span></li>
                            <li><span class="l">Téléphone</span><span class="v"><?php echo !empty($f['telephone']) ? htmlspecialchars((string) $f['telephone']) : '—'; ?></span></li>
                            <li><span class="l">Fonction</span><span class="v"><?php echo !empty($f['poste']) ? htmlspecialchars((string) $f['poste']) : '—'; ?></span></li>
                            <?php if (!empty($f['email'])): ?>
                            <li><span class="l">Email</span><span class="v"><?php echo htmlspecialchars($f['email']); ?></span></li>
                            <?php endif; ?>
                            <li><span class="l">Statut</span><span class="v"><?php echo htmlspecialchars($f['statut'] ?? ''); ?></span></li>
                            <li><span class="l">Statut familial</span><span class="v"><?php echo htmlspecialchars($sf_label); ?></span></li>
                            <li><span class="l">Type de contrat</span><span class="v"><?php echo htmlspecialchars($tc_label); ?></span></li>
                        </ul>
                    </section>

                    <section class="er-detail-card er-detail-card--carte-rh" aria-label="Carte d'identité employé">
                        <?php echo $carte_html_ecran; ?>
                    </section>
                </div>
            </section>

            <section class="er-fiche-tabs__panel er-fiche-tabs__panel--docs" role="tabpanel" aria-labelledby="er-tab-lbl-docs">
                <section class="er-documents er-documents--in-tab" aria-labelledby="er-documents-title">
                    <div class="er-documents__shell">
                        <header class="er-documents__masthead">
                            <div class="er-documents__masthead-glow" aria-hidden="true"></div>
                            <div class="er-documents__masthead-icon" aria-hidden="true">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <div class="er-documents__masthead-text">
                                <h2 id="er-documents-title" class="er-documents__title">Documents</h2>
                                <p class="er-documents__subtitle">Fichiers officiels et contrat de travail</p>
                            </div>
                        </header>

                        <div class="er-documents__list">
                    <article class="er-documents__item <?php echo $contrat_disk_ok ? 'er-documents__item--ok' : ($contrat_rel !== '' ? 'er-documents__item--warn' : 'er-documents__item--empty'); ?>">
                        <div class="er-documents__item-badge" aria-hidden="true">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="er-documents__item-body">
                            <h3 class="er-documents__item-title">Contrat de travail</h3>
                            <p class="er-documents__item-meta">
                                <?php if ($contrat_disk_ok): ?>
                                    <span class="er-documents__pill er-documents__pill--pdf">PDF</span>
                                    <span class="er-documents__item-filename"><?php echo htmlspecialchars(basename($contrat_rel)); ?></span>
                                <?php elseif ($contrat_rel !== ''): ?>
                                    <span class="er-documents__pill er-documents__pill--alert">Référence invalide</span>
                                    <span class="er-documents__item-note">Le fichier n’est plus présent sur le serveur.</span>
                                <?php else: ?>
                                    <span class="er-documents__pill er-documents__pill--muted">Aucun fichier</span>
                                    <span class="er-documents__item-note">Ajoutez le contrat depuis la fiche employé.</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="er-documents__item-actions">
                            <?php if ($contrat_disk_ok): ?>
                                <a href="<?php echo htmlspecialchars($upload_public . $contrat_rel); ?>" class="er-documents__btn er-documents__btn--primary" target="_blank" rel="noopener">
                                    <i class="fas fa-external-link-alt" aria-hidden="true"></i> Ouvrir
                                </a>
                                <a href="<?php echo htmlspecialchars($upload_public . $contrat_rel); ?>" class="er-documents__btn er-documents__btn--secondary" download>
                                    <i class="fas fa-download" aria-hidden="true"></i> Télécharger
                                </a>
                                <form method="post" action="details.php?id=<?php echo (int) $id; ?>" class="er-documents__form-del">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="supprimer_contrat_pdf" value="1">
                                    <button type="submit" class="er-documents__btn er-documents__btn--danger"><i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer</button>
                                </form>
                            <?php elseif ($contrat_rel !== ''): ?>
                                <a href="modifier.php?id=<?php echo (int) $id; ?>" class="er-documents__btn er-documents__btn--secondary">
                                    <i class="fas fa-pen" aria-hidden="true"></i> Mettre à jour
                                </a>
                                <form method="post" action="details.php?id=<?php echo (int) $id; ?>" class="er-documents__form-del">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="supprimer_contrat_pdf" value="1">
                                    <button type="submit" class="er-documents__btn er-documents__btn--danger"><i class="fas fa-unlink" aria-hidden="true"></i> Retirer la référence</button>
                                </form>
                            <?php else: ?>
                                <a href="modifier.php?id=<?php echo (int) $id; ?>" class="er-documents__btn er-documents__btn--primary">
                                    <i class="fas fa-plus" aria-hidden="true"></i> Ajouter un PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>

                    <?php foreach ($documents_extra as $doc): ?>
                        <?php
                        $doc_rel = trim((string) ($doc['fichier_chemin'] ?? ''));
                        $doc_id_row = (int) ($doc['id'] ?? 0);
                        $doc_nature = trim((string) ($doc['nature'] ?? ''));
                        $doc_disk = $doc_rel !== '' && strpos($doc_rel, '..') === false
                            && is_file($upload_disk_root . str_replace('/', DIRECTORY_SEPARATOR, $doc_rel));
                        $doc_ext = strtolower(pathinfo($doc_rel, PATHINFO_EXTENSION));
                        $pill_class = 'er-documents__pill--muted';
                        $fa_icon = 'fa-file';
                        if ($doc_ext === 'pdf') {
                            $pill_class = 'er-documents__pill--pdf';
                            $fa_icon = 'fa-file-pdf';
                        } elseif (in_array($doc_ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                            $pill_class = 'er-documents__pill--img';
                            $fa_icon = 'fa-file-image';
                        }
                        ?>
                        <article class="er-documents__item <?php echo $doc_disk ? 'er-documents__item--ok' : 'er-documents__item--warn'; ?>">
                            <div class="er-documents__item-badge er-documents__item-badge--alt" aria-hidden="true">
                                <i class="fas <?php echo $fa_icon; ?>"></i>
                            </div>
                            <div class="er-documents__item-body">
                                <h3 class="er-documents__item-title"><?php echo htmlspecialchars($doc_nature !== '' ? $doc_nature : 'Document'); ?></h3>
                                <p class="er-documents__item-meta">
                                    <?php if ($doc_disk): ?>
                                        <span class="er-documents__pill <?php echo $pill_class; ?>"><?php echo htmlspecialchars(strtoupper($doc_ext)); ?></span>
                                        <span class="er-documents__item-filename"><?php echo htmlspecialchars(basename($doc_rel)); ?></span>
                                        <?php if (!empty($doc['date_creation'])): ?>
                                            <span class="er-documents__item-date">Ajouté le <?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $doc['date_creation']))); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="er-documents__pill er-documents__pill--alert">Fichier manquant</span>
                                        <span class="er-documents__item-note">Le fichier n’est plus sur le serveur.</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="er-documents__item-actions">
                                <?php if ($doc_disk): ?>
                                    <a href="<?php echo htmlspecialchars($upload_public . $doc_rel); ?>" class="er-documents__btn er-documents__btn--primary" target="_blank" rel="noopener">
                                        <i class="fas fa-external-link-alt" aria-hidden="true"></i> Ouvrir
                                    </a>
                                    <a href="<?php echo htmlspecialchars($upload_public . $doc_rel); ?>" class="er-documents__btn er-documents__btn--secondary" download>
                                        <i class="fas fa-download" aria-hidden="true"></i> Télécharger
                                    </a>
                                <?php endif; ?>
                                <form method="post" action="details.php?id=<?php echo (int) $id; ?>" class="er-documents__form-del">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="supprimer_document_id" value="<?php echo (int) $doc_id_row; ?>">
                                    <button type="submit" class="er-documents__btn er-documents__btn--danger"><i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
            </section>

            <section class="er-fiche-tabs__panel er-fiche-tabs__panel--abs" role="tabpanel" aria-labelledby="er-tab-lbl-abs">
                <div class="er-abs-subtabs">
                    <input type="radio" name="er_abs_sub" id="er_abs_sub_list" class="er-abs-subtabs__state"<?php echo $abs_sub_chk['list']; ?>>
                    <input type="radio" name="er_abs_sub" id="er_abs_sub_justif" class="er-abs-subtabs__state"<?php echo $abs_sub_chk['justif']; ?>>
                    <input type="radio" name="er_abs_sub" id="er_abs_sub_auth" class="er-abs-subtabs__state"<?php echo $abs_sub_chk['auth']; ?>>

                    <nav class="er-abs-subtabs__bar" role="tablist" aria-label="Sous-sections absences">
                        <label for="er_abs_sub_list" class="er-abs-subtabs__tab er-abs-subtabs__tab--list" role="tab">
                            <span class="er-abs-subtabs__ic" aria-hidden="true"><i class="fas fa-calendar-xmark"></i></span>
                            <span class="er-abs-subtabs__txt">
                                <span class="er-abs-subtabs__label" id="er-abs-sub-lbl-list">Absences</span>
                                <span class="er-abs-subtabs__hint"><?php echo (int) $nb_absences; ?> enregistrée(s)</span>
                            </span>
                        </label>
                        <label for="er_abs_sub_justif" class="er-abs-subtabs__tab er-abs-subtabs__tab--justif" role="tab">
                            <span class="er-abs-subtabs__ic" aria-hidden="true"><i class="fas fa-file-signature"></i></span>
                            <span class="er-abs-subtabs__txt">
                                <span class="er-abs-subtabs__label" id="er-abs-sub-lbl-justif">Justificatifs</span>
                                <span class="er-abs-subtabs__hint"><?php echo (int) count($lignes_justifs); ?> pièce(s)</span>
                            </span>
                        </label>
                        <label for="er_abs_sub_auth" class="er-abs-subtabs__tab er-abs-subtabs__tab--auth" role="tab">
                            <span class="er-abs-subtabs__ic" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                            <span class="er-abs-subtabs__txt">
                                <span class="er-abs-subtabs__label" id="er-abs-sub-lbl-auth">Autorisations</span>
                                <span class="er-abs-subtabs__hint"><?php echo (int) $nb_autorisations_abs; ?> autorisation · période</span>
                            </span>
                        </label>
                    </nav>

                    <div class="er-abs-subtabs__panel er-abs-subtabs__panel--list" role="tabpanel" aria-labelledby="er-abs-sub-lbl-list">
                        <section class="er-detail-card er-detail-card--full er-detail-card--abs-nested">
                            <h2 class="er-detail-card__title"><i class="fas fa-list-ul" aria-hidden="true"></i> Liste des absences</h2>
            <?php if (empty($lignes_abs)): ?>
                <p class="er-detail-muted">Aucune absence liée à cette fiche (hors comptes admin).</p>
            <?php else: ?>
                <div class="er-table-scroll">
                    <table class="er-detail-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Motif</th>
                                <th class="er-detail-th-num">Pénalité</th>
                                <th>Retenue salaire</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes_abs as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($r['date_absence']))); ?></td>
                                    <td><?php echo htmlspecialchars(mb_strimwidth((string) $r['motif'], 0, 88, '…', 'UTF-8')); ?></td>
                                    <td class="er-detail-td-num"><?php
                                        $pmon = isset($r['penalite_montant']) ? (float) $r['penalite_montant'] : 0.0;
                                        echo $pmon > 0 ? htmlspecialchars(number_format($pmon, 0, ',', ' ')) . ' F' : '—';
                                    ?></td>
                                    <td><?php
                                        $pded = (int) ($r['penalite_deduite_bulletin_id'] ?? 0);
                                        $pret = !empty($r['penalite_retenir_salaire']);
                                        if ($pmon > 0) {
                                            if ($pded > 0) {
                                                echo '<span class="er-pill ok">Déduite</span> ';
                                                echo '<a class="er-link er-link--small" href="bulletin_paie_voir.php?id=' . (int) $pded . '">Bulletin #' . (int) $pded . '</a>';
                                            } elseif ($pret) {
                                                echo '<span class="er-pill wait">Au prochain bulletin</span>';
                                            } else {
                                                echo '<span class="er-detail-muted">Non</span>';
                                            }
                                        } elseif ($pret) {
                                            echo '<span class="er-pill wait">Au prochain bulletin</span>';
                                        } elseif ($pded > 0) {
                                            echo '<span class="er-pill ok">Déduite</span> ';
                                            echo '<a class="er-link er-link--small" href="bulletin_paie_voir.php?id=' . (int) $pded . '">Bulletin #' . (int) $pded . '</a>';
                                        } else {
                                            echo '<span class="er-detail-muted">—</span>';
                                        }
                                    ?></td>
                                    <td><?php echo !empty($r['justif_id']) ? '<span class="er-pill ok">Justifiée</span>' : '<span class="er-pill wait">En attente</span>'; ?></td>
                                    <td><?php
                                        if ($pded <= 0 && !$pret) {
                                            ?>
                                            <form method="post" action="details.php?id=<?php echo (int) $id; ?>&tab=abs&abs_sub=list" class="er-abs-penal-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="absence_retenir_penalite" value="1">
                                                <input type="hidden" name="absence_id_retenir" value="<?php echo (int) ($r['absence_id'] ?? 0); ?>">
                                                <button type="submit" class="er-btn-penal-ret">Retenir sur le salaire</button>
                                            </form>
                                            <?php
                                        } else {
                                            echo '—';
                                        }
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
                        </section>
                    </div>

                    <div class="er-abs-subtabs__panel er-abs-subtabs__panel--justif" role="tabpanel" aria-labelledby="er-abs-sub-lbl-justif">
                        <section class="er-detail-card er-detail-card--full er-detail-card--abs-nested">
                            <h2 class="er-detail-card__title"><i class="fas fa-file-signature" aria-hidden="true"></i> Justificatifs d’absence</h2>
            <?php if (empty($lignes_justifs)): ?>
                <p class="er-detail-muted">Aucun justificatif enregistré pour cette personne.</p>
            <?php else: ?>
                <div class="er-table-scroll">
                    <table class="er-detail-table">
                        <thead>
                            <tr>
                                <th>Date absence</th>
                                <th>Nom du justificatif</th>
                                <th>Détail / fichier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes_justifs as $j): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($j['date_absence']))); ?></td>
                                    <td><?php echo htmlspecialchars($j['libelle']); ?></td>
                                    <td>
                                        <?php if (!empty($j['fichier_rel'])): ?>
                                            <a href="<?php echo htmlspecialchars($upload_public . $j['fichier_rel']); ?>" target="_blank" rel="noopener" class="er-link"><i class="fas fa-image"></i> Ouvrir le fichier</a>
                                        <?php endif; ?>
                                        <?php if (!empty($j['snippet']) && empty($j['fichier_rel'])): ?>
                                            <span class="er-detail-muted"><?php echo htmlspecialchars(mb_strimwidth(trim((string) $j['snippet']), 0, 120, '…', 'UTF-8')); ?></span>
                                        <?php elseif (!empty($j['snippet']) && !empty($j['fichier_rel'])): ?>
                                            <div class="er-detail-muted sm"><?php echo htmlspecialchars(mb_strimwidth(trim((string) $j['snippet']), 0, 100, '…', 'UTF-8')); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
                        </section>
                    </div>

                    <div class="er-abs-subtabs__panel er-abs-subtabs__panel--auth" role="tabpanel" aria-labelledby="er-abs-sub-lbl-auth">
                        <section class="er-detail-card er-detail-card--full er-detail-card--abs-nested">
                            <header class="er-abs-auth-block__head">
                                <h2 class="er-detail-card__title er-abs-auth-block__title"><i class="fas fa-stamp" aria-hidden="true"></i> Autorisations d’absence</h2>
                                <p class="er-abs-auth-block__intro">Périodes validées par la RH (congés, absences autorisées, etc.).</p>
                                <button type="button" class="er-abs-auth-block__btn page-comptes-cta" id="erAbsAuthOpenBtn">
                                    <i class="fas fa-plus-circle" aria-hidden="true"></i> Enregistrer une autorisation
                                </button>
                            </header>
            <?php if (empty($lignes_autorisations)): ?>
                <p class="er-detail-muted">Aucune autorisation enregistrée pour cette fiche.</p>
            <?php else: ?>
                <div class="er-table-scroll">
                    <table class="er-detail-table">
                        <thead>
                            <tr>
                                <th>Période</th>
                                <th>Motif / objet</th>
                                <th>Enregistré par</th>
                                <th>Date saisie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes_autorisations as $auth): ?>
                                <?php
                                $ap = trim((string) ($auth['admin_prenom'] ?? '') . ' ' . (string) ($auth['admin_nom'] ?? ''));
                                $par = $ap !== '' ? $ap : (!empty($auth['admin_email']) ? (string) $auth['admin_email'] : '—');
                                ?>
                                <tr>
                                    <td>
                                        <span class="er-abs-auth-period">
                                            <?php echo htmlspecialchars(date('d/m/Y', strtotime((string) ($auth['date_debut'] ?? '')))); ?>
                                            →
                                            <?php echo htmlspecialchars(date('d/m/Y', strtotime((string) ($auth['date_fin'] ?? '')))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(mb_strimwidth((string) ($auth['motif'] ?? ''), 0, 100, '…', 'UTF-8')); ?></td>
                                    <td><?php echo htmlspecialchars($par); ?></td>
                                    <td><?php echo !empty($auth['date_creation']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $auth['date_creation']))) : '—'; ?></td>
                                </tr>
                                <?php if (!empty($auth['commentaire'])): ?>
                                    <tr class="er-abs-auth-note-row">
                                        <td colspan="4" class="er-abs-auth-note">
                                            <span class="er-abs-auth-note-label">Commentaire interne</span>
                                            <?php echo nl2br(htmlspecialchars((string) $auth['commentaire'])); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
                        </section>
                    </div>
                </div>
            </section>

            <section class="er-fiche-tabs__panel er-fiche-tabs__panel--san" role="tabpanel" aria-labelledby="er-tab-lbl-san">
                <section class="er-sanctions-panel" aria-labelledby="er-sanctions-title">
                    <header class="er-sanctions-panel__head">
                        <div class="er-sanctions-panel__masthead">
                            <div class="er-sanctions-panel__icon" aria-hidden="true"><i class="fas fa-scale-balanced"></i></div>
                            <div class="er-sanctions-panel__titles">
                                <h2 id="er-sanctions-title" class="er-sanctions-panel__title">Sanctions &amp; discipline</h2>
                                <p class="er-sanctions-panel__subtitle">Historique des mesures disciplinaires enregistrées pour cette fiche.</p>
                            </div>
                        </div>
                        <button type="button" class="er-sanctions-panel__btn-add page-comptes-cta" id="erSanctionOpenBtn">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i> Enregistrer une sanction
                        </button>
                    </header>
                    <?php if (empty($lignes_sanctions)): ?>
                        <p class="er-detail-muted er-sanctions-panel__empty">Aucune mesure disciplinaire enregistrée pour le moment.</p>
                    <?php else: ?>
                        <div class="er-table-scroll er-sanctions-panel__table-wrap">
                            <table class="er-detail-table er-sanctions-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Motif (résumé)</th>
                                        <th>Mesure</th>
                                        <th>Enregistré par</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lignes_sanctions as $s): ?>
                                        <?php
                                        $stype = (string) ($s['type_sanction'] ?? '');
                                        $stype_label = isset($sanction_types_choices[$stype]) ? $sanction_types_choices[$stype] : $stype;
                                        $ap = trim((string) ($s['admin_prenom'] ?? '') . ' ' . (string) ($s['admin_nom'] ?? ''));
                                        $par = $ap !== '' ? $ap : (!(empty($s['admin_email'])) ? (string) $s['admin_email'] : '—');
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) ($s['date_constat'] ?? '')))); ?></td>
                                            <td><span class="er-sanctions-type-pill"><?php echo htmlspecialchars($stype_label); ?></span></td>
                                            <td><?php echo htmlspecialchars(mb_strimwidth((string) ($s['motif'] ?? ''), 0, 80, '…', 'UTF-8')); ?></td>
                                            <td><?php echo htmlspecialchars(mb_strimwidth((string) ($s['mesure'] ?? ''), 0, 80, '…', 'UTF-8')); ?></td>
                                            <td class="er-sanctions-table__admin"><?php echo htmlspecialchars($par); ?></td>
                                        </tr>
                                        <?php if (!empty($s['commentaire'])): ?>
                                            <tr class="er-sanctions-table__note-row">
                                                <td colspan="5" class="er-sanctions-table__note">
                                                    <span class="er-sanctions-table__note-label">Note interne</span>
                                                    <?php echo nl2br(htmlspecialchars((string) $s['commentaire'])); ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <section class="er-fiche-tabs__panel er-fiche-tabs__panel--pret" role="tabpanel" aria-labelledby="er-tab-lbl-pret">
                <section class="er-prets-panel" aria-labelledby="er-prets-title">
                    <header class="er-prets-panel__head">
                        <div class="er-prets-panel__masthead">
                            <div class="er-prets-panel__icon" aria-hidden="true"><i class="fas fa-piggy-bank"></i></div>
                            <div class="er-prets-panel__titles">
                                <h2 id="er-prets-title" class="er-prets-panel__title">Gestion des prêts</h2>
                                <p class="er-prets-panel__subtitle"><?php echo (int) $nb_prets; ?> prêt(s) au total<?php if ($nb_prets_en_cours > 0): ?> dont <strong><?php echo (int) $nb_prets_en_cours; ?></strong> en cours<?php endif; ?>.</p>
                            </div>
                        </div>
                        <button type="button" class="er-prets-panel__btn-add page-comptes-cta" id="erPretOpenBtn">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i> Ajouter un prêt
                        </button>
                    </header>
                    <?php if (empty($lignes_prets)): ?>
                        <p class="er-detail-muted er-prets-panel__empty">Aucun prêt enregistré pour cette fiche.</p>
                    <?php else: ?>
                        <div class="er-table-scroll er-prets-panel__table-wrap">
                            <table class="er-detail-table er-prets-table">
                                <thead>
                                    <tr>
                                        <th>Date d’octroi</th>
                                        <th>Montant prêt</th>
                                        <th>Fin prévue</th>
                                        <th>Montant versé</th>
                                        <th>Reste à payer</th>
                                        <th>Statut</th>
                                        <th class="er-prets-table__th-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lignes_prets as $pr): ?>
                                        <?php
                                        $pst = (string) ($pr['statut'] ?? '');
                                        $plab = isset($pret_statuts_choices[$pst]) ? $pret_statuts_choices[$pst] : $pst;
                                        $ap = trim((string) ($pr['admin_prenom'] ?? '') . ' ' . (string) ($pr['admin_nom'] ?? ''));
                                        $par = $ap !== '' ? $ap : (!empty($pr['admin_email']) ? (string) $pr['admin_email'] : '—');
                                        $m = isset($pr['montant']) ? (float) $pr['montant'] : 0.0;
                                        $verse = isset($pr['montant_verse']) ? (float) $pr['montant_verse'] : 0.0;
                                        $reste = max(0, round($m - $verse, 2));
                                        $df = $pr['date_fin_prevue'] ?? '';
                                        $mens = isset($pr['mensualite']) && $pr['mensualite'] !== null && $pr['mensualite'] !== '' ? (float) $pr['mensualite'] : null;
                                        $pid = (int) ($pr['id'] ?? 0);
                                        $rems = $prets_remb_par_pret[$pid] ?? [];
                                        $rem_out = [];
                                        foreach ($rems as $rw) {
                                            $ra = trim((string) ($rw['admin_prenom'] ?? '') . ' ' . (string) ($rw['admin_nom'] ?? ''));
                                            $rem_out[] = [
                                                'montant_fcfa' => number_format((float) ($rw['montant'] ?? 0), 2, ',', ' '),
                                                'date_fr'      => !empty($rw['date_versement']) ? date('d/m/Y', strtotime((string) $rw['date_versement'])) : '—',
                                                'commentaire'  => (string) ($rw['commentaire'] ?? ''),
                                                'enregistre_par' => $ra !== '' ? $ra : (!empty($rw['admin_email']) ? (string) $rw['admin_email'] : '—'),
                                                'saisi_le_fr' => !empty($rw['date_creation']) ? date('d/m/Y H:i', strtotime((string) $rw['date_creation'])) : '—',
                                            ];
                                        }
                                        $detail_payload = [
                                            'id'              => $pid,
                                            'montant_fcfa'    => number_format($m, 2, ',', ' '),
                                            'verse_fcfa'      => number_format($verse, 2, ',', ' '),
                                            'reste_fcfa'      => number_format($reste, 2, ',', ' '),
                                            'reste_num'       => $reste,
                                            'date_octroi_fr'  => !empty($pr['date_octroi']) ? date('d/m/Y', strtotime((string) $pr['date_octroi'])) : '—',
                                            'fin_prevue_fr'   => $df !== '' ? date('d/m/Y', strtotime((string) $df)) : '—',
                                            'mensualite_fcfa' => $mens !== null ? number_format($mens, 2, ',', ' ') : null,
                                            'motif'           => (string) ($pr['motif'] ?? ''),
                                            'statut'          => $pst,
                                            'statut_label'    => $plab,
                                            'commentaire_pret'=> (string) ($pr['commentaire'] ?? ''),
                                            'saisi_par'       => $par,
                                            'date_creation_pret_fr' => !empty($pr['date_creation']) ? date('d/m/Y H:i', strtotime((string) $pr['date_creation'])) : '—',
                                            'remboursements'  => $rem_out,
                                        ];
                                        $detail_json = htmlspecialchars(json_encode($detail_payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                        $peut_rembourser = $pst !== 'annule' && $reste > 0.005;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) ($pr['date_octroi'] ?? '')))); ?></td>
                                            <td><span class="er-prets-montant"><?php echo htmlspecialchars(number_format($m, 2, ',', ' ')); ?> <span class="er-prets-currency">FCFA</span></span></td>
                                            <td><?php echo $df !== '' ? htmlspecialchars(date('d/m/Y', strtotime((string) $df))) : '—'; ?></td>
                                            <td><span class="er-prets-montant er-prets-montant--verse"><?php echo htmlspecialchars(number_format($verse, 2, ',', ' ')); ?></span></td>
                                            <td>
                                                <?php if ($reste <= 0.005): ?>
                                                    <span class="er-prets-reste er-prets-reste--zero">0,00 <span class="er-prets-currency">FCFA</span></span>
                                                <?php else: ?>
                                                    <span class="er-prets-reste"><?php echo htmlspecialchars(number_format($reste, 2, ',', ' ')); ?> <span class="er-prets-currency">FCFA</span></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="er-prets-stat er-prets-stat--<?php echo htmlspecialchars(preg_replace('/[^a-z_]/', '', $pst)); ?>"><?php echo htmlspecialchars($plab); ?></span></td>
                                            <td class="er-prets-table__actions">
                                                <div class="er-prets-action-btns">
                                                    <button type="button" class="er-pret-btn-detail" data-pret-detail="<?php echo $detail_json; ?>">
                                                        <i class="fas fa-circle-info" aria-hidden="true"></i><span>Détail</span>
                                                    </button>
                                                    <?php if ($peut_rembourser): ?>
                                                        <button type="button" class="er-pret-btn-remb"
                                                            data-pret-id="<?php echo (int) $pid; ?>"
                                                            data-reste-num="<?php echo htmlspecialchars((string) $reste); ?>"
                                                            data-reste-fr="<?php echo htmlspecialchars(number_format($reste, 2, ',', ' ')); ?>">
                                                            <i class="fas fa-money-bill-wave" aria-hidden="true"></i><span>Rembourser</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <section class="er-fiche-tabs__panel er-fiche-tabs__panel--transport" role="tabpanel" aria-labelledby="er-tab-lbl-transport">
                <section class="er-detail-card er-detail-card--full">
                    <header class="er-prets-panel__head">
                        <div class="er-prets-panel__masthead">
                            <div class="er-prets-panel__icon" aria-hidden="true"><i class="fas fa-bus"></i></div>
                            <div class="er-prets-panel__titles">
                                <h2 class="er-prets-panel__title">Prime transport — déductions en jours</h2>
                                <p class="er-prets-panel__subtitle">
                                    Prime mensuelle paramétrée : <strong><?php echo htmlspecialchars(number_format($transport_prime_mensuelle, 0, ',', ' ')); ?> FCFA</strong>
                                    · référence mensuelle : <strong><?php echo (int) $transport_jours_ref; ?> jour(s)</strong>.
                                </p>
                            </div>
                        </div>
                    </header>

                    <form method="post" action="details.php?id=<?php echo (int) $id; ?>&tab=transport" class="er-bp-form" id="transportRetraitForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="ajouter_retrait_transport" value="1">
                        <input type="hidden" id="transport_prime_mensuelle_ref" value="<?php echo htmlspecialchars(number_format($transport_prime_mensuelle, 2, '.', '')); ?>">
                        <input type="hidden" id="transport_jours_reference_ref" value="<?php echo (int) $transport_jours_ref; ?>">
                        <input type="hidden" id="transport_totaux_map_json" value="<?php echo htmlspecialchars(json_encode($transport_map, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="er-bp-form__grid">
                            <div class="er-bp-field">
                                <label for="transport_mois_paie">Mois de paie <span class="req">*</span></label>
                                <input type="month" id="transport_mois_paie" name="transport_mois_paie" required value="<?php
                                    $transport_mois_form = isset($_POST['transport_mois_paie']) ? (string) $_POST['transport_mois_paie'] : $transport_mois_def;
                                    echo htmlspecialchars($transport_mois_form);
                                ?>">
                            </div>
                            <div class="er-bp-field">
                                <label for="transport_nb_jours">Nombre de jours à déduire <span class="req">*</span></label>
                                <input type="number" id="transport_nb_jours" name="transport_nb_jours" min="1" max="31" step="1" required value="<?php
                                    $transport_jours_form = isset($_POST['transport_nb_jours']) ? (string) $_POST['transport_nb_jours'] : '1';
                                    echo htmlspecialchars($transport_jours_form);
                                ?>">
                            </div>
                            <div class="er-bp-field">
                                <label>Montant déduit (temps réel)</label>
                                <div class="er-bp-salaire-readonly" id="transport_montant_preview">0 FCFA</div>
                            </div>
                            <div class="er-bp-field">
                                <label>Prime transport restante (mois)</label>
                                <div class="er-bp-salaire-readonly" id="transport_prime_restante_preview">0 FCFA</div>
                            </div>
                            <div class="er-bp-field er-bp-field--full">
                                <label for="transport_commentaire">Commentaire (optionnel)</label>
                                <textarea id="transport_commentaire" name="transport_commentaire" rows="2" maxlength="500" placeholder="Motif / observation"><?php
                                    echo isset($_POST['transport_commentaire']) ? htmlspecialchars((string) $_POST['transport_commentaire']) : '';
                                ?></textarea>
                            </div>
                        </div>
                        <div class="er-form-actions">
                            <button type="submit" class="er-btn er-btn--primary"><i class="fas fa-check"></i> Enregistrer la déduction transport</button>
                        </div>
                    </form>

                    <?php if (empty($transport_lignes)): ?>
                        <p class="er-detail-muted">Aucune déduction transport enregistrée pour cette fiche.</p>
                    <?php else: ?>
                        <div class="er-table-scroll">
                            <table class="er-detail-table">
                                <thead>
                                    <tr>
                                        <th>Mois</th>
                                        <th>Jours déduits</th>
                                        <th>Montant déduit</th>
                                        <th>Saisi par</th>
                                        <th>Date saisie</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($transport_lignes as $tr): ?>
                                    <?php
                                    $transport_admin = trim((string) ($tr['admin_prenom'] ?? '') . ' ' . (string) ($tr['admin_nom'] ?? ''));
                                    $transport_par = $transport_admin !== '' ? $transport_admin : (!empty($tr['admin_email']) ? (string) $tr['admin_email'] : '—');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($tr['mois_paie'] ?? '')); ?></td>
                                        <td><?php echo (int) ($tr['nb_jours'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars(number_format((float) ($tr['montant_deduit'] ?? 0), 0, ',', ' ')); ?> FCFA</td>
                                        <td><?php echo htmlspecialchars($transport_par); ?></td>
                                        <td><?php echo !empty($tr['date_creation']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $tr['date_creation']))) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <section class="er-fiche-tabs__panel er-fiche-tabs__panel--conges" role="tabpanel" aria-labelledby="er-tab-lbl-conges">
                <section class="er-detail-card er-detail-card--full">
                    <header class="er-prets-panel__head">
                        <div class="er-prets-panel__masthead">
                            <div class="er-prets-panel__icon" aria-hidden="true"><i class="fas fa-umbrella-beach"></i></div>
                            <div class="er-prets-panel__titles">
                                <h2 class="er-prets-panel__title">Congés annuels</h2>
                                <p class="er-prets-panel__subtitle">
                                    Quota global : <strong><?php echo (int) $conges_quota_global; ?> jour(s)</strong> ·
                                    pris en <?php echo htmlspecialchars($conges_annee_def); ?> : <strong><?php echo (int) $conges_pris_annee_def; ?> jour(s)</strong> ·
                                    restant : <strong><?php echo (int) $conges_restant_annee_def; ?> jour(s)</strong>
                                </p>
                            </div>
                        </div>
                        <button type="button" class="er-prets-panel__btn-add page-comptes-cta" id="erCongeOpenBtn">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i> Ajouter un congé
                        </button>
                    </header>

                    <?php if (empty($conges_lignes)): ?>
                        <p class="er-detail-muted">Aucun congé enregistré pour cette fiche.</p>
                    <?php else: ?>
                        <div class="er-table-scroll">
                            <table class="er-detail-table">
                                <thead>
                                    <tr>
                                        <th>Mois</th>
                                        <th>Jours</th>
                                        <th>Notes</th>
                                        <th>Saisi par</th>
                                        <th>Date saisie</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($conges_lignes as $cg): ?>
                                    <?php
                                    $conge_admin = trim((string) ($cg['admin_prenom'] ?? '') . ' ' . (string) ($cg['admin_nom'] ?? ''));
                                    $conge_par = $conge_admin !== '' ? $conge_admin : (!empty($cg['admin_email']) ? (string) $cg['admin_email'] : '—');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($cg['mois_conge'] ?? '')); ?></td>
                                        <td><?php echo (int) ($cg['nb_jours'] ?? 0); ?></td>
                                        <td><?php echo !empty($cg['notes']) ? htmlspecialchars((string) $cg['notes']) : '—'; ?></td>
                                        <td><?php echo htmlspecialchars($conge_par); ?></td>
                                        <td><?php echo !empty($cg['date_creation']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $cg['date_creation']))) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <section class="er-fiche-tabs__panel er-fiche-tabs__panel--bp" role="tabpanel" aria-labelledby="er-tab-lbl-bp">
                <section class="er-bp-panel">
                    <header class="er-bp-panel__head">
                        <h2 class="er-bp-panel__title"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Bulletins de paie</h2>
                        <?php if ($bp_tables_ok) : ?>
                            <button type="button" class="er-bp-btn-generate er-bp-btn-generate--head" id="erBpOpenBtn">
                                <span class="er-bp-btn-generate__icon-box" aria-hidden="true">
                                    <i class="fas fa-file-circle-plus"></i>
                                </span>
                                <span class="er-bp-btn-generate__txt">Générer un bulletin de paie</span>
                            </button>
                        <?php endif; ?>
                    </header>
                    <?php if (!$bp_tables_ok) : ?>
                        <div class="message error"><i class="fas fa-database" aria-hidden="true"></i> Exécutez la migration :
                            <code>php migrations/run_create_bulletin_paie.php</code></div>
                    <?php else :
                        $rub_bp = $bp_params['rubriques'];
                        $bp_taux = $bp_params['retenues_taux'];
                        $bp_pct_codes = bp_retenues_codes_taux_brut();
                        ?>
                    <div class="er-bp-layout er-bp-layout--full">
                        <div class="er-bp-card er-bp-card--history">
                            <div class="er-bp-history-head">
                                <h3 class="er-bp-card__title er-bp-history-head__title"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Historique des bulletins</h3>
                                <p class="er-bp-history-head__hint">Bulletins enregistrés pour ce salarié — ouvrez la vue dédiée pour imprimer.</p>
                            </div>
                            <?php if (empty($bp_list)) : ?>
                                <div class="er-bp-history-empty">
                                    <i class="fas fa-file-lines" aria-hidden="true"></i>
                                    <p>Aucun bulletin enregistré pour ce salarié.</p>
                                    <p class="er-bp-history-empty__sub">Utilisez le bouton « Générer un bulletin de paie » ci-dessus pour créer le premier bulletin.</p>
                                </div>
                            <?php else : ?>
                                <div class="er-table-scroll er-bp-history-scroll">
                                    <table class="er-bp-history-table">
                                        <thead>
                                            <tr>
                                                <th>Mois de paie</th>
                                                <th>Date paiement</th>
                                                <th>Enregistré le</th>
                                                <th class="er-bp-th-num">Brut (FCFA)</th>
                                                <th class="er-bp-th-num">Net à payer</th>
                                                <th class="er-bp-th-actions"><span class="sr-only">Actions</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bp_list as $bl) : ?>
                                                <tr>
                                                    <td><span class="er-bp-pill"><?php echo htmlspecialchars((string) ($bl['mois_paie'] ?? '')); ?></span></td>
                                                    <td><?php echo !empty($bl['date_paiement']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $bl['date_paiement']))) : '—'; ?></td>
                                                    <td class="er-bp-td-muted"><?php echo !empty($bl['date_creation']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $bl['date_creation']))) : '—'; ?></td>
                                                    <td class="er-bp-td-num"><?php echo htmlspecialchars(number_format((float) ($bl['montant_brut'] ?? 0), 0, ',', ' ')); ?></td>
                                                    <td class="er-bp-td-num er-bp-td-net"><?php echo htmlspecialchars(number_format((float) ($bl['net_a_payer'] ?? 0), 0, ',', ' ')); ?></td>
                                                    <td class="er-bp-td-actions">
                                                        <a class="er-bp-btn-voir" href="bulletin_paie_voir.php?id=<?php echo (int) ($bl['id'] ?? 0); ?>">
                                                            <i class="fas fa-file-lines" aria-hidden="true"></i>
                                                            <span>Voir le bulletin</span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="erBpModal" class="er-bp-modal<?php echo $bp_modal_open ? ' is-open' : ''; ?>"
                        role="dialog" aria-modal="true" aria-labelledby="erBpModalTitle"
                        aria-hidden="<?php echo $bp_modal_open ? 'false' : 'true'; ?>">
                        <div class="er-bp-modal__backdrop" id="erBpModalBackdrop" aria-hidden="true"></div>
                        <div class="er-bp-modal__dialog">
                            <header class="er-bp-modal__head">
                                <div class="er-bp-modal__head-text">
                                    <p class="er-bp-modal__eyebrow"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Bulletin de paie</p>
                                    <h2 id="erBpModalTitle" class="er-bp-modal__title">Générer un bulletin</h2>
                                    <p class="er-bp-modal__lede">Période, salaire de base et montants — le document sera enregistré puis ouvert pour impression format A4.</p>
                                </div>
                                <button type="button" class="er-bp-modal__close" id="erBpModalClose" aria-label="Fermer">&times;</button>
                            </header>
                            <div class="er-bp-modal__body">
                                <form class="er-bp-form er-bp-form--modal" method="post" action="bulletin_paie_enregistrer.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="employe_id" value="<?php echo (int) $id; ?>">
                                    <input type="hidden" name="generer_bulletin_paie" value="1">
                                    <div class="er-bp-form__section">
                                        <span class="er-bp-form__section-tag">Étape 1</span>
                                        <h4 class="er-bp-form__section-title">Période &amp; salaire</h4>
                                        <div class="er-bp-form__grid">
                                            <div class="er-bp-field">
                                                <label for="bp_mois_paie">Mois de paie <span class="req">*</span></label>
                                                <input type="month" id="bp_mois_paie" name="mois_paie" required
                                                    value="<?php echo htmlspecialchars($bp_def_mois); ?>">
                                            </div>
                                            <div class="er-bp-field">
                                                <label for="bp_date_paiement">Date de paiement <span class="req">*</span></label>
                                                <input type="date" id="bp_date_paiement" name="date_paiement" required
                                                    value="<?php echo htmlspecialchars($bp_def_date_paiement); ?>">
                                            </div>
                                            <div class="er-bp-field er-bp-field--full">
                                                <label for="bp_salaire_display">Salaire de base (FCFA) <span class="req">*</span></label>
                                                <?php if ($bp_has_salaire_fiche) : ?>
                                                <input type="hidden" name="salaire_base" value="<?php echo htmlspecialchars($bp_salaire_post_value); ?>">
                                                <div class="er-bp-salaire-readonly" id="bp_salaire_display" role="status">
                                                    <span class="er-bp-salaire-readonly__amount"><?php echo htmlspecialchars(number_format((float) $bp_salaire_post_value, 0, ',', ' ')); ?></span>
                                                    <span class="er-bp-salaire-readonly__unit">FCFA</span>
                                                </div>
                                                <span class="er-bp-hint">Montant issu de la <strong>fiche employé</strong> — pour le modifier, enregistrez-le dans <a href="modifier.php?id=<?php echo (int) $id; ?>">Modifier la fiche</a>.</span>
                                                <?php else : ?>
                                                <p class="er-bp-salaire-missing">Aucun salaire brut n’est renseigné sur la fiche. Ajoutez-le dans <a href="modifier.php?id=<?php echo (int) $id; ?>">Modifier la fiche</a> avant de générer un bulletin.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $gain_codes_bp = ['heures_sup', 'prime_performance', 'prime_transport', 'assurance_maladie', 'sursalaire', 'indemnite_transport', 'indemnite_logement', 'indemnite_fonction'];
                                    $has_gain = false;
                                    foreach ($gain_codes_bp as $gc) {
                                        if (!empty($rub_bp['gains'][$gc])) {
                                            $has_gain = true;
                                            break;
                                        }
                                    }
                                    if ($has_gain) :
                                        ?>
                                    <div class="er-bp-form__section">
                                        <span class="er-bp-form__section-tag">Gains</span>
                                        <h4 class="er-bp-form__section-title">Montants complémentaires (FCFA)</h4>
                                        <input type="hidden" id="bp_prime_transport_config" value="<?php echo htmlspecialchars(number_format($transport_prime_mensuelle, 2, '.', '')); ?>">
                                        <input type="hidden" id="bp_prime_transport_map_json" value="<?php echo htmlspecialchars(json_encode($transport_map, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" id="bp_forfait_hs_cfg" value="<?php echo htmlspecialchars(number_format($bp_forfait_hs_cfg, 2, '.', '')); ?>">
                                        <div class="er-bp-form__grid">
                                            <?php foreach ($gain_codes_bp as $gc) :
                                                if (empty($rub_bp['gains'][$gc])) {
                                                    continue;
                                                }
                                                $fid = 'bp_g_' . $gc;
                                                ?>
                                            <div class="er-bp-field">
                                                <label for="<?php echo htmlspecialchars($fid); ?>"><?php echo htmlspecialchars($bp_lg[$gc] ?? $gc); ?></label>
                                                <?php if ($gc === 'prime_transport') : ?>
                                                    <input type="text" id="<?php echo htmlspecialchars($fid); ?>" name="g_<?php echo htmlspecialchars($gc); ?>" inputmode="decimal"
                                                        value="<?php echo htmlspecialchars(number_format($bp_prime_transport_net_def, 2, '.', '')); ?>" readonly>
                                                    <span class="er-bp-hint" id="bp_prime_transport_hint">
                                                        Prime nette = prime mensuelle − déductions transport du mois sélectionné.
                                                    </span>
                                                <?php elseif ($gc === 'sursalaire') : ?>
                                                    <input type="text" id="<?php echo htmlspecialchars($fid); ?>" name="g_<?php echo htmlspecialchars($gc); ?>" inputmode="decimal"
                                                        value="<?php echo htmlspecialchars(number_format($bp_forfait_hs_cfg, 2, '.', '')); ?>" readonly>
                                                    <span class="er-bp-hint">Montant du <strong>forfait HS</strong> défini dans <a href="../../parametres/bulletin_paie.php">Paramètres bulletin</a>.</span>
                                                <?php else : ?>
                                                    <input type="text" id="<?php echo htmlspecialchars($fid); ?>" name="g_<?php echo htmlspecialchars($gc); ?>" inputmode="decimal" value="0">
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php
                                    $ret_codes_bp = ['irpp', 'trimf', 'ipres_rg', 'ipres_cadre', 'css', 'accident_travail', 'pret_salaire', 'autres_retenues'];
                                    $has_ret = false;
                                    foreach ($ret_codes_bp as $rc) {
                                        if (!empty($rub_bp['retenues'][$rc])) {
                                            $has_ret = true;
                                            break;
                                        }
                                    }
                                    if ($has_ret) :
                                        ?>
                                    <div class="er-bp-form__section">
                                        <span class="er-bp-form__section-tag">Retenues</span>
                                        <h4 class="er-bp-form__section-title">Déductions</h4>
                                        <p class="er-bp-form__section-hint">Taux sur brut (IPRES, CSS) : <a href="../../parametres/bulletin_paie.php">Paramètres bulletin</a>. L’<strong>IRPP</strong> et la <strong>TRIMF</strong> (montants fixes) reprennent les valeurs de la <a href="modifier.php?id=<?php echo (int) $id; ?>">fiche employé</a>.</p>
                                        <div class="er-bp-form__grid">
                                            <?php foreach ($ret_codes_bp as $rc) :
                                                if (empty($rub_bp['retenues'][$rc])) {
                                                    continue;
                                                }
                                                if ($rc === 'irpp') :
                                                    ?>
                                            <div class="er-bp-field er-bp-field--pct">
                                                <span class="er-bp-field-label"><?php echo htmlspecialchars($bp_lr[$rc] ?? $rc); ?></span>
                                                <p class="er-bp-pct-display"><strong><?php echo htmlspecialchars(number_format($bp_irpp_montant_fiche, 0, ',', ' ')); ?> FCFA</strong> <span class="er-bp-hint--inline">(fiche employé)</span></p>
                                                <span class="er-bp-hint er-bp-hint--muted">Retenue appliquée à l’enregistrement du bulletin.</span>
                                            </div>
                                                <?php
                                                elseif ($rc === 'trimf') :
                                                    ?>
                                            <div class="er-bp-field er-bp-field--pct">
                                                <span class="er-bp-field-label"><?php echo htmlspecialchars($bp_lr[$rc] ?? $rc); ?></span>
                                                <p class="er-bp-pct-display"><strong><?php echo htmlspecialchars(number_format($bp_trimf_montant_fiche, 0, ',', ' ')); ?> FCFA</strong> <span class="er-bp-hint--inline">(fiche employé)</span></p>
                                                <span class="er-bp-hint er-bp-hint--muted">Retenue appliquée à l’enregistrement du bulletin.</span>
                                            </div>
                                                <?php
                                                elseif (in_array($rc, $bp_pct_codes, true)) :
                                                    $tp = (float) ($bp_taux[$rc] ?? 0);
                                                    ?>
                                            <div class="er-bp-field er-bp-field--pct">
                                                <span class="er-bp-field-label"><?php echo htmlspecialchars($bp_lr[$rc] ?? $rc); ?></span>
                                                <p class="er-bp-pct-display"><strong><?php echo htmlspecialchars(number_format($tp, 2, ',', ' ')); ?> %</strong> du salaire brut</p>
                                                <span class="er-bp-hint er-bp-hint--muted">Montant calculé lors de l’enregistrement du bulletin.</span>
                                            </div>
                                                <?php
                                                else :
                                                    $fid = 'bp_r_' . $rc;
                                                    ?>
                                            <div class="er-bp-field">
                                                <label for="<?php echo htmlspecialchars($fid); ?>"><?php echo htmlspecialchars($bp_lr[$rc] ?? $rc); ?> (FCFA)</label>
                                                <input type="text" id="<?php echo htmlspecialchars($fid); ?>" name="r_<?php echo htmlspecialchars($rc); ?>" inputmode="decimal" value="0">
                                            </div>
                                                <?php
                                                endif;
                                            endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php
                                    $tr = $rub_bp['travail'];
                                    if (!empty($tr['heures_travaillees']) || !empty($tr['heures_sup']) || !empty($tr['jours_presence'])) :
                                        ?>
                                    <div class="er-bp-form__section">
                                        <span class="er-bp-form__section-tag">Travail</span>
                                        <h4 class="er-bp-form__section-title">Temps &amp; présence</h4>
                                        <div class="er-bp-form__grid">
                                            <?php if (!empty($tr['heures_travaillees'])) : ?>
                                            <div class="er-bp-field">
                                                <label for="bp_t_heures_travaillees">Heures travaillées</label>
                                                <input type="text" id="bp_t_heures_travaillees" name="t_heures_travaillees" inputmode="decimal" value="">
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($tr['heures_sup'])) : ?>
                                            <div class="er-bp-field">
                                                <label for="bp_t_heures_sup_nombre">Heures sup. (nombre)</label>
                                                <input type="text" id="bp_t_heures_sup_nombre" name="t_heures_sup_nombre" inputmode="decimal" value="">
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($tr['jours_presence'])) : ?>
                                            <div class="er-bp-field er-bp-field--full">
                                                <span class="er-bp-field-label">Jours de présence</span>
                                                <?php
                                                $jp_ref = $bp_params ? (int) ($bp_params['jours_presence_defaut'] ?? 0) : 0;
                                                $pen_nj = (int) ($bp_pen_en_attente['nb_jours'] ?? count($bp_pen_en_attente['ids'] ?? []));
                                                if (bp_colonne_jours_presence_defaut_disponible() && $jp_ref > 0) {
                                                    $jp_aff = max(0, $jp_ref - $pen_nj);
                                                    ?>
                                                <p class="er-bp-pct-display"><strong><?php echo (int) $jp_aff; ?></strong> j. affichés sur le bulletin
                                                    (réf. commune <strong><?php echo (int) $jp_ref; ?></strong> −
                                                    <strong><?php echo (int) $pen_nj; ?></strong> j. d’absence retenus pour le mois <strong><?php echo htmlspecialchars($bp_def_mois); ?></strong>).</p>
                                                    <?php
                                                } else {
                                                    ?>
                                                <p class="er-bp-hint">Définissez la référence mensuelle dans <a href="../../parametres/bulletin_paie.php">Paramètres bulletin de paie</a>.</p>
                                                    <?php
                                                }
                                                ?>
                                                <span class="er-bp-hint er-bp-hint--muted">Le mois de paie choisi ci-dessous filtre les absences « retenir sur salaire » (montant et jours).</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($rub_bp['mentions']['mode_paiement'])) : ?>
                                    <div class="er-bp-form__section">
                                        <span class="er-bp-form__section-tag">Paiement</span>
                                        <h4 class="er-bp-form__section-title">Mode de règlement</h4>
                                        <div class="er-bp-field er-bp-field--full">
                                            <label for="bp_mode_paiement">Mode de paiement</label>
                                            <select id="bp_mode_paiement" name="mode_paiement">
                                                <option value="">—</option>
                                                <option value="Virement bancaire">Virement bancaire</option>
                                                <option value="Espèces">Espèces</option>
                                                <option value="Chèque">Chèque</option>
                                                <option value="Orange Money">Orange Money</option>
                                                <option value="Wave">Wave</option>
                                                <option value="Autre">Autre</option>
                                            </select>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php
                                    $bp_pen_tot = (float) ($bp_pen_en_attente['total'] ?? 0);
                                    $bp_pen_nj_banner = (int) ($bp_pen_en_attente['nb_jours'] ?? count($bp_pen_en_attente['ids'] ?? []));
                                    if ($bp_pen_tot > 0 || $bp_pen_nj_banner > 0) : ?>
                                    <div class="er-bp-penal-banner" role="status">
                                        <span class="er-bp-penal-banner__ic" aria-hidden="true"><i class="fas fa-hand-holding-dollar"></i></span>
                                        <div class="er-bp-penal-banner__txt">
                                            <strong>Pénalités d’absence</strong> marquées « retenir sur salaire » pour le mois <strong><?php echo htmlspecialchars($bp_def_mois); ?></strong> :
                                            <?php if ($bp_pen_tot > 0) : ?>
                                            <strong><?php echo htmlspecialchars(number_format($bp_pen_tot, 0, ',', ' ')); ?> FCFA</strong>
                                            <?php else : ?>
                                            montant <strong>au prorata</strong> (salaire de base ÷ jours de référence) par jour d’absence retenu
                                            <?php endif; ?>
                                            <?php if ($bp_pen_nj_banner > 0) : ?>
                                            — <strong><?php echo (int) $bp_pen_nj_banner; ?></strong> jour(s) déduit(s) du décompte de présence sur le bulletin.
                                            <?php endif; ?>
                                            Seront appliquées à l’enregistrement du bulletin (puis soldées sur chaque absence concernée).
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="er-bp-modal__footer">
                                        <button type="button" class="er-bp-modal__btn er-bp-modal__btn--ghost" id="erBpModalCancel">Annuler</button>
                                        <button type="submit" class="er-bp-modal__btn er-bp-modal__btn--primary"<?php echo !$bp_has_salaire_fiche ? ' disabled' : ''; ?>><i class="fas fa-file-circle-plus" aria-hidden="true"></i> Enregistrer &amp; ouvrir le bulletin</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php endif; ?>
                </section>
            </section>
        </div>

        <div id="erDetailDocsModal" class="er-docs-modal<?php echo $doc_panel_open ? ' is-open' : ''; ?>"
            role="dialog" aria-modal="true" aria-labelledby="erDocsModalTitle"
            aria-hidden="<?php echo $doc_panel_open ? 'false' : 'true'; ?>">
            <div class="er-docs-modal__backdrop" id="erDetailDocsModalBackdrop" aria-hidden="true"></div>
            <div class="er-docs-modal__dialog">
                <header class="er-docs-modal__head">
                    <div class="er-docs-modal__head-text">
                        <p class="er-docs-modal__eyebrow"><i class="fas fa-folder-plus" aria-hidden="true"></i> Dossier RH</p>
                        <h2 id="erDocsModalTitle" class="er-docs-modal__title">Nouveau document</h2>
                        <p class="er-docs-modal__lede">Décrivez la pièce puis sélectionnez le fichier — l’aperçu s’affiche tout de suite.</p>
                    </div>
                    <button type="button" class="er-docs-modal__close" id="erDetailDocsPanelClose" aria-label="Fermer">&times;</button>
                </header>
                <div class="er-docs-modal__body">
                    <?php if ($doc_form_err !== ''): ?>
                        <p class="er-documents__form-error er-docs-modal__alert" role="alert"><?php echo htmlspecialchars($doc_form_err); ?></p>
                    <?php endif; ?>
                    <div class="er-docs-modal__grid">
                        <div class="er-docs-modal__preview-col">
                            <div class="er-docs-modal__preview-header">
                                <span class="er-docs-modal__preview-tag">Aperçu en direct</span>
                                <span class="er-docs-modal__preview-hint">Image ou PDF</span>
                            </div>
                            <div class="er-docs-modal__preview-viewport">
                                <div id="erDocsPreviewEmpty" class="er-docs-modal__preview-placeholder">
                                    <div class="er-docs-modal__preview-placeholder-icon" aria-hidden="true">
                                        <i class="fas fa-file-import"></i>
                                    </div>
                                    <p class="er-docs-modal__preview-placeholder-title">Aucun fichier sélectionné</p>
                                    <p class="er-docs-modal__preview-placeholder-text">Choisissez un PDF ou une image pour afficher l’aperçu ici.</p>
                                </div>
                                <img src="" alt="" id="erDocsPreviewImg" class="er-docs-modal__preview-img er-docs-modal__preview-media is-hidden" width="400" height="300" decoding="async">
                                <iframe id="erDocsPreviewPdf" class="er-docs-modal__preview-pdf er-docs-modal__preview-media is-hidden" title="Aperçu du PDF"></iframe>
                            </div>
                            <p id="erDocsPreviewMeta" class="er-docs-modal__preview-meta is-hidden"></p>
                        </div>
                        <div class="er-docs-modal__form-col">
                            <form class="er-documents__form er-docs-modal__form" method="post" action="details.php?id=<?php echo (int) $id; ?>" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="ajouter_document" value="1">
                                <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">
                                <div class="er-documents__field">
                                    <label for="document_nature">Nature du document <span class="req">*</span></label>
                                    <input type="text" name="document_nature" id="document_nature" required maxlength="255"
                                        placeholder="Ex. Attestation employeur, Carte d’identité, Diplôme…"
                                        autocomplete="off"
                                        value="<?php echo isset($_POST['document_nature']) && $doc_form_err !== '' ? htmlspecialchars((string) $_POST['document_nature']) : ''; ?>">
                                </div>
                                <div class="er-documents__field">
                                    <span class="er-docs-modal__file-label">Fichier <span class="req">*</span></span>
                                    <div class="er-docs-modal__file-wrap">
                                        <input type="file" name="document_fichier" id="document_fichier" required
                                            accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"
                                            class="er-docs-modal__file-input-native">
                                        <label for="document_fichier" class="er-docs-modal__file-fake">
                                            <span class="er-docs-modal__file-fake-icon"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i></span>
                                            <span class="er-docs-modal__file-fake-text"><strong>Parcourir</strong> ou déposer sur la zone</span>
                                            <span class="er-docs-modal__file-fake-sub">PDF, JPEG, PNG, WebP · <?php echo (int) (EMPLOYE_DOCUMENT_UPLOAD_MAX_BYTES / (1024 * 1024)); ?> Mo max</span>
                                        </label>
                                    </div>
                                    <span class="er-documents__hint">Le fichier n’est pas envoyé tant que vous ne cliquez pas sur « Enregistrer ».</span>
                                </div>
                                <div class="er-documents__form-actions er-docs-modal__actions">
                                    <button type="button" class="er-documents__btn er-documents__btn--secondary" id="erDetailDocsModalCancel">Annuler</button>
                                    <button type="submit" class="er-documents__btn er-documents__btn--primary"><i class="fas fa-check" aria-hidden="true"></i> Enregistrer le document</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="erSanctionModal" class="er-sanction-modal<?php echo $sanction_modal_open ? ' is-open' : ''; ?>"
            role="dialog" aria-modal="true" aria-labelledby="erSanctionModalTitle"
            aria-hidden="<?php echo $sanction_modal_open ? 'false' : 'true'; ?>">
            <div class="er-sanction-modal__backdrop" id="erSanctionModalBackdrop" aria-hidden="true"></div>
            <div class="er-sanction-modal__dialog">
                <header class="er-sanction-modal__head">
                    <div class="er-sanction-modal__head-icon" aria-hidden="true"><i class="fas fa-gavel"></i></div>
                    <div class="er-sanction-modal__head-text">
                        <p class="er-sanction-modal__eyebrow">Discipline — fiche RH</p>
                        <h2 id="erSanctionModalTitle" class="er-sanction-modal__title">Nouvelle sanction ou mesure</h2>
                        <p class="er-sanction-modal__lede">Renseignez les faits constatés et la décision prise. Tous les champs marqués d’une astérisque sont obligatoires.</p>
                    </div>
                    <button type="button" class="er-sanction-modal__close" id="erSanctionModalClose" aria-label="Fermer">&times;</button>
                </header>
                <div class="er-sanction-modal__body">
                    <?php if ($sanction_form_err !== ''): ?>
                        <p class="er-sanction-modal__alert" role="alert"><?php echo htmlspecialchars($sanction_form_err); ?></p>
                    <?php endif; ?>
                    <form class="er-sanction-modal__form" method="post" action="details.php?id=<?php echo (int) $id; ?>&tab=san">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="ajouter_sanction" value="1">
                        <div class="er-sanction-modal__grid">
                            <div class="er-sanction-modal__field">
                                <label for="sanction_date_constat">Date du constat ou de la décision <span class="req">*</span></label>
                                <input type="date" name="sanction_date_constat" id="sanction_date_constat" required
                                    value="<?php
                                    $v = date('Y-m-d');
                                    if (!empty($_POST['sanction_date_constat']) && (($sanction_form_err !== '') || $sanction_modal_open)) {
                                        $v = (string) $_POST['sanction_date_constat'];
                                    }
                                    echo htmlspecialchars($v);
                                    ?>">
                            </div>
                            <div class="er-sanction-modal__field">
                                <label for="sanction_type">Type de mesure <span class="req">*</span></label>
                                <select name="sanction_type" id="sanction_type" required>
                                    <option value="" disabled<?php
                                    $sel = isset($_POST['sanction_type']) && (($sanction_form_err !== '') || $sanction_modal_open)
                                        ? (string) $_POST['sanction_type'] : '';
                                    echo $sel === '' ? ' selected' : '';
                                    ?>>Choisir un type</option>
                                    <?php
                                    foreach ($sanction_types_choices as $slug => $lib) {
                                        echo '<option value="' . htmlspecialchars($slug) . '"' . ($sel === $slug ? ' selected' : '') . '>'
                                            . htmlspecialchars($lib) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="er-sanction-modal__field er-sanction-modal__field--full">
                            <label for="sanction_motif">Motif — faits constatés <span class="req">*</span></label>
                            <textarea name="sanction_motif" id="sanction_motif" required rows="4" maxlength="10000" placeholder="Décrivez les faits, le contexte et les manquements constatés."><?php
                            echo isset($_POST['sanction_motif']) && ($sanction_form_err !== '' || $sanction_modal_open)
                                ? htmlspecialchars((string) $_POST['sanction_motif']) : '';
                            ?></textarea>
                        </div>
                        <div class="er-sanction-modal__field er-sanction-modal__field--full">
                            <label for="sanction_mesure">Mesure ou décision appliquée <span class="req">*</span></label>
                            <textarea name="sanction_mesure" id="sanction_mesure" required rows="4" maxlength="10000" placeholder="Ex. avertissement, durée de mise à pied, notification au salarié…"><?php
                            echo isset($_POST['sanction_mesure']) && ($sanction_form_err !== '' || $sanction_modal_open)
                                ? htmlspecialchars((string) $_POST['sanction_mesure']) : '';
                            ?></textarea>
                        </div>
                        <div class="er-sanction-modal__field er-sanction-modal__field--full">
                            <label for="sanction_commentaire">Commentaire interne (optionnel)</label>
                            <textarea name="sanction_commentaire" id="sanction_commentaire" rows="2" maxlength="5000" placeholder="Notes RH non visibles sur les exports éventuels…"><?php
                            echo isset($_POST['sanction_commentaire']) && ($sanction_form_err !== '' || $sanction_modal_open)
                                ? htmlspecialchars((string) $_POST['sanction_commentaire']) : '';
                            ?></textarea>
                        </div>
                        <div class="er-sanction-modal__actions">
                            <button type="button" class="er-sanction-modal__btn er-sanction-modal__btn--ghost" id="erSanctionModalCancel">Annuler</button>
                            <button type="submit" class="er-sanction-modal__btn er-sanction-modal__btn--primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="erAbsAuthModal" class="er-abs-auth-modal<?php echo $auth_modal_open ? ' is-open' : ''; ?>"
            role="dialog" aria-modal="true" aria-labelledby="erAbsAuthModalTitle"
            aria-hidden="<?php echo $auth_modal_open ? 'false' : 'true'; ?>">
            <div class="er-abs-auth-modal__backdrop" id="erAbsAuthModalBackdrop" aria-hidden="true"></div>
            <div class="er-abs-auth-modal__dialog">
                <header class="er-abs-auth-modal__head">
                    <div class="er-abs-auth-modal__head-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></div>
                    <div class="er-abs-auth-modal__head-text">
                        <p class="er-abs-auth-modal__eyebrow">Absences — autorisation</p>
                        <h2 id="erAbsAuthModalTitle" class="er-abs-auth-modal__title">Nouvelle autorisation d’absence</h2>
                        <p class="er-abs-auth-modal__lede">Définissez la période couverte et l’objet de l’autorisation. La date de fin est incluse.</p>
                    </div>
                    <button type="button" class="er-abs-auth-modal__close" id="erAbsAuthModalClose" aria-label="Fermer">&times;</button>
                </header>
                <div class="er-abs-auth-modal__body">
                    <?php if ($auth_form_err !== ''): ?>
                        <p class="er-abs-auth-modal__alert" role="alert"><?php echo htmlspecialchars($auth_form_err); ?></p>
                    <?php endif; ?>
                    <form class="er-abs-auth-modal__form" method="post" action="details.php?id=<?php echo (int) $id; ?>&tab=abs&abs_sub=auth">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="ajouter_autorisation_absence" value="1">
                        <div class="er-abs-auth-modal__grid">
                            <div class="er-abs-auth-modal__field">
                                <label for="auth_date_debut">Date de début <span class="req">*</span></label>
                                <input type="date" name="auth_date_debut" id="auth_date_debut" required
                                    value="<?php
                                    $ad1 = date('Y-m-d');
                                    if (!empty($_POST['auth_date_debut']) && (($auth_form_err !== '') || $auth_modal_open)) {
                                        $ad1 = (string) $_POST['auth_date_debut'];
                                    }
                                    echo htmlspecialchars($ad1);
                                    ?>">
                            </div>
                            <div class="er-abs-auth-modal__field">
                                <label for="auth_date_fin">Date de fin (incluse) <span class="req">*</span></label>
                                <input type="date" name="auth_date_fin" id="auth_date_fin" required
                                    value="<?php
                                    $ad2 = date('Y-m-d');
                                    if (!empty($_POST['auth_date_fin']) && (($auth_form_err !== '') || $auth_modal_open)) {
                                        $ad2 = (string) $_POST['auth_date_fin'];
                                    }
                                    echo htmlspecialchars($ad2);
                                    ?>">
                            </div>
                        </div>
                        <div class="er-abs-auth-modal__field er-abs-auth-modal__field--full">
                            <label for="auth_motif">Motif / objet de l’autorisation <span class="req">*</span></label>
                            <textarea name="auth_motif" id="auth_motif" required rows="4" maxlength="10000" placeholder="Ex. Congés annuels, RTT, absence autorisée pour rendez-vous médical…"><?php
                            echo !empty($_POST['auth_motif']) && (($auth_form_err !== '') || $auth_modal_open)
                                ? htmlspecialchars((string) $_POST['auth_motif']) : '';
                            ?></textarea>
                        </div>
                        <div class="er-abs-auth-modal__field er-abs-auth-modal__field--full">
                            <label for="auth_commentaire">Commentaire interne (optionnel)</label>
                            <textarea name="auth_commentaire" id="auth_commentaire" rows="2" maxlength="5000" placeholder="Précisions pour le dossier RH…"><?php
                            echo !empty($_POST['auth_commentaire']) && (($auth_form_err !== '') || $auth_modal_open)
                                ? htmlspecialchars((string) $_POST['auth_commentaire']) : '';
                            ?></textarea>
                        </div>
                        <div class="er-abs-auth-modal__actions">
                            <button type="button" class="er-abs-auth-modal__btn er-abs-auth-modal__btn--ghost" id="erAbsAuthModalCancel">Annuler</button>
                            <button type="submit" class="er-abs-auth-modal__btn er-abs-auth-modal__btn--primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer l’autorisation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="erPretModal" class="er-pret-modal<?php echo $pret_modal_open ? ' is-open' : ''; ?>"
            role="dialog" aria-modal="true" aria-labelledby="erPretModalTitle"
            aria-hidden="<?php echo $pret_modal_open ? 'false' : 'true'; ?>">
            <div class="er-pret-modal__backdrop" id="erPretModalBackdrop" aria-hidden="true"></div>
            <div class="er-pret-modal__dialog">
                <header class="er-pret-modal__head">
                    <div class="er-pret-modal__head-icon" aria-hidden="true"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div class="er-pret-modal__head-text">
                        <p class="er-pret-modal__eyebrow">RH — finance interne</p>
                        <h2 id="erPretModalTitle" class="er-pret-modal__title">Nouveau prêt</h2>
                        <p class="er-pret-modal__lede">Saisissez le montant, les dates et l’objet du prêt. Les montants peuvent utiliser la virgule (ex. 1250,50).</p>
                    </div>
                    <button type="button" class="er-pret-modal__close" id="erPretModalClose" aria-label="Fermer">&times;</button>
                </header>
                <div class="er-pret-modal__body">
                    <?php if ($pret_form_err !== ''): ?>
                        <p class="er-pret-modal__alert" role="alert"><?php echo htmlspecialchars($pret_form_err); ?></p>
                    <?php endif; ?>
                    <form class="er-pret-modal__form" method="post" action="details.php?id=<?php echo (int) $id; ?>&tab=pret">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="ajouter_pret" value="1">
                        <div class="er-pret-modal__field er-pret-modal__field--highlight">
                            <label for="pret_montant">Montant total <span class="req">*</span> <span class="er-pret-modal__unit">(FCFA)</span></label>
                            <input type="text" name="pret_montant" id="pret_montant" required inputmode="decimal" autocomplete="off"
                                placeholder="Ex. 500 000 ou 500000,00"
                                value="<?php
                                echo !empty($_POST['pret_montant']) && (($pret_form_err !== '') || $pret_modal_open)
                                    ? htmlspecialchars((string) $_POST['pret_montant']) : '';
                                ?>">
                        </div>
                        <div class="er-pret-modal__grid">
                            <div class="er-pret-modal__field">
                                <label for="pret_date_octroi">Date d’octroi <span class="req">*</span></label>
                                <input type="date" name="pret_date_octroi" id="pret_date_octroi" required
                                    value="<?php
                                    $pret_octroi_val = date('Y-m-d');
                                    if (!empty($_POST['pret_date_octroi']) && (($pret_form_err !== '') || $pret_modal_open)) {
                                        $pret_octroi_val = (string) $_POST['pret_date_octroi'];
                                    }
                                    echo htmlspecialchars($pret_octroi_val);
                                    ?>">
                            </div>
                            <div class="er-pret-modal__field">
                                <label for="pret_date_fin_prevue">Fin de remboursement prévue</label>
                                <input type="date" name="pret_date_fin_prevue" id="pret_date_fin_prevue"
                                    value="<?php
                                    echo !empty($_POST['pret_date_fin_prevue']) && (($pret_form_err !== '') || $pret_modal_open)
                                        ? htmlspecialchars((string) $_POST['pret_date_fin_prevue']) : '';
                                    ?>">
                            </div>
                        </div>
                        <div class="er-pret-modal__field">
                            <label for="pret_mensualite">Mensualité ou versement prévu <span class="er-pret-modal__optional">(optionnel)</span></label>
                            <input type="text" name="pret_mensualite" id="pret_mensualite" inputmode="decimal" autocomplete="off"
                                placeholder="FCFA — laisser vide si non applicable"
                                value="<?php
                                echo isset($_POST['pret_mensualite']) && (($pret_form_err !== '') || $pret_modal_open)
                                    ? htmlspecialchars((string) $_POST['pret_mensualite']) : '';
                                ?>">
                        </div>
                        <div class="er-pret-modal__field">
                            <label for="pret_statut">Statut</label>
                            <select name="pret_statut" id="pret_statut">
                                <?php
                                $psel = isset($_POST['pret_statut']) && (($pret_form_err !== '') || $pret_modal_open)
                                    ? (string) $_POST['pret_statut'] : 'en_cours';
                                foreach ($pret_statuts_choices as $slug => $lib) {
                                    echo '<option value="' . htmlspecialchars($slug) . '"' . ($psel === $slug ? ' selected' : '') . '>'
                                        . htmlspecialchars($lib) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="er-pret-modal__field er-pret-modal__field--full">
                            <label for="pret_motif">Objet / motif du prêt <span class="req">*</span></label>
                            <textarea name="pret_motif" id="pret_motif" required rows="3" maxlength="10000" placeholder="Ex. Avance sur salaire, prêt personnel agréé par la direction…"><?php
                            echo !empty($_POST['pret_motif']) && (($pret_form_err !== '') || $pret_modal_open)
                                ? htmlspecialchars((string) $_POST['pret_motif']) : '';
                            ?></textarea>
                        </div>
                        <div class="er-pret-modal__field er-pret-modal__field--full">
                            <label for="pret_commentaire">Commentaire interne</label>
                            <textarea name="pret_commentaire" id="pret_commentaire" rows="2" maxlength="5000" placeholder="Conditions, références dossier…"><?php
                            echo isset($_POST['pret_commentaire']) && (($pret_form_err !== '') || $pret_modal_open)
                                ? htmlspecialchars((string) $_POST['pret_commentaire']) : '';
                            ?></textarea>
                        </div>
                        <div class="er-pret-modal__actions">
                            <button type="button" class="er-pret-modal__btn er-pret-modal__btn--ghost" id="erPretModalCancel">Annuler</button>
                            <button type="submit" class="er-pret-modal__btn er-pret-modal__btn--primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer le prêt</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="erPretRembModal" class="er-pret-remb-modal<?php echo $pret_remb_modal_open ? ' is-open' : ''; ?>"
            role="dialog" aria-modal="true" aria-labelledby="erPretRembModalTitle"
            aria-hidden="<?php echo $pret_remb_modal_open ? 'false' : 'true'; ?>">
            <div class="er-pret-remb-modal__backdrop" id="erPretRembModalBackdrop" aria-hidden="true"></div>
            <div class="er-pret-remb-modal__dialog">
                <header class="er-pret-remb-modal__head">
                    <div class="er-pret-remb-modal__head-icon" aria-hidden="true"><i class="fas fa-money-bill-transfer"></i></div>
                    <div class="er-pret-remb-modal__head-text">
                        <p class="er-pret-remb-modal__eyebrow">Versement sur prêt</p>
                        <h2 id="erPretRembModalTitle" class="er-pret-remb-modal__title">Enregistrer un remboursement</h2>
                        <p class="er-pret-remb-modal__lede" id="erPretRembResteHint"></p>
                    </div>
                    <button type="button" class="er-pret-remb-modal__close" id="erPretRembModalClose" aria-label="Fermer">&times;</button>
                </header>
                <div class="er-pret-remb-modal__body">
                    <?php if ($pret_remb_form_err !== ''): ?>
                        <p class="er-pret-remb-modal__alert" role="alert"><?php echo htmlspecialchars($pret_remb_form_err); ?></p>
                    <?php endif; ?>
                    <?php
                    if ($pret_remb_modal_open && !empty($_POST['pret_remboursement_pret_id'])) {
                        $hint_pret = employe_pret_get_by_id_for_employe((int) $_POST['pret_remboursement_pret_id'], $id);
                        if ($hint_pret) {
                            $hm = (float) ($hint_pret['montant'] ?? 0);
                            $hv = (float) ($hint_pret['montant_verse'] ?? 0);
                            $hr = max(0, round($hm - $hv, 2));
                            echo '<span id="erPretRembHintBoot" class="er-pret-remb-hint-boot" hidden data-reste-fr="'
                                . htmlspecialchars(number_format($hr, 2, ',', ' ')) . '"></span>';
                        }
                    }
                    ?>
                    <form class="er-pret-remb-modal__form" method="post" action="details.php?id=<?php echo (int) $id; ?>&tab=pret">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="ajouter_remboursement_pret" value="1">
                        <input type="hidden" name="pret_remboursement_pret_id" id="pret_remb_pret_id" value="<?php
                            echo $pret_remb_modal_open && !empty($_POST['pret_remboursement_pret_id'])
                                ? (int) $_POST['pret_remboursement_pret_id'] : '';
                        ?>">
                        <div class="er-pret-remb-modal__field er-pret-remb-modal__field--highlight">
                            <label for="pret_remb_montant">Montant du versement <span class="req">*</span> <span class="er-pret-remb-modal__unit">(FCFA)</span></label>
                            <input type="text" name="pret_remb_montant" id="pret_remb_montant" required inputmode="decimal" autocomplete="off"
                                placeholder="≤ reste à payer"
                                value="<?php
                                echo isset($_POST['pret_remb_montant']) && (($pret_remb_form_err !== '') || $pret_remb_modal_open)
                                    ? htmlspecialchars((string) $_POST['pret_remb_montant']) : '';
                                ?>">
                        </div>
                        <div class="er-pret-remb-modal__field">
                            <label for="pret_remb_date">Date du versement <span class="req">*</span></label>
                            <input type="date" name="pret_remb_date" id="pret_remb_date" required
                                value="<?php
                                $trd = date('Y-m-d');
                                if (!empty($_POST['pret_remb_date']) && (($pret_remb_form_err !== '') || $pret_remb_modal_open)) {
                                    $trd = (string) $_POST['pret_remb_date'];
                                }
                                echo htmlspecialchars($trd);
                                ?>">
                        </div>
                        <div class="er-pret-remb-modal__field er-pret-remb-modal__field--full">
                            <label for="pret_remb_commentaire">Commentaire (optionnel)</label>
                            <textarea name="pret_remb_commentaire" id="pret_remb_commentaire" rows="2" maxlength="5000" placeholder="Bulletin de paie n°, mode de paiement…"><?php
                            echo isset($_POST['pret_remb_commentaire']) && (($pret_remb_form_err !== '') || $pret_remb_modal_open)
                                ? htmlspecialchars((string) $_POST['pret_remb_commentaire']) : '';
                            ?></textarea>
                        </div>
                        <div class="er-pret-remb-modal__actions">
                            <button type="button" class="er-pret-remb-modal__btn er-pret-remb-modal__btn--ghost" id="erPretRembModalCancel">Annuler</button>
                            <button type="submit" class="er-pret-remb-modal__btn er-pret-remb-modal__btn--primary"><i class="fas fa-check" aria-hidden="true"></i> Valider le versement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="erPretDetailModal" class="er-pret-detail-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="erPretDetailTitle">
            <div class="er-pret-detail-modal__backdrop" id="erPretDetailBackdrop" aria-hidden="true"></div>
            <div class="er-pret-detail-modal__frame">
                <header class="er-pret-detail-modal__toolbar">
                    <div class="er-pret-detail-modal__toolbar-text">
                        <p class="er-pret-detail-modal__eyebrow">Fiche prêt</p>
                        <h2 id="erPretDetailTitle" class="er-pret-detail-modal__title">Détail du prêt</h2>
                    </div>
                    <button type="button" class="er-pret-detail-modal__close-x" id="erPretDetailClose" aria-label="Fermer la fenêtre">&times;</button>
                </header>
                <div class="er-pret-detail-modal__scroll">
                    <div id="erPretDetailContent" class="er-pret-detail-modal__content"></div>
                </div>
            </div>
        </div>

        <div id="erCongeModal" class="er-conge-modal<?php echo $conge_modal_open ? ' is-open' : ''; ?>"
            role="dialog" aria-modal="true" aria-labelledby="erCongeModalTitle"
            aria-hidden="<?php echo $conge_modal_open ? 'false' : 'true'; ?>">
            <div class="er-conge-modal__backdrop" id="erCongeModalBackdrop" aria-hidden="true"></div>
            <div class="er-conge-modal__dialog">
                <header class="er-conge-modal__head">
                    <div class="er-conge-modal__head-text">
                        <p class="er-conge-modal__eyebrow"><i class="fas fa-umbrella-beach" aria-hidden="true"></i> Congés</p>
                        <h2 id="erCongeModalTitle" class="er-conge-modal__title">Ajouter un congé</h2>
                        <p class="er-conge-modal__lede">Saisissez le nombre de jours et le mois de prise. Le solde annuel est recalculé en direct.</p>
                    </div>
                    <button type="button" class="er-conge-modal__close" id="erCongeModalClose" aria-label="Fermer">&times;</button>
                </header>
                <div class="er-conge-modal__body">
                    <?php if ($conge_form_err !== ''): ?>
                        <p class="er-conge-modal__alert" role="alert"><?php echo htmlspecialchars($conge_form_err); ?></p>
                    <?php endif; ?>
                    <form class="er-conge-modal__form" method="post" action="details.php?id=<?php echo (int) $id; ?>&tab=conges">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="ajouter_conge" value="1">
                        <input type="hidden" id="conges_quota_global_ref" value="<?php echo (int) $conges_quota_global; ?>">
                        <input type="hidden" id="conges_totaux_annee_json" value="<?php echo htmlspecialchars(json_encode($conges_totaux_par_annee, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="er-conge-modal__grid">
                            <div class="er-conge-modal__field">
                                <label for="conge_nb_jours">Nombre de jours <span class="req">*</span></label>
                                <input type="number" name="conge_nb_jours" id="conge_nb_jours" min="1" max="365" step="1" required value="<?php echo htmlspecialchars(isset($_POST['conge_nb_jours']) ? (string) $_POST['conge_nb_jours'] : '1'); ?>">
                            </div>
                            <div class="er-conge-modal__field">
                                <label for="conge_mois">Mois de prise <span class="req">*</span></label>
                                <input type="month" name="conge_mois" id="conge_mois" required value="<?php echo htmlspecialchars(isset($_POST['conge_mois']) ? (string) $_POST['conge_mois'] : date('Y-m')); ?>">
                            </div>
                            <div class="er-conge-modal__field">
                                <label>Quota restant après saisie</label>
                                <div class="er-bp-salaire-readonly" id="conge_restant_preview">0 jour(s)</div>
                            </div>
                            <div class="er-conge-modal__field er-conge-modal__field--full">
                                <label for="conge_notes">Notes (optionnel)</label>
                                <textarea name="conge_notes" id="conge_notes" rows="3" maxlength="1000" placeholder="Observation, raison, référence interne..."><?php echo isset($_POST['conge_notes']) ? htmlspecialchars((string) $_POST['conge_notes']) : ''; ?></textarea>
                            </div>
                        </div>
                        <div class="er-conge-modal__actions">
                            <button type="button" class="er-conge-modal__btn er-conge-modal__btn--ghost" id="erCongeModalCancel">Annuler</button>
                            <button type="submit" class="er-conge-modal__btn er-conge-modal__btn--primary"><i class="fas fa-check"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="/js/admin-employes-detail-documents.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/admin-employes-detail-sanctions.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/admin-employes-detail-abs-autorisations.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/admin-employes-detail-prets.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/admin-employes-detail-conges.js<?php echo asset_version_query(); ?>" defer></script>
    <script src="/js/admin-employes-detail-bulletin-paie.js<?php echo asset_version_query(); ?>" defer></script>
</body>
</html>
