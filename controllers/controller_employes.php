<?php
/**
 * Contrôleur — fiches employés
 */
require_once __DIR__ . '/../models/model_employes.php';
require_once __DIR__ . '/../models/model_employe_matricules.php';

define('EMPLOYE_PHOTO_UPLOAD_MAX_BYTES', 4 * 1024 * 1024);
define('EMPLOYE_PHOTO_FIELD', 'photo_employe');

/**
 * Champ fichier photo dans les formulaires (multipart).
 */
function employe_photo_champ_fichier() {
    return EMPLOYE_PHOTO_FIELD;
}

/**
 * Détection MIME fichier temporaire (upload).
 *
 * @return string
 */
function employe_photo_mime_detect($tmp_name) {
    if (!is_string($tmp_name) || $tmp_name === '' || !is_file($tmp_name)) {
        return '';
    }
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);
            return is_string($mime) ? $mime : '';
        }
    }
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp_name);
        return is_string($mime) ? $mime : '';
    }

    return '';
}

/**
 * Vérifie un upload optionnel avant INSERT / UPDATE — retourne extension autorisée.
 *
 * @return array{ok:bool, msg:string, ext:string}
 */
function employe_photo_inspect_joint($file) {
    $empty = ['ok' => true, 'msg' => '', 'ext' => ''];
    if (!$file || !is_array($file)) {
        return $empty;
    }
    $code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return $empty;
    }
    if ($code !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Le téléversement de la photo a échoué. Réessayez.', 'ext' => ''];
    }

    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > EMPLOYE_PHOTO_UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'msg' => 'La photo doit faire au plus 4 Mo.', 'ext' => ''];
    }

    $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    $mime = employe_photo_mime_detect($tmp);
    $map = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($map[$mime])) {
        return ['ok' => false, 'msg' => 'Format photo non autorisé (JPG, PNG, WEBP ou GIF uniquement).', 'ext' => ''];
    }

    return ['ok' => true, 'msg' => '', 'ext' => $map[$mime]];
}

/**
 * Enregistre la photo sur disque pour un employé (remplace les anciennes de ce même ID).
 *
 * @return array{ok:bool, msg:string}
 */
function employe_photo_process_for_employe($employe_id, $file) {
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['ok' => false, 'msg' => 'Identifiant employé invalide.'];
    }

    $insp = employe_photo_inspect_joint($file);
    if (!$insp['ok']) {
        return ['ok' => false, 'msg' => $insp['msg']];
    }

    $code = is_array($file) && isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'msg' => ''];
    }

    $upload_dir_abs = __DIR__ . '/../upload/employes_photos/';
    if (!is_dir($upload_dir_abs) && !@mkdir($upload_dir_abs, 0755, true) && !is_dir($upload_dir_abs)) {
        return ['ok' => false, 'msg' => 'Impossible de préparer le dossier des photos RH.'];
    }

    $ext = $insp['ext'];
    $new_base = 'employe_' . $employe_id . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $abs_new = $upload_dir_abs . $new_base;

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'msg' => 'Fichier téléversé invalide.'];
    }

    if (!move_uploaded_file($file['tmp_name'], $abs_new)) {
        return ['ok' => false, 'msg' => 'Impossible d’enregistrer la photo sur le serveur.'];
    }

    $pattern = $upload_dir_abs . 'employe_' . $employe_id . '_*';
    foreach (glob($pattern) ?: [] as $old_abs) {
        if (!is_string($old_abs) || !is_file($old_abs)) {
            continue;
        }
        if (basename($old_abs) === $new_base) {
            continue;
        }
        @unlink($old_abs);
    }

    $relatif = 'employes_photos/' . $new_base;
    if (!employe_set_photo_chemin($employe_id, $relatif)) {
        @unlink($abs_new);
        return ['ok' => false, 'msg' => 'Erreur d’enregistrement du chemin photo en base de données.'];
    }

    return ['ok' => true, 'msg' => ''];
}

function employe_collect_post() {
    return [
        'nom' => isset($_POST['nom']) ? trim($_POST['nom']) : '',
        'prenom' => isset($_POST['prenom']) ? trim($_POST['prenom']) : '',
        'email' => isset($_POST['email']) ? trim($_POST['email']) : '',
        'telephone' => isset($_POST['telephone']) ? trim($_POST['telephone']) : '',
        'poste' => isset($_POST['poste']) ? trim($_POST['poste']) : '',
        'service' => isset($_POST['service']) ? trim($_POST['service']) : '',
        'date_embauche' => isset($_POST['date_embauche']) ? trim($_POST['date_embauche']) : '',
        'statut' => isset($_POST['statut']) ? trim($_POST['statut']) : 'actif',
        'notes' => isset($_POST['notes']) ? trim($_POST['notes']) : '',
        'admin_id' => isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0,
    ];
}

function employe_valider($d) {
    $err = [];
    if ($d['nom'] === '' || mb_strlen($d['nom']) < 2) {
        $err[] = 'Le nom est obligatoire (2 caractères min).';
    }
    if ($d['prenom'] === '' || mb_strlen($d['prenom']) < 2) {
        $err[] = 'Le prénom est obligatoire (2 caractères min).';
    }
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $err[] = 'L’adresse e-mail n’est pas valide.';
    }
    if (!in_array($d['statut'], ['actif', 'inactif', 'suspendu'], true)) {
        $err[] = 'Statut invalide.';
    }
    return $err;
}

function process_employe_ajout() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['creer_employe'])) {
        return ['success' => false, 'message' => ''];
    }
    $d = employe_collect_post();
    $err = employe_valider($d);
    if (!empty($err)) {
        return ['success' => false, 'message' => implode('<br>', $err)];
    }
    $id = create_employe($d);
    if (!$id) {
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement.'];
    }
    if (employe_matricule_assigner_si_absent((int) $id) === false) {
        delete_employe((int) $id);
        return ['success' => false, 'message' => 'Impossible d’attribuer un matricule unique. Réessayez.'];
    }
    $mat_aff = employe_matricule_par_employe_id((int) $id);
    $mat_piece = ($mat_aff !== false) ? (' Matricule : ' . (string) $mat_aff . '.') : '';
    return ['success' => true, 'message' => 'Employé enregistré.' . $mat_piece, 'id' => $id];
}

/**
 * Formulaire court : nom, prénom, fonction (poste) obligatoires.
 */
function process_employe_ajout_rh_simple() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['creer_employe_rh_simple'])) {
        return ['success' => false, 'message' => ''];
    }
    $d = employe_collect_post();
    $err = employe_valider($d);
    if (trim($d['poste']) === '' || mb_strlen(trim($d['poste'])) < 2) {
        $err[] = 'La fonction est obligatoire (2 caractères min).';
    }
    if (($d['telephone'] ?? '') !== '' && !preg_match('/^[0-9 +().\\-\\s]{8,25}$/u', $d['telephone'])) {
        $err[] = 'Le numéro de téléphone ne semble pas valide.';
    }
    $fphoto = isset($_FILES[EMPLOYE_PHOTO_FIELD]) && is_array($_FILES[EMPLOYE_PHOTO_FIELD]) ? $_FILES[EMPLOYE_PHOTO_FIELD] : null;
    $ph_insp = employe_photo_inspect_joint($fphoto);
    if (!$ph_insp['ok']) {
        $err[] = $ph_insp['msg'];
    }
    if (!empty($err)) {
        return ['success' => false, 'message' => implode('<br>', $err)];
    }
    $id = create_employe($d);
    if (!$id) {
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement.'];
    }
    if (employe_matricule_assigner_si_absent((int) $id) === false) {
        delete_employe((int) $id);
        return ['success' => false, 'message' => 'Impossible d’attribuer un matricule unique. Réessayez.'];
    }
    $pho = employe_photo_process_for_employe((int) $id, $fphoto);
    if (!$pho['ok']) {
        delete_employe((int) $id);
        return ['success' => false, 'message' => $pho['msg']];
    }

    require_once __DIR__ . '/../includes/employe_qrcode.php';
    employe_generer_et_sauver_qrcode((int) $id);
    $mat_aff = employe_matricule_par_employe_id((int) $id);
    $mat_piece = ($mat_aff !== false) ? (' Matricule : ' . (string) $mat_aff . '.') : '';
    $photo_piece = (($fphoto && (int) ($fphoto['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) ? ' Photo ajoutée.' : '');
    return ['success' => true, 'message' => 'Employé enregistré avec son badge QR.' . $mat_piece . $photo_piece, 'id' => $id];
}

function process_employe_modification($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['modifier_employe'])) {
        return ['success' => false, 'message' => ''];
    }
    $d = employe_collect_post();
    $err = employe_valider($d);
    if (($d['telephone'] ?? '') !== '' && !preg_match('/^[0-9 +().\\-\\s]{8,25}$/u', $d['telephone'])) {
        $err[] = 'Le numéro de téléphone ne semble pas valide.';
    }
    $fphoto = isset($_FILES[EMPLOYE_PHOTO_FIELD]) && is_array($_FILES[EMPLOYE_PHOTO_FIELD]) ? $_FILES[EMPLOYE_PHOTO_FIELD] : null;
    $ph_insp = employe_photo_inspect_joint($fphoto);
    if (!$ph_insp['ok']) {
        $err[] = $ph_insp['msg'];
    }
    if (!empty($err)) {
        return ['success' => false, 'message' => implode('<br>', $err)];
    }
    if (update_employe($employe_id, $d)) {
        if (employe_matricule_assigner_si_absent((int) $employe_id) === false) {
            return ['success' => false, 'message' => 'Impossible d’enregistrer le matricule RH. Réessayez.'];
        }
        $pho = employe_photo_process_for_employe((int) $employe_id, $fphoto);
        if (!$pho['ok']) {
            return ['success' => false, 'message' => $pho['msg']];
        }
        require_once __DIR__ . '/../includes/employe_qrcode.php';
        employe_generer_et_sauver_qrcode((int) $employe_id);
        $suffix = ($fphoto && (int) ($fphoto['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) ? ' Photo mise à jour.' : '';
        return ['success' => true, 'message' => 'Fiche mise à jour (badge QR régénéré).' . $suffix];
    }
    return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
}

function process_employe_suppression($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['supprimer_employe'])) {
        return ['success' => false, 'message' => ''];
    }
    if (delete_employe($employe_id)) {
        return ['success' => true, 'message' => 'Fiche supprimée.'];
    }
    return ['success' => false, 'message' => 'Impossible de supprimer cette fiche.'];
}
