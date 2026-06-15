/**
 * UI partagée : suggestions recherche produit (devis, BL, commandes) et lignes commande manuelle.
 */
(function (global) {
    'use strict';

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

    /** HTML suggestion recherche : nom · marque · description + fournisseur + catégorie. */
    function buildSearchResultHtml(p) {
        var nom = esc(p.nom);
        var marque = esc(p.marque_nom || '');
        var desc = esc(p.desc_excerpt || '');
        var fourn = esc(p.fournisseur_nom || '');
        var rff = esc(p.ref_fournisseur || '');
        var rfp = esc(p.ref_produit || '');
        var cat = esc(p.categorie_nom || 'Sans catégorie');
        var stock = p.stock_dispo || p.stock || 0;
        var prix = parseFloat(p.prix) || 0;
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
        var catLine =
            '<p class="sr-categorie"><i class="fas fa-tag" aria-hidden="true"></i> ' + cat + '</p>';
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
            '<span class="sr-meta">Stock ' +
            stock +
            ' · <strong class="sr-prix">' +
            formatFcfa(prix) +
            ' FCFA</strong> HT</span>';
        return line1 + fournLine + catLine + refsBlock + meta;
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

    global.FoutaAdminProduitSearchUi = {
        esc: esc,
        formatFcfa: formatFcfa,
        buildSearchResultHtml: buildSearchResultHtml,
        buildLigneBlDesignationCellHtml: buildLigneBlDesignationCellHtml,
        buildLigneCommandeItemHtml: buildLigneCommandeItemHtml,
        updateLigneRowTotal: updateLigneRowTotal,
        updateAllLigneTotals: updateAllLigneTotals,
        getLignesSousTotal: getLignesSousTotal,
        bindLignesLiveRecap: bindLignesLiveRecap
    };
})(typeof window !== 'undefined' ? window : this);
