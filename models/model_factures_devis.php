<?php
/**
 * Modèle pour les factures des devis
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin_activite.php';

/**
 * Génère un numéro de facture devis (format INV-DEV + 5 chiffres)
 */
function generate_numero_facture_devis() {
    global $db;
    try {
        $stmt = $db->query("SELECT MAX(id) as max_id FROM factures_devis");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && $row['max_id']) ? (int) $row['max_id'] + 1 : 1;
        return 'INV-DEV' . str_pad($next, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'INV-DEV' . str_pad(rand(1000, 99999), 5, '0', STR_PAD_LEFT);
    }
}

/**
 * Crée une facture pour un devis
 * @param int $devis_id
 * @param int|null $admin_createur_id Admin ayant généré la facture
 * @return array|false ['success'=>true, 'facture_id'=>int, 'numero_facture'=>string] ou false
 */
function create_facture_devis($devis_id, $admin_createur_id = null) {
    global $db;

    $devis_id = (int) $devis_id;
    if ($devis_id <= 0) return false;

    try {
        $stmt = $db->prepare("SELECT id, montant_total FROM devis WHERE id = :id");
        $stmt->execute(['id' => $devis_id]);
        $devis = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$devis) return false;

        $stmt = $db->prepare("SELECT id FROM factures_devis WHERE devis_id = :did");
        $stmt->execute(['did' => $devis_id]);
        if ($stmt->fetch()) return false;

        $numero = generate_numero_facture_devis();
        $stmt = $db->prepare("SELECT id FROM factures_devis WHERE numero_facture = :num");
        $stmt->execute(['num' => $numero]);
        if ($stmt->fetch()) {
            $numero = generate_numero_facture_devis() . '-' . substr(uniqid(), -3);
        }

        $token = bin2hex(random_bytes(32));

        $has_admin = admin_activite_column_exists('factures_devis', 'admin_createur_id');
        $aid = $has_admin && $admin_createur_id !== null && (int) $admin_createur_id > 0 ? (int) $admin_createur_id : null;

        if ($has_admin) {
            $stmt = $db->prepare("
                INSERT INTO factures_devis (devis_id, numero_facture, date_facture, montant_total, token, admin_createur_id)
                VALUES (:devis_id, :numero_facture, CURDATE(), :montant_total, :token, :admin_createur_id)
            ");
            $stmt->execute([
                'devis_id' => $devis_id,
                'numero_facture' => $numero,
                'montant_total' => (float) $devis['montant_total'],
                'token' => $token,
                'admin_createur_id' => $aid,
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO factures_devis (devis_id, numero_facture, date_facture, montant_total, token)
                VALUES (:devis_id, :numero_facture, CURDATE(), :montant_total, :token)
            ");
            $stmt->execute([
                'devis_id' => $devis_id,
                'numero_facture' => $numero,
                'montant_total' => (float) $devis['montant_total'],
                'token' => $token
            ]);
        }
        $facture_id = (int) $db->lastInsertId();
        return ['success' => true, 'facture_id' => $facture_id, 'numero_facture' => $numero];
    } catch (PDOException $e) {
        error_log('[create_facture_devis] ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère une facture devis par devis_id
 */
function get_facture_devis_by_devis($devis_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM factures_devis WHERE devis_id = :did");
        $stmt->execute(['did' => (int) $devis_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une facture devis par ID
 */
function get_facture_devis_by_id($facture_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM factures_devis WHERE id = :id");
        $stmt->execute(['id' => (int) $facture_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une facture devis par token (accès public)
 */
function get_facture_devis_by_token($token) {
    global $db;
    if (empty($token) || strlen($token) !== 64) return false;
    try {
        $stmt = $db->prepare("SELECT * FROM factures_devis WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * S'assure qu'une facture devis a un token
 */
function ensure_facture_devis_token($facture_id) {
    global $db;
    $facture_id = (int) $facture_id;
    if ($facture_id <= 0) return null;
    try {
        $stmt = $db->prepare("SELECT token FROM factures_devis WHERE id = :id");
        $stmt->execute(['id' => $facture_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if (!empty($row['token'])) return $row['token'];
        $token = bin2hex(random_bytes(32));
        $upd = $db->prepare("UPDATE factures_devis SET token = :token WHERE id = :id");
        $upd->execute(['token' => $token, 'id' => $facture_id]);
        return $token;
    } catch (PDOException $e) {
        return null;
    }
}
