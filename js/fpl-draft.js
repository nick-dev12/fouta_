/* ============================================================
   FPL — Sauvegarde au fil de l'eau (brouillons)
   Tout formulaire portant data-draft="une-cle" est sauvegardé en base
   pendant la frappe. Quitter la page et revenir ne fait rien perdre.
   Le brouillon s'efface quand le vrai enregistrement aboutit.
   ============================================================ */
(function () {
  'use strict';

  var form = document.querySelector('form[data-draft]');
  if (!form) return;

  var cle = form.dataset.draft;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content
    || (form.querySelector('input[name="_token"]') || {}).value;
  var urls = window.FPL_DRAFT_URLS || {};
  if (!csrf || !urls.save) return;

  var timer = null;
  var pastille = null;
  var enAttente = false; // une saisie a eu lieu depuis la dernière sauvegarde
  var restauration = false; // la REMISE EN PLACE d'un brouillon n'est pas une saisie

  // La clé porte l'utilisateur côté serveur ; côté page, on marque le
  // formulaire pour que l'enregistrement final sache quoi purger.
  var champCle = document.createElement('input');
  champCle.type = 'hidden';
  champCle.name = '_draft_key';
  champCle.value = cle;
  form.appendChild(champCle);

  function champs() {
    // Tout sauf les fichiers, les jetons et les mots de passe
    var data = {};
    form.querySelectorAll('input, select, textarea').forEach(function (el) {
      if (!el.name || el.type === 'file' || el.type === 'password') return;
      if (el.name === '_token' || el.name === '_method' || el.name === '_draft_key') return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        if (!el.checked) return;
        if (el.name.slice(-2) === '[]') {
          // Cases multiples (ex. models[]) : toutes les cochées, en tableau
          (data[el.name] = data[el.name] || []).push(el.value);
        } else {
          data[el.name] = el.value;
        }
        return;
      }
      data[el.name] = el.value;
    });
    return data;
  }

  function statut(texte) {
    if (!pastille) {
      pastille = document.createElement('div');
      pastille.className = 'draft-status';
      document.body.appendChild(pastille);
    }
    pastille.textContent = texte;
    pastille.classList.add('on');
    clearTimeout(pastille._t);
    pastille._t = setTimeout(function () { pastille.classList.remove('on'); }, 1800);
  }

  function sauver() {
    enAttente = false;
    fetch(urls.save, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ cle: cle, payload: champs() }),
    }).then(function (r) {
      if (r.ok) statut('Brouillon sauvegardé');
    }).catch(function () { enAttente = true; /* hors ligne : la prochaine frappe réessaie */ });
  }

  form.addEventListener('input', function () {
    if (restauration) return; // la restauration rejoue les champs, pas l'utilisateur
    enAttente = true;
    clearTimeout(timer);
    timer = setTimeout(sauver, 700);
  });
  form.addEventListener('change', function () {
    if (restauration) return;
    enAttente = true;
    clearTimeout(timer);
    timer = setTimeout(sauver, 300);
  });

  // ------------------------------------------------------------
  //  Filet de sécurité : on QUITTE la page avant la sauvegarde
  //  différée (clic sur « Marques » juste après avoir choisi) ?
  //  Le brouillon part quand même — la requête survit à la page.
  // ------------------------------------------------------------
  function sauverAvantDepart() {
    if (!enAttente) return;
    enAttente = false;
    clearTimeout(timer);
    try {
      fetch(urls.save, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ cle: cle, payload: champs() }),
        keepalive: true,
      });
    } catch (e) { /* rien de mieux à faire au départ de la page */ }
  }
  window.addEventListener('pagehide', sauverAvantDepart);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') sauverAvantDepart();
  });

  // L'enregistrement RÉEL part : plus rien à sauvegarder en brouillon
  // (sinon la sauvegarde de départ ressusciterait le brouillon purgé)
  form.addEventListener('submit', function () {
    enAttente = false;
    clearTimeout(timer);
  });

  // ------------------------------------------------------------
  //  À l'arrivée : si un brouillon existe, on le remet en place —
  //  SANS RIEN DIRE (les champs déjà remplis parlent d'eux-mêmes).
  // ------------------------------------------------------------
  fetch(urls.show + '?cle=' + encodeURIComponent(cle), { headers: { 'Accept': 'application/json' } })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d || !d.payload) return;

      restauration = true; // remettre le brouillon en place ne re-sauvegarde rien
      Object.keys(d.payload).forEach(function (nom) {
        var el = form.querySelector('[name="' + CSS.escape(nom) + '"]');
        if (!el || el.type === 'file' || el.type === 'password') return;

        if (el.type === 'checkbox' || el.type === 'radio') {
          // Une valeur ou un tableau (cases multiples) : on coche chacune,
          // et le change relance les cascades de la page (résumé, génération)
          (Array.isArray(d.payload[nom]) ? d.payload[nom] : [d.payload[nom]]).forEach(function (v) {
            var cible = form.querySelector('[name="' + CSS.escape(nom) + '"][value="' + CSS.escape(v) + '"]');
            if (cible && !cible.checked) {
              cible.checked = true;
              cible.dispatchEvent(new Event('change', { bubbles: true }));
            }
          });
          return;
        }
        // Ce que l'utilisateur a déjà retapé n'est pas écrasé
        if (el.value === '' || el.value === el.defaultValue) {
          if (el.value !== d.payload[nom]) {
            el.value = d.payload[nom];
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      });
      restauration = false; // les événements rejoués sont synchrones : dès ici, toute frappe est humaine
    })
    .catch(function () { restauration = false; /* pas de brouillon accessible */ });
})();
