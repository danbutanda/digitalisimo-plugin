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
      var text = label ? label.textContent.trim() : cb.value;
      names.push(text.charAt(0).toLowerCase() + text.slice(1));
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
      // Un concreto con «Todo» marcado empieza de cero (sólo eso); si no, excluye su parte entera (revisión M1).
      if (all && all.checked) each(box, '[data-restore-part]', function (part) { part.checked = false; });
      else each(box, '[data-restore-part][value="' + cb.getAttribute('data-restore-whole') + '"]', function (part) { part.checked = false; });
    }
    if (all && !cb.hasAttribute('data-restore-all')) {
      var every = true;
      each(box, '[data-restore-part]', function (part) { if (!part.checked) every = false; });
      each(box, '[data-restore-item]', function (item) { if (item.checked) every = false; });
      all.checked = every;
    }
    partsRefresh(box);
  });
  /* «Volver a la copia previa» desde el panel de avance (?restore=<copia previa>&undo=<trabajo>&undo_parts=…): la copia
     previa se abre con lo mismo que se restauró ya marcado (por partes, sólo esas partes, plugins, temas o sitios; nunca
     «Todo») y el aviso dice qué vuelve y qué no se toca; restaurada entera, que vuelve todo y se pierde lo de después. */
  function undoInit() {
    var location = typeof window !== 'undefined' && window.location ? window.location : null;
    if (!location || typeof URLSearchParams !== 'function') return;
    var query = new URLSearchParams(location.search || '');
    var file = query.get('restore');
    if (!query.get('undo') || !file) return;
    var list = function (name) { return (query.get('undo_' + name) || '').split(',').filter(function (w) { return w; }); };
    var spec = { parts: list('parts'), plugins: list('plugins'), themes: list('themes'), sites: list('sites') };
    var partial = spec.parts.length + spec.plugins.length + spec.themes.length + spec.sites.length > 0;
    each(document, '[data-restore-parts]', function (box) {
      var form = box.closest ? box.closest('form') : null;
      var field = form ? form.querySelector('input[name="backup_file"]') : null;
      if (!field || field.value !== file) return;
      var missing = [];
      if (partial) {
        var all = box.querySelector('[data-restore-all]');
        if (all) all.checked = false;
        each(box, '[data-restore-part]', function (cb) { cb.checked = spec.parts.indexOf(cb.value) !== -1; });
        each(box, '[data-restore-item]', function (cb) { cb.checked = false; });
        [['plugins', 'plugin-items', 'el plugin '], ['themes', 'theme-items', 'el tema '], ['sites', 'site-items', 'los medios del sitio ']].forEach(function (group) {
          spec[group[0]].forEach(function (value) {
            var found = null;
            each(box, '[data-restore-item="' + group[1] + '"]', function (cb) { if (cb.value === value) found = cb; });
            if (!found) { missing.push(group[2] + value); return; }
            found.checked = true;
            var holder = found.closest ? found.closest('details') : null;
            if (holder) holder.open = true;
          });
        });
        box.open = true;
      }
      partsRefresh(box);
      var undo = form.querySelector('[data-restore-undo]');
      if (!undo) return;
      var kept = [];
      each(box, '[data-restore-part]', function (cb) {
        if (cb.checked) return;
        var label = cb.closest ? cb.closest('label') : null;
        var name = (label ? label.textContent.trim() : cb.value);
        name = name.charAt(0).toLowerCase() + name.slice(1);
        var some = false;
        each(box, '[data-restore-item][data-restore-whole="' + cb.value + '"]', function (item) { if (item.checked) some = true; });
        kept.push(some ? 'los demás ' + (cb.value === 'uploads' ? 'medios' : name) : name);
      });
      var text = partial
        ? 'Deshaces sólo lo que se restauró: eso vuelve a como estaba antes.' + (spec.parts.indexOf('database') !== -1 ? ' La base de datos vuelve entera: se pierde lo que cambió en ella después (pedidos, comentarios, entradas o usuarios nuevos).' : '') + (kept.length ? ' No se toca: ' + kept.join(', ') + '.' : '')
        : 'Vuelve el sitio entero a como estaba antes de esa restauración: se pierde lo que cambió después (pedidos, comentarios, entradas o usuarios nuevos).';
      if (missing.length) text += ' La copia previa no tiene ' + missing.join(', ') + ' (lo trajo la restauración): esta vuelta no lo retira; si no lo quieres, bórralo en Plugins o Apariencia.';
      undo.textContent = text;
      undo.hidden = false;
    });
  }
  function partsInit() {
    if (!document.querySelectorAll) return;
    each(document, '[data-restore-parts]', partsRefresh);
    undoInit();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', partsInit); else partsInit();
  /* El respaldo de una red en un WordPress individual: la frase de la confirmación dice el sitio elegido (como texto). */
  document.addEventListener('change', function (event) {
    var site = event.target;
    if (!site || !site.hasAttribute || !site.hasAttribute('data-network-site') || !site.form) return;
    var label = site.form.querySelector('[data-network-site-label]');
    if (!label) return;
    var option = site.options ? site.options[site.selectedIndex] : null;
    label.textContent = option ? 'el sitio ' + option.text.replace(/ \(ID [0-9]+(, principal)?\)$/, '') : 'el sitio ' + site.value;
  });
  document.addEventListener('change', function (event) {
    var select = event.target;
    if (!select || select.name !== 'digitalisimo_backup_destination' || !select.form) return;
    var label = select.form.querySelector('[data-destination-label]');
    var option = select.options[select.selectedIndex];
    if (label && option) label.textContent = option.text;
  });
}());
