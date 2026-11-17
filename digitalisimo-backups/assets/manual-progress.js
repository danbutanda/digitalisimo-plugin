/* Cola de respaldos y panel de avance de la pantalla Backups. Encola el respaldo manual, hace avanzar los trabajos mientras
   la página está abierta (WP-Cron los continúa si se cierra), muestra su estado real y permite cancelarlos. Un solo panel,
   a todo lo ancho, sigue un respaldo, una restauración y lo que trae un respaldo externo o lo envía (docs/BACKUPS.md,
   «Pantalla»): la subida (assets/upload.js lo usa con window.digitalisimoBackupsPanel), la importación de Backuply, «Traer
   al almacén», «Enviar a un destino» y, mientras su petición trabaja, «Agregar al listado». Porcentaje, fase en lenguaje
   claro con sus tramos de la barra, el paso dentro de la fase, el tiempo y, al terminar, el resultado (o el error y la
   vuelta a la copia previa) con lo que sigue (su fila para restaurarlo). Se pone donde se hizo la acción: bajo «Respaldar
   ahora» o, en otra pestaña, en el hueco de su sección (`data-progress-slot`). Una restauración se sigue aunque el cambio
   de tablas deje sin valor la sesión o los nonces (con el testigo de este navegador), durante el mantenimiento (503) y tras
   un corte de red; al recargar la página, el panel vuelve al trabajo en curso; si no puede seguir, lo dice y dice qué
   hacer. Una restauración que empieza fuera de esta pestaña (otra pestaña, REST, WP-CLI, otro administrador) la toma el
   panel en cuanto la ve: en el sondeo de la cola o, sin trabajos, en la consulta del estado cada 30 s; una importación,
   descarga o envío, si el panel no enseña nada. La barra nunca retrocede. Todo texto del servidor se pinta como texto. */
(function () {
  'use strict';
  var config = window.digitalisimoBackupsManual;
  if (!config) return;
  function byId(id) { return document.getElementById(id); }
  var form = byId('digitalisimo-backups-manual-form');
  var card = byId('digitalisimo-backups-jobs');
  var list = byId('digitalisimo-backups-jobs-list');
  var panel = byId('digitalisimo-backups-manual-progress');
  var bar = byId('digitalisimo-backups-manual-bar');
  var message = byId('digitalisimo-backups-manual-message');
  var detail = byId('digitalisimo-backups-manual-detail');
  var P = 'digitalisimo-backups-progress-';
  var ui = {};
  ['title', 'percent', 'what', 'fill', 'ticks', 'phases', 'time', 'note', 'result', 'cancel', 'reload', 'safety', 'login', 'rollback', 'help', 'go', 'resume', 'import'].forEach(function (name) { ui[name] = byId(P + name); });
  var destination = form ? form.querySelector('[name="digitalisimo_backup_destination"]') : null;
  var remoteOnly = form ? form.querySelector('[name="digitalisimo_backup_remote_only"]') : null;
  var button = form ? form.querySelector('button[type="submit"], button:not([type])') : null;
  var watching = '';
  /* Lo que sigue el panel: tipo, cuándo empezó (reloj de este navegador), sus tramos, la restauración que falló (si sigue
     su vuelta a la copia previa) y si ya terminó. */
  var watch = null;
  var timer = null;
  var busy = false;
  /* Desde cuándo espera la respuesta de un tick: un tramo dura hasta 20 s y el avance llega al terminarlo. */
  var busySince = 0;
  var workingNote = false;
  var failures = 0;
  var sessionFailures = 0;
  var lastOk = Date.now();
  var migrating = false;
  /* Trabajos en curso (salvo el que sigue el panel del botón) vistos durante esta visita: la tarjeta de «Respaldos». */
  var seen = {};
  var reloading = false;
  /* Con una restauración en curso, el tick lleva su testigo: tras el cambio de tablas la sesión puede no valer. Si la
     pantalla se cargó sin él (la empezó en otra pestaña, o es la reconstrucción de un punto), el tick con nonce pasa al del
     testigo en cuanto la sesión deja de valer (la cookie de este navegador lo lleva). */
  var restore = window.digitalisimoBackupsRestore || null;
  var tickMode = restore ? 'restore' : 'jobs';
  /* Lo que el panel recuerda del sondeo mientras todo va bien (p. ej., que la sesión cambió y se sigue con el testigo). */
  var modeNote = '';
  /* El tick por testigo dice si esta pestaña conserva su sesión de gestión: sin ella, al terminar se pide entrar. */
  var sessionLost = false;
  /* Hay un paso programado; o el sondeo se detuvo del todo (sin sesión ni testigo); cuándo se consultó por última vez y
     desde cuándo dice el panel que empieza una restauración. */
  var pendingStep = false;
  var stopped = false;
  var lastPoll = Date.now();
  var startedAt = 0;
  var IDLE_POLL = 30000;
  /* Minutos tras terminar en los que una restauración que esta pestaña no seguía se enseña como recién terminada (los del
     panel al cargar la página, Digitalisimo_Backups_Progress::RECENT). */
  var RECENT = 900;
  /* El formulario de «Restaurar» enviado y cuándo: si la navegación se cancela, a los 60 s vuelve a estar disponible. */
  var sentForm = null;
  var sentAt = 0;
  /* Qué decir si la petición que empieza una acción tarda más de 40 s (la de restaurar, importar, traer, enviar o agregar). */
  var startNote = '';
  /* Lo que no es un trabajo del motor y usa el panel (la subida de assets/upload.js): sus acciones (cancel, resume) y si
     sigue en curso (entonces el panel no toma otro trabajo). */
  var external = null;

  function request(action, fields, nonce) {
    var body = new URLSearchParams(Object.assign({ action: action, nonce: nonce, context: config.context }, fields || {}));
    return fetch(config.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
      .then(function (response) { return response.text().then(function (text) {
        var result;
        /* La sesión ya no vale: 401/403, o admin-ajax responde «-1» (nonce) o «0» (sin sesión). Un corte del proxy, un
           502/504 o el 503 del mantenimiento durante la restauración no lo son. */
        var session = response.status === 401 || response.status === 403 || ['-1', '0'].indexOf(text.trim()) !== -1;
        /* El 503 de la página de mantenimiento de una restauración de Backups trae su cabecera; cualquier otro, no. */
        var restoreGate = !!(response.headers && typeof response.headers.get === 'function' && response.headers.get('X-Digitalisimo-Backups-Restore'));
        try { result = JSON.parse(text); } catch (error) {
          var broken = new Error(response.status === 503 && restoreGate ? 'El sitio está en mantenimiento mientras se aplica la restauración (HTTP 503)' : (response.status === 503 ? 'El servidor no responde (HTTP 503)' : 'El servidor no devolvió JSON (HTTP ' + response.status + '). Revisa el registro de PHP o el firewall del servidor.'));
          broken.session = session;
          broken.status = response.status;
          broken.restore = restoreGate;
          throw broken;
        }
        if (!response.ok || !result.success) {
          var failure = new Error(result.data && result.data.message || 'Error HTTP ' + response.status);
          failure.data = result.data || {};
          failure.session = session;
          failure.status = response.status;
          failure.restore = restoreGate;
          throw failure;
        }
        return result.data;
      }); });
  }

  /* Recarga sin los parámetros de un aviso o de un panel de restaurar viejos (?backup=…&message=…, ?restore=…): la
     página recargada no debe repetir el resultado de otra acción ni reabrir un panel que ya no corresponde. */
  function reloadPage() {
    if (reloading) return;
    reloading = true;
    var location = window.location;
    if (!location) return;
    var clean = '';
    try {
      var url = new URL(location.href);
      var changed = false;
      ['backup', 'message', 'restore', 'job'].forEach(function (name) { if (url.searchParams.has(name)) { url.searchParams.delete(name); changed = true; } });
      if (changed) {
        if (/^#digitalisimo-backups-restore-/.test(url.hash)) url.hash = '';
        clean = url.toString();
      }
    } catch (error) { clean = ''; }
    if (clean && typeof location.replace === 'function') location.replace(clean);
    else if (typeof location.reload === 'function') location.reload();
  }

  function element(tag, text, attributes) {
    var node = document.createElement(tag);
    if (text !== undefined && text !== null) node.textContent = String(text);
    Object.keys(attributes || {}).forEach(function (name) { node.setAttribute(name, attributes[name]); });
    return node;
  }
  function empty(node) { if (node) while (node.firstChild) node.removeChild(node.firstChild); }
  function show(node, visible) { if (node) node.hidden = !visible; }
  function setText(node, text) { if (node && node.textContent !== String(text)) node.textContent = String(text); }
  function renderJobs(jobs) {
    if (!list) return;
    while (list.firstChild) list.removeChild(list.firstChild);
    if (!jobs.length) { list.appendChild(element('p', 'Aún no hay trabajos.')); return; }
    var table = element('table', null, { 'class': 'widefat striped' });
    var head = element('tr');
    ['Estado', 'Trabajo', 'Fase', 'Avance', 'Detalle', 'Acciones'].forEach(function (title) { head.appendChild(element('th', title)); });
    table.appendChild(element('thead')).appendChild(head);
    var body = table.appendChild(element('tbody'));
    jobs.forEach(function (job) {
      var row = element('tr', null, { 'data-job': job.id });
      var status = element('td');
      status.appendChild(element('span', job.status_label, { 'class': 'digitalisimo-backups-job-status', 'data-status': job.status }));
      row.appendChild(status);
      var what = element('td', job.origin_label + ' · ' + job.destination_label);
      what.appendChild(element('br'));
      what.appendChild(element('small', job.created_label));
      row.appendChild(what);
      row.appendChild(element('td', job.phase_label));
      var progress = element('td');
      progress.appendChild(element('progress', null, { max: '100', value: String(job.percent) }));
      progress.appendChild(document.createTextNode(' ' + job.percent + '%'));
      row.appendChild(progress);
      row.appendChild(element('td', job.detail));
      var actions = element('td');
      if (job.can_cancel) actions.appendChild(element('button', 'Cancelar', { type: 'button', 'class': 'button button-small', 'data-cancel': job.id }));
      row.appendChild(actions);
      body.appendChild(row);
    });
    list.appendChild(table);
  }

  /* ------------------------------------------------------------------ El panel de avance */

  function kindOf(job) {
    if (job.kind === 'restore') return job.rollback_of || job.origin_label === 'Vuelta a la copia previa' ? 'rollback' : 'restore';
    if (['chain', 'convert', 'import', 'migration', 'fetch', 'send', 'upload'].indexOf(job.kind) !== -1) return job.kind;
    return 'backup';
  }
  function restoring(kind) { return kind === 'restore' || kind === 'rollback' || kind === 'chain' || kind === 'convert'; }
  /* Lo que trae un respaldo externo o lo envía: al terminar, el resultado se queda en el panel con lo que sigue. */
  function entry(kind) { return kind === 'import' || kind === 'fetch' || kind === 'send' || kind === 'upload'; }
  var TITLES = {
    backup: ['Respaldo en curso', 'Respaldo terminado', 'El respaldo no se completó', 'Respaldo cancelado'],
    chain: ['Preparando la restauración del punto incremental', 'Punto incremental reconstruido', 'El punto incremental no se pudo reconstruir', 'Restauración cancelada'],
    /* El respaldo de una red en un WordPress individual: la conversión del sitio elegido, antes de su restauración. */
    convert: ['Convirtiendo el sitio de la red en este WordPress', 'Sitio de la red convertido', 'El sitio de la red no se pudo convertir', 'Restauración cancelada'],
    restore: ['Restauración en curso', 'Restauración terminada', 'La restauración no se completó', 'Restauración cancelada', 'La restauración falló a mitad del cambio'],
    rollback: ['Volviendo a la copia previa', 'El sitio volvió a la copia previa', 'La vuelta a la copia previa no terminó', 'Vuelta a la copia previa cancelada', 'La vuelta a la copia previa no terminó'],
    import: ['Importación en curso', 'Importación terminada', 'La importación no se completó', 'Importación cancelada'],
    migration: ['Migración en curso', 'Migración terminada', 'La migración no se completó', 'Migración cancelada'],
    fetch: ['Trayendo el respaldo del destino', 'Respaldo traído al almacén', 'No se pudo traer el respaldo', 'Descarga cancelada'],
    send: ['Enviando el respaldo a un destino', 'Copia enviada y verificada', 'El envío no se completó', 'Envío cancelado'],
    upload: ['Subiendo el respaldo', 'Respaldo subido y verificado', 'La subida se detuvo', 'Subida cancelada'],
  };
  var STATE_TEXT = { done: 'hecha: ', current: 'en curso: ', failed: 'se detuvo aquí: ', stopped: 'cancelada aquí: ', pending: 'pendiente: ' };
  function title(kind, job) {
    /* La subida (no es un trabajo) dice su caso: en pausa, descartada, sin registro, de Backuply. */
    if (job.title) return job.title;
    var titles = TITLES[kind] || TITLES.backup;
    if (!job.terminal) return titles[0];
    if (job.status === 'success') return titles[1];
    if (job.status === 'cancelled') return titles[3];
    return job.midway && titles[4] ? titles[4] : titles[2];
  }
  function duration(seconds) {
    var s = Math.max(0, Math.round(seconds));
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    return h ? h + ' h ' + m + ' min' : (m ? m + ' min ' + (s % 60) + ' s' : (s % 60) + ' s');
  }
  function clock(ms) { var d = new Date(ms); return (d.getHours() < 10 ? '0' : '') + d.getHours() + ':' + (d.getMinutes() < 10 ? '0' : '') + d.getMinutes(); }
  function paintTime() {
    if (!ui.time || !watch) return;
    if (watch.noTime) { setText(ui.time, ''); return; }
    var started = /\d{2}:\d{2}$/.test(watch.created || '') ? watch.created.slice(-5) : '';
    var seconds = ((watch.end || Date.now()) - watch.base) / 1000;
    setText(ui.time, (started ? 'Empezó a las ' + started + ' · ' : '') + (watch.end ? 'duró ' : 'lleva ') + duration(seconds));
  }
  /* Nota del sondeo (corte, mantenimiento, sesión): nunca una barra quieta sin explicación. */
  function note(text, level) {
    if (!ui.note) return;
    /* La región viva está siempre en la página (vacía, el CSS la pliega): así un lector de pantalla anuncia lo que se escribe. */
    if (ui.note.hidden) ui.note.hidden = false;
    ui.note.setAttribute('data-level', level || 'info');
    setText(ui.note, text || '');
  }
  /* $waiting: una fase sin pasos medibles (la comprobación de una subida en el servidor): lo hecho se queda lleno y el resto
     de la barra se mueve mientras tanto; sin aria-valuenow (indeterminada), con su explicación en aria-valuetext. */
  function paintBar(percent, label, indeterminate, waiting) {
    if (ui.fill && ui.fill.style) ui.fill.style.width = (indeterminate ? 100 : percent) + '%';
    if (bar) {
      if (waiting) bar.setAttribute('data-waiting', '1');
      else if (bar.removeAttribute) bar.removeAttribute('data-waiting');
      if (indeterminate || waiting) { if (bar.removeAttribute) bar.removeAttribute('aria-valuenow'); bar.setAttribute('aria-valuetext', waiting ? percent + ' % · ' + label : label); }
      else { bar.setAttribute('aria-valuenow', String(percent)); bar.setAttribute('aria-valuetext', percent + ' %' + (label ? ' · ' + label : '')); }
    }
    setText(ui.percent, indeterminate ? '…' : percent + ' %');
  }
  /* El panel va donde se hizo la acción: bajo «Respaldar ahora» (donde se pinta) o, en otra pestaña, en el hueco de su
     sección (la del formulario que se envió, o la de su tipo: la subida, la importación, «Traer al almacén» de un destino),
     que se abre si estaba plegada. Una restauración en curso se queda arriba, como siempre. */
  var SLOTS = { upload: 'upload', import: 'import', fetch: 'remote' };
  function place(kind, near) {
    if (!panel || !document.querySelector) return;
    var slot = null;
    var section = near && near.closest ? near.closest('.digitalisimo-backups-section') : null;
    if (section && section.querySelector) slot = section.querySelector('[data-progress-slot]');
    if (!slot && !near && SLOTS[kind]) slot = document.querySelector('[data-progress-slot="' + SLOTS[kind] + '"]');
    if (!slot || panel.parentNode === slot) return;
    slot.appendChild(panel);
    for (var node = slot; node && node.tagName; node = node.parentNode) if (node.tagName === 'DETAILS' && !node.open) { node.open = true; if (node.setAttribute) node.setAttribute('open', ''); }
  }
  function paintTrack(track, phase, state) {
    if (ui.phases) {
      empty(ui.phases);
      var at = -1;
      track.forEach(function (item, index) { if (item[0] === phase) at = index; });
      track.forEach(function (item, index) {
        var mark = state === 'success' ? 'done' : (index < at ? 'done' : (index === at ? ({ failed: 'failed', cancelled: 'stopped' }[state] || 'current') : 'pending'));
        var li = element('li', null, { 'data-state': mark, 'data-phase': item[0] });
        li.appendChild(element('span', STATE_TEXT[mark], { 'class': 'screen-reader-text' }));
        li.appendChild(document.createTextNode(item[1]));
        if (mark === 'current') li.setAttribute('aria-current', 'step');
        ui.phases.appendChild(li);
      });
      show(ui.phases, track.length > 1);
    }
    /* Las marcas de los tramos sobre la barra: dónde empieza cada fase. */
    if (ui.ticks && watch && watch.ticks !== JSON.stringify(track)) {
      watch.ticks = JSON.stringify(track);
      empty(ui.ticks);
      track.forEach(function (item) {
        if (!(item[2] > 0 && item[2] < 100)) return;
        var tick = element('span', null, { 'class': 'digitalisimo-backups-progress-tick' });
        if (tick.style) tick.style.left = item[2] + '%';
        ui.ticks.appendChild(tick);
      });
    }
  }
  /* La petición que empieza algo trabaja antes de volver: el panel dice ya que empieza (barra indeterminada) y, con $track,
     en qué fase está («Agregar al listado»: la comprobación y después el registro). $near: el formulario que se envió. */
  function starting(heading, text, more, track, near) {
    if (!panel) return;
    if (near) place('', near);
    panel.hidden = false;
    panel.setAttribute('data-state', 'starting');
    panel.removeAttribute && panel.removeAttribute('data-kind');
    setText(ui.title, heading);
    setText(ui.what, '');
    paintBar(0, text, true);
    if (ui.phases) {
      empty(ui.phases);
      (track || []).forEach(function (item, index) {
        var li = element('li', null, { 'data-state': index ? 'pending' : 'current', 'data-phase': item[0] });
        li.appendChild(element('span', STATE_TEXT[index ? 'pending' : 'current'], { 'class': 'screen-reader-text' }));
        li.appendChild(document.createTextNode(item[1]));
        if (!index) li.setAttribute('aria-current', 'step');
        ui.phases.appendChild(li);
      });
      show(ui.phases, !!(track && track.length > 1));
    }
    if (ui.ticks) { empty(ui.ticks); }
    setText(message, text);
    setText(detail, more || '');
    setText(ui.time, '');
    note('');
    empty(ui.result);
    ['cancel', 'reload', 'safety', 'login', 'rollback', 'go', 'resume', 'import'].forEach(function (name) { show(ui[name], false); });
    startedAt = 0;
  }
  /* La franja de avisos repite el estado de la restauración: mientras el panel la sigue, su línea se oculta. */
  function quietStrip() {
    if (!document.querySelector) return;
    var line = document.querySelector('.digitalisimo-backups-strip .digitalisimo-backups-restore-state');
    if (!line || line.hidden) return;
    line.hidden = true;
    var strip = line.closest ? line.closest('.digitalisimo-backups-strip') : null;
    if (strip && strip.querySelectorAll && !Array.prototype.some.call(strip.querySelectorAll('li'), function (li) { return !li.hidden; })) strip.hidden = true;
  }
  function paragraph(text, extra) { if (ui.result && text) ui.result.appendChild(element('p', text, extra ? { 'class': extra } : null)); }
  /* «Volver a la copia previa» tras una restauración que terminó bien: abre la copia previa con lo mismo que se restauró
     ya marcado (por partes: sólo esas partes, plugins, temas o sitios; nunca «Todo») y la confirmación dice qué vuelve y
     qué no se toca (assets/screen.js). Restaurada entera, vuelve todo y se pierde lo que cambió después. */
  function partial(job) { return !!(job.parts_spec && job.parts && job.parts !== 'todo'); }
  function safetyLink(job) {
    var file = job.safety;
    if (!ui.safety || !file) return;
    var href = ui.safety.getAttribute('href') || '';
    var spec = partial(job) ? job.parts_spec : null;
    try {
      var url = new URL(href || window.location.href, window.location.href);
      ['backup', 'message', 'open', 'parts', 'undo', 'undo_parts', 'undo_plugins', 'undo_themes', 'undo_sites'].forEach(function (name) { url.searchParams.delete(name); });
      url.searchParams.set('tab', 'backups');
      url.searchParams.set('restore', file);
      url.searchParams.set('undo', job.id);
      if (spec) {
        url.searchParams.set('parts', '1');
        ['parts', 'plugins', 'themes', 'sites'].forEach(function (name) { if (spec[name] && spec[name].length) url.searchParams.set('undo_' + name, spec[name].join(',')); });
      }
      url.hash = 'digitalisimo-backups-history';
      href = url.toString();
    } catch (error) { /* sin URL: el enlace del servidor */ }
    ui.safety.setAttribute('href', href);
    setText(ui.safety, spec ? 'Volver a la copia previa (sólo ' + job.parts + ')' : 'Volver a la copia previa');
    ui.safety.setAttribute('title', spec ? 'Abre la copia previa ' + file + ' con sólo ' + job.parts + ' marcado: eso vuelve a como estaba antes; lo demás no se toca.' : 'Abre la copia previa ' + file + ' para restaurarla entera: el sitio vuelve a como estaba antes de esta restauración y se pierde lo que cambió después.');
    show(ui.safety, true);
  }
  /* El resultado al terminar: qué se restauró, sus avisos y la copia previa; o el error y qué pasó con el sitio. */
  function paintResult(kind, job) {
    if (!ui.result) return;
    if (ui.result.hidden) ui.result.hidden = false;
    empty(ui.result);
    var failed = watch && watch.failed;
    if (kind === 'rollback' && (job.rollback_file || job.rollback_cause || failed)) {
      var cause = job.rollback_cause || (failed && (failed.error || failed.detail)) || '';
      var of = job.rollback_file || (failed && failed.destination_label) || '';
      paragraph('La restauración' + (of ? ' de ' + of : '') + ' falló a mitad del cambio' + (cause ? ': ' + cause : '.'), 'digitalisimo-backups-warning');
      if (job.rollback_manual) paragraph('Esta vuelta a la copia previa se pidió a mano (la automática no terminó).');
    }
    paragraph(job.detail);
    var report = job.result || null;
    if (report && job.status === 'success') {
      paragraph(report.notice);
      if (report.warnings_total) {
        paragraph(report.warnings_total === 1 ? 'Con 1 aviso:' : 'Con ' + report.warnings_total + ' avisos:', 'digitalisimo-backups-warning');
        var items = element('ul');
        (report.warnings || []).forEach(function (text) { items.appendChild(element('li', text)); });
        if (report.warnings_total > (report.warnings || []).length) items.appendChild(element('li', 'Y ' + (report.warnings_total - report.warnings.length) + ' más en el registro del trabajo.'));
        ui.result.appendChild(items);
      }
      /* El informe ya lo dice (con dónde quedan); sólo si no. */
      if (report.removed && !/Se retiraron/.test(report.notice || '')) paragraph('Se retiraron ' + report.removed + ' archivos que el respaldo no tenía' + (report.kept ? ' (' + report.kept + ' cambiados después se apartaron en el almacén)' : '') + '.');
    } else if (job.notices && job.notices.length) paragraph('Avisos: ' + job.notices.join(' '), 'digitalisimo-backups-warning');
    if (kind === 'restore' && job.status === 'success' && job.safety) {
      if (partial(job)) paragraph('La copia previa ' + job.safety + ' queda bloqueada en el historial. Si algo no quedó bien, «Volver a la copia previa» la abre con sólo ' + job.parts + ' marcado: eso vuelve a como estaba antes' + (job.untouched ? ' y no se toca ' + job.untouched : ' y lo demás no se toca') + '.');
      else paragraph('La copia previa ' + job.safety + ' queda bloqueada en el historial. Si algo no quedó bien, «Volver a la copia previa» la restaura entera: el sitio vuelve a como estaba antes de esta restauración y se pierde lo que cambió después (pedidos, comentarios, entradas o usuarios nuevos).');
    }
    if (sessionLost) paragraph('Esta pestaña perdió la sesión con el cambio de tablas (la restauración repuso los usuarios del respaldo): inicia sesión para ver el resultado completo.', 'digitalisimo-backups-warning');
  }
  /* Una dirección de esta misma pantalla (sitio o red) con otra pestaña, sección o fila, sin los avisos ni los paneles de antes. */
  function screenUrl(params, hash) {
    try {
      var url = new URL(window.location.href);
      ['backup', 'message', 'restore', 'job', 'tab', 'open', 'parts', 'undo', 'undo_parts', 'undo_plugins', 'undo_themes', 'undo_sites'].forEach(function (name) { url.searchParams.delete(name); });
      Object.keys(params).forEach(function (name) { url.searchParams.set(name, params[name]); });
      url.hash = hash || '';
      return url.toString();
    } catch (error) { return ui.go ? ui.go.getAttribute('href') || '' : ''; }
  }
  var LOOSE = { tab: 'tools', open: 'restore-file' };
  /* Lo que sigue a un respaldo que entró: su fila para restaurarlo o «Restaurar un archivo suelto». */
  function entryGo(kind, job) {
    if (job.status !== 'success' || !job.file) return null;
    if (kind === 'fetch' && job.fetch && job.fetch.registered) return { text: 'Restaurarlo', href: screenUrl({ tab: 'backups', restore: job.file }, 'digitalisimo-backups-history'), title: 'Abre la fila de ' + job.file + ' en la lista con «Restaurar» desplegado.' };
    if (kind === 'fetch' || kind === 'import') return { text: 'Ver en «Restaurar un archivo suelto»', href: screenUrl(LOOSE, 'digitalisimo-backups-section-restore-file'), title: 'Ahí se restaura (RESTAURAR SIN FIRMA salvo firma de una clave de este sitio) o se pasa a la lista con «Agregar al listado».' };
    return null;
  }
  /* El resultado de un respaldo que entró o se envió: qué pasó y dónde quedó (las líneas que da la subida, o el texto del
     servidor y sus avisos), con el enlace a lo que sigue y, tras subir un .tar.gz de Backuply, «Importarlo ahora». */
  function paintEntry(kind, job) {
    if (!ui.result) return;
    if (ui.result.hidden) ui.result.hidden = false;
    empty(ui.result);
    if (job.lines) job.lines.forEach(function (line) { paragraph(line.text, line.warning ? 'digitalisimo-backups-warning' : ''); });
    else {
      paragraph(job.detail);
      if (job.notices && job.notices.length) paragraph('Avisos: ' + job.notices.join(' '), 'digitalisimo-backups-warning');
      if (job.status === 'failed') paragraph(kind === 'send' ? 'La copia a medias se retiró del destino; el respaldo sigue en el almacén y se puede volver a enviar.' : 'No quedó nada a medias en el almacén: corrige el motivo y vuelve a intentarlo.');
    }
    var go = job.go === undefined ? entryGo(kind, job) : job.go;
    if (ui.go && go) {
      ui.go.setAttribute('href', go.href);
      setText(ui.go, go.text);
      if (go.title) ui.go.setAttribute('title', go.title);
    }
    show(ui.go, !!go);
    var field = ui.import && ui.import.querySelector ? ui.import.querySelector('input[name="backuply_file"]') : null;
    if (field && job.import_file) field.value = job.import_file;
    show(ui.import, !!(field && job.import_file));
    show(ui.resume, !!job.resumable);
    /* La lista de «Respaldos» cambió (la fila vuelve a tener su ZIP o su copia remota): se recarga a petición. */
    show(ui.reload, job.status === 'success' && (kind === 'fetch' || kind === 'send'));
  }
  /* Pinta un trabajo en el panel. */
  /* La reconstrucción de un punto incremental (o la conversión del sitio de una red) ocupa el primer 20 % de la barra y su
     restauración, el resto: la barra sigue de una a otra sin volver a empezar. */
  function span(job) { return job.kind === 'chain' || job.kind === 'convert' ? [0, 20] : (job.from_point ? [20, 100] : [0, 100]); }
  function scaled(job, value) { var range = span(job); return Math.round(range[0] + (range[1] - range[0]) * Math.max(0, Math.min(100, Number(value) || 0)) / 100); }
  function paint(job) {
    if (!panel) return;
    var kind = kindOf(job);
    if (!restoring(kind)) place(kind);
    if (!watch || watch.id !== job.id) watch = { id: job.id, kind: kind, origin: watch ? watch.base : 0, base: Date.now(), created: watch && watch.created || job.created_label || '', track: watch && watch.track, failed: watch && watch.failed, end: 0, ticks: watch && watch.ticks, top: watch && (watch.kind === 'chain' || watch.kind === 'convert') ? watch.top : 0, phase: '', at: -1 };
    watch.noTime = job.elapsed === null;
    /* El tiempo lo da el servidor (desde que se encoló); en una sucesión de trabajos, desde el primero. */
    if (job.elapsed !== undefined && !watch.end) watch.base = Date.now() - (Number(job.elapsed) || 0) * 1000;
    if (watch.origin) watch.base = Math.min(watch.origin, watch.base);
    watch.kind = kind;
    if (job.track && job.track.length) watch.track = job.track.map(function (item) { return [item[0], item[1], scaled(job, item[2])]; });
    var track = watch.track || [];
    var state = job.terminal ? job.status : 'running';
    panel.hidden = false;
    panel.setAttribute('data-state', state);
    panel.setAttribute('data-kind', kind);
    /* La barra nunca retrocede: ni un reintento (que vuelve al comienzo de su fase aunque se retome desde su punto de
       control), ni una fase que se repite, ni un fallo (se queda donde se detuvo, en rojo). */
    var percent = Math.max(watch.top || 0, job.status === 'success' ? 100 : scaled(job, job.percent));
    watch.top = percent;
    /* La vista mínima (sin sesión) de una que terminó puede no traer la fase: la última que se vio. */
    var phase = job.phase || watch.phase || '';
    watch.phase = phase;
    var at = -1;
    track.forEach(function (item, index) { if (item[0] === phase) at = index; });
    /* Una fase que vuelve atrás (un ZIP que no pasó la verificación y se rehace): se dice, la barra espera. */
    var back = !job.terminal && at >= 0 && at < watch.at;
    if (at > watch.at || job.terminal) watch.at = at;
    setText(ui.title, title(kind, job));
    var what = '';
    if (kind === 'restore') what = 'Respaldo ' + job.destination_label + ' · se restaura ' + (job.parts && job.parts !== 'todo' ? 'sólo: ' + job.parts : 'todo');
    else if (kind === 'rollback') what = 'Copia previa ' + job.destination_label + (job.rollback_file ? ' · deshace la restauración de ' + job.rollback_file : '');
    else if (kind === 'fetch') what = job.origin_label + ' · ' + (job.fetch && job.fetch.name ? job.fetch.name + ' ' : '') + job.destination_label;
    else if (job.destination_label) what = job.origin_label + ' · ' + job.destination_label;
    setText(ui.what, what);
    paintBar(percent, job.terminal ? job.status_label : (back ? 'vuelve a ' : '') + job.phase_label + (job.waiting ? ' en el servidor (sin pasos medibles)' : ''), false, !job.terminal && !!job.waiting);
    paintTrack(track, phase, state);
    setText(message, job.status_label + (job.phase_label ? ' · ' + (back ? 'vuelve a ' : '') + job.phase_label : '') + (job.phase_label && at >= 0 && track.length > 1 ? ' (fase ' + (at + 1) + ' de ' + track.length + ')' : '') + (back ? ': la barra sigue cuando pase de nuevo el ' + percent + ' %' : ''));
    /* Terminada, lo que pasó lo dice el resultado (sin repetirlo en el paso). */
    setText(detail, job.terminal && (restoring(kind) || entry(kind)) ? '' : job.detail);
    if (job.terminal && !watch.end) watch.end = Date.now();
    paintTime();
    /* «Cancelar» sólo si sirve: la fase lo permite, esta pestaña gestiona Backups y el último tick llegó bien (la subida lo
       dice ella: mientras envía fragmentos o, en pausa, para descartarla; nunca mientras el servidor la comprueba). */
    show(ui.cancel, kind === 'upload' ? !!job.can_cancel : !!job.can_cancel && !job.terminal && !sessionLost && !failures);
    if (ui.cancel) ui.cancel.setAttribute('data-cancel', job.id);
    show(ui.help, restoring(kind) && !job.terminal);
    if (restoring(kind)) {
      quietStrip();
      if (button) button.disabled = !job.terminal;
    }
    if (!job.terminal) { empty(ui.result); ['reload', 'safety', 'login', 'rollback', 'go', 'resume', 'import'].forEach(function (name) { show(ui[name], false); }); return; }
    if (entry(kind)) { paintEntry(kind, job); return; }
    if (!restoring(kind)) return;
    paintResult(kind, job);
    show(ui.reload, true);
    if (kind === 'restore' && job.status === 'success') safetyLink(job);
    show(ui.rollback, job.status === 'failed' && !!job.midway);
    /* Sin sesión, el tick por testigo sólo da el estado y un texto general: el resultado se ve al entrar. */
    show(ui.login, sessionLost || (!job.track && !job.result && /Entra al escritorio/.test(job.detail || '')));
    if (ui.safety && sessionLost) show(ui.safety, false);
  }
  /* El trabajo que sigue al que el panel seguía: la vuelta a la copia previa de una restauración que falló a mitad del cambio
     o la restauración de un punto incremental ya reconstruido. */
  function follower(jobs, job) {
    var next = job.followed_by || job.restore_job || '';
    var found = null;
    jobs.forEach(function (item) { if (item.id !== job.id && ((next && item.id === next) || (item.rollback_of && item.rollback_of === job.id))) found = item; });
    return found;
  }
  function showWatched(jobs, finished) {
    if (!watching || !panel) return;
    var job = null;
    jobs.forEach(function (item) { if (item.id === watching) job = item; });
    if (!job) jobs.forEach(function (item) { if (item.rollback_of === watching) job = item; });
    /* La terminó otro proceso y el tick por testigo sólo da la última restauración de este testigo (sin sesión, su vista mínima). */
    if (!job && finished && watch && restoring(watch.kind) && jobs.length === 1 && jobs[0].kind === 'restore') job = jobs[0];
    if (!job) return;
    var next = job.terminal ? follower(jobs, job) : null;
    if (next) {
      if (watch) watch.failed = job.status === 'success' ? null : job;
      job = next;
    }
    watching = job.id;
    paint(job);
    if (!job.terminal) return;
    var kind = kindOf(job);
    watching = '';
    if (button) button.disabled = false;
    if (restoring(kind)) {
      /* La restauración terminó: lo que siga (la cola de respaldos, una automatización que esperaba) vuelve al tick con
         nonce, enseguida; sin sesión de gestión no hay nada más que consultar. */
      tickMode = 'jobs';
      if (!sessionLost) schedule(1000);
      return;
    }
    /* Lo que trajo o envió un respaldo: el resultado se queda en el panel (paint()) con lo que sigue; la lista, a petición. */
    if (entry(kind)) return;
    /* La lista de respaldos se pinta en PHP: al terminar bien, la página se vuelve a cargar con el respaldo nuevo. */
    if (job.status === 'success') {
      detail.textContent = job.detail + '. Actualizando la lista…';
      reloadPage();
    }
    else if (job.status === 'failed') detail.textContent = job.detail + ' No se registró ningún respaldo parcial; puedes corregir el error y volver a intentarlo.';
  }
  /* Una migración que llegó a restaurar reemplaza los usuarios: la sesión de este navegador deja de valer. */
  function sessionReplaced() {
    if (!card || card.querySelector('.digitalisimo-backups-migrate-login')) return;
    var noteNode = element('p', 'La migración reemplazó este sitio y tu sesión ya no vale: inicia sesión con un usuario del sitio de origen para ver el resultado. Si algo salió mal, «Deshacer la migración» en Backups → Herramientas → Migrar lo devuelve a su estado anterior.', { 'class': 'notice notice-info inline digitalisimo-backups-migrate-login', role: 'status' });
    var link = element('a', ' Iniciar sesión', { href: config.login || '/wp-login.php' });
    noteNode.appendChild(link);
    card.insertBefore(noteNode, list);
  }
  /* Una restauración en curso que esta pestaña no empezó (otra pestaña, REST, WP-CLI, otro administrador): la toma el panel
     grande, como al cargar la página; las de una migración las sigue «Migrar». */
  function adopt(jobs, ended) {
    if (watching || !panel || (external && external.active)) return;
    var found = null;
    /* Terminada, sólo si acaba de terminar: una de hace días nunca se enseña como recién terminada (revisión N2). */
    var fresh = function (job) { return !job.terminal || (ended && job.finished_ago !== null && job.finished_ago !== undefined && Number(job.finished_ago) <= RECENT); };
    jobs.forEach(function (job) { if (!found && fresh(job) && !job.aside && restoring(kindOf(job))) found = job; });
    /* Una importación, descarga o envío en curso que empezó en otra pestaña, REST o WP-CLI, si el panel no enseña nada. */
    if (!found && panel.hidden) jobs.forEach(function (job) { if (!found && !job.terminal && !job.aside && entry(kindOf(job))) found = job; });
    if (!found) return;
    watch = null;
    watching = found.id;
  }
  function update(jobs, finished) {
    migrating = jobs.some(function (job) { return job.kind === 'migration' && !job.terminal && ['check', 'restore', 'close'].indexOf(job.phase) !== -1; });
    renderJobs(jobs);
    adopt(jobs);
    showWatched(jobs, finished);
    var active = jobs.filter(function (job) { return !job.terminal; });
    if (card) card.setAttribute('data-active', active.length ? '1' : '0');
    /* En «Respaldos» la tarjeta sólo se ve con trabajos en curso que no sean el que ya sigue el panel del botón. Uno que
       estuvo en curso durante la visita y terminó deja la tarjeta con su estado final (un fallo no se pierde); si todos
       los de la visita terminaron bien, la página se recarga con la lista al día. Sin ninguno, sigue oculta. */
    if (card && card.getAttribute('data-hide-idle') === '1') {
      var others = active.filter(function (job) { return job.id !== watching; });
      others.forEach(function (job) { seen[job.id] = true; });
      var visited = Object.keys(seen);
      card.hidden = !others.length && !visited.length;
      if (!others.length && visited.length) {
        var done = jobs.filter(function (job) { return seen[job.id] && job.terminal; });
        if (done.length === visited.length && done.every(function (job) { return job.status === 'success'; })) reloadPage();
      }
    }
    return active;
  }
  function schedule(delay) {
    clearTimeout(timer);
    pendingStep = true;
    timer = setTimeout(step, delay);
  }
  function following() { return !!(watching && watch && restoring(watch.kind)); }
  /* El panel sigue un trabajo cuyo sondeo explica sus cortes (una restauración, o lo que trae o envía un respaldo). */
  function tracking() { return !!(watching && watch && (restoring(watch.kind) || entry(watch.kind))); }
  function applyNonces(nonces) {
    if (!nonces) return;
    if (nonces.jobs) config.jobsNonce = nonces.jobs;
    /* «Respaldar ahora» vuelve al terminar: con el nonce de la sesión de ahora, no con el de la página. */
    if (nonces.manual) config.nonce = nonces.manual;
    var field = nonces.restore && ui.rollback && ui.rollback.querySelector ? ui.rollback.querySelector('input[name="_wpnonce"]') : null;
    if (field) field.value = nonces.restore;
  }
  /* Ni la sesión ni el testigo de este navegador sirven: no se puede seguir desde esta pestaña. Se dice y se para. */
  function lost(error) {
    clearTimeout(timer);
    pendingStep = false;
    stopped = true;
    show(ui.cancel, false);
    note('No se puede seguir el avance desde esta pestaña: tras el cambio de tablas su sesión o sus nonces ya no valen y este navegador no tiene el testigo de la restauración (sólo lo tiene el que la empezó), o terminó hace rato. La restauración sigue en el servidor: recarga esta página o, si te lo pide, inicia sesión para ver cómo va o cómo terminó.', 'error');
    show(ui.login, true);
    show(ui.reload, true);
    if (panel) panel.setAttribute('data-state', 'lost');
  }
  function connectionNote(error) {
    var since = clock(lastOk);
    var text;
    /* Sólo el 503 de la página de mantenimiento de Backups es el de una restauración; otro es el servidor (revisión N2). */
    var maintenance = error && error.status === 503 && error.restore;
    if (maintenance) text = 'El sitio está en mantenimiento por la restauración y esta pestaña no tiene su testigo (sólo lo recibe el navegador que la empezó, si la petición que la empezó llegó a responder): no ve el avance hasta que termine. La restauración sigue sola; se vuelve a consultar cada 30 s y, al terminar, el panel dice cómo acabó o que hay que recargar o iniciar sesión.';
    else if (!error || !error.status) text = 'Sin conexión con el servidor desde las ' + since + ' (' + failures + (failures === 1 ? ' intento' : ' intentos') + '): se reintenta solo. ' + (following() ? 'La restauración sigue en el servidor aunque esta pestaña no la vea.' : 'El trabajo sigue en el servidor aunque esta pestaña no lo vea.');
    else text = 'El servidor no responde (HTTP ' + error.status + '; ' + failures + (failures === 1 ? ' intento' : ' intentos') + ' desde las ' + since + '): se reintenta solo.';
    /* Recargar durante el mantenimiento da la página de mantenimiento de WordPress y se pierde este panel: no se aconseja. */
    if (failures >= 5) text += maintenance ? ' No recargues esta página mientras dure: la terminan WP-Cron, las visitas al sitio o «wp digitalisimo-backups jobs run».' : ' Si sigue así, recarga esta página; si no carga, la terminan WP-Cron, las visitas al sitio o «wp digitalisimo-backups jobs run».';
    note(text, failures >= 5 ? 'error' : 'warning');
  }
  /* Un tick por petición: si otro proceso trabaja (busy) sólo se consulta; si se espera un reintento, se espera. */
  function step() {
    pendingStep = false;
    /* Otra petición en vuelo (la consulta del estado, p. ej. justo al pulsar «Respaldar ahora»): el paso se aplaza, nunca
       se pierde (revisión N3). */
    if (busy) { schedule(500); return; }
    busy = true;
    busySince = Date.now();
    lastPoll = busySince;
    var tick = tickMode === 'restore' ? request(restore && restore.action || config.restoreTick || 'digitalisimo_backups_restore_tick', { digitalisimo_backups_restore: restore && restore.token || '' }, '') : request('digitalisimo_backups_job_tick', {}, config.jobsNonce);
    tick.then(function (data) {
      busy = false;
      if (workingNote) { workingNote = false; note(''); }
      failures = 0;
      sessionFailures = 0;
      lastOk = Date.now();
      applyNonces(data.nonces);
      if (data.nonces && data.nonces.jobs) modeNote = '';
      if (data.manager === false || data.manager === true) sessionLost = !data.manager;
      var follow = following();
      if (tracking()) note(data.result === 'busy' ? 'Otro proceso (WP-Cron o WP-CLI) está con este paso: esta pestaña sólo consulta su avance.' : (follow ? modeNote : ''));
      var active = update(data.jobs || [], data.result === 'finished');
      if (!active.length) return;
      var delay = 300;
      if (data.result === 'busy') delay = 3000;
      else if (data.result !== 'worked') {
        var soonest = 15;
        active.forEach(function (job) { soonest = Math.min(soonest, job.retry_in > 0 ? job.retry_in : (job.running ? 5 : 1)); });
        delay = Math.max(1000, soonest * 1000);
      }
      schedule(delay);
    }).catch(function (error) {
      busy = false;
      if (workingNote) { workingNote = false; note(''); }
      failures++;
      sessionFailures = error && error.session ? sessionFailures + 1 : 0;
      var follow = following();
      /* La migración y la restauración siguen con el testigo cuando la sesión deja de valer (la cookie que recibió este
         navegador al encolarlas); si tampoco vale, la migración pide iniciar sesión y la restauración lo dice. */
      if ((migrating || follow) && error && error.session && tickMode === 'jobs' && sessionFailures === 1) {
        tickMode = 'restore';
        if (!restore) restore = { action: config.restoreTick || 'digitalisimo_backups_restore_tick', token: '' };
        if (follow) note(modeNote = 'La sesión de esta pestaña cambió con la restauración: el avance se sigue con el testigo de este navegador; al terminar, quizá tengas que iniciar sesión otra vez.');
        schedule(1000);
        return;
      }
      if (migrating && sessionFailures >= 2) { sessionReplaced(); return; }
      if (follow && error && error.session) {
        /* Con el testigo no hay restauración en curso (otro navegador la empezó, o terminó hace rato): se prueba una vez la
           sesión; si tampoco, se dice y se para. */
        if (sessionFailures === 1 && tickMode === 'restore') { tickMode = 'jobs'; schedule(1000); return; }
        lost(error);
        return;
      }
      /* Lo que trae o envía un respaldo no tiene testigo: sin sesión (o con el nonce caducado), se dice y se para. */
      if (tracking() && error && error.session) {
        clearTimeout(timer);
        pendingStep = false;
        stopped = true;
        show(ui.cancel, false);
        note('No se puede seguir el avance desde esta pestaña: su sesión o su nonce ya no valen. El trabajo sigue en la cola del servidor (lo continúan WP-Cron o «wp digitalisimo-backups jobs run»): recarga esta página o, si te lo pide, inicia sesión para ver cómo va.', 'error');
        show(ui.login, true);
        show(ui.reload, true);
        return;
      }
      if (tracking()) { show(ui.cancel, false); connectionNote(error); }
      else if (watching && detail) detail.textContent = 'No se pudo avanzar ahora: ' + error.message + '. Se reintentará; WP-Cron también lo continúa.';
      schedule(follow && error && error.status === 503 && error.restore ? 30000 : Math.min(30000, 2000 * failures));
    });
  }
  function cancelJob(id, target, kind) {
    var texts = { restore: '¿Cancelar la restauración? Lo preparado se retira y el sitio queda como estaba; la copia previa, si ya está, se conserva.', chain: '¿Cancelar la restauración del punto incremental? El sitio no se tocó.', convert: '¿Cancelar la restauración del sitio de la red? El sitio no se tocó.', import: '¿Cancelar la importación? Lo convertido hasta ahora se retira; el sitio no se toca y el .tar.gz de Backuply se conserva.', fetch: '¿Cancelar la descarga? Lo descargado a medias se retira; el respaldo sigue en el destino.', send: '¿Cancelar el envío? Lo copiado a medias en el destino se retira; el respaldo del almacén no se toca.' };
    if (!window.confirm(texts[kind] || '¿Cancelar este respaldo? Lo que lleva hecho se descarta; un respaldo ya verificado se conserva en el historial.')) return;
    if (target) target.disabled = true;
    request('digitalisimo_backups_job_cancel', { id: id }, config.jobsNonce).then(function (data) {
      if (target) target.disabled = false;
      update(data.jobs || []);
      schedule(300);
    }).catch(function (error) {
      if (target) target.disabled = false;
      if (error.data && error.data.jobs) update(error.data.jobs);
      window.alert(error.message);
    });
  }

  if (form && destination) form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (button) button.disabled = true;
    watch = null;
    starting('Respaldo', 'Encolando el respaldo…', 'Verificando requisitos antes de empezar.');
    request('digitalisimo_backups_manual_start', { destination: destination.value, remote_only: remoteOnly && remoteOnly.checked ? '1' : '' }, config.nonce).then(function (data) {
      watching = data.id;
      showWatched([data.view]);
      schedule(0);
    }).catch(function (error) {
      if (error.data && error.data.id) { watching = error.data.id; schedule(0); return; }
      if (button) button.disabled = false;
      if (panel) panel.setAttribute('data-state', 'failed');
      message.textContent = 'No se encoló el respaldo.';
      detail.textContent = error.message;
    });
  });

  if (card) card.addEventListener('click', function (event) {
    var target = event.target.closest ? event.target.closest('[data-cancel]') : null;
    if (!target) return;
    event.preventDefault();
    var id = target.getAttribute('data-cancel');
    cancelJob(id, target, watch && watch.id === id ? watch.kind : '');
  });
  if (ui.cancel) ui.cancel.addEventListener('click', function (event) {
    if (event && event.preventDefault) event.preventDefault();
    /* La subida la cancela (o la descarta) su propio script. */
    if (external && watch && watch.kind === 'upload') { if (typeof external.cancel === 'function') external.cancel(ui.cancel); return; }
    cancelJob(ui.cancel.getAttribute('data-cancel'), ui.cancel, watch ? watch.kind : '');
  });
  if (ui.resume) ui.resume.addEventListener('click', function (event) {
    if (event && event.preventDefault) event.preventDefault();
    if (external && typeof external.resume === 'function') external.resume(ui.resume);
  });
  if (ui.reload) ui.reload.addEventListener('click', function () { reloadPage(); });
  /* Lo que el panel dice mientras la petición que empieza cada acción trabaja (y, pasados 40 s, qué hacer). */
  var RESTORE_LATE = 'La petición que empieza la restauración tarda más de lo normal. Si el navegador muestra un error (504, «se agotó el tiempo»), vuelve a Backups: la restauración sigue en el servidor y este panel la retoma. Si la pantalla muestra la página de mantenimiento, espera: la terminan WP-Cron y las visitas al sitio, o «wp digitalisimo-backups jobs run».';
  var QUEUE_LATE = 'La petición tarda más de lo normal. Si el navegador muestra un error (504, «se agotó el tiempo»), vuelve a Backups: el trabajo sigue en la cola del servidor y este panel lo retoma.';
  var FETCH = ['Traer al almacén', 'Empezando la descarga desde el destino…', 'Esta petición descarga lo que cabe en su tiempo; después la siguen esta pantalla y WP-Cron, por fragmentos y desde donde quedó si se corta.', null, QUEUE_LATE];
  var STARTS = {
    digitalisimo_backups_backuply_import: ['Importación', 'Encolando la importación de Backuply…', 'Se convierte por fases (descompresión, comprobación, base de datos y ZIP) sin tocar el sitio; en unos segundos se ve aquí su avance.', null, QUEUE_LATE],
    digitalisimo_backups_fetch: FETCH,
    digitalisimo_backups_remote_fetch: FETCH,
    digitalisimo_backups_send: ['Enviar a un destino', 'Empezando el envío…', 'Se comprueba el respaldo por su SHA-256 y se copia por tramos, verificado por el destino; en unos segundos se ve aquí su avance.', null, QUEUE_LATE],
    /* «Agregar al listado» comprueba el archivo en esta misma petición (minutos con uno grande): no hay pasos que medir. */
    digitalisimo_backups_register_loose: ['Agregando a la lista', 'Comprobando el archivo como no confiable…', 'Firma, cada archivo contra el manifiesto (CRC y SHA-256) y la base de datos en seco, en el servidor y sin pasos que medir: con un archivo grande tarda minutos. Al terminar, la página vuelve a «Respaldos» con el resultado.', [['check', 'Comprobación', 0], ['register', 'Registro', 97]], 'La comprobación tarda más de lo normal. Si el navegador muestra un error (504, «se agotó el tiempo»), vuelve a Herramientas → «Restaurar un archivo suelto»: si el archivo ya no está ahí, se agregó a la lista; si sigue, agrégalo con WP-CLI (wp digitalisimo-backups register <archivo>), que no tiene límite de tiempo.'],
  };
  /* Al confirmar «Restaurar» (la fila, un archivo suelto, «Volver a la copia previa»), el panel dice ya que empieza: la
     petición trabaja hasta 25 s antes de volver con el avance. Lo mismo al pedir una importación, «Traer al almacén»,
     «Enviar a un destino» o «Agregar al listado», con el panel junto al formulario. */
  if (panel && document.addEventListener) document.addEventListener('submit', function (event) {
    var target = event.target;
    var action = target && target.querySelector ? target.querySelector('input[name="action"]') : null;
    var name = action ? action.value : '';
    if (!action || (name !== 'digitalisimo_backups_restore' && !STARTS[name]) || event.defaultPrevented) return;
    /* Un doble clic manda una sola restauración: la segunda petición sólo diría «ya hay una en curso». */
    if (target.getAttribute('data-sent')) { if (event.preventDefault) event.preventDefault(); return; }
    target.setAttribute('data-sent', '1');
    sentForm = target;
    sentAt = Date.now();
    if (target.querySelectorAll) Array.prototype.forEach.call(target.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]'), function (control) { control.setAttribute('aria-disabled', 'true'); });
    watch = null;
    if (STARTS[name]) {
      var start = STARTS[name];
      starting(start[0], start[1], start[2], start[3], target);
      startNote = start[4];
    } else {
      var back = !!target.querySelector('input[name="rollback_retry"]');
      starting(back ? 'Volviendo a la copia previa' : 'Restauración', back ? 'Empezando la vuelta a la copia previa…' : 'Empezando la restauración…', 'El servidor comprueba el respaldo y prepara la copia previa; en unos segundos se ve aquí su avance.', null, target);
      startNote = RESTORE_LATE;
    }
    startedAt = Date.now();
    if (panel.scrollIntoView) panel.scrollIntoView({ block: 'nearest' });
  });
  /* Volver con «Atrás» a esta página (de la caché del navegador) deja enviar otra vez el formulario. */
  if (typeof window.addEventListener === 'function') window.addEventListener('pageshow', function (event) {
    if (!event || !event.persisted || !document.querySelectorAll) return;
    Array.prototype.forEach.call(document.querySelectorAll('form[data-sent]'), function (sent) {
      sent.removeAttribute('data-sent');
      Array.prototype.forEach.call(sent.querySelectorAll('[aria-disabled="true"]'), function (control) { control.removeAttribute('aria-disabled'); });
    });
  });
  /* El reloj del tiempo transcurrido, entre un tick y otro; y si un tramo tarda, que se diga que el servidor trabaja. */
  if (typeof setInterval === 'function') setInterval(function () {
    /* La petición que empieza la restauración trabaja hasta 25 s antes de responder: si tarda más, se dice qué hacer. Mientras
       tanto, cuánto lleva (nunca una barra quieta sin explicación). */
    if (panel && startedAt && panel.getAttribute('data-state') === 'starting') {
      setText(ui.time, 'Lleva ' + duration((Date.now() - startedAt) / 1000));
      if (Date.now() - startedAt > 40000 && ui.note && !ui.note.textContent) note(startNote || RESTORE_LATE, 'warning');
    }
    /* Sin trabajos que seguir, el estado de la cola cada 30 s: una restauración que empezó fuera de esta pestaña se ve. */
    /* La navegación de «Restaurar» se canceló (Esc, «Detener»): a los 60 s el formulario vuelve a estar disponible. */
    if (sentForm && Date.now() - sentAt > 60000) {
      sentForm.removeAttribute('data-sent');
      if (sentForm.querySelectorAll) Array.prototype.forEach.call(sentForm.querySelectorAll('[aria-disabled="true"]'), function (control) { control.removeAttribute('aria-disabled'); });
      sentForm = null;
    }
    /* Sólo con la pestaña a la vista (revisión N6). */
    var visible = typeof document.visibilityState !== 'string' || document.visibilityState === 'visible';
    if (visible && !busy && !pendingStep && !stopped && !sessionLost && !reloading && !watching && Date.now() - lastPoll >= IDLE_POLL) poll();
    /* El reloj también corre para la subida (no es un trabajo: la sigue assets/upload.js). */
    if (!watch || watch.end || (!watching && !(external && external.active))) return;
    paintTime();
    if (watching && busy && !workingNote && Date.now() - busySince > 4000 && ui.note && !ui.note.textContent) {
      workingNote = true;
      note('El servidor está trabajando en este paso (cada tramo dura hasta 20 s): el avance se actualiza al terminarlo.');
    }
  }, 1000);
  /* La consulta del estado sin trabajos: sólo lee (y deja el testigo de una restauración en curso a quien gestiona Backups). */
  function poll() {
    busy = true;
    lastPoll = Date.now();
    request('digitalisimo_backups_job_status', { peek: '1' }, config.jobsNonce).then(function (data) {
      busy = false;
      /* Terminó el mantenimiento que esta pestaña no veía: el panel enseña cómo acabó esa restauración (la más reciente). */
      if (panel && panel.getAttribute('data-state') === 'maintenance') {
        note('');
        panel.setAttribute('data-state', 'idle');
        panel.hidden = true;
        adopt(data.jobs || [], true);
      }
      if (update(data.jobs || []).length) schedule(300);
    }).catch(function (error) {
      busy = false;
      /* Tras el mantenimiento, la sesión ya no vale (la restauración repuso otras cuentas): se dice y se para. */
      if (error && error.session && panel && panel.getAttribute('data-state') === 'maintenance') { lost(error); return; }
      /* El mantenimiento de una restauración que esta pestaña no ve: se dice en el panel y se sigue consultando. */
      if (error && error.status === 503 && error.restore && panel) {
        if (panel.getAttribute('data-state') !== 'maintenance') {
          starting('Sitio en mantenimiento', 'Sin avance a la vista', '');
          panel.setAttribute('data-state', 'maintenance');
          startedAt = 0;
        }
        failures = Math.max(failures, 1);
        connectionNote(error);
        failures = 0;
      } else if (error && error.session) stopped = true;
    });
  }

  /* Lo que no es un trabajo y usa este mismo panel: la subida (assets/upload.js). Le da sus vistas (con la forma de las del
     servidor: kind «upload», fases, %, paso, y al terminar sus líneas y lo que sigue) y sus acciones (cancel, resume).
     claim(): el panel deja lo que enseñaba, salvo una restauración en curso (que no se tapa: devuelve false). */
  window.digitalisimoBackupsPanel = {
    idle: function () { return !!panel && panel.hidden && !watching && !(external && external.active); },
    claim: function () {
      if (!panel) return false;
      if (watching && watch && restoring(watch.kind)) return false;
      watching = '';
      watch = null;
      startedAt = 0;
      note('');
      return true;
    },
    show: function (view, handlers, fresh) {
      if (!panel || !view) return;
      if (handlers) external = handlers;
      if (fresh) watch = null;
      if (external) external.active = !view.terminal;
      paint(view);
    },
    note: function (text, level) { note(text, level); },
    url: function (params, hash) { return screenUrl(params || {}, hash || ''); },
    release: function () { if (external) external.active = false; },
  };

  /* Al cargar: el trabajo que sigue el panel (una restauración en curso o recién terminada, o el respaldo del botón). */
  var initial = null;
  try { initial = panel && panel.getAttribute('data-view') ? JSON.parse(panel.getAttribute('data-view')) : null; } catch (error) { initial = null; }
  if (initial && initial.id) {
    watching = initial.id;
    showWatched([initial]);
    if (!initial.terminal) schedule(300);
  }
  if (card && card.getAttribute('data-active') === '1') schedule(500);
}());
