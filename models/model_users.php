<?php
/**
 * Modèle pour la gestion des utilisateurs
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Vérifie si un utilisateur existe déjà avec cet email
 * @param string $email L'email à vérifier
 * @return bool True si l'email existe, False sinon
 */
function user_email_exists($email) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $count = $stmt->fetchColumn();
        
        return $count > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un utilisateur par son email
 * @param string $email L'email de l'utilisateur
 * @return array|false Les données de l'utilisateur ou False si non trouvé
 */
function get_user_by_email($email) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ? $user : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un utilisateur par son ID
 * @param int $id L'ID de l'utilisateur
 * @return array|false Les données de l'utilisateur ou False si non trouvé
 */
function get_user_by_id($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ? $user : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un nouvel utilisateur
 * @param string $nom Le nom de l'utilisateur
 * @param string $prenom Le prénom de l'utilisateur
 * @param string $email L'email de l'utilisateur
 * @param string $telephone Le téléphone de l'utilisateur
 * @param string $password_hash Le mot de passe hashé
 * @return int|false L'ID de l'utilisateur créé ou False en cas d'erreur
 */
function create_user($nom, $prenom, $email, $telephone, $password_hash) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO users (nom, prenom, email, telephone, password, date_creation, statut) 
            VALUES (:nom, :prenom, :email, :telephone, :password, NOW(), 'actif')
        ");
        
        $result = $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'password' => $password_hash
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
 * Met à jour les informations d'un utilisateur
 * @param int $id L'ID de l'utilisateur
 * @param array $data Les nouvelles données
 * @return bool True en cas de succès, False sinon
 */
function update_user($id, $data) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE users SET
                nom = :nom,
                prenom = :prenom,
                email = :email,
                telephone = :telephone
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone']
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère tous les utilisateurs avec leurs statistiques de commandes
 * @return array Tableau des utilisateurs avec leurs statistiques
 */
function get_all_users_with_stats() {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.nom,
                u.prenom,
                u.email,
                u.telephone,
                u.date_creation,
                u.statut,
                COUNT(DISTINCT c.id) as nb_commandes,
                COUNT(DISTINCT CASE WHEN c.statut = 'livree' THEN c.id END) as nb_commandes_livrees,
                COALESCE(SUM(CASE WHEN c.id IS NOT NULL AND c.statut <> 'annulee' THEN c.montant_total ELSE 0 END), 0) AS ca_total_ht
            FROM users u
            LEFT JOIN commandes c ON u.id = c.user_id
            GROUP BY u.id
            ORDER BY nb_commandes DESC, nb_commandes_livrees DESC, u.date_creation DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $users ? $users : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Met à jour le statut d'un utilisateur (actif/inactif)
 * @param int $id L'ID de l'utilisateur
 * @param string $statut Le nouveau statut ('actif' ou 'inactif')
 * @return bool True en cas de succès, False sinon
 */
function update_user_statut($id, $statut) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE users SET statut = :statut WHERE id = :id");
        return $stmt->execute([
            'id' => $id,
            'statut' => $statut
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour l'acceptation des conditions d'utilisation par un utilisateur
 * @param int $id L'ID de l'utilisateur
 * @param bool $accepte True si accepté, False sinon
 * @return bool True en cas de succès, False sinon
 */
function update_user_accepte_conditions($id, $accepte = true) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE users SET accepte_conditions = :accepte WHERE id = :id");
        return $stmt->execute([
            'id' => $id,
            'accepte' => $accepte ? 1 : 0
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le mot de passe d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @param string $password_hash Le nouveau mot de passe hashé
 * @return bool True en cas de succès, False sinon
 */
function update_user_password($user_id, $password_hash) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
        return $stmt->execute(['id' => $user_id, 'password' => $password_hash]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un token de réinitialisation de mot de passe pour un client
 * @param string $email L'email du client
 * @param string $token Le token généré
 * @param string $expires_at Date d'expiration (format DATETIME)
 * @return bool True en cas de succès, False sinon
 */
function create_user_password_reset_token($email, $token, $expires_at) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM user_password_reset WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        $stmt = $db->prepare("
            INSERT INTO user_password_reset (email, token, expires_at) 
            VALUES (:email, :token, :expires_at)
        ");
        return $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires_at
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un token de réinitialisation valide pour un client
 * @param string $token Le token à vérifier
 * @return array|false Les données du token ou False si invalide/expiré
 */
function get_valid_user_reset_token($token) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM user_password_reset 
            WHERE token = :token AND used = 0 AND expires_at > NOW()
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Marque un token client comme utilisé
 * @param string $token Le token à marquer
 * @return bool True en cas de succès, False sinon
 */
function mark_user_reset_token_used($token) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE user_password_reset SET used = 1 WHERE token = :token");
        return $stmt->execute(['token' => $token]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Totaux commandes boutique pour la fiche client (hors annulées pour le CA)
 * @return array{nb_commandes: int, ca_total_ht: float}
 */
function get_user_stats_commandes_boutique($user_id) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) AS nb_commandes,
                COALESCE(SUM(CASE WHEN statut <> 'annulee' THEN montant_total ELSE 0 END), 0) AS ca_total_ht
            FROM commandes 
            WHERE user_id = :uid
        ");
        $stmt->execute(['uid' => (int) $user_id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'nb_commandes' => (int) ($r['nb_commandes'] ?? 0),
            'ca_total_ht' => (float) ($r['ca_total_ht'] ?? 0),
        ];
    } catch (PDOException $e) {
        return ['nb_commandes' => 0, 'ca_total_ht' => 0.0];
    }
}

?>

