<?php
/**
 * Vérification du plafond BL cumulé HT (montant défini par contact, plus de table globale).
 */
require_once __DIR__ . '/../conn/conn.php';

function pct_label_type($code)
{
    $c = strtolower((string) $code);
    if ($c === 'vip') {
        return 'VIP';
    }
    return 'Standard';
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
 * Vérifie si cumul actuel + nouveau BL dépasse le plafond du contact.
 *
 * @param float $montant_plafond_ht plafond depuis la fiche contact (0 = pas de limite)
 *
 * @return array{ok:bool,message:string,cumul:float,plafond:float|null,reste:float|null}
 */
function pct_verifier_bl_montant_autorise($client_b2b_id, $montant_plafond_ht, $montant_nouveau_bl)
{
    $plafond = round(max(0, (float) $montant_plafond_ht), 2);
    if ($plafond <= 0) {
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
    $plaf = $plafond;
    if ($apres > $plaf + 0.000001) {
        return [
            'ok' => false,
            'message' => 'Plafond BL dépassé pour ce client : cumul actuel '
                . number_format($cumul, 0, ',', ' ')
                . ' FCFA + ce BL ' . number_format($nouveau, 0, ',', ' ')
                . ' FCFA = ' . number_format($apres, 0, ',', ' ')
                . ' FCFA, maximum autorisé ' . number_format($plaf, 0, ',', ' ')
                . ' FCFA (montant défini sur la fiche contact dans Contacts).',
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
