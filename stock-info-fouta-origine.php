<?php
/**
 * L'ANCIENNE page du scan QR d'un produit (jusqu'au 04/09/2026).
 * Elle montrait publiquement le stock, sa valeur et le CA du produit — le QR
 * mène désormais à la vitrine client (p.php : le contenu du scan est
 * exclusivement destiné aux clients). L'original vit ici, réservé à une
 * session administrateur ouverte.
 */
require_once __DIR__ . '/includes/session_user.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: /stock-info.php' . (isset($_GET['id']) ? '?id=' . (int) $_GET['id'] : ''));
    exit;
}

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($produit_id <= 0) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_commandes.php';
require_once __DIR__ . '/includes/produit_emplacement_entrepot.php';
require_once __DIR__ . '/includes/fpl_public_branding.php';

if (file_exists(__DIR__ . '/includes/asset_version.php')) {
    require_once __DIR__ . '/includes/asset_version.php';
}

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    header('Location: /');
    exit;
}

$quantite_vendue = get_quantite_vendue_produit($produit_id);
$stock_actuel = (int) ($produit['stock'] ?? 0);
$nombre_total = $stock_actuel + $quantite_vendue;
$stock_restant = $nombre_total - $quantite_vendue;

$prix_catalogue = (float) ($produit['prix'] ?? 0);
$prix_promo = null;
if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] > 0) {
    $prix_promo = (float) $produit['prix_promotion'];
}
$en_promotion = $prix_promo !== null && $prix_promo < $prix_catalogue;
$prix_vente = $en_promotion ? $prix_promo : $prix_catalogue;

$valeur_stock_actuel = $stock_actuel * $prix_vente;
$valeur_ventes = $quantite_vendue * $prix_vente;

$emplacement_vals = produit_emplacement_from_produit($produit);
$emplacement_resume = produit_emplacement_resume_court($emplacement_vals);
$a_emplacement = produit_emplacement_a_des_donnees($emplacement_vals);

$brand = fpl_public_branding_coords();
$logo_url = fpl_public_branding_logo_url();
$ref_fpl = trim((string) ($produit['identifiant_interne'] ?? ''));
$av = function_exists('asset_version_query') ? asset_version_query() : '';

$etapes_emplacement = [
    ['col' => 'etage', 'label' => 'Niveau'],
    ['col' => 'numero_rayon', 'label' => 'Rayon'],
    ['col' => 'allee', 'label' => 'Allée'],
    ['col' => 'zone_emplacement', 'label' => 'Zone'],
    ['col' => 'barre_rayon', 'label' => 'Barre'],
    ['col' => 'position_emplacement', 'label' => 'Position'],
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($produit['nom']); ?> — FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo $av; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/stock-info-public.css<?php echo $av; ?>">
</head>

<body class="page-stock-info-public">
    <div class="sip-shell">
        <header class="sip-brand-bar">
            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="" class="sip-brand-bar__logo" width="56" height="56">
            <div>
                <p class="sip-brand-bar__name"><?php echo htmlspecialchars($brand['nom']); ?></p>
                <p class="sip-brand-bar__tag"><?php echo htmlspecialchars($brand['tagline']); ?></p>
            </div>
        </header>

        <?php if ($a_emplacement): ?>
        <section class="sip-card sip-location" aria-label="Emplacement entrepôt">
            <div class="sip-location__banner">
                <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                <h2>Emplacement en entrepôt</h2>
            </div>
            <?php if (!empty($emplacement_vals['chemin_libelle'])): ?>
                <p class="sip-location__chemin"><?php echo htmlspecialchars((string) $emplacement_vals['chemin_libelle']); ?></p>
            <?php elseif ($emplacement_resume !== ''): ?>
                <p class="sip-location__chemin"><?php echo htmlspecialchars($emplacement_resume); ?></p>
            <?php endif; ?>
            <div class="sip-location__grid">
                <?php
                $cells_shown = 0;
                foreach ($etapes_emplacement as $etape):
                    $col = $etape['col'];
                    if (empty($emplacement_vals[$col])) {
                        continue;
                    }
                    $cells_shown++;
                    $is_pos = ($col === 'position_emplacement');
                ?>
                <div class="sip-location__cell<?php echo $is_pos ? ' sip-location__cell--highlight' : ''; ?>">
                    <span class="sip-location__cell-label"><?php echo htmlspecialchars($etape['label']); ?></span>
                    <span class="sip-location__cell-value"><?php echo htmlspecialchars(produit_emplacement_option_label($col, $emplacement_vals[$col])); ?></span>
                </div>
                <?php endforeach; ?>
                <?php if ($cells_shown === 0 && $emplacement_resume !== ''): ?>
                <div class="sip-location__cell sip-location__cell--highlight">
                    <span class="sip-location__cell-label">Chemin</span>
                    <span class="sip-location__cell-value"><?php echo htmlspecialchars($emplacement_resume); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="sip-card">
            <div class="sip-product-head">
                <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>" alt=""
                    class="sip-product-head__img" onerror="this.src='/image/produit1.jpg'">
                <div class="sip-product-head__body">
                    <?php if ($ref_fpl !== ''): ?>
                        <span class="sip-product-head__ref"><?php echo htmlspecialchars($ref_fpl); ?></span>
                    <?php endif; ?>
                    <h1 class="sip-product-head__title"><?php echo htmlspecialchars($produit['nom']); ?></h1>
                    <div class="sip-price-block">
                        <span class="sip-price-block__label">Prix de vente</span>
                        <?php if ($en_promotion): ?>
                            <span class="sip-promo-badge">Promo</span>
                            <span class="sip-price sip-price--promo"><?php echo number_format($prix_vente, 0, ',', ' '); ?> FCFA</span>
                            <span class="sip-price-old"><?php echo number_format($prix_catalogue, 0, ',', ' '); ?> FCFA</span>
                        <?php else: ?>
                            <span class="sip-price"><?php echo number_format($prix_vente, 0, ',', ' '); ?> FCFA</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <h2 class="sip-section-title"><i class="fas fa-chart-pie" aria-hidden="true"></i> Stock &amp; ventes</h2>
            <div class="sip-kpi-grid">
                <div class="sip-kpi sip-kpi--full sip-kpi--total">
                    <span class="sip-kpi__label">Stock total (initial + entrées)</span>
                    <span class="sip-kpi__value"><?php echo (int) $nombre_total; ?></span>
                    <span class="sip-kpi__hint">Quantité enregistrée en base</span>
                </div>
                <div class="sip-kpi sip-kpi--vendu">
                    <span class="sip-kpi__label">Vendu</span>
                    <span class="sip-kpi__value"><?php echo (int) $quantite_vendue; ?></span>
                </div>
                <div class="sip-kpi sip-kpi--restant">
                    <span class="sip-kpi__label">Restant</span>
                    <span class="sip-kpi__value"><?php echo (int) $stock_restant; ?></span>
                </div>
                <div class="sip-kpi sip-kpi--valeur">
                    <span class="sip-kpi__label">Valeur stock actuel</span>
                    <span class="sip-kpi__value"><?php echo number_format($valeur_stock_actuel, 0, ',', ' '); ?> FCFA</span>
                    <span class="sip-kpi__hint"><?php echo (int) $stock_actuel; ?> × <?php echo number_format($prix_vente, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="sip-kpi sip-kpi--valeur">
                    <span class="sip-kpi__label">CA ventes</span>
                    <span class="sip-kpi__value"><?php echo number_format($valeur_ventes, 0, ',', ' '); ?> FCFA</span>
                    <span class="sip-kpi__hint"><?php echo (int) $quantite_vendue; ?> vendu(s)</span>
                </div>
            </div>
        </section>

        <footer class="sip-footer">
            <p><strong><?php echo htmlspecialchars($brand['nom']); ?></strong></p>
            <p>R.C : <?php echo htmlspecialchars($brand['rc']); ?> · N.I.N.E.A : <?php echo htmlspecialchars($brand['ninea']); ?></p>
            <p><?php echo htmlspecialchars($brand['adresse']); ?></p>
            <p>
                <a href="<?php echo htmlspecialchars($brand['telephone_href']); ?>"><?php echo htmlspecialchars($brand['telephone']); ?></a>
                · <a href="<?php echo htmlspecialchars($brand['site']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(parse_url($brand['site'], PHP_URL_HOST) ?: $brand['site']); ?></a>
                · <a href="mailto:<?php echo htmlspecialchars($brand['email']); ?>"><?php echo htmlspecialchars($brand['email']); ?></a>
            </p>
        </footer>
    </div>
</body>
</html>
