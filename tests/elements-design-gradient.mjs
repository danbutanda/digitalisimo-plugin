// design-editor.js de F7a (frentes F y G): vista previa de degradados y de SVG de Medios en el editor.
//
// - Las fixtures de tests/sources/design/gradients.json dan exactamente lo mismo que Backgrounds\Gradient::rules()
//   (tests/elements-design-gradient.php), con la configuración que imprime PHP.
// - Un solo filtro `editor/style/styleText`, registrado una vez; ≤ 8 KB; nada se ejecuta fuera del editor.
// - La regla usa el selector del núcleo (0,3,0) y los @media de los puntos de corte activos (V-I13), y sus
//   declaraciones sobreviven al `addRules` real del editor del núcleo, que parte por `;` (V-B3).
// - SVG de Medios: misma codificación que scripts/design-library.mjs, lista de rechazo, tope de 8 KB, sólo el mismo
//   origen y repintado al llegar el archivo.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { encode, sources } from '../scripts/design-library.mjs';

const file = new URL('../digitalisimo-elements/modules/digitalisimo-design/backgrounds/assets/design-editor.js', import.meta.url);
const code = readFileSync(file, 'utf8');
const fixtures = JSON.parse(readFileSync(new URL('./sources/design/gradients.json', import.meta.url), 'utf8'));

assert.ok(Buffer.byteLength(code) <= 8192, `design-editor.js pesa ${Buffer.byteLength(code)} B; el techo es 8 KB`);
assert.equal((code.match(/addFilter\(/g) || []).length, 1, 'Un solo addFilter en el archivo');
assert.equal((code.match(/addFilter\('editor\/style\/styleText'/g) || []).length, 1, 'El único filtro es editor/style/styleText');
assert.doesNotMatch(code, /elementorFrontend\.hooks|jQuery\(|\$\(|document\.write|innerHTML|eval\(|new Function/, 'Sin código del front ni HTML dinámico');

// ── Arranque: espera a `elementor/init` y registra el filtro una sola vez ─────────────────────────────────────────
const listeners = {};
const filters = [];
const toasts = [];
const fetched = [];
let respond = null;
const win = {
  digitalisimoDesignConfig: fixtures.config,
  location: { href: 'https://sitio.test/wp-admin/post.php?post=42&action=elementor', origin: 'https://sitio.test' },
  addEventListener: (name, fn) => { (listeners[name] ||= []).push(fn); },
  fetch: (url) => { fetched.push(url); return new Promise((resolve) => { respond = (text, ok = true) => resolve({ ok, text: async () => text }); }); },
};
// En modo estricto: una variable sin declarar lanzaría aquí en vez de escapar a window.
const sandbox = { window: win, URL, Object, Array, Number, String, Math, RegExp, isFinite, encodeURIComponent };
vm.runInNewContext(`'use strict';${code}`, sandbox);
assert.deepEqual(Object.keys(sandbox).sort(), ['Array', 'Math', 'Number', 'Object', 'RegExp', 'String', 'URL', 'encodeURIComponent', 'isFinite', 'window'], 'No deja globales');
assert.ok(win.digitalisimoDesign, 'Expone su API para las pruebas');
assert.equal(filters.length, 0, 'Sin el editor no registra nada');
assert.equal(listeners['elementor/init']?.length, 1, 'Espera a elementor/init');
win.elementorFrontend = { config: { responsive: { activeBreakpoints: { mobile: { value: 767, direction: 'max' }, tablet: { value: 1024, direction: 'max' } } } } };
win.elementor = {
  hooks: { addFilter: (name, fn) => filters.push([name, fn]) },
  config: { document: { id: 42 } },
  notifications: { showToast: (args) => toasts.push(args.message) },
};
listeners['elementor/init'][0]();
listeners['elementor/init'][0]();
assert.equal(filters.length, 1, 'El filtro se registra una vez');
assert.equal(filters[0][0], 'editor/style/styleText');
const styleText = filters[0][1];
const api = win.digitalisimoDesign;

// ── Fixtures compartidas con PHP ────────────────────────────────────────────────────────────────────────────────
for (const c of fixtures.cases) {
  assert.deepEqual(JSON.parse(JSON.stringify(api.rules(c.settings, c.prefix, c.devices))), c.expected, `Degradado «${c.name}»`);
}
assert.deepEqual(Array.from(api.devices({ mobile: { value: 767, direction: 'max' }, tablet: { value: 1024, direction: 'max' }, laptop: { value: 1366, direction: 'max' }, widescreen: { value: 2400, direction: 'min' } })),
  ['desktop', 'widescreen', 'laptop', 'tablet', 'mobile'], 'Dispositivos de mayor a menor, como el núcleo');

// ── addRules real del editor del núcleo (parte por `;` y por `:`) ───────────────────────────────────────────────
const editor = readFileSync(new URL('../digitalisimo-elements/elementor/assets/js/editor.js', import.meta.url), 'utf8');
const start = editor.indexOf('this.addRules = function(selector, styleRules, query) {');
const end = editor.indexOf('this.getRules = function', start);
assert.ok(start > 0 && end > start, 'No se encontró addRules en el editor del núcleo: revisar tras sync-elementor-core.sh');
const parser = () => {
  const state = { rules: {} };
  const shim = {
    _: { isEmpty: (value) => !value || !Object.keys(value).length },
    $: { each: (list, fn) => { for (let i = 0; i < list.length; i++) fn.call(String(list[i]), i, list[i]); }, extend: (target, source) => Object.assign(target, source) },
  };
  const make = new Function('_', '$', 'state', `var rules = state.rules, self = {}; var queryToHash = function(q){ return Object.keys(q).map(function(k){ return k + '_' + q[k]; }).join('-'); }; var addQueryHash = function(h){ rules[h] = {}; }; ${editor.slice(start, end)} return this.addRules;`);
  const addRules = make.call({}, shim._, shim.$, state);
  return { addRules, state };
};

// ── Filtro: selector del núcleo, @media activos y valores que sobreviven a addRules ────────────────────────────
const gradientSelector = '{{WRAPPER}}:not(.elementor-motion-effects-element-type-background), {{WRAPPER}} > .elementor-motion-effects-container > .elementor-motion-effects-layer';
function view(type, settings, controls) {
  const copies = { n: 0 };
  const model = { get: (key) => ({ elType: type, id: 'abc1234', settings: { attributes: settings, toJSON: () => { copies.n++; return JSON.parse(JSON.stringify(settings)); }, controls } })[key] };
  return { renders: 0, copies, getEditModel: () => model, renderStyles() { this.renders++; } };
}
const responsive = fixtures.cases.find((c) => c.name.startsWith('responsivo'));
const container = view('container', responsive.settings, { background_gradient_angle: { selectors: { [gradientSelector]: 'x' } } });
const out = styleText('/*núcleo*/', container);
const wrap = '.elementor-42 .elementor-element.elementor-element-abc1234';
const sel = gradientSelector.split('{{WRAPPER}}').join(wrap);
assert.equal(out,
  `/*núcleo*/${sel}{background-color:transparent;background-image:${responsive.expected.desktop}}`
  + `@media(max-width:1024px){${sel}{background-color:transparent;background-image:${responsive.expected.tablet}}}`
  + `@media(max-width:767px){${sel}{background-color:transparent;background-image:${responsive.expected.mobile}}}`,
  'Va detrás de la hoja del núcleo, con su selector (0,3,0) y sus @media');
for (const c of fixtures.cases) {
  for (const value of Object.values(c.expected)) {
    const { addRules, state } = parser();
    addRules('x', `background-color:transparent;background-image:${value}`);
    assert.equal(state.rules.all.x['background-image'], value, `addRules del editor conserva «${c.name}»`);
  }
}
assert.equal(styleText('a', view('container', fixtures.cases[0].settings, { background_gradient_angle: { selectors: { [gradientSelector]: 'x' } } })), 'a', 'Sin controles nuevos no añade nada');
const plain = view('container', { background_background: 'classic', background_color: '#ffffff' }, {});
assert.equal(styleText('a', plain), 'a');
assert.equal(plain.copies.n, 0, 'Un elemento sin nada de F7a no copia sus ajustes en cada repintado');
assert.equal(styleText('a', view('widget', { _background_background: 'gradient', _background_digi_repeat: 'yes' }, {})), 'a', 'Sin el control del núcleo no inventa selectores');
assert.equal(styleText('a', null), 'a', 'Sin contexto devuelve la hoja tal cual');
assert.equal(styleText('a', { getEditModel: () => { throw new Error('x'); } }), 'a', 'Un error nunca rompe la hoja del núcleo');
const widget = styleText('', view('widget', { _background_background: 'gradient', _background_color: '#ff0000', _background_color_b: '#0000ff', _background_digi_repeat: 'yes' }, { _background_gradient_angle: { selectors: { '{{WRAPPER}} > .elementor-widget-container': 'x' } } }));
assert.equal(widget, `${wrap} > .elementor-widget-container{background-color:transparent;background-image:repeating-linear-gradient(180deg, #ff0000 0%, #0000ff 100%)}`, 'Widget con el selector de su pila');

// ── SVG de Medios ───────────────────────────────────────────────────────────────────────────────────────────────
const src = sources();
for (const kind of ['masks', 'patterns']) {
  for (const [slug, svg] of Object.entries(src[kind])) assert.equal(api.svgUri(svg), encode(svg), `Misma codificación que la biblioteca: ${kind}/${slug}`);
}
const inline = '<?xml version="1.0"?>\n<!-- comentario -->\n<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">\n  <rect style="fill:#000;opacity:.5" width="10" height="10"/>\n  <use xlink:href="#a"/>\n</svg>';
const uri = api.svgUri(inline);
assert.ok(uri.startsWith('url("data:image/svg+xml,%3Csvg%20') && uri.endsWith('")'), uri);
assert.doesNotMatch(uri.slice(5, -2), /[;#{}'"\s]/, 'Codificado en porcentaje: sin ; # llaves, comillas ni espacios');
const { addRules, state } = parser();
addRules('x', `-webkit-mask-image:${uri};mask-image:${uri}`);
assert.equal(state.rules.all.x['mask-image'], uri, 'addRules del editor conserva el data: (V-B3)');
for (const hostile of [
  '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
  '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
  '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://otro.test/x.png"/></svg>',
  '<svg xmlns="http://www.w3.org/2000/svg"><a xlink:href="javascript:alert(1)"/></svg>',
  '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject/></svg>',
  '<svg xmlns="http://www.w3.org/2000/svg"><style>@import "x.css";</style></svg>',
  '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(https://otro.test/#p)"/></svg>',
  '<!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg"></svg>',
  `<svg xmlns="http://www.w3.org/2000/svg"><path d="${'M0 0'.repeat(2100)}"/></svg>`,
  'no es un svg',
  42,
]) assert.equal(api.svgUri(hostile), null, `Rechazado: ${String(hostile).slice(0, 60)}`);

const maskSettings = { digitalisimo_mask: 'yes', digitalisimo_mask_shape: 'media', digitalisimo_mask_media: { id: 7, url: 'https://sitio.test/wp-content/uploads/forma.svg' } };
const maskControls = { digitalisimo_mask_shape: { selectors: { '{{WRAPPER}}': '-webkit-mask-image: {{VALUE}}; mask-image: {{VALUE}};' } } };
const section = view('section', maskSettings, maskControls);
assert.equal(styleText('', section), '', 'Mientras llega el archivo no hay regla');
assert.deepEqual(fetched, ['https://sitio.test/wp-content/uploads/forma.svg'], 'Pide el SVG al mismo origen');
assert.equal(styleText('', section), '', 'No lo pide dos veces');
assert.equal(fetched.length, 1);
respond('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><circle cx=".5" cy=".5" r=".5"/></svg>');
await new Promise((resolve) => setTimeout(resolve, 0));
assert.equal(section.renders, 1, 'Al llegar el archivo, repinta los estilos del elemento');
const masked = styleText('', section);
assert.ok(masked.startsWith(`${wrap}{-webkit-mask-image:url("data:image/svg+xml,`) && masked.includes(';mask-image:url("data:image/svg+xml,'), masked);

const pattern = view('container', { digitalisimo_pattern: 'yes', digitalisimo_pattern_tile: 'media', digitalisimo_pattern_media: { url: '/wp-content/uploads/hostil.svg' } }, { digitalisimo_pattern_tile: { selectors: { '{{WRAPPER}}::after': 'x' } } });
assert.equal(styleText('', pattern), '');
respond('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');
await new Promise((resolve) => setTimeout(resolve, 0));
assert.equal(styleText('', pattern), '', 'Un SVG hostil no deja regla');
assert.deepEqual(toasts, [fixtures.config.svgError], 'Y el editor avisa una vez');

const foreign = view('section', { ...maskSettings, digitalisimo_mask_media: { url: 'https://otro.test/forma.svg' } }, maskControls);
assert.equal(styleText('', foreign), '', 'Un SVG de otro origen no se pide');
assert.equal(fetched.length, 2, 'Sólo se pidieron los dos del mismo origen');
assert.equal(styleText('', view('section', { ...maskSettings, digitalisimo_mask: '' }, maskControls)), '', 'Con la máscara apagada no hay regla');
assert.equal(styleText('', view('section', { ...maskSettings, digitalisimo_mask_shape: 'circulo' }, maskControls)), '', 'Las formas de la biblioteca las pinta el diccionario del núcleo');

console.log(`DIGITALÍSIMO Elements: design-editor.js (${Buffer.byteLength(code)} B) con ${fixtures.cases.length} fixtures, addRules del editor y SVG de Medios validado.`);
