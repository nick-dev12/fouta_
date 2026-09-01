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

    var champsDataMap = window.CP_CHAMPS_DATA || {};
    var editModalId = 'modalModifierChampProduit';
    var editChampId = document.getElementById('cp_edit_champ_id');
    var editIntro = document.getElementById('cp_edit_intro');
    var editLabel = document.getElementById('cp_edit_label_champ');
    var editSectionWrap = document.getElementById('cp_edit_section_wrap');
    var editSection = document.getElementById('cp_edit_section_champ');
    var editCustomFields = document.getElementById('cp_edit_custom_fields');
    var editType = document.getElementById('cp_edit_type_champ');
    var editOptionsWrap = document.getElementById('cp_edit_options_wrap');
    var editOptions = document.getElementById('cp_edit_options_champ');
    var editObligatoire = document.getElementById('cp_edit_obligatoire_champ');
    var editRolesFieldset = document.getElementById('cp_edit_roles_fieldset');
    var editRolesGrid = document.getElementById('cp_edit_roles_grid');

    function syncEditOptionsVisibility() {
        if (!editOptionsWrap || !editType) {
            return;
        }
        editOptionsWrap.hidden = editType.value !== 'select';
    }

    if (editType) {
        editType.addEventListener('change', syncEditOptionsVisibility);
    }

    window.cpOpenEditChamp = function (id) {
        if (!id || !champsDataMap[id]) {
            return;
        }
        var data = champsDataMap[id];
        if (editChampId) {
            editChampId.value = String(id);
        }
        if (editIntro) {
            var kind = data.est_systeme ? 'champ système' : 'champ personnalisé';
            editIntro.textContent = 'Modification du ' + kind + ' « ' + (data.label || '') + ' ».';
        }
        if (editLabel) {
            editLabel.value = data.label || '';
        }
        if (editSection) {
            editSection.value = data.section || 'info';
        }
        var verrou = !!data.verrouille;
        var estSys = !!data.est_systeme;
        var customOnly = !estSys && !verrou;

        if (editSectionWrap) {
            editSectionWrap.hidden = verrou;
        }
        if (editCustomFields) {
            editCustomFields.hidden = !customOnly;
        }
        if (customOnly) {
            if (editType) {
                editType.value = data.type_champ || 'texte';
            }
            if (editOptions) {
                editOptions.value = data.options_text || '';
            }
            if (editObligatoire) {
                editObligatoire.checked = !!data.obligatoire;
            }
            syncEditOptionsVisibility();
        }
        var roles = rolesMap[id] || [];
        var allRoles = roles.length === 0;
        if (editRolesGrid) {
            editRolesGrid.querySelectorAll('input[type="checkbox"][data-role]').forEach(function (cb) {
                var role = cb.getAttribute('data-role');
                cb.checked = allRoles || roles.indexOf(role) !== -1;
            });
        }
        openModal(editModalId);
    };

    window.cpOpenAccesChamp = function (id) {
        if (!id) {
            return;
        }
        if (accesChampId) {
            accesChampId.value = String(id);
        }
        if (accesIntro) {
            accesIntro.textContent = 'Définissez quels types de compte admin peuvent voir le champ « '
                + (labelsMap[id] || '') + ' » et ses données dans tout l’espace admin.';
        }
        var roles = rolesMap[id] || [];
        var allRoles = roles.length === 0;
        if (accesRolesGrid) {
            accesRolesGrid.querySelectorAll('input[type="checkbox"][data-role]').forEach(function (cb) {
                var role = cb.getAttribute('data-role');
                cb.checked = allRoles || roles.indexOf(role) !== -1;
            });
        }
        openModal(accesModalId);
    };
})();
