<?php
/**
 * Configuration structure entrepôt (emplacement produits par étage).
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

require_once __DIR__ . '/../../models/model_entrepot_emplacement.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message_emplacement_entrepot'])) {
    $success_message = (string) $_SESSION['success_message_emplacement_entrepot'];
    unset($_SESSION['success_message_emplacement_entrepot']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!entrepot_emplacement_tables_ok()) {
        $error_message = 'Tables absentes — exécutez migrations/run_create_entrepot_emplacement_config.php';
    } elseif (isset($_POST['enregistrer_structure'])) {
        $nb_etages = isset($_POST['nb_etages']) ? (int) $_POST['nb_etages'] : 0;
        $raw_etages = isset($_POST['etages']) && is_array($_POST['etages']) ? $_POST['etages'] : [];
        $etages_data = [];
        foreach ($raw_etages as $num => $row) {
            if (!is_array($row)) {
                continue;
            }
            $etages_data[(int) $num] = [
                'nb_rayons' => isset($row['nb_rayons']) ? (int) $row['nb_rayons'] : 0,
                'nb_allees' => isset($row['nb_allees']) ? (int) $row['nb_allees'] : 0,
                'nb_zones' => isset($row['nb_zones']) ? (int) $row['nb_zones'] : 0,
                'nb_positions' => isset($row['nb_positions']) ? (int) $row['nb_positions'] : 0,
                'nb_barres' => isset($row['nb_barres']) ? (int) $row['nb_barres'] : 0,
            ];
        }
        $res = entrepot_emplacement_enregistrer_config($nb_etages, $etages_data);
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            header('Location: emplacement-entrepot.php');
            exit;
        }
        $error_message = $res['message'];
    } elseif (isset($_POST['supprimer_etage'])) {
        $num_sup = isset($_POST['numero_etage']) ? (int) $_POST['numero_etage'] : 0;
        $res = entrepot_emplacement_supprimer_etage($num_sup);
        if ($res['success']) {
            $_SESSION['success_message_emplacement_entrepot'] = $res['message'];
            header('Location: emplacement-entrepot.php');
            exit;
        }
        $error_message = $res['message'];
    }
}

$data = entrepot_emplacement_get_config();
$config = $data['config'];
$etages = $data['etages'];
$tables_ok = entrepot_emplacement_tables_ok();
$nb_etages = (int) ($config['nb_etages'] ?? 0);

$initial_etages = [];
foreach ($etages as $row) {
    $n = (int) ($row['numero_etage'] ?? 0);
    if ($n <= 0) {
        continue;
    }
    $initial_etages[(string) $n] = [
        'nb_rayons' => (int) ($row['nb_rayons'] ?? 100),
        'nb_allees' => (int) ($row['nb_allees'] ?? 10),
        'nb_zones' => (int) ($row['nb_zones'] ?? 10),
        'nb_positions' => (int) ($row['nb_positions'] ?? 10),
        'nb_barres' => (int) ($row['nb_barres'] ?? 10),
    ];
}

$initial_json = json_encode([
    'nb_etages' => $nb_etages,
    'etages' => $initial_etages,
], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emplacement entrepôt — Paramètres</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-emplacement-entrepot.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-parametres-admin page-emplacement-entrepot">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page ee-wrap">
        <header class="ee-hero" role="banner">
            <a class="ee-hero__back" href="../parametres.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>
            <div class="ee-hero__row">
                <div class="ee-hero__icon" aria-hidden="true"><i class="fas fa-warehouse"></i></div>
                <div class="ee-hero__text">
                    <h1 class="ee-hero__title">Emplacement entrepôt</h1>
                    <p class="ee-hero__lead">
                        Définissez la <strong>carte de l’entrepôt</strong> par étage (rayons, allées, zones, positions, barres).
                        Ces limites s’appliquent ensuite aux formulaires produit lors de l’assignation d’une position.
                    </p>
                </div>
            </div>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="message success ee-flash" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error ee-flash" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$tables_ok): ?>
            <div class="message error ee-flash">
                <i class="fas fa-database" aria-hidden="true"></i>
                Exécutez la migration&nbsp;: <code>php migrations/run_create_entrepot_emplacement_config.php</code>
            </div>
        <?php endif; ?>

        <div class="ee-toolbar">
            <p class="ee-toolbar__meta">
                <?php if ($nb_etages <= 0 || empty($etages)): ?>
                    Aucune structure configurée — les champs emplacement produit restent masqués.
                <?php else: ?>
                    <strong><?php echo (int) $nb_etages; ?></strong> étage(s) ·
                    <strong><?php echo count($etages); ?></strong> fiche(s) détaillée(s)
                <?php endif; ?>
            </p>
            <button type="button" class="ee-btn-primary" onclick="openModalEntrepotEmplacement()" <?php echo !$tables_ok ? 'disabled' : ''; ?>>
                <i class="fas fa-sliders" aria-hidden="true"></i>
                Configurer la structure
            </button>
        </div>

        <?php if (empty($etages)): ?>
            <div class="ee-empty">
                <div class="ee-empty__icon"><i class="fas fa-map" aria-hidden="true"></i></div>
                <h3>Structure non définie</h3>
                <p>Configurez au moins un étage avec ses limites pour activer l’emplacement sur les fiches produit.</p>
            </div>
        <?php else: ?>
            <div class="ee-card">
                <div class="ee-card__head">
                    <h2><i class="fas fa-layer-group" aria-hidden="true"></i> Étages configurés</h2>
                </div>
                <div class="ee-table-scroll">
                    <table class="ee-table">
                        <thead>
                            <tr>
                                <th scope="col">Étage</th>
                                <th scope="col">Rayons</th>
                                <th scope="col">Allées</th>
                                <th scope="col">Zones</th>
                                <th scope="col">Positions</th>
                                <th scope="col">Barres</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($etages as $row): ?>
                            <?php $num = (int) ($row['numero_etage'] ?? 0); ?>
                            <tr>
                                <td><strong>Étage <?php echo $num; ?></strong></td>
                                <td><?php echo (int) ($row['nb_rayons'] ?? 0); ?></td>
                                <td><?php echo (int) ($row['nb_allees'] ?? 0); ?></td>
                                <td><?php echo (int) ($row['nb_zones'] ?? 0); ?></td>
                                <td><?php echo (int) ($row['nb_positions'] ?? 0); ?></td>
                                <td><?php echo (int) ($row['nb_barres'] ?? 0); ?></td>
                                <td>
                                    <div class="ee-actions-cell">
                                        <a href="emplacement-entrepot-etage.php?etage=<?php echo $num; ?>" class="ee-btn-link">
                                            <i class="fas fa-pen-to-square" aria-hidden="true"></i> Modifier
                                        </a>
                                        <form method="post" class="ee-delete-form" onsubmit="return confirm('Supprimer l’étage <?php echo $num; ?> ? Les noms, barres, positions et emplacements produits associés seront retirés.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                            <input type="hidden" name="supprimer_etage" value="1">
                                            <input type="hidden" name="numero_etage" value="<?php echo $num; ?>">
                                            <button type="submit" class="ee-btn-delete" title="Supprimer cet étage" aria-label="Supprimer l’étage <?php echo $num; ?>">
                                                <i class="fas fa-trash-can" aria-hidden="true"></i> Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="ee-modal" id="modalEntrepotEmplacement" aria-hidden="true" role="presentation">
            <div class="ee-modal__backdrop" onclick="closeModalEntrepotEmplacement()"></div>
            <div class="ee-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="titreEntrepotEmplacement">
                <div class="ee-modal__head">
                    <div class="ee-modal__head-top">
                        <div>
                            <h2 id="titreEntrepotEmplacement" class="ee-modal__title">
                                <i class="fas fa-warehouse" aria-hidden="true"></i>
                                Configurer la structure
                            </h2>
                            <p class="ee-modal__subtitle">Indiquez le nombre d’étages, puis configurez chaque étage (rayons, allées, zones, positions, barres).</p>
                        </div>
                        <button type="button" class="ee-modal__close" onclick="closeModalEntrepotEmplacement()" aria-label="Fermer">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <form method="post" id="formEntrepotEmplacement">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <input type="hidden" name="enregistrer_structure" value="1">
                    <div id="ee-hidden-inputs"></div>
                    <script type="application/json" id="ee-initial-data"><?php echo htmlspecialchars($initial_json, ENT_QUOTES, 'UTF-8'); ?></script>
                    <div class="ee-modal__body">
                        <div class="ee-field">
                            <label for="ee_nb_etages"><i class="fas fa-building" aria-hidden="true"></i> Nombre d’étages</label>
                            <input type="number" id="ee_nb_etages" min="1" max="<?php echo (int) ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX; ?>" step="1"
                                value="<?php echo $nb_etages > 0 ? (int) $nb_etages : 3; ?>" required>
                            <span class="ee-field__hint">De 1 à <?php echo (int) ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX; ?> étages.</span>
                        </div>
                        <div class="ee-field" id="ee-etage-select-wrap" hidden>
                            <label for="ee_etage_courant"><i class="fas fa-layer-group" aria-hidden="true"></i> Étage à configurer</label>
                            <select id="ee_etage_courant"></select>
                            <span class="ee-field__hint">Choisissez un étage pour afficher ses limites.</span>
                        </div>
                        <div id="ee-fields-wrap" class="ee-fields-grid" hidden>
                            <div class="ee-field">
                                <label for="ee_nb_rayons"><i class="fas fa-th-large" aria-hidden="true"></i> Nombre de rayons</label>
                                <input type="number" id="ee_nb_rayons" min="1" max="<?php echo (int) ENTREPOT_EMPLACEMENT_NB_RAYONS_MAX; ?>" step="1" required>
                            </div>
                            <div class="ee-field">
                                <label for="ee_nb_allees"><i class="fas fa-road" aria-hidden="true"></i> Nombre d’allées</label>
                                <input type="number" id="ee_nb_allees" min="1" max="<?php echo (int) ENTREPOT_EMPLACEMENT_NB_PETIT_MAX; ?>" step="1" required>
                            </div>
                            <div class="ee-field">
                                <label for="ee_nb_zones"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Nombre de zones</label>
                                <input type="number" id="ee_nb_zones" min="1" max="<?php echo (int) ENTREPOT_EMPLACEMENT_NB_PETIT_MAX; ?>" step="1" required>
                            </div>
                            <div class="ee-field">
                                <label for="ee_nb_positions"><i class="fas fa-crosshairs" aria-hidden="true"></i> Nombre de positions</label>
                                <input type="number" id="ee_nb_positions" min="1" max="<?php echo (int) ENTREPOT_EMPLACEMENT_NB_PETIT_MAX; ?>" step="1" required>
                            </div>
                            <div class="ee-field">
                                <label for="ee_nb_barres"><i class="fas fa-grip-lines" aria-hidden="true"></i> Nombre de barres</label>
                                <input type="number" id="ee_nb_barres" min="1" max="<?php echo (int) ENTREPOT_EMPLACEMENT_NB_PETIT_MAX; ?>" step="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="ee-modal__footer">
                        <button type="button" class="ee-modal__cancel" onclick="closeModalEntrepotEmplacement()">Annuler</button>
                        <button type="submit" class="ee-modal__submit">
                            <i class="fas fa-check" aria-hidden="true"></i>
                            Enregistrer la structure
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="/js/admin-emplacement-entrepot.js<?php echo asset_version_query(); ?>"></script>
    <?php if (!empty($error_message) && ($_POST['enregistrer_structure'] ?? '') === '1'): ?>
    <script>document.addEventListener('DOMContentLoaded', openModalEntrepotEmplacement);</script>
    <?php endif; ?>
</body>
</html>
