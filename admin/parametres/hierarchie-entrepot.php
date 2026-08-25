<?php
/**
 * Configuration de la hiérarchie entrepôt (niveaux libres).
 * Page dédiée — ajouter, renommer, réordonner, activer, supprimer.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

/* Le rayonniste batit et corrige la structure (24/08) - comme le
 * stock.entrepot_configurer que le meme role porte chez FPL natif. */
if (!admin_can_gestion_stock()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_entrepot_hierarchie.php';
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';

entrepot_hierarchie_libre_ensure_schema();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['admin_csrf'];
$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message_hierarchie_config'])) {
    $success_message = (string) $_SESSION['success_message_hierarchie_config'];
    unset($_SESSION['success_message_hierarchie_config']);
}
if (isset($_SESSION['error_message_hierarchie_config'])) {
    $error_message = (string) $_SESSION['error_message_hierarchie_config'];
    unset($_SESSION['error_message_hierarchie_config']);
}

function hc_redirect($ok = true, $message = '') {
    if ($message !== '') {
        if ($ok) {
            $_SESSION['success_message_hierarchie_config'] = $message;
        } else {
            $_SESSION['error_message_hierarchie_config'] = $message;
        }
    }
    header('Location: hierarchie-entrepot.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals($csrf, $token)) {
        $error_message = 'Session expirée ou jeton invalide. Rechargez la page.';
    } elseif (!entrepot_hierarchie_libre_schema_ok()) {
        $error_message = 'Migration requise — exécutez php migrations/run_migrate_entrepot_hierarchie_libre.php';
    } elseif (isset($_POST['hierarchie_def_ajouter'])) {
        $lie_raw = isset($_POST['etiquette_lie_cible']) ? (string) $_POST['etiquette_lie_cible'] : 'etage';
        $lie_type = 'etage';
        $lie_id = null;
        if (strpos($lie_raw, 'niveau:') === 0) {
            $lie_type = 'niveau';
            $lie_id = (int) substr($lie_raw, 7);
        }
        $res = entrepot_hierarchie_def_ajouter(
            isset($_POST['def_label']) ? (string) $_POST['def_label'] : '',
            isset($_POST['def_icon']) ? (string) $_POST['def_icon'] : 'fa-cube',
            isset($_POST['est_etiquette_qr']) && (string) $_POST['est_etiquette_qr'] === '1',
            $lie_type,
            $lie_id
        );
        hc_redirect($res['success'], $res['message']);
    } elseif (isset($_POST['hierarchie_def_modifier']) || isset($_POST['hierarchie_def_renommer'])) {
        $lie_raw = isset($_POST['etiquette_lie_cible']) ? (string) $_POST['etiquette_lie_cible'] : 'etage';
        $lie_type = 'etage';
        $lie_id = null;
        if (strpos($lie_raw, 'niveau:') === 0) {
            $lie_type = 'niveau';
            $lie_id = (int) substr($lie_raw, 7);
        }
        $res = entrepot_hierarchie_def_modifier(
            (int) ($_POST['def_id'] ?? 0),
            isset($_POST['def_label']) ? (string) $_POST['def_label'] : '',
            isset($_POST['def_icon']) ? (string) $_POST['def_icon'] : '',
            isset($_POST['est_etiquette_qr']) && (string) $_POST['est_etiquette_qr'] === '1',
            $lie_type,
            $lie_id
        );
        hc_redirect($res['success'], $res['message']);
    } elseif (isset($_POST['hierarchie_def_actif'])) {
        $res = entrepot_hierarchie_def_set_actif(
            (int) ($_POST['def_id'] ?? 0),
            isset($_POST['def_actif']) ? ((int) $_POST['def_actif'] === 1) : false
        );
        hc_redirect($res['success'], $res['message']);
    } elseif (isset($_POST['hierarchie_def_supprimer'])) {
        if (empty($_POST['confirm_suppression_def'])) {
            $error_message = 'Cochez la case de confirmation avant de supprimer.';
        } else {
            $res = entrepot_hierarchie_def_supprimer((int) ($_POST['def_id'] ?? 0));
            hc_redirect($res['success'], $res['message']);
        }
    } elseif (isset($_POST['hierarchie_def_reordonner'])) {
        $ids = isset($_POST['def_ordre']) && is_array($_POST['def_ordre']) ? $_POST['def_ordre'] : [];
        $res = entrepot_hierarchie_def_reordonner($ids);
        hc_redirect($res['success'], $res['message']);
    } elseif (isset($_POST['hierarchie_def_monter']) || isset($_POST['hierarchie_def_descendre'])) {
        $id = (int) ($_POST['def_id'] ?? 0);
        $all = entrepot_hierarchie_def_list(false);
        $ids = [];
        foreach ($all as $d) {
            $ids[] = (int) ($d['id'] ?? 0);
        }
        $pos = array_search($id, $ids, true);
        if ($pos === false) {
            $error_message = 'Niveau introuvable.';
        } else {
            $swap = isset($_POST['hierarchie_def_monter']) ? $pos - 1 : $pos + 1;
            if ($swap >= 0 && $swap < count($ids)) {
                $tmp = $ids[$pos];
                $ids[$pos] = $ids[$swap];
                $ids[$swap] = $tmp;
                $res = entrepot_hierarchie_def_reordonner($ids);
                hc_redirect($res['success'], $res['message']);
            } else {
                $error_message = 'Déplacement impossible.';
            }
        }
    }
}

$schema_ok = entrepot_hierarchie_libre_schema_ok();
if ($schema_ok) {
    entrepot_hierarchie_etiquette_ensure_schema();
}
$defs_all = $schema_ok ? entrepot_hierarchie_def_list(false) : [];
$defs_actifs = $schema_ok ? entrepot_hierarchie_def_list(true) : [];
$chemin = $schema_ok ? entrepot_hierarchie_chemin_libelle() : 'Niveau';
$def_etiquette = $schema_ok ? entrepot_hierarchie_def_etiquette() : null;
$impacts = [];
foreach ($defs_all as $def) {
    $did = (int) ($def['id'] ?? 0);
    if ($did > 0) {
        $impacts[$did] = entrepot_hierarchie_def_impact_suppression($did);
    }
}

$icon_suggestions = [
    'fa-map-marker-alt' => 'Zone / lieu',
    'fa-th-large' => 'Rayon / grille',
    'fa-bars-staggered' => 'Étagère',
    'fa-grip-lines' => 'Barre',
    'fa-crosshairs' => 'Position',
    'fa-cube' => 'Générique',
    'fa-box' => 'Colis',
    'fa-layer-group' => 'Couche',
    'fa-sitemap' => 'Structure',
    'fa-border-all' => 'Case',
];

/**
 * Options « lié à » pour étiquette (Niveau = étage + code abrégé).
 *
 * @param array<int, array<string, mixed>> $defs
 * @param int $exclude_id
 * @return array<string, string>
 */
function hc_options_lie_etiquette(array $defs, $exclude_id = 0) {
    $etage_label = 'Niveau';
    foreach ($defs as $d) {
        if (entrepot_hierarchie_def_est_etage($d)) {
            $etage_label = trim((string) ($d['label'] ?? 'Niveau')) ?: 'Niveau';
            break;
        }
    }
    $out = ['etage' => $etage_label . ' (code abrégé des étiquettes)'];
    foreach ($defs as $d) {
        $id = (int) ($d['id'] ?? 0);
        if ($id <= 0 || $id === (int) $exclude_id || entrepot_hierarchie_def_est_etage($d)) {
            continue;
        }
        $lab = trim((string) ($d['label'] ?? ''));
        if ($lab === '') {
            continue;
        }
        $out['niveau:' . $id] = $lab . ' (numéro uniquement)';
    }

    return $out;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hiérarchie entrepôt — Paramètres</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-parametres-page.css'); ?>
    <?php fpl_css_link('admin-hierarchie-config.css'); ?>
</head>
<body class="page-parametres-admin page-hierarchie-config">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <section class="produits-section parametres-page hc-wrap">
        <header class="hc-hero">
            <?php /* Retour vers la Structure de l'entrepôt — le point d'entrée
                     vivant de cette page (« Gérer les niveaux »). L'ancienne
                     cartographie d'emplacement n'est plus dans le menu. */ ?>
            <a class="hc-hero__back" href="../produits/structure-entrepot.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Structure de l’entrepôt</a>
            <div class="hc-hero__row">
                <div class="hc-hero__icon" aria-hidden="true"><i class="fas fa-sitemap"></i></div>
                <div class="hc-hero__text">
                    <p class="hc-hero__eyebrow">Paramètre structure</p>
                    <h1 class="hc-hero__title">Hiérarchie de l’entrepôt</h1>
                    <p class="hc-hero__lead">
                        Définissez l’ordre des niveaux utilisés partout&nbsp;: cartographie, formulaires produit et assignation stock.
                    </p>
                </div>
            </div>
        </header>

        <?php if ($success_message !== ''): ?>
        <div class="message success hc-flash" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error_message !== ''): ?>
        <div class="message error hc-flash" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!$schema_ok): ?>
        <div class="message error hc-flash">
            <i class="fas fa-database" aria-hidden="true"></i>
            Exécutez&nbsp;: <code>php migrations/run_migrate_entrepot_hierarchie_libre.php</code>
        </div>
        <?php else: ?>

        <div class="hc-toolbar">
            <p class="hc-toolbar__meta">
                <strong><?php echo count($defs_actifs); ?></strong> niveau(x) actif(s)
                <?php if ($defs_actifs !== []): ?>
                · feuille produit&nbsp;:
                <strong><?php echo htmlspecialchars((string) ($defs_actifs[count($defs_actifs) - 1]['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php endif; ?>
                <?php if (is_array($def_etiquette)): ?>
                · étiquette / QR&nbsp;:
                <strong><?php echo htmlspecialchars((string) ($def_etiquette['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php endif; ?>
            </p>
            <button type="button" class="hc-btn hc-btn--primary" id="hcOpenAddModal">
                <i class="fas fa-plus" aria-hidden="true"></i> Ajouter une hiérarchie
            </button>
        </div>

        <section class="hc-panel hc-panel--list hc-panel--full" aria-labelledby="hc-list-title">
            <div class="hc-panel__head">
                <h2 id="hc-list-title"><i class="fas fa-list-ol" aria-hidden="true"></i> Niveaux configurés</h2>
                <p>Réordonnez et modifiez chaque niveau. L’ordre définit la cascade des formulaires d’ajout partout dans l’application.</p>
            </div>

            <?php if ($defs_all === []): ?>
            <div class="hc-empty">
                <i class="fas fa-inbox" aria-hidden="true"></i>
                <p>Aucun niveau. Cliquez sur <strong>Ajouter une hiérarchie</strong> pour démarrer.</p>
            </div>
            <?php else: ?>
            <ul class="hc-niveau-list">
                <?php foreach ($defs_all as $index => $def):
                    $did = (int) ($def['id'] ?? 0);
                    $actif = (int) ($def['actif'] ?? 0) === 1;
                    $imp = $impacts[$did] ?? ['noeuds' => 0, 'produits' => 0, 'descendants' => 0];
                    $is_first = $index === 0;
                    $is_last = $index === count($defs_all) - 1;
                    $is_etage = entrepot_hierarchie_def_est_etage($def);
                    $is_feuille = false;
                    if (!$is_etage && $actif) {
                        $noeuds_actifs = array_values(array_filter($defs_actifs, function ($d) {
                            return !entrepot_hierarchie_def_est_etage($d);
                        }));
                        $is_feuille = $noeuds_actifs !== [] && (int) ($noeuds_actifs[count($noeuds_actifs) - 1]['id'] ?? 0) === $did;
                    }
                    $is_etiq = (int) ($def['est_etiquette_qr'] ?? 0) === 1;
                    $lie_type = (string) ($def['etiquette_lie_type'] ?? 'etage');
                    $lie_nid = (int) ($def['etiquette_lie_niveau_id'] ?? 0);
                    $lie_label = 'Niveau';
                    foreach ($defs_all as $dx) {
                        if (entrepot_hierarchie_def_est_etage($dx)) {
                            $lie_label = (string) ($dx['label'] ?? 'Niveau');
                            break;
                        }
                    }
                    if ($is_etiq && $lie_type === 'niveau' && $lie_nid > 0) {
                        foreach ($defs_all as $dx) {
                            if ((int) ($dx['id'] ?? 0) === $lie_nid) {
                                $lie_label = (string) ($dx['label'] ?? 'Niveau lié');
                                break;
                            }
                        }
                    }
                    $lie_cible_val = ($lie_type === 'niveau' && $lie_nid > 0) ? ('niveau:' . $lie_nid) : 'etage';
                ?>
                <li class="hc-niveau-card<?php echo $actif ? '' : ' is-inactive'; ?><?php echo $is_feuille ? ' is-leaf' : ''; ?><?php echo $is_etiq ? ' is-etiquette' : ''; ?><?php echo $is_etage ? ' is-systeme' : ''; ?>"
                    data-def-id="<?php echo $did; ?>"
                    data-def-label="<?php echo htmlspecialchars((string) ($def['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-def-icon="<?php echo htmlspecialchars((string) ($def['icon'] ?? 'fa-cube'), ENT_QUOTES, 'UTF-8'); ?>"
                    data-def-etiq="<?php echo $is_etiq ? '1' : '0'; ?>"
                    data-def-lie="<?php echo htmlspecialchars($lie_cible_val, ENT_QUOTES, 'UTF-8'); ?>"
                    data-def-etage="<?php echo $is_etage ? '1' : '0'; ?>">
                    <div class="hc-niveau-card__order">
                        <form method="post" class="hc-inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="def_id" value="<?php echo $did; ?>">
                            <button type="submit" name="hierarchie_def_monter" value="1" class="hc-order-btn" <?php echo $is_first ? 'disabled' : ''; ?> title="Monter" aria-label="Monter">
                                <i class="fas fa-chevron-up" aria-hidden="true"></i>
                            </button>
                        </form>
                        <span class="hc-niveau-card__rank"><?php echo $index + 1; ?></span>
                        <form method="post" class="hc-inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="def_id" value="<?php echo $did; ?>">
                            <button type="submit" name="hierarchie_def_descendre" value="1" class="hc-order-btn" <?php echo $is_last ? 'disabled' : ''; ?> title="Descendre" aria-label="Descendre">
                                <i class="fas fa-chevron-down" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>

                    <div class="hc-niveau-card__icon" aria-hidden="true">
                        <i class="fas <?php echo htmlspecialchars((string) ($def['icon'] ?? 'fa-cube'), ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </div>

                    <div class="hc-niveau-card__body">
                        <div class="hc-niveau-card__head">
                            <div class="hc-niveau-card__title-row">
                                <h3><?php echo htmlspecialchars((string) ($def['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <?php if ($is_etage): ?>
                                <span class="hc-badge hc-badge--systeme" title="Étages avec code abrégé pour étiquettes">
                                    <i class="fas fa-building" aria-hidden="true"></i> Étages
                                </span>
                                <?php endif; ?>
                                <?php if ($is_etiq): ?>
                                <span class="hc-badge hc-badge--etiq" title="Porte les étiquettes et QR — lié à <?php echo htmlspecialchars($lie_label, ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-qrcode" aria-hidden="true"></i> Étiquette / QR
                                </span>
                                <?php endif; ?>
                                <?php if ($is_feuille): ?>
                                <span class="hc-badge hc-badge--leaf">Feuille produit</span>
                                <?php endif; ?>
                                <?php if (!$actif): ?>
                                <span class="hc-badge hc-badge--off">Inactif</span>
                                <?php else: ?>
                                <span class="hc-badge hc-badge--on">Actif</span>
                                <?php endif; ?>
                            </div>
                            <div class="hc-niveau-card__actions hc-niveau-card__actions--inline">
                                <button type="button" class="hc-btn hc-btn--ghost hc-btn--edit" data-hc-edit="<?php echo $did; ?>">
                                    <i class="fas fa-pen" aria-hidden="true"></i> Modifier
                                </button>
                                <?php if (!$is_etage): ?>
                                <form method="post" class="hc-inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="hierarchie_def_actif" value="1">
                                    <input type="hidden" name="def_id" value="<?php echo $did; ?>">
                                    <input type="hidden" name="def_actif" value="<?php echo $actif ? '0' : '1'; ?>">
                                    <button type="submit" class="hc-btn hc-btn--ghost" title="<?php echo $actif ? 'Désactiver' : 'Activer'; ?>">
                                        <i class="fas <?php echo $actif ? 'fa-eye-slash' : 'fa-eye'; ?>" aria-hidden="true"></i>
                                        <?php echo $actif ? 'Désactiver' : 'Activer'; ?>
                                    </button>
                                </form>
                                <details class="hc-details hc-details--danger">
                                    <summary class="hc-btn hc-btn--danger-ghost"><i class="fas fa-trash-can" aria-hidden="true"></i> Supprimer</summary>
                                    <form method="post" class="hc-delete-form" onsubmit="return confirm('Supprimer ce niveau et tous ses éléments ?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="hierarchie_def_supprimer" value="1">
                                        <input type="hidden" name="def_id" value="<?php echo $did; ?>">
                                        <p class="hc-delete-warn">
                                            <?php echo (int) ($imp['noeuds'] ?? 0); ?> élément(s) et
                                            <?php echo (int) ($imp['produits'] ?? 0); ?> produit(s) seront détachés.
                                        </p>
                                        <label class="hc-check">
                                            <input type="checkbox" name="confirm_suppression_def" value="1" required>
                                            <span>Je confirme la suppression définitive</span>
                                        </label>
                                        <button type="submit" class="hc-btn hc-btn--danger hc-btn--sm">Confirmer</button>
                                    </form>
                                </details>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($is_etage): ?>
                        <p class="hc-systeme-note">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            Niveau système — non supprimable. L’ordre influe sur les formulaires d’ajout.
                        </p>
                        <?php endif; ?>
                        <div class="hc-niveau-card__meta">
                            <?php if ($is_etage): ?>
                            <span class="hc-niveau-card__chip"><i class="fas fa-building" aria-hidden="true"></i> <?php echo (int) ($imp['noeuds'] ?? 0); ?> étage(s)</span>
                            <span class="hc-niveau-card__chip"><i class="fas fa-barcode" aria-hidden="true"></i> Formulaire&nbsp;: n°, nom, code abrégé</span>
                            <?php else: ?>
                            <span class="hc-niveau-card__chip"><i class="fas fa-cubes" aria-hidden="true"></i> <?php echo (int) ($imp['noeuds'] ?? 0); ?> élément(s)</span>
                            <span class="hc-niveau-card__chip"><i class="fas fa-box" aria-hidden="true"></i> <?php echo (int) ($imp['produits'] ?? 0); ?> produit(s)</span>
                            <?php endif; ?>
                            <?php if ($is_etiq): ?>
                            <span class="hc-niveau-card__chip"><i class="fas fa-link" aria-hidden="true"></i> Lié&nbsp;: <?php echo htmlspecialchars($lie_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <!-- Modal ajouter -->
        <div class="hc-modal" id="hcModalAdd" aria-hidden="true">
            <div class="hc-modal__backdrop" data-hc-close></div>
            <div class="hc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="hc-add-title">
                <div class="hc-modal__head">
                    <h2 id="hc-add-title"><i class="fas fa-plus-circle" aria-hidden="true"></i> Ajouter une hiérarchie</h2>
                    <button type="button" class="hc-modal__close" data-hc-close aria-label="Fermer"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="hc-modal__body">
                    <p class="hc-modal__hint">Le nouveau niveau est placé <strong>en fin de chaîne</strong>. Réordonnez-le ensuite dans la liste si besoin.</p>
                    <form method="post" class="hc-add-form" id="hcAddForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="hierarchie_def_ajouter" value="1">
                        <div class="hc-field">
                            <label for="def_label_new">Nom du niveau *</label>
                            <input type="text" id="def_label_new" name="def_label" maxlength="100" required placeholder="Ex. Secteur, Allée, Casier…">
                        </div>
                        <div class="hc-field">
                            <label for="def_icon_new">Icône</label>
                            <input type="text" id="def_icon_new" name="def_icon" maxlength="40" value="fa-cube" list="hc-icon-list">
                            <datalist id="hc-icon-list">
                                <?php foreach ($icon_suggestions as $ico => $lab): ?>
                                <option value="<?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="hc-icon-picks" role="list">
                            <?php foreach (array_slice(array_keys($icon_suggestions), 0, 6) as $ico): ?>
                            <button type="button" class="hc-icon-pick" data-icon="<?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($icon_suggestions[$ico], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas <?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="hc-field">
                            <label for="est_etiquette_qr_new">Configurer étiquette / QR *</label>
                            <select id="est_etiquette_qr_new" name="est_etiquette_qr" data-hc-etiq-toggle="add">
                                <option value="0" selected>Non</option>
                                <option value="1">Oui — ce niveau porte l’étiquette et le QR</option>
                            </select>
                            <span class="hc-field__hint">Le code abrégé vient du <strong>Niveau</strong> (étage), pas de ce formulaire.</span>
                        </div>
                        <div class="hc-field hc-field--lie" id="hcLieWrapAdd" hidden>
                            <label for="etiquette_lie_cible_new">Hiérarchie liée *</label>
                            <select id="etiquette_lie_cible_new" name="etiquette_lie_cible">
                                <?php foreach (hc_options_lie_etiquette($defs_all, 0) as $val => $lab): ?>
                                <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $val === 'etage' ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hc-field__hint">Par défaut&nbsp;: <strong>Niveau</strong> (code abrégé). Les autres niveaux n’affichent que leur numéro sur l’étiquette.</span>
                        </div>
                        <div class="hc-modal__actions">
                            <button type="button" class="hc-btn hc-btn--ghost" data-hc-close>Annuler</button>
                            <button type="submit" class="hc-btn hc-btn--primary">
                                <i class="fas fa-plus" aria-hidden="true"></i> Créer le niveau
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal modifier -->
        <div class="hc-modal" id="hcModalEdit" aria-hidden="true">
            <div class="hc-modal__backdrop" data-hc-close-edit></div>
            <div class="hc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="hc-edit-title">
                <div class="hc-modal__head">
                    <h2 id="hc-edit-title"><i class="fas fa-pen" aria-hidden="true"></i> Modifier la hiérarchie</h2>
                    <button type="button" class="hc-modal__close" data-hc-close-edit aria-label="Fermer"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="hc-modal__body">
                    <form method="post" class="hc-edit-form" id="hcEditForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="hierarchie_def_modifier" value="1">
                        <input type="hidden" name="def_id" id="edit_def_id" value="">
                        <div class="hc-field">
                            <label for="edit_def_label">Nom du niveau *</label>
                            <input type="text" id="edit_def_label" name="def_label" maxlength="100" required>
                        </div>
                        <div class="hc-field">
                            <label for="edit_def_icon">Icône</label>
                            <input type="text" id="edit_def_icon" name="def_icon" maxlength="40" list="hc-icon-list-edit">
                            <datalist id="hc-icon-list-edit">
                                <?php foreach ($icon_suggestions as $ico => $lab): ?>
                                <option value="<?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="hc-icon-picks" role="list" id="hcEditIconPicks">
                            <?php foreach (array_slice(array_keys($icon_suggestions), 0, 6) as $ico): ?>
                            <button type="button" class="hc-icon-pick" data-icon="<?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($icon_suggestions[$ico], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas <?php echo htmlspecialchars($ico, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="hc-field">
                            <label for="est_etiquette_qr_edit">Configurer étiquette / QR *</label>
                            <select id="est_etiquette_qr_edit" name="est_etiquette_qr" data-hc-etiq-toggle="edit">
                                <option value="0">Non</option>
                                <option value="1">Oui — ce niveau porte l’étiquette et le QR</option>
                            </select>
                            <span class="hc-field__hint">Un seul niveau peut être étiquette / QR. Le code abrégé reste celui du <strong>Niveau</strong> (étage).</span>
                        </div>
                        <div class="hc-field hc-field--lie" id="hcLieWrapEdit" hidden>
                            <label for="etiquette_lie_cible_edit">Hiérarchie liée *</label>
                            <select id="etiquette_lie_cible_edit" name="etiquette_lie_cible">
                                <?php foreach (hc_options_lie_etiquette($defs_all, 0) as $val => $lab):
                                    $ex = (strpos($val, 'niveau:') === 0) ? (int) substr($val, 7) : 0;
                                ?>
                                <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ex > 0 ? ' data-exclude-self="' . $ex . '"' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hc-field__hint">Par défaut&nbsp;: Niveau (étages). Les autres hiérarchies n’apparaissent sur l’étiquette que par leur numéro.</span>
                        </div>
                        <div class="hc-modal__actions">
                            <button type="button" class="hc-btn hc-btn--ghost" data-hc-close-edit>Annuler</button>
                            <button type="submit" class="hc-btn hc-btn--primary">
                                <i class="fas fa-check" aria-hidden="true"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        var modalAdd = document.getElementById('hcModalAdd');
        var modalEdit = document.getElementById('hcModalEdit');
        var openBtn = document.getElementById('hcOpenAddModal');
        var inputAdd = document.getElementById('def_icon_new');
        var labelInput = document.getElementById('def_label_new');
        var etiqAdd = document.getElementById('est_etiquette_qr_new');
        var lieAdd = document.getElementById('hcLieWrapAdd');
        var etiqEdit = document.getElementById('est_etiquette_qr_edit');
        var lieEdit = document.getElementById('hcLieWrapEdit');
        var lieSelectEdit = document.getElementById('etiquette_lie_cible_edit');
        var editId = document.getElementById('edit_def_id');
        var editLabel = document.getElementById('edit_def_label');
        var editIcon = document.getElementById('edit_def_icon');

        function setOpen(modal, open) {
            if (!modal) return;
            modal.classList.toggle('is-open', open);
            modal.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.body.style.overflow = open ? 'hidden' : '';
        }

        function syncLieVisibility() {
            if (lieAdd && etiqAdd) {
                lieAdd.hidden = etiqAdd.value !== '1';
            }
            if (lieEdit && etiqEdit) {
                lieEdit.hidden = etiqEdit.value !== '1';
            }
        }

        function openAdd() {
            setOpen(modalAdd, true);
            syncLieVisibility();
            if (labelInput) setTimeout(function () { labelInput.focus(); }, 50);
        }

        function closeAdd() { setOpen(modalAdd, false); }

        function openEditFromCard(card) {
            if (!card || !modalEdit) return;
            var id = card.getAttribute('data-def-id') || '';
            var label = card.getAttribute('data-def-label') || '';
            var icon = card.getAttribute('data-def-icon') || 'fa-cube';
            var etiq = card.getAttribute('data-def-etiq') || '0';
            var lie = card.getAttribute('data-def-lie') || 'etage';
            if (editId) editId.value = id;
            if (editLabel) editLabel.value = label;
            if (editIcon) editIcon.value = icon;
            if (etiqEdit) etiqEdit.value = etiq === '1' ? '1' : '0';
            if (lieSelectEdit) {
                Array.prototype.forEach.call(lieSelectEdit.options, function (opt) {
                    var ex = opt.getAttribute('data-exclude-self');
                    opt.hidden = ex && String(ex) === String(id);
                    opt.disabled = !!opt.hidden;
                });
                var found = false;
                Array.prototype.forEach.call(lieSelectEdit.options, function (opt) {
                    if (!opt.disabled && opt.value === lie) {
                        lieSelectEdit.value = lie;
                        found = true;
                    }
                });
                if (!found) lieSelectEdit.value = 'etage';
            }
            syncLieVisibility();
            setOpen(modalEdit, true);
            if (editLabel) setTimeout(function () { editLabel.focus(); }, 50);
        }

        function closeEdit() { setOpen(modalEdit, false); }

        if (openBtn) openBtn.addEventListener('click', openAdd);
        document.querySelectorAll('[data-hc-close]').forEach(function (el) {
            el.addEventListener('click', closeAdd);
        });
        document.querySelectorAll('[data-hc-close-edit]').forEach(function (el) {
            el.addEventListener('click', closeEdit);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAdd();
                closeEdit();
            }
        });

        if (etiqAdd) etiqAdd.addEventListener('change', syncLieVisibility);
        if (etiqEdit) etiqEdit.addEventListener('change', syncLieVisibility);

        document.querySelectorAll('[data-hc-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.hc-niveau-card');
                openEditFromCard(card);
            });
        });

        document.querySelectorAll('#hcAddForm .hc-icon-pick').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (inputAdd) {
                    inputAdd.value = btn.getAttribute('data-icon') || 'fa-cube';
                    inputAdd.focus();
                }
                document.querySelectorAll('#hcAddForm .hc-icon-pick').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
            });
        });
        document.querySelectorAll('#hcEditIconPicks .hc-icon-pick').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (editIcon) {
                    editIcon.value = btn.getAttribute('data-icon') || 'fa-cube';
                    editIcon.focus();
                }
                document.querySelectorAll('#hcEditIconPicks .hc-icon-pick').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
            });
        });

        syncLieVisibility();

        <?php if ($error_message !== '' && isset($_POST['hierarchie_def_ajouter'])): ?>
        openAdd();
        <?php endif; ?>
        <?php if ($error_message !== '' && (isset($_POST['hierarchie_def_modifier']) || isset($_POST['hierarchie_def_renommer']))): ?>
        (function () {
            var id = <?php echo (int) ($_POST['def_id'] ?? 0); ?>;
            var card = document.querySelector('.hc-niveau-card[data-def-id="' + id + '"]');
            if (card) openEditFromCard(card);
        })();
        <?php endif; ?>
    })();
    </script>
</body>
</html>
