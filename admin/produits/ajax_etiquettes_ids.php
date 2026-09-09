<?php
/**
 * TOUS LES IDENTIFIANTS DU FILTRE COURANT (JSON) — ce que demande le bouton
 * « Tout sélectionner » de « Toutes les étiquettes » (09/09/2026).
 *
 * La liste est paginée (20 lignes) : sans cet appel, « tout cocher » ne
 * cocherait que la page affichée, et le PDF de lot manquerait tout le reste.
 * Le filtre est relu ici À L'IDENTIQUE de la liste (etiquettes_pieces_criteres),
 * jamais reçu tout fait du navigateur.
 * Programmation procédurale uniquement.
 */

session_start();

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';

header('Content-Type: application/json; charset=utf-8');

// Le compte restreint (infographiste) n'imprime pas : pas de lot pour lui.
if (!isset($_SESSION['admin_id']) || admin_is_restricted_admin_account()) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$etat = isset($_GET['etat']) && in_array($_GET['etat'], ['a_imprimer', 'imprimees'], true) ? $_GET['etat'] : null;
$du = isset($_GET['du']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['du']) ? $_GET['du'] : null;
$au = isset($_GET['au']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['au']) ? $_GET['au'] : null;

$ids = etiquettes_pieces_ids($q, $etat, $du, $au, 300);

echo json_encode(['ok' => true, 'ids' => $ids, 'nb' => count($ids)]);
