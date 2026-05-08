<?php
/**
 * Modèle — prêts et remboursements (fiches employés)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return list<array<string, mixed>>
 */
function employe_prets_list_for_employe($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT p.*, a.prenom AS admin_prenom, a.nom AS admin_nom, a.email AS admin_email,
                COALESCE(r.somme, 0) AS montant_verse
            FROM employe_prets p
            LEFT JOIN admin a ON p.admin_id = a.id
            LEFT JOIN (
                SELECT pret_id, SUM(montant) AS somme
                FROM employe_pret_remboursements
                GROUP BY pret_id
            ) r ON r.pret_id = p.id
            WHERE p.employe_id = :eid
            ORDER BY p.date_octroi DESC, p.id DESC
        ');
        $stmt->execute(['eid' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Prêt appartenant à la fiche employé.
 * @return array<string, mixed>|false
 */
function employe_pret_get_by_id_for_employe($pret_id, $employe_id) {
    global $db;
    $pret_id = (int) $pret_id;
    $employe_id = (int) $employe_id;
    if ($pret_id <= 0 || $employe_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT p.*, COALESCE(r.somme, 0) AS montant_verse
            FROM employe_prets p
            LEFT JOIN (
                SELECT pret_id, SUM(montant) AS somme
                FROM employe_pret_remboursements
                GROUP BY pret_id
            ) r ON r.pret_id = p.id
            WHERE p.id = :pid AND p.employe_id = :eid
            LIMIT 1
        ');
        $stmt->execute(['pid' => $pret_id, 'eid' => $employe_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array<int, list<array<string, mixed>>>
 */
function employe_pret_remboursements_groupes_par_pret($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT r.*, a.prenom AS admin_prenom, a.nom AS admin_nom, a.email AS admin_email
            FROM employe_pret_remboursements r
            INNER JOIN employe_prets p ON p.id = r.pret_id
            LEFT JOIN admin a ON r.admin_id = a.id
            WHERE p.employe_id = :eid
            ORDER BY r.date_versement DESC, r.id DESC
        ');
        $stmt->execute(['eid' => $employe_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $row) {
        $pid = (int) ($row['pret_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        if (!isset($map[$pid])) {
            $map[$pid] = [];
        }
        $map[$pid][] = $row;
    }
    return $map;
}

function employe_pret_montant_verse_total($pret_id) {
    global $db;
    $pret_id = (int) $pret_id;
    if ($pret_id <= 0) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare('SELECT COALESCE(SUM(montant), 0) FROM employe_pret_remboursements WHERE pret_id = :pid');
        $stmt->execute(['pid' => $pret_id]);
        return round((float) $stmt->fetchColumn(), 2);
    } catch (PDOException $e) {
        return 0.0;
    }
}

function employe_pret_remboursement_insert($pret_id, array $row) {
    global $db;
    $pret_id = (int) $pret_id;
    if ($pret_id <= 0) {
        return false;
    }
    $admin_id = isset($row['admin_id']) ? (int) $row['admin_id'] : 0;
    $admin_id = $admin_id > 0 ? $admin_id : null;
    $commentaire = isset($row['commentaire']) ? trim((string) $row['commentaire']) : '';
    $commentaire = $commentaire === '' ? null : $commentaire;
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_pret_remboursements (pret_id, montant, date_versement, commentaire, admin_id)
            VALUES (:pret_id, :montant, :date_versement, :commentaire, :admin_id)
        ');
        return $stmt->execute([
            'pret_id'         => $pret_id,
            'montant'         => round((float) $row['montant'], 2),
            'date_versement'  => $row['date_versement'],
            'commentaire'     => $commentaire,
            'admin_id'        => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le statut du prêt si le cumul des versements couvre le montant (hors prêt annulé).
 */
function employe_pret_actualiser_statut_solde($pret_id) {
    global $db;
    $pret_id = (int) $pret_id;
    if ($pret_id <= 0) {
        return;
    }
    try {
        $stmt = $db->prepare('SELECT montant, statut FROM employe_prets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $pret_id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return;
        }
        if (($p['statut'] ?? '') === 'annule') {
            return;
        }
        $montant = round((float) ($p['montant'] ?? 0), 2);
        $verse = employe_pret_montant_verse_total($pret_id);
        if ($montant <= 0) {
            return;
        }
        if ($verse + 0.005 >= $montant) {
            $up = $db->prepare('UPDATE employe_prets SET statut = :st WHERE id = :id AND statut != \'annule\'');
            $up->execute(['st' => 'rembourse', 'id' => $pret_id]);
        } else {
            $up = $db->prepare('UPDATE employe_prets SET statut = :st WHERE id = :id AND statut = \'rembourse\'');
            $up->execute(['st' => 'en_cours', 'id' => $pret_id]);
        }
    } catch (PDOException $e) {
    }
}

/**
 * @param array{
 *   montant: float|string,
 *   date_octroi: string,
 *   date_fin_prevue: ?string,
 *   mensualite: ?float|string,
 *   motif: string,
 *   statut: string,
 *   commentaire: ?string,
 *   admin_id: ?int
 * } $row
 */
function employe_pret_insert($employe_id, array $row) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    $admin_id = isset($row['admin_id']) ? (int) $row['admin_id'] : 0;
    $admin_id = $admin_id > 0 ? $admin_id : null;
    $fin = isset($row['date_fin_prevue']) ? trim((string) $row['date_fin_prevue']) : '';
    $fin = $fin === '' ? null : $fin;
    $mens = $row['mensualite'] ?? null;
    if ($mens === null || $mens === '') {
        $mens = null;
    } else {
        $mens = round((float) $mens, 2);
    }
    $commentaire = isset($row['commentaire']) ? trim((string) $row['commentaire']) : '';
    $commentaire = $commentaire === '' ? null : $commentaire;
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_prets (
                employe_id, montant, date_octroi, date_fin_prevue, mensualite,
                motif, statut, commentaire, admin_id
            ) VALUES (
                :employe_id, :montant, :date_octroi, :date_fin_prevue, :mensualite,
                :motif, :statut, :commentaire, :admin_id
            )
        ');
        return $stmt->execute([
            'employe_id'      => $employe_id,
            'montant'         => round((float) $row['montant'], 2),
            'date_octroi'     => $row['date_octroi'],
            'date_fin_prevue' => $fin,
            'mensualite'      => $mens,
            'motif'           => $row['motif'],
            'statut'          => $row['statut'],
            'commentaire'     => $commentaire,
            'admin_id'        => $admin_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}
