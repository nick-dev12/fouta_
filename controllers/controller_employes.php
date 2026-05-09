<?php
/**
 * Contrôleur — fiches employés
 */
require_once __DIR__ . '/../models/model_employes.php';
require_once __DIR__ . '/../models/model_employe_matricules.php';
require_once __DIR__ . '/../models/model_employe_documents.php';
require_once __DIR__ . '/../models/model_employe_sanctions.php';
require_once __DIR__ . '/../models/model_employe_autorisations_absence.php';
require_once __DIR__ . '/../models/model_employe_prets.php';
require_once __DIR__ . '/../models/model_employe_conges.php';
require_once __DIR__ . '/../models/model_bulletin_paie.php';
require_once __DIR__ . '/../models/model_employe_transport.php';
require_once __DIR__ . '/../includes/fouta_upload_limits.php';

define('EMPLOYE_PHOTO_UPLOAD_MAX_BYTES', FOUTA_UPLOAD_IMAGE_MAX_BYTES);
define('EMPLOYE_PHOTO_FIELD', 'photo_employe');
define('EMPLOYE_CONTRAT_PDF_UPLOAD_MAX_BYTES', 8 * 1024 * 1024);
define('EMPLOYE_CONTRAT_PDF_FIELD', 'contrat_pdf');
define('EMPLOYE_DOCUMENT_UPLOAD_MAX_BYTES', FOUTA_UPLOAD_IMAGE_MAX_BYTES);
define('EMPLOYE_DOCUMENT_FIELD', 'document_fichier');

function employe_statuts_familiaux_autorises() {
    return ['celibataire', 'marie', 'pacse', 'divorce', 'veuf', 'union_libre', 'autre'];
}

function employe_types_contrat_autorises() {
    return ['cdi', 'cdd', 'stage', 'alternance', 'interim', 'freelance', 'autre'];
}

/**
 * Étiquettes pour les formulaires (value => libellé).
 */
function employe_statuts_familiaux_choices() {
    return [
        'non_renseigne' => 'Non renseigné',
        'celibataire' => 'Célibataire',
        'marie' => 'Marié(e)',
        'pacse' => 'Pacsé(e)',
        'divorce' => 'Divorcé(e)',
        'veuf' => 'Veuf(ve)',
        'union_libre' => 'Union libre',
        'autre' => 'Autre',
    ];
}

function employe_types_contrat_choices() {
    return [
        'non_renseigne' => 'Non renseigné',
        'cdi' => 'CDI',
        'cdd' => 'CDD',
        'stage' => 'Stage',
        'alternance' => 'Alternance / apprentissage',
        'interim' => 'Intérim',
        'freelance' => 'Freelance / indépendant',
        'autre' => 'Autre',
    ];
}

function employe_sanctions_types_autorises() {
    return [
        'avertissement_verbal',
        'avertissement_ecrit',
        'blame',
        'mise_a_pied',
        'retrait_avantage',
        'autre_mesure',
    ];
}

function employe_sanctions_types_choices() {
    return [
        'avertissement_verbal' => 'Avertissement verbal',
        'avertissement_ecrit' => 'Avertissement écrit',
        'blame' => 'Blâme',
        'mise_a_pied' => 'Mise à pied (disciplinaire)',
        'retrait_avantage' => 'Retrait d’avantage ou de prime',
        'autre_mesure' => 'Autre mesure disciplinaire',
    ];
}

function employe_normalize_rh_enum_field($raw, array $allowed_slugs) {
    $raw = trim((string) $raw);
    if ($raw === '' || $raw === 'non_renseigne') {
        return null;
    }
    return in_array($raw, $allowed_slugs, true) ? $raw : null;
}

function employe_apply_rh_champs_db(array &$d) {
    $d['statut_familial'] = employe_normalize_rh_enum_field($d['statut_familial'] ?? '', employe_statuts_familiaux_autorises());
    $d['type_contrat'] = employe_normalize_rh_enum_field($d['type_contrat'] ?? '', employe_types_contrat_autorises());
}

function employe_contrat_pdf_champ_fichier() {
    return EMPLOYE_CONTRAT_PDF_FIELD;
}

/**
 * Supprime un fichier contrat sur disque (chemin relatif sécurisé sous upload/).
 */
function employe_contrat_pdf_delete_disk($chemin_relatif) {
    $chemin_relatif = trim((string) $chemin_relatif);
    if ($chemin_relatif === '' || strpos($chemin_relatif, '..') !== false) {
        return;
    }
    if (strpos($chemin_relatif, 'employes_contrats/') !== 0) {
        return;
    }
    $full = __DIR__ . '/../upload/' . str_replace('/', DIRECTORY_SEPARATOR, $chemin_relatif);
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Supprime un fichier joint employes_documents/ sur disque.
 */
function employe_document_delete_disk($chemin_relatif) {
    $chemin_relatif = trim((string) $chemin_relatif);
    if ($chemin_relatif === '' || strpos($chemin_relatif, '..') !== false) {
        return;
    }
    if (strpos($chemin_relatif, 'employes_documents/') !== 0) {
        return;
    }
    $full = __DIR__ . '/../upload/' . str_replace('/', DIRECTORY_SEPARATOR, $chemin_relatif);
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * @return array<string, string>
 */
function employe_document_mime_to_ext_map() {
    return [
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

/**
 * @return array{ok:bool, msg:string, ext?:string, mime?:string}
 */
function employe_document_inspect_joint($file) {
    if (!$file || !is_array($file)) {
        return ['ok' => false, 'msg' => 'Aucun fichier.'];
    }
    $code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'msg' => 'Sélectionnez un fichier à téléverser.'];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Le téléversement a échoué. Réessayez.'];
    }
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > EMPLOYE_DOCUMENT_UPLOAD_MAX_BYTES) {
        $doc_mo = (int) (EMPLOYE_DOCUMENT_UPLOAD_MAX_BYTES / (1024 * 1024));
        return ['ok' => false, 'msg' => 'Le document doit faire au plus ' . $doc_mo . ' Mo.'];
    }
    $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    $mime = employe_photo_mime_detect($tmp);
    $map = employe_document_mime_to_ext_map();
    if (!isset($map[$mime])) {
        return ['ok' => false, 'msg' => 'Formats acceptés : PDF, JPEG, PNG ou WebP.'];
    }
    return ['ok' => true, 'msg' => '', 'ext' => $map[$mime], 'mime' => $mime];
}

/**
 * @return array{ok:bool, msg:string, path:?string, mime?:string}
 */
function employe_document_process_upload($employe_id, $file) {
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['ok' => false, 'msg' => 'Identifiant employé invalide.', 'path' => null];
    }
    $insp = employe_document_inspect_joint($file);
    if (!$insp['ok']) {
        return ['ok' => false, 'msg' => $insp['msg'], 'path' => null];
    }
    $upload_dir_abs = __DIR__ . '/../upload/employes_documents/';
    if (!is_dir($upload_dir_abs) && !@mkdir($upload_dir_abs, 0755, true) && !is_dir($upload_dir_abs)) {
        return ['ok' => false, 'msg' => 'Impossible de préparer le dossier des documents.', 'path' => null];
    }
    $ext = $insp['ext'];
    $new_base = 'employe_' . $employe_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $abs_new = $upload_dir_abs . $new_base;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'msg' => 'Fichier téléversé invalide.', 'path' => null];
    }
    if (!move_uploaded_file($file['tmp_name'], $abs_new)) {
        return ['ok' => false, 'msg' => 'Impossible d’enregistrer le fichier sur le serveur.', 'path' => null];
    }
    $mime = isset($insp['mime']) ? (string) $insp['mime'] : '';
    return ['ok' => true, 'msg' => '', 'path' => 'employes_documents/' . $new_base, 'mime' => $mime];
}

/**
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_ajout_document_fiche($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ajouter_document'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    $nature = isset($_POST['document_nature']) ? trim((string) $_POST['document_nature']) : '';
    $nature = mb_substr($nature, 0, 255);
    if ($nature === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Indiquez la nature du document.'];
    }
    if (!get_employe_by_id($employe_id)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé introuvable.'];
    }
    $field = EMPLOYE_DOCUMENT_FIELD;
    $file = isset($_FILES[$field]) && is_array($_FILES[$field]) ? $_FILES[$field] : null;
    $up = employe_document_process_upload($employe_id, $file);
    if (!$up['ok']) {
        return ['handled' => true, 'ok' => false, 'msg' => $up['msg']];
    }
    $new_id = employe_documents_insert($employe_id, $nature, (string) $up['path'], $up['mime'] ?? null);
    if (!$new_id) {
        employe_document_delete_disk((string) $up['path']);
        return ['handled' => true, 'ok' => false, 'msg' => 'Erreur lors de l’enregistrement en base.'];
    }
    return ['handled' => true, 'ok' => true, 'msg' => 'Document ajouté.'];
}

/**
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_supprimer_document_piece_fiche($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['supprimer_document_id'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    $doc_id = isset($_POST['supprimer_document_id']) ? (int) $_POST['supprimer_document_id'] : 0;
    if ($doc_id <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Document invalide.'];
    }
    if (employe_documents_delete($doc_id, $employe_id)) {
        return ['handled' => true, 'ok' => true, 'msg' => 'Document supprimé.'];
    }
    return ['handled' => true, 'ok' => false, 'msg' => 'Impossible de supprimer ce document.'];
}

/**
 * Enregistrement d’une sanction / mesure disciplinaire sur la fiche employé.
 *
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_ajouter_sanction_fiche($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ajouter_sanction'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé invalide.'];
    }
    if (!get_employe_by_id($employe_id)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé introuvable.'];
    }
    $date_constat = isset($_POST['sanction_date_constat']) ? trim((string) $_POST['sanction_date_constat']) : '';
    $type_raw = isset($_POST['sanction_type']) ? trim((string) $_POST['sanction_type']) : '';
    $motif = isset($_POST['sanction_motif']) ? trim((string) $_POST['sanction_motif']) : '';
    $mesure = isset($_POST['sanction_mesure']) ? trim((string) $_POST['sanction_mesure']) : '';
    $commentaire = isset($_POST['sanction_commentaire']) ? trim((string) $_POST['sanction_commentaire']) : '';

    if ($date_constat === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Indiquez la date du constat ou de la décision.'];
    }
    $d = DateTime::createFromFormat('Y-m-d', $date_constat);
    if (!$d || $d->format('Y-m-d') !== $date_constat) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Date invalide.'];
    }
    if (!in_array($type_raw, employe_sanctions_types_autorises(), true)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Type de mesure non reconnu.'];
    }
    if ($motif === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Le motif (faits constatés) est obligatoire.'];
    }
    if ($mesure === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'La mesure ou la décision appliquée est obligatoire.'];
    }
    if (mb_strlen($motif, 'UTF-8') > 10000 || mb_strlen($mesure, 'UTF-8') > 10000) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Motif ou mesure trop long (10 000 caractères max).'];
    }
    if ($commentaire !== '' && mb_strlen($commentaire, 'UTF-8') > 5000) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Commentaire trop long (5 000 caractères max).'];
    }

    $admin_session = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
    $ok = employe_sanction_insert($employe_id, [
        'date_constat'  => $date_constat,
        'type_sanction' => $type_raw,
        'motif'         => $motif,
        'mesure'        => $mesure,
        'commentaire'   => $commentaire,
        'admin_id'        => $admin_session > 0 ? $admin_session : null,
    ]);
    if (!$ok) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Enregistrement impossible (vérifiez que la migration a été exécutée).'];
    }
    return ['handled' => true, 'ok' => true, 'msg' => 'Sanction enregistrée.'];
}

/**
 * Autorisation d’absence (période) sur la fiche employé.
 *
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_ajouter_autorisation_absence_fiche($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ajouter_autorisation_absence'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé invalide.'];
    }
    if (!get_employe_by_id($employe_id)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé introuvable.'];
    }
    $d1 = isset($_POST['auth_date_debut']) ? trim((string) $_POST['auth_date_debut']) : '';
    $d2 = isset($_POST['auth_date_fin']) ? trim((string) $_POST['auth_date_fin']) : '';
    $motif = isset($_POST['auth_motif']) ? trim((string) $_POST['auth_motif']) : '';
    $commentaire = isset($_POST['auth_commentaire']) ? trim((string) $_POST['auth_commentaire']) : '';

    if ($d1 === '' || $d2 === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Indiquez la date de début et la date de fin de la période autorisée.'];
    }
    $dt1 = DateTime::createFromFormat('Y-m-d', $d1);
    $dt2 = DateTime::createFromFormat('Y-m-d', $d2);
    if (!$dt1 || $dt1->format('Y-m-d') !== $d1 || !$dt2 || $dt2->format('Y-m-d') !== $d2) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Dates invalides.'];
    }
    if ($dt2 < $dt1) {
        return ['handled' => true, 'ok' => false, 'msg' => 'La date de fin doit être égale ou postérieure à la date de début.'];
    }
    if ($motif === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Le motif ou l’objet de l’autorisation est obligatoire.'];
    }
    if (mb_strlen($motif, 'UTF-8') > 10000 || mb_strlen($commentaire, 'UTF-8') > 5000) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Motif ou commentaire trop long.'];
    }

    $admin_session = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
    $ok = employe_autorisation_absence_insert($employe_id, [
        'date_debut'  => $d1,
        'date_fin'    => $d2,
        'motif'       => $motif,
        'commentaire' => $commentaire,
        'admin_id'    => $admin_session > 0 ? $admin_session : null,
    ]);
    if (!$ok) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Enregistrement impossible (vérifiez que la migration a été exécutée).'];
    }
    return ['handled' => true, 'ok' => true, 'msg' => 'Autorisation enregistrée.'];
}

/**
 * Convertit une saisie montant (virgule ou point, espaces).
 *
 * @return float|null
 */
function employe_parse_montant_saisie($raw) {
    $raw = trim(str_replace(["\xc2\xa0", ' '], '', (string) $raw));
    $raw = str_replace(',', '.', $raw);
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    $f = (float) $raw;
    if ($f <= 0 || $f > 99999999.99) {
        return null;
    }
    return round($f, 2);
}

/**
 * Déduction de prime transport (en jours) pour un mois de paie donné.
 *
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_ajouter_retrait_transport_fiche($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ajouter_retrait_transport'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé invalide.'];
    }
    if (!get_employe_by_id($employe_id)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé introuvable.'];
    }
    if (!employe_transport_tables_disponibles()) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Table des retraits transport absente — exécutez la migration dédiée.'];
    }

    $mois = trim((string) ($_POST['transport_mois_paie'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Mois de paie invalide (format AAAA-MM).'];
    }
    $jours_raw = trim((string) ($_POST['transport_nb_jours'] ?? ''));
    if ($jours_raw === '' || !ctype_digit($jours_raw)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Indiquez un nombre de jours valide.'];
    }
    $nb_jours = (int) $jours_raw;
    if ($nb_jours < 1 || $nb_jours > 31) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Le nombre de jours doit être compris entre 1 et 31.'];
    }

    $params = bp_get_parametres_effectifs();
    $jours_ref = (int) ($params['jours_presence_defaut'] ?? 0);
    $prime_mensuelle = max(0.0, (float) ($params['prime_transport_mensuelle'] ?? 0.0));
    if ($jours_ref < 1) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Paramètres bulletin : renseignez d’abord les jours de présence de référence (> 0).'];
    }
    if ($prime_mensuelle <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Paramètres bulletin : renseignez d’abord le montant mensuel de prime transport (> 0).'];
    }
    if ($nb_jours > $jours_ref) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Le nombre de jours à déduire ne peut pas dépasser la référence mensuelle (' . $jours_ref . ' jours).'];
    }

    $montant_deduit = round(($prime_mensuelle / $jours_ref) * $nb_jours, 2);
    if ($montant_deduit <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Montant déduit nul — vérifiez la configuration de la prime transport.'];
    }

    $commentaire = trim((string) ($_POST['transport_commentaire'] ?? ''));
    if (mb_strlen($commentaire, 'UTF-8') > 500) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Commentaire trop long (500 caractères max).'];
    }

    $admin_session = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
    $ok = employe_transport_retrait_insert($employe_id, [
        'mois_paie' => $mois,
        'nb_jours' => $nb_jours,
        'montant_deduit' => $montant_deduit,
        'commentaire' => $commentaire,
        'admin_id' => $admin_session > 0 ? $admin_session : null,
    ]);
    if (!$ok) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Enregistrement impossible de la déduction transport.'];
    }
    return ['handled' => true, 'ok' => true, 'msg' => 'Déduction transport enregistrée.'];
}

/**
 * Ajoute une prise de congés (consommation du quota annuel global).
 *
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_ajouter_conge_fiche($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ajouter_conge'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé invalide.'];
    }
    if (!get_employe_by_id($employe_id)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé introuvable.'];
    }
    if (!employe_conges_table_disponible()) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Table congés absente — exécutez la migration.'];
    }
    $mois = trim((string) ($_POST['conge_mois'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Mois de congé invalide (format AAAA-MM).'];
    }
    $jours_raw = trim((string) ($_POST['conge_nb_jours'] ?? ''));
    if ($jours_raw === '' || !ctype_digit($jours_raw)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Nombre de jours invalide.'];
    }
    $nb_jours = (int) $jours_raw;
    if ($nb_jours < 1 || $nb_jours > 365) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Le nombre de jours doit être entre 1 et 365.'];
    }
    $notes = trim((string) ($_POST['conge_notes'] ?? ''));
    if (mb_strlen($notes, 'UTF-8') > 1000) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Notes trop longues (1000 caractères max).'];
    }

    $params = bp_get_parametres_effectifs();
    $quota = max(0, (int) ($params['conges_annuels_global'] ?? 0));
    if ($quota <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Paramètres bulletin : renseignez un quota annuel global de congés supérieur à 0.'];
    }
    $annee = substr($mois, 0, 4);
    $totaux = employe_conges_totaux_par_annee($employe_id);
    $deja_pris = max(0, (int) ($totaux[$annee] ?? 0));
    if (($deja_pris + $nb_jours) > $quota) {
        $restant = max(0, $quota - $deja_pris);
        return ['handled' => true, 'ok' => false, 'msg' => 'Quota insuffisant : il reste ' . $restant . ' jour(s) pour ' . $annee . '.'];
    }

    $admin_session = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
    $ok = employe_conges_insert($employe_id, [
        'mois_conge' => $mois,
        'nb_jours' => $nb_jours,
        'notes' => $notes,
        'admin_id' => $admin_session > 0 ? $admin_session : null,
    ]);
    if (!$ok) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Impossible d’enregistrer le congé.'];
    }
    return ['handled' => true, 'ok' => true, 'msg' => 'Congé enregistré.'];
}

function employe_prets_statuts_autorises() {
    return ['en_cours', 'rembourse', 'annule'];
}

function employe_prets_statuts_choices() {
    return [
        'en_cours'  => 'En cours de remboursement',
        'rembourse' => 'Soldé / entièrement remboursé',
        'annule'    => 'Annulé',
    ];
}

/**
 * Enregistrement d’un prêt sur la fiche employé.
 *
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_ajouter_pret_fiche($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ajouter_pret'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé invalide.'];
    }
    if (!get_employe_by_id($employe_id)) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé introuvable.'];
    }

    $montant = employe_parse_montant_saisie(isset($_POST['pret_montant']) ? $_POST['pret_montant'] : '');
    if ($montant === null) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Indiquez un montant valide (supérieur à zéro, max 99 999 999,99).'];
    }

    $date_octroi = isset($_POST['pret_date_octroi']) ? trim((string) $_POST['pret_date_octroi']) : '';
    if ($date_octroi === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Indiquez la date d’octroi du prêt.'];
    }
    $dt_o = DateTime::createFromFormat('Y-m-d', $date_octroi);
    if (!$dt_o || $dt_o->format('Y-m-d') !== $date_octroi) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Date d’octroi invalide.'];
    }

    $fin_raw = isset($_POST['pret_date_fin_prevue']) ? trim((string) $_POST['pret_date_fin_prevue']) : '';
    $date_fin_prevue = null;
    if ($fin_raw !== '') {
        $dt_f = DateTime::createFromFormat('Y-m-d', $fin_raw);
        if (!$dt_f || $dt_f->format('Y-m-d') !== $fin_raw) {
            return ['handled' => true, 'ok' => false, 'msg' => 'Date de fin prévue invalide.'];
        }
        if ($dt_f < $dt_o) {
            return ['handled' => true, 'ok' => false, 'msg' => 'La date de fin prévue ne peut pas être antérieure à la date d’octroi.'];
        }
        $date_fin_prevue = $fin_raw;
    }

    $mensualite = null;
    $mens_raw = isset($_POST['pret_mensualite']) ? trim((string) $_POST['pret_mensualite']) : '';
    if ($mens_raw !== '') {
        $mensualite = employe_parse_montant_saisie($mens_raw);
        if ($mensualite === null) {
            return ['handled' => true, 'ok' => false, 'msg' => 'Mensualité invalide.'];
        }
        if ($mensualite > $montant) {
            return ['handled' => true, 'ok' => false, 'msg' => 'La mensualité ne peut pas dépasser le montant du prêt.'];
        }
    }

    $motif = isset($_POST['pret_motif']) ? trim((string) $_POST['pret_motif']) : '';
    if ($motif === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Le motif ou l’objet du prêt est obligatoire.'];
    }
    if (mb_strlen($motif, 'UTF-8') > 10000) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Motif trop long.'];
    }

    $statut = isset($_POST['pret_statut']) ? trim((string) $_POST['pret_statut']) : 'en_cours';
    if (!in_array($statut, employe_prets_statuts_autorises(), true)) {
        $statut = 'en_cours';
    }

    $commentaire = isset($_POST['pret_commentaire']) ? trim((string) $_POST['pret_commentaire']) : '';
    if (mb_strlen($commentaire, 'UTF-8') > 5000) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Commentaire trop long.'];
    }

    $admin_session = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
    $ok = employe_pret_insert($employe_id, [
        'montant'         => $montant,
        'date_octroi'     => $date_octroi,
        'date_fin_prevue' => $date_fin_prevue,
        'mensualite'      => $mensualite,
        'motif'           => $motif,
        'statut'          => $statut,
        'commentaire'     => $commentaire,
        'admin_id'        => $admin_session > 0 ? $admin_session : null,
    ]);
    if (!$ok) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Enregistrement impossible (vérifiez que la migration a été exécutée).'];
    }
    return ['handled' => true, 'ok' => true, 'msg' => 'Prêt enregistré.'];
}

/**
 * Enregistrement d’un versement / remboursement sur un prêt.
 *
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_ajouter_remboursement_pret_fiche($employe_id) {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ajouter_remboursement_pret'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    $pret_id = isset($_POST['pret_remboursement_pret_id']) ? (int) $_POST['pret_remboursement_pret_id'] : 0;
    if ($employe_id <= 0 || $pret_id <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Données invalides.'];
    }
    $pret = employe_pret_get_by_id_for_employe($pret_id, $employe_id);
    if (!$pret) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Prêt introuvable.'];
    }
    $statut_pret = (string) ($pret['statut'] ?? '');
    if ($statut_pret === 'annule') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Impossible d’enregistrer un versement sur un prêt annulé.'];
    }

    $montant_pret = round((float) ($pret['montant'] ?? 0), 2);
    $deja = round((float) ($pret['montant_verse'] ?? 0), 2);
    $reste = max(0, round($montant_pret - $deja, 2));

    if ($reste <= 0.005) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Il n’y a plus de solde à rembourser sur ce prêt.'];
    }

    $mnt = employe_parse_montant_saisie(isset($_POST['pret_remb_montant']) ? $_POST['pret_remb_montant'] : '');
    if ($mnt === null) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Indiquez un montant de versement valide.'];
    }
    if ($mnt - $reste > 0.009) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Le versement dépasse le reste à payer (' . number_format($reste, 2, ',', ' ') . ' FCFA).'];
    }

    $d = isset($_POST['pret_remb_date']) ? trim((string) $_POST['pret_remb_date']) : '';
    if ($d === '') {
        return ['handled' => true, 'ok' => false, 'msg' => 'Indiquez la date du versement.'];
    }
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Date du versement invalide.'];
    }

    $commentaire = isset($_POST['pret_remb_commentaire']) ? trim((string) $_POST['pret_remb_commentaire']) : '';
    if (mb_strlen($commentaire, 'UTF-8') > 5000) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Commentaire trop long.'];
    }

    $admin_session = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;

    if (!$db) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Erreur de connexion.'];
    }
    try {
        $db->beginTransaction();
        if (!employe_pret_remboursement_insert($pret_id, [
            'montant'        => $mnt,
            'date_versement' => $d,
            'commentaire'    => $commentaire,
            'admin_id'       => $admin_session > 0 ? $admin_session : null,
        ])) {
            $db->rollBack();
            return ['handled' => true, 'ok' => false, 'msg' => 'Enregistrement du versement impossible.'];
        }
        employe_pret_actualiser_statut_solde($pret_id);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['handled' => true, 'ok' => false, 'msg' => 'Erreur lors de l’enregistrement.'];
    }

    return ['handled' => true, 'ok' => true, 'msg' => 'Versement enregistré.'];
}

/**
 * @return array{ok:bool, msg:string}
 */
function employe_contrat_pdf_inspect_joint($file) {
    $empty = ['ok' => true, 'msg' => ''];
    if (!$file || !is_array($file)) {
        return $empty;
    }
    $code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return $empty;
    }
    if ($code !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Le téléversement du PDF a échoué. Réessayez.'];
    }
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > EMPLOYE_CONTRAT_PDF_UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'msg' => 'Le PDF du contrat doit faire au plus 8 Mo.'];
    }
    $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    $mime = employe_photo_mime_detect($tmp);
    $pdf_mimes = ['application/pdf', 'application/x-pdf'];
    if (!in_array($mime, $pdf_mimes, true)) {
        return ['ok' => false, 'msg' => 'Le contrat doit être un fichier PDF.'];
    }
    return ['ok' => true, 'msg' => ''];
}

/**
 * Enregistre le PDF contrat (remplace les anciens du même employé).
 *
 * @return array{ok:bool, msg:string, path:?string, changed:bool}
 */
function employe_contrat_pdf_process_for_employe($employe_id, $file) {
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['ok' => false, 'msg' => 'Identifiant employé invalide.', 'path' => null, 'changed' => false];
    }
    $code = is_array($file) && isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'msg' => '', 'path' => null, 'changed' => false];
    }
    $insp = employe_contrat_pdf_inspect_joint($file);
    if (!$insp['ok']) {
        return ['ok' => false, 'msg' => $insp['msg'], 'path' => null, 'changed' => false];
    }
    $upload_dir_abs = __DIR__ . '/../upload/employes_contrats/';
    if (!is_dir($upload_dir_abs) && !@mkdir($upload_dir_abs, 0755, true) && !is_dir($upload_dir_abs)) {
        return ['ok' => false, 'msg' => 'Impossible de préparer le dossier des contrats.', 'path' => null, 'changed' => false];
    }
    $new_base = 'employe_' . $employe_id . '_' . bin2hex(random_bytes(5)) . '.pdf';
    $abs_new = $upload_dir_abs . $new_base;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'msg' => 'Fichier téléversé invalide.', 'path' => null, 'changed' => false];
    }
    foreach (glob($upload_dir_abs . 'employe_' . $employe_id . '_*.pdf') ?: [] as $old_abs) {
        if (is_file($old_abs)) {
            @unlink($old_abs);
        }
    }
    if (!move_uploaded_file($file['tmp_name'], $abs_new)) {
        return ['ok' => false, 'msg' => 'Impossible d’enregistrer le PDF sur le serveur.', 'path' => null, 'changed' => false];
    }
    $relatif = 'employes_contrats/' . $new_base;
    return ['ok' => true, 'msg' => '', 'path' => $relatif, 'changed' => true];
}

/**
 * Gestion contrat PDF en modification : retrait, conservation ou nouveau fichier.
 *
 * @return array{ok:bool, msg:string, path:?string}
 */
function employe_contrat_pdf_resolve_modification($employe_id, $file, $current_rel) {
    $employe_id = (int) $employe_id;
    $retirer = !empty($_POST['retirer_contrat_pdf']);
    $current_rel = is_string($current_rel) && trim($current_rel) !== '' ? trim($current_rel) : null;

    if ($retirer) {
        if ($current_rel) {
            employe_contrat_pdf_delete_disk($current_rel);
        }
        return ['ok' => true, 'msg' => '', 'path' => null];
    }

    $insp = employe_contrat_pdf_inspect_joint($file);
    if (!$insp['ok']) {
        return ['ok' => false, 'msg' => $insp['msg'], 'path' => $current_rel];
    }
    $code = is_array($file) && isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'msg' => '', 'path' => $current_rel];
    }
    $r = employe_contrat_pdf_process_for_employe($employe_id, $file);
    if (!$r['ok']) {
        return ['ok' => false, 'msg' => $r['msg'], 'path' => $current_rel];
    }
    return ['ok' => true, 'msg' => '', 'path' => $r['path']];
}

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
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'msg' => fouta_upload_image_err_ini_ou_limite(), 'ext' => ''];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Le téléversement de la photo a échoué. Réessayez.', 'ext' => ''];
    }

    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > EMPLOYE_PHOTO_UPLOAD_MAX_BYTES) {
        $mo = (int) (EMPLOYE_PHOTO_UPLOAD_MAX_BYTES / (1024 * 1024));
        return ['ok' => false, 'msg' => 'La photo doit faire au plus ' . $mo . ' Mo.', 'ext' => ''];
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

/**
 * Date d'embauche (AAAA-MM-JJ) : chaîne vide → null, invalide → false.
 *
 * @return string|null|false
 */
function employe_normalize_date_embauche_post($raw) {
    $raw = trim((string) ($raw ?? ''));
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return false;
    }
    $p = explode('-', $raw);
    $y = (int) $p[0];
    $m = (int) $p[1];
    $d = (int) $p[2];
    if (!checkdate($m, $d, $y)) {
        return false;
    }
    return $raw;
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
        'statut_familial' => isset($_POST['statut_familial']) ? trim($_POST['statut_familial']) : '',
        'type_contrat' => isset($_POST['type_contrat']) ? trim($_POST['type_contrat']) : '',
        'salaire_base' => isset($_POST['salaire_base']) ? trim((string) $_POST['salaire_base']) : '',
        'montant_irpp_mensuel' => isset($_POST['montant_irpp_mensuel']) ? trim((string) $_POST['montant_irpp_mensuel']) : '',
        'categorie_paie' => isset($_POST['categorie_paie']) ? trim((string) $_POST['categorie_paie']) : '',
        'admin_id' => isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0,
    ];
}

/**
 * Montant FCFA optionnel : chaîne vide → null, invalide → false.
 *
 * @return float|null|false
 */
function employe_parse_montant_fcfa_optionnel($raw) {
    $raw = trim((string) ($raw ?? ''));
    if ($raw === '') {
        return null;
    }
    $s = str_replace([' ', "\xc2\xa0"], '', $raw);
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) {
        return false;
    }
    return round(max(0, (float) $s), 2);
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
    $sf = isset($d['statut_familial']) ? trim((string) $d['statut_familial']) : '';
    $sf_ok = array_merge(['', 'non_renseigne'], employe_statuts_familiaux_autorises());
    if (!in_array($sf, $sf_ok, true)) {
        $err[] = 'Statut familial invalide.';
    }
    $tc = isset($d['type_contrat']) ? trim((string) $d['type_contrat']) : '';
    $tc_ok = array_merge(['', 'non_renseigne'], employe_types_contrat_autorises());
    if (!in_array($tc, $tc_ok, true)) {
        $err[] = 'Type de contrat invalide.';
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
    employe_apply_rh_champs_db($d);
    $de_norm = employe_normalize_date_embauche_post($d['date_embauche'] ?? '');
    if ($de_norm === false) {
        return ['success' => false, 'message' => 'Date d’embauche invalide.'];
    }
    $d['date_embauche'] = $de_norm;
    $sb = employe_parse_montant_fcfa_optionnel($d['salaire_base'] ?? '');
    if ($sb === false) {
        return ['success' => false, 'message' => 'Salaire de base invalide.'];
    }
    $d['salaire_base'] = $sb;
    $irpp_m = employe_parse_montant_fcfa_optionnel($d['montant_irpp_mensuel'] ?? '');
    if ($irpp_m === false) {
        return ['success' => false, 'message' => 'Montant IRPP invalide.'];
    }
    $d['montant_irpp_mensuel'] = $irpp_m;
    $cat = mb_substr((string) ($d['categorie_paie'] ?? ''), 0, 120);
    $d['categorie_paie'] = $cat !== '' ? $cat : null;
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
    $fpdf = isset($_FILES[EMPLOYE_CONTRAT_PDF_FIELD]) && is_array($_FILES[EMPLOYE_CONTRAT_PDF_FIELD]) ? $_FILES[EMPLOYE_CONTRAT_PDF_FIELD] : null;
    $pdf_insp = employe_contrat_pdf_inspect_joint($fpdf);
    if (!$pdf_insp['ok']) {
        $err[] = $pdf_insp['msg'];
    }
    if (!empty($err)) {
        return ['success' => false, 'message' => implode('<br>', $err)];
    }
    employe_apply_rh_champs_db($d);
    $de_norm = employe_normalize_date_embauche_post($d['date_embauche'] ?? '');
    if ($de_norm === false) {
        return ['success' => false, 'message' => 'Date d’embauche invalide.'];
    }
    $d['date_embauche'] = $de_norm;
    $sb = employe_parse_montant_fcfa_optionnel($d['salaire_base'] ?? '');
    if ($sb === false) {
        return ['success' => false, 'message' => 'Salaire de base invalide.'];
    }
    $d['salaire_base'] = $sb;
    $irpp_m = employe_parse_montant_fcfa_optionnel($d['montant_irpp_mensuel'] ?? '');
    if ($irpp_m === false) {
        return ['success' => false, 'message' => 'Montant IRPP invalide.'];
    }
    $d['montant_irpp_mensuel'] = $irpp_m;
    $cat = mb_substr((string) ($d['categorie_paie'] ?? ''), 0, 120);
    $d['categorie_paie'] = $cat !== '' ? $cat : null;
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
    $pdf_piece = '';
    $pdf_done = employe_contrat_pdf_process_for_employe((int) $id, $fpdf);
    if (!$pdf_done['ok']) {
        delete_employe((int) $id);
        return ['success' => false, 'message' => $pdf_done['msg']];
    }
    if (!empty($pdf_done['changed']) && !empty($pdf_done['path'])) {
        if (!employe_set_contrat_pdf_chemin((int) $id, $pdf_done['path'])) {
            delete_employe((int) $id);
            return ['success' => false, 'message' => 'Erreur d’enregistrement du contrat PDF.'];
        }
        $pdf_piece = ' Contrat PDF ajouté.';
    }
    return ['success' => true, 'message' => 'Employé enregistré avec son badge QR.' . $mat_piece . $photo_piece . $pdf_piece, 'id' => $id];
}

function process_employe_modification($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['modifier_employe'])) {
        return ['success' => false, 'message' => ''];
    }
    $ex = get_employe_by_id($employe_id);
    if (!$ex) {
        return ['success' => false, 'message' => 'Employé introuvable.'];
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
    $fpdf = isset($_FILES[EMPLOYE_CONTRAT_PDF_FIELD]) && is_array($_FILES[EMPLOYE_CONTRAT_PDF_FIELD]) ? $_FILES[EMPLOYE_CONTRAT_PDF_FIELD] : null;
    $pdf_insp = employe_contrat_pdf_inspect_joint($fpdf);
    if (!$pdf_insp['ok']) {
        $err[] = $pdf_insp['msg'];
    }
    if (!empty($err)) {
        return ['success' => false, 'message' => implode('<br>', $err)];
    }
    employe_apply_rh_champs_db($d);
    $de_norm = employe_normalize_date_embauche_post($d['date_embauche'] ?? '');
    if ($de_norm === false) {
        return ['success' => false, 'message' => 'Date d’embauche invalide.'];
    }
    $d['date_embauche'] = $de_norm;
    $sb = employe_parse_montant_fcfa_optionnel($d['salaire_base'] ?? '');
    if ($sb === false) {
        return ['success' => false, 'message' => 'Salaire de base invalide.'];
    }
    $d['salaire_base'] = $sb;
    $irpp_m = employe_parse_montant_fcfa_optionnel($d['montant_irpp_mensuel'] ?? '');
    if ($irpp_m === false) {
        return ['success' => false, 'message' => 'Montant IRPP invalide.'];
    }
    $d['montant_irpp_mensuel'] = $irpp_m;
    $cat = mb_substr((string) ($d['categorie_paie'] ?? ''), 0, 120);
    $d['categorie_paie'] = $cat !== '' ? $cat : null;
    $curr_pdf = isset($ex['contrat_pdf_chemin']) && trim((string) $ex['contrat_pdf_chemin']) !== ''
        ? trim((string) $ex['contrat_pdf_chemin']) : null;
    $pdf_res = employe_contrat_pdf_resolve_modification($employe_id, $fpdf, $curr_pdf);
    if (!$pdf_res['ok']) {
        return ['success' => false, 'message' => $pdf_res['msg']];
    }
    $d['contrat_pdf_chemin'] = $pdf_res['path'];
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
        if ($fpdf && (int) ($fpdf['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) {
            $suffix .= ' Contrat PDF mis à jour.';
        } elseif (!empty($_POST['retirer_contrat_pdf'])) {
            $suffix .= ' Contrat PDF retiré.';
        }
        return ['success' => true, 'message' => 'Fiche mise à jour (badge QR régénéré).' . $suffix];
    }
    return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
}

/**
 * Retrait du PDF contrat depuis la fiche détail (POST + CSRF vérifié par la page).
 *
 * @return array{handled:bool, ok:bool, msg:string}
 */
function employe_process_supprimer_contrat_pdf_fiche($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['supprimer_contrat_pdf'])) {
        return ['handled' => false, 'ok' => false, 'msg' => ''];
    }
    $employe_id = (int) $employe_id;
    if ($employe_id <= 0) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Identifiant invalide.'];
    }
    $ex = get_employe_by_id($employe_id);
    if (!$ex) {
        return ['handled' => true, 'ok' => false, 'msg' => 'Employé introuvable.'];
    }
    $rel = trim((string) ($ex['contrat_pdf_chemin'] ?? ''));
    if ($rel === '') {
        return ['handled' => true, 'ok' => true, 'msg' => 'Aucun contrat enregistré.'];
    }
    employe_contrat_pdf_delete_disk($rel);
    if (employe_set_contrat_pdf_chemin($employe_id, null)) {
        return ['handled' => true, 'ok' => true, 'msg' => 'Contrat PDF retiré.'];
    }
    return ['handled' => true, 'ok' => false, 'msg' => 'Impossible de mettre à jour la fiche.'];
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
