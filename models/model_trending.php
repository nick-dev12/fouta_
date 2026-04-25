<?php
/**
 * Modèle pour la gestion de la configuration de la section trending
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère la configuration de la section trending
 * @return array|false Les données de configuration ou False si non trouvé
 */
function get_trending_config() {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM trending_config ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si aucune configuration n'existe, retourner une configuration par défaut
        if (!$config) {
            return [
                'id' => 0,
                'label' => 'categories',
                'titre' => 'Enhance Your Music Experience',
                'bouton_texte' => 'Buy Now!',
                'bouton_lien' => '#',
                'image' => 'speaker.png',
                'date_modification' => date('Y-m-d H:i:s')
            ];
        }
        
        return $config;
    } catch (PDOException $e) {
        // En cas d'erreur, retourner une configuration par défaut
        return [
            'id' => 0,
            'label' => 'categories',
            'titre' => 'Enhance Your Music Experience',
            'bouton_texte' => 'Buy Now!',
            'bouton_lien' => '#',
            'image' => 'speaker.png',
            'date_modification' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Met à jour la configuration de la section trending
 * @param array $data Les données de configuration
 * @return bool True en cas de succès, False sinon
 */
function update_trending_config($data) {
    global $db;
    
    try {
        // Vérifier si une configuration existe déjà
        $existing = get_trending_config();
        
        if ($existing && isset($existing['id']) && $existing['id'] > 0) {
            // Mettre à jour la configuration existante
            $stmt = $db->prepare("
                UPDATE trending_config 
                SET label = :label, 
                    titre = :titre, 
                    bouton_texte = :bouton_texte,
                    bouton_lien = :bouton_lien,
                    image = :image,
                    date_modification = NOW()
                WHERE id = :id
            ");
            
            return $stmt->execute([
                'id' => $existing['id'],
                'label' => $data['label'],
                'titre' => $data['titre'],
                'bouton_texte' => $data['bouton_texte'],
                'bouton_lien' => $data['bouton_lien'] ?? '#',
                'image' => $data['image'] ?? null
            ]);
        } else {
            // Créer une nouvelle configuration
            $stmt = $db->prepare("
                INSERT INTO trending_config (label, titre, bouton_texte, bouton_lien, image, date_modification) 
                VALUES (:label, :titre, :bouton_texte, :bouton_lien, :image, NOW())
            ");
            
            return $stmt->execute([
                'label' => $data['label'],
                'titre' => $data['titre'],
                'bouton_texte' => $data['bouton_texte'],
                'bouton_lien' => $data['bouton_lien'] ?? '#',
                'image' => $data['image'] ?? null
            ]);
        }
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime l'image de la section trending
 * @param string $image_name Le nom de l'image à supprimer
 * @return bool True en cas de succès, False sinon
 */
function delete_trending_image($image_name) {
    if (empty($image_name)) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/../upload/trending/';
    $image_path = $upload_dir . $image_name;
    
    if (file_exists($image_path)) {
        return unlink($image_path);
    }
    
    return false;
}

?>

