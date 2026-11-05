(function () {
  'use strict';
  // Buscador, orden por columna, paginación y resumen sobre la tabla completa que pinta el servidor.
  // Sin este script la tabla se lee entera; con él sólo se ocultan filas (atributo hidden), nunca se borran.
  var collator = typeof Intl !== 'undefined' && Intl.Collator ? new Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' }) : null;
  var uid = 0;

  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, attrs[key]); });
    if (text !== undefined) node.textContent = text;
    return node;
  }

  // Un valor numérico («$1,299.50», «45 %», «-3») se ordena como número; el resto, como texto.
  function number(text) {
    if (!/^[^\p{L}\d-]*-?[\d.,\s]+[^\p{L}\d]*$/u.test(text.trim())) return null;
    var clean = text.replace(/[^\d.,-]/g, '');
    if (/,\d{1,2}$/.test(clean) && clean.indexOf('.') < clean.lastIndexOf(',')) clean = clean.replace(/\./g, '').replace(',', '.');
    else clean = clean.replace(/,/g, '');
    var value = parseFloat(clean);
    return isNaN(value) ? null : value;
  }

  function compare(a, b) {
    var na = number(a), nb = number(b);
    if (na !== null && nb !== null) return na - nb;
    return collator ? collator.compare(a, b) : a.localeCompare(b);
  }

  function setup(wrap) {
    if (wrap.digiTable) return;
    var table = wrap.querySelector('table.digi-table');
    var body = table && table.tBodies[0];
    if (!body) return;
    var id = table.id || (table.id = 'digi-table-' + (++uid));
    var rows = Array.prototype.slice.call(body.rows);
    var state = { query: '', page: 1, size: Math.max(1, parseInt(wrap.getAttribute('data-page-size'), 10) || 10), column: -1, dir: 1 };
    var paginate = wrap.hasAttribute('data-paginate');
    var tools = el('div', { class: 'digi-table__tools' });
    var footer = el('div', { class: 'digi-table__footer' });
    var info = null, pager = null, prev = null, next = null, label = null, empty = null;
    wrap.digiTable = true;

    if (wrap.hasAttribute('data-search')) {
      var input = el('input', { type: 'search', id: id + '-q', 'aria-controls': id, autocomplete: 'off' });
      var search = el('label', { class: 'digi-table__search', for: id + '-q' }, 'Buscar ');
      search.appendChild(input);
      tools.appendChild(search);
      input.addEventListener('input', function () { state.query = input.value.trim().toLowerCase(); state.page = 1; render(); });
    }
    if (wrap.hasAttribute('data-sort') && table.tHead) {
      Array.prototype.forEach.call(table.tHead.rows[0].cells, function (th, column) {
        var button = el('button', { type: 'button', class: 'digi-table__sort' });
        while (th.firstChild) button.appendChild(th.firstChild);
        th.appendChild(button);
        th.setAttribute('aria-sort', 'none');
        button.addEventListener('click', function () {
          state.dir = state.column === column ? -state.dir : 1;
          state.column = column;
          rows.sort(function (a, b) { return state.dir * compare((a.cells[column] || {}).textContent || '', (b.cells[column] || {}).textContent || ''); });
          rows.forEach(function (row) { body.appendChild(row); });
          Array.prototype.forEach.call(table.tHead.rows[0].cells, function (cell, index) {
            cell.setAttribute('aria-sort', index === column ? (state.dir > 0 ? 'ascending' : 'descending') : 'none');
          });
          render();
        });
      });
    }
    if (wrap.hasAttribute('data-info')) {
      info = el('p', { class: 'digi-table__info', 'aria-live': 'polite' });
      footer.appendChild(info);
    }
    if (paginate) {
      pager = el('nav', { class: 'digi-table__pager', 'aria-label': 'Páginas de la tabla' });
      prev = el('button', { type: 'button', 'aria-controls': id }, 'Anterior');
      next = el('button', { type: 'button', 'aria-controls': id }, 'Siguiente');
      label = el('span', { class: 'digi-table__page' });
      pager.appendChild(prev); pager.appendChild(label); pager.appendChild(next);
      footer.appendChild(pager);
      prev.addEventListener('click', function () { state.page--; render(); });
      next.addEventListener('click', function () { state.page++; render(); });
    }
    empty = el('p', { class: 'digi-table__empty', hidden: '' }, 'Ninguna fila coincide con la búsqueda.');
    footer.insertBefore(empty, footer.firstChild);
    if (tools.firstChild) wrap.parentNode.insertBefore(tools, wrap);
    wrap.parentNode.insertBefore(footer, wrap.nextSibling);

    function render() {
      var matching = rows.filter(function (row) { return !state.query || row.textContent.toLowerCase().indexOf(state.query) !== -1; });
      var pages = paginate ? Math.max(1, Math.ceil(matching.length / state.size)) : 1;
      state.page = Math.min(Math.max(1, state.page), pages);
      var first = paginate ? (state.page - 1) * state.size : 0;
      var last = paginate ? Math.min(matching.length, first + state.size) : matching.length;
      rows.forEach(function (row) { row.hidden = true; });
      matching.slice(first, last).forEach(function (row) { row.hidden = false; });
      empty.hidden = matching.length > 0;
      if (info) {
        info.textContent = matching.length ? 'Filas ' + (first + 1) + '–' + last + ' de ' + matching.length + (matching.length < rows.length ? ' (filtradas de ' + rows.length + ')' : '') : '';
      }
      if (pager) {
        pager.hidden = pages < 2;
        label.textContent = 'Página ' + state.page + ' de ' + pages;
        prev.disabled = state.page <= 1;
        next.disabled = state.page >= pages;
      }
    }
    render();
  }

  // Tabla de productos: la cantidad elegida en una fila viaja en el botón de compra de esa misma fila.
  function quantities(wrap) {
    if (wrap.digiQuantity) return;
    wrap.digiQuantity = true;
    wrap.addEventListener('change', function (event) {
      var input = event.target;
      if (!input.matches || !input.matches('input.qty')) return;
      var button = input.closest('tr') && input.closest('tr').querySelector('.add_to_cart_button');
      if (!button) return;
      button.setAttribute('data-quantity', input.value);
      try {
        var url = new URL(button.href, location.href);
        if (url.searchParams.has('add-to-cart')) { url.searchParams.set('quantity', input.value); button.href = url.toString(); }
      } catch (e) { /* enlace sin URL válida: basta data-quantity */ }
    });
  }

  function init(scope) {
    (scope || document).querySelectorAll('[data-digi-table]').forEach(setup);
    (scope || document).querySelectorAll('.digi-products-table').forEach(quantities);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-table.default', function ($scope) { init($scope[0] || document); });
    window.elementorFrontend.hooks.addAction('frontend/element_ready/woocommerce-products.default', function ($scope) { init($scope[0] || document); });
  }
})();
