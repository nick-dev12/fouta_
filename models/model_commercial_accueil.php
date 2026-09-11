<?php
/**
 * LES FILES DE TRAVAIL D'UN COMMERCIAL — l'accueil du 10/09/2026
 * (admin/commercial/index.php).
 *
 * Quatre lectures, toutes limitées au compte connecté : ses tickets de caisse
 * pas encore encaissés, ses tickets du jour, ses devis dont aucune facture
 * n'est payée, ses bons de livraison restés en brouillon.
 *
 * AUCUNE de ces fonctions n'avale d'erreur. Un nom de colonne ou de table
 * absent doit se voir : c'est un catch silencieux qui avait masqué quatre des
 * six défauts bloquants trouvés en août. La page appelante décide quoi
 * afficher si la lecture échoue ; le test, lui, échoue.
 *
 * Programmation procédurale uniquement.
 */

require_once __DIR__ . '/../conn/conn.php';

/** Exécute une lecture et lève une exception lisible si la base la refuse. */
function commercial_lire($sql, array $params)
{
    global $db;
    $st = $db->prepare($sql);
    if ($st === false || $st->execute($params) === false) {
        $err = $st ? $st->errorInfo() : $db->errorInfo();
        throw new RuntimeException('Lecture des files commerciales refusée : ' . ($err[2] ?? 'erreur inconnue'));
    }
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ses tickets préparés à la caisse et pas encore encaissés, du plus récent au
 * plus ancien. La « référence » est le code que le client donne au caissier.
 */
function commercial_tickets_en_attente($admin_id)
{
    return commercial_lire(
        "SELECT v.id, v.numero_ticket, v.reference, v.montant_total, v.date_vente
         FROM caisse_ventes v
         WHERE v.sync_deleted_at IS NULL
           AND v.statut = 'en_attente'
           AND v.admin_id = :moi
         ORDER BY v.date_vente DESC, v.id DESC
         LIMIT 200",
        ['moi' => (int) $admin_id]
    );
}

/** Ses tickets du jour, encaissés ou non : nombre et montant. */
function commercial_tickets_du_jour($admin_id)
{
    $lignes = commercial_lire(
        "SELECT COUNT(*) AS n, COALESCE(SUM(v.montant_total), 0) AS total
         FROM caisse_ventes v
         WHERE v.sync_deleted_at IS NULL
           AND v.admin_id = :moi
           AND v.statut <> 'annule'
           AND v.date_vente >= CURDATE()
           AND v.date_vente < CURDATE() + INTERVAL 1 DAY",
        ['moi' => (int) $admin_id]
    );
    return ['n' => (int) $lignes[0]['n'], 'total' => (float) $lignes[0]['total']];
}

/**
 * Ses devis dont aucune facture n'est payée : la règle de la liste « Devis »
 * (get_devis_sans_facture_payee), limitée à ses propres devis. La dernière
 * facture émise, s'il y en a une, dit où en est le devis.
 */
function commercial_devis_ouverts($admin_id)
{
    return commercial_lire(
        "SELECT d.id, d.numero_devis, d.statut, d.client_nom, d.client_prenom, d.montant_total, d.date_creation,
                fd.numero_facture
         FROM devis d
         LEFT JOIN factures_devis fd
                ON fd.id = (SELECT MAX(f2.id) FROM factures_devis f2 WHERE f2.devis_id = d.id)
         WHERE d.sync_deleted_at IS NULL
           AND d.admin_createur_id = :moi
           AND NOT EXISTS (SELECT 1 FROM factures_devis f3 WHERE f3.devis_id = d.id AND f3.payee = 1)
         ORDER BY d.date_creation DESC, d.id DESC
         LIMIT 200",
        ['moi' => (int) $admin_id]
    );
}

/** Ses bons de livraison restés en brouillon (pas encore validés). */
function commercial_bl_brouillons($admin_id)
{
    return commercial_lire(
        "SELECT b.id, b.numero_bl, b.date_bl, b.total_ht, c.raison_sociale
         FROM bons_livraison b
         LEFT JOIN clients_b2b c ON c.id = b.client_b2b_id
         WHERE b.sync_deleted_at IS NULL
           AND b.statut = 'brouillon'
           AND b.admin_createur_id = :moi
         ORDER BY b.date_creation DESC, b.id DESC
         LIMIT 200",
        ['moi' => (int) $admin_id]
    );
}

/**
 * Ses factures de devis non payées, de la plus ancienne à la plus récente
 * (10/09/2026) : ce sont les relances à faire.
 */
function commercial_factures_a_relancer($admin_id)
{
    return commercial_lire(
        "SELECT f.id, f.numero_facture, f.date_facture, f.montant_total,
                d.id AS devis_id, d.numero_devis, d.client_nom, d.client_prenom, d.client_telephone,
                DATEDIFF(CURDATE(), f.date_facture) AS jours
         FROM factures_devis f
         INNER JOIN devis d ON d.id = f.devis_id
         WHERE COALESCE(f.payee, 0) = 0
           AND d.sync_deleted_at IS NULL
           AND d.admin_createur_id = :moi
         ORDER BY f.date_facture ASC, f.id ASC
         LIMIT 200",
        ['moi' => (int) $admin_id]
    );
}

/**
 * Ses devis envoyés restés sans réponse depuis au moins $jours jours
 * (10/09/2026). La date retenue est celle du dernier changement du devis,
 * c'est-à-dire son passage à « envoyé ».
 */
function commercial_devis_sans_reponse($admin_id, $jours = 7)
{
    return commercial_lire(
        "SELECT d.id, d.numero_devis, d.client_nom, d.client_prenom, d.client_telephone, d.montant_total,
                d.date_modification, DATEDIFF(CURDATE(), DATE(d.date_modification)) AS jours
         FROM devis d
         WHERE d.sync_deleted_at IS NULL
           AND d.statut = 'envoye'
           AND d.admin_createur_id = :moi
           AND d.date_modification <= NOW() - INTERVAL " . max(0, (int) $jours) . " DAY
         ORDER BY d.date_modification ASC
         LIMIT 200",
        ['moi' => (int) $admin_id]
    );
}

/**
 * SES VENTES DU MOIS, CHEMIN PAR CHEMIN (10/09/2026). Aucun écran ne montrait au
 * commercial général toutes ses ventes : la comptabilité ne lui est pas ouverte
 * et chaque chemin vit sur sa page. Quatre lignes : tickets encaissés, factures
 * de devis, bons de livraison validés, commandes du site payées.
 *
 * @return array<int, array{chemin:string, nombre:int, montant:float, detail:string}>
 */
function commercial_ventes_du_mois($admin_id)
{
    $moi = (int) $admin_id;
    $debut = date('Y-m-01');

    $caisse = commercial_lire(
        "SELECT COUNT(*) AS n, COALESCE(SUM(montant_total), 0) AS t FROM caisse_ventes
         WHERE sync_deleted_at IS NULL AND statut = 'paye' AND admin_id = :moi AND date_encaissement >= :debut",
        ['moi' => $moi, 'debut' => $debut]
    )[0];
    $devis = commercial_lire(
        "SELECT COUNT(*) AS n, COALESCE(SUM(f.montant_total), 0) AS t,
                COALESCE(SUM(CASE WHEN f.payee = 1 THEN 1 ELSE 0 END), 0) AS payees
         FROM factures_devis f INNER JOIN devis d ON d.id = f.devis_id
         WHERE d.admin_createur_id = :moi AND f.date_facture >= :debut",
        ['moi' => $moi, 'debut' => $debut]
    )[0];
    $bl = commercial_lire(
        "SELECT COUNT(*) AS n, COALESCE(SUM(total_ht), 0) AS t FROM bons_livraison
         WHERE sync_deleted_at IS NULL AND statut = 'valide' AND admin_createur_id = :moi AND date_bl >= :debut",
        ['moi' => $moi, 'debut' => $debut]
    )[0];

    $lignes = [
        ['chemin' => 'Caisse : tickets encaissés', 'nombre' => (int) $caisse['n'], 'montant' => (float) $caisse['t'], 'detail' => 'TTC, encaissés par le caissier ce mois-ci'],
        ['chemin' => 'Factures de devis', 'nombre' => (int) $devis['n'], 'montant' => (float) $devis['t'], 'detail' => (int) $devis['payees'] . ' payée(s)'],
        ['chemin' => 'Bons de livraison validés', 'nombre' => (int) $bl['n'], 'montant' => (float) $bl['t'], 'detail' => 'HT, avant retours'],
    ];

    // La colonne du dernier traitant n'existe pas sur toutes les bases : sans elle, pas de ligne.
    $colonne = commercial_lire(
        "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commandes' AND COLUMN_NAME = 'admin_dernier_traitement_id'",
        []
    )[0];
    if ((int) $colonne['n'] > 0) {
        $site = commercial_lire(
            "SELECT COUNT(*) AS n, COALESCE(SUM(montant_total), 0) AS t FROM commandes
             WHERE statut = 'paye' AND admin_dernier_traitement_id = :moi AND date_livraison >= :debut",
            ['moi' => $moi, 'debut' => $debut]
        )[0];
        $lignes[] = ['chemin' => 'Commandes du site payées', 'nombre' => (int) $site['n'], 'montant' => (float) $site['t'], 'detail' => 'paiement enregistré par vous'];
    }
    return $lignes;
}

/**
 * Ses retours clients préparés et pas encore validés par le caissier
 * (11/09/2026). Vide tant que la base n'a pas la table des retours.
 */
function commercial_retours_en_attente($admin_id)
{
    $table = commercial_lire(
        "SELECT COUNT(*) AS n FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caisse_retours'",
        []
    );
    if ((int) ($table[0]['n'] ?? 0) === 0) {
        return [];
    }
    return commercial_lire(
        "SELECT r.id, r.numero_retour, r.solution, r.especes_a_rendre, r.especes_a_recevoir, r.date_creation, v.numero_ticket
         FROM caisse_retours r
         INNER JOIN caisse_ventes v ON v.id = r.vente_id
         WHERE r.sync_deleted_at IS NULL
           AND r.statut = 'en_attente'
           AND r.admin_id = :moi
         ORDER BY r.date_creation DESC, r.id DESC
         LIMIT 100",
        ['moi' => (int) $admin_id]
    );
}

/**
 * Les pièces vendues en vente directe ces derniers jours qui n'ont toujours pas
 * de prix au catalogue (11/09/2026). Mesuré : 3 095 pièces actives sur 3 235
 * sans prix, et 49 lignes de ticket sur 57 vendues à un prix tapé à la main.
 * Le dernier prix pratiqué aide le vendeur à vendre au même prix d'une fois à
 * l'autre ; fixer le prix au catalogue reste le travail de la gestion du stock.
 */
function commercial_pieces_vendues_sans_prix($jours = 90, $limite = 10)
{
    return commercial_lire(
        "SELECT p.id, p.nom, p.identifiant_interne, COUNT(DISTINCT v.id) AS ventes,
                (SELECT l2.prix_unitaire FROM caisse_vente_lignes l2
                 INNER JOIN caisse_ventes v2 ON v2.id = l2.vente_id
                 WHERE l2.produit_id = p.id AND v2.statut <> 'annule' AND v2.sync_deleted_at IS NULL AND l2.sync_deleted_at IS NULL
                 ORDER BY v2.date_vente DESC, l2.id DESC LIMIT 1) AS dernier_prix,
                MAX(v.date_vente) AS derniere_vente
         FROM caisse_vente_lignes l
         INNER JOIN caisse_ventes v ON v.id = l.vente_id
         INNER JOIN produits p ON p.id = l.produit_id
         WHERE v.statut <> 'annule' AND v.sync_deleted_at IS NULL AND l.sync_deleted_at IS NULL
           AND p.sync_deleted_at IS NULL
           AND COALESCE(p.prix, 0) = 0 AND COALESCE(p.prix_promotion, 0) = 0
           AND v.date_vente >= CURDATE() - INTERVAL " . max(1, (int) $jours) . " DAY
         GROUP BY p.id, p.nom, p.identifiant_interne
         ORDER BY ventes DESC, derniere_vente DESC, p.id
         LIMIT " . max(1, min(50, (int) $limite)),
        []
    );
}

/**
 * Les pièces vendues ces derniers jours (vente directe encaissée ou bon de
 * livraison validé) qui sont en rupture ou à $seuil pièces ou moins
 * (11/09/2026) : le vendeur prévient le client avant de promettre la pièce.
 */
function commercial_pieces_vendues_presque_epuisees($seuil = 2, $jours = 90, $limite = 10)
{
    $depuis = 'CURDATE() - INTERVAL ' . max(1, (int) $jours) . ' DAY';
    return commercial_lire(
        "SELECT p.id, p.nom, p.identifiant_interne, p.stock, p.statut, MAX(x.moment) AS derniere_vente
         FROM produits p
         INNER JOIN (
             SELECT l.produit_id, COALESCE(v.date_encaissement, v.date_vente) AS moment
             FROM caisse_vente_lignes l
             INNER JOIN caisse_ventes v ON v.id = l.vente_id
             WHERE v.statut = 'paye' AND v.sync_deleted_at IS NULL AND l.sync_deleted_at IS NULL
               AND COALESCE(v.date_encaissement, v.date_vente) >= $depuis
             UNION ALL
             SELECT bl.produit_id, b.date_bl AS moment
             FROM bl_lignes bl
             INNER JOIN bons_livraison b ON b.id = bl.bl_id
             WHERE b.statut IN ('valide', 'paye') AND b.sync_deleted_at IS NULL AND b.date_bl >= $depuis
         ) x ON x.produit_id = p.id
         WHERE p.sync_deleted_at IS NULL AND (p.statut = 'rupture_stock' OR p.stock <= :seuil)
         GROUP BY p.id, p.nom, p.identifiant_interne, p.stock, p.statut
         ORDER BY p.stock ASC, derniere_vente DESC, p.id
         LIMIT " . max(1, min(50, (int) $limite)),
        ['seuil' => (int) $seuil]
    );
}
