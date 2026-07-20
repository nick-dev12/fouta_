/**
 * Paramètres — configuration structure entrepôt (progressive UI).
 */
(function () {
    'use strict';

    var modal = document.getElementById('modalEntrepotEmplacement');
    if (!modal) {
        return;
    }

    var nbEtagesInput = document.getElementById('ee_nb_etages');
    var etageSelectWrap = document.getElementById('ee-etage-select-wrap');
    var etageSelect = document.getElementById('ee_etage_courant');
    var fieldsWrap = document.getElementById('ee-fields-wrap');
    var hiddenWrap = document.getElementById('ee-hidden-inputs');
    var store = {};

    var fieldKeys = ['nb_rayons', 'nb_allees', 'nb_zones', 'nb_positions', 'nb_barres'];

    function readInitialStore() {
        var script = document.getElementById('ee-initial-data');
        if (!script || !script.textContent) {
            return;
        }
        try {
            var data = JSON.parse(script.textContent);
            if (data.etages) {
                store = data.etages;
            }
            if (data.nb_etages && nbEtagesInput) {
                nbEtagesInput.value = data.nb_etages;
            }
        } catch (e) {
            store = {};
        }
    }

    function syncFieldsFromStore(num) {
        var row = store[String(num)] || store[num] || {};
        fieldKeys.forEach(function (key) {
            var inp = document.getElementById('ee_' + key);
            if (inp) {
                inp.value = row[key] !== undefined ? row[key] : '';
            }
        });
    }

    function saveCurrentEtageToStore() {
        if (!etageSelect || !etageSelect.value) {
            return;
        }
        var num = etageSelect.value;
        store[num] = {};
        fieldKeys.forEach(function (key) {
            var inp = document.getElementById('ee_' + key);
            store[num][key] = inp ? inp.value : '';
        });
    }

    function rebuildEtageOptions() {
        if (!nbEtagesInput || !etageSelect) {
            return;
        }
        var n = parseInt(nbEtagesInput.value, 10) || 0;
        etageSelect.innerHTML = '';
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '— Choisir un étage —';
        etageSelect.appendChild(empty);
        for (var i = 1; i <= n; i++) {
            var opt = document.createElement('option');
            opt.value = String(i);
            opt.textContent = 'Étage ' + i;
            etageSelect.appendChild(opt);
        }
    }

    function updateVisibility() {
        var n = nbEtagesInput ? parseInt(nbEtagesInput.value, 10) || 0 : 0;
        if (etageSelectWrap) {
            etageSelectWrap.hidden = n < 1;
        }
        if (fieldsWrap) {
            fieldsWrap.hidden = !etageSelect || !etageSelect.value;
        }
    }

    function buildHiddenInputs() {
        if (!hiddenWrap || !nbEtagesInput) {
            return;
        }
        saveCurrentEtageToStore();
        hiddenWrap.innerHTML = '';
        var n = parseInt(nbEtagesInput.value, 10) || 0;
        var nbHidden = document.createElement('input');
        nbHidden.type = 'hidden';
        nbHidden.name = 'nb_etages';
        nbHidden.value = String(n);
        hiddenWrap.appendChild(nbHidden);

        for (var i = 1; i <= n; i++) {
            var row = store[String(i)] || {};
            fieldKeys.forEach(function (key) {
                var h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'etages[' + i + '][' + key + ']';
                h.value = row[key] !== undefined ? row[key] : '';
                hiddenWrap.appendChild(h);
            });
        }
    }

    if (nbEtagesInput) {
        nbEtagesInput.addEventListener('input', function () {
            saveCurrentEtageToStore();
            rebuildEtageOptions();
            if (etageSelect) {
                etageSelect.value = '';
            }
            updateVisibility();
        });
        nbEtagesInput.addEventListener('change', function () {
            saveCurrentEtageToStore();
            rebuildEtageOptions();
            updateVisibility();
        });
    }

    if (etageSelect) {
        etageSelect.addEventListener('change', function () {
            saveCurrentEtageToStore();
            var num = etageSelect.value;
            if (num) {
                syncFieldsFromStore(num);
            }
            updateVisibility();
        });
    }

    var submitForm = document.getElementById('formEntrepotEmplacement');
    if (submitForm) {
        submitForm.addEventListener('submit', function () {
            buildHiddenInputs();
        });
    }

    window.openModalEntrepotEmplacement = function () {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        readInitialStore();
        rebuildEtageOptions();
        if (etageSelect && etageSelect.options.length > 1) {
            etageSelect.selectedIndex = 1;
            syncFieldsFromStore(etageSelect.value);
        }
        updateVisibility();
    };

    window.closeModalEntrepotEmplacement = function () {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    readInitialStore();
    rebuildEtageOptions();
    updateVisibility();

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            window.closeModalEntrepotEmplacement();
        }
    });
})();
