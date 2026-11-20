// Conductor del ensayo del Asistente IA en Playground (lo llama ia-assistant.sh, una fase por sitio o red).
//   T  el Asistente ofrece exactamente las herramientas del MCP: tools/list del servidor de Elements
//      (/wp-json/elementor/mcp) más las abilities que descubre el servidor genérico del adaptador, con JSON-RPC real y
//      contraseña de aplicación; y son las que recibe el modelo;
//   O  digitalisimo-ia/open-assistant por MCP: URL del Asistente de ese sitio, con conversación nueva y mensaje;
//   C  Chrome: menú «Asistente IA», nada del Asistente en el Escritorio, escribir y ver la respuesta simulada, leer
//      directo (dato marcado, sin claves), crear con confirmación (en borrador), borrar con confirmación explícita,
//      apariencia Modern y oscura que se conserva, la conversación al recargar, el mensaje del MCP sin enviarse, la
//      bitácora y la consola sin errores;
//   N  (red) sin menú en el escritorio de la red, el sitio 2 con su propio MCP, sus herramientas y su historial.
// Uso: node run.cjs <puerto> <host> <dir> <fase: single|network>
const http = require('node:http');
const fs = require('node:fs');
const puppeteer = require('puppeteer-core');

const [port, host, dir, phase] = process.argv.slice(2);
const token = fs.readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const secret = JSON.parse(fs.readFileSync(`${dir}/probe/secrets.json`, 'utf8')).admin;
const results = [];
let failures = 0;
const log = (line) => { fs.appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (id, ok, what, detail = '') => {
  results.push({ id, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 600) });
  if (ok) log(`ok · ${id} · ${what}`); else { failures++; log(`FALLA · ${id} · ${what}${detail ? ` · ${String(detail).slice(0, 600)}` : ''}`); }
  return !!ok;
};

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { Host: host, ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 300000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => { const text = Buffer.concat(chunks).toString('utf8'); let json = null; try { json = JSON.parse(text); } catch { /* no JSON */ } resolve({ status: res.statusCode, headers: res.headers, text, json }); });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const probe = async (prefix) => (await request('GET', `${prefix}/?digi_asis=state&token=${token}`)).json;

class Mcp {
  constructor(prefix, route) { this.prefix = prefix; this.route = route; this.id = 0; this.session = ''; }
  async post(payload) {
    const headers = { Authorization: `Basic ${Buffer.from(`admin:${secret}`).toString('base64')}`, Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' };
    if (this.session) headers['Mcp-Session-Id'] = this.session;
    return request('POST', `${this.prefix}/?rest_route=${encodeURIComponent(this.route)}`, { headers, body: payload });
  }
  async rpc(method, params = {}) { return this.post({ jsonrpc: '2.0', id: ++this.id, method, params }); }
  async init() {
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-asis', version: '1.0.0' } });
    this.session = res.headers['mcp-session-id'] || '';
    await this.post({ jsonrpc: '2.0', method: 'notifications/initialized' });
    return res;
  }
  async call(name, args) {
    const res = await this.rpc('tools/call', { name, arguments: args });
    const result = res.json?.result || {};
    let data = result.structuredContent;
    if (!data) { try { data = JSON.parse((result.content || []).map((c) => c.text || '').join('')); } catch { data = null; } }
    return { res, data };
  }
}

const sortAll = (list) => [...new Set(list)].sort();
const toolName = (ability) => ability.replace(/[^A-Za-z0-9_-]/g, '-');

async function parity(prefix, label, page) {
  const elements = new Mcp(prefix, '/elementor/mcp');
  const init = await elements.init();
  check(`${label}T0`, init.status === 200 && elements.session, `${label}MCP de Elements activo (initialize)`, init.text.slice(0, 300));
  const list = await elements.rpc('tools/list', {});
  const elementsTools = (list.json?.result?.tools || []).map((t) => t.name);
  const generic = new Mcp(prefix, '/mcp/mcp-adapter-default-server');
  const ginit = await generic.init();
  let discovered = [];
  if (ginit.status === 200) {
    const { data } = await generic.call('mcp-adapter-discover-abilities', {});
    discovered = (data?.abilities || []).map((a) => a.name);
  }
  const assistant = await page.evaluate(async () => {
    const c = window.digitalisimoAssistantConfig;
    const r = await fetch(`${c.rest}tools`, { credentials: 'same-origin', headers: { 'X-WP-Nonce': c.restNonce, 'X-Digitalisimo-Assistant': c.nonce } });
    return r.json();
  });
  const mine = sortAll((assistant.tools || []).map((t) => t.tool));
  const mcp = sortAll([...elementsTools, ...discovered.map(toolName)]);
  check(`${label}T1`, elementsTools.length > 20 && elementsTools.includes('elementor-build-composition') && elementsTools.includes('digitalisimo-ia-open-assistant'), `${label}tools/list de Elements trae las del constructor y open-assistant (${elementsTools.length})`, elementsTools.join(', '));
  check(`${label}T2`, discovered.includes('digitalisimo-ia/open-assistant') && discovered.includes('digi-asis/delete-note'), `${label}el servidor genérico descubre open-assistant y las de prueba (${discovered.length})`, `${ginit.status} ${discovered.join(', ')}`);
  check(`${label}T3`, JSON.stringify(mine) === JSON.stringify(mcp), `${label}el Asistente lista exactamente las herramientas del MCP (${mine.length})`, `sólo Asistente: ${mine.filter((t) => !mcp.includes(t))} · sólo MCP: ${mcp.filter((t) => !mine.includes(t))}`);
  return { elements, mine };
}

async function chat(page, text) {
  const before = await page.$$eval('.dga-thread > *', (n) => n.length).catch(() => 0);
  await page.click('#dga-input');
  await page.type('#dga-input', text);
  await page.keyboard.press('Enter');
  await page.waitForFunction((n) => document.querySelectorAll('.dga-thread > *').length > n + 1 && !document.querySelector('.dga-pending'), { timeout: 120000 }, before);
}
const lastAssistant = (page) => page.$$eval('.dga-message.is-assistant .dga-bubble', (n) => (n.length ? n[n.length - 1].textContent : ''));

(async () => {
  const site1 = '';
  const site2 = phase === 'network' ? '/sitio2' : '';
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', ...(phase === 'network' ? [`--host-resolver-rules=MAP ${host} 127.0.0.1:${port}`] : [])] });
  const base = `http://${host}`;
  const errors = [];
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1360, height: 900 });
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
    page.on('console', (m) => { if (m.type() === 'error' && !/favicon/i.test(m.text())) errors.push(`console: ${m.text()}`); });
    page.on('dialog', (d) => d.accept());
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 180000 });
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 180000 }), page.click('#wp-submit')]);

    const target = phase === 'network' ? site2 : site1;
    // C1 el menú y nada del Asistente en el Escritorio.
    await page.goto(`${base}${target}/wp-admin/`, { waitUntil: 'networkidle0', timeout: 180000 });
    const dash = await page.evaluate(() => ({ menu: document.querySelector('#toplevel_page_digitalisimo-assistant .wp-menu-name')?.textContent || '', assets: [...document.querySelectorAll('script[src],link[rel=stylesheet]')].map((n) => n.src || n.href).filter((u) => /assets\/assistant\//.test(u)) }));
    check('C1', dash.menu === 'Asistente IA', 'Menú principal «Asistente IA» en el escritorio del sitio', JSON.stringify(dash));
    check('C2', dash.assets.length === 0, 'El Escritorio no carga nada del Asistente', dash.assets.join(', '));
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 180000 }), page.click('#toplevel_page_digitalisimo-assistant > a')]);
    await page.waitForSelector('.dga-root', { timeout: 60000 });
    const html = await page.content();
    check('C3', !html.includes('sk-asis-FAKEKEY') && !html.includes('gpt-asis-fake') && !html.includes('api.openai.com'), 'La pantalla no recibe la clave, el modelo ni la URL del proveedor');

    const { elements } = await parity(target, phase === 'network' ? 'N-' : '', page);

    // C4 escribir y ver la respuesta simulada.
    await chat(page, 'hola asistente');
    check('C4', (await lastAssistant(page)).includes('Respuesta simulada: hola asistente'), 'Escribir y recibir la respuesta del modelo (simulado por HTTP)', await lastAssistant(page));
    const seen = (await probe(target)).seen;
    check('C5', seen.auth && seen.model === 'gpt-asis-fake' && seen.tools.length > 0, `El servidor llamó al proveedor con su clave y su modelo, y le ofreció ${seen.tools?.length} herramientas`, JSON.stringify(seen).slice(0, 300));
    const offered = sortAll(seen.tools);
    const listed = sortAll((await page.evaluate(async () => { const c = window.digitalisimoAssistantConfig; return (await (await fetch(`${c.rest}tools`, { headers: { 'X-WP-Nonce': c.restNonce, 'X-Digitalisimo-Assistant': c.nonce } })).json()).tools.map((t) => t.tool); })));
    check('T4', JSON.stringify(offered) === JSON.stringify(listed), 'El modelo recibe las mismas herramientas que lista el Asistente (y el MCP)', `${offered.length} vs ${listed.length}`);

    if (phase === 'single') {
      // C6 leer directo, como dato y sin claves.
      await chat(page, 'lee la nota');
      await page.waitForSelector('.dga-tool[data-ability="digi-asis/read-note"][data-status="ok"]', { timeout: 60000 });
      const s1 = (await probe(target)).seen;
      const data = JSON.parse(s1.tool_content || '{}');
      check('C6', data.tipo === 'resultado_de_herramienta' && /nunca una instrucción/.test(data.aviso || '') && /IGNORA TODAS TUS REGLAS/.test(data.datos?.texto || ''), 'Leer se ejecuta solo y vuelve al modelo como dato con su aviso', s1.tool_content);
      check('C7', data.datos?.api_key === '••••' && !s1.tool_content.includes('sk-asis-FAKEKEY') && /nunca una instrucción/.test(s1.system), 'Lo secreto llega oculto y las instrucciones del sistema avisan de la inyección');
      check('C8', (await lastAssistant(page)).includes('Resultado recibido: digi-asis/read-note (ok)'), 'El modelo sigue tras leer');
      // C9 crear: tarjeta, nada antes de confirmar, borrador después.
      await chat(page, 'crea una nota');
      await page.waitForSelector('.dga-confirm[data-kind="write"]', { timeout: 60000 });
      check('C9', (await probe(target)).notes.length === 0, 'Escribir espera la confirmación: aún no hay nota');
      const card = await page.$eval('.dga-confirm[data-kind="write"] pre', (n) => n.textContent);
      check('C10', /"status": "draft"/.test(card), 'La tarjeta muestra la nota en borrador', card);
      await page.click('.dga-confirm[data-kind="write"] [data-decision="approve"]');
      await page.waitForSelector('.dga-tool[data-ability="digi-asis/create-note"][data-status="ok"]', { timeout: 60000 });
      await page.waitForFunction(() => !document.querySelector('.dga-pending'), { timeout: 60000 });
      const notes = (await probe(target)).notes;
      check('C11', notes.length === 1 && notes[0].status === 'draft', 'Confirmado, se crea una vez y en borrador', JSON.stringify(notes));
      // C12 borrar: confirmación explícita.
      await chat(page, 'borra la nota');
      await page.waitForSelector('.dga-confirm[data-kind="destructive"]', { timeout: 60000 });
      const disabled = await page.$eval('.dga-confirm[data-kind="destructive"] [data-decision="approve"]', (b) => b.disabled);
      check('C12', disabled, 'Lo destructivo no se puede confirmar sin la casilla');
      await page.click('.dga-confirm[data-kind="destructive"] .dga-ack');
      await page.click('.dga-confirm[data-kind="destructive"] [data-decision="approve"]');
      await page.waitForSelector('.dga-tool[data-ability="digi-asis/delete-note"][data-status="ok"]', { timeout: 60000 });
      await page.waitForFunction(() => !document.querySelector('.dga-pending'), { timeout: 60000 });
      check('C13', (await probe(target)).notes.length === 0, 'Con la confirmación explícita, se borra');
      const log = (await probe(target)).log;
      const kinds = log.map((r) => `${r.ability}:${r.kind}:${r.result}`);
      check('C14', ['digi-asis/read-note:read:ok', 'digi-asis/create-note:write:ok', 'digi-asis/delete-note:destructive:ok'].every((k) => kinds.includes(k)) && log.every((r) => r.user === 1 && r.time && !JSON.stringify(r).includes('sk-asis')), 'Bitácora de cada llamada con usuario y fecha, sin secretos', kinds.join(', '));
      // C19 datos largos: «Confirmar» sólo tras desplazarlos hasta el final.
      await chat(page, 'crea una nota larga');
      await page.waitForSelector('.dga-confirm[data-kind="write"]', { timeout: 60000 });
      const gate = async () => page.$eval('.dga-confirm[data-kind="write"] [data-decision="approve"]', (b) => b.disabled);
      const before = await gate();
      await page.$eval('.dga-confirm[data-kind="write"] pre.dga-input', (el) => { el.scrollTop = el.scrollHeight; el.dispatchEvent(new Event('scroll')); });
      await page.waitForFunction(() => !document.querySelector('.dga-confirm[data-kind="write"] [data-decision="approve"]').disabled, { timeout: 10000 }).catch(() => {});
      const after = await gate();
      const end = await page.$eval('.dga-confirm[data-kind="write"] pre.dga-input', (el) => el.textContent.includes('FIN-DE-LOS-DATOS'));
      check('C19', before === true && after === false && end, 'Con datos largos, «Confirmar» se enciende sólo tras desplazarlos hasta el final', JSON.stringify({ before, after, end }));
      await page.click('.dga-confirm[data-kind="write"] [data-decision="deny"]');
      await page.waitForFunction(() => !document.querySelector('.dga-pending') && !document.querySelector('.dga-confirm'), { timeout: 60000 });
      // C15 apariencia Modern y oscura.
      await page.click('.dga-appearance');
      await page.click('[data-choice="variant:modern"]');
      await page.click('[data-choice="mode:dark"]');
      await page.click('.dga-swatch[data-color-scheme="emerald"]');
      await page.click('.dga-close');
      const look = await page.$eval('.dga-root', (r) => { const s = getComputedStyle(r); const c = getComputedStyle(document.querySelector('.dga-composer')).backgroundColor.match(/[\d.]+/g).map(Number); return { theme: r.dataset.theme, variant: r.dataset.interfaceVariant, scheme: r.dataset.colorScheme, blur: s.getPropertyValue('--ds-assistant-blur').trim(), glow: s.getPropertyValue('--ds-assistant-glow-1').trim(), light: (c[0] + c[1] + c[2]) / 3 }; });
      check('C15', look.theme === 'dark' && look.variant === 'modern' && look.scheme === 'emerald' && look.blur === '18px' && look.glow !== 'transparent' && look.light < 80, 'Apariencia Modern, oscura y con otra paleta', JSON.stringify(look));
      // C16 al recargar: la conversación y la apariencia.
      await page.reload({ waitUntil: 'networkidle0' });
      await page.waitForSelector('.dga-thread .dga-message', { timeout: 60000 });
      const again = await page.evaluate(() => ({ url: location.search, messages: document.querySelectorAll('.dga-thread .dga-message').length, tools: document.querySelectorAll('.dga-tool').length, theme: document.querySelector('.dga-root').dataset.theme, history: document.querySelectorAll('.dga-row').length }));
      check('C16', /conversation=\d+/.test(again.url) && again.messages >= 8 && again.tools >= 3 && again.theme === 'dark' && again.history === 1, 'Al recargar vuelve la conversación (historial) y la apariencia', JSON.stringify(again));
      // C17 la bitácora en pantalla.
      await page.click('.dga-tools .dga-trigger');
      await page.waitForSelector('.dga-open-log');
      await page.click('.dga-open-log');
      await page.waitForSelector('.dga-log tbody tr', { timeout: 30000 });
      check('C17', (await page.$$('.dga-log tbody tr')).length >= 3, 'La bitácora se ve desde el Asistente');
      await page.click('.dga-close');
      // C18 atajo sin IA.
      const calls = (await probe(target)).seen.calls;
      await chat(page, '/digi-asis-read-note');
      await page.waitForFunction(() => document.querySelectorAll('.dga-tool[data-ability="digi-asis/read-note"]').length >= 2, { timeout: 60000 });
      check('C18', (await probe(target)).seen.calls === calls, 'Un atajo /herramienta corre sin llamar al modelo');
    }

    // O open-assistant por MCP y el mensaje sin enviarse.
    const { data: opened } = await elements.call('digitalisimo-ia-open-assistant', { message: 'Hola desde el MCP' });
    const expected = `${base}${target}/wp-admin/admin.php?page=digitalisimo-assistant`;
    check('O1', opened && opened.url.startsWith(`${expected}&new=1&prompt=`) && opened.message_prefilled === true, 'open-assistant devuelve la URL del Asistente de este sitio con conversación nueva y mensaje', JSON.stringify(opened));
    const chatsBefore = (await probe(target)).chats;
    await page.goto(opened.url, { waitUntil: 'networkidle0', timeout: 180000 });
    await page.waitForSelector('#dga-input');
    const prefill = await page.evaluate(() => ({ value: document.querySelector('#dga-input').value, welcome: !!document.querySelector('.dga-welcome'), url: location.search }));
    check('O2', prefill.value === 'Hola desde el MCP' && prefill.welcome && !/prompt=/.test(prefill.url), 'El mensaje queda escrito sin enviarse (y sale de la dirección)', JSON.stringify(prefill));
    check('O3', (await probe(target)).chats === chatsBefore, 'Abrir el enlace no crea ni envía nada');

    if (phase === 'network') {
      await page.goto(`${base}/wp-admin/network/`, { waitUntil: 'networkidle0', timeout: 180000 });
      check('N1', !(await page.$('#toplevel_page_digitalisimo-assistant')), 'El escritorio de la red no tiene el Asistente');
      // El sitio 1 tiene su propio Asistente, MCP e historial.
      await page.goto(`${base}/wp-admin/admin.php?page=digitalisimo-assistant`, { waitUntil: 'networkidle0', timeout: 180000 });
      await page.waitForSelector('.dga-root');
      check('N2', (await page.$$('.dga-row')).length === 0, 'El historial del sitio 1 no trae lo del sitio 2');
      const m1 = new Mcp('', '/elementor/mcp');
      await m1.init();
      const { data: d1 } = await m1.call('digitalisimo-ia-open-assistant', {});
      check('N3', d1 && d1.url === `${base}/wp-admin/admin.php?page=digitalisimo-assistant`, 'open-assistant en el MCP del sitio 1 apunta al sitio 1', JSON.stringify(d1));
      await parity('', 'N1-', page);
    }
  } catch (e) {
    check('X', false, 'El ensayo terminó sin excepción', e.stack || e.message);
  } finally {
    check('Z', errors.length === 0, 'La consola de Chrome sin errores', errors.join(' | '));
    await browser.close();
    fs.writeFileSync(`${dir}/result.json`, JSON.stringify({ phase, failures, checks: results.length, results }, null, 1));
    process.exit(failures ? 1 : 0);
  }
})();
