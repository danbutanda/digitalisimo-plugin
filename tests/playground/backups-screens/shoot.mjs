// Capturas de las pantallas de administración para backups-screens.sh, con Chrome sin interfaz (puppeteer-core de
// PUPPETEER_PATH). Inicia sesión como admin y recorre las pantallas del modo: Backuply 1.6.0 (sus pestañas y ventanas,
// sin respaldos, creando uno desde su botón y con él hecho) o DIGITALÍSIMO Backups (cada pestaña sin respaldos, un
// respaldo creado desde la pantalla con su progreso, otro más y cada pestaña con respaldos; en la red, además, la
// pantalla del sitio principal y la de un subsitio). Cada captura es de página completa a 1280 px y su medida va a
// <dir>/shots/<label>/metrics.json: alto, bloques, encabezados, botones, campos, avisos, palabras y textos más largos.
// Uso: node shoot.mjs <modo> <puerto> <host> <dir> <label>
import http from 'node:http';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [mode, port, host, dir, label] = process.argv.slice(2);
const setup = JSON.parse(readFileSync(`${dir}/run/probe/setup.json`, 'utf8'));
const shots = `${dir}/shots/${label}`;
mkdirSync(shots, { recursive: true });
const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const base = `http://${host}`;
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
const metrics = [];
const dialogs = [];
let failures = 0;
const fail = (what) => { failures++; console.log(`FALLA · ${what}`); };

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : (typeof body === 'string' ? body : JSON.stringify(body));
    const all = { Host: host, ...headers };
    if (data !== undefined) { all['Content-Length'] = Buffer.byteLength(data); all['Content-Type'] = all['Content-Type'] || 'application/json'; }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 900_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no es JSON */ }
        resolve({ status: res.statusCode, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}

// Perfil en una ruta corta: con un TMPDIR largo, Chrome muere con «Socket path too long» (su socket de proceso único).
const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgb-' : `${tmpdir()}/dgb-`);
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
// Los confirm() y alert() se anotan (cuentan como un paso más para el usuario) y se aceptan o se descartan.
// acceptDialogs: true, false o una función que decide por el mensaje.
let acceptDialogs = false;
page.on('dialog', async (d) => {
  dialogs.push({ type: d.type(), message: d.message().slice(0, 300), at: page.url().replace(base, '') });
  const accept = typeof acceptDialogs === 'function' ? acceptDialogs(d.message()) : acceptDialogs;
  if (accept) await d.accept(); else await d.dismiss();
});

/** Medida de lo que ve el usuario dentro de `scope` (sólo elementos visibles). */
function measure(scope) {
  const root = document.querySelector(scope) || document.querySelector('#wpbody-content') || document.body;
  // checkVisibility(): también lo de un <details> cerrado (content-visibility: hidden) cuenta como no visible.
  const visible = (el) => { if (typeof el.checkVisibility === 'function' && !el.checkVisibility({ visibilityProperty: true })) return false; const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none'; };
  const all = (selector) => [...new Set(root.querySelectorAll(selector))].filter(visible);
  const clean = (t) => String(t || '').replace(/\s+/g, ' ').trim();
  const text = clean(root.innerText);
  // Un texto es un bloque sin otros bloques dentro (un <td> con un formulario no es «un texto»: lo son sus párrafos).
  const leaf = (e) => !e.querySelector('p, div, ul, ol, table, form, details, section, fieldset, dl');
  const texts = all('p, li, label, td, th, h1, h2, h3, h4, summary, small, legend, dd, dt, .description').filter(leaf).map((e) => clean(e.innerText)).filter(Boolean);
  const longest = [...new Set(texts)].sort((a, b) => b.length - a.length).slice(0, 8).map((t) => ({ chars: t.length, text: t.slice(0, 500) }));
  return {
    page_height: document.documentElement.scrollHeight,
    scope_height: Math.round(root.getBoundingClientRect().height),
    words: (text.match(/[\p{L}\p{N}][\p{L}\p{N}'’._-]*/gu) || []).length,
    cards: all('.digitalisimo-backups-card, .digitalisimo-ui-card, .postbox, .backuply-settings-block').length,
    h2: all('h2').length,
    h3: all('h3').length,
    headings: all('h1, h2, h3').map((h) => `${h.tagName} ${clean(h.innerText).slice(0, 90)}`),
    buttons: all('button, input[type=submit], input[type=button], input[type=reset], a.button').length,
    fields: all('input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=reset]), select, textarea').length,
    notices: all('.notice, div.updated, div.error').length,
    tables: all('table').length,
    details: all('details').length,
    links: all('a:not(.button)').length,
    longest,
  };
}

async function shot(name, url, { scope = '.wrap', prepare = null, note = '', full = true } = {}) {
  try {
    if (url) {
      const res = await page.goto(base + url, { waitUntil: 'load' });
      if (!res || res.status() >= 400) fail(`${name}: ${url} respondió ${res ? res.status() : 'nada'}`);
    }
    await sleep(1500);
    if (prepare) await prepare();
    await sleep(700);
    // Un ancla (#…) desplaza la página y la barra fija de WordPress taparía las pestañas en la captura completa.
    await page.evaluate(() => window.scrollTo(0, 0));
    await sleep(300);
    const m = await page.evaluate(measure, scope);
    await page.screenshot({ path: `${shots}/${name}.png`, fullPage: full });
    metrics.push({ name, url: url || page.url().replace(base, ''), note, ...m });
    console.log(`ok · ${name} · ${m.page_height}px · ${m.cards} bloques · ${m.buttons} botones · ${m.fields} campos · ${m.words} palabras`);
  } catch (e) {
    fail(`${name}: ${String(e.message || e).slice(0, 300)}`);
  }
}

async function login(path = '/wp-login.php') {
  // Un intento más: el primer arranque de Playground en red a veces responde al login sin la cookie de sesión.
  let response = null;
  for (let attempt = 0; attempt < 2 && !/wp-admin/.test(page.url()); attempt++) {
    await page.goto(base + path, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) continue;
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  }
  if (!/wp-admin/.test(page.url())) {
    fail(`no se inició sesión (${page.url()}, HTTP ${response ? response.status() : '?'})`);
    writeFileSync(`${shots}/login-falla.html`, await page.content());
    const cookies = await page.cookies();
    writeFileSync(`${shots}/login-falla.json`, JSON.stringify({ url: page.url(), status: response ? response.status() : null, headers: response ? response.headers() : null, cookies: cookies.map((c) => c.name) }, null, 1));
  }
}

// ── Backuply 1.6.0 ──────────────────────────────────────────────────────────────────────────────────────────────────
const backuplyTabs = ['dashboard', 'settings', 'location', 'history', 'support', 'pro'];
async function backuplyTab(tab) {
  await page.evaluate((t) => { location.hash = `#backuply-${t}`; const a = document.querySelector(`a[href="#backuply-${t}"]`); if (a) a.click(); window.scrollTo(0, 0); }, tab);
}
async function backuplyScreens(state) {
  for (const tab of backuplyTabs) {
    await shot(`backuply-${state}-${tab}`, '/wp-admin/admin.php?page=backuply', { scope: `#backuply-${tab}`, prepare: () => backuplyTab(tab) });
  }
}
const token = () => readFileSync(`${dir}/run/web/token.txt`, 'utf8').trim();
async function backuplyDrive() {
  let state = null;
  for (let i = 0; i < 300; i++) {
    state = (await request('GET', `/wp-content/digi-web/backuply-state.php?token=${token()}`)).json;
    if (!state?.running) break;
    await request('GET', `/wp-content/digi-web/backuply-run.php?token=${token()}`);
  }
  return state;
}

async function backuply() {
  await login();
  await backuplyScreens('sin');
  await shot('backuply-sin-location-anadir', '/wp-admin/admin.php?page=backuply', { scope: '#add_backup_loc_form', note: '«Add Backup Location» abre esta ventana', prepare: async () => { await backuplyTab('location'); await page.click('#add_backup_loc_btn'); } });
  await shot('backuply-sin-location-anadir-gdrive', null, { scope: '#add_backup_loc_form', note: 'la ventana con Google Drive elegido (la versión gratuita ofrece FTP, Google Drive y Backuply Cloud)', prepare: async () => { await page.select('#add_backup_loc_form [name="protocol"]', 'gdrive').catch(() => fail('sin selector de protocolo en la ventana')); } });
  await shot('backuply-licencia', '/wp-admin/admin.php?page=backuply-license', { scope: '#wpbody-content' });
  // «Create Backup» desde su botón: la ventana de progreso avanza con los tramos que el ensayo pide (sin loopback).
  await page.goto(`${base}/wp-admin/admin.php?page=backuply`, { waitUntil: 'load' });
  await sleep(1200);
  acceptDialogs = true;
  await page.evaluate(() => { for (const id of ['backup_dir', 'backup_db']) { const c = document.getElementById(id); if (c) { c.disabled = false; c.checked = true; } } });
  await page.click('[name="backuply_create_backup"]');
  await sleep(4000);
  const started = (await request('GET', `/wp-content/digi-web/backuply-state.php?token=${token()}`)).json;
  if (!started?.running) await request('GET', `/wp-content/digi-web/backuply-run.php?token=${token()}&start=1`);
  await request('GET', `/wp-content/digi-web/backuply-run.php?token=${token()}`);
  await sleep(3000);
  await shot('backuply-creando', null, { scope: '#backuply-backup-progress', note: 'ventana de progreso de «Create Backup»', full: false });
  const done = await backuplyDrive();
  if (!done?.files?.length || done.running) fail(`Backuply no terminó su respaldo: ${JSON.stringify(done).slice(0, 500)}`);
  await sleep(6000);
  await shot('backuply-terminado', null, { scope: '#backuply-backup-progress', note: 'ventana de progreso al terminar', full: false });
  acceptDialogs = false;
  await backuplyScreens('con');
  // «Restore» de una fila: dos confirm() antes de empezar; se acepta el primero y se descarta el último (no se restaura).
  acceptDialogs = (message) => !/last confirmation/i.test(message);
  await shot('backuply-con-history-restaurar', '/wp-admin/admin.php?page=backuply', { scope: '#backuply-history', note: 'clic en «Restore»: dos confirm() (el último, descartado)', prepare: async () => { await backuplyTab('history'); const b = await page.$('.backuply-restore-btn'); if (b) await b.click(); else fail('no hay botón «Restore» en el historial'); } });
  acceptDialogs = false;
}

// ── DIGITALÍSIMO Backups ────────────────────────────────────────────────────────────────────────────────────────────
/** Pestañas que ofrece la pantalla (las de antes de 1.0.74 o las de la «interfaz simple»); [] sin navegación. */
async function screenTabs(adminPath) {
  await page.goto(`${base}${adminPath}`, { waitUntil: 'load' });
  return page.$$eval('.digitalisimo-backups-tabs a', (links) => links.map((a) => new URL(a.href).searchParams.get('tab')).filter(Boolean));
}
async function backupsScreens(prefix, adminPath, state) {
  const tabs = await screenTabs(adminPath);
  const simple = tabs.includes('tools');
  for (const tab of tabs) await shot(`${prefix}-${state}-${tab}`, `${adminPath}&tab=${tab}`, { scope: '.wrap' });
  // El formulario de un destino con FTP (SFTP es el tipo por defecto): antes, en la pestaña; ahora, tras «Añadir destino».
  await shot(`${prefix}-${state}-servers-ftp`, `${adminPath}&tab=servers${simple ? '&add=1' : ''}`, { scope: '.wrap', note: 'formulario de un destino con FTP elegido', prepare: async () => {
    const select = await page.$('select.digitalisimo-backups-type');
    if (select) await page.select('select.digitalisimo-backups-type', 'ftp'); else fail(`${prefix}: sin selector de tipo en el formulario de un destino`);
  } });
  if (!simple) return;
  await shot(`${prefix}-${state}-servers-sftp-abierto`, `${adminPath}&tab=servers&add=1`, { scope: '.wrap', note: '«Añadir destino» con SFTP (el tipo por defecto)' });
  await shot(`${prefix}-${state}-tools-migrate`, `${adminPath}&tab=tools&open=migrate`, { scope: '.wrap', note: '«Herramientas» con «Migrar» abierto' });
  if (state !== 'con') return;
  // «Restaurar» de la primera fila: el panel bajo la fila (JavaScript), sin recargar.
  await shot(`${prefix}-${state}-restaurar`, `${adminPath}&tab=backups`, { scope: '.wrap', note: '«Restaurar» abierto bajo su fila', prepare: async () => {
    const link = await page.$('[data-restore-toggle]');
    if (link) await link.click(); else fail(`${prefix}: la fila no tiene «Restaurar»`);
  } });
}
async function backupFromScreen(prefix, adminPath, capture) {
  await page.goto(`${base}${adminPath}&tab=backups`, { waitUntil: 'load' });
  await sleep(1200);
  const button = await page.$('#digitalisimo-backups-manual-form button');
  if (!button) { fail(`${prefix}: sin botón de respaldo`); return; }
  await button.click();
  if (capture) { await sleep(5000); await shot(`${prefix}-creando`, null, { scope: '.wrap', note: 'progreso del respaldo desde el botón' }); }
  // Antes, «Actualiza la página…»; desde 1.0.74 la página se recarga sola con el respaldo en la lista.
  try {
    await Promise.race([
      page.waitForNavigation({ waitUntil: 'load', timeout: 900_000 }),
      page.waitForFunction(() => /Actualiza la página|No se registró/.test(document.getElementById('digitalisimo-backups-manual-detail')?.textContent || ''), { timeout: 900_000, polling: 2000 }),
    ]);
  } catch (e) { fail(`${prefix}: el respaldo desde la pantalla no terminó (${String(e.message || e).slice(0, 200)})`); }
  if (capture) await shot(`${prefix}-terminado`, null, { scope: '.wrap', note: 'al terminar el respaldo' });
}

async function backups() {
  await login();
  if (mode === 'single') {
    const admin = '/wp-admin/admin.php?page=digitalisimo-backups';
    await backupsScreens('backups-sitio', admin, 'sin');
    await backupFromScreen('backups-sitio', admin, true);
    await backupFromScreen('backups-sitio', admin, false);
    await backupsScreens('backups-sitio', admin, 'con');
  } else {
    const net = '/wp-admin/network/admin.php?page=digitalisimo-network-backups';
    const main = '/wp-admin/admin.php?page=digitalisimo-backups';
    const sub = '/uno/wp-admin/admin.php?page=digitalisimo-backups';
    await backupsScreens('backups-red', net, 'sin');
    await shot('backups-principal-sin-backups', `${main}&tab=backups`);
    await shot('backups-subsitio-sin-backups', `${sub}&tab=backups`);
    await backupFromScreen('backups-red', net, true);
    await backupFromScreen('backups-red', net, false);
    await backupsScreens('backups-red', net, 'con');
    for (const tab of await screenTabs(main)) await shot(`backups-principal-con-${tab}`, `${main}&tab=${tab}`);
    const subTabs = await screenTabs(sub);
    if (!subTabs.length) await shot('backups-subsitio-con-backups', sub);
    for (const tab of subTabs) await shot(`backups-subsitio-con-${tab}`, `${sub}&tab=${tab}`);
  }
}

try {
  console.log(`· ${mode}: ${setup.versions}`);
  if (mode === 'backuply') await backuply(); else await backups();
} catch (e) {
  fail(String(e.stack || e).slice(0, 600));
} finally {
  writeFileSync(`${shots}/metrics.json`, JSON.stringify({ mode, label, versions: setup.versions, width: 1280, dialogs, js_errors: jsErrors, screens: metrics }, null, 1));
  await browser.close();
  rmSync(profile, { recursive: true, force: true });
}
if (jsErrors.length) console.log(`AVISO · errores de JavaScript: ${jsErrors.slice(0, 5).join(' | ')}`);
console.log(failures ? `FALLA · ${failures} problemas en ${mode}` : `ok · ${mode}: ${metrics.length} capturas`);
process.exit(failures ? 1 : 0);
