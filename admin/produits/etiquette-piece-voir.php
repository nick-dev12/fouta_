<?php
/**
 * VOIR L'ÉTIQUETTE D'UNE PIÈCE — la page-écran, l'étiquette et rien d'autre.
 *
 * Pourquoi cette page (07/09/2026, demande de la direction) : l'infographiste
 * doit voir COMMENT REND l'étiquette de n'importe quelle pièce, pas seulement
 * de celle qu'il est en train d'illustrer. Or « Toutes les étiquettes » menait
 * jusqu'ici à la fiche de la pièce (ajuster-stock.php), qui lui est fermée —
 * prix, stock, fournisseur. Il cliquait « Voir l'étiquette » et l'écran le
 * renvoyait chez lui, sans un mot.
 *
 * Cette page ne montre QUE : l'identité de la pièce (nom, référence, marque,
 * OEM), l'étiquette rendue à sa taille physique, les tailles au choix, le PDF
 * et l'impression. Aucun prix, aucun stock, aucun fournisseur — elle est donc
 * ouvrable par tous les profils qui voient déjà la liste des étiquettes.
 *
 * L'image vient du MÊME moteur que le PDF et que la fiche
 * (etiquette-piece-image.php → includes/etiquette_fpl70.php) : ce que l'écran
 * montre est ce que l'imprimante sort.
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
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_produit_etiquette_parametres.php';

if (!admin_can_voir_etiquettes()) {
    header('Location: ../' . admin_role_default_redirect_path(admin_current_role()));
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$produit = get_produit_by_id(isset($_GET['id']) ? (int) $_GET['id'] : 0);
if ($produit === false) {
    $_SESSION['success_message'] = 'Cette pièce n\'existe pas.';
    header('Location: etiquettes.php?type=pieces');
    exit;
}
$produit_id = (int) $produit['id'];

/* LE FORMAT DEMANDÉ, sinon celui du réglage — la même règle que la fiche et
 * que le PDF (fpl_etiquette_format_ou_reglage). */
$format_id = isset($_GET['format']) ? (int) $_GET['format'] : 0;
$format_courant = $format_id > 0 ? fpl_etiquette_format_get($format_id, 'piece') : false;
$formats = fpl_etiquette_formats_pieces();
$dims = $format_courant !== false
    ? ['largeur_mm' => (float) $format_courant['largeur_mm'], 'hauteur_mm' => (float) $format_courant['hauteur_mm']]
    : fpl_etiquette_dims();

/* L'ÉTIQUETTE S'AFFICHE À SA TAILLE PHYSIQUE (règle du 03/09) : le dessin est
 * carré, il s'imprime au CÔTÉ COURT du format — une 50×30 se voit petite,
 * comme elle sortira (430 px ≡ 70 mm). */
$cote_mm = max(1.0, min((float) $dims['largeur_mm'], (float) $dims['hauteur_mm']));
$img_css = max(150, min(560, (int) round(430 * $cote_mm / 70)));

$img_src = 'etiquette-piece-image.php?id=' . $produit_id
    . ($format_courant !== false ? '&amp;format=' . (int) $format_courant['id'] : '') . '&amp;cote=1080';
$pdf_href = 'etiquette-piece-pdf.php?id=' . $produit_id
    . ($format_courant !== false ? '&amp;format=' . (int) $format_courant['id'] : '');

$ref = fpl_code_afficher(strtoupper(trim((string) ($produit['reference_fpl'] ?? ''))));
if ($ref === '') {
    $ref = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
}
$oem = trim((string) ($produit['reference_oem'] ?? ''));
$nom_wolof = trim((string) ($produit['nom_wolof'] ?? ''));

/* La marque et la famille se lisent en clair : deux jointures, aucune donnée
 * commerciale. */
$marque = '';
$famille = '';
try {
    $st = $db->prepare(
        "SELECT m.nom AS marque_nom, c.nom AS categorie_nom, sc.nom AS sous_categorie_nom
           FROM produits p
      LEFT JOIN marques m ON m.id = p.marque_id
      LEFT JOIN categories c ON c.id = p.categorie_id
      LEFT JOIN sous_categories sc ON sc.id = p.sous_categorie_id
          WHERE p.id = :id LIMIT 1"
    );
    $st->execute([':id' => $produit_id]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $marque = trim((string) ($r['marque_nom'] ?? ''));
    $famille = trim((string) ($r['categorie_nom'] ?? ''));
    if (!empty($r['sous_categorie_nom'])) {
        $famille .= ($famille !== '' ? ' › ' : '') . trim((string) $r['sous_categorie_nom']);
    }
} catch (PDOException $e) {
    $marque = '';
    $famille = '';
}

$est_infographiste = function_exists('admin_current_role') && admin_current_role() === 'photographe';

$fpl_titre_page = 'Étiquette de la pièce';
$fpl_retour_page = 'etiquettes.php?type=pieces';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étiquette — <?php echo fpl_e($produit['nom']); ?></title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <style>
    .ev-wrap { max-width: 900px; margin: 0 auto; padding: 18px 16px 40px; }
    .ev-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
    .ev-top a { color: var(--navy, #10316F); text-decoration: none; font-weight: 600; font-size: 13.5px; }
    .ev-card { background: #fff; border: 1px solid #E5EAF2; border-radius: 14px; padding: 18px; box-shadow: 0 2px 10px rgba(15,32,64,.05); }
    .ev-card + .ev-card { margin-top: 18px; }
    .ev-card h1 { font-size: 21px; color: var(--navy, #10316F); margin: 0 0 2px; line-height: 1.2; }
    .ev-wolof { font-size: 13.5px; color: #5C6A85; font-style: italic; }
    .ev-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .ev-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; padding: 4px 10px; font-size: 12.5px; background: #F3F6FB; color: #33415A; }
    .ev-chip b { color: #16203A; }
    .ev-ref { font-family: Consolas, monospace; background: #ECF2FC; color: var(--navy, #10316F); border-radius: 8px; padding: 3px 10px; font-weight: 700; letter-spacing: .5px; }
    .ev-tailles { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 4px 0 14px; }
    .ev-tailles .l { font-size: 12.5px; font-weight: 600; color: #5A6A85; }
    .ev-taille { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 999px; border: 1.5px solid #DFE4EC; background: #fff; color: #1c2733; font-size: 12.5px; font-weight: 600; text-decoration: none; }
    .ev-taille:hover { border-color: #2957ae; }
    .ev-taille.on { border-color: var(--navy, #10316F); background: #eef3fd; color: var(--navy, #10316F); }
    .ev-apercu { text-align: center; padding: 6px 0 4px; }
    .ev-apercu img { display: block; margin: 0 auto; border-radius: 12px; box-shadow: 0 10px 26px rgba(16,49,111,.14); height: auto; }
    .ev-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 16px; }
    .ev-btn { border: 0; border-radius: 10px; padding: 10px 18px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .ev-btn-primary { background: var(--navy, #10316F); color: #fff; }
    .ev-btn-ghost { background: #EEF2F8; color: #08193A; }
    .ev-hint { font-size: 12.5px; color: #8894A8; text-align: center; margin-top: 10px; }
    </style>
</head>
<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="ev-wrap">
        <div class="ev-top">
            <a href="etiquettes.php?type=pieces">← Toutes les étiquettes</a>
            <?php if ($est_infographiste): ?>
            <a href="photo-editer.php?id=<?php echo $produit_id; ?>">Modifier les images de cette pièce →</a>
            <?php endif; ?>
        </div>

        <div class="ev-card">
            <h1><?php echo fpl_e($produit['nom']); ?></h1>
            <?php if ($nom_wolof !== '' && $nom_wolof !== trim((string) $produit['nom'])): ?>
                <div class="ev-wolof"><?php echo fpl_e($nom_wolof); ?></div>
            <?php endif; ?>
            <div class="ev-chips">
                <?php if ($ref !== ''): ?><span class="ev-ref"><?php echo fpl_e($ref); ?></span><?php endif; ?>
                <?php if ($marque !== ''): ?><span class="ev-chip">Marque <b><?php echo fpl_e($marque); ?></b></span><?php endif; ?>
                <?php if ($famille !== ''): ?><span class="ev-chip">Famille <b><?php echo fpl_e($famille); ?></b></span><?php endif; ?>
                <?php if ($oem !== ''): ?><span class="ev-chip">OEM <b><?php echo fpl_e($oem); ?></b></span><?php endif; ?>
            </div>
        </div>

        <div class="ev-card"
             id="fpl-etiquette-print-root"
             data-fpl-w="<?php echo fpl_e((string) $dims['largeur_mm']); ?>"
             data-fpl-h="<?php echo fpl_e((string) $dims['hauteur_mm']); ?>">
            <?php if ($formats !== []): ?>
            <div class="ev-tailles" role="group" aria-label="Taille de l'étiquette">
                <span class="l">Taille :</span>
                <a class="ev-taille<?php echo $format_courant === false ? ' on' : ''; ?>"
                   href="etiquette-piece-voir.php?id=<?php echo $produit_id; ?>">Réglage (<?php echo fpl_e(fpl_etiquette_dims_label_short(fpl_etiquette_dims())); ?>)</a>
                <?php foreach ($formats as $f): ?>
                <a class="ev-taille<?php echo $format_courant !== false && (int) $format_courant['id'] === (int) $f['id'] ? ' on' : ''; ?>"
                   href="etiquette-piece-voir.php?id=<?php echo $produit_id; ?>&amp;format=<?php echo (int) $f['id']; ?>"><?php echo fpl_e((string) $f['nom']); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="ev-apercu">
                <img id="fpl-etiq70-img" src="<?php echo $img_src; ?>"
                     alt="Étiquette de la pièce <?php echo fpl_e((string) ($produit['identifiant_interne'] ?? '')); ?>"
                     style="width: min(<?php echo (int) $img_css; ?>px, 100%);">
            </div>

            <div class="ev-actions">
                <button type="button" class="ev-btn ev-btn-primary" onclick="imprimerEtiquettePiece()">
                    <i class="fas fa-print" aria-hidden="true"></i> Imprimer l'étiquette
                </button>
                <a class="ev-btn ev-btn-ghost" href="<?php echo $pdf_href; ?>">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i> Télécharger en PDF
                </a>
            </div>
            <div class="ev-hint">L'étiquette est affichée à sa taille réelle (<?php echo fpl_e(fpl_etiquette_dims_label_short($dims)); ?>) : c'est ce que l'imprimante sort.</div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
    /* IMPRIMER : le même geste que la fiche pièce — une fenêtre à la taille
       exacte du format, l'image posée au côté court. La trace « imprimée » part
       en tâche de fond ; elle ne bloque jamais l'impression. */
    function imprimerEtiquettePiece() {
        var root = document.getElementById('fpl-etiquette-print-root');
        var img = document.getElementById('fpl-etiq70-img');
        if (!root || !img || !img.src) return;
        try {
            fetch('ajax_etiquette_imprimee.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    type: 'piece',
                    id: <?php echo $produit_id; ?>,
                    format_id: <?php echo $format_courant !== false ? (int) $format_courant['id'] : 'null'; ?>,
                    _jeton: <?php echo json_encode((string) $_SESSION['admin_csrf']); ?>
                })
            });
        } catch (e) { /* la trace ne bloque jamais l'impression */ }

        var mmW = parseFloat(root.getAttribute('data-fpl-w')) || 70;
        var mmH = parseFloat(root.getAttribute('data-fpl-h')) || 70;
        var mmCote = Math.min(mmW, mmH);
        var w = window.open('', '_blank', 'width=420,height=460');
        if (!w || !w.document) return;
        var doc = w.document;
        doc.open();
        doc.write('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Étiquette FPL ' + mmW + '×' + mmH + ' mm</title>');
        doc.write('<style>');
        doc.write('@page{size:' + mmW + 'mm ' + mmH + 'mm;margin:0}');
        doc.write('html,body{margin:0;padding:0;width:' + mmW + 'mm;height:' + mmH + 'mm;overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}');
        doc.write('img{width:' + mmCote + 'mm;height:' + mmCote + 'mm;display:block;}');
        doc.write('</style></head><body><img src="' + String(img.src).replace(/"/g, '&quot;') + '" alt=""></body></html>');
        doc.close();
        var lance = false;
        function imprimer() {
            if (lance) return;
            lance = true;
            setTimeout(function () {
                try { w.focus(); w.print(); } catch (e) {}
            }, 200);
        }
        var im = w.document.images[0];
        if (im && im.complete) { imprimer(); }
        else if (im) { im.onload = imprimer; im.onerror = imprimer; }
        else { imprimer(); }
    }
    </script>
</body>
</html>
