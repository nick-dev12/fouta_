<?php
/**
 * WIZARD « NOUVELLE PIÈCE » — 4 étapes (Véhicule → La pièce → Stock →
 * Finaliser), UN SEUL <form> POST, étapes en JS, brouillons conservés.
 * Le SEUL chemin pour ajouter une pièce : catégorie → sous-catégorie →
 * « Ajouter une pièce » — aucun champ catégorie dans le formulaire.
 * Programmation procédurale uniquement
 *
 * PORTAGE FIDÈLE de fpl_natif/admin/piece-nouvelle.php (23/08/2026 au soir) :
 * le squelette — en-tête marine, bande d'étapes, têtes de panneau, champs,
 * récapitulatif, barre de navigation, JS et CSS — est celui de FPL natif,
 * au caractère près quand c'est possible. Ce qui change, c'est le MOTEUR :
 *   - l'enregistrement passe par process_add_produit() (controllers/
 *     controller_produits.php), avec LES NOMS DE CHAMPS DE CE DÉPÔT :
 *     `stock` (la quantité initiale), `position_montage`, `images_produit[]`,
 *     `image_etiquette_fpl`, `couleurs` (JSON), la cascade d'emplacement
 *     (`ref_etage`, `ref_niveau_*`, `entrepot_noeud_id`) et les champs
 *     personnalisés (`pf_custom_*`) ;
 *   - les apports de Fouta restent : la cascade d'entrepôt nommée (6 niveaux,
 *     includes/produit_emplacement_entrepot.php + js/admin-emplacement-produit.js),
 *     les champs personnalisés par section, la visibilité par rôle
 *     (pf_champ_visible), la photo dédiée à l'étiquette.
 * La page d'avant est conservée telle quelle : ajouter-fouta-origine.php.
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
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_fournisseurs.php';
require_once __DIR__ . '/../../models/model_marques.php';
require_once __DIR__ . '/../../models/model_brouillons.php';
require_once __DIR__ . '/../../includes/navigation_saisie.php';
require_once __DIR__ . '/../../includes/produit_emplacement_entrepot.php';
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';

// Ajouter une pièce, c'est créer : le compte restreint n'y a pas accès.
if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------------------
// LE SEUL CHEMIN : la sous-catégorie est choisie AVANT d'arriver ici.
// Les deux identifiants voyagent dans l'URL (le catalogue et « Où ranger
// cette pièce ? » les passent tous les deux) ; au renvoi du formulaire
// ils reviennent en champs cachés.
// ---------------------------------------------------------------------
$source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$sous_categorie_id = isset($source['sous_categorie_id']) ? (int) $source['sous_categorie_id'] : 0;
$categorie_id = isset($source['categorie_id']) ? (int) $source['categorie_id'] : 0;

$rayon = $sous_categorie_id > 0 ? get_sous_categorie_by_id($sous_categorie_id) : null;
if ($rayon && $categorie_id <= 0) {
    $categorie_id = (int) $rayon['categorie_id'];
}
if ($rayon && (int) $rayon['categorie_id'] !== $categorie_id) {
    // Un rayon qui n'est pas dans cette catégorie : on suit le rayon.
    $categorie_id = (int) $rayon['categorie_id'];
}
$categorie = $categorie_id > 0 ? get_categorie_by_id($categorie_id) : null;

if (!$categorie) {
    $_SESSION['success_message'] = 'Choisissez d\'abord la catégorie, puis la sous-catégorie, et cliquez « Ajouter une pièce ».';
    header('Location: index.php');
    exit;
}
// Une pièce s'ajoute UNIQUEMENT dans une sous-catégorie
if (!$rayon) {
    $_SESSION['success_message'] = 'Ouvrez une sous-catégorie de « ' . fpl_texte($categorie['nom']) . ' » pour y ajouter la pièce.';
    header('Location: index.php?categorie_id=' . (int) $categorie['id']);
    exit;
}

$cible = [
    'id' => (int) $rayon['id'],
    'nom' => fpl_texte((string) $rayon['nom']),
    'parent_id' => (int) $categorie['id'],
    'parent_nom' => fpl_texte((string) $categorie['nom']),
];
/* Les DEUX sorties du wizard (« Retour au catalogue » et le Retour de la
 * barre) portent ?liste=1 : c'est la porte de sortie volontaire de
 * ReprendreSaisie — sans elle, le catalogue ramènerait ici sans fin. */
$url_retour = 'index.php?categorie_id=' . $cible['parent_id'] . '&sous_categorie_id=' . $cible['id'] . '&liste=1';

// ---------------------------------------------------------------------
// L'ENREGISTREMENT — le contrôleur de ce dépôt, inchangé dans son contrat
// ---------------------------------------------------------------------
require_once __DIR__ . '/../../controllers/controller_produits.php';
$erreur_generale = '';
$result = process_add_produit();
if (isset($result['success']) && $result['success']) {
    // L'enregistrement a abouti : le brouillon n'a plus de raison d'être,
    // et la saisie en cours non plus — le catalogue redevient libre.
    saisie_encours_oublier();
    brouillon_purger((int) $_SESSION['admin_id'],
        isset($_POST['_draft_key']) ? (string) $_POST['_draft_key'] : '');
    $_SESSION['success_message'] = $result['message'];
    $nouveau_id = isset($result['produit_id']) ? (int) $result['produit_id'] : 0;
    // Droit au CHOIX DE LA TAILLE de l'étiquette, puis « Générer » —
    // le parcours exact de FPL natif après l'enregistrement (24/08).
    header('Location: ' . ($nouveau_id > 0 ? 'etiquette-piece-choisir.php?id=' . $nouveau_id : $url_retour));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($result['message']) && $result['message'] !== '') {
    $erreur_generale = (string) $result['message']; // lignes séparées par <br>
}

// ---------------------------------------------------------------------
// LES DONNÉES DU FORMULAIRE
// ---------------------------------------------------------------------
$wizard = marques_pour_wizard();
$couleurs = fpl_couleurs();
$fournisseurs = produits_has_column('fournisseur_id') ? get_all_fournisseurs_ordered_by_nom() : [];
$emplacement_form_vals = produit_emplacement_form_values_for_form($_POST);
$pf_custom_vals = [];

$a_col = function ($c) { return produits_has_column($c); };
// Le lien « Créez-la ici » ne se montre qu'à qui a le droit d'ouvrir la page
$peut_gerer_marques = function_exists('admin_route_is_allowed')
    && admin_route_is_allowed((string) ($_SESSION['admin_role'] ?? ''), 'parametres/logos.php');
$voit = function ($slug) { return pf_champ_visible($slug); };
/* VOIR N'EST PAS MODIFIER (31/08). Sur un formulaire de CRÉATION, un champ
 * qu'on n'a pas le droit d'écrire n'a aucune valeur à montrer : on le retire
 * au lieu de le griser. Le prix, par exemple, n'apparaît qu'à qui le fixe —
 * le responsable stock. */
$saisit = function ($slug) { return pf_champ_modifiable($slug); };

// Après une erreur : la saisie revient intacte (le old() de Laravel)
$old = [
    'nom' => isset($_POST['nom']) ? (string) $_POST['nom'] : '',
    'nom_wolof' => isset($_POST['nom_wolof']) ? (string) $_POST['nom_wolof'] : '',
    'marque_id' => isset($_POST['marque_id']) ? (string) $_POST['marque_id'] : '',
    'modeles' => isset($_POST['modeles']) && is_array($_POST['modeles'])
        ? array_map('strval', $_POST['modeles']) : [],
    'generation_id' => isset($_POST['generation_id']) ? (string) $_POST['generation_id'] : '',
    'position_montage' => isset($_POST['position_montage']) ? (string) $_POST['position_montage'] : '',
    'couleur_nom' => isset($_POST['couleur_nom']) ? (string) $_POST['couleur_nom'] : '',
    'couleurs' => isset($_POST['couleurs']) ? (string) $_POST['couleurs'] : '',
    'taille' => isset($_POST['taille']) ? (string) $_POST['taille'] : '',
    'reference_oem' => isset($_POST['reference_oem']) ? (string) $_POST['reference_oem'] : '',
    'reference_fournisseur' => isset($_POST['reference_fournisseur']) ? (string) $_POST['reference_fournisseur'] : '',
    'fournisseur_id' => isset($_POST['fournisseur_id']) ? (string) $_POST['fournisseur_id'] : '',
    'nom_fournisseur' => isset($_POST['nom_fournisseur']) ? (string) $_POST['nom_fournisseur'] : '',
    'prix_achat' => isset($_POST['prix_achat']) ? (string) $_POST['prix_achat'] : '',
    'prix_revient' => isset($_POST['prix_revient']) ? (string) $_POST['prix_revient'] : '',
    'prix' => isset($_POST['prix']) ? (string) $_POST['prix'] : '',
    'prix_promotion' => isset($_POST['prix_promotion']) ? (string) $_POST['prix_promotion'] : '',
    'prix_entreprise' => isset($_POST['prix_entreprise']) ? (string) $_POST['prix_entreprise'] : '',
    'stock' => isset($_POST['stock']) ? (string) $_POST['stock'] : '0',
    'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
];

$fpl_titre_page = 'Nouvelle pièce';
$fpl_retour_page = $url_retour;

/* REPRENDRE LA SAISIE (01/09) — le formulaire va s'afficher pour de bon :
 * tous les gardes qui redirigent sont passés. On retient l'adresse exacte,
 * rayon compris : le catalogue y ramènera tant que la saisie vit, et le
 * brouillon (fpl-draft) retrouvera sa clé au retour. */
saisie_encours_retenir('produits/ajouter.php');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e($_SESSION['admin_csrf']); ?>">
    <title>Nouvelle pièce — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue fpl-wizard-piece">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

<form method="POST" action="" enctype="multipart/form-data"
      id="wizard-form" class="wizard-form" data-draft="produit.nouveau.<?php echo (int) $cible['id']; ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">
  <input type="hidden" name="categorie_id" value="<?php echo (int) $cible['parent_id']; ?>">
  <input type="hidden" name="sous_categorie_id" value="<?php echo (int) $cible['id']; ?>">
  <?php if ($voit('statut')) : ?><input type="hidden" name="statut" value="actif"><?php endif; ?>

  <!-- ===== BARRE DE PROGRESSION ===== -->
  <div class="wiz-header">
    <div class="wiz-back-row">
      <a href="<?php echo e($url_retour); ?>" class="wiz-back-link">
        <?php echo fpl_icone('arrow-left', 14); ?> Retour au catalogue
      </a>
    </div>

    <div class="wiz-meta">
      <div class="wiz-category-tag">
        <?php echo fpl_icone('folder', 12); ?>
        <?php echo e($cible['parent_nom']); ?> › <?php echo e($cible['nom']); ?>
      </div>
      <h1 class="wiz-title">Nouvelle pièce</h1>
    </div>

    <nav class="wiz-steps" aria-label="Étapes du formulaire">
      <?php
      $etapes = [
          ['Véhicule', 'Marque, modèle & génération'],
          ['La pièce', 'Identité, références & dimensions'],
          ['Stock', 'Quantité & emplacement'],
          ['Finaliser', 'Photos & confirmation'],
      ];
      foreach ($etapes as $i => $etape) : ?>
        <div class="wiz-step <?php echo $i === 0 ? 'active' : ''; ?>" data-step="<?php echo $i + 1; ?>" id="wiz-step-tab-<?php echo $i + 1; ?>">
          <div class="wiz-step-circle">
            <span class="wiz-step-num"><?php echo $i + 1; ?></span>
            <span class="wiz-step-check"><?php echo fpl_icone('check', 11); ?></span>
          </div>
          <div class="wiz-step-label">
            <span class="wiz-step-name"><?php echo e($etape[0]); ?></span>
            <span class="wiz-step-sub"><?php echo e($etape[1]); ?></span>
          </div>
          <?php if ($i < 3) : ?>
            <div class="wiz-step-connector"></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- ===== CORPS DU WIZARD ===== -->
  <div class="wiz-body">

    <?php if ($erreur_generale !== '') : ?>
      <div class="wiz-erreurs">
        <?php foreach (explode('<br>', $erreur_generale) as $err) : if (trim($err) === '') { continue; } ?>
          <div class="wiz-erreur-item"><?php echo fpl_icone('alert-triangle', 14); ?> <?php echo e(strip_tags($err)); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- ================================================================
         ÉTAPE 1 — VÉHICULE : Marque → Modèles → Génération
         ================================================================ -->
    <div class="wiz-panel active" id="wiz-panel-1">
      <div class="wiz-panel-head">
        <div class="wiz-panel-icon"><?php echo fpl_icone('truck', 22); ?></div>
        <div>
          <h2>Véhicule compatible</h2>
          <p>Identifiez le camion — marque, modèle, puis génération. Chaque choix restreint le suivant.</p>
        </div>
      </div>

      <div class="wiz-fields">
        <!-- Marque -->
        <div class="wiz-field-group">
          <div class="wiz-field">
            <label for="marque_id">
              <?php echo fpl_icone('tag', 13); ?> Marque du véhicule
            </label>
            <select id="marque_id" name="marque_id" class="wiz-select">
              <option value="">— Toutes marques —</option>
              <?php foreach ($wizard['marques'] as $m) : ?>
                <option value="<?php echo (int) $m['id']; ?>" <?php echo $old['marque_id'] === (string) $m['id'] ? 'selected' : ''; ?>><?php echo fpl_e($m['nom']); ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($peut_gerer_marques) : ?>
              <div class="wiz-help">Marque absente ? <a href="../parametres/logos.php?tab=marques" target="_blank">Créez-la ici →</a></div>
            <?php else : ?>
              <div class="wiz-help">Marque absente ? Demandez à l'administrateur.</div>
            <?php endif; ?>
          </div>

          <div class="wiz-field">
            <label for="models-btn">
              <?php echo fpl_icone('layers', 13); ?> Modèles compatibles
              <span class="wiz-optional">plusieurs possibles</span>
            </label>
            <div class="multi-modeles" id="models-box">
              <button type="button" class="mm-btn" id="models-btn" disabled>
                <span class="mm-resume" id="models-resume">— Choisir la marque d'abord —</span>
                <span class="mm-chevron"><?php echo fpl_icone('chevron-down', 14); ?></span>
              </button>
              <div class="mm-panel" id="models-panel" hidden></div>
            </div>
            <div class="wiz-help">Cochez tous les modèles sur lesquels la pièce se monte.</div>
          </div>
        </div>

        <!-- Génération + Année héritée -->
        <div class="wiz-field-group">
          <div class="wiz-field">
            <label for="generation_id">
              <?php echo fpl_icone('calendar', 13); ?> Génération
              <span class="wiz-optional">optionnel</span>
            </label>
            <select id="generation_id" name="generation_id" class="wiz-select">
              <option value="">— Choisir le modèle d'abord —</option>
            </select>
            <?php if ($peut_gerer_marques) : ?>
              <div class="wiz-help">Génération absente ? <a href="../parametres/logos.php?tab=marques" target="_blank">Ajoutez-la au modèle →</a></div>
            <?php endif; ?>
          </div>

          <div class="wiz-field">
            <label for="generation_annee">
              <?php echo fpl_icone('clock', 13); ?> Période couverte
            </label>
            <input type="text" id="generation_annee" readonly tabindex="-1"
                   class="wiz-input wiz-input-readonly" value="—"
                   title="Renseignée par la génération choisie">
            <div class="wiz-help">Héritée de la génération — se corrige sur le modèle.</div>
          </div>
        </div>

        <!-- Aperçu véhicule -->
        <div class="wiz-vehicule-preview" id="vehicule-preview" style="display:none">
          <?php echo fpl_icone('truck', 14); ?>
          <span id="vehicule-preview-text"></span>
        </div>
      </div>
    </div>

    <!-- ================================================================
         ÉTAPE 2 — LA PIÈCE : nom, références, couleur, taille
         ================================================================ -->
    <div class="wiz-panel" id="wiz-panel-2">
      <div class="wiz-panel-head">
        <div class="wiz-panel-icon"><?php echo fpl_icone('tool', 22); ?></div>
        <div>
          <h2>Identité de la pièce</h2>
          <p>Nommez la pièce, renseignez les références et définissez ses dimensions.</p>
        </div>
      </div>

      <div class="wiz-fields">
        <!-- Nom -->
        <div class="wiz-field wiz-field-full">
          <label for="nom">
            <?php echo fpl_icone('file-text', 13); ?> Nom de la pièce <span class="wiz-req">*</span>
          </label>
          <input type="text" id="nom" name="nom" value="<?php echo e($old['nom']); ?>" required minlength="2"
                 placeholder="Ex. Filtre à gasoil Mann PL 270" class="wiz-input" autofocus>
        </div>

        <?php if ($a_col('nom_wolof')) : ?>
          <!-- LE NOM EN WOLOF — celui qu'on entend au comptoir. Il devient
               le titre de l'étiquette, le français passe en dessous. -->
          <div class="wiz-field wiz-field-full">
            <label for="nom_wolof">
              <?php echo fpl_icone('tag', 13); ?> Appellation

            </label>
            <input type="text" id="nom_wolof" name="nom_wolof" value="<?php echo e($old['nom_wolof']); ?>"
                   placeholder="Ex. XOTTU SETTU" class="wiz-input">
          </div>
        <?php endif; ?>

        <!-- Références -->
        <div class="wiz-field-group">
          <?php if ($a_col('reference_oem')) : ?>
          <div class="wiz-field">
            <label for="reference_oem">
              <?php echo fpl_icone('hash', 13); ?> Réf. OEM (constructeur)
            </label>
            <input type="text" id="reference_oem" name="reference_oem" value="<?php echo e($old['reference_oem']); ?>"
                   placeholder="WABCO 412 704 001 0" class="wiz-input wiz-mono" maxlength="120">
          </div>
          <?php endif; ?>
          <?php if ($voit('reference_fournisseur') && $a_col('reference_fournisseur')) : ?>
          <div class="wiz-field">
            <label for="reference_fournisseur">
              <?php echo fpl_icone('package', 13); ?> Réf. fournisseur
            </label>
            <input type="text" id="reference_fournisseur" name="reference_fournisseur" value="<?php echo e($old['reference_fournisseur']); ?>"
                   placeholder="PL270/8" class="wiz-input wiz-mono" maxlength="120">
          </div>
          <?php endif; ?>
        </div>

        <?php if ($saisit('fournisseur_id') && ($a_col('fournisseur_id') || $a_col('nom_fournisseur'))) : ?>
          <!-- LE FOURNISSEUR — au référentiel de préférence. Le nom libre
               reste possible, en repli (« Un autre, à saisir »). -->
          <div class="wiz-field-group">
            <div class="wiz-field">
              <label for="fournisseur_id">
                <?php echo fpl_icone('truck', 13); ?> Fournisseur
                <span class="wiz-optional">optionnel</span>
              </label>
              <?php if ($fournisseurs !== []) : ?>
                <select id="fournisseur_id" name="fournisseur_id" class="wiz-select">
                  <option value="">— Aucun —</option>
                  <?php foreach ($fournisseurs as $f) : ?>
                    <option value="<?php echo (int) $f['id']; ?>" <?php echo $old['fournisseur_id'] === (string) $f['id'] ? 'selected' : ''; ?>><?php echo fpl_e($f['nom']); ?></option>
                  <?php endforeach; ?>
                  <option value="libre" <?php echo $old['fournisseur_id'] === 'libre' ? 'selected' : ''; ?>>— Un autre, à saisir —</option>
                </select>
                <?php if ($peut_gerer_marques) : ?>
                  <div class="wiz-help">Liste gérée dans <a href="../parametres/logos.php?tab=fournisseurs" target="_blank">Paramètres → Logos &amp; fournisseurs</a>.</div>
                <?php endif; ?>
              <?php else : ?>
                <input type="text" id="nom_fournisseur_seul" name="nom_fournisseur" class="wiz-input"
                       value="<?php echo e($old['nom_fournisseur']); ?>" placeholder="Nom du fournisseur">
                <div class="wiz-help">Aucun fournisseur au référentiel — le nom est enregistré tel quel.</div>
              <?php endif; ?>
            </div>
            <?php if ($fournisseurs !== []) : ?>
              <div class="wiz-field" id="bloc-fournisseur-libre" <?php echo $old['fournisseur_id'] === 'libre' ? '' : 'hidden'; ?>>
                <label for="nom_fournisseur">Son nom</label>
                <input type="text" id="nom_fournisseur" name="nom_fournisseur" class="wiz-input"
                       value="<?php echo e($old['nom_fournisseur']); ?>" placeholder="Nom du fournisseur" maxlength="150">
              </div>
            <?php endif; ?>
          </div>

          <?php if ($saisit('prix_achat') && $a_col('prix_achat')) : ?>
            <div class="wiz-field-group">
              <?php if ($a_col('prix_revient')) : ?>
              <div class="wiz-field">
                <label for="prix_revient">
                  <?php echo fpl_icone('dollar-sign', 13); ?> Prix d'achat
                  <span class="wiz-optional">optionnel</span>
                </label>
                <input type="number" id="prix_revient" name="prix_revient" min="0" step="any" class="wiz-input"
                       value="<?php echo e($old['prix_revient']); ?>">
                <div class="wiz-help">En FCFA — ce que la pièce vous a coûté (le plus bas).</div>
              </div>
              <?php endif; ?>
              <div class="wiz-field">
                <label for="prix_achat">
                  <?php echo fpl_icone('dollar-sign', 13); ?> Prix grossiste
                  <span class="wiz-optional">optionnel</span>
                </label>
                <input type="number" id="prix_achat" name="prix_achat" min="0" step="any" class="wiz-input"
                       value="<?php echo e($old['prix_achat']); ?>">
                <div class="wiz-help">En FCFA — le prix vendu aux grossistes (sous le prix de vente).</div>
              </div>
              <?php if (!$a_col('prix_revient')) : ?><div class="wiz-field"></div><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($saisit('prix')) : ?>
          <!-- LES PRIX DE VENTE dès la création : la pièce naît vendable -->
          <div class="wiz-field-group">
            <div class="wiz-field">
              <label for="prix">
                <?php echo fpl_icone('dollar-sign', 13); ?> Prix de vente
                <span class="wiz-optional">optionnel</span>
              </label>
              <input type="number" id="prix" name="prix" min="0" step="any" class="wiz-input"
                     value="<?php echo e($old['prix']); ?>">
              <div class="wiz-help">En FCFA. Laissé vide, le prix reste à 0 — à poser plus tard.</div>
            </div>
            <?php if ($saisit('prix_promotion')) : ?>
              <div class="wiz-field">
                <label for="prix_promotion">
                  <?php echo fpl_icone('tag', 13); ?> Prix promotionnel
                  <span class="wiz-optional">optionnel</span>
                </label>
                <input type="number" id="prix_promotion" name="prix_promotion" min="0" step="any" class="wiz-input"
                       value="<?php echo e($old['prix_promotion']); ?>">
                <div class="wiz-help">Toujours sous le prix de vente.</div>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($a_col('prix_entreprise')) : ?>
            <div class="wiz-field-group">
              <div class="wiz-field">
                <label for="prix_entreprise">
                  <?php echo fpl_icone('store', 13); ?> Prix entreprise
                  <span class="wiz-optional">optionnel</span>
                </label>
                <input type="number" id="prix_entreprise" name="prix_entreprise" min="0" step="any" class="wiz-input"
                       value="<?php echo e($old['prix_entreprise']); ?>">
                <div class="wiz-help">Le tarif des clients professionnels — au-dessus du prix de vente.</div>
              </div>
              <div class="wiz-field"></div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($voit('couleurs')) : ?>
        <!-- Couleur : une pastille nommée à l'écran, la teinte JSON en base
             (convention `couleurs` de ce dépôt) -->
        <div class="wiz-field-group">
          <div class="wiz-field">
            <label for="couleur_nom"><?php echo fpl_icone('tag', 13); ?> Couleur <span class="wiz-optional">optionnel</span></label>
            <select id="couleur_nom" name="couleur_nom" class="wiz-select">
              <option value="">— Aucune —</option>
              <?php foreach ($couleurs as $c => $hex) : ?>
                <option value="<?php echo e($c); ?>" data-color="<?php echo e($hex); ?>" <?php echo $old['couleur_nom'] === $c ? 'selected' : ''; ?>><?php echo e($c); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="couleurs" id="couleurs-hidden" value="<?php echo e($old['couleurs']); ?>">
          </div>
          <div class="wiz-field" style="display:flex; align-items:center; gap:10px; padding-top:30px">
            <div id="color-swatch" class="color-swatch" style="display:none"></div>
          </div>
        </div>
        <?php endif; ?>

        <!-- La TAILLE appartient à la pièce ; le rangement en BOX à
             séparations se choisit à l'étape Stock -->
        <div class="wiz-field-group">
          <?php if ($voit('taille') && $a_col('taille')) : ?>
          <div class="wiz-field">
            <label for="taille"><?php echo fpl_icone('grid', 13); ?> Taille / dimensions <span class="wiz-optional">optionnel</span></label>
            <input type="text" id="taille" name="taille" value="<?php echo e($old['taille']); ?>"
                   maxlength="120" placeholder="Ex. 60×105, ø120xø140x13, 55 mm"
                   class="wiz-input wiz-mono">
            <div class="wiz-help">Format libre : diamètre, longueur, épaisseur… comme sur l'emballage.</div>
          </div>
          <?php endif; ?>
          <?php if ($a_col('position_montage')) : ?>
          <div class="wiz-field">
            <label for="position_montage"><?php echo fpl_icone('map-pin', 13); ?> Côté / position <span class="wiz-optional">optionnel</span></label>
            <select id="position_montage" name="position_montage" class="wiz-select">
              <option value="">— Non applicable —</option>
              <option value="gauche" <?php echo $old['position_montage'] === 'gauche' ? 'selected' : ''; ?>>Gauche</option>
              <option value="droite" <?php echo $old['position_montage'] === 'droite' ? 'selected' : ''; ?>>Droite</option>
            </select>
          </div>
          <?php endif; ?>
        </div>

        <!-- LES CHAMPS AJOUTÉS À LA FICHE (Paramètres → Champs de la fiche),
             section « Informations » -->
        <div class="wiz-champs-fouta">
          <?php produit_formulaire_render_champs_custom('info', $pf_custom_vals, $_POST); ?>
        </div>

        <!-- Description automatique -->
        <div class="wiz-field wiz-field-full">
          <label>
            Description
            <span class="badge blue" style="font-size:11px; margin-left:6px">
              <?php echo fpl_icone('sparkles', 10); ?> automatique
            </span>
          </label>
          <div id="desc-preview" class="desc-preview">
            Composée à partir de la marque, du modèle et de la réf. OEM.
          </div>
          <div class="wiz-help" id="desc-badge-txt"></div>
          <input type="hidden" name="description" id="description" value="<?php echo e($old['description']); ?>">
        </div>
      </div>
    </div>

    <!-- ================================================================
         ÉTAPE 3 — STOCK & EMPLACEMENT
         ================================================================ -->
    <div class="wiz-panel" id="wiz-panel-3">
      <div class="wiz-panel-head">
        <div class="wiz-panel-icon"><?php echo fpl_icone('package', 22); ?></div>
        <div>
          <h2>Stock &amp; emplacement</h2>
          <p>Définissez la quantité initiale et l'endroit exact où la pièce est rangée.</p>
        </div>
      </div>

      <div class="wiz-fields">
        <?php if ($voit('stock')) : ?>
        <div class="wiz-field-group">
          <div class="wiz-field" style="max-width:180px">
            <label for="stock">
              <?php echo fpl_icone('layers', 13); ?> Quantité initiale
            </label>
            <input type="number" id="stock" name="stock"
                   value="<?php echo e($old['stock']); ?>" min="0" step="1" class="wiz-input">
            <div class="wiz-help">0 = pièce créée, stock à saisir plus tard.</div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($voit('emplacement')) : ?>
        <div class="wiz-field wiz-field-full">
          <label><?php echo fpl_icone('map-pin', 13); ?> Emplacement de rangement</label>
          <?php /* LA CASCADE D'ENTREPÔT DE FOUTA — ses niveaux nommés, son JS
                   (admin-emplacement-produit.js) et ses champs (`ref_etage`,
                   `ref_niveau_*`, `entrepot_noeud_id`) : un apport de ce dépôt,
                   gardé tel quel dans le panneau de FPL. */ ?>
          <div class="wiz-cascade-fouta">
            <?php produit_emplacement_render_form_fields($emplacement_form_vals); ?>
          </div>
          <div class="wiz-help">
            Une box à séparateurs ? Descendez jusqu'au compartiment : chaque
            séparation reçoit sa pièce. Les niveaux se créent dans
            <a href="../parametres/hierarchie-entrepot.php" target="_blank">la structure de l'entrepôt</a>.
          </div>
        </div>
        <?php endif; ?>

        <!-- Les champs ajoutés à la fiche, sections « Prix » et « Référence » -->
        <div class="wiz-champs-fouta">
          <?php produit_formulaire_render_champs_custom('prix', $pf_custom_vals, $_POST); ?>
          <?php produit_formulaire_render_champs_custom('ref', $pf_custom_vals, $_POST); ?>
          <?php produit_formulaire_render_champs_custom('options', $pf_custom_vals, $_POST); ?>
        </div>
      </div>
    </div>

    <!-- ================================================================
         ÉTAPE 4 — PHOTOS & FINALISATION
         ================================================================ -->
    <div class="wiz-panel" id="wiz-panel-4">
      <div class="wiz-panel-head">
        <div class="wiz-panel-icon"><?php echo fpl_icone('check', 22); ?></div>
        <div>
          <h2>Photos &amp; confirmation</h2>
          <p>Ajoutez des photos (la première sera l'image principale), puis vérifiez le récapitulatif avant d'enregistrer.</p>
        </div>
      </div>

      <div class="wiz-fields">
        <?php if ($voit('images_produit')) : ?>
        <!-- Dropzone -->
        <div class="wiz-field wiz-field-full">
          <label class="dropzone" id="dz">
            <span class="dz-icon"><?php echo fpl_icone('image', 22); ?></span>
            <span class="dz-title">Cliquez ou déposez vos photos</span>
            <span class="dz-sub">JPG, PNG, WEBP — 8 max</span>
            <input type="file" id="images" name="images_produit[]" multiple accept="image/jpeg,image/png,image/webp">
          </label>
          <label class="btn-camera" title="Prendre une photo avec l'appareil photo">
            <?php echo fpl_icone('camera', 15); ?> Prendre une photo
            <input type="file" id="camera-input" accept="image/jpeg,image/png,image/webp" capture="environment" style="display:none">
          </label>
          <div class="previews" id="previews"></div>
        </div>
        <?php endif; ?>

        <?php if ($voit('image_etiquette_fpl') && $a_col('image_etiquette_fpl')) : ?>
        <!-- LA PHOTO DE L'ÉTIQUETTE : une belle photo de catalogue devient
             illisible à 20 mm. -->
        <div class="wiz-field wiz-field-full">
          <label for="image_etiquette">
            <?php echo fpl_icone('tag', 13); ?> Photo pour l'étiquette
            <span class="wiz-optional">optionnel</span>
          </label>
          <input type="file" id="image_etiquette" name="image_etiquette_fpl"
                 accept="image/jpeg,image/png,image/webp" class="wiz-input">
          <div class="wiz-help">
            Une image plus contrastée ou recadrée, pour rester lisible à 20 mm.
            Sans elle, l'étiquette prend la photo principale.
          </div>
        </div>
        <?php endif; ?>

        <div class="wiz-champs-fouta">
          <?php produit_formulaire_render_champs_custom('media', $pf_custom_vals, $_POST); ?>
        </div>

        <!-- Récapitulatif avant envoi -->
        <div class="wiz-recap" id="wiz-recap">
          <div class="wiz-recap-title"><?php echo fpl_icone('list', 14); ?> Récapitulatif</div>
          <div class="wiz-recap-grid">
            <div class="wiz-recap-row"><span>Catégorie</span><strong><?php echo e($cible['parent_nom']); ?> › <?php echo e($cible['nom']); ?></strong></div>
            <div class="wiz-recap-row" id="recap-vehicule"><span>Véhicule</span><strong>—</strong></div>
            <div class="wiz-recap-row" id="recap-piece"><span>Pièce</span><strong>—</strong></div>
            <div class="wiz-recap-row" id="recap-oem"><span>Réf. OEM</span><strong>—</strong></div>
            <div class="wiz-recap-row" id="recap-dim"><span>Dimensions</span><strong>—</strong></div>
            <div class="wiz-recap-row" id="recap-stock"><span>Stock initial</span><strong>0</strong></div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /wiz-body -->

  <!-- ===== BARRE DE NAVIGATION ===== -->
  <div class="wiz-nav">
    <button type="button" id="btn-prev" class="btn btn-outline" style="display:none">
      <?php echo fpl_icone('arrow-left', 14); ?> Précédent
    </button>
    <div class="wiz-nav-right">
      <span class="wiz-step-counter" id="wiz-counter">Étape 1 sur 4</span>
      <button type="button" id="btn-next" class="btn btn-primary">
        Suivant <?php echo fpl_icone('chevron-right', 14); ?>
      </button>
      <button type="submit" id="btn-submit" class="btn btn-primary btn-submit" style="display:none">
        <?php echo fpl_icone('save', 14); ?> Enregistrer la pièce
      </button>
    </div>
  </div>

</form>

    </div><!-- .page-produits-admin -->

<script>
// =====================================================================
// DONNÉES SERVEUR → JS
// =====================================================================
const brandModels      = <?php echo json_encode((object) $wizard['modeles_par_marque'], JSON_UNESCAPED_UNICODE); ?>;
const modelGenerations = <?php echo json_encode((object) $wizard['generations_par_modele'], JSON_UNESCAPED_UNICODE); ?>;
const oldModels        = <?php echo json_encode($old['modeles'], JSON_UNESCAPED_UNICODE); ?>;
const oldGeneration    = '<?php echo e($old['generation_id']); ?>';
// Les brouillons (js/fpl-draft.js) : lecture et écriture sur le même point
window.FPL_DRAFT_URLS = { show: 'ajax_brouillon.php', save: 'ajax_brouillon.php' };

// =====================================================================
// WIZARD — NAVIGATION ENTRE ÉTAPES
// =====================================================================
(function () {
  const TOTAL = 4;
  let current = 1;

  const panels  = (n) => document.getElementById('wiz-panel-' + n);
  const tabs    = (n) => document.getElementById('wiz-step-tab-' + n);
  const btnPrev = document.getElementById('btn-prev');
  const btnNext = document.getElementById('btn-next');
  const btnSub  = document.getElementById('btn-submit');
  const counter = document.getElementById('wiz-counter');
  const form    = document.getElementById('wizard-form');

  function goTo(n) {
    panels(current)?.classList.remove('active');
    tabs(current)?.classList.remove('active');
    if (n > current) tabs(current)?.classList.add('done');

    current = n;

    panels(current)?.classList.add('active');
    tabs(current)?.classList.add('active');
    tabs(current)?.classList.remove('done');

    btnPrev.style.display = current > 1 ? '' : 'none';
    btnNext.style.display = current < TOTAL ? '' : 'none';
    btnSub.style.display  = current === TOTAL ? '' : 'none';
    counter.textContent   = 'Étape ' + current + ' sur ' + TOTAL;

    if (current === TOTAL) updateRecap();

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function validateStep(n) {
    if (n === 2) {
      const nom = document.getElementById('nom');
      if (!nom.value.trim()) {
        nom.focus();
        nom.classList.add('wiz-input-error');
        setTimeout(() => nom.classList.remove('wiz-input-error'), 2000);
        return false;
      }
    }
    return true;
  }

  btnNext.addEventListener('click', () => {
    if (validateStep(current)) goTo(current + 1);
  });
  btnPrev.addEventListener('click', () => goTo(current - 1));

  // Clic sur un tab déjà visité
  document.querySelectorAll('.wiz-step').forEach(tab => {
    tab.addEventListener('click', () => {
      const n = +tab.dataset.step;
      if (n < current || tab.classList.contains('done')) goTo(n);
    });
  });

  // Entrée ne doit pas envoyer le formulaire depuis une étape
  // intermédiaire : elle fait avancer, comme un « Suivant ».
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const t = e.target;
    if (t && (t.tagName === 'TEXTAREA' || t.type === 'submit' || t.type === 'button')) return;
    if (current < TOTAL) {
      e.preventDefault();
      if (validateStep(current)) goTo(current + 1);
    }
  });

  // Un champ obligatoire resté vide dans un panneau caché (ex. un champ
  // ajouté à la fiche) : le navigateur refuserait l'envoi sans rien dire.
  // On ouvre le panneau fautif et on montre le champ.
  form.addEventListener('submit', (e) => {
    if (form.checkValidity()) return;
    const invalide = form.querySelector(':invalid');
    if (!invalide) return;
    e.preventDefault();
    const panneau = invalide.closest('.wiz-panel');
    if (panneau) {
      const n = +panneau.id.replace('wiz-panel-', '');
      if (n && n !== current) goTo(n);
    }
    setTimeout(() => { invalide.focus(); if (invalide.reportValidity) invalide.reportValidity(); }, 250);
  });

  window._wizGoTo = goTo;

  // ===== RÉCAPITULATIF ÉTAPE 4 =====
  function updateRecap() {
    const el = (id) => document.getElementById(id);
    const val = (id) => (document.getElementById(id) || {}).value || '';
    const sel = (id) => {
      const s = document.getElementById(id);
      return s && s.value ? s.options[s.selectedIndex]?.text.trim() : '';
    };

    // Véhicule — tous les modèles cochés ; la génération au modèle unique
    const noms = window._modelesNoms ? window._modelesNoms() : [];
    const vehicule = [sel('marque_id'), noms.join(', '),
      noms.length === 1 ? sel('generation_id') : ''].filter(Boolean).join(' · ');
    el('recap-vehicule').querySelector('strong').textContent = vehicule || '—';

    el('recap-piece').querySelector('strong').textContent = val('nom') || '—';
    el('recap-oem').querySelector('strong').textContent = val('reference_oem') || '—';

    // Dimensions : la taille de LA pièce (et son côté s'il compte)
    const t = val('taille');
    const p = sel('position_montage');
    el('recap-dim').querySelector('strong').textContent = [p, t].filter(Boolean).join(' — ') || '—';

    el('recap-stock').querySelector('strong').textContent = val('stock') || '0';
  }
})();

// =====================================================================
// ÉTAPE 1 : Cascades Marque → Modèles (cases à cocher) → Génération
// La génération appartient à UN modèle — active au modèle unique.
// =====================================================================
(function () {
  const el = (id) => document.getElementById(id);
  const preview = document.getElementById('vehicule-preview');
  const previewText = document.getElementById('vehicule-preview-text');
  const boite = el('models-box'), btn = el('models-btn'),
        panneau = el('models-panel'), resume = el('models-resume');

  const coches = () => Array.from(panneau.querySelectorAll('input:checked'));
  const nomsCoches = () => coches().map(c => c.dataset.nom);
  const modeleUnique = () => { const c = coches(); return c.length === 1 ? c[0].value : ''; };

  // ----- Ouvrir / fermer le déroulant -----
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
    if (!el('marque_id').value) resume.textContent = '— Choisir la marque d\'abord —';
    else if (n === 0) resume.textContent = '— Aucun —';
    else if (n === 1) resume.textContent = nomsCoches()[0];
    else resume.textContent = n + ' modèles — ' + nomsCoches().join(', ');
  }

  function refreshModels() {
    const models = brandModels[el('marque_id').value] || [];
    btn.disabled = models.length === 0;
    panneau.hidden = true;
    boite.classList.remove('open');
    const echappe = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    panneau.innerHTML = models.length
      ? models.map(m => '<label class="mm-opt"><input type="checkbox" name="modeles[]" value="' + m.id + '"'
          + ' data-nom="' + echappe(m.name) + '"' + (oldModels.includes(String(m.id)) ? ' checked' : '')
          + '><span>' + echappe(m.name) + '</span></label>').join('')
      : '<div class="mm-vide">Aucun modèle déclaré pour cette marque.</div>';
    refreshResume();
    refreshGenerations();
  }

  function refreshAnnee() {
    const gens = modelGenerations[modeleUnique()] || [];
    const choisie = gens.find(g => String(g.id) === el('generation_id').value);
    el('generation_annee').value = (choisie && choisie.periode) ? choisie.periode : '—';
    updateVehiculePreview();
  }

  function refreshGenerations() {
    const unique = modeleUnique();
    const gens = modelGenerations[unique] || [];
    const gsel = el('generation_id');
    gsel.disabled = !unique;
    gsel.innerHTML = '<option value="">'
      + (unique
          ? (gens.length ? '— Aucune —' : '— Aucune génération déclarée —')
          : (coches().length > 1 ? '— Réservée au modèle unique —' : '— Choisir le modèle d\'abord —'))
      + '</option>'
      + gens.map(g => `<option value="${g.id}" ${String(g.id) === oldGeneration ? 'selected' : ''}>${g.nom.replace(/</g, '&lt;')}</option>`).join('');
    if (window.fplRefreshSelect) window.fplRefreshSelect(gsel);
    refreshAnnee();
  }

  function updateVehiculePreview() {
    const selText = (id) => { const s = el(id); return s && s.value ? s.options[s.selectedIndex].text.trim() : ''; };
    const noms = nomsCoches();
    const parts = [selText('marque_id'), noms.join(', '),
      noms.length === 1 ? selText('generation_id') : ''].filter(Boolean);
    const annee = el('generation_annee').value;
    if (noms.length === 1 && annee && annee !== '—') parts.push(annee);
    if (parts.length) {
      previewText.textContent = parts.join(' · ');
      preview.style.display = 'flex';
    } else {
      preview.style.display = 'none';
    }
  }

  el('marque_id').addEventListener('change', () => { refreshModels(); refreshDesc(); });
  panneau.addEventListener('change', () => { refreshResume(); refreshGenerations(); refreshDesc(); });
  el('generation_id').addEventListener('change', refreshAnnee);
  refreshModels();

  window._refreshModels = refreshModels;
  window._modelesNoms = nomsCoches;
})();

// =====================================================================
// ÉTAPE 2 : Description automatique — composée MARQUE — MODÈLE — OEM, ou
// reprise d'une pièce qui porte déjà la référence. Elle part dans le champ
// caché `description` : c'est ce que le contrôleur enregistre.
// =====================================================================
let foundDescription = null;
function refreshDesc() {
  const preview = document.getElementById('desc-preview');
  const badgeTxt = document.getElementById('desc-badge-txt');
  const cache = document.getElementById('description');
  const el = (id) => document.getElementById(id);
  const selText = (id) => { const s = el(id); return s && s.value ? s.options[s.selectedIndex].text.trim() : ''; };

  if (foundDescription) {
    preview.textContent = foundDescription;
    preview.className = 'desc-preview found';
    badgeTxt.textContent = 'Reprise de la base : cette référence est déjà connue.';
    if (cache) cache.value = foundDescription;
    return;
  }
  // Le modèle PRINCIPAL (premier coché) compose la description
  const principal = (window._modelesNoms ? window._modelesNoms() : [])[0] || '';
  const parts = [selText('marque_id'), principal,
    el('reference_oem')?.value.trim() ? 'OEM ' + el('reference_oem').value.trim() : ''].filter(Boolean);
  preview.className = 'desc-preview' + (parts.length ? ' filled' : '');
  if (parts.length) { preview.textContent = parts.join(' — '); badgeTxt.textContent = ''; }
  else { preview.textContent = 'Composée à partir de la marque, du modèle et de la réf. OEM.'; }
  if (cache) cache.value = parts.length ? parts.join(' — ') : '';
}

(function () {
  const el = (id) => document.getElementById(id);
  let timer = null;

  function lookup() {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const oem = el('reference_oem')?.value.trim();
      const ref = el('reference_fournisseur')?.value.trim();
      foundDescription = null;
      if (oem || ref) {
        try {
          const r = await fetch('ajax_description_auto.php?oem=' + encodeURIComponent(oem || '') + '&ref=' + encodeURIComponent(ref || ''), { credentials: 'same-origin' });
          if (r.ok) { const j = await r.json(); if (j.found) foundDescription = j.description; }
        } catch (e) { /* hors ligne */ }
      }
      refreshDesc();
    }, 350);
  }

  ['reference_oem', 'reference_fournisseur'].forEach(id => el(id)?.addEventListener('input', lookup));
  refreshDesc();

  // Couleur → pastille, et la teinte JSON pour la base
  const colorSel = el('couleur_nom');
  const swatch = el('color-swatch');
  const couleursCache = el('couleurs-hidden');
  function syncCouleur() {
    const hex = colorSel.options[colorSel.selectedIndex]?.dataset.color;
    if (hex) { swatch.style.background = hex; swatch.style.display = 'block'; }
    else swatch.style.display = 'none';
    if (couleursCache) couleursCache.value = hex ? JSON.stringify([hex]) : '';
  }
  if (colorSel) { colorSel.addEventListener('change', syncCouleur); syncCouleur(); }

  // « Un autre, à saisir » ouvre le champ de nom libre
  const choix = el('fournisseur_id');
  const bloc = el('bloc-fournisseur-libre');
  if (choix && bloc) {
    choix.addEventListener('change', function () {
      bloc.hidden = choix.value !== 'libre';
      if (!bloc.hidden) { bloc.querySelector('input').focus(); }
    });
  }
})();

// =====================================================================
// ÉTAPE 4 : Photos (dropzone, caméra, aperçus)
// =====================================================================
(function () {
  const input = document.getElementById('images');
  const zone  = document.getElementById('dz');
  const grid  = document.getElementById('previews');
  if (!input || !zone || !grid) return;
  let files   = [];

  function sync() {
    const dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    input.files = dt.files;
    grid.innerHTML = '';
    files.forEach((f, i) => {
      const item = document.createElement('div'); item.className = 'preview';
      const img  = document.createElement('img');
      img.src = URL.createObjectURL(f); img.onload = () => URL.revokeObjectURL(img.src);
      item.appendChild(img);
      if (i === 0) { const tag = document.createElement('span'); tag.className = 'tag'; tag.textContent = 'Principale'; item.appendChild(tag); }
      const rm = document.createElement('button'); rm.type = 'button'; rm.className = 'rm'; rm.title = 'Retirer'; rm.innerHTML = '&times;';
      rm.addEventListener('click', e => { e.preventDefault(); files.splice(i, 1); sync(); });
      item.appendChild(rm); grid.appendChild(item);
    });
  }
  function add(list) { [...list].forEach(f => { if (files.length < 8 && f.type.startsWith('image/')) files.push(f); }); sync(); }

  input.addEventListener('change', () => add(input.files));
  ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('over'); }));
  ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('over'); }));
  zone.addEventListener('drop', e => add(e.dataTransfer.files));
  document.getElementById('camera-input')?.addEventListener('change', function () { add(this.files); });
})();
</script>
<script src="/js/admin-emplacement-produit.js<?php echo asset_version_query(); ?>"></script>
<script src="/js/fpl-draft.js<?php echo asset_version_query(); ?>" defer></script>

<style>
/* ========================================================
   WIZARD — DESIGN PREMIUM (repris tel quel de FPL natif)
   ======================================================== */

/* ----- Layout global : LARGE et dense ----- */
.wizard-form {
  max-width: 1180px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Erreurs serveur */
.wiz-erreurs {
  background: #FEF2F2; border: 1px solid #FECACA;
  border-radius: var(--r); padding: 12px 16px; margin: 20px 28px 0;
}
.wiz-erreur-item { color: #B91C1C; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 7px; }

/* ----- En-tête : fond navy, barre de progression ----- */
.wiz-header {
  background: var(--navy);
  border-radius: var(--r) var(--r) 0 0;
  padding: 18px 30px 0;
  color: #fff;
}
.wiz-back-row { margin-bottom: 10px; }
.wiz-back-link {
  display: inline-flex; align-items: center; gap: 6px;
  color: rgba(255,255,255,.65); font-size: 14px; text-decoration: none;
  transition: color .15s;
}
.wiz-back-link:hover { color: #fff; }

.wiz-meta { margin-bottom: 12px; }
.wiz-category-tag {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(255,255,255,.12); border-radius: 20px;
  padding: 3px 11px; font-size: 13px; color: rgba(255,255,255,.8);
  margin-bottom: 7px;
}
.wiz-title { font-size: 27px; font-weight: 800; margin: 0; letter-spacing: -.02em; color: #fff; }

/* ----- Barre de progression ----- */
.wiz-steps {
  display: flex; align-items: stretch;
  gap: 0; list-style: none; padding: 0; margin: 0;
  overflow-x: auto;
}
.wiz-step {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 0 16px;
  cursor: default;
  flex: 1;
  position: relative;
  user-select: none;
  min-width: 130px;
}
.wiz-step.done { cursor: pointer; }

.wiz-step-circle {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
  background: rgba(255,255,255,.15); border: 2px solid rgba(255,255,255,.25);
  display: flex; align-items: center; justify-content: center;
  transition: background .2s, border-color .2s;
  position: relative;
}
.wiz-step-num  { font-size: 14px; font-weight: 700; color: rgba(255,255,255,.7); }
.wiz-step-check { display: none; color: #fff; }

.wiz-step.active .wiz-step-circle {
  background: #fff; border-color: #fff;
}
.wiz-step.active .wiz-step-num { color: var(--navy); }

.wiz-step.done .wiz-step-circle {
  background: var(--ok, #2CB67D); border-color: var(--ok, #2CB67D);
}
.wiz-step.done .wiz-step-num  { display: none; }
.wiz-step.done .wiz-step-check { display: flex; }

.wiz-step-label { display: flex; flex-direction: column; gap: 1px; }
.wiz-step-name  { font-size: 14.5px; font-weight: 700; color: rgba(255,255,255,.55); }
.wiz-step-sub   { font-size: 12px; color: rgba(255,255,255,.4); line-height: 1.3; }
.wiz-step.active .wiz-step-name { color: #fff; }
.wiz-step.active .wiz-step-sub  { color: rgba(255,255,255,.65); }
.wiz-step.done .wiz-step-name { color: rgba(255,255,255,.75); }

.wiz-step-connector {
  position: absolute; right: 0; top: 50%; transform: translateY(-50%);
  width: 20px; height: 2px;
  background: rgba(255,255,255,.2);
}

/* ----- Corps (panneaux blancs) ----- */
.wiz-body {
  background: var(--surface);
  border-left: 1px solid var(--line);
  border-right: 1px solid var(--line);
}

.wiz-panel { display: none; padding: 20px 28px 24px; }
.wiz-panel.active { display: block; }

/* En-tête de panneau */
.wiz-panel-head {
  display: flex; align-items: flex-start; gap: 16px;
  margin-bottom: 28px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--line-soft);
}
.wiz-panel-icon {
  width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
  background: var(--blue-tint); color: var(--blue-600);
  display: flex; align-items: center; justify-content: center;
}
.wiz-panel-head h2 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: var(--navy); }
.wiz-panel-head p  { font-size: 15px; color: var(--slate); margin: 0; line-height: 1.5; }

/* ----- Champs : typo GÉNÉREUSE, gaps resserrés ----- */
.wiz-fields { display: flex; flex-direction: column; gap: 14px; }
.wiz-field-group { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
.wiz-field { display: flex; flex-direction: column; gap: 5px; }
.wiz-field-full { grid-column: 1 / -1; }

.wiz-field > label {
  display: flex; align-items: center; gap: 6px;
  font-size: 14.5px; font-weight: 600; color: var(--slate);
}
.wiz-req   { color: var(--danger, #e23b3b); }
.wiz-optional { font-size: 12px; font-weight: 400; color: var(--slate-soft); }

.wiz-input {
  width: 100%; padding: 11px 13px; border: 1.5px solid var(--line);
  border-radius: var(--r-sm); font-family: inherit; font-size: 16px;
  background: #fff; color: var(--ink);
  transition: border-color .15s, box-shadow .15s;
}
.wiz-input:focus { outline: none; border-color: var(--blue-600); box-shadow: 0 0 0 3px var(--blue-tint); }
.wiz-input-readonly { background: var(--surface); color: var(--slate); cursor: default; }
.wiz-input-error { border-color: var(--danger, #e23b3b) !important; animation: shake .35s ease; }
.wiz-select {
  width: 100%; padding: 11px 13px; border: 1.5px solid var(--line);
  border-radius: var(--r-sm); font-family: inherit; font-size: 16px;
  background: #fff; color: var(--ink);
  transition: border-color .15s;
}
.wiz-select:focus { outline: none; border-color: var(--blue-600); }
.wiz-mono  { font-family: var(--mono); letter-spacing: .02em; }
.wiz-help  { font-size: 13.5px; color: var(--slate-soft); }
.wiz-help a { color: var(--blue-600); }
.wiz-error { font-size: 14px; color: var(--danger, #e23b3b); font-weight: 600; }

/* Aperçu véhicule */
.wiz-vehicule-preview {
  display: flex; align-items: center; gap: 8px;
  background: var(--blue-tint); color: var(--blue-600);
  border: 1px solid var(--blue-tint-2); border-radius: var(--r-sm);
  padding: 8px 14px; font-size: 14px; font-weight: 600;
  animation: fadeIn .3s ease;
}

/* Pastille couleur */
.color-swatch {
  width: 28px; height: 28px; border-radius: 6px;
  border: 2px solid var(--line); flex-shrink: 0;
  transition: background .2s;
}

/* ========================================================
   BARRE NAV
   ======================================================== */
.wiz-nav {
  display: flex; align-items: center; justify-content: space-between;
  background: #fff;
  border: 1px solid var(--line);
  border-top: none;
  border-radius: 0 0 var(--r) var(--r);
  padding: 16px 24px;
}
.wiz-nav-right { display: flex; align-items: center; gap: 12px; }
.wiz-step-counter { font-size: 14px; color: var(--slate-soft); }
.btn-submit { background: var(--ok, #2CB67D) !important; border-color: var(--ok, #2CB67D) !important; }
.btn-submit:hover { opacity: .9; }

/* ========================================================
   RÉCAPITULATIF (étape 4)
   ======================================================== */
.wiz-recap {
  background: #F8FAFC; border: 1.5px solid var(--line);
  border-radius: var(--r); padding: 18px 20px;
}
.wiz-recap-title {
  display: flex; align-items: center; gap: 6px;
  font-size: 14px; font-weight: 700; color: var(--navy);
  margin-bottom: 12px;
}
.wiz-recap-grid { display: flex; flex-direction: column; gap: 6px; }
.wiz-recap-row {
  display: flex; justify-content: space-between; align-items: baseline;
  font-size: 14.5px; padding: 6px 0;
  border-bottom: 1px solid var(--line-soft);
}
.wiz-recap-row:last-child { border-bottom: none; }
.wiz-recap-row span { color: var(--slate); }
.wiz-recap-row strong { color: var(--ink); font-weight: 600; text-align: right; max-width: 60%; }

/* ========================================================
   ANIMATIONS
   ======================================================== */
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
@keyframes shake {
  0%,100% { transform: translateX(0); }
  25%      { transform: translateX(-6px); }
  75%      { transform: translateX(6px); }
}

/* ========================================================
   LES BLOCS DE FOUTA DANS LE SQUELETTE DE FPL — la cascade
   d'entrepôt et les champs ajoutés à la fiche gardent leur
   balisage (.form-group) ; on leur donne la typo et les bords
   des champs du wizard pour qu'ils ne jurent pas.
   ======================================================== */
.wiz-champs-fouta:empty, .wiz-cascade-fouta:empty { display: none; }
.wiz-champs-fouta .pf-custom-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
.wiz-champs-fouta .form-group, .wiz-cascade-fouta .form-group {
  display: flex; flex-direction: column; gap: 5px; margin: 0;
}
.wiz-champs-fouta .form-group > label, .wiz-cascade-fouta .form-group > label {
  display: flex; align-items: center; gap: 6px;
  font-size: 14.5px; font-weight: 600; color: var(--slate); margin: 0;
}
.wiz-champs-fouta input:not([type=file]), .wiz-champs-fouta select, .wiz-champs-fouta textarea,
.wiz-cascade-fouta select, .wiz-cascade-fouta input:not([type=file]) {
  width: 100%; padding: 11px 13px; border: 1.5px solid var(--line);
  border-radius: var(--r-sm); font-family: inherit; font-size: 16px;
  background: #fff; color: var(--ink);
}
.wiz-champs-fouta .form-hint, .wiz-cascade-fouta .form-hint { font-size: 13.5px; color: var(--slate-soft); }
.wiz-cascade-fouta .pm-emplacement-form--referentiel {
  padding: 14px 16px; border-radius: var(--r);
  background: var(--surface); border: 1.5px solid var(--line);
}
.wiz-cascade-fouta .pm-emplacement-steps { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.wiz-cascade-fouta .pm-emplacement-step {
  display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px;
  background: var(--blue-tint); color: var(--blue-600);
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
}
.wiz-cascade-fouta .pm-emplacement-intro { margin: 0 0 10px; font-size: 13.5px; color: var(--slate); }
.wiz-cascade-fouta .pm-emplacement-cascade { margin-top: 6px; padding-top: 10px; border-top: 1px dashed var(--line); }
.wiz-cascade-fouta .pm-emplacement-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; margin-bottom: 14px; }
.wiz-cascade-fouta .pm-emplacement-count { margin: 0 0 10px; padding: 6px 10px; border-radius: 8px; background: var(--blue-tint); color: var(--blue-600); font-size: 13px; font-weight: 600; }
.wiz-cascade-fouta .pm-emplacement-count[hidden], .wiz-cascade-fouta .pm-emplacement-cascade[hidden], .wiz-cascade-fouta .pm-emplacement-apercu[hidden] { display: none !important; }
.wiz-cascade-fouta .pm-emplacement-apercu { margin-top: 12px; padding: 10px 14px; border-radius: var(--r-sm); background: var(--blue-tint); border: 1px solid var(--blue-tint-2); }
.wiz-cascade-fouta .pm-emplacement-apercu__label { display: flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--blue-600); }
.wiz-cascade-fouta .pm-emplacement-apercu__text { margin: 4px 0 0; font-size: 15px; font-weight: 600; color: var(--ink); }
.wiz-cascade-fouta .pm-emplacement-alert .form-hint--warning { color: var(--danger, #e23b3b); }

/* ========================================================
   RESPONSIVE
   ======================================================== */
@media (max-width: 700px) {
  .wiz-header { padding: 16px 16px 0; }
  .wiz-panel  { padding: 20px 16px; }
  .wiz-field-group, .wiz-champs-fouta .pf-custom-fields, .wiz-cascade-fouta .pm-emplacement-row { grid-template-columns: 1fr; }
  .wiz-steps { gap: 0; }
  .wiz-step-sub { display: none; }
  .wiz-step-connector { display: none; }
  .wiz-nav { padding: 12px 16px; }
}

@media print {
  .wiz-header, .wiz-nav { display: none !important; }
}
</style>

    <?php include '../includes/footer.php'; ?>
