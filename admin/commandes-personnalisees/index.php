<?php
/**
 * Liste des commandes personnalisées (Admin)
 * Design élégant, ergonomique et responsive
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../models/model_commandes_personnalisees.php';

$statut_filter = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$commandes = get_all_commandes_personnalisees($statut_filter ?: null);

$total = count_commandes_personnalisees_by_statut();
$en_attente = count_commandes_personnalisees_by_statut('en_attente');
$confirmee = count_commandes_personnalisees_by_statut('confirmee');
$en_preparation = count_commandes_personnalisees_by_statut('en_preparation');
$devis_envoye = count_commandes_personnalisees_by_statut('devis_envoye');
$terminee = count_commandes_personnalisees_by_statut('terminee');
$refusee = count_commandes_personnalisees_by_statut('refusee');
$annulee = count_commandes_personnalisees_by_statut('annulee');

$statuts_labels = get_statuts_commande_personnalisee();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes personnalisées - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-personnalisees.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="cp-page-header">
        <h1><i class="fas fa-palette"></i> Commandes personnalisées</h1>
    </div>

    <div class="cp-stats-grid">
        <div class="cp-stat-card">
            <h3>Total</h3>
            <div class="stat-value"><?php echo $total; ?></div>
        </div>
        <div class="cp-stat-card stat-en-attente">
            <h3>En attente</h3>
            <div class="stat-value"><?php echo $en_attente; ?></div>
        </div>
        <div class="cp-stat-card">
            <h3>Confirmées</h3>
            <div class="stat-value"><?php echo $confirmee; ?></div>
        </div>
        <div class="cp-stat-card">
            <h3>En préparation</h3>
            <div class="stat-value"><?php echo $en_preparation; ?></div>
        </div>
        <div class="cp-stat-card">
            <h3>Devis envoyé</h3>
            <div class="stat-value"><?php echo $devis_envoye; ?></div>
        </div>
        <div class="cp-stat-card stat-terminee">
            <h3>Terminées</h3>
            <div class="stat-value"><?php echo $terminee; ?></div>
        </div>
        <div class="cp-stat-card stat-refusee">
            <h3>Refusées</h3>
            <div class="stat-value"><?php echo $refusee; ?></div>
        </div>
        <div class="cp-stat-card stat-annulee">
            <h3>Annulées</h3>
            <div class="stat-value"><?php echo $annulee; ?></div>
        </div>
    </div>

    <section class="cp-section">
        <div class="cp-section-header">
            <h2 class="cp-section-title"><i class="fas fa-list"></i> Demandes (<?php echo count($commandes); ?>)</h2>
            <form method="GET" class="cp-filter-form">
                <select name="statut" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($statuts_labels as $val => $label): ?>
                    <option value="<?php echo $val; ?>" <?php echo $statut_filter === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($commandes)): ?>
            <div class="cp-empty-state">
                <i class="fas fa-palette"></i>
                <h3>Aucune commande personnalisée</h3>
                <p>Les demandes des clients apparaîtront ici.</p>
            </div>
        <?php else: ?>
            <div class="cp-commandes-grid">
                <?php foreach ($commandes as $cp): ?>
                    <div class="cp-card">
                        <div class="cp-card-header">
                            <span class="cp-card-id">Demande #<?php echo $cp['id']; ?></span>
                            <span class="cp-card-date"><?php echo date('d/m/Y H:i', strtotime($cp['date_creation'])); ?></span>
                        </div>
                        <div class="cp-card-body">
                            <p class="cp-card-client"><?php echo htmlspecialchars($cp['prenom'] . ' ' . $cp['nom']); ?></p>
                            <p class="cp-card-contact"><?php echo htmlspecialchars($cp['email']); ?> · <?php echo htmlspecialchars($cp['telephone']); ?></p>
                            <p class="cp-card-desc"><?php echo htmlspecialchars($cp['description']); ?></p>
                        </div>
                        <div class="cp-card-header" style="margin-bottom: 0;">
                            <span class="commande-statut statut-<?php echo $cp['statut']; ?>" style="margin: 0;">
                                <?php echo $statuts_labels[$cp['statut']] ?? $cp['statut']; ?>
                            </span>
                        </div>
                        <div class="cp-card-actions">
                            <a href="details.php?id=<?php echo $cp['id']; ?>" class="cp-btn-view">
                                <i class="fas fa-eye"></i> Voir / Traiter
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
