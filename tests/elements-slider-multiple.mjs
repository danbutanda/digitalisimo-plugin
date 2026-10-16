// Varias instancias de Slider Optimizado: cada una arranca cuando sus propias imágenes están listas (las que coinciden,
// en el mismo fotograma; una lenta no retiene a las demás y tiene un tope), la pista se recorta a lo que cubre el
// viewport más un elemento (par con espejo) escalando la duración, los elementos apartados vuelven al crecer la ventana,
// una instancia lejana espera a entrar en vista, y el modo estático y el movimiento reducido no se tocan.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('digitalisimo-elements/assets/js/slider-optimizado.js', 'utf8');
const frames = [];
const starts = [];
let frameNumber = 0;
let intersection;
let resizeHandler;

function slider({ id, top = 100, items = 8, itemWidth = 1000, viewport = 1000, mirror = false, infinite = true, duration = '30s', preparing = true }) {
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
    for (let i = 0; i < items; i++) group.children.push({ img: image, getBoundingClientRect: () => ({ width: itemWidth }) });
    group.appendChild = (item) => { group.children.push(item); };
    group.removeChild = (item) => { group.children.splice(group.children.indexOf(item), 1); };
    group.getBoundingClientRect = () => ({ width: group.children.reduce((sum, item) => sum + item.getBoundingClientRect().width, 0) });
    return group;
  };
  const groups = [makeGroup(), makeGroup()];
  const animation = { currentTime: 1200 };
  const track = { children: groups, style: {}, computed: { animationDuration: duration, animationDelay: '3s' }, getAnimations: () => [animation] };
  const view = { clientWidth: viewport, getBoundingClientRect: () => ({ left: 0, right: viewport, width: viewport }) };
  const classes = new Set([...(preparing ? ['digi-slider--preparing'] : []), ...(infinite ? ['digi-slider--infinite'] : []), ...(mirror ? ['digi-slider--mirror'] : [])]);
  const instance = {
    id, dataset: {}, isConnected: true,
    classList: {
      contains: (name) => classes.has(name),
      remove: (name) => { classes.delete(name); if (name === 'digi-slider--preparing') starts.push({ id, frame: frameNumber }); },
    },
    getBoundingClientRect: () => ({ top, bottom: top + 100, left: 0, right: viewport, width: viewport, height: 100 }),
    querySelector: (selector) => (selector === '.digi-slider__viewport' ? view : selector === '.digi-slider__track' ? track : null),
    querySelectorAll: (selector) => (selector === '.digi-slider--preparing' ? [] : groups.flatMap((group) => group.children.map((item) => item.img))),
    matches: () => true,
  };
  return { instance, image, track, groups, view, finish, animation, kept: () => groups.map((group) => group.children.length) };
}

function nextFrame() { const callbacks = frames.splice(0); frameNumber++; for (const callback of callbacks) callback(); }
async function settle() { for (let i = 0; i < 12; i++) await Promise.resolve(); }
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
    IntersectionObserver: class {
      constructor(callback) { intersection = callback; }
      observe() {}
      unobserve() {}
    },
    Set, Promise, Array, Math, parseFloat,
  };
  ctx.window.IntersectionObserver = ctx.IntersectionObserver;
  return ctx;
}

// Onda: ocho copias de ancho completo con espejo; logos: doce de 100 px; un tercero lento; uno lejano; uno estático.
const wave = slider({ id: 'wave', items: 8, itemWidth: 1000, viewport: 1000, mirror: true });
const logos = slider({ id: 'logos', items: 12, itemWidth: 100, viewport: 1000 });
const slow = slider({ id: 'slow', items: 4, itemWidth: 300, viewport: 1000 });
const below = slider({ id: 'below', top: 3000 });
const fixed = slider({ id: 'static', preparing: false });
vm.runInNewContext(source, context([wave, logos, slow, below, fixed]));
assert.equal(frames.length, 1, 'Las instancias visibles se agrupan en un solo fotograma de preparación.');
nextFrame();
assert.equal(wave.image.loading, 'eager');
assert.equal(below.image.loading, 'lazy', 'Un slider lejano no descarga imágenes antes de acercarse.');

// Recorte de la pista al preparar: cubre el viewport más un elemento, par con espejo, y escala la duración.
assert.deepEqual(wave.kept(), [2, 2], 'La onda de ocho copias queda en dos por grupo (viewport más uno, par con espejo).');
assert.equal(wave.track.style.animationDuration, '7.500s', 'La duración se escala en la proporción recortada (30 s × 2/8).');
assert.deepEqual(logos.kept(), [11, 11], 'Doce logos de 100 px en 1000 px: diez cubren el viewport, más uno.');
assert.equal(logos.track.style.animationDuration, '27.500s');
assert.deepEqual(slow.kept(), [4, 4], 'Cuatro de 300 px ya cubren 1000 px: se conservan todos.');
assert.equal(slow.track.style.animationDuration, '');

// Arranque desacoplado: las que terminan juntas arrancan en el mismo fotograma; la lenta no las retiene.
wave.finish(); logos.finish();
await settle();
assert.equal(starts.length, 0, 'Nadie arranca antes de su fotograma de liberación.');
nextFrame();
assert.deepEqual(starts.map((value) => value.id).sort(), ['logos', 'wave'], 'Las dos listas arrancan sin esperar a la lenta.');
assert.equal(new Set(starts.map((value) => value.frame)).size, 1, 'Y lo hacen en el mismo fotograma.');
assert.equal(wave.animation.currentTime, 0, 'Liberada dentro del retraso de CSS, la animación parte del inicio.');
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
assert.equal(starts.filter((value) => value.id === 'static').length, 0, 'El slider estático no se toca.');

// Cambio de tamaño: al crecer la ventana vuelven los elementos apartados; al encoger se apartan más.
logos.view.clientWidth = 2000;
resizeHandler();
await new Promise((resolve) => setTimeout(resolve, 300));
assert.deepEqual(logos.kept(), [12, 12], 'Con 2000 px hacen falta los doce logos.');
assert.equal(logos.track.style.animationDuration, '', 'Sin recorte, la duración vuelve a la configurada.');
logos.view.clientWidth = 500;
resizeHandler();
await new Promise((resolve) => setTimeout(resolve, 300));
assert.deepEqual(logos.kept(), [6, 6], 'Con 500 px bastan cinco más uno.');
assert.equal(logos.track.style.animationDuration, '15.000s');
wave.view.clientWidth = 1000;
resizeHandler();
await new Promise((resolve) => setTimeout(resolve, 300));
assert.deepEqual(wave.kept(), [2, 2], 'Un cambio sin efecto no altera la onda.');

// Tope: una imagen que nunca termina no congela el slider.
frames.length = 0; starts.length = 0;
const stuck = slider({ id: 'stuck', items: 4, itemWidth: 300 });
vm.runInNewContext(source, context([stuck], { timeout: 40 }));
nextFrame();
await new Promise((resolve) => setTimeout(resolve, 80));
await settle();
nextFrame();
assert.equal(starts.filter((value) => value.id === 'stuck').length, 1, 'Pasado el tope, el slider arranca aunque falte una imagen.');

// Movimiento reducido: sin animación ni recorte, con la carga nativa de las imágenes.
const reduced = slider({ id: 'reduced' });
vm.runInNewContext(source, context([reduced], { reduced: true }));
assert.equal(reduced.instance.classList.contains('digi-slider--preparing'), false, 'Movimiento reducido muestra el slider sin esperar imágenes ni animación.');
assert.equal(reduced.image.loading, 'lazy', 'Movimiento reducido conserva la carga nativa de las imágenes.');
assert.deepEqual(reduced.kept(), [8, 8], 'Movimiento reducido no recorta: todo el contenido queda accesible.');

console.log('Slider Optimizado: arranque desacoplado con tope, pista recortada al viewport con velocidad constante, reposición al crecer, instancia lejana, estático y movimiento reducido validados.');
