// Ensayo de punta a punta del kit de ventas de DIGITALÍSIMO Elements contra el Playground que levantó sales-kit.sh:
// JSON-RPC real contra /wp-json/elementor/mcp (initialize, notifications/initialized, tools/list y tools/call) con
// contraseñas de aplicación, la sonda (?digi_sk=…) para leer el estado del sitio sin pasar por el MCP y Chrome real
// para ver cada página creada (1 280 y 390 px) y el aviso al importar un ZIP de plantillas.
// Escribe cada resultado en <dir>/result.txt («ok · …» o «FALLA · …») y <dir>/result.json; sale 1 si algo falla.
// Ningún secreto (contraseñas de aplicación) se escribe en los resultados.
// Uso: node run.mjs <puerto> <dir> <zip del kit>   (PUPPETEER_PATH: carpeta node_modules con puppeteer-core)
import http from 'node:http';
import { appendFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join } from 'node:path';

const [port, dir, zip] = process.argv.slice(2);
const base = `http://127.0.0.1:${port}`;
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const secrets = () => JSON.parse(readFileSync(`${dir}/probe/secrets.json`, 'utf8'));
const results = [];
let failures = 0;
let section = '';
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  results.push({ section, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 800) });
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 800)}` : ''}`); }
  return !!ok;
};
const start = (name) => { section = name; log(`== ${name}`); };

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { ...headers };
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
const probe = async (step, value = '') => {
  const res = await request('GET', `/?digi_sk=${step}&token=${token}&value=${encodeURIComponent(value)}`);
  if (res.status !== 200) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
};

const SALES = ['list-templates', 'create-page', 'guide'].map((t) => `digitalisimo-sales-${t}`);
class Client {
  constructor(login) { this.login = login; this.id = 0; this.session = ''; }
  auth() { return `Basic ${Buffer.from(`${this.login}:${secrets()[`password:${this.login}`]}`).toString('base64')}`; }
  async post(payload) {
    const headers = { Authorization: this.auth(), Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' };
    if (this.session) headers['Mcp-Session-Id'] = this.session;
    return request('POST', `/?rest_route=${encodeURIComponent('/elementor/mcp')}`, { headers, body: payload });
  }
  async rpc(method, params = {}) {
    const res = await this.post({ jsonrpc: '2.0', id: ++this.id, method, params });
    if (!res.json) throw new Error(`${method}: respuesta no JSON (HTTP ${res.status}) ${res.text.slice(0, 300)}`);
    return res;
  }
  async init() {
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-sales-kit-e2e', version: '1.0.0' } });
    this.session = res.headers['mcp-session-id'] || '';
    await this.post({ jsonrpc: '2.0', method: 'notifications/initialized' });
    return res;
  }
  async tools() { return (await this.rpc('tools/list', {})).json?.result?.tools || []; }
  async call(name, args) {
    const res = await this.rpc('tools/call', { name: `digitalisimo-sales-${name}`, arguments: args });
    const result = res.json?.result;
    const error = res.json?.error;
    const text = (result?.content || []).map((c) => c.text || '').join('\n');
    let data = result?.structuredContent;
    if (!data && text) { try { data = JSON.parse(text); } catch { data = null; } }
    if (data && data.result !== undefined && data.ok === undefined) data = data.result;
    return { isError: !!(result?.isError || error), text: error ? JSON.stringify(error) : text, data };
  }
}

// Lo que dice el manifiesto del kit que se prueba (el del repositorio; con el código anterior el ensayo falla antes).
const MANIFEST = JSON.parse(readFileSync(new URL('../../../digitalisimo-elements/modules/digitalisimo-sales/templates/manifest.json', import.meta.url), 'utf8'));
const SITIO_EXTERNO = /^https:\/\/(fonts\.(googleapis|gstatic)\.com\/|secure\.gravatar\.com\/avatar\/|s\.w\.org\/images\/core\/emoji\/)/;
const sameCounts = (a, b) => JSON.stringify(Object.entries(a || {}).sort()) === JSON.stringify(Object.entries(b || {}).sort());
const TEMPLATES = ['venta-completa', 'venta-corta', 'captacion', 'gracias-registro', 'carrito', 'oferta-unica', 'gracias-compra', 'prueba-social'];
const pages = []; // { id, template, variant, preview, widgets }

async function main() {
  start('S0 Servidor MCP y herramientas');
  const ping = await probe('ping');
  check(ping.ready && ping.mcp && ping.sales, `sitio listo (WordPress ${ping.wp}, PHP ${ping.php}, núcleo ${ping.core}), MCP del sitio activo y el módulo del kit cargado`, JSON.stringify(ping));
  check(ping.container === true, 'el experimento de contenedores viene activo en un sitio nuevo (lo normal en Elements 4.3)', JSON.stringify(ping));
  const admin = new Client('admin');
  const init = await admin.init();
  check(init.status === 200 && admin.session, 'initialize con contraseña de aplicación', init.text.slice(0, 300));
  const tools = await admin.tools();
  const ours = tools.filter((t) => t.name.startsWith('digitalisimo-sales-'));
  check(SALES.every((n) => ours.some((t) => t.name === n)) && ours.length === 3, 'las tres herramientas del kit en tools/list', ours.map((t) => t.name).join(','));
  for (const t of ours) {
    const schema = t.inputSchema || {};
    check(schema.additionalProperties === false && !JSON.stringify(schema).includes('"post_id"'), `${t.name}: entrada cerrada, sin post_id`, JSON.stringify(schema).slice(0, 300));
    const ro = t.name !== 'digitalisimo-sales-create-page';
    check(t.annotations?.readOnlyHint === ro && t.annotations?.destructiveHint === false, `${t.name}: anotaciones (sólo lectura ${ro}, no destructiva)`, JSON.stringify(t.annotations));
    check(!/\bElementor\b/.test(`${t.title} ${t.description}`), `${t.name}: sin el nombre del constructor original`);
  }
  const cp = ours.find((t) => t.name === 'digitalisimo-sales-create-page');
  check(JSON.stringify((cp?.inputSchema?.required || []).sort()) === '["template","title"]' && Object.keys(cp?.inputSchema?.properties || {}).join(',') === 'template,title,replacements,omit_blocks,whatsapp,close_date', 'create-page: plantilla, título, reemplazos, bloques, WhatsApp y fecha de cierre', JSON.stringify(cp?.inputSchema?.properties && Object.keys(cp.inputSchema.properties)));

  start('S1 list-templates y guide');
  let r = await admin.call('list-templates', {});
  check(!r.isError && r.data?.ok && r.data.templates.length === 8 && r.data.cases.length >= 10 && r.data.variant === 'containers', 'list-templates: ocho plantillas, la tabla por caso y la variante de este sitio (contenedores)', r.text.slice(0, 400));
  check(JSON.stringify(r.data?.templates?.map((t) => t.id)) === JSON.stringify(TEMPLATES), 'list-templates: en el orden del kit');
  check(r.data?.templates?.[0]?.blocks?.length === 26 && r.data.templates[0].blocks.at(-1).key === 'pie' && r.data.templates[0].blocks.at(-1).required === true, 'list-templates: los 26 bloques de la venta completa, en orden, y el pie obligatorio');
  r = await admin.call('list-templates', { template: 'carrito' });
  const ph = r.data?.templates?.[0]?.placeholders || [];
  check(!r.isError && ph.some((p) => p.placeholder === '[precio]' && p.kind === 'precio') && ph.some((p) => p.placeholder === '[hora de cierre]' && p.kind === 'cierre') && ph.some((p) => p.kind === 'dato_real'), 'list-templates con template: sus placeholders con su tipo', r.text.slice(0, 400));
  check(JSON.stringify(r.data?.templates?.[0]?.internal_links?.map((l) => l.path)) === JSON.stringify(['/aviso-de-privacidad/', '/pago/', '/politica-de-reembolso/', '/terminos-y-condiciones/']), 'list-templates: los enlaces internos del carrito', JSON.stringify(r.data?.templates?.[0]?.internal_links));
  r = await admin.call('guide', { section: 'orden' });
  check(!r.isError && /^## El orden de una página de venta/.test(r.data?.content || '') && /no se cambia el\s+orden/.test(r.data.content) && (r.data.content.match(/^\| [0-9]+ \|/gm) || []).length === 26, 'guide: el orden en 26 bloques (se quita un bloque, no se cambia el orden)', r.text.slice(0, 300));
  r = await admin.call('guide', {});
  check(!r.isError && r.data.content.length > 5000 && !/Daniel|Butanda/.test(r.data.content), 'guide entera, sin la persona de la versión propia');

  start('S2 create-page con contenedores (las ocho)');
  const meta = (await admin.call('list-templates', {})).data.templates;
  const full = Object.fromEntries((await Promise.all(TEMPLATES.map((id) => admin.call('list-templates', { template: id })))).map((x) => [x.data.templates[0].id, x.data.templates[0]]));
  const before = await probe('pages');
  for (const variant of ['containers', 'sections']) {
    if (variant === 'sections') {
      start('S3 create-page con secciones (las ocho, sin el experimento de contenedores)');
      await probe('container', 'inactive');
      const p2 = await probe('ping');
      check(p2.container === false, 'experimento de contenedores apagado', JSON.stringify(p2));
    }
    for (const id of TEMPLATES) {
      const m = meta.find((t) => t.id === id);
      const args = { template: id, title: `Kit ${id} (${variant})`, whatsapp: '5218711234567', replacements: { '[producto]': 'Curso de prueba' } };
      if (!full[id].placeholders.some((p) => p.placeholder === '[producto]')) delete args.replacements;
      if (m.has_countdown) args.close_date = '2026-12-20 20:00';
      r = await admin.call('create-page', args);
      const d = r.data || {};
      if (!check(!r.isError && d.ok && d.status === 'draft' && d.variant === variant && d.id > 0, `${id}: create-page crea un borrador con ${variant === 'containers' ? 'contenedores' : 'secciones'}`, r.text.slice(0, 500))) continue;
      const pg = await probe('page', d.id);
      check(pg.status === 'draft' && pg.type === 'page' && pg.page_template === 'elementor_canvas' && pg.edit_mode === 'builder' && pg.template_type === 'wp-page', `${id}: guardada como borrador del constructor, con lienzo`, JSON.stringify({ ...pg, ids: undefined, widgets: undefined }));
      check(pg.first_type === (variant === 'containers' ? 'container' : 'section'), `${id}: con la variante elegida (${pg.first_type})`);
      const expected = MANIFEST.templates.find((t) => t.id === id).widget_types;
      check(sameCounts(pg.widgets, expected) && Object.values(pg.widgets).reduce((a, b) => a + b, 0) === m.widgets, `${id}: guardada con todos los widgets de la plantilla (${m.widgets})`, `${JSON.stringify(pg.widgets)} vs ${JSON.stringify(expected)}`);
      check(pg.ids.length === new Set(pg.ids).size && pg.ids.every((x) => /^[0-9a-f]{7}$/.test(x)), `${id}: IDs nuevos, sin repetirse`);
      check(!d.whatsapp_pending && Array.isArray(d.pending) && d.pending_count > 0 && /BORRADOR/.test(d.llm_instructions || ''), `${id}: informa lo pendiente (${d.pending_count} apariciones) y que quedó en borrador`);
      check(/[?&]preview=true/.test(d.preview_url || '') && /action=elementor/.test(d.edit_url || ''), `${id}: URL de vista previa y de edición`, `${d.preview_url} ${d.edit_url}`);
      pages.push({ id: d.id, template: id, variant, preview: d.preview_url, widgets: pg.widgets, ids: pg.ids });
    }
  }
  await probe('container', 'active');

  start('S4 Fecha de cierre, urgencia y bloques');
  r = await admin.call('create-page', { template: 'venta-completa', title: 'Kit sin cierre' });
  let pg = r.data?.id ? await probe('page', r.data.id) : {};
  check(!r.isError && r.data.removed_urgency.length === 1 && !pg.widgets?.countdown && pg.top === 31, 'sin close_date: se quita la barra de cierre y no queda ningún contador', r.text.slice(0, 300));
  check(r.data?.pending?.some((p) => p.placeholder === '[día de cierre]') && r.data.whatsapp_pending === true, 'sin close_date ni whatsapp: los textos de cierre y el número de muestra quedan pendientes');
  r = await admin.call('create-page', { template: 'venta-completa', title: 'Kit sin bonus ni oferta', omit_blocks: ['bonus', 'oferta'], close_date: '2026-12-20 20:00' });
  pg = r.data?.id ? await probe('page', r.data.id) : {};
  check(!r.isError && JSON.stringify(r.data.omitted_blocks) === '["bonus","oferta"]' && pg.top === 32 - 7 && r.data.broken_anchors.includes('oferta'), 'omit_blocks quita los bloques enteros (seis secciones de bonus y la oferta) e informa los botones que llevaban a #oferta', r.text.slice(0, 300));
  if (r.data?.id) pages.push({ id: r.data.id, template: 'venta-completa-sin-bonus', variant: 'containers', preview: r.data.preview_url, widgets: pg.widgets, ids: pg.ids });
  r = await admin.call('create-page', { template: 'venta-completa', title: 'X', omit_blocks: ['pie'] });
  check(r.isError && /avisos legales/.test(r.text), 'el pie no se quita', r.text.slice(0, 300));

  start('S5 Candados: nada fuera del borrador ni de los datos');
  const count0 = await probe('pages');
  for (const [args, what, re] of [
    [{ template: 'venta-corta', title: 'X', replacements: { '[producto]': 'Visita https://otro.example/oferta' } }, 'una URL de otro sitio en un reemplazo', /otro sitio/],
    [{ template: 'venta-corta', title: 'X', replacements: { '[producto]': '<a href="//otro.example">aquí</a>' } }, 'un enlace sin esquema a otro sitio', /otro sitio/],
    [{ template: 'venta-completa', title: 'X', replacements: { '[día de cierre]': '15' } }, 'el cierre por un reemplazo', /close_date/],
    [{ template: 'venta-completa', title: 'X', replacements: { '[enlace del video]': '/video/' } }, 'un enlace del kit por un reemplazo', /editor/],
    [{ template: 'venta-corta', title: 'X', replacements: { '[precio]': '49 USD' } }, 'un precio que no es una cifra en pesos', /pesos/],
    [{ template: 'carrito', title: 'X', close_date: '2020-01-01 00:00' }, 'una fecha de cierre pasada', /pasó|fecha|pattern|close_date/],
    [{ template: 'venta-corta', title: 'X', close_date: '2026-12-20 20:00' }, 'close_date en una plantilla sin contador', /no lleva fecha de cierre/],
    [{ template: 'venta-corta', title: 'X', whatsapp: '+52 871 123 4567' }, 'un WhatsApp que no son sólo dígitos', /whatsapp|pattern|dígitos/i],
    [{ template: 'venta-corta', title: 'X', status: 'publish' }, 'pedir otro estado (publish)', /status|additional|desconocid/i],
    [{ template: 'otra', title: 'X' }, 'una plantilla que no existe', /template|enum/i],
  ]) {
    r = await admin.call('create-page', args);
    check(r.isError && re.test(r.text), `rechazado: ${what}`, r.text.slice(0, 300));
  }
  const count1 = await probe('pages');
  check(count1.all === count0.all, 'ningún rechazo creó una página', JSON.stringify({ count0, count1 }));
  check(count1.publish === before.publish, 'ninguna página del kit se publicó', JSON.stringify({ before, count1 }));

  start('S6 Permisos');
  const editora = new Client('editora');
  await editora.init();
  r = await editora.call('create-page', { template: 'venta-corta', title: 'De la editora' });
  check(!r.isError && r.data.status === 'draft', 'una editora (edit_pages) crea un borrador aunque puede publicar', r.text.slice(0, 300));
  if (r.data?.id) { pg = await probe('page', r.data.id); check(pg.status === 'draft', 'y queda en borrador'); }
  for (const login of ['autora', 'editora-sin-paginas']) {
    const c = new Client(login);
    await c.init();
    const ts = await c.tools();
    const n0 = (await probe('pages')).all;
    const a = await c.call('create-page', { template: 'venta-corta', title: `De ${login}` });
    const b = await c.call('list-templates', {});
    const g = await c.call('guide', {});
    const n1 = (await probe('pages')).all;
    check(a.isError && b.isError && g.isError && n0 === n1, `${login} (sin edit_pages) no puede usar el kit ni crear nada`, `${a.text.slice(0, 200)} | tools: ${ts.filter((t) => t.name.startsWith('digitalisimo-sales-')).map((t) => t.name)}`);
  }

  start('S7 Chrome: cada página creada, con su CSS, a 1280 y 390 px');
  const require = createRequire(join(process.env.PUPPETEER_PATH, '/'));
  const puppeteer = require('puppeteer-core');
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  mkdirSync(join(dir, 'capturas'), { recursive: true });
  try {
    const page0 = await browser.newPage();
    await page0.goto(`${base}/?digi_sk=login&token=${token}&value=admin`, { waitUntil: 'load' });
    await page0.close();
    const VISTAS = [
      { nombre: 'escritorio', width: 1280, height: 900, deviceScaleFactor: 0.5 },
      { nombre: 'celular', width: 390, height: 844, deviceScaleFactor: 1, isMobile: true, hasTouch: true },
    ];
    for (const p of pages) {
      for (const v of VISTAS) {
        const page = await browser.newPage();
        await page.setViewport(v);
        const errores = []; const fallidos = []; const externos = []; const fuentes = [];
        page.on('pageerror', (e) => errores.push(String(e.message || e)));
        page.on('console', (m) => { if (m.type() === 'error') errores.push('consola: ' + m.text()); });
        page.on('response', (res) => { if (res.status() >= 400 && res.url().startsWith(base)) fallidos.push(`${res.status()} ${res.url()}`); });
        // Fuera del sitio sólo se tolera lo que pide WordPress o el sitio, no el kit: las fuentes del kit del sitio, el
        // avatar de la barra de administración (la sesión iniciada) y las imágenes de emoji del núcleo de WordPress
        // (los textos del kit llevan ⏰ y 🔒, y Chrome sin fuente de emoji los pide). Nada más: ni videos ni servicios.
        page.on('request', (req) => { const u = req.url(); if (!u.startsWith(base) && !/^(data|blob):/.test(u)) (SITIO_EXTERNO.test(u) ? fuentes : externos).push(u); });
        page.on('requestfailed', (req) => { if (req.url().startsWith(base)) fallidos.push(`falló ${req.url()}`); });
        const url = p.preview.replace(/^https?:\/\/[^/]+/, base);
        const resp = await page.goto(url, { waitUntil: 'networkidle0', timeout: 180000 });
        const alto0 = await page.evaluate(() => document.documentElement.scrollHeight);
        for (let y = 0; y < alto0; y += 700) { await page.evaluate((yy) => window.scrollTo(0, yy), y); await new Promise((res) => setTimeout(res, 40)); }
        await page.evaluate(() => window.scrollTo(0, 0));
        await new Promise((res) => setTimeout(res, 600));
        const m = await page.evaluate((pid) => {
          const widgets = {};
          document.querySelectorAll('.elementor-widget[data-widget_type]').forEach((el) => {
            const w = el.getAttribute('data-widget_type').split('.')[0];
            widgets[w] = (widgets[w] || 0) + 1;
          });
          let css = '';
          for (const sheet of document.styleSheets) {
            try { for (const rule of sheet.cssRules) css += rule.cssText + '\n'; } catch (e) { /* hoja de otro origen */ }
          }
          const enCss = new Set([...css.matchAll(/\.elementor-element-([0-9a-f]{7})\b/g)].map((x) => x[1]));
          const ids = [...document.querySelectorAll(`.elementor-${pid} [data-id]`)].map((el) => el.getAttribute('data-id'));
          const widgetIds = [...document.querySelectorAll(`.elementor-${pid} .elementor-widget[data-id]`)].map((el) => el.getAttribute('data-id'));
          const topIds = [...document.querySelectorAll(`.elementor-${pid} > .elementor-element[data-id], .elementor-${pid} > .elementor-section-wrap > .elementor-element[data-id], .elementor-${pid} > .e-con[data-id], .elementor-${pid} > .elementor-section[data-id]`)].map((el) => el.getAttribute('data-id'));
          const html = document.documentElement.outerHTML;
          return {
            alto: document.documentElement.scrollHeight,
            desborde: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            php: (html.match(/(Warning|Notice|Deprecated|Fatal error)<\/b>:|PHP (Warning|Notice|Fatal)/g) || []).length,
            widgets,
            postCss: [...document.querySelectorAll('link[rel=stylesheet]')].some((l) => l.href.includes(`/elementor/css/post-${pid}.css`)) || !!document.getElementById(`elementor-post-${pid}-css`) || /elementor-post-\d+/.test([...document.querySelectorAll('style')].map((s) => s.id).join(' ')),
            sinCss: [...new Set([...widgetIds, ...topIds])].filter((id) => !enCss.has(id)),
            ajenos: [...enCss].filter((id) => !ids.includes(id) && css.includes(`.elementor-${pid} .elementor-element.elementor-element-${id}`)),
            nIds: ids.length,
            textoMuestra: /52XXXXXXXXXX/.test(html),
          };
        }, p.id);
        await page.screenshot({ path: join(dir, 'capturas', `${p.template}-${p.variant}-${v.nombre}.png`), fullPage: true, captureBeyondViewport: true }).catch(() => {});
        const tag = `${p.template} (${p.variant}) · ${v.nombre}`;
        check(resp && resp.status() === 200, `${tag}: HTTP 200`, resp && resp.status());
        check(!errores.length, `${tag}: sin errores de JavaScript`, errores.slice(0, 3).join(' | '));
        check(!fallidos.length, `${tag}: sin recursos del sitio con 404/500`, fallidos.slice(0, 3).join(' | '));
        check(!externos.length, `${tag}: ninguna petición del kit fuera del sitio (ni videos de otros servicios)${fuentes.length ? ` · ${fuentes.length} de WordPress o del sitio (fuentes, avatar, emoji)` : ''}`, externos.slice(0, 3).join(' | '));
        check(!m.php, `${tag}: sin avisos de PHP en el HTML`, m.php);
        check(m.desborde <= 2, `${tag}: sin desborde horizontal (${m.desborde} px)`);
        check(sameCounts(m.widgets, p.widgets), `${tag}: widgets pintados = guardados`, `${JSON.stringify(m.widgets)} vs ${JSON.stringify(p.widgets)}`);
        check(m.postCss && !m.sinCss.length && !m.ajenos.length, `${tag}: carga su CSS (cada widget y cada sección o contenedor de primer nivel del HTML tiene su regla; ninguna regla de otra versión)`, JSON.stringify({ postCss: m.postCss, sinCss: m.sinCss.slice(0, 5), ajenos: m.ajenos.slice(0, 5) }));
        await page.close();
      }
    }

    start('S8 Aviso al importar un ZIP de plantillas sin la carga de archivos sin filtrar');
    await probe('unfiltered', '0');
    const lib0 = (await probe('library')).count;
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 900 });
    const importar = async () => {
      await page.goto(`${base}/wp-admin/edit.php?post_type=elementor_library&tabs_group=library`, { waitUntil: 'networkidle0', timeout: 180000 });
      const input = await page.$('#elementor-import-template-form input[type=file]');
      if (!input) return { error: 'sin formulario de importación' };
      await input.uploadFile(zip);
      await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 180000 }), page.$eval('#elementor-import-template-form', (f) => f.submit())]);
      return { url: page.url(), aviso: await page.evaluate(() => document.querySelector('.digitalisimo-import-notice')?.innerText || '') };
    };
    let res = await importar();
    const lib1 = (await probe('library')).count;
    await page.screenshot({ path: join(dir, 'capturas', 'aviso-importar.png') }).catch(() => {});
    check(/digitalisimo-import=opcion/.test(res.url || '') && /Habilitar cargas de archivos sin filtrar/.test(res.aviso) && /Configuración → Avanzado/.test(res.aviso), 'sin la opción: vuelve a la biblioteca con un aviso que dice qué falta y dónde se enciende', JSON.stringify(res));
    check(lib1 === lib0, 'y no importó nada (ni a medias)', `${lib0} → ${lib1}`);
    await probe('unfiltered', '1');
    res = await importar();
    const lib2 = (await probe('library')).count;
    check(!res.aviso && lib2 === lib0 + 8, 'con la opción encendida importa las ocho plantillas, sin aviso', JSON.stringify({ res, lib0, lib2 }));
    await probe('unfiltered', '0');
    await page.close();
  } finally {
    await browser.close();
  }

  start('S9 Cierre');
  const after = await probe('pages');
  check(after.publish === before.publish, 'al terminar, ninguna página del kit está publicada', JSON.stringify({ before, after }));
}

main().catch((e) => { check(false, 'el ensayo terminó con una excepción', e.stack || String(e)); }).finally(() => {
  writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, results }, null, 1));
  log(failures ? `${failures} fallas de ${results.length} comprobaciones` : `${results.length} comprobaciones en verde`);
  process.exit(failures ? 1 : 0);
});
