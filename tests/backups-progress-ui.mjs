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
const settle = async () => { for (let i = 0; i < 8; i++) await flush(); };

/* ------------------------------------------------------------------ La pantalla */
const IDS = ['title', 'percent', 'what', 'fill', 'ticks', 'phases', 'time', 'note', 'result', 'cancel', 'reload', 'safety', 'login', 'rollback', 'help'];
function screen({ view = null, token = '', strip = false, card = true, active = false } = {}) {
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
  panel.appendChild(new El('p', { id: P + 'note', hidden: '' }));
  panel.appendChild(new El('div', { id: P + 'result', hidden: '' }));
  const actions = panel.appendChild(new El('div'));
  actions.appendChild(new El('button', { id: P + 'cancel', type: 'button', hidden: '' }));
  actions.appendChild(new El('button', { id: P + 'reload', type: 'button', hidden: '' }));
  actions.appendChild(new El('a', { id: P + 'safety', href: 'https://example.test/wp-admin/admin.php?page=digitalisimo-backups', hidden: '' }));
  actions.appendChild(new El('a', { id: P + 'login', href: 'https://example.test/wp-login.php', hidden: '' }));
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
  const byId = (id) => { const found = document.querySelectorAll('#' + id); return found[0] || null; };
  const own = { requests: [], replies: [], timers: new Map(), seq: 0, reloads: 0, replaced: [], confirms: [], alerts: [] };
  const documentApi = {
    getElementById: byId,
    createElement: (tag) => new El(tag),
    createTextNode: (text) => new Text(text),
    querySelector: (selector) => document.querySelector(selector),
    addEventListener: (type, listener) => document.addEventListener(type, listener),
  };
  const sandbox = {
    window: { digitalisimoBackupsManual: { url: '/wp-admin/admin-ajax.php', nonce: 'nonce-manual', jobsNonce: 'nonce-jobs-vieja', context: 'site', login: '/wp-login.php?migrar' }, confirm: (text) => { own.confirms.push(text); return true; }, alert: (text) => own.alerts.push(text), location: { href: 'https://example.test/wp-admin/admin.php?page=digitalisimo-backups&tab=backups&backup=ok&message=Restauraci%C3%B3n%20en%20curso', reload: () => own.reloads++, replace: (url) => own.replaced.push(url) } },
    document: documentApi,
    URL, URLSearchParams, Object, String, Number, Math, Error, JSON, Date, Array,
    setTimeout: (callback, delay) => { const id = ++own.seq; own.timers.set(id, { callback, delay }); return id; },
    clearTimeout: (id) => { own.timers.delete(id); },
    fetch: (url, options) => {
      const body = Object.fromEntries(new URLSearchParams(options.body));
      own.requests.push(body);
      const reply = own.replies.shift();
      if (reply === undefined) throw new Error('Petición inesperada: ' + body.action);
      if (reply.reject) return Promise.reject(new TypeError('Failed to fetch'));
      return Promise.resolve({ ok: (reply.status || 200) < 400, status: reply.status || 200, text: () => Promise.resolve(typeof reply.body === 'string' ? reply.body : JSON.stringify(reply.body)) });
    },
  };
  if (token) sandbox.window.digitalisimoBackupsRestore = { token, action: 'digitalisimo_backups_restore_tick' };
  sandbox.window.document = documentApi;
  runInNewContext(script, sandbox);
  const P2 = (name) => byId(P + name);
  Object.assign(own, { document, panel, bar, form, submit, card: cardNode, strip: stripNode, ui: Object.fromEntries(IDS.map((name) => [name, P2(name)])), message: byId('digitalisimo-backups-manual-message'), detail: byId('digitalisimo-backups-manual-detail'), config: sandbox.window.digitalisimoBackupsManual });
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
  ok(!page.ui.reload.hidden && page.ui.cancel.hidden && page.ui.help.hidden && page.reloads + page.replaced.length === 0 && page.timers.size === 0, 'Al terminar la restauración la página se recargó (y se perdió el resultado) o sigue consultando');
  ok(page.submit.disabled === false && /duró 5 min 12 s/.test(page.text(page.ui.time)), '«Respaldar ahora» no vuelve o el tiempo no dice cuánto duró: ' + page.text(page.ui.time));
  page.ui.reload.listeners.click[0]({});
  ok(page.replaced.length === 1 && !/backup=|message=/.test(page.replaced[0]), '«Ver la lista actualizada» no recarga sin el aviso viejo: ' + page.replaced);
});

await scenario('P3 · por partes: dice qué se restaura', async () => {
  const page = screen({ view: restoreView('files', 70, { parts: 'el plugin demo' }), token: 't' });
  ok(page.text(page.ui.what).endsWith('se restaura sólo: el plugin demo'), 'Restaurar por partes no dice qué parte: ' + page.text(page.ui.what));
});

await scenario('P4 · 503 del mantenimiento, corte de red y 502: lo dice, sigue consultando y la nota se va al volver', async () => {
  const page = screen({ view: restoreView('database', 45) });
  ok(page.pending().length === 1, 'Al cargar con una restauración en curso no se programa el sondeo');
  let delay = await page.next({ status: 503, body: '<html>Mantenimiento</html>' });
  ok(page.requests[0].action === 'digitalisimo_backups_job_tick' && !page.ui.note.hidden && /mantenimiento/.test(page.text(page.ui.note)) && page.pending()[0].delay === 30000, 'Un 503 no se explica o no se consulta cada 30 s: ' + page.text(page.ui.note));
  ok(page.text(page.ui.percent) === '45 %', 'La barra cambió con el 503');
  for (let i = 0; i < 3; i++) delay = await page.next({ reject: true });
  ok(/Sin conexión con el servidor/.test(page.text(page.ui.note)) && page.pending().length === 1, 'Un corte de red no se explica o se deja de reintentar: ' + page.text(page.ui.note));
  delay = await page.next({ status: 502, body: '<html>Bad gateway</html>' });
  ok(/HTTP 502/.test(page.text(page.ui.note)) && /recarga esta página/.test(page.text(page.ui.note)) && page.ui.note.getAttribute('data-level') === 'error', 'Tras 5 fallos no dice qué hacer: ' + page.text(page.ui.note));
  await page.next(page.jobsReply([restoreView('database', 52)]));
  ok(page.ui.note.hidden && page.text(page.ui.percent) === '52 %', 'Al volver la conexión la nota no se va o el avance no sigue');
});

await scenario('P5 · un nonce que dejó de valer pasa al tick por testigo y toma los nonces nuevos', async () => {
  const page = screen({ view: restoreView('switch', 87) });
  await page.next({ status: 400, body: '-1' });
  ok(!/inicia sesión/i.test(page.text(page.ui.note)) && /testigo de este navegador/.test(page.text(page.ui.note)) && page.pending().length === 1, 'Un nonce que dejó de valer detiene el sondeo: ' + page.text(page.ui.note));
  await page.next(page.jobsReply([restoreView('finish', 95)], { nonces: { jobs: 'nonce-jobs-nueva', restore: 'nonce-restore-nueva' } }));
  const tick = page.requests[page.requests.length - 1];
  ok(tick.action === 'digitalisimo_backups_restore_tick' && tick.digitalisimo_backups_restore === '', 'Tras el «-1» no se pidió el tick por testigo (la cookie): ' + JSON.stringify(tick));
  ok(page.config.jobsNonce === 'nonce-jobs-nueva' && page.ui.rollback.querySelector('input[name="_wpnonce"]').value === 'nonce-restore-nueva', 'Los nonces nuevos no se usan: ' + page.config.jobsNonce);
  ok(page.text(page.ui.percent) === '95 %' && page.ui.note.hidden, 'El avance no sigue con el testigo');
});

await scenario('P6 · recargar la página a mitad de la restauración vuelve al panel y sigue', async () => {
  const page = screen({ view: restoreView('files', 66, { elapsed: 3725 }), token: 'testigo-2' });
  ok(!page.panel.hidden && page.phases().includes('files:current') && page.text(page.ui.percent) === '66 %' && /lleva 1 h 2 min/.test(page.text(page.ui.time)), 'Al recargar el panel no vuelve en su fase y con su tiempo: ' + page.text(page.ui.time));
  ok(page.pending().length === 1 && page.pending()[0].delay === 300, 'Al recargar no sigue consultando enseguida');
  await page.next(page.jobsReply([restoreView('switch', 88)]));
  ok(page.phases().includes('switch:current'), 'Tras recargar el panel no avanza');
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
  ok(page.timers.size === 0, 'Terminada la vuelta, el panel sigue consultando');
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
  await done.next({ body: { success: true, data: { result: 'finished', message: 'Restauración terminada. Entra al escritorio para ver el resultado.', jobs: [{ id: RID, kind: 'restore', status: 'success', status_label: 'Completo', phase: '', phase_label: '', percent: 100, message: '', detail: 'Restauración terminada. Entra al escritorio para ver el resultado.', terminal: true, running: false, retry_in: 0, can_cancel: false, origin_label: 'Restauración', destination_label: '', created_label: '' }] } } });
  ok(done.text(done.ui.title) === 'Restauración terminada' && !done.ui.login.hidden && done.text(done.ui.result).includes('Entra al escritorio') && done.timers.size === 0, 'Terminada por otro proceso sin sesión, el panel no lo dice con «Iniciar sesión»');
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

if (failed.length) throw new Error('FALLA: ' + failed.length + ' caso(s) del panel de avance: ' + failed.join(' | '));
console.log(`DIGITALÍSIMO Backups: un solo panel de avance a todo lo ancho para respaldo y restauración (fases, %, paso, tiempo y resultado), que sobrevive al 503, a cortes, a un nonce nuevo y a recargar (${checks} comprobaciones).`);
