<?php
/**
 * TOUT DÉTOURER (03/09/2026) — un seul geste pour retirer le fond de TOUTES les
 * photos de pièces d'un coup. Les pièces à fond uni (studio blanc) sont
 * nettoyées ; celles à fond chargé sont laissées telles quelles.
 *
 * Le travail est graphique et long : il tourne en arrière-plan
 * (detourage-lot-worker.php), la page suit la progression et montre une planche
 * de preuve au fur et à mesure. Les photos d'origine ne sont JAMAIS modifiées :
 * seul le cache des étiquettes est rempli (les étiquettes s'affichent alors
 * avec le fond retiré, sans attendre).
 *
 * Programmation procédurale uniquement.
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

if (admin_is_restricted_admin_account()) {
    header('Location: mon-travail.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['admin_csrf'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tout détourer — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
    <style>
      .det-intro { color: var(--muted); line-height: 1.55; max-width: 70ch; }
      .det-intro b { color: var(--ink); }
      .det-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: var(--s3); }
      .det-refaire { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: .9em; }
      .det-suivi { margin-top: var(--s4); display: none; }
      .det-barre-fond { height: 14px; border-radius: 8px; background: #e6e9f2; overflow: hidden; }
      .det-barre-jauge { height: 100%; width: 0; background: var(--bleu, #10316F); transition: width .4s ease; }
      .det-chiffres { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 10px; color: var(--muted); }
      .det-chiffres b { color: var(--ink); font-size: 1.15em; }
      .det-planche { margin-top: var(--s4); display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
      .det-vign {
        aspect-ratio: 1 / 1; border-radius: 10px; border: 1px solid var(--bord, #e2e5ee);
        display: flex; align-items: center; justify-content: center; padding: 6px;
        background-color: #fff;
        background-image:
          linear-gradient(45deg, #d7dae4 25%, transparent 25%),
          linear-gradient(-45deg, #d7dae4 25%, transparent 25%),
          linear-gradient(45deg, transparent 75%, #d7dae4 75%),
          linear-gradient(-45deg, transparent 75%, #d7dae4 75%);
        background-size: 16px 16px;
        background-position: 0 0, 0 8px, 8px -8px, -8px 0;
      }
      .det-vign img { max-width: 100%; max-height: 100%; object-fit: contain; }
      .det-fini { margin-top: var(--s3); padding: 12px 14px; border-radius: 10px; background: #eaf6ee; color: #1c6b3a; display: none; }
      .det-fini.warn { background: #fdf1e7; color: #8a4b12; }
    </style>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

      <div class="lab-tabs">
        <a href="etiquettes.php?type=pieces" class="lab-tab">
          <div class="lab-tab-title"><?php echo fpl_icone('tag', 15); ?> Étiquettes de pièce</div>
          <span class="lab-tab-sub">Collées sur la pièce — photo, code FPL, QR</span>
        </a>
        <a href="detourage-lot.php" class="lab-tab on">
          <div class="lab-tab-title"><?php echo fpl_icone('image', 15); ?> Tout détourer</div>
          <span class="lab-tab-sub">Retirer le fond de toutes les photos</span>
        </a>
      </div>

      <div class="card">
        <h2 style="margin-top:0"><?php echo fpl_icone('image', 20); ?> Retirer le fond de toutes les photos</h2>
        <p class="det-intro">
          Ce bouton parcourt <b>toutes les pièces</b> et retire automatiquement le fond
          des photos qui ont un <b>fond uni</b> (studio blanc). La photo découpée sert
          <b>sur l'étiquette</b> : la pièce se pose alors proprement sur le fond camion, sans
          carré blanc. Les photos à <b>fond chargé</b> (atelier, décor) sont reconnues et
          <b>laissées telles quelles</b>.
        </p>
        <p class="det-intro" style="margin-top:10px">
          Vos <b>photos d'origine ne sont pas modifiées</b> : rien n'est écrasé, le traitement
          se contente de préparer les étiquettes. Une photo déjà traitée n'est pas refaite
          (c'est instantané la fois suivante).
        </p>

        <div class="det-actions">
          <button type="button" id="det-lancer" class="btn btn-primary">
            <?php echo fpl_icone('image', 15); ?> Détourer toutes les photos
          </button>
          <label class="det-refaire">
            <input type="checkbox" id="det-refaire"> Tout refaire (même les photos déjà traitées)
          </label>
        </div>

        <div class="det-suivi" id="det-suivi">
          <div class="det-barre-fond"><div class="det-barre-jauge" id="det-jauge"></div></div>
          <div class="det-chiffres">
            <span><b id="det-fait">0</b> / <span id="det-total">0</span> photos</span>
            <span>Fond retiré : <b id="det-uni">0</b></span>
            <span>Fond chargé (laissé) : <b id="det-charge">0</b></span>
            <span>Fichier manquant : <b id="det-absente">0</b></span>
          </div>
          <div class="det-fini" id="det-fini"></div>
          <div class="det-planche" id="det-planche"></div>
        </div>
      </div>

    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    (function () {
      var CSRF = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
      var btn = document.getElementById('det-lancer');
      var suivi = document.getElementById('det-suivi');
      var jauge = document.getElementById('det-jauge');
      var elFait = document.getElementById('det-fait');
      var elTotal = document.getElementById('det-total');
      var elUni = document.getElementById('det-uni');
      var elCharge = document.getElementById('det-charge');
      var elAbsente = document.getElementById('det-absente');
      var planche = document.getElementById('det-planche');
      var fini = document.getElementById('det-fini');
      var minuteur = null;
      var vues = {};

      function maj(etat) {
        suivi.style.display = 'block';
        var total = etat.total || 0;
        var fait = etat.fait || 0;
        elTotal.textContent = total;
        elFait.textContent = fait;
        elUni.textContent = etat.uni || 0;
        elCharge.textContent = etat.charge || 0;
        elAbsente.textContent = etat.absente || 0;
        jauge.style.width = (total ? Math.round(fait * 100 / total) : 0) + '%';
        (etat.ids_uni || []).forEach(function (id) {
          if (vues[id]) { return; }
          vues[id] = true;
          var cell = document.createElement('div');
          cell.className = 'det-vign';
          var img = document.createElement('img');
          img.loading = 'lazy';
          img.alt = 'Pièce ' + id + ' détourée';
          img.src = 'detourage-lot-apercu.php?id=' + id;
          cell.appendChild(img);
          planche.appendChild(cell);
        });
      }

      function terminer(etat) {
        if (minuteur) { clearInterval(minuteur); minuteur = null; }
        btn.disabled = false;
        btn.innerHTML = <?php echo json_encode(fpl_icone('rotate-ccw', 15) . ' Relancer', JSON_UNESCAPED_UNICODE); ?>;
        if (etat && etat.erreur) {
          fini.className = 'det-fini warn'; fini.style.display = 'block';
          fini.textContent = 'Le traitement s\'est arrêté : ' + etat.erreur;
        } else if (etat && etat.interrompu) {
          fini.className = 'det-fini warn'; fini.style.display = 'block';
          fini.textContent = 'Le traitement a été interrompu avant la fin. Vous pouvez le relancer : il reprend là où le cache s\'est arrêté.';
        } else {
          fini.className = 'det-fini'; fini.style.display = 'block';
          fini.textContent = 'Terminé : ' + (etat ? (etat.uni || 0) : 0) + ' photo(s) détourée(s). '
            + 'Les étiquettes concernées s\'affichent maintenant avec le fond retiré.';
        }
      }

      function sonder() {
        fetch('detourage-lot-status.php', { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (!d.ok || !d.etat) { return; }
            maj(d.etat);
            if (d.etat.termine || d.etat.interrompu) { terminer(d.etat); }
          })
          .catch(function () {});
      }

      function lancer() {
        btn.disabled = true;
        fini.style.display = 'none';
        var fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('refaire', document.getElementById('det-refaire').checked ? '1' : '0');
        fetch('detourage-lot-start.php', { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (!d.ok) {
              btn.disabled = false;
              fini.className = 'det-fini warn'; fini.style.display = 'block';
              fini.textContent = d.error || 'Impossible de lancer le traitement.';
              return;
            }
            suivi.style.display = 'block';
            elTotal.textContent = d.total || 0;
            if (!minuteur) { minuteur = setInterval(sonder, 1500); }
            setTimeout(sonder, 600);
          })
          .catch(function () {
            btn.disabled = false;
            fini.className = 'det-fini warn'; fini.style.display = 'block';
            fini.textContent = 'Erreur réseau au lancement.';
          });
      }

      btn.addEventListener('click', lancer);

      // À l'ouverture : refléter un lot déjà en cours ou déjà terminé.
      fetch('detourage-lot-status.php', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok || !d.etat) { return; }
          maj(d.etat);
          if (d.etat.termine || d.etat.interrompu) {
            terminer(d.etat);
          } else {
            btn.disabled = true;
            if (!minuteur) { minuteur = setInterval(sonder, 1500); }
          }
        })
        .catch(function () {});
    })();
    </script>
</body>

</html>
