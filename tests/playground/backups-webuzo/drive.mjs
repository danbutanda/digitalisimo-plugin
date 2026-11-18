// Ensayo de Webuzo con Chrome sin interfaz (puppeteer-core de PUPPETEER_PATH) contra el destino que levantó
// backups-webuzo.sh. Todo por la pantalla, como lo haría el propietario:
//   1. «Subir un respaldo» del .tar.gz de la cuenta: el panel lo reconoce como de Webuzo y ofrece «Importarlo ahora»;
//   2. la importación lo analiza y, con un solo WordPress, lo importa; con varios, se elige en «Respaldo de cuenta de
//      Webuzo» («Importar este WordPress»); queda en la lista como «Importado» (de Webuzo);
//   3. «Restaurar» de su fila escribiendo RESTAURAR SIN FIRMA: el sitio queda con el contenido del origen y la dirección
//      de aquí, la portada y la imagen cargan, nada menciona el origen, quien restaura sigue dentro y vuelve a entrar.
// Capturas a 1280 px (y la tarjeta a 390 px) en <out>/shots; cada comprobación en <out>/result.txt.
// Uso: node drive.mjs <fixture|real> <puerto> <host> <dir> <out> <archivo .tar.gz> [carpeta del WordPress]
import http from 'node:http';
import { appendFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [mode, port, host, dir, out, archive, wanted = ''] = process.argv.slice(2);
const real = mode === 'real';
const shots = `${out}/shots`;
mkdirSync(shots, { recursive: true });
const store = `${dir}/store`;
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const base = `http://${host}`;
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
const latest = (filter) => jobs().filter(filter).sort((a, b) => Number(b.queued_at || 0) - Number(a.queued_at || 0))[0] || {};

const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgwz-' : `${tmpdir()}/dgwz-`);
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
page.on('pageerror', (e) => { const text = String(e && e.message || e); if (!/ViewTransition|Transition was aborted/.test(text)) jsErrors.push(`${page.url()} · ${text.slice(0, 300)}`); });
page.on('dialog', async (d) => { await d.accept(); });
const screen = '/wp-admin/admin.php?page=digitalisimo-backups';
const cookieHeader = async () => (await page.cookies(base)).map((c) => `${c.name}=${c.value}`).join('; ');

async function shot(name, { width = 1280 } = {}) {
  if (width !== 1280) await page.setViewport({ width, height: 900 });
  await page.evaluate(() => window.scrollTo(0, 0));
  await sleep(400);
  await page.screenshot({ path: `${shots}/${mode}-${name}.png`, fullPage: true });
  if (width !== 1280) await page.setViewport({ width: 1280, height: 900 });
  log(`captura · ${mode}-${name}.png`);
}
async function login(label = 'inicio de sesión de admin en el destino') {
  for (let attempt = 0; attempt < 2 && !/wp-admin/.test(page.url()); attempt++) {
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) continue;
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  }
  return check(/wp-admin/.test(page.url()), label, page.url());
}
async function go(path) {
  const res = await page.goto(base + path, { waitUntil: 'load' });
  await sleep(800);
  return res ? res.status() : 0;
}
async function drive(label, seconds = 3600) {
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
const panelState = () => page.evaluate(() => {
  const p = document.getElementById('digitalisimo-backups-manual-progress');
  const q = (id) => document.getElementById('digitalisimo-backups-progress-' + id);
  const form = q('import-webuzo');
  return p ? { state: p.getAttribute('data-state'), text: q('title').textContent + '. ' + q('result').textContent, webuzo: Boolean(form && !form.hidden), backuply: Boolean(q('import') && !q('import').hidden) } : null;
}).catch(() => null);
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
// 1. Subirlo: se reconoce como de Webuzo (aunque es un .tar.gz como los de Backuply) y el panel ofrece importarlo.
await go(`${screen}&tab=tools&open=upload`);
const input = await page.$('#digitalisimo-backups-upload input[type=file]');
let panel = null;
if (check(Boolean(input), '«Subir un respaldo» ofrece elegir el archivo')) {
  await input.uploadFile(archive);
  await page.click('#digitalisimo-backups-upload button[type=submit]');
  for (let i = 0; i < 2400; i++) {
    await sleep(1500);
    panel = await panelState();
    const busy = await page.$eval('#digitalisimo-backups-upload button[type=submit]', (b) => b.disabled).catch(() => false);
    if (!busy && panel && ['success', 'failed', 'cancelled', 'paused'].includes(panel.state)) break;
  }
}
check(panel && /Respaldo de cuenta de Webuzo subido/.test(panel.text) && panel.webuzo && !panel.backuply, 'la subida se reconoce como un respaldo de cuenta de Webuzo y el panel ofrece «Importarlo ahora»', JSON.stringify(panel));
await shot('subido');
// 2. Importarlo desde el panel; si trae varios WordPress, elegir uno en su tarjeta.
if (panel && panel.webuzo) {
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click('#digitalisimo-backups-progress-import-webuzo button')]);
  let run = await drive('análisis e importación');
  let job = latest((j) => j.kind === 'import');
  if (job.status === 'success' && job.result?.webuzo?.choose) {
    const sites = job.result.webuzo.sites || [];
    log(`el respaldo trae ${sites.length} WordPress: ${sites.map((s) => `${s.root} (${s.importable ? 'importable' : s.problem})`).join('; ')}`);
    const target = sites.find((s) => s.root === wanted) || sites.find((s) => s.importable);
    await go(`${screen}&tab=tools&open=import`);
    await shot('elegir');
    const form = target ? await page.evaluateHandle((root) => [...document.querySelectorAll('form')].find((f) => f.querySelector(`input[name="webuzo_site"][value="${root}"]`)), target.root) : null;
    if (check(form && await form.evaluate((f) => Boolean(f)).catch(() => false), 'la tarjeta ofrece «Importar este WordPress»', target ? target.root : 'ninguno importable')) {
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), form.evaluate((f) => f.querySelector('button').click())]);
      run = await drive('importación del elegido');
      job = latest((j) => j.kind === 'import');
    }
  }
  const ticks = existsSync(`${store}/jobs/${job.id}/events.jsonl`) ? readFileSync(`${store}/jobs/${job.id}/events.jsonl`, 'utf8').split('\n').filter((line) => /"type":"phase"/.test(line)).length : 0;
  check(run.open === 0 && job.status === 'success' && job.result?.import?.file, `la importación termina en ${ticks} tramos (${run.seconds} s)`, JSON.stringify({ status: job.status, error: job.error, message: job.progress?.message }));
  if (job.result?.import) log(`importado: ${job.result.import.file} · ${job.result.import.tables} tablas, ${job.result.import.files} archivos, prefijo ${job.result.import.prefix} · avisos: ${(job.result.import.notices || []).join(' | ')}`);
}
let state = await probe();
const row = (state.history || []).find((r) => r.origin === 'imported' && r.import?.format === 'webuzo') || {};
check(row.file && (real || row.home === 'http://origen.test') && row.scope === 'installation', 'queda en la lista como «Importado» (de Webuzo)', JSON.stringify(state.history));
await go(screen);
const listed = row.file ? await rowOf(row.file) : null;
check(listed && /Importado/.test(listed.badge) && /Webuzo/.test(listed.title) && listed.restore, 'la lista muestra «Importado» (de Webuzo) con «Restaurar»', JSON.stringify(listed));
await shot('lista');
await go(`${screen}&tab=tools&open=import`);
await shot('tarjeta-390', { width: 390 });
check(!(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)), 'la pantalla no desborda a 1280 px');
// 3. Restaurarlo con RESTAURAR SIN FIRMA.
if (listed && listed.restore) {
  await go(`${screen}&restore=${encodeURIComponent(row.file)}`);
  const restorePanel = `#${listed.restore}`;
  await shot('confirmar');
  await page.type(`${restorePanel} input[name=confirmation]`, 'RESTAURAR SIN FIRMA');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click(`${restorePanel} button.button-primary`)]);
  const run = await drive('restauración');
  const job = latest((j) => j.kind !== 'import');
  const ticks = existsSync(`${store}/jobs/${job.id}/events.jsonl`) ? readFileSync(`${store}/jobs/${job.id}/events.jsonl`, 'utf8').split('\n').filter((line) => /"type":"phase"/.test(line)).length : 0;
  check(run.open === 0 && job.status === 'success', `la restauración termina, en ${ticks} tramos (${run.seconds} s)`, JSON.stringify({ kind: job.kind, status: job.status, error: job.error, message: job.progress?.message }));
  state = await probe();
  check(state.home === 'http://destino.test' && state.siteurl === 'http://destino.test' && state.blogname !== 'Destino antes de restaurar', 'el sitio queda con la dirección del destino y el nombre del origen', JSON.stringify({ blog: state.blogname, home: state.home, siteurl: state.siteurl, error: state.error }));
  if (!real) {
    check(state.blogname === 'Sitio de origen Webuzo' && (state.posts || []).includes('Entrada de Webuzo principal') && !(state.posts || []).includes('Entrada del destino'), 'el contenido es el del origen', JSON.stringify({ blog: state.blogname, posts: state.posts }));
    check(/http:\/\/destino\.test\/contacto\//.test(state.content) && !/origen\.test/.test(state.content), 'los enlaces y las imágenes del contenido apuntan al destino', state.content);
    check(state.demo && state.mu && (state.active || []).includes('demo-origen/demo-origen.php'), 'el plugin y el mu-plugin del origen están (y el plugin, activo)', JSON.stringify({ demo: state.demo, mu: state.mu, active: state.active }));
    const image = await request('GET', '/wp-content/uploads/2026/11/wz-principal.png', { binary: true });
    check(image.status === 200 && image.raw.slice(1, 4).toString() === 'PNG', 'la imagen de la biblioteca carga en el destino', String(image.status));
    for (const secret of ['/backuply-data/info', '/.acme.sh/origen.test/origen.test.key', '/ssl/keys/llave.key', '/mail/origen.test/dueno/cur/1:2,S']) {
      const leaked = await request('GET', secret);
      check(leaked.status === 404 || !/NO-VIAJA|no viaja/.test(leaked.text), `nada de la cuenta quedó en el sitio (${secret})`, String(leaked.status));
    }
  }
  await go(screen);
  check(/wp-admin/.test(page.url()) && !/wp-login/.test(page.url()), 'quien restauró sigue dentro', page.url());
  await shot('restaurado');
  const front = await request('GET', '/');
  check(front.status === 200 && !/Fatal error|critical error|error crítico/i.test(front.text) && (real || (/Entrada de Webuzo principal/.test(front.text) && !/origen\.test/.test(front.text))), 'la portada del sitio restaurado carga' + (real ? '' : ' con el contenido del origen y sin origen.test'), `${front.status} ${front.text.slice(0, 300)}`);
  // El administrador de este sitio sigue entrando: cerrar sesión y volver a entrar con su clave.
  await page.deleteCookie(...(await page.cookies(base)));
  await page.goto(`${base}/wp-login.php`, { waitUntil: 'load' }).catch(() => {});
  await login('el administrador de este sitio vuelve a entrar con su clave tras restaurar');
}
check(!jsErrors.length, 'sin errores de JavaScript en la pantalla', jsErrors.join(' | '));
await browser.close();
log(`${mode}: ${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - started) / 1000)} s`);
process.exit(failures ? 1 : 0);
