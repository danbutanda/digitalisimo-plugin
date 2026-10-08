import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const listeners = {};
const mediaListeners = {};
const elements = [1, 2].map(() => {
  const state = { played: 0, paused: 0, cancelled: 0 };
  return {
    state,
    matches: () => true,
    getAttribute: (name) => name.endsWith('distance') ? '12' : '5',
    getBoundingClientRect: () => ({ top: 0, bottom: 100 }),
    animate: (frames, options) => { state.frames = frames; state.options = options; return { play: () => state.played++, pause: () => state.paused++, cancel: () => state.cancelled++ }; },
  };
});
const doc = {
  readyState: 'complete', hidden: false,
  querySelectorAll: () => elements,
  addEventListener: (name, callback) => { listeners[name] = callback; },
};
let observer;
const media = { matches: false, addEventListener: (name, callback) => { mediaListeners[name] = callback; } };
const context = {
  document: doc,
  window: {
    innerHeight: 800,
    matchMedia: () => media,
    IntersectionObserver: class { constructor(callback) { this.callback = callback; observer = this; } observe() {} unobserve() {} },
  },
  Map,
  parseInt,
  parseFloat,
};
context.IntersectionObserver = context.window.IntersectionObserver;
vm.runInNewContext(readFileSync(new URL('../digitalisimo-elements/modules/digitalisimo-widgets/assets/js/floating-effects.js', import.meta.url), 'utf8'), context);
assert.equal(elements[0].state.paused, 1);
assert.equal(elements[1].state.paused, 1);
observer.callback(elements.map((target) => ({ target, isIntersecting: true })));
assert.equal(elements[0].state.played, 1);
assert.equal(elements[1].state.played, 1);
assert.equal(elements[0].state.options.iterations, Infinity);
assert.equal(elements[0].state.frames[1].translate, '0 -12px');
doc.hidden = true; listeners.visibilitychange();
assert.equal(elements[0].state.paused, 2);
doc.hidden = false;
media.matches = true;
mediaListeners.change();
assert.equal(elements[0].state.cancelled, 1);
assert.equal(elements[1].state.cancelled, 1);
console.log('DIGITALÍSIMO Elements: dos animaciones, pausa y limpieza validadas.');
