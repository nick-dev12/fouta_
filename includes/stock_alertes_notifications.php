<?php
/**
 * Notifications email lors du franchissement d’un seuil d’alerte (baisse de stock).
 */
require_once __DIR__ . '/../models/model_stock_alertes.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../services/mail.php';

/**
 * Envoie les mails si le stock franchit un ou plusieurs seuils à la baisse.
 *
 * @param int $produit_id
 * @param int $stock_avant Stock avant l’opération (ex. PHP_INT_MAX pour création en alerte)
 * @param int $stock_apres Stock après l’opération
 */
function stock_alertes_notifier_baisse_stock($produit_id, $stock_avant, $stock_apres)
{
    $pid = (int) $produit_id;
    if ($pid <= 0) {
        return;
    }
    if (!stock_alertes_tables_ok()) {
        return;
    }
    $regles = stock_alertes_get_regles_pour_produit($pid);
    if (empty($regles)) {
        return;
    }
    $franchies = stock_alertes_regles_franchies($stock_avant, $stock_apres, $regles);
    if (empty($franchies)) {
        return;
    }

    $produit = get_produit_by_id($pid);
    if (!$produit) {
        return;
    }
    $nom = (string) ($produit['nom'] ?? 'Produit #' . $pid);

    /* CELUI QUI FAIT LE GESTE L'APPREND (31/08) — le bandeau existant ne vit
     * que sur quatre écrans, dont aucun n'est ouvert au rayonniste : il sortait
     * une pièce, la faisait passer sous son seuil, et n'en savait rien. On
     * dépose le franchissement dans la session ; nav.php l'affiche sur l'écran
     * suivant, une seule fois, à la personne qui vient de le provoquer. Le
     * courriel, lui, part comme avant vers les comptes concernés. */
    if (session_status() === PHP_SESSION_ACTIVE) {
        $seuil_le_plus_haut = 0;
        $niveau_le_plus_grave = 'standard';
        foreach ($franchies as $r_flash) {
            $seuil_le_plus_haut = max($seuil_le_plus_haut, (int) ($r_flash['seuil'] ?? 0));
            if (stock_alertes_gravite_niveau($r_flash['niveau'] ?? '') > stock_alertes_gravite_niveau($niveau_le_plus_grave)) {
                $niveau_le_plus_grave = (string) $r_flash['niveau'];
            }
        }
        $_SESSION['stock_alerte_franchie'][] = [
            'nom' => $nom,
            'stock' => (int) $stock_apres,
            'seuil' => $seuil_le_plus_haut,
            'niveau' => $niveau_le_plus_grave,
        ];
    }
    $emails = get_admin_emails_alerte_stock();
    if (empty($emails)) {
        return;
    }

    $avant_lbl = $stock_avant > 999999999 ? 'nouveau catalogue' : (string) (int) $stock_avant;

    $lines = '';
    foreach ($franchies as $r) {
        $scope = isset($r['scope_libelle']) ? (string) $r['scope_libelle'] : '';
        $lines .= '<li><strong>' . htmlspecialchars(stock_alertes_libelle_niveau($r['niveau']), ENT_QUOTES, 'UTF-8')
            . '</strong> — seuil ≤ ' . (int) $r['seuil'];
        if ($scope !== '') {
            $lines .= ' <span style="color:#666;">(' . htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') . ')</span>';
        }
        $lines .= '</li>';
    }

    $body = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:0 auto;">';
    $body .= '<h2 style="color:#3564a6;">Alerte stock</h2>';
    $body .= '<p>Le produit <strong>' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . '</strong> (id ' . $pid . ') ';
    $body .= 'a un stock actuel de <strong>' . (int) $stock_apres . '</strong> unité(s).</p>';
    $body .= '<p>Référence stock précédente : <strong>' . htmlspecialchars($avant_lbl, ENT_QUOTES, 'UTF-8') . '</strong>.</p>';
    $body .= '<p>Seuil(s) franchi(s)&nbsp;:</p><ul>' . $lines . '</ul>';
    $body .= '<p style="font-size:13px;color:#666;">Connectez-vous à l’administration pour réapprovisionner ou ajuster le stock.</p>';
    $body .= '</div>';

    $sujet = '[Alerte stock] ' . $nom . ' — ' . (int) $stock_apres . ' u.';

    foreach ($emails as $to) {
        if ($to === '') {
            continue;
        }
        mail_send($to, $sujet, $body, true);
    }
}
