// Ensayo de punta a punta del kit de ventas de DIGITALÍSIMO Elements contra el Playground que levantó sales-kit.sh:
// JSON-RPC real contra /wp-json/elementor/mcp (initialize, notifications/initialized, tools/list y tools/call) con
// contraseñas de aplicación, la sonda (?digi_sk=…) para leer el estado del sitio sin pasar por el MCP y Chrome real
// para ver cada página creada (1 280 y 390 px; también las de una editora, con el color de sus títulos) y el aviso al
// importar un ZIP de plantillas.
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
    // Este ensayo compara cada página con su plantilla tal cual: sin terminarla (finish: false). La página terminada y la
    // landing de producto las prueba tests/playground/sales-product.sh.
    if (name === 'create-page' && args && !('finish' in args) && !('product_id' in args)) args = { ...args, finish: false };
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
const pages = []; // { id, template, variant, preview, widgets, ids, spans }
// Los resaltes de color en los títulos de cada plantilla (`<span>…</span>`; su color va en el CSS propio del widget).
const RESALTES = { 'venta-completa': 3, 'venta-corta': 2, captacion: 1, 'gracias-registro': 0, carrito: 1, 'oferta-unica': 1, 'gracias-compra': 0, 'prueba-social': 0 };
// Las plantillas con portada (v4: cabe en la primera pantalla, sin video) y con iconos de redes (decisión 2).
const PORTADAS = ['venta-completa', 'venta-corta', 'captacion'];
// Las plantillas con la barra fija de cierre (sólo con close_date): la 01 y, desde la revisión B3, la 02.
const BARRAS = ['venta-completa', 'venta-corta'];
const REDES = ['gracias-registro', 'gracias-compra'];
// El color de cada resalte: el neón de la promesa y el jade del precio.
const COLORES_RESALTE = ['rgb(0, 255, 153)', 'rgb(0, 168, 107)'];
// La fecha de cierre del ensayo: a 30 días de hoy, a las 20:00 (hora del sitio). Nunca una fecha fija: caducaría y
// create-page la rechazaría por pasada.
const CIERRE = `${new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10)} 20:00`;

async function main() {
  start('S0 Servidor MCP y herramientas');
  const before = await probe('pages');
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
  check(!(cp?.inputSchema?.required || []).length && Object.keys(cp?.inputSchema?.properties || {}).join(',') === 'template,title,replacements,omit_blocks,whatsapp,close_date,product_id,offer,finish', 'create-page: plantilla, título, reemplazos, bloques, WhatsApp, fecha de cierre, producto, oferta y terminar (template y title los exige la ejecución)', JSON.stringify(cp?.inputSchema?.properties && Object.keys(cp.inputSchema.properties)));

  // Con el código anterior (sin el kit) S1–S6 fallan con una excepción; Chrome y el aviso de importación se prueban igual.
  let r;
  try {
  if (process.env.SOLO === 'importar') throw new Error('SOLO=importar: sólo S0 y el aviso de importación');
  start('S1 list-templates y guide');
  r = await admin.call('list-templates', {});
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

  start('S2 create-page con contenedores (las ocho, como administrador y como editora)');
  const meta = (await admin.call('list-templates', {})).data.templates;
  const full = Object.fromEntries((await Promise.all(TEMPLATES.map((id) => admin.call('list-templates', { template: id })))).map((x) => [x.data.templates[0].id, x.data.templates[0]]));
  // La editora (edit_pages, sin manage_options: el núcleo le quita los atributos al título) crea también las ocho en las
  // dos variantes; Chrome las pinta todas.
  const editora = new Client('editora');
  await editora.init();
  const avisosEd0 = await probe('notices', '0,0');
  for (const variant of ['containers', 'sections']) {
    if (variant === 'sections') {
      start('S3 create-page con secciones (las ocho, como administrador y como editora, sin el experimento de contenedores)');
      await probe('container', 'inactive');
      const p2 = await probe('ping');
      check(p2.container === false, 'experimento de contenedores apagado', JSON.stringify(p2));
    }
    for (const [cliente, quien] of [[admin, 'admin'], [editora, 'editora']]) for (const id of TEMPLATES) {
      const m = meta.find((t) => t.id === id);
      const args = { template: id, title: `Kit ${id} (${variant}, ${quien})`, whatsapp: '5218711234567', replacements: { '[producto]': 'Curso de prueba' } };
      if (!full[id].placeholders.some((p) => p.placeholder === '[producto]')) delete args.replacements;
      if (m.close_date) args.close_date = CIERRE;
      r = await cliente.call('create-page', args);
      const d = r.data || {};
      if (!check(!r.isError && d.ok && d.status === 'draft' && d.variant === variant && d.id > 0, `${id} (${quien}): create-page crea un borrador con ${variant === 'containers' ? 'contenedores' : 'secciones'}`, r.text.slice(0, 500))) continue;
      const pg = await probe('page', d.id);
      check(pg.status === 'draft' && pg.type === 'page' && pg.page_template === 'elementor_canvas' && pg.edit_mode === 'builder' && pg.template_type === 'wp-page', `${id}: guardada como borrador del constructor, con lienzo`, JSON.stringify({ ...pg, ids: undefined, widgets: undefined }));
      check(pg.first_type === (variant === 'containers' ? 'container' : 'section'), `${id}: con la variante elegida (${pg.first_type})`);
      const expected = MANIFEST.templates.find((t) => t.id === id).widget_types;
      check(sameCounts(pg.widgets, expected) && Object.values(pg.widgets).reduce((a, b) => a + b, 0) === m.widgets, `${id}: guardada con todos los widgets de la plantilla (${m.widgets})`, `${JSON.stringify(pg.widgets)} vs ${JSON.stringify(expected)}`);
      check(pg.ids.length === new Set(pg.ids).size && pg.ids.every((x) => /^[0-9a-f]{7}$/.test(x)), `${id}: IDs nuevos, sin repetirse`);
      check(!d.whatsapp_pending && Array.isArray(d.pending) && d.pending_count > 0 && /BORRADOR/.test(d.llm_instructions || ''), `${id}: informa lo pendiente (${d.pending_count} apariciones) y que quedó en borrador`);
      check(/[?&]preview=true/.test(d.preview_url || '') && /action=elementor/.test(d.edit_url || ''), `${id}: URL de vista previa y de edición`, `${d.preview_url} ${d.edit_url}`);
      check(pg.spans === RESALTES[id] && pg.span_attrs === 0, `${id} (${quien}): sus ${RESALTES[id]} resaltes de título, sin atributos`, JSON.stringify({ spans: pg.spans, span_attrs: pg.span_attrs }));
      const redes = (d.review || []).filter((x) => /Faltan los enlaces de las redes/.test(x));
      check(REDES.includes(id) ? d.social_pending === 4 && redes.length === 1 : d.social_pending === 0 && !redes.length, `${id}: ${REDES.includes(id) ? 'la revisión dice que faltan los enlaces de las cuatro redes' : 'sin iconos de redes'}`, JSON.stringify({ social: d.social_pending, review: d.review }));
      const conBarra = BARRAS.includes(id) && !!args.close_date;
      const logo = (d.review || []).filter((x) => /logo del evento/.test(x));
      check(conBarra ? d.event_logo === true && logo.length === 1 : d.event_logo === false && !logo.length, `${id}: ${conBarra ? 'la revisión avisa que el logo del evento de la barra es el de muestra' : 'sin barra, nada del logo del evento'}`, JSON.stringify({ event_logo: d.event_logo, review: d.review }));
      pages.push({ id: d.id, template: quien === 'admin' ? id : `${id}-editora`, base: id, quien, variant, preview: d.preview_url, widgets: pg.widgets, ids: pg.ids, spans: pg.spans, bar: conBarra });
    }
  }
  await probe('container', 'active');
  const avisosEd1 = await probe('notices', avisosEd0.at);
  const deMcpEd = [...avisosEd1.kit, ...avisosEd1.elements].filter((l) => /mcp/.test(l.split(' · ')[0]) && !/PHP (8192|16384):/.test(l));
  check(!deMcpEd.length, 'crear las dieciséis páginas como editora no deja avisos de PHP (content-sanitizer, db.php, el kit)', deMcpEd.slice(0, 4).join(' | '));

  start('S4 Fecha de cierre, urgencia y bloques');
  r = await admin.call('create-page', { template: 'venta-completa', title: 'Kit sin cierre' });
  let pg = r.data?.id ? await probe('page', r.data.id) : {};
  check(!r.isError && r.data.removed_urgency.length === 1 && !pg.widgets?.countdown && pg.top === 30, 'sin close_date: se quita la barra de cierre y no queda ningún contador', r.text.slice(0, 300));
  if (r.data?.id) pages.push({ id: r.data.id, template: 'venta-completa-sin-cierre', base: 'venta-completa', variant: 'containers', preview: r.data.preview_url, widgets: pg.widgets, ids: pg.ids, spans: pg.spans, bar: false });
  check(r.data?.pending?.some((p) => p.placeholder === '[día de cierre]') && r.data.whatsapp_pending === true, 'sin close_date ni whatsapp: los textos de cierre y el número de muestra quedan pendientes');
  r = await admin.call('create-page', { template: 'venta-completa', title: 'Kit sin bonus ni comunidad', omit_blocks: ['bonus', 'comunidad'], close_date: CIERRE });
  pg = r.data?.id ? await probe('page', r.data.id) : {};
  check(!r.isError && JSON.stringify(r.data.omitted_blocks) === '["bonus","comunidad"]' && pg.top === 31 - 6 && r.data.broken_anchors.includes('historias'), 'omit_blocks quita los bloques enteros (cinco secciones de bonus y la comunidad) e informa los botones que llevaban a #historias', r.text.slice(0, 300));
  if (r.data?.id) pages.push({ id: r.data.id, template: 'venta-completa-sin-bonus', base: 'venta-completa', variant: 'containers', preview: r.data.preview_url, widgets: pg.widgets, ids: pg.ids, spans: pg.spans, bar: true });
  // Decisión 5 del operador: la promesa, la oferta, la garantía, las preguntas frecuentes, WhatsApp y el pie no se quitan.
  const n0 = (await probe('pages')).all;
  for (const [id, bloques] of [['venta-completa', ['oferta']], ['venta-completa', ['promesa', 'garantia', 'preguntas-frecuentes', 'whatsapp']], ['venta-corta', ['garantia-y-preguntas']], ['carrito', ['formulario', 'dudas-de-pago']], ['gracias-compra', ['soporte']], ['captacion', ['registro']], ['gracias-registro', ['mientras-tanto']]]) {
    r = await admin.call('create-page', { template: id, title: 'X', omit_blocks: bloques, ...(meta.find((t) => t.id === id).close_date ? { close_date: CIERRE } : {}) });
    check(r.isError && bloques.every((b) => r.text.includes(`«${b}»`)) && /no se puede quitar/.test(r.text) && /En esta plantilla no se quitan/.test(r.text), `${id}: no deja quitar ${bloques.join(', ')}, y dice cuáles no se quitan`, r.text.slice(0, 400));
  }
  check((await probe('pages')).all === n0, 'pedir quitar un bloque obligatorio no crea nada');
  r = await admin.call('create-page', { template: 'venta-corta', title: 'Kit venta corta sin testimonios', omit_blocks: ['testimonios'] });
  check(!r.isError && JSON.stringify(r.data.omitted_blocks) === '["testimonios"]', 'las pruebas sociales sí se quitan', r.text.slice(0, 300));
  r = await admin.call('list-templates', { template: 'venta-completa' });
  check(JSON.stringify(r.data?.templates?.[0]?.blocks?.filter((b) => b.required).map((b) => b.key)) === '["promesa","oferta","garantia","preguntas-frecuentes","whatsapp","pie"]' && Object.keys(r.data?.required || {}).length === 7 && r.data.templates[0].blocks.filter((b) => b.price).map((b) => b.key).join(',') === 'cuanto-vale,oferta,caminos,cierre', 'list-templates: los seis bloques obligatorios de la venta completa (de siete tipos) y dónde puede ir un precio', JSON.stringify(r.data?.templates?.[0]?.blocks?.map((b) => [b.key, b.required])));
  r = await admin.call('create-page', { template: 'venta-corta', title: 'Kit venta corta con cierre', close_date: CIERRE });
  pg = r.data?.id ? await probe('page', r.data.id) : {};
  check(!r.isError && r.data.close_date === CIERRE && !r.data.pending.some((p) => /de cierre\]$/.test(p.placeholder)) && pg.status === 'draft' && pg.widgets?.countdown === 1, 'la venta corta llena con close_date sus textos de cierre y su barra fija (un contador)', r.text.slice(0, 300));
  r = await admin.call('list-templates', {});
  check(JSON.stringify(r.data?.templates?.filter((t) => t.close_date).map((t) => t.id)) === '["venta-completa","venta-corta","carrito"]', 'list-templates: admiten close_date la venta completa, la corta y el carrito', JSON.stringify(r.data?.templates?.map((t) => [t.id, t.close_date])));
  r = await admin.call('create-page', { template: 'venta-completa', title: 'X', omit_blocks: ['pie'] });
  check(r.isError && /avisos legales/.test(r.text), 'el pie no se quita', r.text.slice(0, 300));

  start('S5 Candados: nada fuera del borrador ni de los datos');
  const count0 = await probe('pages');
  for (const [args, what, re] of [
    [{ template: 'venta-corta', title: 'X', replacements: { '[producto]': 'Visita https://otro.example/oferta' } }, 'una URL de otro sitio en un reemplazo', /otro sitio/],
    [{ template: 'venta-corta', title: 'X', replacements: { '[producto]': '<a href="//otro.example">aquí</a>' } }, 'un enlace (HTML) a otro sitio', /HTML que no se admite|otro sitio/],
    [{ template: 'venta-corta', title: 'X', replacements: { '[producto]': 'Ve a //otro.example/oferta' } }, 'una dirección sin esquema (//) a otro sitio', /otro sitio/],
    [{ template: 'venta-completa', title: 'X', replacements: { '[día de cierre]': '15' } }, 'el cierre por un reemplazo', /close_date/],
    [{ template: 'venta-completa', title: 'X', replacements: { '[enlace del video]': '/video/' } }, 'un enlace del kit por un reemplazo', /editor/],
    [{ template: 'venta-corta', title: 'X', replacements: { '[precio]': '49 USD' } }, 'un precio que no es una cifra en pesos', /pesos/],
    [{ template: 'carrito', title: 'X', close_date: '2020-01-01 00:00' }, 'una fecha de cierre pasada', /pasó|fecha|pattern|close_date/],
    [{ template: 'captacion', title: 'X', close_date: CIERRE }, 'close_date en una plantilla sin contador ni textos de cierre', /no lleva fecha de cierre/],
    [{ template: 'venta-corta', title: 'X', whatsapp: '+52 871 123 4567' }, 'un WhatsApp que no son sólo dígitos', /whatsapp|pattern|dígitos/i],
    [{ template: 'venta-corta', title: 'X', status: 'publish' }, 'pedir otro estado (publish)', /status|additional|desconocid/i],
    [{ template: 'otra', title: 'X' }, 'una plantilla que no existe', /template|enum/i],
    [{ template: 'venta-corta', title: 'X', replacements: { '[Resultado concreto]': 'Llévatelo hoy por $1,997 MXN' } }, 'un precio en la portada (revisión M2)', /sólo en la oferta/],
    [{ template: 'captacion', title: 'X', replacements: { '[Cómo lograr el resultado]': 'Aprende por 497 pesos' } }, 'un precio en la captación (revisión M2)', /sólo en la oferta/],
  ]) {
    r = await admin.call('create-page', args);
    check(r.isError && re.test(r.text), `rechazado: ${what}`, r.text.slice(0, 300));
  }
  const count1 = await probe('pages');
  check(count1.all === count0.all, 'ningún rechazo creó una página', JSON.stringify({ count0, count1 }));
  check(count1.publish === before.publish, 'ninguna página del kit se publicó', JSON.stringify({ before, count1 }));

  start('S6 Permisos, y lo que guarda una editora');
  const sinHtml = new Client('editora-sin-html');
  await sinHtml.init();
  const avisos0 = await probe('notices', '0,0');
  r = await editora.call('create-page', { template: 'venta-corta', title: 'De la editora' });
  check(!r.isError && r.data.status === 'draft', 'una editora (edit_pages) crea un borrador aunque puede publicar', r.text.slice(0, 300));
  if (r.data?.id) {
    pg = await probe('page', r.data.id);
    check(pg.status === 'draft', 'y queda en borrador');
    pages.push({ id: r.data.id, template: 'venta-corta-editora-sin-datos', base: 'venta-corta', variant: 'containers', preview: r.data.preview_url, widgets: pg.widgets, ids: pg.ids, spans: pg.spans });
  }
  // Las plantillas con un resalte de color en el título, creadas por una editora sin unfiltered_html (además,
  // wp_kses_post() en todo; las de la editora ya están en S2 y S3): S7 las pinta y comprueba el color de cada resalte.
  for (const [c, id] of [[sinHtml, 'venta-corta'], [sinHtml, 'carrito']]) {
    const args = { template: id, title: `${id} de ${c.login}`, whatsapp: '5218711234567', replacements: { '[precio]': '1,997' } };
    if (!full[id].placeholders.some((p) => p.placeholder === '[precio]')) delete args.replacements;
    if (meta.find((t) => t.id === id).close_date) args.close_date = CIERRE;
    r = await c.call('create-page', args);
    pg = r.data?.id ? await probe('page', r.data.id) : {};
    if (check(!r.isError && r.data.status === 'draft' && pg.status === 'draft' && pg.spans === RESALTES[id] && pg.span_attrs === 0, `${id} creada por ${c.login}: borrador con sus ${RESALTES[id]} resaltes de título, sin atributos`, r.text.slice(0, 300) + JSON.stringify({ spans: pg.spans, span_attrs: pg.span_attrs }))) {
      pages.push({ id: r.data.id, template: `${id}-${c.login}`, base: id, variant: 'containers', preview: r.data.preview_url, widgets: pg.widgets, ids: pg.ids, spans: pg.spans, bar: BARRAS.includes(id) && !!args.close_date });
    }
  }
  // Guardar como editora no deja avisos de PHP (ni del kit ni del núcleo: el saneador de contenido y db.php).
  const avisos1 = await probe('notices', avisos0.at);
  const deMcp = [...avisos1.kit, ...avisos1.elements].filter((l) => /mcp/.test(l.split(' · ')[0]) && !/PHP (8192|16384):/.test(l));
  check(!deMcp.length, 'guardar como editora no deja avisos de PHP (content-sanitizer, db.php, el kit)', deMcp.slice(0, 4).join(' | '));
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
  } catch (e) {
    if (process.env.SOLO === 'importar') log(`(${e.message})`);
    else check(false, `${section}: terminó con una excepción`, e.stack || String(e));
    await probe('container', 'active').catch(() => {});
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
      { nombre: 'escritorio', width: 1280, height: 800, deviceScaleFactor: 0.5 },
      { nombre: 'celular', width: 390, height: 844, deviceScaleFactor: 1, isMobile: true, hasTouch: true },
    ];
    const altos = [];
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
        // La portada (v4): cabe en la primera pantalla, sin desplazar; el botón se ve entero. Se mide al cargar, con la
        // barra de administración (la vista previa pide sesión), que un visitante no tiene: la prueba es más estricta.
        const portada = PORTADAS.includes(p.base) ? await page.evaluate((pid) => {
          const tops = [...document.querySelectorAll(`.elementor-${pid} > .elementor-element, .elementor-${pid} > .elementor-section-wrap > .elementor-element`)].filter((el) => !el.classList.contains('elementor-sticky__spacer'));
          const el = tops.find((x) => x.querySelector('h1.elementor-heading-title'));
          if (!el) return { error: 'sin portada (h1)' };
          const r = el.getBoundingClientRect();
          const btn = el.querySelector('.elementor-widget-button .elementor-button');
          const b = btn ? btn.getBoundingClientRect() : null;
          return { fondo: Math.round(r.bottom), alto: window.innerHeight, boton: b && Math.round(b.bottom), botonArriba: b && Math.round(b.top), videos: el.querySelectorAll('.elementor-widget-video, iframe, video').length, imagenes: el.querySelectorAll('.elementor-widget-image img').length, h1: el.querySelectorAll('h1').length, primera: tops.indexOf(el), anclaBoton: btn && btn.getAttribute('href') };
        }, p.id) : null;
        if (portada) {
          await page.screenshot({ path: join(dir, 'capturas', `portada-${p.template}-${p.variant}-${v.nombre}.png`) }).catch(() => {});
        }
        const alto0 = await page.evaluate(() => document.documentElement.scrollHeight);
        for (let y = 0; y < alto0; y += 700) { await page.evaluate((yy) => window.scrollTo(0, yy), y); await new Promise((res) => setTimeout(res, 40)); }
        // La barra de cierre (01 con fecha real) queda fija arriba al desplazar.
        let fija = null;
        if (p.bar) {
          await page.evaluate(() => window.scrollTo(0, 2400));
          await new Promise((res) => setTimeout(res, 700));
          fija = await page.evaluate((pid) => {
            const barra = [...document.querySelectorAll(`.elementor-${pid} .elementor-element`)].find((el) => el.querySelector('.elementor-widget-countdown') && !el.classList.contains('elementor-sticky__spacer') && !el.closest('.elementor-sticky__spacer') && (el.classList.contains('elementor-section') || el.classList.contains('e-con')) && !el.parentElement.closest(`.elementor-${pid} .elementor-element`));
            if (!barra) return { error: 'sin barra' };
            const r = barra.getBoundingClientRect();
            const barraAdmin = document.getElementById('wpadminbar');
            const btn = barra.querySelector('.elementor-widget-button .elementor-button');
            return { activa: barra.classList.contains('elementor-sticky--active'), arriba: Math.round(r.top), alto: Math.round(r.height), admin: barraAdmin ? Math.round(barraAdmin.getBoundingClientRect().height) : 0, scroll: Math.round(window.scrollY), espaciador: document.querySelectorAll(`.elementor-${pid} .elementor-sticky__spacer`).length, boton: !!btn && btn.getBoundingClientRect().height > 0, logo: (() => { const w = barra.querySelector('.elementor-widget-image'); return !w ? 'sin logo' : (w.getBoundingClientRect().width > 0 ? 'visible' : 'oculto'); })() };
          }, p.id);
          await page.screenshot({ path: join(dir, 'capturas', `barra-fija-${p.template}-${p.variant}-${v.nombre}.png`) }).catch(() => {});
        }
        await page.evaluate(() => window.scrollTo(0, 0));
        await new Promise((res) => setTimeout(res, 600));
        const m = await page.evaluate((pid) => {
          const widgets = {};
          // El efecto «fijo» deja en su lugar una copia de la barra (`.elementor-sticky__spacer`, invisible y del mismo
          // alto): sus widgets no cuentan como pintados.
          document.querySelectorAll('.elementor-widget[data-widget_type]').forEach((el) => {
            if (el.closest('.elementor-sticky__spacer')) return;
            const w = el.getAttribute('data-widget_type').split('.')[0];
            widgets[w] = (widgets[w] || 0) + 1;
          });
          const redes = [...document.querySelectorAll(`.elementor-${pid} .elementor-social-icon`)].map((a) => ({ href: a.getAttribute('href') || '', icono: !!a.querySelector('svg, i'), visible: a.getBoundingClientRect().width > 0, nueva: a.getAttribute('target') === '_blank' }));
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
            resaltes: [...document.querySelectorAll(`.elementor-${pid} .elementor-heading-title span`)].filter((sp) => !sp.closest('.elementor-sticky__spacer')).map((sp) => ({ color: getComputedStyle(sp).color, titulo: getComputedStyle(sp.closest('.elementor-heading-title')).color, texto: sp.textContent.slice(0, 40) })),
            redes,
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
        const sinColor = m.resaltes.filter((x) => x.color === x.titulo || !COLORES_RESALTE.includes(x.color));
        check(m.resaltes.length === (p.spans ?? m.resaltes.length) && !sinColor.length, `${tag}: cada resalte de título con su color (${m.resaltes.length})`, JSON.stringify({ guardados: p.spans, pintados: m.resaltes.length, sinColor: sinColor.slice(0, 3) }));
        check(m.postCss && !m.sinCss.length && !m.ajenos.length, `${tag}: carga su CSS (cada widget y cada sección o contenedor de primer nivel del HTML tiene su regla; ninguna regla de otra versión)`, JSON.stringify({ postCss: m.postCss, sinCss: m.sinCss.slice(0, 5), ajenos: m.ajenos.slice(0, 5) }));
        if (portada) {
          check(!portada.error && portada.primera === (p.bar ? 1 : 0) && portada.videos === 0 && portada.imagenes === 1 && portada.h1 === 1 && /^#[a-z]/.test(portada.anclaBoton || ''), `${tag}: la portada va ${p.bar ? 'después de la barra' : 'primero'}, con el logo, la promesa y su botón a un bloque de la página, sin video`, JSON.stringify(portada));
          check(!portada.error && portada.boton !== null && portada.botonArriba >= 0 && portada.boton <= portada.alto && portada.fondo <= portada.alto + 1, `${tag}: la portada cabe en la primera pantalla (${v.width}×${v.height}): el botón termina en ${portada.boton} px y la portada en ${portada.fondo} px`, JSON.stringify(portada));
        }
        if (fija && !fija.error) altos.push({ base: p.base, quien: p.quien || '', variant: p.variant, vista: v.nombre, alto: fija.alto, template: p.template });
        if (fija) {
          check(!fija.error && fija.activa && fija.scroll > 1000 && fija.arriba >= 0 && fija.arriba <= fija.admin + 2 && fija.alto > 20 && fija.boton && fija.espaciador === 1, `${tag}: la barra de cierre queda fija arriba al desplazar, con su botón (${fija.arriba} px tras ${fija.scroll} px)`, JSON.stringify(fija));
          check(fija.logo === (v.nombre === 'celular' ? 'oculto' : 'visible'), `${tag}: el logo del evento en la barra ${v.nombre === 'celular' ? 'se oculta en celular' : 'se ve en escritorio'}`, JSON.stringify(fija));
        }
        if (REDES.includes(p.base)) {
          check(m.redes.length === 5 && m.redes.every((x) => x.icono && x.visible) && m.redes.filter((x) => /^#/.test(x.href)).length === 4 && m.redes.filter((x) => x.href === 'https://wa.me/5218711234567').length === 1, `${tag}: cinco iconos de redes con su logo; los perfiles, con el enlace de muestra del sitio, y WhatsApp al número del sitio`, JSON.stringify(m.redes));
          check(m.redes.every((x) => /^#/.test(x.href) || /^https:\/\/wa\.me\/[0-9]+$/.test(x.href)), `${tag}: ningún icono de red enlaza a la red`, JSON.stringify(m.redes));
          check(m.redes.every((x) => x.nueva === !/^#/.test(x.href)), `${tag}: los perfiles con el enlace de muestra abren en la misma pestaña; WhatsApp, en otra`, JSON.stringify(m.redes));
        } else {
          check(!m.redes.length, `${tag}: sin iconos de redes`, JSON.stringify(m.redes));
        }
        await page.close();
      }
    }

    // Revisión M1: la barra mide lo mismo con contenedores que con secciones (antes, 30 px más con secciones).
    for (const base of BARRAS) for (const vista of ['escritorio', 'celular']) {
      const c = altos.find((x) => x.base === base && x.quien === 'admin' && x.variant === 'containers' && x.vista === vista);
      const s = altos.find((x) => x.base === base && x.quien === 'admin' && x.variant === 'sections' && x.vista === vista);
      check(c && s && Math.abs(c.alto - s.alto) <= 2 && c.alto < 130, `${base} · ${vista}: la barra de cierre mide lo mismo con contenedores (${c?.alto} px) que con secciones (${s?.alto} px), y menos que el margen de las anclas (130 px)`, JSON.stringify({ c, s }));
    }

    start('S7b Anclas como visitante: el título del bloque al que lleva un botón queda bajo la barra fija (revisión M1)');
    // Sin la barra de administración (la vista previa pide sesión, pero un visitante no la tiene): WordPress ya no pone
    // su `scroll-padding-top` ni desplaza la barra fija; lo único que separa el título de la barra es el margen de las
    // anclas del kit (`sticky_anchor_link_offset`).
    await probe('adminbar', '0');
    try {
      const destinos = pages.filter((p) => p.quien === 'admin' && PORTADAS.includes(p.base));
      for (const p of destinos) {
        for (const v of VISTAS) {
          const page = await browser.newPage();
          await page.setViewport(v);
          const errores = [];
          page.on('pageerror', (e) => errores.push(String(e.message || e)));
          await page.goto(p.preview.replace(/^https?:\/\/[^/]+/, base), { waitUntil: 'networkidle0', timeout: 180000 });
          await new Promise((res) => setTimeout(res, 600));
          const tag = `${p.template} (${p.variant}) · ${v.nombre} ${v.width}×${v.height}`;
          check(!(await page.evaluate(() => !!document.getElementById('wpadminbar'))), `${tag}: sin la barra de administración, como un visitante`);
          const clics = [['portada', null]];
          if (p.bar) clics.push(['barra', null]);
          if (p.base === 'venta-completa') clics.push(['historias', '#historias']);
          for (const [como] of clics) {
            await page.evaluate(() => window.scrollTo(0, 0));
            await new Promise((res) => setTimeout(res, 400));
            if (como !== 'portada') { await page.evaluate(() => window.scrollTo(0, 1600)); await new Promise((res) => setTimeout(res, 700)); }
            const href = await page.evaluate((pid, como) => {
              const tops = [...document.querySelectorAll(`.elementor-${pid} > .elementor-element, .elementor-${pid} > .elementor-section-wrap > .elementor-element`)].filter((x) => !x.classList.contains('elementor-sticky__spacer'));
              let a = null;
              if (como === 'portada') a = tops.find((x) => x.querySelector('h1'))?.querySelector('a.elementor-button');
              else if (como === 'barra') a = [...document.querySelectorAll(`.elementor-${pid} .elementor-sticky--active a.elementor-button`)].find((x) => (x.getAttribute('href') || '').startsWith('#'));
              else a = [...document.querySelectorAll(`.elementor-${pid} a.elementor-button`)].find((x) => x.getAttribute('href') === '#historias');
              if (!a) return null;
              a.click();
              return a.getAttribute('href');
            }, p.id, como);
            await new Promise((res) => setTimeout(res, 1800));
            const m = await page.evaluate((pid, href) => {
              const destino = href && document.getElementById(href.slice(1));
              const titulo = destino && destino.querySelector('.elementor-heading-title');
              const barra = [...document.querySelectorAll(`.elementor-${pid} .elementor-sticky--active`)].find((el) => !el.classList.contains('elementor-sticky__spacer'));
              const rt = titulo && titulo.getBoundingClientRect();
              const rb = barra && barra.getBoundingClientRect();
              return { titulo: rt && { texto: titulo.textContent.trim().slice(0, 40), arriba: Math.round(rt.top), abajo: Math.round(rt.bottom) }, barra: rb && { arriba: Math.round(rb.top), abajo: Math.round(rb.bottom) }, alto: window.innerHeight, scroll: Math.round(window.scrollY) };
            }, p.id, href);
            await page.screenshot({ path: join(dir, 'capturas', `ancla-${p.template}-${p.variant}-${v.nombre}-${como}.png`) }).catch(() => {});
            const debajo = m.barra ? m.barra.abajo : 0;
            check(href && m.titulo && m.titulo.arriba >= debajo && m.titulo.abajo <= m.alto && m.scroll > 0, `${tag}: tras el clic en el botón de ${como} (${href}), el título «${m.titulo?.texto}» queda a la vista${m.barra ? ` bajo la barra (barra hasta ${m.barra.abajo} px, título desde ${m.titulo?.arriba} px)` : ` (desde ${m.titulo?.arriba} px)`}`, JSON.stringify(m));
          }
          check(!errores.length, `${tag}: sin errores de JavaScript al seguir las anclas`, errores.slice(0, 3).join(' | '));
          await page.close();
        }
      }
    } finally {
      await probe('adminbar', '1');
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
      // Importar las ocho (la 01 es grande) en un Playground de un solo proceso puede pasar de tres minutos.
      const t0 = Date.now();
      await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 900000 }), page.$eval('#elementor-import-template-form', (f) => f.submit())]);
      log(`  (importación: ${Math.round((Date.now() - t0) / 1000)} s)`);
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
