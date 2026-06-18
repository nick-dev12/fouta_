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
function export_catalogue_pdf_set_error($message) {
    $GLOBALS['export_catalogue_pdf_last_error'] = (string) $message;
}

/**
 * @return string|null
 */
function export_catalogue_pdf_get_last_error() {
    return isset($GLOBALS['export_catalogue_pdf_last_error']) ? $GLOBALS['export_catalogue_pdf_last_error'] : null;
}

/**
 * @return string
 */
function export_catalogue_pdf_escape($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * Libellés du filtre « Type » (alignés sur export-catalogue.php).
 *
 * @return array<string, string>
 */
function export_catalogue_pdf_mode_labels() {
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
function export_catalogue_pdf_mode_label($mode) {
    $labels = export_catalogue_pdf_mode_labels();
    $mode = (string) $mode;

    return $labels[$mode] ?? $labels['tous'];
}

/**
 * @param string $date_ymd Y-m-d
 * @return string
 */
function export_catalogue_pdf_format_date($date_ymd) {
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
function export_catalogue_build_filtres_header_html(array $meta) {
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
function export_catalogue_pdf_doc_title(array $meta) {
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
function export_catalogue_image_file_to_thumb_data_uri($full_path, $max_px = 64) {
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
function export_catalogue_produit_image_data_uri(array $produit) {
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
function export_catalogue_logo_data_uri() {
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
 * @param array<int, array<string, mixed>> $produits
 * @param array<string, mixed> $meta date_debut, date_fin, mode, recherche, total
 * @return string HTML
 */
function export_catalogue_build_pdf_html(array $produits, array $meta) {
    $ent = get_entreprise_config();
    $logo = export_catalogue_logo_data_uri();

    $doc_title = export_catalogue_pdf_doc_title($meta);
    $filtres_header_html = export_catalogue_build_filtres_header_html($meta);
    $genere_le = export_catalogue_pdf_escape(date('d/m/Y H:i'));
    $total = (int) ($meta['total'] ?? count($produits));

    $rows_html = '';
    foreach ($produits as $p) {
        $img_uri = export_catalogue_produit_image_data_uri($p);
        $img_cell = $img_uri !== ''
            ? '<img src="' . export_catalogue_pdf_escape($img_uri) . '" alt="" class="prod-img">'
            : '<span class="prod-no-img">—</span>';

        $ident = trim((string) ($p['identifiant_interne'] ?? ''));
        $marque = function_exists('produits_marque_libelle_from_row') ? produits_marque_libelle_from_row($p) : '';
        $fourn = function_exists('produits_fournisseur_nom_affichage') ? produits_fournisseur_nom_affichage($p) : '';
        $prix = number_format((float) ($p['prix'] ?? 0), 0, ',', ' ');
        $promo = !empty($p['prix_promotion']) ? number_format((float) $p['prix_promotion'], 0, ',', ' ') . ' FCFA' : '—';
        $dc = !empty($p['date_creation']) ? date('d/m/Y H:i', strtotime((string) $p['date_creation'])) : '—';
        $dm = !empty($p['date_modification']) ? date('d/m/Y H:i', strtotime((string) $p['date_modification'])) : '—';

        $rows_html .= '<tr>
            <td class="col-img">' . $img_cell . '</td>
            <td class="col-nom"><strong>' . export_catalogue_pdf_escape($p['nom'] ?? '') . '</strong>'
            . ($ident !== '' ? '<br><span class="muted">Réf. ' . export_catalogue_pdf_escape($ident) . '</span>' : '')
            . ($marque !== '' ? '<br><span class="muted">' . export_catalogue_pdf_escape($marque) . '</span>' : '')
            . '</td>
            <td>' . export_catalogue_pdf_escape($p['categorie_nom'] ?? '—') . '</td>
            <td>' . export_catalogue_pdf_escape($fourn !== '' ? $fourn : '—') . '</td>
            <td class="num">' . export_catalogue_pdf_escape($prix) . '</td>
            <td class="num">' . export_catalogue_pdf_escape($promo) . '</td>
            <td class="num">' . export_catalogue_pdf_escape((string) (int) ($p['stock'] ?? 0)) . '</td>
            <td class="date">' . export_catalogue_pdf_escape($dc) . '</td>
            <td class="date">' . export_catalogue_pdf_escape($dm) . '</td>
        </tr>';
    }

    if ($rows_html === '') {
        $rows_html = '<tr><td colspan="9" class="empty">Aucun produit pour les critères sélectionnés.</td></tr>';
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
@page { margin: 14mm 10mm 16mm 10mm; }
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
table.catalogue { width: 100%; border-collapse: collapse; margin-top: 8px; }
table.catalogue th { background: #3564a6; color: #fff; font-size: 8px; text-transform: uppercase; padding: 6px 4px; text-align: left; }
table.catalogue td { border-bottom: 1px solid #e5e5e5; padding: 5px 4px; vertical-align: middle; font-size: 8.5px; }
table.catalogue tr:nth-child(even) td { background: #f8fafc; }
.col-img { width: 42px; text-align: center; }
.prod-img { width: 38px; height: 38px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
.prod-no-img { color: #999; }
.col-nom { min-width: 120px; }
.num { text-align: right; white-space: nowrap; }
.date { white-space: nowrap; font-size: 7.5px; color: #555; }
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
        <tr>
            <th>Image</th>
            <th>Produit</th>
            <th>Catégorie</th>
            <th>Fournisseur</th>
            <th>Prix FCFA</th>
            <th>Promo</th>
            <th>Stock</th>
            <th>Création</th>
            <th>Modification</th>
        </tr>
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
 * @return bool
 */
function export_catalogue_send_pdf(array $produits, array $meta) {
    export_catalogue_pdf_set_error(null);

    if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
        export_catalogue_pdf_set_error('Bibliothèque Dompdf absente. Déployez le dossier vendor/ (composer install).');

        return false;
    }

    require_once __DIR__ . '/admin_pdf_response.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        export_catalogue_pdf_set_error('Chemin racine du projet introuvable.');

        return false;
    }

    $html = export_catalogue_build_pdf_html($produits, $meta);

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

        $slug_debut = preg_replace('/[^0-9]/', '', (string) ($meta['date_debut'] ?? ''));
        $slug_fin = preg_replace('/[^0-9]/', '', (string) ($meta['date_fin'] ?? ''));
        $filename = 'catalogue-produits-' . $slug_debut . '-' . $slug_fin . '.pdf';
        $pdf_output = $dompdf->output();

        if ($pdf_output === '' || $pdf_output === false) {
            export_catalogue_pdf_set_error('Dompdf n’a produit aucun contenu PDF.');

            return false;
        }

        if (!admin_pdf_send_binary($pdf_output, $filename)) {
            export_catalogue_pdf_set_error('Impossible d’envoyer le PDF (en-têtes déjà envoyés).');

            return false;
        }

        return true;
    } catch (Throwable $e) {
        export_catalogue_pdf_set_error('Dompdf : ' . $e->getMessage());

        return false;
    }
}
