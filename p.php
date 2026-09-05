<?php
/**
 * /p/{code} — LA VITRINE CLIENT D'UNE PIÈCE (04/09/2026, redessinée le 05/09).
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
 *
 * DESSIN DU 05/09 (retour de la direction) : une seule colonne tournée vers le
 * client — voir la pièce, la reconnaître, connaître le prix, joindre la maison
 * avec de VRAIS boutons. Ni famille, ni disponibilité, ni phrases marketing.
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

/* Le seul texte du bouton WhatsApp qui change : sans prix, il demande le prix. */
$wa_libelle = ($piece && $prix <= 0) ? 'Demander le prix sur WhatsApp' : 'WhatsApp';

/* La carte « détails » n'existe que si elle a quelque chose à dire. La règle de
   la description est inchangée : une description sans la moindre lettre
   (« 131 900 »…) est une note du staff, pas du texte client — on ne la montre pas. */
$a_description = $piece && !empty($piece['description'])
    && trim((string) $piece['description']) !== trim((string) $piece['nom'])
    /* …ni une description qui ne fait que répéter la marque (« MERCEDES BENZ ») :
       la marque est déjà en sur-titre, la redire n'apporte rien au client. */
    && strcasecmp(trim((string) $piece['description']), trim((string) ($piece['marque_nom'] ?? ''))) !== 0
    && preg_match('/\p{L}/u', (string) $piece['description']);
$a_details = $piece && ($modeles !== [] || !empty($piece['reference_oem']) || $a_description);

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
/* La boutique qui vous répond — une colonne, lumière chaude, l'outremer de
   l'étiquette (#10316F) réservé à l'identité et aux appuis. Deux polices,
   toutes deux servies depuis /fonts/etiquette70 (aucun appel externe) :
   Barlow 500 pour lire, Barlow Condensed 700 pour montrer. */
@font-face { font-family: 'Barlow'; src: url('/fonts/etiquette70/barlow-500.ttf') format('truetype'); font-weight: 500; font-display: swap; }
@font-face { font-family: 'Barlow Condensed'; src: url('/fonts/etiquette70/barlow-condensed-700.ttf') format('truetype'); font-weight: 700; font-display: swap; }
:root {
    --bleu: #10316F; --bleu-nuit: #0B2554; --bleu-voile: #E9EEF7;
    --encre: #1B2437; --gris: #5A6478; --trait: #E6E2DA; --fond: #F7F5F0; --blanc: #FFFFFF;
    --wa: #25D366; --promo: #C4381A;
    --cond: 'Barlow Condensed', 'Arial Narrow', 'Roboto Condensed', sans-serif;
    --corps: 'Barlow', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    --r: 18px;
}
* { box-sizing: border-box; margin: 0; }
html { -webkit-text-size-adjust: 100%; }
body { background: var(--fond); color: var(--encre); font-family: var(--corps); font-weight: 500; font-size: 17px; line-height: 1.6; -webkit-font-smoothing: antialiased; }
a { color: var(--bleu); }
img { max-width: 100%; }
a:focus-visible, button:focus-visible { outline: 3px solid var(--bleu); outline-offset: 3px; }

/* ---- la maison, sur une ligne ---- */
.haut { background: var(--bleu); color: var(--blanc); display: flex; align-items: center; justify-content: center; gap: 14px; padding: 14px 20px; }
.haut .logo { height: 40px; width: auto; display: block; }
.haut .nom-maison { font-family: var(--cond); font-size: 21px; letter-spacing: 2.5px; line-height: 1.1; display: block; }
.haut .devise { display: block; font-size: 14px; color: #C9D5EC; letter-spacing: .3px; margin-top: 1px; }

/* ---- la colonne ---- */
.page { max-width: 560px; margin: 0 auto; padding: 14px 14px 36px; display: grid; gap: 14px; }
.carte { background: var(--blanc); border-radius: var(--r); box-shadow: 0 1px 2px rgba(27, 36, 55, .06), 0 8px 28px rgba(27, 36, 55, .07); }

/* ---- voir la pièce ---- */
.photo-carte { overflow: hidden; }
.visuel { height: min(86vw, 400px); display: flex; align-items: center; justify-content: center; padding: 16px; background: #FBFAF7; }
.visuel img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
.vignettes { display: flex; gap: 10px; padding: 4px 16px 16px; background: #FBFAF7; overflow-x: auto; scrollbar-width: none; }
.vignettes::-webkit-scrollbar { display: none; }
.vignettes button { flex: none; width: 60px; height: 60px; padding: 4px; border: 2px solid var(--trait); border-radius: 12px; background: var(--blanc); cursor: pointer; }
.vignettes button.active { border-color: var(--bleu); }
.vignettes img { width: 100%; height: 100%; object-fit: contain; display: block; }
.sans-photo { padding: 54px 20px; text-align: center; color: var(--gris); background: #FBFAF7; font-size: 16px; }
.sans-photo svg { display: block; margin: 0 auto 10px; }

/* ---- la reconnaître, connaître le prix ---- */
.identite { padding: 22px 22px 24px; }
.sur-titre { font-family: var(--cond); font-size: 16px; letter-spacing: 2.5px; text-transform: uppercase; color: var(--bleu); }
h1.piece { font-family: var(--cond); font-size: 32px; line-height: 1.1; color: var(--encre); margin-top: 6px; font-weight: 700; }
.wolof { font-size: 20px; line-height: 1.3; color: var(--gris); margin-top: 8px; }

.prix { margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--trait); display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
.prix .montant { font-family: var(--cond); font-size: 44px; line-height: 1; color: var(--bleu); }
.prix .ancien { font-size: 19px; color: var(--gris); text-decoration: line-through; }
.prix .badge { align-self: center; background: var(--promo); color: var(--blanc); font-family: var(--cond); font-size: 17px; letter-spacing: .5px; border-radius: 8px; padding: 4px 10px; line-height: 1.2; }
.prix .demande { font-family: var(--cond); font-size: 30px; line-height: 1.1; color: var(--encre); }

/* ---- contacter, venir : le cœur de la page ---- */
.contact { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.bouton { display: flex; align-items: center; justify-content: center; gap: 12px; min-height: 60px; padding: 12px 16px; border-radius: 16px; font-family: var(--cond); font-size: 22px; letter-spacing: .4px; text-decoration: none; line-height: 1.1; text-align: center; -webkit-tap-highlight-color: transparent; }
.bouton svg { flex: none; }
.bouton:active { transform: translateY(1px); }
.b-wa { grid-column: 1 / -1; background: var(--wa); color: var(--blanc); box-shadow: 0 6px 18px rgba(37, 211, 102, .30); }
.b-tel { background: var(--bleu); color: var(--blanc); }
.b-maps { background: var(--blanc); color: var(--bleu); border: 2px solid var(--bleu); }

/* ---- montrer au comptoir ---- */
.comptoir { padding: 18px 22px 20px; }
.comptoir .titre { font-family: var(--cond); font-size: 14px; letter-spacing: 2px; text-transform: uppercase; color: var(--gris); }
.comptoir .ref { font-family: var(--cond); font-size: 24px; line-height: 1.1; color: var(--bleu); letter-spacing: .8px; margin-top: 4px; }
.comptoir .barre { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--trait); }
.comptoir .ean { font-family: var(--cond); font-size: 18px; line-height: 1.1; letter-spacing: 1.5px; color: var(--encre); margin-top: 4px; }

/* ---- les détails utiles ---- */
.details { padding: 8px 22px 10px; }
.details .rang { padding: 14px 0; border-bottom: 1px solid var(--trait); }
.details .rang:last-child { border-bottom: 0; }
.details .k { font-family: var(--cond); font-size: 14px; letter-spacing: 2px; text-transform: uppercase; color: var(--gris); }
.details .v { font-size: 18px; line-height: 1.5; margin-top: 2px; overflow-wrap: anywhere; }
.details .v.texte { font-size: 17px; color: #2A3448; white-space: pre-line; }

/* ---- introuvable, mais accueilli ---- */
.introuvable { padding: 30px 22px; text-align: center; }
.introuvable h1 { font-family: var(--cond); font-size: 30px; line-height: 1.1; color: var(--encre); }
.introuvable p { margin-top: 10px; color: var(--gris); font-size: 17px; }

/* ---- le geste de la maison ---- */
.slogan { display: block; width: 68%; max-width: 300px; margin: 14px auto 0; }

.liens { display: flex; justify-content: center; flex-wrap: wrap; gap: 4px 28px; font-size: 16px; }
.liens a { display: inline-flex; align-items: center; min-height: 44px; color: var(--bleu); text-decoration: underline; text-underline-offset: 4px; text-decoration-thickness: 1.5px; }
.reseaux { display: flex; justify-content: center; gap: 8px; }
.reseaux a { display: inline-flex; align-items: center; justify-content: center; width: 46px; height: 46px; border-radius: 50%; color: var(--gris); }
.reseaux a:hover { background: var(--bleu-voile); color: var(--bleu); }

/* ---- pied ---- */
.pied { background: var(--bleu-nuit); color: #C9D5EC; text-align: center; padding: 28px 20px 34px; font-size: 15px; line-height: 1.75; }
.pied .maison { font-family: var(--cond); font-size: 20px; letter-spacing: 2px; color: var(--blanc); }
.pied .devise { margin-bottom: 8px; }
.pied a { color: var(--blanc); }
.pied .legal { margin-top: 10px; font-size: 13px; color: #8FA3C8; }

@media (min-width: 640px) {
    .page { padding: 24px 16px 44px; gap: 16px; }
    .haut { padding: 18px 20px; }
    .haut .logo { height: 48px; }
    .visuel { height: 420px; }
    h1.piece { font-size: 36px; }
    .contact { grid-template-columns: 1.4fr 1fr 1fr; }
    .b-wa { grid-column: auto; }
    /* Libellé long (« Demander le prix sur WhatsApp ») : WhatsApp garde toute la
       largeur, Appeler et Itinéraire se partagent la ligne du dessous. */
    .contact.longue { grid-template-columns: 1fr 1fr; }
    .contact.longue .b-wa { grid-column: 1 / -1; }
}
</style>
</head>
<body>

<header class="haut">
    <img class="logo" src="/image/logo-fpl-blanc.png" alt="FPL">
    <div>
        <span class="nom-maison">FOUTA POIDS LOURDS</span>
        <span class="devise">The Solution</span>
    </div>
</header>

<main class="page">
    <?php if ($piece): ?>

    <section class="carte photo-carte">
        <?php if ($photos !== []): ?>
        <div class="visuel"><img id="photo-principale" src="<?= fpl_e($photos[0]) ?>" alt="<?= fpl_e($piece['nom']) ?>"></div>
        <?php if (count($photos) > 1): ?>
        <div class="vignettes">
            <?php foreach ($photos as $i => $ph): ?>
            <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-src="<?= fpl_e($ph) ?>" aria-label="Photo <?= $i + 1 ?>"><img src="<?= fpl_e($ph) ?>" alt=""></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="sans-photo">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9AA6BC" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3.2"/><path d="M8 5l1-2h6l1 2"/></svg>
            Photo en cours de préparation
        </div>
        <?php endif; ?>
    </section>

    <section class="carte identite">
        <?php if (!empty($piece['marque_nom'])): ?>
        <div class="sur-titre"><?= fpl_e($piece['marque_nom']) ?></div>
        <?php endif; ?>
        <h1 class="piece"><?= fpl_e($piece['nom']) ?></h1>
        <?php if (!empty($piece['nom_wolof'])): ?>
        <div class="wolof" lang="wo"><?= fpl_e($piece['nom_wolof']) ?></div>
        <?php endif; ?>

        <div class="prix">
            <?php if ($prix > 0 && $promo > 0 && $promo < $prix): ?>
            <span class="montant"><?= fpl_e($fcfa($promo)) ?></span>
            <span class="ancien"><?= fpl_e($fcfa($prix)) ?></span>
            <span class="badge">−<?= (int) round(100 * ($prix - $promo) / $prix) ?> %</span>
            <?php elseif ($prix > 0): ?>
            <span class="montant"><?= fpl_e($fcfa($prix)) ?></span>
            <?php else: ?>
            <span class="demande">Prix sur demande</span>
            <?php endif; ?>
        </div>
    </section>

    <?php else: ?>

    <section class="carte introuvable">
        <h1>Pièce introuvable</h1>
        <p>Cette référence ne correspond à aucune pièce de notre catalogue. Notre équipe reste à votre disposition.</p>
    </section>

    <?php endif; ?>

    <nav class="contact<?= $wa_libelle === 'WhatsApp' ? '' : ' longue' ?>" aria-label="Nous joindre">
        <?php if ($wa_url !== ''): ?>
        <a class="bouton b-wa" href="<?= fpl_e($wa_url) ?>">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
            <?= fpl_e($wa_libelle) ?>
        </a>
        <?php endif; ?>
        <a class="bouton b-tel" href="<?= fpl_e($coords['telephone_href']) ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
            Appeler
        </a>
        <a class="bouton b-maps" href="<?= fpl_e($maps_url) ?>" target="_blank" rel="noopener">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
            Itinéraire
        </a>
    </nav>

    <?php if ($piece): ?>

    <section class="carte comptoir">
        <div class="titre">Référence FPL</div>
        <div class="ref"><?= fpl_e($ref_aeree) ?></div>
        <div class="barre">
            <div class="titre">Code-barres</div>
            <div class="ean"><?= fpl_e($ean13) ?></div>
        </div>
    </section>

    <?php if ($a_details): ?>
    <section class="carte details">
        <?php if ($modeles !== []): ?>
        <div class="rang"><div class="k">Compatible avec</div><div class="v"><?= fpl_e(implode(', ', $modeles)) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($piece['reference_oem'])): ?>
        <div class="rang"><div class="k">Référence OEM</div><div class="v"><?= fpl_e($piece['reference_oem']) ?></div></div>
        <?php endif; ?>
        <?php if ($a_description): ?>
        <div class="rang"><div class="k">Description</div><div class="v texte"><?= fpl_e($piece['description']) ?></div></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php endif; ?>

    <img class="slogan" src="/image/vitrine/slogan-manuscrit.png" alt="Conduire avec assurance — ndakh jombtukay you worr">

    <nav class="liens" aria-label="Aller plus loin">
        <a href="/produits.php">Voir le catalogue</a>
        <a href="?vcard=1">Enregistrer notre contact</a>
    </nav>

    <?php if (!empty($social['facebook']) || !empty($social['linkedin']) || !empty($social['tiktok'])): ?>
    <div class="reseaux">
        <?php if (!empty($social['facebook'])): ?>
        <a href="<?= fpl_e($social['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7h2.4l.4-2.9h-2.8V9.2c0-.8.3-1.4 1.5-1.4h1.4V5.2c-.2 0-1.1-.1-2.1-.1-2.1 0-3.6 1.3-3.6 3.7v2.3H8.3V14h2.4v7h2.8z"/></svg></a>
        <?php endif; ?>
        <?php if (!empty($social['linkedin'])): ?>
        <a href="<?= fpl_e($social['linkedin']) ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M6.5 8.5H3.8V21h2.7V8.5zM5.1 3.5a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2zM21 13.4c0-3-1.6-4.4-3.8-4.4-1.7 0-2.5 1-2.9 1.6V8.5h-2.7V21h2.7v-6.8c0-1.2.8-2 1.9-2s1.8.8 1.8 2V21H21v-7.6z"/></svg></a>
        <?php endif; ?>
        <?php if (!empty($social['tiktok'])): ?>
        <a href="<?= fpl_e($social['tiktok']) ?>" target="_blank" rel="noopener" aria-label="TikTok"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 3c.4 2.1 1.8 3.6 3.9 3.9v2.8c-1.5 0-2.8-.5-3.9-1.3v6.1c0 3.4-2.4 5.5-5.4 5.5S5.8 17.9 5.8 15c0-3.1 2.5-5.2 5.6-5V13c-1.4-.4-2.8.4-2.8 2 0 1.4 1 2.3 2.3 2.3 1.4 0 2.4-1 2.4-2.7V3h3.3z"/></svg></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<footer class="pied">
    <div class="maison"><?= fpl_e($coords['nom']) ?></div>
    <div class="devise"><?= fpl_e($coords['tagline']) ?></div>
    <div><?= fpl_e($coords['adresse']) ?></div>
    <div><a href="<?= fpl_e($coords['telephone_href']) ?>"><?= fpl_e($coords['telephone']) ?></a><?= empty($coords['telephone2']) ? '' : fpl_e(' · ' . $coords['telephone2']) ?></div>
    <div><a href="<?= fpl_e($coords['site']) ?>"><?= fpl_e(preg_replace('#^https?://#', '', $coords['site'])) ?></a></div>
    <div><a href="mailto:<?= fpl_e($coords['email']) ?>"><?= fpl_e($coords['email']) ?></a></div>
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
