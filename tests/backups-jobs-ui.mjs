// La pantalla Backups hace avanzar la cola mientras está abierta, pinta el estado como texto (nunca HTML del servidor),
// espera cuando otro proceso trabaja o hay un reintento, se detiene al terminar y cancela con su propio nonce.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync('digitalisimo-backups/assets/manual-progress.js', 'utf8');
assert.ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(script), 'El estado del servidor debe pintarse como texto');

function node(tag) {
  return {
    tag, attributes: {}, children: [], listeners: {}, hidden: false, disabled: false, value: '', text: '',
    set textContent(value) { this.text = String(value); this.children = []; },
    get textContent() { return this.text + this.children.map((child) => child.textContent).join(''); },
    get firstChild() { return this.children[0] || null; },
    appendChild(child) { this.children.push(child); return child; },
    insertBefore(child, ref) { const at = this.children.indexOf(ref); if (at < 0) this.children.push(child); else this.children.splice(at, 0, child); return child; },
    removeChild(child) { this.children = this.children.filter((item) => item !== child); return child; },
    setAttribute(name, value) { this.attributes[name] = String(value); },
    getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; },
    addEventListener(type, listener) { this.listeners[type] = listener; },
    querySelector(selector) { return this.selectors ? this.selectors[selector] || null : null; },
  };
}
const ids = {};
for (const id of ['digitalisimo-backups-manual-form', 'digitalisimo-backups-jobs', 'digitalisimo-backups-jobs-list', 'digitalisimo-backups-manual-progress', 'digitalisimo-backups-manual-bar', 'digitalisimo-backups-manual-message', 'digitalisimo-backups-manual-detail']) ids[id] = node('div');
const destination = node('select');
destination.value = 'google';
const submit = node('button');
ids['digitalisimo-backups-manual-form'].selectors = { '[name="digitalisimo_backup_destination"]': destination, 'button[type="submit"], button:not([type])': submit };
ids['digitalisimo-backups-jobs'].setAttribute('data-active', '1');

const requests = [];
const replies = [];
const timers = [];
const alerts = [];
const job = (overrides) => Object.assign({ id: '11111111-2222-4333-8444-555555555555', status: 'running', status_label: 'En curso', phase: 'database', phase_label: 'Base de datos', percent: 10, detail: '<img src=x onerror=alert(1)>', origin_label: 'Respaldo manual', destination_label: 'Google Drive', created_label: '2026-11-05 10:00', can_cancel: true, terminal: false, running: true, retry_in: 0 }, overrides);
const context = {
  window: { digitalisimoBackupsManual: { url: '/wp-admin/admin-ajax.php', nonce: 'nonce-manual', jobsNonce: 'nonce-jobs', context: 'network', login: '/wp-login.php?redirect_to=migrar' }, confirm: () => true, alert: (text) => alerts.push(text) },
  document: { getElementById: (id) => ids[id] || null, createElement: node, createTextNode: (text) => ({ textContent: String(text), children: [] }) },
  URLSearchParams, Object, String, Number, Math, Error, JSON,
  setTimeout: (callback, delay) => { timers.push({ callback, delay }); return timers.length; },
  clearTimeout: () => {},
  fetch: (url, options) => {
    const body = new URLSearchParams(options.body);
    requests.push(Object.fromEntries(body));
    const reply = replies.shift();
    assert.ok(reply !== undefined, 'Petición inesperada: ' + body.get('action'));
    return Promise.resolve({ ok: reply.ok !== false, status: reply.status || 200, text: () => Promise.resolve(typeof reply.body === 'string' ? reply.body : JSON.stringify(reply.body)) });
  },
};
context.window.document = context.document;
const flush = () => new Promise((resolve) => setImmediate(resolve));
async function runTimer() {
  const timer = timers.shift();
  assert.ok(timer !== undefined, 'No quedó un paso programado');
  timer.callback();
  for (let i = 0; i < 5; i++) await flush();
  return timer.delay;
}
const texts = (root) => root.textContent;

runInNewContext(script, context);
// Al abrir la pantalla con trabajos activos, el navegador da pasos sin que nadie pulse nada.
assert.ok(timers.length === 1 && timers[0].delay === 500, 'Con trabajos activos, la pantalla debe empezar a avanzar la cola');
replies.push({ body: { success: true, data: { result: 'worked', jobs: [job()] } } });
await runTimer();
assert.ok(requests[0].action === 'digitalisimo_backups_job_tick' && requests[0].nonce === 'nonce-jobs' && requests[0].context === 'network', 'El tick debe usar el nonce de trabajos y el contexto de la pantalla');
assert.ok(texts(ids['digitalisimo-backups-jobs-list']).includes('<img src=x onerror=alert(1)>'), 'El detalle del servidor debe mostrarse literal, como texto');
assert.ok(timers.length === 1 && timers[0].delay === 300, 'Tras un paso hecho, el siguiente va enseguida');
// Otro proceso trabaja: sólo se consulta cada 3 s. Un reintento lejano: se espera hasta 15 s.
replies.push({ body: { success: true, data: { result: 'busy', jobs: [job()] } } });
await runTimer();
assert.ok(timers[0].delay === 3000, 'Con el almacén ocupado, la pantalla debe esperar');
replies.push({ body: { success: true, data: { result: 'idle', jobs: [job({ status: 'queued', running: false, retry_in: 600 })] } } });
await runTimer();
assert.ok(timers[0].delay === 15000, 'Un reintento lejano no debe consultarse sin pausa');
// Un error del servidor no detiene la cola: se reintenta con pausa creciente.
replies.push({ ok: false, status: 502, body: '<html>Bad gateway</html>' });
await runTimer();
assert.ok(timers[0].delay === 2000, 'Un fallo de red debe reintentarse con pausa');

// Crear un respaldo hacia cualquier destino: encola con el nonce del formulario y sigue ese trabajo.
const form = ids['digitalisimo-backups-manual-form'];
let prevented = false;
timers.length = 0;
replies.push({ body: { success: true, data: { id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', view: job({ id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', status: 'queued', status_label: 'Pendiente', percent: 0, detail: 'En cola.' }) } } });
form.listeners.submit({ preventDefault: () => { prevented = true; } });
assert.ok(prevented && submit.disabled === true, 'El envío debe quedar en la página y deshabilitar el botón');
for (let i = 0; i < 5; i++) await flush();
const start = requests[requests.length - 1];
assert.ok(start.action === 'digitalisimo_backups_manual_start' && start.nonce === 'nonce-manual' && start.destination === 'google', 'El respaldo debe encolarse con su destino y el nonce del formulario');
assert.ok(ids['digitalisimo-backups-manual-message'].textContent.startsWith('Pendiente'), 'El panel debe seguir el trabajo encolado');
assert.ok(timers.length === 1 && timers[0].delay === 0, 'El envío debe dar el primer paso enseguida');
replies.push({ body: { success: true, data: { result: 'worked', jobs: [job({ id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', status: 'success', status_label: 'Completo', percent: 100, detail: 'ZIP verificado: x.zip', terminal: true, can_cancel: false, running: false })] } } });
await runTimer();
assert.ok(submit.disabled === false && ids['digitalisimo-backups-manual-detail'].textContent.includes('ZIP verificado') && timers.length === 0, 'Al terminar, la pantalla deja de dar pasos y habilita el botón');
assert.ok(ids['digitalisimo-backups-jobs'].getAttribute('data-active') === '0', 'Sin trabajos activos la tarjeta se marca inactiva');

// Cancelar: con confirmación, su nonce y el identificador del trabajo.
const cancelButton = node('button');
cancelButton.setAttribute('data-cancel', '11111111-2222-4333-8444-555555555555');
let cancelPrevented = false;
replies.push({ body: { success: true, data: { message: 'Trabajo cancelado.', jobs: [job({ status: 'cancelled', status_label: 'Cancelado', terminal: true, can_cancel: false })] } } });
ids['digitalisimo-backups-jobs'].listeners.click({ target: { closest: () => cancelButton }, preventDefault: () => { cancelPrevented = true; } });
for (let i = 0; i < 5; i++) await flush();
const cancel = requests[requests.length - 1];
assert.ok(cancelPrevented && cancel.action === 'digitalisimo_backups_job_cancel' && cancel.id === '11111111-2222-4333-8444-555555555555' && cancel.nonce === 'nonce-jobs', 'Cancelar debe pedirse por AJAX con el nonce de trabajos');
assert.ok(texts(ids['digitalisimo-backups-jobs-list']).includes('Cancelado'), 'La tarjeta debe mostrar el trabajo cancelado');
// Un rechazo (ya verificado) se explica y deja el botón usable.
replies.push({ ok: false, status: 409, body: { success: false, data: { message: 'El respaldo ya está verificado y registrado.' } } });
ids['digitalisimo-backups-jobs'].listeners.click({ target: { closest: () => cancelButton }, preventDefault: () => {} });
for (let i = 0; i < 5; i++) await flush();
assert.ok(alerts.length === 1 && alerts[0].includes('verificado') && cancelButton.disabled === false, 'Un rechazo de cancelación debe explicarse');

// Una migración que llega a restaurar reemplaza los usuarios del sitio: la sesión de este navegador deja de valer. Cuando
// el tick deja de responder, la tarjeta pide iniciar sesión con un usuario del origen en lugar de reintentar sin fin.
const migration = (overrides) => job(Object.assign({ id: 'cccccccc-dddd-4eee-8fff-000000000000', kind: 'migration', phase: 'restore', phase_label: 'Restauración', status: 'running', status_label: 'En curso', terminal: false }, overrides));
assert.ok(timers.length === 1, 'Tras cancelar, la pantalla sigue dando pasos');
replies.push({ body: { success: true, data: { result: 'worked', jobs: [migration()] } } });
await runTimer();
replies.push({ ok: false, status: 400, body: '-1' });
await runTimer();
assert.ok(timers.length === 1 && !texts(ids['digitalisimo-backups-jobs']).includes('inicia sesión'), 'Un primer fallo todavía se reintenta');
replies.push({ ok: false, status: 400, body: '-1' });
await runTimer();
const card = ids['digitalisimo-backups-jobs'];
const notice = card.children.find((child) => /digitalisimo-backups-migrate-login/.test(child.getAttribute('class') || ''));
assert.ok(notice && notice.textContent.includes('inicia sesión con un usuario del sitio de origen') && notice.children[0].getAttribute('href') === '/wp-login.php?redirect_to=migrar' && timers.length === 0, 'Tras restaurar, la tarjeta debe pedir iniciar sesión con un usuario del origen y dejar de reintentar');
console.log('DIGITALÍSIMO Backups: la pantalla avanza la cola, pinta texto, espera, se detiene al terminar y cancela con su nonce.');
