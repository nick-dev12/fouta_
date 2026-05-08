/**
 * Fiche employé — modal d’ajout de document + aperçu fichier (UI uniquement, pas d’envoi AJAX).
 */
(function () {
  'use strict';

  function formatSize(bytes) {
    if (typeof bytes !== 'number' || bytes < 0) {
      return '';
    }
    if (bytes < 1024) {
      return bytes + ' o';
    }
    if (bytes < 1024 * 1024) {
      return (bytes / 1024).toFixed(1) + ' Ko';
    }
    return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
  }

  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('erDetailDocsModal');
    var btnOpen = document.getElementById('erDetailDocsAddBtn');
    var btnClose = document.getElementById('erDetailDocsPanelClose');
    var btnCancel = document.getElementById('erDetailDocsModalCancel');
    var backdrop = document.getElementById('erDetailDocsModalBackdrop');
    var fileInput = document.getElementById('document_fichier');
    var emptyEl = document.getElementById('erDocsPreviewEmpty');
    var imgEl = document.getElementById('erDocsPreviewImg');
    var pdfEl = document.getElementById('erDocsPreviewPdf');
    var metaEl = document.getElementById('erDocsPreviewMeta');
    var fileWrap = document.querySelector('.er-docs-modal__file-wrap');

    if (!modal) {
      return;
    }

    var previewUrl = null;

    function revokePreviewUrl() {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
      }
    }

    function hideMedia() {
      if (imgEl) {
        imgEl.classList.add('is-hidden');
        imgEl.removeAttribute('src');
      }
      if (pdfEl) {
        pdfEl.classList.add('is-hidden');
        pdfEl.removeAttribute('src');
      }
    }

    function showPlaceholder(show) {
      if (!emptyEl) {
        return;
      }
      if (show) {
        emptyEl.classList.remove('is-hidden');
      } else {
        emptyEl.classList.add('is-hidden');
      }
    }

    function restorePlaceholderDefault() {
      if (!emptyEl) {
        return;
      }
      var title = emptyEl.querySelector('.er-docs-modal__preview-placeholder-title');
      var text = emptyEl.querySelector('.er-docs-modal__preview-placeholder-text');
      if (title) {
        title.textContent = 'Aucun fichier sélectionné';
      }
      if (text) {
        text.textContent = 'Choisissez un PDF ou une image pour afficher l’aperçu ici.';
      }
    }

    function resetPreview() {
      restorePlaceholderDefault();
      revokePreviewUrl();
      hideMedia();
      showPlaceholder(true);
      if (metaEl) {
        metaEl.classList.add('is-hidden');
        metaEl.textContent = '';
      }
    }

    function updatePreviewFromFile(file) {
      if (!file) {
        resetPreview();
        return;
      }
      restorePlaceholderDefault();
      revokePreviewUrl();
      hideMedia();

      var type = file.type || '';
      var name = file.name || 'Fichier';
      var sizeStr = formatSize(file.size);

      if (metaEl) {
        metaEl.textContent = name + (sizeStr ? ' · ' + sizeStr : '');
        metaEl.classList.remove('is-hidden');
      }

      if (type.indexOf('image/') === 0 && imgEl) {
        previewUrl = URL.createObjectURL(file);
        imgEl.src = previewUrl;
        imgEl.classList.remove('is-hidden');
        showPlaceholder(false);
        return;
      }

      if (type === 'application/pdf' || /\.pdf$/i.test(name)) {
        previewUrl = URL.createObjectURL(file);
        if (pdfEl) {
          pdfEl.src = previewUrl;
          pdfEl.classList.remove('is-hidden');
        }
        showPlaceholder(false);
        return;
      }

      showPlaceholder(false);
      if (emptyEl) {
        emptyEl.classList.remove('is-hidden');
        var title = emptyEl.querySelector('.er-docs-modal__preview-placeholder-title');
        var text = emptyEl.querySelector('.er-docs-modal__preview-placeholder-text');
        if (title) {
          title.textContent = 'Aperçu non disponible';
        }
        if (text) {
          text.textContent =
            'Ce type de fichier sera bien enregistré, mais seules les images et les PDF s’affichent ici.';
        }
      }
    }

    function openModal() {
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('er-docs-modal-open');
      var nature = document.getElementById('document_nature');
      if (nature) {
        setTimeout(function () {
          nature.focus();
        }, 80);
      }
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('er-docs-modal-open');
      resetPreview();
    }

    if (btnOpen) {
      btnOpen.addEventListener('click', function () {
        openModal();
      });
    }

    if (btnClose) {
      btnClose.addEventListener('click', closeModal);
    }
    if (btnCancel) {
      btnCancel.addEventListener('click', closeModal);
    }
    if (backdrop) {
      backdrop.addEventListener('click', closeModal);
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });

    if (modal.classList.contains('is-open')) {
      document.body.classList.add('er-docs-modal-open');
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        var f = fileInput.files && fileInput.files[0];
        updatePreviewFromFile(f || null);
      });
    }

    if (fileWrap && fileInput) {
      ['dragenter', 'dragover'].forEach(function (ev) {
        fileWrap.addEventListener(ev, function (e) {
          e.preventDefault();
          e.stopPropagation();
          fileWrap.classList.add('is-dragover');
        });
      });
      fileWrap.addEventListener('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        fileWrap.classList.remove('is-dragover');
      });
      fileWrap.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        fileWrap.classList.remove('is-dragover');
        var dt = e.dataTransfer;
        if (!dt || !dt.files || !dt.files.length) {
          return;
        }
        var f = dt.files[0];
        try {
          var ndt = new DataTransfer();
          ndt.items.add(f);
          fileInput.files = ndt.files;
        } catch (err) {
          return;
        }
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }
  });
})();
