<?php
/**
 * Page de connexion administrateur — layout FPL
 */

session_start();

if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_email'])) {
    require_once __DIR__ . '/../includes/admin_route_access.php';
    $role_redir = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
    header('Location: ' . admin_route_build_url(admin_role_default_redirect_path($role_redir)));
    exit;
}

require_once __DIR__ . '/../models/model_admin.php';
if (!admin_exists()) {
    header('Location: inscription-admin.php');
    exit;
}

require_once __DIR__ . '/../controllers/controller_admin.php';
$result = process_admin_login();

if (isset($result['success']) && $result['success'] && $result['admin']) {
    $_SESSION['admin_id'] = $result['admin']['id'];
    $_SESSION['admin_nom'] = $result['admin']['nom'];
    $_SESSION['admin_prenom'] = $result['admin']['prenom'];
    $_SESSION['admin_email'] = $result['admin']['email'];
    $_SESSION['admin_statut'] = $result['admin']['statut'];
    $_SESSION['admin_role'] = normalize_admin_role($result['admin']['role'] ?? 'admin');

    require_once __DIR__ . '/../includes/post_login_welcome.php';
    require_once __DIR__ . '/../includes/admin_route_access.php';
    $next_after_login = admin_route_build_url(admin_role_default_redirect_path($_SESSION['admin_role']));
    if (!empty($_POST['next'])) {
        $next_after_login = (string) $_POST['next'];
    } elseif (!empty($_GET['next'])) {
        $next_after_login = (string) $_GET['next'];
    } elseif (!empty($_SESSION['admin_login_redirect'])) {
        $next_after_login = (string) $_SESSION['admin_login_redirect'];
        unset($_SESSION['admin_login_redirect']);
    }
    $_SESSION['just_logged_in_target'] = post_login_sanitize_next_url($next_after_login);
    header('Location: /post-login-welcome.php');
    exit;
}

$inscription_success = '';
if (isset($_SESSION['inscription_success'])) {
    $inscription_success = $_SESSION['inscription_success'];
    unset($_SESSION['inscription_success']);
}

require_once __DIR__ . '/../includes/fpl_ui.php';

$login_next = '';
if (!empty($_POST['next'])) {
    $login_next = (string) $_POST['next'];
} elseif (!empty($_GET['next'])) {
    $login_next = (string) $_GET['next'];
} elseif (!empty($_SESSION['admin_login_redirect'])) {
    $login_next = (string) $_SESSION['admin_login_redirect'];
}

ob_start();
?>
<form method="POST" action="" id="loginForm">
    <?php if ($login_next !== ''): ?>
        <input type="hidden" name="next" value="<?php echo e($login_next); ?>">
    <?php endif; ?>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="votre@email.com" required
            value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
    </div>
    <div class="field">
        <label for="password">Mot de passe</label>
        <div class="pw-wrap">
            <input type="password" id="password" name="password" placeholder="Votre mot de passe" required>
            <button type="button" class="pw-toggle" onclick="togglePassword('password', this)" aria-label="Afficher le mot de passe">
                <i class="fas fa-eye"></i>
            </button>
        </div>
    </div>
    <div class="auth-row">
        <span></span>
        <a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a>
    </div>
    <button type="submit" class="btn btn-primary">Se connecter</button>
</form>
<?php
$auth_form_html = ob_get_clean();

ob_start();
if (!empty($inscription_success)) {
    echo '<div class="alert success">' . e($inscription_success) . '</div>';
}
if (isset($result['message']) && !empty($result['message']) && empty($result['success'])) {
    echo '<div class="alert error">' . $result['message'] . '</div>';
}
$auth_extra_html = ob_get_clean();

$auth_title = 'Connexion Admin';
$auth_lead = 'Accédez à votre tableau de bord';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../includes/fpl_assets.php'; ?>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur - FOUTA POIDS LOURDS</title>
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
