/**
 * Filtrage produits admin en temps réel (dashboard, liste, catégorie…).
 * Initialise chaque formulaire [data-produits-live-search-form].
 */
(function () {
  'use strict';

  function norm(str) {
    var s = String(str || '').toLowerCase();
    if (typeof s.normalize === 'function') {
      s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return s.replace(/\s+/g, ' ').trim();
  }

  function identLast5(ident) {
    var digits = String(ident || '').replace(/\D/g, '');
    if (digits.length < 5) {
      return '';
    }
    return digits.slice(-5);
  }

  function subsequenceRatio(haystack, needle) {
    if (!needle || !haystack) {
      return 0;
    }
    var hi = 0;
    var matched = 0;
    for (var ni = 0; ni < needle.length; ni++) {
      var ch = needle[ni];
      while (hi < haystack.length) {
        if (haystack.charAt(hi) === ch) {
          matched++;
          hi++;
          break;
        }
        hi++;
      }
    }
    return matched / needle.length;
  }

  function tokenize(query) {
    return norm(query)
      .split(' ')
      .filter(function (t) {
        return t.length > 0;
      });
  }

  function scoreCard(card, queryRaw) {
    var q = norm(queryRaw);
    if (!q) {
      return { show: true, score: 1 };
    }

    var nom = norm(card.getAttribute('data-produit-nom') || '');
    var text = norm(card.getAttribute('data-produit-search') || '');
    var ident = String(card.getAttribute('data-produit-ident') || '').toUpperCase();
    var identRaw = String(card.getAttribute('data-produit-ident') || '');
    var qCompact = q.replace(/\s+/g, '');

    if (/^fpl\d{6}$|^fpl\d{9}$/i.test(qCompact)) {
      var code = qCompact.toUpperCase();
      if (ident === code) {
        return { show: true, score: 10000 };
      }
      return { show: false, score: 0 };
    }

    if (/^\d{5}$/.test(q)) {
      if (identLast5(identRaw) === q) {
        return { show: true, score: 9000 };
      }
      return { show: false, score: 0 };
    }

    var score = 0;
    var tokens = tokenize(q);

    if (nom === q) {
      score += 800;
    } else if (nom.indexOf(q) === 0) {
      score += 600;
    } else if (nom.indexOf(q) !== -1) {
      score += 480;
    }

    if (text.indexOf(q) !== -1) {
      score += 380;
    }

    tokens.forEach(function (token) {
      var len = token.length;
      if (len < 2 && !/^\d+$/.test(token)) {
        return;
      }

      if (nom === token) {
        score += 200;
      } else if (nom.indexOf(token) === 0) {
        score += 160;
      } else if (nom.indexOf(token) !== -1) {
        score += 130;
      } else if (text.indexOf(token) !== -1) {
        score += 85;
      } else {
        var subNom = subsequenceRatio(nom, token);
        var subText = subsequenceRatio(text, token);
        var sub = Math.max(subNom, subText);
        if (sub >= 0.7) {
          score += Math.round(55 * sub);
        } else if (sub >= 0.5) {
          score += Math.round(28 * sub);
        } else if (sub >= 0.35) {
          score += Math.round(12 * sub);
        }
      }
    });

    if (score <= 0 && tokens.length > 0) {
      var partialHits = 0;
      tokens.forEach(function (token) {
        if (token.length < 2 && !/^\d+$/.test(token)) {
          return;
        }
        if (nom.indexOf(token) !== -1 || text.indexOf(token) !== -1) {
          partialHits++;
        } else {
          var sub = Math.max(subsequenceRatio(nom, token), subsequenceRatio(text, token));
          if (sub >= 0.45) {
            partialHits++;
            score += Math.round(8 * sub);
          }
        }
      });
      if (partialHits > 0 && partialHits >= Math.ceil(tokens.length * 0.5)) {
        score = Math.max(score, 15 + partialHits * 5);
      }
    }

    return { show: score > 0, score: score };
  }

  function initLiveSearch(form) {
    var gridId = form.getAttribute('data-live-grid');
    var grid = gridId ? document.getElementById(gridId) : null;
    var input =
      form.querySelector('[data-live-search-input]') ||
      form.querySelector('#recherche') ||
      form.querySelector('input[name="recherche"]');

    if (!grid || !input) {
      return;
    }

    var countId = form.getAttribute('data-live-count');
    var countHintId = form.getAttribute('data-live-count-hint');
    var emptyId = form.getAttribute('data-live-empty');
    var cardSelector =
      form.getAttribute('data-live-card-selector') || '.produit-card.produit-card-linkable';

    var countEl = countId ? document.getElementById(countId) : null;
    var countHintEl = countHintId ? document.getElementById(countHintId) : null;
    var liveEmpty = emptyId ? document.getElementById(emptyId) : null;
    var selectCategorie = form.querySelector('#categorie_id') || document.getElementById('categorie_id');
    var selectMarque = form.querySelector('#marque_id') || document.getElementById('marque_id');
    var selectFournisseur = form.querySelector('#fournisseur_id') || document.getElementById('fournisseur_id');

    var cards = Array.prototype.slice.call(grid.querySelectorAll(cardSelector));
    var totalInDom = parseInt(grid.getAttribute('data-total') || String(cards.length), 10) || cards.length;
    var debounceTimer = null;
    var countHintDefault = countHintEl ? countHintEl.textContent : '';

    function passesSelectFilters(card) {
      if (selectCategorie && selectCategorie.value !== '0') {
        if (String(card.getAttribute('data-categorie-id') || '0') !== selectCategorie.value) {
          return false;
        }
      }
      if (selectMarque && selectMarque.value !== '0') {
        if (String(card.getAttribute('data-marque-id') || '0') !== selectMarque.value) {
          return false;
        }
      }
      if (selectFournisseur && selectFournisseur.value !== '0') {
        if (String(card.getAttribute('data-fournisseur-id') || '0') !== selectFournisseur.value) {
          return false;
        }
      }
      return true;
    }

    function pluralProduit(n) {
      return n > 1 ? 'produits' : 'produit';
    }

    function updateCount(visible, queryRaw) {
      var q = String(queryRaw || '').trim();
      var hasSelectFilter =
        (selectCategorie && selectCategorie.value !== '0') ||
        (selectMarque && selectMarque.value !== '0') ||
        (selectFournisseur && selectFournisseur.value !== '0');
      var filtered = q !== '' || hasSelectFilter;

      if (countEl) {
        if (!filtered) {
          countEl.textContent = '(' + totalInDom + ')';
        } else {
          countEl.textContent = '(' + visible + ' / ' + totalInDom + ')';
        }
      }

      if (countHintEl) {
        if (!filtered) {
          countHintEl.textContent = countHintDefault;
        } else {
          countHintEl.textContent =
            visible +
            ' ' +
            pluralProduit(visible) +
            ' affiché' +
            (visible > 1 ? 's' : '') +
            ' sur ' +
            totalInDom;
        }
      }
    }

    function applyFilters() {
      var queryRaw = input.value || '';
      var ranked = [];
      var visible = 0;
      var i;

      for (i = 0; i < cards.length; i++) {
        var card = cards[i];
        if (!passesSelectFilters(card)) {
          card.classList.add('produit-card--live-hidden');
          card.setAttribute('hidden', 'hidden');
          card.style.order = '';
          continue;
        }

        var result = scoreCard(card, queryRaw);
        if (result.show) {
          ranked.push({ card: card, score: result.score });
          visible++;
        } else {
          card.classList.add('produit-card--live-hidden');
          card.setAttribute('hidden', 'hidden');
          card.style.order = '';
        }
      }

      ranked.sort(function (a, b) {
        return b.score - a.score;
      });

      var fragment = document.createDocumentFragment();
      for (i = 0; i < ranked.length; i++) {
        var item = ranked[i].card;
        item.classList.remove('produit-card--live-hidden');
        item.removeAttribute('hidden');
        item.style.order = String(i);
        fragment.appendChild(item);
      }

      for (i = 0; i < cards.length; i++) {
        if (cards[i].hasAttribute('hidden')) {
          fragment.appendChild(cards[i]);
        }
      }

      grid.appendChild(fragment);

      if (liveEmpty) {
        liveEmpty.hidden = visible > 0;
      }

      updateCount(visible, queryRaw);
    }

    function scheduleFilter() {
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }
      debounceTimer = setTimeout(applyFilters, 120);
    }

    input.addEventListener('input', scheduleFilter);
    input.addEventListener('search', applyFilters);

    if (selectCategorie) {
      selectCategorie.addEventListener('change', applyFilters);
    }
    if (selectMarque) {
      selectMarque.addEventListener('change', applyFilters);
    }
    if (selectFournisseur) {
      selectFournisseur.addEventListener('change', applyFilters);
    }

    applyFilters();
  }

  var forms = document.querySelectorAll('[data-produits-live-search-form]');
  for (var f = 0; f < forms.length; f++) {
    initLiveSearch(forms[f]);
  }
})();
