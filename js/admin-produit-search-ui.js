/**
 * UI partagée : suggestions recherche produit (devis, BL, commandes) et lignes désignation.
 */
(function (global) {
    'use strict';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    /** HTML interne d'une suggestion (fiche riche) : ordre nom · marque · description. */
    function buildSearchResultHtml(p) {
        var nom = esc(p.nom);
        var marque = esc(p.marque_nom || '');
        var desc = esc(p.desc_excerpt || '');
        var fourn = esc(p.fournisseur_nom || '');
        var rff = esc(p.ref_fournisseur || '');
        var rfp = esc(p.ref_produit || '');
        var cat = esc(p.categorie_nom || '');
        var stock = p.stock_dispo || p.stock || 0;
        var prix = parseFloat(p.prix) || 0;
        // Ligne titre : nom · marque · description (même format que les cartes produits)
        var parts = ['<span class="sr-nom">' + nom + '</span>'];
        if (marque) {
            parts.push('<span class="sr-marque">' + marque + '</span>');
        }
        if (desc) {
            parts.push('<span class="sr-desc">' + desc + '</span>');
        }
        var line1 = '<div class="sr-line1">' + parts.join('<span class="sr-sep"> · </span>') + '</div>';
        var refParts = [];
        if (fourn) {
            refParts.push('<span class="sr-ref sr-ref--fourn">' + fourn + '</span>');
        }
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
            '<span class="sr-meta">' + cat + ' · Stock ' + stock + ' · ' + prix + ' FCFA</span>';
        return line1 + refsBlock + meta;
    }

    /** Bloc marque + extrait sous la désignation (commande manuelle, etc.). */
    function buildLigneMetaDivHtml(p) {
        var marque = esc(p.marque_nom || '');
        var desc = esc(p.desc_excerpt || '');
        if (!marque && !desc) {
            return '';
        }
        var metaHtml = '<div class="ligne-produit-meta">';
        if (marque) {
            metaHtml += '<span class="ligne-meta-marque">' + marque + '</span>';
        }
        if (marque && desc) {
            metaHtml += ' · ';
        }
        if (desc) {
            metaHtml += '<span class="ligne-meta-desc">' + desc + '</span>';
        }
        metaHtml += '</div>';
        return metaHtml;
    }

    /**
     * Cellule « désignation » (devis / BL avec classe ligne-bl-cell).
     * @param {object} produit
     * @param {number} idx
     * @param {string} lignesKey — ex. "lignes"
     */
    function buildLigneBlDesignationCellHtml(produit, idx, lignesKey) {
        lignesKey = lignesKey || 'lignes';
        var nom = (produit.nom || '').replace(/"/g, '&quot;');
        var metaHtml = buildLigneMetaDivHtml(produit);
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
            metaHtml +
            '</div>'
        );
    }

    global.FoutaAdminProduitSearchUi = {
        esc: esc,
        buildSearchResultHtml: buildSearchResultHtml,
        buildLigneMetaDivHtml: buildLigneMetaDivHtml,
        buildLigneBlDesignationCellHtml: buildLigneBlDesignationCellHtml
    };
})(typeof window !== 'undefined' ? window : this);
