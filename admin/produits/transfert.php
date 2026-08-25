<?php
/**
 * TRANSFERT D'EMPLACEMENT — déplacer une pièce d'un rangement à un autre,
 * avec sa trace au journal des mouvements.
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/transfert.php (24/08/2026) — le squelette est
 * le sien (recherche → carte de la pièce → d'où / vers où → note → valider,
 * puis « Vos derniers transferts »). Le MOTEUR est celui de ce dépôt, et il
 * diffère sur un point assumé : ICI UNE PIÈCE A UN SEUL EMPLACEMENT
 * (les colonnes de `produits`), pas des quantités par nœud — transférer,
 * c'est donc déplacer LA pièce entière :
 *   - « d'où elle part » se lit (l'emplacement actuel), ne se choisit pas ;
 *   - « où elle va » est la cascade d'entrepôt de ce dépôt (la même que le
 *     wizard d'ajout) ;
 *   - le journal `stock_mouvements` reçoit un mouvement `transfert` avec
 *     emplacement_source_id → emplacement_destination_id (deux colonnes qui
 *     existaient sans que rien ne les écrive), le stock ne bouge pas.
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
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';
require_once __DIR__ . '/../../includes/produit_emplacement_entrepot.php';

// Déplacer, c'est écrire : le compte restreint regarde, il ne touche pas.
if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

/* ---------------------------------------------------------------------
 * LE TRANSFERT (POST) : nouvelle place → colonnes de la pièce + journal.
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tr_valider'])) {
    $jeton = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $produit_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $produit = $produit_id > 0 ? get_produit_by_id($produit_id) : false;

    if ($jeton === '' || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
        $_SESSION['transfert_erreur'] = 'Session expirée — recommencez.';
    } elseif (!$produit) {
        $_SESSION['transfert_erreur'] = 'Pièce introuvable.';
    } else {
        $resolu = produit_emplacement_from_source($_POST);
        /* from_source renvoie AUSSI des clés d'écran (chemin_libelle,
         * ref_zone_id…) : seules les vraies colonnes de `produits` passent
         * dans l'UPDATE — sinon « Unknown column » et rien ne bouge. */
        $nouvel = [];
        foreach ($resolu as $col => $val) {
            if (produits_has_column($col)) {
                $nouvel[$col] = $val;
            }
        }
        $a_une_valeur = false;
        foreach ($nouvel as $v) {
            if ($v !== null && $v !== '') {
                $a_une_valeur = true;
                break;
            }
        }
        $ancien_noeud = isset($produit['entrepot_noeud_id']) ? (int) $produit['entrepot_noeud_id'] : 0;
        $nouveau_noeud = isset($nouvel['entrepot_noeud_id']) ? (int) $nouvel['entrepot_noeud_id'] : 0;

        if (!$a_une_valeur) {
            $_SESSION['transfert_erreur'] = 'Choisissez l\'emplacement d\'arrivée — descendez la cascade jusqu\'au bout.';
        } elseif ($nouveau_noeud > 0 && $nouveau_noeud === $ancien_noeud) {
            $_SESSION['transfert_erreur'] = 'La pièce est déjà rangée à cet emplacement.';
        } else {
            try {
                $sets = ['date_modification = NOW()'];
                $params = ['id' => $produit_id];
                foreach ($nouvel as $col => $val) {
                    $sets[] = "`$col` = :$col";
                    $params[$col] = ($val === '' ? null : $val);
                }
                if (produits_has_column('admin_dernier_modificateur_id')) {
                    $sets[] = 'admin_dernier_modificateur_id = :admin_modif';
                    $params['admin_modif'] = (int) $_SESSION['admin_id'];
                }
                $st = $db->prepare('UPDATE produits SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $st->execute($params);

                $note = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';
                create_stock_mouvement([
                    'type' => 'transfert',
                    'produit_id' => $produit_id,
                    'quantite' => (int) ($produit['stock'] ?? 0),
                    'quantite_avant' => (int) ($produit['stock'] ?? 0),
                    'quantite_apres' => (int) ($produit['stock'] ?? 0),
                    'reference_type' => 'transfert_emplacement',
                    'notes' => $note !== '' ? $note : null,
                    'admin_id' => (int) $_SESSION['admin_id'],
                    'emplacement_source_id' => $ancien_noeud > 0 ? $ancien_noeud : null,
                    'emplacement_destination_id' => $nouveau_noeud > 0 ? $nouveau_noeud : null,
                ]);

                $ou = $nouveau_noeud > 0 && function_exists('entrepot_noeud_chemin_libelle')
                    ? entrepot_noeud_chemin_libelle($nouveau_noeud)
                    : produit_emplacement_resume_court($nouvel);
                $_SESSION['success_message'] = 'Pièce « ' . fpl_texte($produit['nom']) . ' » déplacée'
                    . ($ou !== '' ? ' vers ' . $ou : '') . '.';
            } catch (PDOException $e) {
                error_log('Transfert d\'emplacement : ' . $e->getMessage());
                $_SESSION['transfert_erreur'] = 'Le déplacement a échoué — réessayez.';
            }
        }
    }
    header('Location: transfert.php' . ($produit_id > 0 ? '?produit=' . $produit_id : ''));
    exit;
}

/* ---------------------------------------------------------------------
 * L'ÉCRAN : la pièce choisie (?produit=), ses valeurs, les récents.
 * ------------------------------------------------------------------- */
$produit = !empty($_GET['produit']) ? get_produit_by_id((int) $_GET['produit']) : false;
$chemin_actuel = '';
if ($produit) {
    if (!empty($produit['entrepot_noeud_id']) && function_exists('entrepot_noeud_chemin_libelle')) {
        $chemin_actuel = (string) entrepot_noeud_chemin_libelle((int) $produit['entrepot_noeud_id']);
    }
    if ($chemin_actuel === '') {
        $chemin_actuel = produit_emplacement_resume_court(produit_emplacement_from_produit($produit));
    }
}
// La cascade d'arrivée part VIDE : on choisit la nouvelle place.
$emplacement_form_vals = produit_emplacement_form_values_for_form([]);

// --- Vos derniers transferts (les vôtres, comme chez FPL natif) ---
$par = fpl_par_page('transferts', 10);
$page = max(1, (int) ($_GET['page'] ?? 1));
$recents = ['lignes' => [], 'total' => 0, 'page' => 1, 'par' => $par, 'derniere' => 1];
try {
    $st = $db->prepare("SELECT COUNT(*) FROM stock_mouvements m
                        WHERE m.type = 'transfert' AND m.sync_deleted_at IS NULL AND m.admin_id = :a");
    $st->execute(['a' => (int) $_SESSION['admin_id']]);
    $total = (int) $st->fetchColumn();
    $derniere = max(1, (int) ceil($total / $par));
    $page = min($page, $derniere);
    $st = $db->prepare("SELECT m.*, p.nom AS produit_nom, p.identifiant_interne AS produit_code,
                               ns.nom AS source_nom, nd.nom AS destination_nom
                        FROM stock_mouvements m
                        LEFT JOIN produits p ON p.id = m.produit_id
                        LEFT JOIN entrepot_hierarchie_noeud ns ON ns.id = m.emplacement_source_id
                        LEFT JOIN entrepot_hierarchie_noeud nd ON nd.id = m.emplacement_destination_id
                        WHERE m.type = 'transfert' AND m.sync_deleted_at IS NULL AND m.admin_id = :a
                        ORDER BY m.date_mouvement DESC, m.id DESC
                        LIMIT " . (int) $par . ' OFFSET ' . (($page - 1) * $par));
    $st->execute(['a' => (int) $_SESSION['admin_id']]);
    $recents = ['lignes' => $st->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total, 'page' => $page, 'par' => $par, 'derniere' => $derniere];
} catch (PDOException $e) {
    // la table peut manquer sur une base partielle : l'écran reste debout
}

$flash_ok = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$flash_err = $_SESSION['transfert_erreur'] ?? null;
unset($_SESSION['transfert_erreur']);

$fpl_titre_page = 'Transfert d\'emplacement';
$fpl_retour_page = 'index.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert d'emplacement — Administration</title>
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
        <input type="text" id="tr-q" autocomplete="off" <?php echo $produit ? '' : 'autofocus'; ?>
               placeholder="Cherchez la pièce à déplacer : référence, nom, réf. OEM…">
      </div>
      <div id="tr-results" class="mv-results"></div>
    </div>

    <?php if ($produit) : ?>
      <div class="card" style="margin-bottom:var(--s4)">
        <div class="mv-head">
          <?php if (!empty($produit['image_principale'])) : ?>
            <img class="thumb" style="width:64px; height:64px; object-fit:cover; border-radius:8px"
                 src="../../upload/<?php echo e(ltrim((string) $produit['image_principale'], '/')); ?>" alt="">
          <?php else : ?>
            <div class="thumb" style="width:64px; height:64px; display:flex; align-items:center; justify-content:center">
              <?php echo fpl_icone('tool', 22); ?>
            </div>
          <?php endif; ?>
          <div style="flex:1; min-width:180px">
            <div class="mv-name"><?php echo fpl_e($produit['nom']); ?></div>
            <div class="cell-sub">
              <span class="chip-code"><?php echo e(fpl_code_afficher((string) $produit['identifiant_interne'])); ?></span>
              <?php if (!empty($produit['reference_oem'])) : ?><span style="margin-left:6px">OEM <?php echo fpl_e($produit['reference_oem']); ?></span><?php endif; ?>
              <span style="margin-left:6px" class="muted">Stock : <?php echo (int) ($produit['stock'] ?? 0); ?></span>
            </div>
          </div>
          <a href="transfert.php" class="btn btn-outline btn-sm btn-icon" title="Changer de pièce">
            <?php echo fpl_icone('x', 14); ?>
          </a>
        </div>

        <form method="POST" action="transfert.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
          <input type="hidden" name="tr_valider" value="1">
          <input type="hidden" name="product_id" value="<?php echo (int) $produit['id']; ?>">

          <div class="field" style="margin-bottom:var(--s3)">
            <label>D'où elle part</label>
            <p class="tr-depart">
              <?php echo fpl_icone('map-pin', 14); ?>
              <?php if ($chemin_actuel !== '') : ?>
                <strong><?php echo fpl_e($chemin_actuel); ?></strong>
              <?php else : ?>
                <span class="muted">Aucun emplacement enregistré — ce transfert lui en donne un.</span>
              <?php endif; ?>
            </p>
          </div>

          <div class="field" style="margin-bottom:var(--s3)">
            <label>Où elle va <span class="req">*</span></label>
            <div class="wiz-cascade-fouta">
              <?php produit_emplacement_render_form_fields($emplacement_form_vals); ?>
            </div>
          </div>

          <div class="field" style="max-width:520px; margin-bottom:var(--s3)">
            <label for="notes">Note <span class="muted" style="font-weight:400">(facultative)</span></label>
            <input type="text" name="notes" id="notes" value=""
                   placeholder="Pourquoi ce déplacement — réassort, réorganisation…" maxlength="500">
          </div>

          <button type="submit" class="btn btn-primary">
            <?php echo fpl_icone('transfer', 15); ?> Valider le transfert
          </button>
        </form>
      </div>
    <?php endif; ?>

    <div class="card">
      <h2>Vos derniers transferts</h2>

      <?php if ($recents['lignes'] === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('transfer', 30); ?></span>
          Aucun transfert pour l'instant. Cherchez une pièce ci-dessus pour commencer.
        </div>
      <?php else : ?>
        <?php echo fpl_tablebar_haut($recents, 'transferts'); ?>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Date</th><th>Pièce</th><th>De</th><th>Vers</th><th class="num">Stock</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recents['lignes'] as $m) : ?>
                <tr>
                  <td class="muted" style="white-space:nowrap">
                    <?php echo date('d/m/Y', strtotime($m['date_mouvement'])); ?>
                    <div class="cell-sub"><?php echo date('H:i', strtotime($m['date_mouvement'])); ?></div>
                  </td>
                  <td>
                    <div class="cell-title"><?php echo fpl_e((string) ($m['produit_nom'] ?? '—')); ?></div>
                    <div class="cell-sub"><span class="chip-code"><?php echo e(fpl_code_afficher((string) ($m['produit_code'] ?? ''))); ?></span></div>
                  </td>
                  <td>
                    <?php if (!empty($m['emplacement_source_id'])) : ?>
                      <span class="chip-code" title="<?php echo fpl_e(entrepot_noeud_chemin_libelle((int) $m['emplacement_source_id'])); ?>"><?php echo fpl_e((string) ($m['source_nom'] ?? '—')); ?></span>
                    <?php else : ?>
                      <span class="muted">(sans emplacement)</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($m['emplacement_destination_id'])) : ?>
                      <span class="chip-code" title="<?php echo fpl_e(entrepot_noeud_chemin_libelle((int) $m['emplacement_destination_id'])); ?>"><?php echo fpl_e((string) ($m['destination_nom'] ?? '—')); ?></span>
                    <?php else : ?>
                      <span class="muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="num" style="font-weight:700; color:var(--blue-600)"><?php echo (int) $m['quantite']; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php echo fpl_pager($recents); ?>
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
  .tr-depart {
    display: flex; align-items: center; gap: 8px; margin: 0;
    background: var(--blue-tint); border: 1px solid var(--blue-tint-2);
    border-radius: var(--r-sm); padding: 9px 14px; color: var(--blue-600);
  }
  .tr-depart strong { color: var(--navy); }
  /* La cascade de ce dépôt, aux mesures du wizard (mêmes classes). */
  .wiz-cascade-fouta .form-group { display: flex; flex-direction: column; gap: 5px; margin: 0; }
  .wiz-cascade-fouta .form-group > label { font-size: 14px; font-weight: 600; color: var(--slate); margin: 0; }
  .wiz-cascade-fouta select { width: 100%; }
  .wiz-cascade-fouta .pm-emplacement-form--referentiel {
    padding: 14px 16px; border-radius: var(--r); background: var(--surface); border: 1.5px solid var(--line);
  }
  .wiz-cascade-fouta .pm-emplacement-steps { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
  .wiz-cascade-fouta .pm-emplacement-step {
    display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px;
    background: var(--blue-tint); color: var(--blue-600);
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
  }
  .wiz-cascade-fouta .pm-emplacement-intro { margin: 0 0 10px; font-size: 13.5px; color: var(--slate); }
  .wiz-cascade-fouta .pm-emplacement-cascade { margin-top: 6px; padding-top: 10px; border-top: 1px dashed var(--line); }
  .wiz-cascade-fouta .pm-emplacement-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; margin-bottom: 12px; }
  .wiz-cascade-fouta .pm-emplacement-count { margin: 0 0 10px; padding: 6px 10px; border-radius: 8px; background: var(--blue-tint); color: var(--blue-600); font-size: 13px; font-weight: 600; }
  .wiz-cascade-fouta .pm-emplacement-count[hidden], .wiz-cascade-fouta .pm-emplacement-cascade[hidden], .wiz-cascade-fouta .pm-emplacement-apercu[hidden] { display: none !important; }
  .wiz-cascade-fouta .pm-emplacement-apercu { margin-top: 12px; padding: 10px 14px; border-radius: var(--r-sm); background: var(--blue-tint); border: 1px solid var(--blue-tint-2); }
  .wiz-cascade-fouta .pm-emplacement-apercu__label { display: flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--blue-600); }
  .wiz-cascade-fouta .pm-emplacement-apercu__text { margin: 4px 0 0; font-size: 15px; font-weight: 600; color: var(--ink); }
  @media (max-width: 700px) { .wiz-cascade-fouta .pm-emplacement-row { grid-template-columns: 1fr; } }
</style>

<script>
  (function () {
    // --- Recherche : le choix recharge la page avec la pièce
    const input = document.getElementById('tr-q');
    const results = document.getElementById('tr-results');
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
        row.href = 'transfert.php?produit=' + p.id;
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
  })();
</script>
<script src="/js/admin-emplacement-produit.js<?php echo asset_version_query(); ?>"></script>

    <?php include '../includes/footer.php'; ?>
