<?php
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../models/model_commandes_retours.php';

$retour_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($retour_id <= 0 || !crc_retour_tables_available()) {
    header('Location: index.php?tab=retours');
    exit;
}

$retour = crc_get_by_id($retour_id);
if (!$retour) {
    header('Location: index.php?tab=retours');
    exit;
}

$lignes = crc_get_lignes($retour_id);
$commande_id = (int) ($retour['commande_id'] ?? 0);
$total = (float) ($retour['montant_total_retour'] ?? 0);
$num_retour = htmlspecialchars($retour['numero_retour'] ?? '');
$num_cmd = htmlspecialchars($retour['numero_commande'] ?? '');
$client_aff = htmlspecialchars(trim(($retour['user_prenom'] ?? '') . ' ' . ($retour['user_nom'] ?? '')), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $num_retour; ?> — Retour boutique</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-undo" aria-hidden="true"></i> <?php echo $num_retour; ?></h1>
            <p class="bl-page-header__sub">Commande <strong>#<?php echo $num_cmd; ?></strong><?php echo $client_aff !== '' ? ' · ' . $client_aff : ''; ?> · <?php echo !empty($retour['date_retour']) ? htmlspecialchars(date('d/m/Y à H:i', strtotime($retour['date_retour']))) : '—'; ?></p>
        </div>
        <div class="header-actions bl-page-header__actions bl-page-header__actions--stack">
            <a href="details.php?id=<?php echo $commande_id; ?>" class="btn-secondary"><i class="fas fa-shopping-bag" aria-hidden="true"></i> Voir la commande</a>
            <a href="index.php?tab=retours" class="btn-secondary"><i class="fas fa-list" aria-hidden="true"></i> Retours boutique</a>
            <a href="livrees.php" class="btn-secondary"><i class="fas fa-check-circle" aria-hidden="true"></i> Livrées</a>
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
                <span class="bl-voir-hero__label">Montant total retour HT</span>
                <p class="bl-voir-hero__total"><?php echo number_format($total, 0, ',', ' '); ?> <span class="bl-voir-hero__currency">FCFA</span></p>
            </div>
        </div>

        <?php if (!empty($retour['notes'])): ?>
        <div class="bl-info-panel" style="margin-bottom:24px;">
            <h2 class="bl-info-panel__title"><i class="fas fa-sticky-note" aria-hidden="true"></i> Notes</h2>
            <p><?php echo nl2br(htmlspecialchars($retour['notes'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="bl-lines-section">
            <h2 class="bl-lines-section__title"><i class="fas fa-list" aria-hidden="true"></i> Lignes retournées</h2>
            <div class="bl-lines-table-wrap">
                <table class="admin-table bl-lines-table">
                    <thead>
                        <tr>
                            <th scope="col">Désignation</th>
                            <th scope="col" class="bl-lines-table__num">Qté retour</th>
                            <th scope="col" class="bl-lines-table__num">Prix unit.</th>
                            <th scope="col" class="bl-lines-table__num">Total ligne</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['designation'] ?? ''); ?></td>
                            <td class="bl-lines-table__num"><?php echo rtrim(rtrim(sprintf('%.4F', (float) ($row['quantite_retour'] ?? 0)), '0'), '.'); ?></td>
                            <td class="bl-lines-table__num"><?php echo number_format((float) ($row['prix_unitaire'] ?? 0), 0, ',', ' '); ?></td>
                            <td class="bl-lines-table__num"><strong><?php echo number_format((float) ($row['total_ligne'] ?? 0), 0, ',', ' '); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
