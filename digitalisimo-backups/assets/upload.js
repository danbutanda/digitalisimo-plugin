/* Subida de un respaldo por fragmentos desde la pantalla Backups (tarjeta «Subir un respaldo»). Cada fragmento va en un
   formulario multipart a admin-ajax (por debajo de upload_max_filesize) y se anexa en el byte que el servidor espera: si
   la conexión se corta, se reintenta con espera, y si se recarga la página, volver a elegir el mismo archivo continúa
   donde se quedó (el identificador de la subida se recuerda en este navegador). Todo texto del servidor se pinta como
   texto. */
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
          failure.transient = response.status >= 500 && failure.data.code !== 'digitalisimo_backups_upload_write';
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

  function summary(result) {
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
    if (!file) { say('Elige un archivo .zip o .dgbk.'); return; }
    stopped = false;
    currentFile = file;
    if (submit) submit.disabled = true;
    if (cancel) cancel.hidden = false;
    if (bar) bar.hidden = false;
    var known = recall(file);
    var resume = known ? post('status', { id: known }).then(function (session) { return Number(session.size) === file.size ? session : null; }, function () { return null; }) : Promise.resolve(null);
    resume.then(function (session) {
      if (session) { say('Continuando la subida desde ' + mb(session.received) + '…'); return session; }
      return post('start', { name: file.name, size: file.size });
    }).then(function (session) {
      current = session;
      remember(file, session.id);
      return send(file, session).then(function () {
        say('Comprobando el respaldo como archivo no confiable (puede tardar varios minutos)…');
        return post('finish', { id: session.id, secret: secret ? secret.value : '' });
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
