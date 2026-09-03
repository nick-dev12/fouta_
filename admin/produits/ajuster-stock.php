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

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
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
$show_identifiant = pf_champ_visible('identifiant_interne');
if ($show_identifiant) {
    $code_fpl_live = ensure_produit_identifiant_interne($produit_id);
    if ($code_fpl_live !== null && $code_fpl_live !== '') {
        $produit['identifiant_interne'] = $code_fpl_live;
    }
} else {
    unset($produit['identifiant_interne']);
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
/* LE CHOIX DES TAILLES (24/08, comme FPL natif) : ?etiquette_format=N rend
 * la même étiquette à la taille demandée — les formats viennent de la table
 * etiquette_formats (migrations/run_etiquette_formats.php). Sans paramètre,
 * la taille du réglage reste la règle. */
$fpl_format_courant = null;
if (!empty($_GET['etiquette_format']) && function_exists('fpl_etiquette_format_get')) {
    $fpl_f = fpl_etiquette_format_get((int) $_GET['etiquette_format'], 'piece');
    if ($fpl_f) {
        $fpl_format_courant = $fpl_f;
        $fpl_dims = fpl_etiquette_dims_pour_mm($fpl_f['largeur_mm'], $fpl_f['hauteur_mm']);
    }
}
$fpl_formats_pieces = function_exists('fpl_etiquette_formats_pieces') ? fpl_etiquette_formats_pieces() : [];
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

$etiquette_fpl_ready = $show_identifiant && ($barcode_url !== '' && !empty($produit['identifiant_interne']));
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
$can_pdf_barcode = $show_identifiant && ($barcode_url !== '' && !empty($produit['identifiant_interne']));
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
                            <?php // Le mot court sur la pastille ; la phrase entière est
                                  // dans la grille des faits, comme chez FPL natif.
                                  // « Rupture_stock » s'affichait tel quel, tiret bas compris. ?>
                            <span class="fpl-fiche-statut"><?php echo fpl_e(fpl_statut_piece_libelle($fiche_statut, false)); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php // LES ACTIONS DE LA FICHE. Chez FPL natif elles ferment la colonne
                          // d'identité ; ici la maison range les actions de page en haut à
                          // droite, comme au catalogue — on suit la maison.
                          // « Modifier » n'était atteignable que par le lien « Définir la
                          // position », et seulement quand la position manquait. ?>
                    <div class="fpl-fiche-actions">
                        <a href="modifier.php?id=<?php echo $produit_id; ?>" class="btn-primary page-ajuster-stock-hero__btn">
                            <i class="fas fa-edit" aria-hidden="true"></i> Modifier la pièce
                        </a>
                        <a href="index.php" class="btn-back page-ajuster-stock-back page-ajuster-stock-hero__back">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
                        </a>
                    </div>
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
    $show_images = pf_champ_visible('images_produit');
    $show_stock_kpis = pf_champ_visible('stock');
    $show_valorisation = pf_champ_visible('prix');
    if (!$show_emplacement) {
        $a_emplacement = false;
    }
    $galerie_urls = $show_images ? produits_galerie_web_urls($produit) : [];
    /* Le nombre de VRAIES photos, compté AVANT l'image de remplacement :
     * sinon une pièce sans photo passerait pour illustrée. */
    $fpl_nb_photos = count($galerie_urls);
    if ($show_images && empty($galerie_urls)) {
        $galerie_urls = ['/image/produit1.jpg'];
    }
    // Tout ce que l'on sait de la pièce, rassemblé une fois pour toutes.
    $fpl_faits = produit_fiche_faits($produit);

    /* OÙ LA PIÈCE EST RANGÉE — repris de la fiche de FPL natif. Une pièce peut
     * être à plusieurs endroits, avec une quantité par endroit ; la fiche ne
     * montrait jusqu'ici qu'une position unique. Les données existaient déjà
     * (stock_emplacement + la hiérarchie d'entrepôt), personne ne les lisait. */
    $fpl_emplacements = produit_emplacements($produit_id);
    $fpl_stock_nature = produit_stock_par_nature($produit_id);

    /* CE QUI MANQUE À LA FICHE — le bandeau « Compléter maintenant » de FPL
     * natif. Il dit tout de suite ce qui empêche la pièce d'être trouvée au
     * catalogue, au lieu de laisser la découverte à l'usage. */
    $fpl_manquantes = produit_infos_manquantes($produit, 0, $fpl_nb_photos);

    /* LE SEUIL D'ALERTE. FPL natif en pose un par pièce ; ici les seuils sont
     * des RÈGLES par catégorie et sous-catégorie, réglées dans Paramètres →
     * Alertes stock. On montre celle qui s'applique à CETTE pièce, plutôt que
     * d'inventer une colonne que la base n'a pas. */
    $fpl_regles_alerte = [];
    $fpl_seuil_franchi = null;
    $fichier_alertes = dirname(__DIR__, 2) . '/models/model_stock_alertes.php';
    if (is_file($fichier_alertes)) {
        require_once $fichier_alertes;
        if (function_exists('stock_alertes_get_regles_pour_produit')) {
            $fpl_regles_alerte = stock_alertes_get_regles_pour_produit($produit_id);
            foreach ($fpl_regles_alerte as $regle) {
                $seuil = (float) ($regle['seuil'] ?? 0);
                if ($seuil > 0 && $stock_actuel <= $seuil) {
                    // Le seuil le plus BAS franchi est le plus grave.
                    if ($fpl_seuil_franchi === null || $seuil < $fpl_seuil_franchi['seuil']) {
                        $fpl_seuil_franchi = ['seuil' => $seuil, 'niveau' => (string) ($regle['niveau'] ?? '')];
                    }
                }
            }
        }
    }
    ?>

    <div class="pas-showcase">
        <?php if ($show_images && !empty($galerie_urls)): ?>
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
        <?php endif; ?>

        <div class="pas-showcase__panel">
<?php // Le nom de la pièce et sa référence titrent désormais la page, juste
      // au-dessus : les répéter ici en ferait un doublon. La marque, elle,
      // n'est pas perdue — elle est devenue le fait « Véhicule » de la grille. ?>

            <?php /* CE QUI MANQUE À CETTE FICHE — bandeau de FPL natif. Il se
                     place en tête de l'identité parce que c'est une chose à
                     faire, pas une information à lire. */ ?>
            <?php if (!empty($fpl_manquantes)): ?>
            <div class="note-bleue" style="margin-bottom:var(--s4)">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <span>
                    <strong>Fiche à compléter pour le catalogue</strong> —
                    il manque <?php echo fpl_e(implode(', ', $fpl_manquantes)); ?>.
                    <a href="modifier.php?id=<?php echo $produit_id; ?>">Compléter maintenant</a>
                </span>
            </div>
            <?php endif; ?>

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
                    <p>Cette pièce n’a pas encore d’emplacement assigné.</p>
                </div>
                <a href="modifier.php?id=<?php echo $produit_id; ?>" class="pas-location-empty__link">Définir la position</a>
            </div>
            <?php endif; ?>
        </div>

        <?php /* LA TROISIÈME COLONNE — la disposition de la fiche de FPL natif :
                 la photo IDENTIFIE à gauche, l'identité DÉCRIT au centre, le
                 stock CHIFFRE à droite. C'est ce panneau qui manquait pour que
                 l'œil trouve la quantité sans descendre dans la page. */ ?>
        <div class="fiche-stock">
            <div class="stock-libelle">En stock vendable</div>
            <div class="hero-num<?php echo $stock_actuel <= 0 ? ' zero' : ''; ?>"<?php echo $stock_actuel <= 0 ? ' style="color:var(--danger)"' : ''; ?>>
                <?php echo (int) $stock_actuel; ?>
            </div>

            <?php if (!empty($fpl_stock_nature['defectueux']) && $fpl_stock_nature['defectueux'] > 0): ?>
            <div class="badge warn" style="margin-top:6px">
                <?php echo (string) (0 + $fpl_stock_nature['defectueux']); ?> en zone défectueux
            </div>
            <?php endif; ?>

            <?php /* SOUS LE SEUIL — l'alerte de FPL natif, servie par les règles
                     de ce dépôt (Paramètres → Alertes stock). On montre le seuil
                     franchi le plus bas : c'est le plus grave. */ ?>
            <?php if ($fpl_seuil_franchi !== null): ?>
            <div class="badge danger" style="margin-top:6px">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                Sous le seuil (<?php echo (string) (0 + $fpl_seuil_franchi['seuil']); ?>)
            </div>
            <?php elseif (!empty($fpl_regles_alerte)): ?>
            <?php $fpl_seuils = []; foreach ($fpl_regles_alerte as $r) { $s = (float) ($r['seuil'] ?? 0); if ($s > 0) { $fpl_seuils[] = (string) (0 + $s); } } ?>
            <?php if ($fpl_seuils !== []): ?>
            <div class="muted" style="font-size:12.5px; margin-top:6px">
                seuil d'alerte : <?php echo fpl_e(implode(' · ', $fpl_seuils)); ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($fpl_stock_nature['emplacements'])): ?>
            <?php /* CE QUI EST RÉELLEMENT RANGÉ, et l'écart s'il y en a un. Le
                     stock de la pièce et la somme des emplacements ne coïncident
                     pas tant que le rangement n'est pas saisi partout : le taire
                     ferait croire que tout est localisé. */ ?>
            <?php $fpl_range = 0 + $fpl_stock_nature['vendable'] + $fpl_stock_nature['defectueux']; ?>
            <div class="muted" style="font-size:12.5px; margin-top:6px">
                dont <strong><?php echo (string) $fpl_range; ?></strong> rangé<?php echo $fpl_range > 1 ? 's' : ''; ?>
                sur <?php echo (int) $fpl_stock_nature['emplacements']; ?> emplacement<?php echo $fpl_stock_nature['emplacements'] > 1 ? 's' : ''; ?>
            </div>
            <?php if ($fpl_range < $stock_actuel): ?>
            <div class="badge warn" style="margin-top:4px">
                <?php echo (string) ($stock_actuel - $fpl_range); ?> sans emplacement connu
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($quantite_vendue > 0): ?>
            <div class="muted" style="font-size:12.5px; margin-top:2px">
                <?php echo (int) $quantite_vendue; ?> vendu<?php echo $quantite_vendue > 1 ? 's' : ''; ?> à ce jour
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php /* OÙ ELLE EST RANGÉE — le bloc de la fiche de FPL natif. Il répond à
             la question que « Position en entrepôt » ne pouvait pas poser : la
             pièce est-elle à plusieurs endroits, et combien à chacun ?
             Lecture seule pour l'instant : corriger le stock emplacement par
             emplacement demande un chemin d'écriture que ce dépôt n'a pas
             encore (ici l'ajout est cumulatif et global). */ ?>
    <?php if (!empty($fpl_emplacements)): ?>
    <div class="ajuster-stock-card page-ajuster-stock-card" style="margin-bottom:var(--s4)">
        <h2 class="page-ajuster-stock-card__title"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Où elle est rangée</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Emplacement</th>
                        <th>Chemin complet</th>
                        <th>Type</th>
                        <th class="num">Quantité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fpl_emplacements as $fpl_empl): ?>
                    <tr>
                        <td><span class="chip-code"><?php echo fpl_e($fpl_empl['noeud_code'] !== '' ? $fpl_empl['noeud_code'] : $fpl_empl['noeud_nom']); ?></span></td>
                        <td class="muted"><?php echo $fpl_empl['chemin'] !== '' ? fpl_e($fpl_empl['chemin']) : '—'; ?></td>
                        <td>
                            <?php if (!empty($fpl_empl['est_defectueux'])): ?>
                                <span class="page-produits-badge page-produits-badge--warn">Hors vente</span>
                            <?php else: ?>
                                <span class="page-produits-badge page-produits-badge--ok">Vendable</span>
                            <?php endif; ?>
                        </td>
                        <td class="num">
                            <span class="qty <?php echo $fpl_empl['quantite'] <= 0 ? 'zero' : ''; ?>"><?php
                                echo (string) (0 + $fpl_empl['quantite']);
                            ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="help" style="margin-top:var(--s2)">
            <?php echo (int) $fpl_stock_nature['emplacements']; ?> emplacement(s) ·
            <strong><?php echo (string) (0 + $fpl_stock_nature['vendable']); ?></strong> vendable(s)
            <?php if ($fpl_stock_nature['defectueux'] > 0): ?>
                · <strong><?php echo (string) (0 + $fpl_stock_nature['defectueux']); ?></strong> en zone défectueux
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="ajuster-stock-layout page-ajuster-stock-layout pas-dashboard">
        <div class="ajuster-stock-card page-ajuster-stock-card page-ajuster-stock-card--etat pas-dashboard__stats">
            <h2 class="page-ajuster-stock-card__title"><i class="fas fa-chart-bar" aria-hidden="true"></i> État du stock</h2>
            <?php if ($show_stock_kpis): ?>
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
            <?php endif; ?>

            <?php if ($show_valorisation): ?>
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
            <?php endif; ?>
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
                        <?php if ($show_stock_kpis): ?>
                        Stock actuel&nbsp;: <strong><?php echo (int) $stock_actuel; ?></strong> unité(s). La quantité saisie s’ajoute au stock existant.
                        <?php else: ?>
                        La quantité saisie s’ajoute au stock existant.
                        <?php endif; ?>
                    </p>
                    <?php if ($show_valorisation): ?>
                    <div class="pas-total-card" aria-live="polite">
                        <span class="pas-total-card__label">Valeur de l’ajout (prix vente)</span>
                        <span class="pas-total-card__value" id="pas_qty_total_value"><?php echo number_format($prix_produit * max(1, (int) ($quantite_ajout_form_value !== '' ? $quantite_ajout_form_value : 1)), 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn-primary page-ajuster-stock-form__submit pas-stock-form__submit">
                        <i class="fas fa-check" aria-hidden="true"></i> Enregistrer le stock
                    </button>
                </form>
            </div>

            <?php if (!$etiquette_fpl_ready && $show_identifiant): ?>
                <?php if (!empty($barcode_url) && !empty($produit['identifiant_interne'])): ?>
                <div class="stock-form-block barcode-fpl-block page-ajuster-stock-aux" id="barcode-fpl-print-area"
                    data-barcode-src="<?php echo htmlspecialchars($barcode_url); ?>"
                    data-code="<?php echo htmlspecialchars($produit['identifiant_interne']); ?>"
                    data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-barcode" aria-hidden="true"></i> Code-barres (réf. FPL)</h3>
                    <p class="barcode-fpl-desc page-ajuster-stock-aux__desc">Code <strong>Code 128</strong> : même référence que sur l’étiquette de la pièce. Utilisable avec un scanner ou l’API <code>/api/produit_par_code_fpl.php</code>.</p>
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
                <?php if ($show_identifiant && !empty($qr_code_data_uri)): ?>
                <div class="stock-form-block qr-code-block page-ajuster-stock-aux" id="qr-code-print-area" data-qr="<?php echo htmlspecialchars($qr_code_data_uri); ?>" data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-qrcode" aria-hidden="true"></i> QR code de la pièce</h3>
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

    <?php if ($etiquette_fpl_ready && $show_identifiant): ?>
    <div class="fpl-etiquette-fixed-scroll">
        <div class="stock-form-block page-ajuster-stock-aux fpl-etiquette-sheet-wrap fpl-etiquette-sheet-wrap--fullwidth" id="fpl-etiquette-print-root"
            data-css-url="<?php echo htmlspecialchars($fpl_etiq_css_abs, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $fpl_dims_data; ?>>
            <?php /* LE TITRE DIT CE QUE C'EST, PAS SES MESURES (31/08) : il
                     annonçait « Étiquette FPL 70×70 mm » et la ligne dessous
                     récitait le format, le modèle d'imprimante et le nombre de
                     points — de la fiche technique là où l'on vient chercher
                     l'étiquette d'une pièce. Les tailles restent choisies juste
                     en dessous, en toutes lettres. */ ?>
            <h3 class="page-ajuster-stock-aux__title"><i class="fas fa-tags" aria-hidden="true"></i> Étiquette de la pièce</h3>
            <?php /* LE RÉGLAGE DES DIMENSIONS N'EST PAS DE TOUS LES PROFILS
                     (31/08) : cette page est réservée au Responsable stock
                     (décision du 24/08) — le rayonniste qui cliquait était
                     renvoyé sur son accueil sans un mot. On ne montre plus le
                     lien à qui ne peut pas l'ouvrir ; c'est le geste de FPL
                     natif, qui enveloppe le même lien dans
                     admin_can('stock.configurer'). La ligne technique qui le
                     précédait (format, imprimante, points) est retirée : elle
                     n'apprenait rien à qui vient imprimer une étiquette. */ ?>
            <?php if (admin_route_is_allowed($_SESSION['admin_role'] ?? 'admin', 'parametres/etiquettes-produit.php')) : ?>
            <p class="fpl-etiq-preview-meta"><a href="../parametres/etiquettes-produit.php?produit_id=<?php echo (int) $produit_id; ?>">Modifier les dimensions</a></p>
            <?php endif; ?>
            <?php if ($fpl_formats_pieces !== []) : ?>
            <?php /* LES TAILLES AU CHOIX — un clic re-rend l'étiquette à la
                     taille voulue, comme chez FPL natif. */ ?>
            <div class="fpl-etiq-formats" role="group" aria-label="Taille de l'étiquette">
                <span class="fpl-etiq-formats__label">Taille :</span>
                <a class="fpl-etiq-format<?php echo $fpl_format_courant === null ? ' fpl-etiq-format--on' : ''; ?>"
                   href="ajuster-stock.php?id=<?php echo (int) $produit_id; ?>#fpl-etiquette-print-root">Réglage (<?php echo htmlspecialchars(fpl_etiquette_dims_label_short(fpl_etiquette_dims()), ENT_QUOTES, 'UTF-8'); ?>)</a>
                <?php foreach ($fpl_formats_pieces as $fmt) : ?>
                <a class="fpl-etiq-format<?php echo $fpl_format_courant !== null && (int) $fpl_format_courant['id'] === (int) $fmt['id'] ? ' fpl-etiq-format--on' : ''; ?>"
                   href="ajuster-stock.php?id=<?php echo (int) $produit_id; ?>&amp;etiquette_format=<?php echo (int) $fmt['id']; ?>#fpl-etiquette-print-root"><?php echo htmlspecialchars((string) $fmt['nom'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endforeach; ?>
                <?php /* Le PDF sort à la taille EXACTE : à archiver, à envoyer,
                         ou à confier à un imprimeur (dessin de la maquette 14/08). */ ?>
                <a class="fpl-etiq-format fpl-etiq-format--pdf"
                   href="etiquette-piece-pdf.php?id=<?php echo (int) $produit_id; ?><?php echo $fpl_format_courant !== null ? '&amp;format=' . (int) $fpl_format_courant['id'] : ''; ?>">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i>&nbsp;Télécharger en PDF
                </a>
            </div>
            <style>
                .fpl-etiq-formats { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 6px 0 12px; }
                .fpl-etiq-formats__label { font-size: 12.5px; font-weight: 600; color: var(--slate, #5A6A85); }
                .fpl-etiq-format {
                    display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 999px;
                    border: 1.5px solid var(--line, #DFE4EC); background: #fff; color: var(--ink, #1c2733);
                    font-size: 12.5px; font-weight: 600; text-decoration: none;
                }
                .fpl-etiq-format:hover { border-color: var(--blue, #2957ae); text-decoration: none; }
                .fpl-etiq-format--on { border-color: var(--navy, #10316f); background: var(--blue-tint, #eef3fd); color: var(--navy, #10316f); }
            </style>
            <?php endif; ?>

            <?php /* LE NOUVEAU DESSIN (01/09) : l'étiquette n'est plus un
                     assemblage HTML — c'est L'IMAGE du moteur partagé
                     (includes/etiquette_fpl70.php), celle-là même qui part au
                     PDF. Ce que l'écran montre est ce que l'imprimante sort,
                     au pixel — vérifié en Python contre le PDF validé.
                     DEPUIS LE 03/09, l'image suit la TAILLE choisie : elle rend
                     la page entière aux mm du format (carré posé au côté
                     court, centré — la géométrie du PDF et de l'atelier), et
                     s'affiche à l'échelle des mm réels (430 px ≡ 70 mm) :
                     changer de pastille change VISIBLEMENT l'étiquette. */ ?>
            <?php
            $fpl_img_lmm = (float) $fpl_dims['largeur_mm'];
            $fpl_img_hmm = (float) $fpl_dims['hauteur_mm'];
            $fpl_img_min = max(1.0, min($fpl_img_lmm, $fpl_img_hmm));
            $fpl_img_w = max(1080, (int) round(1080 * $fpl_img_lmm / $fpl_img_min));
            $fpl_img_h = max(1080, (int) round(1080 * $fpl_img_hmm / $fpl_img_min));
            $fpl_img_css = max(240, min(560, (int) round(430 * $fpl_img_lmm / 70)));
            ?>
            <div class="fpl-etiq70-apercu">
                <img id="fpl-etiq70-img"
                    src="etiquette-piece-image.php?id=<?php echo (int) $produit_id; ?>&amp;cote=1080<?php echo $fpl_format_courant !== null ? '&amp;format=' . (int) $fpl_format_courant['id'] : ''; ?>"
                    width="<?php echo $fpl_img_w; ?>" height="<?php echo $fpl_img_h; ?>"
                    alt="Étiquette de la pièce <?php echo htmlspecialchars((string) $produit['identifiant_interne'], ENT_QUOTES, 'UTF-8'); ?>"
                    style="width: min(<?php echo $fpl_img_css; ?>px, 100%); height: auto; display: block; border-radius: 12px; box-shadow: 0 10px 26px rgba(16, 49, 111, .14);">
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
            <p class="page-ajuster-stock-mouv__empty">Aucun mouvement enregistré pour cette pièce.</p>
        <?php else: ?>
            <div class="mouvements-produit-table-wrap page-ajuster-stock-mouv-table-wrap" tabindex="0" role="region" aria-label="Tableau des mouvements de stock">
                <table class="mouvements-produit-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Type</th>
                            <?php // OÙ le mouvement a eu lieu — colonne de la fiche de FPL natif.
                                  // Un transfert dit son trajet, les autres leur lieu. ?>
                            <th scope="col">Emplacement</th>
                            <th scope="col">Quantité</th>
                            <th scope="col">Avant</th>
                            <th scope="col">Après</th>
                            <th scope="col">Référence</th>
                            <th scope="col">Notes</th>
                            <?php // « Par » : la colonne de FPL natif. Un mouvement de stock sans
                                  // son auteur n'est pas une trace, c'est une rumeur. ?>
                            <th scope="col">Par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements as $m): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></td>
                                <td>
                                    <?php
                                    $badge = 'badge-' . $m['type'];
                                    // Les QUATRE types de la table, chacun sous son nom : un
                                    // transfert s'affichait « Inventaire », faute d'un cas à lui.
                                    $labels = [
                                        'entree' => 'Entrée',
                                        'sortie' => 'Sortie',
                                        'inventaire' => 'Inventaire',
                                        'transfert' => 'Transfert',
                                    ];
                                    $label = $labels[$m['type']] ?? ucfirst((string) $m['type']);
                                    ?>
                                    <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                                </td>
                                <td class="muted"><?php
                                    /* Un transfert montre son TRAJET (d'où → vers où) ; les
                                     * autres mouvements montrent le lieu concerné. Les deux
                                     * colonnes existaient déjà en base, rien n'allait les lire. */
                                    $src = trim((string) ($m['source_code'] ?? '')) !== ''
                                        ? (string) $m['source_code'] : (string) ($m['source_nom'] ?? '');
                                    $dst = trim((string) ($m['destination_code'] ?? '')) !== ''
                                        ? (string) $m['destination_code'] : (string) ($m['destination_nom'] ?? '');
                                    if ($m['type'] === 'transfert' && ($src !== '' || $dst !== '')) {
                                        echo fpl_e(($src !== '' ? $src : '?') . ' → ' . ($dst !== '' ? $dst : '?'));
                                    } else {
                                        $lieu = $dst !== '' ? $dst : $src;
                                        echo $lieu !== '' ? fpl_e($lieu) : '—';
                                    }
                                ?></td>
                                <td><?php echo (int) $m['quantite']; ?></td>
                                <td><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></td>
                                <td><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></td>
                                <td><?php echo fpl_e($m['reference_numero'] ?? ($m['reference_type'] ?? '-')); ?>
                                </td>
                                <td><?php echo fpl_e($m['notes'] ?? ''); ?></td>
                                <td><?php
                                    $auteur = produit_fiche_admin_nom($m['admin_id'] ?? 0);
                                    echo $auteur !== '' ? fpl_e($auteur) : '—';
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mouvements-produit-cards">
                <?php foreach ($mouvements as $m):
                    // La version téléphone de l'historique : mêmes libellés, même
                    // réparation d'affichage et même auteur que le tableau.
                    $badge = 'badge-' . $m['type'];
                    $labels = [
                        'entree' => 'Entrée',
                        'sortie' => 'Sortie',
                        'inventaire' => 'Inventaire',
                        'transfert' => 'Transfert',
                    ];
                    $label = $labels[$m['type']] ?? ucfirst((string) $m['type']);
                    $ref = fpl_e($m['reference_numero'] ?? ($m['reference_type'] ?? '-'));
                    $auteur_carte = produit_fiche_admin_nom($m['admin_id'] ?? 0);
                ?>
                <div class="mouvement-produit-card">
                    <div class="mouvement-produit-card-header">
                        <span class="mouvement-produit-card-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></span>
                        <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                    </div>
                    <div class="mouvement-produit-card-body">
                        <?php // La version téléphone dit l'emplacement elle aussi : ne pas
                              // le montrer sur petit écran reviendrait à deux histoires. ?>
                        <?php
                        $src = trim((string) ($m['source_code'] ?? '')) !== ''
                            ? (string) $m['source_code'] : (string) ($m['source_nom'] ?? '');
                        $dst = trim((string) ($m['destination_code'] ?? '')) !== ''
                            ? (string) $m['destination_code'] : (string) ($m['destination_nom'] ?? '');
                        $lieu_carte = ($m['type'] === 'transfert' && ($src !== '' || $dst !== ''))
                            ? (($src !== '' ? $src : '?') . ' → ' . ($dst !== '' ? $dst : '?'))
                            : ($dst !== '' ? $dst : $src);
                        ?>
                        <?php if ($lieu_carte !== ''): ?>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Emplacement</span>
                            <span class="value"><?php echo fpl_e($lieu_carte); ?></span>
                        </div>
                        <?php endif; ?>
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
                        <?php if ($auteur_carte !== ''): ?>
                        <div class="mouvement-produit-card-row">
                            <span class="label">Par</span>
                            <span class="value"><?php echo fpl_e($auteur_carte); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($m['notes'])): ?>
                    <div class="mouvement-produit-card-notes"><?php echo fpl_e($m['notes']); ?></div>
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
        var nom = block.getAttribute('data-nom') || 'Pièce';
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
        var nom = block.getAttribute('data-nom') || 'Pièce';
        var w = window.open('', '_blank', 'width=400,height=500');
        w.document.write('<!DOCTYPE html><html><head><title>QR Code - ' + nom + '</title><style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;} img{max-width:280px;height:auto;} h2{font-size:16px;margin-top:16px;text-align:center;}</style></head><body><img src="' + qr + '" alt="QR Code"><h2>' + nom + '</h2><p style="font-size:12px;color:#666;">Scannez pour voir le stock</p></body></html>');
        w.document.close();
        w.focus();
        setTimeout(function() { w.print(); w.close(); }, 300);
    }
    window.imprimerEtiquetteFPLStock = function() {
        var root = document.getElementById('fpl-etiquette-print-root');
        if (!root) return;
        /* La trace part en tâche de fond : la page « Toutes les étiquettes »
           saura que celle-ci est imprimée, par qui et quand (24/08). */
        try {
            fetch('ajax_etiquette_imprimee.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ type: 'piece', id: <?php echo (int) $produit_id; ?>, format_id: <?php echo $fpl_format_courant !== null ? (int) $fpl_format_courant['id'] : 'null'; ?>, _jeton: <?php echo json_encode((string) $_SESSION['admin_csrf']); ?> })
            });
        } catch (e) { /* la trace ne bloque jamais l'impression */ }
        var img = document.getElementById('fpl-etiq70-img');
        if (!img || !img.src) return;

        var mmW = parseFloat(root.getAttribute('data-fpl-w')) || 70;
        var mmH = parseFloat(root.getAttribute('data-fpl-h')) || 70;
        /* le dessin est carré : il prend le côté COURT de la page, centré —
           le geste de l'atelier de la direction, le même que le PDF */
        var mmCote = Math.min(mmW, mmH);

        var w = window.open('', '_blank', 'width=420,height=460');
        if (!w || !w.document) return;

        var doc = w.document;
        doc.open();
        doc.write('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Étiquette FPL ' + mmW + '\u00d7' + mmH + ' mm</title>');
        doc.write('<style>');
        doc.write('@page{size:' + mmW + 'mm ' + mmH + 'mm;margin:0}');
        doc.write('html,body{margin:0;padding:0;width:' + mmW + 'mm;height:' + mmH + 'mm;overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}');
        doc.write('img{width:' + mmCote + 'mm;height:' + mmCote + 'mm;display:block;}');
        doc.write('</style></head><body><img src="' + String(img.src).replace(/"/g, '&quot;') + '" alt=""></body></html>');
        doc.close();

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
        var pimg = doc.images.length ? doc.images[0] : null;
        if (pimg && !pimg.complete) {
            pimg.addEventListener('load', runPrint);
            pimg.addEventListener('error', runPrint);
            setTimeout(runPrint, 1500);
        } else {
            runPrint();
        }
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