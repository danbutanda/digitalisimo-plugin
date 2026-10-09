import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const listeners = new Map();
const reducedListeners = new Map();
const timers = new Map();
let nextTimer = 0;
let elementorInit;
const headings = Array.from({ length: 30 }, () => {
  const phrase = { style: {}, classList: { add() {}, remove() {} }, textContent: 'Primera' };
  const slot = { clientWidth: 240 };
  return {
    isConnected: true,
    dataset: { digiPhrases: '["Primera","Segunda"]', digiInterval: '3000' },
    querySelector(selector) { return selector.endsWith('__phrase') ? phrase : slot; },
  };
});
const document = {
  readyState: 'complete',
  querySelectorAll() { return headings; },
  createElement() {
    return { style: {}, textContent: '', getBoundingClientRect() { return { width: this.textContent.length * 8 }; }, remove() {} };
  },
  body: { appendChild() {} },
};
const window = {
  matchMedia() { return { matches: false, addEventListener(type, listener) { reducedListeners.set(type, listener); } }; },
  addEventListener(type, listener) { listeners.set(type, listener); },
  getComputedStyle() { return { font: '16px sans-serif' }; },
  elementorFrontend: { hooks: { addAction(_name, callback) { elementorInit = callback; } } },
};
const code = readFileSync(new URL('../digitalisimo-elements/assets/js/animated-heading.js', import.meta.url), 'utf8');
runInNewContext(code, {
  document,
  window,
  WeakMap,
  Set,
  setTimeout(callback) { const id = ++nextTimer; timers.set(id, callback); return id; },
  clearTimeout(id) { timers.delete(id); },
});

assert.equal(listeners.size, 1, 'Las 30 instancias deben compartir un listener de resize.');
assert.equal(reducedListeners.size, 1, 'Las 30 instancias deben compartir un listener de movimiento reducido.');
assert.equal(timers.size, 30, 'Cada instancia visible conserva su propia rotación.');
headings[0].isConnected = false;
listeners.get('resize')();
assert.equal(timers.size, 29, 'La instancia retirada libera su temporizador.');
headings[0].isConnected = true;
elementorInit({ 0: { querySelectorAll() { return [headings[0]]; } } });
assert.equal(timers.size, 30, 'Una instancia reinsertada puede volver a inicializarse.');
assert.equal(listeners.size, 1, 'La reinserción no multiplica listeners globales.');
console.log('Encabezado animado: 30 instancias, listeners compartidos y reinserción validados.');
