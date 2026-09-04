<?php
/**
 * LA VITRINE CLIENT D'UNE PIÈCE — le circuit du QR de l'étiquette (04/09/2026).
 *
 * Décision de la direction : sur l'étiquette de pièce, le QR et le code-barres
 * portent LE MÊME contenu — le numéro EAN-13 de la pièce (200 + les 9 chiffres
 * de l'identifiant FPL + la clé). Le code-barres le donne nu à la douchette ;
 * le QR le place dans le lien /p/{ean13} pour que le téléphone du client
 * ouvre la page vitrine. Ce contenu est exclusivement destiné aux clients :
 * rien d'interne (ni stock, ni emplacement, ni prix d'achat) n'y transite.
 *
 * Ici vivent les trois gestes partagés par l'étiquette, la page p.php et la
 * redirection des anciens QR (stock-info.php) :
 *   - fpl_vitrine_base_url()          : l'adresse PUBLIQUE gravée dans les QR ;
 *   - fpl_vitrine_ean13_pour_produit(): le numéro commun aux deux codes ;
 *   - produit_vitrine_url()           : l'URL complète encodée dans le QR.
 */

require_once __DIR__ . '/site_url.php';

/**
 * L'adresse de base gravée dans les QR imprimés. Une étiquette collée est un
 * engagement : cette adresse doit être joignable par le téléphone d'un client,
 * donc jamais une IP de réseau local. Priorité à la clé 'vitrine_url' de
 * config/site.php (le domaine public), sinon site_url, sinon l'hôte courant.
 *
 * @return string URL sans slash final
 */
function fpl_vitrine_base_url() {
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $url = '';
    if (file_exists(__DIR__ . '/../config/site.php')) {
        $config = require __DIR__ . '/../config/site.php';
        $url = trim((string) ($config['vitrine_url'] ?? ''));
    }
    if ($url === '') {
        $url = get_site_base_url();
    }

    $base = rtrim($url, '/');
    return $base;
}

/**
 * Les 13 chiffres communs au code-barres et au QR : la composition vit dans le
 * moteur d'étiquette (etiquette70_ean12_pour_identifiant + la clé de
 * etiquette70_ean13) — on la réutilise, jamais on ne la recopie.
 *
 * @param array<string, mixed> $produit Doit porter identifiant_interne (et id en repli)
 * @return string 13 chiffres
 */
function fpl_vitrine_ean13_pour_produit(array $produit) {
    if (!function_exists('etiquette70_ean12_pour_identifiant')) {
        require_once __DIR__ . '/etiquette_fpl70.php';
    }
    $identifiant = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
    $douze = etiquette70_ean12_pour_identifiant($identifiant, (int) ($produit['id'] ?? 0));
    $ean = etiquette70_ean13($douze);

    return (string) $ean['chiffres'];
}

/**
 * L'URL encodée dans le QR de l'étiquette de pièce.
 *
 * @param array<string, mixed> $produit
 * @return string
 */
function produit_vitrine_url(array $produit) {
    return fpl_vitrine_base_url() . '/p/' . fpl_vitrine_ean13_pour_produit($produit);
}

/**
 * Résout ce que porte l'URL /p/{code} vers un identifiant FPL cherchable :
 * accepte les 13 chiffres du code-barres (clé vérifiée), l'identifiant FPL
 * lui-même, avec espaces et casse indifférentes — le client tape ce qu'il voit.
 *
 * @param string $brut
 * @return string Identifiant FPL (FPL + 6 ou 9 chiffres), ou '' si inconnu
 */
function fpl_vitrine_code_vers_identifiant($brut) {
    require_once __DIR__ . '/produit_emplacement_entrepot.php';
    $code = strtoupper(str_replace(' ', '', trim((string) $brut)));
    if ($code === '') {
        return '';
    }
    $code = produit_emplacement_extraire_fpl_du_scan($code);
    if (preg_match('/^FPL\d{6}(\d{3})?$/', $code)) {
        return $code;
    }
    /* Les 9 chiffres nus (l'identifiant sans son préfixe) : on les habille. */
    if (preg_match('/^\d{9}$/', $code)) {
        return 'FPL' . $code;
    }

    return '';
}
