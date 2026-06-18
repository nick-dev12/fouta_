<?php
/**
 * Page d'ajustement du stock d'un produit
 * Affiche: stock total, quantité vendue, stock restant, comptabilité, ajout cumulatif au stock, étiquettes FPL, historique.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($produit_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_ajuster_stock_produit($produit_id);

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: ajuster-stock.php?id=' . $produit_id);
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_commandes.php';
require_once __DIR__ . '/../../models/model_mouvements_stock.php';

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/barcode_fpl.php';
$code_fpl_live = ensure_produit_identifiant_interne($produit_id);
if ($code_fpl_live !== null && $code_fpl_live !== '') {
    $produit['identifiant_interne'] = $code_fpl_live;
}
if (get_barcode_produit_web_path($produit_id) === '') {
    generer_barcode_produit_fpl($produit_id);
}
$barcode_url = get_barcode_produit_web_path($produit_id);

$quantite_vendue = get_quantite_vendue_produit($produit_id);
$stock_actuel = (int) ($produit['stock'] ?? 0);
$nombre_total = $stock_actuel + $quantite_vendue;
$stock_restant = $nombre_total - $quantite_vendue;

$prix_produit = (float) ($produit['prix'] ?? 0);
if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < $prix_produit) {
    $prix_produit = (float) $produit['prix_promotion'];
}
$valeur_stock_actuel = $stock_actuel * $prix_produit;
$valeur_ventes = $quantite_vendue * $prix_produit;

$mouvements = get_stock_mouvements(null, $produit_id, null, null, 50);

$quantite_ajout_form_value = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['ajuster_stock'])
    && array_key_exists('quantite_ajout', $_POST)) {
    $quantite_ajout_form_value = (string) (int) $_POST['quantite_ajout'];
    if ($quantite_ajout_form_value === '0') {
        $quantite_ajout_form_value = '';
    }
}

// QR code : utiliser le fichier sauvegardé ou générer à la volée
$qr_code_data_uri = '';
$stock_info_url = '';
$qr_file = __DIR__ . '/../../upload/qrcodes/produit_' . $produit_id . '.png';
require_once __DIR__ . '/../../includes/site_url.php';
$stock_info_url = get_site_base_url() . '/stock-info.php?id=' . $produit_id;
if (file_exists($qr_file)) {
    $qr_code_data_uri = 'data:image/png;base64,' . base64_encode(file_get_contents($qr_file));
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale'        => 8,
            'outputBase64' => true,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $qr_code_data_uri = $qr->render($stock_info_url);
    } catch (Throwable $e) {
        try {
            $qro = new \chillerlan\QRCode\QROptions([
                'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
                'scale'        => 8,
                'outputBase64' => true,
            ]);
            $qr = new \chillerlan\QRCode\QRCode($qro);
            $qr_code_data_uri = $qr->render($stock_info_url);
        } catch (Throwable $e2) {
            $qr_code_data_uri = '';
        }
    }
}

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../includes/etiquette_fpl.php';

$categorie_etiq = [];
$categorie_nom_etiq = '—';
$categorie_id_etiq = isset($produit['categorie_id']) ? (int) $produit['categorie_id'] : 0;
if ($categorie_id_etiq > 0) {
    $tmp_cat = get_categorie_by_id($categorie_id_etiq);
    if ($tmp_cat) {
        $categorie_etiq = $tmp_cat;
        $categorie_nom_etiq = (string) ($tmp_cat['nom'] ?? '—');
    }
}
$fpl_couleur_hex = fpl_etiquette_couleur_pour_categorie(!empty($categorie_etiq) ? $categorie_etiq : [], $categorie_id_etiq);
$fpl_dark_hex = fpl_etiquette_hex_adjust_rgb($fpl_couleur_hex, -34);
$fpl_mini_qr_ref = !empty($produit['identifiant_interne'])
    ? fpl_etiquette_mini_ref_qr($produit['identifiant_interne'])
    : '';
$footer_fpl = fpl_etiquette_footer_textes_par_defaut();
$site_base_et = get_site_base_url();
$origin_et = get_request_origin_base_url();

$fpl_shield_logo_file = 'logo fpl_stock.png';
$fpl_shield_logo_fs = __DIR__ . '/../../image/' . $fpl_shield_logo_file;
$fpl_shield_logo_url = $origin_et . '/image/' . rawurlencode($fpl_shield_logo_file);
$fpl_shield_logo_ver = is_file($fpl_shield_logo_fs) ? (int) filemtime($fpl_shield_logo_fs) : 1;

$etiquette_fpl_ready = ($barcode_url !== '' && !empty($produit['identifiant_interne']));
$fpl_css_path_fs = __DIR__ . '/../../css/fpl-etiquette.css';
$fpl_etiq_css_abs = $origin_et . '/css/fpl-etiquette.css?v=' . (is_file($fpl_css_path_fs) ? (int) filemtime($fpl_css_path_fs) : time());

if ($etiquette_fpl_ready) {
    $barcode_fs_et = __DIR__ . '/../../upload/barcodes/produit_' . $produit_id . '.png';
    $barcode_ver_et = is_file($barcode_fs_et) ? (int) filemtime($barcode_fs_et) : 1;
} else {
    $barcode_ver_et = 1;
}
$barcode_abs_et = ($barcode_url !== '' && strpos($barcode_url, 'http') === 0)
    ? $barcode_url
    : ($origin_et . (strpos((string) $barcode_url, '/') === 0 ? $barcode_url : '/' . ltrim((string) $barcode_url, '/')));
$fpl_etiq_photo_abs = '';
if (produits_has_column('image_etiquette_fpl')) {
    $rel_et = trim((string) ($produit['image_etiquette_fpl'] ?? ''));
    if ($rel_et !== '' && preg_match('#^produits/[a-zA-Z0-9_.-]+$#', $rel_et)) {
        $fs_et = __DIR__ . '/../../upload/' . $rel_et;
        if (is_file($fs_et)) {
            $fpl_etiq_photo_abs = $origin_et . '/upload/' . str_replace('\\', '/', $rel_et) . '?v=' . (int) filemtime($fs_et);
        }
    }
}
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$pdf_barcode_href = 'telecharger-code-pdf.php?id=' . $produit_id . '&type=barcode';
$pdf_qrcode_href = 'telecharger-code-pdf.php?id=' . $produit_id . '&type=qrcode';
$can_pdf_barcode = ($barcode_url !== '' && !empty($produit['identifiant_interne']));
$can_pdf_qrcode = ($stock_info_url !== '');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuster le stock - <?php echo htmlspecialchars($produit['nom']); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-ajuster-stock.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/fpl-etiquette.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-ajuster-stock-body">

    <?php include '../includes/nav.php'; ?>

    <div class="page-ajuster-stock">
        <div class="content-header dashboard-hero page-ajuster-stock-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Stock &amp; inventaire</p>
                <h1 id="page-ajuster-stock-title"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Ajuster le stock</h1>
                <p class="dashboard-subtitle page-ajuster-stock-hero__intro">
                    Produit <strong class="page-ajuster-stock-hero__nom"><?php echo htmlspecialchars($produit['nom']); ?></strong>
                </p>
                <div class="page-ajuster-stock-hero__actions">
                    <a href="index.php" class="btn-back page-ajuster-stock-back">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
                    </a>
                </div>
            </div>
        </div>

    <?php if (!empty($success_message)): ?>
        <div class="message success page-ajuster-stock-flash page-ajuster-stock-flash--success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
        <div class="message error page-ajuster-stock-flash page-ajuster-stock-flash--error" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($result['message']); ?>
        </div>
    <?php endif; ?>

    <?php
    // Récupération des informations enrichies
    $prod_description = trim(strip_tags((string) ($produit['description'] ?? '')));
    $prod_marque = produits_marque_libelle_from_row($produit);
    $prod_fournisseur = produits_fournisseur_nom_affichage($produit);
    $prod_ref_fournisseur = (produits_has_column('reference_fournisseur') ? trim((string) ($produit['reference_fournisseur'] ?? '')) : '');
    $meta_ref = !empty($produit['identifiant_interne']) ? trim((string) $produit['identifiant_interne']) : '';
    $meta_etage = isset($produit['etage']) && (string) $produit['etage'] !== '' ? trim((string) $produit['etage']) : '';
    $meta_rayon = isset($produit['numero_rayon']) && (string) $produit['numero_rayon'] !== '' ? trim((string) $produit['numero_rayon']) : '';
    ?>
    <div class="produit-preview page-ajuster-stock-preview" aria-label="Aperçu produit">
        <div class="page-ajuster-stock-preview__media">
            <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>"
                alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                onerror="this.src='/image/produit1.jpg'" width="96" height="96" loading="eager" decoding="async">
        </div>
        <div class="produit-preview-info page-ajuster-stock-preview__info">
            <!-- Titre avec nom · marque · description -->
            <h3 class="page-ajuster-stock-preview__title">
                <span class="pas-preview-nom"><?php echo htmlspecialchars($produit['nom']); ?></span>
                <?php if ($prod_marque !== ''): ?>
                <span class="pas-preview-sep">·</span>
                <span class="pas-preview-marque"><?php echo htmlspecialchars($prod_marque); ?></span>
                <?php endif; ?>
                <?php if ($prod_description !== ''): ?>
                <span class="pas-preview-sep">·</span>
                <span class="pas-preview-desc"><?php echo htmlspecialchars(substr($prod_description, 0, 100)) . (strlen($prod_description) > 100 ? '…' : ''); ?></span>
                <?php endif; ?>
            </h3>

            <!-- Prix -->
            <span class="prix page-ajuster-stock-preview__prix">
                <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA
                <span class="page-ajuster-stock-preview__prix-unit">/ unité</span>
            </span>
            <p class="page-ajuster-stock-preview__legend">Prix retenu pour la valorisation (promo si applicable).</p>

            <!-- Infos enrichies : Fournisseur + Référence -->
            <?php if ($prod_fournisseur !== '' || $prod_ref_fournisseur !== ''): ?>
            <div class="pas-preview-supplier" role="region" aria-label="Fournisseur">
                <?php if ($prod_fournisseur !== ''): ?>
                <div class="pas-preview-supplier__item">
                    <span class="pas-preview-supplier__ic"><i class="fas fa-truck" aria-hidden="true"></i></span>
                    <div class="pas-preview-supplier__body">
                        <span class="pas-preview-supplier__label">Fournisseur</span>
                        <span class="pas-preview-supplier__value"><?php echo htmlspecialchars($prod_fournisseur); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($prod_ref_fournisseur !== ''): ?>
                <div class="pas-preview-supplier__item">
                    <span class="pas-preview-supplier__ic"><i class="fas fa-hashtag" aria-hidden="true"></i></span>
                    <div class="pas-preview-supplier__body">
                        <span class="pas-preview-supplier__label">Réf. fournisseur</span>
                        <span class="pas-preview-supplier__value"><?php echo htmlspecialchars($prod_ref_fournisseur); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Mini-cartes : référence FPL, étage, rayon -->
            <?php if ($meta_ref !== '' || $meta_etage !== '' || $meta_rayon !== ''): ?>
            <div class="page-ajuster-stock-meta-cards" role="list" aria-label="Informations magasin">
                <?php if ($meta_ref !== ''): ?>
                <div class="page-ajuster-stock-meta-card" role="listitem">
                    <span class="page-ajuster-stock-meta-card__ic" aria-hidden="true"><i class="fas fa-barcode"></i></span>
                    <div class="page-ajuster-stock-meta-card__body">
                        <span class="page-ajuster-stock-meta-card__label">Référence FPL</span>
                        <span class="page-ajuster-stock-meta-card__value"><?php echo htmlspecialchars($meta_ref); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($meta_etage !== ''): ?>
                <div class="page-ajuster-stock-meta-card" role="listitem">
                    <span class="page-ajuster-stock-meta-card__ic" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                    <div class="page-ajuster-stock-meta-card__body">
                        <span class="page-ajuster-stock-meta-card__label">Étage</span>
                        <span class="page-ajuster-stock-meta-card__value"><?php echo htmlspecialchars($meta_etage); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($meta_rayon !== ''): ?>
                <div class="page-ajuster-stock-meta-card" role="listitem">
                    <span class="page-ajuster-stock-meta-card__ic" aria-hidden="true"><i class="fas fa-th-large"></i></span>
                    <div class="page-ajuster-stock-meta-card__body">
                        <span class="page-ajuster-stock-meta-card__label">N° rayon</span>
                        <span class="page-ajuster-stock-meta-card__value"><?php echo htmlspecialchars($meta_rayon); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ajuster-stock-layout page-ajuster-stock-layout">
        <div class="ajuster-stock-card page-ajuster-stock-card page-ajuster-stock-card--etat">
            <h2 class="page-ajuster-stock-card__title"><i class="fas fa-chart-bar" aria-hidden="true"></i> État du stock</h2>
            <div class="stock-stats-grid page-ajuster-stock-stats" role="list">
                <div class="stock-stat-card stock-total page-ajuster-stock-stat" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Nombre total</h4>
                    <div class="value page-ajuster-stock-stat__value" aria-label="Total cumulé"><?php echo (int) $nombre_total; ?></div>
                </div>
                <div class="stock-stat-card stock-vendu page-ajuster-stock-stat" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Quantité vendue</h4>
                    <div class="value page-ajuster-stock-stat__value" aria-label="Unités vendues"><?php echo (int) $quantite_vendue; ?></div>
                </div>
                <div class="stock-stat-card stock-restant page-ajuster-stock-stat" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Stock restant</h4>
                    <div class="value page-ajuster-stock-stat__value" aria-label="Stock actuel"><?php echo (int) $stock_restant; ?></div>
                </div>
            </div>

            <h2 class="page-ajuster-stock-card__title page-ajuster-stock-card__title--spaced"><i class="fas fa-calculator" aria-hidden="true"></i> Comptabilité (valorisation)</h2>
            <div class="comptabilite-grid page-ajuster-stock-compta">
                <div class="comptabilite-item page-ajuster-stock-compta__item">
                    <label class="page-ajuster-stock-compta__label">Valeur du stock actuel</label>
                    <span class="montant page-ajuster-stock-compta__montant"><?php echo number_format($valeur_stock_actuel, 0, ',', ' '); ?> FCFA</span>
                    <span class="detail page-ajuster-stock-compta__detail"><?php echo (int) $stock_actuel; ?> ×
                        <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="comptabilite-item page-ajuster-stock-compta__item">
                    <label class="page-ajuster-stock-compta__label">Chiffre d'affaires (ventes)</label>
                    <span class="montant page-ajuster-stock-compta__montant"><?php echo number_format($valeur_ventes, 0, ',', ' '); ?> FCFA</span>
                    <span class="detail page-ajuster-stock-compta__detail"><?php echo (int) $quantite_vendue; ?> vendu(s) ×
                        <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</span>
                </div>
            </div>
        </div>

        <div class="page-ajuster-stock-side">
            <div class="stock-form-block page-ajuster-stock-form">
                <h3 class="page-ajuster-stock-form__title"><i class="fas fa-edit" aria-hidden="true"></i> Mettre à jour le stock</h3>
                <form method="POST" action="?id=<?php echo $produit_id; ?>" class="page-ajuster-stock-form__form">
                    <input type="hidden" name="ajuster_stock" value="1">
                    <div class="form-group page-ajuster-stock-form__field">
                        <label for="quantite_ajout">Quantité à ajouter au stock actuel</label>
                        <input type="number" id="quantite_ajout" name="quantite_ajout" min="1" step="1" required
                            value="<?php echo htmlspecialchars($quantite_ajout_form_value, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Nombre d’unités reçues / à ajouter" inputmode="numeric" autocomplete="off"
                            aria-describedby="quantite_ajout_aide">
                    </div>
                    <p class="page-ajuster-stock-form__field-hint" id="quantite_ajout_aide">
                        Stock actuel&nbsp;: <strong><?php echo (int) $stock_actuel; ?></strong>.
                        Ce nombre s’<strong>ajoute</strong> aux unités déjà disponibles (ce n’est pas le stock total cible).
                        Pour fixer ou diminuer le stock différemment, utilisez <em>Modifier le produit</em>.
                    </p>
                    <button type="submit" class="btn-primary page-ajuster-stock-form__submit">
                        <i class="fas fa-check" aria-hidden="true"></i> Enregistrer le stock
                    </button>
                </form>
            </div>

            <?php if (!$etiquette_fpl_ready): ?>
                <?php if (!empty($barcode_url) && !empty($produit['identifiant_interne'])): ?>
                <div class="stock-form-block barcode-fpl-block page-ajuster-stock-aux" id="barcode-fpl-print-area"
                    data-barcode-src="<?php echo htmlspecialchars($barcode_url); ?>"
                    data-code="<?php echo htmlspecialchars($produit['identifiant_interne']); ?>"
                    data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-barcode" aria-hidden="true"></i> Code-barres (réf. FPL)</h3>
                    <p class="barcode-fpl-desc page-ajuster-stock-aux__desc">Code <strong>Code 128</strong> : même référence que sur l’étiquette produit. Utilisable avec un scanner ou l’API <code>/api/produit_par_code_fpl.php</code>.</p>
                    <div class="barcode-fpl-wrap page-ajuster-stock-barcode-wrap">
                        <?php
                        $barcode_fs = __DIR__ . '/../../upload/barcodes/produit_' . $produit_id . '.png';
                        $barcode_ver = is_file($barcode_fs) ? (int) filemtime($barcode_fs) : 1;
                        ?>
                        <img src="<?php echo htmlspecialchars($barcode_url); ?>?v=<?php echo $barcode_ver; ?>" alt="Code-barres <?php echo htmlspecialchars($produit['identifiant_interne']); ?>" class="barcode-fpl-img page-ajuster-stock-barcode-img" width="280" height="100">
                        <div class="barcode-fpl-code"><?php echo htmlspecialchars($produit['identifiant_interne']); ?></div>
                    </div>
                    <div class="barcode-fpl-actions page-ajuster-stock-aux__actions page-ajuster-stock-code-actions">
                        <button type="button" class="btn-primary btn-print-barcode page-ajuster-stock-print-btn" onclick="imprimerCodeBarresFPL()">
                            <i class="fas fa-print" aria-hidden="true"></i> Imprimer le code-barres
                        </button>
                        <?php if ($can_pdf_barcode): ?>
                        <a href="<?php echo htmlspecialchars($pdf_barcode_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn-download-pdf page-ajuster-stock-pdf-btn" target="_blank" rel="noopener">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i> Télécharger PDF
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($qr_code_data_uri)): ?>
                <div class="stock-form-block qr-code-block page-ajuster-stock-aux" id="qr-code-print-area" data-qr="<?php echo htmlspecialchars($qr_code_data_uri); ?>" data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-qrcode" aria-hidden="true"></i> QR code du produit</h3>
                    <p class="qr-code-desc page-ajuster-stock-aux__desc">Scannez ce QR code pour afficher les détails du stock sur mobile.</p>
                    <div class="qr-code-wrap page-ajuster-stock-qr-wrap">
                        <img src="<?php echo htmlspecialchars($qr_code_data_uri); ?>" alt="QR Code - <?php echo htmlspecialchars($produit['nom']); ?>" class="qr-code-img" width="180" height="180">
                    </div>
                    <p class="qr-code-produit"><?php echo htmlspecialchars($produit['nom']); ?></p>
                    <div class="qr-code-actions page-ajuster-stock-aux__actions page-ajuster-stock-code-actions">
                        <button type="button" class="btn-primary btn-print-qr page-ajuster-stock-print-btn" onclick="imprimerQRCode()">
                            <i class="fas fa-print" aria-hidden="true"></i> Imprimer le QR code
                        </button>
                        <?php if ($can_pdf_qrcode): ?>
                        <a href="<?php echo htmlspecialchars($pdf_qrcode_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn-download-pdf page-ajuster-stock-pdf-btn" target="_blank" rel="noopener">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i> Télécharger PDF
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($etiquette_fpl_ready): ?>
    <div class="fpl-etiquette-fixed-scroll">
        <div class="stock-form-block page-ajuster-stock-aux fpl-etiquette-sheet-wrap fpl-etiquette-sheet-wrap--fullwidth" id="fpl-etiquette-print-root"
            data-css-url="<?php echo htmlspecialchars($fpl_etiq_css_abs, ENT_QUOTES, 'UTF-8'); ?>">
            <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-tags" aria-hidden="true"></i> Étiquette FPL (QR + code-barres)</h3>

            <article class="fpl-etiq fpl-etiq--fixed"
                style="--fpl-accent: <?php echo htmlspecialchars($fpl_couleur_hex, ENT_QUOTES, 'UTF-8'); ?>; --fpl-accent-dark: <?php echo htmlspecialchars($fpl_dark_hex, ENT_QUOTES, 'UTF-8'); ?>;">
                <div class="fpl-etiq__header-zone">
                    <div class="fpl-etiq__band-top" aria-hidden="true"></div>
                    <div class="fpl-etiq__shield">
                        <img src="<?php echo htmlspecialchars($fpl_shield_logo_url, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $fpl_shield_logo_ver; ?>"
                            width="74"
                            height="44"
                            alt="FPL — Fouta Poids Lourds"
                            class="fpl-etiq__shield-logo">
                        <span class="fpl-etiq__shield-line">FOUTA POIDS LOURDS</span>
                        <span class="fpl-etiq__shield-line fpl-etiq__shield-line--small">The Solution Suarl</span>
                    </div>
                </div>
                <div class="fpl-etiq__sheet">
                    <div class="fpl-etiq__body">
                        <div class="fpl-etiq__col-left">
                            <div class="fpl-etiq__col-left-meta">
                                <div class="fpl-etiq__ref-big"><?php echo htmlspecialchars((string) $produit['identifiant_interne'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="fpl-etiq__nom-main"><?php echo htmlspecialchars((string) $produit['nom'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="fpl-etiq__cat-muted"><?php echo htmlspecialchars($categorie_nom_etiq, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="fpl-etiq__qr-block">
                                <div class="fpl-etiq__qr-mini"><?php echo htmlspecialchars($fpl_mini_qr_ref !== '' ? $fpl_mini_qr_ref : '—', ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if (!empty($qr_code_data_uri)): ?>
                                <div class="fpl-etiq__qr-box">
                                    <img src="<?php echo htmlspecialchars($qr_code_data_uri, ENT_QUOTES, 'UTF-8'); ?>" width="130" height="130" alt="QR Code stock" class="fpl-etiq__qr-img">
                                </div>
                                <?php else: ?>
                                <div class="fpl-etiq__qr-fallback" role="img" aria-label="QR indisponible">QR indisponible</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="fpl-etiq__divider" aria-hidden="true"></div>
                        <div class="fpl-etiq__col-right">
                            <div class="fpl-etiq__photo-box">
                                <?php if ($fpl_etiq_photo_abs !== ''): ?>
                                <div class="fpl-etiq__photo-strip fpl-etiq__photo-strip--image">
                                    <img src="<?php echo htmlspecialchars($fpl_etiq_photo_abs, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="fpl-etiq__photo-produit" width="232" height="90">
                                </div>
                                <?php else: ?>
                                <div class="fpl-etiq__photo-strip fpl-etiq__photo-strip--icons-only" role="list" aria-label="Pictogrammes poids lourds">
                                    <?php for ($__etiq_vi = 0; $__etiq_vi < 4; $__etiq_vi++):
                                        $__badge = fpl_etiquette_thumb_vehicle_badge($__etiq_vi, 28);
                                        ?>
                                    <div class="fpl-etiq__thumb fpl-etiq__thumb--icon-only" role="listitem">
                                        <span class="fpl-etiq__thumb-ico fpl-etiq__thumb-ico--solo" title="<?php echo htmlspecialchars($__badge['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo $__badge['svg']; ?>
                                        </span>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="fpl-etiq__barcode-line">
                                <img src="<?php echo htmlspecialchars($barcode_abs_et, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $barcode_ver_et; ?>"
                                    width="210" height="64" alt="Code-barres <?php echo htmlspecialchars((string) $produit['identifiant_interne'], ENT_QUOTES, 'UTF-8'); ?>" class="fpl-etiq__barcode-img">
                            </div>
                        </div>
                    </div>
                    <footer class="fpl-etiq__footer">
                        <div class="fpl-etiq__footer-row1">
                            <span class="fpl-etiq__footer-ico" aria-hidden="true">📍</span>
                            <span><?php echo htmlspecialchars($footer_fpl['adr'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fpl-etiq__footer-row2">
                            <span><span class="fpl-etiq__footer-ico" aria-hidden="true">☎</span> <?php echo htmlspecialchars($footer_fpl['tels'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><span class="fpl-etiq__footer-ico" aria-hidden="true">🌐</span> <?php echo htmlspecialchars($footer_fpl['web'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><span class="fpl-etiq__footer-ico" aria-hidden="true">✉</span> <?php echo htmlspecialchars($footer_fpl['mail'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </footer>
                </div>
            </article>
            <div class="fpl-etiquette-print-actions page-ajuster-stock-code-actions">
                <?php if ($can_pdf_barcode): ?>
                <a href="<?php echo htmlspecialchars($pdf_barcode_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn-download-pdf page-ajuster-stock-pdf-btn" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i> Code-barres (PDF)
                </a>
                <?php endif; ?>
                <?php if ($can_pdf_qrcode): ?>
                <a href="<?php echo htmlspecialchars($pdf_qrcode_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn-download-pdf page-ajuster-stock-pdf-btn" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i> QR code (PDF)
                </a>
                <?php endif; ?>
                <button type="button" class="btn-primary page-ajuster-stock-print-btn" onclick="window.imprimerEtiquetteFPLStock && window.imprimerEtiquetteFPLStock();">
                    <i class="fas fa-print" aria-hidden="true"></i> Imprimer l’étiquette
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <section class="mouvements-section page-ajuster-stock-mouvements" aria-labelledby="page-ajuster-stock-mouv-heading">
        <h2 id="page-ajuster-stock-mouv-heading" class="page-ajuster-stock-mouv__head"><i class="fas fa-history" aria-hidden="true"></i> Historique des mouvements <span class="page-ajuster-stock-mouv__count">(<?php echo count($mouvements); ?>)</span></h2>
        <?php if (empty($mouvements)): ?>
            <p class="page-ajuster-stock-mouv__empty">Aucun mouvement enregistré pour ce produit.</p>
        <?php else: ?>
            <div class="mouvements-produit-table-wrap page-ajuster-stock-mouv-table-wrap" tabindex="0" role="region" aria-label="Tableau des mouvements de stock">
                <table class="mouvements-produit-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Type</th>
                            <th scope="col">Quantité</th>
                            <th scope="col">Avant</th>
                            <th scope="col">Après</th>
                            <th scope="col">Référence</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements as $m): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></td>
                                <td>
                                    <?php
                                    $badge = 'badge-' . $m['type'];
                                    $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                                    ?>
                                    <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                                </td>
                                <td><?php echo (int) $m['quantite']; ?></td>
                                <td><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></td>
                                <td><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></td>
                                <td><?php echo htmlspecialchars($m['reference_numero'] ?? ($m['reference_type'] ?? '-')); ?>
                                </td>
                                <td><?php echo htmlspecialchars($m['notes'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mouvements-produit-cards">
                <?php foreach ($mouvements as $m):
                    $badge = 'badge-' . $m['type'];
                    $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                    $ref = htmlspecialchars($m['reference_numero'] ?? ($m['reference_type'] ?? '-'));
                ?>
                <div class="mouvement-produit-card">
                    <div class="mouvement-produit-card-header">
                        <span class="mouvement-produit-card-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></span>
                        <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                    </div>
                    <div class="mouvement-produit-card-body">
                        <div class="mouvement-produit-card-row">
                            <span class="label">Quantité</span>
                            <span class="value"><?php echo (int) $m['quantite']; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Avant</span>
                            <span class="value"><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Après</span>
                            <span class="value"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></span>
                        </div>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Référence</span>
                            <span class="value"><?php echo $ref; ?></span>
                        </div>
                    </div>
                    <?php if (!empty($m['notes'])): ?>
                    <div class="mouvement-produit-card-notes"><?php echo htmlspecialchars($m['notes']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    </div><!-- .page-ajuster-stock -->

    <?php include '../includes/footer.php'; ?>

    <script>
    function imprimerCodeBarresFPL() {
        var block = document.getElementById('barcode-fpl-print-area');
        if (!block) return;
        var src = block.getAttribute('data-barcode-src');
        var code = block.getAttribute('data-code') || '';
        var nom = block.getAttribute('data-nom') || 'Produit';
        if (!src) return;
        var w = window.open('', '_blank', 'width=420,height=360');
        w.document.write('<!DOCTYPE html><html><head><title>Code-barres ' + code + '</title><style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;} img{max-width:100%;height:auto;} .code{font-size:18px;font-weight:700;margin-top:12px;letter-spacing:0.08em;font-family:monospace;} h2{font-size:15px;margin:0 0 8px;text-align:center;color:#333;}</style></head><body><h2>' + nom.replace(/</g,'&lt;') + '</h2><img src="' + src + '" alt="Code-barres"><div class="code">' + code.replace(/</g,'&lt;') + '</div><p style="font-size:12px;color:#666;">Référence FPL</p></body></html>');
        w.document.close();
        w.focus();
        setTimeout(function() { w.print(); w.close(); }, 300);
    }
    function imprimerQRCode() {
        var block = document.getElementById('qr-code-print-area');
        if (!block) return;
        var qr = block.getAttribute('data-qr');
        var nom = block.getAttribute('data-nom') || 'Produit';
        var w = window.open('', '_blank', 'width=400,height=500');
        w.document.write('<!DOCTYPE html><html><head><title>QR Code - ' + nom + '</title><style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;} img{max-width:280px;height:auto;} h2{font-size:16px;margin-top:16px;text-align:center;}</style></head><body><img src="' + qr + '" alt="QR Code"><h2>' + nom + '</h2><p style="font-size:12px;color:#666;">Scannez pour voir le stock</p></body></html>');
        w.document.close();
        w.focus();
        setTimeout(function() { w.print(); w.close(); }, 300);
    }
    window.imprimerEtiquetteFPLStock = function() {
        var root = document.getElementById('fpl-etiquette-print-root');
        if (!root) return;
        var cssHref = <?php echo json_encode($fpl_etiq_css_abs, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        var baseHref = <?php echo json_encode(rtrim($origin_et, '/') . '/', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        var node = root.querySelector('.fpl-etiq');
        if (!node || !cssHref) return;

        var w = window.open('', '_blank', 'width=560,height=940');
        if (!w || !w.document) return;

        var doc = w.document;
        doc.open();
        doc.write('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Étiquette FPL</title>');
        doc.write('<base href="' + String(baseHref).replace(/"/g, '&quot;') + '">');
        doc.write('<style>');
        doc.write('html,body{margin:0;padding:12px;box-sizing:border-box;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}');
        doc.write('</style></head><body></body></html>');
        doc.close();

        var head = doc.head;
        var body = doc.body;

        var fa = doc.createElement('link');
        fa.rel = 'stylesheet';
        fa.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        head.appendChild(fa);

        body.innerHTML = node.outerHTML;

        var printed = false;
        function runPrint() {
            if (printed) return;
            printed = true;
            w.requestAnimationFrame(function () {
                setTimeout(function () {
                    try {
                        w.focus();
                        w.print();
                    } catch (e) {}
                    try {
                        w.close();
                    } catch (e2) {}
                }, 120);
            });
        }

        function whenImagesReady(cb) {
            var imgs = doc.images;
            var n = imgs.length;
            var pending = 0;
            var i;
            for (i = 0; i < n; i++) {
                if (!imgs[i].complete) pending++;
            }
            if (pending === 0) {
                cb();
                return;
            }
            function tick() {
                pending--;
                if (pending <= 0) cb();
            }
            for (i = 0; i < n; i++) {
                if (!imgs[i].complete) {
                    imgs[i].addEventListener('load', tick);
                    imgs[i].addEventListener('error', tick);
                }
            }
        }

        var sheet = doc.createElement('link');
        sheet.rel = 'stylesheet';
        sheet.href = cssHref;
        sheet.onload = function () {
            whenImagesReady(runPrint);
        };
        sheet.onerror = function () {
            whenImagesReady(runPrint);
        };
        head.appendChild(sheet);

        setTimeout(function () {
            if (printed || w.closed) return;
            try {
                if (sheet.sheet) {
                    whenImagesReady(runPrint);
                }
            } catch (e) {
                whenImagesReady(runPrint);
            }
        }, 700);
    };
    </script>
</body>

</html>