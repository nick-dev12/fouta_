<?php
/**
 * Page mot de passe oublié - Administrateur (layout FPL)
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
require_once __DIR__ . '/../includes/fpl_ui.php';

ob_start();
if (!empty($result['success'])) {
    echo '<div class="alert success">' . e($result['message']) . '</div>';
    echo '<p class="auth-links"><a href="login.php">Retour à la connexion</a></p>';
} else {
    if (!empty($result['message'])) {
        echo '<div class="alert error">' . $result['message'] . '</div>';
    }
    ?>
    <form method="POST" action="">
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="votre@email.com" required
                value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
        </div>
        <button type="submit" class="btn btn-primary">Envoyer le lien</button>
    </form>
    <p class="auth-links"><a href="login.php">Retour à la connexion</a></p>
    <?php
}
$auth_form_html = ob_get_clean();
$auth_extra_html = '';
$auth_title = 'Mot de passe oublié';
$auth_lead = 'Entrez votre email pour recevoir un lien de réinitialisation';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../includes/fpl_assets.php'; ?>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Admin FOUTA POIDS LOURDS</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php fpl_css_link('variables.css'); ?>
    <?php fpl_css_link('fpl.css'); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/includes/auth_layout.php'; ?>
</body>
</html>
