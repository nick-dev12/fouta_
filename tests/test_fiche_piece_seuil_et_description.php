<?php
/**
 * DEUX DÉFAUTS DE LA FICHE PIÈCE (07/09/2026, constats de la direction).
 *
 *  A. « Après avoir saisi le SEUIL D'ALERTE et enregistré, quand on revient le
 *     seuil est vide. » — la fiche l'affichait, le contrôleur le préparait,
 *     mais update_produit() n'écrivait JAMAIS la colonne : le mot « seuil »
 *     n'apparaissait pas une seule fois dans models/model_produits.php.
 *
 *  B. « Une fois la description générée, même si on change un champ — la réf.
 *     OEM par exemple — la génération affiche les anciennes données. » — une
 *     description reprise de la base restait collée quoi qu'on change ensuite,
 *     et l'aperçu gardait l'ancien texte pendant toute la recherche.
 *
 * Ces vérifications sont STRUCTURELLES et en LECTURE SEULE : elles ne touchent
 * aucune donnée (impensable sur le serveur de l'entreprise ou en production).
 * Le geste lui-même a été prouvé au navigateur : seuil NULL → 5 en base et
 * réaffiché au retour ; aperçu qui suit la frappe dès 300 ms.
 *
 * À jouer :  php tests/test_fiche_piece_seuil_et_description.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/conn/conn.php';
require_once $RACINE . '/models/model_produits.php';

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

echo "— A. le seuil d'alerte part vraiment en base —\n";
$maj = corps_fonction('update_produit');
verifie("update_produit() écrit la colonne seuil_alerte", true, strpos($maj, 'seuil_alerte = :seuil_alerte') !== false);
verifie("update_produit() n'écrit le seuil que si le formulaire l'a envoyé", true,
    strpos($maj, "array_key_exists('seuil_alerte', \$data)") !== false);
verifie("update_produit() écrit aussi la source du seuil quand la colonne existe", true,
    strpos($maj, 'seuil_alerte_source = :seuil_alerte_source') !== false);
verifie('un seuil négatif est ramené à 0', true, strpos($maj, "max(0, (int) \$sa)") !== false);

$ctrl = file_get_contents($RACINE . '/controllers/controller_produits.php');
verifie('le contrôleur prépare toujours le seuil à la modification', true,
    strpos($ctrl, "\$data['seuil_alerte'] = ") !== false);

verifie('la colonne produits.seuil_alerte existe', true, produits_has_column('seuil_alerte'));

echo "— B. la description générée suit les champs —\n";
foreach (['admin/produits/modifier.php', 'admin/produits/ajouter.php'] as $ecran) {
    $src = file_get_contents($RACINE . '/' . $ecran);
    $nom = basename($ecran);
    verifie("$nom : la reprise est mémorisée avec SA référence", true, strpos($src, 'foundPour') !== false);
    verifie("$nom : la reprise n'est réaffichée que si la référence n'a pas bougé", true,
        (bool) preg_match('/if \(foundDescription && foundPour === refsCourantes(Desc)?\(\)\)/', $src));
    verifie("$nom : l'aperçu se recompose dès la frappe, sans attendre la recherche", true,
        (bool) preg_match('/clearTimeout\(timer\);\s*\/\*[^*]*\*\/\s*foundDescription = null;/s', $src));
    verifie("$nom : une réponse arrivée en retard est ignorée", true,
        (bool) preg_match('/if \(cle !== refsCourantes(Desc)?\(\)\)/', $src));
}

echo "— B bis. la recherche de description ne se répond plus à elle-même —\n";
$point = file_get_contents($RACINE . '/admin/produits/ajax_description_auto.php');
verifie('la pièce ouverte est exclue de la recherche (branche OEM)', true,
    strpos($point, 'WHERE reference_oem = :v AND id <> :ex') !== false);
verifie('la pièce ouverte est exclue du repli fournisseur', true,
    strpos($point, 'WHERE reference_fournisseur = :v AND id <> :ex') !== false);
verifie('une référence OEM saisie est souveraine : plus de repli sur le fournisseur', true,
    strpos($point, "\$description === null && \$oem === '' && \$ref !== ''") !== false);

$mod = file_get_contents($RACINE . '/admin/produits/modifier.php');
verifie("l'écran de modification passe l'id de la pièce à la recherche", true,
    strpos($mod, "&id=<?php echo (int) \$produit_id; ?>") !== false);
verifie('la description est recomposée avant l\'envoi du formulaire', true,
    strpos($mod, "formulaire.addEventListener('submit'") !== false);
verifie('le seuil ne dépend plus de la permission du statut', true,
    strpos($mod, "if (\$voit('statut') || \$voit_seuil)") !== false);
verifie('la source du seuil est bornée à son énumération', true,
    strpos(corps_fonction('update_produit'), "in_array(\$sas, ['manuel', 'suggestion'], true)") !== false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
