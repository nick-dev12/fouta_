<?php
/**
 * Modèle — autorisations d’absence (fiches employés)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return list<array<string, mixed>>
 */
function employe_autorisations_absence_list_for_employe($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT t.*, a.prenom AS admin_prenom, a.nom AS admin_nom, a.email AS admin_email
            FROM employe_autorisations_absence t
            LEFT JOIN admin a ON t.admin_id = a.id
            WHERE t.employe_id = :eid
            ORDER BY t.date_debut DESC, t.id DESC
        ');
        $stmt->execute(['eid' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param array{date_debut:string, date_fin:string, motif:string, commentaire:?string, admin_id:?int} $row
 */
function employe_autorisation_absence_insert($employe_id, array $row) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    $admin_id = isset($row['admin_id']) ? (int) $row['admin_id'] : 0;
    $admin_id = $admin_id > 0 ? $admin_id : null;
    $commentaire = isset($row['commentaire']) ? trim((string) $row['commentaire']) : '';
    $commentaire = $commentaire === '' ? null : $commentaire;
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_autorisations_absence (
                employe_id, date_debut, date_fin, motif, commentaire, admin_id
            ) VALUES (
                :employe_id, :date_debut, :date_fin, :motif, :commentaire, :admin_id
            )
        ');
        return $stmt->execute([
            'employe_id'   => $employe_id,
            'date_debut'   => $row['date_debut'],
            'date_fin'     => $row['date_fin'],
            'motif'        => $row['motif'],
            'commentaire'  => $commentaire,
            'admin_id'     => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}
