<?php
/**
 * Page logos partenaires + onglet fournisseurs
 * Programmation procédurale uniquement
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_logos.php';
require_once __DIR__ . '/../../models/model_fournisseurs.php';

$logos = get_all_logos(null);
$fournisseurs = get_all_fournisseurs_ordered_by_nom();

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'fournisseurs') ? 'fournisseurs' : 'logos';
$error_message = '';
$success_message = '';

/** @param string $csrf */
function parametres_logos_verify_csrf($csrf)
{
    $expected = isset($_SESSION['admin_csrf']) ? (string) $_SESSION['admin_csrf'] : '';
    return $csrf !== '' && $expected !== '' && hash_equals($expected, $csrf);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if (!parametres_logos_verify_csrf($token)) {
        $error_message = 'Session expirée ou jeton de sécurité invalide. Rechargez la page.';
        if (isset($_POST['redirect_tab']) && $_POST['redirect_tab'] === 'fournisseurs') {
            $active_tab = 'fournisseurs';
        }
    } elseif (!empty($_POST['add_fournisseur'])) {
        require_once __DIR__ . '/../../controllers/controller_fournisseurs.php';
        $result = process_admin_add_fournisseur();
        if (isset($result['success']) && $result['success']) {
            $_SESSION['success_message'] = $result['message'];
            header('Location: logos.php?tab=fournisseurs');
            exit;
        }
        $error_message = isset($result['message']) ? $result['message'] : 'Erreur.';
        $active_tab = 'fournisseurs';
    } else {
        require_once __DIR__ . '/../../controllers/controller_logos.php';

        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $result = process_delete_logo();
        } elseif (!empty($_POST['add_logo'])) {
            $result = process_add_logo();
        } elseif (!empty($_POST['update_logo'])) {
            $result = process_update_logo();
        } else {
            $result = ['success' => false, 'message' => ''];
        }

        if (isset($result['success']) && $result['success']) {
            $_SESSION['success_message'] = $result['message'];
            header('Location: logos.php');
            exit;
        }
        if (isset($result['message']) && $result['message'] !== '') {
            $error_message = $result['message'];
        }
    }
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$logo_to_edit = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $logo_to_edit = get_logo_by_id((int) $_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logos &amp; fournisseurs - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .logos-page-nav { display: flex; gap: 0; margin-bottom: 24px; border-bottom: 2px solid var(--glass-border); flex-wrap: wrap; }
        .logos-page-nav a {
            display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px;
            color: var(--texte-mute); text-decoration: none; font-weight: 600; font-size: 15px;
            border-bottom: 3px solid transparent; margin-bottom: -2px; transition: color 0.2s, border-color 0.2s;
        }
        .logos-page-nav a:hover { color: var(--couleur-dominante); }
        .logos-page-nav a.is-active { color: var(--couleur-dominante); border-bottom-color: var(--couleur-dominante); }
        .tab-panel { display: none; }
        .tab-panel.is-active { display: block; }
        .logos-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
        .logos-header h2 { margin: 0; font-size: 24px; color: var(--titres); }
        .logos-header p { margin: 5px 0 0; color: var(--texte-fonce); font-size: 14px; }
        .btn-add-logo, .btn-add-fournisseur {
            display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px;
            background: var(--couleur-dominante); color: #fff; border: none; border-radius: 10px;
            font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.3s;
        }
        .btn-add-logo:hover, .btn-add-fournisseur:hover { background: var(--couleur-dominante-hover); transform: translateY(-2px); }
        .logos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
        .logo-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        .logo-card-preview { width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.5); border-radius: 10px; margin-bottom: 12px; }
        .logo-card-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .logo-card-meta { font-size: 12px; color: var(--texte-fonce); margin-bottom: 12px; }
        .logo-card-actions { display: flex; gap: 8px; width: 100%; justify-content: center; flex-wrap: wrap; }
        .logo-card-actions .btn-edit, .logo-card-actions .btn-delete {
            padding: 8px 14px; border-radius: 8px; font-size: 12px; text-decoration: none; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: all 0.3s;
        }
        .logo-card-actions .btn-edit { background: var(--bleu-pale); color: var(--couleur-dominante); }
        .logo-card-actions .btn-edit:hover { background: rgba(53, 100, 166, 0.2); }
        .logo-card-actions .btn-delete { background: rgba(255, 107, 53, 0.12); color: var(--orange-fonce); }
        .logo-card-actions .btn-delete:hover { background: rgba(255, 107, 53, 0.22); }
        .empty-state { text-align: center; padding: 60px 20px; background: var(--glass-bg); border: 1px dashed var(--glass-border); border-radius: 16px; }
        .empty-state i { font-size: 48px; color: var(--couleur-dominante); opacity: 0.6; margin-bottom: 15px; }
        .empty-state h3 { margin: 0 0 8px; color: var(--titres); }
        .empty-state p { margin: 0; color: var(--texte-fonce); }
        .modal-overlay.modal-fullscreen .modal-content { max-width: 95%; width: 600px; }
        .logo-preview-box { width: 100%; min-height: 180px; border: 2px dashed var(--border-input); border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.5); margin-top: 10px; }
        .logo-preview-box img { max-width: 100%; max-height: 200px; object-fit: contain; }
        /* Modal fournisseur : overlay fixe + panneau en position absolute centré */
        .fournisseur-modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 10002;
            background: rgba(13, 13, 13, 0.45); align-items: center; justify-content: center;
        }
        .fournisseur-modal-overlay.is-open { display: flex; }
        .fournisseur-modal-wrap { position: relative; width: 100%; max-width: 440px; min-height: 200px; }
        .fournisseur-modal-panel {
            position: absolute;
            left: 50%; top: 50%;
            transform: translate(-50%, -50%);
            width: 100%; max-width: 420px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow), 0 24px 48px rgba(0,0,0,0.12);
            padding: 24px;
        }
        .fournisseur-modal-panel h3 { margin: 0 0 16px; font-size: 1.15rem; color: var(--titres); display: flex; align-items: center; gap: 10px; }
        .fournisseur-modal-close {
            position: absolute; top: 12px; right: 12px; border: none; background: transparent; cursor: pointer;
            font-size: 22px; line-height: 1; color: var(--texte-mute); padding: 8px; border-radius: 8px;
        }
        .fournisseur-modal-close:hover { color: var(--titres); background: var(--bleu-pale); }
        .fournisseur-list-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--glass-border); background: var(--glass-bg); }
        table.fournisseur-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.fournisseur-table th, table.fournisseur-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--glass-border); }
        table.fournisseur-table th { background: var(--blanc-neige); color: var(--titres); font-weight: 600; }
        table.fournisseur-table tr:last-child td { border-bottom: none; }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <section class="produits-section">
        <nav class="logos-page-nav" aria-label="Sections">
            <a href="logos.php" class="<?php echo $active_tab === 'logos' ? 'is-active' : ''; ?>">
                <i class="fas fa-image"></i> Logos partenaires
            </a>
            <a href="logos.php?tab=fournisseurs" class="<?php echo $active_tab === 'fournisseurs' ? 'is-active' : ''; ?>">
                <i class="fas fa-truck-field"></i> Fournisseurs
            </a>
        </nav>

        <?php if (!empty($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <div id="panel-logos" class="tab-panel <?php echo $active_tab === 'logos' ? 'is-active' : ''; ?>">
            <div class="logos-header">
                <div>
                    <h2><i class="fas fa-image"></i> Logos affichés en carrousel</h2>
                    <p>Gérez les logos de la page d'accueil</p>
                </div>
                <button type="button" class="btn-add-logo" onclick="openLogoModal()">
                    <i class="fas fa-plus"></i> Ajouter un logo
                </button>
            </div>

            <?php if (empty($logos)): ?>
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <h3>Aucun logo pour le moment</h3>
                <p>Cliquez sur « Ajouter un logo » pour commencer</p>
            </div>
            <?php else: ?>
            <div class="logos-grid">
                <?php foreach ($logos as $logo): ?>
                <div class="logo-card">
                    <div class="logo-card-preview">
                        <?php
                        $img_path = '/upload/' . htmlspecialchars($logo['image']);
                        ?>
                        <img src="<?php echo $img_path; ?>" alt="Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <span style="display: none; font-size: 36px; color: #999;"><i class="fas fa-image"></i></span>
                    </div>
                    <div class="logo-card-meta">
                        <span class="<?php echo $logo['statut'] === 'actif' ? 'badge-actif' : 'badge-inactif'; ?>">
                            <?php echo strtoupper($logo['statut']); ?>
                        </span>
                        · Ordre: <?php echo (int) $logo['ordre']; ?>
                    </div>
                    <div class="logo-card-actions">
                        <a href="?edit=<?php echo $logo['id']; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce logo ?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="logo_id" value="<?php echo $logo['id']; ?>">
                            <button type="submit" class="btn-delete">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="panel-fournisseurs" class="tab-panel <?php echo $active_tab === 'fournisseurs' ? 'is-active' : ''; ?>">
            <div class="logos-header">
                <div>
                    <h2><i class="fas fa-truck-field"></i> Fournisseurs</h2>
                    <p>Liste utilisée lors de la création ou modification d’un produit</p>
                </div>
                <button type="button" class="btn-add-fournisseur" onclick="openFournisseurModal()">
                    <i class="fas fa-plus"></i> Ajouter un fournisseur
                </button>
            </div>

            <?php if (empty($fournisseurs)): ?>
            <div class="empty-state">
                <i class="fas fa-building"></i>
                <h3>Aucun fournisseur</h3>
                <p>Ajoutez un fournisseur pour le retrouver dans les fiches produits</p>
            </div>
            <?php else: ?>
            <div class="fournisseur-list-wrap">
                <table class="fournisseur-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Créé le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fournisseurs as $f): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['nom']); ?></td>
                            <td><?php echo htmlspecialchars($f['date_creation'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Modal logos -->
    <div class="modal-overlay modal-fullscreen" id="logoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-<?php echo $logo_to_edit ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $logo_to_edit ? 'Modifier le logo' : 'Ajouter un logo'; ?>
                </h3>
                <button type="button" class="modal-close" onclick="closeLogoModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="logoForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                <?php if ($logo_to_edit): ?>
                <input type="hidden" name="logo_id" value="<?php echo (int) $logo_to_edit['id']; ?>">
                <input type="hidden" name="update_logo" value="1">
                <?php else: ?>
                <input type="hidden" name="add_logo" value="1">
                <?php endif; ?>

                <?php if (!empty($error_message) && $active_tab === 'logos'): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="image">
                        <i class="fas fa-image"></i> Image du logo <?php echo $logo_to_edit ? '(laisser vide pour conserver)' : '*'; ?>
                    </label>
                    <div class="file-input-wrapper">
                        <label for="image" class="file-input-label">
                            <i class="fas fa-upload"></i>
                            <span><?php echo $logo_to_edit ? 'Changer l\'image' : 'Choisir une image'; ?></span>
                        </label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="previewLogo(this)">
                    </div>
                    <small style="display: block; color: #666; font-size: 12px; margin-top: 5px;">
                        JPG, PNG, GIF, WebP (max 2 Mo)
                    </small>
                    <div id="logoPreview" class="logo-preview-box">
                        <?php if ($logo_to_edit && !empty($logo_to_edit['image'])): ?>
                        <img src="/upload/<?php echo htmlspecialchars($logo_to_edit['image']); ?>" alt="Aperçu" id="previewImg">
                        <?php else: ?>
                        <span style="color: #999;"><i class="fas fa-image"></i> Aperçu</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="ordre"><i class="fas fa-sort-numeric-down"></i> Ordre d'affichage</label>
                    <input type="number" id="ordre" name="ordre" min="0" value="<?php echo $logo_to_edit ? (int) $logo_to_edit['ordre'] : 0; ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> <?php echo $logo_to_edit ? 'Mettre à jour' : 'Ajouter'; ?>
                    </button>
                    <a href="logos.php" class="btn-cancel" onclick="closeLogoModal(); return false;">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal fournisseur (panneau absolute) -->
    <div class="fournisseur-modal-overlay" id="fournisseurModal" role="dialog" aria-modal="true" aria-labelledby="fournisseurModalTitle">
        <div class="fournisseur-modal-wrap">
            <div class="fournisseur-modal-panel">
                <button type="button" class="fournisseur-modal-close" onclick="closeFournisseurModal()" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
                <h3 id="fournisseurModalTitle"><i class="fas fa-plus-circle"></i> Nouveau fournisseur</h3>

                <?php if (!empty($error_message) && $active_tab === 'fournisseurs'): ?>
                <div class="message error" style="margin-bottom: 16px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="logos.php?tab=fournisseurs" id="formAddFournisseur">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <input type="hidden" name="add_fournisseur" value="1">
                    <input type="hidden" name="redirect_tab" value="fournisseurs">
                    <div class="form-group">
                        <label for="fournisseur_nom">Nom du fournisseur *</label>
                        <input type="text" id="fournisseur_nom" name="fournisseur_nom" required maxlength="255"
                            placeholder="Raison sociale ou nom commercial"
                            value="<?php echo (isset($_POST['fournisseur_nom']) && $active_tab === 'fournisseurs') ? htmlspecialchars($_POST['fournisseur_nom']) : ''; ?>">
                    </div>
                    <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                        <button type="button" class="btn-cancel" onclick="closeFournisseurModal()"><i class="fas fa-times"></i> Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        function openLogoModal() {
            document.getElementById('logoModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            <?php if (!$logo_to_edit): ?>
            var lf = document.getElementById('logoForm');
            if (lf) {
                lf.reset();
                var pr = document.getElementById('logoPreview');
                if (pr) {
                    pr.innerHTML = '<span style="color: #999;"><i class="fas fa-image"></i> Aperçu</span>';
                }
            }
            <?php endif; ?>
        }

        function closeLogoModal() {
            document.getElementById('logoModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            window.location.href = 'logos.php';
        }

        function openFournisseurModal() {
            document.getElementById('fournisseurModal').classList.add('is-open');
            document.body.style.overflow = 'hidden';
            var inp = document.getElementById('fournisseur_nom');
            if (inp && !inp.value) { setTimeout(function() { inp.focus(); }, 50); }
        }

        function closeFournisseurModal() {
            document.getElementById('fournisseurModal').classList.remove('is-open');
            document.body.style.overflow = 'auto';
            var f = document.getElementById('formAddFournisseur');
            if (f) { f.reset(); }
        }

        function previewLogo(input) {
            var box = document.getElementById('logoPreview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    box.innerHTML = '<img src="' + e.target.result + '" alt="Aperçu" id="previewImg">';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                <?php if ($logo_to_edit && !empty($logo_to_edit['image'])): ?>
                box.innerHTML = '<img src="/upload/<?php echo htmlspecialchars($logo_to_edit['image']); ?>" alt="Aperçu" id="previewImg">';
                <?php else: ?>
                box.innerHTML = '<span style="color: #999;"><i class="fas fa-image"></i> Aperçu</span>';
                <?php endif; ?>
            }
        }

        <?php if ($logo_to_edit): ?>
        document.addEventListener('DOMContentLoaded', function() { openLogoModal(); });
        <?php endif; ?>

        <?php if ($active_tab === 'fournisseurs' && !empty($error_message)): ?>
        document.addEventListener('DOMContentLoaded', function() { openFournisseurModal(); });
        <?php endif; ?>
    </script>
</body>

</html>
