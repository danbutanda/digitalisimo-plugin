// Comprobaciones HTTP del ensayo keep-access (backups-keep-access.sh), el caso del /wp de digitalisimo.mx del
// 2026-11-12: en un WordPress individual servido en /wp (prefijo wpkx_, «admin» con su contraseña de aplicación) se sube
// por /uploads, en fragmentos, el sitio 1 extraído de la red y se restaura por REST con addresses=current, siguiendo el
// trabajo hasta el final. Después: la contraseña de aplicación de «admin» vale en la REST y en el MCP de Elements, su
// cookie del navegador sigue valiendo, «digitalisimo» entra con su contraseña y con la suya de aplicación, wp-login.php
// responde y el informe dice la URL de acceso. Escribe cada resultado en <dir>/result.txt («ok · …» o «FALLA · …»).
// Uso: node check.mjs <puerto> <dir-destino> <dir-red> <etiqueta>
import http from 'node:http';
import { createHash } from 'node:crypto';
import { appendFileSync, readFileSync, statSync, openSync, readSync, closeSync } from 'node:fs';

const [port, dir, redDir, label = 'nuevo'] = process.argv.slice(2);
const base = '/wp';
const host = `127.0.0.1:${port}`;
const setup = JSON.parse(readFileSync(`${dir}/probe/setup.json`, 'utf8'));
const secrets = JSON.parse(readFileSync(`${redDir}/probe/secrets.json`, 'utf8'));
const packageName = readFileSync(`${dir}/probe/package.txt`, 'utf8').trim();
const packagePath = `${dir}/package/${packageName}`;
// Lo que nunca puede aparecer en una respuesta, un informe o un registro: las contraseñas de prueba.
const forbidden = [setup.password, secrets.digitalisimo_app, secrets.digitalisimo_password].filter(Boolean);
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 600)}` : ''}`); }
  return ok;
};
const seen = [];
function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : (Buffer.isBuffer(body) || typeof body === 'string' ? body : JSON.stringify(body));
    const all = { Host: host, ...headers };
    if (data !== undefined) all['Content-Length'] = Buffer.byteLength(data);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 900_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no es JSON */ }
        seen.push(text);
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const basic = (login, password) => ({ Authorization: `Basic ${Buffer.from(`${login}:${password}`).toString('base64')}` });
const route = (r, query = '') => `${base}/?rest_route=${encodeURIComponent(r)}${query}`;
const api = (method, r, { headers = {}, body, query = '' } = {}) => request(method, route(`/digitalisimo-backups/v1${r}`, query), { headers: body === undefined || Buffer.isBuffer(body) ? headers : { 'Content-Type': 'application/json', ...headers }, body });
const admin = basic('admin', setup.password);
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
const cookieOf = (res) => [].concat(res.headers['set-cookie'] || []).map((value) => value.split(';')[0]).join('; ');
async function login(user, password) {
  return request('POST', `${base}/wp-login.php`, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
    body: `log=${encodeURIComponent(user)}&pwd=${encodeURIComponent(password)}&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent(`${base}/wp-admin/`)}`,
  });
}
async function mcp(auth) {
  const headers = { ...auth, Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18', 'Content-Type': 'application/json' };
  const init = await request('POST', route('/elementor/mcp'), { headers, body: { jsonrpc: '2.0', id: 1, method: 'initialize', params: { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-keep-access', version: '1.0.0' } } } });
  const session = init.headers['mcp-session-id'] || '';
  if (session) headers['Mcp-Session-Id'] = session;
  await request('POST', route('/elementor/mcp'), { headers, body: { jsonrpc: '2.0', method: 'notifications/initialized' } });
  const tools = await request('POST', route('/elementor/mcp'), { headers, body: { jsonrpc: '2.0', id: 2, method: 'tools/list', params: {} } });
  return { init, tools };
}

const started = Date.now();
log(`== ${label}: destino ${base} en el puerto ${port}, paquete ${packageName}`);
// Antes: «admin» entra y su contraseña de aplicación vale; su navegador queda con una sesión.
const before = await login('admin', 'password');
const browser = cookieOf(before);
check(before.status === 302 && /wordpress_logged_in_/.test(browser), 'antes: «admin» inicia sesión en /wp (la cookie del navegador que debe seguir valiendo)', before.status);
let me = await request('GET', route('/wp/v2/users/me'), { headers: admin });
check(me.status === 200 && me.json?.slug === 'admin', 'antes: la contraseña de aplicación de «admin» vale en la REST', me.text.slice(0, 200));

// 1. Subida por /uploads en fragmentos (como el cliente de la pantalla, por la REST).
const size = statSync(packagePath).size;
const sha256 = createHash('sha256').update(readFileSync(packagePath)).digest('hex');
let res = await api('POST', '/uploads', { headers: admin, body: { name: packageName, size, sha256 } });
check(res.status === 201 && res.json?.id, 'POST /uploads empieza la subida', res.text.slice(0, 300));
const upload = res.json || {};
const chunk = Math.max(65536, Number(upload.chunk_size) || 1048576);
const fd = openSync(packagePath, 'r');
let offset = 0;
let chunks = 0;
while (offset < size) {
  const length = Math.min(chunk, size - offset);
  const buffer = Buffer.alloc(length);
  readSync(fd, buffer, 0, length, offset);
  res = await api('POST', `/uploads/${upload.id}/chunk`, { headers: { ...admin, 'Content-Type': 'application/octet-stream' }, body: buffer, query: `&offset=${offset}` });
  if (res.status !== 200) { check(false, `fragmento en ${offset}`, res.text.slice(0, 300)); break; }
  offset = Number(res.json.received);
  chunks++;
}
closeSync(fd);
check(offset === size, `subida en ${chunks} fragmentos de ${(chunk / 1048576).toFixed(1)} MB (${(size / 1048576).toFixed(1)} MB)`);
res = await api('POST', `/uploads/${upload.id}/finish`, { headers: admin, body: {} });
for (let i = 0; i < 200 && res.status === 202; i++) { await sleep(3000); res = await api('GET', `/uploads/${upload.id}`, { headers: admin }); if (res.json?.phase === 'done') { res = { status: 200, json: res.json.result, text: res.text }; break; } }
const uploaded = res.json || {};
check(res.status === 200 && uploaded.file, 'POST /uploads/<id>/finish comprueba el paquete', res.text.slice(0, 400));
const list = await api('GET', '/uploads', { headers: admin });
const mine = (list.json || []).find((item) => item.id === upload.id) || {};
check(mine.phase === 'done' && mine.received === size && mine.result?.verified === true && mine.result?.compatible === true && mine.result?.confirmation === 'RESTAURAR SIN FIRMA', 'GET /uploads: phase=done, received=size, verified, compatible y «RESTAURAR SIN FIRMA» (lo que la barra debe reflejar)', JSON.stringify(mine).slice(0, 400));

// 2. Restaurar por REST con addresses=current, como en el incidente, y seguir el trabajo hasta el final.
const restoreStarted = Date.now();
res = await api('POST', `/backups/${uploaded.file}/restore`, { headers: admin, body: { confirm: 'RESTAURAR SIN FIRMA', addresses: 'current' } });
check([200, 202].includes(res.status) && (res.json?.job?.id || res.json?.restored), `POST restore empieza la restauración (${res.status})`, res.text.slice(0, 400));
const follow = res.json?.follow || null;
const jobId = follow?.job || res.json?.job?.id || '';
check(follow && /^[0-9a-f]{48}$/.test(follow.token || '') && follow.header === 'X-Digitalisimo-Backups-Job-Token', 'la respuesta entrega la ficha de lectura del trabajo', JSON.stringify(follow));
const readerCodes = {};
const adminCodes = {};
let final = res.json?.restored ? { status: 'success', restore: { result: res.json } } : null;
let maintenanceSeen = false;
for (let i = 0; i < 600 && !final && jobId; i++) {
  if (follow) {
    const r = await api('GET', `/jobs/${jobId}`, { headers: { [follow.header]: follow.token } });
    readerCodes[r.status] = (readerCodes[r.status] || 0) + 1;
    if (r.status === 200 && r.json?.terminal) final = r.json;
  }
  if (!final && i % 5 === 0) {
    const r = await api('GET', `/jobs/${jobId}`, { headers: admin });
    adminCodes[r.status] = (adminCodes[r.status] || 0) + 1;
    if (r.status === 503) maintenanceSeen = true;
    if (r.status === 200 && r.json?.terminal) final = r.json;
    // Sin ficha (el código anterior) y con «admin» ya rechazado: si el sitio salió del mantenimiento, terminó.
    if (!follow && r.status === 401 && (await request('GET', `${base}/`)).status === 200) break;
  }
  if (final) break;
  await request('GET', `${base}/wp-cron.php?doing_wp_cron=${Date.now()}`);
  await sleep(2000);
}
log(`· respuestas al seguirlo: con la ficha ${JSON.stringify(readerCodes)}; con la contraseña de aplicación de «admin» ${JSON.stringify(adminCodes)}${maintenanceSeen ? ' (503 de mantenimiento mientras dura, como se documenta)' : ''}`);
check(follow && final && Object.keys(readerCodes).every((code) => code === '200'), 'con la ficha, el estado se lee en cada consulta hasta el final (sin 401 ni 403, también en mantenimiento y tras cambiar las tablas de usuarios)', JSON.stringify(readerCodes));
check(final?.status === 'success', `la restauración terminó bien (${Math.round((Date.now() - restoreStarted) / 1000)} s)`, JSON.stringify(final || {}).slice(0, 400));
// Al terminar, también con la credencial de «admin».
res = await api('GET', `/jobs/${jobId}`, { headers: admin });
const result = res.json?.restore?.result || final?.restore?.result || {};
const access = result.access || {};
check(res.status === 200 && res.json?.status === 'success', 'al terminar, GET /jobs/<id> con la contraseña de aplicación de «admin» lee su estado final', `${res.status} ${res.text.slice(0, 300)}`);
check(access.kept === true && access.user === 'admin', 'el informe dice que se conservó el acceso de «admin»', JSON.stringify(access).slice(0, 400));
check(access.login_url === `http://${host}${base}/wp-login.php` && String(result.notice || '').startsWith(`Cómo entrar ahora: http://${host}${base}/wp-login.php`), 'el informe dice la URL de acceso efectiva (wp-login.php de /wp) y empieza por «Cómo entrar ahora»', `${access.login_url} · ${String(result.notice || '').slice(0, 200)}`);
check((access.admins || []).includes('digitalisimo') && (access.admins || []).includes('admin'), 'el informe dice quién puede entrar', JSON.stringify(access.admins));
check(follow && (await api('GET', `/jobs/${jobId}`, { query: `&job_token=${follow.token}` })).status === 400, 'la ficha en la URL se rechaza (400)');

// 3. Después de restaurar.
me = await request('GET', route('/wp/v2/users/me'), { headers: admin });
check(me.status === 200 && me.json?.slug === 'admin', 'la contraseña de aplicación de «admin» vale en la REST', `${me.status} ${me.text.slice(0, 200)}`);
const tools = await mcp(admin);
check(tools.init.status === 200 && tools.init.json?.result?.serverInfo && tools.tools.status === 200 && Array.isArray(tools.tools.json?.result?.tools), 'la contraseña de aplicación de «admin» vale en el MCP de Elements (initialize y tools/list)', `${tools.init.status} ${tools.init.text.slice(0, 200)} · ${tools.tools.status}`);
const red = basic('digitalisimo', secrets.digitalisimo_app);
me = await request('GET', route('/wp/v2/users/me'), { headers: red });
check(me.status === 200 && me.json?.slug === 'digitalisimo', 'la contraseña de aplicación de «digitalisimo» (viajó en el paquete) vale en /wp', `${me.status} ${me.text.slice(0, 200)}`);
const loginPage = await request('GET', `${base}/wp-login.php`);
check(loginPage.status === 200 && /user_login/.test(loginPage.text), '/wp/wp-login.php responde con el formulario (no 404)', loginPage.status);
const redLogin = await login('digitalisimo', secrets.digitalisimo_password);
check(redLogin.status === 302 && /wordpress_logged_in_/.test(cookieOf(redLogin)), '«digitalisimo» entra con su contraseña por wp-login.php', redLogin.status);
const adminLogin = await login('admin', 'password');
check(adminLogin.status === 302 && /wordpress_logged_in_/.test(cookieOf(adminLogin)), '«admin» entra con su contraseña de siempre', adminLogin.status);
const dash = await request('GET', `${base}/wp-admin/`, { headers: { Cookie: browser } });
check(dash.status === 200 && /wp-admin-bar|adminmenu/.test(dash.text), 'la cookie del navegador de «admin» de antes de restaurar sigue valiendo en el escritorio', `${dash.status} ${dash.headers.location || ''}`);
const pages = await request('GET', route('/wp/v2/pages', '&per_page=50'));
check(pages.status === 200 && (pages.json || []).some((p) => /Portada de la red/.test(p.title?.rendered || '')) && (pages.json || []).every((p) => String(p.link || '').startsWith(`http://${host}${base}/`)), 'el sitio es el de la red con sus enlaces en /wp', pages.text.slice(0, 200));
check(!seen.some((text) => forbidden.some((secret) => text.includes(secret))), 'ninguna respuesta lleva una contraseña de prueba');
log(`TERMINADO ${label}: ${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - started) / 1000)} s`);
process.exit(failures ? 1 : 0);
