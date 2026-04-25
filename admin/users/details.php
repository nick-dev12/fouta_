<?php
/**
 * Fiche détail client (compte site + commandes)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

$__ar = $_SESSION['admin_role'] ?? '';
if ($__ar === 'utilisateur') {
    $__ar = 'gestion_stock';
}
if (!in_array($__ar, ['admin', 'rh'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($user_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_users.php';
require_once __DIR__ . '/../../models/model_commandes.php';
require_once __DIR__ . '/../../models/model_commandes_personnalisees.php';

$user = get_user_by_id($user_id);
if (!$user) {
    header('Location: index.php');
    exit;
}

$stats = get_user_stats_commandes_boutique($user_id);
$commandes = get_commandes_by_user($user_id);
$commandes_perso = get_commandes_personnalisees_by_user($user_id);

function statut_commande_libelle($s)
{
    $m = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'en_preparation' => 'En préparation',
        'prise_en_charge' => 'Prise en charge',
        'livraison_en_cours' => 'Livraison en cours',
        'livree' => 'Livrée',
        'paye' => 'Payée',
        'annulee' => 'Annulée',
    ];
    return $m[$s] ?? ucfirst(str_replace('_', ' ', (string) $s));
}

$is_actif = ($user['statut'] ?? '') === 'actif';
$initiale = strtoupper(substr((string) ($user['prenom'] ?? '?'), 0, 1));
$nom_complet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nom_complet !== '' ? $nom_complet : 'Client'); ?> — Fiche client</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-clients-index.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-clients-detail.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page admin-client-detail-page">
    <?php include '../includes/nav.php'; ?>

    <div class="admin-clients-shell">
        <header class="admin-detail-hero">
            <div class="admin-detail-hero__left">
                <div class="admin-detail-hero__avatar" aria-hidden="true"><?php echo htmlspecialchars($initiale); ?></div>
                <div class="admin-detail-hero__title-block">
                    <h1><i class="fas fa-user-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($nom_complet); ?></h1>
                    <p class="admin-detail-hero__meta">
                        <span>Compte créé le <?php echo date('d/m/Y à H:i', strtotime($user['date_creation'])); ?></span>
                        <span class="admin-detail-hero__badge<?php echo $is_actif ? ' admin-detail-hero__badge--actif' : ' admin-detail-hero__badge--inactif'; ?>">
                            <?php echo $is_actif ? 'Actif' : 'Inactif'; ?>
                        </span>
                    </p>
                </div>
            </div>
            <a href="index.php" class="admin-clients-btn admin-clients-btn--outline">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
            </a>
        </header>

        <div class="admin-clients-kpis" aria-label="Synthèse du client">
            <div class="admin-clients-kpi">
                <div class="admin-clients-kpi__icon" aria-hidden="true"><i class="fas fa-shopping-bag"></i></div>
                <div>
                    <div class="admin-clients-kpi__label">Commandes boutique</div>
                    <div class="admin-clients-kpi__value"><?php echo (int) ($stats['nb_commandes'] ?? 0); ?></div>
                </div>
            </div>
            <div class="admin-clients-kpi">
                <div class="admin-clients-kpi__icon" aria-hidden="true"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="admin-clients-kpi__label">CA HT (non annulées)</div>
                    <div class="admin-clients-kpi__value" style="font-size: 1.35rem;">
                        <?php echo number_format((float) ($stats['ca_total_ht'] ?? 0), 0, ',', ' '); ?>
                    </div>
                </div>
            </div>
            <div class="admin-clients-kpi">
                <div class="admin-clients-kpi__icon" aria-hidden="true"><i class="fas fa-palette"></i></div>
                <div>
                    <div class="admin-clients-kpi__label">Commandes perso.</div>
                    <div class="admin-clients-kpi__value"><?php echo count($commandes_perso); ?></div>
                </div>
            </div>
        </div>

        <div class="admin-detail-layout">
            <section class="admin-detail-panel" aria-labelledby="client-coords">
                <div class="admin-detail-panel__head">
                    <h2 id="client-coords"><i class="fas fa-id-card" aria-hidden="true"></i> Coordonnées</h2>
                </div>
                <dl class="admin-detail-dl">
                    <div>
                        <dt>Nom</dt>
                        <dd><?php echo htmlspecialchars($user['nom'] ?? ''); ?></dd>
                    </div>
                    <div>
                        <dt>Prénom</dt>
                        <dd><?php echo htmlspecialchars($user['prenom'] ?? ''); ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>
                            <a href="mailto:<?php echo htmlspecialchars($user['email'] ?? ''); ?>"><?php echo htmlspecialchars($user['email'] ?? ''); ?></a>
                        </dd>
                    </div>
                    <div>
                        <dt>Téléphone</dt>
                        <dd><?php echo htmlspecialchars($user['telephone'] ?? '—'); ?></dd>
                    </div>
                </dl>
            </section>

            <section class="admin-detail-panel" aria-labelledby="client-commandes">
                <div class="admin-detail-panel__head">
                    <h2 id="client-commandes"><i class="fas fa-shopping-cart" aria-hidden="true"></i> Commandes enregistrées sur le site</h2>
                </div>
                <?php if (empty($commandes)): ?>
                <p class="admin-detail-empty">Aucune commande boutique pour ce client.</p>
                <?php else: ?>
                <div class="admin-detail-table-wrap">
                    <table class="admin-detail-table">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $cmd): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cmd['numero_commande'] ?? '#' . $cmd['id']); ?></strong></td>
                                <td><?php echo isset($cmd['date_commande']) ? date('d/m/Y H:i', strtotime($cmd['date_commande'])) : '—'; ?></td>
                                <td><?php echo number_format((float) ($cmd['montant_total'] ?? 0), 0, ',', ' '); ?> FCFA</td>
                                <td>
                                    <span class="admin-detail-badge badge-statut statut-<?php echo htmlspecialchars(preg_replace('/[^a-z0-9_]/i', '', (string) ($cmd['statut'] ?? ''))); ?>">
                                        <?php echo htmlspecialchars(statut_commande_libelle($cmd['statut'] ?? '')); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../commandes/details.php?id=<?php echo (int) $cmd['id']; ?>" class="admin-detail-link">
                                        <i class="fas fa-eye" aria-hidden="true"></i> Détail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <?php if (!empty($commandes_perso)): ?>
            <section class="admin-detail-panel" aria-labelledby="client-perso">
                <div class="admin-detail-panel__head">
                    <h2 id="client-perso"><i class="fas fa-palette" aria-hidden="true"></i> Commandes personnalisées</h2>
                </div>
                <div class="admin-detail-table-wrap">
                    <table class="admin-detail-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes_perso as $cp): ?>
                            <tr>
                                <td><strong>#<?php echo (int) $cp['id']; ?></strong></td>
                                <td><?php echo !empty($cp['date_creation']) ? date('d/m/Y H:i', strtotime($cp['date_creation'])) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($cp['statut'] ?? '—'); ?></td>
                                <td>
                                    <a href="../commandes-personnalisees/details.php?id=<?php echo (int) $cp['id']; ?>" class="admin-detail-link">
                                        <i class="fas fa-eye" aria-hidden="true"></i> Détail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <aside class="admin-detail-footnote">
                <h2><i class="fas fa-route" aria-hidden="true"></i> Périmètre métier (B2B / compta)</h2>
                <p>
                    Les clients <strong>professionnels</strong> (BL, factures mensuelles HT) sont gérés dans l’espace
                    <a href="../commercial/index.php">Commercial</a> / <a href="../comptabilite/index.php">Comptabilité</a>, pas sur cette fiche.
                    Cette page concerne les <strong>comptes clients du site</strong> et leurs commandes en ligne.
                </p>
            </aside>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
