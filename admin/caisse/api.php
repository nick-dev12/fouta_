<?php
/**
 * API JSON caisse — panier sans session, ticket depuis payload client
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non authentifié.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_caisse()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Accès refusé.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse.php';
require_once __DIR__ . '/../../models/model_produits.php';

function caisse_api_out(array $data, $code = 200)
{
    http_response_code((int) $code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function caisse_api_read_input()
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return $_POST;
}

function caisse_api_check_csrf(array $input)
{
    $tok = (string) ($input['csrf_token'] ?? '');
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        caisse_api_out(['ok' => false, 'error' => 'Session expirée — rechargez la page.'], 403);
    }
}

$input = caisse_api_read_input();
$action = trim((string) ($input['action'] ?? $_GET['action'] ?? ''));

if ($action === 'catalog') {
    if (!admin_can_caisse_vendeur() && !admin_can_caisse()) {
        caisse_api_out(['ok' => false, 'error' => 'Accès refusé.'], 403);
    }
    $items_json = caisse_catalog_live_json();
    header('Cache-Control: private, max-age=30');
    echo '{"ok":true,"items":' . $items_json . '}';
    exit;
}

if ($action === 'csrf') {
    caisse_api_out(['ok' => true, 'csrf_token' => (string) $_SESSION['admin_csrf']]);
}

if ($action === 'resolve_product') {
    caisse_api_check_csrf($input);
    $code = trim((string) ($input['code'] ?? ''));
    if ($code === '') {
        caisse_api_out(['ok' => false, 'error' => 'Code vide.']);
    }
    $ticket_scan_id = caisse_trouver_vente_id_par_code_scan($code);
    if ($ticket_scan_id > 0) {
        caisse_api_out([
            'ok' => true,
            'type' => 'ticket',
            'vente_id' => $ticket_scan_id,
            'redirect' => admin_current_role() === 'caissier'
                ? 'encaisser-ticket.php?ticket=' . $ticket_scan_id
                : 'index.php?ticket=' . $ticket_scan_id,
        ]);
    }
    $res = caisse_resoudre_produit_par_code($code);
    if (!$res['ok']) {
        caisse_api_out(['ok' => false, 'error' => $res['error'] ?? 'Produit introuvable.']);
    }
    caisse_api_out([
        'ok' => true,
        'type' => 'produit',
        'produit' => caisse_produit_api_format($res['produit']),
    ]);
}

if ($action === 'get_product') {
    caisse_api_check_csrf($input);
    $pid = (int) ($input['produit_id'] ?? 0);
    $p = $pid > 0 ? get_produit_by_id($pid) : null;
    if (!$p || ($p['statut'] ?? '') !== 'actif') {
        caisse_api_out(['ok' => false, 'error' => 'Produit introuvable.']);
    }
    if ((int) ($p['stock'] ?? 0) <= 0) {
        caisse_api_out(['ok' => false, 'error' => 'Produit en rupture de stock.']);
    }
    caisse_api_out(['ok' => true, 'produit' => caisse_produit_api_format($p)]);
}

if ($action === 'generer_ticket') {
    if (!admin_can_caisse_vendeur()) {
        caisse_api_out(['ok' => false, 'error' => 'Action non autorisée.'], 403);
    }
    caisse_api_check_csrf($input);
    $payload = isset($input['cart']) && is_array($input['cart']) ? $input['cart'] : $input;
    if (admin_current_role() === 'commercial') {
        $payload['inclure_tva'] = 0;
    }
    $built = caisse_build_cart_from_payload($payload);
    if (!$built['ok']) {
        caisse_api_out(['ok' => false, 'error' => $built['error'] ?? 'Panier invalide.']);
    }
    $res = caisse_creer_ticket_en_attente((int) $_SESSION['admin_id'], $built['cart']);
    if (!$res['ok']) {
        caisse_api_out(['ok' => false, 'error' => $res['error'] ?? 'Erreur lors de la création du ticket.']);
    }
    caisse_api_out([
        'ok' => true,
        'vente_id' => (int) ($res['vente_id'] ?? 0),
        'numero_ticket' => (string) ($res['numero_ticket'] ?? ''),
        'reference_caisse' => (string) ($res['reference_caisse'] ?? ''),
        'nb_lignes' => count($built['cart']['lines']),
        'redirect' => 'index.php?ticket=' . (int) ($res['vente_id'] ?? 0),
    ]);
}

if ($action === 'encaisser') {
    if (!admin_can_caisse_vendeur()) {
        caisse_api_out(['ok' => false, 'error' => 'Action non autorisée.'], 403);
    }
    caisse_api_check_csrf($input);
    $payload = isset($input['cart']) && is_array($input['cart']) ? $input['cart'] : [];
    if (admin_current_role() === 'commercial') {
        $payload['inclure_tva'] = 0;
    }
    $built = caisse_build_cart_from_payload($payload);
    if (!$built['ok']) {
        caisse_api_out(['ok' => false, 'error' => $built['error'] ?? 'Panier invalide.']);
    }
    $cart = $built['cart'];
    $mode = trim((string) ($input['mode_paiement'] ?? 'especes'));
    $totals = caisse_compute_totals($cart);
    $total = (float) $totals['total'];

    $montant_recu = isset($input['montant_recu']) && $input['montant_recu'] !== '' ? (float) str_replace(',', '.', (string) $input['montant_recu']) : null;
    $montant_especes = isset($input['montant_especes']) && $input['montant_especes'] !== '' ? (float) str_replace(',', '.', (string) $input['montant_especes']) : null;
    $montant_carte = isset($input['montant_carte']) && $input['montant_carte'] !== '' ? (float) str_replace(',', '.', (string) $input['montant_carte']) : null;
    $montant_orange_money = isset($input['montant_orange_money']) && $input['montant_orange_money'] !== '' ? (float) str_replace(',', '.', (string) $input['montant_orange_money']) : null;
    $montant_wave = isset($input['montant_wave']) && $input['montant_wave'] !== '' ? (float) str_replace(',', '.', (string) $input['montant_wave']) : null;
    $notes = trim((string) ($input['notes_vente'] ?? ''));

    $paiement = [
        'montant_recu' => $montant_recu,
        'montant_especes' => $montant_especes,
        'montant_carte' => $montant_carte,
        'montant_orange_money' => $montant_orange_money,
        'montant_wave' => $montant_wave,
        'notes' => $notes,
    ];
    if (caisse_mode_avec_montant_recu_affiche($mode) && $montant_recu !== null) {
        $paiement['monnaie_rendue'] = max(0, round($montant_recu - $total, 2));
    }

    $res = caisse_enregistrer_vente((int) $_SESSION['admin_id'], $cart, $mode, $paiement);
    if (!$res['ok']) {
        caisse_api_out(['ok' => false, 'error' => $res['error'] ?? 'Erreur encaissement.']);
    }
    caisse_api_out([
        'ok' => true,
        'vente_id' => (int) ($res['vente_id'] ?? 0),
        'numero_ticket' => (string) ($res['numero_ticket'] ?? ''),
        'redirect' => 'index.php?ticket=' . (int) ($res['vente_id'] ?? 0),
    ]);
}

caisse_api_out(['ok' => false, 'error' => 'Action inconnue.'], 400);
