// I1 · Respaldo antes de actualizar en las pantallas de actualización (assets/pre-update.js): un formulario de
// update-core.php o una actualización por AJAX de un tipo con «Manuales y automáticas» no sale hasta que el servidor
// dice que hay respaldo (si hace falta, lo crea y la página lo hace avanzar); si falla, pregunta: «Aceptar» pide
// «sin respaldo» y sigue, «Cancelar» no envía el formulario (y una petición AJAX sale para que el servidor la rechace
// con su motivo). Los tipos sin «Manuales y automáticas» no esperan, un corte de red al consultar no es un fallo, y
// todo texto del servidor se pinta como texto.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync('digitalisimo-backups/assets/pre-update.js', 'utf8');
assert.ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(script), 'El estado del servidor debe pintarse como texto');

function node(tag) {
  return {
    tag, attributes: {}, children: [], className: '', text: '',
    set textContent(value) { this.text = String(value); },
    get textContent() { return this.text; },
    get firstChild() { return this.children[0] || null; },
    setAttribute(name, value) { this.attributes[name] = String(value); },
    getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; },
    appendChild(child) { this.children.push(child); return child; },
    insertBefore(child) { this.children.unshift(child); return child; },
    querySelector() { return null; },
  };
}
const tick = () => new Promise((resolve) => setImmediate(resolve));
async function settle() { for (let i = 0; i < 40; i++) await tick(); }

function setup({ types = { core: true, plugin: true }, server, confirm = () => true }) {
  const requests = [];
  const submitted = [];
  const listeners = {};
  const body = node('body');
  const confirms = [];
  const original = [];
  function Deferred() {
    let resolve; let reject;
    const promise = new Promise((a, b) => { resolve = a; reject = b; });
    const deferred = { resolve: (value) => { resolve(value); return deferred; }, reject: (value) => { reject(value); return deferred; }, promise: () => promise };
    return deferred;
  }
  function HTMLFormElement() {}
  HTMLFormElement.prototype.submit = function () { submitted.push(this); };
  const wp = { updates: { ajax(action, data) { original.push({ action, data }); return Promise.resolve({ action }); } } };
  const context = {
    window: {
      digitalisimoBackupsPreUpdate: { url: '/wp-admin/admin-ajax.php', nonce: 'nonce-pre', start: 'start', status: 'status', skip: 'skip', types, text: { running: 'Creando…', ready: 'Listo.', ask: 'Falló: %s ¿Seguir?', declined: 'No se actualizó.' } },
      setTimeout: (fn) => { Promise.resolve().then(fn); return 1; },
      confirm: (text) => { confirms.push(text); return confirm(text); },
      wp, jQuery: { Deferred },
    },
    document: {
      body,
      createElement: (tag) => node(tag),
      querySelector: () => null,
      addEventListener: (type, listener) => { listeners[type] = listener; },
    },
    HTMLFormElement, FormData, Promise, Object, String, parseInt,
    fetch: async (url, options) => {
      const fields = Object.fromEntries(options.body.entries());
      assert.equal(fields.nonce, 'nonce-pre', 'Cada petición lleva el nonce');
      requests.push(fields);
      const reply = await server(fields, requests.length);
      if ('network' === reply) throw new TypeError('Failed to fetch');
      return { json: async () => reply };
    },
  };
  runInNewContext(script, context);
  const notice = () => (body.children[0] ? { kind: body.children[0].className, text: body.children[0].firstChild.text } : null);
  return { requests, submitted, listeners, original, confirms, wp, notice };
}
function form(name) {
  const f = node('form');
  f.attributes.name = name;
  return f;
}
function submit(env, target, submitter = { name: 'upgrade', value: 'Actualizar' }) {
  const event = { target, submitter, prevented: false, preventDefault() { this.prevented = true; } };
  env.listeners.submit(event);
  return event;
}
const running = (percent, message = 'Exportando <b>base</b>') => ({ success: true, data: { state: 'running', job: { id: 'job-1', percent, message } } });
const ready = { success: true, data: { state: 'ready' } };
const failed = { success: true, data: { state: 'failed', message: 'Sin espacio' } };

// 1. Formulario de plugins: espera el respaldo (empezar → dos consultas) y después se envía con el botón pulsado.
{
  const env = setup({ server: (f, n) => (n === 1 ? running(10) : n === 2 ? running(60) : ready) });
  const target = form('upgrade-plugins');
  const event = submit(env, target, { name: 'upgrade', value: 'Actualizar plugins' });
  assert.ok(event.prevented, 'El formulario no debe salir antes del respaldo');
  await settle();
  assert.deepEqual(env.requests.map((r) => r.action), ['start', 'status', 'status'], 'Empieza y consulta hasta que está listo');
  assert.equal(env.requests[1].job, 'job-1', 'La consulta lleva el trabajo');
  assert.equal(env.submitted.length, 1, 'Con el respaldo listo, el formulario se envía');
  assert.equal(env.submitted[0], target);
  const hidden = target.children.find((child) => child.name === 'upgrade');
  assert.ok(hidden && hidden.value === 'Actualizar plugins' && hidden.type === 'hidden', 'El botón pulsado viaja como campo oculto');
  assert.equal(env.notice().text, 'Listo.');
  // Ya hay respaldo en esta página: otro envío sale sin preguntar al servidor.
  const again = submit(env, form('upgrade'));
  assert.ok(!again.prevented && env.requests.length === 3, 'Con el respaldo listo no se vuelve a esperar');
}

// 2. El texto del servidor se pinta como texto, con el avance.
{
  let release;
  const env = setup({ server: (f, n) => (n === 1 ? running(42) : new Promise((resolve) => { release = () => resolve(ready); })) });
  submit(env, form('upgrade'));
  await settle();
  assert.equal(env.notice().text, 'Creando… 42 % · Exportando <b>base</b>', 'El avance se muestra como texto');
  release();
  await settle();
  assert.equal(env.submitted.length, 1);
}

// 3. Tipos sin «Manuales y automáticas» (aquí, temas) y formularios ajenos no esperan.
{
  const env = setup({ server: () => { throw new Error('No debía consultar al servidor'); } });
  assert.ok(!submit(env, form('upgrade-themes')).prevented, 'Un tipo sin espera no se retiene');
  assert.ok(!submit(env, form('upgrade-translations')).prevented, 'Las traducciones no se retienen');
  await env.wp.updates.ajax('update-theme', { slug: 'twentytwentysix' });
  await env.wp.updates.ajax('install-plugin', { slug: 'hello' });
  assert.deepEqual(env.original.map((o) => o.action), ['update-theme', 'install-plugin']);
}

// 4. El respaldo falla y se acepta seguir: se pide «sin respaldo» y el formulario sale.
{
  const env = setup({ server: (f) => ('start' === f.action ? running(5) : 'status' === f.action ? failed : { success: true, data: { skip_until: 'x' } }) });
  submit(env, form('upgrade'));
  await settle();
  assert.equal(env.confirms.length, 1, 'Un fallo pregunta una vez');
  assert.ok(env.confirms[0].includes('Sin espacio'), 'La pregunta lleva el motivo');
  assert.deepEqual(env.requests.map((r) => r.action), ['start', 'status', 'skip']);
  assert.equal(env.submitted.length, 1, 'Aceptado, el formulario se envía');
}

// 5. El respaldo falla y se cancela: el formulario no sale; una petición AJAX sale para que el servidor la rechace.
{
  const env = setup({ server: (f) => ('start' === f.action ? failed : ready), confirm: () => false });
  submit(env, form('upgrade-plugins'));
  await settle();
  assert.equal(env.submitted.length, 0, 'Cancelado, el formulario no se envía');
  assert.equal(env.notice().kind.includes('notice-error'), true, 'El aviso queda como error');
  assert.ok(!env.requests.some((r) => 'skip' === r.action), 'Cancelado, no pide «sin respaldo»');
  const reply = env.wp.updates.ajax('update-plugin', { plugin: 'hello/hello.php' });
  await settle();
  assert.equal(env.original.length, 1, 'La actualización por AJAX sale igual y el servidor la rechaza');
  assert.ok(reply && 'function' === typeof reply.then);
}

// 6. AJAX de Plugins (lote): todas esperan al mismo respaldo y salen después, en orden; una sola petición «start».
{
  const env = setup({ server: (f, n) => (n === 1 ? running(1) : ready) });
  const first = env.wp.updates.ajax('update-plugin', { plugin: 'a/a.php' });
  const second = env.wp.updates.ajax('update-plugin', { plugin: 'b/b.php' });
  assert.equal(env.original.length, 0, 'Nada sale antes del respaldo');
  await settle();
  assert.deepEqual(env.original.map((o) => o.data.plugin), ['a/a.php', 'b/b.php'], 'Salen las dos, en orden');
  assert.equal(env.requests.filter((r) => 'start' === r.action).length, 1, 'Un solo respaldo para el lote');
  assert.deepEqual(await first, { action: 'update-plugin' }, 'La promesa devuelve la respuesta de WordPress');
  assert.ok(await second);
}

// 7. Un corte de red al consultar no es un fallo: vuelve a preguntar; muchos seguidos, sí.
{
  const env = setup({ server: (f, n) => (n === 1 ? running(3) : n < 4 ? 'network' : ready) });
  submit(env, form('upgrade'));
  await settle();
  assert.equal(env.confirms.length, 0, 'Dos cortes no preguntan');
  assert.equal(env.submitted.length, 1, 'Tras los cortes, sigue');
  const lost = setup({ server: (f, n) => (n === 1 ? running(3) : 'network'), confirm: () => false });
  submit(lost, form('upgrade'));
  await settle();
  assert.equal(lost.confirms.length, 1, 'Muchos cortes seguidos acaban preguntando');
  assert.equal(lost.submitted.length, 0);
}

// 8. Sin permiso (respuesta de error del servidor): pregunta con su mensaje.
{
  const env = setup({ server: () => ({ success: false, data: { message: 'No autorizado para gestionar respaldos.' } }), confirm: () => false });
  submit(env, form('upgrade'));
  await settle();
  assert.ok(env.confirms[0].includes('No autorizado'), 'El mensaje del servidor llega a la pregunta');
}

// 9. Revisión H7: «Ocultar esta actualización» y «Mostrar de nuevo» (mismo formulario) no esperan un respaldo.
{
  const env = setup({ server: () => { throw new Error('No debía consultar al servidor'); } });
  assert.ok(!submit(env, form('upgrade'), { name: 'dismiss', value: 'Ocultar esta actualización' }).prevented, '«Ocultar» no espera');
  assert.ok(!submit(env, form('upgrade'), { name: 'undismiss', value: 'Mostrar de nuevo' }).prevented, '«Mostrar de nuevo» no espera');
  assert.equal(env.requests.length, 0);
}

// 10. Revisiones H10 y N3: sin event.submitter (navegadores antiguos) cuenta el último botón pulsado del formulario: el de
// «Actualizar» espera y sale con su campo «upgrade»; «Ocultar» no espera; sin saber qué botón, el formulario sale tal cual.
{
  const env = setup({ server: () => ready });
  const target = form('upgrade');
  const click = (name, value, owner = target) => env.listeners.click({ target: { name, value, form: owner, closest() { return this; } } });
  click('upgrade', 'Actualizar a la versión 7.0');
  const event = submit(env, target, null);
  assert.ok(event.prevented, 'Con el botón «Actualizar» pulsado, espera');
  await settle();
  const hidden = target.children.find((child) => child.name === 'upgrade');
  assert.ok(env.submitted.length === 1 && hidden && hidden.value === 'Actualizar a la versión 7.0', 'Sin submitter, el formulario lleva «upgrade» con su valor');
  const other = setup({ server: () => { throw new Error('No debía consultar al servidor'); } });
  const dismiss = form('upgrade');
  other.listeners.click({ target: { name: 'dismiss', value: 'Ocultar esta actualización', form: dismiss, closest() { return this; } } });
  assert.ok(!submit(other, dismiss, null).prevented, 'Sin submitter, «Ocultar» no espera ni sale como actualización');
  assert.ok(!submit(other, form('upgrade'), null).prevented, 'Sin saber qué botón, el formulario sale tal cual');
  other.listeners.click({ target: { name: 'upgrade', value: 'Actualizar', form: form('upgrade-plugins'), closest() { return this; } } });
  assert.ok(!submit(other, dismiss, null).prevented, 'Un botón de otro formulario no cuenta');
  assert.equal(other.requests.length, 0);
}

// 11. Revisión H4: tras «Cancelar», la página no vuelve a pedir el respaldo ni a preguntar; cada actualización por
// AJAX sale y el servidor responde con el motivo; un nuevo envío del formulario también sale.
{
  const env = setup({ server: (f) => ('start' === f.action ? failed : ready), confirm: () => false });
  const first = env.wp.updates.ajax('update-plugin', { plugin: 'a/a.php' });
  await settle();
  env.wp.updates.ajax('update-plugin', { plugin: 'b/b.php' });
  env.wp.updates.ajax('update-plugin', { plugin: 'c/c.php' });
  await settle();
  assert.equal(env.requests.filter((r) => 'start' === r.action).length, 1, 'Un solo «start» en el lote tras «Cancelar»');
  assert.equal(env.confirms.length, 1, 'Una sola pregunta en el lote');
  assert.deepEqual(env.original.map((o) => o.data.plugin), ['a/a.php', 'b/b.php', 'c/c.php'], 'Las tres salen para que el servidor dé el motivo');
  assert.ok(await first);
  assert.ok(!submit(env, form('upgrade-plugins'), { name: 'upgrade', value: 'Actualizar' }).prevented, 'Tras «Cancelar», el formulario sale al servidor');
}

console.log('Respaldo antes de actualizar (pantalla): formularios, AJAX en lote, fallos, «sin respaldo», cortes de red, «Ocultar», sin submitter y «Cancelar» recordado: OK');
