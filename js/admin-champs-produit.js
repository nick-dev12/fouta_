(function () {
    'use strict';

    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    window.cpOpenModal = openModal;
    window.cpCloseModal = closeModal;

    var typeSelect = document.getElementById('type_champ');
    var optionsWrap = document.getElementById('cp_options_wrap');
    if (typeSelect && optionsWrap) {
        function syncOptions() {
            optionsWrap.hidden = typeSelect.value !== 'select';
        }
        typeSelect.addEventListener('change', syncOptions);
        syncOptions();
    }

    var impactMap = window.CP_CHAMPS_IMPACT || {};
    var retraitModalId = 'modalConfirmerRetraitChamp';
    var retraitChampId = document.getElementById('cp_retrait_champ_id');
    var retraitIntro = document.getElementById('cp_retrait_intro');
    var retraitWarnings = document.getElementById('cp_retrait_warnings');
    var retraitCheck = document.getElementById('cp_retrait_check');
    var retraitConfirmWrap = document.getElementById('cp_retrait_confirm_wrap');
    var retraitConfirmLabel = document.getElementById('cp_retrait_confirm_label');
    var confirmHidden = document.getElementById('cp_confirm_suppression_champ');
    var btnRetrait = document.getElementById('cp_btn_retrait_champ');
    var retraitTitleText = document.getElementById('cp_retrait_title_text');

    function resetRetraitModal() {
        if (retraitCheck) retraitCheck.checked = false;
        if (confirmHidden) confirmHidden.value = '';
        if (btnRetrait) {
            btnRetrait.disabled = true;
            btnRetrait.textContent = 'Retirer';
        }
        if (retraitWarnings) retraitWarnings.innerHTML = '';
        if (retraitIntro) retraitIntro.textContent = '';
        if (retraitConfirmWrap) retraitConfirmWrap.hidden = false;
        if (retraitTitleText) retraitTitleText.textContent = 'Confirmer le retrait';
    }

    function renderRetraitModal(id) {
        resetRetraitModal();
        if (!id || !impactMap[id]) {
            return;
        }
        var imp = impactMap[id];
        if (retraitChampId) retraitChampId.value = String(id);
        if (retraitIntro) {
            retraitIntro.textContent = 'Retrait du champ « ' + (imp.label || '') + ' ».';
        }
        if (retraitWarnings && imp.avertissements && imp.avertissements.length) {
            imp.avertissements.forEach(function (txt) {
                var li = document.createElement('li');
                li.textContent = txt;
                retraitWarnings.appendChild(li);
            });
        }
        var action = imp.action || 'supprimer';
        if (action === 'bloque') {
            if (retraitConfirmWrap) retraitConfirmWrap.hidden = true;
            if (btnRetrait) btnRetrait.disabled = true;
            if (retraitTitleText) retraitTitleText.textContent = 'Retrait impossible';
            return;
        }
        if (retraitTitleText) {
            retraitTitleText.textContent = action === 'desactiver'
                ? 'Retirer des formulaires'
                : 'Supprimer définitivement';
        }
        if (btnRetrait) {
            btnRetrait.textContent = action === 'desactiver' ? 'Retirer des formulaires' : 'Supprimer';
        }
        if (retraitConfirmLabel) {
            retraitConfirmLabel.textContent = action === 'desactiver'
                ? 'Je comprends que ce champ système sera désactivé (masqué des formulaires).'
                : 'Je comprends les conséquences et souhaite supprimer définitivement ce champ.';
        }
    }

    window.cpOpenDeleteChamp = function (id) {
        renderRetraitModal(id);
        openModal(retraitModalId);
    };

    if (retraitCheck && confirmHidden && btnRetrait) {
        retraitCheck.addEventListener('change', function () {
            var ok = retraitCheck.checked;
            confirmHidden.value = ok ? '1' : '';
            btnRetrait.disabled = !ok;
        });
    }

    var rolesMap = window.CP_CHAMPS_ROLES || {};
    var labelsMap = window.CP_CHAMPS_LABELS || {};
    var accesModalId = 'modalAccesChampProduit';
    var accesChampId = document.getElementById('cp_acces_champ_id');
    var accesIntro = document.getElementById('cp_acces_intro');
    var accesRolesGrid = document.getElementById('cp_acces_roles_grid');

    window.cpOpenAccesChamp = function (id) {
        if (!id) {
            return;
        }
        if (accesChampId) {
            accesChampId.value = String(id);
        }
        if (accesIntro) {
            accesIntro.textContent = 'Pour le champ « ' + (labelsMap[id] || '')
                + ' », dites qui le voit et qui peut l’écrire. « Voir seulement » affiche la valeur en lecture seule : '
                + 'le formulaire la grise et l’enregistrement la refuse.';
        }
        /* VOIR N'EST PAS MODIFIER (31/08) : chaque type de compte a trois
           états — aucun accès, voir, voir et modifier. Sans restriction
           enregistrée, tout le monde peut voir ET modifier, comme avant. */
        var roles = rolesMap[id] || [];
        var niveaux = (window.CP_CHAMPS_NIVEAUX || {})[id] || {};
        var sansRestriction = roles.length === 0;
        if (accesRolesGrid) {
            accesRolesGrid.querySelectorAll('select[data-role]').forEach(function (sel) {
                var role = sel.getAttribute('data-role');
                var aLeDroit = sansRestriction || roles.indexOf(role) !== -1;
                sel.value = aLeDroit ? (niveaux[role] === 'voir' ? 'voir' : 'modifier') : '';
            });
        }
        openModal(accesModalId);
    };
})();
