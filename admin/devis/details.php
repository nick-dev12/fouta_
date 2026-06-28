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
if (!admin_can_devis()) {
    header('Location: ../dashboard.php');
    exit;
}

$devis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($devis_id <= 0) {
    header('Location: devis.php');
    exit;
}

require_once __DIR__ . '/../../models/model_devis.php';
require_once __DIR__ . '/../../models/model_factures_devis.php';
require_once __DIR__ . '/../../models/model_produits.php';

$devis = get_devis_by_id($devis_id);
$produits = get_produits_by_devis($devis_id);
$produits = is_array($produits) ? $produits : [];
$has_ident_devis = function_exists('produits_has_column') && produits_has_column('identifiant_interne');
foreach ($produits as &$produit_devis_row) {
    $produit_devis_row['ref_fpl'] = '';
    $pid_d = (int) ($produit_devis_row['produit_id'] ?? 0);
    if ($pid_d > 0 && $has_ident_devis) {
        $pr_d = get_produit_by_id($pid_d);
        if ($pr_d && trim((string) ($pr_d['identifiant_interne'] ?? '')) !== '') {
            $produit_devis_row['ref_fpl'] = strtoupper(trim((string) $pr_d['identifiant_interne']));
        }
    }
}
unset($produit_devis_row);
$facture = get_facture_devis_by_devis($devis_id);

if (!$devis) {
    header('Location: devis.php');
    exit;
}

$client_nom = trim($devis['client_prenom'] . ' ' . $devis['client_nom']);
$num_devis = htmlspecialchars($devis['numero_devis']);
$date_creation_txt = date('d/m/Y à H:i', strtotime($devis['date_creation']));
$st = htmlspecialchars($devis['statut']);
$st_uc = ucfirst($devis['statut']);
$sous_total = array_sum(array_column($produits, 'prix_total'));
$frais = isset($devis['frais_livraison']) ? (float) $devis['frais_livraison'] : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis #<?php echo $num_devis; ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-devis-detail.css<?php echo asset_version_query(); ?>">
</head>
<body class="devis-detail-page">
    <?php include '../includes/nav.php'; ?>

    <div class="devis-detail-wrap">
    <div class="content-header devis-detail-header">
        <div class="devis-detail-header__lead">
            <span class="devis-detail-ic devis-detail-ic--doc" aria-hidden="true"><i class="fas fa-file-invoice"></i></span>
            <div class="devis-detail-header__text">
                <p class="devis-detail-header__eyebrow">Devis commercial</p>
                <h1>Devis #<?php echo $num_devis; ?></h1>
                <p class="devis-detail-header__meta">
                    <span class="devis-detail-header__meta-item"><i class="fas fa-calendar-day" aria-hidden="true"></i> <?php echo htmlspecialchars($date_creation_txt); ?></span>
                    <span class="devis-detail-header__meta-item"><span class="commande-statut statut-<?php echo $st; ?>"><?php echo htmlspecialchars($st_uc); ?></span></span>
                </p>
            </div>
        </div>
        <div class="header-actions">
            <?php if ($facture): ?>
                <a href="facture.php?id=<?php echo (int) $facture['id']; ?>" class="btn-primary">
                    <i class="fas fa-file-invoice" aria-hidden="true"></i> Voir la facture
                </a>
            <?php else: ?>
                <a href="generer_facture.php?id=<?php echo $devis_id; ?>" class="btn-primary">
                    <i class="fas fa-file-signature" aria-hidden="true"></i> Générer une facture
                </a>
            <?php endif; ?>
            <a href="devis.php" class="btn-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <div class="commande-details-grid devis-detail-grid">
        <article class="detail-box devis-detail-card">
            <h3 class="devis-detail-card__head">
                <span class="devis-detail-ic devis-detail-ic--user devis-detail-ic--sm" aria-hidden="true"><i class="fas fa-user"></i></span>
                Informations client
            </h3>
            <dl class="devis-detail-dl">
                <div class="devis-detail-dl__row">
                    <dt>Nom complet</dt>
                    <dd><?php echo htmlspecialchars($client_nom); ?></dd>
                </div>
                <div class="devis-detail-dl__row">
                    <dt>Email</dt>
                    <dd><?php echo htmlspecialchars($devis['client_email'] ?? '—'); ?></dd>
                </div>
                <div class="devis-detail-dl__row">
                    <dt>Téléphone</dt>
                    <dd><?php echo htmlspecialchars($devis['client_telephone']); ?></dd>
                </div>
            </dl>
        </article>

        <article class="detail-box devis-detail-card">
            <h3 class="devis-detail-card__head">
                <span class="devis-detail-ic devis-detail-ic--ship devis-detail-ic--sm" aria-hidden="true"><i class="fas fa-truck"></i></span>
                Livraison
            </h3>
            <dl class="devis-detail-dl">
                <div class="devis-detail-dl__row devis-detail-dl__row--full">
                    <dt>Adresse</dt>
                    <dd><?php echo trim((string) ($devis['adresse_livraison'] ?? '')) !== '' ? nl2br(htmlspecialchars($devis['adresse_livraison'])) : '—'; ?></dd>
                </div>
                <?php if (!empty($devis['frais_livraison'])): ?>
                <div class="devis-detail-dl__row">
                    <dt>Frais de livraison</dt>
                    <dd><?php echo number_format($devis['frais_livraison'], 0, ',', ' '); ?> FCFA</dd>
                </div>
                <?php endif; ?>
            </dl>
        </article>
    </div>

    <section class="content-section devis-detail-section">
        <header class="devis-detail-section-head">
            <span class="devis-detail-ic devis-detail-ic--box" aria-hidden="true"><i class="fas fa-box-open"></i></span>
            <h2>Produits du devis</h2>
        </header>

        <div class="produits-list devis-detail-produits">
            <?php if (empty($produits)): ?>
                <div class="devis-detail-produits-empty" role="status">
                    <div><i class="fas fa-inbox" aria-hidden="true"></i></div>
                    <p>Aucune ligne produit sur ce devis.</p>
                </div>
            <?php else: ?>
                <?php $idx = 0; ?>
                <?php foreach ($produits as $produit): ?>
                    <?php $idx++; ?>
                <div class="produit-item devis-detail-ligne-produit">
                    <div class="devis-detail-ligne-produit__main">
                        <span class="devis-detail-ligne-index" aria-hidden="true"><?php echo $idx; ?></span>
                        <div class="devis-detail-ligne-body">
                            <h4><?php echo htmlspecialchars($produit['produit_nom'] ?? $produit['nom_produit'] ?? ''); ?></h4>
                            <?php if (!empty($produit['ref_fpl'])): ?>
                            <p class="devis-detail-ligne-ref"><code><?php echo htmlspecialchars($produit['ref_fpl']); ?></code></p>
                            <?php endif; ?>
                            <div class="devis-detail-ligne-stats">
                                <span class="devis-detail-chip"><i class="fas fa-cubes" aria-hidden="true"></i> Qté <?php echo (int) $produit['quantite']; ?></span>
                                <span class="devis-detail-chip devis-detail-chip--muted"><i class="fas fa-tag" aria-hidden="true"></i> <?php echo number_format($produit['prix_unitaire'], 0, ',', ' '); ?> FCFA / u.</span>
                            </div>
                        </div>
                    </div>
                    <div class="produit-total devis-detail-ligne-price"><?php echo number_format($produit['prix_total'], 0, ',', ' '); ?> FCFA</div>
                </div>
                <?php endforeach; ?>

                <div class="produits-list-total devis-detail-total">
                    <div class="devis-detail-total-lines">
                        <?php if ($frais > 0): ?>
                        <span><span class="devis-detail-total-label">Sous-total produits</span><span class="devis-detail-total-amount"><?php echo number_format($sous_total, 0, ',', ' '); ?> FCFA</span></span>
                        <span><span class="devis-detail-total-label">Frais de livraison</span><span class="devis-detail-total-amount"><?php echo number_format($frais, 0, ',', ' '); ?> FCFA</span></span>
                        <?php endif; ?>
                    </div>
                    <h3>Total : <span class="total-value"><?php echo number_format($devis['montant_total'], 0, ',', ' '); ?> FCFA</span></h3>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($devis['notes'])): ?>
    <section class="content-section devis-detail-section devis-detail-section--notes">
        <header class="devis-detail-section-head">
            <span class="devis-detail-ic devis-detail-ic--note devis-detail-ic--sm" aria-hidden="true"><i class="fas fa-sticky-note"></i></span>
            <h2>Notes</h2>
        </header>
        <div class="detail-box devis-detail-notes">
            <p><?php echo nl2br(htmlspecialchars($devis['notes'])); ?></p>
        </div>
    </section>
    <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
