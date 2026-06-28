/**
 * Panier caisse — état client uniquement (pas de session PHP).
 * Génération ticket / encaissement via API JSON.
 */
(function (global) {
  'use strict';

  var cfg = {};
  var state = {
    lines: [],
    remise_globale_pct: 0,
    inclure_tva: 0
  };

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function parseMontant(raw) {
    var t = String(raw || '').trim().replace(/\s+/g, '').replace(',', '.');
    if (t === '') return 0;
    var n = parseFloat(t);
    return isNaN(n) ? 0 : n;
  }

  function fmtFcfa(n) {
    return String(Math.round(Number(n))).replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f');
  }

  function linePrix(ln) {
    if (ln.prix_saisie != null && String(ln.prix_saisie).trim() !== '') {
      return parseMontant(ln.prix_saisie);
    }
    return parseMontant(ln.prix_unitaire);
  }

  function lineTotal(line) {
    var rl = Math.min(100, Math.max(0, Number(line.remise_ligne_pct) || 0));
    var q = Math.max(0, parseInt(String(line.quantite), 10) || 0);
    var pu = linePrix(line);
    return pu * q * (1 - rl / 100);
  }

  function computeTotals() {
    var sous = 0;
    state.lines.forEach(function (ln) {
      sous += lineTotal(ln);
    });
    var rg = Math.min(100, Math.max(0, Number(state.remise_globale_pct) || 0));
    var netBrut = Math.round(sous * (1 - rg / 100) * 100) / 100;
    var taux = Number(cfg.tva_taux || 0) / 100;
    var inclure = !!state.inclure_tva;
    var ht, tva, ttc;
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
    return { ht: ht, tva: tva, ttc: ttc, sous: sous };
  }

  function findLine(pid) {
    pid = parseInt(pid, 10);
    for (var i = 0; i < state.lines.length; i++) {
      if (state.lines[i].produit_id === pid) return state.lines[i];
    }
    return null;
  }

  function cartPayload() {
    syncCartFromDom();
    return {
      lines: state.lines.map(function (ln) {
        return {
          produit_id: ln.produit_id,
          quantite: parseInt(ln.quantite, 10) || 1,
          prix_unitaire: linePrix(ln),
          remise_ligne_pct: Number(ln.remise_ligne_pct) || 0
        };
      }),
      remise_globale_pct: Number(state.remise_globale_pct) || 0,
      inclure_tva: state.inclure_tva ? 1 : 0
    };
  }

  function syncCartFromDom() {
    var mount = document.getElementById('caisse-panier-mount');
    if (!mount) {
      return;
    }
    mount.querySelectorAll('[data-field="prix"]').forEach(function (inp) {
      var pid = parseInt(inp.getAttribute('data-pid'), 10);
      var ln = findLine(pid);
      if (!ln) {
        return;
      }
      ln.prix_saisie = inp.value;
      ln.prix_unitaire = parseMontant(inp.value);
      if (String(inp.value).trim() !== '') {
        ln.prix_manuel = 1;
      }
    });
    mount.querySelectorAll('[data-field="qty"]').forEach(function (inp) {
      var pid = parseInt(inp.getAttribute('data-pid'), 10);
      var ln = findLine(pid);
      if (!ln) {
        return;
      }
      var q = parseInt(inp.value, 10);
      if (!isNaN(q) && q >= 1) {
        ln.quantite = q;
      }
    });
  }

  function prixInputValue(ln) {
    if (ln.prix_saisie != null && String(ln.prix_saisie).trim() !== '') {
      return String(ln.prix_saisie);
    }
    if (ln.prix_unitaire > 0) {
      return String(Math.round(parseMontant(ln.prix_unitaire)));
    }
    return '';
  }

  function apiCall(action, extra) {
    var body = extra || {};
    body.action = action;
    body.csrf_token = cfg.csrf || '';
    return fetch(cfg.api_url || 'api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json().catch(function () {
        return { ok: false, error: 'Réponse serveur invalide.' };
      });
    });
  }

  function flash(msg, isErr) {
    var el = document.getElementById('caisse-flash-live');
    if (!el) return;
    if (!msg) {
      el.hidden = true;
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.className = 'caisse-flash-live ' + (isErr ? 'caisse-flash-live--err' : 'caisse-flash-live--ok');
    el.textContent = msg;
  }

  function updateRecapOnly() {
    var totals = computeTotals();
    var hasLines = state.lines.length > 0;
    var mount = document.getElementById('caisse-panier-mount');

    if (mount) {
      state.lines.forEach(function (ln) {
        var row = mount.querySelector('tr.caisse-cart-row[data-produit-id="' + ln.produit_id + '"]');
        if (!row) {
          return;
        }
        var totalCell = row.querySelector('.caisse-cart-total-value');
        if (totalCell) {
          totalCell.textContent = fmtFcfa(Math.round(lineTotal(ln)));
        }
      });
    }

    var htEl = document.getElementById('caisse-recap-ht');
    var tvaEl = document.getElementById('caisse-recap-tva');
    var ttcEl = document.getElementById('caisse-recap-ttc');
    if (htEl) htEl.innerHTML = fmtFcfa(totals.ht) + ' <small>FCFA</small>';
    if (tvaEl) tvaEl.innerHTML = fmtFcfa(totals.tva) + ' <small>FCFA</small>';
    if (ttcEl) ttcEl.innerHTML = fmtFcfa(totals.ttc) + ' <small>FCFA</small>';

    var payBtn = document.querySelector('.caisse-btn-valider');
    if (payBtn) payBtn.disabled = !hasLines || totals.ttc <= 0 || !cfg.tables_ok;

    var monnaieBox = document.getElementById('caisse-monnaie-box');
    if (monnaieBox) monnaieBox.hidden = !hasLines || totals.ttc <= 0;

    var hint = mount ? mount.querySelector('.caisse-generer-ticket-hint') : null;
    if (hint) {
      hint.hidden = totals.ttc > 0;
    }
  }

  function render() {
    var root = document.getElementById('caisse-panier-root');
    if (!root) return;

    var totals = computeTotals();
    var hasLines = state.lines.length > 0;

    if (!hasLines) {
      root.innerHTML =
        '<div class="caisse-panier-vide">' +
        '<i class="fas fa-cart-arrow-down"></i>' +
        '<p>Panier vide</p>' +
        '<p class="caisse-panier-vide-hint">Scannez un code ou recherchez un produit ci-dessus.</p>' +
        '</div>';
    } else {
      var rows = '';
      state.lines.forEach(function (ln) {
        var tl = Math.round(lineTotal(ln));
        var prixCls = ln.prix_manuel || ln.sans_prix_catalogue ? ' caisse-prix-input--manuel' : '';
        var refHtml = ln.ref ? '<span class="caisse-cart-ref"><code>' + esc(ln.ref) + '</code></span>' : '';
        rows +=
          '<tr class="caisse-cart-row" data-produit-id="' + ln.produit_id + '">' +
          '<td><div class="caisse-cart-produit-cell">' +
          '<div class="caisse-cart-produit-line1"><span class="caisse-cart-nom">' + esc(ln.nom) + '</span></div>' +
          refHtml + '</div></td>' +
          '<td><div class="caisse-prix-cell">' +
          '<input type="text" class="caisse-prix-input' + prixCls + '" data-field="prix" data-pid="' + ln.produit_id + '" ' +
          'value="' + esc(prixInputValue(ln)) + '" inputmode="decimal" autocomplete="off" ' +
          (ln.sans_prix_catalogue ? 'placeholder="Prix à saisir"' : '') + '></div></td>' +
          '<td><div class="caisse-qty-cell caisse-qty-cell--solo">' +
          '<input type="number" class="caisse-qty-input" data-field="qty" data-pid="' + ln.produit_id + '" ' +
          'min="1" max="' + Math.max(1, ln.stock) + '" value="' + esc(ln.quantite) + '" inputmode="numeric"></div></td>' +
          '<td class="caisse-cart-total-ligne"><strong class="caisse-cart-total-value">' + fmtFcfa(tl) + '</strong></td>' +
          '<td><button type="button" class="caisse-btn-remove" data-remove="' + ln.produit_id + '" title="Supprimer"><i class="fas fa-times"></i></button></td>' +
          '</tr>';
      });

      root.innerHTML =
        '<div class="caisse-zone-b-head">' +
        '<h2 class="caisse-zone-title"><i class="fas fa-shopping-basket"></i> B · Panier</h2>' +
        '<button type="button" class="btn-annuler-vente" id="caisse-btn-clear"><i class="fas fa-times-circle"></i> Annuler la vente</button>' +
        '</div>' +
        '<div class="caisse-table-scroll">' +
        '<table class="caisse-cart-table" id="caisse-cart-table">' +
        '<thead><tr><th>Produit</th><th>Prix HT</th><th>Quantité</th><th>Total</th><th class="caisse-col-actions"></th></tr></thead>' +
        '<tbody>' + rows + '</tbody></table></div>' +
        (cfg.afficher_tva
          ? '<div class="caisse-tva-option"><label class="caisse-tva-option-label">' +
            '<input type="checkbox" class="caisse-tva-option-check" id="caisse-tva-check"' + (state.inclure_tva ? ' checked' : '') + '> ' +
            '<span><strong>Inclure la TVA</strong> (' + esc(cfg.tva_taux) + ' %) : la TVA s’ajoute au net catalogue.</span></label></div>'
          : '') +
        '<button type="button" class="btn-primary caisse-btn-generer-ticket" id="caisse-btn-generer"' +
        (cfg.tables_ok ? '' : ' disabled') + '><i class="fas fa-ticket-alt"></i> Générer le ticket</button>' +
        (totals.ttc <= 0 ? '<p class="caisse-generer-ticket-hint">Saisissez le prix de chaque ligne avant de générer le ticket.</p>' : '');
    }

    updateRecapOnly();
  }

  function bindRootEvents() {
    var mount = document.getElementById('caisse-panier-mount');
    if (!mount || mount._caisseBound) {
      return;
    }
    mount._caisseBound = true;

    mount.addEventListener('input', function (ev) {
      var inp = ev.target;
      if (!inp || !inp.getAttribute('data-pid')) return;
      var pid = parseInt(inp.getAttribute('data-pid'), 10);
      var ln = findLine(pid);
      if (!ln) return;
      if (inp.getAttribute('data-field') === 'prix') {
        ln.prix_saisie = inp.value;
        ln.prix_unitaire = parseMontant(inp.value);
        ln.prix_manuel = 1;
        inp.classList.add('caisse-prix-input--manuel');
        updateRecapOnly();
        return;
      }
      if (inp.getAttribute('data-field') === 'qty') {
        var q = parseInt(inp.value, 10);
        if (isNaN(q) || q < 1) q = 1;
        if (q > ln.stock) {
          q = ln.stock;
          inp.value = q;
          flash('Stock maximum : ' + ln.stock, true);
        }
        ln.quantite = q;
        updateRecapOnly();
      }
    });

    mount.addEventListener('click', function (ev) {
      var rm = ev.target.closest('[data-remove]');
      if (rm) {
        var pidRm = parseInt(rm.getAttribute('data-remove'), 10);
        state.lines = state.lines.filter(function (l) { return l.produit_id !== pidRm; });
        render();
        return;
      }
      if (ev.target.closest('#caisse-btn-clear')) {
        if (state.lines.length && !confirm('Annuler toute la vente en cours ?')) return;
        state.lines = [];
        flash('');
        render();
        return;
      }
      if (ev.target.closest('#caisse-btn-generer')) {
        genererTicket();
        return;
      }
      if (ev.target.closest('#caisse-tva-check')) {
        state.inclure_tva = ev.target.checked ? 1 : 0;
        render();
      }
    });
  }

  function dismissLiveResults() {
    if (global.CaisseLiveSearch && typeof global.CaisseLiveSearch.dismiss === 'function') {
      global.CaisseLiveSearch.dismiss(true);
    }
  }

  function addProduct(produit, qty, silent) {
    if (!produit || !produit.id) return;
    qty = Math.max(1, parseInt(qty, 10) || 1);
    var existing = findLine(produit.id);
    if (existing) {
      var nq = existing.quantite + qty;
      if (nq > produit.stock) {
        flash('Stock insuffisant pour « ' + produit.nom + ' » (max ' + produit.stock + ').', true);
        return;
      }
      existing.quantite = nq;
    } else {
      if (qty > produit.stock) {
        flash('Stock insuffisant pour « ' + produit.nom + ' ».', true);
        return;
      }
      state.lines.push({
        produit_id: produit.id,
        nom: produit.nom || '',
        ref: produit.ref || '',
        stock: produit.stock || 0,
        prix_unitaire: produit.prix > 0 ? produit.prix : 0,
        prix_saisie: produit.prix > 0 ? String(Math.round(produit.prix)) : '',
        quantite: qty,
        remise_ligne_pct: 0,
        prix_manuel: produit.sans_prix_catalogue ? 1 : 0,
        sans_prix_catalogue: !!produit.sans_prix_catalogue
      });
    }
    if (!silent) {
      flash('« ' + (produit.nom || 'Produit') + ' » ajouté au panier.');
    } else {
      flash('');
    }
    dismissLiveResults();
    render();
  }

  function addById(pid, qty) {
    pid = parseInt(pid, 10);
    if (!pid) return Promise.resolve();
    return apiCall('get_product', { produit_id: pid }).then(function (res) {
      if (!res.ok || !res.produit) {
        flash(res.error || 'Produit introuvable.', true);
        return res;
      }
      addProduct(res.produit, qty || 1);
      return res;
    }).catch(function () {
      flash('Erreur réseau.', true);
    });
  }

  function genererTicket() {
    syncCartFromDom();
    if (!state.lines.length) {
      flash('Panier vide.', true);
      return;
    }
    var payload = cartPayload();
    if (!payload.lines.length) {
      flash('Panier vide.', true);
      return;
    }
    for (var i = 0; i < payload.lines.length; i++) {
      if (payload.lines[i].prix_unitaire <= 0) {
        flash('Saisissez le prix de chaque ligne avant de générer le ticket.', true);
        return;
      }
    }
    var btn = document.getElementById('caisse-btn-generer');
    if (btn) btn.disabled = true;
    apiCall('generer_ticket', { cart: payload }).then(function (res) {
      if (btn) btn.disabled = false;
      if (!res.ok) {
        flash(res.error || 'Erreur.', true);
        return;
      }
      state.lines = [];
      flash('Ticket généré : ' + (res.numero_ticket || ''));
      if (res.redirect) {
        window.location.href = res.redirect;
      }
    }).catch(function () {
      if (btn) btn.disabled = false;
      flash('Erreur réseau.', true);
    });
  }

  function encaisser(formData) {
    var payload = cartPayload();
    var body = {
      cart: payload,
      mode_paiement: formData.mode_paiement || 'especes',
      montant_recu: formData.montant_recu || '',
      montant_especes: formData.montant_especes || '',
      montant_carte: formData.montant_carte || '',
      montant_orange_money: formData.montant_orange_money || '',
      montant_wave: formData.montant_wave || '',
      notes_vente: formData.notes_vente || ''
    };
    return apiCall('encaisser', body);
  }

  function init(options) {
    cfg = options || {};
    var mount = document.getElementById('caisse-panier-mount');
    if (mount && !document.getElementById('caisse-panier-root')) {
      mount.innerHTML = '<div id="caisse-panier-root"></div>';
    }
    bindRootEvents();
    render();

    var payForm = document.querySelector('.caisse-pay-form');
    if (payForm && !payForm._caisseBound) {
      payForm._caisseBound = true;
      payForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!state.lines.length) {
          flash('Panier vide.', true);
          return;
        }
        var fd = {
          mode_paiement: (document.getElementById('mode_paiement') || {}).value || 'especes',
          montant_recu: (document.getElementById('montant_recu_final') || {}).value || '',
          montant_especes: (document.getElementById('montant_especes') || {}).value || '',
          montant_carte: (document.getElementById('montant_carte') || {}).value || '',
          montant_orange_money: (document.getElementById('montant_orange_money') || {}).value || '',
          montant_wave: (document.getElementById('montant_wave') || {}).value || '',
          notes_vente: (document.getElementById('notes_vente') || {}).value || ''
        };
        var btn = payForm.querySelector('.caisse-btn-valider');
        if (btn) btn.disabled = true;
        encaisser(fd).then(function (res) {
          if (btn) btn.disabled = false;
          if (!res.ok) {
            flash(res.error || 'Erreur encaissement.', true);
            return;
          }
          state.lines = [];
          if (res.redirect) window.location.href = res.redirect;
        }).catch(function () {
          if (btn) btn.disabled = false;
          flash('Erreur réseau.', true);
        });
      });
    }
  }

  global.CaissePanier = {
    init: init,
    addProduct: addProduct,
    addById: addById,
    addFromCatalog: function (p, qty) {
      addProduct({
        id: p.id,
        nom: p.nom,
        ref: p.ref || '',
        prix: p.prix,
        stock: p.stock,
        sans_prix_catalogue: p.prix <= 0
      }, qty || 1);
    },
    resolveAndAdd: function (code, qty) {
      return apiCall('resolve_product', { code: code }).then(function (res) {
        if (!res.ok) {
          flash(res.error || 'Introuvable.', true);
          return res;
        }
        if (res.type === 'ticket' && res.redirect) {
          window.location.href = res.redirect;
          return res;
        }
        if (res.produit) {
          addProduct(res.produit, qty || 1);
        }
        return res;
      });
    },
    getState: function () { return state; },
    render: render
  };
})(window);
