<?php
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

/* LE GARDE-BARRIÈRE (31/08/2026) : cette page ne demandait jamais si le
 * compte connecté avait le droit d'être là. La règle existe depuis
 * toujours dans includes/admin_route_access.php ; il manquait l'appel. */
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_bl_retours_b2b()) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_bons_retour.php';

$bl_id = isset($_GET['bl_id']) ? (int) $_GET['bl_id'] : 0;
if ($bl_id <= 0 || !bl_tables_available() || !br_retour_tables_available()) {
    header('Location: index.php?tab=br');
    exit;
}

$bl = get_bl_by_id($bl_id);
if (!$bl) {
    header('Location: index.php?tab=br');
    exit;
}

$lignes = get_lignes_bl($bl_id);
if (empty($lignes)) {
    $_SESSION['bl_erreur'] = 'Ce bon ne contient aucune ligne : création de bon de retour impossible.';
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

$csrf = htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8');
$nom_bl = htmlspecialchars($bl['numero_bl'] ?? '', ENT_QUOTES, 'UTF-8');
$client_rs = htmlspecialchars($bl['raison_sociale'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de retour — <?php echo $nom_bl; ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-br-creation.css'); ?>
</head>
<body class="br-creation-admin-page">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1>
                <span class="br-creation-ic br-creation-ic--hero" aria-hidden="true"><i class="fas fa-undo"></i></span>
                <span>Nouveau bon de retour</span>
            </h1>
            <p class="bl-page-header__sub">
                <span class="br-creation-header-meta"><i class="fas fa-file-invoice" aria-hidden="true"></i> <strong><?php echo $nom_bl; ?></strong></span>
                <span class="br-creation-header-meta"><i class="fas fa-building" aria-hidden="true"></i> <?php echo $client_rs; ?></span>
            </p>
        </div>
        <div class="header-actions bl-page-header__actions bl-page-header__actions--stack">
            <a href="bl_voir.php?id=<?php echo (int) $bl_id; ?>" class="btn-secondary"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour au BL</a>
            <a href="index.php?tab=br" class="btn-secondary"><i class="fas fa-list" aria-hidden="true"></i> Liste bons de retour</a>
        </div>
    </div>

    <section class="content-section bl-detail-page br-creation-page">
        <form method="post" action="br_enregistrer.php" class="br-creation-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="bl_id" value="<?php echo (int) $bl_id; ?>">

            <div class="br-creation-card">
                <div class="br-creation-card__head">
                    <span class="br-creation-ic br-creation-ic--notes" aria-hidden="true"><i class="fas fa-pen-to-square"></i></span>
                    <h2>Notes (optionnel)</h2>
                </div>
                <label for="notes_br">Commentaire interne</label>
                <input type="text" id="notes_br" name="notes" class="form-control" maxlength="2000" placeholder="Motif, référence interne…" autocomplete="off">
            </div>

            <div class="bl-lines-section">
                <h2 class="bl-lines-section__title">
                    <span class="br-creation-ic br-creation-ic--lines" aria-hidden="true"><i class="fas fa-boxes-stacked"></i></span>
                    <span>Lignes à retourner</span>
                </h2>
                <div class="bl-lines-table-wrap">
                    <table class="admin-table bl-lines-table br-creation-table">
                        <thead>
                            <tr>
                                <th scope="col">Désignation</th>
                                <th scope="col" class="bl-lines-table__num">Qté BL</th>
                                <th scope="col" class="bl-lines-table__num">Déjà retourné</th>
                                <th scope="col" class="bl-lines-table__num">Disponible</th>
                                <th scope="col" class="bl-lines-table__num">Qté retour</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes as $l): ?>
                                <?php
                                $lid = (int) ($l['id'] ?? 0);
                                $q_bl = (float) ($l['quantite'] ?? 0);
                                $deja = br_quantite_deja_retournee_bl_ligne($lid);
                                $dispo = br_quantite_disponible_retour_bl_ligne($l);
                                $des = htmlspecialchars($l['designation'] ?? '', ENT_QUOTES, 'UTF-8');
                                ?>
                            <tr>
                                <td><?php echo $des; ?></td>
                                <td class="bl-lines-table__num"><?php echo rtrim(rtrim(sprintf('%.4F', $q_bl), '0'), '.'); ?></td>
                                <td class="bl-lines-table__num"><?php echo rtrim(rtrim(sprintf('%.4F', $deja), '0'), '.'); ?></td>
                                <td class="bl-lines-table__num"><strong><?php echo rtrim(rtrim(sprintf('%.4F', $dispo), '0'), '.'); ?></strong></td>
                                <td class="bl-lines-table__num">
                                    <?php if ($dispo <= 0): ?>
                                        <span class="text-muted">—</span>
                                        <input type="hidden" name="qty[<?php echo $lid; ?>]" value="0">
                                    <?php else: ?>
                                        <input type="number"
                                            name="qty[<?php echo $lid; ?>]"
                                            value="0"
                                            min="0"
                                            max="<?php echo htmlspecialchars((string) $dispo, ENT_QUOTES, 'UTF-8'); ?>"
                                            step="any"
                                            class="form-control"
                                            title="Quantité à retourner (max. <?php echo htmlspecialchars((string) $dispo, ENT_QUOTES, 'UTF-8'); ?>)">
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="br-creation-actions bl-voir-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer le bon de retour</button>
                <a href="bl_voir.php?id=<?php echo (int) $bl_id; ?>" class="btn-secondary"><i class="fas fa-times" aria-hidden="true"></i> Annuler</a>
            </div>
        </form>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
