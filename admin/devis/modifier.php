<?php
/**
 * Modification d'un devis (brouillon uniquement)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis()) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_devis.php';
require_once __DIR__ . '/../../models/model_zones_livraison.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';
require_once __DIR__ . '/../../includes/devis_prix_champs_ui.php';
require_once __DIR__ . '/../../models/model_produit_formulaire_champs.php';
require_once __DIR__ . '/../../models/model_produits.php';
$fiscal_tva_pourcent_modifier = fiscal_taux_tva_pourcent();

$devis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($devis_id <= 0) {
    header('Location: devis.php');
    exit;
}

$devis = get_devis_by_id($devis_id);
if (!$devis || ($devis['statut'] ?? '') !== 'brouillon') {
    $_SESSION['error_devis'] = 'Ce devis ne peut pas être modifié.';
    header('Location: devis.php');
    exit;
}

$produits = get_produits_by_devis($devis_id);
$zones_livraison = get_all_zones_livraison('actif');

$devis_erreur = $_SESSION['devis_erreur'] ?? null;
$devis_post = $_SESSION['devis_post'] ?? null;
if (isset($_SESSION['devis_erreur'])) {
    unset($_SESSION['devis_erreur']);
}
if (isset($_SESSION['devis_post'])) {
    unset($_SESSION['devis_post']);
}

if ($devis_post && is_array($devis_post)) {
    $client_nom = $devis_post['client_nom'] ?? '';
    $client_prenom = $devis_post['client_prenom'] ?? '';
    $client_telephone = $devis_post['client_telephone'] ?? '';
    $client_email = $devis_post['client_email'] ?? '';
    $adresse_client = $devis_post['adresse_client'] ?? '';
    $notes = $devis_post['notes'] ?? '';
    $zone_livraison_id = $devis_post['zone_livraison_id'] ?? '';
    $user_id_val = $devis_post['user_id'] ?? '';
    $lignes_form = isset($devis_post['lignes']) && is_array($devis_post['lignes'])
        ? array_values($devis_post['lignes']) : [];
} else {
    $client_nom = $devis['client_nom'] ?? '';
    $client_prenom = $devis['client_prenom'] ?? '';
    $client_telephone = $devis['client_telephone'] ?? '';
    $client_email = $devis['client_email'] ?? '';
    $adresse_client = $devis['adresse_client'] ?? '';
    $notes = $devis['notes'] ?? '';
    $zone_livraison_id = $devis['zone_livraison_id'] ?? '';
    $user_id_val = $devis['user_id'] ?? '';
    $lignes_form = [];
    foreach ($produits as $p) {
        $lignes_form[] = [
            'produit_id' => $p['produit_id'],
            'nom_produit' => $p['produit_nom'] ?? '',
            'quantite' => $p['quantite'],
            'prix_unitaire' => $p['prix_unitaire'],
            'prix_promotion' => '',
        ];
    }
}

if ($devis_post && is_array($devis_post)) {
    $inclure_tva_checked = !empty($devis_post['inclure_tva']);
} else {
    $inclure_tva_checked = !empty($devis['tva_incluse']);
}

$nb_lignes = count($lignes_form);

$champ_prix_calcul_sel = '';
if ($devis_post && is_array($devis_post)) {
    $champ_prix_calcul_sel = (string) ($devis_post['champ_prix_calcul'] ?? '');
}
if ($champ_prix_calcul_sel === '') {
    $champ_prix_calcul_sel = (string) (produit_formulaire_devis_prix_manifest()['champ_calcul_defaut'] ?? 'prix');
}

$produits_ligne_map = [];
foreach ($lignes_form as $lf) {
    $pid_map = (int) ($lf['produit_id'] ?? 0);
    if ($pid_map > 0 && !isset($produits_ligne_map[$pid_map])) {
        $pr_map = get_produit_by_id($pid_map);
        if ($pr_map) {
            $pr_map['pf_custom'] = produit_formulaire_valeurs_custom($pid_map);
            $produits_ligne_map[$pid_map] = $pr_map;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le devis #<?php echo htmlspecialchars($devis['numero_devis']); ?> — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-edit"></i> Modifier le devis #<?php echo htmlspecialchars($devis['numero_devis']); ?></h1>
        <div class="header-actions">
            <a href="details.php?id=<?php echo (int) $devis_id; ?>" class="btn-secondary"><i class="fas fa-eye"></i> Détail</a>
            <a href="devis.php" class="btn-back"><i class="fas fa-arrow-left"></i> Liste des devis</a>
        </div>
    </div>

    <?php if ($devis_erreur): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($devis_erreur); ?></span>
        </div>
    <?php endif; ?>

    <section class="content-section">
        <form method="POST" action="update.php" id="form-devis" class="modifier-devis-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
            <input type="hidden" name="devis_id" value="<?php echo (int) $devis_id; ?>">
            <input type="hidden" name="user_id" id="user_id" value="<?php echo htmlspecialchars((string) $user_id_val); ?>">

            <div class="form-commande-manuelle-grid">
                <div class="form-commande-manuelle-col form-col-articles">
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <i class="fas fa-search"></i>
                            <h3>Rechercher un produit</h3>
                        </div>
                        <div class="form-group search-group">
                            <div class="search-input-wrapper">
                                <input type="text" id="search-produit" placeholder="Nom, réf. produit (FPL…), réf. fournisseur…" autocomplete="off">
                                <i class="fas fa-search search-icon"></i>
                                <span class="search-loading" id="search-loading" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span>
                            </div>
                            <div id="search-produit-results" class="search-produit-results" role="listbox" aria-hidden="true"></div>
                        </div>
                        <p class="form-hint"><i class="fas fa-info-circle"></i> Recherche par <strong>nom</strong>, <strong>réf. produit</strong> (FPL, 5 chiffres) ou <strong>réf. fournisseur</strong>. Laissez vide pour tous les articles en stock. Ajoutez ou retirez des lignes comme à la création.</p>
                    </div>

                    <div class="form-section-card">
                        <div class="form-section-header">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>Produits du devis</h3>
                            <span class="lignes-count" id="lignes-count"><?php echo (int) $nb_lignes; ?> article(s)</span>
                        </div>
                        <?php
                        $colonnes_mod_post = ($devis_post && !empty($devis_post['prix_colonnes']) && is_array($devis_post['prix_colonnes'])) ? $devis_post['prix_colonnes'] : null;
                        list($slugs_visibles_mod, $champ_prix_calcul_sel) = devis_prix_colonnes_etat_defaut($colonnes_mod_post, $champ_prix_calcul_sel);
                        $nb_prix_cols_modifier = count($slugs_visibles_mod);
                        devis_prix_colonnes_panel_render('devis_prix_panel_modifier', $slugs_visibles_mod, $champ_prix_calcul_sel);
                        ?>
                        <div id="lignes-commande" class="lignes-commande lignes-commande-modal-wrap" style="--devis-prix-cols: <?php echo max(1, $nb_prix_cols_modifier); ?>;">
                            <div class="ligne-commande-head ligne-commande-head-bl" id="lignes-head-devis" <?php echo $nb_lignes > 0 ? '' : 'hidden'; ?>>
                                <?php devis_prix_ligne_head_cellules_render($slugs_visibles_mod); ?>
                            </div>
                            <?php if ($nb_lignes === 0): ?>
                                <div class="lignes-empty" id="lignes-empty">
                                    <i class="fas fa-inbox"></i>
                                    <p>Aucun produit. Utilisez la recherche ci-dessus.</p>
                                </div>
                            <?php else: ?>
                                <div class="lignes-empty" id="lignes-empty" style="display:none;">
                                    <i class="fas fa-inbox"></i>
                                    <p>Aucun produit. Utilisez la recherche ci-dessus.</p>
                                </div>
                                <?php foreach ($lignes_form as $idx => $l): ?>
                                    <?php
                                    $nom = htmlspecialchars($l['nom_produit'] ?? '');
                                    $pid = (int) ($l['produit_id'] ?? 0);
                                    $q = (int) ($l['quantite'] ?? 1);
                                    $pu = htmlspecialchars((string) ($l['prix_unitaire'] ?? '0'));
                                    $pp = isset($l['prix_promotion']) && $l['prix_promotion'] !== '' ? htmlspecialchars((string) $l['prix_promotion']) : '';
                                    ?>
                                    <div class="ligne-commande-item ligne-commande-item-bl" data-produit-id="<?php echo $pid; ?>">
                                        <?php
                                        $produit_ligne = isset($produits_ligne_map[$pid]) ? $produits_ligne_map[$pid] : null;
                                        devis_ligne_designation_cell_render((int) $idx, $l, $produit_ligne);
                                        ?>
                                        <div class="ligne-bl-cell">
                                            <span class="ligne-bl-label">Quantité</span>
                                            <input type="number" name="lignes[<?php echo (int) $idx; ?>][quantite]" value="<?php echo $q; ?>" min="1" max="99999" class="ligne-qte" aria-label="Quantité">
                                        </div>
                                        <?php
                                        devis_prix_ligne_cellules_render((int) $idx, $l, $champ_prix_calcul_sel, $produit_ligne, $slugs_visibles_mod);
                                        ?>
                                        <div class="ligne-bl-cell ligne-bl-cell-total ligne-bl-cell-total--highlight">
                                            <span class="ligne-bl-label">Total</span>
                                            <strong class="ligne-total-value" aria-live="polite"><?php echo number_format($q * (float) ($l['prix_unitaire'] ?? 0), 0, ',', ' '); ?></strong>
                                        </div>
                                        <button type="button" class="ligne-remove" aria-label="Retirer la ligne"><i class="fas fa-trash"></i></button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                        </div>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="client_nom">Nom <span class="required">*</span></label>
                                <input type="text" id="client_nom" name="client_nom" required value="<?php echo htmlspecialchars($client_nom); ?>">
                            </div>
                            <div class="form-group">
                                <label for="client_prenom">Prénom <span class="optional">(optionnel)</span></label>
                                <input type="text" id="client_prenom" name="client_prenom" value="<?php echo htmlspecialchars($client_prenom); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="client_telephone">Téléphone <span class="required">*</span></label>
                            <input type="tel" id="client_telephone" name="client_telephone" required value="<?php echo htmlspecialchars($client_telephone); ?>">
                        </div>
                        <div class="form-group">
                            <label for="client_email">Email</label>
                            <input type="email" id="client_email" name="client_email" value="<?php echo htmlspecialchars($client_email); ?>">
                        </div>
                        <div class="form-group">
                            <label for="adresse_client">Adresse du client <span class="optional">(optionnel)</span></label>
                            <textarea id="adresse_client" name="adresse_client" rows="2" placeholder="Siège social, rue, complément…"><?php echo htmlspecialchars($adresse_client); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="zone_livraison_id"><i class="fas fa-map-marker-alt"></i> Adresse de livraison <span class="optional">(optionnel)</span></label>
                            <select id="zone_livraison_id" name="zone_livraison_id">
                                <option value="">— Sélectionnez une adresse —</option>
                                <?php foreach ($zones_livraison as $z): ?>
                                <option value="<?php echo (int) $z['id']; ?>"
                                    data-adresse="<?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>"
                                    data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                    <?php echo ((string) $zone_livraison_id === (string) $z['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                    (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                </option>
                                <?php endforeach; ?>
                                <option value="custom" <?php echo ($zone_livraison_id === 'custom' || (is_array($devis_post) && ($devis_post['zone_livraison_id'] ?? '') === 'custom')) ? 'selected' : ''; ?>>— Adresse personnalisée —</option>
                            </select>
                            <?php
                            $adresse_custom = is_array($devis_post) ? ($devis_post['adresse_livraison'] ?? '') : ($devis['adresse_livraison'] ?? '');
                            $show_custom = ($zone_livraison_id === 'custom' || ($devis_post && ($devis_post['zone_livraison_id'] ?? '') === 'custom'));
                            ?>
                            <div id="adresse-custom-wrap" class="adresse-custom-wrap" style="<?php echo $show_custom ? '' : 'display:none;'; ?> margin-top:10px;">
                                <textarea id="adresse_livraison_ta" rows="3" placeholder="Saisissez l'adresse complète"><?php echo htmlspecialchars($adresse_custom); ?></textarea>
                            </div>
                            <div id="adresse-zone-display" class="adresse-zone-display" style="display:none; margin-top:8px; padding:10px; background:#f5f5f4; border-radius:8px;"></div>
                            <input type="hidden" name="adresse_livraison" id="adresse_livraison" value="<?php echo htmlspecialchars($devis['adresse_livraison'] ?? ''); ?>">
                            <input type="hidden" name="frais_livraison" id="frais_livraison" value="<?php echo htmlspecialchars((string) ($devis['frais_livraison'] ?? 0)); ?>">
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2"><?php echo htmlspecialchars($notes); ?></textarea>
                        </div>
                        <div class="form-group devis-option-tva">
                            <label class="checkbox-inline-label" for="inclure_tva_devis">
                                <input type="hidden" name="inclure_tva" value="0">
                                <input type="checkbox" name="inclure_tva" value="1" id="inclure_tva_devis" <?php echo $inclure_tva_checked ? 'checked' : ''; ?>>
                                <span>Inclure la TVA (<?php echo htmlspecialchars((string) $fiscal_tva_pourcent_modifier); ?> %) — total à payer TTC (comme la caisse)</span>
                            </label>
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
                            <div class="recap-line recap-tva-line-devis" id="recap-tva-line-devis" style="<?php echo $inclure_tva_checked ? '' : 'display:none;'; ?>">
                                <span>TVA (<span id="recap-tva-pct-devis"><?php echo htmlspecialchars((string) $fiscal_tva_pourcent_modifier); ?></span> %)</span>
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

            <div class="form-commande-manuelle-actions" style="margin-top: 24px;">
                <a href="devis.php" class="btn-secondary">Annuler</a>
                <button type="submit" class="btn-primary btn-submit-commande"><i class="fas fa-save"></i> Enregistrer les modifications</button>
            </div>
        </form>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script src="/js/admin-produit-search-ui.js<?php echo asset_version_query(); ?>"></script>
    <script>
    (function() {
        var FISCAL_TVA_PCT = <?php echo json_encode((float) $fiscal_tva_pourcent_modifier); ?>;
        var modal = null;
        var searchInput = document.getElementById('search-produit');
        var searchResults = document.getElementById('search-produit-results');
        var searchLoading = document.getElementById('search-loading');
        var lignesContainer = document.getElementById('lignes-commande');
        var lignesEmpty = document.getElementById('lignes-empty');
        var lignesCount = document.getElementById('lignes-count');
        var ligneIndex = <?php echo (int) $nb_lignes; ?>;
        var ajaxUrl = 'ajax_search_produits.php';
        var champPrixPanelModifier = document.getElementById('devis_prix_panel_modifier');
        var headDevisMod = document.getElementById('lignes-head-devis');

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
            var calc = U && U.getChampCalculSlug ? U.getChampCalculSlug(champPrixPanelModifier) : 'prix';
            var idx = ligneIndex++;
            var div = document.createElement('div');
            div.className = 'ligne-commande-item ligne-commande-item-bl';
            div.dataset.produitId = produit.id;
            div.innerHTML = U && U.buildLigneCommandeItemHtml
                ? U.buildLigneCommandeItemHtml(produit, idx, 'lignes', calc, champPrixPanelModifier)
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
            var calc = U && U.getChampCalculSlug ? U.getChampCalculSlug(champPrixPanelModifier) : 'prix';
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

        var UMod = window.FoutaAdminProduitSearchUi;
        if (UMod && UMod.initChampPrixColonnesPanel) {
            UMod.initChampPrixColonnesPanel(champPrixPanelModifier, lignesContainer, headDevisMod, updateRecap);
        }
        if (UMod && UMod.bindLignesLiveRecap) {
            UMod.bindLignesLiveRecap(lignesContainer, updateRecap, champPrixPanelModifier);
        }
        if (UMod && UMod.updateAllLigneTotals) {
            UMod.updateAllLigneTotals(lignesContainer, UMod.getChampCalculSlug(champPrixPanelModifier));
        }

        if (lignesContainer) {
            lignesContainer.querySelectorAll('.ligne-remove').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var row = btn.closest('.ligne-commande-item');
                    if (row) { row.remove(); updateLignesUI(); updateRecap(); }
                });
            });
        }

        if (formDevis) {
            formDevis.addEventListener('submit', function(ev) {
                if (zoneSelect && zoneSelect.value === 'custom' && adresseTa) {
                    if (adresseLivraison) adresseLivraison.value = adresseTa.value.trim();
                } else if (zoneSelect && zoneSelect.value && zoneSelect.value !== 'custom') {
                    onZoneChange();
                }
            });
        }

        var searchTimeout;
        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                var q = searchInput.value.trim();
                searchTimeout = setTimeout(function() { doSearch(q); }, 250);
            });
            searchInput.addEventListener('focus', function() {
                var q = searchInput.value.trim();
                doSearch(q);
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
                    .finally(function() {
                        if (searchClientLoading) searchClientLoading.style.visibility = 'hidden';
                    });
            }
            searchClientInput.addEventListener('input', function() {
                clearTimeout(clientSearchTimeout);
                var q = searchClientInput.value.trim();
                clientSearchTimeout = setTimeout(function() { doClientSearch(q); }, 300);
            });
            searchClientResults.addEventListener('mousedown', function(ev) { ev.preventDefault(); });
        }

        updateLignesUI();
        if (zoneSelect && zoneSelect.value) {
            onZoneChange();
        } else {
            updateRecap();
        }
    })();
    </script>
</body>
</html>
