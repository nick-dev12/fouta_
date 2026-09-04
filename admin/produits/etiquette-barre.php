<?php
/**
 * ÉTIQUETTE D'UNE BARRE — la page-écran : le libellé en très gros + le QR,
 * noir seul sur l'étiquette jaune vierge, aux cotes RÉELLES du format.
 * La DISPOSITION se règle en direct, format par format (Responsable).
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/etiquette-bac.php (25/08/2026), au moteur de
 * ce dépôt :
 *   - le CONTENU vient de entrepot_noeud_etiquette_payload() — le libellé
 *     composé {code abrégé de l'étage}{niveau lié}-{numéro} (ex. AR1-01)
 *     et le QR du nœud (upload/qrcodes/noeud_N.png) ;
 *   - la GÉOMÉTRIE vient de etiquette_geometrie_barre() : auto-adaptée à
 *     la longueur du libellé et au format, modulée par la disposition
 *     enregistrée (etiquette_formats.disposition_barre, JSON) ;
 *   - « Télécharger en PDF » sort le même dessin à la même géométrie
 *     (emplacement-noeud-etiquette.php?format=N).
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
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';
require_once __DIR__ . '/../../models/model_entrepot_etiquette_parametres.php';
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';

if (!admin_can_gestion_stock()) {
    header('Location: ../dashboard.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$noeud_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$payload = entrepot_noeud_etiquette_payload($noeud_id);
if ($payload === null) {
    $_SESSION['success_message'] = 'Cette étiquette est réservée aux barres.';
    header('Location: etiquettes.php?type=barres');
    exit;
}

$libelle = (string) ($payload['libelle'] ?? '');
$qr_web = (string) ($payload['qr_url'] ?? '');
$chemin = function_exists('entrepot_noeud_chemin_libelle') ? (string) entrepot_noeud_chemin_libelle($noeud_id) : '';

/* LE QR EN NOIR SEUL, SANS FOND (25/08, comme FPL natif) : le PNG stocké
 * porte un fond blanc qui faisait un carré sur le jaune de l'étiquette.
 * Ici, seuls les modules noirs se dessinent (SVG) — le jaune du support
 * traverse. Même contenu que le PNG : le libellé. Repli sur le PNG stocké
 * si le dessin échoue. */
$qr_data_uri = '';
if (is_file(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    try {
        $qr_data_uri = (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'drawLightModules' => false,
            'connectPaths' => true,
            'addQuietzone' => false,
        ])))->render($libelle);
    } catch (Throwable $e) {
        $qr_data_uri = '';
    }
}

// Le format demandé, sinon celui des réglages d'entrepôt, sinon le premier.
$format = !empty($_GET['format']) ? fpl_etiquette_format_get((int) $_GET['format'], 'barre') : false;
if ($format === false) {
    $format = etiquette_format_barre_defaut();
}
if ($format === false) {
    $_SESSION['success_message'] = 'Aucun format d\'étiquette de barre n\'est défini.';
    header('Location: etiquettes.php?type=barres');
    exit;
}
$formats = etiquette_formats_barres();
$g = etiquette_geometrie_barre($format, $libelle);

$peut_regler = function_exists('admin_can_gestion_stock_etendue') && admin_can_gestion_stock_etendue();
$pdf_url = '../parametres/emplacement-noeud-etiquette.php?id=' . $noeud_id
    . (!empty($format['id']) ? '&format=' . (int) $format['id'] : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<?php include __DIR__ . '/../../includes/favicon.php'; ?>
<meta charset="UTF-8">
<title>Étiquette barre <?php echo e($libelle); ?> — <?php echo e((string) $format['nom']); ?> — Administration</title>
<style>
  :root {
    --l: <?php echo $g['largeur']; ?>mm;
    --h: <?php echo $g['hauteur']; ?>mm;
    --pad: <?php echo $g['pad']; ?>mm;
    --qr: <?php echo $g['qr']; ?>mm;
    --gap: <?php echo $g['gap']; ?>mm;
    --code: <?php echo $g['code']; ?>mm;
    --decx: <?php echo $g['decal_x']; ?>mm;
    --decy: <?php echo $g['decal_y']; ?>mm;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: "Arial Black", Arial, sans-serif; background: #666;
    display: flex; flex-direction: column; align-items: center; padding: 20px; gap: 14px; }

  .toolbar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: center;
    max-width: 92vw; background: #fff; border-radius: 12px;
    padding: 9px 12px; box-shadow: 0 6px 22px rgba(0, 0, 0, .3);
  }
  .toolbar a, .toolbar button {
    background: transparent; border: 1px solid #DFE4EC; border-radius: 8px;
    padding: 8px 14px; font-family: Arial, sans-serif; font-size: 13.5px; font-weight: 650;
    color: #10316F; cursor: pointer; text-decoration: none;
    transition: background .12s ease, border-color .12s ease;
  }
  .toolbar a:hover, .toolbar button:hover { border-color: #10316F; }
  .toolbar button { background: #10316F; border-color: #10316F; color: #fff; }
  .toolbar button:hover { background: #0C2350; }
  .toolbar .active { background: #10316F; border-color: #10316F; color: #fff; }

  .ou {
    color: #EDEFF4; font-size: 13.5px; font-weight: 650; text-align: center;
    font-family: Arial, sans-serif;
    background: rgba(0, 0, 0, .25); border-radius: 8px; padding: 6px 14px; max-width: 92vw;
  }

  .label {
    width: var(--l); height: var(--h);
    background: #FFE600; /* aperçu écran uniquement — jamais imprimé */
    padding: var(--pad);
    box-shadow: 0 4px 18px rgba(0, 0, 0, .4); border-radius: 1mm;
    overflow: hidden;
  }
  .pose {
    width: 100%; height: 100%;
    display: flex; align-items: center; gap: var(--gap);
    transform: translate(var(--decx), var(--decy));
  }
  .code {
    flex: 1; min-width: 0; text-align: center;
    font-weight: 900; font-size: var(--code); color: #000;
    line-height: .95; letter-spacing: .02em; white-space: nowrap;
  }
  .qr { width: var(--qr); height: var(--qr); flex-shrink: 0; }
  .pose.qr-gauche .qr { order: -1; }
  .qr img { width: 100%; height: 100%; display: block; }

  .regles {
    background: #fff; border-radius: 10px; padding: 16px 18px;
    font-family: Arial, sans-serif; font-size: 13px; color: #16203A;
    width: min(680px, 94vw);
    display: none;
  }
  .regles.ouvert { display: block; }
  .regles h2 { font-size: 13px; color: #10316F; margin-bottom: 2px; }
  .regles .qui { font-size: 11.5px; color: #5C6A85; margin-bottom: 12px; }
  .regles .grille { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 22px; }
  .regles .ligne { display: flex; align-items: center; gap: 10px; }
  .regles .ligne label { flex: 0 0 118px; font-weight: 700; font-size: 12px; }
  .regles .ligne input[type=range] { flex: 1; accent-color: #10316F; }
  .regles .ligne output { flex: 0 0 64px; text-align: right; font-variant-numeric: tabular-nums; color: #5C6A85; }
  .regles .ligne .paire { display: flex; gap: 6px; }
  .regles .ligne .paire button {
    border: 1px solid #DFE4EC; background: #fff; border-radius: 6px;
    padding: 4px 10px; font-weight: 700; cursor: pointer; color: #16203A; font-size: 12px;
  }
  .regles .ligne .paire button.actif { background: #10316F; border-color: #10316F; color: #fff; }
  .regles .alerte { color: #8F6212; font-size: 11.5px; margin-top: 8px; display: none; }
  .regles .alerte.visible { display: block; }
  .regles .actions { display: flex; gap: 8px; margin-top: 14px; align-items: center; }
  .regles .actions button {
    border: none; border-radius: 6px; padding: 8px 14px; cursor: pointer;
    font-family: inherit; font-size: 13px; font-weight: 700;
  }
  .regles .actions .enregistrer { background: #10316F; color: #fff; }
  .regles .actions .reinit { background: #fff; color: #16203A; border: 1px solid #DFE4EC; }
  .regles .actions .etat { font-size: 12px; color: #12694A; opacity: 0; transition: opacity .2s; }
  .regles .actions .etat.visible { opacity: 1; }

  .hint { color: #D6D9DE; font-size: 13px; max-width: 500px; text-align: center; font-family: Arial, sans-serif; }

  @media print {
    body { background: #fff; padding: 0; }
    .toolbar, .hint, .regles, .ou { display: none !important; }
    .label { background: none; box-shadow: none; border-radius: 0; }
    @page { size: <?php echo $g['largeur']; ?>mm <?php echo $g['hauteur']; ?>mm; margin: 0; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <button onclick="imprimerBarre()">Imprimer (<?php echo e((string) $format['nom']); ?>)</button>
  <a href="<?php echo e($pdf_url); ?>">Télécharger en PDF</a>
  <a href="<?php echo e($pdf_url . (strpos($pdf_url, '?') !== false ? '&' : '?') . 'qr=1'); ?>" target="_blank" rel="noopener">Imprimer le QR seul</a>
  <?php foreach ($formats as $f) : ?>
    <a href="etiquette-barre.php?id=<?php echo (int) $noeud_id; ?>&format=<?php echo (int) $f['id']; ?>"
       class="<?php echo (int) ($format['id'] ?? 0) === (int) $f['id'] ? 'active' : ''; ?>"><?php echo e((string) $f['nom']); ?></a>
  <?php endforeach; ?>
  <?php if ($peut_regler && !empty($format['id'])) : ?>
    <button type="button" onclick="document.getElementById('regles').classList.toggle('ouvert')">Régler la disposition</button>
  <?php endif; ?>
  <a href="etiquettes.php?type=barres">Retour</a>
</div>

<?php if ($chemin !== '') : ?>
  <div class="ou"><?php echo fpl_e($chemin); ?></div>
<?php endif; ?>

<div class="label">
  <div class="pose <?php echo $g['qr_position'] === 'gauche' ? 'qr-gauche' : ''; ?>" id="pose">
    <div class="code" id="code"><?php echo fpl_e($libelle); ?></div>
    <?php if ($qr_data_uri !== '') : ?>
      <div class="qr" id="qr"><img src="<?php echo $qr_data_uri; ?>" alt="QR <?php echo e($libelle); ?>"></div>
    <?php elseif ($qr_web !== '') : ?>
      <div class="qr" id="qr"><img src="../..<?php echo e($qr_web); ?>" alt="QR <?php echo e($libelle); ?>"></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($peut_regler && !empty($format['id'])) : ?>
<div class="regles" id="regles">
  <h2>Disposition de l'étiquette</h2>
  <div class="qui">Réglage enregistré pour le format <strong><?php echo e((string) $format['nom']); ?></strong> —
    il vaut pour TOUTES les étiquettes de barre imprimées à ce format.</div>

  <div class="grille">
    <div class="ligne">
      <label for="r-code">Taille du code</label>
      <input type="range" id="r-code" min="40" max="170" step="5" value="<?php echo $g['code_echelle']; ?>">
      <output id="o-code"><?php echo $g['code_echelle']; ?> %</output>
    </div>
    <div class="ligne">
      <label for="r-qr">Taille du QR</label>
      <input type="range" id="r-qr" min="40" max="170" step="5" value="<?php echo $g['qr_echelle']; ?>">
      <output id="o-qr"><?php echo $g['qr_echelle']; ?> %</output>
    </div>
    <div class="ligne">
      <label>Position du QR</label>
      <div class="paire">
        <button type="button" id="p-gauche" class="<?php echo $g['qr_position'] === 'gauche' ? 'actif' : ''; ?>">En avant (gauche)</button>
        <button type="button" id="p-droite" class="<?php echo $g['qr_position'] === 'droite' ? 'actif' : ''; ?>">À droite</button>
      </div>
    </div>
    <div class="ligne">
      <label for="r-ecart">Écart code ↔ QR</label>
      <input type="range" id="r-ecart" min="0" max="20" step="0.5" value="<?php echo $g['gap']; ?>">
      <output id="o-ecart"><?php echo $g['gap']; ?> mm</output>
    </div>
    <div class="ligne">
      <label for="r-decx">Gauche ↔ droite</label>
      <input type="range" id="r-decx" min="-20" max="20" step="0.5" value="<?php echo $g['decal_x']; ?>">
      <output id="o-decx"><?php echo $g['decal_x']; ?> mm</output>
    </div>
    <div class="ligne">
      <label for="r-decy">Haut ↔ bas</label>
      <input type="range" id="r-decy" min="-20" max="20" step="0.5" value="<?php echo $g['decal_y']; ?>">
      <output id="o-decy"><?php echo $g['decal_y']; ?> mm</output>
    </div>
    <div class="ligne">
      <label for="r-marge">Marge du bord</label>
      <input type="range" id="r-marge" min="0" max="15" step="0.5" value="<?php echo $g['pad']; ?>">
      <output id="o-marge"><?php echo $g['pad']; ?> mm</output>
    </div>
  </div>

  <div class="alerte" id="alerte-qr">Sous 14,5 mm, un QR thermique devient difficile à scanner — testez avant d'imprimer en série.</div>

  <div class="actions">
    <button type="button" class="enregistrer" id="btn-enregistrer">Enregistrer pour ce format</button>
    <button type="button" class="reinit" id="btn-reinit">Revenir à l'automatique</button>
    <span class="etat" id="etat">Enregistré ✓</span>
  </div>
</div>
<?php endif; ?>

<div class="hint">
  Dans la fenêtre d'impression, choisissez l'imprimante à étiquettes — format <?php echo e((string) $format['nom']); ?>, marges 0.
</div>

<script>
  // Chaque impression laisse une trace : qui, quand, quel format.
  function imprimerBarre() {
    fetch('ajax_etiquette_imprimee.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ type: 'barre', id: <?php echo (int) $noeud_id; ?>, format_id: <?php echo (int) ($format['id'] ?? 0) ?: 'null'; ?>, _jeton: <?php echo json_encode((string) $_SESSION['admin_csrf']); ?> }),
    }).catch(() => {}).finally(() => window.print());
  }

  <?php if ($peut_regler && !empty($format['id'])) : ?>
  // ----- Le panneau de disposition : l'aperçu bouge en direct, puis s'enregistre.
  (function () {
    const el = (id) => document.getElementById(id);
    const racine = document.documentElement;
    const qrBrut = <?php echo json_encode(round($g['qr'] / max(1, $g['qr_echelle'] / 100), 2)); ?>;
    const codeBrut = <?php echo json_encode(round($g['code'] / max(1, $g['code_echelle'] / 100), 2)); ?>;

    function majApercu() {
      racine.style.setProperty('--code', (codeBrut * (+el('r-code').value) / 100).toFixed(2) + 'mm');
      const qrMm = qrBrut * (+el('r-qr').value) / 100;
      racine.style.setProperty('--qr', qrMm.toFixed(2) + 'mm');
      racine.style.setProperty('--gap', (+el('r-ecart').value).toFixed(1) + 'mm');
      racine.style.setProperty('--decx', (+el('r-decx').value).toFixed(1) + 'mm');
      racine.style.setProperty('--decy', (+el('r-decy').value).toFixed(1) + 'mm');
      racine.style.setProperty('--pad', (+el('r-marge').value).toFixed(1) + 'mm');
      el('o-code').textContent = el('r-code').value + ' %';
      el('o-qr').textContent = el('r-qr').value + ' %';
      el('o-ecart').textContent = (+el('r-ecart').value).toFixed(1) + ' mm';
      el('o-decx').textContent = (+el('r-decx').value).toFixed(1) + ' mm';
      el('o-decy').textContent = (+el('r-decy').value).toFixed(1) + ' mm';
      el('o-marge').textContent = (+el('r-marge').value).toFixed(1) + ' mm';
      el('alerte-qr').classList.toggle('visible', qrMm < 14.5);
    }
    ['r-code', 'r-qr', 'r-ecart', 'r-decx', 'r-decy', 'r-marge'].forEach(id => el(id).addEventListener('input', majApercu));

    let qrPosition = <?php echo json_encode($g['qr_position']); ?>;
    function majPosition(pos) {
      qrPosition = pos;
      document.getElementById('pose').classList.toggle('qr-gauche', pos === 'gauche');
      el('p-gauche').classList.toggle('actif', pos === 'gauche');
      el('p-droite').classList.toggle('actif', pos === 'droite');
    }
    el('p-gauche').addEventListener('click', () => majPosition('gauche'));
    el('p-droite').addEventListener('click', () => majPosition('droite'));

    function envoyer(corps) {
      return fetch('ajax_disposition_barre.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(Object.assign({
          format_id: <?php echo (int) $format['id']; ?>,
          _jeton: <?php echo json_encode((string) $_SESSION['admin_csrf']); ?>,
        }, corps)),
      });
    }

    el('btn-enregistrer').addEventListener('click', function () {
      envoyer({
        qr_position: qrPosition,
        qr_echelle: +el('r-qr').value,
        code_echelle: +el('r-code').value,
        decal_x: +el('r-decx').value,
        decal_y: +el('r-decy').value,
        marge: +el('r-marge').value,
        ecart: +el('r-ecart').value,
      }).then(r => {
        if (!r.ok) return;
        el('etat').classList.add('visible');
        /* On RECHARGE : l'aperçu est alors redessiné par le serveur avec la
           disposition enregistrée (bornes comprises) — donc EXACTEMENT ce que
           sortira le PDF. Sans ça, l'aperçu restait sur l'approximation JS et
           semblait ne pas correspondre au PDF. */
        setTimeout(() => location.reload(), 700);
      }).catch(() => {});
    });

    el('btn-reinit').addEventListener('click', function () {
      envoyer({ reinitialiser: 1 }).then(r => {
        if (r.ok) location.reload();
      }).catch(() => {});
    });

    majApercu();
  })();
  <?php endif; ?>
</script>

</body>
</html>
