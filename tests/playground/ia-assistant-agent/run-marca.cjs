// Conductor del ensayo «la landing con la marca del sitio» con la API REAL (ia-assistant-marca.sh; caso plomerito 3 del
// 2026-11-25, IA Tools 1.0.73 + Elements 4.3.0.197). Habla con la REST del Asistente como el navegador y manda, en una
// tienda inventada con su propia marca (morado y naranja, Montserrat y Lato):
//   «créame una landing de oferta navideña con todos los productos al 40 %»
// y mide de forma automática, en la landing que se ve (Chrome, vista previa con la sesión iniciada):
//   C1–C3  colores: los de la marca del sitio están (fondo morado y botón naranja) y no aparece ningún color de la
//          paleta DIGITALÍSIMO; todo color con tono de la landing es del sitio (su kit o lo que se ve en su cabecera y
//          su pie);
//   F1–F2  fuentes: los títulos en Montserrat y el texto en Lato (las del sitio), nunca Roboto de fábrica;
//   H1     la cabecera y el pie del sitio (las plantillas del constructor de temas) en la página;
//   G1–G3  la cuadrícula: productos reales con foto, su precio normal tachado y el 40 % menos, en pesos;
//   X1     ningún texto de cursos («Lo que vas a lograr», bonus, módulos, factura, resultados e ingresos…);
//   B1     el botón principal lleva a la tienda (nunca al carrito);
//   Q1–Q2  cero preguntas antes de crear, la landing en el primer turno sin tarjeta;
//   W1–W2  ningún precio de la tienda cambió ni hubo intentos (ni cupones);
//   J1     nada de jerga ni URLs sueltas en lo que ve la persona; J2 enlaces con texto;
//   A1     no le pide a la persona activar ni configurar nada («Editor atómico», experimentos, ajustes);
//   V1     si dice que usa los colores del sitio, es porque la página los lleva (no afirma lo que no comprobó).
// Deja en <dir>: conversacion.json, respuestas.md, evaluacion.json, medicion.json y landing.png (página entera) y
// landing-arriba.png (la primera pantalla).
// Uso: node run-marca.cjs <puerto> <host> <dir> real
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

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
  results.push({ id, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 1500) });
  if (ok) log(`ok · ${id} · ${what}`); else { failures++; log(`FALLA · ${id} · ${what}${detail ? ` · ${String(detail).slice(0, 1500)}` : ''}`); }
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

// El caso «producto» pide la landing de UN producto; los demás, la oferta de toda la tienda.
const PRODUCT = ready.case === 'producto';
const MESSAGE = PRODUCT ? 'créame una landing del taladro inalámbrico 20 V con 25 % de descuento hasta el 24 de diciembre' : 'créame una landing de oferta navideña con todos los productos al 40 %';

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

// La misma lista de jerga del ensayo «mostrar» (caso plomerito 2) más lo de este caso.
const JARGON = [
  [/\bID\b|\bid\s*\d|post_id|page_id|product_id|store_offer/i, 'IDs o parámetros'],
  [/digitalisimo-|elementor\/|create-page|read-page-text|site-contact|list-templates|\bherramienta [a-z-]+\/[a-z-]+/i, 'nombres de herramientas'],
  [/\bMCP\b|\bREST\b|\bAPI\b|\bJSON\b|\bCSS\b|\bhex\b|#[0-9a-f]{6}\b/i, 'MCP, REST, API, CSS o códigos de color'],
  [/schema|canonical|\bslug|widget|placeholder|matches_site|\blook\b|\bcheck\b|globals?\b/i, 'schema, slug, widget, placeholder o nombres internos'],
  [/plantilla\s*(0?\d|«|")|venta[- ]corta|\b02\b/i, 'nombres o números de plantillas'],
  [/\bUTC\b|zona horaria|GMT/i, 'zonas horarias'],
  [/\?page_id=|\?p=\d|preview=true/i, 'direcciones con ?page_id='],
  [/(^|[\s(«"])\/[a-z0-9-]+\/(?=[\s.,;)»"]|$)/i, 'rutas'],
  [/apariciones|\d+ marcadores|\[[^\]\n(]{2,60}\](?!\()/i, 'recuentos técnicos o marcadores'],
  [/resultado desconocido|acción destructiva/i, 'estados técnicos'],
];
// Pedirle a la persona que active o configure algo (el caso terminó en «activa el Editor atómico»).
const ACTIVATE = /editor at[óo]mico|experimento|\bv4\b|activ(a|e|ar|es)\s+(la|el|una|un|esa|esta)\s+(opci[óo]n|funci[óo]n|ajuste|editor|casilla)|(entra|ve)\s+a\s+(digitalisimo|ajustes|elements|configuraci[óo]n)|desde aqu[íi] no (puedo|me es posible)|en el editor porque/i;
const COURSE = /lo que vas a lograr|\+?\s*todos los bonus|\bbonus\b|\bm[óo]dulos?\b|\bfactura|resultados e ingresos|resultados no son t[íi]picos|resultados dependen|inscripci[óo]n|clase en vivo|\bcurso\b/i;
const withoutLinks = (text) => String(text).replace(/\[([^\]\n]{1,100})\]\((https?:\/\/[^\s)]+)\)/g, '$1');

// La paleta DIGITALÍSIMO (scripts/sales-kit/kit_lib.py) y los colores de fábrica del kit.
const PALETTE = ['#1592DA', '#0A6FCF', '#0F141A', '#F5F8FB', '#00A86B', '#00FF99', '#1F252C', '#4E5863', '#E1E6EC', '#161D25', '#E9EEF3', '#9AA6B2', '#0B2A45', '#26313C', '#DCEBFA'];
const FACTORY = ['#6EC1E4', '#61CE70', '#54595F', '#7A7A7A'];
const hex = (rgb) => { const m = String(rgb).match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/); if (!m) return ''; if (m[4] !== undefined && Number(m[4]) < 0.6) return ''; return `#${[m[1], m[2], m[3]].map((x) => Number(x).toString(16).padStart(2, '0')).join('').toUpperCase()}`; };
const rgbOf = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const dist = (a, b) => { const x = rgbOf(a); const y = rgbOf(b); return Math.hypot(x[0] - y[0], x[1] - y[1], x[2] - y[2]); };
const vivid = (h) => { const [r, g, b] = rgbOf(h).map((c) => c / 255); const max = Math.max(r, g, b); const min = Math.min(r, g, b); const l = (max + min) / 2; if (max === min) return false; const d = max - min; const s = l > 0.5 ? d / (2 - max - min) : d / (max + min); return s >= 0.35 && l >= 0.15 && l <= 0.85; };
const near = (h, list, tol = 14) => list.some((x) => dist(h, x) < tol);
// Como escribe los precios WooCommerce con sus ajustes de fábrica: dos decimales, coma de miles y el símbolo antes.
const money = (n) => `$${Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const digits = (n) => Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });

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

  let landing = null;
  try {
    await probe('login');
    nonces = await probe('nonces');
    check('S0', nonces && nonces.user === ready.admin && nonces.rest && nonces.assistant, `sesión de admin y nonces · caso ${ready.case} · tema ${ready.theme}`, JSON.stringify(nonces));
    const before = await probe('products');
    const t = await send(MESSAGE);
    const state = await probe('state');
    const after = await probe('products');
    const tools = t.events.filter((e) => e.type === 'tool').map((e) => e.message);
    const cards = t.events.filter((e) => e.type === 'confirm').map((e) => e.action);
    landing = (state.made || []).filter((p) => p.type === 'page')[0] || null;

    fs.writeFileSync(`${dir}/conversacion.json`, JSON.stringify({ mode, ready, transcript, state: { ...state, log: (state.log || []).slice(-40) } }, null, 1));
    const md = [`# La landing con la marca del sitio (caso ${ready.case}, ${new Date().toISOString()})`, '', `## » ${t.text}`, '', `_${t.seconds} s · ${t.requests} petición(es)_`, '', '**Lo que ve la persona:**', ''];
    for (const v of t.visible) md.push(v, '');
    md.push('<details><summary>Trabajo plegado</summary>', '');
    for (const e of t.events) {
      if (e.type === 'tool') md.push(`- \`${e.message.kind}\` ${e.message.label || e.message.ability} → ${e.message.status}`);
      if (e.type === 'message' && e.message.role === 'assistant' && !t.visible.includes(e.message.text)) md.push(`- _nota:_ ${e.message.text.replace(/\n/g, ' ').slice(0, 300)}`);
      if (e.type === 'confirm') md.push(`- **Pide confirmación:** ${e.action.label} (${e.action.kind})`);
    }
    md.push('', '</details>', '');
    if (landing) md.push('## La landing', '', `${landing.title} (${landing.status})`, '', '```', landing.text.slice(0, 6000), '```', '');
    fs.writeFileSync(`${dir}/respuestas.md`, md.join('\n'));

    check('T0', !t.error, 'la petición no falló', t.error);
    // Q · Cero preguntas; la landing en el primer turno, sin tarjeta.
    const draftAt = t.events.findIndex((e) => e.type === 'tool' && e.message.kind === 'draft' && e.message.status === 'ok' && e.message.ability === 'digitalisimo-sales/create-page');
    const askedBefore = t.events.slice(0, draftAt < 0 ? t.events.length : draftAt).filter((e) => e.type === 'message' && e.message.role === 'assistant' && /\?/.test(e.message.text));
    check('Q1', draftAt >= 0 && cards.length === 0, `la landing se crea en el primer turno, sin tarjeta (${cards.length} tarjetas)`, JSON.stringify(tools.map((m) => `${m.kind}:${m.ability}:${m.status}`)));
    check('Q2', askedBefore.length === 0 && !t.visible.some((v) => (withoutLinks(v).match(/\?/g) || []).length > 1), `cero preguntas antes de crear (y como mucho una al final): ${askedBefore.length}`, [...askedBefore.map((e) => e.message.text), ...t.visible].join(' | '));
    // W · Ningún precio cambió.
    const priceTry = tools.filter((m) => /digitalisimo-woo\/(products|catalog|coupons)|woocommerce\//.test(m.ability) && m.kind !== 'read');
    check('W1', priceTry.length === 0, `ningún intento de cambiar precios, productos ni cupones (${priceTry.length})`, priceTry.map((m) => `${m.ability}:${m.status}`).join(' | '));
    check('W2', JSON.stringify(before.products) === JSON.stringify(after.products) && after.coupons === before.coupons, 'los precios de la tienda quedaron igual y no hay cupones nuevos', JSON.stringify({ before, after }).slice(0, 1200));
    // J · Lenguaje.
    const seen = t.visible.map((v) => ({ text: v }));
    const shown = t.events.filter((e) => e.type === 'message' || e.type === 'tool').map((e) => e.message);
    for (const item of screen.groupMessages(shown)) {
      if (item.work) {
        seen.push({ text: screen.workTitle(item.work) });
        for (const m of item.work.filter((x) => x.role === 'tool')) seen.push({ text: `${screen.VERBS[m.kind] || 'Paso'}: ${m.label || ''} · ${screen.STATES[m.status] || m.status}` });
      }
    }
    for (const e of t.events) { if (e.type === 'status') seen.push({ text: e.text }); if (e.type === 'message' && e.message.role === 'system') seen.push({ text: e.message.text }); }
    const jargon = [];
    for (const v of seen) {
      const plain = withoutLinks(v.text);
      for (const [re, what] of JARGON) { const m = plain.match(re); if (m) jargon.push(`${what}: «${m[0]}»`); }
      if (/https?:\/\//.test(plain)) jargon.push('URL suelta');
    }
    check('J1', jargon.length === 0, `nada de jerga ni URLs sueltas (${seen.length} textos)`, jargon.join(' | '));
    const reply = t.visible.join('\n');
    check('J2', /\[[^\]]{2,40}\]\(https?:\/\/[^)]+\)/.test(reply), 'entrega la landing con un enlace con texto', reply);
    check('A1', !ACTIVATE.test(reply) && !seen.some((v) => ACTIVATE.test(v.text)), 'no pide activar ni configurar nada, ni dice que no puede corregirla', reply);
    check('L1', reply.length <= 900, `respuesta corta (${reply.length} caracteres)`, reply);

    check('B0', !!landing, 'hay una landing en borrador');
    if (!landing) throw new Error('no se creó la landing');
    const meta = await probe(`landing&id=${landing.id}`);
    check('H0', meta.template === 'elementor_header_footer' || (meta.settings && meta.settings.template === 'elementor_header_footer'), `la página pide la cabecera y el pie del sitio (${meta.template || (meta.settings && meta.settings.template)})`);
    check('X1', !COURSE.test(landing.text), 'ningún texto de cursos en la landing', (landing.text.match(COURSE) || [''])[0]);

    // La landing como se ve: Chrome con la sesión iniciada.
    const puppeteer = require('puppeteer-core');
    const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    let m;
    try {
      const page = await browser.newPage();
      await page.setViewport({ width: 1280, height: 900 });
      const base = `http://${host}`;
      await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value: decodeURIComponent(value), url: base })));
      await page.goto(`${base}/?page_id=${landing.id}&preview=true`, { waitUntil: 'networkidle2', timeout: 240000 });
      await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 60)); } window.scrollTo(0, 0); });
      await new Promise((r) => setTimeout(r, 800));
      m = await page.evaluate((pid) => {
        const root = document.querySelector(`[data-elementor-id="${pid}"]`);
        const part = (type) => document.querySelector(`[data-elementor-type="${type}"]`);
        const out = { root: !!root, header: part('header') ? Number(part('header').dataset.elementorId) : 0, footer: part('footer') ? Number(part('footer').dataset.elementorId) : 0, colors: {}, siteColors: {}, headingFonts: {}, bodyFonts: {}, buttons: [], images: 0, dels: [], prices: [], text: '' };
        const scan = (el, bag) => {
          for (const node of el.querySelectorAll('*')) {
            const cs = getComputedStyle(node);
            if (cs.display === 'none' || cs.visibility === 'hidden' || node.offsetWidth === 0) continue;
            const add = (v, kind) => { if (v && v !== 'rgba(0, 0, 0, 0)' && v !== 'transparent') { bag[v] = bag[v] || []; if (bag[v].length < 3) bag[v].push(`${kind}:${node.tagName.toLowerCase()}.${String(node.className).split(' ').slice(0, 2).join('.')}`); } };
            add(cs.backgroundColor, 'fondo');
            if (node.childNodes && [...node.childNodes].some((c) => c.nodeType === 3 && c.textContent.trim())) add(cs.color, 'texto');
            if (node.tagName === 'I' || node.tagName === 'svg' || node.closest('.elementor-icon')) add(cs.color, 'icono');
            if (cs.borderTopWidth !== '0px' && cs.borderTopStyle !== 'none') add(cs.borderTopColor, 'borde');
          }
        };
        if (!root) return out;
        scan(root, out.colors);
        for (const t of ['header', 'footer']) if (part(t)) scan(part(t), out.siteColors);
        for (const h of root.querySelectorAll('h1, h2, h3')) out.headingFonts[getComputedStyle(h).fontFamily] = (out.headingFonts[getComputedStyle(h).fontFamily] || 0) + 1;
        for (const p of root.querySelectorAll('p, li, .elementor-button-text, .elementor-tab-content')) { if (p.textContent.trim()) out.bodyFonts[getComputedStyle(p).fontFamily] = (out.bodyFonts[getComputedStyle(p).fontFamily] || 0) + 1; }
        out.buttons = [...root.querySelectorAll('a.elementor-button')].map((a) => ({ href: a.href, text: a.textContent.trim(), top: Math.round(a.getBoundingClientRect().top + window.scrollY) }));
        out.images = [...root.querySelectorAll('img')].filter((i) => i.naturalWidth > 0 && i.offsetWidth > 40).length;
        out.dels = [...root.querySelectorAll('del')].map((d) => d.textContent.trim());
        out.text = root.innerText;
        // Contraste de lo que se ve: cada texto contra el primer fondo opaco de sus ancestros (WCAG AA: 4.5; 3 si es grande
        // —24 px, o 18.66 px en negritas— o un botón).
        const rgb = (v) => { const m = String(v).match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/); return m && (m[4] === undefined || Number(m[4]) >= 0.6) ? [Number(m[1]), Number(m[2]), Number(m[3])] : null; };
        const L = (c) => c.map((x) => x / 255).map((x) => (x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4)).reduce((a, x, i) => a + x * [0.2126, 0.7152, 0.0722][i], 0);
        const ratio = (a, b) => (Math.max(L(a), L(b)) + 0.05) / (Math.min(L(a), L(b)) + 0.05);
        const bgOf = (el) => { for (let n = el; n; n = n.parentElement) { const st = getComputedStyle(n); const c = rgb(st.backgroundColor) || (/gradient/.test(st.backgroundImage) ? rgb((st.backgroundImage.match(/rgba?\([^)]*\)/) || [''])[0]) : null); if (c) return c; } return [255, 255, 255]; };
        out.lowContrast = [];
        for (const node of root.querySelectorAll('*')) {
          const own = [...node.childNodes].some((c) => c.nodeType === 3 && c.textContent.trim());
          if (!own || node.offsetWidth === 0) continue;
          const cs = getComputedStyle(node);
          const fg = rgb(cs.color);
          if (!fg) continue;
          const size = parseFloat(cs.fontSize);
          const large = size >= 24 || (size >= 18.66 && Number(cs.fontWeight) >= 700) || !!node.closest('.elementor-button');
          const r = ratio(fg, bgOf(node));
          if (r + 1e-6 < (large ? 3 : 4.5)) out.lowContrast.push(`${node.textContent.trim().slice(0, 40)} · ${cs.color} sobre ${bgOf(node).join(',')} = ${r.toFixed(2)}`);
        }
        out.footers = document.querySelectorAll('[data-elementor-type="footer"]').length;
        out.kitFooter = /no forma parte de Meta|Todos los derechos reservados/.test(root.innerText);
        // El contador (si hay): el color de sus números y el fondo de su caja.
        out.countdown = [...root.querySelectorAll('.elementor-countdown-item')].map((it) => ({ box: getComputedStyle(it).backgroundColor, digits: getComputedStyle(it.querySelector('.elementor-countdown-digits') || it).color }));
        return out;
      }, landing.id);
      await page.screenshot({ path: `${dir}/landing.png`, fullPage: true });
      await page.screenshot({ path: `${dir}/landing-arriba.png` });
      // La cabecera fija y lo que quede fijo de la landing (la barra de cierre), ya bajando por la página: no se tapan.
      await page.evaluate(() => window.scrollTo(0, 1400));
      await new Promise((r) => setTimeout(r, 900));
      m.overlap = await page.evaluate((pid) => {
        const head = document.querySelector('[data-elementor-type="header"]');
        const root = document.querySelector(`[data-elementor-id="${pid}"]`);
        if (!head || !root) return { checked: false };
        const h = head.querySelector('.elementor-sticky--active') || head.firstElementChild || head;
        const hr = h.getBoundingClientRect();
        const fixed = [...root.querySelectorAll('*')].filter((n) => ['fixed', 'sticky'].includes(getComputedStyle(n).position) && n.offsetHeight > 0).map((n) => n.getBoundingClientRect()).filter((r) => r.top < window.innerHeight && r.bottom > 0);
        const hits = fixed.filter((r) => r.top < hr.bottom - 1 && r.bottom > hr.top + 1 && hr.height > 0);
        return { checked: true, header: [Math.round(hr.top), Math.round(hr.bottom)], headerFixed: !!head.querySelector('.elementor-sticky--active'), fixed: fixed.length, hits: hits.map((r) => [Math.round(r.top), Math.round(r.bottom)]) };
      }, landing.id);
      await page.screenshot({ path: `${dir}/landing-bajando.png` });
      log('capturas: landing.png, landing-arriba.png y landing-bajando.png');
    } finally {
      await browser.close();
    }
    fs.writeFileSync(`${dir}/medicion.json`, JSON.stringify(m, null, 1));

    // H · Cabecera y pie del sitio.
    check('H1', m.root && m.header === ready.header && m.footer === ready.footer, `la landing lleva la cabecera (${m.header}) y el pie (${m.footer}) del sitio`, JSON.stringify({ header: m.header, footer: m.footer, want: [ready.header, ready.footer] }));
    // C · Colores.
    const colors = Object.keys(m.colors).map(hex).filter(Boolean);
    const site = [ready.brand.primary, ready.brand.accent, ready.brand.text, '#F3ECFA', ...Object.keys(m.siteColors).map(hex).filter(Boolean)];
    const bad = colors.filter((c) => near(c, PALETTE, 6));
    const factory = colors.filter((c) => near(c, FACTORY, 6) && !near(c, site, 6));
    const foreign = colors.filter((c) => vivid(c) && !near(c, site));
    // Si la paleta DIGITALÍSIMO ES la del sitio (como digitalisimo.lat), esos colores son del sitio.
    const badSite = bad.filter((c) => !near(c, site, 6));
    check('C1', near(ready.brand.primary, colors, 10) && near(ready.brand.accent, colors, 10), `la landing lleva los dos colores de la marca del sitio, ${ready.brand.primary} y ${ready.brand.accent} (${colors.filter((c) => vivid(c) || c === ready.brand.primary || c === ready.brand.accent).join(', ')})`, JSON.stringify(m.colors));
    check('C2', badSite.length === 0 && factory.length === 0, ready.case === 'digitalisimo' ? 'la paleta DIGITALÍSIMO es la del sitio: ningún color ajeno ni de fábrica del kit' : 'ningún color de la paleta DIGITALÍSIMO ni de fábrica del kit', JSON.stringify({ badSite, factory, where: [...badSite, ...factory].map((c) => Object.entries(m.colors).find(([k]) => hex(k) === c)) }));
    check('W3', (m.lowContrast || []).length === 0, `todo texto de la landing pasa WCAG AA en Chrome (${(m.lowContrast || []).length} fallas)`, (m.lowContrast || []).slice(0, 8).join(' | '));
    check('H2', m.footers === 1 && !m.kitFooter, `un solo pie: el del sitio (${m.footers} pies del sitio; pie propio de la plantilla: ${m.kitFooter ? 'sí' : 'no'})`);
    check('H3', m.overlap && m.overlap.checked && m.overlap.hits.length === 0, `la cabecera fija del sitio no queda tapada por nada fijo de la landing (${JSON.stringify(m.overlap)})`, JSON.stringify(m.overlap));
    check('C3', foreign.length === 0, 'todo color con tono de la landing es del sitio', JSON.stringify(foreign.map((c) => [c, Object.entries(m.colors).find(([k]) => hex(k) === c)?.[1]])));
    // F · Fuentes.
    const hf = Object.keys(m.headingFonts);
    const bf = Object.keys(m.bodyFonts);
    check('F1', hf.length > 0 && hf.every((f) => /Montserrat/i.test(f)), `los títulos en Montserrat (${hf.join(' | ')})`, JSON.stringify(m.headingFonts));
    check('F2', bf.length > 0 && Object.entries(m.bodyFonts).filter(([f]) => /Lato/i.test(f)).reduce((a, [, n]) => a + n, 0) >= 0.8 * Object.values(m.bodyFonts).reduce((a, n) => a + n, 0) && !bf.some((f) => /^\s*"?Roboto/i.test(f)), `el texto en Lato, nunca Roboto (${bf.join(' | ')})`, JSON.stringify(m.bodyFonts));
    const prods = after.products.filter(Boolean);
    if (PRODUCT) {
      // G · La landing de UN producto: el taladro, su precio normal y el 25 % menos; el botón de compra.
      const drill = prods.find((p) => /Taladro/.test(p.name));
      const sale = Math.round(Number(drill.regular) * 0.75 * 100) / 100;
      check('G1', m.text.includes(drill.name) && m.images >= 1, `la landing es del producto (${drill.name}) con su foto`, m.text.slice(0, 600));
      check('G2', m.text.includes(digits(Number(drill.regular))) && m.text.includes(digits(sale)), `su precio normal (${digits(Number(drill.regular))}) y el de oferta 25 % menos (${digits(sale)})`, m.text.slice(0, 1500));
      const buy = m.buttons.filter((b) => /add-to-cart=/.test(b.href) || b.href.replace(/\/$/, '') === String(drill.url).replace(/\/$/, ''));
      check('B1', buy.length > 0 && !m.buttons.some((b) => /\/(carrito|cart)\/?$/.test(new URL(b.href).pathname) && !/add-to-cart=/.test(b.href)), `un botón agrega el producto o lleva a su ficha, ninguno al carrito vacío (${buy.map((b) => b.href).join(' ')})`, JSON.stringify(m.buttons.slice(0, 8)));
    } else {
    // G · Productos reales con su precio tachado y el 40 % menos.
    const inPage = prods.filter((p) => m.text.includes(p.name));
    const priced = inPage.filter((p) => { const r = Number(p.regular); const s = Math.round(r * 0.6 * 100) / 100; return m.dels.some((d) => d.includes(money(r))) && m.text.includes(money(s)); });
    check('G1', inPage.length >= 8 && m.images >= 8, `cuadrícula de productos reales con foto (${inPage.length} de ${prods.length} productos, ${m.images} fotos)`, m.text.slice(0, 1200));
    check('G2', priced.length >= 8 && priced.length === inPage.length, `cada producto con su precio normal tachado y el 40 % menos (${priced.length} de ${inPage.length})`, JSON.stringify({ dels: m.dels.slice(0, 12), missing: inPage.filter((p) => !priced.includes(p)).map((p) => `${p.name} ${p.regular}`) }));
    check('G3', /40\s?%/.test(m.text) && /MXN|\$/.test(m.text), 'dice el 40 % y los precios en pesos', m.text.slice(0, 400));
    // B · El botón principal a la tienda.
    const ordered = [...m.buttons].sort((a, b) => a.top - b.top);
    const main = ordered[0];
    const shop = String(ready.shop).replace(/\/$/, '');
    check('B1', main && main.href.replace(/\/$/, '').startsWith(shop) && !m.buttons.some((b) => /\/(carrito|cart)\/?(\?|$)/.test(new URL(b.href).pathname + (new URL(b.href).search ? '?' : '')) && !/add-to-cart=/.test(b.href)), `el botón principal lleva a la tienda (${main ? main.href : 'sin botón'}) y ninguno al carrito`, JSON.stringify(m.buttons.slice(0, 6)));
    }
    const lum = (h) => rgbOf(h).map((c) => c / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)).reduce((a, c, i) => a + c * [0.2126, 0.7152, 0.0722][i], 0);
    const contrast = (a, b) => (Math.max(lum(a), lum(b)) + 0.05) / (Math.min(lum(a), lum(b)) + 0.05);
    const unreadable = (m.countdown || []).filter((c) => hex(c.box) && hex(c.digits) && contrast(hex(c.box), hex(c.digits)) < 3);
    check('K1', unreadable.length === 0, `el contador, si lo hay, se lee (${(m.countdown || []).length} cajas)`, JSON.stringify(unreadable));
    check('X2', !COURSE.test(m.text), 'tampoco en lo que se ve hay textos de cursos', (m.text.match(COURSE) || [''])[0]);
    // V · Lo que afirma.
    const claims = /(usa|lleva|con) (los |tus )?colores|tus colores|colores de tu (sitio|marca|kit)/i.test(reply);
    check('V1', !claims || (badSite.length === 0 && near(ready.brand.primary, colors, 10)), `si dice que usa los colores del sitio, es verdad (${claims ? 'lo dice' : 'no lo dice'})`, reply);

    fs.writeFileSync(`${dir}/evaluacion.json`, JSON.stringify({ results, failures, seconds: t.seconds, tools: tools.map((x) => `${x.kind}:${x.ability}:${x.status}`), landing: { id: landing.id, title: landing.title }, visible: t.visible, colors, fonts: { headings: m.headingFonts, body: m.bodyFonts }, buttons: m.buttons }, null, 1));
  } catch (e) {
    check('X', false, 'el ensayo terminó sin errores', e.stack || e.message);
  } finally {
    try { await probe('wipe'); } catch { /* el servidor ya no responde */ }
    fs.writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, results }, null, 1));
    log(failures ? `\n${failures} falla(s)` : `\nTodo bien (${results.length} comprobaciones).`);
    process.exit(failures ? 1 : 0);
  }
})();
