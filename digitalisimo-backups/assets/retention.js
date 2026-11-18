/* «Conservar» de una automatización (docs/BACKUPS.md, «Incrementales» → «Retención»): al cambiar el número, la unidad
   (respaldos o cadenas enteras), el modo, «un completo cada N incrementales o cada D días», la frecuencia o el destino,
   la estimación de cuántos respaldos y cadenas se guardarán cambia al momento. Es la misma cuenta y el mismo texto que
   Digitalisimo_Backups_Chain::estimate() pinta en el servidor (tests/backups-retain-chains-ui.mjs lo comprueba). Sin
   dependencias; todo se pinta como texto. Sin JavaScript se ve la estimación de lo guardado. */
(function (root) {
  'use strict';
  var HISTORY_ROWS = 100, FULL_EVERY = 6, FULL_DAYS = 7, MAX_FULL_EVERY = 60, MAX_FULL_DAYS = 90, DAYS_MARGIN = 21600, BIG = 999999999;
  // Segundos entre dos ejecuciones de cada frecuencia (Digitalisimo_Backups_Chain::PERIODS); sin programación, 0.
  var PERIODS = { hourly: 3600, digitalisimo_every_6_hours: 21600, digitalisimo_every_12_hours: 43200, daily: 86400, weekly: 604800, monthly: 2592000 };

  /* Digitalisimo_Backups_Chain::integer(): un número entero, o un texto de dígitos con signo opcional y espacios alrededor
     (más de 9 cifras, ±999 999 999); decimales, exponentes (1e1000), hexadecimal o vacío dan `fallback`. */
  function integer(value, fallback) {
    if (typeof value === 'number') return isFinite(value) && Math.floor(value) === value ? Math.max(-BIG, Math.min(BIG, value)) : fallback;
    if (typeof value !== 'string') return fallback;
    var match = /^[ \t\n\r]*([+-]?)0*(\d+)[ \t\n\r]*$/.exec(value);
    if (!match) return fallback;
    var number = match[2].length > 9 ? BIG : parseInt(match[2], 10);
    return match[1] === '-' ? -number : number;
  }
  /* empty() de PHP para una casilla: falso con nada, false, 0, '' o '0'. */
  function filled(value) { return !(value === undefined || value === null || value === false || value === 0 || value === '' || value === '0'); }
  function clamp(value, low, high) { return Math.max(low, Math.min(high, value)); }
  function plural(n, one, many) { return n + (n === 1 ? one : many); }

  /* Digitalisimo_Backups_Chain::estimate(): retain, retain_unit, mode, full_every, full_days, schedule, destination y
     remote_only. `text` va a la vista (menos de 140 caracteres) y `detail`, a la ayuda. */
  function estimate(policy) {
    policy = policy || {};
    var retain = integer(policy.retain, 4);
    if (policy.mode !== 'incremental') {
      var all = clamp(retain, 1, HISTORY_ROWS);
      return { unit: 'points', retain: all, cap: HISTORY_ROWS, min: all, max: all, chains_min: all, chains_max: all, previous_min: all - 1, chain_length: 1, text: 'Guardarás ' + all + (all === 1 ? ' respaldo completo.' : ' respaldos completos.'), detail: '' };
    }
    var every = clamp(integer(policy.full_every, FULL_EVERY), 1, MAX_FULL_EVERY);
    var days = clamp(integer(policy.full_days, FULL_DAYS), 1, MAX_FULL_DAYS);
    var unit = policy.retain_unit === 'chains' ? 'chains' : 'points';
    var period = Object.prototype.hasOwnProperty.call(PERIODS, String(policy.schedule)) ? PERIODS[String(policy.schedule)] : 0;
    var length = every + 1;
    // A los D días menos el margen del retraso de WP-Cron (Digitalisimo_Backups_Chain::days_margin()).
    if (period > 0) length = Math.min(length, Math.max(1, Math.floor((days * 86400 - Math.min(DAYS_MARGIN, Math.floor(period / 2)) + period - 1) / period)));
    var cap = unit === 'chains' ? Math.max(1, Math.floor(HISTORY_ROWS / (every + 1))) : Math.max(1, HISTORY_ROWS - every);
    var n = clamp(retain, 1, cap);
    var min, max, low, high;
    if (unit === 'chains') {
      min = (n - 1) * length + 1; max = n * length; low = n; high = n;
    } else {
      min = n; max = n + length - 1;
      low = 1 + Math.floor((n - 1) / length); high = 1 + Math.floor((n + length - 2) / length);
    }
    var text = 'Guardarás ' + (min === max ? plural(min, ' respaldo', ' respaldos') : 'entre ' + min + ' y ' + max + ' respaldos') + ' en ' + (low === high ? plural(low, ' cadena', ' cadenas') : low + ' o ' + high + ' cadenas') + '.';
    if (low > 1) {
      text += low === 2 ? ' Siempre queda al menos una cadena anterior completa.' : ' Siempre quedan al menos ' + (low - 1) + ' cadenas anteriores completas.';
    } else {
      var local = String(policy.destination === undefined || policy.destination === null ? 'local' : policy.destination) === 'local';
      var only = filled(policy.remote_only);
      var copy = local ? 'si su base se daña, no hay otra copia.' : (!only ? 'si su base se daña, sólo queda la copia remota.' : 'su única copia es la remota: conviene «2 cadenas».');
      text += (high === 1 ? ' Una sola cadena' : (period > 0 && period < 86400 ? ' Hay horas' : ' Hay días') + ' con una sola cadena') + (local || !only ? ': ' : ' y ') + copy;
    }
    var daysText = 'cada ' + plural(days, ' día', ' días');
    var detail = length < every + 1
      ? (length === 1 ? 'Con esta frecuencia cada respaldo sale completo (un completo ' + daysText + ').' : 'Con esta frecuencia la cadena se corta por días (un completo ' + daysText + '): ' + length + ' respaldos.')
      : 'Cada cadena: un completo y ' + plural(every, ' incremental', ' incrementales') + ' (' + length + ' respaldos).';
    if (retain > cap) detail += ' «Conservar» quedará en ' + cap + '.';
    return { unit: unit, retain: n, cap: cap, min: min, max: max, chains_min: low, chains_max: high, previous_min: low - 1, chain_length: length, text: text, detail: detail };
  }

  var NAME = 'digitalisimo_backup_automation';
  function field(form, key) { return form.querySelector('[name="' + NAME + '[' + key + ']"]'); }
  function value(form, key) { var input = field(form, key); return input ? input.value : undefined; }
  /* Recalcula la estimación de un formulario de automatización y muestra la unidad sólo en una incremental. */
  function refresh(form) {
    if (!form || !form.querySelector) return;
    var output = form.querySelector('[data-retain-estimate]');
    if (!output) return;
    var unit = form.querySelector('[data-retain-unit]');
    var mode = value(form, 'mode');
    var only = field(form, 'remote_only');
    var policy = { retain: value(form, 'retain'), retain_unit: unit ? unit.value : 'points', mode: mode, full_every: value(form, 'full_every'), full_days: value(form, 'full_days'), schedule: value(form, 'schedule'), destination: value(form, 'destination'), remote_only: !!(only && only.checked) };
    var incremental = mode === 'incremental';
    if (unit) unit.hidden = !incremental;
    var noun = form.querySelector('[data-retain-noun]');
    if (noun) noun.hidden = incremental;
    var help = form.querySelector('[data-retain-chains-help]');
    if (help) help.hidden = !incremental;
    var result = estimate(policy);
    if (output.textContent !== result.text) output.textContent = result.text;
    var detail = form.querySelector('[data-retain-detail]');
    if (detail && detail.textContent !== result.detail) detail.textContent = result.detail;
  }
  function changed(event) {
    var target = event && event.target;
    if (!target || !target.form || typeof target.name !== 'string' || target.name.indexOf(NAME + '[') !== 0) return;
    refresh(target.form);
  }
  root.digitalisimoBackupsRetention = { estimate: estimate, refresh: refresh };
  var doc = root.document;
  if (!doc || !doc.addEventListener) return;
  doc.addEventListener('input', changed);
  doc.addEventListener('change', changed);
  function init() {
    if (!doc.querySelectorAll) return;
    Array.prototype.forEach.call(doc.querySelectorAll('[data-retain-estimate]'), function (output) { refresh(output.closest ? output.closest('form') : null); });
  }
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init); else init();
}(typeof window !== 'undefined' ? window : this));
