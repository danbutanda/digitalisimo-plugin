/* Pantalla «Servidores» de DIGITALÍSIMO Backups: en cada formulario muestra sólo los campos del tipo elegido y los que
   dependen de otra opción (cifrado de FTP, proveedor compatible con S3, autenticación SFTP…), y «Probar conexión» por
   AJAX sin salir de la página. Sin JavaScript, el mismo botón envía el formulario a admin-post y vuelve con el
   resultado. Todo texto del servidor se pinta como texto. Nunca se guarda nada al probar. */
(function () {
  'use strict';
  var config = window.digitalisimoBackupsDestinations || {};

  function fieldValue(form, key) {
    var field = form.querySelector('[name$="[' + key + ']"]');
    if (!field) return null;
    if (field.type === 'checkbox') return field.checked ? '1' : '';
    return field.value;
  }
  function visible(row, form, type) {
    if (row.getAttribute('data-server') !== type) return false;
    var show = row.getAttribute('data-show');
    if (!show) return true;
    var parts = show.split('=');
    var value = fieldValue(form, parts[0]);
    return value === null || parts[1].split('|').indexOf(value) !== -1;
  }
  function refresh(form) {
    var select = form.querySelector('.digitalisimo-backups-type');
    if (!select) return;
    var type = select.value;
    form.querySelectorAll('[data-server]').forEach(function (row) { row.hidden = !visible(row, form, type); });
  }
  function testResult(button) {
    var holder = button.parentNode && button.parentNode.querySelector('.digitalisimo-backups-test-result');
    return holder || null;
  }
  function runTest(form, button) {
    var result = testResult(button);
    if (!result || !window.fetch || !window.FormData || !config.url) return false;
    var data = new FormData(form);
    data.set('action', config.action);
    data.set('digitalisimo_backups_test', button.value || '');
    var select = form.querySelector('.digitalisimo-backups-type');
    if (!button.value && select) data.set('connection_type', select.value);
    button.disabled = true;
    result.removeAttribute('data-state');
    result.textContent = 'Probando: subiendo, verificando, descargando y borrando un archivo pequeño…';
    fetch(config.url, { method: 'POST', credentials: 'same-origin', body: data })
      .then(function (response) { return response.text().then(function (text) {
        var parsed;
        try { parsed = JSON.parse(text); } catch (error) { throw new Error('El servidor no devolvió JSON (HTTP ' + response.status + ').'); }
        return parsed;
      }); })
      .then(function (parsed) {
        var payload = parsed && parsed.data ? parsed.data : {};
        result.setAttribute('data-state', parsed && parsed.success ? 'ok' : 'error');
        result.textContent = payload.message || (parsed && parsed.success ? 'Conexión correcta.' : 'La prueba falló.');
      })
      .catch(function (error) {
        result.setAttribute('data-state', 'error');
        result.textContent = error.message;
      })
      .then(function () { button.disabled = false; });
    return true;
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.digitalisimo-backups-connection-form').forEach(function (form) {
      refresh(form);
      form.addEventListener('change', function () { refresh(form); });
      form.querySelectorAll('.digitalisimo-backups-test').forEach(function (button) {
        button.addEventListener('click', function (event) {
          if (runTest(form, button)) event.preventDefault();
        });
      });
    });
  });
})();
