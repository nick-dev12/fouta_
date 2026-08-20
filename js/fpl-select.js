/**
 * FPL — Sélecteur avec recherche intelligente intégrée.
 * Transforme TOUS les <select> en listes déroulantes avec champ de recherche :
 * l'utilisateur tape « CA » et voit instantanément « CA15-01 », « CA20-03 »…
 * Le <select> natif reste dans le formulaire (soumission inchangée) ;
 * l'événement « change » est déclenché normalement (les cascades fonctionnent).
 */
(function () {
  function enhance(sel) {
    if (sel._fs || sel.classList.contains('no-search')) return;
    sel._fs = true;

    const wrap = document.createElement('div');
    wrap.className = 'fs-wrap';
    if (sel.dataset.depth !== undefined) wrap.dataset.depth = sel.dataset.depth;
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    sel.style.display = 'none';

    const disp = document.createElement('button');
    disp.type = 'button';
    disp.className = 'fs-display';

    const panel = document.createElement('div');
    panel.className = 'fs-panel';
    const search = document.createElement('input');
    search.type = 'text';
    search.placeholder = 'Rechercher…';
    search.className = 'fs-search';
    search.autocomplete = 'off';
    const list = document.createElement('div');
    list.className = 'fs-list';
    panel.append(search, list);
    wrap.append(disp, panel);

    const currentLabel = () => {
      const o = sel.options[sel.selectedIndex];
      return o ? o.text : '';
    };

    // Pastille de couleur exacte : quand l'option porte data-color, une petite
    // case de sa teinte précède son libellé (liste ET bouton refermé).
    const swatch = (color) => {
      const s = document.createElement('span');
      s.className = 'fs-swatch';
      s.style.background = color;
      return s;
    };

    function renderList(filter) {
      list.innerHTML = '';
      const f = (filter || '').toLowerCase();
      [...sel.options].forEach((o) => {
        // dès qu'on tape, on ne propose que de vrais choix (jamais le « — … — »)
        if (f && (o.value === '' || !o.text.toLowerCase().includes(f))) return;
        const it = document.createElement('div');
        it.className = 'fs-item' + (o.selected ? ' sel' : '');
        if (o.dataset.color) it.appendChild(swatch(o.dataset.color));
        it.appendChild(document.createTextNode(o.text || '—'));
        it.addEventListener('mousedown', (e) => {
          e.preventDefault();
          sel.value = o.value;
          sel.dispatchEvent(new Event('change', { bubbles: true }));
          update();
          close();
        });
        list.appendChild(it);
      });
      if (!list.children.length) {
        const empty = document.createElement('div');
        empty.className = 'fs-empty';
        empty.textContent = 'Aucun résultat';
        list.appendChild(empty);
      }
    }

    function update() {
      const o = sel.options[sel.selectedIndex];
      disp.innerHTML = '';
      if (o && o.dataset.color) disp.appendChild(swatch(o.dataset.color));
      disp.appendChild(document.createTextNode((o && o.text) || '—'));
    }
    function open() {
      document.querySelectorAll('.fs-wrap.open').forEach((w) => w.classList.remove('open'));
      wrap.classList.add('open');
      search.value = '';
      renderList('');
      setTimeout(() => search.focus(), 10);
    }
    function close() { wrap.classList.remove('open'); }

    disp.addEventListener('click', () => (wrap.classList.contains('open') ? close() : open()));
    search.addEventListener('input', () => renderList(search.value));
    search.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const first = list.querySelector('.fs-item');
        if (first) first.dispatchEvent(new Event('mousedown'));
      }
      if (e.key === 'Escape') close();
    });

    sel.addEventListener('change', update);
    update();
  }

  document.addEventListener('click', (e) => {
    document.querySelectorAll('.fs-wrap.open').forEach((w) => {
      if (!w.contains(e.target)) w.classList.remove('open');
    });
  });

  window.fplEnhanceSelect = enhance;
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select').forEach(enhance);
  });
})();
