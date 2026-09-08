<?php
/**
 * TROIS DÉFAUTS DE L'ÉDITEUR PHOTO (08/09/2026, constat de la direction :
 * « les images ajoutées auparavant s'affichent même après avoir changé les
 * photos »).
 *
 *  1. La nouvelle photo arrivait EN DERNIER : l'ancienne restait principale
 *     (étiquette, page du QR, catalogue) tant qu'on ne cliquait pas
 *     « Principale » puis Enregistrer une seconde fois.
 *  2. Deux enregistrements pouvaient se croiser : un onglet ouvert AVANT un
 *     autre enregistrement remettait l'ancien ordre et EFFAÇAIT du disque les
 *     photos ajoutées entre-temps.
 *  3. L'aperçu du détourage était chargé avec une URL fixe (t=0) et gardé 24 h
 *     par le navigateur : on revoyait l'ANCIEN détourage.
 *
 * Les deux règles (composition de la galerie, empreinte) sont des fonctions
 * PURES d'includes/photo_editeur.php : prouvées ici sans base ni fichier. Le
 * reste est vérifié STRUCTURELLEMENT (le JS envoie l'empreinte, l'ajax refuse
 * en 409, l'aperçu est en no-cache, les URLs portent une clé qui change).
 * LECTURE SEULE : aucune donnée touchée.
 *
 * À jouer :  php tests/test_editeur_photo_nouvelles_photos.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/includes/photo_editeur.php';

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

echo "— 1. la galerie : les nouvelles photos passent en premier —\n";
verifie('une nouvelle photo devient la principale', ['n1.webp', 'a.webp', 'b.webp'],
    photo_editeur_composer_galerie(['a.webp', 'b.webp'], ['n1.webp'], null));
verifie('plusieurs nouvelles : devant, dans leur ordre d\'arrivée', ['n1.webp', 'n2.webp', 'a.webp'],
    photo_editeur_composer_galerie(['a.webp'], ['n1.webp', 'n2.webp'], null));
verifie('sans nouvelle photo, l\'ordre des gardées est conservé', ['b.webp', 'a.webp'],
    photo_editeur_composer_galerie(['b.webp', 'a.webp'], [], null));
verifie('sans gardée, la nouvelle est seule et principale', ['n1.webp'],
    photo_editeur_composer_galerie([], ['n1.webp'], null));
verifie('tout vide → galerie vide (l\'ajax refusera)', [],
    photo_editeur_composer_galerie([], [], null));

echo "— 1 bis. une principale désignée par l'utilisateur est respectée —\n";
verifie('principale choisie en tête, puis les gardées, puis les nouvelles', ['b.webp', 'a.webp', 'n1.webp'],
    photo_editeur_composer_galerie(['b.webp', 'a.webp'], ['n1.webp'], 'b.webp'));
verifie('la principale choisie est ramenée en tête si elle ne l\'était pas', ['b.webp', 'a.webp', 'n1.webp'],
    photo_editeur_composer_galerie(['a.webp', 'b.webp'], ['n1.webp'], 'b.webp'));
verifie('une principale choisie inconnue des gardées est ignorée (nouvelles devant)', ['n1.webp', 'a.webp'],
    photo_editeur_composer_galerie(['a.webp'], ['n1.webp'], 'z.webp'));
verifie('une principale choisie vide est ignorée', ['n1.webp', 'a.webp'],
    photo_editeur_composer_galerie(['a.webp'], ['n1.webp'], ''));

echo "— 1 ter. aucune perte, aucun doublon, aucun chemin vide —\n";
$gardees = ['p/1.webp', 'p/2.webp', 'p/3.webp'];
$nouvelles = ['p/4.webp', 'p/5.webp'];
foreach ([null, 'p/2.webp'] as $choix) {
    $f = photo_editeur_composer_galerie($gardees, $nouvelles, $choix);
    $attendu = array_merge($gardees, $nouvelles);
    sort($attendu);
    $obtenu = $f;
    sort($obtenu);
    verifie('aucun chemin perdu (' . ($choix === null ? 'sans choix' : 'avec choix') . ')', $attendu, $obtenu);
    verifie('aucun doublon (' . ($choix === null ? 'sans choix' : 'avec choix') . ')', count($f), count(array_unique($f)));
}
verifie('un chemin présent deux fois n\'entre qu\'une fois', ['n1.webp', 'a.webp', 'b.webp'],
    photo_editeur_composer_galerie(['a.webp', 'a.webp', 'b.webp'], ['n1.webp', 'n1.webp'], null));
verifie('une nouvelle qui existait déjà n\'est pas dédoublée', ['a.webp', 'n1.webp', 'b.webp'],
    photo_editeur_composer_galerie(['a.webp', 'b.webp'], ['a.webp', 'n1.webp'], null));
verifie('les chemins vides ou blancs sont ignorés, les autres nettoyés', ['n1.webp', 'a.webp'],
    photo_editeur_composer_galerie(['', ' a.webp ', '   '], ["\tn1.webp"], null));
verifie('la galerie est réindexée à partir de 0', [0, 1, 2],
    array_keys(photo_editeur_composer_galerie(['a.webp', 'b.webp'], ['n1.webp'], 'b.webp')));

echo "— 1 quater. lire le choix explicite dans l'ordre envoyé —\n";
verifie('ancienne principale restée en tête : pas de choix', null,
    photo_editeur_principale_choisie(['a.webp', 'b.webp'], 'a.webp'));
verifie('une autre gardée devant l\'ancienne : c\'est un choix', 'b.webp',
    photo_editeur_principale_choisie(['b.webp', 'a.webp'], 'a.webp'));
verifie('ancienne principale retirée : pas de choix (la nouvelle passera devant)', null,
    photo_editeur_principale_choisie(['b.webp'], 'a.webp'));
verifie('rien de gardé : pas de choix', null,
    photo_editeur_principale_choisie([], 'a.webp'));
verifie('pièce sans principale en base : pas de choix', null,
    photo_editeur_principale_choisie(['b.webp', 'a.webp'], null));
verifie('les antislashs de la base ne trompent pas la comparaison', null,
    photo_editeur_principale_choisie(['produits/x.webp', 'produits/y.webp'], 'produits\\x.webp'));
verifie('…et le choix est rendu normalisé', 'produits/y.webp',
    photo_editeur_principale_choisie(['produits\\y.webp', 'produits/x.webp'], 'produits/x.webp'));
verifie('…et ce chemin normalisé est reconnu par la composition', ['produits/y.webp', 'produits/x.webp', 'n1.webp'],
    photo_editeur_composer_galerie(['produits\\y.webp', 'produits/x.webp'], ['n1.webp'], 'produits/y.webp'));

/* LE CLIC « PRINCIPALE » SURVIT AU RETRAIT DE L'ANCIENNE (08/09, contre-lecture) :
   galerie [a,b,c] en base ; l'infographiste retire a, met c devant b, puis colle
   une nouvelle photo. Le repère n'est plus l'ancienne principale (partie) mais le
   PREMIER de la galerie en base encore gardé — ici b. */
$actuelles_base = ['a.webp', 'b.webp', 'c.webp'];
verifie("ancienne principale retirée MAIS ordre changé : c'est un choix", 'c.webp',
    photo_editeur_principale_choisie(['c.webp', 'b.webp'], 'a.webp', $actuelles_base));
verifie('…et la nouvelle photo ne passe plus devant ce choix', ['c.webp', 'b.webp', 'n1.webp'],
    photo_editeur_composer_galerie(['c.webp', 'b.webp'], ['n1.webp'],
        photo_editeur_principale_choisie(['c.webp', 'b.webp'], 'a.webp', $actuelles_base)));
verifie('ancienne principale retirée et ordre INCHANGÉ : pas de choix', null,
    photo_editeur_principale_choisie(['b.webp', 'c.webp'], 'a.webp', $actuelles_base));
verifie('…donc la nouvelle photo devient principale', ['n1.webp', 'b.webp', 'c.webp'],
    photo_editeur_composer_galerie(['b.webp', 'c.webp'], ['n1.webp'],
        photo_editeur_principale_choisie(['b.webp', 'c.webp'], 'a.webp', $actuelles_base)));
verifie("galerie en base vide : on retombe sur l'ancienne principale seule", 'b.webp',
    photo_editeur_principale_choisie(['b.webp', 'a.webp'], 'a.webp', []));

echo "— 2. l'empreinte de la galerie —\n";
$e1 = photo_editeur_empreinte('produits/a.webp', '["produits\/a.webp","produits\/b.webp"]');
verifie('32 caractères hexadécimaux', 1, preg_match('/^[0-9a-f]{32}$/', $e1));
verifie('déterministe (même ligne → même empreinte)', $e1,
    photo_editeur_empreinte('produits/a.webp', '["produits\/a.webp","produits\/b.webp"]'));
verifie('change quand la principale change', false,
    $e1 === photo_editeur_empreinte('produits/b.webp', '["produits\/a.webp","produits\/b.webp"]'));
verifie('change quand l\'ordre des images change', false,
    $e1 === photo_editeur_empreinte('produits/a.webp', '["produits\/b.webp","produits\/a.webp"]'));
verifie('change quand une image est ajoutée', false,
    $e1 === photo_editeur_empreinte('produits/a.webp', '["produits\/a.webp","produits\/b.webp","produits\/c.webp"]'));
verifie('NULL en base compte comme vide (même lecture des deux côtés)',
    photo_editeur_empreinte('', ''), photo_editeur_empreinte(null, null));
verifie('principale + images ne se confondent pas par simple concaténation', false,
    photo_editeur_empreinte('ab', 'c') === photo_editeur_empreinte('a', 'bc'));

echo "— 2 bis. l'écran emporte l'empreinte, le JS la renvoie, l'ajax refuse en 409 —\n";
$ecran = file_get_contents($RACINE . '/admin/produits/photo-editer.php');
verifie('photo-editer.php charge l\'include des calculs purs', true,
    strpos($ecran, "require_once __DIR__ . '/../../includes/photo_editeur.php';") !== false);
verifie('l\'empreinte est calculée sur les colonnes brutes de la ligne', true,
    strpos($ecran, "photo_editeur_empreinte(\$piece['image_principale'], \$piece['images'])") !== false);
verifie('…et posée sur l\'écran (data-empreinte)', true,
    strpos($ecran, 'data-empreinte="<?php echo htmlspecialchars($empreinte, ENT_QUOTES); ?>"') !== false);

$js = file_get_contents($RACINE . '/js/admin-photo-editer.js');
verifie('le JS lit l\'empreinte de l\'écran', true,
    strpos($js, "wrap.getAttribute('data-empreinte')") !== false);
verifie('le JS l\'envoie avec chaque enregistrement', true,
    strpos($js, "fd.append('empreinte', empreinte);") !== false);
verifie('le JS reprend la nouvelle empreinte renvoyée (second enregistrement possible)', true,
    strpos($js, 'empreinte = res.empreinte || empreinte;') !== false);
verifie('le JS affiche le message du serveur en cas de refus', true,
    strpos($js, "message((res && res.error) ? res.error : 'Échec de l’enregistrement.', false);") !== false);
verifie('sur un 409, le bouton reste fermé : il faut recharger', true,
    strpos($js, 'elSave.disabled = (rep.statut === 409);') !== false);

$ajax = file_get_contents($RACINE . '/admin/produits/ajax_photo_enregistrer.php');
verifie('l\'ajax charge l\'include des calculs purs', true,
    strpos($ajax, "require_once __DIR__ . '/../../includes/photo_editeur.php';") !== false);
verifie('l\'ajax recalcule l\'empreinte sur la ligne actuelle', true,
    strpos($ajax, "\$empreinte_base = photo_editeur_empreinte(\$piece['image_principale'], \$piece['images']);") !== false);
verifie('empreinte absente OU différente → refus', true,
    strpos($ajax, "if (\$empreinte_client === '' || !hash_equals(\$empreinte_base, \$empreinte_client))") !== false);
verifie('le refus est un 409', true, strpos($ajax, 'http_response_code(409);') !== false);
verifie('avec le message attendu', true,
    strpos($ajax, "'La galerie a été modifiée entre-temps (autre poste ou onglet) : rechargez la page.") !== false);
verifie("…qui dit aussi ce qu'il advient des images collées", true,
    strpos($ajax, "Les images collées non enregistrées seront à recoller.'") !== false);
$pos_refus = strpos($ajax, 'http_response_code(409);');
$pos_upload = strpos($ajax, 'upload_produit_images_multiples(');
$pos_collee = strpos($ajax, 'image_optimizer_process_tmp(');
$pos_update = strpos($ajax, 'UPDATE produits SET image_principale');
$pos_menage = strpos($ajax, 'image_optimizer_delete_with_variants(');
verifie('le refus tombe AVANT tout téléversement', true, $pos_refus !== false && $pos_upload !== false && $pos_refus < $pos_upload);
verifie('…AVANT le rangement d\'une image collée', true, $pos_collee !== false && $pos_refus < $pos_collee);
verifie('…AVANT l\'écriture en base', true, $pos_update !== false && $pos_refus < $pos_update);
verifie('…AVANT le ménage disque', true, $pos_menage !== false && $pos_refus < $pos_menage);
verifie('la galerie finale passe par la fonction pure', true,
    strpos($ajax, 'photo_editeur_composer_galerie(') !== false);
verifie('le choix explicite est lu dans l\'ordre envoyé, contre la principale en base', true,
    strpos($ajax, "photo_editeur_principale_choisie(\$gardees, \$piece['image_principale'], \$actuelles)") !== false);
verifie('plus de « gardées puis nouvelles » en dur', false,
    strpos($ajax, 'array_merge($gardees, $nouvelles)') !== false);
verifie('la réponse renvoie la nouvelle empreinte', true,
    strpos($ajax, "'empreinte' => \$empreinte_neuve") !== false);
verifie('…relue en base après l\'écriture', true,
    strpos($ajax, 'SELECT images, image_principale FROM produits WHERE id = :id') !== false);

echo "— 3. l'aperçu du détourage ne reste plus 24 h dans le navigateur —\n";
$apercu = file_get_contents($RACINE . '/admin/produits/detourage-lot-apercu.php');
verifie('l\'aperçu répond en no-cache', true,
    strpos($apercu, "header('Cache-Control: private, no-cache');") !== false);
verifie('plus d\'en-tête max-age (le commentaire peut le citer, pas le header)', false,
    strpos($apercu, "header('Cache-Control: private, max-age") !== false);
verifie('photo-editer.php : la clé t = date du fichier de la photo principale', true,
    strpos($ecran, "\$t_photo = (int) filemtime(__DIR__ . '/../../upload/' . ltrim(\$chemin_principale, '/'));") !== false);
verifie('…portée par l\'URL de l\'aperçu (plus de t=0)', true,
    strpos($ecran, "src=\"detourage-lot-apercu.php?id=<?php echo (int) \$piece['id']; ?>&t=<?php echo (int) \$t_photo; ?>\"") !== false);
verifie('…et l\'ancienne URL fixe a disparu', false,
    strpos($ecran, "detourage-lot-apercu.php?id=<?php echo (int) \$piece['id']; ?>&t=0\"") !== false);
$lot = file_get_contents($RACINE . '/admin/produits/detourage-lot.php');
verifie('detourage-lot.php : l\'URL de la planche porte une clé t (début du lot)', true,
    strpos($lot, "img.src = 'detourage-lot-apercu.php?id=' + id + '&t=' + (etat.demarre || 0);") !== false);
verifie('…et plus d\'URL sans clé', false,
    strpos($lot, "img.src = 'detourage-lot-apercu.php?id=' + id;") !== false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
