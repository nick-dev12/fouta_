<?php
/**
 * /p-photo.php — LES PHOTOS DE LA VITRINE CLIENT, SANS LEUR FOND (11/09/2026).
 *
 * Demande de la direction : quand on scanne le QR de l'étiquette d'une pièce,
 * la photo ne doit plus montrer son fond blanc. L'étiquette pose déjà la pièce
 * détourée (includes/etiquette_fpl70.php) ; la vitrine reprend le même
 * détourage, donc le même cache, et sert une version allégée pour le
 * téléphone : WebP avec transparence, 900 px (180 px pour les vignettes).
 *
 * Aucune entrée libre : la pièce se retrouve par son code (EAN-13 ou
 * identifiant FPL, comme /p/{code}) et la photo par son rang dans la liste de
 * fpl_vitrine_photos_chemins(). Une photo que le détourage refuse (fond
 * chargé) est servie telle quelle. Aucune session n'est ouverte.
 */

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/includes/produit_vitrine.php';

$identifiant = fpl_vitrine_code_vers_identifiant($_GET['code'] ?? '');
$rang = max(0, (int) ($_GET['n'] ?? 0));
$taille = (int) ($_GET['t'] ?? 900) === 180 ? 180 : 900;

$chemins = [];
if ($identifiant !== '') {
    try {
        $st = $db->prepare('SELECT image_principale, images, image_etiquette_fpl FROM produits
            WHERE UPPER(TRIM(identifiant_interne)) = :code AND sync_deleted_at IS NULL LIMIT 1');
        $st->execute([':code' => $identifiant]);
        $piece = $st->fetch(PDO::FETCH_ASSOC);
        if ($piece) {
            $chemins = fpl_vitrine_photos_chemins($piece, __DIR__ . '/upload');
        }
    } catch (PDOException $e) {
        error_log('[p-photo] ' . $e->getMessage());
    }
}
if (!isset($chemins[$rang])) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

$relatif = $chemins[$rang];
$fichier = fpl_vitrine_photo_detouree_fichier(__DIR__ . '/upload/' . $relatif, $taille);
if ($fichier === null) {
    header('Cache-Control: public, max-age=86400');
    header('Location: /upload/' . implode('/', array_map('rawurlencode', explode('/', $relatif))), true, 302);
    exit;
}
header('Content-Type: image/webp');
header('Content-Length: ' . filesize($fichier));
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
readfile($fichier);
