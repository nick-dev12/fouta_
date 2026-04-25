<?php
/**
 * Page de profil administrateur - Modification des informations
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

// Récupérer les informations de l'administrateur
require_once __DIR__ . '/../models/model_admin.php';
$admin = get_admin_by_id($_SESSION['admin_id']);

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Traitement des formulaires
$success_message = '';
$error_message = '';

// Récupérer le message de succès de la session (après redirection)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Traitement du formulaire d'informations personnelles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_profil'])) {
    // Récupération et nettoyage des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    $errors = [];
    
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
    } else {
        // Vérifier si l'email existe déjà pour un autre administrateur
        $existing_admin = get_admin_by_email($email);
        if ($existing_admin && $existing_admin['id'] != $_SESSION['admin_id']) {
            $errors[] = 'Cet email est déjà utilisé par un autre administrateur.';
        }
    }
    
    // Si aucune erreur, procéder à la mise à jour
    if (empty($errors)) {
        // Préparer les données à mettre à jour
        $data = [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email
        ];

        // Mettre à jour les informations de base
        if (update_admin($_SESSION['admin_id'], $data)) {
            // Mettre à jour l'email dans la session
            $_SESSION['admin_email'] = $email;

            // Redirection pour éviter la double soumission (pattern Post-Redirect-Get)
            $_SESSION['success_message'] = 'Vos informations personnelles ont été mises à jour avec succès !';
            header('Location: profil.php');
            exit;
        } else {
            $error_message = 'Une erreur est survenue lors de la mise à jour. Veuillez réessayer.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_mot_de_passe'])) {
    // Récupération et nettoyage des données (protection XSS)
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    
    $errors = [];
    
    // Validation du mot de passe actuel
    if (empty($current_password)) {
        $errors[] = 'Le mot de passe actuel est obligatoire.';
    } else {
        // Vérifier que le mot de passe actuel est correct
        if (!password_verify($current_password, $admin['password'])) {
            $errors[] = 'Le mot de passe actuel est incorrect.';
        }
    }
    
    // Validation du nouveau mot de passe
    if (empty($password)) {
        $errors[] = 'Le nouveau mot de passe est obligatoire.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password === $current_password) {
        $errors[] = 'Le nouveau mot de passe doit être différent de l\'ancien.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
    }
    
    // Si aucune erreur, procéder à la mise à jour
    if (empty($errors)) {
        require_once __DIR__ . '/../conn/conn.php';
        global $db;
        
        // Protection contre les injections SQL : utilisation de PDO avec prepared statements
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE admin SET password = :password WHERE id = :id");
        
        if ($stmt->execute([
            'id' => $_SESSION['admin_id'],
            'password' => $password_hash
        ])) {
            // Redirection pour éviter la double soumission (pattern Post-Redirect-Get)
            $_SESSION['success_message'] = 'Votre mot de passe a été modifié avec succès !';
            header('Location: profil.php');
            exit;
        } else {
            $error_message = 'Une erreur est survenue lors de la modification du mot de passe. Veuillez réessayer.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Administration FOUTA POIDS LOURDS</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-user-shield"></i> Mon Profil</h1>
    </div>

    <section class="content-section">
        <div class="profil-container">
            <!-- En-tête du profil -->
            <div class="profil-header">
                <div class="avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h2><?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></h2>
                <p><?php echo htmlspecialchars($admin['email']); ?></p>
            </div>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Informations du compte -->
            <div class="info-section">
                <h3><i class="fas fa-info-circle"></i> Informations du compte</h3>
                <div class="info-item">
                    <label>Date de création:</label>
                    <span class="value"><?php echo date('d/m/Y', strtotime($admin['date_creation'])); ?></span>
                </div>
                <?php if ($admin['derniere_connexion']): ?>
                    <div class="info-item">
                        <label>Dernière connexion:</label>
                        <span class="value"><?php echo date('d/m/Y à H:i', strtotime($admin['derniere_connexion'])); ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <label>Statut:</label>
                    <span class="value statut-<?php echo $admin['statut']; ?>">
                        <?php echo ucfirst($admin['statut']); ?>
                    </span>
                </div>
            </div>

            <!-- Section Informations personnelles -->
            <form method="POST" action="" class="profil-form">
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Informations personnelles</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom <span class="required">*</span></label>
                            <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($admin['nom']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="prenom">Prénom <span class="required">*</span></label>
                            <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($admin['prenom']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="modifier_profil" class="btn-submit">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="dashboard.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </div>
            </form>

            <!-- Section Sécurité -->
            <form method="POST" action="" class="profil-form">
                <div class="form-section security-section">
                    <h3><i class="fas fa-lock"></i> Sécurité</h3>

                    <div class="form-group">
                        <label for="current_password">Mot de passe actuel <span class="required">*</span></label>
                        <input type="password" id="current_password" name="current_password" placeholder="Entrez votre mot de passe actuel" required autocomplete="current-password">
                        <div class="help-text">Vous devez confirmer votre mot de passe actuel pour le modifier</div>
                    </div>

                    <div class="form-group">
                        <label for="password">Nouveau mot de passe <span class="required">*</span></label>
                        <input type="password" id="password" name="password" placeholder="Entrez votre nouveau mot de passe" required autocomplete="new-password">
                        <div class="help-text">Minimum 6 caractères</div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirmer le nouveau mot de passe <span class="required">*</span></label>
                        <input type="password" id="password_confirm" name="password_confirm" placeholder="Confirmez votre nouveau mot de passe" required autocomplete="new-password">
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="modifier_mot_de_passe" class="btn-submit">
                            <i class="fas fa-key"></i> Changer le mot de passe
                        </button>
                        <a href="dashboard.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

