<?php
/**
 * Page de test d'envoi d'emails (Admin)
 * Permet de vérifier que la configuration SMTP fonctionne
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destinataire = isset($_POST['email']) ? trim($_POST['email']) : $_SESSION['admin_email'];
    if (empty($destinataire) || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        $message = 'Adresse email invalide.';
        $message_type = 'error';
    } elseif (!function_exists('mail_send')) {
        $message = 'Service email non disponible. Exécutez "composer install".';
        $message_type = 'error';
    } else {
        $sujet = 'Test email - FOUTA POIDS LOURDS';
        $body = '<h2>Test d\'envoi d\'email</h2>';
        $body .= '<p>Si vous recevez ce message, la configuration SMTP fonctionne correctement.</p>';
        $body .= '<p>Envoyé le ' . date('d/m/Y H:i:s') . ' depuis l\'interface admin.</p>';
        $body .= '<hr><p><small>FOUTA POIDS LOURDS - Site e-commerce</small></p>';

        $result = mail_send($destinataire, $sujet, $body, true);

        if ($result['success']) {
            $message = "Email de test envoyé avec succès à {$destinataire}. Vérifiez votre boîte de réception (et les spams).";
            $message_type = 'success';
        } else {
            $message = 'Échec de l\'envoi : ' . ($result['error'] ?? 'Erreur inconnue');
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test email - Admin</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .test-email-form { max-width: 500px; margin: 20px 0; }
        .test-email-form label { display: block; margin-bottom: 8px; font-weight: 600; }
        .test-email-form input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 15px; }
        .test-email-form button { margin-top: 15px; padding: 12px 24px; background: var(--couleur-dominante, #918a44); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; }
        .test-email-form button:hover { opacity: 0.9; }
        .message { padding: 15px 20px; border-radius: 10px; margin: 20px 0; }
        .message.success { background: #e8f8f8; color: #0d7377; border-left: 4px solid #0d7377; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <div class="contents-container">
        <div class="content-header">
            <h1><i class="fas fa-envelope"></i> Test d'envoi d'email</h1>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>

        <p>Envoyez un email de test pour vérifier que la configuration SMTP (tresorafricain.com) fonctionne.</p>

        <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <form method="post" action="test-email.php" class="test-email-form">
            <label for="email">Adresse de test *</label>
            <input type="email" id="email" name="email" required
                value="<?php echo htmlspecialchars($_POST['email'] ?? $_SESSION['admin_email'] ?? ''); ?>"
                placeholder="email@exemple.com">
            <button type="submit">
                <i class="fas fa-paper-plane"></i> Envoyer l'email de test
            </button>
        </form>
    </div>
</body>
</html>
