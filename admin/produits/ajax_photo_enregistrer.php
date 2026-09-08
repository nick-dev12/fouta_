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
 *                              (une NOUVELLE photo devient la principale, sauf si
 *                              l'ordre désigne une autre gardée que l'ancienne
 *                              principale — voir includes/photo_editeur.php)
 *   images_supplementaires[] : nouveaux fichiers téléversés (multipart)
 *   collee                   : (optionnel) une image collée, en data:URL
 *   empreinte                : OBLIGATOIRE — l'empreinte de la galerie chargée par
 *                              l'écran (data-empreinte) ; refus 409 si la base a
 *                              bougé entre-temps, ou si elle manque
 *   _jeton / X-CSRF-TOKEN     : jeton de session (admin_csrf)
 *
 * Seul appelant connu : js/admin-photo-editer.js (vérifié le 08/09/2026).
 */

session_start();

require_once __DIR__ . '/../includes/require_access_json.php';
require_once __DIR__ . '/../../conn/conn.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../controllers/controller_produits.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';
require_once __DIR__ . '/../../includes/photo_editeur.php';

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

/* CONTRE LA COURSE ENTRE DEUX ENREGISTREMENTS (08/09/2026) : un onglet ouvert
   AVANT un autre enregistrement (autre poste, autre onglet, « pièce suivante »)
   qui enregistre APRÈS remettait l'ancien ordre et effaçait du disque, plus bas,
   les photos ajoutées entre-temps. L'écran emporte l'empreinte de la galerie
   qu'il a chargée ; si la ligne actuelle n'a plus la même, on refuse TOUT —
   avant d'avoir écrit un seul fichier (les téléversements viennent après).
   Une empreinte absente est refusée aussi : pas de contournement silencieux. */
$empreinte_client = isset($_POST['empreinte']) ? trim((string) $_POST['empreinte']) : '';
$empreinte_base = photo_editeur_empreinte($piece['image_principale'], $piece['images']);
if ($empreinte_client === '' || !hash_equals($empreinte_base, $empreinte_client)) {
    http_response_code(409);
    $repondre(['ok' => false, 'error' => 'La galerie a été modifiée entre-temps (autre poste ou onglet) : rechargez la page. Les images collées non enregistrées seront à recoller.']);
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

/* La galerie finale (08/09/2026) : les NOUVELLES d'abord — jusqu'ici elles
   arrivaient à la fin et l'ancienne photo restait principale (étiquette, page
   du QR, catalogue) tant qu'on ne cliquait pas « Principale » puis Enregistrer
   une seconde fois. Sauf si l'ordre envoyé désigne lui-même une autre gardée
   que l'ancienne principale : ce choix-là est respecté. Sans doublon ; la 1re
   EST la principale. Refus si tout serait vide. */
$finale = photo_editeur_composer_galerie(
    $gardees,
    $nouvelles,
    photo_editeur_principale_choisie($gardees, $piece['image_principale'], $actuelles)
);
if ($finale === []) {
    $repondre(['ok' => false, 'error' => 'Il faut au moins une photo.']);
}
$principale = $finale[0];

try {
    /* LA SYNCHRO DOIT VOIR CE CHANGEMENT (08/09) : la ligne n'est poussée vers le
       site public que si sync_updated_at avance. Ce sont les déclencheurs MySQL
       qui s'en chargent d'ordinaire — mais foutasvr les a perdus à l'import du
       01/09 (et ne peut pas les recréer sans le droit SUPER : erreur 1419).
       Résultat vu par la direction : les anciennes photos restaient sur la
       page du QR. On marque donc la ligne nous-mêmes, déclencheurs ou pas. */
    $up = $db->prepare("UPDATE produits SET image_principale = :p, images = :j, date_modification = NOW()"
        . (produits_has_column('sync_updated_at') ? ", sync_updated_at = NOW()" : '')
        . " WHERE id = :id");
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

/* On renvoie la galerie relue (URLs prêtes à afficher) pour rafraîchir l'écran,
   et la NOUVELLE empreinte, relue en base (08/09) : sans elle, le second
   enregistrement du même onglet serait refusé comme une course. */
$urls = [];
foreach ($finale as $rel) {
    $urls[] = ['rel' => $rel, 'url' => '../../upload/' . ltrim(str_replace('\\', '/', $rel), '/')];
}
$empreinte_neuve = photo_editeur_empreinte($principale, json_encode($finale, JSON_UNESCAPED_UNICODE));
try {
    $st = $db->prepare("SELECT images, image_principale FROM produits WHERE id = :id");
    $st->execute([':id' => $id]);
    $relue = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($relue)) {
        $empreinte_neuve = photo_editeur_empreinte($relue['image_principale'], $relue['images']);
    }
} catch (PDOException $e) {
    // on garde l'empreinte calculée sur ce qu'on vient d'écrire
}
$repondre(['ok' => true, 'principale' => $principale, 'photos' => $urls, 'empreinte' => $empreinte_neuve]);
