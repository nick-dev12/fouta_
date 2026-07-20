/**
 * Page édition référentiel entrepôt par étage — UI uniquement.
 */
(function () {
    'use strict';

    var panels = document.querySelectorAll('.ee-panel--collapsible');
    panels.forEach(function (panel) {
        var toggle = panel.querySelector('.ee-panel__toggle');
        var body = panel.querySelector('.ee-panel__body');
        if (!toggle || !body) {
            return;
        }
        var open = panel.getAttribute('data-panel-open') === 'true';
        if (open) {
            body.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            panel.classList.add('is-open');
        }
        toggle.addEventListener('click', function () {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            body.hidden = expanded;
            panel.classList.toggle('is-open', !expanded);
        });
    });

    document.querySelectorAll('.ee-barre-card--accordion').forEach(function (card) {
        var btn = card.querySelector('.ee-barre-card__toggle');
        var body = card.querySelector('.ee-barre-card__body');
        if (!btn || !body) {
            return;
        }
        btn.addEventListener('click', function () {
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            body.hidden = expanded;
            card.classList.toggle('is-open', !expanded);
        });
    });

    document.querySelectorAll('.ee-search__input').forEach(function (input) {
        input.addEventListener('input', function () {
            var target = input.getAttribute('data-filter-target');
            var q = input.value.trim().toLowerCase();
            var scope = target === 'barres'
                ? document.querySelector('.ee-barres-stack')
                : document.querySelector('[data-naming-list="' + target + '"]');
            if (!scope) {
                return;
            }
            scope.querySelectorAll('[data-filter-text]').forEach(function (row) {
                var text = row.getAttribute('data-filter-text') || '';
                row.hidden = q !== '' && text.indexOf(q) === -1;
            });
        });
    });

    var navLinks = document.querySelectorAll('.ee-etage-nav__link');
    navLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            var href = link.getAttribute('href');
            if (!href || href.charAt(0) !== '#') {
                return;
            }
            var target = document.querySelector(href);
            if (!target) {
                return;
            }
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            navLinks.forEach(function (l) { l.classList.remove('is-active'); });
            link.classList.add('is-active');
            var collapsible = target.closest('.ee-panel--collapsible');
            if (collapsible) {
                var toggle = collapsible.querySelector('.ee-panel__toggle');
                var body = collapsible.querySelector('.ee-panel__body');
                if (toggle && body) {
                    toggle.setAttribute('aria-expanded', 'true');
                    body.hidden = false;
                    collapsible.classList.add('is-open');
                }
            }
        });
    });
})();
