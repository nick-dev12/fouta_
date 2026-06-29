<?php
/**
 * Numérotation séquentielle des factures BL (bons de livraison validés).
 * Reprend la suite historique à partir du numéro 2519.
 */
if (!defined('FISCAL_BL_FACTURE_NUMERO_DEPART')) {
    define('FISCAL_BL_FACTURE_NUMERO_DEPART', 2519);
}

/**
 * Premier numéro de facture BL si la base est vide (ou sans numéro ≥ au départ).
 */
function fiscal_bl_facture_numero_depart() {
    return (int) FISCAL_BL_FACTURE_NUMERO_DEPART;
}

/**
 * Extrait la partie numérique d'une référence facture (FPL2519, 2519, FPL000001…).
 *
 * @return int|null
 */
function fiscal_extraire_numero_facture_sequentiel($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^FPL(\d+)$/i', $raw, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/^\d+$/', $raw)) {
        return (int) $raw;
    }
    return null;
}

/**
 * Plus grand numéro BL déjà attribué (≥ au numéro de départ), ou (départ − 1) si aucun.
 */
function fiscal_max_numero_facture_bl() {
    global $db;
    $depart = fiscal_bl_facture_numero_depart();
    $max = $depart - 1;

    if (!function_exists('bl_numero_reference_fpl_column_ok') || !bl_numero_reference_fpl_column_ok() || !$db) {
        return $max;
    }

    try {
        $stmt = $db->query("
            SELECT numero_reference_fpl
            FROM bons_livraison
            WHERE numero_reference_fpl IS NOT NULL AND TRIM(numero_reference_fpl) <> ''
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $n = fiscal_extraire_numero_facture_sequentiel($row['numero_reference_fpl'] ?? '');
            if ($n !== null && $n >= $depart && $n > $max) {
                $max = $n;
            }
        }
    } catch (PDOException $e) {
        error_log('[fiscal_max_numero_facture_bl] ' . $e->getMessage());
    }

    return $max;
}

/**
 * Prochain numéro de facture BL (ex. 2520 si 2519 existe déjà).
 */
function fiscal_prochain_numero_facture_bl() {
    return (string) (fiscal_max_numero_facture_bl() + 1);
}
