/**
 * Liste employés — filtrage en direct (affichage uniquement, pas d’envoi serveur).
 */
(function () {
  var input = document.getElementById('er-search-input');
  var grid = document.getElementById('er-employes-grid');
  if (!input || !grid) {
    return;
  }

  var cards = grid.querySelectorAll('.er-card');
  var total = cards.length;
  var countEl = document.getElementById('er-search-preview-count');
  var hintEl = document.getElementById('er-search-preview-hint');
  var noResults = document.getElementById('er-search-no-results');

  function norm(s) {
    s = String(s || '').toLowerCase();
    if (typeof s.normalize === 'function') {
      s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return s;
  }

  function applyFilter() {
    var qRaw = input.value || '';
    var q = norm(qRaw.trim());
    var visible = 0;
    var i;

    for (i = 0; i < cards.length; i++) {
      var card = cards[i];
      var hay = norm(card.getAttribute('data-er-search') || '');
      var show = q === '' || hay.indexOf(q) !== -1;
      card.style.display = show ? '' : 'none';
      card.toggleAttribute('hidden', !show);
      if (show) {
        visible++;
      }
    }

    if (countEl) {
      if (q === '') {
        countEl.textContent =
          total + (total > 1 ? ' employés' : total === 1 ? ' employé' : ' employé');
      } else {
        countEl.textContent =
          visible +
          (visible > 1 ? ' résultats' : visible === 1 ? ' résultat' : ' résultat') +
          ' · ' +
          total +
          ' au total';
      }
    }

    if (hintEl) {
      hintEl.textContent =
        q === ''
          ? 'Filtrez la liste en temps réel'
          : 'Recherche : « ' + qRaw.trim() + ' »';
    }

    if (noResults) {
      noResults.hidden = visible > 0;
    }
  }

  input.addEventListener('input', applyFilter);
  input.addEventListener('search', applyFilter);
  applyFilter();
})();
