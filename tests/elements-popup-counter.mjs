// Réplica de los contadores de popups (F-front, frente A, D11 y §6.1).
// - `setViewsAndSessions()` y su condición se extraen de assets/js/elements-handlers.js (el paquete de PRO, sin
//   editar) y se ejecutan con `vm` junto a modules/digitalisimo-front/assets/popup-counter.js sobre un `storage`
//   falso con la semántica del núcleo (local y de sesión): primera visita, misma sesión y sesión nueva dan los mismos
//   efectos y la misma secuencia de lecturas y escrituras.
// - La réplica no hace nada en el editor, en la vista previa de WordPress ni si el paquete de PRO está cargado.
// - El archivo compila, pesa como mucho 0,5 KB y escucha el evento nativo que el núcleo despacha en `window`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const E = join(ROOT, 'digitalisimo-elements');
const handlers = readFileSync(join(E, 'assets/js/elements-handlers.js'), 'utf8');
const counterPath = join(E, 'modules/digitalisimo-front/assets/popup-counter.js');
const counter = readFileSync(counterPath, 'utf8');
const core = readFileSync(join(E, 'elementor/assets/js/frontend.js'), 'utf8');

/** Cuerpo de un método de clase con sus llaves emparejadas. */
function methodBody(source, signature) {
  const start = source.indexOf(signature);
  assert.notEqual(start, -1, `No se encontró ${signature} en elements-handlers.js`);
  const open = source.indexOf('{', start);
  let depth = 0;
  for (let i = open; i < source.length; i++) {
    if (source[i] === '{') depth++;
    else if (source[i] === '}' && --depth === 0) return source.slice(open + 1, i);
  }
  throw new Error(`Llaves sin cerrar en ${signature}`);
}

// El módulo de popups de PRO: la condición y los contadores, tal como están en el paquete.
const popupModule = handlers.slice(handlers.indexOf('//#region modules/popup/assets/js/frontend/frontend.js'));
const shouldBody = methodBody(popupModule, 'shouldSetViewsAndSessions() {');
const setBody = methodBody(popupModule, 'setViewsAndSessions() {');
assert.match(shouldBody, /!elementorFrontend\.isEditMode\(\) && !elementorFrontend\.isWPPreviewMode\(\) && ElementorProFrontendConfig\.popup\.hasPopUps/, 'La condición de PRO cambió: revisar la réplica');
assert.match(popupModule, /if \(this\.shouldSetViewsAndSessions\(\)\) this\.setViewsAndSessions\(\);/, 'PRO dejó de contar al construir el módulo de popups');

/** `storage` del núcleo: `get(key, { session })` y `set(key, value, { session })` sobre dos almacenes. */
function makeStorage(local, session, log) {
  return {
    get(key, options) {
      const store = options && options.session ? session : local;
      log.push(['get', key, !!(options && options.session)]);
      return store[key];
    },
    set(key, value, options) {
      const store = options && options.session ? session : local;
      log.push(['set', key, value, !!(options && options.session)]);
      store[key] = value;
    },
  };
}

function frontend(storage, { edit = false, wpPreview = false } = {}) {
  return { storage, isEditMode: () => edit, isWPPreviewMode: () => wpPreview };
}

/** PRO: el constructor del módulo de popups. */
function runPro(local, session, flags = {}, hasPopUps = true) {
  const log = [];
  const elementorFrontend = frontend(makeStorage(local, session, log), flags);
  const context = { elementorFrontend, ElementorProFrontendConfig: { popup: { hasPopUps } } };
  vm.runInNewContext(`(function(){ const should = () => { ${shouldBody} }; if (should()) { ${setBody} } })();`, context);
  return log;
}

/** Réplica: se ejecuta en posición `before` y actúa en el evento nativo `elementor/frontend/init`. */
function runReplica(local, session, flags = {}, extra = {}) {
  const log = [];
  const listeners = {};
  const window = {
    addEventListener(name, callback) { (listeners[name] = listeners[name] || []).push(callback); },
    ...extra,
  };
  vm.runInNewContext(counter, { window });
  assert.deepEqual(Object.keys(listeners), ['elementor/frontend/init'], 'La réplica sólo escucha elementor/frontend/init');
  // El núcleo crea elementorFrontend (con su storage) antes de despachar el evento.
  window.elementorFrontend = frontend(makeStorage(local, session, log), flags);
  listeners['elementor/frontend/init'].forEach((callback) => callback({ type: 'elementor/frontend/init' }));
  return log;
}

// Primera visita, misma sesión y sesión nueva, en los dos lados con almacenes separados.
const pro = { local: {}, session: {} };
const replica = { local: {}, session: {} };
const steps = [
  ['primera visita', () => {}],
  ['misma sesión', () => {}],
  ['sesión nueva', (state) => { state.session = {}; }],
  ['otra vista', () => {}],
];
for (const [label, before] of steps) {
  before(pro);
  before(replica);
  const a = runPro(pro.local, pro.session);
  const b = runReplica(replica.local, replica.session);
  assert.deepEqual(b, a, `${label}: la réplica hace las mismas lecturas y escrituras que PRO`);
  assert.deepEqual(replica.local, pro.local, `${label}: el almacén local queda igual`);
  assert.deepEqual(replica.session, pro.session, `${label}: el almacén de sesión queda igual`);
}
assert.deepEqual(pro.local, { pageViews: 4, sessions: 2 }, 'Cuatro vistas en dos sesiones');
assert.deepEqual(pro.session, { activeSession: true }, 'La sesión queda marcada');

// Valores previos de otras páginas: se continúan, no se reinician.
{
  const a = { local: { pageViews: 7, sessions: 3, other: 'x' }, session: {} };
  const b = structuredClone(a);
  assert.deepEqual(runReplica(b.local, b.session), runPro(a.local, a.session), 'Con contadores previos, mismas operaciones');
  assert.deepEqual(b, a, 'Con contadores previos, mismo resultado');
}

// La réplica no hace nada en el editor, en la vista previa ni con el paquete de PRO cargado; tampoco PRO.
for (const [label, flags, extra] of [
  ['editor', { edit: true }, {}],
  ['vista previa de WordPress', { wpPreview: true }, {}],
  ['paquete de PRO cargado', {}, { elementorProFrontend: {} }],
]) {
  const local = {};
  const session = {};
  assert.deepEqual(runReplica(local, session, flags, extra), [], `${label}: la réplica no toca el almacén`);
  assert.deepEqual(local, {}, `${label}: sin vistas contadas`);
  if (!extra.elementorProFrontend) assert.deepEqual(runPro({}, {}, flags), [], `${label}: PRO tampoco cuenta`);
}
assert.deepEqual(runPro({}, {}, {}, false), [], 'Sin popups PRO no cuenta (la puerta sólo imprime la réplica con hasPopUps)');

// Sin elementorFrontend (otro script despachó el evento) la réplica no falla.
{
  const listeners = {};
  vm.runInNewContext(counter, { window: { addEventListener(name, callback) { listeners[name] = callback; } } });
  assert.doesNotThrow(() => listeners['elementor/frontend/init']({}), 'Sin elementorFrontend la réplica no lanza');
}

// El archivo compila, cabe en el presupuesto y no puede cerrar la etiqueta <script> en línea.
assert.doesNotThrow(() => new vm.Script(counter, { filename: 'popup-counter.js' }), 'popup-counter.js compila');
assert.ok(Buffer.byteLength(counter) <= 512, `popup-counter.js pesa ${Buffer.byteLength(counter)} B (tope 512)`);
assert.ok(!/<\/script/i.test(counter), 'popup-counter.js no contiene </script>');

// El núcleo crea storage antes de despachar el evento nativo en window, y lo despacha como CustomEvent.
const init = methodBody(core, 'init() {\n\t\t\t\tthis.hooks = new EventManager();');
assert.ok(init.indexOf('this.storage = new') !== -1 && init.indexOf('this.storage = new') < init.indexOf('"elementor/frontend/init"'), 'El núcleo crea storage antes de despachar elementor/frontend/init');
assert.match(init, /_events\.default\.dispatch\(this\.elements\.\$window, "elementor\/frontend\/init"\)/, 'El núcleo despacha elementor/frontend/init en window');
assert.match(core, /context\.dispatchEvent\(new CustomEvent\(event, \{ detail: data \}\)\)/, 'El evento es nativo (CustomEvent)');
// PRO se crea como global de window, que es lo que la réplica mira.
assert.match(readFileSync(join(E, 'assets/js/frontend.js'), 'utf8'), /window\.elementorProFrontend = new ElementorProFrontend\(\);/, 'PRO publica window.elementorProFrontend');

console.log('DIGITALÍSIMO Elements: réplica de contadores de popups igual a la de PRO.');
