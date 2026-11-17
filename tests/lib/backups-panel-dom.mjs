// DOM mínimo (con selectores sencillos) y el panel de avance de la pantalla Backups, para cargar en la misma página
// simulada assets/manual-progress.js y los scripts que lo usan (assets/upload.js). Lo usan tests/backups-upload-ui.mjs y
// tests/backups-progress-ui.mjs. Sin dependencias.
export class Text { constructor(text) { this.text = String(text); this.parentNode = null; this.children = []; } get textContent() { return this.text; } set textContent(v) { this.text = String(v); } }
export class El {
  constructor(tag, attributes = {}) {
    this.tagName = tag.toUpperCase(); this.attributes = {}; this.children = []; this.parentNode = null; this.listeners = {};
    this.hidden = false; this.disabled = false; this.value = ''; this.style = {}; this.text = ''; this.open = false;
    for (const [name, value] of Object.entries(attributes)) this.setAttribute(name, value);
  }
  get id() { return this.attributes.id || ''; }
  setAttribute(name, value) { this.attributes[name] = String(value); if (name === 'hidden') this.hidden = true; if (name === 'value') this.value = String(value); if (name === 'open') this.open = true; }
  getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; }
  hasAttribute(name) { return name in this.attributes; }
  removeAttribute(name) { delete this.attributes[name]; }
  appendChild(child) { if (child.parentNode) child.parentNode.removeChild(child); child.parentNode = this; this.children.push(child); return child; }
  insertBefore(child, ref) { const at = this.children.indexOf(ref); if (at < 0) return this.appendChild(child); if (child.parentNode) child.parentNode.removeChild(child); child.parentNode = this; this.children.splice(at, 0, child); return child; }
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
export function matches(node, selector, scope = null) {
  return selector.split(',').some((alternative) => {
    const parts = alternative.trim().split(/\s+/);
    if (!compound(node, parts[parts.length - 1])) return false;
    let at = node.parentNode;
    for (let i = parts.length - 2; i >= 0; i--) { while (at instanceof El && at !== scope && !compound(at, parts[i])) at = at.parentNode; if (!(at instanceof El) || at === scope) return false; at = at.parentNode; }
    return true;
  });
}
export const flush = () => new Promise((resolve) => setImmediate(resolve));
export const settle = async (rounds = 8) => { for (let i = 0; i < rounds; i++) await flush(); };

/* Los identificadores del panel que usa el script (P = digitalisimo-backups-progress-). */
export const PANEL_IDS = ['title', 'percent', 'what', 'fill', 'ticks', 'phases', 'time', 'note', 'result', 'cancel', 'reload', 'safety', 'login', 'rollback', 'help', 'go', 'resume', 'import'];
/** El panel, como lo pinta Digitalisimo_Backups_Progress::panel(), dentro de $parent. */
export function buildPanel(parent, { view = null, card = false } = {}) {
  const P = 'digitalisimo-backups-progress-';
  const panel = parent.appendChild(new El('div', { id: 'digitalisimo-backups-manual-progress', class: 'digitalisimo-backups-manual-progress digitalisimo-backups-progress' + (card ? ' digitalisimo-backups-card' : ''), 'data-state': 'idle', role: 'region', hidden: '', 'data-login': 'https://example.test/wp-login.php' }));
  if (view) panel.setAttribute('data-view', JSON.stringify(view));
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
  const imported = actions.appendChild(new El('form', { id: P + 'import', hidden: '' }));
  imported.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_backuply_import' }));
  imported.appendChild(new El('input', { type: 'hidden', name: 'backuply_file', value: '' }));
  imported.appendChild(new El('input', { type: 'hidden', name: 'backuply_source', value: 'store' }));
  const rollback = actions.appendChild(new El('form', { id: P + 'rollback', hidden: '' }));
  rollback.appendChild(new El('input', { type: 'hidden', name: '_wpnonce', value: 'nonce-restore-vieja' }));
  rollback.appendChild(new El('input', { type: 'hidden', name: 'action', value: 'digitalisimo_backups_restore' }));
  rollback.appendChild(new El('input', { type: 'hidden', name: 'rollback_retry', value: '1' }));
  panel.appendChild(new El('details', { id: P + 'help', hidden: '' }));
  return panel;
}
/** La API de document que usan los scripts, sobre el árbol $document. */
export function documentApi(document, extra = {}) {
  const byId = (id) => document.querySelectorAll('#' + id)[0] || null;
  return Object.assign({
    getElementById: byId,
    createElement: (tag) => new El(tag),
    createTextNode: (text) => new Text(text),
    querySelector: (selector) => document.querySelector(selector),
    querySelectorAll: (selector) => document.querySelectorAll(selector),
    addEventListener: (type, listener) => document.addEventListener(type, listener),
  }, extra);
}
/** Las fases del panel como «fase:estado». */
export function phasesOf(document) {
  const list = document.querySelectorAll('#digitalisimo-backups-progress-phases')[0];
  return list ? list.children.map((li) => `${li.getAttribute('data-phase')}:${li.getAttribute('data-state')}`) : [];
}
