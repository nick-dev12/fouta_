<?php
/**
 * L'ADRESSE GRAVÉE DANS LES ANCIENS QR (04/09/2026) — redirection permanente.
 *
 * Des étiquettes imprimées portent /stock-info.php?id=N : une étiquette collée
 * est un engagement, cette adresse continue donc de répondre — mais elle mène
 * désormais à la vitrine client /p/{ean13} (décision de la direction : le
 * contenu du scan est exclusivement destiné aux clients ; l'ancienne page
 * exposait le stock, sa valeur et le CA sans authentification).
 *
 * La redirection reste sur l'HÔTE COURANT (Location relative) : un QR imprimé
 * avec l'adresse du réseau local continue de fonctionner sur ce réseau.
 * L'ancienne page vit dans stock-info-fouta-origine.php (session admin).
 */

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$cible = '/';
if ($produit_id > 0) {
    require_once __DIR__ . '/conn/conn.php';
    require_once __DIR__ . '/includes/produit_vitrine.php';
    try {
        $st = $db->prepare(
            'SELECT id, identifiant_interne FROM produits
              WHERE id = :id AND sync_deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':id' => $produit_id]);
        $piece = $st->fetch(PDO::FETCH_ASSOC);
        if ($piece) {
            $cible = '/p/' . fpl_vitrine_ean13_pour_produit($piece);
        }
    } catch (PDOException $e) {
        $cible = '/';
    }
}

header('Location: ' . $cible, true, 301);
exit;
