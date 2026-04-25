<?php
/**
 * Activité métier liée à un compte d'accès interne (BL, factures, etc.)
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
require_once __DIR__ . '/../../models/model_employes.php';
require_once __DIR__ . '/../../models/model_admin_activite.php';

$admin_cible_id = isset($_GET['admin_id']) ? (int) $_GET['admin_id'] : 0;
if ($admin_cible_id <= 0) {
    header('Location: index.php');
    exit;
}

$admin_cible = get_admin_by_id($admin_cible_id);
if (!$admin_cible) {
    header('Location: index.php');
    exit;
}

$employe_lie = get_employe_by_admin_id($admin_cible_id);
$stats = get_stats_activite_par_admin_id($admin_cible_id);

$initiale = strtoupper(mb_substr(trim($admin_cible['prenom'] ?? ''), 0, 1, 'UTF-8'));
if ($initiale === '') {
    $initiale = '?';
}

$liens_activite = [
    ['type' => 'commandes_creees', 'icon' => 'fa-file-circle-plus', 'label' => 'Commandes créées (manuel)', 'hint' => 'Saisie manuelle depuis l’admin'],
    ['type' => 'commandes_traitees', 'icon' => 'fa-truck-fast', 'label' => 'Dernier traitement commande', 'hint' => 'Changements de statut enregistrés'],
    ['type' => 'devis', 'icon' => 'fa-file-invoice', 'label' => 'Devis créés', 'hint' => ''],
    ['type' => 'factures_devis', 'icon' => 'fa-receipt', 'label' => 'Factures (devis)', 'hint' => 'PDF / facture liée au devis'],
    ['type' => 'bl', 'icon' => 'fa-dolly', 'label' => 'Bons de livraison', 'hint' => 'BL créés par ce compte'],
    ['type' => 'factures_mensuelles', 'icon' => 'fa-calendar-check', 'label' => 'Factures mensuelles HT', 'hint' => 'B2B — regroupement BL'],
    ['type' => 'clients_b2b', 'icon' => 'fa-building', 'label' => 'Clients B2B enregistrés', 'hint' => 'Fiches créées depuis l’admin'],
];

$page_title = 'Activité — ' . htmlspecialchars($admin_cible['prenom'] . ' ' . $admin_cible['nom']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-users-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employe-activite.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes page-employe-activite">
    <?php include '../includes/nav.php'; ?>

    <div class="ea-page-wrap">
    <header class="content-header ea-content-header">
        <div class="ea-content-header__intro">
            <h1 class="ea-content-header__h1" id="ea-page-h1"><i class="fas fa-chart-line" aria-hidden="true"></i> Activité du compte</h1>
            <p class="ea-content-header__lede">Statistiques et listes liées à ce compte d’accès (commandes, devis, BL, factures, clients B2B).</p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn-back ea-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux comptes</a>
        </div>
    </header>

    <section class="ea-identity" aria-labelledby="ea-titre">
        <div class="ea-identity__avatar" aria-hidden="true"><?php echo htmlspecialchars($initiale); ?></div>
        <div class="ea-identity__body">
            <p class="ea-identity__eyebrow">Compte d’accès</p>
            <h2 class="ea-identity__title" id="ea-titre"><?php echo htmlspecialchars($admin_cible['prenom'] . ' ' . $admin_cible['nom']); ?></h2>
            <p class="ea-identity__email">
                <i class="fas fa-envelope" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($admin_cible['email']); ?></span>
            </p>
            <p class="ea-identity__role">
                <span class="role-badge role-<?php echo htmlspecialchars($admin_cible['role'] ?? 'utilisateur'); ?>"><?php echo htmlspecialchars(admin_role_label($admin_cible['role'] ?? 'utilisateur')); ?></span>
            </p>
            <?php if ($employe_lie): ?>
            <p class="ea-identity__rh">
                <i class="fas fa-id-card" aria-hidden="true"></i>
                Fiche employé : <a href="employes/modifier.php?id=<?php echo (int) $employe_lie['id']; ?>">Ouvrir la fiche RH</a>
            </p>
            <?php else: ?>
            <p class="ea-identity__rh ea-identity__rh--muted">Aucune fiche employé liée.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="ea-section" aria-labelledby="ea-synthese">
        <h2 class="ea-section__title" id="ea-synthese"><i class="fas fa-gauge-high" aria-hidden="true"></i> Synthèse</h2>
        <div class="ea-kpis" role="list" aria-label="Indicateurs d’activité">
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-cart-plus" aria-hidden="true"></i> Commandes créées</div>
                <div class="kpi-val"><?php echo $stats['trace_commandes_creees'] ? number_format($stats['nb_commandes_creees'], 0, ',', ' ') : '—'; ?></div>
            </div>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Dernier traitement commande</div>
                <div class="kpi-val"><?php echo $stats['trace_commandes'] ? number_format($stats['nb_commandes_traitees'], 0, ',', ' ') : '—'; ?></div>
            </div>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-file-signature" aria-hidden="true"></i> Devis créés</div>
                <div class="kpi-val"><?php echo $stats['trace_devis'] ? number_format($stats['nb_devis'], 0, ',', ' ') : '—'; ?></div>
            </div>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Factures (devis)</div>
                <div class="kpi-val"><?php echo $stats['trace_factures_devis'] ? number_format($stats['nb_factures_devis'], 0, ',', ' ') : '—'; ?></div>
            </div>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Factures mensuelles HT</div>
                <div class="kpi-val"><?php echo number_format($stats['nb_factures_mensuelles'], 0, ',', ' '); ?></div>
            </div>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-truck-loading" aria-hidden="true"></i> BL créés / validés</div>
                <div class="kpi-val"><?php echo number_format($stats['nb_bl_total'], 0, ',', ' '); ?> <span class="ea-kpi-sub">/ <?php echo number_format($stats['nb_bl_valides'], 0, ',', ' '); ?> val.</span></div>
            </div>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-industry" aria-hidden="true"></i> Clients B2B</div>
                <div class="kpi-val"><?php echo $stats['trace_clients_b2b'] ? number_format($stats['nb_clients_b2b_crees'], 0, ',', ' ') : '—'; ?></div>
            </div>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-clock" aria-hidden="true"></i> Heures (indicatif)</div>
                <div class="kpi-val"><?php echo $stats['heures_indicatif'] !== null ? number_format($stats['heures_indicatif'], 0, ',', ' ') . ' h' : '—'; ?></div>
            </div>
        </div>
    </section>

    <section class="ea-section" aria-labelledby="ea-detail">
        <h2 class="ea-section__title" id="ea-detail"><i class="fas fa-list-check" aria-hidden="true"></i> Consulter le détail</h2>
        <div class="ea-actions">
            <?php foreach ($liens_activite as $la): ?>
            <a class="ea-action" href="employe-activite-liste.php?admin_id=<?php echo (int) $admin_cible_id; ?>&amp;type=<?php echo htmlspecialchars($la['type']); ?>">
                <span class="ea-action-icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($la['icon']); ?>"></i></span>
                <span class="ea-action__text">
                    <strong class="ea-action__label"><?php echo htmlspecialchars($la['label']); ?></strong>
                    <?php if ($la['hint'] !== ''): ?>
                    <small class="ea-action__hint"><?php echo htmlspecialchars($la['hint']); ?></small>
                    <?php endif; ?>
                </span>
                <i class="fas fa-chevron-right ea-action__chev" aria-hidden="true"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="ea-note" role="note">
        <p class="ea-note__p"><span class="ea-note__lead">Traçabilité :</span> les colonnes <code>admin_createur_id</code> / <code>admin_dernier_traitement_id</code> lient les actions au compte interne après exécution de la migration SQL
        <code>migrations/add_admin_tracabilite_interactions.sql</code>. Les indicateurs « — » signifient que la colonne n’existe pas encore ou qu’aucune donnée n’est enregistrée.
        L’indicateur « heures » est la différence entre la date de création du compte et la dernière connexion (valeur indicative, pas un relevé de temps de travail).</p>
    </aside>

    </div><!-- .ea-page-wrap -->

    <?php include '../includes/footer.php'; ?>
</body>
</html>
