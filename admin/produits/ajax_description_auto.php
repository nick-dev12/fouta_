<?php
/**
 * Description suggérée (JSON) — reprise de FPL natif.
 *
 * Quand la référence OEM ou la référence fournisseur saisie est déjà connue du
 * catalogue, on renvoie la description de cette pièce-là. Ce n'est pas une
 * génération : c'est une recherche, et le champ reste modifiable à la main.
 * Aucune écriture, aucune donnée touchée.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['found' => false, 'description' => null]);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_produits.php';

header('Content-Type: application/json; charset=utf-8');

$oem = isset($_GET['oem']) ? trim((string) $_GET['oem']) : '';
$ref = isset($_GET['ref']) ? trim((string) $_GET['ref']) : '';
$description = null;

try {
    // La référence OEM prime : c'est celle du constructeur, la plus fiable.
    if ($oem !== '' && produits_has_column('reference_oem')) {
        $stmt = $db->prepare("SELECT description FROM produits
                               WHERE reference_oem = :v
                                 AND description IS NOT NULL AND description <> ''
                               ORDER BY id DESC LIMIT 1");
        $stmt->execute(['v' => $oem]);
        $trouve = $stmt->fetchColumn();
        if ($trouve !== false) {
            $description = $trouve;
        }
    }
    if ($description === null && $ref !== '' && produits_has_column('reference_fournisseur')) {
        $stmt = $db->prepare("SELECT description FROM produits
                               WHERE reference_fournisseur = :v
                                 AND description IS NOT NULL AND description <> ''
                               ORDER BY id DESC LIMIT 1");
        $stmt->execute(['v' => $ref]);
        $trouve = $stmt->fetchColumn();
        if ($trouve !== false) {
            $description = $trouve;
        }
    }
} catch (PDOException $e) {
    $description = null;
}

echo json_encode([
    'found' => $description !== null,
    'description' => $description,
], JSON_UNESCAPED_UNICODE);
