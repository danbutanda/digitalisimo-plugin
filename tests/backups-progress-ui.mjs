// El panel de avance de la pantalla Backups (assets/manual-progress.js) es uno solo para un respaldo y una restauración, a
// todo lo ancho (docs/BACKUPS.md, «Pantalla»). Con un DOM mínimo y respuestas del servidor simuladas comprueba que:
//   P1  un respaldo pinta título, porcentaje, la barra (role=progressbar con aria-valuenow), sus fases con la actual
//       marcada, el paso dentro de la fase y el tiempo; al terminar bien recarga la lista como siempre;
//   P2  una restauración seguida desde que se carga la página (data-view y su testigo) avanza fase por fase con su tick
//       por testigo, deja «Cancelar» sólo en las fases que se pueden cancelar, oculta la línea de la franja que repetía
//       su estado, bloquea «Respaldar ahora» mientras dura y al terminar enseña el resultado (qué se restauró, avisos,
//       lo retirado y «Volver a la copia previa») sin recargar;
//   P3  restaurar por partes dice qué se restaura;
//   P4  el sondeo sobrevive al 503 del mantenimiento (lo dice y consulta cada 30 s), a un corte de red y a un 502 (lo dice;
//       tras 5 fallos, qué hacer) y la nota se va al volver;
//   P5  un nonce que dejó de valer con el cambio de tablas («-1») pasa al tick por testigo, que trae nonces nuevos: los
//       usan la cola y «Volver a la copia previa»;
//   P6  recargar la página a mitad de la restauración vuelve a enseñar el panel en su fase y sigue;
//   P7  una restauración que falla a mitad del cambio sigue en su vuelta a la copia previa y termina diciendo que el sitio
//       volvió a como estaba y por qué falló;
//   P8  si la vuelta también falla, el panel ofrece «Volver a la copia previa»;
//   P9  sin sesión ni testigo, lo dice, ofrece iniciar sesión y deja de consultar; terminada por otro proceso sin sesión,
//       el estado final con «Iniciar sesión»;
//   P10 un punto incremental: la reconstrucción y después la restauración, en el mismo panel;
//   P11 al confirmar «Restaurar» el panel dice ya que empieza (barra indeterminada, sin aria-valuenow);
//   P12 cada identificador que usa el script está en el marcado de PHP (class-backups-progress.php) y nada se pinta como HTML.
// Y lo de la revisión adversarial (cada caso falla con 34c0fae9):
//   P13 una restauración que empezó fuera de esta pestaña (otra pestaña, REST, WP-CLI) la toma el panel grande, en el
//       sondeo de la cola o, sin trabajos, en la consulta del estado cada 30 s; tras el cambio sigue con el testigo y los
//       nonces nuevos; ya en mantenimiento, lo dice (M3);
//   P14 terminada por otro proceso, la cola vuelve al tick con nonce (B3, J10); sin sesión, para;
//   P15 la barra nunca retrocede: el punto incremental ocupa el 0–20 % y su restauración el resto; una fase que vuelve
//       atrás se dice (B4);
//   P16 la vista mínima de una que falló marca su fase, la de una vuelta lo dice y un fallo no baja la barra (B5, J20);
//   P17 «Cancelar» sólo con sesión de gestión y con el sondeo bien (B2);  P18 «Respaldar ahora» con el nonce nuevo (B1);
//   P19 con 503 no aconseja recargar (B6);  P20 «Volver a la copia previa» con las partes restauradas y, entera, el aviso
//       de lo que se pierde (M1);  P21 región y regiones vivas siempre presentes (B7);  P22 doble clic (B10);
//   P23 «finished» con la restauración del punto reconstruido (J13).
// Y lo de la verificación de los arreglos (fallan con 99a5a73f):
//   P24 sólo el 503 de la puerta de Backups (su cabecera) es de una restauración, otro es «el servidor no responde»; una
//       restauración de hace días nunca se enseña como recién terminada (N2);  P25 «Respaldar ahora» con la consulta del
//       estado en vuelo no pierde su primer paso (N3);  P26 la consulta cada 30 s, sólo a la vista y ligera (N6);
//   P27 «Restaurar» vuelve a estar disponible a los 60 s si la navegación se cancela y a los 40 s dice qué hacer (N7);
//   P28 la nota se ve aunque el marcado la traiga oculta.
// Uso: node tests/backups-progress-ui.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync('digitalisimo-backups/assets/manual-progress.js', 'utf8');
const markup = readFileSync('digitalisimo-backups/includes/class-backups-progress.php', 'utf8');

/* ------------------------------------------------------------------ DOM mínimo con selectores sencillos */
class Text { constructor(text) { this.text = String(text); this.parentNode = null; this.children = []; } get textContent() { return this.text; } set textContent(v) { this.text = String(v); } }
class El {
  constructor(tag, attributes = {}) {
    this.tagName = tag.toUpperCase(); this.attributes = {}; this.children = []; this.parentNode = null; this.listeners = {};
    this.hidden = false; this.disabled = false; this.value = ''; this.style = {}; this.text = '';
    for (const [name, value] of Object.entries(attributes)) this.setAttribute(name, value);
  }
  get id() { return this.attributes.id || ''; }
  setAttribute(name, value) { this.attributes[name] = String(value); if (name === 'hidden') this.hidden = true; if (name === 'value') this.value = String(value); }
  getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; }
  hasAttribute(name) { return name in this.attributes; }
  removeAttribute(name) { delete this.attributes[name]; }
  appendChild(child) { if (child.parentNode) child.parentNode.removeChild(child); child.parentNode = this; this.children.push(child); return child; }
  insertBefore(child, ref) { const at = this.children.indexOf(ref); if (at < 0) return this.appendChild(child); child.parentNode = this; this.children.splice(at, 0, child); return child; }
  removeChild(child) { this.children = this.children.filter((item) => item !== child); child.parentNode = null; return child; }
  get firstChild() { return this.children[0] || null; }
  set textContent(value) { for (const child of this.children) child.parentNode = null; this.children = []; this.text = String(value); }
  get textContent() { return this.text + this.children.map((child) => child.textContent).join(''); }
  addEventListener(type, listener) { (this.listeners[type] ||= []).push(listener); }
  closest(selector) { for (let node = this; node instanceof El; node = node.parentNode) if (matches(node, selector)) return node; return null; }
  querySelectorAll(selector) { const out = []; const walk = (node) => { for (const child of node.children) if (child instanceof El) { if (matches(child, selector, this)) out.push(child); walk(child); } }; walk(this); return out; }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  scrollIntoView() { this.scrolled = true; }
}
function compound(node, part) {
  const re = /^([a-z0-9]+)?((?:#[\w-]+|\.[\w-]+|\[[\w-]+(?:="[^"]*")?\]|:not\(\[[\w-]+\]\))*)$/i;
  const m = part.match(re);
  if (!m) throw new Error('Selector no admitido en la prueba: ' + part);
  if (m[1] && node.tagName !== m[1].toUpperCase()) return false;
  for (const [token] of m[2].matchAll(/#[\w-]+|\.[\w-]+|\[[\w-]+(?:="[^"]*")?\]|:not\(\[[\w-]+\]\)/g)) {
    if (token[0] === '#' && node.id !== token.slice(1)) return false;
    if (token[0] === '.' && !(' ' + (node.getAttribute('class') || '') + ' ').includes(' ' + token.slice(1) + ' ')) return false;
    if (token[0] === '[') { const [, name, value] = token.match(/^\[([\w-]+)(?:="([^"]*)")?\]$/); if (!node.hasAttribute(name) || (value !== undefined && node.getAttribute(name) !== value)) return false; }
    if (token.startsWith(':not')) { const name = token.match(/\[([\w-]+)\]/)[1]; if (node.hasAttribute(name)) return false; }
  }
  return true;
}
function matches(node, selector, scope = null) {
  return selector.split(',').some((alternative) => {
    const parts = alternative.trim().split(/\s+/);
    if (!compound(node, parts[parts.length - 1])) return false;
    let at = node.parentNode;
    for (let i = parts.length - 2; i >= 0; i--) { while (at instanceof El && at !== scope && !compound(at, parts[i])) at = at.parentNode; if (!(at instanceof El) || at === scope) return false; at = at.parentNode; }
    return true;
  });
}
const flush = () => new Promise((resolve) => setImmediate(resolve));
/* El 503 de la página de mantenimiento de una restauración de Backups (con la cabecera de su puerta) y otro cualquiera. */
const GATE = () => ({ status: 503, body: '<html>Mantenimiento</html>', headers: { 'X-Digitalisimo-Backups-Restore': 'maintenance' } });
const OTHER503 = () => ({ status: 503, body: '<html>Briefly unavailable for scheduled maintenance. Check back in a minute.</html>' });
const settle = async () => { for (let i = 0; i < 8; i++) await flush(); };

/* ------------------------------------------------------------------ La pantalla */
const IDS = ['title', 'percent', 'what', 'fill', 'ticks', 'phases', 'time', 'note', 'result', 'cancel', 'reload', 'safety', 'login', 'rollback', 'help', 'go', 'resume', 'import'];
/* $sections: secciones plegadas de otra pestaña con su hueco para el panel ([id de la sección, nombre del hueco]). */
function screen({ view = null, token = '', strip = false, card = true, active = false, visibility, sections = [], href = '' } = {}) {
  const document = new El('html');
  const body = document.appendChild(new El('body'));
  const wrap = body.appendChild(new El('div', { class: 'wrap digitalisimo-backups' }));
  let stripNode = null;
  if (strip) {
    stripNode = wrap.appendChild(new El('div', { class: 'notice notice-error digitalisimo-backups-strip' }));
    const ul = stripNode.appendChild(new El('ul'));
    ul.appendChild(new El('li', { class: 'digitalisimo-backups-restore-state is-error' })).textContent = 'Restauración en curso.';
  }
  const home = wrap.appendChild(new El('section', { class: 'digitalisimo-backups-card digitalisimo-backups-home' }));
  const form = home.appendChild(new El('form', { id: 'digitalisimo-backups-manual-form' }));
  const select = form.appendChild(new El('select', { name: 'digitalisimo_backup_destination' }));
  select.value = 'local';
  const submit = form.appendChild(new El('button', { class: 'button button-primary' }));
  const panel = home.appendChild(new El('div', { id: 'digitalisimo-backups-manual-progress', class: 'digitalisimo-backups-manual-progress digitalisimo-backups-progress', hidden: '', 'data-login': 'https://example.test/wp-login.php?redirect_to=backups' }));
  if (view) panel.setAttribute('data-view', JSON.stringify(view));
  const P = 'digitalisimo-backups-progress-';
  panel.appendChild(new El('h2', { id: P + 'title' }));
  panel.appendChild(new El('span', { id: P + 'percent', 'aria-hidden': 'true' }));
  panel.appendChild(new El('p', { id: P + 'what' }));
  const bar = panel.appendChild(new El('div', { id: 'digitalisimo-backups-manual-bar', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': '0' }));
  bar.appendChild(new El('span', { id: P + 'fill' }));
  bar.appendChild(new El('span', { id: P + 'ticks' }));
  panel.appendChild(new El('ol', { id: P + 'phases' }));
  panel.appendChild(new El('p', { id: 'digitalisimo-backups-manual-message', role: 'status', 'aria-live': 'polite' }));
  panel.appendChild(new El('p', { id: 'digitalisimo-backups-manual-detail' }));
  panel.appendChild(new El('p', { id: P + 'time' }));
  panel.appendChild(new El('p', { id: P + 'note', role: 'status', 'aria-live': 'polite' }));
  panel.appendChild(new El('div', { id: P + 'result', 'aria-live': 'polite' }));
  const actions = panel.appendChild(new El('div'));
  actions.appendChild(new El('button', { id: P + 'cancel', type: 'button', hidden: '' }));
  actions.appendChild(new El('button', { id: P + 'reload', type: 'button', hidden: '' }));
  actions.appendChild(new El('a', { id: P + 'safety', href: 'https://example.test/wp-admin/admin.php?page=digitalisimo-backups', hidden: '' }));
  actions.appendChild(new El('a', { id: P + 'login', href: 'https://example.test/wp-login.php', hidden: '' }));
  actions.appendChild(new El('a', { id: P + 'go', href: 'https://example.test/wp-admin/admin.php?page=digitalisimo-backups', hidden: '' }));
  actions.appendChild(new El('button', { id: P + 'resume', type: 'button', hidden: '' }));
  const importForm = actions.appendChild(new El('form', { id: P + 'import', hidden: '' }));
  importForm.appendChild(new El('input', { type: 'hidden', name: 'backuply_file', value: '' }));
  const rollback = actions.appendChild(new El('form', { id: P + 'rollback', hidden: '' }));
  rollback.appendChild(new El('input', { type: 'hidden', name: '_wpnonce', value: 'nonce-restore-vieja' }));
  rollback.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_restore' }));
  rollback.appendChild(new El('input', { type: 'hidden', name: 'rollback_retry', value: '1' }));
  panel.appendChild(new El('details', { id: P + 'help', hidden: '' }));
  let cardNode = null;
  if (card) {
    cardNode = wrap.appendChild(new El('section', { id: 'digitalisimo-backups-jobs', 'data-active': active ? '1' : '0', 'data-hide-idle': '1' }));
    cardNode.hidden = !active;
    cardNode.appendChild(new El('div', { id: 'digitalisimo-backups-jobs-list' }));
  }
  const slots = {};
  for (const [section, slot] of sections) {
    const details = wrap.appendChild(new El('details', { class: 'digitalisimo-backups-section', id: 'digitalisimo-backups-section-' + section }));
    slots[slot] = details.appendChild(new El('div', { class: 'digitalisimo-backups-progress-slot', 'data-progress-slot': slot }));
    slots[slot].section = details;
  }
  const byId = (id) => { const found = document.querySelectorAll('#' + id); return found[0] || null; };
  const own = { requests: [], replies: [], timers: new Map(), seq: 0, reloads: 0, replaced: [], confirms: [], alerts: [], clock: { now: Date.now() }, intervals: [] };
  const clock = own.clock;
  class FakeDate extends Date { constructor(...args) { super(...(args.length ? args : [clock.now])); } static now() { return clock.now; } }
  const documentApi = {
    getElementById: byId,
    createElement: (tag) => new El(tag),
    createTextNode: (text) => new Text(text),
    querySelector: (selector) => document.querySelector(selector),
    querySelectorAll: (selector) => document.querySelectorAll(selector),
    addEventListener: (type, listener) => document.addEventListener(type, listener),
  };
  if (visibility) documentApi.visibilityState = visibility;
  const sandbox = {
    window: { digitalisimoBackupsManual: { url: '/wp-admin/admin-ajax.php', nonce: 'nonce-manual', jobsNonce: 'nonce-jobs-vieja', context: 'site', login: '/wp-login.php?migrar' }, confirm: (text) => { own.confirms.push(text); return true; }, alert: (text) => own.alerts.push(text), location: { href: href || 'https://example.test/wp-admin/admin.php?page=digitalisimo-backups&tab=backups&backup=ok&message=Restauraci%C3%B3n%20en%20curso', reload: () => own.reloads++, replace: (url) => own.replaced.push(url) } },
    document: documentApi,
    URL, URLSearchParams, Object, String, Number, Math, Error, JSON, Date: FakeDate, Array,
    setInterval: (callback) => { own.intervals.push(callback); return own.intervals.length; },
    setTimeout: (callback, delay) => { const id = ++own.seq; own.timers.set(id, { callback, delay }); return id; },
    clearTimeout: (id) => { own.timers.delete(id); },
    fetch: (url, options) => {
      const body = Object.fromEntries(new URLSearchParams(options.body));
      own.requests.push(body);
      const reply = own.replies.shift();
      if (reply === undefined) throw new Error('Petición inesperada: ' + body.action);
      if (reply.reject) return Promise.reject(new TypeError('Failed to fetch'));
      if (reply.pending) return new Promise(() => {});
      if (reply.defer) return reply.defer;
      const headers = Object.fromEntries(Object.entries(reply.headers || {}).map(([k, v]) => [k.toLowerCase(), v]));
      return Promise.resolve({ ok: (reply.status || 200) < 400, status: reply.status || 200, headers: { get: (name) => headers[String(name).toLowerCase()] ?? null }, text: () => Promise.resolve(typeof reply.body === 'string' ? reply.body : JSON.stringify(reply.body)) });
    },
  };
  if (token) sandbox.window.digitalisimoBackupsRestore = { token, action: 'digitalisimo_backups_restore_tick' };
  sandbox.window.document = documentApi;
  runInNewContext(script, sandbox);
  const P2 = (name) => byId(P + name);
  Object.assign(own, { document, panel, bar, form, submit, card: cardNode, strip: stripNode, slots, wrap, ui: Object.fromEntries(IDS.map((name) => [name, P2(name)])), message: byId('digitalisimo-backups-manual-message'), detail: byId('digitalisimo-backups-manual-detail'), config: sandbox.window.digitalisimoBackupsManual });
  /* El siguiente paso programado (sólo puede haber uno: cada uno cancela el anterior). */
  own.pending = () => [...own.timers.values()];
  own.next = async (reply) => {
    if (reply !== undefined) own.replies.push(reply);
    assert.equal(own.timers.size, 1, 'Debe quedar exactamente un paso programado, hay ' + own.timers.size);
    const [id, timer] = [...own.timers.entries()][0];
    own.timers.delete(id);
    timer.callback();
    await settle();
    return timer.delay;
  };
  own.jobsReply = (jobs, extra = {}) => ({ body: { success: true, data: Object.assign({ result: 'worked', message: '', jobs }, extra) } });
  own.phases = () => own.ui.phases.children.map((li) => `${li.getAttribute('data-phase')}:${li.getAttribute('data-state')}`);
  own.text = (node) => (node ? node.textContent : '');
  return own;
}

/* ------------------------------------------------------------------ Vistas del servidor */
const RESTORE_TRACK = [['inspect', 'Verificación del respaldo', 0], ['safety', 'Copia previa', 10], ['database', 'Base de datos', 40], ['files', 'Archivos', 62], ['switch', 'Cambio del sitio', 86], ['finish', 'Direcciones y cachés', 94], ['cleanup', 'Limpieza', 97]];
const BACKUP_TRACK = [['preflight', 'Comprobaciones', 0], ['database', 'Base de datos', 3], ['pack', 'Archivos y ZIP', 40], ['verify', 'Verificación', 81], ['publish', 'Registro', 98], ['prune', 'Retención', 99]];
const LABEL = Object.fromEntries(RESTORE_TRACK.map(([key, label]) => [key, label]));
const RID = '11111111-2222-4333-8444-555555555555';
const BACK = '22222222-3333-4444-8555-666666666666';
const CHAIN = '33333333-4444-4555-8666-777777777777';
const MINE = '44444444-5555-4666-8777-888888888888';
function restoreView(phase, percent, over = {}) {
  return Object.assign({ id: RID, kind: 'restore', status: phase === 'inspect' ? 'verifying' : 'running', status_label: phase === 'inspect' ? 'Verificando' : 'En curso', phase, phase_label: LABEL[phase], percent, detail: 'Comprobando archivo 10 de 20 <img src=x onerror=alert(1)>', message: '', terminal: false, running: true, retry_in: 0, can_cancel: ['inspect', 'safety', 'database', 'files'].includes(phase), origin_label: 'Restauración', destination_label: 'digitalisimo-sitio-2026-11-15.zip', created_label: '2026-11-15 10:04', track: RESTORE_TRACK, elapsed: 65, rollback_of: '', safety: 'safety-before-restore-x.zip', parts: 'todo', followed_by: '', midway: false }, over);
}
const restoreDone = (over = {}) => restoreView('cleanup', 100, Object.assign({ status: 'success', status_label: 'Completo', phase_label: '', detail: 'Restauración terminada desde digitalisimo-sitio-2026-11-15.zip.', terminal: true, running: false, can_cancel: false, elapsed: 312, result: { notice: 'Cómo entrar ahora: https://example.test/wp-login.php', warnings: ['Limpieza: no se pudo apartar wp-content/cache'], warnings_total: 3, removed: 4, kept: 1 } }, over));
const backupView = (phase, percent, over = {}) => Object.assign({ id: MINE, kind: 'backup', status: 'running', status_label: 'En curso', phase, phase_label: Object.fromEntries(BACKUP_TRACK.map(([k, l]) => [k, l]))[phase], percent, detail: 'Exportando tabla 3 de 12: wp_posts', message: 'Exportando tabla 3 de 12: wp_posts', terminal: false, running: true, retry_in: 0, can_cancel: true, origin_label: 'Respaldo manual', destination_label: 'Hosting / local', created_label: '2026-11-15 09:00', track: BACKUP_TRACK, elapsed: 4 }, over);

const failed = [];
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
async function scenario(label, test) {
  try { await test(); console.log('ok · ' + label); } catch (error) { failed.push(label); console.log('FALLA · ' + label + ': ' + error.message); }
}

await scenario('P12 · el script usa el marcado de PHP y nada se pinta como HTML', async () => {
  ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(script), 'El panel debe pintar el texto del servidor como texto');
  for (const name of IDS) ok(markup.includes(`'-${name}"`) || markup.includes(`-${name}" `) || markup.includes(`$id . '-${name}`), `El marcado de PHP no tiene el elemento «${name}» del panel`);
  for (const id of ['digitalisimo-backups-manual-progress', 'digitalisimo-backups-manual-bar', 'digitalisimo-backups-manual-message', 'digitalisimo-backups-manual-detail']) ok(markup.includes(`id="${id}"`), `El marcado de PHP no tiene #${id}`);
  ok(/role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"/.test(markup) && /id="digitalisimo-backups-manual-message"[^>]*role="status" aria-live="polite"/.test(markup), 'La barra no es un progressbar con sus valores o el estado no es una región viva');
});

await scenario('P1 · un respaldo: título, %, barra accesible, fases, paso, tiempo; al terminar recarga la lista', async () => {
  const page = screen();
  page.replies.push({ body: { success: true, data: { id: MINE, view: backupView('preflight', 0, { status: 'queued', status_label: 'Pendiente', running: false, detail: 'En cola.' }) } } });
  page.form.listeners.submit[0]({ preventDefault: () => {} });
  ok(!page.panel.hidden && page.panel.getAttribute('data-state') === 'starting' && page.text(page.message) === 'Encolando el respaldo…', 'Al pulsar «Respaldar ahora» el panel no dice que encola');
  await settle();
  ok(page.text(page.message).startsWith('Pendiente'), 'El panel no sigue el respaldo encolado: ' + page.text(page.message));
  await page.next(page.jobsReply([backupView('database', 12)]));
  ok(page.text(page.ui.title) === 'Respaldo en curso' && page.text(page.ui.percent) === '12 %', 'Título o porcentaje del respaldo: ' + page.text(page.ui.title) + ' / ' + page.text(page.ui.percent));
  ok(page.bar.getAttribute('aria-valuenow') === '12' && /12 %/.test(page.bar.getAttribute('aria-valuetext')) && page.ui.fill.style.width === '12%', 'La barra no dice su valor a los lectores de pantalla o no se llena: ' + JSON.stringify([page.bar.attributes, page.ui.fill.style]));
  ok(JSON.stringify(page.phases()) === JSON.stringify(['preflight:done', 'database:current', 'pack:pending', 'verify:pending', 'publish:pending', 'prune:pending']), 'Las fases del respaldo no marcan la actual: ' + page.phases());
  ok(page.ui.phases.children[1].getAttribute('aria-current') === 'step', 'La fase en curso no lleva aria-current="step"');
  ok(page.text(page.message) === 'En curso · Base de datos (fase 2 de 6)' && page.text(page.detail).includes('Exportando tabla 3 de 12'), 'El estado o el paso dentro de la fase no se ven: ' + page.text(page.message) + ' / ' + page.text(page.detail));
  ok(/lleva \d+ s/.test(page.text(page.ui.time)) && page.text(page.ui.time).startsWith('Empezó a las 09:00'), 'El tiempo transcurrido no se ve: ' + page.text(page.ui.time));
  ok(page.ui.ticks.children.length === 5, 'Los tramos de la barra no se marcan: ' + page.ui.ticks.children.length);
  ok(!page.ui.cancel.hidden && page.card.hidden, '«Cancelar» no está en el panel o la tarjeta repite el respaldo del panel');
  await page.next(page.jobsReply([backupView('prune', 100, { status: 'success', status_label: 'Completo', phase_label: '', detail: 'ZIP verificado: x.zip', terminal: true, running: false, can_cancel: false })]));
  ok(page.text(page.ui.title) === 'Respaldo terminado' && page.phases().every((p) => p.endsWith(':done')) && page.text(page.detail).includes('Actualizando la lista') && page.reloads + page.replaced.length === 1, 'Al terminar el respaldo no se ve completo o no se recarga la lista una vez');
});

await scenario('P2 · una restauración por fases, con su testigo, «Cancelar» sólo antes del cambio y el resultado al terminar', async () => {
  const page = screen({ view: restoreView('inspect', 3), token: 'testigo-1', strip: true, active: true });
  ok(!page.panel.hidden && page.text(page.ui.title) === 'Restauración en curso' && page.text(page.ui.percent) === '3 %', 'Al cargar la página con una restauración en curso, el panel no la enseña');
  ok(page.strip.hidden && page.strip.children[0].children[0].hidden, 'La franja sigue repitiendo el estado de la restauración que ya enseña el panel');
  ok(page.submit.disabled === true, '«Respaldar ahora» sigue activo durante la restauración');
  ok(page.text(page.ui.what) === 'Respaldo digitalisimo-sitio-2026-11-15.zip · se restaura todo', 'No dice qué respaldo se restaura ni qué: ' + page.text(page.ui.what));
  ok(!page.ui.help.hidden, '«Qué la hace avanzar» no se ofrece durante la restauración');
  let delay = 0;
  for (const [phase, percent] of [['safety', 18], ['database', 51], ['files', 70], ['switch', 88], ['finish', 95], ['cleanup', 98]]) {
    delay = await page.next(page.jobsReply([restoreView(phase, percent)]));
    const request = page.requests[page.requests.length - 1];
    ok(request.action === 'digitalisimo_backups_restore_tick' && request.digitalisimo_backups_restore === 'testigo-1', 'El tick de la restauración no lleva su testigo: ' + JSON.stringify(request));
    ok(page.text(page.ui.percent) === percent + ' %' && page.phases().includes(phase + ':current'), `La fase ${phase} no se ve en curso: ${page.phases()}`);
    ok(page.ui.cancel.hidden === !['inspect', 'safety', 'database', 'files'].includes(phase), `«Cancelar» en ${phase}: ${page.ui.cancel.hidden ? 'oculto' : 'visible'}`);
    ok(page.text(page.message).includes(`(fase ${RESTORE_TRACK.findIndex(([k]) => k === phase) + 1} de 7)`), 'No dice qué fase de cuántas: ' + page.text(page.message));
    ok(page.text(page.detail).includes('<img src=x onerror=alert(1)>'), 'El paso de la fase no se pinta literal');
  }
  ok(delay === 300, 'Tras un paso hecho, el siguiente debe ir enseguida');
  await page.next(page.jobsReply([restoreDone()]));
  const result = page.text(page.ui.result);
  ok(page.text(page.ui.title) === 'Restauración terminada' && page.text(page.ui.percent) === '100 %' && page.phases().every((p) => p.endsWith(':done')), 'La restauración terminada no se ve al 100 % con todas sus fases hechas');
  ok(result.includes('Restauración terminada desde') && result.includes('Cómo entrar ahora') && result.includes('Con 3 avisos') && result.includes('Limpieza: no se pudo apartar') && result.includes('Y 2 más') && result.includes('Se retiraron 4 archivos') && result.includes('safety-before-restore-x.zip'), 'El resultado no dice qué se restauró, sus avisos, lo retirado y la copia previa: ' + result);
  ok(!page.ui.safety.hidden && /tab=backups/.test(page.ui.safety.getAttribute('href')) && /restore=safety-before-restore-x\.zip/.test(page.ui.safety.getAttribute('href')) && !/message=/.test(page.ui.safety.getAttribute('href')), '«Volver a la copia previa» no abre la copia previa: ' + page.ui.safety.getAttribute('href'));
  ok(!page.ui.reload.hidden && page.ui.cancel.hidden && page.ui.help.hidden && page.reloads + page.replaced.length === 0 && page.pending().length === 1 && page.pending()[0].delay === 1000, 'Al terminar la restauración la página se recargó (y se perdió el resultado) o no vuelve a la cola');
  ok(page.submit.disabled === false && /duró 5 min 12 s/.test(page.text(page.ui.time)), '«Respaldar ahora» no vuelve o el tiempo no dice cuánto duró: ' + page.text(page.ui.time));
  // Lo que siga (la cola de respaldos) vuelve al tick con nonce, una vez; sin trabajos, el sondeo para (revisión J10).
  await page.next(page.jobsReply([restoreDone()]));
  ok(page.requests[page.requests.length - 1].action === 'digitalisimo_backups_job_tick' && page.timers.size === 0 && page.text(page.ui.title) === 'Restauración terminada', 'Tras terminar, la cola no vuelve al tick con nonce o sigue consultando sin trabajos: ' + JSON.stringify(page.requests[page.requests.length - 1]));
  page.ui.reload.listeners.click[0]({});
  ok(page.replaced.length === 1 && !/backup=|message=/.test(page.replaced[0]), '«Ver la lista actualizada» no recarga sin el aviso viejo: ' + page.replaced);
});

await scenario('P3 · por partes: dice qué se restaura', async () => {
  const page = screen({ view: restoreView('files', 70, { parts: 'el plugin demo' }), token: 't' });
  ok(page.text(page.ui.what).endsWith('se restaura sólo: el plugin demo'), 'Restaurar por partes no dice qué parte: ' + page.text(page.ui.what));
  // Lo retirado lo dice el informe (con dónde queda): el panel no lo repite.
  await page.next(page.jobsReply([restoreDone({ parts: 'el plugin demo', result: { notice: 'Restauración por partes: se restauró el plugin demo. Se retiraron 2 archivo(s) que el respaldo no tenía: están en la copia previa x.zip.', warnings: [], warnings_total: 0, removed: 2, kept: 0 } })]));
  ok(page.text(page.ui.result).split('Se retiraron').length === 2, 'Lo retirado se dice dos veces: ' + page.text(page.ui.result));
});

await scenario('P4 · 503 del mantenimiento, corte de red y 502: lo dice, sigue consultando y la nota se va al volver', async () => {
  const page = screen({ view: restoreView('database', 45) });
  ok(page.pending().length === 1, 'Al cargar con una restauración en curso no se programa el sondeo');
  let delay = await page.next(GATE());
  ok(page.requests[0].action === 'digitalisimo_backups_job_tick' && !page.ui.note.hidden && /mantenimiento/.test(page.text(page.ui.note)) && page.pending()[0].delay === 30000 && !/recarga esta página/.test(page.text(page.ui.note)), 'Un 503 no se explica o no se consulta cada 30 s: ' + page.text(page.ui.note));
  ok(page.text(page.ui.percent) === '45 %', 'La barra cambió con el 503');
  for (let i = 0; i < 3; i++) delay = await page.next({ reject: true });
  ok(/Sin conexión con el servidor/.test(page.text(page.ui.note)) && page.pending().length === 1, 'Un corte de red no se explica o se deja de reintentar: ' + page.text(page.ui.note));
  delay = await page.next({ status: 502, body: '<html>Bad gateway</html>' });
  ok(/HTTP 502/.test(page.text(page.ui.note)) && /recarga esta página/.test(page.text(page.ui.note)) && page.ui.note.getAttribute('data-level') === 'error', 'Tras 5 fallos no dice qué hacer: ' + page.text(page.ui.note));
  await page.next(page.jobsReply([restoreView('database', 52)]));
  ok(page.text(page.ui.note) === '' && !page.ui.note.hidden && page.text(page.ui.percent) === '52 %', 'Al volver la conexión la nota no se va (la región viva sigue en la página, vacía) o el avance no sigue');
  // Un reintento devuelve el % al comienzo de la fase (se retoma desde su punto de control): la barra no retrocede.
  await page.next(page.jobsReply([restoreView('database', 40, { running: false, retry_in: 8, detail: 'Reintento a las 10:20 (2 de 5)' })], { result: 'idle' }));
  ok(page.text(page.ui.percent) === '52 %' && /Reintento a las 10:20/.test(page.text(page.detail)) && page.pending()[0].delay === 8000, 'Un reintento hace retroceder la barra o no dice cuándo sigue: ' + page.text(page.ui.percent));
});

await scenario('P5 · un nonce que dejó de valer pasa al tick por testigo y toma los nonces nuevos', async () => {
  const page = screen({ view: restoreView('switch', 87) });
  await page.next({ status: 400, body: '-1' });
  ok(!/inicia sesión/i.test(page.text(page.ui.note)) && /testigo de este navegador/.test(page.text(page.ui.note)) && page.pending().length === 1, 'Un nonce que dejó de valer detiene el sondeo: ' + page.text(page.ui.note));
  await page.next(page.jobsReply([restoreView('finish', 95)], { nonces: { jobs: 'nonce-jobs-nueva', restore: 'nonce-restore-nueva' } }));
  const tick = page.requests[page.requests.length - 1];
  ok(tick.action === 'digitalisimo_backups_restore_tick' && tick.digitalisimo_backups_restore === '', 'Tras el «-1» no se pidió el tick por testigo (la cookie): ' + JSON.stringify(tick));
  ok(page.config.jobsNonce === 'nonce-jobs-nueva' && page.ui.rollback.querySelector('input[name="_wpnonce"]').value === 'nonce-restore-nueva', 'Los nonces nuevos no se usan: ' + page.config.jobsNonce);
  ok(page.text(page.ui.percent) === '95 %' && page.text(page.ui.note) === '', 'El avance no sigue con el testigo');
});

await scenario('P6 · recargar la página a mitad de la restauración vuelve al panel y sigue', async () => {
  const page = screen({ view: restoreView('files', 66, { elapsed: 3725 }), token: 'testigo-2' });
  ok(!page.panel.hidden && page.phases().includes('files:current') && page.text(page.ui.percent) === '66 %' && /lleva 1 h 2 min/.test(page.text(page.ui.time)), 'Al recargar el panel no vuelve en su fase y con su tiempo: ' + page.text(page.ui.time));
  ok(page.pending().length === 1 && page.pending()[0].delay === 300, 'Al recargar no sigue consultando enseguida');
  await page.next(page.jobsReply([restoreView('switch', 88)]));
  ok(page.phases().includes('switch:current'), 'Tras recargar el panel no avanza');
  // Un tramo que tarda (la respuesta del tick llega al terminarlo, hasta 20 s): el panel dice que el servidor trabaja.
  await page.next({ pending: true });
  page.clock.now += 5000;
  for (const tick of page.intervals) tick();
  ok(!page.ui.note.hidden && /trabajando en este paso/.test(page.text(page.ui.note)) && /lleva 1 min 10 s/.test(page.text(page.ui.time)), 'Mientras un tramo tarda, el panel no dice que el servidor trabaja o el reloj se para: ' + page.text(page.ui.note));
});

await scenario('P7 · falla a mitad del cambio: sigue la vuelta a la copia previa y dice que el sitio volvió y por qué', async () => {
  const page = screen({ view: restoreView('switch', 88), token: 't' });
  const failedView = restoreView('switch', 88, { status: 'failed', status_label: 'Fallido', phase_label: '', terminal: true, running: false, can_cancel: false, followed_by: BACK, error: 'fallo inyectado al mover los archivos', detail: 'La restauración falló a mitad del cambio (Cambio del sitio): fallo inyectado al mover los archivos Se está restaurando la copia previa safety-before-restore-x.zip.' });
  const back = (phase, percent, over = {}) => restoreView(phase, percent, Object.assign({ id: BACK, origin_label: 'Vuelta a la copia previa', destination_label: 'safety-before-restore-x.zip', rollback_of: RID, rollback_file: 'digitalisimo-sitio-2026-11-15.zip', rollback_cause: 'fallo inyectado al mover los archivos', safety: '', can_cancel: false }, over));
  await page.next(page.jobsReply([back('inspect', 2), failedView]));
  ok(page.text(page.ui.title) === 'Volviendo a la copia previa' && page.text(page.ui.what).includes('deshace la restauración de digitalisimo-sitio-2026-11-15.zip') && page.ui.cancel.hidden, 'Tras fallar a mitad del cambio el panel no sigue la vuelta a la copia previa: ' + page.text(page.ui.title));
  ok(page.pending().length === 1, 'La vuelta a la copia previa no se sigue consultando');
  await page.next(page.jobsReply([back('cleanup', 100, { status: 'success', status_label: 'Completo', phase_label: '', terminal: true, running: false, detail: 'Se volvió a la copia previa safety-before-restore-x.zip: el sitio está como antes de la restauración que falló.' }), failedView]));
  const result = page.text(page.ui.result);
  ok(page.text(page.ui.title) === 'El sitio volvió a la copia previa' && result.includes('falló a mitad del cambio: fallo inyectado') && result.includes('el sitio está como antes') && page.ui.safety.hidden && page.ui.rollback.hidden, 'El resultado no dice que el sitio volvió y por qué: ' + page.text(page.ui.title) + ' / ' + result);
  ok(page.pending().length === 1 && page.pending()[0].delay === 1000, 'Terminada la vuelta, la cola no vuelve al tick con nonce');
  await page.next(page.jobsReply([]));
  ok(page.timers.size === 0 && page.requests[page.requests.length - 1].action === 'digitalisimo_backups_job_tick', 'Terminada la vuelta, el panel sigue consultando');
});

await scenario('P8 · si la vuelta también falla, ofrece «Volver a la copia previa»', async () => {
  const page = screen({ view: restoreView('database', 3, { id: BACK, origin_label: 'Vuelta a la copia previa', rollback_of: RID, rollback_file: 'x.zip', rollback_cause: 'fallo', safety: '' }), token: 't' });
  await page.next(page.jobsReply([restoreView('switch', 88, { id: BACK, origin_label: 'Vuelta a la copia previa', rollback_of: RID, status: 'failed', status_label: 'Fallido', phase_label: '', terminal: true, running: false, can_cancel: false, midway: true, detail: 'La vuelta a la copia previa también falló: disco lleno. El sitio quedó a medio cambiar y sigue en mantenimiento.' })]));
  ok(page.text(page.ui.title) === 'La vuelta a la copia previa no terminó' && !page.ui.rollback.hidden && page.text(page.ui.result).includes('a medio cambiar') && page.panel.getAttribute('data-state') === 'failed', 'Un sitio a medio cambiar no ofrece «Volver a la copia previa»');
});

await scenario('P9 · sin sesión ni testigo lo dice y para; terminada sin sesión, su estado con «Iniciar sesión»', async () => {
  const lost = screen({ view: restoreView('files', 70) });
  await lost.next({ status: 400, body: '0' });
  await lost.next({ status: 403, body: { success: false, data: { message: 'No hay una restauración en curso con este testigo.' } } });
  ok(/No se puede seguir el avance/.test(lost.text(lost.ui.note)) && !lost.ui.login.hidden && lost.timers.size === 0 && lost.panel.getAttribute('data-state') === 'lost', 'Sin sesión ni testigo el panel no lo dice o sigue consultando sin fin');
  const done = screen({ view: restoreView('cleanup', 98), token: 't' });
  await done.next({ body: { success: true, data: { result: 'finished', manager: false, message: 'Restauración terminada. Entra al escritorio para ver el resultado.', jobs: [{ id: RID, kind: 'restore', status: 'success', status_label: 'Completo', phase: '', phase_label: '', percent: 100, message: '', detail: 'Restauración terminada. Entra al escritorio para ver el resultado.', terminal: true, running: false, retry_in: 0, can_cancel: false, origin_label: 'Restauración', destination_label: '', created_label: '' }] } } });
  ok(done.text(done.ui.title) === 'Restauración terminada' && !done.ui.login.hidden && done.text(done.ui.result).includes('Entra al escritorio') && done.timers.size === 0, 'Terminada por otro proceso sin sesión, el panel no lo dice con «Iniciar sesión»');
  const ticked = screen({ view: restoreView('cleanup', 98), token: 't' });
  await ticked.next(ticked.jobsReply([restoreDone({ result: undefined })], { manager: false }));
  ok(ticked.text(ticked.ui.title) === 'Restauración terminada' && !ticked.ui.login.hidden && ticked.ui.safety.hidden && /perdió la sesión/.test(ticked.text(ticked.ui.result)), 'Terminada en su tick con la sesión perdida, el panel no pide entrar para ver el resultado');
});

await scenario('P10 · un punto incremental: la reconstrucción y después la restauración en el mismo panel', async () => {
  const chain = (over = {}) => Object.assign({ id: CHAIN, kind: 'chain', status: 'running', status_label: 'En curso', phase: 'build', phase_label: 'Reconstrucción del punto incremental', percent: 30, detail: 'Reconstruyendo el punto: 20 de 60 archivos…', terminal: false, running: true, retry_in: 0, can_cancel: true, origin_label: 'Restaurar un punto incremental', destination_label: 'antes de restaurar x.zip', created_label: '2026-11-15 10:00', track: [['preflight', 'Comprobaciones', 0], ['build', 'Reconstrucción del punto incremental', 5], ['seal', 'Cierre del punto reconstruido', 92], ['handoff', 'Entrega a la restauración', 98]], elapsed: 30, restore_job: '' }, over);
  const page = screen({ view: chain() });
  ok(page.text(page.ui.title) === 'Preparando la restauración del punto incremental' && page.phases().includes('build:current'), 'La reconstrucción del punto no se ve en el panel');
  await page.next(page.jobsReply([restoreView('inspect', 1), chain({ status: 'success', status_label: 'Completo', phase: 'handoff', phase_label: '', percent: 100, terminal: true, running: false, can_cancel: false, restore_job: RID })]));
  ok(page.text(page.ui.title) === 'Restauración en curso' && page.phases().includes('inspect:current') && page.pending().length === 1, 'Tras reconstruir el punto el panel no sigue su restauración');
});

await scenario('P11 · al confirmar «Restaurar» el panel dice ya que empieza', async () => {
  const page = screen();
  const restoreForm = page.document.children[0].appendChild(new El('form'));
  restoreForm.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_restore' }));
  for (const listener of page.document.listeners.submit || []) listener({ target: restoreForm, defaultPrevented: false });
  ok(!page.panel.hidden && page.panel.getAttribute('data-state') === 'starting' && page.text(page.message) === 'Empezando la restauración…' && !page.bar.hasAttribute('aria-valuenow') && page.panel.scrolled, 'Al confirmar «Restaurar» el panel no dice que empieza (barra indeterminada)');
});

/* ------------------------------------------------------------------ Revisión adversarial (M1–M5, B1–B11) */

await scenario('P13 · una restauración que empezó fuera de esta pestaña la toma el panel grande y sigue tras el cambio (M3)', async () => {
  // La cola en curso (una automatización): en su sondeo aparece la restauración que pidió otra pestaña, REST o WP-CLI.
  const page = screen({ active: true });
  await page.next(page.jobsReply([restoreView('database', 45)]));
  ok(!page.panel.hidden && page.text(page.ui.title) === 'Restauración en curso' && page.text(page.ui.percent) === '45 %' && page.card.hidden, 'Una restauración que no empezó esta pestaña no entra al panel grande (o la repite la tarjeta): ' + page.text(page.ui.title));
  ok(page.submit.disabled === true, '«Respaldar ahora» sigue activo durante una restauración que empezó otra pestaña');
  // Cambio de tablas: el nonce deja de valer; pasa al tick por testigo (la cookie que le dio el sondeo) con su nota.
  await page.next({ status: 400, body: '-1' });
  ok(/testigo de este navegador/.test(page.text(page.ui.note)), 'Tras «-1» el panel no explica que sigue con el testigo: ' + page.text(page.ui.note));
  await page.next(page.jobsReply([restoreView('finish', 95)], { manager: true, nonces: { jobs: 'jobs-nueva', manual: 'manual-nueva', restore: 'restore-nueva' } }));
  const tick = page.requests[page.requests.length - 1];
  ok(tick.action === 'digitalisimo_backups_restore_tick' && page.config.jobsNonce === 'jobs-nueva' && page.text(page.ui.percent) === '95 %', 'Tras el cambio de tablas la pestaña no siguió con el testigo y los nonces nuevos: ' + JSON.stringify(tick));
  // Sin trabajos al cargar: la consulta del estado cada 30 s la ve igual.
  const idle = screen({ card: true, active: false });
  ok(idle.timers.size === 0, 'Sin trabajos no debe programarse un tick');
  idle.clock.now += 31000;
  idle.replies.push(idle.jobsReply([restoreView('safety', 12)]));
  for (const run of idle.intervals) run();
  await settle();
  ok(idle.requests.length === 1 && idle.requests[0].action === 'digitalisimo_backups_job_status' && idle.requests[0].nonce === 'nonce-jobs-vieja', 'Sin trabajos la pantalla no consulta el estado: ' + JSON.stringify(idle.requests));
  ok(!idle.panel.hidden && idle.text(idle.ui.title) === 'Restauración en curso' && idle.pending().length === 1 && idle.pending()[0].delay === 300, 'La consulta del estado no deja la restauración en el panel ni empieza a seguirla');
  // Si ya está en mantenimiento (503 sin testigo), el panel lo dice y qué hacer, sin barra quieta y sin pedir recargar.
  const late = screen({ card: true, active: false });
  late.clock.now += 31000;
  late.replies.push(GATE());
  for (const run of late.intervals) run();
  await settle();
  ok(!late.panel.hidden && late.panel.getAttribute('data-state') === 'maintenance' && /mantenimiento/.test(late.text(late.ui.note)) && /testigo/.test(late.text(late.ui.note)) && !late.bar.hasAttribute('aria-valuenow'), 'Un 503 en la consulta del estado no se explica en el panel: ' + late.text(late.ui.note));
  // Al terminar el mantenimiento, el panel enseña cómo acabó; si la sesión ya no vale, lo dice y para.
  late.clock.now += 31000;
  late.replies.push(late.jobsReply([restoreDone({ finished_ago: 40 })]));
  for (const run of late.intervals) run();
  await settle();
  ok(late.text(late.ui.title) === 'Restauración terminada' && late.text(late.ui.note) === '' && !late.ui.result.hidden && late.text(late.ui.result).includes('Restauración terminada desde'), 'Terminado el mantenimiento, el panel no enseña cómo acabó la restauración: ' + late.text(late.ui.title));
  const gone = screen({ card: true, active: false });
  gone.clock.now += 31000;
  gone.replies.push(GATE());
  for (const run of gone.intervals) run();
  await settle();
  gone.clock.now += 31000;
  gone.replies.push({ status: 400, body: '0' });
  for (const run of gone.intervals) run();
  await settle();
  ok(gone.panel.getAttribute('data-state') === 'lost' && /No se puede seguir el avance/.test(gone.text(gone.ui.note)) && !gone.ui.login.hidden, 'Tras el mantenimiento sin sesión, el panel no lo dice: ' + gone.text(gone.ui.note));
  gone.clock.now += 31000;
  for (const run of gone.intervals) run();
  ok(gone.requests.length === 2, 'Sin sesión, la consulta del estado sigue');
});

await scenario('P14 · terminada por otro proceso con otra espera en la cola: vuelve al tick con nonce (B3, J10)', async () => {
  const page = screen({ view: restoreView('cleanup', 98), token: 't', active: true });
  const auto = backupView('preflight', 0, { id: '55555555-6666-4777-8888-999999999999', origin_label: 'Automatización', status: 'queued', status_label: 'Pendiente', running: false, retry_in: 0 });
  await page.next(page.jobsReply([restoreView('cleanup', 99), auto]));
  await page.next({ body: { success: true, data: { result: 'finished', manager: true, nonces: { jobs: 'jobs-nueva' }, message: '', jobs: [restoreDone()] } } });
  ok(page.text(page.ui.title) === 'Restauración terminada' && page.pending().length === 1, 'Terminada por otro proceso, la pantalla deja de consultar aunque la cola espera: ' + page.timers.size);
  await page.next(page.jobsReply([auto, restoreDone()]));
  const tick = page.requests[page.requests.length - 1];
  ok(tick.action === 'digitalisimo_backups_job_tick' && tick.nonce === 'jobs-nueva' && !page.card.hidden && page.text(page.card).includes('Automatización'), 'La cola no vuelve al tick con nonce (con el nuevo) o la tarjeta pierde la automatización: ' + JSON.stringify(tick));
  // Sin sesión de gestión no hay cola que consultar: para.
  const lostSession = screen({ view: restoreView('cleanup', 98), token: 't' });
  await lostSession.next({ body: { success: true, data: { result: 'finished', manager: false, nonces: [], message: '', jobs: [restoreDone({ result: undefined })] } } });
  ok(lostSession.timers.size === 0, 'Sin sesión, tras terminar sigue consultando');
});

await scenario('P15 · la barra nunca retrocede: del punto incremental a su restauración y una fase que vuelve atrás (B4)', async () => {
  const chain = (over = {}) => Object.assign({ id: CHAIN, kind: 'chain', status: 'running', status_label: 'En curso', phase: 'seal', phase_label: 'Cierre', percent: 96, detail: '', terminal: false, running: true, retry_in: 0, can_cancel: true, origin_label: 'Restaurar un punto incremental', destination_label: 'x', created_label: '2026-11-15 10:00', track: [['preflight', 'Comprobaciones', 0], ['build', 'Reconstrucción', 5], ['seal', 'Cierre', 92], ['handoff', 'Entrega', 98]], elapsed: 30, restore_job: '' }, over);
  const page = screen({ view: chain() });
  const before = parseInt(page.text(page.ui.percent), 10);
  ok(before === 19, 'La reconstrucción del punto no ocupa el primer 20 % de la barra: ' + before);
  await page.next(page.jobsReply([restoreView('inspect', 1, { from_point: true }), chain({ status: 'success', status_label: 'Completo', phase: 'handoff', percent: 100, terminal: true, running: false, can_cancel: false, restore_job: RID })]));
  const after = parseInt(page.text(page.ui.percent), 10);
  ok(after >= before && after === 21 && page.ui.ticks.children.length === 7 && page.ui.ticks.children[0].style.left === '20%' && page.ui.ticks.children[1].style.left === '28%', 'Del punto a su restauración la barra retrocede o sus tramos no siguen la escala: ' + before + ' -> ' + after);
  await page.next(page.jobsReply([restoreView('files', 70, { from_point: true })]));
  ok(page.text(page.ui.percent) === '76 %', 'La restauración del punto no sigue la escala 20–100: ' + page.text(page.ui.percent));
  // Un respaldo cuyo ZIP no pasó la verificación vuelve a «Archivos y ZIP»: la barra espera y el panel lo dice.
  const backup = screen({ view: backupView('verify', 81) });
  await backup.next(backup.jobsReply([backupView('pack', 45, { detail: 'Se reintentará: el ZIP no pasó la verificación' })]));
  ok(backup.text(backup.ui.percent) === '81 %' && /vuelve a Archivos y ZIP/.test(backup.text(backup.message)) && /cuando pase de nuevo el 81 %/.test(backup.text(backup.message)) && backup.phases().includes('pack:current'), 'Una fase que vuelve atrás no se dice o la barra retrocede: ' + backup.text(backup.ui.percent) + ' / ' + backup.text(backup.message));
});

await scenario('P16 · sin sesión, la vista mínima de una que falló marca su fase y la vuelta dice que lo es; la barra no baja (B5, J20)', async () => {
  const page = screen({ view: restoreView('switch', 88), token: 't' });
  const minimal = { id: RID, kind: 'restore', status: 'failed', status_label: 'Fallido', phase: 'switch', phase_label: '', percent: 88, message: '', detail: 'La restauración no terminó bien. Entra al escritorio para ver el detalle y la copia previa.', terminal: true, running: false, retry_in: 0, can_cancel: false, origin_label: 'Restauración', destination_label: '', created_label: '2026-11-15 10:04', track: RESTORE_TRACK, elapsed: 300, parts: 'todo', rollback_of: '', followed_by: '', midway: true };
  await page.next({ body: { success: true, data: { result: 'finished', manager: false, message: '', jobs: [Object.assign({}, minimal, { percent: 0, phase: '' })] } } });
  ok(page.phases().includes('switch:failed') && page.phases().filter((p) => p.endsWith(':done')).length === 4, 'Sin la fase en la vista mínima, el panel no marca dónde se detuvo: ' + page.phases());
  ok(page.text(page.ui.percent) === '88 %' && page.panel.getAttribute('data-state') === 'failed', 'Un fallo hace retroceder la barra: ' + page.text(page.ui.percent));
  const back = screen({ view: restoreView('database', 30, { id: BACK, origin_label: 'Vuelta a la copia previa', rollback_of: RID }), token: 't' });
  await back.next({ body: { success: true, data: { result: 'finished', manager: false, message: '', jobs: [Object.assign({}, minimal, { id: BACK, origin_label: 'Vuelta a la copia previa', rollback_of: RID, phase: 'files', percent: 66 })] } } });
  ok(back.text(back.ui.title) === 'La vuelta a la copia previa no terminó' && back.phases().includes('files:failed') && back.text(back.ui.percent) === '66 %', 'La vista mínima de una vuelta a la copia previa no lo dice: ' + back.text(back.ui.title) + ' / ' + back.phases());
});

await scenario('P17 · «Cancelar» sólo cuando sirve: no sin sesión de gestión ni mientras el sondeo falla (B2)', async () => {
  const page = screen({ view: restoreView('database', 45), token: 't' });
  ok(!page.ui.cancel.hidden, '«Cancelar» no se ofrece en una fase que se puede cancelar');
  await page.next(page.jobsReply([restoreView('database', 50)], { manager: false, nonces: [] }));
  ok(page.ui.cancel.hidden, '«Cancelar» visible sin sesión de gestión');
  await page.next(page.jobsReply([restoreView('database', 52)], { manager: true }));
  ok(!page.ui.cancel.hidden, '«Cancelar» no vuelve con la sesión');
  await page.next(GATE());
  ok(page.ui.cancel.hidden, '«Cancelar» visible mientras el sitio responde 503');
});

await scenario('P18 · tras terminar con nonces nuevos, «Respaldar ahora» encola con el nuevo (B1)', async () => {
  const page = screen({ view: restoreView('cleanup', 98), token: 't' });
  await page.next(page.jobsReply([restoreDone()], { manager: true, nonces: { jobs: 'jobs-nueva', manual: 'manual-nueva', restore: 'restore-nueva' } }));
  ok(page.submit.disabled === false, '«Respaldar ahora» no vuelve al terminar');
  page.timers.clear();
  page.replies.push({ body: { success: true, data: { id: MINE, view: backupView('preflight', 0, { status: 'queued', status_label: 'Pendiente', running: false }) } } });
  page.form.listeners.submit[0]({ preventDefault() {} });
  await settle();
  const sent = page.requests[page.requests.length - 1];
  ok(sent.action === 'digitalisimo_backups_manual_start' && sent.nonce === 'manual-nueva', '«Respaldar ahora» encola con el nonce viejo: ' + JSON.stringify(sent));
});

await scenario('P19 · cinco 503 seguidos: no aconseja recargar (B6)', async () => {
  const page = screen({ view: restoreView('database', 45) });
  for (let i = 0; i < 5; i++) await page.next(GATE());
  ok(!/recarga esta página/.test(page.text(page.ui.note)) && /No recargues/.test(page.text(page.ui.note)) && page.ui.note.getAttribute('data-level') === 'error', 'Tras cinco 503 aconseja recargar (y perder el panel): ' + page.text(page.ui.note));
});

await scenario('P20 · «Volver a la copia previa» tras restaurar por partes lleva esas partes; entera, avisa de lo que se pierde (M1)', async () => {
  const parts = screen({ view: restoreView('files', 70, { parts: 'el plugin demo', parts_spec: { parts: [], plugins: ['demo'], themes: [], sites: [] }, untouched: 'base de datos, núcleo de WordPress, los demás plugins, temas, medios, mu-plugins, resto de wp-content' }), token: 't' });
  await parts.next(parts.jobsReply([restoreDone({ parts: 'el plugin demo', parts_spec: { parts: [], plugins: ['demo'], themes: [], sites: [] }, untouched: 'base de datos, núcleo de WordPress, los demás plugins, temas, medios, mu-plugins, resto de wp-content' })]));
  const link = new URL(parts.ui.safety.getAttribute('href'));
  ok(link.searchParams.get('restore') === 'safety-before-restore-x.zip' && link.searchParams.get('undo') === RID && link.searchParams.get('undo_plugins') === 'demo' && link.searchParams.get('parts') === '1' && !link.searchParams.has('undo_parts'), '«Volver a la copia previa» de una restauración por partes no lleva esas partes: ' + link);
  ok(/sólo el plugin demo/.test(parts.text(parts.ui.safety)) && /no se toca base de datos/.test(parts.text(parts.ui.result)) && !/se pierde lo que cambió después/.test(parts.text(parts.ui.result)), 'El resultado no dice que sólo vuelve lo restaurado y qué no se toca: ' + parts.text(parts.ui.result));
  const whole = screen({ view: restoreView('files', 70), token: 't' });
  await whole.next(whole.jobsReply([restoreDone()]));
  const full = new URL(whole.ui.safety.getAttribute('href'));
  ok(full.searchParams.get('undo') === RID && !full.searchParams.has('parts') && !full.searchParams.has('undo_plugins') && /se pierde lo que cambió después/.test(whole.text(whole.ui.result)) && /entera/.test(whole.ui.safety.getAttribute('title')), 'Restaurada entera, «Volver a la copia previa» no avisa de lo que se pierde: ' + whole.text(whole.ui.result));
});

await scenario('P21 · accesibilidad: el panel es una región; la nota y el resultado, regiones vivas siempre en la página (B7)', async () => {
  ok(/id="digitalisimo-backups-manual-progress"[^>]*role="region" aria-labelledby=/.test(markup.replace(/' \. \$id \. '/g, 'digitalisimo-backups-progress').replace(/' \. \( [^)]*\) \. '/g, '')) || /data-state="idle" role="region" aria-labelledby=/.test(markup), 'El panel no tiene role="region" con su nombre');
  ok(/-note" class="[^"]*" role="status" aria-live="polite"><\/p>/.test(markup) && /-result" class="[^"]*" aria-live="polite"><\/div>/.test(markup), 'La nota o el resultado (regiones vivas) se pintan ocultos');
  const page = screen({ view: restoreView('database', 45), token: 't' });
  await page.next(GATE());
  ok(!page.ui.note.hidden && page.text(page.ui.note) !== '', 'La nota se escribe en una región oculta');
  await page.next(page.jobsReply([restoreDone()]));
  ok(!page.ui.result.hidden && page.text(page.ui.result) !== '' && page.text(page.ui.note) === '' && !page.ui.note.hidden, 'El resultado se escribe oculto o la nota se oculta al vaciarse');
});

await scenario('P22 · un doble clic en «Restaurar» manda una sola restauración (B10)', async () => {
  const page = screen();
  const restoreForm = page.document.children[0].appendChild(new El('form'));
  restoreForm.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_restore' }));
  const button = restoreForm.appendChild(new El('button', { class: 'button button-primary' }));
  const send = () => { const event = { target: restoreForm, defaultPrevented: false, prevented: false, preventDefault() { this.prevented = true; } }; for (const listener of page.document.listeners.submit || []) listener(event); return event; };
  const first = send();
  const second = send();
  ok(!first.prevented && second.prevented && button.getAttribute('aria-disabled') === 'true', 'El segundo envío de «Restaurar» no se detiene: ' + JSON.stringify([first.prevented, second.prevented]));
});

await scenario('P23 · «finished» con otra restauración del mismo testigo (la del punto reconstruido) la enseña (J13)', async () => {
  const chainView = { id: CHAIN, kind: 'chain', status: 'running', status_label: 'En curso', phase: 'handoff', phase_label: 'Entrega', percent: 98, detail: '', terminal: false, running: true, retry_in: 0, can_cancel: false, origin_label: 'Restaurar un punto incremental', destination_label: 'x', created_label: '2026-11-15 10:00', track: [['preflight', 'Comprobaciones', 0], ['build', 'Reconstrucción', 5], ['seal', 'Cierre', 92], ['handoff', 'Entrega', 98]], elapsed: 30, restore_job: '' };
  const page = screen({ view: chainView, token: 't' });
  await page.next({ body: { success: true, data: { result: 'finished', manager: true, nonces: {}, message: '', jobs: [restoreDone({ from_point: true })] } } });
  ok(page.text(page.ui.title) === 'Restauración terminada' && page.text(page.ui.percent) === '100 %', 'Terminada la restauración del punto por otro proceso, el panel sigue en la reconstrucción: ' + page.text(page.ui.title));
});

/* ------------------------------------------------------------------ Verificación de los arreglos (N1–N8) */

const runIntervals = async (page, ms) => { page.clock.now += ms; for (const run of page.intervals) run(); await settle(); };

await scenario('P24 · sólo el 503 de la puerta de Backups es de una restauración; otro, «el servidor no responde»; una de hace días no se enseña (N2)', async () => {
  // Siguiendo una restauración: un 503 sin la marca (el hosting, una actualización de WordPress) no se atribuye a ella.
  const page = screen({ view: restoreView('database', 45) });
  await page.next(OTHER503());
  ok(/El servidor no responde \(HTTP 503/.test(page.text(page.ui.note)) && !/mantenimiento/.test(page.text(page.ui.note)) && page.pending()[0].delay === 2000, 'Un 503 sin la marca de Backups se atribuye a la restauración: ' + page.text(page.ui.note));
  await page.next(GATE());
  ok(/mantenimiento por la restauración/.test(page.text(page.ui.note)) && /sólo lo recibe el navegador que la empezó/.test(page.text(page.ui.note)) && page.pending()[0].delay === 30000, 'El 503 de la puerta de Backups no se explica como el de la restauración: ' + page.text(page.ui.note));
  // Sin trabajos: un 503 ajeno en la consulta no abre el panel; a los 30 s, la lista con una restauración de hace días tampoco.
  const idle = screen({ card: true, active: false });
  idle.replies.push(OTHER503());
  await runIntervals(idle, 31000);
  ok(idle.panel.hidden && idle.text(idle.ui.note) === '', 'Un 503 ajeno en la consulta del estado abre el panel de la restauración: ' + idle.text(idle.ui.title));
  idle.replies.push(GATE());
  await runIntervals(idle, 31000);
  ok(!idle.panel.hidden && idle.panel.getAttribute('data-state') === 'maintenance', 'El 503 de la puerta de Backups en la consulta no se dice');
  const old = restoreDone({ id: '99999999-8888-4777-8666-555555555555', created_label: '2026-11-12 09:00', elapsed: 300, finished_ago: 3 * 86400 });
  idle.replies.push(idle.jobsReply([old]));
  await runIntervals(idle, 31000);
  ok(idle.panel.hidden && idle.text(idle.ui.title) !== 'Restauración terminada', 'Tras el mantenimiento, el panel enseña como recién terminada una restauración de hace días: ' + idle.text(idle.ui.title));
});

await scenario('P25 · «Respaldar ahora» con la consulta del estado en vuelo: el primer paso se aplaza, no se pierde (N3)', async () => {
  const page = screen({ card: true, active: false });
  let release;
  page.replies.push({ defer: new Promise((resolve) => { release = resolve; }) });
  await runIntervals(page, 31000);
  ok(page.requests.length === 1 && page.requests[0].action === 'digitalisimo_backups_job_status', 'La consulta del estado no salió');
  page.replies.push({ body: { success: true, data: { id: MINE, view: backupView('preflight', 0, { status: 'queued', status_label: 'Pendiente', running: false, detail: 'En cola.' }) } } });
  page.form.listeners.submit[0]({ preventDefault() {} });
  await settle();
  const first = await page.next();
  ok(first === 0 && page.pending().length === 1 && page.pending()[0].delay === 500, 'El primer paso del respaldo, con la consulta en vuelo, se perdió: ' + JSON.stringify(page.pending()));
  release({ ok: true, status: 200, headers: { get: () => null }, text: () => Promise.resolve(JSON.stringify({ success: true, data: { jobs: [] } })) });
  await settle();
  ok(page.pending().length === 1, 'Al volver la consulta (sin el respaldo todavía), no queda un paso programado');
  await page.next(page.jobsReply([backupView('database', 12)]));
  ok(page.requests.filter((r) => r.action === 'digitalisimo_backups_job_tick').length === 1 && page.text(page.ui.percent) === '12 %', 'El respaldo no avanza tras la consulta: ' + page.text(page.message));
});

await scenario('P26 · la consulta del estado: cada 30 s, sólo con la pestaña a la vista y ligera (N6, K12)', async () => {
  const page = screen({ card: true, active: false });
  await runIntervals(page, 29000);
  ok(page.requests.length === 0, 'La consulta del estado sale antes de los 30 s');
  page.replies.push(page.jobsReply([]));
  await runIntervals(page, 1500);
  ok(page.requests.length === 1 && page.requests[0].action === 'digitalisimo_backups_job_status' && page.requests[0].peek === '1', 'La consulta del estado no sale a los 30 s o no es la ligera: ' + JSON.stringify(page.requests));
  const hidden = screen({ card: true, active: false, visibility: 'hidden' });
  await runIntervals(hidden, 120000);
  ok(hidden.requests.length === 0, 'Con la pestaña oculta la pantalla sigue consultando el estado');
});

await scenario('P27 · «Restaurar»: si la navegación se cancela, a los 60 s vuelve a estar disponible (N7); a los 40 s, qué hacer (K15)', async () => {
  const page = screen();
  const restoreForm = page.document.children[0].appendChild(new El('form'));
  restoreForm.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_restore' }));
  const button = restoreForm.appendChild(new El('button', { class: 'button button-primary' }));
  const send = () => { const event = { target: restoreForm, defaultPrevented: false, prevented: false, preventDefault() { this.prevented = true; } }; for (const listener of page.document.listeners.submit || []) listener(event); return event; };
  send();
  // Mientras tanto la consulta del estado sigue (cada 30 s): si la restauración empezó, el panel la tomaría.
  for (let i = 0; i < 3; i++) page.replies.push(page.jobsReply([]));
  await runIntervals(page, 39000);
  ok(page.text(page.ui.note) === '', 'La nota de «tarda más de lo normal» sale antes de los 40 s');
  await runIntervals(page, 2000);
  ok(/tarda más de lo normal/.test(page.text(page.ui.note)), 'Pasados 40 s, el panel no dice qué hacer si la petición no vuelve');
  ok(send().prevented && restoreForm.getAttribute('data-sent') === '1', 'Antes de 60 s, un segundo envío no se detiene');
  await runIntervals(page, 20000);
  ok(!restoreForm.hasAttribute('data-sent') && !button.hasAttribute('aria-disabled') && !send().prevented, 'Cancelada la navegación, «Restaurar» sigue bloqueado');
});

await scenario('P28 · la nota se ve aunque el marcado la traiga oculta (una página en caché; K10)', async () => {
  const page = screen({ view: restoreView('database', 45) });
  page.ui.note.hidden = true;
  await page.next(GATE());
  ok(!page.ui.note.hidden && /mantenimiento/.test(page.text(page.ui.note)), 'La nota se escribe en una región oculta');
});

/* ------------------------------------------------------------------ Lo que trae un respaldo externo o lo envía (2026-11-16) */

const IMPORT_TRACK = [['unpack', 'Descompresión', 2], ['scan', 'Comprobación del archivo', 20], ['database', 'Base de datos', 25], ['files', 'ZIP convertido', 50], ['finish', 'Manifiesto y publicación', 94]];
const FETCH_TRACK = [['preflight', 'Conexión con el destino', 0], ['receive', 'Descarga del destino', 5], ['examine', 'Comprobación del archivo', 85], ['place', 'Entrada en el almacén', 98]];
const SEND_TRACK = [['preflight', 'Comprobación del respaldo', 0], ['deliver', 'Copia al destino', 5]];
const IMP = '66666666-7777-4888-8999-aaaaaaaaaaaa';
const FET = '77777777-8888-4999-8aaa-bbbbbbbbbbbb';
const SND = '88888888-9999-4aaa-8bbb-cccccccccccc';
const importView = (phase, percent, over = {}) => Object.assign({ id: IMP, kind: 'import', status: ['scan', 'finish'].includes(phase) ? 'verifying' : 'running', status_label: ['scan', 'finish'].includes(phase) ? 'Verificando' : 'En curso', phase, phase_label: Object.fromEntries(IMPORT_TRACK.map(([k, l]) => [k, l]))[phase], percent, detail: 'Escribiendo el ZIP convertido: 120 de 900 archivos <b>x</b>', message: '', terminal: false, running: true, retry_in: 0, can_cancel: phase !== 'finish', origin_label: 'Importación de Backuply', destination_label: 'backup_sitio_2026.tar.gz', created_label: '2026-11-16 10:00', track: IMPORT_TRACK, elapsed: 40, notices: [] }, over);
const importDone = (over = {}) => importView('finish', 100, Object.assign({ status: 'success', status_label: 'Completo', phase_label: '', terminal: true, running: false, can_cancel: false, file: 'digitalisimo-backuply-backup_sitio_2026.zip', detail: 'Respaldo de Backuply importado como digitalisimo-backuply-backup_sitio_2026.zip: aparece en Herramientas → «Restaurar un archivo suelto» como archivo sin firma (escribe RESTAURAR SIN FIRMA para restaurarlo).', notices: ['No se importaron vistas ni disparadores.'] }, over));
const fetchView = (phase, percent, over = {}) => Object.assign({ id: FET, kind: 'fetch', status: phase === 'receive' ? 'transferring' : 'verifying', status_label: phase === 'receive' ? 'Transfiriendo' : 'Verificando', phase, phase_label: Object.fromEntries(FETCH_TRACK.map(([k, l]) => [k, l]))[phase], percent, detail: 'Descargando: 40,0 MB de 120,0 MB…', message: '', terminal: false, running: true, retry_in: 0, can_cancel: phase !== 'place', origin_label: 'Traer de un destino remoto', destination_label: 'desde SFTP', created_label: '2026-11-16 10:00', track: FETCH_TRACK, elapsed: 30, fetch: { name: 'digitalisimo-sitio-2026-11-10.zip', connection: 'sftp', registered: true } }, over);
const sendView = (phase, percent, over = {}) => Object.assign({ id: SND, kind: 'send', status: 'transferring', status_label: 'Transfiriendo', phase, phase_label: Object.fromEntries(SEND_TRACK.map(([k, l]) => [k, l]))[phase], percent, detail: 'Copiando a SFTP…', message: '', terminal: false, running: true, retry_in: 0, can_cancel: true, origin_label: 'Enviar a un destino: digitalisimo-sitio.zip', destination_label: 'SFTP', created_label: '2026-11-16 10:00', track: SEND_TRACK, elapsed: 12 }, over);
const TOOLS = 'https://example.test/wp-admin/admin.php?page=digitalisimo-backups&tab=tools&open=import&backup=ok&message=x&job=' + IMP + '#digitalisimo-backups-section-import';

await scenario('P29 · una importación de Backuply se sigue en el mismo panel, en su sección, con sus fases y su resultado', async () => {
  const page = screen({ view: importView('files', 60), card: false, sections: [['import', 'import'], ['upload', 'upload']], href: TOOLS });
  ok(page.panel.parentNode === page.slots.import && page.slots.import.section.open === true, 'La importación no se sigue en su sección (abierta)');
  ok(!page.panel.hidden && page.text(page.ui.title) === 'Importación en curso' && page.text(page.ui.what) === 'Importación de Backuply · backup_sitio_2026.tar.gz' && page.text(page.ui.percent) === '60 %', 'La importación no se ve en el panel grande: ' + page.text(page.ui.title) + ' / ' + page.text(page.ui.what));
  ok(JSON.stringify(page.phases()) === JSON.stringify(['unpack:done', 'scan:done', 'database:done', 'files:current', 'finish:pending']) && page.text(page.message) === 'En curso · ZIP convertido (fase 4 de 5)' && page.text(page.detail).includes('<b>x</b>') && page.ui.ticks.children.length === 5, 'Las fases de la importación no se ven: ' + page.phases());
  ok(!page.ui.cancel.hidden && page.ui.help.hidden && page.bar.getAttribute('aria-valuenow') === '60', '«Cancelar» no se ofrece en una fase que se puede cancelar (o la barra no dice su valor)');
  // Un reintento vuelve el % al comienzo de su fase: la barra no retrocede; un corte de red se dice.
  await page.next(page.jobsReply([importView('files', 50, { running: false, retry_in: 10, detail: 'Reintento a las 10:20 (2 de 3)' })], { result: 'idle' }));
  ok(page.text(page.ui.percent) === '60 %' && /Reintento a las 10:20/.test(page.text(page.detail)), 'Un reintento bajó la barra de la importación');
  await page.next({ reject: true });
  ok(/Sin conexión con el servidor/.test(page.text(page.ui.note)) && /El trabajo sigue en el servidor aunque esta pestaña no lo vea/.test(page.text(page.ui.note)) && page.ui.cancel.hidden && page.pending().length === 1, 'Un corte de red durante la importación no se dice o se deja de reintentar: ' + page.text(page.ui.note));
  await page.next(page.jobsReply([importView('finish', 96)]));
  ok(page.text(page.ui.note) === '' && page.ui.cancel.hidden && page.phases().includes('finish:current'), 'Tras volver la conexión la nota sigue o «Cancelar» se ofrece en la fase que no se cancela');
  await page.next(page.jobsReply([importDone()]));
  ok(page.text(page.ui.title) === 'Importación terminada' && page.text(page.ui.percent) === '100 %' && page.phases().every((p) => p.endsWith(':done')) && page.text(page.detail) === '', 'La importación terminada no se ve al 100 % con todas sus fases');
  const result = page.text(page.ui.result);
  ok(result.includes('importado como digitalisimo-backuply-backup_sitio_2026.zip') && result.includes('Avisos: No se importaron vistas'), 'El resultado no dice qué se importó, dónde quedó ni sus avisos: ' + result);
  const go = new URL(page.ui.go.getAttribute('href'));
  ok(!page.ui.go.hidden && page.text(page.ui.go) === 'Ver en «Restaurar un archivo suelto»' && go.searchParams.get('tab') === 'tools' && go.searchParams.get('open') === 'restore-file' && go.hash === '#digitalisimo-backups-section-restore-file' && !go.searchParams.has('job') && !go.searchParams.has('message'), 'El resultado no lleva a «Restaurar un archivo suelto»: ' + page.ui.go.getAttribute('href'));
  ok(page.reloads + page.replaced.length === 0 && page.timers.size === 0, 'Al terminar la importación la página se recargó (y se perdió el resultado) o sigue consultando');
});

await scenario('P30 · «Traer al almacén»: bajo el botón, con su destino, sus cortes, su resultado y la fila para restaurarlo', async () => {
  const page = screen({ view: fetchView('receive', 40) });
  ok(page.panel.parentNode.getAttribute('class').includes('digitalisimo-backups-home') && page.text(page.ui.title) === 'Trayendo el respaldo del destino' && page.text(page.ui.what) === 'Traer de un destino remoto · digitalisimo-sitio-2026-11-10.zip desde SFTP', 'La descarga no se sigue bajo «Respaldar ahora»: ' + page.text(page.ui.what));
  ok(JSON.stringify(page.phases()) === JSON.stringify(['preflight:done', 'receive:current', 'examine:pending', 'place:pending']) && page.text(page.message) === 'Transfiriendo · Descarga del destino (fase 2 de 4)', 'Las fases de «Traer al almacén» no se ven: ' + page.phases());
  await page.next(page.jobsReply([fetchView('examine', 88, { detail: 'Comprobando el archivo como no confiable…' })]));
  await page.next(page.jobsReply([fetchView('place', 100, { status: 'success', status_label: 'Completo', phase_label: '', terminal: true, running: false, can_cancel: false, file: 'digitalisimo-sitio-2026-11-10.zip', detail: 'Copia de SFTP descargada al almacén privado y verificada por el SHA-256 de su historial: digitalisimo-sitio-2026-11-10.zip. Ya se puede restaurar.' })]));
  const go = new URL(page.ui.go.getAttribute('href'));
  ok(page.text(page.ui.title) === 'Respaldo traído al almacén' && /Ya se puede restaurar/.test(page.text(page.ui.result)) && page.text(page.ui.go) === 'Restaurarlo' && go.searchParams.get('restore') === 'digitalisimo-sitio-2026-11-10.zip' && go.searchParams.get('tab') === 'backups' && !page.ui.reload.hidden && page.reloads + page.replaced.length === 0, 'Traído, el panel no lleva a su fila para restaurarlo: ' + page.ui.go.getAttribute('href'));
  // Sin registro (de otro sitio): a «Restaurar un archivo suelto».
  const other = screen({ view: fetchView('place', 99, { fetch: { name: 'digitalisimo-otro.zip', connection: 'sftp', registered: false } }) });
  await other.next(other.jobsReply([fetchView('place', 100, { fetch: { name: 'digitalisimo-otro.zip', connection: 'sftp', registered: false }, status: 'success', status_label: 'Completo', phase_label: '', terminal: true, running: false, can_cancel: false, file: 'digitalisimo-otro.zip', detail: 'Respaldo de SFTP traído al almacén como «sin registro»…' })]));
  ok(other.text(other.ui.go) === 'Ver en «Restaurar un archivo suelto»' && new URL(other.ui.go.getAttribute('href')).searchParams.get('open') === 'restore-file', 'Lo traído sin registro no lleva a «Restaurar un archivo suelto»');
  // La sesión o el nonce dejan de valer: lo dice, ofrece entrar y deja de consultar (no hay testigo para esto).
  const lostSession = screen({ view: fetchView('receive', 40) });
  await lostSession.next({ status: 400, body: '-1' });
  ok(/su sesión o su nonce ya no valen/.test(lostSession.text(lostSession.ui.note)) && !lostSession.ui.login.hidden && lostSession.timers.size === 0, 'Sin sesión, la descarga no lo dice o sigue consultando: ' + lostSession.text(lostSession.ui.note));
});

await scenario('P31 · «Enviar a un destino»: el mismo panel, su resultado y la lista a petición', async () => {
  const page = screen({ view: sendView('deliver', 40) });
  ok(page.text(page.ui.title) === 'Enviando el respaldo a un destino' && JSON.stringify(page.phases()) === JSON.stringify(['preflight:done', 'deliver:current']) && !page.ui.cancel.hidden, 'El envío no se ve en el panel con sus fases: ' + page.phases());
  page.replies.push(page.jobsReply([sendView('deliver', 70, { cancel_requested: true, can_cancel: false })]));
  page.ui.cancel.listeners.click[0]({ preventDefault() {} });
  await settle();
  const cancel = page.requests[page.requests.length - 1];
  ok(/Cancelar el envío\? Lo copiado a medias en el destino se retira/.test(page.confirms[0] || '') && cancel.action === 'digitalisimo_backups_job_cancel' && cancel.id === SND && page.ui.cancel.hidden, 'Cancelar el envío no dice qué pasa o no lo pide a la cola: ' + page.confirms[0]);
  const done = screen({ view: sendView('deliver', 90) });
  await done.next(done.jobsReply([sendView('deliver', 100, { status: 'success', status_label: 'Completo', phase_label: '', terminal: true, running: false, can_cancel: false, file: 'digitalisimo-sitio.zip', detail: 'Copia en SFTP verificada: digitalisimo-sitio.zip.' })]));
  ok(done.text(done.ui.title) === 'Copia enviada y verificada' && !done.ui.reload.hidden && done.ui.go.hidden && done.reloads + done.replaced.length === 0, 'El envío terminado no se dice o recarga la página');
  const bad = screen({ view: sendView('deliver', 90) });
  await bad.next(bad.jobsReply([sendView('deliver', 50, { status: 'failed', status_label: 'Fallido', phase_label: '', terminal: true, running: false, can_cancel: false, detail: 'La copia a SFTP falló: sin espacio.' })]));
  ok(bad.text(bad.ui.title) === 'El envío no se completó' && /La copia a medias se retiró del destino/.test(bad.text(bad.ui.result)) && bad.text(bad.ui.percent) === '90 %', 'Un envío fallido no dice qué pasó o baja la barra');
});

await scenario('P32 · una importación o descarga empezada en otra pestaña: el panel la toma si no enseña nada', async () => {
  const idle = screen({ card: true, active: false });
  idle.replies.push(idle.jobsReply([fetchView('receive', 20)]));
  await runIntervals(idle, 31000);
  ok(!idle.panel.hidden && idle.text(idle.ui.title) === 'Trayendo el respaldo del destino' && idle.pending().length === 1, 'Una descarga en curso que empezó en otra pestaña no entra al panel');
  // El panel enseña ya un resultado: no lo tapa.
  const busy = screen({ view: importDone(), card: true, active: false });
  busy.replies.push(busy.jobsReply([fetchView('receive', 20), importDone()]));
  await runIntervals(busy, 31000);
  ok(busy.text(busy.ui.title) === 'Importación terminada', 'Una descarga de otra pestaña tapó el resultado de la importación');
});

await scenario('P33 · importar, traer, enviar o agregar al listado: el panel dice ya que empieza, junto al formulario', async () => {
  const page = screen({ card: false, sections: [['import', 'import'], ['restore-file', 'restore-file']] });
  const importForm = page.slots.import.section.appendChild(new El('form'));
  importForm.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_backuply_import' }));
  const send = (form) => { const event = { target: form, defaultPrevented: false, prevented: false, preventDefault() { this.prevented = true; } }; for (const listener of page.document.listeners.submit || []) listener(event); return event; };
  send(importForm);
  ok(page.panel.parentNode === page.slots.import && page.slots.import.section.open && page.panel.getAttribute('data-state') === 'starting' && page.text(page.message) === 'Encolando la importación de Backuply…' && !page.bar.hasAttribute('aria-valuenow'), 'Al pedir la importación el panel no dice que empieza junto a ella');
  ok(send(importForm).prevented, 'Un doble clic manda dos importaciones');
  const register = page.slots['restore-file'].section.appendChild(new El('form'));
  register.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_register_loose' }));
  send(register);
  ok(page.panel.parentNode === page.slots['restore-file'] && page.text(page.ui.title) === 'Agregando a la lista' && JSON.stringify(page.phases()) === JSON.stringify(['check:current', 'register:pending']) && /sin pasos que medir/.test(page.text(page.detail)), '«Agregar al listado» no dice que comprueba (y sus fases): ' + page.phases());
  page.replies.push(page.jobsReply([]));
  await runIntervals(page, 41000);
  ok(/Lleva 41 s/.test(page.text(page.ui.time)) && /wp digitalisimo-backups register/.test(page.text(page.ui.note)), 'Pasados 40 s, «Agregar al listado» no dice qué hacer ni cuánto lleva: ' + page.text(page.ui.note) + ' / ' + page.text(page.ui.time));
  const home = screen();
  const fetchForm = home.document.children[0].appendChild(new El('form'));
  fetchForm.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_fetch' }));
  for (const listener of home.document.listeners.submit || []) listener({ target: fetchForm, defaultPrevented: false, preventDefault() {} });
  ok(home.text(home.message) === 'Empezando la descarga desde el destino…' && home.panel.parentNode.getAttribute('class').includes('digitalisimo-backups-home'), '«Traer al almacén» no dice que empieza bajo el botón');
});

if (failed.length) throw new Error('FALLA: ' + failed.length + ' caso(s) del panel de avance: ' + failed.join(' | '));
console.log(`DIGITALÍSIMO Backups: un solo panel de avance a todo lo ancho para respaldo y restauración (fases, %, paso, tiempo y resultado), que sobrevive al 503, a cortes, a un nonce nuevo y a recargar (${checks} comprobaciones).`);
