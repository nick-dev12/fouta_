/**
 * Paramètres — emplacement entrepôt (hiérarchie CRUD par onglets).
 */
(function () {
    'use strict';

    function lockBody(open) {
        document.body.style.overflow = open ? 'hidden' : '';
    }

    function openModal(id) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        lockBody(true);
    }

    function closeModal(id) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        var anyOpen = document.querySelector('.ee-modal.is-open');
        lockBody(!!anyOpen);
    }

    window.openModal = openModal;
    window.closeModal = closeModal;

    window.openModalEntrepotEmplacement = function () { openModal('modalNiveau'); };
    window.closeModalEntrepotEmplacement = function () { closeModal('modalNiveau'); };
    window.openModalAjouterChamp = function () { openModal('modalAjouterChamp'); };
    window.closeModalAjouterChamp = function () { closeModal('modalAjouterChamp'); };
    window.openModalSupprimerChamp = function () { openModal('modalSupprimerChamp'); };
    window.closeModalSupprimerChamp = function () { closeModal('modalSupprimerChamp'); };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.ee-modal.is-open').forEach(function (m) {
                closeModal(m);
            });
        }
    });

    /**
     * Soumission cascade GET : vide les niveaux enfants lors d’un changement parent.
     */
    window.eeCascadeResetAndSubmit = function (form, mode, from) {
        if (!form) {
            return;
        }
        var zone = form.querySelector('[name="c_zone"]');
        var rayon = form.querySelector('[name="c_rayon"]');
        var etagere = form.querySelector('[name="c_etagere"]');
        if (from === 'zone') {
            if (rayon) {
                rayon.removeAttribute('name');
            }
            if (etagere) {
                etagere.removeAttribute('name');
            }
        } else if (from === 'rayon') {
            if (etagere) {
                etagere.removeAttribute('name');
            }
        } else {
            if (zone) {
                zone.removeAttribute('name');
            }
            if (rayon) {
                rayon.removeAttribute('name');
            }
            if (etagere) {
                etagere.removeAttribute('name');
            }
        }
        form.submit();
    };

    function eeInitSupprimerChampModal() {
        var form = document.getElementById('ee_form_supprimer_champ');
        var select = document.getElementById('champ_id');
        var impactBox = document.getElementById('ee_champ_impact');
        var impactTitle = document.getElementById('ee_champ_impact_title');
        var impactIntro = document.getElementById('ee_champ_impact_intro');
        var impactStats = document.getElementById('ee_champ_impact_stats');
        var impactWarnings = document.getElementById('ee_champ_impact_warnings');
        var impactCheck = document.getElementById('ee_champ_impact_check');
        var confirmField = document.getElementById('ee_confirm_suppression_champ');
        var submitBtn = document.getElementById('ee_btn_supprimer_champ');
        var impactData = window.EE_CHAMPS_IMPACT || {};

        if (!form || !select || !impactBox) {
            return;
        }

        function resetImpact() {
            impactBox.hidden = true;
            if (impactCheck) {
                impactCheck.checked = false;
            }
            if (confirmField) {
                confirmField.value = '';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            if (impactStats) {
                impactStats.innerHTML = '';
            }
            if (impactWarnings) {
                impactWarnings.innerHTML = '';
            }
        }

        function renderImpact(id) {
            resetImpact();
            if (!id || !impactData[id]) {
                return;
            }
            var data = impactData[id];
            impactBox.hidden = false;
            if (impactTitle) {
                impactTitle.textContent = 'Impact — « ' + (data.label || '') + ' »';
            }
            if (impactIntro) {
                var intro = 'Vous êtes sur le point de supprimer le champ structurel « ' + (data.label || '') + ' »';
                if (data.colonne_db) {
                    intro += ' (colonne ' + data.colonne_db + ')';
                }
                intro += '.';
                impactIntro.textContent = intro;
            }
            if (impactStats) {
                var cards = [];
                if (data.niveau_label) {
                    cards.push('<div class="ee-champ-impact__stat"><span>Niveau hiérarchique</span><strong>' + data.niveau_label + '</strong></div>');
                }
                (data.entites || []).forEach(function (ent) {
                    cards.push('<div class="ee-champ-impact__stat"><span>' + ent.label + '</span><strong>' + ent.count + '</strong></div>');
                });
                if ((data.elements_champ || 0) > 0) {
                    cards.push('<div class="ee-champ-impact__stat"><span>Éléments nommés</span><strong>' + data.elements_champ + '</strong></div>');
                }
                if ((data.barres_liees || 0) > 0) {
                    cards.push('<div class="ee-champ-impact__stat"><span>Barres liées</span><strong>' + data.barres_liees + '</strong></div>');
                }
                cards.push('<div class="ee-champ-impact__stat ee-champ-impact__stat--warn"><span>Produits avec emplacement</span><strong>' + (data.produits_lies || 0) + '</strong></div>');
                impactStats.innerHTML = cards.join('');
            }
            if (impactWarnings) {
                impactWarnings.innerHTML = '';
                (data.avertissements || []).forEach(function (msg) {
                    var li = document.createElement('li');
                    li.textContent = msg;
                    impactWarnings.appendChild(li);
                });
            }
        }

        select.addEventListener('change', function () {
            renderImpact(select.value);
        });

        if (impactCheck) {
            impactCheck.addEventListener('change', function () {
                var ok = impactCheck.checked && select.value !== '';
                if (submitBtn) {
                    submitBtn.disabled = !ok;
                }
                if (confirmField) {
                    confirmField.value = ok ? '1' : '';
                }
            });
        }

        form.addEventListener('submit', function (e) {
            if (!select.value || !impactCheck || !impactCheck.checked) {
                e.preventDefault();
                window.alert('Veuillez sélectionner un champ, lire l’impact et cocher la case de confirmation.');
                return;
            }
            var data = impactData[select.value] || {};
            var msg = 'Confirmer la suppression du champ « ' + (data.label || '') + ' » ?';
            if ((data.produits_lies || 0) > 0) {
                msg += '\n\n' + data.produits_lies + ' produit(s) perdront leur emplacement assigné.';
            }
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', eeInitSupprimerChampModal);
})();
