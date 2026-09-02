<?php
/**
 * EXPORTER LES PIÈCES — la page d'aperçu avant téléchargement.
 * Programmation procédurale uniquement
 *
 * PORTAGE DE fpl_natif/admin/export-pieces-apercu.php, comportement compris :
 * on filtre, on VOIT exactement ce que le fichier contiendra, puis on choisit
 * son format. Les colonnes cochées sont celles qui sortent — pas d'autres.
 *
 * LA PAGE D'ORIGINE N'EST PAS PERDUE : le « Suivi du catalogue » de ce dépôt,
 * avec son PDF asynchrone, sa barre de progression, son choix de colonnes et
 * l'édition des prix, vit entier dans export-catalogue-fouta-origine.php.
 * Un lien y mène depuis cette page — on y reprendra ses apports un par un.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../includes/export_catalogue_fichier.php';

/** Une date de filtre au format AAAA-MM-JJ — sinon vide, sans erreur. */
function export_date_filtre($valeur)
{
    $v = trim((string) $valeur);
    if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return '';
    }

    return checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : '';
}

/* Le choix de catégorie tient dans UN seul menu, comme chez FPL natif : les
 * catégories et leurs rayons y sont mêlés, les rayons décalés d'un cran. La
 * valeur porte son origine (« c:12 » ou « s:28 »), puisqu'ici les deux tables
 * ont des identifiants séparés. */
$cat_brut = isset($_GET['cat']) ? trim((string) $_GET['cat']) : '';
$cat_id = 0;
$cat_est_sous = false;
if ($cat_brut !== '' && preg_match('/^([cs]):(\d+)$/', $cat_brut, $m)) {
    $cat_est_sous = ($m[1] === 's');
    $cat_id = (int) $m[2];
}

$filtres = [
    'du' => export_date_filtre($_GET['du'] ?? ''),
    'au' => export_date_filtre($_GET['au'] ?? ''),
    'cat' => $cat_id,
    'cat_est_sous' => $cat_est_sous,
    'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
    'ref' => isset($_GET['ref']) ? trim((string) $_GET['ref']) : '',
    'marque' => isset($_GET['marque']) ? (int) $_GET['marque'] : 0,
    'modele' => isset($_GET['modele']) ? (int) $_GET['modele'] : 0,
    'annee' => isset($_GET['annee']) ? (int) $_GET['annee'] : 0,
];

$pieces = produits_export_fpl($filtres, isset($_GET['page']) ? (int) $_GET['page'] : 1, 50);

$categories = get_all_categories();
$sous_categories = (function_exists('sous_categories_table_ok') && sous_categories_table_ok())
    ? get_all_sous_categories_with_categorie_nom()
    : [];

$marques = [];
if (produits_has_column('marque_id')) {
    require_once __DIR__ . '/../../models/model_marques.php';
    if (marques_table_ok()) {
        $marques = get_all_marques_ordered_by_nom();
    }
}

$modeles = [];
try {
    foreach ($db->query('SELECT id, nom FROM vehicule_modeles ORDER BY nom') as $vm) {
        $modeles[] = $vm;
    }
} catch (PDOException $e) {
    $modeles = [];
}

// LES COLONNES : celles demandées. Elles voyagent dans l'URL de téléchargement
// — « ce que je coche est ce que je sors ».
$colonnes_dispo = export_colonnes_fpl_toutes();
$colonnes_choisies = export_colonnes_fpl_retenues(
    isset($_GET['colonnes']) && is_array($_GET['colonnes']) ? $_GET['colonnes'] : null
);

$params = array_filter([
    'du' => $filtres['du'] !== '' ? $filtres['du'] : null,
    'au' => $filtres['au'] !== '' ? $filtres['au'] : null,
    'cat' => $cat_brut !== '' ? $cat_brut : null,
    'q' => $filtres['q'] !== '' ? $filtres['q'] : null,
    'ref' => $filtres['ref'] !== '' ? $filtres['ref'] : null,
    'marque' => $filtres['marque'] > 0 ? $filtres['marque'] : null,
    'modele' => $filtres['modele'] > 0 ? $filtres['modele'] : null,
    'annee' => $filtres['annee'] > 0 ? $filtres['annee'] : null,
]);
$a_des_filtres = ($params !== []);

$params_export = http_build_query($params + ['source' => 'fpl']);
foreach ($colonnes_choisies as $c) {
    $params_export .= '&colonnes[]=' . urlencode($c);
}
$url_fichier = 'export-catalogue-fichier.php?' . $params_export;

$formats = export_catalogue_fichier_formats_disponibles();

$fpl_titre_page = 'Exporter les pièces';
$fpl_retour_page = 'index.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exporter les pièces — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

    <div class="page-lead">
      <div>
        <div class="muted">Filtrez ce que vous voulez sortir — l'aperçu montre exactement ce que le fichier contiendra.</div>
      </div>
      <?php // La page de suivi du catalogue de ce dépôt n'est pas perdue :
            // son PDF asynchrone et l'édition des prix restent à un clic. ?>
      <a href="export-catalogue-fouta-origine.php" class="btn btn-outline" style="margin-left:auto">
        <?php echo fpl_icone('list', 14); ?> Suivi du catalogue
      </a>
    </div>

    <div class="card" style="margin-bottom:var(--s4)">
      <form method="GET" action="export-catalogue.php">
        <div class="export-grid">
          <div class="fc-champ">
            <label for="du">Ajoutées du</label>
            <input type="date" id="du" name="du" value="<?php echo e($filtres['du']); ?>">
          </div>
          <div class="fc-champ">
            <label for="au">au</label>
            <input type="date" id="au" name="au" value="<?php echo e($filtres['au']); ?>">
          </div>
          <div class="fc-champ">
            <label for="cat">Catégorie / sous-catégorie</label>
            <select id="cat" name="cat">
              <option value="">— Toutes —</option>
              <?php foreach ($categories as $c) : ?>
                <?php $vc = 'c:' . (int) $c['id']; ?>
                <option value="<?php echo $vc; ?>" <?php echo $cat_brut === $vc ? 'selected' : ''; ?>>
                  <?php echo fpl_e($c['nom']); ?> (toute la catégorie)
                </option>
                <?php foreach ($sous_categories as $sc) : ?>
                  <?php if ((int) $sc['categorie_id'] !== (int) $c['id']) { continue; } ?>
                  <?php $vs = 's:' . (int) $sc['id']; ?>
                  <option value="<?php echo $vs; ?>" <?php echo $cat_brut === $vs ? 'selected' : ''; ?>>
                    &nbsp;&nbsp;› <?php echo fpl_e($sc['nom']); ?>
                  </option>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fc-champ">
            <label for="q">Nom de la pièce</label>
            <input type="text" id="q" name="q" value="<?php echo e($filtres['q']); ?>" placeholder="Ex. coque rétroviseur">
          </div>
          <div class="fc-champ">
            <label for="ref">Référence (FPL, OEM ou fournisseur)</label>
            <input type="text" id="ref" name="ref" value="<?php echo e($filtres['ref']); ?>" placeholder="Ex. 9408107516">
          </div>
          <?php /* LA LIGNE VÉHICULE — ses trois champs sur UNE rangée à eux,
                   en colonnes égales (retour du 24/08 : ils retombaient dans
                   les colonnes étroites des dates et se collaient). */ ?>
          <div class="export-vehicule">
          <?php if ($marques !== []) : ?>
          <div class="fc-champ">
            <label for="marque">Marque du véhicule</label>
            <select id="marque" name="marque">
              <option value="">— Toutes —</option>
              <?php foreach ($marques as $m_ligne) : ?>
                <option value="<?php echo (int) $m_ligne['id']; ?>" <?php echo $filtres['marque'] === (int) $m_ligne['id'] ? 'selected' : ''; ?>>
                  <?php echo fpl_e($m_ligne['nom']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <?php if ($modeles !== []) : ?>
          <div class="fc-champ">
            <label for="modele">Modèle du véhicule</label>
            <select id="modele" name="modele">
              <option value="">— Tous —</option>
              <?php foreach ($modeles as $md) : ?>
                <option value="<?php echo (int) $md['id']; ?>" <?php echo $filtres['modele'] === (int) $md['id'] ? 'selected' : ''; ?>>
                  <?php echo fpl_e($md['nom']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fc-champ">
            <label for="annee">Année du véhicule</label>
            <input type="number" id="annee" name="annee" value="<?php echo $filtres['annee'] > 0 ? (int) $filtres['annee'] : ''; ?>"
                   min="1950" max="<?php echo date('Y') + 1; ?>" step="1" placeholder="2015" inputmode="numeric">
          </div>
          <?php endif; ?>
          </div><!-- /export-vehicule -->
        </div>

        <!-- CE QUE LE FICHIER EMPORTE — les colonnes cochées, et rien d'autre. -->
        <div class="fc-champ" style="margin-top:var(--s3)">
          <label>Colonnes du fichier</label>
          <div class="export-colonnes">
            <?php foreach ($colonnes_dispo as $cle => $def) : ?>
              <label class="export-colonne">
                <input type="checkbox" name="colonnes[]" value="<?php echo e($cle); ?>"
                       <?php echo in_array($cle, $colonnes_choisies, true) ? 'checked' : ''; ?>>
                <span><?php echo e($def[0]); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="help">Aucune cochée : les colonnes habituelles sortent.</div>
        </div>

        <div class="page-actions" style="margin-top:var(--s3)">
          <button type="submit" class="btn btn-primary"><?php echo fpl_icone('search', 14); ?> Afficher l'aperçu</button>
          <?php if ($a_des_filtres) : ?>
            <a href="export-catalogue.php" class="btn btn-outline">Tout effacer</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card">
      <div style="display:flex; align-items:center; gap:var(--s3); flex-wrap:wrap; margin-bottom:var(--s3)">
        <h2 style="margin:0">
          <?php echo (int) $pieces['total']; ?> pièce<?php echo $pieces['total'] > 1 ? 's' : ''; ?> à exporter
        </h2>
        <span style="flex:1"></span>
        <div class="export-dl">
          <span class="muted" style="font-size:12px">Télécharger :</span>
          <a href="<?php echo e($url_fichier . '&format=csv'); ?>" class="btn btn-outline btn-sm"><?php echo fpl_icone('download', 12); ?> CSV</a>
          <?php if (!empty($formats['xlsx'])) : ?>
            <a href="<?php echo e($url_fichier . '&format=xlsx'); ?>" class="btn btn-outline btn-sm"><?php echo fpl_icone('download', 12); ?> Excel</a>
          <?php endif; ?>
          <a href="<?php echo e('export-catalogue-fouta-origine.php'); ?>" class="btn btn-outline btn-sm"
             title="Le PDF passe par le suivi du catalogue, qui sait le fabriquer en tâche de fond"><?php echo fpl_icone('download', 12); ?> PDF</a>
          <?php if (!empty($formats['docx'])) : ?>
            <a href="<?php echo e($url_fichier . '&format=docx'); ?>" class="btn btn-outline btn-sm"><?php echo fpl_icone('download', 12); ?> Word</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($formats['xlsx']) || empty($formats['docx'])) : ?>
        <div class="help" style="margin-bottom:var(--s3)">
          Excel et Word demandent deux bibliothèques qui ne sont pas encore installées sur ce serveur.
          Le CSV s'ouvre dans Excel sans rien installer.
        </div>
      <?php endif; ?>

      <?php if ($pieces['lignes'] === []) : ?>
        <div class="empty" style="padding:var(--s5)">
          Aucune pièce ne correspond à ces critères. Élargissez la période ou effacez un filtre.
        </div>
      <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <?php foreach ($colonnes_choisies as $cle) : ?>
                  <th><?php echo e($colonnes_dispo[$cle][0]); ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pieces['lignes'] as $p) : ?>
                <tr>
                  <?php foreach ($colonnes_choisies as $cle) : ?>
                    <?php $v = export_valeur_colonne_fpl($cle, $p); ?>
                    <?php if ($cle === 'image') : ?>
                      <?php $rel_img = ltrim(str_replace('\\', '/', trim((string) ($p['image_principale'] ?? ''))), '/'); ?>
                      <td><?php if ($rel_img !== '') : ?><img src="../../upload/<?php echo e($rel_img); ?>" alt="" loading="lazy" style="width:34px;height:34px;object-fit:cover;border-radius:6px;display:block"><?php else : ?><span class="muted">—</span><?php endif; ?></td>
                    <?php elseif ($cle === 'reference') : ?>
                      <td><span class="chip-code"><?php echo e($v); ?></span></td>
                    <?php elseif ($cle === 'nom') : ?>
                      <td><a href="ajuster-stock.php?id=<?php echo (int) $p['id']; ?>" style="color:var(--ink)"><?php echo fpl_e($v); ?></a></td>
                    <?php elseif ($cle === 'stock') : ?>
                      <td class="num"><span class="qty <?php echo (float) $v <= 0 ? 'zero' : ''; ?>"><?php echo e($v); ?></span></td>
                    <?php elseif (in_array($cle, ['reference_oem', 'reference_fournisseur'], true)) : ?>
                      <td class="mono" style="font-size:12px"><?php echo $v !== '' ? fpl_e($v) : '—'; ?></td>
                    <?php else : ?>
                      <td class="muted"><?php echo $v !== '' ? fpl_e($v) : '—'; ?></td>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php echo fpl_pager($pieces); ?>

        <?php if ($pieces['derniere'] > 1) : ?>
          <div class="help" style="margin-top:var(--s2)">
            L'aperçu est paginé, mais le fichier téléchargé contient bien LES <?php echo (int) $pieces['total']; ?> pièces filtrées.
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    </div><!-- .page-produits-admin -->

    <?php include '../includes/footer.php'; ?>

<style>
  /* La grille de filtres et les cases de colonnes, reprises de FPL natif. */
  .export-grid {
    display: grid; gap: var(--s3);
    grid-template-columns: 150px 150px 1fr 1fr 1fr;
    align-items: end;
  }
  /* La rangée véhicule : trois colonnes égales, mêmes écarts que la grille. */
  .export-vehicule {
    grid-column: 1 / -1;
    display: grid; gap: var(--s3);
    grid-template-columns: repeat(3, minmax(180px, 1fr));
    align-items: end;
  }
  @media (max-width: 900px) {
    .export-vehicule { grid-template-columns: 1fr; }
  }
  .export-dl { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
  @media (max-width: 900px) {
    .export-grid { grid-template-columns: 1fr 1fr; }
  }
  .export-colonnes { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 6px; }
  .export-colonne {
    display: flex; align-items: center; gap: 7px; cursor: pointer;
    padding: 6px 10px; border: 1.5px solid var(--line); border-radius: var(--r-sm);
    background: #fff; font-size: 13px;
  }
  .export-colonne:hover { border-color: var(--blue-600); }
  .export-colonne:has(input:checked) { border-color: var(--navy); background: var(--blue-tint); font-weight: 600; }
</style>
