import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function eventNode() {
  const handlers = new Map();
  return {
    addEventListener(name, handler) {
      if (!handlers.has(name)) handlers.set(name, []);
      handlers.get(name).push(handler);
    },
    fire(name) {
      for (const handler of handlers.get(name) || []) handler({ relatedTarget: null });
    },
    contains() { return false; },
  };
}

const click = eventNode();
const hover = eventNode();
const panels = [
  { event: 'click', selector: '#boton', trigger: click },
  { event: 'mouseover', selector: '.tarjeta', trigger: hover },
  { event: 'click', selector: '#ausente', trigger: null },
].map(({ event, selector, trigger }) => {
  const panel = eventNode();
  const close = eventNode();
  panel.hidden = true;
  panel.dataset = { event, selector, timeout: '0' };
  panel.querySelector = () => close;
  panel.close = close;
  panel.trigger = trigger;
  return panel;
});
const document = {
  readyState: 'complete',
  querySelectorAll: () => panels,
  querySelector: (selector) => panels.find((panel) => panel.dataset.selector === selector)?.trigger || null,
};
const source = readFileSync(new URL('../digitalisimo-elements/assets/js/notification.js', import.meta.url), 'utf8');
vm.runInNewContext(source, { document, window: {}, setTimeout, clearTimeout });

assert.equal(panels[0].hidden, true, 'El aviso por clic espera al activador.');
assert.equal(panels[1].hidden, true, 'El aviso por cursor espera al activador.');
assert.equal(panels[2].hidden, true, 'Un activador ausente no dispara el aviso.');
click.fire('click');
assert.equal(panels[0].hidden, false, 'El clic abre sólo su aviso.');
panels[0].close.fire('click');
assert.equal(panels[0].hidden, true, 'El botón cierra el aviso.');
hover.fire('focusin');
assert.equal(panels[1].hidden, false, 'El teclado puede abrir el aviso configurado para cursor.');
assert.equal(panels[2].hidden, true, 'El aviso sin activador sigue oculto.');
console.log('DIGITALÍSIMO Elements: eventos de notificación validados.');
