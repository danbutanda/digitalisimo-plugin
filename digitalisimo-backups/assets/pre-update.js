/*
 * Respaldo antes de actualizar (I1), en las pantallas de actualización (las de la red también).
 *
 * Antes de enviar una actualización manual de un tipo con «Manuales y automáticas» (los formularios de
 * update-core.php y las actualizaciones por AJAX de plugins y temas, `wp.updates.ajax`), pide al servidor el respaldo
 * previo: si ya hay uno reciente válido sigue enseguida; si no, el servidor lo encola y esta página lo hace avanzar
 * (cada consulta es un tick corto de la cola, dentro del límite de la petición) mostrando su avance. Cuando termina,
 * envía la actualización. Si falla, pregunta si actualizar sin respaldo: con «Aceptar», el servidor deja actualizar
 * sin respaldo unos minutos; con «Cancelar», el formulario no se envía y una actualización por AJAX la rechaza el
 * servidor con su motivo. Todo texto del servidor se pinta como texto.
 */
(function () {
  'use strict';
  var cfg = window.digitalisimoBackupsPreUpdate;
  if (!cfg || !cfg.types) return;
  var state = { ready: false, pending: null };
  var box = null;
  var later = function (fn, ms) { return window.setTimeout(fn, ms); };

  function say(text, kind) {
    if (!box) {
      box = document.createElement('div');
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      box.appendChild(document.createElement('p'));
      var wrap = document.querySelector('.wrap');
      var title = wrap ? wrap.querySelector('h1') : null;
      if (title && title.parentNode) title.parentNode.insertBefore(box, title.nextSibling);
      else (wrap || document.body).insertBefore(box, (wrap || document.body).firstChild);
    }
    box.className = 'notice notice-' + (kind || 'info') + ' digitalisimo-backups-pre-update-notice';
    box.firstChild.textContent = String(text);
  }
  function post(fields) {
    var body = new FormData();
    body.append('nonce', cfg.nonce);
    Object.keys(fields).forEach(function (key) { body.append(key, fields[key]); });
    return fetch(cfg.url, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) { return response.json(); });
  }

  /** Promesa que se cumple con true para actualizar (respaldo listo o «sin respaldo» aceptado) o con false. */
  function ensure() {
    if (state.ready) return Promise.resolve(true);
    if (state.pending) return state.pending;
    state.pending = new Promise(function (resolve) {
      var errors = 0;
      function handle(reply) {
        var data = (reply && reply.data) || {};
        if (!reply || !reply.success) return fail(data.message || 'El servidor no respondió como se esperaba.');
        errors = 0;
        if ('ready' === data.state) {
          state.ready = true;
          say(cfg.text.ready, 'success');
          return resolve(true);
        }
        if ('running' === data.state && data.job && data.job.id) {
          say(cfg.text.running + ' ' + (parseInt(data.job.percent, 10) || 0) + ' % · ' + (data.job.message || ''), 'info');
          return later(function () { poll(data.job.id); }, 2000);
        }
        fail(data.message || 'El respaldo previo falló.');
      }
      function poll(id) {
        post({ action: cfg.status, job: id }).then(handle, function () {
          // Un corte de la conexión o del proxy no es un fallo del respaldo: vuelve a preguntar.
          errors++;
          if (errors > 5) return fail('Se perdió la conexión con el servidor mientras se creaba el respaldo.');
          later(function () { poll(id); }, 5000);
        });
      }
      function fail(message) {
        if (window.confirm(String(cfg.text.ask).replace('%s', String(message)))) {
          post({ action: cfg.skip }).then(function (reply) {
            state.ready = !!(reply && reply.success);
            if (!state.ready) say((reply && reply.data && reply.data.message) || cfg.text.declined, 'error');
            resolve(state.ready);
          }, function () { say(cfg.text.declined, 'error'); resolve(false); });
          return;
        }
        say(cfg.text.declined + ' ' + String(message), 'error');
        resolve(false);
      }
      post({ action: cfg.start }).then(handle, function () { fail('No se pudo contactar con el servidor.'); });
    }).then(function (ok) { state.pending = null; return ok; });
    return state.pending;
  }

  // Formularios de Escritorio → Actualizaciones (update-core.php).
  var forms = { 'upgrade': 'core', 'upgrade-plugins': 'plugin', 'upgrade-themes': 'theme' };
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var type = form && form.getAttribute ? forms[form.getAttribute('name')] : null;
    if (!type || !cfg.types[type] || state.ready) return;
    event.preventDefault();
    var submitter = event.submitter || null;
    ensure().then(function (ok) {
      if (!ok) return;
      // submit() no envía el botón pulsado: va como campo oculto (p. ej. «upgrade» o «reinstall»).
      if (submitter && submitter.name) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = submitter.name;
        input.value = submitter.value;
        form.appendChild(input);
      }
      HTMLFormElement.prototype.submit.call(form);
    });
  }, true);

  // Actualizaciones por AJAX de plugins y temas (Plugins, Temas, acciones en lote).
  var actions = { 'update-plugin': 'plugin', 'update-theme': 'theme' };
  var wp = window.wp;
  var $ = window.jQuery;
  if (wp && wp.updates && 'function' === typeof wp.updates.ajax && !wp.updates.ajax.digitalisimoPreUpdate && $ && $.Deferred) {
    var original = wp.updates.ajax;
    var wrapped = function (action) {
      var type = actions[action];
      if (!type || !cfg.types[type] || state.ready) return original.apply(this, arguments);
      var self = this;
      var args = arguments;
      var deferred = $.Deferred();
      // Sin respaldo y con «Cancelar», la petición sale igual: el servidor la rechaza y la fila muestra el motivo.
      ensure().then(function () {
        var request = original.apply(self, args);
        if (request && 'function' === typeof request.then) request.then(deferred.resolve, deferred.reject);
        else deferred.resolve(request);
      });
      return deferred.promise();
    };
    wrapped.digitalisimoPreUpdate = true;
    wp.updates.ajax = wrapped;
  }
})();
