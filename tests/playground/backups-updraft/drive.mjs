// Ensayo de UpdraftPlus con Chrome sin interfaz (puppeteer-core de PUPPETEER_PATH) contra el destino que levantó
// backups-updraft.sh. Todo por la pantalla, como lo haría el propietario:
//   1. «Subir un respaldo» de cada pieza del conjunto de UpdraftPlus (la base de datos y sus ZIP), con el panel de avance;
//   2. Herramientas → «Importar de Backuply o UpdraftPlus» → «Importar» del conjunto: el trabajo lo convierte y queda en
//      la lista de respaldos como «Importado»;
//   3. «Restaurar» de su fila escribiendo RESTAURAR SIN FIRMA (en una red, como sitio nuevo; el de una red en un
//      individual, su sitio principal): el sitio queda con el contenido del origen y la dirección de aquí, las imágenes
//      cargan, nada menciona origen.test y quien restaura sigue dentro.
// Capturas a 1280 px (y la lista también a 390 px) en <out>/shots; cada comprobación en <out>/result.txt.
// Uso: node drive.mjs <single|red|a-red> <puerto> <host> <dir> <out> <carpeta del conjunto> <conjunto>
import http from 'node:http';
import { appendFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [mode, port, host, dir, out, setDir, setId] = process.argv.slice(2);
const shots = `${out}/shots`;
mkdirSync(shots, { recursive: true });
const store = `${dir}/store`;
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const base = `http://${host}`;
const network = mode === 'a-red';
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
let failures = 0;
const log = (line) => { appendFileSync(`${out}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${mode} · ${what}`);
  else { failures++; log(`FALLA · ${mode} · ${what}${detail ? ` · ${String(detail).slice(0, 900)}` : ''}`); }
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
const probe = async () => (await request('GET', `/?digi_ud=state&token=${token}`)).json || {};
const jobs = () => (existsSync(`${store}/jobs`) ? readdirSync(`${store}/jobs`).map((id) => { try { return JSON.parse(readFileSync(`${store}/jobs/${id}/job.json`, 'utf8')); } catch { return null; } }).filter(Boolean) : []);
const terminal = (job) => job && ['success', 'failed', 'cancelled'].includes(job.status);

const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgud-' : `${tmpdir()}/dgud-`);
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
// «Transition was aborted…» (ViewTransition) es del escritorio de WordPress cuando una navegación corta una transición,
// no de Backups: como en los ensayos de los paneles de avance, no cuenta.
page.on('pageerror', (e) => { const text = String(e && e.message || e); if (!/ViewTransition|Transition was aborted/.test(text)) jsErrors.push(`${page.url()} · ${text.slice(0, 300)}`); });
page.on('dialog', async (d) => { await d.accept(); });
const screen = network ? '/wp-admin/network/admin.php?page=digitalisimo-network-backups' : '/wp-admin/admin.php?page=digitalisimo-backups';
const cookieHeader = async () => (await page.cookies(base)).map((c) => `${c.name}=${c.value}`).join('; ');

async function shot(name, { width = 1280 } = {}) {
  if (width !== 1280) await page.setViewport({ width, height: 900 });
  await page.evaluate(() => window.scrollTo(0, 0));
  await sleep(400);
  await page.screenshot({ path: `${shots}/${mode}-${name}.png`, fullPage: true });
  if (width !== 1280) await page.setViewport({ width: 1280, height: 900 });
  log(`captura · ${mode}-${name}.png`);
}
async function login() {
  for (let attempt = 0; attempt < 2 && !/wp-admin/.test(page.url()); attempt++) {
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) continue;
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  }
  return check(/wp-admin/.test(page.url()), 'inicio de sesión de admin en el destino', page.url());
}
async function go(path) {
  const res = await page.goto(base + path, { waitUntil: 'load' });
  await sleep(800);
  return res ? res.status() : 0;
}
/** Hace avanzar los trabajos sin terminar como WP-Cron (y, en una restauración, con su testigo) hasta que terminan. */
async function drive(label, seconds = 1500) {
  const started = Date.now();
  while (Date.now() - started < seconds * 1000) {
    const open = jobs().filter((job) => !terminal(job));
    if (!open.length) break;
    const restoreCookie = (await page.cookies(base)).find((c) => c.name === 'digitalisimo_backups_restore');
    if (restoreCookie) {
      await request('POST', `/wp-admin/admin-ajax.php?digitalisimo_backups_restore=${restoreCookie.value}`, { headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: await cookieHeader() }, body: new URLSearchParams({ action: 'digitalisimo_backups_restore_tick', digitalisimo_backups_restore: restoreCookie.value }).toString() });
    }
    await request('GET', '/wp-cron.php');
    await sleep(1500);
  }
  return { open: jobs().filter((job) => !terminal(job)).length, seconds: Math.round((Date.now() - started) / 1000), label };
}
/** «Subir un respaldo» de Herramientas con el archivo; el título y el resultado del panel de avance. */
async function upload(path) {
  await go(`${screen}&tab=tools&open=upload`);
  const input = await page.$('#digitalisimo-backups-upload input[type=file]');
  if (!check(Boolean(input), '«Subir un respaldo» ofrece elegir el archivo')) return '';
  await input.uploadFile(path);
  await page.click('#digitalisimo-backups-upload button[type=submit]');
  let status = '';
  for (let i = 0; i < 400; i++) {
    await sleep(1500);
    const panel = await page.evaluate(() => {
      const p = document.getElementById('digitalisimo-backups-manual-progress');
      const q = (id) => document.getElementById('digitalisimo-backups-progress-' + id);
      return p ? { state: p.getAttribute('data-state'), text: q('title').textContent + '. ' + q('result').textContent } : null;
    }).catch(() => null);
    const busy = await page.$eval('#digitalisimo-backups-upload button[type=submit]', (b) => b.disabled).catch(() => false);
    status = panel ? panel.text : '';
    if (!busy && panel && ['success', 'failed', 'cancelled', 'paused'].includes(panel.state)) break;
  }
  return status;
}
async function rowOf(file) {
  return page.evaluate((f) => {
    const row = [...document.querySelectorAll('tr.digitalisimo-backups-row')].find((tr) => tr.dataset.file === f);
    if (!row) return null;
    const restore = row.querySelector('[data-restore-toggle]');
    return { text: row.innerText, restore: restore ? restore.getAttribute('aria-controls') : '', badge: row.querySelector('.is-uploaded')?.textContent || '', title: row.querySelector('.is-uploaded')?.getAttribute('title') || '' };
  }, file);
}

const started = Date.now();
await login();
// 1. Subir cada pieza del conjunto (UpdraftPlus las deja en su carpeta; aquí vienen de «otro servidor»).
const pieces = readdirSync(setDir).filter((name) => name.startsWith(setId + '-')).sort();
check(pieces.some((p) => /-db\.gz$/.test(p)) && pieces.some((p) => /-plugins\.zip$/.test(p)) && pieces.some((p) => /-uploads\.zip$/.test(p)), 'UpdraftPlus dejó un conjunto completo (base de datos, plugins y medios)', pieces.join(', '));
for (const piece of pieces) {
  const status = await upload(`${setDir}/${piece}`);
  check(/Pieza de UpdraftPlus subida/.test(status), `«Subir un respaldo» acepta la pieza ${piece.slice(setId.length)}`, status);
}
await shot('pieza-subida');
// 2. Importar el conjunto desde Herramientas.
await go(`${screen}&tab=tools&open=import`);
const form = await page.evaluateHandle((id) => [...document.querySelectorAll('form')].find((f) => f.querySelector(`input[name="updraft_set"][value="${id}"]`) && f.querySelector('input[name="action"][value="digitalisimo_backups_updraft_import"]')), setId);
const hasForm = await form.evaluate((f) => Boolean(f)).catch(() => false);
await shot('importar');
if (check(hasForm, 'Herramientas ofrece «Importar» del conjunto subido')) {
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), form.evaluate((f) => f.querySelector('button').click())]);
  const run = await drive('importación');
  const job = jobs().filter((j) => j.kind === 'import').sort((a, b) => Number(b.queued_at || 0) - Number(a.queued_at || 0))[0] || {};
  check(run.open === 0 && job.status === 'success' && /importado como/.test(job.progress?.message || ''), `la importación termina (${run.seconds} s)`, JSON.stringify({ status: job.status, error: job.error, message: job.progress?.message }));
}
let state = await probe();
const row = (state.history || []).find((r) => r.origin === 'imported' && r.import?.format === 'updraft') || {};
check(row.file && row.import.source === setId && row.home === 'http://origen.test' && row.scope === (mode === 'red' ? 'network' : 'installation'), 'el conjunto queda en la lista como «Importado», de origen.test', JSON.stringify(state.history));
if (mode === 'red') check(Object.keys(row.sites || {}).length === 2, 'la fila de la red lleva sus dos sitios', JSON.stringify(row.sites));
await go(screen);
const listed = row.file ? await rowOf(row.file) : null;
check(listed && /Importado/.test(listed.badge) && /UpdraftPlus/.test(listed.title) && listed.restore, 'la lista muestra «Importado» (de UpdraftPlus) con «Restaurar»', JSON.stringify(listed));
await shot('lista');
await shot('lista-390', { width: 390 });
check(!(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)), 'la lista no desborda a 1280 px');
// 3. Restaurarlo desde su fila con RESTAURAR SIN FIRMA.
if (listed && listed.restore) {
  await go(`${screen}&restore=${encodeURIComponent(row.file)}`);
  const panel = `#${listed.restore}`;
  const panelText = await page.$eval(panel, (p) => p.innerText).catch(() => '');
  if (mode === 'red') check(/Es el respaldo de una red y este WordPress es individual/.test(panelText), 'el panel ofrece restaurar un sitio de la red en este individual', panelText.slice(0, 400));
  if (network) check(/Importar en la red/.test(panelText), 'el panel ofrece importarlo como un sitio de la red', panelText.slice(0, 400));
  await shot('confirmar');
  await page.type(`${panel} input[name=confirmation]`, 'RESTAURAR SIN FIRMA');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click(`${panel} button.button-primary`)]);
  const run = await drive('restauración');
  const job = jobs().filter((j) => j.kind !== 'import').sort((a, b) => Number(b.queued_at || 0) - Number(a.queued_at || 0))[0] || {};
  check(run.open === 0 && job.status === 'success', `la restauración termina (${run.seconds} s)`, JSON.stringify({ kind: job.kind, status: job.status, error: job.error, message: job.progress?.message }));
  state = await probe();
  let site = state;
  if (network) {
    const created = Object.values(state.sites || {}).find((s) => (s.posts || []).includes('Entrada de UpdraftPlus principal')) || null;
    const main = (state.sites || {})[1] || {};
    check(created && /^http:\/\/destino\.test\/[a-z0-9-]+$/.test(created.home) && created.blogname === 'Sitio de origen UpdraftPlus', 'en la red queda un sitio nuevo con el contenido del origen y una dirección de la red', JSON.stringify(state.sites));
    check((main.posts || []).includes('Entrada del destino') && main.home === 'http://destino.test', 'el sitio principal de la red no cambió', JSON.stringify(main));
    site = created ? { ...created, content: '' } : {};
  } else {
    check(state.blogname === 'Sitio de origen UpdraftPlus' && state.home === 'http://destino.test' && state.siteurl === 'http://destino.test', 'el sitio queda con el nombre del origen y la dirección del destino', JSON.stringify({ blog: state.blogname, home: state.home, siteurl: state.siteurl }));
    check((state.posts || []).includes('Entrada de UpdraftPlus principal') && !(state.posts || []).includes('Entrada del destino'), 'las entradas son las del origen', JSON.stringify(state.posts));
    check(/http:\/\/destino\.test\/contacto\//.test(state.content) && !/origen\.test/.test(state.content), 'los enlaces y las imágenes del contenido apuntan al destino', state.content);
    check(state.demo && state.mu && (state.active || []).includes('demo-origen/demo-origen.php'), 'el plugin y el mu-plugin del origen están (y el plugin, activo)', JSON.stringify({ demo: state.demo, mu: state.mu, active: state.active }));
    const image = await request('GET', '/wp-content/uploads/2026/11/ud-principal.png', { binary: true });
    check(image.status === 200 && image.raw.slice(1, 4).toString() === 'PNG', 'la imagen de la biblioteca carga en el destino', String(image.status));
  }
  await go(screen);
  check(/wp-admin/.test(page.url()) && !/wp-login/.test(page.url()), 'quien restauró sigue dentro', page.url());
  await shot('restaurado');
  const front = await request('GET', network && site.home ? site.home.replace(base, '') + '/' : '/');
  check(front.status === 200 && /Entrada de UpdraftPlus principal/.test(front.text) && !/origen\.test/.test(front.text), 'la portada del sitio restaurado carga con el contenido del origen y sin origen.test', `${front.status}`);
}
check(!jsErrors.length, 'sin errores de JavaScript en la pantalla', jsErrors.join(' | '));
await browser.close();
log(`${mode}: ${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - started) / 1000)} s`);
process.exit(failures ? 1 : 0);
