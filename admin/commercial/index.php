<?php
/**
 * ACCUEIL DES COMMERCIAUX (10/09/2026) — commercial et commercial général.
 *
 * Avant, ce fichier n'était qu'un aiguillage : le commercial général arrivait
 * sur le hub des bons de livraison, le commercial sur les commandes du site,
 * une file restée vide depuis toujours (0 commande en production).
 * Mesuré sur foutasvr le 10/09 : leur travail réel, c'est la CAISSE (47
 * tickets préparés par les commerciaux généraux), puis les devis (10), puis
 * les bons de livraison (2).
 *
 * L'écran part donc de là : chercher une pièce (la recherche ouvre la vente
 * directe, qui montre le prix et le stock et où l'on ajoute au ticket), puis
 * les gestes du métier.
 *
 * RECENTRÉ LE 11/09/2026, décision de la direction : le commercial général n'a
 * pas à suivre ses tickets en attente de caisse, ses devis ouverts ni ses ventes
 * du mois ; suivre l'argent est le travail de la caisse et de la comptabilité.
 * Ont été retirés avec eux les compteurs du haut, les factures à relancer et les
 * devis sans réponse. L'accueil garde ce qui attend son geste (retours clients
 * en attente de caisse, bons de livraison en brouillon) et montre ce qui l'aide
 * au comptoir, mesuré en base : les pièces vendues presque épuisées, et les
 * pièces vendues sans prix au catalogue avec le dernier prix pratiqué.
 *
 * Programmation procédurale uniquement.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

/* Le compte « admin » restreint n'a ni caisse ni devis : il retourne chez lui. */
if (!admin_can_caisse_vendeur()) {
    admin_redirect_role_home();
}

require_once __DIR__ . '/../../includes/fpl_texte.php';  // fpl_e(), appelé par le menu
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../models/model_commercial_accueil.php';
require_once __DIR__ . '/../../models/model_devis.php';  // devis_statut_libelle()

$moi = (int) $_SESSION['admin_id'];
$peut_bl = admin_can_bl_retours_b2b();

$peut_retour = admin_can_preparer_retour_caisse();
$retours_attente = [];
$bl_brouillons = [];
$pieces_epuisees = [];
$pieces_sans_prix = [];
$lecture_ko = false;
try {
    $retours_attente = commercial_retours_en_attente($moi);
    $bl_brouillons = $peut_bl ? commercial_bl_brouillons($moi) : [];
    $pieces_epuisees = commercial_pieces_vendues_presque_epuisees(2, 90, 10);
    $pieces_sans_prix = commercial_pieces_vendues_sans_prix(90, 10);
} catch (Throwable $e) {
    error_log('[commercial/index] ' . $e->getMessage());
    $lecture_ko = true;
}

$fpl_titre_page = 'Accueil';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
</head>

<body class="fpl-catalogue">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="page-produits-admin page-commercial-accueil">

    <?php if ($lecture_ko) : ?>
      <div class="alert alert-warning" role="status">Vos files de travail n'ont pas pu être lues. La cause est inscrite dans le journal du serveur.</div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:var(--s4)">
      <form class="scan-bar" action="../caisse/index.php" method="get" role="search">
        <span style="color:var(--blue-600); display:flex"><?php echo fpl_icone('search', 20); ?></span>
        <input type="search" name="q" id="ca-q" autocomplete="off"
               placeholder="Quelle pièce ? Nom ou référence, scannez ou tapez">
        <button type="submit" class="btn btn-primary">Chercher</button>
      </form>
    </div>

    <div class="action-grid" style="margin-bottom:var(--s4)">
      <a class="action-btn" href="../caisse/index.php">
        <span class="big"><i class="fas fa-cash-register" aria-hidden="true"></i></span>
        <strong>Nouvelle vente</strong>
        <span>Préparer un ticket de caisse</span>
      </a>
      <?php if ($peut_retour) : ?>
      <a class="action-btn" href="../caisse/retour.php">
        <span class="big"><i class="fas fa-undo" aria-hidden="true"></i></span>
        <strong>Retour client</strong>
        <span>Mauvaise pièce ou pièce défectueuse</span>
      </a>
      <?php endif; ?>
      <a class="action-btn" href="../devis/devis.php?modal=devis">
        <span class="big"><i class="fas fa-file-invoice" aria-hidden="true"></i></span>
        <strong>Nouveau devis</strong>
        <span>Chiffrer pour un client</span>
      </a>
      <?php if ($peut_bl) : ?>
      <a class="action-btn" href="../devis/index.php?modal=bl">
        <span class="big"><i class="fas fa-truck-loading" aria-hidden="true"></i></span>
        <strong>Nouveau bon de livraison</strong>
        <span>Pour un client professionnel</span>
      </a>
      <?php endif; ?>
    </div>

    <?php if ($retours_attente !== []) : ?>
    <div class="card" id="retours-en-attente" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Mes retours clients en attente de caisse</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Retour</th><th>Ticket</th><th>Préparé le</th><th>Espèces</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($retours_attente as $r) : ?>
              <tr>
                <td><strong class="ca-ref"><?php echo e($r['numero_retour']); ?></strong></td>
                <td class="muted"><?php echo e($r['numero_ticket']); ?></td>
                <td class="muted"><?php echo date('d/m/Y à H:i', strtotime($r['date_creation'])); ?></td>
                <td><?php
                  if ((float) $r['especes_a_rendre'] >= 0.5) {
                      echo fpl_montant($r['especes_a_rendre']) . ' FCFA à rendre';
                  } elseif ((float) $r['especes_a_recevoir'] >= 0.5) {
                      echo fpl_montant($r['especes_a_recevoir']) . ' FCFA à recevoir';
                  } else {
                      echo 'aucun argent';
                  }
                ?></td>
                <td class="num"><a class="btn btn-outline btn-sm" href="../caisse/retours.php?retour=<?php echo (int) $r['id']; ?>">Voir le retour</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($peut_bl && $bl_brouillons !== []) : ?>
    <div class="card" id="bl-brouillons" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Mes bons de livraison en brouillon</h2>
        <a href="../devis/index.php" class="btn btn-outline btn-sm">Tous les BL</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>BL</th><th>Client</th><th>Date</th><th class="num">Total HT</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($bl_brouillons as $b) : ?>
              <tr>
                <td><span class="chip-code"><?php echo e($b['numero_bl']); ?></span></td>
                <td><div class="cell-title"><?php echo trim((string) $b['raison_sociale']) !== '' ? e($b['raison_sociale']) : '—'; ?></div></td>
                <td class="muted"><?php echo $b['date_bl'] ? date('d/m/Y', strtotime($b['date_bl'])) : '—'; ?></td>
                <td class="num"><span class="qty"><?php echo fpl_montant($b['total_ht']); ?></span> <span class="muted">FCFA</span></td>
                <td class="num"><a class="btn btn-outline btn-sm" href="../devis/bl_modifier.php?id=<?php echo (int) $b['id']; ?>">Reprendre</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="card" id="pieces-presque-epuisees" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Pièces vendues presque épuisées</h2>
      </div>
      <p class="muted ca-aide">Vendues ces 90 derniers jours et à 2 pièces ou moins en stock : prévenez le client avant de lui promettre la pièce.</p>
      <?php if ($pieces_epuisees === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('package', 34); ?></span>
          Aucune pièce vendue ces 90 derniers jours n'est presque épuisée.
        </div>
      <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Pièce</th><th class="num">En stock</th><th>Dernière vente</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($pieces_epuisees as $p) : ?>
                <tr>
                  <td>
                    <div class="cell-title"><?php echo e($p['nom']); ?></div>
                    <div class="cell-sub"><span class="chip-code"><?php echo e($p['identifiant_interne']); ?></span></div>
                  </td>
                  <td class="num"><?php echo (int) $p['stock'] > 0 ? '<span class="qty">' . (int) $p['stock'] . '</span>' : '<span class="badge warn">Rupture</span>'; ?></td>
                  <td class="muted"><?php echo date('d/m/Y', strtotime((string) $p['derniere_vente'])); ?></td>
                  <td class="num"><a class="btn btn-outline btn-sm" href="../caisse/index.php?q=<?php echo rawurlencode((string) $p['identifiant_interne']); ?>">Voir en vente directe</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card" id="pieces-sans-prix" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Pièces vendues sans prix au catalogue</h2>
      </div>
      <p class="muted ca-aide">Ces 90 derniers jours, leur prix a été tapé à la main. Reprenez le dernier prix pratiqué pour vendre au même prix ; la gestion du stock fixe le prix au catalogue.</p>
      <?php if ($pieces_sans_prix === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('package', 34); ?></span>
          Toutes les pièces vendues ces 90 derniers jours ont un prix au catalogue.
        </div>
      <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Pièce</th><th class="num">Tickets</th><th class="num">Dernier prix pratiqué</th><th>Dernière vente</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($pieces_sans_prix as $p) : ?>
                <tr>
                  <td>
                    <div class="cell-title"><?php echo e($p['nom']); ?></div>
                    <div class="cell-sub"><span class="chip-code"><?php echo e($p['identifiant_interne']); ?></span></div>
                  </td>
                  <td class="num"><?php echo (int) $p['ventes']; ?></td>
                  <td class="num"><span class="qty"><?php echo fpl_montant($p['dernier_prix']); ?></span> <span class="muted">FCFA</span></td>
                  <td class="muted"><?php echo date('d/m/Y', strtotime((string) $p['derniere_vente'])); ?></td>
                  <td class="num"><a class="btn btn-outline btn-sm" href="../caisse/index.php?q=<?php echo rawurlencode((string) $p['identifiant_interne']); ?>">Voir en vente directe</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    </div><!-- .page-commercial-accueil -->

<style>
  .page-commercial-accueil .scan-bar .btn { flex-shrink: 0; }
  .page-commercial-accueil .action-btn .big { font-size: 26px; line-height: 1; }
  .page-commercial-accueil a.tile { display: block; color: inherit; text-decoration: none; }
  .page-commercial-accueil a.tile:hover { border-color: var(--blue); box-shadow: var(--sh-2); }
  .page-commercial-accueil .tile .cell-sub { margin-top: 4px; font-variant-numeric: tabular-nums; }
  .page-commercial-accueil .ca-ref {
    font-family: var(--mono); font-size: 18px; letter-spacing: .06em; color: var(--navy);
  }
  .page-commercial-accueil td.num { white-space: nowrap; }
  .page-commercial-accueil .ca-aide { margin: 0 0 var(--s3); max-width: 72ch; }
</style>

<script>
  /* Le curseur dans la recherche, sans faire défiler la page (le pistolet de
     scan tape au bon endroit) : même geste que « Mon travail ». */
  (function () {
    const champ = document.getElementById('ca-q');
    try { champ.focus({ preventScroll: true }); } catch (e) { champ.focus(); }
  })();
</script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
