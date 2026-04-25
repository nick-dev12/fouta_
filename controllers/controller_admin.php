<?php
/**
 * Contrôleur pour la gestion des administrateurs
 * Programmation procédurale uniquement
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/site_url.php';

/**
 * Traite l'inscription d'un nouvel administrateur
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_admin_inscription() {
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
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    
    // Validation du nom
    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/u', $nom)) {
        $errors[] = 'Le nom contient des caractères invalides.';
    }
    
    // Validation du prénom
    if (empty($prenom)) {
        $errors[] = 'Le prénom est obligatoire.';
    } elseif (strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/u', $prenom)) {
        $errors[] = 'Le prénom contient des caractères invalides.';
    }
    
    // Validation de l'email
    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    } elseif (admin_email_exists($email)) {
        $errors[] = 'Cet email est déjà utilisé.';
    }
    
    // Validation du mot de passe
    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }
    
    // Validation de la confirmation du mot de passe
    if (empty($password_confirm)) {
        $errors[] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    
    // Rôle (voir model_admin admin_roles_valides())
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'gestion_stock';
    if (!in_array($role, admin_roles_valides(), true)) {
        $role = 'gestion_stock';
    }

    // Si un admin est connecté, il doit avoir le rôle admin pour ajouter des comptes
    $admin_connecte = isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
    if ($admin_connecte && ($_SESSION['admin_role'] ?? '') !== 'admin') {
        $errors[] = 'Vous n\'avez pas les droits pour ajouter des comptes.';
    }

    // Si aucune erreur, procéder à l'inscription
    if (empty($errors)) {
        // Hashage du mot de passe
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Premier compte = toujours administrateur ; sinon rôle choisi (comptes, commercial, compta, RH…)
        $role_final = $role;
        if (!admin_exists()) {
            $role_final = 'admin';
        }

        // Création de l'administrateur
        $admin_id = create_admin($nom, $prenom, $email, $password_hash, $role_final);

        if ($admin_id) {
            $success = true;
            if ($admin_connecte) {
                $message = 'Compte ajouté avec succès !';
            } else {
                $message = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
            }
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
 * Traite la connexion d'un administrateur
 * @return array Tableau avec 'success' (bool), 'message' (string) et 'admin' (array|false)
 */
function process_admin_login() {
    $errors = [];
    $success = false;
    $message = '';
    $admin = false;
    
    // Vérifier si le formulaire a été soumis
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'admin' => false];
    }
    
    // Récupération des données
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
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
    
    // Si aucune erreur de validation, vérifier les identifiants
    if (empty($errors)) {
        // Récupérer l'administrateur par email
        $admin = get_admin_by_email($email);
        
        if ($admin) {
            // Vérifier le statut
            if ($admin['statut'] !== 'actif') {
                $errors[] = 'Votre compte est désactivé. Contactez l\'administrateur.';
            } elseif (password_verify($password, $admin['password'])) {
                // Mot de passe correct
                $success = true;
                $message = 'Connexion réussie !';
                
                // Mettre à jour la dernière connexion
                update_admin_last_login($admin['id']);
            } else {
                $errors[] = 'Email ou mot de passe incorrect.';
            }
        } else {
            $errors[] = 'Email ou mot de passe incorrect.';
        }
    }
    
    // Retourner le résultat
    if ($success) {
        return ['success' => true, 'message' => $message, 'admin' => $admin];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message, 'admin' => false];
    }
}

/**
 * Traite la demande de réinitialisation de mot de passe (mot de passe oublié)
 * @return array Tableau avec 'success', 'message', 'email', 'reset_link', 'token' (pour EmailJS)
 */
function process_forgot_password() {
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
    } elseif (!admin_email_exists($email)) {
        // Pour des raisons de sécurité, on affiche le même message que si l'email existait
        $success = true;
        $message = 'Si cet email est associé à un compte admin, vous recevrez un lien de réinitialisation.';
        return ['success' => $success, 'message' => $message, 'email' => '', 'reset_link' => '', 'token' => ''];
    }

    if (empty($errors)) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));

        if (create_password_reset_token($email, $token, $expires_at)) {
            $base_url = get_site_base_url();
            $reset_link = rtrim($base_url, '/') . '/admin/reinitialiser-mot-de-passe.php?token=' . $token;

            if (function_exists('mail_send_reset_link')) {
                $mail_result = mail_send_reset_link($email, $reset_link, 'admin');
                if (!$mail_result['success']) {
                    $message = 'Le lien a été généré mais l\'envoi de l\'email a échoué : ' . ($mail_result['error'] ?? 'Erreur inconnue');
                    return ['success' => false, 'message' => $message, 'email' => '', 'reset_link' => '', 'token' => ''];
                }
            }

            $success = true;
            $message = 'Si cet email est associé à un compte admin, vous recevrez un lien de réinitialisation.';
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
 * Traite la réinitialisation du mot de passe (nouveau mot de passe)
 * @return array Tableau avec 'success', 'message'
 */
function process_reset_password() {
    $errors = [];
    $success = false;
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

    if (empty($token)) {
        $errors[] = 'Token invalide ou manquant.';
    } else {
        $token_data = get_valid_reset_token($token);
        if (!$token_data) {
            $errors[] = 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez faire une nouvelle demande.';
        }
    }

    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }

    if (empty($password_confirm)) {
        $errors[] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    if (empty($errors) && isset($token_data)) {
        $admin = get_admin_by_email($token_data['email']);
        if ($admin) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            if (update_admin_password($admin['id'], $password_hash) && mark_reset_token_used($token)) {
                $success = true;
                $message = 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.';
            } else {
                $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
            }
        } else {
            $errors[] = 'Compte administrateur introuvable.';
        }
    }

    if (!$success && !empty($errors)) {
        $message = implode('<br>', $errors);
    }

    return ['success' => $success, 'message' => $message];
}

?>

