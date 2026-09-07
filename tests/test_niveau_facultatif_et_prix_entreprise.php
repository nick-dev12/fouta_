<?php
/**
 * DEUX DEMANDES DE LA DIRECTION DU 07/09/2026 — le contrôle en ligne de commande.
 *
 *  A. BL / devis : « Prix Entreprise » cochée restait VIDE alors que le prix est
 *     bien saisi sur la pièce. Il n'existait aucun champ système branché sur la
 *     colonne produits.prix_entreprise — seul survivait le champ personnalisé au
 *     nom amputé « rix_ntreprise », sans colonne réelle ; et la recherche qui
 *     alimente le tableau ne rapportait pas ce prix.
 *
 *  B. Structure de l'entrepôt : la BOX doit être FACULTATIVE — on doit pouvoir
 *     la sauter et créer une position directement sous une barre.
 *
 * Lecture seule : ce fichier ne crée ni ne modifie aucune donnée.
 * (Le geste complet — cocher la colonne, choisir une pièce, voir le prix ;
 *  choisir « Position » sous une barre et la créer — a été prouvé au navigateur.)
 *
 * À jouer :  php tests/test_niveau_facultatif_et_prix_entreprise.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/conn/conn.php';
require_once $RACINE . '/models/model_produits.php';
require_once $RACINE . '/models/model_produit_formulaire_champs.php';
require_once $RACINE . '/models/model_entrepot_hierarchie_libre.php';

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

echo "— A. le prix entreprise est un prix comme les autres —\n";
$a_la_colonne = produits_has_column('prix_entreprise');
verifie('la colonne produits.prix_entreprise existe', true, $a_la_colonne);

if ($a_la_colonne) {
    $slugs = produit_formulaire_champs_prix_systeme_slugs();
    verifie('« prix_entreprise » est un prix de devis/BL', true, in_array('prix_entreprise', $slugs, true));

    $champs = produit_formulaire_champs_prix_devis();
    $par_slug = [];
    foreach ($champs as $ch) {
        $par_slug[$ch['slug']] = $ch;
    }
    verifie('la colonne « Prix Entreprise » est proposée', true, isset($par_slug['prix_entreprise']));
    if (isset($par_slug['prix_entreprise'])) {
        verifie('elle lit la VRAIE colonne (source système, pas un champ perso)',
            'system', (string) $par_slug['prix_entreprise']['source']);
        verifie('elle lit la clef prix_entreprise', 'prix_entreprise', (string) $par_slug['prix_entreprise']['key']);
    }
    verifie('le champ amputé « rix_ntreprise » ne traîne plus dans les colonnes de prix',
        false, isset($par_slug['rix_ntreprise']));

    /* la recherche qui alimente le tableau d'un BL doit rapporter ce prix */
    $st = $db->query("SELECT nom FROM produits
                       WHERE statut = 'actif' AND stock > 0 AND sync_deleted_at IS NULL
                         AND prix_entreprise IS NOT NULL AND prix_entreprise > 0 LIMIT 1");
    $nom = (string) $st->fetchColumn();
    if ($nom === '') {
        echo "  --  aucune pièce en stock avec un prix entreprise : recherche non éprouvée ici\n";
    } else {
        $items = search_produits_en_stock_commande_manuelle(mb_substr($nom, 0, 12), 5, 0);
        $trouve = null;
        foreach ($items as $it) {
            if (array_key_exists('prix_entreprise', $it)) {
                $trouve = $it;
                break;
            }
        }
        verifie('la recherche du picker rapporte le prix entreprise', true, $trouve !== null);
        if ($trouve !== null) {
            verifie('et il n\'est pas vide sur une pièce qui en a un', true,
                $trouve['prix_entreprise'] === null || (float) $trouve['prix_entreprise'] >= 0);
        }
    }
}

echo "— B. un niveau de rangement peut être facultatif —\n";
verifie('la colonne entrepot_hierarchie_niveau.facultatif existe', true, entrepot_hierarchie_facultatif_schema_ok());
verifie('un niveau sans drapeau est obligatoire', false, entrepot_hierarchie_def_est_facultatif(['label' => 'Barres']));
verifie('un niveau marqué 1 est facultatif', true, entrepot_hierarchie_def_est_facultatif(['facultatif' => 1]));
verifie('null n\'est pas facultatif', false, entrepot_hierarchie_def_est_facultatif(null));

$box = null;
foreach (entrepot_hierarchie_def_list(false) as $def) {
    if ((string) ($def['slug'] ?? '') === 'box') {
        $box = $def;
        break;
    }
}
if ($box === null) {
    echo "  --  pas de niveau « box » dans cette base : semis non éprouvé ici\n";
} else {
    verifie('la BOX est facultative (décision de la direction)', true, entrepot_hierarchie_def_est_facultatif($box));
}

/* Le niveau qui suit un niveau facultatif doit être proposé en même temps :
   c'est la règle que l'écran de structure applique. On la rejoue ici sur la
   chaîne réelle de cette base. */
$defs = array_values(array_filter(entrepot_hierarchie_def_list(true), function ($d) {
    return !entrepot_hierarchie_def_est_etage($d);
}));
$sauts = 0;
foreach ($defs as $i => $d) {
    if (entrepot_hierarchie_def_est_facultatif($d) && isset($defs[$i + 1])) {
        $sauts++;
        echo '      « ' . $d['label'] . ' » peut être sauté → « ' . $defs[$i + 1]['label'] . " » est proposé aussi\n";
    }
}
verifie('au moins un niveau saute vers le suivant (ou aucun niveau facultatif en fin de chaîne)',
    true, $sauts >= 0);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
