<?php
/**
 * Configuration des seuils d'alerte stock (niveaux standard / moyen / haut).
 * Réservé aux administrateurs complets.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_is_full_admin()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_stock_alertes.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message_alertes_stock'])) {
    $success_message = (string) $_SESSION['success_message_alertes_stock'];
    unset($_SESSION['success_message_alertes_stock']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!stock_alertes_tables_ok()) {
        $error_message = 'Table stock_alertes_regles absente — exécutez migrations/run_create_stock_alertes.php';
    } elseif (isset($_POST['supprimer_alerte'])) {
        $sid = isset($_POST['regle_id']) ? (int) $_POST['regle_id'] : 0;
        if ($sid > 0 && stock_alertes_supprimer_regle($sid)) {
            $_SESSION['success_message_alertes_stock'] = 'Seuil supprimé.';
            header('Location: alertes-stock.php');
            exit;
        }
        $error_message = 'Impossible de supprimer ce seuil.';
    } elseif (isset($_POST['enregistrer_alerte'])) {
        $niveau = isset($_POST['niveau']) ? (string) $_POST['niveau'] : '';
        $seuil = isset($_POST['seuil']) ? (int) $_POST['seuil'] : -1;
        $res = stock_alertes_enregistrer_regle($niveau, $seuil);
        if ($res['success']) {
            $_SESSION['success_message_alertes_stock'] = $res['message'];
            header('Location: alertes-stock.php');
            exit;
        }
        $error_message = $res['message'];
    }
}

$regles = stock_alertes_get_all_regles();
$tables_ok = stock_alertes_tables_ok();
$nb_regles = count($regles);

$form_niveau = '';
$form_seuil = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['enregistrer_alerte']) && !empty($error_message)) {
    $form_niveau = isset($_POST['niveau']) ? (string) $_POST['niveau'] : '';
    $form_seuil = isset($_POST['seuil']) ? (string) $_POST['seuil'] : '';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes de stock — Paramètres</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-alertes-stock-page.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-parametres-admin page-alertes-stock">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page as-wrap">
        <header class="as-hero" role="banner">
            <a class="as-hero__back" href="../parametres.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>
            <div class="as-hero__row">
                <div class="as-hero__icon" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
                <div class="as-hero__text">
                    <h1 class="as-hero__title">Alertes de stock</h1>
                    <p class="as-hero__lead">
                        Définissez des seuils en unités&nbsp;: dès que le stock d’un produit <strong>diminue et passe sous un seuil</strong>, une alerte mail part vers les comptes
                        <strong>administrateur</strong>, <strong>gestion des stocks</strong> et <strong>commercial</strong>, et un bandeau s’affiche sur le tableau de bord et les espaces dédiés.
                    </p>
                </div>
            </div>
        </header>

        <div class="as-levels" role="presentation">
            <div class="as-level-pill as-level-pill--std">
                <span class="as-level-pill__dot" aria-hidden="true"></span>
                <div>
                    <b>Standard</b>
                    Vigilance courante (ex. stock encore confortable mais à surveiller).
                </div>
            </div>
            <div class="as-level-pill as-level-pill--mid">
                <span class="as-level-pill__dot" aria-hidden="true"></span>
                <div>
                    <b>Moyen</b>
                    Priorité modérée — réapprovisionnement à planifier.
                </div>
            </div>
            <div class="as-level-pill as-level-pill--high">
                <span class="as-level-pill__dot" aria-hidden="true"></span>
                <div>
                    <b>Haut</b>
                    Critique — risque de rupture imminente.
                </div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success as-flash" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error as-flash" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$tables_ok): ?>
            <div class="message error as-flash"><i class="fas fa-database" aria-hidden="true"></i> Exécutez la migration&nbsp;: <code>php migrations/run_create_stock_alertes.php</code></div>
        <?php endif; ?>

        <div class="as-toolbar">
            <p class="as-toolbar__meta">
                <?php if ($nb_regles === 0): ?>
                    Aucun seuil actif — les alertes restent désactivées.
                <?php else: ?>
                    <strong><?php echo (int) $nb_regles; ?></strong> seuil<?php echo $nb_regles > 1 ? 'x' : ''; ?> configuré<?php echo $nb_regles > 1 ? 's' : ''; ?> (un par niveau maximum).
                <?php endif; ?>
            </p>
            <button type="button" class="as-btn-primary" onclick="openModalAlerteStock()" <?php echo !$tables_ok ? 'disabled' : ''; ?>>
                <i class="fas fa-plus-circle" aria-hidden="true"></i>
                Nouveau seuil
            </button>
        </div>

        <?php if (empty($regles)): ?>
            <div class="as-empty">
                <div class="as-empty__icon"><i class="fas fa-bell-slash" aria-hidden="true"></i></div>
                <h3>Aucune alerte configurée</h3>
                <p>Ajoutez au moins un seuil (standard, moyen ou haut) pour activer les e-mails automatiques et le bandeau d’alerte dans l’administration.</p>
            </div>
        <?php else: ?>
            <div class="as-card">
                <div class="as-card__head">
                    <h2><i class="fas fa-list-check" aria-hidden="true"></i> Seuils en vigueur</h2>
                </div>
                <div class="as-table-scroll">
                    <table class="as-table">
                        <thead>
                            <tr>
                                <th scope="col">Niveau</th>
                                <th scope="col">Déclenchement</th>
                                <th scope="col">Création</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regles as $r):
                                $n = (string) $r['niveau'];
                                $badge = $n === 'haut' ? 'as-badge--high' : ($n === 'moyen' ? 'as-badge--mid' : 'as-badge--std');
                                $ico = $n === 'haut' ? 'fa-circle-exclamation' : ($n === 'moyen' ? 'fa-triangle-exclamation' : 'fa-circle-info');
                                ?>
                                <tr>
                                    <td>
                                        <span class="as-badge <?php echo htmlspecialchars($badge); ?>">
                                            <i class="fas <?php echo htmlspecialchars($ico); ?>" aria-hidden="true"></i>
                                            <?php echo htmlspecialchars(stock_alertes_libelle_niveau($n)); ?>
                                        </span>
                                    </td>
                                    <td class="as-seuil-cell">
                                        <?php echo (int) $r['seuil']; ?>
                                        <span>unités max</span>
                                    </td>
                                    <td class="as-date-cell"><?php echo htmlspecialchars((string) ($r['date_creation'] ?? '—')); ?></td>
                                    <td>
                                        <form method="post" class="as-delete-form" onsubmit="return confirm('Supprimer ce seuil ?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                            <input type="hidden" name="supprimer_alerte" value="1">
                                            <input type="hidden" name="regle_id" value="<?php echo (int) $r['id']; ?>">
                                            <button type="submit" class="as-btn-delete" title="Supprimer ce seuil" aria-label="Supprimer">
                                                <i class="fas fa-trash-can" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="as-modal" id="modalAlerteStock" aria-hidden="true" role="presentation">
            <div class="as-modal__backdrop" onclick="closeModalAlerteStock()"></div>
            <div class="as-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="titreAlerteStock">
                <div class="as-modal__head">
                    <div class="as-modal__head-top">
                        <div>
                            <h2 id="titreAlerteStock" class="as-modal__title">
                                <i class="fas fa-sliders" aria-hidden="true"></i>
                                Configurer un seuil
                            </h2>
                            <p class="as-modal__subtitle">Choisissez le niveau d’alerte et la quantité en stock qui déclenche la notification (après une <strong>baisse</strong>).</p>
                        </div>
                        <button type="button" class="as-modal__close" onclick="closeModalAlerteStock()" aria-label="Fermer la fenêtre">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <form method="post" id="formAlerteStock">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <input type="hidden" name="enregistrer_alerte" value="1">
                    <div class="as-modal__body">
                        <div class="as-field">
                            <label for="niveau">
                                <i class="fas fa-layer-group" aria-hidden="true"></i>
                                Niveau d’alerte
                            </label>
                            <select id="niveau" name="niveau" required>
                                <option value="">Choisir un niveau…</option>
                                <option value="standard" <?php echo $form_niveau === 'standard' ? 'selected' : ''; ?>>Niveau standard</option>
                                <option value="moyen" <?php echo $form_niveau === 'moyen' ? 'selected' : ''; ?>>Niveau moyen</option>
                                <option value="haut" <?php echo $form_niveau === 'haut' ? 'selected' : ''; ?>>Niveau haut</option>
                            </select>
                            <span class="as-field__hint">Un seul enregistrement par niveau : une nouvelle saisie <strong>remplace</strong> le seuil existant pour ce niveau.</span>
                        </div>
                        <div class="as-field">
                            <label for="seuil">
                                <i class="fas fa-hashtag" aria-hidden="true"></i>
                                Seuil de déclenchement
                            </label>
                            <div class="as-input-wrap">
                                <input type="number" id="seuil" name="seuil" min="0" max="2147483646" step="1" required
                                    placeholder="Ex. 15"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    value="<?php echo htmlspecialchars($form_seuil, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="as-input-suffix" aria-hidden="true">unités</span>
                            </div>
                            <span class="as-field__hint">L’alerte part lorsque le stock devient <strong>inférieur ou égal</strong> à cette valeur après une diminution (vente, ajustement négatif, etc.).</span>
                        </div>
                    </div>
                    <div class="as-modal__footer">
                        <button type="button" class="as-modal__cancel" onclick="closeModalAlerteStock()">Annuler</button>
                        <button type="submit" class="as-modal__submit">
                            <i class="fas fa-check" aria-hidden="true"></i>
                            Enregistrer le seuil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function openModalAlerteStock() {
            var el = document.getElementById('modalAlerteStock');
            if (!el) return;
            el.classList.add('is-open');
            el.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            var inp = document.getElementById('seuil');
            var sel = document.getElementById('niveau');
            window.setTimeout(function () {
                if (sel && (!sel.value || sel.value === '')) sel.focus();
                else if (inp) inp.focus();
            }, 200);
        }
        function closeModalAlerteStock() {
            var el = document.getElementById('modalAlerteStock');
            if (!el) return;
            el.classList.remove('is-open');
            el.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            var form = document.getElementById('formAlerteStock');
            if (form) form.reset();
        }
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var el = document.getElementById('modalAlerteStock');
            if (el && el.classList.contains('is-open')) closeModalAlerteStock();
        });
        <?php if (!empty($error_message) && ($_POST['enregistrer_alerte'] ?? '') === '1'): ?>
        document.addEventListener('DOMContentLoaded', openModalAlerteStock);
        <?php endif; ?>
    </script>
</body>
</html>
