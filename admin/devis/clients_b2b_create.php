<?php
/**
 * Création rapide d'un client B2B (POST) puis redirection vers index (modal BL)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis_bl()) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=bl');
    exit;
}

$rs = trim($_POST['raison_sociale'] ?? '');
$tel = trim($_POST['telephone'] ?? '');

if ($rs === '' || $tel === '') {
    $_SESSION['bl_erreur'] = 'Raison sociale et téléphone sont requis pour créer un client B2B.';
    header('Location: index.php?modal=bl&tab=bl');
    exit;
}

require_once __DIR__ . '/../../models/model_clients_b2b.php';
$id = create_client_b2b([
    'raison_sociale' => $rs,
    'nom_contact' => '',
    'prenom_contact' => '',
    'email' => null,
    'telephone' => $tel,
    'adresse' => null,
    'notes' => 'Création rapide depuis BL',
    'statut' => 'actif',
    'admin_createur_id' => (int) ($_SESSION['admin_id'] ?? 0),
]);

if ($id) {
    $_SESSION['success_message'] = 'Client B2B créé. Vous pouvez maintenant établir le BL.';
    header('Location: index.php?modal=bl&tab=bl&client=' . (int) $id);
    exit;
}

$_SESSION['bl_erreur'] = 'Impossible de créer le client.';
header('Location: index.php?modal=bl&tab=bl');
exit;
