/**
 * Export catalogue PDF en arrière-plan + barre de progression.
 */
(function () {
    'use strict';

    var pollTimer = null;
    var activeJob = null;
    var frameId = 'adminPdfDownloadFrame';

    function qs(id) {
        return document.getElementById(id);
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

    function showModal() {
        var overlay = qs('exportCataloguePdfOverlay');
        var modal = qs('exportCataloguePdfModal');
        if (overlay) {
            overlay.hidden = false;
        }
        if (modal) {
            modal.hidden = false;
        }
    }

    function hideModal() {
        var overlay = qs('exportCataloguePdfOverlay');
        var modal = qs('exportCataloguePdfModal');
        if (overlay) {
            overlay.hidden = true;
        }
        if (modal) {
            modal.hidden = true;
        }
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        activeJob = null;
    }

    function setProgress(percent, message) {
        var bar = qs('exportCataloguePdfBar');
        var pct = qs('exportCataloguePdfPercent');
        var status = qs('exportCataloguePdfStatus');
        var p = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
        if (bar) {
            bar.style.width = p + '%';
        }
        if (pct) {
            pct.textContent = p + ' %';
        }
        if (status && message) {
            status.textContent = message;
        }
    }

    function showDone(downloadUrl, filename) {
        var closeBtn = qs('exportCataloguePdfClose');
        var dl = qs('exportCataloguePdfDownload');
        var title = qs('exportCataloguePdfTitle');
        if (title) {
            title.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i> PDF prêt';
        }
        setProgress(100, 'Export terminé — téléchargement en cours…');
        if (closeBtn) {
            closeBtn.hidden = false;
        }
        if (dl) {
            dl.href = downloadUrl;
            dl.hidden = false;
            if (filename) {
                dl.setAttribute('download', filename);
            }
        }
        ensureFrame().src = downloadUrl;
    }

    function showError(message) {
        var closeBtn = qs('exportCataloguePdfClose');
        var title = qs('exportCataloguePdfTitle');
        if (title) {
            title.innerHTML = '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Export impossible';
        }
        setProgress(0, message || 'Une erreur est survenue.');
        if (closeBtn) {
            closeBtn.hidden = false;
        }
    }

    function pollStatus() {
        if (!activeJob) {
            return;
        }
        var url = 'export-catalogue-pdf-status.php?job=' + encodeURIComponent(activeJob.job_id)
            + '&token=' + encodeURIComponent(activeJob.token);

        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok) {
                    showError((data && data.error) ? data.error : 'Statut indisponible.');
                    if (pollTimer) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                    }
                    return;
                }

                setProgress(data.progress, data.message);

                if (data.status === 'done' && data.download_url) {
                    if (pollTimer) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                    }
                    showDone(data.download_url, data.filename);
                } else if (data.status === 'failed') {
                    if (pollTimer) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                    }
                    showError(data.error || data.message || 'Échec de l’export.');
                }
            })
            .catch(function () {
                /* réseau temporaire — on réessaie au prochain tick */
            });
    }

    function startAsyncExport(query) {
        showModal();
        setProgress(0, 'Démarrage de l’export en arrière-plan…');

        var closeBtn = qs('exportCataloguePdfClose');
        var dl = qs('exportCataloguePdfDownload');
        if (closeBtn) {
            closeBtn.hidden = true;
        }
        if (dl) {
            dl.hidden = true;
        }

        fetch('export-catalogue-pdf-start.php?' + query, {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok) {
                    showError((data && data.error) ? data.error : 'Impossible de démarrer l’export.');
                    return;
                }
                activeJob = {
                    job_id: data.job_id,
                    token: data.token
                };
                setProgress(3, 'Tâche créée — préparation du catalogue…');
                pollStatus();
                pollTimer = setInterval(pollStatus, 1500);
            })
            .catch(function () {
                showError('Erreur réseau lors du démarrage de l’export.');
            });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-export-catalogue-async]');
        if (!link) {
            return;
        }
        event.preventDefault();
        var query = link.getAttribute('data-export-query') || '';
        if (query === '' && link.href) {
            var parts = link.href.split('?');
            query = parts.length > 1 ? parts[1] : '';
        }
        if (query === '') {
            return;
        }
        startAsyncExport(query);
    });

    document.addEventListener('DOMContentLoaded', function () {
        var closeBtn = qs('exportCataloguePdfClose');
        var overlay = qs('exportCataloguePdfOverlay');
        if (closeBtn) {
            closeBtn.addEventListener('click', hideModal);
        }
        if (overlay) {
            overlay.addEventListener('click', hideModal);
        }

        var page = document.querySelector('.page-produits-export');
        if (page && window.location.search.indexOf('async_pdf=1') !== -1) {
            var firstBtn = page.querySelector('[data-export-catalogue-async]');
            if (firstBtn) {
                var q = firstBtn.getAttribute('data-export-query') || '';
                if (q !== '') {
                    startAsyncExport(q);
                }
            }
        }
    });
})();
