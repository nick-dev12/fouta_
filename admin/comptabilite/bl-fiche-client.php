<?php
/**
 * Fiche client B2B — BL + facturation mensuelle HT (comptabilité)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_comptabilite()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_clients_b2b.php';
require_once __DIR__ . '/../../models/model_factures_mensuelles.php';

if (!bl_tables_available()) {
    header('Location: index.php?tab=bl');
    exit;
}

$client_b2b_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($client_b2b_id <= 0) {
    header('Location: index.php?tab=bl');
    exit;
}

$client = get_client_b2b_by_id($client_b2b_id);
if (!$client) {
    header('Location: index.php?tab=bl');
    exit;
}

$bl_list = get_all_bl_for_client_b2b($client_b2b_id);
$raison = $client['raison_sociale'] ?? '';
$contact_nom = trim(($client['nom_contact'] ?? '') . ' ' . ($client['prenom_contact'] ?? ''));

$initials = '?';
if ($raison !== '') {
    $words = preg_split('/\s+/u', $raison, -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) >= 2) {
        $initials = mb_strtoupper(
            mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1),
            'UTF-8'
        );
    } else {
        $initials = mb_strtoupper(mb_substr($raison, 0, min(2, mb_strlen($raison, 'UTF-8')), 'UTF-8'), 'UTF-8');
    }
}

$nb_bl = count($bl_list);

$fm_tables_ok = factures_mensuelles_table_ok();
$nb_bl_a_facturer = $fm_tables_ok ? count(get_bl_valides_non_factures($client_b2b_id)) : 0;
$fm_mois_courant = $fm_tables_ok ? get_facture_mensuelle_mois_courant($client_b2b_id) : false;
$fm_compte_bl = $fm_tables_ok ? facture_mensuelle_compte_bl_client($client_b2b_id) : null;

$fm_erreur = $_SESSION['fm_erreur'] ?? null;
if (isset($_SESSION['fm_erreur'])) {
    unset($_SESSION['fm_erreur']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bons de livraison — <?php echo htmlspecialchars($raison); ?> — Comptabilité</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-truck-loading" aria-hidden="true"></i> Bons de livraison</h1>
            <p class="bl-page-header__sub">Client professionnel — suivi comptable &amp; facture mensuelle HT</p>
        </div>
        <div class="header-actions bl-page-header__actions">
            <a href="index.php?tab=bl" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour comptabilité (BL)</a>
        </div>
    </div>

    <?php if (!empty($fm_erreur)): ?>
        <div class="message error" style="max-width:1100px;margin:0 auto 16px;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($fm_erreur); ?></span>
        </div>
    <?php endif; ?>

    <section class="content-section bl-detail-page">
        <div class="bl-tab-surface">
            <header class="bl-client-banner" aria-labelledby="bl-client-banner-title">
                <div class="bl-client-banner__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                <div class="bl-client-banner__body">
                    <h2 id="bl-client-banner-title" class="bl-client-banner__title"><?php echo htmlspecialchars($raison ?: '—'); ?></h2>
                    <?php if ($contact_nom !== ''): ?>
                        <p class="bl-client-banner__contact">
                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                            <?php echo htmlspecialchars($contact_nom); ?>
                        </p>
                    <?php endif; ?>
                    <ul class="bl-client-banner__meta">
                        <li>
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-phone"></i></span>
                            <span><?php echo htmlspecialchars($client['telephone'] ?? '—'); ?></span>
                        </li>
                        <li>
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                            <span><?php echo !empty($client['email']) ? htmlspecialchars($client['email']) : '—'; ?></span>
                        </li>
                        <?php if (!empty($client['adresse'])): ?>
                        <li class="bl-client-banner__meta--full">
                            <span class="bl-client-banner__meta-ic" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                            <span><?php echo nl2br(htmlspecialchars($client['adresse'])); ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="bl-client-banner__stat">
                    <span class="bl-client-banner__stat-num"><?php echo (int) $nb_bl; ?></span>
                    <span class="bl-client-banner__stat-label">BL enregistré<?php echo $nb_bl > 1 ? 's' : ''; ?></span>
                </div>
            </header>

            <?php if ($fm_tables_ok): ?>
                <div class="bl-facture-bar">
                    <div class="bl-facture-bar__text">
                        <strong><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Facturation HT (mois en cours)</strong>
                        <span class="bl-facture-bar__meta">
                            <?php echo (int) $nb_bl_a_facturer; ?> BL validé<?php echo $nb_bl_a_facturer > 1 ? 's' : ''; ?> à inclure ·
                            regroupe les produits et montants des bons de livraison non encore facturés.
                        </span>
                    </div>
                    <div class="bl-facture-bar__actions">
                        <?php if (!empty($fm_mois_courant)): ?>
                            <a href="../devis/facture_mensuelle.php?id=<?php echo (int) $fm_mois_courant['id']; ?>" class="btn-secondary"><i class="fas fa-eye" aria-hidden="true"></i> Voir la facture du mois</a>
                        <?php endif; ?>
                        <a href="../devis/facture_mensuelle_generer.php?client_b2b_id=<?php echo (int) $client_b2b_id; ?>" class="btn-primary"><i class="fas fa-magic" aria-hidden="true"></i> Générer / mettre à jour la facture</a>
                    </div>
                </div>
                <?php if ($nb_bl_a_facturer === 0 && is_array($fm_compte_bl) && ($fm_compte_bl['eligible'] ?? 0) > 0 && ($fm_compte_bl['sans_lien'] ?? 0) === 0): ?>
                    <p class="form-hint bl-facture-hint" style="margin-top:12px;">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        Les <?php echo (int) $fm_compte_bl['eligible']; ?> bon(s) validés (comptabilité) sont <strong>déjà inclus dans une facture mensuelle</strong> (un BL ne peut figurer qu’une fois). Ouvrez la facture via « Voir la facture » ou l’onglet Comptabilité — il n’y a rien à ajouter tant qu’aucun nouveau BL n’est validé.
                    </p>
                <?php elseif ($nb_bl_a_facturer === 0 && is_array($fm_compte_bl) && ($fm_compte_bl['eligible'] ?? 0) === 0 && ($fm_compte_bl['brouillon'] ?? 0) > 0): ?>
                    <p class="form-hint bl-facture-hint" style="margin-top:12px;">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        Seuls les BL au statut <strong>Validé (comptabilité)</strong> entrent dans la facture mensuelle. Les brouillons doivent d’abord être validés depuis le détail du bon.
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="form-hint bl-facture-bar--warn"><i class="fas fa-database"></i> Factures mensuelles indisponibles : exécutez la migration <code>migrations/migration_admin_b2b_structure.sql</code>.</p>
            <?php endif; ?>
        </div>

        <?php if (empty($bl_list)): ?>
            <div class="bl-empty-state bl-empty-state--compact" role="status">
                <div class="bl-empty-state__visual" aria-hidden="true">
                    <span class="bl-empty-state__ring"></span>
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h3 class="bl-empty-state__title">Aucun bon de livraison</h3>
                <p class="bl-empty-state__text">Ce contact n’a pas encore de BL associé.</p>
                <a href="index.php?tab=bl" class="btn-primary bl-empty-state__btn"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour</a>
            </div>
        <?php else: ?>
            <div class="bl-list-section">
                <h2 class="bl-list-section__title"><i class="fas fa-list-ul" aria-hidden="true"></i> Liste des bons de livraison</h2>
                <p class="bl-list-section__hint"><?php echo (int) $nb_bl; ?> document<?php echo $nb_bl > 1 ? 's' : ''; ?> — cliquez sur « Ouvrir » pour le détail ou « Réajuster » pour modifier.</p>
                <div class="bl-record-grid" role="list">
                    <?php foreach ($bl_list as $b): ?>
                        <?php
                        $bst = $b['statut'] ?? 'brouillon';
                        $bst_label = bl_libelle_statut_court($bst);
                        $bid = (int) $b['id'];
                        ?>
                        <article class="bl-record-card" role="listitem">
                            <div class="bl-record-card__top">
                                <div class="bl-record-card__ids">
                                    <h3 class="bl-record-card__num"><?php echo htmlspecialchars($b['numero_bl'] ?? ''); ?></h3>
                                    <?php if (!empty($b['date_bl'])): ?>
                                        <p class="bl-record-card__date"><i class="fas fa-calendar-day" aria-hidden="true"></i> <?php echo htmlspecialchars($b['date_bl']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="commande-statut statut-<?php echo htmlspecialchars($bst); ?>"><?php echo htmlspecialchars($bst_label); ?></span>
                            </div>
                            <div class="bl-record-card__amount">
                                <span class="bl-record-card__amount-label">Total HT</span>
                                <span class="bl-record-card__amount-val"><?php echo number_format((float) ($b['total_ht'] ?? 0), 0, ',', ' '); ?> <small>FCFA</small></span>
                            </div>
                            <div class="bl-record-card__actions">
                                <a href="../devis/bl_voir.php?id=<?php echo $bid; ?>" class="bl-record-card__btn bl-record-card__btn--primary"><i class="fas fa-eye" aria-hidden="true"></i> Ouvrir</a>
                                <?php if (!bl_est_statut_verrouille($bst)): ?>
                                <a href="../devis/bl_modifier.php?id=<?php echo $bid; ?>" class="bl-record-card__btn bl-record-card__btn--secondary"><i class="fas fa-edit" aria-hidden="true"></i> Réajuster</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
