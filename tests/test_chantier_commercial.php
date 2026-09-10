<?php
/**
 * LE CHANTIER DU COMMERCIAL GÉNÉRAL (10/09/2026) — contrôles en ligne de commande.
 *
 * Ce fichier grandit à chaque point du chantier décrit dans le rapport
 * « Chaîne commerciale Fouta ». Chaque section dit ce que la direction a
 * demandé et vérifie la règle, pas seulement la présence du code.
 *
 * À jouer :  php tests/test_chantier_commercial.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/includes/admin_route_access.php';
require_once $RACINE . '/includes/admin_permissions.php';

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

/** Le texte d'un bloc « if ($action === 'x') » jusqu'au bloc suivant. */
function bloc_action($source, $action) {
    $debut = strpos($source, "if (\$action === '$action')");
    if ($debut === false) {
        return '';
    }
    $suite = strpos($source, "if (\$action === '", $debut + 10);
    return $suite === false ? substr($source, $debut) : substr($source, $debut, $suite - $debut);
}

echo "— historique des mouvements et rapport journalier (demande de la direction) —\n";
foreach (['stock/mouvements.php', 'produits/rapport-jour.php'] as $p) {
    verifie("le fichier existe : $p", true, is_file("$RACINE/admin/$p"));
    verifie("ouvert au commercial général : $p", true, admin_route_is_allowed('commercial_general', $p));
    verifie("encore fermé au commercial simple : $p", false, admin_route_is_allowed('commercial', $p));
}
foreach (['produits/ajuster-stock.php', 'produits/entree.php'] as $p) {
    verifie("toujours fermé au commercial général : $p", false, admin_route_is_allowed('commercial_general', $p));
}
$nav = file_get_contents("$RACINE/admin/includes/nav.php");
verifie("le menu réserve les deux entrées au commercial général", true, strpos($nav, "<?php if (\$admin_role === 'commercial_general'): ?>") !== false);
$mouv = file_get_contents("$RACINE/admin/stock/mouvements.php");
verifie("l'historique ne mène pas un commercial vers la fiche stock", true, strpos($mouv, '$mouvements_fiche_fermee') !== false);
$rapport = file_get_contents("$RACINE/admin/produits/rapport-jour.php");
verifie("le Retour du rapport mène un commercial à son accueil", true, strpos($rapport, "'../commercial/index.php'") !== false);
verifie("le rapport d'un vendeur compte les sorties de ses tickets", true, strpos($rapport, "m.reference_type = 'caisse_vente'") !== false);

echo "— point 1 : encaisser est le métier du caissier —\n";
$encaissement_direct = [
    'commercial_general' => false,
    'commercial' => false,
    'caissier' => false,
    'informaticien' => true,
    'developpeur' => true,
];
foreach ($encaissement_direct as $role => $attendu) {
    $_SESSION['admin_role'] = $role;
    verifie("encaissement direct pour « $role »", $attendu, admin_can_caisse_vendeur() && admin_can_encaisser_ticket());
}
$_SESSION['admin_role'] = 'caissier';
verifie('le caissier encaisse toujours les tickets préparés', true, admin_can_encaisser_ticket());
$_SESSION['admin_role'] = 'commercial_general';
verifie('le commercial général prépare toujours ses tickets', true, admin_can_caisse_vendeur());

$api = file_get_contents("$RACINE/admin/caisse/api.php");
$post = file_get_contents("$RACINE/admin/caisse/post.php");
foreach (['admin/caisse/api.php' => $api, 'admin/caisse/post.php' => $post] as $f => $source) {
    $bloc = bloc_action($source, 'encaisser');
    verifie("$f : le bloc « encaisser » existe", true, $bloc !== '');
    verifie("$f : il exige le droit d'encaisser", true, strpos($bloc, 'admin_can_encaisser_ticket()') !== false);
}
verifie("générer un ticket reste ouvert au vendeur", false, strpos(bloc_action($api, 'generer_ticket'), 'admin_can_encaisser_ticket()') !== false);
verifie("finaliser un ticket reste au caissier", true, strpos(bloc_action($post, 'finaliser_ticket'), 'admin_can_encaisser_ticket()') !== false);

echo "— point 2 : une facture émise fige son devis —\n";
require_once "$RACINE/models/model_devis.php";
foreach (['models/model_devis.php', 'admin/devis/modifier.php', 'admin/devis/update.php', 'admin/devis/devis_par_client.php'] as $f) {
    verifie("$f applique la règle", true, strpos(file_get_contents("$RACINE/$f"), 'devis_est_facture(') !== false);
}
$devis_facture = (int) $db->query('SELECT devis_id FROM factures_devis ORDER BY id LIMIT 1')->fetchColumn();
$devis_libre = (int) $db->query('SELECT d.id FROM devis d WHERE NOT EXISTS (SELECT 1 FROM factures_devis f WHERE f.devis_id = d.id) ORDER BY d.id LIMIT 1')->fetchColumn();
if ($devis_facture > 0) {
    verifie("le devis #$devis_facture, facturé, est figé", true, devis_est_facture($devis_facture));
}
if ($devis_libre > 0) {
    verifie("le devis #$devis_libre, sans facture, reste modifiable", false, devis_est_facture($devis_libre));
}

echo "— points 3 et 4 : les écrans passent par les nouvelles règles —\n";
verifie('valider depuis l’écran sort le stock', true, strpos(file_get_contents("$RACINE/admin/devis/bl_statut.php"), 'bl_valider_et_sortir_stock(') !== false);
verifie('un bon créé « validé » sans stock le dit', true, strpos(file_get_contents("$RACINE/admin/devis/bl_enregistrer.php"), "reste_brouillon") !== false);
verifie('le bon de retour dit s’il est rentré en stock', true, strpos(file_get_contents("$RACINE/admin/devis/br_enregistrer.php"), "sans_entree_stock") !== false);
verifie('la facture du mois affiche ses retours', true, strpos(file_get_contents("$RACINE/includes/facture_mensuelle_content.php"), '$fm_retours') !== false);

echo "— points 3 et 4 : essai réel en base locale, nettoyé à la fin —\n";
require_once "$RACINE/models/model_bl.php";
require_once "$RACINE/models/model_bons_retour.php";
require_once "$RACINE/models/model_factures_mensuelles.php";
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$hote = (string) $db->getAttribute(PDO::ATTR_CONNECTION_STATUS);
if ($base !== 'jomas_fouta3' || (stripos($hote, 'localhost') === false && stripos($hote, '127.0.0.1') === false)) {
    echo "  --  essai réel ignoré : il ne tourne que sur la base locale jomas_fouta3 ($base, $hote)\n";
} else {
    $piece = $db->query("SELECT id, stock, date_modification, sync_updated_at FROM produits WHERE statut = 'actif' AND stock >= 5 AND sync_deleted_at IS NULL ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $client = (int) $db->query('SELECT id FROM clients_b2b ORDER BY id LIMIT 1')->fetchColumn();
    $avant = (int) $piece['stock'];
    $bl_ids = [];
    $fm_id = 0;
    $cree_bl = function ($statut, $qte) use ($db, $piece, $client, &$bl_ids) {
        $db->prepare("INSERT INTO bons_livraison (numero_bl, client_b2b_id, devis_id, admin_createur_id, statut, date_bl, total_ht, notes, date_creation)
                      VALUES (:n, :c, NULL, 38, :s, CURDATE(), :t, 'ESSAI AUTOMATIQUE test_chantier_commercial', NOW())")
           ->execute(['n' => 'ESSAI-' . substr(uniqid(), -8), 'c' => $client, 's' => $statut, 't' => 1000 * $qte]);
        $id = (int) $db->lastInsertId();
        $bl_ids[] = $id;
        $db->prepare("INSERT INTO bl_lignes (bl_id, produit_id, designation, quantite, prix_unitaire_ht, total_ligne_ht, ordre)
                      VALUES (:b, :p, 'Pièce d’essai', :q, 1000, :t, 1)")
           ->execute(['b' => $id, 'p' => (int) $piece['id'], 'q' => $qte, 't' => 1000 * $qte]);
        return $id;
    };
    $stock = function () use ($db, $piece) {
        return (int) $db->query('SELECT stock FROM produits WHERE id = ' . (int) $piece['id'])->fetchColumn();
    };
    $valeur = function ($sql) use ($db) {
        return $db->query($sql)->fetchColumn();
    };
    try {
        // Valider un bon sort le stock et l'écrit au journal
        $bl1 = $cree_bl('brouillon', 2);
        $r = bl_valider_et_sortir_stock($bl1, 38);
        verifie('valider un bon : accepté', true, $r['success']);
        verifie('valider un bon : le stock baisse de 2', $avant - 2, $stock());
        verifie('valider un bon : le bon est validé', 'valide', (string) $valeur("SELECT statut FROM bons_livraison WHERE id = $bl1"));
        verifie('valider un bon : la sortie est au journal', 1, (int) $valeur("SELECT COUNT(*) FROM stock_mouvements WHERE reference_type = 'bon_livraison' AND reference_id = $bl1 AND type = 'sortie' AND quantite = 2"));
        verifie('le bon sait qu’il a sorti son stock', true, bl_a_sorti_stock($bl1));

        // Valider deux fois ne sort pas deux fois
        $r = bl_valider_et_sortir_stock($bl1, 38);
        verifie('valider deux fois : refusé', false, $r['success']);
        verifie('valider deux fois : stock inchangé', $avant - 2, $stock());

        // Stock insuffisant : rien n'est écrit
        $bl2 = $cree_bl('brouillon', $avant + 1000);
        $r = bl_valider_et_sortir_stock($bl2, 38);
        verifie('stock insuffisant : refusé', false, $r['success']);
        verifie('stock insuffisant : le message nomme le manque', 0, strpos($r['message'], 'Stock insuffisant'));
        verifie('stock insuffisant : stock inchangé', $avant - 2, $stock());
        verifie('stock insuffisant : le bon reste brouillon', 'brouillon', (string) $valeur("SELECT statut FROM bons_livraison WHERE id = $bl2"));
        verifie('stock insuffisant : aucune sortie au journal', 0, (int) $valeur("SELECT COUNT(*) FROM stock_mouvements WHERE reference_type = 'bon_livraison' AND reference_id = $bl2"));

        // Pas de bon de retour sur un brouillon
        $ligne2 = (int) $valeur("SELECT id FROM bl_lignes WHERE bl_id = $bl2");
        $r = br_create_bon_retour($bl2, 38, 'essai', [$ligne2 => 1]);
        verifie('bon de retour sur un brouillon : refusé', false, $r['success']);

        // La facture du mois en brouillon suit le retour, qui rentre en stock
        $db->prepare("INSERT INTO factures_mensuelles (numero_facture, client_b2b_id, annee, mois, statut, total_ht, admin_createur_id, date_creation)
                      VALUES (:n, :c, 2099, 12, 'brouillon', 0, 38, NOW())")
           ->execute(['n' => 'FM-ESSAI-' . substr(uniqid(), -6), 'c' => $client]);
        $fm_id = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO facture_mensuelle_bl (facture_mensuelle_id, bl_id) VALUES (:f, :b)')->execute(['f' => $fm_id, 'b' => $bl1]);
        recalc_total_facture_mensuelle($fm_id);
        verifie('facture du mois : total des bons', 2000.0, (float) $valeur("SELECT total_ht FROM factures_mensuelles WHERE id = $fm_id"));
        $ligne1 = (int) $valeur("SELECT id FROM bl_lignes WHERE bl_id = $bl1");
        $r = br_create_bon_retour($bl1, 38, 'essai', [$ligne1 => 1]);
        verifie('retour sur un bon validé selon la nouvelle règle : accepté', true, $r['success']);
        verifie('ce retour rentre en stock', $avant - 1, $stock());
        verifie('ce retour n’est pas signalé « sans entrée en stock »', false, !empty($r['sans_entree_stock']));
        verifie('facture du mois en brouillon : retour déduit tout seul', 1000.0, (float) $valeur("SELECT total_ht FROM factures_mensuelles WHERE id = $fm_id"));
        $m = facture_mensuelle_montants($fm_id);
        verifie('montants : bons 2000, retours 1000, net 1000', [2000.0, 1000.0, 1000.0], [$m['bl'], $m['retours'], $m['net']]);
        verifie('le retour est listé sur la facture', 1, count(facture_mensuelle_retours($fm_id)));

        // Une facture payée n'est jamais recalculée
        $db->exec("UPDATE factures_mensuelles SET statut = 'payee', total_ht = 2000 WHERE id = $fm_id");
        recalc_total_facture_mensuelle($fm_id);
        verifie('facture payée : montant conservé, l’écart relève d’un avoir', 2000.0, (float) $valeur("SELECT total_ht FROM factures_mensuelles WHERE id = $fm_id"));

        // Un bon validé avant la règle : son retour ne fait rien rentrer
        $bl3 = $cree_bl('valide', 1);
        $ligne3 = (int) $valeur("SELECT id FROM bl_lignes WHERE bl_id = $bl3");
        $stock_avant_retour = $stock();
        $r = br_create_bon_retour($bl3, 38, 'essai', [$ligne3 => 1]);
        verifie('retour sur un ancien bon : accepté', true, $r['success']);
        verifie('retour sur un ancien bon : rien ne rentre en stock', $stock_avant_retour, $stock());
        verifie('retour sur un ancien bon : l’écran le dira', true, !empty($r['sans_entree_stock']));
    } finally {
        // Nettoyage complet, même si une vérification a échoué.
        $liste = implode(',', array_map('intval', $bl_ids ?: [0]));
        $brs = $db->query("SELECT id FROM bons_retour WHERE bl_id IN ($liste)")->fetchAll(PDO::FETCH_COLUMN);
        $liste_br = implode(',', array_map('intval', $brs ?: [0]));
        $db->exec("DELETE FROM stock_mouvements WHERE (reference_type = 'bon_livraison' AND reference_id IN ($liste)) OR (reference_type = 'bon_retour' AND reference_id IN ($liste_br))");
        $db->exec("DELETE FROM bons_retour_lignes WHERE bon_retour_id IN ($liste_br)");
        $db->exec("DELETE FROM bons_retour WHERE id IN ($liste_br)");
        if ($fm_id > 0) {
            $db->exec("DELETE FROM facture_mensuelle_bl WHERE facture_mensuelle_id = $fm_id");
            $db->exec("DELETE FROM factures_mensuelles WHERE id = $fm_id");
        }
        $db->exec("DELETE FROM bl_lignes WHERE bl_id IN ($liste)");
        $db->exec("DELETE FROM bons_livraison WHERE id IN ($liste)");
        $db->exec('SET @sync_applying = 1');
        $db->prepare('UPDATE produits SET stock = :s, date_modification = :d, sync_updated_at = :u WHERE id = :id')
           ->execute(['s' => $avant, 'd' => $piece['date_modification'], 'u' => $piece['sync_updated_at'], 'id' => (int) $piece['id']]);
        $db->exec('SET @sync_applying = NULL');
        verifie('nettoyage : stock de la pièce rétabli', $avant, $stock());
        verifie('nettoyage : aucun bon d’essai restant', 0, (int) $valeur("SELECT COUNT(*) FROM bons_livraison WHERE numero_bl LIKE 'ESSAI-%'"));
        verifie('nettoyage : aucun mouvement d’essai restant', 0, (int) $valeur("SELECT COUNT(*) FROM stock_mouvements WHERE (reference_type = 'bon_livraison' AND reference_id IN ($liste)) OR (reference_type = 'bon_retour' AND reference_id IN ($liste_br))"));
    }
}

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
