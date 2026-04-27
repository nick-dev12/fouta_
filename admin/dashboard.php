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

$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$admin_show_catalogue = admin_can_gestion_boutique();
$categories = [];
$produits = [];
$derniers_produits_ajoutes = [];
if ($admin_show_catalogue) {
    $categories = get_all_categories();
    $produits = get_all_produits();
    if (admin_is_full_admin()) {
        $derniers_produits_ajoutes = get_derniers_produits_ajoutes(10);
    }
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
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
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
                <p class="dashboard-subtitle">Vue d’ensemble des commandes et de votre catalogue produits.</p>
            </div>
            <div class="header-actions">
                <button type="button" id="btn-install-pwa" class="btn-primary btn-secondary-style"
                    title="Installer l'application FOUTA POIDS LOURDS sur cet appareil" style="display: none;">
                    <i class="fas fa-download"></i> Installer l'application
                </button>
                <button type="button" id="btn-enable-notifications" class="btn-primary btn-secondary-style"
                    title="Recevoir des notifications push pour les nouvelles commandes">
                    <i class="fas fa-bell"></i> Activer les notifications
                </button>
                <!-- <a href="test-notification.php" class="btn-primary btn-secondary-style" title="Envoyer une notification de test sur cet ordinateur">
                    <i class="fas fa-paper-plane"></i> Test notification
                </a> -->
                <?php if (admin_can_zones_livraison()): ?>
                <a href="zones-livraison/index.php" class="btn-primary btn-secondary-style">
                    <i class="fas fa-truck"></i> Zones de livraison
                </a>
                <?php endif; ?>
                <?php if (admin_can_gestion_boutique()): ?>
                <a href="produits/ajouter.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Nouveau Produit
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
        // Récupérer les statistiques des commandes
        $total_commandes = count_commandes_by_statut();
        $commandes_perso_en_attente = count_commandes_personnalisees_by_statut('en_attente');
        $en_attente = count_commandes_by_statut('en_attente');
        $prise_en_charge = count_commandes_by_statut('prise_en_charge');
        $livraison_en_cours = count_commandes_by_statut('livraison_en_cours');
        ?>

        <!-- Statistiques des commandes -->
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

        <!-- Lien rapide vers les commandes -->
        <?php if ($en_attente > 0 || $prise_en_charge > 0): ?>
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
        <?php if (admin_is_full_admin()): ?>
        <section class="dashboard-recent-produits" aria-labelledby="recent-produits-heading">
            <div class="section-title section-title--dashboard">
                <div>
                    <h2 id="recent-produits-heading"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Derniers produits ajoutés</h2>
                    <p class="section-title-hint">Les ajouts les plus récents au catalogue (tous comptes confondus)</p>
                </div>
                <a href="produits/index.php" class="btn-primary btn-secondary-style"><i class="fas fa-list"></i> Liste produits</a>
            </div>
            <?php if (empty($derniers_produits_ajoutes)): ?>
                <p class="dashboard-recent-empty">Aucun produit enregistré pour le moment.</p>
            <?php else: ?>
                <div class="produits-grid produits-grid--recent">
                    <?php foreach ($derniers_produits_ajoutes as $dp): ?>
                        <?php
                        $statut_class = 'statut-actif';
                        if (($dp['statut'] ?? '') == 'inactif') {
                            $statut_class = 'statut-inactif';
                        } elseif (($dp['statut'] ?? '') == 'rupture_stock') {
                            $statut_class = 'statut-rupture';
                        }
                        $statut_label = ucfirst(str_replace('_', ' ', (string) ($dp['statut'] ?? '')));
                        $img_dp = $dp['image_principale'] ?? '';
                        $date_ajout = !empty($dp['date_creation']) ? date('d/m/Y H:i', strtotime($dp['date_creation'])) : '—';
                        $par = trim((string) ($dp['createur_prenom'] ?? '') . ' ' . (string) ($dp['createur_nom'] ?? ''));
                        ?>
                        <div class="produit-card produit-card-linkable produit-card--dashboard"
                            data-href="produits/modifier.php?id=<?php echo (int) $dp['id']; ?>">
                            <span class="statut-badge <?php echo $statut_class; ?>"><?php echo htmlspecialchars($statut_label); ?></span>
                            <div class="produit-card-media">
                                <?php if ($img_dp !== ''): ?>
                                <img src="/upload/<?php echo htmlspecialchars($img_dp); ?>"
                                    alt="<?php echo htmlspecialchars($dp['nom'] ?? ''); ?>" class="produit-card-image"
                                    onerror="this.src='/image/produit1.jpg'">
                                <?php else: ?>
                                <img src="/image/produit1.jpg" alt="" class="produit-card-image">
                                <?php endif; ?>
                            </div>
                            <div class="produit-card-body">
                                <h3 class="produit-card-nom"><?php echo htmlspecialchars($dp['nom'] ?? ''); ?></h3>
                                <p class="produit-card-categorie">
                                    <i class="fas fa-tag" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($dp['categorie_nom'] ?? 'Sans catégorie'); ?>
                                </p>
                                <p class="produit-card-ajout-meta">
                                    <span><i class="fas fa-calendar-plus" aria-hidden="true"></i> <?php echo htmlspecialchars($date_ajout); ?></span>
                                    <?php if ($par !== ''): ?>
                                    <span><i class="fas fa-user" aria-hidden="true"></i> <?php echo htmlspecialchars($par); ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="produit-card-prix">
                                    <span class="prix-montant"><?php echo number_format((float) ($dp['prix'] ?? 0), 0, ',', ' '); ?></span>
                                    <span class="prix-unite">FCFA</span>
                                    <?php if (!empty($dp['prix_promotion'])): ?>
                                        <span class="prix-promo">
                                            Promo <?php echo number_format((float) $dp['prix_promotion'], 0, ',', ' '); ?> FCFA
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="produit-card-stock">
                                    <i class="fas fa-cubes" aria-hidden="true"></i>
                                    Stock <span class="stock-value"><?php echo (int) ($dp['stock'] ?? 0); ?></span>
                                </p>
                                <div class="produit-card-actions">
                                    <a href="produits/modifier.php?id=<?php echo (int) $dp['id']; ?>" class="btn-card btn-edit">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="produits/supprimer.php?id=<?php echo (int) $dp['id']; ?>"
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
                    <a href="produits/ajouter.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter le premier produit
                    </a>
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
                        $statut_label = ucfirst(str_replace('_', ' ', $produit['statut']));
                        ?>
                        <div class="produit-card produit-card-linkable produit-card--dashboard"
                            data-href="produits/modifier.php?id=<?php echo (int) $produit['id']; ?>">
                            <span class="statut-badge <?php echo $statut_class; ?>"><?php echo $statut_label; ?></span>
                            <div class="produit-card-media">
                            <img src="/upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
                                alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-card-image"
                                onerror="this.src='/image/produit1.jpg'">
                            </div>
                            <div class="produit-card-body">
                                <h3 class="produit-card-nom"><?php echo htmlspecialchars($produit['nom']); ?></h3>
                                <p class="produit-card-categorie">
                                    <i class="fas fa-tag" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie'); ?>
                                </p>
                                <p class="produit-card-prix">
                                    <span class="prix-montant"><?php echo number_format($produit['prix'], 0, ',', ' '); ?></span>
                                    <span class="prix-unite">FCFA</span>
                                    <?php if ($produit['prix_promotion']): ?>
                                        <span class="prix-promo">
                                            Promo <?php echo number_format($produit['prix_promotion'], 0, ',', ' '); ?> FCFA
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="produit-card-stock">
                                    <i class="fas fa-cubes" aria-hidden="true"></i>
                                    Stock <span class="stock-value"><?php echo $produit['stock']; ?></span>
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
            var deferredPrompt;

            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                if (installBtn) installBtn.style.display = 'none';
            } else {
                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault();
                    deferredPrompt = e;
                    if (installBtn) installBtn.style.display = 'inline-flex';
                });

                if (installBtn) {
                    installBtn.addEventListener('click', function () {
                        if (!deferredPrompt) {
                            alert(
                                'L\'installation n\'est pas disponible. Essayez depuis Chrome ou Edge en mode HTTPS.'
                            );
                            return;
                        }
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then(function (choiceResult) {
                            if (choiceResult.outcome === 'accepted') {
                                installBtn.style.display = 'none';
                            }
                            deferredPrompt = null;
                        });
                    });
                }
            }
        });
    </script>
    <?php include 'includes/footer.php'; ?>