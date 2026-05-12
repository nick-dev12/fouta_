<?php
/**
 * Contrôleurs fournisseurs (ajout / modification depuis l’admin)
 */
require_once __DIR__ . '/../models/model_fournisseurs.php';

/**
 * Vérifie le jeton CSRF admin envoyé en POST.
 */
function admin_fournisseur_verify_csrf() {
    $t = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $expected = isset($_SESSION['admin_csrf']) ? (string) $_SESSION['admin_csrf'] : '';
    return $t !== '' && $expected !== '' && hash_equals($expected, $t);
}

/**
 * @return array{success:bool, message:string}
 */
function process_admin_add_fournisseur() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['add_fournisseur'])) {
        return ['success' => false, 'message' => ''];
    }
    if (!admin_fournisseur_verify_csrf()) {
        return ['success' => false, 'message' => 'Session expirée ou jeton invalide. Rechargez la page.'];
    }
    $nom = isset($_POST['fournisseur_nom']) ? (string) $_POST['fournisseur_nom'] : '';
    $tel = isset($_POST['fournisseur_telephone']) ? (string) $_POST['fournisseur_telephone'] : '';
    $email = isset($_POST['fournisseur_email']) ? (string) $_POST['fournisseur_email'] : '';
    $r = create_fournisseur_row($nom, $tel, $email);
    if (!$r['success']) {
        return ['success' => false, 'message' => $r['message']];
    }
    return ['success' => true, 'message' => $r['message']];
}

/**
 * @return array{success:bool, message:string}
 */
function process_admin_update_fournisseur() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['update_fournisseur'])) {
        return ['success' => false, 'message' => ''];
    }
    if (!admin_fournisseur_verify_csrf()) {
        return ['success' => false, 'message' => 'Session expirée ou jeton invalide. Rechargez la page.'];
    }
    $id = isset($_POST['fournisseur_id']) ? (int) $_POST['fournisseur_id'] : 0;
    $nom = isset($_POST['fournisseur_nom']) ? (string) $_POST['fournisseur_nom'] : '';
    $tel = isset($_POST['fournisseur_telephone']) ? (string) $_POST['fournisseur_telephone'] : '';
    $email = isset($_POST['fournisseur_email']) ? (string) $_POST['fournisseur_email'] : '';
    $r = update_fournisseur_row($id, $nom, $tel, $email);
    return ['success' => $r['success'], 'message' => $r['message']];
}
