/**
 * Export catalogue PDF en arrière-plan — barre de progression dans l’en-tête.
 */
(function () {
    'use strict';

    var pollTimer = null;
    var doneTimer = null;
    var activeJob = null;
    var frameId = 'adminPdfDownloadFrame';
    var exportRunning = false;

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

    function setPdfButtonsDisabled(disabled) {
        document.querySelectorAll('[data-export-catalogue-async]').forEach(function (el) {
            if (disabled) {
                el.setAttribute('aria-disabled', 'true');
                el.classList.add('is-export-disabled');
            } else {
                el.removeAttribute('aria-disabled');
                el.classList.remove('is-export-disabled');
            }
        });
    }

    function showProgressPanel() {
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
        setPdfButtonsDisabled(true);
        exportRunning = true;
    }

    function hideProgressPanel() {
        var panel = qs('exportCataloguePdfProgress');
        var actions = qs('exportCataloguePdfHeroActions');
        if (panel) {
            panel.hidden = true;
            panel.setAttribute('aria-busy', 'false');
        }
        if (actions) {
            actions.hidden = false;
        }
        setPdfButtonsDisabled(false);
        exportRunning = false;
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
        activeJob = null;
    }

    function cancelExport() {
        stopPolling();
        hideProgressPanel();
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
        var cancelBtn = qs('exportCataloguePdfCancel');
        setProgress(100, 'Export terminé — téléchargement du PDF…');
        if (cancelBtn) {
            cancelBtn.hidden = true;
        }
        ensureFrame().src = downloadUrl;

        doneTimer = setTimeout(function () {
            stopPolling();
            hideProgressPanel();
        }, 2500);
    }

    function showError(message) {
        var cancelBtn = qs('exportCataloguePdfCancel');
        setProgress(0, message || 'Une erreur est survenue.');
        if (cancelBtn) {
            cancelBtn.hidden = false;
            cancelBtn.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i> Fermer';
        }
        stopPolling();
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

    function pollStatus() {
        if (!activeJob) {
            return;
        }
        var url = 'export-catalogue-pdf-status.php?job=' + encodeURIComponent(activeJob.job_id)
            + '&token=' + encodeURIComponent(activeJob.token);

        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(parseJsonResponse)
            .then(function (data) {
                if (!activeJob) {
                    return;
                }
                if (!data || !data.ok) {
                    showError((data && data.error) ? data.error : 'Statut indisponible.');
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
                    showError(data.error || data.message || 'Échec de l’export.');
                }
            })
            .catch(function () {
                /* réseau temporaire — on réessaie au prochain tick */
            });
    }

    function startAsyncExport(query) {
        if (exportRunning) {
            return;
        }

        showProgressPanel();
        setProgress(0, 'Démarrage de l’export…');

        fetch('export-catalogue-pdf-start.php', {
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
                if (!exportRunning) {
                    return;
                }
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
            .catch(function (err) {
                showError((err && err.message) ? err.message : 'Erreur réseau lors du démarrage de l’export.');
            });
    }

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
            cancelBtn.addEventListener('click', function () {
                if (exportRunning && activeJob) {
                    cancelExport();
                    return;
                }
                hideProgressPanel();
            });
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
