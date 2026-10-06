import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const listeners = {};
const button = () => ({ disabled: false, attrs: {}, addEventListener(name, callback) { this[name] = callback; }, setAttribute(name, value) { this.attrs[name] = value; } });
const previous = button();
const next = button();
const slides = Array.from({ length: 3 }, (_, position) => ({
  position,
  scrollIntoView() { track.position = position; },
  getBoundingClientRect() { return { left: (this.position - track.position) * 100, right: (this.position - track.position + 1) * 100 }; },
}));
const list = { children: slides };
const track = {
  position: 0,
  querySelector(selector) { return selector === '.digi-carousel__slides' ? list : null; },
  addEventListener(name, callback) { listeners[name] = callback; },
  getBoundingClientRect() { return { left: 0, right: 100 }; },
};
const root = {
  dataset: {},
  querySelector(selector) { return ({ '[data-digi-carousel-track]': track, '[data-digi-carousel-prev]': previous, '[data-digi-carousel-next]': next })[selector] || null; },
};
const document = { readyState: 'complete', documentElement: { dir: 'ltr' }, querySelectorAll() { return [root]; } };
const context = { document, window: {}, getComputedStyle: () => ({ getPropertyValue: () => '2' }), matchMedia: () => ({ matches: false }), requestAnimationFrame: (callback) => { callback(); return 1; } };
vm.runInNewContext(readFileSync(new URL('../digitalisimo-elements/assets/js/carousel-engine.js', import.meta.url), 'utf8'), context);
assert.equal(root.dataset.digiCarouselReady, '1');
assert.equal(previous.disabled, true);
assert.equal(next.disabled, false);
next.click();
assert.equal(track.position, 1);
assert.equal(next.disabled, true);
assert.equal(previous.disabled, false);
previous.click();
assert.equal(track.position, 0);
assert.equal(previous.disabled, true);
vm.runInNewContext(readFileSync(new URL('../digitalisimo-elements/assets/js/carousel-engine.js', import.meta.url), 'utf8'), context);
assert.equal(next.disabled, false);
console.log('DIGITALÍSIMO Elements: navegación, límites e inicio único del carrusel validados.');
