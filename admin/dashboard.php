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
$admin_show_catalogue = admin_can_gestion_boutique();
$categories = [];
$produits = [];
if ($admin_show_catalogue) {
    $categories = get_all_categories();
    $produits = get_all_produits();
}

if ($admin_show_catalogue && !empty($produits)) {
    $produits = array_values(array_filter($produits, function ($produit) use ($recherche, $categorie_id) {
        if ($categorie_id > 0 && (int) ($produit['categorie_id'] ?? 0) !== $categorie_id) {
            return false;
        }

        if ($recherche === '') {
            return true;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($recherche) : strtolower($recherche);
        $haystacks = [
            $produit['nom'] ?? '',
            $produit['description'] ?? '',
            $produit['categorie_nom'] ?? '',
            $produit['statut'] ?? ''
        ];

        foreach ($haystacks as $value) {
            $value = function_exists('mb_strtolower') ? mb_strtolower((string) $value) : strtolower((string) $value);
            if (strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }));
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
    <link rel="stylesheet" href="/css/admin-dashboard-caisse-pages.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Barre de navigation verticale -->

    <!-- Contenu principal -->
    <div class="contents-container dashboard-page">
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

        <section class="produits-section produits-section--dashboard" aria-labelledby="produits-heading">
            <div class="section-title section-title--dashboard">
                <div>
                    <h2 id="produits-heading"><i class="fas fa-box" aria-hidden="true"></i> Catalogue produits</h2>
                    <p class="section-title-hint"><?php echo count($produits); ?> produit<?php echo count($produits) > 1 ? 's' : ''; ?> affiché<?php echo count($produits) > 1 ? 's' : ''; ?></p>
                </div>
            </div>

            <form method="GET" action="" class="admin-filters-bar">
                <div class="admin-filter-field">
                    <label for="recherche">Recherche</label>
                    <input type="text" id="recherche" name="recherche" placeholder="Nom, description, statut..."
                        value="<?php echo htmlspecialchars($recherche); ?>">
                </div>
                <div class="admin-filter-field">
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
                <div class="admin-filter-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="dashboard.php" class="btn-filter-reset">
                        <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                    </a>
                </div>
            </form>

            <?php if (empty($produits)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>Aucun produit enregistré pour le moment.</p>
                    <?php if (!admin_is_restricted_admin_account()): ?>
                    <a href="produits/ajouter.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter le premier produit
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Grille de produits -->
                <div class="produits-grid">
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        $statut_class = 'statut-actif';
                        if ($produit['statut'] == 'inactif') {
                            $statut_class = 'statut-inactif';
                        } elseif ($produit['statut'] == 'rupture_stock') {
                            $statut_class = 'statut-rupture';
                        }
                        $statut_label = ucfirst(str_replace('_', ' ', (string) ($produit['statut'] ?? '')));
                        $img_catalogue = '';
                        if (!empty($produit['image_principale'])) {
                            $img_catalogue = trim((string) $produit['image_principale']);
                        }
                        ?>
                        <div class="produit-card produit-card-linkable produit-card--dashboard"
                            data-href="produits/modifier.php?id=<?php echo (int) $produit['id']; ?>">
                            <span class="statut-badge <?php echo $statut_class; ?>"><?php echo htmlspecialchars($statut_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="produit-card-media">
                                <?php if ($img_catalogue !== ''): ?>
                                <img src="/upload/<?php echo htmlspecialchars($img_catalogue, ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="produit-card-image"
                                    onerror="this.onerror=null;var w=document.createElement('div');w.className='produit-card-media-placeholder';w.setAttribute('role','img');w.setAttribute('aria-label','Sans image');w.innerHTML='<i class=\'fas fa-truck\' aria-hidden=\'true\'></i>';this.replaceWith(w);">
                                <?php else: ?>
                                <div class="produit-card-media-placeholder" role="img" aria-label="Pas d'image">
                                    <i class="fas fa-truck" aria-hidden="true"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="produit-card-body">
                                <h3 class="produit-card-nom"><?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="produit-card-categorie">
                                    <i class="fas fa-tag" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars((string) ($produit['categorie_nom'] ?? 'Sans catégorie'), ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <p class="produit-card-prix">
                                    <span class="prix-montant"><?php echo number_format((float) ($produit['prix'] ?? 0), 0, ',', ' '); ?></span>
                                    <span class="prix-unite">FCFA</span>
                                    <?php if (!empty($produit['prix_promotion'])): ?>
                                        <span class="prix-promo">
                                            Promo <?php echo number_format((float) $produit['prix_promotion'], 0, ',', ' '); ?> FCFA
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="produit-card-stock">
                                    <i class="fas fa-cubes" aria-hidden="true"></i>
                                    Stock <span class="stock-value"><?php echo (int) ($produit['stock'] ?? 0); ?></span>
                                </p>
                                <div class="produit-card-actions">
                                    <a href="produits/modifier.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-edit">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="produits/supprimer.php?id=<?php echo $produit['id']; ?>"
                                        class="btn-card btn-delete"
                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

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
            document.querySelectorAll('.produit-card-linkable').forEach(function(card) {
                card.addEventListener('click', function(event) {
                    if (event.target.closest('a, button, input, select, textarea, form')) {
                        return;
                    }
                    var href = card.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
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
    </script>
    <?php include __DIR__ . '/../includes/admin_stock_alerte_popup.php'; ?>
    <?php include 'includes/footer.php'; ?>