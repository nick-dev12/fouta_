<?php
/**
 * Recherche clients (users + contacts) pour commande manuelle - retourne JSON
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce_json_empty();

require_once __DIR__ . '/../../models/model_contacts.php';

$recherche = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = min(30, max(5, (int) ($_GET['limit'] ?? 20)));

$resultats = search_clients_for_commande($recherche, $limit);

$out = [];
foreach ($resultats as $r) {
    $nom_complet = trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''));
    $out[] = [
        'id' => (int) $r['id'],
        'source' => $r['source'] ?? 'user',
        'nom' => $r['nom'] ?? '',
        'prenom' => $r['prenom'] ?? '',
        'nom_complet' => $nom_complet,
        'telephone' => $r['telephone'] ?? '',
        'email' => $r['email'] ?? ''
    ];
}

echo json_encode($out);
