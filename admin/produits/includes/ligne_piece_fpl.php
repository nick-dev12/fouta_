<?php
/**
 * UNE LIGNE DU TABLEAU DES PIÈCES — balisage de FPL natif.
 * Programmation procédurale uniquement
 *
 * Traduction fidèle du <tr> de fpl_natif/admin/pieces.php : vignette, nom en
 * lien avec son code en pastille dessous, marque, modèle, référence d'origine,
 * puis les actions dont « Détail » écrit en toutes lettres.
 *
 * Ce fichier NE REMPLACE PAS ligne_produit_table.php : celui-là sert encore la
 * recherche en direct et d'autres écrans, et on n'y touche pas.
 *
 * Variables attendues : $produit (array), $upload_base (string),
 *                       $fpl_modeles_noms (array id => nom)
 */
if (!isset($produit) || !is_array($produit)) {
    return;
}

/* CE FICHIER DOIT SE SUFFIRE À LUI-MÊME : la page du catalogue charge déjà ces
 * assistants, mais le point AJAX de la recherche en direct, lui, ne les charge
 * pas — et la ligne y était rendue avec « Call to undefined function e() ».
 * Même précaution que ligne_produit_table.php. */
if (!function_exists('e')) {
    require_once __DIR__ . '/../../../includes/fpl_ui.php';
}
if (!function_exists('fpl_e')) {
    require_once __DIR__ . '/../../../includes/fpl_texte.php';
}
/* LE FOURNISSEUR N'EST PAS DE TOUS LES PROFILS (31/08) : la table
 * produit_formulaire_champ_role réserve quatre champs — Fournisseur, Prix de
 * vente, Prix d'achat, Prix promotionnel — à tous les rôles SAUF le stock
 * simple. La colonne les respecte : au rayonniste elle montre la catégorie,
 * qui est toujours renseignée et ne dit rien de confidentiel. */
if (!function_exists('pf_champ_visible')) {
    require_once __DIR__ . '/../../../includes/produit_formulaire_champs.php';
}
/* LE STOCK SE LIT SUR LA LIGNE (31/08) : jusqu'ici il fallait ouvrir la fiche
 * d'une pièce pour savoir s'il en restait. La colonne montre le nombre ; quand
 * la pièce est à zéro, ou à son seuil ou en dessous, elle le dit — avec LE
 * SEUIL DE CETTE PIÈCE affiché à côté, parce que deux rayons n'ont pas le même
 * et qu'un « sous le seuil » sans le nombre ne veut rien dire. */
if (!function_exists('stock_alerte_seuil_effectif')) {
    require_once __DIR__ . '/../../../models/model_stock_alertes.php';
}

$upload_base = isset($upload_base) ? (string) $upload_base : '/upload/';
if ($upload_base !== '' && substr($upload_base, -1) !== '/') {
    $upload_base .= '/';
}

$fpl_modeles_noms = isset($fpl_modeles_noms) && is_array($fpl_modeles_noms) ? $fpl_modeles_noms : [];

$pid = (int) ($produit['id'] ?? 0);
$nom = (string) ($produit['nom'] ?? '');
$img = trim((string) ($produit['image_principale'] ?? ''));
$code = trim((string) ($produit['identifiant_interne'] ?? ''));
$marque = trim((string) ($produit['marque_libelle_catalogue'] ?? ''));
$oem = trim((string) ($produit['reference_oem'] ?? ''));

/* CE QUE LA COLONNE MONTRE VRAIMENT (31/08) : dans ce catalogue la référence
 * d'origine (OEM) n'est renseignée que sur 1 pièce sur 3259, et le modèle de
 * véhicule sur 1 aussi — deux colonnes vides sur cinq, alors que la fiche,
 * elle, affichait la réf. fournisseur (2126 pièces) et son nom (2674). D'où le
 * « les données s'affichent dans la fiche mais pas dans les listes ». La liste
 * montre donc le FOURNISSEUR à la place du modèle, et LA RÉFÉRENCE QU'ON A :
 * l'OEM d'abord, sinon celle du fournisseur, en le disant. Le modèle reste
 * calculé plus bas, prêt pour le jour où la donnée sera saisie. */
$ref_fournisseur = trim((string) ($produit['reference_fournisseur'] ?? ''));
$ref_affichee = $oem !== '' ? $oem : $ref_fournisseur;
$peut_voir_fournisseur = !function_exists('pf_champ_visible') || pf_champ_visible('fournisseur_id');
$fournisseur = $peut_voir_fournisseur ? trim((string) ($produit['nom_fournisseur'] ?? '')) : '';

$stock_piece = (int) ($produit['stock'] ?? 0);
$seuil_piece = null;
if (function_exists('stock_alerte_seuil_effectif')) {
    $seuil_piece = stock_alerte_seuil_effectif($produit)['seuil'];
}
$stock_manque = $stock_piece <= 0 || ($seuil_piece !== null && $stock_piece <= (int) $seuil_piece);
$colonne_libre = $peut_voir_fournisseur ? $fournisseur : trim((string) ($produit['categorie_nom'] ?? ''));

$modele_id = (int) ($produit['modele_id'] ?? 0);
$modele = ($modele_id > 0 && isset($fpl_modeles_noms[$modele_id])) ? $fpl_modeles_noms[$modele_id] : '';

// La fiche de la pièce, dans ce dépôt, c'est l'écran d'ajustement de stock —
// il porte le nom de la pièce et en montre les faits, comme la fiche de FPL.
$fiche = 'ajuster-stock.php?id=' . $pid;

/* LA GALERIE PHOTOS de ce dépôt : toutes les images de la pièce, pas seulement
 * la principale. C'est un apport de Fouta que FPL natif n'a pas au catalogue,
 * et qui rendait service — le clic sur la vignette les fait défiler en grand. */
$galerie = [];
if (function_exists('produits_galerie_web_urls')) {
    foreach (produits_galerie_web_urls($produit) as $une) {
        $une = (string) $une;
        if ($une === '') {
            continue;
        }
        // Les chemins relatifs sont ramenés à la racine publique, comme ailleurs.
        $galerie[] = ($une[0] === '/') ? $une : ($upload_base . ltrim($une, '/'));
    }
}
if ($galerie === [] && $img !== '') {
    $galerie[] = $upload_base . $img;
}
$galerie_json = htmlspecialchars(
    json_encode(array_values($galerie), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
?>
<?php /* LA LIGNE ENTIÈRE EST CLIQUABLE — geste de ce dépôt, gardé. Elle mène à
         la même fiche que le nom et que le bouton « Détail » : trois portes, une
         seule destination. Les boutons d'action et la vignette s'en excluent. */ ?>
<tr class="fpl-ligne-cliquable" data-href="<?php echo e($fiche); ?>" tabindex="0" role="link"
    aria-label="<?php echo e('Voir la fiche : ' . $nom); ?>">
  <td>
    <?php if ($img !== '') : ?>
      <?php // Les DEUX classes : celle que la visionneuse de ce dépôt écoute
            // (page-produits-table__thumb-btn), et la nôtre pour l'habillage.
            // Ainsi le JavaScript existant marche sans qu'on y touche. ?>
      <button type="button" class="page-produits-table__thumb-btn fpl-thumb-btn"
              data-produit-gallery="<?php echo $galerie_json; ?>"
              data-produit-nom="<?php echo e($nom); ?>"
              title="Voir la galerie photos"
              aria-label="<?php echo e('Voir la galerie photos : ' . $nom); ?>">
        <img class="thumb" src="<?php echo e($upload_base . $img); ?>" alt="<?php echo e($nom); ?>" loading="lazy" decoding="async"
             onerror="this.nextElementSibling.style.display='flex'; this.remove();">
        <span class="thumb" style="display:none;align-items:center;justify-content:center;color:var(--slate)"><i class="fas fa-box" aria-hidden="true"></i></span>
      </button>
    <?php else : ?>
      <?php // L'icône des pièces, la même que dans le menu de gauche. ?>
      <div class="thumb" style="display:flex;align-items:center;justify-content:center;color:var(--slate)"><i class="fas fa-box" aria-hidden="true"></i></div>
    <?php endif; ?>
  </td>
  <td>
    <a class="cell-title" href="<?php echo e($fiche); ?>" style="color:var(--ink)"><?php echo fpl_e($nom); ?></a>
    <?php if ($code !== '') : ?>
      <div class="cell-sub"><span class="chip-code"><?php echo e(fpl_code_afficher($code)); ?></span></div>
    <?php endif; ?>
  </td>
  <td>
    <?php if ($marque !== '') : ?>
      <span class="cell-title" style="font-weight:550"><?php echo fpl_e($marque); ?></span>
    <?php else : ?>
      <span class="muted">—</span>
    <?php endif; ?>
  </td>
  <td>
    <?php if ($colonne_libre !== '') : ?>
      <?php echo fpl_e($colonne_libre); ?>
    <?php else : ?>
      <span class="muted">—</span>
    <?php endif; ?>
  </td>
  <td class="mono" style="font-size:14px"><?php
      if ($ref_affichee !== '') {
          echo fpl_e($ref_affichee);
          if ($oem === '') {
              echo ' <span class="muted" style="font-size:11px">fourn.</span>';
          }
      } else {
          echo '—';
      }
  ?></td>
  <td class="num fpl-cell-stock<?php echo $stock_manque ? ' fpl-cell-stock--manque' : ''; ?>">
    <strong><?php echo $stock_piece; ?></strong>
    <?php if ($stock_manque && $seuil_piece !== null) : ?>
      <span class="fpl-cell-stock__seuil">/ <?php echo (int) $seuil_piece; ?></span>
    <?php endif; ?>
  </td>
  <td>
    <div class="row-actions">
      <a href="<?php echo e($fiche); ?>" class="btn btn-outline btn-sm btn-detail">
        <?php echo fpl_icone('eye', 13); ?> Détail
      </a>
      <?php // L'ÉTIQUETTE DE LA PIÈCE, bouton de FPL natif. Ce dépôt n'a pas
            // d'écran d'étiquette séparé : le bouton ouvre donc la fiche
            // directement sur son bloc d'étiquette, qui existe déjà. ?>
      <a href="<?php echo e($fiche); ?>#fpl-etiquette-print-root" class="btn btn-outline btn-sm btn-icon"
         title="Étiquette de la pièce"><?php echo fpl_icone('tag', 13); ?></a>
      <a href="modifier.php?id=<?php echo $pid; ?>" class="btn btn-outline btn-sm btn-icon" title="Modifier"><?php echo fpl_icone('edit', 13); ?></a>
      <?php // La suppression garde la fenêtre de confirmation de ce dépôt : elle
            // dit vrai (ici une pièce supprimée l'est pour de bon, images et QR
            // effacés du disque), là où la phrase de FPL natif parlerait d'un
            // historique conservé qui, chez nous, ne l'est pas. ?>
      <a href="supprimer.php?id=<?php echo $pid; ?>" class="btn btn-danger btn-sm btn-icon"
         data-delete-confirm="true" data-delete-name="<?php echo e($nom); ?>" title="Supprimer"><?php echo fpl_icone('trash', 13); ?></a>
    </div>
  </td>
</tr>
