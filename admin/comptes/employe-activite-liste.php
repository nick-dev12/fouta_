<?php
/**
 * Liste détaillée d’éléments d’activité pour un compte admin (traçabilité)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../models/model_admin_activite.php';

$admin_cible_id = isset($_GET['admin_id']) ? (int) $_GET['admin_id'] : 0;
$type = isset($_GET['type']) ? preg_replace('/[^a-z_]/', '', $_GET['type']) : '';

if ($admin_cible_id <= 0 || $type === '') {
    header('Location: index.php');
    exit;
}

$libelles = get_activite_liste_types_libelles();
if (!isset($libelles[$type])) {
    header('Location: index.php');
    exit;
}

$admin_cible = get_admin_by_id($admin_cible_id);
if (!$admin_cible) {
    header('Location: index.php');
    exit;
}

$lignes = get_liste_activite_par_admin($admin_cible_id, $type, 250);
$titre_liste = $libelles[$type];
$page_title = $titre_liste . ' — ' . htmlspecialchars($admin_cible['prenom'] . ' ' . $admin_cible['nom']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-users-cards.css<?php echo asset_version_query(); ?>">
    <style>
        .liste-activite-wrap { max-width: 1100px; margin: 0 auto 40px; }
        .liste-activite-table-wrap { overflow-x: auto; border-radius: 14px; border: 1px solid var(--glass-border, rgba(0,0,0,.08)); background: var(--glass-bg, #fff); box-shadow: var(--glass-shadow); }
        .liste-activite-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .liste-activite-table th, .liste-activite-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(0,0,0,.06); }
        .liste-activite-table th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--gris-moyen, #666); background: rgba(145, 138, 68, .08); }
        .liste-activite-table tr:hover td { background: rgba(194, 102, 56, .06); }
        .liste-activite-table a { color: var(--couleur-dominante); font-weight: 600; text-decoration: none; }
        .liste-activite-table a:hover { text-decoration: underline; }
        .liste-vide { padding: 48px 24px; text-align: center; color: var(--gris-moyen); }
    </style>
</head>
<body class="page-comptes">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-list-ul"></i> <?php echo htmlspecialchars($titre_liste); ?></h1>
        <div class="header-actions">
            <a href="employe-activite.php?admin_id=<?php echo (int) $admin_cible_id; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Activité</a>
            <a href="index.php" class="btn-back"><i class="fas fa-users"></i> Comptes</a>
        </div>
    </div>

    <section class="liste-activite-wrap">
        <p style="margin: 0 0 20px; font-size: 1.05rem;">
            <strong><?php echo htmlspecialchars($admin_cible['prenom'] . ' ' . $admin_cible['nom']); ?></strong>
            · <?php echo htmlspecialchars($admin_cible['email']); ?>
        </p>

        <?php if (empty($lignes)): ?>
            <div class="liste-vide">
                <i class="fas fa-inbox" style="font-size: 2.5rem; opacity: .35; display: block; margin-bottom: 12px;"></i>
                Aucun enregistrement pour ce type, ou la colonne de traçabilité n’est pas encore présente en base (exécutez la migration <code>add_admin_tracabilite_interactions.sql</code>).
            </div>
        <?php else: ?>
            <div class="liste-activite-table-wrap">
                <table class="liste-activite-table">
                    <thead>
                        <tr>
                        <?php if ($type === 'commandes_creees' || $type === 'commandes_traitees'): ?>
                            <th>N° commande</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Montant</th>
                            <th></th>
                        <?php elseif ($type === 'devis'): ?>
                            <th>N° devis</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Montant TTC</th>
                            <th></th>
                        <?php elseif ($type === 'factures_devis'): ?>
                            <th>N° facture</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th></th>
                        <?php elseif ($type === 'bl'): ?>
                            <th>N° BL</th>
                            <th>Date BL</th>
                            <th>Statut</th>
                            <th>Total HT</th>
                            <th></th>
                        <?php elseif ($type === 'factures_mensuelles'): ?>
                            <th>N° facture</th>
                            <th>Période</th>
                            <th>Total HT</th>
                            <th>Statut</th>
                            <th></th>
                        <?php elseif ($type === 'clients_b2b'): ?>
                            <th>Raison sociale</th>
                            <th>Téléphone</th>
                            <th>Date création</th>
                            <th></th>
                        <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes as $row): ?>
                        <tr>
                            <?php if ($type === 'commandes_creees' || $type === 'commandes_traitees'): ?>
                                <td><?php echo htmlspecialchars($row['numero_commande'] ?? ''); ?></td>
                                <td><?php echo !empty($row['date_commande']) ? date('d/m/Y H:i', strtotime($row['date_commande'])) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['statut'] ?? ''); ?></td>
                                <td><?php echo isset($row['montant_total']) ? number_format((float) $row['montant_total'], 2, ',', ' ') . ' €' : '—'; ?></td>
                                <td><a href="../commandes/details.php?id=<?php echo (int) ($row['id'] ?? 0); ?>">Ouvrir</a></td>
                            <?php elseif ($type === 'devis'): ?>
                                <td><?php echo htmlspecialchars($row['numero_devis'] ?? ''); ?></td>
                                <td><?php echo !empty($row['date_creation']) ? date('d/m/Y H:i', strtotime($row['date_creation'])) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['statut'] ?? ''); ?></td>
                                <td><?php echo isset($row['montant_total']) ? number_format((float) $row['montant_total'], 2, ',', ' ') . ' €' : '—'; ?></td>
                                <td><a href="../devis/details.php?id=<?php echo (int) ($row['id'] ?? 0); ?>">Ouvrir</a></td>
                            <?php elseif ($type === 'factures_devis'): ?>
                                <td><?php echo htmlspecialchars($row['numero_facture'] ?? ''); ?></td>
                                <td><?php echo !empty($row['date_facture']) ? date('d/m/Y', strtotime($row['date_facture'])) : '—'; ?></td>
                                <td><?php echo isset($row['montant_total']) ? number_format((float) $row['montant_total'], 2, ',', ' ') . ' €' : '—'; ?></td>
                                <td><a href="../devis/facture.php?id=<?php echo (int) ($row['id'] ?? 0); ?>">Ouvrir</a></td>
                            <?php elseif ($type === 'bl'): ?>
                                <td><?php echo htmlspecialchars($row['numero_bl'] ?? ''); ?></td>
                                <td><?php echo !empty($row['date_bl']) ? date('d/m/Y', strtotime($row['date_bl'])) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['statut'] ?? ''); ?></td>
                                <td><?php echo isset($row['total_ht']) ? number_format((float) $row['total_ht'], 2, ',', ' ') . ' €' : '—'; ?></td>
                                <td><a href="../devis/bl_voir.php?id=<?php echo (int) ($row['id'] ?? 0); ?>">Ouvrir</a></td>
                            <?php elseif ($type === 'factures_mensuelles'): ?>
                                <td><?php echo htmlspecialchars($row['numero_facture'] ?? ''); ?></td>
                                <td><?php echo (int) ($row['mois'] ?? 0); ?> / <?php echo (int) ($row['annee'] ?? 0); ?></td>
                                <td><?php echo isset($row['total_ht']) ? number_format((float) $row['total_ht'], 2, ',', ' ') . ' €' : '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['statut'] ?? ''); ?></td>
                                <td><a href="../devis/facture_mensuelle.php?id=<?php echo (int) ($row['id'] ?? 0); ?>">Ouvrir</a></td>
                            <?php elseif ($type === 'clients_b2b'): ?>
                                <td><?php echo htmlspecialchars($row['raison_sociale'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['telephone'] ?? '—'); ?></td>
                                <td><?php echo !empty($row['date_creation']) ? date('d/m/Y', strtotime($row['date_creation'])) : '—'; ?></td>
                                <td><a href="../devis/bl_par_client.php?id=<?php echo (int) ($row['id'] ?? 0); ?>">Fiche client</a></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
