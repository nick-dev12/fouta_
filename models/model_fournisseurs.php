<?php
/**
 * Modèle fournisseurs (liste pour produits et paramètres)
 * Procédural uniquement
 */
require_once __DIR__ . '/../conn/conn.php';

function fournisseurs_contact_columns_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT telephone, email FROM fournisseurs LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @return array<int, array<string, mixed>>
 */
function get_all_fournisseurs_ordered_by_nom() {
    global $db;
    if (!$db) {
        return [];
    }
    try {
        if (fournisseurs_contact_columns_ok()) {
            $stmt = $db->query(
                'SELECT id, nom, telephone, email, date_creation FROM fournisseurs ORDER BY nom ASC'
            );
        } else {
            $stmt = $db->query(
                'SELECT id, nom, date_creation FROM fournisseurs ORDER BY nom ASC'
            );
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array<string, mixed>|null
 */
function get_fournisseur_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !$db) {
        return null;
    }
    try {
        if (fournisseurs_contact_columns_ok()) {
            $stmt = $db->prepare(
                'SELECT id, nom, telephone, email, date_creation FROM fournisseurs WHERE id = ? LIMIT 1'
            );
        } else {
            $stmt = $db->prepare(
                'SELECT id, nom, date_creation FROM fournisseurs WHERE id = ? LIMIT 1'
            );
        }
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param string|null $telephone
 * @param string|null $email
 * @return array{success:bool, message:string, id:int|null}
 */
function create_fournisseur_row($nom, $telephone = null, $email = null) {
    global $db;
    $nom = trim((string) $nom);
    if ($nom === '') {
        return ['success' => false, 'message' => 'Le nom du fournisseur est obligatoire.', 'id' => null];
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($nom, 'UTF-8') > 255) {
            $nom = mb_substr($nom, 0, 255, 'UTF-8');
        }
    } elseif (strlen($nom) > 255) {
        $nom = substr($nom, 0, 255);
    }

    $tel = $telephone !== null ? trim((string) $telephone) : '';
    if (function_exists('mb_strlen') && mb_strlen($tel, 'UTF-8') > 40) {
        $tel = mb_substr($tel, 0, 40, 'UTF-8');
    } elseif (strlen($tel) > 40) {
        $tel = substr($tel, 0, 40);
    }
    $tel = $tel !== '' ? $tel : null;

    $em = $email !== null ? trim((string) $email) : '';
    if ($em !== '' && strlen($em) > 255) {
        $em = substr($em, 0, 255);
    }
    if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Adresse e-mail invalide.', 'id' => null];
    }
    $em = $em !== '' ? $em : null;

    try {
        if (fournisseurs_contact_columns_ok()) {
            $stmt = $db->prepare(
                'INSERT INTO fournisseurs (nom, telephone, email, date_creation) VALUES (:nom, :tel, :email, NOW())'
            );
            $stmt->execute(['nom' => $nom, 'tel' => $tel, 'email' => $em]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO fournisseurs (nom, date_creation) VALUES (:nom, NOW())'
            );
            $stmt->execute(['nom' => $nom]);
        }
        return [
            'success' => true,
            'message' => 'Fournisseur enregistré.',
            'id' => (int) $db->lastInsertId(),
        ];
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000 || stripos($e->getMessage(), 'Duplicate') !== false) {
            return [
                'success' => false,
                'message' => 'Ce nom de fournisseur existe déjà.',
                'id' => null,
            ];
        }
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement.', 'id' => null];
    }
}

/**
 * @return array{success:bool, message:string}
 */
function update_fournisseur_row($id, $nom, $telephone = null, $email = null) {
    global $db;
    $id = (int) $id;
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Fournisseur invalide.'];
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return ['success' => false, 'message' => 'Le nom du fournisseur est obligatoire.'];
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($nom, 'UTF-8') > 255) {
            $nom = mb_substr($nom, 0, 255, 'UTF-8');
        }
    } elseif (strlen($nom) > 255) {
        $nom = substr($nom, 0, 255);
    }

    $tel = $telephone !== null ? trim((string) $telephone) : '';
    if (function_exists('mb_strlen') && mb_strlen($tel, 'UTF-8') > 40) {
        $tel = mb_substr($tel, 0, 40, 'UTF-8');
    } elseif (strlen($tel) > 40) {
        $tel = substr($tel, 0, 40);
    }
    $tel = $tel !== '' ? $tel : null;

    $em = $email !== null ? trim((string) $email) : '';
    if ($em !== '' && strlen($em) > 255) {
        $em = substr($em, 0, 255);
    }
    if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Adresse e-mail invalide.'];
    }
    $em = $em !== '' ? $em : null;

    try {
        if (fournisseurs_contact_columns_ok()) {
            $stmt = $db->prepare(
                'UPDATE fournisseurs SET nom = :nom, telephone = :tel, email = :email WHERE id = :id'
            );
            $stmt->execute(['nom' => $nom, 'tel' => $tel, 'email' => $em, 'id' => $id]);
        } else {
            $stmt = $db->prepare('UPDATE fournisseurs SET nom = :nom WHERE id = :id');
            $stmt->execute(['nom' => $nom, 'id' => $id]);
        }
        if ($stmt->rowCount() === 0) {
            $chk = get_fournisseur_by_id($id);
            if (!$chk) {
                return ['success' => false, 'message' => 'Fournisseur introuvable.'];
            }
        }
        return ['success' => true, 'message' => 'Fournisseur mis à jour.'];
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000 || stripos($e->getMessage(), 'Duplicate') !== false) {
            return ['success' => false, 'message' => 'Ce nom de fournisseur existe déjà.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }
}
