<?php
/**
 * Enregistrement d'une sous-catégorie (POST) — page stock ou fiches produit.
 */
session_start();

if (!isset($_SESSION['admin_id'], $_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';
require_once __DIR__ . '/../../models/model_categories.php';

/**
 * @param string $raw chemin relatif autorisé (depuis admin/categories/ pour ../stock/… ou nom de page produits)
 */
function admin_sous_categorie_sanitize_return_url($raw)
{
    $u = trim((string) $raw);
    if (preg_match('#^\.\./stock/index\.php(\?[-a-zA-Z0-9_&.=%]*)?$#', $u) === 1) {
        return $u;
    }
    if (preg_match('#^\.\./stock/sous-categories/index\.php(\?[-a-zA-Z0-9_&.=%]*)?$#', $u) === 1) {
        return $u;
    }
    if ($u === '' || preg_match('#^(ajouter\.php|modifier\.php)(\?[-a-zA-Z0-9_&.=%]+)?$#', $u) !== 1) {
        return '../produits/ajouter.php';
    }
    return '../produits/' . $u;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');
if ($token === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $token)) {
    $_SESSION['produit_form_notice'] = 'Session expirée (sécurité). Réessayez.';
    header('Location: ' . admin_sous_categorie_sanitize_return_url($_POST['return_url'] ?? ''));
    exit;
}

$nom = isset($_POST['nom']) ? trim((string) $_POST['nom']) : '';
$categorie_id = isset($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : 0;
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
$return_url = admin_sous_categorie_sanitize_return_url($_POST['return_url'] ?? '');

if ($nom === '' || $categorie_id <= 0 || !get_categorie_by_id($categorie_id)) {
    $_SESSION['produit_form_notice'] = 'Nom et catégorie valides requis pour la sous-catégorie.';
    header('Location: ' . $return_url);
    exit;
}

if (!sous_categories_table_ok()) {
    $_SESSION['produit_form_notice'] = 'Table sous_categories absente. Exécutez : php migrations/run_create_sous_categories_prix_achat.php';
    header('Location: ' . $return_url);
    exit;
}

$new_id = create_sous_categorie([
    'nom' => $nom,
    'categorie_id' => $categorie_id,
    'description' => $description !== '' ? $description : null,
]);

if ($new_id) {
    $_SESSION['produit_form_notice'] = 'Sous-catégorie enregistrée.';
    $sep = strpos($return_url, '?') !== false ? '&' : '?';
    header('Location: ' . $return_url . $sep . 'sous_categorie_id=' . (int) $new_id);
} else {
    $_SESSION['produit_form_notice'] = 'Impossible d\'enregistrer la sous-catégorie.';
    header('Location: ' . $return_url);
}
exit;
