<?php
/**
 * Page mot de passe oublié - Administrateur
 * Utilise PHPMailer pour l'envoi d'emails côté serveur
 */

session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../models/model_admin.php';
if (!admin_exists()) {
    header('Location: inscription-admin.php');
    exit;
}

require_once __DIR__ . '/../controllers/controller_admin.php';
$result = process_forgot_password();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Admin FOUTA POIDS LOURDS</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            z-index: 100;
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

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: var(--texte-fonce);
        }

        .loading.show {
            display: block;
        }

        .loading i {
            font-size: 24px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
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
                <div class="icon"><i class="fas fa-key"></i></div>
                <h1>Mot de passe oublié</h1>
                <p>Entrez votre email pour recevoir un lien de réinitialisation</p>
            </div>

            <?php if ($result['success']): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($result['message']); ?>
                </div>
                <div class="footer-text">
                    <p><a href="login.php">Retour à la connexion</a></p>
                </div>
            <?php else: ?>
                <?php if (!empty($result['message'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" id="email" name="email" placeholder="votre@email.com" required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Envoyer le lien
                    </button>
                </form>

                <div class="footer-text">
                    <p><a href="login.php">Retour à la connexion</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>