/**
 * ÉDITEUR PHOTO D'UNE PIÈCE (espace photographe).
 * Réordonner (glisser), choisir la principale, retirer, téléverser, COLLER
 * une image (Ctrl+V) réduite à 1200 px, enregistrer par ajax_photo_enregistrer.php,
 * puis rafraîchir l'aperçu du détourage. Aucune dépendance externe.
 */
(function () {
    'use strict';
    var wrap = document.querySelector('.pe-wrap');
    if (!wrap) { return; }

    var pieceId = wrap.getAttribute('data-piece-id');
    var jeton = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var UPLOAD = '../../upload/';

    // état
    var photos = [];  // { rel, url } — photos existantes gardées, dans l'ordre
    try {
        (JSON.parse(wrap.getAttribute('data-photos') || '[]') || []).forEach(function (rel) {
            rel = String(rel || '').replace(/\\/g, '/');
            if (rel) { photos.push({ rel: rel, url: UPLOAD + rel.replace(/^\/+/, '') }); }
        });
    } catch (e) { photos = []; }
    var pending = [];   // { blob, previewUrl } — nouvelles à envoyer
    var dirty = false;

    var elPhotos = document.getElementById('pe-photos');
    var elAttente = document.getElementById('pe-attente');
    var elDz = document.getElementById('pe-dz');
    var elFile = document.getElementById('pe-file');
    var elSave = document.getElementById('pe-save');
    var elMsg = document.getElementById('pe-msg');
    var elDetour = document.getElementById('pe-detour');
    var elEtq = document.getElementById('pe-etq');

    function marquerModifie() { dirty = true; elSave.disabled = false; }
    function message(txt, ok) {
        elMsg.textContent = txt || '';
        elMsg.className = 'pe-msg ' + (ok ? 'ok' : 'ko');
    }

    var dragIndex = null;
    function rendrePhotos() {
        elPhotos.innerHTML = '';
        if (photos.length === 0) {
            var v = document.createElement('div');
            v.className = 'pe-vide';
            v.textContent = 'Aucune photo pour l’instant — ajoutez-en ci-dessous.';
            elPhotos.appendChild(v);
            return;
        }
        photos.forEach(function (p, i) {
            var card = document.createElement('div');
            card.className = 'pe-photo' + (i === 0 ? ' principale' : '');
            card.setAttribute('draggable', 'true');
            card.dataset.index = String(i);

            var img = document.createElement('img');
            img.src = p.url; img.alt = '';
            card.appendChild(img);

            if (i === 0) {
                var b = document.createElement('span');
                b.className = 'pe-badge'; b.textContent = 'Principale';
                card.appendChild(b);
            }
            var act = document.createElement('div');
            act.className = 'pe-actions';
            if (i !== 0) {
                var pr = document.createElement('button');
                pr.type = 'button'; pr.textContent = 'Principale';
                pr.title = 'Définir comme photo principale';
                pr.addEventListener('click', function () {
                    var x = photos.splice(i, 1)[0];
                    photos.unshift(x);
                    marquerModifie(); rendrePhotos();
                });
                act.appendChild(pr);
            }
            var del = document.createElement('button');
            del.type = 'button'; del.className = 'pe-del'; del.textContent = 'Retirer';
            del.addEventListener('click', function () {
                photos.splice(i, 1);
                marquerModifie(); rendrePhotos();
            });
            act.appendChild(del);
            card.appendChild(act);

            card.addEventListener('dragstart', function () { dragIndex = i; card.style.opacity = '.4'; });
            card.addEventListener('dragend', function () { dragIndex = null; card.style.opacity = ''; });
            card.addEventListener('dragover', function (e) { e.preventDefault(); });
            card.addEventListener('drop', function (e) {
                e.preventDefault();
                if (dragIndex === null || dragIndex === i) { return; }
                var moved = photos.splice(dragIndex, 1)[0];
                photos.splice(i, 0, moved);
                marquerModifie(); rendrePhotos();
            });
            elPhotos.appendChild(card);
        });
    }

    function rendreAttente() {
        elAttente.innerHTML = '';
        pending.forEach(function (item, i) {
            var box = document.createElement('div');
            box.className = 'pe-att';
            var img = document.createElement('img');
            img.src = item.previewUrl; box.appendChild(img);
            var x = document.createElement('button');
            x.type = 'button'; x.innerHTML = '&times;'; x.title = 'Retirer';
            x.addEventListener('click', function () { pending.splice(i, 1); rendreAttente(); });
            box.appendChild(x);
            elAttente.appendChild(box);
        });
    }

    // Réduit une image (File/Blob) à 1200 px max, JPEG 0.9 — le geste de l'atelier.
    function reduireEtAjouter(fichier) {
        var lecteur = new FileReader();
        lecteur.onload = function () {
            var im = new Image();
            im.onload = function () {
                var MAX = 1200;
                var e = Math.min(1, MAX / Math.max(im.width, im.height));
                var c = document.createElement('canvas');
                c.width = Math.round(im.width * e);
                c.height = Math.round(im.height * e);
                c.getContext('2d').drawImage(im, 0, 0, c.width, c.height);
                c.toBlob(function (blob) {
                    if (!blob) { return; }
                    pending.push({ blob: blob, previewUrl: URL.createObjectURL(blob) });
                    marquerModifie(); rendreAttente();
                }, 'image/jpeg', 0.9);
            };
            im.src = lecteur.result;
        };
        lecteur.readAsDataURL(fichier);
    }

    // dropzone
    elDz.addEventListener('click', function () { elFile.click(); });
    elFile.addEventListener('change', function () {
        Array.prototype.forEach.call(elFile.files, function (f) {
            if (f.type && f.type.indexOf('image/') === 0) { reduireEtAjouter(f); }
        });
        elFile.value = '';
    });
    ['dragenter', 'dragover'].forEach(function (ev) {
        elDz.addEventListener(ev, function (e) { e.preventDefault(); elDz.classList.add('drag'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        elDz.addEventListener(ev, function (e) { e.preventDefault(); elDz.classList.remove('drag'); });
    });
    elDz.addEventListener('drop', function (e) {
        var dt = e.dataTransfer;
        if (dt && dt.files) {
            Array.prototype.forEach.call(dt.files, function (f) {
                if (f.type && f.type.indexOf('image/') === 0) { reduireEtAjouter(f); }
            });
        }
    });
    // coller (Ctrl+V) une image depuis internet
    window.addEventListener('paste', function (e) {
        var items = (e.clipboardData && e.clipboardData.items) ? e.clipboardData.items : [];
        for (var k = 0; k < items.length; k++) {
            if (items[k].type && items[k].type.indexOf('image/') === 0) {
                e.preventDefault();
                reduireEtAjouter(items[k].getAsFile());
                return;
            }
        }
    });

    // rafraîchir l'aperçu (casse le cache navigateur)
    function rafraichirApercu() {
        var t = Date.now();
        if (elDetour) { elDetour.src = 'detourage-lot-apercu.php?id=' + pieceId + '&t=' + t; }
        if (elEtq) { elEtq.src = 'etiquette-piece-image.php?id=' + pieceId + '&cote=760&t=' + t; }
    }
    var elRefresh = document.getElementById('pe-refresh');
    if (elRefresh) { elRefresh.addEventListener('click', rafraichirApercu); }

    // onglets aperçu
    document.querySelectorAll('.pe-onglets button').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.pe-onglets button').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on');
            var vue = b.getAttribute('data-vue');
            document.getElementById('pe-vue-detour').hidden = (vue !== 'detour');
            document.getElementById('pe-vue-etq').hidden = (vue !== 'etq');
        });
    });

    // enregistrer
    elSave.addEventListener('click', function () {
        if (photos.length === 0 && pending.length === 0) {
            message('Ajoutez au moins une photo.', false);
            return;
        }
        elSave.disabled = true;
        message('Enregistrement…', true);
        var fd = new FormData();
        fd.append('id', pieceId);
        fd.append('_jeton', jeton);
        fd.append('ordre', JSON.stringify(photos.map(function (p) { return p.rel; })));
        pending.forEach(function (item, i) {
            fd.append('images_supplementaires[]', item.blob, 'collee_' + i + '.jpg');
        });
        fetch('ajax_photo_enregistrer.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Réponse invalide.' }; }); })
            .then(function (res) {
                if (!res || !res.ok) {
                    message((res && res.error) ? res.error : 'Échec de l’enregistrement.', false);
                    elSave.disabled = false;
                    return;
                }
                // reconstruire l'état depuis la galerie renvoyée
                photos = (res.photos || []).map(function (p) { return { rel: p.rel, url: p.url }; });
                pending = [];
                dirty = false;
                rendrePhotos(); rendreAttente();
                elSave.disabled = true;
                message('Photos enregistrées.', true);
                rafraichirApercu();
            })
            .catch(function () { message('Réseau indisponible.', false); elSave.disabled = false; });
    });

    rendrePhotos();
    rendreAttente();
})();
