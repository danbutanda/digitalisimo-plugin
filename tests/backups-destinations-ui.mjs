// Pantalla «Servidores» de Backups (assets/destinations.js): muestra sólo las filas del tipo elegido y las que dependen
// de otra opción (cifrado FTP, proveedor S3…), y «Probar conexión» envía el formulario por AJAX con la acción y la
// conexión, sin recargar, y pinta la respuesta como texto (nunca HTML del servidor), también un error.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync('digitalisimo-backups/assets/destinations.js', 'utf8');
assert.ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(script), 'La respuesta del servidor debe pintarse como texto');

function element(tag, attributes = {}, extra = {}) {
  return Object.assign({
    tag, attributes: { ...attributes }, listeners: {}, hidden: false, disabled: false, text: '', parentNode: null,
    set textContent(value) { this.text = String(value); }, get textContent() { return this.text; },
    getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; },
    setAttribute(name, value) { this.attributes[name] = String(value); },
    removeAttribute(name) { delete this.attributes[name]; },
    addEventListener(type, listener) { this.listeners[type] = listener; },
  }, extra);
}
const typeSelect = element('select', { class: 'digitalisimo-backups-type' }, { value: 'ftp' });
const security = element('select', { name: 'digitalisimo_backups[ftp_security]' }, { value: 'explicit', type: 'select-one' });
const insecure = element('input', { name: 'digitalisimo_backups[ftp_insecure]' }, { type: 'checkbox', checked: false });
const rows = {
  ftpHost: element('tr', { 'data-server': 'ftp' }),
  ftpCa: element('tr', { 'data-server': 'ftp', 'data-show': 'ftp_security=explicit|implicit' }),
  sftpHost: element('tr', { 'data-server': 'sftp' }),
  s3Region: element('tr', { 'data-server': 's3compat', 'data-show': 's3compat_provider=digitalocean' }),
  ftpWarn: element('tr', { 'data-server': 'ftp', 'data-show': 'ftp_insecure=1' }),
};
const result = element('span', { class: 'digitalisimo-backups-test-result' });
const actions = element('p', {}, { querySelector: (selector) => (selector === '.digitalisimo-backups-test-result' ? result : null) });
const button = element('button', { class: 'digitalisimo-backups-test' }, { value: 'ftp', parentNode: actions });
const fields = { 'digitalisimo_backups[ftp_host]': 'ftp.ejemplo.test', 'digitalisimo_backups[ftp_password]': 'Clave-1', _wpnonce: 'nonce-save' };
const form = element('form', { class: 'digitalisimo-backups-connection-form' }, {
  querySelector(selector) {
    if (selector === '.digitalisimo-backups-type') return typeSelect;
    if (selector === '[name$="[ftp_security]"]') return security;
    if (selector === '[name$="[ftp_insecure]"]') return insecure;
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '[data-server]') return Object.values(rows);
    if (selector === '.digitalisimo-backups-test') return [button];
    return [];
  },
});
const listeners = {};
const requests = [];
const replies = [];
class FakeFormData {
  constructor(source) { this.entries = new Map(Object.entries(source === form ? fields : {})); }
  set(name, value) { this.entries.set(name, String(value)); }
  get(name) { return this.entries.get(name); }
}
const context = {
  window: { digitalisimoBackupsDestinations: { url: 'https://example.test/wp-admin/admin-ajax.php', action: 'digitalisimo_backups_test_destination' }, fetch: true, FormData: true },
  document: { addEventListener: (type, listener) => { listeners[type] = listener; }, querySelectorAll: (selector) => (selector === '.digitalisimo-backups-connection-form' ? [form] : []) },
  FormData: FakeFormData,
  fetch: (url, options) => { requests.push({ url, options }); return replies.shift(); },
  console,
};
context.window.fetch = context.fetch;
context.window.FormData = FakeFormData;
runInNewContext(script, context);
listeners.DOMContentLoaded();

// Sólo las filas del tipo elegido y las que cumplen su condición.
assert.equal(rows.ftpHost.hidden, false, 'Una fila del tipo elegido quedó oculta');
assert.equal(rows.ftpCa.hidden, false, 'La CA propia de FTPS explícito quedó oculta');
assert.equal(rows.sftpHost.hidden, true, 'Una fila de otro tipo quedó visible');
assert.equal(rows.ftpWarn.hidden, true, 'Una fila dependiente sin su condición quedó visible');
security.value = 'none';
insecure.checked = true;
form.listeners.change();
assert.equal(rows.ftpCa.hidden, true, 'Con FTP sin cifrar la CA propia siguió visible');
assert.equal(rows.ftpWarn.hidden, false, 'La fila de la casilla marcada no apareció');
typeSelect.value = 'sftp';
form.listeners.change();
assert.ok(rows.sftpHost.hidden === false && rows.ftpHost.hidden === true, 'Cambiar de tipo no cambió las filas visibles');

// «Probar conexión»: AJAX con la acción, la conexión y el formulario; la respuesta se pinta como texto.
const settle = () => new Promise((resolve) => setTimeout(resolve, 0));
const reply = (status, body) => Promise.resolve({ status, text: () => Promise.resolve(typeof body === 'string' ? body : JSON.stringify(body)) });
replies.push(reply(200, { success: true, data: { message: '<img src=x onerror=alert(1)> Conexión con FTP/FTPS correcta.' } }));
let prevented = false;
button.listeners.click({ preventDefault: () => { prevented = true; } });
assert.ok(prevented, 'El botón con JavaScript envió además el formulario (recarga)');
assert.equal(button.disabled, true, 'El botón no se desactivó mientras prueba');
await settle(); await settle(); await settle();
const sent = requests[0];
assert.equal(sent.url, 'https://example.test/wp-admin/admin-ajax.php');
assert.equal(sent.options.method, 'POST');
assert.equal(sent.options.credentials, 'same-origin');
assert.equal(sent.options.body.get('action'), 'digitalisimo_backups_test_destination');
assert.equal(sent.options.body.get('digitalisimo_backups_test'), 'ftp');
assert.equal(sent.options.body.get('_wpnonce'), 'nonce-save');
assert.equal(result.textContent, '<img src=x onerror=alert(1)> Conexión con FTP/FTPS correcta.', 'La respuesta no se pintó tal cual como texto');
assert.equal(result.getAttribute('data-state'), 'ok');
assert.equal(button.disabled, false, 'El botón no se reactivó');
// Un error del servidor (JSON con success=false) y una respuesta que no es JSON.
replies.push(reply(200, { success: false, data: { message: 'La prueba de FTP/FTPS falló: el servidor rechazó el usuario.' } }));
button.listeners.click({ preventDefault() {} });
await settle(); await settle(); await settle();
assert.equal(result.getAttribute('data-state'), 'error');
assert.ok(result.textContent.includes('rechazó el usuario'), 'El error del servidor no se mostró');
replies.push(reply(500, '<html>Fatal</html>'));
button.listeners.click({ preventDefault() {} });
await settle(); await settle(); await settle();
assert.equal(result.getAttribute('data-state'), 'error');
assert.ok(result.textContent.includes('HTTP 500'), 'Una respuesta que no es JSON no se explicó');
console.log('DIGITALÍSIMO Backups: «Servidores» muestra los campos de cada destino y prueba la conexión por AJAX, pintando la respuesta como texto.');
