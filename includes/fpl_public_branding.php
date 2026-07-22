<?php
/**
 * Identité visuelle pages publiques scan QR (produit / barre entrepôt).
 */
require_once __DIR__ . '/site_url.php';

/**
 * @return array<string, string>
 */
function fpl_public_branding_coords() {
    return [
        'nom' => 'FOUTA POIDS LOURDS',
        'tagline' => 'The Solution',
        'rc' => 'SN.DKR.2019.M.28414',
        'ninea' => '006705654/2A2',
        'adresse' => 'Rond-Point Zac Mbao, Dakar',
        'telephone' => '+221 338700070',
        'telephone_href' => 'tel:+221338700070',
        'site' => 'https://www.foutapoidslourds.com',
        'email' => 'info@foutapoidslourds.com',
    ];
}

/**
 * @return string
 */
function fpl_public_branding_logo_url() {
    return get_site_logo_url_for_current_request();
}
