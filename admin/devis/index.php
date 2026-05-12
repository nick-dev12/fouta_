<?php
/**
 * Hub Bons de livraison & bons de retour B2B (Admin)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

if (isset($_GET['tab']) && $_GET['tab'] === 'devis') {
    header('Location: devis.php', true, 302);
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_bl_retours_b2b()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_zones_livraison.php';
require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_bons_retour.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';
$fiscal_tva_pourcent_devis_bl = fiscal_taux_tva_pourcent();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$zones_livraison = get_all_zones_livraison('actif');
$bl_tables_ok = bl_tables_available();
$bl_clients_list = $bl_tables_ok ? get_clients_b2b_avec_bl() : [];
$br_tables_ok = br_retour_tables_available();
$br_clients_list = $br_tables_ok ? get_clients_b2b_avec_bons_retour() : [];

$bl_erreur = $_SESSION['bl_erreur'] ?? null;
if (isset($_SESSION['bl_erreur'])) {
    unset($_SESSION['bl_erreur']);
}

$show_modal_bl = isset($_GET['modal']) && $_GET['modal'] === 'bl';
$tab_param = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
$tab_allowed = ['bl', 'br'];
$active_tab = in_array($tab_param, $tab_allowed, true) ? $tab_param : 'bl';

$bl_post = $_SESSION['bl_post'] ?? null;
if (isset($_SESSION['bl_post'])) {
    unset($_SESSION['bl_post']);
}
$bl_modal_err = $bl_erreur;
if ($show_modal_bl && $bl_modal_err) {
    $bl_erreur = null;
}

/** Valeurs re-affichées dans le modal BL (mêmes clés que le devis + date_bl / statut) */
$bp = is_array($bl_post) ? $bl_post : [];
$devis_page_has_alert = isset($_SESSION['success_message']) || !empty($bl_erreur);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bons de livraison &amp; retours — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-devis-compta-pages.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-devis-admin">
    <div class="content-header dashboard-hero page-devis-hero">
        <div class="dashboard-hero-text">
            <p class="dashboard-eyebrow">Espace commercial</p>
            <h1><i class="fas fa-truck-loading" aria-hidden="true"></i> Bons de livraison &amp; retours</h1>
            <p class="dashboard-subtitle">Gérez les livraisons B2B (BL) et les bons de retour clients professionnels.</p>
        </div>
    </div>

    <?php if ($devis_page_has_alert): ?>
    <div class="page-devis-alerts" role="region" aria-label="Messages">
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success page-devis-message">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($bl_erreur)): ?>
        <div class="message error page-devis-message">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($bl_erreur); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($devis_page_has_alert): ?>
    </div>
    <?php endif; ?>

    <?php
    $tab_bl_active = $active_tab === 'bl';
    $tab_br_active = $active_tab === 'br';
    ?>
    <section class="content-section page-devis-section" aria-label="Bons de livraison et retours">
        <div class="section-header section-header--tabs page-devis-tabs-wrap">
            <div class="admin-devis-bl-tabs" role="tablist" aria-label="Bons de livraison ou bons de retour">
                <button type="button" class="admin-tab admin-tab--bl <?php echo $tab_bl_active ? 'is-active' : ''; ?>" id="tab-btn-bl" role="tab" aria-selected="<?php echo $tab_bl_active ? 'true' : 'false'; ?>" aria-controls="panel-bl" data-tab="bl" <?php echo !$bl_tables_ok ? 'disabled title="Migration B2B requise"' : ''; ?>>
                    <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-truck-loading"></i></span>
                    <span class="admin-tab__txt">Bons de livraison (<?php echo $bl_tables_ok ? count($bl_clients_list) : 0; ?>)</span>
                </button>
                <button type="button" class="admin-tab admin-tab--br <?php echo $tab_br_active ? 'is-active' : ''; ?>" id="tab-btn-br" role="tab" aria-selected="<?php echo $tab_br_active ? 'true' : 'false'; ?>" aria-controls="panel-br" data-tab="br" <?php echo !$br_tables_ok ? 'disabled title="Exécutez migrations/run_create_bons_retour_tables.php"' : ''; ?>>
                    <span class="admin-tab__ic" aria-hidden="true"><i class="fas fa-undo"></i></span>
                    <span class="admin-tab__txt">Bons de retour (<?php echo $br_tables_ok ? count($br_clients_list) : 0; ?>)</span>
                </button>
            </div>
        </div>

        <div id="panel-bl" class="tab-panel-devis-bl <?php echo $tab_bl_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="tab-btn-bl" <?php echo $tab_bl_active ? '' : 'hidden'; ?>>
        <?php if (!$bl_tables_ok): ?>
            <p class="message error page-devis-message page-devis-b2b-migration"><i class="fas fa-database" aria-hidden="true"></i> Tables BL absentes : exécutez la migration <code>migrations/migration_admin_b2b_structure.sql</code>.</p>
        <?php else: ?>
        <?php if (!admin_is_restricted_admin_account()): ?>
        <div class="admin-devis-bl-panel-actions">
            <button type="button" class="btn-secondary" id="btn-nouveau-bl" aria-label="Créer un bon de livraison">
                <i class="fas fa-truck-loading"></i> Nouveau BL
            </button>
        </div>
        <?php endif; ?>
        <?php if (empty($bl_clients_list)): ?>
            <div class="bl-empty-state" role="status">
                <div class="bl-empty-state__visual" aria-hidden="true">
                    <span class="bl-empty-state__ring"></span>
                    <i class="fas fa-truck-loading"></i>
                </div>
                <h3 class="bl-empty-state__title">Aucun contact avec bon de livraison</h3>
                <p class="bl-empty-state__text">Créez un premier BL avec « Nouveau BL » : le client professionnel apparaîtra ici pour un suivi rapide.</p>
            </div>
        <?php else: ?>
            <?php
            $bl_nb_contacts = count($bl_clients_list);
            ?>
            <div class="bl-tab-surface">
                <header class="bl-contacts-hero">
                    <div class="bl-contacts-hero__icon-wrap" aria-hidden="true">
                        <i class="fas fa-people-group"></i>
                    </div>
                    <div class="bl-contacts-hero__copy">
                        <h2 class="bl-contacts-hero__title">Contacts &amp; livraisons B2B</h2>
                        <p class="bl-contacts-hero__lead">Clients professionnels ayant au moins un bon de livraison. Accédez à l’historique complet par entreprise.</p>
                    </div>
                    <div class="bl-contacts-hero__stat" title="Nombre de contacts listés">
                        <span class="bl-contacts-hero__stat-num"><?php echo (int) $bl_nb_contacts; ?></span>
                        <span class="bl-contacts-hero__stat-label">contact<?php echo $bl_nb_contacts > 1 ? 's' : ''; ?></span>
                    </div>
                </header>

                <div class="bl-contacts-grid" role="list">
                <?php foreach ($bl_clients_list as $cl): ?>
                    <?php
                    $cid = (int) $cl['id'];
                    $nb_bl = (int) ($cl['nb_bl'] ?? 0);
                    $contact_nom = trim(($cl['nom_contact'] ?? '') . ' ' . ($cl['prenom_contact'] ?? ''));
                    $rs = trim($cl['raison_sociale'] ?? '');
                    $initials = '?';
                    if ($rs !== '') {
                        $words = preg_split('/\s+/u', $rs, -1, PREG_SPLIT_NO_EMPTY);
                        if (count($words) >= 2) {
                            $initials = mb_strtoupper(
                                mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1),
                                'UTF-8'
                            );
                        } else {
                            $initials = mb_strtoupper(mb_substr($rs, 0, min(2, mb_strlen($rs, 'UTF-8')), 'UTF-8'), 'UTF-8');
                        }
                    }
                    $adr_short = '';
                    if (!empty($cl['adresse'])) {
                        $adr_short = mb_substr($cl['adresse'], 0, 110);
                        if (mb_strlen($cl['adresse'], 'UTF-8') > 110) {
                            $adr_short .= '…';
                        }
                    }
                    $last_bl = !empty($cl['dernier_bl_date'])
                        ? date('d/m/Y · H:i', strtotime($cl['dernier_bl_date']))
                        : '—';
                    ?>
                    <article class="bl-contact-card" role="listitem">
                        <div class="bl-contact-card__inner">
                            <div class="bl-contact-card__head">
                                <div class="bl-contact-card__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                                <div class="bl-contact-card__head-text">
                                    <h3 class="bl-contact-card__company"><?php echo htmlspecialchars($rs ?: '—'); ?></h3>
                                    <?php if ($contact_nom !== ''): ?>
                                        <p class="bl-contact-card__person">
                                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                                            <?php echo htmlspecialchars($contact_nom); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="bl-contact-card__pill">
                                    <i class="fas fa-file-invoice" aria-hidden="true"></i>
                                    <?php echo $nb_bl; ?> BL
                                </span>
                            </div>

                            <ul class="bl-contact-card__meta">
                                <li class="bl-contact-card__meta-row">
                                    <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-phone"></i></span>
                                    <span class="bl-contact-card__meta-val"><?php echo htmlspecialchars($cl['telephone'] ?? '—'); ?></span>
                                </li>
                                <li class="bl-contact-card__meta-row">
                                    <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                                    <span class="bl-contact-card__meta-val"><?php echo !empty($cl['email']) ? htmlspecialchars($cl['email']) : '—'; ?></span>
                                </li>
                                <?php if ($adr_short !== ''): ?>
                                <li class="bl-contact-card__meta-row bl-contact-card__meta-row--address">
                                    <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                                    <span class="bl-contact-card__meta-val"><?php echo htmlspecialchars($adr_short); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>

                            <div class="bl-contact-card__foot">
                                <div class="bl-contact-card__last">
                                    <span class="bl-contact-card__last-label">Dernier BL</span>
                                    <?php if (!empty($cl['dernier_bl_date'])): ?>
                                        <time class="bl-contact-card__last-date" datetime="<?php echo htmlspecialchars(date('c', strtotime($cl['dernier_bl_date']))); ?>"><?php echo htmlspecialchars($last_bl); ?></time>
                                    <?php else: ?>
                                        <span class="bl-contact-card__last-date">—</span>
                                    <?php endif; ?>
                                </div>
                                <a href="bl_par_client.php?id=<?php echo $cid; ?>" class="bl-contact-card__cta">
                                    <span>Voir les bons de livraison</span>
                                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        </div>

        <div id="panel-br" class="tab-panel-devis-bl <?php echo $tab_br_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="tab-btn-br" <?php echo $tab_br_active ? '' : 'hidden'; ?>>
        <?php if (!$br_tables_ok): ?>
            <p class="message error page-devis-message page-devis-b2b-migration"><i class="fas fa-database" aria-hidden="true"></i> Tables absentes : exécutez en ligne de commande <code>php migrations/run_create_bons_retour_tables.php</code> (prérequis : BL déjà migrés).</p>
        <?php elseif (empty($br_clients_list)): ?>
            <div class="empty-state page-devis-empty">
                <div class="page-devis-empty__ic" aria-hidden="true"><i class="fas fa-undo"></i></div>
                <h3>Aucun bon de retour</h3>
                <p>Depuis un bon de livraison ouvert, utilisez le bouton « Bon de retour » pour enregistrer une sortie de marchandise.</p>
            </div>
        <?php else: ?>
            <?php
            $br_nb_contacts = count($br_clients_list);
            ?>
            <div class="bl-tab-surface">
                <header class="bl-contacts-hero">
                    <div class="bl-contacts-hero__icon-wrap" aria-hidden="true">
                        <i class="fas fa-undo"></i>
                    </div>
                    <div class="bl-contacts-hero__copy">
                        <h2 class="bl-contacts-hero__title">Contacts &amp; bons de retour B2B</h2>
                        <p class="bl-contacts-hero__lead">Clients professionnels ayant au moins un bon de retour. Consultez l’historique complet par entreprise.</p>
                    </div>
                    <div class="bl-contacts-hero__stat" title="Nombre de contacts listés">
                        <span class="bl-contacts-hero__stat-num"><?php echo (int) $br_nb_contacts; ?></span>
                        <span class="bl-contacts-hero__stat-label">contact<?php echo $br_nb_contacts > 1 ? 's' : ''; ?></span>
                    </div>
                </header>

                <div class="bl-contacts-grid" role="list">
                <?php foreach ($br_clients_list as $cl): ?>
                    <?php
                    $cid = (int) $cl['id'];
                    $nb_br = (int) ($cl['nb_br'] ?? 0);
                    $contact_nom = trim(($cl['nom_contact'] ?? '') . ' ' . ($cl['prenom_contact'] ?? ''));
                    $rs = trim($cl['raison_sociale'] ?? '');
                    $initials = '?';
                    if ($rs !== '') {
                        $words = preg_split('/\s+/u', $rs, -1, PREG_SPLIT_NO_EMPTY);
                        if (count($words) >= 2) {
                            $initials = mb_strtoupper(
                                mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1),
                                'UTF-8'
                            );
                        } else {
                            $initials = mb_strtoupper(mb_substr($rs, 0, min(2, mb_strlen($rs, 'UTF-8')), 'UTF-8'), 'UTF-8');
                        }
                    }
                    $adr_short = '';
                    if (!empty($cl['adresse'])) {
                        $adr_short = mb_substr($cl['adresse'], 0, 110);
                        if (mb_strlen($cl['adresse'], 'UTF-8') > 110) {
                            $adr_short .= '…';
                        }
                    }
                    $last_br = !empty($cl['dernier_br_date'])
                        ? date('d/m/Y · H:i', strtotime($cl['dernier_br_date']))
                        : '—';
                    ?>
                    <article class="bl-contact-card" role="listitem">
                        <div class="bl-contact-card__inner">
                            <div class="bl-contact-card__head">
                                <div class="bl-contact-card__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                                <div class="bl-contact-card__head-text">
                                    <h3 class="bl-contact-card__company"><?php echo htmlspecialchars($rs ?: '—'); ?></h3>
                                    <?php if ($contact_nom !== ''): ?>
                                        <p class="bl-contact-card__person">
                                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                                            <?php echo htmlspecialchars($contact_nom); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="bl-contact-card__pill bl-contact-card__pill--br">
                                    <i class="fas fa-undo" aria-hidden="true"></i>
                                    <?php echo $nb_br; ?> BR
                                </span>
                            </div>

                            <ul class="bl-contact-card__meta">
                                <li class="bl-contact-card__meta-row">
                                    <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-phone"></i></span>
                                    <span class="bl-contact-card__meta-val"><?php echo htmlspecialchars($cl['telephone'] ?? '—'); ?></span>
                                </li>
                                <li class="bl-contact-card__meta-row">
                                    <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                                    <span class="bl-contact-card__meta-val"><?php echo !empty($cl['email']) ? htmlspecialchars($cl['email']) : '—'; ?></span>
                                </li>
                                <?php if ($adr_short !== ''): ?>
                                <li class="bl-contact-card__meta-row bl-contact-card__meta-row--address">
                                    <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                                    <span class="bl-contact-card__meta-val"><?php echo htmlspecialchars($adr_short); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>

                            <div class="bl-contact-card__foot">
                                <div class="bl-contact-card__last">
                                    <span class="bl-contact-card__last-label">Dernier BR</span>
                                    <?php if (!empty($cl['dernier_br_date'])): ?>
                                        <time class="bl-contact-card__last-date" datetime="<?php echo htmlspecialchars(date('c', strtotime($cl['dernier_br_date']))); ?>"><?php echo htmlspecialchars($last_br); ?></time>
                                    <?php else: ?>
                                        <span class="bl-contact-card__last-date">—</span>
                                    <?php endif; ?>
                                </div>
                                <a href="br_par_client.php?id=<?php echo $cid; ?>" class="bl-contact-card__cta">
                                    <span>Voir les bons de retour</span>
                                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </section>
    </div>

    <?php if ($bl_tables_ok): ?>
    <!-- Modal BL : mêmes champs et même structure que le modal devis (+ date BL + statut) -->
    <div id="modal-bl" class="modal-commande-manuelle <?php echo $show_modal_bl ? 'modal-open' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="modal-bl-title">
        <div class="modal-commande-manuelle-backdrop" id="modal-bl-backdrop"></div>
        <div class="modal-commande-manuelle-content">
            <div class="modal-commande-manuelle-header">
                <h2 id="modal-bl-title"><i class="fas fa-truck-loading"></i> Nouveau bon de livraison</h2>
                <button type="button" class="modal-commande-manuelle-close" id="modal-bl-close" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-commande-manuelle-body">
                <?php if ($bl_modal_err): ?>
                    <div class="message error modal-commande-erreur">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($bl_modal_err); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="bl_enregistrer.php" id="form-bl">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <input type="hidden" name="user_id" id="user_id_bl" value="<?php echo htmlspecialchars($bp['user_id'] ?? ''); ?>">
                    <div class="form-commande-manuelle-grid">
                        <div class="form-commande-manuelle-col form-col-articles">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-search"></i>
                                    <h3>Rechercher un produit</h3>
                                </div>
                                <div class="form-group search-group">
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-produit-bl" placeholder="Tapez le nom du produit..." autocomplete="off">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-loading-bl" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <div id="search-produit-results-bl" class="search-produit-results" role="listbox" aria-hidden="true"></div>
                                </div>
                                <p class="form-hint"><i class="fas fa-info-circle"></i> Tapez au moins 1 caractère ou laissez vide pour afficher tous les articles.</p>
                            </div>

                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h3>Produits du devis</h3>
                                    <span class="lignes-count" id="lignes-count-bl">0 article(s)</span>
                                </div>
                                <div id="lignes-commande-bl" class="lignes-commande lignes-commande-modal-wrap">
                                    <div class="ligne-commande-head ligne-commande-head-bl" id="lignes-head-bl" hidden>
                                        <span class="lch-head-cell">Produit</span>
                                        <span class="lch-head-cell">Quantité</span>
                                        <span class="lch-head-cell">prix FCFA</span>
                                        <span class="lch-head-cell">promo FCFA</span>
                                        <span class="lch-head-cell lch-head-actions" aria-hidden="true"></span>
                                    </div>
                                    <div class="lignes-empty" id="lignes-empty-bl">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun produit ajouté. Utilisez la recherche ci-dessus.</p>
                                    </div>
                                </div>
                                <div class="modal-tva-option" role="group" aria-labelledby="modal-tva-bl-title">
                                    <input type="hidden" name="inclure_tva" value="0">
                                    <label class="modal-tva-option__label" for="inclure_tva_bl">
                                        <span class="modal-tva-option__inner">
                                            <span class="modal-tva-option__glow" aria-hidden="true"></span>
                                            <span class="modal-tva-option__leading">
                                                <span class="modal-tva-option__icon" aria-hidden="true"><i class="fas fa-percent"></i></span>
                                                <span class="modal-tva-option__title" id="modal-tva-bl-title">Inclure la TVA</span>
                                            </span>
                                            <span class="modal-tva-option__toggle">
                                                <input type="checkbox" name="inclure_tva" value="1" id="inclure_tva_bl" class="modal-tva-option__checkbox"
                                                    <?php echo (is_array($bp) && !empty($bp['inclure_tva'])) ? 'checked' : ''; ?>>
                                                <span class="modal-tva-option__track" aria-hidden="true"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-commande-manuelle-col form-col-client">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-user"></i>
                                    <h3>Informations client</h3>
                                </div>
                                <div class="form-group search-group" style="position:relative;">
                                    <label for="search-client-bl">Rechercher un client</label>
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-client-bl" placeholder="Nom, téléphone ou email..." autocomplete="off">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-client-loading-bl" style="visibility:hidden;"><i class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <div id="search-client-results-bl" class="search-produit-results" role="listbox" aria-hidden="true" style="position:absolute; left:0; right:0; top:100%; z-index:100;"></div>
                                </div>
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label for="client_nom_bl">Nom <span class="required">*</span></label>
                                        <input type="text" id="client_nom_bl" name="client_nom" required
                                            value="<?php echo htmlspecialchars($bp['client_nom'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="client_prenom_bl">Prénom <span class="optional">(optionnel)</span></label>
                                        <input type="text" id="client_prenom_bl" name="client_prenom"
                                            value="<?php echo htmlspecialchars($bp['client_prenom'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="client_telephone_bl">Téléphone <span class="required">*</span></label>
                                    <input type="tel" id="client_telephone_bl" name="client_telephone" required
                                        placeholder="Ex: 07 12 34 56 78"
                                        value="<?php echo htmlspecialchars($bp['client_telephone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="client_email_bl">Email <span class="optional">(optionnel)</span></label>
                                    <input type="email" id="client_email_bl" name="client_email"
                                        value="<?php echo htmlspecialchars($bp['client_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="adresse_client_bl">Adresse du client <span class="optional">(optionnel)</span></label>
                                    <textarea id="adresse_client_bl" name="adresse_client" rows="2" placeholder="Siège social, rue, complément d’adresse…"><?php echo htmlspecialchars($bp['adresse_client'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="zone_livraison_id_bl"><i class="fas fa-map-marker-alt"></i> Adresse de livraison <span class="optional">(optionnel)</span></label>
                                    <select id="zone_livraison_id_bl" name="zone_livraison_id">
                                        <option value="">— Sélectionnez une adresse —</option>
                                        <?php foreach ($zones_livraison as $z): ?>
                                        <option value="<?php echo (int) $z['id']; ?>"
                                            data-adresse="<?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>"
                                            data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                            <?php echo (isset($bp['zone_livraison_id']) && (string) $bp['zone_livraison_id'] === (string) $z['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                            (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="custom" <?php echo (isset($bp['zone_livraison_id']) && $bp['zone_livraison_id'] === 'custom') ? 'selected' : ''; ?>>— Adresse personnalisée —</option>
                                    </select>
                                    <div id="adresse-custom-wrap-bl" class="adresse-custom-wrap" style="display:none; margin-top:10px;">
                                        <textarea id="adresse_livraison_ta_bl" rows="3" placeholder="Saisissez l'adresse complète"><?php echo htmlspecialchars($bp['adresse_livraison'] ?? ''); ?></textarea>
                                    </div>
                                    <div id="adresse-zone-display-bl" class="adresse-zone-display" style="display:none; margin-top:8px; padding:10px; background:#f5f5f4; border-radius:8px;"></div>
                                    <input type="hidden" name="adresse_livraison" id="adresse_livraison_bl" value="">
                                    <input type="hidden" name="frais_livraison" id="frais_livraison_bl" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="notes_bl">Notes</label>
                                    <textarea id="notes_bl" name="notes" rows="2" placeholder="Instructions supplémentaires..."><?php echo htmlspecialchars($bp['notes'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label for="date_bl">Date du BL</label>
                                        <input type="date" name="date_bl" id="date_bl" value="<?php echo htmlspecialchars($bp['date_bl'] ?? date('Y-m-d')); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="statut_bl_sel">Statut du BL</label>
                                        <select name="statut" id="statut_bl_sel">
                                            <?php
                                            $sb = $bp['statut'] ?? 'brouillon';
                                            if (!in_array($sb, ['brouillon', 'valide'], true)) {
                                                $sb = 'brouillon';
                                            }
                                            ?>
                                            <option value="brouillon" <?php echo $sb === 'brouillon' ? 'selected' : ''; ?>>Brouillon</option>
                                            <option value="valide" <?php echo $sb === 'valide' ? 'selected' : ''; ?>>Validé (comptabilité)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="commande-manuelle-recap">
                                    <div class="recap-line">
                                        <span>Sous-total produits (HT)</span>
                                        <span id="recap-sous-total-bl">0 FCFA</span>
                                    </div>
                                    <div class="recap-line">
                                        <span>Frais de livraison (HT)</span>
                                        <span id="recap-frais-bl">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-tva-line-bl" id="recap-tva-line-bl" style="display:none;">
                                        <span>TVA (<span id="recap-tva-pct-bl"><?php echo htmlspecialchars((string) $fiscal_tva_pourcent_devis_bl); ?></span> %)</span>
                                        <span id="recap-tva-montant-bl">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-total">
                                        <span id="recap-total-label-bl">Total</span>
                                        <span id="recap-total-bl">0 FCFA</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-commande-manuelle-actions">
                        <button type="button" class="btn-secondary" id="modal-bl-cancel">Annuler</button>
                        <button type="submit" class="btn-primary btn-submit-commande" name="submit_bl">
                            <i class="fas fa-check"></i> Enregistrer le BL
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include __DIR__ . '/../../includes/admin_stock_alerte_popup.php'; ?>

    <?php include '../includes/footer.php'; ?>

    <script>
    (function() {
        var FISCAL_TVA_PCT = <?php echo json_encode((float) $fiscal_tva_pourcent_devis_bl); ?>;
        var tabBl = document.getElementById('tab-btn-bl');
        var tabBr = document.getElementById('tab-btn-br');
        var panelBl = document.getElementById('panel-bl');
        var panelBr = document.getElementById('panel-br');

        function showTab(which) {
            if (which === 'bl' && tabBl && tabBl.disabled) {
                return;
            }
            if (which === 'br' && tabBr && tabBr.disabled) {
                return;
            }
            var map = [
                ['bl', tabBl, panelBl],
                ['br', tabBr, panelBr],
            ];
            for (var i = 0; i < map.length; i++) {
                var id = map[i][0];
                var btn = map[i][1];
                var panel = map[i][2];
                if (!panel) {
                    continue;
                }
                var on = id === which;
                if (on) {
                    panel.removeAttribute('hidden');
                    panel.classList.add('is-active');
                } else {
                    panel.setAttribute('hidden', 'hidden');
                    panel.classList.remove('is-active');
                }
                if (btn && !btn.disabled) {
                    if (on) {
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-selected', 'true');
                    } else {
                        btn.classList.remove('is-active');
                        btn.setAttribute('aria-selected', 'false');
                    }
                }
            }
        }

        if (tabBl) tabBl.addEventListener('click', function() { if (!tabBl.disabled) showTab('bl'); });
        if (tabBr) tabBr.addEventListener('click', function() { if (!tabBr.disabled) showTab('br'); });

        var modalBl = document.getElementById('modal-bl');
        var btnOpenBl = document.getElementById('btn-nouveau-bl');
        var btnCloseBl = document.getElementById('modal-bl-close');
        var btnCancelBl = document.getElementById('modal-bl-cancel');
        var backdropBl = document.getElementById('modal-bl-backdrop');

        function openModalBl() {
            showTab('bl');
            if (modalBl) {
                modalBl.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            }
        }
        function closeModalBl() {
            if (modalBl) {
                modalBl.classList.remove('modal-open');
                document.body.style.overflow = '';
            }
        }

        if (btnOpenBl) btnOpenBl.addEventListener('click', openModalBl);
        if (btnCloseBl) btnCloseBl.addEventListener('click', closeModalBl);
        if (btnCancelBl) btnCancelBl.addEventListener('click', closeModalBl);
        if (backdropBl) backdropBl.addEventListener('click', closeModalBl);

        if (modalBl && modalBl.classList.contains('modal-open')) {
            document.body.style.overflow = 'hidden';
            showTab('bl');
        }

        /* ——— Même logique que le modal devis (recherche produits, lignes, zone, client, recap) ——— */
        var searchInputBl = document.getElementById('search-produit-bl');
        var searchResultsBl = document.getElementById('search-produit-results-bl');
        var searchLoadingBl = document.getElementById('search-loading-bl');
        var lignesContainerBl = document.getElementById('lignes-commande-bl');
        var lignesEmptyBl = document.getElementById('lignes-empty-bl');
        var lignesCountBl = document.getElementById('lignes-count-bl');
        var ligneIndexBl = 0;
        var ajaxUrlBl = 'ajax_search_produits.php';

        function updateLignesUIBl() {
            var items = lignesContainerBl ? lignesContainerBl.querySelectorAll('.ligne-commande-item') : [];
            var n = items.length;
            if (lignesEmptyBl) lignesEmptyBl.style.display = n === 0 ? 'flex' : 'none';
            if (lignesCountBl) lignesCountBl.textContent = n + ' article(s)';
            var headBl = document.getElementById('lignes-head-bl');
            if (headBl) {
                if (n > 0) {
                    headBl.removeAttribute('hidden');
                } else {
                    headBl.setAttribute('hidden', 'hidden');
                }
            }
        }

        function addLigneBl(produit) {
            var prix = parseFloat(produit.prix) || 0;
            var prixPromo = produit.prix_promotion && parseFloat(produit.prix_promotion) > 0 ? parseFloat(produit.prix_promotion) : '';
            var nom = (produit.nom || '');
            var idx = ligneIndexBl++;
            var div = document.createElement('div');
            div.className = 'ligne-commande-item ligne-commande-item-bl';
            div.dataset.produitId = produit.id;
            div.innerHTML =
                '<div class="ligne-bl-cell">' +
                    '<input type="hidden" name="lignes[' + idx + '][produit_id]" value="' + produit.id + '">' +
                    '<span class="ligne-bl-label">Désignation</span>' +
                    '<input type="text" name="lignes[' + idx + '][nom_produit]" value="' + (nom.replace(/"/g, '&quot;')) + '" placeholder="Nom du produit" class="ligne-nom-input" aria-label="Désignation du produit">' +
                '</div>' +
                '<div class="ligne-bl-cell">' +
                    '<span class="ligne-bl-label">Quantité</span>' +
                    '<input type="number" name="lignes[' + idx + '][quantite]" value="1" min="1" max="' + (produit.stock_dispo || produit.stock || 999) + '" class="ligne-qte" aria-label="Quantité">' +
                '</div>' +
                '<div class="ligne-bl-cell ligne-bl-cell-prix">' +
                    '<span class="ligne-bl-label">Prix unitaire</span>' +
                    '<div class="ligne-bl-prix-row">' +
                        '<input type="number" name="lignes[' + idx + '][prix_unitaire]" value="' + (prixPromo || prix) + '" min="0" step="0.01" class="ligne-prix" aria-label="Prix unitaire en FCFA">' +
                        '<span class="ligne-unit-fcfa">FCFA</span>' +
                    '</div>' +
                '</div>' +
                '<div class="ligne-bl-cell ligne-bl-cell-prix">' +
                    '<span class="ligne-bl-label">Prix promo</span>' +
                    '<div class="ligne-bl-prix-row">' +
                        '<input type="number" name="lignes[' + idx + '][prix_promotion]" value="' + (prixPromo || '') + '" min="0" step="0.01" placeholder="Optionnel" class="ligne-prix-promo" aria-label="Prix promotionnel en FCFA">' +
                        '<span class="ligne-unit-fcfa">FCFA</span>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="ligne-remove" aria-label="Retirer la ligne"><i class="fas fa-trash"></i></button>';
            if (lignesEmptyBl) lignesEmptyBl.style.display = 'none';
            div.querySelector('.ligne-remove').addEventListener('click', function() {
                div.remove();
                updateLignesUIBl();
                updateRecapBl();
            });
            lignesContainerBl.appendChild(div);
            updateLignesUIBl();
            updateRecapBl();
        }

        function doSearchBl(q) {
            if (searchLoadingBl) searchLoadingBl.style.visibility = 'visible';
            fetch(ajaxUrlBl + '?q=' + encodeURIComponent(q) + '&limit=25')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var items = data.items || [];
                    searchResultsBl.innerHTML = '';
                    if (items.length === 0) {
                        searchResultsBl.innerHTML = '<div class="search-no-results"><i class="fas fa-box-open"></i> Aucun produit trouvé.</div>';
                    } else {
                        items.forEach(function(p) {
                            var el = document.createElement('div');
                            el.className = 'search-result-item';
                            el.setAttribute('role', 'option');
                            el.setAttribute('tabindex', '0');
                            var stock = p.stock_dispo || p.stock || 0;
                            var prix = parseFloat(p.prix) || 0;
                            el.innerHTML = '<span class="sr-nom">' + (p.nom || '') + '</span>' +
                                '<span class="sr-meta">' + (p.categorie_nom || '') + ' &bull; Stock: ' + stock + ' &bull; ' + prix + ' FCFA</span>';
                            el.addEventListener('mousedown', function(ev) {
                                ev.preventDefault();
                                addLigneBl(p);
                                searchInputBl.value = '';
                                searchResultsBl.innerHTML = '';
                                searchResultsBl.setAttribute('aria-hidden', 'true');
                            });
                            el.addEventListener('keydown', function(ev) {
                                if (ev.key === 'Enter' || ev.key === ' ') {
                                    ev.preventDefault();
                                    addLigneBl(p);
                                    searchInputBl.value = '';
                                    searchResultsBl.innerHTML = '';
                                    searchResultsBl.setAttribute('aria-hidden', 'true');
                                }
                            });
                            searchResultsBl.appendChild(el);
                        });
                    }
                    searchResultsBl.setAttribute('aria-hidden', 'false');
                })
                .catch(function() {
                    searchResultsBl.innerHTML = '<div class="search-no-results"><i class="fas fa-exclamation-triangle"></i> Erreur de recherche.</div>';
                })
                .finally(function() {
                    if (searchLoadingBl) searchLoadingBl.style.visibility = 'hidden';
                });
        }

        var zoneSelectBl = document.getElementById('zone_livraison_id_bl');
        var adresseCustomWrapBl = document.getElementById('adresse-custom-wrap-bl');
        var adresseZoneDisplayBl = document.getElementById('adresse-zone-display-bl');
        var adresseLivraisonBl = document.getElementById('adresse_livraison_bl');
        var adresseTaBl = document.getElementById('adresse_livraison_ta_bl');
        var fraisInputBl = document.getElementById('frais_livraison_bl');
        var recapSousTotalBl = document.getElementById('recap-sous-total-bl');
        var recapFraisBl = document.getElementById('recap-frais-bl');
        var recapTotalBl = document.getElementById('recap-total-bl');
        var recapTotalLabelBl = document.getElementById('recap-total-label-bl');
        var recapTvaLineBl = document.getElementById('recap-tva-line-bl');
        var recapTvaMontantBl = document.getElementById('recap-tva-montant-bl');
        var inclureTvaBl = document.getElementById('inclure_tva_bl');
        var formBl = document.getElementById('form-bl');

        function formatNumberBl(n) {
            return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function getSousTotalBl() {
            var total = 0;
            var items = lignesContainerBl ? lignesContainerBl.querySelectorAll('.ligne-commande-item') : [];
            items.forEach(function(row) {
                var qte = parseFloat(row.querySelector('.ligne-qte').value) || 0;
                var prix = parseFloat(row.querySelector('.ligne-prix').value) || 0;
                var promo = row.querySelector('.ligne-prix-promo');
                var p = promo && promo.value && parseFloat(promo.value) > 0 ? parseFloat(promo.value) : prix;
                total += p * qte;
            });
            return total;
        }

        function getFraisLivraisonBl() {
            if (!zoneSelectBl || zoneSelectBl.value === '' || zoneSelectBl.value === 'custom') return 0;
            var opt = zoneSelectBl.options[zoneSelectBl.selectedIndex];
            return opt && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
        }

        function updateRecapBl() {
            var sousTotal = getSousTotalBl();
            var frais = getFraisLivraisonBl();
            var netHt = sousTotal + frais;
            var tvaOn = inclureTvaBl && inclureTvaBl.checked;
            var tvaMontant = 0;
            var totalAff = netHt;
            if (tvaOn) {
                tvaMontant = Math.round(netHt * (FISCAL_TVA_PCT / 100));
                totalAff = Math.round(netHt + tvaMontant);
                if (recapTvaLineBl) recapTvaLineBl.style.display = '';
                if (recapTvaMontantBl) recapTvaMontantBl.textContent = formatNumberBl(tvaMontant) + ' FCFA';
                if (recapTotalLabelBl) recapTotalLabelBl.textContent = 'Total TTC';
            } else {
                if (recapTvaLineBl) recapTvaLineBl.style.display = 'none';
                if (recapTotalLabelBl) recapTotalLabelBl.textContent = 'Total';
            }
            if (recapSousTotalBl) recapSousTotalBl.textContent = formatNumberBl(sousTotal) + ' FCFA';
            if (recapFraisBl) recapFraisBl.textContent = formatNumberBl(frais) + ' FCFA';
            if (recapTotalBl) recapTotalBl.textContent = formatNumberBl(totalAff) + ' FCFA';
            if (fraisInputBl) fraisInputBl.value = frais;
        }

        function onZoneChangeBl() {
            var val = zoneSelectBl ? zoneSelectBl.value : '';
            if (val === 'custom') {
                if (adresseCustomWrapBl) adresseCustomWrapBl.style.display = 'block';
                if (adresseZoneDisplayBl) adresseZoneDisplayBl.style.display = 'none';
                if (adresseLivraisonBl) adresseLivraisonBl.value = '';
            } else if (val !== '') {
                var opt = zoneSelectBl.options[zoneSelectBl.selectedIndex];
                var adr = opt && opt.dataset.adresse ? opt.dataset.adresse : '';
                if (adresseLivraisonBl) adresseLivraisonBl.value = adr;
                if (adresseCustomWrapBl) adresseCustomWrapBl.style.display = 'none';
                if (adresseZoneDisplayBl) {
                    adresseZoneDisplayBl.textContent = adr;
                    adresseZoneDisplayBl.style.display = 'block';
                }
            } else {
                if (adresseCustomWrapBl) adresseCustomWrapBl.style.display = 'none';
                if (adresseZoneDisplayBl) adresseZoneDisplayBl.style.display = 'none';
                if (adresseLivraisonBl) adresseLivraisonBl.value = '';
            }
            updateRecapBl();
        }

        if (zoneSelectBl) zoneSelectBl.addEventListener('change', onZoneChangeBl);

        if (inclureTvaBl) inclureTvaBl.addEventListener('change', updateRecapBl);

        if (lignesContainerBl) {
            lignesContainerBl.addEventListener('input', function(ev) {
                if (ev.target.classList.contains('ligne-qte') || ev.target.classList.contains('ligne-prix') || ev.target.classList.contains('ligne-prix-promo')) {
                    updateRecapBl();
                }
            });
        }

        if (formBl) {
            formBl.addEventListener('submit', function(ev) {
                var zvb = zoneSelectBl ? zoneSelectBl.value : '';
                if (zvb === 'custom' && adresseTaBl && adresseLivraisonBl) {
                    adresseLivraisonBl.value = adresseTaBl.value.trim();
                } else if (zvb && zvb !== 'custom' && zoneSelectBl && adresseLivraisonBl) {
                    var optB = zoneSelectBl.options[zoneSelectBl.selectedIndex];
                    adresseLivraisonBl.value = optB && optB.dataset.adresse ? optB.dataset.adresse : '';
                }
            });
        }

        var searchTimeoutBl;
        if (searchInputBl && searchResultsBl) {
            searchInputBl.addEventListener('input', function() {
                clearTimeout(searchTimeoutBl);
                var q = searchInputBl.value.trim();
                searchTimeoutBl = setTimeout(function() { doSearchBl(q); }, 250);
            });
            searchInputBl.addEventListener('focus', function() {
                var q = searchInputBl.value.trim();
                if (searchResultsBl.getAttribute('aria-hidden') === 'true' || searchResultsBl.innerHTML === '') {
                    doSearchBl(q);
                }
            });
            searchInputBl.addEventListener('blur', function() {
                setTimeout(function() {
                    if (!searchResultsBl.contains(document.activeElement)) {
                        searchResultsBl.innerHTML = '';
                        searchResultsBl.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchResultsBl.addEventListener('mousedown', function(ev) { ev.preventDefault(); });
        }

        var searchClientInputBl = document.getElementById('search-client-bl');
        var searchClientResultsBl = document.getElementById('search-client-results-bl');
        var searchClientLoadingBl = document.getElementById('search-client-loading-bl');
        var clientNomInputBl = document.getElementById('client_nom_bl');
        var clientPrenomInputBl = document.getElementById('client_prenom_bl');
        var clientTelInputBl = document.getElementById('client_telephone_bl');
        var clientEmailInputBl = document.getElementById('client_email_bl');
        var userIdInputBl = document.getElementById('user_id_bl');
        var clientSearchTimeoutBl;
        if (searchClientInputBl && searchClientResultsBl && clientNomInputBl && clientPrenomInputBl && clientTelInputBl) {
            function doClientSearchBl(q) {
                if (q.length < 1) {
                    searchClientResultsBl.innerHTML = '';
                    searchClientResultsBl.setAttribute('aria-hidden', 'true');
                    return;
                }
                if (searchClientLoadingBl) searchClientLoadingBl.style.visibility = 'visible';
                fetch('ajax_search_clients.php?q=' + encodeURIComponent(q) + '&limit=15')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        searchClientResultsBl.innerHTML = '';
                        if (data.length === 0) {
                            searchClientResultsBl.innerHTML = '<div class="search-no-results">Aucun client trouvé.</div>';
                        } else {
                            data.forEach(function(c) {
                                var el = document.createElement('div');
                                el.className = 'search-result-item';
                                el.setAttribute('role', 'option');
                                el.innerHTML = '<span class="sr-nom">' + (c.nom_complet || '') + '</span>' +
                                    '<span class="sr-meta">' + (c.telephone || '') + (c.email ? ' &bull; ' + c.email : '') + '</span>';
                                el.addEventListener('mousedown', function(ev) {
                                    ev.preventDefault();
                                    clientNomInputBl.value = c.nom || '';
                                    clientPrenomInputBl.value = c.prenom || '';
                                    clientTelInputBl.value = c.telephone || '';
                                    if (clientEmailInputBl) clientEmailInputBl.value = c.email || '';
                                    if (userIdInputBl) userIdInputBl.value = (c.source === 'user') ? c.id : '';
                                    searchClientInputBl.value = '';
                                    searchClientResultsBl.innerHTML = '';
                                    searchClientResultsBl.setAttribute('aria-hidden', 'true');
                                });
                                searchClientResultsBl.appendChild(el);
                            });
                        }
                        searchClientResultsBl.setAttribute('aria-hidden', 'false');
                    })
                    .catch(function() {
                        searchClientResultsBl.innerHTML = '<div class="search-no-results">Erreur de recherche.</div>';
                    })
                    .finally(function() {
                        if (searchClientLoadingBl) searchClientLoadingBl.style.visibility = 'hidden';
                    });
            }
            searchClientInputBl.addEventListener('input', function() {
                clearTimeout(clientSearchTimeoutBl);
                var q = searchClientInputBl.value.trim();
                clientSearchTimeoutBl = setTimeout(function() { doClientSearchBl(q); }, 300);
            });
            searchClientInputBl.addEventListener('focus', function() {
                var q = searchClientInputBl.value.trim();
                if (q.length >= 1) doClientSearchBl(q);
            });
            searchClientInputBl.addEventListener('blur', function() {
                setTimeout(function() {
                    if (!searchClientResultsBl.contains(document.activeElement)) {
                        searchClientResultsBl.innerHTML = '';
                        searchClientResultsBl.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchClientResultsBl.addEventListener('mousedown', function(ev) { ev.preventDefault(); });
        }

        updateLignesUIBl();
        if (modalBl && modalBl.classList.contains('modal-open') && zoneSelectBl && zoneSelectBl.value) {
            onZoneChangeBl();
        }
        updateRecapBl();
    })();
    </script>
</body>
</html>
