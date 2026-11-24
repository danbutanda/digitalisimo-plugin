// Ensayo de punta a punta de la plantilla de página (`_wp_page_template`) de DIGITALÍSIMO Elements, contra el
// Playground que levantó elements-page-template.sh: JSON-RPC real contra /wp-json/elementor/mcp y la REST de WordPress
// (/wp/v2/pages/<id>) con una contraseña de aplicación, y la sonda (?digi_pt=…) para crear páginas y leer su estado
// directo de la base. Regla del dueño (2026-11-24): toda actualización que no menciona la plantilla la conserva.
//   T1  MCP update-page-settings con post_title                T2  MCP update-page-settings con post_name (slug)
//   T3  REST con slug                                          T4  REST con title
//   T5  REST con slug sobre una publicada con un cambio pendiente del MCP, y publish-document
//   T6  la plantilla que se pide con update-page-settings mientras hay un autoguardado del MCP sobrevive a publish-document
//   T7  borrador con lienzo: build-composition, título por el MCP y publish-document
//   T8  MCP update-page-settings con post_status publish y post_title
//   T9  la página publicada sale con el lienzo (clase page-template-elementor_canvas)
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
const probe = async (step, extra = '') => {
  const res = await request('GET', `/?digi_pt=${step}&token=${token}${extra}`);
  if (res.status !== 200 || !res.json) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
};
const auth = () => `Basic ${Buffer.from(`admin:${secrets()['password:admin']}`).toString('base64')}`;
const rest = (id, body) => request('POST', `/?rest_route=${encodeURIComponent(`/wp/v2/pages/${id}`)}`, { headers: { Authorization: auth() }, body });

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
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-page-template-e2e', version: '1.0.0' } });
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

const CANVAS = 'elementor_canvas';
const SETTINGS = { hide_title: 'yes', background_background: 'classic', background_color: '#05070A' };
const norm = (v) => (Array.isArray(v) ? v.map(norm) : v && typeof v === 'object' ? Object.fromEntries(Object.keys(v).sort().map((k) => [k, norm(v[k])])) : v);
const same = (a, b) => JSON.stringify(norm(a)) === JSON.stringify(norm(b));
const brief = (s) => JSON.stringify({ template: s.template, title: s.title, slug: s.slug, status: s.status, settings: s.settings, autosave: s.autosave, auto_template: s.auto_template });
const append = (id, text) => mcp.call('build-composition', { post_id: id, mode: 'append', xml_structure: '<e-heading configuration-id="Nuevo"></e-heading>', element_config: { Nuevo: { title: text } } });
let mcp;
let wp = '';

async function main() {
  const ping = await probe('ping');
  wp = ping.wp;
  log(`== WordPress ${ping.wp} · PHP ${ping.php} · Abilities API ${ping.abilities}`);
  await probe('users');
  mcp = new Client();
  await mcp.init();
  await mcp.tools();

  log('== T1 update-page-settings con post_title');
  let p = await probe('page', '&tpl=elementor_canvas');
  check('T0', p.template === CANVAS && p.templates.includes(CANVAS), 'la página nueva usa el lienzo y el tema lo ofrece', brief(p));
  let r = await mcp.call('update-page-settings', { post_id: p.id, settings: { post_title: 'Título nuevo por el MCP' } });
  let s = await probe('state', `&id=${p.id}`);
  check('T1.1', !r.isError && s.title === 'Título nuevo por el MCP', 'el título cambia', `${r.text.slice(0, 300)} · ${brief(s)}`);
  check('T1.2', s.template === CANVAS, 'la plantilla sigue en el lienzo', brief(s));
  check('T1.3', same(s.settings, SETTINGS), 'los ajustes de la página quedan como estaban', brief(s));

  log('== T2 update-page-settings con post_name');
  p = await probe('page', '&tpl=elementor_canvas');
  r = await mcp.call('update-page-settings', { post_id: p.id, settings: { post_name: 'slug-nuevo-mcp' } });
  s = await probe('state', `&id=${p.id}`);
  check('T2.1', !r.isError, 'update-page-settings responde sin error', r.text.slice(0, 300));
  check('T2.2', s.template === CANVAS, 'la plantilla sigue en el lienzo', brief(s));
  check('T2.3', s.slug === 'slug-nuevo-mcp', 'el slug cambia', brief(s));
  check('T2.4', same(s.settings, SETTINGS), 'post_name no se guarda como ajuste del documento y los ajustes quedan', brief(s));

  log('== T3 REST con slug');
  p = await probe('page', '&tpl=elementor_canvas');
  r = await rest(p.id, { slug: 'slug-nuevo-rest' });
  s = await probe('state', `&id=${p.id}`);
  check('T3.1', r.status === 200 && s.slug === 'slug-nuevo-rest', 'la REST cambia el slug', `HTTP ${r.status} ${r.text.slice(0, 200)} · ${brief(s)}`);
  check('T3.2', s.template === CANVAS && r.json?.template === CANVAS, 'la plantilla sigue en el lienzo (base y respuesta)', `${JSON.stringify(r.json?.template)} · ${brief(s)}`);
  check('T3.3', same(s.settings, SETTINGS), 'los ajustes de la página quedan', brief(s));
  const t3 = p.id;

  log('== T4 REST con title');
  p = await probe('page', '&tpl=elementor_canvas');
  r = await rest(p.id, { title: 'Título nuevo por la REST' });
  s = await probe('state', `&id=${p.id}`);
  check('T4.1', r.status === 200 && s.title === 'Título nuevo por la REST', 'la REST cambia el título', `HTTP ${r.status} · ${brief(s)}`);
  check('T4.2', s.template === CANVAS && r.json?.template === CANVAS, 'la plantilla sigue en el lienzo (base y respuesta)', `${JSON.stringify(r.json?.template)} · ${brief(s)}`);

  log('== T5 REST con slug sobre un cambio pendiente del MCP, y publicar');
  p = await probe('page', '&tpl=elementor_canvas');
  r = await append(p.id, 'Pendiente T5');
  s = await probe('state', `&id=${p.id}`);
  check('T5.0', !r.isError && s.autosave > 0, 'build-composition deja el cambio en el autoguardado', `${r.text.slice(0, 200)} · ${brief(s)}`);
  r = await rest(p.id, { slug: 'slug-t5', title: 'Título T5' });
  s = await probe('state', `&id=${p.id}`);
  check('T5.1', r.status === 200 && s.template === CANVAS, 'tras la REST la plantilla sigue en el lienzo', brief(s));
  r = await mcp.call('publish-document', { post_id: p.id });
  s = await probe('state', `&id=${p.id}`);
  check('T5.2', !r.isError && s.data.includes('Pendiente T5'), 'publish-document publica el cambio pendiente', `${r.text.slice(0, 200)} · ${brief(s)}`);
  check('T5.3', s.template === CANVAS && s.slug === 'slug-t5' && s.title === 'Título T5', 'y conserva el lienzo, el slug y el título nuevos', brief(s));

  log('== T6 plantilla pedida con un autoguardado del MCP pendiente');
  p = await probe('page', '&tpl=default');
  r = await append(p.id, 'Pendiente T6');
  s = await probe('state', `&id=${p.id}`);
  check('T6.0', !r.isError && s.autosave > 0, 'build-composition deja el cambio en el autoguardado (copia de la plantilla por defecto)', brief(s));
  r = await mcp.call('update-page-settings', { post_id: p.id, settings: { template: CANVAS } });
  s = await probe('state', `&id=${p.id}`);
  check('T6.1', !r.isError && s.template === CANVAS, 'update-page-settings pone el lienzo', `${r.text.slice(0, 200)} · ${brief(s)}`);
  check('T6.2', s.autosave === 0 || s.auto_template === CANVAS, 'el autoguardado pendiente recibe la plantilla pedida', brief(s));
  r = await mcp.call('publish-document', { post_id: p.id });
  s = await probe('state', `&id=${p.id}`);
  check('T6.3', !r.isError && s.template === CANVAS, 'publish-document conserva el lienzo pedido', brief(s));

  log('== T7 borrador con lienzo: reconstruir, título y publicar');
  p = await probe('page', '&tpl=elementor_canvas&status=draft');
  r = await mcp.call('build-composition', { post_id: p.id, mode: 'replace_children', xml_structure: '<e-heading configuration-id="Nuevo"></e-heading>', element_config: { Nuevo: { title: 'Reconstruida T7' } } });
  s = await probe('state', `&id=${p.id}`);
  check('T7.1', !r.isError && s.template === CANVAS, 'build-composition en un borrador conserva el lienzo', `${r.text.slice(0, 200)} · ${brief(s)}`);
  r = await mcp.call('update-page-settings', { post_id: p.id, settings: { post_title: 'Título T7' } });
  r = await mcp.call('publish-document', { post_id: p.id });
  s = await probe('state', `&id=${p.id}`);
  check('T7.2', !r.isError && s.status === 'publish' && s.template === CANVAS && s.title === 'Título T7', 'al publicar sigue en el lienzo con el título nuevo', brief(s));

  log('== T8 update-page-settings con post_status publish y post_title');
  p = await probe('page', '&tpl=elementor_canvas');
  r = await mcp.call('update-page-settings', { post_id: p.id, settings: { post_status: 'publish', post_title: 'Título T8' } });
  s = await probe('state', `&id=${p.id}`);
  check('T8.1', !r.isError && s.template === CANVAS && s.title === 'Título T8', 'la plantilla sigue en el lienzo', brief(s));

  log('== T9 la página publicada sale con el lienzo');
  const front = await request('GET', `/?page_id=${t3}`);
  check('T9.1', front.status === 200 && /<body[^>]*\bpage-template-elementor_canvas\b/.test(front.text), 'la página de T3 sale con page-template-elementor_canvas', `HTTP ${front.status} ${(front.text.match(/<body[^>]*>/) || [''])[0]}`);
}

try {
  await main();
} catch (e) {
  check('excepcion', false, 'el ensayo terminó con una excepción', e.stack || String(e));
}
writeFileSync(`${dir}/result.json`, JSON.stringify({ wp, checks: results.length, failures, failed: results.filter((x) => !x.ok).map((x) => x.id), results }, null, 1));
log(failures ? `== ${failures} fallas de ${results.length}` : `== todo bien (${results.length})`);
process.exit(failures ? 1 : 0);
