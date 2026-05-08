<?php
/**
 * Modèle pour les factures des devis
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin_activite.php';
require_once __DIR__ . '/../includes/fiscal_tva.php';
require_once __DIR__ . '/model_devis.php';

/**
 * Colonnes fiscales (snapshot TVA) sur factures_devis
 */
function factures_devis_fiscal_columns_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT tva_incluse, montant_ht, montant_tva, taux_tva_pourcent FROM factures_devis LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

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
        $stmt = $db->prepare("SELECT * FROM devis WHERE id = :id");
        $stmt->execute(['id' => $devis_id]);
        $devis = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$devis) return false;

        $stmt = $db->prepare("SELECT id FROM factures_devis WHERE devis_id = :did");
        $stmt->execute(['did' => $devis_id]);
        if ($stmt->fetch()) return false;

        $net = devis_calcul_net_ht($devis_id);
        $tva_incl = devis_tva_columns_ok() && !empty($devis['tva_incluse']);
        $ttp = devis_tva_columns_ok() && isset($devis['taux_tva_pourcent']) && (float) $devis['taux_tva_pourcent'] > 0
            ? (float) $devis['taux_tva_pourcent']
            : null;
        $f = fiscal_decomposer_net_ht($net, $tva_incl, $ttp);
        $montant_total_ins = (float) ($devis['montant_total'] ?? $f['montant_ttc']);
        $mht = $f['montant_ht'];
        $mtva = $f['montant_tva'];
        $ttp = $ttp ?? fiscal_taux_tva_pourcent();
        $tva_snap = $tva_incl ? 1 : 0;

        $numero = generate_numero_facture_devis();
        $stmt = $db->prepare("SELECT id FROM factures_devis WHERE numero_facture = :num");
        $stmt->execute(['num' => $numero]);
        if ($stmt->fetch()) {
            $numero = generate_numero_facture_devis() . '-' . substr(uniqid(), -3);
        }

        $token = bin2hex(random_bytes(32));

        $has_admin = admin_activite_column_exists('factures_devis', 'admin_createur_id');
        $aid = $has_admin && $admin_createur_id !== null && (int) $admin_createur_id > 0 ? (int) $admin_createur_id : null;

        $fiscal_ok = factures_devis_fiscal_columns_ok();

        if ($fiscal_ok && $has_admin) {
            $stmt = $db->prepare("
                INSERT INTO factures_devis (devis_id, numero_facture, date_facture, montant_total, tva_incluse, montant_ht, montant_tva, taux_tva_pourcent, token, admin_createur_id)
                VALUES (:devis_id, :numero_facture, CURDATE(), :montant_total, :tva_incluse, :montant_ht, :montant_tva, :taux_tva_pourcent, :token, :admin_createur_id)
            ");
            $stmt->execute([
                'devis_id' => $devis_id,
                'numero_facture' => $numero,
                'montant_total' => $montant_total_ins,
                'tva_incluse' => $tva_snap,
                'montant_ht' => $mht,
                'montant_tva' => $mtva,
                'taux_tva_pourcent' => $ttp,
                'token' => $token,
                'admin_createur_id' => $aid,
            ]);
        } elseif ($fiscal_ok) {
            $stmt = $db->prepare("
                INSERT INTO factures_devis (devis_id, numero_facture, date_facture, montant_total, tva_incluse, montant_ht, montant_tva, taux_tva_pourcent, token)
                VALUES (:devis_id, :numero_facture, CURDATE(), :montant_total, :tva_incluse, :montant_ht, :montant_tva, :taux_tva_pourcent, :token)
            ");
            $stmt->execute([
                'devis_id' => $devis_id,
                'numero_facture' => $numero,
                'montant_total' => $montant_total_ins,
                'tva_incluse' => $tva_snap,
                'montant_ht' => $mht,
                'montant_tva' => $mtva,
                'taux_tva_pourcent' => $ttp,
                'token' => $token,
            ]);
        } elseif ($has_admin) {
            $stmt = $db->prepare("
                INSERT INTO factures_devis (devis_id, numero_facture, date_facture, montant_total, token, admin_createur_id)
                VALUES (:devis_id, :numero_facture, CURDATE(), :montant_total, :token, :admin_createur_id)
            ");
            $stmt->execute([
                'devis_id' => $devis_id,
                'numero_facture' => $numero,
                'montant_total' => $montant_total_ins,
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
                'montant_total' => $montant_total_ins,
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

/**
 * Colonnes paiement FPL présentes sur factures_devis
 */
function factures_devis_col_payee_ok()
{
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT payee, numero_reference_fpl FROM factures_devis LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Prochain numéro comptable FPL##### (séquence sur factures payées).
 */
function generate_numero_reference_fpl_facture_devis()
{
    global $db;
    if (!factures_devis_col_payee_ok()) {
        return 'FPL00001';
    }
    try {
        $stmt = $db->query("
            SELECT numero_reference_fpl FROM factures_devis
            WHERE numero_reference_fpl IS NOT NULL AND numero_reference_fpl LIKE 'FPL%'
        ");
        $max = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $raw = (string) ($row['numero_reference_fpl'] ?? '');
            if (preg_match('/^FPL(\d+)$/i', $raw, $m)) {
                $n = (int) $m[1];
                if ($n > $max) {
                    $max = $n;
                }
            }
        }
        return 'FPL' . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'FPL' . str_pad((string) random_int(1, 50000), 5, '0', STR_PAD_LEFT);
    }
}

/**
 * @return array{ok:bool, error?:string, numero_reference_fpl?:string}
 */
function marquer_facture_devis_payee($facture_id)
{
    global $db;
    $facture_id = (int) $facture_id;
    if ($facture_id <= 0 || !factures_devis_col_payee_ok()) {
        return ['ok' => false, 'error' => 'Opération indisponible (migration requise).'];
    }
    $f = get_facture_devis_by_id($facture_id);
    if (!$f) {
        return ['ok' => false, 'error' => 'Facture introuvable.'];
    }
    if (!empty($f['payee'])) {
        return ['ok' => false, 'error' => 'Cette facture est déjà marquée comme payée.'];
    }
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $num = generate_numero_reference_fpl_facture_devis();
        try {
            $stmt = $db->prepare('
                UPDATE factures_devis
                SET payee = 1, date_paiement = NOW(), numero_reference_fpl = :n
                WHERE id = :id AND (payee = 0 OR payee IS NULL)
            ');
            $stmt->execute(['n' => $num, 'id' => $facture_id]);
            if ($stmt->rowCount() === 1) {
                return ['ok' => true, 'numero_reference_fpl' => $num];
            }
            return ['ok' => false, 'error' => 'Mise à jour impossible.'];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                continue;
            }
            error_log('[marquer_facture_devis_payee] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Erreur technique.'];
        }
    }
    return ['ok' => false, 'error' => 'Impossible d\'attribuer un numéro FPL unique.'];
}

/**
 * @return array<int, array<string, mixed>>
 */
function get_factures_devis_payees_avec_devis()
{
    global $db;
    if (!factures_devis_col_payee_ok()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT fd.*, d.numero_devis, d.client_nom, d.client_prenom, d.client_telephone, d.client_email,
                   d.statut AS devis_statut, d.id AS devis_id_ref
            FROM factures_devis fd
            INNER JOIN devis d ON d.id = fd.devis_id
            WHERE fd.payee = 1
            ORDER BY fd.date_paiement DESC, fd.id DESC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}
