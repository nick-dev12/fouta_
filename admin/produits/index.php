<?php
/**
 * LE CATALOGUE — page unique d'accès aux pièces.
 * Programmation procédurale uniquement
 *
 * PORTAGE DE fpl_natif/admin/pieces.php, à l'identique de ce qui se voit :
 * la ligne de cartes filtre le tableau sans quitter la page, la recherche prime
 * toujours sur la navigation, l'export emporte les mêmes filtres.
 *
 * CE QUI CHANGE PAR RAPPORT À FPL NATIF, et seulement cela :
 *   - les données viennent des fonctions de CE dépôt (get_admin_produits_liste_*),
 *     donc rien de la couche métier de Fouta n'est réécrit ;
 *   - la catégorie et le rayon gardent leurs deux paramètres d'URL
 *     (categorie_id, sous_categorie_id) au lieu du « cat » unique de FPL, parce
 *     qu'ici les deux tables ont des identifiants séparés ;
 *   - l'entête, le menu et le pied de page restent ceux de Fouta.
 *
 * LA PAGE D'ORIGINE N'EST PAS PERDUE : elle vit, entière et fonctionnelle, dans
 * index-fouta-origine.php. On y trouve encore la recherche en direct, la galerie
 * photos, les filtres marque et fournisseur, le suivi du catalogue et le clic
 * sur toute la ligne. On ira y rechercher, un par un, les apports à replacer ici.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../includes/site_url.php';
/* Les champs réservés à certains profils (Fournisseur, prix) : la colonne
 * du tableau s'y conforme — voir includes/ligne_piece_fpl.php. */
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';

// Le compte « admin » est le compte restreint de ce dépôt : il regarde, il ne
// range pas. C'est lui qui remplace les permissions nommées de FPL natif.
$peut_gerer = !admin_is_restricted_admin_account();

// Le jeton de session : la poubelle d'un rayon est un POST (rayon.php) qui l'exige.
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

/** Une date de filtre au format AAAA-MM-JJ — sinon vide, sans erreur. */
function pieces_date_filtre($valeur)
{
    $v = trim((string) $valeur);
    if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return '';
    }

    return checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : '';
}

$q  = isset($_GET['recherche']) ? trim((string) $_GET['recherche']) : '';
$du = pieces_date_filtre(isset($_GET['du']) ? $_GET['du'] : '');
$au = pieces_date_filtre(isset($_GET['au']) ? $_GET['au'] : '');

$categorie_id      = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$sous_categorie_id = isset($_GET['sous_categorie_id']) ? (int) $_GET['sous_categorie_id'] : 0;

/* MARQUE ET FOURNISSEUR — deux filtres propres à ce dépôt, que FPL natif n'a
 * pas. Ils étaient tombés quand la page a été refaite ; ils reviennent, à leur
 * place dans la barre, sans rien déranger de la mise en page de FPL. */
$marque_id      = isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0;
$fournisseur_id = isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0;

$marques_filtre = [];
if (produits_has_column('marque_id')) {
    require_once __DIR__ . '/../../models/model_marques.php';
    if (marques_table_ok()) {
        $marques_filtre = get_all_marques_ordered_by_nom();
    }
}

$fournisseurs_filtre = [];
if (produits_has_column('fournisseur_id')) {
    require_once __DIR__ . '/../../models/model_fournisseurs.php';
    $fournisseurs_filtre = get_all_fournisseurs_ordered_by_nom();
}

// La recherche prime toujours sur la navigation : on cherche dans TOUT le
// catalogue, sinon une recherche qui a des résultats répondrait « aucun ».
if ($q !== '') {
    $categorie_id = 0;
    $sous_categorie_id = 0;
}
// Un rayon sans sa catégorie n'a pas de sens.
if ($categorie_id <= 0) {
    $sous_categorie_id = 0;
}

$categories = get_all_categories();

/* L'ORDRE DU BANDEAU : alphabétique sur le nom tel qu'il S'AFFICHE. Les deux
 * catégories au texte doublement encodé (« Ã‰chappement », « Ã‰lectricité… »)
 * passaient avant le A parce que la base trie les octets ; FPL natif les met
 * à leur place. On trie donc en PHP, après réparation, sans accent ni casse. */
$pieces_cle_tri = function ($nom) {
    // Pas d'iconv ici : sous Windows, son TRANSLIT rend « É » par « 'E » et
    // l'apostrophe repasserait les mêmes noms en tête. Une table suffit.
    $t = mb_strtolower(fpl_texte((string) $nom));
    return strtr($t, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
    ]);
};
usort($categories, function ($x, $y) use ($pieces_cle_tri) {
    return strcmp($pieces_cle_tri($x['nom'] ?? ''), $pieces_cle_tri($y['nom'] ?? ''));
});

$categorie_courante_nom = '';
$sous_categorie_courante_nom = '';
$sous_categories_de_la_categorie = [];

if ($categorie_id > 0) {
    foreach ($categories as $c) {
        if ((int) $c['id'] === $categorie_id) {
            $categorie_courante_nom = (string) $c['nom'];
            break;
        }
    }
    if (function_exists('sous_categories_table_ok') && sous_categories_table_ok()) {
        foreach (get_all_sous_categories_with_categorie_nom() as $sc) {
            if ((int) $sc['categorie_id'] === $categorie_id) {
                $sous_categories_de_la_categorie[] = $sc;
                if ((int) $sc['id'] === $sous_categorie_id) {
                    $sous_categorie_courante_nom = (string) $sc['nom'];
                }
            }
        }
    }
}

// --- La ligne de cartes : le niveau courant — SANS compteur (les cartes
//     restent muettes, décision reprise de FPL natif) ---
if ($categorie_id <= 0) {
    $cartes = $categories;
    $cartes_sont_des_rayons = false;
} else {
    usort($sous_categories_de_la_categorie, function ($x, $y) use ($pieces_cle_tri) {
        return strcmp($pieces_cle_tri($x['nom'] ?? ''), $pieces_cle_tri($y['nom'] ?? ''));
    });
    $cartes = $sous_categories_de_la_categorie;
    $cartes_sont_des_rayons = true;
}

// --- Le tableau : toujours les pièces ---
$per_page = fpl_par_page('catalogue_pieces', 5);
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = count_admin_produits_liste($categorie_id, $marque_id, $fournisseur_id, $sous_categorie_id, $du, $au, $q);
$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$produits = get_admin_produits_liste_paginated(
    $categorie_id, $marque_id, $fournisseur_id, ($page - 1) * $per_page, $per_page, $sous_categorie_id, $du, $au, $q
);

/* « CE QUI MANQUE » (31/08) : la case cochée, la liste n'est plus celle du
 * catalogue mais celle des pièces à zéro ou arrivées à leur seuil — la plus
 * basse d'abord. Le seuil de chaque pièce vient de la pièce elle-même, sinon
 * de la règle de son rayon ; le calcul est celui du modèle des alertes, pas
 * un second à entretenir. Le rayon et la recherche en cours sont respectés. */
$filtre_manque = !empty($_GET['manque']);
if ($filtre_manque) {
    require_once __DIR__ . '/../../models/model_stock_alertes.php';
    $ids_manque = [];
    foreach (stock_alertes_produits_sous_seuil(100000) as $p_manque) {
        if ($categorie_id > 0 && (int) ($p_manque['categorie_id'] ?? 0) !== $categorie_id) {
            continue;
        }
        if ($sous_categorie_id > 0 && (int) ($p_manque['sous_categorie_id'] ?? 0) !== $sous_categorie_id) {
            continue;
        }
        if ($q !== ''
            && stripos((string) ($p_manque['nom'] ?? ''), $q) === false
            && stripos((string) ($p_manque['identifiant_interne'] ?? ''), $q) === false) {
            continue;
        }
        $ids_manque[] = (int) $p_manque['id'];
    }
    $total = count($ids_manque);
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = min(max(1, $page), $total_pages);
    $ids_page = array_slice($ids_manque, ($page - 1) * $per_page, $per_page);
    $produits = [];
    if ($ids_page !== []) {
        try {
            $jb = produits_catalog_join_bundle();
            $trous = implode(',', array_fill(0, count($ids_page), '?'));
            $st = $db->prepare('SELECT p.*, c.nom AS categorie_nom ' . $jb['sel'] . '
                                FROM produits p
                                LEFT JOIN categories c ON c.id = p.categorie_id ' . $jb['join'] . '
                                WHERE p.id IN (' . $trous . ')');
            $st->execute($ids_page);
            $par_id = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ligne_m) {
                $par_id[(int) $ligne_m['id']] = $ligne_m;
            }
            /* On garde l'ordre du plus bas stock au plus haut. */
            foreach ($ids_page as $pid_m) {
                if (isset($par_id[$pid_m])) {
                    $produits[] = $par_id[$pid_m];
                }
            }
        } catch (PDOException $e) {
            $produits = [];
        }
    }
}

$pagination = [
    'total' => (int) $total,
    'page' => (int) $page,
    'par' => (int) $per_page,
    'derniere' => (int) $total_pages,
];

/* Les noms de modèles de véhicule, résolus en une requête plutôt qu'en
 * alourdissant celle de la liste. */
$fpl_modeles_noms = [];
try {
    foreach ($db->query('SELECT id, nom FROM vehicule_modeles') as $vm) {
        $fpl_modeles_noms[(int) $vm['id']] = (string) $vm['nom'];
    }
} catch (PDOException $e) {
    $fpl_modeles_noms = [];   // table absente : la colonne affichera un tiret
}

// Les filtres qui voyagent : pagination, export, « tout effacer ».
$filtres_url = array_filter([
    'manque' => $filtre_manque ? 1 : null,
    'recherche' => $q !== '' ? $q : null,
    'categorie_id' => $categorie_id > 0 ? $categorie_id : null,
    'sous_categorie_id' => $sous_categorie_id > 0 ? $sous_categorie_id : null,
    'marque_id' => $marque_id > 0 ? $marque_id : null,
    'fournisseur_id' => $fournisseur_id > 0 ? $fournisseur_id : null,
    'du' => $du !== '' ? $du : null,
    'au' => $au !== '' ? $au : null,
]);

$upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';
$retour_catalogue = 'index.php' . ($categorie_id > 0 ? '?categorie_id=' . $categorie_id : '');

/* LA BARRE DU HAUT DE FPL NATIF : le titre de la page, la date du jour et le
 * bouton « Retour ». Ces deux variables allument cette zone dans nav.php ; une
 * page qui ne les pose pas garde la barre d'aujourd'hui, inchangée.
 *
 * Le Retour remonte d'un cran dans le catalogue, comme chez FPL natif : d'un
 * rayon vers sa catégorie, d'une catégorie vers la racine, et de la racine vers
 * le tableau de bord. */
$fpl_titre_page = 'Pièces';
if ($sous_categorie_id > 0) {
    $fpl_retour_page = 'index.php?categorie_id=' . $categorie_id;
} elseif ($categorie_id > 0) {
    $fpl_retour_page = 'index.php';
} else {
    $fpl_retour_page = '../dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue des pièces — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<?php // La barre du haut cherche déjà les pièces ; ici la barre de filtres le
      // fait mieux. On marque le corps pour la masquer sur cet écran seulement. ?>
<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

    <?php if ($success_message !== '') : ?>
      <div class="message success page-produits-flash" role="status">
        <?php echo fpl_icone('check', 14); ?> <?php echo fpl_e($success_message); ?>
      </div>
    <?php endif; ?>

    <div class="page-lead">
      <?php if ($categorie_id <= 0) : ?>
        <div>
          <div class="page-lead-title">Catalogue des pièces</div>
          <div class="muted">Ajoutez directement par le nom de la pièce, ou parcourez les catégories ci-dessous.</div>
        </div>
      <?php else : ?>
        <div class="crumb" style="margin-bottom:0">
          <?php // L'icône des pièces est celle du menu de gauche (fa-box) : le même
                // objet doit porter le même signe d'un bout à l'autre de l'application. ?>
          <a href="index.php"><i class="fas fa-box" aria-hidden="true"></i> Pièces</a>
          <?php echo fpl_icone('chevron-right', 12); ?>
          <?php if ($sous_categorie_id > 0) : ?>
            <a href="index.php?categorie_id=<?php echo $categorie_id; ?>"><?php echo fpl_e($categorie_courante_nom); ?></a>
            <?php echo fpl_icone('chevron-right', 12); ?>
            <strong><?php echo fpl_e($sous_categorie_courante_nom); ?></strong>
          <?php else : ?>
            <strong><?php echo fpl_e($categorie_courante_nom); ?></strong>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php /* « PAR SON NOM » — le bouton mène désormais à l'écran de rangement,
               pas au formulaire direct. C'est tout son sens : on tape « filtre »,
               l'écran cherche où le ranger et prévient si la pièce existe déjà. */ ?>
      <?php if ($categorie_id <= 0 && $peut_gerer) : ?>
        <a href="piece-ranger.php" class="btn btn-primary btn-lead" style="margin-left:auto">
          <?php echo fpl_icone('search', 15); ?> Ajouter une pièce par son nom
        </a>
      <?php endif; ?>
    </div>

    <?php /* LE BANDEAU DE CARTES — les catégories à la racine, les rayons dans
             une catégorie. Au niveau RAYON il n'y a plus rien à ouvrir : FPL
             natif ne l'affiche pas, le fil d'Ariane suffit ; on fait pareil. */ ?>
    <?php if ($sous_categorie_id <= 0) : ?>
    <div class="rail-wrap">
      <button type="button" class="rail-arrow" data-dir="-1" title="Précédent"><?php echo fpl_icone('chevron-left', 16); ?></button>

      <div class="rail" id="rail">
        <?php foreach ($cartes as $carte) : ?>
          <?php
            $cid = (int) ($carte['id'] ?? 0);
            $cnom = (string) ($carte['nom'] ?? '');
            $cimg = trim((string) ($carte['image'] ?? ''));
            $clien = $cartes_sont_des_rayons
                ? 'index.php?categorie_id=' . $categorie_id . '&sous_categorie_id=' . $cid
                : 'index.php?categorie_id=' . $cid;
          ?>
          <div class="rail-item">
            <a class="rail-card" href="<?php echo e($clien); ?>">
              <div class="rail-visual">
                <?php /* LE REPLI SUR L'ICÔNE si le fichier manque. Ce dépôt l'avait,
                         je l'ai perdu en réécrivant le bandeau : six catégories
                         montraient un carré vide parce que leur image n'est plus sur
                         le disque. L'icône est posée dessous, masquée ; l'image
                         s'efface et la découvre. Même geste que ligne_produit_table.php. */ ?>
                <?php if ($cimg !== '') : ?>
                  <img src="<?php echo e($upload_base . ltrim($cimg, '/')); ?>" alt="" loading="lazy" decoding="async"
                       onerror="this.nextElementSibling.style.display='flex'; this.remove();">
                  <div class="rail-ph" style="display:none"><?php echo fpl_icone($cartes_sont_des_rayons ? 'package' : 'tool', 22); ?></div>
                <?php else : ?>
                  <div class="rail-ph"><?php echo fpl_icone($cartes_sont_des_rayons ? 'package' : 'tool', 22); ?></div>
                <?php endif; ?>
              </div>
              <?php // FPL natif affiche ici le CODE de la catégorie. Ce dépôt n'a
                    // pas encore cette colonne : la ligne reste, vide, pour que
                    // la carte garde exactement la même hauteur. ?>
              <div class="rail-meta"><span class="rail-num"></span></div>
              <div class="rail-name" title="<?php echo fpl_e($cnom); ?>"><?php echo fpl_e($cnom); ?></div>
            </a>
            <?php if ($peut_gerer && !$cartes_sont_des_rayons) : ?>
              <?php // Modifier ou retirer une catégorie sans quitter le catalogue
                    // (les écrans de ce dépôt pour les catégories racines). ?>
              <div class="rail-actions">
                <a href="../categories/modifier.php?id=<?php echo $cid; ?>" class="rail-edit"
                   title="Modifier « <?php echo fpl_e($cnom); ?> » — nom, image…">
                  <?php echo fpl_icone('edit', 12); ?>
                </a>
                <a href="../categories/supprimer.php?id=<?php echo $cid; ?>" class="rail-edit rail-del"
                   title="Supprimer « <?php echo fpl_e($cnom); ?> »">
                  <?php echo fpl_icone('trash', 12); ?>
                </a>
              </div>
            <?php elseif ($peut_gerer) : ?>
              <?php // Le rayon : crayon et poubelle comme chez FPL natif, vers
                    // rayon.php (nom, mots-clés, description, image). La
                    // suppression est un POST avec jeton, confirmé d'abord. ?>
              <div class="rail-actions">
                <a href="rayon.php?id=<?php echo $cid; ?>" class="rail-edit"
                   title="Modifier « <?php echo fpl_e($cnom); ?> » — nom, image…">
                  <?php echo fpl_icone('edit', 12); ?>
                </a>
                <form method="POST" action="rayon.php"
                      onsubmit="return confirm('Supprimer « <?php echo e(addslashes(fpl_texte($cnom))); ?> » ? Ses pièces restent, sans ce classement.')">
                  <input type="hidden" name="csrf_token" value="<?php echo e((string) ($_SESSION['admin_csrf'] ?? '')); ?>">
                  <input type="hidden" name="action" value="supprimer">
                  <input type="hidden" name="id" value="<?php echo $cid; ?>">
                  <button type="submit" class="rail-edit rail-del" title="Supprimer « <?php echo fpl_e($cnom); ?> »">
                    <?php echo fpl_icone('trash', 12); ?>
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($peut_gerer && !$cartes_sont_des_rayons) : ?>
          <a class="rail-card rail-new" href="../categories/ajouter.php">
            <div class="rail-visual new"><?php echo fpl_icone('plus', 20); ?></div>
            <div class="rail-name">Nouvelle catégorie</div>
          </a>
        <?php elseif ($peut_gerer) : ?>
          <a class="rail-card rail-new" href="rayon.php?categorie_id=<?php echo $categorie_id; ?>">
            <div class="rail-visual new"><?php echo fpl_icone('plus', 20); ?></div>
            <div class="rail-name">Nouvelle sous-catégorie</div>
          </a>
        <?php endif; ?>
      </div>

      <button type="button" class="rail-arrow" data-dir="1" title="Suivant"><?php echo fpl_icone('chevron-right', 16); ?></button>
    </div>
    <?php endif; ?>

    <?php /* LA RECHERCHE EN DIRECT DE CE DÉPÔT, remise en place. Elle affiche les
             résultats au fil de la frappe, sans recharger la page, dans un second
             tableau qui prend la place du premier. Le JavaScript cherche le
             formulaire ET le tableau à l'intérieur de ce bloc : les deux cartes
             sont donc enveloppées ensemble.
             Le contexte « fpl » fait rendre au point AJAX les mêmes lignes que le
             tableau principal — sinon les résultats changeraient de forme en
             cours de frappe. */ ?>
    <div data-produits-index-page
         data-ajax-url="ajax_live_search.php"
         data-ajax-context="fpl"
         data-total-catalog="<?php echo (int) $total; ?>"
         data-id-main-wrap="fpl-main-wrap"
         data-id-main-grid="fpl-table-body"
         data-id-live-wrap="fpl-live-wrap"
         data-id-live-grid="fpl-live-body"
         data-id-live-empty="fpl-live-empty"
         data-id-live-meta="fpl-live-meta"
         data-id-pagination="fpl-pagination"
         data-id-count="fpl-count"
         data-id-catalog-empty="fpl-catalog-empty">

    <div class="card filtre-complet" style="margin-bottom:var(--s4)">
      <form method="GET" action="index.php" class="fc-ligne" data-produits-index-form>
        <?php if ($categorie_id > 0) : ?><input type="hidden" name="categorie_id" value="<?php echo $categorie_id; ?>"><?php endif; ?>
        <?php if ($sous_categorie_id > 0) : ?><input type="hidden" name="sous_categorie_id" value="<?php echo $sous_categorie_id; ?>"><?php endif; ?>

        <div class="fc-champ fc-recherche">
          <label for="fc-q">Recherche</label>
          <input type="text" id="fc-q" name="recherche" value="<?php echo e($q); ?>"
                 placeholder="Nom, référence FPL, réf. OEM, marque, emplacement…"
                 autocomplete="off" inputmode="search" data-produits-index-search>
        </div>

        <div class="fc-champ">
          <label for="catfind">Catégorie</label>
          <div class="catfind">
            <input type="text" id="catfind" autocomplete="off"
                   placeholder="<?php echo $categorie_courante_nom !== '' ? fpl_e($categorie_courante_nom) : 'Toutes'; ?>">
            <div class="catfind-panel" id="catfind-panel" hidden></div>
          </div>
        </div>

        <?php /* MARQUE ET FOURNISSEUR — l'apport de ce dépôt. Ils ne figurent
                 pas chez FPL natif, mais ils servent ici : on cherche souvent
                 « toutes les pièces IVECO » ou « tout ce qui vient de NPR ».
                 Chacun ne s'affiche que si sa table existe et qu'elle a du monde. */ ?>
        <?php if ($marques_filtre !== []) : ?>
        <div class="fc-champ">
          <label for="fc-marque">Marque</label>
          <select id="marque_id" name="marque_id">
            <option value="0">Toutes</option>
            <?php foreach ($marques_filtre as $m_ligne) : ?>
              <option value="<?php echo (int) $m_ligne['id']; ?>" <?php echo $marque_id === (int) $m_ligne['id'] ? 'selected' : ''; ?>>
                <?php echo fpl_e($m_ligne['nom']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <?php if ($fournisseurs_filtre !== []) : ?>
        <div class="fc-champ">
          <label for="fc-fournisseur">Fournisseur</label>
          <select id="fournisseur_id" name="fournisseur_id">
            <option value="0">Tous</option>
            <?php foreach ($fournisseurs_filtre as $f_ligne) : ?>
              <option value="<?php echo (int) $f_ligne['id']; ?>" <?php echo $fournisseur_id === (int) $f_ligne['id'] ? 'selected' : ''; ?>>
                <?php echo fpl_e($f_ligne['nom']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="fc-champ fc-date">
          <label for="fc-du">Ajoutées du</label>
          <input type="date" id="fc-du" name="du" value="<?php echo e($du); ?>">
        </div>
        <div class="fc-champ fc-date">
          <label for="fc-au">au</label>
          <input type="date" id="fc-au" name="au" value="<?php echo e($au); ?>">
        </div>

        <?php // Les actions descendent sur leur propre ligne, à droite : les six
              // champs gardent ainsi la ligne du haut pour eux seuls. ?>
        <?php /* CE QUI MANQUE (31/08) : la question qu'on se pose vraiment
                 devant un catalogue de 3 259 pièces — qu'est-ce qu'il faut
                 recommander ? Une case, et la liste ne garde que ça. */ ?>
        <div class="fc-champ fc-manque">
          <label class="fc-manque__case">
            <input type="checkbox" name="manque" value="1" <?php echo $filtre_manque ? 'checked' : ''; ?>
                   onchange="this.form.submit()">
            <span>Seulement ce qui manque</span>
          </label>
        </div>

        <div class="fc-actions">
          <button type="submit" class="btn btn-primary"><?php echo fpl_icone('search', 14); ?> Filtrer</button>
          <?php /* L'export ne sait pas filtrer « ce qui manque » (31/08) :
                   on ne lui passe donc pas ce filtre, plutôt que de promettre
                   un fichier qui contiendrait tout le catalogue. */ ?>
          <a href="export-catalogue.php<?php $filtres_export = array_diff_key($filtres_url, ['manque' => 1]); echo $filtres_export ? '?' . e(http_build_query($filtres_export)) : ''; ?>"
             class="btn btn-outline"><?php echo fpl_icone('download', 14); ?> Exporter</a>
        </div>
      </form>

      <?php if ($filtre_manque) : ?>
        <p class="fc-manque__note">
          <?php echo (int) $pagination['total']; ?> pièce(s) à zéro ou arrivée(s) à leur seuil, la plus basse d'abord.
        </p>
      <?php endif; ?>

      <?php if ($q !== '' || $du !== '' || $au !== '' || $sous_categorie_id > 0 || $marque_id > 0 || $fournisseur_id > 0) : ?>
        <div class="fc-actifs">
          <span class="muted">Filtres :</span>
          <?php if ($q !== '') : ?><span class="cat-tag">« <?php echo e($q); ?> »</span><?php endif; ?>
          <?php if ($sous_categorie_courante_nom !== '') : ?><span class="cat-tag"><?php echo fpl_e($sous_categorie_courante_nom); ?></span><?php endif; ?>
          <?php // La marque et le fournisseur retenus se rappellent eux aussi. ?>
          <?php if ($marque_id > 0) : ?>
            <?php foreach ($marques_filtre as $m_ligne) : ?>
              <?php if ((int) $m_ligne['id'] === $marque_id) : ?><span class="cat-tag"><?php echo fpl_e($m_ligne['nom']); ?></span><?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($fournisseur_id > 0) : ?>
            <?php foreach ($fournisseurs_filtre as $f_ligne) : ?>
              <?php if ((int) $f_ligne['id'] === $fournisseur_id) : ?><span class="cat-tag"><?php echo fpl_e($f_ligne['nom']); ?></span><?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($du !== '') : ?><span class="cat-tag">du <?php echo date('d/m/Y', strtotime($du)); ?></span><?php endif; ?>
          <?php if ($au !== '') : ?><span class="cat-tag">au <?php echo date('d/m/Y', strtotime($au)); ?></span><?php endif; ?>
          <a href="<?php echo e($retour_catalogue); ?>" class="fc-effacer">Tout effacer</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <h2>
          <?php echo (int) $total; ?> pièce(s)
          <?php if ($q !== '') : ?> pour « <?php echo e($q); ?> »
          <?php elseif ($sous_categorie_courante_nom !== '') : ?> dans <?php echo fpl_e($sous_categorie_courante_nom); ?>
          <?php elseif ($categorie_courante_nom !== '') : ?> dans <?php echo fpl_e($categorie_courante_nom); ?>
          <?php endif; ?>
          <?php // Le compte que la recherche en direct met à jour au fil de la frappe. ?>
          <span id="fpl-count" hidden>(<?php echo (int) $total; ?>)</span>
        </h2>

        <?php if ($sous_categorie_id > 0 && $peut_gerer) : ?>
          <a href="ajouter.php?categorie_id=<?php echo $categorie_id; ?>&sous_categorie_id=<?php echo $sous_categorie_id; ?>" class="btn btn-blue">
            <?php echo fpl_icone('plus', 14); ?> Ajouter une pièce
          </a>
        <?php endif; ?>
      </div>

      <div id="fpl-catalog-empty"<?php echo $produits !== [] ? ' hidden' : ''; ?>>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('tool', 34); ?></span>
          <?php if ($q !== '') : ?>
            Aucune pièce ne correspond à « <?php echo e($q); ?> ».
          <?php elseif ($categorie_id > 0 && $sous_categorie_id <= 0) : ?>
            Aucune pièce dans cette catégorie — ouvrez une sous-catégorie ci-dessus pour en ajouter.
          <?php elseif ($sous_categorie_id > 0) : ?>
            Aucune pièce ici.
            <?php if ($peut_gerer) : ?><br><a href="ajouter.php?categorie_id=<?php echo $categorie_id; ?>&sous_categorie_id=<?php echo $sous_categorie_id; ?>">Ajouter la première</a><?php endif; ?>
          <?php else : ?>
            Le catalogue est vide.
          <?php endif; ?>
        </div>
      </div>

      <div id="fpl-main-wrap"<?php echo $produits === [] ? ' hidden' : ''; ?>>
        <?php if ($produits !== []) : ?>
          <?php echo fpl_tablebar_haut($pagination, 'pièces'); ?>
        <?php endif; ?>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:56px"></th>
                <th>Pièce</th>
                <th>Marque</th>
                <th><?php echo pf_champ_visible('fournisseur_id') ? 'Fournisseur' : 'Catégorie'; ?></th>
                <th>Référence</th>
                <th class="num">Stock</th>
                <th style="width:190px; text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody id="fpl-table-body">
              <?php foreach ($produits as $produit) : ?>
                <?php include __DIR__ . '/includes/ligne_piece_fpl.php'; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($produits !== []) : ?>
          <?php echo fpl_pager($pagination); ?>
        <?php endif; ?>
        <nav id="fpl-pagination" hidden></nav>
      </div>

      <?php /* LE SECOND TABLEAU — celui de la recherche en direct. Il prend la
               place du premier pendant la frappe, avec les MÊMES colonnes et les
               mêmes lignes, puis s'efface quand on vide le champ. */ ?>
      <div id="fpl-live-wrap" hidden>
        <p class="muted" id="fpl-live-meta" aria-live="polite" hidden style="margin-bottom:var(--s3)"></p>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:56px"></th>
                <th>Pièce</th>
                <th>Marque</th>
                <th><?php echo pf_champ_visible('fournisseur_id') ? 'Fournisseur' : 'Catégorie'; ?></th>
                <th>Référence</th>
                <th class="num">Stock</th>
                <th style="width:190px; text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody id="fpl-live-body"></tbody>
          </table>
        </div>
        <div class="empty" id="fpl-live-empty" hidden>
          <span class="big"><?php echo fpl_icone('search', 34); ?></span>
          Aucune pièce ne correspond. Modifiez les mots ou élargissez les filtres.
        </div>
      </div>
    </div>

    </div><!-- [data-produits-index-page] -->

    </div><!-- .page-produits-admin -->

    <?php include '../includes/footer.php'; ?>

    <?php /* LES DEUX SCRIPTS DE CE DÉPÔT, remis : la recherche en direct et la
             visionneuse de photos. Ils étaient tombés quand la page a été
             refaite. On ne les modifie pas — le balisage ci-dessus leur donne
             ce qu'ils attendent. */ ?>
    <script src="<?php echo e(fpl_script_src('admin-produits-index-search.js')); ?><?php echo asset_version_query(); ?>"></script>
    <script src="<?php echo e(fpl_script_src('admin-produits-gallery-lightbox.js')); ?><?php echo asset_version_query(); ?>"></script>

    <?php // LA FENÊTRE DE CONFIRMATION DE SUPPRESSION de ce dépôt, gardée telle
          // quelle : elle est plus sûre que le confirm() du navigateur, et son
          // texte dit la vérité sur ce que fait la suppression ici. ?>
    <div class="delete-confirm-overlay" id="deleteConfirmOverlay"></div>
    <div class="delete-confirm-modal" id="deleteConfirmModal" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
        <div class="delete-confirm-modal__icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 class="delete-confirm-modal__title" id="deleteConfirmTitle">Confirmer la suppression</h3>
        <p class="delete-confirm-modal__text">Êtes-vous sûr de vouloir supprimer cette pièce ?</p>
        <div class="delete-confirm-modal__product" id="deleteConfirmProduct"></div>
        <p class="delete-confirm-modal__warning"><i class="fas fa-info-circle"></i> Cette action est irréversible</p>
        <div class="delete-confirm-modal__actions">
            <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--cancel" id="deleteConfirmCancel">
                <i class="fas fa-times"></i> Annuler
            </button>
            <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--confirm" id="deleteConfirmConfirm">
                <i class="fas fa-trash"></i> Confirmer
            </button>
        </div>
    </div>

<style>
  /* Le seul habillage que la feuille commune n'a pas : le panneau de
     suggestions du champ « Catégorie ». Repris tel quel de FPL natif. */
  .catfind { position: relative; }
  .catfind-panel {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 70;
    min-width: 260px;
    background: var(--surface); border: 1px solid var(--line); border-radius: var(--r);
    box-shadow: var(--sh-3); overflow: hidden; max-height: 300px; overflow-y: auto;
  }
  .catfind-item {
    display: flex; align-items: center; gap: 9px; padding: 9px 12px;
    font-size: 15px; color: var(--ink); cursor: pointer;
  }
  .catfind-item:hover, .catfind-item.act { background: var(--blue-tint); text-decoration: none; }
  .catfind-item .ci-ico { color: var(--blue-600); display: flex; flex-shrink: 0; }
  .catfind-item strong { color: var(--navy); }
  .catfind-item .ci-parent { color: var(--slate); font-size: 14px; }
  .catfind-empty { padding: var(--s3); color: var(--slate); font-size: 14.5px; text-align: center; }
</style>

<script>
  // Aller droit à une catégorie : suggestions en direct sous le champ
  (function () {
    const input = document.getElementById('catfind');
    const panel = document.getElementById('catfind-panel');
    if (!input) return;
    let timer = null;
    let idx = -1;

    function esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function render(list) {
      panel.innerHTML = '';
      idx = -1;
      if (!list.length) {
        panel.innerHTML = '<div class="catfind-empty">Aucune catégorie ne correspond.</div>';
        panel.hidden = false;
        return;
      }
      list.forEach(c => {
        const a = document.createElement('a');
        a.className = 'catfind-item';
        a.href = c.url;
        a.innerHTML =
          '<span class="ci-ico">' +
            (c.sous
              ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>'
              : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>') +
          '</span>' +
          '<span><strong>' + esc(c.name) + '</strong>' +
            (c.parent ? ' <span class="ci-parent">dans ' + esc(c.parent) + '</span>' : '') +
          '</span>';
        panel.appendChild(a);
      });
      panel.hidden = false;
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = this.value.trim();
      if (q.length < 2) { panel.hidden = true; return; }
      timer = setTimeout(async () => {
        try {
          const r = await fetch('ajax_recherche_categories.php?q=' + encodeURIComponent(q));
          if (r.ok) render((await r.json()).categories);
        } catch (e) { /* réseau indisponible */ }
      }, 180);
    });

    input.addEventListener('keydown', function (e) {
      const items = panel.querySelectorAll('.catfind-item');
      if (panel.hidden || !items.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach((it, i) => it.classList.toggle('act', i === idx));
      } else if (e.key === 'Enter') {
        e.preventDefault();
        (items[idx] || items[0]).click();
      } else if (e.key === 'Escape') {
        panel.hidden = true;
      }
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.catfind')) panel.hidden = true;
    });
  })();

  // Défilement horizontal de la ligne de catégories
  (function () {
    const rail = document.getElementById('rail');
    if (!rail) return;
    document.querySelectorAll('.rail-arrow').forEach(btn => {
      btn.addEventListener('click', () => {
        rail.scrollBy({ left: (+btn.dataset.dir) * 340, behavior: 'smooth' });
      });
    });
    function updateArrows() {
      const max = rail.scrollWidth - rail.clientWidth - 2;
      const g = document.querySelector('.rail-arrow[data-dir="-1"]');
      const d = document.querySelector('.rail-arrow[data-dir="1"]');
      if (g) g.disabled = rail.scrollLeft <= 0;
      if (d) d.disabled = rail.scrollLeft >= max;
    }
    rail.addEventListener('scroll', updateArrows);
    window.addEventListener('resize', updateArrows);
    window.addEventListener('load', updateArrows);
    updateArrows();
  })();

  /* LA LIGNE ENTIÈRE MÈNE À LA FICHE — geste de ce dépôt, remis.
     On s'écarte quand le clic vise un bouton d'action, la vignette (qui ouvre
     la galerie) ou un lien : ceux-là ont déjà leur destination. La délégation
     sur le document couvre aussi les lignes que la recherche en direct ajoute
     après coup. */
  (function () {
    function cible(e) {
      if (e.target.closest('.row-actions, .fpl-thumb-btn, a, button, form')) return null;
      var tr = e.target.closest('.fpl-ligne-cliquable');
      return tr ? tr.getAttribute('data-href') : null;
    }

    document.addEventListener('click', function (e) {
      var href = cible(e);
      if (href) window.location.href = href;
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      if (!e.target.classList || !e.target.classList.contains('fpl-ligne-cliquable')) return;
      var href = e.target.getAttribute('data-href');
      if (href) { e.preventDefault(); window.location.href = href; }
    });
  })();

  // La fenêtre de confirmation de suppression — reprise de la page d'origine
  document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('deleteConfirmOverlay');
    var modal = document.getElementById('deleteConfirmModal');
    var cible = document.getElementById('deleteConfirmProduct');
    var annuler = document.getElementById('deleteConfirmCancel');
    var confirmer = document.getElementById('deleteConfirmConfirm');
    var lienCourant = null;
    if (!overlay || !modal) return;

    function fermer() {
      overlay.classList.remove('visible');
      modal.classList.remove('visible', 'animated');
      lienCourant = null;
    }

    document.addEventListener('click', function (e) {
      var lien = e.target.closest('a[data-delete-confirm="true"]');
      if (!lien) return;
      e.preventDefault();
      lienCourant = lien;
      cible.textContent = lien.getAttribute('data-delete-name') || 'cette pièce';
      overlay.classList.add('visible');
      modal.classList.add('visible', 'animated');
      annuler.focus();
    });

    annuler.addEventListener('click', fermer);
    overlay.addEventListener('click', fermer);
    confirmer.addEventListener('click', function () {
      if (lienCourant) window.location.href = lienCourant.href;
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('visible')) fermer();
    });
  });
</script>
