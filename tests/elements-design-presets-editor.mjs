// F7a · frente D: presets-editor.js en una máquina virtual con dobles de `elementor`, `$e`, `elementorCommon` y jQuery.
// - El archivo compila, es ES2017, pesa como mucho 8 KB, no tiene textos visibles propios y sólo se encola en el editor.
// - flatten(): un control es local o del preset, nunca mezclado (R-M10), con variantes responsivas, __globals__ y
//   __dynamic__; las fixtures compartidas con PHP (tests/sources/design/presets.json, frente C) cuando existen.
// - Vista previa por editor/style/styleText: sólo lo que el modelo no guarda, con el analizador del núcleo, su selector
//   (.elementor-{doc} .elementor-element.elementor-element-{id}) y sus puntos de corte, detrás del estilo del elemento.
// - Menú contextual sólo en widgets v3 y contenedores (los que tienen el control), con las cuatro acciones y sus permisos.
// - La extracción usa el criterio de «Pegar estilo»; «Usar sólo el preset» borra exactamente lo local que tapa el preset;
//   «Desvincular y copiar estilos» copia y vacía el vínculo; guardar y actualizar por AJAX; refresco de las opciones.
// - Pestaña «Presets de estilo» (Kit_Tab): renombrar y borrar con data-digi-preset-*.
import assert from 'node:assert/strict';
import { readFileSync, existsSync, statSync } from 'node:fs';
import vm from 'node:vm';

const FILE = new URL('../digitalisimo-elements/modules/digitalisimo-design/presets/assets/presets-editor.js', import.meta.url);
const EDITOR_PHP = new URL('../digitalisimo-elements/modules/digitalisimo-design/presets/class-editor.php', import.meta.url);
const FIXTURES = new URL('./sources/design/presets.json', import.meta.url);
const source = readFileSync(FILE, 'utf8');

// ── Archivo ──────────────────────────────────────────────────────────────────────────────────────────────────────
const bytes = statSync(FILE).size;
assert.ok(bytes <= 8192, `presets-editor.js pesa ${bytes} bytes; el presupuesto es 8 KB`);
assert.doesNotThrow(() => new vm.Script(source, { filename: 'presets-editor.js' }), 'presets-editor.js compila');
for (const [re, what] of [[/\?\./, 'encadenamiento opcional'], [/\?\?/, 'fusión de nulos'], [/(\|\||&&)=/, 'asignación lógica'], [/\bclass\s+\w/, 'clases'], [/#\w+\s*[=(]/, 'campos privados'], [/\bimport\s*\(/, 'import dinámico'], [/\{\s*\.\.\./, 'propagación en objetos'], [/\basync\b|\bawait\b/, 'async']]) {
  assert.ok(!re.test(source), `presets-editor.js debe ser ES2017 (sin ${what})`);
}
const literals = [...source.matchAll(/'([^'\\]*(?:\\.[^'\\]*)*)'|`([^`]*)`/g)].map((m) => m[1] || m[2] || '');
assert.deepEqual(literals.filter((t) => /Elementor|[áéíóúñ¿¡]/.test(t)), [], 'presets-editor.js no tiene textos visibles propios: todos vienen de i18n (PHP)');
const php = readFileSync(EDITOR_PHP, 'utf8');
assert.ok(php.includes("add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) )") && !/wp_enqueue_scripts|elementor\/frontend\//.test(php), 'el script sólo se encola en el editor');
assert.ok(php.includes("array( 'elementor-editor' )"), 'el script depende del editor');

// ── Dobles ───────────────────────────────────────────────────────────────────────────────────────────────────────
const clone = (x) => (undefined === x ? x : JSON.parse(JSON.stringify(x)));
// Lo que sale de la máquina virtual tiene otro Object.prototype: se compara como JSON.
const plain = clone;
const MEDIA = { tablet: '@media(max-width:1024px)', mobile: '@media(max-width:767px)' };
const T = {
  none: 'Sin preset', save: 'Guardar estilo como preset…', update: 'Actualizar el preset con este estilo', only: 'Usar sólo el preset',
  unlink: 'Desvincular y copiar estilos', name: 'Nombre del preset', ok: 'Guardar', cancel: 'Cancelar', remove: '¿Borrar este preset?',
  saved: 'Preset guardado.', left: 'Siguen como valores locales:', covered1: '1 valor local tapa el preset.',
  covered: '%d valores locales tapan el preset.', error: 'con error', failed: 'No se pudo completar la acción.',
};
const size = (n) => ({ unit: 'px', size: n, sizes: [] });
const EMPTY = { unit: 'px', size: '', sizes: [] };
const typo = (extra) => ({ groupType: 'typography', groupPrefix: 'typography_', tab: 'style', condition: { typography_typography: ['custom'] }, ...extra });
const TITLE = '{{WRAPPER}} .elementor-heading-title';
const CONTROLS = {
  heading: {
    title: { type: 'textarea', tab: 'content', default: 'Añade tu título' },
    header_size: { type: 'select', tab: 'content', default: 'h2' },
    link: { type: 'url', tab: 'content', style_transfer: false, default: { url: '' } },
    align: { type: 'choose', tab: 'style', label: 'Alineación', default: '', selectors: { '{{WRAPPER}}': 'text-align: {{VALUE}};' } },
    title_color: { type: 'color', tab: 'style', label: 'Color del texto', default: '', global: { active: true }, selectors: { [TITLE]: 'color: {{VALUE}};' } },
    typography_typography: { type: 'popover_toggle', tab: 'style', groupType: 'typography', groupPrefix: 'typography_', default: '' },
    typography_font_family: typo({ type: 'font', default: '', selectors: { [TITLE]: 'font-family: "{{VALUE}}";' } }),
    typography_font_size: typo({ type: 'slider', default: EMPTY, responsive: { max: 'desktop' }, parent: null, selectors: { [TITLE]: 'font-size: {{SIZE}}{{UNIT}};' } }),
    typography_font_size_tablet: typo({ type: 'slider', default: EMPTY, responsive: { max: 'tablet' }, parent: 'typography_font_size', selectors: { [TITLE]: 'font-size: {{SIZE}}{{UNIT}};' } }),
    typography_font_size_mobile: typo({ type: 'slider', default: EMPTY, responsive: { max: 'mobile' }, parent: 'typography_font_size_tablet', selectors: { [TITLE]: 'font-size: {{SIZE}}{{UNIT}};' } }),
    _css_classes: { type: 'text', tab: 'advanced', label: 'Clases CSS', default: '' },
    _animation: { type: 'animation', tab: 'advanced', prefix_class: 'animated ', default: '' },
    digitalisimo_preset: { type: 'select', tab: 'advanced', style_transfer: true, default: '', render_type: 'none' },
  },
  container: {
    background_background: { type: 'choose', tab: 'style', groupType: 'background', groupPrefix: 'background_', default: '' },
    background_color: { type: 'color', tab: 'style', groupType: 'background', groupPrefix: 'background_', default: '', condition: { background_background: ['classic', 'gradient'] }, selectors: { '{{WRAPPER}}': 'background-color: {{VALUE}};' } },
    digitalisimo_preset: { type: 'select', tab: 'advanced', style_transfer: true, default: '' },
  },
  // Un elemento v4: no tiene el control.
  'e-heading': { title: { type: 'text', tab: 'content', default: '' } },
};
for (const set of Object.values(CONTROLS)) for (const [name, control] of Object.entries(set)) control.name = name;

class Settings {
  constructor(attrs, controls) {
    this.controls = controls;
    this.attributes = {};
    for (const [k, c] of Object.entries(controls)) this.attributes[k] = clone(c.default);
    Object.assign(this.attributes, clone(attrs));
    this.changed = {};
  }
  get(k) { return this.attributes[k]; }
  set(values) {
    this.changed = {};
    for (const [k, v] of Object.entries(values)) {
      if (JSON.stringify(this.attributes[k]) !== JSON.stringify(v)) this.changed[k] = v;
      this.attributes[k] = clone(v);
    }
  }
  hasChanged(k) { return undefined === k ? Object.keys(this.changed).length > 0 : k in this.changed; }
  toJSON(options = {}) {
    const out = clone(this.attributes);
    if ((options.remove || []).includes('default')) {
      for (const [k, c] of Object.entries(this.controls)) if (k in out && JSON.stringify(out[k]) === JSON.stringify(c.default)) delete out[k];
    }
    for (const k of ['__globals__', '__dynamic__']) if (out[k] && !Object.keys(out[k]).length) delete out[k];
    return out;
  }
  // Como el núcleo: un global de grupo cuenta como «custom» al resolver las condiciones.
  getActiveControls(controls, values) {
    const v = { ...values };
    for (const [k, c] of Object.entries(controls)) if ((values.__globals__ || {})[k] && c.groupType) v[k] = 'custom';
    const out = {};
    for (const [k, c] of Object.entries(controls)) {
      if (!c.condition || Object.entries(c.condition).every(([n, want]) => (Array.isArray(want) ? want.includes(v[n]) : want === v[n]))) out[k] = c;
    }
    return out;
  }
}
// El analizador del núcleo, reducido a lo que la prueba mira: selector, punto de corte y valor (o global).
class Parser {
  constructor(options = {}) {
    this.options = options;
    this.rules = [];
    Parser.made.push(this);
    const rules = this.rules;
    this.stylesheet = { toString: () => rules.map((r) => (r.media ? `${r.media}{${r.selector}{${r.css}}}` : `${r.selector}{${r.css}}`)).join('') };
  }
  addStyleRules(list, values, controls, placeholders, replacements) {
    this.call = { list: Array.from(list, (c) => c.name), values: clone(values), placeholders: Array.from(placeholders), replacements: Array.from(replacements) };
    const globals = this.options.context.model.get('settings').get('__globals__') || {};
    for (const c of list) {
      if (!c.selectors) continue;
      const key = globals[c.groupType ? c.groupPrefix + c.groupType : c.name];
      let value = values[c.name];
      if (key) {
        const id = key.split('id=')[1], field = c.name.replace(c.groupPrefix, '').replace(/_(tablet|mobile)$/, '').replace('_', '-');
        value = c.groupType ? `var( --e-global-${c.groupType}-${id}-${field} )` : `var( --e-global-color-${id} )`;
      } else if (undefined === value || '' === value || (value && 'object' === typeof value && '' === value.size)) continue;
      for (const [selector, declaration] of Object.entries(c.selectors)) {
        let sel = selector;
        placeholders.forEach((p, i) => { sel = sel.replace(p, replacements[i]); });
        const css = (key ? declaration.replace(/:[^;]+/, `: ${value}`) : declaration.replace('{{VALUE}}', value).replace('{{SIZE}}{{UNIT}}', value.size + value.unit)).replace(/;\s*$/, '').replace(/:\s+/, ':');
        const max = c.responsive && c.responsive.max;
        this.rules.push({ media: max && 'desktop' !== max ? MEDIA[max] : '', selector: sel, css });
      }
    }
  }
}
Parser.made = [];

function container(type, attrs = {}, id = 'el' + Math.random().toString(36).slice(2, 7)) {
  const atomic = 'e-heading' === type;
  const data = { id, elType: 'container' === type ? 'container' : 'widget', widgetType: 'container' === type ? undefined : type };
  const settings = new Settings(attrs, CONTROLS[type]);
  const model = { get: (k) => ('settings' === k ? settings : data[k]) };
  const c = { id, model, settings, locked: false, renders: 0, isLocked: () => c.locked };
  c.view = { getContainer: () => c, getEditModel: () => model, renderStyles: () => { c.renders++; }, controlsCSSParser: atomic ? undefined : new Parser() };
  return c;
}

const state = { runs: [], requests: [], dialogs: [], toasts: [], clicks: {}, page: null, docs: [], filters: {}, channel: {}, listeners: {} };
const reset = () => { state.runs = []; state.requests = []; state.dialogs = []; state.toasts = []; Parser.made = []; };
const $e = {
  run(cmd, args) {
    state.runs.push({ cmd, id: args.container.id, settings: clone(args.settings), external: args.options && args.options.external });
    const s = args.container.settings;
    if ('document/elements/settings' === cmd) s.set(args.settings);
    if ('document/globals/settings' === cmd) s.set({ __globals__: { ...(s.get('__globals__') || {}), ...args.settings } });
    if ('document/dynamic/disable' === cmd) { const d = { ...(s.get('__dynamic__') || {}) }; for (const k of Object.keys(args.settings)) delete d[k]; s.set({ __dynamic__: d }); }
  },
};
const elementorCommon = {
  ajax: { addRequest(action, options) { state.requests.push({ action, data: clone(options.data), options }); } },
  dialogsManager: { createWidget(type, props) { const d = { type, props, shown: false, show() { d.shown = true; return d; } }; state.dialogs.push(d); return d; } },
};
const doc = { nodeName: '#document' };
function $(x) {
  if ('string' === typeof x && '<' === x[0]) { const input = { value: '', val(v) { if (undefined === v) return input.value; input.value = v; return input; } }; return input; }
  if (x === doc) return { on: (event, selector, fn) => { state.clicks[`${event} ${selector}`] = fn; } };
  return x;
}
$.trim = (s) => String(s).trim();
function makeElementor(withHooks = true) {
  const e = {
    config: { controls: { color: { global: { active: true } }, slider: {}, font: {}, popover_toggle: {}, choose: {}, select: {}, textarea: {}, text: {}, url: {}, animation: {} }, document: { id: 10 } },
    channels: { editor: { on(event, fn) { state.channel[event] = fn; return this; } } },
    notifications: { showToast: (o) => state.toasts.push(o.message) },
    getPanelView: () => ({ getCurrentPageView: () => state.page }),
    documents: { getCurrent: () => ({ container: { children: { forEachRecursive: (fn) => state.docs.forEach(fn) } } }) },
  };
  if (withHooks) e.hooks = { addFilter: (name, fn) => { (state.filters[name] = state.filters[name] || []).push(fn); } };
  return e;
}
const PRESETS = {
  tarjeta: { type: 'heading', label: 'Tarjeta', error: false, settings: { title_color: '#cc0000', typography_typography: 'custom', typography_font_size: size(32), typography_font_size_tablet: size(24) } },
  global: { type: 'heading', label: 'Global', error: false, settings: { __globals__: { typography_typography: 'globals/typography?id=primary', title_color: 'globals/colors?id=accent' } } },
  caja: { type: 'container', label: 'Caja', error: false, settings: { background_background: 'classic', background_color: '#112233' } },
  roto: { type: 'heading', label: 'Roto', error: true, settings: {} },
};
function load({ withHooks = true, presets = PRESETS, canManage = true, canDesign = true } = {}) {
  state.filters = {}; state.channel = {}; state.clicks = {}; state.listeners = {};
  const elementor = makeElementor(withHooks);
  const cfg = { control: 'digitalisimo_preset', presets: clone(presets), canManage, canDesign, i18n: T };
  const window = { digitalisimoPresets: cfg, jQuery: $, document: doc, elementor, addEventListener: (event, fn) => { state.listeners[event] = fn; } };
  vm.runInNewContext(source, { window, elementor, $e, elementorCommon }, { filename: 'presets-editor.js' });
  return { window, elementor, cfg, api: window.digitalisimoPresetsEditor };
}
const filter = (name, ...args) => state.filters[name].reduce((value, fn) => fn(value, ...args.slice(1)), args[0]);

// ── Arranque ─────────────────────────────────────────────────────────────────────────────────────────────────────
{
  const late = load({ withHooks: false });
  assert.deepEqual(Object.keys(state.filters), [], 'sin elementor.hooks no registra nada todavía');
  assert.equal(typeof state.listeners['elementor/init-components'], 'function', 'espera a elementor/init-components');
  late.elementor.hooks = { addFilter: (name, fn) => { (state.filters[name] = state.filters[name] || []).push(fn); } };
  state.listeners['elementor/init-components']();
  assert.deepEqual(Object.keys(state.filters).sort(), ['editor/style/styleText', 'elements/container/contextMenuGroups', 'elements/widget/contextMenuGroups'], 'al iniciarse el editor registra la vista previa y los dos menús');
  assert.deepEqual(Object.keys(state.channel).sort(), ['change', 'section:activated'], 'escucha la apertura de secciones y los cambios del panel');
  assert.equal(typeof state.clicks['click [data-digi-preset-action]'], 'function', 'escucha los botones de la pestaña «Presets de estilo»');
  assert.equal(load().api && state.listeners['elementor/init-components'], undefined, 'con el editor ya iniciado arranca en el acto');
}

// ── flatten (R-M10) ──────────────────────────────────────────────────────────────────────────────────────────────
const { api } = load();
const H = CONTROLS.heading;
const flat = (preset, local, controls = H) => JSON.parse(JSON.stringify(api.flatten(preset, local, controls)));
const P1 = PRESETS.tarjeta.settings;
assert.deepEqual(flat(P1, {}), { values: P1, globals: {} }, 'sin valores locales, el preset aporta todo');
assert.deepEqual(flat(P1, { typography_font_size_mobile: size(12) }), { values: { title_color: '#cc0000', typography_typography: 'custom' }, globals: {} }, 'una variante local del tamaño quita las tres del preset (R-M10)');
assert.deepEqual(flat(P1, { title_color: '#000000' }).values.title_color, undefined, 'un valor local gana');
assert.deepEqual(flat(P1, { __globals__: { title_color: 'globals/colors?id=primary' } }).values.title_color, undefined, 'un global local también es local');
assert.deepEqual(flat(P1, { __globals__: { title_color: '' } }).values.title_color, '#cc0000', 'un global vacío no es local');
assert.deepEqual(flat(P1, { __dynamic__: { title_color: '[elementor-tag id="1"]' } }).values.title_color, undefined, 'una etiqueta dinámica local también es local');
assert.deepEqual(flat(PRESETS.global.settings, { typography_typography: 'custom', typography_font_size: size(20) }), { values: {}, globals: { title_color: 'globals/colors?id=accent' } }, 'el global de tipografía no entra si el grupo es local');
assert.deepEqual(flat(P1, { typography_font_size_mobile: size(12) }, {}), flat(P1, { typography_font_size_mobile: size(12) }), 'sin la definición de los controles, la raíz sale del sufijo de dispositivo (como Store::root)');
assert.deepEqual(flat(P1, { __fa4_migrated: { x: true }, title: 'Hola' }), { values: P1, globals: {} }, 'el contenido local no tapa el estilo del preset');

// Fixtures compartidas con PHP (frente C). `settings`: los ajustes validados del preset; sin ellos, el `fill` de PHP
// debe ser estable: aplicado sobre el mismo elemento, flatten() devuelve exactamente lo mismo.
let shared = 'pendientes (tests/sources/design/presets.json aún no existe)';
if (existsSync(FIXTURES)) {
  const fixtures = JSON.parse(readFileSync(FIXTURES, 'utf8'));
  assert.ok(Array.isArray(fixtures.cases) && fixtures.cases.length >= 8, 'las fixtures compartidas traen los casos de V.2.2');
  let full = 0;
  for (const c of fixtures.cases) {
    const fill = { values: c.fill.values || {}, globals: c.fill.globals || {} };
    const preset = c.settings || { ...fill.values, ...(Object.keys(fill.globals).length ? { __globals__: fill.globals } : {}) };
    if (c.settings) full++;
    assert.deepEqual(flat(preset, Array.isArray(c.local) ? {} : c.local, {}), JSON.parse(JSON.stringify(fill)), `fixture ${c.name}: flatten() coincide con Compiler::fill()`);
    const base = fixtures.selector.replace('{document}', fixtures.document).replace('{id}', c.element.id);
    for (const rule of Object.keys(c.css || {})) {
      const [media, selector] = rule.includes('|') ? rule.split('|') : ['', rule];
      assert.ok(selector.includes(base), `fixture ${c.name}: la regla ${rule} usa el selector del núcleo`);
      assert.ok('' === media || Object.values(fixtures.breakpoints).includes(media), `fixture ${c.name}: ${media} es un punto de corte activo`);
    }
  }
  shared = `${fixtures.cases.length} casos (${full} con los ajustes validados)`;
}

// ── Vista previa ─────────────────────────────────────────────────────────────────────────────────────────────────
{
  load();
  const heading = container('heading', { digitalisimo_preset: 'tarjeta' }, 'h1');
  const css = filter('editor/style/styleText', '/* del elemento */', heading.view);
  const parser = Parser.made[Parser.made.length - 1];
  const sel = '.elementor-10 .elementor-element.elementor-element-h1 .elementor-heading-title';
  assert.ok(css.startsWith('/* del elemento */'), 'las reglas del preset van detrás de las del elemento');
  assert.equal(css, `/* del elemento */${sel}{color:#cc0000}${sel}{font-size:32px}${MEDIA.tablet}{${sel}{font-size:24px}}`, 'reglas del preset con el selector y el punto de corte del núcleo');
  assert.deepEqual(parser.call.replacements, ['h1', '.elementor-10 .elementor-element.elementor-element-h1'], 'mismo {{ID}} y {{WRAPPER}} que renderStyles() del núcleo');
  assert.deepEqual(parser.call.placeholders.map(String), ['/{{ID}}/g', '/{{WRAPPER}}/g'], 'mismos marcadores que el núcleo');
  assert.deepEqual(parser.call.list.sort(), ['title_color', 'typography_font_size', 'typography_font_size_tablet', 'typography_typography'], 'sólo los controles que aporta el preset');
  // El módulo base del núcleo (elementorModules.Module) enlaza cada método en la instancia, también `constructor`:
  // `new instancia.constructor()` no crea un ControlsCSSParser (design.sh DS1: la vista previa no pintaba el preset).
  // La clase se toma del prototipo.
  const bound = container('heading', { digitalisimo_preset: 'tarjeta' }, 'h1');
  bound.view.controlsCSSParser.constructor = function () { return {}; };
  assert.equal(filter('editor/style/styleText', '', bound.view), `${sel}{color:#cc0000}${sel}{font-size:32px}${MEDIA.tablet}{${sel}{font-size:24px}}`, 'con un constructor propio enlazado en la instancia (como el núcleo), la vista previa usa la clase del prototipo');
  assert.equal(parser.options.settingsModel, heading.settings, 'los valores dinámicos los resuelve el modelo del elemento');

  const mixed = container('heading', { digitalisimo_preset: 'tarjeta', typography_typography: 'custom', typography_font_size_mobile: size(12) }, 'h2');
  const css2 = filter('editor/style/styleText', '', mixed.view);
  assert.ok(!css2.includes('font-size'), 'un tamaño local en móvil quita todos los tamaños del preset (R-M10)');
  assert.ok(css2.includes('color:#cc0000'), 'el color del preset sigue');

  const global = container('heading', { digitalisimo_preset: 'global' }, 'h3');
  const css3 = filter('editor/style/styleText', '', global.view);
  const p3 = Parser.made[Parser.made.length - 1];
  assert.ok(p3.call.list.includes('typography_font_family') && p3.call.list.includes('typography_font_size_tablet'), 'un global de tipografía incluye los campos de su grupo');
  assert.ok(css3.includes('color:var( --e-global-color-accent )') && css3.includes('font-family:var( --e-global-typography-primary-font-family )'), 'los globales del preset se resuelven como en el núcleo: ' + css3);
  assert.deepEqual(plain(p3.options.context.model.get('settings').get('__globals__')), PRESETS.global.settings.__globals__, 'el analizador ve los globales del preset sin pisar los locales');

  const cases = [
    ['sin vínculo', container('heading', {}, 'h4')],
    ['otro tipo', container('container', { digitalisimo_preset: 'tarjeta' }, 'c1')],
    ['preset con error', container('heading', { digitalisimo_preset: 'roto' }, 'h5')],
    ['preset inexistente', container('heading', { digitalisimo_preset: 'nada' }, 'h6')],
    ['todo local', container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000', typography_typography: 'custom', typography_font_size: size(9) }, 'h7')],
    ['v4', container('e-heading', {}, 'v4')],
  ];
  const before = Parser.made.length;
  for (const [name, el] of cases) {
    assert.equal(filter('editor/style/styleText', 'X', el.view), 'X', `${name}: la vista previa no añade nada`);
  }
  assert.equal(Parser.made.length - before, 0, 'sin nada que aportar no se crea ningún analizador');
  assert.equal(filter('editor/style/styleText', 'Y', { getEditModel() { throw new Error('roto'); } }), 'Y', 'un error en la vista previa nunca rompe el estilo del elemento');
  assert.equal(filter('editor/style/styleText', 'Z', undefined), 'Z', 'sin contexto (CSS del kit), nada');
}

// ── Menú contextual ──────────────────────────────────────────────────────────────────────────────────────────────
const groups = () => [{ name: 'general', actions: [] }, { name: 'clipboard', actions: [] }, { name: 'delete', actions: [] }];
const actions = (el, type = 'widget') => {
  const out = filter(`elements/${type}/contextMenuGroups`, groups(), el.view);
  return out.find((g) => 'digitalisimo-preset' === g.name);
};
const enabled = (group) => Object.fromEntries(group.actions.map((a) => [a.name.replace('digitalisimo-preset-', ''), a.isEnabled()]));
{
  load();
  const out = filter('elements/widget/contextMenuGroups', groups(), container('heading').view);
  assert.deepEqual(out.map((g) => g.name), ['general', 'clipboard', 'digitalisimo-preset', 'delete'], 'el grupo va antes de «Borrar»');
  const group = out[2];
  assert.deepEqual(plain(group.actions.map((a) => a.name)), ['digitalisimo-preset-save', 'digitalisimo-preset-update', 'digitalisimo-preset-only', 'digitalisimo-preset-unlink'], 'las cuatro acciones de 3.1.4');
  assert.deepEqual(plain(group.actions.map((a) => a.title)), [T.save, T.update, T.only, T.unlink], 'con sus textos de i18n');
  const atomic = groups();
  assert.equal(filter('elements/widget/contextMenuGroups', atomic, container('e-heading').view), atomic, 'un elemento sin el control (v4) no cambia');
  assert.ok(actions(container('container'), 'container'), 'también en contenedores');
  assert.deepEqual(enabled(actions(container('heading'))), { save: true, update: false, only: false, unlink: false }, 'sin vínculo sólo se puede guardar');
  assert.deepEqual(enabled(actions(container('heading', { digitalisimo_preset: 'tarjeta' }))), { save: true, update: true, only: false, unlink: true }, 'vinculado sin valores locales que lo tapen');
  assert.deepEqual(enabled(actions(container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000' }))), { save: true, update: true, only: true, unlink: true }, 'vinculado con un valor local que lo tapa');
  assert.deepEqual(enabled(actions(container('heading', { digitalisimo_preset: 'roto' }))), { save: true, update: false, only: false, unlink: true }, 'con un preset con error se puede desvincular');
  const locked = container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000' });
  locked.locked = true;
  assert.deepEqual(enabled(actions(locked)), { save: false, update: false, only: false, unlink: false }, 'un elemento bloqueado no ofrece nada');
  load({ canManage: false });
  assert.deepEqual(enabled(actions(container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000' }))), { save: false, update: false, only: true, unlink: true }, 'sin manage_options no se guardan presets');
  load({ canDesign: false });
  assert.deepEqual(enabled(actions(container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000' }))), { save: true, update: true, only: false, unlink: false }, 'con «sólo contenido» no se vincula ni desvincula');
  // El núcleo aplica el filtro en behaviors(), al construir la vista: todavía no hay `this.model` y getContainer()
  // lanza un TypeError (design.sh DS1–DS4: el editor no cargaba). El grupo se decide con options.model y el
  // contenedor se pide al abrir el menú.
  load();
  const el = container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000' });
  const early = { ready: false, getEditModel: () => undefined, options: { model: el.model }, getContainer: () => { if (!early.ready) throw new TypeError("Cannot read properties of undefined (reading 'get')"); return el; } };
  const built = filter('elements/widget/contextMenuGroups', groups(), early).find((g) => 'digitalisimo-preset' === g.name);
  assert.ok(built, 'al construir la vista, sin contenedor todavía, el grupo se añade sin pedirlo');
  early.ready = true;
  assert.deepEqual(enabled(built), { save: true, update: true, only: true, unlink: true }, 'y sus acciones piden el contenedor al abrir el menú');
  const plainEarly = { getEditModel: () => undefined, options: { model: container('e-heading').model }, getContainer: () => { throw new TypeError('sin contenedor'); } };
  const v4 = groups();
  assert.equal(filter('elements/widget/contextMenuGroups', v4, plainEarly), v4, 'un elemento sin el control tampoco pide el contenedor');
}

// ── Acciones ─────────────────────────────────────────────────────────────────────────────────────────────────────
const act = (el, name) => actions(el).actions.find((a) => a.name === `digitalisimo-preset-${name}`).callback();
{
  // Extracción con el criterio de «Pegar estilo»: lo visible (preset + local), sin contenido ni el vínculo.
  const loaded = load();
  const el = container('heading', { digitalisimo_preset: 'tarjeta', title: 'Hola', header_size: 'h1', link: { url: 'https://a.test' }, align: 'center', title_color: '#000000', _css_classes: 'mia', _animation: 'fadeIn', __globals__: { typography_font_family: 'globals/typography?id=x' }, __dynamic__: { align: '[elementor-tag]' } });
  assert.deepEqual(JSON.parse(JSON.stringify(loaded.api.extract(el))), {
    typography_typography: 'custom', typography_font_size: size(32), typography_font_size_tablet: size(24),
    align: 'center', title_color: '#000000', _css_classes: 'mia', _animation: 'fadeIn',
    __globals__: { typography_font_family: 'globals/typography?id=x' },
  }, 'extrae el estilo visible con el criterio de «Pegar estilo»; el servidor decide qué queda (V.2.2)');

  // «Usar sólo el preset»: borra exactamente lo local que tapa el preset, en todas sus variantes.
  reset();
  const only = container('heading', { digitalisimo_preset: 'tarjeta', align: 'center', title_color: '#000000', typography_font_size_mobile: size(12), __globals__: { typography_typography: 'globals/typography?id=x' }, __dynamic__: { title_color: '[elementor-tag]' } }, 'o1');
  act(only, 'only');
  assert.deepEqual(state.runs, [
    { cmd: 'document/elements/settings', id: 'o1', settings: { title_color: '', typography_font_size_mobile: EMPTY }, external: true },
    { cmd: 'document/globals/settings', id: 'o1', settings: { typography_typography: '' }, external: true },
    { cmd: 'document/dynamic/disable', id: 'o1', settings: { title_color: '' }, external: true },
  ], '«Usar sólo el preset» borra exactamente las claves del preset que el elemento guarda');
  assert.equal(only.settings.get('align'), 'center', 'lo local que el preset no define se queda');
  assert.deepEqual(plain(loaded.api.covered(only)), { values: [], globals: [], dynamic: [] }, 'después no queda nada local que tape el preset');

  // «Desvincular y copiar estilos»: copia lo que aportaba el preset y vacía el vínculo.
  reset();
  const unlink = container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000' }, 'u1');
  act(unlink, 'unlink');
  assert.deepEqual(state.runs, [{ cmd: 'document/elements/settings', id: 'u1', settings: { typography_typography: 'custom', typography_font_size: size(32), typography_font_size_tablet: size(24), digitalisimo_preset: '' }, external: true }], 'copia el estilo del preset como local y vacía el vínculo');
  assert.equal(unlink.settings.get('title_color'), '#000000', 'lo local no cambia');
  reset();
  act(container('heading', { digitalisimo_preset: 'global' }, 'u2'), 'unlink');
  assert.deepEqual(state.runs.map((r) => [r.cmd, r.settings]), [['document/elements/settings', { digitalisimo_preset: '' }], ['document/globals/settings', PRESETS.global.settings.__globals__]], 'también copia los globales del preset');

  // «Guardar estilo como preset…»: nombre, AJAX, vínculo, valores locales cubiertos y aviso de lo que queda local.
  reset();
  const saved = container('heading', { title_color: '#123456', align: 'center', _css_classes: 'mia' }, 's1');
  const other = container('heading', { digitalisimo_preset: 'mi-tarjeta' }, 's2');
  state.docs = [saved, other, container('heading', {}, 's3')];
  act(saved, 'save');
  assert.equal(state.dialogs.length, 1, 'pide el nombre');
  const dialog = state.dialogs[0];
  assert.equal(dialog.type, 'confirm');
  assert.equal(dialog.props.headerMessage, T.name);
  assert.ok(dialog.shown && 'function' === typeof dialog.props.message.val, 'con un campo de texto');
  dialog.props.message.val('  ');
  dialog.props.onConfirm();
  assert.equal(state.requests.length, 0, 'un nombre vacío no envía nada');
  dialog.props.message.val(' Mi tarjeta ');
  dialog.props.onConfirm();
  assert.equal(state.requests.length, 1);
  const request = state.requests[0];
  assert.equal(request.action, 'digitalisimo_preset_save');
  assert.deepEqual(request.data, { slug: '', type: 'heading', label: 'Mi tarjeta', settings: { title_color: '#123456', align: 'center', _css_classes: 'mia' } }, 'envía el estilo extraído y el nombre');
  const presets = { ...clone(PRESETS), 'mi-tarjeta': { type: 'heading', label: 'Mi tarjeta', error: false, settings: { title_color: '#123456', align: 'center' } } };
  request.options.success({ ok: true, slug: 'mi-tarjeta', presets, local: ['_css_classes', '__dynamic__'] });
  assert.equal(loaded.cfg.presets['mi-tarjeta'].label, 'Mi tarjeta', 'la respuesta actualiza los presets del editor');
  assert.deepEqual(state.runs.map((r) => [r.cmd, r.settings]), [['document/elements/settings', { digitalisimo_preset: 'mi-tarjeta' }], ['document/elements/settings', { align: '', title_color: '' }]], 'vincula el elemento y deja de guardar como local lo que ya da el preset');
  assert.equal(saved.settings.get('_css_classes'), 'mia', 'lo que el servidor no guardó sigue local');
  assert.deepEqual(state.toasts, [`${T.saved} ${T.left} Clases CSS`], 'avisa de lo que sigue local');
  assert.deepEqual([saved.renders, other.renders, state.docs[2].renders], [1, 1, 0], 'vuelve a pintar los elementos con ese preset');

  // «Actualizar el preset con este estilo»: sin diálogo, con el slug y el nombre del preset.
  reset();
  act(container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000' }), 'update');
  assert.equal(state.dialogs.length, 0, 'actualizar no pide nombre');
  assert.deepEqual(state.requests[0].data, { slug: 'tarjeta', type: 'heading', label: 'Tarjeta', settings: { typography_typography: 'custom', typography_font_size: size(32), typography_font_size_tablet: size(24), title_color: '#000000' } }, 'actualiza con lo que el elemento muestra');

  // Errores del servidor.
  reset();
  act(container('heading', { digitalisimo_preset: 'tarjeta' }), 'update');
  state.requests[0].options.error('No tienes permiso para gestionar los presets de estilo.');
  state.requests[0].options.error({ status: 0 });
  assert.deepEqual(state.toasts, ['No tienes permiso para gestionar los presets de estilo.', T.failed], 'el mensaje del servidor, o uno genérico');
  assert.equal(state.runs.length, 0, 'un error no cambia el elemento');
}

// ── Panel: opciones por tipo y aviso ─────────────────────────────────────────────────────────────────────────────
{
  load();
  const model = (attrs) => {
    const m = { attributes: { ...attrs }, changed: {}, renders: 0 };
    m.get = (k) => m.attributes[k];
    m.set = (values) => { m.changed = {}; for (const [k, v] of Object.entries(values)) { if (JSON.stringify(m.attributes[k]) !== JSON.stringify(v)) m.changed[k] = v; m.attributes[k] = v; } };
    m.hasChanged = () => Object.keys(m.changed).length > 0;
    return m;
  };
  const page = (el, rendered = true) => {
    const cm = model({ name: 'digitalisimo_preset', options: { '': 'Sin preset', tarjeta: 'Tarjeta', global: 'Global', caja: 'Caja', roto: 'Roto' }, description: 'Aplica el estilo guardado en el preset.' });
    const view = { renders: 0, render() { view.renders++; } };
    return { cm, view, getControlModel: (name) => ('digitalisimo_preset' === name ? cm : undefined), getOption: (k) => ('editedElementView' === k ? el.view : undefined), getControlViewByModel: () => (rendered ? view : undefined) };
  };
  const el = container('heading', { digitalisimo_preset: 'tarjeta', title_color: '#000000', typography_font_size_mobile: size(12) });
  const p = page(el);
  state.channel['section:activated']('digitalisimo_section_preset', p);
  assert.deepEqual(plain(p.cm.get('options')), { '': 'Sin preset', tarjeta: 'Tarjeta', global: 'Global', roto: 'Roto · con error' }, 'sólo los presets del tipo, con los de error marcados (V-I2)');
  assert.equal(p.cm.get('description'), 'Aplica el estilo guardado en el preset. 2 valores locales tapan el preset.', 'aviso «N valores locales tapan el preset» detrás de la ayuda del control');
  assert.equal(p.view.renders, 1, 'vuelve a pintar el control');
  state.channel['section:activated']('digitalisimo_section_preset', p);
  assert.equal(p.view.renders, 1, 'sin cambios no lo vuelve a pintar');
  el.settings.set({ typography_font_size_mobile: EMPTY });
  state.page = p;
  state.channel.change({ model: { get: () => 'typography_font_size_mobile' } }, el.view);
  assert.equal(p.cm.get('description'), 'Aplica el estilo guardado en el preset. 1 valor local tapa el preset.', 'el aviso sigue los cambios del panel');
  assert.equal(el.renders, 0, 'cambiar otro control no repinta el estilo');
  state.channel.change({ model: { get: () => 'digitalisimo_preset' } }, el.view);
  assert.equal(el.renders, 1, 'cambiar el vínculo repinta el estilo (el control es render_type none)');
  const box = page(container('container', { digitalisimo_preset: 'caja' }));
  state.channel['section:activated']('digitalisimo_section_preset', box);
  assert.deepEqual(plain(box.cm.get('options')), { '': 'Sin preset', caja: 'Caja' }, 'un contenedor sólo ve presets de contenedor');
  assert.equal(box.cm.get('description'), 'Aplica el estilo guardado en el preset.', 'sin valores locales, sólo la ayuda');
  const hidden = page(container('heading'), false);
  state.channel['section:activated']('otra', hidden);
  assert.ok(hidden.cm.get('options').tarjeta && !hidden.cm.get('options').caja, 'con la sección cerrada también filtra (la vista se pinta después)');
  state.channel['section:activated']('otra', { getControlModel: () => undefined });
  state.channel['section:activated']('otra', undefined);
}

// ── Pestaña «Presets de estilo» ──────────────────────────────────────────────────────────────────────────────────
{
  const loaded = load();
  reset();
  const label = { value: 'Tarjeta', text(v) { label.value = v; } };
  const row = { removed: false, find: (sel) => ('[data-digi-preset-label]' === sel ? label : null), remove() { row.removed = true; } };
  const button = (action) => ({ data: (k) => ({ digiPreset: 'tarjeta', digiPresetAction: action })[k], closest: (sel) => ('[data-digi-preset-row]' === sel ? row : null) });
  const click = state.clicks['click [data-digi-preset-action]'];
  click({ currentTarget: button('rename') });
  assert.equal(state.dialogs[0].props.message.val(), 'Tarjeta', 'renombrar propone el nombre actual');
  state.dialogs[0].props.message.val('Tarjeta nueva');
  state.dialogs[0].props.onConfirm();
  assert.deepEqual([state.requests[0].action, state.requests[0].data], ['digitalisimo_preset_rename', { slug: 'tarjeta', label: 'Tarjeta nueva' }]);
  state.requests[0].options.success({ ok: true, slug: 'tarjeta', presets: { ...clone(PRESETS), tarjeta: { ...PRESETS.tarjeta, label: 'Tarjeta nueva' } } });
  assert.equal(label.value, 'Tarjeta nueva', 'la fila muestra el nombre nuevo');
  assert.equal(loaded.cfg.presets.tarjeta.label, 'Tarjeta nueva', 'y el editor también');
  click({ currentTarget: button('delete') });
  assert.equal(state.dialogs[1].props.headerMessage, T.remove, 'borrar pide confirmación');
  assert.equal(state.dialogs[1].props.message, '', 'sin campo de texto');
  state.dialogs[1].props.onConfirm();
  assert.deepEqual([state.requests[1].action, state.requests[1].data], ['digitalisimo_preset_delete', { slug: 'tarjeta' }]);
  const without = clone(PRESETS);
  delete without.tarjeta;
  state.requests[1].options.success({ ok: true, slug: 'tarjeta', presets: without });
  assert.ok(row.removed && !loaded.cfg.presets.tarjeta, 'la fila y el preset desaparecen');
  state.requests[1].options.error('Este preset se usa en algún documento: desvincúlalo de cada elemento antes de borrarlo. Inicio');
  assert.equal(state.toasts[state.toasts.length - 1].startsWith('Este preset se usa'), true, 'borrar con usos muestra los documentos (D8)');
}

console.log(`F7a · presets-editor.js (${bytes} bytes): vista previa con el analizador y el selector del núcleo, R-M10, menú contextual con sus permisos, «Pegar estilo», «Usar sólo el preset», «Desvincular y copiar estilos», guardar y actualizar por AJAX, opciones por tipo con su aviso y pestaña de presets validados; fixtures compartidas: ${shared}.`);
