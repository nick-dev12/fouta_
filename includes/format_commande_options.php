<?php
/**
 * Helpers pour l'affichage des options commande (couleur, poids, taille)
 * Inclure model_produits avant (pour parse_options_with_surcharge)
 */

/**
 * Retourne le nom lisible d'une couleur hex ou la chaîne telle quelle
 */
function format_couleur_commande($hex) {
    $hex = trim($hex ?? '');
    if ($hex === '') return '';
    if (preg_match('/^\[/', $hex)) {
        $dec = json_decode($hex, true);
        if (is_array($dec) && !empty($dec)) {
            $first = is_string($dec[0]) ? $dec[0] : ($dec[0]['v'] ?? $dec[0]['valeur'] ?? '');
            $hex = trim($first);
        }
    }
    static $noms = [
        '#000000' => 'Noir', '#ffffff' => 'Blanc', '#ff0000' => 'Rouge', '#00ff00' => 'Vert',
        '#0000ff' => 'Bleu', '#ffff00' => 'Jaune', '#ff00ff' => 'Magenta', '#00ffff' => 'Cyan',
        '#808080' => 'Gris', '#c0c0c0' => 'Argent', '#800000' => 'Marron', '#4862e5' => 'Bleu',
        '#e54848' => 'Rouge', '#2e7d32' => 'Vert foncé', '#f9a825' => 'Jaune doré'
    ];
    $h = strtolower($hex);
    foreach ($noms as $code => $nom) {
        if (strtolower($code) === $h) return $nom;
    }
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) return $hex;
    return $hex;
}

/**
 * Parse poids/taille et retourne UNIQUEMENT l'option sélectionnée pour cette commande
 * (filtre par surcout pour n'afficher que la donnée de la commande)
 * @return array [['v'=>'500g','s'=>300]] une seule entrée
 */
function parse_poids_taille_commande($raw, $surcout_fallback = 0) {
    $raw = trim($raw ?? '');
    if ($raw === '') {
        return $surcout_fallback > 0 ? [['v' => '', 's' => $surcout_fallback]] : [];
    }
    $opts = parse_options_with_surcharge($raw);
    if (empty($opts)) return [['v' => $raw, 's' => $surcout_fallback]];
    if (count($opts) === 1) {
        $opts[0]['s'] = $surcout_fallback > 0 ? $surcout_fallback : ($opts[0]['s'] ?? 0);
        return $opts;
    }
    $surcout_f = (float) $surcout_fallback;
    $filtered = array_filter($opts, function ($o) use ($surcout_f) {
        return ((float)($o['s'] ?? 0)) == $surcout_f;
    });
    if (!empty($filtered)) return [array_values($filtered)[0]];
    return [['v' => $opts[0]['v'], 's' => $surcout_fallback]];
}
