<?php
/**
 * Contrôleurs fournisseurs (ajout depuis l’admin)
 */
require_once __DIR__ . '/../models/model_fournisseurs.php';

/**
 * Vérifie le jeton CSRF admin envoyé en POST.
 */
function admin_fournisseur_verify_csrf()
{
    $t = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $expected = isset($_SESSION['admin_csrf']) ? (string) $_SESSION['admin_csrf'] : '';
    return $t !== '' && $expected !== '' && hash_equals($expected, $t);
}

/**
 * Traite POST add_fournisseur (nom uniquement).
 *
 * @return array{success:bool, message:string}
 */
function process_admin_add_fournisseur()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['add_fournisseur'])) {
        return ['success' => false, 'message' => ''];
    }
    if (!admin_fournisseur_verify_csrf()) {
        return ['success' => false, 'message' => 'Session expirée ou jeton invalide. Rechargez la page.'];
    }
    $nom = isset($_POST['fournisseur_nom']) ? (string) $_POST['fournisseur_nom'] : '';
    $r = create_fournisseur_row($nom);
    if (!$r['success']) {
        return ['success' => false, 'message' => $r['message']];
    }
    return ['success' => true, 'message' => $r['message']];
}
