<?php
/**
 * Modèle pour les variantes de produits
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère les variantes d'un produit
 * @param int $produit_id ID du produit
 * @return array Tableau des variantes
 */
function get_variantes_by_produit($produit_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM produits_variantes WHERE produit_id = :produit_id ORDER BY ordre ASC, id ASC");
        $stmt->execute(['produit_id' => $produit_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère une variante par ID
 * @param int $id ID de la variante
 * @return array|false
 */
function get_variante_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM produits_variantes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée une variante
 * @param array $data nom, produit_id, prix, prix_promotion, image, ordre
 * @return int|false ID de la variante ou false
 */
function create_variante($data) {
    global $db;
    try {
        $stmt = $db->prepare("
            INSERT INTO produits_variantes (produit_id, nom, prix, prix_promotion, image, ordre)
            VALUES (:produit_id, :nom, :prix, :prix_promotion, :image, :ordre)
        ");
        $stmt->execute([
            'produit_id' => $data['produit_id'],
            'nom' => $data['nom'],
            'prix' => $data['prix'],
            'prix_promotion' => $data['prix_promotion'] ?? null,
            'image' => $data['image'] ?? null,
            'ordre' => $data['ordre'] ?? 0
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour une variante
 * @param int $id ID de la variante
 * @param array $data nom, prix, prix_promotion, image, ordre
 * @return bool
 */
function update_variante($id, $data) {
    global $db;
    try {
        $stmt = $db->prepare("
            UPDATE produits_variantes SET
                nom = :nom, prix = :prix, prix_promotion = :prix_promotion,
                image = COALESCE(:image, image), ordre = :ordre
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'prix' => $data['prix'],
            'prix_promotion' => $data['prix_promotion'] ?? null,
            'image' => isset($data['image']) ? $data['image'] : null,
            'ordre' => $data['ordre'] ?? 0
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime une variante par ID
 * @param int $id
 * @return bool
 */
function delete_variante($id) {
    global $db;
    try {
        $stmt = $db->prepare("DELETE FROM produits_variantes WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime les variantes d'un produit
 * @param int $produit_id
 * @return bool
 */
function delete_variantes_by_produit($produit_id) {
    global $db;
    try {
        $stmt = $db->prepare("DELETE FROM produits_variantes WHERE produit_id = :produit_id");
        return $stmt->execute(['produit_id' => $produit_id]);
    } catch (PDOException $e) {
        return false;
    }
}
