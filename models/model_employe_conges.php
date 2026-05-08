<?php
/**
 * Modèle — congés employés (consommation annuelle)
 */
require_once __DIR__ . '/../conn/conn.php';

function employe_conges_table_disponible() {
    global $db;
    try {
        $db->query('SELECT 1 FROM employe_conges LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return list<array<string,mixed>>
 */
function employe_conges_list_for_employe($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0 || !employe_conges_table_disponible()) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT c.*, a.prenom AS admin_prenom, a.nom AS admin_nom, a.email AS admin_email
            FROM employe_conges c
            LEFT JOIN admin a ON c.admin_id = a.id
            WHERE c.employe_id = :eid
            ORDER BY c.mois_conge DESC, c.date_creation DESC, c.id DESC
        ');
        $stmt->execute(['eid' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array<string,int> annee => total jours
 */
function employe_conges_totaux_par_annee($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0 || !employe_conges_table_disponible()) {
        return [];
    }
    try {
        $stmt = $db->prepare("
            SELECT SUBSTRING(mois_conge, 1, 4) AS annee, COALESCE(SUM(nb_jours), 0) AS total_jours
            FROM employe_conges
            WHERE employe_id = :eid
            GROUP BY SUBSTRING(mois_conge, 1, 4)
        ");
        $stmt->execute(['eid' => $employe_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $annee = (string) ($r['annee'] ?? '');
            if (!preg_match('/^\d{4}$/', $annee)) {
                continue;
            }
            $out[$annee] = max(0, (int) ($r['total_jours'] ?? 0));
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

function employe_conges_insert($employe_id, array $row) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0 || !employe_conges_table_disponible()) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_conges (employe_id, mois_conge, nb_jours, notes, admin_id)
            VALUES (:eid, :mois, :jours, :notes, :admin_id)
        ');
        $admin_id = isset($row['admin_id']) ? (int) $row['admin_id'] : 0;
        $admin_id = $admin_id > 0 ? $admin_id : null;
        $notes = isset($row['notes']) ? trim((string) $row['notes']) : '';
        $notes = $notes !== '' ? mb_substr($notes, 0, 1000) : null;
        return $stmt->execute([
            'eid' => $employe_id,
            'mois' => (string) $row['mois_conge'],
            'jours' => (int) $row['nb_jours'],
            'notes' => $notes,
            'admin_id' => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}
