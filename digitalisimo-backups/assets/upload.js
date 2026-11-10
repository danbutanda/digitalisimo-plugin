/* Subida de un respaldo por fragmentos desde la pantalla Backups (tarjeta «Subir un respaldo»). Cada fragmento va en un
   formulario multipart a admin-ajax (por debajo de upload_max_filesize) y se anexa en el byte que el servidor espera: si
   la conexión se corta, se reintenta con espera, y si se recarga la página, volver a elegir el mismo archivo continúa
   donde se quedó (el identificador de la subida se recuerda en este navegador). El cierre se comprueba en el servidor:
   si tarda más que el proxy o la conexión se corta, se consulta su estado hasta el resultado (nunca se sube otra vez).
   Todo texto del servidor se pinta como texto. */
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
  var cancel = form.querySelector('[data-upload-cancel]');
  var bar = form.querySelector('progress');
  var status = form.querySelector('.digitalisimo-backups-upload-status');
  var storage = null;
  try { storage = window.localStorage; } catch (error) { storage = null; }
  var current = null;
  var currentFile = null;
  var stopped = false;
  form.hidden = false;

  function say(text) { if (status) status.textContent = String(text); }
  function mb(bytes) { return (Number(bytes) / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
  function key(file) { return 'digitalisimo-backups-upload:' + config.context + ':' + file.name + ':' + file.size + ':' + file.lastModified; }
  function remember(file, id) {
    try {
      if (!storage || !file) return;
      if (id) storage.setItem(key(file), id); else storage.removeItem(key(file));
    } catch (error) { /* Sin almacenamiento local la subida sigue; sólo no se reanuda tras recargar. */ }
  }
  function recall(file) {
    try { return storage ? storage.getItem(key(file)) : null; } catch (error) { return null; }
  }
  function wait(ms) { return new Promise(function (resolve) { window.setTimeout(resolve, ms); }); }

  function post(step, fields) {
    var body = new FormData();
    body.append('action', 'digitalisimo_backups_upload_' + step);
    body.append('nonce', config.nonce);
    body.append('context', config.context);
    Object.keys(fields || {}).forEach(function (name) {
      var value = fields[name];
      if (value && typeof value === 'object' && typeof value.slice === 'function') body.append(name, value, 'fragmento.bin');
      else body.append(name, String(value));
    });
    return fetch(config.url, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) {
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
      var lost = new Error('Se perdió la conexión con el servidor.');
      lost.transient = true;
      throw lost;
    });
  }

  /** Envía los fragmentos desde lo que el servidor ya tiene; corrige el desplazamiento si el servidor dice otro. */
  function send(file, session) {
    var received = Number(session.received) || 0;
    var size = Number(session.size);
    // El tamaño lo fija el servidor (por debajo de lo que acepta el hosting); el cliente nunca lo agranda.
    var chunk = Number(session.chunk_size) > 0 ? Number(session.chunk_size) : 1048576;
    var failures = 0;
    function step() {
      if (stopped) return Promise.reject(new Error('Subida cancelada.'));
      if (bar) bar.value = size ? Math.floor(100 * received / size) : 0;
      say('Subiendo ' + file.name + ': ' + mb(received) + ' de ' + mb(size) + '…');
      if (received >= size) return Promise.resolve();
      return post('chunk', { id: session.id, offset: received, chunk: file.slice(received, Math.min(size, received + chunk)) }).then(function (data) {
        received = Number(data.received);
        failures = 0;
        return step();
      }, function (error) {
        var data = error.data || {};
        if (typeof data.received === 'number') received = data.received;
        if (data.code === 'digitalisimo_backups_upload_offset') return step();
        if ((error.transient || data.code === 'digitalisimo_backups_upload_busy') && failures < 6) {
          failures++;
          var delay = Math.min(30000, 1000 * Math.pow(2, failures));
          say(error.message + ' Se reintentará en ' + Math.round(delay / 1000) + ' s (' + failures + ' de 6).');
          return wait(delay).then(step);
        }
        throw error;
      });
    }
    return step();
  }

  /** Espera el resultado de una comprobación en curso (o cortada): consulta el estado; si el proceso murió, la reanuda. */
  function settle(session) {
    var polls = 0;
    function poll() {
      return wait(Math.min(15000, 3000 + 1000 * polls)).then(function () {
        polls++;
        return post('status', { id: session.id });
      }).then(function (state) {
        if (state.phase === 'done' && state.result) return state.result;
        if (state.phase === 'rejected') { var rejected = new Error(state.error && state.error.message || 'El archivo no era un respaldo válido.'); rejected.data = { code: 'digitalisimo_backups_upload_invalid' }; throw rejected; }
        if (state.phase === 'validating' && state.validating) { say('Comprobando el respaldo… (sigue en el servidor aunque se haya cortado la conexión)'); return poll(); }
        if (state.error) { var kept = new Error(state.error.message); kept.data = { code: state.error.code }; throw kept; }
        return close(session);
      }, function (error) {
        if (error.transient && polls < 200) return poll();
        throw error;
      });
    }
    return poll();
  }
  /** Cierra la subida; ante 202 (otro proceso la comprueba) o un corte, consulta el estado en vez de volver a subirla. */
  function close(session) {
    say('Comprobando el respaldo como archivo no confiable (puede tardar varios minutos)…');
    return post('finish', { id: session.id, secret: secret ? secret.value : '' }).catch(function (error) {
      var code = error.data && error.data.code || '';
      if (code === 'digitalisimo_backups_upload_validating' || error.transient) return settle(session);
      throw error;
    });
  }

  function summary(result) {
    if (result.container === 'backuply') return 'Respaldo de Backuply subido: ' + result.file + '. Impórtalo en «Respaldos de Backuply» (recarga la página) para convertirlo en un ZIP de Backups y restaurarlo.';
    var text = 'Respaldo subido y comprobado: ' + result.file + '. ';
    if (result.needs_secret) text += 'Está cifrado con una clave que este sitio no tiene: al restaurarlo escribe su frase o su clave y ';
    else text += 'Para restaurarlo ';
    text += 'escribe ' + result.confirmation + '. ';
    if (result.problems && result.problems.length) text += result.problems.join(' ') + ' ';
    if (result.wp_config && result.wp_config.text) text += result.wp_config.text + ' ';
    return text + 'Recarga la página para verlo en «Restaurar» como «sin registro».';
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var file = input && input.files && input.files[0];
    if (!file) { say('Elige un archivo .zip, .dgbk o .tar.gz.'); return; }
    stopped = false;
    currentFile = file;
    if (submit) submit.disabled = true;
    if (cancel) cancel.hidden = false;
    if (bar) bar.hidden = false;
    var known = recall(file);
    var resume = known ? post('status', { id: known }).then(function (session) { return Number(session.size) === file.size && session.phase !== 'cancelled' ? session : null; }, function () { return null; }) : Promise.resolve(null);
    resume.then(function (session) {
      if (session && session.phase === 'done' && session.result) return { done: session.result };
      if (session && session.phase === 'rejected') return { session: null };
      if (session) { say('Continuando la subida desde ' + mb(session.received) + '…'); return { session: session }; }
      return { session: null };
    }).then(function (found) {
      if (found.done) return found.done;
      var start = found.session ? Promise.resolve(found.session) : post('start', { name: file.name, size: file.size });
      return start.then(function (session) {
        current = session;
        remember(file, session.id);
        if (session.phase === 'validating') return settle(session);
        return send(file, session).then(function () { return close(session); });
      });
    }).then(function (result) {
      remember(file, null);
      current = null;
      if (secret) secret.value = '';
      if (bar) bar.value = 100;
      say(summary(result));
    }).catch(function (error) {
      var code = error.data && error.data.code || '';
      // Descartada por el servidor (no válida, SHA-256 distinto o caducada): no hay nada que continuar.
      if (/upload_(invalid|checksum|missing|owner)$/.test(code)) { remember(file, null); current = null; }
      if (stopped) say('Subida cancelada.');
      else say('La subida se detuvo: ' + error.message + (current ? ' Vuelve a elegir el mismo archivo para continuar donde se quedó.' : ''));
    }).then(function () {
      if (submit) submit.disabled = false;
      if (cancel) cancel.hidden = true;
    });
  });

  if (cancel) cancel.addEventListener('click', function () {
    stopped = true;
    if (!current) return;
    var id = current.id;
    remember(currentFile, null);
    current = null;
    post('cancel', { id: id }).then(function () { say('Subida cancelada; se borró lo recibido.'); }, function (error) { say('No se pudo cancelar la subida: ' + error.message); });
  });
})();
