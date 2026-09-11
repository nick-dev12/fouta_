<?php
/**
 * PARAMÈTRE DE LA CAISSE : LE DÉLAI DE RETOUR DES PIÈCES (11/09/2026).
 *
 * Décision de la direction : le délai pendant lequel un client peut rapporter
 * une pièce achetée en caisse se règle ici, par l'informaticien, et sera fixé
 * plus tard. Vide = aucune limite. Le délai se compte depuis l'encaissement du
 * ticket. Réglage stocké dans caisse_parametres (clé retour_delai_jours).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!in_array(admin_current_role(), ['informaticien', 'developpeur'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse_retours.php';

$tables_ok = caisse_retours_tables_ok();
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jeton = (string) ($_POST['csrf_token'] ?? '');
    $saisie = trim((string) ($_POST['delai_jours'] ?? ''));
    if ($jeton === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton)) {
        $erreur = 'Session expirée : rechargez la page.';
    } elseif (!$tables_ok) {
        $erreur = 'La base attend migrations/run_caisse_retours.php.';
    } elseif ($saisie !== '' && (!ctype_digit($saisie) || (int) $saisie < 1 || (int) $saisie > 365)) {
        $erreur = 'Le délai est un nombre de jours entre 1 et 365, ou vide pour ne fixer aucune limite.';
    } elseif (caisse_parametre_ecrire('retour_delai_jours', $saisie === '' ? null : (string) (int) $saisie, (int) $_SESSION['admin_id'])) {
        $_SESSION['caisse_param_message'] = $saisie === ''
            ? 'Délai de retour retiré : aucune limite.'
            : 'Délai de retour fixé à ' . (int) $saisie . ' jour(s).';
        header('Location: caisse-retours.php');
        exit;
    } else {
        $erreur = 'Le réglage n’a pas pu être enregistré.';
    }
}
$message = (string) ($_SESSION['caisse_param_message'] ?? '');
unset($_SESSION['caisse_param_message']);
$delai = $tables_ok ? caisse_retour_delai_jours() : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Délai de retour en caisse - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-dashboard-caisse-pages.css'); ?>
    <style>
        .param-retour { max-width: 720px; }
        .param-retour h1 { margin: 6px 0 10px; color: #10316F; }
        .param-retour p { max-width: 64ch; }
        .param-retour form { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-top: 16px; }
        .param-retour label { display: block; font-weight: 600; margin-bottom: 4px; }
        .param-retour input[type="number"] { width: 140px; padding: 9px 11px; border: 1px solid #C4CCDA; border-radius: 6px; font: inherit; }
        .param-retour input:focus-visible, .param-retour button:focus-visible { outline: 2px solid #10316F; outline-offset: 1px; }
        .param-retour__etat { padding: 12px 14px; background: #F3F5F9; border-radius: 8px; }
        .param-retour__ok { padding: 12px 14px; background: #E6F4EC; color: #1E6B42; border-radius: 8px; }
        .param-retour__err { padding: 12px 14px; background: #FBE9EA; color: #A61E25; border-radius: 8px; }
    </style>
</head>
<body class="admin-caisse-page">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="caisse-page-wrap">
    <main class="card-style-caisse param-retour">
        <p><a href="../parametres.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a></p>
        <h1>Délai de retour en caisse</h1>
        <p>Nombre de jours pendant lesquels un client peut rapporter une pièce achetée en caisse, comptés depuis l’encaissement de son ticket. Laissez vide pour ne fixer aucune limite.</p>

        <?php if ($message !== ''): ?>
        <p class="param-retour__ok" role="status"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if ($erreur !== ''): ?>
        <p class="param-retour__err" role="alert"><?php echo htmlspecialchars($erreur); ?></p>
        <?php endif; ?>

        <?php if (!$tables_ok): ?>
        <p class="param-retour__err">La base attend migrations/run_caisse_retours.php.</p>
        <?php else: ?>
        <p class="param-retour__etat">Réglage actuel : <strong><?php echo $delai === null ? 'aucune limite' : $delai . ' jour(s)'; ?></strong></p>
        <form method="post" action="caisse-retours.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <div>
                <label for="delai_jours">Délai en jours</label>
                <input type="number" id="delai_jours" name="delai_jours" min="1" max="365" step="1" inputmode="numeric"
                    value="<?php echo $delai === null ? '' : (int) $delai; ?>" placeholder="Aucune limite">
            </div>
            <button type="submit" class="btn-primary">Enregistrer</button>
        </form>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
