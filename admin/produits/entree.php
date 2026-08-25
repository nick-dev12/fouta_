<?php
/**
 * ENTRÉE EN STOCK — une livraison arrive, le stock augmente : chercher la
 * pièce, saisir la quantité, valider. Le rappel du jour suit, avec la
 * CORRECTION par mouvement inverse (les deux lignes restent visibles).
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/entree.php + includes/mv_panel.php (25/08/2026).
 * Le MOTEUR est celui de ce dépôt : le stock est UN nombre sur la pièce
 * (produits.stock), chaque geste s'écrit dans stock_mouvements
 * (quantité avant/après, qui, quand). À stock redevenu positif, une pièce
 * en rupture repasse d'elle-même en actif — le miroir de la règle du dépôt.
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
require_once __DIR__ . '/../../includes/stock_alertes_notifications.php';

// Une entrée est une écriture : le compte restreint regarde, il ne touche pas.
if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

/* ---------------------------------------------------------------------
 * LE GESTE (POST) : l'entrée elle-même, ou la correction d'une ligne.
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jeton = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($jeton === '' || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
        $_SESSION['entree_erreur'] = 'Session expirée — recommencez.';
        header('Location: entree.php');
        exit;
    }

    if (isset($_POST['mv_corriger'])) {
        /* LA CORRECTION : un mouvement INVERSE, jamais un effacement — les
         * deux lignes restent au journal. Seulement ses propres mouvements
         * du jour, jamais une correction elle-même, jamais deux fois. */
        $mid = (int) $_POST['mv_corriger'];
        try {
            $st = $db->prepare("SELECT m.* FROM stock_mouvements m
                                WHERE m.id = :id AND m.sync_deleted_at IS NULL
                                  AND m.admin_id = :a AND DATE(m.date_mouvement) = CURDATE()
                                  AND m.type IN ('entree', 'sortie')");
            $st->execute(['id' => $mid, 'a' => (int) $_SESSION['admin_id']]);
            $m = $st->fetch(PDO::FETCH_ASSOC);
            $deja = $db->prepare("SELECT COUNT(*) FROM stock_mouvements
                                  WHERE reference_type = 'correction' AND reference_id = :id
                                    AND sync_deleted_at IS NULL");
            $deja->execute(['id' => $mid]);
            if (!$m || ($m['reference_type'] ?? '') === 'correction') {
                $_SESSION['entree_erreur'] = 'Ce mouvement ne peut pas se corriger ici.';
            } elseif ((int) $deja->fetchColumn() > 0) {
                $_SESSION['entree_erreur'] = 'Ce mouvement est déjà corrigé.';
            } else {
                $produit = get_produit_by_id((int) $m['produit_id']);
                if (!$produit) {
                    $_SESSION['entree_erreur'] = 'La pièce de ce mouvement n\'existe plus.';
                } else {
                    $avant = (int) $produit['stock'];
                    $delta = (int) $m['quantite'];
                    $apres = $m['type'] === 'entree' ? $avant - $delta : $avant + $delta;
                    if ($apres < 0) {
                        $_SESSION['entree_erreur'] = 'Impossible : le stock passerait sous zéro.';
                    } else {
                        $sets = 'stock = :stock, date_modification = NOW()';
                        $params = ['stock' => $apres, 'id' => (int) $m['produit_id']];
                        if ($apres <= 0 && ($produit['statut'] ?? '') === 'actif') {
                            $sets .= ", statut = 'rupture_stock'";
                        } elseif ($apres > 0 && ($produit['statut'] ?? '') === 'rupture_stock') {
                            $sets .= ", statut = 'actif'";
                        }
                        $db->prepare("UPDATE produits SET $sets WHERE id = :id")->execute($params);
                        create_stock_mouvement([
                            'type' => $m['type'] === 'entree' ? 'sortie' : 'entree',
                            'produit_id' => (int) $m['produit_id'],
                            'quantite' => $delta,
                            'quantite_avant' => $avant,
                            'quantite_apres' => $apres,
                            'reference_type' => 'correction',
                            'reference_id' => $mid,
                            'notes' => 'Correction du mouvement n° ' . $mid,
                            'admin_id' => (int) $_SESSION['admin_id'],
                        ]);
                        if ($apres < $avant && function_exists('stock_alertes_notifier_baisse_stock')) {
                            stock_alertes_notifier_baisse_stock((int) $m['produit_id'], $avant, $apres);
                        }
                        $_SESSION['success_message'] = 'Mouvement corrigé — le mouvement inverse est au journal.';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Correction de mouvement : ' . $e->getMessage());
            $_SESSION['entree_erreur'] = 'La correction a échoué — réessayez.';
        }
        header('Location: entree.php');
        exit;
    }

    // L'ENTRÉE elle-même
    $produit_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $produit = $produit_id > 0 ? get_produit_by_id($produit_id) : false;
    $qty = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
    if (!$produit) {
        $_SESSION['entree_erreur'] = 'Pièce introuvable.';
    } elseif ($qty < 1) {
        $_SESSION['entree_erreur'] = 'La quantité doit être d\'au moins 1.';
    } else {
        try {
            $avant = (int) $produit['stock'];
            $apres = $avant + $qty;
            $sets = 'stock = :stock, date_modification = NOW()';
            $params = ['stock' => $apres, 'id' => $produit_id];
            // Le stock revient : une pièce en rupture repasse en actif.
            if (($produit['statut'] ?? '') === 'rupture_stock' && $apres > 0) {
                $sets .= ", statut = 'actif'";
            }
            if (produits_has_column('admin_dernier_modificateur_id')) {
                $sets .= ', admin_dernier_modificateur_id = :admin_modif';
                $params['admin_modif'] = (int) $_SESSION['admin_id'];
            }
            $db->prepare("UPDATE produits SET $sets WHERE id = :id")->execute($params);
            create_stock_mouvement([
                'type' => 'entree',
                'produit_id' => $produit_id,
                'quantite' => $qty,
                'quantite_avant' => $avant,
                'quantite_apres' => $apres,
                'reference_type' => 'entree_manuelle',
                'notes' => null,
                'admin_id' => (int) $_SESSION['admin_id'],
            ]);
            $_SESSION['success_message'] = '+' . $qty . ' × « ' . fpl_texte($produit['nom'])
                . ' » — en stock : ' . $apres . '.';
        } catch (PDOException $e) {
            error_log('Entrée en stock : ' . $e->getMessage());
            $_SESSION['entree_erreur'] = 'L\'entrée a échoué — réessayez.';
        }
    }
    header('Location: entree.php');
    exit;
}

/* ---------------------------------------------------------------------
 * CE QUE VOUS AVEZ ENREGISTRÉ AUJOURD'HUI (le vôtre, paginé)
 * ------------------------------------------------------------------- */
$par = fpl_par_page('mouvements_jour', 10);
$page = max(1, (int) ($_GET['page'] ?? 1));
$jour = ['lignes' => [], 'total' => 0, 'page' => 1, 'par' => $par, 'derniere' => 1, 'corriges' => []];
try {
    $st = $db->prepare("SELECT COUNT(*) FROM stock_mouvements m
                        WHERE m.sync_deleted_at IS NULL AND m.admin_id = :a
                          AND DATE(m.date_mouvement) = CURDATE() AND m.type IN ('entree', 'sortie')");
    $st->execute(['a' => (int) $_SESSION['admin_id']]);
    $total = (int) $st->fetchColumn();
    $derniere = max(1, (int) ceil($total / $par));
    $page = min($page, $derniere);
    $st = $db->prepare("SELECT m.*, p.nom AS produit_nom, p.identifiant_interne AS produit_code,
                               p.sync_deleted_at AS produit_supprime
                        FROM stock_mouvements m
                        LEFT JOIN produits p ON p.id = m.produit_id
                        WHERE m.sync_deleted_at IS NULL AND m.admin_id = :a
                          AND DATE(m.date_mouvement) = CURDATE() AND m.type IN ('entree', 'sortie')
                        ORDER BY m.date_mouvement DESC, m.id DESC
                        LIMIT " . (int) $par . ' OFFSET ' . (($page - 1) * $par));
    $st->execute(['a' => (int) $_SESSION['admin_id']]);
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
    $corriges = [];
    if ($lignes !== []) {
        $ids = implode(',', array_map(function ($l) { return (int) $l['id']; }, $lignes));
        $cq = $db->query("SELECT reference_id FROM stock_mouvements
                          WHERE reference_type = 'correction' AND reference_id IN ($ids)
                            AND sync_deleted_at IS NULL");
        $corriges = array_map('intval', $cq->fetchAll(PDO::FETCH_COLUMN));
    }
    $jour = ['lignes' => $lignes, 'total' => $total, 'page' => $page, 'par' => $par,
        'derniere' => $derniere, 'corriges' => $corriges];
} catch (PDOException $e) {
    // base partielle : l'écran reste debout
}

$flash_ok = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$flash_err = $_SESSION['entree_erreur'] ?? null;
unset($_SESSION['entree_erreur']);

$fpl_titre_page = 'Entrée en stock';
$fpl_retour_page = 'index.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrée en stock — Administration</title>
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
    <?php if ($flash_err) : ?>
      <div class="alert alert-error" role="alert"><?php echo e($flash_err); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:var(--s4)">
      <div class="scan-bar">
        <span style="color:var(--blue-600); display:flex"><?php echo fpl_icone('search', 20); ?></span>
        <input type="text" id="mv-q" autocomplete="off" autofocus
               placeholder="Scannez, ou cherchez par référence FPL, réf. OEM, nom…">
      </div>
      <div id="mv-results" class="mv-results"></div>
    </div>

    <div class="card" id="mv-panel" hidden style="margin-bottom:var(--s4)">
      <form method="POST" action="entree.php" id="mv-form">
        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
        <input type="hidden" name="product_id" id="mv-product-id">

        <div class="mv-head">
          <img id="mv-img" class="thumb" style="width:64px; height:64px; object-fit:cover; border-radius:8px" alt="">
          <div style="flex:1; min-width:180px">
            <div class="mv-name" id="mv-name"></div>
            <div class="cell-sub">
              <span class="chip-code" id="mv-code"></span>
              <span id="mv-oem" style="margin-left:6px"></span>
            </div>
            <div class="cell-sub" id="mv-loc"></div>
          </div>
          <div class="mv-stock">
            <div class="muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.06em">En stock</div>
            <div class="hero-num" id="mv-stock" style="font-size:26px; font-weight:800; color:var(--navy)"></div>
          </div>
          <button type="button" class="btn btn-outline btn-sm btn-icon" id="mv-close" title="Changer de pièce">
            <?php echo fpl_icone('x', 14); ?>
          </button>
        </div>

        <div class="mv-actions">
          <div class="field" style="width:140px; margin:0">
            <label for="mv-qty">Quantité <span class="req">*</span></label>
            <input type="number" name="qty" id="mv-qty" min="1" step="1" value="1" required>
          </div>

          <div class="mv-buttons">
            <button type="submit" class="btn btn-in">
              <?php echo fpl_icone('download', 15); ?> Entrée en stock
            </button>
          </div>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>Ce que vous avez enregistré aujourd'hui</h2>

      <?php if ($jour['lignes'] === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('package', 30); ?></span>
          Aucun mouvement aujourd'hui.
        </div>
      <?php else : ?>
        <?php echo fpl_tablebar_haut($jour, 'mouvements'); ?>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Heure</th><th>Pièce</th><th>Motif</th>
                <th class="num">Qté</th><th class="num">Restant</th><th style="width:96px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jour['lignes'] as $m) : ?>
                <?php $corrige = ($m['reference_type'] ?? '') === 'correction' || in_array((int) $m['id'], $jour['corriges'], true); ?>
                <tr<?php echo $corrige ? ' style="opacity:.6"' : ''; ?>>
                  <td class="muted"><?php echo date('H:i', strtotime($m['date_mouvement'])); ?></td>
                  <td>
                    <div class="cell-title">
                      <?php echo $m['produit_nom'] !== null ? fpl_e($m['produit_nom']) : 'Pièce supprimée'; ?>
                      <?php if ($m['produit_supprime'] !== null) : ?><span class="muted" style="font-size:10.5px">(supprimée)</span><?php endif; ?>
                    </div>
                    <div class="cell-sub"><span class="chip-code"><?php echo $m['produit_code'] !== null ? e(fpl_code_afficher((string) $m['produit_code'])) : '—'; ?></span></div>
                  </td>
                  <td class="muted"><?php echo e(stock_mouvement_motif_libelle($m)); ?></td>
                  <td class="num" style="font-weight:700; color:<?php echo $m['type'] === 'sortie' ? 'var(--danger, #e23b3b)' : 'var(--ok, #2CB67D)'; ?>">
                    <?php echo stock_mouvement_signe($m['type']); ?><?php echo (int) $m['quantite']; ?>
                  </td>
                  <td class="num muted"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '—'; ?></td>
                  <td>
                    <?php if ($corrige) : ?>
                      <span class="muted" style="font-size:11px">
                        <?php echo ($m['reference_type'] ?? '') === 'correction' ? 'Correction' : 'Corrigé'; ?>
                      </span>
                    <?php else : ?>
                      <form method="POST" action="entree.php" style="display:inline"
                            onsubmit="return confirm('Annuler ce mouvement ?\n\nUn mouvement inverse sera enregistré : les deux lignes resteront visibles.')">
                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
                        <input type="hidden" name="mv_corriger" value="<?php echo (int) $m['id']; ?>">
                        <button type="submit" class="btn btn-outline btn-sm" title="Corriger une erreur de saisie">Corriger</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php echo fpl_pager($jour); ?>

        <div class="mt" style="margin-top:var(--s3)">
          <a href="rapport-jour.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline btn-sm">
            <?php echo fpl_icone('printer', 13); ?> Rapport de la journée
          </a>
        </div>
      <?php endif; ?>
    </div>

    </div><!-- .page-produits-admin -->

<style>
  .mv-results { display: flex; flex-direction: column; gap: 4px; margin-top: var(--s3); }
  .mv-row {
    display: flex; align-items: center; gap: var(--s3); padding: 9px 12px;
    border: 1px solid var(--line-soft); border-radius: var(--r-sm);
    background: #FBFCFE; cursor: pointer; text-align: left; width: 100%;
    font-family: inherit; font-size: 13px; color: var(--ink);
  }
  .mv-row:hover { border-color: var(--blue); background: var(--blue-tint); }
  .mv-row img, .mv-row .ph {
    width: 38px; height: 38px; border-radius: var(--r-sm); object-fit: cover;
    background: #EEF1F6; flex-shrink: 0; display: inline-block;
  }
  .mv-row strong { color: var(--navy); display: block; }
  .mv-row-info { flex: 1; min-width: 0; }
  .mv-row .qty { font-weight: 700; font-variant-numeric: tabular-nums; flex-shrink: 0; }
  .mv-head {
    display: flex; align-items: center; gap: var(--s4); flex-wrap: wrap;
    padding-bottom: var(--s3); border-bottom: 1px solid var(--line-soft); margin-bottom: var(--s4);
  }
  .mv-name { font-size: 16px; font-weight: 700; color: var(--navy); }
  .mv-stock { text-align: right; }
  .mv-actions { display: flex; gap: var(--s4); align-items: flex-end; flex-wrap: wrap; }
  .mv-buttons { display: flex; gap: var(--s2); flex-wrap: wrap; }
  .btn-in { background: var(--ok, #2CB67D); color: #fff; border-color: var(--ok, #2CB67D); }
  .btn-in:hover { background: #0E5539; color: #fff; }
</style>

<script>
  (function () {
    const input = document.getElementById('mv-q');
    const results = document.getElementById('mv-results');
    const panel = document.getElementById('mv-panel');
    let timer = null;

    function esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function choose(p) {
      document.getElementById('mv-product-id').value = p.id;
      document.getElementById('mv-name').textContent = p.name;
      document.getElementById('mv-code').textContent = p.code;
      document.getElementById('mv-oem').textContent = p.oem ? 'OEM ' + p.oem : '';
      document.getElementById('mv-loc').textContent =
        (p.emplacement ? p.emplacement + ' · ' : '') + (p.categorie || '');
      document.getElementById('mv-stock').textContent = p.stock;

      const img = document.getElementById('mv-img');
      if (p.image) { img.src = p.image; img.hidden = false; } else { img.hidden = true; }

      panel.hidden = false;
      results.innerHTML = '';
      input.value = '';
      const qty = document.getElementById('mv-qty');
      qty.focus();
      qty.select();
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
        const row = document.createElement('div');
        row.className = 'mv-row';
        const details = [
          p.oem ? 'OEM ' + esc(p.oem) : '',
          p.categorie ? esc(p.categorie) : '',
          p.emplacement ? esc(p.emplacement) : '',
        ].filter(Boolean).join(' · ');

        row.innerHTML =
          (p.image ? `<img src="${esc(p.image)}" alt="">` : `<span class="ph"></span>`) +
          `<span class="mv-row-info">
             <strong>${esc(p.name)} <span class="chip-code">${esc(p.code)}</span></strong>
             <span class="muted">${details}</span>
           </span>
           <span class="qty">${p.stock}</span>`;

        const act = document.createElement('button');
        act.type = 'button';
        act.className = 'btn btn-primary btn-sm';
        act.textContent = 'Ajouter';
        act.addEventListener('click', () => choose(p));
        row.appendChild(act);

        row.addEventListener('click', (e) => { if (e.target === act) return; choose(p); });
        results.appendChild(row);
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

    // Le scan d'un code-barres se termine par Entrée : on prend le premier résultat
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const first = results.querySelector('.mv-row');
        if (first) first.click();
      }
    });

    document.getElementById('mv-close').addEventListener('click', () => {
      panel.hidden = true;
      input.focus();
    });
  })();
</script>

    <?php include '../includes/footer.php'; ?>
