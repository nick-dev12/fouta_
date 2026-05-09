<?php
/**
 * Ajout rapide d’une fiche employé (nom, prénom, fonction)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'rh', 'informaticien'], true)) {
    header('Location: ../../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../../controllers/controller_employes.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $_SESSION['error_message'] = 'Session expirée. Réessayez.';
        header('Location: ajouter.php');
        exit;
    }
    $result = process_employe_ajout_rh_simple();
    if (!empty($result['success'])) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: index.php');
        exit;
    }
}
$result = isset($result) ? $result : ['success' => false, 'message' => ''];
$err_flash = !$result['success'] && ($result['message'] ?? '') !== '' ? $result['message'] : '';
$p = $_POST;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un employé — Administration</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-comptes-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-employes-rh.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-comptes page-employes-rh page-employes-ajouter">
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="page-comptes-wrap er-page er-page--narrow">
        <header class="er-mini-hero">
            <div>
                <p class="page-comptes-eyebrow">Nouvelle fiche</p>
                <h1><i class="fas fa-user-plus" aria-hidden="true"></i> Ajouter un employé</h1>
                <p class="comptes-lead">Enregistré dans la table <strong>employes</strong>. Vous pourrez compléter la fiche ensuite (contact, compte lié…).</p>
            </div>
            <a href="index.php" class="page-comptes-cta page-comptes-cta--ghost"><i class="fas fa-arrow-left"></i> Liste</a>
        </header>

        <?php if ($err_flash): ?>
            <div class="message error page-comptes-flash" role="alert"><?php echo $err_flash; ?></div>
        <?php endif; ?>

        <form method="post" action="ajouter.php" class="er-form-card" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="creer_employe_rh_simple" value="1">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">

            <div class="er-form-grid">
                <?php
                $sf_sel = isset($p['statut_familial']) ? trim((string) $p['statut_familial']) : '';
                if ($sf_sel === '') {
                    $sf_sel = 'non_renseigne';
                }
                $tc_sel = isset($p['type_contrat']) ? trim((string) $p['type_contrat']) : '';
                if ($tc_sel === '') {
                    $tc_sel = 'non_renseigne';
                }
                ?>
                <div class="er-field">
                    <label for="nom">Nom <span class="er-req">*</span></label>
                    <input type="text" id="nom" name="nom" required autocomplete="family-name" placeholder="Ex. : Diop"
                        value="<?php echo htmlspecialchars($p['nom'] ?? ''); ?>">
                </div>
                <div class="er-field">
                    <label for="prenom">Prénom <span class="er-req">*</span></label>
                    <input type="text" id="prenom" name="prenom" required autocomplete="given-name" placeholder="Ex. : Aminata"
                        value="<?php echo htmlspecialchars($p['prenom'] ?? ''); ?>">
                </div>
                <div class="er-field">
                    <label for="telephone">Téléphone</label>
                    <input type="text" id="telephone" name="telephone" autocomplete="tel" inputmode="tel" placeholder="+33 … ou 06 …"
                        value="<?php echo htmlspecialchars($p['telephone'] ?? ''); ?>">
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
                    <label for="salaire_base">Salaire brut (FCFA) <span class="er-opt">(optionnel)</span></label>
                    <input type="text" id="salaire_base" name="salaire_base" inputmode="decimal" autocomplete="off"
                        placeholder="Préremplit le bulletin de paie"
                        value="<?php echo htmlspecialchars($p['salaire_base'] ?? ''); ?>">
                </div>
                <div class="er-field">
                    <label for="montant_irpp_mensuel">IRPP mensuel (FCFA) <span class="er-opt">(optionnel)</span></label>
                    <input type="text" id="montant_irpp_mensuel" name="montant_irpp_mensuel" inputmode="decimal" autocomplete="off"
                        placeholder="Impôt sur le revenu retenu chaque mois"
                        value="<?php echo htmlspecialchars($p['montant_irpp_mensuel'] ?? ''); ?>">
                </div>
                <div class="er-field">
                    <label for="date_embauche">Date d’embauche <span class="er-opt">(optionnel)</span></label>
                    <input type="date" id="date_embauche" name="date_embauche"
                        value="<?php echo !empty($p['date_embauche']) ? htmlspecialchars(substr((string) $p['date_embauche'], 0, 10)) : ''; ?>">
                </div>
                <div class="er-field er-field--full">
                    <label for="poste">Fonction <span class="er-req">*</span></label>
                    <input type="text" id="poste" name="poste" required placeholder="Ex. : Magasinier, Comptable, Chauffeur…"
                        value="<?php echo htmlspecialchars($p['poste'] ?? ''); ?>">
                </div>
                <div class="er-field er-field--full">
                    <label for="contrat_pdf">Contrat (PDF) <span class="er-opt">(optionnel, max 8 Mo)</span></label>
                    <input type="file" id="contrat_pdf" name="contrat_pdf" accept="application/pdf">
                    <p class="er-photo-hint">Fichier PDF uniquement.</p>
                </div>
                <div class="er-field er-field--full er-photo-field">
                    <label for="photo_employe">Photo de l’employé <span class="er-opt">(optionnel, max <?php echo (int) (EMPLOYE_PHOTO_UPLOAD_MAX_BYTES / (1024 * 1024)); ?> Mo)</span></label>
                    <input type="file" id="photo_employe" name="photo_employe" accept="image/jpeg,image/png,image/webp,image/gif">
                    <p class="er-photo-hint">JPG, PNG, WEBP ou GIF. Une prévisualisation s’affiche ci-dessous une fois choisie.</p>
                    <div class="er-photo-preview-wrap" id="photoPreviewWrap" hidden>
                        <img src="" alt="Aperçu photo employé" class="er-photo-preview" id="photoPreviewImg" width="160" height="160">
                    </div>
                </div>
            </div>

            <div class="er-form-actions">
                <a href="index.php" class="er-btn er-btn--ghost">Annuler</a>
                <button type="submit" class="er-btn er-btn--primary"><i class="fas fa-check" aria-hidden="true"></i> Enregistrer</button>
            </div>
        </form>
    </div>
    <script>
    (function () {
        var input = document.getElementById('photo_employe');
        var wrap = document.getElementById('photoPreviewWrap');
        var img = document.getElementById('photoPreviewImg');
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
