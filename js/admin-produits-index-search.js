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

    function pluralProduit(n) {
      return n > 1 ? 'produits' : 'produit';
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

    function buildQueryParams() {
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
      return params;
    }

    function buildFetchUrl() {
      var params = buildQueryParams();
      var sep = ajaxUrl.indexOf('?') >= 0 ? '&' : '?';
      return ajaxUrl + sep + params.toString();
    }

    function setLiveLoading(loading) {
      if (liveWrap) {
        liveWrap.classList.toggle('page-produits-live-wrap--loading', loading);
      }
    }

    function runLiveSearch() {
      var q = (input.value || '').trim();
      if (q === '') {
        showCatalogView();
        return;
      }

      showLiveView();
      setLiveLoading(true);
      var currentId = ++requestId;

      fetch(buildFetchUrl(), {
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
          setLiveLoading(false);

          var total = parseInt(data.total, 10) || 0;
          var shown = parseInt(data.shown, 10) || 0;
          var html = data.html || '';

          liveGrid.innerHTML = html;

          if (liveEmpty) {
            liveEmpty.hidden = total > 0;
          }

          if (liveMeta) {
            if (total === 0) {
              liveMeta.textContent = '';
              liveMeta.hidden = true;
            } else {
              var msg =
                shown +
                ' ' +
                pluralProduit(shown) +
                ' affiché' +
                (shown > 1 ? 's' : '') +
                ' sur ' +
                total;
              if (data.truncated) {
                msg += ' — affichage limité aux ' + shown + ' premiers résultats';
              }
              liveMeta.textContent = msg;
              liveMeta.hidden = false;
            }
          }

          updateCount(total, 'live');
        })
        .catch(function () {
          if (currentId !== requestId) {
            return;
          }
          setLiveLoading(false);
          liveGrid.innerHTML = '';
          if (liveEmpty) {
            liveEmpty.hidden = false;
          }
          if (liveMeta) {
            liveMeta.textContent = 'Erreur lors de la recherche. Réessayez.';
            liveMeta.hidden = false;
          }
        });
    }

    function scheduleSearch() {
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }
      debounceTimer = setTimeout(runLiveSearch, 200);
    }

    input.addEventListener('input', scheduleSearch);
    input.addEventListener('search', runLiveSearch);

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
        runLiveSearch();
      }
    });

    if ((input.value || '').trim() !== '') {
      runLiveSearch();
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var roots = document.querySelectorAll('[data-produits-index-page]');
    for (var i = 0; i < roots.length; i++) {
      initProduitsIndexPage(roots[i]);
    }
  });
})();
