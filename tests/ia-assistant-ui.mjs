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
for (const piece of ['Nueva conversación', 'Buscar en el historial', 'La mejor disponible', 'Apariencia', 'Classic', 'Modern', 'Oscuro', 'Sí, hazlo', 'Ver detalles', 'Puedes deshacerlo después.', 'Ver la bitácora', 'Borrar todo el historial', 'scrollTop = area.scrollHeight']) ok(ui.includes(piece), `La interfaz trae «${piece}»`);
ok(/disabled: props\.busy \|\| \( needsAck && ! ack \)/.test(ui) && /const needsAck = !! action\.ack;/.test(ui), 'El botón de lo que borra o mueve dinero sigue apagado hasta marcar la casilla (la decide el servidor)');
// Revisión adversarial.
ok(/h\( 'details', \{ className: 'dga-details', open: !! action\.after_read \}/.test(ui) && /h\( 'summary', null, 'Ver detalles' \)/.test(ui) && !/action\.input\b/.test(ui) && !/action\.input\.slice|substring\(/.test(ui), 'A2 (caso plomerito 4): los datos completos van plegados en «Ver detalles», con nombres legibles del servidor y sin JSON');
ok(/warnings\.map\(/.test(ui) && /action\.final \?/.test(ui) && /action\.undo \?/.test(ui), 'A2/R2-B11: la tarjeta trae lo arriesgado en llano, lo que no tiene vuelta atrás y si se puede deshacer');
ok(/404 === e\.status \|\| 409 === e\.status \|\| 429 === e\.status \) \) \{\s*open\( activeRef\.current \)/.test(ui) && /setDraft\( text \);\s*setPending\( before \)/.test(ui), 'R2-B4: tras 404/409/429 se relee la conversación (sin tarjeta zombi) y un envío rechazado devuelve el texto');
ok(/warnings\.length \?/.test(ui) && /action\.text \|\|/.test(ui), 'A2: la tarjeta dice lo que hará con la frase del servidor y avisa de lo arriesgado');
ok(/'running' === action\.state/.test(ui) && /Este cambio empezó y no sé si terminó\./.test(ui) && /dga-refresh/.test(ui) && /unknown: 'por revisar'/.test(ui), 'A1/R2-B4: una acción en curso o cortada no se puede confirmar, se puede actualizar y su resultado queda «por revisar»');
ok(/! providers\.length && ! isShortcut\( text \)/.test(ui) && /busy \|\| \( noAi && ! isShortcut\( draft \) \)/.test(ui), 'B3: sin IA configurada se pueden enviar atajos /herramienta');
ok(/fromLink && isShortcut\( text \)/.test(ui) && /vino en un enlace/.test(ui), 'B5: lo que llega en un enlace sólo se precarga, avisado, y un atajo precargado hay que editarlo');
ok(/\.dga-confirm \.dga-details dl \{[^}]*max-block-size: 18rem;[^}]*overflow: auto/.test(css), 'A2: «Ver detalles» va en un área con desplazamiento');

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

// 2b. El estilo de los demás plugins DIGITALÍSIMO (pedido del propietario del 2026-11-26: «al asistente dale el mismo
// estilo de los plugins en colores y todo para que se vea igual todo»): los tokens de digitalisimo-ui, no otros.
const brand = rules.slice(rules.indexOf('.dga-root {\n  --font-size-sm: .875rem;'));
ok(brand.length > 2000, 'E0: la hoja trae la sección con el estilo de los demás plugins');
ok(/border-radius: var\(--digi-radius-shell, 22px\)/.test(brand) && /box-shadow: var\(--digi-shadow-card, 0 7px 22px rgba\(42,56,115,\.06\)\)/.test(brand), 'E1: la pantalla es una tarjeta como el `.wrap` (radio y sombra de la marca)');
ok(/\.dga-title::before \{[^}]*content: "D"/.test(brand) && /--ds-assistant-mark: var\(--digi-gradient-badge,/.test(brand), 'E2: el encabezado lleva la insignia «D» con el degradado de la marca');
ok(/\.dga-send:not\(:disabled\)[^{]*\{ background: var\(--digi-gradient, /.test(brand) && /var\(--digi-gradient-hover, /.test(brand) && /var\(--digi-shadow-button, /.test(brand), 'E3: el botón principal con el degradado, su sombra y su estado al pasar');
ok(/\.dga-message\.is-me \.dga-bubble \{ background: var\(--digi-gradient, /.test(brand), 'E4: el mensaje de la persona con el degradado de la marca');
ok(/var\(--digi-gradient-tab, /.test(brand) && /var\(--digi-focus-ring, /.test(brand) && /var\(--digi-radius-control, 9px\)/.test(brand) && /var\(--digi-radius-card, 15px\)/.test(brand), 'E5: pestañas, anillo de foco y radios de controles y tarjetas de digitalisimo-ui');
ok(/@media screen and \(max-width: 782px\) \{\s*\.dga-root \{ margin: 10px;/.test(brand), 'E6: en pantallas angostas la tarjeta ocupa el ancho con el margen de las demás pantallas');
const withoutFallbacks = (css) => { let out = ''; let i = 0; for (;;) { const at = css.indexOf('var(--digi-', i); if (at < 0) return out + css.slice(i); out += css.slice(i, at); let depth = 0; let k = at; for (; k < css.length; k++) { if (css[k] === '(') depth++; else if (css[k] === ')' && --depth === 0) break; } out += 'TOKEN'; i = k + 1; } };
ok(!/#[0-9a-f]{3,8}\b|rgba?\(/i.test(withoutFallbacks(brand)), 'E7: la sección no trae colores propios: sólo tokens (con el respaldo de cada uno)');

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
