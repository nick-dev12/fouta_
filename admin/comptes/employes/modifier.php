<?php
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

require_once __DIR__ . '/../../../models/model_employes.php';
require_once __DIR__ . '/../../../controllers/controller_employes.php';
require_once __DIR__ . '/../../../models/model_admin.php';
require_once __DIR__ . '/../../../includes/site_url.php';

$employe = get_employe_by_id($id);
if (!$employe) {
    header('Location: index.php');
    exit;
}

$result = process_employe_modification($id);
if (!empty($result['success'])) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}

$admins = get_all_admins();
$error_msg = isset($result['message']) && !$result['success'] ? $result['message'] : '';

$p = $_POST;
if (empty($p)) {
    $p = $employe;
}
$sf_sel = isset($p['statut_familial']) ? trim((string) $p['statut_familial']) : '';
if ($sf_sel === '' || $sf_sel === null) {
    $sf_sel = 'non_renseigne';
}
$tc_sel = isset($p['type_contrat']) ? trim((string) $p['type_contrat']) : '';
if ($tc_sel === '' || $tc_sel === null) {
    $tc_sel = 'non_renseigne';
}

$upload_public = rtrim(get_request_origin_base_url(), '/') . '/upload/';
$upload_disk = __DIR__ . '/../../../upload/';
$curr_ph = trim((string) ($employe['photo_chemin'] ?? ''));
$curr_ph_ok = $curr_ph !== '' && strpos($curr_ph, '..') === false
    && is_file($upload_disk . str_replace('/', DIRECTORY_SEPARATOR, $curr_ph));
$photo_field = employe_photo_champ_fichier();
$contrat_pdf_field = employe_contrat_pdf_champ_fichier();
$curr_pdf_rel = trim((string) ($employe['contrat_pdf_chemin'] ?? ''));
$curr_pdf_ok = $curr_pdf_rel !== '' && strpos($curr_pdf_rel, '..') === false
    && is_file($upload_disk . str_replace('/', DIRECTORY_SEPARATOR, $curr_pdf_rel));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier employé — Administration</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-comptes-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employes-rh.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes page-employes-rh page-employes-modifier">
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="page-comptes-wrap er-page er-page--narrow">
        <header class="er-mini-hero">
            <div>
                <p class="page-comptes-eyebrow"><a href="details.php?id=<?php echo (int) $id; ?>">← Fiche employé</a></p>
                <h1><i class="fas fa-user-edit" aria-hidden="true"></i> Modifier la fiche</h1>
                <p class="comptes-lead">Informations du salarié, rémunération, IRPP &amp; TRIMF, pièces jointes.</p>
            </div>
            <a href="index.php" class="page-comptes-cta page-comptes-cta--ghost"><i class="fas fa-list"></i> Liste</a>
        </header>

        <?php if ($error_msg): ?>
            <div class="message error page-comptes-flash" role="alert"><i class="fas fa-exclamation-circle"></i> <span><?php echo $error_msg; ?></span></div>
        <?php endif; ?>

        <form method="post" class="er-form-card" enctype="multipart/form-data">
            <input type="hidden" name="modifier_employe" value="1">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">
            <div class="er-form-grid">
                <div class="er-form-section er-form-section--full">
                    <h2 class="er-form-section__title"><i class="fas fa-id-card" aria-hidden="true"></i> Identité</h2>
                </div>
                <div class="er-field">
                    <label for="nom">Nom <span class="er-req">*</span></label>
                    <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($p['nom'] ?? ''); ?>">
                </div>
                <div class="er-field">
                    <label for="prenom">Prénom <span class="er-req">*</span></label>
                    <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($p['prenom'] ?? ''); ?>">
                </div>
                <div class="er-field er-field--full">
                    <label for="poste">Poste</label>
                    <input type="text" id="poste" name="poste" value="<?php echo htmlspecialchars($p['poste'] ?? ''); ?>">
                </div>
                <div class="er-field er-field--full">
                    <label for="service">Service</label>
                    <input type="text" id="service" name="service" value="<?php echo htmlspecialchars($p['service'] ?? ''); ?>">
                </div>
                <div class="er-field er-field--full">
                    <label for="categorie_paie">Catégorie / classification (bulletin)</label>
                    <input type="text" id="categorie_paie" name="categorie_paie" maxlength="120"
                        placeholder="Ex. catégorie conventionnelle…"
                        value="<?php echo htmlspecialchars((string) ($p['categorie_paie'] ?? '')); ?>">
                </div>

                <div class="er-form-section er-form-section--full">
                    <h2 class="er-form-section__title"><i class="fas fa-address-book" aria-hidden="true"></i> Contact &amp; contrat</h2>
                </div>
                <div class="er-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($p['email'] ?? ''); ?>">
                </div>
                <div class="er-field">
                    <label for="telephone">Téléphone</label>
                    <input type="text" id="telephone" name="telephone" value="<?php echo htmlspecialchars($p['telephone'] ?? ''); ?>">
                </div>
                <div class="er-field">
                    <label for="date_embauche">Date d’embauche</label>
                    <input type="date" id="date_embauche" name="date_embauche" value="<?php echo !empty($p['date_embauche']) ? htmlspecialchars(substr($p['date_embauche'], 0, 10)) : ''; ?>">
                </div>
                <div class="er-field">
                    <label for="statut_familial">Statut familial</label>
                    <select id="statut_familial" name="statut_familial">
                        <?php foreach (employe_statuts_familiaux_choices() as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($sf_sel === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="er-field">
                    <label for="type_contrat">Type de contrat</label>
                    <select id="type_contrat" name="type_contrat">
                        <?php foreach (employe_types_contrat_choices() as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($tc_sel === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="er-field">
                    <label for="statut">Statut</label>
                    <select id="statut" name="statut">
                        <?php foreach (['actif' => 'Actif', 'inactif' => 'Inactif', 'suspendu' => 'Suspendu'] as $k => $lab): ?>
                        <option value="<?php echo $k; ?>" <?php echo (($p['statut'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $lab; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="er-field er-field--full">
                    <label for="admin_id">Compte d’accès interne</label>
                    <select id="admin_id" name="admin_id">
                        <option value="0">— Aucun —</option>
                        <?php foreach ($admins as $a): ?>
                        <option value="<?php echo (int) $a['id']; ?>" <?php echo (int) ($p['admin_id'] ?? 0) === (int) $a['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['prenom'] . ' ' . $a['nom'] . ' (' . $a['email'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="er-form-section er-form-section--full">
                    <h2 class="er-form-section__title"><i class="fas fa-coins" aria-hidden="true"></i> Rémunération &amp; fiscalité</h2>
                    <p class="er-form-section__lead">IRPP et TRIMF : montants fixes repris sur le bulletin de paie.</p>
                </div>
                <div class="er-field">
                    <label for="salaire_base">Salaire brut / de base (FCFA)</label>
                    <input type="text" id="salaire_base" name="salaire_base" inputmode="decimal" autocomplete="off"
                        value="<?php
                        $sb = $p['salaire_base'] ?? '';
                        echo ($sb !== null && $sb !== '') ? htmlspecialchars((string) $sb) : '';
                        ?>">
                </div>
                <div class="er-field">
                    <label for="montant_irpp_mensuel">IRPP mensuel (FCFA)</label>
                    <input type="text" id="montant_irpp_mensuel" name="montant_irpp_mensuel" inputmode="decimal" autocomplete="off"
                        value="<?php
                        $imir = $p['montant_irpp_mensuel'] ?? '';
                        echo ($imir !== null && $imir !== '') ? htmlspecialchars((string) $imir) : '';
                        ?>">
                </div>
                <div class="er-field">
                    <label for="montant_trimf_mensuel">TRIMF mensuel (FCFA)</label>
                    <input type="text" id="montant_trimf_mensuel" name="montant_trimf_mensuel" inputmode="decimal" autocomplete="off"
                        value="<?php
                        $mtrim = $p['montant_trimf_mensuel'] ?? '';
                        echo ($mtrim !== null && $mtrim !== '') ? htmlspecialchars((string) $mtrim) : '';
                        ?>">
                </div>

                <div class="er-form-section er-form-section--full">
                    <h2 class="er-form-section__title"><i class="fas fa-paperclip" aria-hidden="true"></i> Notes &amp; pièces</h2>
                </div>
                <div class="er-field er-field--full">
                    <label for="notes">Notes internes</label>
                    <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($p['notes'] ?? ''); ?></textarea>
                </div>
                <div class="er-field er-field--full">
                    <label for="<?php echo htmlspecialchars($contrat_pdf_field); ?>">Contrat (PDF)</label>
                    <?php if ($curr_pdf_ok): ?>
                    <p class="er-photo-hint">
                        <a href="<?php echo htmlspecialchars($upload_public . $curr_pdf_rel); ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-pdf" aria-hidden="true"></i> Télécharger le contrat actuel</a>
                    </p>
                    <label class="er-opt" style="display:flex;align-items:center;gap:8px;font-weight:500;margin-bottom:8px;">
                        <input type="checkbox" name="retirer_contrat_pdf" value="1"> Retirer le PDF enregistré
                    </label>
                    <?php endif; ?>
                    <input type="file" id="<?php echo htmlspecialchars($contrat_pdf_field); ?>" name="<?php echo htmlspecialchars($contrat_pdf_field); ?>" accept="application/pdf">
                    <p class="er-photo-hint">PDF uniquement. Laisser vide pour conserver le fichier actuel.</p>
                </div>
                <div class="er-field er-field--full er-photo-field">
                    <label for="<?php echo htmlspecialchars($photo_field); ?>">Photo de l’employé</label>
                    <?php if ($curr_ph_ok): ?>
                    <div class="er-photo-modifier-thumb er-photo-modifier-thumb--hero">
                        <img src="<?php echo htmlspecialchars($upload_public . $curr_ph); ?>" alt="Photo actuelle" width="140" height="140" decoding="async" class="er-photo-preview er-photo-preview--current">
                    </div>
                    <?php endif; ?>
                    <input type="file" id="<?php echo htmlspecialchars($photo_field); ?>" name="<?php echo htmlspecialchars($photo_field); ?>" accept="image/jpeg,image/png,image/webp,image/gif">
                    <p class="er-photo-hint">Laissez vide pour conserver la photo actuelle.</p>
                    <div class="er-photo-preview-wrap" id="photoPreviewWrapMod" hidden>
                        <p class="er-photo-modifier-actuelle">Nouvelle photo :</p>
                        <img src="" alt="Aperçu" class="er-photo-preview" id="photoPreviewImgMod" width="140" height="140">
                    </div>
                </div>
            </div>

            <div class="er-form-actions">
                <a href="details.php?id=<?php echo (int) $id; ?>" class="er-btn er-btn--ghost">Annuler</a>
                <button type="submit" class="er-btn er-btn--primary"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer</button>
            </div>
        </form>
    </div>

    <script>
    (function () {
        var input = document.getElementById(<?php echo json_encode($photo_field); ?>);
        var wrap = document.getElementById('photoPreviewWrapMod');
        var img = document.getElementById('photoPreviewImgMod');
        if (!input || !wrap || !img) return;
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) {
                wrap.hidden = true;
                img.removeAttribute('src');
                return;
            }
            var r = new FileReader();
            r.onload = function (e) {
                img.src = e.target.result || '';
                wrap.hidden = false;
            };
            r.readAsDataURL(input.files[0]);
        });
    })();
    </script>
</body>
</html>
