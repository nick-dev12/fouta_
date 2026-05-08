/**
 * Fiche employé — modale génération bulletin de paie (ouverture / fermeture uniquement).
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('erBpModal');
    if (!modal) {
      return;
    }

    var btnOpen = document.getElementById('erBpOpenBtn');
    var btnClose = document.getElementById('erBpModalClose');
    var btnCancel = document.getElementById('erBpModalCancel');
    var backdrop = document.getElementById('erBpModalBackdrop');
    var tabBp = document.getElementById('er_fiche_tab_bp');
    var moisInput = document.getElementById('bp_mois_paie');
    var primeTransportInput = document.getElementById('bp_g_prime_transport');
    var primeTransportCfgInput = document.getElementById('bp_prime_transport_config');
    var primeTransportMapInput = document.getElementById('bp_prime_transport_map_json');
    var primeTransportHint = document.getElementById('bp_prime_transport_hint');

    function parseMap(raw) {
      if (!raw) {
        return {};
      }
      try {
        var obj = JSON.parse(raw);
        return obj && typeof obj === 'object' ? obj : {};
      } catch (e) {
        return {};
      }
    }

    function formatFcfa(n) {
      var v = Math.max(0, Math.round(Number(n) || 0));
      return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    }

    function updatePrimeTransportForMonth() {
      if (!primeTransportInput || !primeTransportCfgInput || !primeTransportMapInput || !moisInput) {
        return;
      }
      var cfg = parseFloat(primeTransportCfgInput.value || '0');
      if (!isFinite(cfg) || cfg < 0) {
        cfg = 0;
      }
      var mois = (moisInput.value || '').trim();
      var map = parseMap(primeTransportMapInput.value || '{}');
      var row = map[mois] || { jours: 0, montant: 0 };
      var montantRetrait = Number(row.montant || 0);
      if (!isFinite(montantRetrait) || montantRetrait < 0) {
        montantRetrait = 0;
      }
      var net = Math.max(0, Math.round((cfg - montantRetrait) * 100) / 100);
      primeTransportInput.value = net.toFixed(2);
      if (primeTransportHint) {
        var j = parseInt(row.jours || 0, 10);
        if (!isFinite(j) || j < 0) {
          j = 0;
        }
        primeTransportHint.textContent = 'Prime nette pour ' + (mois || 'ce mois') + ' : ' + formatFcfa(net) + ' (déductions : ' + j + ' jour(s) / ' + formatFcfa(montantRetrait) + ').';
      }
    }

    function openModal() {
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('er-bp-modal-open');
      if (tabBp) {
        tabBp.checked = true;
      }
      updatePrimeTransportForMonth();
      var first = moisInput;
      if (first) {
        setTimeout(function () {
          first.focus();
        }, 80);
      }
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('er-bp-modal-open');
    }

    if (btnOpen) {
      btnOpen.addEventListener('click', openModal);
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
    if (moisInput) {
      moisInput.addEventListener('change', updatePrimeTransportForMonth);
    }
    updatePrimeTransportForMonth();

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });

    var transportForm = document.getElementById('transportRetraitForm');
    var transportMois = document.getElementById('transport_mois_paie');
    var transportJours = document.getElementById('transport_nb_jours');
    var transportPrimeRef = document.getElementById('transport_prime_mensuelle_ref');
    var transportJoursRef = document.getElementById('transport_jours_reference_ref');
    var transportMapJson = document.getElementById('transport_totaux_map_json');
    var transportMontantPreview = document.getElementById('transport_montant_preview');
    var transportPrimeRestantePreview = document.getElementById('transport_prime_restante_preview');

    function updateTransportPreview() {
      if (!transportForm || !transportMois || !transportJours || !transportPrimeRef || !transportJoursRef || !transportMapJson) {
        return;
      }
      var primeRef = parseFloat(transportPrimeRef.value || '0');
      var joursRef = parseInt(transportJoursRef.value || '0', 10);
      var nbJours = parseInt(transportJours.value || '0', 10);
      var moisSel = (transportMois.value || '').trim();
      var map = parseMap(transportMapJson.value || '{}');
      var row = map[moisSel] || { jours: 0, montant: 0 };
      var montantExistant = Number(row.montant || 0);
      if (!isFinite(montantExistant) || montantExistant < 0) {
        montantExistant = 0;
      }
      if (!isFinite(nbJours) || nbJours < 0) {
        nbJours = 0;
      }

      var montantSaisi = 0;
      if (primeRef > 0 && joursRef > 0 && nbJours > 0) {
        montantSaisi = (primeRef / joursRef) * nbJours;
      }
      var primeRestante = Math.max(0, primeRef - montantExistant - montantSaisi);
      if (transportMontantPreview) {
        transportMontantPreview.textContent = formatFcfa(montantSaisi);
      }
      if (transportPrimeRestantePreview) {
        transportPrimeRestantePreview.textContent = formatFcfa(primeRestante);
      }
    }

    if (transportMois) {
      transportMois.addEventListener('change', updateTransportPreview);
    }
    if (transportJours) {
      transportJours.addEventListener('input', updateTransportPreview);
    }
    updateTransportPreview();
  });
})();
