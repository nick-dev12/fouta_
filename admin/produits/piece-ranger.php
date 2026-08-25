<?php
/**
 * « OÙ RANGER CETTE PIÈCE ? » — l'entrée par le nom générique : tapez
 * « filtre », le rangement se trouve tout seul (nom, mots-clés, ou déduit
 * des pièces déjà rangées). Les doublons se signalent avant d'être créés.
 * Programmation procédurale uniquement
 *
 * Portage de fpl_natif/admin/piece-ranger.php. C'est la destination du bouton
 * « Ajouter une pièce par son nom » du catalogue : on ne choisit plus une
 * catégorie dans une liste, on dit ce qu'on a en main et l'écran propose où
 * le ranger.
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

// Ranger une pièce, c'est en créer une.
if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}

$fpl_titre_page = 'Ajouter une pièce';
$fpl_retour_page = 'index.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une pièce — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

    <div class="card" style="max-width:760px; margin-left:auto; margin-right:auto">
      <div style="text-align:center; padding:var(--s4) 0 var(--s5)">
        <div style="font-size:19px; font-weight:700; color:var(--navy); letter-spacing:-.01em">
          Quelle pièce voulez-vous ajouter ?
        </div>
        <div class="muted" style="margin-top:5px">
          Tapez son nom courant — « filtre », « plaquette », « courroie »… — le rangement se trouve tout seul.
        </div>
      </div>

      <div class="scan-bar">
        <span style="color:var(--blue-600); display:flex"><?php echo fpl_icone('search', 20); ?></span>
        <input type="text" id="pp-q" placeholder="Ex. filtre, disque, amortisseur…" autocomplete="off" autofocus>
      </div>

      <div id="pp-results" style="margin-top:var(--s4)"></div>

      <div id="pp-hint" class="muted" style="text-align:center; padding:var(--s6) 0">
        Saisissez au moins 2 lettres.
      </div>
    </div>

    </div><!-- .page-produits-admin -->

    <?php include '../includes/footer.php'; ?>

<template id="tpl-empty">
  <div class="empty">
    <span class="big"><?php echo fpl_icone('search', 32); ?></span>
    Aucun rangement ne correspond.
    <div style="margin-top:var(--s3)">
      <a href="../categories/ajouter.php" class="btn btn-outline btn-sm">Créer une catégorie</a>
    </div>
  </div>
</template>

<script>
  (function () {
    const input = document.getElementById('pp-q');
    const box = document.getElementById('pp-results');
    const hint = document.getElementById('pp-hint');
    const tplEmpty = document.getElementById('tpl-empty');
    let timer = null;

    function esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function render(data) {
      box.innerHTML = '';
      hint.hidden = true;

      if (!data.categories.length && !data.products.length) {
        box.appendChild(tplEmpty.content.cloneNode(true));
        return;
      }

      // OÙ LA RANGER — les rayons proposés, celui déduit des pièces déjà
      // rangées portant le mot en tête.
      if (data.categories.length) {
        const h = document.createElement('div');
        h.className = 'pp-title';
        h.textContent = 'Ranger dans';
        box.appendChild(h);

        const grid = document.createElement('div');
        grid.className = 'pp-grid';
        data.categories.forEach(c => {
          const a = document.createElement('a');
          a.className = 'pp-card';
          a.href = c.url;
          a.innerHTML =
            (c.image
              ? `<img src="${esc(c.image)}" alt="" onerror="this.outerHTML='<div class=\\'pp-ph\\'></div>'">`
              : `<div class="pp-ph"></div>`) +
            `<div class="pp-body">
               <strong>${esc(c.name)}</strong>
               <div class="muted">${esc(c.parent ?? '')}</div>
               ${c.origine === 'pieces'
                  ? `<div class="pp-tag">${c.nb} pièce(s) de ce type y sont rangées</div>`
                  : ''}
             </div>
             <span class="pp-go">Ajouter</span>`;
          grid.appendChild(a);
        });
        box.appendChild(grid);
      }

      // LES DOUBLONS — on les montre AVANT de créer, pas après.
      if (data.products.length) {
        const h = document.createElement('div');
        h.className = 'pp-title';
        h.textContent = 'Ces pièces existent déjà';
        box.appendChild(h);

        const list = document.createElement('div');
        list.className = 'pp-exist';
        data.products.forEach(p => {
          const a = document.createElement('a');
          a.href = p.url;
          a.className = 'pp-exist-row';
          a.innerHTML =
            `<div><strong>${esc(p.name)}</strong>
               <div class="muted"><span class="chip-code">${esc(p.code)}</span>
               ${p.oem ? ' · ' + esc(p.oem) : ''} · ${esc(p.path)}</div>
             </div>
             <span class="muted">Ouvrir la fiche</span>`;
          list.appendChild(a);
        });
        box.appendChild(list);
      }
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = this.value.trim();
      if (q.length < 2) {
        box.innerHTML = '';
        hint.hidden = false;
        return;
      }
      timer = setTimeout(async () => {
        try {
          const r = await fetch('ajax_piece_ranger.php?q=' + encodeURIComponent(q));
          if (r.ok) render(await r.json());
        } catch (e) { /* réseau indisponible */ }
      }, 220);
    });
  })();
</script>

<style>
  /* Repris tel quel de fpl_natif/admin/piece-ranger.php. */
  .scan-bar {
    display: flex; align-items: center; gap: 10px;
    border: 1.5px solid var(--line); border-radius: var(--r);
    padding: 12px 16px; background: var(--surface);
  }
  .scan-bar:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px var(--blue-tint); }
  .scan-bar input {
    flex: 1; border: 0; outline: 0; font: inherit; font-size: 16px;
    background: transparent; color: var(--ink);
  }
  .pp-title {
    font-size: 10.5px; font-weight: 650; color: var(--slate-soft, #8a94a6);
    text-transform: uppercase; letter-spacing: .08em;
    margin: var(--s4) 0 var(--s3);
  }
  .pp-grid { display: flex; flex-direction: column; gap: var(--s2); }
  .pp-card {
    display: flex; align-items: center; gap: var(--s3);
    border: 1px solid var(--line); border-radius: var(--r);
    padding: 10px 12px; text-decoration: none; color: var(--ink); background: var(--surface);
    transition: border-color .12s ease, box-shadow .12s ease;
  }
  .pp-card:hover { border-color: var(--blue); box-shadow: var(--sh-2); text-decoration: none; }
  .pp-card img, .pp-ph {
    width: 46px; height: 46px; border-radius: var(--r-sm); object-fit: cover; flex-shrink: 0;
    background: var(--blue-tint);
  }
  .pp-body { flex: 1; min-width: 0; }
  .pp-body strong { color: var(--navy); }
  .pp-tag { font-size: 12px; color: var(--blue-600); margin-top: 2px; }
  .pp-go {
    flex-shrink: 0; font-size: 13px; font-weight: 600; color: var(--blue);
    border: 1px solid var(--line); border-radius: var(--r-full, 999px); padding: 5px 12px;
  }
  .pp-exist { display: flex; flex-direction: column; gap: 6px; }
  .pp-exist-row {
    display: flex; align-items: center; justify-content: space-between; gap: var(--s3);
    border: 1px dashed var(--line); border-radius: var(--r);
    padding: 9px 12px; text-decoration: none; color: var(--ink);
  }
  .pp-exist-row:hover { border-style: solid; border-color: var(--blue); text-decoration: none; }
</style>
