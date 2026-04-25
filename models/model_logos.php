<?php
/**
 * Modèle pour la gestion des logos partenaires
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère tous les logos actifs
 * @param string|null $statut Filtrer par statut ('actif', 'inactif' ou null pour tous)
 * @return array Tableau des logos
 */
function get_all_logos($statut = 'actif') {
    global $db;
    try {
        if ($statut) {
            $stmt = $db->prepare("
                SELECT * FROM logos 
                WHERE statut = :statut 
                ORDER BY ordre ASC, date_creation DESC
            ");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM logos 
                ORDER BY ordre ASC, date_creation DESC
            ");
            $stmt->execute();
        }
        $logos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $logos ? $logos : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un logo par son ID
 * @param int $id L'ID du logo
 * @return array|false Les données du logo ou False
 */
function get_logo_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM logos WHERE id = :id");
        $stmt->execute(['id' => (int) $id]);
        $logo = $stmt->fetch(PDO::FETCH_ASSOC);
        return $logo ? $logo : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un nouveau logo
 * @param string $image Chemin de l'image (ex: logos/xxx.png)
 * @param int $ordre Ordre d'affichage
 * @param string $statut actif ou inactif
 * @return int|false ID du logo créé ou False
 */
function create_logo($image, $ordre = 0, $statut = 'actif') {
    global $db;
    try {
        $stmt = $db->prepare("
            INSERT INTO logos (image, ordre, statut, date_creation) 
            VALUES (:image, :ordre, :statut, NOW())
        ");
        $stmt->execute([
            'image' => $image,
            'ordre' => (int) $ordre,
            'statut' => in_array($statut, ['actif', 'inactif']) ? $statut : 'actif'
        ]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour un logo
 * @param int $id ID du logo
 * @param string|null $image Nouveau chemin image (null = ne pas modifier)
 * @param int|null $ordre Nouvel ordre (null = ne pas modifier)
 * @param string|null $statut Nouveau statut (null = ne pas modifier)
 * @return bool
 */
function update_logo($id, $image = null, $ordre = null, $statut = null) {
    global $db;
    try {
        $sets = [];
        $params = ['id' => (int) $id];
        if ($image !== null) {
            $sets[] = 'image = :image';
            $params['image'] = $image;
        }
        if ($ordre !== null) {
            $sets[] = 'ordre = :ordre';
            $params['ordre'] = (int) $ordre;
        }
        if ($statut !== null && in_array($statut, ['actif', 'inactif'])) {
            $sets[] = 'statut = :statut';
            $params['statut'] = $statut;
        }
        if (empty($sets)) {
            return true;
        }
        $sql = "UPDATE logos SET " . implode(', ', $sets) . ", date_modification = NOW() WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un logo
 * @param int $id ID du logo
 * @return bool
 */
function delete_logo($id) {
    global $db;
    try {
        $stmt = $db->prepare("DELETE FROM logos WHERE id = :id");
        return $stmt->execute(['id' => (int) $id]);
    } catch (PDOException $e) {
        return false;
    }
}
