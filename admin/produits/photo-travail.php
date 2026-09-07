<?php
/**
 * ESPACE INFOGRAPHISTE — l'accueil du rôle « photographe » (libellé : Infographiste).
 *
 * Son métier (précisé par la direction le 07/09) : il ne photographie pas, il
 * CHERCHE SUR INTERNET les bonnes images des pièces — et d'autres faces d'une
 * pièce déjà illustrée — pour faire avancer l'informaticien plus vite.
 * Trois files, une recherche, et pour chaque pièce un raccourci « Chercher des
 * images » qui ouvre la recherche d'images avec la référence OEM (ou la marque
 * et le nom). Il ne voit ni prix, ni stock, ni fournisseur.
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

$ref_aeree = function ($ident) {
    $r = strtoupper(trim((string) $ident));
    if (preg_match('/^FPL(\d{9})$/', $r, $m)) {
        return 'FPL ' . implode(' ', str_split($m[1], 3));
    }
    return $r;
};

/* Le nombre de FACES (images) d'une pièce, en SQL : la galerie JSON si elle
 * est valide, sinon 1 si une photo principale existe, sinon 0. */
$NB_FACES = "(CASE WHEN p.images IS NOT NULL AND p.images <> '' AND JSON_VALID(p.images)
                   THEN JSON_LENGTH(p.images)
                   ELSE (CASE WHEN p.image_principale IS NOT NULL AND p.image_principale <> '' THEN 1 ELSE 0 END) END)";
$CHAMPS = "p.id, p.identifiant_interne, p.nom, p.reference_oem, p.image_principale, p.images, m.nom AS marque_nom, $NB_FACES AS nb_faces";
$FROM = "FROM produits p LEFT JOIN marques m ON m.id = p.marque_id WHERE p.sync_deleted_at IS NULL";

$compte = ['sans' => 0, 'une' => 0, 'ok' => 0];
$sans = $une = $recentes = [];
try {
    $row = $db->query("SELECT SUM($NB_FACES = 0) s, SUM($NB_FACES = 1) u, SUM($NB_FACES >= 2) o FROM produits p WHERE p.sync_deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
    $compte = ['sans' => (int) ($row['s'] ?? 0), 'une' => (int) ($row['u'] ?? 0), 'ok' => (int) ($row['o'] ?? 0)];
    $sans = $db->query("SELECT $CHAMPS $FROM AND $NB_FACES = 0 ORDER BY p.id DESC LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);
    $une = $db->query("SELECT $CHAMPS $FROM AND $NB_FACES = 1 ORDER BY p.date_modification DESC, p.id DESC LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);
    $recentes = $db->query("SELECT $CHAMPS $FROM AND $NB_FACES >= 1 ORDER BY p.date_modification DESC, p.id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // la page reste utilisable (recherche) même si une requête échoue
}

$vignette = function ($row) {
    $imgs = json_decode((string) ($row['images'] ?? ''), true);
    $rel = (is_array($imgs) && !empty($imgs[0])) ? $imgs[0] : (string) ($row['image_principale'] ?? '');
    return $rel !== '' ? '../../upload/' . ltrim(str_replace('\\', '/', $rel), '/') : '';
};
/* La requête d'images : la référence OEM d'abord (la plus discriminante),
 * sinon la marque et le nom de la pièce. */
$requete_images = function ($row) {
    $oem = trim((string) ($row['reference_oem'] ?? ''));
    if ($oem !== '') {
        return $oem;
    }
    return trim(($row['marque_nom'] ?? '') . ' ' . ($row['nom'] ?? ''));
};
$lien_images = function ($q) {
    return 'https://www.google.com/search?tbm=isch&q=' . rawurlencode($q);
};

$carte = function ($row, $cta) use ($vignette, $requete_images, $lien_images, $ref_aeree) {
    $v = $vignette($row);
    $faces = (int) ($row['nb_faces'] ?? 0);
    $oem = trim((string) ($row['reference_oem'] ?? ''));
    ?>
    <div class="pt-card">
        <a class="pt-thumb<?php echo $v === '' ? ' vide' : ''; ?>" href="photo-editer.php?id=<?php echo (int) $row['id']; ?>">
            <?php if ($v !== ''): ?><img src="<?php echo fpl_e($v); ?>" alt="" loading="lazy"><?php else: ?><?php echo fpl_icone('image', 26); ?><span>Aucune image</span><?php endif; ?>
            <?php if ($faces > 0): ?><span class="pt-faces"><?php echo $faces; ?> face<?php echo $faces > 1 ? 's' : ''; ?></span><?php endif; ?>
        </a>
        <div class="pt-meta">
            <?php if (!empty($row['marque_nom'])): ?><div class="pt-marque"><?php echo fpl_e($row['marque_nom']); ?></div><?php endif; ?>
            <div class="pt-nom"><?php echo fpl_e($row['nom']); ?></div>
            <div class="pt-ref"><?php echo fpl_e($ref_aeree($row['identifiant_interne'])); ?></div>
            <?php if ($oem !== ''): ?><div class="pt-oem" title="Référence OEM">OEM <?php echo fpl_e($oem); ?></div><?php endif; ?>
        </div>
        <div class="pt-actions">
            <a class="pt-act pt-act-web" href="<?php echo fpl_e($lien_images($requete_images($row))); ?>" target="_blank" rel="noopener" title="Ouvre la recherche d'images dans un nouvel onglet"><?php echo fpl_icone('search', 13); ?> Chercher des images</a>
            <a class="pt-act pt-act-open" href="photo-editer.php?id=<?php echo (int) $row['id']; ?>"><?php echo fpl_e($cta); ?> →</a>
        </div>
    </div>
    <?php
};

$fpl_titre_page = 'Espace infographiste';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace infographiste — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <style>
    .pt-wrap { max-width: 1240px; margin: 0 auto; padding: 18px 16px 40px; }
    .pt-wrap h1 { font-size: 24px; color: var(--navy, #10316F); margin: 0 0 2px; }
    .pt-lead { color: #5C6A85; font-size: 14px; margin-bottom: 18px; max-width: 760px; line-height: 1.5; }
    .pt-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 18px; }
    .pt-kpi { background: #fff; border: 1px solid #E5EAF2; border-radius: 14px; padding: 14px 16px; text-align: left; cursor: pointer; font: inherit; }
    .pt-kpi:hover, .pt-kpi.on { border-color: var(--navy, #10316F); box-shadow: 0 0 0 3px rgba(16,49,111,.10); }
    .pt-kpi .n { font-size: 26px; font-weight: 800; color: var(--navy, #10316F); line-height: 1.1; }
    .pt-kpi .l { font-size: 13px; color: #33415A; font-weight: 600; margin-top: 2px; }
    .pt-kpi .h { font-size: 12px; color: #8894A8; margin-top: 2px; }
    .pt-search { position: relative; max-width: 640px; margin-bottom: 6px; }
    .pt-search input { width: 100%; border: 1.5px solid #DBE2EE; border-radius: 12px; padding: 13px 14px 13px 42px; font-size: 15px; background: #fff; }
    .pt-search input:focus { outline: none; border-color: var(--navy, #10316F); box-shadow: 0 0 0 3px rgba(16,49,111,.10); }
    .pt-search .ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #8894A8; }
    .pt-res { margin: 4px 0 26px; display: none; }
    .pt-section h2 { font-size: 16px; color: var(--navy-ink, #08193A); margin: 22px 0 12px; display: flex; align-items: center; gap: 8px; }
    .pt-pill { background: #ECF2FC; color: var(--navy, #10316F); font-size: 12.5px; font-weight: 700; border-radius: 999px; padding: 2px 10px; }
    .pt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(196px, 1fr)); gap: 14px; }
    .pt-card { background: #fff; border: 1px solid #E5EAF2; border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; transition: .12s; min-width: 0; }
    .pt-card:hover { border-color: var(--navy, #10316F); box-shadow: 0 4px 14px rgba(15,32,64,.10); }
    .pt-thumb { position: relative; height: 138px; background: #F4F6FA; display: flex; align-items: center; justify-content: center; text-decoration: none; }
    .pt-thumb img { max-width: 100%; max-height: 138px; object-fit: contain; }
    .pt-thumb.vide { color: #A9B4C8; flex-direction: column; gap: 6px; font-size: 12px; }
    .pt-faces { position: absolute; right: 8px; top: 8px; background: rgba(16,49,111,.88); color: #fff; font-size: 11px; font-weight: 700; border-radius: 6px; padding: 2px 7px; }
    .pt-meta { padding: 10px 12px 6px; flex: 1; min-width: 0; }
    .pt-marque { font-size: 11px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: #5C6A85; }
    .pt-nom { font-weight: 600; font-size: 13.5px; line-height: 1.25; color: #16203A; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .pt-ref, .pt-oem { font-family: Consolas, monospace; font-size: 11.5px; color: #5C6A85; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pt-oem { color: var(--navy, #10316F); font-weight: 700; }
    .pt-actions { display: flex; border-top: 1px solid #EEF1F6; }
    .pt-act { flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px; padding: 8px 6px; font-size: 12px; font-weight: 700; text-decoration: none; color: var(--navy, #10316F); min-width: 0; white-space: nowrap; }
    .pt-act + .pt-act { border-left: 1px solid #EEF1F6; }
    .pt-act:hover { background: #F3F7FF; }
    .pt-act-web { color: #12694A; }
    .pt-empty { color: #8894A8; font-size: 13.5px; padding: 10px 2px; }
    .pt-file[hidden] { display: none; }
    </style>
</head>
<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="pt-wrap">
        <h1>Espace infographiste</h1>
        <div class="pt-lead">Trouvez sur internet les bonnes images de chaque pièce, ajoutez d'autres faces à celles qui n'en ont qu'une, et vérifiez le rendu sur l'étiquette. « Chercher des images » ouvre la recherche avec la référence OEM ; copiez l'image trouvée, puis collez-la (Ctrl+V) dans l'éditeur de la pièce.</div>

        <div class="pt-kpis" role="tablist">
            <button type="button" class="pt-kpi on" data-file="sans" role="tab"><div class="n"><?php echo (int) $compte['sans']; ?></div><div class="l">Sans image</div><div class="h">à illustrer en priorité</div></button>
            <button type="button" class="pt-kpi" data-file="une" role="tab"><div class="n"><?php echo (int) $compte['une']; ?></div><div class="l">Une seule face</div><div class="h">à compléter par d'autres angles</div></button>
            <button type="button" class="pt-kpi" data-file="recentes" role="tab"><div class="n"><?php echo (int) $compte['ok']; ?></div><div class="l">Illustrées (2 faces et plus)</div><div class="h">les dernières modifiées</div></button>
        </div>

        <div class="pt-search">
            <span class="ico"><?php echo fpl_icone('search', 16); ?></span>
            <input id="pt-q" type="text" placeholder="Rechercher une pièce : nom, référence FPL, référence OEM…" autocomplete="off">
        </div>
        <div id="pt-res" class="pt-res pt-grid"></div>

        <div class="pt-section pt-file" data-file="sans">
            <h2>Sans image <span class="pt-pill"><?php echo (int) $compte['sans']; ?> pièce<?php echo $compte['sans'] > 1 ? 's' : ''; ?></span></h2>
            <?php if ($sans === []): ?>
                <div class="pt-empty">Toutes les pièces ont au moins une image.</div>
            <?php else: ?>
            <div class="pt-grid"><?php foreach ($sans as $row) { $carte($row, 'Illustrer'); } ?></div>
            <?php if ($compte['sans'] > count($sans)): ?><div class="pt-empty">… et <?php echo (int) ($compte['sans'] - count($sans)); ?> autres. Traitez celles-ci d'abord, la file se renouvelle.</div><?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="pt-section pt-file" data-file="une" hidden>
            <h2>Une seule face <span class="pt-pill"><?php echo (int) $compte['une']; ?> pièce<?php echo $compte['une'] > 1 ? 's' : ''; ?></span></h2>
            <?php if ($une === []): ?>
                <div class="pt-empty">Aucune pièce n'est limitée à une seule image.</div>
            <?php else: ?>
            <div class="pt-grid"><?php foreach ($une as $row) { $carte($row, 'Ajouter des faces'); } ?></div>
            <?php if ($compte['une'] > count($une)): ?><div class="pt-empty">… et <?php echo (int) ($compte['une'] - count($une)); ?> autres.</div><?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="pt-section pt-file" data-file="recentes" hidden>
            <h2>Récemment modifiées</h2>
            <?php if ($recentes === []): ?>
                <div class="pt-empty">Rien pour l'instant.</div>
            <?php else: ?>
            <div class="pt-grid"><?php foreach ($recentes as $row) { $carte($row, 'Ouvrir'); } ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
    (function () {
        // les trois files : un clic sur un compteur montre la sienne
        var kpis = document.querySelectorAll('.pt-kpi');
        var files = document.querySelectorAll('.pt-file');
        kpis.forEach(function (k) {
            k.addEventListener('click', function () {
                kpis.forEach(function (x) { x.classList.toggle('on', x === k); });
                files.forEach(function (f) { f.hidden = f.getAttribute('data-file') !== k.getAttribute('data-file'); });
            });
        });

        // la recherche : mêmes cartes, mêmes raccourcis
        var q = document.getElementById('pt-q');
        var res = document.getElementById('pt-res');
        var t = null;
        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
        function lienImages(p) {
            var query = (p.oem && String(p.oem).trim()) ? String(p.oem).trim() : String(p.name || '');
            return 'https://www.google.com/search?tbm=isch&q=' + encodeURIComponent(query);
        }
        function cherche() {
            var v = q.value.trim();
            if (v.length < 2) { res.style.display = 'none'; res.innerHTML = ''; return; }
            fetch('ajax_recherche_piece.php?q=' + encodeURIComponent(v), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var items = (d && d.products) ? d.products : [];
                    if (items.length === 0) { res.style.display = 'block'; res.innerHTML = '<div class="pt-empty">Aucune pièce ne correspond.</div>'; return; }
                    res.innerHTML = items.map(function (p) {
                        var img = p.image ? '<img src="' + esc(p.image) + '" alt="">' : '<span>Aucune image</span>';
                        return '<div class="pt-card">'
                            + '<a class="pt-thumb' + (p.image ? '' : ' vide') + '" href="photo-editer.php?id=' + encodeURIComponent(p.id) + '">' + img + '</a>'
                            + '<div class="pt-meta"><div class="pt-nom">' + esc(p.name) + '</div>'
                            + '<div class="pt-ref">' + esc(p.code) + '</div>'
                            + (p.oem ? '<div class="pt-oem">OEM ' + esc(p.oem) + '</div>' : '') + '</div>'
                            + '<div class="pt-actions"><a class="pt-act pt-act-web" target="_blank" rel="noopener" href="' + lienImages(p) + '">Chercher des images</a>'
                            + '<a class="pt-act pt-act-open" href="photo-editer.php?id=' + encodeURIComponent(p.id) + '">Ouvrir →</a></div></div>';
                    }).join('');
                    res.style.display = 'grid';
                })
                .catch(function () { res.style.display = 'none'; });
        }
        q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(cherche, 300); });
    })();
    </script>
</body>
</html>
