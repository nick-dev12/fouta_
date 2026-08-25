<?php
/**
 * RAPPORT JOURNALIER DES MOUVEMENTS — le document A4 imprimable : les
 * quatre compteurs du jour, la synthèse par motif, le journal détaillé,
 * et les deux signatures. Navigation de jour en jour.
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/rapport-jour.php (25/08/2026). Le MOTEUR est
 * celui de ce dépôt : stock_mouvements (entrées, sorties, transferts,
 * inventaires), motifs lisibles par stock_mouvement_motif_libelle().
 * Chacun ne voit que SON rapport ; le Responsable (et les techniciens)
 * consultent celui de n'importe qui (?user=).
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
require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../models/model_mouvements_stock.php';
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';

$date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['date'])
    ? (string) $_GET['date'] : date('Y-m-d');

$cible_id = (int) $_SESSION['admin_id'];
$cible_nom = trim((string) ($_SESSION['admin_prenom'] ?? '') . ' ' . (string) ($_SESSION['admin_nom'] ?? ''));
$cible_role = (string) ($_SESSION['admin_role'] ?? '');
if (!empty($_GET['user']) && admin_can_gestion_stock_etendue()) {
    $autre = get_admin_by_id((int) $_GET['user']);
    if ($autre) {
        $cible_id = (int) $autre['id'];
        $cible_nom = trim((string) $autre['prenom'] . ' ' . (string) $autre['nom']);
        $cible_role = (string) $autre['role'];
    }
}

$mouvements = [];
try {
    $st = $db->prepare("SELECT m.*, p.nom AS produit_nom, p.identifiant_interne AS produit_code,
                               ns.nom AS source_nom, nd.nom AS destination_nom
                        FROM stock_mouvements m
                        LEFT JOIN produits p ON p.id = m.produit_id
                        LEFT JOIN entrepot_hierarchie_noeud ns ON ns.id = m.emplacement_source_id
                        LEFT JOIN entrepot_hierarchie_noeud nd ON nd.id = m.emplacement_destination_id
                        WHERE m.sync_deleted_at IS NULL AND m.admin_id = :a
                          AND DATE(m.date_mouvement) = :d
                        ORDER BY m.date_mouvement, m.id");
    $st->execute(['a' => $cible_id, 'd' => $date]);
    $mouvements = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mouvements = [];
}

$sorties = 0;
$entrees = 0;
$pieces = [];
$par_motif = [];
foreach ($mouvements as $m) {
    if ($m['type'] === 'sortie') {
        $sorties += (int) $m['quantite'];
    } elseif ($m['type'] === 'entree') {
        $entrees += (int) $m['quantite'];
    }
    if (!empty($m['produit_id'])) {
        $pieces[(int) $m['produit_id']] = true;
    }
    $motif = stock_mouvement_motif_libelle($m);
    if (!isset($par_motif[$motif])) {
        $par_motif[$motif] = ['nb' => 0, 'qte' => 0,
            'sens' => $m['type'] === 'sortie' ? 'out' : ($m['type'] === 'transfert' ? 'mv' : 'in')];
    }
    $par_motif[$motif]['nb']++;
    $par_motif[$motif]['qte'] += (int) $m['quantite'];
}
uasort($par_motif, function ($a, $b) {
    return $b['qte'] <=> $a['qte'];
});

$agent_connecte = trim((string) ($_SESSION['admin_prenom'] ?? '') . ' ' . (string) ($_SESSION['admin_nom'] ?? ''));
$role_lisible = ucfirst(str_replace('_', ' ', $cible_role));
$user_q = $cible_id !== (int) $_SESSION['admin_id'] ? '&user=' . $cible_id : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<?php include __DIR__ . '/../../includes/favicon.php'; ?>
<meta charset="UTF-8">
<title>Rapport journalier — <?php echo date('d/m/Y', strtotime($date)); ?> — Administration</title>
<?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php require_once __DIR__ . '/../../includes/fpl_assets.php'; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(fpl_asset_uri('css/fpl.css'), ENT_QUOTES, 'UTF-8'); ?><?php echo asset_version_query(); ?>">
<style>
  body { background: var(--ground, #EFF2F7); margin: 0; font-family: Inter, "Segoe UI", Arial, sans-serif; }
  .report { max-width: 880px; margin: 0 auto; padding: 24px 28px 48px; }

  .no-print {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-bottom: 20px;
  }
  .date-nav {
    display: flex; align-items: center; gap: 6px; margin-left: auto;
    background: var(--surface, #fff); border: 1px solid var(--line); border-radius: var(--r-sm);
    padding: 4px 6px;
  }
  .date-nav a {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: var(--r-sm); color: var(--navy);
  }
  .date-nav a:hover { background: var(--blue-tint); text-decoration: none; }
  .date-nav input {
    border: none; font-family: inherit; font-size: 13px; color: var(--ink);
    background: transparent; padding: 2px 4px;
  }

  .report-doc {
    background: var(--surface, #fff); border: 1px solid var(--line); border-radius: var(--r);
    box-shadow: var(--sh-1, 0 1px 3px rgba(16,49,111,.08)); padding: 26px 30px;
  }
  .report-header {
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    border-bottom: 3px solid var(--navy); padding-bottom: 16px; margin-bottom: 22px;
  }
  .report-brand { display: flex; align-items: center; gap: 12px; }
  .report-brand img { width: 52px; height: 52px; object-fit: contain; }
  .report-title h1 { color: var(--navy); font-size: 19px; letter-spacing: -.01em; margin: 0; }
  .report-title .muted { font-size: 12.5px; }

  .r-tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 22px; }
  @media (max-width: 700px) { .r-tiles { grid-template-columns: repeat(2, 1fr); } }
  .r-tile { border: 1px solid var(--line); border-radius: var(--r); padding: 12px 14px; }
  .r-tile .k {
    font-size: 10px; text-transform: uppercase; letter-spacing: .07em;
    color: var(--slate); font-weight: 650;
  }
  .r-tile .v {
    font-size: 22px; font-weight: 750; color: var(--navy);
    font-variant-numeric: tabular-nums; margin-top: 3px; letter-spacing: -.02em;
  }
  .r-tile .v.neg { color: var(--danger, #B23A31); }
  .r-tile .v.pos { color: var(--ok, #2CB67D); }

  .r-section {
    font-size: 11px; font-weight: 650; color: var(--slate-soft);
    text-transform: uppercase; letter-spacing: .08em;
    margin: 22px 0 8px; padding-bottom: 6px; border-bottom: 1px solid var(--line-soft);
  }

  table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
       color: var(--slate); padding: 7px 8px; border-bottom: 1.5px solid var(--line); }
  td { padding: 7px 8px; border-bottom: 1px solid var(--line-soft); vertical-align: top; }
  .num { text-align: right; font-variant-numeric: tabular-nums; }
  .muted { color: var(--slate, #5A6A85); }
  .cell-title { font-weight: 600; color: var(--ink); }
  .empty { text-align: center; color: var(--slate); }
  .empty .big { display: block; margin-bottom: 8px; color: var(--line); }

  .motif-bar { display: flex; align-items: center; gap: 10px; }
  .motif-bar .gauge { flex: 1; height: 6px; background: var(--line-soft); border-radius: 3px; overflow: hidden; display: inline-block; }
  .motif-bar .gauge i { display: block; height: 100%; border-radius: 3px; }

  .sig-row { display: flex; gap: 40px; margin-top: 34px; }
  .sig { flex: 1; font-size: 13px; color: var(--slate); }
  .sig .qui { font-weight: 700; color: var(--ink); font-size: 14.5px; }
  .sig .trait { border-top: 1.5px solid var(--ink); margin-top: 44px; padding-top: 5px; }
  .imprime-par {
    margin-top: 18px; padding-top: 10px; border-top: 1px solid var(--line-soft);
    text-align: center; font-size: 11.5px; color: var(--slate-soft); font-style: italic;
  }

  @media print {
    body { background: #fff; font-size: 12pt; }
    .no-print { display: none; }
    .report { padding: 0; max-width: none; }
    .report-doc { border: none; box-shadow: none; padding: 0; }
    .report-header { padding-bottom: 10pt; margin-bottom: 12pt; }
    .report-title h1 { font-size: 16pt; }
    .report-title .muted { font-size: 10.5pt; }
    .r-tiles { gap: 8pt; margin-bottom: 12pt; }
    .r-tile { padding: 8pt 10pt; }
    .r-tile .k { font-size: 8pt; }
    .r-tile .v { font-size: 17pt; }
    .r-section { font-size: 9.5pt; margin: 12pt 0 5pt; }
    table { font-size: 10.5pt; }
    th { font-size: 8.5pt; padding: 5pt 6pt; background: #F2F4F8 !important;
         -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    td { padding: 4.5pt 6pt; }
    tr { page-break-inside: avoid; }
    .sig-row { margin-top: 22pt; page-break-inside: avoid; }
    .motif-bar .gauge i { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
<div class="report">

  <div class="no-print">
    <a class="btn btn-outline btn-sm" href="entree.php" title="Revenir à l'entrée en stock">
      <?php echo fpl_icone('arrow-left', 14); ?> Retour
    </a>
    <button class="btn btn-primary" onclick="window.print()"><?php echo fpl_icone('printer', 14); ?> Imprimer / PDF</button>

    <div class="date-nav">
      <a href="rapport-jour.php?date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?><?php echo $user_q; ?>" title="Jour précédent">
        <?php echo fpl_icone('chevron-left', 15); ?>
      </a>
      <input type="date" value="<?php echo e($date); ?>" max="<?php echo date('Y-m-d'); ?>"
             onchange="location.href='rapport-jour.php?date=' + this.value + '<?php echo $user_q; ?>'">
      <?php if ($date < date('Y-m-d')) : ?>
        <a href="rapport-jour.php?date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?><?php echo $user_q; ?>" title="Jour suivant">
          <?php echo fpl_icone('chevron-right', 15); ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="report-doc">
    <div class="report-header">
      <div class="report-brand">
        <img src="<?php echo htmlspecialchars(fpl_asset_uri('img/logo-fpl.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="FPL">
        <div>
          <strong style="color:var(--navy)">FOUTA POIDS LOURDS</strong>
          <div class="muted" style="font-style:italic">The Solution</div>
        </div>
      </div>
      <div class="report-title" style="text-align:right">
        <h1>Rapport journalier des mouvements</h1>
        <div class="muted"><?php echo fpl_date_longue($date); ?> — <?php echo e($cible_nom); ?>
          (<?php echo e($role_lisible); ?>)</div>
      </div>
    </div>

    <div class="r-tiles">
      <div class="r-tile">
        <div class="k">Opérations</div>
        <div class="v"><?php echo count($mouvements); ?></div>
      </div>
      <div class="r-tile">
        <div class="k">Pièces différentes</div>
        <div class="v"><?php echo count($pieces); ?></div>
      </div>
      <div class="r-tile">
        <div class="k">Total sorti</div>
        <div class="v neg">−<?php echo (int) $sorties; ?></div>
      </div>
      <div class="r-tile">
        <div class="k">Total entré</div>
        <div class="v pos">+<?php echo (int) $entrees; ?></div>
      </div>
    </div>

    <?php if ($mouvements === []) : ?>
      <div class="empty" style="padding:48px 20px">
        <span class="big"><?php echo fpl_icone('package', 34); ?></span>
        Aucun mouvement enregistré ce jour.
      </div>
    <?php else : ?>

      <?php if ($par_motif !== []) : ?>
        <div class="r-section">Synthèse par motif</div>
        <?php $max_qte = max(1, max(array_map(function ($d) { return $d['qte']; }, $par_motif))); ?>
        <table>
          <thead>
            <tr><th style="width:40%">Motif</th><th style="width:34%"></th><th class="num">Opérations</th><th class="num">Quantité</th></tr>
          </thead>
          <tbody>
            <?php foreach ($par_motif as $motif => $d) : ?>
              <?php $couleur = $d['sens'] === 'out' ? 'var(--danger, #B23A31)'
                  : ($d['sens'] === 'mv' ? 'var(--blue-600, #2957ae)' : 'var(--ok, #2CB67D)'); ?>
              <tr>
                <td><?php echo e($motif); ?></td>
                <td>
                  <div class="motif-bar">
                    <span class="gauge">
                      <i style="width:<?php echo round($d['qte'] / $max_qte * 100); ?>%; background:<?php echo $couleur; ?>"></i>
                    </span>
                  </div>
                </td>
                <td class="num"><?php echo $d['nb']; ?></td>
                <td class="num" style="font-weight:700; color:<?php echo $couleur; ?>">
                  <?php echo $d['sens'] === 'out' ? '−' : ($d['sens'] === 'mv' ? '⇄' : '+'); ?><?php echo (int) $d['qte']; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="r-section">Journal détaillé — <?php echo count($mouvements); ?> opération(s)</div>
      <table>
        <thead>
          <tr><th>Heure</th><th>Pièce</th><th>Motif</th><th class="num">Qté</th><th class="num">Stock après</th><th>Emplacement</th></tr>
        </thead>
        <tbody>
          <?php foreach ($mouvements as $m) : ?>
            <tr>
              <td class="muted"><?php echo date('H:i', strtotime($m['date_mouvement'])); ?></td>
              <td>
                <span class="cell-title"><?php echo $m['produit_nom'] !== null ? fpl_e($m['produit_nom']) : 'Pièce supprimée'; ?></span>
                <span class="muted" style="margin-left:5px"><?php echo $m['produit_code'] !== null ? e(fpl_code_afficher((string) $m['produit_code'])) : ''; ?></span>
              </td>
              <td class="muted"><?php echo e(stock_mouvement_motif_libelle($m)); ?></td>
              <td class="num" style="font-weight:700; color:<?php echo $m['type'] === 'sortie' ? 'var(--danger, #B23A31)' : ($m['type'] === 'transfert' ? 'var(--blue-600, #2957ae)' : 'var(--ok, #2CB67D)'); ?>">
                <?php echo stock_mouvement_signe($m['type']); ?><?php echo (int) $m['quantite']; ?>
              </td>
              <td class="num muted"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '—'; ?></td>
              <td class="muted" style="font-size:12px">
                <?php if ($m['type'] === 'transfert') : ?>
                  <?php echo fpl_e(((string) ($m['source_nom'] ?? '') !== '' ? $m['source_nom'] : '—') . ' → ' . ((string) ($m['destination_nom'] ?? '') !== '' ? $m['destination_nom'] : '—')); ?>
                <?php elseif (!empty($m['emplacement_destination_id'])) : ?>
                  <?php echo fpl_e(entrepot_noeud_chemin_libelle((int) $m['emplacement_destination_id'])); ?>
                <?php elseif (!empty($m['emplacement_source_id'])) : ?>
                  <?php echo fpl_e(entrepot_noeud_chemin_libelle((int) $m['emplacement_source_id'])); ?>
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div class="sig-row">
      <div class="sig">
        Signature de l'agent
        <div class="qui"><?php echo e($cible_nom); ?></div>
        <div class="trait">&nbsp;</div>
      </div>
      <div class="sig">
        Visa du responsable
        <div class="qui">&nbsp;</div>
        <div class="trait">&nbsp;</div>
      </div>
    </div>

    <div class="imprime-par">
      Édité par <?php echo e($agent_connecte); ?> le <?php echo date('d/m/Y à H:i'); ?> — FOUTA POIDS LOURDS
    </div>
  </div>

</div>
</body>
</html>
