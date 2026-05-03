<?php
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh'], true)) {
    header('Location: ../../dashboard.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../../models/model_employes.php';
require_once __DIR__ . '/../../../controllers/controller_employes.php';
require_once __DIR__ . '/../../../models/model_admin.php';
require_once __DIR__ . '/../../../includes/site_url.php';

$employe = get_employe_by_id($id);
if (!$employe) {
    header('Location: index.php');
    exit;
}

$result = process_employe_modification($id);
if (!empty($result['success'])) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}

$admins = get_all_admins();
$error_msg = isset($result['message']) && !$result['success'] ? $result['message'] : '';

$p = $_POST;
if (empty($p)) {
    $p = $employe;
}

$upload_public = rtrim(get_request_origin_base_url(), '/') . '/upload/';
$upload_disk = __DIR__ . '/../../../upload/';
$curr_ph = trim((string) ($employe['photo_chemin'] ?? ''));
$curr_ph_ok = $curr_ph !== '' && strpos($curr_ph, '..') === false
    && is_file($upload_disk . str_replace('/', DIRECTORY_SEPARATOR, $curr_ph));
$photo_field = employe_photo_champ_fichier();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier employé — Administration</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employes-rh.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-user-edit"></i> Modifier la fiche</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <section class="content-section" style="max-width: 640px;">
        <?php if ($error_msg): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <span><?php echo $error_msg; ?></span></div>
        <?php endif; ?>

        <form method="post" class="form-add er-modifier-employe-form" style="background: var(--glass-bg, rgba(255,255,255,.7)); padding: 24px; border-radius: 12px;" enctype="multipart/form-data">
            <input type="hidden" name="modifier_employe" value="1">
            <input type="hidden" name="MAX_FILE_SIZE" value="4194304">
            <div class="form-group">
                <label for="nom">Nom *</label>
                <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($p['nom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="prenom">Prénom *</label>
                <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($p['prenom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($p['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="text" id="telephone" name="telephone" value="<?php echo htmlspecialchars($p['telephone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="poste">Poste</label>
                <input type="text" id="poste" name="poste" value="<?php echo htmlspecialchars($p['poste'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="service">Service</label>
                <input type="text" id="service" name="service" value="<?php echo htmlspecialchars($p['service'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="date_embauche">Date d’embauche</label>
                <input type="date" id="date_embauche" name="date_embauche" value="<?php echo !empty($p['date_embauche']) ? htmlspecialchars(substr($p['date_embauche'], 0, 10)) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <?php foreach (['actif' => 'Actif', 'inactif' => 'Inactif', 'suspendu' => 'Suspendu'] as $k => $lab): ?>
                    <option value="<?php echo $k; ?>" <?php echo (($p['statut'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $lab; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="admin_id">Compte d’accès interne (optionnel)</label>
                <select id="admin_id" name="admin_id">
                    <option value="0">— Aucun —</option>
                    <?php foreach ($admins as $a): ?>
                    <option value="<?php echo (int)$a['id']; ?>" <?php echo (int)($p['admin_id'] ?? 0) === (int)$a['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['prenom'] . ' ' . $a['nom'] . ' (' . $a['email'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="notes">Notes internes</label>
                <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($p['notes'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="<?php echo htmlspecialchars($photo_field); ?>">Photo de l’employé <span class="er-opt">(optionnel, max 4 Mo)</span></label>
                <?php if ($curr_ph_ok): ?>
                    <p class="er-photo-modifier-actuelle">Photo actuelle :</p>
                    <div class="er-photo-modifier-thumb">
                        <img src="<?php echo htmlspecialchars($upload_public . $curr_ph); ?>" alt="Photo actuelle" width="120" height="120" decoding="async" class="er-photo-preview er-photo-preview--current">
                    </div>
                <?php endif; ?>
                <input type="file" id="<?php echo htmlspecialchars($photo_field); ?>" name="<?php echo htmlspecialchars($photo_field); ?>" accept="image/jpeg,image/png,image/webp,image/gif">
                <p class="er-photo-hint" style="margin-top:8px;">Laissez vide pour conserver la photo actuelle. JPG, PNG, WEBP ou GIF.</p>
                <div class="er-photo-preview-wrap" id="photoPreviewWrapMod" hidden>
                    <p class="er-photo-modifier-actuelle">Nouvelle photo (aperçu) :</p>
                    <img src="" alt="Aperçu" class="er-photo-preview" id="photoPreviewImgMod" width="120" height="120">
                </div>
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        </form>
    </section>

    <?php include '../../includes/footer.php'; ?>
    <script>
    (function () {
        var input = document.getElementById(<?php echo json_encode($photo_field); ?>);
        var wrap = document.getElementById('photoPreviewWrapMod');
        var img = document.getElementById('photoPreviewImgMod');
        if (!input || !wrap || !img) return;
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) {
                wrap.hidden = true;
                img.removeAttribute('src');
                return;
            }
            var r = new FileReader();
            r.onload = function (e) {
                img.src = e.target.result || '';
                wrap.hidden = false;
            };
            r.readAsDataURL(input.files[0]);
        });
    })();
    </script>
</body>
</html>
