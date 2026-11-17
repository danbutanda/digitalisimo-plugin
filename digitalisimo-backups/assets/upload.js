/* Subida de un respaldo por fragmentos desde la pantalla Backups (tarjeta «Subir un respaldo»). Cada fragmento va en un
   formulario multipart a admin-ajax (por debajo de upload_max_filesize) y se anexa en el byte que el servidor espera: si
   la conexión se corta, se reintenta con espera, y si se recarga la página, volver a elegir el mismo archivo continúa
   donde se quedó (el identificador de la subida se recuerda en este navegador). El cierre se comprueba en el servidor:
   si tarda más que el proxy o la conexión se corta, se consulta su estado hasta el resultado (nunca se sube otra vez).
   Mientras se comprueba, el estado se consulta también en paralelo: si la respuesta del cierre no llega nunca (un proxy
   que retiene la conexión, como en el ensayo de /wp del 2026-11-12), el panel llega igual a 100 % y dice «verificado» en
   cuanto el servidor dice phase=done. Cada petición tiene plazo.
   El avance no se pinta en la tarjeta: lo sigue el panel de avance de la pantalla, el mismo del respaldo y de la
   restauración (assets/manual-progress.js, window.digitalisimoBackupsPanel), en el hueco bajo el formulario, con tres
   fases: «Subida» (bytes enviados de los totales, velocidad, tiempo restante y reintentos), «Comprobación» (en el
   servidor y sin pasos medibles: la barra espera y dice en qué paso va) y «Registro»; al terminar, el resultado con su
   fila para restaurarlo (o por qué se descartó o quedó «sin registro»). Al recargar la página, el panel vuelve a la subida
   que se está comprobando o enseña la que quedó a medias. Todo texto del servidor se pinta como texto. */
(function () {
  'use strict';
  var config = window.digitalisimoBackupsUpload;
  var card = document.getElementById('digitalisimo-backups-upload');
  if (!config || !card) return;
  var form = card.querySelector('.digitalisimo-backups-upload-form');
  if (!form) return;
  var input = form.querySelector('input[type="file"]');
  var secret = form.querySelector('input[name="digitalisimo_backups_upload_secret"]');
  var submit = form.querySelector('button[type="submit"]');
  /* Sólo lo que pide el formulario antes de empezar («Elige un archivo…»): el avance y el resultado van en el panel. */
  var status = form.querySelector('.digitalisimo-backups-upload-status');
  var storage = null;
  try { storage = window.localStorage; } catch (error) { storage = null; }
  var current = null;
  var currentFile = null;
  var stopped = false;
  form.hidden = false;

  function panel() { return window.digitalisimoBackupsPanel || null; }
  function say(text) { if (status) status.textContent = String(text); }
  function note(text, level) { var p = panel(); if (p) p.note(text, level); }
  function mb(bytes) { return (Number(bytes) / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
  function speedText(bytes) { return bytes >= 1048576 ? mb(bytes) + '/s' : Math.max(1, Math.round(bytes / 1024)) + ' KB/s'; }
  function duration(seconds) {
    var s = Math.max(0, Math.round(seconds));
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    return h ? h + ' h ' + m + ' min' : (m ? m + ' min ' + (s % 60) + ' s' : (s % 60) + ' s');
  }
  function two(n) { return (n < 10 ? '0' : '') + n; }
  function stamp(ms) { var d = new Date(ms); return d.getFullYear() + '-' + two(d.getMonth() + 1) + '-' + two(d.getDate()) + ' ' + two(d.getHours()) + ':' + two(d.getMinutes()); }
  function key(file) { return 'digitalisimo-backups-upload:' + config.context + ':' + file.name + ':' + file.size + ':' + file.lastModified; }
  /* La subida que esta pestaña tenía en marcha: al recargar, el panel enseña su resultado si terminó mientras tanto. */
  var ACTIVE = 'digitalisimo-backups-upload-active:' + config.context;
  function remember(file, id) {
    try {
      if (!storage) return;
      if (file) { if (id) storage.setItem(key(file), id); else storage.removeItem(key(file)); }
      if (id) storage.setItem(ACTIVE, id); else storage.removeItem(ACTIVE);
    } catch (error) { /* Sin almacenamiento local la subida sigue; sólo no se reanuda tras recargar. */ }
  }
  function recall(file) {
    try { return storage ? storage.getItem(file ? key(file) : ACTIVE) : null; } catch (error) { return null; }
  }
  function wait(ms) { return new Promise(function (resolve) { window.setTimeout(resolve, ms); }); }
  /* Plazo de cada petición (ms): una que nunca responde se corta y cuenta como un corte de red (se reintenta o se consulta
     el estado). El cierre no tiene plazo: su resultado también llega por el estado. */
  var LIMITS = { start: 60000, chunk: 180000, status: 45000, cancel: 60000 };
  /* Cuánto esperar antes de consultar el estado mientras el cierre no responde. */
  var STATUS_AFTER = typeof config.statusAfter === 'number' ? config.statusAfter : 5000;

  function post(step, fields, hold) {
    var controller = typeof AbortController === 'function' ? new AbortController() : null;
    if (hold) hold(controller);
    var limit = LIMITS[step] || 0;
    var timer = controller && limit ? window.setTimeout(function () { controller.abort(); }, limit) : 0;
    function clear() { if (timer && typeof window.clearTimeout === 'function') window.clearTimeout(timer); }
    var body = new FormData();
    body.append('action', 'digitalisimo_backups_upload_' + step);
    body.append('nonce', config.nonce);
    body.append('context', config.context);
    Object.keys(fields || {}).forEach(function (name) {
      var value = fields[name];
      if (value && typeof value === 'object' && typeof value.slice === 'function') body.append(name, value, 'fragmento.bin');
      else body.append(name, String(value));
    });
    var options = { method: 'POST', credentials: 'same-origin', body: body };
    if (controller) options.signal = controller.signal;
    return fetch(config.url, options).then(function (response) {
      clear();
      return response.text().then(function (text) {
        var result;
        try { result = JSON.parse(text); } catch (error) {
          var invalid = new Error('El servidor no devolvió JSON (HTTP ' + response.status + '). Revisa el registro de PHP o el firewall del servidor.');
          invalid.transient = response.status >= 500 || response.status === 408 || response.status === 429;
          throw invalid;
        }
        if (!response.ok || !result.success) {
          var failure = new Error(result.data && result.data.message || 'Error HTTP ' + response.status);
          failure.data = result.data || {};
          failure.status = response.status;
          // Con código, el servidor explicó el motivo (espacio, cortes, frase): se muestra y no se repite en bucle.
          failure.transient = response.status >= 500 && !failure.data.code;
          throw failure;
        }
        return result.data;
      });
    }, function () {
      clear();
      var lost = new Error('Se perdió la conexión con el servidor (o no respondió a tiempo).');
      lost.transient = true;
      throw lost;
    });
  }

  /* ------------------------------------------------------------------ Lo que ve el panel de avance */

  /* Tres fases: la subida llena la barra hasta el 85 %, la comprobación (sin pasos medibles) hasta el 97 % y el registro el
     resto. La barra nunca retrocede (el panel guarda lo más alto que enseñó). */
  var TRACK = [['upload', 'Subida', 0], ['check', 'Comprobación', 85], ['register', 'Registro', 97]];
  var LABELS = { upload: 'Subida', check: 'Comprobación', register: 'Registro' };
  var CHECK_TEXT = 'Comprobando el respaldo en el servidor como archivo no confiable: firma, cifrado, cada archivo contra el manifiesto y la base de datos en seco. Sigue aunque se corte la conexión; con un archivo grande tarda minutos.';
  var run = null;
  /* Una subida nueva empieza su barra desde cero; continuar la misma sesión (tras una pausa) la conserva. */
  function begin(name, size, fresh, age) {
    if (run && !fresh) { run.name = name; run.size = Number(size) || 0; run.samples = []; run.phase = 'upload'; return; }
    var began = Date.now() - 1000 * (Number(age) || 0);
    run = { name: name, size: Number(size) || 0, received: 0, phase: 'upload', began: began, created: stamp(began), samples: [] };
  }
  function percentOf() {
    if (run.phase === 'check') return 85;
    if (run.phase === 'register') return 97;
    return run.size ? Math.min(85, Math.floor(85 * run.received / run.size)) : 0;
  }
  function view(over) {
    var phase = run.phase;
    var base = {
      id: 'upload', kind: 'upload', status: phase === 'upload' ? 'running' : 'verifying', status_label: phase === 'upload' ? 'En curso' : 'Verificando',
      phase: phase, phase_label: LABELS[phase], percent: percentOf(), detail: '', terminal: false, running: true, retry_in: 0,
      can_cancel: phase === 'upload' && !!current && !stopped, origin_label: 'Subida', destination_label: run.name + ' (' + mb(run.size) + ')',
      created_label: run.created, track: TRACK, elapsed: Math.max(0, (Date.now() - run.began) / 1000), waiting: phase !== 'upload',
      title: phase === 'upload' ? '' : 'Comprobando el respaldo subido',
    };
    Object.keys(over || {}).forEach(function (name) { base[name] = over[name]; });
    return base;
  }
  function paint(over, fresh) { var p = panel(); if (p && run) p.show(view(over), handlers, fresh); }
  /* Velocidad de los últimos 30 s y tiempo restante: con menos de 2 s medidos, todavía no. */
  function sample() {
    var now = Date.now();
    run.samples.push([now, run.received]);
    while (run.samples.length > 2 && now - run.samples[0][0] > 30000) run.samples.shift();
  }
  function rate() {
    var s = run.samples;
    if (s.length < 2) return 0;
    var seconds = (s[s.length - 1][0] - s[0][0]) / 1000;
    return seconds >= 2 ? Math.max(0, s[s.length - 1][1] - s[0][1]) / seconds : 0;
  }
  function uploadDetail() {
    var text = mb(run.received) + ' de ' + mb(run.size);
    var speed = rate();
    if (speed > 0) {
      var left = Math.max(0, run.size - run.received) / speed;
      text += ' · ' + speedText(speed) + ' · ' + (left >= 1 ? 'quedan unos ' + duration(left) : 'casi terminada');
    } else if (run.received < run.size) text += ' · midiendo la velocidad…';
    return text;
  }
  /* El paso de la comprobación que anota el servidor (su vista de pantalla): qué se comprueba y lo que va diciendo. */
  function checkDetail(state) {
    var check = state && state.check || {};
    if (check.step === 'register') run.phase = 'register';
    var parts = [check.text || CHECK_TEXT];
    if (check.detail) parts.push(check.detail);
    if (Number(state && state.attempts) > 1) parts.push('intento ' + Number(state.attempts) + ' de ' + (Number(state.max_attempts) || 3));
    return parts.join(' · ');
  }
  function screenUrl(params, hash) { var p = panel(); return p && typeof p.url === 'function' ? p.url(params, hash) : ''; }

  /** Envía los fragmentos desde lo que el servidor ya tiene; corrige el desplazamiento si el servidor dice otro. */
  function send(file, session) {
    var received = Number(session.received) || 0;
    var size = Number(session.size);
    // El tamaño lo fija el servidor (por debajo de lo que acepta el hosting); el cliente nunca lo agranda.
    var chunk = Number(session.chunk_size) > 0 ? Number(session.chunk_size) : 1048576;
    var failures = 0;
    run.received = received;
    sample();
    paint({ detail: received > 0 ? 'Continuando desde ' + mb(received) + ' de ' + mb(size) + ': lo recibido no se sube otra vez.' : 'Enviando el primer fragmento (' + mb(Math.min(chunk, size)) + ')…' });
    function step() {
      if (stopped) return Promise.reject(new Error('Subida cancelada.'));
      run.received = received;
      // Con algo enviado en esta tanda: bytes, velocidad y tiempo restante.
      if (run.samples.length > 1) paint({ detail: uploadDetail() });
      if (received >= size) return Promise.resolve();
      return post('chunk', { id: session.id, offset: received, chunk: file.slice(received, Math.min(size, received + chunk)) }).then(function (data) {
        received = Number(data.received);
        run.received = received;
        sample();
        // El reintento funcionó: la nota de la espera ya no hace falta.
        if (failures) { failures = 0; note(''); }
        return step();
      }, function (error) {
        var data = error.data || {};
        if (typeof data.received === 'number') received = data.received;
        if (data.code === 'digitalisimo_backups_upload_offset') return step();
        if ((error.transient || data.code === 'digitalisimo_backups_upload_busy') && failures < 6) {
          failures++;
          var delay = Math.min(30000, 1000 * Math.pow(2, failures));
          note(error.message + ' Se reintentará en ' + Math.round(delay / 1000) + ' s (' + failures + ' de 6); lo recibido no se sube otra vez.', 'warning');
          return wait(delay).then(step);
        }
        throw error;
      });
    }
    return step();
  }

  /**
   * Cierra la subida y espera su resultado. El cierre comprueba el archivo en el servidor (puede tardar minutos): su
   * respuesta puede no llegar nunca (un proxy que corta o retiene la conexión), así que el estado se consulta a la vez y
   * gana lo primero que diga el resultado. Si nadie lo está comprobando (el proceso del servidor murió), se vuelve a cerrar.
   * Nunca se sube otra vez. Con $follow (al recargar la página a media comprobación) no se cierra: sólo se consulta el
   * estado, y si nadie la comprueba se dice y se ofrece terminarla («Terminar la comprobación»).
   */
  function close(session, follow) {
    run.phase = 'check';
    paint({ detail: follow ? checkDetail(session) : CHECK_TEXT });
    return new Promise(function (resolve, reject) {
      var settled = false;
      var pending = null;
      var attempt = 0;
      var answered = true;
      var restarts = 0;
      var idle = 0;
      var polls = 0;
      var started = Date.now();
      function end(callback, value) {
        if (settled) return;
        settled = true;
        if (pending && typeof pending.abort === 'function') { try { pending.abort(); } catch (error) { /* ya terminó */ } }
        callback(value);
      }
      function finish() {
        var mine = ++attempt;
        answered = false;
        post('finish', { id: session.id, secret: secret ? secret.value : '' }, function (controller) { pending = controller; }).then(function (result) {
          if (mine === attempt) answered = true;
          end(resolve, result);
        }, function (error) {
          // El rechazo de un cierre anterior (abortado al volver a cerrar) no cuenta: el nuevo sigue en vuelo.
          if (mine !== attempt) return;
          answered = true;
          var code = error.data && error.data.code || '';
          // 202 «se está comprobando» o un corte: el resultado llegará por el estado.
          if (code === 'digitalisimo_backups_upload_validating') { if (!settled) note('La comprobación sigue en otro proceso del servidor: esta pestaña consulta su estado.', 'info'); return; }
          if (error.transient) { if (!settled) note('Se cortó la conexión del cierre (un proxy o el tiempo límite del servidor): la comprobación sigue en el servidor y esta pestaña consulta su estado.', 'warning'); return; }
          end(reject, error);
        });
      }
      function tick() {
        if (settled) return;
        if (Date.now() - started < STATUS_AFTER) { wait(1000).then(tick); return; }
        polls++;
        post('status', { id: session.id }).then(function (state) {
          if (settled) return;
          if (state.phase === 'done' && state.result) { end(resolve, state.result); return; }
          if (state.phase === 'rejected') { var rejected = new Error(state.error && state.error.message || 'El archivo no era un respaldo válido.'); rejected.data = { code: 'digitalisimo_backups_upload_invalid' }; end(reject, rejected); return; }
          if (state.phase === 'validating' && state.validating) { idle = 0; paint({ detail: checkDetail(state) }); return; }
          if (state.error && answered) { var kept = new Error(state.error.message); kept.data = { code: state.error.code }; end(reject, kept); return; }
          // Nadie lo comprueba: si el cierre ya contestó (o no contesta y van dos consultas así), se vuelve a cerrar.
          idle++;
          if (follow && idle >= 2) { var cut = new Error('El proceso del servidor que la comprobaba se cortó.'); cut.interrupted = state; end(reject, cut); return; }
          if (!follow && (answered || idle >= 2) && restarts < 6) {
            restarts++;
            idle = 0;
            note('Nadie la estaba comprobando: el proceso del servidor se cortó (corte ' + Math.max(1, Number(state.attempts) || 1) + ' de ' + (Number(state.max_attempts) || 3) + ' antes de pedir WP-CLI). Se vuelve a cerrar, sin subirla otra vez.', 'warning');
            if (pending && typeof pending.abort === 'function') { try { pending.abort(); } catch (error) { /* ya terminó */ } }
            finish();
          }
        }, function (error) {
          if (!error.transient || polls > 400) end(reject, error);
        }).then(function () {
          if (!settled) wait(Math.min(15000, 3000 + 1000 * polls)).then(tick);
        });
      }
      if (!follow) finish();
      tick();
    });
  }

  /* El resultado: dónde quedó (su fila en la lista, para restaurarlo), o por qué quedó «sin registro»; un .tar.gz de
     Backuply, para importarlo desde el mismo panel. */
  function finished(result) {
    run.phase = 'register';
    var lines = [];
    var heading = '';
    var go = null;
    var importFile = '';
    if (result.container === 'backuply') {
      heading = 'Respaldo de Backuply subido';
      importFile = result.backuply && result.backuply.name || result.file;
      lines.push({ text: 'Quedó en el almacén privado como ' + result.file + ': impórtalo para convertirlo en un ZIP de Backups (no toca el sitio). La importación se sigue en este mismo panel.' });
    } else if (result.listed) {
      lines.push({ text: result.duplicate ? 'Ese mismo respaldo ya estaba en la lista como ' + result.file + ': no se guardó otra copia.' : 'Ya está en la lista de respaldos, arriba, marcado «' + (result.uploaded && result.uploaded.label || 'Subido') + '»: ' + result.file + '.' });
      var row = [];
      if (result.date) row.push(String(result.date).slice(0, 16));
      if (result.size) row.push(mb(result.size));
      if (result.uploaded && result.uploaded.same_site) row.push('de este mismo sitio (firmado con su clave)');
      else if (result.uploaded && result.uploaded.home) row.push('de ' + result.uploaded.home);
      if (row.length) lines.push({ text: 'Su fila: ' + row.join(' · ') + '.' });
      lines.push({ text: 'Para restaurarlo escribe ' + result.confirmation + (result.needs_secret ? ' y su frase o su clave' : '') + '.' });
      go = result.restorable === false ? { text: 'Ver en la lista', href: screenUrl({ tab: 'backups' }, 'digitalisimo-backups-history') } : { text: 'Restaurarlo', href: screenUrl({ tab: 'backups', restore: result.file }, 'digitalisimo-backups-history'), title: 'Abre su fila en la lista con «Restaurar» desplegado.' };
    } else {
      heading = 'Respaldo subido, sin registro';
      lines.push({ text: 'Quedó en el almacén como ' + result.file + ', «sin registro»: no está en la lista de respaldos.', warning: true });
      go = { text: 'Agregarlo a la lista', href: screenUrl({ tab: 'tools', open: 'restore-file' }, 'digitalisimo-backups-section-restore-file'), title: 'Herramientas → «Restaurar un archivo suelto»: «Agregar al listado» con su frase.' };
    }
    (result.problems || []).forEach(function (problem) { lines.push({ text: problem, warning: true }); });
    if (result.wp_config && result.wp_config.text) lines.push({ text: result.wp_config.text });
    paint({ terminal: true, status: 'success', status_label: 'Completo', phase: 'register', phase_label: '', percent: 100, waiting: false, can_cancel: false, running: false, title: heading, lines: lines, go: go, import_file: importFile, detail: lines[0].text });
  }
  /* Se detuvo: por qué y qué hacer (continuarla, terminarla con WP-CLI o descartarla); descartada, sin nada que continuar. */
  function failed(error) {
    var code = error.data && error.data.code || '';
    var discarded = /upload_(invalid|checksum)$/.test(code);
    var gone = /upload_(missing|owner)$/.test(code);
    var lines = [{ text: error.message, warning: true }];
    if (!discarded && !gone && current) lines.push({ text: /upload_timeout$/.test(code) ? 'La subida se conserva en el servidor; o descártala con «Cancelar».' : 'La subida se conserva: vuelve a elegir el mismo archivo y pulsa «Subir respaldo» para continuar donde se quedó (lo recibido no se sube otra vez), o descártala con «Cancelar».' });
    paint({ terminal: true, status: 'failed', status_label: 'Fallido', phase_label: '', title: discarded ? 'El respaldo se descartó' : '', lines: lines, go: null, waiting: false, running: false, can_cancel: !!current && !discarded && !gone, detail: error.message });
  }
  /* Al recargar: una subida a medias (o completa sin cerrar) espera a que se vuelva a elegir su archivo, o a terminarla. */
  function paused(session) {
    var complete = Number(session.received) >= Number(session.size);
    var lines = [{ text: mb(session.received) + ' de ' + mb(session.size) + ' recibidos de ' + session.name + '.' }];
    if (session.error && session.error.message) lines.push({ text: session.error.message, warning: true });
    lines.push({ text: complete ? 'Ya llegó entero: «Terminar la comprobación» lo comprueba sin subirlo otra vez (si está cifrado con una clave que este sitio no tiene, escribe antes su frase arriba).' : 'Para continuar, vuelve a elegir el mismo archivo arriba y pulsa «Subir respaldo»: sigue desde ahí, sin subir otra vez lo recibido. Sin actividad, se descarta sola a las 24 h.' });
    run.received = Number(session.received) || 0;
    paint({ terminal: true, status: 'paused', status_label: 'En pausa', phase: 'upload', phase_label: '', title: complete ? 'Subida completa, sin comprobar' : 'Subida en pausa', lines: lines, go: null, resumable: complete, can_cancel: true, waiting: false, running: false, elapsed: null, detail: lines[0].text });
  }
  function interrupted(state) {
    var lines = [{ text: 'El proceso del servidor que la comprobaba se cortó (' + Math.max(1, Number(state.attempts) || 1) + ' de ' + (Number(state.max_attempts) || 3) + ' cortes antes de pedir WP-CLI). Lo recibido sigue en el servidor: termínala sin subirla otra vez.', warning: true }, { text: 'Si estaba cifrado con una clave que este sitio no tiene, escribe arriba su frase antes de terminarla.' }];
    paint({ terminal: true, status: 'paused', status_label: 'Interrumpida', phase: 'check', phase_label: '', title: 'La comprobación se interrumpió', lines: lines, go: null, resumable: true, can_cancel: true, waiting: false, running: false, detail: lines[0].text });
  }
  /* El final de un cierre: el resultado, o por qué se detuvo (una comprobación que nadie hace, tras recargar, se ofrece terminar). */
  function settle(promise, file) {
    return promise.then(function (result) {
      remember(file, null);
      current = null;
      if (secret) secret.value = '';
      finished(result);
    }).catch(function (error) {
      var code = error.data && error.data.code || '';
      // Descartada por el servidor (no válida, SHA-256 distinto o caducada): no hay nada que continuar.
      if (/upload_(invalid|checksum|missing|owner)$/.test(code)) { remember(file, null); current = null; }
      if (stopped) return;
      if (error.interrupted) { interrupted(error.interrupted); return; }
      failed(error);
    });
  }

  /* «Cancelar» del panel (mientras se envían fragmentos) o «Descartar» una subida en pausa; «Terminar la comprobación». */
  var handlers = {
    cancel: function (button) {
      if (!current) return;
      if (typeof window.confirm === 'function' && !window.confirm('¿Cancelar la subida? Se borra lo recibido y habría que subirla otra vez.')) return;
      stopped = true;
      var id = current.id;
      remember(currentFile, null);
      current = null;
      if (button) button.disabled = true;
      paint({ can_cancel: false, detail: 'Cancelando la subida…' });
      post('cancel', { id: id }).then(function () {
        paint({ terminal: true, status: 'cancelled', status_label: 'Cancelado', phase_label: '', lines: [{ text: 'Subida cancelada; se borró lo recibido.' }], go: null, can_cancel: false, waiting: false, running: false, detail: 'Subida cancelada; se borró lo recibido.' });
      }, function (error) { note('No se pudo cancelar la subida: ' + error.message, 'error'); }).then(function () { if (button) button.disabled = false; });
    },
    resume: function (button) {
      if (!current) return;
      stopped = false;
      if (button) button.disabled = true;
      if (submit) submit.disabled = true;
      note('');
      settle(close(current, false), null).then(function () {
        if (button) button.disabled = false;
        if (submit) submit.disabled = false;
      });
    },
  };

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var file = input && input.files && input.files[0];
    if (!file) { say('Elige un archivo .zip, .dgbk o .tar.gz.'); return; }
    var p = panel();
    if (p && typeof p.claim === 'function' && !p.claim()) { say('Hay una restauración en curso y el panel de avance la sigue: sube el respaldo cuando termine.'); return; }
    say('');
    stopped = false;
    currentFile = file;
    if (submit) submit.disabled = true;
    var known = recall(file);
    // Continuar la sesión que el panel enseñaba en pausa conserva su barra; otra subida empieza la suya.
    var fresh = !(current && known && current.id === known);
    if (fresh) current = null;
    begin(file.name, file.size, fresh);
    paint({ detail: known ? 'Buscando la subida anterior de este archivo…' : 'Preparando la subida…' }, fresh);
    var resume = known ? post('status', { id: known }).then(function (session) { return Number(session.size) === file.size && session.phase !== 'cancelled' ? session : null; }, function () { return null; }) : Promise.resolve(null);
    var flow = resume.then(function (session) {
      if (session && session.phase === 'done' && session.result) return { done: session.result };
      if (session && session.phase === 'rejected') return { session: null };
      return { session: session || null };
    }).then(function (found) {
      if (found.done) return found.done;
      var start = found.session ? Promise.resolve(found.session) : post('start', { name: file.name, size: file.size });
      return start.then(function (session) {
        current = session;
        remember(file, session.id);
        if (session.phase === 'validating') return close(session, false);
        return send(file, session).then(function () { return close(session, false); });
      });
    });
    settle(flow, file).then(function () {
      if (submit) submit.disabled = false;
    });
  });

  /* Al cargar la página: la subida de esta cuenta que se está comprobando (el panel la sigue hasta su resultado), la que quedó
     a medias (en pausa, con qué hacer) o la que esta pestaña tenía en marcha y terminó mientras tanto (su resultado). Sólo si
     el panel no sigue otra cosa (una restauración, una importación). */
  (function resumeOnLoad() {
    var p = panel();
    if (!p || typeof p.idle !== 'function' || !p.idle()) return;
    var sessions = [];
    try { sessions = JSON.parse(card.getAttribute('data-sessions') || '[]') || []; } catch (error) { sessions = []; }
    var pointer = recall(null);
    var pick = null;
    sessions.forEach(function (session) { if (session.phase === 'validating' && (!pick || session.id === pointer)) pick = session; });
    if (pick) {
      current = pick;
      begin(pick.name, pick.size, true, pick.age);
      run.received = Number(pick.size) || 0;
      if (submit) submit.disabled = true;
      settle(close(pick, true), null).then(function () { if (submit) submit.disabled = false; });
      return;
    }
    if (sessions.length) {
      current = sessions[0];
      begin(current.name, current.size, true, current.age);
      paused(current);
      return;
    }
    if (!pointer) return;
    post('status', { id: pointer }).then(function (state) {
      if (!panel() || !panel().idle()) return;
      begin(state.name, state.size, true, state.age);
      if (state.phase === 'done' && state.result) { remember(null, null); finished(state.result); }
      else if (state.phase === 'rejected') { remember(null, null); failed({ message: state.error && state.error.message || 'El archivo no era un respaldo válido y se descartó.', data: { code: 'digitalisimo_backups_upload_invalid' } }); }
    }, function () { remember(null, null); });
  }());
})();
