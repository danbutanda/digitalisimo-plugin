// Conductor del ensayo «primero hacer y mostrar» con la API REAL (ia-assistant-mostrar.sh; caso plomerito 2 del
// 2026-11-24, IA Tools 1.0.72): «es muy preguntón, arroja información técnica que el usuario nunca va a entender y toca
// cosas de más: sólo le pedí una landing y metió muchas cosas como editar producto»; «el usuario promedio quiere primero
// ver y luego cambiar las cosas». Habla con la REST del Asistente como el navegador y manda, en una tienda inventada:
//   1 «En qué puedes ayudarme con mi sitio web ?»
//   2 «créame una landing de Black Friday del purificador de 10,000 a 7,500, hasta fin de mes, con el número del sitio»
//   3 «Cámbiale el título a "Black Friday: agua pura en tu casa"» (rehacer o corregir el MISMO borrador)
// y mide de forma automática:
//   C1–C3  «¿en qué me ayudas?»: corto (≤ 6 líneas), sin jerga, con un ejemplo;
//   Q1–Q2  cero preguntas antes de crear y el borrador creado en el primer turno del pedido;
//   W1–W4  ninguna escritura fuera de la landing: ninguna tarjeta, ningún intento de cambiar el producto (tampoco uno
//          retenido por el servidor), el producto igual y una sola landing (el turno 3 corrige la misma);
//   J1–J2  ninguna palabra técnica de la lista ni URL suelta en lo que la persona ve; los enlaces van con texto;
//   L1     respuestas cortas;
//   B1–B5  la landing: producto, precios, WhatsApp de la tienda, sin bloques de muestra, sin enlaces rotos y el
//          contador cerrando a la medianoche de la tienda (sitio en UTC, tienda en México);
//   E1     consultas acotadas (el caso hizo 19 para un teléfono y unas políticas).
// Deja en <dir>: conversacion.json, respuestas.md, evaluacion.json (y landing.png si hay Chrome).
// Uso: node run-mostrar.cjs <puerto> <host> <dir> real
const http = require('node:http');
const fs = require('node:fs');

const [port, host, dir, mode] = process.argv.slice(2);
const token = fs.readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const ready = JSON.parse(fs.readFileSync(`${dir}/probe/ready.json`, 'utf8'));
const results = [];
let failures = 0;
const log = (line) => { fs.appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (id, ok, what, detail = '') => {
  results.push({ id, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 1200) });
  if (ok) log(`ok · ${id} · ${what}`); else { failures++; log(`FALLA · ${id} · ${what}${detail ? ` · ${String(detail).slice(0, 1200)}` : ''}`); }
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

const MESSAGES = [
  'En qué puedes ayudarme con mi sitio web ?',
  'créame una landing de Black Friday del purificador de 10,000 a 7,500, hasta fin de mes, con el número del sitio',
  'Cámbiale el título a "Black Friday: agua pura en tu casa"',
];

// Lo que la persona ve: los mensajes del Asistente que la pantalla NO pliega (la misma regla que groupMessages() de
// assistant.js: un mensaje con llamadas al que le siguen herramientas en su turno va dentro de «Revisé tu sitio»).
function visible(events) {
  const msgs = events.filter((e) => e.type === 'message' || e.type === 'tool').map((e) => e.message);
  const out = [];
  msgs.forEach((m, i) => {
    if (m.role !== 'assistant') return;
    const toolAfter = msgs.slice(i + 1).some((x) => x.role === 'tool');
    if (m.work && toolAfter) return;
    out.push(m.text);
  });
  return out;
}

// Palabras técnicas que la persona nunca debe ver (pedido del dueño, caso plomerito 2).
const JARGON = [
  [/\bID\b|\bid\s*\d|post_id|page_id|product_id/i, 'IDs'],
  [/digitalisimo-|elementor\/|create-page|read-page-text|site-contact|list-templates|\bherramienta [a-z-]+\/[a-z-]+/i, 'nombres de herramientas'],
  [/\bMCP\b|\bREST\b|\bAPI\b|\bJSON\b/, 'MCP, REST, API'],
  [/schema|canonical|\bslug|widget|placeholder/i, 'schema, canonical, slug, widget o placeholder'],
  [/plantilla\s*(0?\d|«|")|venta[- ]corta|\b02\b/i, 'nombres o números de plantillas'],
  [/\bUTC\b|zona horaria|GMT/i, 'zonas horarias'],
  [/\?page_id=|\?p=\d|preview=true/i, 'direcciones con ?page_id='],
  [/(^|[\s(«"])\/[a-z0-9-]+\/(?=[\s.,;)»"]|$)/i, 'rutas como /politica-de-reembolso/'],
  [/apariciones|\d+ marcadores|\[[^\]\n(]{2,60}\](?!\()/i, 'recuentos técnicos o marcadores'],
  [/resultado desconocido|acción destructiva/i, 'estados técnicos'],
];
// Quita los enlaces con texto «[Ver la landing](URL)» (eso SÍ está bien) para revisar el resto.
const withoutLinks = (text) => String(text).replace(/\[([^\]\n]{1,100})\]\((https?:\/\/[^\s)]+)\)/g, '$1');

(async () => {
  const transcript = [];
  let nonces;
  let conversation = 0;
  const call = async (route, payload) => {
    const started = Date.now();
    const res = await request('POST', `/?rest_route=${encodeURIComponent(`/digitalisimo-ai/v1/assistant/${route}`)}`, { headers: { 'X-WP-Nonce': nonces.rest, 'X-Digitalisimo-Assistant': nonces.assistant }, body: payload });
    return { ...res, seconds: Math.round((Date.now() - started) / 1000) };
  };
  const send = async (text) => {
    const turn = { text, events: [], seconds: 0, requests: 0 };
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
    transcript.push(turn);
    const tools = turn.events.filter((e) => e.type === 'tool').map((e) => `${e.message.kind}:${e.message.ability}:${e.message.status}`);
    log(`\n» ${text}\n  (${turn.seconds} s, ${turn.requests} petición(es), ${tools.length} herramienta(s): ${tools.join(', ')})\n  ${turn.visible.join('\n  ---\n  ').slice(0, 3000)}${turn.error ? `\n  ERROR ${turn.error}` : ''}`);
    return turn;
  };

  try {
    await probe('login');
    nonces = await probe('nonces');
    check('S0', nonces && nonces.user === ready.admin && nonces.rest && nonces.assistant, 'sesión de admin y los dos nonces del Asistente', JSON.stringify(nonces));
    check('S1', ready.site_tz === 'UTC' || ready.site_tz === '+00:00', 'el sitio está en UTC, como el del caso', ready.site_tz);
    const before = await probe('state');
    for (const text of MESSAGES) await send(text);
    const state = await probe('state');
    const [t1, t2, t3] = transcript;
    const all = transcript.flatMap((t) => t.events);
    const tools = all.filter((e) => e.type === 'tool').map((e) => e.message);
    const cards = all.filter((e) => e.type === 'confirm').map((e) => e.action);

    fs.writeFileSync(`${dir}/conversacion.json`, JSON.stringify({ mode, ready, transcript, state: { ...state, log: (state.log || []).slice(-80) } }, null, 1));
    const md = [`# «Primero hacer y mostrar» con la API real (${new Date().toISOString()})`, ''];
    for (const t of transcript) {
      md.push(`## » ${t.text}`, '', `_${t.seconds} s · ${t.requests} petición(es)_`, '', '**Lo que ve la persona:**', '');
      for (const v of t.visible) md.push(v, '');
      md.push('<details><summary>Trabajo plegado</summary>', '');
      for (const e of t.events) {
        if (e.type === 'tool') md.push(`- \`${e.message.kind}\` ${e.message.label || e.message.ability} → ${e.message.status}${e.message.summary ? ` · ${e.message.summary.slice(0, 200)}` : ''}`);
        if (e.type === 'message' && e.message.role === 'assistant' && !t.visible.includes(e.message.text)) md.push(`- _nota:_ ${e.message.text.replace(/\n/g, ' ').slice(0, 300)}`);
        if (e.type === 'message' && e.message.role === 'system') md.push(`- _aviso:_ ${e.message.text}`);
        if (e.type === 'confirm') md.push(`- **Pide confirmación:** ${e.action.label} (${e.action.kind})`);
      }
      md.push('', '</details>', '');
    }
    md.push('## Lo creado', '');
    for (const p of state.made || []) md.push(`### ${p.title} (${p.status})`, '', `WhatsApp: ${p.whatsapp.join(', ') || '—'} · marcadores: ${p.placeholders.length} · enlaces rotos: ${p.broken.join(', ') || 'ninguno'} · contador: ${(p.due || []).join(', ') || '—'}`, '', '```', p.text.slice(0, 8000), '```', '');
    fs.writeFileSync(`${dir}/respuestas.md`, md.join('\n'));

    check('T0', transcript.every((t) => !t.error), 'ninguna petición falló', JSON.stringify(transcript.map((t) => t.error).filter(Boolean)));

    // C · «¿En qué puedes ayudarme?»
    const help = t1.visible.join('\n');
    const helpLines = help.split('\n').filter((l) => l.trim());
    check('C1', helpLines.length >= 2 && helpLines.length <= 7 && help.length <= 700, `«¿en qué me ayudas?» en pocas líneas (${helpLines.length} líneas, ${help.length} caracteres)`, help);
    check('C2', /p[áa]gina|landing/i.test(help) && /google|buscador/i.test(help) && /venta|pedido/i.test(help), 'habla de páginas o landings, de Google y de ventas o pedidos', help);
    check('C3', !t1.events.some((e) => e.type === 'tool' && e.message.kind !== 'read'), 'no toca nada al contestar qué puede hacer');

    // Q · Cero preguntas antes de crear; el borrador en el primer turno del pedido.
    const t2draft = t2.events.findIndex((e) => e.type === 'tool' && e.message.kind === 'draft' && e.message.status === 'ok' && e.message.ability === 'digitalisimo-sales/create-page');
    const askedBefore = t2.events.slice(0, t2draft < 0 ? t2.events.length : t2draft).filter((e) => e.type === 'message' && e.message.role === 'assistant' && /\?/.test(e.message.text));
    check('Q1', t2draft >= 0, 'la landing se crea en el primer turno del pedido, sin tarjeta', JSON.stringify(t2.events.filter((e) => e.type !== 'status').map((e) => (e.message ? `${e.type}:${e.message.kind || e.message.role}:${e.message.ability || ''}:${e.message.status || ''}` : e.type))));
    check('Q2', askedBefore.length === 0 && !t2.visible.some((v) => (withoutLinks(v).match(/\?/g) || []).length > 1), `cero preguntas antes de crear (y como mucho una al final): ${askedBefore.length}`, [...askedBefore.map((e) => e.message.text), ...t2.visible].join(' | '));

    // W · Sólo lo que se pidió.
    const productTry = tools.filter((m) => /digitalisimo-woo\/(products|catalog)|woocommerce\/|digi-ias\/apply-offer/.test(m.ability) && m.kind !== 'read');
    check('W1', cards.length === 0, `ninguna tarjeta de confirmación (${cards.length})`, cards.map((a) => `${a.label} [${a.kind}]`).join(', '));
    check('W2', productTry.length === 0, `ningún intento de cambiar el producto ni la tienda (${productTry.length}, también los que el servidor retuvo)`, productTry.map((m) => `${m.ability}:${m.status}:${m.summary}`).join(' | '));
    const writes = tools.filter((m) => m.kind !== 'read' && !(m.kind === 'draft' && /^(digitalisimo-sales\/create-page|elementor\/(build-composition|manage-elements|update-page-settings))$/.test(m.ability)));
    check('W3', writes.length === 0, 'ninguna escritura fuera de la landing', writes.map((m) => `${m.kind}:${m.ability}:${m.status}`).join(', '));
    check('W4', state.product && before.product && state.product.sale === '' && state.product.regular === '10000' && state.product.modified === before.product.modified, 'el producto quedó igual (precio normal 10,000, sin precio de oferta, sin cambios)', JSON.stringify({ before: before.product, after: state.product }));
    const landings = (state.made || []).filter((p) => p.type === 'page');
    check('W5', landings.length === 1, `una sola landing: el turno 3 corrigió la misma (${landings.length})`, landings.map((p) => `${p.title}`).join(' | '));

    // J · Lenguaje de usuario.
    const seen = transcript.flatMap((t) => t.visible.map((v) => ({ turn: t.text.slice(0, 30), text: v })));
    const jargon = [];
    for (const v of seen) {
      const plain = withoutLinks(v.text);
      for (const [re, what] of JARGON) { const m = plain.match(re); if (m) jargon.push(`${what}: «${m[0]}» en «${v.turn}…»`); }
      if (/https?:\/\//.test(plain)) jargon.push(`URL suelta en «${v.turn}…»`);
    }
    check('J1', jargon.length === 0, 'ninguna palabra técnica de la lista ni URL suelta en lo que ve la persona', jargon.join(' | '));
    const t2text = t2.visible.join('\n');
    check('J2', /\[[^\]]{2,40}\]\(https?:\/\/[^)]+\)/.test(t2text), 'entrega la landing con un enlace con texto («Ver la landing»)', t2text);

    // L · Corto.
    const longest = Math.max(...transcript.slice(1).map((t) => t.visible.join('\n').length));
    const lines = Math.max(...transcript.slice(1).map((t) => t.visible.join('\n').split('\n').filter((l) => l.trim()).length));
    check('L1', longest <= 900 && lines <= 10, `respuestas cortas (hasta ${longest} caracteres y ${lines} líneas)`, transcript.map((t) => t.visible.join('\n')).join('\n=====\n'));

    // B · La landing.
    const landing = landings[0];
    check('B0', !!landing, 'hay una landing en borrador');
    if (landing) {
      check('B1', /purificador/i.test(landing.text) && /7,500|7\.500|7500/.test(landing.text) && /10,000|10\.000|10000/.test(landing.text), 'lleva el producto, el precio de oferta 7,500 y el normal 10,000', landing.text.slice(0, 800));
      check('B2', landing.whatsapp.length > 0 && landing.whatsapp.every((n) => n === ready.whatsapp), 'sus botones de WhatsApp van al número de la tienda', landing.whatsapp.join(', '));
      const samples = landing.text.match(/Foto de la persona|detr[áa]s de la marca|Qui[ée]n est[áa] detr[áa]s|lorem ipsum|testimonio de muestra|\[[^\]\n]{2,80}\]|5210000000000|tu (logo|n[úu]mero)/gi) || [];
      check('B3', landing.placeholders.length === 0 && samples.length === 0, 'ningún bloque ni texto de muestra', [...landing.placeholders, ...samples].join(' · '));
      check('B4', landing.broken.length === 0, 'ningún enlace a páginas que no existen', landing.broken.join(', '));
      // Fin de mes a las 23:59 de México (la tienda), guardado en la zona del sitio (UTC): +6 h.
      const now = new Date();
      const mx = new Date(now.getTime() - 6 * 3600 * 1000);
      const last = new Date(Date.UTC(mx.getUTCFullYear(), mx.getUTCMonth() + 1, 0, 23, 59));
      const due = new Date(last.getTime() + 6 * 3600 * 1000).toISOString().slice(0, 16).replace('T', ' ');
      check('B5', (landing.due || []).length > 0 && landing.due.every((d) => d === due), `el contador cierra a la medianoche de la tienda (${due} en la hora del sitio)`, JSON.stringify(landing.due));
      check('B6', /agua pura en tu casa/i.test(`${landing.title} ${landing.text}`), 'el turno 3 cambió el título en la misma landing', `${landing.title}`);
    }

    // E · Consultas acotadas.
    const reads2 = t2.events.filter((e) => e.type === 'tool' && e.message.kind === 'read').length;
    check('E1', reads2 <= 6, `consultas acotadas para crear la landing (${reads2}; el caso hizo 19)`, t2.events.filter((e) => e.type === 'tool').map((e) => e.message.ability).join(', '));

    fs.writeFileSync(`${dir}/evaluacion.json`, JSON.stringify({ results, failures, seconds: transcript.map((t) => t.seconds), tools: tools.map((m) => `${m.kind}:${m.ability}:${m.status}`), landing: landing ? { title: landing.title, whatsapp: landing.whatsapp, placeholders: landing.placeholders, broken: landing.broken, due: landing.due } : null, visible: transcript.map((t) => t.visible) }, null, 1));
    if (landing) {
      try {
        const puppeteer = require('puppeteer-core');
        const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox'] });
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 900 });
        const base = `http://${host}`;
        await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value: decodeURIComponent(value), url: base })));
        await page.goto(`${base}/?page_id=${landing.id}&preview=true`, { waitUntil: 'networkidle2', timeout: 180000 });
        await page.screenshot({ path: `${dir}/landing.png`, fullPage: true });
        await browser.close();
        log('captura: landing.png');
      } catch (e) { log(`sin captura: ${e.message}`); }
    }
  } catch (e) {
    check('X', false, 'el ensayo terminó sin errores', e.stack || e.message);
  } finally {
    try { await probe('wipe'); } catch { /* el servidor ya no responde */ }
    fs.writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, results }, null, 1));
    log(failures ? `\n${failures} falla(s)` : `\nTodo bien (${results.length} comprobaciones).`);
    process.exit(failures ? 1 : 0);
  }
})();
