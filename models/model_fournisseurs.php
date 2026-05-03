<?php
/**
 * Modèle fournisseurs (liste pour produits et paramètres)
 * Procédural uniquement
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return array<int, array{id:int,nom:string,date_creation:string}>
 */
function get_all_fournisseurs_ordered_by_nom()
{
    global $db;
    if (!$db) {
        return [];
    }
    try {
        $stmt = $db->query(
            'SELECT id, nom, date_creation FROM fournisseurs ORDER BY nom ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $id
 * @return array{id:int,nom:string,date_creation:string}|null
 */
function get_fournisseur_by_id($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !$db) {
        return null;
    }
    try {
        $stmt = $db->prepare(
            'SELECT id, nom, date_creation FROM fournisseurs WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Insère un fournisseur (nom unique).
 *
 * @return array{success:bool, message:string, id:int|null}
 */
function create_fournisseur_row($nom)
{
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
    try {
        $stmt = $db->prepare(
            'INSERT INTO fournisseurs (nom, date_creation) VALUES (:nom, NOW())'
        );
        $stmt->execute(['nom' => $nom]);
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
