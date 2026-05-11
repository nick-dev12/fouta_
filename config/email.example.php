<?php
/**
 * Configuration email - PHPMailer / SMTP
 * Copiez ce fichier en config/email.php et modifiez les valeurs
 * NE JAMAIS committer config/email.php (ajoutez-le à .gitignore)
 *
 * Hébergeur : SSL/TLS (port 465 = implicit SSL / SMTPS)
 */

return [
    'method' => 'smtp',

    'smtp' => [
        'host' => 'mail.foutapoidslourds.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'no-replay@foutapoidslourds.com',
        'password' => 'VOTRE_MOT_DE_PASSE_ICI',
        'timeout' => 30,
        // Si le certificat du serveur ne correspond pas au nom (hébergement mutualisé), passer à true uniquement si nécessaire :
        'verify_ssl' => true,
    ],

    'from' => [
        'email' => 'no-replay@foutapoidslourds.com',
        'name' => 'FOUTA POIDS LOURDS',
    ],

    'contact_email' => 'info@foutapoidslourds.com',

    'debug' => false,
];
