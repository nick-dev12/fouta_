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
 * L'écran part donc de là : chercher une pièce (la recherche ouvre la caisse,
 * qui montre le prix et le stock et où l'on ajoute au ticket), les gestes du
 * métier, puis CE QUI ATTEND chez lui : ses tickets pas encore encaissés, ses
 * devis sans facture payée, ses bons de livraison restés en brouillon.
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

$tickets_attente = [];
$jour = ['n' => 0, 'total' => 0.0];
$devis_ouverts = [];
$bl_brouillons = [];
$factures_a_relancer = [];
$devis_sans_reponse = [];
$ventes_du_mois = [];
$retours_attente = [];
$lecture_ko = false;
try {
    $tickets_attente = commercial_tickets_en_attente($moi);
    $jour = commercial_tickets_du_jour($moi);
    $devis_ouverts = commercial_devis_ouverts($moi);
    $bl_brouillons = $peut_bl ? commercial_bl_brouillons($moi) : [];
    $factures_a_relancer = commercial_factures_a_relancer($moi);
    $devis_sans_reponse = commercial_devis_sans_reponse($moi, 7);
    $ventes_du_mois = commercial_ventes_du_mois($moi);
    $retours_attente = commercial_retours_en_attente($moi);
} catch (Throwable $e) {
    error_log('[commercial/index] ' . $e->getMessage());
    $lecture_ko = true;
}

$total_attente = 0.0;
foreach ($tickets_attente as $t) {
    $total_attente += (float) $t['montant_total'];
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

    <div class="kpi-line" style="margin-bottom:var(--s4)">
      <div class="card tile">
        <div class="label">Mes tickets du jour</div>
        <div class="value"><?php echo (int) $jour['n']; ?></div>
        <div class="cell-sub"><?php echo fpl_montant($jour['total']); ?> FCFA</div>
      </div>
      <a class="card tile" href="#a-encaisser">
        <div class="label">En attente de caisse</div>
        <div class="value" style="color:var(--warn)"><?php echo count($tickets_attente); ?></div>
        <div class="cell-sub"><?php echo fpl_montant($total_attente); ?> FCFA</div>
      </a>
      <a class="card tile" href="#devis-ouverts">
        <div class="label">Devis ouverts</div>
        <div class="value" style="color:var(--blue-600)"><?php echo count($devis_ouverts); ?></div>
        <div class="cell-sub">sans facture payée</div>
      </a>
    </div>

    <div class="card" id="a-encaisser" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Mes tickets en attente de caisse</h2>
        <a href="../caisse/index.php" class="btn btn-outline btn-sm">Ouvrir la vente directe</a>
      </div>
      <?php if ($tickets_attente === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('package', 34); ?></span>
          Aucun ticket en attente : tout ce que vous avez préparé est encaissé.
        </div>
      <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Ticket</th><th>Réf. caisse</th><th>Préparé le</th><th class="num">Montant</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($tickets_attente as $t) : ?>
                <?php $ref = trim((string) ($t['reference'] ?? '')); ?>
                <tr>
                  <td><span class="chip-code"><?php echo e($t['numero_ticket']); ?></span></td>
                  <td><strong class="ca-ref"><?php echo $ref !== '' ? e($ref) : '—'; ?></strong></td>
                  <td class="muted"><?php echo date('d/m/Y à H:i', strtotime($t['date_vente'])); ?></td>
                  <td class="num"><span class="qty"><?php echo fpl_montant($t['montant_total']); ?></span> <span class="muted">FCFA</span></td>
                  <td class="num"><a class="btn btn-outline btn-sm" href="../caisse/index.php?ticket=<?php echo (int) $t['id']; ?>">Voir le ticket</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
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

    <?php if ($factures_a_relancer !== []) : ?>
    <div class="card" id="factures-a-relancer" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Mes factures de devis à relancer</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Facture</th><th>Client</th><th>Émise le</th><th class="num">Depuis</th><th class="num">Montant</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($factures_a_relancer as $f) : ?>
              <?php $client_f = trim((string) $f['client_prenom'] . ' ' . (string) $f['client_nom']); ?>
              <tr>
                <td><span class="chip-code"><?php echo e($f['numero_facture']); ?></span></td>
                <td>
                  <div class="cell-title"><?php echo $client_f !== '' ? e($client_f) : '—'; ?></div>
                  <?php if (!empty($f['client_telephone'])) : ?><div class="cell-sub"><?php echo e($f['client_telephone']); ?></div><?php endif; ?>
                </td>
                <td class="muted"><?php echo !empty($f['date_facture']) ? date('d/m/Y', strtotime($f['date_facture'])) : '—'; ?></td>
                <td class="num"><?php echo (int) $f['jours']; ?> j</td>
                <td class="num"><span class="qty"><?php echo fpl_montant($f['montant_total']); ?></span> <span class="muted">FCFA</span></td>
                <td class="num"><a class="btn btn-outline btn-sm" href="../devis/details.php?id=<?php echo (int) $f['devis_id']; ?>">Ouvrir</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($devis_sans_reponse !== []) : ?>
    <div class="card" id="devis-sans-reponse" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Mes devis envoyés sans réponse depuis 7 jours</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Devis</th><th>Client</th><th class="num">Envoyé depuis</th><th class="num">Montant</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($devis_sans_reponse as $s) : ?>
              <?php $client_s = trim((string) $s['client_prenom'] . ' ' . (string) $s['client_nom']); ?>
              <tr>
                <td><span class="chip-code"><?php echo e($s['numero_devis']); ?></span></td>
                <td>
                  <div class="cell-title"><?php echo $client_s !== '' ? e($client_s) : '—'; ?></div>
                  <?php if (!empty($s['client_telephone'])) : ?><div class="cell-sub"><?php echo e($s['client_telephone']); ?></div><?php endif; ?>
                </td>
                <td class="num"><?php echo (int) $s['jours']; ?> j</td>
                <td class="num"><span class="qty"><?php echo fpl_montant($s['montant_total']); ?></span> <span class="muted">FCFA</span></td>
                <td class="num"><a class="btn btn-outline btn-sm" href="../devis/details.php?id=<?php echo (int) $s['id']; ?>">Ouvrir</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="card" id="devis-ouverts" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Mes devis ouverts</h2>
        <a href="../devis/devis.php" class="btn btn-outline btn-sm">Tous les devis</a>
      </div>
      <?php if ($devis_ouverts === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('package', 34); ?></span>
          Aucun devis ouvert : chacun de vos devis a sa facture payée.
        </div>
      <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Devis</th><th>Client</th><th>Statut</th><th>Créé le</th><th class="num">Montant</th><th>Facture</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($devis_ouverts as $d) : ?>
                <?php $client = trim((string) $d['client_prenom'] . ' ' . (string) $d['client_nom']); ?>
                <tr>
                  <td><span class="chip-code"><?php echo e($d['numero_devis']); ?></span></td>
                  <td><div class="cell-title"><?php echo $client !== '' ? e($client) : '—'; ?></div></td>
                  <td><span class="badge"><?php echo e(devis_statut_libelle($d)); ?></span></td>
                  <td class="muted"><?php echo date('d/m/Y', strtotime($d['date_creation'])); ?></td>
                  <td class="num"><span class="qty"><?php echo fpl_montant($d['montant_total']); ?></span> <span class="muted">FCFA</span></td>
                  <td>
                    <?php if (!empty($d['numero_facture'])) : ?>
                      <span class="badge warn"><?php echo e($d['numero_facture']); ?> à payer</span>
                    <?php else : ?>
                      <span class="badge">Pas encore facturé</span>
                    <?php endif; ?>
                  </td>
                  <td class="num"><a class="btn btn-outline btn-sm" href="../devis/details.php?id=<?php echo (int) $d['id']; ?>">Ouvrir</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

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

    <?php if ($ventes_du_mois !== []) : ?>
    <div class="card" id="mes-ventes-du-mois" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Mes ventes du mois</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Chemin</th><th class="num">Nombre</th><th class="num">Montant</th><th>Précision</th></tr>
          </thead>
          <tbody>
            <?php foreach ($ventes_du_mois as $v) : ?>
              <tr>
                <td><div class="cell-title"><?php echo e($v['chemin']); ?></div></td>
                <td class="num"><?php echo (int) $v['nombre']; ?></td>
                <td class="num"><span class="qty"><?php echo fpl_montant($v['montant']); ?></span> <span class="muted">FCFA</span></td>
                <td class="muted"><?php echo e($v['detail']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

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
