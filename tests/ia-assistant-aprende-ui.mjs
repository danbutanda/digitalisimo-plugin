// IA Tools · el aprendizaje en la pantalla del Asistente (docs/ASSISTANT.md, «Lo que sé de tu sitio»), sin navegador:
//   1. assistant-learning.js compila sin transpilar, sólo usa wp.element, nunca construye HTML con cadenas ni pide nada
//      fuera del sitio; trae «Entrenar con mi sitio web», «Lo que sé de tu sitio», corregir, borrar, borrar todo y
//      «Aprender de mis conversaciones», y sólo se pinta para quien administra el sitio (config.learning);
//   2. assistant.js lo pone en la bienvenida y en la barra (LearnSlot) y lo carga antes (dependencia en PHP);
//   3. su hoja sólo toca piezas propias (.dga-root .dga-learn-*), sin colores fuera de los tokens;
//   4. su adaptador con un fetch simulado: rutas, métodos y los dos nonces; si los adaptadores traen `learning`, se usa ése.
// Falla con el código anterior (no existe el aprendizaje). Uso: node tests/ia-assistant-aprende-ui.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const root = new URL('../digitalisimo-ia-tools/assets/assistant/', import.meta.url);
const learn = readFileSync(new URL('assistant-learning.js', root), 'utf8');
const ui = readFileSync(new URL('assistant.js', root), 'utf8');
const css = readFileSync(new URL('assistant-learning.css', root), 'utf8');
const php = readFileSync(new URL('../../includes/class-digitalisimo-ai-assistant.php', root), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// 1. Forma.
ok(!!new vm.Script(learn, { filename: 'assistant-learning.js' }), 'assistant-learning.js compila sin transpilar');
const code = learn.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
ok(!/\bimport\s|require\(|@digitalisimo\/|core-sdk|next\//.test(code), 'Sin dependencias fuera de wp.element');
ok(!/innerHTML|outerHTML|insertAdjacentHTML|dangerouslySetInnerHTML|document\.write|\beval\(|new Function/.test(code), 'Nunca construye HTML con cadenas');
ok(!/https?:\/\//.test(code), 'No pide nada fuera del sitio');
for (const piece of ['Entrenar con mi sitio web', 'Volver a entrenar', 'Lo que sé de tu sitio', 'Aprender de mis conversaciones', 'Corregir', 'Borrar todo lo aprendido', 'Tu sitio cambió: ', 'view.notice']) ok(learn.includes(piece), `Trae «${piece}»`);
ok(/if \( ! config\.learning \) \{\s*return null;/.test(learn), 'Sólo para quien administra el sitio (config.learning)');
ok(/window\.setTimeout\( next, 400 \)/.test(learn) && /'running' === view\.job\.status/.test(learn), 'El entrenamiento avanza paso a paso y muestra el avance');

// 2. En la pantalla.
ok(/h\( LearnSlot, \{ place: 'welcome' \} \)/.test(ui) && /h\( LearnSlot, \{ place: 'header' \} \)/.test(ui), 'La bienvenida y la barra traen el aprendizaje');
ok(/'digitalisimo-ai-assistant-learning' \), \$version, true \)/.test(php) && /assistant-learning\.css/.test(php), 'Se carga antes que la interfaz, con su hoja');
ok(/memory: 'Recordé'/.test(ui), '«Recordar» se dice en llano en el trabajo del turno');
ok(/m\.memory && LearnMemory/.test(ui) && /'Sí, recuérdalo'/.test(learn) && /api\(\)\.approve\( memory\.id \)/.test(learn) && /api\(\)\.discard\( memory\.id \)/.test(learn), 'La propuesta de recordar se confirma en el hilo con «Sí, recuérdalo» o «No»');
ok(!/api\(\)\.remove\( memory\.id \)/.test(learn) && /act\( api\(\)\.discard\( item\.id \) \)/.test(learn), '«No» y «Descartar» usan la ruta que sólo descarta propuestas pendientes (nunca «Borrar»)');
ok(/memoryState\( s\.view, memory\.id \)/.test(learn) && /'Recordado: «'/.test(learn) && /'Descartado: no lo recordaré\.'/.test(learn), 'Al recargar, la tarjeta muestra el estado real: «Recordado» o «Descartado»');
ok(/config\.learning \? h\( 'div', \{ className: 'dga-learn-actions' \}/.test(learn), 'Los botones sólo para quien administra el sitio');

// 3. Hoja.
const rules = css.replace(/\/\*[\s\S]*?\*\//g, '');
const selectors = [];
let depth = 0; let start = 0; let prelude = '';
const walk = (text) => {
  depth = 0; start = 0;
  for (let i = 0; i < text.length; i++) {
    if (text[i] === '{') { if (depth === 0) prelude = text.slice(start, i).trim(); depth++; if (depth === 1) start = i + 1; }
    else if (text[i] === '}') { depth--; if (depth === 0) { const body = text.slice(start, i); if (prelude.startsWith('@media')) { const saved = [start, prelude]; walk(body); [start, prelude] = saved; depth = 0; } else selectors.push(...prelude.split(',').map((s) => s.trim())); start = i + 1; } }
  }
};
walk(rules);
ok(selectors.length > 15, `La hoja tiene sus reglas (${selectors.length})`);
const loose = selectors.filter((s) => !/^\.dga-root \.dga-learn-/.test(s));
ok(!loose.length, `Sólo piezas propias, dentro del Asistente: ${loose.join(' | ')}`);
ok(!/#[0-9a-f]{3,8}\b(?![^(]*\))/i.test(rules.replace(/var\([^)]*\)/g, '')), 'Sin colores fuera de los tokens (sólo como respaldo de un var())');

// 4. Adaptador.
const calls = [];
let reply = () => ({ ok: true, status: 200, json: async () => ({ trained: false, sections: [] }) });
const fakeFetch = async (url, options) => { calls.push({ url, options }); return reply(url, options); };
const el = { createElement: () => null, useState: (v) => [v, () => {}], useEffect: () => {}, useRef: () => ({ current: null }), Fragment: 'f' };
const sandbox = { window: { fetch: fakeFetch, digitalisimoAssistantConfig: { rest: 'https://sitio.test/wp-json/digitalisimo-ai/v1/assistant/', restNonce: 'R1', nonce: 'A1', learning: true }, setTimeout }, document: { addEventListener() {}, removeEventListener() {} }, Object, JSON, Promise, Array, String, Math, encodeURIComponent, Error };
sandbox.window.wp = { element: el };
vm.runInNewContext(learn, sandbox);
const api = sandbox.window.digitalisimoAssistantLearning.createLearningAdapter(sandbox.window.digitalisimoAssistantConfig);
await api.view(); ok(calls.at(-1).url.endsWith('/assistant/learning') && calls.at(-1).options.method === 'GET', 'Lo aprendido: GET learning');
await api.train(); ok(calls.at(-1).url.endsWith('/learning/train') && calls.at(-1).options.method === 'POST', 'Entrenar: POST learning/train');
await api.step(); ok(calls.at(-1).url.endsWith('/learning/step'), 'Siguiente paso: learning/step');
await api.conversations(); ok(calls.at(-1).url.endsWith('/learning/conversations'), 'Aprender de mis conversaciones');
await api.edit('a1b2', 'Nuevo texto'); ok(calls.at(-1).url.endsWith('/learning/items/a1b2') && JSON.parse(calls.at(-1).options.body).text === 'Nuevo texto', 'Corregir un punto');
await api.remove('a1b2'); ok(calls.at(-1).url.endsWith('/learning/items/a1b2/delete') && calls.at(-1).options.method === 'POST', 'Borrar un punto (POST)');
await api.discard('a1b2'); ok(calls.at(-1).url.endsWith('/learning/items/a1b2/discard') && calls.at(-1).options.method === 'POST', 'Descartar una propuesta (POST)');
await api.clear(); ok(calls.at(-1).url.endsWith('/learning/clear'), 'Borrar todo');
ok(calls.every((c) => c.options.headers['X-WP-Nonce'] === 'R1' && c.options.headers['X-Digitalisimo-Assistant'] === 'A1' && c.options.credentials === 'same-origin'), 'Toda petición lleva la sesión y los dos nonces');
reply = () => ({ ok: false, status: 403, json: async () => ({ message: 'Lo que aprende el Asistente lo ve y lo cambia quien administra el sitio.' }) });
await assert.rejects(api.view(), /quien administra el sitio/); checks++;
ok(/set && set\.learning \? set\.learning : createLearningAdapter\( config \)/.test(learn), 'Otro conjunto de adaptadores con `learning` se respeta');

console.log(`Aprendizaje en la pantalla del Asistente: ${checks} comprobaciones correctas.`);
