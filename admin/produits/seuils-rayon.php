<?php
/**
 * LES SEUILS, RAYON PAR RAYON (31/08/2026).
 * Programmation procédurale uniquement
 *
 * Chaque pièce a son propre seuil d'alerte : c'est la règle posée par la
 * direction. Mais 3 259 fiches ouvertes une par une, personne ne le fera.
 * Cet écran prend un rayon, affiche ses pièces avec leur stock, et laisse
 * saisir les seuils à la chaîne, d'un seul enregistrement.
 *
 * Ce qu'on lit sur chaque ligne : le stock d'aujourd'hui — sans lui, on
 * règlerait à l'aveugle — et le seuil qui s'applique déjà, avec son origine.
 *
 * Case laissée telle quelle : rien ne change. Case vidée : le seuil est
 * retiré. Le seuil posé ici est « posé à la main » : le calcul des
 * suggestions ne l'écrasera jamais.
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
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';
require_once __DIR__ . '/../../models/model_stock_alertes.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';

/* Qui écrit le seuil écrit ici — la même règle que sur la fiche. */
if (!pf_champ_modifiable('seuil_alerte')) {
    $_SESSION['acces_refuse_message'] = "Le réglage des seuils est réservé au responsable stock et à l'administrateur.";
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$sous_categorie_id = isset($_GET['sous_categorie_id']) ? (int) $_GET['sous_categorie_id'] : 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$par_page = 50;

$flash_ok = null;
$flash_err = null;

/* ------------------------------------------------------------------ ENREGISTREMENT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $token)) {
        $flash_err = 'Session expirée. Rechargez la page puis enregistrez à nouveau.';
    } else {
        $seuils = isset($_POST['seuil']) && is_array($_POST['seuil']) ? $_POST['seuil'] : [];
        $avant = isset($_POST['avant']) && is_array($_POST['avant']) ? $_POST['avant'] : [];
        $poses = 0;
        $retires = 0;
        foreach ($seuils as $pid => $valeur) {
            $pid = (int) $pid;
            if ($pid <= 0) {
                continue;
            }
            $nouveau = trim((string) $valeur);
            $ancien = isset($avant[$pid]) ? trim((string) $avant[$pid]) : '';
            if ($nouveau === $ancien) {
                continue;   // la case n'a pas bougé : on n'y touche pas
            }
            if ($nouveau === '') {
                $res = stock_alertes_seuil_piece_enregistrer($pid, null);
                if ($res['success']) {
                    $retires++;
                }
                continue;
            }
            if (!is_numeric($nouveau) || (int) $nouveau < 0) {
                continue;
            }
            $res = stock_alertes_seuil_piece_enregistrer($pid, (int) $nouveau, 'manuel');
            if ($res['success']) {
                $poses++;
            }
        }
        $parts = [];
        if ($poses > 0) {
            $parts[] = $poses . ' seuil(s) enregistré(s)';
        }
        if ($retires > 0) {
            $parts[] = $retires . ' retiré(s)';
        }
        $_SESSION['success_message_seuils_rayon'] = $parts === []
            ? 'Aucune case n\'a changé — rien n\'a été modifié.'
            : implode(', ', $parts) . '.';
        $retour = 'seuils-rayon.php?' . http_build_query(array_filter([
            'categorie_id' => $categorie_id ?: null,
            'sous_categorie_id' => $sous_categorie_id ?: null,
            'page' => $page > 1 ? $page : null,
        ]));
        header('Location: ' . $retour);
        exit;
    }
}

if (isset($_SESSION['success_message_seuils_rayon'])) {
    $flash_ok = (string) $_SESSION['success_message_seuils_rayon'];
    unset($_SESSION['success_message_seuils_rayon']);
}

/* ------------------------------------------------------------------ LES DONNÉES */
$categories = get_all_categories();
$sous_categories = [];
if ($categorie_id > 0 && function_exists('get_sous_categories_by_categorie_id')) {
    $sous_categories = get_sous_categories_by_categorie_id($categorie_id);
}

$lignes = [];
$total = 0;
$deja_regles = 0;
if ($categorie_id > 0) {
    $ou = ['p.sync_deleted_at IS NULL', 'p.categorie_id = :cat'];
    $params = [':cat' => $categorie_id];
    if ($sous_categorie_id > 0) {
        $ou[] = 'p.sous_categorie_id = :sc';
        $params[':sc'] = $sous_categorie_id;
    }
    $ouSql = implode(' AND ', $ou);
    try {
        $st = $db->prepare("SELECT COUNT(*) FROM produits p WHERE $ouSql");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $db->prepare("SELECT COUNT(*) FROM produits p WHERE $ouSql AND p.seuil_alerte IS NOT NULL");
        $st->execute($params);
        $deja_regles = (int) $st->fetchColumn();

        $derniere = max(1, (int) ceil($total / $par_page));
        $page = min($page, $derniere);
        $offset = ($page - 1) * $par_page;
        $st = $db->prepare("SELECT p.id, p.nom, p.identifiant_interne, p.stock, p.seuil_alerte, p.seuil_alerte_source,
                                   p.categorie_id, p.sous_categorie_id
                            FROM produits p
                            WHERE $ouSql
                            ORDER BY p.nom ASC
                            LIMIT $par_page OFFSET $offset");
        $st->execute($params);
        $lignes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $flash_err = 'Lecture impossible : ' . $e->getMessage();
    }
}
$derniere_page = max(1, (int) ceil(max(1, $total) / $par_page));

$fpl_titre_page = 'Seuils, rayon par rayon';
$fpl_retour_page = '../parametres/alertes-stock.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seuils, rayon par rayon — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

      <?php if ($flash_ok) : ?>
        <div class="alert alert-success" role="status"><?php echo e($flash_ok); ?></div>
      <?php endif; ?>
      <?php if ($flash_err) : ?>
        <div class="alert alert-error" role="alert"><?php echo e($flash_err); ?></div>
      <?php endif; ?>

      <div class="page-lead">
        <div>
          <h1 class="page-lead__title">Seuils, rayon par rayon</h1>
          <p class="page-lead__sub">
            Chaque pièce a son propre seuil. Prenez un rayon, regardez le stock, posez les chiffres,
            enregistrez une seule fois. L'alerte parlera dès que le stock sera inférieur ou égal au seuil.
          </p>
        </div>
        <div class="page-lead__actions">
          <a href="../parametres/alertes-stock.php" class="btn btn-outline"><?php echo fpl_icone('sliders', 14); ?> Alertes de stock</a>
        </div>
      </div>

      <div class="card" style="margin-bottom:var(--s4)">
        <form method="get" style="display:flex; gap:var(--s2); flex-wrap:wrap; align-items:flex-end">
          <div class="field" style="flex:1 1 240px">
            <label for="categorie_id">Catégorie</label>
            <select id="categorie_id" name="categorie_id" onchange="this.form.sous_categorie_id.value=''; this.form.submit()">
              <option value="">— Choisissez un rayon —</option>
              <?php foreach ($categories as $c) : ?>
                <option value="<?php echo (int) $c['id']; ?>" <?php echo $categorie_id === (int) $c['id'] ? 'selected' : ''; ?>>
                  <?php echo fpl_e($c['nom']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($sous_categories !== []) : ?>
            <div class="field" style="flex:1 1 240px">
              <label for="sous_categorie_id">Sous-catégorie <span class="hint-inline">facultatif</span></label>
              <select id="sous_categorie_id" name="sous_categorie_id" onchange="this.form.submit()">
                <option value="">— Toutes —</option>
                <?php foreach ($sous_categories as $sc) : ?>
                  <option value="<?php echo (int) $sc['id']; ?>" <?php echo $sous_categorie_id === (int) $sc['id'] ? 'selected' : ''; ?>>
                    <?php echo fpl_e($sc['nom']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-outline">Afficher</button>
        </form>
      </div>

      <?php if ($categorie_id <= 0) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('folder', 34); ?></span>
          Choisissez un rayon pour commencer.
        </div>
      <?php elseif ($lignes === []) : ?>
        <div class="empty">
          <span class="big"><?php echo fpl_icone('box', 34); ?></span>
          Aucune pièce dans ce rayon.
        </div>
      <?php else : ?>

        <p class="muted" style="margin-bottom:var(--s3)">
          <strong><?php echo (int) $total; ?></strong> pièce(s) dans ce rayon —
          <strong><?php echo (int) $deja_regles; ?></strong> ont déjà leur propre seuil.
          Page <?php echo (int) $page; ?> sur <?php echo (int) $derniere_page; ?>.
        </p>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Pièce</th>
                  <th class="num">Stock</th>
                  <th>Seuil qui s'applique</th>
                  <th style="width:170px">Seuil de cette pièce</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lignes as $p) : ?>
                  <?php
                  $eff = stock_alerte_seuil_effectif($p);
                  $propre = ($p['seuil_alerte'] !== null && $p['seuil_alerte'] !== '') ? (string) (int) $p['seuil_alerte'] : '';
                  ?>
                  <tr>
                    <td>
                      <a class="cell-title" href="ajuster-stock.php?id=<?php echo (int) $p['id']; ?>" style="color:var(--ink)"><?php echo fpl_e($p['nom']); ?></a>
                      <?php if (!empty($p['identifiant_interne'])) : ?>
                        <div class="cell-sub"><span class="chip-code"><?php echo e(fpl_code_afficher((string) $p['identifiant_interne'])); ?></span></div>
                      <?php endif; ?>
                    </td>
                    <td class="num"><strong><?php echo (int) $p['stock']; ?></strong></td>
                    <td>
                      <?php if ($eff['seuil'] === null) : ?>
                        <span class="muted">aucun</span>
                      <?php else : ?>
                        <?php echo (int) $eff['seuil']; ?>
                        <div class="cell-sub"><?php echo e($eff['libelle']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <input type="hidden" name="avant[<?php echo (int) $p['id']; ?>]" value="<?php echo e($propre); ?>">
                      <input type="number" name="seuil[<?php echo (int) $p['id']; ?>]" min="0" step="1"
                             value="<?php echo e($propre); ?>" placeholder="aucun" style="width:120px">
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div style="display:flex; gap:var(--s3); align-items:center; margin-top:var(--s3); flex-wrap:wrap">
            <button type="submit" class="btn btn-primary"><?php echo fpl_icone('check', 14); ?> Enregistrer cette page</button>
            <span class="muted" style="font-size:12.5px">
              Une case laissée telle quelle ne change rien. Une case vidée retire le seuil de la pièce.
            </span>
          </div>
        </form>

        <?php if ($derniere_page > 1) : ?>
          <div style="display:flex; gap:var(--s2); margin-top:var(--s4); flex-wrap:wrap">
            <?php for ($i = 1; $i <= $derniere_page; $i++) : ?>
              <a class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?>"
                 href="seuils-rayon.php?<?php echo http_build_query(array_filter([
                     'categorie_id' => $categorie_id,
                     'sous_categorie_id' => $sous_categorie_id ?: null,
                     'page' => $i,
                 ])); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>

    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
