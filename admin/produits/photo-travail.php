<?php
/**
 * ESPACE INFOGRAPHISTE — l'accueil du rôle « photographe » (libellé : Infographiste).
 *
 * Son métier (précisé par la direction le 07/09) : il ne photographie pas, il
 * CHERCHE SUR INTERNET les bonnes images des pièces — et d'autres faces d'une
 * pièce déjà illustrée — pour faire avancer l'informaticien plus vite.
 *
 * L'écran (07/09, sur retours de la direction : pas de bandeau ni de texte
 * d'explication ; et « il doit voir TOUTES les pièces, pas seulement celles
 * sans image, car on peut vouloir changer l'image d'une étiquette ou ajouter
 * d'autres faces ») : QUATRE files paginées — Sans image, Une seule face,
 * Illustrées, Toutes les pièces — filtrables par marque et par famille, une
 * recherche, et pour chaque pièce une carte avec marque, OEM, nombre de faces
 * et le raccourci « Chercher des images » (Google Images avec la référence
 * OEM, sinon marque + nom). Il ne voit ni prix, ni stock, ni fournisseur.
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

/* ---- les quatre files ---- */
$FILES = [
    'sans'   => ['titre' => 'Sans image',        'sous' => 'à illustrer en priorité',              'cta' => 'Illustrer',         'ou' => "$NB_FACES = 0",  'ordre' => 'p.id DESC',                           'cls' => 'k-sans',   'ico' => 'image'],
    'une'    => ['titre' => 'Une seule face',    'sous' => "à compléter par d'autres angles",       'cta' => 'Ajouter des faces', 'ou' => "$NB_FACES = 1",  'ordre' => 'p.date_modification DESC, p.id DESC', 'cls' => 'k-une',    'ico' => 'layers'],
    'ok'     => ['titre' => 'Illustrées',        'sous' => '2 faces et plus',                       'cta' => 'Ouvrir',            'ou' => "$NB_FACES >= 2", 'ordre' => 'p.date_modification DESC, p.id DESC', 'cls' => 'k-ok',     'ico' => 'check'],
    'toutes' => ['titre' => 'Toutes les pièces', 'sous' => 'changer une image, ajouter des faces',  'cta' => 'Ouvrir',            'ou' => '1 = 1',          'ordre' => 'p.id DESC',                           'cls' => 'k-toutes', 'ico' => 'grid'],
];
$file = isset($_GET['file']) && isset($FILES[$_GET['file']]) ? (string) $_GET['file'] : 'sans';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$marque_id = isset($_GET['marque']) ? max(0, (int) $_GET['marque']) : 0;
$cat_id = isset($_GET['cat']) ? max(0, (int) $_GET['cat']) : 0;
$PAR_PAGE = 24;

/* ---- les filtres (marque, famille) : appliqués à chaque file ---- */
$filtre_sql = '';
$filtre_params = [];
if ($marque_id > 0) {
    $filtre_sql .= ' AND p.marque_id = :marque';
    $filtre_params[':marque'] = $marque_id;
}
if ($cat_id > 0) {
    $filtre_sql .= ' AND p.categorie_id = :cat';
    $filtre_params[':cat'] = $cat_id;
}

$compte = ['sans' => 0, 'une' => 0, 'ok' => 0, 'toutes' => 0];
$lignes = [];
$marques = [];
$categories = [];
try {
    $st = $db->prepare("SELECT COUNT(*) t, SUM($NB_FACES = 0) s, SUM($NB_FACES = 1) u, SUM($NB_FACES >= 2) o
                          FROM produits p WHERE p.sync_deleted_at IS NULL $filtre_sql");
    $st->execute($filtre_params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $compte = ['sans' => (int) ($row['s'] ?? 0), 'une' => (int) ($row['u'] ?? 0), 'ok' => (int) ($row['o'] ?? 0), 'toutes' => (int) ($row['t'] ?? 0)];

    $pages = max(1, (int) ceil($compte[$file] / $PAR_PAGE));
    $page = min($page, $pages);
    $st = $db->prepare("SELECT p.id, p.identifiant_interne, p.nom, p.reference_oem, p.image_principale, p.images,
                               m.nom AS marque_nom, $NB_FACES AS nb_faces
                          FROM produits p LEFT JOIN marques m ON m.id = p.marque_id
                         WHERE p.sync_deleted_at IS NULL AND {$FILES[$file]['ou']} $filtre_sql
                         ORDER BY {$FILES[$file]['ordre']}
                         LIMIT " . (int) $PAR_PAGE . ' OFFSET ' . (int) (($page - 1) * $PAR_PAGE));
    $st->execute($filtre_params);
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);

    $marques = $db->query("SELECT id, nom FROM marques WHERE nom IS NOT NULL AND nom <> '' ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    $categories = $db->query("SELECT id, nom FROM categories WHERE sync_deleted_at IS NULL ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // la page reste utilisable (recherche) même si une requête échoue
}
$total_file = $compte[$file];
$pages = max(1, (int) ceil($total_file / $PAR_PAGE));

/* l'URL d'une file / d'une page en gardant les filtres */
$url = function ($f, $pg = 1) use ($marque_id, $cat_id) {
    $q = ['file' => $f];
    if ($pg > 1) {
        $q['page'] = $pg;
    }
    if ($marque_id > 0) {
        $q['marque'] = $marque_id;
    }
    if ($cat_id > 0) {
        $q['cat'] = $cat_id;
    }
    return 'photo-travail.php?' . http_build_query($q);
};

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

    /* ---- les quatre files ---- */
    .pt-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin: 4px 0 0; }
    .pt-kpi { background: #fff; border: 1px solid #E5EAF2; border-radius: 16px; padding: 14px 16px; text-align: left; text-decoration: none; color: inherit; box-shadow: 0 6px 18px rgba(15,32,64,.08); display: flex; gap: 14px; align-items: center; transition: .15s; }
    .pt-kpi:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(15,32,64,.12); }
    .pt-kpi.on { border-color: var(--navy, #10316F); box-shadow: 0 0 0 3px rgba(16,49,111,.12), 0 10px 24px rgba(15,32,64,.10); }
    .pt-kpi .ico { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex: none; }
    .pt-kpi.k-sans .ico { background: #FBE9E7; color: #A32D24; }
    .pt-kpi.k-une .ico { background: #FFF4D6; color: #8A5A00; }
    .pt-kpi.k-ok .ico { background: #E4F6EC; color: #12694A; }
    .pt-kpi.k-toutes .ico { background: #ECF2FC; color: var(--navy, #10316F); }
    .pt-kpi .n { font-size: 24px; font-weight: 800; color: var(--navy, #10316F); line-height: 1.1; }
    .pt-kpi .l { font-size: 13.5px; color: #16203A; font-weight: 700; margin-top: 1px; }
    .pt-kpi .h { font-size: 12px; color: #8894A8; margin-top: 1px; }

    /* ---- la recherche et les filtres ---- */
    .pt-barre { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 18px 0 6px; }
    .pt-search { position: relative; flex: 1 1 320px; max-width: 680px; }
    .pt-search input { width: 100%; border: 1.5px solid #DBE2EE; border-radius: 14px; padding: 13px 16px 13px 46px; font-size: 15px; background: #fff; box-shadow: 0 2px 8px rgba(15,32,64,.04); }
    .pt-search input:focus { outline: none; border-color: var(--navy, #10316F); box-shadow: 0 0 0 3px rgba(16,49,111,.12); }
    .pt-search .ico { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #8894A8; display: flex; }
    .pt-filtres { display: flex; gap: 8px; flex-wrap: wrap; }
    .pt-filtres select { border: 1.5px solid #DBE2EE; border-radius: 12px; padding: 11px 12px; font-size: 14px; background: #fff; color: #16203A; max-width: 240px; }
    .pt-filtres select:focus { outline: none; border-color: var(--navy, #10316F); }
    .pt-filtres .pt-raz { align-self: center; font-size: 13px; color: var(--navy, #10316F); text-decoration: none; font-weight: 700; padding: 0 6px; }
    .pt-res { margin: 8px 0 26px; display: none; }

    /* ---- la section et les cartes ---- */
    .pt-section { margin-top: 22px; }
    .pt-section h2 { font-size: 17px; color: var(--navy-ink, #08193A); margin: 0 0 4px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
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

    /* ---- la pagination ---- */
    .pt-pages { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 22px; flex-wrap: wrap; }
    .pt-pages a, .pt-pages span.dis { border: 1.5px solid #DBE2EE; border-radius: 10px; padding: 8px 14px; font-size: 13.5px; font-weight: 700; text-decoration: none; color: var(--navy, #10316F); background: #fff; }
    .pt-pages a:hover { border-color: var(--navy, #10316F); background: #F3F7FF; }
    .pt-pages span.dis { color: #B7C2D6; }
    .pt-pages .pt-ou { font-size: 13px; color: #5C6A85; }
    </style>
</head>
<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="pt-wrap">
        <div class="pt-kpis">
            <?php foreach ($FILES as $k => $f): ?>
            <a class="pt-kpi <?php echo $f['cls']; ?><?php echo $k === $file ? ' on' : ''; ?>" href="<?php echo fpl_e($url($k)); ?>">
                <span class="ico"><?php echo fpl_icone($f['ico'], 20); ?></span>
                <span><div class="n"><?php echo number_format($compte[$k], 0, ',', ' '); ?></div><div class="l"><?php echo fpl_e($f['titre']); ?></div><div class="h"><?php echo fpl_e($f['sous']); ?></div></span>
            </a>
            <?php endforeach; ?>
        </div>

        <form class="pt-barre" method="get" action="photo-travail.php" id="pt-form">
            <input type="hidden" name="file" value="<?php echo fpl_e($file); ?>">
            <div class="pt-search">
                <span class="ico"><?php echo fpl_icone('search', 17); ?></span>
                <input id="pt-q" type="text" placeholder="Rechercher une pièce : nom, référence FPL, référence OEM…" autocomplete="off">
            </div>
            <div class="pt-filtres">
                <select name="marque" onchange="document.getElementById('pt-form').submit()" aria-label="Filtrer par marque">
                    <option value="0">Toutes les marques</option>
                    <?php foreach ($marques as $m): ?>
                    <option value="<?php echo (int) $m['id']; ?>"<?php echo (int) $m['id'] === $marque_id ? ' selected' : ''; ?>><?php echo fpl_e($m['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="cat" onchange="document.getElementById('pt-form').submit()" aria-label="Filtrer par famille">
                    <option value="0">Toutes les familles</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === $cat_id ? ' selected' : ''; ?>><?php echo fpl_e($c['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($marque_id > 0 || $cat_id > 0): ?><a class="pt-raz" href="photo-travail.php?file=<?php echo fpl_e($file); ?>">Effacer les filtres</a><?php endif; ?>
            </div>
        </form>
        <div id="pt-res" class="pt-res pt-grid"></div>

        <section class="pt-section">
            <h2><?php echo fpl_e($FILES[$file]['titre']); ?> <span class="pt-pill"><?php echo number_format($total_file, 0, ',', ' '); ?> pièce<?php echo $total_file > 1 ? 's' : ''; ?></span>
                <?php if ($pages > 1): ?><span class="pt-pill">page <?php echo (int) $page; ?> / <?php echo (int) $pages; ?></span><?php endif; ?></h2>
            <div class="pt-sub"><?php echo fpl_e($FILES[$file]['sous']); ?><?php echo ($marque_id > 0 || $cat_id > 0) ? ' — filtres appliqués' : ''; ?></div>
            <?php if ($lignes === []): ?>
                <div class="pt-empty">Aucune pièce dans cette file<?php echo ($marque_id > 0 || $cat_id > 0) ? ' avec ces filtres' : ''; ?>.</div>
            <?php else: ?>
            <div class="pt-grid"><?php foreach ($lignes as $row) { $carte($row, $FILES[$file]['cta']); } ?></div>
            <?php endif; ?>

            <?php if ($pages > 1): ?>
            <div class="pt-pages">
                <?php if ($page > 1): ?><a href="<?php echo fpl_e($url($file, $page - 1)); ?>">← Précédent</a><?php else: ?><span class="dis">← Précédent</span><?php endif; ?>
                <span class="pt-ou">page <?php echo (int) $page; ?> sur <?php echo (int) $pages; ?></span>
                <?php if ($page < $pages): ?><a href="<?php echo fpl_e($url($file, $page + 1)); ?>">Suivant →</a><?php else: ?><span class="dis">Suivant →</span><?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
    (function () {
        // la recherche : mêmes cartes, mêmes raccourcis (elle cherche dans TOUTES les pièces)
        var q = document.getElementById('pt-q');
        var res = document.getElementById('pt-res');
        var t = null;
        q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
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
