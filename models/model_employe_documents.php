<?php
/**
 * Documents RH associés à une fiche employé
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return array<int, array<string,mixed>>
 */
function employe_documents_list($employe_id) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('SELECT * FROM employe_documents WHERE employe_id = :eid ORDER BY date_creation DESC, id DESC');
        $stmt->execute(['eid' => $employe_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array<string,mixed>|false
 */
function employe_documents_get($document_id, $employe_id) {
    global $db;
    $document_id = (int) $document_id;
    $employe_id = (int) $employe_id;
    if ($document_id <= 0 || $employe_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM employe_documents WHERE id = :id AND employe_id = :eid LIMIT 1');
        $stmt->execute(['id' => $document_id, 'eid' => $employe_id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function employe_documents_insert($employe_id, $nature, $fichier_chemin, $mime_type = null) {
    global $db;
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return false;
    }
    $nature = mb_substr(trim((string) $nature), 0, 255);
    $fichier_chemin = trim((string) $fichier_chemin);
    if ($fichier_chemin === '') {
        return false;
    }
    try {
        $stmt = $db->prepare('
            INSERT INTO employe_documents (employe_id, nature, fichier_chemin, mime_type, date_creation)
            VALUES (:eid, :n, :f, :m, NOW())
        ');
        $ok = $stmt->execute([
            'eid' => $employe_id,
            'n' => $nature,
            'f' => $fichier_chemin,
            'm' => ($mime_type !== null && trim((string) $mime_type) !== '') ? trim($mime_type) : null,
        ]);
        return $ok ? (int) $db->lastInsertId() : false;
    } catch (PDOException $e) {
        return false;
    }
}

function employe_documents_delete($document_id, $employe_id) {
    global $db;
    $document_id = (int) $document_id;
    $employe_id = (int) $employe_id;
    if ($document_id <= 0 || $employe_id <= 0) {
        return false;
    }
    $row = employe_documents_get($document_id, $employe_id);
    if (!$row) {
        return false;
    }
    $rel = trim((string) ($row['fichier_chemin'] ?? ''));
    if ($rel !== '' && strpos($rel, '..') === false && strpos($rel, 'employes_documents/') === 0) {
        $full = __DIR__ . '/../upload/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($full)) {
            @unlink($full);
        }
    }
    try {
        $stmt = $db->prepare('DELETE FROM employe_documents WHERE id = :id AND employe_id = :eid');
        return $stmt->execute(['id' => $document_id, 'eid' => $employe_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime les fichiers sur disque pour un employé (avant suppression de la fiche employé).
 */
function employe_documents_delete_all_files_for_employe($employe_id) {
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return;
    }
    $dir = realpath(__DIR__ . '/../upload/employes_documents/');
    if (!$dir || !is_dir($dir)) {
        return;
    }
    $prefix = $dir . DIRECTORY_SEPARATOR . 'employe_' . $employe_id . '_';
    foreach (glob($prefix . '*') ?: [] as $g) {
        if (is_file($g)) {
            @unlink($g);
        }
    }
}
