/**
 * Panier caisse — totaux ligne et récap en temps réel ; synchro prix/qté à l’encaissement.
 */
(function () {
  'use strict';

  function parseMontant(raw) {
    var t = String(raw || '').trim();
    if (t === '') {
      return 0;
    }
    t = t.replace(/\s+/g, '').replace(',', '.');
    var n = parseFloat(t);
    return isNaN(n) ? 0 : n;
  }

  function fmtFcfa(n) {
    var x = Math.round(Number(n));
    return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f');
  }

  function lineTotal(pu, qty, remisePct) {
    var rl = Math.min(100, Math.max(0, Number(remisePct) || 0));
    var q = Math.max(0, parseInt(String(qty), 10) || 0);
    return pu * q * (1 - rl / 100);
  }

  function getCartTable() {
    return document.querySelector('.caisse-cart-table');
  }

  function updateRowTotal(tr) {
    if (!tr) {
      return 0;
    }
    var prixInp = tr.querySelector('.caisse-prix-input');
    var qtyInp = tr.querySelector('.caisse-qty-input');
    var totalEl = tr.querySelector('.caisse-cart-total-value');
    if (!prixInp || !qtyInp || !totalEl) {
      return 0;
    }
    var remise = parseFloat(tr.getAttribute('data-remise-ligne') || '0') || 0;
    var pu = parseMontant(prixInp.value);
    var q = parseInt(qtyInp.value, 10);
    if (isNaN(q) || q < 1) {
      q = 1;
    }
    var tl = Math.round(lineTotal(pu, q, remise));
    totalEl.textContent = fmtFcfa(tl);
    return tl;
  }

  function setRecapAmount(el, amount) {
    if (!el) {
      return;
    }
    var small = el.querySelector('small');
    var suffix = small ? small.outerHTML : ' <small>FCFA</small>';
    el.innerHTML = fmtFcfa(amount) + suffix;
  }

  function updateRecap() {
    var table = getCartTable();
    if (!table) {
      return;
    }
    var rows = table.querySelectorAll('.caisse-cart-row');
    var sous = 0;
    var i;
    for (i = 0; i < rows.length; i++) {
      var tr = rows[i];
      var prixInp = tr.querySelector('.caisse-prix-input');
      var qtyInp = tr.querySelector('.caisse-qty-input');
      if (!prixInp || !qtyInp) {
        continue;
      }
      var remise = parseFloat(tr.getAttribute('data-remise-ligne') || '0') || 0;
      var pu = parseMontant(prixInp.value);
      var q = parseInt(qtyInp.value, 10);
      if (isNaN(q) || q < 1) {
        q = 1;
      }
      sous += lineTotal(pu, q, remise);
    }

    var rg = parseFloat(table.getAttribute('data-remise-globale') || '0') || 0;
    rg = Math.min(100, Math.max(0, rg));
    var netBrut = Math.round(sous * (1 - rg / 100) * 100) / 100;
    var taux = parseFloat(table.getAttribute('data-tva-taux') || '0') / 100;
    var inclure = table.getAttribute('data-inclure-tva') === '1';
    var ht;
    var tva;
    var ttc;

    if (taux <= 0) {
      ht = netBrut;
      tva = 0;
      ttc = netBrut;
    } else if (inclure) {
      ht = netBrut;
      tva = Math.round(netBrut * taux * 100) / 100;
      ttc = Math.round((netBrut + tva) * 100) / 100;
    } else {
      ttc = netBrut;
      ht = Math.round((netBrut / (1 + taux)) * 100) / 100;
      tva = Math.round((ttc - ht) * 100) / 100;
    }

    setRecapAmount(document.getElementById('caisse-recap-ht'), ht);
    setRecapAmount(document.getElementById('caisse-recap-tva'), tva);
    setRecapAmount(document.getElementById('caisse-recap-ttc'), ttc);
  }

  function refreshCartTotals() {
    var rows = document.querySelectorAll('.caisse-cart-row');
    var i;
    for (i = 0; i < rows.length; i++) {
      updateRowTotal(rows[i]);
    }
    updateRecap();
  }

  function syncFieldToForm(form, selector) {
    if (!form) {
      return;
    }
    document.querySelectorAll(selector).forEach(function (inp) {
      var name = inp.getAttribute('name');
      if (!name) {
        return;
      }
      var found = null;
      var fields = form.querySelectorAll('input[name]');
      for (var i = 0; i < fields.length; i++) {
        if (fields[i].name === name && fields[i] !== inp) {
          found = fields[i];
          break;
        }
      }
      if (found) {
        found.value = inp.value;
      } else {
        var h = document.createElement('input');
        h.type = 'hidden';
        h.name = name;
        h.value = inp.value;
        form.appendChild(h);
      }
    });
  }

  function syncCartToForm(form) {
    syncFieldToForm(form, '.caisse-prix-input');
    syncFieldToForm(form, '.caisse-qty-input');
  }

  function bindCartInputs() {
    document.querySelectorAll('.caisse-prix-input').forEach(function (inp) {
      inp.addEventListener('input', function () {
        inp.classList.add('caisse-prix-input--manuel');
        var tr = inp.closest('.caisse-cart-row');
        updateRowTotal(tr);
        updateRecap();
      });
    });

    document.querySelectorAll('.caisse-qty-input').forEach(function (inp) {
      function onQtyChange() {
        var tr = inp.closest('.caisse-cart-row');
        updateRowTotal(tr);
        updateRecap();
      }
      inp.addEventListener('input', onQtyChange);
      inp.addEventListener('change', onQtyChange);
    });
  }

  var formPay = document.querySelector('.caisse-pay-form');
  if (formPay) {
    formPay.addEventListener('submit', function () {
      syncCartToForm(formPay);
    });
  }

  if (getCartTable()) {
    bindCartInputs();
    refreshCartTotals();
  }
})();
