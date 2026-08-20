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
    admin_redirect_role_home();
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_marques.php';
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';

// Panier géré côté client (AJAX) — suppression de l’ancien panier session
caisse_cart_clear();
$taux_tva = (float) CAISSE_TVA_TAUX_POURCENT;
$total_ttc = 0.0;
$total_ht = 0.0;
$montant_tva = 0.0;

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
$marque_sel = isset($_GET['marque']) ? (int) $_GET['marque'] : 0;
$marque_sel = $marque_sel > 0 ? $marque_sel : null;
$marque_has_col = function_exists('produits_has_column') && produits_has_column('marque_id') && pf_champ_visible('marque_id');
$marque_arg = ($marque_has_col && $marque_sel !== null) ? $marque_sel : null;
$fournisseur_sel = isset($_GET['fournisseur']) ? (int) $_GET['fournisseur'] : 0;
$fournisseur_sel = $fournisseur_sel > 0 ? $fournisseur_sel : null;
$fournisseur_has_col = function_exists('produits_has_column') && produits_has_column('fournisseur_id') && pf_champ_visible('fournisseur_id');

$marques_liste = [];
if (function_exists('marques_table_ok') && marques_table_ok()) {
    $marques_liste = get_all_marques_ordered_by_nom();
}

$fournisseurs_liste = [];
if ($fournisseur_has_col) {
    require_once __DIR__ . '/../../models/model_fournisseurs.php';
    $fournisseurs_liste = get_all_fournisseurs_ordered_by_nom();
}

$show_catalogue = ($q !== '' || $cat !== null || $marque_arg !== null || $fournisseur_sel !== null);
$produits_liste = $show_catalogue ? search_produits_with_filters($q, null, null, $cat, 'nom', 0, 60, $marque_arg) : [];
if ($fournisseur_sel !== null && $fournisseur_has_col && !empty($produits_liste)) {
    $produits_liste = array_values(array_filter($produits_liste, function ($pr) use ($fournisseur_sel) {
        return (int) ($pr['fournisseur_id'] ?? 0) === $fournisseur_sel;
    }));
}
$categories = get_all_categories();

$has_ident = function_exists('produits_has_column') && produits_has_column('identifiant_interne');

$ticket_id = isset($_GET['ticket']) ? (int) $_GET['ticket'] : 0;
if ($ticket_id > 0) {
    require_once __DIR__ . '/../../models/model_caisse_compta.php';
    require_once __DIR__ . '/../../includes/barcode_caisse_ticket.php';
}
$ticket_data = ($ticket_id > 0) ? caisse_get_vente_by_id($ticket_id) : null;
$ticket_introuvable = ($ticket_id > 0 && !$ticket_data);
$ticket_recap = $ticket_data ? caisse_vente_recap_fiscal_affichage($ticket_data) : null;
$ticket_inclure_tva = $ticket_data && !empty($ticket_data['tva_incluse']);
$ticket_afficher_detail_tva = $ticket_inclure_tva && $ticket_recap && abs((float) ($ticket_recap['tva'] ?? 0)) >= 0.005;
$ticket_statut = $ticket_data ? caisse_vente_statut($ticket_data) : null;
$masquer_zone_paiement_commercial = in_array(admin_current_role(), ['commercial', 'commercial_general'], true);
/** Option « Inclure la TVA » : masquée pour le rôle commercial seul ; visible pour commercial_general et les autres */
$afficher_option_tva_caisse = admin_current_role() !== 'commercial';
$ticket_barcode_src = $ticket_data ? caisse_ticket_get_barcode_web_path($ticket_data) : '';
$ticket_barcode_payload = $ticket_data ? caisse_ticket_valeur_code_barres($ticket_data) : '';

$tables_ok = caisse_tables_exist();
$page_title = 'Caisse';

$preview_recu = null;
$monnaie_preview = null;
$manque_preview = null;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-dashboard-caisse-pages.css'); ?>
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
                <?php if ($ticket_afficher_detail_tva): ?>
                <div class="caisse-ticket-row"><span>Total
                        HT</span><strong><?php echo number_format($ticket_recap['ht'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="caisse-ticket-row"><span>TVA
                        (<?php echo htmlspecialchars((string) CAISSE_TVA_TAUX_POURCENT); ?>
                        %)</span><strong><?php echo number_format($ticket_recap['tva'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <?php endif; ?>
                <div class="caisse-ticket-row caisse-ticket-row--total"><span><?php echo $ticket_afficher_detail_tva ? 'Total TTC' : 'Total à payer'; ?></span><strong><?php echo number_format($ticket_recap['ttc'], 0, ',', ' '); ?> FCFA</strong>
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

            <!-- ——— Zone A : recherche unifiée (catalogue + codes) ——— -->
            <section class="caisse-zone caisse-zone--a" aria-label="Recherche catalogue">
                <div class="caisse-zone-a-grid">
                    <div class="caisse-search-card caisse-search-card--primary">
                        <h2 class="caisse-catalog-title"><i class="fas fa-search" aria-hidden="true"></i> Recherche &amp; ajout catalogue</h2>
                        <form method="get" action="index.php" class="caisse-search-form" id="caisse-search-form-get">
                            <div class="caisse-search-fields">
                                <div class="caisse-search-field caisse-search-field--q">
                                    <label for="caisse_q_live" class="caisse-search-label">Recherche</label>
                                    <input type="text" name="q" id="caisse_q_live" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                                        placeholder="Nom, description… — filtre en direct"
                                        class="caisse-search-input caisse-search-input--live"
                                        aria-label="Recherche produit" autocomplete="off" inputmode="search"
                                        data-live-search-input autofocus>
                                </div>
                                <select name="cat" id="caisse_cat_live" class="caisse-search-select" aria-label="Catégorie">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo (int) $c['id']; ?>"
                                        <?php echo ($cat !== null && (int) $c['id'] === $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($marques_liste) && $marque_has_col): ?>
                                <select name="marque" id="caisse_marque_live" class="caisse-search-select" aria-label="Marque">
                                    <option value="">Toutes les marques</option>
                                    <?php foreach ($marques_liste as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"
                                        <?php echo ($marque_sel !== null && (int) $m['id'] === (int) $marque_sel) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                                <?php if (!empty($fournisseurs_liste) && $fournisseur_has_col): ?>
                                <select name="fournisseur" id="caisse_fournisseur_live" class="caisse-search-select" aria-label="Fournisseur">
                                    <option value="">Tous les fournisseurs</option>
                                    <?php foreach ($fournisseurs_liste as $f): ?>
                                    <option value="<?php echo (int) $f['id']; ?>"
                                        <?php echo ($fournisseur_sel !== null && (int) $f['id'] === (int) $fournisseur_sel) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($f['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                                <button type="submit" class="btn-primary caisse-search-btn">Rechercher (liste complète)</button>
                            </div>
                        </form>
                        <div class="caisse-live-results-wrap">
                            <span id="caisse-live-results-label" class="visually-hidden">Suggestions produits</span>
                            <div id="caisse-live-results" class="caisse-live-results" role="listbox" aria-labelledby="caisse-live-results-label"
                                data-csrf="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-has-ident="<?php echo $has_ident ? '1' : '0'; ?>"
                                data-marque-filter="<?php echo ($marque_has_col && !empty($marques_liste)) ? '1' : '0'; ?>"
                                data-fournisseur-filter="<?php echo ($fournisseur_has_col && !empty($fournisseurs_liste)) ? '1' : '0'; ?>"
                                data-catalog-url="api.php?action=catalog"
                                hidden></div>
                        </div>
                        <form id="caisse-add-scan-fallback" method="post" action="post.php" class="visually-hidden" tabindex="-1" aria-hidden="true">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="caisse_action" value="add_scan">
                            <input type="hidden" name="quantite" value="1">
                            <input type="hidden" name="code" id="caisse_add_scan_code" value="">
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
                                <td><?php echo $pu > 0 ? number_format($pu, 0, ',', ' ') : '—'; ?></td>
                                <td><?php echo $stk; ?></td>
                                <td class="caisse-results-act">
                                    <button type="button" class="btn-add-line caisse-inline-add-btn" data-produit-id="<?php echo (int) $pr['id']; ?>">
                                        <i class="fas fa-plus"></i> Ajouter</button>
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

                <!-- ——— Zone B : panier (AJAX, sans session) ——— -->
                <section class="caisse-zone caisse-zone--b" aria-label="Panier">
                    <div id="caisse-flash-live" class="caisse-flash-live" hidden role="alert"></div>
                    <div id="caisse-panier-mount"></div>
                </section>

                <!-- ——— Zone C : résumé + paiement ——— -->
                <?php if (!$masquer_zone_paiement_commercial): ?>
                <aside class="caisse-zone caisse-zone--c" aria-label="Résumé et paiement">
                    <span class="caisse-zone-label"><i class="fas fa-receipt"></i> C · Résumé &amp; paiement</span>

                    <div class="caisse-recap" id="caisse-recap-live">
                        <div class="caisse-recap-row"><span>Total
                                HT</span><strong id="caisse-recap-ht"><?php echo number_format($total_ht, 0, ',', ' '); ?>
                                <small>FCFA</small></strong></div>
                        <div class="caisse-recap-row"><span>TVA (<?php echo htmlspecialchars((string) $taux_tva); ?>
                                %)</span><strong id="caisse-recap-tva"><?php echo number_format($montant_tva, 0, ',', ' '); ?>
                                <small>FCFA</small></strong></div>
                        <div class="caisse-recap-row caisse-recap-row--main"><span><?php echo $taux_tva > 0 ? 'Total TTC (à payer)' : 'Total à payer'; ?></span><strong id="caisse-recap-ttc"><?php echo number_format($total_ttc, 0, ',', ' '); ?>
                                <small>FCFA</small></strong></div>
                    </div>
                    <?php if ($taux_tva > 0 && !$afficher_option_tva_caisse): ?>
                    <p class="caisse-recap-note">Le total à payer est TTC ; le détail HT + TVA figure sur le ticket.</p>
                    <?php endif; ?>

                    <div class="caisse-monnaie-box" id="caisse-monnaie-box" hidden>
                        <p class="caisse-monnaie-title">Espèces — aperçu monnaie</p>
                        <div class="caisse-monnaie-row">
                            <input type="text" id="montant_recu_preview" inputmode="decimal"
                                placeholder="Montant reçu" class="caisse-input-pay">
                            <button type="button" class="btn-secondary btn-sm" id="caisse-btn-preview-monnaie">Calculer</button>
                        </div>
                        <p class="caisse-monnaie-note" id="caisse-monnaie-result"></p>
                    </div>

                    <form class="caisse-pay-form" action="#" method="post">
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

                        <button type="submit" class="caisse-btn-valider" disabled>
                            <i class="fas fa-check"></i> Valider la vente
                        </button>
                    </form>
                </aside>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="caisse-gallery-modal" class="caisse-gallery-modal" hidden role="dialog" aria-modal="true"
        aria-labelledby="caisseGalleryTitle">
        <button type="button" class="caisse-gallery-modal__backdrop" data-caisse-gallery-close aria-label="Fermer la galerie"></button>
        <div class="caisse-gallery-modal__panel" tabindex="-1">
            <h2 id="caisseGalleryTitle" class="visually-hidden">Photos du produit</h2>
            <button type="button" class="caisse-gallery-modal__close" data-caisse-gallery-close aria-label="Fermer"><i class="fas fa-times" aria-hidden="true"></i></button>
            <div class="caisse-gallery-modal__img-wrap">
                <img src="" alt="" class="caisse-gallery-modal__img" id="caisseGalleryImg" width="800" height="800">
            </div>
            <div class="caisse-gallery-modal__toolbar">
                <button type="button" class="caisse-gallery-modal__nav-btn" id="caisseGalleryPrev" aria-label="Image précédente"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                <span class="caisse-gallery-modal__counter" id="caisseGalleryCounter" aria-live="polite"></span>
                <button type="button" class="caisse-gallery-modal__nav-btn" id="caisseGalleryNext" aria-label="Image suivante"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>

    <script type="application/json" id="caisse-catalog-json">[]</script>
    <script src="/js/admin-caisse-live-search.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/admin-caisse-panier.js<?php echo asset_version_query(); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.CaissePanier) {
            CaissePanier.init({
                api_url: 'api.php',
                csrf: <?php echo json_encode((string) $_SESSION['admin_csrf'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                tva_taux: <?php echo json_encode($taux_tva); ?>,
                afficher_tva: <?php echo $afficher_option_tva_caisse ? 'true' : 'false'; ?>,
                tables_ok: <?php echo $tables_ok ? 'true' : 'false'; ?>
            });
        }
        document.querySelectorAll('.caisse-inline-add-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pid = parseInt(btn.getAttribute('data-produit-id'), 10);
                if (!pid || !window.CaissePanier) return;
                if (typeof CaissePanier.addById === 'function') {
                    CaissePanier.addById(pid, 1);
                    return;
                }
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action: 'get_product',
                        csrf_token: <?php echo json_encode((string) $_SESSION['admin_csrf']); ?>,
                        produit_id: pid
                    })
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (res.ok && res.produit) CaissePanier.addProduct(res.produit, 1);
                });
            });
        });
        var btnMon = document.getElementById('caisse-btn-preview-monnaie');
        if (btnMon) {
            btnMon.addEventListener('click', function () {
                var recu = parseFloat(String(document.getElementById('montant_recu_preview').value || '').replace(',', '.')) || 0;
                var ttcEl = document.getElementById('caisse-recap-ttc');
                var ttc = ttcEl ? parseFloat(ttcEl.textContent.replace(/\s/g, '').replace(',', '.')) || 0 : 0;
                var out = document.getElementById('caisse-monnaie-result');
                if (!out) return;
                if (recu <= 0) { out.textContent = 'Saisissez un montant.'; return; }
                if (recu + 0.001 >= ttc) {
                    out.innerHTML = '<span class="caisse-monnaie-ok"><i class="fas fa-coins"></i> Monnaie : <strong>' +
                        Math.round(recu - ttc).toLocaleString('fr-FR') + ' FCFA</strong></span>';
                } else {
                    out.innerHTML = '<span class="caisse-monnaie-err">Manque ' +
                        Math.round(ttc - recu).toLocaleString('fr-FR') + ' FCFA</span>';
                }
            });
        }
    });
    </script>

    <script>
    (function() {
        var btn = document.getElementById('btnPrintTicket');
        if (btn) btn.addEventListener('click', function() {
            window.print();
        });
    })();
    (function() {
        var modal = document.getElementById('caisse-gallery-modal');
        var imgEl = document.getElementById('caisseGalleryImg');
        var counterEl = document.getElementById('caisseGalleryCounter');
        var btnPrev = document.getElementById('caisseGalleryPrev');
        var btnNext = document.getElementById('caisseGalleryNext');
        if (!modal || !imgEl || !counterEl || !btnPrev || !btnNext) {
            return;
        }
        var urls = [];
        var idx = 0;
        var placeholderImg = '/image/produit1.jpg';
        function showSlide() {
            if (!urls.length) {
                return;
            }
            var u = urls[idx] || placeholderImg;
            imgEl.src = u;
            imgEl.alt = 'Photo ' + (idx + 1) + ' sur ' + urls.length;
            counterEl.textContent = (idx + 1) + ' / ' + urls.length;
            btnPrev.disabled = urls.length < 2;
            btnNext.disabled = urls.length < 2;
        }
        function openGallery(list) {
            urls = list && list.length ? list.slice() : [placeholderImg];
            idx = 0;
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            showSlide();
            setTimeout(function() {
                btnNext.focus();
            }, 50);
        }
        function closeGallery() {
            modal.hidden = true;
            document.body.style.overflow = '';
            imgEl.removeAttribute('src');
        }
        function step(d) {
            if (urls.length < 2) {
                return;
            }
            idx = (idx + d + urls.length) % urls.length;
            showSlide();
        }
        document.addEventListener('click', function(ev) {
            var btn = ev.target.closest && ev.target.closest('[data-caisse-gallery]');
            if (!btn) {
                return;
            }
            var raw = btn.getAttribute('data-caisse-gallery');
            var list = [];
            try {
                list = JSON.parse(raw || '[]');
            } catch (e) {
                list = [];
            }
            if (!Array.isArray(list) || !list.length) {
                list = [placeholderImg];
            }
            ev.preventDefault();
            ev.stopPropagation();
            openGallery(list);
        });
        modal.addEventListener('click', function(ev) {
            if (ev.target.closest('[data-caisse-gallery-close]')) {
                closeGallery();
            }
        });
        btnPrev.addEventListener('click', function() {
            step(-1);
        });
        btnNext.addEventListener('click', function() {
            step(1);
        });
        document.addEventListener('keydown', function(ev) {
            if (modal.hidden) {
                return;
            }
            if (ev.key === 'Escape') {
                closeGallery();
            } else if (ev.key === 'ArrowLeft') {
                step(-1);
            } else if (ev.key === 'ArrowRight') {
                step(1);
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