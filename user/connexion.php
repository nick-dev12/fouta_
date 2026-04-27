<?php
/**
 * Page de connexion utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start();

// Redirection après connexion (page demandée ou index)
$redirect_after = isset($_POST['redirect']) ? trim($_POST['redirect']) : (isset($_GET['redirect']) ? trim($_GET['redirect']) : '');
if ($redirect_after && $redirect_after[0] !== '/') {
    $redirect_after = '/' . $redirect_after;
}
$redirect_url = (!empty($redirect_after) && strpos($redirect_after, '//') === false) ? $redirect_after : '/index.php';

// Si l'admin est déjà connecté, rediriger vers l'espace admin
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_email'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

// Si l'utilisateur est déjà connecté, rediriger
if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
    header('Location: ' . $redirect_url);
    exit;
}

// Traiter le formulaire de connexion (admin + user)
require_once __DIR__ . '/../controllers/controller_users.php';
$result = process_unified_login();

// Connexion admin : session + redirection vers l'espace admin
if (isset($result['success']) && $result['success'] && $result['type'] === 'admin' && $result['admin']) {
    $_SESSION['admin_id'] = $result['admin']['id'];
    $_SESSION['admin_nom'] = $result['admin']['nom'];
    $_SESSION['admin_prenom'] = $result['admin']['prenom'];
    $_SESSION['admin_email'] = $result['admin']['email'];
    $_SESSION['admin_statut'] = $result['admin']['statut'];
    $_SESSION['admin_role'] = normalize_admin_role($result['admin']['role'] ?? 'admin');

    require_once __DIR__ . '/../includes/post_login_welcome.php';
    $_SESSION['just_logged_in_target'] = post_login_sanitize_next_url('/admin/dashboard.php');
    header('Location: /post-login-welcome.php');
    exit;
}

// Connexion utilisateur : session + redirection
if (isset($result['success']) && $result['success'] && $result['type'] === 'user' && $result['user']) {
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user_nom'] = $result['user']['nom'];
    $_SESSION['user_prenom'] = $result['user']['prenom'];
    $_SESSION['user_email'] = $result['user']['email'];
    $_SESSION['user_telephone'] = $result['user']['telephone'];
    $_SESSION['user_statut'] = $result['user']['statut'];

    require_once __DIR__ . '/../includes/post_login_welcome.php';
    $_SESSION['just_logged_in_target'] = post_login_sanitize_next_url($redirect_url);
    header('Location: /post-login-welcome.php');
    exit;
}

// Afficher le message de succès d'inscription si présent
$inscription_success = '';
if (isset($_SESSION['inscription_success'])) {
    $inscription_success = $_SESSION['inscription_success'];
    unset($_SESSION['inscription_success']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Connexion - FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-corps);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        .auth-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 12px 30px;
            background: #ffffff;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            z-index: 100;
        }

        .auth-header .logo {
            display: inline-block;
        }

        .auth-header .logo img {
            height: 55px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
        }

        .auth-header .logo:hover {
            opacity: 0.9;
        }

        .auth-content {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding-top: 80px;
        }

        .container {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--couleur-dominante);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header .icon {
            width: 70px;
            height: 70px;
            background: var(--couleur-dominante);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--texte-clair);
            font-size: 30px;
        }

        .header h1 {
            color: var(--titres);
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
            font-family: var(--font-titres);
        }

        .header p {
            color: var(--texte-fonce);
            font-size: 14px;
            opacity: 0.85;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: var(--titres);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(229, 72, 138, 0.2);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            color: var(--texte-fonce);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 3px rgba(229, 72, 138, 0.15);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--couleur-dominante);
            font-size: 16px;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--couleur-dominante);
            font-size: 16px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--titres);
        }

        .input-wrapper.password-wrapper {
            position: relative;
        }

        .input-wrapper.password-wrapper input {
            padding-right: 45px;
        }

        .input-wrapper.password-wrapper .password-toggle {
            right: 15px;
        }

        .error-message {
            background: rgba(229, 72, 138, 0.1);
            border-left: 4px solid var(--couleur-dominante);
            color: var(--titres);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .success-message {
            background: rgba(32, 197, 199, 0.12);
            border-left: 4px solid var(--turquoise);
            color: var(--titres);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: var(--ombre-douce);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            background: rgba(229, 72, 138, 0.9);
        }

        .footer-text {
            text-align: center;
            margin-top: 25px;
            color: var(--texte-fonce);
            font-size: 14px;
            opacity: 0.85;
        }

        .footer-text a {
            color: var(--couleur-dominante);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
            margin-top: 3px;
            cursor: pointer;
            accent-color: var(--couleur-dominante);
        }

        .checkbox-group label {
            font-weight: normal;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.5;
            color: var(--texte-fonce);
        }

        .checkbox-group label a {
            color: var(--couleur-dominante);
            text-decoration: underline;
        }

        .checkbox-group label a:hover {
            color: var(--titres);
        }

        .forgot-password-link {
            margin-top: 8px;
            text-align: right;
        }

        .forgot-password-link a {
            color: var(--couleur-dominante);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .forgot-password-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <header class="auth-header">
        <a class="logo" href="/index.php">
            <img src="/image/logo-fpl.png" alt="FOUTA POIDS LOURDS">
        </a>
    </header>

    <div class="auth-content">
        <div class="container">
            <div class="header">
                <div class="icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <h1>Connexion</h1>
                <p>Accédez à votre compte</p>
            </div>

            <?php if (!empty($inscription_success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($inscription_success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <?php if (!empty($redirect_after)): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_after); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="votre@email.com" required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Mot de passe *</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Votre mot de passe" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="forgot-password-link">
                        <a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="accepte_conditions" name="accepte_conditions" value="1" required>
                    <label for="accepte_conditions">
                        J'accepte les <a href="/conditions-utilisation.php" target="_blank">conditions d'utilisation</a>
                        *
                    </label>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>

            <div class="footer-text">
                <p>Vous n'avez pas de compte ? <a href="inscription.php">Créer un compte</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
    <?php include __DIR__ . '/../includes/social_floating.php'; ?>
</body>

</html>