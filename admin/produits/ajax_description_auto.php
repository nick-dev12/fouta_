<?php
/**
 * Description suggérée (JSON) — reprise de FPL natif.
 *
 * Quand la référence OEM ou la référence fournisseur saisie est déjà connue du
 * catalogue, on renvoie la description de cette pièce-là. Ce n'est pas une
 * génération : c'est une recherche, et le champ reste modifiable à la main.
 * Aucune écriture, aucune donnée touchée.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['found' => false, 'description' => null]);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_produits.php';

header('Content-Type: application/json; charset=utf-8');

$oem = isset($_GET['oem']) ? trim((string) $_GET['oem']) : '';
$ref = isset($_GET['ref']) ? trim((string) $_GET['ref']) : '';
/* LA PIÈCE QU'ON EST EN TRAIN DE MODIFIER NE SE RÉPOND PAS À ELLE-MÊME
 * (07/09). Constat de la direction : « on change la réf. OEM et la
 * description affiche encore les anciennes données. » Voici pourquoi :
 * l'OEM neuf n'était connu de personne, la recherche se rabattait sur la
 * RÉFÉRENCE FOURNISSEUR — restée la même — et retombait sur la pièce
 * elle-même, qui se renvoyait sa PROPRE ancienne description, badgée
 * « cette référence est déjà connue ». Sur cette base, 1 740 pièces sont
 * dans ce cas. L'écran passe donc désormais l'id de la pièce ouverte, et
 * elle est exclue de la recherche. */
$exclure = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$description = null;

try {
    // La référence OEM prime : c'est celle du constructeur, la plus fiable.
    if ($oem !== '' && produits_has_column('reference_oem')) {
        $stmt = $db->prepare("SELECT description FROM produits
                               WHERE reference_oem = :v AND id <> :ex
                                 AND description IS NOT NULL AND description <> ''
                               ORDER BY id DESC LIMIT 1");
        $stmt->execute(['v' => $oem, 'ex' => $exclure]);
        $trouve = $stmt->fetchColumn();
        if ($trouve !== false) {
            $description = $trouve;
        }
    }
    /* LE REPLI SUR LA RÉFÉRENCE FOURNISSEUR N'A LIEU QUE SANS OEM (07/09) :
     * une référence constructeur saisie est SOUVERAINE. Si elle n'est connue
     * de personne, la description se compose (marque — modèle — OEM) au lieu
     * d'aller chercher celle d'une autre pièce du même fournisseur. */
    if ($description === null && $oem === '' && $ref !== '' && produits_has_column('reference_fournisseur')) {
        $stmt = $db->prepare("SELECT description FROM produits
                               WHERE reference_fournisseur = :v AND id <> :ex
                                 AND description IS NOT NULL AND description <> ''
                               ORDER BY id DESC LIMIT 1");
        $stmt->execute(['v' => $ref, 'ex' => $exclure]);
        $trouve = $stmt->fetchColumn();
        if ($trouve !== false) {
            $description = $trouve;
        }
    }
} catch (PDOException $e) {
    $description = null;
}

echo json_encode([
    'found' => $description !== null,
    'description' => $description,
], JSON_UNESCAPED_UNICODE);
