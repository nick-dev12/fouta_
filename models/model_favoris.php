<?php
/**
 * Modèle pour la gestion des favoris
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Ajoute un produit aux favoris
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @return bool True en cas de succès, False sinon
 */
function add_favori($user_id, $produit_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO favoris (user_id, produit_id, date_ajout) 
            VALUES (:user_id, :produit_id, NOW())
            ON DUPLICATE KEY UPDATE date_ajout = NOW()
        ");
        
        return $stmt->execute([
            'user_id' => $user_id,
            'produit_id' => $produit_id
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un produit des favoris
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @return bool True en cas de succès, False sinon
 */
function remove_favori($user_id, $produit_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM favoris WHERE user_id = :user_id AND produit_id = :produit_id");
        return $stmt->execute([
            'user_id' => $user_id,
            'produit_id' => $produit_id
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifie si un produit est dans les favoris
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @return bool True si favori, False sinon
 */
function is_favori($user_id, $produit_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM favoris WHERE user_id = :user_id AND produit_id = :produit_id");
        $stmt->execute([
            'user_id' => $user_id,
            'produit_id' => $produit_id
        ]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère tous les favoris d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return array|false Tableau des favoris ou False en cas d'erreur
 */
function get_favoris_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT f.*, p.*, c.nom as categorie_nom
            FROM favoris f
            INNER JOIN produits p ON f.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE f.user_id = :user_id
            ORDER BY f.date_ajout DESC
        ");
        
        $stmt->execute(['user_id' => $user_id]);
        $favoris = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $favoris ? $favoris : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Compte le nombre de favoris d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return int Le nombre de favoris
 */
function count_favoris_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM favoris WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

?>

