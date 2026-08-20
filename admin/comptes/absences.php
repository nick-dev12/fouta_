<?php
/**
 * Gestion des absences employés — saisie et justificatifs (admin / RH)
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
if (!in_array($role, ['admin', 'rh', 'informaticien', 'developpeur'], true)) {
    $_SESSION['error_message'] = 'Accès réservé aux administrateurs, aux RH, aux informaticiens ou aux développeurs.';
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../models/model_employes.php';
require_once __DIR__ . '/../../models/model_employe_absences.php';
require_once __DIR__ . '/../../models/model_bulletin_paie.php';
require_once __DIR__ . '/../../includes/fouta_upload_limits.php';

/**
 * Peut recevoir une absence : compte admin actif, pas le rôle « admin ».
 */
function absences_staff_est_eligible(array $admin_row) {
    if (($admin_row['statut'] ?? '') !== 'actif') {
        return false;
    }
    $r = $admin_row['role'] ?? '';
    return $r !== 'admin';
}

function absences_fiche_employe_eligible($emp) {
    return $emp && ($emp['statut'] ?? '') === 'actif';
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf'];

$upload_subdir = 'employe_absences';
$upload_abs = realpath(__DIR__ . '/../../upload');
if ($upload_abs === false) {
    $upload_abs = __DIR__ . '/../../upload';
}
$upload_dir = $upload_abs . DIRECTORY_SEPARATOR . $upload_subdir;
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

/**
 * @return array{0:?string,1:?string,2:?string}|string Erreur message string
 */
function absences_traiter_upload_justif(array $file, $max_bytes = null) {
    if ($max_bytes === null) {
        $max_bytes = FOUTA_UPLOAD_IMAGE_MAX_BYTES;
    }
    $max_bytes = (int) $max_bytes;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null, null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return 'Erreur lors de l’envoi du fichier.';
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return 'Fichier invalide.';
    }
    if (($file['size'] ?? 0) > $max_bytes) {
        $mo = (int) ($max_bytes / (1024 * 1024));
        return 'Le fichier dépasse la taille maximale autorisée (' . $mo . ' Mo).';
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return 'Format non autorisé. Utilisez JPEG, PNG ou WebP.';
    }
    $ext = $allowed[$mime];
    $basename = 'justif_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    return [$basename, $file['name'] ?? $basename, $mime];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $_SESSION['error_message'] = 'Session expirée. Réessayez.';
        header('Location: absences.php');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_absence') {
        $raw_cible = isset($_POST['absence_cible']) ? trim((string) $_POST['absence_cible']) : '';
        $date_a = isset($_POST['date_absence']) ? trim((string) $_POST['date_absence']) : '';
        $motif = isset($_POST['motif']) ? trim((string) $_POST['motif']) : '';
        $penalite = bp_parse_montant_post($_POST['penalite_montant'] ?? null);
        $nid = false;

        if ($date_a === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_a)) {
            $_SESSION['error_message'] = 'Date d’absence invalide.';
        } elseif ($motif === '') {
            $_SESSION['error_message'] = 'Le motif est obligatoire.';
        } elseif (preg_match('/^adm-(\d+)$/', $raw_cible, $m)) {
            $admin_cible_id = (int) $m[1];
            $st = get_admin_by_id($admin_cible_id);
            if (!$st || !absences_staff_est_eligible($st)) {
                $_SESSION['error_message'] = 'Compte invalide ou non éligible (les comptes administrateur ne sont pas proposés).';
            } else {
                $nid = employe_absence_creer_pour_staff_admin($admin_cible_id, $date_a, $motif, (int) $_SESSION['admin_id'], $penalite);
            }
        } elseif (preg_match('/^emp-(\d+)$/', $raw_cible, $m)) {
            $emp_id = (int) $m[1];
            $em = get_employe_by_id($emp_id);
            if (!absences_fiche_employe_eligible($em)) {
                $_SESSION['error_message'] = 'Fiche employé invalide ou inactive.';
            } else {
                $nid = employe_absence_creer_pour_fiche_employe($emp_id, $date_a, $motif, (int) $_SESSION['admin_id'], $penalite);
            }
        } else {
            $_SESSION['error_message'] = 'Veuillez choisir une personne dans la liste.';
        }

        if (!isset($_SESSION['error_message'])) {
            if ($nid) {
                $_SESSION['success_message'] = 'Absence enregistrée.';
            } else {
                $_SESSION['error_message'] = 'Impossible d’enregistrer (déjà une absence à cette date pour cette personne ou erreur technique).';
            }
        }
        header('Location: absences.php');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'justify_absence') {
        $raw_cible = isset($_POST['justify_cible']) ? trim((string) $_POST['justify_cible']) : '';
        $aid = isset($_POST['absence_id']) ? (int) $_POST['absence_id'] : 0;
        $texte = isset($_POST['justif_texte']) ? trim((string) $_POST['justif_texte']) : '';
        $abs = $aid > 0 ? employe_absence_get_by_id($aid) : false;

        $cible_ok = false;
        if (preg_match('/^adm-(\d+)$/', $raw_cible, $m)) {
            $sid = (int) $m[1];
            $st = get_admin_by_id($sid);
            $cible_ok = $st && absences_staff_est_eligible($st)
                && $abs && (int) ($abs['subject_admin_id'] ?? 0) === $sid;
        } elseif (preg_match('/^emp-(\d+)$/', $raw_cible, $m)) {
            $eid = (int) $m[1];
            $em = get_employe_by_id($eid);
            $cible_ok = absences_fiche_employe_eligible($em)
                && $abs && (int) ($abs['employe_id'] ?? 0) === $eid && (int) ($abs['subject_admin_id'] ?? 0) === 0;
        }

        if ($aid <= 0) {
            $_SESSION['error_message'] = 'Veuillez sélectionner une absence à justifier.';
        } elseif (!$cible_ok) {
            $_SESSION['error_message'] = 'Personne ou absence incohérentes.';
        } elseif (employe_absence_a_deja_justificatif($aid)) {
            $_SESSION['error_message'] = 'Cette absence possède déjà un justificatif.';
        } else {
            $rel_path = null;
            $orig_name = null;
            $mime_st = null;
            if (!empty($_FILES['justif_fichier']) && is_array($_FILES['justif_fichier'])) {
                $up = absences_traiter_upload_justif($_FILES['justif_fichier']);
                if (is_string($up)) {
                    $_SESSION['error_message'] = $up;
                    header('Location: absences.php');
                    exit;
                }
                list($basename, $orig_name, $mime_st) = $up;
                if ($basename !== null && $basename !== '') {
                    $dest = $upload_dir . DIRECTORY_SEPARATOR . $basename;
                    if (!move_uploaded_file($_FILES['justif_fichier']['tmp_name'], $dest)) {
                        $_SESSION['error_message'] = 'Échec de l’enregistrement du fichier.';
                        header('Location: absences.php');
                        exit;
                    }
                    $rel_path = $upload_subdir . '/' . $basename;
                }
            }
            if (($texte === '') && ($rel_path === null || $rel_path === '')) {
                $_SESSION['error_message'] = 'Saisissez un texte de justification ou joignez une image.';
            } else {
                $jid = employe_absence_justification_enregistrer($aid, $texte ?: null, $rel_path, $orig_name, $mime_st, (int) $_SESSION['admin_id']);
                if ($jid) {
                    $_SESSION['success_message'] = 'Justificatif enregistré.';
                } else {
                    if ($rel_path && is_file($upload_dir . DIRECTORY_SEPARATOR . basename($rel_path))) {
                        @unlink($upload_dir . DIRECTORY_SEPARATOR . basename($rel_path));
                    }
                    $_SESSION['error_message'] = 'Impossible d’enregistrer le justificatif.';
                }
            }
        }
        header('Location: absences.php');
        exit;
    }

    header('Location: absences.php');
    exit;
}

$staff_liste = get_admins_eligibles_absences();
$fiches_employes_abs = get_all_employes('actif');

$absences_recentes = employe_absences_liste_recentes(80, null);

$absences_par_cible_json = [];

foreach ($staff_liste as $collab) {
    $key = 'adm-' . (int) $collab['id'];
    $absences_par_cible_json[$key] = [];
    foreach (employe_absences_non_justifiees_pour_staff_admin((int) $collab['id']) as $row) {
        $absences_par_cible_json[$key][] = [
            'id' => (int) $row['id'],
            'date' => $row['date_absence'],
            'motif' => $row['motif'],
        ];
    }
}
foreach ($fiches_employes_abs as $fe) {
    $key = 'emp-' . (int) $fe['id'];
    $absences_par_cible_json[$key] = [];
    foreach (employe_absences_non_justifiees_pour_fiche_employe((int) $fe['id']) as $row) {
        $absences_par_cible_json[$key][] = [
            'id' => (int) $row['id'],
            'date' => $row['date_absence'],
            'motif' => $row['motif'],
        ];
    }
}

$page_title = 'Gestion des absences';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-comptes-page.css'); ?>
    <?php fpl_css_link('admin-absences.css'); ?>
</head>
<body class="page-comptes page-absences">
    <?php include '../includes/nav.php'; ?>

    <div class="page-comptes-wrap abs-page">
        <header class="comptes-header-bar page-comptes-hero abs-hero">
            <div class="page-comptes-hero__text">
                <p class="page-comptes-eyebrow">Ressources humaines</p>
                <h1 id="abs-page-title"><i class="fas fa-calendar-xmark" aria-hidden="true"></i> Gestion des absences</h1>
                <p class="comptes-lead">Comptes espace admin <strong>non administrateur</strong> et <strong>fiches employés</strong> (table <strong>employes</strong>). Ajoutez les fiches depuis <a href="employes/index.php">Employés</a>.</p>
            </div>
            <div class="comptes-header-actions page-comptes-hero__actions abs-hero__actions">
                <button type="button" class="page-comptes-cta page-comptes-cta--secondary abs-hero-btn" data-abs-open="justify">
                    <i class="fas fa-file-signature" aria-hidden="true"></i> Justifier une absence
                </button>
                <button type="button" class="page-comptes-cta abs-hero-btn" data-abs-open="add">
                    <i class="fas fa-plus" aria-hidden="true"></i> Ajouter une absence
                </button>
                <a href="employes/index.php" class="page-comptes-cta page-comptes-cta--employes abs-hero-btn">
                    <i class="fas fa-id-card-clip" aria-hidden="true"></i> Employés
                </a>
                <a href="index.php" class="page-comptes-cta page-comptes-cta--ghost abs-hero-btn">
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

        <section class="abs-panel" aria-labelledby="abs-list-title">
            <div class="abs-panel__head">
                <h2 id="abs-list-title" class="abs-panel__title"><i class="fas fa-list-ul" aria-hidden="true"></i> Dernières absences</h2>
                <p class="abs-panel__hint">Statut « justifiée » dès qu’un texte ou une image est enregistré.</p>
            </div>
            <?php if (empty($absences_recentes)): ?>
                <div class="abs-empty">
                    <div class="abs-empty__ic" aria-hidden="true"><i class="fas fa-mug-hot"></i></div>
                    <p class="abs-empty__title">Aucune absence enregistrée</p>
                    <p class="abs-empty__txt">Utilisez le bouton <strong>Ajouter une absence</strong> pour commencer.</p>
                </div>
            <?php else: ?>
                <div class="abs-table-wrap">
                    <table class="abs-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Type</th>
                                <th scope="col">Collaborateur</th>
                                <th scope="col">Motif</th>
                                <th scope="col">Pénalité</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Justificatif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absences_recentes as $a): ?>
                                <tr>
                                    <td data-label="Date"><?php echo htmlspecialchars(date('d/m/Y', strtotime($a['date_absence']))); ?></td>
                                    <td data-label="Type">
                                        <?php if (($a['absence_source'] ?? '') === 'admin'): ?>
                                            <span class="abs-badge-type abs-badge-type--admin"><i class="fas fa-user-shield" aria-hidden="true"></i> Compte</span>
                                        <?php elseif (($a['absence_source'] ?? '') === 'employe_fiche'): ?>
                                            <span class="abs-badge-type abs-badge-type--fiche"><i class="fas fa-id-card" aria-hidden="true"></i> Employé</span>
                                        <?php else: ?>
                                            <span class="abs-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Collaborateur"><?php
                                        $aff = trim((string) ($a['employe_prenom'] ?? '') . ' ' . (string) ($a['employe_nom'] ?? ''));
                                        echo $aff !== '' ? htmlspecialchars($aff) : '—';
                                    ?></td>
                                    <td data-label="Motif" class="abs-table__motif"><?php echo htmlspecialchars(mb_strimwidth($a['motif'], 0, 120, '…', 'UTF-8')); ?></td>
                                    <td data-label="Pénalité" class="abs-table__num"><?php
                                        $pm = isset($a['penalite_montant']) ? (float) $a['penalite_montant'] : 0.0;
                                        echo $pm > 0 ? htmlspecialchars(number_format($pm, 0, ',', ' ')) . ' FCFA' : '—';
                                    ?></td>
                                    <td data-label="Statut">
                                        <?php if (!empty($a['justif_id'])): ?>
                                            <span class="abs-badge abs-badge--ok"><i class="fas fa-check" aria-hidden="true"></i> Justifiée</span>
                                        <?php else: ?>
                                            <span class="abs-badge abs-badge--pending"><i class="fas fa-hourglass-half" aria-hidden="true"></i> En attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Justificatif" class="abs-table__justif">
                                        <?php if (!empty($a['justif_id'])): ?>
                                            <?php if (!empty($a['justif_fichier'])): ?>
                                                <a href="/upload/<?php echo htmlspecialchars($a['justif_fichier']); ?>" target="_blank" rel="noopener" class="abs-link-file"><i class="fas fa-image" aria-hidden="true"></i> Voir l’image</a>
                                            <?php endif; ?>
                                            <?php if (!empty($a['justif_texte'])): ?>
                                                <span class="abs-justif-snippet" title="<?php echo htmlspecialchars($a['justif_texte']); ?>"><?php echo htmlspecialchars(mb_strimwidth($a['justif_texte'], 0, 80, '…', 'UTF-8')); ?></span>
                                            <?php elseif (empty($a['justif_fichier'])): ?>
                                                <span class="abs-muted">—</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="abs-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <script type="application/json" id="abs-json-cibles-absence"><?php echo json_encode($absences_par_cible_json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>

    <!-- Modal ajouter -->
    <div id="abs-modal-add" class="abs-modal" aria-hidden="true" role="dialog" aria-labelledby="abs-modal-add-title">
        <div class="abs-modal__backdrop" data-abs-close tabindex="-1"></div>
        <div class="abs-modal__panel">
            <button type="button" class="abs-modal__close" data-abs-close aria-label="Fermer"><i class="fas fa-times" aria-hidden="true"></i></button>
            <div class="abs-modal__head">
                <span class="abs-modal__icon" aria-hidden="true"><i class="fas fa-calendar-plus"></i></span>
                <div>
                    <h2 id="abs-modal-add-title" class="abs-modal__title">Ajouter une absence</h2>
                    <p class="abs-modal__subtitle">Choisissez un <strong>compte admin</strong> (hors administrateur système) ou une <strong>fiche employé</strong> enregistrée.</p>
                </div>
            </div>
            <form class="abs-form" method="post" action="absences.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="add_absence">
                <div class="abs-form__grid">
                    <div class="abs-field">
                        <label for="add-date">Date de l’absence <span class="abs-req">*</span></label>
                        <input type="date" id="add-date" name="date_absence" required value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                    </div>
                    <div class="abs-field abs-field--full">
                        <label for="add-cible">Personne concernée <span class="abs-req">*</span></label>
                        <select id="add-cible" name="absence_cible" required>
                            <option value="">— Sélectionnez —</option>
                            <?php if (!empty($staff_liste)): ?>
                            <optgroup label="Comptes espace admin">
                                <?php foreach ($staff_liste as $collab): ?>
                                <option value="adm-<?php echo (int) $collab['id']; ?>"><?php echo htmlspecialchars($collab['prenom'] . ' ' . $collab['nom'] . ' — ' . admin_role_label($collab['role'] ?? 'gestion_stock')); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($fiches_employes_abs)): ?>
                            <optgroup label="Fiches employés (table employes)">
                                <?php foreach ($fiches_employes_abs as $fe): ?>
                                <option value="emp-<?php echo (int) $fe['id']; ?>"><?php echo htmlspecialchars($fe['prenom'] . ' ' . $fe['nom'] . ($fe['poste'] ? ' — ' . $fe['poste'] : '')); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($staff_liste) && empty($fiches_employes_abs)): ?>
                            <p class="abs-muted" style="margin-top:8px;font-size:0.85rem;">Aucune cible disponible : créez des comptes (autres que « administrateur ») ou des <a href="employes/index.php">fiches employés</a>.</p>
                        <?php endif; ?>
                    </div>
                    <div class="abs-field abs-field--full">
                        <label for="add-motif">Motif <span class="abs-req">*</span></label>
                        <textarea id="add-motif" name="motif" required rows="4" placeholder="Ex. : arrêt maladie, congé sans solde, rendez-vous médical…"></textarea>
                    </div>
                    <div class="abs-field">
                        <label for="add-penalite">Pénalité (FCFA)</label>
                        <input type="text" id="add-penalite" name="penalite_montant" inputmode="decimal" placeholder="0" value="0">
                        <p class="abs-muted" style="margin-top:6px;font-size:0.8rem;">Montant optionnel — pour les fiches employés, la RH peut demander une retenue sur salaire depuis la fiche.</p>
                    </div>
                </div>
                <div class="abs-form__actions">
                    <button type="button" class="abs-btn abs-btn--ghost" data-abs-close>Annuler</button>
                    <button type="submit" class="abs-btn abs-btn--primary"><i class="fas fa-check" aria-hidden="true"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal justifier -->
    <div id="abs-modal-justify" class="abs-modal" aria-hidden="true" role="dialog" aria-labelledby="abs-modal-justify-title">
        <div class="abs-modal__backdrop" data-abs-close tabindex="-1"></div>
        <div class="abs-modal__panel abs-modal__panel--wide">
            <button type="button" class="abs-modal__close" data-abs-close aria-label="Fermer"><i class="fas fa-times" aria-hidden="true"></i></button>
            <div class="abs-modal__head">
                <span class="abs-modal__icon abs-modal__icon--orange" aria-hidden="true"><i class="fas fa-file-signature"></i></span>
                <div>
                    <h2 id="abs-modal-justify-title" class="abs-modal__title">Justifier une absence</h2>
                    <p class="abs-modal__subtitle">Sélectionnez la personne, puis l’absence encore sans justificatif. Au moins un texte ou une image est requis.</p>
                </div>
            </div>
            <form class="abs-form" method="post" action="absences.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">
                <input type="hidden" name="action" value="justify_absence">
                <div class="abs-form__grid abs-form__grid--split">
                    <div class="abs-field">
                        <label for="justify-cible">Personne concernée <span class="abs-req">*</span></label>
                        <select id="justify-cible" name="justify_cible" required>
                            <option value="">— Choisir —</option>
                            <?php if (!empty($staff_liste)): ?>
                            <optgroup label="Comptes espace admin">
                                <?php foreach ($staff_liste as $collab):
                                    $k = 'adm-' . (int) $collab['id'];
                                    $nj = count($absences_par_cible_json[$k] ?? []);
                                    ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($collab['prenom'] . ' ' . $collab['nom']); ?><?php echo $nj ? ' (' . $nj . ' sans justif.)' : ''; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($fiches_employes_abs)): ?>
                            <optgroup label="Fiches employés">
                                <?php foreach ($fiches_employes_abs as $fe):
                                    $ke = 'emp-' . (int) $fe['id'];
                                    $nje = count($absences_par_cible_json[$ke] ?? []);
                                    ?>
                                <option value="<?php echo htmlspecialchars($ke); ?>"><?php echo htmlspecialchars($fe['prenom'] . ' ' . $fe['nom']); ?><?php echo $nje ? ' (' . $nje . ' sans justif.)' : ''; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="abs-field">
                        <label for="justify-absence">Absence à justifier <span class="abs-req">*</span></label>
                        <select id="justify-absence" name="absence_id" required disabled>
                            <option value="">— Choisir d’abord un employé —</option>
                        </select>
                    </div>
                    <div class="abs-field abs-field--full">
                        <label for="justify-texte">Texte du justificatif</label>
                        <textarea id="justify-texte" name="justif_texte" rows="4" placeholder="Commentaire libre, référence du document, etc."></textarea>
                    </div>
                    <div class="abs-field abs-field--full">
                        <label for="justify-file">Joindre une image</label>
                        <div class="abs-filezone">
                            <input type="file" id="justify-file" name="justif_fichier" accept="image/jpeg,image/png,image/webp" class="abs-file-input">
                            <p class="abs-filezone__hint">JPEG, PNG ou WebP — max. <?php echo (int) fouta_upload_image_max_mo_int(); ?> Mo</p>
                        </div>
                        <div id="justify-preview" class="abs-preview abs-preview--hidden" aria-live="polite">
                            <p class="abs-preview__label">Prévisualisation</p>
                            <div class="abs-preview__frame">
                                <img id="justify-preview-img" src="" alt="Aperçu du justificatif">
                            </div>
                            <button type="button" id="justify-preview-clear" class="abs-btn abs-btn--mini abs-btn--ghost">Retirer l’image</button>
                        </div>
                    </div>
                </div>
                <div class="abs-form__actions">
                    <button type="button" class="abs-btn abs-btn--ghost" data-abs-close>Annuler</button>
                    <button type="submit" class="abs-btn abs-btn--primary abs-btn--orange"><i class="fas fa-paper-plane" aria-hidden="true"></i> Enregistrer le justificatif</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/js/admin-absences-ui.js<?php echo asset_version_query(); ?>"></script>
</body>
</html>
