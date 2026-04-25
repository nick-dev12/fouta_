# Configuration Composer - PHPMailer & Firebase

## Dépendances installées

- **phpmailer/phpmailer** : Envoi d'emails via SMTP
- **kreait/firebase-php** : Notifications push Google (Firebase Cloud Messaging)

## 1. Emails (PHPMailer)

### Configuration

Copiez `config/email.example.php` vers `config/email.php` et configurez :

```php
return [
    'method' => 'smtp',  // 'smtp', 'sendmail' ou 'mail'
    'smtp' => [
        'host'       => 'smtp.example.com',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => 'votre_email@example.com',
        'password'   => 'votre_mot_de_passe',
    ],
    'from' => [
        'email' => 'noreply@sugarpaper.com',
        'name'  => 'Sugar Paper',
    ],
    'contact_email' => 'service@sugarpaper.com',
];
```

### Utilisation

```php
// Charger l'autoload
require_once __DIR__ . '/vendor/autoload.php';

// Envoi simple
$result = mail_send('destinataire@example.com', 'Sujet', '<p>Corps HTML</p>');
if ($result['success']) {
    // OK
} else {
    echo $result['error'];
}

// Formulaire contact
$result = mail_send_contact($nom, $email, $sujet, $message);
```

## 2. Notifications push (Firebase)

Le service `firebase_push.php` utilise automatiquement **kreait/firebase-php** si Composer est installé, sinon l'implémentation native.

### Configuration

Optionnel : créez `config/firebase_server.php` (copier depuis `config/firebase_server.example.php`) pour personnaliser le chemin du fichier credentials :

```php
return [
    'credentials_path' => __DIR__ . '/../sugar-paper-d34851eeca5a.json',
];
```

### Utilisation

```php
require_once __DIR__ . '/services/firebase_push.php';

$result = firebase_send_notification(
    ['token_fcm_1', 'token_fcm_2'],
    'Titre',
    'Message',
    ['link' => '/user/mes-commandes.php', 'commande_id' => '123']
);
// $result = ['success' => 2, 'failed' => 0, 'errors' => []]
```

## 3. Autoload Composer

L'autoload est chargé automatiquement via `conn/conn.php`. Si vous avez déjà un `conn.php` existant, ajoutez au début du fichier (après `<?php`) :

```php
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
```

Les pages comme `contact.php` chargent l'autoload directement si nécessaire.

## 4. Mise à jour des dépendances

```bash
composer update
```
