<?php
/**
 * TOUTES LES ÉTIQUETTES — une seule page pour retrouver et imprimer
 * n'importe quelle étiquette : pièces et barres, avec l'état d'impression
 * (le filtre « à imprimer » liste ce qui reste à faire).
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/etiquettes.php (24/08/2026) — le squelette est
 * le sien (deux onglets, barre de filtres, état d'impression, Marquer /
 * Retirer, Imprimer), le moteur est celui de ce dépôt :
 *   - l'étiquette d'une PIÈCE s'imprime depuis sa fiche (le bloc étiquette
 *     de ajuster-stock.php) — « Imprimer » y mène droit ;
 *   - les BARRES sont les nœuds du niveau `barre` de la hiérarchie libre,
 *     leur étiquette s'imprime dans parametres/emplacement-noeud-etiquette.php ;
 *   - la trace vit dans `etiquette_impressions`
 *     (migrations/run_etiquette_impressions.php) et s'écrit aussi toute
 *     seule quand on lance l'impression depuis la fiche (ajax_etiquette_imprimee.php).
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
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

// Qui peut marquer / imprimer : tout compte non restreint du module stock.
$peut_imprimer = !admin_is_restricted_admin_account();

// Marquer / retirer une trace d'impression à la main
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jeton = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $jeton_ok = $jeton !== '' && hash_equals((string) $_SESSION['admin_csrf'], $jeton);
    $type = isset($_POST['type']) && $_POST['type'] === 'barre' ? 'noeud' : 'produit';
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($jeton_ok && $peut_imprimer && $id > 0) {
        if (isset($_POST['retirer'])) {
            etiquette_retirer_derniere_impression($type, $id);
            $_SESSION['success_message'] = 'Dernier marquage retiré.';
        } else {
            etiquette_tracer_impression($type, $id, null, (int) $_SESSION['admin_id'], true);
            $_SESSION['success_message'] = 'Étiquette marquée comme imprimée.';
        }
    }
    $retour_post = isset($_POST['retour']) ? (string) $_POST['retour'] : 'etiquettes.php';
    // Jamais ailleurs que sur cette page (la valeur vient d'un champ caché)
    if (strpos($retour_post, 'etiquettes.php') !== 0) {
        $retour_post = 'etiquettes.php';
    }
    header('Location: ' . $retour_post);
    exit;
}

$type = isset($_GET['type']) && $_GET['type'] === 'barres' ? 'barres' : 'pieces';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$etat = isset($_GET['etat']) && in_array($_GET['etat'], ['a_imprimer', 'imprimees'], true) ? $_GET['etat'] : null;
$du = isset($_GET['du']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['du']) ? $_GET['du'] : null;
$au = isset($_GET['au']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['au']) ? $_GET['au'] : null;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

$pieces = $type === 'pieces' ? etiquettes_pieces_liste($q, $etat, $du, $au, $page, fpl_par_page('etiquettes_pieces', 20)) : null;
$barres = $type === 'barres' ? etiquettes_barres_liste($q, $etat, $du, $au, $page, fpl_par_page('etiquettes_barres', 20)) : null;

$url_courante = 'etiquettes.php?' . http_build_query(array_filter([
    'type' => $type, 'q' => $q !== '' ? $q : null, 'etat' => $etat, 'du' => $du, 'au' => $au,
    'page' => $page > 1 ? $page : null,
]));

$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$fpl_titre_page = 'Toutes les étiquettes';
$fpl_retour_page = 'index.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toutes les étiquettes — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

    <?php if ($success_message) : ?>
      <div class="alert alert-success" role="status"><?php echo e($success_message); ?></div>
    <?php endif; ?>

    <div class="lab-tabs">
      <a href="etiquettes.php?type=pieces" class="lab-tab <?php echo $type === 'pieces' ? 'on' : ''; ?>">
        <div class="lab-tab-title">
          <?php echo fpl_icone('tag', 15); ?> Étiquettes de pièce
        </div>
        <span class="lab-tab-sub">Collées sur la pièce — photo, code FPL, QR</span>
      </a>
      <a href="etiquettes.php?type=barres" class="lab-tab <?php echo $type === 'barres' ? 'on' : ''; ?>">
        <div class="lab-tab-title">
          <?php echo fpl_icone('map-pin', 15); ?> Étiquettes de barre
        </div>
        <span class="lab-tab-sub">Nomment les barres du rangement</span>
      </a>
    </div>

    <div class="card">
      <form method="GET" action="etiquettes.php" class="fc-ligne fc-ligne-etiquettes" style="margin-bottom:var(--s3)">
        <input type="hidden" name="type" value="<?php echo $type; ?>">
        <?php if ($etat) : ?><input type="hidden" name="etat" value="<?php echo e($etat); ?>"><?php endif; ?>

        <div class="fc-champ fc-recherche">
          <label for="fc-q">Recherche</label>
          <input type="text" id="fc-q" name="q" value="<?php echo e($q); ?>"
                 placeholder="<?php echo $type === 'pieces' ? 'Nom, référence FPL, réf. OEM…' : 'Nom, numéro, code scan…'; ?>">
        </div>
        <div class="fc-champ">
          <label for="fc-du">Créées du</label>
          <input type="date" id="fc-du" name="du" value="<?php echo e((string) $du); ?>">
        </div>
        <div class="fc-champ">
          <label for="fc-au">au</label>
          <input type="date" id="fc-au" name="au" value="<?php echo e((string) $au); ?>">
        </div>
        <button type="submit" class="btn btn-primary"><?php echo fpl_icone('search', 14); ?> Filtrer</button>
      </form>

      <?php if ($q !== '' || $du || $au) : ?>
        <div class="fc-actifs" style="margin-bottom:var(--s3); margin-top:0; padding-top:0; border-top:none">
          <span class="muted">Filtres :</span>
          <?php if ($q !== '') : ?><span class="cat-tag">« <?php echo e($q); ?> »</span><?php endif; ?>
          <?php if ($du) : ?><span class="cat-tag">du <?php echo date('d/m/Y', strtotime($du)); ?></span><?php endif; ?>
          <?php if ($au) : ?><span class="cat-tag">au <?php echo date('d/m/Y', strtotime($au)); ?></span><?php endif; ?>
          <a href="etiquettes.php?type=<?php echo $type; ?>" class="fc-effacer">Tout effacer</a>
        </div>
      <?php endif; ?>

      <?php if ($peut_imprimer) : ?>
        <div style="display:flex; gap:6px; margin-bottom:var(--s3); flex-wrap:wrap">
          <?php foreach ([null => 'Toutes', 'a_imprimer' => 'À imprimer', 'imprimees' => 'Imprimées'] as $val => $lib) : ?>
            <?php $lien_etat = 'etiquettes.php?' . http_build_query(array_filter(['type' => $type, 'q' => $q !== '' ? $q : null, 'du' => $du, 'au' => $au, 'etat' => $val])); ?>
            <a href="<?php echo e($lien_etat); ?>"
               class="btn btn-sm <?php echo $etat === $val ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $lib; ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($type === 'pieces') : ?>
        <?php if ($pieces['lignes'] === []) : ?>
          <div class="empty">
            <span class="big"><?php echo fpl_icone('tag', 32); ?></span>
            Aucune pièce<?php echo $q !== '' ? ' ne correspond à « ' . e($q) . ' »' : ''; ?>.
          </div>
        <?php else : ?>
          <?php echo fpl_tablebar_haut($pieces, 'pièces'); ?>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th style="width:52px"></th>
                  <th>Pièce</th>
                  <th>Référence</th>
                  <th>Rangement</th>
                  <?php if ($peut_imprimer) : ?>
                    <th>État d'impression</th>
                  <?php endif; ?>
                  <th style="width:180px; text-align:center">Étiquette</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pieces['lignes'] as $p) : ?>
                  <?php $trace = etiquette_derniere_impression('produit', (int) $p['id']); ?>
                  <?php /* D'abord le CHOIX DE LA TAILLE, puis l'étiquette —
                           le parcours de FPL natif (24/08). */ ?>
                  <?php $cible_etiquette = 'etiquette-piece-choisir.php?id=' . (int) $p['id']; ?>
                  <tr>
                    <td>
                      <?php if (!empty($p['image_principale'])) : ?>
                        <img class="thumb" style="width:40px; height:40px; object-fit:cover; border-radius:6px"
                             src="../../upload/<?php echo e(ltrim((string) $p['image_principale'], '/')); ?>" alt="" loading="lazy">
                      <?php else : ?>
                        <div class="thumb" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center">
                          <?php echo fpl_icone('tool', 14); ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <a class="cell-title" href="ajuster-stock.php?id=<?php echo (int) $p['id']; ?>" style="color:var(--ink)"><?php echo fpl_e($p['nom']); ?></a>
                      <div class="cell-sub"><span class="chip-code"><?php echo e(fpl_code_afficher((string) $p['identifiant_interne'])); ?></span></div>
                    </td>
                    <?php /* LA RÉFÉRENCE QU'ON A (31/08) : la référence d'origine
                             (OEM) n'est renseignée que sur 1 pièce du catalogue,
                             celle du fournisseur sur 2126 — la colonne affichait
                             « — » partout alors que la fiche, elle, montrait la
                             réf. fournisseur. On montre celle qui existe, et on
                             dit laquelle. */ ?>
                    <?php
                      $ref_oem = trim((string) ($p['reference_oem'] ?? ''));
                      $ref_fou = trim((string) ($p['reference_fournisseur'] ?? ''));
                      $ref_aff = $ref_oem !== '' ? $ref_oem : $ref_fou;
                    ?>
                    <td class="mono" style="font-size:14px"><?php
                        if ($ref_aff !== '') {
                            echo fpl_e($ref_aff);
                            if ($ref_oem === '') {
                                echo ' <span class="muted" style="font-size:11px">fourn.</span>';
                            }
                        } else {
                            echo '—';
                        }
                    ?></td>
                    <td class="muted">
                      <?php echo fpl_e(($p['categorie_nom'] ?: '') . ($p['categorie_nom'] && $p['sous_categorie_nom'] ? ' › ' : '') . ($p['sous_categorie_nom'] ?: ($p['categorie_nom'] ? '' : '—'))); ?>
                    </td>
                    <?php if ($peut_imprimer) : ?>
                      <td>
                        <?php if ($trace) : ?>
                          <span class="lab-etat lab-etat--faite">
                            <span class="lab-point lab-point--faite"></span> Imprimée
                          </span>
                          <div class="cell-sub lab-etat-sub">
                            <?php echo date('d/m/Y H:i', strtotime($trace['date_impression'])); ?>
                            <span class="lab-etat-par">par <?php echo $trace['admin_nom'] !== '' ? e($trace['admin_nom']) : '—'; ?><?php echo $trace['manuel'] ? ' (marquée)' : ''; ?></span>
                          </div>
                        <?php else : ?>
                          <span class="lab-etat lab-etat--afaire">
                            <span class="lab-point lab-point--afaire"></span> À imprimer
                          </span>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                    <td>
                      <div class="row-actions">
                        <a href="ajuster-stock.php?id=<?php echo (int) $p['id']; ?>" class="btn btn-outline btn-sm" title="Voir la fiche de la pièce">
                          Détails
                        </a>
                        <?php /* L'ŒIL A ÉTÉ RETIRÉ (31/08) : chez FPL natif il ouvre
                                 une page d'étiquette à part (etiquette-piece.php) ;
                                 ici l'étiquette vit DANS la fiche, il menait donc à
                                 la même page que « Détails », à l'ancre près.
                                 « Imprimer » conduit déjà à l'étiquette. */ ?>
                        <?php if ($peut_imprimer) : ?>
                          <?php if ($trace) : ?>
                            <form method="POST" action="etiquettes.php"
                                  onsubmit="return confirm('Retirer le dernier marquage « imprimée » de cette étiquette ?')">
                              <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
                              <input type="hidden" name="retour" value="<?php echo e($url_courante); ?>">
                              <input type="hidden" name="retirer" value="1">
                              <input type="hidden" name="type" value="piece">
                              <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                              <button type="submit" class="btn btn-outline btn-sm btn-icon" title="Retirer le dernier marquage">
                                <?php echo fpl_icone('rotate-ccw', 13); ?>
                              </button>
                            </form>
                          <?php else : ?>
                            <form method="POST" action="etiquettes.php">
                              <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
                              <input type="hidden" name="retour" value="<?php echo e($url_courante); ?>">
                              <input type="hidden" name="type" value="piece">
                              <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                              <button type="submit" class="btn btn-outline btn-sm" title="Étiquette déjà imprimée ailleurs : marquer sans réimprimer">
                                Marquer
                              </button>
                            </form>
                          <?php endif; ?>
                          <a href="<?php echo e($cible_etiquette); ?>" class="btn btn-blue btn-sm">
                            <?php echo fpl_icone('printer', 13); ?> Imprimer
                          </a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php echo fpl_pager($pieces); ?>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($type === 'barres') : ?>
        <?php if ($barres['lignes'] === []) : ?>
          <div class="empty">
            <span class="big"><?php echo fpl_icone('map-pin', 32); ?></span>
            Aucune barre<?php echo $q !== '' ? ' ne correspond à « ' . e($q) . ' »' : ''; ?> —
            elles se créent dans <a href="../parametres/hierarchie-entrepot.php">la structure de l'entrepôt</a>.
          </div>
        <?php else : ?>
          <?php echo fpl_tablebar_haut($barres, 'barres'); ?>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Barre</th>
                  <th>Emplacement complet</th>
                  <?php if ($peut_imprimer) : ?>
                    <th>État d'impression</th>
                  <?php endif; ?>
                  <th style="width:230px; text-align:center">Étiquette</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($barres['lignes'] as $barre) : ?>
                  <?php $trace = etiquette_derniere_impression('noeud', (int) $barre['id']); ?>
                  <?php /* La page-ÉCRAN de l'étiquette (aperçu + Imprimer + PDF),
                           comme etiquette-bac.php chez FPL natif — avant, l'œil
                           téléchargeait le PDF brut sans aperçu (retour du 25/08). */ ?>
                  <?php $cible_barre = 'etiquette-barre.php?id=' . (int) $barre['id']; ?>
                  <tr>
                    <td>
                      <span class="chip-code" style="font-size:14px"><?php echo fpl_e($barre['nom'] !== '' ? $barre['nom'] : ('Barre ' . $barre['numero'])); ?></span>
                    </td>
                    <td class="muted"><?php echo fpl_e(entrepot_noeud_chemin_libelle((int) $barre['id'])); ?></td>
                    <?php if ($peut_imprimer) : ?>
                      <td>
                        <?php if ($trace) : ?>
                          <span class="lab-etat lab-etat--faite">
                            <span class="lab-point lab-point--faite"></span> Imprimée
                          </span>
                          <div class="cell-sub lab-etat-sub">
                            <?php echo date('d/m/Y H:i', strtotime($trace['date_impression'])); ?>
                            <span class="lab-etat-par">par <?php echo $trace['admin_nom'] !== '' ? e($trace['admin_nom']) : '—'; ?><?php echo $trace['manuel'] ? ' (marquée)' : ''; ?></span>
                          </div>
                        <?php else : ?>
                          <span class="lab-etat lab-etat--afaire">
                            <span class="lab-point lab-point--afaire"></span> À imprimer
                          </span>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                    <td>
                      <div class="row-actions">
                        <?php /* L'ŒIL ET « IMPRIMER » AVAIENT LA MÊME ADRESSE
                                 (31/08) : l'œil ne reste que pour les comptes
                                 restreints, qui n'ont pas le bouton d'impression
                                 et n'auraient sinon plus aucune porte. */ ?>
                        <?php if (!$peut_imprimer) : ?>
                        <a href="<?php echo e($cible_barre); ?>" class="btn btn-outline btn-sm btn-icon" title="Voir l'étiquette">
                          <?php echo fpl_icone('eye', 13); ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($peut_imprimer) : ?>
                          <?php if ($trace) : ?>
                            <form method="POST" action="etiquettes.php"
                                  onsubmit="return confirm('Retirer le dernier marquage « imprimée » de cette étiquette ?')">
                              <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
                              <input type="hidden" name="retour" value="<?php echo e($url_courante); ?>">
                              <input type="hidden" name="retirer" value="1">
                              <input type="hidden" name="type" value="barre">
                              <input type="hidden" name="id" value="<?php echo (int) $barre['id']; ?>">
                              <button type="submit" class="btn btn-outline btn-sm btn-icon" title="Retirer le dernier marquage">
                                <?php echo fpl_icone('rotate-ccw', 13); ?>
                              </button>
                            </form>
                          <?php else : ?>
                            <form method="POST" action="etiquettes.php">
                              <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
                              <input type="hidden" name="retour" value="<?php echo e($url_courante); ?>">
                              <input type="hidden" name="type" value="barre">
                              <input type="hidden" name="id" value="<?php echo (int) $barre['id']; ?>">
                              <button type="submit" class="btn btn-outline btn-sm" title="Étiquette déjà imprimée ailleurs : marquer sans réimprimer">
                                Marquer
                              </button>
                            </form>
                          <?php endif; ?>
                          <a href="<?php echo e($cible_barre); ?>" class="btn btn-blue btn-sm">
                            <?php echo fpl_icone('printer', 13); ?> Imprimer
                          </a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php echo fpl_pager($barres); ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    </div><!-- .page-produits-admin -->

<style>
  .lab-tabs { display: flex; gap: 14px; margin-bottom: 24px; flex-wrap: wrap; }
  .lab-tab {
    flex: 1; min-width: 240px;
    display: flex; flex-direction: column; align-items: flex-start; gap: 6px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 8px;
    padding: 16px 20px; color: var(--ink); text-decoration: none;
    transition: all .15s ease-in-out;
  }
  .lab-tab:hover { border-color: var(--blue); background: #FAFBFD; text-decoration: none; }
  .lab-tab.on { border-color: var(--navy); border-width: 2px; background: #F4F7FB; }
  .lab-tab-title { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 15.5px; color: var(--navy); }
  .lab-tab-sub { font-weight: 400; font-size: 12.5px; color: var(--slate); margin-left: 23px; }

  /* La barre de filtres de CETTE page : quatre blocs, pas six. */
  .fpl-catalogue .fc-ligne-etiquettes { grid-template-columns: minmax(220px, 2fr) minmax(150px, 1fr) minmax(150px, 1fr) auto; }
  @media (max-width: 900px) { .fpl-catalogue .fc-ligne-etiquettes { grid-template-columns: 1fr 1fr; } }

  .lab-etat {
    font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 12px;
    display: inline-flex; align-items: center; gap: 4px;
  }
  .lab-etat--faite { background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; }
  .lab-etat--afaire { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }
  .lab-point { display: inline-block; width: 6px; height: 6px; border-radius: 50%; }
  .lab-point--faite { background: #0EA5E9; }
  .lab-point--afaire { background: #94A3B8; }
  .lab-etat-sub { font-size: 11px; margin-top: 3px; color: #5A6A85; }
  .lab-etat-par { display: block; font-size: 10px; color: #8A9AAA; }
  .row-actions { display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: wrap; }
  .row-actions form { margin: 0; }
</style>

    <?php include '../includes/footer.php'; ?>
