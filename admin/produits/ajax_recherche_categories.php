<?php
/**
 * LES SUGGESTIONS DU CHAMP « CATÉGORIE » (JSON).
 * Programmation procédurale uniquement
 *
 * Reprise du geste de FPL natif : au lieu de dérouler une liste de toutes les
 * catégories, on tape deux lettres et les catégories ET les sous-catégories
 * qui correspondent s'affichent sous le champ, chacune avec le rayon d'où elle
 * vient. Entrée ouvre la première.
 *
 * Le tri se fait ici, en PHP, sur les deux listes déjà chargées par le modèle :
 * une catégorie n'est pas une donnée volumineuse, et cela évite d'ajouter une
 * requête à un écran qui en fait déjà beaucoup.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(403);
    echo json_encode(['categories' => []]);
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce_json_empty();

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if (mb_strlen($q) < 2) {
    echo json_encode(['categories' => []]);
    exit;
}

/** Comparaison indifférente aux accents et à la casse. */
function fpl_cat_contient($foin, $aiguille)
{
    $normaliser = function ($t) {
        $t = mb_strtolower((string) $t, 'UTF-8');
        $tr = @iconv('UTF-8', 'ASCII//TRANSLIT', $t);

        return $tr === false ? $t : $tr;
    };

    return strpos($normaliser($foin), $normaliser($aiguille)) !== false;
}

$resultats = [];

foreach (get_all_categories() as $c) {
    if (count($resultats) >= 10) {
        break;
    }
    $nom = (string) ($c['nom'] ?? '');
    if ($nom !== '' && fpl_cat_contient($nom, $q)) {
        $resultats[] = [
            'id' => (int) $c['id'],
            'name' => fpl_texte($nom),
            'parent' => null,
            'sous' => false,
            'url' => 'index.php?categorie_id=' . (int) $c['id'],
        ];
    }
}

if (function_exists('sous_categories_table_ok') && sous_categories_table_ok()) {
    foreach (get_all_sous_categories_with_categorie_nom() as $sc) {
        if (count($resultats) >= 10) {
            break;
        }
        $nom = (string) ($sc['nom'] ?? '');
        if ($nom !== '' && fpl_cat_contient($nom, $q)) {
            $resultats[] = [
                'id' => (int) $sc['id'],
                'name' => fpl_texte($nom),
                'parent' => fpl_texte((string) ($sc['categorie_nom'] ?? '')),
                'sous' => true,
                'url' => 'index.php?categorie_id=' . (int) ($sc['categorie_id'] ?? 0)
                    . '&sous_categorie_id=' . (int) $sc['id'],
            ];
        }
    }
}

echo json_encode(['categories' => $resultats], JSON_UNESCAPED_UNICODE);
