<?php
/**
 * AJOUTER UNE CATÉGORIE RACINE — au patron FPL (form-card), comme le
 * rayon (sous-catégorie) et le wizard des pièces. Le moteur ne bouge pas :
 * c'est toujours process_add_categorie() qui valide et enregistre.
 * Programmation procédurale uniquement
 *
 * Portage de la mise en page de FPL natif (categorie-nouvelle.php +
 * includes/categorie_form.php). La couleur d'étiquette n'est pas demandée
 * ici : elle se calcule automatiquement à la création, puis se règle sur
 * l'écran « Modifier ». Pas de numéro (ce dépôt n'a pas la colonne `code`).
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/fouta_upload_limits.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';

// Traiter le formulaire
require_once __DIR__ . '/../../controllers/controller_categories.php';
$result = process_add_categorie();

// Si l'ajout est réussi, retour au catalogue — la carte neuve sous les yeux.
if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: ../produits/index.php');
    exit;
}

$erreur = (isset($result['message']) && $result['message'] !== '' && empty($result['success']))
    ? (string) $result['message'] : '';
$saisies = [
    'nom' => isset($_POST['nom']) ? (string) $_POST['nom'] : '',
    'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
];

$retour = '../produits/index.php';
$fpl_titre_page = 'Nouvelle catégorie';
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
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">

  <div class="form-card" style="max-width:640px">
    <div class="form-block">
      <h3>
        <?php echo fpl_icone('folder', 14); ?>
        Nouvelle catégorie
      </h3>

      <?php if ($erreur !== '') : ?>
        <div class="alert warn" style="margin-bottom:var(--s3)"><?php echo $erreur; ?></div>
      <?php endif; ?>

      <div class="field">
        <label for="nom">Nom <span class="req">*</span></label>
        <input type="text" id="nom" name="nom" value="<?php echo e($saisies['nom']); ?>" required minlength="2"
               placeholder="Ex. Freinage" autofocus>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="2"
                  placeholder="Facultative"><?php echo e($saisies['description']); ?></textarea>
      </div>
    </div>

    <div class="form-block">
      <h3>
        <?php echo fpl_icone('image', 14); ?> Image
        <span class="hint-inline">Pour reconnaître la catégorie d'un coup d'œil</span>
      </h3>

      <label class="dropzone" id="dz">
        <span class="dz-icon"><?php echo fpl_icone('image', 18); ?></span>
        <span class="dz-title">Cliquez ou déposez une image</span>
        <span class="dz-sub">JPG, PNG, WEBP — <?php echo (int) fouta_upload_image_max_mo_int(); ?> Mo max</span>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
      </label>

      <div class="previews" id="previews"></div>
    </div>
  </div>

  <div class="form-bar" style="max-width:640px">
    <button type="submit" class="btn btn-primary">
      <?php echo fpl_icone('save', 14); ?>
      Créer la catégorie
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
