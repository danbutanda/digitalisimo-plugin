// La tarjeta «Subir un respaldo» sube por fragmentos del tamaño que fija el servidor, cada uno en el byte que el
// servidor espera: corrige el desplazamiento cuando responde 409 con lo recibido, reintenta con espera si se corta la
// conexión, continúa tras recargar la página con el mismo archivo y pinta todo texto del servidor como texto.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync('digitalisimo-backups/assets/upload.js', 'utf8');
assert.ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(script), 'El estado del servidor debe pintarse como texto');

function node(tag) {
  return {
    tag, attributes: {}, listeners: {}, hidden: false, disabled: false, value: '', text: '', files: null, removed: [],
    removeAttribute(name) { this.removed.push(name); if (name === 'value') this.value = undefined; },
    set textContent(value) { this.text = String(value); },
    get textContent() { return this.text; },
    setAttribute(name, value) { this.attributes[name] = String(value); },
    addEventListener(type, listener) { this.listeners[type] = listener; },
    querySelector(selector) { return this.selectors ? this.selectors[selector] || null : null; },
  };
}

const bytes = new Uint8Array(1000);
for (let i = 0; i < bytes.length; i++) bytes[i] = (i * 7) % 251;
const file = new File([bytes], 'digitalisimo-backup-sitio.zip', { lastModified: 1762650000000 });

function setup({ stored = {}, server, statusAfter }) {
  const form = node('form');
  const input = node('input'); input.files = [file];
  const secret = node('input'); secret.value = 'frase <b>secreta</b>';
  const submit = node('button'); const cancel = node('button'); const bar = node('progress'); const status = node('p');
  form.hidden = true;
  form.selectors = { 'input[type="file"]': input, 'input[name="digitalisimo_backups_upload_secret"]': secret, 'button[type="submit"]': submit, '[data-upload-cancel]': cancel, 'progress': bar, '.digitalisimo-backups-upload-status': status };
  const card = node('section'); card.selectors = { '.digitalisimo-backups-upload-form': form };
  const requests = [];
  const storage = { data: { ...stored }, getItem(k) { return k in this.data ? this.data[k] : null; }, setItem(k, v) { this.data[k] = String(v); }, removeItem(k) { delete this.data[k]; } };
  const waits = [];
  const context = {
    window: { digitalisimoBackupsUpload: { url: '/wp-admin/admin-ajax.php', nonce: 'nonce-upload', context: 'site', ...(statusAfter === undefined ? {} : { statusAfter }) }, localStorage: storage, setTimeout: (fn, ms) => { waits.push(ms); Promise.resolve().then(fn); } },
    document: { getElementById: (id) => (id === 'digitalisimo-backups-upload' ? card : null) },
    FormData, Promise, JSON, Math, Number, String, Object, Error,
    fetch: async (url, options) => {
      const fields = {};
      for (const [name, value] of options.body.entries()) fields[name] = typeof value === 'string' ? value : new Uint8Array(await value.arrayBuffer());
      requests.push(fields);
      const reply = await server(fields, requests.length);
      if (reply === 'network') throw new TypeError('Failed to fetch');
      return { ok: reply.status === 200, status: reply.status, text: async () => JSON.stringify(reply.body) };
    },
  };
  runInNewContext(script, context);
  return { form, status, bar, submit, storage, requests, waits };
}
async function run(ui) {
  ui.form.listeners.submit({ preventDefault() {} });
  assert.equal(ui.submit.disabled, true, 'El botón no se deshabilitó durante la subida');
  for (let i = 0; i < 500; i++) {
    await new Promise((resolve) => setImmediate(resolve));
    if (!ui.submit.disabled) return;
  }
  throw new Error('La subida no terminó');
}
/** Servidor simulado: guarda lo recibido y responde como los handlers AJAX. */
function server({ chunkSize = 300, failures = {} } = {}) {
  const received = [];
  let total = 0;
  const state = { received, get total() { return total; } };
  state.handle = (fields, n) => {
    const action = fields.action.replace('digitalisimo_backups_upload_', '');
    assert.equal(fields.nonce, 'nonce-upload');
    assert.equal(fields.context, 'site');
    if (failures[n]) return failures[n](state);
    if (action === 'start') return { status: 200, body: { success: true, data: { id: 'sesion-1', size: Number(fields.size), received: 0, chunk_size: chunkSize } } };
    if (action === 'status') return { status: 200, body: { success: true, data: { id: fields.id, size: 1000, received: total, chunk_size: chunkSize } } };
    if (action === 'chunk') {
      if (Number(fields.offset) !== total) return { status: 409, body: { success: false, data: { code: 'digitalisimo_backups_upload_offset', message: 'La subida va en el byte ' + total, received: total } } };
      received.push(fields.chunk);
      total += fields.chunk.length;
      return { status: 200, body: { success: true, data: { id: fields.id, size: 1000, received: total, chunk_size: chunkSize } } };
    }
    if (action === 'finish') {
      assert.equal(total, 1000, 'Se terminó antes de recibir todo');
      return { status: 200, body: { success: true, data: { file: 'digitalisimo-backup-sitio.zip', confirmation: 'RESTAURAR SIN FIRMA', needs_secret: false, problems: ['<img src=x onerror=alert(1)>'] } } };
    }
    return { status: 400, body: { success: false, data: { message: 'acción desconocida' } } };
  };
  return state;
}
const joined = (chunks) => { const out = new Uint8Array(chunks.reduce((n, c) => n + c.length, 0)); let o = 0; for (const c of chunks) { out.set(c, o); o += c.length; } return out; };

// 1. Subida limpia: start, fragmentos de 300 bytes en 0, 300, 600 y 900, y finish con la frase escrita.
let s = server();
let s7;
let ui = setup({ server: s.handle });
await run(ui);
assert.deepEqual(ui.requests.map((r) => r.action.replace('digitalisimo_backups_upload_', '') + (r.offset !== undefined ? '@' + r.offset : '')), ['start', 'chunk@0', 'chunk@300', 'chunk@600', 'chunk@900', 'finish']);
assert.equal(ui.requests[0].name, 'digitalisimo-backup-sitio.zip');
assert.equal(ui.requests[0].size, '1000');
assert.deepEqual(joined(s.received), bytes, 'Los fragmentos no reconstruyen el archivo');
assert.equal(ui.requests[5].secret, 'frase <b>secreta</b>', 'La frase debe viajar tal cual sólo al terminar');
assert.ok(!('secret' in ui.requests[1]), 'La frase no viaja con los fragmentos');
assert.match(ui.status.textContent, /^Respaldo subido y verificado: digitalisimo-backup-sitio\.zip\. Para restaurarlo escribe RESTAURAR SIN FIRMA\. <img src=x onerror=alert\(1\)> /, 'El resultado no se pintó como texto');
assert.equal(ui.bar.value, 100);
assert.deepEqual(Object.keys(ui.storage.data), [], 'Una subida terminada sigue recordada para reanudarse');

// 2. Una respuesta perdida (el servidor ya tiene el fragmento) y un corte de red: 409 corrige el desplazamiento y el
// corte se reintenta con espera, sin duplicar ni saltarse bytes.
s = server({ failures: {
  3: (state) => { state.handle({ action: 'digitalisimo_backups_upload_chunk', nonce: 'nonce-upload', context: 'site', id: 'sesion-1', offset: String(state.total), chunk: bytes.slice(state.total, state.total + 300) }, 0); return 'network'; },
  5: () => 'network',
} });
ui = setup({ server: s.handle });
await run(ui);
assert.deepEqual(joined(s.received), bytes, 'Tras el 409 y el corte, el archivo no quedó idéntico');
assert.ok(ui.waits.length >= 2 && ui.waits.every((ms) => ms >= 1000), 'Los reintentos no esperaron');
assert.ok(ui.requests.some((r) => r.action.endsWith('chunk') && r.offset === '300') && ui.requests.filter((r) => r.action.endsWith('finish')).length === 1);

// 3. Reanudar tras recargar: el mismo archivo continúa desde lo que el servidor ya tiene, sin empezar otra subida.
const resumed = server();
for (const offset of [0, 300]) resumed.handle({ action: 'digitalisimo_backups_upload_chunk', nonce: 'nonce-upload', context: 'site', id: 'sesion-1', offset: String(offset), chunk: bytes.slice(offset, offset + 300) }, 0);
const key = 'digitalisimo-backups-upload:site:digitalisimo-backup-sitio.zip:1000:1762650000000';
ui = setup({ stored: { [key]: 'sesion-1' }, server: resumed.handle });
await run(ui);
assert.deepEqual(ui.requests.map((r) => r.action.replace('digitalisimo_backups_upload_', '') + (r.offset !== undefined ? '@' + r.offset : '')), ['status', 'chunk@600', 'chunk@900', 'finish'], 'No se reanudó desde lo recibido');
assert.deepEqual(joined(resumed.received), bytes, 'La subida reanudada no reconstruye el archivo');

// 4. Un archivo descartado por el servidor (no válido) se olvida y el mensaje se pinta como texto.
s = server({ failures: { 6: () => ({ status: 422, body: { success: false, data: { code: 'digitalisimo_backups_upload_invalid', message: 'No supera la validación <script>x</script>' } } }) } });
ui = setup({ server: s.handle });
await run(ui);
assert.equal(ui.status.textContent, 'La subida se detuvo: No supera la validación <script>x</script>');
assert.deepEqual(Object.keys(ui.storage.data), [], 'Un archivo descartado sigue recordado para reanudarse');

// 5. P1: el cierre tarda más que el proxy (202 «comprobándose») o la conexión se corta al cerrar: se consulta el estado
// hasta el resultado, sin volver a cerrar en bucle ni subir el archivo otra vez.
for (const cut of ['validating', 'network']) {
  const st = server();
  let finishes = 0;
  let polls = 0;
  const handle = (fields, n) => {
    const action = fields.action.replace('digitalisimo_backups_upload_', '');
    if (action === 'finish') {
      finishes++;
      if (cut === 'network') return 'network';
      return { status: 202, body: { success: false, data: { code: 'digitalisimo_backups_upload_validating', message: 'La subida se está comprobando' } } };
    }
    if (action === 'status' && st.total === 1000) {
      polls++;
      if (polls < 3) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: 1000, received: 1000, chunk_size: 300, phase: 'validating', validating: true } } };
      return { status: 200, body: { success: true, data: { id: 'sesion-1', size: 1000, received: 1000, chunk_size: 300, phase: 'done', result: { file: 'digitalisimo-backup-sitio.zip', confirmation: 'RESTAURAR SIN FIRMA', needs_secret: false, problems: [] } } } };
    }
    return st.handle(fields, n);
  };
  ui = setup({ server: handle });
  await run(ui);
  assert.equal(finishes, 1, `Con el cierre cortado (${cut}) se volvió a cerrar en vez de consultar el estado`);
  assert.equal(ui.requests.filter((r) => r.action.endsWith('start')).length, 1, 'Se empezó otra subida');
  assert.ok(polls >= 3, 'No se consultó el estado hasta el resultado');
  assert.match(ui.status.textContent, /^Respaldo subido y verificado: digitalisimo-backup-sitio\.zip/);
}

// 6. P1: al volver a elegir el archivo de una subida ya cerrada (el navegador no vio el final), se muestra su resultado.
ui = setup({ stored: { [key]: 'sesion-1' }, server: (fields) => {
  const action = fields.action.replace('digitalisimo_backups_upload_', '');
  if (action === 'status') return { status: 200, body: { success: true, data: { id: 'sesion-1', size: 1000, received: 1000, chunk_size: 300, phase: 'done', result: { file: 'digitalisimo-backup-sitio.zip', confirmation: 'RESTAURAR', needs_secret: false, problems: [] } } } };
  throw new Error('petición inesperada: ' + action);
} });
await run(ui);
assert.deepEqual(ui.requests.map((r) => r.action.replace('digitalisimo_backups_upload_', '')), ['status'], 'Una subida ya cerrada se volvió a subir o a cerrar');
assert.match(ui.status.textContent, /^Respaldo subido y verificado: digitalisimo-backup-sitio\.zip\. Para restaurarlo escribe RESTAURAR\./);

// 7. Un error con motivo del servidor (espacio para descifrar, 507) se muestra y no se reintenta en bucle.
s7 = server({ failures: { 6: () => ({ status: 507, body: { success: false, data: { code: 'digitalisimo_backups_upload_space', message: 'No hay espacio para descifrar el respaldo. La subida se conserva.' } } }) } });
ui = setup({ server: s7.handle });
await run(ui);
assert.equal(ui.requests.filter((r) => r.action.endsWith('finish')).length, 1, 'Un 507 con motivo se reintentó');
assert.equal(ui.status.textContent, 'La subida se detuvo: No hay espacio para descifrar el respaldo. La subida se conserva. Vuelve a elegir el mismo archivo para continuar donde se quedó.');
assert.deepEqual(Object.values(ui.storage.data), ['sesion-1'], 'Una subida conservada se olvidó');

// 8. Incidente de /wp (2026-11-12): la respuesta del cierre no llega nunca (el proxy retiene la conexión) aunque el
// servidor ya terminó (GET status: phase=done). La barra llega a 100 % y dice «verificado»; durante la comprobación queda
// indeterminada (sin valor), no detenida en un número. Con el código anterior la subida no terminaba nunca.
{
  const st = server();
  let finishes = 0;
  const handle = (fields, n) => {
    const action = fields.action.replace('digitalisimo_backups_upload_', '');
    if (action === 'finish') { finishes++; return new Promise(() => {}); }
    if (action === 'status' && st.total === 1000) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: 1000, received: 1000, chunk_size: 300, phase: 'done', result: { file: 'digitalisimo-backup-sitio.zip', verified: true, confirmation: 'RESTAURAR SIN FIRMA', needs_secret: false, problems: [] } } } };
    return st.handle(fields, n);
  };
  ui = setup({ server: handle, statusAfter: 0 });
  await run(ui);
  assert.equal(finishes, 1, 'Se volvió a cerrar una subida que el servidor ya había terminado');
  assert.ok(ui.bar.removed.includes('value'), 'Durante la comprobación la barra no quedó indeterminada');
  assert.equal(ui.bar.value, 100, 'La barra no llegó a 100 % con phase=done');
  assert.match(ui.status.textContent, /^Respaldo subido y verificado: digitalisimo-backup-sitio\.zip\./, 'No dice «verificado»: ' + ui.status.textContent);
}

// 9. El proceso del servidor que comprobaba murió y el cierre no contesta: el estado dice que nadie la comprueba y se
// vuelve a cerrar (sin subir de nuevo); el segundo cierre termina.
{
  const st = server();
  let finishes = 0;
  const handle = (fields, n) => {
    const action = fields.action.replace('digitalisimo_backups_upload_', '');
    if (action === 'finish') {
      finishes++;
      if (finishes === 1) return new Promise(() => {});
      return { status: 200, body: { success: true, data: { file: 'digitalisimo-backup-sitio.zip', verified: true, confirmation: 'RESTAURAR SIN FIRMA', needs_secret: false, problems: [] } } };
    }
    if (action === 'status' && st.total === 1000) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: 1000, received: 1000, chunk_size: 300, phase: 'validating', validating: false } } };
    return st.handle(fields, n);
  };
  ui = setup({ server: handle, statusAfter: 0 });
  await run(ui);
  assert.equal(finishes, 2, 'No se volvió a cerrar una comprobación que nadie hacía');
  assert.equal(ui.requests.filter((r) => r.action.endsWith('start')).length, 1, 'Se empezó otra subida');
  assert.equal(ui.bar.value, 100);
  assert.match(ui.status.textContent, /^Respaldo subido y verificado:/);
}

console.log('DIGITALÍSIMO Backups: la subida por fragmentos corrige el desplazamiento, reintenta con espera, se reanuda tras recargar, pinta texto y la barra llega a 100 % «verificado» aunque la respuesta del cierre no llegue.');
