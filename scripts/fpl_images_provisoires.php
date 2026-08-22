<?php
/**
 * IMAGES DE SUBSTITUTION — pour juger la mise en page sans le serveur.
 *
 * Les vraies photos vivent sur la production et arrivent par
 * scripts/pull_prod_to_dev.php --files-only. Tant qu'on ne les a pas, le
 * catalogue n'affiche que des icônes grises : impossible de juger la forme
 * des cartes et du tableau.
 *
 * Ce script fabrique, aux NOMS EXACTS que la base attend, des vignettes
 * neutres portant les initiales de la pièce ou de la catégorie. Rien de plus.
 *
 *   php scripts/fpl_images_provisoires.php            (fabrique)
 *   php scripts/fpl_images_provisoires.php --retirer  (efface)
 *
 * Chaque fichier produit porte le marqueur « FPL-PROVISOIRE » dans ses
 * métadonnées : --retirer n'efface que ceux-là, jamais une vraie photo.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$racine = dirname(__DIR__);
$upload = $racine . '/upload';
$retirer = in_array('--retirer', $argv, true);
$marqueur = 'FPL-PROVISOIRE';

$db = new PDO('mysql:host=127.0.0.1;dbname=jomas_fouta3;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

/** Les chemins d'image attendus par la base, catégories puis pièces. */
function chemins_attendus(PDO $db)
{
    $liste = [];
    foreach ($db->query("SELECT image, nom FROM categories
                          WHERE image IS NOT NULL AND image <> ''") as $r) {
        $liste[$r['image']] = $r['nom'];
    }
    foreach ($db->query("SELECT image_principale, nom FROM produits
                          WHERE image_principale IS NOT NULL AND image_principale <> ''") as $r) {
        $liste[$r['image_principale']] = $r['nom'];
    }

    return $liste;
}

/** Deux lettres tirées du nom, pour reconnaître la vignette d'un coup d'œil. */
function initiales($nom)
{
    $mots = preg_split('/[\s\-_]+/u', trim((string) $nom), -1, PREG_SPLIT_NO_EMPTY);
    $t = '';
    foreach (array_slice($mots, 0, 2) as $m) {
        $t .= mb_strtoupper(mb_substr($m, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return $t !== '' ? $t : '?';
}

$fichier_inventaire = $upload . '/.fpl-provisoires.json';
$deja = is_file($fichier_inventaire)
    ? (array) json_decode((string) file_get_contents($fichier_inventaire), true)
    : [];
$inventaire = [];

$attendus = chemins_attendus($db);
$faits = 0;
$effaces = 0;
$ignores = 0;

foreach ($attendus as $rel => $nom) {
    $chemin = $upload . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    $dossier = dirname($chemin);

    if ($retirer) {
        if (is_file($chemin) && in_array($rel, $deja, true)) {
            @unlink($chemin);
            $effaces++;
        }
        continue;
    }

    if (is_file($chemin)) {
        $ignores++;          // une vraie photo est déjà là : on n'y touche pas
        continue;
    }
    if (!is_dir($dossier) && !@mkdir($dossier, 0777, true) && !is_dir($dossier)) {
        continue;
    }

    // Une vignette sobre : aplat clair, initiales en bleu, cadre discret.
    $img = imagecreatetruecolor(400, 400);
    $fond = imagecolorallocate($img, 237, 241, 248);   // --blue-tint
    $trait = imagecolorallocate($img, 216, 224, 238);  // --blue-tint-2
    $encre = imagecolorallocate($img, 29, 69, 144);    // --blue-600
    imagefilledrectangle($img, 0, 0, 399, 399, $fond);
    imagerectangle($img, 0, 0, 399, 399, $trait);

    $texte = initiales($nom);
    $police = 5;
    $l = imagefontwidth($police) * strlen($texte);
    $h = imagefontheight($police);
    imagestring($img, $police, (int) ((400 - $l) / 2), (int) ((400 - $h) / 2), $texte, $encre);

    $ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
    if ($ext === 'png') {
        imagepng($img, $chemin);
    } elseif ($ext === 'webp') {
        imagewebp($img, $chemin);
    } else {
        imagejpeg($img, $chemin, 85);
    }
    imagedestroy($img);

    // On NE touche PAS aux octets de l'image. Y ajouter un commentaire de fin
    // corrompt les PNG, que le navigateur refuse alors d'afficher — c'était le
    // défaut de la première version. Les fichiers produits sont inscrits dans
    // un inventaire, à côté, et c'est lui qui sert à les retirer.
    $inventaire[] = $rel;
    $faits++;
}

if ($retirer) {
    @unlink($fichier_inventaire);
    echo "Images provisoires effacées : $effaces\n";
} else {
    file_put_contents(
        $fichier_inventaire,
        json_encode(array_values(array_unique(array_merge($deja, $inventaire))))
    );
    echo "Images provisoires fabriquées : $faits\n";
    echo "Vraies photos déjà présentes, laissées intactes : $ignores\n";
    echo "\nPour tout retirer quand les vraies arriveront :\n";
    echo "  php scripts/fpl_images_provisoires.php --retirer\n";
}
