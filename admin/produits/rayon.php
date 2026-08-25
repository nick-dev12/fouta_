<?php
/**
 * LE RAYON (sous-catégorie) — créer, modifier, supprimer, SANS QUITTER LE
 * CATALOGUE : c'est la cible du crayon, de la poubelle et de la carte
 * « Nouvelle sous-catégorie » du bandeau de index.php quand une catégorie
 * est ouverte.
 *   rayon.php?categorie_id=12      → nouvelle sous-catégorie de la catégorie 12
 *   rayon.php?id=27                → modifier le rayon 27 (nom, mots-clés,
 *                                    description, image)
 *   POST action=supprimer&id=27    → supprimer (jeton csrf obligatoire)
 * Programmation procédurale uniquement
 *
 * Portage de fpl_natif/admin/categorie-nouvelle.php + categorie-modifier.php
 * + includes/categorie_form.php, pour les SOUS-catégories. Les catégories
 * racines gardent leurs écrans à elles (admin/categories/). Pas de numéro
 * ici : ce dépôt n'a pas la colonne `code`. Pas de brouillon non plus : le
 * formulaire est court.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../includes/fouta_upload_limits.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';

// Gérer les rayons, c'est gérer le catalogue : le compte restreint n'y touche pas.
if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['admin_csrf'];
$jeton_ok = function () use ($csrf) {
    $t = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    return $t !== '' && hash_equals($csrf, $t);
};

if (!sous_categories_table_ok()) {
    $_SESSION['success_message'] = 'Les sous-catégories ne sont pas disponibles sur cette base.';
    header('Location: index.php');
    exit;
}

// ---------------------------------------------------------------------
// Qui est-on venu voir ? Un rayon existant (id) ou le parent d'un rayon à créer
// ---------------------------------------------------------------------
$rayon = null;
$parent = null;
$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
if ($id > 0) {
    $rayon = get_sous_categorie_by_id($id);
    if (!$rayon) {
        $_SESSION['success_message'] = 'Ce rayon n\'existe plus.';
        header('Location: index.php');
        exit;
    }
    $parent = get_categorie_by_id((int) $rayon['categorie_id']);
} else {
    $pid = isset($_REQUEST['categorie_id']) ? (int) $_REQUEST['categorie_id'] : 0;
    $parent = $pid > 0 ? get_categorie_by_id($pid) : null;
}
if (!$parent) {
    $_SESSION['success_message'] = 'Ouvrez d\'abord une catégorie, puis cliquez « Nouvelle sous-catégorie ».';
    header('Location: index.php');
    exit;
}
$parent_id = (int) $parent['id'];
$parent_nom = fpl_texte((string) $parent['nom']);
$retour = 'index.php?categorie_id=' . $parent_id;

// ---------------------------------------------------------------------
// SUPPRIMER (POST, jeton) — les pièces du rayon ne disparaissent pas :
// elles perdent seulement ce classement (clé étrangère SET NULL).
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer' && $rayon) {
    if (!$jeton_ok()) {
        $_SESSION['success_message'] = 'Session expirée — la suppression n\'a pas été faite. Réessayez.';
        header('Location: ' . $retour);
        exit;
    }
    $nom_supprime = fpl_texte((string) $rayon['nom']);
    if (delete_sous_categorie((int) $rayon['id'])) {
        $_SESSION['success_message'] = 'Sous-catégorie « ' . $nom_supprime . ' » supprimée — ses pièces restent, sans ce classement.';
    } else {
        $_SESSION['success_message'] = 'La suppression de « ' . $nom_supprime . ' » a échoué.';
    }
    header('Location: ' . $retour);
    exit;
}

// ---------------------------------------------------------------------
// CRÉER / MODIFIER (POST, jeton)
// ---------------------------------------------------------------------
$erreurs = [];
$saisies = [
    'nom' => isset($_POST['nom']) ? (string) $_POST['nom'] : ($rayon ? fpl_texte((string) $rayon['nom']) : ''),
    'mots_cles' => isset($_POST['mots_cles']) ? (string) $_POST['mots_cles'] : ($rayon ? (string) ($rayon['mots_cles'] ?? '') : ''),
    'description' => isset($_POST['description']) ? (string) $_POST['description'] : ($rayon ? fpl_texte((string) ($rayon['description'] ?? '')) : ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'supprimer')) {
    if (!$jeton_ok()) {
        $erreurs['general'] = 'Session expirée — rien n\'a été enregistré. Réessayez.';
    }
    $nom = trim($saisies['nom']);
    if (mb_strlen($nom) < 2 || mb_strlen($nom) > 190) {
        $erreurs['nom'] = mb_strlen($nom) === 0 ? 'Le nom de la sous-catégorie est obligatoire.'
            : 'Le nom doit faire au moins 2 caractères.';
    } else {
        // Unicité du nom au sein du même parent
        foreach (get_sous_categories_by_categorie_id($parent_id) as $sc) {
            if ((!$rayon || (int) $sc['id'] !== (int) $rayon['id'])
                && mb_strtolower(fpl_texte((string) $sc['nom'])) === mb_strtolower($nom)) {
                $erreurs['nom'] = 'Une sous-catégorie porte déjà ce nom dans « ' . $parent_nom . ' ».';
                break;
            }
        }
    }
    $image = null;
    if (isset($_FILES['image']) && (int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $image = sous_categorie_image_enregistrer($_FILES['image']);
        if ($image === null) {
            $erreurs['image'] = 'L\'image doit être au format JPG, PNG, WEBP ou GIF, sous '
                . (int) fouta_upload_image_max_mo_int() . ' Mo.';
        }
    }

    if ($erreurs === []) {
        $champs = [
            'nom' => $nom,
            'categorie_id' => $parent_id,
            'description' => mb_substr(trim($saisies['description']), 0, 2000),
            'mots_cles' => mb_substr(trim($saisies['mots_cles']), 0, 500),
        ];
        if ($image !== null) {
            $champs['image'] = $image;
        }
        if ($rayon) {
            $ok = update_sous_categorie((int) $rayon['id'], $champs);
            $message = 'Sous-catégorie « ' . $nom . ' » mise à jour' . ($image !== null ? ' — nouvelle image en place' : '') . '.';
        } else {
            $ok = create_sous_categorie($champs) !== false;
            $message = 'Sous-catégorie « ' . $nom . ' » créée.';
        }
        if ($ok) {
            // On revient LÀ OÙ LA CARTE S'AFFICHE — pour VOIR le changement
            $_SESSION['success_message'] = $message;
            header('Location: ' . $retour);
            exit;
        }
        $erreurs['general'] = 'L\'enregistrement a échoué — réessayez.';
    }
}

$existe = $rayon !== null;
$image_actuelle = $existe ? trim((string) ($rayon['image'] ?? '')) : '';
if (!function_exists('get_public_root_uri_path')) {
    require_once __DIR__ . '/../../includes/site_url.php';
}
$upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';

$fpl_titre_page = $existe ? 'Modifier « ' . fpl_texte((string) $rayon['nom']) . ' »' : 'Nouvelle sous-catégorie';
$fpl_retour_page = $retour;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($fpl_titre_page); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

<form method="POST" action="" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">
  <?php if ($existe) : ?><input type="hidden" name="id" value="<?php echo (int) $rayon['id']; ?>"><?php endif; ?>
  <input type="hidden" name="categorie_id" value="<?php echo $parent_id; ?>">

  <div class="form-card" style="max-width:640px">
    <div class="form-block">
      <h3>
        <?php echo fpl_icone('package', 14); ?>
        Sous-catégorie de « <?php echo e($parent_nom); ?> »
      </h3>

      <?php if (isset($erreurs['general'])) : ?>
        <div class="alert warn" style="margin-bottom:var(--s3)"><?php echo e($erreurs['general']); ?></div>
      <?php endif; ?>

      <div class="field">
        <label for="nom">Nom <span class="req">*</span></label>
        <input type="text" id="nom" name="nom" value="<?php echo e($saisies['nom']); ?>" required minlength="2"
               placeholder="Ex. Filtres à air" autofocus>
        <?php if (isset($erreurs['nom'])) : ?><div class="error"><?php echo e($erreurs['nom']); ?></div><?php endif; ?>
      </div>

      <?php if (sous_categories_has_column('mots_cles')) : ?>
      <div class="field">
        <label for="mots_cles">Mots-clés de recherche</label>
        <input type="text" id="mots_cles" name="mots_cles" value="<?php echo e($saisies['mots_cles']); ?>"
               placeholder="filtre, filter, gasoil, gazoil…">
        <div class="help">Séparés par des virgules — pour retrouver ce rangement sans connaître son nom exact (« Ajouter une pièce par son nom »).</div>
      </div>
      <?php endif; ?>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="2"
                  placeholder="Facultative"><?php echo e($saisies['description']); ?></textarea>
      </div>
    </div>

    <?php if (sous_categories_has_column('image')) : ?>
    <div class="form-block">
      <h3>
        <?php echo fpl_icone('image', 14); ?> Image
        <span class="hint-inline">Pour reconnaître les pièces sans lire le nom</span>
      </h3>

      <label class="dropzone" id="dz">
        <span class="dz-icon"><?php echo fpl_icone('image', 18); ?></span>
        <span class="dz-title"><?php echo $image_actuelle !== '' ? 'Remplacer l\'image' : 'Cliquez ou déposez une image'; ?></span>
        <span class="dz-sub">JPG, PNG, WEBP — <?php echo (int) fouta_upload_image_max_mo_int(); ?> Mo max</span>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
      </label>

      <div class="previews" id="previews">
        <?php if ($image_actuelle !== '') : ?>
          <div class="preview">
            <img src="<?php echo e($upload_base . ltrim($image_actuelle, '/')); ?>" alt="">
            <span class="tag">Actuelle</span>
          </div>
        <?php endif; ?>
      </div>
      <?php if (isset($erreurs['image'])) : ?><div class="error"><?php echo e($erreurs['image']); ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="form-bar" style="max-width:640px">
    <button type="submit" class="btn btn-primary">
      <?php echo fpl_icone('save', 14); ?>
      <?php echo $existe ? 'Enregistrer' : 'Créer la sous-catégorie'; ?>
    </button>
    <a href="<?php echo e($retour); ?>" class="btn btn-outline">Annuler</a>
  </div>
</form>

    </div><!-- .page-produits-admin -->

<script>
  // Aperçu de l'image déposée
  (function () {
    const input = document.getElementById('image');
    const zone = document.getElementById('dz');
    const grid = document.getElementById('previews');
    if (!input || !zone || !grid) return;

    function show() {
      const f = input.files?.[0];
      if (!f) return;
      grid.innerHTML = '';
      const item = document.createElement('div');
      item.className = 'preview';
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.onload = () => URL.revokeObjectURL(img.src);
      const tag = document.createElement('span');
      tag.className = 'tag';
      tag.textContent = 'Nouvelle';
      const rm = document.createElement('button');
      rm.type = 'button'; rm.className = 'rm'; rm.innerHTML = '&times;'; rm.title = 'Retirer';
      rm.addEventListener('click', (e) => { e.preventDefault(); input.value = ''; grid.innerHTML = ''; });
      item.append(img, tag, rm);
      grid.appendChild(item);
    }

    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('over'); }));
    zone.addEventListener('drop', e => {
      const dt = new DataTransfer();
      const f = [...e.dataTransfer.files].find(x => x.type.startsWith('image/'));
      if (f) { dt.items.add(f); input.files = dt.files; show(); }
    });
  })();
</script>

    <?php include '../includes/footer.php'; ?>
