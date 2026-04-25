<?php
/**
 * Modèle pour la gestion des factures
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Génère un numéro de facture unique (format INV + 5 chiffres)
 */
function generate_numero_facture() {
    global $db;
    try {
        $stmt = $db->query("SELECT MAX(id) as max_id FROM factures");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && $row['max_id']) ? (int) $row['max_id'] + 1 : 1;
        return 'INV' . str_pad($next, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'INV' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
    }
}

/**
 * Crée une facture pour une commande
 * @param int $commande_id
 * @return array|false ['success'=>true, 'facture_id'=>int, 'numero_facture'=>string] ou false
 */
function create_facture($commande_id) {
    global $db;

    $commande_id = (int) $commande_id;
    if ($commande_id <= 0) return false;

    try {
        $stmt = $db->prepare("SELECT id, montant_total FROM commandes WHERE id = :id");
        $stmt->execute(['id' => $commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$commande) return false;

        $stmt = $db->prepare("SELECT id FROM factures WHERE commande_id = :cid");
        $stmt->execute(['cid' => $commande_id]);
        if ($stmt->fetch()) return false;

        $numero = generate_numero_facture();
        $stmt = $db->prepare("SELECT id FROM factures WHERE numero_facture = :num");
        $stmt->execute(['num' => $numero]);
        if ($stmt->fetch()) {
            $numero = generate_numero_facture() . '-' . substr(uniqid(), -3);
        }

        $token = bin2hex(random_bytes(32));

        $stmt = $db->prepare("
            INSERT INTO factures (commande_id, numero_facture, date_facture, montant_total, token)
            VALUES (:commande_id, :numero_facture, CURDATE(), :montant_total, :token)
        ");
        $stmt->execute([
            'commande_id' => $commande_id,
            'numero_facture' => $numero,
            'montant_total' => (float) $commande['montant_total'],
            'token' => $token
        ]);
        $facture_id = (int) $db->lastInsertId();
        return ['success' => true, 'facture_id' => $facture_id, 'numero_facture' => $numero];
    } catch (PDOException $e) {
        error_log('[create_facture] ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère une facture par commande_id
 * @param int $commande_id
 * @return array|false
 */
function get_facture_by_commande($commande_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM factures WHERE commande_id = :cid");
        $stmt->execute(['cid' => (int) $commande_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une facture par token (accès public)
 * @param string $token
 * @return array|false
 */
function get_facture_by_token($token) {
    global $db;
    if (empty($token) || strlen($token) !== 64) return false;
    try {
        $stmt = $db->prepare("SELECT * FROM factures WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * S'assure qu'une facture a un token (pour les factures créées avant la migration)
 * @param int $facture_id
 * @return string|null Le token ou null
 */
function ensure_facture_token($facture_id) {
    global $db;
    $facture_id = (int) $facture_id;
    if ($facture_id <= 0) return null;
    try {
        $stmt = $db->prepare("SELECT token FROM factures WHERE id = :id");
        $stmt->execute(['id' => $facture_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if (!empty($row['token'])) return $row['token'];
        $token = bin2hex(random_bytes(32));
        $upd = $db->prepare("UPDATE factures SET token = :token WHERE id = :id");
        $upd->execute(['token' => $token, 'id' => $facture_id]);
        return $token;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Récupère une facture par ID
 * @param int $facture_id
 * @return array|false
 */
function get_facture_by_id($facture_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM factures WHERE id = :id");
        $stmt->execute(['id' => (int) $facture_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}
