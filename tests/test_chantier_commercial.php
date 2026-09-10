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

echo "— point 5 : une vente, une seule facture —\n";
require_once "$RACINE/models/model_factures_devis.php";
verifie('facturer un devis parti en BL : la page refuse', true, strpos(file_get_contents("$RACINE/admin/devis/generer_facture.php"), 'bl_exists_for_devis(') !== false);
verifie('un bon regroupé au mois ne se présente plus comme une facture', true, strpos(file_get_contents("$RACINE/admin/devis/bl_facture.php"), '$bl_fm_numero === null') !== false);
$base_locale = ((string) $db->query('SELECT DATABASE()')->fetchColumn()) === 'jomas_fouta3'
    && (stripos((string) $db->getAttribute(PDO::ATTR_CONNECTION_STATUS), 'localhost') !== false
        || stripos((string) $db->getAttribute(PDO::ATTR_CONNECTION_STATUS), '127.0.0.1') !== false);
if ($base_locale) {
    // Un devis facturé ne part pas en bon de livraison
    $devis_facture = (int) $db->query("SELECT devis_id FROM factures_devis ORDER BY id LIMIT 1")->fetchColumn();
    $bl_avant = (int) $db->query("SELECT COUNT(*) FROM bons_livraison WHERE devis_id = $devis_facture")->fetchColumn();
    $r = create_bl_from_devis($devis_facture, 38);
    $bl_crees = (int) $db->query("SELECT COUNT(*) FROM bons_livraison WHERE devis_id = $devis_facture")->fetchColumn() - $bl_avant;
    if ($bl_crees > 0) {
        $db->exec("DELETE l FROM bl_lignes l INNER JOIN bons_livraison b ON b.id = l.bl_id WHERE b.devis_id = $devis_facture");
        $db->exec("DELETE FROM bons_livraison WHERE devis_id = $devis_facture");
    }
    verifie('convertir un devis facturé en BL : refusé', false, !empty($r['success']));
    verifie('le refus dit pourquoi', true, strpos((string) ($r['message'] ?? ''), 'déjà facturé') !== false);
    verifie('aucun bon de livraison créé', 0, $bl_crees);

    // Un devis parti en bon de livraison ne se facture pas seul
    $devis_libre = (int) $db->query('SELECT d.id FROM devis d WHERE NOT EXISTS (SELECT 1 FROM factures_devis f WHERE f.devis_id = d.id) ORDER BY d.id LIMIT 1')->fetchColumn();
    $max_facture = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM factures_devis')->fetchColumn();
    $client_essai = (int) $db->query('SELECT id FROM clients_b2b ORDER BY id LIMIT 1')->fetchColumn();
    $db->prepare("INSERT INTO bons_livraison (numero_bl, client_b2b_id, devis_id, admin_createur_id, statut, date_bl, total_ht, notes, date_creation)
                  VALUES (:n, :c, :d, 38, 'brouillon', CURDATE(), 0, 'ESSAI AUTOMATIQUE test_chantier_commercial', NOW())")
       ->execute(['n' => 'ESSAI-' . substr(uniqid(), -8), 'c' => $client_essai, 'd' => $devis_libre]);
    $bl_essai = (int) $db->lastInsertId();
    try {
        $r = create_facture_devis($devis_libre, 38);
        verifie('facturer seul un devis parti en BL : refusé', true, $r === false);
    } finally {
        $db->exec("DELETE FROM factures_devis WHERE id > $max_facture AND devis_id = $devis_libre");
        $db->exec("DELETE FROM bons_livraison WHERE id = $bl_essai");
    }
    verifie('aucune facture créée', 0, (int) $db->query("SELECT COUNT(*) FROM factures_devis WHERE id > $max_facture")->fetchColumn());

    // Un bon regroupé dans une facture mensuelle ne se paie pas seul
    $bl_regroupe = (int) $db->query('SELECT f.bl_id FROM facture_mensuelle_bl f INNER JOIN bons_livraison b ON b.id = f.bl_id WHERE COALESCE(b.facture_bl_payee, 0) = 0 ORDER BY f.bl_id LIMIT 1')->fetchColumn();
    if ($bl_regroupe > 0) {
        $r = marquer_bl_facture_payee($bl_regroupe);
        if (!empty($r['ok'])) {
            $db->exec("UPDATE bons_livraison SET facture_bl_payee = 0, date_paiement_bl = NULL WHERE id = $bl_regroupe");
        }
        verifie("payer seul le bon #$bl_regroupe, regroupé au mois : refusé", false, !empty($r['ok']));
        verifie('le refus nomme la facture mensuelle', true, strpos((string) ($r['error'] ?? ''), 'facture mensuelle FM') !== false);
    }
}

echo "— point 7 : annuler un ticket en attente —\n";
require_once "$RACINE/models/model_caisse.php";
require_once "$RACINE/models/model_commercial_accueil.php";
$enum_statut = (string) $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caisse_ventes' AND COLUMN_NAME = 'statut'")->fetchColumn();
verifie('la base connaît le statut « annule »', true, strpos($enum_statut, "'annule'") !== false);
verifie('un ticket annulé ne passe jamais pour payé', 'annule', caisse_vente_statut(['statut' => 'annule']));
verifie('un ticket en attente reste en attente', 'en_attente', caisse_vente_statut(['statut' => 'en_attente']));
if ($base_locale) {
    $cree_ticket = function ($vendeur) use ($db) {
        $db->prepare("INSERT INTO caisse_ventes (admin_id, numero_ticket, reference, montant_total, montant_ht, montant_tva, tva_incluse, remise_globale_pct, mode_paiement, statut, date_vente)
                      VALUES (:a, :n, NULL, 1000, 1000, 0, 0, 0, 'especes', 'en_attente', NOW())")
           ->execute(['a' => $vendeur, 'n' => 'ESSAI-' . substr(uniqid(), -10)]);
        return (int) $db->lastInsertId();
    };
    $ticket = $cree_ticket(38);
    $ticket2 = $cree_ticket(38);
    $role_avant = $_SESSION['admin_role'] ?? null;
    try {
        $_SESSION['admin_role'] = 'commercial_general';
        $r = caisse_annuler_ticket($ticket, 20, 'Essai automatique : pas son ticket');
        verifie('un autre vendeur ne peut pas annuler ce ticket', false, $r['ok']);
        $r = caisse_annuler_ticket($ticket, 38, 'ok');
        verifie('le motif est obligatoire', false, $r['ok']);
        $r = caisse_annuler_ticket($ticket, 38, 'Essai automatique : doublon');
        verifie('le vendeur annule son propre ticket', true, $r['ok']);
        $ligne = $db->query("SELECT statut, reference, annule_par, motif_annulation, date_annulation FROM caisse_ventes WHERE id = $ticket")->fetch(PDO::FETCH_ASSOC);
        verifie('le ticket est annulé', 'annule', $ligne['statut']);
        verifie('l’auteur et le motif sont gardés', [38, 'Essai automatique : doublon'], [(int) $ligne['annule_par'], $ligne['motif_annulation']]);
        verifie('la date d’annulation est posée', true, !empty($ligne['date_annulation']));
        $r = caisse_annuler_ticket($ticket, 38, 'Essai automatique : deuxième fois');
        verifie('annuler deux fois : refusé', false, $r['ok']);
        $restants = array_filter(commercial_tickets_en_attente(38), function ($t) use ($ticket) {
            return (int) $t['id'] === $ticket;
        });
        verifie('le ticket annulé quitte les tickets en attente du vendeur', 0, count($restants));

        $_SESSION['admin_role'] = 'caissier';
        $r = caisse_annuler_ticket($ticket2, 33, 'Essai automatique : client parti');
        verifie('le caissier annule le ticket d’un vendeur', true, $r['ok']);
        verifie('aucun mouvement de stock pour ces annulations', 0, (int) $db->query("SELECT COUNT(*) FROM stock_mouvements WHERE reference_type = 'caisse_vente' AND reference_id IN ($ticket, $ticket2)")->fetchColumn());
    } finally {
        $_SESSION['admin_role'] = $role_avant;
        $db->exec("DELETE FROM caisse_vente_lignes WHERE vente_id IN ($ticket, $ticket2)");
        $db->exec("DELETE FROM caisse_ventes WHERE id IN ($ticket, $ticket2)");
    }
    verifie('nettoyage : aucun ticket d’essai restant', 0, (int) $db->query("SELECT COUNT(*) FROM caisse_ventes WHERE numero_ticket LIKE 'ESSAI-%'")->fetchColumn());
}

echo "— point 6 : pas de remise cachée, et le prix tapé à la main se voit —\n";
$role_avant_6 = $_SESSION['admin_role'] ?? null;
verifie('plafond de remise des vendeurs à 0 tant que la direction ne l’a pas fixé', 0.0, (float) CAISSE_REMISE_MAX_VENDEUR_PCT);
$_SESSION['admin_role'] = 'commercial_general';
verifie('remise sur le ticket refusée au vendeur', true, caisse_remise_vendeur_refusee(['remise_globale_pct' => 10, 'lines' => []]) !== null);
verifie('remise de 99,99 % sur une ligne refusée au vendeur', true, caisse_remise_vendeur_refusee(['lines' => [['remise_ligne_pct' => 99.99]]]) !== null);
verifie('sans remise, le panier du vendeur passe', null, caisse_remise_vendeur_refusee(['remise_globale_pct' => 0, 'lines' => [['remise_ligne_pct' => 0]]]));
$_SESSION['admin_role'] = 'informaticien';
verifie('l’informaticien n’est pas concerné par le plafond', null, caisse_remise_vendeur_refusee(['remise_globale_pct' => 10, 'lines' => []]));
verifie('les colonnes de trace du prix existent en base locale', true, caisse_lignes_prix_trace_ok());
if ($base_locale) {
    $piece_prix = $db->query("SELECT id, nom, prix, prix_promotion, stock FROM produits WHERE statut = 'actif' AND stock >= 2 AND prix > 0 AND sync_deleted_at IS NULL ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $_SESSION['admin_role'] = 'commercial_general';
    $prix_du_catalogue = round((float) caisse_prix_unitaire_produit($piece_prix), 2);
    $panier = caisse_build_cart_from_payload(['lines' => [['produit_id' => (int) $piece_prix['id'], 'quantite' => 1, 'prix_unitaire' => (string) ($prix_du_catalogue + 500)]]]);
    verifie('panier construit avec un prix tapé à la main', true, !empty($panier['ok']));
    $nb_tickets_avant = (int) $db->query('SELECT COUNT(*) FROM caisse_ventes')->fetchColumn();
    $res_ticket = !empty($panier['ok']) ? caisse_creer_ticket_en_attente(38, $panier['cart']) : ['ok' => false];
    $vente_essai = (int) ($res_ticket['vente_id'] ?? 0);
    try {
        verifie('ticket d’essai créé', true, !empty($res_ticket['ok']));
        $ligne_essai = $db->query("SELECT prix_unitaire, prix_catalogue, prix_saisi FROM caisse_vente_lignes WHERE vente_id = $vente_essai")->fetch(PDO::FETCH_ASSOC);
        verifie('la ligne garde le prix du catalogue du jour', $prix_du_catalogue, round((float) ($ligne_essai['prix_catalogue'] ?? 0), 2));
        verifie('la ligne est marquée « prix saisi »', 1, (int) ($ligne_essai['prix_saisi'] ?? 0));
        $panier_remise = $panier['cart'];
        $panier_remise['remise_globale_pct'] = 50;
        $refus = caisse_creer_ticket_en_attente(38, $panier_remise);
        verifie('ticket avec une remise cachée de 50 % : refusé', false, !empty($refus['ok']));
        verifie('le refus ne crée aucun ticket', $nb_tickets_avant + 1, (int) $db->query('SELECT COUNT(*) FROM caisse_ventes')->fetchColumn());
    } finally {
        if ($vente_essai > 0) {
            $db->exec("DELETE FROM caisse_vente_lignes WHERE vente_id = $vente_essai");
            $db->exec("DELETE FROM caisse_ventes WHERE id = $vente_essai");
        }
    }
    verifie('nettoyage : nombre de tickets revenu à l’identique', $nb_tickets_avant, (int) $db->query('SELECT COUNT(*) FROM caisse_ventes')->fetchColumn());
}
$_SESSION['admin_role'] = $role_avant_6;

echo "— point 19 : aucune écriture ne part d'un simple lien —\n";
foreach (['admin/devis/generer_facture.php', 'admin/devis/convertir_bl.php', 'admin/devis/facture_mensuelle_generer.php',
          'admin/devis/clients_b2b_create.php', 'admin/commandes/generer_facture.php', 'admin/commandes/create_manuelle.php'] as $f) {
    $source_ecriture = file_get_contents("$RACINE/$f");
    verifie("$f exige un jeton de sécurité", true,
        strpos($source_ecriture, 'hash_equals(') !== false && strpos($source_ecriture, "\$_POST['csrf_token']") !== false);
}
foreach (['admin/devis/generer_facture.php', 'admin/devis/convertir_bl.php', 'admin/devis/facture_mensuelle_generer.php', 'admin/commandes/generer_facture.php'] as $f) {
    $source_ecriture = file_get_contents("$RACINE/$f");
    verifie("$f refuse une demande qui n'est pas un POST", true, strpos($source_ecriture, "\$_SERVER['REQUEST_METHOD'] !== 'POST'") !== false);
    verifie("$f ne lit plus ses paramètres dans l'adresse", false, strpos($source_ecriture, '$_GET[') !== false);
}
verifie('fiche commande : les changements de statut exigent le jeton', true,
    strpos(file_get_contents("$RACINE/admin/commandes/details.php"), "hash_equals((string) (\$_SESSION['admin_csrf']") !== false);
verifie('fiche client de la comptabilité : plus de génération de facture par GET', false,
    strpos(file_get_contents("$RACINE/admin/comptabilite/bl-fiche-client.php"), 'method="get" action="../devis/facture_mensuelle_generer.php"') !== false);

echo "— point 18 : les commandes vont à l'équipe commerciale, le formulaire hérité est retiré —\n";
require_once "$RACINE/models/model_admin.php";
$emails_equipe = get_emails_equipe_commerciale();
$emails_attendus = $db->query("SELECT email FROM admin WHERE statut = 'actif' AND email IS NOT NULL AND email != ''
    AND role IN ('commercial', 'commercial_general', 'informaticien', 'developpeur')")->fetchAll(PDO::FETCH_COLUMN);
sort($emails_equipe);
sort($emails_attendus);
verifie('les destinataires sont exactement l’équipe commerciale active', $emails_attendus, $emails_equipe);
$hors_equipe = (int) $db->query("SELECT COUNT(*) FROM admin WHERE statut = 'actif' AND email IS NOT NULL AND email != ''
    AND role NOT IN ('commercial', 'commercial_general', 'informaticien', 'developpeur')")->fetchColumn();
verifie("les $hors_equipe comptes hors équipe commerciale ne reçoivent plus les commandes", count($emails_attendus), count($emails_equipe));
$service = file_get_contents("$RACINE/services/send_new_commande_to_admin.php");
verifie('le service n’écrit plus à tous les comptes', false, strpos($service, 'get_all_admin_emails()') !== false);
verifie('le service ne notifie plus tous les comptes', false, strpos($service, 'get_all_fcm_tokens_admin()') !== false);
verifie('la page du formulaire hérité renvoie vers Contact', true, strpos(file_get_contents("$RACINE/commande-personnalisee.php"), "header('Location: /contact.php', true, 301);") !== false);
foreach (['user/mes-commandes.php', 'user/produits-livres.php', 'sitemap.php', 'generate_sitemap.php'] as $f) {
    verifie("$f ne mène plus au formulaire hérité", false, strpos(file_get_contents("$RACINE/$f"), '/commande-personnalisee.php') !== false);
}

echo "— point 17 : le client ne déclare plus le paiement, et les statuts ne reviennent pas en arrière —\n";
require_once "$RACINE/models/model_commandes_admin.php";
$enum_commandes = (string) $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commandes' AND COLUMN_NAME = 'statut'")->fetchColumn();
verifie('la base connaît le statut « paye » des commandes', true, strpos($enum_commandes, "'paye'") !== false);
$page_client = file_get_contents("$RACINE/user/mes-commandes.php");
verifie('« Colis reçu » passe la commande à « livrée », plus à « payée »', true,
    strpos($page_client, "update_commande_statut(\$commande_id, 'livree')") !== false && strpos($page_client, "update_commande_statut(\$commande_id, 'paye')") === false);
verifie('le client ne peut annuler qu’avant la prise en charge', true, strpos($page_client, "in_array(\$commande['statut'], ['en_attente', 'confirmee'], true)") !== false);
verifie('les formulaires du client portent un jeton', true, strpos($page_client, "\$_SESSION['user_csrf']") !== false && strpos($page_client, '$user_jeton_ok && isset($_POST') !== false);
verifie('la fiche commande propose d’enregistrer le paiement d’une commande livrée', true, strpos(file_get_contents("$RACINE/admin/commandes/details.php"), 'Enregistrer le paiement') !== false);
if ($base_locale) {
    $piece_cmd = $db->query("SELECT id, stock, date_modification, sync_updated_at FROM produits WHERE statut = 'actif' AND stock >= 3 AND sync_deleted_at IS NULL ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $stock_cmd_avant = (int) $piece_cmd['stock'];
    $cmd_ids = [];
    $cree_commande = function ($statut, $quantite) use ($db, $piece_cmd, &$cmd_ids) {
        $db->prepare("INSERT INTO commandes (numero_commande, montant_total, adresse_livraison, telephone_livraison, statut, date_commande)
                      VALUES (:n, :m, 'Essai automatique test_chantier_commercial', '770000009', :s, NOW())")
           ->execute(['n' => 'ESSAI-' . substr(uniqid(), -8), 'm' => 1000 * $quantite, 's' => $statut]);
        $id = (int) $db->lastInsertId();
        $cmd_ids[] = $id;
        $db->prepare('INSERT INTO commande_produits (commande_id, produit_id, quantite, prix_unitaire, prix_total) VALUES (:c, :p, :q, 1000, :t)')
           ->execute(['c' => $id, 'p' => (int) $piece_cmd['id'], 'q' => $quantite, 't' => 1000 * $quantite]);
        return $id;
    };
    $stock_piece_cmd = function () use ($db, $piece_cmd) {
        return (int) $db->query('SELECT stock FROM produits WHERE id = ' . (int) $piece_cmd['id'])->fetchColumn();
    };
    try {
        $c1 = $cree_commande('livraison_en_cours', 2);
        verifie('le colis reçu passe la commande à « livrée »', true, update_commande_statut($c1, 'livree'));
        verifie('... sans toucher au stock', $stock_cmd_avant, $stock_piece_cmd());
        verifie('une commande livrée ne s’annule plus', false, update_commande_statut($c1, 'annulee', 38));
        verifie('... et le refus est expliqué', true, strpos((string) ($GLOBALS['commande_statut_erreur'] ?? ''), 'livrée') !== false);
        verifie('le commercial enregistre le paiement', true, update_commande_statut($c1, 'paye', 38));
        verifie('... la marchandise sort du stock', $stock_cmd_avant - 2, $stock_piece_cmd());
        verifie('... la sortie est au journal', 1, (int) $db->query("SELECT COUNT(*) FROM stock_mouvements WHERE reference_type = 'commande' AND reference_id = $c1 AND type = 'sortie' AND quantite = 2")->fetchColumn());
        verifie('une commande payée ne revient pas en attente', false, update_commande_statut($c1, 'en_attente', 38));
        verifie('... et ne ressort pas son stock une seconde fois', $stock_cmd_avant - 2, $stock_piece_cmd());

        $c2 = $cree_commande('livree', $stock_cmd_avant + 1000);
        verifie('stock insuffisant : le paiement est refusé', false, update_commande_statut($c2, 'paye', 38));
        verifie('... le refus nomme le manque', 0, strpos((string) ($GLOBALS['commande_statut_erreur'] ?? ''), 'Stock insuffisant'));
        verifie('... la commande reste livrée', 'livree', (string) $db->query("SELECT statut FROM commandes WHERE id = $c2")->fetchColumn());
        verifie('... aucun stock sorti', $stock_cmd_avant - 2, $stock_piece_cmd());
    } finally {
        $liste_cmd = implode(',', array_map('intval', $cmd_ids ?: [0]));
        $db->exec("DELETE FROM stock_mouvements WHERE reference_type = 'commande' AND reference_id IN ($liste_cmd)");
        $db->exec("DELETE FROM commande_produits WHERE commande_id IN ($liste_cmd)");
        $db->exec("DELETE FROM commandes WHERE id IN ($liste_cmd)");
        $db->exec('SET @sync_applying = 1');
        $db->prepare('UPDATE produits SET stock = :s, date_modification = :d, sync_updated_at = :u WHERE id = :id')
           ->execute(['s' => $stock_cmd_avant, 'd' => $piece_cmd['date_modification'], 'u' => $piece_cmd['sync_updated_at'], 'id' => (int) $piece_cmd['id']]);
        $db->exec('SET @sync_applying = NULL');
    }
    verifie('nettoyage : stock rétabli, aucune commande d’essai restante', [$stock_cmd_avant, 0],
        [$stock_piece_cmd(), (int) $db->query("SELECT COUNT(*) FROM commandes WHERE numero_commande LIKE 'ESSAI-%'")->fetchColumn()]);
}

echo "— point 13 : le devis et la facture du mois suivent leurs états —\n";
verifie('les libellés des statuts de devis', ['Brouillon', 'Envoyé', 'Accepté', 'Refusé'],
    array_map(function ($s) { return devis_statut_libelle(['statut' => $s]); }, ['brouillon', 'envoye', 'accepte', 'refuse']));
verifie('la comptabilité peut enregistrer le paiement d’une facture validée', true, admin_route_is_allowed('comptabilite', 'devis/facture_mensuelle_marquer_payee.php'));
verifie('la page de statut des devis existe', true, is_file("$RACINE/admin/devis/devis_statut.php"));
verifie('un devis refusé ne se facture pas', true, strpos(file_get_contents("$RACINE/admin/devis/generer_facture.php"), "=== 'refuse'") !== false);
verifie('valider une facture du mois ne la marque plus payée', true, strpos(file_get_contents("$RACINE/models/model_factures_mensuelles.php"), "SET statut = \\'validee\\'") !== false);
if ($base_locale) {
    $devis_essai = [];
    $fm_etat = 0;
    $cree_devis = function () use ($db, &$devis_essai) {
        $db->prepare("INSERT INTO devis (numero_devis, client_nom, client_prenom, client_telephone, adresse_livraison, montant_total, statut, date_creation)
                      VALUES (:n, 'Essai', 'Automatique', '770000010', 'Essai automatique test_chantier_commercial', 1000, 'brouillon', NOW())")
           ->execute(['n' => 'ESSAI-' . substr(uniqid(), -8)]);
        $id = (int) $db->lastInsertId();
        $devis_essai[] = $id;
        return $id;
    };
    $statut_du_devis = function ($id) use ($db) {
        return (string) $db->query("SELECT statut FROM devis WHERE id = $id")->fetchColumn();
    };
    try {
        $d1 = $cree_devis();
        verifie('brouillon → envoyé', true, devis_changer_statut($d1, 'envoye')['ok']);
        verifie('envoyé → brouillon : refusé', false, devis_changer_statut($d1, 'brouillon')['ok']);
        verifie('envoyé → accepté', true, devis_changer_statut($d1, 'accepte')['ok']);
        verifie('accepté → refusé : refusé', false, devis_changer_statut($d1, 'refuse')['ok']);
        verifie('le devis reste accepté', 'accepte', $statut_du_devis($d1));
        $d2 = $cree_devis();
        verifie('brouillon → refusé', true, devis_changer_statut($d2, 'refuse')['ok']);
        verifie('refusé → accepté : refusé', false, devis_changer_statut($d2, 'accepte')['ok']);

        $client_etat = (int) $db->query('SELECT id FROM clients_b2b ORDER BY id LIMIT 1')->fetchColumn();
        $db->prepare("INSERT INTO factures_mensuelles (numero_facture, client_b2b_id, annee, mois, statut, total_ht, admin_createur_id, date_creation)
                      VALUES (:n, :c, 2099, 11, 'brouillon', 0, 38, NOW())")
           ->execute(['n' => 'FM-ESSAI-' . substr(uniqid(), -6), 'c' => $client_etat]);
        $fm_etat = (int) $db->lastInsertId();
        verifie('valider une facture du mois', true, valider_facture_mensuelle($fm_etat));
        $ligne_fm = $db->query("SELECT statut, date_emission, date_paiement FROM factures_mensuelles WHERE id = $fm_etat")->fetch(PDO::FETCH_ASSOC);
        verifie('... elle est « validée », pas payée', 'validee', $ligne_fm['statut']);
        verifie('... émise aujourd’hui, sans date de paiement', [date('Y-m-d'), null], [$ligne_fm['date_emission'], $ligne_fm['date_paiement']]);
        verifie('enregistrer ensuite son paiement', true, marquer_facture_mensuelle_comme_payee($fm_etat));
        verifie('... elle est payée', 'payee', (string) $db->query("SELECT statut FROM factures_mensuelles WHERE id = $fm_etat")->fetchColumn());
    } finally {
        $liste_devis_essai = implode(',', array_map('intval', $devis_essai ?: [0]));
        $db->exec("DELETE FROM devis WHERE id IN ($liste_devis_essai)");
        if ($fm_etat > 0) {
            $db->exec("DELETE FROM factures_mensuelles WHERE id = $fm_etat");
        }
    }
    verifie('nettoyage : aucun devis ni facture du mois d’essai restant', [0, 0], [
        (int) $db->query("SELECT COUNT(*) FROM devis WHERE numero_devis LIKE 'ESSAI-%'")->fetchColumn(),
        (int) $db->query("SELECT COUNT(*) FROM factures_mensuelles WHERE numero_facture LIKE 'FM-ESSAI-%'")->fetchColumn(),
    ]);
}

echo "— point 15 : l'accueil montre les relances et toutes ses ventes du mois —\n";
require_once "$RACINE/models/model_commercial_accueil.php";
$source_accueil = file_get_contents("$RACINE/admin/commercial/index.php");
foreach (['factures-a-relancer', 'devis-sans-reponse', 'mes-ventes-du-mois'] as $section) {
    verifie("l'accueil porte la section « $section »", true, strpos($source_accueil, 'id="' . $section . '"') !== false);
}
$debut_du_mois = date('Y-m-01');
foreach ([20, 23, 10] as $compte) {
    $attendu_relances = (int) $db->query("SELECT COUNT(*) FROM factures_devis f INNER JOIN devis d ON d.id = f.devis_id
        WHERE COALESCE(f.payee, 0) = 0 AND d.sync_deleted_at IS NULL AND d.admin_createur_id = $compte")->fetchColumn();
    verifie("compte $compte : factures à relancer = seconde lecture ($attendu_relances)", $attendu_relances, count(commercial_factures_a_relancer($compte)));
    $mois_compte = commercial_ventes_du_mois($compte);
    verifie("compte $compte : au moins trois chemins de vente dans le mois", true, count($mois_compte) >= 3);
    $attendu_caisse = (int) $db->query("SELECT COUNT(*) FROM caisse_ventes WHERE sync_deleted_at IS NULL AND statut = 'paye'
        AND admin_id = $compte AND date_encaissement >= '$debut_du_mois'")->fetchColumn();
    verifie("compte $compte : tickets encaissés du mois = seconde lecture ($attendu_caisse)", $attendu_caisse, $mois_compte[0]['nombre']);
}
if ($base_locale) {
    $db->prepare("INSERT INTO devis (numero_devis, client_nom, client_prenom, client_telephone, adresse_livraison, montant_total, statut, admin_createur_id, date_creation)
                  VALUES (:n, 'Essai', 'Relance', '770000011', 'Essai automatique test_chantier_commercial', 1000, 'envoye', 38, NOW())")
       ->execute(['n' => 'ESSAI-' . substr(uniqid(), -8)]);
    $devis_relance = (int) $db->lastInsertId();
    try {
        $db->exec('SET @sync_applying = 1');
        $db->exec("UPDATE devis SET date_modification = NOW() - INTERVAL 10 DAY WHERE id = $devis_relance");
        $db->exec('SET @sync_applying = NULL');
        $cherche_relance = function ($seuil) use ($devis_relance) {
            return count(array_filter(commercial_devis_sans_reponse(38, $seuil), function ($s) use ($devis_relance) {
                return (int) $s['id'] === $devis_relance;
            }));
        };
        verifie('un devis envoyé il y a 10 jours apparaît « sans réponse » au seuil de 7 jours', 1, $cherche_relance(7));
        verifie('... mais pas au seuil de 15 jours', 0, $cherche_relance(15));
    } finally {
        $db->exec("DELETE FROM devis WHERE id = $devis_relance");
    }
    verifie('nettoyage : aucun devis d’essai restant', 0, (int) $db->query("SELECT COUNT(*) FROM devis WHERE numero_devis LIKE 'ESSAI-%'")->fetchColumn());
}

echo "— point 14 : la caisse trouve la pièce par son étiquette, sa référence FPL ou OEM —\n";
require_once "$RACINE/includes/produit_vitrine.php";
$vivantes = $db->query("SELECT id, nom, description, identifiant_interne, reference_fpl, reference_oem
    FROM produits WHERE statut = 'actif' AND sync_deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$resolu = function ($code) {
    $r = caisse_resoudre_produit_par_code($code);
    return !empty($r['ok']) ? (int) $r['produit']['id'] : 0;
};
// Combien de pièces portent chaque clé de référence (FPL ou OEM), comparée comme le serveur la compare.
$porteurs = [];
$idents = [];
foreach ($vivantes as $v) {
    foreach ([(string) $v['reference_fpl'], (string) $v['reference_oem']] as $reference) {
        $cle = produits_ref_normalise($reference);
        if (strlen($cle) >= 4) {
            $porteurs[$cle][(int) $v['id']] = true;
        }
    }
    $ident = strtoupper(trim((string) $v['identifiant_interne']));
    $idents[$ident] = ($idents[$ident] ?? 0) + 1;
}

$piece_etiquette = null;
foreach (array_reverse($vivantes) as $v) {
    $ident = strtoupper(trim((string) $v['identifiant_interne']));
    if (preg_match('/^FPL\d{9}$/', $ident) && $idents[$ident] === 1) {
        $piece_etiquette = $v;
        break;
    }
}
verifie('une pièce à étiquette existe pour l’essai', true, $piece_etiquette !== null);
if ($piece_etiquette) {
    $id_attendu = (int) $piece_etiquette['id'];
    $ean = fpl_vitrine_ean13_pour_produit($piece_etiquette);
    verifie("le code-barres de l'étiquette ($ean) trouve sa pièce", $id_attendu, $resolu($ean));
    verifie('le QR de l’étiquette trouve la pièce', $id_attendu, $resolu(produit_vitrine_url($piece_etiquette)));
    verifie('l’identifiant FPL tapé en minuscules trouve la pièce', $id_attendu, $resolu(strtolower((string) $piece_etiquette['identifiant_interne'])));
    verifie('ses 9 chiffres seuls trouvent la pièce', $id_attendu, $resolu(substr(trim((string) $piece_etiquette['identifiant_interne']), 3)));
}

// Un numéro interne tapé n'ajoute plus sa pièce (choisie pour que rien d'autre sur elle ne porte ce nombre).
foreach (array_reverse($vivantes) as $v) {
    $id_txt = (string) $v['id'];
    $sur_la_piece = $v['nom'] . ' ' . $v['description'] . ' ' . $v['identifiant_interne'] . ' ' . $v['reference_fpl'] . ' ' . $v['reference_oem'];
    if (strpos((string) $sur_la_piece, $id_txt) === false) {
        verifie("taper « $id_txt » n’ajoute plus la pièce qui porte ce numéro interne", false, $resolu($id_txt) === (int) $v['id']);
        $r = caisse_resoudre_produit_par_code($id_txt);
        verifie('… ni une autre pièce au hasard d’une description', count($porteurs[produits_ref_normalise($id_txt)] ?? []) === 1, !empty($r['ok']));
        break;
    }
}

$oem_essaye = $fpl_essaye = $double_essaye = false;
foreach ($vivantes as $v) {
    $cle_oem = produits_ref_normalise((string) $v['reference_oem']);
    if (!$oem_essaye && strlen($cle_oem) >= 6 && !preg_match('/^(\d{6}|\d{9}|\d{13})$/', $cle_oem)
        && count($porteurs[$cle_oem] ?? []) === 1) {
        $saisie = implode(' ', str_split($cle_oem, 3));
        verifie("la référence OEM tapée « $saisie » trouve sa pièce", (int) $v['id'], $resolu($saisie));
        $oem_essaye = true;
    }
    $cle_fpl = produits_ref_normalise((string) $v['reference_fpl']);
    if (strlen($cle_fpl) >= 4) {
        $n = count($porteurs[$cle_fpl] ?? []);
        if (!$fpl_essaye && $n === 1) {
            verifie("la référence FPL imprimée « {$v['reference_fpl']} » trouve sa pièce", (int) $v['id'], $resolu(strtolower((string) $v['reference_fpl'])));
            $fpl_essaye = true;
        }
        if (!$double_essaye && $n > 1) {
            $r = caisse_resoudre_produit_par_code((string) $v['reference_fpl']);
            verifie("« {$v['reference_fpl']} », portée par $n pièces, ne tranche pas au hasard", false, !empty($r['ok']));
            verifie('… et le dit au vendeur', true, strpos((string) ($r['error'] ?? ''), 'Plusieurs pièces') === 0);
            $double_essaye = true;
        }
    }
}
verifie('les cas OEM, référence FPL et référence partagée ont tous été essayés', [true, true, true], [$oem_essaye, $fpl_essaye, $double_essaye]);

$catalogue_direct = caisse_catalog_live_items();
$avec_oem = $avec_fpl = null;
foreach ($catalogue_direct as $it) {
    if ($avec_oem === null && trim((string) ($it['ref_oem'] ?? '')) !== '') {
        $avec_oem = $it;
    }
    if ($avec_fpl === null && trim((string) ($it['ref_fpl'] ?? '')) !== '') {
        $avec_fpl = $it;
    }
}
verifie('la référence OEM est dans le texte cherché en direct', true,
    $avec_oem !== null && strpos((string) $avec_oem['search'], produits_recherche_normalize($avec_oem['ref_oem'])) !== false);
verifie('la référence FPL imprimée est dans le texte cherché en direct', true,
    $avec_fpl !== null && strpos((string) $avec_fpl['search'], produits_recherche_normalize($avec_fpl['ref_fpl'])) !== false);

$panier_js = str_replace("\r\n", "\n", file_get_contents("$RACINE/js/admin-caisse-panier.js"));
verifie('le panier est gardé dans l’onglet à chaque mise à jour du total', true, strpos($panier_js, "function updateRecapOnly() {\n    sauverPanier();") !== false);
verifie('il est repris au chargement de la caisse', true, strpos($panier_js, "restaurerPanier();\n    bindRootEvents();") !== false);
verifie('le panier d’un autre vendeur n’est pas repris', true, strpos($panier_js, 'lu.vendeur === (cfg.vendeur_id || 0)') !== false);
verifie('le panier est oublié après le ticket et après l’encaissement', 2, substr_count($panier_js, 'oublierPanier();'));
verifie('la page de caisse transmet le vendeur au panier', true,
    strpos(file_get_contents("$RACINE/admin/caisse/index.php"), "vendeur_id: <?php echo (int) (\$_SESSION['admin_id'] ?? 0); ?>") !== false);
$recherche_js = file_get_contents("$RACINE/js/admin-caisse-live-search.js");
$appel_code = strpos($recherche_js, 'if (estCodeExact(raw) && window.CaissePanier)');
verifie('un code d’étiquette part au serveur avant le rapprochement flou', true,
    $appel_code !== false && $appel_code < strpos($recherche_js, 'var hitsQuick'));

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
