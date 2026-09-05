<?php
/**
 * L'ÉDITEUR PHOTO D'UNE PIÈCE — l'espace du photographe.
 * Le SEUL travail ici : les photos (téléverser / coller / réordonner / retirer
 * / choisir la principale) et VÉRIFIER que le détourage rend bien. Aucun autre
 * champ (ni prix, ni stock, ni fournisseur). Enregistre par ajax_photo_enregistrer.php.
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

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$piece = null;
if ($id > 0) {
    try {
        $st = $db->prepare(
            "SELECT p.id, p.identifiant_interne, p.nom, p.images, p.image_principale,
                    c.nom AS categorie_nom, m.nom AS marque_nom
               FROM produits p
          LEFT JOIN categories c ON c.id = p.categorie_id
          LEFT JOIN marques m ON m.id = p.marque_id
              WHERE p.id = :id AND p.sync_deleted_at IS NULL LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $piece = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $piece = null;
    }
}
if ($piece === null) {
    $_SESSION['success_message'] = 'Cette pièce est introuvable.';
    header('Location: photo-travail.php');
    exit;
}

$photos = json_decode((string) $piece['images'], true);
if (!is_array($photos)) {
    $photos = [];
    if (!empty($piece['image_principale'])) {
        $photos[] = (string) $piece['image_principale'];
    }
}
$upload_base = '../../upload/';

$ref = strtoupper(trim((string) $piece['identifiant_interne']));
if (preg_match('/^FPL(\d{9})$/', $ref, $mref)) {
    $ref = 'FPL ' . implode(' ', str_split($mref[1], 3));
}

$fpl_titre_page = 'Photos de la pièce';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES); ?>">
    <title>Photos — <?php echo fpl_e($piece['nom']); ?></title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <style>
    .pe-wrap { max-width: 1180px; margin: 0 auto; padding: 18px 16px 40px; }
    .pe-lead { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; margin-bottom: 4px; }
    .pe-lead h1 { font-size: 22px; color: var(--navy, #10316F); margin: 0; }
    .pe-ref { font-family: Consolas, monospace; background: #ECF2FC; color: var(--navy, #10316F); border-radius: 8px; padding: 3px 10px; font-weight: 700; letter-spacing: .5px; }
    .pe-sub { color: #5C6A85; font-size: 13.5px; margin: 2px 0 18px; }
    .pe-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 20px; align-items: start; }
    @media (max-width: 900px) { .pe-grid { grid-template-columns: 1fr; } }
    .pe-card { background: #fff; border: 1px solid #E5EAF2; border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 10px rgba(15,32,64,.05); }
    .pe-card h2 { font-size: 15px; letter-spacing: .3px; color: var(--navy-ink, #08193A); margin: 0 0 12px; }
    .pe-photos { display: flex; flex-wrap: wrap; gap: 12px; }
    .pe-photo { position: relative; width: 150px; border: 2px solid #E5EAF2; border-radius: 12px; overflow: hidden; background: #F7F9FC; cursor: grab; }
    .pe-photo.principale { border-color: var(--navy, #10316F); }
    .pe-photo img { display: block; width: 100%; height: 130px; object-fit: contain; background:#fff; }
    .pe-photo .pe-badge { position: absolute; top: 6px; left: 6px; background: var(--navy, #10316F); color: #fff; font-size: 11px; font-weight: 700; border-radius: 6px; padding: 2px 7px; }
    .pe-photo .pe-actions { display: flex; gap: 4px; padding: 6px; background: #fff; border-top: 1px solid #EEF1F6; }
    .pe-photo .pe-actions button { flex: 1; border: 1px solid #DBE2EE; background: #fff; border-radius: 6px; font-size: 11.5px; padding: 4px 2px; cursor: pointer; color: #33415A; }
    .pe-photo .pe-actions button:hover { background: #EEF3FB; }
    .pe-photo .pe-actions .pe-del:hover { background: #FBE9E7; color: #A32D24; border-color: #E9C4BF; }
    .pe-vide { color: #8894A8; font-size: 13.5px; padding: 20px; text-align: center; width: 100%; }
    .pe-dz { margin-top: 14px; border: 2px dashed #C4D1E9; border-radius: 12px; padding: 20px; text-align: center; color: #5C6A85; cursor: pointer; transition: .15s; }
    .pe-dz.drag { border-color: var(--navy, #10316F); background: #F3F7FF; }
    .pe-dz strong { color: var(--navy, #10316F); }
    .pe-attente { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    .pe-attente .pe-att { position: relative; width: 92px; border-radius: 8px; overflow: hidden; border: 1px solid #DBE2EE; }
    .pe-attente .pe-att img { width: 100%; height: 78px; object-fit: cover; display: block; }
    .pe-attente .pe-att button { position: absolute; top: 2px; right: 2px; background: rgba(163,45,36,.9); color: #fff; border: 0; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; }
    .pe-bar { display: flex; gap: 10px; align-items: center; margin-top: 16px; flex-wrap: wrap; }
    .pe-btn { border: 0; border-radius: 10px; padding: 10px 18px; font-weight: 700; font-size: 14px; cursor: pointer; }
    .pe-btn-primary { background: var(--navy, #10316F); color: #fff; }
    .pe-btn-primary[disabled] { opacity: .5; cursor: default; }
    .pe-btn-ghost { background: #EEF2F8; color: var(--navy-ink, #08193A); text-decoration: none; }
    .pe-msg { font-size: 13.5px; font-weight: 600; }
    .pe-msg.ok { color: #12694A; } .pe-msg.ko { color: #A32D24; }
    .pe-apercu { text-align: center; }
    .pe-apercu-img { display: inline-block; border-radius: 12px; overflow: hidden; }
    .pe-apercu-det { background: conic-gradient(#e9edf4 0 25%, #fff 0 50%, #e9edf4 0 75%, #fff 0) 0 0/24px 24px; border: 1px solid #E5EAF2; border-radius: 12px; padding: 8px; }
    .pe-apercu-det img, .pe-etq img { max-width: 100%; display: block; margin: 0 auto; }
    .pe-etq { margin-top: 14px; }
    .pe-hint { font-size: 12.5px; color: #8894A8; margin-top: 8px; }
    .pe-onglets { display: flex; gap: 6px; margin-bottom: 10px; }
    .pe-onglets button { border: 1px solid #DBE2EE; background: #fff; border-radius: 8px; padding: 6px 12px; font-size: 13px; cursor: pointer; color: #33415A; }
    .pe-onglets button.on { background: var(--navy, #10316F); color: #fff; border-color: var(--navy, #10316F); }
    </style>
</head>
<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="pe-wrap"
         data-piece-id="<?php echo (int) $piece['id']; ?>"
         data-photos='<?php echo htmlspecialchars(json_encode($photos, JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>'>

        <div class="pe-lead">
            <h1><?php echo fpl_e($piece['nom']); ?></h1>
            <span class="pe-ref"><?php echo fpl_e($ref); ?></span>
        </div>
        <div class="pe-sub">
            <?php echo fpl_e(trim(($piece['marque_nom'] ?? '') . (($piece['marque_nom'] && $piece['categorie_nom']) ? ' · ' : '') . ($piece['categorie_nom'] ?? ''))); ?>
            — <a href="photo-travail.php" style="color:var(--navy,#10316F)">← retour à l'espace photo</a>
        </div>

        <div class="pe-grid">
            <!-- GAUCHE : les photos -->
            <div class="pe-card">
                <h2>Les photos de la pièce</h2>
                <div class="pe-hint">Glissez pour réordonner. La 1<sup>re</sup> est la <strong>photo principale</strong> (celle de l'étiquette).</div>
                <div id="pe-photos" class="pe-photos"></div>

                <div id="pe-dz" class="pe-dz">
                    <strong>Déposez des photos ici</strong> ou cliquez pour choisir —
                    ou <strong>collez une image</strong> (Ctrl+V) depuis internet.
                    <input id="pe-file" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
                </div>
                <div id="pe-attente" class="pe-attente"></div>

                <div class="pe-bar">
                    <button id="pe-save" class="pe-btn pe-btn-primary" disabled>Enregistrer les photos</button>
                    <span id="pe-msg" class="pe-msg"></span>
                </div>
            </div>

            <!-- DROITE : l'aperçu du détourage -->
            <div class="pe-card pe-apercu">
                <h2>Comment le détourage rend</h2>
                <div class="pe-onglets">
                    <button type="button" class="on" data-vue="detour">Pièce détourée</button>
                    <button type="button" data-vue="etq">Sur l'étiquette</button>
                </div>
                <div id="pe-vue-detour">
                    <div class="pe-apercu-det pe-apercu-img">
                        <img id="pe-detour" alt="Aperçu du détourage" src="detourage-lot-apercu.php?id=<?php echo (int) $piece['id']; ?>&t=0"
                             onerror="this.style.display='none';this.insertAdjacentHTML('afterend','<div class=\'pe-hint\'>Aperçu indisponible — ajoutez d\'abord une photo.</div>');this.onerror=null;">
                    </div>
                    <div class="pe-hint">Fond à damier = transparent (détourage réussi). Si le fond de la photo est chargé, la pièce reste sur sa photo d'origine.</div>
                </div>
                <div id="pe-vue-etq" hidden>
                    <div class="pe-etq">
                        <img id="pe-etq" alt="Aperçu de l'étiquette" src="etiquette-piece-image.php?id=<?php echo (int) $piece['id']; ?>&cote=760&t=0">
                    </div>
                </div>
                <div class="pe-bar" style="justify-content:center">
                    <button id="pe-refresh" class="pe-btn pe-btn-ghost" type="button">Rafraîchir l'aperçu</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="<?php echo htmlspecialchars(fpl_asset_uri('js/admin-photo-editer.js'), ENT_QUOTES); ?><?php echo asset_version_query(); ?>"></script>
</body>
</html>
