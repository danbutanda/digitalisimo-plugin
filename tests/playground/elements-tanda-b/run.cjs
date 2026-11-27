// Ensayo de punta a punta de la tanda B de DIGITALÍSIMO Elements (auditoría de digitalisimo.lat, 2026-11-24), contra el
// Playground que levantó elements-tanda-b.sh (WooCommerce 11.2.1 fijado, DIGITALÍSIMO SEO y el tema DIGITALÍSIMO):
//   G  plantillas globales pesadas: guardar la cabecera y el pie programa el cálculo (WP-Cron apagado: nada lo corre
//      solo), visitar el frente no calcula nada, el evento mide el pie (8 H2, más de 1 300 palabras, con lo que incrusta)
//      y la cabecera (1 H1); la opción va sin carga automática; avisos en «Rendimiento», en el editor del pie y de la
//      plantilla incrustada, y en el «Análisis SEO» de una página;
//   H  un solo H1 en la ficha de producto: sin la opción, dos H1 (el del encabezado del sitio y el de WooCommerce); con
//      «Etiqueta en las fichas de producto» = p en el Encabezado de la cabecera, uno solo (el de WooCommerce) y la
//      cabecera pinta un <p>; en otra página la cabecera sigue con su H1;
//   M  marquesina atómica por el MCP real: build-composition crea e-digitalisimo-marquee con su estilo y un párrafo con
//      `animation` (sin @keyframes no mueve nada), y la respuesta dice qué usar; el frente la pinta con su hoja y su script; en Chrome se mueve, el
//      botón la pausa y con «reducir movimiento» no se mueve ni muestra la copia ni el botón; el editor la pinta en el
//      lienzo con su plantilla Twig.
// Escribe <dir>/result.txt y <dir>/result.json; sale 1 si algo falla. Uso: node run.cjs <puerto> <host> <dir>
const http = require('node:http');
const fs = require('node:fs');
const puppeteer = require('puppeteer-core');

const [port, host, dir] = process.argv.slice(2);
const token = fs.readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const secrets = () => JSON.parse(fs.readFileSync(`${dir}/probe/secrets.json`, 'utf8'));
const results = [];
let failures = 0;
const log = (line) => { fs.appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (id, ok, what, detail = '') => {
  results.push({ id, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 1200) });
  if (ok) log(`ok · ${id} · ${what}`);
  else { failures++; log(`FALLA · ${id} · ${what}${detail ? ` · ${String(detail).slice(0, 1200)}` : ''}`); }
  return !!ok;
};

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { Host: host, ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const probe = async (step, extra = '') => {
  const res = await request('GET', `/?digi_tb=${step}&token=${token}${extra}`);
  if (res.status !== 200 || !res.json) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 400)}`);
  return res.json;
};
const pathOf = (url) => { const u = new URL(url); return u.pathname + u.search; };
const page = async (url) => (await request('GET', pathOf(url))).text;
const auth = () => `Basic ${Buffer.from(`admin:${secrets()['password:admin']}`).toString('base64')}`;
const count = (text, re) => (text.match(re) || []).length;

class Client {
  constructor() { this.id = 0; this.session = ''; this.names = {}; }
  post(payload) {
    const headers = { Authorization: auth(), Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' };
    if (this.session) headers['Mcp-Session-Id'] = this.session;
    return request('POST', `/?rest_route=${encodeURIComponent('/elementor/mcp')}`, { headers, body: payload });
  }
  async rpc(method, params = {}) {
    const res = await this.post({ jsonrpc: '2.0', id: ++this.id, method, params });
    if (!res.json) throw new Error(`${method}: respuesta no JSON (HTTP ${res.status}) ${res.text.slice(0, 300)}`);
    return res;
  }
  async init() {
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-tanda-b-e2e', version: '1.0.0' } });
    this.session = res.headers['mcp-session-id'] || '';
    await this.post({ jsonrpc: '2.0', method: 'notifications/initialized' });
  }
  async tools() {
    const res = await this.rpc('tools/list', {});
    const tools = res.json?.result?.tools || [];
    for (const t of tools) this.names[t.name.replace(/^elementor[-/]/, '')] = t.name;
    return tools;
  }
  async call(slug, args) {
    const res = await this.rpc('tools/call', { name: this.names[slug] || `elementor-${slug}`, arguments: args });
    const result = res.json?.result;
    const error = res.json?.error;
    const text = (result?.content || []).map((c) => c.text || '').join('\n');
    let data = result?.structuredContent;
    if (!data && text) { try { data = JSON.parse(text); } catch { data = null; } }
    return { isError: !!(result?.isError || error), text: error ? JSON.stringify(error) : text, data: data || {} };
  }
}

async function main() {
  const ping = await probe('ping');
  log(`== WordPress ${ping.wp} · PHP ${ping.php} · WooCommerce ${ping.wc} · Elements ${ping.elements}`);
  await probe('users');
  const seed = await probe('seed');
  const ids = seed.ids;
  let state = await probe('state');
  const urls = state.urls;

  log('== G plantillas globales pesadas');
  check('G1', seed.scheduled === true && state.scheduled === true, 'guardar la cabecera y el pie programa el cálculo en WP-Cron', JSON.stringify(seed));
  check('G2', !state.data, 'nada se calcula al guardar ni en la petición', JSON.stringify(state.data).slice(0, 300));
  const front1 = await page(urls.product);
  await page(urls.home);
  state = await probe('state');
  check('G3', !state.data && state.scheduled === true, 'visitar el frente no calcula nada', JSON.stringify(state.data).slice(0, 300));
  const cron = await probe('cron');
  const footer = cron.data?.templates?.[ids.footer] || {};
  const header = cron.data?.templates?.[ids.header] || {};
  check('G4', cron.was_scheduled === true && footer.h2 === 8 && footer.words >= 1300 && (footer.reasons || []).length === 2, 'el evento mide el pie: 8 H2 (uno de la plantilla incrustada) y más de 1 300 palabras', JSON.stringify(footer).slice(0, 500));
  check('G5', (footer.embeds || []).includes(ids.embedded), 'el pie anota la plantilla que incrusta', JSON.stringify(footer.embeds));
  check('G6', header.h1 === 1 && JSON.stringify(header.reasons) === JSON.stringify(['1 H1']), 'la cabecera con el título de la página en H1 también avisa', JSON.stringify(header).slice(0, 300));
  state = await probe('state');
  check('G7', ['no', 'off', 'auto-off'].includes(String(state.autoload)), 'la opción va sin carga automática', state.autoload);

  log('== H un solo H1 en la ficha de producto');
  check('H1', count(front1, /<h1\b/gi) === 2 && /<h1[^>]*class="product_title entry-title"/.test(front1) && /<h1 class="elementor-heading-title[^"]*">Web Hosting Starter<\/h1>/.test(front1), 'sin la opción: dos H1 (el del encabezado del sitio y el de WooCommerce), como en digitalisimo.lat', (front1.match(/<h1[^>]*>[^<]*/gi) || []).join(' | '));
  await probe('optin', '&tag=p');
  state = await probe('state');
  check('H2', state.scheduled === true, 'guardar la cabecera vuelve a programar el cálculo');
  const product = await page(urls.product);
  check('H3', count(product, /<h1\b/gi) === 1 && /<h1[^>]*class="product_title entry-title"/.test(product), 'con «Etiqueta en las fichas de producto» = p: un solo H1, el de WooCommerce', (product.match(/<h1[^>]*>[^<]*/gi) || []).join(' | '));
  check('H4', /<p class="elementor-heading-title[^"]*">Web Hosting Starter<\/p>/.test(product), 'la cabecera pinta el título del producto en un <p>', (product.match(/elementor-heading-title[^>]*>[^<]*/gi) || []).join(' | '));
  const home = await page(urls.home);
  check('H5', /<h1 class="elementor-heading-title[^"]*">Inicio del ensayo<\/h1>/.test(home), 'en otra página la cabecera sigue con su H1', (home.match(/<h1[^>]*>[^<]*/gi) || []).join(' | '));

  log('== M marquesina atómica por el MCP');
  const mcp = new Client();
  await mcp.init();
  await mcp.tools();
  const xml = '<e-flexbox configuration-id="Caja"><e-digitalisimo-marquee configuration-id="Marquesina"></e-digitalisimo-marquee><e-paragraph configuration-id="Texto"></e-paragraph></e-flexbox>';
  const r = await mcp.call('build-composition', {
    post_id: ids.mcp,
    mode: 'replace_children',
    xml_structure: xml,
    element_config: {
      Marquesina: { items: 'PEÑOLES\nTEC DE MONTERREY\nY MUCHOS MÁS', label: 'Han confiado en nosotros', separator: '|', accent: '#00FF99', 'accent-last': true, duration: 42, 'fade-edges': true },
      Texto: { paragraph: 'Texto con movimiento pedido por CSS.' },
    },
    style: {
      Marquesina: 'font-size: 48px; color: #FFFFFF; background-color: #05070A; padding: 12px 0;',
      Texto: 'color: #222222; animation: bf-marquesina 42s linear infinite;',
    },
  });
  const warnings = JSON.stringify(r.data?.warnings || r.data?.hints || r.text);
  check('M1', !r.isError, 'build-composition acepta e-digitalisimo-marquee', r.text.slice(0, 600));
  check('M2', /e-digitalisimo-marquee/.test(warnings) && /prefers-reduced-motion/.test(warnings), 'la respuesta dice que use la marquesina en lugar de animation', warnings.slice(0, 800));
  const published = await probe('publish', `&id=${ids.mcp}`);
  check('M3', /e-digitalisimo-marquee/.test(published.data), 'el documento guarda el widget', published.data.slice(0, 300));
  const front = await page(published.url);
  check('M4', /data-digi-marquee/.test(front) && count(front, /<span class="digi-marquee__text">/g) === 6 && /aria-hidden="true" inert/.test(front) && /digi-marquee__toggle/.test(front), 'el frente pinta la marquesina: dos listas de tres, la copia inerte y el botón', (front.match(/<div class="[^"]*digi-marquee[^>]*>/) || [''])[0]);
  check('M5', /marquee\.css/.test(front) && /marquee\.js/.test(front), 'con su hoja y su script', '');
  check('M6', /--digi-marquee-accent:#00FF99/.test(front) && /digi-marquee--fade/.test(front) && /digi-marquee--accent-last/.test(front) && count(front, /digi-marquee__separator/g) === 6, 'acento, desvanecido, último con acento y separadores', '');
  check('M7', (warnings.match(/e-digitalisimo-marquee/g) || []).length === 1, 'la pista sale una sola vez', warnings.slice(0, 400));

  log('== Chrome');
  const profile = fs.mkdtempSync('/tmp/digi-tb-');
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', userDataDir: profile, env: { ...process.env, TMPDIR: '/tmp' }, args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  try {
    const tab = await browser.newPage();
    await tab.setViewport({ width: 1280, height: 900 });
    const target = `http://${host}${pathOf(published.url)}`;
    await tab.goto(target, { waitUntil: 'networkidle0', timeout: 180000 });
    const moving = await tab.evaluate(() => {
      const root = document.querySelector('[data-digi-marquee]');
      const track = root && root.querySelector('.digi-marquee__track');
      const cs = track ? getComputedStyle(track) : null;
      return root ? { name: cs.animationName, state: cs.animationPlayState, duration: cs.animationDuration, font: getComputedStyle(root).fontSize, mask: getComputedStyle(root.querySelector('.digi-marquee__viewport')).maskImage || getComputedStyle(root.querySelector('.digi-marquee__viewport')).webkitMaskImage, last: getComputedStyle(root.querySelector('.digi-marquee__item:last-child .digi-marquee__text')).color } : null;
    });
    check('M8', moving && moving.name === 'digi-marquee' && moving.state === 'running' && moving.duration === '42s', 'en Chrome se mueve con su duración', JSON.stringify(moving));
    check('M9', moving && moving.font === '48px' && /gradient/.test(String(moving.mask)) && moving.last === 'rgb(0, 255, 153)', 'el estilo del MCP llega a la raíz, con desvanecido y acento en el último', JSON.stringify(moving));
    await tab.click('[data-digi-marquee-toggle]');
    const paused = await tab.evaluate(() => {
      const root = document.querySelector('[data-digi-marquee]');
      const button = root.querySelector('[data-digi-marquee-toggle]');
      return { cls: root.classList.contains('is-paused'), pressed: button.getAttribute('aria-pressed'), text: button.textContent.trim(), state: getComputedStyle(root.querySelector('.digi-marquee__track')).animationPlayState };
    });
    check('M10', paused.cls && paused.pressed === 'true' && paused.text === 'Reanudar' && paused.state === 'paused', 'el botón la pausa (WCAG 2.2.2)', JSON.stringify(paused));
    await tab.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
    await tab.goto(target, { waitUntil: 'networkidle0', timeout: 180000 });
    const still = await tab.evaluate(() => {
      const root = document.querySelector('[data-digi-marquee]');
      const lists = root.querySelectorAll('.digi-marquee__list');
      return { name: getComputedStyle(root.querySelector('.digi-marquee__track')).animationName, copy: getComputedStyle(lists[1]).display, button: getComputedStyle(root.querySelector('.digi-marquee__toggle')).display, mask: getComputedStyle(root.querySelector('.digi-marquee__viewport')).maskImage };
    });
    check('M11', still.name === 'none' && still.copy === 'none' && still.button === 'none' && (!still.mask || still.mask === 'none'), 'con «reducir movimiento» no se mueve, sin copia, sin botón y sin desvanecido', JSON.stringify(still));

    await tab.emulateMediaFeatures([]);
    await tab.goto(`http://${host}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 180000 });
    await tab.type('#user_login', 'admin');
    await tab.type('#user_pass', 'password');
    await Promise.all([tab.waitForNavigation({ waitUntil: 'networkidle0', timeout: 180000 }), tab.click('#wp-submit')]);
    await tab.goto(`http://${host}/wp-admin/admin.php?page=digitalisimo-elements-front`, { waitUntil: 'networkidle0', timeout: 180000 });
    const screen = await tab.evaluate(() => document.body.innerText);
    check('G8', /Plantillas que se repiten en cada página/.test(screen) && /Pesada: 8 H2/.test(screen) && /Pesada: 1 H1/.test(screen) && /Analizar ahora/.test(screen), '«Rendimiento» muestra la tabla con las dos pesadas y «Analizar ahora»', screen.slice(screen.indexOf('Plantillas que'), screen.indexOf('Plantillas que') + 600));
    await tab.goto(`http://${host}/wp-admin/post.php?post=${ids.home}&action=edit`, { waitUntil: 'networkidle0', timeout: 180000 });
    const edit = await tab.content();
    check('G9', /Plantilla que se repite en cada página/.test(edit) && /se aplica a todo el sitio/.test(edit), 'el «Análisis SEO» de una página trae la nota', '');
    for (const [id, what] of [[ids.footer, 'G10'], [ids.embedded, 'G11']]) {
      await tab.goto(`http://${host}/wp-admin/post.php?post=${id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 240000 });
      let shown = null;
      try {
        await tab.waitForFunction(() => window.digitalisimoGlobalWeight && window.digitalisimoGlobalWeight.shown === 1, { timeout: 180000 });
        shown = await tab.evaluate(() => ({ messages: window.digitalisimoGlobalWeight.messages, text: document.body.innerText.includes('se aplica a todo el sitio') }));
      } catch (e) {
        shown = { error: String(e.message || e), config: await tab.evaluate(() => window.digitalisimoGlobalWeight || null) };
      }
      check(what, shown && shown.text === true && (shown.messages || []).length === 1, what === 'G10' ? 'el editor del pie muestra el aviso fijo' : 'el editor de la plantilla incrustada avisa de la global que la incluye', JSON.stringify(shown).slice(0, 500));
    }
    // M12: el editor v4 pinta la marquesina con su plantilla (Twig en el navegador) dentro del lienzo.
    await tab.goto(`http://${host}/wp-admin/post.php?post=${ids.mcp}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 240000 });
    let canvas = null;
    try {
      await tab.waitForFunction(() => {
        const frame = document.querySelector('#elementor-preview-iframe');
        const doc = frame && frame.contentDocument;
        return !!(doc && doc.querySelector('[data-digi-marquee] .digi-marquee__text'));
      }, { timeout: 240000 });
      canvas = await tab.evaluate(() => {
        const doc = document.querySelector('#elementor-preview-iframe').contentDocument;
        const root = doc.querySelector('[data-digi-marquee]');
        return { items: root.querySelectorAll('.digi-marquee__text').length, separators: root.querySelectorAll('.digi-marquee__separator').length, fade: root.classList.contains('digi-marquee--fade'), style: root.getAttribute('style') };
      });
    } catch (e) {
      canvas = { error: String(e.message || e) };
    }
    check('M12', canvas && canvas.items === 6 && canvas.separators === 6 && canvas.fade && /--digi-marquee-duration:42s/.test(canvas.style || ''), 'el editor pinta la marquesina atómica en el lienzo con su plantilla', JSON.stringify(canvas));
  } finally {
    await browser.close();
    fs.rmSync(profile, { recursive: true, force: true });
  }
}

main()
  .catch((e) => { failures++; log(`FALLA · ensayo · ${e.stack || e}`); })
  .finally(() => {
    fs.writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, failed: results.filter((r) => !r.ok).map((r) => r.id), results }, null, 1));
    log(`== ${results.length} comprobaciones, ${failures} fallas`);
    process.exit(failures ? 1 : 0);
  });
