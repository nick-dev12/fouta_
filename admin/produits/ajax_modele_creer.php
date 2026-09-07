<?php
/**
 * CRÉER UN MODÈLE DE VÉHICULE DEPUIS LE FORMULAIRE D'AJOUT DE PIÈCE (07/09).
 *
 * Demande de la direction : il manque des modèles, et il n'y a pas d'écran
 * pour les créer ; pour faciliter le travail, la gestion de stock simple peut
 * créer un modèle à la volée, depuis « Ajouter une pièce », pour l'instant.
 *
 * Entrée (POST) : marque_id, nom, _jeton (ou en-tête X-CSRF-TOKEN).
 * Sortie (JSON) : { ok, id, nom, existait } ou { ok:false, message }.
 * Règles : même garde que l'ajout de pièce (compte restreint exclu) ; nom de
 * 2 à 100 caractères ; un modèle déjà présent pour la marque (même nom, sans
 * tenir compte de la casse) est RENDU tel quel (jamais de doublon) ; un modèle
 * retiré (suppression douce) sous ce nom est restauré ; uuid posé pour la sync.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Session expirée.']);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../conn/conn.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || admin_is_restricted_admin_account()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Non autorisé.']);
    exit;
}

$jeton = isset($_POST['_jeton']) ? (string) $_POST['_jeton'] : (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['admin_csrf']) || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'Jeton invalide : rechargez la page.']);
    exit;
}

$marque_id = isset($_POST['marque_id']) ? (int) $_POST['marque_id'] : 0;
$nom = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nom'] ?? '')));
if ($marque_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Choisissez d\'abord la marque.']);
    exit;
}
if (mb_strlen($nom, 'UTF-8') < 2 || mb_strlen($nom, 'UTF-8') > 100) {
    echo json_encode(['ok' => false, 'message' => 'Le nom du modèle doit faire entre 2 et 100 caractères.']);
    exit;
}

try {
    /** @var PDO $db */
    $st = $db->prepare('SELECT id, nom FROM marques WHERE id = :id AND sync_deleted_at IS NULL');
    $st->execute([':id' => $marque_id]);
    $marque = $st->fetch(PDO::FETCH_ASSOC);
    if (!$marque) {
        echo json_encode(['ok' => false, 'message' => 'Marque introuvable.']);
        exit;
    }

    // déjà là ? (même nom, casse ignorée), retiré ? → restauré
    $st = $db->prepare('SELECT id, nom, sync_deleted_at FROM vehicule_modeles WHERE marque_id = :m AND LOWER(nom) = LOWER(:n) ORDER BY sync_deleted_at IS NULL DESC, id LIMIT 1');
    $st->execute([':m' => $marque_id, ':n' => $nom]);
    $existant = $st->fetch(PDO::FETCH_ASSOC);
    if ($existant) {
        if ($existant['sync_deleted_at'] !== null) {
            $db->prepare('UPDATE vehicule_modeles SET sync_deleted_at = NULL, sync_updated_at = NOW(), date_modification = NOW() WHERE id = :id')
               ->execute([':id' => (int) $existant['id']]);
        }
        echo json_encode(['ok' => true, 'id' => (int) $existant['id'], 'nom' => (string) $existant['nom'], 'existait' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->prepare('INSERT INTO vehicule_modeles (marque_id, nom, date_creation, sync_uuid, sync_updated_at) VALUES (:m, :n, NOW(), UUID(), NOW())')
       ->execute([':m' => $marque_id, ':n' => $nom]);
    $id = (int) $db->lastInsertId();
    echo json_encode(['ok' => true, 'id' => $id, 'nom' => $nom, 'existait' => false], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erreur d\'enregistrement.']);
}
