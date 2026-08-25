<?php
/**
 * Juste après l'enregistrement d'une pièce — ou depuis « Toutes les
 * étiquettes » : choisir la taille de l'étiquette, puis « Générer ».
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/etiquette-piece-choisir.php (24/08/2026).
 * « Générer » ouvre le bloc étiquette de la fiche à la taille choisie
 * (ajuster-stock.php?etiquette_format=N#fpl-etiquette-print-root) — c'est
 * le moteur d'étiquette de CE dépôt, rendu au format demandé.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_produit_etiquette_parametres.php';

$produit = get_produit_by_id(isset($_GET['id']) ? (int) $_GET['id'] : 0);
if ($produit === false) {
    $_SESSION['success_message'] = 'Cette pièce n\'existe pas.';
    header('Location: index.php');
    exit;
}

$formats = fpl_etiquette_formats_pieces();
if ($formats === []) {
    // Sans la table des formats, l'étiquette du réglage reste la voie.
    header('Location: ajuster-stock.php?id=' . (int) $produit['id'] . '#fpl-etiquette-print-root');
    exit;
}

// Le format coché d'office : celui qui a les mm du réglage courant, sinon le premier.
$reglage = fpl_etiquette_dims();
$defaut = 0;
foreach ($formats as $f) {
    if (abs((float) $f['largeur_mm'] - (float) $reglage['largeur_mm']) < 0.01
        && abs((float) $f['hauteur_mm'] - (float) $reglage['hauteur_mm']) < 0.01) {
        $defaut = (int) $f['id'];
        break;
    }
}
if ($defaut === 0 && isset($formats[0])) {
    $defaut = (int) $formats[0]['id'];
}

$cat_retour = !empty($produit['sous_categorie_id'])
    ? 'index.php?categorie_id=' . (int) $produit['categorie_id'] . '&sous_categorie_id=' . (int) $produit['sous_categorie_id']
    : (!empty($produit['categorie_id']) ? 'index.php?categorie_id=' . (int) $produit['categorie_id'] : 'index.php');

$fpl_titre_page = 'Étiquette de la pièce';
$fpl_retour_page = $cat_retour;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étiquette de la pièce — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

    <div class="card" style="max-width:760px; margin-left:auto; margin-right:auto">
      <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px">
        <?php if (!empty($produit['image_principale'])) : ?>
          <img src="../../upload/<?php echo e(ltrim((string) $produit['image_principale'], '/')); ?>" alt=""
               style="width:64px; height:64px; object-fit:cover; border-radius:8px">
        <?php else : ?>
          <div class="thumb" style="width:64px; height:64px; display:flex; align-items:center; justify-content:center"><?php echo fpl_icone('tool', 24); ?></div>
        <?php endif; ?>
        <div>
          <strong style="font-size:16px"><?php echo fpl_e($produit['nom']); ?></strong>
          <div><span class="chip-code"><?php echo e(fpl_code_afficher((string) $produit['identifiant_interne'])); ?></span></div>
        </div>
      </div>

      <form method="GET" action="ajuster-stock.php">
        <input type="hidden" name="id" value="<?php echo (int) $produit['id']; ?>">
        <h2>Choisissez la taille de l'étiquette</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:10px; margin-bottom:16px">
          <?php foreach ($formats as $f) : ?>
            <label class="fmt-choice">
              <input type="radio" name="etiquette_format" value="<?php echo (int) $f['id']; ?>" <?php echo $defaut === (int) $f['id'] ? 'checked' : ''; ?>>
              <strong><?php echo e($f['nom']); ?></strong>
              <span class="muted">
                <?php echo e(fpl_etiquette_dims_fmt_mm($f['largeur_mm'])); ?> × <?php echo e(fpl_etiquette_dims_fmt_mm($f['hauteur_mm'])); ?> mm
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="page-actions" style="display:flex; gap:10px; flex-wrap:wrap">
          <button type="submit" class="btn btn-primary"><?php echo fpl_icone('printer', 14); ?> Générer l'étiquette</button>
          <button type="submit" class="btn btn-outline" id="btn-pdf-choisir"
                  formaction="etiquette-piece-pdf.php" formtarget="_blank"
                  title="Le PDF sort à la taille exacte de l'étiquette">
            <?php echo fpl_icone('download', 14); ?> Télécharger en PDF
          </button>
          <a href="<?php echo e($cat_retour); ?>" class="btn btn-outline">Plus tard</a>
        </div>
        <script>
          // Le PDF attend « format », l'aperçu attend « etiquette_format » :
          // le bouton PDF renomme le champ le temps de son envoi.
          (function () {
            const btn = document.getElementById('btn-pdf-choisir');
            btn.addEventListener('click', function () {
              document.querySelectorAll('input[name="etiquette_format"]').forEach(r => { r.name = 'format'; });
              setTimeout(() => {
                document.querySelectorAll('input[name="format"]').forEach(r => { r.name = 'etiquette_format'; });
              }, 300);
            });
          })();
        </script>
      </form>
    </div>

    </div><!-- .page-produits-admin -->

<style>
  .fmt-choice {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    border: 2px solid var(--line); border-radius: var(--r); padding: 14px 8px;
    cursor: pointer; text-align: center; background: #fff;
  }
  .fmt-choice:hover { border-color: var(--blue); }
  .fmt-choice:has(input:checked) { border-color: var(--blue); background: var(--blue-tint); }
  .fmt-choice strong { color: var(--navy); font-size: 13.5px; }
</style>

<script>
  // « Générer » doit atterrir SUR l'étiquette : l'ancre part avec le formulaire.
  (function () {
    const form = document.querySelector('form[action^="ajuster-stock"]');
    form.addEventListener('submit', function () {
      form.action = 'ajuster-stock.php#fpl-etiquette-print-root';
    });
  })();
</script>

    <?php include '../includes/footer.php'; ?>
