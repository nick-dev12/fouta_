<?php
/**
 * Réajustement des lignes et de l'en-tête d'un BL (brouillon ou validé)
 */
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

if (bl_est_statut_verrouille($bl['statut'] ?? '')) {
    $_SESSION['success_message'] = 'Ce bon est validé pour la comptabilité : le réajustement des lignes n’est plus disponible.';
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

$lignes = get_lignes_bl($bl_id);
$bl_erreur = $_SESSION['bl_erreur'] ?? null;
if (isset($_SESSION['bl_erreur'])) {
    unset($_SESSION['bl_erreur']);
}

$st = $bl['statut'] ?? 'brouillon';
$lib_statut = bl_libelle_statut($st);
$client_b2b_id = (int) ($bl['client_b2b_id'] ?? 0);
$total_ht = (float) ($bl['total_ht'] ?? 0);

$rows = $lignes;
if (count($rows) < 3) {
    for ($i = count($rows); $i < 5; $i++) {
        $rows[] = [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier BL <?php echo htmlspecialchars($bl['numero_bl']); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-edit" aria-hidden="true"></i> Réajuster <?php echo htmlspecialchars($bl['numero_bl']); ?></h1>
            <p class="bl-page-header__sub">
                <?php echo htmlspecialchars($bl['raison_sociale'] ?? ''); ?>
                · <?php echo htmlspecialchars($lib_statut); ?>
            </p>
        </div>
        <div class="header-actions bl-page-header__actions bl-page-header__actions--stack">
            <a href="bl_voir.php?id=<?php echo (int) $bl_id; ?>" class="btn-secondary"><i class="fas fa-eye" aria-hidden="true"></i> Aperçu</a>
            <?php if ($client_b2b_id > 0): ?>
                <a href="bl_par_client.php?id=<?php echo $client_b2b_id; ?>" class="btn-secondary"><i class="fas fa-building" aria-hidden="true"></i> BL du client</a>
            <?php endif; ?>
            <a href="index.php?tab=bl" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Contacts BL</a>
        </div>
    </div>

    <?php if ($bl_erreur): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($bl_erreur); ?></span>
        </div>
    <?php endif; ?>

    <section class="content-section bl-detail-page">
        <div class="bl-voir-hero bl-modifier-hero">
            <div class="bl-voir-hero__main">
                <span class="bl-voir-hero__label">Total HT (après enregistrement)</span>
                <p class="bl-voir-hero__total"><?php echo number_format($total_ht, 0, ',', ' '); ?> <span class="bl-voir-hero__currency">FCFA</span></p>
            </div>
            <div class="bl-voir-hero__side">
                <span class="bl-voir-hero__label">Statut</span>
                <span class="commande-statut statut-<?php echo htmlspecialchars($st); ?> bl-voir-hero__stat"><?php echo htmlspecialchars($lib_statut); ?></span>
            </div>
        </div>

        <p class="bl-modifier-lead">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            Modifiez la date, les notes et les lignes ci-dessous, puis enregistrez. Les montants sont recalculés côté serveur.
        </p>

        <form method="post" action="bl_maj.php" id="form-bl-edit" class="bl-modifier-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
            <input type="hidden" name="bl_id" value="<?php echo (int) $bl_id; ?>">

            <div class="bl-modifier-fields">
                <div class="bl-modifier-field">
                    <label for="date_bl" class="bl-modifier-label">Date du BL</label>
                    <input type="date" name="date_bl" id="date_bl" class="bl-modifier-input" value="<?php echo htmlspecialchars($bl['date_bl'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="bl-modifier-field bl-modifier-field--grow">
                    <label for="notes" class="bl-modifier-label">Notes</label>
                    <textarea name="notes" id="notes" class="bl-modifier-textarea" rows="2" placeholder="Informations internes, précisions de livraison…"><?php echo htmlspecialchars($bl['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="bl-lines-section bl-modifier-lines">
                <h2 class="bl-lines-section__title"><i class="fas fa-list" aria-hidden="true"></i> Lignes (HT)</h2>
                <p class="bl-modifier-lines__hint">Renseignez désignation, quantité et prix unitaire HT. Laissez une ligne vide pour l’ignorer.</p>
                <div class="bl-lines-table-wrap bl-lines-table-wrap--edit">
                    <table class="admin-table bl-lines-table bl-lines-table--edit">
                        <thead>
                            <tr>
                                <th scope="col">Désignation</th>
                                <th scope="col" class="bl-lines-table__num">Qté</th>
                                <th scope="col" class="bl-lines-table__num">Prix unit. HT</th>
                                <th scope="col" class="bl-lines-table__num">ID produit</th>
                                <th scope="col" class="bl-lines-table__actions">Suppr.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $i => $row): ?>
                            <tr>
                                <td>
                                    <input type="text" class="bl-lines-input bl-lines-input--designation" name="lignes[<?php echo $i; ?>][designation]" value="<?php echo htmlspecialchars($row['designation'] ?? ''); ?>" autocomplete="off">
                                </td>
                                <td class="bl-lines-table__num">
                                    <input type="number" class="bl-lines-input bl-lines-input--num" name="lignes[<?php echo $i; ?>][quantite]" value="<?php echo htmlspecialchars((string) ($row['quantite'] ?? '')); ?>" min="0" step="0.001">
                                </td>
                                <td class="bl-lines-table__num">
                                    <input type="number" class="bl-lines-input bl-lines-input--num" name="lignes[<?php echo $i; ?>][prix_unitaire_ht]" value="<?php echo htmlspecialchars((string) ($row['prix_unitaire_ht'] ?? '')); ?>" min="0" step="0.01">
                                </td>
                                <td class="bl-lines-table__num">
                                    <input type="number" class="bl-lines-input bl-lines-input--num bl-lines-input--id" name="lignes[<?php echo $i; ?>][produit_id]" value="<?php echo !empty($row['produit_id']) ? (int) $row['produit_id'] : ''; ?>" min="0" step="1" placeholder="—">
                                </td>
                                <td class="bl-lines-table__actions">
                                    <?php if (!empty($row['id'])): ?>
                                    <button type="submit" class="bl-line-delete-btn" form="bl-del-ligne-<?php echo (int) $row['id']; ?>" title="Supprimer la ligne" aria-label="Supprimer la ligne"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                                    <?php else: ?>
                                    <span class="bl-line-delete-na">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bl-modifier-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer les modifications</button>
                <a href="bl_voir.php?id=<?php echo (int) $bl_id; ?>" class="btn-secondary">Annuler</a>
            </div>
        </form>

        <?php foreach ($lignes as $ln): ?>
            <?php if (!empty($ln['id'])): ?>
            <form id="bl-del-ligne-<?php echo (int) $ln['id']; ?>" method="post" action="bl_ligne_supprimer.php" class="bl-line-delete-form-hidden" aria-hidden="true" onsubmit="return confirm('Supprimer cette ligne ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="bl_id" value="<?php echo (int) $bl_id; ?>">
                <input type="hidden" name="ligne_id" value="<?php echo (int) $ln['id']; ?>">
            </form>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
