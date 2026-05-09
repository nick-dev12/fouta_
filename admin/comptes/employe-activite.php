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
if (!in_array($role, ['admin', 'rh', 'informaticien'], true)) {
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

$types_role = admin_activite_types_pour_role($admin_cible['role'] ?? 'admin');
$stats = get_stats_activite_par_admin_id($admin_cible_id, $types_role);

$initiale = strtoupper(mb_substr(trim($admin_cible['prenom'] ?? ''), 0, 1, 'UTF-8'));
if ($initiale === '') {
    $initiale = '?';
}

/** Définitions complètes : filtrées par $types_role */
$activite_blocs = [
    'commandes_creees' => [
        'icon' => 'fa-file-circle-plus',
        'label' => 'Commandes créées (manuel)',
        'hint' => 'Saisie manuelle depuis l’admin',
        'kpi' => 'Commandes créées',
        'nb_key' => 'nb_commandes_creees',
        'trace_key' => 'trace_commandes_creees',
    ],
    'commandes_traitees' => [
        'icon' => 'fa-truck-fast',
        'label' => 'Dernier traitement commande',
        'hint' => 'Changements de statut enregistrés',
        'kpi' => 'Dernier traitement commande',
        'nb_key' => 'nb_commandes_traitees',
        'trace_key' => 'trace_commandes',
    ],
    'devis' => [
        'icon' => 'fa-file-invoice',
        'label' => 'Devis créés',
        'hint' => '',
        'kpi' => 'Devis créés',
        'nb_key' => 'nb_devis',
        'trace_key' => 'trace_devis',
    ],
    'factures_devis' => [
        'icon' => 'fa-receipt',
        'label' => 'Factures (devis)',
        'hint' => 'PDF / facture liée au devis',
        'kpi' => 'Factures (devis)',
        'nb_key' => 'nb_factures_devis',
        'trace_key' => 'trace_factures_devis',
    ],
    'bl' => [
        'icon' => 'fa-dolly',
        'label' => 'Bons de livraison',
        'hint' => 'BL créés par ce compte',
        'kpi' => 'BL créés / validés',
        'nb_key' => 'nb_bl_total',
        'trace_key' => null,
    ],
    'factures_mensuelles' => [
        'icon' => 'fa-calendar-check',
        'label' => 'Factures mensuelles HT',
        'hint' => 'B2B — regroupement BL',
        'kpi' => 'Factures mensuelles HT',
        'nb_key' => 'nb_factures_mensuelles',
        'trace_key' => null,
    ],
    'clients_b2b' => [
        'icon' => 'fa-building',
        'label' => 'Clients B2B enregistrés',
        'hint' => 'Fiches créées depuis l’admin',
        'kpi' => 'Clients B2B',
        'nb_key' => 'nb_clients_b2b_crees',
        'trace_key' => 'trace_clients_b2b',
    ],
    'caisse_encaissements' => [
        'icon' => 'fa-cash-register',
        'label' => 'Encaissements caisse',
        'hint' => 'Ventes encaissées (rôle caissier)',
        'kpi' => 'Encaissements caisse',
        'nb_key' => 'nb_caisse_encaissements',
        'trace_key' => 'trace_caisse_encaissements',
    ],
    'caisse_tickets_bureau' => [
        'icon' => 'fa-ticket',
        'label' => 'Tickets / ventes caisse (bureau)',
        'hint' => 'Tickets générés depuis la caisse vendeur',
        'kpi' => 'Tickets caisse (bureau)',
        'nb_key' => 'nb_caisse_tickets_bureau',
        'trace_key' => 'trace_caisse_tickets_bureau',
    ],
    'produits_crees' => [
        'icon' => 'fa-box-open',
        'label' => 'Produits créés',
        'hint' => 'Enregistrements dont ce compte est le créateur',
        'kpi' => 'Produits créés',
        'nb_key' => 'nb_produits_crees',
        'trace_key' => 'trace_produits_crees',
    ],
    'produits_modifies' => [
        'icon' => 'fa-pen-to-square',
        'label' => 'Produits modifiés',
        'hint' => 'Dernière modification enregistrée pour ce compte',
        'kpi' => 'Produits modifiés',
        'nb_key' => 'nb_produits_modifies',
        'trace_key' => 'trace_produits_modifies',
    ],
    'categories_crees' => [
        'icon' => 'fa-folder-plus',
        'label' => 'Catégories créées',
        'hint' => '',
        'kpi' => 'Catégories créées',
        'nb_key' => 'nb_categories_crees',
        'trace_key' => 'trace_categories_crees',
    ],
    'categories_modifiees' => [
        'icon' => 'fa-folder-tree',
        'label' => 'Catégories modifiées',
        'hint' => 'Dernière modification enregistrée',
        'kpi' => 'Catégories modifiées',
        'nb_key' => 'nb_categories_modifiees',
        'trace_key' => 'trace_categories_modifiees',
    ],
    'mouvements_stock' => [
        'icon' => 'fa-right-left',
        'label' => 'Mouvements de stock',
        'hint' => 'Entrées, sorties, inventaires liés à ce compte',
        'kpi' => 'Mouvements de stock',
        'nb_key' => 'nb_mouvements_stock',
        'trace_key' => 'trace_mouvements_stock',
    ],
];

$liens_activite = [];
foreach ($types_role as $tr) {
    if (isset($activite_blocs[$tr])) {
        $liens_activite[] = array_merge(['type' => $tr], $activite_blocs[$tr]);
    }
}

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
            <p class="ea-content-header__lede">Statistiques et listes filtrées selon le <strong>rôle</strong> du compte (même périmètre que les pages auxquelles il a accès).</p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn-back ea-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux comptes</a>
        </div>
    </header>

    <section class="ea-identity ea-identity-card" aria-labelledby="ea-titre">
        <div class="ea-identity-card__splash">
            <div class="ea-identity__avatar" aria-hidden="true"><?php echo htmlspecialchars($initiale); ?></div>
            <div class="ea-identity-card__splash-text">
                <p class="ea-identity__eyebrow">Compte d’accès</p>
                <h2 class="ea-identity__title" id="ea-titre"><?php echo htmlspecialchars($admin_cible['prenom'] . ' ' . $admin_cible['nom']); ?></h2>
            </div>
        </div>
        <div class="ea-identity-card__panel">
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
        <?php if (empty($types_role)): ?>
            <p class="ea-empty-role" style="padding:16px 0;margin:0;color:var(--gris-moyen,#666);max-width:42rem;">
                Aucune activité métier n’est tracée pour ce rôle (<strong><?php echo htmlspecialchars(admin_role_label($admin_cible['role'] ?? 'utilisateur')); ?></strong>) dans cette vue. Les fiches RH se consultent depuis la gestion des employés.
            </p>
        <?php endif; ?>
        <div class="ea-kpis" role="list" aria-label="Indicateurs d’activité">
            <?php foreach ($types_role as $tr):
                if (!isset($activite_blocs[$tr])) {
                    continue;
                }
                $b = $activite_blocs[$tr];
                $trk = $b['trace_key'] ?? null;
                $n = (int) ($stats[$b['nb_key']] ?? 0);
                if ($tr === 'bl') {
                    $val_html = number_format($stats['nb_bl_total'] ?? 0, 0, ',', ' ') . ' <span class="ea-kpi-sub">/ ' . number_format($stats['nb_bl_valides'] ?? 0, 0, ',', ' ') . ' val.</span>';
                } elseif ($trk !== null && empty($stats[$trk])) {
                    $val_html = '—';
                } else {
                    $val_html = htmlspecialchars(number_format($n, 0, ',', ' '), ENT_QUOTES, 'UTF-8');
                }
            ?>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas <?php echo htmlspecialchars($b['icon']); ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($b['kpi']); ?></div>
                <div class="kpi-val"><?php echo $val_html; ?></div>
            </div>
            <?php endforeach; ?>
            <div class="ea-kpi" role="listitem">
                <div class="kpi-label"><i class="fas fa-clock" aria-hidden="true"></i> Heures (indicatif)</div>
                <div class="kpi-val"><?php echo $stats['heures_indicatif'] !== null ? number_format($stats['heures_indicatif'], 0, ',', ' ') . ' h' : '—'; ?></div>
            </div>
        </div>
    </section>

    <section class="ea-section" aria-labelledby="ea-detail">
        <h2 class="ea-section__title" id="ea-detail"><i class="fas fa-list-check" aria-hidden="true"></i> Consulter le détail</h2>
        <?php if (empty($liens_activite)): ?>
            <p style="margin:0;color:var(--gris-moyen,#666);">Aucun détail listable pour ce rôle.</p>
        <?php else: ?>
        <div class="ea-actions">
            <?php foreach ($liens_activite as $la): ?>
            <a class="ea-action" href="employe-activite-liste.php?admin_id=<?php echo (int) $admin_cible_id; ?>&amp;type=<?php echo htmlspecialchars($la['type']); ?>">
                <span class="ea-action-icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($la['icon']); ?>"></i></span>
                <span class="ea-action__text">
                    <strong class="ea-action__label"><?php echo htmlspecialchars($la['label']); ?></strong>
                    <?php if (!empty($la['hint'])): ?>
                    <small class="ea-action__hint"><?php echo htmlspecialchars($la['hint']); ?></small>
                    <?php endif; ?>
                </span>
                <i class="fas fa-chevron-right ea-action__chev" aria-hidden="true"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    </div><!-- .ea-page-wrap -->

    <?php include '../includes/footer.php'; ?>
</body>
</html>
