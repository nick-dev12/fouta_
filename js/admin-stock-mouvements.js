/**
 * Mouvements de stock — barre de recherche live, filtres et pagination AJAX.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-mv-page]');
  if (!root) {
    return;
  }

  var ajaxUrl = root.getAttribute('data-ajax-url') || 'ajax_mouvements_live.php';
  var searchInput = root.querySelector('[data-mv-search]');
  var categorieSelect = root.querySelector('[data-mv-categorie]');
  var typeSelect = root.querySelector('[data-mv-type]');
  var resetBtn = root.querySelector('[data-mv-reset]');
  var tbody = root.querySelector('[data-mv-tbody]');
  var cardsWrap = root.querySelector('[data-mv-cards]');
  var paginationWrap = root.querySelector('[data-mv-pagination-wrap]');
  var countEl = root.querySelector('[data-mv-count]');
  var countHintEl = root.querySelector('[data-mv-count-hint]');
  var emptyEl = root.querySelector('[data-mv-empty]');
  var tableSection = root.querySelector('[data-mv-table-section]');
  var loadingEl = root.querySelector('[data-mv-loading]');

  var currentPage = parseInt(root.getAttribute('data-initial-page') || '1', 10) || 1;
  var debounceTimer = null;
  var fetchController = null;

  function getTypeValue() {
    return typeSelect ? String(typeSelect.value || '') : '';
  }

  function setLoading(on) {
    if (loadingEl) {
      loadingEl.hidden = !on;
    }
    if (tableSection) {
      tableSection.classList.toggle('is-loading', on);
    }
  }

  function getParams(page) {
    return {
      q: searchInput ? String(searchInput.value || '').trim() : '',
      categorie_id: categorieSelect ? String(categorieSelect.value || '0') : '0',
      type: getTypeValue(),
      page: String(page || 1),
      per_page: root.getAttribute('data-per-page') || '25',
    };
  }

  function buildUrl(page) {
    var params = getParams(page);
    var parts = [];
    Object.keys(params).forEach(function (key) {
      var val = params[key];
      if (key === 'type' && val === '') {
        return;
      }
      if (key === 'categorie_id' && (val === '' || val === '0')) {
        return;
      }
      if (key === 'q' && val === '') {
        return;
      }
      parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
    });
    return ajaxUrl + (parts.length ? '?' + parts.join('&') : '');
  }

  function updateHistory(page) {
    if (!window.history || !window.history.replaceState) {
      return;
    }
    var params = getParams(page);
    var qsParts = [];
    if (params.q) {
      qsParts.push('q=' + encodeURIComponent(params.q));
    }
    if (params.categorie_id && params.categorie_id !== '0') {
      qsParts.push('categorie_id=' + encodeURIComponent(params.categorie_id));
    }
    if (params.type) {
      qsParts.push('type=' + encodeURIComponent(params.type));
    }
    if (page > 1) {
      qsParts.push('page=' + encodeURIComponent(String(page)));
    }
    var qs = qsParts.length ? '?' + qsParts.join('&') : '';
    window.history.replaceState(null, '', window.location.pathname + qs);
  }

  function pluralMouvement(n) {
    return n > 1 ? 'mouvements' : 'mouvement';
  }

  function updateCount(data) {
    var total = data.total || 0;
    var page = data.page || 1;
    var perPage = data.per_page || 25;
    var from = total === 0 ? 0 : (page - 1) * perPage + 1;
    var to = Math.min(page * perPage, total);

    if (countEl) {
      countEl.textContent = String(total);
    }
    if (countHintEl) {
      if (total === 0) {
        countHintEl.textContent = 'Aucun résultat';
      } else {
        countHintEl.textContent =
          from + '–' + to + ' sur ' + total + ' ' + pluralMouvement(total);
      }
    }
  }

  function bindPagination() {
    if (!paginationWrap) {
      return;
    }
    var buttons = paginationWrap.querySelectorAll('[data-mv-page]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function () {
        var p = parseInt(this.getAttribute('data-mv-page') || '1', 10);
        if (p > 0) {
          fetchPage(p);
        }
      });
    }
  }

  function fetchPage(page) {
    if (fetchController) {
      fetchController.abort();
    }
    fetchController = typeof AbortController !== 'undefined' ? new AbortController() : null;
    setLoading(true);

    var opts = { credentials: 'same-origin' };
    if (fetchController) {
      opts.signal = fetchController.signal;
    }

    fetch(buildUrl(page), opts)
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (data.error) {
          return;
        }
        currentPage = data.page || page;
        if (tbody) {
          tbody.innerHTML = data.html_table || '';
        }
        if (cardsWrap) {
          cardsWrap.innerHTML = data.html_cards || '';
        }
        if (paginationWrap) {
          paginationWrap.innerHTML = data.html_pagination || '';
          bindPagination();
        }
        if (emptyEl) {
          emptyEl.hidden = !data.empty;
        }
        if (tableSection) {
          tableSection.hidden = !!data.empty;
        }
        updateCount(data);
        updateHistory(currentPage);
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') {
          return;
        }
      })
      .finally(function () {
        setLoading(false);
      });
  }

  function scheduleFetch(resetPage) {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(function () {
      fetchPage(resetPage ? 1 : currentPage);
    }, 220);
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      scheduleFetch(true);
    });
    searchInput.addEventListener('search', function () {
      fetchPage(1);
    });
  }

  if (categorieSelect) {
    categorieSelect.addEventListener('change', function () {
      fetchPage(1);
    });
  }

  if (typeSelect) {
    typeSelect.addEventListener('change', function () {
      fetchPage(1);
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      if (searchInput) {
        searchInput.value = '';
      }
      if (categorieSelect) {
        categorieSelect.value = '0';
      }
      if (typeSelect) {
        typeSelect.value = '';
      }
      fetchPage(1);
    });
  }

  bindPagination();
})();
