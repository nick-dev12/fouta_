<?php
/**
 * WORKER « TOUT DÉTOURER » (03/09/2026) — tourne en arrière-plan (CLI), détoure
 * la photo principale de CHAQUE pièce et grave le résultat au cache. Les
 * étiquettes réutilisent ensuite ce cache : elles s'affichent avec le fond
 * retiré, sans attendre.
 *
 * Il n'écrit JAMAIS sur les photos d'origine : seul le cache
 * (upload/detour_cache) est rempli. Les photos à fond chargé sont laissées
 * telles quelles (le détourage décline de lui-même).
 *
 * Progression écrite dans upload/detour_cache/_lot.json (lue par le navigateur).
 * Lancé par detourage-lot-start.php. Programmation procédurale uniquement.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement\n");
}

$root = dirname(__DIR__, 2);            // .../fouta  (portable PC / serveur)
chdir($root);
require $root . '/conn/conn.php';       // fournit $db (PDO)
require $root . '/includes/fpl_detourage.php';

$token   = isset($argv[1]) ? (string) $argv[1] : '';
$refaire = (isset($argv[2]) && $argv[2] === '1');

@set_time_limit(0);
if (function_exists('ignore_user_abort')) {
    ignore_user_abort(true);
}

$dir  = $root . '/upload/detour_cache';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
$prog = $dir . '/_lot.json';

$ecrire = function (array $etat) use ($prog) {
    @file_put_contents($prog, json_encode($etat, JSON_UNESCAPED_UNICODE));
};

// « Tout refaire » : on efface le cache pour reconstruire à neuf.
if ($refaire) {
    foreach (glob($dir . '/*.png') as $f) {
        @unlink($f);
    }
}

try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $rows = $db->query(
        "SELECT id, image_principale FROM produits
         WHERE sync_deleted_at IS NULL AND image_principale <> '' AND image_principale IS NOT NULL
         ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $ecrire([
        'token' => $token, 'total' => 0, 'fait' => 0, 'uni' => 0, 'charge' => 0,
        'absente' => 0, 'termine' => true, 'erreur' => 'Base : ' . $e->getMessage(),
        'demarre' => time(), 'battement' => time(), 'ids_uni' => [],
    ]);
    exit;
}

$etat = [
    'token'    => $token,
    'total'    => count($rows),
    'fait'     => 0,
    'uni'      => 0,
    'charge'   => 0,
    'absente'  => 0,
    'termine'  => false,
    'erreur'   => null,
    'demarre'  => time(),
    'battement' => time(),
    'ids_uni'  => [], // premiers détourés, pour la planche de preuve à l'écran
];
$ecrire($etat);

foreach ($rows as $r) {
    try {
        $p = 'upload/' . ltrim((string) $r['image_principale'], '/');
        if (!is_file($p)) {
            $etat['absente']++;
        } else {
            $res = fpl_detourage_fichier($p);
            if ($res === null) {
                $etat['charge']++;
            } else {
                $etat['uni']++;
                if (count($etat['ids_uni']) < 60) {
                    $etat['ids_uni'][] = (int) $r['id'];
                }
                if ($res['img'] instanceof GdImage || is_resource($res['img'])) {
                    imagedestroy($res['img']);
                }
            }
        }
    } catch (Throwable $e) {
        // une photo abîmée ne doit pas arrêter le lot
        $etat['charge']++;
    }
    $etat['fait']++;
    $etat['battement'] = time();
    if ($etat['fait'] % 5 === 0 || $etat['fait'] === $etat['total']) {
        $ecrire($etat);
    }
}

$etat['termine'] = true;
$etat['battement'] = time();
$ecrire($etat);
