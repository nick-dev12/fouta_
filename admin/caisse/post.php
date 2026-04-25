<?php
/**
 * Actions POST caisse (CSRF, redirections)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_caisse()) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse.php';

$tok = $_POST['csrf_token'] ?? '';
if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), (string) $tok)) {
    $_SESSION['caisse_flash_error'] = 'Session expirée ou jeton de sécurité invalide. Réessayez.';
    header('Location: index.php');
    exit;
}

$action = $_POST['caisse_action'] ?? '';

$cart = caisse_cart_get();

function caisse_redirect_ok($query = '')
{
    $url = 'index.php' . ($query !== '' ? '?' . $query : '');
    header('Location: ' . $url);
    exit;
}

if ($action === 'add_scan') {
    $code = trim((string) ($_POST['code'] ?? ''));
    $qty = max(1, (int) ($_POST['quantite'] ?? 1));
    /* Anti double envoi : certains scanners ou USB renvoient deux Entrées quasi instantanés pour le même code */
    if ($code !== '') {
        $norm = strtoupper(preg_replace('/\s+/u', '', $code));
        $now = microtime(true);
        $last = $_SESSION['caisse_last_scan'] ?? null;
        if (is_array($last) && isset($last['norm'], $last['t'])
            && $last['norm'] === $norm && ($now - (float) $last['t']) < 0.45) {
            caisse_redirect_ok();
        }
        $_SESSION['caisse_last_scan'] = ['norm' => $norm, 't' => $now];
    }
    $ticket_scan_id = caisse_trouver_vente_id_par_code_scan($code);
    if ($ticket_scan_id > 0) {
        if (admin_current_role() === 'caissier') {
            header('Location: encaisser-ticket.php?ticket=' . $ticket_scan_id);
        } else {
            header('Location: index.php?ticket=' . $ticket_scan_id);
        }
        exit;
    }
    $res = caisse_resoudre_produit_par_code($code);
    if (!$res['ok']) {
        $_SESSION['caisse_flash_error'] = $res['error'] ?? 'Erreur.';
        caisse_redirect_ok();
    }
    $add = caisse_cart_add_produit($cart, $res['produit'], $qty);
    if (!$add['ok']) {
        $_SESSION['caisse_flash_error'] = $add['error'] ?? 'Erreur.';
    }
    caisse_cart_save($cart);
    caisse_redirect_ok();
}

if ($action === 'add_product') {
    $pid = (int) ($_POST['produit_id'] ?? 0);
    $qty = max(1, (int) ($_POST['quantite'] ?? 1));
    require_once __DIR__ . '/../../models/model_produits.php';
    $p = get_produit_by_id($pid);
    if (!$p) {
        $_SESSION['caisse_flash_error'] = 'Produit introuvable.';
        caisse_redirect_ok();
    }
    $add = caisse_cart_add_produit($cart, $p, $qty);
    if (!$add['ok']) {
        $_SESSION['caisse_flash_error'] = $add['error'] ?? 'Erreur.';
    }
    caisse_cart_save($cart);
    caisse_redirect_ok();
}

if ($action === 'update_qty') {
    $key = trim((string) ($_POST['line_key'] ?? ''));
    $qty = (int) ($_POST['quantite'] ?? 0);
    if (isset($cart['lines'][$key]) && $qty > 0) {
        require_once __DIR__ . '/../../models/model_produits.php';
        $pid = (int) ($cart['lines'][$key]['produit_id'] ?? 0);
        $p = get_produit_by_id($pid);
        if ($p) {
            $stock = (int) ($p['stock'] ?? 0);
            if ($qty > $stock) {
                $_SESSION['caisse_flash_error'] = 'Quantité supérieure au stock disponible (' . $stock . ').';
            } else {
                $cart['lines'][$key]['quantite'] = $qty;
                $cart['lines'][$key]['prix_unitaire'] = caisse_prix_unitaire_produit($p);
            }
        }
    }
    caisse_cart_save($cart);
    caisse_redirect_ok();
}

if ($action === 'qty_step') {
    $key = trim((string) ($_POST['line_key'] ?? ''));
    $dir = trim((string) ($_POST['dir'] ?? ''));
    if (isset($cart['lines'][$key]) && ($dir === 'plus' || $dir === 'minus')) {
        require_once __DIR__ . '/../../models/model_produits.php';
        $pid = (int) ($cart['lines'][$key]['produit_id'] ?? 0);
        $p = get_produit_by_id($pid);
        $cur = (int) ($cart['lines'][$key]['quantite'] ?? 0);
        if ($p) {
            $stock = (int) ($p['stock'] ?? 0);
            if ($dir === 'plus') {
                if ($cur >= $stock) {
                    $_SESSION['caisse_flash_error'] = 'Stock maximum atteint (' . $stock . ').';
                } else {
                    $cart['lines'][$key]['quantite'] = $cur + 1;
                    $cart['lines'][$key]['prix_unitaire'] = caisse_prix_unitaire_produit($p);
                }
            } else {
                if ($cur <= 1) {
                    unset($cart['lines'][$key]);
                } else {
                    $cart['lines'][$key]['quantite'] = $cur - 1;
                    $cart['lines'][$key]['prix_unitaire'] = caisse_prix_unitaire_produit($p);
                }
            }
        }
    }
    caisse_cart_save($cart);
    caisse_redirect_ok();
}

if ($action === 'preview_monnaie') {
    if (admin_current_role() === 'commercial') {
        caisse_redirect_ok();
    }
    $mr_raw = isset($_POST['montant_recu']) ? trim((string) $_POST['montant_recu']) : '';
    $_SESSION['caisse_preview_recu'] = $mr_raw !== ''
        ? (float) str_replace(',', '.', $mr_raw)
        : null;
    caisse_redirect_ok();
}

if ($action === 'remove_line') {
    $key = trim((string) ($_POST['line_key'] ?? ''));
    if (isset($cart['lines'][$key])) {
        unset($cart['lines'][$key]);
    }
    caisse_cart_save($cart);
    caisse_redirect_ok();
}

if ($action === 'set_remise_globale') {
    $pct = (float) ($_POST['remise_globale_pct'] ?? 0);
    $cart['remise_globale_pct'] = min(100, max(0, $pct));
    caisse_cart_save($cart);
    caisse_redirect_ok();
}

if ($action === 'set_remise_ligne') {
    $key = trim((string) ($_POST['line_key'] ?? ''));
    $pct = (float) ($_POST['remise_ligne_pct'] ?? 0);
    if (isset($cart['lines'][$key])) {
        $cart['lines'][$key]['remise_ligne_pct'] = min(100, max(0, $pct));
    }
    caisse_cart_save($cart);
    caisse_redirect_ok();
}

if ($action === 'clear_cart') {
    caisse_cart_clear();
    unset($_SESSION['caisse_preview_recu']);
    $_SESSION['caisse_flash_success'] = 'Vente annulée — panier vidé.';
    caisse_redirect_ok();
}

if ($action === 'generer_ticket') {
    if (!admin_can_caisse_vendeur()) {
        $_SESSION['caisse_flash_error'] = 'Action non autorisée.';
        caisse_redirect_ok();
    }
    $res = caisse_creer_ticket_en_attente((int) $_SESSION['admin_id'], $cart);
    if (!$res['ok']) {
        $_SESSION['caisse_flash_error'] = $res['error'] ?? 'Erreur lors de la génération du ticket.';
        caisse_cart_save($cart);
        caisse_redirect_ok();
    }
    $msg_ok = 'Ticket généré : ' . ($res['numero_ticket'] ?? '') . '.';
    if (!empty($res['reference_caisse'])) {
        $msg_ok .= ' Réf. caisse : ' . $res['reference_caisse'] . ' (recherche rapide à l’encaissement).';
    }
    $msg_ok .= ' Le panier reste actif — utilisez le lien ci-dessous pour afficher le ticket à imprimer.';
    $_SESSION['caisse_flash_success'] = $msg_ok;
    $_SESSION['caisse_last_ticket_id'] = (int) ($res['vente_id'] ?? 0);
    caisse_redirect_ok();
}

if ($action === 'encaisser') {
    if (admin_current_role() !== 'admin') {
        $_SESSION['caisse_flash_error'] = 'L’encaissement depuis le bureau vendeur est réservé aux administrateurs. Les commerciaux génèrent un ticket ; le caissier l’encaisse.';
        caisse_cart_save($cart);
        caisse_redirect_ok();
    }
    $mode = trim((string) ($_POST['mode_paiement'] ?? 'especes'));
    $totals = caisse_compute_totals($cart);
    $total = $totals['total'];

    $montant_recu = isset($_POST['montant_recu']) && $_POST['montant_recu'] !== '' ? (float) str_replace(',', '.', (string) $_POST['montant_recu']) : null;
    $montant_especes = isset($_POST['montant_especes']) && $_POST['montant_especes'] !== '' ? (float) str_replace(',', '.', (string) $_POST['montant_especes']) : null;
    $montant_carte = isset($_POST['montant_carte']) && $_POST['montant_carte'] !== '' ? (float) str_replace(',', '.', (string) $_POST['montant_carte']) : null;
    $montant_mobile_money = isset($_POST['montant_mobile_money']) && $_POST['montant_mobile_money'] !== '' ? (float) str_replace(',', '.', (string) $_POST['montant_mobile_money']) : null;
    $notes = trim((string) ($_POST['notes_vente'] ?? ''));

    $paiement = [
        'montant_recu' => $montant_recu,
        'montant_especes' => $montant_especes,
        'montant_carte' => $montant_carte,
        'montant_mobile_money' => $montant_mobile_money,
        'notes' => $notes,
    ];

    if ($mode === 'especes' && $montant_recu !== null) {
        $paiement['monnaie_rendue'] = max(0, round($montant_recu - $total, 2));
    }

    $res = caisse_enregistrer_vente((int) $_SESSION['admin_id'], $cart, $mode, $paiement);
    if (!$res['ok']) {
        $_SESSION['caisse_flash_error'] = $res['error'] ?? 'Erreur encaissement.';
        caisse_cart_save($cart);
        caisse_redirect_ok();
    }

    caisse_cart_clear();
    unset($_SESSION['caisse_preview_recu']);
    $_SESSION['caisse_flash_success'] = 'Vente enregistrée. Ticket n° ' . ($res['numero_ticket'] ?? '') . '.';
    caisse_redirect_ok('ticket=' . (int) ($res['vente_id'] ?? 0));
}

if ($action === 'finaliser_ticket') {
    if (!admin_can_encaisser_ticket()) {
        $_SESSION['caisse_flash_error'] = 'Action non autorisée.';
        header('Location: encaisser-ticket.php');
        exit;
    }
    $vente_id = (int) ($_POST['vente_id'] ?? 0);
    $mode = trim((string) ($_POST['mode_paiement'] ?? 'especes'));
    $totals_chk = null;

    $montant_recu = isset($_POST['montant_recu']) && $_POST['montant_recu'] !== '' ? (float) str_replace(',', '.', (string) $_POST['montant_recu']) : null;
    $montant_especes = isset($_POST['montant_especes']) && $_POST['montant_especes'] !== '' ? (float) str_replace(',', '.', (string) $_POST['montant_especes']) : null;
    $montant_carte = isset($_POST['montant_carte']) && $_POST['montant_carte'] !== '' ? (float) str_replace(',', '.', (string) $_POST['montant_carte']) : null;
    $montant_mobile_money = isset($_POST['montant_mobile_money']) && $_POST['montant_mobile_money'] !== '' ? (float) str_replace(',', '.', (string) $_POST['montant_mobile_money']) : null;
    $notes = trim((string) ($_POST['notes_vente'] ?? ''));

    $paiement = [
        'montant_recu' => $montant_recu,
        'montant_especes' => $montant_especes,
        'montant_carte' => $montant_carte,
        'montant_mobile_money' => $montant_mobile_money,
        'notes' => $notes,
    ];

    $vpre = caisse_get_vente_by_id($vente_id);
    if ($vpre) {
        $cart_chk = ['lines' => [], 'remise_globale_pct' => (float) ($vpre['remise_globale_pct'] ?? 0)];
        foreach ($vpre['lignes'] as $lg) {
            $k = caisse_line_key((int) $lg['produit_id']);
            $cart_chk['lines'][$k] = [
                'produit_id' => (int) $lg['produit_id'],
                'nom' => $lg['designation'] ?? '',
                'prix_unitaire' => (float) ($lg['prix_unitaire'] ?? 0),
                'quantite' => (int) ($lg['quantite'] ?? 0),
                'remise_ligne_pct' => (float) ($lg['remise_ligne_pct'] ?? 0),
            ];
        }
        $totals_chk = caisse_compute_totals($cart_chk);
    }
    $total = $totals_chk ? (float) $totals_chk['total'] : 0;

    if ($mode === 'especes' && $montant_recu !== null) {
        $paiement['monnaie_rendue'] = max(0, round($montant_recu - $total, 2));
    }

    $res = caisse_finaliser_vente_en_attente($vente_id, (int) $_SESSION['admin_id'], $mode, $paiement);
    if (!$res['ok']) {
        $_SESSION['caisse_flash_error'] = $res['error'] ?? 'Erreur encaissement.';
        header('Location: encaisser-ticket.php?ticket=' . $vente_id);
        exit;
    }
    $_SESSION['caisse_flash_success'] = 'Encaissement enregistré. Ticket n° ' . ($res['numero_ticket'] ?? '') . '.';
    header('Location: encaisser-ticket.php?ticket=' . (int) ($res['vente_id'] ?? 0) . '&imprimer=1');
    exit;
}

header('Location: index.php');
exit;
