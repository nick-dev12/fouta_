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
        "SELECT d.id, d.numero_devis, d.client_nom, d.client_prenom, d.montant_total, d.date_creation,
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
