<?php
/**
 * Page de réinitialisation du mot de passe - Administrateur (layout FPL)
 */

session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../controllers/controller_admin.php';
require_once __DIR__ . '/../includes/fpl_ui.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$token_valid = false;
$error_token = '';

if (empty($token)) {
    $error_token = 'Lien invalide. Token manquant.';
} else {
    $token_data = get_valid_reset_token($token);
    $token_valid = (bool) $token_data;
    if (!$token_valid) {
        $error_token = 'Ce lien est invalide ou a expiré. Veuillez faire une nouvelle demande de réinitialisation.';
    }
}

$result = ['success' => false, 'message' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $result = process_reset_password();
    if ($result['success']) {
        $token_valid = false;
    }
}

ob_start();
if (!empty($error_token) && !$token_valid && empty($result['success'])) {
    echo '<div class="alert error">' . e($error_token) . '</div>';
    echo '<p class="auth-links"><a href="mot-de-passe-oublie.php">Demander un nouveau lien</a> · <a href="login.php">Connexion</a></p>';
} elseif (!empty($result['success'])) {
    echo '<div class="alert success">' . e($result['message']) . '</div>';
    echo '<p class="auth-links"><a href="login.php">Se connecter</a></p>';
} else {
    if (!empty($result['message'])) {
        echo '<div class="alert error">' . $result['message'] . '</div>';
    }
    ?>
    <form method="POST" action="">
        <input type="hidden" name="token" value="<?php echo e($token); ?>">
        <div class="field">
            <label for="password">Nouveau mot de passe</label>
            <div class="pw-wrap">
                <input type="password" id="password" name="password" placeholder="Min. 8 caractères" required>
                <button type="button" class="pw-toggle" onclick="togglePassword('password', this)" aria-label="Afficher">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <div class="field">
            <label for="password_confirm">Confirmer le mot de passe</label>
            <div class="pw-wrap">
                <input type="password" id="password_confirm" name="password_confirm" required>
                <button type="button" class="pw-toggle" onclick="togglePassword('password_confirm', this)" aria-label="Afficher">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Réinitialiser le mot de passe</button>
    </form>
    <p class="auth-links"><a href="login.php">Retour à la connexion</a></p>
    <?php
}
$auth_form_html = ob_get_clean();
$auth_extra_html = '';
$auth_title = 'Nouveau mot de passe';
$auth_lead = 'Choisissez un mot de passe sécurisé';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../includes/fpl_assets.php'; ?>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe - Admin FOUTA POIDS LOURDS</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php fpl_css_link('variables.css'); ?>
    <?php fpl_css_link('fpl.css'); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/includes/auth_layout.php'; ?>
<script>
function togglePassword(inputId, button) {
    var input = document.getElementById(inputId);
    var icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
