// IA Tools · chatbot público (digitalisimo-ia-tools/assets/chat.js) en un DOM falso con `vm` (sin navegador): la
// respuesta del modelo y el mensaje de error se pintan como texto aunque traigan <img onerror>, <script>, atributos on*
// o enlaces javascript:; los saltos de línea se conservan con <br> creados por DOM; un fetch fallido, un JSON inválido o
// una respuesta que no es texto no rompen el chat. El DOM falso interpreta el HTML que recibe insertAdjacentHTML o
// innerHTML, así que la versión anterior (que pegaba d.answer con insertAdjacentHTML) falla aquí.
// También comprueba sin ejecutar que los demás scripts de IA Tools no construyen HTML con datos del servidor.
// Uso: (ulimit -v 3000000; timeout 120 node --max-old-space-size=400 tests/ia-chat-xss.mjs [ruta/a/chat.js])
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const root = new URL('../', import.meta.url);
const file = process.argv[2] ? new URL(process.argv[2], 'file://' + process.cwd() + '/') : new URL('digitalisimo-ia-tools/assets/chat.js', root);
const source = readFileSync(file, 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// 1. Forma.
assert.doesNotThrow(() => new vm.Script(source, { filename: 'chat.js' }), 'chat.js compila.');
ok(statSync(file).size <= 1536, `chat.js sigue siendo pequeño (${statSync(file).size} B ≤ 1,5 KB).`);

// 2. DOM falso, con un analizador de HTML mínimo para lo que llegue como cadena.
const VOID = new Set(['BR', 'IMG', 'INPUT', 'HR', 'META', 'LINK', 'SOURCE']);
const decode = (text) => text.replace(/&(amp|lt|gt|quot|#39);/g, (m, e) => ({ amp: '&', lt: '<', gt: '>', quot: '"', '#39': "'" })[e]);
class Node {
  constructor(type, name) { this.nodeType = type; this.nodeName = name; this.childNodes = []; this.parentNode = null; }
  appendChild(child) { if (child.parentNode) child.parentNode.removeChild(child); child.parentNode = this; this.childNodes.push(child); return child; }
  removeChild(child) { this.childNodes = this.childNodes.filter((node) => node !== child); child.parentNode = null; return child; }
  get textContent() { return 3 === this.nodeType ? this.data : this.childNodes.map((node) => node.textContent).join(''); }
  set textContent(value) {
    if (3 === this.nodeType) { this.data = String(value); return; }
    this.childNodes.forEach((node) => { node.parentNode = null; });
    this.childNodes = [];
    if ('' !== String(value)) this.appendChild(new Text(String(value)));
  }
}
class Text extends Node { constructor(data) { super(3, '#text'); this.data = String(data); } }
class Element extends Node {
  constructor(tag) { super(1, tag.toUpperCase()); this.tagName = tag.toUpperCase(); this.attributes = new Map(); this.value = ''; }
  setAttribute(name, value) { this.attributes.set(String(name).toLowerCase(), String(value)); }
  getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
  get className() { return this.getAttribute('class') || ''; }
  matches(selector) {
    return selector.split(',').some((part) => {
      const m = /^([a-z]*)(?:\.([\w-]+))?$/.exec(part.trim());
      if (!m) throw new Error('Selector no previsto: ' + part);
      return (!m[1] || this.tagName === m[1].toUpperCase()) && (!m[2] || this.className.split(/\s+/).includes(m[2]));
    });
  }
  closest(selector) { for (let node = this; node && 1 === node.nodeType; node = node.parentNode) if (node.matches(selector)) return node; return null; }
  querySelectorAll(selector) {
    const out = [];
    const walk = (node) => node.childNodes.forEach((child) => { if (1 === child.nodeType) { if (child.matches(selector)) out.push(child); walk(child); } });
    walk(this);
    return out;
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  set innerHTML(html) { this.textContent = ''; parse(this, html); }
  insertAdjacentHTML(position, html) {
    if ('beforeend' !== position) throw new Error('Posición no prevista: ' + position);
    parse(this, html);
  }
}
function parse(parent, html) {
  const stack = [parent];
  const re = /<(\/?)([a-zA-Z][\w-]*)((?:\s+[^\s=>\/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*\/?>/g;
  let last = 0;
  let m;
  const text = (chunk) => { if (chunk) stack[stack.length - 1].appendChild(new Text(decode(chunk))); };
  while ((m = re.exec(html))) {
    text(html.slice(last, m.index));
    last = re.lastIndex;
    const tag = m[2].toUpperCase();
    if (m[1]) {
      const at = stack.map((node) => node.tagName).lastIndexOf(tag);
      if (at > 0) stack.length = at;
      continue;
    }
    const element = new Element(tag);
    for (const a of m[3].matchAll(/([^\s=>\/]+)(?:\s*=\s*("[^"]*"|'[^']*'|[^\s>]+))?/g)) element.setAttribute(a[1], decode((a[2] || '').replace(/^["']|["']$/g, '')));
    stack[stack.length - 1].appendChild(element);
    if (!VOID.has(tag)) stack.push(element);
  }
  text(html.slice(last));
}

// 3. Escenario: el marcado del shortcode [digitalisimo_chatbot] y un fetch controlado por cada caso.
const listeners = {};
const document = {
  createElement: (tag) => new Element(tag),
  createTextNode: (data) => new Text(data),
  addEventListener: (type, handler) => { (listeners[type] = listeners[type] || []).push(handler); },
  write: () => { throw new Error('document.write no debe usarse'); },
};
const box = new Element('div'); box.setAttribute('class', 'digitalisimo-chatbot');
const log = box.appendChild(new Element('div')); log.setAttribute('class', 'digitalisimo-chatbot-log');
const form = box.appendChild(new Element('form'));
const input = form.appendChild(new Element('input'));
form.appendChild(new Element('button'));
const other = new Element('form'); other.appendChild(new Element('input'));
let respond = null;
let requests = [];
const fetch = (url, options) => { requests.push({ url, options }); return respond(); };
const json = (body) => () => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(body) });
const context = vm.createContext({ document, window: {}, fetch, digitalisimoChatbot: { url: 'https://sitio.test/wp-json/digitalisimo-ai/v1/chat' }, console });
vm.runInContext(source, context, { filename: 'chat.js', timeout: 2000 });
ok((listeners.submit || []).length === 1, 'chat.js escucha un solo evento submit.');

async function send(message, responder, target = form) {
  respond = responder;
  requests = [];
  input.value = message;
  let prevented = false;
  const before = log.childNodes.length;
  // Un error del manejador (fetch fallido, JSON inválido) llegaría aquí como promesa rechazada: debe resolverse.
  await listeners.submit[0]({ target, preventDefault: () => { prevented = true; } });
  return { prevented, added: log.childNodes.slice(before) };
}
const elements = (node) => node.childNodes.filter((child) => 1 === child.nodeType).flatMap((child) => [child, ...elements(child)]);
function plain(paragraph, who, expected) {
  ok(paragraph && 'P' === paragraph.tagName, `${who}: cada mensaje es un <p>.`);
  const tags = elements(paragraph).map((node) => node.tagName);
  ok(tags.every((tag) => 'STRONG' === tag || 'BR' === tag), `${who}: el mensaje sólo crea <strong> y <br> (creó ${tags.join(', ')}).`);
  ok(elements(paragraph).every((node) => 0 === node.attributes.size), `${who}: ningún nodo del mensaje lleva atributos (on*, href, src).`);
  ok(paragraph.childNodes[0].tagName === 'STRONG' && paragraph.childNodes[0].textContent === who + ':', `${who}: conserva <strong>${who}:</strong>.`);
  const lines = String(expected).replace(/\r\n?/g, '\n').split('\n');
  ok(tags.filter((tag) => 'BR' === tag).length === lines.length - 1, `${who}: un <br> por salto de línea.`);
  const texts = paragraph.childNodes.filter((node) => 3 === node.nodeType).map((node) => node.data);
  ok(texts.join('\n') === ' ' + lines.join('\n'), `${who}: el texto visible es el original, carácter por carácter.`);
}

// 4. Respuestas peligrosas: se ven como texto, sin crear nodos ni atributos.
const attacks = [
  '<img src=x onerror=alert(document.cookie)>Hola',
  '<script>alert(1)</script>Respuesta',
  '<a href="javascript:alert(1)" onclick="robar()">Abrir tu cuenta</a>',
  '<div onmouseover=alert(1) style="position:fixed;inset:0">Pasa el ratón</div>',
  '<svg/onload=alert(1)><iframe src="javascript:alert(2)"></iframe>',
  '&lt;b&gt;ya escapado&lt;/b&gt; &amp; <b>negrita</b>',
];
for (const answer of attacks) {
  const { prevented, added } = await send('¿Qué ofrecen?', json({ answer }));
  ok(prevented, 'El envío no recarga la página.');
  ok(added.length === 2, 'Se añaden la pregunta y la respuesta.');
  plain(added[0], 'Tú', '¿Qué ofrecen?');
  plain(added[1], 'Digitalisimo', answer);
  ok(requests.length === 1 && JSON.parse(requests[0].options.body).message === '¿Qué ofrecen?', 'La pregunta viaja como JSON a la ruta REST.');
}
ok(!elements(log).some((node) => ['IMG', 'SCRIPT', 'A', 'DIV', 'SVG', 'IFRAME', 'B'].includes(node.tagName)), 'El registro no contiene ningún elemento venido de una respuesta.');

// 5. La pregunta del visitante también es texto.
{
  const question = '<img src=x onerror=alert(1)> y "comillas" & <b>';
  const { added } = await send(question, json({ answer: 'Gracias.' }));
  plain(added[0], 'Tú', question);
  plain(added[1], 'Digitalisimo', 'Gracias.');
}

// 6. Saltos de línea: \n y \r\n se conservan como <br>, también al principio y al final.
for (const answer of ['Primera línea\nSegunda línea\nTercera', 'Paso 1\r\nPaso 2\r\n\r\nFin', 'Dos líneas\n<script>x()</script>\n']) {
  const { added } = await send('Pasos', json({ answer }));
  plain(added[1], 'Digitalisimo', answer);
}

// 7. Errores: el chat sigue funcionando y muestra un mensaje en texto.
const fallback = 'No fue posible responder.';
{
  const { added } = await send('Hola', () => Promise.reject(new TypeError('Failed to fetch')));
  ok(added.length === 2, 'Un fetch fallido no rompe el manejador.');
  plain(added[1], 'Digitalisimo', fallback);
}
{
  const { added } = await send('Hola', () => Promise.resolve({ ok: false, status: 502, json: () => Promise.reject(new SyntaxError('Unexpected token < in JSON')) }));
  plain(added[1], 'Digitalisimo', fallback);
}
{
  const { added } = await send('Hola', json({ code: 'rate', message: 'Intenta más tarde. <img src=x onerror=alert(1)>', data: { status: 429 } }));
  plain(added[1], 'Digitalisimo', 'Intenta más tarde. <img src=x onerror=alert(1)>');
}
for (const body of [null, {}, { answer: { html: '<b>x</b>' } }, { answer: '   ' }, 'texto suelto', { answer: 42 }]) {
  const { added } = await send('Hola', json(body));
  plain(added[1], 'Digitalisimo', fallback);
}
{
  const saved = context.digitalisimoChatbot;
  delete context.digitalisimoChatbot;
  const { added } = await send('Hola', json({ answer: 'no debe llegar' }));
  plain(added[1], 'Digitalisimo', fallback);
  context.digitalisimoChatbot = saved;
}
{
  const before = log.childNodes.length;
  const empty = await send('   ', json({ answer: 'x' }));
  ok(log.childNodes.length === before && 0 === requests.length && empty.prevented, 'Un mensaje vacío no envía nada.');
  const outside = await send('Hola', json({ answer: 'x' }), other);
  ok(!outside.prevented && 0 === requests.length && log.childNodes.length === before, 'Otros formularios de la página no se tocan.');
}

// 8. Sin ejecutar: ningún script de IA Tools construye HTML con datos del servidor o del modelo.
ok(!/innerHTML|outerHTML|insertAdjacentHTML|document\.write|eval\(|new Function/.test(source), 'chat.js no construye HTML con cadenas.');
ok(/createTextNode|textContent/.test(source), 'chat.js pinta con nodos de texto.');
const articleChat = readFileSync(new URL('digitalisimo-ia-tools/assets/article-chat.js', root), 'utf8');
ok(!/\.innerHTML\s*=/.test(articleChat) && /new Option\(/.test(articleChat), 'article-chat.js crea las opciones de personaje con new Option, sin innerHTML.');
const images = readFileSync(new URL('digitalisimo-ia-tools/includes/class-digitalisimo-ai-images.php', root), 'utf8');
ok(!/innerHTML|insertAdjacentHTML/.test(images), 'La vista previa de la imagen generada no usa innerHTML.');
ok(/createElement\(\\?"img\\?"\)|createElement\("img"\)/.test(images) && /\^https\?:/.test(images), 'La vista previa crea el <img> por DOM y sólo acepta URL http(s).');
const ai = readFileSync(new URL('digitalisimo-ia-tools/includes/class-digitalisimo-ai.php', root), 'utf8');
ok(!/innerHTML|insertAdjacentHTML/.test(ai) && /r\.textContent=x\.answer/.test(ai), 'El asistente editorial del editor muestra la propuesta como texto.');

console.log(`IA Tools · chatbot sin XSS: ${checks} comprobaciones (${attacks.length} respuestas peligrosas, saltos de línea, errores de red y JSON inválido).`);
