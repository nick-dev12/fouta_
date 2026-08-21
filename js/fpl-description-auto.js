/*
 * fpl-description-auto.js — la description proposée d'après une référence connue.
 *
 * Reprise de FPL natif. Quand on quitte le champ « Référence OEM » ou
 * « Référence fournisseur », on demande au serveur si une pièce portant cette
 * référence existe déjà ; si oui, sa description remplit le champ resté VIDE.
 * Une description déjà saisie n'est jamais écrasée.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var champOem = document.getElementById('reference_oem');
        var champRef = document.getElementById('reference_fournisseur');
        var champDesc = document.getElementById('description');

        if (!champDesc || (!champOem && !champRef)) {
            return;
        }

        var note = document.createElement('small');
        note.className = 'form-hint';
        note.hidden = true;
        champDesc.parentNode.appendChild(note);

        var dernier = '';

        function proposer() {
            var oem = champOem ? champOem.value.trim() : '';
            var ref = champRef ? champRef.value.trim() : '';
            if (oem === '' && ref === '') { return; }

            var cle = oem + '|' + ref;
            if (cle === dernier) { return; }
            dernier = cle;

            // On ne touche jamais à une description déjà écrite.
            if (champDesc.value.trim() !== '') { return; }

            var url = 'ajax_description_auto.php?oem=' + encodeURIComponent(oem)
                    + '&ref=' + encodeURIComponent(ref);

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d || !d.found || !d.description) { return; }
                    if (champDesc.value.trim() !== '') { return; }
                    champDesc.value = d.description;
                    note.textContent = 'Description reprise d’une pièce portant la même référence. Modifiable.';
                    note.hidden = false;
                })
                .catch(function () { /* silencieux : la saisie manuelle reste possible */ });
        }

        if (champOem) { champOem.addEventListener('blur', proposer); }
        if (champRef) { champRef.addEventListener('blur', proposer); }
    });
})();
