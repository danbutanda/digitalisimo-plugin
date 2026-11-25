// IA Tools · Asistente IA en el navegador, sin navegador (docs/ASSISTANT.md):
//   1. assistant.js y assistant-adapters.js compilan, sólo usan wp.element (sin import, require, @digitalisimo/ui,
//      core-sdk ni Next), no piden nada fuera del sitio (sin Iconify ni CDN) y nunca construyen HTML con cadenas;
//   2. assistant.css sólo toca la pantalla del Asistente (todo selector acotado), no trae la paleta del constructor
//      original y lleva Classic, Modern, modo oscuro y los mandos --ds-assistant-* del tema mini;
//   3. los adaptadores con un fetch simulado: las dos cabeceras de nonce en cada petición, el flujo NDJSON aunque llegue
//      partido en trozos (y con una línea basura), el respaldo JSON, el mensaje de error del servidor, y las rutas y
//      métodos del historial y de las herramientas; otro conjunto de adaptadores definido antes se respeta.
// Uso: node tests/ia-assistant-ui.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const root = new URL('../digitalisimo-ia-tools/assets/assistant/', import.meta.url);
const ui = readFileSync(new URL('assistant.js', root), 'utf8');
const adaptersSource = readFileSync(new URL('assistant-adapters.js', root), 'utf8');
const css = readFileSync(new URL('assistant.css', root), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// 1. Forma.
for (const [name, source] of [['assistant.js', ui], ['assistant-adapters.js', adaptersSource]]) {
  ok(!!new vm.Script(source, { filename: name }), `${name} compila sin transpilar`);
  const code = source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
  ok(!/\bimport\s|require\(|@digitalisimo\/|core-sdk|next\//.test(code), `${name} no depende de @digitalisimo/ui, core-sdk ni Next`);
  ok(!/innerHTML|outerHTML|insertAdjacentHTML|dangerouslySetInnerHTML|document\.write|\beval\(|new Function/.test(code), `${name} nunca construye HTML con cadenas`);
  ok(!/https?:\/\//.test(code), `${name} no pide nada fuera del sitio`);
}
ok(/wp\.element/.test(ui) && /createRoot/.test(ui) && /el\.render\(/.test(ui), 'La interfaz usa wp.element (createRoot y render para WordPress anteriores)');
ok(/window\.digitalisimoAssistantAdapters\.create\(\s*config\s*\)/.test(ui) && !/fetch\(/.test(ui), 'La interfaz sólo habla con el servidor por los adaptadores');
for (const piece of ['Nueva conversación', 'Buscar en el historial', 'La mejor disponible', 'Apariencia', 'Classic', 'Modern', 'Oscuro', 'Confirmar', 'Sí, hacerlo', 'Entiendo que puede no tener vuelta atrás', 'Ver la bitácora', 'Borrar todo el historial', 'scrollTop = area.scrollHeight']) ok(ui.includes(piece), `La interfaz trae «${piece}»`);
ok(/disabled: props\.busy \|\| ! seen \|\| \( destructive && ! ack \)/.test(ui), 'El botón de una acción destructiva sigue apagado hasta marcar la casilla');
// Revisión adversarial.
ok(/el\.scrollTop \+ el\.clientHeight >= el\.scrollHeight - 4/.test(ui) && /onScroll: check/.test(ui) && /useEffect\( check, \[ full, action\.input \] \)/.test(ui) && !/action\.input\.slice|substring\(/.test(ui), 'A2: «Confirmar» se enciende sólo si los datos completos caben o se desplazaron hasta el final');
ok(/risks\.map\(/.test(ui) && /action\.idempotent \? null/.test(ui), 'A2/R2-B11: la tarjeta trae el resumen de riesgos y avisa si no es idempotente');
ok(/404 === e\.status \|\| 409 === e\.status \|\| 429 === e\.status \) \) \{\s*open\( activeRef\.current \)/.test(ui) && /setDraft\( text \);\s*setPending\( before \)/.test(ui), 'R2-B4: tras 404/409/429 se relee la conversación (sin tarjeta zombi) y un envío rechazado devuelve el texto');
ok(/risks\.length \?/.test(ui) && /action\.after_read \?/.test(ui), 'A2: la tarjeta avisa de lo oculto (en sus riesgos) y de que llega tras leer contenido del sitio');
ok(/'running' === action\.state/.test(ui) && /En curso o cortada/.test(ui) && /dga-refresh/.test(ui) && /unknown: 'por revisar'/.test(ui), 'A1/R2-B4: una acción en curso o cortada no se puede confirmar, se puede actualizar y su resultado queda «por revisar»');
ok(/! providers\.length && ! isShortcut\( text \)/.test(ui) && /busy \|\| \( noAi && ! isShortcut\( draft \) \)/.test(ui), 'B3: sin IA configurada se pueden enviar atajos /herramienta');
ok(/fromLink && isShortcut\( text \)/.test(ui) && /vino en un enlace/.test(ui), 'B5: lo que llega en un enlace sólo se precarga, avisado, y un atajo precargado hay que editarlo');
ok(css.includes('.dga-confirm pre.is-full') && /max-block-size: 14rem; margin: 0; padding: var\(--ds-space-2\) var\(--ds-space-3\); overflow: auto/.test(css), 'A2: los datos van en un área con desplazamiento');

// 2. Hoja.
const rules = css.replace(/\/\*[\s\S]*?\*\//g, '');
const selectors = [];
const walk = (text) => {
  let depth = 0; let start = 0; let prelude = '';
  for (let i = 0; i < text.length; i++) {
    if (text[i] === '{') { if (depth === 0) prelude = text.slice(start, i).trim(); depth++; if (depth === 1) start = i + 1; }
    else if (text[i] === '}') { depth--; if (depth === 0) { const body = text.slice(start, i); if (prelude.startsWith('@media') || prelude.startsWith('@supports')) walk(body); else if (!prelude.startsWith('@keyframes') && !/^(from|to|\d+%)$/.test(prelude)) selectors.push(...prelude.split(',').map((s) => s.trim())); start = i + 1; } }
  }
};
walk(rules);
ok(selectors.length > 100, `La hoja tiene sus reglas (${selectors.length} selectores)`);
const scoped = /^(\.dga-|\.toplevel_page_digitalisimo-assistant |\.digitalisimo-assistant-loading$)/;
const loose = selectors.filter((s) => !scoped.test(s));
ok(!loose.length, `Todo selector está acotado a la pantalla del Asistente: ${loose.join(' | ')}`);
ok(!selectors.some((s) => /(^|[\s>+~])(:root|html|body)([\s.:[#>+~]|$)/.test(s)), 'La hoja no redefine nada en :root, html ni body');
const elementor = /#(F3BAFD|F5D0FE|F0ABFC|EB8EFB|E879F9|D004D4|C00BB9|E73CF6|D355CE|93003F|92003B|7E013B|B51243|8F1A4C|A93365|D6107C|310132|FAE8FF|524CFF|1DDDBF|5EEAD4|58D0F5|10BCF2)\b/i;
ok(!elementor.test(css) && !elementor.test(ui), 'Sin la paleta del constructor original');
for (const token of ['--ds-assistant-glow-1', '--ds-assistant-glow-2', '--ds-assistant-glow-3', '--ds-assistant-edge-line', '--ds-assistant-glass', '--ds-assistant-edge', '--ds-assistant-mark', '--ds-assistant-blur']) ok(css.includes(`${token}:`), `Mando del tema mini ${token}`);
ok(css.includes('.dga-root[data-theme="dark"]') && css.includes('.dga-root[data-interface-variant="modern"]') && css.includes('[data-color-scheme="digitalisimo"]'), 'Modo oscuro, acabado Modern y la paleta DIGITALÍSIMO');
ok(/var\(--digi-blue, #245eff\)/.test(css), 'La paleta DIGITALÍSIMO lee los tokens compartidos con su respaldo');
const schemes = [...ui.slice(ui.indexOf('const SCHEMES = ['), ui.indexOf('];', ui.indexOf('const SCHEMES = ['))).matchAll(/\[ '([a-z]+)', '/g)].map((m) => m[1]);
ok(schemes.length === 8 && schemes[0] === 'digitalisimo', `Ocho paletas, DIGITALÍSIMO primero: ${schemes}`);
for (const scheme of schemes) ok(css.includes(`[data-color-scheme="${scheme}"]`), `La paleta ${scheme} existe en la hoja`);

// 3. Adaptadores con fetch simulado.
const calls = [];
const encoder = new TextEncoder();
const streamOf = (chunks) => new ReadableStream({ start(controller) { for (const c of chunks) controller.enqueue(encoder.encode(c)); controller.close(); } });
let reply = null;
const fakeFetch = async (url, options) => { calls.push({ url, options }); return reply(url, options); };
const response = (status, type, body) => ({ ok: status >= 200 && status < 300, status, headers: { get: (k) => (k.toLowerCase() === 'content-type' ? type : null) }, body: typeof body === 'string' ? null : body, text: async () => (typeof body === 'string' ? body : ''), json: async () => JSON.parse(body) });
const sandbox = { window: { fetch: fakeFetch, TextDecoder }, Object, JSON, Promise, Array, String, encodeURIComponent, Error };
vm.runInNewContext(adaptersSource, sandbox);
const adapters = sandbox.window.digitalisimoAssistantAdapters.create({ rest: 'https://sitio.test/?rest_route=/digitalisimo-ai/v1/assistant/', restNonce: 'R1', nonce: 'A1', providers: [{ key: 'openai', name: 'OpenAI', aiName: 'ChatGPT', status: 'available' }] });

const events = [];
reply = () => response(200, 'application/x-ndjson; charset=utf-8', streamOf(['{"type":"conversation","id":7,"title":"Hola"}\n{"type":"sta', 'tus","text":"Pensando…"}\nnotice: basura\n', '{"type":"message","message":{"id":"m1","role":"assistant","text":"<img src=x onerror=alert(1)>"}}\n{"type":"done","pending":0}']));
await adapters.model.send({ conversation: 0, message: 'Hola', provider: 'auto' }, (e) => events.push(e));
ok(events.map((e) => e.type).join(',') === 'conversation,status,message,done', `El flujo NDJSON llega en orden aunque venga partido y con una línea basura: ${events.map((e) => e.type)}`);
ok(events[2].message.text === '<img src=x onerror=alert(1)>', 'El texto llega tal cual (la interfaz lo pinta como texto)');
const sent = calls[0];
ok(sent.url === 'https://sitio.test/?rest_route=/digitalisimo-ai/v1/assistant/message' && sent.options.method === 'POST' && sent.options.credentials === 'same-origin', 'Envía el mensaje por POST con la sesión del sitio');
ok(sent.options.headers['X-WP-Nonce'] === 'R1' && sent.options.headers['X-Digitalisimo-Assistant'] === 'A1', 'Con el nonce de la REST y el del Asistente');
ok(JSON.parse(sent.options.body).stream === true && JSON.parse(sent.options.body).message === 'Hola', 'Pide la respuesta en flujo');

events.length = 0;
reply = () => response(200, 'application/json', JSON.stringify({ events: [{ type: 'confirm', action: { id: 'act1' } }, { type: 'done', pending: 1 }] }));
await adapters.model.confirm({ conversation: 7, action: 'act1', decision: 'approve' }, (e) => events.push(e));
ok(events.map((e) => e.type).join(',') === 'confirm,done' && calls.at(-1).url.endsWith('/confirm'), 'Si la respuesta llega entera (JSON), los eventos salen igual');

reply = () => response(403, 'application/json', JSON.stringify({ code: 'digitalisimo_assistant_nonce', message: 'La sesión del Asistente caducó: recarga la página.' }));
await assert.rejects(adapters.model.send({ message: 'x' }, () => {}), /caducó/);
await assert.rejects(adapters.history.list(), /caducó/);
checks += 2;

reply = (url) => response(200, 'application/json', JSON.stringify(url.endsWith('conversations') ? { conversations: [{ id: 3, title: 'T', date: '2026-11-19T10:00:00+00:00' }] } : url.endsWith('/log') ? { rows: [{ ability: 'demo/x' }] } : { deleted: true, tools: [], categories: [] }));
ok((await adapters.history.list())[0].id === 3 && calls.at(-1).options.method === 'GET', 'Historial: lista');
await adapters.history.get(3); ok(calls.at(-1).url.endsWith('/conversations/3'), 'Historial: abre una');
await adapters.history.remove(3); ok(calls.at(-1).url.endsWith('/conversations/3/delete') && calls.at(-1).options.method === 'POST', 'Historial: borra una (POST, sin DELETE que algunos servidores bloquean)');
await adapters.history.clear(); ok(calls.at(-1).url.endsWith('/conversations/delete-all'), 'Historial: borra todo');
await adapters.tools.list(); ok(calls.at(-1).url.endsWith('/tools'), 'Herramientas del MCP');
ok((await adapters.tools.log())[0].ability === 'demo/x', 'Bitácora');
ok((await adapters.model.providers())[0].aiName === 'ChatGPT', 'Las IA salen de la configuración de la pantalla (sin pedir nada)');
ok(calls.every((c) => c.options.headers['X-Digitalisimo-Assistant'] === 'A1' && c.options.headers['X-WP-Nonce'] === 'R1'), 'Toda petición lleva los dos nonces');

const custom = { create: () => 'propios' };
const other = { window: { digitalisimoAssistantAdapters: custom } };
vm.runInNewContext(adaptersSource, other);
ok(other.window.digitalisimoAssistantAdapters === custom, 'Otros adaptadores definidos antes (p. ej. sobre core-sdk) se respetan');

console.log(`Asistente IA en el navegador: ${checks} comprobaciones correctas.`);
