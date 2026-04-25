<?php
/**
 * Modèle pour la gestion des catégories
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère toutes les catégories actives
 * @return array|false Tableau des catégories ou False en cas d'erreur
 */
function get_all_categories()
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM categories ORDER BY nom ASC");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $categories ? $categories : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère une catégorie par son ID
 * @param int $id L'ID de la catégorie
 * @return array|false Les données de la catégorie ou False si non trouvée
 */
function get_categorie_by_id($id)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);

        return $categorie ? $categorie : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une catégorie par son nom
 * @param string $nom Le nom de la catégorie
 * @return array|false Les données de la catégorie ou False si non trouvée
 */
function get_categorie_by_nom($nom)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM categories WHERE nom = :nom");
        $stmt->execute(['nom' => $nom]);
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);

        return $categorie ? $categorie : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée une nouvelle catégorie
 * @param string $nom Le nom de la catégorie
 * @param string $description La description
 * @param string|null $image Le chemin de l'image
 * @return int|false L'ID de la catégorie créée ou False en cas d'erreur
 */
function create_categorie($nom, $description = null, $image = null)
{
    global $db;

    try {
        $stmt = $db->prepare("
            INSERT INTO categories (nom, description, image, date_creation) 
            VALUES (:nom, :description, :image, NOW())
        ");

        $result = $stmt->execute([
            'nom' => $nom,
            'description' => $description,
            'image' => $image
        ]);

        if ($result) {
            return $db->lastInsertId();
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour une catégorie
 * @param int $id L'ID de la catégorie
 * @param string $nom Le nom de la catégorie
 * @param string $description La description
 * @param string|null $image Le chemin de l'image
 * @return bool True en cas de succès, False sinon
 */
function update_categorie($id, $nom, $description = null, $image = null)
{
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE categories SET
                nom = :nom,
                description = :description,
                image = :image
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'nom' => $nom,
            'description' => $description,
            'image' => $image
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime une catégorie
 * @param int $id L'ID de la catégorie
 * @return bool True en cas de succès, False sinon
 */
function delete_categorie($id)
{
    global $db;

    try {
        $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifie si une catégorie a des produits associés
 * @param int $categorie_id L'ID de la catégorie
 * @return bool True si la catégorie a des produits, False sinon
 */
function categorie_has_produits($categorie_id)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE categorie_id = :id");
        $stmt->execute(['id' => $categorie_id]);
        $count = $stmt->fetchColumn();

        return $count > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère toutes les catégories avec le nombre de produits
 * @return array Tableau des catégories avec le nombre de produits
 */
function get_all_categories_with_count()
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT c.*, COUNT(p.id) as nb_produits
            FROM categories c
            LEFT JOIN produits p ON c.id = p.categorie_id AND p.statut = 'actif'
            GROUP BY c.id
            ORDER BY c.nom ASC
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $categories ? $categories : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les catégories les plus populaires (basées sur les visites et commandes)
 * @param int $limit Nombre maximum de catégories à retourner (par défaut 5)
 * @return array Tableau des catégories les plus populaires mélangées aléatoirement
 */
function get_top_categories($limit = 5)
{
    global $db;

    try {
        // Récupérer les catégories avec le nombre de visites et de commandes
        $stmt = $db->prepare("
            SELECT 
                c.*,
                COALESCE(visites_stats.nb_visites, 0) as nb_visites,
                COALESCE(commandes_stats.nb_commandes, 0) as nb_commandes,
                (COALESCE(visites_stats.nb_visites, 0) + COALESCE(commandes_stats.nb_commandes, 0)) as score_popularite
            FROM categories c
            LEFT JOIN (
                SELECT p.categorie_id, COUNT(pv.id) as nb_visites
                FROM produits_visites pv
                INNER JOIN produits p ON pv.produit_id = p.id
                WHERE p.statut = 'actif'
                GROUP BY p.categorie_id
            ) visites_stats ON c.id = visites_stats.categorie_id
            LEFT JOIN (
                SELECT p.categorie_id, COUNT(cp.id) as nb_commandes
                FROM commande_produits cp
                INNER JOIN produits p ON cp.produit_id = p.id
                WHERE p.statut = 'actif'
                GROUP BY p.categorie_id
            ) commandes_stats ON c.id = commandes_stats.categorie_id
            HAVING score_popularite > 0
            ORDER BY score_popularite DESC, c.nom ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit * 2, PDO::PARAM_INT); // Récupérer plus pour avoir de la variété
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si aucune catégorie avec visites/commandes, récupérer toutes les catégories
        if (empty($categories)) {
            $categories = get_all_categories();
        }

        // Mélanger aléatoirement les catégories
        if (!empty($categories)) {
            mt_srand(time() + (int) (microtime(true) * 1000000));
            shuffle($categories);
            // Limiter au nombre demandé
            $categories = array_slice($categories, 0, $limit);
        }

        return $categories ? $categories : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner toutes les catégories mélangées
        $categories = get_all_categories();
        if (!empty($categories)) {
            mt_srand(time() + (int) (microtime(true) * 1000000));
            shuffle($categories);
            $categories = array_slice($categories, 0, $limit);
        }
        return $categories ? $categories : [];
    }
}

?>