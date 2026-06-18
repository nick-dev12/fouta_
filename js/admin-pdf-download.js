/**
 * Téléchargement PDF admin via iframe cachée (évite ERR_FAILED / boucles Edge).
 */
(function () {
    'use strict';

    var frameId = 'adminPdfDownloadFrame';

    function isAdminPdfHref(href) {
        if (!href) {
            return false;
        }
        return href.indexOf('telecharger-code-pdf.php') !== -1
            || href.indexOf('export-catalogue-pdf.php') !== -1;
    }

    function ensureFrame() {
        var frame = document.getElementById(frameId);
        if (!frame) {
            frame = document.createElement('iframe');
            frame.id = frameId;
            frame.name = frameId;
            frame.setAttribute('aria-hidden', 'true');
            frame.tabIndex = -1;
            frame.title = 'Téléchargement PDF';
            frame.style.cssText = 'position:absolute;width:0;height:0;border:0;visibility:hidden;pointer-events:none';
            document.body.appendChild(frame);
        }
        return frame;
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-admin-pdf-download], a.btn-download-pdf, a.btn-export-pdf-inline');
        if (!link || !link.href) {
            return;
        }
        if (link.hasAttribute('data-export-catalogue-async')) {
            return;
        }
        if (!isAdminPdfHref(link.getAttribute('href') || link.href)) {
            return;
        }
        event.preventDefault();
        ensureFrame().src = link.href;
    });
})();

