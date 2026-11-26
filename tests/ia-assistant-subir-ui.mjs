// IA Tools · arrastrar archivos al chat del Asistente IA (pedido del propietario del 2026-11-26: «solo permite que el
// asistente cargue archivos arrastrándolos y los agregue a la biblioteca»). Sin navegador: assistant.js corre con un
// wp.element mínimo que SÍ pinta (componentes, useState, useEffect, useRef) y se le disparan los eventos como los
// dispara React:
//   D1  arrastrar archivos sobre el Asistente muestra «Suelta aquí tus archivos»; algo que no son archivos, no;
//   D2  soltar 2 JPG, 1 PNG, un PHP y uno más pesado que el máximo: los permitidos suben uno por uno por el adaptador
//       (el pesado ni se manda), la bandeja los muestra con su miniatura y el hilo dice en llano «Subí 3 imágenes a tu
//       biblioteca (de 6 MB a 600 KB en total).» y qué no se subió;
//   D3  una miniatura de otro sitio no se pinta; nada se pinta como HTML;
//   D4  enviar: el mensaje lleva los ids de lo subido (no el PHP ni el pesado) y la burbuja, sus miniaturas; sin texto
//       también se puede enviar; mientras sube, no se puede enviar;
//   D5  más de 10 por vez: se avisa y se dejan fuera; el clip abre el selector de archivos; pegar una imagen también sube;
//   D6  quien no puede subir (sin upload_files) no ve el clip y soltar no hace nada;
//   D7  el adaptador sube por multipart a assistant/upload con los dos nonces y sin Content-Type propio;
//   D8  pesos y resumen en palabras de cualquiera (formatBytes, uploadSummary).
// Falla con el código anterior (no hay soltar, clip, bandeja ni adaptador de subida). Uso: node tests/ia-assistant-subir-ui.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const root = new URL('../digitalisimo-ia-tools/assets/assistant/', import.meta.url);
const ui = readFileSync(new URL('assistant.js', root), 'utf8');
const adaptersSource = readFileSync(new URL('assistant-adapters.js', root), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const tick = () => new Promise((r) => setTimeout(r, 0));
const settle = async () => { for (let i = 0; i < 20; i++) await tick(); };

/** Un wp.element mínimo: pinta a un árbol de objetos, con estado por posición y efectos tras cada pintada. */
function makeElement() {
  const store = new Map();
  let app = null; let tree = null; let path = ''; let hook = 0; let queued = false; let effects = [];
  const key = () => `${path}#${hook++}`;
  const render = () => {
    effects = [];
    tree = resolve(app, 'r');
    const run = effects; effects = [];
    run.forEach((f) => f());
  };
  const schedule = () => { if (!queued) { queued = true; queueMicrotask(() => { queued = false; render(); }); } };
  function resolve(node, at) {
    if (node === null || node === undefined || node === false || node === true) return null;
    if (Array.isArray(node)) return node.map((n, i) => resolve(n, `${at}.${(n && n.props && n.props.key) ?? i}`));
    if (typeof node !== 'object') return String(node);
    if (typeof node.type === 'function') {
      const before = [path, hook];
      path = `${at}:${node.type.name}`; hook = 0;
      const out = node.type(node.props);
      const me = path;
      [path, hook] = before;
      return resolve(out, me);
    }
    return { type: node.type, props: node.props, children: node.props.children.map((c, i) => resolve(c, `${at}/${(c && c.props && c.props.key) ?? i}`)) };
  }
  const element = {
    Fragment: 'Fragment',
    createElement: (type, props, ...children) => ({ type, props: { ...(props || {}), children: children.flat(Infinity) } }),
    useState(init) {
      const k = key();
      if (!store.has(k)) store.set(k, typeof init === 'function' ? init() : init);
      return [store.get(k), (v) => { const prev = store.get(k); const next = typeof v === 'function' ? v(prev) : v; if (next !== prev) { store.set(k, next); schedule(); } }];
    },
    useRef(init) { const k = key(); if (!store.has(k)) store.set(k, { current: init }); return store.get(k); },
    useMemo: (f) => f(),
    useCallback: (f) => f,
    useEffect(f, deps) {
      const k = key();
      const prev = store.get(k);
      if (!prev || !deps || deps.some((d, i) => d !== prev.deps[i])) effects.push(() => { if (prev && typeof prev.cleanup === 'function') prev.cleanup(); store.set(k, { deps, cleanup: f() }); });
    },
    createRoot: () => ({ render: (a) => { app = a; render(); } }),
  };
  return { element, tree: () => tree };
}

const all = (node, test, out = []) => {
  if (!node || typeof node !== 'object') return out;
  if (Array.isArray(node)) { node.forEach((n) => all(n, test, out)); return out; }
  if (test(node)) out.push(node);
  all(node.children, test, out);
  return out;
};
const byClass = (tree, name) => all(tree, (n) => typeof n.props.className === 'string' && n.props.className.split(/\s+/).includes(name));
const text = (node) => (!node ? '' : typeof node === 'string' ? node : Array.isArray(node) ? node.map(text).join('') : text(node.children));

const file = (name, size, type) => ({ name, size, type });
const SITE = 'https://tienda.test';

function boot({ upload = { maxBytes: 8 * 1048576, maxFiles: 10, webp: true }, uploads = {} } = {}) {
  const { element, tree } = makeElement();
  const sent = []; const uploaded = [];
  let resolveUpload = null;
  const adapters = {
    history: { list: async () => [], get: async () => null, remove: async () => ({}), clear: async () => ({}) },
    model: { providers: async () => [], send: async (payload, onEvent) => { sent.push(payload); onEvent({ type: 'conversation', id: 9, title: 'T' }); onEvent({ type: 'done', pending: 0 }); }, confirm: async () => {}, resume: async () => {} },
    tools: { list: async () => ({ tools: [], categories: [] }), log: async () => [] },
    media: { upload: (f) => { uploaded.push(f.name); return new Promise((res, rej) => { const reply = () => { const r = uploads[f.name]; if (r instanceof Error) rej(r); else res(r); }; if (resolveUpload) resolveUpload.push(reply); else reply(); }); } },
  };
  const window = {
    wp: { element },
    digitalisimoAssistantConfig: { site: { name: 'Tienda', home: `${SITE}/` }, pageUrl: `${SITE}/wp-admin/admin.php?page=digitalisimo-assistant`, providers: [{ key: 'anthropic', name: 'Anthropic', aiName: 'Claude', status: 'available' }], upload, limits: { message: 4000 } },
    digitalisimoAssistantAdapters: { create: () => adapters },
    location: { href: `${SITE}/wp-admin/admin.php?page=digitalisimo-assistant`, host: 'tienda.test' },
    URL,
    history: { replaceState() {} },
    localStorage: { getItem: () => null, setItem: () => {} },
    matchMedia: () => ({ matches: true, addEventListener() {}, removeEventListener() {} }),
    getComputedStyle: () => ({ lineHeight: '20px' }),
    confirm: () => true,
  };
  const document = { getElementById: () => ({}), addEventListener() {}, removeEventListener() {} };
  vm.runInNewContext(ui, { window, document, console, Promise, setTimeout, queueMicrotask });
  return { tree, sent, uploaded, hold: () => { resolveUpload = []; return () => { const list = resolveUpload; resolveUpload = null; list.forEach((f) => f()); }; } };
}
const ev = (extra = {}) => ({ defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra });
const dragFiles = (files) => ({ types: ['Files'], files, dropEffect: 'none' });

// 1. Arrastrar y soltar.
const ok1 = (id, name, bytes, before, thumb) => ({ files: [{ id, name, mime: 'image/webp', kind: 'image', bytes, before, width: 1200, height: 900, url: `${SITE}/wp-content/uploads/${name}`, thumb, webp: true, reason: '' }], errors: [] });
const app = boot({ uploads: {
  'foto-1.jpg': ok1(11, 'foto-1.webp', 200 * 1024, 2 * 1048576, `${SITE}/wp-content/uploads/foto-1-150x150.webp`),
  'foto-2.jpg': ok1(12, 'foto-2.webp', 200 * 1024, 2 * 1048576, 'https://otro.test/espia.webp'),
  'logo.png': ok1(13, 'logo.webp', 200 * 1024, 2 * 1048576, `${SITE}/wp-content/uploads/logo-150x150.webp`),
  'shell.php': { files: [], errors: [{ name: 'shell.php', message: 'ese tipo de archivo no se puede subir.' }] },
} });
await settle();
let rootNode = byClass(app.tree(), 'dga-root')[0];
ok(rootNode && typeof rootNode.props.onDrop === 'function' && typeof rootNode.props.onDragOver === 'function', 'D1: el Asistente entero recibe archivos soltados');
const over = ev({ dataTransfer: dragFiles([]) });
rootNode.props.onDragOver(over);
ok(over.defaultPrevented && over.dataTransfer.dropEffect === 'copy', 'D1: al pasar archivos por encima, el navegador no los abre (copiar)');
rootNode.props.onDragEnter(ev({ dataTransfer: dragFiles([]) }));
await settle();
const drop = byClass(app.tree(), 'dga-drop')[0];
ok(drop && /Suelta aquí tus archivos/.test(text(drop)) && /se comprimen/.test(text(drop)), `D1: aparece «Suelta aquí tus archivos»: «${text(drop)}»`);
rootNode = byClass(app.tree(), 'dga-root')[0];
rootNode.props.onDragLeave(ev({ dataTransfer: dragFiles([]) }));
await settle();
ok(!byClass(app.tree(), 'dga-drop').length, 'D1: al salir, el aviso se va');
const textOnly = ev({ dataTransfer: { types: ['text/plain'], files: [] } });
byClass(app.tree(), 'dga-root')[0].props.onDragEnter(textOnly);
await settle();
ok(!byClass(app.tree(), 'dga-drop').length && !textOnly.defaultPrevented, 'D1: arrastrar texto (no archivos) no cambia nada');

const release = app.hold();
const dropEvent = ev({ dataTransfer: dragFiles([file('foto-1.jpg', 2 * 1048576, 'image/jpeg'), file('foto-2.jpg', 2 * 1048576, 'image/jpeg'), file('logo.png', 2 * 1048576, 'image/png'), file('shell.php', 30, 'application/x-php'), file('enorme.jpg', 20 * 1048576, 'image/jpeg')]) });
byClass(app.tree(), 'dga-root')[0].props.onDragEnter(ev({ dataTransfer: dragFiles([]) }));
byClass(app.tree(), 'dga-root')[0].props.onDrop(dropEvent);
await settle();
ok(dropEvent.defaultPrevented && !byClass(app.tree(), 'dga-drop').length, 'D2: al soltar, el navegador no abre el archivo y el aviso se va');
let tray = byClass(app.tree(), 'dga-tray-item');
ok(tray.length === 5 && tray.filter((t) => t.props['data-status'] === 'uploading').length === 4 && tray.find((t) => t.props['data-status'] === 'error'), `D2: la bandeja muestra los 5, subiendo y el pesado con su motivo (${tray.map((t) => t.props['data-status'])})`);
ok(/pesa 20 MB y tu servidor deja subir hasta 8 MB por archivo/.test(text(tray.find((t) => t.props['data-status'] === 'error'))), 'D2: el pesado se explica en llano sin mandarlo');
let send = byClass(app.tree(), 'dga-send')[0];
ok(send.props.disabled === true, 'D4: mientras sube, no se puede enviar');
ok(/Subiendo archivos/.test(text(byClass(app.tree(), 'dga-composer-tools')[0])), 'D4: el redactor dice «Subiendo archivos…»');
release();
await settle();
ok(app.uploaded.join(',') === 'foto-1.jpg,foto-2.jpg,logo.png,shell.php', `D2: subieron uno por uno, en orden, y el pesado no se mandó: ${app.uploaded}`);
tray = byClass(app.tree(), 'dga-tray-item');
ok(tray.filter((t) => t.props['data-status'] === 'done').length === 3 && tray.filter((t) => t.props['data-status'] === 'error').length === 2, 'D2: tres listos y dos con su motivo');
const notes = byClass(app.tree(), 'dga-system');
// El texto del aviso (sin los nombres de sus miniaturas).
const own = (n) => n.children.filter((c) => typeof c === 'string').join('');
const summary = notes.map(own).find((t) => /^Subí/.test(t)) || '';
ok(/^Subí 3 imágenes a tu biblioteca \(de 6 MB a 600 KB en total\)\./.test(summary), `D2: el resumen llano: «${summary}»`);
ok(/No subí «shell\.php»: ese tipo de archivo no se puede subir\./.test(summary) && /No subí «enorme\.jpg»: pesa 20 MB/.test(summary), 'D2: y qué no se subió y por qué');
ok(!/webp|mime|image\/|\bid\b|MCP|REST/i.test(summary), 'D2: sin jerga (ni webp, ni tipos, ni ids)');
const imgs = all(byClass(app.tree(), 'dga-system').find((n) => /^Subí/.test(text(n))), (n) => n.type === 'img');
ok(imgs.length === 2 && imgs.every((i) => i.props.src.startsWith(`${SITE}/`) && i.props.alt === ''), `D3: las miniaturas del resumen, sólo del propio sitio (${imgs.length}; la de otro sitio queda como ícono)`);
ok(!/innerHTML|dangerouslySetInnerHTML/.test(ui), 'D3: nada se pinta como HTML');

// 2. Enviar con los archivos.
send = byClass(app.tree(), 'dga-send')[0];
ok(send.props.disabled === false, 'D4: con los archivos listos se puede enviar aunque no haya texto');
const area = all(app.tree(), (n) => n.type === 'textarea')[0];
ok(/Dime qué hago con estos archivos/.test(area.props.placeholder), 'D4: el cuadro invita a decir qué hacer con ellos');
area.props.onChange({ target: { value: 'Pon la primera como portada de mi landing' } });
await settle();
all(app.tree(), (n) => n.type === 'form')[0].props.onSubmit(ev());
await settle();
ok(app.sent.length === 1 && app.sent[0].message === 'Pon la primera como portada de mi landing' && JSON.stringify(app.sent[0].files) === '[11,12,13]', `D4: el mensaje lleva los ids de lo subido: ${JSON.stringify(app.sent[0])}`);
ok(!byClass(app.tree(), 'dga-tray-item').length, 'D4: la bandeja se vacía al enviar');
const mine = byClass(app.tree(), 'is-me')[0];
ok(mine && all(mine, (n) => n.type === 'img').length === 2 && byClass(mine, 'dga-file').length === 3, 'D4: la burbuja muestra las miniaturas de lo que envió');

// 3. Sólo archivos, sin texto.
const solo = boot({ uploads: { 'menu.pdf': { files: [{ id: 21, name: 'menu.pdf', mime: 'application/pdf', kind: 'file', bytes: 300 * 1024, before: 300 * 1024, width: 0, height: 0, url: `${SITE}/wp-content/uploads/menu.pdf`, thumb: '', webp: false, reason: '' }], errors: [] } } });
await settle();
byClass(solo.tree(), 'dga-root')[0].props.onDrop(ev({ dataTransfer: dragFiles([file('menu.pdf', 300 * 1024, 'application/pdf')]) }));
await settle();
ok(byClass(solo.tree(), 'dga-system').map(own).some((t) => t === 'Subí 1 archivo a tu biblioteca (300 KB).'), `D2: un PDF se sube sin convertir: «${byClass(solo.tree(), 'dga-system').map(own).join(' | ')}»`);
all(solo.tree(), (n) => n.type === 'form')[0].props.onSubmit(ev());
await settle();
ok(solo.sent.length === 1 && solo.sent[0].message === '' && JSON.stringify(solo.sent[0].files) === '[21]', 'D4: se puede enviar sólo con archivos');

// 4. Más de 10, clip y pegar.
const many = boot({ uploads: Object.fromEntries(Array.from({ length: 12 }, (_, i) => [`f${i}.jpg`, ok1(100 + i, `f${i}.webp`, 1000, 5000, '')])) });
await settle();
const clip = byClass(many.tree(), 'dga-attach')[0];
const picker = all(many.tree(), (n) => n.type === 'input' && n.props.type === 'file')[0];
ok(clip && /Subir archivos/.test(clip.props['aria-label']) && picker && picker.props.multiple === true, 'D5: el clip y su selector de varios archivos');
let clicked = false;
all(many.tree(), (n) => n.type === 'input' && n.props.type === 'file')[0].props.ref.current = { click: () => { clicked = true; } };
clip.props.onClick();
ok(clicked, 'D5: el clip abre el selector de archivos');
const chosen = { target: { files: Array.from({ length: 12 }, (_, i) => file(`f${i}.jpg`, 4000, 'image/jpeg')), value: 'C:\\fakepath\\f0.jpg' } };
picker.props.onChange(chosen);
await settle();
ok(many.uploaded.length === 10 && chosen.target.value === '', `D5: con el clip, hasta 10 por vez (${many.uploaded.length}) y el selector queda listo para otra vez`);
ok(byClass(many.tree(), 'dga-system').map(text).some((t) => t === 'Puedes subir hasta 10 archivos por vez: dejé fuera 2.'), 'D5: se avisa de los que quedaron fuera');
const twice = boot({ uploads: Object.fromEntries(Array.from({ length: 12 }, (_, i) => [`g${i}.jpg`, ok1(200 + i, `g${i}.webp`, 1000, 5000, '')])) });
await settle();
const rootTwice = byClass(twice.tree(), 'dga-root')[0];
rootTwice.props.onDrop(ev({ dataTransfer: dragFiles(Array.from({ length: 6 }, (_, i) => file(`g${i}.jpg`, 4000, 'image/jpeg'))) }));
rootTwice.props.onDrop(ev({ dataTransfer: dragFiles(Array.from({ length: 6 }, (_, i) => file(`g${i + 6}.jpg`, 4000, 'image/jpeg'))) }));
await settle();
ok(twice.uploaded.length === 10, `D5: dos soltadas seguidas tampoco pasan de 10 (${twice.uploaded.length})`);
const pasteApp = boot({ uploads: { 'captura.png': ok1(31, 'captura.webp', 10 * 1024, 90 * 1024, '') } });
await settle();
const paste = ev({ clipboardData: { files: [file('captura.png', 90 * 1024, 'image/png')] } });
all(pasteApp.tree(), (n) => n.type === 'textarea')[0].props.onPaste(paste);
await settle();
ok(paste.defaultPrevented && pasteApp.uploaded.join() === 'captura.png' && byClass(pasteApp.tree(), 'dga-system').map(own).includes('Subí 1 imagen a tu biblioteca (de 90 KB a 10 KB).'), 'D5: pegar una imagen en el cuadro también la sube');

// 5. Sin permiso de subir.
const viewer = boot({ upload: null });
await settle();
ok(!byClass(viewer.tree(), 'dga-attach').length && !all(viewer.tree(), (n) => n.type === 'input' && n.props.type === 'file').length, 'D6: sin upload_files no hay clip');
ok(typeof byClass(viewer.tree(), 'dga-root')[0].props.onDrop !== 'function' && !viewer.uploaded.length, 'D6: ni zona para soltar');

// 6. El adaptador.
const calls = [];
const sandbox = { window: { fetch: async (url, options) => { calls.push({ url, options }); return { ok: true, status: 200, json: async () => ({ files: [{ id: 5 }], errors: [] }) }; }, TextDecoder, FormData }, Object, JSON, Promise, Array, String, encodeURIComponent, Error };
vm.runInNewContext(adaptersSource, sandbox);
const adapters = sandbox.window.digitalisimoAssistantAdapters.create({ rest: `${SITE}/?rest_route=/digitalisimo-ai/v1/assistant/`, restNonce: 'R1', nonce: 'A1' });
const blob = new Blob(['x'], { type: 'image/jpeg' });
blob.name = 'foto.jpg';
const res = await adapters.media.upload(blob);
const call = calls[0];
ok(res.files[0].id === 5 && call.url === `${SITE}/?rest_route=/digitalisimo-ai/v1/assistant/upload` && call.options.method === 'POST' && call.options.credentials === 'same-origin', 'D7: sube por POST a assistant/upload con la sesión del sitio');
ok(call.options.headers['X-WP-Nonce'] === 'R1' && call.options.headers['X-Digitalisimo-Assistant'] === 'A1' && !('Content-Type' in call.options.headers), 'D7: con los dos nonces y sin Content-Type propio (multipart del navegador)');
ok(call.options.body instanceof FormData && call.options.body.get('file') && call.options.body.get('file').name === 'foto.jpg', 'D7: el archivo va en el campo «file»');
sandbox.window.fetch = async () => ({ ok: false, status: 403, json: async () => ({ message: 'Tu usuario no puede subir archivos a la biblioteca.' }) });
await assert.rejects(adapters.media.upload(blob), /no puede subir archivos/); checks++;

// 7. Piezas puras.
let parts = null;
{
  // Sólo las piezas puras: un wp.element que no pinta (como tests/ia-assistant-mostrar-ui.mjs).
  const element = { createElement: () => ({}), Fragment: 'F', useState: () => [null, () => {}], useEffect: () => {}, useRef: () => ({}), useMemo: (f) => f(), useCallback: (f) => f, createRoot: () => ({ render: () => {} }), render: () => {} };
  vm.runInNewContext(ui, { window: { wp: { element }, digitalisimoAssistantConfig: {}, digitalisimoAssistantAdapters: { create: () => ({}) }, digitalisimoAssistantExpose: (p) => { parts = p; }, location: { href: `${SITE}/`, host: 'tienda.test' }, URL, localStorage: { getItem: () => null, setItem() {} }, matchMedia: () => ({ matches: false, addEventListener() {}, removeEventListener() {} }) }, document: { getElementById: () => ({}), addEventListener() {}, removeEventListener() {} } });
}
ok(parts.formatBytes(640 * 1024) === '640 KB' && parts.formatBytes(4.2 * 1048576) === '4.2 MB' && parts.formatBytes(12) === '12 bytes' && parts.formatBytes(8 * 1048576) === '8 MB', 'D8: pesos como 640 KB y 4.2 MB (igual que el servidor)');
const img = (bytes, before, reason = '') => ({ kind: 'image', bytes, before, reason });
ok(parts.uploadSummary([img(200, 1000), img(200, 1000), img(240, 2402)], []) === 'Subí 3 imágenes a tu biblioteca (de 4 KB a 640 bytes en total).', `D8: ${parts.uploadSummary([img(200, 1000), img(200, 1000), img(240, 2402)], [])}`);
ok(/Las imágenes quedaron sin comprimir: para eso hace falta activar DIGITALÍSIMO Tools en tu sitio\./.test(parts.uploadSummary([img(5000, 5000, 'tools'), img(5000, 5000, 'tools')], [])), 'D8: sin Tools, se dice qué activar');
ok(/El GIF animado quedó como estaba para no perder la animación\./.test(parts.uploadSummary([img(5000, 5000, 'gif')], [])), 'D8: el GIF animado conservado');
ok(parts.uploadSummary([], [{ name: 'a.exe', message: 'ese tipo de archivo no se puede subir.' }]) === 'No subí «a.exe»: ese tipo de archivo no se puede subir.', 'D8: sólo errores');
ok(/Subí 1 imagen y 1 archivo/.test(parts.uploadSummary([img(10, 20), { kind: 'file', bytes: 5, before: 5 }], [])), 'D8: imágenes y otros archivos');

console.log(`Asistente IA, arrastrar y subir archivos: ${checks} comprobaciones correctas.`);
