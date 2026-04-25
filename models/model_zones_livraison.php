<?php
/**
 * Modèle pour la gestion des zones de livraison
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère toutes les zones de livraison actives
 * @param string|null $statut Filtrer par statut (actif, inactif) ou null pour toutes
 * @return array Tableau des zones
 */
function get_all_zones_livraison($statut = 'actif') {
    global $db;
    try {
        if ($statut) {
            $stmt = $db->prepare("
                SELECT * FROM zones_livraison 
                WHERE statut = :statut 
                ORDER BY ville ASC, quartier ASC
            ");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM zones_livraison 
                ORDER BY ville ASC, quartier ASC
            ");
            $stmt->execute();
        }
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $zones ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère une zone de livraison par son ID
 * @param int $id L'ID de la zone
 * @return array|false Les données de la zone ou False
 */
function get_zone_livraison_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM zones_livraison WHERE id = :id");
        $stmt->execute(['id' => (int) $id]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);
        return $zone ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifie si une zone existe (ville + quartier) en excluant un ID
 * @param string $ville
 * @param string $quartier
 * @param int|null $exclude_id ID à exclure (pour modification)
 * @return bool True si existe déjà
 */
function zone_livraison_exists($ville, $quartier, $exclude_id = null) {
    global $db;
    try {
        $sql = "SELECT id FROM zones_livraison WHERE ville = :ville AND quartier = :quartier";
        $params = ['ville' => trim($ville), 'quartier' => trim($quartier)];
        if ($exclude_id !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = (int) $exclude_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée une nouvelle zone de livraison
 * @param string $ville
 * @param string $quartier
 * @param float $prix_livraison
 * @param string|null $description
 * @return array Tableau avec 'success' et 'message' ou 'id'
 */
function create_zone_livraison($ville, $quartier, $prix_livraison, $description = null) {
    global $db;
    $ville = trim($ville);
    $quartier = trim($quartier);
    $prix_livraison = (float) $prix_livraison;
    if (empty($ville) || empty($quartier)) {
        return ['success' => false, 'message' => 'La ville et le quartier sont obligatoires.'];
    }
    if ($prix_livraison < 0) {
        return ['success' => false, 'message' => 'Le prix de livraison doit être positif ou nul.'];
    }
    if (zone_livraison_exists($ville, $quartier)) {
        return ['success' => false, 'message' => 'Cette zone (ville + quartier) existe déjà.'];
    }
    try {
        $stmt = $db->prepare("
            INSERT INTO zones_livraison (ville, quartier, prix_livraison, description, statut)
            VALUES (:ville, :quartier, :prix_livraison, :description, 'actif')
        ");
        $stmt->execute([
            'ville' => $ville,
            'quartier' => $quartier,
            'prix_livraison' => $prix_livraison,
            'description' => $description ? trim($description) : null
        ]);
        return ['success' => true, 'id' => (int) $db->lastInsertId(), 'message' => 'Zone ajoutée avec succès.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
    }
}

/**
 * Met à jour une zone de livraison
 * @param int $id
 * @param string $ville
 * @param string $quartier
 * @param float $prix_livraison
 * @param string|null $description
 * @param string $statut
 * @return array Tableau avec 'success' et 'message'
 */
function update_zone_livraison($id, $ville, $quartier, $prix_livraison, $description = null, $statut = 'actif') {
    global $db;
    $ville = trim($ville);
    $quartier = trim($quartier);
    $prix_livraison = (float) $prix_livraison;
    $id = (int) $id;
    if (empty($ville) || empty($quartier)) {
        return ['success' => false, 'message' => 'La ville et le quartier sont obligatoires.'];
    }
    if ($prix_livraison < 0) {
        return ['success' => false, 'message' => 'Le prix de livraison doit être positif ou nul.'];
    }
    if (zone_livraison_exists($ville, $quartier, $id)) {
        return ['success' => false, 'message' => 'Cette zone (ville + quartier) existe déjà.'];
    }
    try {
        $stmt = $db->prepare("
            UPDATE zones_livraison 
            SET ville = :ville, quartier = :quartier, prix_livraison = :prix_livraison, 
                description = :description, statut = :statut, date_modification = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'ville' => $ville,
            'quartier' => $quartier,
            'prix_livraison' => $prix_livraison,
            'description' => $description ? trim($description) : null,
            'statut' => in_array($statut, ['actif', 'inactif']) ? $statut : 'actif'
        ]);
        return ['success' => true, 'message' => 'Zone modifiée avec succès.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la modification.'];
    }
}

/**
 * Supprime une zone de livraison
 * @param int $id
 * @return array Tableau avec 'success' et 'message'
 */
function delete_zone_livraison($id) {
    global $db;
    $id = (int) $id;
    try {
        $stmt = $db->prepare("DELETE FROM zones_livraison WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Zone supprimée.'];
        }
        return ['success' => false, 'message' => 'Zone introuvable.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    }
}
