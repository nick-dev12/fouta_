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
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';
produit_formulaire_champs_ensure_schema();

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/barcode_fpl.php';
require_once __DIR__ . '/../../includes/produit_emplacement_entrepot.php';
$code_fpl_live = ensure_produit_identifiant_interne($produit_id);
if ($code_fpl_live !== null && $code_fpl_live !== '') {
    $produit['identifiant_interne'] = $code_fpl_live;
}
$emplacement_vals_sync = produit_emplacement_from_produit($produit);
if (produit_emplacement_a_des_donnees($emplacement_vals_sync)) {
    generer_barcode_produit_fpl($produit_id);
    generer_qrcode_produit($produit_id);
} elseif (get_barcode_produit_web_path($produit_id) === '') {
    generer_barcode_produit_fpl($produit_id);
}
$barcode_url = get_barcode_produit_web_path($produit_id);
$barcode_payload = !empty($produit['identifiant_interne'])
    ? produit_emplacement_barcode_payload($produit['identifiant_interne'], $emplacement_vals_sync)
    : '';

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
$stock_info_url = produit_emplacement_stock_info_url($produit_id, $produit);
$qr_file = __DIR__ . '/../../upload/qrcodes/produit_' . $produit_id . '.png';
require_once __DIR__ . '/../../includes/site_url.php';
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
require_once __DIR__ . '/../../models/model_produit_etiquette_parametres.php';
fpl_etiquette_parametres_ensure_schema();
$fpl_dims = fpl_etiquette_dims();
$fpl_dims_data = fpl_etiquette_dims_data_attrs($fpl_dims);

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
$fpl_ref_affichage = !empty($produit['identifiant_interne'])
    ? fpl_etiquette_format_ref_affichage($produit['identifiant_interne'])
    : '';
list($fpl_compat_l1, $fpl_compat_l2) = fpl_etiquette_lignes_compatibilite($produit);
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
if ($fpl_etiq_photo_abs === '') {
    $img_princ = trim((string) ($produit['image_principale'] ?? ''));
    if ($img_princ !== '') {
        $rel_p = (strpos($img_princ, 'produits/') === 0) ? $img_princ : ('produits/' . ltrim($img_princ, '/'));
        if (preg_match('#^produits/[a-zA-Z0-9_.-]+$#', $rel_p)) {
            $fs_p = __DIR__ . '/../../upload/' . $rel_p;
            if (is_file($fs_p)) {
                $fpl_etiq_photo_abs = $origin_et . '/upload/' . str_replace('\\', '/', $rel_p) . '?v=' . (int) filemtime($fs_p);
            }
        } elseif (preg_match('#^https?://#i', $img_princ)) {
            $fpl_etiq_photo_abs = $img_princ;
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

// LA FICHE PORTE LE NOM DE LA PIÈCE — comme chez FPL natif, où le titre de
// admin/piece.php est le nom de la pièce et non celui du module. De quoi
// titrer la page : le rayon d'où l'on vient, le code, le statut.
require_once __DIR__ . '/../../models/model_produit_fiche.php';
$fiche_nom = trim((string) ($produit['nom'] ?? ''));
$fiche_categorie_id = (int) ($produit['categorie_id'] ?? 0);
$fiche_categorie_nom = trim((string) ($produit['categorie_nom'] ?? ''));
$fiche_sous_categorie_id = (int) ($produit['sous_categorie_id'] ?? 0);
$fiche_sous_categorie_nom = pf_champ_visible('sous_categorie_id')
    ? produit_fiche_sous_categorie_nom($fiche_sous_categorie_id) : '';
$fiche_code = pf_champ_visible('identifiant_interne')
    ? trim((string) ($produit['identifiant_interne'] ?? '')) : '';
$fiche_statut = pf_champ_visible('statut') ? trim((string) ($produit['statut'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo fpl_e($fiche_nom); ?> - Fiche pièce - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-ajuster-stock.css'); ?>
    <?php fpl_css_link('fpl-etiquette.css'); ?>
    <?php echo fpl_etiquette_dims_style_block($fpl_dims); ?>
</head>

<body class="page-ajuster-stock-body">

    <?php include '../includes/nav.php'; ?>

    <div class="page-ajuster-stock">
        <div class="content-header dashboard-hero page-ajuster-stock-hero page-ajuster-stock-hero--compact">
            <div class="dashboard-hero-text page-ajuster-stock-hero__inner">
                <?php // LE FIL D'ARIANE — le même qu'au catalogue : il dit de quel rayon
                      // la pièce vient, et il y ramène d'un clic. ?>
                <nav class="dashboard-eyebrow fpl-fil" aria-label="Fil d’Ariane">
                    <a href="index.php">Pièces</a>
                    <?php if ($fiche_categorie_nom !== ''): ?>
                    <span class="fpl-fil__sep" aria-hidden="true">›</span>
                    <a href="index.php?categorie_id=<?php echo $fiche_categorie_id; ?>"><?php echo fpl_e($fiche_categorie_nom); ?></a>
                    <?php endif; ?>
                    <?php if ($fiche_sous_categorie_nom !== ''): ?>
                    <span class="fpl-fil__sep" aria-hidden="true">›</span>
                    <a href="index.php?categorie_id=<?php echo $fiche_categorie_id; ?>&amp;sous_categorie_id=<?php echo $fiche_sous_categorie_id; ?>"><?php echo fpl_e($fiche_sous_categorie_nom); ?></a>
                    <?php endif; ?>
                </nav>
                <div class="page-ajuster-stock-hero__row">
                    <div class="page-ajuster-stock-hero__titles">
                        <?php // Le titre de la fiche, c'est LA PIÈCE. « Ajuster le stock » était
                              // le nom du module : il ne disait pas de quoi la page parle. ?>
                        <h1 id="page-ajuster-stock-title" class="fpl-fiche-titre"><?php echo fpl_e($fiche_nom); ?></h1>
                        <p class="dashboard-subtitle page-ajuster-stock-hero__intro">
                            <?php if ($fiche_code !== ''): ?>
                            <span class="fpl-chip-code"><?php echo fpl_e(fpl_code_afficher($fiche_code)); ?></span>
                            <?php endif; ?>
                            <?php if ($fiche_statut !== '' && $fiche_statut !== 'actif'): ?>
                            <span class="fpl-fiche-statut"><?php echo $fiche_statut === 'inactif' ? 'Inactive' : fpl_e(ucfirst($fiche_statut)); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="index.php" class="btn-back page-ajuster-stock-back page-ajuster-stock-hero__back">
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
    $prod_description = trim(strip_tags((string) ($produit['description'] ?? '')));
    $prod_marque = produits_marque_libelle_from_row($produit);
    $prod_fournisseur = produits_fournisseur_nom_affichage($produit);
    $prod_ref_fournisseur = (produits_has_column('reference_fournisseur') ? trim((string) ($produit['reference_fournisseur'] ?? '')) : '');
    $meta_ref = !empty($produit['identifiant_interne']) ? trim((string) $produit['identifiant_interne']) : '';
    if (!pf_champ_visible('description')) {
        $prod_description = '';
    }
    if (!pf_champ_visible('marque_id')) {
        $prod_marque = '';
    }
    if (!pf_champ_visible('fournisseur_id')) {
        $prod_fournisseur = '';
    }
    if (!pf_champ_visible('reference_fournisseur')) {
        $prod_ref_fournisseur = '';
    }
    if (!pf_champ_visible('identifiant_interne')) {
        $meta_ref = '';
    }
    $emplacement_vals = produit_emplacement_from_produit($produit);
    if (produit_emplacement_use_referentiel() && !empty($emplacement_vals['entrepot_position_id'])) {
        $emplacement_vals = produit_emplacement_enrich_referentiel_form_values($emplacement_vals);
    }
    $emplacement_resume = produit_emplacement_resume_court($emplacement_vals);
    $a_emplacement = produit_emplacement_a_des_donnees($emplacement_vals);
    $prix_catalogue = (float) ($produit['prix'] ?? 0);
    $prix_promo_val = null;
    if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] > 0) {
        $prix_promo_val = (float) $produit['prix_promotion'];
    }
    $en_promotion = $prix_promo_val !== null && $prix_promo_val < $prix_catalogue;
    if (!pf_champ_visible('prix_promotion')) {
        $en_promotion = false;
        $prix_promo_val = null;
    }
    $show_prix_vente = pf_champ_visible('prix');
    $show_emplacement = pf_champ_visible('emplacement');
    if (!$show_emplacement) {
        $a_emplacement = false;
    }
    $galerie_urls = produits_galerie_web_urls($produit);
    if (empty($galerie_urls)) {
        $galerie_urls = ['/image/produit1.jpg'];
    }
    // Tout ce que l'on sait de la pièce, rassemblé une fois pour toutes.
    $fpl_faits = produit_fiche_faits($produit);
    ?>

    <div class="pas-showcase">
        <div class="pas-showcase__gallery">
            <div class="pas-gallery" id="pas-product-gallery">
                <div class="pas-gallery__main">
                    <img id="pas-gallery-main"
                        src="<?php echo htmlspecialchars($galerie_urls[0], ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                        class="pas-gallery__main-img"
                        onerror="this.src='/image/produit1.jpg'" loading="eager" decoding="async">
                </div>
                <?php if (count($galerie_urls) > 1): ?>
                <div class="pas-gallery__thumbs-row">
                    <button type="button" class="pas-gallery__nav pas-gallery__nav--prev" id="pas-gallery-prev" aria-label="Image précédente">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <div class="pas-gallery__thumbs-list" id="pas-gallery-thumbs-list">
                        <?php foreach ($galerie_urls as $idx => $url): ?>
                        <button type="button"
                            class="pas-gallery__thumb<?php echo $idx === 0 ? ' is-active' : ''; ?>"
                            data-index="<?php echo (int) $idx; ?>"
                            data-src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="Afficher l’image <?php echo (int) ($idx + 1); ?>"
                            aria-pressed="<?php echo $idx === 0 ? 'true' : 'false'; ?>">
                            <img src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Vue <?php echo (int) ($idx + 1); ?>"
                                onerror="this.src='/image/produit1.jpg'" loading="lazy" decoding="async">
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="pas-gallery__nav pas-gallery__nav--next" id="pas-gallery-next" aria-label="Image suivante">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="pas-showcase__panel">
<?php // Le nom de la pièce et sa référence titrent désormais la page, juste
      // au-dessus : les répéter ici en ferait un doublon. La marque, elle,
      // n'est pas perdue — elle est devenue le fait « Véhicule » de la grille. ?>

            <?php if ($show_prix_vente): ?>
            <div class="pas-price-card">
                <span class="pas-price-card__label">Prix de vente</span>
                <div class="pas-price-card__row">
                    <?php if ($en_promotion): ?>
                        <span class="pas-price-card__amount pas-price-card__amount--promo"><?php echo number_format($prix_promo_val, 0, ',', ' '); ?> FCFA</span>
                        <span class="pas-price-card__old"><?php echo number_format($prix_catalogue, 0, ',', ' '); ?> FCFA</span>
                        <span class="pas-price-card__badge">Promo</span>
                    <?php else: ?>
                        <span class="pas-price-card__amount"><?php echo number_format($prix_catalogue, 0, ',', ' '); ?> FCFA</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php // LA GRILLE DES FAITS, reprise de la fiche pièce de FPL natif : tout ce
                  // que l'on sait de la pièce, en colonnes, chaque fait sous son intitulé.
                  // La carte « Informations » n'en montrait que deux ; la liste est
                  // construite par produit_fiche_faits() et n'affiche que ce qui existe. ?>
            <?php if ($fpl_faits !== [] || $prod_description !== ''): ?>
            <div class="pas-info-card fpl-fiche-infos">
                <h3 class="pas-info-card__title"><i class="fas fa-circle-info" aria-hidden="true"></i> Informations</h3>
                <?php if ($fpl_faits !== []): ?>
                <div class="fpl-fiche-faits">
                    <?php foreach ($fpl_faits as $fpl_fait): ?>
                    <div class="fpl-fait">
                        <span class="fpl-fait__k"><?php echo htmlspecialchars($fpl_fait['k'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="fpl-fait__v"><?php echo $fpl_fait['v']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($prod_description !== ''): ?>
                <?php // La description auto tient sur plusieurs lignes (« Référence OEM… »
                      // puis « Modèle… ») : on les respecte au lieu de tout coller. ?>
                <p class="pas-preview-desc fpl-fiche-desc"><?php
                    echo nl2br(htmlspecialchars($prod_description, ENT_QUOTES, 'UTF-8'));
                ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($show_emplacement && $a_emplacement): ?>
            <section class="pas-location-hero" aria-labelledby="pas-location-title">
                <div class="pas-location-hero__banner">
                    <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                    <h3 id="pas-location-title">Position en entrepôt</h3>
                </div>
                <?php if (!empty($emplacement_vals['chemin_libelle'])): ?>
                    <p class="pas-location-hero__chemin"><?php echo htmlspecialchars((string) $emplacement_vals['chemin_libelle']); ?></p>
                <?php elseif ($emplacement_resume !== ''): ?>
                    <p class="pas-location-hero__chemin"><?php echo htmlspecialchars($emplacement_resume); ?></p>
                <?php endif; ?>
                <div class="pas-location-hero__grid">
                    <?php
                    $etapes_loc = [
                        ['col' => 'etage', 'label' => 'Niveau'],
                        ['col' => 'numero_rayon', 'label' => 'Rayon'],
                        ['col' => 'allee', 'label' => 'Allée'],
                        ['col' => 'zone_emplacement', 'label' => 'Zone'],
                        ['col' => 'barre_rayon', 'label' => 'Barre'],
                        ['col' => 'position_emplacement', 'label' => 'Position'],
                    ];
                    foreach ($etapes_loc as $etape):
                        $col = $etape['col'];
                        if (empty($emplacement_vals[$col])) {
                            continue;
                        }
                        $is_pos = ($col === 'position_emplacement');
                    ?>
                    <div class="pas-location-hero__cell<?php echo $is_pos ? ' pas-location-hero__cell--accent' : ''; ?>">
                        <span class="pas-location-hero__cell-label"><?php echo htmlspecialchars($etape['label']); ?></span>
                        <span class="pas-location-hero__cell-value"><?php echo htmlspecialchars(produit_emplacement_option_label($col, $emplacement_vals[$col])); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php else: ?>
            <div class="pas-location-empty">
                <i class="fas fa-map-pin" aria-hidden="true"></i>
                <div>
                    <strong>Aucune position en entrepôt</strong>
                    <p>Ce produit n’a pas encore d’emplacement assigné.</p>
                </div>
                <a href="modifier.php?id=<?php echo $produit_id; ?>" class="pas-location-empty__link">Définir la position</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ajuster-stock-layout page-ajuster-stock-layout pas-dashboard">
        <div class="ajuster-stock-card page-ajuster-stock-card page-ajuster-stock-card--etat pas-dashboard__stats">
            <h2 class="page-ajuster-stock-card__title"><i class="fas fa-chart-bar" aria-hidden="true"></i> État du stock</h2>
            <div class="stock-stats-grid page-ajuster-stock-stats pas-kpi-row" role="list">
                <div class="stock-stat-card stock-total page-ajuster-stock-stat pas-kpi-tile" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Total cumulé</h4>
                    <div class="value page-ajuster-stock-stat__value"><?php echo (int) $nombre_total; ?></div>
                </div>
                <div class="stock-stat-card stock-vendu page-ajuster-stock-stat pas-kpi-tile" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Vendu</h4>
                    <div class="value page-ajuster-stock-stat__value"><?php echo (int) $quantite_vendue; ?></div>
                </div>
                <div class="stock-stat-card stock-restant page-ajuster-stock-stat pas-kpi-tile" role="listitem">
                    <h4 class="page-ajuster-stock-stat__label">Restant</h4>
                    <div class="value page-ajuster-stock-stat__value"><?php echo (int) $stock_restant; ?></div>
                </div>
            </div>

            <h2 class="page-ajuster-stock-card__title page-ajuster-stock-card__title--spaced"><i class="fas fa-calculator" aria-hidden="true"></i> Valorisation</h2>
            <div class="comptabilite-grid page-ajuster-stock-compta pas-valorisation">
                <div class="comptabilite-item page-ajuster-stock-compta__item pas-valorisation__item">
                    <label class="page-ajuster-stock-compta__label">Valeur stock actuel</label>
                    <span class="montant page-ajuster-stock-compta__montant"><?php echo number_format($valeur_stock_actuel, 0, ',', ' '); ?> FCFA</span>
                    <span class="detail page-ajuster-stock-compta__detail"><?php echo (int) $stock_actuel; ?> × <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="comptabilite-item page-ajuster-stock-compta__item pas-valorisation__item">
                    <label class="page-ajuster-stock-compta__label">Chiffre d'affaires</label>
                    <span class="montant page-ajuster-stock-compta__montant"><?php echo number_format($valeur_ventes, 0, ',', ' '); ?> FCFA</span>
                    <span class="detail page-ajuster-stock-compta__detail"><?php echo (int) $quantite_vendue; ?> vendu(s)</span>
                </div>
            </div>
        </div>

        <div class="page-ajuster-stock-side pas-dashboard__form-col">
            <div class="stock-form-block page-ajuster-stock-form pas-stock-form">
                <h3 class="page-ajuster-stock-form__title"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Ajouter au stock</h3>
                <form method="POST" action="?id=<?php echo $produit_id; ?>" class="page-ajuster-stock-form__form" id="pas-stock-form">
                    <input type="hidden" name="ajuster_stock" value="1">
                    <div class="form-group page-ajuster-stock-form__field">
                        <label for="quantite_ajout">Quantité à ajouter</label>
                        <div class="pas-qty-stepper">
                            <button type="button" class="pas-qty-stepper__btn" id="pas_qty_minus" aria-label="Diminuer">−</button>
                            <input type="number" id="quantite_ajout" name="quantite_ajout" min="1" step="1" required
                                value="<?php echo htmlspecialchars($quantite_ajout_form_value !== '' ? $quantite_ajout_form_value : '1', ENT_QUOTES, 'UTF-8'); ?>"
                                inputmode="numeric" autocomplete="off" aria-describedby="quantite_ajout_aide">
                            <button type="button" class="pas-qty-stepper__btn" id="pas_qty_plus" aria-label="Augmenter">+</button>
                        </div>
                    </div>
                    <p class="page-ajuster-stock-form__field-hint" id="quantite_ajout_aide">
                        Stock actuel&nbsp;: <strong><?php echo (int) $stock_actuel; ?></strong> unité(s). La quantité saisie s’ajoute au stock existant.
                    </p>
                    <div class="pas-total-card" aria-live="polite">
                        <span class="pas-total-card__label">Valeur de l’ajout (prix vente)</span>
                        <span class="pas-total-card__value" id="pas_qty_total_value"><?php echo number_format($prix_produit * max(1, (int) ($quantite_ajout_form_value !== '' ? $quantite_ajout_form_value : 1)), 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <button type="submit" class="btn-primary page-ajuster-stock-form__submit pas-stock-form__submit">
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
                        <div class="barcode-fpl-code"><?php echo htmlspecialchars($barcode_payload !== '' ? $barcode_payload : $produit['identifiant_interne']); ?></div>
                        <?php if ($barcode_payload !== '' && strpos($barcode_payload, ';') !== false): ?>
                        <p class="barcode-fpl-emplacement page-ajuster-stock-aux__desc"><?php echo htmlspecialchars(produit_emplacement_resume_court($emplacement_vals_sync)); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="barcode-fpl-actions page-ajuster-stock-aux__actions page-ajuster-stock-code-actions">
                        <button type="button" class="btn-primary btn-print-barcode page-ajuster-stock-print-btn" onclick="imprimerCodeBarresFPL()">
                            <i class="fas fa-print" aria-hidden="true"></i> Imprimer le code-barres
                        </button>
                        <?php if ($can_pdf_barcode): ?>
                        <a href="<?php echo htmlspecialchars($pdf_barcode_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn-download-pdf page-ajuster-stock-pdf-btn" data-admin-pdf-download>
                            <i class="fas fa-file-pdf" aria-hidden="true"></i> Télécharger PDF
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($qr_code_data_uri)): ?>
                <div class="stock-form-block qr-code-block page-ajuster-stock-aux" id="qr-code-print-area" data-qr="<?php echo htmlspecialchars($qr_code_data_uri); ?>" data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-qrcode" aria-hidden="true"></i> QR code du produit</h3>
                    <p class="qr-code-desc page-ajuster-stock-aux__desc">Scannez ce QR code pour afficher les détails du stock et l’emplacement en entrepôt sur mobile.</p>
                    <div class="qr-code-wrap page-ajuster-stock-qr-wrap">
                        <img src="<?php echo htmlspecialchars($qr_code_data_uri); ?>" alt="QR Code - <?php echo htmlspecialchars($produit['nom']); ?>" class="qr-code-img" width="180" height="180">
                    </div>
                    <p class="qr-code-produit"><?php echo htmlspecialchars($produit['nom']); ?></p>
                    <?php if (produit_emplacement_a_des_donnees($emplacement_vals_sync)): ?>
                    <p class="qr-code-emplacement page-ajuster-stock-aux__desc"><?php echo htmlspecialchars(produit_emplacement_resume_court($emplacement_vals_sync)); ?></p>
                    <?php endif; ?>
                    <div class="qr-code-actions page-ajuster-stock-aux__actions page-ajuster-stock-code-actions">
                        <button type="button" class="btn-primary btn-print-qr page-ajuster-stock-print-btn" onclick="imprimerQRCode()">
                            <i class="fas fa-print" aria-hidden="true"></i> Imprimer le QR code
                        </button>
                        <?php if ($can_pdf_qrcode): ?>
                        <a href="<?php echo htmlspecialchars($pdf_qrcode_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn-download-pdf page-ajuster-stock-pdf-btn" data-admin-pdf-download>
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
            data-css-url="<?php echo htmlspecialchars($fpl_etiq_css_abs, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $fpl_dims_data; ?>>
            <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-tags" aria-hidden="true"></i> <?php echo htmlspecialchars((string) $fpl_dims['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="fpl-etiq-preview-meta"><?php echo htmlspecialchars((string) $fpl_dims['meta'], ENT_QUOTES, 'UTF-8'); ?>
                · <a href="../parametres/etiquettes-produit.php?produit_id=<?php echo (int) $produit_id; ?>">Modifier les dimensions</a>
            </p>

            <div class="fpl-etiq-preview-scale">
            <article class="fpl-etiq fpl-etiq--fixed"
                style="--fpl-accent: <?php echo htmlspecialchars($fpl_couleur_hex, ENT_QUOTES, 'UTF-8'); ?>; --fpl-accent-dark: <?php echo htmlspecialchars($fpl_dark_hex, ENT_QUOTES, 'UTF-8'); ?>;">
                <div class="fpl-etiq__header-zone">
                    <div class="fpl-etiq__band-top" aria-hidden="true"></div>
                    <div class="fpl-etiq__shield" aria-hidden="true">
                        <img src="<?php echo htmlspecialchars($fpl_shield_logo_url, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $fpl_shield_logo_ver; ?>"
                            width="120"
                            height="88"
                            alt=""
                            class="fpl-etiq__shield-logo">
                        <span class="fpl-etiq__shield-line">FOUTA POIDS LOURDS</span>
                        <span class="fpl-etiq__shield-line fpl-etiq__shield-line--small">The Solution</span>
                    </div>
                </div>
                <div class="fpl-etiq__sheet">
                    <div class="fpl-etiq__body">
                        <div class="fpl-etiq__col-left">
                            <div class="fpl-etiq__col-left-meta">
                                <div class="fpl-etiq__ref-big"><?php echo htmlspecialchars($fpl_ref_affichage !== '' ? $fpl_ref_affichage : (string) $produit['identifiant_interne'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="fpl-etiq__nom-main"><?php echo htmlspecialchars((string) $produit['nom'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="fpl-etiq__cat-muted"><?php echo htmlspecialchars($categorie_nom_etiq, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="fpl-etiq__qr-block">
                                <?php if (!empty($qr_code_data_uri)): ?>
                                <div class="fpl-etiq__qr-box">
                                    <img src="<?php echo htmlspecialchars($qr_code_data_uri, ENT_QUOTES, 'UTF-8'); ?>" width="160" height="160" alt="QR Code stock" class="fpl-etiq__qr-img">
                                </div>
                                <?php else: ?>
                                <div class="fpl-etiq__qr-fallback" role="img" aria-label="QR indisponible">QR</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="fpl-etiq__divider" aria-hidden="true"></div>
                        <div class="fpl-etiq__col-right">
                            <?php if ($fpl_compat_l1 !== '' || $fpl_compat_l2 !== ''): ?>
                            <div class="fpl-etiq__compat">
                                <?php if ($fpl_compat_l1 !== ''): ?>
                                <div class="fpl-etiq__compat-l1"><?php echo htmlspecialchars($fpl_compat_l1, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <?php if ($fpl_compat_l2 !== ''): ?>
                                <div class="fpl-etiq__compat-l2"><?php echo htmlspecialchars($fpl_compat_l2, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="fpl-etiq__photo-box">
                                <?php if ($fpl_etiq_photo_abs !== ''): ?>
                                <img src="<?php echo htmlspecialchars($fpl_etiq_photo_abs, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="fpl-etiq__photo-produit" width="200" height="140">
                                <?php endif; ?>
                            </div>
                            <div class="fpl-etiq__barcode-wrap">
                                <div class="fpl-etiq__barcode-line">
                                    <img src="<?php echo htmlspecialchars($barcode_abs_et, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $barcode_ver_et; ?>"
                                        width="192" height="72" alt="Code-barres <?php echo htmlspecialchars((string) $produit['identifiant_interne'], ENT_QUOTES, 'UTF-8'); ?>" class="fpl-etiq__barcode-img">
                                </div>
                                <div class="fpl-etiq__pcs">1 pcs</div>
                            </div>
                        </div>
                    </div>
                    <footer class="fpl-etiq__footer">
                        <div class="fpl-etiq__footer-row1">
                            <span><?php echo htmlspecialchars($footer_fpl['adr_rue'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="fpl-etiq__footer-ico" aria-hidden="true">✉</span>
                            <span><?php echo htmlspecialchars($footer_fpl['adr_bp'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fpl-etiq__footer-row2">
                            <span><span class="fpl-etiq__footer-ico" aria-hidden="true">☎</span> <?php echo htmlspecialchars($footer_fpl['tels'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><span class="fpl-etiq__footer-ico" aria-hidden="true">🌐</span> <?php echo htmlspecialchars($footer_fpl['web'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><span class="fpl-etiq__footer-ico" aria-hidden="true">✉</span> <?php echo htmlspecialchars($footer_fpl['mail'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </footer>
                </div>
            </article>
            </div>
            <div class="fpl-etiquette-print-actions page-ajuster-stock-code-actions">
                <?php if ($can_pdf_barcode): ?>
                <a href="<?php echo htmlspecialchars($pdf_barcode_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn-download-pdf page-ajuster-stock-pdf-btn" data-admin-pdf-download>
                    <i class="fas fa-file-pdf" aria-hidden="true"></i> Code-barres (PDF)
                </a>
                <?php endif; ?>
                <?php if ($can_pdf_qrcode): ?>
                <a href="<?php echo htmlspecialchars($pdf_qrcode_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn-download-pdf page-ajuster-stock-pdf-btn" data-admin-pdf-download>
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

        var mmW = parseFloat(root.getAttribute('data-fpl-w')) || 70;
        var mmH = parseFloat(root.getAttribute('data-fpl-h')) || 70;
        var sx = parseFloat(root.getAttribute('data-fpl-sx'));
        var sy = parseFloat(root.getAttribute('data-fpl-sy'));
        if (isNaN(sx) || sx <= 0) sx = mmW / 70;
        if (isNaN(sy) || sy <= 0) sy = mmH / 70;
        var sizeW = mmW + 'mm';
        var sizeH = mmH + 'mm';

        var w = window.open('', '_blank', 'width=420,height=460');
        if (!w || !w.document) return;

        var doc = w.document;
        doc.open();
        doc.write('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Étiquette FPL ' + mmW + '\u00d7' + mmH + ' mm</title>');
        doc.write('<base href="' + String(baseHref).replace(/"/g, '&quot;') + '">');
        doc.write('<style>');
        doc.write(':root{--fpl-w:' + sizeW + ';--fpl-h:' + sizeH + ';--fpl-sx:' + sx + ';--fpl-sy:' + sy + ';--fpl-s:' + Math.min(sx, sy) + '}');
        doc.write('@page{size:' + sizeW + ' ' + sizeH + ';margin:0}');
        doc.write('html,body{margin:0;padding:0;width:' + sizeW + ';height:' + sizeH + ';overflow:hidden;box-sizing:border-box;background:#fff;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}');
        doc.write('.fpl-etiq{margin:0!important;box-shadow:none!important;border:none!important;');
        doc.write('transform:scale(' + sx + ',' + sy + ')!important;transform-origin:top left!important}');
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

    (function () {
        var input = document.getElementById('quantite_ajout');
        var minus = document.getElementById('pas_qty_minus');
        var plus = document.getElementById('pas_qty_plus');
        var totalEl = document.getElementById('pas_qty_total_value');
        var unitPrice = <?php echo json_encode($prix_produit); ?>;
        if (!input) {
            return;
        }
        function formatFcfa(n) {
            return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
        }
        function updateTotal() {
            var q = parseInt(input.value, 10);
            if (isNaN(q) || q < 1) {
                q = 1;
            }
            if (totalEl) {
                totalEl.textContent = formatFcfa(q * unitPrice);
            }
        }
        function step(delta) {
            var q = parseInt(input.value, 10);
            if (isNaN(q) || q < 1) {
                q = 1;
            }
            q = Math.max(1, q + delta);
            input.value = String(q);
            updateTotal();
        }
        if (minus) {
            minus.addEventListener('click', function () { step(-1); });
        }
        if (plus) {
            plus.addEventListener('click', function () { step(1); });
        }
        input.addEventListener('input', updateTotal);
        input.addEventListener('change', updateTotal);
    })();

    (function () {
        var mainImg = document.getElementById('pas-gallery-main');
        var thumbs = document.querySelectorAll('.pas-gallery__thumb');
        var prevBtn = document.getElementById('pas-gallery-prev');
        var nextBtn = document.getElementById('pas-gallery-next');
        var list = document.getElementById('pas-gallery-thumbs-list');
        if (!mainImg || !thumbs.length) {
            return;
        }
        var currentIdx = 0;
        function setActive(idx) {
            currentIdx = idx;
            thumbs.forEach(function (thumb, i) {
                var active = i === idx;
                thumb.classList.toggle('is-active', active);
                thumb.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            var src = thumbs[idx].getAttribute('data-src');
            if (src) {
                mainImg.src = src;
            }
            if (list && thumbs[idx]) {
                var thumbEl = thumbs[idx];
                var scrollLeft = thumbEl.offsetLeft - (list.clientWidth / 2) + (thumbEl.clientWidth / 2);
                list.scrollTo({ left: Math.max(0, scrollLeft), behavior: 'smooth' });
            }
        }
        thumbs.forEach(function (thumb, idx) {
            thumb.addEventListener('click', function () {
                setActive(idx);
            });
        });
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                setActive((currentIdx - 1 + thumbs.length) % thumbs.length);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                setActive((currentIdx + 1) % thumbs.length);
            });
        }
    })();
    </script>
</body>

</html>