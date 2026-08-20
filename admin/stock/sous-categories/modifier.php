<?php
/**
 * Modification d'une sous-catégorie
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';
require_once __DIR__ . '/../../../models/model_categories.php';
require_once __DIR__ . '/../../../models/model_sous_categories.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$sous = get_sous_categorie_by_id($id);
if (!$sous) {
    header('Location: index.php');
    exit;
}

$categories = get_all_categories();
$csrf = (string) $_SESSION['admin_csrf'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_sous_categorie'])) {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if ($tok === '' || !hash_equals($csrf, $tok)) {
        $errors[] = 'Session expirée ou jeton invalide.';
    } else {
        $post_id = isset($_POST['sous_categorie_id']) ? (int) $_POST['sous_categorie_id'] : 0;
        if ($post_id !== $id) {
            $errors[] = 'Identifiant incohérent.';
        } else {
            $nom = isset($_POST['nom']) ? trim((string) $_POST['nom']) : '';
            $cid = isset($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : 0;
            $desc = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
            if ($nom === '' || $cid <= 0 || !get_categorie_by_id($cid)) {
                $errors[] = 'Nom et catégorie parente valides requis.';
            } elseif (update_sous_categorie($id, [
                'nom' => $nom,
                'categorie_id' => $cid,
                'description' => $desc,
            ])) {
                $_SESSION['success_message_souscat'] = 'Sous-catégorie mise à jour.';
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Enregistrement impossible.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier sous-catégorie — Admin</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../..//includes/fpl_head.php'; ?>
    <style>
        .sc-mod { max-width: 640px; margin: 0 auto; padding: 1.25rem 1.25rem 3rem; }
        .sc-mod h1 { font-family: var(--font-titres); font-size: 1.45rem; margin: 0 0 1rem; color: var(--titres); display: flex; align-items: center; gap: 0.5rem; }
        .sc-mod .form-card { background: var(--fond-principal); border: 1px solid var(--border-input); border-radius: 14px; padding: 1.35rem; box-shadow: var(--glass-shadow); }
        .sc-mod label { display: block; font-weight: 600; margin-bottom: 0.35rem; font-size: 0.88rem; }
        .sc-mod input, .sc-mod select, .sc-mod textarea {
            width: 100%; padding: 0.55rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-input); font-family: inherit; margin-bottom: 1rem; box-sizing: border-box;
        }
        .sc-mod textarea { min-height: 100px; resize: vertical; }
        .sc-mod .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
        .sc-mod .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1rem; border-radius: 10px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; font-family: inherit; }
        .sc-mod .btn-primary { background: var(--couleur-dominante); color: var(--texte-clair); }
        .sc-mod .btn-ghost { background: var(--fond-secondaire); color: var(--titres); border: 1px solid var(--border-input); }
        .sc-mod .alert { padding: 0.75rem 1rem; border-radius: 10px; background: var(--error-bg); border: 1px solid var(--error-border); margin-bottom: 1rem; color: var(--titres); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="sc-mod">
        <h1><i class="fas fa-edit" aria-hidden="true"></i> Modifier la sous-catégorie</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert"><?php echo htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="post" action="modifier.php?id=<?php echo $id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="sous_categorie_id" value="<?php echo $id; ?>">

                <label for="nom">Nom *</label>
                <input id="nom" type="text" name="nom" required maxlength="190" value="<?php echo htmlspecialchars((string) ($sous['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                <label for="categorie_id">Catégorie parente *</label>
                <select id="categorie_id" name="categorie_id" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>" <?php echo ((int) ($sous['categorie_id'] ?? 0) === (int) $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $c['nom'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Facultatif"><?php echo htmlspecialchars((string) ($sous['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>

                <div class="actions">
                    <button type="submit" name="modifier_sous_categorie" value="1" class="btn btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i> Enregistrer
                    </button>
                    <a href="index.php" class="btn btn-ghost">Annuler</a>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
