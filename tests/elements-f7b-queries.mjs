// F7b, frente C: `modules/digitalisimo-f7b/assets/js/queries-editor.js` (V.4; C2-17, C2-18, C2-26) en un editor falso
// con `vm`: resumen de sólo lectura con «Editar en Consultas (N usos)» y el aviso del contexto «padre»; la lista se pide
// una vez; «Desvincular» sólo lee por AJAX, confirma (avisando si cambian los resultados) y copia los ajustes con un
// único `$e.run( 'document/elements/settings' )`; los textos se escapan; el archivo cabe en 8 KB y no usa jQuery.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const file = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/assets/js/queries-editor.js', import.meta.url);
const source = readFileSync(file, 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

ok((() => { new vm.Script(source, { filename: 'queries-editor.js' }); return true; })(), 'El script compila.');
ok(statSync(file).size <= 8192, `El script cabe en 8 KB (${statSync(file).size} B).`);
ok(!/jQuery|\$\(/.test(source), 'El script no usa jQuery.');
ok(!/https?:\/\//.test(source), 'El script no nombra ninguna URL.');
ok(!/innerHTML\s*=/.test(source), 'Nunca asigna innerHTML.');

class Node {
  constructor(tag) { this.tagName = String(tag).toUpperCase(); this.children = []; this.text = ''; this.className = ''; }
  set textContent(value) { this.text = String(value); this.children = []; }
  get textContent() { return this.text + this.children.map((child) => child.textContent).join(''); }
  get innerHTML() { return this.text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  appendChild(child) { this.children.push(child); return child; }
  find(predicate) {
    for (const child of this.children) {
      if (predicate(child)) return child;
      const deep = child.find(predicate);
      if (deep) return deep;
    }
    return null;
  }
}

function settingsModel(values) {
  const handlers = {};
  return {
    values,
    get(key) { return this.values[key]; },
    on(event, fn) { (handlers[event] = handlers[event] || []).push(fn); },
    off(event, fn) { handlers[event] = (handlers[event] || []).filter((h) => h !== fn); },
    set(key, value) { this.values[key] = value; (handlers['change:' + key] || []).forEach((fn) => fn()); },
    count(event) { return (handlers[event] || []).length; },
  };
}

const config = {
  control: 'digitalisimo_saved_query',
  event: 'digitalisimo:queries:unlink',
  list: 'digitalisimo_f7b_queries',
  read: 'digitalisimo_f7b_query_settings',
  prefixes: { posts: 'posts_', portfolio: 'posts_', 'loop-grid': 'post_query_', 'loop-carousel': 'post_query_' },
  fields: ['post_type', 'include', 'include_term_ids', 'include_authors', 'exclude', 'exclude_ids', 'exclude_term_ids', 'exclude_authors', 'avoid_duplicates', 'offset', 'select_date', 'date_before', 'date_after', 'orderby', 'order'],
  i18n: {
    loading: 'Cargando la consulta…',
    missing: 'Esta consulta ya no existe o no está publicada: el bucle queda vacío.',
    edit: 'Editar en Consultas (%d usos)',
    uses: '%d usos',
    parent: 'Con contexto «padre» no hay entrada de contexto en los filtros por AJAX.',
    title: 'Desvincular la consulta',
    confirm: 'Se copiarán los ajustes <b>de la consulta</b> a este widget.',
    changes: 'Los resultados cambiarán.',
    ok: 'Desvincular',
    cancel: 'Cancelar',
    error: 'No se pudo leer la consulta.',
  },
};

function setup({ booted = true } = {}) {
  const requests = [];
  const actions = {};
  const channel = {};
  const runs = [];
  const dialogs = [];
  const toasts = [];
  const timers = [];
  const listeners = {};
  const elementor = {
    hooks: { addAction(name, fn) { actions[name] = fn; } },
    channels: { editor: { on(event, fn) { (channel[event] = channel[event] || []).push(fn); } } },
    notifications: { showToast(args) { toasts.push(args); } },
  };
  const window = {
    digitalisimoSavedQueries: config,
    elementor: booted ? elementor : {},
    elementorCommon: {
      ajax: { addRequest(action, options) { requests.push({ action, options }); } },
      dialogsManager: { createWidget(type, options) { const dialog = { type, options, shown: false, show() { this.shown = true; } }; dialogs.push(dialog); return dialog; } },
    },
    $e: { run(command, args) { runs.push({ command, args }); } },
    confirm() { return true; },
    addEventListener(event, fn) { listeners[event] = fn; },
  };
  const document = { createElement: (tag) => new Node(tag) };
  const context = vm.createContext({ window, document, setTimeout: (fn) => timers.push(fn), console, Object, Array, String, parseInt });
  vm.runInContext(source, context);
  const flush = () => { while (timers.length) timers.shift()(); };
  return { window, elementor, requests, actions, channel, runs, dialogs, toasts, flush, listeners };
}

function panelFor() {
  const root = new Node('div');
  const box = new Node('div');
  root.querySelector = (selector) => (selector === '[data-digi-sq-summary]' ? box : null);
  return { panel: { $el: [root] }, box };
}

const list = {
  queries: {
    5: { title: 'Relacionadas', summary: 'Entradas · Noticias · 6 · contexto actual · mismos términos de «Categorías»', context: 'current', relation: 'terms', valid: true, uses: 3, drafts: 1, edit: 'admin.php?page=digitalisimo-elements-queries&action=edit&id=5' },
    7: { title: 'Padre', summary: 'Entradas · 4 · contexto padre · mismo autor', context: 'parent', relation: 'author', valid: true, uses: 0, drafts: 0, edit: '' },
  },
  screen: 'admin.php?page=digitalisimo-elements-queries',
  can_manage: true,
};

// 1. Arranque: con el editor listo engancha el panel y el evento; si no, espera a `elementor/init`.
{
  const env = setup();
  ok(typeof env.actions['panel/open_editor/widget'] === 'function', 'Engancha panel/open_editor/widget.');
  ok(env.channel['digitalisimo:queries:unlink'] && env.channel['digitalisimo:queries:unlink'].length === 1, 'Escucha el evento del botón «Desvincular».');
  const late = setup({ booted: false });
  ok(typeof late.listeners['elementor/init'] === 'function' && !late.actions['panel/open_editor/widget'], 'Sin editor todavía, espera a elementor/init.');
}

// 2. Resumen: una sola petición de la lista, texto de sólo lectura y enlace «Editar en Consultas (N usos)».
{
  const env = setup();
  const { panel, box } = panelFor();
  const settings = settingsModel({ digitalisimo_saved_query: '5' });
  const model = { get: (key) => (key === 'widgetType' ? 'posts' : settings) };
  env.actions['panel/open_editor/widget'](panel, model);
  env.flush();
  ok(env.requests.length === 1 && env.requests[0].action === 'digitalisimo_f7b_queries', 'Pide la lista con digitalisimo_f7b_queries.');
  ok(box.textContent === 'Cargando la consulta…', 'Mientras llega, «Cargando…».');
  env.requests[0].options.success(list);
  ok(box.children.length === 2 && box.children[0].textContent === list.queries[5].summary, 'Primero, el resumen de sólo lectura.');
  const link = box.find((node) => node.tagName === 'A');
  ok(link && link.textContent === 'Editar en Consultas (3 usos)' && link.href === list.queries[5].edit && link.target === '_blank' && link.rel === 'noopener', 'Después, «Editar en Consultas (N usos)» a la pantalla.');
  settings.set('digitalisimo_saved_query', '7');
  env.flush();
  ok(env.requests.length === 1, 'Cambiar de consulta no vuelve a pedir la lista.');
  ok(box.textContent.includes('mismo autor') && box.textContent.includes('0 usos') && !box.find((node) => node.tagName === 'A'), 'Sin permiso de la pantalla, sólo «N usos», sin enlace.');
  ok(box.textContent.includes('Con contexto «padre»'), 'Con contexto «padre», el aviso de los filtros por AJAX.');
  settings.set('digitalisimo_saved_query', '99');
  env.flush();
  ok(box.textContent === config.i18n.missing, 'Consulta inexistente: el aviso de que el bucle queda vacío.');
  settings.set('digitalisimo_saved_query', '');
  env.flush();
  ok(box.textContent === '', 'Con «Ninguna», el resumen se vacía.');
  // Otro panel: suelta el anterior; un widget sin consulta de PRO no engancha nada.
  const other = settingsModel({});
  env.actions['panel/open_editor/widget'](panelFor().panel, { get: (key) => (key === 'widgetType' ? 'heading' : other) });
  ok(settings.count('change:digitalisimo_saved_query') === 0 && other.count('change:digitalisimo_saved_query') === 0, 'Al abrir otro widget suelta el anterior y no engancha uno ajeno.');
}

// 2b. Con el panel del núcleo, el resumen se busca en la página actual del panel (`getCurrentPageView().el`).
{
  const env = setup();
  const { panel, box } = panelFor();
  env.elementor.getPanelView = () => ({ getCurrentPageView: () => ({ el: panel.$el[0] }) });
  const settings = settingsModel({ digitalisimo_saved_query: '5' });
  env.actions['panel/open_editor/widget']({}, { get: (key) => (key === 'widgetType' ? 'loop-carousel' : settings) });
  env.flush();
  env.requests[0].options.success(list);
  ok(box.children.length === 2 && box.children[0].textContent === list.queries[5].summary, 'Encuentra el resumen en la página actual del panel.');
  env.channel['section:activated'][0]();
  env.flush();
  ok(env.requests.length === 1 && box.children.length === 2, 'Al abrir una sección se vuelve a pintar, sin otra petición.');
}

// 3. Si la lista falla, se vuelve a pedir la próxima vez.
{
  const env = setup();
  const { panel, box } = panelFor();
  const settings = settingsModel({ digitalisimo_saved_query: '5' });
  env.actions['panel/open_editor/widget'](panel, { get: (key) => (key === 'widgetType' ? 'loop-grid' : settings) });
  env.flush();
  env.requests[0].options.error();
  ok(box.textContent === config.i18n.missing, 'Lista fallida: el aviso.');
  settings.set('digitalisimo_saved_query', '5');
  env.flush();
  ok(env.requests.length === 2, 'Y se vuelve a pedir.');
}

// 4. «Desvincular»: lee, confirma y copia con un único $e.run (con «Deshacer»); nunca escribe en el servidor.
{
  const env = setup();
  const settings = settingsModel({ digitalisimo_saved_query: '5', _skin: 'cards', cards_posts_per_page: 12 });
  const container = { settings, model: { get: (key) => (key === 'widgetType' ? 'posts' : null) } };
  env.channel['digitalisimo:queries:unlink'][0]({ container });
  ok(env.requests.length === 1 && env.requests[0].action === 'digitalisimo_f7b_query_settings' && env.requests[0].options.data.id === '5', 'Lee los ajustes con digitalisimo_f7b_query_settings { id }.');
  ok(env.runs.length === 0 && env.dialogs.length === 0, 'Nada cambia antes de la respuesta.');
  env.requests[0].options.success({
    id: 5,
    settings: { post_type: 'post', include: ['terms'], include_term_ids: [11, 12], include_authors: [], exclude: [], exclude_ids: [], exclude_term_ids: [], exclude_authors: [], avoid_duplicates: '', offset: 0, select_date: 'anytime', date_before: '', date_after: '', orderby: 'post_title', order: 'asc' },
    per_page: 6,
    context: 'current',
    relation: 'terms',
  });
  ok(env.dialogs.length === 1 && env.dialogs[0].type === 'confirm' && env.dialogs[0].shown, 'Pide confirmación con el diálogo del editor.');
  const message = env.dialogs[0].options.message;
  ok(message.includes('Los resultados cambiarán.') && message.includes('&lt;b&gt;') && !message.includes('<b>'), 'Avisa de que los resultados cambian (contexto o relación) y escapa el texto.');
  ok(env.runs.length === 0, 'Sin confirmar, nada.');
  env.dialogs[0].options.onConfirm();
  ok(env.runs.length === 1 && env.runs[0].command === 'document/elements/settings', 'Confirmar: un único $e.run( document/elements/settings ).');
  const args = env.runs[0].args;
  ok(args.container === container && args.options.external === true, 'Sobre el contenedor del widget, refrescando el panel.');
  const copied = args.settings;
  ok(copied.posts_post_type === 'post' && copied.posts_orderby === 'post_title' && copied.posts_order === 'asc', 'Copia el tipo y el orden con el prefijo del grupo.');
  ok(Array.isArray(copied.posts_include_term_ids) && copied.posts_include_term_ids.join() === '11,12' && typeof copied.posts_include_term_ids[0] === 'string', 'Las listas de IDs como texto (lo que guarda el control de consulta).');
  ok(copied.posts_include_authors.length === 0 && copied.posts_select_date === 'anytime', 'También los vacíos: el widget queda igual que la consulta.');
  ok(copied.digitalisimo_saved_query === '', 'Y pone el select en «Ninguna».');
  ok(copied.cards_posts_per_page === 6, 'La cantidad del skin baja al tope de la consulta.');
  ok(!('per_page' in copied) && !('context' in copied) && !('relation' in copied), 'No copia nada fuera del grupo.');
}

// 5. Sin contexto ni relación, sin el aviso; un Loop Grid usa su prefijo; la cantidad por debajo del tope se respeta.
{
  const env = setup();
  const settings = settingsModel({ digitalisimo_saved_query: '8', posts_per_page: 3 });
  const container = { settings, model: { get: () => 'loop-grid' } };
  env.channel['digitalisimo:queries:unlink'][0]({ container });
  env.requests[0].options.success({ id: 8, settings: { post_type: 'page', orderby: 'post_date', order: 'desc' }, per_page: 10, context: 'none', relation: 'none' });
  ok(!env.dialogs[0].options.message.includes('Los resultados cambiarán.'), 'Sin contexto ni relación, sin el aviso.');
  env.dialogs[0].options.onConfirm();
  const copied = env.runs[0].args.settings;
  ok(copied.post_query_post_type === 'page' && !('posts_per_page' in copied), 'Loop Grid: prefijo post_query_; su cantidad (3) ya está bajo el tope.');
}

// 6. Casos que no hacen nada: sin consulta elegida, sin contenedor, widget ajeno; lectura fallida avisa.
{
  const env = setup();
  env.channel['digitalisimo:queries:unlink'][0]({ container: { settings: settingsModel({ digitalisimo_saved_query: '' }), model: { get: () => 'posts' } } });
  env.channel['digitalisimo:queries:unlink'][0]({});
  env.channel['digitalisimo:queries:unlink'][0]({ container: { settings: settingsModel({ digitalisimo_saved_query: '5' }), model: { get: () => 'heading' } } });
  ok(env.requests.length === 0, 'Sin consulta, sin contenedor o en otro widget: ninguna petición.');
  env.channel['digitalisimo:queries:unlink'][0]({ container: { settings: settingsModel({ digitalisimo_saved_query: '5' }), model: { get: () => 'portfolio' } } });
  env.requests[0].options.error();
  ok(env.toasts.length === 1 && env.toasts[0].message === config.i18n.error && env.runs.length === 0, 'Lectura fallida: un aviso y nada cambia.');
}

// 7. Pantalla «Consultas» (`queries/assets/queries-screen.js`): búsqueda por POST con nonce, «Añadir» crea el campo
// oculto, «Quitar» lo quita y devuelve el foco, un selector simple sustituye, y «Borrar» pide confirmación.
const screenFile = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/queries/assets/queries-screen.js', import.meta.url);
const screenSource = readFileSync(screenFile, 'utf8');
ok((() => { new vm.Script(screenSource, { filename: 'queries-screen.js' }); return true; })(), 'El script de la pantalla compila.');
ok(statSync(screenFile).size <= 6144 && !/jQuery|\$\(/.test(screenSource) && !/https?:\/\//.test(screenSource) && !/innerHTML\s*=/.test(screenSource), 'Pantalla: ≤ 6 KB, sin jQuery, sin URLs, sin innerHTML.');

class El {
  constructor(tag, attrs = {}) { this.tagName = tag.toUpperCase(); this.attrs = { ...attrs }; this.children = []; this.parent = null; this.text = ''; this.listeners = {}; this.value = attrs.value || ''; }
  get textContent() { return this.text + this.children.map((c) => c.textContent).join(''); }
  set textContent(v) { this.text = String(v); this.children.forEach((c) => { c.parent = null; }); this.children = []; }
  set type(v) { this.attrs.type = v; } get type() { return this.attrs.type; }
  set name(v) { this.attrs.name = v; } get name() { return this.attrs.name; }
  set className(v) { this.attrs.class = v; } get className() { return this.attrs.class; }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; }
  hasAttribute(k) { return k in this.attrs; }
  removeAttribute(k) { delete this.attrs[k]; }
  appendChild(c) { c.parent = this; this.children.push(c); return c; }
  remove() { if (this.parent) { this.parent.children = this.parent.children.filter((c) => c !== this); this.parent = null; } }
  addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); }
  fire(type, extra = {}) {
    const event = { type, target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra };
    for (let node = this; node; node = node.parent) (node.listeners[type] || []).forEach((fn) => fn(event));
    return event;
  }
  matches(sel) {
    let m = sel.match(/^\[([\w-]+)\]$/); if (m) return this.hasAttribute(m[1]) || (m[1] === 'value' && this.value !== '');
    m = sel.match(/^(\w+)\[([\w-]+)(?:="([^"]*)")?\]$/); if (m) return this.tagName === m[1].toUpperCase() && (m[3] === undefined ? this.hasAttribute(m[2]) : String(m[2] === 'value' ? this.value : this.getAttribute(m[2])) === m[3]);
    return this.tagName === sel.toUpperCase();
  }
  all(sel, out = []) { for (const c of this.children) { if (c.matches(sel)) out.push(c); c.all(sel, out); } return out; }
  querySelector(sel) { return this.all(sel)[0] || null; }
  querySelectorAll(sel) { return this.all(sel); }
  closest(sel) { for (let n = this; n; n = n.parent) if (n.matches(sel)) return n; return null; }
  contains(node) { for (let n = node; n; n = n.parent) if (n === this) return true; return false; }
  focus() { this.focused = true; }
}

function screenSetup({ single = false, chosen = [] } = {}) {
  const body = new El('body');
  const type = body.appendChild(new El('select', { 'data-digi-sq-type': '', value: 'post' }));
  type.value = 'post';
  const picker = body.appendChild(new El('div', { 'data-digi-sq-picker': '', 'data-object': 'term', 'data-name': single ? 'q[ctx]' : 'q[settings][include_term_ids][]', ...(single ? { 'data-single': '' } : {}) }));
  const list = picker.appendChild(new El('ul', { 'data-chosen': '' }));
  for (const id of chosen) {
    const li = list.appendChild(new El('li'));
    const input = li.appendChild(new El('input', { type: 'hidden', name: 'x', value: String(id) }));
    input.value = String(id);
    li.appendChild(new El('button', { 'data-remove': '' }));
  }
  const search = picker.appendChild(new El('input', { 'data-search': '' }));
  const go = picker.appendChild(new El('button', { 'data-go': '' }));
  const results = picker.appendChild(new El('ul', { 'data-results': '' }));
  const status = picker.appendChild(new El('p', { 'data-status': '' }));
  const del = body.appendChild(new El('button', { 'data-confirm': '¿Borrar esta consulta?' }));
  const requests = [];
  let answer = { success: true, data: { results: [{ id: 11, text: 'Categorías: Noticias' }, { id: 12, text: 'Categorías: <b>Deportes</b>' }] } };
  const confirms = [];
  let confirmResult = false;
  const window = {
    digitalisimoQueriesScreen: { url: 'admin-ajax.php', action: 'digitalisimo_f7b_queries_search', nonce: 'n0nce', i18n: { short: 'Escribe al menos 2 letras.', none: 'Sin resultados.', found: '%d resultados.', add: 'Añadir «%s»', remove: 'Quitar «%s»', removeT: 'Quitar', error: 'La búsqueda falló.', added: 'Se añadió «%s».' } },
    confirm(message) { confirms.push(message); return confirmResult; },
  };
  const document = {
    createElement: (tag) => new El(tag),
    createTextNode: (text) => { const node = new El('#text'); node.text = String(text); return node; },
    querySelector: (sel) => body.querySelector(sel),
    querySelectorAll: (sel) => body.querySelectorAll(sel),
  };
  const fetch = (url, options) => { requests.push({ url, options }); return Promise.resolve({ json: () => Promise.resolve(answer) }); };
  vm.runInContext(screenSource, vm.createContext({ window, document, fetch, URLSearchParams, Promise, console }));
  return { body, picker, list, search, go, results, status, del, requests, confirms, setConfirm(v) { confirmResult = v; }, setAnswer(v) { answer = v; } };
}
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

{
  const s = screenSetup();
  s.search.value = 'n';
  s.go.fire('click');
  ok(s.requests.length === 0 && s.status.textContent === 'Escribe al menos 2 letras.', 'Menos de 2 letras: no busca y lo dice.');
  s.search.value = ' noti ';
  const key = s.search.fire('keydown', { key: 'Enter' });
  ok(key.defaultPrevented && s.requests.length === 1, 'Intro busca (y no envía el formulario).');
  const sent = new URLSearchParams(String(s.requests[0].options.body));
  ok(s.requests[0].options.method === 'POST' && s.requests[0].options.credentials === 'same-origin' && sent.get('action') === 'digitalisimo_f7b_queries_search' && sent.get('nonce') === 'n0nce' && sent.get('object') === 'term' && sent.get('q') === 'noti' && sent.get('post_type') === 'post', 'Busca por POST con acción, nonce, objeto, texto y tipo.');
  ok(s.picker.getAttribute('aria-busy') === 'true', 'Mientras busca, aria-busy.');
  await tick(); await tick(); await tick();
  ok(!s.picker.hasAttribute('aria-busy') && s.results.children.length === 2 && s.status.textContent === '2 resultados.', 'Dos resultados como botones y el anuncio.');
  const add = s.results.children[1].children[0];
  ok(add.tagName === 'BUTTON' && add.textContent === 'Añadir «Categorías: <b>Deportes</b>»', 'El texto del resultado va como texto, no como HTML.');
  add.fire('click');
  const input = s.list.querySelector('input');
  ok(input && input.name === 'q[settings][include_term_ids][]' && input.value === '12' && input.type === 'hidden', '«Añadir» crea el campo oculto con el ID.');
  ok(s.results.children.length === 1 && s.status.textContent === 'Se añadió «Categorías: <b>Deportes</b>».', 'El resultado sale de la lista y se anuncia.');
  const remove = s.list.querySelector('[data-remove]');
  ok(remove.getAttribute('aria-label') === 'Quitar «Categorías: <b>Deportes</b>»', '«Quitar» nombra lo que quita.');
  remove.fire('click');
  ok(s.list.children.length === 0 && s.search.focused, '«Quitar» lo quita y devuelve el foco a la búsqueda.');
  s.setAnswer({ success: false, data: { message: 'x' } });
  s.go.fire('click');
  await tick(); await tick(); await tick();
  ok(s.status.textContent === 'La búsqueda falló.' && !s.picker.hasAttribute('aria-busy'), 'Un error se anuncia.');
  s.del.fire('click');
  ok(s.confirms.length === 1 && s.confirms[0] === '¿Borrar esta consulta?', '«Borrar» pide confirmación.');
}
{
  const s = screenSetup({ single: true, chosen: [7] });
  s.search.value = 'madre';
  s.go.fire('click');
  await tick(); await tick(); await tick();
  s.results.children[0].children[0].fire('click');
  ok(s.list.children.length === 1 && s.list.querySelector('input').value === '11', 'Selector simple (contexto de «Probar»): el nuevo sustituye al anterior.');
  s.search.value = 'madre';
  s.go.fire('click');
  await tick(); await tick(); await tick();
  s.results.children[0].children[0].fire('click');
  ok(s.list.children.length === 1, 'Elegir dos veces lo mismo no lo repite.');
}

console.log(`elements-f7b-queries.mjs: ${checks} comprobaciones en verde.`);
