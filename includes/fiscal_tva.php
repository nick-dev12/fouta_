<?php
/**
 * TVA facturation devis / BL — aligné sur la caisse (net HT + TVA en sus).
 * Les prix lignes sont hors taxes ; si « inclure la TVA », le total à payer = HT + TVA.
 */
if (!defined('FISCAL_TVA_TAUX_POURCENT')) {
    define('FISCAL_TVA_TAUX_POURCENT', 18.0);
}

/**
 * Taux affiché / calculé (même valeur que la caisse si model_caisse chargé avant).
 */
function fiscal_taux_tva_pourcent() {
    if (defined('CAISSE_TVA_TAUX_POURCENT')) {
        return (float) CAISSE_TVA_TAUX_POURCENT;
    }
    return (float) FISCAL_TVA_TAUX_POURCENT;
}

/**
 * @param float|null $taux_pct Pourcentage TVA (ex. 18). Si null, utilise {@see fiscal_taux_tva_pourcent()}.
 * @return array{montant_ht:float,montant_tva:float,montant_ttc:float}
 */
function fiscal_decomposer_net_ht($net_ht, $tva_incluse, $taux_pct = null) {
    $net_ht = round((float) $net_ht, 2);
    $t = ($taux_pct !== null && (float) $taux_pct > 0) ? (float) $taux_pct : fiscal_taux_tva_pourcent();
    $taux = $t / 100.0;
    if ($tva_incluse) {
        $tva = round($net_ht * $taux, 2);
        $ttc = round($net_ht + $tva, 2);
        return ['montant_ht' => $net_ht, 'montant_tva' => $tva, 'montant_ttc' => $ttc];
    }
    return ['montant_ht' => $net_ht, 'montant_tva' => 0.0, 'montant_ttc' => $net_ht];
}
