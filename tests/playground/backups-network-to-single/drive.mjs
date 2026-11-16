// Ensayo «el respaldo de una red en un WordPress individual» con Chrome sin interfaz (puppeteer-core de PUPPETEER_PATH)
// contra el WordPress individual de destino que levantó backups-network-to-single.sh (destino.test, otra dirección que la
// red red.test). Todo por la pantalla, como lo haría el propietario:
//   1. «Subir un respaldo» del ZIP de la red → queda en la lista, sin «No se restaura aquí», con «Restaurar».
//   2. «Restaurar» de su fila: la frase dice qué pasa, se elige el sitio 2 en la lista de sitios de la red y se confirma
//      con RESTAURAR; el panel de avance sigue la conversión y después la restauración.
//   3. Con huellas: el contenido del sitio 2, sus medios servidos desde uploads/ (los bytes de la red), sus usuarios que
//      entran con su contraseña de la red, los plugins (el de la red y el suyo) activos, su tema, ninguna tabla de la red
//      y la portada sin errores; quien restauró sigue dentro.
//   4. Lo mismo con el sitio principal.
//   5. Una restauración con una falla después del cambio (falla.php) vuelve sola a la copia previa: el sitio queda igual.
// Capturas a 1280 px (y la lista también a 390 px) en <out>/shots; cada comprobación en <out>/result.txt.
// Uso: node drive.mjs <puerto> <host> <dir> <out> <ZIP de la red> <inventario de la red>
import http from 'node:http';
import { appendFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [port, host, dir, out, zip, inventoryPath] = process.argv.slice(2);
const shots = `${out}/shots`;
mkdirSync(shots, { recursive: true });
const store = `${dir}/store`;
const token = readFileSync(`${dir}/web/token.txt`, 'utf8').trim();
const network = JSON.parse(readFileSync(`${dir}/probe/state.json`, 'utf8'));
const inventory = JSON.parse(readFileSync(inventoryPath, 'utf8'));
const base = `http://${host}`;
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
const sha = (bytes) => createHash('sha256').update(bytes).digest('hex');
let failures = 0;
const log = (line) => { appendFileSync(`${out}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 1200)}` : ''}`); }
  return ok;
};
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
// `rollback` de una restauración normal es una lista vacía (`[]` en el JSON del trabajo, que en JavaScript es verdadera).
const isRollback = (job) => Boolean(job?.spec?.rollback) && !(Array.isArray(job.spec.rollback) && !job.spec.rollback.length);

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
    req.on('error', (error) => resolve({ status: 0, raw: Buffer.alloc(0), text: String(error.message), json: null, headers: {} }));
    req.on('timeout', () => req.destroy(new Error('tiempo agotado')));
    if (body !== undefined) req.write(body);
    req.end();
  });
}
const state = async () => (await request('GET', `/wp-content/digi-web/state.php?token=${token}`)).json || {};
const jobs = () => (existsSync(`${store}/jobs`) ? readdirSync(`${store}/jobs`).map((id) => { try { return JSON.parse(readFileSync(`${store}/jobs/${id}/job.json`, 'utf8')); } catch { return null; } }).filter(Boolean) : []);
const terminal = (job) => job && ['success', 'failed', 'cancelled'].includes(job.status);

const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgn2s-' : `${tmpdir()}/dgn2s-`);
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
const screen = '/wp-admin/admin.php?page=digitalisimo-backups';
const cookieHeader = async () => (await page.cookies(base)).map((c) => `${c.name}=${c.value}`).join('; ');

async function shot(name, { width = 1280 } = {}) {
  if (width !== 1280) await page.setViewport({ width, height: 900 });
  await page.evaluate(() => window.scrollTo(0, 0));
  await sleep(400);
  await page.screenshot({ path: `${shots}/${name}.png`, fullPage: true });
  if (width !== 1280) await page.setViewport({ width: 1280, height: 900 });
  log(`captura · ${name}.png`);
}
// Dentro del escritorio por la ruta (no por un `redirect_to=…wp-admin…` de wp-login.php).
const inAdmin = () => { try { return new URL(page.url()).pathname.includes('/wp-admin/'); } catch { return false; } };
async function login() {
  // Con la sesión de antes (quien restaura la conserva), ya está dentro.
  await page.goto(`${base}${screen}`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
  for (let attempt = 0; attempt < 2 && !inAdmin(); attempt++) {
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) continue;
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  }
  return check(inAdmin(), 'inicio de sesión de admin en el destino', page.url());
}
async function go(path) {
  const res = await page.goto(base + path, { waitUntil: 'load' });
  await sleep(800);
  return res ? res.status() : 0;
}
/** Hace avanzar los trabajos sin terminar como WP-Cron (y, en una restauración, con su testigo) hasta que terminan. */
async function drive(label, seconds = 1500) {
  const started = Date.now();
  let shotDuring = false;
  while (Date.now() - started < seconds * 1000) {
    const open = jobs().filter((job) => !terminal(job));
    if (!open.length) break;
    const restoreCookie = (await page.cookies(base)).find((c) => c.name === 'digitalisimo_backups_restore');
    if (restoreCookie) await request('POST', '/wp-admin/admin-ajax.php', { headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: await cookieHeader() }, body: new URLSearchParams({ action: 'digitalisimo_backups_restore_tick', digitalisimo_backups_restore: restoreCookie.value }).toString() });
    await request('GET', '/wp-cron.php');
    if (!shotDuring && open.some((job) => job.spec?.kind === 'convert' && job.phase !== 'preflight')) {
      shotDuring = true;
      await go(screen).catch(() => {});
      await sleep(2500);
      await shot(`${label}-convirtiendo`);
    }
    await sleep(1500);
  }
  return { open: jobs().filter((job) => !terminal(job)).length, seconds: Math.round((Date.now() - started) / 1000) };
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
  await go('/wp-admin/admin.php?page=digitalisimo-backups&tab=tools&open=upload');
  const input = await page.$('#digitalisimo-backups-upload input[type=file]');
  if (!check(Boolean(input), '«Subir un respaldo» ofrece elegir el archivo')) return '';
  await input.uploadFile(path);
  await page.click('#digitalisimo-backups-upload button[type=submit]');
  let status = '';
  for (let i = 0; i < 600; i++) {
    await sleep(1500);
    status = await page.$eval('#digitalisimo-backups-upload .digitalisimo-backups-upload-status', (p) => p.textContent).catch(() => '');
    const busy = await page.$eval('#digitalisimo-backups-upload button[type=submit]', (b) => b.disabled).catch(() => false);
    if (!busy && status) break;
  }
  return status;
}
/** «Restaurar» de la fila con el sitio de la red elegido; devuelve el trabajo de la restauración y su vuelta, si la hubo. */
async function restoreSite(file, site, label) {
  const before = new Set(jobs().map((job) => job.id));
  await go(`${screen}&restore=${encodeURIComponent(file)}`);
  const row = await rowOf(file);
  if (!check(row && row.restore, `${label}: la fila del respaldo de la red ofrece «Restaurar»`)) return null;
  const panel = `#${row.restore}`;
  const options = await page.$$eval(`${panel} select[name=restore_site] option`, (os) => os.map((o) => ({ value: o.value, text: o.textContent, selected: o.selected })));
  check(options.length === 3 && options.find((o) => o.selected)?.value === '1' && /principal/.test(options[0].text), `${label}: la confirmación lista los 3 sitios de la red con el principal elegido`, JSON.stringify(options));
  await page.select(`${panel} select[name=restore_site]`, String(site));
  const sentence = await page.$eval(`${panel} [data-network-site-summary]`, (p) => p.textContent);
  check(new RegExp(`se restaura el sitio ${site === 1 ? 'red\\.test ' : 'red\\.test/dos '}de la red como este WordPress`).test(sentence), `${label}: la frase dice qué sitio de la red queda como este WordPress`, sentence);
  await shot(`${label}-confirmacion`);
  await page.type(`${panel} input[name=confirmation]`, 'RESTAURAR');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click(`${panel} button.button-primary`)]);
  // La petición responde enseguida (falla.php, con /digi-probe/lento): el panel de avance sigue la conversión.
  await sleep(1500);
  await shot(`${label}-avance`);
  const during = await page.$eval('#digitalisimo-backups-manual-progress', (p) => p.innerText).catch(() => '');
  const run = await drive(label);
  const created = jobs().filter((j) => !before.has(j.id));
  const convert = created.find((j) => j.spec?.kind === 'convert') || null;
  const restore = created.filter((j) => j.kind === 'restore' && !isRollback(j)).sort((a, b) => Number(a.queued_at || 0) - Number(b.queued_at || 0))[0] || null;
  const rollback = created.find((j) => j.kind === 'restore' && isRollback(j)) || null;
  await go(screen);
  await sleep(3000);
  await shot(`${label}-resultado`);
  const panelText = await page.$eval('#digitalisimo-backups-manual-progress', (p) => p.innerText).catch(() => '');
  return { run, convert, restore, rollback, panelText, during };
}
/** La portada y un medio servido, por HTTP, como un visitante. */
async function front(expectTheme, expectPlugins, mediaName, mediaHash) {
  const home = await request('GET', '/');
  const html = home.text;
  const ok = home.status === 200 && html.includes(`<p id="tema">${expectTheme}</p>`) && expectPlugins.every((p) => html.includes(`<p class="digi-plugin">${p}</p>`)) && !/Fatal error|Warning:|Uncaught/.test(html);
  const src = (html.match(/<img[^>]+src="([^"]+)"/) || [])[1] || '';
  const media = src ? await request('GET', src.replace(base, ''), { binary: true }) : { status: 0, raw: Buffer.alloc(0) };
  return { ok, status: home.status, src, mediaOk: src.startsWith(`${base}/wp-content/uploads/`) && !src.includes('/sites/') && src.endsWith(mediaName) && media.status === 200 && sha(media.raw) === mediaHash, html: html.slice(0, 600) };
}
/** Un usuario de la red entra con su contraseña de la red (wp-login.php real). */
async function canLogin(login) {
  const body = new URLSearchParams({ log: login, pwd: network.passwords[login], 'wp-submit': 'Acceder', redirect_to: `${base}/wp-admin/`, testcookie: '1' }).toString();
  const res = await request('POST', '/wp-login.php', { headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' }, body });
  return res.status === 302 && /wp-admin/.test(String(res.headers.location || '')) && String(res.headers['set-cookie'] || '').includes('wordpress_logged_in');
}
async function canLoginAs(login, password) {
  const body = new URLSearchParams({ log: login, pwd: password, 'wp-submit': 'Acceder', redirect_to: `${base}/wp-admin/`, testcookie: '1' }).toString();
  const res = await request('POST', '/wp-login.php', { headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' }, body });
  return res.status === 302 && String(res.headers['set-cookie'] || '').includes('wordpress_logged_in');
}
const networkTables = (tables) => tables.filter((t) => /(?:blogs|blogmeta|site|sitemeta|signups|registration_log)$/.test(t) || /_[0-9]+_/.test(t));

const started = Date.now();
await login();
// 1. Subir el respaldo de la red: queda en la lista y se restaura aquí.
const status = await upload(zip);
check(/Respaldo subido y verificado/.test(status) && /Ya está en la lista de respaldos/.test(status) && !/No se restaura aquí|no se puede restaurar/i.test(status), '«Subir un respaldo» deja el de la red en la lista, restaurable aquí', status);
let st = await state();
const file = (st.history || []).find((row) => row.origin === 'uploaded' && row.scope === 'network')?.file || '';
await go(screen);
const row = await rowOf(file);
check(row && row.restore && !/No se restaura aquí/.test(row.text), 'la lista muestra el respaldo de la red sin «No se restaura aquí» y con «Restaurar»', row?.text);
await shot('lista');
await shot('lista-390', { width: 390 });

// 2-3. El sitio 2 como este WordPress.
const two = inventory['2'];
writeFileSync(`${dir}/probe/lento`, '1');
let run = await restoreSite(file, 2, 'sitio-2');
try { (await import('node:fs')).unlinkSync(`${dir}/probe/lento`); } catch { /* ya no está */ }
st = await state();
check(run?.convert?.status === 'success' && run?.restore?.status === 'success' && !run?.rollback, `la conversión del sitio 2 y su restauración terminan (${run?.run?.seconds} s)`, JSON.stringify({ convert: run?.convert?.status, error: run?.convert?.error || run?.restore?.error, restore: run?.restore?.status }));
check(/Convirtiendo el sitio de la red|Lectura del sitio|Conversión de|Restauración en curso/.test(run?.during || ''), 'el panel de avance sigue la conversión del sitio de la red', run?.during);
check(/Se restauró el sitio red\.test\/dos \(ID 2\) del respaldo de la red/.test(run?.panelText || '') || /Se restauró el sitio red\.test\/dos/.test(JSON.stringify(run?.restore?.result || {})), 'el resultado dice qué sitio de la red se restauró', run?.panelText);
check(same(Object.values(st.posts || {}).sort(), Object.values(two.posts).sort()), 'las entradas, páginas y medios son los del sitio 2', JSON.stringify({ aqui: st.posts, red: two.posts }));
check(same(st.uploads?.files, two.files), 'los medios del sitio 2 están en uploads/ con sus mismos bytes (SHA-256)', JSON.stringify({ aqui: st.uploads?.files, red: two.files }));
check(st.stylesheet === 'tema-dos' && st.template === 'tema-base' && same([...st.plugins].sort(), ['digitalisimo-backups/digitalisimo-backups.php', 'plugin-dos/plugin-dos.php', 'red-plugin/red-plugin.php']), 'su tema (hijo) y los plugins (el de la red, el suyo y Backups) quedan activos', JSON.stringify({ tema: st.stylesheet, plugins: st.plugins }));
check(!networkTables(st.tables || []).length && st.multisite === false, 'no queda ninguna tabla de la red ni de otros sitios', JSON.stringify(st.tables));
check(same(st.users?.editora?.roles, ['editor']) && same(st.users?.autora?.roles, ['author']) && same(st.users?.suscriptor?.roles, ['subscriber']) && st.users?.editora?.network_password && st.users?.autora?.network_password && !st.users?.solo3 && !st.users?.jefa && same(st.users?.admin?.roles, ['administrator']), 'los miembros del sitio 2 con sus roles y su contraseña de la red; los de otros sitios no; quien restauró sigue', JSON.stringify(st.users));
check(await canLogin('editora') && await canLogin('autora'), 'la editora y la autora entran por wp-login.php con su contraseña de la red');
check(!(st.leftovers || []).length, 'ningún valor de la base de datos apunta al sitio 2 en la red (red.test/dos) ni a uploads/sites/ (los enlaces a la portada de la red quedan, como al extraer)', JSON.stringify(st.leftovers));
let home = await front('tema-dos', ['red-plugin', 'plugin-dos'], 'portada-2.png', Object.entries(two.files).find(([name]) => name.endsWith('portada-2.png'))?.[1]);
check(home.ok && home.mediaOk, 'la portada carga sin errores con su tema y sus plugins, y su medio se sirve desde uploads/ con los bytes de la red', JSON.stringify(home));
await page.goto(base + '/', { waitUntil: 'load' });
await shot('sitio-2-portada');
check((await page.goto(base + screen, { waitUntil: 'load' }), inAdmin()), 'quien restauró sigue dentro del escritorio', page.url());

// 4. El sitio principal.
const one = inventory['1'];
run = await restoreSite(file, 1, 'principal');
st = await state();
check(run?.convert?.status === 'success' && run?.restore?.status === 'success', `la conversión del sitio principal y su restauración terminan (${run?.run?.seconds} s)`, JSON.stringify({ convert: run?.convert?.status, error: run?.convert?.error || run?.restore?.error }));
check(same(Object.values(st.posts || {}).sort(), Object.values(one.posts).sort()) && same(st.uploads?.files, one.files), 'el contenido y los medios son los del principal (sin sites/)', JSON.stringify({ posts: st.posts, files: st.uploads?.files, red: one.files }));
check(st.stylesheet === 'tema-uno' && same([...st.plugins].sort(), ['digitalisimo-backups/digitalisimo-backups.php', 'plugin-uno/plugin-uno.php', 'red-plugin/red-plugin.php']) && !networkTables(st.tables || []).length, 'el tema y los plugins del principal, sin tablas de la red', JSON.stringify({ tema: st.stylesheet, plugins: st.plugins, tables: st.tables }));
const roled = Object.entries(st.users || {}).filter(([, u]) => (u.roles || []).length).map(([login]) => login).sort();
check(same(st.users?.jefa?.roles, ['editor']) && st.users?.jefa?.network_password && same(st.users?.admin?.roles, ['administrator']) && same(roled, ['admin', 'jefa']), 'los usuarios del principal con sus roles (la jefa con su contraseña de la red), los demás miembros sin rol como en la red, y quien restauró', JSON.stringify(st.users));
check(await canLoginAs('admin', 'password'), 'quien restauró sigue entrando con su contraseña de aquí');
home = await front('tema-uno', ['red-plugin', 'plugin-uno'], 'portada-1.png', Object.entries(one.files).find(([name]) => name.endsWith('portada-1.png'))?.[1]);
check(home.ok && home.mediaOk, 'la portada del principal carga sin errores y su medio se sirve desde uploads/', JSON.stringify(home));
await page.goto(base + '/', { waitUntil: 'load' });
await shot('principal-portada');

// 5. Una falla después del cambio vuelve sola a la copia previa.
await login();
const before = await state();
writeFileSync(`${dir}/probe/falla`, '1');
run = await restoreSite(file, 2, 'falla');
const after = await state();
check(existsSync(`${dir}/probe/falla-hecha`), 'la falla después del cambio ocurrió');
check(run?.restore?.status === 'failed' && run?.rollback?.status === 'success', 'la restauración con la falla vuelve sola a la copia previa', JSON.stringify({ restore: run?.restore?.status, error: run?.restore?.error, rollback: run?.rollback?.status }));
const keys = ['posts', 'stylesheet', 'template', 'plugins', 'tables', 'plugin_dirs', 'theme_dirs'];
check(keys.every((k) => same(before[k], after[k])) && same(before.uploads?.files, after.uploads?.files) && same(Object.keys(before.users || {}).sort(), Object.keys(after.users || {}).sort()), 'tras la vuelta, el sitio es el de antes (entradas, tema, plugins, tablas, archivos y usuarios)', JSON.stringify(keys.filter((k) => !same(before[k], after[k]))));
check(/volvió|copia previa/i.test(run?.panelText || '') && /falla simulada/.test(run?.panelText || ''), 'el panel dice que el sitio volvió a la copia previa y por qué', run?.panelText);

check(!jsErrors.length, 'sin errores de JavaScript en la pantalla', jsErrors.join(' | '));
await browser.close();
log(`red → individual: ${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - started) / 1000)} s`);
process.exit(failures ? 1 : 0);
