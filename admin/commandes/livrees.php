<?php
/**
 * Page de liste des commandes livrées (Admin)
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer uniquement les commandes avec le statut "livree"
/* LE GARDE-BARRIÈRE (31/08/2026) : cette page ne demandait jamais si le
 * compte connecté avait le droit d'être là. La règle existe depuis
 * toujours dans includes/admin_route_access.php ; il manquait l'appel. */
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_commandes_retours.php';
$crc_tables_ok = crc_retour_tables_available();
$toutes_commandes = get_all_commandes();

// Filtrer pour ne garder que les commandes avec le statut "livree" ou "paye"
$commandes_livrees = array_filter($toutes_commandes, function ($commande) {
    return $commande['statut'] === 'livree' || $commande['statut'] === 'paye';
});

// Par défaut : afficher uniquement les livraisons du jour. Option pour inclure les jours précédents
$jours_precedents = isset($_GET['jours_precedents']) && $_GET['jours_precedents'] === '1';
if (!$jours_precedents) {
    $aujourd_hui = date('Y-m-d');
    $commandes_livrees = array_filter($commandes_livrees, function ($c) use ($aujourd_hui) {
        $date_ref = !empty($c['date_livraison']) ? $c['date_livraison'] : $c['date_commande'];
        $date_c = date('Y-m-d', strtotime($date_ref));
        return $date_c === $aujourd_hui;
    });
}

// Statistiques
$total_commandes = count_commandes_by_statut();
$livrees = count_commandes_by_statut('livree') + count_commandes_by_statut('paye');

// Comptabilité : montant total des commandes livrées
$montant_total_livrees = get_montant_total_commandes('livree') + get_montant_total_commandes('paye');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes Livrées - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <div class="content-header">
        <h1><i class="fas fa-check-circle"></i> Commandes Livrées</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="commandes-stats">
        <div class="stat-box">
            <h3>Total Commandes</h3>
            <div class="stat-value"><?php echo $total_commandes; ?></div>
        </div>
        <div class="stat-box">
            <h3>Commandes Livrées</h3>
            <div class="stat-value"><?php echo $livrees; ?></div>
        </div>
    </div>

    <!-- Comptabilité -->
    <div class="comptabilite-box">
        <div class="comptabilite-label"><i class="fas fa-calculator"></i> Montant total des commandes livrées</div>
        <div class="comptabilite-value"><?php echo number_format($montant_total_livrees, 0, ',', ' '); ?> FCFA</div>
    </div>

    <!-- Liste des commandes -->
    <section class="content-section">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="fas fa-check-circle"></i> Commandes Reçues (<?php echo count($commandes_livrees); ?>)</h2>
            </div>
            <div class="form-actions" style="flex-wrap: wrap;">
                <?php if ($jours_precedents): ?>
                    <a href="livrees.php" class="btn-link">
                        <i class="fas fa-calendar-day"></i> Voir uniquement les livraisons du jour
                    </a>
                <?php else: ?>

                <?php endif; ?>
                <a href="index.php" class="btn-link">
                    <i class="fas fa-shopping-bag"></i> Voir les commandes à traiter
                </a>
                <?php if ($crc_tables_ok): ?>
                <a href="index.php?tab=retours" class="btn-link">
                    <i class="fas fa-undo"></i> Commandes retournées
                </a>
                <?php endif; ?>
                <a href="annulees.php" class="btn-link btn-danger">
                    <i class="fas fa-ban"></i> Voir les commandes annulées
                </a>
            </div>
        </div>

        <?php if (empty($commandes_livrees)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Aucune commande livrée</h3>
                <p>Aucune commande n'a encore été livrée.</p>
            </div>
        <?php else: ?>
            <div class="commandes-grid">
                <?php foreach ($commandes_livrees as $commande): ?>
                    <div class="commande-item">
                        <div class="commande-header">
                            <div class="commande-info">
                                <h3>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></h3>
                                <p>
                                    <strong>Client:</strong>
                                    <?php echo htmlspecialchars(trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''))); ?><br>
                                    <span
                                        class="client-email"><?php echo !empty($commande['user_email']) ? htmlspecialchars($commande['user_email']) : '—'; ?></span>
                                </p>
                                <p class="commande-date">Date:
                                    <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?>
                                </p>
                            </div>
                            <span class="commande-statut statut-<?php echo $commande['statut']; ?>">
                                <?php echo $commande['statut'] === 'paye' ? '<i class="fas fa-money-bill-wave"></i> Payée' : '<i class="fas fa-check-circle"></i> Reçu'; ?>
                            </span>
                        </div>
                        <div class="commande-details">
                            <div class="detail-item">
                                <label>Montant total</label>
                                <div class="value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Adresse</label>
                                <div class="value small">
                                    <?php echo htmlspecialchars(substr($commande['adresse_livraison'], 0, 30)); ?>...
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Téléphone</label>
                                <div class="value"><?php echo htmlspecialchars($commande['telephone_livraison']); ?></div>
                            </div>
                            <?php if ($commande['date_livraison']): ?>
                                <div class="detail-item">
                                    <label>Date livraison</label>
                                    <div class="value"><?php echo date('d/m/Y', strtotime($commande['date_livraison'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="commande-actions-devis" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px;">
                            <a href="details.php?id=<?php echo (int) $commande['id']; ?>" class="btn-view" style="flex:1;min-width:140px;text-align:center;justify-content:center;">
                                <i class="fas fa-eye"></i> Voir les détails
                            </a>
                            <?php if ($crc_tables_ok && crc_commande_est_eligible_retour($commande)): ?>
                            <a href="retour_creation.php?id=<?php echo (int) $commande['id']; ?>" class="btn-secondary" style="flex:1;min-width:160px;display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;">
                                <i class="fas fa-undo"></i> Enregistrer un retour
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>

</body>

</html>