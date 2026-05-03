<?php
/**
 * Paramètres plafond BL par type client (Standard / VIP) + vérifications cumul.
 */
require_once __DIR__ . '/../conn/conn.php';

function pct_types_client_bl_tables_available()
{
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!$db) {
        $ok = false;
        return false;
    }
    try {
        $db->query('SELECT 1 FROM parametres_types_client_bl LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function pct_label_type($code)
{
    $c = strtolower((string) $code);
    if ($c === 'vip') {
        return 'VIP';
    }
    return 'Standard';
}

/**
 * Plafond : 0 ou NULL = pas de limite (cumul non borné)
 */
function pct_get_plafond_bl($code_type)
{
    global $db;
    if (!pct_types_client_bl_tables_available()) {
        return null;
    }
    $code = ($code_type === 'vip') ? 'vip' : 'standard';
    try {
        $stmt = $db->prepare('SELECT montant_plafond_ht FROM parametres_types_client_bl WHERE code_type = :c');
        $stmt->execute(['c' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0;
        }
        return (float) ($row['montant_plafond_ht'] ?? 0);
    } catch (PDOException $e) {
        return null;
    }
}

function pct_get_all_plafonds()
{
    global $db;
    if (!pct_types_client_bl_tables_available()) {
        return ['standard' => 0, 'vip' => 0];
    }
    try {
        $stmt = $db->query('SELECT code_type, montant_plafond_ht FROM parametres_types_client_bl');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = ['standard' => 0, 'vip' => 0];
        foreach ($rows as $r) {
            $k = ($r['code_type'] === 'vip') ? 'vip' : 'standard';
            $out[$k] = (float) ($r['montant_plafond_ht'] ?? 0);
        }
        return $out;
    } catch (PDOException $e) {
        return ['standard' => 0, 'vip' => 0];
    }
}

/**
 * @return bool succès
 */
function pct_upsert_plafond($code_type, $montant)
{
    global $db;
    if (!pct_types_client_bl_tables_available()) {
        return false;
    }
    $code = ($code_type === 'vip') ? 'vip' : 'standard';
    $montant = max(0, (float) $montant);
    try {
        $stmt = $db->prepare('
            INSERT INTO parametres_types_client_bl (code_type, montant_plafond_ht)
            VALUES (:c, :m)
            ON DUPLICATE KEY UPDATE montant_plafond_ht = VALUES(montant_plafond_ht), date_modification = NOW()
        ');
        return $stmt->execute(['c' => $code, 'm' => round($montant, 2)]);
    } catch (PDOException $e) {
        error_log('[pct_upsert_plafond] ' . $e->getMessage());
        return false;
    }
}

/**
 * Remettre un type sans plafond (même effet que montant = 0).
 *
 * @return bool succès
 */
function pct_reinitialiser_plafond_type_bl($code_type)
{
    return pct_upsert_plafond($code_type, 0);
}

/**
 * Somme des total_ht des BL existants pour ce client B2B
 */
function pct_somme_totaux_bl_client_b2b($client_b2b_id)
{
    global $db;
    $client_b2b_id = (int) $client_b2b_id;
    if ($client_b2b_id <= 0) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare('SELECT COALESCE(SUM(total_ht), 0) AS s FROM bons_livraison WHERE client_b2b_id = :id');
        $stmt->execute(['id' => $client_b2b_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($row['s'] ?? 0);
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Vérifie si cumul actuel + nouveau BL dépasse le plafond pour le type donné
 *
 * @return array{ok:bool,message:string,cumul:float,plafond:float|null,reste:float|null}
 */
function pct_verifier_bl_montant_autorise($client_b2b_id, $code_type, $montant_nouveau_bl)
{
    $plafond = pct_get_plafond_bl($code_type);
    if ($plafond === null || $plafond <= 0) {
        return [
            'ok' => true,
            'message' => '',
            'cumul' => pct_somme_totaux_bl_client_b2b($client_b2b_id),
            'plafond' => 0,
            'reste' => null,
        ];
    }
    $cumul = pct_somme_totaux_bl_client_b2b((int) $client_b2b_id);
    $nouveau = (float) $montant_nouveau_bl;
    $apres = round($cumul + $nouveau, 2);
    $plaf = round($plafond, 2);
    if ($apres > $plaf + 0.000001) {
        $label = pct_label_type($code_type);
        return [
            'ok' => false,
            'message' => 'Plafond BL pour les clients « ' . $label . ' » dépassé : cumul actuel ' . number_format($cumul, 0, ',', ' ')
                . ' FCFA + ce BL ' . number_format($nouveau, 0, ',', ' ')
                . ' FCFA = ' . number_format($apres, 0, ',', ' ')
                . ' FCFA, maximum autorisé ' . number_format($plaf, 0, ',', ' ')
                . ' FCFA. Augmentez le plafond dans Paramètres ou changez le type du client.',
            'cumul' => $cumul,
            'plafond' => $plafond,
            'reste' => max(0, round($plaf - $cumul, 2)),
        ];
    }
    return [
        'ok' => true,
        'message' => '',
        'cumul' => $cumul,
        'plafond' => $plafond,
        'reste' => max(0, round($plaf - $cumul - $nouveau, 2)),
    ];
}
