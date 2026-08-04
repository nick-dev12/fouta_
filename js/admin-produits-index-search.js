/**
 * Catalogue admin : pagination serveur + recherche live AJAX (grille séparée).
 * Initialise chaque bloc [data-produits-index-page] (liste produits, dashboard, catégorie…).
 */
(function () {
  'use strict';

  function elByData(root, key) {
    var id = root.getAttribute('data-id-' + key);
    return id ? document.getElementById(id) : null;
  }

  function initProduitsIndexPage(root) {
    var form = root.querySelector('[data-produits-index-form]');
    var input = root.querySelector('[data-produits-index-search]');
    var mainWrap = elByData(root, 'main-wrap');
    var liveWrap = elByData(root, 'live-wrap');
    var liveGrid = elByData(root, 'live-grid');
    var liveEmpty = elByData(root, 'live-empty');
    var liveMeta = elByData(root, 'live-meta');
    var pagination = elByData(root, 'pagination');
    var countEl = elByData(root, 'count');
    var countHintEl = elByData(root, 'count-hint');
    var catalogEmpty = elByData(root, 'catalog-empty');
    var ajaxUrl = root.getAttribute('data-ajax-url') || 'ajax_live_search.php';
    var ajaxContext = root.getAttribute('data-ajax-context') || '';
    var fixedCategorieId = root.getAttribute('data-fixed-categorie-id') || '';

    if (!form || !input || !liveGrid) {
      return;
    }

    var selectCategorie = form.querySelector('#categorie_id');
    var selectMarque = form.querySelector('#marque_id');
    var selectFournisseur = form.querySelector('#fournisseur_id');
    var hiddenCategorieId = form.querySelector('input[name="id"]');
    var totalCatalog = parseInt(root.getAttribute('data-total-catalog') || '0', 10) || 0;
    var countHintDefault = countHintEl ? countHintEl.textContent : '';
    var debounceTimer = null;
    var requestId = 0;
    var liveActive = false;
    var liveOffset = 0;
    var liveDisplayed = 0;
    var liveTotal = 0;
    var liveLimit = 60;
    var loadMoreBtn = null;
    var loadMoreLoading = false;

    function pluralProduit(n) {
      return n > 1 ? 'produits' : 'produit';
    }

    function ensureLoadMoreBtn() {
      if (loadMoreBtn || !liveWrap) {
        return loadMoreBtn;
      }
      loadMoreBtn = document.createElement('button');
      loadMoreBtn.type = 'button';
      loadMoreBtn.className = 'btn-secondary page-produits-live-load-more';
      loadMoreBtn.hidden = true;
      loadMoreBtn.addEventListener('click', function () {
        runLiveSearch(true);
      });
      if (liveEmpty && liveEmpty.parentNode === liveWrap) {
        liveWrap.insertBefore(loadMoreBtn, liveEmpty);
      } else {
        liveWrap.appendChild(loadMoreBtn);
      }
      return loadMoreBtn;
    }

    function updateLoadMoreButton(hasMore) {
      var btn = ensureLoadMoreBtn();
      if (!btn) {
        return;
      }
      btn.hidden = !hasMore;
      btn.disabled = loadMoreLoading;
      if (hasMore) {
        var remaining = Math.max(0, liveTotal - liveDisplayed);
        var nextBatch = Math.min(remaining, liveLimit);
        btn.innerHTML =
          '<i class="fas fa-chevron-down" aria-hidden="true"></i> Voir plus (' +
          nextBatch +
          ' ' +
          pluralProduit(nextBatch) +
          ')';
      }
    }

    function updateLiveMeta(displayed, total) {
      if (!liveMeta) {
        return;
      }
      if (total === 0) {
        liveMeta.textContent = '';
        liveMeta.hidden = true;
        return;
      }
      liveMeta.textContent =
        displayed +
        ' ' +
        pluralProduit(displayed) +
        ' affiché' +
        (displayed > 1 ? 's' : '') +
        ' sur ' +
        total;
      liveMeta.hidden = false;
    }

    function updateCount(visible, mode) {
      if (countEl) {
        if (mode === 'live') {
          countEl.textContent = '(' + visible + ')';
        } else {
          countEl.textContent = '(' + totalCatalog + ')';
        }
      }
      if (countHintEl) {
        if (mode === 'live') {
          countHintEl.textContent =
            visible +
            ' ' +
            pluralProduit(visible) +
            ' trouvé' +
            (visible > 1 ? 's' : '') +
            ' pour la recherche';
        } else {
          countHintEl.textContent = countHintDefault;
        }
      }
    }

    function showCatalogView() {
      liveActive = false;
      liveOffset = 0;
      liveDisplayed = 0;
      liveTotal = 0;
      if (mainWrap) {
        mainWrap.hidden = totalCatalog === 0;
      }
      if (catalogEmpty) {
        catalogEmpty.hidden = totalCatalog > 0;
      }
      if (liveWrap) {
        liveWrap.hidden = true;
      }
      if (pagination) {
        pagination.hidden = false;
      }
      if (liveMeta) {
        liveMeta.textContent = '';
        liveMeta.hidden = true;
      }
      if (liveEmpty) {
        liveEmpty.hidden = true;
      }
      if (loadMoreBtn) {
        loadMoreBtn.hidden = true;
      }
      liveGrid.innerHTML = '';
      updateCount(totalCatalog, 'catalog');
    }

    function showLiveView() {
      liveActive = true;
      if (mainWrap) {
        mainWrap.hidden = true;
      }
      if (catalogEmpty) {
        catalogEmpty.hidden = true;
      }
      if (liveWrap) {
        liveWrap.hidden = false;
      }
      if (pagination) {
        pagination.hidden = true;
      }
    }

    function buildQueryParams(offset) {
      var params = new URLSearchParams();
      var q = (input.value || '').trim();
      if (q !== '') {
        params.set('q', q);
      }
      if (ajaxContext) {
        params.set('context', ajaxContext);
      }
      if (fixedCategorieId) {
        params.set('categorie_id', fixedCategorieId);
      } else if (selectCategorie && selectCategorie.value !== '0') {
        params.set('categorie_id', selectCategorie.value);
      } else if (hiddenCategorieId && hiddenCategorieId.value) {
        params.set('categorie_id', hiddenCategorieId.value);
      }
      if (selectMarque && selectMarque.value !== '0') {
        params.set('marque_id', selectMarque.value);
      }
      if (selectFournisseur && selectFournisseur.value !== '0') {
        params.set('fournisseur_id', selectFournisseur.value);
      }
      if (typeof offset === 'number' && offset > 0) {
        params.set('offset', String(offset));
      }
      return params;
    }

    function buildFetchUrl(offset) {
      var params = buildQueryParams(offset);
      var sep = ajaxUrl.indexOf('?') >= 0 ? '&' : '?';
      return ajaxUrl + sep + params.toString();
    }

    function setLiveLoading(loading, append) {
      if (liveWrap && !append) {
        liveWrap.classList.toggle('page-produits-live-wrap--loading', loading);
      }
      loadMoreLoading = loading && append;
      if (loadMoreBtn && append) {
        loadMoreBtn.disabled = loading;
      }
    }

    function applyLiveResponse(data, append) {
      var total = parseInt(data.total, 10) || 0;
      var shown = parseInt(data.shown, 10) || 0;
      var html = data.html || '';
      liveLimit = parseInt(data.limit, 10) || liveLimit;
      liveTotal = total;

      if (append) {
        if (html !== '') {
          liveGrid.insertAdjacentHTML('beforeend', html);
        }
        liveDisplayed += shown;
        liveOffset = parseInt(data.next_offset, 10);
        if (isNaN(liveOffset)) {
          liveOffset = liveDisplayed;
        }
      } else {
        liveGrid.innerHTML = html;
        liveDisplayed = parseInt(data.displayed, 10) || shown;
        liveOffset = parseInt(data.next_offset, 10);
        if (isNaN(liveOffset)) {
          liveOffset = liveDisplayed;
        }
      }

      if (liveEmpty) {
        liveEmpty.hidden = total > 0;
      }

      updateLiveMeta(liveDisplayed, liveTotal);
      updateLoadMoreButton(!!data.has_more);
      updateCount(liveTotal, 'live');
    }

    function runLiveSearch(append) {
      var q = (input.value || '').trim();
      if (q === '') {
        showCatalogView();
        return;
      }

      if (!append) {
        liveOffset = 0;
        liveDisplayed = 0;
      }

      showLiveView();
      setLiveLoading(true, append);
      var currentId = ++requestId;
      var fetchOffset = append ? liveOffset : 0;

      fetch(buildFetchUrl(fetchOffset), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (currentId !== requestId) {
            return;
          }
          setLiveLoading(false, append);
          applyLiveResponse(data, append);
        })
        .catch(function () {
          if (currentId !== requestId) {
            return;
          }
          setLiveLoading(false, append);
          if (!append) {
            liveGrid.innerHTML = '';
          }
          if (liveEmpty && !append) {
            liveEmpty.hidden = false;
          }
          if (liveMeta) {
            liveMeta.textContent = append
              ? 'Erreur lors du chargement. Réessayez.'
              : 'Erreur lors de la recherche. Réessayez.';
            liveMeta.hidden = false;
          }
          updateLoadMoreButton(false);
        });
    }

    function scheduleSearch() {
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }
      debounceTimer = setTimeout(function () {
        runLiveSearch(false);
      }, 200);
    }

    input.addEventListener('input', scheduleSearch);
    input.addEventListener('search', function () {
      runLiveSearch(false);
    });

    if (selectCategorie) {
      selectCategorie.addEventListener('change', function () {
        if (liveActive || (input.value || '').trim() !== '') {
          scheduleSearch();
        }
      });
    }
    if (selectMarque) {
      selectMarque.addEventListener('change', function () {
        if (liveActive || (input.value || '').trim() !== '') {
          scheduleSearch();
        }
      });
    }
    if (selectFournisseur) {
      selectFournisseur.addEventListener('change', function () {
        if (liveActive || (input.value || '').trim() !== '') {
          scheduleSearch();
        }
      });
    }

    form.addEventListener('submit', function (ev) {
      var q = (input.value || '').trim();
      if (q !== '') {
        ev.preventDefault();
        runLiveSearch(false);
      }
    });

    if ((input.value || '').trim() !== '') {
      runLiveSearch(false);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var roots = document.querySelectorAll('[data-produits-index-page]');
    for (var i = 0; i < roots.length; i++) {
      initProduitsIndexPage(roots[i]);
    }
  });
})();
