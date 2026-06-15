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
$categories = [];
$produits = [];
$marques_filtre = [];
$fournisseurs_filtre = [];
if ($admin_show_catalogue) {
    $categories = get_all_categories();
    if (produits_has_column('marque_id')) {
        require_once __DIR__ . '/../models/model_marques.php';
        if (marques_table_ok()) {
            $marques_filtre = get_all_marques_ordered_by_nom();
        }
    }
    if (produits_has_column('fournisseur_id')) {
        require_once __DIR__ . '/../models/model_fournisseurs.php';
        $fournisseurs_filtre = get_all_fournisseurs_ordered_by_nom();
    }

    $per_page = ADMIN_PRODUITS_LISTE_PER_PAGE;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $total_produits = count_admin_produits_liste($categorie_id, $marque_id, $fournisseur_id);
    $total_pages = max(1, (int) ceil($total_produits / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $per_page;
    $produits = get_admin_produits_liste_paginated($categorie_id, $marque_id, $fournisseur_id, $offset, $per_page);

    $pagination_query_base = [];
    if ($categorie_id > 0) {
        $pagination_query_base['categorie_id'] = $categorie_id;
    }
    if ($marque_id > 0) {
        $pagination_query_base['marque_id'] = $marque_id;
    }
    if ($fournisseur_id > 0) {
        $pagination_query_base['fournisseur_id'] = $fournisseur_id;
    }
}

$dashboard_filtres_classes = 'admin-filters-bar page-dashboard-filters';
if (!empty($marques_filtre)) {
    $dashboard_filtres_classes .= ' page-dashboard-filters--has-marque';
}
if (!empty($fournisseurs_filtre)) {
    $dashboard_filtres_classes .= ' page-dashboard-filters--has-fournisseur';
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Administration FOUTA POIDS LOURDS</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php $pwa_mode = 'admin'; include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard-caisse-pages.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Barre de navigation verticale -->

    <!-- Contenu principal -->
    <div class="contents-container dashboard-page page-dashboard-home">
        <div class="content-header dashboard-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Administration</p>
                <h1><i class="fas fa-chart-line" aria-hidden="true"></i> Tableau de bord</h1>
                <p class="dashboard-subtitle">Vue d'ensemble <?php echo $dashboard_show_commandes ? 'des commandes et ' : ''; ?>de votre catalogue produits.</p>
            </div>
            <div class="header-actions header-actions--with-pwa">
                <button type="button" id="btn-install-pwa" class="btn-primary btn-secondary-style dashboard-hero-action dashboard-hero-action--pwa dashboard-pwa-install"
                    title="Installer l’application administration sur cet appareil (PWA)">
                    <span class="dashboard-hero-action__ic" aria-hidden="true"><i class="fas fa-download"></i></span>
                    <span class="dashboard-hero-action__txt">Installer l’application</span>
                </button>
                <button type="button" id="btn-enable-notifications" class="btn-primary btn-secondary-style dashboard-hero-action dashboard-hero-action--notify"
                    title="Recevoir des notifications push pour les nouvelles commandes">
                    <span class="dashboard-hero-action__ic" aria-hidden="true"><i class="fas fa-bell"></i></span>
                    <span class="dashboard-hero-action__txt">Activer les notifications</span>
                </button>
                <!-- <a href="test-notification.php" class="btn-primary btn-secondary-style dashboard-hero-action" ...>
                </a> -->
                <?php if (admin_can_zones_livraison()): ?>
                <a href="zones-livraison/index.php" class="btn-primary btn-secondary-style dashboard-hero-action dashboard-hero-action--zones">
                    <span class="dashboard-hero-action__ic" aria-hidden="true"><i class="fas fa-truck"></i></span>
                    <span class="dashboard-hero-action__txt">Zones de livraison</span>
                </a>
                <?php endif; ?>
                <?php if (admin_can_gestion_boutique() && !admin_is_restricted_admin_account()): ?>
                <a href="produits/ajouter.php" class="btn-primary dashboard-hero-action dashboard-hero-action--product">
                    <span class="dashboard-hero-action__ic" aria-hidden="true"><i class="fas fa-plus"></i></span>
                    <span class="dashboard-hero-action__txt">Nouveau Produit</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php
        if (isset($_SESSION['notification_test_message'])) {
            $test_msg = $_SESSION['notification_test_message'];
            $test_type = $_SESSION['notification_test_type'] ?? 'success';
            unset($_SESSION['notification_test_message'], $_SESSION['notification_test_type']);
            ?>
            <div class="alert-box message-<?php echo htmlspecialchars($test_type); ?>" style="margin-bottom: 20px;">
                <p><i class="fas fa-<?php echo $test_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($test_msg); ?></p>
            </div>
            <?php
        }
        // Statistiques commandes (informaticien / développeur : le rôle admin restreint n'y a pas accès)
        $total_commandes = 0;
        $commandes_perso_en_attente = 0;
        $en_attente = 0;
        $prise_en_charge = 0;
        $livraison_en_cours = 0;
        if ($dashboard_show_commandes) {
            $total_commandes = count_commandes_by_statut();
            $commandes_perso_en_attente = count_commandes_personnalisees_by_statut('en_attente');
            $en_attente = count_commandes_by_statut('en_attente');
            $prise_en_charge = count_commandes_by_statut('prise_en_charge');
            $livraison_en_cours = count_commandes_by_statut('livraison_en_cours');
        } else {
            $commandes_perso_en_attente = count_commandes_personnalisees_by_statut('en_attente');
        }
        ?>

        <!-- Statistiques des commandes -->
        <?php if ($dashboard_show_commandes): ?>
        <div class="stats-grid" role="region" aria-label="Statistiques des commandes">
            <div class="stat-card stat-card--total">
                <div class="stat-card-icon" aria-hidden="true"><i class="fas fa-shopping-bag"></i></div>
                <div class="stat-card-body">
                    <h3>Total commandes</h3>
                    <div class="stat-value"><?php echo $total_commandes; ?></div>
                </div>
            </div>
            <div class="stat-card stat-en-attente">
                <div class="stat-card-icon" aria-hidden="true"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-card-body">
                    <h3>En attente</h3>
                    <div class="stat-value"><?php echo $en_attente; ?></div>
                </div>
            </div>
            <div class="stat-card stat-prise">
                <div class="stat-card-icon" aria-hidden="true"><i class="fas fa-hand-holding"></i></div>
                <div class="stat-card-body">
                    <h3>Prise en charge</h3>
                    <div class="stat-value"><?php echo $prise_en_charge; ?></div>
                </div>
            </div>
            <div class="stat-card stat-livraison">
                <div class="stat-card-icon" aria-hidden="true"><i class="fas fa-truck"></i></div>
                <div class="stat-card-body">
                    <h3>Livraison en cours</h3>
                    <div class="stat-value"><?php echo $livraison_en_cours; ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lien rapide vers les commandes -->
        <?php if ($dashboard_show_commandes && ($en_attente > 0 || $prise_en_charge > 0)): ?>
            <div class="alert-box alert-box--dashboard">
                <p>
                    <i class="fas fa-exclamation-circle"></i>
                    <?php if ($en_attente > 0): ?>
                        <?php echo $en_attente; ?> commande<?php echo $en_attente > 1 ? 's' : ''; ?> en attente de prise en
                        charge
                    <?php elseif ($prise_en_charge > 0): ?>
                        <?php echo $prise_en_charge; ?> commande<?php echo $prise_en_charge > 1 ? 's' : ''; ?>
                        prise<?php echo $prise_en_charge > 1 ? 's' : ''; ?> en charge,
                        prête<?php echo $prise_en_charge > 1 ? 's' : ''; ?> à être
                        expédiée<?php echo $prise_en_charge > 1 ? 's' : ''; ?>
                    <?php endif; ?>
                </p>
                <a href="commandes/index.php" class="btn-alert">
                    <i class="fas fa-arrow-right"></i> Gérer les commandes
                </a>
            </div>
        <?php endif; ?>

        <?php if ($commandes_perso_en_attente > 0): ?>
            <div class="alert-box alert-box--dashboard alert-box--spaced">
                <p>
                    <i class="fas fa-palette"></i>
                    <?php echo $commandes_perso_en_attente; ?>
                    commande<?php echo $commandes_perso_en_attente > 1 ? 's' : ''; ?>
                    personnalisée<?php echo $commandes_perso_en_attente > 1 ? 's' : ''; ?> en attente
                </p>
                <a href="commandes-personnalisees/index.php" class="btn-alert">
                    <i class="fas fa-arrow-right"></i> Voir les commandes personnalisées
                </a>
            </div>
        <?php endif; ?>

        <!-- Section produits (gestion des stocks) -->
        <?php if ($admin_show_catalogue): ?>

        <section class="produits-section produits-section--dashboard" aria-label="Catalogue produits"
            data-produits-index-page
            data-ajax-url="produits/ajax_live_search.php"
            data-ajax-context="dashboard"
            data-total-catalog="<?php echo (int) $total_produits; ?>"
            data-id-main-wrap="page-dashboard-main-wrap"
            data-id-main-grid="page-dashboard-produits-grid"
            data-id-live-wrap="page-dashboard-live-wrap"
            data-id-live-grid="page-dashboard-live-grid"
            data-id-live-empty="page-dashboard-live-empty"
            data-id-live-meta="page-dashboard-live-meta"
            data-id-pagination="page-dashboard-pagination"
            data-id-catalog-empty="page-dashboard-catalog-empty">

            <form method="GET" action="" class="<?php echo htmlspecialchars($dashboard_filtres_classes, ENT_QUOTES, 'UTF-8'); ?>"
                data-produits-index-form>
                <div class="admin-filter-field page-dashboard-filters__search">
                    <label for="recherche">Recherche</label>
                    <input type="text" id="recherche" name="recherche"
                        placeholder="Nom, description… — filtre en direct"
                        value="<?php echo htmlspecialchars($recherche); ?>" autocomplete="off" inputmode="search"
                        data-produits-index-search>
                </div>
                <div class="admin-filter-field page-dashboard-filters__categorie">
                    <label for="categorie_id">Catégorie</label>
                    <select id="categorie_id" name="categorie_id">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?php echo (int) $categorie['id']; ?>"
                                <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categorie['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($marques_filtre)): ?>
                <div class="admin-filter-field page-dashboard-filters__marque">
                    <label for="marque_id">Marque</label>
                    <select id="marque_id" name="marque_id">
                        <option value="0">Toutes les marques</option>
                        <?php foreach ($marques_filtre as $marque): ?>
                            <option value="<?php echo (int) $marque['id']; ?>"
                                <?php echo $marque_id === (int) $marque['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($marque['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (!empty($fournisseurs_filtre)): ?>
                <div class="admin-filter-field page-dashboard-filters__fournisseur">
                    <label for="fournisseur_id">Fournisseur</label>
                    <select id="fournisseur_id" name="fournisseur_id">
                        <option value="0">Tous les fournisseurs</option>
                        <?php foreach ($fournisseurs_filtre as $fournisseur): ?>
                            <option value="<?php echo (int) $fournisseur['id']; ?>"
                                <?php echo $fournisseur_id === (int) $fournisseur['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fournisseur['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="admin-filter-actions page-dashboard-filters__actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="dashboard.php" class="btn-filter-reset">
                        <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                    </a>
                </div>
            </form>

            <?php if ($total_produits === 0): ?>
                <div class="empty-state" id="page-dashboard-catalog-empty">
                    <i class="fas fa-box-open"></i>
                    <p>Aucun produit enregistré pour le moment.</p>
                    <?php if (!admin_is_restricted_admin_account()): ?>
                    <a href="produits/ajouter.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter le premier produit
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div id="page-dashboard-main-wrap" <?php echo $total_produits === 0 ? 'hidden' : ''; ?>>
                <?php if ($total_produits > 0): ?>
                <div class="produits-grid page-dashboard-produits-grid" id="page-dashboard-produits-grid">
                    <?php
                    $pcm_paths = ['base' => 'produits/', 'upload' => '/upload/'];
                    foreach ($produits as $produit):
                        include __DIR__ . '/includes/carte_produit_dashboard.php';
                    endforeach;
                    ?>
                </div>
                <?php
                $pagination_href_base = 'dashboard.php';
                $pagination_id = 'page-dashboard-pagination';
                include __DIR__ . '/includes/pagination_catalogue.php';
                ?>
                <?php else: ?>
                <div class="produits-grid page-dashboard-produits-grid" id="page-dashboard-produits-grid" hidden></div>
                <?php endif; ?>
            </div>

            <div id="page-dashboard-live-wrap" class="page-produits-live-wrap" hidden>
                <p class="page-produits-live-meta" id="page-dashboard-live-meta" aria-live="polite" hidden></p>
                <div class="produits-grid page-dashboard-produits-grid page-produits-live-grid" id="page-dashboard-live-grid"></div>
                <div class="empty-state page-dashboard-live-empty" id="page-dashboard-live-empty" hidden>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <p>Aucun produit ne correspond à votre recherche.</p>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <script src="/js/admin-produits-index-search.js<?php echo asset_version_query(); ?>"></script>
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
            document.addEventListener('click', function (event) {
                var card = event.target.closest('.page-dashboard-home .produit-card-linkable');
                if (!card) {
                    return;
                }
                if (event.target.closest('a, button, input, select, textarea, form')) {
                    return;
                }
                var href = card.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            });

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
                var icIos = installBtn.querySelector('.dashboard-hero-action__ic');
                var txtIos = installBtn.querySelector('.dashboard-hero-action__txt');
                if (icIos) {
                    icIos.innerHTML = '<i class="fas fa-mobile-screen-button" aria-hidden="true"></i>';
                }
                if (txtIos) {
                    txtIos.textContent = 'Ajouter à l’écran d’accueil';
                }
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

        // Modal de confirmation de suppression
        (function() {
            var deleteOverlay = document.createElement('div');
            deleteOverlay.className = 'delete-confirm-overlay';
            deleteOverlay.id = 'deleteConfirmOverlay';
            document.body.appendChild(deleteOverlay);

            var deleteModal = document.createElement('div');
            deleteModal.className = 'delete-confirm-modal';
            deleteModal.id = 'deleteConfirmModal';
            deleteModal.setAttribute('role', 'dialog');
            deleteModal.setAttribute('aria-modal', 'true');
            deleteModal.innerHTML = `
                <div class="delete-confirm-modal__icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h3 class="delete-confirm-modal__title">Confirmer la suppression</h3>
                <p class="delete-confirm-modal__text">Êtes-vous sûr de vouloir supprimer ce produit ?</p>
                <div class="delete-confirm-modal__product" id="deleteConfirmProduct"></div>
                <p class="delete-confirm-modal__warning"><i class="fas fa-info-circle"></i> Cette action est irréversible</p>
                <div class="delete-confirm-modal__actions">
                    <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--cancel" id="deleteConfirmCancel">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--confirm" id="deleteConfirmConfirm">
                        <i class="fas fa-trash"></i> Confirmer
                    </button>
                </div>
            `;
            document.body.appendChild(deleteModal);

            var deleteProduct = document.getElementById('deleteConfirmProduct');
            var deleteCancel = document.getElementById('deleteConfirmCancel');
            var deleteConfirm = document.getElementById('deleteConfirmConfirm');
            var currentDeleteLink = null;

            function positionModal(triggerElement) {
                var rect = triggerElement.getBoundingClientRect();
                var modalWidth = deleteModal.offsetWidth || 360;
                var modalHeight = deleteModal.offsetHeight || 300;
                var left = rect.left + (rect.width / 2) - (modalWidth / 2);
                var top = rect.top + rect.height + 10;
                if (left < 10) left = 10;
                if (left + modalWidth > window.innerWidth - 10) left = window.innerWidth - modalWidth - 10;
                if (top + modalHeight > window.innerHeight - 10) top = rect.top - modalHeight - 10;
                if (top < 10) top = 10;
                deleteModal.style.left = left + 'px';
                deleteModal.style.top = top + 'px';
            }

            function showModal(link) {
                currentDeleteLink = link;
                var productName = link.getAttribute('data-delete-name') || 'ce produit';
                deleteProduct.textContent = productName;
                deleteOverlay.classList.add('visible');
                deleteModal.classList.add('visible', 'animated');
                deleteCancel.focus();
            }

            function hideModal() {
                deleteOverlay.classList.remove('visible');
                deleteModal.classList.remove('visible', 'animated');
                currentDeleteLink = null;
            }

            document.addEventListener('click', function (event) {
                var link = event.target.closest('a[data-delete-confirm="true"]');
                if (!link) {
                    return;
                }
                event.preventDefault();
                positionModal(link);
                showModal(link);
            });

            deleteCancel.addEventListener('click', hideModal);
            deleteOverlay.addEventListener('click', hideModal);
            deleteConfirm.addEventListener('click', function() {
                if (currentDeleteLink) window.location.href = currentDeleteLink.href;
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && deleteModal.classList.contains('visible')) hideModal();
            });
        })();
    </script>
    <?php include __DIR__ . '/../includes/admin_stock_alerte_popup.php'; ?>
    <?php include 'includes/footer.php'; ?>