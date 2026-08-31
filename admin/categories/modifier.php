<?php
/**
 * MODIFIER UNE CATÉGORIE RACINE — au patron FPL (form-card). Le moteur ne
 * bouge pas : process_update_categorie() valide et enregistre, en gardant
 * l'ancienne image si aucune n'est déposée.
 * Programmation procédurale uniquement
 *
 * En plus du rayon : la COULEUR D'ÉTIQUETTE STOCK FPL (bandeau, écusson,
 * pastilles véhicules de la fiche « Ajuster le stock »). Laisser le bleu
 * par défaut = couleur calculée automatiquement pour la catégorie.
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

// Récupérer l'ID de la catégorie
$categorie_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($categorie_id <= 0) {
    header('Location: ../produits/index.php');
    exit;
}

// Récupérer la catégorie
require_once __DIR__ . '/../../models/model_categories.php';
$categorie = get_categorie_by_id($categorie_id);

if (!$categorie) {
    header('Location: ../produits/index.php');
    exit;
}

// Traiter le formulaire
require_once __DIR__ . '/../../controllers/controller_categories.php';
$result = process_update_categorie($categorie_id);

// Si la modification est réussie, retour au catalogue — la carte corrigée sous les yeux.
if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: ../produits/index.php');
    exit;
}

$erreur = (isset($result['message']) && $result['message'] !== '' && empty($result['success']))
    ? (string) $result['message'] : '';

// Reprise de saisie sur erreur, sinon la valeur en base
$val_nom = isset($_POST['nom']) ? (string) $_POST['nom'] : fpl_texte((string) $categorie['nom']);
$val_desc = isset($_POST['description']) ? (string) $_POST['description'] : fpl_texte((string) ($categorie['description'] ?? ''));

// Couleur d'étiquette : #RRGGBB, bleu FPL par défaut
$couleur_val = preg_match('/^#[0-9A-Fa-f]{6}$/i', (string) ($categorie['couleur_etiquette'] ?? ''))
    ? strtoupper((string) $categorie['couleur_etiquette']) : '#1E3A5F';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['couleur_etiquette'])
    && preg_match('/^#[0-9A-Fa-f]{6}$/i', trim((string) $_POST['couleur_etiquette']))) {
    $couleur_val = strtoupper(trim((string) $_POST['couleur_etiquette']));
}

$image_actuelle = trim((string) ($categorie['image'] ?? ''));
$retour = '../produits/index.php';
$fpl_titre_page = 'Modifier « ' . fpl_texte((string) $categorie['nom']) . ' »';
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
        Identité
      </h3>

      <?php if ($erreur !== '') : ?>
        <div class="alert warn" style="margin-bottom:var(--s3)"><?php echo $erreur; ?></div>
      <?php endif; ?>

      <div class="field">
        <label for="nom">Nom <span class="req">*</span></label>
        <input type="text" id="nom" name="nom" value="<?php echo e($val_nom); ?>" required minlength="2" autofocus>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="2"><?php echo e($val_desc); ?></textarea>
      </div>
    </div>

    <div class="form-block">
      <h3>
        <?php echo fpl_icone('tag', 14); ?> Couleur d'étiquette stock FPL
      </h3>
      <div class="field">
        <label for="couleur_etiquette">Teinte de l'étiquette</label>
        <input type="color" id="couleur_etiquette" name="couleur_etiquette" value="<?php echo e($couleur_val); ?>"
               style="max-width:120px;height:44px;padding:4px;cursor:pointer">
        <div class="help">
          Bandeau vertical, bande du haut, écusson, pied de page et pastilles véhicules des étiquettes FPL
          (fiche « Ajuster le stock »). Laissé sur le bleu foncé par défaut (#1E3A5F), une couleur est
          calculée automatiquement pour cette catégorie ; choisissez une teinte ci-dessus pour l'imposer.
        </div>
      </div>
    </div>

    <div class="form-block">
      <h3>
        <?php echo fpl_icone('image', 14); ?> Image
        <span class="hint-inline">Laissez vide pour conserver l'actuelle</span>
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
            <img src="../../upload/<?php echo e($image_actuelle); ?>" alt="">
            <span class="tag">Actuelle</span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="form-bar" style="max-width:640px">
    <button type="submit" class="btn btn-primary">
      <?php echo fpl_icone('save', 14); ?>
      Enregistrer
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
