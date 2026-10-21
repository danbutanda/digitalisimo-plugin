// Cargador opt-in de Lottie y Prism (modules/digitalisimo-front/assets/lazy-libs.js, `lazy_libs` de F-front) en un
// entorno falso con `vm`: `window`, `document`, `IntersectionObserver` y `elementorFrontend.elementsHandler`. Sólo se
// envuelven `lottie.default` y `code-highlight.default`; con la librería presente, la llamada es inmediata; cada
// librería se inserta una sola vez; Prism llega en orden core → autoloader → normalize → plugins según las marcas de
// la página, con `Prism.manual` antes del core y un solo `highlightAll()`; una segunda instancia no recarga; sin
// `IntersectionObserver`, carga en el momento; un error de carga no llama al original y avisa en consola; el archivo
// compila y cabe en 1,5 KB.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const file = new URL('../digitalisimo-elements/modules/digitalisimo-front/assets/lazy-libs.js', import.meta.url);
const source = readFileSync(file, 'utf8');
assert.doesNotThrow(() => new vm.Script(source, { filename: 'lazy-libs.js' }), 'El cargador compila.');
assert.ok(statSync(file).size <= 1536, `El cargador cabe en 1,5 KB (${statSync(file).size} B).`);
assert.ok(!/https?:\/\//.test(source), 'El cargador no nombra ninguna URL: todas llegan de la configuración.');

const PRISM = ['prismjs_core', 'prismjs_loader', 'prismjs_normalize', 'prismjs_line_numbers', 'prismjs_line_highlight', 'prismjs_toolbar', 'prismjs_clipboard', 'prismjs_copy_to_clipboard'];
const base = 'https://sitio.test/wp-content/plugins/pro-elements/assets/lib';
const config = () => ({
  lottie: { src: `${base}/lottie/lottie.min.js?ver=5.6.6`, ver: '5.6.6' },
  prism: PRISM.map((handle) => ({ handle, src: `${base}/prism/${handle}.min.js?ver=1.23.0`, ver: handle === 'prismjs_clipboard' ? '2.0.0' : '1.23.0' })),
});
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
const handleOf = (src) => /\/prism\/(prismjs_[a-z_]+)\.min\.js/.exec(src)?.[1] || (src.includes('/lottie/') ? 'lottie' : src);

/** Entorno nuevo: cada corrida del script tiene su estado (librerías pedidas, Prism propio). */
function setup({ markers = [], observer = true, bodymovin = false, prism = null, withConfig = true, initFirst = false } = {}) {
  const listeners = {};
  const inserted = [];
  const observers = [];
  const warnings = [];
  const calls = [];
  const state = { highlightAll: 0, manualAtCore: undefined };
  const window = {
    addEventListener(name, fn) { (listeners[name] ||= []).push(fn); },
  };
  if (withConfig) window.digitalisimoLazyLibs = config();
  if (bodymovin) window.bodymovin = {};
  if (prism) window.Prism = prism;
  if (observer) {
    window.IntersectionObserver = class {
      constructor(callback, options) { this.callback = callback; this.options = options; this.targets = []; this.connected = true; observers.push(this); }
      observe(el) { this.targets.push(el); }
      disconnect() { this.connected = false; }
      fire(isIntersecting) { this.callback([{ isIntersecting, target: this.targets[0] }]); }
    };
  }
  const document = {
    body: { appendChild(script) { inserted.push(script); } },
    createElement(tag) { return { tag, src: '', async: true, onload: null, onerror: null }; },
    querySelector(selector) {
      assert.ok(selector.startsWith('[data-widget_type^=code-highlight] '), `Las marcas se buscan dentro de los widgets de código: ${selector}`);
      return markers.some((marker) => selector.endsWith(` ${marker}`)) ? {} : null;
    },
  };
  const original = function (Handler, options, flag) { calls.push({ Handler, options, flag, self: this }); };
  const handler = { addHandler: original };
  const frontend = { elementsHandler: handler, isEditMode: () => false };
  if (initFirst) window.elementorFrontend = frontend;
  /** Ejecuta un script insertado como lo haría el navegador y dispara su onload. */
  const execute = (script) => {
    const handle = handleOf(script.src);
    if (handle === 'lottie') window.bodymovin = { loadAnimation() {} };
    if (handle === 'prismjs_core') {
      state.manualAtCore = window.Prism && window.Prism.manual;
      window.Prism = { manual: state.manualAtCore, highlightAll() { state.highlightAll++; }, highlightAllUnder() {} };
    }
    script.onload();
  };
  const fail = (script) => script.onerror();
  vm.runInNewContext(source, { window, document, console: { warn: (...args) => warnings.push(args.join(' ')) }, setTimeout });
  const init = () => { window.elementorFrontend = frontend; for (const fn of listeners['elementor/frontend/init'] || []) fn(); };
  const element = (name) => ({ elementName: name, $element: { 0: { id: name } } });
  return { window, handler, original, calls, inserted, observers, warnings, listeners, state, init, execute, fail, element, handles: () => inserted.map((s) => handleOf(s.src)) };
}

// 1. Se engancha en el evento nativo `elementor/frontend/init` y sólo envuelve los dos nombres.
{
  const t = setup();
  assert.equal(t.handler.addHandler, t.original, 'Antes de `elementor/frontend/init` no se toca nada.');
  assert.equal(t.listeners['elementor/frontend/init']?.length, 1, 'Escucha el evento nativo en window.');
  t.init();
  assert.notEqual(t.handler.addHandler, t.original, 'En `elementor/frontend/init` envuelve addHandler.');
  const Handler = function () {};
  for (const name of ['nav-menu.default', 'lottie', 'code-highlight', 'container', 'global', 'video-playlist.default', 'constructor', '__proto__', 'toString', undefined]) {
    t.handler.addHandler(Handler, t.element(name), true);
  }
  assert.equal(t.calls.length, 10, 'Cualquier otro nombre llama al original en el momento.');
  assert.ok(t.calls.every((c) => c.Handler === Handler && c.flag === true && c.self === t.handler), 'El original recibe los mismos argumentos y su `this`.');
  assert.equal(t.inserted.length + t.observers.length, 0, 'Otros nombres no observan ni cargan nada.');
}

// 2. Si `elementsHandler` ya existe al cargar el script, envuelve en el momento.
{
  const t = setup({ initFirst: true });
  assert.notEqual(t.handler.addHandler, t.original, 'Con elementsHandler presente envuelve de inmediato.');
  assert.equal(t.listeners['elementor/frontend/init'], undefined, 'Y no espera al evento.');
}

// 3. Sin configuración no hace nada.
{
  const t = setup({ withConfig: false, initFirst: true });
  assert.equal(t.handler.addHandler, t.original, 'Sin window.digitalisimoLazyLibs no envuelve.');
  assert.equal(t.listeners['elementor/frontend/init'], undefined, 'Ni escucha el evento.');
}

// 4. Lottie con la librería presente: llamada inmediata.
{
  const t = setup({ bodymovin: true });
  t.init();
  t.handler.addHandler(function L() {}, t.element('lottie.default'), true);
  assert.equal(t.calls.length, 1, 'Con window.bodymovin presente llama al original en el momento.');
  assert.equal(t.inserted.length + t.observers.length, 0, 'Sin observar ni insertar nada.');
}

// 5. Lottie diferido: observa con margen de una pantalla, carga una vez y llama al original con los mismos argumentos.
{
  const t = setup();
  t.init();
  const Handler = function () {};
  const first = t.element('lottie.default');
  t.handler.addHandler(Handler, first, true);
  assert.equal(t.calls.length, 0, 'Sin la librería no llama al original todavía.');
  assert.equal(t.observers.length, 1, 'Observa el elemento.');
  assert.deepEqual(t.observers[0].targets, [first.$element[0]], 'Observa el elemento del widget.');
  assert.equal(t.observers[0].options.rootMargin, '100% 0px', 'Con una pantalla de margen.');
  assert.equal(t.inserted.length, 0, 'No pide la librería antes de acercarse.');
  t.observers[0].fire(false);
  assert.equal(t.inserted.length, 0, 'Un aviso sin intersección no carga.');
  const second = t.element('lottie.default');
  t.handler.addHandler(Handler, second, true);
  t.observers[0].fire(true);
  t.observers[1].fire(true);
  assert.equal(t.observers[0].connected, false, 'Deja de observar al acercarse.');
  assert.deepEqual(t.handles(), ['lottie'], 'Dos instancias a la vez piden la librería una sola vez.');
  assert.equal(t.inserted[0].src, config().lottie.src, 'Con la URL y la versión de la configuración.');
  assert.equal(t.inserted[0].async, false, 'Sin async: se ejecuta en orden de inserción.');
  await flush();
  assert.equal(t.calls.length, 0, 'Hasta que la librería carga no hay handler.');
  t.execute(t.inserted[0]);
  await flush();
  assert.equal(t.calls.length, 2, 'Al cargar, llama al original de cada instancia.');
  assert.ok(t.calls[0].Handler === Handler && t.calls[0].options === first && t.calls[0].flag === true && t.calls[0].self === t.handler, 'Con los mismos argumentos.');
  t.handler.addHandler(Handler, t.element('lottie.default'), true);
  assert.equal(t.calls.length, 3, 'Una instancia posterior, con la librería ya cargada, es inmediata.');
  assert.equal(t.inserted.length, 1, 'Y no recarga.');
}

// 6. Prism con todas las marcas: orden core → autoloader → normalize → plugins, `manual` antes del core y un
//    solo `highlightAll()`.
{
  const t = setup({ markers: ['pre.line-numbers', 'pre[data-line]:not([data-line=""])', '.copy-to-clipboard'] });
  t.init();
  const Handler = function () {};
  t.handler.addHandler(Handler, t.element('code-highlight.default'), true);
  assert.equal(t.inserted.length, 0, 'Prism tampoco se pide antes de acercarse.');
  t.observers[0].fire(true);
  assert.deepEqual(t.handles(), PRISM, 'Orden: core, autoloader, normalize, line-numbers, line-highlight, toolbar, clipboard y copy.');
  assert.ok(t.inserted.every((s) => s.async === false), 'Todos sin async: se ejecutan en orden.');
  assert.equal(t.window.Prism.manual, true, 'Prism.manual se fija antes de insertar el core.');
  for (const script of t.inserted.slice(0, 7)) t.execute(script);
  await flush();
  assert.equal(t.calls.length, 0, 'Falta un plugin: todavía no hay handler.');
  assert.equal(t.state.highlightAll, 0, 'Ni highlightAll.');
  t.execute(t.inserted[7]);
  await flush();
  assert.equal(t.state.manualAtCore, true, 'El core se ejecutó en modo manual: no resalta solo.');
  assert.equal(t.state.highlightAll, 1, 'highlightAll() una vez, como el arranque automático de antes.');
  assert.equal(t.calls.length, 1, 'Después, el handler original.');
  // Segunda instancia: no recarga ni vuelve a resaltar todo.
  t.handler.addHandler(Handler, t.element('code-highlight.default'), true);
  assert.equal(t.calls.length, 1, 'La segunda instancia también espera a acercarse.');
  t.observers[1].fire(true);
  await flush();
  assert.equal(t.inserted.length, 8, 'Una segunda instancia no recarga nada.');
  assert.equal(t.state.highlightAll, 1, 'Ni repite highlightAll().');
  assert.equal(t.calls.length, 2, 'Y llama a su original.');
}

// 7. Prism sin marcas: sólo core, autoloader y normalize. Un widget posterior con otra marca sólo añade su plugin.
{
  const markers = [];
  const t = setup({ markers });
  t.init();
  t.handler.addHandler(function () {}, t.element('code-highlight.default'), true);
  t.observers[0].fire(true);
  assert.deepEqual(t.handles(), PRISM.slice(0, 3), 'Sin marcas, sólo core, autoloader y normalize.');
  for (const script of t.inserted) t.execute(script);
  await flush();
  assert.equal(t.calls.length, 1, 'Y el handler.');
  markers.push('pre.line-numbers');
  t.handler.addHandler(function () {}, t.element('code-highlight.default'), true);
  t.observers[1].fire(true);
  assert.deepEqual(t.handles(), [...PRISM.slice(0, 3), 'prismjs_line_numbers'], 'Un widget posterior con números de línea sólo añade su plugin.');
  t.execute(t.inserted[3]);
  await flush();
  assert.equal(t.calls.length, 2, 'Y llama a su original.');
  assert.equal(t.state.highlightAll, 1, 'Sin otro highlightAll().');
}

// 8. Prism ya presente (de otro origen): llamada inmediata, sin cargar nada.
{
  const t = setup({ prism: { highlightAll() {}, highlightAllUnder() {} } });
  t.init();
  t.handler.addHandler(function () {}, t.element('code-highlight.default'), true);
  assert.equal(t.calls.length, 1, 'Con window.Prism presente llama al original en el momento.');
  assert.equal(t.inserted.length + t.observers.length, 0, 'Sin observar ni insertar nada.');
}

// 9. Sin IntersectionObserver (o sin elemento): carga en el momento.
{
  const t = setup({ observer: false });
  t.init();
  t.handler.addHandler(function () {}, t.element('lottie.default'), true);
  assert.deepEqual(t.handles(), ['lottie'], 'Sin IntersectionObserver pide la librería en el momento.');
  t.execute(t.inserted[0]);
  await flush();
  assert.equal(t.calls.length, 1, 'Y llama al original al cargar.');
  const u = setup();
  u.init();
  u.handler.addHandler(function () {}, { elementName: 'code-highlight.default', $element: {} }, true);
  assert.deepEqual(u.handles(), PRISM.slice(0, 3), 'Sin elemento que observar, también en el momento.');
  assert.equal(u.observers.length, 0, 'Sin observador.');
}

// 10. Error de carga: no llama al original y avisa en consola; no reintenta en cada instancia.
{
  const t = setup({ observer: false });
  t.init();
  t.handler.addHandler(function () {}, t.element('lottie.default'), true);
  t.fail(t.inserted[0]);
  await flush();
  assert.equal(t.calls.length, 0, 'Si la librería no carga, el handler no se llama.');
  assert.equal(t.warnings.length, 1, 'Y la consola lo avisa.');
  assert.ok(t.warnings[0].includes('DIGITALÍSIMO Elements: no se pudo cargar') && t.warnings[0].includes(config().lottie.src), `El aviso nombra la URL: ${t.warnings[0]}`);
  t.handler.addHandler(function () {}, t.element('lottie.default'), true);
  await flush();
  assert.equal(t.inserted.length, 1, 'Una instancia posterior no vuelve a pedirla.');
  assert.equal(t.calls.length, 0, 'Ni llama a su original.');
  const p = setup({ observer: false });
  p.init();
  p.handler.addHandler(function () {}, p.element('code-highlight.default'), true);
  p.fail(p.inserted[0]);
  for (const script of p.inserted.slice(1)) script.onload();
  await flush();
  assert.equal(p.calls.length, 0, 'Si el core de Prism falla, el handler no se llama.');
  assert.equal(p.warnings.length, 1, 'Y la consola lo avisa.');
}

console.log('DIGITALÍSIMO Elements: cargador diferido de Lottie y Prism validado (10 escenarios).');
