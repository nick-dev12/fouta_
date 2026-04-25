<?php
/**
 * Page de gestion des logos partenaires
 * Programmation procédurale uniquement
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../models/model_logos.php';
$logos = get_all_logos(null);

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    <title>Gestion des Logos - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .logos-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
        .logos-header h2 { margin: 0; font-size: 24px; color: var(--titres); }
        .logos-header p { margin: 5px 0 0; color: var(--texte-fonce); font-size: 14px; }
        .btn-add-logo { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--couleur-dominante); color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.3s; }
        .btn-add-logo:hover { background: var(--couleur-secondaire); transform: translateY(-2px); }
        .logos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
        .logo-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        .logo-card-preview { width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.5); border-radius: 10px; margin-bottom: 12px; }
        .logo-card-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .logo-card-meta { font-size: 12px; color: var(--texte-fonce); margin-bottom: 12px; }
        .logo-card-actions { display: flex; gap: 8px; width: 100%; justify-content: center; flex-wrap: wrap; }
        .logo-card-actions .btn-edit, .logo-card-actions .btn-delete { padding: 8px 14px; border-radius: 8px; font-size: 12px; text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.3s; }
        .logo-card-actions .btn-edit { background: rgba(229, 72, 138, 0.15); color: var(--couleur-dominante); }
        .logo-card-actions .btn-edit:hover { background: rgba(229, 72, 138, 0.25); }
        .logo-card-actions .btn-delete { background: rgba(247, 127, 0, 0.15); color: #c26638; }
        .logo-card-actions .btn-delete:hover { background: rgba(247, 127, 0, 0.3); }
        .empty-state { text-align: center; padding: 60px 20px; background: var(--glass-bg); border: 1px dashed var(--glass-border); border-radius: 16px; }
        .empty-state i { font-size: 48px; color: var(--couleur-dominante); opacity: 0.6; margin-bottom: 15px; }
        .empty-state h3 { margin: 0 0 8px; color: var(--titres); }
        .empty-state p { margin: 0; color: var(--texte-fonce); }
        .modal-overlay.modal-fullscreen .modal-content { max-width: 95%; width: 600px; }
        .logo-preview-box { width: 100%; min-height: 180px; border: 2px dashed rgba(229, 72, 138, 0.3); border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.5); margin-top: 10px; }
        .logo-preview-box img { max-width: 100%; max-height: 200px; object-fit: contain; }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <section class="produits-section">
        <div class="logos-header">
            <div>
                <h2><i class="fas fa-image"></i> Gestion des Logos Partenaires</h2>
                <p>Gérez les logos affichés en carrousel sur la page d'accueil</p>
            </div>
            <button class="btn-add-logo" onclick="openModal()">
                <i class="fas fa-plus"></i> Ajouter un logo
            </button>
        </div>

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

        <?php if (empty($logos)): ?>
        <div class="empty-state">
            <i class="fas fa-images"></i>
            <h3>Aucun logo pour le moment</h3>
            <p>Cliquez sur "Ajouter un logo" pour commencer</p>
        </div>
        <?php else: ?>
        <div class="logos-grid">
            <?php foreach ($logos as $logo): ?>
            <div class="logo-card">
                <div class="logo-card-preview">
                    <?php
                    $img_path = '/upload/' . htmlspecialchars($logo['image']);
                    $file_path = __DIR__ . '/../../upload/' . $logo['image'];
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
    </section>

    <!-- Modal plein écran pour ajout/modification -->
    <div class="modal-overlay modal-fullscreen" id="logoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-<?php echo $logo_to_edit ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $logo_to_edit ? 'Modifier le Logo' : 'Ajouter un Logo'; ?>
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="logoForm">
                <?php if ($logo_to_edit): ?>
                <input type="hidden" name="logo_id" value="<?php echo $logo_to_edit['id']; ?>">
                <input type="hidden" name="update_logo" value="1">
                <?php else: ?>
                <input type="hidden" name="add_logo" value="1">
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
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
                    <a href="logos.php" class="btn-cancel" onclick="closeModal(); return false;">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        function openModal(logoId) {
            document.getElementById('logoModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            if (!logoId) {
                document.getElementById('logoForm').reset();
                var box = document.getElementById('logoPreview');
                box.innerHTML = '<span style="color: #999;"><i class="fas fa-image"></i> Aperçu</span>';
            }
        }

        function closeModal() {
            document.getElementById('logoModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            window.location.href = 'logos.php';
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
        document.addEventListener('DOMContentLoaded', function() { openModal(<?php echo $logo_to_edit['id']; ?>); });
        <?php endif; ?>
    </script>
</body>

</html>
