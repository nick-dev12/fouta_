/**
 * Page édition référentiel entrepôt par étage — UI + impression étiquettes barres.
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
            var scope = document.querySelector('[data-naming-list="' + target + '"]');
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

    document.querySelectorAll('.ee-barre-in-rayon__goto').forEach(function (link) {
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
            var liePanel = target.closest('.ee-panel--lie-barre');
            if (liePanel) {
                var panelToggle = liePanel.querySelector('.ee-panel__toggle');
                var panelBody = liePanel.querySelector('.ee-panel__body');
                if (panelToggle && panelBody) {
                    panelToggle.setAttribute('aria-expanded', 'true');
                    panelBody.hidden = false;
                    liePanel.classList.add('is-open');
                }
            }
            var block = target.closest('.ee-rayon-block');
            if (block) {
                var btn = block.querySelector('.ee-rayon-block__toggle');
                var body = block.querySelector('.ee-rayon-block__barres');
                if (btn && body) {
                    btn.setAttribute('aria-expanded', 'true');
                    body.hidden = false;
                    block.classList.add('is-open');
                }
            }
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.classList.add('is-highlight');
            setTimeout(function () {
                target.classList.remove('is-highlight');
            }, 1800);
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

    function eeEtiqDimsFrom(root) {
        var g = window.EE_ETIQ_DIMS || {};
        function num(attr, key, fallback) {
            var fromAttr = root && root.getAttribute ? root.getAttribute(attr) : null;
            var n = parseFloat(fromAttr);
            if (!isNaN(n) && n > 0) {
                return n;
            }
            n = parseFloat(g[key]);
            if (!isNaN(n) && n > 0) {
                return n;
            }
            return fallback;
        }
        return {
            w: num('data-etiq-w', 'largeur_mm', 90),
            h: num('data-etiq-h', 'hauteur_mm', 40),
            qr: num('data-etiq-qr', 'qr_mm', 30),
            texte: num('data-etiq-texte', 'texte_mm', 11)
        };
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
        var d = eeEtiqDimsFrom(root);
        var mmW = d.w + 'mm';
        var mmH = d.h + 'mm';
        var mmQr = d.qr + 'mm';
        var mmTx = d.texte + 'mm';
        var baseHref = window.EE_BARRE_ETIQ_ORIGIN || (window.location.origin + '/');
        var win = window.open('', '_blank', 'width=480,height=220');
        if (!win || !win.document) {
            return;
        }
        var doc = win.document;
        doc.open();
        doc.write('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">');
        doc.write('<title>Étiquette ' + d.w + '\u00d7' + d.h + ' mm</title>');
        doc.write('<base href="' + String(baseHref).replace(/"/g, '&quot;') + '">');
        doc.write('<style>');
        doc.write(':root{--ee-etiq-w:' + mmW + ';--ee-etiq-h:' + mmH + ';--ee-etiq-qr:' + mmQr + ';--ee-etiq-texte:' + mmTx + '}');
        doc.write('@page{size:' + mmW + ' ' + mmH + ';margin:0}');
        doc.write('*{box-sizing:border-box}');
        doc.write('html,body{margin:0!important;padding:0!important;width:' + mmW + '!important;height:' + mmH + '!important;');
        doc.write('overflow:hidden!important;background:#fff!important;');
        doc.write('-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}');
        doc.write('.ee-barre-etiq{margin:0!important;padding:2.5mm 3.5mm 2.5mm 4mm!important;');
        doc.write('width:' + mmW + '!important;height:' + mmH + '!important;min-width:' + mmW + '!important;max-width:' + mmW + '!important;');
        doc.write('min-height:' + mmH + '!important;max-height:' + mmH + '!important;');
        doc.write('box-shadow:none!important;transform:none!important;border:none!important;');
        doc.write('display:flex!important;align-items:center!important;justify-content:space-between!important;');
        doc.write('gap:3mm!important;background:#ffe600!important}');
        doc.write('.ee-barre-etiq__text{font-size:' + mmTx + '!important;font-weight:800!important;line-height:1!important;');
        doc.write('color:#000!important;white-space:nowrap!important;overflow:hidden!important}');
        doc.write('.ee-barre-etiq__qr-box{flex:0 0 ' + mmQr + '!important;width:' + mmQr + '!important;height:' + mmQr + '!important;');
        doc.write('background:#fff!important;padding:0.6mm!important}');
        doc.write('.ee-barre-etiq__qr{width:100%!important;height:100%!important;display:block!important;object-fit:contain!important}');
        doc.write('@media print{@page{size:' + mmW + ' ' + mmH + ';margin:0}');
        doc.write('html,body{width:' + mmW + '!important;height:' + mmH + '!important;margin:0!important;padding:0!important}}');
        doc.write('</style></head><body></body></html>');
        doc.close();
        doc.body.innerHTML = node.outerHTML;
        var sheet = doc.createElement('link');
        sheet.rel = 'stylesheet';
        sheet.href = cssHref + (cssHref.indexOf('?') >= 0 ? '&' : '?') + 'v=' + Date.now();
        sheet.onload = function () {
            whenImagesReady(doc, function () {
                win.requestAnimationFrame(function () {
                    setTimeout(function () {
                        try {
                            win.focus();
                            win.print();
                        } catch (e) {}
                        try {
                            win.close();
                        } catch (e2) {}
                    }, 180);
                });
            });
        };
        sheet.onerror = function () {
            whenImagesReady(doc, function () {
                try {
                    win.print();
                } catch (e) {}
            });
        };
        doc.head.appendChild(sheet);
    }

    // Délégation (boutons statiques + dynamiques dans la modale drill)
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-barre-print]') : null;
        if (!btn) {
            return;
        }
        var id = btn.getAttribute('data-barre-print');
        if (id) {
            e.preventDefault();
            imprimerEtiquetteBarre(id);
        }
    });

    window.imprimerEtiquetteBarreEntrepot = imprimerEtiquetteBarre;
})();
