<?php
/**
 * API JSON : recherche par code FPL complet ou par les 5 derniers chiffres (saisie rapide)
 *
 * GET  /api/produit_par_code_fpl.php?code=FPL000042
 * GET  /api/produit_par_code_fpl.php?code=00042   (5 chiffres — derniers du numéro interne)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';

$code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
if ($code === '' && isset($_GET['q'])) {
    $code = trim((string) $_GET['q']);
}

if ($code === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Paramètre code ou q requis (ex. FPL000042 ou 00151 pour les 5 derniers chiffres).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!produits_has_column('identifiant_interne')) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Identifiants produits non disponibles (migration base de données).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/site_url.php';
$base = get_site_base_url();

function api_produit_fpl_format_row(array $p, $base_url)
{
    return [
        'id' => (int) $p['id'],
        'identifiant_interne' => $p['identifiant_interne'] ?? '',
        'nom' => $p['nom'] ?? '',
        'description' => $p['description'] ?? '',
        'prix' => isset($p['prix']) ? (float) $p['prix'] : 0,
        'prix_promotion' => isset($p['prix_promotion']) && $p['prix_promotion'] !== null ? (float) $p['prix_promotion'] : null,
        'stock' => isset($p['stock']) ? (int) $p['stock'] : 0,
        'statut' => $p['statut'] ?? '',
        'unite' => $p['unite'] ?? 'unité',
        'categorie_id' => isset($p['categorie_id']) ? (int) $p['categorie_id'] : null,
        'categorie_nom' => $p['categorie_nom'] ?? '',
        'etage' => isset($p['etage']) && $p['etage'] !== '' ? $p['etage'] : null,
        'numero_rayon' => isset($p['numero_rayon']) && $p['numero_rayon'] !== '' ? $p['numero_rayon'] : null,
        'image_principale' => $p['image_principale'] ?? '',
        'url_fiche' => $base_url . '/produit.php?id=' . (int) $p['id'],
        'url_stock_info' => $base_url . '/stock-info.php?id=' . (int) $p['id'],
    ];
}

// Recherche rapide : exactement 5 chiffres = fin du numéro interne (hors préfixe FPL)
if (preg_match('/^\d{5}$/', $code)) {
    $liste = get_produits_by_identifiant_suffix_5_chiffres($code, 0, 50, false);
    if (empty($liste)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Aucun produit pour ces 5 chiffres.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $out = [];
    foreach ($liste as $row) {
        $out[] = api_produit_fpl_format_row($row, $base);
    }
    echo json_encode([
        'success' => true,
        'match_type' => 'suffix_5_digits',
        'suffix' => $code,
        'count' => count($out),
        'produits' => $out,
        // Rétrocompatibilité : un seul résultat = clé produit encore présente
        'produit' => count($out) === 1 ? $out[0] : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$code = strtoupper($code);
if (!preg_match('/^FPL\d{6}$/', $code)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Utilisez le code complet FPL + 6 chiffres, ou exactement 5 chiffres pour la recherche rapide.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$p = get_produit_by_identifiant_interne($code, false);
if (!$p) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Aucun produit pour cette référence.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = api_produit_fpl_format_row($p, $base);
echo json_encode([
    'success' => true,
    'match_type' => 'full_code',
    'produit' => $row,
    'produits' => [$row],
    'count' => 1,
], JSON_UNESCAPED_UNICODE);
