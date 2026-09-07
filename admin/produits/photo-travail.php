<?php
/**
 * ESPACE INFOGRAPHISTE — l'accueil du rôle « photographe » (libellé : Infographiste).
 *
 * Son métier (précisé par la direction le 07/09) : il ne photographie pas, il
 * CHERCHE SUR INTERNET les bonnes images des pièces — et d'autres faces d'une
 * pièce déjà illustrée — pour faire avancer l'informaticien plus vite.
 *
 * L'écran (redessiné le 07/09 sur retour « plus jolie ») : un bandeau outremer
 * qui dit où en est le catalogue (part illustrée, faces à ajouter, travail du
 * jour), trois files cliquables, une recherche, et pour chaque pièce une carte
 * avec marque, OEM, nombre de faces et le raccourci « Chercher des images » qui
 * ouvre la recherche d'images avec la référence OEM (sinon marque + nom).
 * Il ne voit ni prix, ni stock, ni fournisseur.
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

$compte = ['sans' => 0, 'une' => 0, 'ok' => 0, 'total' => 0, 'jour' => 0];
$sans = $une = $recentes = [];
try {
    $row = $db->query("SELECT COUNT(*) t, SUM($NB_FACES = 0) s, SUM($NB_FACES = 1) u, SUM($NB_FACES >= 2) o,
                              SUM($NB_FACES >= 1 AND DATE(p.date_modification) = CURDATE()) j
                         FROM produits p WHERE p.sync_deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
    $compte = [
        'sans' => (int) ($row['s'] ?? 0), 'une' => (int) ($row['u'] ?? 0), 'ok' => (int) ($row['o'] ?? 0),
        'total' => (int) ($row['t'] ?? 0), 'jour' => (int) ($row['j'] ?? 0),
    ];
    $sans = $db->query("SELECT $CHAMPS $FROM AND $NB_FACES = 0 ORDER BY p.id DESC LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);
    $une = $db->query("SELECT $CHAMPS $FROM AND $NB_FACES = 1 ORDER BY p.date_modification DESC, p.id DESC LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);
    $recentes = $db->query("SELECT $CHAMPS $FROM AND $NB_FACES >= 1 ORDER BY p.date_modification DESC, p.id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // la page reste utilisable (recherche) même si une requête échoue
}
$part_illustree = $compte['total'] > 0 ? (int) round(100 * ($compte['une'] + $compte['ok']) / $compte['total']) : 0;
$part_complete = $compte['total'] > 0 ? (int) round(100 * $compte['ok'] / $compte['total']) : 0;

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
    <article class="pt-card">
        <a class="pt-thumb<?php echo $v === '' ? ' vide' : ''; ?>" href="photo-editer.php?id=<?php echo (int) $row['id']; ?>" aria-label="Ouvrir la pièce">
            <?php if ($v !== ''): ?><img src="<?php echo fpl_e($v); ?>" alt="" loading="lazy"><?php else: ?><span class="pt-vide-ico"><?php echo fpl_icone('image', 22); ?></span><span>Aucune image</span><?php endif; ?>
            <span class="pt-faces<?php echo $faces === 0 ? ' zero' : ($faces === 1 ? ' une' : ''); ?>"><?php echo $faces === 0 ? 'à illustrer' : $faces . ' face' . ($faces > 1 ? 's' : ''); ?></span>
        </a>
        <div class="pt-meta">
            <div class="pt-marque"><?php echo !empty($row['marque_nom']) ? fpl_e($row['marque_nom']) : '<span class="pt-sans-marque">Marque non renseignée</span>'; ?></div>
            <div class="pt-nom"><?php echo fpl_e($row['nom']); ?></div>
            <div class="pt-refs">
                <span class="pt-ref"><?php echo fpl_e($ref_aeree($row['identifiant_interne'])); ?></span>
                <?php if ($oem !== ''): ?><span class="pt-oem" title="Référence OEM">OEM <?php echo fpl_e($oem); ?></span><?php endif; ?>
            </div>
        </div>
        <div class="pt-actions">
            <a class="pt-act pt-act-web" href="<?php echo fpl_e($lien_images($requete_images($row))); ?>" target="_blank" rel="noopener" title="Ouvre la recherche d'images dans un nouvel onglet"><?php echo fpl_icone('search', 13); ?> Chercher des images</a>
            <a class="pt-act pt-act-open" href="photo-editer.php?id=<?php echo (int) $row['id']; ?>"><?php echo fpl_e($cta); ?> <?php echo fpl_icone('chevron-right', 13); ?></a>
        </div>
    </article>
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
    .pt-wrap { max-width: 1240px; margin: 0 auto; padding: 18px 16px 48px; }

    /* ---- le bandeau : où en est le catalogue ---- */
    .pt-hero { position: relative; overflow: hidden; border-radius: 20px; padding: 26px 28px 22px; color: #fff;
               background: linear-gradient(118deg, #0B2455 0%, #10316F 55%, #1E4A9A 100%); box-shadow: 0 10px 30px rgba(16,49,111,.22); }
    .pt-hero::before { content: ''; position: absolute; right: -80px; top: -90px; width: 320px; height: 320px; border-radius: 50%; background: rgba(255,255,255,.06); }
    .pt-hero::after { content: ''; position: absolute; right: 120px; bottom: -140px; width: 260px; height: 260px; border-radius: 50%; background: rgba(255,255,255,.05); }
    .pt-hero > * { position: relative; z-index: 1; }
    .pt-hero-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; }
    .pt-hero h1 { font-size: 26px; margin: 0 0 4px; letter-spacing: .2px; }
    .pt-hero .pt-lead { color: rgba(255,255,255,.82); font-size: 14px; line-height: 1.5; max-width: 700px; margin: 0; }
    .pt-jour { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22); border-radius: 14px; padding: 10px 16px; text-align: center; min-width: 130px; }
    .pt-jour .n { font-size: 28px; font-weight: 800; line-height: 1; }
    .pt-jour .l { font-size: 12px; color: rgba(255,255,255,.8); margin-top: 3px; }
    .pt-progress { margin-top: 18px; }
    .pt-progress .pt-pl { display: flex; justify-content: space-between; font-size: 12.5px; color: rgba(255,255,255,.85); margin-bottom: 6px; }
    .pt-progress .pt-pb { height: 10px; border-radius: 999px; background: rgba(255,255,255,.18); overflow: hidden; display: flex; }
    .pt-progress .pt-pb i { display: block; height: 100%; }
    .pt-progress .pt-pb .ok { background: #7BE3B4; }
    .pt-progress .pt-pb .une { background: #FFD666; }
    .pt-legende { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 8px; font-size: 12px; color: rgba(255,255,255,.85); }
    .pt-legende b { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }

    /* ---- les trois files ---- */
    .pt-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: -26px 14px 0; position: relative; z-index: 2; }
    .pt-kpi { background: #fff; border: 1px solid #E5EAF2; border-radius: 16px; padding: 16px 18px; text-align: left; cursor: pointer; font: inherit; box-shadow: 0 6px 18px rgba(15,32,64,.08); display: flex; gap: 14px; align-items: center; transition: .15s; }
    .pt-kpi:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(15,32,64,.12); }
    .pt-kpi.on { border-color: var(--navy, #10316F); box-shadow: 0 0 0 3px rgba(16,49,111,.12), 0 10px 24px rgba(15,32,64,.10); }
    .pt-kpi .ico { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex: none; }
    .pt-kpi.k-sans .ico { background: #FBE9E7; color: #A32D24; }
    .pt-kpi.k-une .ico { background: #FFF4D6; color: #8A5A00; }
    .pt-kpi.k-ok .ico { background: #E4F6EC; color: #12694A; }
    .pt-kpi .n { font-size: 24px; font-weight: 800; color: var(--navy, #10316F); line-height: 1.1; }
    .pt-kpi .l { font-size: 13.5px; color: #16203A; font-weight: 700; margin-top: 1px; }
    .pt-kpi .h { font-size: 12px; color: #8894A8; margin-top: 1px; }

    /* ---- la recherche ---- */
    .pt-search { position: relative; max-width: 680px; margin: 22px 0 6px; }
    .pt-search input { width: 100%; border: 1.5px solid #DBE2EE; border-radius: 14px; padding: 14px 16px 14px 46px; font-size: 15px; background: #fff; box-shadow: 0 2px 8px rgba(15,32,64,.04); }
    .pt-search input:focus { outline: none; border-color: var(--navy, #10316F); box-shadow: 0 0 0 3px rgba(16,49,111,.12); }
    .pt-search .ico { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #8894A8; display: flex; }
    .pt-res { margin: 8px 0 26px; display: none; }

    /* ---- les sections et les cartes ---- */
    .pt-section { margin-top: 22px; }
    .pt-section h2 { font-size: 17px; color: var(--navy-ink, #08193A); margin: 0 0 4px; display: flex; align-items: center; gap: 10px; }
    .pt-section .pt-sub { color: #8894A8; font-size: 13px; margin-bottom: 14px; }
    .pt-pill { background: #ECF2FC; color: var(--navy, #10316F); font-size: 12.5px; font-weight: 700; border-radius: 999px; padding: 2px 10px; }
    .pt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px; }
    .pt-card { background: #fff; border: 1px solid #E5EAF2; border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: .15s; min-width: 0; }
    .pt-card:hover { border-color: #C9D6EE; box-shadow: 0 10px 24px rgba(15,32,64,.12); transform: translateY(-2px); }
    .pt-thumb { position: relative; height: 150px; background: linear-gradient(180deg, #F8FAFD 0%, #EEF2F8 100%); display: flex; align-items: center; justify-content: center; text-decoration: none; padding: 10px; }
    .pt-thumb img { max-width: 100%; max-height: 130px; object-fit: contain; filter: drop-shadow(0 6px 10px rgba(15,32,64,.12)); }
    .pt-thumb.vide { color: #A9B4C8; flex-direction: column; gap: 6px; font-size: 12px; background: repeating-linear-gradient(135deg, #F7F9FC 0 10px, #F1F4F9 10px 20px); }
    .pt-vide-ico { width: 44px; height: 44px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; color: #B7C2D6; border: 1px solid #E5EAF2; }
    .pt-faces { position: absolute; left: 10px; top: 10px; background: #E4F6EC; color: #12694A; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 9px; }
    .pt-faces.une { background: #FFF4D6; color: #8A5A00; }
    .pt-faces.zero { background: #FBE9E7; color: #A32D24; }
    .pt-meta { padding: 12px 14px 8px; flex: 1; min-width: 0; }
    .pt-marque { font-size: 11px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: var(--navy, #10316F); }
    .pt-sans-marque { color: #B7C2D6; font-weight: 600; text-transform: none; letter-spacing: 0; }
    .pt-nom { font-weight: 700; font-size: 14px; line-height: 1.3; color: #16203A; margin-top: 2px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em; }
    .pt-refs { display: flex; flex-wrap: wrap; gap: 4px 8px; margin-top: 6px; }
    .pt-ref, .pt-oem { font-family: Consolas, monospace; font-size: 11.5px; color: #5C6A85; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
    .pt-oem { color: #8A5A00; background: #FFF8E6; border-radius: 6px; padding: 1px 6px; font-weight: 700; }
    .pt-actions { display: flex; border-top: 1px solid #EEF1F6; }
    .pt-act { flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px; padding: 10px 6px; font-size: 12.5px; font-weight: 700; text-decoration: none; color: var(--navy, #10316F); min-width: 0; white-space: nowrap; transition: .12s; }
    .pt-act + .pt-act { border-left: 1px solid #EEF1F6; }
    .pt-act:hover { background: #F3F7FF; }
    .pt-act-web { color: #12694A; }
    .pt-act-web:hover { background: #E4F6EC; }
    .pt-empty { color: #8894A8; font-size: 13.5px; padding: 18px; text-align: center; background: #fff; border: 1px dashed #DBE2EE; border-radius: 14px; }
    .pt-more { color: #8894A8; font-size: 13px; padding: 10px 2px; }
    .pt-file[hidden] { display: none; }
    @media (max-width: 700px) {
        .pt-hero { padding: 20px 18px 18px; border-radius: 16px; }
        .pt-kpis { margin: 12px 0 0; }
        .pt-kpi { padding: 12px 14px; }
    }
    </style>
</head>
<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="pt-wrap">
        <section class="pt-hero">
            <div class="pt-hero-top">
                <div>
                    <h1>Espace infographiste</h1>
                    <p class="pt-lead">Trouvez sur internet les bonnes images de chaque pièce, ajoutez d'autres faces à celles qui n'en ont qu'une, et vérifiez le rendu sur l'étiquette. « Chercher des images » ouvre la recherche avec la référence OEM ; copiez l'image trouvée, puis collez-la (Ctrl+V) dans la fiche de la pièce.</p>
                </div>
                <div class="pt-jour">
                    <div class="n"><?php echo (int) $compte['jour']; ?></div>
                    <div class="l">pièce<?php echo $compte['jour'] > 1 ? 's' : ''; ?> illustrée<?php echo $compte['jour'] > 1 ? 's' : ''; ?> aujourd'hui</div>
                </div>
            </div>
            <div class="pt-progress">
                <div class="pt-pl"><span>Catalogue illustré : <strong><?php echo $part_illustree; ?> %</strong> des <?php echo number_format($compte['total'], 0, ',', ' '); ?> pièces ont au moins une image</span><span><?php echo $part_complete; ?> % avec 2 faces et plus</span></div>
                <div class="pt-pb"><i class="ok" style="width:<?php echo $part_complete; ?>%"></i><i class="une" style="width:<?php echo max(0, $part_illustree - $part_complete); ?>%"></i></div>
                <div class="pt-legende"><span><b style="background:#7BE3B4"></b>2 faces et plus</span><span><b style="background:#FFD666"></b>une seule face</span><span><b style="background:rgba(255,255,255,.35)"></b>sans image</span></div>
            </div>
        </section>

        <div class="pt-kpis" role="tablist">
            <button type="button" class="pt-kpi k-sans on" data-file="sans" role="tab"><span class="ico"><?php echo fpl_icone('image', 20); ?></span><span><div class="n"><?php echo number_format($compte['sans'], 0, ',', ' '); ?></div><div class="l">Sans image</div><div class="h">à illustrer en priorité</div></span></button>
            <button type="button" class="pt-kpi k-une" data-file="une" role="tab"><span class="ico"><?php echo fpl_icone('layers', 20); ?></span><span><div class="n"><?php echo number_format($compte['une'], 0, ',', ' '); ?></div><div class="l">Une seule face</div><div class="h">à compléter par d'autres angles</div></span></button>
            <button type="button" class="pt-kpi k-ok" data-file="recentes" role="tab"><span class="ico"><?php echo fpl_icone('check', 20); ?></span><span><div class="n"><?php echo number_format($compte['ok'], 0, ',', ' '); ?></div><div class="l">Illustrées</div><div class="h">2 faces et plus — les dernières modifiées</div></span></button>
        </div>

        <div class="pt-search">
            <span class="ico"><?php echo fpl_icone('search', 17); ?></span>
            <input id="pt-q" type="text" placeholder="Rechercher une pièce : nom, référence FPL, référence OEM…" autocomplete="off">
        </div>
        <div id="pt-res" class="pt-res pt-grid"></div>

        <section class="pt-section pt-file" data-file="sans">
            <h2>Sans image <span class="pt-pill"><?php echo number_format($compte['sans'], 0, ',', ' '); ?> pièce<?php echo $compte['sans'] > 1 ? 's' : ''; ?></span></h2>
            <div class="pt-sub">Les plus récentes d'abord. Traitez celles-ci, la file se renouvelle.</div>
            <?php if ($sans === []): ?>
                <div class="pt-empty">Toutes les pièces ont au moins une image.</div>
            <?php else: ?>
            <div class="pt-grid"><?php foreach ($sans as $row) { $carte($row, 'Illustrer'); } ?></div>
            <?php if ($compte['sans'] > count($sans)): ?><div class="pt-more">… et <?php echo number_format($compte['sans'] - count($sans), 0, ',', ' '); ?> autres.</div><?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="pt-section pt-file" data-file="une" hidden>
            <h2>Une seule face <span class="pt-pill"><?php echo number_format($compte['une'], 0, ',', ' '); ?> pièce<?php echo $compte['une'] > 1 ? 's' : ''; ?></span></h2>
            <div class="pt-sub">Une image existe : ajoutez l'arrière, le côté, un détail.</div>
            <?php if ($une === []): ?>
                <div class="pt-empty">Aucune pièce n'est limitée à une seule image.</div>
            <?php else: ?>
            <div class="pt-grid"><?php foreach ($une as $row) { $carte($row, 'Ajouter des faces'); } ?></div>
            <?php if ($compte['une'] > count($une)): ?><div class="pt-more">… et <?php echo number_format($compte['une'] - count($une), 0, ',', ' '); ?> autres.</div><?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="pt-section pt-file" data-file="recentes" hidden>
            <h2>Récemment modifiées</h2>
            <div class="pt-sub">Pour vérifier le rendu de ce qui vient d'être fait.</div>
            <?php if ($recentes === []): ?>
                <div class="pt-empty">Rien pour l'instant.</div>
            <?php else: ?>
            <div class="pt-grid"><?php foreach ($recentes as $row) { $carte($row, 'Ouvrir'); } ?></div>
            <?php endif; ?>
        </section>
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
                        var img = p.image ? '<img src="' + esc(p.image) + '" alt="">' : '<span class="pt-vide-ico"></span><span>Aucune image</span>';
                        return '<article class="pt-card">'
                            + '<a class="pt-thumb' + (p.image ? '' : ' vide') + '" href="photo-editer.php?id=' + encodeURIComponent(p.id) + '">' + img + '</a>'
                            + '<div class="pt-meta"><div class="pt-nom">' + esc(p.name) + '</div>'
                            + '<div class="pt-refs"><span class="pt-ref">' + esc(p.code) + '</span>'
                            + (p.oem ? '<span class="pt-oem">OEM ' + esc(p.oem) + '</span>' : '') + '</div></div>'
                            + '<div class="pt-actions"><a class="pt-act pt-act-web" target="_blank" rel="noopener" href="' + lienImages(p) + '">Chercher des images</a>'
                            + '<a class="pt-act pt-act-open" href="photo-editer.php?id=' + encodeURIComponent(p.id) + '">Ouvrir</a></div></article>';
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
