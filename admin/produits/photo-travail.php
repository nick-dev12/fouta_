<?php
/**
 * ESPACE PHOTO — l'accueil du photographe. Trois entrées :
 *  1. RECHERCHER une pièce (nom/réf) pour éditer ses photos.
 *  2. À PHOTOGRAPHIER : les pièces sans aucune image (la priorité).
 *  3. RÉCEMMENT MODIFIÉES : ses dernières pièces, pour vérifier le rendu.
 * Le photographe ne voit ni prix, ni stock, ni fournisseur.
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

$SANS_PHOTO = "(image_principale IS NULL OR image_principale='')
    AND (images IS NULL OR images='' OR images='[]')
    AND (image_etiquette_fpl IS NULL OR image_etiquette_fpl='')";

$nb_sans_photo = 0;
$sans_photo = [];
try {
    $nb_sans_photo = (int) $db->query(
        "SELECT COUNT(*) FROM produits WHERE sync_deleted_at IS NULL AND ($SANS_PHOTO)"
    )->fetchColumn();
    $sans_photo = $db->query(
        "SELECT p.id, p.identifiant_interne, p.nom, m.nom AS marque_nom
           FROM produits p LEFT JOIN marques m ON m.id = p.marque_id
          WHERE p.sync_deleted_at IS NULL AND ($SANS_PHOTO)
          ORDER BY p.id DESC LIMIT 24"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sans_photo = [];
}

$recentes = [];
try {
    $recentes = $db->query(
        "SELECT p.id, p.identifiant_interne, p.nom, p.image_principale, p.images
           FROM produits p
          WHERE p.sync_deleted_at IS NULL AND NOT ($SANS_PHOTO)
          ORDER BY p.date_modification DESC, p.id DESC LIMIT 12"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentes = [];
}
$vignette = function ($row) {
    $imgs = json_decode((string) ($row['images'] ?? ''), true);
    $rel = (is_array($imgs) && !empty($imgs[0])) ? $imgs[0] : (string) ($row['image_principale'] ?? '');
    return $rel !== '' ? '../../upload/' . ltrim(str_replace('\\', '/', $rel), '/') : '';
};

$fpl_titre_page = 'Espace photo';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace photo — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <style>
    .pt-wrap { max-width: 1180px; margin: 0 auto; padding: 18px 16px 40px; }
    .pt-wrap h1 { font-size: 23px; color: var(--navy, #10316F); margin: 0 0 2px; }
    .pt-lead { color: #5C6A85; font-size: 13.5px; margin-bottom: 18px; }
    .pt-search { position: relative; max-width: 560px; margin-bottom: 8px; }
    .pt-search input { width: 100%; border: 1.5px solid #DBE2EE; border-radius: 10px; padding: 12px 14px; font-size: 15px; }
    .pt-search input:focus { outline: none; border-color: var(--navy, #10316F); }
    .pt-res { margin: 4px 0 26px; display: none; }
    .pt-section h2 { font-size: 16px; color: var(--navy-ink, #08193A); margin: 22px 0 12px; display: flex; align-items: center; gap: 8px; }
    .pt-pill { background: #ECF2FC; color: var(--navy, #10316F); font-size: 12.5px; font-weight: 700; border-radius: 999px; padding: 2px 10px; }
    .pt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(168px, 1fr)); gap: 14px; }
    .pt-card { background: #fff; border: 1px solid #E5EAF2; border-radius: 12px; overflow: hidden; text-decoration: none; color: inherit; display: flex; flex-direction: column; transition: .12s; }
    .pt-card:hover { border-color: var(--navy, #10316F); box-shadow: 0 4px 14px rgba(15,32,64,.10); }
    .pt-thumb { height: 128px; background: #F4F6FA; display: flex; align-items: center; justify-content: center; }
    .pt-thumb img { max-width: 100%; max-height: 128px; object-fit: contain; }
    .pt-thumb.vide { color: #A9B4C8; flex-direction: column; gap: 6px; font-size: 12px; }
    .pt-meta { padding: 10px 12px; }
    .pt-meta .pt-nom { font-weight: 600; font-size: 13.5px; line-height: 1.25; color: #16203A; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .pt-meta .pt-ref { font-family: Consolas, monospace; font-size: 11.5px; color: #5C6A85; margin-top: 3px; }
    .pt-cta { margin-top: 6px; font-size: 12px; font-weight: 700; color: var(--navy, #10316F); }
    .pt-empty { color: #8894A8; font-size: 13.5px; padding: 10px 2px; }
    </style>
</head>
<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="pt-wrap">
        <h1>Espace photo</h1>
        <div class="pt-lead">Trouvez une pièce, ajoutez ses photos, vérifiez que le détourage rend bien.</div>

        <div class="pt-search">
            <input id="pt-q" type="text" placeholder="Rechercher une pièce : nom, référence…" autocomplete="off">
        </div>
        <div id="pt-res" class="pt-res pt-grid"></div>

        <div class="pt-section">
            <h2>À photographier <span class="pt-pill"><?php echo (int) $nb_sans_photo; ?> pièce<?php echo $nb_sans_photo > 1 ? 's' : ''; ?></span></h2>
            <?php if ($sans_photo === []): ?>
                <div class="pt-empty">Bravo — toutes les pièces ont au moins une photo.</div>
            <?php else: ?>
            <div class="pt-grid">
                <?php foreach ($sans_photo as $row): ?>
                <a class="pt-card" href="photo-editer.php?id=<?php echo (int) $row['id']; ?>">
                    <div class="pt-thumb vide">
                        <?php echo fpl_icone('image', 26); ?>
                        <span>Aucune photo</span>
                    </div>
                    <div class="pt-meta">
                        <div class="pt-nom"><?php echo fpl_e($row['nom']); ?></div>
                        <div class="pt-ref"><?php echo fpl_e($ref_aeree($row['identifiant_interne'])); ?></div>
                        <div class="pt-cta">Photographier →</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if ($nb_sans_photo > count($sans_photo)): ?>
                <div class="pt-empty">… et <?php echo (int) ($nb_sans_photo - count($sans_photo)); ?> autres. Traitez celles-ci d'abord.</div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($recentes !== []): ?>
        <div class="pt-section">
            <h2>Récemment modifiées</h2>
            <div class="pt-grid">
                <?php foreach ($recentes as $row): $v = $vignette($row); ?>
                <a class="pt-card" href="photo-editer.php?id=<?php echo (int) $row['id']; ?>">
                    <div class="pt-thumb"><?php if ($v !== ''): ?><img src="<?php echo fpl_e($v); ?>" alt=""><?php else: ?><?php echo fpl_icone('image', 24); ?><?php endif; ?></div>
                    <div class="pt-meta">
                        <div class="pt-nom"><?php echo fpl_e($row['nom']); ?></div>
                        <div class="pt-ref"><?php echo fpl_e($ref_aeree($row['identifiant_interne'])); ?></div>
                        <div class="pt-cta">Modifier les photos →</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
    (function () {
        var q = document.getElementById('pt-q');
        var res = document.getElementById('pt-res');
        var t = null;
        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
        function cherche() {
            var v = q.value.trim();
            if (v.length < 2) { res.style.display = 'none'; res.innerHTML = ''; return; }
            fetch('ajax_recherche_piece.php?q=' + encodeURIComponent(v), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var items = (d && d.products) ? d.products : [];
                    if (items.length === 0) { res.style.display = 'block'; res.innerHTML = '<div class="pt-empty">Aucune pièce ne correspond.</div>'; return; }
                    res.innerHTML = items.map(function (p) {
                        var img = p.image ? '<img src="' + esc(p.image) + '" alt="">' : '<span>Aucune photo</span>';
                        return '<a class="pt-card" href="photo-editer.php?id=' + encodeURIComponent(p.id) + '">'
                            + '<div class="pt-thumb' + (p.image ? '' : ' vide') + '">' + img + '</div>'
                            + '<div class="pt-meta"><div class="pt-nom">' + esc(p.name) + '</div>'
                            + '<div class="pt-ref">' + esc(p.code) + '</div>'
                            + '<div class="pt-cta">Modifier les photos →</div></div></a>';
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
