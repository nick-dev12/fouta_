<?php
/**
 * Modèle — sanctions / discipline (fiches employés)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return list<array<string, mixed>>
 */
function employe_sanctions_list_for_employe($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT s.*, a.prenom AS admin_prenom, a.nom AS admin_nom, a.email AS admin_email
            FROM employe_sanctions s
            LEFT JOIN admin a ON s.admin_id = a.id
            WHERE s.employe_id = :eid
            ORDER BY s.date_constat DESC, s.id DESC
        ');
        $stmt->execute(['eid' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param array{type_sanction:string, motif:string, mesure:string, commentaire:?string, date_constat:string, admin_id:int} $row
 */
function employe_sanction_insert($employe_id, array $row) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    $admin_id = isset($row['admin_id']) ? (int) $row['admin_id'] : 0;
    if ($admin_id <= 0) {
        $admin_id = null;
    }
    $commentaire = isset($row['commentaire']) ? trim((string) $row['commentaire']) : '';
    $commentaire = $commentaire === '' ? null : $commentaire;
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_sanctions (
                employe_id, date_constat, type_sanction, motif, mesure, commentaire, admin_id
            ) VALUES (
                :employe_id, :date_constat, :type_sanction, :motif, :mesure, :commentaire, :admin_id
            )
        ');
        return $stmt->execute([
            'employe_id'     => $employe_id,
            'date_constat'   => $row['date_constat'],
            'type_sanction'  => $row['type_sanction'],
            'motif'          => $row['motif'],
            'mesure'         => $row['mesure'],
            'commentaire'    => $commentaire,
            'admin_id'       => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}
