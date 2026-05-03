<?php
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis_bl()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bons_retour.php';

$br_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($br_id <= 0 || !br_retour_tables_available()) {
    header('Location: index.php?tab=br');
    exit;
}

$br = br_get_by_id($br_id);
if (!$br) {
    header('Location: index.php?tab=br');
    exit;
}

$lignes = br_get_lignes($br_id);
$bl_id = (int) ($br['bl_id'] ?? 0);
$total = (float) ($br['total_ht_retour'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($br['numero_br'] ?? 'BR'); ?> — Bon de retour</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-undo" aria-hidden="true"></i> <?php echo htmlspecialchars($br['numero_br'] ?? ''); ?></h1>
            <p class="bl-page-header__sub"><?php echo htmlspecialchars($br['raison_sociale'] ?? ''); ?> · <?php echo !empty($br['date_retour']) ? htmlspecialchars(date('d/m/Y à H:i:s', strtotime($br['date_retour']))) : '—'; ?></p>
        </div>
        <div class="header-actions bl-page-header__actions bl-page-header__actions--stack">
            <?php if ($bl_id > 0): ?>
            <a href="bl_voir.php?id=<?php echo $bl_id; ?>" class="btn-secondary"><i class="fas fa-file-invoice" aria-hidden="true"></i> Voir le BL <?php echo htmlspecialchars($br['numero_bl'] ?? ''); ?></a>
            <?php endif; ?>
            <a href="index.php?tab=br" class="btn-secondary"><i class="fas fa-list" aria-hidden="true"></i> Liste bons de retour</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <section class="content-section bl-detail-page">
        <div class="bl-voir-hero">
            <div class="bl-voir-hero__main">
                <span class="bl-voir-hero__label">Total HT retour</span>
                <p class="bl-voir-hero__total"><?php echo number_format($total, 0, ',', ' '); ?> <span class="bl-voir-hero__currency">FCFA</span></p>
            </div>
        </div>

        <div class="bl-voir-panels">
            <div class="bl-info-panel">
                <h2 class="bl-info-panel__title"><i class="fas fa-building" aria-hidden="true"></i> Client</h2>
                <dl class="bl-dl">
                    <div class="bl-dl__row">
                        <dt>Raison sociale</dt>
                        <dd><?php echo htmlspecialchars($br['raison_sociale'] ?? '—'); ?></dd>
                    </div>
                    <div class="bl-dl__row">
                        <dt>Téléphone</dt>
                        <dd><?php echo htmlspecialchars($br['client_telephone'] ?? '—'); ?></dd>
                    </div>
                    <div class="bl-dl__row">
                        <dt>Email</dt>
                        <dd><?php echo htmlspecialchars($br['client_email'] ?? '—'); ?></dd>
                    </div>
                </dl>
            </div>
            <div class="bl-info-panel">
                <h2 class="bl-info-panel__title"><i class="fas fa-info-circle" aria-hidden="true"></i> Bon de retour</h2>
                <dl class="bl-dl">
                    <div class="bl-dl__row">
                        <dt>Date / heure du retour</dt>
                        <dd><strong><?php echo !empty($br['date_retour']) ? htmlspecialchars(date('d/m/Y à H:i:s', strtotime($br['date_retour']))) : '—'; ?></strong></dd>
                    </div>
                    <div class="bl-dl__row">
                        <dt>Bon de livraison</dt>
                        <dd><a href="bl_voir.php?id=<?php echo $bl_id; ?>" class="bl-dl__link"><?php echo htmlspecialchars($br['numero_bl'] ?? '—'); ?></a></dd>
                    </div>
                    <?php if (!empty($br['notes'])): ?>
                    <div class="bl-dl__row bl-dl__row--block">
                        <dt>Notes</dt>
                        <dd><?php echo nl2br(htmlspecialchars((string) $br['notes'])); ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="bl-lines-section">
            <h2 class="bl-lines-section__title"><i class="fas fa-list" aria-hidden="true"></i> Lignes retournées</h2>
            <div class="bl-lines-table-wrap">
                <table class="admin-table bl-lines-table">
                    <thead>
                        <tr>
                            <th scope="col">Désignation</th>
                            <th scope="col" class="bl-lines-table__num">Qté retour</th>
                            <th scope="col" class="bl-lines-table__num">Prix unit. HT</th>
                            <th scope="col" class="bl-lines-table__num">Total ligne HT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes as $l): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($l['designation'] ?? ''); ?></td>
                            <td class="bl-lines-table__num"><?php echo htmlspecialchars(rtrim(rtrim(sprintf('%.4F', (float) ($l['quantite_retour'] ?? 0)), '0'), '.')); ?></td>
                            <td class="bl-lines-table__num"><?php echo number_format((float) ($l['prix_unitaire_ht'] ?? 0), 0, ',', ' '); ?></td>
                            <td class="bl-lines-table__num"><strong><?php echo number_format((float) ($l['total_ligne_ht'] ?? 0), 0, ',', ' '); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bl-lines-table__foot">
                            <th scope="row" colspan="3">Total HT</th>
                            <td class="bl-lines-table__num"><?php echo number_format($total, 0, ',', ' '); ?> FCFA</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
