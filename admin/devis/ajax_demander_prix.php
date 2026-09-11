<?php
/**
 * DEMANDER LE PRIX D'UNE PIÈCE DEPUIS UN DEVIS OU UN BON (11/09/2026).
 *
 * Décision de la direction : le vendeur ne tape plus aucun prix. Une ligne de
 * devis ou de bon dont la pièce n'a pas le prix choisi pour le total propose
 * « Demander le prix » : ce point l'enregistre pour le responsable de stock.
 * Même règle que la vente directe (admin/caisse/api.php, action demander_prix).
 * Règles : models/model_demandes_prix.php. Réponse JSON.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Session expirée : reconnectez-vous.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_devis() && !admin_can_bl_retours_b2b()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Action non autorisée.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Demande invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$jeton = (string) ($_POST['csrf_token'] ?? '');
if ($jeton === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expirée : rechargez la page, puis demandez de nouveau.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../models/model_produit_formulaire_champs.php';
require_once __DIR__ . '/../../models/model_demandes_prix.php';

/* On ne demande qu'une colonne de prix que l'on voit dans le devis ; sinon le prix de vente. */
$champ = trim((string) ($_POST['champ'] ?? 'prix'));
$visibles = array_map(static function ($ch) {
    return (string) ($ch['slug'] ?? '');
}, produit_formulaire_champs_prix_devis());
if (!in_array($champ, $visibles, true)) {
    $champ = 'prix';
}

$res = demande_prix_creer((int) ($_POST['produit_id'] ?? 0), (int) $_SESSION['admin_id'], $champ);
if (empty($res['ok'])) {
    echo json_encode(['ok' => false, 'error' => (string) ($res['error'] ?? 'La demande de prix a été refusée.')], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true, 'message' => demandes_prix_message_demande($res)], JSON_UNESCAPED_UNICODE);
