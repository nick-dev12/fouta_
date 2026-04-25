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

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_bl.php';

$bl_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bl_id <= 0 || !bl_tables_available()) {
    header('Location: index.php?tab=bl');
    exit;
}

$bl = get_bl_by_id($bl_id);
if (!$bl) {
    header('Location: index.php?tab=bl');
    exit;
}

$lignes = get_lignes_bl($bl_id);
$st = $bl['statut'] ?? 'brouillon';
$lib_statut = bl_libelle_statut($st);
$client_b2b_id = (int) ($bl['client_b2b_id'] ?? 0);

$total_ht = (float) ($bl['total_ht'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BL <?php echo htmlspecialchars($bl['numero_bl']); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-file-invoice" aria-hidden="true"></i> <?php echo htmlspecialchars($bl['numero_bl']); ?></h1>
            <p class="bl-page-header__sub">
                <?php echo htmlspecialchars($bl['raison_sociale'] ?? ''); ?>
                <?php if (!empty($bl['date_bl'])): ?>
                    · <?php echo htmlspecialchars($bl['date_bl']); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-actions bl-page-header__actions bl-page-header__actions--stack">
            <?php if ($client_b2b_id > 0): ?>
                <a href="bl_par_client.php?id=<?php echo $client_b2b_id; ?>" class="btn-secondary"><i class="fas fa-building" aria-hidden="true"></i> Tous les BL du client</a>
            <?php endif; ?>
            <a href="bl_facture.php?id=<?php echo (int) $bl_id; ?>" class="btn-primary" target="_blank" rel="noopener"><i class="fas fa-file-invoice" aria-hidden="true"></i> Facture</a>
            <?php if (!bl_est_statut_verrouille($st)): ?>
            <a href="bl_modifier.php?id=<?php echo (int) $bl_id; ?>" class="btn-secondary"><i class="fas fa-edit" aria-hidden="true"></i> Réajuster</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['bl_erreur'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['bl_erreur']); unset($_SESSION['bl_erreur']); ?></span>
        </div>
    <?php endif; ?>

    <section class="content-section bl-detail-page">
        <div class="bl-voir-hero">
            <div class="bl-voir-hero__main">
                <span class="bl-voir-hero__label">Total HT</span>
                <p class="bl-voir-hero__total"><?php echo number_format($total_ht, 0, ',', ' '); ?> <span class="bl-voir-hero__currency">FCFA</span></p>
            </div>
            <div class="bl-voir-hero__side">
                <span class="bl-voir-hero__label">Statut</span>
                <span class="commande-statut statut-<?php echo htmlspecialchars($st); ?> bl-voir-hero__stat bl-statut-badge"><?php echo htmlspecialchars($lib_statut); ?></span>
            </div>
        </div>

        <div class="bl-voir-panels">
            <div class="bl-info-panel">
                <h2 class="bl-info-panel__title"><i class="fas fa-building" aria-hidden="true"></i> Client</h2>
                <dl class="bl-dl">
                    <div class="bl-dl__row">
                        <dt>Raison sociale</dt>
                        <dd><?php echo htmlspecialchars($bl['raison_sociale'] ?? '—'); ?></dd>
                    </div>
                    <div class="bl-dl__row">
                        <dt>Téléphone</dt>
                        <dd><?php echo htmlspecialchars($bl['client_telephone'] ?? '—'); ?></dd>
                    </div>
                    <div class="bl-dl__row">
                        <dt>Email</dt>
                        <dd><?php echo htmlspecialchars($bl['client_email'] ?? '—'); ?></dd>
                    </div>
                    <div class="bl-dl__row bl-dl__row--block">
                        <dt>Adresse</dt>
                        <dd><?php echo nl2br(htmlspecialchars($bl['client_adresse'] ?? '—')); ?></dd>
                    </div>
                </dl>
            </div>
            <div class="bl-info-panel">
                <h2 class="bl-info-panel__title"><i class="fas fa-info-circle" aria-hidden="true"></i> Bon de livraison</h2>
                <dl class="bl-dl">
                    <div class="bl-dl__row">
                        <dt>Date</dt>
                        <dd><strong><?php echo htmlspecialchars($bl['date_bl'] ?? '—'); ?></strong></dd>
                    </div>
                    <div class="bl-dl__row">
                        <dt>Numéro</dt>
                        <dd><?php echo htmlspecialchars($bl['numero_bl'] ?? '—'); ?></dd>
                    </div>
                    <?php if (!empty($bl['devis_id'])): ?>
                    <div class="bl-dl__row">
                        <dt>Origine</dt>
                        <dd><a href="details.php?id=<?php echo (int) $bl['devis_id']; ?>" class="bl-dl__link">Devis n°<?php echo (int) $bl['devis_id']; ?></a></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bl['notes'])): ?>
                    <div class="bl-dl__row bl-dl__row--block">
                        <dt>Notes</dt>
                        <dd><?php echo nl2br(htmlspecialchars($bl['notes'])); ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="bl-lines-section">
            <h2 class="bl-lines-section__title"><i class="fas fa-list" aria-hidden="true"></i> Lignes du bon</h2>
            <div class="bl-lines-table-wrap">
                <table class="admin-table bl-lines-table">
                    <thead>
                        <tr>
                            <th scope="col">Désignation</th>
                            <th scope="col" class="bl-lines-table__num">Qté</th>
                            <th scope="col" class="bl-lines-table__num">Prix unit. HT</th>
                            <th scope="col" class="bl-lines-table__num">Total ligne HT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lignes)): ?>
                        <tr>
                            <td colspan="4" class="bl-lines-table__empty">Aucune ligne sur ce bon.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($lignes as $l): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($l['designation'] ?? ''); ?></td>
                                <td class="bl-lines-table__num"><?php echo htmlspecialchars((string) ($l['quantite'] ?? '')); ?></td>
                                <td class="bl-lines-table__num"><?php echo number_format((float) ($l['prix_unitaire_ht'] ?? 0), 0, ',', ' '); ?></td>
                                <td class="bl-lines-table__num"><strong><?php echo number_format((float) ($l['total_ligne_ht'] ?? 0), 0, ',', ' '); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($lignes)): ?>
                    <tfoot>
                        <tr class="bl-lines-table__foot">
                            <th scope="row" colspan="3">Total HT</th>
                            <td class="bl-lines-table__num"><?php echo number_format($total_ht, 0, ',', ' '); ?> FCFA</td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="bl-voir-actions" role="group" aria-label="Actions sur le bon de livraison">
            <?php if ($st === 'brouillon'): ?>
                <form method="post" action="bl_statut.php" class="bl-voir-actions__form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <input type="hidden" name="bl_id" value="<?php echo (int) $bl_id; ?>">
                    <input type="hidden" name="statut" value="valide">
                    <button type="submit" class="btn-primary"><i class="fas fa-check-circle" aria-hidden="true"></i> Valider (comptabilité)</button>
                </form>
            <?php endif; ?>

            <?php if ($st === 'brouillon'): ?>
                <form method="post" action="bl_supprimer.php" class="bl-voir-actions__form" onsubmit="return confirm('Supprimer définitivement ce BL ?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <input type="hidden" name="bl_id" value="<?php echo (int) $bl_id; ?>">
                    <button type="submit" class="btn-secondary bl-voir-btn-delete"><i class="fas fa-trash" aria-hidden="true"></i> Supprimer</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
