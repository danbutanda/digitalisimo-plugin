// Ensayo de punta a punta del CSS tras las escrituras del MCP de DIGITALÍSIMO Elements, contra el Playground que
// levantó elements-mcp-css.sh: JSON-RPC real contra /wp-json/elementor/mcp (initialize, notifications/initialized,
// tools/list y tools/call) con una contraseña de aplicación, y la sonda (?digi_mc=…) para preparar el sitio y leer su
// estado sin pasar por el MCP. Tras cada escritura abre la página (borrador con la sesión de la administradora,
// publicada como visitante o por el enlace firmado de create-preview-link) y comprueba que cada clase de estilo local
// (e-xxxxxxxx-xxxx) y global del HTML existe en el CSS que la página carga y que el `ver` de su hoja cambió.
// Escribe <dir>/result.txt («ok · …», «FALLA · …» o «nota · …») y <dir>/result.json; sale 1 si algo falla.
// Con EXPECT_FAIL=1 (código anterior al arreglo) sale 0 sólo si fallan exactamente las comprobaciones de BEFORE_FIX.
// Uso: node run.mjs <puerto> <host> <dir>
import http from 'node:http';
import { appendFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [port, host, dir] = process.argv.slice(2);
const expectFail = process.env.EXPECT_FAIL === '1';
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const secrets = () => JSON.parse(readFileSync(`${dir}/probe/secrets.json`, 'utf8'));
const results = [];
let failures = 0;
let section = '';
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
// Cada comprobación lleva un ID estable: con el código anterior se espera que fallen exactamente las de BEFORE_FIX.
const check = (id, ok, what, detail = '') => {
  results.push({ id, section, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 800) });
  if (ok) log(`ok · ${id} · ${what}`);
  else { failures++; log(`FALLA · ${id} · ${what}${detail ? ` · ${String(detail).slice(0, 800)}` : ''}`); }
  return !!ok;
};
const note = (what) => { results.push({ id: 'nota', section, what, ok: true, note: true }); log(`nota · ${what}`); };
const start = (name) => { section = name; log(`== ${name}`); };

// Las comprobaciones que fallan con DIGITALÍSIMO Elements 4.3.0.185 (núcleo 4.3.5), sin el arreglo.
const BEFORE_FIX = [
  // El incidente: la hoja «frontend» del borrador no se invalida (enlace firmado con el CSS y el ver de la primera).
  'M1.2-css', 'M1.2-ver', 'M1.2-color', 'M1.3-css', 'M1.3-ver', 'M1.3-color',
  'M2.1-css', 'M2.1-ver', 'M2.2-css', 'M2.2-ver',
  // Un enlace viejo deja su HTML (caché del documento) y su CSS en la página.
  'M4.3a-html', 'M4.3a-css', 'M4.3-css',
  // La hoja vieja sigue sirviéndose después de update-page-settings y de publicar.
  'M5.1-css', 'M6.1-css', 'M6.2-css', 'M6.3-html', 'M6.3-css', 'M6.3b-css',
  // publish-document promueve el autoguardado sin invalidar hojas ni la caché HTML.
  'M6.4-html', 'M6.4-css', 'M6.4-ver',
  // font-family con varias familias: comillas anidadas, Chrome descarta la declaración.
  'M9.1-font', 'M9.3-chrome-font',
];

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { Host: host, ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* respuesta no JSON */ }
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
  const res = await request('GET', `/?digi_mc=${step}&token=${token}&value=${encodeURIComponent(value)}`, { headers: step === 'login' ? {} : {} });
  if (res.status !== 200) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 300)}`);
  return step === 'login' ? res : res.json;
};

class Client {
  constructor() { this.id = 0; this.session = ''; this.names = {}; }
  auth() { return `Basic ${Buffer.from(`admin:${secrets()['password:admin']}`).toString('base64')}`; }
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
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-mcp-css-e2e', version: '1.0.0' } });
    this.session = res.headers['mcp-session-id'] || '';
    await this.post({ jsonrpc: '2.0', method: 'notifications/initialized' });
    return res;
  }
  async tools() {
    const res = await this.rpc('tools/list', {});
    const tools = res.json?.result?.tools || [];
    for (const t of tools) this.names[t.name.replace(/^elementor[-/]/, '')] = t.name;
    return tools;
  }
  // tools/call de una herramienta del constructor por su slug (build-composition, manage-elements…).
  async call(slug, args) {
    const name = this.names[slug] || `elementor-${slug}`;
    const res = await this.rpc('tools/call', { name, arguments: args });
    const result = res.json?.result;
    const error = res.json?.error;
    const text = (result?.content || []).map((c) => c.text || '').join('\n');
    let data = result?.structuredContent;
    if (!data && text) { try { data = JSON.parse(text); } catch { data = null; } }
    return { isError: !!(result?.isError || error), text: error ? JSON.stringify(error) : text, data: data || {}, raw: res.text };
  }
}

// Sesión de la administradora para ver borradores (cookies de wp_set_auth_cookie).
let cookies = '';
async function login() {
  const res = await probe('login');
  const set = [].concat(res.headers['set-cookie'] || []);
  cookies = set.map((c) => c.split(';')[0]).filter((c) => /^wordpress_/.test(c)).join('; ');
  return cookies;
}

const LOCAL_RE = /^e-[0-9a-f]{6,12}-[0-9a-f]{3,10}$/;
const decode = (u) => u.replace(/&#038;|&amp;/g, '&');
const pathOf = (u) => { const url = new URL(decode(u), `http://${host}`); return url.pathname + url.search; };
const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

// Abre una página y trae el CSS que carga (hojas de uploads/elementor/css y <style> en línea). Cada página queda en
// <dir>/pages/NN.html con sus hojas en NN.json (evidencia del informe).
let pageNo = 0;
async function view(path, { admin = false } = {}) {
  const res = await request('GET', path, { headers: admin ? { Cookie: cookies } : {} });
  const html = res.text;
  const links = [...html.matchAll(/<link\b[^>]*\bhref=['"]([^'"]+)['"][^>]*>/g)]
    .map((m) => decode(m[1]))
    .filter((u) => /\/wp-content\/uploads\/elementor\/css\/[^?]+\.css/.test(u))
    .map((u) => { const url = new URL(u, `http://${host}`); return { file: url.pathname.split('/').pop(), ver: url.searchParams.get('ver') || '', path: url.pathname + url.search }; });
  let css = [...html.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)].map((m) => m[1]).join('\n');
  const sheets = {};
  for (const l of links) {
    const r = await request('GET', l.path);
    sheets[l.file] = r.status === 200 ? r.text : '';
    css += `\n${sheets[l.file]}`;
  }
  const classes = new Set();
  for (const m of html.matchAll(/\bclass=["']([^"']*)["']/g)) for (const c of m[1].split(/\s+/)) if (c) classes.add(c);
  const local = [...classes].filter((c) => LOCAL_RE.test(c));
  const n = String(++pageNo).padStart(2, '0');
  mkdirSync(`${dir}/pages`, { recursive: true });
  writeFileSync(`${dir}/pages/${n}.html`, html);
  writeFileSync(`${dir}/pages/${n}.json`, JSON.stringify({ path: path.replace(/elementor_preview_token=[^&]+/, 'elementor_preview_token=…'), admin, section, links, local, sheets }, null, 1));
  return { status: res.status, html, links, sheets, css, classes, local, n };
}

const hasRule = (css, cls) => new RegExp(`\\.${escapeRe(cls)}(?![\\w-])`).test(css);
const verOf = (page, file) => page.links.find((l) => l.file === file)?.ver || '';
// La hoja local de escritorio que carga la página: «frontend» (visitante, enlace firmado) o «preview» (un borrador que
// abre quien puede editarlo: WordPress lo trata como vista previa).
const localSheet = (page, postId) => page.links.find((l) => new RegExp(`^local-${postId}-(frontend|preview)-desktop\\.css$`).test(l.file)) || { file: '', ver: '' };

// Cada clase local del HTML (y las esperadas) tiene regla en el CSS cargado; el `ver` de la hoja de escritorio cambió.
function assertPage(id, label, page, { postId, expected = [], prevVer = null, globals = [] } = {}) {
  check(`${id}-http`, page.status === 200, `${label}: la página responde 200`, `HTTP ${page.status}`);
  const missingHtml = expected.filter((c) => !page.classes.has(c));
  if (expected.length) check(`${id}-html`, missingHtml.length === 0, `${label}: el HTML tiene los ${expected.length} estilos locales guardados`, `faltan en el HTML: ${missingHtml.join(', ')}`);
  const missing = page.local.filter((c) => !hasRule(page.css, c));
  check(`${id}-css`, page.local.length > 0 && missing.length === 0, `${label}: cada una de las ${page.local.length} clases e-… del HTML existe en el CSS cargado`, `sin regla: ${missing.join(', ')} · hojas: ${page.links.map((l) => `${l.file}?ver=${l.ver}`).join(', ')}`);
  for (const g of globals) check(`${id}-global-${g}`, page.classes.has(g) && hasRule(page.css, g), `${label}: la clase global .${g} está en el HTML y en el CSS`, `en HTML ${page.classes.has(g)} · en CSS ${hasRule(page.css, g)}`);
  const sheet = localSheet(page, postId);
  const ver = sheet.file ? `${sheet.file}?ver=${sheet.ver}` : '';
  if (prevVer !== null) check(`${id}-ver`, ver && ver !== prevVer, `${label}: el ver de la hoja local cambió (${prevVer} → ${ver})`, `${ver || '(sin hoja local)'} igual al anterior`);
  return ver;
}

const design = (n) => ({
  xml_structure: '<e-flexbox configuration-id="Seccion"><e-heading configuration-id="Titulo"></e-heading><e-paragraph configuration-id="Texto"></e-paragraph></e-flexbox>',
  element_config: { Titulo: { title: `Versión ${n}` }, Texto: { paragraph: `Texto de la versión ${n} con <strong>fuerte ${n}</strong>` } },
  style: {
    Seccion: `display: flex; flex-direction: column; gap: ${8 * n}px; padding: ${10 * n}px; background-color: #f${n}f${n}f${n};`,
    Titulo: `color: #${n}${n}0000; font-size: ${30 + n}px; font-family: "Geist Mono", ui-monospace, monospace;`,
    Texto: `color: #0000${n}${n}; & strong { font-weight: 800; color: #00${n}${n}00; }`,
  },
});
const idsIn = (xml, tag) => [...String(xml || '').matchAll(new RegExp(`<${tag}\\b[^>]*\\bid="([^"]+)"`, 'g'))].map((m) => m[1]);
const okCall = (id, r, what) => check(id, !r.isError && r.data?.success !== false && r.data?.status !== 'error', what, r.text.slice(0, 600));

// =====================================================================================================================
const admin = new Client();
let wp = '';
async function main() {
  start('M0 Preparación');
  const ping = await probe('ping');
  wp = ping.wp;
  check('M0-ping', ping.ready && ping.abilities && ping.atomic, `sitio listo: WordPress ${ping.wp}, PHP ${ping.php}, Abilities API ${ping.abilities}, editor atómico ${ping.atomic}, arreglo cargado ${ping.fix_loaded}`, JSON.stringify(ping));
  await probe('users');
  const init = await admin.init();
  check('M0-init', init.status === 200 && admin.session, 'initialize con contraseña de aplicación: sesión del servidor MCP', init.text.slice(0, 300));
  const tools = await admin.tools();
  const need = ['create-page', 'build-composition', 'manage-elements', 'update-page-settings', 'create-preview-link', 'publish-document', 'manage-classes', 'manage-global-variable', 'manage-default-styles'];
  const missingTools = need.filter((s) => !admin.names[s]);
  check('M0-tools', missingTools.length === 0, `tools/list ofrece las ${need.length} herramientas del ensayo (${tools.length} en total)`, `faltan: ${missingTools.join(', ')} · hay: ${tools.map((t) => t.name).join(', ')}`);
  await login();
  check('M0-login', /wordpress_logged_in_/.test(cookies), 'sesión de la administradora para ver borradores');

  // Las administradoras corren las migraciones del constructor al entrar al escritorio (capacidades de clases globales).
  const dash = await request('GET', '/wp-admin/index.php', { headers: { Cookie: cookies } });
  check('M0-admin', dash.status === 200, 'la administradora entra al escritorio (migraciones del constructor)', `HTTP ${dash.status}`);

  // Tras cada escritura en un borrador: la administradora lo abre (WordPress lo trata como vista previa: hoja local
  // «preview») y se pide un enlace firmado nuevo y se abre como visitante (hoja local «frontend», la del incidente).
  const both = async (id, label, P, state, prevLink) => {
    const own = await view(`/?page_id=${P}`, { admin: true });
    assertPage(`${id}a`, `${label}, borrador abierto por la administradora`, own, { postId: P, expected: state.local_ids });
    const r = await admin.call('create-preview-link', { post_id: P });
    const link = await view(pathOf(r.data?.url || '/'));
    const ver = assertPage(id, `${label}, enlace firmado nuevo`, link, { postId: P, expected: state.local_ids, prevVer: prevLink });
    return { own, link, ver };
  };

  // ---------------------------------------------------------------------------------------------------------------
  start('M1 Página V4 en borrador reconstruida tres veces con build-composition (replace_children)');
  let r = await admin.call('create-page', { title: 'CSS del MCP' });
  const P = Number(r.data?.id);
  check('M1.0-create', !r.isError && P > 0 && r.data?.status === 'draft', `create-page: borrador ${P}`, r.text);
  r = await admin.call('update-page-settings', { post_id: P, settings: { template: 'elementor_canvas' } });
  okCall('M1.0-canvas', r, 'update-page-settings: plantilla lienzo');
  const pagePath = `/?page_id=${P}`;
  let last = null;
  let prev = null;
  let state = null;
  let seen = null;
  for (const n of [1, 2, 3]) {
    r = await admin.call('build-composition', { post_id: P, mode: 'replace_children', ...design(n) });
    okCall(`M1.${n}-build`, r, `reconstrucción ${n} (replace_children): success`);
    last = r;
    state = await probe('state', String(P));
    if (n > 1) note(`tras la reconstrucción ${n}, antes de abrirla: caché local de ${P} = ${JSON.stringify(state.local)}`);
    seen = await both(`M1.${n}`, `reconstrucción ${n}`, P, state, n === 1 ? null : prev);
    check(`M1.${n}-color`, seen.link.css.toLowerCase().includes(`#${n}${n}0000`), `reconstrucción ${n}: el CSS del enlace firmado lleva el color nuevo del título (#${n}${n}0000)`, 'el color nuevo no está en el CSS');
    prev = seen.ver;
  }
  const heading = idsIn(last.data?.resolved_xml, 'e-heading')[0];
  const flex = idsIn(last.data?.resolved_xml, 'e-flexbox')[0];

  // ---------------------------------------------------------------------------------------------------------------
  start('M2 Los demás modos: append en la raíz y replace_children dentro de un contenedor');
  r = await admin.call('build-composition', { post_id: P, mode: 'append', ...design(4) });
  okCall('M2.1-build', r, 'append en la raíz: success');
  state = await probe('state', String(P));
  prev = (await both('M2.1', 'append', P, state, prev)).ver;
  r = await admin.call('build-composition', {
    post_id: P, parent_id: flex, mode: 'replace_children',
    xml_structure: '<e-heading configuration-id="Interior"></e-heading>',
    element_config: { Interior: { title: 'Dentro del contenedor' } },
    style: { Interior: 'color: #5a5a00; text-transform: uppercase;' },
  });
  okCall('M2.2-build', r, `replace_children dentro del contenedor ${flex}: success`);
  state = await probe('state', String(P));
  prev = (await both('M2.2', 'replace_children en un contenedor', P, state, prev)).ver;

  // ---------------------------------------------------------------------------------------------------------------
  start('M3 manage-elements');
  const target = idsIn(r.data?.resolved_xml, 'e-heading')[0] || heading;
  r = await admin.call('manage-elements', { post_id: P, operations: [{ action: 'update', element_id: target, style: 'color: #7a7a00; letter-spacing: 2px;' }] });
  okCall('M3.1-update', r, `manage-elements update del estilo de ${target}: success`);
  state = await probe('state', String(P));
  seen = await both('M3.1', 'manage-elements', P, state, prev);
  prev = seen.ver;
  check('M3.1-color', seen.link.css.toLowerCase().includes('#7a7a00'), 'manage-elements: el color nuevo está en el CSS del enlace firmado');

  // ---------------------------------------------------------------------------------------------------------------
  start('M4 Un enlace firmado viejo abierto después de otra reconstrucción');
  r = await admin.call('create-preview-link', { post_id: P });
  const link1 = r.data?.url;
  check('M4.0-link', !r.isError && /elementor_preview_token=/.test(link1 || ''), 'create-preview-link devuelve el enlace firmado', r.text);
  let page = await view(pathOf(link1));
  assertPage('M4.1', 'enlace firmado (anónimo)', page, { postId: P, expected: state.local_ids });
  const oldIds = state.local_ids;
  r = await admin.call('build-composition', { post_id: P, mode: 'replace_children', ...design(5) });
  okCall('M4.2-build', r, 'reconstrucción 5: success');
  state = await probe('state', String(P));
  // El enlace viejo (anterior a la reconstrucción) es el primero en abrirse: muestra su instantánea con su CSS…
  page = await view(pathOf(link1));
  assertPage('M4.2', 'el enlace viejo abierto después de la reconstrucción 5', page, { postId: P, expected: oldIds });
  // …y no deja ese CSS en la hoja de la página: el enlace nuevo y el borrador ven el de la reconstrucción 5.
  seen = await both('M4.3', 'después del enlace viejo', P, state, null);
  prev = seen.ver;

  // ---------------------------------------------------------------------------------------------------------------
  start('M5 update-page-settings con un cambio de estilo');
  r = await admin.call('update-page-settings', { post_id: P, settings: { background_background: 'classic', background_color: '#13579B' } });
  okCall('M5.1-settings', r, 'update-page-settings: fondo del documento');
  seen = await both('M5.1', 'tras update-page-settings', P, state, null);
  check('M5.1-post-css', /13579b/i.test(seen.own.sheets[`post-${P}.css`] || ''), `post-${P}.css lleva el fondo nuevo (borrador abierto por la administradora)`, `hojas: ${Object.keys(seen.own.sheets).join(', ')}`);
  // Límite del núcleo, igual con 4.3.0.185: el enlace firmado de un borrador no carga la hoja del documento
  // (post-<id>.css), porque lee `_elementor_css` de la revisión firmada y la encuentra en «empty».
  note(`enlace firmado del borrador: post-${P}.css ${seen.link.sheets[`post-${P}.css`] !== undefined ? 'cargada' : 'no cargada (límite del núcleo, ver el informe)'}`);

  // ---------------------------------------------------------------------------------------------------------------
  start('M6 publish-document y una página publicada (autoguardado y promoción)');
  r = await admin.call('publish-document', { post_id: P });
  check('M6.1-publish', !r.isError && r.data?.status === 'publish', 'publish-document: publicada', r.text);
  page = await view(pagePath);
  prev = assertPage('M6.1', 'publicada, visitante', page, { postId: P, expected: state.local_ids });
  check('M6.1-post-css', /13579b/i.test(page.sheets[`post-${P}.css`] || ''), `publicada: post-${P}.css lleva el fondo de update-page-settings`, Object.keys(page.sheets).join(', '));
  const liveIds = state.local_ids;
  r = await admin.call('build-composition', { post_id: P, mode: 'replace_children', ...design(6) });
  okCall('M6.2-build', r, 'reconstrucción 6 sobre la publicada: success');
  state = await probe('state', String(P));
  check('M6.2-autosave', state.autosave > 0 && JSON.stringify(state.local_ids) === JSON.stringify(liveIds), `la reconstrucción queda en el autoguardado ${state.autosave}; la publicada no cambia`, JSON.stringify(state));
  page = await view(pagePath);
  assertPage('M6.2', 'publicada con cambios pendientes, visitante', page, { postId: P, expected: liveIds });
  // El enlace firmado de una publicada con cambios pendientes muestra esos cambios (el autoguardado), con su CSS.
  const pendingIds = state.autosave_ids;
  r = await admin.call('create-preview-link', { post_id: P });
  page = await view(pathOf(r.data?.url));
  assertPage('M6.3', 'enlace firmado de la publicada con cambios pendientes', page, { postId: P, expected: pendingIds });
  page = await view(pagePath);
  prev = assertPage('M6.3b', 'publicada otra vez, visitante', page, { postId: P, expected: liveIds });
  r = await admin.call('publish-document', { post_id: P });
  check('M6.4-publish', !r.isError && r.data?.status === 'publish', 'publish-document promueve el autoguardado', r.text);
  state = await probe('state', String(P));
  check('M6.4-promoted', state.autosave === 0 && JSON.stringify(state.local_ids) !== JSON.stringify(liveIds), 'la publicada tiene ahora los estilos de la reconstrucción 6', JSON.stringify(state));
  page = await view(pagePath);
  assertPage('M6.4', 'publicada tras la promoción, visitante', page, { postId: P, expected: state.local_ids, prevVer: prev });

  // ---------------------------------------------------------------------------------------------------------------
  start('M7 Kit: variables, clases globales y estilos predeterminados regeneran el CSS global');
  r = await admin.call('manage-global-variable', { operations: [{ action: 'create', type: 'global-color-variable', label: 'mc-acento', value: '#aa00ff' }] });
  okCall('M7.1-var', r, 'manage-global-variable: color mc-acento');
  const varId = (r.text.match(/e-gv-[0-9a-z]+/) || [])[0] || '';
  r = await admin.call('manage-classes', { operations: [{ action: 'create', label: 'mc-tarjeta', css: 'background-color: var(--mc-acento); padding: 12px;' }] });
  okCall('M7.2-class', r, 'manage-classes: clase mc-tarjeta');
  r = await admin.call('create-page', { title: 'Kit del MCP' });
  const K = Number(r.data?.id);
  r = await admin.call('build-composition', {
    post_id: K, mode: 'replace_children',
    xml_structure: '<e-flexbox configuration-id="Caja"><e-heading configuration-id="H2"></e-heading></e-flexbox>',
    element_config: { H2: { title: 'Kit' } }, classes: { Caja: ['mc-tarjeta'] }, style: { H2: 'color: #222222;' },
  });
  okCall('M7.3-build', r, `página ${K} con la clase global`);
  const kPath = `/?page_id=${K}`;
  page = await view(kPath, { admin: true });
  state = await probe('state', String(K));
  assertPage('M7.3', 'página con clase global', page, { postId: K, expected: state.local_ids, globals: ['mc-tarjeta'] });
  check('M7.3-var', /aa00ff/i.test(page.css), 'la variable mc-acento (#aa00ff) está en el CSS cargado', varId);
  // Las variables van en la hoja del kit (post-<kit>.css, `:root { --mc-acento: … }`); las clases globales, en
  // global-<id>-<contexto>-desktop.css («preview» en el borrador que abre la administradora).
  const kitFile = Object.keys(page.sheets).find((f) => /^post-\d+\.css$/.test(f) && /--mc-acento/.test(page.sheets[f])) || '';
  const globalFile = page.links.find((l) => new RegExp(`^global-${K}-(frontend|preview)-desktop\\.css$`).test(l.file))?.file || `global-${K}-preview-desktop.css`;
  const verKit = verOf(page, kitFile);
  const verGlobal = verOf(page, globalFile);
  r = await admin.call('manage-global-variable', { operations: [{ action: 'update', id: varId, label: 'mc-acento', value: '#00aa55' }] });
  okCall('M7.4-var', r, 'manage-global-variable: mc-acento pasa a #00aa55');
  page = await view(kPath, { admin: true });
  check('M7.4-css', /--mc-acento:\s*#00aa55/i.test(page.sheets[kitFile] || '') && !/aa00ff/i.test(page.css), `${kitFile || 'la hoja del kit'} lleva el valor nuevo de la variable y el CSS cargado ya no el viejo`, `${kitFile}: ${(page.sheets[kitFile] || '').match(/--mc-acento[^;}]*/g)}`);
  check('M7.4-ver', verOf(page, kitFile) !== verKit, `el ver de ${kitFile} (kit) cambió`, verKit);
  r = await admin.call('manage-classes', { operations: [{ action: 'update', label: 'mc-tarjeta', css: 'padding: 21px; border-radius: 7px;' }] });
  okCall('M7.5-class', r, 'manage-classes: mc-tarjeta cambia');
  page = await view(kPath, { admin: true });
  check('M7.5-css', /21px/.test(page.sheets[globalFile] || '') && /\.mc-tarjeta/.test(page.sheets[globalFile] || ''), `${globalFile} lleva la clase cambiada`, (page.sheets[globalFile] || '').slice(0, 300));
  check('M7.5-ver', verOf(page, globalFile) !== verGlobal, `el ver de ${globalFile} cambió`, verGlobal);
  r = await admin.call('create-preview-link', { post_id: K });
  page = await view(pathOf(r.data?.url || '/'));
  state = await probe('state', String(K));
  assertPage('M7.5b', 'enlace firmado de la página con clase global', page, { postId: K, expected: state.local_ids, globals: ['mc-tarjeta'] });
  check('M7.5b-css', /21px/.test(page.sheets[`global-${K}-frontend-desktop.css`] || '') && /00aa55/i.test(page.css), `enlace firmado: global-${K}-frontend-desktop.css con la clase cambiada y el kit con la variable nueva`, Object.keys(page.sheets).join(', '));
  page = await view(kPath, { admin: true });
  r = await admin.call('manage-default-styles', { operations: [{ action: 'update', tag: 'h2', css: 'letter-spacing: 3px;' }] });
  okCall('M7.6-default', r, 'manage-default-styles: h2 con letter-spacing 3px');
  page = await view(kPath, { admin: true });
  const defFile = Object.keys(page.sheets).find((f) => /^default-.*desktop\.css$/.test(f)) || '';
  const verDef = verOf(page, defFile);
  check('M7.6-css', /letter-spacing:\s*3px/.test(page.sheets[defFile] || ''), `${defFile || 'la hoja de estilos predeterminados'} lleva el estilo de h2`, Object.keys(page.sheets).join(', '));
  r = await admin.call('manage-default-styles', { operations: [{ action: 'update', tag: 'h2', css: 'letter-spacing: 5px;', mode: 'replace' }] });
  okCall('M7.7-default', r, 'manage-default-styles: h2 pasa a 5px');
  page = await view(kPath, { admin: true });
  check('M7.7-css', /letter-spacing:\s*5px/.test(page.sheets[defFile] || '') && !/letter-spacing:\s*3px/.test(page.sheets[defFile] || ''), `${defFile} lleva el valor nuevo`, (page.sheets[defFile] || '').slice(0, 300));
  check('M7.7-ver', verOf(page, defFile) !== verDef, `el ver de ${defFile} cambió`, verDef);

  // ---------------------------------------------------------------------------------------------------------------
  start('M8 Una página V3 (clásica) no empeora');
  const V = (await probe('v3page')).id;
  const vPath = `/?page_id=${V}`;
  page = await view(vPath);
  const v3css = page.sheets[`post-${V}.css`] || '';
  check('M8.1-css', /\.elementor-element-c3a1b2d/.test(v3css) && /1e90ff/i.test(v3css) && /ff6600/i.test(v3css), `post-${V}.css con el contenedor y el título clásicos`, Object.keys(page.sheets).join(', '));
  const verV3 = verOf(page, `post-${V}.css`);
  r = await admin.call('update-page-settings', { post_id: V, settings: { background_background: 'classic', background_color: '#0A0B0C' } });
  okCall('M8.2-settings', r, 'update-page-settings en la página clásica');
  page = await view(vPath);
  const v3after = page.sheets[`post-${V}.css`] || '';
  check('M8.2-css', /0a0b0c/i.test(v3after) && /\.elementor-element-c3a1b2d/.test(v3after) && /ff6600/i.test(v3after), `post-${V}.css regenerado con el fondo nuevo y lo demás intacto`, v3after.slice(0, 300));
  check('M8.2-ver', verOf(page, `post-${V}.css`) !== verV3, `el ver de post-${V}.css cambió`, verV3);
  check('M8.2-nolocal', !page.links.some((l) => l.file.startsWith(`local-${V}-`)), 'la página clásica no carga hojas de estilos locales atómicos');

  // ---------------------------------------------------------------------------------------------------------------
  start('M9 Observación secundaria: font-family con comillas y `\\n` literal en los selectores anidados');
  page = await view(pagePath);
  const localCss = page.sheets[`local-${P}-frontend-desktop.css`] || '';
  const families = [...localCss.matchAll(/font-family:([^;}]*)/g)].map((m) => m[1]);
  check('M9.1-font', families.length > 0 && families.every((f) => !/""/.test(f) && f.trim() === '"Geist Mono", ui-monospace, monospace'), `font-family sale como «"Geist Mono", ui-monospace, monospace» (${families.length} declaraciones)`, families.join(' | '));
  const literal = (localCss.match(/\\n\}/g) || []).length;
  note(`«\\n}» literal en local-${P}-frontend-desktop.css: ${literal} veces (núcleo, styles-renderer.php; ver el informe)`);
  check('M9.2-nested', /& strong\s*\{[^}]*font-weight:\s*800/.test(localCss), 'la regla anidada «& strong» está en el CSS', localCss.slice(0, 400));
  await chromeCheck(pagePath);
}

// Con Chrome (si hay puppeteer-core): estilos calculados del título y del <strong> anidado en la página publicada.
async function chromeCheck(path) {
  const base = process.env.PUPPETEER_PATH;
  if (!base || process.env.CHROME === '0' || !existsSync(`${base}/puppeteer-core/lib/puppeteer/puppeteer-core.js`)) { note('sin Chrome: no se miden los estilos calculados'); return; }
  const { default: puppeteer } = await import(pathToFileURL(`${base}/puppeteer-core/lib/puppeteer/puppeteer-core.js`).href);
  const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dmc-' : `${tmpdir()}/dmc-`);
  const browser = await puppeteer.launch({ userDataDir: profile, executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', `--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1`] });
  try {
    const tab = await browser.newPage();
    await tab.goto(`http://${host}${path}`, { waitUntil: 'load', timeout: 300_000 });
    const got = await tab.evaluate(() => {
      const h = document.querySelector('.e-heading-base, h2');
      const s = document.querySelector('p strong, .e-paragraph-base strong, strong');
      return { family: h ? getComputedStyle(h).fontFamily : null, weight: s ? getComputedStyle(s).fontWeight : null, color: s ? getComputedStyle(s).color : null };
    });
    check('M9.3-chrome-font', /Geist Mono/.test(got.family || '') && /monospace/.test(got.family || ''), `Chrome: el título calcula font-family ${got.family}`, JSON.stringify(got));
    check('M9.3-chrome-nested', got.weight === '800', `Chrome: el <strong> anidado calcula font-weight ${got.weight} y color ${got.color} (el «\\n» literal no rompe la regla)`, JSON.stringify(got));
  } finally {
    await browser.close();
  }
}

try {
  await main();
} catch (e) {
  check('excepcion', false, 'el ensayo terminó con una excepción', e.stack || String(e));
}
const failed = results.filter((x) => !x.ok).map((x) => x.id);
let exitCode = failures ? 1 : 0;
let expected = null;
if (expectFail) {
  const extra = failed.filter((id) => !BEFORE_FIX.includes(id));
  const missing = BEFORE_FIX.filter((id) => !failed.includes(id));
  expected = { extra, missing };
  log(`== Código anterior: fallan ${failed.length} (${failed.join(', ')}); esperadas ${BEFORE_FIX.length}; de más: ${extra.join(', ') || 'ninguna'}; no fallaron: ${missing.join(', ') || 'ninguna'}`);
  exitCode = extra.length || missing.length ? 1 : 0;
}
writeFileSync(`${dir}/result.json`, JSON.stringify({ wp, checks: results.filter((x) => !x.note).length, failures, failed, expected_failures: expected, results }, null, 1));
log(failures ? `== ${failures} fallas de ${results.length}` : `== todo bien (${results.length})`);
process.exit(exitCode);
