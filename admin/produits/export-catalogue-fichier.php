<?php
/**
 * LE CATALOGUE TÉLÉCHARGÉ EN CSV, EXCEL OU WORD.
 * Programmation procédurale uniquement
 *
 * Le pendant de export-catalogue-pdf.php pour les trois autres formats.
 * Il ne double aucune logique : mêmes filtres (export_catalogue_filters_from_request),
 * mêmes colonnes choisies à l'écran (export_catalogue_pdf_parse_selected_columns),
 * mêmes valeurs de cellules que le PDF. Seul l'emballage change.
 *
 * UNE DIFFÉRENCE ASSUMÉE AVEC LE PDF : on lit les produits SANS « for_pdf »,
 * donc le filtre d'accès par périmètre s'applique — un compte ne télécharge
 * que ce que la liste lui montre déjà à l'écran. C'est le choix prudent.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../includes/export_produits_catalogue_pdf.php';
require_once __DIR__ . '/../../includes/export_catalogue_fichier.php';

$format = isset($_GET['format']) && in_array($_GET['format'], ['csv', 'xlsx', 'docx'], true)
    ? (string) $_GET['format']
    : 'csv';

// Un format dont la bibliothèque manque ne produit pas un fichier cassé :
// on renvoie l'utilisateur à la page avec un mot, et le PDF reste entier.
$dispos = export_catalogue_fichier_formats_disponibles();
if (empty($dispos[$format])) {
    $_SESSION['export_catalogue_flash'] = [
        'type' => 'erreur',
        'message' => 'Le format « ' . strtoupper($format) . ' » demande une bibliothèque qui n\'est pas installée sur ce serveur. Le CSV et le PDF restent disponibles.',
    ];
    header('Location: export-catalogue.php?' . http_build_query(array_diff_key($_GET, ['format' => 1])));
    exit;
}

/* DEUX PROVENANCES, DEUX CHEMINS.
 *
 * « source=fpl » : la page d'aperçu d'export (export-catalogue.php, portage de
 * FPL natif). Elle a ses propres filtres et ses propres colonnes cochées, et
 * la promesse écrite en haut de cette page est que le fichier contient
 * EXACTEMENT l'aperçu — on emprunte donc les mêmes fonctions qu'elle.
 *
 * Sans « source » : l'ancien chemin, celui du suivi du catalogue, laissé
 * intact pour ne rien casser de ce qui l'appelle déjà. */
if (isset($_GET['source']) && $_GET['source'] === 'fpl') {
    $cat_id = 0;
    $cat_est_sous = false;
    if (isset($_GET['cat']) && preg_match('/^([cs]):(\d+)$/', (string) $_GET['cat'], $m)) {
        $cat_est_sous = ($m[1] === 's');
        $cat_id = (int) $m[2];
    }

    $filtres = [
        'du' => isset($_GET['du']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['du']) ? $_GET['du'] : '',
        'au' => isset($_GET['au']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['au']) ? $_GET['au'] : '',
        'cat' => $cat_id,
        'cat_est_sous' => $cat_est_sous,
        'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
        'ref' => isset($_GET['ref']) ? trim((string) $_GET['ref']) : '',
        'marque' => isset($_GET['marque']) ? (int) $_GET['marque'] : 0,
        'modele' => isset($_GET['modele']) ? (int) $_GET['modele'] : 0,
        'annee' => isset($_GET['annee']) ? (int) $_GET['annee'] : 0,
    ];

    $colonnes = export_colonnes_fpl_retenues(
        isset($_GET['colonnes']) && is_array($_GET['colonnes']) ? $_GET['colonnes'] : null
    );
    $catalogue = export_colonnes_fpl_toutes();

    // TOUTES les pièces filtrées, pas la seule page affichée : c'est la
    // promesse faite sous l'aperçu.
    $tout = produits_export_fpl($filtres, 1, 200);
    $produits = $tout['lignes'];
    $page = 2;
    while (count($produits) < $tout['total'] && $page <= 500) {
        $suite = produits_export_fpl($filtres, $page, 200);
        if ($suite['lignes'] === []) {
            break;
        }
        foreach ($suite['lignes'] as $l) {
            $produits[] = $l;
        }
        $page++;
    }

    $entetes = [];
    foreach ($colonnes as $cle) {
        $entetes[] = $catalogue[$cle][0];
    }
    $lignes = [];
    foreach ($produits as $piece) {
        $ligne = [];
        foreach ($colonnes as $cle) {
            $ligne[] = export_valeur_colonne_fpl($cle, $piece);
        }
        $lignes[] = $ligne;
    }

    export_catalogue_fichier_livrer($format, 'pieces', 'Catalogue des pièces', $entetes, $lignes);
}

$filters = export_catalogue_filters_from_request($_GET);

// SANS choix explicite dans l'URL, on veut TOUTES les colonnes — pas la
// poignée de colonnes « verrouillées » que le lecteur du PDF renvoie quand
// on ne lui demande rien (il rendrait un fichier à une seule colonne, où le
// nom, la référence, la marque et le fournisseur seraient collés ensemble).
$colonnes = (array_key_exists('pdf_cols', $_GET) || array_key_exists('pdf_cols[]', $_GET))
    ? export_catalogue_pdf_parse_selected_columns($_GET)
    : null;

$produits = get_admin_produits_export_catalogue_all(
    $filters['date_debut'],
    $filters['date_fin'],
    $filters['mode'],
    $filters['recherche'],
    $filters['categorie_id'],
    $filters['marque_id'],
    0,
    200
);

list($entetes, $lignes) = export_catalogue_fichier_tableau($produits, $colonnes);

export_catalogue_fichier_livrer($format, 'catalogue', 'Catalogue des pièces', $entetes, $lignes);
