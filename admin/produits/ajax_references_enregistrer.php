<?php
/**
 * ENREGISTRER LES DEUX RÉFÉRENCES D'UNE PIÈCE (JSON) : OEM et fournisseur.
 *
 * POURQUOI (12/09/2026, demande de la direction) : ce sont les deux références
 * qui s'impriment sur l'étiquette et par lesquelles on retrouve la pièce au
 * comptoir. L'infographiste, qui prépare l'étiquette et cherche les images à
 * partir de l'OEM, doit pouvoir les corriger depuis SON éditeur — sans passer
 * par la fiche complète, qui montre le prix et le stock (hors de son périmètre).
 *
 * N'ÉCRIT QUE CES DEUX COLONNES : un UPDATE ciblé, JAMAIS update_produit()
 * (qui réécrirait nom, prix, stock, catégorie). La référence FPL est
 * recalculée derrière : elle porte les 6 chiffres de l'OEM (règle direction).
 *
 * POST : id, reference_oem, reference_fournisseur
 *        _jeton (ou en-tête X-CSRF-TOKEN) : jeton de session admin_csrf
 * Programmation procédurale uniquement.
 */

session_start();

require_once __DIR__ . '/../includes/require_access_json.php';
require_once __DIR__ . '/../../conn/conn.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_produits.php';

header('Content-Type: application/json; charset=utf-8');

$repondre = function (array $x) {
    echo json_encode($x, JSON_UNESCAPED_UNICODE);
    exit;
};

if (!function_exists('admin_can_modifier_references_piece') || !admin_can_modifier_references_piece()) {
    http_response_code(403);
    $repondre(['ok' => false, 'error' => "Votre profil ne modifie pas les références."]);
}

/* CSRF : en-tête X-CSRF-TOKEN (comme les autres AJAX) ou champ _jeton. */
$jeton = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN']
    : (isset($_POST['_jeton']) ? (string) $_POST['_jeton'] : '');
if (empty($_SESSION['admin_csrf']) || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
    http_response_code(419);
    $repondre(['ok' => false, 'error' => 'Session expirée, rechargez la page.']);
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    $repondre(['ok' => false, 'error' => 'Pièce inconnue.']);
}

$avant = null;
try {
    $st = $db->prepare("SELECT id, reference_oem, reference_fournisseur FROM produits WHERE id = :id AND sync_deleted_at IS NULL");
    $st->execute([':id' => $id]);
    $avant = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    $avant = null;
}
if ($avant === null) {
    $repondre(['ok' => false, 'error' => 'Pièce introuvable.']);
}

/* On garde ce qui est tapé, en enlevant seulement les espaces en trop, et on
   respecte la taille des colonnes (OEM 100, fournisseur 120). */
$propre = function ($valeur, $taille) {
    $v = trim(preg_replace('/\s+/u', ' ', (string) $valeur));
    return $v === '' ? null : mb_substr($v, 0, $taille);
};
$oem = $propre(isset($_POST['reference_oem']) ? $_POST['reference_oem'] : '', 100);
$fournisseur = $propre(isset($_POST['reference_fournisseur']) ? $_POST['reference_fournisseur'] : '', 120);

$sets = [];
$params = [':id' => $id];
if (produits_has_column('reference_oem')) {
    $sets[] = 'reference_oem = :oem';
    $params[':oem'] = $oem;
}
if (produits_has_column('reference_fournisseur')) {
    $sets[] = 'reference_fournisseur = :rf';
    $params[':rf'] = $fournisseur;
}
if ($sets === []) {
    $repondre(['ok' => false, 'error' => 'Ces colonnes n existent pas sur ce serveur.']);
}
if (produits_has_column('date_modification')) {
    $sets[] = 'date_modification = NOW()';
}
/* La synchro vers le site public doit voir la correction (marque explicite,
   comme partout ailleurs : ceinture et bretelles avec les déclencheurs). */
if (produits_has_column('sync_updated_at')) {
    $sets[] = 'sync_updated_at = NOW()';
}
if (produits_has_column('admin_dernier_modificateur_id') && !empty($_SESSION['admin_id'])) {
    $sets[] = 'admin_dernier_modificateur_id = :admin_id';
    $params[':admin_id'] = (int) $_SESSION['admin_id'];
}

try {
    $st = $db->prepare("UPDATE produits SET " . implode(', ', $sets) . " WHERE id = :id AND sync_deleted_at IS NULL");
    $st->execute($params);
} catch (PDOException $e) {
    http_response_code(500);
    $repondre(['ok' => false, 'error' => "L'enregistrement a échoué."]);
}

/* La référence FPL porte les 6 chiffres de l'OEM : elle se refait ici. */
if (function_exists('produit_reference_fpl_apres_maj')) {
    produit_reference_fpl_apres_maj($id);
}
/* Le catalogue en cache de la caisse repart de la base (sinon l'ancienne
   référence resterait affichée au comptoir jusqu'à l'expiration du cache). */
$modele_caisse = __DIR__ . '/../../models/model_caisse.php';
if (is_file($modele_caisse)) {
    require_once $modele_caisse;
    if (function_exists('caisse_catalog_live_cache_invalidate')) {
        caisse_catalog_live_cache_invalidate();
    }
}

$apres = ['reference_oem' => $oem, 'reference_fournisseur' => $fournisseur, 'reference_fpl' => ''];
try {
    $st = $db->prepare("SELECT reference_oem, reference_fournisseur, reference_fpl, identifiant_interne FROM produits WHERE id = :id");
    $st->execute([':id' => $id]);
    $ligne = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($ligne !== []) {
        $apres = [
            'reference_oem' => (string) ($ligne['reference_oem'] ?? ''),
            'reference_fournisseur' => (string) ($ligne['reference_fournisseur'] ?? ''),
            'reference_fpl' => (string) ($ligne['reference_fpl'] ?? $ligne['identifiant_interne'] ?? ''),
        ];
    }
} catch (PDOException $e) {
    // la mise à jour a réussi ; on rend au moins ce qui vient d'être écrit
}

$repondre([
    'ok' => true,
    'oem_avant' => (string) ($avant['reference_oem'] ?? ''),
    'reference_oem' => $apres['reference_oem'],
    'reference_fournisseur' => $apres['reference_fournisseur'],
    'reference_fpl' => $apres['reference_fpl'],
]);
