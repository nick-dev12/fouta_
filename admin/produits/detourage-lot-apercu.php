<?php
/**
 * APERÇU DÉTOURÉ (03/09/2026) — renvoie en PNG (fond transparent) la photo
 * principale d'une pièce, détourée. Sert la planche de preuve à l'écran.
 * Réutilise le cache : instantané si la pièce a déjà été traitée par le lot.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

$root = dirname(__DIR__, 2);
require $root . '/conn/conn.php'; // $db
require $root . '/includes/fpl_detourage.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit;
}

try {
    $st = $db->prepare("SELECT image_principale FROM produits WHERE id = ? AND sync_deleted_at IS NULL");
    $st->execute([$id]);
    $rel = (string) $st->fetchColumn();
} catch (Throwable $e) {
    http_response_code(500);
    exit;
}
if ($rel === '') {
    http_response_code(404);
    exit;
}

$chemin = $root . '/upload/' . ltrim($rel, '/');
$res = fpl_detourage_fichier($chemin);

header('Content-Type: image/png');
header('Cache-Control: private, max-age=86400');

if ($res === null) {
    // fond chargé (non détouré) : on renvoie la photo d'origine telle quelle
    if (is_file($chemin)) {
        $t = @getimagesize($chemin);
        $src = null;
        if ($t) {
            switch ($t[2]) {
                case IMAGETYPE_PNG:  $src = @imagecreatefrompng($chemin); break;
                case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($chemin); break;
                case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($chemin); break;
            }
        }
        if ($src) {
            imagepng($src);
            imagedestroy($src);
            exit;
        }
    }
    http_response_code(404);
    exit;
}

imagesavealpha($res['img'], true);
imagepng($res['img']);
imagedestroy($res['img']);
