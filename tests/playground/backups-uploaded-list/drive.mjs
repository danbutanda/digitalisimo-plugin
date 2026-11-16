// Ensayo «lo subido queda en la lista» con Chrome sin interfaz (puppeteer-core de PUPPETEER_PATH) contra el Playground que
// levantó backups-uploaded-list.sh. Todo por la pantalla, como lo haría el propietario:
//   a   en el sitio A, «Respaldar ahora»; el ZIP sale del almacén para subirlo al sitio B.
//   b   en el sitio B (con su frase de respaldos: sus respaldos nuevos van firmados con su clave): un respaldo propio
//       con «Respaldar ahora», «Descargar» de su fila y «Borrar»; «Subir un respaldo» del ZIP de A → queda en la lista
//       («Subido», de sitio-a.test); «Restaurar» de su fila escribiendo RESTAURAR (sin la frase de sin firma) → el sitio
//       es el de A con las direcciones de B y quien restaura sigue dentro; «Restaurar» sólo la base de datos («Qué
//       restaurar»); «Borrar» de su fila; y el respaldo propio de B, descargado antes, vuelto a subir → «Subido · de este
//       mismo sitio (firmado con su clave)», cuenta como respaldo reciente válido y se restaura con RESTAURAR.
// Capturas a 1280 px (y la lista también a 390 px) en <out>/shots; el resultado de cada comprobación en <out>/result.txt.
// Uso: node drive.mjs <a|b> <puerto> <host> <dir> <out> [ZIP del sitio A]
import http from 'node:http';
import { appendFileSync, copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [phase, port, host, dir, out, zipA] = process.argv.slice(2);
const shots = `${out}/shots`;
mkdirSync(shots, { recursive: true });
const store = `${dir}/store`;
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const base = `http://${host}`;
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
const sha = (bytes) => createHash('sha256').update(bytes).digest('hex');
let failures = 0;
const log = (line) => { appendFileSync(`${out}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${phase} · ${what}`);
  else { failures++; log(`FALLA · ${phase} · ${what}${detail ? ` · ${String(detail).slice(0, 900)}` : ''}`); }
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
const probe = async (what = 'state', extra = '') => (await request('GET', `/?digi_uplist=${what}&token=${token}${extra}`)).json || {};
const jobs = () => (existsSync(`${store}/jobs`) ? readdirSync(`${store}/jobs`).map((id) => { try { return JSON.parse(readFileSync(`${store}/jobs/${id}/job.json`, 'utf8')); } catch { return null; } }).filter(Boolean) : []);
const terminal = (job) => job && ['success', 'failed', 'cancelled'].includes(job.status);
const zips = () => readdirSync(store).filter((name) => /^digitalisimo-.*\.(zip|dgbk)$/.test(name));

const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgu-' : `${tmpdir()}/dgu-`);
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
  await page.screenshot({ path: `${shots}/${phase}-${name}.png`, fullPage: true });
  if (width !== 1280) await page.setViewport({ width: 1280, height: 900 });
  log(`captura · ${phase}-${name}.png`);
}
async function login() {
  for (let attempt = 0; attempt < 2 && !/wp-admin/.test(page.url()); attempt++) {
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) continue;
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  }
  return check(/wp-admin/.test(page.url()), 'inicio de sesión de admin', page.url());
}
async function go(path) {
  const res = await page.goto(base + path, { waitUntil: 'load' });
  await sleep(800);
  return res ? res.status() : 0;
}
/** Hace avanzar los trabajos sin terminar como WP-Cron (y, en una restauración, con su testigo) hasta que terminan. */
async function drive(label, seconds = 900) {
  const started = Date.now();
  let last = null;
  while (Date.now() - started < seconds * 1000) {
    const open = jobs().filter((job) => !terminal(job));
    if (!open.length) break;
    last = open[0];
    const restoreCookie = (await page.cookies(base)).find((c) => c.name === 'digitalisimo_backups_restore');
    if (restoreCookie && ['restore'].includes(last.kind)) {
      await request('POST', `/wp-admin/admin-ajax.php?digitalisimo_backups_restore=${restoreCookie.value}`, { headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: await cookieHeader() }, body: new URLSearchParams({ action: 'digitalisimo_backups_restore_tick', digitalisimo_backups_restore: restoreCookie.value }).toString() });
    }
    await request('GET', '/wp-cron.php');
    await sleep(1500);
  }
  const done = jobs().sort((a, b) => Number(b.finished_at || 0) - Number(a.finished_at || 0));
  return { open: jobs().filter((job) => !terminal(job)).length, last: done[0] || last, seconds: Math.round((Date.now() - started) / 1000), label };
}
/** «Respaldar ahora» desde la pantalla; devuelve el archivo nuevo del almacén. */
async function backupNow() {
  const before = new Set(zips());
  await go(screen);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click('#digitalisimo-backups-manual-form button.button-hero')]);
  const run = await drive('respaldo');
  const fresh = zips().filter((name) => !before.has(name));
  check(run.open === 0 && fresh.length === 1, `«Respaldar ahora» crea el respaldo (${run.seconds} s)`, JSON.stringify({ run, fresh }));
  await go(screen);
  return fresh[0] || '';
}
/** La fila de la lista de un archivo, con su HTML y sus acciones. */
async function rowOf(file) {
  return page.evaluate((f) => {
    const row = [...document.querySelectorAll('tr.digitalisimo-backups-row')].find((tr) => tr.dataset.file === f);
    if (!row) return null;
    const restore = row.querySelector('[data-restore-toggle]');
    const download = [...row.querySelectorAll('a.button')].find((a) => /Descargar/.test(a.textContent));
    return { html: row.outerHTML, text: row.innerText, restore: restore ? restore.getAttribute('aria-controls') : '', download: download ? download.getAttribute('href') : '', uploaded: row.querySelector('.is-uploaded')?.getAttribute('title') || '' };
  }, file);
}
/** «Subir un respaldo» de Herramientas con el archivo; devuelve el texto del resultado. */
async function upload(path) {
  await go('/wp-admin/admin.php?page=digitalisimo-backups&tab=tools&open=upload');
  const input = await page.$('#digitalisimo-backups-upload input[type=file]');
  if (!check(Boolean(input), '«Subir un respaldo» ofrece elegir el archivo')) return '';
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
/** «Restaurar» de la fila: abre su panel, elige las partes (null: todo), escribe RESTAURAR y sigue la restauración. */
async function restoreRow(file, parts = null) {
  const before = new Set(jobs().map((job) => job.id));
  await go(`${screen}&restore=${encodeURIComponent(file)}`);
  const row = await rowOf(file);
  if (!check(row && row.restore, `la fila de ${file} ofrece «Restaurar»`)) return null;
  const panel = `#${row.restore}`;
  if (parts) {
    await page.$eval(`${panel} details[data-restore-parts]`, (d) => { d.open = true; });
    await page.click(`${panel} input[data-restore-all]`);
    for (const part of parts) {
      const box = await page.$(`${panel} input[name="restore_parts[]"][value="${part}"]`);
      const on = await box.evaluate((b) => b.checked);
      if (!on) await box.click();
    }
    const selected = await page.$$eval(`${panel} input[name="restore_parts[]"]`, (boxes) => boxes.filter((b) => b.checked).map((b) => b.value));
    check(JSON.stringify(selected) === JSON.stringify(parts), `«Qué restaurar» elige sólo ${parts.join(', ')}`, JSON.stringify(selected));
  }
  await page.type(`${panel} input[name=confirmation]`, 'RESTAURAR');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click(`${panel} button.button-primary`)]);
  const notice = await page.$eval('.digitalisimo-backups-result', (n) => n.textContent).catch(() => '');
  const run = await drive('restauración');
  const job = jobs().filter((j) => !before.has(j.id) && j.kind === 'restore').sort((a, b) => Number(b.queued_at || 0) - Number(a.queued_at || 0))[0] || null;
  return { notice, run, job };
}

const started = Date.now();
await login();
if (phase === 'a') {
  const file = await backupNow();
  await shot('respaldo-hecho');
  check(file && existsSync(`${store}/${file}`), 'el respaldo del sitio A está en su almacén', file);
  if (file) copyFileSync(`${store}/${file}`, `${out}/sitio-a.zip`);
} else {
  const key = await probe('passphrase');
  check(/^[0-9a-f]{8}$/.test(key.key || ''), 'el sitio B firma sus respaldos con su propia clave', JSON.stringify(key));
  // 1. Un respaldo propio de B: se descarga desde su fila (el handler real) y se borra de la lista.
  const own = await backupNow();
  let row = await rowOf(own);
  check(row && !row.uploaded, 'el respaldo propio de B está en la lista sin «Subido»', row?.text);
  const download = row?.download ? await request('GET', row.download.replace(base, ''), { headers: { Cookie: await cookieHeader() }, binary: true }) : { status: 0, raw: Buffer.alloc(0) };
  const ownBytes = existsSync(`${store}/${own}`) ? readFileSync(`${store}/${own}`) : Buffer.alloc(0);
  check(download.status === 200 && download.raw.length > 0 && sha(download.raw) === sha(ownBytes), '«Descargar» del respaldo propio de B da sus bytes exactos', `${download.status} ${download.raw.length}`);
  writeFileSync(`${out}/sitio-b-descargado.zip`, download.raw);
  await go(screen);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.$$eval('tr.digitalisimo-backups-row', (rows, f) => { const r = rows.find((tr) => tr.dataset.file === f); r.querySelector('form.digitalisimo-backups-inline button').click(); }, own)]);
  check(!existsSync(`${store}/${own}`) && !(await rowOf(own)), '«Borrar» quita de la lista y del almacén el respaldo propio de B');

  // 2. Subir el respaldo del sitio A: queda en la lista, «Subido», de sitio-a.test.
  const status = await upload(zipA);
  await shot('subida-de-a');
  check(/Respaldo subido y verificado/.test(status) && /Ya está en la lista de respaldos/.test(status) && /RESTAURAR\./.test(status) && !/SIN FIRMA|sin registro/.test(status), '«Subir un respaldo» deja el de A en la lista y pide RESTAURAR', status);
  check(await page.$eval('[data-upload-list]', (p) => !p.hidden).catch(() => false), 'la tarjeta ofrece «Ver en la lista de respaldos»');
  let state = await probe();
  const a = (state.history || []).find((r) => r.origin === 'uploaded' && /sitio-a/.test(r.home)) || {};
  check(a.file && a.size === statSync(zipA).size && a.home === 'http://sitio-a.test', 'la fila del subido lleva su tamaño y su origen (sitio-a.test)', JSON.stringify(state.history));
  await go(screen);
  row = await rowOf(a.file);
  check(row && /Subido/.test(row.text) && /sitio-a\.test/.test(row.uploaded) && row.restore && row.download, 'la lista muestra el de A con «Subido» (de sitio-a.test), Restaurar y Descargar', row?.text);
  await shot('lista');
  await shot('lista-390', { width: 390 });
  check(!(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)), 'la lista no desborda a 1280 px');

  // 3. Restaurarlo como uno hecho aquí: RESTAURAR, sin la frase de sin firma.
  let run = await restoreRow(a.file);
  state = await probe();
  check(run?.job?.status === 'success' && state.blogname === 'Sitio A' && state.posts.includes('Entrada del sitio A') && !state.posts.includes('Entrada del sitio B') && state.home === `http://${host}`, `restaurar el de A con RESTAURAR deja el sitio de A con las direcciones de B (${run?.run?.seconds} s)`, JSON.stringify({ job: run?.job?.status, error: run?.job?.error, blog: state.blogname, home: state.home, posts: state.posts }));
  check((run?.job?.spec?.source?.vouched || '') === 'uploaded' && (run?.job?.spec?.source?.trust || '') === 'untrusted', 'la restauración del subido se validó como archivo no confiable (subido, sin firma)', JSON.stringify(run?.job?.spec?.source));
  check(state.demo?.a && !state.demo?.b, 'los archivos son los del respaldo de A (su plugin de ejemplo; el de B se retiró)', JSON.stringify(state.demo));
  await go(screen);
  check(/wp-admin/.test(page.url()) && (await rowOf(a.file)), 'quien restauró sigue dentro y el subido sigue en la lista', page.url());
  await shot('restaurado-a');

  // 4. Restaurar por partes: sólo la base de datos.
  await probe('set', '&blogname=Cambiado%20por%20partes&demo=cambiado-despues');
  run = await restoreRow(a.file, ['database']);
  state = await probe();
  check(run?.job?.status === 'success' && state.blogname === 'Sitio A' && /cambiado-despues/.test(state.demo?.a || ''), `restaurar sólo la base de datos del subido no toca los archivos (${run?.run?.seconds} s)`, JSON.stringify({ job: run?.job?.status, error: run?.job?.error, blog: state.blogname, demo: state.demo }));
  await go(screen);
  await shot('restaurado-por-partes');

  // 5. Borrarlo como cualquier otro.
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.$$eval('tr.digitalisimo-backups-row', (rows, f) => { const r = rows.find((tr) => tr.dataset.file === f); r.querySelector('form.digitalisimo-backups-inline button').click(); }, a.file)]);
  check(!existsSync(`${store}/${a.file}`) && !(await rowOf(a.file)), '«Borrar» quita el subido de la lista y del almacén');

  // 6. El respaldo propio de B, descargado antes y vuelto a subir: de este mismo sitio, firmado con su clave.
  const again = await upload(`${out}/sitio-b-descargado.zip`);
  check(/Ya está en la lista de respaldos/.test(again) && /RESTAURAR\./.test(again), 'el respaldo propio de B vuelto a subir queda en la lista', again);
  state = await probe();
  const b = (state.history || []).find((r) => r.origin === 'uploaded') || {};
  check(b.same_site === true && b.signed === true && state.fresh === b.file, 'el de B vuelto a subir es «de este mismo sitio» (firmado con su clave) y cuenta como respaldo reciente válido', JSON.stringify({ b, fresh: state.fresh }));
  await go(screen);
  row = await rowOf(b.file);
  check(row && /de este mismo sitio \(firmado con su clave\)/.test(row.uploaded), 'la lista dice que el subido es de este mismo sitio', row?.uploaded);
  await shot('lista-propio-vuelto-a-subir');
  run = await restoreRow(b.file);
  state = await probe();
  check(run?.job?.status === 'success' && state.blogname === 'Sitio B' && state.posts.includes('Entrada del sitio B') && !state.posts.includes('Entrada del sitio A'), `restaurar el propio vuelto a subir devuelve el sitio B (${run?.run?.seconds} s)`, JSON.stringify({ job: run?.job?.status, error: run?.job?.error, blog: state.blogname, posts: state.posts }));
  await go(screen);
  await shot('final');
}
check(!jsErrors.length, 'sin errores de JavaScript en la pantalla', jsErrors.join(' | '));
await browser.close();
log(`${phase}: ${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - started) / 1000)} s`);
process.exit(failures ? 1 : 0);
