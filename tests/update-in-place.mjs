import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const modules = ['digitalisimo-seo', 'digitalisimo.chatbot', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements'];
const script = readFileSync('digitalisimo-elements/assets/js/update-in-place.js', 'utf8');
for (const module of modules) {
  assert.equal(readFileSync(`${module}/assets/js/update-in-place.js`, 'utf8'), script, `${module} debe incluir el mismo controlador`);
}

const listeners = [];
const updates = [];
let credentials = 0;
let currentFile = 'digitalisimo-elements/pro-elements.php';
const registered = Object.fromEntries([
  'digitalisimo-backups/digitalisimo-backups.php',
  'digitalisimo-elements/pro-elements.php',
  'digitalisimo-hosting/digitalisimo-hosting.php',
  'digitalisimo.chatbot/digitalisimo-chatbot.php',
  'digitalisimo-seo/digitalisimo-integrations.php',
  'digitalisimo-tools/digitalisimo-tools.php'
].map(file => [file, true]));
const row = {
  getAttribute: name => name === 'data-plugin' ? currentFile : currentFile.split('/')[0]
};
const link = {
  closest: selector => selector === 'tr[data-plugin]' ? row : null,
  classList: { contains: () => false }
};
const event = {
  button: 0,
  target: { closest: () => link },
  prevented: false,
  stopped: false,
  preventDefault() { this.prevented = true; },
  stopImmediatePropagation() { this.stopped = true; }
};
const context = {
  window: { digitalisimoPluginUpdateFiles: registered, wp: { updates: { updatePlugin: args => updates.push(args), maybeRequestFilesystemCredentials: () => credentials++ } } },
  document: { addEventListener: (...args) => listeners.push(args) }
};
context.wp = context.window.wp;
runInNewContext(script, context);
runInNewContext(script, context);
assert.equal(listeners.length, 1, 'varios plugins activos no deben registrar eventos duplicados');
assert.equal(listeners[0][2], true, 'debe capturar el clic antes de la navegación del enlace');
listeners[0][1](event);
assert.equal(event.prevented, true);
assert.equal(event.stopped, true);
assert.equal(credentials, 1);
assert.deepEqual(JSON.parse(JSON.stringify(updates)), [{ plugin: 'digitalisimo-elements/pro-elements.php', slug: 'digitalisimo-elements' }]);

for (const file of [
  'digitalisimo-backups/digitalisimo-backups.php',
  'digitalisimo-hosting/digitalisimo-hosting.php',
  'digitalisimo.chatbot/digitalisimo-chatbot.php',
  'digitalisimo-seo/digitalisimo-integrations.php',
  'digitalisimo-tools/digitalisimo-tools.php'
]) {
  currentFile = file;
  listeners[0][1]({ ...event, prevented: false, stopped: false });
  assert.equal(updates.at(-1).plugin, file);
}
assert.equal(updates.length, 6);

delete registered[currentFile];
const unregistered = { ...event, prevented: false, stopped: false };
listeners[0][1](unregistered);
assert.equal(unregistered.prevented, false, 'el controlador sólo debe atender módulos registrados por PHP');
registered[currentFile] = true;

const foreign = { ...event, prevented: false, stopped: false, target: { closest: () => ({ ...link, closest: () => ({ getAttribute: () => 'otro/otro.php' }) }) } };
listeners[0][1](foreign);
assert.equal(foreign.prevented, false, 'los plugins de otros desarrolladores deben quedar bajo WordPress');
assert.equal(updates.length, 6);

const modified = { ...event, ctrlKey: true, prevented: false, stopped: false };
listeners[0][1](modified);
assert.equal(modified.prevented, false, 'clics modificados conservan el enlace nativo');

delete context.wp.updates.updatePlugin;
const fallback = { ...event, prevented: false, stopped: false };
listeners[0][1](fallback);
assert.equal(fallback.prevented, false, 'sin AJAX disponible se conserva la ruta nativa');
console.log('Actualización en Plugins: captura limitada, sin duplicados y con fallback nativo.');
