/**
 * Alertes stock — modal catégories / sous-catégories (UI uniquement)
 */
(function () {
    var allSc = window.__asSousCategories || [];
    var preselected = window.__asFormSousCategories || [];

    function getSelectedCategoryIds() {
        var ids = [];
        document.querySelectorAll('.as-categorie-cb:checked').forEach(function (cb) {
            ids.push(parseInt(cb.value, 10));
        });
        return ids;
    }

    function renderSousCategories() {
        var grid = document.getElementById('asSousCategoriesGrid');
        var btnAll = document.getElementById('asBtnSelectAllSc');
        if (!grid) return;

        var catIds = getSelectedCategoryIds();
        grid.innerHTML = '';

        if (catIds.length === 0) {
            var ph = document.createElement('p');
            ph.className = 'as-check-empty';
            ph.id = 'asSousCatPlaceholder';
            ph.textContent = 'Sélectionnez d\u2019abord une ou plusieurs catégories.';
            grid.appendChild(ph);
            if (btnAll) {
                btnAll.disabled = true;
                btnAll.innerHTML = '<i class="fas fa-check-double" aria-hidden="true"></i> Tout sélectionner';
            }
            return;
        }

        var filtered = allSc.filter(function (sc) {
            return catIds.indexOf(sc.categorie_id) !== -1;
        });

        if (filtered.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'as-check-empty';
            empty.textContent = 'Aucune sous-catégorie pour les catégories sélectionnées.';
            grid.appendChild(empty);
            if (btnAll) btnAll.disabled = true;
            return;
        }

        filtered.forEach(function (sc) {
            var label = document.createElement('label');
            label.className = 'as-check-item as-check-item--sc';
            label.setAttribute('data-categorie-id', String(sc.categorie_id));

            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'sous_categories[]';
            cb.value = String(sc.id);
            cb.className = 'as-sous-categorie-cb';
            if (preselected.indexOf(sc.id) !== -1) {
                cb.checked = true;
            }

            var span = document.createElement('span');
            span.innerHTML = escHtml(sc.nom) + ' <em>(' + escHtml(sc.categorie_nom) + ')</em>';

            label.appendChild(cb);
            label.appendChild(span);
            grid.appendChild(label);
        });

        if (btnAll) {
            btnAll.disabled = false;
            updateSelectAllLabel();
        }
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function getVisibleScCheckboxes() {
        return document.querySelectorAll('#asSousCategoriesGrid .as-sous-categorie-cb');
    }

    function updateSelectAllLabel() {
        var btnAll = document.getElementById('asBtnSelectAllSc');
        if (!btnAll) return;
        var cbs = getVisibleScCheckboxes();
        if (!cbs.length) {
            btnAll.disabled = true;
            return;
        }
        var allChecked = true;
        cbs.forEach(function (cb) {
            if (!cb.checked) allChecked = false;
        });
        btnAll.innerHTML = allChecked
            ? '<i class="fas fa-xmark" aria-hidden="true"></i> Tout désélectionner'
            : '<i class="fas fa-check-double" aria-hidden="true"></i> Tout sélectionner';
    }

    function toggleSelectAllSc() {
        var cbs = getVisibleScCheckboxes();
        if (!cbs.length) return;
        var allChecked = true;
        cbs.forEach(function (cb) {
            if (!cb.checked) allChecked = false;
        });
        cbs.forEach(function (cb) {
            cb.checked = !allChecked;
        });
        updateSelectAllLabel();
    }

    function onCategoryChange() {
        var catIds = getSelectedCategoryIds();
        document.querySelectorAll('#asSousCategoriesGrid .as-sous-categorie-cb:checked').forEach(function (cb) {
            var item = cb.closest('.as-check-item--sc');
            if (!item) return;
            var cid = parseInt(item.getAttribute('data-categorie-id'), 10);
            if (catIds.indexOf(cid) === -1) {
                cb.checked = false;
            }
        });
        preselected = [];
        document.querySelectorAll('#asSousCategoriesGrid .as-sous-categorie-cb:checked').forEach(function (cb) {
            preselected.push(parseInt(cb.value, 10));
        });
        renderSousCategories();
    }

    function activateTab(niveau) {
        if (!niveau) return;
        window.__asOngletActif = niveau;

        document.querySelectorAll('.as-tab').forEach(function (tab) {
            var active = tab.getAttribute('data-niveau') === niveau;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        document.querySelectorAll('.as-tab-panel').forEach(function (panel) {
            var active = panel.getAttribute('data-niveau') === niveau;
            panel.classList.toggle('is-active', active);
            if (active) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });

        var url = new URL(window.location.href);
        url.searchParams.set('niveau', niveau);
        window.history.replaceState({}, '', url.toString());
    }

    function initTabs() {
        document.querySelectorAll('.as-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                activateTab(tab.getAttribute('data-niveau'));
            });
        });
        if (window.__asOngletActif) {
            activateTab(window.__asOngletActif);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.as-categorie-cb').forEach(function (cb) {
            cb.addEventListener('change', onCategoryChange);
        });

        var btnAll = document.getElementById('asBtnSelectAllSc');
        if (btnAll) {
            btnAll.addEventListener('click', toggleSelectAllSc);
        }

        document.getElementById('asSousCategoriesGrid').addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('as-sous-categorie-cb')) {
                updateSelectAllLabel();
            }
        });

        renderSousCategories();
        initTabs();

        if (window.__asReopenModal) {
            openModalAlerteStock(window.__asOngletActif || '');
        }
    });

    window.openModalAlerteStock = function (niveau) {
        var el = document.getElementById('modalAlerteStock');
        if (!el) return;
        var sel = document.getElementById('niveau');
        if (sel && niveau && ['standard', 'moyen', 'haut'].indexOf(niveau) !== -1) {
            sel.value = niveau;
        }
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var inp = document.getElementById('seuil');
        window.setTimeout(function () {
            if (sel && (!sel.value || sel.value === '')) sel.focus();
            else if (inp) inp.focus();
        }, 200);
    };

    window.closeModalAlerteStock = function () {
        var el = document.getElementById('modalAlerteStock');
        if (!el) return;
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        var form = document.getElementById('formAlerteStock');
        if (form) {
            form.reset();
            preselected = [];
            renderSousCategories();
        }
    };

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var el = document.getElementById('modalAlerteStock');
        if (el && el.classList.contains('is-open')) closeModalAlerteStock();
    });
})();
