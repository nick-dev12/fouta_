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
})();
