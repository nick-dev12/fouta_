<?php
/**
 * Contrôleur — fiches employés
 */
require_once __DIR__ . '/../models/model_employes.php';

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
    if ($id) {
        return ['success' => true, 'message' => 'Employé enregistré.', 'id' => $id];
    }
    return ['success' => false, 'message' => 'Erreur lors de l’enregistrement.'];
}

function process_employe_modification($employe_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['modifier_employe'])) {
        return ['success' => false, 'message' => ''];
    }
    $d = employe_collect_post();
    $err = employe_valider($d);
    if (!empty($err)) {
        return ['success' => false, 'message' => implode('<br>', $err)];
    }
    if (update_employe($employe_id, $d)) {
        return ['success' => true, 'message' => 'Fiche mise à jour.'];
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
