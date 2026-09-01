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

    function clonePickTable(sourceTable, filterFn, onRowClone) {
        if (!sourceTable) {
            return null;
        }
        var wrap = document.createElement('div');
        wrap.className = 'ee-table-scroll';
        var table = sourceTable.cloneNode(true);
        var tbody = table.querySelector('tbody');
        if (!tbody) {
            return null;
        }
        var sourceRows = sourceTable.querySelectorAll('tbody tr[data-ee-item]');
        tbody.innerHTML = '';
        var visible = 0;
        sourceRows.forEach(function (srcRow) {
            if (!filterFn(srcRow)) {
                return;
            }
            var clone = srcRow.cloneNode(true);
            clone.removeAttribute('hidden');
            clone.classList.remove('is-selected');
            clone.setAttribute('aria-selected', 'false');
            tbody.appendChild(clone);
            if (typeof onRowClone === 'function') {
                onRowClone(clone, srcRow);
            }
            visible++;
        });
        if (visible === 0) {
            return null;
        }
        wrap.appendChild(table);
        return wrap;
    }

    document.querySelectorAll('[data-ee-hierarchie-root]').forEach(function (root) {
        var zoneRows = root.querySelectorAll('[data-ee-item="zone"]');
        var rayonRows = root.querySelectorAll('[data-ee-item="rayon"]');
        var rayonsSection = root.querySelector('[data-ee-rayons-section]');
        var rayonsTable = rayonsSection ? rayonsSection.querySelector('table') : null;
        var drillRoot = root.querySelector('[data-ee-drill]');
        var panels = {
            etageres: root.querySelector('[data-ee-level="etageres"]'),
            barres: root.querySelector('[data-ee-level="barres"]'),
            positions: root.querySelector('[data-ee-level="positions"]')
        };
        var etagereRows = root.querySelectorAll('[data-ee-item="etagere"]');
        var barreRows = root.querySelectorAll('[data-ee-item="barre"]');
        var barreDetails = root.querySelectorAll('[data-ee-barre-detail]');

        var modalTitle = document.querySelector('[data-ee-drill-modal-title-text]');
        var modalSubtitle = document.querySelector('[data-ee-drill-modal-subtitle]');
        var modalIcon = document.querySelector('[data-ee-drill-modal-icon]');
        var modalBody = document.querySelector('[data-ee-drill-modal-body]');
        var modalBack = document.querySelector('[data-ee-drill-modal-back]');

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

        var drillHistory = [];

        var state = {
            zoneId: null,
            rayonId: null,
            etagereId: null,
            barreId: null
        };

        function matchesZoneRow(row) {
            return row.getAttribute('data-ee-zone') === String(state.zoneId)
                || row.getAttribute('data-ee-id') === String(state.zoneId);
        }

        function matchesRayonRow(row) {
            return row.getAttribute('data-ee-zone') === String(state.zoneId)
                && row.getAttribute('data-ee-rayon') === String(state.rayonId);
        }

        function matchesEtagereRow(row) {
            return matchesRayonRow(row) && row.getAttribute('data-ee-etagere') === String(state.etagereId);
        }

        function firstPanelAfterRayon() {
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

        function openDrillModal() {
            if (typeof window.openModal === 'function') {
                window.openModal('modalEeDrillNav');
            }
        }

        function setModalHeader(view) {
            if (modalTitle) {
                modalTitle.textContent = view.title || '';
            }
            if (modalSubtitle) {
                if (view.subtitle) {
                    modalSubtitle.textContent = view.subtitle;
                    modalSubtitle.hidden = false;
                } else {
                    modalSubtitle.textContent = '';
                    modalSubtitle.hidden = true;
                }
            }
            if (modalIcon) {
                modalIcon.className = 'fas ' + (view.icon || 'fa-sitemap');
            }
            if (modalBack) {
                modalBack.hidden = drillHistory.length <= 1;
            }
        }

        function renderCurrentView() {
            var view = drillHistory[drillHistory.length - 1];
            if (!view || !modalBody) {
                return;
            }
            setModalHeader(view);
            modalBody.innerHTML = '';
            var node = view.build();
            if (node) {
                modalBody.appendChild(node);
            } else {
                var empty = document.createElement('p');
                empty.className = 'ee-h-empty ee-h-empty--sm';
                empty.textContent = view.emptyText || 'Aucun élément.';
                modalBody.appendChild(empty);
            }
        }

        function pushView(view) {
            drillHistory.push(view);
            renderCurrentView();
        }

        function popView() {
            if (drillHistory.length <= 1) {
                return;
            }
            drillHistory.pop();
            renderCurrentView();
        }

        function buildRayonsView(zoneLabel) {
            return {
                title: 'Rayons',
                subtitle: zoneLabel ? 'Zone : ' + zoneLabel : '',
                icon: 'fa-th-large',
                emptyText: 'Aucun rayon dans cette zone.',
                build: function () {
                    return clonePickTable(rayonsTable, function (row) {
                        return row.getAttribute('data-ee-zone') === String(state.zoneId);
                    }, function (clone) {
                        bindPickRow(clone, onRayonSelectInModal);
                    });
                }
            };
        }

        function buildEtageresView(rayonLabel) {
            var etagereTable = panels.etageres ? panels.etageres.querySelector('table') : null;
            return {
                title: 'Étagères',
                subtitle: rayonLabel || '',
                icon: 'fa-bars-staggered',
                emptyText: 'Aucune étagère pour ce rayon.',
                build: function () {
                    return clonePickTable(etagereTable, function (row) {
                        return matchesRayonRow(row);
                    }, function (clone) {
                        if (hasBarre || hasPosition) {
                            bindPickRow(clone, onEtagereSelectInModal);
                        }
                    });
                }
            };
        }

        function buildBarresView(contextLabel, forRayon) {
            var barreTable = panels.barres ? panels.barres.querySelector('table') : null;
            return {
                title: 'Barres',
                subtitle: contextLabel || '',
                icon: 'fa-grip-lines',
                emptyText: forRayon ? 'Aucune barre pour ce rayon.' : 'Aucune barre pour cette étagère.',
                build: function () {
                    return clonePickTable(barreTable, function (row) {
                        if (forRayon) {
                            return row.getAttribute('data-ee-zone') === String(state.zoneId)
                                && row.getAttribute('data-ee-rayon') === String(state.rayonId);
                        }
                        return matchesEtagereRow(row);
                    }, function (clone) {
                        if (hasPosition) {
                            bindPickRow(clone, onBarreSelectInModal);
                        }
                    });
                }
            };
        }

        function buildPositionsView(barreLabel) {
            return {
                title: 'Positions',
                subtitle: barreLabel || '',
                icon: 'fa-crosshairs',
                emptyText: 'Aucune position pour cette barre.',
                build: function () {
                    var match = null;
                    barreDetails.forEach(function (block) {
                        if (block.getAttribute('data-ee-barre-detail') === String(state.barreId)
                            && block.getAttribute('data-ee-zone') === String(state.zoneId)
                            && block.getAttribute('data-ee-rayon') === String(state.rayonId)
                            && block.getAttribute('data-ee-etagere') === String(state.etagereId)) {
                            match = block;
                        }
                    });
                    if (!match) {
                        return null;
                    }
                    var wrap = document.createElement('div');
                    wrap.appendChild(match.cloneNode(true));
                    return wrap;
                }
            };
        }

        function onRayonSelectInModal(row) {
            state.rayonId = row.getAttribute('data-ee-id');
            if (!hasZone) {
                state.zoneId = row.getAttribute('data-ee-zone');
            }
            state.etagereId = null;
            state.barreId = null;
            var panel = firstPanelAfterRayon();
            if (!panel) {
                return;
            }
            if (panel === 'etageres') {
                pushView(buildEtageresView(rowLabel(row)));
            } else if (panel === 'barres') {
                pushView(buildBarresView(rowLabel(row), true));
            } else if (panel === 'positions') {
                state.barreId = row.getAttribute('data-ee-id');
                pushView(buildPositionsView(rowLabel(row)));
            }
        }

        function onEtagereSelectInModal(row) {
            state.etagereId = row.getAttribute('data-ee-id');
            state.barreId = null;
            if (hasBarre) {
                pushView(buildBarresView(rowLabel(row), false));
            } else if (hasPosition) {
                pushView(buildPositionsView(rowLabel(row)));
            }
        }

        function onBarreSelectInModal(row) {
            state.barreId = row.getAttribute('data-ee-id');
            pushView(buildPositionsView(rowLabel(row)));
        }

        function onZoneSelect(row) {
            state.zoneId = row.getAttribute('data-ee-id');
            state.rayonId = null;
            state.etagereId = null;
            state.barreId = null;
            setSelectedRow(zoneRows, row);
            drillHistory = [];
            if (hasRayon) {
                pushView(buildRayonsView(rowLabel(row)));
                openDrillModal();
            }
        }

        function onRayonSelectTop(row) {
            state.rayonId = row.getAttribute('data-ee-id');
            state.zoneId = row.getAttribute('data-ee-zone');
            state.etagereId = null;
            state.barreId = null;
            setSelectedRow(rayonRows, row);
            drillHistory = [];
            var panel = firstPanelAfterRayon();
            if (!panel) {
                return;
            }
            if (panel === 'etageres') {
                pushView(buildEtageresView(rowLabel(row)));
            } else if (panel === 'barres') {
                pushView(buildBarresView(rowLabel(row), true));
            } else if (panel === 'positions') {
                state.barreId = row.getAttribute('data-ee-id');
                pushView(buildPositionsView(rowLabel(row)));
            }
            openDrillModal();
        }

        zoneRows.forEach(function (row) {
            if (hasZone) {
                bindPickRow(row, onZoneSelect);
            }
        });

        if (!hasZone && hasRayon) {
            rayonRows.forEach(function (row) {
                row.removeAttribute('hidden');
                bindPickRow(row, onRayonSelectTop);
            });
        }

        if (modalBack) {
            modalBack.addEventListener('click', popView);
        }

        if (drillRoot) {
            drillRoot.setAttribute('hidden', '');
        }
    });

    var EE_EDIT_META = {
        zone: {
            title: 'Modifier la zone',
            icon: 'fa-map-marker-alt',
            hint: 'Doublon du numéro uniquement parmi les zones de ce niveau (pas avec rayons, barres, etc.).'
        },
        rayon: {
            title: 'Modifier le rayon',
            icon: 'fa-th-large',
            hint: 'Doublon du numéro uniquement parmi les rayons de la même zone (les autres zones peuvent réutiliser ce numéro).'
        },
        etagere: {
            title: 'Modifier l’étagère',
            icon: 'fa-bars-staggered',
            hint: 'Doublon du numéro uniquement parmi les étagères du même rayon.'
        },
        barre: {
            title: 'Modifier la barre',
            icon: 'fa-grip-lines',
            hint: 'Doublon du numéro uniquement parmi les barres du même rayon.'
        },
        position: {
            title: 'Modifier la position',
            icon: 'fa-crosshairs',
            hint: 'Doublon du numéro uniquement parmi les positions de la même barre.'
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

    /* ---------- Hiérarchie libre : drill modal tableaux ---------- */
    function eeLibreParseJson(el) {
        if (!el || !el.textContent) {
            return null;
        }
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    function eeLibreEsc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function eeLibreAttr(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function eeLibreNextDef(defs, niveauId) {
        var found = false;
        var i;
        for (i = 0; i < defs.length; i++) {
            if (found) {
                return defs[i];
            }
            if (parseInt(defs[i].id, 10) === parseInt(niveauId, 10)) {
                found = true;
            }
        }
        return null;
    }

    function eeLibreDefById(defs, id) {
        var nid = parseInt(id, 10);
        var i;
        for (i = 0; i < defs.length; i++) {
            if (parseInt(defs[i].id, 10) === nid) {
                return defs[i];
            }
        }
        return null;
    }

    function eeLibreCountLabel(label, count) {
        return String(count) + '\u00a0' + (label || 'élément');
    }

    document.querySelectorAll('[data-ee-hierarchie-libre]').forEach(function (root) {
        var jsonEl = root.querySelector('script[type="application/json"]');
        var data = eeLibreParseJson(jsonEl);
        if (!data || !Array.isArray(data.racines) || data.racines.length === 0) {
            return;
        }
        var defs = Array.isArray(data.defs) ? data.defs : [];

        var modalTitle = document.querySelector('[data-ee-drill-modal-title-text]');
        var modalSubtitle = document.querySelector('[data-ee-drill-modal-subtitle]');
        var modalIcon = document.querySelector('[data-ee-drill-modal-icon]');
        var modalBody = document.querySelector('[data-ee-drill-modal-body]');
        var modalBack = document.querySelector('[data-ee-drill-modal-back]');

        var history = [];
        var pathLabels = [];

        function openDrillModal() {
            if (typeof window.openModal === 'function') {
                window.openModal('modalEeDrillNav');
            }
        }

        function setHeader(view) {
            if (modalTitle) {
                modalTitle.textContent = view.title || '';
            }
            if (modalSubtitle) {
                if (view.subtitle) {
                    modalSubtitle.textContent = view.subtitle;
                    modalSubtitle.hidden = false;
                } else {
                    modalSubtitle.textContent = '';
                    modalSubtitle.hidden = true;
                }
            }
            if (modalIcon) {
                modalIcon.className = 'fas ' + (view.icon || 'fa-sitemap');
            }
            if (modalBack) {
                modalBack.hidden = history.length <= 1;
            }
        }

        function renderView() {
            var view = history[history.length - 1];
            if (!view || !modalBody) {
                return;
            }
            setHeader(view);
            modalBody.innerHTML = '';
            var node = view.build();
            if (node) {
                modalBody.appendChild(node);
            } else {
                var empty = document.createElement('p');
                empty.className = 'ee-h-empty ee-h-empty--sm';
                empty.textContent = view.emptyText || 'Aucun élément.';
                modalBody.appendChild(empty);
            }
        }

        function pushView(view) {
            history.push(view);
            renderView();
            openDrillModal();
        }

        function popView() {
            if (history.length <= 1) {
                return;
            }
            history.pop();
            pathLabels.pop();
            renderView();
        }

        function eeEtiqDims() {
            var d = (data && data.etiquette_dims) || window.EE_ETIQ_DIMS || {};
            return {
                w: parseFloat(d.largeur_mm) > 0 ? parseFloat(d.largeur_mm) : 90,
                h: parseFloat(d.hauteur_mm) > 0 ? parseFloat(d.hauteur_mm) : 40,
                qr: parseFloat(d.qr_mm) > 0 ? parseFloat(d.qr_mm) : 30,
                texte: parseFloat(d.texte_mm) > 0 ? parseFloat(d.texte_mm) : 11,
                label: d.label || ('Étiquette ' + (parseFloat(d.largeur_mm) || 90) + '\u00d7' + (parseFloat(d.hauteur_mm) || 40) + ' mm')
            };
        }

        var etiqCache = {};
        var etiqPending = {};

        function fetchEtiquette(noeudId) {
            var id = parseInt(noeudId, 10) || 0;
            if (id <= 0) {
                return Promise.reject(new Error('invalid'));
            }
            if (etiqCache[id]) {
                return Promise.resolve(etiqCache[id]);
            }
            if (etiqPending[id]) {
                return etiqPending[id];
            }
            etiqPending[id] = fetch('ajax_entrepot_noeud_etiquette.php?id=' + encodeURIComponent(String(id)), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            })
                .then(function (res) {
                    return res.json().then(function (body) {
                        if (!res.ok || !body || !body.success || !body.etiquette) {
                            throw new Error((body && body.message) ? body.message : 'Chargement impossible');
                        }
                        etiqCache[id] = body.etiquette;
                        return body.etiquette;
                    });
                })
                .finally(function () {
                    delete etiqPending[id];
                });
            return etiqPending[id];
        }

        function buildEtiquetteLazySlot(noeudId, printKey, autoLoad) {
            var id = parseInt(noeudId, 10) || 0;
            var key = String(printKey || ('n' + id));
            var slot = document.createElement('div');
            slot.className = 'ee-libre-etiq-lazy';
            slot.setAttribute('data-ee-etiq-slot', String(id));
            slot.setAttribute('data-ee-etiq-print-key', key);
            slot.innerHTML = '<button type="button" class="ee-btn-secondary ee-libre-etiq-load-btn">'
                + '<i class="fas fa-qrcode" aria-hidden="true"></i> Afficher l\u2019étiquette</button>'
                + '<p class="ee-libre-etiq-lazy__hint">L\u2019aperçu est chargé à la demande pour accélérer la navigation.</p>';
            var btn = slot.querySelector('.ee-libre-etiq-load-btn');
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    loadEtiquetteIntoSlot(slot, id, key);
                });
            }
            if (autoLoad) {
                loadEtiquetteIntoSlot(slot, id, key);
            }
            return slot;
        }

        function loadEtiquetteIntoSlot(slot, noeudId, printKey) {
            if (!slot || slot.getAttribute('data-ee-etiq-loaded') === '1') {
                return;
            }
            var id = parseInt(noeudId, 10) || 0;
            var key = String(printKey || ('n' + id));
            slot.innerHTML = '<p class="ee-libre-etiq-lazy__loading"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Chargement de l\u2019étiquette…</p>';
            fetchEtiquette(id)
                .then(function (etiq) {
                    slot.setAttribute('data-ee-etiq-loaded', '1');
                    slot.classList.add('is-loaded');
                    slot.innerHTML = buildEtiquetteBlock(etiq, key);
                })
                .catch(function (err) {
                    slot.innerHTML = '<p class="ee-h-empty ee-h-empty--sm">'
                        + eeLibreEsc((err && err.message) ? err.message : 'Étiquette indisponible.')
                        + '</p><button type="button" class="ee-btn-secondary ee-libre-etiq-load-btn">Réessayer</button>';
                    var retry = slot.querySelector('.ee-libre-etiq-load-btn');
                    if (retry) {
                        retry.addEventListener('click', function (e) {
                            e.stopPropagation();
                            slot.removeAttribute('data-ee-etiq-loaded');
                            loadEtiquetteIntoSlot(slot, id, key);
                        });
                    }
                });
        }

        function buildEtiquetteBlock(etiq, printKey) {
            if (!etiq || !etiq.libelle) {
                return '';
            }
            var key = String(printKey || etiq.print_key || '');
            var dims = eeEtiqDims();
            var cssUrl = (window.location.origin || '') + '/css/entrepot-barre-etiquette.css';
            var qr = etiq.qr_url
                ? ('<img src="' + eeLibreAttr(etiq.qr_url) + '" width="96" height="96" alt="QR" class="ee-barre-etiq__qr">')
                : '';
            var pdf = etiq.pdf_url
                ? ('<a href="' + eeLibreAttr(etiq.pdf_url) + '" class="ee-barre-etiq-pdf-btn" target="_blank" rel="noopener">'
                    + '<i class="fas fa-file-pdf" aria-hidden="true"></i> PDF</a>')
                : '';
            return '<div class="ee-barre-etiq-block" id="ee-barre-etiq-root-' + eeLibreAttr(key) + '"'
                + ' data-css-url="' + eeLibreAttr(cssUrl) + '"'
                + ' data-etiq-w="' + dims.w + '" data-etiq-h="' + dims.h + '"'
                + ' data-etiq-qr="' + dims.qr + '" data-etiq-texte="' + dims.texte + '">'
                + '<p class="ee-barre-etiq-block__label">' + eeLibreEsc(dims.label) + '</p>'
                + '<div class="ee-barre-etiq-row">'
                + '<div class="ee-barre-etiq-preview-wrap"><div class="ee-barre-etiq-preview-scale">'
                + '<article class="ee-barre-etiq" data-barre-etiq>'
                + '<span class="ee-barre-etiq__text">' + eeLibreEsc(etiq.libelle) + '</span>'
                + '<div class="ee-barre-etiq__qr-box">' + qr + '</div>'
                + '</article></div></div>'
                + '<div class="ee-barre-etiq-actions">'
                + '<button type="button" class="ee-barre-etiq-print-btn" data-barre-print="' + eeLibreAttr(key) + '">'
                + '<i class="fas fa-print" aria-hidden="true"></i> Imprimer l\u2019étiquette</button>'
                + pdf
                + '</div></div></div>';
        }

        function isEtiquetteNiveau(niveauId) {
            var def = eeLibreDefById(defs, niveauId);
            if (def && parseInt(def.est_etiquette_qr, 10) === 1) {
                return true;
            }
            return parseInt(data.etiquette_niveau_id, 10) === parseInt(niveauId, 10)
                && parseInt(niveauId, 10) > 0;
        }

        function bindNodeActions(container, child) {
            var id = parseInt(child.id, 10) || 0;
            var nom = child.nom || '';
            var numero = parseInt(child.numero, 10) || 0;
            var editBtn = container.querySelector('[data-ee-libre-edit]');
            if (editBtn) {
                editBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (typeof window.eeOpenModifierNoeud === 'function') {
                        window.eeOpenModifierNoeud(id, nom, numero);
                    }
                });
            }
            var delBtn = container.querySelector('[data-ee-libre-del]');
            if (delBtn) {
                delBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (typeof window.eeOpenDeleteNoeudLibre === 'function') {
                        window.eeOpenDeleteNoeudLibre(delBtn);
                    }
                });
            }
        }

        function buildChildrenTable(parentNode, children) {
            var niveauId = children.length
                ? parseInt(children[0].niveau_id, 10)
                : 0;
            var def = eeLibreDefById(defs, niveauId);
            var nextDef = eeLibreNextDef(defs, niveauId);
            var label = (def && def.label) || 'Éléments';
            var childCol = (nextDef && nextDef.label) || 'Contenu';
            var canDrillLevel = !!nextDef;
            var showEtiq = isEtiquetteNiveau(niveauId);

            var wrap = document.createElement('div');
            wrap.className = showEtiq ? 'ee-libre-etiq-list' : 'ee-table-scroll';

            var sectionHead = document.createElement('header');
            sectionHead.className = 'ee-h-drill-panel__head ee-h-drill-panel__head--modal';
            sectionHead.innerHTML = '<h3><i class="fas '
                + eeLibreEsc((def && def.icon) || 'fa-cube')
                + '" aria-hidden="true"></i> '
                + eeLibreEsc(label)
                + '</h3>'
                + (showEtiq
                    ? '<p class="ee-h-drill-panel__hint">Cliquez sur un élément pour afficher son étiquette, ou ouvrez le niveau suivant.</p>'
                    : (canDrillLevel
                        ? '<p class="ee-h-drill-panel__hint">Cliquez une ligne pour ouvrir le niveau suivant.</p>'
                        : ''));
            wrap.appendChild(sectionHead);

            if (showEtiq) {
                children.forEach(function (child) {
                    var id = parseInt(child.id, 10) || 0;
                    var nom = child.nom || '';
                    var numero = parseInt(child.numero, 10) || 0;
                    var enfants = Array.isArray(child.enfants) ? child.enfants : [];
                    var nb = enfants.length;
                    var rowNext = eeLibreNextDef(defs, child.niveau_id);
                    var canDrill = !!rowNext;
                    var printKey = child.etiquette_print_key || ('n' + id);
                    var hasEtiq = !!child.has_etiquette || isEtiquetteNiveau(child.niveau_id);

                    var card = document.createElement('article');
                    card.className = 'ee-libre-etiq-card';
                    card.setAttribute('data-ee-libre-node', String(id));

                    var headHtml = '<div class="ee-libre-etiq-card__head">'
                        + '<div class="ee-libre-etiq-card__title">'
                        + '<strong>#' + numero + '</strong> '
                        + '<span class="ee-entity-name">' + eeLibreEsc(nom) + '</span>'
                        + '</div>'
                        + '<div class="ee-libre-node__actions">'
                        + '<button type="button" class="ee-btn-icon" title="Modifier" data-ee-libre-edit><i class="fas fa-pen"></i></button>'
                        + '<button type="button" class="ee-btn-icon ee-btn-icon--danger" title="Supprimer"'
                        + ' data-ee-noeud-id="' + id + '" data-ee-noeud-nom="' + eeLibreAttr(nom) + '" data-ee-libre-del>'
                        + '<i class="fas fa-trash-can"></i></button>'
                        + '</div></div>';

                    card.appendChild(document.createRange().createContextualFragment(headHtml));
                    if (hasEtiq) {
                        card.appendChild(buildEtiquetteLazySlot(id, printKey, false));
                    }
                    if (canDrill) {
                        var drillBtn = document.createElement('button');
                        drillBtn.type = 'button';
                        drillBtn.className = 'ee-libre-etiq-card__drill';
                        drillBtn.setAttribute('data-ee-libre-drill', '1');
                        drillBtn.innerHTML = '<span class="ee-badge ee-badge--muted">' + nb + '&nbsp;'
                            + eeLibreEsc((rowNext && rowNext.label) || 'éléments')
                            + '</span><span>Ouvrir <i class="fas fa-chevron-right" aria-hidden="true"></i></span>';
                        card.appendChild(drillBtn);
                    }

                    bindNodeActions(card, child);
                    var drillBtnEl = card.querySelector('[data-ee-libre-drill]');
                    if (drillBtnEl && canDrill) {
                        drillBtnEl.addEventListener('click', function (e) {
                            e.stopPropagation();
                            openNode(child);
                        });
                    }
                    wrap.appendChild(card);
                });
                return wrap;
            }

            var table = document.createElement('table');
            table.className = 'ee-table ee-table--hierarchie ee-h-pick-table';
            table.innerHTML = '<thead><tr>'
                + '<th scope="col">N°</th>'
                + '<th scope="col">Nom</th>'
                + '<th scope="col">' + eeLibreEsc(canDrillLevel ? childCol : 'Type') + '</th>'
                + '<th scope="col" class="ee-table__actions">Actions</th>'
                + '</tr></thead>';
            var tbody = document.createElement('tbody');

            children.forEach(function (child) {
                var id = parseInt(child.id, 10) || 0;
                var nom = child.nom || '';
                var numero = parseInt(child.numero, 10) || 0;
                var enfants = Array.isArray(child.enfants) ? child.enfants : [];
                var nb = enfants.length;
                var rowNext = eeLibreNextDef(defs, child.niveau_id);
                var canDrill = !!rowNext;
                var tr = document.createElement('tr');
                tr.className = 'ee-h-pick-row ee-h-pick-row--libre' + (canDrill ? ' is-drillable' : '');
                tr.setAttribute('data-ee-libre-node', String(id));
                if (canDrill) {
                    tr.setAttribute('tabindex', '0');
                    tr.setAttribute('role', 'button');
                    tr.setAttribute('aria-selected', 'false');
                }

                var countCell = canDrill
                    ? ('<span class="ee-h-count-cell"><span class="ee-badge ee-badge--muted">'
                        + eeLibreEsc(eeLibreCountLabel(rowNext.label, nb))
                        + '</span><i class="fas fa-chevron-right ee-h-pick-chevron" aria-hidden="true"></i></span>')
                    : '<span class="ee-badge ee-badge--leaf">Feuille</span>';

                tr.innerHTML = '<td class="ee-table-etage-num">#' + numero + '</td>'
                    + '<td><span class="ee-entity-name">' + eeLibreEsc(nom) + '</span></td>'
                    + '<td>' + countCell + '</td>'
                    + '<td class="ee-table__actions"><div class="ee-libre-node__actions">'
                    + '<button type="button" class="ee-btn-icon" title="Modifier" data-ee-libre-edit>'
                    + '<i class="fas fa-pen"></i></button>'
                    + '<button type="button" class="ee-btn-icon ee-btn-icon--danger" title="Supprimer"'
                    + ' data-ee-noeud-id="' + id + '"'
                    + ' data-ee-noeud-nom="' + eeLibreAttr(nom) + '"'
                    + ' data-ee-libre-del>'
                    + '<i class="fas fa-trash-can"></i></button>'
                    + '</div></td>';

                bindNodeActions(tr, child);

                if (canDrill) {
                    bindPickRow(tr, function () {
                        openNode(child);
                    });
                }

                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            wrap.appendChild(table);
            return wrap;
        }

        function openNode(node) {
            var enfants = Array.isArray(node.enfants) ? node.enfants : [];
            var next = eeLibreNextDef(defs, node.niveau_id);
            var parentIsEtiq = isEtiquetteNiveau(node.niveau_id);
            var noeudId = parseInt(node.id, 10) || 0;
            var printKey = node.etiquette_print_key || ('n' + noeudId);

            // Nœud étiquette : afficher l’étiquette + enfants (positions), même sans next si feuille
            if (parentIsEtiq) {
                var parentLabel = node.nom || '';
                pathLabels.push(parentLabel);
                var crumbE = pathLabels.join(' · ');
                var childDef = next || null;
                pushView({
                    title: parentLabel || ((eeLibreDefById(defs, node.niveau_id) || {}).label) || 'Élément',
                    subtitle: crumbE,
                    icon: 'fa-qrcode',
                    emptyText: childDef
                        ? ('Aucun élément « ' + (childDef.label || 'enfant') + ' » sous « ' + parentLabel + ' ».')
                        : 'Aucun sous-élément.',
                    build: function () {
                        var box = document.createElement('div');
                        box.className = 'ee-libre-etiq-detail';
                        box.appendChild(buildEtiquetteLazySlot(noeudId, printKey, true));
                        if (enfants.length && childDef) {
                            box.appendChild(buildChildrenTable(node, enfants));
                        } else if (childDef) {
                            var empty = document.createElement('p');
                            empty.className = 'ee-h-empty ee-h-empty--sm';
                            empty.textContent = 'Aucun élément « ' + (childDef.label || 'enfant') + ' » sous « ' + parentLabel + ' ».';
                            box.appendChild(empty);
                        }
                        return box;
                    }
                });
                return;
            }

            if (!next) {
                return;
            }
            var def = eeLibreDefById(defs, next.id);
            var parentLabel2 = node.nom || '';
            pathLabels.push(parentLabel2);
            var crumb = pathLabels.join(' · ');
            pushView({
                title: (def && def.label) || 'Éléments',
                subtitle: crumb,
                icon: (def && def.icon) || 'fa-sitemap',
                emptyText: 'Aucun élément « ' + ((def && def.label) || 'enfant') + ' » sous « ' + parentLabel2 + ' ».',
                build: function () {
                    if (!enfants.length) {
                        return null;
                    }
                    return buildChildrenTable(node, enfants);
                }
            });
        }

        function findNodeById(nodes, id) {
            var target = parseInt(id, 10);
            var i;
            for (i = 0; i < nodes.length; i++) {
                if (parseInt(nodes[i].id, 10) === target) {
                    return nodes[i];
                }
            }
            return null;
        }

        root.querySelectorAll('[data-ee-libre-node].is-drillable').forEach(function (row) {
            bindPickRow(row, function (r) {
                var id = r.getAttribute('data-ee-libre-node');
                var node = findNodeById(data.racines, id);
                if (!node) {
                    return;
                }
                history = [];
                pathLabels = [];
                openNode(node);
            });
        });

        if (modalBack && !modalBack.getAttribute('data-ee-libre-bound')) {
            modalBack.setAttribute('data-ee-libre-bound', '1');
            modalBack.addEventListener('click', function () {
                if (history.length > 1) {
                    popView();
                }
            });
        }
    });
})();
