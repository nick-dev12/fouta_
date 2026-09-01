<?php
/**
 * MODIFIER UNE PIÈCE — la fiche complète en trois temps : l'IDENTITÉ (nom,
 * marque, modèles à cocher, génération au modèle unique, références,
 * taille, couleur, prix, description), l'EMPLACEMENT (la cascade nommée de
 * ce dépôt) et les PHOTOS (principale cochée, retraits, photo d'étiquette).
 * Programmation procédurale uniquement
 *
 * PORTAGE FIDÈLE de fpl_natif/admin/piece-modifier.php (23/08/2026 au soir) :
 * le squelette — form-card, blocs, field-row, photo-grid — est celui de FPL
 * natif. Le MOTEUR reste celui de ce dépôt : process_update_produit()
 * (controllers/controller_produits.php), avec ses noms de champs. Deux
 * traductions se font ICI, avant d'appeler le contrôleur :
 *   - les photos : l'écran FPL coche « Principale » et « Retirer » ; le
 *     contrôleur attend `images_to_keep[]` ordonné (première = principale) —
 *     la conversion est faite en PHP, sans dépendre du JavaScript ;
 *   - le rangement : un seul menu « Catégorie › Rayon » comme chez FPL ; le
 *     contrôleur attend `categorie_id` + `sous_categorie_id` séparés.
 * Les apports de Fouta restent : cascade d'entrepôt, champs personnalisés,
 * variantes et options (dans leur état actuel : non affichés sur cette page,
 * mais leurs valeurs voyagent pour n'être jamais perdues), statut trois états.
 * La page d'avant est conservée telle quelle : modifier-fouta-origine.php.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../includes/fouta_upload_limits.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';

// Modifier, c'est écrire : le compte restreint n'y a pas accès.
if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($produit_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_variantes.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_fournisseurs.php';
require_once __DIR__ . '/../../models/model_marques.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';
require_once __DIR__ . '/../../includes/produit_emplacement_entrepot.php';

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    $_SESSION['success_message'] = 'Cette pièce n\'existe plus.';
    header('Location: index.php');
    exit;
}
$variantes = get_variantes_by_produit($produit_id);

/* ---------------------------------------------------------------------
 * LES PHOTOS EXISTANTES — la liste vient de la colonne `images` (JSON),
 * la première est la principale (convention de ce dépôt).
 * ------------------------------------------------------------------- */
$images_existantes = [];
if (!empty($produit['images'])) {
    $dec = json_decode((string) $produit['images'], true);
    if (is_array($dec)) {
        $images_existantes = array_values(array_filter(array_map('strval', $dec)));
    }
}
if ($images_existantes === [] && !empty($produit['image_principale'])) {
    $images_existantes = [(string) $produit['image_principale']];
}

/* ---------------------------------------------------------------------
 * TRADUCTION DU POST « écran FPL » → « contrôleur Fouta », AVANT l'appel.
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Le rangement : un seul menu (valeur "sc-<id>" ou "cat-<id>")
    if (isset($_POST['rangement'])) {
        $rang = (string) $_POST['rangement'];
        if (preg_match('/^sc-(\d+)$/', $rang, $m)) {
            $sc = get_sous_categorie_by_id((int) $m[1]);
            if ($sc) {
                $_POST['sous_categorie_id'] = (string) (int) $sc['id'];
                $_POST['categorie_id'] = (string) (int) $sc['categorie_id'];
            }
        } elseif (preg_match('/^cat-(\d+)$/', $rang, $m)) {
            $_POST['categorie_id'] = (string) (int) $m[1];
            $_POST['sous_categorie_id'] = '';
        }
    }
    // 2. Les photos : « Principale » cochée + « Retirer » cochés →
    //    images_to_keep[] ordonné, la principale d'abord.
    $retirees = isset($_POST['photos_retirees']) && is_array($_POST['photos_retirees'])
        ? array_map('strval', $_POST['photos_retirees']) : [];
    $principale_choisie = isset($_POST['image_principale']) ? (string) $_POST['image_principale'] : '';
    $gardees = array_values(array_filter($images_existantes, function ($img) use ($retirees) {
        return !in_array($img, $retirees, true);
    }));
    if ($principale_choisie !== '' && in_array($principale_choisie, $gardees, true)) {
        $gardees = array_merge([$principale_choisie], array_values(array_diff($gardees, [$principale_choisie])));
    }
    $_POST['images_to_keep'] = $gardees;
}

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_update_produit($produit_id);
if (isset($result['success']) && $result['success']) {
    // La photo d'étiquette retirée (sans remplaçante) : le contrôleur ne
    // gère pas ce cas — on efface ICI la seule colonne concernée, rien d'autre.
    if (!empty($_POST['photo_etiquette_retiree'])
        && produits_has_column('image_etiquette_fpl')
        && (!isset($_FILES['image_etiquette_fpl']) || (int) ($_FILES['image_etiquette_fpl']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
        $ancienne = trim((string) ($produit['image_etiquette_fpl'] ?? ''));
        if ($ancienne !== '') {
            if (function_exists('image_optimizer_delete_with_variants')) {
                image_optimizer_delete_with_variants($ancienne);
            }
            try {
                $st = $db->prepare('UPDATE produits SET image_etiquette_fpl = NULL, date_modification = NOW() WHERE id = :id');
                $st->execute(['id' => $produit_id]);
            } catch (PDOException $e) {
                error_log('Retrait de la photo d\'étiquette : ' . $e->getMessage());
            }
        }
    }
    $_SESSION['success_message'] = $result['message'];
    $retour_cat = (int) ($_POST['categorie_id'] ?? $produit['categorie_id']);
    $retour_sc = (int) ($_POST['sous_categorie_id'] ?? 0);
    header('Location: index.php?categorie_id=' . $retour_cat
        . ($retour_sc > 0 ? '&sous_categorie_id=' . $retour_sc : ''));
    exit;
}
$erreur_generale = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($result['message']) && $result['message'] !== '') {
    $erreur_generale = (string) $result['message'];
    // La fiche rechargée reflète le POST refusé
    $produit = array_merge($produit, [
        'nom' => isset($_POST['nom']) ? (string) $_POST['nom'] : $produit['nom'],
        'description' => isset($_POST['description']) ? (string) $_POST['description'] : $produit['description'],
        'reference_oem' => isset($_POST['reference_oem']) ? (string) $_POST['reference_oem'] : $produit['reference_oem'],
        'reference_fournisseur' => isset($_POST['reference_fournisseur']) ? (string) $_POST['reference_fournisseur'] : $produit['reference_fournisseur'],
    ]);
}

/* ---------------------------------------------------------------------
 * LES DONNÉES DU FORMULAIRE
 * ------------------------------------------------------------------- */
$wizard = marques_pour_wizard();
$couleurs = fpl_couleurs();
$fournisseurs = produits_has_column('fournisseur_id') ? get_all_fournisseurs_ordered_by_nom() : [];
$categories = get_all_categories();
$sous_categories_all = (produits_has_column('sous_categorie_id') && sous_categories_table_ok())
    ? get_all_sous_categories_with_categorie_nom() : [];
$emplacement_form_vals = produit_emplacement_form_values_for_form(
    $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [], $produit);
$pf_custom_vals = produit_formulaire_valeurs_custom($produit_id);
$pf_post_source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : null;

// Les modèles cochés : le POST refusé d'abord, sinon les compatibilités en base
$modeles_coches = isset($_POST['modeles']) && is_array($_POST['modeles'])
    ? array_map('strval', $_POST['modeles'])
    : array_map('strval', function_exists('produit_modeles_ids') ? produit_modeles_ids($produit_id) : []);
// Une pièce d'avant le pivot : son modèle principal vaut compatibilité
if ($modeles_coches === [] && !empty($produit['modele_id'])) {
    $modeles_coches = [(string) (int) $produit['modele_id']];
}

// La couleur : la première teinte du JSON, rendue à son nom
$couleur_hex_actuelle = '';
$couleurs_raw = trim((string) ($produit['couleurs'] ?? ''));
if ($couleurs_raw !== '' && $couleurs_raw !== '[]') {
    $dec = json_decode($couleurs_raw, true);
    if (is_array($dec) && isset($dec[0]) && is_string($dec[0])) {
        $couleur_hex_actuelle = strtoupper($dec[0]);
    }
}
$couleur_nom_actuelle = '';
foreach ($couleurs as $cn => $chex) {
    if (strtoupper($chex) === $couleur_hex_actuelle) {
        $couleur_nom_actuelle = $cn;
        break;
    }
}

// La taille : rendue lisible si elle est au format JSON [{v,s}]
$taille_affichee = trim((string) ($produit['taille'] ?? ''));
if ($taille_affichee !== '' && $taille_affichee !== '[]') {
    $dec = json_decode($taille_affichee, true);
    if (is_array($dec)) {
        $vals = [];
        foreach ($dec as $t) {
            $v = is_array($t) ? trim((string) ($t['v'] ?? '')) : trim((string) $t);
            if ($v !== '') {
                $vals[] = $v;
            }
        }
        $taille_affichee = implode(', ', $vals);
    }
} elseif ($taille_affichee === '[]') {
    $taille_affichee = '';
}

$nombre = function ($v) {
    return ($v === null || $v === '') ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
};

if (!function_exists('get_public_root_uri_path')) {
    require_once __DIR__ . '/../../includes/site_url.php';
}
$upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';

$nom_affiche = fpl_texte((string) $produit['nom']);
$cat_id_courante = (int) ($produit['categorie_id'] ?? 0);
$sc_id_courante = (int) ($produit['sous_categorie_id'] ?? 0);
$retour_catalogue = 'index.php?categorie_id=' . $cat_id_courante
    . ($sc_id_courante > 0 ? '&sous_categorie_id=' . $sc_id_courante : '');

$fpl_titre_page = 'Modifier « ' . $nom_affiche . ' »';
$fpl_retour_page = $retour_catalogue;

$voit = function ($slug) { return pf_champ_visible($slug); };
/* VOIR SANS TOUCHER (31/08) : un champ que le profil a le droit de LIRE mais
 * pas d'ÉCRIRE — le prix, pour qui vend — s'affiche avec sa valeur, grisé, et
 * n'est pas envoyé. Le contrôleur le refuserait de toute façon : ceci n'est
 * que la politesse de l'écran. */
$fige = function ($slug) { return pf_champ_visible($slug) && !pf_champ_modifiable($slug); };
$attr_fige = function ($slug) use ($fige) {
    return $fige($slug) ? ' disabled title="Lecture seule : seul le responsable stock modifie ce champ"' : '';
};
$note_figee = function ($slug) use ($fige) {
    return $fige($slug) ? '<span class="hint-inline">lecture seule</span>' : '';
};
// Le lien vers Marques & modèles ne se montre qu'à qui a le droit d'ouvrir la page
$peut_gerer_marques = function_exists('admin_route_is_allowed')
    && admin_route_is_allowed((string) ($_SESSION['admin_role'] ?? ''), 'parametres/logos.php');
$affiche_prix = $voit('prix');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier « <?php echo e($nom_affiche); ?> » — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

<form method="POST" action="" enctype="multipart/form-data" id="form-piece-modifier">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">
  <?php // Le témoin : les modèles cochés ont bien été envoyés par CET écran ?>
  <input type="hidden" name="modeles_envoyes" value="1">

  <div class="form-card">

    <?php if ($erreur_generale !== '') : ?>
      <div class="alert warn" style="margin-bottom:var(--s3)">
        <?php foreach (explode('<br>', $erreur_generale) as $err) : if (trim($err) === '') { continue; } ?>
          <div><?php echo e(strip_tags($err)); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Identité -->
    <div class="form-block">
      <h3>
        <?php echo fpl_icone('tool', 14); ?> Identité
        <span class="hint-inline"><span class="chip-code"><?php echo e(fpl_code_afficher((string) ($produit['identifiant_interne'] ?? ''))); ?></span></span>
      </h3>

      <div class="field">
        <label for="nom">Nom de la pièce <span class="req">*</span></label>
        <input type="text" id="nom" name="nom" value="<?php echo e($nom_affiche); ?>" required minlength="2">
      </div>

      <?php if (produits_has_column('nom_wolof')) : ?>
        <div class="field">
          <label for="nom_wolof">Nom en wolof
            <span class="hint-inline">le nom qu'on demande au comptoir — il titre l'étiquette</span>
          </label>
          <input type="text" id="nom_wolof" name="nom_wolof"
                 value="<?php echo e((string) ($produit['nom_wolof'] ?? '')); ?>" placeholder="Ex. XOTTU SETTU">
        </div>
      <?php endif; ?>

      <div class="field-row three">
        <!-- Liste STRICTE : marques et modèles du référentiel -->
        <div class="field">
          <label for="marque_id">Marque</label>
          <select id="marque_id" name="marque_id">
            <option value="">— Aucune —</option>
            <?php foreach ($wizard['marques'] as $m) : ?>
              <option value="<?php echo (int) $m['id']; ?>" <?php echo (int) ($produit['marque_id'] ?? 0) === (int) $m['id'] ? 'selected' : ''; ?>><?php echo fpl_e($m['nom']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="models-btn">Modèles compatibles <span class="hint-inline">plusieurs possibles</span></label>
          <div class="multi-modeles" id="models-box">
            <button type="button" class="mm-btn" id="models-btn" disabled>
              <span class="mm-resume" id="models-resume">— Choisir la marque d'abord —</span>
              <span class="mm-chevron"><?php echo fpl_icone('chevron-down', 14); ?></span>
            </button>
            <div class="mm-panel" id="models-panel" hidden></div>
          </div>
        </div>
        <?php if ($voit('reference_fournisseur') && produits_has_column('reference_fournisseur')) : ?>
        <div class="field">
          <label for="reference_fournisseur">Réf. fournisseur</label>
          <input type="text" id="reference_fournisseur" name="reference_fournisseur" maxlength="120"
                 value="<?php echo e((string) ($produit['reference_fournisseur'] ?? '')); ?>">
        </div>
        <?php endif; ?>
      </div>

      <?php if ($voit('fournisseur_id') && (produits_has_column('fournisseur_id') || produits_has_column('nom_fournisseur'))) : ?>
        <div class="field-row<?php echo ($voit('prix_achat') && produits_has_column('prix_achat')) ? ' three' : ''; ?>">
          <div class="field">
            <label for="fournisseur_id">Fournisseur <span class="hint-inline">optionnel</span></label>
            <?php if ($fournisseurs !== []) : ?>
              <select id="fournisseur_id" name="fournisseur_id"<?php echo $attr_fige('fournisseur_id'); ?>>
                <option value="">— Aucun —</option>
                <?php foreach ($fournisseurs as $f) : ?>
                  <option value="<?php echo (int) $f['id']; ?>" <?php echo (int) ($produit['fournisseur_id'] ?? 0) === (int) $f['id'] ? 'selected' : ''; ?>><?php echo fpl_e($f['nom']); ?></option>
                <?php endforeach; ?>
                <option value="libre" <?php echo empty($produit['fournisseur_id']) && !empty($produit['nom_fournisseur']) ? 'selected' : ''; ?>>— Un autre, à saisir —</option>
              </select>
            <?php else : ?>
              <input type="text" id="nom_fournisseur_seul" name="nom_fournisseur"
                     value="<?php echo e((string) ($produit['nom_fournisseur'] ?? '')); ?>">
            <?php endif; ?>
          </div>
          <?php if ($fournisseurs !== []) : ?>
            <div class="field" id="bloc-fournisseur-libre" <?php echo empty($produit['fournisseur_id']) && !empty($produit['nom_fournisseur']) ? '' : 'hidden'; ?>>
              <label for="nom_fournisseur">Son nom</label>
              <input type="text" id="nom_fournisseur" name="nom_fournisseur" maxlength="150"
                     value="<?php echo e((string) ($produit['nom_fournisseur'] ?? '')); ?>" placeholder="Nom du fournisseur">
            </div>
          <?php endif; ?>
          <?php if ($voit('prix_achat') && produits_has_column('prix_achat')) : ?>
            <div class="field">
              <label for="prix_achat">Prix grossiste <span class="hint-inline">FCFA</span> <?php echo $note_figee('prix_achat'); ?></label>
              <input type="number" id="prix_achat" name="prix_achat" min="0" step="any"
                     value="<?php echo e($nombre($produit['prix_achat'] ?? null)); ?>"<?php echo $attr_fige('prix_achat'); ?>>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($affiche_prix) : ?>
        <div class="field-row three">
          <div class="field">
            <label for="prix">Prix de vente <span class="hint-inline">FCFA</span> <?php echo $note_figee('prix'); ?></label>
            <input type="number" id="prix" name="prix" min="0" step="any"
                   value="<?php echo e($nombre($produit['prix'] ?? null)); ?>"<?php echo $attr_fige('prix'); ?>>
          </div>
          <?php if ($voit('prix_promotion')) : ?>
            <div class="field">
              <label for="prix_promotion">Prix promotionnel <?php echo $note_figee('prix_promotion'); ?></label>
              <input type="number" id="prix_promotion" name="prix_promotion" min="0" step="any"
                     value="<?php echo e($nombre($produit['prix_promotion'] ?? null)); ?>"<?php echo $attr_fige('prix_promotion'); ?>>
            </div>
          <?php endif; ?>
          <?php if (produits_has_column('prix_entreprise')) : ?>
            <div class="field">
              <label for="prix_entreprise">Prix entreprise</label>
              <input type="number" id="prix_entreprise" name="prix_entreprise" min="0" step="any"
                     value="<?php echo e($nombre($produit['prix_entreprise'] ?? null)); ?>">
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($voit('statut')) : ?>
        <!-- Retirer une pièce de la vente sans la supprimer -->
        <div class="field-row">
          <div class="field">
            <label for="statut">Statut</label>
            <?php $statut_courant = in_array(($produit['statut'] ?? ''), ['actif', 'inactif', 'rupture_stock'], true) ? $produit['statut'] : 'actif'; ?>
            <select id="statut" name="statut">
              <option value="actif" <?php echo $statut_courant === 'actif' ? 'selected' : ''; ?>>Actif — la pièce se vend</option>
              <option value="inactif" <?php echo $statut_courant === 'inactif' ? 'selected' : ''; ?>>Inactif — retirée de la vente, sans être supprimée</option>
              <option value="rupture_stock" <?php echo $statut_courant === 'rupture_stock' ? 'selected' : ''; ?>>Rupture de stock</option>
            </select>
            <div class="help">À stock nul, la pièce passe d'elle-même en rupture de stock.</div>
          </div>
          <div class="field">
            <label for="stock">Stock</label>
            <input type="number" id="stock" name="stock" min="0" step="1"
                   value="<?php echo (int) ($produit['stock'] ?? 0); ?>">
          </div>
          <?php /* LE SEUIL DE CETTE PIÈCE (31/08) : chaque pièce a le sien.
                   L'alerte parle dès que le stock lui est inférieur OU égal,
                   et tant qu'il n'est pas remonté au-dessus. Case vide : le
                   logiciel ne dit rien sur cette pièce. Zéro : préviens-moi
                   seulement quand il n'y en a plus du tout. */ ?>
          <?php if ($voit('seuil_alerte') && produits_has_column('seuil_alerte')) : ?>
            <div class="field">
              <label for="seuil_alerte">Seuil d'alerte <span class="hint-inline">prévient sous ce nombre, ou à ce nombre</span> <?php echo $note_figee('seuil_alerte'); ?></label>
              <input type="number" id="seuil_alerte" name="seuil_alerte" min="0" step="1"
                     value="<?php echo $produit['seuil_alerte'] !== null && $produit['seuil_alerte'] !== '' ? (int) $produit['seuil_alerte'] : ''; ?>"
                     placeholder="aucun"<?php echo $attr_fige('seuil_alerte'); ?>>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="field-row">
        <?php if (produits_has_column('reference_oem')) : ?>
        <div class="field">
          <label for="reference_oem">Réf. OEM (constructeur)</label>
          <input type="text" id="reference_oem" name="reference_oem" maxlength="120"
                 value="<?php echo e((string) ($produit['reference_oem'] ?? '')); ?>">
        </div>
        <?php endif; ?>
        <?php if ($voit('taille') && produits_has_column('taille')) : ?>
        <div class="field">
          <label for="taille">Taille / dimensions <span class="hint-inline">optionnel</span></label>
          <input type="text" id="taille" name="taille" maxlength="120"
                 value="<?php echo e($taille_affichee); ?>" placeholder="Ex. 60 x 105, ø120xø140x13">
        </div>
        <?php endif; ?>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="rangement">Rangement <span class="req">*</span></label>
          <select id="rangement" name="rangement" required>
            <?php foreach ($categories as $c) : ?>
              <?php
                $cid = (int) $c['id'];
                $cnom = fpl_texte((string) $c['nom']);
                $rayons_de_c = array_values(array_filter($sous_categories_all, function ($sc) use ($cid) {
                    return (int) $sc['categorie_id'] === $cid;
                }));
              ?>
              <option value="cat-<?php echo $cid; ?>" <?php echo ($cat_id_courante === $cid && $sc_id_courante <= 0) ? 'selected' : ''; ?>>
                <?php echo e($cnom); ?> — sans rayon
              </option>
              <?php foreach ($rayons_de_c as $sc) : ?>
                <option value="sc-<?php echo (int) $sc['id']; ?>" <?php echo $sc_id_courante === (int) $sc['id'] ? 'selected' : ''; ?>>
                  <?php echo e($cnom); ?> › <?php echo fpl_e($sc['nom']); ?>
                </option>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- GÉNÉRATION ET ANNÉES — deux champs distincts. -->
      <div class="field-row">
        <div class="field">
          <label for="generation_id">Génération <span class="hint-inline">optionnel</span></label>
          <select id="generation_id" name="generation_id">
            <option value="">— Choisir le modèle d'abord —</option>
          </select>
          <?php if ($peut_gerer_marques) : ?>
            <div class="help">
              Génération absente ? Ajoutez-la au modèle depuis
              <a href="../parametres/logos.php?tab=marques" target="_blank">Marques &amp; modèles</a>.
            </div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label for="generation_annee">Année</label>
          <input type="text" id="generation_annee" readonly tabindex="-1"
                 class="champ-herite" value="—"
                 title="Les années viennent de la génération choisie">
          <div class="help">Renseignée par la génération — elle se corrige sur le modèle.</div>
        </div>
      </div>

      <div class="field-row">
        <?php if (produits_has_column('position_montage')) : ?>
        <div class="field">
          <label for="position_montage">Position <span class="hint-inline">optionnel</span></label>
          <select id="position_montage" name="position_montage">
            <option value="">— Non applicable —</option>
            <option value="gauche" <?php echo ($produit['position_montage'] ?? '') === 'gauche' ? 'selected' : ''; ?>>Gauche</option>
            <option value="droite" <?php echo ($produit['position_montage'] ?? '') === 'droite' ? 'selected' : ''; ?>>Droite</option>
          </select>
        </div>
        <?php endif; ?>
        <?php if ($voit('couleurs')) : ?>
        <div class="field">
          <label for="couleur_nom">Couleur</label>
          <select id="couleur_nom" name="couleur_nom">
            <option value="">— Aucune —</option>
            <?php foreach ($couleurs as $c => $hex) : ?>
              <option value="<?php echo e($c); ?>" data-color="<?php echo e($hex); ?>" <?php echo $couleur_nom_actuelle === $c ? 'selected' : ''; ?>><?php echo e($c); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="couleurs" id="couleurs-hidden"
                 value="<?php echo e($couleurs_raw !== '[]' ? $couleurs_raw : ''); ?>">
        </div>
        <?php endif; ?>
      </div>

      <!-- LES CHAMPS AJOUTÉS À LA FICHE (Paramètres → Champs de la fiche) -->
      <div class="fpl-champs-fouta">
        <?php produit_formulaire_render_champs_custom('info', $pf_custom_vals, $pf_post_source); ?>
        <?php produit_formulaire_render_champs_custom('prix', $pf_custom_vals, $pf_post_source); ?>
      </div>

      <div class="field">
        <label>
          Description
          <span class="badge blue" style="font-size:11px; margin-left:6px">
            <?php echo fpl_icone('sparkles', 10); ?> automatique
          </span>
        </label>
        <?php /* PLUS DE SAISIE (24/08) : la description se compose toute seule
                 — marque, modèle principal, réf. OEM — ou se reprend d'une
                 pièce portant la même référence. Celle déjà en base reste
                 telle quelle tant qu'on ne touche ni au véhicule ni aux
                 références. */ ?>
        <div id="desc-preview" class="desc-preview<?php echo trim((string) ($produit['description'] ?? '')) !== '' ? ' filled' : ''; ?>"><?php
          $desc_actuelle = trim((string) ($produit['description'] ?? ''));
          echo $desc_actuelle !== '' ? fpl_e($desc_actuelle) : 'Composée à partir de la marque, du modèle et de la réf. OEM.';
        ?></div>
        <div class="help" id="desc-badge-txt"><?php echo $desc_actuelle !== '' ? 'Description enregistrée — elle se recompose si le véhicule ou les références changent.' : ''; ?></div>
        <input type="hidden" name="description" id="description" value="<?php echo fpl_e($desc_actuelle); ?>">
      </div>
    </div>

    <!-- Emplacement -->
    <div class="form-block">
      <h3>
        <?php echo fpl_icone('map-pin', 14); ?> Emplacement
        <span class="hint-inline">Où la pièce est rangée physiquement</span>
      </h3>

      <?php if ($voit('emplacement')) : ?>
        <div class="fpl-cascade-fouta">
          <?php produit_emplacement_render_form_fields($emplacement_form_vals); ?>
        </div>
      <?php endif; ?>
      <div class="fpl-champs-fouta">
        <?php produit_formulaire_render_champs_custom('ref', $pf_custom_vals, $pf_post_source); ?>
      </div>
    </div>

    <!-- Photos -->
    <div class="form-block">
      <h3>
        <?php echo fpl_icone('image', 14); ?> Photos
        <span class="hint-inline">Cochez l'image principale</span>
      </h3>

      <?php if ($images_existantes !== []) : ?>
        <div class="photo-grid" style="margin-bottom:var(--s3)">
          <?php foreach ($images_existantes as $i => $img) : ?>
            <label class="photo-item <?php echo $i === 0 ? 'main' : ''; ?>">
              <img src="<?php echo e($upload_base . ltrim($img, '/')); ?>" alt="">
              <div class="photo-actions">
                <span><input type="radio" name="image_principale" value="<?php echo e($img); ?>" <?php echo $i === 0 ? 'checked' : ''; ?>> Principale</span>
                <span><input type="checkbox" name="photos_retirees[]" value="<?php echo e($img); ?>"> Retirer</span>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($voit('images_produit')) : ?>
      <label class="dropzone" id="dz">
        <span class="dz-icon"><?php echo fpl_icone('image', 18); ?></span>
        <span class="dz-title">Ajouter des photos</span>
        <span class="dz-sub">JPG, PNG, WEBP</span>
        <input type="file" id="images" name="images_supplementaires[]" multiple accept="image/jpeg,image/png,image/webp">
      </label>
      <div class="previews" id="previews"></div>
      <?php endif; ?>

      <?php if ($voit('image_etiquette_fpl') && produits_has_column('image_etiquette_fpl')) : ?>
      <!-- LA PHOTO DE L'ÉTIQUETTE -->
      <div class="field" style="margin-top:var(--s3)">
        <label for="image_etiquette">
          <?php echo fpl_icone('tag', 13); ?> Photo pour l'étiquette
          <span class="hint-inline">optionnel</span>
        </label>
        <?php if (!empty($produit['image_etiquette_fpl'])) : ?>
          <div class="photo-grid" style="margin-bottom:var(--s2)">
            <label class="photo-item">
              <img src="<?php echo e($upload_base . ltrim((string) $produit['image_etiquette_fpl'], '/')); ?>" alt="">
              <div class="photo-actions">
                <span><input type="checkbox" name="photo_etiquette_retiree" value="1"> Retirer</span>
              </div>
            </label>
          </div>
        <?php endif; ?>
        <input type="file" id="image_etiquette" name="image_etiquette_fpl"
               accept="image/jpeg,image/png,image/webp">
        <div class="hint-inline">
          <?php echo !empty($produit['image_etiquette_fpl'])
              ? 'En choisir une autre remplace celle-ci.'
              : 'Sans elle, l\'étiquette prend la photo principale.'; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="fpl-champs-fouta">
        <?php produit_formulaire_render_champs_custom('media', $pf_custom_vals, $pf_post_source); ?>
      </div>
    </div>

    <?php /* LES VARIANTES ET LES OPTIONS D'ACHAT DE CE DÉPÔT — non affichées
             ici, comme sur la page d'avant (elles y étaient déjà masquées),
             mais leurs VALEURS voyagent avec le formulaire : sans elles, un
             enregistrement effacerait les variantes existantes. */ ?>
    <div hidden aria-hidden="true">
      <?php foreach ($variantes as $v) : ?>
        <input type="hidden" name="variantes_id[]" value="<?php echo (int) $v['id']; ?>">
        <input type="hidden" name="variantes_nom[]" value="<?php echo e((string) $v['nom']); ?>">
        <input type="hidden" name="variantes_prix[]" value="<?php echo e((string) $v['prix']); ?>">
        <input type="hidden" name="variantes_prix_promo[]" value="<?php echo e((string) ($v['prix_promotion'] ?? '')); ?>">
      <?php endforeach; ?>
      <?php $poids_raw = trim((string) ($produit['poids'] ?? '')); ?>
      <input type="hidden" name="poids" value="<?php echo e($poids_raw !== '[]' ? $poids_raw : ''); ?>">
      <?php /* L'unité voyage telle quelle, OCTET POUR OCTET : sans elle, le
               contrôleur remettrait « unité » par défaut — et réécrirait en
               douce les 508 lignes dont l'unité porte le double encodage
               historique (découvert le 24/08). */ ?>
      <input type="hidden" name="unite" value="<?php echo e((string) ($produit['unite'] ?? 'unité')); ?>">
      <?php produit_formulaire_render_champs_custom('options', $pf_custom_vals, $pf_post_source); ?>
    </div>
  </div>

  <div class="form-bar" style="max-width:860px">
    <button type="submit" class="btn btn-primary"><?php echo fpl_icone('save', 14); ?> Enregistrer</button>
    <a href="<?php echo e($retour_catalogue); ?>" class="btn btn-outline">Annuler</a>
    <span class="spacer"></span>
    <a href="ajuster-stock.php?id=<?php echo $produit_id; ?>" class="btn btn-outline"><?php echo fpl_icone('eye', 13); ?> Détail</a>
    <a href="ajuster-stock.php?id=<?php echo $produit_id; ?>#fpl-etiquette-print-root" class="btn btn-outline"><?php echo fpl_icone('tag', 13); ?> Étiquette</a>
  </div>
</form>

    </div><!-- .page-produits-admin -->

<style>
  .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(112px, 1fr)); gap: var(--s2); }
  .photo-item {
    border: 1px solid var(--line); border-radius: var(--r-sm); overflow: hidden;
    background: var(--surface); cursor: pointer; display: block;
  }
  .photo-item.main { border-color: var(--blue, var(--navy)); box-shadow: 0 0 0 2px var(--blue-tint); }
  .photo-item img { width: 100%; height: 74px; object-fit: cover; display: block; }
  .photo-actions {
    padding: 5px 7px; font-size: 11px; color: var(--slate);
    display: flex; flex-direction: column; gap: 2px;
  }
  .photo-actions span { display: flex; align-items: center; gap: 4px; }

  /* Les blocs de ce dépôt (cascade, champs ajoutés) dans le squelette FPL */
  .fpl-champs-fouta:empty, .fpl-cascade-fouta:empty { display: none; }
  .fpl-champs-fouta .pf-custom-fields { display: grid; grid-template-columns: 1fr 1fr; gap: var(--s3); margin-bottom: var(--s3); }
  .fpl-champs-fouta .form-group, .fpl-cascade-fouta .form-group { display: flex; flex-direction: column; gap: 5px; margin: 0 0 var(--s3); }
  .fpl-champs-fouta .form-group > label, .fpl-cascade-fouta .form-group > label {
    font-size: 13px; font-weight: 600; color: var(--slate); margin: 0;
  }
  .fpl-champs-fouta .form-hint, .fpl-cascade-fouta .form-hint { font-size: 12.5px; color: var(--slate-soft); }
  .fpl-cascade-fouta .pm-emplacement-form--referentiel {
    padding: 12px 14px; border-radius: var(--r); background: var(--surface); border: 1px solid var(--line);
  }
  .fpl-cascade-fouta .pm-emplacement-steps { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
  .fpl-cascade-fouta .pm-emplacement-step {
    display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px;
    background: var(--blue-tint); color: var(--blue-600);
    font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
  }
  .fpl-cascade-fouta .pm-emplacement-intro { margin: 0 0 8px; font-size: 12.5px; color: var(--slate); }
  .fpl-cascade-fouta .pm-emplacement-cascade { margin-top: 4px; padding-top: 8px; border-top: 1px dashed var(--line); }
  .fpl-cascade-fouta .pm-emplacement-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--s3); margin-bottom: 0; }
  .fpl-cascade-fouta .pm-emplacement-count { margin: 0 0 8px; padding: 5px 9px; border-radius: 6px; background: var(--blue-tint); color: var(--blue-600); font-size: 12.5px; font-weight: 600; }
  .fpl-cascade-fouta .pm-emplacement-count[hidden], .fpl-cascade-fouta .pm-emplacement-cascade[hidden], .fpl-cascade-fouta .pm-emplacement-apercu[hidden] { display: none !important; }
  .fpl-cascade-fouta .pm-emplacement-apercu { margin-top: 10px; padding: 8px 12px; border-radius: var(--r-sm); background: var(--blue-tint); border: 1px solid var(--blue-tint-2); }
  .fpl-cascade-fouta .pm-emplacement-apercu__label { display: flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--blue-600); }
  .fpl-cascade-fouta .pm-emplacement-apercu__text { margin: 3px 0 0; font-size: 14px; font-weight: 600; color: var(--ink); }
  @media (max-width: 700px) {
    .fpl-champs-fouta .pf-custom-fields, .fpl-cascade-fouta .pm-emplacement-row { grid-template-columns: 1fr; }
  }
</style>

<script>
  // Marque choisie → seuls SES modèles se proposent, à COCHER (une pièce
  // se monte souvent sur plusieurs). La génération reste l'affaire d'un
  // modèle unique — à plusieurs cochés, elle se désactive.
  (function () {
    const brandModels = <?php echo json_encode((object) $wizard['modeles_par_marque'], JSON_UNESCAPED_UNICODE); ?>;
    const brand = document.getElementById('marque_id');
    const boite = document.getElementById('models-box');
    const btn = document.getElementById('models-btn');
    const panneau = document.getElementById('models-panel');
    const resume = document.getElementById('models-resume');
    const cochesInitiales = <?php echo json_encode(array_values($modeles_coches), JSON_UNESCAPED_UNICODE); ?>;

    const coches = () => Array.from(panneau.querySelectorAll('input:checked'));
    const nomsCoches = () => coches().map(c => c.dataset.nom);
    const modeleUnique = () => { const c = coches(); return c.length === 1 ? c[0].value : ''; };

    btn.addEventListener('click', () => {
      const fermer = !panneau.hidden;
      panneau.hidden = fermer;
      boite.classList.toggle('open', !fermer);
    });
    document.addEventListener('click', (e) => {
      if (!boite.contains(e.target) && !panneau.hidden) {
        panneau.hidden = true;
        boite.classList.remove('open');
      }
    });

    function refreshResume() {
      const n = coches().length;
      if (!brand.value) resume.textContent = '— Choisir la marque d\'abord —';
      else if (n === 0) resume.textContent = '— Aucun —';
      else if (n === 1) resume.textContent = nomsCoches()[0];
      else resume.textContent = n + ' modèles — ' + nomsCoches().join(', ');
    }

    function refresh() {
      const models = brandModels[brand.value] || [];
      btn.disabled = models.length === 0;
      panneau.hidden = true;
      boite.classList.remove('open');
      const echappe = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
      panneau.innerHTML = models.length
        ? models.map(m => '<label class="mm-opt"><input type="checkbox" name="modeles[]" value="' + m.id + '"'
            + ' data-nom="' + echappe(m.name) + '"' + (cochesInitiales.includes(String(m.id)) ? ' checked' : '')
            + '><span>' + echappe(m.name) + '</span></label>').join('')
        : '<div class="mm-vide">Aucun modèle déclaré pour cette marque.</div>';
      refreshResume();
    }
    // …et modèle unique choisi → seules SES générations. Le champ Année
    // suit la génération retenue : il ne se saisit pas, il se lit.
    const modelGenerations = <?php echo json_encode((object) $wizard['generations_par_modele'], JSON_UNESCAPED_UNICODE); ?>;
    const genSel = document.getElementById('generation_id');
    const anneeChamp = document.getElementById('generation_annee');
    const currentGen = '<?php echo (int) ($produit['generation_id'] ?? 0); ?>';

    function refreshAnnee() {
      const gens = modelGenerations[modeleUnique()] || [];
      const choisie = gens.find(g => String(g.id) === genSel.value);
      anneeChamp.value = (choisie && choisie.periode) ? choisie.periode : '—';
    }

    function refreshGenerations() {
      const unique = modeleUnique();
      const gens = modelGenerations[unique] || [];
      genSel.disabled = !unique;
      genSel.innerHTML = '<option value="">'
        + (unique
            ? (gens.length ? '— Aucune —' : '— Aucune génération déclarée —')
            : (coches().length > 1 ? '— Réservée au modèle unique —' : '— Choisir le modèle d\'abord —'))
        + '</option>'
        + gens.map(g => `<option value="${g.id}" ${String(g.id) === currentGen && currentGen !== '0' ? 'selected' : ''}>${g.nom.replace(/</g, '&lt;')}</option>`).join('');
      refreshAnnee();
    }

    brand.addEventListener('change', () => { refresh(); refreshGenerations(); });
    panneau.addEventListener('change', () => { refreshResume(); refreshGenerations(); });
    genSel.addEventListener('change', refreshAnnee);
    refresh();
    refreshGenerations();

    // La description générée regarde ces cases (voir le bloc plus bas).
    window._modelesNoms = nomsCoches;
  })();

  // =====================================================================
  // DESCRIPTION GÉNÉRÉE — repris du wizard d'ajout (24/08) : composée
  // MARQUE — MODÈLE PRINCIPAL — OEM, ou reprise d'une pièce qui porte déjà
  // la référence. Elle ne bouge PAS tant qu'on ne touche ni au véhicule ni
  // aux références : celle déjà en base est conservée telle quelle.
  // =====================================================================
  (function () {
    const el = (id) => document.getElementById(id);
    const preview = el('desc-preview');
    const badgeTxt = el('desc-badge-txt');
    const cache = el('description');
    if (!preview || !cache) { return; }

    let foundDescription = null;
    let timer = null;

    function selText(id) { const s = el(id); return s && s.value ? s.options[s.selectedIndex].text.trim() : ''; }

    function composer() {
      const principal = (window._modelesNoms ? window._modelesNoms() : [])[0] || '';
      const oem = el('reference_oem') ? el('reference_oem').value.trim() : '';
      return [selText('marque_id'), principal, oem !== '' ? 'OEM ' + oem : ''].filter(Boolean).join(' — ');
    }

    function refreshDesc() {
      if (foundDescription) {
        preview.textContent = foundDescription;
        preview.className = 'desc-preview found';
        badgeTxt.textContent = 'Reprise de la base : cette référence est déjà connue.';
        cache.value = foundDescription;
        return;
      }
      const composee = composer();
      preview.className = 'desc-preview' + (composee ? ' filled' : '');
      preview.textContent = composee || 'Composée à partir de la marque, du modèle et de la réf. OEM.';
      badgeTxt.textContent = '';
      cache.value = composee;
    }

    function lookup() {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        const oem = el('reference_oem') ? el('reference_oem').value.trim() : '';
        const ref = el('reference_fournisseur') ? el('reference_fournisseur').value.trim() : '';
        foundDescription = null;
        if (oem || ref) {
          try {
            const r = await fetch('ajax_description_auto.php?oem=' + encodeURIComponent(oem) + '&ref=' + encodeURIComponent(ref), { credentials: 'same-origin' });
            if (r.ok) { const j = await r.json(); if (j.found) foundDescription = j.description; }
          } catch (e) { /* hors ligne : la composition locale suffit */ }
        }
        refreshDesc();
      }, 350);
    }

    // Elle ne se recompose QUE si l'identité change — pas au chargement.
    ['reference_oem', 'reference_fournisseur'].forEach(id => el(id)?.addEventListener('input', lookup));
    el('marque_id')?.addEventListener('change', refreshDesc);
    el('generation_id')?.addEventListener('change', refreshDesc);
    const panneauModeles = el('models-panel');
    if (panneauModeles) { panneauModeles.addEventListener('change', refreshDesc); }
  })();

  // « Un autre, à saisir » ouvre le champ de nom libre ; la couleur choisie
  // remplit la teinte JSON attendue par le contrôleur.
  (function () {
    const choix = document.getElementById('fournisseur_id');
    const bloc = document.getElementById('bloc-fournisseur-libre');
    if (choix && bloc) {
      choix.addEventListener('change', function () {
        bloc.hidden = choix.value !== 'libre';
        if (!bloc.hidden) { bloc.querySelector('input').focus(); }
      });
    }
    const colorSel = document.getElementById('couleur_nom');
    const couleursCache = document.getElementById('couleurs-hidden');
    if (colorSel && couleursCache) {
      colorSel.addEventListener('change', function () {
        const hex = colorSel.options[colorSel.selectedIndex]?.dataset.color;
        couleursCache.value = hex ? JSON.stringify([hex]) : '';
      });
    }
  })();

  // Aperçus des nouvelles photos, retrait avant envoi, glisser-déposer
  (function () {
    const input = document.getElementById('images');
    const zone = document.getElementById('dz');
    const grid = document.getElementById('previews');
    if (!input || !zone || !grid) return;
    let files = [];

    function sync() {
      const dt = new DataTransfer();
      files.forEach(f => dt.items.add(f));
      input.files = dt.files;

      grid.innerHTML = '';
      files.forEach((f, i) => {
        const item = document.createElement('div');
        item.className = 'preview';
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.onload = () => URL.revokeObjectURL(img.src);
        const tag = document.createElement('span');
        tag.className = 'tag';
        tag.textContent = 'Nouvelle';
        const rm = document.createElement('button');
        rm.type = 'button'; rm.className = 'rm'; rm.innerHTML = '&times;'; rm.title = 'Retirer';
        rm.addEventListener('click', (e) => { e.preventDefault(); files.splice(i, 1); sync(); });
        item.append(img, tag, rm);
        grid.appendChild(item);
      });
    }

    function add(list) {
      [...list].forEach(f => { if (files.length < 8 && f.type.startsWith('image/')) files.push(f); });
      sync();
    }

    input.addEventListener('change', () => add(input.files));
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('over'); }));
    zone.addEventListener('drop', e => add(e.dataTransfer.files));
  })();
</script>
<script src="/js/admin-emplacement-produit.js<?php echo asset_version_query(); ?>"></script>

    <?php include '../includes/footer.php'; ?>
