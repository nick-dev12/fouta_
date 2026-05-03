<?php
/**
 * Liste des fiches employés (RH) — table employes
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh'], true)) {
    header('Location: ../../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../../models/model_employes.php';
require_once __DIR__ . '/../../../includes/site_url.php';

$upload_public = rtrim(get_request_origin_base_url(), '/') . '/upload/';
$upload_disk = __DIR__ . '/../../../upload/';

$fiches = get_all_employes(null);
$fiches_actifs = count(array_filter($fiches, function ($r) {
    return ($r['statut'] ?? '') === 'actif';
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employés — Administration</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-comptes-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employes-rh.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes page-employes-rh">
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="page-comptes-wrap er-page">
        <header class="comptes-header-bar page-comptes-hero er-hero">
            <div class="page-comptes-hero__text">
                <p class="page-comptes-eyebrow">Ressources humaines</p>
                <h1><i class="fas fa-id-card-clip" aria-hidden="true"></i> Employés</h1>
                <p class="comptes-lead">Fiches enregistrées dans la table <strong>employes</strong> pour les <strong>absences</strong> et le suivi interne.</p>
            </div>
            <div class="comptes-header-actions page-comptes-hero__actions er-hero-actions">
                <a href="ajouter.php" class="page-comptes-cta er-btn-primary">
                    <i class="fas fa-user-plus" aria-hidden="true"></i> Ajouter un employé
                </a>
                <a href="../absences.php" class="page-comptes-cta page-comptes-cta--secondary">
                    <i class="fas fa-calendar-xmark" aria-hidden="true"></i> Absences
                </a>
                <a href="../index.php" class="page-comptes-cta page-comptes-cta--ghost">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Comptes d’accès
                </a>
            </div>
        </header>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success page-comptes-flash" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error page-comptes-flash" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="er-kpis">
            <div class="er-kpi er-kpi--total">
                <span class="er-kpi__ic" aria-hidden="true"><i class="fas fa-users"></i></span>
                <div>
                    <span class="er-kpi__lbl">Fiches</span>
                    <span class="er-kpi__val"><?php echo count($fiches); ?></span>
                </div>
            </div>
            <div class="er-kpi er-kpi--actif">
                <span class="er-kpi__ic" aria-hidden="true"><i class="fas fa-user-check"></i></span>
                <div>
                    <span class="er-kpi__lbl">Actifs</span>
                    <span class="er-kpi__val"><?php echo (int) $fiches_actifs; ?></span>
                </div>
            </div>
        </div>

        <?php if (empty($fiches)): ?>
            <div class="er-empty">
                <div class="er-empty__ic" aria-hidden="true"><i class="fas fa-folder-open"></i></div>
                <h2>Aucune fiche</h2>
                <p>Ajoutez un employé pour qu’il apparaisse aussi dans les formulaires d’absence.</p>
                <a href="ajouter.php" class="page-comptes-cta er-btn-primary">Ajouter le premier employé</a>
            </div>
        <?php else: ?>
            <ul class="er-grid">
                <?php foreach ($fiches as $f): ?>
                    <?php
                    $ph_rel = trim((string) ($f['photo_chemin'] ?? ''));
                    $ph_ok = $ph_rel !== '' && strpos($ph_rel, '..') === false && is_file($upload_disk . str_replace('/', DIRECTORY_SEPARATOR, $ph_rel));
                    ?>
                    <li class="er-card<?php echo ($f['statut'] ?? '') !== 'actif' ? ' er-card--muted' : ''; ?>">
                        <div class="er-card__head">
                            <?php if ($ph_ok): ?>
                            <span class="er-card__avatar er-card__avatar--photo" aria-hidden="true">
                                <img src="<?php echo htmlspecialchars($upload_public . $ph_rel); ?>" alt="" width="52" height="52" decoding="async" class="er-card__avatar-img">
                            </span>
                            <?php else: ?>
                            <span class="er-card__avatar" aria-hidden="true"><?php echo strtoupper(substr((string) ($f['prenom'] ?? '?'), 0, 1)); ?></span>
                            <?php endif; ?>
                            <div class="er-card__id">
                                <h2 class="er-card__name"><?php echo htmlspecialchars(trim(($f['prenom'] ?? '') . ' ' . ($f['nom'] ?? ''))); ?></h2>
                                <span class="er-card__fonction"><?php echo htmlspecialchars(($f['poste'] ?? '') !== '' ? $f['poste'] : '—'); ?></span>
                            </div>
                        </div>
                        <span class="er-card__badge er-card__badge--<?php echo htmlspecialchars($f['statut'] ?? 'actif'); ?>">
                            <?php echo ($f['statut'] ?? '') === 'actif' ? 'Actif' : (($f['statut'] ?? '') === 'inactif' ? 'Inactif' : 'Suspendu'); ?>
                        </span>
                        <?php if (!empty($f['email'])): ?>
                            <p class="er-card__meta"><i class="fas fa-envelope" aria-hidden="true"></i> <?php echo htmlspecialchars($f['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($f['telephone'])): ?>
                            <p class="er-card__meta"><i class="fas fa-phone" aria-hidden="true"></i> <?php echo htmlspecialchars((string) $f['telephone']); ?></p>
                        <?php endif; ?>
                        <div class="er-card__footer er-card__actions">
                            <a href="details.php?id=<?php echo (int) $f['id']; ?>" class="er-card__btn er-card__btn--secondary"><i class="fas fa-eye" aria-hidden="true"></i> Détails</a>
                            <a href="modifier.php?id=<?php echo (int) $f['id']; ?>" class="er-card__btn"><i class="fas fa-pen" aria-hidden="true"></i> Modifier</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>
