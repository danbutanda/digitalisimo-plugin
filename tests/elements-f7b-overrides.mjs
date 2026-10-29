// F7b · frente E: overrides-editor.js en una máquina virtual con dobles de `elementor`, `elementorCommon` y del DOM.
// - El archivo compila, es ES2017, pesa como mucho 8 KB, no tiene textos visibles propios y sólo se encola en el editor.
// - La clave sólo se ve en plantillas de la biblioteca (clase del body y regla que oculta los dos controles).
// - Aviso de clave no válida, de clave repetida (contada en el modelo del documento abierto, sin AJAX) y de las
//   instancias que la sobrescriben (digitalisimo_f7b_slot_uses, una vez por documento).
// - Plantilla: digitalisimo_f7b_slots con el ID de la plantilla; opciones de «Clave» en cada fila y en la configuración
//   del repetidor; opción «(sin destino)» y aviso de sobrescrituras huérfanas (C2-31) y de kind que no aplica; sin
//   claves; error de lectura; sin volver a pintar los selects si las opciones no cambian.
// Uso: (ulimit -v 3000000; timeout 150 node --max-old-space-size=600 tests/elements-f7b-overrides.mjs)
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const FILE = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/assets/js/overrides-editor.js', import.meta.url);
const PHP = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/overrides/class-module.php', import.meta.url);
const source = readFileSync(FILE, 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// ── Archivo ──────────────────────────────────────────────────────────────────────────────────────────────────────
const bytes = statSync(FILE).size;
ok(bytes <= 8192, `overrides-editor.js pesa ${bytes} bytes; el presupuesto es 8 KB`);
assert.doesNotThrow(() => new vm.Script(source, { filename: 'overrides-editor.js' }), 'overrides-editor.js compila');
for (const [re, what] of [[/\?\./, 'encadenamiento opcional'], [/\?\?/, 'fusión de nulos'], [/(\|\||&&)=/, 'asignación lógica'], [/\bclass\s+\w/, 'clases'], [/#\w+\s*[=(]/, 'campos privados'], [/\bimport\s*\(/, 'import dinámico'], [/\{\s*\.\.\./, 'propagación en objetos'], [/\basync\b|\bawait\b/, 'async']]) {
  ok(!re.test(source), `overrides-editor.js debe ser ES2017 (sin ${what})`);
}
const literals = [...source.matchAll(/'([^'\\]*(?:\\.[^'\\]*)*)'|`([^`]*)`/g)].map((m) => m[1] || m[2] || '');
assert.deepEqual(literals.filter((t) => /Elementor|[áéíóúñ¿¡]/.test(t)), [], 'sin textos visibles propios: vienen de i18n (PHP)');
checks++;
const php = readFileSync(PHP, 'utf8');
ok(php.includes("add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) )") && !/wp_enqueue_scripts|elementor\/frontend\/after_enqueue/.test(php), 'el script sólo se encola en el editor');
ok(!/fetch\(|XMLHttpRequest|jQuery\.ajax|\$\.ajax/.test(source), 'las lecturas van por elementorCommon.ajax (nonce y editor_post_id del núcleo)');

// ── Dobles ───────────────────────────────────────────────────────────────────────────────────────────────────────
class Emitter {
  constructor() { this.handlers = []; }
  on(evt, fn) { for (const e of String(evt).split(' ')) this.handlers.push([e, fn]); return this; }
  off(evt, fn) { this.handlers = this.handlers.filter(([e, f]) => !((null === evt || e === evt) && f === fn)); return this; }
  trigger(evt, ...args) { for (const [e, f] of this.handlers.slice()) if (e === evt) f(...args); return this; }
}
let cid = 0;
class Model extends Emitter {
  constructor(attrs) { super(); this.attributes = { ...attrs }; this.cid = 'c' + (++cid); }
  get(k) { return this.attributes[k]; }
  set(k, v) { this.attributes[k] = v; this.trigger('change', this); }
}
class Coll extends Emitter {
  constructor(models) { super(); this.models = models; }
  each(fn) { this.models.forEach(fn); }
  findWhere(q) { return this.models.find((m) => Object.keys(q).every((k) => m.get(k) === q[k])) || null; }
  toJSON() { return this.models.map((m) => ({ ...m.attributes })); }
}
class El {
  constructor(tag) { this.tag = tag; this.children = []; this.attrs = {}; this.textContent = ''; }
  get firstChild() { return this.children[0] || null; }
  appendChild(c) { this.children.push(c); return c; }
  removeChild(c) { this.children.splice(this.children.indexOf(c), 1); }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; }
  get lines() { return this.children.map((c) => c.textContent); }
}
const classes = new Set();
const head = new El('head');
const document = {
  head,
  body: { classList: { toggle: (name, on) => (on ? classes.add(name) : classes.delete(name)) } },
  createElement: (tag) => new El(tag),
};
const T = {
  choose: 'Elige', orphanOpt: '%s (sin destino)', repeated: '%1$s (×%2$d)', orphans1: 'Una huérfana', orphans: '%d huérfanas',
  wrongKind1: 'Un kind no aplica', wrongKind: '%d kinds no aplican', noSlots: 'Sin claves', loadError: 'Error', invalid: 'No válida',
  duplicate: 'Repetida en %d', uses1: 'Una instancia', uses: '%d instancias',
};
const CONFIG = {
  slot: '_digitalisimo_slot', pattern: '^[a-z0-9_-]{1,40}$', docKey: 'digitalisimoOverrides',
  types: { heading: 'Encabezado', button: 'Botón', 'text-editor': 'Editor de texto', image: 'Imagen', icon: 'Icono', video: 'Vídeo', 'icon-box': 'Caja de icono' },
  kinds: { heading: ['text', 'html', 'link', 'hide'], 'text-editor': ['text', 'html', 'hide'], image: ['text', 'image', 'link', 'hide'], button: ['text', 'link', 'hide'], icon: ['link', 'hide'], video: ['image', 'link', 'hide'], 'icon-box': ['text', 'html', 'link', 'hide'] },
  actions: { slots: 'digitalisimo_f7b_slots', uses: 'digitalisimo_f7b_slot_uses' },
  i18n: T,
};
// La configuración de PHP tiene las mismas claves que este doble.
for (const key of Object.keys(T)) ok(php.includes(`'${key}'`), `i18n.${key} existe en Module::config()`);

const requests = [];
const hooks = {};
const channel = new Emitter();
const appEvents = new Emitter();
let currentDoc = null;
let page = null;
const timers = [];
const elementor = {
  documents: { getCurrent: () => currentDoc },
  hooks: { addAction: (name, fn) => { hooks[name] = fn; } },
  channels: { editor: channel },
  on: (evt, fn) => appEvents.on(evt, fn),
  getPanelView: () => ({ getCurrentPageView: () => page }),
  elements: null,
};
const context = {
  window: null,
  document,
  elementor,
  elementorCommon: { ajax: { addRequest: (action, options) => requests.push({ action, options }) } },
  digitalisimoOverrides: CONFIG,
  setTimeout: (fn) => { timers.push(fn); return timers.length; },
  clearTimeout: () => { timers.length = 0; },
  addEventListener: () => { throw new Error('elementor ya existe: no debe esperar a elementor/init'); },
  JSON, RegExp, String, Object, Array,
};
context.window = context;
vm.createContext(context);
vm.runInContext(source, context, { filename: 'overrides-editor.js' });
const flush = () => { const run = timers.splice(0); run.forEach((fn) => fn()); };

// ── Arranque ─────────────────────────────────────────────────────────────────────────────────────────────────────
ok(1 === head.children.length && head.children[0].textContent.includes('body:not(.digi-ovr-lib) .elementor-control-_digitalisimo_slot,body:not(.digi-ovr-lib) .elementor-control-digitalisimo_slot_note'), 'una regla oculta la clave y su aviso fuera de las plantillas');
ok(head.children[0].textContent.includes(':has(.digitalisimo-overrides-notice:empty)'), 'los avisos vacíos no ocupan sitio');
ok('function' === typeof hooks['panel/open_editor/widget'], 'se engancha a panel/open_editor/widget');
const open = hooks['panel/open_editor/widget'];

// ── Clave en una plantilla ───────────────────────────────────────────────────────────────────────────────────────
const widget = (type, settings, children = []) => new Model({ widgetType: type, settings: new Model(settings), elements: new Coll(children) });
const h1 = widget('heading', { _digitalisimo_slot: 'titulo' });
const h2 = widget('heading', { _digitalisimo_slot: 'titulo' });
const html = widget('html', { _digitalisimo_slot: 'titulo' });
const box = new Model({ elType: 'container', settings: new Model({}), elements: new Coll([h2, html]) });
const tree = new Coll([h1, box]);
currentDoc = { id: 300, config: { id: 300, digitalisimoOverrides: { library: true } }, container: { model: new Model({ elements: tree }) } };
appEvents.trigger('document:loaded');
flush();
ok(classes.has('digi-ovr-lib'), 'en una plantilla de la biblioteca el body lleva digi-ovr-lib');
const note = new El('div');
page = { el: { querySelector: (s) => ('.digitalisimo-slot-note' === s ? note : null) }, collection: new Coll([]), children: { findByModelCid: () => null } };
open({}, h1);
flush();
ok(1 === requests.length && 'digitalisimo_f7b_slot_uses' === requests[0].action && 'digitalisimo_f7b_slot_uses-300' === requests[0].options.unique_id, 'pide una vez las instancias que sobrescriben las claves de esta plantilla');
assert.deepEqual(note.lines, ['Repetida en 2']);
checks++;
requests[0].options.success({ ready: true, slots: { titulo: 3 } });
flush();
assert.deepEqual(note.lines, ['Repetida en 2', '3 instancias'], 'clave repetida (el HTML no cuenta) e instancias');
checks++;
h1.get('settings').set('_digitalisimo_slot', 'Título');
flush();
assert.deepEqual(note.lines, ['No válida'], 'una clave con otra forma avisa');
checks++;
h1.get('settings').set('_digitalisimo_slot', 'unica');
flush();
assert.deepEqual(note.lines, [], 'una clave única sin instancias no avisa');
checks++;
ok(1 === requests.length, 'las instancias no se vuelven a pedir en el mismo documento');
h1.get('settings').set('_digitalisimo_slot', 'titulo');
h2.get('settings').set('_digitalisimo_slot', 'otra');
flush();
assert.deepEqual(note.lines, ['3 instancias']);
checks++;

// Un documento que no es de la biblioteca: clase fuera y sin avisos.
currentDoc = { id: 1000, config: { id: 1000, digitalisimoOverrides: { library: false } }, container: { model: new Model({ elements: tree }) } };
appEvents.trigger('document:loaded');
flush();
ok(!classes.has('digi-ovr-lib'), 'fuera de la biblioteca el body no lleva digi-ovr-lib');
assert.deepEqual(note.lines, [], 'fuera de la biblioteca no hay avisos de clave');
checks++;
ok(1 === requests.length, 'fuera de la biblioteca no se pide nada');

// ── Plantilla: claves, filas sin destino y kind que no aplica ────────────────────────────────────────────────────
const notice = new El('div');
const makeRow = (attrs) => {
  const model = new Model({ options: { '': 'Elige' } });
  const view = { model, renders: 0, render() { this.renders++; } };
  return { attrs, view, row: { getChildControlView: (name) => ('slot' === name ? view : null) } };
};
const rows = [makeRow({ slot: 'titulo', kind: 'text' }), makeRow({ slot: 'viejo', kind: 'text' }), makeRow({ slot: 'boton', kind: 'image' }), makeRow({ slot: '' })];
const rowModels = new Coll(rows.map((r) => new Model(r.attrs)));
const fields = { slot: { name: 'slot', options: { '': 'Elige' } }, kind: { name: 'kind', options: {} } };
const repModel = new Model({ name: 'digitalisimo_overrides', fields });
const repView = { model: repModel, children: new Coll(rows.map((r) => r.row)) };
const tplSettings = new Model({ template_id: 300, digitalisimo_overrides: rowModels });
const tpl = new Model({ widgetType: 'template', settings: tplSettings, elements: new Coll([]) });
page = {
  el: { querySelector: (s) => ('.digitalisimo-overrides-notice' === s ? notice : null) },
  collection: new Coll([repModel]),
  children: { findByModelCid: (c) => (c === repModel.cid ? repView : null) },
};
open({}, tpl);
flush();
const slotsReq = requests[requests.length - 1];
ok('digitalisimo_f7b_slots' === slotsReq.action && '300' === slotsReq.options.data.template_id && 'digitalisimo_f7b_slots-300' === slotsReq.options.unique_id, 'pide las claves de la plantilla elegida (sólo su ID)');
assert.deepEqual(notice.lines, [], 'mientras carga no avisa');
checks++;
slotsReq.options.success({ slots: [{ key: 'titulo', type: 'heading', count: 1, label: 'Original' }, { key: 'boton', type: 'button', count: 2, label: 'Ir' }] });
flush();
const expected = { '': 'Elige', titulo: 'titulo · Encabezado · Original', boton: 'boton · Botón · Ir (×2)', viejo: 'viejo (sin destino)' };
assert.deepEqual({ ...fields.slot.options }, expected, 'opciones: claves con tipo, extracto y repetición, más la clave huérfana de una fila');
checks++;
for (const r of rows) {
  assert.deepEqual({ ...r.view.model.get('options') }, expected);
  ok(1 === r.view.renders, 'cada select de fila se repinta una vez con las opciones nuevas');
}
assert.deepEqual(notice.lines, ['Una huérfana', 'Un kind no aplica'], 'avisa de la sobrescritura sin destino y de la imagen en un botón');
checks++;
rowModels.trigger('change');
flush();
ok(rows.every((r) => 1 === r.view.renders), 'sin cambios en las opciones no se repinta ningún select');
rowModels.models[1].attributes.slot = 'titulo';
rowModels.trigger('change');
flush();
assert.deepEqual(notice.lines, ['Un kind no aplica'], 'al cambiar la fila huérfana desaparece su aviso');
checks++;
ok(rows.every((r) => 2 === r.view.renders), 'al cambiar las opciones se repintan los selects');

// Plantilla sin claves y error de lectura.
tplSettings.set('template_id', 301);
flush();
const emptyReq = requests[requests.length - 1];
ok('301' === emptyReq.options.data.template_id, 'al cambiar de plantilla se piden sus claves');
emptyReq.options.success({ slots: [] });
flush();
assert.deepEqual(notice.lines, ['Sin claves', '3 huérfanas'], 'una plantilla sin claves lo dice, y las filas que apuntaban a la otra quedan sin destino');
checks++;
tplSettings.set('template_id', 302);
flush();
requests[requests.length - 1].options.error('403');
flush();
assert.deepEqual(notice.lines, ['Error'], 'un error de lectura lo dice');
checks++;
tplSettings.set('template_id', '');
flush();
assert.deepEqual(notice.lines, [], 'sin plantilla no hay aviso');
checks++;
// Al volver a abrir el panel se vuelve a leer la plantilla (pudo cambiar en otra pestaña).
const before = requests.length;
tplSettings.attributes.template_id = 300;
open({}, tpl);
flush();
ok(requests.length === before + 1 && '300' === requests[requests.length - 1].options.data.template_id, 'abrir el panel vuelve a leer las claves');

console.log(`OK: ${checks} comprobaciones.`);
