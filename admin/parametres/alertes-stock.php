<?php
/**
 * Configuration des seuils d'alerte stock (niveaux standard / moyen / haut).
 * Périmètre par catégorie et sous-catégorie.
 * Réservé aux administrateurs complets.
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

require_once __DIR__ . '/../../models/model_stock_alertes.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message_alertes_stock'])) {
    $success_message = (string) $_SESSION['success_message_alertes_stock'];
    unset($_SESSION['success_message_alertes_stock']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!stock_alertes_tables_ok()) {
        $error_message = 'Table stock_alertes_regles absente — exécutez migrations/run_create_stock_alertes.php';
    } elseif (!stock_alertes_scope_tables_ok()) {
        $error_message = 'Migration catégories absente — exécutez migrations/run_migrate_stock_alertes_par_categorie.php';
    } elseif (isset($_POST['supprimer_alerte'])) {
        $sid = isset($_POST['regle_id']) ? (int) $_POST['regle_id'] : 0;
        $redirect_niveau = isset($_POST['niveau_onglet']) ? (string) $_POST['niveau_onglet'] : 'standard';
        if ($sid > 0 && stock_alertes_supprimer_regle($sid)) {
            $_SESSION['success_message_alertes_stock'] = 'Seuil supprimé.';
            $loc = 'alertes-stock.php';
            if (in_array($redirect_niveau, ['standard', 'moyen', 'haut'], true)) {
                $loc .= '?niveau=' . urlencode($redirect_niveau);
            }
            header('Location: ' . $loc);
            exit;
        }
        $error_message = 'Impossible de supprimer ce seuil.';
    } elseif (isset($_POST['enregistrer_alerte'])) {
        $niveau = isset($_POST['niveau']) ? (string) $_POST['niveau'] : '';
        $seuil = isset($_POST['seuil']) ? (int) $_POST['seuil'] : -1;
        $categorie_ids = isset($_POST['categories']) && is_array($_POST['categories'])
            ? array_map('intval', $_POST['categories']) : [];
        $sous_categorie_ids = isset($_POST['sous_categories']) && is_array($_POST['sous_categories'])
            ? array_map('intval', $_POST['sous_categories']) : [];
        $res = stock_alertes_enregistrer_regle($niveau, $seuil, $categorie_ids, $sous_categorie_ids);
        if ($res['success']) {
            $_SESSION['success_message_alertes_stock'] = $res['message'];
            header('Location: alertes-stock.php?niveau=' . urlencode($niveau));
            exit;
        }
        $error_message = $res['message'];
    }
}

$regles = stock_alertes_get_all_regles();
$tables_ok = stock_alertes_tables_ok();
$scope_ok = stock_alertes_scope_tables_ok();
$nb_regles = count($regles);

$niveaux_onglets = [
    'standard' => [
        'label' => 'Standard',
        'libelle' => 'Niveau standard',
        'icon' => 'fa-circle-info',
        'pill' => 'std',
    ],
    'moyen' => [
        'label' => 'Moyen',
        'libelle' => 'Niveau moyen',
        'icon' => 'fa-triangle-exclamation',
        'pill' => 'mid',
    ],
    'haut' => [
        'label' => 'Haut',
        'libelle' => 'Niveau haut',
        'icon' => 'fa-circle-exclamation',
        'pill' => 'high',
    ],
];
$regles_par_niveau = [
    'standard' => [],
    'moyen' => [],
    'haut' => [],
];
foreach ($regles as $regle_row) {
    $nv = (string) ($regle_row['niveau'] ?? '');
    if (isset($regles_par_niveau[$nv])) {
        $regles_par_niveau[$nv][] = $regle_row;
    }
}
$onglet_actif = isset($_GET['niveau']) ? (string) $_GET['niveau'] : 'standard';
if (!isset($niveaux_onglets[$onglet_actif])) {
    $onglet_actif = 'standard';
}

$categories = get_all_categories();
$sous_categories_all = get_all_sous_categories_with_categorie_nom();
$sous_categories_json = json_encode(array_map(function ($sc) {
    return [
        'id' => (int) $sc['id'],
        'categorie_id' => (int) $sc['categorie_id'],
        'nom' => (string) $sc['nom'],
        'categorie_nom' => (string) ($sc['categorie_nom'] ?? ''),
    ];
}, $sous_categories_all), JSON_UNESCAPED_UNICODE);

$form_niveau = '';
$form_seuil = '';
$form_categories = [];
$form_sous_categories = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['enregistrer_alerte']) && !empty($error_message)) {
    $form_niveau = isset($_POST['niveau']) ? (string) $_POST['niveau'] : '';
    $form_seuil = isset($_POST['seuil']) ? (string) $_POST['seuil'] : '';
    $form_categories = isset($_POST['categories']) && is_array($_POST['categories'])
        ? array_map('intval', $_POST['categories']) : [];
    $form_sous_categories = isset($_POST['sous_categories']) && is_array($_POST['sous_categories'])
        ? array_map('intval', $_POST['sous_categories']) : [];
    if ($form_niveau !== '' && isset($niveaux_onglets[$form_niveau])) {
        $onglet_actif = $form_niveau;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes de stock — Paramètres</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-alertes-stock-page.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-parametres-admin page-alertes-stock">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page as-wrap">
        <header class="as-hero" role="banner">
            <a class="as-hero__back" href="../parametres.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>
            <div class="as-hero__row">
                <div class="as-hero__icon" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
                <div class="as-hero__text">
                    <h1 class="as-hero__title">Alertes de stock</h1>
                    <p class="as-hero__lead">
                        Définissez des seuils en unités <strong>par catégorie et sous-catégorie</strong>&nbsp;: dès que le stock d’un produit concerné <strong>diminue et passe sous un seuil</strong>, une alerte mail part vers les comptes
                        <strong>administrateur</strong>, <strong>gestion des stocks</strong> et <strong>commercial</strong>, et un bandeau s’affiche sur le tableau de bord.
                    </p>
                </div>
            </div>
        </header>

        <div class="as-levels" role="presentation">
            <div class="as-level-pill as-level-pill--std">
                <span class="as-level-pill__dot" aria-hidden="true"></span>
                <div>
                    <b>Standard</b>
                    Vigilance courante (ex. stock encore confortable mais à surveiller).
                </div>
            </div>
            <div class="as-level-pill as-level-pill--mid">
                <span class="as-level-pill__dot" aria-hidden="true"></span>
                <div>
                    <b>Moyen</b>
                    Priorité modérée — réapprovisionnement à planifier.
                </div>
            </div>
            <div class="as-level-pill as-level-pill--high">
                <span class="as-level-pill__dot" aria-hidden="true"></span>
                <div>
                    <b>Haut</b>
                    Critique — risque de rupture imminente.
                </div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success as-flash" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error as-flash" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$tables_ok): ?>
            <div class="message error as-flash"><i class="fas fa-database" aria-hidden="true"></i> Exécutez la migration&nbsp;: <code>php migrations/run_create_stock_alertes.php</code></div>
        <?php elseif (!$scope_ok): ?>
            <div class="message error as-flash"><i class="fas fa-database" aria-hidden="true"></i> Exécutez la migration&nbsp;: <code>php migrations/run_migrate_stock_alertes_par_categorie.php</code></div>
        <?php endif; ?>

        <div class="as-toolbar">
            <p class="as-toolbar__meta">
                <?php if ($nb_regles === 0): ?>
                    Aucun seuil actif — les alertes restent désactivées.
                <?php else: ?>
                    <strong><?php echo (int) $nb_regles; ?></strong> seuil<?php echo $nb_regles > 1 ? 'x' : ''; ?> configuré<?php echo $nb_regles > 1 ? 's' : ''; ?> (plusieurs périmètres possibles).
                <?php endif; ?>
            </p>
            <button type="button" class="as-btn-primary" onclick="openModalAlerteStock(window.__asOngletActif || 'standard')" <?php echo (!$tables_ok || !$scope_ok) ? 'disabled' : ''; ?>>
                <i class="fas fa-plus-circle" aria-hidden="true"></i>
                Nouveau seuil
            </button>
        </div>

        <?php if (empty($regles)): ?>
            <div class="as-empty">
                <div class="as-empty__icon"><i class="fas fa-bell-slash" aria-hidden="true"></i></div>
                <h3>Aucune alerte configurée</h3>
                <p>Ajoutez un seuil en choisissant le niveau, les catégories (et optionnellement les sous-catégories) pour activer les e-mails automatiques et le bandeau d’alerte.</p>
            </div>
        <?php else: ?>
            <div class="as-card as-tabs-card">
                <div class="as-tabs" role="tablist" aria-label="Niveaux d’alerte">
                    <?php foreach ($niveaux_onglets as $nv_key => $nv_meta):
                        $nv_count = count($regles_par_niveau[$nv_key]);
                        $is_active = $onglet_actif === $nv_key;
                        ?>
                        <button type="button"
                            class="as-tab as-tab--<?php echo htmlspecialchars($nv_meta['pill']); ?><?php echo $is_active ? ' is-active' : ''; ?>"
                            role="tab"
                            id="as-tab-<?php echo htmlspecialchars($nv_key); ?>"
                            aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                            aria-controls="as-panel-<?php echo htmlspecialchars($nv_key); ?>"
                            data-niveau="<?php echo htmlspecialchars($nv_key); ?>">
                            <i class="fas <?php echo htmlspecialchars($nv_meta['icon']); ?>" aria-hidden="true"></i>
                            <span><?php echo htmlspecialchars($nv_meta['label']); ?></span>
                            <span class="as-tab__count"><?php echo (int) $nv_count; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($niveaux_onglets as $nv_key => $nv_meta):
                    $nv_regles = $regles_par_niveau[$nv_key];
                    $is_active = $onglet_actif === $nv_key;
                    ?>
                    <div class="as-tab-panel<?php echo $is_active ? ' is-active' : ''; ?>"
                        role="tabpanel"
                        id="as-panel-<?php echo htmlspecialchars($nv_key); ?>"
                        aria-labelledby="as-tab-<?php echo htmlspecialchars($nv_key); ?>"
                        data-niveau="<?php echo htmlspecialchars($nv_key); ?>"
                        <?php echo $is_active ? '' : 'hidden'; ?>>
                        <?php if (empty($nv_regles)): ?>
                            <div class="as-tab-empty">
                                <div class="as-tab-empty__icon as-tab-empty__icon--<?php echo htmlspecialchars($nv_meta['pill']); ?>">
                                    <i class="fas <?php echo htmlspecialchars($nv_meta['icon']); ?>" aria-hidden="true"></i>
                                </div>
                                <h3>Aucun seuil — <?php echo htmlspecialchars($nv_meta['libelle']); ?></h3>
                                <p>Ajoutez un seuil pour ce niveau afin de surveiller les stocks des catégories concernées.</p>
                                <button type="button" class="as-btn-primary as-btn-primary--sm" onclick="openModalAlerteStock('<?php echo htmlspecialchars($nv_key); ?>')">
                                    <i class="fas fa-plus-circle" aria-hidden="true"></i>
                                    Ajouter un seuil <?php echo htmlspecialchars(strtolower($nv_meta['label'])); ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="as-table-scroll">
                                <table class="as-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Périmètre</th>
                                            <th scope="col">Déclenchement</th>
                                            <th scope="col">Création</th>
                                            <th scope="col"><span class="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($nv_regles as $r): ?>
                                            <tr>
                                                <td class="as-scope-cell">
                                                    <span class="as-scope-cell__label"><?php echo htmlspecialchars((string) ($r['scope_libelle'] ?? '—')); ?></span>
                                                    <?php if (!empty($r['sous_categories'])): ?>
                                                        <span class="as-scope-cell__detail"><?php echo (int) count($r['sous_categories']); ?> sous-catégorie<?php echo count($r['sous_categories']) > 1 ? 's' : ''; ?></span>
                                                    <?php elseif (!empty($r['categories']) && empty($r['sous_categorie_ids'])): ?>
                                                        <span class="as-scope-cell__detail">toutes sous-catégories</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="as-seuil-cell">
                                                    <?php echo (int) $r['seuil']; ?>
                                                    <span>unités max</span>
                                                </td>
                                                <td class="as-date-cell"><?php echo htmlspecialchars((string) ($r['date_creation'] ?? '—')); ?></td>
                                                <td>
                                                    <form method="post" class="as-delete-form" onsubmit="return confirm('Supprimer ce seuil ?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                                        <input type="hidden" name="supprimer_alerte" value="1">
                                                        <input type="hidden" name="niveau_onglet" value="<?php echo htmlspecialchars($nv_key); ?>">
                                                        <input type="hidden" name="regle_id" value="<?php echo (int) $r['id']; ?>">
                                                        <button type="submit" class="as-btn-delete" title="Supprimer ce seuil" aria-label="Supprimer">
                                                            <i class="fas fa-trash-can" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="as-modal" id="modalAlerteStock" aria-hidden="true" role="presentation">
            <div class="as-modal__backdrop" onclick="closeModalAlerteStock()"></div>
            <div class="as-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="titreAlerteStock">
                <div class="as-modal__head">
                    <div class="as-modal__head-top">
                        <div>
                            <h2 id="titreAlerteStock" class="as-modal__title">
                                <i class="fas fa-sliders" aria-hidden="true"></i>
                                Configurer un seuil
                            </h2>
                            <p class="as-modal__subtitle">Choisissez le niveau, les catégories concernées, puis les sous-catégories (optionnel) et la quantité qui déclenche l’alerte.</p>
                        </div>
                        <button type="button" class="as-modal__close" onclick="closeModalAlerteStock()" aria-label="Fermer la fenêtre">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <form method="post" id="formAlerteStock">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <input type="hidden" name="enregistrer_alerte" value="1">
                    <div class="as-modal__body">
                        <div class="as-field">
                            <label for="niveau">
                                <i class="fas fa-layer-group" aria-hidden="true"></i>
                                Niveau d’alerte
                            </label>
                            <select id="niveau" name="niveau" required>
                                <option value="">Choisir un niveau…</option>
                                <option value="standard" <?php echo $form_niveau === 'standard' ? 'selected' : ''; ?>>Niveau standard</option>
                                <option value="moyen" <?php echo $form_niveau === 'moyen' ? 'selected' : ''; ?>>Niveau moyen</option>
                                <option value="haut" <?php echo $form_niveau === 'haut' ? 'selected' : ''; ?>>Niveau haut</option>
                            </select>
                            <span class="as-field__hint">Plusieurs seuils possibles pour un même niveau, avec des périmètres catégorie différents.</span>
                        </div>

                        <div class="as-field">
                            <span class="as-field__legend">
                                <i class="fas fa-folder-tree" aria-hidden="true"></i>
                                Catégories
                            </span>
                            <div class="as-check-grid" id="asCategoriesGrid">
                                <?php if (empty($categories)): ?>
                                    <p class="as-check-empty">Aucune catégorie disponible.</p>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat):
                                        $cid = (int) $cat['id'];
                                        $checked = in_array($cid, $form_categories, true) ? ' checked' : '';
                                        ?>
                                        <label class="as-check-item">
                                            <input type="checkbox" name="categories[]" value="<?php echo $cid; ?>" data-categorie-id="<?php echo $cid; ?>" class="as-categorie-cb"<?php echo $checked; ?>>
                                            <span><?php echo htmlspecialchars((string) $cat['nom']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <span class="as-field__hint">Sélectionnez une ou plusieurs catégories. Les sous-catégories affichées ci-dessous seront filtrées en conséquence.</span>
                        </div>

                        <div class="as-field as-field--sous-cat" id="asSousCatField">
                            <div class="as-field__row-head">
                                <span class="as-field__legend">
                                    <i class="fas fa-tags" aria-hidden="true"></i>
                                    Sous-catégories
                                </span>
                                <button type="button" class="as-btn-select-all" id="asBtnSelectAllSc" disabled>
                                    <i class="fas fa-check-double" aria-hidden="true"></i>
                                    Tout sélectionner
                                </button>
                            </div>
                            <div class="as-check-grid as-check-grid--scroll" id="asSousCategoriesGrid">
                                <p class="as-check-empty" id="asSousCatPlaceholder">Sélectionnez d’abord une ou plusieurs catégories.</p>
                            </div>
                            <span class="as-field__hint">Optionnel&nbsp;: si aucune sous-catégorie n’est cochée, le seuil s’applique à <strong>tous les produits</strong> des catégories sélectionnées.</span>
                        </div>

                        <div class="as-field">
                            <label for="seuil">
                                <i class="fas fa-hashtag" aria-hidden="true"></i>
                                Seuil de déclenchement
                            </label>
                            <div class="as-input-wrap">
                                <input type="number" id="seuil" name="seuil" min="0" max="2147483646" step="1" required
                                    placeholder="Ex. 15"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    value="<?php echo htmlspecialchars($form_seuil, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="as-input-suffix" aria-hidden="true">unités</span>
                            </div>
                            <span class="as-field__hint">L’alerte part lorsque le stock devient <strong>inférieur ou égal</strong> à cette valeur après une diminution.</span>
                        </div>
                    </div>
                    <div class="as-modal__footer">
                        <button type="button" class="as-modal__cancel" onclick="closeModalAlerteStock()">Annuler</button>
                        <button type="submit" class="as-modal__submit">
                            <i class="fas fa-check" aria-hidden="true"></i>
                            Enregistrer le seuil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        window.__asSousCategories = <?php echo $sous_categories_json ?: '[]'; ?>;
        window.__asFormSousCategories = <?php echo json_encode($form_sous_categories); ?>;
        window.__asReopenModal = <?php echo (!empty($error_message) && ($_POST['enregistrer_alerte'] ?? '') === '1') ? 'true' : 'false'; ?>;
        window.__asOngletActif = <?php echo json_encode($onglet_actif); ?>;
    </script>
    <script src="/js/admin-alertes-stock.js<?php echo asset_version_query(); ?>"></script>
</body>
</html>
