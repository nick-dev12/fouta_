<?php
/**
 * Paramètres dimensions d’impression des étiquettes produit FPL.
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

require_once __DIR__ . '/../../models/model_produit_etiquette_parametres.php';
require_once __DIR__ . '/../../includes/asset_version.php';

fpl_etiquette_parametres_ensure_schema();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['admin_csrf'];

$produit_id_retour = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produit_id'])) {
    $produit_id_retour = (int) $_POST['produit_id'];
} elseif (isset($_GET['produit_id'])) {
    $produit_id_retour = (int) $_GET['produit_id'];
}
if ($produit_id_retour < 0) {
    $produit_id_retour = 0;
}

$retour_url = $produit_id_retour > 0
    ? ('../produits/ajuster-stock.php?id=' . $produit_id_retour)
    : '../parametres.php';
$retour_label = $produit_id_retour > 0 ? 'Retour au produit' : 'Retour';

$error_message = '';
$success_message = '';
if (isset($_SESSION['success_message_fpl_etiq_dims'])) {
    $success_message = (string) $_SESSION['success_message_fpl_etiq_dims'];
    unset($_SESSION['success_message_fpl_etiq_dims']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer_fpl_etiq_dims'])) {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals($csrf, $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } else {
        $res = fpl_etiquette_parametres_save([
            'largeur_mm' => $_POST['largeur_mm'] ?? null,
            'hauteur_mm' => $_POST['hauteur_mm'] ?? null,
        ]);
        if ($res['success']) {
            $_SESSION['success_message_fpl_etiq_dims'] = $res['message'];
            $loc = 'etiquettes-produit.php';
            if ($produit_id_retour > 0) {
                $loc .= '?produit_id=' . $produit_id_retour;
            }
            header('Location: ' . $loc);
            exit;
        }
        $error_message = $res['message'];
    }
}

/* LES TAILLES PROPOSÉES À L'IMPRESSION (24/08) — la liste des formats que
 * la fiche, « Toutes les étiquettes » et le PDF offrent au choix :
 * l'utiliser comme réglage, en ajouter une, en retirer une (les tailles
 * d'origine sont protégées, le retrait est une suppression douce). */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['format_utiliser']) || isset($_POST['format_ajouter']) || isset($_POST['format_retirer']))) {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals($csrf, $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } else {
        $res = ['success' => false, 'message' => ''];
        if (isset($_POST['format_utiliser'])) {
            $f = fpl_etiquette_format_get((int) $_POST['format_utiliser'], 'piece');
            $res = $f !== false
                ? fpl_etiquette_parametres_save(['largeur_mm' => $f['largeur_mm'], 'hauteur_mm' => $f['hauteur_mm']])
                : ['success' => false, 'message' => 'Ce format n\'existe pas.'];
        } elseif (isset($_POST['format_ajouter'])) {
            $res = fpl_etiquette_format_ajouter($_POST['format_largeur'] ?? null, $_POST['format_hauteur'] ?? null);
        } elseif (isset($_POST['format_retirer'])) {
            $res = fpl_etiquette_format_retirer((int) $_POST['format_retirer']);
        }
        if ($res['success']) {
            $_SESSION['success_message_fpl_etiq_dims'] = $res['message'];
            $loc = 'etiquettes-produit.php';
            if ($produit_id_retour > 0) {
                $loc .= '?produit_id=' . $produit_id_retour;
            }
            header('Location: ' . $loc);
            exit;
        }
        $error_message = $res['message'];
    }
}

$dims = fpl_etiquette_dims();
$def = fpl_etiquette_dims_defaut();
$formats_pieces = function_exists('fpl_etiquette_formats_pieces') ? fpl_etiquette_formats_pieces() : [];
$w = (float) $dims['largeur_mm'];
$h = (float) $dims['hauteur_mm'];
$label = (string) $dims['label'];
$meta = (string) $dims['meta'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étiquettes de pièce FPL — Paramètres</title>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-parametres-page.css'); ?>
    <?php fpl_css_link('admin-etiquettes-entrepot.css'); ?>
    <?php fpl_css_link('fpl-etiquette.css'); ?>
    <?php echo fpl_etiquette_dims_style_block($dims); ?>
    <style>
      .page-etiquettes-produit .ee-etiq-params__grid,
      .page-etiquettes-produit .ee-etiq-params__grid--stack {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      .page-etiquettes-produit .ee-etiq-params__hero-row {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
      }
      .page-etiquettes-produit .ee-etiq-params__hero-main {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        flex: 1 1 16rem;
        min-width: 0;
      }
      .page-etiquettes-produit .ee-etiq-params__retour {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        flex: 0 0 auto;
        margin-top: 0.15rem;
        padding: 0.5rem 0.95rem;
        border-radius: 8px;
        border: 1px solid rgba(53, 100, 166, 0.3);
        background: #fff;
        color: var(--couleur-dominante, #3564a6);
        font-size: 0.88rem;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
      }
      .page-etiquettes-produit .ee-etiq-params__retour:hover {
        background: rgba(53, 100, 166, 0.08);
        color: var(--couleur-dominante-hover, #2d5690);
      }
      .fpl-params-preview {
        width: 100%;
      }
      .fpl-params-preview .fpl-etiq-preview-scale {
        transform: scale(1.35);
        margin-bottom: calc(var(--fpl-h) * 0.4);
      }
      .fpl-params-preview .fpl-etiq.fpl-etiq--fixed {
        box-shadow: 0 2px 12px rgba(13, 13, 13, 0.12);
      }
      .fpl-params-mini {
        font-size: 2.2mm;
        font-weight: 800;
        color: #19377d;
        padding: 8mm 3mm;
        text-align: center;
      }
    </style>
</head>
<body class="page-parametres-admin page-etiquettes-entrepot page-etiquettes-produit">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page ee-etiq-params">
        <header class="ee-etiq-params__hero">
            <div class="ee-etiq-params__hero-row">
                <div class="ee-etiq-params__hero-main">
                    <div class="ee-etiq-params__icon" aria-hidden="true"><i class="fas fa-tag"></i></div>
                    <div>
                        <h1 class="ee-etiq-params__title">Étiquettes de pièce</h1>
                        <p class="ee-etiq-params__lead">
                            Taille d’impression des étiquettes FPL (page ajuster stock). Défaut&nbsp;:
                            <?php echo (int) $def['largeur_mm']; ?>×<?php echo (int) $def['hauteur_mm']; ?>&nbsp;mm (Zebra ZD420).
                            Le contenu est mis à l’échelle automatiquement.
                        </p>
                    </div>
                </div>
                <a class="ee-etiq-params__retour" href="<?php echo htmlspecialchars($retour_url, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> <?php echo htmlspecialchars($retour_label, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
        </header>

        <?php if ($success_message !== ''): ?>
        <div class="message success" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error_message !== ''): ?>
        <div class="message error" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="ee-etiq-params__grid ee-etiq-params__grid--stack">
            <form method="post" class="ee-etiq-params__form" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($produit_id_retour > 0): ?>
                <input type="hidden" name="produit_id" value="<?php echo (int) $produit_id_retour; ?>">
                <?php endif; ?>

                <div class="ee-etiq-params__fields">
                    <label class="ee-etiq-field">
                        <span class="ee-etiq-field__label">Largeur (mm)</span>
                        <input type="number" name="largeur_mm" id="fplLargeur" min="30" max="200" step="0.5"
                               value="<?php echo htmlspecialchars((string) $w, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label class="ee-etiq-field">
                        <span class="ee-etiq-field__label">Hauteur (mm)</span>
                        <input type="number" name="hauteur_mm" id="fplHauteur" min="30" max="200" step="0.5"
                               value="<?php echo htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                </div>

                <p class="ee-etiq-params__hint">
                    Plage&nbsp;: 30–200&nbsp;mm. Ces valeurs s’appliquent à l’aperçu et au bouton
                    <strong>Imprimer l’étiquette</strong> sur la fiche stock produit.
                </p>

                <div class="ee-etiq-params__actions">
                    <button type="submit" name="enregistrer_fpl_etiq_dims" value="1" class="ee-etiq-params__save">
                        <i class="fas fa-floppy-disk" aria-hidden="true"></i> Enregistrer
                    </button>
                    <button type="button" class="ee-etiq-params__reset" id="fplResetDefaut">
                        Remettre <?php echo (int) $def['largeur_mm']; ?>×<?php echo (int) $def['hauteur_mm']; ?>
                    </button>
                </div>
            </form>

            <?php if ($formats_pieces !== []) : ?>
            <section class="fpl-formats-card" aria-labelledby="fpl-formats-titre">
                <h2 id="fpl-formats-titre" class="fpl-formats-card__titre"><i class="fas fa-ruler-combined" aria-hidden="true"></i> Les tailles proposées à l'impression</h2>
                <p class="fpl-formats-card__hint">Ce sont les tailles offertes au choix sur la fiche, dans « Toutes les étiquettes » et pour le PDF. « Utiliser » en fait la taille du réglage ci-dessus.</p>
                <table class="fpl-formats-table">
                    <thead>
                        <tr><th>Taille</th><th>Dimensions</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formats_pieces as $fmt) : ?>
                        <?php $est_reglage = abs((float) $fmt['largeur_mm'] - (float) $dims['largeur_mm']) < 0.01
                            && abs((float) $fmt['hauteur_mm'] - (float) $dims['hauteur_mm']) < 0.01; ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars((string) $fmt['nom'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($est_reglage) : ?><span class="fpl-formats-actuel">réglage actuel</span><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(fpl_etiquette_dims_fmt_mm($fmt['largeur_mm']) . ' × ' . fpl_etiquette_dims_fmt_mm($fmt['hauteur_mm']) . ' mm', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:right; white-space:nowrap">
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="produit_id" value="<?php echo (int) $produit_id_retour; ?>">
                                    <button type="submit" name="format_utiliser" value="<?php echo (int) $fmt['id']; ?>"
                                            class="fpl-formats-btn" <?php echo $est_reglage ? 'disabled' : ''; ?>>Utiliser</button>
                                </form>
                                <?php if ((int) ($fmt['est_systeme'] ?? 0) !== 1) : ?>
                                <form method="post" style="display:inline"
                                      onsubmit="return confirm('Retirer la taille « <?php echo htmlspecialchars((string) $fmt['nom'], ENT_QUOTES, 'UTF-8'); ?> » de la liste ?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="produit_id" value="<?php echo (int) $produit_id_retour; ?>">
                                    <button type="submit" name="format_retirer" value="<?php echo (int) $fmt['id']; ?>"
                                            class="fpl-formats-btn fpl-formats-btn--danger">Retirer</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <form method="post" class="fpl-formats-ajout">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="produit_id" value="<?php echo (int) $produit_id_retour; ?>">
                    <label>Nouvelle taille&nbsp;:
                        <input type="number" name="format_largeur" min="30" max="200" step="0.5" placeholder="Largeur" required>
                    </label>
                    <span aria-hidden="true">×</span>
                    <label>
                        <input type="number" name="format_hauteur" min="30" max="200" step="0.5" placeholder="Hauteur" required>
                    </label>
                    <span class="fpl-formats-ajout__mm">mm</span>
                    <button type="submit" name="format_ajouter" value="1" class="fpl-formats-btn fpl-formats-btn--primaire">
                        <i class="fas fa-plus" aria-hidden="true"></i> Ajouter
                    </button>
                </form>
                <style>
                    .fpl-formats-card { grid-column: 1 / -1; margin-top: 18px; background: #fff; border: 1px solid var(--line, #DFE4EC); border-radius: 12px; padding: 18px 20px; }
                    .fpl-formats-card__titre { font-size: 16px; font-weight: 700; color: var(--navy, #10316f); margin: 0 0 4px; display: flex; align-items: center; gap: 8px; }
                    .fpl-formats-card__hint { font-size: 12.5px; color: var(--slate, #5A6A85); margin: 0 0 12px; }
                    .fpl-formats-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
                    .fpl-formats-table th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--slate, #5A6A85); padding: 6px 8px; border-bottom: 1px solid var(--line, #DFE4EC); }
                    .fpl-formats-table td { padding: 8px; border-bottom: 1px solid var(--line-soft, #EDF1F6); }
                    .fpl-formats-actuel { margin-left: 8px; font-size: 11px; font-weight: 700; color: #0369A1; background: #E0F2FE; border: 1px solid #BAE6FD; border-radius: 10px; padding: 1px 8px; }
                    .fpl-formats-btn { font-family: inherit; font-size: 12.5px; font-weight: 600; padding: 5px 12px; border-radius: 8px; border: 1.5px solid var(--line, #DFE4EC); background: #fff; color: var(--ink, #1c2733); cursor: pointer; }
                    .fpl-formats-btn:hover:not(:disabled) { border-color: var(--blue, #2957ae); }
                    .fpl-formats-btn:disabled { opacity: .45; cursor: default; }
                    .fpl-formats-btn--danger { color: #B91C1C; }
                    .fpl-formats-btn--danger:hover { border-color: #B91C1C; }
                    .fpl-formats-btn--primaire { background: var(--navy, #10316f); border-color: var(--navy, #10316f); color: #fff; }
                    .fpl-formats-ajout { display: flex; align-items: center; gap: 8px; margin-top: 12px; flex-wrap: wrap; font-size: 13px; }
                    .fpl-formats-ajout input { width: 90px; padding: 6px 8px; border: 1.5px solid var(--line, #DFE4EC); border-radius: 8px; font-family: inherit; }
                </style>
            </section>
            <?php endif; ?>

            <aside class="ee-etiq-params__preview fpl-params-preview" aria-label="Aperçu">
                <p class="ee-barre-etiq-block__label" id="fplPreviewLabel"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="fpl-etiq-preview-meta" id="fplPreviewMeta"><?php echo htmlspecialchars($meta, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="fpl-etiq-preview-scale" id="fplPreviewScale">
                    <article class="fpl-etiq fpl-etiq--fixed" style="--fpl-accent:#19377d;--fpl-accent-dark:#00155B;">
                        <div class="fpl-etiq__header-zone">
                            <div class="fpl-etiq__band-top" aria-hidden="true"></div>
                        </div>
                        <div class="fpl-etiq__sheet">
                            <div class="fpl-params-mini">Aperçu<br>format d’impression</div>
                        </div>
                        <footer class="fpl-etiq__footer" style="position:absolute;bottom:0;left:0;right:0;">
                            <div class="fpl-etiq__footer-row1"><span>FOUTA POIDS LOURDS</span></div>
                        </footer>
                    </article>
                </div>
                <p class="ee-etiq-params__preview-note">Aperçu réduit — l’impression réelle utilise les mm saisis.</p>
            </aside>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        var defW = <?php echo json_encode($def['largeur_mm']); ?>;
        var defH = <?php echo json_encode($def['hauteur_mm']); ?>;
        var elW = document.getElementById('fplLargeur');
        var elH = document.getElementById('fplHauteur');
        var label = document.getElementById('fplPreviewLabel');
        var meta = document.getElementById('fplPreviewMeta');
        var root = document.documentElement;

        function fmt(n) {
            var x = Math.round(n * 10) / 10;
            return (Math.abs(x - Math.round(x)) < 0.05) ? String(Math.round(x)) : String(x);
        }

        function applyLive() {
            var w = parseFloat(elW.value) || defW;
            var h = parseFloat(elH.value) || defH;
            var sx = w / 70;
            var sy = h / 70;
            root.style.setProperty('--fpl-w', w + 'mm');
            root.style.setProperty('--fpl-h', h + 'mm');
            root.style.setProperty('--fpl-sx', String(sx));
            root.style.setProperty('--fpl-sy', String(sy));
            root.style.setProperty('--fpl-s', String(Math.min(sx, sy)));
            if (label) {
                label.textContent = 'Étiquette FPL ' + fmt(w) + '\u00d7' + fmt(h) + ' mm';
            }
            if (meta) {
                var dw = Math.round(w * 8);
                var dh = Math.round(h * 8);
                meta.textContent = 'Format d\u2019impression ' + fmt(w) + ' \u00d7 ' + fmt(h)
                    + ' mm \u00b7 Zebra ZD420 (203 dpi \u2248 8 dots/mm \u00b7 ' + dw + '\u00d7' + dh + ' dots) \u00b7 Aper\u00e7u agrandi \u00e0 l\u2019\u00e9cran';
            }
        }

        [elW, elH].forEach(function (el) {
            if (el) el.addEventListener('input', applyLive);
        });
        var btn = document.getElementById('fplResetDefaut');
        if (btn) {
            btn.addEventListener('click', function () {
                elW.value = defW;
                elH.value = defH;
                applyLive();
            });
        }
        applyLive();
    })();
    </script>
</body>
</html>
