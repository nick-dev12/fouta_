<?php
/**
 * Contrôleur pour la gestion des utilisateurs
 * Programmation procédurale uniquement
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
require_once __DIR__ . '/../models/model_users.php';
require_once __DIR__ . '/../models/model_admin.php';

/**
 * Connexion unifiée : vérifie admin puis users.
 * Permet aux admins de se connecter depuis la page user/connexion.php.
 * @return array ['success' => bool, 'message' => string, 'type' => 'admin'|'user'|null, 'admin' => array|null, 'user' => array|null]
 */
function process_unified_login() {
    $errors = [];
    $success = false;
    $message = '';
    $type = null;
    $admin = null;
    $user = null;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'type' => null, 'admin' => null, 'user' => null];
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $accepte_conditions = isset($_POST['accepte_conditions']) && $_POST['accepte_conditions'] == '1';

    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    }
    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors), 'type' => null, 'admin' => null, 'user' => null];
    }

    // 1. Vérifier d'abord la table admin
    $admin = get_admin_by_email($email);
    if ($admin && $admin['statut'] === 'actif' && password_verify($password, $admin['password'])) {
        update_admin_last_login($admin['id']);
        return ['success' => true, 'message' => 'Connexion réussie !', 'type' => 'admin', 'admin' => $admin, 'user' => null];
    }

    // 2. Sinon vérifier la table users
    $user = get_user_by_email($email);
    if ($user) {
        if ($user['statut'] !== 'actif') {
            $errors[] = 'Votre compte est désactivé. Contactez le support.';
        } elseif (!$accepte_conditions) {
            $errors[] = 'Vous devez accepter les conditions d\'utilisation pour vous connecter.';
        } elseif (password_verify($password, $user['password'])) {
            if ($accepte_conditions) {
                update_user_accepte_conditions($user['id'], true);
            }
            return ['success' => true, 'message' => 'Connexion réussie !', 'type' => 'user', 'admin' => null, 'user' => $user];
        } else {
            $errors[] = 'Email ou mot de passe incorrect.';
        }
    } else {
        $errors[] = 'Email ou mot de passe incorrect.';
    }

    $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
    return ['success' => false, 'message' => $message, 'type' => null, 'admin' => null, 'user' => null];
}

/**
 * Traite l'inscription d'un nouvel utilisateur
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_user_inscription() {
    $errors = [];
    $success = false;
    $message = '';
    
    // Vérifier si le formulaire a été soumis
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupération et validation des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    
    // Validation du nom
    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }
    
    // Validation du prénom
    if (empty($prenom)) {
        $errors[] = 'Le prénom est obligatoire.';
    } elseif (strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    }
    
    // Validation de l'email
    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    } elseif (user_email_exists($email)) {
        $errors[] = 'Cet email est déjà utilisé.';
    }
    
    // Validation du téléphone
    if (empty($telephone)) {
        $errors[] = 'Le téléphone est obligatoire.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $telephone)) {
        $errors[] = 'Le format du téléphone n\'est pas valide.';
    }
    
    // Validation du mot de passe
    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
    }
    
    // Validation de la confirmation du mot de passe
    if (empty($password_confirm)) {
        $errors[] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    
    // Si aucune erreur, procéder à l'inscription
    if (empty($errors)) {
        // Hashage du mot de passe
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Création de l'utilisateur
        $user_id = create_user($nom, $prenom, $email, $telephone, $password_hash);
        
        if ($user_id) {
            $success = true;
            $message = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.';
        }
    }
    
    // Retourner le résultat
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la connexion d'un utilisateur
 * @return array Tableau avec 'success' (bool), 'message' (string) et 'user' (array|false)
 */
function process_user_login() {
    $errors = [];
    $success = false;
    $message = '';
    $user = false;
    
    // Vérifier si le formulaire a été soumis
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'user' => false];
    }
    
    // Récupération des données
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $accepte_conditions = isset($_POST['accepte_conditions']) && $_POST['accepte_conditions'] == '1';
    
    // Validation de l'email
    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    }
    
    // Validation du mot de passe
    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    }
    
    // Validation de l'acceptation des conditions
    if (!$accepte_conditions) {
        $errors[] = 'Vous devez accepter les conditions d\'utilisation pour vous connecter.';
    }
    
    // Si aucune erreur de validation, vérifier les identifiants
    if (empty($errors)) {
        // Récupérer l'utilisateur par email
        $user = get_user_by_email($email);
        
        if ($user) {
            // Vérifier le statut
            if ($user['statut'] !== 'actif') {
                $errors[] = 'Votre compte est désactivé. Contactez le support.';
            } elseif (password_verify($password, $user['password'])) {
                // Mot de passe correct
                $success = true;
                $message = 'Connexion réussie !';
                
                // Mettre à jour l'acceptation des conditions d'utilisation
                if ($accepte_conditions) {
                    update_user_accepte_conditions($user['id'], true);
                }
            } else {
                $errors[] = 'Email ou mot de passe incorrect.';
            }
        } else {
            $errors[] = 'Email ou mot de passe incorrect.';
        }
    }
    
    // Retourner le résultat
    if ($success) {
        return ['success' => true, 'message' => $message, 'user' => $user];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message, 'user' => false];
    }
}

/**
 * Traite la demande de réinitialisation de mot de passe (mot de passe oublié) - Clients
 * @return array Tableau avec 'success', 'message', 'email', 'reset_link', 'token'
 */
function process_user_forgot_password() {
    $errors = [];
    $success = false;
    $message = '';
    $email = '';
    $reset_link = '';
    $token = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'email' => '', 'reset_link' => '', 'token' => ''];
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    } elseif (!user_email_exists($email)) {
        $success = true;
        $message = 'Si cet email est associé à un compte, vous recevrez un lien de réinitialisation.';
        return ['success' => $success, 'message' => $message, 'email' => '', 'reset_link' => '', 'token' => ''];
    }

    if (empty($errors)) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));

        if (create_user_password_reset_token($email, $token, $expires_at)) {
            require_once __DIR__ . '/../includes/site_url.php';
            $base_url = get_site_base_url();
            $reset_link = $base_url . '/user/reinitialiser-mot-de-passe.php?token=' . $token;

            if (function_exists('mail_send_reset_link')) {
                $mail_result = mail_send_reset_link($email, $reset_link, 'user');
                if (!$mail_result['success']) {
                    $message = 'Le lien a été généré mais l\'envoi de l\'email a échoué : ' . ($mail_result['error'] ?? 'Erreur inconnue');
                    return ['success' => false, 'message' => $message, 'email' => '', 'reset_link' => '', 'token' => ''];
                }
            }

            $success = true;
            $message = 'Si cet email est associé à un compte, vous recevrez un lien de réinitialisation.';
        } else {
            $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }

    if (!$success && !empty($errors)) {
        $message = implode('<br>', $errors);
    }

    return [
        'success' => $success,
        'message' => $message,
        'email' => $email,
        'reset_link' => $reset_link,
        'token' => $token
    ];
}

/**
 * Traite la réinitialisation du mot de passe (nouveau mot de passe) - Clients
 * @return array Tableau avec 'success', 'message'
 */
function process_user_reset_password() {
    $errors = [];
    $success = false;
    $message = '';
    $token_data = null;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

    if (empty($token)) {
        $errors[] = 'Token invalide ou manquant.';
    } else {
        $token_data = get_valid_user_reset_token($token);
        if (!$token_data) {
            $errors[] = 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez faire une nouvelle demande.';
        }
    }

    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
    }

    if (empty($password_confirm)) {
        $errors[] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    if (empty($errors) && $token_data) {
        $user = get_user_by_email($token_data['email']);
        if ($user) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            if (update_user_password($user['id'], $password_hash) && mark_user_reset_token_used($token)) {
                $success = true;
                $message = 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.';
            } else {
                $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
            }
        } else {
            $errors[] = 'Compte introuvable.';
        }
    }

    if (!$success && !empty($errors)) {
        $message = implode('<br>', $errors);
    }

    return ['success' => $success, 'message' => $message];
}

?>

