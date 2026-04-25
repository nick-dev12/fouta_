<?php
/**
 * Modèle pour la gestion de la configuration de la section4
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère la configuration de la section4
 * @return array|false Les données de configuration ou False si non trouvé
 */
function get_section4_config() {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM section4_config ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si aucune configuration n'existe, retourner une configuration par défaut
        if (!$config) {
            return [
                'id' => 0,
                'titre' => 'Bienvenue au Sugar Paper',
                'texte' => 'Tous les produits a petit prix',
                'image_fond' => 'market.png',
                'statut' => 'actif',
                'date_modification' => date('Y-m-d H:i:s')
            ];
        }
        if (!isset($config['statut'])) {
            $config['statut'] = 'actif';
        }
        return $config;
    } catch (PDOException $e) {
        return [
            'id' => 0,
            'titre' => 'Bienvenue au Sugar Paper',
            'texte' => 'Tous les produits a petit prix',
            'image_fond' => 'market.png',
            'statut' => 'actif',
            'date_modification' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Vérifie si une colonne existe dans la table section4_config
 * @param string $column_name Nom de la colonne
 * @return bool
 */
function section4_has_column($column_name) {
    global $db;
    static $columns = null;
    if ($columns === null) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM section4_config");
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
        } catch (PDOException $e) {
            return false;
        }
    }
    return in_array($column_name, $columns);
}

/**
 * Met à jour la configuration de la section4
 * @param array $data Les données de configuration
 * @return array ['success' => bool, 'message' => string]
 */
function update_section4_config($data) {
    global $db;
    
    try {
        $existing = get_section4_config();
        $has_statut = section4_has_column('statut');
        
        $statut = isset($data['statut']) && in_array($data['statut'], ['actif', 'inactif']) ? $data['statut'] : 'actif';
        $titre = isset($data['titre']) ? trim($data['titre']) : '';
        $texte = isset($data['texte']) ? trim($data['texte']) : '';
        $image_fond = isset($data['image_fond']) ? $data['image_fond'] : null;

        if ($existing && isset($existing['id']) && $existing['id'] > 0) {
            $set_parts = ['titre = :titre', 'texte = :texte', 'image_fond = :image_fond', 'date_modification = NOW()'];
            $params = [
                'id' => $existing['id'],
                'titre' => $titre,
                'texte' => $texte,
                'image_fond' => $image_fond
            ];
            if ($has_statut) {
                $set_parts[] = 'statut = :statut';
                $params['statut'] = $statut;
            }
            $sql = "UPDATE section4_config SET " . implode(', ', $set_parts) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $ok = $stmt->execute($params);
            return $ok ? ['success' => true, 'message' => ''] : ['success' => false, 'message' => 'Échec de l\'exécution'];
        } else {
            $cols = ['titre', 'texte', 'image_fond', 'date_modification'];
            $placeholders = [':titre', ':texte', ':image_fond', 'NOW()'];
            $params = ['titre' => $titre, 'texte' => $texte, 'image_fond' => $image_fond];
            if ($has_statut) {
                $cols[] = 'statut';
                $placeholders[] = ':statut';
                $params['statut'] = $statut;
            }
            $sql = "INSERT INTO section4_config (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $db->prepare($sql);
            $ok = $stmt->execute($params);
            return $ok ? ['success' => true, 'message' => ''] : ['success' => false, 'message' => 'Échec de l\'exécution'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Supprime l'image de fond de la section4
 * @param string $image_name Le nom de l'image à supprimer
 * @return bool True en cas de succès, False sinon
 */
function delete_section4_image($image_name) {
    if (empty($image_name)) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/../upload/section4/';
    $image_path = $upload_dir . $image_name;
    
    if (file_exists($image_path)) {
        return unlink($image_path);
    }
    
    return false;
}

?>

