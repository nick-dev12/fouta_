<?php
/**
 * Page de liste des devis (Admin)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis()) {
    admin_redirect_role_home();
}

require_once __DIR__ . '/../../models/model_factures_devis.php';
require_once __DIR__ . '/../../models/model_devis.php';
require_once __DIR__ . '/../../models/model_zones_livraison.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';
require_once __DIR__ . '/../../includes/devis_prix_champs_ui.php';
require_once __DIR__ . '/../../models/model_produit_formulaire_champs.php';
$fiscal_tva_pourcent_devis_bl = fiscal_taux_tva_pourcent();
list($nb_prix_cols_devis_arr,) = devis_prix_colonnes_etat_defaut();
$nb_prix_cols_devis = count($nb_prix_cols_devis_arr);

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$devis_clients_groupes = get_devis_agreges_par_client_non_payes();
$devis_nb_contacts = count($devis_clients_groupes);
$zones_livraison = get_all_zones_livraison('actif');

$error_devis = $_SESSION['error_devis'] ?? null;
if (isset($_SESSION['error_devis'])) {
    unset($_SESSION['error_devis']);
}

$show_modal_devis = isset($_GET['modal']) && $_GET['modal'] === 'devis';

$devis_erreur = $_SESSION['devis_erreur'] ?? null;
$devis_post = $_SESSION['devis_post'] ?? null;
if (isset($_SESSION['devis_erreur'])) {
    unset($_SESSION['devis_erreur']);
}
if (isset($_SESSION['devis_post'])) {
    unset($_SESSION['devis_post']);
}

$devis_page_has_alert = isset($_SESSION['success_message']) || !empty($error_devis);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-devis-compta-pages.css'); ?>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-devis-admin">
    <div class="content-header dashboard-hero page-devis-hero">
        <div class="dashboard-hero-text">
            <p class="dashboard-eyebrow">Espace commercial</p>
            <h1><i class="fas fa-file-invoice" aria-hidden="true"></i> Devis</h1>
            <p class="dashboard-subtitle">Créez et suivez vos devis jusqu’à la facturation.</p>
        </div>
    </div>

    <?php if ($devis_page_has_alert): ?>
    <div class="page-devis-alerts" role="region" aria-label="Messages">
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success page-devis-message">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_devis): ?>
        <div class="message error page-devis-message">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($error_devis); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($devis_page_has_alert): ?>
    </div>
    <?php endif; ?>

    <section class="content-section page-devis-section" aria-label="Devis">
        <div class="tab-panel-devis-bl is-active" role="region">
        <?php if (!admin_is_restricted_admin_account()): ?>
        <div class="admin-devis-bl-panel-actions">
            <button type="button" class="btn-primary" id="btn-nouveau-devis" aria-label="Créer un devis">
                <i class="fas fa-plus-circle"></i> Nouveau devis
            </button>
        </div>
        <?php endif; ?>
        <?php if (empty($devis_clients_groupes)): ?>
            <div class="empty-state page-devis-empty">
                <div class="page-devis-empty__ic" aria-hidden="true"><i class="fas fa-file-invoice"></i></div>
                <h3>Aucun devis à suivre</h3>
                <p>Tous les devis ont une facture marquée payée, ou aucun devis n’a encore été créé. Utilisez « Nouveau devis » pour en ajouter.</p>
            </div>
        <?php else: ?>
            <div class="bl-tab-surface">
                <header class="bl-contacts-hero">
                    <div class="bl-contacts-hero__icon-wrap" aria-hidden="true">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="bl-contacts-hero__copy">
                        <h2 class="bl-contacts-hero__title">Clients &amp; devis</h2>
                    </div>
                    <div class="bl-contacts-hero__stat" title="Nombre de contacts">
                        <span class="bl-contacts-hero__stat-num"><?php echo (int) $devis_nb_contacts; ?></span>
                        <span class="bl-contacts-hero__stat-label">contact<?php echo $devis_nb_contacts > 1 ? 's' : ''; ?></span>
                    </div>
                </header>

                <div class="bl-contacts-grid" role="list">
                <?php foreach ($devis_clients_groupes as $cl): ?>
                    <?php
                    $cid_display = $cl['cle'];
                    $nb_d = (int) ($cl['nb'] ?? 0);
                    $label = trim($cl['label'] ?? '');
                    if ($label === '') {
                        $label = 'Client';
                    }
                    $initials = '?';
                    $words = preg_split('/\s+/u', $label, -1, PREG_SPLIT_NO_EMPTY);
                    if (count($words) >= 2) {
                        $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1), 'UTF-8');
                    } elseif (count($words) === 1) {
                        $initials = mb_strtoupper(mb_substr($words[0], 0, min(2, mb_strlen($words[0], 'UTF-8')), 'UTF-8'), 'UTF-8');
                    }
                    $last_d = !empty($cl['derniere']) ? date('d/m/Y · H:i', strtotime($cl['derniere'])) : '—';
                    ?>
                    <article class="bl-contact-card" role="listitem">
                        <div class="bl-contact-card__inner">
                            <div class="bl-contact-card__head">
                                <div class="bl-contact-card__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                                <div class="bl-contact-card__head-text">
                                    <h3 class="bl-contact-card__company"><?php echo htmlspecialchars($label); ?></h3>
                                </div>
                                <span class="bl-contact-card__pill">
                                    <i class="fas fa-file-invoice" aria-hidden="true"></i>
                                    <?php echo $nb_d; ?> devis
                                </span>
                            </div>

                            <ul class="bl-contact-card__meta">
                                <li class="bl-contact-card__meta-row">
                                    <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-phone"></i></span>
                                    <span class="bl-contact-card__meta-val"><?php echo htmlspecialchars($cl['telephone'] ?? '—'); ?></span>
                                </li>
                                <li class="bl-contact-card__meta-row">
                                    <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                                    <span class="bl-contact-card__meta-val"><?php echo !empty($cl['email']) ? htmlspecialchars($cl['email']) : '—'; ?></span>
                                </li>
                            </ul>

                            <div class="bl-contact-card__foot">
                                <div class="bl-contact-card__last">
                                    <span class="bl-contact-card__last-label">Dernier devis</span>
                                    <span class="bl-contact-card__last-date"><?php echo htmlspecialchars($last_d); ?></span>
                                </div>
                                <a href="devis_par_client.php?k=<?php echo rawurlencode($cid_display); ?>" class="bl-contact-card__cta">
                                    <span>Voir les devis</span>
                                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </section>
    </div>

    <!-- Modal nouveau devis -->
    <div id="modal-devis" class="modal-commande-manuelle <?php echo $show_modal_devis ? 'modal-open' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="modal-devis-title">
        <div class="modal-commande-manuelle-backdrop"></div>
        <div class="modal-commande-manuelle-content">
            <div class="modal-commande-manuelle-header">
                <h2 id="modal-devis-title"><i class="fas fa-file-invoice"></i> Nouveau devis</h2>
                <button type="button" class="modal-commande-manuelle-close" id="modal-devis-close" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-commande-manuelle-body">
                <?php if ($devis_erreur): ?>
                    <div class="message error modal-commande-erreur">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($devis_erreur); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="create.php" id="form-devis">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="user_id" id="user_id" value="<?php echo htmlspecialchars($devis_post['user_id'] ?? ''); ?>">
                    <div class="form-commande-manuelle-grid">
                        <div class="form-commande-manuelle-col form-col-articles">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-search"></i>
                                    <h3>Rechercher un produit</h3>
                                </div>
                                <div class="form-group search-group">
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-produit" placeholder="Nom, description… — filtre en direct" autocomplete="off" inputmode="search" data-live-search-input>
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-loading" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <div id="search-produit-results" class="search-produit-results" role="listbox" aria-hidden="true"></div>
                                </div>
                            </div>

                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h3>Produits du devis</h3>
                                    <span class="lignes-count" id="lignes-count">0 article(s)</span>
                                </div>
                                <?php
                                $colonnes_devis_post = is_array($devis_post) && !empty($devis_post['prix_colonnes']) && is_array($devis_post['prix_colonnes']) ? $devis_post['prix_colonnes'] : null;
                                $calc_devis_post = is_array($devis_post) ? (string) ($devis_post['champ_prix_calcul'] ?? '') : '';
                                devis_prix_colonnes_panel_render('devis_prix_panel_devis', $colonnes_devis_post, $calc_devis_post);
                                ?>
                                <div id="lignes-commande" class="lignes-commande lignes-commande-modal-wrap" style="--devis-prix-cols: <?php echo max(1, $nb_prix_cols_devis); ?>;">
                                    <div class="ligne-commande-head ligne-commande-head-bl" id="lignes-head-devis" hidden></div>
                                    <div class="lignes-empty" id="lignes-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun produit ajouté. Utilisez la recherche ci-dessus.</p>
                                    </div>
                                </div>
                                <div class="modal-tva-option" role="group" aria-labelledby="modal-tva-devis-title">
                                    <input type="hidden" name="inclure_tva" value="0">
                                    <label class="modal-tva-option__label" for="inclure_tva_devis">
                                        <span class="modal-tva-option__inner">
                                            <span class="modal-tva-option__glow" aria-hidden="true"></span>
                                            <span class="modal-tva-option__leading">
                                                <span class="modal-tva-option__icon" aria-hidden="true"><i class="fas fa-percent"></i></span>
                                                <span class="modal-tva-option__title" id="modal-tva-devis-title">Inclure la TVA</span>
                                            </span>
                                            <span class="modal-tva-option__toggle">
                                                <input type="checkbox" name="inclure_tva" value="1" id="inclure_tva_devis" class="modal-tva-option__checkbox"
                                                    <?php echo (is_array($devis_post) && !empty($devis_post['inclure_tva'])) ? 'checked' : ''; ?>>
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
                                <div class="form-group search-group" style="position:relative;">
                                    <label for="search-client">Rechercher un client</label>
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-client" placeholder="Nom, téléphone ou email..." autocomplete="off">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-client-loading" style="visibility:hidden;"><i class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <div id="search-client-results" class="search-produit-results" role="listbox" aria-hidden="true" style="position:absolute; left:0; right:0; top:100%; z-index:100;"></div>
                                    <p class="form-hint"><i class="fas fa-info-circle"></i> Recherchez un client ou saisissez manuellement ci-dessous.</p>
                                </div>
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label for="client_nom">Nom <span class="required">*</span></label>
                                        <input type="text" id="client_nom" name="client_nom" required
                                            value="<?php echo htmlspecialchars($devis_post['client_nom'] ?? ''); ?>">
                                    </div>
                                <div class="form-group">
                                    <label for="client_prenom">Prénom <span class="optional">(optionnel)</span></label>
                                    <input type="text" id="client_prenom" name="client_prenom"
                                        value="<?php echo htmlspecialchars($devis_post['client_prenom'] ?? ''); ?>">
                                </div>
                                </div>
                                <div class="form-group">
                                    <label for="client_telephone">Téléphone <span class="required">*</span></label>
                                    <input type="tel" id="client_telephone" name="client_telephone" required
                                        placeholder="Ex: 07 12 34 56 78"
                                        value="<?php echo htmlspecialchars($devis_post['client_telephone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="client_email">Email <span class="optional">(optionnel)</span></label>
                                    <input type="email" id="client_email" name="client_email"
                                        value="<?php echo htmlspecialchars($devis_post['client_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="adresse_client">Adresse du client <span class="optional">(optionnel)</span></label>
                                    <textarea id="adresse_client" name="adresse_client" rows="2" placeholder="Siège social, quartier, complément…"><?php echo htmlspecialchars($devis_post['adresse_client'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="zone_livraison_id"><i class="fas fa-map-marker-alt"></i> Adresse de livraison <span class="optional">(optionnel)</span></label>
                                    <select id="zone_livraison_id" name="zone_livraison_id">
                                        <option value="">— Sélectionnez une adresse —</option>
                                        <?php foreach ($zones_livraison as $z): ?>
                                        <option value="<?php echo (int) $z['id']; ?>"
                                            data-adresse="<?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>"
                                            data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                            <?php echo (isset($devis_post['zone_livraison_id']) && (int)$devis_post['zone_livraison_id'] === (int)$z['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                            (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="custom" <?php echo (isset($devis_post['zone_livraison_id']) && $devis_post['zone_livraison_id'] === 'custom') ? 'selected' : ''; ?>>— Adresse personnalisée —</option>
                                    </select>
                                    <div id="adresse-custom-wrap" class="adresse-custom-wrap" style="display:none; margin-top:10px;">
                                        <textarea id="adresse_livraison_ta" rows="3" placeholder="Saisissez l'adresse complète"><?php echo htmlspecialchars($devis_post['adresse_livraison'] ?? ''); ?></textarea>
                                    </div>
                                    <div id="adresse-zone-display" class="adresse-zone-display" style="display:none; margin-top:8px; padding:10px; background:#f5f5f4; border-radius:8px;"></div>
                                    <input type="hidden" name="adresse_livraison" id="adresse_livraison" value="">
                                    <input type="hidden" name="frais_livraison" id="frais_livraison" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <textarea id="notes" name="notes" rows="2" placeholder="Instructions supplémentaires..."><?php echo htmlspecialchars($devis_post['notes'] ?? ''); ?></textarea>
                                </div>
                                <div class="commande-manuelle-recap">
                                    <div class="recap-line">
                                        <span>Sous-total produits (HT)</span>
                                        <span id="recap-sous-total">0 FCFA</span>
                                    </div>
                                    <div class="recap-line">
                                        <span>Frais de livraison (HT)</span>
                                        <span id="recap-frais">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-tva-line-devis" id="recap-tva-line-devis" style="display:none;">
                                        <span>TVA (<span id="recap-tva-pct-devis"><?php echo htmlspecialchars((string) $fiscal_tva_pourcent_devis_bl); ?></span> %)</span>
                                        <span id="recap-tva-montant-devis">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-total">
                                        <span id="recap-total-label-devis">Total</span>
                                        <span id="recap-total">0 FCFA</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-commande-manuelle-actions">
                        <button type="button" class="btn-secondary" id="modal-devis-cancel">Annuler</button>
                        <button type="submit" class="btn-primary btn-submit-commande" name="submit_devis">
                            <i class="fas fa-check"></i> Créer le devis
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <?php include __DIR__ . '/../../includes/admin_stock_alerte_popup.php'; ?>

    <?php include '../includes/footer.php'; ?>

    <script src="/js/admin-produit-search-ui.js<?php echo asset_version_query(); ?>"></script>
    <script>
    (function() {
        var FISCAL_TVA_PCT = <?php echo json_encode((float) $fiscal_tva_pourcent_devis_bl); ?>;
        var modal = document.getElementById('modal-devis');
        var btnOpen = document.getElementById('btn-nouveau-devis');
        var btnClose = document.getElementById('modal-devis-close');
        var btnCancel = document.getElementById('modal-devis-cancel');
        var backdrop = modal ? modal.querySelector('.modal-commande-manuelle-backdrop') : null;
        var searchInput = document.getElementById('search-produit');
        var searchResults = document.getElementById('search-produit-results');
        var searchLoading = document.getElementById('search-loading');
        var lignesContainer = document.getElementById('lignes-commande');
        var lignesEmpty = document.getElementById('lignes-empty');
        var lignesCount = document.getElementById('lignes-count');
        var ligneIndex = 0;
        var ajaxUrl = 'ajax_search_produits.php';
        var champPrixPanelDevis = document.getElementById('devis_prix_panel_devis');
        var headDevis = document.getElementById('lignes-head-devis');
        var UInit = window.FoutaAdminProduitSearchUi;
        if (UInit && UInit.buildLignesHeadHtml && headDevis) {
            UInit.buildLignesHeadHtml(headDevis, lignesContainer, champPrixPanelDevis);
        }

        function openModal() {
            if (modal) modal.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            if (modal) modal.classList.remove('modal-open');
            document.body.style.overflow = '';
        }

        if (btnOpen) btnOpen.addEventListener('click', openModal);
        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnCancel) btnCancel.addEventListener('click', closeModal);
        if (backdrop) backdrop.addEventListener('click', closeModal);

        if (modal && modal.classList.contains('modal-open')) document.body.style.overflow = 'hidden';

        function updateLignesUI() {
            var items = lignesContainer ? lignesContainer.querySelectorAll('.ligne-commande-item') : [];
            var n = items.length;
            if (lignesEmpty) lignesEmpty.style.display = n === 0 ? 'flex' : 'none';
            if (lignesCount) lignesCount.textContent = n + ' article(s)';
            var headDevis = document.getElementById('lignes-head-devis');
            if (headDevis) {
                if (n > 0) headDevis.removeAttribute('hidden');
                else headDevis.setAttribute('hidden', 'hidden');
            }
        }

        function addLigne(produit) {
            var U = window.FoutaAdminProduitSearchUi;
            var calc = U && U.getChampCalculSlug ? U.getChampCalculSlug(champPrixPanelDevis) : 'prix';
            var idx = ligneIndex++;
            var div = document.createElement('div');
            div.className = 'ligne-commande-item ligne-commande-item-bl';
            div.dataset.produitId = produit.id;
            div.innerHTML = U && U.buildLigneCommandeItemHtml
                ? U.buildLigneCommandeItemHtml(produit, idx, 'lignes', calc, champPrixPanelDevis)
                : '';
            if (lignesEmpty) lignesEmpty.style.display = 'none';
            div.querySelector('.ligne-remove').addEventListener('click', function() {
                div.remove();
                updateLignesUI();
                updateRecap();
            });
            lignesContainer.appendChild(div);
            if (U && U.updateLigneRowTotal) U.updateLigneRowTotal(div, calc);
            updateLignesUI();
            updateRecap();
        }

        function doSearch(q) {
            if (productSearch) {
                productSearch.search(q);
            }
        }

        var productSearch = null;
        var searchUi = window.FoutaAdminProduitSearchUi;
        if (searchInput && searchResults && searchUi && searchUi.createAjaxSearchController) {
            productSearch = searchUi.createAjaxSearchController({
                ajaxUrl: ajaxUrl,
                resultsEl: searchResults,
                loadingEl: searchLoading,
                onSelect: function (p) {
                    addLigne(p);
                    searchInput.value = '';
                    searchResults.innerHTML = '';
                    searchResults.setAttribute('aria-hidden', 'true');
                }
            });
        } else if (searchInput && searchResults) {
            function doSearchLegacy(q) {
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
                                searchResults.appendChild(el);
                            });
                        }
                        searchResults.setAttribute('aria-hidden', 'false');
                    })
                    .finally(function() {
                        if (searchLoading) searchLoading.style.visibility = 'hidden';
                    });
            }
            doSearch = doSearchLegacy;
        }

        var zoneSelect = document.getElementById('zone_livraison_id');
        var adresseCustomWrap = document.getElementById('adresse-custom-wrap');
        var adresseZoneDisplay = document.getElementById('adresse-zone-display');
        var adresseLivraison = document.getElementById('adresse_livraison');
        var adresseTa = document.getElementById('adresse_livraison_ta');
        var fraisInput = document.getElementById('frais_livraison');
        var recapSousTotal = document.getElementById('recap-sous-total');
        var recapFrais = document.getElementById('recap-frais');
        var recapTotal = document.getElementById('recap-total');
        var recapTotalLabelDevis = document.getElementById('recap-total-label-devis');
        var recapTvaLineDevis = document.getElementById('recap-tva-line-devis');
        var recapTvaMontantDevis = document.getElementById('recap-tva-montant-devis');
        var inclureTvaDevis = document.getElementById('inclure_tva_devis');
        var formDevis = document.getElementById('form-devis');

        function formatNumber(n) {
            return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function getSousTotal() {
            var U = window.FoutaAdminProduitSearchUi;
            var calc = U && U.getChampCalculSlug ? U.getChampCalculSlug(champPrixPanelDevis) : 'prix';
            if (U && U.getLignesSousTotal) {
                return U.getLignesSousTotal(lignesContainer, calc);
            }
            return 0;
        }

        function getFraisLivraison() {
            if (!zoneSelect || zoneSelect.value === '' || zoneSelect.value === 'custom') return 0;
            var opt = zoneSelect.options[zoneSelect.selectedIndex];
            return opt && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
        }

        function updateRecap() {
            var sousTotal = getSousTotal();
            var frais = getFraisLivraison();
            var netHt = sousTotal + frais;
            var tvaOn = inclureTvaDevis && inclureTvaDevis.checked;
            var tvaMontant = 0;
            var totalAff = netHt;
            if (tvaOn) {
                tvaMontant = Math.round(netHt * (FISCAL_TVA_PCT / 100));
                totalAff = Math.round(netHt + tvaMontant);
                if (recapTvaLineDevis) recapTvaLineDevis.style.display = '';
                if (recapTvaMontantDevis) recapTvaMontantDevis.textContent = formatNumber(tvaMontant) + ' FCFA';
                if (recapTotalLabelDevis) recapTotalLabelDevis.textContent = 'Total TTC';
            } else {
                if (recapTvaLineDevis) recapTvaLineDevis.style.display = 'none';
                if (recapTotalLabelDevis) recapTotalLabelDevis.textContent = 'Total';
            }
            if (recapSousTotal) recapSousTotal.textContent = formatNumber(sousTotal) + ' FCFA';
            if (recapFrais) recapFrais.textContent = formatNumber(frais) + ' FCFA';
            if (recapTotal) recapTotal.textContent = formatNumber(totalAff) + ' FCFA';
            if (fraisInput) fraisInput.value = frais;
        }

        function onZoneChange() {
            var val = zoneSelect ? zoneSelect.value : '';
            if (val === 'custom') {
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'block';
                if (adresseZoneDisplay) adresseZoneDisplay.style.display = 'none';
                if (adresseLivraison) adresseLivraison.value = '';
            } else if (val !== '') {
                var opt = zoneSelect.options[zoneSelect.selectedIndex];
                var adr = opt && opt.dataset.adresse ? opt.dataset.adresse : '';
                if (adresseLivraison) adresseLivraison.value = adr;
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'none';
                if (adresseZoneDisplay) {
                    adresseZoneDisplay.textContent = adr;
                    adresseZoneDisplay.style.display = 'block';
                }
            } else {
                if (adresseCustomWrap) adresseCustomWrap.style.display = 'none';
                if (adresseZoneDisplay) adresseZoneDisplay.style.display = 'none';
                if (adresseLivraison) adresseLivraison.value = '';
            }
            updateRecap();
        }

        if (zoneSelect) zoneSelect.addEventListener('change', onZoneChange);

        if (inclureTvaDevis) inclureTvaDevis.addEventListener('change', updateRecap);

        var U = window.FoutaAdminProduitSearchUi;
        if (U && U.initChampPrixColonnesPanel) {
            U.initChampPrixColonnesPanel(champPrixPanelDevis, lignesContainer, headDevis, updateRecap);
        }
        if (U && U.bindLignesLiveRecap) {
            U.bindLignesLiveRecap(lignesContainer, updateRecap, champPrixPanelDevis);
        }

        if (formDevis) {
            formDevis.addEventListener('submit', function(ev) {
                var zv = zoneSelect ? zoneSelect.value : '';
                if (zv === 'custom' && adresseTa && adresseLivraison) {
                    adresseLivraison.value = adresseTa.value.trim();
                } else if (zv && zv !== 'custom' && zoneSelect && adresseLivraison) {
                    var optD = zoneSelect.options[zoneSelect.selectedIndex];
                    adresseLivraison.value = optD && optD.dataset.adresse ? optD.dataset.adresse : '';
                }
            });
        }

        var searchTimeout;
        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                var q = searchInput.value.trim();
                searchTimeout = setTimeout(function() { doSearch(q); }, 120);
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

        var searchClientInput = document.getElementById('search-client');
        var searchClientResults = document.getElementById('search-client-results');
        var searchClientLoading = document.getElementById('search-client-loading');
        var clientNomInput = document.getElementById('client_nom');
        var clientPrenomInput = document.getElementById('client_prenom');
        var clientTelInput = document.getElementById('client_telephone');
        var clientEmailInput = document.getElementById('client_email');
        var userIdInput = document.getElementById('user_id');
        var clientSearchTimeout;
        if (searchClientInput && searchClientResults && clientNomInput && clientPrenomInput && clientTelInput) {
            function doClientSearch(q) {
                if (q.length < 1) {
                    searchClientResults.innerHTML = '';
                    searchClientResults.setAttribute('aria-hidden', 'true');
                    return;
                }
                if (searchClientLoading) searchClientLoading.style.visibility = 'visible';
                fetch('ajax_search_clients.php?q=' + encodeURIComponent(q) + '&limit=15')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        searchClientResults.innerHTML = '';
                        if (data.length === 0) {
                            searchClientResults.innerHTML = '<div class="search-no-results">Aucun client trouvé.</div>';
                        } else {
                            data.forEach(function(c) {
                                var el = document.createElement('div');
                                el.className = 'search-result-item';
                                el.setAttribute('role', 'option');
                                el.innerHTML = '<span class="sr-nom">' + (c.nom_complet || '') + '</span>' +
                                    '<span class="sr-meta">' + (c.telephone || '') + (c.email ? ' &bull; ' + c.email : '') + '</span>';
                                el.addEventListener('mousedown', function(ev) {
                                    ev.preventDefault();
                                    clientNomInput.value = c.nom || '';
                                    clientPrenomInput.value = c.prenom || '';
                                    clientTelInput.value = c.telephone || '';
                                    if (clientEmailInput) clientEmailInput.value = c.email || '';
                                    if (userIdInput) userIdInput.value = (c.source === 'user') ? c.id : '';
                                    searchClientInput.value = '';
                                    searchClientResults.innerHTML = '';
                                    searchClientResults.setAttribute('aria-hidden', 'true');
                                });
                                searchClientResults.appendChild(el);
                            });
                        }
                        searchClientResults.setAttribute('aria-hidden', 'false');
                    })
                    .catch(function() {
                        searchClientResults.innerHTML = '<div class="search-no-results">Erreur de recherche.</div>';
                    })
                    .finally(function() {
                        if (searchClientLoading) searchClientLoading.style.visibility = 'hidden';
                    });
            }
            searchClientInput.addEventListener('input', function() {
                clearTimeout(clientSearchTimeout);
                var q = searchClientInput.value.trim();
                clientSearchTimeout = setTimeout(function() { doClientSearch(q); }, 300);
            });
            searchClientInput.addEventListener('focus', function() {
                var q = searchClientInput.value.trim();
                if (q.length >= 1) doClientSearch(q);
            });
            searchClientInput.addEventListener('blur', function() {
                setTimeout(function() {
                    if (!searchClientResults.contains(document.activeElement)) {
                        searchClientResults.innerHTML = '';
                        searchClientResults.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            });
            searchClientResults.addEventListener('mousedown', function(ev) { ev.preventDefault(); });
        }

        updateLignesUI();
        if (modal && modal.classList.contains('modal-open') && zoneSelect && zoneSelect.value) {
            onZoneChange();
        } else {
            updateRecap();
        }
    })();
    </script>
</body>
</html>
