<?php
/**
 * LE CATALOGUE EN FICHIER — les formats que le PDF ne couvre pas.
 * Programmation procédurale uniquement
 *
 * Ce fichier NE TOUCHE PAS AU PDF. Le PDF garde son moteur, son worker
 * asynchrone, sa barre de progression et son choix de colonnes : tout cela
 * marche, on n'y met pas la main. On vient seulement à côté, avec les trois
 * formats qui manquaient — CSV, Excel (.xlsx) et Word (.docx).
 *
 * LA RÈGLE QUI ÉVITE LES DIVERGENCES : on ne redécrit nulle part ce qu'est
 * une colonne ni comment se calcule une valeur. On appelle les fonctions du
 * PDF — export_catalogue_pdf_table_columns() pour les en-têtes et
 * export_catalogue_pdf_row_cell_contents() pour les cellules — et on se
 * contente de les habiller autrement. Un prix qui change de forme dans le PDF
 * change du même coup dans le CSV, l'Excel et le Word, sans qu'on y pense.
 *
 * La colonne « image » est la seule écartée : une vignette n'a pas de sens
 * dans un tableur.
 */

require_once __DIR__ . '/export_produits_catalogue_pdf.php';

/**
 * Le HTML d'une cellule du PDF ramené au texte nu d'un tableur.
 * Les retours à la ligne du PDF (« Réf. X » sous le nom) deviennent des
 * séparateurs lisibles ; le tiret cadratin des cases vides devient un vide,
 * parce qu'un tableur préfère une cellule vide à un « — » qu'il faudra
 * effacer pour compter.
 *
 * @param string $html
 * @return string
 */
function export_catalogue_fichier_texte($html)
{
    $texte = (string) $html;
    $texte = preg_replace('#<br\s*/?>#i', ' · ', $texte);
    $texte = strip_tags($texte);
    $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texte = preg_replace('/\s+/u', ' ', $texte);
    $texte = trim($texte);

    return ($texte === '—') ? '' : $texte;
}

/**
 * Les en-têtes et les lignes du fichier, tels que le PDF les montre.
 *
 * @param array<int, array<string, mixed>> $produits
 * @param array<int, string>|null $selected_cols Les colonnes choisies à l'écran
 * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
 */
function export_catalogue_fichier_tableau(array $produits, ?array $selected_cols = null)
{
    $has_prix_achat = export_catalogue_has_prix_achat_column();
    $colonnes = export_catalogue_pdf_table_columns($has_prix_achat, $selected_cols);

    // La vignette ne descend pas dans un tableur.
    $colonnes = array_values(array_filter($colonnes, function ($col) {
        return ($col['key'] ?? '') !== 'img';
    }));

    $entetes = [];
    foreach ($colonnes as $col) {
        $entetes[] = (string) ($col['label'] ?? $col['key']);
    }

    $lignes = [];
    foreach ($produits as $produit) {
        $cellules = export_catalogue_pdf_row_cell_contents($produit, $has_prix_achat, $selected_cols);
        $ligne = [];
        foreach ($colonnes as $col) {
            $ligne[] = export_catalogue_fichier_texte($cellules[$col['key']] ?? '');
        }
        $lignes[] = $ligne;
    }

    return [$entetes, $lignes];
}

/**
 * Les formats réellement livrables sur CETTE machine : le CSV ne demande
 * rien, l'Excel et le Word demandent leurs bibliothèques. On le dit à
 * l'écran plutôt que de proposer un bouton qui tomberait en panne.
 *
 * @return array<string, bool>
 */
function export_catalogue_fichier_formats_disponibles()
{
    export_catalogue_fichier_autoload();

    return [
        'csv'  => true,
        'xlsx' => class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet'),
        'docx' => class_exists('\\PhpOffice\\PhpWord\\PhpWord'),
    ];
}

/** Charge l'autoload de composer s'il existe, une seule fois. */
function export_catalogue_fichier_autoload()
{
    static $fait = false;
    if ($fait) {
        return;
    }
    $fait = true;
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
}

/**
 * Livre le fichier demandé et TERMINE la requête.
 *
 * @param string $format 'csv' | 'xlsx' | 'docx'
 * @param string $nom    Le début du nom de fichier (« catalogue »)
 * @param string $titre  Le titre humain (« Catalogue des pièces »)
 * @param array<int, string> $entetes
 * @param array<int, array<int, string>> $lignes
 */
function export_catalogue_fichier_livrer($format, $nom, $titre, array $entetes, array $lignes)
{
    $fichier = $nom . '-' . date('Y-m-d-Hi');
    $dispos = export_catalogue_fichier_formats_disponibles();

    if (empty($dispos[$format])) {
        $format = 'csv';
    }

    switch ($format) {
        case 'xlsx':
            export_catalogue_fichier_xlsx($fichier, $titre, $entetes, $lignes);
            break;
        case 'docx':
            export_catalogue_fichier_docx($fichier, $titre, $entetes, $lignes);
            break;
        default:
            export_catalogue_fichier_csv($fichier, $entetes, $lignes);
    }
    exit;
}

/**
 * CSV — point-virgule et BOM, les deux conditions pour qu'Excel en français
 * ouvre le fichier en colonnes du premier coup au lieu d'une bouillie.
 */
function export_catalogue_fichier_csv($fichier, array $entetes, array $lignes)
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fichier . '.csv"');
    $sortie = fopen('php://output', 'w');
    fwrite($sortie, "\xEF\xBB\xBF");
    fputcsv($sortie, $entetes, ';');
    foreach ($lignes as $ligne) {
        fputcsv($sortie, $ligne, ';');
    }
    fclose($sortie);
    exit;
}

/** Excel véritable (.xlsx) : en-têtes aux couleurs de la maison. */
function export_catalogue_fichier_xlsx($fichier, $titre, array $entetes, array $lignes)
{
    export_catalogue_fichier_autoload();

    $classeur = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $feuille = $classeur->getActiveSheet();
    $feuille->setTitle(mb_substr(str_replace(['/', '\\', '?', '*', '[', ']'], '-', $titre), 0, 31));

    $feuille->fromArray($entetes, null, 'A1');
    $derniere = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($entetes)));
    $feuille->getStyle('A1:' . $derniere . '1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '10316F']],
    ]);
    $feuille->fromArray($lignes, null, 'A2');

    for ($i = 1; $i <= count($entetes); $i++) {
        $lettre = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $feuille->getColumnDimension($lettre)->setAutoSize(true);
    }
    $feuille->freezePane('A2');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fichier . '.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($classeur);
    $writer->save('php://output');
    exit;
}

/** Word (.docx) : un tableau en paysage, titre et date en tête. */
function export_catalogue_fichier_docx($fichier, $titre, array $entetes, array $lignes)
{
    export_catalogue_fichier_autoload();

    $doc = new \PhpOffice\PhpWord\PhpWord();
    $section = $doc->addSection(['orientation' => 'landscape']);
    $section->addText($titre, ['bold' => true, 'size' => 15]);
    $section->addText('Édité le ' . date('d/m/Y à H:i'), ['size' => 9, 'color' => '666666']);
    $section->addTextBreak(1);

    $tableau = $section->addTable([
        'borderSize' => 6,
        'borderColor' => 'CCCCCC',
        'cellMargin' => 60,
    ]);

    $tableau->addRow();
    foreach ($entetes as $entete) {
        $cellule = $tableau->addCell(1800, ['bgColor' => '10316F']);
        $cellule->addText((string) $entete, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
    }

    foreach ($lignes as $ligne) {
        $tableau->addRow();
        foreach ($ligne as $valeur) {
            $tableau->addCell(1800)->addText(
                \PhpOffice\PhpWord\Shared\Text::toUTF8((string) $valeur),
                ['size' => 9]
            );
        }
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $fichier . '.docx"');
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007');
    $writer->save('php://output');
    exit;
}
