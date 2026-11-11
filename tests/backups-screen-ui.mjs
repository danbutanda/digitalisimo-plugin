// Pantalla de Backups («interfaz simple», 1.0.74, assets/screen.js): «Restaurar» abre y cierra su panel bajo la fila sin
// recargar y lleva el foco a la confirmación; «Cancelar» lo cierra y vuelve a «Restaurar»; el destino elegido para
// «Respaldar ahora» se pinta como texto. Nada de HTML.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync('digitalisimo-backups/assets/screen.js', 'utf8');
assert.ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(script), 'La pantalla debe pintar texto, no HTML');

function element(attributes = {}, extra = {}) {
  return Object.assign({
    attributes: { ...attributes }, hidden: false, focused: false, text: '',
    getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; },
    setAttribute(name, value) { this.attributes[name] = String(value); },
    hasAttribute(name) { return name in this.attributes; },
    focus() { focused = this; },
    set textContent(value) { this.text = String(value); }, get textContent() { return this.text; },
  }, extra);
}
let focused = null;
const input = element({ name: 'confirmation' });
const row = element({ id: 'digitalisimo-backups-restore-0' }, { hidden: true, querySelector: (selector) => (selector === 'input[name="confirmation"]' ? input : null) });
const link = element({ 'data-restore-toggle': 'digitalisimo-backups-restore-0', 'aria-expanded': 'false' });
const cancel = element({ 'data-restore-cancel': 'digitalisimo-backups-restore-0' });
const label = element({ 'data-destination-label': '' });
label.textContent = 'Hosting / local';
const listeners = {};
const context = {
  document: {
    addEventListener: (type, listener) => { listeners[type] = listener; },
    getElementById: (id) => (id === 'digitalisimo-backups-restore-0' ? row : null),
    querySelector: (selector) => (selector === '[data-restore-toggle="digitalisimo-backups-restore-0"]' ? link : null),
  },
};
runInNewContext(script, context);
const click = (target) => { let prevented = false; listeners.click({ target: { closest: () => target }, preventDefault: () => { prevented = true; } }); return prevented; };

assert.ok(click(link) && row.hidden === false && link.getAttribute('aria-expanded') === 'true' && focused === input, '«Restaurar» debe abrir su panel sin recargar y llevar el foco a la confirmación');
assert.ok(click(cancel) && row.hidden === true && link.getAttribute('aria-expanded') === 'false' && focused === link, '«Cancelar» debe cerrar el panel y volver a «Restaurar»');
assert.ok(click(link) && row.hidden === false && click(link) && row.hidden === true, '«Restaurar» de nuevo cierra el panel');
let prevented = false;
listeners.click({ target: { closest: () => null }, preventDefault: () => { prevented = true; } });
assert.ok(!prevented, 'Otros clics no se tocan');
const gone = element({ 'data-restore-toggle': 'digitalisimo-backups-restore-9' });
assert.ok(!click(gone), 'Sin su fila, el enlace sigue a ?restore= (como sin JavaScript)');

const select = { name: 'digitalisimo_backup_destination', selectedIndex: 1, options: [{ text: 'Hosting / local' }, { text: '<b>SFTP oficina</b>' }], form: { querySelector: (selector) => (selector === '[data-destination-label]' ? label : null) } };
listeners.change({ target: select });
assert.ok(label.textContent === '<b>SFTP oficina</b>', 'El destino elegido se pinta como texto');
listeners.change({ target: { name: 'otro', form: select.form } });
assert.ok(label.textContent === '<b>SFTP oficina</b>', 'Otro campo no cambia el destino');
console.log('DIGITALÍSIMO Backups: «Restaurar» abre y cierra su panel bajo la fila y el destino del botón se pinta como texto.');
