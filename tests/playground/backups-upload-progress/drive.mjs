// Ensayo «subir e importar con el panel de avance» con Chrome sin interfaz (puppeteer-core de PUPPETEER_PATH) contra el
// Playground que levantó backups-upload-progress.sh. Todo por la pantalla, como lo haría el propietario:
//   1  «Respaldar ahora»; el ZIP sale del almacén (el respaldo «de otro servidor») y su fila se borra con «Borrar»;
//   2  «Subir un respaldo» con los fragmentos frenados: el panel (en su sección) pinta «Subida» con bytes, velocidad y
//      tiempo restante; a mitad se recarga (el panel dice «Subida en pausa»), se elige el mismo archivo y sigue desde lo
//      recibido (sin volver al byte 0); la «Comprobación» (frenada al empezar) es indeterminada y se recarga otra vez a
//      mitad: el panel vuelve a ella; el resultado es «Respaldo subido y verificado» con su fila, y «Restaurarlo» abre su
//      fila en la lista; la barra nunca retrocede;
//   3  importar el respaldo de Backuply de su carpeta y subir otro y pulsar «Importarlo ahora»: cada importación se sigue
//      en el panel, en la sección «Importar», por sus fases, hasta su resultado.
// Capturas a 1 280 y 390 px en <out>/shots; cada estado del panel en <out>/samples.jsonl; cada comprobación en
// <out>/result.txt. Uso: node drive.mjs <puerto> <host> <dir> <out>
import http from 'node:http';
import { appendFileSync, copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [port, host, dir, out] = process.argv.slice(2);
const shots = `${out}/shots`;
mkdirSync(shots, { recursive: true });
const store = `${dir}/store`;
const ctl = `${dir}/ctl`;
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const base = `http://${host}`;
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
let failures = 0;
const log = (line) => { appendFileSync(`${out}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 1200)}` : ''}`); }
  return ok;
};
const setCtl = (name, value) => writeFileSync(`${ctl}/${name}`, String(value));
const chunkLog = () => (existsSync(`${ctl}/chunks.log`) ? readFileSync(`${ctl}/chunks.log`, 'utf8').split('\n').filter(Boolean).map(Number) : []);

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve) => {
    const all = { Host: host, ...headers };
    if (body !== undefined) all['Content-Length'] = Buffer.byteLength(body);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no es JSON */ }
        resolve({ status: res.statusCode, text, json });
      });
    });
    req.on('error', (error) => resolve({ status: 0, text: String(error.message), json: null }));
    req.on('timeout', () => req.destroy(new Error('tiempo agotado')));
    if (body !== undefined) req.write(body);
    req.end();
  });
}
const probe = async () => (await request('GET', `/?digi_upbar=state&token=${token}`)).json || {};
const zips = () => readdirSync(store).filter((name) => /^digitalisimo-.*\.(zip|dgbk)$/.test(name));

const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgp-' : `${tmpdir()}/dgp-`);
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
// Las transiciones de vista del escritorio de WordPress que el navegador aborta al recargar no son del panel.
page.on('pageerror', (e) => { const text = String(e && e.message || e); if (!/ViewTransition|Transition was aborted/.test(text)) jsErrors.push(`${page.url()} · ${text.slice(0, 300)}${e && e.stack ? ' · ' + String(e.stack).replace(/\s+/g, ' ').slice(0, 400) : ''}`); });
page.on('dialog', async (d) => { await d.accept(); });
const screen = '/wp-admin/admin.php?page=digitalisimo-backups';

async function login() {
  for (let attempt = 0; attempt < 3 && !/wp-admin/.test(page.url()); attempt++) {
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) continue;
    await page.$eval('#user_login', (el) => { el.value = 'admin'; });
    await page.$eval('#user_pass', (el) => { el.value = 'password'; });
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  }
  return check(/wp-admin/.test(page.url()), 'inicio de sesión de admin', page.url());
}
async function go(path) {
  const res = await page.goto(base + path, { waitUntil: 'load' });
  await sleep(800);
  return res ? res.status() : 0;
}
/** El estado del panel de avance tal como se ve. */
async function snap() {
  return page.evaluate(() => {
    const panel = document.getElementById('digitalisimo-backups-manual-progress');
    if (!panel) return null;
    const q = (id) => document.getElementById('digitalisimo-backups-progress-' + id);
    const bar = document.getElementById('digitalisimo-backups-manual-bar');
    const go = q('go');
    const imported = q('import');
    return {
      hidden: panel.hidden, state: panel.getAttribute('data-state'), kind: panel.getAttribute('data-kind'), title: q('title').textContent, percent: q('percent').textContent,
      now: bar.getAttribute('aria-valuenow'), valuetext: bar.getAttribute('aria-valuetext'), waiting: bar.hasAttribute('data-waiting'), role: bar.getAttribute('role'),
      phases: [...q('phases').children].map((li) => `${li.dataset.phase}:${li.dataset.state}`).join(','), message: document.getElementById('digitalisimo-backups-manual-message').textContent,
      detail: document.getElementById('digitalisimo-backups-manual-detail').textContent, time: q('time').textContent, note: q('note').textContent, result: q('result').textContent,
      go: go && !go.hidden ? `${go.textContent} ${go.getAttribute('href')}` : '', imports: imported ? !imported.hidden : false, cancel: !q('cancel').hidden, resume: !q('resume').hidden,
      slot: panel.parentElement && panel.parentElement.dataset ? panel.parentElement.dataset.progressSlot || '' : '', width: Math.round(panel.getBoundingClientRect().width),
      overflow: document.documentElement.scrollWidth > window.innerWidth + 1, url: location.href,
    };
  }).catch(() => null);
}
const samples = [];
/** Muestrea el panel cada $every ms hasta que $done(estado) o pasen $seconds. */
async function sampleUntil(done, { label, seconds = 600, every = 400, onSample = null } = {}) {
  const started = Date.now();
  let last = null;
  while (Date.now() - started < seconds * 1000) {
    const s = await snap();
    if (s) {
      s.t = Date.now();
      s.label = label;
      samples.push(s);
      appendFileSync(`${out}/samples.jsonl`, `${JSON.stringify(s)}\n`);
      last = s;
      if (onSample) await onSample(s);
      if (done(s)) return s;
    }
    await sleep(every);
  }
  return last;
}
/** Captura de la página entera y del panel; a 390 px, además, que nada desborde. */
async function shot(name, { width = 1280 } = {}) {
  if (width !== 1280) { await page.setViewport({ width, height: 900 }); await sleep(500); }
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1).catch(() => true);
  await page.screenshot({ path: `${shots}/${name}.png`, fullPage: true });
  const panel = await page.$('#digitalisimo-backups-manual-progress');
  if (panel) await panel.screenshot({ path: `${shots}/${name}-panel.png` }).catch(() => {});
  if (width !== 1280) {
    check(!overflow, `a ${width} px no hay desplazamiento horizontal (${name})`);
    await page.setViewport({ width: 1280, height: 900 });
    await sleep(300);
  }
  log(`captura · ${name}.png`);
}
const pct = (s) => parseInt(String(s && s.percent || ''), 10);
const monotonic = (list) => list.every((value, index) => !index || value >= list[index - 1]);
const terminal = (s) => s && ['success', 'failed', 'cancelled', 'paused'].includes(s.state);
async function backupNow() {
  const before = new Set(zips());
  await go(screen);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 900_000 }).catch(() => null), page.click('#digitalisimo-backups-manual-form button.button-hero')]);
  const started = Date.now();
  while (Date.now() - started < 900_000 && !zips().some((name) => !before.has(name))) { await request('GET', '/wp-cron.php'); await sleep(1500); }
  for (let i = 0; i < 120 && (await probe()).jobs?.some((job) => !['success', 'failed', 'cancelled'].includes(job.status)); i++) { await request('GET', '/wp-cron.php'); await sleep(1500); }
  const fresh = zips().filter((name) => !before.has(name));
  check(fresh.length === 1, `«Respaldar ahora» deja un respaldo (${Math.round((Date.now() - started) / 1000)} s)`, JSON.stringify(fresh));
  return fresh[0] || '';
}
async function chooseAndSend(file) {
  const input = await page.$('#digitalisimo-backups-upload input[type=file]');
  if (!check(Boolean(input), '«Subir un respaldo» ofrece elegir el archivo')) return false;
  await input.uploadFile(file);
  await page.click('#digitalisimo-backups-upload button[type=submit]');
  return true;
}

const t0 = Date.now();
await login();

// 1. El respaldo «de otro servidor»: se hace aquí, se saca del almacén y se borra de la lista.
const made = await backupNow();
const source = `${out}/respaldo-de-otro-servidor.zip`;
if (made) copyFileSync(`${store}/${made}`, source);
const size = existsSync(source) ? statSync(source).size : 0;
check(size > 20 * 1048576, `el respaldo pesa varias decenas de MB (${(size / 1048576).toFixed(1)} MB)`);
await go(screen);
await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.$$eval('tr.digitalisimo-backups-row', (rows, f) => { const r = rows.find((tr) => tr.dataset.file === f); r.querySelector('form.digitalisimo-backups-inline button').click(); }, made)]);
check(!existsSync(`${store}/${made}`), '«Borrar» lo quita del almacén y de la lista');

// 2. Subirlo con los fragmentos frenados, recargando a media subida y a media comprobación.
setCtl('chunk-delay-ms', 350);
setCtl('check-delay', 15);
await go(`${screen}&tab=tools&open=upload`);
let startShot = false;
await chooseAndSend(source);
const firstRun = await sampleUntil((s) => s.phases.startsWith('upload:current') && pct(s) >= 40, {
  label: 'subida',
  onSample: async (s) => {
    if (!startShot && s.phases.startsWith('upload:current') && pct(s) >= 15 && /\/s · quedan unos/.test(s.detail)) { startShot = true; await shot('subida-1280'); await shot('subida-390', { width: 390 }); }
  },
});
check(firstRun && firstRun.slot === 'upload' && !firstRun.hidden && firstRun.role === 'progressbar' && firstRun.now === String(pct(firstRun)), 'el panel sigue la subida en su sección, con la barra accesible', JSON.stringify(firstRun));
check(firstRun && firstRun.title === 'Subiendo el respaldo' && /^En curso · Subida \(fase 1 de 3\)$/.test(firstRun.message) && /^\d+,\d MB de \d+,\d MB · \d+(,\d)? (MB|KB)\/s · quedan unos /.test(firstRun.detail), 'la subida dice bytes, velocidad y tiempo restante', JSON.stringify(firstRun));
check(startShot, 'hay captura de la subida en curso');
const atReload = (await probe()).uploads?.find((u) => u.phase === 'uploading') || {};
const chunksBefore = chunkLog().length;
await page.reload({ waitUntil: 'load' });
await sleep(1000);
const paused = await snap();
check(paused && paused.title === 'Subida en pausa' && /recibidos de respaldo-de-otro-servidor\.zip/.test(paused.result) && /vuelve a elegir el mismo archivo/.test(paused.result) && pct(paused) >= pct(firstRun) && paused.slot === 'upload' && paused.cancel, 'al recargar a media subida el panel la enseña en pausa, sin bajar la barra', JSON.stringify(paused));
await shot('pausa-1280');
await chooseAndSend(source);
let checkShot = false;
const checking = await sampleUntil((s) => s.waiting && s.phases === 'upload:done,check:current,register:pending', {
  label: 'subida-2',
  seconds: 900,
});
const resumedOffsets = chunkLog().slice(chunksBefore);
check(resumedOffsets.length > 0 && resumedOffsets[0] >= (atReload.received || 1) && !resumedOffsets.includes(0), `elegir el mismo archivo sigue desde lo recibido (${(resumedOffsets[0] / 1048576).toFixed(1)} MB), sin volver a empezar`, JSON.stringify({ atReload, first: resumedOffsets.slice(0, 3) }));
if (checking) { checkShot = true; await shot('comprobacion-1280'); await shot('comprobacion-390', { width: 390 }); }
check(checking && checking.now === null && /sin pasos medibles/.test(checking.valuetext || '') && checking.title === 'Comprobando el respaldo subido' && !checking.cancel && /^Verificando · Comprobación \(fase 2 de 3\)$/.test(checking.message), 'la comprobación es indeterminada, se explica y no ofrece «Cancelar»', JSON.stringify(checking));
await sleep(2500);
await page.reload({ waitUntil: 'load' });
await sleep(1000);
const back = await snap();
check(back && back.title === 'Comprobando el respaldo subido' && back.waiting && back.phases === 'upload:done,check:current,register:pending' && back.slot === 'upload' && /lleva/.test(back.time), 'al recargar a media comprobación el panel vuelve a ella', JSON.stringify(back));
await shot('recarga-comprobacion-1280');
const done = await sampleUntil((s) => terminal(s), { label: 'comprobacion', seconds: 900 });
check(done && done.state === 'success' && done.title === 'Respaldo subido y verificado' && done.percent === '100 %' && done.phases === 'upload:done,check:done,register:done', 'la subida termina «Respaldo subido y verificado» al 100 % con sus tres fases', JSON.stringify(done));
check(done && /Ya está en la lista de respaldos, arriba, marcado «Subido»/.test(done.result) && /Su fila: \d{4}-\d{2}-\d{2} \d{2}:\d{2} · /.test(done.result) && /Para restaurarlo escribe RESTAURAR\./.test(done.result) && /^Restaurarlo .*tab=backups.*restore=/.test(done.go), 'el resultado dice su fila en la lista y ofrece «Restaurarlo»', JSON.stringify(done));
const steps = samples.filter((s) => s.label === 'comprobacion' && s.waiting).map((s) => s.detail);
check(steps.some((d) => /Verificando archivo \d+ de \d+|Comprobando cada archivo|Abriendo el ZIP|Registrándolo|registrándolo/.test(d)), 'durante la comprobación el panel dice en qué paso va', JSON.stringify([...new Set(steps)].slice(0, 8)));
await shot('resultado-1280');
await shot('resultado-390', { width: 390 });
const run = samples.filter((s) => ['subida', 'subida-2', 'comprobacion'].includes(s.label)).map(pct).filter((n) => !Number.isNaN(n));
check(monotonic(run), 'la barra nunca retrocede en toda la subida (también al recargar)', JSON.stringify(run));
const state = await probe();
const row = (state.history || []).find((r) => r.origin === 'uploaded');
check(row && row.size === size, 'el respaldo subido está en la lista con su tamaño', JSON.stringify(state.history));
await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#digitalisimo-backups-progress-go')]);
await sleep(800);
const opened = await page.evaluate((f) => {
  const tr = [...document.querySelectorAll('tr.digitalisimo-backups-row')].find((r) => r.dataset.file === f);
  const toggle = tr && tr.querySelector('[data-restore-toggle]');
  const panel = toggle && document.getElementById(toggle.getAttribute('aria-controls'));
  return { row: Boolean(tr), uploaded: Boolean(tr && tr.querySelector('.is-uploaded')), expanded: toggle && toggle.getAttribute('aria-expanded'), visible: Boolean(panel && !panel.hidden) };
}, row ? row.file : '');
check(opened.row && opened.uploaded && opened.expanded === 'true' && opened.visible, '«Restaurarlo» abre su fila «Subido» en la lista con «Restaurar» desplegado', JSON.stringify(opened));
await shot('fila-1280');

// 3. Importar un respaldo de Backuply: desde su carpeta y, otro, subido y con «Importarlo ahora».
setCtl('chunk-delay-ms', 0);
setCtl('check-delay', 0);
setCtl('tick-budget', 3);
async function followImport(label) {
  let shotTaken = false;
  const end = await sampleUntil((s) => terminal(s), {
    label,
    seconds: 900,
    onSample: async (s) => { if (!shotTaken && !s.hidden && /files:current|database:current/.test(s.phases)) { shotTaken = true; await shot(`${label}-en-curso-1280`); await shot(`${label}-en-curso-390`, { width: 390 }); } },
  });
  const seen = samples.filter((s) => s.label === label);
  const percents = seen.map(pct).filter((n) => !Number.isNaN(n));
  check(seen.length && seen.every((s) => s.slot === 'import'), `${label}: el panel la sigue en la sección «Importar»`, JSON.stringify(seen.map((s) => s.slot)));
  check(seen.some((s) => s.title === 'Importación en curso' && /fase \d de 5/.test(s.message)) && monotonic(percents), `${label}: se ve por sus fases y la barra no retrocede`, JSON.stringify({ percents, phases: [...new Set(seen.map((s) => s.phases))] }));
  check(end && end.title === 'Importación terminada' && /importado como digitalisimo-backuply-/.test(end.result) && /^Ver en «Restaurar un archivo suelto» .*open=restore-file/.test(end.go), `${label}: termina con lo importado y dónde quedó`, JSON.stringify(end));
  await shot(`${label}-terminada-1280`);
  return end;
}
await go(`${screen}&tab=tools&open=import`);
const [, fromFolder] = await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.evaluate(() => {
  const form = [...document.querySelectorAll('#digitalisimo-backups-backuply form')].find((f) => f.querySelector('input[name="backuply_source"][value="backuply"]'));
  if (!form) return false;
  form.querySelector('button').click();
  return true;
})]);
check(fromFolder, 'la sección «Importar» ofrece el respaldo de la carpeta de Backuply');
await sleep(800);
await followImport('importacion');
await go(`${screen}&tab=tools&open=upload`);
await chooseAndSend(`${out}/wp_otro.example_2026-11-16_11-00-00.tar.gz`);
const uploadedTar = await sampleUntil((s) => terminal(s), { label: 'subida-backuply', seconds: 600 });
check(uploadedTar && uploadedTar.title === 'Respaldo de Backuply subido' && uploadedTar.imports && /impórtalo para convertirlo en un ZIP de Backups/.test(uploadedTar.result), 'tras subir un .tar.gz de Backuply el panel ofrece «Importarlo ahora»', JSON.stringify(uploadedTar));
await shot('backuply-subido-1280');
await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#digitalisimo-backups-progress-import button')]);
await sleep(800);
await followImport('importacion-subida');
const final = await probe();
const imports = (final.jobs || []).filter((job) => job.kind === 'import');
check(imports.length === 2 && imports.every((job) => job.status === 'success') && imports.every((job) => (final.files || []).includes(job.file)), 'las dos importaciones dejaron su ZIP en el almacén', JSON.stringify({ imports, files: final.files }));

check(!jsErrors.length, 'sin errores de JavaScript en la pantalla', jsErrors.join(' | '));
await browser.close();
log(`${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - t0) / 1000)} s`);
process.exit(failures ? 1 : 0);
