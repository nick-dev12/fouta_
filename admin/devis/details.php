<?php
/**
 * Page de détails d'un devis (Admin)
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

$devis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($devis_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_devis.php';
require_once __DIR__ . '/../../models/model_factures_devis.php';

$devis = get_devis_by_id($devis_id);
$produits = get_produits_by_devis($devis_id);
$produits = is_array($produits) ? $produits : [];
$facture = get_facture_devis_by_devis($devis_id);

if (!$devis) {
    header('Location: index.php');
    exit;
}

$client_nom = trim($devis['client_prenom'] . ' ' . $devis['client_nom']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis #<?php echo htmlspecialchars($devis['numero_devis']); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1>
            <i class="fas fa-file-invoice"></i> Devis #<?php echo htmlspecialchars($devis['numero_devis']); ?>
        </h1>
        <div class="header-actions">
            <?php if ($facture): ?>
                <a href="facture.php?id=<?php echo (int) $facture['id']; ?>" class="btn-primary" target="_blank">
                    <i class="fas fa-file-invoice"></i> Voir la facture
                </a>
            <?php else: ?>
                <a href="generer_facture.php?id=<?php echo $devis_id; ?>" class="btn-primary">
                    <i class="fas fa-file-invoice"></i> Générer une facture
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <div class="commande-details-grid">
        <div class="detail-box">
            <h3><i class="fas fa-user"></i> Informations Client</h3>
            <div class="detail-item">
                <label>Nom complet</label>
                <div class="value"><?php echo htmlspecialchars($client_nom); ?></div>
            </div>
            <div class="detail-item">
                <label>Email</label>
                <div class="value"><?php echo htmlspecialchars($devis['client_email'] ?? '—'); ?></div>
            </div>
            <div class="detail-item">
                <label>Téléphone</label>
                <div class="value"><?php echo htmlspecialchars($devis['client_telephone']); ?></div>
            </div>
        </div>

        <div class="detail-box">
            <h3><i class="fas fa-map-marker-alt"></i> Livraison</h3>
            <div class="detail-item">
                <label>Adresse</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($devis['adresse_livraison'])); ?></div>
            </div>
            <?php if (!empty($devis['frais_livraison'])): ?>
                <div class="detail-item">
                    <label>Frais de livraison</label>
                    <div class="value"><?php echo number_format($devis['frais_livraison'], 0, ',', ' '); ?> FCFA</div>
                </div>
            <?php endif; ?>
            <div class="detail-item">
                <label>Date création</label>
                <div class="value"><?php echo date('d/m/Y à H:i', strtotime($devis['date_creation'])); ?></div>
            </div>
            <div class="detail-item">
                <label>Statut</label>
                <div class="value">
                    <span class="commande-statut statut-<?php echo $devis['statut']; ?>"><?php echo ucfirst($devis['statut']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-box"></i> Produits du devis</h2>
        </div>

        <div class="produits-list">
            <?php foreach ($produits as $produit): ?>
                <div class="produit-item">
                    <div class="produit-info">
                        <h4><?php echo htmlspecialchars($produit['produit_nom'] ?? $produit['nom_produit'] ?? ''); ?></h4>
                        <div class="produit-info-lignes">
                            <div class="info-ligne">Quantité: <?php echo $produit['quantite']; ?></div>
                            <div class="info-ligne">Prix unitaire: <?php echo number_format($produit['prix_unitaire'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                    </div>
                    <div class="produit-total">
                        <?php echo number_format($produit['prix_total'], 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="produits-list-total">
                <?php
                $sous_total = array_sum(array_column($produits, 'prix_total'));
                $frais = isset($devis['frais_livraison']) ? (float) $devis['frais_livraison'] : 0;
                ?>
                <?php if ($frais > 0): ?>
                    <p style="margin-bottom: 8px;">Sous-total produits: <?php echo number_format($sous_total, 0, ',', ' '); ?> FCFA</p>
                    <p style="margin-bottom: 8px;">Frais de livraison: <?php echo number_format($frais, 0, ',', ' '); ?> FCFA</p>
                <?php endif; ?>
                <h3>Total: <span class="total-value"><?php echo number_format($devis['montant_total'], 0, ',', ' '); ?> FCFA</span></h3>
            </div>
        </div>
    </section>

    <?php if (!empty($devis['notes'])): ?>
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-sticky-note"></i> Notes</h2>
        </div>
        <div class="detail-box">
            <p><?php echo nl2br(htmlspecialchars($devis['notes'])); ?></p>
        </div>
    </section>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
