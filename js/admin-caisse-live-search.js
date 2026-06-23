/**
 * Recherche catalogue caisse — filtrage live aligné admin/produits (nom, description, FPL, 5 chiffres…)
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

  function scoreCatalogItem(p, queryRaw) {
    var q = norm(queryRaw);
    if (!q) {
      return { show: true, score: 1 };
    }

    var nom = norm(p.nom_norm || p.nom || '');
    var text = norm(p.search || '');
    var ident = String(p.ref || '').toUpperCase();
    var identRaw = String(p.ref || '');
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

  var elJson = document.getElementById('caisse-catalog-json');
  var box = document.getElementById('caisse-live-results');
  var inputQ = document.getElementById('caisse_q_live');
  var selCat = document.getElementById('caisse_cat_live');
  var selMarque = document.getElementById('caisse_marque_live');
  var selFournisseur = document.getElementById('caisse_fournisseur_live');

  if (!elJson || !box || !inputQ || !selCat) {
    return;
  }

  var marqueFilterOn = box.getAttribute('data-marque-filter') === '1' && selMarque;
  var fournisseurFilterOn = box.getAttribute('data-fournisseur-filter') === '1' && selFournisseur;
  var catalog = [];

  try {
    catalog = JSON.parse(elJson.textContent || '[]');
  } catch (e) {
    catalog = [];
  }

  var csrf = box.getAttribute('data-csrf') || '';
  var hasIdent = box.getAttribute('data-has-ident') === '1';
  var maxLive = 25;
  var placeholderImg = '/image/produit1.jpg';
  var debounceTimer;

  function fmtFcfa(n) {
    var x = Math.round(Number(n));
    return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f');
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function escAttr(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  function filtersOk(p, catVal, marqueVal, fournisseurVal) {
    if (catVal && String(p.cat_id) !== String(catVal)) {
      return false;
    }
    if (marqueVal && String(p.marque_id || '0') !== String(marqueVal)) {
      return false;
    }
    if (fournisseurVal && String(p.fournisseur_id || '0') !== String(fournisseurVal)) {
      return false;
    }
    return true;
  }

  function collectHits(qRaw, catVal, marqueVal, fournisseurVal, cap) {
    cap = cap || maxLive;
    var q = (qRaw || '').trim();
    var hasSelect = catVal !== '' || marqueVal !== '' || fournisseurVal !== '';
    var needFilter = q !== '' || hasSelect;
    var ranked = [];
    var i;

    if (!needFilter) {
      return [];
    }

    for (i = 0; i < catalog.length; i++) {
      var p = catalog[i];
      if (!filtersOk(p, catVal, marqueVal, fournisseurVal)) {
        continue;
      }
      if (q === '') {
        ranked.push({ p: p, score: 1 });
      } else {
        var result = scoreCatalogItem(p, qRaw);
        if (result.show) {
          ranked.push({ p: p, score: result.score });
        }
      }
    }

    ranked.sort(function (a, b) {
      return b.score - a.score;
    });

    var hits = [];
    for (i = 0; i < ranked.length && hits.length < cap; i++) {
      hits.push(ranked[i].p);
    }
    return hits;
  }

  function preferScanResolve(raw) {
    var t = (raw || '').trim();
    if (t === '') {
      return false;
    }
    if (/^tkt/i.test(t)) {
      return true;
    }
    if (/^fpl\d+/i.test(t)) {
      return true;
    }
    if (/^\d{1,12}$/.test(t)) {
      return true;
    }
    return false;
  }

  function buildCardHeading(p) {
    var html =
      '<h3 class="caisse-live-card-nom produit-card-nom"><span class="pcn-nom">' + esc(p.nom) + '</span></h3>';
    var marqueNom = (p.marque_nom || '').trim();
    if (marqueNom) {
      html +=
        '<p class="caisse-live-marque-line produit-card-marque">' +
        '<i class="fas fa-tag" aria-hidden="true"></i> ' +
        esc(marqueNom) +
        '</p>';
    }
    var descEx = (p.desc_preview || p.desc_excerpt || p.desc_short || '').trim();
    if (descEx) {
      html += '<p class="caisse-live-desc-line produit-card-desc">' + esc(descEx) + '</p>';
    }
    return html;
  }

  function buildCardFournisseur(p) {
    var four = (p.fournisseur_nom || '').trim();
    if (!four) {
      return '';
    }
    return (
      '<p class="caisse-live-fournisseur produit-card-fournisseur">' +
      '<i class="fas fa-truck-field" aria-hidden="true"></i> ' +
      esc(four) +
      '</p>'
    );
  }

  function buildCardCategorie(p) {
    var cat = (p.categorie_nom || '').trim() || 'Sans catégorie';
    return (
      '<p class="caisse-live-categorie produit-card-categorie">' +
      '<i class="fas fa-tag" aria-hidden="true"></i> ' +
      esc(cat) +
      '</p>'
    );
  }

  function renderLive() {
    var q = inputQ.value;
    var catVal = selCat.value;
    var marqueVal = marqueFilterOn ? selMarque.value : '';
    var fournisseurVal = fournisseurFilterOn ? selFournisseur.value : '';
    var needFilter =
      q.trim() !== '' || catVal !== '' || marqueVal !== '' || fournisseurVal !== '';

    if (!needFilter) {
      box.innerHTML = '';
      box.hidden = true;
      box.classList.remove('is-empty');
      return;
    }

    var hits = collectHits(q, catVal, marqueVal, fournisseurVal, maxLive);
    if (hits.length === 0) {
      box.innerHTML = '<p class="caisse-live-empty">Aucun produit en stock ne correspond.</p>';
      box.hidden = false;
      box.classList.add('is-empty');
      return;
    }

    var html = '<ul class="caisse-live-list">';
    var i;
    for (i = 0; i < hits.length; i++) {
      var p = hits[i];
      var refThumb = hasIdent && (p.ref || '').trim()
        ? '<div class="caisse-live-ref-thumb" aria-label="Référence FPL">' +
          '<code>' + esc(p.ref) + '</code></div>'
        : '';
      var headingHtml = buildCardHeading(p);
      var fournisseurHtml = buildCardFournisseur(p);
      var categorieHtml = buildCardCategorie(p);
      var refFourn = (p.ref_f || '').trim()
        ? '<span class="caisse-live-ref-fourn">Réf. fourn. <code>' + esc(p.ref_f) + '</code></span>'
        : '';
      var imgs = Array.isArray(p.imgs) ? p.imgs : [];
      var thumbSrc = imgs.length ? imgs[0] : placeholderImg;
      var imgsAttr = escAttr(JSON.stringify(imgs.length ? imgs : [placeholderImg]));
      html +=
        '<li class="caisse-live-item" role="option">' +
        '<form method="post" action="post.php" class="caisse-live-add-form">' +
        '<input type="hidden" name="csrf_token" value="' + esc(csrf) + '">' +
        '<input type="hidden" name="caisse_action" value="add_product">' +
        '<input type="hidden" name="produit_id" value="' + esc(String(p.id)) + '">' +
        '<input type="hidden" name="quantite" value="1">' +
        '<div class="caisse-live-media">' +
        refThumb +
        '<button type="button" class="caisse-live-thumb" data-caisse-gallery="' +
        imgsAttr +
        '" title="Voir les photos">' +
        '<img src="' +
        escAttr(thumbSrc) +
        '" alt="" loading="lazy" width="96" height="96" onerror="this.src=\'' +
        placeholderImg +
        '\'">' +
        '</button>' +
        '</div>' +
        '<button type="submit" class="caisse-live-row-hit">' +
        headingHtml +
        fournisseurHtml +
        categorieHtml +
        refFourn +
        '<span class="caisse-live-meta"><strong>' +
        (p.prix > 0
          ? fmtFcfa(p.prix) + ' FCFA</strong> HT · stock '
          : 'Prix à saisir</strong> · stock ') +
        esc(String(p.stock)) +
        '</span>' +
        '<span class="caisse-live-hint-add">Cliquer pour ajouter au panier</span>' +
        '</button>' +
        '</form></li>';
    }
    if (catalog.length >= 2500 && hits.length >= maxLive) {
      html +=
        '</ul><p class="caisse-live-cap-hint">Affichage limité à ' +
        maxLive +
        ' résultats — affinez la recherche ou utilisez « Liste complète ».</p>';
    } else {
      html += '</ul>';
    }
    box.innerHTML = html;
    box.hidden = false;
    box.classList.remove('is-empty');
  }

  function schedule() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(renderLive, 120);
  }

  inputQ.addEventListener('input', schedule);
  inputQ.addEventListener('search', renderLive);
  inputQ.addEventListener('focus', schedule);
  selCat.addEventListener('change', renderLive);
  if (marqueFilterOn) {
    selMarque.addEventListener('change', renderLive);
  }
  if (fournisseurFilterOn) {
    selFournisseur.addEventListener('change', renderLive);
  }

  var scanForm = document.getElementById('caisse-add-scan-fallback');
  var scanCodeInput = document.getElementById('caisse_add_scan_code');
  inputQ.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') {
      return;
    }
    var raw = inputQ.value.trim();
    var catVal = selCat.value;
    var marqueVal = marqueFilterOn ? selMarque.value : '';
    var fournisseurVal = fournisseurFilterOn ? selFournisseur.value : '';
    var hitsQuick = collectHits(inputQ.value, catVal, marqueVal, fournisseurVal, 2);
    if (hitsQuick.length === 1) {
      ev.preventDefault();
      var quickForm = box.querySelector('.caisse-live-add-form');
      if (quickForm) {
        quickForm.submit();
      }
      return;
    }
    if (preferScanResolve(raw) && scanForm && scanCodeInput) {
      ev.preventDefault();
      scanCodeInput.value = raw;
      scanForm.submit();
    }
  });

  document.addEventListener('click', function (ev) {
    if (!box.hidden && !box.contains(ev.target) && ev.target !== inputQ && !selCat.contains(ev.target)) {
      if (marqueFilterOn && selMarque.contains(ev.target)) {
        return;
      }
      if (fournisseurFilterOn && selFournisseur.contains(ev.target)) {
        return;
      }
      var insideFields = ev.target.closest && ev.target.closest('.caisse-search-fields');
      var noTextQ = inputQ.value.trim() === '';
      var noCat = !selCat.value;
      var noMarque = !marqueFilterOn || !selMarque.value;
      var noFournisseur = !fournisseurFilterOn || !selFournisseur.value;
      if (!insideFields && noTextQ && noCat && noMarque && noFournisseur) {
        box.innerHTML = '';
        box.hidden = true;
        box.classList.remove('is-empty');
      }
    }
  });
})();
