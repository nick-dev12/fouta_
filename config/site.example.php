<?php
/**
 * Configuration de l'URL du site (pour les liens dans les emails, notifications, etc.)
 * Copiez ce fichier en config/site.php et modifiez site_url pour la production
 * 
 * En production : https://sugar-paper.com
 * En développement : http://localhost:5000 ou laisser vide pour utiliser $_SERVER['HTTP_HOST']
 */

return [
    // URL de base du site (sans slash final)
    // Exemples : 'https://sugar-paper.com' | 'https://www.sugar-paper.com' | ''
    // Si vide, l'URL est déduite automatiquement de la requête HTTP
    'site_url' => 'https://sugar-paper.com',

    // Adresse PUBLIQUE gravée dans les QR des étiquettes de pièce
    // (vitrine client /p/{ean13}). Une étiquette imprimée est un engagement :
    // mettre ici le domaine joignable par le téléphone d'un client — jamais
    // une IP de réseau local. Vide = repli sur site_url.
    'vitrine_url' => '',
];
