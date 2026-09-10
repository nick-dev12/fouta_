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

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
