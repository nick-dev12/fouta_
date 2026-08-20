<?php
/**
 * Page d'accueil du tableau de bord administrateur
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/require_access.php';
require_once __DIR__ . '/../includes/admin_permissions.php';

$dashboard_roles = ['admin', 'informaticien', 'developpeur', 'gestion_stock_general'];
if (!in_array(admin_current_role(), $dashboard_roles, true)) {
    admin_redirect_role_home();
}

require_once __DIR__ . '/../models/model_commandes_admin.php';
require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_categories.php';

$dashboard_show_commandes = in_array(admin_current_role(), ['informaticien', 'developpeur'], true);

$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$marque_id = isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0;
$fournisseur_id = isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0;
$admin_show_catalogue = admin_can_gestion_boutique();

require_once __DIR__ . '/../models/model_dashboard_stats.php';
$dash_charts_payload = dashboard_charts_payload((int) date('Y'));
$dash_stats_jour = dashboard_stats_jour();
$produits_top_vendus = $admin_show_catalogue ? dashboard_produits_top_vendus_details(15) : [];

$categories = [];
$marques_filtre = [];
$fournisseurs_filtre = [];
$dashboard_filtres_classes = 'admin-filters-bar page-dashboard-filters';

require_once __DIR__ . '/../includes/fpl_ui.php';

/**
 * @param float|int $current
 * @param float|int $previous
 */
function dashboard_calc_trend($current, $previous) {
    $current = (float) $current;
    $previous = (float) $previous;
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

$total_commandes = 0;
$commandes_perso_en_attente = 0;
$en_attente = 0;
$prise_en_charge = 0;
$livraison_en_cours = 0;
$confirmee = 0;
$livree = 0;
$paye = 0;

if ($dashboard_show_commandes) {
    $total_commandes = count_commandes_by_statut();
    $commandes_perso_en_attente = count_commandes_personnalisees_by_statut('en_attente');
    $en_attente = count_commandes_by_statut('en_attente');
    $prise_en_charge = count_commandes_by_statut('prise_en_charge');
    $livraison_en_cours = count_commandes_by_statut('livraison_en_cours');
    $confirmee = count_commandes_by_statut('confirmee');
    $livree = count_commandes_by_statut('livree');
    $paye = count_commandes_by_statut('paye');
} else {
    $commandes_perso_en_attente = count_commandes_personnalisees_by_statut('en_attente');
}

$card_nouvelles = $en_attente;
$card_en_cours = $prise_en_charge + $livraison_en_cours + $confirmee;
$card_attente = $commandes_perso_en_attente;
$card_expediees = $livree + $paye;

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$prev_week_start = date('Y-m-d', strtotime('monday last week'));
$prev_week_end = date('Y-m-d', strtotime('sunday last week'));

$commandes_semaine = $dashboard_show_commandes
    ? get_commandes_by_periode('plage', null, null, $week_start, $week_end) : [];
$commandes_semaine_prev = $dashboard_show_commandes
    ? get_commandes_by_periode('plage', null, null, $prev_week_start, $prev_week_end) : [];

$kpi_nb_semaine = count($commandes_semaine);
$kpi_ca_semaine = array_sum(array_column($commandes_semaine, 'montant_total'));
$kpi_nb_semaine_prev = count($commandes_semaine_prev);
$kpi_ca_semaine_prev = array_sum(array_column($commandes_semaine_prev, 'montant_total'));
$trend_nb_semaine = dashboard_calc_trend($kpi_nb_semaine, $kpi_nb_semaine_prev);
$trend_ca_semaine = dashboard_calc_trend($kpi_ca_semaine, $kpi_ca_semaine_prev);

$stats_vendues = get_stats_commandes_vendues_globales();
$kpi_produits = ($admin_show_catalogue && function_exists('count_all_produits_actifs'))
    ? count_all_produits_actifs() : 0;

$dash_date_longue = fpl_date_longue();

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Administration FOUTA POIDS LOURDS</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php $pwa_mode = 'admin'; include __DIR__ . '/../includes/pwa_meta.php'; ?>
<?php include __DIR__ . '/includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-dashboard-maquette.css'); ?>
    <?php fpl_css_link('admin-dashboard-page.css'); ?>
    <?php fpl_css_link('admin-dashboard-caisse-pages.css'); ?>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Barre de navigation verticale -->

    <!-- Contenu principal -->
    <div class="contents-container dashboard-page page-dashboard-home dash-maquette">

        <div class="dash-maquette__topmeta">
            <ol class="dash-breadcrumbs">
                <li><a href="dashboard.php">Accueil</a></li>
                <li>Administration</li>
                <li><span class="is-current">Tableau de bord</span></li>
            </ol>
            <time class="dash-maquette__date" datetime="<?php echo date('Y-m-d'); ?>"><?php echo e($dash_date_longue); ?></time>
        </div>

        <div class="dash-maquette__quick">
            <button type="button" id="btn-install-pwa" class="dash-quick-btn dashboard-pwa-install"
                title="Installer l’application administration (PWA)">
                <i class="fas fa-download" aria-hidden="true"></i> Installer l’application
            </button>
            <button type="button" id="btn-enable-notifications" class="dash-quick-btn"
                title="Notifications push — nouvelles commandes">
                <i class="fas fa-bell" aria-hidden="true"></i> Activer les notifications
            </button>
        </div>

        <?php
        if (isset($_SESSION['notification_test_message'])) {
            $test_msg = $_SESSION['notification_test_message'];
            $test_type = $_SESSION['notification_test_type'] ?? 'success';
            unset($_SESSION['notification_test_message'], $_SESSION['notification_test_type']);
            ?>
            <div class="alert-box message-<?php echo htmlspecialchars($test_type); ?>">
                <p><i class="fas fa-<?php echo $test_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($test_msg); ?></p>
            </div>
            <?php
        }
        ?>

        <?php if ($dashboard_show_commandes): ?>
        <div class="dash-today-row" role="region" aria-label="Indicateurs du jour">
            <article class="dash-today-card dash-today-card--navy">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-coins"></i></span>
                <span class="dash-today-card__value"><?php echo e(fpl_montant($dash_stats_jour['ca_jour'])); ?></span>
                <span class="dash-today-card__label">CA du jour (FCFA)</span>
            </article>
            <article class="dash-today-card dash-today-card--blue">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-boxes-stacked"></i></span>
                <span class="dash-today-card__value"><?php echo e(fpl_quantite($dash_stats_jour['qte_produits_jour'], 0)); ?></span>
                <span class="dash-today-card__label">Produits vendus aujourd’hui</span>
            </article>
            <article class="dash-today-card dash-today-card--orange">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-shopping-bag"></i></span>
                <span class="dash-today-card__value"><?php echo (int) $dash_stats_jour['nb_commandes_jour']; ?></span>
                <span class="dash-today-card__label">Commandes boutique du jour</span>
            </article>
            <article class="dash-today-card dash-today-card--green">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-truck-loading"></i></span>
                <span class="dash-today-card__value"><?php echo (int) $dash_stats_jour['nb_bl_jour']; ?></span>
                <span class="dash-today-card__label">BL du jour</span>
            </article>
            <article class="dash-today-card dash-today-card--paid">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
                <span class="dash-today-card__value"><?php echo (int) $dash_stats_jour['bl_payes']; ?></span>
                <span class="dash-today-card__label">BL factures payées</span>
            </article>
            <article class="dash-today-card dash-today-card--unpaid">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                <span class="dash-today-card__value"><?php echo (int) $dash_stats_jour['bl_impayes']; ?></span>
                <span class="dash-today-card__label">BL factures impayées</span>
            </article>
        </div>
        <?php else: ?>
        <div class="dash-today-row" role="region" aria-label="Indicateurs du jour">
            <article class="dash-today-card dash-today-card--navy">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-coins"></i></span>
                <span class="dash-today-card__value"><?php echo e(fpl_montant($dash_stats_jour['ca_bl_jour'])); ?></span>
                <span class="dash-today-card__label">CA BL du jour (HT)</span>
            </article>
            <article class="dash-today-card dash-today-card--blue">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-boxes-stacked"></i></span>
                <span class="dash-today-card__value"><?php echo e(fpl_quantite($dash_stats_jour['qte_produits_jour'], 0)); ?></span>
                <span class="dash-today-card__label">Pièces vendues aujourd’hui</span>
            </article>
            <article class="dash-today-card dash-today-card--orange">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-truck-loading"></i></span>
                <span class="dash-today-card__value"><?php echo (int) $dash_stats_jour['nb_bl_jour']; ?></span>
                <span class="dash-today-card__label">BL émis aujourd’hui</span>
            </article>
            <article class="dash-today-card dash-today-card--green">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                <span class="dash-today-card__value"><?php echo (int) $dash_stats_jour['nb_clients_bl_jour']; ?></span>
                <span class="dash-today-card__label">Clients BL du jour</span>
            </article>
            <article class="dash-today-card dash-today-card--paid">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
                <span class="dash-today-card__value"><?php echo (int) $dash_stats_jour['bl_payes']; ?></span>
                <span class="dash-today-card__label">BL payés</span>
            </article>
            <article class="dash-today-card dash-today-card--unpaid">
                <span class="dash-today-card__icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                <span class="dash-today-card__value"><?php echo (int) $dash_stats_jour['bl_impayes']; ?></span>
                <span class="dash-today-card__label">BL impayés</span>
            </article>
        </div>
        <?php endif; ?>

        <div class="dash-charts-row" role="region" aria-label="Graphiques de performance">
            <article class="dash-chart-card">
                <header class="dash-chart-card__head">
                    <h3>Ventes <?php echo (int) date('Y'); ?></h3>
                    <span class="dash-chart-card__hint">Quantités &amp; montants par mois</span>
                </header>
                <div class="dash-chart-card__body">
                    <canvas id="dashChartMensuel" aria-label="Ventes mensuelles"></canvas>
                </div>
            </article>
            <article class="dash-chart-card">
                <header class="dash-chart-card__head">
                    <h3>Top 10 produits vendus</h3>
                    <span class="dash-chart-card__hint">Boutique + bons de livraison</span>
                </header>
                <div class="dash-chart-card__body">
                    <canvas id="dashChartTopProduits" aria-label="Produits les plus vendus"></canvas>
                </div>
            </article>
            <article class="dash-chart-card">
                <header class="dash-chart-card__head">
                    <h3>BL récents (7 jours)</h3>
                    <span class="dash-chart-card__hint">Pièces commandées &amp; montant HT</span>
                </header>
                <div class="dash-chart-card__body">
                    <canvas id="dashChartBlRecents" aria-label="Bons de livraison récents"></canvas>
                </div>
            </article>
        </div>
        <script type="application/json" id="dashChartsData"><?php echo json_encode($dash_charts_payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

        <?php if ($dashboard_show_commandes && ($en_attente > 0 || $prise_en_charge > 0)): ?>
            <div class="alert-box alert-box--dashboard">
                <p>
                    <i class="fas fa-exclamation-circle"></i>
                    <?php if ($en_attente > 0): ?>
                        <?php echo $en_attente; ?> commande<?php echo $en_attente > 1 ? 's' : ''; ?> en attente de prise en charge
                    <?php else: ?>
                        <?php echo $prise_en_charge; ?> commande<?php echo $prise_en_charge > 1 ? 's' : ''; ?> prête<?php echo $prise_en_charge > 1 ? 's' : ''; ?> à être expédiée<?php echo $prise_en_charge > 1 ? 's' : ''; ?>
                    <?php endif; ?>
                </p>
                <a href="commandes/index.php" class="btn-alert"><i class="fas fa-arrow-right"></i> Gérer les commandes</a>
            </div>
        <?php endif; ?>

        <?php if ($commandes_perso_en_attente > 0): ?>
            <div class="alert-box alert-box--dashboard alert-box--spaced">
                <p>
                    <i class="fas fa-palette"></i>
                    <?php echo $commandes_perso_en_attente; ?> commande<?php echo $commandes_perso_en_attente > 1 ? 's' : ''; ?> personnalisée<?php echo $commandes_perso_en_attente > 1 ? 's' : ''; ?> en attente
                </p>
                <a href="commandes-personnalisees/index.php" class="btn-alert"><i class="fas fa-arrow-right"></i> Voir les commandes personnalisées</a>
            </div>
        <?php endif; ?>

        <?php if ($admin_show_catalogue): ?>
        <?php
        require_once __DIR__ . '/../includes/site_url.php';
        $dash_upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';
        ?>
        <section class="dash-panel dash-panel--catalogue dash-panel--top-produits" aria-label="Produits les plus vendus">
            <div class="dash-panel__head">
                <h2 class="dash-panel__title">Produits les plus vendus</h2>
                <a href="produits/index.php" class="dash-table__view">Catalogue complet <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
            </div>
            <div class="dash-table-wrap dash-table-wrap--produits">
                <table class="dash-table dash-table--produits">
                    <thead>
                        <tr>
                            <th class="col-thumb">Visuel</th>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th class="col-num">Vendus</th>
                            <th class="col-num">CA généré</th>
                            <th class="col-num">Stock</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produits_top_vendus)): ?>
                        <tr>
                            <td colspan="8" class="dash-table-empty">Aucune vente enregistrée pour le moment.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($produits_top_vendus as $idx => $pv):
                            $pid = (int) ($pv['produit_id'] ?? 0);
                            $img = trim((string) ($pv['image_principale'] ?? ''));
                            $statut = (string) ($pv['statut'] ?? '');
                            $statut_label = $statut !== '' ? ucfirst(str_replace('_', ' ', $statut)) : '—';
                            $badge_class = 'dash-badge--muted';
                            if ($statut === 'actif') {
                                $badge_class = 'dash-badge--ok';
                            } elseif ($statut === 'rupture_stock') {
                                $badge_class = 'dash-badge--cours';
                            }
                            ?>
                        <tr>
                            <td class="col-thumb" data-label="Visuel">
                                <?php if ($img !== ''): ?>
                                <img src="<?php echo e($dash_upload_base . $img); ?>" alt="" class="dash-produit-thumb" loading="lazy" decoding="async"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <span class="dash-produit-thumb dash-produit-thumb--ph" style="display:none" aria-hidden="true"><i class="fas fa-box"></i></span>
                                <?php else: ?>
                                <span class="dash-produit-thumb dash-produit-thumb--ph" aria-hidden="true"><i class="fas fa-box"></i></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Produit">
                                <strong class="dash-produit-nom"><?php echo e($pv['nom']); ?></strong>
                                <?php if (!empty($pv['reference'])): ?>
                                <span class="dash-produit-ref"><?php echo e($pv['reference']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Catégorie"><?php echo e($pv['categorie_nom'] !== '' ? $pv['categorie_nom'] : '—'); ?></td>
                            <td class="col-num" data-label="Vendus"><?php echo e(fpl_quantite($pv['total_qte'], 0)); ?></td>
                            <td class="col-num" data-label="CA"><?php echo e(fpl_montant($pv['total_montant'])); ?> FCFA</td>
                            <td class="col-num" data-label="Stock"><?php echo $pv['stock'] !== null ? (int) $pv['stock'] : '—'; ?></td>
                            <td data-label="Statut">
                                <?php if ($statut !== ''): ?>
                                <span class="dash-badge <?php echo $badge_class; ?>"><?php echo e($statut_label); ?></span>
                                <?php else: ?>
                                <span class="dash-badge dash-badge--muted">Hors catalogue</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="">
                                <?php if ($pid > 0): ?>
                                <a href="produits/ajuster-stock.php?id=<?php echo $pid; ?>" class="dash-table__view">Voir <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="dash-panel__foot">
                <span>Classement basé sur les ventes boutique + BL validés</span>
                <a href="produits/index.php" class="dash-table__view">Voir tous les produits</a>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php require_once __DIR__ . '/../includes/fpl_assets.php'; ?>
    <script src="<?php echo htmlspecialchars(fpl_script_src('admin-dashboard-charts.js'), ENT_QUOTES, 'UTF-8'); ?><?php echo asset_version_query(); ?>"></script>
    <script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-messaging-compat.js"></script>
    <?php require_once __DIR__ . '/../includes/firebase_init.php'; ?>
    <script>
        if (window.FIREBASE_CONFIG) {
            firebase.initializeApp(window.FIREBASE_CONFIG);
        }
    </script>
    <script src="/js/firebase-notifications.js"></script>
    <script>
        /**
         * PWA admin : beforeinstallprompt doit être écouté tout de suite.
         */
        (function () {
            window.__foutaAdminDeferredInstallPrompt = null;

            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                window.__foutaAdminDeferredInstallPrompt = e;
                document.dispatchEvent(new CustomEvent('fouta:installprompt'));
            });

            window.addEventListener('appinstalled', function () {
                window.__foutaAdminDeferredInstallPrompt = null;
                var b = document.getElementById('btn-install-pwa');
                if (b) {
                    b.hidden = true;
                }
            });
        })();

        function foutaAdminPwaIsStandalone() {
            try {
                return window.matchMedia('(display-mode: standalone)').matches ||
                    window.matchMedia('(display-mode: fullscreen)').matches ||
                    window.matchMedia('(display-mode: minimal-ui)').matches ||
                    window.navigator.standalone === true;
            } catch (e) {
                return window.navigator.standalone === true;
            }
        }

        /** iPhone, iPad, iPod — y compris UA récents et iPadOS « desktop ». */
        function foutaAdminPwaIsIosLike() {
            var ua = navigator.userAgent || '';
            if (/iPad|iPhone|iPod/i.test(ua)) {
                return true;
            }
            if (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) {
                return true;
            }
            if (/Macintosh/i.test(ua) && /Mobile\/[^\s]+/i.test(ua)) {
                return true;
            }
            return false;
        }

        function foutaAdminPwaRemoveHelp() {
            var o = document.getElementById('fouta-pwa-help-overlay');
            if (o && o.parentNode) {
                o.parentNode.removeChild(o);
            }
            document.body.style.overflow = '';
        }

        function foutaAdminPwaShowHelp(mode) {
            foutaAdminPwaRemoveHelp();
            var wrap = document.createElement('div');
            wrap.id = 'fouta-pwa-help-overlay';
            wrap.className = 'fouta-pwa-help-overlay';
            wrap.setAttribute('role', 'dialog');
            wrap.setAttribute('aria-modal', 'true');
            wrap.setAttribute('aria-labelledby', 'fouta-pwa-help-title');

            var title = mode === 'ios'
                ? 'Ajouter sur l’écran d’accueil (iPhone / iPad)'
                : 'Installer l’application (Android)';
            var bodyHtml;
            if (mode === 'ios') {
                bodyHtml =
                    '<ol class="fouta-pwa-help-steps">' +
                    '<li>Touchez <strong>Partager</strong> ' +
                    '<span class="fouta-pwa-help-hint">(carré avec flèche vers le haut)</span> en bas de l’écran ou dans la barre d’adresse.</li>' +
                    '<li>Choisissez <strong>« Sur l’écran d’accueil »</strong> ou ' +
                    '<strong>« Ajouter à l’écran d’accueil »</strong>, puis validez avec <strong>Ajouter</strong>.</li>' +
                    '<li>L’icône <strong>FPL Admin</strong> apparaîtra comme un raccourci application.</li>' +
                    '</ol>' +
                    '<p class="fouta-pwa-help-note">Avec Chrome sur iOS, le bouton Partager est souvent dans la barre du bas.</p>';
            } else {
                bodyHtml =
                    '<ol class="fouta-pwa-help-steps">' +
                    '<li>Ouvrez cette page dans <strong>Google Chrome</strong> si possible.</li>' +
                    '<li>Touchez le menu <strong>⋮</strong> en haut à droite.</li>' +
                    '<li>Choisissez <strong>« Installer l’application »</strong>, ' +
                    '<strong>« Ajouter à l’écran d’accueil »</strong> ou l’équivalent proposé par votre navigateur.</li>' +
                    '</ol>' +
                    '<p class="fouta-pwa-help-note">Si rien n’apparaît : attendez 10–15 s après le chargement, actualisez, ou vérifiez que le site est bien en HTTPS. ' +
                    'Certains navigateurs intégrés (Facebook, etc.) ne proposent pas l’installation : ouvrez le lien dans Chrome.</p>';
            }

            wrap.innerHTML =
                '<div class="fouta-pwa-help-dialog">' +
                '<button type="button" class="fouta-pwa-help-close" data-fouta-pwa-close aria-label="Fermer">&times;</button>' +
                '<h2 id="fouta-pwa-help-title">' + title + '</h2>' +
                bodyHtml +
                '<button type="button" class="btn-primary fouta-pwa-help-btn-ok" data-fouta-pwa-close>Fermer</button>' +
                '</div>';

            document.body.appendChild(wrap);
            document.body.style.overflow = 'hidden';

            function closeHelp() {
                foutaAdminPwaRemoveHelp();
            }
            wrap.querySelectorAll('[data-fouta-pwa-close]').forEach(function (el) {
                el.addEventListener('click', closeHelp);
            });
            wrap.addEventListener('click', function (ev) {
                if (ev.target === wrap) {
                    closeHelp();
                }
            });
            document.addEventListener('keydown', function onEsc(ev) {
                if (ev.key === 'Escape') {
                    document.removeEventListener('keydown', onEsc);
                    closeHelp();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('btn-enable-notifications');
            if (btn) {
                btn.addEventListener('click', function () {
                    if (typeof FirebaseNotifications !== 'undefined') {
                        FirebaseNotifications.enable('admin', this);
                    } else {
                        alert(
                            'Erreur: Les scripts de notification ne sont pas chargés. Vérifiez la console (F12).'
                        );
                    }
                });
            }

            var installBtn = document.getElementById('btn-install-pwa');
            if (!installBtn) {
                return;
            }

            if (foutaAdminPwaIsStandalone()) {
                installBtn.hidden = true;
                return;
            }

            if (foutaAdminPwaIsIosLike()) {
                installBtn.setAttribute('title', 'Ajouter le raccourci sur l’écran d’accueil');
                var icIos = installBtn.querySelector('i');
                if (icIos) {
                    icIos.className = 'fas fa-mobile-screen-button';
                }
                installBtn.innerHTML = '<i class="fas fa-mobile-screen-button" aria-hidden="true"></i> Ajouter à l’écran d’accueil';
            }

            installBtn.hidden = false;

            document.addEventListener('fouta:installprompt', function () {
                if (!foutaAdminPwaIsStandalone()) {
                    installBtn.hidden = false;
                }
            });

            installBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                if (foutaAdminPwaIsStandalone()) {
                    return;
                }

                if (foutaAdminPwaIsIosLike()) {
                    foutaAdminPwaShowHelp('ios');
                    return;
                }

                var dp = window.__foutaAdminDeferredInstallPrompt;
                if (dp && typeof dp.prompt === 'function') {
                    try {
                        dp.prompt();
                        var pr = dp.userChoice;
                        if (pr && typeof pr.then === 'function') {
                            pr.then(function (choiceResult) {
                                if (choiceResult && choiceResult.outcome === 'accepted') {
                                    installBtn.hidden = true;
                                }
                                window.__foutaAdminDeferredInstallPrompt = null;
                            }).catch(function () {
                                window.__foutaAdminDeferredInstallPrompt = null;
                            });
                        }
                    } catch (err) {
                        window.__foutaAdminDeferredInstallPrompt = null;
                        foutaAdminPwaShowHelp('android');
                    }
                } else {
                    foutaAdminPwaShowHelp('android');
                }
            });
        });
    </script>
    <?php include __DIR__ . '/../includes/admin_stock_alerte_popup.php'; ?>
    <?php include 'includes/footer.php'; ?>