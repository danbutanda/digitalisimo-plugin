// «Qué restaurar» (restaurar por partes) en la pantalla de Backups (assets/screen.js): «Todo» marca o desmarca las
// partes; una parte entera y sus plugins o temas concretos se excluyen; el resumen («Se restaura sólo: …») y los avisos
// de coherencia siguen a lo marcado con las mismas reglas que el servidor (Digitalisimo_Backups_Restore_Parts::RULES,
// leídas del PHP). Todo como texto. Debe fallar con el código anterior: el script no conocía «Qué restaurar».
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync('digitalisimo-backups/assets/screen.js', 'utf8');
assert.ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(script), 'La pantalla debe pintar texto, no HTML');
assert.ok(script.includes('data-restore-parts'), 'El script no conoce «Qué restaurar» (restaurar por partes)');
const php = readFileSync('digitalisimo-backups/includes/restore/class-restore-parts.php', 'utf8');

// Las reglas de coherencia, del PHP: el servidor y la pantalla usan las mismas.
const block = php.slice(php.indexOf('const RULES = array('), php.indexOf(');', php.indexOf('const RULES = array(')));
const rules = [...block.matchAll(/'([a-z_]+)' => array\( 'any' => array\(([^)]*)\)(?:, 'none' => array\(([^)]*)\))?(?:, 'missing' => array\(([^)]*)\))?/g)]
  .map((m) => ({ code: m[1], any: words(m[2]), none: words(m[3]), missing: words(m[4]) }));
function words(text) { return [...(text || '').matchAll(/'([a-z-]+)'/g)].map((w) => w[1]); }
assert.equal(rules.length, 9, 'No se leyeron las nueve reglas de coherencia del PHP');

// DOM mínimo: atributos, casillas, texto, hidden, closest y querySelector(All) con selectores de atributos y etiquetas.
function matches(el, selector) {
  const tag = selector.match(/^[a-z]+/);
  if (tag && el.tag !== tag[0]) return false;
  for (const [, name, value] of selector.matchAll(/\[([a-z-]+)(?:="([^"]*)")?\]/g)) {
    if (!(name in el.attributes) && !(name === 'value' && el.value !== undefined)) return false;
    if (value !== undefined && String(name === 'value' ? el.value : el.attributes[name]) !== value) return false;
  }
  return true;
}
function make(tag, attributes = {}, children = [], text = '') {
  const el = {
    tag, attributes: { ...attributes }, children, parent: null, checked: 'checked' in attributes, hidden: 'hidden' in attributes,
    value: attributes.value, text,
    getAttribute(n) { return n in this.attributes ? this.attributes[n] : null; },
    hasAttribute(n) { return n in this.attributes; },
    get textContent() { return this.text + this.children.map((c) => c.textContent).join(''); },
    set textContent(v) { this.text = String(v); this.children = []; },
    closest(sel) { for (let e = this; e; e = e.parent) if (matches(e, sel)) return e; return null; },
    querySelectorAll(sel) { const out = []; const walk = (e) => e.children.forEach((c) => { if (matches(c, sel)) out.push(c); walk(c); }); walk(this); return out; },
    querySelector(sel) { return this.querySelectorAll(sel)[0] || null; },
  };
  children.forEach((c) => { c.parent = el; });
  return el;
}
const label = (text, input) => { const l = make('label', {}, [input], ' ' + text); return l; };
const parts = { database: 'Base de datos', core: 'Núcleo de WordPress', plugins: 'Plugins', themes: 'Temas', uploads: 'Medios', 'mu-plugins': 'Mu-plugins', content: 'Resto de wp-content' };
const input = {};
for (const key of Object.keys(parts)) input[key] = make('input', { 'data-restore-part': '', value: key, checked: '' });
input.all = make('input', { 'data-restore-all': '', checked: '' });
input.demo = make('input', { 'data-restore-item': 'plugin-items', 'data-restore-whole': 'plugins', value: 'demo' });
input.tema = make('input', { 'data-restore-item': 'theme-items', 'data-restore-whole': 'themes', value: 'tema' });
const caption = make('span', { 'data-restore-parts-label': '' }, [], 'todo');
const warnings = rules.map((r) => make('li', { 'data-coherence': r.code, 'data-any': r.any.join(' '), 'data-none': r.none.join(' '), 'data-missing': r.missing.join(' ') }, [], r.code));
const box = make('details', { 'data-restore-parts': '' }, [
  make('summary', {}, [caption]),
  label('Todo (base de datos y archivos)', input.all),
  ...Object.keys(parts).map((k) => label(parts[k], input[k])),
  label('demo', input.demo), label('tema', input.tema),
  make('ul', {}, warnings),
]);
const summary = make('p', { 'data-restore-parts-summary': '', hidden: '' });
const form = make('form', {}, [summary, box]);
const page = make('div', {}, [form]);

const listeners = {};
const document = {
  readyState: 'complete',
  addEventListener: (type, fn) => { (listeners[type] = listeners[type] || []).push(fn); },
  querySelectorAll: (sel) => page.querySelectorAll(sel),
  querySelector: (sel) => page.querySelector(sel),
  getElementById: () => null,
};
runInNewContext(script, { document });
const change = (el, checked) => { el.checked = checked; (listeners.change || []).forEach((fn) => fn({ target: el })); };
const visible = () => warnings.filter((w) => !w.hidden).map((w) => w.getAttribute('data-coherence'));

// Al cargar, con «Todo»: ningún aviso, «todo» y sin resumen.
assert.deepEqual(visible(), [], 'Con «Todo» no debe verse ningún aviso de coherencia');
assert.equal(caption.textContent, 'todo');
assert.equal(summary.hidden, true, 'Con «Todo» el resumen de partes no se ve');

// Revisión M1: con «Todo» marcado, marcar un plugin concreto empieza de cero (sólo ese plugin, nunca la base y lo demás).
change(input.demo, true);
assert.ok(Object.keys(parts).every((k) => !input[k].checked) && !input.all.checked, 'Un concreto con «Todo» marcado debe empezar de cero');
assert.equal(summary.textContent, 'Se restaura sólo: demo. Lo demás no se toca.');
assert.deepEqual(visible(), ['plugins_without_database']);
change(input.all, true);
assert.ok(Object.keys(parts).every((k) => input[k].checked) && !input.demo.checked, '«Todo» debe volver a todo');

// Desmarcar «Todo» desmarca las partes; nada marcado se dice.
change(input.all, false);
assert.ok(Object.keys(parts).every((k) => !input[k].checked), 'Desmarcar «Todo» debe desmarcar las partes');
assert.equal(summary.hidden, false);
assert.match(summary.textContent, /Marca al menos una parte/);

// Sólo la base de datos: los cuatro avisos de lo que conviene elegir junto (los mismos que coherence() en PHP).
change(input.database, true);
assert.deepEqual(visible(), ['database_without_plugins', 'database_without_themes', 'database_without_core', 'database_without_uploads']);
assert.equal(summary.textContent, 'Se restaura sólo: base de datos. Lo demás no se toca.');
assert.equal(caption.textContent, 'base de datos');

// Un plugin concreto sin la base: avisa de la base; la parte «Plugins» entera se excluye con él.
change(input.database, false);
change(input.plugins, true);
change(input.demo, true);
assert.equal(input.plugins.checked, false, 'Marcar un plugin concreto debe desmarcar «Plugins» entero');
assert.deepEqual(visible(), ['plugins_without_database']);
assert.equal(summary.textContent, 'Se restaura sólo: demo. Lo demás no se toca.');
change(input.plugins, true);
assert.equal(input.demo.checked, false, 'Marcar «Plugins» entero debe desmarcar los concretos');

// Medios sin la base; y casi todo, sin avisos.
change(input.plugins, false);
change(input.uploads, true);
assert.deepEqual(visible(), ['uploads_without_database']);
for (const k of ['database', 'core', 'plugins', 'themes', 'mu-plugins']) change(input[k], true);
assert.deepEqual(visible(), [], 'Con todo salvo el resto de wp-content no hay avisos');
assert.equal(input.all.checked, false);
change(input.content, true);
assert.equal(input.all.checked, true, 'Con las siete partes marcadas y ningún concreto, «Todo» vuelve a marcarse');
assert.equal(summary.hidden, true);

// Marcar «Todo» vuelve a todo y quita los concretos.
change(input.all, false);
change(input.tema, true);
change(input.all, true);
assert.ok(Object.keys(parts).every((k) => input[k].checked) && !input.tema.checked && caption.textContent === 'todo', '«Todo» debe marcar las partes y quitar los concretos');
console.log('ok · «Qué restaurar»: «Todo», partes, concretos, resumen y avisos de coherencia con las reglas del servidor');

// Revisión M1 de backups/restore-progress: «Volver a la copia previa» del panel de avance abre la copia previa
// (?restore=<copia>&undo=<trabajo>&undo_…=) con lo mismo que se restauró ya marcado, nunca «Todo» si fue por partes, y el
// aviso dice qué vuelve y qué no se toca; restaurada entera, que se pierde lo que cambió después. Sólo en esa fila.
function undoPage(search) {
  const field = (name) => make('input', { 'data-restore-part': '', value: name, checked: '' });
  const own = { parts: {} };
  for (const key of Object.keys(parts)) own.parts[key] = field(key);
  own.all = make('input', { 'data-restore-all': '', checked: '' });
  own.demo = make('input', { 'data-restore-item': 'plugin-items', 'data-restore-whole': 'plugins', value: 'demo' });
  own.otro = make('input', { 'data-restore-item': 'plugin-items', 'data-restore-whole': 'plugins', value: 'otro' });
  own.tema = make('input', { 'data-restore-item': 'theme-items', 'data-restore-whole': 'themes', value: 'tema' });
  own.items = make('details', { class: 'digitalisimo-backups-parts-items' }, [make('ul', {}, [make('li', {}, [label('demo', own.demo)]), make('li', {}, [label('otro', own.otro)])])]);
  own.themes = make('details', { class: 'digitalisimo-backups-parts-items' }, [make('ul', {}, [make('li', {}, [label('tema', own.tema)])])]);
  own.box = make('details', { 'data-restore-parts': '' }, [make('summary', {}, [make('span', { 'data-restore-parts-label': '' }, [], 'todo')]), label('Todo (base de datos y archivos)', own.all), ...Object.keys(parts).map((k) => label(parts[k], own.parts[k])), own.items, own.themes]);
  own.summary = make('p', { 'data-restore-parts-summary': '', hidden: '' });
  own.undo = make('p', { 'data-restore-undo': '', hidden: '' });
  own.form = make('form', {}, [make('input', { name: 'backup_file', value: 'safety-before-restore-x.zip' }), own.summary, own.undo, own.box]);
  own.otherAll = make('input', { 'data-restore-all': '', checked: '' });
  own.other = make('form', {}, [make('input', { name: 'backup_file', value: 'digitalisimo-otro.zip' }), make('details', { 'data-restore-parts': '' }, [label('Todo', own.otherAll)]), make('p', { 'data-restore-undo': '', hidden: '' })]);
  const root = make('div', {}, [own.form, own.other]);
  const doc = { readyState: 'complete', addEventListener: () => {}, querySelectorAll: (sel) => root.querySelectorAll(sel), querySelector: (sel) => root.querySelector(sel), getElementById: () => null };
  runInNewContext(script, { document: doc, window: { location: { search } }, URLSearchParams });
  return own;
}
{
  const page = undoPage('?page=digitalisimo-backups&tab=backups&restore=safety-before-restore-x.zip&undo=11111111-2222-4333-8444-555555555555&parts=1&undo_plugins=demo,falta');
  assert.ok(!page.all.checked && Object.keys(parts).every((k) => !page.parts[k].checked) && page.demo.checked && !page.otro.checked && !page.tema.checked, 'La copia previa no se abre con sólo el plugin restaurado marcado (o queda «Todo»)');
  assert.ok(page.box.open === true && page.items.open === true && page.themes.open !== true, '«Qué restaurar» y el plugin marcado no quedan a la vista');
  assert.equal(page.summary.textContent, 'Se restaura sólo: demo. Lo demás no se toca.');
  assert.ok(page.undo.hidden === false && /Deshaces sólo lo que se restauró/.test(page.undo.textContent) && /No se toca: base de datos, núcleo de WordPress, los demás plugins, temas, medios/.test(page.undo.textContent) && /no tiene el plugin falta \(lo trajo la restauración\)/.test(page.undo.textContent) && !/se pierde/.test(page.undo.textContent), 'El aviso no dice qué vuelve, qué no se toca y qué no tiene la copia previa: ' + page.undo.textContent);
  assert.ok(page.otherAll.checked && page.other.children[2].hidden, 'Otra fila cambió con el enlace de la copia previa');
  const db = undoPage('?restore=safety-before-restore-x.zip&undo=11111111-2222-4333-8444-555555555555&undo_parts=database');
  assert.ok(page.parts && db.parts.database.checked && !db.parts.plugins.checked && !db.all.checked && /La base de datos vuelve entera/.test(db.undo.textContent), 'Deshacer sólo la base de datos no la marca sola o no avisa de lo que se pierde en ella');
  const whole = undoPage('?restore=safety-before-restore-x.zip&undo=11111111-2222-4333-8444-555555555555');
  assert.ok(whole.all.checked && Object.keys(parts).every((k) => whole.parts[k].checked) && /se pierde lo que cambió después/.test(whole.undo.textContent) && whole.undo.hidden === false, 'Deshacer una restauración entera no avisa de que se pierde lo que cambió después');
  const plain = undoPage('?restore=safety-before-restore-x.zip');
  assert.ok(plain.all.checked && plain.undo.hidden === true, 'Sin «undo», abrir la copia previa no debe cambiar nada');
  console.log('ok · «Volver a la copia previa» abre la copia previa con lo restaurado marcado y dice qué vuelve y qué no se toca');
}
