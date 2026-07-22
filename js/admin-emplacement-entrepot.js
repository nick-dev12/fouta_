/**
 * Paramètres — ajout d’un niveau entrepôt (un enregistrement à la fois).
 */
(function () {
    'use strict';

    var modal = document.getElementById('modalEntrepotEmplacement');
    if (!modal) {
        return;
    }

    var form = document.getElementById('formEntrepotEmplacement');
    var nomInput = document.getElementById('ee_nom_niveau');

    window.openModalEntrepotEmplacement = function () {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (nomInput && nomInput.value === '') {
            nomInput.focus();
        }
    };

    window.closeModalEntrepotEmplacement = function () {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    if (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('.ee-modal__submit');
            if (btn) {
                btn.disabled = true;
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            window.closeModalEntrepotEmplacement();
        }
    });
})();
