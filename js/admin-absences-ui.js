/**
 * Absences RH — interaction UI uniquement : modales, liste dépendante, prévisualisation image.
 * Soumission et validation : PHP (formulaires avec action).
 */
(function () {
  'use strict';

  var DATA_ID = 'abs-json-cibles-absence';

  function parseMap() {
    var el = document.getElementById(DATA_ID);
    if (!el) {
      return {};
    }
    try {
      return JSON.parse(el.textContent || '{}');
    } catch (e) {
      return {};
    }
  }

  var mapNonJustif = parseMap();

  function qs(id) {
    return document.getElementById(id);
  }

  function trapFocus(panel) {
    if (!panel) {
      return;
    }
    var f = panel.querySelector('input:not([type="hidden"]), select, textarea, button:not([disabled])');
    if (f) {
      f.focus();
    }
  }

  function rebuildJustifyAbsenceOptions() {
    var justifyCible = qs('justify-cible');
    var justifyAbsence = qs('justify-absence');
    if (!justifyCible || !justifyAbsence) {
      return;
    }
    var ckey = justifyCible.value;
    justifyAbsence.innerHTML = '';
    if (!ckey) {
      justifyAbsence.disabled = true;
      var o = document.createElement('option');
      o.value = '';
      o.textContent = '— Choisir d’abord une personne —';
      justifyAbsence.appendChild(o);
      return;
    }
    var rows = mapNonJustif[ckey] || [];
    if (!rows.length) {
      justifyAbsence.disabled = true;
      var o2 = document.createElement('option');
      o2.value = '';
      o2.textContent = 'Aucune absence sans justificatif';
      justifyAbsence.appendChild(o2);
      return;
    }
    justifyAbsence.disabled = false;
    var ph = document.createElement('option');
    ph.value = '';
    ph.textContent = '— Sélectionnez une absence —';
    justifyAbsence.appendChild(ph);
    rows.forEach(function (r) {
      var opt = document.createElement('option');
      opt.value = String(r.id);
      var ds = '';
      try {
        if (r.date) {
          var p = String(r.date).split('-');
          if (p.length === 3) {
            ds = p[2] + '/' + p[1] + '/' + p[0];
          }
        }
      } catch (ignored) {}
      var mt = String(r.motif || '').replace(/\s+/g, ' ');
      if (mt.length > 56) {
        mt = mt.slice(0, 53) + '…';
      }
      opt.textContent = ds ? (ds + ' — ' + mt) : mt || ('#' + r.id);
      justifyAbsence.appendChild(opt);
    });
  }

  function openModal(which) {
    var id = which === 'add' ? 'abs-modal-add' : 'abs-modal-justify';
    var modal = qs(id);
    if (!modal) {
      return;
    }
    document.querySelectorAll('.abs-modal').forEach(function (m) {
      m.classList.remove('abs-modal--visible');
      m.setAttribute('aria-hidden', 'true');
    });
    modal.classList.add('abs-modal--visible');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('abs-modal-open');
    if (which === 'justify') {
      rebuildJustifyAbsenceOptions();
    }
    trapFocus(modal.querySelector('.abs-modal__panel'));
  }

  function closeModals() {
    document.querySelectorAll('.abs-modal').forEach(function (m) {
      m.classList.remove('abs-modal--visible');
      m.setAttribute('aria-hidden', 'true');
    });
    document.body.classList.remove('abs-modal-open');
  }

  document.querySelectorAll('[data-abs-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.getAttribute('data-abs-open') || 'add');
    });
  });

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) {
      return;
    }
    if (t.closest('[data-abs-close]')) {
      closeModals();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeModals();
    }
  });

  var justifyCibleEl = qs('justify-cible');
  if (justifyCibleEl) {
    justifyCibleEl.addEventListener('change', rebuildJustifyAbsenceOptions);
  }

  var fileInput = qs('justify-file');
  var previewBox = qs('justify-preview');
  var previewImg = qs('justify-preview-img');
  var previewClear = qs('justify-preview-clear');

  function clearPreview() {
    if (!fileInput || !previewBox || !previewImg) {
      return;
    }
    fileInput.value = '';
    previewImg.removeAttribute('src');
    previewBox.classList.add('abs-preview--hidden');
  }

  if (fileInput && previewBox && previewImg) {
    fileInput.addEventListener('change', function () {
      var f = fileInput.files && fileInput.files[0];
      if (!f) {
        clearPreview();
        return;
      }
      if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
        clearPreview();
        return;
      }
      var reader = new FileReader();
      reader.onload = function (ev) {
        previewImg.src = ev.target && ev.target.result ? ev.target.result : '';
        previewBox.classList.remove('abs-preview--hidden');
      };
      reader.readAsDataURL(f);
    });
  }

  if (previewClear && fileInput) {
    previewClear.addEventListener('click', clearPreview);
  }
})();
