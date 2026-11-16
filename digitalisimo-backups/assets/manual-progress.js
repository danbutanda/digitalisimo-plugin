/* Cola de respaldos y panel de avance de la pantalla Backups. Encola el respaldo manual, hace avanzar los trabajos mientras
   la página está abierta (WP-Cron los continúa si se cierra), muestra su estado real y permite cancelarlos. Un solo panel,
   a todo lo ancho, sigue un respaldo o una restauración (docs/BACKUPS.md, «Pantalla»): porcentaje, fase en lenguaje claro
   con sus tramos de la barra, el paso dentro de la fase, el tiempo y, al terminar, el resultado (o el error y la vuelta a
   la copia previa). Una restauración se sigue aunque el cambio de tablas deje sin valor la sesión o los nonces (con el
   testigo de este navegador), durante el mantenimiento (503) y tras un corte de red; al recargar la página, el panel vuelve
   al trabajo en curso; si no puede seguir, lo dice y dice qué hacer. Todo texto del servidor se pinta como texto. */
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
  ['title', 'percent', 'what', 'fill', 'ticks', 'phases', 'time', 'note', 'result', 'cancel', 'reload', 'safety', 'login', 'rollback', 'help'].forEach(function (name) { ui[name] = byId(P + name); });
  var destination = form ? form.querySelector('[name="digitalisimo_backup_destination"]') : null;
  var remoteOnly = form ? form.querySelector('[name="digitalisimo_backup_remote_only"]') : null;
  var button = form ? form.querySelector('button[type="submit"], button:not([type])') : null;
  var watching = '';
  /* Lo que sigue el panel: tipo, cuándo empezó (reloj de este navegador), sus tramos, la restauración que falló (si sigue
     su vuelta a la copia previa) y si ya terminó. */
  var watch = null;
  var timer = null;
  var busy = false;
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

  function request(action, fields, nonce) {
    var body = new URLSearchParams(Object.assign({ action: action, nonce: nonce, context: config.context }, fields || {}));
    return fetch(config.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
      .then(function (response) { return response.text().then(function (text) {
        var result;
        /* La sesión ya no vale: 401/403, o admin-ajax responde «-1» (nonce) o «0» (sin sesión). Un corte del proxy, un
           502/504 o el 503 del mantenimiento durante la restauración no lo son. */
        var session = response.status === 401 || response.status === 403 || ['-1', '0'].indexOf(text.trim()) !== -1;
        try { result = JSON.parse(text); } catch (error) {
          var broken = new Error(response.status === 503 ? 'El sitio está en mantenimiento mientras se aplica la restauración (HTTP 503)' : 'El servidor no devolvió JSON (HTTP ' + response.status + '). Revisa el registro de PHP o el firewall del servidor.');
          broken.session = session;
          broken.status = response.status;
          throw broken;
        }
        if (!response.ok || !result.success) {
          var failure = new Error(result.data && result.data.message || 'Error HTTP ' + response.status);
          failure.data = result.data || {};
          failure.session = session;
          failure.status = response.status;
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
      ['backup', 'message', 'restore'].forEach(function (name) { if (url.searchParams.has(name)) { url.searchParams.delete(name); changed = true; } });
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
    if (job.kind === 'chain' || job.kind === 'import' || job.kind === 'migration') return job.kind;
    return 'backup';
  }
  function restoring(kind) { return kind === 'restore' || kind === 'rollback' || kind === 'chain'; }
  var TITLES = {
    backup: ['Respaldo en curso', 'Respaldo terminado', 'El respaldo no se completó', 'Respaldo cancelado'],
    chain: ['Preparando la restauración del punto incremental', 'Punto incremental reconstruido', 'El punto incremental no se pudo reconstruir', 'Restauración cancelada'],
    restore: ['Restauración en curso', 'Restauración terminada', 'La restauración no se completó', 'Restauración cancelada', 'La restauración falló a mitad del cambio'],
    rollback: ['Volviendo a la copia previa', 'El sitio volvió a la copia previa', 'La vuelta a la copia previa no terminó', 'Vuelta a la copia previa cancelada', 'La vuelta a la copia previa no terminó'],
    import: ['Importación en curso', 'Importación terminada', 'La importación no se completó', 'Importación cancelada'],
    migration: ['Migración en curso', 'Migración terminada', 'La migración no se completó', 'Migración cancelada'],
  };
  var STATE_TEXT = { done: 'hecha: ', current: 'en curso: ', failed: 'se detuvo aquí: ', stopped: 'cancelada aquí: ', pending: 'pendiente: ' };
  function title(kind, job) {
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
    var started = /\d{2}:\d{2}$/.test(watch.created || '') ? watch.created.slice(-5) : '';
    var seconds = ((watch.end || Date.now()) - watch.base) / 1000;
    setText(ui.time, (started ? 'Empezó a las ' + started + ' · ' : '') + (watch.end ? 'duró ' : 'lleva ') + duration(seconds));
  }
  /* Nota del sondeo (corte, mantenimiento, sesión): nunca una barra quieta sin explicación. */
  function note(text, level) {
    if (!ui.note) return;
    setText(ui.note, text || '');
    ui.note.setAttribute('data-level', level || 'info');
    show(ui.note, !!text);
  }
  function paintBar(percent, label, indeterminate) {
    if (ui.fill && ui.fill.style) ui.fill.style.width = (indeterminate ? 100 : percent) + '%';
    if (bar) {
      if (indeterminate) { if (bar.removeAttribute) bar.removeAttribute('aria-valuenow'); bar.setAttribute('aria-valuetext', label); }
      else { bar.setAttribute('aria-valuenow', String(percent)); bar.setAttribute('aria-valuetext', percent + ' %' + (label ? ' · ' + label : '')); }
    }
    setText(ui.percent, indeterminate ? '…' : percent + ' %');
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
  function starting(heading, text, more) {
    if (!panel) return;
    panel.hidden = false;
    panel.setAttribute('data-state', 'starting');
    setText(ui.title, heading);
    setText(ui.what, '');
    paintBar(0, text, true);
    if (ui.phases) { empty(ui.phases); show(ui.phases, false); }
    setText(message, text);
    setText(detail, more || '');
    setText(ui.time, '');
    note('');
    if (ui.result) { empty(ui.result); show(ui.result, false); }
    ['cancel', 'reload', 'safety', 'login', 'rollback'].forEach(function (name) { show(ui[name], false); });
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
  function safetyLink(file) {
    if (!ui.safety || !file) return;
    var href = ui.safety.getAttribute('href') || '';
    try {
      var url = new URL(href || window.location.href, window.location.href);
      ['backup', 'message', 'open', 'parts'].forEach(function (name) { url.searchParams.delete(name); });
      url.searchParams.set('tab', 'backups');
      url.searchParams.set('restore', file);
      url.hash = 'digitalisimo-backups-history';
      href = url.toString();
    } catch (error) { /* sin URL: el enlace del servidor */ }
    ui.safety.setAttribute('href', href);
    ui.safety.setAttribute('title', 'Abre la copia previa ' + file + ' para restaurarla: el sitio vuelve a como estaba antes.');
    show(ui.safety, true);
  }
  /* El resultado al terminar: qué se restauró, sus avisos y la copia previa; o el error y qué pasó con el sitio. */
  function paintResult(kind, job) {
    if (!ui.result) return;
    empty(ui.result);
    var failed = watch && watch.failed;
    if (kind === 'rollback') {
      var cause = job.rollback_cause || (failed && (failed.error || failed.detail)) || '';
      var of = job.rollback_file || (failed && failed.destination_label) || '';
      paragraph('La restauración' + (of ? ' de ' + of : '') + ' falló a mitad del cambio' + (cause ? ': ' + cause : '.'), 'digitalisimo-backups-warning');
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
      if (report.removed) paragraph('Se retiraron ' + report.removed + ' archivos que el respaldo no tenía' + (report.kept ? ' (' + report.kept + ' cambiados después se apartaron en el almacén)' : '') + '.');
    } else if (job.notices && job.notices.length) paragraph('Avisos: ' + job.notices.join(' '), 'digitalisimo-backups-warning');
    if (kind === 'restore' && job.status === 'success' && job.safety) paragraph('La copia previa ' + job.safety + ' queda bloqueada en el historial: si algo no quedó bien, «Volver a la copia previa» la restaura.');
    if (sessionLost) paragraph('Esta pestaña perdió la sesión con el cambio de tablas (la restauración repuso los usuarios del respaldo): inicia sesión para ver el resultado completo.', 'digitalisimo-backups-warning');
    show(ui.result, ui.result.firstChild !== null);
  }
  /* Pinta un trabajo en el panel. */
  function paint(job) {
    if (!panel) return;
    var kind = kindOf(job);
    if (!watch || watch.id !== job.id) watch = { id: job.id, kind: kind, origin: watch ? watch.base : 0, base: Date.now(), created: watch && watch.created || job.created_label || '', track: watch && watch.track, failed: watch && watch.failed, end: 0, ticks: watch && watch.ticks };
    /* El tiempo lo da el servidor (desde que se encoló); en una sucesión de trabajos, desde el primero. */
    if (job.elapsed !== undefined && !watch.end) watch.base = Date.now() - (Number(job.elapsed) || 0) * 1000;
    if (watch.origin) watch.base = Math.min(watch.origin, watch.base);
    watch.kind = kind;
    if (job.track && job.track.length) watch.track = job.track;
    var track = watch.track || [];
    var state = job.terminal ? job.status : 'running';
    panel.hidden = false;
    panel.setAttribute('data-state', state);
    panel.setAttribute('data-kind', kind);
    /* Un reintento devuelve el % al comienzo de su fase aunque se retome desde su punto de control: la barra no retrocede. */
    var percent = Math.max(watch.top || 0, Math.min(100, Number(job.percent) || 0));
    if (!job.terminal || job.status === 'success') watch.top = percent;
    if (job.terminal && job.status !== 'success') percent = Math.max(0, Math.min(100, Number(job.percent) || percent));
    var at = -1;
    track.forEach(function (item, index) { if (item[0] === job.phase) at = index; });
    setText(ui.title, title(kind, job));
    var what = '';
    if (kind === 'restore') what = 'Respaldo ' + job.destination_label + ' · se restaura ' + (job.parts && job.parts !== 'todo' ? 'sólo: ' + job.parts : 'todo');
    else if (kind === 'rollback') what = 'Copia previa ' + job.destination_label + (job.rollback_file ? ' · deshace la restauración de ' + job.rollback_file : '');
    else if (job.destination_label) what = job.origin_label + ' · ' + job.destination_label;
    setText(ui.what, what);
    paintBar(percent, job.terminal ? job.status_label : job.phase_label, false);
    paintTrack(track, job.phase, state);
    setText(message, job.status_label + (job.phase_label ? ' · ' + job.phase_label : '') + (job.phase_label && at >= 0 && track.length > 1 ? ' (fase ' + (at + 1) + ' de ' + track.length + ')' : ''));
    setText(detail, job.terminal && restoring(kind) ? '' : job.detail);
    if (job.terminal && !watch.end) watch.end = Date.now();
    paintTime();
    show(ui.cancel, !!job.can_cancel && !job.terminal);
    if (ui.cancel) ui.cancel.setAttribute('data-cancel', job.id);
    show(ui.help, restoring(kind) && !job.terminal);
    if (restoring(kind)) {
      quietStrip();
      if (button) button.disabled = !job.terminal;
    }
    if (!job.terminal) { if (ui.result) { empty(ui.result); show(ui.result, false); } ['reload', 'safety', 'login', 'rollback'].forEach(function (name) { show(ui[name], false); }); return; }
    if (!restoring(kind)) return;
    paintResult(kind, job);
    show(ui.reload, true);
    if (kind === 'restore' && job.status === 'success') safetyLink(job.safety);
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
      /* La restauración terminó: lo que siga (la cola de respaldos) vuelve al tick con nonce. */
      tickMode = 'jobs';
      return;
    }
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
  function update(jobs, finished) {
    migrating = jobs.some(function (job) { return job.kind === 'migration' && !job.terminal && ['check', 'restore', 'close'].indexOf(job.phase) !== -1; });
    renderJobs(jobs);
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
    timer = setTimeout(step, delay);
  }
  function following() { return !!(watching && watch && restoring(watch.kind)); }
  function applyNonces(nonces) {
    if (!nonces) return;
    if (nonces.jobs) config.jobsNonce = nonces.jobs;
    var field = nonces.restore && ui.rollback && ui.rollback.querySelector ? ui.rollback.querySelector('input[name="_wpnonce"]') : null;
    if (field) field.value = nonces.restore;
  }
  /* Ni la sesión ni el testigo de este navegador sirven: no se puede seguir desde esta pestaña. Se dice y se para. */
  function lost(error) {
    clearTimeout(timer);
    note('No se puede seguir el avance desde esta pestaña: tu sesión ya no vale (la restauración repuso los usuarios del respaldo) y este navegador no tiene su testigo, o terminó hace rato. La restauración sigue en el servidor: inicia sesión para ver cómo terminó.', 'error');
    show(ui.login, true);
    show(ui.reload, true);
    if (panel) panel.setAttribute('data-state', 'lost');
  }
  function connectionNote(error) {
    var since = clock(lastOk);
    var text;
    if (error && error.status === 503) text = 'El sitio está en mantenimiento por la restauración y esta pestaña no tiene su testigo (la empezó otro navegador o se borraron sus cookies): no ve el avance hasta que termine. La restauración sigue sola; se vuelve a consultar cada 30 s.';
    else if (!error || !error.status) text = 'Sin conexión con el servidor desde las ' + since + ' (' + failures + (failures === 1 ? ' intento' : ' intentos') + '): se reintenta solo. La restauración sigue en el servidor aunque esta pestaña no la vea.';
    else text = 'El servidor no respondió bien (HTTP ' + error.status + '; ' + failures + (failures === 1 ? ' intento' : ' intentos') + ' desde las ' + since + '): se reintenta solo.';
    if (failures >= 5) text += ' Si sigue así, recarga esta página; si no carga, la terminan WP-Cron, las visitas al sitio o «wp digitalisimo-backups jobs run».';
    note(text, failures >= 5 ? 'error' : 'warning');
  }
  /* Un tick por petición: si otro proceso trabaja (busy) sólo se consulta; si se espera un reintento, se espera. */
  function step() {
    if (busy) return;
    busy = true;
    var tick = tickMode === 'restore' ? request(restore && restore.action || config.restoreTick || 'digitalisimo_backups_restore_tick', { digitalisimo_backups_restore: restore && restore.token || '' }, '') : request('digitalisimo_backups_job_tick', {}, config.jobsNonce);
    tick.then(function (data) {
      busy = false;
      failures = 0;
      sessionFailures = 0;
      lastOk = Date.now();
      applyNonces(data.nonces);
      if (data.nonces && data.nonces.jobs) modeNote = '';
      if (data.manager === false || data.manager === true) sessionLost = !data.manager;
      var follow = following();
      if (follow) note(data.result === 'busy' ? 'Otro proceso (WP-Cron o WP-CLI) está con este paso: esta pestaña sólo consulta su avance.' : modeNote);
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
      if (follow) connectionNote(error);
      else if (watching && detail) detail.textContent = 'No se pudo avanzar ahora: ' + error.message + '. Se reintentará; WP-Cron también lo continúa.';
      schedule(follow && error && error.status === 503 ? 30000 : Math.min(30000, 2000 * failures));
    });
  }
  function cancelJob(id, target, kind) {
    var texts = { restore: '¿Cancelar la restauración? Lo preparado se retira y el sitio queda como estaba; la copia previa, si ya está, se conserva.', chain: '¿Cancelar la restauración del punto incremental? El sitio no se tocó.' };
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
    cancelJob(ui.cancel.getAttribute('data-cancel'), ui.cancel, watch ? watch.kind : '');
  });
  if (ui.reload) ui.reload.addEventListener('click', function () { reloadPage(); });
  /* Al confirmar «Restaurar» (la fila, un archivo suelto, «Volver a la copia previa»), el panel dice ya que empieza: la
     petición trabaja hasta 25 s antes de volver con el avance. */
  if (panel && document.addEventListener) document.addEventListener('submit', function (event) {
    var target = event.target;
    var action = target && target.querySelector ? target.querySelector('input[name="action"]') : null;
    if (!action || action.value !== 'digitalisimo_backups_restore' || event.defaultPrevented) return;
    watch = null;
    var back = !!target.querySelector('input[name="rollback_retry"]');
    starting(back ? 'Volviendo a la copia previa' : 'Restauración', back ? 'Empezando la vuelta a la copia previa…' : 'Empezando la restauración…', 'El servidor comprueba el respaldo y prepara la copia previa; en unos segundos se ve aquí su avance.');
    if (panel.scrollIntoView) panel.scrollIntoView({ block: 'nearest' });
  });
  /* El reloj del tiempo transcurrido, entre un tick y otro. */
  if (typeof setInterval === 'function') setInterval(function () { if (watch && !watch.end && watching) paintTime(); }, 1000);

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
