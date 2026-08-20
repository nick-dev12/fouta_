<?php
/**
 * Impression carte employé — même rendu et même structure DOM que la fiche détail (#adminContent … .er-detail-card--carte-rh .er-carte-rh).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh', 'informaticien', 'developpeur'], true)) {
    header('Location: ../../dashboard.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../../includes/carte_employe_rh.php';

$carte_prep = employes_carte_rh_preparer_variables($id);
if (!$carte_prep) {
    header('Location: index.php');
    exit;
}

$carte_html = employes_carte_rh_rendre_html($carte_prep, '');
$titre = 'Carte employé — impression';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titre); ?></title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-comptes-page.css'); ?>
    <?php fpl_css_link('admin-employes-rh.css'); ?>
</head>
<body class="page-comptes page-employes-rh page-employes-carte-impression">
    <div class="carte-impression-toolbar carte-impression-toolbar--no-print" role="toolbar" aria-label="Actions avant impression">
        <a href="details.php?id=<?php echo (int) $id; ?>" class="carte-impression-toolbar__link-back">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour fiche
        </a>
        <button type="button" class="carte-impression-toolbar__btn-print" id="btnWindowPrint">
            <i class="fas fa-print" aria-hidden="true"></i> Imprimer…
        </button>
        <span class="carte-impression-toolbar__hint">La zone imprimée reprend strictement la même carte que sur la fiche employé.</span>
    </div>

    <div class="admin-container admin-container--carte-impression">
        <main class="admin-content" id="adminContent">
            <div class="page-comptes-wrap er-page">
                <div class="er-detail-grid er-detail-grid--carte-seule">
                    <section class="er-detail-card er-detail-card--carte-rh" aria-label="Carte d'identité employé">
                        <?php echo $carte_html; ?>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.getElementById('btnWindowPrint').addEventListener('click', function () {
        window.print();
    });
    </script>
</body>
</html>
