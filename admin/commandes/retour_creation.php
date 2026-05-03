<?php
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_commandes_retours.php';

$commande_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($commande_id <= 0 || !crc_retour_tables_available()) {
    header('Location: livrees.php');
    exit;
}

$commande = get_commande_by_id($commande_id);
if (!$commande || !crc_commande_est_eligible_retour($commande)) {
    $_SESSION['error_message'] = 'Cette commande ne permet pas d’enregistrer un retour (livraison ou paiement requis).';
    header('Location: livrees.php');
    exit;
}

$lignes = get_produits_by_commande($commande_id);
if (empty($lignes)) {
    $_SESSION['error_message'] = 'Aucune ligne produit sur cette commande.';
    header('Location: details.php?id=' . $commande_id);
    exit;
}

$csrf = htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8');
$num_cmd = htmlspecialchars($commande['numero_commande'] ?? '', ENT_QUOTES, 'UTF-8');
$client_nom = htmlspecialchars(trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? '')), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retour commande — <?php echo $num_cmd; ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-br-creation.css<?php echo asset_version_query(); ?>">
</head>
<body class="br-creation-admin-page">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1>
                <span class="br-creation-ic br-creation-ic--hero" aria-hidden="true"><i class="fas fa-undo"></i></span>
                <span>Retour marchandise (boutique)</span>
            </h1>
            <p class="bl-page-header__sub">
                <span class="br-creation-header-meta"><i class="fas fa-shopping-bag" aria-hidden="true"></i> <strong><?php echo $num_cmd; ?></strong></span>
                <span class="br-creation-header-meta"><i class="fas fa-user" aria-hidden="true"></i> <?php echo $client_nom !== '' ? $client_nom : '—'; ?></span>
            </p>
        </div>
        <div class="header-actions bl-page-header__actions bl-page-header__actions--stack">
            <a href="details.php?id=<?php echo (int) $commande_id; ?>" class="btn-secondary"><i class="fas fa-arrow-left" aria-hidden="true"></i> Fiche commande</a>
            <a href="livrees.php" class="btn-secondary"><i class="fas fa-check-circle" aria-hidden="true"></i> Commandes livrées</a>
            <a href="index.php?tab=retours" class="btn-secondary"><i class="fas fa-list" aria-hidden="true"></i> Retours enregistrés</a>
        </div>
    </div>

    <section class="content-section bl-detail-page br-creation-page">
        <form method="post" action="retour_enregistrer.php" class="br-creation-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="commande_id" value="<?php echo (int) $commande_id; ?>">

            <div class="br-creation-card">
                <div class="br-creation-card__head">
                    <span class="br-creation-ic br-creation-ic--notes" aria-hidden="true"><i class="fas fa-pen-to-square"></i></span>
                    <h2>Notes (optionnel)</h2>
                </div>
                <label for="notes_retour">Commentaire interne</label>
                <input type="text" id="notes_retour" name="notes" class="form-control" maxlength="2000" placeholder="Motif du retour…" autocomplete="off">
            </div>

            <div class="bl-lines-section">
                <h2 class="bl-lines-section__title">
                    <span class="br-creation-ic br-creation-ic--lines" aria-hidden="true"><i class="fas fa-boxes-stacked"></i></span>
                    <span>Quantités retournées par ligne</span>
                </h2>
                <div class="bl-lines-table-wrap">
                    <table class="admin-table bl-lines-table br-creation-table">
                        <thead>
                            <tr>
                                <th scope="col">Produit</th>
                                <th scope="col" class="bl-lines-table__num">Qté commandée</th>
                                <th scope="col" class="bl-lines-table__num">Déjà retourné</th>
                                <th scope="col" class="bl-lines-table__num">Disponible</th>
                                <th scope="col" class="bl-lines-table__num">Qté retour</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes as $l): ?>
                                <?php
                                $lid = (int) ($l['id'] ?? 0);
                                $q_cmd = (float) ($l['quantite'] ?? 0);
                                $deja = crc_quantite_deja_retournee_ligne_cp($lid);
                                $dispo = crc_quantite_disponible_retour_ligne($l);
                                $nom_p = $l['produit_nom'] ?? $l['nom_produit'] ?? '';
                                $des = htmlspecialchars($nom_p, ENT_QUOTES, 'UTF-8');
                                ?>
                            <tr>
                                <td><?php echo $des; ?></td>
                                <td class="bl-lines-table__num"><?php echo rtrim(rtrim(sprintf('%.4F', $q_cmd), '0'), '.'); ?></td>
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
                                            step="1"
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
                <button type="submit" class="btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer le retour</button>
                <a href="details.php?id=<?php echo (int) $commande_id; ?>" class="btn-secondary"><i class="fas fa-times" aria-hidden="true"></i> Annuler</a>
            </div>
        </form>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
