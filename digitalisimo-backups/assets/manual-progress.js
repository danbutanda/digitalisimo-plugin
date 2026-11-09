/* Cola de respaldos en la pantalla Backups: encola, hace avanzar los trabajos mientras la página está abierta (WP-Cron
   los continúa si se cierra), muestra su estado real y permite cancelarlos. Todo texto del servidor se pinta como texto. */
(function () {
  'use strict';
  var config = window.digitalisimoBackupsManual;
  if (!config) return;
  var form = document.getElementById('digitalisimo-backups-manual-form');
  var card = document.getElementById('digitalisimo-backups-jobs');
  var list = document.getElementById('digitalisimo-backups-jobs-list');
  var panel = document.getElementById('digitalisimo-backups-manual-progress');
  var bar = document.getElementById('digitalisimo-backups-manual-bar');
  var message = document.getElementById('digitalisimo-backups-manual-message');
  var detail = document.getElementById('digitalisimo-backups-manual-detail');
  var destination = form ? form.querySelector('[name="digitalisimo_backup_destination"]') : null;
  var button = form ? form.querySelector('button[type="submit"], button:not([type])') : null;
  var watching = '';
  var timer = null;
  var busy = false;
  var failures = 0;
  var migrating = false;

  function request(action, fields, nonce) {
    var body = new URLSearchParams(Object.assign({ action: action, nonce: nonce, context: config.context }, fields || {}));
    return fetch(config.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
      .then(function (response) { return response.text().then(function (text) {
        var result;
        try { result = JSON.parse(text); } catch (error) { throw new Error('El servidor no devolvió JSON (HTTP ' + response.status + '). Revisa el registro de PHP o el firewall del servidor.'); }
        if (!response.ok || !result.success) {
          var failure = new Error(result.data && result.data.message || 'Error HTTP ' + response.status);
          failure.data = result.data || {};
          throw failure;
        }
        return result.data;
      }); });
  }

  function element(tag, text, attributes) {
    var node = document.createElement(tag);
    if (text !== undefined && text !== null) node.textContent = String(text);
    Object.keys(attributes || {}).forEach(function (name) { node.setAttribute(name, attributes[name]); });
    return node;
  }
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
  function showWatched(jobs) {
    if (!watching || !panel) return;
    var job = null;
    jobs.forEach(function (item) { if (item.id === watching) job = item; });
    if (!job) return;
    panel.hidden = false;
    bar.value = Number(job.percent) || 0;
    message.textContent = job.status_label + (job.phase_label ? ' · ' + job.phase_label : '') + ' · ' + bar.value + '%';
    detail.textContent = job.detail;
    if (!job.terminal) return;
    watching = '';
    if (button) button.disabled = false;
    if (job.status === 'success') detail.textContent = job.detail + '. Actualiza la página para verlo en el historial.';
    else if (job.status === 'failed') detail.textContent = job.detail + ' No se registró ningún respaldo parcial; puedes corregir el error y volver a intentarlo.';
  }
  /* Una migración que llegó a restaurar reemplaza los usuarios: la sesión de este navegador deja de valer. */
  function sessionReplaced() {
    if (!card || card.querySelector('.digitalisimo-backups-migrate-login')) return;
    var note = element('p', 'La migración reemplazó este sitio y tu sesión ya no vale: inicia sesión con un usuario del sitio de origen para ver el resultado. Si algo salió mal, «Deshacer la migración» en Backups → Migrar lo devuelve a su estado anterior.', { 'class': 'notice notice-info inline digitalisimo-backups-migrate-login', role: 'status' });
    var link = element('a', ' Iniciar sesión', { href: config.login || '/wp-login.php' });
    note.appendChild(link);
    card.insertBefore(note, list);
  }
  function update(jobs) {
    migrating = jobs.some(function (job) { return job.kind === 'migration' && !job.terminal && ['check', 'restore', 'finish'].indexOf(job.phase) !== -1; });
    renderJobs(jobs);
    showWatched(jobs);
    var active = jobs.filter(function (job) { return !job.terminal; });
    if (card) card.setAttribute('data-active', active.length ? '1' : '0');
    return active;
  }
  function schedule(delay) {
    clearTimeout(timer);
    timer = setTimeout(step, delay);
  }
  /* Un tick por petición: si otro proceso trabaja (busy) sólo se consulta; si se espera un reintento, se espera. */
  function step() {
    if (busy) return;
    busy = true;
    request('digitalisimo_backups_job_tick', {}, config.jobsNonce).then(function (data) {
      busy = false;
      failures = 0;
      var active = update(data.jobs || []);
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
      if (migrating && failures >= 2) { sessionReplaced(); return; }
      if (watching && detail) detail.textContent = 'No se pudo avanzar ahora: ' + error.message + '. Se reintentará; WP-Cron también lo continúa.';
      schedule(Math.min(30000, 2000 * failures));
    });
  }

  if (form && destination) form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (button) button.disabled = true;
    panel.hidden = false;
    bar.value = 0;
    message.textContent = 'Encolando el respaldo…';
    detail.textContent = 'Verificando requisitos antes de empezar.';
    request('digitalisimo_backups_manual_start', { destination: destination.value }, config.nonce).then(function (data) {
      watching = data.id;
      showWatched([data.view]);
      schedule(0);
    }).catch(function (error) {
      if (error.data && error.data.id) { watching = error.data.id; schedule(0); return; }
      if (button) button.disabled = false;
      message.textContent = 'No se encoló el respaldo.';
      detail.textContent = error.message;
    });
  });

  if (card) card.addEventListener('click', function (event) {
    var target = event.target.closest ? event.target.closest('[data-cancel]') : null;
    if (!target) return;
    event.preventDefault();
    if (!window.confirm('¿Cancelar este respaldo? Lo que lleva hecho se descarta; un respaldo ya verificado se conserva en el historial.')) return;
    target.disabled = true;
    request('digitalisimo_backups_job_cancel', { id: target.getAttribute('data-cancel') }, config.jobsNonce).then(function (data) {
      update(data.jobs || []);
      schedule(300);
    }).catch(function (error) {
      target.disabled = false;
      if (error.data && error.data.jobs) update(error.data.jobs);
      window.alert(error.message);
    });
  });

  if (card && card.getAttribute('data-active') === '1') schedule(500);
}());
