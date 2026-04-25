<?php
/**
 * Service d'envoi d'emails via PHPMailer
 * Nécessite : composer require phpmailer/phpmailer
 * Configuration : config/email.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Charge la configuration email
 * @return array|null Configuration ou null si absente
 */
function mail_get_config() {
    $path = __DIR__ . '/../config/email.php';
    if (!file_exists($path)) {
        return null;
    }
    return require $path;
}

/**
 * Crée et configure une instance PHPMailer
 * @return PHPMailer|null Instance configurée ou null en cas d'erreur
 */
function mail_create_instance() {
    $config = mail_get_config();
    if (!$config) {
        return null;
    }

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->setLanguage('fr', __DIR__ . '/../vendor/phpmailer/phpmailer/language/');

    $from = $config['from'] ?? [];
    $mail->setFrom(
        $from['email'] ?? 'noreply@localhost',
        $from['name'] ?? 'FOUTA POIDS LOURDS'
    );

    if (($config['debug'] ?? false) === true) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    }

    switch ($config['method'] ?? 'mail') {
        case 'smtp':
            $mail->isSMTP();
            $smtp = $config['smtp'] ?? [];
            $mail->Host       = $smtp['host'] ?? 'localhost';
            $mail->Port       = (int) ($smtp['port'] ?? 587);
            $mail->Timeout     = (int) ($smtp['timeout'] ?? 30);
            $mail->SMTPAuth    = !empty($smtp['username']);
            if ($mail->SMTPAuth) {
                $mail->Username   = $smtp['username'] ?? '';
                $mail->Password   = $smtp['password'] ?? '';
            }
            $enc = $smtp['encryption'] ?? 'tls';
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            // Hébergement partagé : certificat émis pour *.web-hosting.com au lieu de mail.domaine.com
            if (isset($smtp['verify_ssl']) && $smtp['verify_ssl'] === false) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }
            break;
        case 'sendmail':
            $mail->isSendmail();
            break;
        default:
            $mail->isMail();
            break;
    }

    return $mail;
}

/**
 * Envoie un email simple
 * @param string $to Destinataire
 * @param string $subject Sujet
 * @param string $body Corps du message (HTML ou texte)
 * @param bool $isHtml true si le corps est en HTML
 * @return array ['success' => bool, 'error' => string|null]
 */
function mail_send($to, $subject, $body, $isHtml = true) {
    $mail = mail_create_instance();
    if (!$mail) {
        return ['success' => false, 'error' => 'Configuration email manquante (config/email.php)'];
    }

    try {
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML($isHtml);
        $mail->Body = $body;
        if (!$isHtml) {
            $mail->AltBody = $body;
        }
        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}

/**
 * Envoie l'email du formulaire de contact
 * @param string $nom Nom de l'expéditeur
 * @param string $email Email de l'expéditeur
 * @param string $sujet Sujet du message
 * @param string $message Corps du message
 * @return array ['success' => bool, 'error' => string|null]
 */
function mail_send_contact($nom, $email, $sujet, $message) {
    $config = mail_get_config();
    $contact = $config['contact_email'] ?? 'service@sugarpaper.com';

    $body = "<p><strong>De :</strong> " . htmlspecialchars($nom) . " &lt;" . htmlspecialchars($email) . "&gt;</p>";
    $body .= "<p><strong>Sujet :</strong> " . htmlspecialchars($sujet) . "</p>";
    $body .= "<hr><p>" . nl2br(htmlspecialchars($message)) . "</p>";

    $result = mail_send($contact, "[Contact] " . $sujet, $body, true);
    if ($result['success']) {
        $reply = "Merci pour votre message. Nous vous répondrons rapidement.";
        mail_send($email, "Confirmation - FOUTA POIDS LOURDS", "<p>" . htmlspecialchars($reply) . "</p>", true);
    }
    return $result;
}

/**
 * Envoie l'email de réinitialisation de mot de passe
 * @param string $email Destinataire
 * @param string $reset_link Lien de réinitialisation
 * @param string $type 'user' ou 'admin' (pour adapter le texte)
 * @return array ['success' => bool, 'error' => string|null]
 */
function mail_send_reset_link($email, $reset_link, $type = 'user') {
    $is_admin = ($type === 'admin');
    $sujet = 'Réinitialisation de votre mot de passe - Sugar Paper';

    $body = '<div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto;">';
    $body .= '<h2 style="color: #918a44;">Réinitialisation du mot de passe</h2>';
    $body .= '<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>';
    $body .= '<p>Cliquez sur le lien ci-dessous pour définir un nouveau mot de passe :</p>';
    $body .= '<p style="margin: 25px 0;"><a href="' . htmlspecialchars($reset_link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Réinitialiser mon mot de passe</a></p>';
    $body .= '<p style="font-size: 13px; color: #666;">Ou copiez ce lien dans votre navigateur :<br><span style="word-break: break-all;">' . htmlspecialchars($reset_link) . '</span></p>';
    $body .= '<p style="font-size: 12px; color: #999; margin-top: 30px;">Ce lien expire dans 2 heures. Si vous n\'avez pas fait cette demande, ignorez cet email.</p>';
    $body .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
    $body .= '<p style="font-size: 12px; color: #999;">FOUTA POIDS LOURDS - Pièces poids lourds</p>';
    $body .= '</div>';

    return mail_send($email, $sujet, $body, true);
}
