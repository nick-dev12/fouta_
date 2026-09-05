<?php
/**
 * ENREGISTRER LES PHOTOS D'UNE PIÈCE (JSON) — le cœur de l'espace photographe.
 *
 * N'ÉCRIT QUE les photos : UPDATE ciblé de image_principale + images (JSON).
 * JAMAIS update_produit() (qui réécrirait nom/prix/stock/catégorie). Réutilise
 * le moteur photo existant (upload_produit_images_multiples, image_optimizer)
 * pour la validation, la conversion WebP et les variantes.
 *
 * POST (multipart) :
 *   id                       : la pièce
 *   ordre                    : JSON, chemins relatifs GARDÉS dans l'ordre voulu
 *                              (le 1er devient la photo principale)
 *   images_supplementaires[] : nouveaux fichiers téléversés (multipart)
 *   collee                   : (optionnel) une image collée, en data:URL
 *   _jeton / X-CSRF-TOKEN     : jeton de session (admin_csrf)
 */

session_start();

require_once __DIR__ . '/../includes/require_access_json.php';
require_once __DIR__ . '/../../conn/conn.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../controllers/controller_produits.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';

header('Content-Type: application/json; charset=utf-8');

$repondre = function (array $x) {
    echo json_encode($x, JSON_UNESCAPED_UNICODE);
    exit;
};

/* CSRF : en-tête X-CSRF-TOKEN (comme les autres AJAX) ou champ _jeton. */
$jeton = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN']
    : (isset($_POST['_jeton']) ? (string) $_POST['_jeton'] : '');
if (empty($_SESSION['admin_csrf']) || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
    http_response_code(419);
    $repondre(['ok' => false, 'error' => 'Session expirée, rechargez la page.']);
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    $repondre(['ok' => false, 'error' => 'Pièce inconnue.']);
}

$piece = null;
try {
    $st = $db->prepare("SELECT id, images, image_principale FROM produits WHERE id = :id AND sync_deleted_at IS NULL");
    $st->execute([':id' => $id]);
    $piece = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    $piece = null;
}
if ($piece === null) {
    $repondre(['ok' => false, 'error' => 'Pièce introuvable.']);
}

/* Les photos ACTUELLES (pour repérer les retirées à supprimer du disque). */
$actuelles = json_decode((string) $piece['images'], true);
if (!is_array($actuelles)) {
    $actuelles = [];
    if (!empty($piece['image_principale'])) {
        $actuelles[] = (string) $piece['image_principale'];
    }
}

/* Les GARDÉES, dans l'ordre voulu (validées : seulement des chemins qui
   existaient — on n'accepte pas n'importe quel chemin venu du client). */
$ordre = json_decode(isset($_POST['ordre']) ? (string) $_POST['ordre'] : '[]', true);
if (!is_array($ordre)) {
    $ordre = $actuelles;
}
$gardees = [];
foreach ($ordre as $rel) {
    $rel = trim((string) $rel);
    if ($rel !== '' && in_array($rel, $actuelles, true) && !in_array($rel, $gardees, true)) {
        $gardees[] = $rel;
    }
}

/* Les NOUVELLES : fichiers téléversés + éventuelle image collée. */
$nouvelles = [];
$err = null;
if (!empty($_FILES['images_supplementaires']) && is_array($_FILES['images_supplementaires']['name'])) {
    $nouvelles = upload_produit_images_multiples($_FILES, 'images_supplementaires', $err);
    if ($err !== null) {
        $repondre(['ok' => false, 'error' => $err]);
    }
}

/* Image COLLÉE (data:URL) : décodée vers un fichier temporaire, puis rangée
   par le même optimiseur (WebP + variantes) que les uploads. */
if (!empty($_POST['collee']) && is_string($_POST['collee'])
    && preg_match('#^data:image/(png|jpe?g|webp);base64,#i', (string) $_POST['collee'])) {
    $b64 = substr((string) $_POST['collee'], strpos((string) $_POST['collee'], ',') + 1);
    $bin = base64_decode($b64, true);
    if ($bin !== false && strlen($bin) > 64 && strlen($bin) <= FOUTA_UPLOAD_IMAGE_MAX_BYTES) {
        $tmp = tempnam(sys_get_temp_dir(), 'fplpaste');
        if ($tmp !== false && file_put_contents($tmp, $bin) !== false) {
            $res = image_optimizer_process_tmp($tmp, __DIR__ . '/../../upload/produits/', 'produits', 'produit_');
            @unlink($tmp);
            // image_optimizer_process_tmp renvoie un TABLEAU {success, relative_path, …}
            if (is_array($res) && !empty($res['success']) && !empty($res['relative_path'])) {
                $nouvelles[] = (string) $res['relative_path'];
            }
        }
    }
}

/* La galerie finale = gardées (ordre) + nouvelles (à la fin), sans doublon.
   La 1re EST la principale. Refus si tout serait vide. */
$finale = array_values(array_unique(array_merge($gardees, $nouvelles)));
if ($finale === []) {
    $repondre(['ok' => false, 'error' => 'Il faut au moins une photo.']);
}
$principale = $finale[0];

try {
    $up = $db->prepare("UPDATE produits SET image_principale = :p, images = :j, date_modification = NOW() WHERE id = :id");
    $up->execute([':p' => $principale, ':j' => json_encode($finale, JSON_UNESCAPED_UNICODE), ':id' => $id]);
} catch (PDOException $e) {
    $repondre(['ok' => false, 'error' => 'Enregistrement impossible.']);
}

/* Ménage disque : les photos retirées (dans les actuelles, plus dans la
   finale) — avec leurs variantes _md/_sm. */
foreach (array_diff($actuelles, $finale) as $retiree) {
    if (function_exists('image_optimizer_delete_with_variants')) {
        image_optimizer_delete_with_variants($retiree);
    }
}

/* On renvoie la galerie relue (URLs prêtes à afficher) pour rafraîchir l'écran. */
$urls = [];
foreach ($finale as $rel) {
    $urls[] = ['rel' => $rel, 'url' => '../../upload/' . ltrim(str_replace('\\', '/', $rel), '/')];
}
$repondre(['ok' => true, 'principale' => $principale, 'photos' => $urls]);
