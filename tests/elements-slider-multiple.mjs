// Varias instancias de Slider Optimizado: cada una arranca cuando sus propias imágenes están listas (las que coinciden,
// en el mismo fotograma; una lenta no retiene a las demás y tiene un tope), la pista se recorta sólo en repeticiones
// (nunca por debajo de las imágenes distintas, en múltiplos de la secuencia, par con espejo) escalando la duración por
// una variable CSS, los elementos apartados vuelven al crecer la ventana, el ancho natural no se recorta, sin la hoja de
// estilos aplicada se reintenta al arrancar, una instancia lejana queda en pausa real hasta entrar en vista, y el modo
// estático y el movimiento reducido no se tocan.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('digitalisimo-elements/assets/js/slider-optimizado.js', 'utf8');
const frames = [];
const starts = [];
let frameNumber = 0;
let intersection;
let resizeHandler;

function slider({ id, top = 100, items = 8, itemWidth = 1000, viewport = 1000, sources = 1, mirror = false, infinite = true, natural = false, preparing = true, animationName = 'digi-slider-left' }) {
  // Como en el navegador, cada llamada a decode() devuelve su propia promesa y todas resuelven al decodificar.
  const resolvers = [];
  const image = {
    complete: false, naturalWidth: 0, loading: 'lazy',
    getBoundingClientRect: () => ({ left: 0, right: 100, width: 100 }),
    decode: () => new Promise((resolve) => { resolvers.push(resolve); }),
  };
  const finish = () => { image.complete = true; image.naturalWidth = 100; resolvers.splice(0).forEach((resolve) => resolve()); };
  const makeGroup = () => {
    const group = { children: [], classList: { contains: (name) => name === 'digi-slider__group' } };
    for (let i = 0; i < items; i++) group.children.push({ index: i, img: image, getBoundingClientRect: () => ({ width: itemWidth }) });
    group.appendChild = (item) => { group.children.push(item); };
    group.removeChild = (item) => { group.children.splice(group.children.indexOf(item), 1); };
    group.getBoundingClientRect = () => ({ width: group.children.reduce((sum, item) => sum + item.getBoundingClientRect().width, 0) });
    return group;
  };
  const groups = [makeGroup(), makeGroup()];
  const props = {};
  const style = { animationPlayState: '', setProperty: (k, v) => { props[k] = v; }, removeProperty: (k) => { delete props[k]; }, getPropertyValue: (k) => props[k] || '' };
  const animation = { currentTime: 1200 };
  const track = { children: groups, style, computed: { animationDuration: '30s', animationDelay: '3s', animationName }, getAnimations: () => [animation] };
  const view = { clientWidth: viewport, getBoundingClientRect: () => ({ left: 0, right: viewport, width: viewport }) };
  const classes = new Set([...(preparing ? ['digi-slider--preparing'] : []), ...(infinite ? ['digi-slider--infinite'] : []), ...(mirror ? ['digi-slider--mirror'] : []), ...(natural ? ['digi-slider--natural'] : [])]);
  const instance = {
    id, dataset: { digiSources: String(sources) }, isConnected: true,
    classList: {
      contains: (name) => classes.has(name),
      remove: (name) => { classes.delete(name); if (name === 'digi-slider--preparing') starts.push({ id, frame: frameNumber }); },
    },
    getBoundingClientRect: () => ({ top, bottom: top + 100, left: 0, right: viewport, width: viewport, height: 100 }),
    querySelector: (selector) => (selector === '.digi-slider__viewport' ? view : selector === '.digi-slider__track' ? track : selector === '.digi-slider__item' ? groups[0].children[0] : null),
    querySelectorAll: (selector) => (selector === '.digi-slider--preparing' ? [] : groups.flatMap((group) => group.children.map((item) => item.img))),
    matches: () => true,
  };
  return { instance, image, track, groups, view, finish, animation, kept: () => groups.map((group) => group.children.length), order: () => groups[0].children.map((item) => item.index), scale: () => style.getPropertyValue('--digi-fit-scale') };
}

function nextFrame() { const callbacks = frames.splice(0); frameNumber++; for (const callback of callbacks) callback(); }
async function settle() { for (let i = 0; i < 12; i++) await Promise.resolve(); }
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
function context(all, { reduced = false, timeout = 4000 } = {}) {
  const ctx = {
    document: { readyState: 'complete', querySelectorAll: () => all.map((value) => value.instance) },
    window: {
      innerWidth: 1200, innerHeight: 800, DIGI_SLIDER_TIMEOUT: timeout,
      requestAnimationFrame: (callback) => { frames.push(callback); return frames.length; },
      setTimeout, clearTimeout,
      matchMedia: () => ({ matches: reduced }),
      getComputedStyle: (track) => track.computed,
      addEventListener: (name, handler) => { if (name === 'resize') resizeHandler = handler; },
      elementorFrontend: { hooks: { addAction: () => {} } },
    },
    IntersectionObserver: class { constructor(callback) { intersection = callback; } observe() {} unobserve() {} },
    ResizeObserver: class { observe() {} unobserve() {} },
    Set, Promise, Array, Math, parseFloat, parseInt,
  };
  ctx.window.IntersectionObserver = ctx.IntersectionObserver;
  ctx.window.ResizeObserver = ctx.ResizeObserver;
  return ctx;
}

// Onda: ocho copias de una imagen con espejo; logos: doce distintos; tres logos repetidos hasta diez; uno lento; ancho natural;
// uno sin la hoja de estilos aplicada; uno lejano; uno estático.
const wave = slider({ id: 'wave', items: 8, itemWidth: 1000, viewport: 1000, mirror: true });
const logos = slider({ id: 'logos', items: 12, itemWidth: 100, viewport: 1000, sources: 12 });
const three = slider({ id: 'three', items: 10, itemWidth: 100, viewport: 450, sources: 3 });
const slow = slider({ id: 'slow', items: 4, itemWidth: 300, viewport: 1000, sources: 4 });
const natural = slider({ id: 'natural', items: 10, itemWidth: 300, viewport: 1000, natural: true });
const unstyled = slider({ id: 'unstyled', items: 8, itemWidth: 1000, viewport: 1000, animationName: 'none' });
const below = slider({ id: 'below', top: 3000 });
const fixed = slider({ id: 'static', preparing: false });
vm.runInNewContext(source, context([wave, logos, three, slow, natural, unstyled, below, fixed]));
assert.equal(frames.length, 1, 'Las instancias visibles se agrupan en un solo fotograma de preparación.');
assert.equal(below.track.style.animationPlayState, 'paused', 'La instancia lejana queda en pausa real hasta prepararse.');
nextFrame();
assert.equal(wave.image.loading, 'eager');
assert.equal(below.image.loading, 'lazy', 'Un slider lejano no descarga imágenes antes de acercarse.');

// Recorte al preparar: sólo repeticiones, nunca por debajo de las imágenes distintas, en múltiplos de la secuencia.
assert.deepEqual(wave.kept(), [2, 2], 'La onda de ocho copias queda en dos por grupo (viewport más uno, par con espejo).');
assert.equal(wave.scale(), '0.2500', 'La duración se escala por variable en la proporción recortada (2/8).');
assert.deepEqual(logos.kept(), [12, 12], 'Doce logotipos distintos se conservan todos aunque diez cubran el viewport.');
assert.equal(logos.scale(), '', 'Sin recorte no hay escala.');
assert.deepEqual(three.kept(), [6, 6], 'Tres logos repetidos: cinco cubren 450 px, más uno son seis, múltiplo de tres.');
assert.deepEqual(three.order(), [0, 1, 2, 3, 4, 5], 'Se conserva el orden de la secuencia.');
assert.equal(three.scale(), '0.6000');
assert.deepEqual(slow.kept(), [4, 4], 'Cuatro de 300 px ya cubren 1000 px: se conservan todos.');
assert.deepEqual(natural.kept(), [10, 10], 'Con ancho natural no se recorta.');
assert.equal(natural.scale(), '');
assert.deepEqual(unstyled.kept(), [8, 8], 'Sin la hoja de estilos aplicada no se recorta todavía.');

// Arranque desacoplado: las que terminan juntas arrancan en el mismo fotograma; la lenta no las retiene.
wave.finish(); logos.finish(); three.finish(); natural.finish();
unstyled.track.computed.animationName = 'digi-slider-left';
unstyled.finish();
await settle();
assert.equal(starts.length, 0, 'Nadie arranca antes de su fotograma de liberación.');
nextFrame();
assert.deepEqual(starts.map((value) => value.id).sort(), ['logos', 'natural', 'three', 'unstyled', 'wave'], 'Las listas arrancan sin esperar a la lenta.');
assert.equal(new Set(starts.map((value) => value.frame)).size, 1, 'Y lo hacen en el mismo fotograma.');
assert.equal(wave.animation.currentTime, 0, 'Liberada dentro del retraso de CSS, la animación parte del inicio.');
assert.deepEqual(unstyled.kept(), [2, 2], 'Con la hoja ya aplicada, el recorte se reintenta al arrancar.');
slow.animation.currentTime = 4200;
slow.finish();
await settle();
nextFrame();
assert.equal(starts.filter((value) => value.id === 'slow').length, 1, 'La lenta arranca al terminar sus imágenes.');
assert.equal(slow.animation.currentTime, 1200, 'Liberada cuando el retraso ya venció, la animación sigue donde estaba (4.2 s menos 3 s).');
assert.equal(below.instance.classList.contains('digi-slider--preparing'), true);
intersection([{ target: below.instance, isIntersecting: true }]);
nextFrame();
below.finish();
await settle();
nextFrame();
assert.equal(starts.filter((value) => value.id === 'below').length, 1, 'La instancia lejana arranca al entrar en el viewport.');
assert.equal(below.track.style.animationPlayState, '', 'Y deja la pausa real.');
assert.equal(starts.filter((value) => value.id === 'static').length, 0, 'El slider estático no se toca.');

// Cambio de tamaño: al encoger se apartan repeticiones; al crecer vuelven; las imágenes distintas nunca se van.
three.view.clientWidth = 200;
resizeHandler();
await wait(250);
assert.deepEqual(three.kept(), [3, 3], 'Con 200 px bastan dos más uno: una secuencia entera.');
assert.equal(three.scale(), '0.3000');
three.view.clientWidth = 1000;
resizeHandler();
await wait(250);
assert.deepEqual(three.kept(), [10, 10], 'Con 1000 px hacen falta once: vuelven las diez.');
assert.equal(three.scale(), '', 'Sin recorte, la escala desaparece.');
logos.view.clientWidth = 300;
resizeHandler();
await wait(250);
assert.deepEqual(logos.kept(), [12, 12], 'Doce logotipos distintos siguen enteros en 300 px.');
wave.view.clientWidth = 1000;
resizeHandler();
await wait(250);
assert.deepEqual(wave.kept(), [2, 2], 'Un cambio sin efecto no altera la onda.');

// Tope: una imagen que nunca termina no congela el slider.
frames.length = 0; starts.length = 0;
const stuck = slider({ id: 'stuck', items: 4, itemWidth: 300, sources: 4 });
vm.runInNewContext(source, context([stuck], { timeout: 40 }));
nextFrame();
await wait(80);
await settle();
nextFrame();
assert.equal(starts.filter((value) => value.id === 'stuck').length, 1, 'Pasado el tope, el slider arranca aunque falte una imagen.');

// Movimiento reducido: sin animación ni recorte, con la carga nativa de las imágenes.
const reduced = slider({ id: 'reduced' });
vm.runInNewContext(source, context([reduced], { reduced: true }));
assert.equal(reduced.instance.classList.contains('digi-slider--preparing'), false, 'Movimiento reducido muestra el slider sin esperar imágenes ni animación.');
assert.equal(reduced.image.loading, 'lazy', 'Movimiento reducido conserva la carga nativa de las imágenes.');
assert.deepEqual(reduced.kept(), [8, 8], 'Movimiento reducido no recorta: todo el contenido queda accesible.');

console.log('Slider Optimizado: arranque desacoplado con tope, recorte sólo de repeticiones con velocidad constante, reposición al crecer, ancho natural, hoja de estilos tardía, instancia lejana en pausa, estático y movimiento reducido validados.');
