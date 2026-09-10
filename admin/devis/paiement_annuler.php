<?php
/**
 * ANNULER UN PAIEMENT DE FACTURE (10/09/2026). Rien ne s'efface : le paiement
 * reste visible, barré, avec son motif et son auteur ; la facture redevient
 * impayée si ce qui reste n'est plus couvert. Les règles vivent dans
 * paiement_facture_annuler().
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

$retours = [
    'facture_devis' => 'facture.php?id=',
    'bl' => 'bl_facture.php?id=',
    'facture_mensuelle' => 'facture_mensuelle.php?id=',
];
$retour_vers = static function ($type, $id) use ($retours) {
    return (isset($retours[$type]) && (int) $id > 0) ? $retours[$type] . (int) $id : 'devis.php';
};
$cle_erreur = static function ($type) {
    return $type === 'facture_mensuelle' ? 'fm_erreur' : 'flash_facture_error';
};
$type = (string) ($_POST['type'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

if (!admin_can_enregistrer_paiement_facture()) {
    $_SESSION[$cle_erreur($type)] = 'L’annulation d’un paiement est réservée à la comptabilité.';
    header('Location: ' . $retour_vers($type, $id));
    exit;
}

$jeton_recu = (string) ($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $jeton_recu === ''
    || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton_recu)) {
    $_SESSION[$cle_erreur($type)] = 'Demande refusée : annulez le paiement depuis la facture.';
    header('Location: ' . $retour_vers($type, $id));
    exit;
}

require_once __DIR__ . '/../../models/model_paiements_factures.php';
$resultat = paiement_facture_annuler((int) ($_POST['paiement_id'] ?? 0), (int) $_SESSION['admin_id'], (string) ($_POST['motif'] ?? ''));
$type = (string) ($resultat['type'] ?? $type);
$id = (int) ($resultat['id'] ?? $id);

if (empty($resultat['ok'])) {
    $_SESSION[$cle_erreur($type)] = $resultat['error'] ?? 'L’annulation n’a pas pu être enregistrée.';
    header('Location: ' . $retour_vers($type, $id));
    exit;
}

$_SESSION['success_message'] = !empty($resultat['rouverte'])
    ? 'Paiement annulé : la facture redevient impayée, reste ' . number_format((float) $resultat['reste'], 0, ',', ' ') . ' FCFA.'
    : 'Paiement annulé.';
header('Location: ' . $retour_vers($type, $id));
exit;
