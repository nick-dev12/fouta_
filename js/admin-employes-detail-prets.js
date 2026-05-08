/**
 * Fiche employé — modales prêts (nouveau prêt, remboursement, détail plein écran).
 */
(function () {
  'use strict';

  function escText(s) {
    if (s === null || s === undefined) {
      return '';
    }
    return String(s);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('erPretModal');
    var btnOpen = document.getElementById('erPretOpenBtn');
    var btnClose = document.getElementById('erPretModalClose');
    var btnCancel = document.getElementById('erPretModalCancel');
    var backdrop = document.getElementById('erPretModalBackdrop');
    var tabPret = document.getElementById('er_fiche_tab_pret');

    var remModal = document.getElementById('erPretRembModal');
    var remBackdrop = document.getElementById('erPretRembModalBackdrop');
    var remClose = document.getElementById('erPretRembModalClose');
    var remCancel = document.getElementById('erPretRembModalCancel');
    var remHint = document.getElementById('erPretRembResteHint');
    var remPretId = document.getElementById('pret_remb_pret_id');
    var remMontant = document.getElementById('pret_remb_montant');

    var detModal = document.getElementById('erPretDetailModal');
    var detBackdrop = document.getElementById('erPretDetailBackdrop');
    var detClose = document.getElementById('erPretDetailClose');
    var detContent = document.getElementById('erPretDetailContent');
    var detTitle = document.getElementById('erPretDetailTitle');

    function openPretModal() {
      if (!modal) {
        return;
      }
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('er-pret-modal-open');
      if (tabPret) {
        tabPret.checked = true;
      }
      var first = document.getElementById('pret_montant');
      if (first) {
        setTimeout(function () {
          first.focus();
        }, 80);
      }
    }

    function closePretModal() {
      if (!modal) {
        return;
      }
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('er-pret-modal-open');
    }

    function openRemModal(pretId, resteFr) {
      if (!remModal) {
        return;
      }
      if (remPretId) {
        remPretId.value = pretId;
      }
      if (remHint) {
        remHint.textContent =
          'Reste à payer : ' +
          escText(resteFr) +
          ' FCFA — le versement ne peut pas dépasser ce montant.';
      }
      if (remMontant) {
        remMontant.placeholder = 'Max. ' + escText(resteFr) + ' FCFA';
      }
      remModal.classList.add('is-open');
      remModal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('er-pret-remb-modal-open');
      if (tabPret) {
        tabPret.checked = true;
      }
      var f = document.getElementById('pret_remb_montant');
      if (f) {
        setTimeout(function () {
          f.focus();
        }, 80);
      }
    }

    function closeRemModal() {
      if (!remModal) {
        return;
      }
      remModal.classList.remove('is-open');
      remModal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('er-pret-remb-modal-open');
    }

    function renderDetail(d) {
      if (!detContent || !detTitle) {
        return;
      }
      detContent.textContent = '';
      detTitle.textContent = 'Prêt n° ' + escText(d.id);

      var grid = document.createElement('div');
      grid.className = 'er-pret-detail-grid';

      function addCard(label, value) {
        var card = document.createElement('div');
        card.className = 'er-pret-detail-card';
        var lb = document.createElement('span');
        lb.className = 'er-pret-detail-card__label';
        lb.textContent = label;
        var val = document.createElement('strong');
        val.className = 'er-pret-detail-card__value';
        val.textContent = value;
        card.appendChild(lb);
        card.appendChild(val);
        grid.appendChild(card);
      }

      addCard('Montant du prêt', escText(d.montant_fcfa) + ' FCFA');
      addCard('Montant versé', escText(d.verse_fcfa) + ' FCFA');
      addCard('Reste à payer', escText(d.reste_fcfa) + ' FCFA');
      addCard('Date d’octroi', escText(d.date_octroi_fr));
      addCard('Fin de remboursement prévue', escText(d.fin_prevue_fr));
      if (d.mensualite_fcfa) {
        addCard('Mensualité prévue', escText(d.mensualite_fcfa) + ' FCFA');
      }
      addCard('Statut', escText(d.statut_label));
      addCard('Enregistré par', escText(d.saisi_par));
      addCard('Date de saisie du prêt', escText(d.date_creation_pret_fr));

      detContent.appendChild(grid);

      var hMot = document.createElement('h3');
      hMot.className = 'er-pret-detail-section-title';
      hMot.textContent = 'Objet / motif';
      detContent.appendChild(hMot);
      var pMot = document.createElement('p');
      pMot.className = 'er-pret-detail-text';
      pMot.textContent = escText(d.motif);
      detContent.appendChild(pMot);

      if (d.commentaire_pret) {
        var hC = document.createElement('h3');
        hC.className = 'er-pret-detail-section-title';
        hC.textContent = 'Commentaire interne (prêt)';
        detContent.appendChild(hC);
        var pC = document.createElement('p');
        pC.className = 'er-pret-detail-text';
        pC.textContent = escText(d.commentaire_pret);
        detContent.appendChild(pC);
      }

      var hR = document.createElement('h3');
      hR.className = 'er-pret-detail-section-title';
      hR.textContent = 'Historique des versements';
      detContent.appendChild(hR);

      if (!d.remboursements || !d.remboursements.length) {
        var empty = document.createElement('p');
        empty.className = 'er-pret-detail-muted';
        empty.textContent = 'Aucun versement enregistré.';
        detContent.appendChild(empty);
      } else {
        var wrap = document.createElement('div');
        wrap.className = 'er-pret-detail-remb-wrap';
        var table = document.createElement('table');
        table.className = 'er-detail-table er-pret-detail-remb-table';
        var thead = document.createElement('thead');
        var trh = document.createElement('tr');
        ['Date', 'Montant (FCFA)', 'Enregistré par', 'Saisi le', 'Commentaire'].forEach(function (h) {
          var th = document.createElement('th');
          th.textContent = h;
          trh.appendChild(th);
        });
        thead.appendChild(trh);
        table.appendChild(thead);
        var tb = document.createElement('tbody');
        d.remboursements.forEach(function (r) {
          var tr = document.createElement('tr');
          [
            escText(r.date_fr),
            escText(r.montant_fcfa),
            escText(r.enregistre_par),
            escText(r.saisi_le_fr),
            escText(r.commentaire) || '—'
          ].forEach(function (cell) {
            var td = document.createElement('td');
            td.textContent = cell;
            tr.appendChild(td);
          });
          tb.appendChild(tr);
        });
        table.appendChild(tb);
        wrap.appendChild(table);
        detContent.appendChild(wrap);
      }
    }

    function openDetailModal(jsonStr) {
      if (!detModal) {
        return;
      }
      var d;
      try {
        d = JSON.parse(jsonStr);
      } catch (err) {
        return;
      }
      renderDetail(d);
      detModal.classList.add('is-open');
      detModal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('er-pret-detail-open');
    }

    function closeDetailModal() {
      if (!detModal) {
        return;
      }
      detModal.classList.remove('is-open');
      detModal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('er-pret-detail-open');
    }

    if (modal) {
      if (btnOpen) {
        btnOpen.addEventListener('click', openPretModal);
      }
      if (btnClose) {
        btnClose.addEventListener('click', closePretModal);
      }
      if (btnCancel) {
        btnCancel.addEventListener('click', closePretModal);
      }
      if (backdrop) {
        backdrop.addEventListener('click', closePretModal);
      }
      if (modal.classList.contains('is-open')) {
        document.body.classList.add('er-pret-modal-open');
      }
    }

    if (remModal) {
      if (remClose) {
        remClose.addEventListener('click', closeRemModal);
      }
      if (remCancel) {
        remCancel.addEventListener('click', closeRemModal);
      }
      if (remBackdrop) {
        remBackdrop.addEventListener('click', closeRemModal);
      }
      document.querySelectorAll('.er-pret-btn-remb').forEach(function (btn) {
        btn.addEventListener('click', function () {
          openRemModal(btn.getAttribute('data-pret-id'), btn.getAttribute('data-reste-fr'));
        });
      });
      if (remModal.classList.contains('is-open')) {
        document.body.classList.add('er-pret-remb-modal-open');
        var boot = document.getElementById('erPretRembHintBoot');
        if (boot && boot.getAttribute('data-reste-fr') && remHint) {
          var rf = boot.getAttribute('data-reste-fr');
          remHint.textContent =
            'Reste à payer : ' +
            rf +
            ' FCFA — le versement ne peut pas dépasser ce montant.';
          if (remMontant) {
            remMontant.placeholder = 'Max. ' + rf + ' FCFA';
          }
        }
      }
    }

    if (detModal) {
      document.querySelectorAll('.er-pret-btn-detail').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var raw = btn.getAttribute('data-pret-detail');
          if (raw) {
            openDetailModal(raw);
          }
        });
      });
      if (detClose) {
        detClose.addEventListener('click', closeDetailModal);
      }
      if (detBackdrop) {
        detBackdrop.addEventListener('click', closeDetailModal);
      }
    }

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') {
        return;
      }
      if (detModal && detModal.classList.contains('is-open')) {
        closeDetailModal();
      } else if (remModal && remModal.classList.contains('is-open')) {
        closeRemModal();
      } else if (modal && modal.classList.contains('is-open')) {
        closePretModal();
      }
    });
  });
})();
