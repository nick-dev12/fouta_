/**
 * Page édition référentiel entrepôt par étage — UI + impression étiquettes barres 90×30 mm.
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

    document.querySelectorAll('.ee-rayon-block').forEach(function (block) {
        var btn = block.querySelector('.ee-rayon-block__toggle');
        var body = block.querySelector('.ee-rayon-block__barres');
        if (!btn || !body) {
            return;
        }
        btn.addEventListener('click', function () {
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            body.hidden = expanded;
            block.classList.toggle('is-open', !expanded);
        });
    });

    document.querySelectorAll('.ee-search__input').forEach(function (input) {
        input.addEventListener('input', function () {
            var target = input.getAttribute('data-filter-target');
            var q = input.value.trim().toLowerCase();
            var scope = target === 'rayons'
                ? document.querySelector('.ee-rayons-stack')
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

    function whenImagesReady(doc, cb) {
        var imgs = doc.images;
        var pending = 0;
        var i;
        for (i = 0; i < imgs.length; i++) {
            if (!imgs[i].complete) {
                pending++;
            }
        }
        if (pending === 0) {
            cb();
            return;
        }
        function tick() {
            pending--;
            if (pending <= 0) {
                cb();
            }
        }
        for (i = 0; i < imgs.length; i++) {
            if (!imgs[i].complete) {
                imgs[i].addEventListener('load', tick);
                imgs[i].addEventListener('error', tick);
            }
        }
    }

    function imprimerEtiquetteBarre(barreId) {
        var root = document.getElementById('ee-barre-etiq-root-' + barreId);
        if (!root) {
            return;
        }
        var cssHref = root.getAttribute('data-css-url') || '';
        var node = root.querySelector('[data-barre-etiq]');
        if (!node || !cssHref) {
            return;
        }
        var baseHref = window.EE_BARRE_ETIQ_ORIGIN || (window.location.origin + '/');
        var w = window.open('', '_blank', 'width=480,height=200');
        if (!w || !w.document) {
            return;
        }
        var doc = w.document;
        doc.open();
        doc.write('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Étiquette barre 90×30 mm</title>');
        doc.write('<base href="' + String(baseHref).replace(/"/g, '&quot;') + '">');
        doc.write('<style>');
        doc.write('@page{size:90mm 30mm;margin:0}');
        doc.write('html,body{margin:0;padding:0;width:90mm;height:30mm;overflow:hidden;box-sizing:border-box;background:#fff;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}');
        doc.write('.ee-barre-etiq{margin:0!important;box-shadow:none!important;transform:none!important;width:90mm!important;height:30mm!important;}');
        doc.write('</style></head><body></body></html>');
        doc.close();
        doc.body.innerHTML = node.outerHTML;
        var sheet = doc.createElement('link');
        sheet.rel = 'stylesheet';
        sheet.href = cssHref;
        sheet.onload = function () {
            whenImagesReady(doc, function () {
                w.requestAnimationFrame(function () {
                    setTimeout(function () {
                        try {
                            w.focus();
                            w.print();
                        } catch (e) {}
                        try {
                            w.close();
                        } catch (e2) {}
                    }, 120);
                });
            });
        };
        sheet.onerror = function () {
            whenImagesReady(doc, function () {
                try {
                    w.print();
                } catch (e) {}
            });
        };
        doc.head.appendChild(sheet);
    }

    document.querySelectorAll('[data-barre-print]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-barre-print');
            if (id) {
                imprimerEtiquetteBarre(id);
            }
        });
    });

    window.imprimerEtiquetteBarreEntrepot = imprimerEtiquetteBarre;
})();
