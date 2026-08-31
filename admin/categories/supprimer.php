<?php
/**
 * SUPPRIMER UNE CATÉGORIE RACINE — au patron FPL (form-card, page de
 * confirmation). Le moteur ne bouge pas : process_delete_categorie()
 * refuse tant que la catégorie contient des pièces, puis efface l'image
 * et la catégorie.
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
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
require_once __DIR__ . '/../../models/model_sous_categories.php';
$categorie = get_categorie_by_id($categorie_id);

if (!$categorie) {
    header('Location: ../produits/index.php');
    exit;
}

// Traiter la suppression
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    require_once __DIR__ . '/../../controllers/controller_categories.php';
    $result = process_delete_categorie($categorie_id);

    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: ../produits/index.php');
        exit;
    }
    $error_message = (string) $result['message'];
}

// De quoi éclairer la décision
$a_des_pieces = categorie_has_produits($categorie_id);
$sous_cats = sous_categories_table_ok() ? get_sous_categories_by_categorie_id($categorie_id) : [];
$nb_sous = count($sous_cats);
$image_actuelle = trim((string) ($categorie['image'] ?? ''));
$nom = fpl_texte((string) $categorie['nom']);
$description = fpl_texte((string) ($categorie['description'] ?? ''));

$retour = '../produits/index.php';
$fpl_titre_page = 'Supprimer « ' . $nom . ' »';
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

  <div class="form-card" style="max-width:640px">
    <div class="form-block">
      <h3>
        <?php echo fpl_icone('alert-triangle', 14); ?>
        Supprimer cette catégorie
      </h3>

      <?php if ($error_message !== '') : ?>
        <div class="alert error" style="margin-bottom:var(--s3)"><?php echo e($error_message); ?></div>
      <?php endif; ?>

      <?php if ($a_des_pieces) : ?>
        <div class="alert warn" style="margin-bottom:var(--s3)">
          Cette catégorie contient des pièces : elle ne peut pas être supprimée.
          Déplacez ou retirez d'abord ses pièces.
        </div>
      <?php else : ?>
        <div class="alert warn" style="margin-bottom:var(--s3)">
          Action irréversible : la catégorie sera définitivement supprimée.
          <?php if ($nb_sous > 0) : ?>
            Ses <strong><?php echo (int) $nb_sous; ?> sous-catégorie<?php echo $nb_sous > 1 ? 's' : ''; ?></strong>
            seront supprimées avec elle.
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="recap-piece" style="display:flex;gap:var(--s3);align-items:center">
        <?php if ($image_actuelle !== '') : ?>
          <img src="../../upload/<?php echo e($image_actuelle); ?>" alt=""
               style="width:72px;height:72px;object-fit:cover;border-radius:var(--r2);border:1px solid var(--line)">
        <?php else : ?>
          <span class="recap-noimg" style="width:72px;height:72px;display:flex;align-items:center;justify-content:center;border-radius:var(--r2);background:var(--surface-2);color:var(--slate)"><?php echo fpl_icone('folder', 20); ?></span>
        <?php endif; ?>
        <div>
          <div style="font-weight:700;font-size:1.05rem"><?php echo e($nom); ?></div>
          <div class="muted"><?php echo $description !== '' ? e($description) : 'Aucune description'; ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="form-bar" style="max-width:640px">
    <?php if (!$a_des_pieces) : ?>
    <form method="POST" action="" style="display:inline"
          onsubmit="return confirm('Supprimer définitivement la catégorie « <?php echo e(addslashes($nom)); ?> » ? Cette action est irréversible.');">
      <input type="hidden" name="confirm_delete" value="1">
      <button type="submit" class="btn btn-danger">
        <?php echo fpl_icone('trash', 14); ?>
        Confirmer la suppression
      </button>
    </form>
    <?php endif; ?>
    <a href="<?php echo e($retour); ?>" class="btn btn-outline">Annuler</a>
  </div>

    </div><!-- .page-produits-admin -->

    <?php include '../includes/footer.php'; ?>
