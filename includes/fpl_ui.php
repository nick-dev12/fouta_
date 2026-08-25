<?php
/**
 * Interface FPL : icônes SVG et petits assistants d'affichage
 * Programmation procédurale uniquement
 *
 * Les icônes sont la traduction du composant Blade <x-icon> de FPL :
 * style trait (type Feather/Lucide), aucune dépendance externe.
 */

/**
 * Échappe une chaîne pour l'affichage HTML
 */
function e($texte)
{
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

/**
 * Une quantité au format FPL : « 1 234,5 » — les zéros de fin tombent
 * (traduction du rtrim(rtrim(number_format(x, 3, ',', ' '), '0'), ',') du Laravel)
 */
function fpl_quantite($valeur, $decimales = 3)
{
    return rtrim(rtrim(number_format((float) $valeur, $decimales, ',', ' '), '0'), ',');
}

/**
 * Un montant entier au format FPL : « 12 500 »
 */
function fpl_montant($valeur)
{
    return number_format((float) $valeur, 0, ',', ' ');
}

/**
 * La référence FPL telle qu'elle s'affiche : « FPL100CR 7484535954 » —
 * le bloc FPL + catégorie + initiales reste collé, le suffixe s'en détache
 * (traduction du service CodeFpl::afficher du Laravel)
 */
function fpl_code_afficher($code)
{
    $code = (string) $code;
    if (preg_match('/^FPL(\d{2,3})([A-Z]{1,3})(.+)$/', $code, $m)) {
        return 'FPL' . $m[1] . $m[2] . ' ' . $m[3];
    }

    return preg_match('/^FPL\d{2}/', $code)
        ? substr($code, 0, 5) . ' ' . substr($code, 5)
        : $code;
}

/**
 * La date longue en français : « mardi 12 août 2026 »
 */
function fpl_date_longue($quand = null)
{
    $ts = $quand === null ? time() : (is_numeric($quand) ? (int) $quand : strtotime($quand));
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    return $jours[(int) date('w', $ts)] . ' ' . (int) date('j', $ts) . ' '
        . $mois[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Un délai en toutes lettres : « il y a 3 min », « il y a 2 j »
 * (équivalent simple du diffForHumans du Laravel)
 */
function fpl_il_y_a($quand)
{
    $ts = is_numeric($quand) ? (int) $quand : strtotime((string) $quand);
    if (!$ts) {
        return '—';
    }
    $ecart = max(0, time() - $ts);
    if ($ecart < 60) {
        return 'à l\'instant';
    }
    if ($ecart < 3600) {
        return 'il y a ' . floor($ecart / 60) . ' min';
    }
    if ($ecart < 86400) {
        return 'il y a ' . floor($ecart / 3600) . ' h';
    }

    return 'il y a ' . floor($ecart / 86400) . ' j';
}

/**
 * La liste STRICTE des couleurs proposées — cohérente d'une pièce à
 * l'autre, chaque couleur porte sa teinte exacte pour la pastille
 * (traduction de ProductController::COULEURS)
 */
function fpl_couleurs()
{
    return [
        'Argent' => '#C0C0C0', 'Beige' => '#D9C4A3', 'Blanc' => '#FFFFFF',
        'Bleu' => '#1565C0', 'Bleu marine' => '#0B1F4B', 'Bordeaux' => '#6D1B2C',
        'Doré' => '#D4AF37', 'Gris' => '#9E9E9E', 'Gris anthracite' => '#3B3F45',
        'Jaune' => '#F5C518', 'Marron' => '#6B4226', 'Noir' => '#111111',
        'Orange' => '#F5820D', 'Rose' => '#E85D8A', 'Rouge' => '#E23B3B',
        'Turquoise' => '#2FBFB0', 'Vert' => '#3C9A4A', 'Violet' => '#7B4FA6',
    ];
}

/**
 * La pastille hexadécimale d'une couleur nommée (traduction de
 * ProductController::couleurHex)
 */
function fpl_couleur_hex($nom)
{
    $couleurs = fpl_couleurs();

    return $nom && isset($couleurs[$nom]) ? $couleurs[$nom] : null;
}

/**
 * Le type d'un nœud d'entrepôt, en toutes lettres (traduction de kindLabel)
 */
function fpl_noeud_type_libelle($noeud)
{
    if (!empty($noeud['est_defectueux'])) {
        return 'Pièces défectueuses';
    }

    return !empty($noeud['est_reserve']) ? 'Réserve' : 'Rayon (vente)';
}

/**
 * Envoie un fichier CSV UTF-8 (BOM) au point-virgule : Excel l'ouvre d'un
 * double-clic, accents corrects (traduction de ExportController::csv)
 */
function fpl_csv_telecharger($fichier, $entetes, $lignes)
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fichier . '.csv"');
    $sortie = fopen('php://output', 'w');
    fwrite($sortie, "\xEF\xBB\xBF"); // BOM : Excel lit l'UTF-8
    fputcsv($sortie, $entetes, ';');
    foreach ($lignes as $ligne) {
        fputcsv($sortie, array_map(function ($v) {
            if ($v === null) {
                return '';
            }

            return is_float($v) ? str_replace('.', ',', (string) $v) : (string) $v;
        }, $ligne), ';');
    }
    fclose($sortie);
    exit;
}

/**
 * Les liens de téléchargement d'un export : CSV opérationnel ; Excel, PDF
 * et Word arriveront avec leurs bibliothèques (le clic prévient proprement)
 */
function fpl_export_boutons($url_base)
{
    $sep = strpos($url_base, '?') === false ? '?' : '&';

    return '<span class="muted" style="font-size:12px">Télécharger :</span> '
        . '<a href="' . e($url_base . $sep . 'format=csv') . '" class="btn btn-outline btn-sm">' . fpl_icone('download', 12) . ' CSV</a> '
        . '<a href="' . e($url_base . $sep . 'format=xlsx') . '" class="btn btn-outline btn-sm">' . fpl_icone('download', 12) . ' Excel</a> '
        . '<a href="' . e($url_base . $sep . 'format=pdf') . '" class="btn btn-outline btn-sm">' . fpl_icone('download', 12) . ' PDF</a> '
        . '<a href="' . e($url_base . $sep . 'format=docx') . '" class="btn btn-outline btn-sm">' . fpl_icone('download', 12) . ' Word</a>';
}

/**
 * Le nombre de lignes par page : le choix de l'utilisateur (?par=N), retenu
 * en session tableau par tableau — traduction du trait Paginates (défaut 5)
 */
function fpl_par_page($cle, $defaut = 5)
{
    $memoire = 'affichage_' . str_replace('.', '_', $cle);

    $demande = isset($_GET['par']) ? $_GET['par'] : null;
    $retenu = isset($_SESSION[$memoire]) ? $_SESSION[$memoire] : null;

    if (is_scalar($demande) && trim((string) $demande) !== '') {
        $valeur = (int) $demande;
    } elseif (is_scalar($retenu)) {
        $valeur = (int) $retenu;
    } else {
        $valeur = $defaut;
    }

    if ($valeur < 1 || $valeur > 500) {
        $valeur = $defaut;
    }

    $_SESSION[$memoire] = $valeur;

    return $valeur;
}

/**
 * L'adresse courante avec des paramètres remplacés (pour la pagination)
 */
function fpl_url_avec($remplacements)
{
    $query = array_merge($_GET, $remplacements);
    foreach ($query as $k => $v) {
        if ($v === null) {
            unset($query[$k]);
        }
    }
    $chemin = strtok($_SERVER['REQUEST_URI'], '?');

    return $chemin . ($query !== [] ? '?' . http_build_query($query) : '');
}

/**
 * EN-TÊTE DE TABLEAU : ce qui est affiché + le nombre de lignes, choisi
 * librement (traduction du composant <x-table-controls>)
 * @param array $p Le résultat paginé ['total','page','par','derniere']
 */
function fpl_tablebar_haut($p, $nom = 'lignes')
{
    $premier = $p['total'] === 0 ? 0 : ($p['page'] - 1) * $p['par'] + 1;
    $dernier = min($p['total'], $p['page'] * $p['par']);

    $html = '<div class="tablebar top"><div class="tablebar-count">';
    if ($p['total'] === 0) {
        $html .= 'Aucun résultat';
    } else {
        $html .= 'Affichage de <strong>' . fpl_montant($premier) . '</strong> à <strong>' . fpl_montant($dernier)
            . '</strong> sur <strong>' . fpl_montant($p['total']) . '</strong> ' . e($nom);
    }
    $html .= '</div><form method="GET" class="tablebar-per" onsubmit="return false"><label>Lignes par page '
        . '<input type="number" class="per-input" value="' . (int) $p['par'] . '" min="1" max="500" '
        . 'data-url="' . e(fpl_url_avec(['par' => '__N__', 'page' => 1])) . '" '
        . 'title="Nombre de lignes affichées — validez avec Entrée"></label></form></div>';
    $html .= '<script>if (!window.fplPerInput) { window.fplPerInput = 1;
        document.addEventListener("change", function (e) {
          const input = e.target.closest(".per-input");
          if (!input) return;
          let n = parseInt(input.value, 10);
          if (isNaN(n)) return;
          n = Math.min(500, Math.max(1, n));
          location.href = input.dataset.url.replace("__N__", n);
        }); }</script>';

    return $html;
}

/**
 * PIED DE TABLEAU : les flèches et les pages numérotées
 * (traduction du composant <x-pagination>)
 */
function fpl_pager($p)
{
    $courante = (int) $p['page'];
    $derniere = (int) $p['derniere'];
    if ($derniere <= 1) {
        return '';
    }

    // Une fenêtre de pages autour de la courante, avec les extrémités
    $fenetre = range(max(1, $courante - 2), min($derniere, $courante + 2));
    if (!in_array(1, $fenetre, true)) {
        $fenetre = array_merge([1, null], $fenetre);
    }
    if (!in_array($derniere, $fenetre, true)) {
        $fenetre = array_merge($fenetre, [null, $derniere]);
    }

    $html = '<div class="tablebar"><nav class="pager" aria-label="Pages" style="margin-inline:auto">';
    $html .= $courante <= 1
        ? '<span class="pager-btn disabled" aria-hidden="true">' . fpl_icone('chevron-left', 14) . '</span>'
        : '<a class="pager-btn" href="' . e(fpl_url_avec(['page' => $courante - 1])) . '" rel="prev" title="Page précédente">' . fpl_icone('chevron-left', 14) . '</a>';
    foreach ($fenetre as $n) {
        if ($n === null) {
            $html .= '<span class="pager-gap">…</span>';
        } elseif ($n === $courante) {
            $html .= '<span class="pager-btn current" aria-current="page">' . $n . '</span>';
        } else {
            $html .= '<a class="pager-btn" href="' . e(fpl_url_avec(['page' => $n])) . '">' . $n . '</a>';
        }
    }
    $html .= $courante >= $derniere
        ? '<span class="pager-btn disabled" aria-hidden="true">' . fpl_icone('chevron-right', 14) . '</span>'
        : '<a class="pager-btn" href="' . e(fpl_url_avec(['page' => $courante + 1])) . '" rel="next" title="Page suivante">' . fpl_icone('chevron-right', 14) . '</a>';
    $html .= '</nav></div>';

    return $html;
}

/**
 * Retourne le SVG d'une icône FPL
 * @param string $nom Le nom de l'icône (voir le tableau)
 * @param int $taille Taille en pixels (largeur = hauteur)
 * @param string $classe Classe CSS du <svg>
 */
function fpl_icone($nom, $taille = 16, $classe = 'icon')
{
    static $icones = [
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'tool' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        // Les deux dessins que la page des pièces de FPL natif emploie et qui
        // manquaient ici : l'œil du bouton « Détail », la flèche de « Exporter ».
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'tag' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
        'hash' => '<line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/>',
        'rotate-ccw' => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
        'alert-triangle' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'dollar-sign' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'arrow-left' => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'package' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'list' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'chevron-up' => '<polyline points="18 15 12 9 6 15"/>',
        'arrow-up' => '<line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>',
        'corner-up-left' => '<polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>',
        'arrow-down' => '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'truck' => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'shopping-cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'log-in' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        'transfer' => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'sparkles' => '<path d="M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3z"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
        'send' => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'store' => '<path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M8 21v-6h8v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'camera' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
    ];

    $trace = isset($icones[$nom]) ? $icones[$nom] : '';

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $taille . '" height="' . (int) $taille . '"'
        . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
        . ' stroke-linejoin="round" class="' . e($classe) . '" aria-hidden="true">' . $trace . '</svg>';
}

/**
 * LE STATUT D'UNE PIÈCE, écrit pour un humain.
 * Programmation procédurale uniquement
 *
 * La base range les statuts en mots-machine (« rupture_stock ») ; les afficher
 * tels quels donnait « Rupture_stock » sur 73 fiches, tiret bas compris.
 *
 * La phrase de l'inactive est celle de FPL natif : elle ne dit pas seulement
 * l'état, elle dit la CONSÉQUENCE — la pièce ne se vend plus.
 *
 * @param string $statut
 * @param bool $complet true : la phrase entière ; false : le mot seul
 * @return string
 */
function fpl_statut_piece_libelle($statut, $complet = true)
{
    $statut = trim((string) $statut);

    $libelles = [
        'actif' => ['Active', 'Active'],
        'inactif' => ['Inactive', 'Inactive — retirée de la vente'],
        'rupture_stock' => ['En rupture', 'En rupture de stock'],
        'attente_tarif' => ['À tarifer', 'En attente de tarification'],
    ];

    if (isset($libelles[$statut])) {
        return $complet ? $libelles[$statut][1] : $libelles[$statut][0];
    }

    // Un statut qu'on ne connaît pas encore : au moins sans tiret bas.
    return $statut !== '' ? ucfirst(str_replace('_', ' ', $statut)) : '';
}
