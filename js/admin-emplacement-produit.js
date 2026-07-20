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

    if (form.getAttribute('data-mode') === 'referentiel') {
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

    function initReferentiel() {
        var refEl = document.getElementById('pm-emplacement-referentiel');
        var selEl = document.getElementById('pm-emplacement-selection');
        var referentiel = {};
        var selection = {};

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

        var etageSel = document.getElementById('ref_etage');
        var rayonSel = document.getElementById('ref_rayon');
        var alleeSel = document.getElementById('ref_allee');
        var zoneSel = document.getElementById('ref_zone');
        var barreSel = document.getElementById('ref_barre');
        var positionSel = document.getElementById('entrepot_position_id');
        var cascadeWrap = document.getElementById('pm-emplacement-cascade');
        var apercuWrap = document.getElementById('pm-emplacement-apercu');
        var apercuText = document.getElementById('pm-emplacement-apercu-text');

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

        function updateApercu() {
            if (!apercuWrap || !apercuText) {
                return;
            }
            var parts = [];
            var etageTxt = selectedText(etageSel);
            if (etageTxt && etageSel.value) {
                parts.push(etageTxt);
            }
            var rayonTxt = selectedText(rayonSel);
            if (rayonTxt && rayonSel.value) {
                parts.push(rayonTxt);
            }
            var alleeTxt = selectedText(alleeSel);
            if (alleeTxt && alleeSel.value) {
                parts.push(alleeTxt);
            }
            var zoneTxt = selectedText(zoneSel);
            if (zoneTxt && zoneSel.value) {
                parts.push(zoneTxt);
            }
            var barreTxt = selectedText(barreSel);
            if (barreTxt && barreSel.value) {
                parts.push(barreTxt);
            }
            var posTxt = selectedText(positionSel);
            if (posTxt && positionSel.value) {
                parts.push(posTxt);
            }
            if (parts.length === 0) {
                apercuWrap.hidden = true;
                apercuText.textContent = '';
                return;
            }
            apercuText.textContent = parts.join(' · ');
            apercuWrap.hidden = false;
        }

        function rebuildLists(keep) {
            var data = getEtageData();
            var hasEtage = !!data;
            if (cascadeWrap) {
                cascadeWrap.hidden = !hasEtage;
            }
            if (!hasEtage) {
                fillSelect(rayonSel, [], 'id', function () { return ''; }, '', '— Choisir —');
                fillSelect(alleeSel, [], 'id', function () { return ''; }, '', '— Choisir —');
                fillSelect(zoneSel, [], 'id', function () { return ''; }, '', '— Choisir —');
                fillSelect(barreSel, [], 'id', function () { return ''; }, '', '— Choisir —');
                fillSelect(positionSel, [], 'id', function () { return ''; }, '', '— Choisissez d’abord une barre —');
                updateApercu();
                return;
            }

            fillSelect(
                rayonSel,
                data.rayons,
                'id',
                function (r) { return r.nom || ('Rayon ' + r.numero); },
                keep ? selection.rayon_id : '',
                '— Choisir un rayon —'
            );
            fillSelect(
                alleeSel,
                data.allees,
                'id',
                function (a) { return a.nom || ('Allée ' + a.numero); },
                keep ? selection.allee_id : '',
                '— Choisir une allée —'
            );
            fillSelect(
                zoneSel,
                data.zones,
                'id',
                function (z) { return z.nom || ('Zone ' + z.numero); },
                keep ? selection.zone_id : '',
                '— Choisir une zone —'
            );
            // Toutes les barres de l’étage — noms seuls, sans liaison forcée
            fillSelect(
                barreSel,
                data.barres,
                'id',
                function (b) { return b.nom || ('Barre ' + b.numero); },
                keep ? selection.barre_id : '',
                '— Choisir une barre —'
            );
            rebuildPositions(keep);
            updateApercu();
        }

        function rebuildPositions(keep) {
            var data = getEtageData();
            var barreId = barreSel ? parseInt(barreSel.value, 10) : 0;
            var positions = [];
            if (data && barreId > 0) {
                asArray(data.barres).forEach(function (b) {
                    if (parseInt(b.id, 10) === barreId) {
                        positions = asArray(b.positions);
                    }
                });
            }
            fillSelect(
                positionSel,
                positions,
                'id',
                function (p) { return p.nom || ('Position ' + p.numero); },
                keep ? selection.position_id : '',
                barreId > 0 ? '— Choisir une position —' : '— Choisissez d’abord une barre —'
            );
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

        function onEtageChange(clearDownstream) {
            if (clearDownstream) {
                selection.rayon_id = '';
                selection.allee_id = '';
                selection.zone_id = '';
                selection.barre_id = '';
                selection.position_id = '';
            }
            rebuildLists(!clearDownstream);
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
        // Rayon / allée / zone : choix libre, met seulement à jour l’aperçu
        [rayonSel, alleeSel, zoneSel].forEach(function (sel) {
            if (sel) {
                sel.addEventListener('change', updateApercu);
            }
        });
        if (barreSel) {
            barreSel.addEventListener('change', function () {
                selection.position_id = '';
                rebuildPositions(false);
            });
        }
        if (positionSel) {
            positionSel.addEventListener('change', updateApercu);
        }
    }
})();
