<?php
/**
 * Export PDF catalogue produits (période, filtres).
 */

require_once __DIR__ . '/entreprise_config.php';
require_once __DIR__ . '/site_url.php';

/** @var string|null Dernière erreur export catalogue PDF */
$GLOBALS['export_catalogue_pdf_last_error'] = null;

/**
 * @param string $message
 */
function export_catalogue_pdf_set_error($message)
{
    $GLOBALS['export_catalogue_pdf_last_error'] = (string) $message;
}

/**
 * @return string|null
 */
function export_catalogue_pdf_get_last_error()
{
    return isset($GLOBALS['export_catalogue_pdf_last_error']) ? $GLOBALS['export_catalogue_pdf_last_error'] : null;
}

/**
 * @return string
 */
function export_catalogue_pdf_escape($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * Libellés du filtre « Type » (alignés sur export-catalogue.php).
 *
 * @return array<string, string>
 */
function export_catalogue_pdf_mode_labels()
{
    return [
        'complet' => 'Tous les produits',
        'ajout' => 'Produits ajoutés (période)',
        'modification' => 'Produits modifiés (période)',
        'tous' => 'Ajouts et modifications (période)',
    ];
}

/**
 * @param string $mode
 * @return string
 */
function export_catalogue_pdf_mode_label($mode)
{
    $labels = export_catalogue_pdf_mode_labels();
    $mode = (string) $mode;

    return $labels[$mode] ?? $labels['tous'];
}

/**
 * @param string $date_ymd Y-m-d
 * @return string
 */
function export_catalogue_pdf_format_date($date_ymd)
{
    $date_ymd = trim((string) $date_ymd);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) {
        return $date_ymd;
    }

    return date('d/m/Y', strtotime($date_ymd));
}

/**
 * Bloc HTML des filtres actifs (intitulés identiques au formulaire admin).
 *
 * @param array<string, mixed> $meta
 * @return string
 */
function export_catalogue_build_filtres_header_html(array $meta)
{
    $mode = (string) ($meta['mode'] ?? 'tous');
    $mode_label = trim((string) ($meta['mode_label'] ?? ''));
    if ($mode_label === '') {
        $mode_label = export_catalogue_pdf_mode_label($mode);
    }

    $lines = [];
    $lines[] = '<p class="doc-filtre"><span class="doc-filtre__label">Type</span> '
        . '<span class="doc-filtre__value">' . export_catalogue_pdf_escape($mode_label) . '</span></p>';

    if (!empty($meta['show_categorie_filtre'])) {
        $cat = trim((string) ($meta['categorie_nom'] ?? ''));
        if ($cat === '') {
            $cat = 'Toutes les catégories';
        }
        $lines[] = '<p class="doc-filtre"><span class="doc-filtre__label">Catégorie</span> '
            . '<span class="doc-filtre__value">' . export_catalogue_pdf_escape($cat) . '</span></p>';
    }

    if (!empty($meta['show_marque_filtre'])) {
        $marque = trim((string) ($meta['marque_nom'] ?? ''));
        if ($marque === '') {
            $marque = 'Toutes les marques';
        }
        $lines[] = '<p class="doc-filtre"><span class="doc-filtre__label">Marque</span> '
            . '<span class="doc-filtre__value">' . export_catalogue_pdf_escape($marque) . '</span></p>';
    }

    if (!empty($meta['show_fournisseur_filtre'])) {
        $four = trim((string) ($meta['fournisseur_nom'] ?? ''));
        if ($four === '') {
            $four = 'Tous les fournisseurs';
        }
        $lines[] = '<p class="doc-filtre"><span class="doc-filtre__label">Fournisseur</span> '
            . '<span class="doc-filtre__value">' . export_catalogue_pdf_escape($four) . '</span></p>';
    }

    if (!empty($meta['recherche'])) {
        $lines[] = '<p class="doc-filtre"><span class="doc-filtre__label">Recherche</span> '
            . '<span class="doc-filtre__value">« ' . export_catalogue_pdf_escape($meta['recherche']) . ' »</span></p>';
    }

    if ($mode !== 'complet') {
        $debut = export_catalogue_pdf_format_date($meta['date_debut'] ?? '');
        $fin = export_catalogue_pdf_format_date($meta['date_fin'] ?? '');
        $lines[] = '<p class="doc-filtre"><span class="doc-filtre__label">Période</span> '
            . '<span class="doc-filtre__value">Du <strong>' . export_catalogue_pdf_escape($debut)
            . '</strong> au <strong>' . export_catalogue_pdf_escape($fin) . '</strong></span></p>';
    } else {
        $lines[] = '<p class="doc-filtre"><span class="doc-filtre__label">Période</span> '
            . '<span class="doc-filtre__value">Toutes dates</span></p>';
    }

    return implode("\n        ", $lines);
}

/**
 * Titre principal du document selon le type de filtre.
 *
 * @param array<string, mixed> $meta
 * @return string
 */
function export_catalogue_pdf_doc_title(array $meta)
{
    $mode_label = trim((string) ($meta['mode_label'] ?? ''));
    if ($mode_label === '') {
        $mode_label = export_catalogue_pdf_mode_label((string) ($meta['mode'] ?? 'tous'));
    }

    return 'Export catalogue — ' . $mode_label;
}

/**
 * Miniature JPEG (data URI) pour limiter la taille du HTML Dompdf.
 *
 * @param string $full_path
 * @param int $max_px
 * @return string
 */
function export_catalogue_image_file_to_thumb_data_uri($full_path, $max_px = 64)
{
    $raw = @file_get_contents($full_path);
    if ($raw === false || $raw === '') {
        return '';
    }

    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        if (strlen($raw) <= 120000) {
            $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');

            return 'data:' . $mime . ';base64,' . base64_encode($raw);
        }

        return '';
    }

    $src = @imagecreatefromstring($raw);
    if ($src === false) {
        return '';
    }

    $width = imagesx($src);
    $height = imagesy($src);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($src);

        return '';
    }

    $scale = min($max_px / $width, $max_px / $height, 1.0);
    $new_w = max(1, (int) round($width * $scale));
    $new_h = max(1, (int) round($height * $scale));

    $dst = imagecreatetruecolor($new_w, $new_h);
    if ($dst === false) {
        imagedestroy($src);

        return '';
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
    imagedestroy($src);

    ob_start();
    imagejpeg($dst, null, 72);
    $jpeg = ob_get_clean();
    imagedestroy($dst);

    if ($jpeg === false || $jpeg === '') {
        return '';
    }

    return 'data:image/jpeg;base64,' . base64_encode($jpeg);
}

/**
 * @param array<string, mixed> $produit
 * @return string data URI ou chaîne vide
 */
function export_catalogue_produit_image_data_uri(array $produit)
{
    $img = trim((string) ($produit['image_principale'] ?? ''));
    if ($img === '') {
        return '';
    }

    $root = realpath(__DIR__ . '/..');
    $full = realpath(__DIR__ . '/../upload/' . str_replace(['\\', '..'], ['/', ''], $img));
    if ($root === false || $full === false || !is_file($full) || strpos($full, $root) !== 0) {
        return '';
    }

    return export_catalogue_image_file_to_thumb_data_uri($full, 64);
}

/**
 * @return string data URI logo entreprise
 */
function export_catalogue_logo_data_uri()
{
    $name = get_site_logo_relative_filename();
    if ($name === '') {
        return '';
    }
    $full = realpath(__DIR__ . '/../image/' . $name);
    if ($full === false || !is_file($full)) {
        return '';
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
    $raw = @file_get_contents($full);
    if ($raw === false || $raw === '') {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

/**
 * @param array<string, mixed> $produit
 * @return string
 */
function export_catalogue_format_prix_fcfa($value, $with_suffix = false)
{
    if ($value === null || $value === '') {
        return '—';
    }
    $formatted = number_format((float) $value, 0, ',', ' ');

    return $with_suffix ? $formatted . ' FCFA' : $formatted;
}

/**
 * Prix pour l’export catalogue : tiret si absent ou ≤ 0.
 *
 * @param mixed $value
 * @return string
 */
function export_catalogue_format_prix_fcfa_export($value, $with_suffix = true)
{
    if ($value === null || $value === '' || (float) $value <= 0) {
        return '—';
    }

    return export_catalogue_format_prix_fcfa($value, $with_suffix);
}

/**
 * Stock pour l’export catalogue : tiret si absent, sinon entier.
 *
 * @param mixed $value
 * @return string
 */
function export_catalogue_format_stock_export($value)
{
    if ($value === null || $value === '') {
        return '—';
    }

    return (string) (int) $value;
}

/**
 * @param array<string, mixed> $produit
 * @return string|null null si colonne absente
 */
function export_catalogue_produit_prix_achat_affichage(array $produit)
{
    if (!function_exists('produits_has_column') || !produits_has_column('prix_achat')) {
        return null;
    }

    return export_catalogue_format_prix_fcfa_export($produit['prix_achat'] ?? null, true);
}

/**
 * @return bool
 */
function export_catalogue_has_prix_achat_column()
{
    return function_exists('produits_has_column') && produits_has_column('prix_achat');
}

/**
 * Définition des colonnes du tableau PDF (largeurs explicites pour Dompdf).
 *
 * @return array<int, array{key: string, label: string, width: string, class: string}>
 */
function export_catalogue_pdf_table_columns($has_prix_achat)
{
    $cols = [
        ['key' => 'img', 'label' => 'Image', 'width' => '5%', 'class' => 'col-img'],
        ['key' => 'nom', 'label' => 'Produit', 'width' => '18%', 'class' => 'col-nom'],
        ['key' => 'cat', 'label' => 'Catégorie', 'width' => '9%', 'class' => 'col-text'],
        ['key' => 'four', 'label' => 'Fournisseur', 'width' => '9%', 'class' => 'col-text'],
    ];

    if ($has_prix_achat) {
        $cols[] = ['key' => 'prix_achat', 'label' => 'Prix achat', 'width' => '10%', 'class' => 'col-num col-prix-achat'];
        $cols[] = ['key' => 'prix', 'label' => 'Prix vente', 'width' => '10%', 'class' => 'col-num col-prix'];
        $cols[] = ['key' => 'promo', 'label' => 'Promo', 'width' => '9%', 'class' => 'col-num col-promo'];
        $cols[] = ['key' => 'stock', 'label' => 'Stock', 'width' => '8%', 'class' => 'col-num col-stock'];
        $cols[] = ['key' => 'creation', 'label' => 'Création', 'width' => '11%', 'class' => 'col-date'];
        $cols[] = ['key' => 'modification', 'label' => 'Modification', 'width' => '11%', 'class' => 'col-date'];
    } else {
        $cols[1]['width'] = '22%';
        $cols[] = ['key' => 'prix', 'label' => 'Prix vente', 'width' => '10%', 'class' => 'col-num col-prix'];
        $cols[] = ['key' => 'promo', 'label' => 'Promo', 'width' => '8%', 'class' => 'col-num col-promo'];
        $cols[] = ['key' => 'stock', 'label' => 'Stock', 'width' => '8%', 'class' => 'col-num col-stock'];
        $cols[] = ['key' => 'creation', 'label' => 'Création', 'width' => '12%', 'class' => 'col-date'];
        $cols[] = ['key' => 'modification', 'label' => 'Modification', 'width' => '12%', 'class' => 'col-date'];
    }

    return $cols;
}

/**
 * @param string $tag th|td
 * @param string $width
 * @param string $class
 * @param string $inner_html
 * @return string
 */
function export_catalogue_pdf_table_cell($tag, $width, $class, $inner_html)
{
    $tag = $tag === 'th' ? 'th' : 'td';
    $class_attr = $class !== '' ? ' class="' . export_catalogue_pdf_escape($class) . '"' : '';
    $style = 'width:' . $width;
    if (strpos($class, 'col-num') !== false) {
        $style .= ';text-align:center';
    }
    $style_attr = ' style="' . export_catalogue_pdf_escape($style) . '"';

    return '<' . $tag . $class_attr . $style_attr . '>' . $inner_html . '</' . $tag . '>';
}

/**
 * Contenu HTML des cellules d’une ligne produit (ordre identique aux colonnes).
 *
 * @param array<string, mixed> $produit
 * @return array<string, string>
 */
function export_catalogue_pdf_row_cell_contents(array $produit, $has_prix_achat)
{
    $img_uri = export_catalogue_produit_image_data_uri($produit);
    $img_cell = $img_uri !== ''
        ? '<img src="' . export_catalogue_pdf_escape($img_uri) . '" alt="" class="prod-img">'
        : '<span class="prod-no-img">—</span>';

    $ident = trim((string) ($produit['identifiant_interne'] ?? ''));
    $marque = function_exists('produits_marque_libelle_from_row') ? produits_marque_libelle_from_row($produit) : '';
    $fourn = function_exists('produits_fournisseur_nom_affichage') ? produits_fournisseur_nom_affichage($produit) : '';
    $prix = export_catalogue_format_prix_fcfa_export($produit['prix'] ?? null, true);
    $promo = export_catalogue_format_prix_fcfa_export($produit['prix_promotion'] ?? null, true);
    $dc = !empty($produit['date_creation']) ? date('d/m/Y H:i', strtotime((string) $produit['date_creation'])) : '—';
    $dm = !empty($produit['date_modification']) ? date('d/m/Y H:i', strtotime((string) $produit['date_modification'])) : '—';

    $nom_html = '<strong>' . export_catalogue_pdf_escape($produit['nom'] ?? '') . '</strong>';
    if ($ident !== '') {
        $nom_html .= '<br><span class="muted">Réf. ' . export_catalogue_pdf_escape($ident) . '</span>';
    }
    if ($marque !== '') {
        $nom_html .= '<br><span class="muted">' . export_catalogue_pdf_escape($marque) . '</span>';
    }

    $cells = [
        'img' => $img_cell,
        'nom' => $nom_html,
        'cat' => export_catalogue_pdf_escape($produit['categorie_nom'] ?? '—'),
        'four' => export_catalogue_pdf_escape($fourn !== '' ? $fourn : '—'),
        'prix' => export_catalogue_pdf_escape($prix),
        'promo' => export_catalogue_pdf_escape($promo),
        'stock' => export_catalogue_pdf_escape(export_catalogue_format_stock_export($produit['stock'] ?? null)),
        'creation' => export_catalogue_pdf_escape($dc),
        'modification' => export_catalogue_pdf_escape($dm),
    ];

    if ($has_prix_achat) {
        $cells['prix_achat'] = export_catalogue_pdf_escape(
            export_catalogue_format_prix_fcfa_export($produit['prix_achat'] ?? null, true)
        );
    }

    return $cells;
}

/**
 * @param array<int, array<string, mixed>> $produits
 * @param array<string, mixed> $meta date_debut, date_fin, mode, recherche, total
 * @return string HTML
 */
function export_catalogue_build_pdf_html(array $produits, array $meta, $on_progress = null)
{
    $ent = get_entreprise_config();
    $logo = export_catalogue_logo_data_uri();
    $has_prix_achat = export_catalogue_has_prix_achat_column();
    $columns = export_catalogue_pdf_table_columns($has_prix_achat);
    $col_count = count($columns);

    $doc_title = export_catalogue_pdf_doc_title($meta);
    $filtres_header_html = export_catalogue_build_filtres_header_html($meta);
    $genere_le = export_catalogue_pdf_escape(date('d/m/Y H:i'));
    $total = (int) ($meta['total'] ?? count($produits));
    $count = count($produits);

    $header_cells = '';
    foreach ($columns as $col) {
        $header_cells .= export_catalogue_pdf_table_cell('th', $col['width'], $col['class'], export_catalogue_pdf_escape($col['label']));
    }

    $rows_html = '';
    $i = 0;
    foreach ($produits as $p) {
        $i++;
        $cell_contents = export_catalogue_pdf_row_cell_contents($p, $has_prix_achat);
        $row_cells = '';
        foreach ($columns as $col) {
            $key = $col['key'];
            $inner = $cell_contents[$key] ?? '—';
            $row_cells .= export_catalogue_pdf_table_cell('td', $col['width'], $col['class'], $inner);
        }
        $rows_html .= '<tr>' . $row_cells . '</tr>';

        if ($on_progress !== null && ($i % 5 === 0 || $i === $count)) {
            $pct = 42 + (int) floor(28 * $i / max(1, $count));
            $on_progress($pct, 'Préparation du document (' . $i . ' / ' . $count . ')…');
        }
    }

    if ($rows_html === '') {
        $rows_html = '<tr><td colspan="' . $col_count . '" class="empty">Aucun produit pour les critères sélectionnés.</td></tr>';
    }

    $logo_html = $logo !== ''
        ? '<img src="' . export_catalogue_pdf_escape($logo) . '" alt="Logo" class="logo">'
        : '';

    return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Export catalogue produits</title>
<style>
@page { margin: 12mm 8mm 14mm 8mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0d0d0d; margin: 0; }
.header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #3564a6; padding-bottom: 10px; }
.header-left { display: table-cell; vertical-align: top; width: 58%; }
.header-right { display: table-cell; vertical-align: top; text-align: right; width: 42%; }
.logo { max-width: 72px; max-height: 72px; margin-bottom: 6px; }
.entreprise h1 { font-size: 16px; margin: 0 0 6px; color: #0d0d0d; }
.entreprise p { margin: 0 0 3px; font-size: 8.5px; color: #444; line-height: 1.35; }
.doc-title { font-size: 13px; font-weight: bold; color: #3564a6; margin: 0 0 8px; line-height: 1.3; }
.doc-meta { font-size: 8.5px; color: #555; line-height: 1.4; margin: 0 0 4px; }
.doc-meta strong { color: #0d0d0d; }
.doc-filtres { margin: 0 0 6px; padding: 8px 10px; background: #f4f7fb; border: 1px solid #dde6f2; border-radius: 4px; }
.doc-filtre { margin: 0 0 5px; font-size: 8.5px; line-height: 1.35; }
.doc-filtre:last-child { margin-bottom: 0; }
.doc-filtre__label { display: block; font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.04em; color: #3564a6; margin-bottom: 1px; }
.doc-filtre__value { color: #0d0d0d; }
.doc-filtre__value strong { color: #0d0d0d; }
table.catalogue { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
table.catalogue th { background: #3564a6; color: #fff; font-size: 7.5px; text-transform: uppercase; padding: 6px 4px; text-align: left; vertical-align: middle; word-wrap: break-word; overflow: hidden; }
table.catalogue td { border-bottom: 1px solid #e5e5e5; padding: 5px 4px; vertical-align: top; font-size: 8px; word-wrap: break-word; overflow-wrap: break-word; overflow: hidden; }
table.catalogue tr:nth-child(even) td { background: #f8fafc; }
.col-img { text-align: center; vertical-align: middle !important; }
.prod-img { width: 34px; height: 34px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; display: block; margin: 0 auto; }
.prod-no-img { color: #999; }
.col-nom { line-height: 1.35; }
.col-text { line-height: 1.35; }
.col-num { text-align: center; vertical-align: middle; }
table.catalogue th.col-num { text-align: center; }
.col-date { font-size: 7.5px; color: #555; vertical-align: top; line-height: 1.3; }
.muted { color: #666; font-size: 7.5px; }
.empty { text-align: center; padding: 20px; color: #666; }
.footer { margin-top: 12px; font-size: 7.5px; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
</style>
</head>
<body>
<div class="header">
    <div class="header-left">
        <div class="entreprise">'
        . $logo_html
        . '<h1>' . export_catalogue_pdf_escape($ent['nom']) . '</h1>
            <p>R.C : ' . export_catalogue_pdf_escape($ent['rc']) . '</p>
            <p>N.I.N.E.A : ' . export_catalogue_pdf_escape($ent['ninea']) . '</p>
            <p>' . export_catalogue_pdf_escape($ent['adresse']) . '</p>
            <p>+221 ' . export_catalogue_pdf_escape($ent['tel1']) . '</p>
            <p>' . export_catalogue_pdf_escape($ent['site']) . ' · ' . export_catalogue_pdf_escape($ent['email']) . '</p>
        </div>
    </div>
    <div class="header-right">
        <p class="doc-title">' . export_catalogue_pdf_escape($doc_title) . '</p>
        <div class="doc-filtres">
        ' . $filtres_header_html . '
        </div>
        <p class="doc-meta">' . $total . ' produit(s) · Généré le ' . $genere_le . '</p>
    </div>
</div>
<table class="catalogue">
    <thead>
        <tr>' . $header_cells . '</tr>
    </thead>
    <tbody>' . $rows_html . '</tbody>
</table>
<p class="footer">Document généré par l’administration FOUTA POIDS LOURDS — export catalogue interne.</p>
</body>
</html>';
}

/**
 * @param array<int, array<string, mixed>> $produits
 * @param array<string, mixed> $meta
 * @return string|false
 */

function export_catalogue_render_pdf_binary(array $produits, array $meta, $on_progress = null)
{
    export_catalogue_pdf_set_error(null);

    if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
        export_catalogue_pdf_set_error('Bibliothèque Dompdf absente. Déployez le dossier vendor/ (composer install).');

        return false;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        export_catalogue_pdf_set_error('Chemin racine du projet introuvable.');

        return false;
    }

    $html = export_catalogue_build_pdf_html($produits, $meta, $on_progress);

    if ($on_progress !== null) {
        $on_progress(72, 'Rendu PDF en cours…');
    }

    try {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', $root);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        if ($on_progress !== null) {
            $on_progress(88, 'Finalisation du rendu PDF…');
        }

        $pdf_output = $dompdf->output();
        if ($pdf_output === '' || $pdf_output === false) {
            export_catalogue_pdf_set_error('Dompdf n’a produit aucun contenu PDF.');

            return false;
        }

        return $pdf_output;
    } catch (Throwable $e) {
        export_catalogue_pdf_set_error('Dompdf : ' . $e->getMessage());

        return false;
    }
}

/**
 * Écrit le PDF catalogue sur disque (export arrière-plan).
 *
 * @param callable|null $on_progress function(int $percent, string $message): void
 * @return bool
 */
function export_catalogue_write_pdf_file(array $produits, array $meta, $output_path, $on_progress = null)
{
    $pdf_output = export_catalogue_render_pdf_binary($produits, $meta, $on_progress);
    if ($pdf_output === false) {
        return false;
    }

    if ($on_progress !== null) {
        $on_progress(93, 'Enregistrement du fichier PDF…');
    }

    $dir = dirname($output_path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    if (@file_put_contents($output_path, $pdf_output, LOCK_EX) === false) {
        export_catalogue_pdf_set_error('Impossible d’enregistrer le PDF sur le serveur.');

        return false;
    }

    if ($on_progress !== null) {
        $on_progress(98, 'Finalisation…');
    }

    return true;
}

/**
 * @param array<int, array<string, mixed>> $produits
 * @param array<string, mixed> $meta
 * @return bool
 */
function export_catalogue_send_pdf(array $produits, array $meta)
{
    export_catalogue_pdf_set_error(null);

    require_once __DIR__ . '/admin_pdf_response.php';

    $pdf_output = export_catalogue_render_pdf_binary($produits, $meta);
    if ($pdf_output === false) {
        return false;
    }

    $slug_debut = preg_replace('/[^0-9]/', '', (string) ($meta['date_debut'] ?? ''));
    $slug_fin = preg_replace('/[^0-9]/', '', (string) ($meta['date_fin'] ?? ''));
    $filename = 'catalogue-produits-' . $slug_debut . '-' . $slug_fin . '.pdf';

    if (!admin_pdf_send_binary($pdf_output, $filename)) {
        export_catalogue_pdf_set_error('Impossible d’envoyer le PDF (en-têtes déjà envoyés).');

        return false;
    }

    return true;
}