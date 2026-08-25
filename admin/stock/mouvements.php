<?php
/**
 * HISTORIQUE DES MOUVEMENTS — le registre GLOBAL : tous les mouvements de
 * tout le monde, filtrés (recherche, catégorie, type), comptés et paginés
 * avec le choix du nombre de lignes — le même tableau que le catalogue.
 * Programmation procédurale uniquement
 *
 * Passage au squelette FPL le 24/08 (demande : « fais pareil que le
 * tableau du catalogue, et agrandis les écritures ») : barre de filtres
 * fc-ligne, fpl_tablebar_haut (compteur + « Lignes par page »), fpl_pager,
 * motifs lisibles partagés (stock_mouvement_motif_libelle), transferts
 * affichés départ → arrivée, et QUI a fait le geste.
 * La page d'avant, avec sa recherche en direct : mouvements-fouta-origine.php.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_mouvements_stock.php';

$recherche = trim((string) ($_GET['q'] ?? $_GET['recherche'] ?? ''));
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$type_filtre = isset($_GET['type']) && in_array($_GET['type'], ['entree', 'sortie', 'transfert', 'inventaire'], true)
    ? (string) $_GET['type'] : null;
$du = isset($_GET['du']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['du']) ? (string) $_GET['du'] : null;
$au = isset($_GET['au']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['au']) ? (string) $_GET['au'] : null;

$par = fpl_par_page('mouvements_historique', 25);
$page = max(1, (int) ($_GET['page'] ?? 1));

$total = count_stock_mouvements($categorie_id > 0 ? $categorie_id : null, $type_filtre, $recherche !== '' ? $recherche : null, null, $du, $au);
$derniere = max(1, (int) ceil($total / $par));
$page = min($page, $derniere);
$lignes = get_stock_mouvements_paginated(
    $categorie_id > 0 ? $categorie_id : null,
    $type_filtre,
    $recherche !== '' ? $recherche : null,
    ($page - 1) * $par,
    $par,
    null,
    $du,
    $au
);
$pagine = ['lignes' => $lignes, 'total' => $total, 'page' => $page, 'par' => $par, 'derniere' => $derniere];

// QUI a fait chaque geste — un seul aller-retour pour toutes les lignes.
$admins_noms = [];
$admin_ids = array_values(array_unique(array_filter(array_map(function ($m) {
    return (int) ($m['admin_id'] ?? 0);
}, $lignes))));
if ($admin_ids !== []) {
    try {
        $ph = implode(',', array_fill(0, count($admin_ids), '?'));
        $st = $db->prepare("SELECT id, TRIM(CONCAT(COALESCE(prenom, ''), ' ', COALESCE(nom, ''))) AS qui FROM admin WHERE id IN ($ph)");
        $st->execute($admin_ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $admins_noms[(int) $a['id']] = (string) $a['qui'];
        }
    } catch (PDOException $e) {
        $admins_noms = [];
    }
}

$categories = get_all_categories();

$types_libelles = [
    '' => 'Tous les types',
    'entree' => 'Entrées',
    'sortie' => 'Sorties',
    'transfert' => 'Transferts',
    'inventaire' => 'Inventaires',
];

$fpl_titre_page = 'Historique des mouvements';
$fpl_retour_page = '../produits/mon-travail.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des mouvements — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin page-mouvements-historique">

    <div class="card filtre-complet" style="margin-bottom:var(--s4)">
      <form method="GET" action="mouvements.php" class="fc-ligne fc-ligne-mouvements">
        <div class="fc-champ fc-recherche">
          <label for="fc-q">Recherche</label>
          <input type="text" id="fc-q" name="q" value="<?php echo e($recherche); ?>"
                 placeholder="Pièce, référence, note, motif…">
        </div>
        <div class="fc-champ">
          <label for="fc-cat">Catégorie</label>
          <select id="fc-cat" name="categorie_id" onchange="this.form.submit()">
            <option value="0">Toutes les catégories</option>
            <?php foreach ($categories as $c) : ?>
              <option value="<?php echo (int) $c['id']; ?>" <?php echo $categorie_id === (int) $c['id'] ? 'selected' : ''; ?>>
                <?php echo fpl_e($c['nom']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fc-champ">
          <label for="fc-type">Type</label>
          <select id="fc-type" name="type" onchange="this.form.submit()">
            <?php foreach ($types_libelles as $val => $lib) : ?>
              <option value="<?php echo e($val); ?>" <?php echo (string) $type_filtre === $val && !($val === '' && $type_filtre !== null) ? 'selected' : ''; ?>>
                <?php echo e($lib); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fc-champ">
          <label for="fc-du">Du</label>
          <input type="date" id="fc-du" name="du" value="<?php echo e((string) $du); ?>" onchange="this.form.submit()">
        </div>
        <div class="fc-champ">
          <label for="fc-au">au</label>
          <input type="date" id="fc-au" name="au" value="<?php echo e((string) $au); ?>" onchange="this.form.submit()">
        </div>
        <button type="submit" class="btn btn-primary"><?php echo fpl_icone('search', 14); ?> Filtrer</button>
      </form>

      <?php if ($recherche !== '' || $categorie_id > 0 || $type_filtre !== null || $du !== null || $au !== null) : ?>
        <div class="fc-actifs" style="margin-top:var(--s3)">
          <span class="muted">Filtres :</span>
          <?php if ($recherche !== '') : ?><span class="cat-tag">« <?php echo e($recherche); ?> »</span><?php endif; ?>
          <?php if ($categorie_id > 0) : ?>
            <?php foreach ($categories as $c) : if ((int) $c['id'] === $categorie_id) : ?>
              <span class="cat-tag"><?php echo fpl_e($c['nom']); ?></span>
            <?php endif; endforeach; ?>
          <?php endif; ?>
          <?php if ($type_filtre !== null) : ?><span class="cat-tag"><?php echo e($types_libelles[$type_filtre] ?? $type_filtre); ?></span><?php endif; ?>
          <?php if ($du !== null) : ?><span class="cat-tag">du <?php echo date('d/m/Y', strtotime($du)); ?></span><?php endif; ?>
          <?php if ($au !== null) : ?><span class="cat-tag">au <?php echo date('d/m/Y', strtotime($au)); ?></span><?php endif; ?>
          <a href="mouvements.php" class="fc-effacer">Tout effacer</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <?php if ($pagine['lignes'] === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('package', 32); ?></span>
          Aucun mouvement<?php echo $recherche !== '' ? ' ne correspond à « ' . e($recherche) . ' »' : ' enregistré'; ?>.
        </div>
      <?php else : ?>
        <?php echo fpl_tablebar_haut($pagine, 'mouvements'); ?>

        <div class="table-wrap">
          <table class="mv-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Pièce</th>
                <th>Motif</th>
                <th class="num">Qté</th>
                <th class="num">Avant</th>
                <th class="num">Après</th>
                <th>Emplacement</th>
                <th>Par</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pagine['lignes'] as $m) : ?>
                <tr>
                  <td class="muted" style="white-space:nowrap">
                    <?php echo date('d/m/Y', strtotime($m['date_mouvement'])); ?>
                    <span style="display:block; font-size:12.5px"><?php echo date('H:i', strtotime($m['date_mouvement'])); ?></span>
                  </td>
                  <td>
                    <?php if (!empty($m['produit_id'])) : ?>
                      <a class="cell-title" href="../produits/ajuster-stock.php?id=<?php echo (int) $m['produit_id']; ?>" style="color:var(--ink)">
                        <?php echo $m['produit_nom'] !== null ? fpl_e($m['produit_nom']) : 'Pièce supprimée'; ?>
                      </a>
                    <?php else : ?>
                      <span class="muted">—</span>
                    <?php endif; ?>
                    <?php if (!empty($m['notes'])) : ?>
                      <div class="cell-sub muted" style="font-size:12.5px"><?php echo fpl_e(mb_substr((string) $m['notes'], 0, 80)); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="muted"><?php echo e(stock_mouvement_motif_libelle($m)); ?></td>
                  <td class="num" style="font-weight:700; color:<?php echo $m['type'] === 'sortie' ? 'var(--danger, #B23A31)' : ($m['type'] === 'transfert' ? 'var(--blue-600)' : 'var(--ok, #2CB67D)'); ?>">
                    <?php echo stock_mouvement_signe($m['type']); ?><?php echo (int) $m['quantite']; ?>
                  </td>
                  <td class="num muted"><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '—'; ?></td>
                  <td class="num"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '—'; ?></td>
                  <td class="muted" style="font-size:13.5px">
                    <?php if ($m['type'] === 'transfert') : ?>
                      <?php echo fpl_e(((string) ($m['source_nom'] ?? '') !== '' ? $m['source_nom'] : '—') . ' → ' . ((string) ($m['destination_nom'] ?? '') !== '' ? $m['destination_nom'] : '—')); ?>
                    <?php elseif (!empty($m['destination_nom'])) : ?>
                      <?php echo fpl_e($m['destination_nom']); ?>
                    <?php elseif (!empty($m['source_nom'])) : ?>
                      <?php echo fpl_e($m['source_nom']); ?>
                    <?php else : ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td class="muted"><?php echo isset($admins_noms[(int) ($m['admin_id'] ?? 0)]) ? e($admins_noms[(int) $m['admin_id']]) : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php echo fpl_pager($pagine); ?>
      <?php endif; ?>
    </div>

    </div><!-- .page-produits-admin -->

<style>
  /* La barre : quatre blocs, pas six. */
  .fpl-catalogue .fc-ligne-mouvements { grid-template-columns: minmax(190px, 1.6fr) minmax(160px, 1fr) minmax(140px, 1fr) minmax(130px, 1fr) minmax(130px, 1fr) auto; }
  @media (max-width: 1240px) { .fpl-catalogue .fc-ligne-mouvements { grid-template-columns: repeat(3, minmax(150px, 1fr)); } }
  @media (max-width: 900px) { .fpl-catalogue .fc-ligne-mouvements { grid-template-columns: 1fr 1fr; } }

  /* LES ÉCRITURES, PLUS GRANDES (demande du 24/08) : le registre se lit
     debout, vite — la table passe à 15 px, les cellules respirent. */
  .page-mouvements-historique .mv-table { font-size: 15px; }
  .page-mouvements-historique .mv-table th { font-size: 12.5px; padding: 10px 12px; }
  .page-mouvements-historique .mv-table td { padding: 11px 12px; }
  .page-mouvements-historique .mv-table .cell-title { font-size: 15px; font-weight: 600; }
  .page-mouvements-historique .tablebar-count { font-size: 14.5px; }
</style>

<script>
  // La recherche filtre EN DIRECT : une pause de frappe suffit (600 ms) —
  // l'esprit de l'ancienne page, sans point AJAX à entretenir.
  (function () {
    const champ = document.getElementById('fc-q');
    if (!champ) return;
    const valeurInitiale = champ.value;
    let minuterie = null;
    champ.addEventListener('input', function () {
      clearTimeout(minuterie);
      const v = champ.value.trim();
      if (v === valeurInitiale.trim()) return;
      if (v.length === 1) return; // une seule lettre : trop tôt
      minuterie = setTimeout(function () { champ.form.submit(); }, 600);
    });
  })();
</script>

    <?php include '../includes/footer.php'; ?>
