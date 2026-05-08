<?php
/**
 * Modèle — absences : compte admin (subject_admin_id) OU fiche employés (employe_id exclusif)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * Vérifie s’il existe déjà une absence ce jour pour la même cible.
 */
function employe_absence_deja_sur_date($date_absence, $subject_admin_id, $employe_id) {
    global $db;
    $subject_admin_id = (int) $subject_admin_id;
    $employe_id = (int) $employe_id;
    if ($date_absence === '' || ($subject_admin_id <= 0 && $employe_id <= 0)) {
        return true;
    }
    try {
        if ($subject_admin_id > 0) {
            $stmt = $db->prepare('SELECT id FROM employe_absences WHERE date_absence = :d AND subject_admin_id = :sid LIMIT 1');
            $stmt->execute(['d' => $date_absence, 'sid' => $subject_admin_id]);
        } else {
            $stmt = $db->prepare('SELECT id FROM employe_absences WHERE date_absence = :d AND employe_id = :eid LIMIT 1');
            $stmt->execute(['d' => $date_absence, 'eid' => $employe_id]);
        }
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return true;
    }
}

/**
 * @return array<int,array<string,mixed>>
 */
function employe_absences_liste_recentes($limit = 80, $filter_subject_admin_or_employe = null) {
    global $db;
    $limit = max(1, min(500, (int) $limit));
    $base = '
        SELECT a.*,
            COALESCE(ad.prenom, e.prenom) AS employe_prenom,
            COALESCE(ad.nom, e.nom) AS employe_nom,
            CASE
                WHEN a.subject_admin_id IS NOT NULL THEN \'admin\'
                WHEN a.employe_id IS NOT NULL THEN \'employe_fiche\'
                ELSE \'\'
            END AS absence_source,
            j.id AS justif_id, j.texte AS justif_texte, j.fichier_chemin AS justif_fichier
        FROM employe_absences a
        LEFT JOIN admin ad ON ad.id = a.subject_admin_id
        LEFT JOIN employes e ON e.id = a.employe_id
        LEFT JOIN employe_absence_justificatifs j ON j.absence_id = a.id
    ';
    try {
        if (is_array($filter_subject_admin_or_employe)) {
            $sid = isset($filter_subject_admin_or_employe['admin']) ? (int) $filter_subject_admin_or_employe['admin'] : 0;
            $eid = isset($filter_subject_admin_or_employe['employe']) ? (int) $filter_subject_admin_or_employe['employe'] : 0;
            if ($sid > 0) {
                $stmt = $db->prepare($base . ' WHERE a.subject_admin_id = :sid ORDER BY a.date_absence DESC, a.id DESC LIMIT ' . $limit);
                $stmt->execute(['sid' => $sid]);
            } elseif ($eid > 0) {
                $stmt = $db->prepare($base . ' WHERE a.employe_id = :eid ORDER BY a.date_absence DESC, a.id DESC LIMIT ' . $limit);
                $stmt->execute(['eid' => $eid]);
            } else {
                $stmt = $db->query($base . ' ORDER BY a.date_absence DESC, a.id DESC LIMIT ' . $limit);
            }
        } else {
            $stmt = $db->query($base . '
                ORDER BY a.date_absence DESC, a.id DESC
                LIMIT ' . $limit
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Sans justificatif — compte admin (hors élégibilité métier déjà OK côté UI)
 */
function employe_absences_non_justifiees_pour_staff_admin($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT a.id, a.date_absence, a.motif
            FROM employe_absences a
            LEFT JOIN employe_absence_justificatifs j ON j.absence_id = a.id
            WHERE a.subject_admin_id = :aid AND j.id IS NULL
            ORDER BY a.date_absence DESC, a.id DESC
        ');
        $stmt->execute(['aid' => $admin_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Sans justificatif — fiche dans la table employes
 */
function employe_absences_non_justifiees_pour_fiche_employe($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT a.id, a.date_absence, a.motif
            FROM employe_absences a
            LEFT JOIN employe_absence_justificatifs j ON j.absence_id = a.id
            WHERE a.employe_id = :eid AND a.subject_admin_id IS NULL AND j.id IS NULL
            ORDER BY a.date_absence DESC, a.id DESC
        ');
        $stmt->execute(['eid' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function employe_absence_get_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT a.*,
                COALESCE(ad.prenom, e.prenom) AS employe_prenom,
                COALESCE(ad.nom, e.nom) AS employe_nom,
                CASE
                    WHEN a.subject_admin_id IS NOT NULL THEN \'admin\'
                    WHEN a.employe_id IS NOT NULL THEN \'employe_fiche\'
                    ELSE \'\'
                END AS absence_source
            FROM employe_absences a
            LEFT JOIN admin ad ON ad.id = a.subject_admin_id
            LEFT JOIN employes e ON e.id = a.employe_id
            WHERE a.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Absence pour un utilisateur avec compte admin (non rôle « admin », vérifié amont).
 * @return int|false id créé
 */
function employe_absence_creer_pour_staff_admin($subject_admin_id, $date_absence, $motif, $created_by_admin_id, $penalite_montant = 0.0) {
    global $db;
    $subject_admin_id = (int) $subject_admin_id;
    if ($subject_admin_id <= 0 || $date_absence === '' || trim($motif) === '') {
        return false;
    }
    if (employe_absence_deja_sur_date($date_absence, $subject_admin_id, 0)) {
        return false;
    }
    $admin_id = $created_by_admin_id ? (int) $created_by_admin_id : null;
    $pen = round(max(0, (float) $penalite_montant), 2);
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_absences (employe_id, subject_admin_id, date_absence, motif, penalite_montant, created_by_admin_id)
            VALUES (NULL, :sid, :d, :m, :pen, :aid)
        ');
        $stmt->execute([
            'sid' => $subject_admin_id,
            'd' => $date_absence,
            'm' => trim($motif),
            'pen' => $pen,
            'aid' => $admin_id ?: null,
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Absence pour une ligne de la table employes (sans compte obligatoire)
 * @return int|false
 */
function employe_absence_creer_pour_fiche_employe($employe_id, $date_absence, $motif, $created_by_admin_id, $penalite_montant = 0.0) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0 || $date_absence === '' || trim($motif) === '') {
        return false;
    }
    if (employe_absence_deja_sur_date($date_absence, 0, $employe_id)) {
        return false;
    }
    $creator = $created_by_admin_id ? (int) $created_by_admin_id : null;
    $pen = round(max(0, (float) $penalite_montant), 2);
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_absences (employe_id, subject_admin_id, date_absence, motif, penalite_montant, created_by_admin_id)
            VALUES (:eid, NULL, :d, :m, :pen, :aid)
        ');
        $stmt->execute([
            'eid' => $employe_id,
            'd' => $date_absence,
            'm' => trim($motif),
            'pen' => $pen,
            'aid' => $creator ?: null,
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function employe_absence_a_deja_justificatif($absence_id) {
    global $db;
    $absence_id = (int) $absence_id;
    if ($absence_id <= 0) {
        return true;
    }
    try {
        $stmt = $db->prepare('SELECT id FROM employe_absence_justificatifs WHERE absence_id = :id LIMIT 1');
        $stmt->execute(['id' => $absence_id]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return true;
    }
}

function employe_absence_justification_enregistrer($absence_id, $texte, $fichier_chemin, $fichier_nom_original, $fichier_mime, $created_by_admin_id) {
    global $db;
    $absence_id = (int) $absence_id;
    if ($absence_id <= 0) {
        return false;
    }
    $txt = $texte !== null && $texte !== '' ? trim($texte) : null;
    $path = $fichier_chemin !== null && $fichier_chemin !== '' ? trim($fichier_chemin) : null;
    if (($txt === null || $txt === '') && ($path === null || $path === '')) {
        return false;
    }
    $admin_id = $created_by_admin_id ? (int) $created_by_admin_id : null;
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_absence_justificatifs (absence_id, texte, fichier_chemin, fichier_nom_original, fichier_mime, created_by_admin_id)
            VALUES (:aid, :t, :p, :on, :mime, :cid)
        ');
        $stmt->execute([
            'aid' => $absence_id,
            't' => $txt,
            'p' => $path,
            'on' => $fichier_nom_original,
            'mime' => $fichier_mime,
            'cid' => $admin_id ?: null,
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Absences liées à la fiche employés uniquement (+ jointure justificatif).
 * @return array<int,array<string,mixed>>
 */
function employe_absences_detail_pour_fiche_employe($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT
                a.id AS absence_id,
                a.date_absence,
                a.motif,
                a.penalite_montant,
                a.penalite_retenir_salaire,
                a.penalite_deduite_bulletin_id,
                a.date_creation AS absence_creation,
                j.id AS justif_id,
                j.texte AS justif_texte,
                j.fichier_chemin AS justif_fichier_chemin,
                j.fichier_nom_original AS justif_nom_fichier,
                j.date_creation AS justif_creation
            FROM employe_absences a
            LEFT JOIN employe_absence_justificatifs j ON j.absence_id = a.id
            WHERE a.employe_id = :eid
              AND (a.subject_admin_id IS NULL OR a.subject_admin_id = 0)
            ORDER BY a.date_absence DESC, a.id DESC
        ');
        $stmt->execute(['eid' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Active la déduction de la pénalité au prochain bulletin (fiche employé uniquement).
 */
function employe_absence_marquer_retenir_penalite($absence_id, $employe_id) {
    global $db;
    $absence_id = (int) $absence_id;
    $employe_id = (int) $employe_id;
    if ($absence_id <= 0 || $employe_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            UPDATE employe_absences
            SET penalite_retenir_salaire = 1
            WHERE id = :aid
              AND employe_id = :eid
              AND (subject_admin_id IS NULL OR subject_admin_id = 0)
              AND penalite_deduite_bulletin_id IS NULL
        ');
        $stmt->execute(['aid' => $absence_id, 'eid' => $employe_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Pénalités marquées à retenir et pas encore appliquées à un bulletin.
 *
 * @param string|null $mois_ym Mois de paie (AAAA-MM) : ne retient que les absences dont `date_absence` tombe dans ce mois.
 * @return array{ids: list<int>, total: float, nb_jours: int, lignes: list<array{id:int, penalite_montant:float}>}
 */
function employe_absences_penalites_en_attente_pour_employe($employe_id, $mois_ym = null) {
    global $db;
    $employe_id = (int) $employe_id;
    $empty = ['ids' => [], 'total' => 0.0, 'nb_jours' => 0, 'lignes' => []];
    if ($employe_id <= 0) {
        return $empty;
    }
    $sql = '
            SELECT id, penalite_montant
            FROM employe_absences
            WHERE employe_id = :eid
              AND (subject_admin_id IS NULL OR subject_admin_id = 0)
              AND penalite_retenir_salaire = 1
              AND penalite_deduite_bulletin_id IS NULL
    ';
    $bind = ['eid' => $employe_id];
    if ($mois_ym !== null && $mois_ym !== '' && preg_match('/^\d{4}-\d{2}$/', (string) $mois_ym)) {
        $d0 = $mois_ym . '-01';
        $ts = strtotime($d0);
        $d1 = $ts !== false ? date('Y-m-t', $ts) : $d0;
        $sql .= ' AND date_absence >= :d0 AND date_absence <= :d1';
        $bind['d0'] = $d0;
        $bind['d1'] = $d1;
    }
    $sql .= ' ORDER BY date_absence ASC, id ASC';
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $ids = [];
        $total = 0.0;
        $lignes = [];
        foreach ($rows as $row) {
            $i = (int) ($row['id'] ?? 0);
            if ($i <= 0) {
                continue;
            }
            $p = round((float) ($row['penalite_montant'] ?? 0), 2);
            $ids[] = $i;
            if ($p > 0) {
                $total += $p;
            }
            $lignes[] = ['id' => $i, 'penalite_montant' => $p];
        }
        return [
            'ids' => $ids,
            'total' => round($total, 2),
            'nb_jours' => count($ids),
            'lignes' => $lignes,
        ];
    } catch (PDOException $e) {
        return $empty;
    }
}

/**
 * @param list<int> $absence_ids
 */
function employe_absences_marquer_penalites_deduites(array $absence_ids, $bulletin_id) {
    global $db;
    $bulletin_id = (int) $bulletin_id;
    $ids = array_values(array_unique(array_filter(array_map('intval', $absence_ids))));
    if ($bulletin_id <= 0 || $ids === []) {
        return;
    }
    try {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE employe_absences SET penalite_deduite_bulletin_id = ? WHERE id IN (' . $ph . ')';
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$bulletin_id], $ids));
    } catch (PDOException $e) {
    }
}
