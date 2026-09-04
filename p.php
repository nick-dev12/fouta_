<?php
/**
 * /p/{code} — LA VITRINE CLIENT D'UNE PIÈCE (04/09/2026).
 *
 * La page qu'ouvre le QR de l'étiquette de pièce. Le {code} est le numéro
 * EAN-13 imprimé sous le code-barres (même contenu que le QR — décision de la
 * direction), l'identifiant FPL est aussi accepté (FPL001006463, espaces et
 * casse indifférents).
 *
 * C'est du MARKETING, pas de la gestion : rien d'interne n'y figure — ni
 * stock chiffré, ni emplacement, ni fournisseur, ni prix d'achat/grossiste/
 * entreprise. La requête est une LISTE BLANCHE de colonnes, jamais un SELECT *
 * imprimé. Aucune session n'est ouverte.
 */

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/includes/produit_vitrine.php';
require_once __DIR__ . '/includes/fpl_public_branding.php';
require_once __DIR__ . '/includes/fpl_texte.php';

$coords = fpl_public_branding_coords();
$social = is_file(__DIR__ . '/config/social.php') ? (array) (require __DIR__ . '/config/social.php') : [];

/* ------------------------------------------------------------------ vCard */
if (isset($_GET['vcard'])) {
    $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\n"
        . "N:;" . $coords['nom'] . ";;;\r\n"
        . "FN:" . $coords['nom'] . "\r\n"
        . "ORG:" . $coords['nom'] . " — " . $coords['tagline'] . "\r\n"
        . "TEL;TYPE=WORK,VOICE:" . preg_replace('/[^+\d]/', '', $coords['telephone']) . "\r\n";
    if (!empty($coords['telephone2'])) {
        $vcf .= "TEL;TYPE=WORK,VOICE:" . preg_replace('/[^+\d]/', '', $coords['telephone2']) . "\r\n";
    }
    if (!empty($social['whatsapp'])) {
        $vcf .= "TEL;TYPE=CELL:+" . preg_replace('/\D/', '', (string) $social['whatsapp']) . "\r\n";
    }
    $vcf .= "EMAIL;TYPE=WORK:" . $coords['email'] . "\r\n"
        . "ADR;TYPE=WORK:;;" . $coords['adresse'] . ";Dakar;;;Sénégal\r\n"
        . "URL:" . $coords['site'] . "\r\n"
        . "END:VCARD\r\n";
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="fouta-poids-lourds.vcf"');
    echo $vcf;
    exit;
}

/* ------------------------------------------------- résolution de la pièce */
$identifiant = fpl_vitrine_code_vers_identifiant($_GET['code'] ?? '');

$piece = null;
$modeles = [];
if ($identifiant !== '') {
    try {
        $st = $db->prepare(
            "SELECT p.id, p.identifiant_interne, p.nom, p.nom_wolof, p.description,
                    p.statut, p.stock, p.prix, p.prix_promotion, p.reference_oem,
                    p.image_principale, p.images, p.image_etiquette_fpl,
                    c.nom AS categorie_nom, sc.nom AS sous_categorie_nom,
                    m.nom AS marque_nom
               FROM produits p
          LEFT JOIN categories c ON c.id = p.categorie_id
          LEFT JOIN sous_categories sc ON sc.id = p.sous_categorie_id
          LEFT JOIN marques m ON m.id = p.marque_id
              WHERE UPPER(TRIM(p.identifiant_interne)) = :code
                AND p.sync_deleted_at IS NULL
              LIMIT 1"
        );
        $st->execute([':code' => $identifiant]);
        $piece = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $piece = null;
    }

    /* Compatibilité multi-modèles : enrichissement OPTIONNEL — la table pivot
       peut ne pas exister sur une base pas encore migrée, la page vit sans. */
    if ($piece) {
        try {
            $sm = $db->prepare(
                "SELECT vm.nom FROM produit_modeles pm
                   JOIN vehicule_modeles vm ON vm.id = pm.modele_id
                  WHERE pm.produit_id = :id ORDER BY vm.nom"
            );
            $sm->execute([':id' => (int) $piece['id']]);
            $modeles = $sm->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            $modeles = [];
        }
    }
}

/* ------------------------------------------------------------ les données */
$photos = [];
$ean13 = '';
$ref_aeree = '';
$dispo = false;
$prix = 0.0;
$promo = 0.0;

if ($piece) {
    $ean13 = fpl_vitrine_ean13_pour_produit($piece);
    /* FPL 001 006 463 — la référence aérée en groupes de 3, le geste de l'étiquette */
    $ref_aeree = trim((string) $piece['identifiant_interne']);
    if (preg_match('/^FPL(\d{9})$/', strtoupper($ref_aeree), $m)) {
        $ref_aeree = 'FPL ' . implode(' ', str_split($m[1], 3));
    }

    $bruts = [];
    if (!empty($piece['image_principale'])) {
        $bruts[] = (string) $piece['image_principale'];
    }
    $galerie = json_decode((string) ($piece['images'] ?? ''), true);
    if (is_array($galerie)) {
        foreach ($galerie as $g) {
            if (is_string($g) && $g !== '') {
                $bruts[] = $g;
            }
        }
    }
    if ($bruts === [] && !empty($piece['image_etiquette_fpl'])) {
        $bruts[] = (string) $piece['image_etiquette_fpl'];
    }
    foreach (array_values(array_unique($bruts)) as $chemin) {
        $chemin = ltrim(str_replace('\\', '/', $chemin), '/');
        if (is_file(__DIR__ . '/upload/' . $chemin)) {
            $photos[] = '/upload/' . implode('/', array_map('rawurlencode', explode('/', $chemin)));
        }
    }

    $dispo = ($piece['statut'] === 'actif' && (int) $piece['stock'] > 0);
    $prix = (float) $piece['prix'];
    $promo = (float) $piece['prix_promotion'];
}

$fcfa = function ($v) {
    return number_format((float) $v, 0, ',', ' ') . ' FCFA';
};

$wa_num = preg_replace('/\D/', '', (string) ($social['whatsapp'] ?? ''));
$wa_msg = $piece
    ? 'Bonjour FOUTA POIDS LOURDS, je souhaite des informations sur la pièce '
        . $ref_aeree . ' — ' . $piece['nom']
    : 'Bonjour FOUTA POIDS LOURDS, je souhaite des informations sur une pièce.';
$wa_url = $wa_num !== '' ? 'https://wa.me/' . $wa_num . '?text=' . rawurlencode($wa_msg) : '';
$maps_url = 'https://www.google.com/maps/search/?api=1&query='
    . rawurlencode($coords['nom'] . ', ' . $coords['adresse']);

$origine = get_request_origin_base_url();
$canonique = $piece ? $origine . '/p/' . $ean13 : $origine . '/p/';
$titre = $piece ? fpl_texte($piece['nom']) . ' — Fouta Poids Lourds' : 'Pièce introuvable — Fouta Poids Lourds';
$meta_desc = $piece
    ? 'Pièce poids lourd ' . fpl_texte($piece['nom'])
        . (empty($piece['marque_nom']) ? '' : ' pour ' . fpl_texte($piece['marque_nom']))
        . ' — référence ' . $ref_aeree . '. FOUTA POIDS LOURDS, The Solution, Dakar.'
    : 'FOUTA POIDS LOURDS — pièces détachées poids lourds à Dakar.';

if (!$piece) {
    http_response_code(404);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= fpl_e($titre) ?></title>
<meta name="description" content="<?= fpl_e($meta_desc) ?>">
<link rel="canonical" href="<?= fpl_e($canonique) ?>">
<meta property="og:title" content="<?= fpl_e($titre) ?>">
<meta property="og:description" content="<?= fpl_e($meta_desc) ?>">
<?php if ($photos !== []): ?>
<meta property="og:image" content="<?= fpl_e($origine . $photos[0]) ?>">
<?php endif; ?>
<style>
/* La langue visuelle de l'étiquette : outremer #10316F (le rendu de l'encre),
   Anton pour le grand titre, Barlow Condensed pour les libellés. */
@font-face { font-family: 'Anton'; src: url('/fonts/etiquette70/anton-400.ttf') format('truetype'); font-weight: 400; font-display: swap; }
@font-face { font-family: 'Barlow Condensed'; src: url('/fonts/etiquette70/barlow-condensed-700.ttf') format('truetype'); font-weight: 700; font-display: swap; }
:root {
    --navy: #10316F; --navy-deep: #0C2350; --navy-ink: #08193A;
    --blue: #2957AE; --blue-600: #1D4590; --tint: #EDF1F8; --tint-2: #D8E0EE;
    --ink: #16203A; --slate: #5C6A85; --line: #DFE4EC; --ground: #F4F6FA;
    --ok: #12694A; --ok-bg: #E4F2EB; --wa: #1FAF54; --promo: #FF6B35;
    --cond: 'Barlow Condensed', 'Arial Narrow', sans-serif;
    --corps: 'Segoe UI', system-ui, -apple-system, sans-serif;
}
* { box-sizing: border-box; margin: 0; }
body { background: var(--ground); color: var(--ink); font-family: var(--corps); font-size: 15.5px; line-height: 1.55; -webkit-font-smoothing: antialiased; }
a { color: var(--blue-600); }

.hero { background: radial-gradient(130% 130% at 50% -10%, #1D4590 0%, var(--navy) 52%, var(--navy-ink) 100%); color: #fff; text-align: center; padding: 34px 20px 86px; }
.hero img.logo { height: 58px; width: auto; }
.hero .nom-maison { font-family: var(--cond); font-size: 25px; letter-spacing: 2.5px; margin-top: 10px; }
.hero .tagline { font-style: italic; font-size: 14.5px; color: #C4D1E9; margin-top: 2px; }
.hero .accueil { max-width: 480px; margin: 16px auto 0; font-size: 14.5px; color: #DDE6F5; }

.wrap { max-width: 640px; margin: -58px auto 0; padding: 0 14px 30px; position: relative; }
.camion { position: absolute; top: -120px; right: -40px; width: 300px; pointer-events: none; z-index: -1; display: none; }
@media (min-width: 900px) { .camion { display: block; } }

.card { background: #fff; border-radius: 16px; box-shadow: 0 10px 34px rgba(8, 25, 58, .16); overflow: hidden; }
.visuel { background: #F8F8F8; padding: 18px; text-align: center; }
.visuel img { max-width: 100%; max-height: 300px; object-fit: contain; }
.vignettes { display: flex; gap: 8px; justify-content: center; padding: 0 18px 14px; background: #F8F8F8; flex-wrap: wrap; }
.vignettes button { border: 2px solid var(--line); border-radius: 8px; background: #fff; padding: 3px; cursor: pointer; }
.vignettes button.active { border-color: var(--navy); }
.vignettes img { width: 52px; height: 52px; object-fit: contain; display: block; }
.sans-photo { padding: 44px 18px; color: var(--slate); background: #F8F8F8; }
.sans-photo svg { display: block; margin: 0 auto 10px; }

.corps { padding: 20px 22px 24px; }
.refbar { display: flex; align-items: center; gap: 10px; background: #ECF2FC; border: 1px solid var(--tint-2); border-radius: 10px; padding: 9px 13px; }
.refbar .k { font-family: var(--cond); font-size: 12.5px; letter-spacing: 1.2px; color: var(--navy-ink); }
.refbar .v { font-family: Consolas, SFMono-Regular, monospace; font-weight: 700; font-size: 16.5px; color: var(--navy); letter-spacing: .5px; }
.code-article { margin-top: 6px; font-size: 12px; color: var(--slate); }
.code-article code { font-family: Consolas, monospace; letter-spacing: 1px; }

h1.piece { font-family: var(--cond); font-size: 27px; line-height: 1.15; letter-spacing: .4px; color: var(--navy-ink); margin-top: 14px; text-transform: uppercase; }
.wolof { font-family: 'Anton', var(--cond); font-size: 30px; letter-spacing: 1.5px; color: var(--navy-ink); margin-top: 14px; }
.wolof + h1.piece { font-size: 19px; color: var(--slate); margin-top: 2px; }

.dispo { display: inline-flex; align-items: center; gap: 7px; margin-top: 11px; font-size: 13.5px; font-weight: 600; border-radius: 999px; padding: 4px 12px; }
.dispo.oui { color: var(--ok); background: var(--ok-bg); }
.dispo.non { color: var(--slate); background: var(--tint); }
.dispo .pt { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

dl.details { margin-top: 16px; display: grid; gap: 9px; }
dl.details > div { display: flex; gap: 12px; }
dl.details dt { font-family: var(--cond); font-size: 13px; letter-spacing: 1px; color: var(--slate); min-width: 128px; text-transform: uppercase; padding-top: 2px; }
dl.details dd { font-weight: 600; }

.desc { margin-top: 16px; background: #F7F9FC; border-left: 3px solid var(--blue); border-radius: 0 8px 8px 0; padding: 11px 14px; font-size: 14.5px; }

.prix-bloc { margin-top: 18px; border-top: 1px solid var(--line); padding-top: 16px; display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
.prix-bloc .montant { font-family: var(--cond); font-size: 30px; color: var(--navy); }
.prix-bloc .ancien { color: var(--slate); text-decoration: line-through; }
.prix-bloc .badge-promo { background: var(--promo); color: #fff; font-weight: 700; font-size: 13px; border-radius: 6px; padding: 3px 8px; }
.prix-demande { margin-top: 18px; border-top: 1px solid var(--line); padding-top: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.prix-demande .txt { font-family: var(--cond); font-size: 19px; color: var(--navy-ink); }

.engagements { margin-top: 22px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.engagements .item { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 13px 10px; text-align: center; font-size: 12.5px; color: var(--ink); font-weight: 600; line-height: 1.35; }
.engagements svg { display: block; margin: 0 auto 7px; }
@media (max-width: 430px) { .engagements { grid-template-columns: 1fr; } .engagements .item { display: flex; align-items: center; gap: 10px; text-align: left; } .engagements svg { margin: 0; flex: none; } }

.slogan { display: block; max-width: 320px; width: 72%; margin: 26px auto 4px; }

.contact { margin-top: 20px; background: #fff; border-radius: 16px; box-shadow: 0 6px 22px rgba(8, 25, 58, .10); padding: 20px 22px; }
.contact h2 { font-family: var(--cond); font-size: 21px; letter-spacing: .5px; color: var(--navy-ink); }
.contact .sous { color: var(--slate); font-size: 13.5px; margin-top: 2px; }
.actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
.actions a { display: flex; align-items: center; justify-content: center; gap: 9px; border-radius: 11px; padding: 12px 10px; font-weight: 700; font-size: 14.5px; text-decoration: none; }
.actions svg { flex: none; }
.a-wa { background: var(--wa); color: #fff; grid-column: 1 / -1; }
.a-tel { background: var(--navy); color: #fff; }
.a-maps { background: var(--tint); color: var(--navy-ink); }
.a-cat { background: var(--tint); color: var(--navy-ink); }
.a-vcf { background: #fff; color: var(--navy-ink); border: 1.5px solid var(--tint-2); }
.reseaux { display: flex; gap: 14px; justify-content: center; margin-top: 16px; }
.reseaux a { color: var(--slate); display: inline-flex; }

.pied { background: var(--navy-ink); color: #C4D1E9; text-align: center; padding: 26px 20px 30px; margin-top: 28px; font-size: 13px; line-height: 1.7; }
.pied .maison { font-family: var(--cond); font-size: 17px; letter-spacing: 1.5px; color: #fff; }
.pied .tagline { font-style: italic; }
.pied a { color: #fff; }
.pied .legal { margin-top: 8px; font-size: 11.5px; color: #8B9EC1; }
</style>
</head>
<body>

<header class="hero">
    <img class="logo" src="/image/logo-fpl-blanc.png" alt="FPL" onerror="this.style.display='none'">
    <div class="nom-maison">FOUTA POIDS LOURDS</div>
    <div class="tagline">The Solution</div>
    <?php if ($piece): ?>
    <p class="accueil">Merci de votre confiance ! Vous venez de scanner une pièce sélectionnée et contrôlée par nos équipes.</p>
    <?php else: ?>
    <p class="accueil">Cette référence ne correspond à aucune pièce de notre catalogue. Notre équipe reste à votre disposition.</p>
    <?php endif; ?>
</header>

<main class="wrap">
    <img class="camion" src="/image/vitrine/camion-filigrane.png" alt="">

    <?php if ($piece): ?>
    <article class="card">
        <?php if ($photos !== []): ?>
        <div class="visuel"><img id="photo-principale" src="<?= fpl_e($photos[0]) ?>" alt="<?= fpl_e($piece['nom']) ?>"></div>
        <?php if (count($photos) > 1): ?>
        <div class="vignettes">
            <?php foreach ($photos as $i => $ph): ?>
            <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-src="<?= fpl_e($ph) ?>"><img src="<?= fpl_e($ph) ?>" alt=""></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="sans-photo">
            <svg width="54" height="54" viewBox="0 0 24 24" fill="none" stroke="#8B9EC1" stroke-width="1.6"><path d="M14.7 6.3a5 5 0 1 0-6.9 6.9L3 18v3h3l4.8-4.8a5 5 0 0 0 6.9-6.9L14 12l-2-2 3.7-3.7z"/></svg>
            Photo en cours de préparation
        </div>
        <?php endif; ?>

        <div class="corps">
            <div class="refbar"><span class="k">RÉFÉRENCE FPL</span><span class="v"><?= fpl_e($ref_aeree) ?></span></div>
            <div class="code-article">Code article (identique au code-barres de l'étiquette) : <code><?= fpl_e($ean13) ?></code></div>

            <?php if (!empty($piece['nom_wolof'])): ?>
            <div class="wolof"><?= fpl_e($piece['nom_wolof']) ?></div>
            <?php endif; ?>
            <h1 class="piece"><?= fpl_e($piece['nom']) ?></h1>

            <?php if ($dispo): ?>
            <span class="dispo oui"><span class="pt"></span>Disponible en magasin</span>
            <?php else: ?>
            <span class="dispo non"><span class="pt"></span>Nous consulter</span>
            <?php endif; ?>

            <dl class="details">
                <?php if (!empty($piece['marque_nom'])): ?>
                <div><dt>Marque</dt><dd><?= fpl_e($piece['marque_nom']) ?></dd></div>
                <?php endif; ?>
                <?php if ($modeles !== []): ?>
                <div><dt>Compatible avec</dt><dd><?= fpl_e(implode(', ', $modeles)) ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($piece['reference_oem'])): ?>
                <div><dt>Référence OEM</dt><dd><?= fpl_e($piece['reference_oem']) ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($piece['categorie_nom'])): ?>
                <div><dt>Famille</dt><dd><?= fpl_e($piece['categorie_nom'] . (empty($piece['sous_categorie_nom']) ? '' : ' › ' . $piece['sous_categorie_nom'])) ?></dd></div>
                <?php endif; ?>
            </dl>

            <?php /* Une description sans la moindre lettre (« 131 900 »…) est une
                     note interne du staff, pas du texte client : 130 pièces en
                     portent — on ne la montre pas. */ ?>
            <?php if (!empty($piece['description'])
                && trim((string) $piece['description']) !== trim((string) $piece['nom'])
                && preg_match('/\p{L}/u', (string) $piece['description'])): ?>
            <div class="desc"><?= fpl_e($piece['description']) ?></div>
            <?php endif; ?>

            <?php if ($prix > 0 && $promo > 0 && $promo < $prix): ?>
            <div class="prix-bloc">
                <span class="montant"><?= fpl_e($fcfa($promo)) ?></span>
                <span class="ancien"><?= fpl_e($fcfa($prix)) ?></span>
                <span class="badge-promo">−<?= (int) round(100 * ($prix - $promo) / $prix) ?> %</span>
            </div>
            <?php elseif ($prix > 0): ?>
            <div class="prix-bloc"><span class="montant"><?= fpl_e($fcfa($prix)) ?></span></div>
            <?php else: ?>
            <div class="prix-demande">
                <span class="txt">Prix sur demande</span>
                <?php if ($wa_url !== ''): ?><a class="a-wa" style="display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:9px 14px;color:#fff;text-decoration:none;font-weight:700;font-size:13.5px;" href="<?= fpl_e($wa_url) ?>">Demander sur WhatsApp</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </article>

    <section class="engagements">
        <div class="item">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#10316F" stroke-width="1.9"><path d="M12 2l8 3.5v5.2c0 5-3.4 9.4-8 10.8-4.6-1.4-8-5.8-8-10.8V5.5L12 2z"/><path d="M8.5 12l2.4 2.4 4.6-4.8"/></svg>
            Pièce contrôlée par nos équipes
        </div>
        <div class="item">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#10316F" stroke-width="1.9"><circle cx="12" cy="8" r="3.4"/><path d="M4.5 20c.8-3.6 3.9-5.6 7.5-5.6s6.7 2 7.5 5.6"/></svg>
            Conseil d'experts poids lourds
        </div>
        <div class="item">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#10316F" stroke-width="1.9"><path d="M3 7h11v10H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg>
            Un large choix à Dakar
        </div>
    </section>
    <?php endif; ?>

    <img class="slogan" src="/image/vitrine/slogan-manuscrit.png" alt="Conduire avec assurance — ndakh jombtukay you worr">

    <section class="contact">
        <h2>Une question sur cette pièce ?</h2>
        <div class="sous">Notre équipe vous répond — <?= fpl_e($coords['nom']) ?>, <?= fpl_e($coords['tagline']) ?>.</div>
        <div class="actions">
            <?php if ($wa_url !== ''): ?>
            <a class="a-wa" href="<?= fpl_e($wa_url) ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.4 14.1c-.2.7-1.3 1.3-1.9 1.4-.5.1-1.1.2-3.4-.7-2.8-1.2-4.7-4-4.8-4.2-.1-.2-1.2-1.6-1.2-3s.7-2.1 1-2.4c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.4.6-.5.5c-.2.2-.3.4-.1.7.2.3.8 1.4 1.8 2.2 1.3 1.1 2.3 1.5 2.7 1.6.3.1.5.1.7-.1l1-1.2c.2-.3.4-.2.7-.1l2.1 1c.3.1.5.2.6.3 0 .2 0 .7-.2 1.4z"/></svg>
                Écrire sur WhatsApp
            </a>
            <?php endif; ?>
            <a class="a-tel" href="<?= fpl_e($coords['telephone_href']) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                Appeler
            </a>
            <a class="a-maps" href="<?= fpl_e($maps_url) ?>" target="_blank" rel="noopener">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
                Itinéraire
            </a>
            <a class="a-cat" href="/produits.php">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16M4 12h16M4 19h16"/></svg>
                Catalogue
            </a>
            <a class="a-vcf" href="?vcard=1" style="grid-column: 1 / -1;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6.5 16c.5-1.5 1.5-2.2 2.5-2.2s2 .7 2.5 2.2M14 9.5h4M14 13h4"/></svg>
                Enregistrer notre contact
            </a>
        </div>
        <div class="reseaux">
            <?php if (!empty($social['facebook'])): ?>
            <a href="<?= fpl_e($social['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook"><svg width="21" height="21" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7h2.4l.4-2.9h-2.8V9.2c0-.8.3-1.4 1.5-1.4h1.4V5.2c-.2 0-1.1-.1-2.1-.1-2.1 0-3.6 1.3-3.6 3.7v2.3H8.3V14h2.4v7h2.8z"/></svg></a>
            <?php endif; ?>
            <?php if (!empty($social['linkedin'])): ?>
            <a href="<?= fpl_e($social['linkedin']) ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><svg width="21" height="21" viewBox="0 0 24 24" fill="currentColor"><path d="M6.5 8.5H3.8V21h2.7V8.5zM5.1 3.5a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2zM21 13.4c0-3-1.6-4.4-3.8-4.4-1.7 0-2.5 1-2.9 1.6V8.5h-2.7V21h2.7v-6.8c0-1.2.8-2 1.9-2s1.8.8 1.8 2V21H21v-7.6z"/></svg></a>
            <?php endif; ?>
            <?php if (!empty($social['tiktok'])): ?>
            <a href="<?= fpl_e($social['tiktok']) ?>" target="_blank" rel="noopener" aria-label="TikTok"><svg width="21" height="21" viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 3c.4 2.1 1.8 3.6 3.9 3.9v2.8c-1.5 0-2.8-.5-3.9-1.3v6.1c0 3.4-2.4 5.5-5.4 5.5S5.8 17.9 5.8 15c0-3.1 2.5-5.2 5.6-5V13c-1.4-.4-2.8.4-2.8 2 0 1.4 1 2.3 2.3 2.3 1.4 0 2.4-1 2.4-2.7V3h3.3z"/></svg></a>
            <?php endif; ?>
        </div>
    </section>
</main>

<footer class="pied">
    <div class="maison"><?= fpl_e($coords['nom']) ?></div>
    <div class="tagline"><?= fpl_e($coords['tagline']) ?></div>
    <div><?= fpl_e($coords['adresse']) ?></div>
    <div><?= fpl_e($coords['telephone']) ?><?= empty($coords['telephone2']) ? '' : fpl_e(' · ' . $coords['telephone2']) ?></div>
    <div><a href="<?= fpl_e($coords['site']) ?>"><?= fpl_e(preg_replace('#^https?://#', '', $coords['site'])) ?></a> · <a href="mailto:<?= fpl_e($coords['email']) ?>"><?= fpl_e($coords['email']) ?></a></div>
    <div class="legal">RC <?= fpl_e($coords['rc']) ?> · NINEA <?= fpl_e($coords['ninea']) ?></div>
</footer>

<?php if (count($photos) > 1): ?>
<script>
document.querySelectorAll('.vignettes button').forEach(function (b) {
    b.addEventListener('click', function () {
        document.getElementById('photo-principale').src = b.dataset.src;
        document.querySelectorAll('.vignettes button').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
    });
});
</script>
<?php endif; ?>
</body>
</html>
