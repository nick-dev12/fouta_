<?php
/**
 * PRIX DEMANDÉS (11/09/2026).
 *
 * Décision de la direction : le vendeur ne tape plus aucun prix ; une pièce sans
 * prix ne se vend pas, le vendeur demande son prix. Ici, le profil qui a le
 * droit d'écrire le prix de la fiche pièce (le responsable de stock) voit les
 * pièces qui attendent le leur, les plus demandées d'abord, avec les derniers
 * prix pratiqués pour repère. Il tape les prix et les enregistre d'un seul
 * geste : chaque prix va au catalogue, la vente directe et les devis le
 * prennent aussitôt, et le vendeur le retrouve sur son accueil.
 * Règles : models/model_demandes_prix.php.
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
require_once __DIR__ . '/../../models/model_produit_formulaire_champs.php';
require_once __DIR__ . '/../../models/model_demandes_prix.php';

/* Écrire le prix est un droit de la fiche pièce (Paramètres › Champs de la
 * fiche pièce) : la page suit ce droit, pas une liste de rôles écrite ici. */
if (!produit_formulaire_champ_modifiable('prix')) {
    admin_redirect_role_home();
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$moi = (int) $_SESSION['admin_id'];
$tables_ok = demandes_prix_table_ok();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fixes = [];
    $refus = [];
    $jeton = (string) ($_POST['csrf_token'] ?? '');
    if ($jeton === '' || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
        $refus[] = 'Session expirée : rechargez la page, puis tapez les prix de nouveau.';
    } elseif ($tables_ok) {
        $saisis = isset($_POST['prix']) && is_array($_POST['prix']) ? $_POST['prix'] : [];
        foreach ($saisis as $demande_id => $brut) {
            $montant = demandes_prix_lire_montant(is_string($brut) ? $brut : '');
            if ($montant === null) {
                continue;   // case laissée vide : la demande reste en attente
            }
            $demande = demande_prix_par_id((int) $demande_id);
            if (!$demande || $demande['statut'] !== 'en_attente') {
                $refus[] = 'Une demande n’est plus en attente : son prix a déjà été fixé.';
                continue;
            }
            $piece = '« ' . $demande['nom'] . ' »';
            if ($montant === false || $montant <= 0) {
                $refus[] = $piece . ' : « ' . trim((string) $brut) . ' » n’est pas un prix. Tapez un montant en FCFA, par exemple 12500.';
                continue;
            }
            if (!produit_formulaire_champ_modifiable((string) $demande['champ'])) {
                $refus[] = $piece . ' : votre profil n’a pas le droit d’écrire le ' . demandes_prix_libelle_champ($demande['champ']) . '.';
                continue;
            }
            $res = demande_prix_fixer((int) $demande_id, $montant, $moi);
            if (!empty($res['ok'])) {
                $fixes[] = $piece . ' : ' . fpl_montant($res['prix']) . ' FCFA' . ($res['champ'] !== 'prix' ? ' (' . $res['libelle'] . ')' : '');
            } else {
                $refus[] = (string) $res['error'];
            }
        }
    }
    $_SESSION['prix_demandes_flash'] = ['ok' => $fixes, 'err' => $refus];
    header('Location: prix-demandes.php');
    exit;
}

$flash = $_SESSION['prix_demandes_flash'] ?? ['ok' => [], 'err' => []];
unset($_SESSION['prix_demandes_flash']);

$attente = [];
$recentes = [];
if ($tables_ok) {
    demandes_prix_solder_prix_poses();
    $attente = demandes_prix_en_attente(300);
    $recentes = demandes_prix_traitees_recentes(7, 30);
}
$nb_attente = count($attente);
$nom_de = static function ($prenom, $nom) {
    $complet = trim((string) $prenom . ' ' . (string) $nom);
    return $complet !== '' ? $complet : '—';
};
$quand = static function ($date) {
    return $date ? date('d/m à H:i', strtotime((string) $date)) : '—';
};
$fpl_titre_page = 'Prix demandés';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prix demandés — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
</head>

<body class="fpl-catalogue">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="page-produits-admin page-prix-demandes">

    <?php if (!$tables_ok) : ?>
      <div class="alert error" role="alert">Les demandes de prix attendent la mise à jour de la base : jouez migrations/run_demandes_prix.php sur ce serveur.</div>
    <?php endif; ?>
    <?php if ($flash['ok'] !== []) : ?>
      <div class="alert success alert-neuve" role="status">
        <div>
          <strong><?php echo count($flash['ok']) === 1 ? 'Prix fixé au catalogue' : count($flash['ok']) . ' prix fixés au catalogue'; ?></strong>
          <ul class="pd-flash-liste"><?php foreach ($flash['ok'] as $ligne) : ?><li><?php echo e($ligne); ?></li><?php endforeach; ?></ul>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($flash['err'] !== []) : ?>
      <div class="alert error alert-neuve" role="alert">
        <div>
          <strong>Non enregistré</strong>
          <ul class="pd-flash-liste"><?php foreach ($flash['err'] as $ligne) : ?><li><?php echo e($ligne); ?></li><?php endforeach; ?></ul>
        </div>
      </div>
    <?php endif; ?>

    <div class="card" id="prix-a-fixer" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Prix demandés par les vendeurs</h2>
        <?php if ($nb_attente > 0) : ?>
          <span class="badge warn"><?php echo $nb_attente; ?> pièce<?php echo $nb_attente > 1 ? 's' : ''; ?> en attente</span>
        <?php endif; ?>
      </div>
      <p class="muted pd-aide">Les vendeurs ne tapent plus de prix : une pièce sans prix ne se vend pas tant que le sien n’est pas fixé. Les plus demandées sont en tête. Tapez les prix en FCFA, puis enregistrez : chaque prix va au catalogue, et la vente directe comme les devis le prennent aussitôt. Une case laissée vide reste en attente.</p>
      <?php if ($attente === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('check', 34); ?></span>
          Aucune pièce n’attend son prix.
        </div>
      <?php else : ?>
        <form method="post" action="prix-demandes.php" class="pd-form">
          <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
          <div class="table-wrap">
            <table class="pd-table">
              <thead>
                <tr><th>Pièce</th><th>Prix demandé</th><th>Demandes</th><th>Stock</th><th>Derniers prix pratiqués</th><th>Prix à fixer</th></tr>
              </thead>
              <tbody>
                <?php foreach ($attente as $d) : ?>
                <tr>
                  <td class="pd-piece">
                    <div class="cell-title"><?php echo e($d['nom']); ?></div>
                    <div class="cell-sub"><span class="chip-code"><?php echo e($d['identifiant_interne']); ?></span>
                      <a href="modifier.php?id=<?php echo (int) $d['produit_id']; ?>">Fiche</a></div>
                  </td>
                  <td><span class="badge <?php echo $d['champ'] === 'prix' ? 'blue' : 'neutral'; ?>"><?php echo e($d['libelle']); ?></span></td>
                  <td class="num">
                    <span class="qty"><?php echo (int) $d['nb_demandes']; ?></span>
                    <div class="cell-sub"><?php echo e($nom_de($d['dernier_prenom'], $d['dernier_nom'])); ?>, le <?php echo e($quand($d['date_derniere_demande'] ?: $d['date_demande'])); ?></div>
                  </td>
                  <td class="num"><?php echo (int) $d['stock']; ?></td>
                  <td class="pd-reperes">
                    <?php if ($d['dernier_prix_vendu'] !== null) : ?><div>Vente directe : <?php echo fpl_montant($d['dernier_prix_vendu']); ?>&nbsp;FCFA</div><?php endif; ?>
                    <?php if ($d['dernier_prix_devis'] !== null) : ?><div>Devis : <?php echo fpl_montant($d['dernier_prix_devis']); ?>&nbsp;FCFA</div><?php endif; ?>
                    <?php if ($d['dernier_prix_vendu'] === null && $d['dernier_prix_devis'] === null) : ?><span>Aucun</span><?php endif; ?>
                  </td>
                  <td>
                    <label class="pd-saisie">
                      <input type="text" name="prix[<?php echo (int) $d['id']; ?>]" inputmode="numeric" autocomplete="off" placeholder="—"
                             aria-label="<?php echo e($d['libelle'] . ' de « ' . $d['nom'] . ' », en FCFA'); ?>">
                      <span aria-hidden="true">FCFA</span>
                    </label>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="pd-actions">
            <button type="submit" class="btn btn-primary"><?php echo fpl_icone('save', 16); ?> <span class="pd-bouton-texte">Enregistrer les prix tapés</span></button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($recentes !== []) : ?>
    <div class="card" id="prix-fixes">
      <h2>Prix fixés ces 7 derniers jours</h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Pièce</th><th>Prix fixé</th><th>Fixé par</th><th>Le</th><th>Demandé par</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentes as $d) : ?>
            <tr>
              <td class="pd-piece">
                <div class="cell-title"><?php echo e($d['nom']); ?></div>
                <div class="cell-sub"><span class="chip-code"><?php echo e($d['identifiant_interne']); ?></span></div>
              </td>
              <td class="num">
                <strong><?php echo fpl_montant($d['prix_fixe']); ?>&nbsp;FCFA</strong>
                <?php if ($d['champ'] !== 'prix') : ?><div class="cell-sub"><?php echo e($d['libelle']); ?></div><?php endif; ?>
              </td>
              <td><?php echo $d['traite_par'] ? e($nom_de($d['traite_prenom'], $d['traite_nom'])) : '<span class="muted">Depuis la fiche de la pièce</span>'; ?></td>
              <td class="muted"><?php echo e($quand($d['date_traitement'])); ?></td>
              <td class="muted"><?php echo e($nom_de($d['demandeur_prenom'], $d['demandeur_nom'])); ?><?php echo (int) $d['nb_demandes'] > 1 ? ' · ' . (int) $d['nb_demandes'] . ' demandes' : ''; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    </div><!-- .page-prix-demandes -->

<script>
  /* La saisie des prix : chaque ligne tapée se voit, le bouton dit combien de
     prix partent, et quitter la page avec des prix non enregistrés demande
     confirmation. */
  (function () {
    var form = document.querySelector('.page-prix-demandes .pd-form');
    if (!form) { return; }
    var bouton = form.querySelector('.pd-actions button');
    var texte = form.querySelector('.pd-bouton-texte');
    var envoi = false;
    function compter() {
      var n = 0;
      form.querySelectorAll('.pd-saisie input').forEach(function (champ) {
        var tape = champ.value.trim() !== '';
        champ.closest('tr').classList.toggle('pd-tape', tape);
        if (tape) { n++; }
      });
      texte.textContent = n === 0 ? 'Tapez un prix pour enregistrer' : (n === 1 ? 'Enregistrer le prix tapé' : 'Enregistrer les ' + n + ' prix tapés');
      bouton.disabled = n === 0;
      return n;
    }
    form.addEventListener('input', compter);
    form.addEventListener('submit', function () { envoi = true; });
    window.addEventListener('beforeunload', function (ev) {
      if (!envoi && compter() > 0) { ev.preventDefault(); ev.returnValue = ''; }
    });
    compter();
  })();
</script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
