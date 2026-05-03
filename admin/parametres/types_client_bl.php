<?php
/**
 * Plafonds cumul BL par type Standard / VIP
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_parametres_types_client.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message_pct_bl'])) {
    $success_message = (string) $_SESSION['success_message_pct_bl'];
    unset($_SESSION['success_message_pct_bl']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action_pct_bl'] ?? '') === 'save') {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), (string) $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!pct_types_client_bl_tables_available()) {
        $error_message = 'Table parametres_types_client_bl absente — exécutez la migration.';
    } else {
        $code = ($_POST['code_type'] ?? '') === 'vip' ? 'vip' : 'standard';
        $montant = isset($_POST['montant_plafond_ht']) ? (float) str_replace(',', '.', (string) $_POST['montant_plafond_ht']) : 0;
        $montant = max(0, round($montant, 2));
        if (pct_upsert_plafond($code, $montant)) {
            $_SESSION['success_message_pct_bl'] = 'Plafond enregistré pour « ' . pct_label_type($code) . ' ».';
            header('Location: types_client_bl.php');
            exit;
        }
        $error_message = 'Enregistrement impossible.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action_pct_bl'] ?? '') === 'delete') {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), (string) $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!pct_types_client_bl_tables_available()) {
        $error_message = 'Table parametres_types_client_bl absente — exécutez la migration.';
    } else {
        $code_del = ($_POST['code_type'] ?? '') === 'vip' ? 'vip' : 'standard';
        if (pct_reinitialiser_plafond_type_bl($code_del)) {
            $_SESSION['success_message_pct_bl'] = 'Plafond supprimé pour « ' . pct_label_type($code_del) . ' » (aucune limite).';
            header('Location: types_client_bl.php');
            exit;
        }
        $error_message = 'Suppression du plafond impossible.';
    }
}

$rows = pct_get_all_plafonds();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Types clients &amp; plafonds BL — Paramètres</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-parametres-admin page-pct-types-bl">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page pct-types-bl-root">
        <p class="parametres-hero__eyebrow"><a href="../parametres.php" style="color:inherit;text-decoration:none;">← Paramètres</a></p>
        <header class="parametres-hero">
            <h1 class="parametres-hero__title"><i class="fas fa-layer-group" aria-hidden="true"></i> Types client &amp; plafonds BL</h1>
            <p class="parametres-hero__lead">Définissez le montant maximum HT cumulé (tous bons de livraison) autorisé par type. La valeur <strong>0</strong> signifie <strong>aucune limite</strong>. Les types Standard et VIP ne peuvent pas être retirés : <strong>Supprimer</strong> enlève uniquement le plafond.</p>
        </header>

        <?php if ($error_message !== ''): ?>
            <div class="message error" role="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message !== ''): ?>
            <div class="message success" role="status"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!pct_types_client_bl_tables_available()): ?>
            <div class="message error"><i class="fas fa-database"></i> Exécutez <code>php migrations/run_add_types_client_bl.php</code> sur le serveur.</div>
        <?php else: ?>

        <div class="pct-types-toolbar">
            <button type="button" class="btn-primary" id="btn-open-pct-modal"><i class="fas fa-plus"></i> Définir / modifier un plafond</button>
        </div>

        <div class="pct-types-table-wrap">
            <table class="pct-types-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Plafond cumul HT (FCFA)</th>
                        <th class="pct-types-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pct_lignes = [
                        'standard' => ['badge' => 'pct-badge--std', 'lbl' => 'Standard'],
                        'vip' => ['badge' => 'pct-badge--vip', 'lbl' => 'VIP'],
                    ];
                    foreach ($pct_lignes as $code_k => $meta) :
                        $m = (float) ($rows[$code_k] ?? 0);
                        $has_plafond = $m > 0;
                    ?>
                    <tr>
                        <td><span class="pct-badge <?php echo htmlspecialchars($meta['badge']); ?>"><?php echo htmlspecialchars($meta['lbl']); ?></span></td>
                        <td><?php echo $has_plafond ? number_format($m, 0, ',', ' ') . ' FCFA' : '— (aucune limite)'; ?></td>
                        <td class="pct-types-actions-cell">
                            <div class="pct-row-actions">
                                <button type="button" class="btn-secondary pct-btn-edit"
                                    data-code="<?php echo htmlspecialchars($code_k); ?>"
                                    data-montant="<?php echo htmlspecialchars((string) $m); ?>">
                                    <i class="fas fa-pen-to-square"></i> Modifier
                                </button>
                                <?php if ($has_plafond): ?>
                                    <form method="post" class="pct-delete-form-inline" onsubmit="return confirm('Supprimer ce plafond ? Le type <?php echo htmlspecialchars($meta['lbl'], ENT_QUOTES, 'UTF-8'); ?> n\'aura plus de limite cumul.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                        <input type="hidden" name="action_pct_bl" value="delete">
                                        <input type="hidden" name="code_type" value="<?php echo htmlspecialchars($code_k); ?>">
                                        <button type="submit" class="btn-secondary pct-btn-delete">
                                            <i class="fas fa-trash-alt"></i> Supprimer
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="pct-no-delete" title="Aucun plafond défini pour ce type"><i class="fas fa-minus-circle"></i> —</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal position absolute dans la page -->
        <div class="pct-modal-overlay" id="pct-modal-overlay" aria-hidden="true"></div>
        <div class="pct-modal-box" id="pct-modal-box" role="dialog" aria-modal="true" aria-labelledby="pct-modal-title">
            <div class="pct-modal-box__head">
                <h2 id="pct-modal-title">Plafond par type</h2>
                <button type="button" class="pct-modal-close" id="pct-modal-close" aria-label="Fermer">&times;</button>
            </div>
            <form method="post" class="pct-modal-box__body">
                <input type="hidden" name="action_pct_bl" value="save">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                <div class="pct-form-row">
                    <label for="code_type_sel">Type de client</label>
                    <select name="code_type" id="code_type_sel">
                        <option value="standard">Standard</option>
                        <option value="vip">VIP</option>
                    </select>
                </div>
                <div class="pct-form-row">
                    <label for="montant_pct">Montant max cumul BL (HT, FCFA)</label>
                    <input type="number" step="0.01" min="0" name="montant_plafond_ht" id="montant_pct" value="0" required>
                    <p class="pct-form-hint">0 = pas de plafond pour ce type.</p>
                </div>
                <div class="pct-modal-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    <button type="button" class="btn-secondary" id="pct-modal-cancel">Annuler</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function() {
        var btn = document.getElementById('btn-open-pct-modal');
        var overlay = document.getElementById('pct-modal-overlay');
        var box = document.getElementById('pct-modal-box');
        var cls = document.getElementById('pct-modal-close');
        var cancel = document.getElementById('pct-modal-cancel');
        var selCode = document.getElementById('code_type_sel');
        var inpMontant = document.getElementById('montant_pct');
        function openM(reset) {
            if (overlay && box) {
                if (reset) {
                    if (selCode) selCode.value = 'standard';
                    if (inpMontant) inpMontant.value = '0';
                }
                overlay.classList.add('is-open');
                box.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
            }
        }
        function closeM() {
            if (overlay && box) {
                overlay.classList.remove('is-open');
                box.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
            }
        }
        if (btn) btn.addEventListener('click', function() { openM(true); });
        if (cls) cls.addEventListener('click', closeM);
        if (cancel) cancel.addEventListener('click', closeM);
        if (overlay) overlay.addEventListener('click', closeM);
        document.querySelectorAll('.pct-btn-edit').forEach(function(b) {
            b.addEventListener('click', function() {
                if (selCode && b.dataset.code) selCode.value = (b.dataset.code === 'vip') ? 'vip' : 'standard';
                if (inpMontant && b.dataset.montant !== undefined && b.dataset.montant !== '') {
                    inpMontant.value = parseFloat(String(b.dataset.montant).replace(',', '.')).toFixed(2);
                } else if (inpMontant) inpMontant.value = '0';
                openM(false);
            });
        });
    })();
    </script>
</body>
</html>
