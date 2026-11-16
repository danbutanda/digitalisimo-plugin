// «Subir un respaldo» con el mismo panel de avance que el respaldo y la restauración (pedido del propietario del
// 2026-11-16: «usa esa misma [barra], pero ahora para cuando se sube o importa un respaldo externo»). Carga en la misma
// página simulada assets/manual-progress.js (el panel) y assets/upload.js (la subida) y comprueba, con un servidor simulado:
//   U1  una subida limpia: el panel (en el hueco de su sección, abierta) pinta la fase «Subida» con los bytes enviados de
//       los totales, la velocidad y el tiempo restante; «Comprobación» indeterminada (sin aria-valuenow, la barra espera
//       y lo dice) y «Registro»; el resultado: «Respaldo subido y verificado», su fila en la lista y «Restaurarlo»; los
//       fragmentos van en el byte que el servidor espera, la frase sólo al cerrar, todo texto como texto;
//   U2  un 409 (corrige el desplazamiento) y cortes de red: los reintentos se ven («Se reintentará en 2 s (1 de 6)») y la
//       nota se va al volver; el archivo queda idéntico; la barra nunca retrocede;
//   U3  reanudar tras recargar eligiendo el mismo archivo: sigue desde lo recibido y lo dice;
//   U4  un archivo descartado (no válido): «El respaldo se descartó» con el motivo, sin nada que continuar;
//   U5  el cierre responde 202 o se corta (el proxy de /wp): se consulta el estado, el panel dice que la comprobación sigue
//       en el servidor y el paso en el que va («Verificando archivo 120 de 5 000», el registro);
//   U6  una subida ya cerrada que se vuelve a elegir: su resultado, sin subir ni cerrar otra vez;
//   U7  un error con motivo (507): se dice, no se repite en bucle y la subida se conserva (con «Cancelar» para descartarla);
//   U8  el cierre que nunca responde (incidente de /wp): llega a 100 % y «verificado» por el estado; mientras, indeterminada;
//   U9  el proceso que comprobaba murió: se vuelve a cerrar (sin subir) y se dice; U10 sin cierres en cascada (B4);
//   U11 en la lista o ya estaba (duplicado); U12 504 tras tres cortes: pide WP-CLI y la subida se conserva;
//   U13 «Cancelar» del panel durante la subida: borra lo recibido y lo dice; nunca mientras se comprueba;
//   U14 un .dgbk sin su frase: «sin registro», con el motivo y el enlace a «Agregar al listado»;
//   U15 recargar a media comprobación: el panel vuelve a ella y la sigue hasta su resultado sin cerrarla otra vez; si nadie
//       la comprueba, lo dice y «Terminar la comprobación» la termina sin subir otra vez;
//   U16 al recargar con una subida a medias: «Subida en pausa» con qué hacer; elegir el mismo archivo sigue sin que la barra
//       retroceda; «Cancelar» la descarta;
//   U17 un .tar.gz de Backuply: «Importarlo ahora» con su nombre; U18 el resultado que llegó mientras se recargaba;
//   U19 con una restauración en curso el panel no se tapa; U20 nada se pinta como HTML y el marcado de PHP es el del panel.
// Uso: node tests/backups-upload-ui.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { El, buildPanel, documentApi, phasesOf, settle, PANEL_IDS } from './lib/backups-panel-dom.mjs';

const upload = readFileSync('digitalisimo-backups/assets/upload.js', 'utf8');
const manual = readFileSync('digitalisimo-backups/assets/manual-progress.js', 'utf8');
const markup = readFileSync('digitalisimo-backups/includes/class-backups-progress.php', 'utf8');
const cardPhp = readFileSync('digitalisimo-backups/includes/class-backups-uploads.php', 'utf8');

const MiB = 1048576;
const SIZE = 3 * MiB;
const bytes = new Uint8Array(SIZE);
for (let i = 0; i < bytes.length; i++) bytes[i] = (i * 7) % 251;
const file = new File([bytes], 'digitalisimo-backup-sitio.zip', { lastModified: 1762650000000 });
const KEY = 'digitalisimo-backups-upload:site:digitalisimo-backup-sitio.zip:' + SIZE + ':1762650000000';
const ACTIVE = 'digitalisimo-backups-upload-active:site';
const joined = (chunks) => { const out = new Uint8Array(chunks.reduce((n, c) => n + c.length, 0)); let o = 0; for (const c of chunks) { out.set(c, o); o += c.length; } return out; };

/** La pestaña «Herramientas» con el panel arriba y «Subir un respaldo» plegada, con su hueco para el panel. */
function setup({ stored = {}, server, statusAfter, sessions = null, view = null, theFile = file, confirm = true, tick = 1000 } = {}) {
  const document = new El('html');
  const body = document.appendChild(new El('body'));
  const wrap = body.appendChild(new El('div', { class: 'wrap digitalisimo-backups', 'data-tab': 'tools' }));
  const panel = buildPanel(wrap, { view, card: true });
  const section = wrap.appendChild(new El('details', { class: 'digitalisimo-backups-section', id: 'digitalisimo-backups-section-upload' }));
  const card = section.appendChild(new El('section', { id: 'digitalisimo-backups-upload', class: 'digitalisimo-backups-card digitalisimo-backups-upload' }));
  if (sessions) card.setAttribute('data-sessions', JSON.stringify(sessions));
  const form = card.appendChild(new El('form', { class: 'digitalisimo-backups-upload-form', hidden: '' }));
  const input = form.appendChild(new El('input', { type: 'file', name: 'digitalisimo_backups_upload_file' }));
  input.files = theFile ? [theFile] : [];
  const secret = form.appendChild(new El('input', { type: 'password', name: 'digitalisimo_backups_upload_secret' }));
  secret.value = 'frase <b>secreta</b>';
  const submit = form.appendChild(new El('button', { class: 'button button-primary', type: 'submit' }));
  const status = form.appendChild(new El('p', { class: 'digitalisimo-backups-upload-status' }));
  const slot = card.appendChild(new El('div', { class: 'digitalisimo-backups-progress-slot', 'data-progress-slot': 'upload' }));
  const clock = { now: Date.UTC(2026, 10, 16, 15, 0, 0) };
  class FakeDate extends Date { constructor(...args) { super(...(args.length ? args : [clock.now])); } static now() { return clock.now; } }
  const own = { requests: [], seen: [], waits: [], timers: [], intervals: [], confirms: [], clock, storage: null };
  const storage = { data: { ...stored }, getItem(k) { return k in this.data ? this.data[k] : null; }, setItem(k, v) { this.data[k] = String(v); }, removeItem(k) { delete this.data[k]; } };
  own.storage = storage;
  const api = documentApi(document);
  const sandbox = {
    window: {
      digitalisimoBackupsManual: { url: '/wp-admin/admin-ajax.php', nonce: 'nonce-manual', jobsNonce: 'nonce-jobs', context: 'site' },
      digitalisimoBackupsUpload: { url: '/wp-admin/admin-ajax.php', nonce: 'nonce-upload', context: 'site', ...(statusAfter === undefined ? {} : { statusAfter }) },
      localStorage: storage,
      setTimeout: (fn, ms) => { own.waits.push(ms); if (ms >= 45000) return 0; Promise.resolve().then(fn); return 1; },
      clearTimeout: () => {},
      confirm: (text) => { own.confirms.push(text); return confirm; },
      location: { href: 'https://example.test/wp-admin/admin.php?page=digitalisimo-backups&tab=tools&open=upload&backup=ok&message=x' },
      document: api,
    },
    document: api,
    FormData, Promise, JSON, Math, Number, String, Object, Error, URL, URLSearchParams, Array, AbortController, Date: FakeDate,
    setTimeout: (fn, ms) => { own.timers.push({ fn, ms }); return own.timers.length; },
    clearTimeout: () => {},
    setInterval: (fn) => { own.intervals.push(fn); return own.intervals.length; },
    fetch: async (url, options) => {
      if (typeof options.body === 'string') throw new Error('Petición de la cola inesperada: ' + options.body);
      const fields = {};
      for (const [name, value] of options.body.entries()) fields[name] = typeof value === 'string' ? value : new Uint8Array(await value.arrayBuffer());
      own.requests.push(fields);
      own.seen.push(own.snapshot());
      clock.now += tick;
      const reply = await server(fields, own.requests.length, own);
      if (reply === 'network') throw new TypeError('Failed to fetch');
      if (reply && reply.pending) return new Promise((resolve, reject) => { options.signal?.addEventListener('abort', () => reject(new TypeError('aborted'))); });
      return { ok: reply.status === 200, status: reply.status, headers: { get: () => null }, text: async () => JSON.stringify(reply.body) };
    },
  };
  const byId = (id) => api.getElementById(id);
  const P = (name) => byId('digitalisimo-backups-progress-' + name);
  // Antes de cargar los scripts: al cargar, la subida puede consultar ya el servidor (lo que terminó mientras se recargaba).
  Object.assign(own, { document, panel, section, card, form, input, secret, submit, status, slot, sandbox, ui: Object.fromEntries(PANEL_IDS.map((n) => [n, P(n)])), bar: byId('digitalisimo-backups-manual-bar'), message: byId('digitalisimo-backups-manual-message'), detail: byId('digitalisimo-backups-manual-detail') });
  own.text = (node) => (node ? node.textContent : '');
  own.phases = () => phasesOf(document);
  own.snapshot = () => ({ percent: own.text(own.ui.percent), now: own.bar.getAttribute('aria-valuenow'), valuetext: own.bar.getAttribute('aria-valuetext'), waiting: own.bar.hasAttribute('data-waiting'), title: own.text(own.ui.title), message: own.text(own.message), detail: own.text(own.detail), note: own.text(own.ui.note), level: own.ui.note.getAttribute('data-level'), phases: own.phases().join(','), cancel: !own.ui.cancel.hidden });
  own.percents = () => own.seen.map((s) => parseInt(s.percent, 10)).filter((n) => !Number.isNaN(n));
  runInNewContext(manual, sandbox);
  runInNewContext(upload, sandbox);
  return own;
}
async function run(page) {
  page.form.listeners.submit[0]({ preventDefault() {} });
  assert.equal(page.submit.disabled, true, 'El botón no se deshabilitó durante la subida');
  return until(page, () => !page.submit.disabled);
}
async function until(page, done, label = 'La subida no terminó') {
  for (let i = 0; i < 800; i++) { await new Promise((resolve) => setImmediate(resolve)); if (done()) return; }
  throw new Error(label);
}
const actions = (page) => page.requests.map((r) => r.action.replace('digitalisimo_backups_upload_', '') + (r.offset !== undefined ? '@' + (Number(r.offset) / MiB) : ''));
const monotonic = (list) => list.every((value, index) => !index || value >= list[index - 1]);

/** Servidor simulado: guarda lo recibido y responde como los handlers AJAX (el estado, con la vista de pantalla). */
function server({ chunkSize = MiB, failures = {}, result = {} } = {}) {
  const received = [];
  let total = 0;
  const state = { received, get total() { return total; }, set total(v) { total = v; } };
  state.result = Object.assign({ file: 'digitalisimo-backup-sitio.zip', size: SIZE, registered: true, listed: true, duplicate: false, date: '2026-11-16 09:04:05', confirmation: 'RESTAURAR', vouched: 'uploaded', needs_secret: false, restorable: true, uploaded: { label: 'Subido', home: 'https://otro.test', same_site: false }, problems: ['<img src=x onerror=alert(1)>'] }, result);
  state.handle = (fields, n) => {
    const action = fields.action.replace('digitalisimo_backups_upload_', '');
    assert.equal(fields.nonce, 'nonce-upload');
    assert.equal(fields.context, 'site');
    if (failures[n]) return failures[n](state);
    if (action === 'start') return { status: 200, body: { success: true, data: { id: 'sesion-1', name: fields.name, size: Number(fields.size), received: 0, chunk_size: chunkSize, phase: 'uploading' } } };
    if (action === 'status') return { status: 200, body: { success: true, data: { id: fields.id, name: file.name, size: SIZE, received: total, chunk_size: chunkSize, phase: 'uploading', max_attempts: 3 } } };
    if (action === 'chunk') {
      if (Number(fields.offset) !== total) return { status: 409, body: { success: false, data: { code: 'digitalisimo_backups_upload_offset', message: 'La subida va en el byte ' + total, received: total } } };
      received.push(fields.chunk);
      total += fields.chunk.length;
      return { status: 200, body: { success: true, data: { id: fields.id, size: SIZE, received: total, chunk_size: chunkSize, phase: 'uploading' } } };
    }
    if (action === 'finish') {
      assert.equal(total, SIZE, 'Se terminó antes de recibir todo');
      return { status: 200, body: { success: true, data: state.result } };
    }
    if (action === 'cancel') return { status: 200, body: { success: true, data: { id: fields.id, cancelled: true } } };
    return { status: 400, body: { success: false, data: { message: 'acción desconocida' } } };
  };
  return state;
}

const failed = [];
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
async function scenario(label, test) {
  try { await test(); console.log('ok · ' + label); } catch (error) { failed.push(label); console.log('FALLA · ' + label + ': ' + error.message); }
}

await scenario('U20 · nada se pinta como HTML; el panel de PHP tiene lo que usa la subida; la tarjeta ya no pinta su barra', async () => {
  ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(upload) && !/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(manual), 'Un texto del servidor se pinta como HTML');
  for (const name of ['go', 'resume', 'import']) ok(markup.includes(`$id . '-${name}"`), `El marcado del panel no tiene «${name}»`);
  ok(/digitalisimo_backups_backuply_import|ACTION_BACKUPLY_IMPORT/.test(markup) && /name="backuply_source" value="store"/.test(markup), '«Importarlo ahora» no es la acción de importar un .tar.gz del almacén');
  ok(!/<progress/.test(cardPhp) && /data-progress-slot="upload"/.test(cardPhp) && !/data-upload-cancel|data-upload-list/.test(cardPhp), 'La tarjeta «Subir un respaldo» sigue pintando su propia barra (o no tiene el hueco del panel)');
  ok(!/querySelector\('progress'\)|\.value = percent|bar\.value/.test(upload), 'upload.js sigue moviendo un <progress> propio');
});

await scenario('U1 · subida limpia: fases, bytes, velocidad, tiempo restante, comprobación indeterminada y resultado con su fila', async () => {
  const s = server();
  const page = setup({ server: s.handle });
  ok(page.panel.hidden && page.panel.parentNode !== page.slot, 'El panel se ve antes de subir nada');
  await run(page);
  ok(JSON.stringify(actions(page)) === JSON.stringify(['start', 'chunk@0', 'chunk@1', 'chunk@2', 'finish']), 'Las peticiones no son start, tres fragmentos en su byte y finish: ' + actions(page));
  ok(page.requests[0].name === file.name && page.requests[0].size === String(SIZE), 'start no lleva nombre y tamaño');
  ok(JSON.stringify(joined(s.received)) === JSON.stringify(bytes), 'Los fragmentos no reconstruyen el archivo');
  ok(page.requests[4].secret === 'frase <b>secreta</b>' && !('secret' in page.requests[1]), 'La frase no viaja tal cual sólo al cerrar');
  // El panel, en el hueco de «Subir un respaldo» (abierta), a todo lo ancho.
  ok(page.panel.parentNode === page.slot && page.section.open === true, 'El panel no está junto al formulario de la subida (o su sección sigue plegada)');
  const during = page.seen[3];
  ok(during.title === 'Subiendo el respaldo' && during.phases === 'upload:current,check:pending,register:pending' && during.message === 'En curso · Subida (fase 1 de 3)', 'La subida no se ve como la fase 1 de 3: ' + JSON.stringify(during));
  ok(/^2,0 MB de 3,0 MB · 1,0 MB\/s · quedan unos 1 s$/.test(during.detail), 'La subida no dice bytes, velocidad y tiempo restante: ' + during.detail);
  ok(during.percent === '56 %' && during.now === '56' && !during.waiting && during.cancel, 'La barra de la subida no dice su valor o no ofrece «Cancelar»: ' + JSON.stringify(during));
  const check = page.seen[4];
  ok(check.waiting && check.now === null && check.percent === '85 %' && check.phases === 'upload:done,check:current,register:pending' && check.title === 'Comprobando el respaldo subido', 'La comprobación no se ve indeterminada en su fase: ' + JSON.stringify(check));
  ok(/Comprobando el respaldo en el servidor como archivo no confiable/.test(check.detail) && check.message === 'Verificando · Comprobación (fase 2 de 3)' && /^85 % · Comprobación en el servidor \(sin pasos medibles\)$/.test(check.valuetext) && !check.cancel, 'La comprobación no se explica o ofrece «Cancelar»: ' + JSON.stringify(check));
  ok(page.text(page.ui.title) === 'Respaldo subido y verificado' && page.text(page.ui.percent) === '100 %' && page.phases().every((p) => p.endsWith(':done')) && page.panel.getAttribute('data-state') === 'success', 'El resultado no es «Respaldo subido y verificado» al 100 % con todas las fases hechas');
  const result = page.text(page.ui.result);
  ok(/Ya está en la lista de respaldos, arriba, marcado «Subido»: digitalisimo-backup-sitio\.zip\./.test(result) && /Su fila: 2026-11-16 09:04 · 3,0 MB · de https:\/\/otro\.test\./.test(result) && /Para restaurarlo escribe RESTAURAR\./.test(result) && result.includes('<img src=x onerror=alert(1)>'), 'El resultado no dice su fila ni cómo restaurarlo (o no pinta el texto literal): ' + result);
  const go = new URL(page.ui.go.getAttribute('href'));
  ok(!page.ui.go.hidden && page.text(page.ui.go) === 'Restaurarlo' && go.searchParams.get('tab') === 'backups' && go.searchParams.get('restore') === 'digitalisimo-backup-sitio.zip' && go.hash === '#digitalisimo-backups-history' && !go.searchParams.has('open') && !go.searchParams.has('message'), '«Restaurarlo» no abre su fila en la lista: ' + page.ui.go.getAttribute('href'));
  ok(page.ui.cancel.hidden && page.ui.import.hidden && page.ui.resume.hidden, 'Terminada, el panel ofrece «Cancelar», importar o terminar');
  ok(Object.keys(page.storage.data).length === 0, 'Una subida terminada sigue recordada para reanudarse');
  ok(monotonic(page.percents()) && /Empezó a las \d{2}:\d{2} · duró 5 s/.test(page.text(page.ui.time)), 'La barra retrocedió o el tiempo no se ve: ' + page.percents() + ' / ' + page.text(page.ui.time));
});

await scenario('U2 · un 409 y cortes de red: los reintentos se ven, la nota se va y la barra no retrocede', async () => {
  const s = server({ failures: {
    3: (state) => { state.handle({ action: 'digitalisimo_backups_upload_chunk', nonce: 'nonce-upload', context: 'site', id: 'sesion-1', offset: String(state.total), chunk: bytes.slice(state.total, state.total + MiB) }, 0); return 'network'; },
    5: () => 'network',
  } });
  const page = setup({ server: s.handle });
  await run(page);
  ok(JSON.stringify(joined(s.received)) === JSON.stringify(bytes), 'Tras el 409 y los cortes el archivo no quedó idéntico');
  const retries = page.seen.filter((seen) => /Se reintentará en 2 s \(1 de 6\); lo recibido no se sube otra vez\./.test(seen.note) && seen.level === 'warning');
  ok(retries.length >= 1, 'El reintento no se ve en el panel: ' + page.seen.map((x) => x.note).join(' | '));
  ok(page.waits.filter((ms) => ms >= 1000 && ms < 45000).length >= 2, 'Los reintentos no esperaron');
  ok(page.text(page.ui.note) === '' && page.text(page.ui.title) === 'Respaldo subido y verificado', 'La nota del reintento sigue tras volver la conexión: ' + page.text(page.ui.note));
  ok(monotonic(page.percents()), 'La barra retrocedió: ' + page.percents());
});

await scenario('U3 · reanudar tras recargar: el mismo archivo sigue desde lo recibido y lo dice', async () => {
  const s = server();
  for (const offset of [0, MiB, 2 * MiB].slice(0, 2)) s.handle({ action: 'digitalisimo_backups_upload_chunk', nonce: 'nonce-upload', context: 'site', id: 'sesion-1', offset: String(offset), chunk: bytes.slice(offset, offset + MiB) }, 0);
  const page = setup({ stored: { [KEY]: 'sesion-1' }, server: s.handle });
  await run(page);
  ok(JSON.stringify(actions(page)) === JSON.stringify(['status', 'chunk@2', 'finish']), 'No se reanudó desde lo recibido: ' + actions(page));
  ok(JSON.stringify(joined(s.received)) === JSON.stringify(bytes), 'La subida reanudada no reconstruye el archivo');
  ok(/^Continuando desde 2,0 MB de 3,0 MB: lo recibido no se sube otra vez\.$/.test(page.seen[1].detail) && page.seen[1].percent === '56 %', 'Al reanudar el panel no dice desde dónde sigue: ' + JSON.stringify(page.seen[1]));
});

await scenario('U4 · descartado por el servidor: «El respaldo se descartó» con el motivo, sin nada que continuar', async () => {
  const s = server({ failures: { 5: () => ({ status: 422, body: { success: false, data: { code: 'digitalisimo_backups_upload_invalid', message: 'No supera la validación <script>x</script>' } } }) } });
  const page = setup({ server: s.handle });
  await run(page);
  ok(page.text(page.ui.title) === 'El respaldo se descartó' && page.panel.getAttribute('data-state') === 'failed' && page.text(page.ui.result) === 'No supera la validación <script>x</script>', 'El descarte no se dice con su motivo literal: ' + page.text(page.ui.title) + ' / ' + page.text(page.ui.result));
  ok(page.ui.cancel.hidden && page.ui.go.hidden && Object.keys(page.storage.data).length === 0, 'Un archivo descartado sigue recordado o se ofrece continuarlo');
  ok(page.text(page.ui.percent) === '85 %', 'El fallo bajó la barra: ' + page.text(page.ui.percent));
});

await scenario('U5 · cierre con 202 o cortado: se consulta el estado y el panel dice el paso de la comprobación y el registro', async () => {
  for (const cut of ['validating', 'network']) {
    const s = server();
    let finishes = 0;
    let polls = 0;
    const handle = (fields, n) => {
      const action = fields.action.replace('digitalisimo_backups_upload_', '');
      if (action === 'finish') { finishes++; return cut === 'network' ? 'network' : { status: 202, body: { success: false, data: { code: 'digitalisimo_backups_upload_validating', message: 'La subida se está comprobando' } } }; }
      if (action === 'status' && s.total === SIZE) {
        polls++;
        if (polls === 1) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: SIZE, received: SIZE, phase: 'validating', validating: true, attempts: 1, max_attempts: 3, check: { step: 'contents', text: 'Comprobando cada archivo contra el manifiesto (CRC y SHA-256) y la base de datos en seco…', detail: 'Verificando archivo 120 de 5 000', for: 40 } } } };
        if (polls === 2) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: SIZE, received: SIZE, phase: 'validating', validating: true, attempts: 1, max_attempts: 3, check: { step: 'register', text: 'Registrándolo en la lista de respaldos…', detail: '', for: 70 } } } };
        return { status: 200, body: { success: true, data: { id: 'sesion-1', size: SIZE, received: SIZE, phase: 'done', result: s.result } } };
      }
      return s.handle(fields, n);
    };
    const page = setup({ server: handle, statusAfter: 0 });
    await run(page);
    ok(finishes === 1 && page.requests.filter((r) => r.action.endsWith('start')).length === 1 && polls >= 3, `Con el cierre cortado (${cut}) se volvió a cerrar o a subir, o no se consultó el estado`);
    const notes = page.seen.map((x) => x.note).join(' | ');
    ok(cut === 'network' ? /Se cortó la conexión del cierre/.test(notes) : /La comprobación sigue en otro proceso del servidor/.test(notes), `El panel no dice que la comprobación sigue (${cut}): ` + notes);
    const step = page.seen.find((x) => /Verificando archivo 120 de 5 000/.test(x.detail));
    ok(step && /^Comprobando cada archivo contra el manifiesto/.test(step.detail) && step.waiting, 'El paso de la comprobación no se ve: ' + page.seen.map((x) => x.detail).join(' | '));
    ok(page.seen.some((x) => x.phases === 'upload:done,check:done,register:current' && x.percent === '97 %'), 'El registro no se ve como la fase 3: ' + page.seen.map((x) => x.phases + ' ' + x.percent).join(' | '));
    ok(page.text(page.ui.title) === 'Respaldo subido y verificado', 'No terminó con el resultado');
  }
});

await scenario('U6 · una subida ya cerrada: su resultado, sin subir ni cerrar otra vez', async () => {
  const s = server();
  const page = setup({ stored: { [KEY]: 'sesion-1' }, server: (fields) => {
    if (fields.action.endsWith('status')) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: SIZE, received: SIZE, phase: 'done', result: s.result } } };
    throw new Error('petición inesperada: ' + fields.action);
  } });
  await run(page);
  ok(JSON.stringify(actions(page)) === JSON.stringify(['status']) && page.text(page.ui.title) === 'Respaldo subido y verificado' && page.text(page.ui.percent) === '100 %', 'Una subida ya cerrada se volvió a subir o no enseña su resultado');
});

await scenario('U7 · un error con motivo (507): se dice, no se repite y la subida se conserva para continuarla o descartarla', async () => {
  const s = server({ failures: { 5: () => ({ status: 507, body: { success: false, data: { code: 'digitalisimo_backups_upload_space', message: 'No hay espacio para descifrar el respaldo. La subida se conserva.' } } }) } });
  const page = setup({ server: s.handle });
  await run(page);
  ok(page.requests.filter((r) => r.action.endsWith('finish')).length === 1, 'Un 507 con motivo se reintentó');
  const result = page.text(page.ui.result);
  ok(page.text(page.ui.title) === 'La subida se detuvo' && result.startsWith('No hay espacio para descifrar el respaldo. La subida se conserva.') && /vuelve a elegir el mismo archivo/.test(result) && !page.ui.cancel.hidden, 'El 507 no dice qué hacer o no deja descartarla: ' + result);
  ok(page.storage.data[KEY] === 'sesion-1', 'Una subida conservada se olvidó');
});

await scenario('U8 · el cierre nunca responde (incidente de /wp): 100 % y «verificado» por el estado; mientras, indeterminada', async () => {
  const s = server();
  let finishes = 0;
  const handle = (fields, n) => {
    const action = fields.action.replace('digitalisimo_backups_upload_', '');
    if (action === 'finish') { finishes++; return { pending: true }; }
    if (action === 'status' && s.total === SIZE) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: SIZE, received: SIZE, phase: 'done', result: s.result } } };
    return s.handle(fields, n);
  };
  const page = setup({ server: handle, statusAfter: 0 });
  await run(page);
  const waiting = page.seen.filter((x) => x.waiting);
  ok(finishes === 1 && waiting.length >= 1 && waiting.every((x) => x.now === null), 'Durante la comprobación la barra no quedó indeterminada o se volvió a cerrar');
  ok(page.text(page.ui.percent) === '100 %' && page.text(page.ui.title) === 'Respaldo subido y verificado', 'No llegó a 100 % «verificado» con phase=done');
});

await scenario('U9 · el proceso que comprobaba murió: se vuelve a cerrar sin subir y se dice', async () => {
  const s = server();
  let finishes = 0;
  const handle = (fields, n) => {
    const action = fields.action.replace('digitalisimo_backups_upload_', '');
    if (action === 'finish') { finishes++; return finishes === 1 ? { pending: true } : { status: 200, body: { success: true, data: s.result } }; }
    if (action === 'status' && s.total === SIZE) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: SIZE, received: SIZE, phase: 'validating', validating: false, attempts: 1, max_attempts: 3 } } };
    return s.handle(fields, n);
  };
  const page = setup({ server: handle, statusAfter: 0 });
  await run(page);
  ok(finishes === 2 && page.requests.filter((r) => r.action.endsWith('start')).length === 1, 'No se volvió a cerrar una comprobación que nadie hacía (o se subió otra vez)');
  ok(page.seen.some((x) => /Nadie la estaba comprobando: el proceso del servidor se cortó \(corte 1 de 3/.test(x.note)), 'No se dice que se vuelve a cerrar');
  ok(page.text(page.ui.title) === 'Respaldo subido y verificado', 'No terminó');
});

await scenario('U10 · sin cierres en cascada: el rechazo del cierre abortado no cuenta (B4)', async () => {
  let total = 0;
  let finishes = 0;
  let polls = 0;
  let validatingFrom = Infinity;
  const page = setup({ statusAfter: 0, server: (fields) => {
    const action = fields.action.replace('digitalisimo_backups_upload_', '');
    const okay = (data) => ({ status: 200, body: { success: true, data } });
    if (action === 'start') return okay({ id: 's1', size: SIZE, received: 0, chunk_size: 2 * MiB });
    if (action === 'chunk') { total += fields.chunk.length; return okay({ id: 's1', size: SIZE, received: total, chunk_size: 2 * MiB }); }
    if (action === 'finish') { finishes++; if (finishes > 1) validatingFrom = Math.min(validatingFrom, polls + 2); return { pending: true }; }
    if (action === 'status') { polls++; if (polls >= validatingFrom + 6) return okay({ id: 's1', size: SIZE, received: SIZE, phase: 'done', result: { file: 'f.zip', listed: true, confirmation: 'RESTAURAR', problems: [] } }); return okay({ id: 's1', size: SIZE, received: SIZE, phase: 'validating', validating: polls >= validatingFrom }); }
    return { status: 400, body: {} };
  } });
  await run(page);
  ok(finishes <= 2 && page.text(page.ui.title) === 'Respaldo subido y verificado', `Cierres en cascada: ${finishes}`);
});

await scenario('U11 · en la lista, o ya estaba (duplicado): el resultado lo dice y lleva a su fila', async () => {
  for (const duplicate of [false, true]) {
    const s = server({ result: { duplicate, problems: [] } });
    const page = setup({ server: s.handle });
    await run(page);
    const result = page.text(page.ui.result);
    ok(duplicate ? /^Ese mismo respaldo ya estaba en la lista como digitalisimo-backup-sitio\.zip: no se guardó otra copia\./.test(result) : /^Ya está en la lista de respaldos/.test(result), 'El resultado no dice que está en la lista: ' + result);
    ok(!/sin registro|SIN FIRMA/.test(result) && !page.ui.go.hidden, 'Lo subido aún habla de «sin registro» o no lleva a su fila');
  }
});

await scenario('U12 · 504 tras tres cortes: pide WP-CLI, se conserva y se puede descartar', async () => {
  const s = server({ failures: { 5: () => ({ status: 504, body: { success: false, data: { code: 'digitalisimo_backups_upload_timeout', message: 'La comprobación de esta subida se cortó 3 veces (el tiempo límite del servidor o del proxy). Se conserva: termínala con WP-CLI (wp digitalisimo-backups upload finish sesion-1), que no tiene límite de tiempo, o descártala.' } } }) } });
  const page = setup({ server: s.handle });
  await run(page);
  const result = page.text(page.ui.result);
  ok(page.requests.filter((r) => r.action.endsWith('finish')).length === 1 && /wp digitalisimo-backups upload finish sesion-1/.test(result) && /La subida se conserva en el servidor/.test(result) && !page.ui.cancel.hidden && page.panel.getAttribute('data-state') === 'failed', 'El 504 no pide WP-CLI o no deja descartarla: ' + result);
});

await scenario('U13 · «Cancelar» del panel durante la subida: borra lo recibido y lo dice; nunca mientras se comprueba', async () => {
  const s = server();
  let page = null;
  const handle = (fields, n) => {
    if (fields.action.endsWith('chunk') && Number(fields.offset) === MiB) page.ui.cancel.listeners.click[0]({ preventDefault() {} });
    return s.handle(fields, n);
  };
  page = setup({ server: handle });
  await run(page);
  await settle();
  ok(page.confirms.length === 1 && page.requests.some((r) => r.action.endsWith('cancel') && r.id === 'sesion-1') && !page.requests.some((r) => r.action.endsWith('finish')), 'Cancelar no pidió confirmación o no borró la subida: ' + actions(page));
  ok(page.text(page.ui.title) === 'Subida cancelada' && page.text(page.ui.result) === 'Subida cancelada; se borró lo recibido.' && page.panel.getAttribute('data-state') === 'cancelled' && Object.keys(page.storage.data).length === 0, 'La cancelación no se dice: ' + page.text(page.ui.title));
  const checking = setup({ server: server().handle });
  await run(checking);
  ok(checking.seen.filter((x) => x.waiting).every((x) => !x.cancel), '«Cancelar» se ofrece mientras el servidor comprueba');
});

await scenario('U14 · un .dgbk sin su frase: «sin registro», con el motivo y «Agregar al listado»', async () => {
  const s = server({ result: { file: 'digitalisimo-x.dgbk', registered: false, listed: false, needs_secret: true, restorable: false, uploaded: null, confirmation: 'RESTAURAR', problems: ['Está cifrado con la clave abcd1234, que este sitio no tiene: quedó en el almacén «sin registro», con el nombre digitalisimo-x.dgbk. Da su frase para agregarlo a la lista.'] } });
  const page = setup({ server: s.handle });
  await run(page);
  const go = new URL(page.ui.go.getAttribute('href'));
  ok(page.text(page.ui.title) === 'Respaldo subido, sin registro' && /no está en la lista de respaldos/.test(page.text(page.ui.result)) && /Da su frase para agregarlo a la lista/.test(page.text(page.ui.result)), 'Un .dgbk sin frase no dice que quedó sin registro y por qué: ' + page.text(page.ui.result));
  ok(page.text(page.ui.go) === 'Agregarlo a la lista' && go.searchParams.get('tab') === 'tools' && go.searchParams.get('open') === 'restore-file' && go.hash === '#digitalisimo-backups-section-restore-file', 'No lleva a «Agregar al listado»: ' + page.ui.go.getAttribute('href'));
});

await scenario('U15 · recargar a media comprobación: el panel vuelve a ella y la sigue; si nadie la comprueba, se termina sin subir', async () => {
  const checking = { id: 'sesion-1', name: file.name, size: SIZE, received: SIZE, chunk_size: MiB, phase: 'validating', validating: true, attempts: 1, max_attempts: 3, age: 95, check: { step: 'contents', text: 'Comprobando cada archivo contra el manifiesto (CRC y SHA-256) y la base de datos en seco…', detail: 'Verificando archivo 300 de 5 000', for: 50 } };
  const s = server();
  let polls = 0;
  const page = setup({ sessions: [checking], statusAfter: 0, stored: { [ACTIVE]: 'sesion-1' }, server: (fields) => {
    if (!fields.action.endsWith('status')) throw new Error('petición inesperada al seguir la comprobación: ' + fields.action);
    polls++;
    return polls < 3 ? { status: 200, body: { success: true, data: checking } } : { status: 200, body: { success: true, data: { id: 'sesion-1', size: SIZE, received: SIZE, phase: 'done', result: s.result } } };
  } });
  ok(!page.panel.hidden && page.panel.parentNode === page.slot && page.section.open && page.text(page.ui.title) === 'Comprobando el respaldo subido' && page.phases().join(',') === 'upload:done,check:current,register:pending' && /Verificando archivo 300 de 5 000/.test(page.text(page.detail)) && page.bar.hasAttribute('data-waiting') && /lleva 1 min 35 s/.test(page.text(page.ui.time)), 'Al recargar el panel no vuelve a la comprobación en curso: ' + page.text(page.ui.title) + ' / ' + page.text(page.detail) + ' / ' + page.text(page.ui.time));
  await until(page, () => page.text(page.ui.title) === 'Respaldo subido y verificado', 'La comprobación seguida tras recargar no termina');
  ok(page.requests.every((r) => r.action.endsWith('status')) && !page.submit.disabled && !(ACTIVE in page.storage.data), 'Tras recargar se volvió a cerrar o a subir, o la subida sigue recordada: ' + actions(page));
  // Nadie la comprueba (el proceso murió con la recarga): se dice y «Terminar la comprobación» la cierra, sin subirla.
  const dead = Object.assign({}, checking, { validating: false });
  let finishes = 0;
  const stuck = setup({ sessions: [dead], statusAfter: 0, server: (fields) => {
    // Hasta que se vuelve a cerrar, nadie la comprueba; después, el cierre nuevo la está comprobando.
    if (fields.action.endsWith('status')) return { status: 200, body: { success: true, data: finishes ? checking : dead } };
    if (fields.action.endsWith('finish')) { finishes++; assert.equal(fields.secret, 'frase <b>secreta</b>'); return { status: 200, body: { success: true, data: s.result } }; }
    throw new Error('petición inesperada: ' + fields.action);
  } });
  await until(stuck, () => !stuck.ui.resume.hidden, 'Una comprobación que nadie hace no se ofrece terminar');
  ok(stuck.text(stuck.ui.title) === 'La comprobación se interrumpió' && /1 de 3 cortes antes de pedir WP-CLI/.test(stuck.text(stuck.ui.result)) && finishes === 0, 'No dice que la comprobación se interrumpió: ' + stuck.text(stuck.ui.result));
  stuck.ui.resume.listeners.click[0]({ preventDefault() {} });
  await until(stuck, () => stuck.text(stuck.ui.title) === 'Respaldo subido y verificado', '«Terminar la comprobación» no la termina');
  ok(finishes === 1 && !stuck.requests.some((r) => /start|chunk/.test(r.action)) && stuck.text(stuck.ui.percent) === '100 %', '«Terminar la comprobación» subió otra vez o no cerró');
});

await scenario('U16 · al recargar con una subida a medias: «Subida en pausa», seguir sin que la barra retroceda, o descartarla', async () => {
  const half = { id: 'sesion-1', name: file.name, size: SIZE, received: MiB, chunk_size: MiB, phase: 'uploading', validating: false, attempts: 0, max_attempts: 3, age: 600 };
  const s = server();
  s.handle({ action: 'digitalisimo_backups_upload_chunk', nonce: 'nonce-upload', context: 'site', id: 'sesion-1', offset: '0', chunk: bytes.slice(0, MiB) }, 0);
  const page = setup({ sessions: [half], stored: { [KEY]: 'sesion-1' }, server: s.handle });
  ok(page.text(page.ui.title) === 'Subida en pausa' && page.text(page.ui.percent) === '28 %' && /1,0 MB de 3,0 MB recibidos/.test(page.text(page.ui.result)) && /vuelve a elegir el mismo archivo/.test(page.text(page.ui.result)) && !page.ui.cancel.hidden && page.text(page.ui.time) === '' && page.panel.parentNode === page.slot, 'La subida a medias no se enseña en pausa con qué hacer: ' + page.text(page.ui.title) + ' / ' + page.text(page.ui.result));
  await run(page);
  ok(JSON.stringify(actions(page)) === JSON.stringify(['status', 'chunk@1', 'chunk@2', 'finish']) && monotonic(page.percents()) && page.percents()[0] >= 28, 'Seguir la subida en pausa la empezó de nuevo o bajó la barra: ' + actions(page) + ' / ' + page.percents());
  const discard = setup({ sessions: [half], stored: { [KEY]: 'sesion-1' }, server: s.handle });
  discard.ui.cancel.listeners.click[0]({ preventDefault() {} });
  await settle();
  ok(discard.requests.length === 1 && discard.requests[0].action.endsWith('cancel') && discard.text(discard.ui.title) === 'Subida cancelada', '«Cancelar» no descarta la subida en pausa');
});

await scenario('U17 · un .tar.gz de Backuply: «Importarlo ahora» con su nombre en el almacén', async () => {
  const tar = new File([bytes.slice(0, 2 * MiB)], 'backup_sitio_2026.tar.gz', { lastModified: 1762650000000 });
  const s = server({ result: { file: 'backup_sitio_2026.tar.gz', container: 'backuply', backuply: { source: 'store', name: 'backup_sitio_2026.tar.gz' }, registered: false, listed: false, problems: ['Es un respaldo de Backuply: impórtalo.'] } });
  let total = 0;
  const page = setup({ theFile: tar, server: (fields, n) => {
    if (fields.action.endsWith('chunk')) { total += fields.chunk.length; return { status: 200, body: { success: true, data: { id: 'sesion-1', size: 2 * MiB, received: total, chunk_size: MiB } } }; }
    if (fields.action.endsWith('start')) return { status: 200, body: { success: true, data: { id: 'sesion-1', size: 2 * MiB, received: 0, chunk_size: MiB } } };
    if (fields.action.endsWith('finish')) return { status: 200, body: { success: true, data: s.result } };
    return s.handle(fields, n);
  } });
  await run(page);
  ok(page.text(page.ui.title) === 'Respaldo de Backuply subido' && !page.ui.import.hidden && page.ui.import.querySelector('input[name="backuply_file"]').value === 'backup_sitio_2026.tar.gz' && page.ui.go.hidden && /impórtalo para convertirlo en un ZIP de Backups/.test(page.text(page.ui.result)), 'Tras subir un .tar.gz de Backuply no se ofrece importarlo: ' + page.text(page.ui.result));
});

await scenario('U18 · lo que terminó mientras se recargaba: su resultado, una vez', async () => {
  const s = server();
  const page = setup({ stored: { [ACTIVE]: 'sesion-1' }, server: (fields) => (fields.action.endsWith('status') ? { status: 200, body: { success: true, data: { id: 'sesion-1', name: file.name, size: SIZE, received: SIZE, phase: 'done', age: 30, result: s.result } } } : { status: 400, body: {} }) });
  await settle();
  ok(page.text(page.ui.title) === 'Respaldo subido y verificado' && !page.ui.go.hidden && !(ACTIVE in page.storage.data), 'El resultado que llegó al recargar no se enseña (o se enseñaría otra vez)');
});

await scenario('U19 · con una restauración en curso el panel no se tapa: la subida espera', async () => {
  const restoring = { id: '11111111-2222-4333-8444-555555555555', kind: 'restore', status: 'running', status_label: 'En curso', phase: 'database', phase_label: 'Base de datos', percent: 45, detail: '', terminal: false, running: true, retry_in: 0, can_cancel: true, origin_label: 'Restauración', destination_label: 'x.zip', created_label: '2026-11-16 09:00', track: [['inspect', 'Verificación', 0], ['database', 'Base de datos', 40]], elapsed: 60, parts: 'todo' };
  const page = setup({ view: restoring, server: () => { throw new Error('no debe subir'); } });
  page.form.listeners.submit[0]({ preventDefault() {} });
  await settle();
  ok(page.requests.length === 0 && /Hay una restauración en curso/.test(page.text(page.status)) && page.text(page.ui.title) === 'Restauración en curso' && !page.submit.disabled, 'La subida tapó la restauración en el panel');
});

await scenario('U21 · el reloj del panel corre mientras un fragmento tarda en llegar', async () => {
  const s = server();
  const page = setup({ server: (fields, n) => (fields.action.endsWith('chunk') ? { pending: true } : s.handle(fields, n)) });
  page.form.listeners.submit[0]({ preventDefault() {} });
  await settle();
  const before = page.text(page.ui.time);
  page.clock.now += 65000;
  for (const tick of page.intervals) tick();
  ok(/lleva \d+ s$/.test(before) && /lleva 1 min \d+ s$/.test(page.text(page.ui.time)), 'El reloj del panel no corre durante la subida: ' + before + ' → ' + page.text(page.ui.time));
});

if (failed.length) throw new Error('FALLA: ' + failed.length + ' caso(s) de la subida con el panel de avance: ' + failed.join(' | '));
console.log(`DIGITALÍSIMO Backups: la subida usa el mismo panel de avance que el respaldo (fases Subida, Comprobación y Registro; bytes, velocidad, tiempo restante, reintentos, 202, 504, cancelar, recargar a mitad y resultado con su fila), sin perder su robustez (${checks} comprobaciones).`);
