/**
 * Suivi catalogue — modals PDF + colonnes tableau + masque dates jj/mm/aaaa.
 */
(function () {
    'use strict';

    var pdfModal = null;
    var pdfOverlay = null;
    var pdfErrorEl = null;
    var pendingPdfQuery = '';

    var tableModal = null;
    var tableOverlay = null;
    var tableErrorEl = null;

    function qs(id) {
        return document.getElementById(id);
    }

    function getPageRoot() {
        return document.querySelector('.page-produits-export');
    }

    function getAdminProduitsBase() {
        var path = window.location.pathname || '';
        if (path.indexOf('export-catalogue.php') !== -1) {
            return path.replace(/export-catalogue\.php.*$/, '');
        }
        var m = path.match(/^(.*\/admin\/produits\/)/);
        return m ? m[1] : '/admin/produits/';
    }

    function parseJsonAttr(el, name, fallback) {
        if (!el) {
            return fallback;
        }
        var raw = el.getAttribute(name) || '';
        if (raw === '') {
            return fallback;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return fallback;
        }
    }

    /* ——— PDF modal ——— */

    function getPdfColumnCheckboxes() {
        if (!pdfModal) {
            return [];
        }
        return Array.prototype.slice.call(pdfModal.querySelectorAll('[data-export-pdf-col]'));
    }

    function selectedPdfColumns() {
        var cols = [];
        getPdfColumnCheckboxes().forEach(function (cb) {
            if (cb.checked || cb.disabled) {
                cols.push(cb.value);
            }
        });
        return cols;
    }

    function syncPdfCheckboxesFromVisible(visibleCols) {
        var lookup = {};
        visibleCols.forEach(function (key) { lookup[key] = true; });
        getPdfColumnCheckboxes().forEach(function (cb) {
            if (cb.disabled) {
                cb.checked = true;
                return;
            }
            cb.checked = !!lookup[cb.value];
        });
    }

    function setAllPdfColumns(checked) {
        getPdfColumnCheckboxes().forEach(function (cb) {
            if (cb.disabled) {
                cb.checked = true;
                return;
            }
            cb.checked = checked;
        });
    }

    function showPdfError(show) {
        if (pdfErrorEl) {
            pdfErrorEl.hidden = !show;
        }
    }

    function openPdfModal(baseQuery) {
        if (!pdfModal || !pdfOverlay) {
            return;
        }
        pendingPdfQuery = baseQuery || '';
        showPdfError(false);
        var root = getPageRoot();
        var visible = parseJsonAttr(root, 'data-suivi-visible-cols', null);
        if (visible && visible.length) {
            syncPdfCheckboxesFromVisible(visible);
        } else {
            setAllPdfColumns(true);
        }
        pdfOverlay.hidden = false;
        pdfModal.hidden = false;
        document.body.classList.add('export-catalogue-modal-open');
        var first = pdfModal.querySelector('[data-export-pdf-col]');
        if (first) {
            first.focus();
        }
    }

    function closePdfModal() {
        if (!pdfModal || !pdfOverlay) {
            return;
        }
        pdfOverlay.hidden = true;
        pdfModal.hidden = true;
        if (!tableModal || tableModal.hidden) {
            document.body.classList.remove('export-catalogue-modal-open');
        }
        pendingPdfQuery = '';
        showPdfError(false);
    }

    function buildQueryWithPdfColumns(baseQuery, columns) {
        var params = new URLSearchParams(baseQuery);
        params.delete('pdf_cols');
        params.delete('pdf_cols[]');
        params.delete('async_pdf');
        columns.forEach(function (col) {
            params.append('pdf_cols[]', col);
        });
        return params.toString();
    }

    function useAsyncExport() {
        var root = getPageRoot();
        return root && root.getAttribute('data-export-use-async') === '1';
    }

    function startPdfExport(query) {
        if (useAsyncExport() && typeof window.exportCatalogueStartPdf === 'function') {
            window.exportCatalogueStartPdf(query);
            return;
        }
        if (typeof window.exportCatalogueDownloadSync === 'function') {
            window.exportCatalogueDownloadSync(query);
            return;
        }
        window.location.href = getAdminProduitsBase() + 'export-catalogue-pdf.php?' + query;
    }

    function onPdfConfirmClick() {
        var cols = selectedPdfColumns();
        if (cols.length === 0) {
            showPdfError(true);
            return;
        }
        if (pendingPdfQuery === '') {
            closePdfModal();
            return;
        }
        closePdfModal();
        startPdfExport(buildQueryWithPdfColumns(pendingPdfQuery, cols));
    }

    function bindPdfModal() {
        pdfModal = qs('exportCataloguePdfModal');
        pdfOverlay = qs('exportCataloguePdfModalOverlay');
        pdfErrorEl = qs('exportCataloguePdfModalError');
        if (!pdfModal || !pdfOverlay) {
            return;
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-export-pdf-trigger]');
            if (!trigger || trigger.classList.contains('is-export-disabled')) {
                return;
            }
            event.preventDefault();
            openPdfModal(trigger.getAttribute('data-export-query') || '');
        });

        var closeBtn = qs('exportCataloguePdfModalClose');
        var cancelBtn = qs('exportCataloguePdfModalCancel');
        var confirmBtn = qs('exportCataloguePdfModalConfirm');
        var selectAllBtn = qs('exportCataloguePdfSelectAll');
        var selectNoneBtn = qs('exportCataloguePdfSelectNone');

        if (closeBtn) closeBtn.addEventListener('click', closePdfModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closePdfModal);
        if (confirmBtn) confirmBtn.addEventListener('click', onPdfConfirmClick);
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                setAllPdfColumns(true);
                showPdfError(false);
            });
        }
        if (selectNoneBtn) selectNoneBtn.addEventListener('click', function () {
            getPdfColumnCheckboxes().forEach(function (cb) {
                if (!cb.disabled) {
                    cb.checked = false;
                }
            });
        });
        pdfOverlay.addEventListener('click', closePdfModal);
    }

    /* ——— Table columns modal ——— */

    function getTableColumnCheckboxes() {
        if (!tableModal) {
            return [];
        }
        return Array.prototype.slice.call(tableModal.querySelectorAll('[data-suivi-table-col]'));
    }

    function selectedTableColumns() {
        var cols = [];
        getTableColumnCheckboxes().forEach(function (cb) {
            if (cb.checked || cb.disabled) {
                cols.push(cb.value);
            }
        });
        return cols;
    }

    function syncTableCheckboxesFromVisible(visibleCols) {
        var lookup = {};
        visibleCols.forEach(function (key) { lookup[key] = true; });
        getTableColumnCheckboxes().forEach(function (cb) {
            if (cb.disabled) {
                cb.checked = true;
                return;
            }
            cb.checked = !!lookup[cb.value];
        });
    }

    function showTableError(show) {
        if (tableErrorEl) {
            tableErrorEl.hidden = !show;
        }
    }

    function openTableModal() {
        var root = getPageRoot();
        if (!tableModal || !tableOverlay || !root) {
            return;
        }
        var visible = parseJsonAttr(root, 'data-suivi-visible-cols', []);
        syncTableCheckboxesFromVisible(visible);
        showTableError(false);
        tableOverlay.hidden = false;
        tableModal.hidden = false;
        document.body.classList.add('export-catalogue-modal-open');
        var first = tableModal.querySelector('[data-suivi-table-col]:not([disabled])');
        if (first) {
            first.focus();
        }
    }

    function closeTableModal() {
        if (!tableModal || !tableOverlay) {
            return;
        }
        tableOverlay.hidden = true;
        tableModal.hidden = true;
        if (!pdfModal || pdfModal.hidden) {
            document.body.classList.remove('export-catalogue-modal-open');
        }
        showTableError(false);
    }

    function applyTableColumnVisibility(cols) {
        var root = getPageRoot();
        var lookup = {};
        cols.forEach(function (key) { lookup[key] = true; });

        document.querySelectorAll('[data-suivi-col]').forEach(function (el) {
            var key = el.getAttribute('data-suivi-col');
            var hidden = !lookup[key];
            el.classList.toggle('is-suivi-col-hidden', hidden);
            if (el.tagName === 'COL') {
                if (hidden) {
                    el.setAttribute('style', 'width:0;min-width:0;max-width:0;padding:0;border:0');
                } else if (key === 'img') {
                    el.setAttribute('style', 'width:56px;max-width:100px');
                } else {
                    el.removeAttribute('style');
                }
            }
        });

        var showIdent = !!lookup.identifiant;
        var showMarque = !!lookup.marque;
        document.querySelectorAll('.is-suivi-nom-meta-ident').forEach(function (el) {
            el.classList.toggle('is-suivi-nom-meta-hidden', showIdent);
        });
        document.querySelectorAll('.is-suivi-nom-meta-marque').forEach(function (el) {
            el.classList.toggle('is-suivi-nom-meta-hidden', showMarque);
        });

        if (root) {
            root.setAttribute('data-suivi-visible-cols', JSON.stringify(cols));
        }
    }

    function showTableSaveToast(message) {
        var root = getPageRoot();
        if (!root) {
            return;
        }
        var existing = root.querySelector('.page-produits-export-toast');
        if (existing) {
            existing.remove();
        }
        var toast = document.createElement('div');
        toast.className = 'message success page-produits-flash page-produits-export-toast';
        toast.setAttribute('role', 'status');
        toast.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i> ' + message;
        var hero = root.querySelector('.dashboard-hero-text');
        if (hero) {
            hero.insertBefore(toast, hero.querySelector('.page-produits-hero__actions'));
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 3500);
        }
    }

    function saveTableColumns(cols) {
        var root = getPageRoot();
        var csrf = root ? (root.getAttribute('data-suivi-csrf') || '') : '';
        var body = new URLSearchParams();
        body.set('csrf_token', csrf);
        cols.forEach(function (col) {
            body.append('cols[]', col);
        });

        return fetch(getAdminProduitsBase() + 'export-catalogue-colonnes-save.php', {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = null;
                try { data = JSON.parse(text); } catch (e) { data = null; }
                if (!data || !data.ok) {
                    throw new Error((data && data.error) ? data.error : 'Enregistrement impossible.');
                }
                return data;
            });
        });
    }

    function onTableConfirmClick() {
        var cols = selectedTableColumns();
        if (cols.length === 0) {
            showTableError(true);
            return;
        }
        var confirmBtn = qs('exportCatalogueTableModalConfirm');
        if (confirmBtn) {
            confirmBtn.disabled = true;
        }
        saveTableColumns(cols)
            .then(function (data) {
                var saved = data.colonnes && data.colonnes.length ? data.colonnes : cols;
                applyTableColumnVisibility(saved);
                closeTableModal();
                showTableSaveToast(data.message || 'Colonnes enregistrées.');
            })
            .catch(function (err) {
                showTableError(true);
                if (tableErrorEl) {
                    tableErrorEl.textContent = (err && err.message) ? err.message : 'Enregistrement impossible.';
                }
            })
            .finally(function () {
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                }
            });
    }

    function bindTableModal() {
        tableModal = qs('exportCatalogueTableModal');
        tableOverlay = qs('exportCatalogueTableModalOverlay');
        tableErrorEl = qs('exportCatalogueTableModalError');
        if (!tableModal || !tableOverlay) {
            return;
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-suivi-table-options-trigger]');
            if (!trigger) {
                return;
            }
            event.preventDefault();
            openTableModal();
        });

        var closeBtn = qs('exportCatalogueTableModalClose');
        var cancelBtn = qs('exportCatalogueTableModalCancel');
        var confirmBtn = qs('exportCatalogueTableModalConfirm');
        var selectAllBtn = qs('exportCatalogueTableSelectAll');
        var selectNoneBtn = qs('exportCatalogueTableSelectNone');

        if (closeBtn) closeBtn.addEventListener('click', closeTableModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeTableModal);
        if (confirmBtn) confirmBtn.addEventListener('click', onTableConfirmClick);
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                getTableColumnCheckboxes().forEach(function (cb) { cb.checked = true; });
                showTableError(false);
            });
        }
        if (selectNoneBtn) {
            selectNoneBtn.addEventListener('click', function () {
                getTableColumnCheckboxes().forEach(function (cb) {
                    if (!cb.disabled) {
                        cb.checked = false;
                    }
                });
            });
        }
        tableOverlay.addEventListener('click', closeTableModal);
    }

    function bindEscapeClose() {
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            if (tableModal && !tableModal.hidden) {
                closeTableModal();
            } else if (pdfModal && !pdfModal.hidden) {
                closePdfModal();
            }
        });
    }

    function formatDateInputValue(raw) {
        var digits = String(raw || '').replace(/\D/g, '').slice(0, 8);
        if (digits.length <= 2) return digits;
        if (digits.length <= 4) return digits.slice(0, 2) + '/' + digits.slice(2);
        return digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4);
    }

    function bindDateMasks() {
        document.querySelectorAll('.page-produits-export-date').forEach(function (input) {
            input.addEventListener('input', function () {
                var start = input.selectionStart;
                var before = input.value;
                input.value = formatDateInputValue(input.value);
                if (document.activeElement === input && start !== null) {
                    var diff = input.value.length - before.length;
                    input.setSelectionRange(start + diff, start + diff);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!getPageRoot()) {
            return;
        }
        bindPdfModal();
        bindTableModal();
        bindEscapeClose();
        bindDateMasks();
    });
})();
