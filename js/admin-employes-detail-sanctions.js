/**
 * Fiche employé — modal sanctions / discipline (ouverture / fermeture uniquement).
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('erSanctionModal');
    var btnOpen = document.getElementById('erSanctionOpenBtn');
    var btnClose = document.getElementById('erSanctionModalClose');
    var btnCancel = document.getElementById('erSanctionModalCancel');
    var backdrop = document.getElementById('erSanctionModalBackdrop');

    if (!modal) {
      return;
    }

    function openModal() {
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('er-sanction-modal-open');
      var first = document.getElementById('sanction_date_constat');
      if (first) {
        setTimeout(function () {
          first.focus();
        }, 80);
      }
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('er-sanction-modal-open');
    }

    if (btnOpen) {
      btnOpen.addEventListener('click', function () {
        openModal();
      });
    }

    if (btnClose) {
      btnClose.addEventListener('click', closeModal);
    }
    if (btnCancel) {
      btnCancel.addEventListener('click', closeModal);
    }
    if (backdrop) {
      backdrop.addEventListener('click', closeModal);
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });

    if (modal.classList.contains('is-open')) {
      document.body.classList.add('er-sanction-modal-open');
    }
  });
})();
