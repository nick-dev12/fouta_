<?php
/**
 * Contrôleurs marques (paramètres admin)
 */
require_once __DIR__ . '/../models/model_marques.php';

function admin_marque_verify_csrf() {
    $t = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $expected = isset($_SESSION['admin_csrf']) ? (string) $_SESSION['admin_csrf'] : '';
    return $t !== '' && $expected !== '' && hash_equals($expected, $t);
}

/**
 * @return array{success:bool, message:string}
 */
function process_admin_add_marque() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['add_marque'])) {
        return ['success' => false, 'message' => ''];
    }
    if (!admin_marque_verify_csrf()) {
        return ['success' => false, 'message' => 'Session expirée ou jeton invalide. Rechargez la page.'];
    }
    $nom = isset($_POST['marque_nom']) ? (string) $_POST['marque_nom'] : '';
    $r = create_marque_row($nom);
    if (!$r['success']) {
        return ['success' => false, 'message' => $r['message']];
    }
    return ['success' => true, 'message' => $r['message']];
}

/**
 * @return array{success:bool, message:string}
 */
function process_admin_update_marque() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['update_marque'])) {
        return ['success' => false, 'message' => ''];
    }
    if (!admin_marque_verify_csrf()) {
        return ['success' => false, 'message' => 'Session expirée ou jeton invalide. Rechargez la page.'];
    }
    $id = isset($_POST['marque_id']) ? (int) $_POST['marque_id'] : 0;
    $nom = isset($_POST['marque_nom']) ? (string) $_POST['marque_nom'] : '';
    $r = update_marque_row($id, $nom);
    return ['success' => $r['success'], 'message' => $r['message']];
}
