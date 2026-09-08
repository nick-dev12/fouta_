<?php
/**
 * LA PHOTO DE L'ÉTIQUETTE DE PIÈCE — l'ordre des candidates et le rôle (08/09/2026).
 *
 * Constat de la direction : « les images ajoutées auparavant s'affichent même
 * après avoir changé les photos ». Deux causes locales, dans le rendu :
 *
 *  1. L'ORDRE DES CANDIDATES. etiquette70_donnees_pour_produit() prenait la
 *     première photo dont le FICHIER existe dans l'ordre principale → dédiée
 *     (image_etiquette_fpl, jamais mise à jour par l'éditeur photo) → images[0].
 *     Dès que le fichier de la principale manquait, l'ancienne dédiée ressortait
 *     avant la galerie, qui est pourtant la photo entretenue. Attendu désormais :
 *     principale → TOUTE la galerie dans l'ordre → dédiée en DERNIER repli
 *     (8 pièces n'ont qu'elle : elle doit rester une candidate vivante).
 *
 *  2. LE FILTRE PAR RÔLE. Les pages d'étiquette chargeaient la pièce par
 *     get_produit_by_id(), qui retire de la ligne les colonnes des champs que
 *     le rôle ne voit pas : « Galerie photos » masquée → image_principale et
 *     images disparaissent, image_etiquette_fpl reste, l'étiquette de ce compte
 *     n'a plus que l'ancienne photo. Attendu : ces rendus chargent la pièce par
 *     get_produit_by_id_sans_filtre_acces().
 *
 * Le point 1 est prouvé par LECTURE du code ET par EXÉCUTION sur une pièce
 * fictive dont les photos sont des fichiers TEMPORAIRES du système (créés puis
 * supprimés) ; le point 2 par lecture des pages. Aucune base de données n'est
 * ouverte : le test est en lecture seule par construction.
 *
 * À jouer :  php tests/test_etiquette_photo_candidates.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/includes/etiquette_fpl70.php';

$ok = 0;
$ko = 0;
function verifie($libelle, $attendu, $obtenu) {
    global $ok, $ko;
    if ($attendu === $obtenu) {
        $ok++;
        echo "  OK  $libelle\n";
    } else {
        $ko++;
        echo "  KO  $libelle (attendu " . var_export($attendu, true) . ", obtenu " . var_export($obtenu, true) . ")\n";
    }
}

/** Le corps d'une fonction, lu par réflexion — sans l'exécuter. */
function corps_fonction($nom) {
    $r = new ReflectionFunction($nom);
    $lignes = file($r->getFileName());

    return implode('', array_slice($lignes, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
}

/* Le moteur résout toujours une photo sous upload/ (__DIR__ . '/../upload/' .
   chemin relatif). Pour qu'un fichier du dossier temp du SYSTÈME serve de
   photo, on lui donne un chemin qui REMONTE depuis upload/ jusqu'à lui
   (../../tmp/x.png) — is_file() résout les « .. » comme n'importe quel
   chemin. Sur Windows, un dossier temp sur un autre lecteur ne peut pas être
   atteint ainsi : on le dit plutôt que de faire semblant. */
function chemin_relatif_depuis_upload($absolu) {
    global $RACINE;
    $segments = function ($p) {
        return array_values(array_filter(explode('/', str_replace('\\', '/', (string) $p)), 'strlen'));
    };
    $de = $segments(realpath($RACINE . '/upload'));
    $vers = $segments($absolu);
    if ($de === [] || $vers === []) {
        return null;
    }
    if (preg_match('/^[a-z]:$/i', $de[0]) && strcasecmp($de[0], $vers[0]) !== 0) {
        return null; // autre lecteur Windows : pas de chemin relatif possible
    }
    $i = 0;
    while ($i < count($de) && $i < count($vers) && strcasecmp($de[$i], $vers[$i]) === 0) {
        $i++;
    }

    return str_repeat('../', count($de) - $i) . implode('/', array_slice($vers, $i));
}

/** Le code d'un fichier PHP sans ses commentaires — un commentaire qui NOMME
    une fonction n'est pas un appel. */
function code_sans_commentaires($chemin) {
    $code = '';
    foreach (token_get_all((string) file_get_contents($chemin)) as $jeton) {
        if (is_array($jeton)) {
            if ($jeton[0] === T_COMMENT || $jeton[0] === T_DOC_COMMENT) {
                continue;
            }
            $code .= $jeton[1];
        } else {
            $code .= $jeton;
        }
    }

    return $code;
}

/** Compare le chemin rendu par le moteur au fichier temporaire attendu. */
function meme_fichier($chemin, $attendu) {
    if ($chemin === null || $attendu === null) {
        return $chemin === $attendu;
    }
    $a = realpath($chemin);
    $b = realpath($attendu);

    return $a !== false && $b !== false && strcasecmp($a, $b) === 0;
}

echo "— 1. l'ordre des candidates, par lecture du code —\n";
$corps = corps_fonction('etiquette70_donnees_pour_produit');
$pos_principale = strpos($corps, "\$candidates = [(string) (\$produit['image_principale']");
$pos_galerie = strpos($corps, 'foreach ($imgs as $img_rel)');
$pos_dediee = strpos($corps, "\$candidates[] = (string) (\$produit['image_etiquette_fpl']");
verifie('la principale ouvre la liste des candidates', true, $pos_principale !== false);
verifie('TOUTE la galerie est parcourue (plus seulement images[0])', true,
    $pos_galerie !== false && strpos($corps, '$imgs[0]') === false);
verifie('la dédiée (image_etiquette_fpl) reste une candidate', true, $pos_dediee !== false);
verifie('ordre : principale, puis la galerie, puis la dédiée en dernier', true,
    $pos_principale !== false && $pos_galerie !== false && $pos_dediee !== false
    && $pos_principale < $pos_galerie && $pos_galerie < $pos_dediee);
verifie("le fichier doit exister pour qu'une candidate soit retenue", true,
    strpos($corps, 'if (is_file($chemin))') !== false);

echo "— 2. l'ordre des candidates, par exécution sur des fichiers temporaires —\n";
$temp = sys_get_temp_dir();
$marque = 'fpl_etq_' . uniqid('', true);
$fichiers = [
    'principale' => $temp . DIRECTORY_SEPARATOR . $marque . '_principale.png',
    'galerie0' => $temp . DIRECTORY_SEPARATOR . $marque . '_galerie0.png',
    'galerie1' => $temp . DIRECTORY_SEPARATOR . $marque . '_galerie1.png',
    'dediee' => $temp . DIRECTORY_SEPARATOR . $marque . '_dediee.png',
];
$absente = $temp . DIRECTORY_SEPARATOR . $marque . '_absente.png'; // jamais créée
$rel = [];
foreach ($fichiers as $cle => $abs) {
    $rel[$cle] = chemin_relatif_depuis_upload($abs);
}
$rel['absente'] = chemin_relatif_depuis_upload($absente);

if (in_array(null, $rel, true)) {
    echo "  --  dossier temp inatteignable depuis upload/ (autre lecteur) : exécution sautée\n";
} else {
    foreach ($fichiers as $abs) {
        file_put_contents($abs, 'x');
    }
    $base = ['id' => 1, 'identifiant_interne' => 'FPL000001', 'nom' => 'Pièce fictive'];

    /* la galerie fraîche gagne sur l'ancienne dédiée quand la principale manque */
    $d = etiquette70_donnees_pour_produit($base + [
        'image_principale' => $rel['absente'],
        'images' => json_encode([$rel['galerie0'], $rel['galerie1']]),
        'image_etiquette_fpl' => $rel['dediee'],
    ]);
    verifie('principale ABSENTE + galerie présente → la galerie gagne sur la dédiée', true,
        meme_fichier($d['photo_chemin'], $fichiers['galerie0']));

    /* la galerie est parcourue en entier : sa deuxième photo sert si la première manque */
    $d = etiquette70_donnees_pour_produit($base + [
        'image_principale' => $rel['absente'],
        'images' => json_encode([$rel['absente'], $rel['galerie1']]),
        'image_etiquette_fpl' => $rel['dediee'],
    ]);
    verifie('galerie[0] absente → galerie[1] sert, toujours avant la dédiée', true,
        meme_fichier($d['photo_chemin'], $fichiers['galerie1']));

    /* la principale, quand son fichier existe, reste souveraine */
    $d = etiquette70_donnees_pour_produit($base + [
        'image_principale' => $rel['principale'],
        'images' => json_encode([$rel['galerie0']]),
        'image_etiquette_fpl' => $rel['dediee'],
    ]);
    verifie('principale présente → elle gagne sur la galerie et la dédiée', true,
        meme_fichier($d['photo_chemin'], $fichiers['principale']));

    /* les 8 pièces qui n'ont QUE la dédiée : elle sert toujours */
    $d = etiquette70_donnees_pour_produit($base + [
        'image_principale' => '',
        'images' => '[]',
        'image_etiquette_fpl' => $rel['dediee'],
    ]);
    verifie('rien sauf la dédiée → la dédiée sert', true, meme_fichier($d['photo_chemin'], $fichiers['dediee']));

    $d = etiquette70_donnees_pour_produit($base + ['image_etiquette_fpl' => $rel['dediee']]);
    verifie('colonnes principale/galerie ABSENTES de la ligne + dédiée → la dédiée sert', true,
        meme_fichier($d['photo_chemin'], $fichiers['dediee']));

    /* la galerie n'est plus un cul-de-sac : tous ses fichiers absents → la dédiée */
    $d = etiquette70_donnees_pour_produit($base + [
        'image_principale' => $rel['absente'],
        'images' => json_encode([$rel['absente']]),
        'image_etiquette_fpl' => $rel['dediee'],
    ]);
    verifie('principale et galerie absentes → la dédiée en dernier repli', true,
        meme_fichier($d['photo_chemin'], $fichiers['dediee']));

    /* une galerie mal formée ne casse rien */
    $d = etiquette70_donnees_pour_produit($base + [
        'image_principale' => '',
        'images' => '{pas du json',
        'image_etiquette_fpl' => $rel['dediee'],
    ]);
    verifie('galerie illisible → la dédiée sert quand même', true, meme_fichier($d['photo_chemin'], $fichiers['dediee']));

    /* aucun fichier nulle part → pas de photo, sans erreur */
    $d = etiquette70_donnees_pour_produit($base + [
        'image_principale' => $rel['absente'],
        'images' => json_encode([$rel['absente']]),
        'image_etiquette_fpl' => $rel['absente'],
    ]);
    verifie('aucun fichier existant → photo_chemin null', null, $d['photo_chemin']);

    foreach ($fichiers as $abs) {
        @unlink($abs);
    }
    verifie('les fichiers temporaires sont supprimés', false,
        is_file($fichiers['principale']) || is_file($fichiers['galerie0']) || is_file($fichiers['galerie1']) || is_file($fichiers['dediee']));
}

echo "— 3. les rendus d'étiquette chargent la pièce SANS filtre d'accès —\n";
$modele = file_get_contents($RACINE . '/models/model_produits.php');
verifie('get_produit_by_id_sans_filtre_acces() existe dans le modèle', true,
    strpos($modele, 'function get_produit_by_id_sans_filtre_acces(') !== false);
verifie('get_produit_by_id() reste la variante FILTRÉE (les écrans de saisie ne changent pas)', true,
    strpos($modele, 'return produits_appliquer_filtre_acces_champs(get_produit_by_id_sans_filtre_acces($id));') !== false);

foreach (['etiquette-piece-image.php', 'etiquette-piece-pdf.php', 'etiquette-piece-voir.php'] as $page) {
    $src = code_sans_commentaires($RACINE . '/admin/produits/' . $page);
    verifie("$page : n'appelle plus get_produit_by_id(", false, strpos($src, 'get_produit_by_id(') !== false);
    verifie("$page : charge la pièce par get_produit_by_id_sans_filtre_acces(", true,
        strpos($src, '$produit = get_produit_by_id_sans_filtre_acces(') !== false);
}

/* La fiche pièce (ajuster-stock.php) garde SON filtre — prix, stock,
   fournisseur — mais son aperçu d'étiquette passe par etiquette-piece-image.php,
   donc par le chargement sans filtre : il est couvert sans la toucher. */
$fiche = file_get_contents($RACINE . '/admin/produits/ajuster-stock.php');
verifie("la fiche pièce rend son aperçu d'étiquette par etiquette-piece-image.php", true,
    strpos($fiche, 'src="etiquette-piece-image.php?id=') !== false);
verifie('la fiche pièce garde le chargement filtré par rôle', true,
    strpos($fiche, '$produit = get_produit_by_id($produit_id);') !== false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
