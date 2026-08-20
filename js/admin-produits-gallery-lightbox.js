/**
 * Galerie plein écran — vignettes du tableau produits admin.
 * Réutilise les styles .lb de fpl.css.
 */
(function () {
  'use strict';

  var overlay = null;
  var visuel = null;
  var legende = null;
  var compteur = null;
  var urls = [];
  var index = 0;

  function construire() {
    if (overlay) {
      return;
    }

    overlay = document.createElement('div');
    overlay.className = 'lb page-produits-gallery-lb';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Galerie photos produit');
    overlay.innerHTML =
      '<button type="button" class="lb-close" title="Fermer (Échap)" aria-label="Fermer">&times;</button>' +
      '<button type="button" class="lb-nav lb-prev" title="Image précédente" aria-label="Image précédente">&#8249;</button>' +
      '<figure class="lb-stage"><img alt=""><figcaption></figcaption></figure>' +
      '<button type="button" class="lb-nav lb-next" title="Image suivante" aria-label="Image suivante">&#8250;</button>' +
      '<div class="lb-count"></div>';

    document.body.appendChild(overlay);
    visuel = overlay.querySelector('.lb-stage img');
    legende = overlay.querySelector('figcaption');
    compteur = overlay.querySelector('.lb-count');

    overlay.querySelector('.lb-close').addEventListener('click', fermer);
    overlay.querySelector('.lb-prev').addEventListener('click', function (e) {
      e.stopPropagation();
      deplacer(-1);
    });
    overlay.querySelector('.lb-next').addEventListener('click', function (e) {
      e.stopPropagation();
      deplacer(1);
    });
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay || !e.target.closest('.lb-stage, .lb-nav, .lb-close')) {
        fermer();
      }
    });
    visuel.addEventListener('click', function (e) {
      e.stopPropagation();
    });
  }

  function afficher() {
    if (!urls.length) {
      return;
    }
    visuel.src = urls[index];
    legende.textContent = legende.dataset.nom || '';
    legende.hidden = !legende.textContent;
    compteur.textContent = urls.length > 1 ? index + 1 + ' / ' + urls.length : '';
    overlay.classList.toggle('multi', urls.length > 1);
  }

  function deplacer(pas) {
    if (urls.length < 2) {
      return;
    }
    index = (index + pas + urls.length) % urls.length;
    afficher();
  }

  function ouvrir(liste, startIndex, nom) {
    urls = liste.filter(function (u) {
      return typeof u === 'string' && u !== '';
    });
    if (!urls.length) {
      return;
    }
    index = Math.max(0, Math.min(startIndex || 0, urls.length - 1));
    construire();
    legende.dataset.nom = nom || '';
    afficher();
    overlay.classList.add('open');
    document.body.classList.add('lb-locked');
    overlay.querySelector('.lb-close').focus();
  }

  function fermer() {
    if (!overlay) {
      return;
    }
    overlay.classList.remove('open');
    document.body.classList.remove('lb-locked');
    visuel.removeAttribute('src');
    urls = [];
    index = 0;
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.page-produits-table__thumb-btn');
    if (!btn) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    var raw = btn.getAttribute('data-produit-gallery') || '[]';
    var liste = [];
    try {
      liste = JSON.parse(raw);
    } catch (err) {
      liste = [];
    }
    ouvrir(liste, 0, btn.getAttribute('data-produit-nom') || '');
  });

  document.addEventListener('keydown', function (e) {
    if (!overlay || !overlay.classList.contains('open')) {
      return;
    }
    if (e.key === 'Escape') {
      fermer();
    } else if (e.key === 'ArrowLeft') {
      deplacer(-1);
    } else if (e.key === 'ArrowRight') {
      deplacer(1);
    }
  });

  window.adminProduitsGalleryLightbox = { ouvrir: ouvrir, fermer: fermer };
})();
