<?php
/**
 * Modèle pour les factures des commandes personnalisées
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Vérifie si la table factures_personnalisees existe
 */
function _fp_table_exists() {
    global $db;
    if (!$db) return false;
    try {
        $r = $db->query("SHOW TABLES LIKE 'factures_personnalisees'");
        return $r && $r->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée la table factures_personnalisees si elle n'existe pas
 */
function ensure_factures_personnalisees_table() {
    global $db;
    if (!$db || _fp_table_exists()) return true;
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS factures_personnalisees (
                id INT(11) NOT NULL AUTO_INCREMENT,
                commande_personnalisee_id INT(11) NOT NULL,
                numero_facture VARCHAR(50) NOT NULL,
                date_facture DATE NOT NULL,
                montant_total DECIMAL(10,2) NOT NULL DEFAULT 0,
                token VARCHAR(64) NULL UNIQUE,
                date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_cp (commande_personnalisee_id),
                KEY idx_numero (numero_facture),
                CONSTRAINT fk_fp_commande_perso FOREIGN KEY (commande_personnalisee_id) REFERENCES commandes_personnalisees (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Génère un numéro de facture unique pour les commandes personnalisées
 */
function generate_numero_facture_personnalisee() {
    global $db;
    if (!$db) return 'INV-CP' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    try {
        $stmt = $db->query("SELECT MAX(id) as max_id FROM factures_personnalisees");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && $row['max_id']) ? (int) $row['max_id'] + 1 : 1;
        return 'INV-CP' . str_pad($next, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'INV-CP' . str_pad(rand(1000, 99999), 5, '0', STR_PAD_LEFT);
    }
}

/**
 * Crée une facture pour une commande personnalisée
 * @param int $cp_id
 * @param float $montant_total Montant du devis (0 = à définir)
 * @return array|false
 */
function create_facture_personnalisee($cp_id, $montant_total = 0) {
    global $db;
    if (!$db) return false;

    ensure_factures_personnalisees_table();

    $cp_id = (int) $cp_id;
    if ($cp_id <= 0) return false;

    try {
        $stmt = $db->prepare("SELECT id FROM commandes_personnalisees WHERE id = :id");
        $stmt->execute(['id' => $cp_id]);
        if (!$stmt->fetch()) return false;

        $stmt = $db->prepare("SELECT id FROM factures_personnalisees WHERE commande_personnalisee_id = :cid");
        $stmt->execute(['cid' => $cp_id]);
        if ($stmt->fetch()) return false;

        $numero = generate_numero_facture_personnalisee();
        $token = bin2hex(random_bytes(32));

        $stmt = $db->prepare("
            INSERT INTO factures_personnalisees (commande_personnalisee_id, numero_facture, date_facture, montant_total, token)
            VALUES (:cp_id, :numero_facture, CURDATE(), :montant_total, :token)
        ");
        $stmt->execute([
            'cp_id' => $cp_id,
            'numero_facture' => $numero,
            'montant_total' => (float) $montant_total,
            'token' => $token
        ]);
        $facture_id = (int) $db->lastInsertId();
        return ['success' => true, 'facture_id' => $facture_id, 'numero_facture' => $numero];
    } catch (PDOException $e) {
        error_log('[create_facture_personnalisee] ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère une facture par commande_personnalisee_id
 */
function get_facture_personnalisee_by_cp($cp_id) {
    global $db;
    if (!$db) return false;
    try {
        $stmt = $db->prepare("SELECT * FROM factures_personnalisees WHERE commande_personnalisee_id = :cid");
        $stmt->execute(['cid' => (int) $cp_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une facture personnalisée par ID
 */
function get_facture_personnalisee_by_id($facture_id) {
    global $db;
    if (!$db) return false;
    try {
        $stmt = $db->prepare("SELECT * FROM factures_personnalisees WHERE id = :id");
        $stmt->execute(['id' => (int) $facture_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une facture personnalisée par token
 */
function get_facture_personnalisee_by_token($token) {
    global $db;
    if (!$db || empty($token) || strlen($token) !== 64) return false;
    try {
        $stmt = $db->prepare("SELECT * FROM factures_personnalisees WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le montant total d'une facture personnalisée
 * @param int $facture_id
 * @param float $montant_total
 * @return bool
 */
function update_facture_personnalisee_montant($facture_id, $montant_total) {
    global $db;
    if (!$db || (int) $facture_id <= 0) return false;
    try {
        $stmt = $db->prepare("UPDATE factures_personnalisees SET montant_total = :montant WHERE id = :id");
        return $stmt->execute([
            'id' => (int) $facture_id,
            'montant' => (float) $montant_total
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * S'assure qu'une facture personnalisée a un token
 */
function ensure_facture_personnalisee_token($facture_id) {
    global $db;
    if (!$db || (int) $facture_id <= 0) return null;
    try {
        $stmt = $db->prepare("SELECT token FROM factures_personnalisees WHERE id = :id");
        $stmt->execute(['id' => (int) $facture_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if (!empty($row['token'])) return $row['token'];
        $token = bin2hex(random_bytes(32));
        $upd = $db->prepare("UPDATE factures_personnalisees SET token = :token WHERE id = :id");
        $upd->execute(['token' => $token, 'id' => (int) $facture_id]);
        return $token;
    } catch (PDOException $e) {
        return null;
    }
}
