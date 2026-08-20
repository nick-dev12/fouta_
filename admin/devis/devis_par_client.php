<?php
/**
 * Liste des devis d'un même client (regroupement Devis, hors facture payée)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis() && !admin_can_comptabilite()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_factures_devis.php';
require_once __DIR__ . '/../../models/model_devis.php';

$cle = isset($_GET['k']) ? trim((string) $_GET['k']) : '';
if ($cle === '') {
    header('Location: devis.php');
    exit;
}

$groupes = get_devis_agreges_par_client_non_payes();
$filtre = null;
foreach ($groupes as $g) {
    if (($g['cle'] ?? '') === $cle) {
        $filtre = $g;
        break;
    }
}

if (!$filtre) {
    $_SESSION['error_devis'] = 'Client ou groupe de devis introuvable (déjà entièrement soldé ?).';
    header('Location: devis.php');
    exit;
}

$devis_items = $filtre['devis'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis — <?php echo htmlspecialchars($filtre['label'] ?: 'Client', ENT_QUOTES, 'UTF-8'); ?></title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-devis-compta-pages.css'); ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="page-devis-admin">
        <div class="content-header dashboard-hero page-devis-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Devis par client</p>
                <h1><i class="fas fa-user-group" aria-hidden="true"></i> <?php echo htmlspecialchars($filtre['label'] ?: 'Client', ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="dashboard-subtitle"><?php echo (int) $filtre['nb']; ?> devis non soldés (facture non marquée payée) · dernier : <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($filtre['derniere']))); ?></p>
            </div>
            <div class="header-actions">
                <a href="devis.php" class="btn-secondary"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour</a>
            </div>
        </div>

        <section class="content-section page-devis-section">
            <div class="commandes-grid">
                <?php foreach ($devis_items as $d): ?>
                    <?php $devis_id_row = (int) $d['id']; $is_brouillon = ($d['statut'] ?? '') === 'brouillon'; ?>
                    <div class="commande-item">
                        <div class="commande-header">
                            <div class="commande-info">
                                <h3>Devis #<?php echo htmlspecialchars((string) $d['numero_devis']); ?></h3>
                                <p class="commande-date">Date: <?php echo date('d/m/Y à H:i', strtotime($d['date_creation'])); ?></p>
                            </div>
                            <span class="commande-statut statut-<?php echo htmlspecialchars((string) $d['statut']); ?>">
                                <?php echo ucfirst((string) $d['statut']); ?>
                            </span>
                        </div>
                        <div class="commande-details">
                            <div class="detail-item">
                                <label>Montant total</label>
                                <div class="value"><?php echo number_format((float) $d['montant_total'], 0, ',', ' '); ?> FCFA</div>
                            </div>
                            <div class="detail-item">
                                <label>Téléphone</label>
                                <div class="value"><?php echo htmlspecialchars((string) $d['client_telephone']); ?></div>
                            </div>
                        </div>
                        <div class="commande-actions-devis devis-card-actions">
                            <a href="details.php?id=<?php echo $devis_id_row; ?>" class="btn-view"><i class="fas fa-eye"></i> Voir</a>
                            <?php if ($is_brouillon): ?>
                                <a href="modifier.php?id=<?php echo $devis_id_row; ?>" class="btn-secondary"><i class="fas fa-edit"></i> Modifier</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
