/**
 * Fiche employé — modal congés + prévisualisation du solde annuel.
 */
(function () {
  'use strict';

  function parseMap(raw) {
    if (!raw) return {};
    try {
      var obj = JSON.parse(raw);
      return obj && typeof obj === 'object' ? obj : {};
    } catch (e) {
      return {};
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('erCongeModal');
    if (!modal) return;

    var openBtn = document.getElementById('erCongeOpenBtn');
    var closeBtn = document.getElementById('erCongeModalClose');
    var cancelBtn = document.getElementById('erCongeModalCancel');
    var backdrop = document.getElementById('erCongeModalBackdrop');
    var tabConges = document.getElementById('er_fiche_tab_conges');

    var inputMois = document.getElementById('conge_mois');
    var inputJours = document.getElementById('conge_nb_jours');
    var quotaInput = document.getElementById('conges_quota_global_ref');
    var mapInput = document.getElementById('conges_totaux_annee_json');
    var restantPreview = document.getElementById('conge_restant_preview');

    function openModal() {
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('er-conge-modal-open');
      if (tabConges) tabConges.checked = true;
      if (inputJours) {
        setTimeout(function () { inputJours.focus(); }, 80);
      }
      updatePreview();
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('er-conge-modal-open');
    }

    function updatePreview() {
      if (!restantPreview || !inputMois || !inputJours || !quotaInput || !mapInput) return;
      var quota = parseInt(quotaInput.value || '0', 10);
      if (!isFinite(quota) || quota < 0) quota = 0;
      var jours = parseInt(inputJours.value || '0', 10);
      if (!isFinite(jours) || jours < 0) jours = 0;
      var mois = (inputMois.value || '').trim();
      var annee = mois.length >= 4 ? mois.substring(0, 4) : '';
      var map = parseMap(mapInput.value || '{}');
      var deja = parseInt(map[annee] || 0, 10);
      if (!isFinite(deja) || deja < 0) deja = 0;
      var restant = quota - deja - jours;
      if (restant < 0) restant = 0;
      restantPreview.textContent = restant + ' jour(s)';
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);
    if (inputMois) inputMois.addEventListener('change', updatePreview);
    if (inputJours) inputJours.addEventListener('input', updatePreview);
    updatePreview();

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });
  });
})();
