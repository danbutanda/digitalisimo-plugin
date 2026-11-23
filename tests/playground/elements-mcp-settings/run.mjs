// Ensayo de punta a punta de los ajustes que escribe el MCP de DIGITALÍSIMO Elements, contra el Playground que levantó
// elements-mcp-settings.sh: JSON-RPC real contra /wp-json/elementor/mcp (initialize, notifications/initialized,
// tools/list y tools/call de update-page-settings) con una contraseña de aplicación, y la sonda (?digi_ms=…) para
// sembrar el kit y la página y leer su meta directo de la base.
//   S0  la herramienta anuncia la combinación, el borrado con null y changed_keys
//   S1  kit completo, sólo custom_css: el resto del kit queda igual y la página carga el CSS del kit con
//       --e-global-color-* y --e-global-typography-*
//   S2  un global en __globals__; S3 página con diseño, fondo y CSS; S4 borrar con null (clave y global)
//   S5  kit con post_status publish: ninguna opción de WordPress ni de WooCommerce cambia salvo la que se manda
//   S6  publicada con un cambio pendiente de build-composition: update-page-settings no lo borra y publish-document
//       publica los dos
// Escribe <dir>/result.txt y <dir>/result.json; sale 1 si algo falla. Uso: node run.mjs <puerto> <host> <dir>
import http from 'node:http';
import { appendFileSync, readFileSync, writeFileSync } from 'node:fs';

const [port, host, dir] = process.argv.slice(2);
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const secrets = () => JSON.parse(readFileSync(`${dir}/probe/secrets.json`, 'utf8'));
const results = [];
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
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
const probe = async (step) => {
  const res = await request('GET', `/?digi_ms=${step}&token=${token}`);
  if (res.status !== 200 || !res.json) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
};

class Client {
  constructor() { this.id = 0; this.session = ''; this.names = {}; }
  auth() { return `Basic ${Buffer.from(`admin:${secrets()['password:admin']}`).toString('base64')}`; }
  post(payload) {
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
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-mcp-settings-e2e', version: '1.0.0' } });
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

// Igualdad sin importar el orden de las claves de los objetos (sí el de las listas).
const norm = (v) => (Array.isArray(v) ? v.map(norm) : v && typeof v === 'object' ? Object.fromEntries(Object.keys(v).sort().map((k) => [k, norm(v[k])])) : v);
const same = (a, b) => JSON.stringify(norm(a)) === JSON.stringify(norm(b));
const without = (o, keys) => Object.fromEntries(Object.entries(o || {}).filter(([k]) => !keys.includes(k)));
const diffKeys = (a, b) => [...new Set([...Object.keys(a || {}), ...Object.keys(b || {})])].filter((k) => !same((a || {})[k], (b || {})[k]));
const sorted = (l) => [...(l || [])].sort();
// Las opciones como texto: WordPress devuelve un entero saneado en la misma petición y texto al leerlo de la base.
const text = (o) => Object.fromEntries(Object.entries(o || {}).map(([k, v]) => [k, v === null ? null : String(v)]));

// La página como la ve un visitante y todo el CSS que carga (hojas de uploads/elementor/css y <style> en línea).
async function pageCss(id) {
  const res = await request('GET', `/?page_id=${id}`);
  const decode = (u) => u.replace(/&#038;|&amp;/g, '&');
  const links = [...res.text.matchAll(/<link\b[^>]*\bhref=['"]([^'"]+)['"][^>]*>/g)].map((m) => decode(m[1])).filter((u) => /\/wp-content\/uploads\/elementor\/css\/[^?]+\.css/.test(u));
  let css = [...res.text.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)].map((m) => m[1]).join('\n');
  for (const u of links) { const url = new URL(u, `http://${host}`); const r = await request('GET', url.pathname + url.search); css += `\n${r.status === 200 ? r.text : ''}`; }
  return { status: res.status, css, links };
}

let wp = '';
async function main() {
  const ping = await probe('ping');
  wp = ping.wp;
  log(`== WordPress ${ping.wp} · PHP ${ping.php} · Abilities API ${ping.abilities}`);
  await probe('users');
  const mcp = new Client();
  await mcp.init();
  const tools = await mcp.tools();
  const tool = tools.find((t) => /update-page-settings$/.test(t.name));
  log('== S0 definición');
  check('S0.1', tool && /every other saved setting is kept/.test(tool.description) && /null is deleted|set to null is deleted/.test(tool.description) && /__globals__/.test(tool.description), 'tools/list: update-page-settings dice que combina, que null borra y cómo va __globals__', tool?.description);
  check('S0.2', /a key with null is deleted/.test(tool?.inputSchema?.properties?.settings?.description || ''), 'el esquema de settings documenta el borrado con null', JSON.stringify(tool?.inputSchema?.properties?.settings));

  log('== S1 kit completo, sólo custom_css');
  let seed = await probe('seed');
  const kit = seed.kit;
  const page = seed.page;
  const kitSeed = seed.kit_meta;
  const before = await pageCss(page);
  check('S1.0', /--e-global-color-primary:\s*#1A2B3C/i.test(before.css) && /--e-global-typography-primary-font-family:\s*"Montserrat"/.test(before.css), 'antes: la página carga el CSS del kit con sus variables', before.links.join(', '));
  let r = await mcp.call('update-page-settings', { post_id: kit, settings: { custom_css: '.nuevo-del-mcp{color:blue}' } });
  check('S1.1', !r.isError && r.data.success === true, 'update-page-settings responde success', r.text);
  let st = await probe('state');
  check('S1.2', st.kit_meta?.custom_css === '.nuevo-del-mcp{color:blue}', 'custom_css del kit cambió', JSON.stringify(st.kit_meta?.custom_css));
  const lost = diffKeys(without(kitSeed, ['custom_css']), without(st.kit_meta, ['custom_css']));
  check('S1.3', lost.length === 0, 'colores, tipografías, __globals__, botones, formularios, puntos de corte, identidad y WooCommerce del kit quedan intactos', `cambiaron: ${lost.join(', ')}`);
  check('S1.4', same(r.data.changed_keys, ['custom_css']) && same(r.data.removed_keys, []), 'la respuesta dice changed_keys ["custom_css"]', JSON.stringify({ changed: r.data.changed_keys, removed: r.data.removed_keys }));
  const after = await pageCss(page);
  check('S1.5', /--e-global-color-primary:\s*#1A2B3C/i.test(after.css) && /--e-global-color-accent:\s*#FF6600/i.test(after.css) && /--e-global-color-a1b2c3d:\s*#00AA88/i.test(after.css), 'el CSS del kit regenerado conserva --e-global-color-*', after.links.join(', '));
  check('S1.6', /--e-global-typography-primary-font-family:\s*"Montserrat"/.test(after.css) && /--e-global-typography-f0e1d2c-font-family:\s*"Playfair Display"/.test(after.css), 'el CSS del kit regenerado conserva --e-global-typography-*', after.links.join(', '));
  check('S1.7', after.css.includes('.nuevo-del-mcp{color:blue}') && !after.css.includes('.viejo{color:red}'), 'la página carga el CSS nuevo del kit', '');

  log('== S2 un solo global');
  seed = await probe('seed');
  check('S2.0', same(seed.kit_meta, kitSeed), 'la siembra deja el kit igual que en S1', diffKeys(kitSeed, seed.kit_meta).join(', '));
  r = await mcp.call('update-page-settings', { post_id: kit, settings: { __globals__: { button_background_color: 'globals/colors?id=primary' } } });
  st = await probe('state');
  check('S2.1', same(st.kit_meta?.__globals__, { button_text_color: 'globals/colors?id=secondary', button_background_color: 'globals/colors?id=primary', body_color: 'globals/colors?id=text' }), 'un global cambia y los otros quedan', JSON.stringify(st.kit_meta?.__globals__));
  check('S2.2', diffKeys(without(kitSeed, ['__globals__']), without(st.kit_meta, ['__globals__'])).length === 0, 'el resto del kit queda intacto', diffKeys(kitSeed, st.kit_meta).join(', '));
  check('S2.3', same(r.data.changed_keys, ['__globals__.button_background_color']), 'la respuesta dice __globals__.button_background_color', JSON.stringify(r.data.changed_keys));

  log('== S3 página con diseño, fondo y CSS');
  seed = await probe('seed');
  const pageSeed = seed.page_meta;
  r = await mcp.call('update-page-settings', { post_id: page, settings: { hide_title: 'yes' } });
  st = await probe('state');
  check('S3.1', st.page_meta?.hide_title === 'yes' && diffKeys(without(pageSeed, ['hide_title']), without(st.page_meta, ['hide_title'])).length === 0, 'hide_title cambia; relleno, fondo, CSS y __globals__ quedan', JSON.stringify(st.page_meta));
  const pcss = await pageCss(page);
  check('S3.2', /content:\s*"azul"/.test(pcss.css) && /padding:\s*40px 0px 40px 0px/.test(pcss.css), 'la página sigue cargando su CSS propio y su relleno', pcss.links.join(', '));
  check('S3.3', same(r.data.changed_keys, ['hide_title']), 'la respuesta dice hide_title', JSON.stringify(r.data.changed_keys));

  log('== S4 borrar con null');
  seed = await probe('seed');
  r = await mcp.call('update-page-settings', { post_id: page, settings: { custom_css: null } });
  st = await probe('state');
  check('S4.1', st.page_meta && !('custom_css' in st.page_meta) && diffKeys(without(pageSeed, ['custom_css']), st.page_meta).length === 0, 'custom_css con null se borra y lo demás queda', JSON.stringify(st.page_meta));
  check('S4.2', same(r.data.removed_keys, ['custom_css']), 'removed_keys ["custom_css"]', JSON.stringify(r.data));
  r = await mcp.call('update-page-settings', { post_id: kit, settings: { __globals__: { button_text_color: null } } });
  st = await probe('state');
  check('S4.3', same(st.kit_meta?.__globals__, { button_background_color: 'globals/colors?id=accent', body_color: 'globals/colors?id=text' }) && same(st.kit_meta?.system_colors, kitSeed.system_colors), 'un global con null se desvincula y los otros y el kit quedan', JSON.stringify(st.kit_meta?.__globals__));
  check('S4.4', same(r.data.removed_keys, ['__globals__.button_text_color']), 'removed_keys ["__globals__.button_text_color"]', JSON.stringify(r.data));

  log('== S5 opciones de WordPress y WooCommerce');
  seed = await probe('seed');
  const options = text(seed.options);
  check('S5.0', seed.woo_tab === true, 'WooCommerce activo: la pestaña de WooCommerce del kit corre su on_save', JSON.stringify(seed.woo_tab));
  r = await mcp.call('update-page-settings', { post_id: kit, settings: { post_status: 'publish', custom_css: '.x{}' } });
  st = await probe('state');
  check('S5.1', !r.isError && same(text(st.options), options) && Number(st.logo) === 42, 'con post_status publish y sólo custom_css: nombre, descripción, ícono, logo y las seis páginas de WooCommerce quedan', JSON.stringify({ antes: options, despues: st.options, logo: st.logo }));
  check('S5.2', diffKeys(without(seed.kit_meta, ['custom_css']), without(st.kit_meta, ['custom_css'])).length === 0, 'y el kit conserva todo lo demás', diffKeys(seed.kit_meta, st.kit_meta).join(', '));
  seed = await probe('seed');
  r = await mcp.call('update-page-settings', { post_id: kit, settings: { post_status: 'publish', site_name: 'Nombre nuevo del MCP' } });
  st = await probe('state');
  check('S5.3', st.options.blogname === 'Nombre nuevo del MCP' && same(without(text(st.options), ['blogname']), without(options, ['blogname'])) && Number(st.logo) === 42, 'lo que sí viene (site_name) se escribe en blogname, y nada más', JSON.stringify(st.options));

  log('== S6 autoguardado pendiente de una publicada');
  seed = await probe('seed');
  r = await mcp.call('build-composition', { post_id: page, mode: 'append', xml_structure: '<e-heading configuration-id="Pendiente"></e-heading>', element_config: { Pendiente: { title: 'Cambio pendiente del MCP' } } });
  st = await probe('state');
  check('S6.0', !r.isError && st.autosave > 0 && !st.page_data.includes('Cambio pendiente del MCP'), 'build-composition deja el cambio en el autoguardado de la publicada', `${r.text.slice(0, 300)} · autoguardado ${st.autosave}`);
  const pendingId = st.autosave;
  r = await mcp.call('update-page-settings', { post_id: page, settings: { hide_title: 'yes' } });
  st = await probe('state');
  check('S6.1', !r.isError && st.autosave === pendingId && st.auto_meta?.hide_title === 'yes' && st.page_meta?.hide_title === 'yes', 'update-page-settings no borra el autoguardado y le pasa hide_title', JSON.stringify({ autosave: st.autosave, auto: st.auto_meta?.hide_title, page: st.page_meta?.hide_title }));
  check('S6.1b', st.auto_time > st.page_time, 'el autoguardado sigue siendo más nuevo que la página (el editor lo ofrece)', `${st.auto_time} frente a ${st.page_time}`);
  r = await mcp.call('publish-document', { post_id: page });
  st = await probe('state');
  check('S6.2', !r.isError && st.page_data.includes('Cambio pendiente del MCP') && st.page_meta?.hide_title === 'yes' && st.page_meta?.custom_css === seed.page_meta.custom_css, 'publish-document publica el cambio pendiente y conserva hide_title y el CSS de la página', JSON.stringify({ r: r.text.slice(0, 200), hide: st.page_meta?.hide_title }));
}

try {
  await main();
} catch (e) {
  check('excepcion', false, 'el ensayo terminó con una excepción', e.stack || String(e));
}
writeFileSync(`${dir}/result.json`, JSON.stringify({ wp, checks: results.length, failures, failed: results.filter((x) => !x.ok).map((x) => x.id), results }, null, 1));
log(failures ? `== ${failures} fallas de ${results.length}` : `== todo bien (${results.length})`);
process.exit(failures ? 1 : 0);
