<?php
/**
 * Modèle pour la gestion des produits visités
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Enregistre une visite d'un produit
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @return bool True en cas de succès, False sinon
 */
function add_visite($user_id, $produit_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO produits_visites (user_id, produit_id, date_visite) 
            VALUES (:user_id, :produit_id, NOW())
            ON DUPLICATE KEY UPDATE date_visite = NOW()
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
 * Récupère les produits visités par un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @param int $limit Nombre maximum de résultats (par défaut 20)
 * @return array|false Tableau des produits visités ou False en cas d'erreur
 */
function get_produits_visites_by_user($user_id, $limit = 20) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT pv.*, p.*, c.nom as categorie_nom
            FROM produits_visites pv
            INNER JOIN produits p ON pv.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE pv.user_id = :user_id
            ORDER BY pv.date_visite DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $visites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $visites ? $visites : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Compte le nombre de produits visités par un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return int Le nombre de produits visités
 */
function count_visites_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT produit_id) FROM produits_visites WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Récupère les produits les plus visités
 * @param int $limit Nombre minimum de produits requis (par défaut 10)
 * @return array Tableau des produits les plus visités ou tous les produits mélangés si moins de $limit
 */
function get_produits_plus_visites($limit = 10) {
    global $db;
    
    try {
        // Récupérer les produits les plus visités avec le nombre de visites
        $stmt = $db->prepare("
            SELECT 
                p.*,
                c.nom as categorie_nom,
                COUNT(pv.id) as nb_visites
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            LEFT JOIN produits_visites pv ON p.id = pv.produit_id
            WHERE p.statut = 'actif'
            GROUP BY p.id
            HAVING nb_visites > 0
            ORDER BY nb_visites DESC, p.date_creation DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':limit', $limit * 2, PDO::PARAM_INT); // Récupérer plus pour avoir de la variété
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si moins de $limit produits visités, récupérer tous les produits actifs
        if (count($produits) < $limit) {
            if (file_exists(__DIR__ . '/model_produits.php')) {
                require_once __DIR__ . '/model_produits.php';
                $tous_produits = get_all_produits('actif');
                
                if ($tous_produits !== false && !empty($tous_produits)) {
                    // Mélanger aléatoirement
                    mt_srand(time() + (int)(microtime(true) * 1000000));
                    shuffle($tous_produits);
                    // Limiter au nombre demandé
                    $produits = array_slice($tous_produits, 0, $limit);
                }
            }
        } else {
            // Mélanger aléatoirement les produits les plus visités
            mt_srand(time() + (int)(microtime(true) * 1000000));
            shuffle($produits);
            // Limiter au nombre demandé
            $produits = array_slice($produits, 0, $limit);
        }
        
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner tous les produits actifs mélangés
        if (file_exists(__DIR__ . '/model_produits.php')) {
            require_once __DIR__ . '/model_produits.php';
            $produits = get_all_produits('actif');
            if ($produits !== false && !empty($produits)) {
                mt_srand(time() + (int)(microtime(true) * 1000000));
                shuffle($produits);
                $produits = array_slice($produits, 0, $limit);
                return $produits;
            }
        }
        return [];
    }
}

?>

