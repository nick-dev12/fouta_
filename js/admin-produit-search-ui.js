/**
 * UI partagée : suggestions recherche produit (devis, BL, commandes) et lignes commande manuelle.
 */
(function (global) {
    'use strict';

    var SEARCH_PAGE_SIZE = 25;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function formatFcfa(n) {
        var x = Math.round(Number(n) || 0);
        return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f');
    }

    function effectiveLineUnitPrice(row) {
        var prix = parseFloat(row.querySelector('.ligne-prix').value) || 0;
        var promo = row.querySelector('.ligne-prix-promo');
        if (promo && promo.value && parseFloat(promo.value) > 0) {
            return parseFloat(promo.value);
        }
        return prix;
    }

    function computeLineTotal(row) {
        var qte = parseFloat(row.querySelector('.ligne-qte').value) || 0;
        return Math.round(effectiveLineUnitPrice(row) * qte);
    }

    function updateLigneRowTotal(row) {
        if (!row) {
            return;
        }
        var el = row.querySelector('.ligne-total-value');
        if (el) {
            el.textContent = formatFcfa(computeLineTotal(row));
        }
    }

    function updateAllLigneTotals(container) {
        if (!container) {
            return;
        }
        var rows = container.querySelectorAll('.ligne-commande-item');
        for (var i = 0; i < rows.length; i++) {
            updateLigneRowTotal(rows[i]);
        }
    }

    function getLignesSousTotal(container) {
        if (!container) {
            return 0;
        }
        var total = 0;
        var rows = container.querySelectorAll('.ligne-commande-item');
        for (var i = 0; i < rows.length; i++) {
            total += computeLineTotal(rows[i]);
        }
        return total;
    }

    function bindLignesLiveRecap(container, updateRecapFn) {
        if (!container) {
            return;
        }
        function onLineFieldChange(ev) {
            var t = ev.target;
            if (
                !t.classList.contains('ligne-qte') &&
                !t.classList.contains('ligne-prix') &&
                !t.classList.contains('ligne-prix-promo')
            ) {
                return;
            }
            var row = t.closest('.ligne-commande-item');
            updateLigneRowTotal(row);
            if (typeof updateRecapFn === 'function') {
                updateRecapFn();
            }
        }
        container.addEventListener('input', onLineFieldChange);
        container.addEventListener('change', onLineFieldChange);
    }

    function slugVisible(slug) {
        var m = global.adminProduitChampsManifest;
        if (!m || !Array.isArray(m.slugs)) {
            return true;
        }
        return m.slugs.indexOf(slug) !== -1;
    }

    /** HTML suggestion recherche : nom · marque · description + fournisseur + catégorie. */
    function buildSearchResultHtml(p) {
        var nom = esc(p.nom);
        var marque = slugVisible('marque_id') ? esc(p.marque_nom || '') : '';
        var desc = slugVisible('description') ? esc(p.desc_excerpt || '') : '';
        var fourn = slugVisible('fournisseur_id') ? esc(p.fournisseur_nom || '') : '';
        var rff = slugVisible('reference_fournisseur') ? esc(p.ref_fournisseur || '') : '';
        var rfp = slugVisible('identifiant_interne') ? esc(p.ref_produit || '') : '';
        var cat = slugVisible('categorie_id') ? esc(p.categorie_nom || 'Sans catégorie') : '';
        var stock = slugVisible('stock') ? (p.stock_dispo || p.stock || 0) : null;
        var prix = slugVisible('prix') ? (parseFloat(p.prix) || 0) : null;
        var parts = ['<span class="sr-nom">' + nom + '</span>'];
        if (marque) {
            parts.push('<span class="sr-marque">' + marque + '</span>');
        }
        if (desc) {
            parts.push('<span class="sr-desc">' + desc + '</span>');
        }
        var line1 = '<div class="sr-line1">' + parts.join('<span class="sr-sep"> · </span>') + '</div>';
        var fournLine = fourn
            ? '<p class="sr-fournisseur"><i class="fas fa-truck-field" aria-hidden="true"></i> ' + fourn + '</p>'
            : '';
        var catLine = cat
            ? '<p class="sr-categorie"><i class="fas fa-tag" aria-hidden="true"></i> ' + cat + '</p>'
            : '';
        var refParts = [];
        if (rff) {
            refParts.push('<span class="sr-ref">Réf. fourn. <strong>' + rff + '</strong></span>');
        }
        if (rfp) {
            refParts.push('<span class="sr-ref">Réf. prod. <strong>' + rfp + '</strong></span>');
        }
        var refsBlock = refParts.length
            ? '<div class="sr-refs">' + refParts.join(' <span class="sr-ref-sep">·</span> ') + '</div>'
            : '';
        var meta =
            (stock !== null && prix !== null)
                ? '<span class="sr-meta">Stock ' +
                  stock +
                  ' · <strong class="sr-prix">' +
                  formatFcfa(prix) +
                  ' FCFA</strong> HT</span>'
                : (stock !== null
                    ? '<span class="sr-meta">Stock ' + stock + '</span>'
                    : (prix !== null
                        ? '<span class="sr-meta"><strong class="sr-prix">' + formatFcfa(prix) + ' FCFA</strong> HT</span>'
                        : ''));
        return line1 + fournLine + (cat ? catLine : '') + refsBlock + meta;
    }

    function buildLigneBlDesignationCellHtml(produit, idx, lignesKey) {
        lignesKey = lignesKey || 'lignes';
        var nom = (produit.nom || '').replace(/"/g, '&quot;');
        return (
            '<div class="ligne-bl-cell ligne-bl-cell--designation">' +
            '<input type="hidden" name="' +
            lignesKey +
            '[' +
            idx +
            '][produit_id]" value="' +
            produit.id +
            '">' +
            '<span class="ligne-bl-label">Désignation</span>' +
            '<input type="text" name="' +
            lignesKey +
            '[' +
            idx +
            '][nom_produit]" value="' +
            nom +
            '" placeholder="Nom du produit" class="ligne-nom-input" aria-label="Désignation du produit">' +
            '</div>'
        );
    }

    /**
     * Ligne complète devis / BL (qty, prix, promo, total live, supprimer).
     */
    function buildLigneCommandeItemHtml(produit, idx, lignesKey) {
        lignesKey = lignesKey || 'lignes';
        var prix = parseFloat(produit.prix) || 0;
        var prixPromo =
            produit.prix_promotion && parseFloat(produit.prix_promotion) > 0
                ? parseFloat(produit.prix_promotion)
                : '';
        var unit = prixPromo || prix;
        var stockMax = produit.stock_dispo || produit.stock || 999;
        var cellDes = buildLigneBlDesignationCellHtml(produit, idx, lignesKey);

        return (
            cellDes +
            '<div class="ligne-bl-cell">' +
            '<span class="ligne-bl-label">Quantité</span>' +
            '<input type="number" name="' +
            lignesKey +
            '[' +
            idx +
            '][quantite]" value="1" min="1" max="' +
            stockMax +
            '" class="ligne-qte" aria-label="Quantité" inputmode="numeric">' +
            '</div>' +
            '<div class="ligne-bl-cell ligne-bl-cell-prix">' +
            '<span class="ligne-bl-label">Prix unitaire</span>' +
            '<div class="ligne-bl-prix-row">' +
            '<input type="number" name="' +
            lignesKey +
            '[' +
            idx +
            '][prix_unitaire]" value="' +
            unit +
            '" min="0" step="0.01" class="ligne-prix" aria-label="Prix unitaire en FCFA" inputmode="decimal">' +
            '<span class="ligne-unit-fcfa">FCFA</span>' +
            '</div>' +
            '</div>' +
            '<div class="ligne-bl-cell ligne-bl-cell-prix">' +
            '<span class="ligne-bl-label">Prix promo</span>' +
            '<div class="ligne-bl-prix-row">' +
            '<input type="number" name="' +
            lignesKey +
            '[' +
            idx +
            '][prix_promotion]" value="' +
            (prixPromo || '') +
            '" min="0" step="0.01" placeholder="Optionnel" class="ligne-prix-promo" aria-label="Prix promotionnel en FCFA" inputmode="decimal">' +
            '<span class="ligne-unit-fcfa">FCFA</span>' +
            '</div>' +
            '</div>' +
            '<div class="ligne-bl-cell ligne-bl-cell-total">' +
            '<span class="ligne-bl-label">Total</span>' +
            '<strong class="ligne-total-value" aria-live="polite">' +
            formatFcfa(unit) +
            '</strong>' +
            '</div>' +
            '<button type="button" class="ligne-remove" aria-label="Retirer la ligne"><i class="fas fa-trash"></i></button>'
        );
    }

    function createSearchResultElement(p) {
        var el = document.createElement('div');
        el.className = 'search-result-item';
        el.setAttribute('role', 'option');
        el.setAttribute('tabindex', '0');
        el.innerHTML = buildSearchResultHtml(p);
        return el;
    }

    function removeLoadMoreButton(container) {
        if (!container) {
            return;
        }
        var btn = container.querySelector('.search-load-more-btn');
        if (btn) {
            btn.remove();
        }
    }

    /**
     * Recherche AJAX paginée (devis, BL, commandes) avec bouton « Voir plus ».
     * @param {{ajaxUrl?: string, resultsEl: Element, loadingEl?: Element, pageSize?: number, onSelect: function}} opts
     */
    function createAjaxSearchController(opts) {
        opts = opts || {};
        var ajaxUrl = opts.ajaxUrl || 'ajax_search_produits.php';
        var resultsEl = opts.resultsEl;
        var loadingEl = opts.loadingEl || null;
        var pageSize = opts.pageSize || SEARCH_PAGE_SIZE;
        var onSelect = typeof opts.onSelect === 'function' ? opts.onSelect : function () {};
        var state = { q: '', offset: 0, loading: false };

        function bindItem(el, produit) {
            el.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                onSelect(produit);
            });
            el.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    onSelect(produit);
                }
            });
        }

        function appendLoadMore(hasMore) {
            removeLoadMoreButton(resultsEl);
            if (!hasMore || !resultsEl) {
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'search-load-more-btn';
            btn.textContent = 'Voir plus';
            btn.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
            });
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                loadMore();
            });
            resultsEl.appendChild(btn);
        }

        function fetchPage(append) {
            if (!resultsEl || state.loading) {
                return Promise.resolve();
            }
            state.loading = true;
            if (loadingEl) {
                loadingEl.style.visibility = 'visible';
            }
            var offset = append ? state.offset : 0;
            var url =
                ajaxUrl +
                '?q=' +
                encodeURIComponent(state.q) +
                '&limit=' +
                pageSize +
                '&offset=' +
                offset;
            return fetch(url)
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    var items = data.items || [];
                    if (!append) {
                        resultsEl.innerHTML = '';
                        state.offset = 0;
                    } else {
                        removeLoadMoreButton(resultsEl);
                    }
                    if (!append && items.length === 0) {
                        resultsEl.innerHTML =
                            '<div class="search-no-results"><i class="fas fa-box-open"></i> Aucun produit trouvé.</div>';
                    } else if (items.length > 0) {
                        for (var i = 0; i < items.length; i++) {
                            var el = createSearchResultElement(items[i]);
                            bindItem(el, items[i]);
                            resultsEl.appendChild(el);
                        }
                        state.offset += items.length;
                        appendLoadMore(!!data.has_more);
                    }
                    resultsEl.setAttribute('aria-hidden', 'false');
                })
                .catch(function () {
                    if (!append) {
                        resultsEl.innerHTML =
                            '<div class="search-no-results"><i class="fas fa-exclamation-triangle"></i> Erreur de recherche.</div>';
                    }
                })
                .finally(function () {
                    state.loading = false;
                    if (loadingEl) {
                        loadingEl.style.visibility = 'hidden';
                    }
                });
        }

        function search(q) {
            state.q = q == null ? '' : String(q);
            state.offset = 0;
            return fetchPage(false);
        }

        function loadMore() {
            return fetchPage(true);
        }

        return { search: search, loadMore: loadMore };
    }

    global.FoutaAdminProduitSearchUi = {
        SEARCH_PAGE_SIZE: SEARCH_PAGE_SIZE,
        esc: esc,
        formatFcfa: formatFcfa,
        buildSearchResultHtml: buildSearchResultHtml,
        buildLigneBlDesignationCellHtml: buildLigneBlDesignationCellHtml,
        buildLigneCommandeItemHtml: buildLigneCommandeItemHtml,
        updateLigneRowTotal: updateLigneRowTotal,
        updateAllLigneTotals: updateAllLigneTotals,
        getLignesSousTotal: getLignesSousTotal,
        bindLignesLiveRecap: bindLignesLiveRecap,
        createAjaxSearchController: createAjaxSearchController
    };
})(typeof window !== 'undefined' ? window : this);
