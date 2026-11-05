(function () {
  'use strict';
  // Resalta el día de hoy y, con estado en vivo, recalcula «abierto/cerrado» y el reloj en la zona del negocio:
  // la página puede venir de una caché y el servidor la pintó con otra hora.
  var DAYS = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };

  // Día, minutos y fecha en la zona del bloque: nombre IANA, desplazamiento fijo o, sin ninguno, la hora del visitante.
  function now(root) {
    var date = new Date();
    var tz = root.getAttribute('data-tz');
    var offset = root.getAttribute('data-offset');
    if (tz) {
      try {
        var parts = {};
        new Intl.DateTimeFormat('en-US', { timeZone: tz, hourCycle: 'h23', weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
          .formatToParts(date).forEach(function (p) { parts[p.type] = p.value; });
        var h = parseInt(parts.hour, 10) % 24, m = parseInt(parts.minute, 10);
        return { day: DAYS[parts.weekday], hours: h, minutes: m, total: h * 60 + m, ymd: parts.year + '-' + parts.month + '-' + parts.day };
      } catch (e) { /* zona desconocida para el navegador: se usa la del visitante */ }
    }
    if (offset !== null && offset !== '') {
      var shifted = new Date(date.getTime() + parseInt(offset, 10) * 60000);
      return { day: shifted.getUTCDay(), hours: shifted.getUTCHours(), minutes: shifted.getUTCMinutes(), total: shifted.getUTCHours() * 60 + shifted.getUTCMinutes(), ymd: shifted.getUTCFullYear() + '-' + pad(shifted.getUTCMonth() + 1) + '-' + pad(shifted.getUTCDate()) };
    }
    return { day: date.getDay(), hours: date.getHours(), minutes: date.getMinutes(), total: date.getHours() * 60 + date.getMinutes(), ymd: date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) };
  }

  var toMinutes = function (value) { var p = String(value || '').split(':'); return parseInt(p[0], 10) * 60 + parseInt(p[1], 10); };

  // Misma regla que el servidor: un cierre igual o anterior a la apertura termina al día siguiente.
  function isOpen(root, at) {
    var prev = (at.day + 6) % 7;
    return Array.prototype.some.call(root.querySelectorAll('[data-weekday][data-opens][data-closes]'), function (row) {
      var day = parseInt(row.getAttribute('data-weekday'), 10), open = toMinutes(row.getAttribute('data-opens')), close = toMinutes(row.getAttribute('data-closes'));
      if (isNaN(open) || isNaN(close)) return false;
      if (day === at.day && (open < close ? at.total >= open && at.total < close : at.total >= open)) return true;
      return day === prev && close <= open && at.total < close;
    });
  }

  // Formato de hora de WordPress con los símbolos habituales; con otros, el del navegador.
  function clock(format, at) {
    if (/[^gGhHiaAs\s:.\-\\]/.test(format.replace(/\\./g, ''))) {
      return pad(at.hours) + ':' + pad(at.minutes);
    }
    var out = '', h12 = at.hours % 12 || 12;
    for (var i = 0; i < format.length; i++) {
      var c = format.charAt(i);
      if (c === '\\') { out += format.charAt(++i); continue; }
      out += c === 'g' ? h12 : c === 'G' ? at.hours : c === 'h' ? pad(h12) : c === 'H' ? pad(at.hours) : c === 'i' ? pad(at.minutes) : c === 's' ? '00' : c === 'a' ? (at.hours < 12 ? 'am' : 'pm') : c === 'A' ? (at.hours < 12 ? 'AM' : 'PM') : c;
    }
    return out;
  }

  function swapStatus(root, open) {
    var status = root.querySelector('.digi-hours__status'), alt = root.querySelector('template.digi-hours__alt');
    var want = open ? 'open' : 'closed';
    if (!status || !alt || status.getAttribute('data-state') === want) return;
    var previous = document.createDocumentFragment();
    while (status.firstChild) previous.appendChild(status.firstChild);
    status.appendChild(alt.content.cloneNode(true));
    while (alt.content.firstChild) alt.content.removeChild(alt.content.firstChild);
    alt.content.appendChild(previous);
    alt.setAttribute('data-state', status.getAttribute('data-state'));
    status.setAttribute('data-state', want);
    status.classList.toggle('is-open', open);
    status.classList.toggle('is-closed', !open);
  }

  function update(root) {
    var at = now(root);
    if (root.hasAttribute('data-digi-hours-today')) {
      root.querySelectorAll('[data-weekday]').forEach(function (row) { row.classList.toggle('is-today', row.getAttribute('data-weekday') === String(at.day)); });
    }
    swapStatus(root, isOpen(root, at));
    var time = root.querySelector('.digi-hours__clock');
    if (time) time.textContent = clock(root.getAttribute('data-time-format') || 'H:i', at);
    var date = root.querySelector('.digi-hours__date');
    if (date && root.getAttribute('data-today') !== at.ymd) {
      var p = at.ymd.split('-');
      date.textContent = new Date(Date.UTC(+p[0], p[1] - 1, +p[2], 12)).toLocaleDateString(document.documentElement.lang || undefined, { timeZone: 'UTC', year: 'numeric', month: 'long', day: 'numeric' });
      root.setAttribute('data-today', at.ymd);
    }
  }

  var live = [], timer = null;
  function init(scope) {
    (scope || document).querySelectorAll('.digi-hours').forEach(function (root) {
      update(root);
      if (live.indexOf(root) < 0 && (root.hasAttribute('data-tz') || root.hasAttribute('data-offset'))) live.push(root);
    });
    if (live.length && !timer) {
      timer = setInterval(function () { live = live.filter(function (root) { return root.isConnected; }); live.forEach(update); }, 30000);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-business-hours.default', function ($scope) { init($scope[0] || document); });
  }
})();
