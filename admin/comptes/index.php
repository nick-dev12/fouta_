<?php
/**
 * Comptes d'accès internes (administration) — réservé au rôle administrateur
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if ($role === 'utilisateur') {
    $role = 'gestion_stock';
}
if (!in_array($role, ['admin', 'rh'], true)) {
    $_SESSION['error_message'] = 'Accès réservé aux administrateurs ou aux RH.';
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_admin.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['admin_id'])) {
    $admin_id = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;

    if ($admin_id > 0) {
        if ($admin_id === (int) $_SESSION['admin_id']) {
            $error_message = 'Vous ne pouvez pas modifier votre propre compte depuis cette page.';
        } else {
            if (isset($_POST['toggle_statut'])) {
                $nouveau_statut = $_POST['nouveau_statut'] ?? '';
                if (in_array($nouveau_statut, ['actif', 'inactif']) && update_admin_statut($admin_id, $nouveau_statut)) {
                    $success_message = $nouveau_statut === 'actif' ? 'Compte activé avec succès.' : 'Compte désactivé avec succès.';
                } else {
                    $error_message = 'Erreur lors de la modification du statut.';
                }
            } elseif (isset($_POST['definir_role'])) {
                $nouveau_role = $_POST['nouveau_role'] ?? '';
                if (in_array($nouveau_role, admin_roles_valides(), true) && update_admin_role($admin_id, $nouveau_role)) {
                    $success_message = 'Rôle mis à jour avec succès.';
                } else {
                    $error_message = 'Erreur lors de la modification du rôle.';
                }
            }
        }
    }
}

$admins = get_all_admins();
$total = count($admins);
$admins_actifs = count(array_filter($admins, function ($a) { return $a['statut'] === 'actif'; }));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptes d’accès — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-users-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-comptes-page.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes page-comptes-index">
    <?php include '../includes/nav.php'; ?>

    <div class="page-comptes-wrap">
    <header class="comptes-header-bar page-comptes-hero">
        <div class="page-comptes-hero__text">
            <p class="page-comptes-eyebrow">Gestion des accès</p>
            <h1 id="page-comptes-title"><i class="fas fa-user-shield" aria-hidden="true"></i> Comptes d’accès administration</h1>
            <p class="comptes-lead">Comptes internes pour l’espace admin (rôles, activité, activation). Les <strong>clients du site e-commerce</strong> sont gérés séparément.</p>
        </div>
        <div class="comptes-header-actions page-comptes-hero__actions">
            <a href="../inscription-admin.php" class="btn-open-emp-modal btn-inscription-admin-link page-comptes-cta">
                <i class="fas fa-user-plus" aria-hidden="true"></i> Ajouter un compte
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

    <?php if ($success_message): ?>
        <div class="message success page-comptes-flash" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="message error page-comptes-flash" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="users-stats page-comptes-kpis" aria-label="Synthèse des comptes">
        <div class="stat-box page-comptes-kpi page-comptes-kpi--total">
            <span class="page-comptes-kpi__ic" aria-hidden="true"><i class="fas fa-users"></i></span>
            <div class="page-comptes-kpi__body">
                <h3>Comptes (interne)</h3>
                <div class="stat-value"><?php echo (int) $total; ?></div>
            </div>
        </div>
        <div class="stat-box page-comptes-kpi page-comptes-kpi--actifs">
            <span class="page-comptes-kpi__ic" aria-hidden="true"><i class="fas fa-user-check"></i></span>
            <div class="page-comptes-kpi__body">
                <h3>Comptes actifs</h3>
                <div class="stat-value"><?php echo (int) $admins_actifs; ?></div>
            </div>
        </div>
    </div>

    <section class="page-comptes-main" aria-labelledby="comptes-list-heading">
    <h2 id="comptes-list-heading" class="hub-section-title page-comptes-section-title"><i class="fas fa-users-gear" aria-hidden="true"></i> Utilisateurs de l’espace admin</h2>
    <p class="section-subtitle section-subtitle-comptes">
        Connexions à l’administration (rôles : commercial, comptabilité, RH, etc.). Distinct des
        <strong>clients du site</strong> — voir <a href="../users/index.php">la liste des clients</a>.
    </p>

    <?php if (empty($admins)): ?>
        <div class="empty-state page-comptes-empty">
            <div class="page-comptes-empty__icon" aria-hidden="true"><i class="fas fa-user-shield"></i></div>
            <h3>Aucun compte</h3>
            <p>Aucun compte d’accès n’est enregistré pour l’instant.</p>
            <a href="../inscription-admin.php" class="btn-primary page-comptes-empty__btn"><i class="fas fa-plus" aria-hidden="true"></i> Créer le premier compte</a>
        </div>
    <?php else: ?>
        <div class="users-grid page-comptes-grid comptes-acces-grid">
            <?php foreach ($admins as $admin): ?>
                <?php
                $is_self = ((int) $admin['id'] === (int) $_SESSION['admin_id']);
                $esc_role = htmlspecialchars($admin['role'] ?? 'utilisateur', ENT_QUOTES, 'UTF-8');
                ?>
                <article class="comptes-acces-card<?php echo $admin['statut'] === 'inactif' ? ' comptes-acces-card--inactive' : ''; ?>"
                    data-statut="<?php echo htmlspecialchars($admin['statut'], ENT_QUOTES, 'UTF-8'); ?>">
                    <header class="comptes-acces-card__head">
                        <div class="comptes-acces-card__head-badges" aria-label="Statut et rôle">
                            <span class="comptes-acces-card__status comptes-acces-card__status--<?php echo $admin['statut'] === 'actif' ? 'actif' : 'inactif'; ?>">
                                <?php echo $admin['statut'] === 'actif' ? 'Actif' : 'Inactif'; ?>
                            </span>
                            <span class="comptes-acces-card__role-pill role-badge role-<?php echo $esc_role; ?>">
                                <?php echo htmlspecialchars(admin_role_label($admin['role'] ?? 'utilisateur')); ?>
                            </span>
                        </div>
                        <div class="comptes-acces-card__identity">
                            <div class="comptes-acces-card__avatar" aria-hidden="true">
                                <?php echo strtoupper(substr($admin['prenom'], 0, 1)); ?>
                            </div>
                            <div class="comptes-acces-card__id-text">
                                <h3 class="comptes-acces-card__name"><?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></h3>
                                <p class="comptes-acces-card__email">
                                    <i class="fas fa-envelope" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($admin['email']); ?>
                                </p>
                            </div>
                        </div>
                    </header>

                    <div class="comptes-acces-card__body">
                        <div class="comptes-acces-card__meta" role="group" aria-label="Dates du compte">
                            <div class="comptes-acces-card__meta-tile">
                                <span class="comptes-acces-card__meta-ic" aria-hidden="true"><i class="fas fa-calendar-plus"></i></span>
                                <div class="comptes-acces-card__meta-txt">
                                    <span class="comptes-acces-card__meta-label">Compte créé</span>
                                    <span class="comptes-acces-card__meta-value"><?php echo date('d/m/Y', strtotime($admin['date_creation'])); ?></span>
                                    <span class="comptes-acces-card__meta-hint"><?php echo date('H\hi', strtotime($admin['date_creation'])); ?></span>
                                </div>
                            </div>
                            <div class="comptes-acces-card__meta-tile">
                                <span class="comptes-acces-card__meta-ic" aria-hidden="true"><i class="fas fa-clock-rotate-left"></i></span>
                                <div class="comptes-acces-card__meta-txt">
                                    <span class="comptes-acces-card__meta-label">Dernière connexion</span>
                                    <?php if (!empty($admin['derniere_connexion'])): ?>
                                        <span class="comptes-acces-card__meta-value"><?php echo date('d/m/Y', strtotime($admin['derniere_connexion'])); ?></span>
                                        <span class="comptes-acces-card__meta-hint"><?php echo date('H\hi', strtotime($admin['derniere_connexion'])); ?></span>
                                    <?php else: ?>
                                        <span class="comptes-acces-card__meta-value comptes-acces-card__meta-value--empty">Jamais</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="comptes-acces-card__toolbar">
                            <a class="comptes-acces-card__btn comptes-acces-card__btn--activity" href="employe-activite.php?admin_id=<?php echo (int) $admin['id']; ?>">
                                <i class="fas fa-chart-line" aria-hidden="true"></i> Activité
                            </a>
                            <?php if ($is_self): ?>
                                <span class="comptes-acces-card__self-chip" title="Vous ne pouvez pas modifier votre propre rôle ici">
                                    <i class="fas fa-user-check" aria-hidden="true"></i> Votre compte
                                </span>
                            <?php elseif ($admin['statut'] === 'actif'): ?>
                                <form method="post" action="" class="comptes-acces-card__statut-form">
                                    <input type="hidden" name="admin_id" value="<?php echo (int) $admin['id']; ?>">
                                    <input type="hidden" name="nouveau_statut" value="inactif">
                                    <button type="submit" name="toggle_statut" class="comptes-acces-card__btn comptes-acces-card__btn--deactivate"
                                            onclick="return confirm('Désactiver ce compte ?');">
                                        <i class="fas fa-user-slash" aria-hidden="true"></i> Désactiver
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="" class="comptes-acces-card__statut-form">
                                    <input type="hidden" name="admin_id" value="<?php echo (int) $admin['id']; ?>">
                                    <input type="hidden" name="nouveau_statut" value="actif">
                                    <button type="submit" name="toggle_statut" class="comptes-acces-card__btn comptes-acces-card__btn--activate"
                                            onclick="return confirm('Activer ce compte ?');">
                                        <i class="fas fa-user-check" aria-hidden="true"></i> Activer
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (!$is_self): ?>
                        <div class="comptes-acces-card__role-block">
                            <p class="comptes-acces-card__role-title" id="role-heading-<?php echo (int) $admin['id']; ?>">
                                <i class="fas fa-id-badge" aria-hidden="true"></i> Rôle d’accès
                            </p>
                            <form method="post" action="" class="comptes-acces-card__role-form" aria-labelledby="role-heading-<?php echo (int) $admin['id']; ?>">
                                <input type="hidden" name="admin_id" value="<?php echo (int) $admin['id']; ?>">
                                <div class="comptes-acces-card__role-row">
                                    <label for="role-<?php echo (int) $admin['id']; ?>" class="visually-hidden">Choisir le rôle pour <?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></label>
                                    <select name="nouveau_role" id="role-<?php echo (int) $admin['id']; ?>" class="comptes-acces-card__select comptes-role-select">
                                        <?php foreach (admin_roles_valides() as $r): ?>
                                        <option value="<?php echo htmlspecialchars($r); ?>" <?php echo (($admin['role'] ?? '') === $r) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(admin_role_label($r)); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="definir_role" class="comptes-acces-card__btn comptes-acces-card__btn--save"
                                            onclick="return confirm('Enregistrer ce rôle pour ce compte ?');">
                                        <i class="fas fa-floppy-disk" aria-hidden="true"></i> Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php else: ?>
                        <p class="comptes-acces-card__self-hint">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            Rôle actuel : <strong><?php echo htmlspecialchars(admin_role_label($admin['role'] ?? 'utilisateur')); ?></strong> — la modification de votre propre rôle s’effectue par un autre administrateur.
                        </p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </section>

    </div><!-- .page-comptes-wrap -->

    <?php include '../includes/footer.php'; ?>
</body>
</html>
