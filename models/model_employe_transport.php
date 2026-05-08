<?php
/**
 * Modèle — déductions prime de transport (fiches employés)
 */
require_once __DIR__ . '/../conn/conn.php';

function employe_transport_tables_disponibles() {
    global $db;
    try {
        $db->query('SELECT 1 FROM employe_prime_transport_retraits LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function employe_transport_retraits_list_for_employe($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0 || !employe_transport_tables_disponibles()) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT t.*, a.prenom AS admin_prenom, a.nom AS admin_nom, a.email AS admin_email
            FROM employe_prime_transport_retraits t
            LEFT JOIN admin a ON t.admin_id = a.id
            WHERE t.employe_id = :eid
            ORDER BY t.mois_paie DESC, t.date_creation DESC, t.id DESC
        ');
        $stmt->execute(['eid' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array{jours:int,montant:float}
 */
function employe_transport_retraits_totaux_mois($employe_id, $mois_paie) {
    global $db;
    $employe_id = (int) $employe_id;
    $mois_paie = trim((string) $mois_paie);
    if ($employe_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $mois_paie) || !employe_transport_tables_disponibles()) {
        return ['jours' => 0, 'montant' => 0.0];
    }
    try {
        $stmt = $db->prepare('
            SELECT COALESCE(SUM(nb_jours), 0) AS total_jours,
                   COALESCE(SUM(montant_deduit), 0) AS total_montant
            FROM employe_prime_transport_retraits
            WHERE employe_id = :eid AND mois_paie = :mois
        ');
        $stmt->execute(['eid' => $employe_id, 'mois' => $mois_paie]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'jours' => max(0, (int) ($row['total_jours'] ?? 0)),
            'montant' => max(0.0, round((float) ($row['total_montant'] ?? 0), 2)),
        ];
    } catch (PDOException $e) {
        return ['jours' => 0, 'montant' => 0.0];
    }
}

function employe_transport_retrait_insert($employe_id, array $row) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0 || !employe_transport_tables_disponibles()) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_prime_transport_retraits (
                employe_id, mois_paie, nb_jours, montant_deduit, commentaire, admin_id
            ) VALUES (
                :eid, :mois, :jours, :montant, :commentaire, :admin_id
            )
        ');
        $admin_id = isset($row['admin_id']) ? (int) $row['admin_id'] : 0;
        $admin_id = $admin_id > 0 ? $admin_id : null;
        $commentaire = isset($row['commentaire']) ? trim((string) $row['commentaire']) : '';
        $commentaire = $commentaire !== '' ? mb_substr($commentaire, 0, 500) : null;
        return $stmt->execute([
            'eid' => $employe_id,
            'mois' => (string) $row['mois_paie'],
            'jours' => (int) $row['nb_jours'],
            'montant' => round((float) $row['montant_deduit'], 2),
            'commentaire' => $commentaire,
            'admin_id' => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}
