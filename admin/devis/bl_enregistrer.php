<?php
/**
 * Création BL — mêmes champs / validation que create.php (devis), puis client B2B + lignes HT
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

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['bl_erreur'] = 'Session expirée. Réessayez.';
    header('Location: index.php?modal=bl&tab=bl');
    exit;
}

$client_nom = trim($_POST['client_nom'] ?? '');
$client_prenom = trim($_POST['client_prenom'] ?? '');
$client_telephone = trim($_POST['client_telephone'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$zone_livraison_id = isset($_POST['zone_livraison_id']) && $_POST['zone_livraison_id'] !== '' && $_POST['zone_livraison_id'] !== 'custom'
    ? (int) $_POST['zone_livraison_id'] : null;
$frais_livraison = (float) ($_POST['frais_livraison'] ?? 0);

$date_bl = trim($_POST['date_bl'] ?? '');
if ($date_bl === '') {
    $date_bl = date('Y-m-d');
}
$statut = $_POST['statut'] ?? 'brouillon';
if (!in_array($statut, ['brouillon', 'valide'], true)) {
    $statut = 'brouillon';
}

$items = [];
if (!empty($_POST['lignes']) && is_array($_POST['lignes'])) {
    foreach (array_values($_POST['lignes']) as $l) {
        $produit_id = (int) ($l['produit_id'] ?? 0);
        $quantite = (int) ($l['quantite'] ?? 1);
        $prix_unitaire = (float) str_replace(',', '.', $l['prix_unitaire'] ?? '0');
        $prix_promotion = isset($l['prix_promotion']) && $l['prix_promotion'] !== '' ? (float) str_replace(',', '.', $l['prix_promotion']) : null;
        if ($produit_id > 0 && $quantite > 0 && $prix_unitaire > 0) {
            $items[] = [
                'produit_id' => $produit_id,
                'quantite' => $quantite,
                'prix_unitaire' => $prix_promotion ?? $prix_unitaire,
                'nom_produit' => isset($l['nom_produit']) ? trim($l['nom_produit']) : null,
            ];
        }
    }
}

$erreur = null;
if ($client_nom === '') {
    $erreur = 'Le nom du client est requis.';
} elseif ($client_prenom === '') {
    $erreur = 'Le prénom du client est requis.';
} elseif ($client_telephone === '') {
    $erreur = 'Le téléphone du client est requis.';
} elseif ($adresse_livraison === '') {
    $erreur = "L'adresse de livraison est requise.";
} elseif (empty($items)) {
    $erreur = 'Ajoutez au moins un produit.';
}

if ($erreur) {
    $_SESSION['bl_erreur'] = $erreur;
    $_SESSION['bl_post'] = $_POST;
    header('Location: index.php?modal=bl&tab=bl');
    exit;
}

require_once __DIR__ . '/../../models/model_contacts.php';
require_once __DIR__ . '/../../models/model_clients_b2b.php';
require_once __DIR__ . '/../../models/model_bl.php';

if (!bl_tables_available()) {
    $_SESSION['bl_erreur'] = 'Tables BL non installées.';
    $_SESSION['bl_post'] = $_POST;
    header('Location: index.php?modal=bl&tab=bl');
    exit;
}

/** Carnet « Contacts » : si ce numéro n'existe pas dans `contacts`, création automatique (nom, prénom, téléphone, email) */
ensure_contact_from_bl(
    $client_nom,
    $client_prenom,
    $client_telephone,
    $client_email !== '' ? $client_email : null
);

$client = find_client_b2b_by_telephone($client_telephone);
if (!$client) {
    $rs = trim($client_prenom . ' ' . $client_nom);
    $cid = create_client_b2b([
        'raison_sociale' => $rs !== '' ? $rs : 'Client BL',
        'nom_contact' => $client_nom,
        'prenom_contact' => $client_prenom,
        'email' => $client_email !== '' ? $client_email : null,
        'telephone' => $client_telephone,
        'adresse' => $adresse_livraison,
        'notes' => 'Créé depuis formulaire BL (identique devis)',
        'statut' => 'actif',
        'admin_createur_id' => (int) ($_SESSION['admin_id'] ?? 0),
    ]);
    if (!$cid) {
        $_SESSION['bl_erreur'] = 'Impossible de créer la fiche client B2B.';
        $_SESSION['bl_post'] = $_POST;
        header('Location: index.php?modal=bl&tab=bl');
        exit;
    }
    $client = get_client_b2b_by_id($cid);
}

$lignes = [];
foreach ($items as $it) {
    $designation = trim($it['nom_produit'] ?? '');
    if ($designation === '') {
        $designation = 'Produit';
    }
    $lignes[] = [
        'produit_id' => (int) $it['produit_id'],
        'designation' => $designation,
        'quantite' => (float) $it['quantite'],
        'prix_unitaire_ht' => (float) $it['prix_unitaire'],
    ];
}

if ($frais_livraison > 0) {
    $lignes[] = [
        'produit_id' => null,
        'designation' => 'Frais de livraison',
        'quantite' => 1,
        'prix_unitaire_ht' => $frais_livraison,
    ];
}

$res = create_bl_manuel((int) $client['id'], $date_bl, $notes !== '' ? $notes : null, $lignes, (int) $_SESSION['admin_id'], $statut);

if (!empty($res['success'])) {
    $_SESSION['success_message'] = 'Bon de livraison ' . ($res['numero_bl'] ?? '') . ' enregistré.';
    header('Location: bl_voir.php?id=' . (int) $res['bl_id']);
    exit;
}

$_SESSION['bl_erreur'] = $res['message'] ?? 'Erreur à l\'enregistrement.';
$_SESSION['bl_post'] = $_POST;
header('Location: index.php?modal=bl&tab=bl');
exit;
