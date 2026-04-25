<?php
/**
 * Envoie une notification push et un email aux administrateurs lors d'une nouvelle commande
 * @param string $numero_commande Numéro de la commande
 * @param float $montant_total Montant total en FCFA
 * @param int $nombre_articles Nombre d'articles
 * @param string $telephone_livraison Téléphone du client pour la livraison
 * @param string $adresse_livraison Adresse de livraison sélectionnée
 * @param array $produits Liste des produits [['nom' => ..., 'quantite' => ..., 'prix_unitaire' => ..., 'prix_total' => ...], ...]
 * @return void
 */
function send_new_commande_to_admin($numero_commande, $montant_total, $nombre_articles, $telephone_livraison = '', $adresse_livraison = '', $produits = []) {
    require_once __DIR__ . '/../models/model_admin.php';
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';

    $title = 'Nouvelle commande !';
    $body = "Commande #{$numero_commande} - " . number_format($montant_total, 0, ',', ' ') . " FCFA ({$nombre_articles} article(s))";

    require_once __DIR__ . '/../includes/site_url.php';
    $base_url = get_site_base_url();
    $link = $base_url . '/admin/commandes/index.php';

    $tokens = get_all_fcm_tokens_admin();
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, [
            'link' => $link,
            'numero_commande' => $numero_commande,
            'tag' => 'nouvelle-commande-' . $numero_commande
        ]);
    }

    $admin_emails = get_all_admin_emails();
    if (!empty($admin_emails) && function_exists('mail_send')) {
        $sujet = "[FOUTA POIDS LOURDS] Nouvelle commande #{$numero_commande}";
        $body_html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
        $body_html .= '<h2 style="color: #918a44;">Nouvelle commande reçue</h2>';
        $body_html .= '<p><strong>Numéro :</strong> ' . htmlspecialchars($numero_commande) . '</p>';
        $body_html .= '<p><strong>Montant total :</strong> ' . number_format($montant_total, 0, ',', ' ') . ' FCFA</p>';
        $body_html .= '<p><strong>Nombre d\'articles :</strong> ' . (int) $nombre_articles . '</p>';

        if (!empty($telephone_livraison)) {
            $body_html .= '<p><strong>Téléphone du client :</strong> ' . htmlspecialchars($telephone_livraison) . '</p>';
        }
        if (!empty($adresse_livraison)) {
            $body_html .= '<p><strong>Adresse de livraison :</strong> ' . htmlspecialchars($adresse_livraison) . '</p>';
        }

        if (!empty($produits)) {
            $body_html .= '<h3 style="color: #6b2f20; margin-top: 20px;">Produits commandés</h3>';
            $body_html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
            $body_html .= '<thead><tr style="background: #f5f5f5;"><th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Produit</th><th style="padding: 8px; text-align: center; border: 1px solid #ddd;">Qté</th><th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Options (variante, couleur, poids, taille)</th><th style="padding: 8px; text-align: right; border: 1px solid #ddd;">Prix unit.</th><th style="padding: 8px; text-align: right; border: 1px solid #ddd;">Total</th></tr></thead><tbody>';
            foreach ($produits as $p) {
                $details = [];
                if (!empty(trim($p['variante_nom'] ?? ''))) $details[] = 'Variante: ' . htmlspecialchars($p['variante_nom']);
                if (!empty(trim($p['couleur'] ?? ''))) $details[] = 'Couleur: ' . htmlspecialchars($p['couleur']);
                if (!empty(trim($p['poids'] ?? ''))) {
                    $poids_str = htmlspecialchars($p['poids']);
                    if (!empty($p['surcout_poids']) && $p['surcout_poids'] > 0) $poids_str .= ' (+' . number_format($p['surcout_poids'], 0, ',', ' ') . ' FCFA)';
                    $details[] = 'Poids: ' . $poids_str;
                }
                if (!empty(trim($p['taille'] ?? ''))) {
                    $taille_str = htmlspecialchars($p['taille']);
                    if (!empty($p['surcout_taille']) && $p['surcout_taille'] > 0) $taille_str .= ' (+' . number_format($p['surcout_taille'], 0, ',', ' ') . ' FCFA)';
                    $details[] = 'Taille: ' . $taille_str;
                }
                $details_str = !empty($details) ? implode(' — ', $details) : '—';
                $body_html .= '<tr>';
                $body_html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($p['nom'] ?? '') . '</td>';
                $body_html .= '<td style="padding: 8px; text-align: center; border: 1px solid #ddd;">' . (int) ($p['quantite'] ?? 0) . '</td>';
                $body_html .= '<td style="padding: 8px; border: 1px solid #ddd; font-size: 13px;">' . $details_str . '</td>';
                $body_html .= '<td style="padding: 8px; text-align: right; border: 1px solid #ddd;">' . number_format((float) ($p['prix_unitaire'] ?? 0), 0, ',', ' ') . ' FCFA</td>';
                $body_html .= '<td style="padding: 8px; text-align: right; border: 1px solid #ddd;">' . number_format((float) ($p['prix_total'] ?? 0), 0, ',', ' ') . ' FCFA</td>';
                $body_html .= '</tr>';
            }
            $body_html .= '</tbody></table>';
        }

        $body_html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($link) . '" style="display: inline-block; padding: 12px 24px; background: #918a44; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">Voir les commandes</a></p>';
        $body_html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
        $body_html .= '<p style="font-size: 12px; color: #999;">FOUTA POIDS LOURDS - Pièces poids lourds</p>';
        $body_html .= '</div>';

        foreach ($admin_emails as $email) {
            if (!empty(trim($email))) {
                mail_send(trim($email), $sujet, $body_html, true);
            }
        }
    }
}
