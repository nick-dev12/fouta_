<?php
/**
 * Envoie une notification push et un email au client lors du changement de statut de commande
 * @param int $user_id ID du client
 * @param string $numero_commande Numéro de la commande
 * @param string $nouveau_statut Statut mis à jour
 * @param string $user_email Email du client (celui de son compte) pour l'envoi de l'email
 * @return void
 */
function send_commande_status_notification($user_id, $numero_commande, $nouveau_statut, $user_email = '') {
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';

    $statut_labels = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'prise_en_charge' => 'Prise en charge',
        'en_preparation' => 'En préparation',
        'livraison_en_cours' => 'Livraison en cours',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'annulee' => 'Annulée'
    ];

    $label = $statut_labels[$nouveau_statut] ?? ucfirst(str_replace('_', ' ', $nouveau_statut));

    $title = 'Mise à jour de votre commande';
    $body = "Commande #{$numero_commande} : {$label}";

    require_once __DIR__ . '/../includes/site_url.php';
    $base_url = get_site_base_url();
    $link = $base_url . '/user/mes-commandes.php';

    // Notification push (si le client a des tokens FCM)
    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, [
            'link' => $link,
            'commande_id' => '',
            'statut' => $nouveau_statut,
            'numero_commande' => $numero_commande,
            'tag' => 'commande-' . $numero_commande
        ]);
    }

    // Email au client (si email valide et service mail configuré)
    $user_email = trim($user_email ?? '');
    if (!empty($user_email) && filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        if (function_exists('mail_send')) {
            $sujet = "[FOUTA POIDS LOURDS] Mise à jour de votre commande #{$numero_commande}";
            $body_html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
            $body_html .= '<h2 style="color: #918a44;">Mise à jour de votre commande</h2>';
            $body_html .= '<p>Bonjour,</p>';
            $body_html .= '<p>Le statut de votre commande <strong>#' . htmlspecialchars($numero_commande) . '</strong> a été mis à jour.</p>';
            $body_html .= '<p><strong>Nouveau statut :</strong> <span style="color: #6b2f20; font-weight: 600;">' . htmlspecialchars($label) . '</span></p>';
            $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Voir mes commandes</a></p>';
            $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
            $body_html .= '<p style="font-size: 12px; color: #999;">FOUTA POIDS LOURDS - Pièces poids lourds</p>';
            $body_html .= '</div>';

            mail_send($user_email, $sujet, $body_html, true);
        }
    }
}
