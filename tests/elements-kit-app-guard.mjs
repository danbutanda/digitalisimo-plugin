// Kits (etapa K, CV-27): la guarda de la App en assets/digitalisimo/vendor-free.js lleva la biblioteca de kits del
// proveedor y el importador y el exportador heredados (2.0) al importador 3.0, sin capturar sus propias rutas
// (#/import-customization/…, #/export-customization/…), y el asistente de configuración al Inicio rápido propio.
// Evalúa las funciones reales del archivo (kitRoute y app) con una ventana simulada. También revisa
// assets/digitalisimo/kits.js: ≤ 2 KB, sólo en «Importación completa», sin peticiones ni HTML inyectado.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = readFileSync(join(ROOT, 'digitalisimo-elements/assets/digitalisimo/vendor-free.js'), 'utf8');

/** Texto de una función declarada en el archivo (`function nombre(…) { … }`), con sus llaves equilibradas. */
const extract = (name) => {
  const start = source.indexOf(`function ${name}(`);
  assert.ok(start >= 0, `vendor-free.js debe declarar ${name}()`);
  let depth = 0;
  for (let i = source.indexOf('{', start); i < source.length; i++) {
    if (source[i] === '{') depth++;
    else if (source[i] === '}' && --depth === 0) return source.slice(start, i + 1);
  }
  throw new Error(`${name}() sin cerrar`);
};

const build = (hash, config) => {
  const replaced = [];
  const listeners = {};
  const window = {
    location: { hash, replace: (target) => replaced.push(target) },
    addEventListener: (type, fn) => { listeners[type] = fn; },
  };
  const { app } = new Function('window', 'config', `${extract('kitRoute')}\n${extract('app')}\nreturn { app };`)(window, config);
  app();
  return { replaced, listeners, window };
};
const route = (hash, config = { home: 'https://sitio.test/wp-admin/admin.php?page=elementor' }) => build(hash, config).replaced;

// Redirigen al importador o al exportador 3.0, sin la consulta del 2.0 ni de la biblioteca del proveedor.
for (const hash of ['#/import', '#/import/', '#/import/process', '#/import?referrer=kit-library&id=1', '#/import/content?x=1', '#/kit-library', '#/kit-library/cloud', '#/kit-library/favorites', '#/kit-library/preview/9?x=1']) {
  assert.deepEqual(route(hash), ['#/import-customization'], `${hash} lleva al importador de kits 3.0`);
}
for (const hash of ['#/export', '#/export/', '#/export/process', '#/export?x=1']) {
  assert.deepEqual(route(hash), ['#/export-customization'], `${hash} lleva al exportador de kits 3.0`);
}
// Se quedan: las rutas del 3.0 (también la del kit preparado por la pantalla «Kits») y las demás de la App.
for (const hash of ['#/import-customization', '#/import-customization/', '#/import-customization/content', '#/import-customization/complete', '#/import-customization?referrer=digitalisimo&id=abababababababababababababababab', '#/export-customization', '#/export-customization/complete', '#/importer', '#/exports', '#/site-editor', '#/site-editor/templates', '', '#']) {
  assert.deepEqual(route(hash), [], `${hash || '(vacío)'} no se redirige`);
}
// El asistente de configuración lleva al Inicio rápido propio; sin destino configurado no se mueve.
assert.deepEqual(route('#/onboarding'), ['https://sitio.test/wp-admin/admin.php?page=elementor'], '#/onboarding lleva al Inicio rápido');
assert.deepEqual(route('#onboarding'), ['https://sitio.test/wp-admin/admin.php?page=elementor'], '#onboarding lleva al Inicio rápido');
assert.deepEqual(route('#/onboarding', {}), [], 'Sin destino configurado, el asistente no se redirige a ninguna parte');

// La guarda también actúa al cambiar el hash, y su destino es estable (no hay bucles).
const live = build('#/import-customization', {});
assert.equal(typeof live.listeners.hashchange, 'function', 'La guarda escucha hashchange');
live.window.location.hash = '#/kit-library/cloud';
live.listeners.hashchange();
live.window.location.hash = '#/export';
live.listeners.hashchange();
assert.deepEqual(live.replaced, ['#/import-customization', '#/export-customization'], 'Al navegar dentro de la App, la guarda redirige igual');
for (const target of ['#/import-customization', '#/export-customization']) assert.deepEqual(route(target), [], `${target} es un destino estable`);
assert.match(source, /if \( config\.context === 'app' \) \{\s*app\(\);/, 'La guarda corre en la App');

// kits.js: el enlace al informe sólo en «Importación completa», sin peticiones ni HTML inyectado.
const kits = readFileSync(join(ROOT, 'digitalisimo-elements/assets/digitalisimo/kits.js'), 'utf8');
assert.ok(Buffer.byteLength(kits) <= 2048, `kits.js pesa ${Buffer.byteLength(kits)} bytes (máximo 2 KB)`);
assert.ok(!/fetch\(|XMLHttpRequest|innerHTML|insertAdjacentHTML|document\.write|https?:\/\//.test(kits), 'kits.js no hace peticiones, no inyecta HTML y no lleva direcciones');
const page = (hash, withBox = true) => {
  const nodes = {};
  const box = { children: [], appendChild(child) { this.children.push(child); nodes[child.id] = child; } };
  const make = (tag) => ({ tag, style: {}, children: [], appendChild(child) { this.children.push(child); } });
  const document = {
    documentElement: {},
    getElementById: (id) => nodes[id] || null,
    querySelector: (selector) => (withBox && selector === '[data-testid="import-complete-content"]' ? box : null),
    createElement: make,
    createTextNode: (text) => ({ text }),
  };
  const listeners = {};
  const window = {
    location: { hash },
    digitalisimoKits: { report: 'https://sitio.test/wp-admin/admin.php?page=digitalisimo-elements-kits', intro: 'Informe:', label: 'ver el informe en Kits' },
    addEventListener: (type, fn) => { listeners[type] = fn; },
  };
  new Function('window', 'document', kits)(window, document);
  return { box, listeners, window };
};
const done = page('#/import-customization/complete');
assert.equal(done.box.children.length, 1, 'En «Importación completa» se añade el enlace al informe');
const [line] = done.box.children;
assert.equal(line.id, 'digitalisimo-kit-report');
assert.equal(line.children[1].href, 'https://sitio.test/wp-admin/admin.php?page=digitalisimo-elements-kits', 'El enlace lleva a la pantalla «Kits» del sitio');
assert.equal(line.children[1].textContent, 'ver el informe en Kits', 'El texto del enlace sale de la configuración, como texto');
done.listeners.hashchange();
assert.equal(done.box.children.length, 1, 'El enlace no se repite');
for (const hash of ['#/import-customization', '#/import-customization/content', '#/export-customization/complete']) {
  assert.equal(page(hash).box.children.length, 0, `${hash}: sin enlace`);
}
assert.equal(page('#/import-customization/complete', false).box.children.length, 0, 'Sin la pantalla final pintada, no se añade nada');
const later = page('#/import-customization/content');
later.window.location.hash = '#/import-customization/complete';
later.listeners.hashchange();
assert.equal(later.box.children.length, 1, 'Al llegar a «Importación completa», aparece el enlace');

console.log('Guarda de la App: kits al importador 3.0, el 2.0 y la biblioteca del proveedor redirigidos, sin bucles; kits.js sólo enlaza el informe.');
