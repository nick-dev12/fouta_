<?php
/**
 * Configuration email - PHPMailer / SMTP
 * Copiez ce fichier en config/email.php et modifiez les valeurs
 * NE JAMAIS committer config/email.php (ajoutez-le à .gitignore)
 */

return [
    // Méthode d'envoi : 'smtp', 'sendmail', ou 'mail' (fonction mail() PHP)
    'method' => 'smtp',

    // Configuration SMTP - SSL/TLS recommandé (port 465)
    'smtp' => [
        'host' => 'sugar-paper.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'service@sugar-paper.com',
        'password' => 'VOTRE_MOT_DE_PASSE',
        'timeout' => 30,
        'verify_ssl' => false,
    ],

    // Expéditeur par défaut
    'from' => [
        'email' => 'service@sugar-paper.com',
        'name' => 'FOUTA POIDS LOURDS',
    ],

    // Email de contact (destinataire des messages du formulaire contact)
    'contact_email' => 'info@foutapoidslourds.com',

    // Mode debug : true pour afficher les erreurs SMTP
    'debug' => false,

    // URL du site pour les liens dans les emails (optionnel)
    // Si défini ici, surcharge config/site.php. Ex: 'https://sugar-paper.com'
    // 'site_url' => 'https://sugar-paper.com',
];