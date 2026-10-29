// F7b · frente H: «Aplicar a todos los de este tipo» (V.7, C2-15 y C2-16 de la especificación) con un editor falso.
// - El archivo compila, es ES2017, pesa como mucho 8 KB, no tiene textos visibles propios y sólo se encola en el editor.
// - Menú contextual sólo en widgets v3, delante de «Borrar», visible sólo con el permiso «design»; el título cuenta los
//   widgets del mismo tipo del documento (también anidados), sin el origen ni los globales (modelo con templateID).
// - Tras la confirmación (que dice que se pega todo el estilo y cuántos globales quedan fuera): una sola llamada
//   $e.run( 'document/elements/paste-style', { containers, storageKey } ), con el origen en la clave propia en el formato
//   de «Copiar»; la clave se borra también si el comando falla y el portapapeles del usuario no cambia.
// - «Pegar estilo» es el del núcleo: applyPasteStyleData(), validateControls(), pasteStyle(), getHistory() e
//   isStyleTransferControl() se extraen de elementor/assets/js/editor.js y se ejecutan tal cual. Comprueba que no copia
//   los controles con style_transfer => false (acciones, claves, vínculos, etiquetas) y que sin esa marca sí lo haría.
import assert from 'node:assert/strict';
import { readFileSync, existsSync, statSync } from 'node:fs';
import vm from 'node:vm';

const ROOT = new URL('../digitalisimo-elements/', import.meta.url);
const FILE = new URL('modules/digitalisimo-f7b/assets/js/apply-all-editor.js', ROOT);
const PHP = new URL('modules/digitalisimo-f7b/editor/class-module.php', ROOT);
const INLINE_PHP = new URL('modules/digitalisimo-f7b/inline-tags/class-module.php', ROOT);
const CORE = new URL('elementor/assets/js/editor.js', ROOT);
const source = readFileSync(FILE, 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// ── Archivo ──────────────────────────────────────────────────────────────────────────────────────────────────────
const bytes = statSync(FILE).size;
ok(bytes <= 8192, `apply-all-editor.js pesa ${bytes} bytes; el presupuesto es 8 KB`);
ok((() => { new vm.Script(source, { filename: 'apply-all-editor.js' }); return true; })(), 'apply-all-editor.js compila');
for (const [re, what] of [[/\?\./, 'encadenamiento opcional'], [/\?\?/, 'fusión de nulos'], [/(\|\||&&)=/, 'asignación lógica'], [/\bclass\s+\w/, 'clases'], [/#\w+\s*[=(]/, 'campos privados'], [/\bimport\s*\(/, 'import dinámico'], [/\{\s*\.\.\./, 'propagación en objetos'], [/\basync\b|\bawait\b/, 'async'], [/\bjQuery\b|\$\(/, 'jQuery']]) {
  ok(!re.test(source), `apply-all-editor.js debe ser ES2017 y sin jQuery (sin ${what})`);
}
const code = source.split('\n').filter((l) => !/^\s*(\/\/|\/\*)/.test(l)).join('\n');
const literals = [...code.matchAll(/'([^'\\]*(?:\\.[^'\\]*)*)'|`([^`]*)`/g)].map((m) => m[1] || m[2] || '');
assert.deepEqual(literals.filter((t) => /Elementor|[áéíóúñ¿¡«»]/.test(t)), [], 'apply-all-editor.js no tiene textos visibles propios: todos vienen de i18n (PHP)');
checks++;
const php = readFileSync(PHP, 'utf8');
ok(php.includes("add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) )") && !/wp_enqueue_scripts'|elementor\/frontend\/|wp_ajax|register_ajax_action|add_option|update_option/.test(php), 'el script sólo se encola en el editor; sin acciones AJAX ni opciones');
ok(php.includes("array( 'elementor-editor' )") && php.includes("STORAGE_KEY = 'digitalisimo-apply-all'"), 'depende del editor y declara la clave propia del almacén');
ok(/if \( ! self::requires\( self::SYMBOLS \) \|\| ! self::editor_context\(\) \)/.test(php) && php.includes('is_admin()'), 'fuera de la administración y del editor, init() no engancha nada');
ok(!/[A-Za-z]+ ?Pro\b|Elementor/.test(php.split('strings(): array')[1].split('editor_context')[0]), 'los textos no nombran a otros constructores');

// ── «Pegar estilo» del núcleo, extraído de editor.js ─────────────────────────────────────────────────────────────
const core = readFileSync(CORE, 'utf8');
function region(path) {
  const start = core.indexOf(`//#region ${path}`);
  assert.ok(start > 0, `editor.js contiene la región ${path}`);
  return core.slice(start, core.indexOf('//#endregion', start));
}
function body(text, name) {
  const sig = `function ${name}(`;
  const at = text.indexOf(sig);
  assert.ok(at > 0, `editor.js contiene ${name}()`);
  const params = text.slice(at + sig.length, text.indexOf(')', at));
  let i = text.indexOf('{', at), depth = 0, quote = '';
  const from = i;
  for (; i < text.length; i++) {
    const ch = text[i];
    if (quote) { if ('\\' === ch) i++; else if (ch === quote) quote = ''; continue; }
    if ('"' === ch || "'" === ch) { quote = ch; continue; }
    if ('{' === ch) depth++;
    if ('}' === ch && 0 === --depth) break;
  }
  return { params, code: text.slice(from + 1, i) };
}
const PASTE = region('assets/dev/js/editor/document/elements/commands/paste-style.js');
const COPY = region('assets/dev/js/editor/document/elements/commands/copy.js');
// isStyleTransferControl() en editor.js:9758-9760 (V.1b): lo que no es de la pestaña Contenido cuenta como estilo.
const ist = body(core, 'isStyleTransferControl');
ok(ist.code.includes('control.style_transfer') && ist.code.includes('"content" !== control.tab'), 'isStyleTransferControl() del núcleo: style_transfer manda; si no, todo lo que no es Contenido es estilo');
const istLine = core.slice(0, core.indexOf('isStyleTransferControl: function isStyleTransferControl')).split('\n').length;
ok(istLine >= 9700 && istLine <= 9800, `isStyleTransferControl() sigue cerca de editor.js:9758 (línea ${istLine})`);
// El formato del almacén que usa el script es el de «Copiar».
const copy = body(COPY, 'apply');
ok(/type: "elementor",\s*siteurl: elementorCommon\.config\.urls\.rest,\s*elements: elementsData/.test(copy.code) && copy.code.includes('toJSON({ copyHtmlCache: true })'), '«Copiar» guarda { type: "elementor", siteurl, elements: [toJSON({ copyHtmlCache: true })] }');
ok(/storageKey\d? === void 0 \? "clipboard"/.test(body(PASTE, 'apply').code) && /storageKey\d? === void 0 \? "clipboard"/.test(body(PASTE, 'validateArgs').code), '«Pegar estilo» acepta storageKey (por defecto, el portapapeles)');
const coreSource = `(function () {
  var _slicedToArray = function (a) { return a; };
  var _typeof = function (x) { return typeof x; };
  return {
    requireContainer: function () {},
    requireArgumentType: function (name, type, args) { if (type !== typeof args[name] || null === args[name]) throw new Error(name + ' no es ' + type); },
    validateArgs: function validateArgs(${body(PASTE, 'validateArgs').params}) {${body(PASTE, 'validateArgs').code}},
    validateControls: function validateControls(${body(PASTE, 'validateControls').params}) {${body(PASTE, 'validateControls').code}},
    getHistory: function getHistory(${body(PASTE, 'getHistory').params}) {${body(PASTE, 'getHistory').code}},
    apply: function apply(${body(PASTE, 'apply').params}) {${body(PASTE, 'apply').code}},
    applyPasteStyleData: function applyPasteStyleData(${body(PASTE, 'applyPasteStyleData').params}) {${body(PASTE, 'applyPasteStyleData').code}},
    pasteStyle: function pasteStyle(${body(PASTE, 'pasteStyle').params}) {${body(PASTE, 'pasteStyle').code}}
  };
})()`;
const isStyleTransferControl = new Function(ist.params, ist.code);

// ── Dobles ───────────────────────────────────────────────────────────────────────────────────────────────────────
const clone = (x) => (undefined === x ? x : JSON.parse(JSON.stringify(x)));
// Controles de V.12 que no son de estilo: todos con style_transfer => false.
const F7B = {
  _digitalisimo_actions_on: { type: 'switcher', tab: 'advanced', style_transfer: false, default: '' },
  _digitalisimo_actions: { type: 'repeater', tab: 'advanced', style_transfer: false, default: [] },
  _digitalisimo_hidden_initial: { type: 'switcher', tab: 'advanced', style_transfer: false, default: '' },
  _digitalisimo_slot: { type: 'text', tab: 'advanced', style_transfer: false, default: '' },
  digitalisimo_saved_query: { type: 'select', tab: 'content', style_transfer: false, default: '' },
  digitalisimo_overrides: { type: 'repeater', tab: 'content', style_transfer: false, default: [] },
  digitalisimo_inline_tags: { type: 'switcher', tab: 'content', style_transfer: false, default: '' },
  digitalisimo_conditional: { type: 'switcher', tab: 'content', style_transfer: false, default: '' },
};
const CONTROLS = {
  heading: {
    title: { type: 'textarea', tab: 'content', default: 'Título' },
    header_size: { type: 'select', tab: 'content', default: 'h2' },
    link: { type: 'url', tab: 'content', default: { url: '' } },
    align: { type: 'choose', tab: 'content', default: '', selectors: { '{{WRAPPER}}': 'text-align: {{VALUE}};' } },
    title_color: { type: 'color', tab: 'style', default: '', selectors: { '{{WRAPPER}} .elementor-heading-title': 'color: {{VALUE}};' } },
    typography_font_size: { type: 'slider', tab: 'style', default: { unit: 'px', size: '', sizes: [] } },
    _css_classes: { type: 'text', tab: 'advanced', default: '' },
    _element_id: { type: 'text', tab: 'advanced', style_transfer: false, default: '' },
    digitalisimo_preset: { type: 'select', tab: 'advanced', style_transfer: true, default: '' },
    ...F7B,
  },
  button: { text: { type: 'text', tab: 'content', default: 'Botón' }, button_text_color: { type: 'color', tab: 'style', default: '' } },
  'e-heading': { title: { type: 'text', tab: 'content', default: '' } },
};
for (const set of Object.values(CONTROLS)) for (const [name, control] of Object.entries(set)) control.name = name;

class Settings {
  constructor(attrs, controls) {
    this.controls = controls;
    this.attributes = {};
    for (const [k, c] of Object.entries(controls)) this.attributes[k] = clone(c.default);
    Object.assign(this.attributes, clone(attrs));
  }
  get(k) { return this.attributes[k]; }
  set(values) { for (const [k, v] of Object.entries(values)) this.attributes[k] = clone(v); }
  toJSON() { return clone(this.attributes); }
}
const state = {};
function reset() {
  Object.assign(state, { runs: [], depth: 0, dialogs: [], toasts: [], filters: {}, listeners: {}, raw: '{}', during: null, history: [], design: true, fail: false, renders: {} });
  state.raw = JSON.stringify({ clipboard: { type: 'elementor', elements: [{ id: 'usuario' }] } });
}
function container(type, attrs = {}, extra = {}) {
  const id = extra.id || type + Math.random().toString(36).slice(2, 7);
  const settings = new Settings(attrs, CONTROLS[type] || {});
  const data = { id, elType: extra.elType || 'widget', widgetType: 'container' === extra.elType ? undefined : type, templateID: extra.templateID };
  const model = { get: (k) => ('settings' === k ? settings : data[k]), toJSON: (o) => ({ id, elType: data.elType, widgetType: data.widgetType, settings: settings.toJSON(), elements: [], htmlCache: o && o.copyHtmlCache ? '<p>x</p>' : undefined }) };
  const c = { id, model, settings, locked: false, kids: [], isLocked: () => c.locked, panel: { refresh() {} }, render() { state.renders[id] = (state.renders[id] || 0) + 1; } };
  c.view = { isStyleTransferControl, getContainer: () => c };
  c.children = list(c.kids);
  return c;
}
function list(items) {
  items.forEachRecursive = (fn) => items.forEach((c) => { fn(c); if (c.kids.length) c.children.forEachRecursive(fn); });
  return items;
}
const storage = {
  get(key) { const all = JSON.parse(state.raw); return key ? all[key] : all; },
  // Como el núcleo: un objeto en localStorage serializado con JSON; un valor undefined desaparece al guardar.
  set(key, value) { const all = JSON.parse(state.raw); all[key] = value; state.raw = JSON.stringify(all); },
};
const elementorCommon = {
  storage,
  config: { urls: { rest: 'https://sitio.test/wp-json/' } },
  dialogsManager: { createWidget(type, props) { const d = { type, props, shown: false, show() { d.shown = true; return d; } }; state.dialogs.push(d); return d; } },
};
let coreCmd;
const $e = {
  run(cmd, args) {
    state.runs.push({ cmd, depth: state.depth, args });
    if ('document/elements/paste-style' === cmd) {
      state.during = { key: args.storageKey, stored: clone(storage.get(args.storageKey)), clipboard: clone(storage.get('clipboard')) };
      if (state.fail) throw new Error('fallo simulado');
      coreCmd.validateArgs(args);
      state.history.push(coreCmd.getHistory(args));
      state.depth++;
      try { return coreCmd.apply(args); } finally { state.depth--; }
    }
    if ('document/elements/settings' === cmd) args.container.settings.set(args.settings);
    if ('document/globals/settings' === cmd) args.container.settings.set({ __globals__: { ...(args.container.settings.get('__globals__') || {}), ...args.settings } });
    return undefined;
  },
};
const WIDGETS = { heading: { title: 'Encabezado' }, button: { title: 'Botón' }, 'e-heading': { title: 'Encabezado v4', atomic_props_schema: {} } };
let doc;
function makeElementor(withHooks = true) {
  const e = {
    widgetsCache: WIDGETS,
    userCan: (cap) => 'design' === cap && state.design,
    helpers: { isAtomicWidget: (m) => !!(WIDGETS[m.get('widgetType')] || {}).atomic_props_schema },
    getElementData: (m) => WIDGETS[m.get('widgetType')],
    getControlView: () => ({ onPasteStyle: () => true }),
    notifications: { showToast: (o) => state.toasts.push(o.message) },
    documents: { getCurrent: () => doc },
  };
  if (withHooks) e.hooks = { addFilter: (name, fn) => { (state.filters[name] = state.filters[name] || []).push(fn); } };
  return e;
}
const T = {
  menu: 'Aplicar este estilo a todos los de este tipo (%d)', title: 'Aplicar el estilo a todos los «%s»',
  message: 'Se pegará todo el estilo de este elemento en los otros %1$d «%2$s» de esta página.', global1: 'Un widget global queda fuera.',
  globals: '%d widgets globales quedan fuera.', ok: 'Aplicar', cancel: 'Cancelar', done: 'Estilo aplicado a %d elementos.',
};
function load({ withHooks = true } = {}) {
  const elementor = makeElementor(withHooks);
  const window = { digitalisimoApplyAll: { key: 'digitalisimo-apply-all', i18n: T }, elementor, elementorCommon, $e, addEventListener: (event, fn) => { state.listeners[event] = fn; } };
  const context = vm.createContext({ window, elementor, elementorCommon, $e });
  coreCmd = vm.runInContext(coreSource, context, { filename: 'paste-style (núcleo)' });
  vm.runInContext(source, context, { filename: 'apply-all-editor.js' });
  return { window, elementor, api: window.digitalisimoApplyAllEditor };
}
const viewOf = (c) => ({ options: { model: c.model }, getContainer: () => c });
const groupsFor = (c) => state.filters['elements/widget/contextMenuGroups'].reduce((g, fn) => fn(g, viewOf(c)), [{ name: 'general', actions: [] }, { name: 'clipboard', actions: [] }, { name: 'delete', actions: [] }]);
const action = (c) => { const g = groupsFor(c).find((x) => 'digitalisimo-apply-all' === x.name); return g && g.actions[0]; };

// ── Arranque ─────────────────────────────────────────────────────────────────────────────────────────────────────
reset();
load({ withHooks: false });
ok(!state.filters['elements/widget/contextMenuGroups'] && 'function' === typeof state.listeners['elementor/init-components'], 'sin hooks todavía, espera a elementor/init-components');
reset();
const { api } = load();
ok(1 === (state.filters['elements/widget/contextMenuGroups'] || []).length && !state.filters['elements/context-menu/groups'], 'se engancha al menú de los widgets (el filtro genérico no recibe la vista: editor.js:9654)');

// Documento: origen, dos encabezados hermanos, uno anidado, un global, un botón, un encabezado v4.
const source1 = container('heading', {
  title: 'Origen', link: { url: 'https://origen.test' }, align: 'center', title_color: '#c00', typography_font_size: { unit: 'px', size: 40, sizes: [] },
  _css_classes: 'tarjeta', _element_id: 'ancla', digitalisimo_preset: 'marca',
  _digitalisimo_actions_on: 'yes', _digitalisimo_actions: [{ trigger: 'click', action: 'show', target: 'abc1234' }], _digitalisimo_hidden_initial: 'yes',
  _digitalisimo_slot: 'titulo', digitalisimo_saved_query: '55', digitalisimo_overrides: [{ slot: 'x', kind: 'text', value: 'y' }], digitalisimo_inline_tags: 'yes', digitalisimo_conditional: 'yes',
  __globals__: { title_color: 'globals/colors?id=primary' }, __dynamic__: { title: '[elementor-tag id="1" name="post-title" settings="%7B%7D"]' },
}, { id: 'origen' });
const h1 = container('heading', { title: 'Uno', title_color: '#00f' }, { id: 'h1' });
const h2 = container('heading', { title: 'Dos', _digitalisimo_slot: 'propio' }, { id: 'h2' });
const nested = container('heading', { title: 'Anidado' }, { id: 'anidado' });
const box = container('container', {}, { id: 'caja', elType: 'container' });
box.kids.push(nested);
const globalHeading = container('heading', { title: 'Global' }, { id: 'global', templateID: 77 });
const button = container('button', { text: 'Ir' }, { id: 'boton' });
const atomic = container('e-heading', { title: 'v4' }, { id: 'v4' });
doc = { container: { children: list([source1, h1, box, h2, globalHeading, button, atomic]) } };

// ── Menú contextual ──────────────────────────────────────────────────────────────────────────────────────────────
const groups = groupsFor(source1);
ok(groups.map((g) => g.name).join() === 'general,clipboard,digitalisimo-apply-all,delete', 'la entrada va delante de «Borrar»');
assert.deepEqual(groupsFor(box).map((g) => g.name), ['general', 'clipboard', 'delete'], 'un contenedor no recibe la entrada'); checks++;
assert.deepEqual(groupsFor(atomic).map((g) => g.name), ['general', 'clipboard', 'delete'], 'un widget v4 no recibe la entrada'); checks++;
const entry = action(source1);
ok('Aplicar este estilo a todos los de este tipo (3)' === entry.title(), 'el título cuenta los otros tres encabezados (también el anidado), sin el origen ni el global');
const found = api.targets(source1);
ok(found.list.map((c) => c.id).join() === 'h1,anidado,h2' && 1 === found.globals, 'destinos: mismo tipo del documento, sin el origen ni los globales (templateID)');
ok(true === entry.isVisible() && true === entry.isEnabled(), 'visible y activa con el permiso «design»');
state.design = false;
ok(false === entry.isVisible() && false === entry.isEnabled(), 'sin el permiso «design» no se ofrece (como el núcleo, editor.js:9703)');
assert.deepEqual(groupsFor(source1).map((g) => g.name), ['general', 'clipboard', 'delete'], 'sin el permiso «design» ni siquiera se añade el grupo'); checks++;
state.design = true;
source1.locked = true;
ok(false === entry.isEnabled(), 'un elemento bloqueado no la activa');
source1.locked = false;
ok(false === action(button).isEnabled() && 'Aplicar este estilo a todos los de este tipo (0)' === action(button).title(), 'sin otros widgets del mismo tipo, desactivada');

// ── Confirmación ─────────────────────────────────────────────────────────────────────────────────────────────────
entry.callback();
ok(0 === state.runs.length && 1 === state.dialogs.length && state.dialogs[0].shown && 'confirm' === state.dialogs[0].type, 'primero, un diálogo de confirmación; nada se ejecuta todavía');
const dialog = state.dialogs[0].props;
ok(dialog.message.includes('en los otros 3 «Encabezado»') && dialog.message.includes('todo el estilo') && dialog.message.includes('Un widget global queda fuera.'), 'la confirmación dice el recuento, que se pega todo el estilo y que el global queda fuera');
ok('Aplicar el estilo a todos los «Encabezado»' === dialog.headerMessage && 'Aplicar' === dialog.strings.confirm && 'Cancelar' === dialog.strings.cancel, 'cabecera y botones con los textos de PHP');

// ── Aplicar: un solo comando del núcleo ──────────────────────────────────────────────────────────────────────────
const sourceBefore = clone(source1.settings.attributes);
dialog.onConfirm();
const top = state.runs.filter((r) => 0 === r.depth);
ok(1 === top.length && 'document/elements/paste-style' === top[0].cmd, 'un solo $e.run de primer nivel: document/elements/paste-style');
ok(top[0].args.containers.map((c) => c.id).join() === 'h1,anidado,h2' && !('container' in top[0].args), 'los contenedores son los destinos, sin el origen ni el global');
ok('digitalisimo-apply-all' === top[0].args.storageKey && 'digitalisimo-apply-all' === state.during.key, 'con la clave propia del almacén');
const stored = state.during.stored;
ok(stored && 'elementor' === stored.type && 'https://sitio.test/wp-json/' === stored.siteurl && 1 === stored.elements.length && 'origen' === stored.elements[0].id && '<p>x</p>' === stored.elements[0].htmlCache, 'durante el comando, la clave guarda el origen en el formato de «Copiar»');
ok(!('digitalisimo-apply-all' in storage.get()), 'después, la clave propia se borra');
ok('usuario' === storage.get('clipboard').elements[0].id && 'usuario' === state.during.clipboard.elements[0].id, 'el portapapeles del usuario no cambia');
ok(1 === state.history.length && 'paste_style' === state.history[0].type && 3 === state.history[0].containers.length, 'un único registro de historial nativo (getHistory del núcleo): un solo «Deshacer»');
ok(state.toasts.includes('Estilo aplicado a 3 elementos.'), 'aviso final con el recuento');

// ── Qué copia «Pegar estilo» del núcleo ──────────────────────────────────────────────────────────────────────────
for (const target of [h1, nested, h2]) {
  const s = target.settings.attributes;
  ok('#c00' === s.title_color && 40 === s.typography_font_size.size && 'center' === s.align, `${target.id}: copia el estilo (pestaña Estilo y controles con selectors)`);
  ok('tarjeta' === s._css_classes && 'marca' === s.digitalisimo_preset, `${target.id}: copia lo de Avanzado sin style_transfer (como el núcleo) y el preset de F7a (style_transfer => true)`);
  ok('globals/colors?id=primary' === (s.__globals__ || {}).title_color, `${target.id}: copia el color global`);
  ok(!s.__dynamic__ || !s.__dynamic__.title, `${target.id}: no copia la etiqueta dinámica de un control de contenido`);
  ok(s.title !== 'Origen' && '' === s.link.url && '' === s._element_id, `${target.id}: no toca el contenido, el enlace ni el ID (style_transfer => false del núcleo)`);
  for (const name of Object.keys(F7B)) {
    ok(JSON.stringify(s[name]) === JSON.stringify(target === h2 && '_digitalisimo_slot' === name ? 'propio' : F7B[name].default), `${target.id}: «Pegar estilo» no copia ${name} (style_transfer => false)`);
  }
}
ok('Global' === globalHeading.settings.get('title') && '' === globalHeading.settings.get('title_color') && !state.renders.global, 'el widget global no se toca');
ok('' === button.settings.get('button_text_color') && !state.renders.boton, 'otro tipo de widget no se toca');
ok(JSON.stringify(sourceBefore) === JSON.stringify(source1.settings.attributes) && !state.renders.origen, 'el origen no cambia');

// Prueba de control: la misma pestaña Avanzado sin style_transfer => false sí se copiaría.
for (const [name, control] of Object.entries(F7B)) {
  const loose = { ...control };
  delete loose.style_transfer;
  ok(false === isStyleTransferControl(control) && ('advanced' === control.tab) === !!isStyleTransferControl(loose), `isStyleTransferControl(${name}): false con la marca; sin ella, ${'advanced' === control.tab ? 'contaría como estilo' : 'el contenido sin selectors no cuenta'}`);
}

// ── Si el comando falla, la clave se borra igual ─────────────────────────────────────────────────────────────────
reset();
load();
state.fail = true;
assert.throws(() => api.apply(source1), /fallo simulado/); checks++;
ok(!('digitalisimo-apply-all' in storage.get()) && 'usuario' === storage.get('clipboard').elements[0].id, 'tras un fallo, la clave propia se borra y el portapapeles sigue igual');
state.fail = false;
ok(0 === api.apply(button) && 0 === state.runs.filter((r) => 'document/elements/paste-style' === r.cmd && r.args.containers.includes(button)).length, 'sin destinos no se ejecuta nada');

// ── El control propio de F7b (frente I) declara style_transfer => false ──────────────────────────────────────────
if (existsSync(INLINE_PHP)) {
  const inline = readFileSync(INLINE_PHP, 'utf8');
  const block = inline.slice(inline.indexOf('self::CONTROL,'), inline.indexOf(')', inline.indexOf("'style_transfer'", inline.indexOf('self::CONTROL,'))) + 1);
  ok(inline.includes("const CONTROL = 'digitalisimo_inline_tags'") && /'style_transfer'\s*=>\s*false/.test(block) && !/'default'/.test(block), 'digitalisimo_inline_tags: style_transfer => false y sin default (V.12)');
} else {
  console.log('Aviso: inline-tags/class-module.php todavía no existe; la comprobación de su control se omite.');
}

console.log(`elements-f7b-apply-all: ${checks} comprobaciones en verde`);
