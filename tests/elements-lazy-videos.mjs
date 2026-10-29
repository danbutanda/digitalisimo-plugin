// Script de la carga diferida de vídeos (modules/digitalisimo-front/assets/lazy-videos.js, `lazy_videos`, Fase 3) en un
// entorno falso con `vm`: `document`, `window`, `IntersectionObserver` y `MutationObserver`. Con el observador, los
// vídeos marcados se observan a una pantalla de distancia y sólo al acercarse recuperan `autoplay` y `preload="auto"` y
// se reproducen una vez; sin observador, se reproducen al cargar; los vídeos que llegan después (popups, bucles) se
// observan por `MutationObserver`; un `play()` rechazado no rompe nada; el archivo compila, cabe en 1,5 KB y no usa jQuery.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const file = new URL('../digitalisimo-elements/modules/digitalisimo-front/assets/lazy-videos.js', import.meta.url);
const source = readFileSync(file, 'utf8');
assert.doesNotThrow(() => new vm.Script(source, { filename: 'lazy-videos.js' }), 'El script compila.');
assert.ok(statSync(file).size <= 1536, `El script cabe en 1,5 KB (${statSync(file).size} B).`);
assert.ok(!/jQuery|\$\(/.test(source), 'El script no usa jQuery.');
assert.ok(/https?:\/\//.test(source) === false, 'El script no nombra ninguna URL.');

function video(reject = false) {
  return { preload: 'none', autoplay: false, plays: 0, play() { this.plays++; return reject ? Promise.reject(new Error('NotAllowed')) : Promise.resolve(); } };
}

function setup({ observer = true, videos = [video(), video()], mutation = true } = {}) {
  const observers = [];
  const mutations = [];
  const window = {};
  if (observer) {
    window.IntersectionObserver = class {
      constructor(callback, options) { this.callback = callback; this.options = options; this.targets = []; this.unobserved = []; observers.push(this); }
      observe(el) { this.targets.push(el); }
      unobserve(el) { this.unobserved.push(el); }
      fire(target, isIntersecting = true) { this.callback([{ target, isIntersecting, intersectionRatio: isIntersecting ? 0.1 : 0 }]); }
    };
  }
  if (mutation) {
    window.MutationObserver = class { constructor(callback) { this.callback = callback; mutations.push(this); } observe() {} };
  }
  const document = {
    body: {},
    list: videos.slice(),
    querySelectorAll(sel) { assert.equal(sel, 'video[data-digi-lazy-video]'); return this.list.slice(); },
  };
  const context = vm.createContext({ document, window, console });
  vm.runInContext(source, context);
  return { document, window, observers, mutations };
}

// 1. Con observador: nada se reproduce al cargar; a una pantalla de distancia; al acercarse, una sola vez.
{
  const v = [video(), video()];
  const { observers } = setup({ videos: v });
  assert.equal(observers.length, 1, 'Un solo observador.');
  const io = observers[0];
  assert.equal(io.options.rootMargin, '100% 0px', 'El margen es una pantalla.');
  assert.ok(io.targets.length === 2 && io.targets[0] === v[0] && io.targets[1] === v[1], 'Observa los dos vídeos marcados.');
  assert.ok(v[0].plays === 0 && v[1].plays === 0 && v[0].preload === 'none' && v[0].autoplay === false, 'Al cargar, nada se reproduce ni cambia.');
  io.fire(v[0], false);
  assert.ok(v[0].plays === 0, 'Fuera del margen, nada.');
  io.fire(v[0], true);
  assert.ok(v[0].plays === 1 && v[0].preload === 'auto' && v[0].autoplay === true, 'Al acercarse: preload auto, autoplay y play().');
  assert.ok(io.unobserved.length === 1 && io.unobserved[0] === v[0], 'Deja de observarlo.');
  io.fire(v[0], true);
  assert.ok(v[0].plays === 1, 'Una segunda intersección no repite el play().');
  assert.ok(v[1].plays === 0 && v[1].preload === 'none', 'El otro vídeo sigue esperando.');
}

// 2. Sin IntersectionObserver: se reproducen al cargar.
{
  const v = [video(), video(true)];
  const { observers } = setup({ observer: false, videos: v });
  assert.equal(observers.length, 0);
  assert.ok(v[0].plays === 1 && v[0].autoplay === true && v[0].preload === 'auto' && v[1].plays === 1, 'Sin observador, los dos se reproducen al cargar; un play() rechazado no rompe el resto.');
}

// 3. Los vídeos que llegan después se observan (MutationObserver), y los ya vistos no se repiten.
{
  const v = [video()];
  const { document, observers, mutations } = setup({ videos: v });
  assert.equal(mutations.length, 1, 'Observa el DOM para los nodos nuevos.');
  const late = video();
  document.list.push(late);
  mutations[0].callback([]);
  assert.ok(observers[0].targets.length === 2 && observers[0].targets[1] === late, 'El vídeo nuevo queda observado.');
  mutations[0].callback([]);
  assert.equal(observers[0].targets.length, 2, 'Un segundo barrido no vuelve a observar los mismos.');
  observers[0].fire(late, true);
  assert.ok(late.plays === 1 && v[0].plays === 0, 'El nuevo arranca al acercarse; el primero sigue esperando.');
}

// 4. Un play() rechazado (autoplay bloqueado) no lanza.
{
  const v = [video(true)];
  const { observers } = setup({ videos: v });
  assert.doesNotThrow(() => observers[0].fire(v[0], true));
  assert.equal(v[0].plays, 1);
}

console.log('DIGITALÍSIMO Elements: script de la carga diferida de vídeos validado (observador, sin observador, nodos nuevos).');
