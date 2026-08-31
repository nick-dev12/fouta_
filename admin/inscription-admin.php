<?php
session_start();

require_once __DIR__ . '/../models/model_admin.php';

// Si des admins existent : seul un admin connecté avec rôle admin peut ajouter des comptes
if (admin_exists()) {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
        header('Location: login.php');
        exit;
    }
    $ar = $_SESSION['admin_role'] ?? '';
    if ($ar !== 'admin' && $ar !== 'informaticien' && $ar !== 'developpeur') {
        header('Location: dashboard.php');
        exit;
    }
}

// Traiter le formulaire
require_once __DIR__ . '/../controllers/controller_admin.php';
$result = process_admin_inscription();

// Si l'inscription est réussie, rediriger
if (isset($result['success']) && $result['success']) {
    if (isset($_SESSION['admin_id'])) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: comptes/index.php');
    } else {
        $_SESSION['inscription_success'] = $result['message'];
        header('Location: login.php');
    }
    exit;
}

$is_ajout_par_admin = admin_exists() && isset($_SESSION['admin_id']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php require_once __DIR__ . '/../includes/fpl_assets.php'; ?>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Administrateur - FOUTA POIDS LOURDS</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php fpl_css_link('variables.css'); ?>
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
            max-width: 500px;
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

        .form-group input::placeholder {
            color: rgba(61, 40, 0, 0.5);
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

        .input-wrapper.password-wrapper input {
            padding-right: 45px;
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
            background: rgba(229, 72, 138, 0.9);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            color: var(--texte-clair);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .password-requirements {
            background: rgba(255, 255, 255, 0.95);
            padding: 12px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 12px;
            color: var(--titres);
            border: 1px solid rgba(229, 72, 138, 0.2);
        }

        .password-requirements ul {
            list-style: none;
            padding-left: 0;
            margin: 5px 0 0 0;
        }

        .password-requirements li {
            margin: 5px 0;
            padding-left: 20px;
            position: relative;
        }

        .password-requirements li::before {
            content: '•';
            position: absolute;
            left: 0;
            color: var(--couleur-dominante);
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

        .form-group-role {
            background: rgba(229, 72, 138, 0.06);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(229, 72, 138, 0.2);
        }

        .select-role {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(229, 72, 138, 0.2);
            border-radius: 8px;
            font-size: 15px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--texte-fonce);
        }

        .select-role:focus {
            outline: none;
            border-color: var(--couleur-dominante);
        }

        .role-help {
            font-size: 12px;
            color: var(--texte-fonce);
            margin-top: 10px;
            line-height: 1.5;
            opacity: 0.9;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .header .icon {
                width: 60px;
                height: 60px;
                font-size: 25px;
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
                    <i class="fas fa-user-shield"></i>
                </div>
                <h1><?php echo $is_ajout_par_admin ? 'Ajouter un compte' : 'Inscription Administrateur'; ?></h1>
                <p><?php echo $is_ajout_par_admin ? 'Créez un nouveau compte pour la plateforme' : 'Créez le compte administrateur principal'; ?>
                </p>
            </div>

            <?php if (isset($result['message']) && !empty($result['message'])): ?>
                <?php if (isset($result['success']) && $result['success']): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($result['message']); ?>
                    </div>
                <?php else: ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="" id="inscriptionForm">
                <div class="form-group">
                    <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                    <input type="text" id="nom" name="nom" placeholder="Votre nom" required
                        value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="prenom"><i class="fas fa-user"></i> Prénom *</label>
                    <input type="text" id="prenom" name="prenom" placeholder="Votre prénom" required
                        value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="votre@email.com" required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <?php if ($is_ajout_par_admin): ?>
                    <div class="form-group form-group-role">
                        <label for="role"><i class="fas fa-user-tag"></i> Rôle *</label>
                        <select id="role" name="role" required class="select-role">
                            <?php
                            $role_post = $_POST['role'] ?? 'gestion_stock';
                            // Un seul contexte technique (31/08) : « Développeur » n'est plus proposé.
                            foreach (admin_roles_proposes() as $r):
                                ?>
                                <option value="<?php echo htmlspecialchars($r); ?>" <?php echo ($role_post === $r) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(admin_role_label($r)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="role-help">
                            <strong>Administrateur :</strong> tout l’espace admin.<br>
                            <strong>Gestion des stocks :</strong> produits, catégories et stock uniquement.<br>
                            <strong>Gestion des stocks générale :</strong> périmètre stocks étendu (tableau de bord, catégories complètes, entrepôt, alertes).<br>
                            <strong>Commercial :</strong> devis, commandes et caisse.<br>
                            <strong>Comptabilité :</strong> comptabilité et historique des ventes.<br>
                            <strong>RH :</strong> contacts, clients (comptes site), comptes d’accès internes.
                        </p>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Mot de passe *</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Votre mot de passe" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-requirements">
                        <strong>Le mot de passe doit contenir :</strong>
                        <ul>
                            <li>Au moins 8 caractères</li>
                            <li>Au moins une majuscule</li>
                            <li>Au moins une minuscule</li>
                            <li>Au moins un chiffre</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirm"><i class="fas fa-lock"></i> Confirmer le mot de passe *</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" id="password_confirm" name="password_confirm"
                            placeholder="Confirmez votre mot de passe" required>
                        <button type="button" class="password-toggle"
                            onclick="togglePassword('password_confirm', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-user-plus"></i>
                    <?php echo $is_ajout_par_admin ? 'Ajouter le compte' : 'Créer le compte administrateur'; ?>
                </button>
            </form>

            <div class="footer-text">
                <?php if ($is_ajout_par_admin): ?>
                    <p><a href="comptes/index.php">← Retour à la gestion des comptes</a></p>
                <?php else: ?>
                    <p>Après l'inscription, vous serez redirigé vers la page de connexion</p>
                <?php endif; ?>
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
</body>

</html>