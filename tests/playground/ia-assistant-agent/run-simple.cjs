// Conductor del ensayo «sin tecnicismos» con la API REAL (ia-assistant-simple.sh; casos plomerito 2, 3 y 4 del 2026-11-24
// al 2026-11-27). Caso 4: «el asistente lo veo muy técnico, debe ser fácil de usar para el usuario sin nada de
// conocimientos que sólo quiere un sitio o tienda web»; una revisión de Skydropx pidió «Este cambio no tiene vuelta
// atrás: confírmalo» con el JSON de la entrada, y el Asistente dijo «Te conviene quitar tú mismo el texto en latín…» y
// «¿Seguimos con "Sample Page"?». Habla con la REST del Asistente como el navegador, en una ferretería inventada:
//   A1 «revisa el sitio web y optimizalo en SEO»                                    (caso 4, primera parte)
//   A2 «revisa si el plugin skydropx esta funcionando bien y mi carrito funciona bien» (caso 4, misma conversación)
//   B  «creame una landing para blackfriday con la promoción del purificador aquaplus de 10000 a 7500» (caso 2)
//   C  «Creame una landingpage de oferta navideña con todos los productos al 40% de descuento»        (caso 3)
// y mide de forma automática:
//   R1  cero tarjetas de confirmación para revisiones (una llamada que sólo lee nunca llega como tarjeta);
//   J1  cero palabras técnicas en lo que ve la persona: respuestas, frases de las tarjetas, la línea plegada, el rótulo
//       de cada paso, los estados y los avisos;
//   D1  cero «hazlo tú» cuando hay herramienta (Google, páginas de prueba, descripciones, peso, precios, borradores);
//   Q1  como mucho una pregunta por respuesta, y nunca «¿seguimos?» ni «¿lo hago?»;
//   K1  Skydropx: revisó con lecturas y dice en llano lo que falla (los productos sin peso) y cómo lo arregla;
//   L1  las landings se crean en su primer turno, sin tarjeta.
// Deja en <dir>: conversacion.json, respuestas.md, evaluacion.json y result.txt.
// Uso: node run-simple.cjs <puerto> <host> <dir> real
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Las piezas de la pantalla (assistant.js): la línea plegada, los rótulos y los estados, como los ve la persona.
let screen = null;
{
  const ui = fs.readFileSync(path.join(__dirname, '../../../digitalisimo-ia-tools/assets/assistant/assistant.js'), 'utf8');
  const element = { createElement: () => ({}), Fragment: 'F', useState: () => [null, () => {}], useEffect: () => {}, useRef: () => ({}), useMemo: (f) => f(), useCallback: (f) => f, createRoot: () => ({ render: () => {} }), render: () => {} };
  const w = { wp: { element }, digitalisimoAssistantConfig: { site: { home: 'http://x.test/' } }, digitalisimoAssistantAdapters: { create: () => ({}) }, digitalisimoAssistantExpose: (p) => { screen = p; }, location: { href: 'http://x.test/wp-admin/', host: 'x.test' }, URL, localStorage: { getItem: () => null, setItem: () => {} }, matchMedia: () => ({ matches: false, addEventListener() {}, removeEventListener() {} }) };
  vm.runInNewContext(ui, { window: w, document: { getElementById: () => ({}) }, console });
}

const [port, host, dir, mode] = process.argv.slice(2);
const token = fs.readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const ready = JSON.parse(fs.readFileSync(`${dir}/probe/ready.json`, 'utf8'));
const results = [];
let failures = 0;
const log = (line) => { fs.appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (id, ok, what, detail = '') => {
  results.push({ id, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 2000) });
  if (ok) log(`ok · ${id} · ${what}`); else { failures++; log(`FALLA · ${id} · ${what}${detail ? ` · ${String(detail).slice(0, 2000)}` : ''}`); }
  return !!ok;
};
const cookies = {};
function request(method, p, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { Host: host, Cookie: Object.entries(cookies).map(([k, v]) => `${k}=${v}`).join('; '), ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path: p, headers: all, timeout: 40 * 60 * 1000 }, (res) => {
      for (const c of res.headers['set-cookie'] || []) { const [pair] = c.split(';'); const i = pair.indexOf('='); cookies[pair.slice(0, i)] = pair.slice(i + 1); }
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => { const text = Buffer.concat(chunks).toString('utf8'); let json = null; try { json = JSON.parse(text); } catch { /* no JSON */ } resolve({ status: res.statusCode, text, json }); });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${p}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const probe = async (what) => (await request('GET', `/?digi_ias=${what}&token=${token}`)).json;

// Lo que sólo lee (la misma regla que la tabla del Asistente, escrita aparte para no fiarse de ella).
const READS = {
  'digitalisimo-woo/products': ['list', 'get'], 'digitalisimo-woo/catalog': ['list', 'get'], 'digitalisimo-woo/orders': ['list', 'get'],
  'digitalisimo-woo/customers': ['list', 'get'], 'digitalisimo-woo/coupons': ['list', 'get'], 'digitalisimo-woo/store-settings': ['list', 'get'],
  'digitalisimo-woo/reports': ['list', 'sales', 'top_sellers', 'orders_totals', 'products_totals', 'customers_totals', 'coupons_totals', 'reviews_totals', 'log', 'changes'],
  'digitalisimo-woo/skydropx': ['status', 'audit_products', 'quote', 'shipments', 'get_shipment', 'label'],
  'digitalisimo-hosting/settings': ['get', 'log'], 'digitalisimo-hosting/domain-product': ['list'],
};
const READ_ONLY = /^(digitalisimo-seo\/(get-|analyze-)|digitalisimo-ia\/(read-page-text|site-contact|open-assistant)|digitalisimo-sales\/(list-templates|guide)|elementor\/(get-|list-|read-)|digitalisimo-tools\/plugin-health|core\/get-)/;
const isRead = (ability, input) => (READS[ability] ? READS[ability].includes(String((input || {}).action || '').toLowerCase()) : READ_ONLY.test(ability));

// Palabras técnicas que la persona nunca debe ver (casos plomerito 2 y 4).
const JARGON = [
  [/\bID\b|\bid\s*\d|post_id|page_id|product_id/i, 'IDs'],
  [/digitalisimo-[a-z]+\/|elementor\/|create-page|read-page-text|site-contact|list-templates|audit_products|update_products|per_page|\baction\b/i, 'nombres de herramientas o de acciones'],
  [/\bMCP\b|\bREST\b|\bAPI\b|\bJSON\b|\bendpoints?\b/i, 'MCP, REST, API, JSON o endpoint'],
  [/schema|canonical|\bslug|widget|placeholder|noindex|nofollow|metadatos|meta ?descripci[oó]n|\bmeta\b(?!\s+de\b)/i, 'schema, canonical, slug, meta, noindex…'],
  [/\bplugins?\b/i, 'plugin'],
  [/plantilla\s*(0?\d|«|")|venta[- ]corta/i, 'nombres o números de plantillas'],
  [/\bUTC\b|zona horaria|GMT/i, 'zonas horarias'],
  [/\?page_id=|\?p=\d|preview=true/i, 'direcciones con ?page_id='],
  [/(^|[\s(«"])\/[a-z0-9-]+\/(?=[\s.,;)»"]|$)/i, 'rutas como /sample-page/'],
  [/resultado desconocido|acción destructiva|vuelta atrás: confírmalo|Datos exactos|Agrandar los datos|aplicado igual/i, 'textos de la tarjeta vieja'],
  [/[{}]\s*"|"\s*:\s*[{["\d]/, 'JSON crudo'],
];
const withoutLinks = (text) => String(text).replace(/\[([^\]\n]{1,100})\]\((https?:\/\/[^\s)]+)\)/g, '$1').replace(/https?:\/\/\S+/g, '');
const jargonOf = (text) => JARGON.filter(([re]) => re.test(withoutLinks(text))).map(([re, name]) => `${name} («${(withoutLinks(text).match(re) || [''])[0]}»)`);
// «Hazlo tú»: mandar a la persona a hacer lo que el Asistente puede hacer con sus herramientas.
const DIY = /(t[úu] mism[oa]|hazlo t[úu]|te conviene (que )?(quites|cambies|pongas|borres|agregues|revises|edites|quitar|cambiar|poner|borrar|agregar|revisar|editar|entrar)|tendr[áa]s que|tienes que (entrar|ir|quitar|cambiar|poner|editar)|deber[íi]as (quitar|cambiar|poner|entrar|editar)|entra (a|al|en) (tu )?(escritorio|editor|panel)|ve a (Ajustes|Apariencia|WooCommerce|Productos|P[áa]ginas)|hay que corregir(lo)? en el editor|desde aqu[íi] no puedo)/i;
const WITH_TOOL = /Google|descripci[oó]n|t[íi]tulo|Sample Page|p[áa]gina de (ejemplo|prueba)|\bpeso\b|medidas|precio|ocult|landing|borrador|oferta/i;
const sentences = (text) => withoutLinks(text).split(/(?<=[.!?])\s+|\n+/).filter((s) => s.trim());
const diyOf = (text) => sentences(text).filter((s) => DIY.test(s) && WITH_TOOL.test(s));
const diyAny = (text) => sentences(text).filter((s) => DIY.test(s));
const questions = (text) => (withoutLinks(text).match(/\?/g) || []).length;
const ASK_NEXT = /¿\s*(seguimos|contin[úu]o|contin[úu]amos|quieres que (siga|contin[úu]e|lo haga)|lo hago|te parece si sigo)\b/i;

// Lo que la persona ve: los mensajes del Asistente que la pantalla NO pliega (groupMessages() de assistant.js).
function visible(events) {
  const msgs = events.filter((e) => e.type === 'message' || e.type === 'tool').map((e) => e.message);
  return screen.groupMessages(msgs).filter((it) => it.message && it.message.role === 'assistant').map((it) => it.message.text);
}

const CONVERSATIONS = [
  ['A', ['revisa el sitio web y optimizalo en SEO', 'revisa si el plugin skydropx esta funcionando bien y mi carrito funciona bien']],
  ['B', ['creame una landing para blackfriday con la promoción del purificador aquaplus de 10000 a 7500']],
  ['C', ['Creame una landingpage de oferta navideña con todos los productos al 40% de descuento']],
  // Revisión de ia/asistente-optimizado (2026-11-27): una página que no es del kit también se llena en su primer turno.
  ['D', ['hazme una página de Nosotros con la historia de la ferretería']],
];

(async () => {
  const transcript = [];
  let nonces;
  let conversation = 0;
  const call = async (route, payload) => {
    const started = Date.now();
    const res = await request('POST', `/?rest_route=${encodeURIComponent(`/digitalisimo-ai/v1/assistant/${route}`)}`, { headers: { 'X-WP-Nonce': nonces.rest, 'X-Digitalisimo-Assistant': nonces.assistant }, body: payload });
    return { ...res, seconds: Math.round((Date.now() - started) / 1000) };
  };
  const send = async (label, text) => {
    const turn = { label, text, events: [], seconds: 0, requests: 0 };
    let res = await call('message', { conversation, message: text, provider: 'auto' });
    for (;;) {
      turn.requests++;
      turn.seconds += res.seconds;
      if (res.status !== 200 || !res.json || !Array.isArray(res.json.events)) { turn.error = `HTTP ${res.status}: ${res.text.slice(0, 400)}`; break; }
      turn.events.push(...res.json.events);
      conversation = res.json.conversation?.id || conversation;
      if (!res.json.events.some((e) => e.type === 'continue')) break;
      res = await call('continue', { conversation });
    }
    turn.visible = visible(turn.events);
    turn.cards = turn.events.filter((e) => e.type === 'confirm').map((e) => e.action);
    turn.tools = turn.events.filter((e) => e.type === 'tool').map((e) => e.message);
    transcript.push(turn);
    log(`\n» [${label}] ${text}\n  (${turn.seconds} s, ${turn.requests} petición(es), ${turn.tools.length} paso(s): ${turn.tools.map((m) => `${m.kind}:${m.ability}:${m.status}`).join(', ')})\n  ${turn.visible.join('\n  ---\n  ').slice(0, 3000)}${turn.cards.map((a) => `\n  [TARJETA] ${a.text}${a.final ? ` · ${a.final}` : ''}${a.undo ? ' · Puedes deshacerlo después.' : ''}${a.ack ? ` · [casilla] ${a.ack_label}` : ''}`).join('')}${turn.error ? `\n  ERROR ${turn.error}` : ''}`);
    return turn;
  };

  try {
    await probe('login');
    nonces = await probe('nonces');
    check('S0', nonces && nonces.user === ready.admin && nonces.rest && nonces.assistant, 'sesión de admin y los dos nonces del Asistente', JSON.stringify(nonces));
    for (const [label, messages] of CONVERSATIONS) {
      conversation = 0;
      for (const [i, text] of messages.entries()) await send(`${label}${i + 1}`, text);
    }
    const state = await probe('state');
    fs.writeFileSync(`${dir}/conversacion.json`, JSON.stringify({ mode, ready, transcript, state: { ...state, log: (state.log || []).slice(-80) } }, null, 1));
    // MODE=medir: sin API; sólo importan las peticiones guardadas en probe/bodies (lo que se habría mandado).
    if (mode === 'medir') { log(`info · medir: ${((state.seen || {}).requests || []).length} peticiones guardadas en probe/bodies`); return; }
    // Consumo real (docs/ASSISTANT.md, «Modelo fijo»): tokens que cobró la API, su costo y el tiempo, por modelo.
    const usage = ((state.seen || {}).usage || []).filter((u) => u.usage);
    const PRICES = { // USD por millón: entrada, salida, caché leída, caché escrita 5 min; Haiku 5.5 sube pasando 100 000.
      'claude-opus-5-5': [4, 20, 0.2, 5], 'claude-sonnet-5-5': [2, 10, 0.1, 2.5], 'claude-haiku-5-5': [0.1, 0.5, 0.01, 0.125],
    };
    const model = ready.model || ((state.seen || {}).requests || [])[0]?.model || '';
    let usd = 0; const sum = { input: 0, cache_read: 0, cache_write: 0, output: 0 }; let prompts = 0; let biggest = 0;
    for (const u of usage) {
      const t = { input: u.usage.input_tokens || 0, cache_read: u.usage.cache_read_input_tokens || 0, cache_write: u.usage.cache_creation_input_tokens || 0, output: u.usage.output_tokens || 0 };
      for (const k of Object.keys(sum)) sum[k] += t[k];
      const prompt = t.input + t.cache_read + t.cache_write;
      prompts += prompt; biggest = Math.max(biggest, prompt);
      let p = PRICES[u.model || model] || PRICES[model];
      if (p && /haiku-5-5/.test(u.model || model) && prompt > 100000) p = [0.5, 2.5, 0.05, 0.625];
      if (p) usd += (t.input * p[0] + t.output * p[1] + t.cache_read * p[2] + t.cache_write * p[3]) / 1e6;
    }
    const consumo = { model, steps: usage.length, errors: ((state.seen || {}).usage || []).filter((u) => u.code && u.code !== 200).length, tokens: sum, prompt_avg: usage.length ? Math.round(prompts / usage.length) : 0, prompt_max: biggest, usd: Math.round(usd * 10000) / 10000, seconds: transcript.reduce((n, t) => n + t.seconds, 0), turns: transcript.map((t) => ({ label: t.label, seconds: t.seconds })) };
    fs.writeFileSync(`${dir}/consumo.json`, JSON.stringify(consumo, null, 1));
    log(`info · consumo · ${model} · ${consumo.steps} pasos · ${consumo.prompt_avg} tokens de contexto por paso (máx. ${biggest}) · salida ${sum.output} · ${consumo.usd} USD · ${consumo.seconds} s${consumo.errors ? ` · ${consumo.errors} respuestas no 200` : ''}`);

    const md = [`# «Sin tecnicismos» con la API real (${new Date().toISOString()})`, ''];
    for (const t of transcript) {
      md.push(`## » [${t.label}] ${t.text}`, '', `_${t.seconds} s · ${t.requests} petición(es)_`, '', '**Lo que ve la persona:**', '');
      const items = screen.groupMessages(t.events.filter((e) => e.type === 'message' || e.type === 'tool').map((e) => e.message));
      for (const it of items) if (it.work) md.push(`> ▸ ${screen.workTitle(it.work)}`, '');
      for (const v of t.visible) md.push(v, '');
      for (const a of t.cards) md.push(`> **Tarjeta:** ${a.text}${a.undo ? ' Puedes deshacerlo después.' : ''}${a.final ? ` ${a.final}` : ''}${a.ack ? ` ☐ ${a.ack_label}` : ''} [Sí, hazlo] [No]`, '');
      md.push('<details><summary>Trabajo plegado</summary>', '');
      for (const m of t.tools) md.push(`- ${screen.VERBS[m.kind] || 'Paso'}: ${m.label} — ${screen.STATES[m.status] || m.status} · \`${m.ability} ${m.summary ? m.summary.slice(0, 160) : ''}\``);
      md.push('', '</details>', '');
    }
    fs.writeFileSync(`${dir}/respuestas.md`, md.join('\n'));

    check('T0', transcript.every((t) => !t.error), 'ninguna petición falló', JSON.stringify(transcript.map((t) => t.error).filter(Boolean)));
    const cards = transcript.flatMap((t) => t.cards.map((a) => ({ turn: t.label, a })));
    const cardInput = (a) => { try { return JSON.parse(a.input || '{}'); } catch { return {}; } };

    // R1 · Ninguna revisión pide confirmación.
    const readCards = cards.filter(({ a }) => isRead(a.ability, cardInput(a)));
    check('R1', readCards.length === 0, `cero tarjetas de confirmación en revisiones (${readCards.length} de ${cards.length} tarjetas)`, readCards.map(({ turn, a }) => `${turn}: ${a.ability} ${a.input}`).join(' | '));
    const reads = transcript.flatMap((t) => t.tools).filter((m) => m.kind === 'read');
    check('R2', reads.length > 0 && reads.every((m) => m.status !== 'skipped'), `las revisiones corrieron solas (${reads.length} consultas)`);

    // J1 · Ninguna palabra técnica en lo que ve la persona.
    const seen = [];
    for (const t of transcript) {
      for (const v of t.visible) seen.push([`${t.label} respuesta`, v]);
      for (const a of t.cards) seen.push([`${t.label} tarjeta`, [a.text, a.final, a.ack ? a.ack_label : '', ...(a.warnings || [])].join(' ')]);
      const items = screen.groupMessages(t.events.filter((e) => e.type === 'message' || e.type === 'tool').map((e) => e.message));
      for (const it of items) if (it.work) seen.push([`${t.label} línea plegada`, screen.workTitle(it.work)]);
      for (const m of t.tools) seen.push([`${t.label} paso`, `${screen.VERBS[m.kind] || 'Paso'}: ${m.label} — ${screen.STATES[m.status] || m.status}`]);
      for (const e of t.events) if (e.type === 'message' && e.message.role === 'system') seen.push([`${t.label} aviso`, e.message.text]);
    }
    const jargon = seen.map(([where, text]) => [where, text, jargonOf(text)]).filter(([, , j]) => j.length);
    check('J1', jargon.length === 0, `cero palabras técnicas en ${seen.length} textos que ve la persona`, jargon.map(([w, text, j]) => `${w}: ${j.join(', ')} → ${text.slice(0, 200)}`).join(' | '));

    // D1 · Nada de «hazlo tú» cuando hay herramienta.
    const diy = transcript.flatMap((t) => t.visible.flatMap((v) => diyOf(v).map((s) => `${t.label}: ${s}`)));
    check('D1', diy.length === 0, `cero «hazlo tú» cuando hay herramienta (${diy.length})`, diy.join(' | '));
    // D3 · Revisar no crea nada: en las revisiones (A1 y A2) ningún borrador ni página de prueba (corrida 8 de Haiku
    // del 2026-11-27: creó «__prueba_no_crear__» para «revisar» el carrito).
    const reviewDrafts = transcript.filter((t) => /^A/.test(t.label)).flatMap((t) => t.tools.filter((m) => m.kind === 'draft').map((m) => `${t.label}: ${m.ability} ${m.summary || ''}`.slice(0, 200)));
    check('D3', reviewDrafts.length === 0, `las revisiones no crean borradores ni páginas (${reviewDrafts.length})`, reviewDrafts.join(' | '));
    const diyOther = transcript.flatMap((t) => t.visible.flatMap((v) => diyAny(v).filter((s) => !WITH_TOOL.test(s)).map((s) => `${t.label}: ${s}`)));
    log(`info · D2 · pasos que se le piden a la persona donde no hay herramienta (permitido, con pasos simples): ${diyOther.length ? diyOther.join(' | ') : 'ninguno'}`);

    // Q1 · Una sola pregunta, y nunca «¿seguimos?».
    const many = transcript.flatMap((t) => t.visible.filter((v) => questions(v) > 1).map((v) => `${t.label} (${questions(v)}): ${v.slice(0, 300)}`));
    check('Q1', many.length === 0, 'como mucho una pregunta por respuesta', many.join(' | '));
    const askNext = transcript.flatMap((t) => t.visible.filter((v) => ASK_NEXT.test(v)).map((v) => `${t.label}: ${v.match(ASK_NEXT)[0]}`));
    check('Q2', askNext.length === 0, 'nunca «¿seguimos?» ni «¿lo hago?» para lo siguiente', askNext.join(' | '));

    // K1 · Skydropx y el carrito, en llano.
    const a2 = transcript.find((t) => t.label === 'A2');
    const skyReads = a2.tools.filter((m) => m.ability === 'digitalisimo-woo/skydropx');
    const skyAudit = skyReads.filter((m) => /"action":"(status|audit_products|quote)"/.test(m.summary || ''));
    check('K1', skyAudit.length > 0 && skyAudit.every((m) => m.kind === 'read') && a2.cards.filter((a) => isRead(a.ability, cardInput(a))).length === 0, `las revisiones de Skydropx corrieron como lecturas, sin tarjeta (${skyReads.map((m) => `${m.kind}:${m.status}`).join(', ')})`, JSON.stringify(a2.tools.map((m) => `${m.kind}:${m.ability}:${m.status}:${m.summary}`)));
    const a2text = a2.visible.join('\n');
    check('K0', a2text.trim().length > 0, 'el informe de Skydropx y el carrito se ve (no queda escondido detrás de una tarjeta)', JSON.stringify(a2.events.filter((e) => e.type === 'message').map((e) => e.message)));
    check('K2', /\bpeso/i.test(a2text) && /(3|tres) (de (tus|los) \d+ )?productos/i.test(a2text), 'dice que hay productos sin peso (y cuántos)', a2text);
    check('K3', /carrito|pago|transferencia|contra entrega/i.test(a2text), 'dice cómo está el carrito o los pagos', a2text);

    // L1 · Las landings se crean en su primer turno, sin tarjeta.
    for (const label of ['B1', 'C1']) {
      const t = transcript.find((x) => x.label === label);
      const made = t.tools.some((m) => m.kind === 'draft' && m.status === 'ok' && m.ability === 'digitalisimo-sales/create-page');
      check(`L1-${label}`, made && t.cards.length === 0, `${label}: la landing quedó en borrador en el primer turno, sin tarjeta`, JSON.stringify(t.tools.map((m) => `${m.kind}:${m.ability}:${m.status}`)));
    }
    // L2 · Una página que no es del kit: se crea en blanco y recibe su contenido en el mismo turno, sin tarjeta.
    {
      const t = transcript.find((x) => x.label === 'D1');
      const made = t.tools.some((m) => m.kind === 'draft' && m.status === 'ok' && /^(elementor\/create-page|digitalisimo-sales\/create-page)$/.test(m.ability));
      const filled = t.tools.some((m) => m.kind === 'draft' && m.status === 'ok' && /^(elementor\/build-composition|digitalisimo-sales\/create-page)$/.test(m.ability));
      check('L2-D1', made && filled && t.cards.length === 0, 'D1: la página de Nosotros quedó en borrador con su contenido en el primer turno, sin tarjeta', JSON.stringify(t.tools.map((m) => `${m.kind}:${m.ability}:${m.status}`)));
    }
    const tokens = transcript.reduce((n, t) => n + t.seconds, 0);
    log(`info · tiempo total de los turnos: ${tokens} s · tarjetas: ${cards.length} (${cards.map(({ turn, a }) => `${turn}: ${a.text}`).join(' | ') || 'ninguna'})`);
  } catch (e) {
    check('X', false, 'el conductor terminó sin errores', e.stack || String(e));
  } finally {
    try { await probe('wipe'); } catch { /* el arnés borra la clave igual */ }
    fs.writeFileSync(`${dir}/evaluacion.json`, JSON.stringify({ failures, results }, null, 1));
    log(failures ? `\n${failures} comprobación(es) fallaron` : '\nTodas las comprobaciones pasaron');
    process.exit(failures ? 1 : 0);
  }
})();
