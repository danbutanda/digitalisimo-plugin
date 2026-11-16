// Ensayo «importar un WordPress individual en una red» con Chrome sin interfaz (puppeteer-core de PUPPETEER_PATH) contra
// la red de Playground que levantó backups-site-import.sh. Todo por la pantalla de la red, como el propietario:
//   1  «Subir un respaldo» del individual `origen.test` en la administración de la red: queda en la lista sin «No se
//      restaura aquí» y su «Restaurar» ofrece importarlo como sitio nuevo o reemplazando uno.
//   2  como sitio nuevo en /importado/: el contenido, los medios servidos desde uploads/sites/N, los usuarios que entran
//      con su contraseña (y los de la red con la suya), los roles, la autoría, el plugin activo sólo en ese sitio, el tema
//      habilitado sólo en él, sin la configuración de Backups del origen; y los sitios 1 y 2 sin cambios (huellas).
//   3  reemplazando el sitio 2: su copia previa (sólo ese sitio, bloqueada), su administrador sigue entrando y siéndolo,
//      el principal y el sitio nuevo sin cambios.
//   4  una importación con una falla inyectada justo después de fundir los usuarios: vuelve sola a la copia previa del
//      sitio 2 y los usuarios de la red quedan como estaban.
// Capturas en <out>/shots; el resultado de cada comprobación en <out>/result.txt.
// Uso: node drive.mjs <puerto> <dir> <out>
import http from 'node:http';
import { appendFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [port, dir, out] = process.argv.slice(2);
const host = 'red.test';
const shots = `${out}/shots`;
mkdirSync(shots, { recursive: true });
const store = `${dir}/store`;
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const origin = JSON.parse(readFileSync(`${dir}/probe/origen.json`, 'utf8'));
const red = JSON.parse(readFileSync(`${dir}/probe/red.json`, 'utf8'));
const zip = `${dir}/probe/origen.zip`;
const base = `http://${host}`;
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
const sha = (bytes) => createHash('sha256').update(bytes).digest('hex');
let failures = 0;
const log = (line) => { appendFileSync(`${out}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 1500)}` : ''}`); }
  return ok;
};
function request(method, path, { headers = {}, body, binary = false } = {}) {
  return new Promise((resolve) => {
    const all = { Host: host, ...headers };
    if (body !== undefined) all['Content-Length'] = Buffer.byteLength(body);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const raw = Buffer.concat(chunks);
        const text = binary ? '' : raw.toString('utf8');
        let json = null;
        try { json = binary ? null : JSON.parse(text); } catch { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, raw, text, json });
      });
    });
    req.on('error', (error) => resolve({ status: 0, raw: Buffer.alloc(0), text: String(error.message), json: null }));
    req.on('timeout', () => req.destroy(new Error('tiempo agotado')));
    if (body !== undefined) req.write(body);
    req.end();
  });
}
const probe = async (what = 'state', extra = '') => (await request('GET', `/?digi_si=${what}&token=${token}${extra}`)).json || {};
const jobs = () => (existsSync(`${store}/jobs`) ? readdirSync(`${store}/jobs`).map((id) => { try { return JSON.parse(readFileSync(`${store}/jobs/${id}/job.json`, 'utf8')); } catch { return null; } }).filter(Boolean) : []);
const terminal = (job) => job && ['success', 'failed', 'cancelled'].includes(job.status);
/** Quién entra por wp-login.php (un navegador aparte por usuario: la cookie de sesión) y abre el escritorio del sitio. */
async function loginAs(login, pass, path) {
  const form = new URLSearchParams({ log: login, pwd: pass, 'wp-submit': 'Acceder', redirect_to: `${base}${path}`, testcookie: '1' }).toString();
  const res = await request('POST', '/wp-login.php', { headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' }, body: form });
  const cookies = (res.headers['set-cookie'] || []).map((c) => c.split(';')[0]).filter((c) => /wordpress_logged_in_|wordpress_[0-9a-f]+=/.test(c));
  if (res.status !== 302 || !cookies.length) return { ok: false, status: res.status };
  const admin = await request('GET', path, { headers: { Cookie: cookies.join('; ') } });
  return { ok: admin.status === 200 && /wp-admin|adminmenu/.test(admin.text) && !/wp-login\.php/.test(String(admin.headers.location || '')), status: admin.status };
}

const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dsi-' : `${tmpdir()}/dsi-`);
const browser = await puppeteer.launch({
  userDataDir: profile,
  env: { ...process.env, TMPDIR: existsSync('/tmp') ? '/tmp' : tmpdir() },
  executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
  headless: true,
  protocolTimeout: 900_000,
  args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', '--font-render-hinting=none', `--host-resolver-rules=MAP ${host} 127.0.0.1:${port}, MAP * ~NOTFOUND`],
});
const page = await browser.newPage();
page.setDefaultTimeout(300_000);
page.setDefaultNavigationTimeout(300_000);
await page.setViewport({ width: 1280, height: 900 });
const jsErrors = [];
page.on('pageerror', (e) => jsErrors.push(String(e.message || e).slice(0, 300)));
page.on('dialog', async (d) => { await d.accept(); });
const screen = '/wp-admin/network/admin.php?page=digitalisimo-network-backups';
const cookieHeader = async () => (await page.cookies(base)).map((c) => `${c.name}=${c.value}`).join('; ');
async function shot(name, { width = 1280 } = {}) {
  if (width !== 1280) await page.setViewport({ width, height: 900 });
  await page.evaluate(() => window.scrollTo(0, 0));
  await sleep(400);
  await page.screenshot({ path: `${shots}/${name}.png`, fullPage: true });
  if (width !== 1280) await page.setViewport({ width: 1280, height: 900 });
  log(`captura · ${name}.png`);
}
async function go(path) {
  const res = await page.goto(base + path, { waitUntil: 'load' });
  await sleep(800);
  return res ? res.status() : 0;
}
async function login() {
  for (let attempt = 0; attempt < 2 && !/wp-admin/.test(page.url()); attempt++) {
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) continue;
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  }
  return check(/wp-admin/.test(page.url()), 'el superadministrador entra a la red', page.url());
}
/** Hace avanzar los trabajos como WP-Cron (y con el testigo de la restauración) hasta que terminan. */
async function drive(label, seconds = 1500) {
  const started = Date.now();
  while (Date.now() - started < seconds * 1000) {
    const open = jobs().filter((job) => !terminal(job));
    if (!open.length) break;
    const restoreCookie = (await page.cookies(base)).find((c) => c.name === 'digitalisimo_backups_restore');
    if (restoreCookie && open[0].kind === 'restore') {
      await request('POST', `/wp-admin/admin-ajax.php?digitalisimo_backups_restore=${restoreCookie.value}`, { headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: await cookieHeader() }, body: new URLSearchParams({ action: 'digitalisimo_backups_restore_tick', digitalisimo_backups_restore: restoreCookie.value }).toString() });
    }
    await request('GET', '/wp-cron.php');
    await sleep(1500);
  }
  return { open: jobs().filter((job) => !terminal(job)).length, seconds: Math.round((Date.now() - started) / 1000), label };
}
async function rowOf(file) {
  return page.evaluate((f) => {
    const row = [...document.querySelectorAll('tr.digitalisimo-backups-row')].find((tr) => tr.dataset.file === f);
    if (!row) return null;
    const restore = row.querySelector('[data-restore-toggle]');
    return { text: row.innerText, html: row.outerHTML, restore: restore ? restore.getAttribute('aria-controls') : '' };
  }, file);
}
async function upload(path) {
  await go(`${screen}&tab=tools&open=upload`);
  const input = await page.$('#digitalisimo-backups-upload input[type=file]');
  if (!check(Boolean(input), '«Subir un respaldo» de la red ofrece elegir el archivo')) return '';
  await input.uploadFile(path);
  await page.click('#digitalisimo-backups-upload button[type=submit]');
  let status = '';
  for (let i = 0; i < 400; i++) {
    await sleep(1500);
    status = await page.$eval('#digitalisimo-backups-upload .digitalisimo-backups-upload-status', (p) => p.textContent).catch(() => '');
    const busy = await page.$eval('#digitalisimo-backups-upload button[type=submit]', (b) => b.disabled).catch(() => false);
    if (!busy && status) break;
  }
  return status;
}
/** «Restaurar» de la fila con «Importar en la red»: sitio nuevo (address) o reemplazando el sitio `site`. */
async function importRow(file, { address = '', site = 0, name = 'importar' } = {}) {
  const before = new Set(jobs().map((job) => job.id));
  await go(`${screen}&restore=${encodeURIComponent(file)}`);
  const row = await rowOf(file);
  if (!check(row && row.restore, `la fila del respaldo subido ofrece «Restaurar» (${name})`, row?.text)) return null;
  const panel = `#${row.restore}`;
  const offer = await page.$eval(panel, (p) => p.innerText).catch(() => '');
  check(/Importar en la red/.test(offer) && /Como sitio nuevo de la red/.test(offer) && /Reemplazando el sitio/.test(offer), 'la confirmación ofrece importar como sitio nuevo o reemplazando uno', offer);
  if (site) {
    await page.click(`${panel} input[name=into][value=replace]`);
    await page.select(`${panel} select[name=into_site]`, String(site));
  } else {
    await page.click(`${panel} input[name=into][value=new]`);
    await page.$eval(`${panel} input[name=address]`, (i, v) => { i.value = v; }, address);
  }
  await shot(`${name}-confirmacion`);
  await page.type(`${panel} input[name=confirmation]`, 'RESTAURAR');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click(`${panel} button.button-primary`)]);
  const notice = await page.$eval('.digitalisimo-backups-result', (n) => n.textContent).catch(() => '');
  // El panel de avance de la red mientras importa (la fase «Importación en la red»).
  await sleep(1500);
  const phases = await page.$$eval('[id$="-phases"] li', (items) => items.map((li) => li.textContent)).catch(() => []);
  const run = await drive(name);
  const all = jobs().filter((j) => !before.has(j.id) && j.kind === 'restore').sort((a, b) => Number(a.queued_at || 0) - Number(b.queued_at || 0));
  return { notice, run, job: all[0] || null, rollback: all[1] || null, phases };
}
/** Qué partes de los sitios difieren (para el detalle de un fallo). */
const differs = (a, b) => { const out = []; for (const id of Object.keys(a)) for (const part of ['tables', 'files', 'plugins', 'theme']) { const x = a[id]?.[part]; const y = b[id]?.[part]; if (JSON.stringify(x) !== JSON.stringify(y)) out.push(`${id}.${part}${x && typeof x === 'object' && !Array.isArray(x) ? `(${[...new Set([...Object.keys(x), ...Object.keys(y || {})])].filter((k) => x[k] !== y?.[k]).join(',')})` : ''}`); } return out.join(' '); };
const others = (state, keep) => Object.fromEntries(Object.entries(state.sites || {}).filter(([id]) => keep.includes(Number(id))).map(([id, site]) => [id, { tables: site.tables, files: site.files, plugins: site.active_plugins, theme: site.stylesheet }]));
const network = (state, ignoreSite) => Object.fromEntries(Object.entries(state.users || {}).map(([login, u]) => [login, { email: u.email, pass: u.pass, meta: u.meta, super: u.super, roles: Object.fromEntries(Object.entries(u.roles).filter(([id]) => Number(id) !== ignoreSite)) }]));

const started = Date.now();
await login();
let before = await probe();
check(before.sites && before.sites[1] && before.sites[2] && !before.error, 'la sonda describe la red (sitios 1 y 2)', JSON.stringify(before).slice(0, 400));

// 1. Subir el respaldo del individual en la administración de la red.
const status = await upload(zip);
await shot('1-subida');
check(/Respaldo subido y verificado/.test(status) && /Ya está en la lista de respaldos/.test(status), '«Subir un respaldo» de la red deja el del individual en la lista', status);
let state = await probe();
const uploaded = (state.history || []).find((r) => r.origin === 'uploaded') || {};
check(uploaded.file && uploaded.scope === 'installation', 'su fila es la de un individual subido', JSON.stringify(state.history));
await go(screen);
let row = await rowOf(uploaded.file);
check(row && /Subido/.test(row.text) && !/No se restaura aquí/.test(row.text) && row.restore, 'la lista de la red ya no dice «No se restaura aquí» y ofrece «Restaurar»', row?.text);
await shot('1-lista');
await shot('1-lista-390', { width: 390 });

// 2. Como sitio nuevo en /importado/.
before = await probe();
let run = await importRow(uploaded.file, { address: 'importado', name: '2-sitio-nuevo' });
state = await probe();
const sites = Object.entries(state.sites || {}).find(([, s]) => s.path === '/importado/');
const id = sites ? Number(sites[0]) : 0;
const site = sites ? sites[1] : {};
check(run?.job?.status === 'success' && id > 2, `importado como sitio nuevo de la red en /importado/ (sitio ${id}, ${run?.run?.seconds} s)`, JSON.stringify({ job: run?.job?.status, error: run?.job?.error, notice: run?.notice }));
check((run?.phases || []).some((p) => /Importación en la red/.test(p)), 'el panel de avance dice «Importación en la red»', JSON.stringify(run?.phases));
check(site.blogname === 'Tienda del origen' && site.home === `${base}/importado` && site.posts?.['Entrada de la autora del origen'] === 'autora-red' && site.posts?.['Página de la dueña del origen'] === 'duena', 'el contenido del origen, con su autoría en los usuarios de la red (la autora es «autora-red» por su correo)', JSON.stringify({ blog: site.blogname, home: site.home, posts: site.posts }));
check((site.comments || []).includes('socia-2') && !(site.comments || []).includes('socia'), 'el comentario de la suscriptora es de su cuenta nueva (socia-2: su nombre ya era de otra persona en la red)', JSON.stringify(site.comments));
check(site.uploads_dir === `wp-content/uploads/sites/${id}` && site.files?.['2026/11/origen.png'] === origin.media_sha && Object.keys(site.files || {}).every((f) => !f.startsWith('sites/')), 'los medios del origen están en uploads/sites/N, iguales', JSON.stringify({ dir: site.uploads_dir, files: site.files }));
check(site.content.includes(`/importado/wp-content/uploads/sites/${id}/2026/11/origen.png`) && !site.content.includes('origen.test'), 'la URL del medio en el contenido es la de uploads/sites/N del sitio nuevo', site.content);
const served = await request('GET', `/importado/wp-content/uploads/sites/${id}/2026/11/origen.png`, { binary: true });
check(served.status === 200 && sha(served.raw) === origin.media_sha, 'el medio se sirve desde uploads/sites/N', `${served.status}`);
const front = await request('GET', '/importado/');
check(front.status === 200 && /<!-- tema-origen -->/.test(front.text) && /<!-- plugin-origen activo -->/.test(front.text) && /Entrada de la autora del origen/.test(front.text), 'la portada del sitio nuevo usa el tema y el plugin del origen', `${front.status} ${front.text.slice(0, 300)}`);
const mainFront = await request('GET', '/');
check(mainFront.status === 200 && !/plugin-origen activo/.test(mainFront.text), 'el plugin del origen no está activo en el principal', `${mainFront.status}`);
check(site.active_plugins.includes('plugin-origen/plugin-origen.php') && !state.sites[1].active_plugins.includes('plugin-origen/plugin-origen.php') && !state.sites[2].active_plugins.includes('plugin-origen/plugin-origen.php'), 'el plugin del origen se instaló y está activo sólo en ese sitio', JSON.stringify(site.active_plugins));
check(site.stylesheet === 'tema-origen' && site.allowedthemes.includes('tema-origen') && !state.sites[1].allowedthemes.includes('tema-origen'), 'el tema del origen está habilitado sólo para ese sitio', JSON.stringify({ theme: site.stylesheet, allowed: site.allowedthemes }));
check(site.backups_options === 0, 'la configuración de Backups del origen no quedó en el sitio importado', String(site.backups_options));
const users = state.users || {};
check(users.duena?.roles?.[id]?.includes('administrator') && users.edita?.roles?.[id]?.includes('editor') && users['autora-red']?.roles?.[id]?.includes('author') && users['socia-2']?.roles?.[id]?.includes('subscriber') && users.socia?.roles?.[id] === undefined, 'los roles del respaldo en el sitio nuevo (socia de la red no es miembro)', JSON.stringify(Object.fromEntries(Object.entries(users).map(([l, u]) => [l, u.roles]))));
const net = (s) => network(s, id);
check(JSON.stringify(net(before)) === JSON.stringify(Object.fromEntries(Object.entries(net(state)).filter(([login]) => login in (before.users || {})))), 'ningún usuario de la red cambió su contraseña, su correo, sus metas ni su rol en otro sitio', JSON.stringify({ before: net(before).edita, after: net(state).edita }));
check(JSON.stringify(others(before, [1, 2])) === JSON.stringify(others(state, [1, 2])), 'los sitios 1 y 2 no cambiaron (huellas de sus tablas y medios)', differs(others(before, [1, 2]), others(state, [1, 2])));
for (const [login, pass] of [['duena', origin.passwords.duena], ['socia-2', origin.passwords.socia], ['edita', red.passwords.edita], ['autora-red', red.passwords['autora-red']], ['dueno2', red.passwords.dueno2]]) {
  const auth = await probe('auth', `&login=${encodeURIComponent(login)}&pass=${encodeURIComponent(pass)}`);
  check(auth.ok, `${login} entra con su contraseña (${['duena', 'socia-2'].includes(login) ? 'la del respaldo' : 'la de la red'})`, JSON.stringify(auth));
}
const ownerLogin = await loginAs('duena', origin.passwords.duena, '/importado/wp-admin/');
check(ownerLogin.ok, 'la dueña del origen entra por wp-login.php al escritorio de su sitio', JSON.stringify(ownerLogin));
const editaOld = await probe('auth', `&login=edita&pass=${encodeURIComponent(origin.passwords.edita)}`);
check(!editaOld.ok, 'la contraseña del respaldo no reemplaza la de una cuenta de la red (edita)');
check(!state.maintenance && state.jobs === 0, 'sin mantenimiento ni trabajos al terminar');
await go(screen);
await shot('2-importado');
check(/Importado como sitio nuevo de la red/.test(run?.job?.result?.restore?.notice || ''), 'el informe dice dónde quedó y qué pasó con los usuarios', run?.job?.result?.restore?.notice);

// 3. Reemplazando el sitio 2 (las huellas de ahora: entrar al escritorio cambia opciones de su sitio).
before = await probe();
run = await importRow(uploaded.file, { site: 2, name: '3-reemplazo' });
state = await probe();
const two = state.sites?.[2] || {};
check(run?.job?.status === 'success' && two.blogname === 'Tienda del origen' && two.home === `${base}/dos` && two.posts?.['Entrada de la autora del origen'] && !two.posts?.['Entrada del sitio dos'], `el sitio 2 es ahora el del respaldo en su dirección (${run?.run?.seconds} s)`, JSON.stringify({ job: run?.job?.status, error: run?.job?.error, two: { blog: two.blogname, home: two.home, posts: two.posts } }));
check(two.files?.['2026/11/origen.png'] === origin.media_sha && !two.files?.['2026/11/dos.png'], 'los medios del sitio 2 son los del respaldo', JSON.stringify(two.files));
const copy = (state.history || []).find((r) => r.scope === 'network-site' && r.site_copy === 2);
check(copy && copy.locked && run?.job?.result?.restore?.safety_backup === copy.file, 'la copia previa es sólo la del sitio 2 y queda bloqueada', JSON.stringify({ copy, safety: run?.job?.result?.restore?.safety_backup }));
check(state.users?.dueno2?.roles?.[2]?.includes('administrator') && (await probe('auth', `&login=dueno2&pass=${encodeURIComponent(red.passwords.dueno2)}`)).ok, 'el administrador del sitio 2 sigue siéndolo y entra con su contraseña');
check((await loginAs('dueno2', red.passwords.dueno2, '/dos/wp-admin/')).ok, 'el administrador del sitio 2 entra a su escritorio tras el reemplazo');
check(JSON.stringify(others(before, [1, id])) === JSON.stringify(others(state, [1, id])), 'el principal y el sitio nuevo no cambiaron con el reemplazo', differs(others(before, [1, id]), others(state, [1, id])));
await go(screen);
await shot('3-reemplazado');

// 4. Falla inyectada justo después de fundir los usuarios: vuelve a la copia previa del sitio 2.
before = await probe();
await probe('fault', '&on=1');
run = await importRow(uploaded.file, { site: 2, name: '4-falla' });
state = await probe();
check(run?.job?.status === 'failed' && run?.rollback?.status === 'success', `la importación con la falla inyectada falló y volvió sola a la copia previa (${run?.run?.seconds} s)`, JSON.stringify({ job: run?.job?.status, error: run?.job?.error, rollback: run?.rollback?.status, rerr: run?.rollback?.error }));
check(JSON.stringify(others(before, [1, 2, id])) === JSON.stringify(others(state, [1, 2, id])), 'tras la vuelta, los tres sitios están como antes (huellas)', differs(others(before, [1, 2, id]), others(state, [1, 2, id])));
check(JSON.stringify(network(before, 0)) === JSON.stringify(network(state, 0)) && Object.keys(before.users || {}).join() === Object.keys(state.users || {}).join(), 'tras la vuelta, los usuarios de la red (cuentas, contraseñas, metas y roles en cada sitio) están exactamente como antes', JSON.stringify({ before: Object.keys(before.users || {}), after: Object.keys(state.users || {}) }));
check(!state.maintenance && state.jobs === 0, 'sin mantenimiento ni trabajos tras la vuelta');
await go(screen);
await shot('4-vuelta');

check(!jsErrors.length, 'sin errores de JavaScript en la pantalla', jsErrors.join(' | '));
await browser.close();
log(`${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - started) / 1000)} s`);
process.exit(failures ? 1 : 0);
