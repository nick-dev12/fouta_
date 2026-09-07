<?php
/**
 * L'ÉDITEUR D'IMAGES D'UNE PIÈCE — l'espace de l'infographiste (rôle « photographe »).
 *
 * Le SEUL travail ici : les images (chercher sur internet, coller, téléverser,
 * réordonner, retirer, choisir la principale) et VÉRIFIER que le détourage
 * rend bien sur l'étiquette. Aucun autre champ (ni prix, ni stock, ni
 * fournisseur). Enregistre par ajax_photo_enregistrer.php.
 *
 * 07/09 — la direction précise le métier : il ne photographie pas, il cherche
 * les bonnes images (et d'autres faces) sur internet pour l'informaticien.
 * D'où : la fiche d'identité de la pièce (nom, marque, catégorie, OEM à copier,
 * description), un bloc « Trouver des images sur internet » avec les recherches
 * toutes prêtes, et « Pièce suivante à illustrer » pour enchaîner sans revenir
 * à la liste.
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
            "SELECT p.id, p.identifiant_interne, p.nom, p.nom_wolof, p.reference_oem, p.description,
                    p.images, p.image_principale, p.image_etiquette_fpl,
                    c.nom AS categorie_nom, sc.nom AS sous_categorie_nom, m.nom AS marque_nom
               FROM produits p
          LEFT JOIN categories c ON c.id = p.categorie_id
          LEFT JOIN sous_categories sc ON sc.id = p.sous_categorie_id
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
$oem = trim((string) ($piece['reference_oem'] ?? ''));
$marque = trim((string) ($piece['marque_nom'] ?? ''));
$famille = trim((string) ($piece['categorie_nom'] ?? ''));
if (!empty($piece['sous_categorie_nom'])) {
    $famille .= ($famille !== '' ? ' › ' : '') . trim((string) $piece['sous_categorie_nom']);
}
$description = trim((string) ($piece['description'] ?? ''));
$nb_faces = count($photos);
/* TOUTES LES IMAGES (07/09) : la galerie ci-dessus, PLUS l'image dédiée à
 * l'étiquette héritée de l'ancien modèle (colonne image_etiquette_fpl) quand
 * elle existe et n'est pas déjà dans la galerie — il doit la voir, même si
 * son éditeur ne la modifie pas (l'étiquette prend la principale d'abord). */
$image_etiquette = trim(str_replace('\\', '/', (string) ($piece['image_etiquette_fpl'] ?? '')));
if ($image_etiquette !== '' && in_array($image_etiquette, array_map(function ($r) { return str_replace('\\', '/', (string) $r); }, $photos), true)) {
    $image_etiquette = '';
}
if ($image_etiquette !== '' && !is_file(__DIR__ . '/../../upload/' . ltrim($image_etiquette, '/'))) {
    $image_etiquette = '';
}

/* LES RECHERCHES TOUTES PRÊTES : la référence OEM d'abord (la plus sûre), puis
 * la marque et le nom, puis le nom seul. Chacune s'ouvre dans un nouvel onglet ;
 * l'image trouvée se copie puis se colle ici (Ctrl+V). */
$recherches = [];
$img = function ($q) { return 'https://www.google.com/search?tbm=isch&q=' . rawurlencode($q); };
if ($oem !== '') {
    $recherches[] = ['Google Images — référence OEM', $oem, $img($oem)];
    if ($marque !== '') {
        $recherches[] = ['Google Images — OEM + marque', $marque . ' ' . $oem, $img($marque . ' ' . $oem)];
    }
}
$nom_marque = trim($marque . ' ' . (string) $piece['nom']);
$recherches[] = ['Google Images — marque + nom', $nom_marque, $img($nom_marque . ' camion')];
if ($oem !== '') {
    $recherches[] = ['Bing Images — référence OEM', $oem, 'https://www.bing.com/images/search?q=' . rawurlencode($oem)];
}

/* LA PIÈCE SUIVANTE À ILLUSTRER : la prochaine sans image dans l'ordre de la
 * file (id décroissant), après celle-ci ; à défaut la première de la file. */
$suivante = 0;
try {
    $SANS = "(p.image_principale IS NULL OR p.image_principale = '') AND (p.images IS NULL OR p.images = '' OR p.images = '[]')";
    $q = $db->prepare("SELECT p.id FROM produits p WHERE p.sync_deleted_at IS NULL AND $SANS AND p.id < :id ORDER BY p.id DESC LIMIT 1");
    $q->execute([':id' => $id]);
    $suivante = (int) $q->fetchColumn();
    if ($suivante <= 0) {
        $q = $db->prepare("SELECT p.id FROM produits p WHERE p.sync_deleted_at IS NULL AND $SANS AND p.id <> :id ORDER BY p.id DESC LIMIT 1");
        $q->execute([':id' => $id]);
        $suivante = (int) $q->fetchColumn();
    }
} catch (PDOException $e) {
    $suivante = 0;
}

$fpl_titre_page = 'Images de la pièce';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES); ?>">
    <title>Images — <?php echo fpl_e($piece['nom']); ?></title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <style>
    .pe-wrap { max-width: 1240px; margin: 0 auto; padding: 18px 16px 40px; }
    .pe-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
    .pe-top a.pe-back { color: var(--navy, #10316F); text-decoration: none; font-weight: 600; font-size: 13.5px; }
    .pe-fiche { background: #fff; border: 1px solid #E5EAF2; border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 10px rgba(15,32,64,.05); margin-bottom: 18px; display: grid; grid-template-columns: 1fr auto; gap: 14px 24px; align-items: start; }
    @media (max-width: 760px) { .pe-fiche { grid-template-columns: 1fr; } }
    .pe-fiche h1 { font-size: 22px; color: var(--navy, #10316F); margin: 0 0 2px; line-height: 1.2; }
    .pe-fiche .pe-wolof { font-size: 13.5px; color: #5C6A85; font-style: italic; }
    .pe-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .pe-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; padding: 4px 10px; font-size: 12.5px; background: #F3F6FB; color: #33415A; }
    .pe-chip b { color: #16203A; }
    .pe-chip.oem { background: #ECF2FC; color: var(--navy, #10316F); font-family: Consolas, monospace; font-weight: 700; letter-spacing: .3px; }
    .pe-chip.oem button { border: 0; background: var(--navy, #10316F); color: #fff; border-radius: 6px; font-size: 11px; padding: 2px 8px; cursor: pointer; font-family: inherit; }
    .pe-ref { font-family: Consolas, monospace; background: #ECF2FC; color: var(--navy, #10316F); border-radius: 8px; padding: 3px 10px; font-weight: 700; letter-spacing: .5px; }
    .pe-desc { margin-top: 10px; font-size: 13.5px; color: #33415A; white-space: pre-line; max-height: 4.6em; overflow: hidden; }
    .pe-heritee { display: flex; align-items: center; gap: 12px; margin-top: 12px; background: #FFF8E6; border: 1px solid #F1E1B3; border-radius: 10px; padding: 8px 12px; font-size: 12.5px; color: #5C4A1A; }
    .pe-heritee img { width: 64px; height: 64px; object-fit: contain; background: #fff; border-radius: 8px; border: 1px solid #F1E1B3; }
    .pe-faces { text-align: right; }
    .pe-faces .n { font-size: 34px; font-weight: 800; color: var(--navy, #10316F); line-height: 1; }
    .pe-faces .l { font-size: 12.5px; color: #5C6A85; }
    .pe-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 20px; align-items: start; }
    @media (max-width: 900px) { .pe-grid { grid-template-columns: 1fr; } }
    .pe-card { background: #fff; border: 1px solid #E5EAF2; border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 10px rgba(15,32,64,.05); }
    .pe-card + .pe-card { margin-top: 18px; }
    .pe-card h2 { font-size: 15px; letter-spacing: .3px; color: var(--navy-ink, #08193A); margin: 0 0 12px; }
    .pe-etapes { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
    @media (max-width: 560px) { .pe-etapes { grid-template-columns: 1fr; } }
    .pe-etape { background: #F7F9FC; border-radius: 10px; padding: 10px 12px; font-size: 12.5px; color: #33415A; line-height: 1.35; }
    .pe-etape b { display: block; color: var(--navy, #10316F); font-size: 13px; margin-bottom: 2px; }
    .pe-liens { display: flex; flex-direction: column; gap: 8px; }
    .pe-lien { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1.5px solid #DBE2EE; border-radius: 10px; padding: 9px 12px; text-decoration: none; color: #16203A; transition: .12s; }
    .pe-lien:hover { border-color: var(--navy, #10316F); background: #F3F7FF; }
    .pe-lien .t { font-weight: 700; font-size: 13.5px; }
    .pe-lien .q { font-family: Consolas, monospace; font-size: 12px; color: #5C6A85; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 55%; }
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
    .pe-btn { border: 0; border-radius: 10px; padding: 10px 18px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
    .pe-btn-primary { background: var(--navy, #10316F); color: #fff; }
    .pe-btn-primary[disabled] { opacity: .5; cursor: default; }
    .pe-btn-ghost { background: #EEF2F8; color: var(--navy-ink, #08193A); }
    .pe-btn-next { margin-left: auto; background: #E8F5EE; color: #12694A; }
    .pe-msg { font-size: 13.5px; font-weight: 600; }
    .pe-msg.ok { color: #12694A; } .pe-msg.ko { color: #A32D24; }
    .pe-apercu { text-align: center; }
    .pe-apercu-img { display: inline-block; border-radius: 12px; overflow: hidden; }
    .pe-apercu-det { background: conic-gradient(#e9edf4 0 25%, #fff 0 50%, #e9edf4 0 75%, #fff 0) 0 0/24px 24px; border: 1px solid #E5EAF2; border-radius: 12px; padding: 8px; }
    .pe-apercu-det img, .pe-etq img { max-width: 100%; display: block; margin: 0 auto; }
    .pe-etq { margin-top: 14px; }
    .pe-hint { font-size: 12.5px; color: #8894A8; margin-top: 8px; }
    .pe-onglets { display: flex; gap: 6px; margin-bottom: 10px; justify-content: center; }
    .pe-onglets button { border: 1px solid #DBE2EE; background: #fff; border-radius: 8px; padding: 6px 12px; font-size: 13px; cursor: pointer; color: #33415A; }
    .pe-onglets button.on { background: var(--navy, #10316F); color: #fff; border-color: var(--navy, #10316F); }
    </style>
</head>
<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="pe-wrap"
         data-piece-id="<?php echo (int) $piece['id']; ?>"
         data-photos='<?php echo htmlspecialchars(json_encode($photos, JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>'>

        <div class="pe-top">
            <a class="pe-back" href="photo-travail.php">← Espace infographiste</a>
            <?php if ($suivante > 0): ?>
            <a class="pe-btn pe-btn-next" href="photo-editer.php?id=<?php echo (int) $suivante; ?>" title="Passer à la prochaine pièce sans image">Pièce suivante à illustrer →</a>
            <?php endif; ?>
        </div>

        <!-- LA FICHE D'IDENTITÉ : tout ce qu'il faut pour chercher la bonne image, rien de plus -->
        <div class="pe-fiche">
            <div>
                <h1><?php echo fpl_e($piece['nom']); ?></h1>
                <?php if (!empty($piece['nom_wolof']) && trim((string) $piece['nom_wolof']) !== trim((string) $piece['nom'])): ?>
                    <div class="pe-wolof"><?php echo fpl_e($piece['nom_wolof']); ?></div>
                <?php endif; ?>
                <div class="pe-chips">
                    <span class="pe-ref"><?php echo fpl_e($ref); ?></span>
                    <?php if ($marque !== ''): ?><span class="pe-chip">Marque <b><?php echo fpl_e($marque); ?></b></span><?php endif; ?>
                    <?php if ($famille !== ''): ?><span class="pe-chip">Famille <b><?php echo fpl_e($famille); ?></b></span><?php endif; ?>
                    <?php if ($oem !== ''): ?>
                        <span class="pe-chip oem">OEM <?php echo fpl_e($oem); ?> <button type="button" class="pe-copier" data-copie="<?php echo fpl_e($oem); ?>" title="Copier la référence OEM">Copier</button></span>
                    <?php else: ?>
                        <span class="pe-chip">Pas de référence OEM — cherchez par marque et nom</span>
                    <?php endif; ?>
                </div>
                <?php if ($description !== ''): ?><div class="pe-desc"><?php echo fpl_e($description); ?></div><?php endif; ?>
                <?php if ($image_etiquette !== ''): ?>
                <div class="pe-heritee">
                    <img src="<?php echo fpl_e($upload_base . ltrim($image_etiquette, '/')); ?>" alt="">
                    <div><b>Image dédiée à l'étiquette</b> (héritée de l'ancien modèle). L'étiquette prend d'abord l'image principale ci-dessous ; celle-ci sert de repli.</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="pe-faces">
                <div class="n" id="pe-nb-faces"><?php echo (int) $nb_faces; ?></div>
                <div class="l">face<?php echo $nb_faces > 1 ? 's' : ''; ?> enregistrée<?php echo $nb_faces > 1 ? 's' : ''; ?></div>
            </div>
        </div>

        <div class="pe-grid">
            <div>
                <!-- 1. TROUVER DES IMAGES SUR INTERNET -->
                <div class="pe-card">
                    <h2>1. Trouver des images sur internet</h2>
                    <div class="pe-etapes">
                        <div class="pe-etape"><b>Ouvrir une recherche</b>Elle part de la référence OEM, la plus sûre.</div>
                        <div class="pe-etape"><b>Copier l'image</b>Clic droit sur l'image › « Copier l'image ». Préférez une face nette, sur fond uni.</div>
                        <div class="pe-etape"><b>Revenir ici et coller</b>Ctrl+V n'importe où sur la page : l'image rejoint la liste.</div>
                    </div>
                    <div class="pe-liens">
                        <?php foreach ($recherches as $r): ?>
                        <a class="pe-lien" href="<?php echo fpl_e($r[2]); ?>" target="_blank" rel="noopener">
                            <span class="t"><?php echo fpl_e($r[0]); ?> ↗</span>
                            <span class="q"><?php echo fpl_e($r[1]); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 2. LES IMAGES DE LA PIÈCE -->
                <div class="pe-card">
                    <h2>2. Les images de la pièce</h2>
                    <div class="pe-hint">Glissez pour réordonner. La 1<sup>re</sup> est l'<strong>image principale</strong> (celle de l'étiquette et de la vitrine). Ajoutez d'autres faces : avant, arrière, côté, détail.</div>
                    <div id="pe-photos" class="pe-photos"></div>

                    <div id="pe-dz" class="pe-dz">
                        <strong>Collez une image</strong> (Ctrl+V) trouvée sur internet —
                        ou déposez des fichiers ici, ou cliquez pour choisir.
                        <input id="pe-file" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
                    </div>
                    <div id="pe-attente" class="pe-attente"></div>

                    <div class="pe-bar">
                        <button id="pe-save" class="pe-btn pe-btn-primary" disabled>Enregistrer les images</button>
                        <span id="pe-msg" class="pe-msg"></span>
                    </div>
                </div>
            </div>

            <!-- 3. LE RENDU -->
            <div class="pe-card pe-apercu">
                <h2>3. Comment ça rend</h2>
                <div class="pe-onglets">
                    <button type="button" class="on" data-vue="detour">Pièce détourée</button>
                    <button type="button" data-vue="etq">Sur l'étiquette</button>
                </div>
                <div id="pe-vue-detour">
                    <div class="pe-apercu-det pe-apercu-img">
                        <img id="pe-detour" alt="Aperçu du détourage" src="detourage-lot-apercu.php?id=<?php echo (int) $piece['id']; ?>&t=0"
                             onerror="this.style.display='none';this.insertAdjacentHTML('afterend','<div class=\'pe-hint\'>Aperçu indisponible — ajoutez d\'abord une image.</div>');this.onerror=null;">
                    </div>
                    <div class="pe-hint">Fond à damier = transparent (détourage réussi). Si le fond de l'image est chargé, la pièce reste sur son image d'origine : préférez une image sur fond uni.</div>
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
    <script>
    // Copier la référence OEM d'un clic (pour la coller dans une autre recherche).
    document.querySelectorAll('.pe-copier').forEach(function (b) {
        b.addEventListener('click', function () {
            var txt = b.getAttribute('data-copie') || '';
            var ok = function () { b.textContent = 'Copié'; setTimeout(function () { b.textContent = 'Copier'; }, 1400); };
            if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(ok, ok); }
            else { var i = document.createElement('input'); i.value = txt; document.body.appendChild(i); i.select(); try { document.execCommand('copy'); } catch (e) {} i.remove(); ok(); }
        });
    });
    </script>
</body>
</html>
