// Aviso de peso de los vídeos de fondo en el editor (modules/digitalisimo-front/assets/background-video-weight.js,
// Background_Video) en un entorno falso con `vm`: el texto del aviso (umbral del sitio, celular según la política y
// «Reproducir en móvil», imagen de respaldo), que sólo pide el tamaño (HEAD) de vídeos del mismo origen, una vez por
// dirección, que escribe el aviso en el control informativo y lo borra al quitar el vídeo, y que se engancha al abrir
// contenedores y secciones. El archivo compila, cabe en 6 KB y no nombra ninguna URL.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const file = new URL('../digitalisimo-elements/modules/digitalisimo-front/assets/background-video-weight.js', import.meta.url);
const source = readFileSync(file, 'utf8');
assert.doesNotThrow(() => new vm.Script(source, { filename: 'background-video-weight.js' }), 'El script compila.');
assert.ok(statSync(file).size <= 6144, `El script (sólo del editor) cabe en 6 KB (${statSync(file).size} B).`);
assert.ok(!/https?:\/\//.test(source), 'El script no nombra ninguna URL.');

function setup({ mobile = 'element', maxBytes = 2097152, bytes = 3 * 1048576, ok = true } = {}) {
  const fetches = [];
  const actions = {};
  const boxes = [{ textContent: '', className: 'digitalisimo-bg-video-weight' }];
  const listeners = {};
  const timers = [];
  const window = {
    digitalisimoBackgroundVideo: { maxBytes, mobile },
    location: { href: 'https://sitio.test/wp-admin/post.php?post=1&action=elementor', origin: 'https://sitio.test' },
    URL,
    fetch(url, options) { fetches.push([url, options && options.method]); return Promise.resolve({ ok, headers: { get: () => String(bytes) } }); },
    setTimeout(fn) { timers.push(fn); return timers.length; },
    elementor: {
      hooks: { addAction(name, fn) { actions[name] = fn; } },
      channels: { editor: { on(name, fn) { listeners[name] = fn; } } },
    },
  };
  const document = { querySelectorAll: () => boxes };
  vm.runInNewContext(source, { window, document, Promise, Number, Math, Array, String });
  const flush = async () => { while (timers.length) timers.shift()(); for (let i = 0; i < 5; i++) await Promise.resolve(); };
  const model = (settings) => {
    const handlers = [];
    const s = { ...settings, toJSON() { return { ...this }; }, on(events, fn) { handlers.push(fn); }, off() {} };
    return { settings: s, handlers, get: () => s };
  };
  return { window, actions, listeners, boxes, fetches, flush, model };
}

const VIDEO = { background_background: 'video', background_video_link: 'https://sitio.test/wp-content/uploads/fondo.mp4', background_play_on_mobile: 'yes', background_video_fallback: { url: '' } };
const WITH_FALLBACK = { ...VIDEO, background_video_fallback: { url: 'https://sitio.test/wp-content/uploads/respaldo.jpg' } };

// Texto del aviso.
{
  const { window } = setup();
  const message = window.digitalisimoBackgroundVideoMessage;
  assert.equal(message(WITH_FALLBACK, 2 * 1048576, 'element'), '', 'Igual al límite y con respaldo: sin aviso.');
  const only = message(VIDEO, 0, 'element');
  assert.match(only, /Falta la «Imagen de respaldo»/, 'Sin respaldo se avisa aunque no se conozca el peso.');
  assert.doesNotMatch(only, /pesa/, 'Sin peso conocido no se habla del peso.');
  assert.match(message(VIDEO, 1048576, 'element'), /Falta la «Imagen de respaldo»/, 'Sin respaldo se avisa aunque pese poco.');
  assert.match(message(VIDEO, 3e6, 'never'), /También se descarga en el celular/, 'Política «nunca» sin respaldo: el vídeo no se quita y se descarga.');
  const text = message(VIDEO, 8.1 * 1048576, 'element');
  assert.match(text, /pesa 8,1 MB, más que el límite del sitio \(2 MB\)/, 'Dice el peso y el límite.');
  assert.match(text, /También se descarga en el celular/, 'Con «Reproducir en móvil»: se descarga en el celular.');
  assert.match(text, /Falta la «Imagen de respaldo»/, 'Sin respaldo: lo dice.');
  assert.match(message({ ...VIDEO, background_play_on_mobile: '' }, 3e6, 'element'), /En el celular no se descarga/, 'Sin «Reproducir en móvil»: no se descarga.');
  assert.match(message(WITH_FALLBACK, 3e6, 'never'), /En el celular no se descarga/, 'Política «nunca» con respaldo: no se descarga aunque el elemento lo pida.');
  assert.match(message({ ...VIDEO, background_play_on_mobile: '' }, 3e6, 'always'), /También se descarga/, 'Política «siempre»: se descarga.');
  assert.doesNotMatch(message({ ...VIDEO, background_video_fallback: { url: 'x.jpg' } }, 3e6, 'element'), /Falta/, 'Con respaldo: no lo pide.');
  assert.equal(message({ ...VIDEO, background_background: 'classic' }, 9e9, 'element'), '', 'Sin fondo de vídeo: sin aviso.');
}

// Enganches, petición del tamaño y escritura en el control.
{
  const t = setup();
  assert.ok(t.actions['panel/open_editor/container'] && t.actions['panel/open_editor/section'], 'Se engancha a contenedores y secciones.');
  assert.ok(t.listeners['section:activated'], 'Repinta al abrir una sección del panel.');
  const m = t.model(VIDEO);
  t.actions['panel/open_editor/container'](null, m);
  await t.flush();
  assert.deepEqual(t.fetches, [['https://sitio.test/wp-content/uploads/fondo.mp4', 'HEAD']], 'Pide el tamaño con HEAD, una vez.');
  assert.match(t.boxes[0].textContent, /pesa 3 MB/, 'Escribe el aviso en el control.');
  assert.match(t.boxes[0].className, /elementor-panel-alert-warning/, 'Con el estilo de advertencia del panel.');
  t.listeners['section:activated']();
  await t.flush();
  assert.equal(t.fetches.length, 1, 'La misma dirección no se vuelve a pedir.');
  m.settings.background_background = 'classic';
  m.handlers[0]();
  await t.flush();
  assert.equal(t.boxes[0].textContent, '', 'Sin vídeo de fondo, el aviso se borra.');
}
{
  const t = setup();
  t.actions['panel/open_editor/section'](null, t.model({ ...WITH_FALLBACK, background_video_link: 'https://cdn.otro.test/fondo.mp4' }));
  await t.flush();
  assert.equal(t.fetches.length, 0, 'Un vídeo de otro origen no se pide.');
  assert.equal(t.boxes[0].textContent, '', 'Ni se avisa de su peso.');
}
{
  const t = setup();
  t.actions['panel/open_editor/section'](null, t.model({ ...VIDEO, background_video_link: 'https://cdn.otro.test/fondo.mp4' }));
  await t.flush();
  assert.match(t.boxes[0].textContent, /Falta la «Imagen de respaldo»/, 'Pero sí de la falta de imagen de respaldo.');
}
{
  const t = setup({ bytes: 1048576 });
  t.actions['panel/open_editor/container'](null, t.model(WITH_FALLBACK));
  await t.flush();
  assert.equal(t.boxes[0].textContent, '', 'Bajo el límite y con respaldo: sin aviso.');
}
{
  const t = setup({ maxBytes: 10 * 1048576, bytes: 8 * 1048576 });
  t.actions['panel/open_editor/container'](null, t.model(WITH_FALLBACK));
  await t.flush();
  assert.equal(t.boxes[0].textContent, '', 'El umbral es el del sitio (10 MB): 8 MB no avisa.');
}
console.log('background-video-weight.js: ok');
