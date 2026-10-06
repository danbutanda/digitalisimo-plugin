(function () {
  'use strict';
  var form = document.getElementById('digitalisimo-backups-manual-form');
  var config = window.digitalisimoBackupsManual;
  if (!form || !config) return;
  var destination = form.querySelector('[name="digitalisimo_backup_destination"]');
  var button = form.querySelector('button[type="submit"], button:not([type])');
  var panel = document.getElementById('digitalisimo-backups-manual-progress');
  var bar = document.getElementById('digitalisimo-backups-manual-bar');
  var message = document.getElementById('digitalisimo-backups-manual-message');
  var detail = document.getElementById('digitalisimo-backups-manual-detail');
  var timer = null;
  var started = 0;

  function request(action, fields) {
    var body = new URLSearchParams(Object.assign({ action: action, nonce: config.nonce }, fields || {}));
    return fetch(config.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
      .then(function (response) { return response.text().then(function (text) {
        var result;
        try { result = JSON.parse(text); } catch (error) { throw new Error('El servidor no devolvió JSON (HTTP ' + response.status + '). Revisa el registro de PHP o el firewall del servidor.'); }
        if (!response.ok || !result.success) throw new Error(result.data && result.data.message || 'Error HTTP ' + response.status);
        return result.data;
      }); });
  }

  function show(job) {
    panel.hidden = false;
    bar.value = Number(job.percent) || 0;
    message.textContent = job.message || 'Preparando respaldo…';
    var elapsed = Math.max(0, Math.floor((Date.now() - started) / 1000));
    detail.textContent = 'Etapa: ' + (job.phase || 'preparación') + ' · ' + elapsed + ' s · avance estimado: ' + bar.value + '%';
    if (job.status === 'complete') {
      clearInterval(timer); button.disabled = false;
      detail.textContent = 'ZIP verificado: ' + job.file + '. Actualiza esta página para verlo en el historial.';
    } else if (job.status === 'failed') {
      clearInterval(timer); button.disabled = false;
      detail.textContent = 'No se registró un respaldo válido. Puedes corregir el error y volver a intentarlo.';
    }
  }

  form.addEventListener('submit', function (event) {
    if (!destination || destination.value !== 'local') return;
    event.preventDefault();
    if (timer) clearInterval(timer);
    button.disabled = true;
    started = Date.now();
    show({ status: 'queued', phase: 'preparación', percent: 0, message: 'Verificando requisitos del respaldo local…' });
    request('digitalisimo_backups_manual_start', { context: config.context, destination: destination.value })
      .then(function (result) {
        var token = result.token;
        show(result.job);
        function poll() {
          request('digitalisimo_backups_manual_status', { token: token }).then(show).catch(function (error) {
            detail.textContent = 'No se pudo consultar el avance: ' + error.message + '. El proceso puede seguir ejecutándose.';
          });
        }
        timer = setInterval(poll, 1500);
        request('digitalisimo_backups_manual_work', { token: token }).then(poll).catch(function (error) {
          detail.textContent = 'La conexión del proceso se interrumpió: ' + error.message + '. Consultando el estado real…';
          poll();
        });
      }).catch(function (error) {
        button.disabled = false;
        show({ status: 'failed', phase: 'preparación', percent: 0, message: error.message });
      });
  });
}());
