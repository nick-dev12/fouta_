/**
 * Formulaire produit — emplacement entrepôt (UI uniquement).
 * Choix indépendants : étage → rayon / allée / zone / barre / position (noms en base).
 */
(function () {
    'use strict';

    var form = document.getElementById('pm-emplacement-form');
    if (!form) {
        return;
    }

    if (form.getAttribute('data-mode') === 'referentiel' || form.getAttribute('data-mode') === 'libre') {
        initReferentiel();
        return;
    }

    initLegacy();

    function initLegacy() {
        var limitesEl = document.getElementById('pm-emplacement-limites');
        var limites = {};
        if (limitesEl && limitesEl.textContent) {
            try {
                limites = JSON.parse(limitesEl.textContent);
            } catch (e) {
                limites = {};
            }
        }

        var prefixes = {
            numero_rayon: 'Rayon',
            allee: 'Allée',
            zone_emplacement: 'Zone',
            position_emplacement: 'Position',
            barre_rayon: 'Barre'
        };

        var childCols = ['numero_rayon', 'allee', 'zone_emplacement', 'position_emplacement', 'barre_rayon'];
        var etageSel = document.getElementById('etage');
        var enfantsWrap = document.getElementById('pm-emplacement-enfants');

        function rebuildSelect(col, max, keepValue) {
            var sel = document.querySelector('[data-emplacement-select="' + col + '"]');
            if (!sel) {
                return;
            }
            var current = keepValue ? sel.value : '';
            sel.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = '— Non renseigné —';
            sel.appendChild(empty);
            for (var i = 1; i <= max; i++) {
                var opt = document.createElement('option');
                opt.value = String(i);
                opt.textContent = (prefixes[col] || 'Valeur') + ' ' + i;
                if (keepValue && String(i) === current) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            }
            if (keepValue && current && parseInt(current, 10) > max) {
                sel.value = '';
            }
        }

        function clearChildSelects() {
            childCols.forEach(function (col) {
                var sel = document.querySelector('[data-emplacement-select="' + col + '"]');
                if (sel) {
                    sel.value = '';
                }
            });
        }

        function applyEtageLimits(keepValues) {
            var etage = etageSel ? parseInt(etageSel.value, 10) : 0;
            if (!etage || (!limites[String(etage)] && !limites[etage])) {
                if (enfantsWrap) {
                    enfantsWrap.hidden = true;
                }
                if (!keepValues) {
                    clearChildSelects();
                }
                return;
            }

            var L = limites[etage] || limites[String(etage)];
            if (enfantsWrap) {
                enfantsWrap.hidden = false;
            }
            rebuildSelect('numero_rayon', L.nb_rayons, keepValues);
            rebuildSelect('allee', L.nb_allees, keepValues);
            rebuildSelect('zone_emplacement', L.nb_zones, keepValues);
            rebuildSelect('position_emplacement', L.nb_positions, keepValues);
            rebuildSelect('barre_rayon', L.nb_barres, keepValues);
        }

        if (etageSel) {
            etageSel.addEventListener('change', function () {
                clearChildSelects();
                applyEtageLimits(false);
            });
            applyEtageLimits(true);
        }
    }

    function initLibreCascade(referentiel, selection, structure, etageSel, cascadeWrap, apercuWrap, apercuText) {
        function asArray(list) {
            if (!list) {
                return [];
            }
            if (Array.isArray(list)) {
                return list;
            }
            return Object.keys(list).map(function (k) { return list[k]; });
        }

        function fieldSelect(key) {
            return document.querySelector('[data-emplacement-ref-select="' + key + '"]');
        }

        function fillSelect(sel, items, selectedVal, emptyLabel, valueKey, labelFn) {
            if (!sel) {
                return;
            }
            var vKey = valueKey || 'id';
            var current = selectedVal != null && selectedVal !== '' ? String(selectedVal) : '';
            var found = false;
            sel.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = emptyLabel || '— Choisir —';
            sel.appendChild(empty);
            asArray(items).forEach(function (item) {
                if (!item || item[vKey] == null) {
                    return;
                }
                var opt = document.createElement('option');
                opt.value = String(item[vKey]);
                opt.textContent = labelFn
                    ? labelFn(item)
                    : (item.nom || ('#' + (item.numero != null ? item.numero : item[vKey])));
                if (current && String(item[vKey]) === current) {
                    opt.selected = true;
                    found = true;
                }
                sel.appendChild(opt);
            });
            if (current && !found) {
                sel.value = '';
            }
        }

        function selectedText(sel) {
            if (!sel || !sel.value) {
                return '';
            }
            var opt = sel.options[sel.selectedIndex];
            return opt ? String(opt.textContent || '').trim() : '';
        }

        var etages = asArray(referentiel.etages);
        var byNiveau = referentiel.noeuds_par_niveau || {};
        // Compat ancien format { numero_etage: { etage, racines } }
        if (etages.length === 0 && referentiel && typeof referentiel === 'object') {
            Object.keys(referentiel).forEach(function (key) {
                var block = referentiel[key];
                if (block && block.etage) {
                    etages.push({
                        id: block.etage.id,
                        numero_etage: block.etage.numero_etage,
                        nom: block.etage.nom || ('Niveau ' + block.etage.numero_etage),
                        code_abrege: block.etage.code_abrege || ''
                    });
                }
            });
        }

        var state = {};
        structure.forEach(function (field) {
            var key = field.key;
            if (field.type === 'etage') {
                state[key] = selection.ref_etage || selection.numero_etage || '';
            } else if (key === 'entrepot_noeud_id') {
                state[key] = selection.entrepot_noeud_id || '';
            } else {
                state[key] = selection[key] || selection['ref_niveau_' + (field.niveau_id || '')] || '';
            }
        });

        function etageIdFromNumero(num) {
            var n = parseInt(num, 10) || 0;
            if (!n) {
                return 0;
            }
            var found = 0;
            etages.forEach(function (et) {
                if (parseInt(et.numero_etage, 10) === n) {
                    found = parseInt(et.id, 10) || 0;
                }
            });
            return found;
        }

        function currentEtageId() {
            var etageField = null;
            structure.forEach(function (f) {
                if (f.type === 'etage') {
                    etageField = f;
                }
            });
            if (!etageField) {
                return 0;
            }
            return etageIdFromNumero(state[etageField.key]);
        }

        function updateApercu() {
            if (!apercuWrap || !apercuText) {
                return;
            }
            var parts = [];
            structure.forEach(function (field) {
                var sel = fieldSelect(field.key);
                var txt = selectedText(sel);
                if (txt && sel && sel.value) {
                    parts.push(txt);
                }
            });
            if (parts.length === 0) {
                apercuWrap.hidden = true;
                apercuText.textContent = '';
                return;
            }
            apercuText.textContent = parts.join(' · ');
            apercuWrap.hidden = false;
        }

        function parentContextForIndex(index) {
            var parentVal = 0;
            var etageFilter = currentEtageId();
            var cascadeOk = true;
            for (var j = 0; j < index; j++) {
                var prev = structure[j];
                var val = parseInt(state[prev.key], 10) || 0;
                if (!val) {
                    cascadeOk = false;
                    break;
                }
                if (prev.type === 'etage') {
                    etageFilter = etageIdFromNumero(val);
                    parentVal = 0;
                } else {
                    parentVal = val;
                }
            }
            return { parentVal: cascadeOk ? parentVal : -1, etageFilter: etageFilter, cascadeOk: cascadeOk };
        }

        function rebuildFrom(fromIndex, keep) {
            if (cascadeWrap) {
                cascadeWrap.hidden = false;
            }
            for (var i = fromIndex; i < structure.length; i++) {
                var field = structure[i];
                var sel = fieldSelect(field.key);
                if (!sel) {
                    continue;
                }
                var label = field.label || 'élément';
                var selected = keep ? (state[field.key] || '') : '';
                if (field.type === 'etage') {
                    fillSelect(
                        sel,
                        etages,
                        selected,
                        '— Choisir ' + label + ' —',
                        'numero_etage',
                        function (et) {
                            var code = et.code_abrege ? (' (' + et.code_abrege + ')') : '';
                            return (et.nom || ('#' + et.numero_etage)) + code;
                        }
                    );
                    state[field.key] = sel.value || '';
                    continue;
                }

                var ctx = parentContextForIndex(i);
                var niveauId = parseInt(field.niveau_id || field.champ_id, 10);
                var list = byNiveau[niveauId] || byNiveau[String(niveauId)] || [];
                var filtered = [];
                if (ctx.cascadeOk || i === 0) {
                    filtered = asArray(list).filter(function (n) {
                        if (ctx.parentVal < 0 && i > 0) {
                            return false;
                        }
                        if (ctx.etageFilter > 0 && parseInt(n.etage_id, 10) !== ctx.etageFilter) {
                            return false;
                        }
                        return (parseInt(n.parent_id, 10) || 0) === (ctx.parentVal < 0 ? 0 : ctx.parentVal);
                    });
                }
                var emptyLabel = (!ctx.cascadeOk && i > 0)
                    ? '— Choisissez d’abord le niveau précédent —'
                    : ('— Choisir ' + label + ' —');
                fillSelect(sel, filtered, selected, emptyLabel, 'id');
                state[field.key] = sel.value || '';
            }
            updateApercu();
        }

        function clearDownstream(fromIndex) {
            for (var i = fromIndex; i < structure.length; i++) {
                state[structure[i].key] = '';
                selection[structure[i].key] = '';
            }
        }

        rebuildFrom(0, true);

        structure.forEach(function (field, index) {
            var sel = fieldSelect(field.key);
            if (!sel) {
                return;
            }
            sel.addEventListener('change', function () {
                state[field.key] = sel.value;
                selection[field.key] = sel.value;
                if (field.type === 'etage') {
                    selection.numero_etage = sel.value;
                    selection.ref_etage = sel.value;
                }
                if (field.key === 'entrepot_noeud_id') {
                    selection.entrepot_noeud_id = sel.value;
                }
                clearDownstream(index + 1);
                rebuildFrom(index + 1, false);
                updateApercu();
            });
        });
    }

    function initReferentiel() {
        var refEl = document.getElementById('pm-emplacement-referentiel');
        var selEl = document.getElementById('pm-emplacement-selection');
        var structEl = document.getElementById('pm-emplacement-structure');
        var referentiel = {};
        var selection = {};
        var structure = [];

        if (refEl && refEl.textContent) {
            try {
                referentiel = JSON.parse(refEl.textContent);
            } catch (e) {
                referentiel = {};
            }
        }
        if (selEl && selEl.textContent) {
            try {
                selection = JSON.parse(selEl.textContent);
            } catch (e) {
                selection = {};
            }
        }
        if (structEl && structEl.textContent) {
            try {
                structure = JSON.parse(structEl.textContent);
            } catch (e) {
                structure = [];
            }
        }

        var etageSel = document.getElementById('ref_etage');
        var cascadeWrap = document.getElementById('pm-emplacement-cascade');
        var apercuWrap = document.getElementById('pm-emplacement-apercu');
        var apercuText = document.getElementById('pm-emplacement-apercu-text');
        var isLibreMode = form.getAttribute('data-mode') === 'libre'
            || !!(referentiel && referentiel.mode === 'libre')
            || (Object.keys(referentiel).some(function (k) {
                return referentiel[k] && referentiel[k].mode === 'libre';
            }));

        if (isLibreMode) {
            initLibreCascade(referentiel, selection, structure, etageSel, cascadeWrap, apercuWrap, apercuText);
            return;
        }

        var selKeyMap = {
            ref_zone: 'zone_id',
            ref_rayon: 'rayon_id',
            ref_etagere: 'etagere_id',
            ref_allee: 'allee_id',
            ref_barre: 'barre_id',
            entrepot_position_id: 'position_id'
        };

        function isHierarchieData(data) {
            return !!(data && Array.isArray(data.zones));
        }

        function findZone(data, zoneId) {
            var zid = parseInt(zoneId, 10);
            if (!data || zid <= 0) {
                return null;
            }
            var found = null;
            asArray(data.zones).forEach(function (z) {
                if (parseInt(z.id, 10) === zid) {
                    found = z;
                }
            });
            return found;
        }

        function findRayonInData(data, rayonId) {
            var rid = parseInt(rayonId, 10);
            if (!data || rid <= 0) {
                return null;
            }
            var found = null;
            asArray(data.zones).forEach(function (z) {
                asArray(z.rayons).forEach(function (r) {
                    if (parseInt(r.id, 10) === rid) {
                        found = r;
                    }
                });
            });
            return found;
        }

        function findEtagereInData(data, etagereId) {
            var eid = parseInt(etagereId, 10);
            if (!data || eid <= 0) {
                return null;
            }
            var found = null;
            asArray(data.zones).forEach(function (z) {
                asArray(z.rayons).forEach(function (r) {
                    asArray(r.etageres).forEach(function (e) {
                        if (parseInt(e.id, 10) === eid) {
                            found = e;
                        }
                    });
                });
            });
            return found;
        }

        function findBarreInData(data, barreId) {
            var bid = parseInt(barreId, 10);
            if (!data || bid <= 0) {
                return null;
            }
            var found = null;
            asArray(data.zones).forEach(function (z) {
                asArray(z.rayons).forEach(function (r) {
                    asArray(r.etageres).forEach(function (e) {
                        asArray(e.barres).forEach(function (b) {
                            if (parseInt(b.id, 10) === bid) {
                                found = b;
                            }
                        });
                    });
                });
            });
            return found;
        }

        function fieldSelect(key) {
            return document.querySelector('[data-emplacement-ref-select="' + key + '"]');
        }

        function selectionKeyForField(field) {
            if (field.type === 'custom') {
                return field.key;
            }
            return selKeyMap[field.key] || field.key;
        }

        function asArray(list) {
            if (!list) {
                return [];
            }
            if (Array.isArray(list)) {
                return list;
            }
            return Object.keys(list).map(function (k) { return list[k]; });
        }

        function fillSelect(sel, items, valueKey, labelFn, selectedVal, emptyLabel) {
            if (!sel) {
                return;
            }
            var current = selectedVal != null && selectedVal !== '' ? String(selectedVal) : '';
            var found = false;
            sel.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = emptyLabel || '— Choisir —';
            sel.appendChild(empty);
            asArray(items).forEach(function (item) {
                if (!item || item[valueKey] == null) {
                    return;
                }
                var opt = document.createElement('option');
                opt.value = String(item[valueKey]);
                opt.textContent = labelFn(item);
                if (current && String(item[valueKey]) === current) {
                    opt.selected = true;
                    found = true;
                }
                sel.appendChild(opt);
            });
            if (current && !found) {
                sel.value = '';
            }
        }

        function selectedText(sel) {
            if (!sel || !sel.value) {
                return '';
            }
            var opt = sel.options[sel.selectedIndex];
            return opt ? String(opt.textContent || '').trim() : '';
        }

        function getEtageData() {
            var n = etageSel ? parseInt(etageSel.value, 10) : 0;
            if (!n) {
                return null;
            }
            return referentiel[n] || referentiel[String(n)] || null;
        }

        function elementNomById(data, elementId) {
            var eid = parseInt(elementId, 10);
            if (!data || eid <= 0) {
                return '';
            }
            if (data.lie_barre && data.lie_barre.elements) {
                var foundLie = '';
                asArray(data.lie_barre.elements).forEach(function (el) {
                    if (parseInt(el.id, 10) === eid) {
                        foundLie = el.nom || ('#' + el.numero);
                    }
                });
                if (foundLie) {
                    return foundLie;
                }
            }
            if (data.champs_custom) {
                var keys = Object.keys(data.champs_custom);
                for (var i = 0; i < keys.length; i++) {
                    var block = data.champs_custom[keys[i]];
                    var found = '';
                    asArray(block.elements).forEach(function (el) {
                        if (parseInt(el.id, 10) === eid) {
                            found = el.nom || ('#' + el.numero);
                        }
                    });
                    if (found) {
                        return found;
                    }
                }
            }
            return '';
        }

        function updateApercu() {
            if (!apercuWrap || !apercuText) {
                return;
            }
            var parts = [];
            var etageTxt = selectedText(etageSel);
            if (etageTxt && etageSel && etageSel.value) {
                parts.push(etageTxt);
            }
            structure.forEach(function (field) {
                var sel = fieldSelect(field.key);
                var txt = selectedText(sel);
                if (txt && sel && sel.value) {
                    parts.push(txt);
                }
            });
            var data = getEtageData();
            var barreSel = fieldSelect('ref_barre');
            if (data && barreSel && barreSel.value) {
                var barreId = parseInt(barreSel.value, 10);
                asArray(data.barres).forEach(function (b) {
                    if (parseInt(b.id, 10) === barreId && b.champ_element_id) {
                        var elNom = elementNomById(data, b.champ_element_id);
                        if (elNom) {
                            parts.push(elNom);
                        }
                    }
                });
            }
            if (parts.length === 0) {
                apercuWrap.hidden = true;
                apercuText.textContent = '';
                return;
            }
            apercuText.textContent = parts.join(' · ');
            apercuWrap.hidden = false;
        }

        function rayonNom(data, rayonId) {
            var rid = parseInt(rayonId, 10);
            if (!data || rid <= 0) {
                return '';
            }
            var found = '';
            asArray(data.rayons).forEach(function (r) {
                if (parseInt(r.id, 10) === rid) {
                    found = r.nom || ('Rayon ' + r.numero);
                }
            });
            return found;
        }

        function barresPourSelect(data) {
            var barres = asArray(data ? data.barres : []);
            var rayonSel = fieldSelect('ref_rayon');
            var rayonId = rayonSel ? parseInt(rayonSel.value, 10) : 0;
            if (rayonId > 0) {
                barres = barres.filter(function (b) {
                    return parseInt(b.rayon_id, 10) === rayonId;
                });
            }
            return barres;
        }

        function barreLabel(data, b) {
            var nom = b.nom || ('Barre ' + b.numero);
            var rl = rayonNom(data, b.rayon_id);
            if (rl) {
                return rl + ' · ' + nom;
            }
            return nom;
        }

        function rebuildField(field, keep) {
            var data = getEtageData();
            var sel = fieldSelect(field.key);
            if (!sel) {
                return;
            }
            var selKey = selectionKeyForField(field);
            var selected = keep ? (selection[selKey] || '') : '';
            var hier = isHierarchieData(data);

            if (field.type === 'zones') {
                if (hier) {
                    fillSelect(sel, data.zones, 'id', function (z) {
                        return z.nom || ('Zone ' + z.numero);
                    }, selected, '— Choisir une zone —');
                } else {
                    fillSelect(sel, data ? data.zones : [], 'id', function (z) {
                        return z.nom || ('Zone ' + z.numero);
                    }, selected, '— Choisir une zone —');
                }
            } else if (field.type === 'rayons') {
                var zoneSel = fieldSelect('ref_zone');
                var zoneId = zoneSel ? parseInt(zoneSel.value, 10) : 0;
                var rayons = [];
                if (hier && zoneId > 0) {
                    var z = findZone(data, zoneId);
                    rayons = z ? asArray(z.rayons) : [];
                } else if (data) {
                    rayons = asArray(data.rayons);
                }
                fillSelect(sel, rayons, 'id', function (r) {
                    return r.nom || ('Rayon ' + r.numero);
                }, selected, zoneId > 0 || !hier ? '— Choisir un rayon —' : '— Choisissez d’abord une zone —');
            } else if (field.type === 'etageres') {
                var rayonSel = fieldSelect('ref_rayon');
                var rayonId = rayonSel ? parseInt(rayonSel.value, 10) : 0;
                var etageres = [];
                if (hier && rayonId > 0) {
                    var r = findRayonInData(data, rayonId);
                    etageres = r ? asArray(r.etageres) : [];
                }
                fillSelect(sel, etageres, 'id', function (e) {
                    return e.nom || ('Étagère ' + e.numero);
                }, selected, rayonId > 0 ? '— Choisir une étagère —' : '— Choisissez d’abord un rayon —');
            } else if (field.type === 'allees') {
                fillSelect(sel, data ? data.allees : [], 'id', function (a) {
                    return a.nom || ('Allée ' + a.numero);
                }, selected, '— Choisir une allée —');
            } else if (field.type === 'barres') {
                var etagereSel = fieldSelect('ref_etagere');
                var etagereId = etagereSel ? parseInt(etagereSel.value, 10) : 0;
                var barres = [];
                if (hier && etagereId > 0) {
                    var et = findEtagereInData(data, etagereId);
                    barres = et ? asArray(et.barres) : [];
                } else if (!hier) {
                    barres = barresPourSelect(data);
                }
                fillSelect(sel, barres, 'id', function (b) {
                    return b.nom || ('Barre ' + b.numero);
                }, selected, (hier && etagereId > 0) || (!hier) ? '— Choisir une barre —' : '— Choisissez d’abord une étagère —');
            } else if (field.type === 'positions') {
                var barreSel = fieldSelect('ref_barre');
                var barreId = barreSel ? parseInt(barreSel.value, 10) : 0;
                var positions = [];
                if (hier && barreId > 0) {
                    var b = findBarreInData(data, barreId);
                    positions = b ? asArray(b.positions) : [];
                } else if (data && barreId > 0) {
                    asArray(data.barres).forEach(function (bb) {
                        if (parseInt(bb.id, 10) === barreId) {
                            positions = asArray(bb.positions);
                        }
                    });
                }
                fillSelect(sel, positions, 'id', function (p) {
                    return p.nom || ('Position ' + p.numero);
                }, selected, barreId > 0 ? '— Choisir une position —' : '— Choisissez d’abord une barre —');
            } else if (field.type === 'custom') {
                var elements = [];
                var cid = String(field.champ_id || '');
                if (data && data.champs_custom && data.champs_custom[cid]) {
                    elements = asArray(data.champs_custom[cid].elements);
                } else if (data && data.champs_custom && data.champs_custom[parseInt(cid, 10)]) {
                    elements = asArray(data.champs_custom[parseInt(cid, 10)].elements);
                }
                fillSelect(sel, elements, 'id', function (el) {
                    return el.nom || ('#' + el.numero);
                }, selected, '— Choisir —');
            }
        }

        function rebuildLists(keep) {
            var data = getEtageData();
            var hasEtage = !!data;
            if (cascadeWrap) {
                cascadeWrap.hidden = !hasEtage;
            }
            if (!hasEtage) {
                structure.forEach(function (field) {
                    rebuildField(field, false);
                });
                updateApercu();
                return;
            }
            structure.forEach(function (field) {
                rebuildField(field, keep);
            });
            updateApercu();
        }

        function rebuildEtages() {
            var items = [];
            Object.keys(referentiel).forEach(function (key) {
                var block = referentiel[key];
                if (block && block.etage) {
                    items.push({
                        numero: block.etage.numero_etage,
                        nom: block.etage.nom || ('Étage ' + block.etage.numero_etage)
                    });
                }
            });
            items.sort(function (a, b) {
                return parseInt(a.numero, 10) - parseInt(b.numero, 10);
            });
            fillSelect(
                etageSel,
                items,
                'numero',
                function (e) { return e.nom; },
                selection.numero_etage,
                '— Choisir un étage —'
            );
        }

        function clearDownstream(fromIndex) {
            for (var i = fromIndex; i < structure.length; i++) {
                var sk = selectionKeyForField(structure[i]);
                selection[sk] = '';
            }
        }

        function onEtageChange(clearDownstreamFlag) {
            if (clearDownstreamFlag) {
                clearDownstream(0);
            }
            rebuildLists(!clearDownstreamFlag);
        }

        rebuildEtages();
        if (selection.numero_etage) {
            onEtageChange(false);
        } else {
            onEtageChange(true);
        }

        if (etageSel) {
            etageSel.addEventListener('change', function () {
                onEtageChange(true);
            });
        }

        structure.forEach(function (field, index) {
            var sel = fieldSelect(field.key);
            if (!sel) {
                return;
            }
            sel.addEventListener('change', function () {
                var sk = selectionKeyForField(field);
                selection[sk] = sel.value;
                clearDownstream(index + 1);
                for (var j = index + 1; j < structure.length; j++) {
                    rebuildField(structure[j], false);
                }
                updateApercu();
            });
        });
    }
})();
