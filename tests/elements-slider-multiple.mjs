import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('digitalisimo-elements/assets/js/slider-optimizado.js', 'utf8');
const frames = [];
const starts = [];
let intersection;

function slider(id, top = 100) {
  let finish;
  const image = {
    complete: false, naturalWidth: 0, loading: 'lazy',
    getBoundingClientRect: () => ({ left: 0, right: 100, width: 100 }),
    decode: () => new Promise((resolve) => { finish = () => { image.complete = true; image.naturalWidth = 100; resolve(); }; })
  };
  const classes = new Set(['digi-slider--preparing']);
  const instance = {
    dataset: {}, isConnected: true,
    classList: {
      contains: (name) => classes.has(name),
      remove: (name) => { classes.delete(name); if (name === 'digi-slider--preparing') starts.push({ id, frame: frameNumber }); }
    },
    getBoundingClientRect: () => ({ top, bottom: top + 100, left: 0, right: 100, width: 100, height: 100 }),
    querySelector: () => ({ getBoundingClientRect: () => ({ left: 0, right: 100, width: 100 }) }),
    querySelectorAll: (selector) => selector === '.digi-slider--preparing' ? [] : [image],
    matches: () => true
  };
  return { instance, image, finish: () => finish() };
}

let frameNumber = 0;
let elementorHook;
function nextFrame() {
  const callbacks = frames.splice(0);
  frameNumber++;
  for (const callback of callbacks) callback();
}
async function settle() { for (let i = 0; i < 8; i++) await Promise.resolve(); }

const visible = Array.from({ length: 30 }, (_, i) => slider(i));
const below = slider(30, 3000);
const staticSlider = slider(31);
staticSlider.instance.classList.remove('digi-slider--preparing');
starts.length = 0;
const all = [...visible, below, staticSlider].map((value) => value.instance);
const context = {
  document: { readyState: 'complete', querySelectorAll: () => all },
  window: {
    innerWidth: 1200, innerHeight: 800,
    requestAnimationFrame: (callback) => { frames.push(callback); return frames.length; },
    setTimeout, clearTimeout,
    matchMedia: () => ({ matches: false }),
    elementorFrontend: { hooks: { addAction: (_, callback) => { elementorHook = callback; } } }
  },
  IntersectionObserver: class {
    constructor(callback) { intersection = callback; this.observed = new Set(); }
    observe(item) { this.observed.add(item); }
    unobserve(item) { this.observed.delete(item); }
  },
  Set, Promise
};
context.window.IntersectionObserver = context.IntersectionObserver;
vm.runInNewContext(source, context);
elementorHook({ 0: visible[0].instance });
elementorHook({ 0: visible[0].instance });
assert.equal(frames.length, 1, 'Las instancias visibles deben agruparse en un solo fotograma.');
nextFrame();
assert.equal(visible.filter((value) => value.image.loading === 'eager').length, 30);
assert.equal(below.image.loading, 'lazy', 'Un slider lejano no debe bloquear ni descargar imágenes antes de acercarse.');
for (const value of visible.slice(0, -1)) value.finish();
await settle();
assert.equal(starts.length, 0, 'Ninguna instancia visible debe iniciar mientras otra de su grupo carga.');
visible.at(-1).finish();
await settle();
nextFrame();
assert.equal(starts.length, 30, 'No debe existir un límite de instancias por página.');
assert.equal(new Set(starts.map((value) => value.frame)).size, 1, 'Las instancias visibles deben iniciar juntas.');
assert.equal(below.instance.classList.contains('digi-slider--preparing'), true);
intersection([{ target: below.instance, isIntersecting: true }]);
nextFrame();
below.finish();
await settle();
nextFrame();
assert.equal(starts.length, 31, 'La instancia lejana debe iniciar al entrar en el viewport.');
assert.equal(starts.filter((value) => value.id === 31).length, 0, 'El slider estático no debe tocarse.');

const reduced = slider(32);
const reducedContext = {
  document: { readyState: 'complete', querySelectorAll: () => [reduced.instance] },
  window: {
    innerWidth: 1200, innerHeight: 800,
    requestAnimationFrame: () => { throw new Error('Movimiento reducido no debe preparar una animación.'); },
    setTimeout, clearTimeout,
    matchMedia: () => ({ matches: true })
  },
  Set, Promise
};
vm.runInNewContext(source, reducedContext);
assert.equal(reduced.instance.classList.contains('digi-slider--preparing'), false, 'Movimiento reducido debe mostrar el slider sin esperar imágenes o animación.');
assert.equal(reduced.image.loading, 'lazy', 'Movimiento reducido debe conservar la carga nativa de las imágenes.');

console.log('Slider Optimizado: 30 instancias sincronizadas, una diferida, modo estático y movimiento reducido independientes.');
