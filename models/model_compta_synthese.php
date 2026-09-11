<?php
/**
 * LA SYNTHÈSE COMPTABLE UNIQUE (point 16 du chantier, 11/09/2026).
 *
 * Mesuré : aucune page n'additionnait toutes les ventes. La carte « Gains » de
 * la comptabilité ne comptait que le site et la caisse ; le bilan posait côte à
 * côte les bons de livraison et les factures du mois qui les regroupent, et
 * n'additionnait que 400 tickets au plus ; aucun total ne déduisait les bons de
 * retour ni les retours de caisse ; le tableau de bord ajoutait du TTC à du HT,
 * brouillons compris, sans la caisse.
 *
 * Un seul calcul, trois questions :
 * - VENDU sur la période, par les quatre chemins, retours déduits :
 *   caisse (tickets payés, date d'encaissement ; un retour validé retire la
 *   valeur rendue et ajoute la pièce remise, le jour de sa validation),
 *   factures de devis (date de facture), bons de livraison validés (date du
 *   bon ; leurs bons de retour se déduisent le jour du retour ; la facture du
 *   mois ne s'ajoute PAS : elle regroupe ces bons), site (commandes livrées ou
 *   payées, date de commande). Montants dus par le client, avec la règle de
 *   TVA de paiement_facture_etat().
 * - ENCAISSÉ sur la période : caisse par moyen de paiement, espèces des retours,
 *   paiements du registre des factures à leur date, factures payées avant le
 *   registre (montant de la facture, date de paiement notée), site payé.
 * - À ENCAISSER à ce jour : ce que les clients doivent encore, et les avoirs à
 *   émettre (retours arrivés après une facture qui ne se recalcule plus).
 * Les dépenses se retirent des encaissements ; ce solde n'est pas un bénéfice.
 *
 * AUCUNE fonction n'avale d'erreur : un total faux et silencieux est pire
 * qu'une page qui prévient. L'appelant attrape et affiche.
 */

require_once __DIR__ . '/../includes/fiscal_tva.php';
require_once __DIR__ . '/model_caisse.php';
require_once __DIR__ . '/model_caisse_compta.php';
require_once __DIR__ . '/model_bl.php';
require_once __DIR__ . '/model_factures_mensuelles.php';
require_once __DIR__ . '/model_paiements_factures.php';

/** La colonne existe-t-elle sur ce serveur ? */
function compta_synthese_colonne_ok($table, $colonne)
{
    global $db;
    static $cache = [];
    $cle = $table . '.' . $colonne;
    if (!array_key_exists($cle, $cache)) {
        $st = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        if ($st === false || $st->execute([(string) $table, (string) $colonne]) === false) {
            throw new RuntimeException('Synthèse comptable : schéma illisible.');
        }
        $cache[$cle] = (int) $st->fetchColumn() > 0;
    }
    return $cache[$cle];
}

/** « AND alias.sync_deleted_at IS NULL » quand la table porte la colonne : une ligne supprimée ne compte pas. */
function compta_synthese_vivant($table, $alias)
{
    return compta_synthese_colonne_ok($table, 'sync_deleted_at') ? " AND $alias.sync_deleted_at IS NULL" : '';
}

/** @return array<int, array<string, mixed>> */
function compta_synthese_requete($sql, array $params)
{
    global $db;
    $st = $db->prepare($sql);
    if ($st === false || $st->execute($params) === false) {
        $err = $st ? $st->errorInfo() : $db->errorInfo();
        throw new RuntimeException('Synthèse comptable : lecture refusée (' . ($err[2] ?? 'erreur inconnue') . ').');
    }
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed> */
function compta_synthese_ligne($sql, array $params)
{
    $lignes = compta_synthese_requete($sql, $params);
    return $lignes[0] ?? [];
}

/** Deux dates AAAA-MM-JJ valides, dans l'ordre. @return array{0:string,1:string} */
function compta_synthese_dates($date_debut, $date_fin)
{
    $d1 = trim((string) $date_debut);
    $d2 = trim((string) $date_fin);
    foreach ([$d1, $d2] as $d) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
            throw new InvalidArgumentException('Synthèse comptable : date invalide « ' . $d . ' ».');
        }
    }
    return strcmp($d1, $d2) > 0 ? [$d2, $d1] : [$d1, $d2];
}

/**
 * Le montant dû d'un bon en SQL : TTC si sa TVA est incluse, sinon son total,
 * comme paiement_facture_etat(). $valeur est une colonne HT du bon ou d'un de
 * ses retours ; $alias_bon désigne la ligne du bon qui porte la TVA.
 */
function compta_synthese_sql_du_bon($alias_bon, $valeur)
{
    if (!bl_tva_columns_ok() || !compta_synthese_colonne_ok('bons_livraison', 'tva_incluse')) {
        return "($valeur)";
    }
    $defaut = sprintf('%.4F', fiscal_taux_tva_pourcent());
    $taux = compta_synthese_colonne_ok('bons_livraison', 'taux_tva_pourcent')
        ? "(CASE WHEN $alias_bon.taux_tva_pourcent > 0 THEN $alias_bon.taux_tva_pourcent ELSE $defaut END)"
        : $defaut;
    return "($valeur + CASE WHEN $alias_bon.tva_incluse = 1 THEN ROUND($valeur * $taux / 100, 2) ELSE 0 END)";
}

/** Le montant dû d'une facture du mois en SQL, comme paiement_facture_etat(). */
function compta_synthese_sql_du_facture_mois($alias, $valeur)
{
    if (!factures_mensuelles_tva_incluse_column_ok()) {
        return "($valeur)";
    }
    $taux = sprintf('%.4F', fiscal_taux_tva_pourcent());
    return "($valeur + CASE WHEN $alias.tva_incluse = 1 THEN ROUND($valeur * $taux / 100, 2) ELSE 0 END)";
}

/** Libellé d'un moyen de paiement (caisse ou registre des factures). */
function compta_synthese_libelle_moyen($moyen)
{
    $libelles = [
        'especes' => 'Espèces',
        'carte' => 'Carte bancaire',
        'orange_money' => 'Orange Money',
        'wave' => 'Wave',
        'cheque' => 'Chèque',
        'virement' => 'Virement',
        'autre' => 'Autre',
    ];
    return $libelles[(string) $moyen] ?? ucfirst(str_replace('_', ' ', (string) $moyen));
}

/** Les retours clients validés en caisse sur la période, à la date de validation. */
function compta_synthese_retours_caisse($d1, $d2)
{
    $vide = ['nb' => 0, 'rendu' => 0.0, 'remis' => 0.0, 'especes_rendues' => 0.0, 'especes_recues' => 0.0];
    if (!compta_synthese_colonne_ok('caisse_retours', 'date_validation')) {
        return $vide;
    }
    $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(r.montant_rendu), 0) AS rendu, COALESCE(SUM(r.montant_remis), 0) AS remis,
            COALESCE(SUM(r.especes_a_rendre), 0) AS rendues, COALESCE(SUM(r.especes_a_recevoir), 0) AS recues
        FROM caisse_retours r
        WHERE r.statut = 'valide' AND DATE(r.date_validation) BETWEEN :d1 AND :d2" . compta_synthese_vivant('caisse_retours', 'r'),
        ['d1' => $d1, 'd2' => $d2]);
    return [
        'nb' => (int) $l['nb'],
        'rendu' => round((float) $l['rendu'], 2),
        'remis' => round((float) $l['remis'], 2),
        'especes_rendues' => round((float) $l['rendues'], 2),
        'especes_recues' => round((float) $l['recues'], 2),
    ];
}

/**
 * CE QUI A ÉTÉ VENDU sur la période, par chemin, retours déduits.
 *
 * @return array<string, mixed>
 */
function compta_synthese_ventes($date_debut, $date_fin)
{
    [$d1, $d2] = compta_synthese_dates($date_debut, $date_fin);
    $p = ['d1' => $d1, 'd2' => $d2];

    $caisse = ['nb' => 0, 'brut' => 0.0, 'retours_nb' => 0, 'rendu' => 0.0, 'remis' => 0.0, 'net' => 0.0];
    $attente = ['nb' => 0, 'montant' => 0.0];
    if (caisse_tables_exist()) {
        $vivant = compta_synthese_vivant('caisse_ventes', 'v');
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(v.montant_total), 0) AS s FROM caisse_ventes v
            WHERE v.statut = 'paye' AND DATE(COALESCE(v.date_encaissement, v.date_vente)) BETWEEN :d1 AND :d2$vivant", $p);
        $retours = compta_synthese_retours_caisse($d1, $d2);
        $caisse = [
            'nb' => (int) $l['nb'],
            'brut' => round((float) $l['s'], 2),
            'retours_nb' => $retours['nb'],
            'rendu' => $retours['rendu'],
            'remis' => $retours['remis'],
            'net' => round((float) $l['s'] - $retours['rendu'] + $retours['remis'], 2),
        ];
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(v.montant_total), 0) AS s FROM caisse_ventes v
            WHERE v.statut = 'en_attente' AND DATE(v.date_vente) BETWEEN :d1 AND :d2$vivant", $p);
        $attente = ['nb' => (int) $l['nb'], 'montant' => round((float) $l['s'], 2)];
    }

    $devis = ['nb' => 0, 'net' => 0.0];
    if (compta_synthese_colonne_ok('factures_devis', 'date_facture')) {
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(f.montant_total), 0) AS s FROM factures_devis f
            WHERE f.date_facture BETWEEN :d1 AND :d2" . compta_synthese_vivant('factures_devis', 'f'), $p);
        $devis = ['nb' => (int) $l['nb'], 'net' => round((float) $l['s'], 2)];
    }

    $bons = ['nb' => 0, 'brut' => 0.0, 'retours_nb' => 0, 'retours' => 0.0, 'net' => 0.0];
    $brouillons = ['nb' => 0, 'montant' => 0.0];
    if (bl_tables_available()) {
        $du = compta_synthese_sql_du_bon('b', 'b.total_ht');
        $vivant_b = compta_synthese_vivant('bons_livraison', 'b');
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM($du), 0) AS s FROM bons_livraison b
            WHERE b.statut IN ('valide', 'paye') AND DATE(b.date_bl) BETWEEN :d1 AND :d2$vivant_b", $p);
        $retours = ['nb' => 0, 's' => 0.0];
        if (compta_synthese_colonne_ok('bons_retour', 'total_ht_retour')) {
            $du_retour = compta_synthese_sql_du_bon('b', 'r.total_ht_retour');
            $retours = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM($du_retour), 0) AS s FROM bons_retour r
                INNER JOIN bons_livraison b ON b.id = r.bl_id
                WHERE b.statut IN ('valide', 'paye') AND DATE(r.date_retour) BETWEEN :d1 AND :d2$vivant_b" . compta_synthese_vivant('bons_retour', 'r'), $p);
        }
        $bons = [
            'nb' => (int) $l['nb'],
            'brut' => round((float) $l['s'], 2),
            'retours_nb' => (int) $retours['nb'],
            'retours' => round((float) $retours['s'], 2),
            'net' => round((float) $l['s'] - (float) $retours['s'], 2),
        ];
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM($du), 0) AS s FROM bons_livraison b
            WHERE b.statut = 'brouillon' AND DATE(b.date_bl) BETWEEN :d1 AND :d2$vivant_b", $p);
        $brouillons = ['nb' => (int) $l['nb'], 'montant' => round((float) $l['s'], 2)];
    }

    $site = ['nb' => 0, 'net' => 0.0];
    if (compta_synthese_colonne_ok('commandes', 'date_commande')) {
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(c.montant_total), 0) AS s FROM commandes c
            WHERE c.statut IN ('livree', 'paye') AND DATE(c.date_commande) BETWEEN :d1 AND :d2" . compta_synthese_vivant('commandes', 'c'), $p);
        $site = ['nb' => (int) $l['nb'], 'net' => round((float) $l['s'], 2)];
    }

    $factures_mois = ['nb' => 0, 'montant' => 0.0];
    if (factures_mensuelles_table_ok()) {
        $premier_du_mois = "STR_TO_DATE(CONCAT(f.annee, '-', LPAD(f.mois, 2, '0'), '-01'), '%Y-%m-%d')";
        $emission = compta_synthese_colonne_ok('factures_mensuelles', 'date_emission') ? "COALESCE(f.date_emission, $premier_du_mois)" : $premier_du_mois;
        $du_fm = compta_synthese_sql_du_facture_mois('f', 'f.total_ht');
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM($du_fm), 0) AS s FROM factures_mensuelles f
            WHERE f.statut IN ('validee', 'payee') AND $emission BETWEEN :d1 AND :d2" . compta_synthese_vivant('factures_mensuelles', 'f'), $p);
        $factures_mois = ['nb' => (int) $l['nb'], 'montant' => round((float) $l['s'], 2)];
    }

    return [
        'caisse' => $caisse,
        'devis' => $devis,
        'bons' => $bons,
        'site' => $site,
        'total' => round($caisse['net'] + $devis['net'] + $bons['net'] + $site['net'], 2),
        'hors' => ['tickets_attente' => $attente, 'bons_brouillon' => $brouillons, 'factures_mois' => $factures_mois],
    ];
}

/**
 * L'ARGENT REÇU sur la période.
 *
 * @return array<string, mixed>
 */
function compta_synthese_encaissements($date_debut, $date_fin)
{
    [$d1, $d2] = compta_synthese_dates($date_debut, $date_fin);
    $p = ['d1' => $d1, 'd2' => $d2];

    $canaux = array_fill_keys(caisse_compta_canaux_tri(), 0.0);
    $caisse = ['nb' => 0, 'total' => 0.0, 'canaux' => $canaux, 'especes_rendues' => 0.0, 'especes_recues' => 0.0, 'net' => 0.0];
    if (caisse_tables_exist()) {
        $tickets = compta_synthese_requete("SELECT v.mode_paiement, v.montant_total, v.montant_especes, v.montant_carte,
                v.montant_orange_money, v.montant_wave, v.montant_mobile_money
            FROM caisse_ventes v
            WHERE v.statut = 'paye' AND DATE(COALESCE(v.date_encaissement, v.date_vente)) BETWEEN :d1 AND :d2" . compta_synthese_vivant('caisse_ventes', 'v'), $p);
        $total = 0.0;
        foreach ($tickets as $ticket) {
            $total += (float) $ticket['montant_total'];
            foreach (array_keys($canaux) as $canal) {
                $canaux[$canal] += caisse_compta_montant_vente_canal($ticket, $canal);
            }
        }
        foreach ($canaux as $canal => $montant) {
            $canaux[$canal] = round($montant, 2);
        }
        $retours = compta_synthese_retours_caisse($d1, $d2);
        $caisse = [
            'nb' => count($tickets),
            'total' => round($total, 2),
            'canaux' => $canaux,
            'especes_rendues' => $retours['especes_rendues'],
            'especes_recues' => $retours['especes_recues'],
            'net' => round($total - $retours['especes_rendues'] + $retours['especes_recues'], 2),
        ];
    }

    $par_type = ['facture_devis' => 0.0, 'bl' => 0.0, 'facture_mensuelle' => 0.0];
    $factures = ['nb' => 0, 'total' => 0.0, 'types' => $par_type, 'moyens' => []];
    $registre = paiements_factures_table_ok();
    if ($registre) {
        $lignes = compta_synthese_requete("SELECT CASE WHEN p.facture_devis_id IS NOT NULL THEN 'facture_devis' WHEN p.bl_id IS NOT NULL THEN 'bl' ELSE 'facture_mensuelle' END AS type_facture,
                p.mode_paiement, COUNT(*) AS nb, COALESCE(SUM(p.montant), 0) AS s
            FROM paiements_factures p
            WHERE p.date_annulation IS NULL AND p.date_paiement BETWEEN :d1 AND :d2" . compta_synthese_vivant('paiements_factures', 'p') . "
            GROUP BY type_facture, p.mode_paiement", $p);
        foreach ($lignes as $l) {
            $factures['nb'] += (int) $l['nb'];
            $factures['total'] += (float) $l['s'];
            $factures['types'][$l['type_facture']] += (float) $l['s'];
            $factures['moyens'][$l['mode_paiement']] = ($factures['moyens'][$l['mode_paiement']] ?? 0.0) + (float) $l['s'];
        }
        $factures['total'] = round($factures['total'], 2);
    }

    // Les factures marquées payées avant le registre (10/09/2026) : sans ligne de paiement, datées par la date notée.
    $sans_ligne = static function ($colonne, $alias) use ($registre) {
        return $registre
            ? " AND NOT EXISTS (SELECT 1 FROM paiements_factures p WHERE p.$colonne = $alias.id AND p.date_annulation IS NULL" . compta_synthese_vivant('paiements_factures', 'p') . ')'
            : '';
    };
    $avant = ['nb' => 0, 'total' => 0.0, 'types' => $par_type];
    if (compta_synthese_colonne_ok('factures_devis', 'payee') && compta_synthese_colonne_ok('factures_devis', 'date_paiement')) {
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(f.montant_total), 0) AS s FROM factures_devis f
            WHERE f.payee = 1 AND DATE(f.date_paiement) BETWEEN :d1 AND :d2" . compta_synthese_vivant('factures_devis', 'f') . $sans_ligne('facture_devis_id', 'f'), $p);
        $avant['nb'] += (int) $l['nb'];
        $avant['types']['facture_devis'] = round((float) $l['s'], 2);
    }
    if (bl_tables_available() && compta_synthese_colonne_ok('bons_livraison', 'facture_bl_payee') && compta_synthese_colonne_ok('bons_livraison', 'date_paiement_bl')) {
        $du = compta_synthese_sql_du_bon('b', 'b.total_ht');
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM($du), 0) AS s FROM bons_livraison b
            WHERE b.facture_bl_payee = 1 AND DATE(b.date_paiement_bl) BETWEEN :d1 AND :d2" . compta_synthese_vivant('bons_livraison', 'b') . $sans_ligne('bl_id', 'b'), $p);
        $avant['nb'] += (int) $l['nb'];
        $avant['types']['bl'] = round((float) $l['s'], 2);
    }
    if (factures_mensuelles_table_ok() && compta_synthese_colonne_ok('factures_mensuelles', 'date_paiement')) {
        $du_fm = compta_synthese_sql_du_facture_mois('f', 'f.total_ht');
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM($du_fm), 0) AS s FROM factures_mensuelles f
            WHERE f.statut = 'payee' AND DATE(f.date_paiement) BETWEEN :d1 AND :d2" . compta_synthese_vivant('factures_mensuelles', 'f') . $sans_ligne('facture_mensuelle_id', 'f'), $p);
        $avant['nb'] += (int) $l['nb'];
        $avant['types']['facture_mensuelle'] = round((float) $l['s'], 2);
    }
    $avant['total'] = round(array_sum($avant['types']), 2);

    $site = ['nb' => 0, 'total' => 0.0];
    if (compta_synthese_colonne_ok('commandes', 'date_commande')) {
        $livraison = compta_synthese_colonne_ok('commandes', 'date_livraison') ? 'COALESCE(c.date_livraison, c.date_commande)' : 'c.date_commande';
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(c.montant_total), 0) AS s FROM commandes c
            WHERE c.statut = 'paye' AND DATE($livraison) BETWEEN :d1 AND :d2" . compta_synthese_vivant('commandes', 'c'), $p);
        $site = ['nb' => (int) $l['nb'], 'total' => round((float) $l['s'], 2)];
    }

    return [
        'caisse' => $caisse,
        'factures' => $factures,
        'avant_registre' => $avant,
        'site' => $site,
        'total' => round($caisse['net'] + $factures['total'] + $avant['total'] + $site['total'], 2),
    ];
}

/** Les dépenses saisies sur la période, TTC quand il est connu. @return array{nb:int, montant:float} */
function compta_synthese_depenses($date_debut, $date_fin)
{
    [$d1, $d2] = compta_synthese_dates($date_debut, $date_fin);
    if (!compta_synthese_colonne_ok('depenses', 'date_depense')) {
        return ['nb' => 0, 'montant' => 0.0];
    }
    $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(COALESCE(d.montant_ttc, d.montant_ht)), 0) AS s FROM depenses d
        WHERE d.date_depense BETWEEN :d1 AND :d2" . compta_synthese_vivant('depenses', 'd'), ['d1' => $d1, 'd2' => $d2]);
    return ['nb' => (int) $l['nb'], 'montant' => round((float) $l['s'], 2)];
}

/**
 * CE QUE LES CLIENTS DOIVENT ENCORE, à ce jour, et les avoirs à émettre.
 * Un bon regroupé dans une facture du mois validée ou payée se compte par la
 * facture, jamais deux fois.
 *
 * @return array<string, mixed>
 */
function compta_synthese_a_encaisser()
{
    $registre = paiements_factures_table_ok();
    $paye = static function ($colonne, $alias) use ($registre) {
        return $registre
            ? "COALESCE((SELECT SUM(p.montant) FROM paiements_factures p WHERE p.$colonne = $alias.id AND p.date_annulation IS NULL" . compta_synthese_vivant('paiements_factures', 'p') . '), 0)'
            : '0';
    };
    $somme = static function ($sql) {
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(x.reste), 0) AS s FROM ($sql) x WHERE x.reste > 0.005", []);
        return ['nb' => (int) $l['nb'], 'montant' => round((float) $l['s'], 2)];
    };
    $vide = ['nb' => 0, 'montant' => 0.0];
    $out = ['devis' => $vide, 'bons' => $vide, 'factures_mois' => $vide, 'site' => $vide, 'total' => 0.0,
        'avoirs' => ['nb' => 0, 'montant' => 0.0, 'lignes' => []]];

    if (compta_synthese_colonne_ok('factures_devis', 'payee')) {
        $out['devis'] = $somme('SELECT f.montant_total - ' . $paye('facture_devis_id', 'f') . ' AS reste FROM factures_devis f
            WHERE COALESCE(f.payee, 0) = 0' . compta_synthese_vivant('factures_devis', 'f'));
    }

    $fm_ok = factures_mensuelles_table_ok() && compta_synthese_colonne_ok('facture_mensuelle_bl', 'bl_id');
    $retours_ok = compta_synthese_colonne_ok('bons_retour', 'total_ht_retour');
    $vivant_r = compta_synthese_vivant('bons_retour', 'r');
    if (bl_tables_available() && compta_synthese_colonne_ok('bons_livraison', 'facture_bl_payee')) {
        $du = compta_synthese_sql_du_bon('b', 'b.total_ht');
        $retours = $retours_ok
            ? 'COALESCE((SELECT SUM(' . compta_synthese_sql_du_bon('b', 'r.total_ht_retour') . ") FROM bons_retour r WHERE r.bl_id = b.id$vivant_r), 0)"
            : '0';
        $hors_facture_close = $fm_ok
            ? " AND NOT EXISTS (SELECT 1 FROM facture_mensuelle_bl x INNER JOIN factures_mensuelles fm ON fm.id = x.facture_mensuelle_id
                WHERE x.bl_id = b.id AND fm.statut IN ('validee', 'payee'))"
            : '';
        // Bons livrés, pas encore sur une facture du mois validée ni payés seuls : leur montant, retours déduits, moins ce qui est déjà payé.
        $out['bons'] = $somme("SELECT $du - $retours - " . $paye('bl_id', 'b') . " AS reste FROM bons_livraison b
            WHERE b.statut IN ('valide', 'paye') AND COALESCE(b.facture_bl_payee, 0) = 0" . compta_synthese_vivant('bons_livraison', 'b') . $hors_facture_close);
    }

    if (factures_mensuelles_table_ok()) {
        $du_fm = compta_synthese_sql_du_facture_mois('f', 'f.total_ht');
        $out['factures_mois'] = $somme("SELECT $du_fm - " . $paye('facture_mensuelle_id', 'f') . " AS reste FROM factures_mensuelles f
            WHERE f.statut = 'validee'" . compta_synthese_vivant('factures_mensuelles', 'f'));
    }

    if (compta_synthese_colonne_ok('commandes', 'statut')) {
        $l = compta_synthese_ligne("SELECT COUNT(*) AS nb, COALESCE(SUM(c.montant_total), 0) AS s FROM commandes c
            WHERE c.statut = 'livree'" . compta_synthese_vivant('commandes', 'c'), []);
        $out['site'] = ['nb' => (int) $l['nb'], 'montant' => round((float) $l['s'], 2)];
    }
    $out['total'] = round($out['devis']['montant'] + $out['bons']['montant'] + $out['factures_mois']['montant'] + $out['site']['montant'], 2);

    // Avoirs : une facture du mois validée ou payée ne se recalcule plus ; les retours arrivés depuis se rendent par un avoir.
    if ($fm_ok) {
        $tva = factures_mensuelles_tva_incluse_column_ok() ? ', f.tva_incluse' : ', 0 AS tva_incluse';
        $retours_fm = $retours_ok
            ? "(SELECT COALESCE(SUM(r.total_ht_retour), 0) FROM facture_mensuelle_bl x INNER JOIN bons_retour r ON r.bl_id = x.bl_id WHERE x.facture_mensuelle_id = f.id$vivant_r)"
            : '0';
        $factures = compta_synthese_requete("SELECT f.numero_facture, f.total_ht$tva,
                (SELECT COALESCE(SUM(b.total_ht), 0) FROM facture_mensuelle_bl x INNER JOIN bons_livraison b ON b.id = x.bl_id WHERE x.facture_mensuelle_id = f.id) AS bons,
                $retours_fm AS retours
            FROM factures_mensuelles f
            WHERE f.statut IN ('validee', 'payee')" . compta_synthese_vivant('factures_mensuelles', 'f') . '
            ORDER BY f.id', []);
        foreach ($factures as $f) {
            $ecart = round((float) $f['total_ht'] - max(0.0, (float) $f['bons'] - (float) $f['retours']), 2);
            if ($ecart > 0.005) {
                $montant = !empty($f['tva_incluse']) ? round($ecart + round($ecart * fiscal_taux_tva_pourcent() / 100, 2), 2) : $ecart;
                $out['avoirs']['lignes'][] = ['document' => (string) $f['numero_facture'], 'nature' => 'Facture du mois', 'montant' => $montant];
            }
        }
    }
    if ($retours_ok && bl_tables_available() && compta_synthese_colonne_ok('bons_livraison', 'facture_bl_payee')) {
        $dans_facture = $fm_ok ? ' AND NOT EXISTS (SELECT 1 FROM facture_mensuelle_bl x WHERE x.bl_id = b.id)' : '';
        $bons = compta_synthese_requete('SELECT y.numero_bl, y.montant FROM (
                SELECT b.numero_bl, COALESCE(SUM(' . compta_synthese_sql_du_bon('b', 'r.total_ht_retour') . "), 0) AS montant
                FROM bons_livraison b INNER JOIN bons_retour r ON r.bl_id = b.id
                WHERE b.facture_bl_payee = 1" . compta_synthese_vivant('bons_livraison', 'b') . "$vivant_r$dans_facture
                GROUP BY b.id, b.numero_bl
            ) y WHERE y.montant > 0.005 ORDER BY y.numero_bl", []);
        foreach ($bons as $b) {
            $out['avoirs']['lignes'][] = ['document' => (string) $b['numero_bl'], 'nature' => 'Bon payé seul', 'montant' => round((float) $b['montant'], 2)];
        }
    }
    $out['avoirs']['nb'] = count($out['avoirs']['lignes']);
    $out['avoirs']['montant'] = round(array_sum(array_column($out['avoirs']['lignes'], 'montant')), 2);

    return $out;
}

/**
 * LA SYNTHÈSE D'UNE PÉRIODE : vendu, encaissé, dépensé, solde, et ce qui reste dû à ce jour.
 *
 * @return array<string, mixed>
 */
function compta_synthese_periode($date_debut, $date_fin)
{
    [$d1, $d2] = compta_synthese_dates($date_debut, $date_fin);
    $encaissements = compta_synthese_encaissements($d1, $d2);
    $depenses = compta_synthese_depenses($d1, $d2);
    return [
        'debut' => $d1,
        'fin' => $d2,
        'ventes' => compta_synthese_ventes($d1, $d2),
        'encaissements' => $encaissements,
        'depenses' => $depenses,
        'solde' => round($encaissements['total'] - $depenses['montant'], 2),
        'a_encaisser' => compta_synthese_a_encaisser(),
    ];
}
