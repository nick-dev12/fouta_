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
