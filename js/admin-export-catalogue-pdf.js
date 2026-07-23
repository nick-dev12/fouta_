/**
 * Export catalogue PDF — suivi persistant (localStorage + polling global admin).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'fouta_export_catalogue_job';
    var POLL_MS = 800;
    var QUEUED_STALL_MS = 4000;
    var pollTimer = null;
    var doneTimer = null;
    var queuedSince = 0;
    var frameId = 'adminPdfDownloadFrame';
    var floaterId = 'exportCataloguePdfFloater';

    function qs(id) {
        return document.getElementById(id);
    }

    function getAdminBase() {
        var path = window.location.pathname || '';
        var m = path.match(/^(.*\/admin\/)/);
        return m ? m[1] : '/admin/';
    }

    function endpoint(name) {
        return getAdminBase() + 'produits/export-catalogue-pdf-' + name + '.php';
    }

    function isExportPage() {
        return document.querySelector('.page-produits-export') !== null;
    }

    function loadStoredJob() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            if (!data || !data.job_id || !data.token) {
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    function saveStoredJob(job) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(job));
        } catch (e) {
            /* quota */
        }
    }

    function clearStoredJob() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            /* ignore */
        }
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

    function ensureFloater() {
        var el = document.getElementById(floaterId);
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = floaterId;
        el.className = 'export-catalogue-floater';
        el.hidden = true;
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.innerHTML = ''
            + '<div class="export-catalogue-floater__inner">'
            + '  <div class="export-catalogue-floater__head">'
            + '    <span class="export-catalogue-floater__title"><i class="fas fa-file-pdf" aria-hidden="true"></i> Export catalogue</span>'
            + '    <span class="export-catalogue-floater__percent" data-export-pct>0 %</span>'
            + '  </div>'
            + '  <p class="export-catalogue-floater__status" data-export-status>En cours…</p>'
            + '  <div class="export-catalogue-floater__track"><div class="export-catalogue-floater__bar" data-export-bar></div></div>'
            + '  <div class="export-catalogue-floater__actions">'
            + '    <button type="button" class="btn-secondary export-catalogue-floater__cancel" data-export-cancel>'
            + '      <i class="fas fa-times" aria-hidden="true"></i> Annuler'
            + '    </button>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(el);
        el.querySelector('[data-export-cancel]').addEventListener('click', onCancelClick);
        return el;
    }

    function setPdfButtonsDisabled(disabled) {
        document.querySelectorAll('[data-export-catalogue-async], [data-export-pdf-trigger]').forEach(function (el) {
            if (disabled) {
                el.setAttribute('aria-disabled', 'true');
                el.classList.add('is-export-disabled');
            } else {
                el.removeAttribute('aria-disabled');
                el.classList.remove('is-export-disabled');
            }
        });
    }

    function showUi(force) {
        if (!force) {
            var stored = loadStoredJob();
            if (!stored || stored.cancelled) {
                hideUi();
                return;
            }
        }

        setPdfButtonsDisabled(true);

        if (isExportPage()) {
            var panel = qs('exportCataloguePdfProgress');
            var actions = qs('exportCataloguePdfHeroActions');
            var cancelBtn = qs('exportCataloguePdfCancel');
            if (panel) {
                panel.hidden = false;
                panel.setAttribute('aria-busy', 'true');
            }
            if (actions) {
                actions.hidden = true;
            }
            if (cancelBtn) {
                cancelBtn.hidden = false;
                cancelBtn.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i> Annuler';
            }
            var floater = document.getElementById(floaterId);
            if (floater) {
                floater.hidden = true;
            }
        } else {
            var floaterEl = ensureFloater();
            floaterEl.hidden = false;
        }
    }

    function hideUi() {
        var panel = qs('exportCataloguePdfProgress');
        var actions = qs('exportCataloguePdfHeroActions');
        if (panel) {
            panel.hidden = true;
            panel.setAttribute('aria-busy', 'false');
        }
        if (actions) {
            actions.hidden = false;
        }
        var floater = document.getElementById(floaterId);
        if (floater) {
            floater.hidden = true;
        }
        setPdfButtonsDisabled(false);
    }

    function setProgress(percent, message) {
        var p = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
        var msg = message || '';

        var bar = qs('exportCataloguePdfBar');
        var pct = qs('exportCataloguePdfPercent');
        var status = qs('exportCataloguePdfStatus');
        if (bar) {
            bar.style.width = p + '%';
        }
        if (pct) {
            pct.textContent = p + ' %';
        }
        if (status && msg) {
            status.textContent = msg;
        }

        var floater = document.getElementById(floaterId);
        if (floater && !floater.hidden) {
            var fBar = floater.querySelector('[data-export-bar]');
            var fPct = floater.querySelector('[data-export-pct]');
            var fStatus = floater.querySelector('[data-export-status]');
            if (fBar) {
                fBar.style.width = p + '%';
            }
            if (fPct) {
                fPct.textContent = p + ' %';
            }
            if (fStatus && msg) {
                fStatus.textContent = msg;
            }
        }
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (doneTimer) {
            clearTimeout(doneTimer);
            doneTimer = null;
        }
    }

    function parseJsonResponse(res) {
        return res.text().then(function (text) {
            var data = null;
            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    data = null;
                }
            }
            if (!data) {
                var snippet = (text || '').replace(/\s+/g, ' ').trim().slice(0, 180);
                throw new Error(snippet !== '' ? snippet : ('Réponse serveur invalide (HTTP ' + res.status + ').'));
            }
            if (!res.ok && data.error) {
                throw new Error(data.error);
            }
            return data;
        });
    }

    function triggerDownload(downloadUrl) {
        ensureFrame().src = downloadUrl;
    }

    function fireRun() {
        var stored = loadStoredJob();
        if (!stored || !stored.job_id || !stored.token || stored.cancelled) {
            return;
        }
        try {
            fetch(endpoint('run'), {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: 'job=' + encodeURIComponent(stored.job_id) + '&token=' + encodeURIComponent(stored.token)
            }).catch(function () {
                /* fire-and-forget : le serveur poursuit via ignore_user_abort */
            });
        } catch (e) {
            /* ignore */
        }
    }

    function finishSuccess(downloadUrl, filename) {
        var stored = loadStoredJob();
        setProgress(100, 'Export terminé — téléchargement du PDF…');

        if (stored && !stored.downloaded && downloadUrl) {
            stored.downloaded = true;
            saveStoredJob(stored);
            triggerDownload(downloadUrl);
        }

        doneTimer = setTimeout(function () {
            stopPolling();
            clearStoredJob();
            hideUi();
        }, 3000);
    }

    function finishError(message) {
        var stored = loadStoredJob();
        if (stored) {
            stored.failed = true;
            saveStoredJob(stored);
        }
        setProgress(0, message || 'Une erreur est survenue.');

        var cancelBtn = qs('exportCataloguePdfCancel');
        if (cancelBtn) {
            cancelBtn.hidden = false;
            cancelBtn.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i> Fermer';
        }

        var floater = document.getElementById(floaterId);
        if (floater && !floater.hidden) {
            var btn = floater.querySelector('[data-export-cancel]');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i> Fermer';
            }
        }

        stopPolling();
    }

    function pollStatus() {
        var stored = loadStoredJob();
        if (!stored || stored.cancelled) {
            stopPolling();
            hideUi();
            return;
        }

        var url = endpoint('status') + '?job=' + encodeURIComponent(stored.job_id)
            + '&token=' + encodeURIComponent(stored.token);

        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(parseJsonResponse)
            .then(function (data) {
                var current = loadStoredJob();
                if (!current || current.cancelled) {
                    return;
                }
                if (!data || !data.ok) {
                    finishError((data && data.error) ? data.error : 'Statut indisponible.');
                    return;
                }

                setProgress(data.progress, data.message);

                if (data.status === 'done' && data.download_url) {
                    queuedSince = 0;
                    stopPolling();
                    finishSuccess(data.download_url, data.filename);
                } else if (data.status === 'failed') {
                    queuedSince = 0;
                    finishError(data.error || data.message || 'Échec de l’export.');
                } else if (data.status === 'cancelled') {
                    queuedSince = 0;
                    clearStoredJob();
                    stopPolling();
                    hideUi();
                } else if (data.status === 'queued') {
                    if (queuedSince === 0) {
                        queuedSince = Date.now();
                    } else if (Date.now() - queuedSince > QUEUED_STALL_MS) {
                        queuedSince = Date.now();
                        fireRun();
                    }
                } else {
                    queuedSince = 0;
                }
            })
            .catch(function () {
                /* réseau temporaire */
            });
    }

    function startPolling() {
        if (pollTimer) {
            return;
        }
        queuedSince = 0;
        showUi();
        pollStatus();
        pollTimer = setInterval(pollStatus, POLL_MS);
    }

    function onCancelClick() {
        var stored = loadStoredJob();
        if (!stored) {
            hideUi();
            return;
        }

        if (stored.failed) {
            clearStoredJob();
            hideUi();
            return;
        }

        stored.cancelled = true;
        saveStoredJob(stored);

        fetch(endpoint('cancel'), {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: 'job=' + encodeURIComponent(stored.job_id) + '&token=' + encodeURIComponent(stored.token)
        }).catch(function () {
            /* ignore */
        });

        stopPolling();
        clearStoredJob();
        hideUi();
    }

    function startAsyncExport(query) {
        var existing = loadStoredJob();
        if (existing && existing.job_id && existing.token && !existing.cancelled && !existing.failed && !existing.downloaded) {
            startPolling();
            return;
        }

        showUi(true);
        setProgress(0, 'Démarrage de l’export…');

        fetch(endpoint('start'), {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: query
        })
            .then(parseJsonResponse)
            .then(function (data) {
                if (!data || !data.ok) {
                    finishError((data && data.error) ? data.error : 'Impossible de démarrer l’export.');
                    return;
                }
                saveStoredJob({
                    job_id: data.job_id,
                    token: data.token,
                    cancelled: false,
                    downloaded: false,
                    failed: false,
                    started_at: Date.now()
                });
                fireRun();
                startPolling();
            })
            .catch(function (err) {
                finishError((err && err.message) ? err.message : 'Erreur réseau lors du démarrage de l’export.');
            });
    }

    function resumeIfNeeded() {
        var stored = loadStoredJob();
        if (!stored || stored.cancelled || stored.failed) {
            if (stored && (stored.cancelled || stored.failed)) {
                clearStoredJob();
            }
            return;
        }
        if (stored.job_id && stored.token) {
            if (!stored.downloaded) {
                fireRun();
            }
            startPolling();
        }
    }

    window.exportCatalogueStartPdf = startAsyncExport;

    window.exportCatalogueDownloadSync = function (query) {
        if (!query) {
            return;
        }
        ensureFrame().src = getAdminBase() + 'produits/export-catalogue-pdf.php?' + query;
    };

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-export-catalogue-async]');
        if (!link || link.classList.contains('is-export-disabled')) {
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
        var cancelBtn = qs('exportCataloguePdfCancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', onCancelClick);
        }

        resumeIfNeeded();

        if (isExportPage() && window.location.search.indexOf('async_pdf=1') !== -1) {
            var params = new URLSearchParams(window.location.search);
            params.delete('async_pdf');
            var q = params.toString();
            if (q !== '') {
                startAsyncExport(q);
            }
        }
    });
})();
