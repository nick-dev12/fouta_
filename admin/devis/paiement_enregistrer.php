<?php
/**
 * ENREGISTRER UN PAIEMENT DE FACTURE (10/09/2026) : montant, moyen, date de
 * réception, référence et auteur. Sert la facture de devis, la facture d'un bon
 * de livraison et la facture mensuelle. Les règles vivent dans
 * paiement_facture_enregistrer() ; cette page contrôle le droit et le jeton.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

$type = (string) ($_POST['type'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$retours = [
    'facture_devis' => 'facture.php?id=',
    'bl' => 'bl_facture.php?id=',
    'facture_mensuelle' => 'facture_mensuelle.php?id=',
];
$retour = (isset($retours[$type]) && $id > 0) ? $retours[$type] . $id : 'devis.php';
$cle_erreur = $type === 'facture_mensuelle' ? 'fm_erreur' : 'flash_facture_error';

if (!admin_can_enregistrer_paiement_facture()) {
    $_SESSION[$cle_erreur] = 'Le paiement d’une facture est enregistré par la comptabilité.';
    header('Location: ' . $retour);
    exit;
}

$jeton_recu = (string) ($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $jeton_recu === ''
    || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton_recu)) {
    $_SESSION[$cle_erreur] = 'Demande refusée : enregistrez le paiement depuis la facture.';
    header('Location: ' . $retour);
    exit;
}

require_once __DIR__ . '/../../models/model_paiements_factures.php';
$resultat = paiement_facture_enregistrer(
    $type,
    $id,
    (string) ($_POST['montant'] ?? ''),
    (string) ($_POST['mode_paiement'] ?? ''),
    (string) ($_POST['date_paiement'] ?? ''),
    (string) ($_POST['reference'] ?? ''),
    (string) ($_POST['notes'] ?? ''),
    (int) $_SESSION['admin_id']
);

if (empty($resultat['ok'])) {
    $_SESSION[$cle_erreur] = $resultat['error'] ?? 'Le paiement n’a pas pu être enregistré.';
    header('Location: ' . $retour);
    exit;
}

$_SESSION['success_message'] = !empty($resultat['soldee'])
    ? 'Paiement enregistré : la facture est soldée.' . (!empty($resultat['numero_reference_fpl']) ? ' Référence : ' . $resultat['numero_reference_fpl'] . '.' : '')
    : 'Paiement enregistré. Reste à payer : ' . number_format((float) $resultat['reste'], 0, ',', ' ') . ' FCFA.';
header('Location: ' . $retour);
exit;
