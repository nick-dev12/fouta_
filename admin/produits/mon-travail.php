<?php
/**
 * MON TRAVAIL — l'écran d'accueil du rayonniste : chercher une pièce
 * (« où est-elle ? »), les trois gestes du métier, les compteurs du jour,
 * et ses dernières opérations.
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/includes/tb_terrain.php (25/08/2026), au
 * moteur de ce dépôt : compteurs et journal depuis stock_mouvements,
 * recherche par ajax_recherche_piece.php (stock + emplacement dans la
 * réponse), les pièces « sans emplacement » comptées sur les colonnes
 * de rangement de `produits`.
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
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_mouvements_stock.php';

/* ── Les compteurs et le journal DU JOUR, les miens ─────────────────── */
$mes_mouvements = [];
try {
    $st = $db->prepare("SELECT m.*, p.nom AS produit_nom, p.identifiant_interne AS produit_code
                        FROM stock_mouvements m
                        LEFT JOIN produits p ON p.id = m.produit_id
                        WHERE m.sync_deleted_at IS NULL AND m.admin_id = :a
                          AND DATE(m.date_mouvement) = CURDATE()
                        ORDER BY m.date_mouvement DESC, m.id DESC
                        LIMIT 500");
    $st->execute(['a' => (int) $_SESSION['admin_id']]);
    $mes_mouvements = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mes_mouvements = [];
}
$operations = count($mes_mouvements);
$transferts = count(array_filter($mes_mouvements, function ($m) {
    return $m['type'] === 'transfert';
}));
$entrees = 0;
foreach ($mes_mouvements as $m) {
    if ($m['type'] === 'entree') {
        $entrees += (int) $m['quantite'];
    }
}
$mouvements_affiches = array_slice($mes_mouvements, 0, 8);

/* ── Les pièces que personne ne peut retrouver au rayon ─────────────── */
$sans_emplacement = 0;
try {
    $sans_emplacement = (int) $db->query(
        "SELECT COUNT(*) FROM produits
         WHERE sync_deleted_at IS NULL
           AND (entrepot_noeud_id IS NULL OR entrepot_noeud_id = 0)
           AND COALESCE(etage, '') = ''"
    )->fetchColumn();
} catch (PDOException $e) {
    $sans_emplacement = 0;
}

$flash_ok = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

$fpl_titre_page = 'Mon travail';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon travail — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

    <?php if ($flash_ok) : ?>
      <div class="alert alert-success" role="status"><?php echo e($flash_ok); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:var(--s4)">
      <div class="scan-bar">
        <span style="color:var(--blue-600); display:flex"><?php echo fpl_icone('search', 20); ?></span>
        <input type="text" id="mt-q" autocomplete="off" autofocus
               placeholder="Où est cette pièce ? Scannez ou cherchez : référence, nom, réf. OEM…">
      </div>
      <div id="mt-results" class="mt-results"></div>
    </div>

    <div class="action-grid" style="margin-bottom:var(--s4)">
      <a class="action-btn" href="entree.php">
        <span class="big"><?php echo fpl_icone('download', 26); ?></span>
        <strong>Entrée</strong>
        <span>Une livraison arrive</span>
      </a>
      <a class="action-btn" href="transfert.php">
        <span class="big"><?php echo fpl_icone('transfer', 26); ?></span>
        <strong>Déplacement</strong>
        <span>D'une barre à une autre</span>
      </a>
      <a class="action-btn" href="piece-ranger.php">
        <span class="big"><?php echo fpl_icone('plus', 26); ?></span>
        <strong>Nouvelle pièce</strong>
        <span>Ajouter au catalogue</span>
      </a>
    </div>

    <div class="kpi-line" style="margin-bottom:var(--s4)">
      <div class="card tile">
        <div class="label">Opérations aujourd'hui</div>
        <div class="value"><?php echo $operations; ?></div>
      </div>
      <div class="card tile">
        <div class="label">Déplacements</div>
        <div class="value" style="color:var(--blue-600)"><?php echo $transferts; ?></div>
      </div>
      <div class="card tile">
        <div class="label">Pièces entrées</div>
        <div class="value" style="color:var(--ok, #2CB67D)">+<?php echo $entrees; ?></div>
      </div>
    </div>

    <?php if ($sans_emplacement > 0) : ?>
      <div class="draft-banner" style="margin-bottom:var(--s4)">
        <span>
          <?php echo fpl_icone('alert-triangle', 14); ?>
          <strong><?php echo $sans_emplacement; ?> pièce(s) sans emplacement</strong> — elles ne sont rangées nulle part
          dans l'entrepôt, impossible de les retrouver au rayon.
        </span>
        <a href="index.php" class="btn btn-outline btn-sm">Voir le catalogue</a>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <h2>Mes dernières opérations</h2>
        <a href="rapport-jour.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline btn-sm">
          <?php echo fpl_icone('printer', 13); ?> Rapport de la journée
        </a>
      </div>

      <?php if ($mouvements_affiches === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('package', 34); ?></span>
          Rien enregistré aujourd'hui — commencez par une entrée ou un déplacement ci-dessus.
        </div>
      <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Heure</th><th>Pièce</th><th>Motif</th><th class="num">Qté</th><th class="num">Restant</th></tr>
            </thead>
            <tbody>
              <?php foreach ($mouvements_affiches as $m) : ?>
                <tr>
                  <td class="muted"><?php echo date('H:i', strtotime($m['date_mouvement'])); ?></td>
                  <td>
                    <div class="cell-title"><?php echo $m['produit_nom'] !== null ? fpl_e($m['produit_nom']) : 'Pièce supprimée'; ?></div>
                    <div class="cell-sub"><span class="chip-code"><?php echo $m['produit_code'] !== null ? e(fpl_code_afficher((string) $m['produit_code'])) : '—'; ?></span></div>
                  </td>
                  <td class="muted"><?php echo e(stock_mouvement_motif_libelle($m)); ?></td>
                  <td class="num" style="font-weight:700; color:<?php echo $m['type'] === 'sortie' ? 'var(--danger, #e23b3b)' : ($m['type'] === 'transfert' ? 'var(--blue-600)' : 'var(--ok, #2CB67D)'); ?>">
                    <?php echo stock_mouvement_signe($m['type']); ?><?php echo (int) $m['quantite']; ?>
                  </td>
                  <td class="num muted"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    </div><!-- .page-produits-admin -->

<style>
  .mt-results { display: flex; flex-direction: column; gap: 4px; margin-top: var(--s3); }
  .mt-row {
    display: flex; align-items: center; gap: var(--s3); padding: 10px 12px;
    border: 1px solid var(--line-soft); border-radius: var(--r-sm);
    background: #FBFCFE; text-decoration: none; color: var(--ink);
  }
  .mt-row:hover { border-color: var(--blue); background: var(--blue-tint); text-decoration: none; }
  .mt-row img, .mt-row .ph {
    width: 40px; height: 40px; border-radius: var(--r-sm); object-fit: cover;
    background: #EEF1F6; flex-shrink: 0; display: inline-block;
  }
  .mt-row strong { color: var(--navy); display: block; }
  .mt-where { margin-left: auto; text-align: right; flex-shrink: 0; }
  .mt-where .qty { font-weight: 700; font-variant-numeric: tabular-nums; font-size: 17px; }
</style>

<script>
  // « Où est cette pièce ? » — la question la plus fréquente du rayonniste
  (function () {
    const input = document.getElementById('mt-q');
    const results = document.getElementById('mt-results');
    let timer = null;

    function esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function render(list) {
      results.innerHTML = '';
      if (!list.length) {
        const d = document.createElement('div');
        d.className = 'muted';
        d.style.padding = '10px 2px';
        d.textContent = 'Aucune pièce ne correspond.';
        results.appendChild(d);
        return;
      }
      list.forEach(p => {
        const a = document.createElement('a');
        a.className = 'mt-row';
        a.href = 'ajuster-stock.php?id=' + p.id;
        a.innerHTML =
          (p.image ? `<img src="${esc(p.image)}" alt="">` : `<span class="ph"></span>`) +
          `<span>
             <strong>${esc(p.name)}</strong>
             <span class="muted">${esc(p.code)}${p.oem ? ' · OEM ' + esc(p.oem) : ''}</span>
           </span>
           <span class="mt-where">
             <span class="qty">${p.stock}</span>
             <span class="muted" style="display:block; font-size:13.5px">
               ${p.emplacement ? esc(p.emplacement) : 'Non rangée'}
             </span>
           </span>`;
        results.appendChild(a);
      });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = this.value.trim();
      if (q.length < 2) { results.innerHTML = ''; return; }
      timer = setTimeout(async () => {
        try {
          const r = await fetch('ajax_recherche_piece.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
          if (r.ok) render((await r.json()).products);
        } catch (e) { /* réseau indisponible */ }
      }, 200);
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const first = results.querySelector('.mt-row');
        if (first) first.click();
      }
    });
  })();
</script>

    <?php include '../includes/footer.php'; ?>
