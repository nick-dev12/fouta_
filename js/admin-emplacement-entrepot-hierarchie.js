/**
 * Navigation hiérarchique entrepôt — tableaux Zone → Rayon → Étagère → Barre → Position.
 */
(function () {
    'use strict';

    function rowLabel(row) {
        if (!row) {
            return '';
        }
        var name = row.querySelector('.ee-entity-name');
        return name ? name.textContent.trim() : '';
    }

    function setSelectedRow(rows, activeRow) {
        rows.forEach(function (row) {
            var on = row === activeRow;
            row.classList.toggle('is-selected', on);
            row.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function bindPickRow(row, handler) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('form, button, a')) {
                return;
            }
            handler(row);
        });
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handler(row);
            }
        });
    }

    document.querySelectorAll('[data-ee-hierarchie-root]').forEach(function (root) {
        var zoneRows = root.querySelectorAll('[data-ee-item="zone"]');
        var rayonRows = root.querySelectorAll('[data-ee-item="rayon"]');
        var rayonsSection = root.querySelector('[data-ee-rayons-section]');
        var rayonsEmpty = root.querySelector('[data-ee-rayons-empty]');
        var drillRoot = root.querySelector('[data-ee-drill]');
        var drillHint = root.querySelector('[data-ee-drill-hint]');
        var panels = {
            etageres: root.querySelector('[data-ee-level="etageres"]'),
            barres: root.querySelector('[data-ee-level="barres"]'),
            positions: root.querySelector('[data-ee-level="positions"]')
        };
        var crumbBack = root.querySelector('[data-ee-crumb-back]');
        var crumbTrail = root.querySelector('[data-ee-crumb-trail]');
        var etagereRows = root.querySelectorAll('[data-ee-item="etagere"]');
        var barreRows = root.querySelectorAll('[data-ee-item="barre"]');
        var barreDetails = root.querySelectorAll('[data-ee-barre-detail]');
        var etageresEmpty = root.querySelector('[data-ee-etageres-empty]');
        var barresEmpty = root.querySelector('[data-ee-barres-empty]');

        if (!zoneRows.length && !rayonRows.length) {
            return;
        }

        var niveauxActifs = [];
        try {
            niveauxActifs = JSON.parse(root.getAttribute('data-ee-niveaux-actifs') || '[]');
        } catch (e) {
            niveauxActifs = [];
        }
        var hasZone = niveauxActifs.indexOf('zone') !== -1;
        var hasRayon = niveauxActifs.indexOf('rayon') !== -1;
        var hasEtagere = niveauxActifs.indexOf('etagere') !== -1;
        var hasBarre = niveauxActifs.indexOf('barre') !== -1;
        var hasPosition = niveauxActifs.indexOf('position') !== -1;

        function firstDrillPanelAfterRayon() {
            if (hasEtagere) {
                return 'etageres';
            }
            if (hasBarre) {
                return 'barres';
            }
            if (hasPosition) {
                return 'positions';
            }

            return null;
        }

        var state = {
            zoneId: null,
            rayonId: null,
            etagereId: null,
            barreId: null,
            level: null
        };

        function matchesZone(row) {
            return row.getAttribute('data-ee-zone') === String(state.zoneId)
                || row.getAttribute('data-ee-id') === String(state.zoneId);
        }

        function matchesRayon(row) {
            return row.getAttribute('data-ee-zone') === String(state.zoneId)
                && row.getAttribute('data-ee-rayon') === String(state.rayonId);
        }

        function matchesEtagere(row) {
            return matchesRayon(row) && row.getAttribute('data-ee-etagere') === String(state.etagereId);
        }

        function hideAllDrillRows() {
            etagereRows.forEach(function (row) {
                row.setAttribute('hidden', '');
            });
            barreRows.forEach(function (row) {
                row.setAttribute('hidden', '');
            });
            barreDetails.forEach(function (block) {
                block.setAttribute('hidden', '');
            });
            if (etageresEmpty) {
                etageresEmpty.hidden = true;
            }
            if (barresEmpty) {
                barresEmpty.hidden = true;
            }
        }

        function clearRayonSelection() {
            setSelectedRow(rayonRows, null);
            state.rayonId = null;
        }

        function hideDrillPanels() {
            state.level = null;
            state.etagereId = null;
            state.barreId = null;
            hideAllDrillRows();
            Object.keys(panels).forEach(function (key) {
                var panel = panels[key];
                if (!panel) {
                    return;
                }
                panel.classList.remove('is-active');
                panel.setAttribute('hidden', '');
            });
            if (drillHint) {
                drillHint.hidden = false;
            }
            if (crumbBack) {
                crumbBack.hidden = true;
            }
            if (crumbTrail) {
                crumbTrail.textContent = '';
            }
        }

        function showDrill() {
            if (drillRoot) {
                drillRoot.removeAttribute('hidden');
            }
        }

        function showPanel(level) {
            state.level = level;
            if (drillHint) {
                drillHint.hidden = true;
            }
            Object.keys(panels).forEach(function (key) {
                var panel = panels[key];
                if (!panel) {
                    return;
                }
                var show = key === level;
                panel.classList.toggle('is-active', show);
                if (show) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', '');
                }
            });
            if (crumbBack) {
                crumbBack.hidden = level === 'etageres';
            }
            updateCrumb();
        }

        function updateCrumb() {
            if (!crumbTrail) {
                return;
            }
            var parts = [];
            var zoneRow = root.querySelector('[data-ee-item="zone"].is-selected');
            var rayonRow = root.querySelector('[data-ee-item="rayon"].is-selected');
            if (zoneRow) {
                parts.push(rowLabel(zoneRow));
            }
            if (rayonRow) {
                parts.push(rowLabel(rayonRow));
            }
            if (state.etagereId) {
                var etRow = root.querySelector('[data-ee-item="etagere"][data-ee-id="' + state.etagereId + '"]');
                if (etRow) {
                    parts.push(rowLabel(etRow));
                }
            }
            if (state.barreId) {
                var bRow = root.querySelector('[data-ee-item="barre"][data-ee-id="' + state.barreId + '"]');
                if (bRow) {
                    parts.push(rowLabel(bRow));
                }
            }
            crumbTrail.textContent = parts.join(' › ');
        }

        function filterRayons() {
            var visible = 0;
            rayonRows.forEach(function (row) {
                var show = !hasZone || row.getAttribute('data-ee-zone') === String(state.zoneId);
                if (show) {
                    row.removeAttribute('hidden');
                    visible++;
                } else {
                    row.setAttribute('hidden', '');
                    row.classList.remove('is-selected');
                    row.setAttribute('aria-selected', 'false');
                }
            });
            if (rayonsSection) {
                rayonsSection.removeAttribute('hidden');
            }
            if (rayonsEmpty) {
                rayonsEmpty.hidden = visible > 0;
            }
        }

        function filterEtageres() {
            var visible = 0;
            etagereRows.forEach(function (row) {
                var show = matchesRayon(row);
                if (show) {
                    row.removeAttribute('hidden');
                    visible++;
                } else {
                    row.setAttribute('hidden', '');
                }
            });
            if (etageresEmpty) {
                etageresEmpty.hidden = visible > 0;
            }
        }

        function filterBarres() {
            var visible = 0;
            barreRows.forEach(function (row) {
                var show = matchesEtagere(row);
                if (show) {
                    row.removeAttribute('hidden');
                    visible++;
                } else {
                    row.setAttribute('hidden', '');
                }
            });
            if (barresEmpty) {
                barresEmpty.hidden = visible > 0;
            }
        }

        function showBarreDetail() {
            barreDetails.forEach(function (block) {
                var show = block.getAttribute('data-ee-barre-detail') === String(state.barreId)
                    && block.getAttribute('data-ee-zone') === String(state.zoneId)
                    && block.getAttribute('data-ee-rayon') === String(state.rayonId)
                    && block.getAttribute('data-ee-etagere') === String(state.etagereId);
                if (show) {
                    block.removeAttribute('hidden');
                } else {
                    block.setAttribute('hidden', '');
                }
            });
        }

        function onZoneSelect(row) {
            state.zoneId = row.getAttribute('data-ee-id');
            setSelectedRow(zoneRows, row);
            clearRayonSelection();
            filterRayons();
            hideDrillPanels();
            showDrill();
        }

        function onRayonSelect(row) {
            if (hasZone && row.getAttribute('data-ee-zone') !== String(state.zoneId)) {
                return;
            }
            if (!hasZone) {
                state.zoneId = row.getAttribute('data-ee-zone');
            }
            state.rayonId = row.getAttribute('data-ee-id');
            setSelectedRow(rayonRows, row);
            state.etagereId = null;
            state.barreId = null;
            hideAllDrillRows();
            showDrill();
            var panel = firstDrillPanelAfterRayon();
            if (panel) {
                showPanel(panel);
                if (panel === 'etageres') {
                    filterEtageres();
                } else if (panel === 'barres') {
                    filterBarres();
                } else if (panel === 'positions') {
                    showBarreDetail();
                }
            }
        }

        function onEtagereSelect(row) {
            if (!matchesRayon(row)) {
                return;
            }
            state.etagereId = row.getAttribute('data-ee-id');
            state.barreId = null;
            barreRows.forEach(function (r) {
                r.setAttribute('hidden', '');
            });
            barreDetails.forEach(function (block) {
                block.setAttribute('hidden', '');
            });
            showPanel('barres');
            filterBarres();
        }

        function onBarreSelect(row) {
            if (!matchesEtagere(row)) {
                return;
            }
            state.barreId = row.getAttribute('data-ee-id');
            showPanel('positions');
            showBarreDetail();
        }

        zoneRows.forEach(function (row) {
            if (hasZone) {
                bindPickRow(row, onZoneSelect);
            }
        });

        rayonRows.forEach(function (row) {
            bindPickRow(row, onRayonSelect);
        });

        if (!hasZone && hasRayon) {
            filterRayons();
        }

        etagereRows.forEach(function (row) {
            bindPickRow(row, onEtagereSelect);
        });

        barreRows.forEach(function (row) {
            bindPickRow(row, onBarreSelect);
        });

        if (crumbBack) {
            crumbBack.addEventListener('click', function () {
                if (state.level === 'positions') {
                    state.barreId = null;
                    showPanel('barres');
                    filterBarres();
                } else if (state.level === 'barres') {
                    state.etagereId = null;
                    showPanel('etageres');
                    filterEtageres();
                }
            });
        }
    });

    var EE_EDIT_META = {
        zone: {
            title: 'Modifier la zone',
            icon: 'fa-map-marker-alt',
            hint: 'Le numéro et le nom doivent être uniques sur ce niveau.'
        },
        rayon: {
            title: 'Modifier le rayon',
            icon: 'fa-th-large',
            hint: 'Le numéro et le nom doivent être uniques sur ce niveau (toutes zones confondues).'
        },
        etagere: {
            title: 'Modifier l’étagère',
            icon: 'fa-bars-staggered',
            hint: 'Le numéro et le nom doivent être uniques sur le même rayon.'
        },
        barre: {
            title: 'Modifier la barre',
            icon: 'fa-grip-lines',
            hint: 'Le numéro et le nom doivent être uniques sur le même rayon.'
        },
        position: {
            title: 'Modifier la position',
            icon: 'fa-crosshairs',
            hint: 'Le numéro et le nom doivent être uniques sur la même barre.'
        }
    };

    window.eeOpenEditHierarchie = function (btn) {
        if (!btn) {
            return;
        }
        var type = btn.getAttribute('data-ee-edit-type') || '';
        var table = btn.getAttribute('data-ee-edit-table') || '';
        var meta = EE_EDIT_META[type] || { title: 'Modifier', icon: 'fa-pen', hint: '' };
        var idEl = document.getElementById('ee_edit_id');
        var tableEl = document.getElementById('ee_edit_table');
        var etageEl = document.getElementById('ee_edit_etage_id');
        var niveauEl = document.getElementById('ee_edit_numero_niveau');
        var numeroEl = document.getElementById('ee_edit_numero');
        var nomEl = document.getElementById('ee_edit_nom');
        var titleText = document.getElementById('ee_edit_title_text');
        var iconEl = document.getElementById('ee_edit_icon');
        var hintEl = document.getElementById('ee_edit_hint');

        if (!idEl || !tableEl || !numeroEl || !nomEl) {
            return;
        }

        idEl.value = btn.getAttribute('data-ee-edit-id') || '';
        tableEl.value = table;
        if (etageEl) {
            etageEl.value = btn.getAttribute('data-ee-edit-etage') || '';
        }
        if (niveauEl) {
            var nv = btn.getAttribute('data-ee-edit-niveau');
            if (nv) {
                niveauEl.value = nv;
            }
        }
        numeroEl.value = btn.getAttribute('data-ee-edit-numero') || '1';
        nomEl.value = btn.getAttribute('data-ee-edit-nom') || '';

        if (titleText) {
            titleText.textContent = meta.title;
        }
        if (iconEl) {
            iconEl.className = 'fas ' + meta.icon;
        }
        if (hintEl) {
            hintEl.textContent = meta.hint;
        }

        if (typeof window.openModal === 'function') {
            window.openModal('modalEditHierarchie');
        }
        window.setTimeout(function () {
            numeroEl.focus();
            numeroEl.select();
        }, 80);
    };

    function eeRenderDeleteImpact(data) {
        var titleEl = document.getElementById('ee_delete_h_impact_title');
        var introEl = document.getElementById('ee_delete_h_impact_intro');
        var statsEl = document.getElementById('ee_delete_h_impact_stats');
        var warningsEl = document.getElementById('ee_delete_h_impact_warnings');
        var titleText = document.getElementById('ee_delete_h_title_text');

        if (titleText) {
            titleText.textContent = data.mode === 'niveau' ? 'Supprimer le niveau' : 'Supprimer l’élément';
        }
        if (titleEl) {
            titleEl.textContent = 'Impact — ' + (data.label || '');
        }
        if (introEl) {
            introEl.textContent = 'Vous êtes sur le point de supprimer « ' + (data.label || '') + ' » et tout ce qui est lié.';
        }
        if (statsEl) {
            var cards = [];
            (data.entites || []).forEach(function (ent) {
                cards.push('<div class="ee-champ-impact__stat"><span>' + ent.label + '</span><strong>' + ent.count + '</strong></div>');
            });
            cards.push('<div class="ee-champ-impact__stat ee-champ-impact__stat--warn"><span>Produits avec emplacement</span><strong>' + (data.produits_lies || 0) + '</strong></div>');
            statsEl.innerHTML = cards.join('');
        }
        if (warningsEl) {
            warningsEl.innerHTML = '';
            (data.avertissements || []).forEach(function (msg) {
                var li = document.createElement('li');
                li.textContent = msg;
                warningsEl.appendChild(li);
            });
        }
    }

    window.eeOpenDeleteHierarchie = function (btn) {
        if (!btn) {
            return;
        }
        var raw = btn.getAttribute('data-ee-delete-impact') || '{}';
        var data = {};
        try {
            data = JSON.parse(raw);
        } catch (e) {
            data = {};
        }
        if (!data || !data.label) {
            return;
        }

        var form = document.getElementById('ee_form_delete_hierarchie');
        var csrfEl = document.getElementById('ee_delete_h_csrf');
        var confirmEl = document.getElementById('ee_delete_h_confirm');
        var checkEl = document.getElementById('ee_delete_h_impact_check');
        var submitBtn = document.getElementById('ee_delete_h_submit');
        var supprEntite = document.getElementById('ee_delete_h_supprimer_entite');
        var supprNiveau = document.getElementById('ee_delete_h_supprimer_niveau');
        var tableEl = document.getElementById('ee_delete_h_table');
        var idEl = document.getElementById('ee_delete_h_id');
        var etageEl = document.getElementById('ee_delete_h_etage_id');
        var numNiveauEl = document.getElementById('ee_delete_h_numero_niveau');
        var numEtageEl = document.getElementById('ee_delete_h_numero_etage');

        if (csrfEl && btn.getAttribute('data-ee-delete-csrf')) {
            csrfEl.value = btn.getAttribute('data-ee-delete-csrf');
        }
        if (confirmEl) {
            confirmEl.value = '';
        }
        if (checkEl) {
            checkEl.checked = false;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        if (data.mode === 'niveau') {
            if (supprEntite) {
                supprEntite.value = '';
            }
            if (supprNiveau) {
                supprNiveau.value = '1';
            }
            if (tableEl) {
                tableEl.value = '';
            }
            if (idEl) {
                idEl.value = '';
            }
            if (etageEl) {
                etageEl.value = String(data.etage_id || '');
            }
            if (numEtageEl) {
                numEtageEl.value = String(data.numero_etage || btn.getAttribute('data-ee-delete-niveau-num') || '');
            }
            if (numNiveauEl) {
                numNiveauEl.value = String(data.numero_etage || '');
            }
        } else {
            if (supprEntite) {
                supprEntite.value = '1';
            }
            if (supprNiveau) {
                supprNiveau.value = '';
            }
            if (tableEl) {
                tableEl.value = btn.getAttribute('data-ee-delete-table') || data.table || '';
            }
            if (idEl) {
                idEl.value = btn.getAttribute('data-ee-delete-id') || String(data.id || '');
            }
            if (etageEl) {
                etageEl.value = btn.getAttribute('data-ee-delete-etage') || String(data.etage_id || '');
            }
            if (numEtageEl) {
                numEtageEl.value = '';
            }
            if (numNiveauEl) {
                var nv = btn.getAttribute('data-ee-delete-niveau');
                if (nv) {
                    numNiveauEl.value = nv;
                }
            }
        }

        eeRenderDeleteImpact(data);

        if (typeof window.openModal === 'function') {
            window.openModal('modalSupprimerHierarchie');
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('ee_form_delete_hierarchie');
        var checkEl = document.getElementById('ee_delete_h_impact_check');
        var confirmEl = document.getElementById('ee_delete_h_confirm');
        var submitBtn = document.getElementById('ee_delete_h_submit');

        if (checkEl) {
            checkEl.addEventListener('change', function () {
                var ok = checkEl.checked;
                if (submitBtn) {
                    submitBtn.disabled = !ok;
                }
                if (confirmEl) {
                    confirmEl.value = ok ? '1' : '';
                }
            });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                if (!checkEl || !checkEl.checked) {
                    e.preventDefault();
                    window.alert('Veuillez lire l’impact et cocher la case de confirmation.');
                    return;
                }
                var titleEl = document.getElementById('ee_delete_h_impact_title');
                var label = titleEl ? titleEl.textContent.replace(/^Impact — /, '') : 'cet élément';
                var statsEl = document.getElementById('ee_delete_h_impact_stats');
                var prod = '0';
                if (statsEl) {
                    var warn = statsEl.querySelector('.ee-champ-impact__stat--warn strong');
                    if (warn) {
                        prod = warn.textContent;
                    }
                }
                var msg = 'Confirmer la suppression de ' + label + ' ?';
                if (parseInt(prod, 10) > 0) {
                    msg += '\n\n' + prod + ' produit(s) perdront leur emplacement assigné.';
                }
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
            });
        }
    });
})();
