<?php
/**
 * PIÈCES DÉFECTUEUSES — la déclaration INTERNE (pas le retour client) :
 * la pièce quitte le stock vendable, motif obligatoire à l'appui.
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/defectueux.php (24/08/2026) — squelette FPL
 * (déclarer → cumul → dernières déclarations), MOTEUR de ce dépôt, avec une
 * différence assumée : ici le stock est UN nombre sur la pièce (pas des
 * quantités par nœud d'entrepôt), il n'y a donc pas de « zone défectueux »
 * où les quantités dorment. Déclarer défectueux, c'est :
 *   - retirer la quantité du stock vendable (produits.stock) ;
 *   - journaliser une SORTIE `reference_type = 'defectueux'` avec le motif
 *     dans stock_mouvements (qui, quand, combien, pourquoi) ;
 *   - passer la pièce en rupture de stock si elle tombe à zéro, et
 *     prévenir les alertes de seuil comme tout autre mouvement.
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

// Déclarer, c'est écrire : le compte restreint regarde, il ne touche pas.
if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

/** Les motifs — la liste de FPL natif, telle quelle. */
function defectueux_motifs()
{
    return [
        'Cassée à la manutention',
        'Défaut constaté à l\'inspection',
        'Abîmée au stockage (humidité, chute…)',
        'Défaut d\'origine fournisseur',
        'Pièce incomplète',
        'Autre (préciser en note)',
    ];
}

/* ---------------------------------------------------------------------
 * LA DÉCLARATION (POST)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['def_valider'])) {
    $jeton = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $produit_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $produit = $produit_id > 0 ? get_produit_by_id($produit_id) : false;
    $qty = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
    $motif = isset($_POST['motif']) ? trim((string) $_POST['motif']) : '';
    $note = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';

    if ($jeton === '' || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
        $_SESSION['defectueux_erreur'] = 'Session expirée — recommencez.';
    } elseif (!$produit) {
        $_SESSION['defectueux_erreur'] = 'Pièce introuvable.';
    } elseif ($qty < 1) {
        $_SESSION['defectueux_erreur'] = 'La quantité doit être d\'au moins 1.';
    } elseif ($qty > (int) ($produit['stock'] ?? 0)) {
        $_SESSION['defectueux_erreur'] = 'Il n\'y a que ' . (int) ($produit['stock'] ?? 0) . ' en stock — impossible d\'en déclarer ' . $qty . '.';
    } elseif (!in_array($motif, defectueux_motifs(), true)) {
        $_SESSION['defectueux_erreur'] = 'Choisissez le motif.';
    } elseif (strpos($motif, 'Autre') === 0 && $note === '') {
        $_SESSION['defectueux_erreur'] = 'Le motif « Autre » demande une précision en note.';
    } else {
        try {
            $avant = (int) $produit['stock'];
            $apres = $avant - $qty;
            $sets = 'stock = :stock, date_modification = NOW()';
            $params = ['stock' => $apres, 'id' => $produit_id];
            // À zéro, la pièce passe d'elle-même en rupture (règle du dépôt).
            if ($apres <= 0 && ($produit['statut'] ?? '') === 'actif') {
                $sets .= ", statut = 'rupture_stock'";
            }
            if (produits_has_column('admin_dernier_modificateur_id')) {
                $sets .= ', admin_dernier_modificateur_id = :admin_modif';
                $params['admin_modif'] = (int) $_SESSION['admin_id'];
            }
            $st = $db->prepare("UPDATE produits SET $sets WHERE id = :id");
            $st->execute($params);

            create_stock_mouvement([
                'type' => 'sortie',
                'produit_id' => $produit_id,
                'quantite' => $qty,
                'quantite_avant' => $avant,
                'quantite_apres' => $apres,
                'reference_type' => 'defectueux',
                'notes' => $motif . ($note !== '' ? ' — ' . $note : ''),
                'admin_id' => (int) $_SESSION['admin_id'],
            ]);
            if (function_exists('stock_alertes_notifier_baisse_stock')) {
                stock_alertes_notifier_baisse_stock($produit_id, $avant, $apres);
            }
            $_SESSION['success_message'] = $qty . ' × « ' . fpl_texte($produit['nom'])
                . ' » déclarée(s) défectueuse(s) — ' . $motif . '. Stock restant : ' . $apres . '.';
        } catch (PDOException $e) {
            error_log('Déclaration défectueuse : ' . $e->getMessage());
            $_SESSION['defectueux_erreur'] = 'La déclaration a échoué — réessayez.';
        }
    }
    header('Location: defectueux.php');
    exit;
}

/* ---------------------------------------------------------------------
 * L'ÉCRAN : pièce choisie, cumul des déclarations, dernières déclarations.
 * ------------------------------------------------------------------- */
$produit = !empty($_GET['produit']) ? get_produit_by_id((int) $_GET['produit']) : false;

$cumul = [];
$declarations = [];
try {
    $cumul = $db->query("SELECT m.produit_id, SUM(m.quantite) AS total,
                                p.nom AS produit_nom, p.identifiant_interne AS produit_code,
                                (p.sync_deleted_at IS NOT NULL) AS produit_supprime
                         FROM stock_mouvements m
                         LEFT JOIN produits p ON p.id = m.produit_id
                         WHERE m.type = 'sortie' AND m.reference_type = 'defectueux'
                           AND m.sync_deleted_at IS NULL
                         GROUP BY m.produit_id, p.nom, p.identifiant_interne, p.sync_deleted_at
                         ORDER BY total DESC
                         LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    $declarations = $db->query("SELECT m.*, p.nom AS produit_nom, p.identifiant_interne AS produit_code,
                                       TRIM(CONCAT(COALESCE(a.prenom, ''), ' ', COALESCE(a.nom, ''))) AS admin_nom
                                FROM stock_mouvements m
                                LEFT JOIN produits p ON p.id = m.produit_id
                                LEFT JOIN admin a ON a.id = m.admin_id
                                WHERE m.type = 'sortie' AND m.reference_type = 'defectueux'
                                  AND m.sync_deleted_at IS NULL
                                ORDER BY m.date_mouvement DESC, m.id DESC
                                LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // base partielle : l'écran reste debout
}

$flash_ok = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$flash_err = $_SESSION['defectueux_erreur'] ?? null;
unset($_SESSION['defectueux_erreur']);

$fpl_titre_page = 'Pièces défectueuses';
$fpl_retour_page = 'index.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pièces défectueuses — Administration</title>
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
      <h2>Déclarer une pièce défectueuse</h2>
      <p class="muted" style="font-size:12.5px; margin-bottom:var(--s3)">
        Pour une pièce du stock qui se révèle défectueuse EN INTERNE (casse, défaut constaté…).
        Elle quitte le stock vendable, motif à l'appui.
      </p>

      <div class="scan-bar">
        <span style="color:var(--blue-600); display:flex"><?php echo fpl_icone('search', 20); ?></span>
        <input type="text" id="def-q" autocomplete="off" <?php echo $produit ? '' : 'autofocus'; ?>
               placeholder="Cherchez la pièce : référence, nom, réf. OEM…">
      </div>
      <div id="def-results" class="mv-results"></div>

      <?php if ($produit) : ?>
        <div class="mv-head" style="margin-top:var(--s4)">
          <?php if (!empty($produit['image_principale'])) : ?>
            <img class="thumb" style="width:56px; height:56px; object-fit:cover; border-radius:8px"
                 src="../../upload/<?php echo e(ltrim((string) $produit['image_principale'], '/')); ?>" alt="">
          <?php else : ?>
            <div class="thumb" style="width:56px; height:56px; display:flex; align-items:center; justify-content:center">
              <?php echo fpl_icone('tool', 20); ?>
            </div>
          <?php endif; ?>
          <div style="flex:1; min-width:180px">
            <div class="mv-name"><?php echo fpl_e($produit['nom']); ?></div>
            <div class="cell-sub">
              <span class="chip-code"><?php echo e(fpl_code_afficher((string) $produit['identifiant_interne'])); ?></span>
              <span style="margin-left:6px" class="muted">Stock : <?php echo (int) ($produit['stock'] ?? 0); ?></span>
            </div>
          </div>
          <a href="defectueux.php" class="btn btn-outline btn-sm btn-icon" title="Changer de pièce">
            <?php echo fpl_icone('x', 14); ?>
          </a>
        </div>

        <?php if ((int) ($produit['stock'] ?? 0) < 1) : ?>
          <div class="empty" style="padding:var(--s4)">Cette pièce n'a plus de stock vendable — rien à déclarer.</div>
        <?php else : ?>
          <form method="POST" action="defectueux.php">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
            <input type="hidden" name="def_valider" value="1">
            <input type="hidden" name="product_id" value="<?php echo (int) $produit['id']; ?>">

            <div class="def-grid">
              <div class="field" style="max-width:120px">
                <label for="qty">Quantité <span class="req">*</span></label>
                <input type="number" name="qty" id="qty" min="1" step="1" value="1"
                       max="<?php echo (int) $produit['stock']; ?>" required>
                <div class="help"><?php echo (int) $produit['stock']; ?> en stock</div>
              </div>

              <div class="field">
                <label for="motif">Pourquoi défectueuse ? <span class="req">*</span></label>
                <select name="motif" id="motif" required class="no-search">
                  <option value="">— Choisir le motif —</option>
                  <?php foreach (defectueux_motifs() as $m) : ?>
                    <option value="<?php echo e($m); ?>"><?php echo e($m); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="field" style="max-width:560px; margin-bottom:var(--s3)">
              <label for="notes">Précision <span class="muted" style="font-weight:400">(obligatoire si « Autre »)</span></label>
              <input type="text" name="notes" id="notes" value=""
                     placeholder="Détail utile : où, comment, référence du constat…" maxlength="500">
            </div>

            <button type="submit" class="btn btn-primary">
              <?php echo fpl_icone('alert-triangle', 14); ?> Déclarer défectueuse
            </button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-bottom:var(--s4)">
      <h2>Le cumul des déclarations</h2>
      <?php if ($cumul === []) : ?>
        <div class="empty" style="padding:var(--s4)">Rien de déclaré défectueux — tant mieux.</div>
      <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Pièce</th><th>Référence</th><th class="num">Quantité déclarée</th></tr>
            </thead>
            <tbody>
              <?php foreach ($cumul as $ligne) : ?>
                <tr>
                  <td>
                    <div class="cell-title">
                      <?php echo $ligne['produit_nom'] !== null ? fpl_e($ligne['produit_nom']) : 'Pièce supprimée'; ?>
                      <?php if (!empty($ligne['produit_supprime'])) : ?><span class="muted" style="font-size:10.5px">(supprimée)</span><?php endif; ?>
                    </div>
                  </td>
                  <td><span class="chip-code"><?php echo $ligne['produit_code'] !== null ? e(fpl_code_afficher((string) $ligne['produit_code'])) : '—'; ?></span></td>
                  <td class="num" style="font-weight:700"><?php echo (int) $ligne['total']; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Dernières déclarations</h2>
      <?php if ($declarations === []) : ?>
        <div class="empty" style="padding:var(--s4)">Aucune déclaration pour l'instant.</div>
      <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Date</th><th>Pièce</th><th class="num">Quantité</th><th>Motif</th><th>Par</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($declarations as $m) : ?>
                <tr>
                  <td class="muted" style="white-space:nowrap">
                    <?php echo date('d/m/Y', strtotime($m['date_mouvement'])); ?>
                    <div class="cell-sub"><?php echo date('H:i', strtotime($m['date_mouvement'])); ?></div>
                  </td>
                  <td>
                    <div class="cell-title"><?php echo $m['produit_nom'] !== null ? fpl_e($m['produit_nom']) : 'Pièce supprimée'; ?></div>
                    <div class="cell-sub"><span class="chip-code"><?php echo $m['produit_code'] !== null ? e(fpl_code_afficher((string) $m['produit_code'])) : '—'; ?></span></div>
                  </td>
                  <td class="num" style="font-weight:700; color:var(--danger, #e23b3b)"><?php echo (int) $m['quantite']; ?></td>
                  <td class="muted" style="max-width:340px"><?php echo $m['notes'] !== null && $m['notes'] !== '' ? fpl_e($m['notes']) : '—'; ?></td>
                  <td class="muted"><?php echo (isset($m['admin_nom']) && $m['admin_nom'] !== '') ? e($m['admin_nom']) : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
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
    font-family: inherit; font-size: 13px; color: var(--ink); text-decoration: none;
  }
  .mv-row:hover { border-color: var(--blue); background: var(--blue-tint); text-decoration: none; }
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
  .def-grid {
    display: grid; grid-template-columns: 140px 1fr;
    gap: var(--s3); align-items: start; margin-bottom: var(--s3);
  }
  @media (max-width: 700px) { .def-grid { grid-template-columns: 1fr; } }
</style>

<script>
  (function () {
    const input = document.getElementById('def-q');
    const results = document.getElementById('def-results');
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
        const row = document.createElement('a');
        row.className = 'mv-row';
        row.href = 'defectueux.php?produit=' + p.id;
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

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const first = results.querySelector('.mv-row');
        if (first) first.click();
      }
    });

    // « Autre » exige la précision — dit AVANT l'envoi.
    const motif = document.getElementById('motif');
    const notes = document.getElementById('notes');
    if (motif && notes) {
      motif.addEventListener('change', function () {
        notes.required = this.value.indexOf('Autre') === 0;
      });
    }
  })();
</script>

    <?php include '../includes/footer.php'; ?>
