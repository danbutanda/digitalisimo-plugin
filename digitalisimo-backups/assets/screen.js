/* Pantalla de Backups («interfaz simple», 1.0.74). «Restaurar» abre y cierra su panel bajo la fila sin recargar (sin
   JavaScript, el enlace lleva ?restore=… y PHP pinta el panel abierto) y «Destino» del botón dice el elegido. Todo se
   pinta como texto: nada de HTML. */
(function () {
  'use strict';
  function toggle(link, row, open) {
    row.hidden = !open;
    if (link) link.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      var input = row.querySelector('input[name="confirmation"]');
      if (input && input.focus) input.focus();
    } else if (link && link.focus) link.focus();
  }
  document.addEventListener('click', function (event) {
    var target = event.target && event.target.closest ? event.target.closest('[data-restore-toggle], [data-restore-cancel]') : null;
    if (!target) return;
    var cancel = target.getAttribute('data-restore-cancel');
    var id = cancel || target.getAttribute('data-restore-toggle');
    var row = id ? document.getElementById(id) : null;
    if (!row) return;
    event.preventDefault();
    var link = document.querySelector('[data-restore-toggle="' + id + '"]');
    toggle(link, row, cancel ? false : row.hidden);
  });
  /* «Qué restaurar» (restaurar por partes): «Todo» marca o desmarca las partes; una parte entera y sus concretos se
     excluyen; el resumen y los avisos de coherencia (data-any / data-none / data-missing) siguen a lo marcado. Todo como
     texto. Sin JavaScript se ven todos los avisos y manda «Todo». */
  function each(root, selector, fn) { Array.prototype.forEach.call(root.querySelectorAll(selector), fn); }
  function words(text) { return (text || '').split(/\s+/).filter(function (w) { return w; }); }
  function partsRefresh(box) {
    var all = box.querySelector('[data-restore-all]');
    var on = {};
    var names = [];
    each(box, '[data-restore-part]', function (cb) {
      if (!cb.checked) return;
      on[cb.value] = true;
      var label = cb.closest ? cb.closest('label') : null;
      names.push(label ? label.textContent.trim().toLowerCase() : cb.value);
    });
    each(box, '[data-restore-item]', function (cb) {
      if (!cb.checked) return;
      on[cb.getAttribute('data-restore-item')] = true;
      var label = cb.closest ? cb.closest('label') : null;
      names.push((label ? label.textContent.trim() : cb.value));
    });
    var whole = all && all.checked;
    var label = box.querySelector('[data-restore-parts-label]');
    if (label) label.textContent = whole ? 'todo' : (names.length ? names.join(', ') : 'nada marcado');
    each(box, '[data-coherence]', function (li) {
      var any = words(li.getAttribute('data-any')).some(function (k) { return on[k]; });
      var none = words(li.getAttribute('data-none')).every(function (k) { return !on[k]; });
      var missing = words(li.getAttribute('data-missing'));
      var lacks = !missing.length || missing.some(function (k) { return !on[k]; });
      li.hidden = whole || !(any && none && lacks);
    });
    var form = box.closest ? box.closest('form') : null;
    var summary = form ? form.querySelector('[data-restore-parts-summary]') : null;
    if (summary) {
      summary.hidden = whole;
      summary.textContent = whole ? '' : (names.length ? 'Se restaura sólo: ' + names.join(', ') + '. Lo demás no se toca.' : 'Marca al menos una parte o «Todo».');
    }
  }
  document.addEventListener('change', function (event) {
    var cb = event.target;
    var box = cb && cb.closest ? cb.closest('[data-restore-parts]') : null;
    if (!box) return;
    var all = box.querySelector('[data-restore-all]');
    if (cb.hasAttribute('data-restore-all')) {
      each(box, '[data-restore-part]', function (part) { part.checked = cb.checked; });
      if (cb.checked) each(box, '[data-restore-item]', function (item) { item.checked = false; });
    } else if (cb.hasAttribute('data-restore-part')) {
      if (cb.checked) each(box, '[data-restore-item][data-restore-whole="' + cb.value + '"]', function (item) { item.checked = false; });
    } else if (cb.hasAttribute('data-restore-item') && cb.checked) {
      each(box, '[data-restore-part][value="' + cb.getAttribute('data-restore-whole') + '"]', function (part) { part.checked = false; });
    }
    if (all && !cb.hasAttribute('data-restore-all')) {
      var every = true;
      each(box, '[data-restore-part]', function (part) { if (!part.checked) every = false; });
      each(box, '[data-restore-item]', function (item) { if (item.checked) every = false; });
      all.checked = every;
    }
    partsRefresh(box);
  });
  function partsInit() { if (document.querySelectorAll) each(document, '[data-restore-parts]', partsRefresh); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', partsInit); else partsInit();
  document.addEventListener('change', function (event) {
    var select = event.target;
    if (!select || select.name !== 'digitalisimo_backup_destination' || !select.form) return;
    var label = select.form.querySelector('[data-destination-label]');
    var option = select.options[select.selectedIndex];
    if (label && option) label.textContent = option.text;
  });
}());
