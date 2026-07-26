<?php
/**
 * Paramètres dimensions d’impression des étiquettes entrepôt.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_gestion_stock_etendue()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_entrepot_etiquette_parametres.php';
require_once __DIR__ . '/../../includes/asset_version.php';

entrepot_etiquette_parametres_ensure_schema();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['admin_csrf'];

$error_message = '';
$success_message = '';
if (isset($_SESSION['success_message_etiq_dims'])) {
    $success_message = (string) $_SESSION['success_message_etiq_dims'];
    unset($_SESSION['success_message_etiq_dims']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer_etiq_dims'])) {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals($csrf, $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } else {
        $res = entrepot_etiquette_parametres_save([
            'largeur_mm' => $_POST['largeur_mm'] ?? null,
            'hauteur_mm' => $_POST['hauteur_mm'] ?? null,
            'qr_mm' => $_POST['qr_mm'] ?? null,
            'texte_mm' => $_POST['texte_mm'] ?? null,
        ]);
        if ($res['success']) {
            $_SESSION['success_message_etiq_dims'] = $res['message'];
            header('Location: etiquettes-entrepot.php');
            exit;
        }
        $error_message = $res['message'];
    }
}

$dims = entrepot_etiquette_dims();
$def = entrepot_etiquette_dims_defaut();
$w = (float) $dims['largeur_mm'];
$h = (float) $dims['hauteur_mm'];
$qr = (float) $dims['qr_mm'];
$tx = (float) $dims['texte_mm'];
$label = (string) $dims['label'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étiquettes entrepôt — Paramètres</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/entrepot-barre-etiquette.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-etiquettes-entrepot.css<?php echo asset_version_query(); ?>">
    <?php echo entrepot_etiquette_dims_style_block($dims); ?>
</head>
<body class="page-parametres-admin page-etiquettes-entrepot">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page ee-etiq-params">
        <header class="ee-etiq-params__hero">
            <a class="ee-etiq-params__back" href="../parametres.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>
            <div class="ee-etiq-params__hero-row">
                <div class="ee-etiq-params__icon" aria-hidden="true"><i class="fas fa-tags"></i></div>
                <div>
                    <h1 class="ee-etiq-params__title">Dimensions des étiquettes</h1>
                    <p class="ee-etiq-params__lead">
                        Définissez la taille d’impression utilisée pour l’aperçu, le bouton <strong>Imprimer</strong>
                        et le <strong>PDF</strong> des étiquettes barres. Défaut&nbsp;: <?php echo (int) $def['largeur_mm']; ?>×<?php echo (int) $def['hauteur_mm']; ?>&nbsp;mm.
                    </p>
                </div>
            </div>
        </header>

        <?php if ($success_message !== ''): ?>
        <div class="message success" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error_message !== ''): ?>
        <div class="message error" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="ee-etiq-params__grid">
            <form method="post" class="ee-etiq-params__form" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="ee-etiq-params__fields">
                    <label class="ee-etiq-field">
                        <span class="ee-etiq-field__label">Largeur (mm)</span>
                        <input type="number" name="largeur_mm" id="etiqLargeur" min="20" max="200" step="0.5"
                               value="<?php echo htmlspecialchars((string) $w, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label class="ee-etiq-field">
                        <span class="ee-etiq-field__label">Hauteur (mm)</span>
                        <input type="number" name="hauteur_mm" id="etiqHauteur" min="15" max="150" step="0.5"
                               value="<?php echo htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label class="ee-etiq-field">
                        <span class="ee-etiq-field__label">Taille QR (mm)</span>
                        <input type="number" name="qr_mm" id="etiqQr" min="8" max="80" step="0.5"
                               value="<?php echo htmlspecialchars((string) $qr, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label class="ee-etiq-field">
                        <span class="ee-etiq-field__label">Taille texte (mm)</span>
                        <input type="number" name="texte_mm" id="etiqTexte" min="4" max="24" step="0.5"
                               value="<?php echo htmlspecialchars((string) $tx, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                </div>

                <p class="ee-etiq-params__hint">
                    Plages&nbsp;: largeur 20–200, hauteur 15–150, QR 8–80, texte 4–24&nbsp;mm.
                    Le QR est automatiquement limité pour rester dans la hauteur de l’étiquette.
                </p>

                <div class="ee-etiq-params__actions">
                    <button type="submit" name="enregistrer_etiq_dims" value="1" class="ee-etiq-params__save">
                        <i class="fas fa-floppy-disk" aria-hidden="true"></i> Enregistrer
                    </button>
                    <button type="button" class="ee-etiq-params__reset" id="etiqResetDefaut">
                        Remettre <?php echo (int) $def['largeur_mm']; ?>×<?php echo (int) $def['hauteur_mm']; ?>
                    </button>
                    <a href="etiquettes-produit.php" class="ee-etiq-params__reset" style="text-decoration:none;">Étiquettes produit →</a>
                </div>
            </form>

            <aside class="ee-etiq-params__preview" aria-label="Aperçu">
                <p class="ee-barre-etiq-block__label" id="etiqPreviewLabel"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="ee-barre-etiq-preview-wrap" id="etiqPreviewWrap">
                    <div class="ee-barre-etiq-preview-scale" id="etiqPreviewScale">
                        <article class="ee-barre-etiq" data-barre-etiq>
                            <span class="ee-barre-etiq__text">CA1-01</span>
                            <div class="ee-barre-etiq__qr-box">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='96' height='96'%3E%3Crect fill='%23fff' width='96' height='96'/%3E%3Crect fill='%23000' x='8' y='8' width='24' height='24'/%3E%3Crect fill='%23000' x='64' y='8' width='24' height='24'/%3E%3Crect fill='%23000' x='8' y='64' width='24' height='24'/%3E%3Crect fill='%23000' x='40' y='40' width='16' height='16'/%3E%3C/svg%3E" width="96" height="96" alt="QR" class="ee-barre-etiq__qr">
                            </div>
                        </article>
                    </div>
                </div>
                <p class="ee-etiq-params__preview-note">Aperçu à l’échelle — l’impression réelle utilise les mm saisis.</p>
            </aside>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        var defW = <?php echo json_encode($def['largeur_mm']); ?>;
        var defH = <?php echo json_encode($def['hauteur_mm']); ?>;
        var defQr = <?php echo json_encode($def['qr_mm']); ?>;
        var defTx = <?php echo json_encode($def['texte_mm']); ?>;
        var elW = document.getElementById('etiqLargeur');
        var elH = document.getElementById('etiqHauteur');
        var elQr = document.getElementById('etiqQr');
        var elTx = document.getElementById('etiqTexte');
        var label = document.getElementById('etiqPreviewLabel');
        var root = document.documentElement;

        function fmt(n) {
            var x = Math.round(n * 10) / 10;
            return (Math.abs(x - Math.round(x)) < 0.05) ? String(Math.round(x)) : String(x);
        }

        function applyLive() {
            var w = parseFloat(elW.value) || defW;
            var h = parseFloat(elH.value) || defH;
            var qr = parseFloat(elQr.value) || defQr;
            var tx = parseFloat(elTx.value) || defTx;
            root.style.setProperty('--ee-etiq-w', w + 'mm');
            root.style.setProperty('--ee-etiq-h', h + 'mm');
            root.style.setProperty('--ee-etiq-qr', qr + 'mm');
            root.style.setProperty('--ee-etiq-texte', tx + 'mm');
            if (label) {
                label.textContent = 'Étiquette ' + fmt(w) + '\u00d7' + fmt(h) + ' mm';
            }
        }

        [elW, elH, elQr, elTx].forEach(function (el) {
            if (el) {
                el.addEventListener('input', applyLive);
            }
        });

        var btn = document.getElementById('etiqResetDefaut');
        if (btn) {
            btn.addEventListener('click', function () {
                elW.value = defW;
                elH.value = defH;
                elQr.value = defQr;
                elTx.value = defTx;
                applyLive();
            });
        }
        applyLive();
    })();
    </script>
</body>
</html>
