<?php
/**
 * Réajustement des lignes et de l'en-tête d'un BL — même ergonomie que le formulaire de création (index modal BL)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_bl_retours_b2b() && !admin_can_comptabilite()) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';

$fiscal_tva_pourcent_devis_bl = fiscal_taux_tva_pourcent();

$bl_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bl_id <= 0 || !bl_tables_available()) {
    header('Location: index.php?tab=bl');
    exit;
}

$bl = get_bl_by_id($bl_id);
if (!$bl) {
    header('Location: index.php?tab=bl');
    exit;
}

if (bl_est_statut_verrouille($bl['statut'] ?? '')) {
    $_SESSION['success_message'] = 'Ce bon est validé pour la comptabilité : le réajustement des lignes n’est plus disponible.';
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

$lignes = get_lignes_bl($bl_id);
$bl_erreur = $_SESSION['bl_erreur'] ?? null;
if (isset($_SESSION['bl_erreur'])) {
    unset($_SESSION['bl_erreur']);
}

$st = $bl['statut'] ?? 'brouillon';
$lib_statut = bl_libelle_statut($st);
$client_b2b_id = (int) ($bl['client_b2b_id'] ?? 0);
$total_ht = (float) ($bl['total_ht'] ?? 0);
$bl_tva_checked = !empty($bl['tva_incluse']);

$lignes_count_init = count($lignes);

/** @param mixed $v */
function bl_modifier_esc_attr($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier BL <?php echo bl_modifier_esc_attr($bl['numero_bl'] ?? ''); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header bl-page-header">
        <div class="bl-page-header__lead">
            <h1><i class="fas fa-edit" aria-hidden="true"></i> Modifier <?php echo bl_modifier_esc_attr($bl['numero_bl'] ?? ''); ?></h1>
            <p class="bl-page-header__sub">
                <?php echo bl_modifier_esc_attr($bl['raison_sociale'] ?? ''); ?>
                · <?php echo bl_modifier_esc_attr($lib_statut); ?>
            </p>
        </div>
        <div class="header-actions bl-page-header__actions bl-page-header__actions--stack">
            <a href="bl_voir.php?id=<?php echo (int) $bl_id; ?>" class="btn-secondary"><i class="fas fa-eye" aria-hidden="true"></i> Aperçu</a>
            <?php if ($client_b2b_id > 0): ?>
                <a href="bl_par_client.php?id=<?php echo $client_b2b_id; ?>" class="btn-secondary"><i class="fas fa-building" aria-hidden="true"></i> BL du client</a>
            <?php endif; ?>
            <a href="index.php?tab=bl" class="btn-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Contacts BL</a>
        </div>
    </div>

    <?php if ($bl_erreur): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($bl_erreur); ?></span>
        </div>
    <?php endif; ?>

    <section class="content-section bl-detail-page bl-modifier-form-page">
        <div class="bl-voir-hero bl-modifier-hero">
            <div class="bl-voir-hero__main">
                <span class="bl-voir-hero__label">Total HT enregistré (avant enregistrement ici)</span>
                <p class="bl-voir-hero__total"><?php echo number_format($total_ht, 0, ',', ' '); ?> <span class="bl-voir-hero__currency">FCFA</span></p>
            </div>
            <div class="bl-voir-hero__side">
                <span class="bl-voir-hero__label">Statut</span>
                <span class="commande-statut statut-<?php echo htmlspecialchars($st); ?> bl-voir-hero__stat"><?php echo htmlspecialchars($lib_statut); ?></span>
            </div>
        </div>

        <form method="post" action="bl_maj.php" id="form-bl-edit" class="bl-modifier-form-shell">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
            <input type="hidden" name="bl_id" value="<?php echo (int) $bl_id; ?>">

            <div class="form-commande-manuelle-grid">
                <div class="form-commande-manuelle-col form-col-articles">
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <i class="fas fa-search"></i>
                            <h3>Rechercher un produit</h3>
                        </div>
                        <div class="form-group search-group">
                            <div class="search-input-wrapper">
                                <input type="text" id="search-produit-bl-edit" placeholder="Nom, réf. produit (FPL…), réf. fournisseur…" autocomplete="off" aria-label="Rechercher un produit">
                                <i class="fas fa-search search-icon"></i>
                                <span class="search-loading" id="search-loading-bl-edit" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span>
                            </div>
                            <div id="search-produit-results-bl-edit" class="search-produit-results" role="listbox" aria-hidden="true"></div>
                        </div>
                        <p class="form-hint"><i class="fas fa-info-circle"></i> Recherche par <strong>nom</strong>, <strong>réf. produit</strong> (FPL, 5 chiffres) ou <strong>réf. fournisseur</strong>. Laissez vide pour tous les articles.</p>
                    </div>

                    <div class="form-section-card">
                        <div class="form-section-header">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>Produits du BL</h3>
                            <span class="lignes-count" id="lignes-count-bl-edit"><?php echo (int) $lignes_count_init; ?> article(s)</span>
                        </div>
                        <div id="lignes-commande-bl-edit" class="lignes-commande lignes-commande-modal-wrap">
                            <div class="ligne-commande-head ligne-commande-head-bl" id="lignes-head-bl-edit" <?php echo $lignes_count_init > 0 ? '' : 'hidden'; ?>>
                                <span class="lch-head-cell">Produit</span>
                                <span class="lch-head-cell">Quantité</span>
                                <span class="lch-head-cell">prix FCFA</span>
                                <span class="lch-head-cell">promo FCFA</span>
                                <span class="lch-head-cell lch-head-actions" aria-hidden="true"></span>
                            </div>
                            <div class="lignes-empty" id="lignes-empty-bl-edit" <?php echo $lignes_count_init > 0 ? 'style="display:none"' : ''; ?>>
                                <i class="fas fa-inbox"></i>
                                <p>Aucun produit. Utilisez la recherche ci-dessus ou ajoutez des lignes.</p>
                            </div>
                            <?php
                            $idx = 0;
                            foreach ($lignes as $row):
                                $pid = !empty($row['produit_id']) ? (int) $row['produit_id'] : 0;
                                $nom = (string) ($row['designation'] ?? '');
                                $qte = $row['quantite'] ?? '';
                                $pu = (float) ($row['prix_unitaire_ht'] ?? 0);
                                $maxStock = 99999;
                                ?>
                            <div class="ligne-commande-item ligne-commande-item-bl" data-produit-id="<?php echo $pid > 0 ? $pid : ''; ?>">
                                <div class="ligne-bl-cell">
                                    <input type="hidden" name="lignes[<?php echo $idx; ?>][produit_id]" value="<?php echo $pid > 0 ? $pid : ''; ?>">
                                    <span class="ligne-bl-label">Désignation</span>
                                    <input type="text" name="lignes[<?php echo $idx; ?>][nom_produit]" value="<?php echo bl_modifier_esc_attr($nom); ?>" placeholder="Nom du produit" class="ligne-nom-input" aria-label="Désignation du produit">
                                </div>
                                <div class="ligne-bl-cell">
                                    <span class="ligne-bl-label">Quantité</span>
                                    <input type="number" name="lignes[<?php echo $idx; ?>][quantite]" value="<?php echo bl_modifier_esc_attr($qte); ?>" min="0.001" step="0.001" max="<?php echo (int) $maxStock; ?>" class="ligne-qte" aria-label="Quantité">
                                </div>
                                <div class="ligne-bl-cell ligne-bl-cell-prix">
                                    <span class="ligne-bl-label">Prix unitaire</span>
                                    <div class="ligne-bl-prix-row">
                                        <input type="number" name="lignes[<?php echo $idx; ?>][prix_unitaire]" value="<?php echo bl_modifier_esc_attr($pu); ?>" min="0" step="0.01" class="ligne-prix" aria-label="Prix unitaire en FCFA">
                                        <span class="ligne-unit-fcfa">FCFA</span>
                                    </div>
                                </div>
                                <div class="ligne-bl-cell ligne-bl-cell-prix">
                                    <span class="ligne-bl-label">Prix promo</span>
                                    <div class="ligne-bl-prix-row">
                                        <input type="number" name="lignes[<?php echo $idx; ?>][prix_promotion]" value="" min="0" step="0.01" placeholder="Optionnel" class="ligne-prix-promo" aria-label="Prix promotionnel en FCFA">
                                        <span class="ligne-unit-fcfa">FCFA</span>
                                    </div>
                                </div>
                                <button type="button" class="ligne-remove" aria-label="Retirer la ligne"><i class="fas fa-trash"></i></button>
                            </div>
                                <?php
                                $idx++;
                            endforeach;
                            ?>
                        </div>
                        <div class="modal-tva-option" role="group" aria-labelledby="modal-tva-bl-edit-title">
                            <input type="hidden" name="inclure_tva" value="0">
                            <label class="modal-tva-option__label" for="inclure_tva_bl_edit">
                                <span class="modal-tva-option__inner">
                                    <span class="modal-tva-option__glow" aria-hidden="true"></span>
                                    <span class="modal-tva-option__leading">
                                        <span class="modal-tva-option__icon" aria-hidden="true"><i class="fas fa-percent"></i></span>
                                        <span class="modal-tva-option__title" id="modal-tva-bl-edit-title">Inclure la TVA</span>
                                    </span>
                                    <span class="modal-tva-option__toggle">
                                        <input type="checkbox" name="inclure_tva" value="1" id="inclure_tva_bl_edit" class="modal-tva-option__checkbox" <?php echo $bl_tva_checked ? 'checked' : ''; ?>>
                                        <span class="modal-tva-option__track" aria-hidden="true"></span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-commande-manuelle-col form-col-client">
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <i class="fas fa-user"></i>
                            <h3>Informations client</h3>
                        </div>
                        <div class="bl-modifier-client-readonly">
                            <div class="bl-modifier-client-field">
                                <span class="bl-modifier-client-label">Raison sociale / contact</span>
                                <p class="bl-modifier-client-val"><?php echo bl_modifier_esc_attr($bl['raison_sociale'] ?? '—'); ?></p>
                            </div>
                            <div class="form-row-2 bl-modifier-client-row2">
                                <div class="bl-modifier-client-field">
                                    <span class="bl-modifier-client-label">Nom</span>
                                    <p class="bl-modifier-client-val"><?php echo bl_modifier_esc_attr($bl['nom_contact'] ?? '—'); ?></p>
                                </div>
                                <div class="bl-modifier-client-field">
                                    <span class="bl-modifier-client-label">Prénom</span>
                                    <p class="bl-modifier-client-val"><?php echo bl_modifier_esc_attr($bl['prenom_contact'] ?? '—'); ?></p>
                                </div>
                            </div>
                            <div class="bl-modifier-client-field">
                                <span class="bl-modifier-client-label">Téléphone</span>
                                <p class="bl-modifier-client-val"><?php echo bl_modifier_esc_attr($bl['client_telephone'] ?? '—'); ?></p>
                            </div>
                            <div class="bl-modifier-client-field">
                                <span class="bl-modifier-client-label">Email</span>
                                <p class="bl-modifier-client-val"><?php echo bl_modifier_esc_attr($bl['client_email'] ?? '—'); ?></p>
                            </div>
                            <div class="form-group">
                                <label for="adresse_client">Adresse du client (facturation) <span class="optional">(optionnel)</span></label>
                                <textarea id="adresse_client" name="adresse_client" rows="2" placeholder="Siège social, rue, complément d’adresse affiché sur la facture…"><?php echo bl_modifier_esc_attr($bl['adresse_client'] ?? ''); ?></textarea>
                            </div>
                            <?php if (!empty($bl['client_adresse'])): ?>
                            <div class="bl-modifier-client-field">
                                <span class="bl-modifier-client-label">Adresse</span>
                                <p class="bl-modifier-client-val"><?php echo nl2br(htmlspecialchars((string) ($bl['client_adresse'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2" placeholder="Instructions supplémentaires..."><?php echo bl_modifier_esc_attr($bl['notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="date_bl">Date du BL</label>
                                <input type="date" name="date_bl" id="date_bl" value="<?php echo bl_modifier_esc_attr($bl['date_bl'] ?? date('Y-m-d')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Statut du BL</label>
                                <p class="bl-modifier-statut-pill"><span class="commande-statut statut-<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($lib_statut); ?></span></p>
                            </div>
                        </div>
                        <div class="commande-manuelle-recap">
                            <div class="recap-line">
                                <span>Sous-total produits (HT)</span>
                                <span id="recap-sous-total-bl-edit">0 FCFA</span>
                            </div>
                            <div class="recap-line bl-modifier-recap-frais">
                                <span>Frais de livraison (HT)</span>
                                <span id="recap-frais-bl-edit">0 FCFA</span>
                            </div>
                            <div class="recap-line recap-tva-line-bl-edit" id="recap-tva-line-bl-edit" style="<?php echo $bl_tva_checked ? '' : 'display:none;'; ?>">
                                <span>TVA (<span id="recap-tva-pct-bl-edit"><?php echo bl_modifier_esc_attr($fiscal_tva_pourcent_devis_bl); ?></span> %)</span>
                                <span id="recap-tva-montant-bl-edit">0 FCFA</span>
                            </div>
                            <div class="recap-line recap-total">
                                <span id="recap-total-label-bl-edit"><?php echo $bl_tva_checked ? 'Total TTC' : 'Total'; ?></span>
                                <span id="recap-total-bl-edit">0 FCFA</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-commande-manuelle-actions bl-modifier-actions-bar">
                <a href="bl_voir.php?id=<?php echo (int) $bl_id; ?>" class="btn-secondary">Annuler</a>
                <button type="submit" class="btn-primary btn-submit-commande">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
            </div>
        </form>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script src="/js/admin-produit-search-ui.js<?php echo asset_version_query(); ?>"></script>
    <script>
    (function() {
        var FISCAL_TVA_PCT = <?php echo json_encode((float) $fiscal_tva_pourcent_devis_bl); ?>;
        var searchInput = document.getElementById('search-produit-bl-edit');
        var searchResults = document.getElementById('search-produit-results-bl-edit');
        var searchLoading = document.getElementById('search-loading-bl-edit');
        var lignesContainer = document.getElementById('lignes-commande-bl-edit');
        var lignesEmpty = document.getElementById('lignes-empty-bl-edit');
        var lignesCount = document.getElementById('lignes-count-bl-edit');
        var ligneIndex = <?php echo (int) $lignes_count_init; ?>;
        var ajaxUrl = 'ajax_search_produits.php';
        var inclureTva = document.getElementById('inclure_tva_bl_edit');
        var recapSousTotal = document.getElementById('recap-sous-total-bl-edit');
        var recapFrais = document.getElementById('recap-frais-bl-edit');
        var recapTotal = document.getElementById('recap-total-bl-edit');
        var recapTotalLabel = document.getElementById('recap-total-label-bl-edit');
        var recapTvaLine = document.getElementById('recap-tva-line-bl-edit');
        var recapTvaMontant = document.getElementById('recap-tva-montant-bl-edit');

        function formatNumber(n) {
            return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function updateLignesUI() {
            var items = lignesContainer ? lignesContainer.querySelectorAll('.ligne-commande-item') : [];
            var n = items.length;
            if (lignesEmpty) lignesEmpty.style.display = n === 0 ? 'flex' : 'none';
            if (lignesCount) lignesCount.textContent = n + ' article(s)';
            var head = document.getElementById('lignes-head-bl-edit');
            if (head) {
                if (n > 0) head.removeAttribute('hidden');
                else head.setAttribute('hidden', 'hidden');
            }
        }

        function getSousTotal() {
            var total = 0;
            var rows = lignesContainer ? lignesContainer.querySelectorAll('.ligne-commande-item') : [];
            rows.forEach(function(row) {
                var qel = row.querySelector('.ligne-qte');
                var pel = row.querySelector('.ligne-prix');
                var promo = row.querySelector('.ligne-prix-promo');
                var qte = qel ? (parseFloat(qel.value) || 0) : 0;
                var prix = pel ? (parseFloat(pel.value) || 0) : 0;
                var p = promo && promo.value && parseFloat(promo.value) > 0 ? parseFloat(promo.value) : prix;
                total += p * qte;
            });
            return total;
        }

        function updateRecap() {
            var sousTotal = getSousTotal();
            var frais = 0;
            var netHt = sousTotal + frais;
            var tvaOn = inclureTva && inclureTva.checked;
            var tvaMontant = 0;
            var totalAff = netHt;
            if (tvaOn) {
                tvaMontant = Math.round(netHt * (FISCAL_TVA_PCT / 100));
                totalAff = Math.round(netHt + tvaMontant);
                if (recapTvaLine) recapTvaLine.style.display = '';
                if (recapTvaMontant) recapTvaMontant.textContent = formatNumber(tvaMontant) + ' FCFA';
                if (recapTotalLabel) recapTotalLabel.textContent = 'Total TTC';
            } else {
                if (recapTvaLine) recapTvaLine.style.display = 'none';
                if (recapTotalLabel) recapTotalLabel.textContent = 'Total';
            }
            if (recapSousTotal) recapSousTotal.textContent = formatNumber(sousTotal) + ' FCFA';
            if (recapFrais) recapFrais.textContent = formatNumber(frais) + ' FCFA';
            if (recapTotal) recapTotal.textContent = formatNumber(totalAff) + ' FCFA';
        }

        function addLigne(produit) {
            var prix = parseFloat(produit.prix) || 0;
            var prixPromo = produit.prix_promotion && parseFloat(produit.prix_promotion) > 0 ? parseFloat(produit.prix_promotion) : '';
            var idx = ligneIndex++;
            var div = document.createElement('div');
            div.className = 'ligne-commande-item ligne-commande-item-bl';
            div.dataset.produitId = produit.id;
            var maxQ = produit.stock_dispo || produit.stock || 99999;
            var U = window.FoutaAdminProduitSearchUi;
            var cellDes = U && U.buildLigneBlDesignationCellHtml
                ? U.buildLigneBlDesignationCellHtml(produit, idx, 'lignes')
                : ('<div class="ligne-bl-cell">' +
                    '<input type="hidden" name="lignes[' + idx + '][produit_id]" value="' + produit.id + '">' +
                    '<span class="ligne-bl-label">Désignation</span>' +
                    '<input type="text" name="lignes[' + idx + '][nom_produit]" value="' + (produit.nom || '').replace(/"/g, '&quot;').replace(/</g, '') + '" placeholder="Nom du produit" class="ligne-nom-input" aria-label="Désignation du produit">' +
                '</div>');
            div.innerHTML = cellDes +
                '<div class="ligne-bl-cell">' +
                    '<span class="ligne-bl-label">Quantité</span>' +
                    '<input type="number" name="lignes[' + idx + '][quantite]" value="1" min="0.001" step="0.001" max="' + maxQ + '" class="ligne-qte" aria-label="Quantité">' +
                '</div>' +
                '<div class="ligne-bl-cell ligne-bl-cell-prix">' +
                    '<span class="ligne-bl-label">Prix unitaire</span>' +
                    '<div class="ligne-bl-prix-row">' +
                        '<input type="number" name="lignes[' + idx + '][prix_unitaire]" value="' + (prixPromo || prix) + '" min="0" step="0.01" class="ligne-prix" aria-label="Prix unitaire en FCFA">' +
                        '<span class="ligne-unit-fcfa">FCFA</span>' +
                    '</div>' +
                '</div>' +
                '<div class="ligne-bl-cell ligne-bl-cell-prix">' +
                    '<span class="ligne-bl-label">Prix promo</span>' +
                    '<div class="ligne-bl-prix-row">' +
                        '<input type="number" name="lignes[' + idx + '][prix_promotion]" value="' + (prixPromo || '') + '" min="0" step="0.01" placeholder="Optionnel" class="ligne-prix-promo" aria-label="Prix promotionnel en FCFA">' +
                        '<span class="ligne-unit-fcfa">FCFA</span>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="ligne-remove" aria-label="Retirer la ligne"><i class="fas fa-trash"></i></button>';
            if (lignesEmpty) lignesEmpty.style.display = 'none';
            div.querySelector('.ligne-remove').addEventListener('click', function() {
                div.remove();
                updateLignesUI();
                updateRecap();
            });
            lignesContainer.appendChild(div);
            updateLignesUI();
            updateRecap();
        }

        function doSearch(q) {
            if (searchLoading) searchLoading.style.visibility = 'visible';
            fetch(ajaxUrl + '?q=' + encodeURIComponent(q) + '&limit=25')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var items = data.items || [];
                    searchResults.innerHTML = '';
                    if (items.length === 0) {
                        searchResults.innerHTML = '<div class="search-no-results"><i class="fas fa-box-open"></i> Aucun produit trouvé.</div>';
                    } else {
                        items.forEach(function(p) {
                            var el = document.createElement('div');
                            el.className = 'search-result-item';
                            el.setAttribute('role', 'option');
                            el.setAttribute('tabindex', '0');
                            var U = window.FoutaAdminProduitSearchUi;
                            el.innerHTML = U && U.buildSearchResultHtml ? U.buildSearchResultHtml(p) : (
                                '<span class="sr-nom">' + (p.nom || '') + '</span>' +
                                '<span class="sr-meta">' + (p.categorie_nom || '') + '</span>'
                            );
                            el.addEventListener('mousedown', function(ev) {
                                ev.preventDefault();
                                addLigne(p);
                                searchInput.value = '';
                                searchResults.innerHTML = '';
                                searchResults.setAttribute('aria-hidden', 'true');
                            });
                            el.addEventListener('keydown', function(ev) {
                                if (ev.key === 'Enter' || ev.key === ' ') {
                                    ev.preventDefault();
                                    addLigne(p);
                                    searchInput.value = '';
                                    searchResults.innerHTML = '';
                                    searchResults.setAttribute('aria-hidden', 'true');
                                }
                            });
                            searchResults.appendChild(el);
                        });
                    }
                    searchResults.setAttribute('aria-hidden', 'false');
                })
                .catch(function() {
                    searchResults.innerHTML = '<div class="search-no-results"><i class="fas fa-exclamation-triangle"></i> Erreur de recherche.</div>';
                })
                .finally(function() {
                    if (searchLoading) searchLoading.style.visibility = 'hidden';
                });
        }

        if (lignesContainer) {
            lignesContainer.querySelectorAll('.ligne-commande-item .ligne-remove').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var row = btn.closest('.ligne-commande-item');
                    if (row) { row.remove(); updateLignesUI(); updateRecap(); }
                });
            });
            lignesContainer.addEventListener('input', function(ev) {
                if (ev.target.classList.contains('ligne-qte') || ev.target.classList.contains('ligne-prix') || ev.target.classList.contains('ligne-prix-promo')) {
                    updateRecap();
                }
            });
        }

        if (inclureTva) inclureTva.addEventListener('change', updateRecap);

        var searchTimeout;
        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                var q = searchInput.value.trim();
                searchTimeout = setTimeout(function() { doSearch(q); }, 250);
            });
            searchInput.addEventListener('focus', function() {
                var q = searchInput.value.trim();
                if (searchResults.getAttribute('aria-hidden') === 'true' || searchResults.innerHTML === '') {
                    doSearch(q);
                }
            });
            searchInput.addEventListener('blur', function() {
                setTimeout(function() {
                    if (!searchResults.contains(document.activeElement)) {
                        searchResults.innerHTML = '';
                        searchResults.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchResults.addEventListener('mousedown', function(ev) { ev.preventDefault(); });
        }

        updateLignesUI();
        updateRecap();
    })();
    </script>
</body>
</html>
