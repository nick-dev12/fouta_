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

    function getDevisPrixManifest() {
        return global.adminDevisPrixChamps || null;
    }

    function getDevisPrixChamps() {
        var cfg = getDevisPrixManifest();
        if (cfg && Array.isArray(cfg.champs) && cfg.champs.length > 0) {
            return cfg.champs;
        }
        return [
            { slug: 'prix', label: 'Prix unitaire', source: 'system', key: 'prix' },
            { slug: 'prix_promotion', label: 'Prix promo', source: 'system', key: 'prix_promotion' }
        ];
    }

    function getChampCalculDefaut() {
        var cfg = getDevisPrixManifest();
        if (cfg && cfg.champ_calcul_defaut) {
            return String(cfg.champ_calcul_defaut);
        }
        return 'prix';
    }

    function getColonnesDefaut() {
        var cfg = getDevisPrixManifest();
        if (cfg && Array.isArray(cfg.colonnes_defaut) && cfg.colonnes_defaut.length > 0) {
            return cfg.colonnes_defaut.slice();
        }
        return ['prix', 'prix_promotion'];
    }

    function getPrixPanel(fromEl) {
        if (fromEl && fromEl.getAttribute && fromEl.getAttribute('data-devis-prix-panel') === '1') {
            return fromEl;
        }
        if (fromEl && fromEl.closest) {
            var inForm = fromEl.closest('form');
            if (inForm) {
                var p = inForm.querySelector('[data-devis-prix-panel]');
                if (p) {
                    return p;
                }
            }
        }
        return document.querySelector('[data-devis-prix-panel]');
    }

    function getVisiblePrixSlugs(panelEl) {
        panelEl = getPrixPanel(panelEl);
        if (!panelEl) {
            return getColonnesDefaut();
        }
        var slugs = [];
        panelEl.querySelectorAll('input[data-prix-colonne]:checked').forEach(function (cb) {
            if (cb.value) {
                slugs.push(String(cb.value));
            }
        });
        if (slugs.length === 0) {
            return getColonnesDefaut().filter(function (s) {
                return findChampBySlug(s) !== null;
            });
        }
        return slugs.filter(function (s) {
            return findChampBySlug(s) !== null;
        });
    }

    function getVisiblePrixChamps(panelEl) {
        var slugs = getVisiblePrixSlugs(panelEl);
        var all = getDevisPrixChamps();
        var out = [];
        for (var i = 0; i < all.length; i++) {
            if (slugs.indexOf(all[i].slug) !== -1) {
                out.push(all[i]);
            }
        }
        return out;
    }

    function getChampCalculSlug(panelEl) {
        panelEl = getPrixPanel(panelEl);
        if (panelEl) {
            var radio = panelEl.querySelector('input[data-prix-calcul-radio]:checked');
            if (radio && radio.value) {
                var vis = getVisiblePrixSlugs(panelEl);
                if (vis.indexOf(String(radio.value)) !== -1) {
                    return String(radio.value);
                }
            }
        }
        var visSlugs = getVisiblePrixSlugs(panelEl);
        if (visSlugs.indexOf('prix') !== -1) {
            return 'prix';
        }
        if (visSlugs.length > 0) {
            return visSlugs[0];
        }
        return getChampCalculDefaut();
    }

    function updatePrixApercu(panelEl) {
        panelEl = getPrixPanel(panelEl);
        if (!panelEl) {
            return;
        }
        var el = panelEl.querySelector('[data-devis-prix-apercu-cols]');
        if (!el) {
            return;
        }
        var parts = ['Produit', 'Quantité'];
        var champs = getVisiblePrixChamps(panelEl);
        for (var i = 0; i < champs.length; i++) {
            parts.push(champs[i].label);
        }
        parts.push('Total');
        el.textContent = parts.join(' · ');
        panelEl.querySelectorAll('.devis-prix-colonne-chip').forEach(function (chip) {
            var cb = chip.querySelector('input[data-prix-colonne]');
            var radio = chip.querySelector('input[data-prix-calcul-radio]');
            chip.classList.toggle('is-checked', !!(cb && cb.checked));
            chip.classList.toggle('is-calc', !!(radio && radio.checked));
        });
    }

    function getProduitPrixValue(produit, champ) {
        if (!produit || !champ) {
            return 0;
        }
        if (champ.source === 'custom') {
            var slug = champ.slug;
            var custom = produit.pf_custom && typeof produit.pf_custom === 'object' ? produit.pf_custom : {};
            var raw = custom[slug];
            if (raw !== undefined && raw !== null && raw !== '') {
                return parseFloat(raw) || 0;
            }
            return 0;
        }
        var key = champ.key || champ.slug;
        if (key === 'prix_promotion') {
            var promo = produit.prix_promotion || produit.prix_promo;
            if (promo !== null && promo !== undefined && promo !== '' && parseFloat(promo) > 0) {
                return parseFloat(promo) || 0;
            }
            return 0;
        }
        return parseFloat(produit[key]) || 0;
    }

    function findChampBySlug(slug) {
        var champs = getDevisPrixChamps();
        for (var i = 0; i < champs.length; i++) {
            if (champs[i].slug === slug) {
                return champs[i];
            }
        }
        return null;
    }

    function effectiveLineUnitPrice(row, calcSlug) {
        if (!row) {
            return 0;
        }
        calcSlug = calcSlug || getChampCalculSlug();
        var input = row.querySelector('.ligne-prix-champ[data-slug="' + calcSlug + '"]');
        if (input) {
            return parseFloat(input.value) || 0;
        }
        var legacy = row.querySelector('.ligne-prix');
        if (legacy) {
            var promo = row.querySelector('.ligne-prix-promo');
            if (promo && promo.value && parseFloat(promo.value) > 0) {
                return parseFloat(promo.value);
            }
            return parseFloat(legacy.value) || 0;
        }
        return 0;
    }

    function computeLineTotal(row, calcSlug) {
        var qte = parseFloat(row.querySelector('.ligne-qte').value) || 0;
        return Math.round(effectiveLineUnitPrice(row, calcSlug) * qte);
    }

    function updateLigneRowTotal(row, calcSlug) {
        if (!row) {
            return;
        }
        var el = row.querySelector('.ligne-total-value');
        if (el) {
            el.textContent = formatFcfa(computeLineTotal(row, calcSlug));
        }
        var calc = calcSlug || getChampCalculSlug();
        var hidden = row.querySelector('.ligne-prix-unitaire-calc');
        if (hidden) {
            hidden.value = effectiveLineUnitPrice(row, calc);
        }
    }

    function updateAllLigneTotals(container, calcSlug) {
        if (!container) {
            return;
        }
        var rows = container.querySelectorAll('.ligne-commande-item');
        for (var i = 0; i < rows.length; i++) {
            updateLigneRowTotal(rows[i], calcSlug);
        }
    }

    function getLignesSousTotal(container, calcSlug) {
        if (!container) {
            return 0;
        }
        var total = 0;
        var rows = container.querySelectorAll('.ligne-commande-item');
        for (var i = 0; i < rows.length; i++) {
            total += computeLineTotal(rows[i], calcSlug);
        }
        return total;
    }

    function bindLignesLiveRecap(container, updateRecapFn, panelEl) {
        if (!container) {
            return;
        }
        function onLineFieldChange(ev) {
            var t = ev.target;
            if (
                !t.classList.contains('ligne-qte') &&
                !t.classList.contains('ligne-prix-champ') &&
                !t.classList.contains('ligne-prix') &&
                !t.classList.contains('ligne-prix-promo')
            ) {
                return;
            }
            var row = t.closest('.ligne-commande-item');
            var calc = getChampCalculSlug(panelEl);
            updateLigneRowTotal(row, calc);
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

    function buildSearchResultHtml(p) {
        var nom = esc(p.nom);
        var marque = slugVisible('marque_id') ? esc(p.marque_nom || '') : '';
        var desc = slugVisible('description') ? esc(p.desc_excerpt || '') : '';
        var fourn = slugVisible('fournisseur_id') ? esc(p.fournisseur_nom || '') : '';
        var rff = slugVisible('reference_fournisseur') ? esc(p.ref_fournisseur || '') : '';
        var rfp = slugVisible('identifiant_interne') ? esc(p.ref_produit || '') : '';
        var cat = slugVisible('categorie_id') ? esc(p.categorie_nom || 'Sans catégorie') : '';
        var stock = slugVisible('stock') ? (p.stock_dispo || p.stock || 0) : null;
        var calcSlug = getChampCalculSlug();
        var champ = findChampBySlug(calcSlug) || getDevisPrixChamps()[0];
        var prix = champ ? getProduitPrixValue(p, champ) : (parseFloat(p.prix) || 0);
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
            stock !== null && prix > 0
                ? '<span class="sr-meta">Stock ' +
                  stock +
                  ' · <strong class="sr-prix">' +
                  formatFcfa(prix) +
                  ' FCFA</strong> HT</span>'
                : stock !== null
                    ? '<span class="sr-meta">Stock ' + stock + '</span>'
                    : prix > 0
                        ? '<span class="sr-meta"><strong class="sr-prix">' + formatFcfa(prix) + ' FCFA</strong> HT</span>'
                        : '';
        return line1 + fournLine + (cat ? catLine : '') + refsBlock + meta;
    }

    function buildLigneThumbHtml(produit) {
        if (!slugVisible('images_produit')) {
            return '';
        }
        var img = produit && produit.image_principale ? String(produit.image_principale).trim() : '';
        if (!img) {
            return '<span class="ligne-bl-thumb ligne-bl-thumb--ph" aria-hidden="true"><i class="fas fa-box"></i></span>';
        }
        var src = img.charAt(0) === '/' ? img : '/upload/' + img.replace(/^\/+/, '');
        return (
            '<img src="' +
            esc(src) +
            '" alt="" class="ligne-bl-thumb" loading="lazy" decoding="async" onerror="this.style.display=\'none\';if(this.nextElementSibling)this.nextElementSibling.style.display=\'flex\';">' +
            '<span class="ligne-bl-thumb ligne-bl-thumb--ph" style="display:none" aria-hidden="true"><i class="fas fa-box"></i></span>'
        );
    }

    function buildLigneBlDesignationCellHtml(produit, idx, lignesKey) {
        lignesKey = lignesKey || 'lignes';
        var nom = (produit.nom || '').replace(/"/g, '&quot;');
        var thumb = buildLigneThumbHtml(produit);
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
            '<div class="ligne-bl-designation-inner">' +
            thumb +
            '<input type="text" name="' +
            lignesKey +
            '[' +
            idx +
            '][nom_produit]" value="' +
            nom +
            '" placeholder="Nom du produit" class="ligne-nom-input" aria-label="Désignation du produit">' +
            '</div></div>'
        );
    }

    function buildLignePrixChampsHtml(produit, idx, lignesKey, calcSlug, panelEl, existingVals) {
        lignesKey = lignesKey || 'lignes';
        panelEl = getPrixPanel(panelEl);
        calcSlug = calcSlug || getChampCalculSlug(panelEl);
        var champs = getVisiblePrixChamps(panelEl);
        existingVals = existingVals && typeof existingVals === 'object' ? existingVals : {};
        var html = '';
        var unitCalc = 0;
        for (var i = 0; i < champs.length; i++) {
            var ch = champs[i];
            var val = existingVals[ch.slug];
            if (val === undefined || val === null || val === '') {
                val = getProduitPrixValue(produit, ch);
            } else {
                val = parseFloat(val) || 0;
            }
            if (ch.slug === calcSlug) {
                unitCalc = parseFloat(val) || 0;
            }
            var displayVal = val > 0 ? val : '';
            html +=
                '<div class="ligne-bl-cell ligne-bl-cell-prix' +
                (ch.slug === calcSlug ? ' ligne-bl-cell-prix--calc' : '') +
                '" data-prix-col-slug="' +
                esc(ch.slug) +
                '">' +
                '<span class="ligne-bl-label">' +
                esc(ch.label) +
                '</span>' +
                '<div class="ligne-bl-prix-row">' +
                '<input type="number" name="' +
                lignesKey +
                '[' +
                idx +
                '][prix_champs][' +
                esc(ch.slug) +
                ']" value="' +
                displayVal +
                '" min="0" step="0.01" class="ligne-prix-champ" data-slug="' +
                esc(ch.slug) +
                '" aria-label="' +
                esc(ch.label) +
                ' en FCFA" inputmode="decimal">' +
                '<span class="ligne-unit-fcfa">FCFA</span>' +
                '</div>' +
                '</div>';
        }
        html +=
            '<input type="hidden" class="ligne-prix-unitaire-calc" name="' +
            lignesKey +
            '[' +
            idx +
            '][prix_unitaire]" value="' +
            unitCalc +
            '">';

        return html;
    }

    function collectRowPrixValues(row) {
        var vals = {};
        if (!row) {
            return vals;
        }
        row.querySelectorAll('.ligne-prix-champ').forEach(function (inp) {
            if (inp.dataset.slug) {
                vals[inp.dataset.slug] = inp.value;
            }
        });
        return vals;
    }

    function rebuildRowPrixCells(row, panelEl, calcSlug) {
        if (!row) {
            return;
        }
        panelEl = getPrixPanel(panelEl);
        calcSlug = calcSlug || getChampCalculSlug(panelEl);
        var vals = collectRowPrixValues(row);
        var idxMatch = null;
        var hiddenPu = row.querySelector('.ligne-prix-unitaire-calc');
        if (hiddenPu && hiddenPu.name) {
            var m = hiddenPu.name.match(/\[(\d+)\]/);
            if (m) {
                idxMatch = m[1];
            }
        }
        if (idxMatch === null) {
            var anyInp = row.querySelector('input[name*="[prix_champs]"]');
            if (anyInp && anyInp.name) {
                m = anyInp.name.match(/\[(\d+)\]/);
                if (m) {
                    idxMatch = m[1];
                }
            }
        }
        if (idxMatch === null) {
            idxMatch = '0';
        }
        row.querySelectorAll('.ligne-bl-cell-prix, .ligne-prix-unitaire-calc').forEach(function (el) {
            el.remove();
        });
        var totalCell = row.querySelector('.ligne-bl-cell-total');
        var tmp = document.createElement('div');
        tmp.innerHTML = buildLignePrixChampsHtml({}, idxMatch, 'lignes', calcSlug, panelEl, vals);
        var frag = document.createDocumentFragment();
        while (tmp.firstChild) {
            frag.appendChild(tmp.firstChild);
        }
        if (totalCell) {
            row.insertBefore(frag, totalCell);
        } else {
            row.appendChild(frag);
        }
        updateLigneRowTotal(row, calcSlug);
    }

    function refreshLignesForPrixColumns(container, headEl, panelEl, updateRecapFn) {
        if (!container) {
            return;
        }
        panelEl = getPrixPanel(panelEl);
        var calc = getChampCalculSlug(panelEl);
        buildLignesHeadHtml(headEl, container, panelEl);
        updatePrixApercu(panelEl);
        var rows = container.querySelectorAll('.ligne-commande-item');
        for (var i = 0; i < rows.length; i++) {
            rebuildRowPrixCells(rows[i], panelEl, calc);
        }
        if (typeof updateRecapFn === 'function') {
            updateRecapFn();
        }
    }

    function buildLigneCommandeItemHtml(produit, idx, lignesKey, calcSlug, panelEl) {
        lignesKey = lignesKey || 'lignes';
        panelEl = getPrixPanel(panelEl);
        calcSlug = calcSlug || getChampCalculSlug(panelEl);
        var stockMax = produit.stock_dispo || produit.stock || 999;
        var cellDes = buildLigneBlDesignationCellHtml(produit, idx, lignesKey);
        var prixCells = buildLignePrixChampsHtml(produit, idx, lignesKey, calcSlug, panelEl);
        var unit = getProduitPrixValue(produit, findChampBySlug(calcSlug) || getVisiblePrixChamps(panelEl)[0]);

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
            prixCells +
            '<div class="ligne-bl-cell ligne-bl-cell-total ligne-bl-cell-total--highlight">' +
            '<span class="ligne-bl-label">Total</span>' +
            '<strong class="ligne-total-value" aria-live="polite">' +
            formatFcfa(unit) +
            '</strong>' +
            '</div>' +
            '<button type="button" class="ligne-remove" aria-label="Retirer la ligne"><i class="fas fa-trash"></i></button>'
        );
    }

    function syncDevisPrixGridCols(wrapEl, panelEl) {
        if (!wrapEl) {
            return;
        }
        panelEl = getPrixPanel(panelEl);
        var n = getVisiblePrixChamps(panelEl).length;
        wrapEl.style.setProperty('--devis-prix-cols', String(Math.max(1, n)));
    }

    function buildLignesHeadHtml(headEl, wrapEl, panelEl) {
        if (!headEl) {
            return;
        }
        panelEl = getPrixPanel(panelEl);
        syncDevisPrixGridCols(wrapEl || headEl.closest('.lignes-commande-modal-wrap'), panelEl);
        var champs = getVisiblePrixChamps(panelEl);
        var prixHead = '';
        for (var i = 0; i < champs.length; i++) {
            prixHead +=
                '<span class="lch-head-cell" data-prix-head-slug="' +
                esc(champs[i].slug) +
                '">' +
                esc(champs[i].label) +
                ' FCFA</span>';
        }
        headEl.innerHTML =
            '<span class="lch-head-cell">Produit</span>' +
            '<span class="lch-head-cell">Quantité</span>' +
            prixHead +
            '<span class="lch-head-cell">Total</span>' +
            '<span class="lch-head-cell lch-head-actions" aria-hidden="true"></span>';
    }

    function ensureCalcRadioValid(panelEl) {
        panelEl = getPrixPanel(panelEl);
        if (!panelEl) {
            return;
        }
        var vis = getVisiblePrixSlugs(panelEl);
        var checkedRadio = panelEl.querySelector('input[data-prix-calcul-radio]:checked');
        if (!checkedRadio || vis.indexOf(String(checkedRadio.value)) === -1) {
            var pick = vis.indexOf('prix') !== -1 ? 'prix' : vis[0];
            if (pick) {
                var r = panelEl.querySelector('input[data-prix-calcul-radio][value="' + pick + '"]');
                if (r) {
                    r.checked = true;
                }
            }
        }
        if (vis.length === 1) {
            var only = panelEl.querySelector('input[data-prix-calcul-radio][value="' + vis[0] + '"]');
            if (only) {
                only.checked = true;
            }
        }
    }

    function initChampPrixColonnesPanel(panelEl, container, headEl, updateRecapFn) {
        panelEl = getPrixPanel(panelEl);
        if (!panelEl) {
            return;
        }
        ensureCalcRadioValid(panelEl);
        updatePrixApercu(panelEl);
        buildLignesHeadHtml(headEl, container, panelEl);

        function onPanelChange() {
            var boxes = panelEl.querySelectorAll('input[data-prix-colonne]');
            var checkedCount = panelEl.querySelectorAll('input[data-prix-colonne]:checked').length;
            boxes.forEach(function (cb) {
                if (checkedCount <= 1 && cb.checked) {
                    cb.disabled = true;
                } else {
                    cb.disabled = false;
                }
            });
            ensureCalcRadioValid(panelEl);
            refreshLignesForPrixColumns(container, headEl, panelEl, updateRecapFn);
        }

        panelEl.addEventListener('change', function (ev) {
            var t = ev.target;
            if (!t.matches('input[data-prix-colonne], input[data-prix-calcul-radio]')) {
                return;
            }
            if (t.matches('input[data-prix-colonne]') && !t.checked) {
                var remaining = panelEl.querySelectorAll('input[data-prix-colonne]:checked').length;
                if (remaining === 0) {
                    t.checked = true;
                    return;
                }
            }
            onPanelChange();
        });

        onPanelChange();
    }

    /** @deprecated */
    function initChampPrixCalculSelect(selectEl, container, updateRecapFn) {
        var headEl = container ? container.querySelector('.ligne-commande-head') : null;
        initChampPrixColonnesPanel(selectEl, container, headEl, updateRecapFn);
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
        getDevisPrixChamps: getDevisPrixChamps,
        getChampCalculSlug: getChampCalculSlug,
        getProduitPrixValue: getProduitPrixValue,
        buildSearchResultHtml: buildSearchResultHtml,
        buildLigneBlDesignationCellHtml: buildLigneBlDesignationCellHtml,
        buildLigneCommandeItemHtml: buildLigneCommandeItemHtml,
        buildLignesHeadHtml: buildLignesHeadHtml,
        syncDevisPrixGridCols: syncDevisPrixGridCols,
        initChampPrixColonnesPanel: initChampPrixColonnesPanel,
        initChampPrixCalculSelect: initChampPrixCalculSelect,
        refreshLignesForPrixColumns: refreshLignesForPrixColumns,
        updatePrixApercu: updatePrixApercu,
        getVisiblePrixSlugs: getVisiblePrixSlugs,
        updateLigneRowTotal: updateLigneRowTotal,
        updateAllLigneTotals: updateAllLigneTotals,
        getLignesSousTotal: getLignesSousTotal,
        bindLignesLiveRecap: bindLignesLiveRecap,
        createAjaxSearchController: createAjaxSearchController
    };
})(typeof window !== 'undefined' ? window : this);
