/**
 * Fiche employé — modal autorisations d’absence (affichage uniquement).
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('erAbsAuthModal');
    var btnOpen = document.getElementById('erAbsAuthOpenBtn');
    var btnClose = document.getElementById('erAbsAuthModalClose');
    var btnCancel = document.getElementById('erAbsAuthModalCancel');
    var backdrop = document.getElementById('erAbsAuthModalBackdrop');
    var subAuth = document.getElementById('er_abs_sub_auth');

    if (!modal) {
      return;
    }

    function openModal() {
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('er-abs-auth-modal-open');
      if (subAuth) {
        subAuth.checked = true;
      }
      var first = document.getElementById('auth_date_debut');
      if (first) {
        setTimeout(function () {
          first.focus();
        }, 80);
      }
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('er-abs-auth-modal-open');
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
      document.body.classList.add('er-abs-auth-modal-open');
    }
  });
})();
