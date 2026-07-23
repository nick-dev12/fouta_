<?php
/**
 * Configuration entrepôt — hiérarchie CRUD par onglets (Niveau → Zone → Rayon → Étagère → Barre → Position).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_gestion_stock_etendue()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_entrepot_emplacement.php';
require_once __DIR__ . '/../../models/model_entrepot_referentiel.php';
require_once __DIR__ . '/../../models/model_entrepot_structure_champs.php';
require_once __DIR__ . '/../../models/model_entrepot_hierarchie.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/entrepot_barcode_service.php';

entrepot_structure_champs_ensure_table();
entrepot_structure_champ_ensure_hierarchie_schema();
entrepot_champ_element_ensure_table();
entrepot_barre_ensure_champ_element_schema();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';
$numero_niveau_actif = isset($_GET['niveau']) ? (int) $_GET['niveau'] : 0;
$cascade_modal = isset($_GET['modal']) ? (string) $_GET['modal'] : '';
$cascade_etage = isset($_GET['c_etage']) ? (int) $_GET['c_etage'] : 0;
$cascade_zone = isset($_GET['c_zone']) ? (int) $_GET['c_zone'] : 0;
$cascade_rayon = isset($_GET['c_rayon']) ? (int) $_GET['c_rayon'] : 0;
$cascade_etagere = isset($_GET['c_etagere']) ? (int) $_GET['c_etagere'] : 0;

if (isset($_SESSION['success_message_emplacement_entrepot'])) {
    $success_message = (string) $_SESSION['success_message_emplacement_entrepot'];
    unset($_SESSION['success_message_emplacement_entrepot']);
}
if (isset($_SESSION['error_message_emplacement_entrepot'])) {
    $error_message = (string) $_SESSION['error_message_emplacement_entrepot'];
    unset($_SESSION['error_message_emplacement_entrepot']);
}

function ee_redirect_niveau($numero, $modal = '') {
    $url = 'emplacement-entrepot.php';
    $q = [];
    if ($numero > 0) {
        $q['niveau'] = $numero;
    }
    if ($modal !== '') {
        $q['modal'] = $modal;
    }
    if ($q !== []) {
        $url .= '?' . http_build_query($q);
    }
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $redirect_n = isset($_POST['numero_niveau']) ? (int) $_POST['numero_niveau'] : $numero_niveau_actif;

    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!entrepot_hierarchie_schema_ok()) {
        $error_message = 'Migration hiérarchie requise — exécutez php migrations/run_migrate_entrepot_hierarchie_crud.php';
    } elseif (isset($_POST['ajouter_niveau'])) {
        $res = entrepot_niveau_ajouter(
            isset($_POST['nom_niveau']) ? (string) $_POST['nom_niveau'] : '',
            isset($_POST['code_abrege']) ? (string) $_POST['code_abrege'] : ''
        );
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            ee_redirect_niveau((int) ($res['numero_etage'] ?? 0));
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['ajouter_zone'])) {
        $res = entrepot_zone_ajouter(
            (int) ($_POST['etage_id'] ?? 0),
            isset($_POST['nom']) ? (string) $_POST['nom'] : '',
            (int) ($_POST['numero'] ?? 1)
        );
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            ee_redirect_niveau($redirect_n);
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['ajouter_rayon'])) {
        $res = entrepot_rayon_ajouter(
            (int) ($_POST['etage_id'] ?? 0),
            (int) ($_POST['zone_id'] ?? 0),
            isset($_POST['nom']) ? (string) $_POST['nom'] : '',
            (int) ($_POST['numero'] ?? 1)
        );
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            ee_redirect_niveau($redirect_n);
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['ajouter_etagere'])) {
        $res = entrepot_etagere_ajouter(
            (int) ($_POST['etage_id'] ?? 0),
            (int) ($_POST['rayon_id'] ?? 0),
            isset($_POST['nom']) ? (string) $_POST['nom'] : '',
            (int) ($_POST['numero'] ?? 1)
        );
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            ee_redirect_niveau($redirect_n);
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['ajouter_barre'])) {
        $res = entrepot_barre_ajouter(
            (int) ($_POST['etage_id'] ?? 0),
            (int) ($_POST['rayon_id'] ?? 0),
            (int) ($_POST['etagere_id'] ?? 0),
            isset($_POST['nom']) ? (string) $_POST['nom'] : '',
            (int) ($_POST['numero'] ?? 1)
        );
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            ee_redirect_niveau($redirect_n);
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['ajouter_position'])) {
        $res = entrepot_position_ajouter(
            (int) ($_POST['barre_id'] ?? 0),
            isset($_POST['nom']) ? (string) $_POST['nom'] : '',
            (int) ($_POST['numero'] ?? 1)
        );
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            ee_redirect_niveau($redirect_n);
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['modifier_entite'])) {
        $res = entrepot_hierarchie_modifier_entite(
            isset($_POST['entite_table']) ? (string) $_POST['entite_table'] : '',
            (int) ($_POST['entite_id'] ?? 0),
            (int) ($_POST['etage_id'] ?? 0),
            isset($_POST['nom']) ? (string) $_POST['nom'] : '',
            (int) ($_POST['numero'] ?? 1)
        );
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            ee_redirect_niveau($redirect_n);
        }
        $error_message = $res['message'];
    } elseif (!empty($_POST['supprimer_niveau'])) {
        if (empty($_POST['confirm_suppression_hierarchie'])) {
            $error_message = 'Veuillez lire l’impact et confirmer la suppression du niveau.';
        } else {
            $res = entrepot_hierarchie_supprimer_niveau((int) ($_POST['numero_etage'] ?? 0));
            if ($res['success']) {
                $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
                ee_redirect_niveau(0);
            }
            $error_message = $res['message'];
        }
    } elseif (!empty($_POST['supprimer_entite'])) {
        if (empty($_POST['confirm_suppression_hierarchie'])) {
            $error_message = 'Veuillez lire l’impact et confirmer la suppression.';
        } else {
            $res = entrepot_hierarchie_supprimer_entite(
                isset($_POST['entite_table']) ? (string) $_POST['entite_table'] : '',
                (int) ($_POST['entite_id'] ?? 0),
                (int) ($_POST['etage_id'] ?? 0)
            );
            if ($res['success']) {
                $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
                ee_redirect_niveau($redirect_n);
            }
            $error_message = $res['message'];
        }
    } elseif (isset($_POST['ajouter_champ_structure'])) {
        $niv = isset($_POST['niveau_hierarchie']) ? (string) $_POST['niveau_hierarchie'] : '';
        $res = entrepot_structure_champ_ajouter(
            isset($_POST['label_champ']) ? (string) $_POST['label_champ'] : '',
            'fa-cube',
            (int) ($_POST['max_champ'] ?? 50),
            (int) ($_POST['max_champ'] ?? 50),
            !empty($_POST['lie_barre']),
            $niv !== '' ? $niv : null
        );
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            ee_redirect_niveau($redirect_n);
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['supprimer_champ_structure'])) {
        if (empty($_POST['confirm_suppression_champ'])) {
            $error_message = 'Veuillez lire l’impact et cocher la case de confirmation avant de supprimer.';
        } else {
            $res = entrepot_structure_champ_supprimer((int) ($_POST['champ_id'] ?? 0));
            if ($res['success']) {
                $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
                ee_redirect_niveau($redirect_n);
            }
            $error_message = $res['message'];
        }
    }
}

$niveaux = entrepot_hierarchie_liste_niveaux();
if ($numero_niveau_actif <= 0 && $niveaux !== []) {
    $numero_niveau_actif = (int) ($niveaux[0]['numero_etage'] ?? 1);
}

$arbre_actif = null;
$etage_actif = null;
if ($numero_niveau_actif > 0) {
    $arbre_actif = entrepot_hierarchie_liste_pour_niveau($numero_niveau_actif);
    $etage_actif = $arbre_actif['etage'] ?? entrepot_get_etage_ref_by_numero($numero_niveau_actif);
}

$etage_id_actif = is_array($etage_actif) ? (int) ($etage_actif['id'] ?? 0) : 0;
$cascade_etage_effectif = $cascade_etage > 0 ? $cascade_etage : $etage_id_actif;
$cascade_lists = entrepot_hierarchie_liste_pour_cascade($cascade_etage_effectif, $cascade_zone, $cascade_rayon, $cascade_etagere);
$all_niveaux_select = entrepot_hierarchie_liste_pour_cascade(0)['niveaux'];

$structure_champs = entrepot_structure_champs_pour_formulaire();
$structure_champs_tous = entrepot_structure_champs_list();
$peut_supprimer_champ = count($structure_champs) > 1;
$hierarchie_actifs = entrepot_hierarchie_niveaux_actifs();
$champs_impact_suppression = [];
foreach ($structure_champs_tous as $sc) {
    $cid = (int) ($sc['id'] ?? 0);
    if ($cid <= 0) {
        continue;
    }
    $imp = entrepot_structure_champ_impact_suppression($cid);
    if ($imp !== null) {
        $champs_impact_suppression[$cid] = $imp;
    }
}
$hierarchie_toolbar_modals = [
    'zone' => 'modalZone',
    'rayon' => 'modalRayon',
    'etagere' => 'modalEtagere',
    'barre' => 'modalBarre',
    'position' => 'modalPosition',
];
$niveaux_max_atteint = count($niveaux) >= (int) ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX;
$prochain_numero_niveau = 1;
foreach ($niveaux as $nv) {
    $n = (int) ($nv['numero_etage'] ?? 0);
    if ($n >= $prochain_numero_niveau) {
        $prochain_numero_niveau = $n + 1;
    }
}

$niveaux_hierarchie_options = [
    'zone' => 'Zone',
    'rayon' => 'Rayon',
    'etagere' => 'Étagère',
    'barre' => 'Barre',
    'position' => 'Position',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emplacement entrepôt — Paramètres</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-emplacement-entrepot.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/entrepot-barre-etiquette.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-parametres-admin page-emplacement-entrepot">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page ee-wrap">
        <header class="ee-hero" role="banner">
            <a class="ee-hero__back" href="../parametres.php"><i class="fas fa-arrow-left"></i> Paramètres</a>
            <div class="ee-hero__row">
                <div class="ee-hero__icon"><i class="fas fa-warehouse"></i></div>
                <div class="ee-hero__text">
                    <h1 class="ee-hero__title">Emplacement entrepôt</h1>
                    <p class="ee-hero__lead">
                        Hiérarchie <strong>Niveau → Zone → Rayon → Étagère → Barre → Position</strong>.
                        Gérez la structure par onglets et assignez les emplacements aux produits.
                    </p>
                </div>
            </div>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="message success ee-flash"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error ee-flash"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!entrepot_hierarchie_schema_ok()): ?>
            <div class="message error ee-flash">
                <i class="fas fa-database"></i>
                Exécutez&nbsp;: <code>php migrations/run_migrate_entrepot_hierarchie_crud.php</code>
            </div>
        <?php endif; ?>

        <div class="ee-toolbar ee-toolbar--hierarchie">
            <p class="ee-toolbar__meta">
                <strong><?php echo count($niveaux); ?></strong> niveau(x) ·
                <strong><?php echo count($structure_champs); ?></strong> champ(s) structurel(s)
            </p>
            <div class="ee-toolbar__actions ee-toolbar__actions--wrap">
                <button type="button" class="ee-btn-secondary" onclick="openModal('modalAjouterChamp')"><i class="fas fa-plus-circle"></i> Ajouter un champ</button>
                <button type="button" class="ee-btn-secondary ee-btn-secondary--danger" onclick="openModal('modalSupprimerChamp')" <?php echo !$peut_supprimer_champ ? 'disabled' : ''; ?>><i class="fas fa-minus-circle"></i> Supprimer un champ</button>
                <?php foreach ($hierarchie_actifs as $niv_key => $niv_meta):
                    $modal_id = $hierarchie_toolbar_modals[$niv_key] ?? '';
                    if ($modal_id === '') {
                        continue;
                    }
                    $btn_icon = htmlspecialchars((string) ($niv_meta['icon'] ?? 'fa-cube'), ENT_QUOTES, 'UTF-8');
                    $btn_label = htmlspecialchars((string) ($niv_meta['label'] ?? ucfirst($niv_key)), ENT_QUOTES, 'UTF-8');
                ?>
                <button type="button" class="ee-btn-secondary" onclick="openModal('<?php echo htmlspecialchars($modal_id, ENT_QUOTES, 'UTF-8'); ?>')"><i class="fas <?php echo $btn_icon; ?>"></i> <?php echo $btn_label; ?></button>
                <?php endforeach; ?>
                <button type="button" class="ee-btn-primary" onclick="openModal('modalNiveau')" <?php echo $niveaux_max_atteint ? 'disabled' : ''; ?>><i class="fas fa-plus"></i> Ajouter un niveau</button>
            </div>
        </div>

        <?php if ($niveaux === []): ?>
            <div class="ee-empty">
                <div class="ee-empty__icon"><i class="fas fa-map"></i></div>
                <h3>Aucun niveau</h3>
                <p>Créez un premier niveau pour démarrer la cartographie de l’entrepôt.</p>
            </div>
        <?php else: ?>
            <nav class="ee-tabs-niveaux" aria-label="Niveaux entrepôt">
                <?php foreach ($niveaux as $nv):
                    $num = (int) ($nv['numero_etage'] ?? 0);
                    $active = $num === $numero_niveau_actif;
                    $ab = htmlspecialchars((string) ($nv['code_abrege'] ?? $nv['code'] ?? ''), ENT_QUOTES, 'UTF-8');
                ?>
                <a href="emplacement-entrepot.php?niveau=<?php echo $num; ?>" class="ee-tab-niveau<?php echo $active ? ' is-active' : ''; ?>">
                    <span class="ee-tab-niveau__nom"><?php echo htmlspecialchars((string) ($nv['nom'] ?? 'Niveau'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="ee-tab-niveau__code"><?php echo $ab !== '' ? $ab : ('E' . $num); ?></span>
                </a>
                <?php endforeach; ?>
            </nav>

            <?php if (is_array($etage_actif)): ?>
            <div class="ee-card ee-card--hierarchie">
                <div class="ee-card__head ee-card__head--hierarchie">
                    <div>
                        <h2><i class="fas fa-sitemap"></i> <?php echo htmlspecialchars((string) $etage_actif['nom'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="ee-card__sub">Code abrégé étiquettes&nbsp;: <code><?php echo htmlspecialchars((string) ($etage_actif['code_abrege'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></p>
                    </div>
                    <?php
                    $impact_supprimer_niveau = entrepot_hierarchie_impact_suppression_niveau(
                        (int) $numero_niveau_actif,
                        (string) ($etage_actif['nom'] ?? ''),
                        (int) $etage_id_actif
                    );
                    $impact_supprimer_niveau_json = htmlspecialchars(
                        json_encode($impact_supprimer_niveau ?: [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                    <button type="button"
                        class="ee-btn-delete"
                        data-ee-delete-impact="<?php echo $impact_supprimer_niveau_json; ?>"
                        data-ee-delete-csrf="<?php echo htmlspecialchars((string) $_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-ee-delete-niveau-num="<?php echo (int) $numero_niveau_actif; ?>"
                        onclick="eeOpenDeleteHierarchie(this);">
                        <i class="fas fa-trash-can"></i> Supprimer le niveau
                    </button>
                </div>
                <div class="ee-card__body">
                    <?php
                    $arbre = $arbre_actif;
                    $numero_niveau = $numero_niveau_actif;
                    $etage_id = $etage_id_actif;
                    include __DIR__ . '/partials/entrepot-hierarchie-arbre.php';
                    ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        include __DIR__ . '/partials/entrepot-modals-hierarchie.php';
        ?>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="/js/admin-emplacement-referentiel.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/admin-emplacement-entrepot.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/admin-emplacement-entrepot-hierarchie.js<?php echo asset_version_query(); ?>"></script>
    <script>window.EE_CHAMPS_IMPACT = <?php echo json_encode($champs_impact_suppression, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <?php if ($cascade_modal !== ''): ?>
    <script>document.addEventListener('DOMContentLoaded', function () { openModal('<?php echo htmlspecialchars($cascade_modal, ENT_QUOTES, 'UTF-8'); ?>'); });</script>
    <?php endif; ?>
</body>
</html>
