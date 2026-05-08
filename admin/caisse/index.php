<?php
/**
 * Caisse magasin — zones A (scan/recherche), B (panier), C (résumé + paiement)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (admin_current_role() === 'caissier') {
    header('Location: encaisser-ticket.php');
    exit;
}
if (!admin_can_caisse_vendeur()) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse.php';
require_once __DIR__ . '/../../models/model_caisse_compta.php';
require_once __DIR__ . '/../../includes/barcode_caisse_ticket.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';

$cart = caisse_cart_get();
if (admin_current_role() === 'commercial' && !empty($cart['inclure_tva'])) {
    $cart['inclure_tva'] = 0;
    caisse_cart_save($cart);
    $cart = caisse_cart_get();
}
$totals = caisse_compute_totals($cart);
$total_ttc = (float) ($totals['total_ttc'] ?? $totals['total'] ?? 0);
$total_ht = (float) ($totals['total_ht'] ?? 0);
$montant_tva = (float) ($totals['montant_tva'] ?? 0);
$taux_tva = (float) ($totals['taux_tva_pourcent'] ?? CAISSE_TVA_TAUX_POURCENT);

$flash_ok = '';
$flash_err = '';
if (!empty($_SESSION['caisse_flash_success'])) {
    $flash_ok = (string) $_SESSION['caisse_flash_success'];
    unset($_SESSION['caisse_flash_success']);
}
if (!empty($_SESSION['caisse_flash_error'])) {
    $flash_err = (string) $_SESSION['caisse_flash_error'];
    unset($_SESSION['caisse_flash_error']);
}

$caisse_last_ticket_notice = 0;
if (!empty($_SESSION['caisse_last_ticket_id'])) {
    $caisse_last_ticket_notice = (int) $_SESSION['caisse_last_ticket_id'];
    unset($_SESSION['caisse_last_ticket_id']);
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$cat = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$cat = $cat > 0 ? $cat : null;

$show_catalogue = ($q !== '' || $cat !== null);
$produits_liste = $show_catalogue ? search_produits_with_filters($q, null, null, $cat, 'nom', 0, 60) : [];
$categories = get_all_categories();

$has_ident = function_exists('produits_has_column') && produits_has_column('identifiant_interne');

/** Catalogue actif (stock > 0) pour filtrage temps réel côté navigateur — limite pour poids de page */
$caisse_catalog_json = [];
$caisse_catalog_limit = 2500;
$caisse_catalog_rows = search_produits_with_filters('', null, null, null, 'nom', 0, $caisse_catalog_limit);
foreach ($caisse_catalog_rows as $pr) {
    if ((int) ($pr['stock'] ?? 0) <= 0) {
        continue;
    }
    $caisse_catalog_json[] = [
        'id' => (int) ($pr['id'] ?? 0),
        'nom' => (string) ($pr['nom'] ?? ''),
        'ref' => $has_ident ? strtoupper(trim((string) ($pr['identifiant_interne'] ?? ''))) : '',
        'cat_id' => (int) ($pr['categorie_id'] ?? 0),
        'prix' => round((float) caisse_prix_unitaire_produit($pr), 2),
        'stock' => (int) ($pr['stock'] ?? 0),
    ];
}
$caisse_catalog_json_script = json_encode($caisse_catalog_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
if ($caisse_catalog_json_script === false) {
    $caisse_catalog_json_script = '[]';
}

$ticket_id = isset($_GET['ticket']) ? (int) $_GET['ticket'] : 0;
$ticket_data = ($ticket_id > 0) ? caisse_get_vente_by_id($ticket_id) : null;
$ticket_introuvable = ($ticket_id > 0 && !$ticket_data);
$ticket_recap = $ticket_data ? caisse_vente_recap_fiscal_affichage($ticket_data) : null;
$ticket_show_tva_row = $ticket_recap && abs((float) ($ticket_recap['tva'] ?? 0)) >= 0.005;
$ticket_statut = $ticket_data ? caisse_vente_statut($ticket_data) : null;
$masquer_zone_paiement_commercial = in_array(admin_current_role(), ['commercial', 'commercial_general'], true);
/** Option « Inclure la TVA » : masquée pour le rôle commercial seul ; visible pour commercial_general et les autres */
$afficher_option_tva_caisse = admin_current_role() !== 'commercial';
$ticket_barcode_src = $ticket_data ? caisse_ticket_get_barcode_web_path($ticket_data) : '';
$ticket_barcode_payload = $ticket_data ? caisse_ticket_valeur_code_barres($ticket_data) : '';

$tables_ok = caisse_tables_exist();
$page_title = 'Caisse';

$preview_recu = isset($_SESSION['caisse_preview_recu']) ? $_SESSION['caisse_preview_recu'] : null;
if ($preview_recu !== null && !is_numeric($preview_recu)) {
    $preview_recu = null;
}
$monnaie_preview = null;
$manque_preview = null;
if ($preview_recu !== null && $total_ttc > 0) {
    if ($preview_recu + 0.001 >= $total_ttc) {
        $monnaie_preview = max(0, round((float) $preview_recu - $total_ttc, 2));
    } else {
        $manque_preview = round($total_ttc - (float) $preview_recu, 2);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard-caisse-pages.css<?php echo asset_version_query(); ?>">
</head>

<body class="admin-caisse-page<?php echo $masquer_zone_paiement_commercial ? ' admin-caisse-commercial' : ''; ?><?php echo ($ticket_data && $ticket_recap) ? ' caisse-modal-open' : ''; ?>">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="caisse-page-wrap">
        <header class="caisse-page-head">
            <div class="caisse-page-head-inner">
                <h1 class="caisse-page-title"><i class="fas fa-cash-register"></i>
                    <?php echo htmlspecialchars($page_title); ?></h1>
            </div>
        </header>

        <?php if (!$tables_ok): ?>
        <div class="caisse-banner caisse-banner--warn">
            <i class="fas fa-database"></i>
            <span>Tables caisse absentes — exécutez <code>migrations/create_caisse_tables.sql</code> pour enregistrer
                les ventes.</span>
        </div>
        <?php endif; ?>

        <?php if ($flash_ok !== ''): ?>
        <div class="caisse-banner caisse-banner--ok"><?php echo htmlspecialchars($flash_ok); ?></div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
        <div class="caisse-banner caisse-banner--err"><?php echo htmlspecialchars($flash_err); ?></div>
        <?php endif; ?>

        <?php if ($caisse_last_ticket_notice > 0): ?>
        <div class="caisse-banner caisse-banner--ok caisse-banner--ticket-link no-print">
            <span><i class="fas fa-ticket-alt"></i> Dernier ticket généré :</span>
            <a class="caisse-banner-ticket-link" href="index.php?ticket=<?php echo $caisse_last_ticket_notice; ?>">Afficher pour impression</a>
        </div>
        <?php endif; ?>

        <?php if ($ticket_introuvable): ?>
        <div class="caisse-banner caisse-banner--warn">
            Ticket introuvable. <a href="index.php">Retour à la caisse</a>
        </div>
        <?php endif; ?>

        <?php if ($ticket_data && $ticket_recap): ?>
        <div id="caisseTicketViewModal" class="caisse-ticket-view-modal caisse-ticket-view-modal--fullscreen is-open"
            role="dialog" aria-modal="true" aria-labelledby="caisseTicketViewTitleVendeur" aria-hidden="false">
            <a href="index.php" class="caisse-ticket-view-modal__backdrop no-print"
                aria-label="Fermer l'aperçu du ticket"></a>
            <div class="caisse-ticket-view-modal__panel">
                <div class="caisse-ticket-view-modal__head no-print">
                    <h2 id="caisseTicketViewTitleVendeur" class="caisse-ticket-view-modal__title"><i class="fas fa-receipt"
                            aria-hidden="true"></i> Ticket généré</h2>
                    <a href="index.php" class="caisse-ticket-view-modal__close" aria-label="Fermer"><i
                            class="fas fa-times"></i></a>
                </div>
        <div class="caisse-ticket-card" id="ticket-print-zone">
            <div class="caisse-ticket-brand">FOUTA POIDS LOURDS</div>
            <div class="caisse-ticket-head">
                <strong><?php echo htmlspecialchars(caisse_ticket_numero_date_public($ticket_data)); ?></strong>
                <span><?php echo isset($ticket_data['date_vente']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($ticket_data['date_vente']))) : ''; ?></span>
            </div>
            <?php if ($ticket_statut === 'en_attente' && isset($ticket_data['reference']) && (string) $ticket_data['reference'] !== ''): ?>
            <p class="caisse-ticket-ref-caisse">Ref : <strong><code><?php echo htmlspecialchars((string) $ticket_data['reference']); ?></code></strong></p>
            <?php endif; ?>
            <p class="caisse-ticket-meta">
                Préparé par :
                <?php echo htmlspecialchars(trim(($ticket_data['admin_prenom'] ?? '') . ' ' . ($ticket_data['admin_nom'] ?? ''))); ?>
            </p>
            <?php if ($ticket_statut === 'paye' && trim(($ticket_data['caissier_prenom'] ?? '') . ' ' . ($ticket_data['caissier_nom'] ?? '')) !== ''): ?>
            <p class="caisse-ticket-meta">
                Encaissé par :
                <?php echo htmlspecialchars(trim(($ticket_data['caissier_prenom'] ?? '') . ' ' . ($ticket_data['caissier_nom'] ?? ''))); ?>
            </p>
            <?php endif; ?>
            <table class="caisse-ticket-table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Qté</th>
                        <th>PU HT</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ticket_data['lignes'] as $lg): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lg['designation'] ?? ''); ?></td>
                        <td><?php echo (int) ($lg['quantite'] ?? 0); ?></td>
                        <td><?php echo number_format((float) ($lg['prix_unitaire'] ?? 0), 0, ',', ' '); ?></td>
                        <td><?php echo number_format((float) ($lg['total_ligne'] ?? 0), 0, ',', ' '); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="caisse-ticket-tva-block">
                <div class="caisse-ticket-row"><span>Total
                        HT</span><strong><?php echo number_format($ticket_recap['ht'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <?php if ($ticket_show_tva_row): ?>
                <div class="caisse-ticket-row"><span>TVA
                        (<?php echo htmlspecialchars((string) CAISSE_TVA_TAUX_POURCENT); ?>
                        %)</span><strong><?php echo number_format($ticket_recap['tva'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <?php endif; ?>
                <div class="caisse-ticket-row caisse-ticket-row--total"><span><?php echo $ticket_show_tva_row ? 'Total TTC' : 'Total à payer'; ?></span><strong><?php echo number_format($ticket_recap['ttc'], 0, ',', ' '); ?> FCFA</strong>
                </div>
            </div>
            <?php if ($ticket_barcode_src !== ''): ?>
            <div class="caisse-ticket-barcode-block">
                <div class="caisse-ticket-barcode-wrap">
                    <img src="<?php echo htmlspecialchars($ticket_barcode_src); ?>?v=<?php echo (int) ($ticket_data['id'] ?? 0); ?>"
                        alt="Code-barres ticket <?php echo htmlspecialchars($ticket_barcode_payload); ?>"
                        class="caisse-ticket-barcode-img">
                </div>
                <div class="caisse-ticket-barcode-value"><?php echo htmlspecialchars($ticket_barcode_payload); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($ticket_statut === 'paye'): ?>
            <p class="caisse-ticket-pay">Paiement :
                <strong><?php echo htmlspecialchars(caisse_compta_libelle_paiement_ticket($ticket_data)); ?></strong></p>
            <?php endif; ?>
            <div class="caisse-ticket-actions no-print">
                <button type="button" class="btn-primary" id="btnPrintTicket"><i class="fas fa-print"></i>
                    Imprimer</button>
                <?php if ($ticket_statut === 'en_attente'): ?>
                <a href="index.php" class="btn-secondary">Continuer la vente</a>
                <?php else: ?>
                <a href="index.php" class="btn-secondary">Nouvelle vente</a>
                <?php endif; ?>
            </div>
            </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="caisse-shell">

            <!-- ——— Zone A : scan + recherche ——— -->
            <section class="caisse-zone caisse-zone--a" aria-label="Recherche catalogue et scan">
                <div class="caisse-zone-a-grid">
                    <div class="caisse-search-card caisse-search-card--primary">
                        <h2 class="caisse-catalog-title"><i class="fas fa-search" aria-hidden="true"></i> Recherche catalogue</h2>
                        <form method="get" action="index.php" class="caisse-search-form" id="caisse-search-form-get">
                            <div class="caisse-search-fields">
                                <input type="search" name="q" id="caisse_q_live" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Nom, mot-clé, réf. FPL…" class="caisse-search-input caisse-search-input--live"
                                    aria-label="Recherche produit" autocomplete="off" autofocus>
                                <select name="cat" id="caisse_cat_live" class="caisse-search-select" aria-label="Catégorie">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo (int) $c['id']; ?>"
                                        <?php echo ($cat !== null && (int) $c['id'] === $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-primary caisse-search-btn">Rechercher (liste complète)</button>
                            </div>
                        </form>
                        <div class="caisse-live-results-wrap">
                            <span id="caisse-live-results-label" class="visually-hidden">Suggestions produits</span>
                            <div id="caisse-live-results" class="caisse-live-results" role="listbox" aria-labelledby="caisse-live-results-label"
                                data-csrf="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-has-ident="<?php echo $has_ident ? '1' : '0'; ?>" hidden></div>
                        </div>
                    </div>

                    <div class="caisse-scan-card caisse-scan-card--compact">
                        <span class="caisse-zone-label"><i class="fas fa-barcode"></i> A · Code-barres &amp; référence</span>
                        <form method="post" action="post.php" class="caisse-scan-form">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="caisse_action" value="add_scan">
                            <input type="hidden" name="quantite" value="1">
                            <label class="visually-hidden" for="code_scan">Code ou recherche</label>
                            <div class="caisse-scan-input-wrap">
                                <i class="fas fa-keyboard caisse-scan-icon" aria-hidden="true"></i>
                                <input type="text" name="code" id="code_scan" class="caisse-scan-input"
                                    placeholder="FPL, code ticket TKT…, ID produit…" autocomplete="off">
                                <button type="submit" class="caisse-scan-submit" title="Valider (Entrée)"><i
                                        class="fas fa-arrow-right"></i></button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($show_catalogue): ?>
                <div class="caisse-results-wrap">
                    <table class="caisse-results-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <?php if ($has_ident): ?><th>Référence</th><?php endif; ?>
                                <th>Prix HT</th>
                                <th>Stock</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                        $any_row = false;
                        foreach ($produits_liste as $pr):
                            $pu = caisse_prix_unitaire_produit($pr);
                            $stk = (int) ($pr['stock'] ?? 0);
                            if ($stk <= 0) {
                                continue;
                            }
                            $any_row = true;
                        ?>
                            <tr>
                                <td class="caisse-results-nom"><?php echo htmlspecialchars($pr['nom'] ?? ''); ?></td>
                                <?php if ($has_ident): ?>
                                <td><code
                                        class="caisse-ref"><?php echo htmlspecialchars(trim($pr['identifiant_interne'] ?? '') ?: '—'); ?></code>
                                </td>
                                <?php endif; ?>
                                <td><?php echo number_format($pu, 0, ',', ' '); ?></td>
                                <td><?php echo $stk; ?></td>
                                <td class="caisse-results-act">
                                    <form method="post" action="post.php" class="caisse-inline-add">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                        <input type="hidden" name="caisse_action" value="add_product">
                                        <input type="hidden" name="produit_id" value="<?php echo (int) $pr['id']; ?>">
                                        <input type="hidden" name="quantite" value="1">
                                        <button type="submit" class="btn-add-line"><i class="fas fa-plus"></i>
                                            Ajouter</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$any_row): ?>
                            <tr>
                                <td colspan="<?php echo $has_ident ? 5 : 4; ?>" class="caisse-results-empty">Aucun
                                    article en stock pour cette recherche.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <div class="caisse-split">

                <!-- ——— Zone B : panier ——— -->
                <section class="caisse-zone caisse-zone--b" aria-label="Panier">
                    <div class="caisse-zone-b-head">
                        <h2 class="caisse-zone-title"><i class="fas fa-shopping-basket"></i> B · Panier</h2>
                        <?php if (!empty($cart['lines'])): ?>
                        <form method="post" action="post.php" class="caisse-annuler-form"
                            onsubmit="return confirm('Annuler toute la vente en cours ? Le panier sera vidé.');">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="caisse_action" value="clear_cart">
                            <button type="submit" class="btn-annuler-vente"><i class="fas fa-times-circle"></i> Annuler
                                la vente</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($cart['lines'])): ?>
                    <div class="caisse-panier-vide">
                        <i class="fas fa-cart-arrow-down"></i>
                        <p>Panier vide</p>
                        <p class="caisse-panier-vide-hint">Scannez un code ou recherchez un produit ci-dessus.</p>
                    </div>
                    <?php else: ?>

                    <div class="caisse-table-scroll">
                        <table class="caisse-cart-table">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Prix HT</th>
                                    <th>Quantité</th>
                                    <th>Total</th>
                                    <th class="caisse-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart['lines'] as $key => $line):
                                $pu = (float) ($line['prix_unitaire'] ?? 0);
                                $q = (int) ($line['quantite'] ?? 0);
                                $rl = (float) ($line['remise_ligne_pct'] ?? 0);
                                $tl = $pu * $q * (1 - min(100, max(0, $rl)) / 100);
                                $p_stock = get_produit_by_id((int) ($line['produit_id'] ?? 0));
                                $stock_dispo = $p_stock ? (int) ($p_stock['stock'] ?? 0) : $q;
                                $ref_produit = '';
                                if ($has_ident && $p_stock && trim((string) ($p_stock['identifiant_interne'] ?? '')) !== '') {
                                    $ref_produit = strtoupper(trim((string) $p_stock['identifiant_interne']));
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div class="caisse-cart-produit-cell">
                                            <div class="caisse-cart-produit-line1">
                                                <span
                                                    class="caisse-cart-nom"><?php echo htmlspecialchars($line['nom'] ?? ''); ?></span>
                                                <?php if ($rl > 0): ?><span
                                                    class="caisse-cart-badge">−<?php echo htmlspecialchars((string) $rl); ?>%</span><?php endif; ?>
                                            </div>
                                            <?php if ($ref_produit !== ''): ?>
                                            <span class="caisse-cart-ref"><code><?php echo htmlspecialchars($ref_produit); ?></code></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($pu, 0, ',', ' '); ?></td>
                                    <td>
                                        <div class="caisse-qty-cell caisse-qty-cell--solo">
                                            <form method="post" action="post.php" class="caisse-qty-set-form">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                                <input type="hidden" name="caisse_action" value="update_qty">
                                                <input type="hidden" name="line_key"
                                                    value="<?php echo htmlspecialchars($key); ?>">
                                                <label class="visually-hidden" for="qty_<?php echo htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '_', $key)); ?>">Quantité</label>
                                                <input type="number" name="quantite" id="qty_<?php echo htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '_', $key)); ?>"
                                                    class="caisse-qty-input" min="1" max="<?php echo max(1, $stock_dispo); ?>"
                                                    value="<?php echo $q; ?>" inputmode="numeric" required>
                                                <button type="submit" class="caisse-qty-apply btn-secondary btn-sm" title="Appliquer la quantité saisie">OK</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="caisse-cart-total-ligne">
                                        <strong><?php echo number_format($tl, 0, ',', ' '); ?></strong></td>
                                    <td>
                                        <form method="post" action="post.php">
                                            <input type="hidden" name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                            <input type="hidden" name="caisse_action" value="remove_line">
                                            <input type="hidden" name="line_key"
                                                value="<?php echo htmlspecialchars($key); ?>">
                                            <button type="submit" class="caisse-btn-remove"
                                                title="Supprimer la ligne"><i class="fas fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <details class="caisse-remise-globale-box">
                        <summary>Remise sur ticket (%)</summary>
                        <form method="post" action="post.php" class="caisse-remise-globale-form">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="caisse_action" value="set_remise_globale">
                            <input type="number" name="remise_globale_pct"
                                value="<?php echo htmlspecialchars((string) $totals['remise_globale_pct']); ?>" min="0"
                                max="100" step="0.5" class="caisse-remise-input">
                            <button type="submit" class="btn-secondary btn-sm">Appliquer</button>
                        </form>
                    </details>

                    <?php if ($afficher_option_tva_caisse): ?>
                    <div class="caisse-tva-option">
                        <form method="post" action="post.php" class="caisse-tva-option-form">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="caisse_action" value="set_inclure_tva">
                            <input type="hidden" name="inclure_tva" value="0">
                            <label class="caisse-tva-option-label">
                                <input type="checkbox" name="inclure_tva" value="1"
                                    class="caisse-tva-option-check"
                                    <?php echo !empty($cart['inclure_tva']) ? 'checked' : ''; ?>
                                    onchange="this.form.submit()">
                                <span><strong>Inclure la TVA</strong> (<?php echo htmlspecialchars((string) CAISSE_TVA_TAUX_POURCENT); ?> %) : la TVA s’ajoute au net catalogue — le total à payer augmente. <em>Sans cocher</em>, le montant affiché est le <strong>total TTC</strong> ; le ticket détaille HT + TVA.</span>
                            </label>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($cart['lines']) && $total_ttc > 0): ?>
                    <form method="post" action="post.php" class="caisse-generer-ticket-form">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                        <input type="hidden" name="caisse_action" value="generer_ticket">
                        <button type="submit" class="btn-primary caisse-btn-generer-ticket"
                            <?php echo !$tables_ok ? 'disabled' : ''; ?>>
                            <i class="fas fa-ticket-alt"></i> Générer le ticket
                        </button>
                        <p class="caisse-generer-hint">Le ticket s’affiche en plein écran ; le panier est vidé pour une
                            nouvelle vente.</p>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                </section>

                <!-- ——— Zone C : résumé + paiement ——— -->
                <?php if (!$masquer_zone_paiement_commercial): ?>
                <aside class="caisse-zone caisse-zone--c" aria-label="Résumé et paiement">
                    <span class="caisse-zone-label"><i class="fas fa-receipt"></i> C · Résumé &amp; paiement</span>

                    <div class="caisse-recap">
                        <div class="caisse-recap-row"><span>Total
                                HT</span><strong><?php echo number_format($total_ht, 0, ',', ' '); ?>
                                <small>FCFA</small></strong></div>
                        <div class="caisse-recap-row"><span>TVA (<?php echo htmlspecialchars((string) $taux_tva); ?>
                                %)</span><strong><?php echo number_format($montant_tva, 0, ',', ' '); ?>
                                <small>FCFA</small></strong></div>
                        <div class="caisse-recap-row caisse-recap-row--main"><span><?php echo $taux_tva > 0 ? 'Total TTC (à payer)' : 'Total à payer'; ?></span><strong><?php echo number_format($total_ttc, 0, ',', ' '); ?>
                                <small>FCFA</small></strong></div>
                    </div>
                    <?php if ($taux_tva > 0): ?>
                    <?php if ($afficher_option_tva_caisse): ?>
                    <p class="caisse-recap-note">Sans cocher « Inclure la TVA », le montant affiché est le <strong>total TTC</strong> (décomposition HT + TVA sur le ticket). Coché : le panier reste en <strong>net HT</strong> et la TVA s’ajoute.</p>
                    <?php else: ?>
                    <p class="caisse-recap-note">Le total à payer est TTC ; le détail HT + TVA figure sur le ticket.</p>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($cart['lines']) && $total_ttc > 0): ?>
                    <div class="caisse-monnaie-box">
                        <p class="caisse-monnaie-title">Espèces — aperçu monnaie</p>
                        <form method="post" action="post.php" class="caisse-preview-form">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="caisse_action" value="preview_monnaie">
                            <label class="visually-hidden" for="montant_recu_preview">Montant reçu</label>
                            <div class="caisse-monnaie-row">
                                <input type="text" name="montant_recu" id="montant_recu_preview" inputmode="decimal"
                                    placeholder="Montant reçu"
                                    value="<?php echo $preview_recu !== null ? htmlspecialchars((string) $preview_recu) : ''; ?>"
                                    class="caisse-input-pay">
                                <button type="submit" class="btn-secondary btn-sm">Calculer</button>
                            </div>
                        </form>
                        <?php if ($preview_recu !== null): ?>
                        <?php if ($monnaie_preview !== null): ?>
                        <p class="caisse-monnaie-ok"><i class="fas fa-coins"></i> Monnaie à rendre :
                            <strong><?php echo number_format($monnaie_preview, 0, ',', ' '); ?> FCFA</strong></p>
                        <?php elseif ($manque_preview !== null): ?>
                        <p class="caisse-monnaie-err"><i class="fas fa-exclamation-circle"></i> Montant insuffisant
                            (manque <?php echo number_format($manque_preview, 0, ',', ' '); ?> FCFA).</p>
                        <?php endif; ?>
                        <?php else: ?>
                        <p class="caisse-monnaie-note">Saisissez un montant et cliquez « Calculer » (sans enregistrer la
                            vente).</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="post.php" class="caisse-pay-form">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                        <input type="hidden" name="caisse_action" value="encaisser">

                        <label for="mode_paiement">Mode de paiement</label>
                        <select name="mode_paiement" id="mode_paiement" class="caisse-select-pay" required>
                            <option value="especes">Espèces</option>
                            <option value="orange_money">Orange Money</option>
                            <option value="wave">Wave</option>
                            <option value="carte">Carte bancaire</option>
                            <option value="cheque">Chèque</option>
                            <option value="mixte">Paiement mixte</option>
                            <option value="autre">Autre</option>
                        </select>

                        <div id="caisseVendeurBlocEspeces">
                        <label for="montant_recu_final" id="caisse_vendeur_label_montant_recu">Montant reçu</label>
                        <input type="text" name="montant_recu" id="montant_recu_final" inputmode="decimal"
                            placeholder="Ex. 20000" class="caisse-input-pay" autocomplete="off">
                        </div>

                        <div id="caisseVendeurBlocMixte" class="caisse-encaisse-bloc-mixte is-hidden">
                        <p class="caisse-pay-split-title">Répartition mixte (chaque partie versée)</p>
                        <label for="montant_especes">Part espèces</label>
                        <input type="text" name="montant_especes" id="montant_especes" inputmode="decimal"
                            placeholder="0" class="caisse-input-pay">
                        <label for="montant_carte">Part carte bancaire</label>
                        <input type="text" name="montant_carte" id="montant_carte" inputmode="decimal" placeholder="0"
                            class="caisse-input-pay">
                        <label for="montant_orange_money">Part Orange Money</label>
                        <input type="text" name="montant_orange_money" id="montant_orange_money" inputmode="decimal"
                            placeholder="0" class="caisse-input-pay">
                        <label for="montant_wave">Part Wave</label>
                        <input type="text" name="montant_wave" id="montant_wave" inputmode="decimal" placeholder="0"
                            class="caisse-input-pay">
                        </div>

                        <label for="notes_vente">Note (optionnel)</label>
                        <textarea name="notes_vente" id="notes_vente" rows="2" class="caisse-textarea-pay"
                            placeholder="Référence…"></textarea>

                        <button type="submit" class="caisse-btn-valider"
                            <?php echo (!$tables_ok || empty($cart['lines']) || $total_ttc <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check"></i> Valider la vente
                        </button>
                    </form>
                </aside>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script type="application/json" id="caisse-catalog-json"><?php echo $caisse_catalog_json_script; ?></script>

    <script>
    (function() {
        var btn = document.getElementById('btnPrintTicket');
        if (btn) btn.addEventListener('click', function() {
            window.print();
        });
    })();
    (function() {
        var elJson = document.getElementById('caisse-catalog-json');
        var box = document.getElementById('caisse-live-results');
        var inputQ = document.getElementById('caisse_q_live');
        var selCat = document.getElementById('caisse_cat_live');
        if (!elJson || !box || !inputQ || !selCat) {
            return;
        }
        var catalog = [];
        try {
            catalog = JSON.parse(elJson.textContent || '[]');
        } catch (e) {
            catalog = [];
        }
        var csrf = box.getAttribute('data-csrf') || '';
        var hasIdent = box.getAttribute('data-has-ident') === '1';
        var maxLive = 25;
        var fmtFcfa = function(n) {
            var x = Math.round(Number(n));
            return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f');
        };
        var debounceTimer;
        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
        function matchProduct(p, qRaw, catVal) {
            if (catVal) {
                if (String(p.cat_id) !== String(catVal)) {
                    return false;
                }
            }
            var q = (qRaw || '').trim();
            if (!q) {
                return false;
            }
            var d = q.toLowerCase();
            var nom = (p.nom || '').toLowerCase();
            if (nom.indexOf(d) !== -1) {
                return true;
            }
            var ref = (p.ref || '').toUpperCase();
            var qu = q.toUpperCase();
            if (ref && ref.indexOf(qu) !== -1) {
                return true;
            }
            var qDigits = q.replace(/\D/g, '');
            if (qDigits && ref) {
                var rDigits = ref.replace(/\D/g, '');
                if (rDigits.indexOf(qDigits) !== -1) {
                    return true;
                }
            }
            return false;
        }
        function renderLive() {
            var q = inputQ.value;
            var catVal = selCat.value;
            var hits = [];
            var i;
            var needFilter = (q.trim() !== '') || (catVal !== '');
            if (!needFilter) {
                box.innerHTML = '';
                box.hidden = true;
                box.classList.remove('is-empty');
                return;
            }
            if (q.trim() === '' && catVal !== '') {
                for (i = 0; i < catalog.length; i++) {
                    if (String(catalog[i].cat_id) === String(catVal)) {
                        hits.push(catalog[i]);
                    }
                    if (hits.length >= maxLive) {
                        break;
                    }
                }
            } else {
                for (i = 0; i < catalog.length; i++) {
                    if (matchProduct(catalog[i], q, catVal)) {
                        hits.push(catalog[i]);
                    }
                    if (hits.length >= maxLive) {
                        break;
                    }
                }
            }
            if (hits.length === 0) {
                box.innerHTML = '<p class="caisse-live-empty">Aucun produit en stock ne correspond.</p>';
                box.hidden = false;
                box.classList.add('is-empty');
                return;
            }
            var html = '<ul class="caisse-live-list">';
            for (i = 0; i < hits.length; i++) {
                var p = hits[i];
                var refCell = hasIdent
                    ? '<span class="caisse-live-ref"><code>' + esc(p.ref || '—') + '</code></span>'
                    : '';
                html += '<li class="caisse-live-item" role="option">' +
                    '<div class="caisse-live-item-main">' +
                    '<span class="caisse-live-nom">' + esc(p.nom) + '</span>' + refCell +
                    '<span class="caisse-live-meta">' + fmtFcfa(p.prix) + ' FCFA HT · stock ' + esc(String(p.stock)) + '</span>' +
                    '</div>' +
                    '<form method="post" action="post.php" class="caisse-live-add">' +
                    '<input type="hidden" name="csrf_token" value="' + esc(csrf) + '">' +
                    '<input type="hidden" name="caisse_action" value="add_product">' +
                    '<input type="hidden" name="produit_id" value="' + esc(String(p.id)) + '">' +
                    '<input type="hidden" name="quantite" value="1">' +
                    '<button type="submit" class="btn-add-line"><i class="fas fa-plus"></i> Ajouter</button>' +
                    '</form></li>';
            }
            if (catalog.length >= 2500 && hits.length >= maxLive) {
                html += '</ul><p class="caisse-live-cap-hint">Affichage limité à ' + maxLive + ' résultats — affinez la recherche ou utilisez « Liste complète ».</p>';
            } else {
                html += '</ul>';
            }
            box.innerHTML = html;
            box.hidden = false;
            box.classList.remove('is-empty');
        }
        function schedule() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(renderLive, 180);
        }
        inputQ.addEventListener('input', schedule);
        inputQ.addEventListener('focus', function() {
            schedule();
        });
        selCat.addEventListener('change', renderLive);
        document.addEventListener('click', function(ev) {
            if (!box.hidden && !box.contains(ev.target) && ev.target !== inputQ && !selCat.contains(ev.target)) {
                var insideFields = ev.target.closest && ev.target.closest('.caisse-search-fields');
                if (!insideFields && inputQ.value.trim() === '') {
                    box.innerHTML = '';
                    box.hidden = true;
                    box.classList.remove('is-empty');
                }
            }
        });
    })();
    (function() {
        var selPay = document.getElementById('mode_paiement');
        var bEsp = document.getElementById('caisseVendeurBlocEspeces');
        var bMix = document.getElementById('caisseVendeurBlocMixte');
        var labRecuV = document.getElementById('caisse_vendeur_label_montant_recu');
        if (!selPay || !bEsp || !bMix) return;
        var labParMode = {
            especes: 'Montant reçu (espèces)',
            orange_money: 'Montant reçu (Orange Money)',
            wave: 'Montant reçu (Wave)',
            carte: 'Montant débité / reçu (carte bancaire)',
            cheque: 'Montant du chèque',
            autre: 'Montant reçu'
        };
        function syncPay() {
            var m = selPay.value;
            var inpRecu = document.getElementById('montant_recu_final');
            if (m === 'mixte') {
                bMix.classList.remove('is-hidden');
                if (inpRecu) inpRecu.value = '';
            } else {
                bMix.classList.add('is-hidden');
            }
            bEsp.style.display = (m === 'mixte') ? 'none' : '';
            if (labRecuV) {
                labRecuV.textContent = labParMode[m] || 'Montant reçu';
            }
        }
        selPay.addEventListener('change', syncPay);
        syncPay();
    })();
    </script>
</body>

</html>