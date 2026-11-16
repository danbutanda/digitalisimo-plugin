// Ensayo del panel de avance (backups-restore-progress.sh) con Chrome sin interfaz contra el Playground que levantó el
// script: todo desde la pantalla Backups, como un usuario, y nada más hace avanzar la cola (sin loopback de WP-Cron).
//   single     1) «Respaldar ahora» (1 280 px): el panel grande aparece bajo el botón, avanza por las fases del respaldo
//                 y la lista se recarga con el respaldo;
//              2) restauración completa desde su fila (1 280 px), con otra sesión que el cambio de tablas deja sin valor:
//                 el panel dice al instante que empieza, avanza fase por fase (Verificación del respaldo … Limpieza),
//                 vuelve tras recargar la página a mitad (en «Base de datos», ya en mantenimiento), sigue con el testigo
//                 tras el cambio de tablas y termina con «Restauración terminada»; sin sesión ofrece «Iniciar sesión» y, al
//                 entrar otra vez, la pantalla enseña el resultado completo;
//              3) restauración por partes (sólo plugins) a 390 px: sin desplazamiento horizontal, dice qué se restaura y
//                 termina con el resultado y «Volver a la copia previa»;
//              4) restauración (plugins) con una falla inyectada al mover el código (1 280 px): el panel sigue la vuelta
//                 a la copia previa y termina diciendo que el sitio volvió a como estaba y por qué falló.
//   multisite  1) y 3) en la administración de la red.
// Capturas (página y panel) en <dir>/shots; las muestras del panel, en <dir>/shots/samples.json.
// Uso: node drive.mjs <puerto> <host> <dir> <single|multisite>
import http from 'node:http';
import { appendFileSync, copyFileSync, existsSync, mkdtempSync, readFileSync, rmSync, unlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [port, host, dir, mode] = process.argv.slice(2);
const network = mode === 'multisite';
const shots = `${dir}/shots`;
const base = `http://${host}`;
const screen = network ? '/wp-admin/network/admin.php?page=digitalisimo-network-backups' : '/wp-admin/admin.php?page=digitalisimo-backups';
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
let failures = 0;
const log = (line) => { appendFileSync(`${shots}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 1500)}` : ''}`); }
  return ok;
};
function request(method, path) {
  return new Promise((resolve) => {
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: { Host: host }, timeout: 120_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => { const text = Buffer.concat(chunks).toString('utf8'); let json = null; try { json = JSON.parse(text); } catch { /* página */ } resolve({ status: res.statusCode, text, json }); });
    });
    req.on('error', (error) => resolve({ status: 0, text: String(error.message), json: null }));
    req.on('timeout', () => req.destroy(new Error('tiempo agotado')));
    req.end();
  });
}
const lab = async (what, extra = '') => (await request('GET', `/?digi_lab=${what}&token=${token}${extra}`)).json;

const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
// Perfil en una ruta corta: con un TMPDIR largo, Chrome muere con «Socket path too long».
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
// Las transiciones de vista entre documentos del escritorio de WordPress (Chrome) no son de esta pantalla.
page.on('pageerror', (e) => { const text = String(e.message || e); if (!/ViewTransition|Transition was aborted/.test(text)) jsErrors.push(text.slice(0, 300)); });
page.on('dialog', async (d) => { log(`· diálogo: ${d.message().slice(0, 200)}`); await d.accept(); });
const samples = {};

async function login(path = '/wp-login.php') {
  for (let attempt = 0; attempt < 3; attempt++) {
    if (!/wp-login\.php/.test(page.url())) await page.goto(base + path, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) { if (/wp-admin/.test(page.url())) return true; continue; }
    await page.$eval('#user_login', (input) => { input.value = ''; });
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
    if (/wp-admin/.test(page.url())) return true;
  }
  return false;
}
/** Lo que enseña el panel ahora mismo (null si la página está cambiando). */
async function panel() {
  try {
    return await page.evaluate(() => {
      const p = document.getElementById('digitalisimo-backups-manual-progress');
      if (!p) return { absent: true, url: location.href };
      const q = (id) => document.getElementById(id);
      const shown = (el) => !!el && !el.hidden && el.getBoundingClientRect().height > 0;
      const bar = q('digitalisimo-backups-manual-bar');
      const current = p.querySelector('#digitalisimo-backups-progress-phases li[data-state="current"]');
      const box = p.getBoundingClientRect();
      const wrap = document.querySelector('.wrap').getBoundingClientRect();
      // El ancho disponible: el interior de la tarjeta que lo contiene (o el de .wrap, si es la tarjeta).
      const holder = p.parentElement.closest('.digitalisimo-backups-card') || document.querySelector('.wrap');
      const cs = getComputedStyle(holder);
      const inner = holder.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
      return {
        url: location.href, visible: shown(p), state: p.getAttribute('data-state'), kind: p.getAttribute('data-kind'),
        title: q('digitalisimo-backups-progress-title').textContent, percent: q('digitalisimo-backups-progress-percent').textContent,
        now: bar.getAttribute('aria-valuenow'), role: bar.getAttribute('role'), live: q('digitalisimo-backups-manual-message').getAttribute('role'),
        phase: current ? current.getAttribute('data-phase') : '',
        phases: [...p.querySelectorAll('#digitalisimo-backups-progress-phases li')].map((li) => `${li.getAttribute('data-phase')}:${li.getAttribute('data-state')}`),
        what: q('digitalisimo-backups-progress-what').textContent, message: q('digitalisimo-backups-manual-message').textContent, detail: q('digitalisimo-backups-manual-detail').textContent,
        time: q('digitalisimo-backups-progress-time').textContent, note: shown(q('digitalisimo-backups-progress-note')) ? q('digitalisimo-backups-progress-note').textContent : '',
        result: shown(q('digitalisimo-backups-progress-result')) ? q('digitalisimo-backups-progress-result').innerText : '',
        actions: ['cancel', 'reload', 'safety', 'login', 'rollback'].filter((name) => shown(q('digitalisimo-backups-progress-' + name))),
        width: Math.round(box.width), wrap: Math.round(wrap.width), inner: Math.round(inner), bar: Math.round(bar.getBoundingClientRect().width), fill: Math.round(q('digitalisimo-backups-progress-fill').getBoundingClientRect().width),
        overflow: document.documentElement.scrollWidth - window.innerWidth,
        strip: shown(document.querySelector('.digitalisimo-backups-strip .digitalisimo-backups-restore-state')),
        view: (() => { try { return JSON.parse(p.getAttribute('data-view') || 'null')?.id || ''; } catch { return ''; } })(),
      };
    });
  } catch { return null; }
}
async function shot(name, element = true) {
  const width = page.viewport().width;
  try {
    await page.screenshot({ path: `${shots}/${name}-${width}.png`, fullPage: true });
    const handle = element ? await page.$('#digitalisimo-backups-manual-progress') : null;
    if (handle && await handle.evaluate((el) => !el.hidden)) await handle.screenshot({ path: `${shots}/${name}-${width}-panel.png` });
    log(`· captura ${name}-${width}`);
  } catch (e) { log(`· sin captura ${name}: ${String(e.message || e).slice(0, 200)}`); }
}
/**
 * Sigue el panel hasta `until(muestra)` (o `minutes`). Anota cada cambio (fase, %, título, nota) y llama a `onSample` con
 * cada muestra nueva (para recargar, capturar…). Devuelve las muestras.
 */
async function follow(label, until, { minutes = 25, onSample = null } = {}) {
  const seen = [];
  const deadline = Date.now() + minutes * 60_000;
  let last = '';
  let away = 0;
  while (Date.now() < deadline) {
    const s = await panel();
    // Sin el panel (otra página, la de error crítico) más de 90 s seguidos: no hay nada que seguir.
    if (!s || s.absent) { away = away || Date.now(); if (Date.now() - away > 90_000) break; } else away = 0;
    if (s && !s.absent) {
      const key = [s.state, s.title, s.percent, s.phase, s.note, s.result ? 1 : 0].join('|');
      if (key !== last) {
        last = key;
        seen.push({ t: Math.round((Date.now() - (seen[0]?.at || Date.now())) / 1000), at: Date.now(), visible: s.visible, state: s.state, title: s.title, percent: s.percent, now: s.now, phase: s.phase, phases: s.phases.join(' '), note: s.note.slice(0, 160), detail: s.detail.slice(0, 160) });
        if (onSample) await onSample(s, seen);
      }
      if (until(s)) { samples[label] = seen.map(({ at, ...rest }) => rest); return { seen, last: s }; }
    }
    await sleep(700);
  }
  samples[label] = seen.map(({ at, ...rest }) => rest);
  check(false, `${label}: el panel no llegó al final en ${minutes} min`, JSON.stringify(seen.slice(-5)));
  return { seen, last: await panel() };
}
const ended = (s) => ['success', 'failed', 'cancelled', 'lost'].includes(s.state) && !!s.result;
const currentPhases = (seen) => [...new Set(seen.map((s) => s.phase).filter(Boolean))];
/** Abre «Restaurar» de la primera fila, elige las partes (null: todo), escribe RESTAURAR y lo envía. */
async function restoreFromRow(parts) {
  await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
  await sleep(800);
  // El respaldo de «Respaldar ahora», no una copia previa de las restauraciones anteriores.
  const toggle = await page.$('tr.digitalisimo-backups-row[data-file^="digitalisimo-"] [data-restore-toggle]');
  if (!check(!!toggle, 'la fila del respaldo tiene «Restaurar»')) return false;
  const row = await toggle.evaluate((a) => a.getAttribute('data-restore-toggle'));
  await toggle.click();
  await sleep(300);
  if (parts) {
    await page.click(`#${row} details[data-restore-parts] > summary`);
    await page.$eval(`#${row} [data-restore-all]`, (box) => { if (box.checked) box.click(); });
    for (const part of parts) await page.$eval(`#${row} [data-restore-part][value="${part}"]`, (box) => { if (!box.checked) box.click(); });
  }
  await page.type(`#${row} input[name="confirmation"]`, 'RESTAURAR');
  // Lo que enseña el panel justo después de enviar, guardado antes de que la navegación cambie de documento.
  await page.evaluate(() => {
    sessionStorage.removeItem('digi-starting');
    document.addEventListener('submit', () => setTimeout(() => {
      const p = document.getElementById('digitalisimo-backups-manual-progress');
      const bar = document.getElementById('digitalisimo-backups-manual-bar');
      sessionStorage.setItem('digi-starting', JSON.stringify({ visible: !p.hidden && p.getBoundingClientRect().height > 0, state: p.getAttribute('data-state'), message: document.getElementById('digitalisimo-backups-manual-message').textContent, now: bar.getAttribute('aria-valuenow') }));
    }, 0));
  });
  // El POST se retiene 2 s para capturar el panel «empieza» antes de que la página navegue.
  await page.setRequestInterception(true);
  const hold = (request) => { if (request.isInterceptResolutionHandled()) return; if (request.method() === 'POST' && /admin-post\.php/.test(request.url())) setTimeout(() => request.continue().catch(() => {}), 2000); else request.continue().catch(() => {}); };
  page.on('request', hold);
  const navigation = page.waitForNavigation({ waitUntil: 'load', timeout: 300_000 }).catch(() => null);
  await page.click(`#${row} .digitalisimo-backups-restore-confirm button`);
  await sleep(400);
  await shot(`${parts ? 'partes' : 'completa'}-empieza`);
  await navigation;
  page.off('request', hold);
  await page.setRequestInterception(false);
  const starting = await page.evaluate(() => JSON.parse(sessionStorage.getItem('digi-starting') || 'null')).catch(() => null);
  // Tras enviar, la pantalla vuelve con el panel (no la página de error crítico, ni otra).
  const landed = await page.$('#digitalisimo-backups-manual-progress');
  if (!check(!!landed, 'tras enviar «Restaurar», la pantalla vuelve con el panel de avance', `${page.url()} · ${(await page.evaluate(() => document.body.innerText).catch(() => '')).slice(0, 200)} · ${existsSync(`${dir}/ctl/fatal.jsonl`) ? readFileSync(`${dir}/ctl/fatal.jsonl`, 'utf8').slice(-800) : ''} · ${existsSync(`${dir}/ctl/debug.log`) ? readFileSync(`${dir}/ctl/debug.log`, 'utf8').split('\n').filter((l) => /Fatal|Allowed memory|Uncaught/.test(l)).slice(-3).join(' | ') : ''}`)) return false;
  check(starting && starting.visible && starting.state === 'starting' && /Empezando la restauración/.test(starting.message) && starting.now === null, 'al confirmar «Restaurar», el panel dice al instante que empieza (barra indeterminada)', JSON.stringify(starting));
  return true;
}

const start = Date.now();
// Modo «start» (para comparar con otra versión del plugin, sin el panel nuevo): un respaldo con el botón y «Restaurar»
// desde su fila; anota a qué página llega el navegador y los errores fatales de PHP.
if (mode === 'start') {
  try {
    check(await login(), 'inicio de sesión de admin');
    if ((process.env.BACKUP_MEMORY || '128M') !== '64M') writeFileSync(`${dir}/ctl/backup-memory`, process.env.BACKUP_MEMORY || '128M');
    await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 1_200_000 }).catch(() => null), page.click('#digitalisimo-backups-manual-form button')]);
    rmSync(`${dir}/ctl/backup-memory`, { force: true });
    await lab('mutate', '&round=1');
    await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
    const toggle = await page.$('tr.digitalisimo-backups-row[data-file^="digitalisimo-"] [data-restore-toggle]');
    if (check(!!toggle, 'la fila del respaldo tiene «Restaurar»')) {
      const row = await toggle.evaluate((a) => a.getAttribute('data-restore-toggle'));
      await toggle.click();
      await page.type(`#${row} input[name="confirmation"]`, 'RESTAURAR');
      await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 300_000 }).catch(() => null), page.click(`#${row} .digitalisimo-backups-restore-confirm button`)]);
      const text = await page.evaluate(() => document.body.innerText).catch(() => '');
      const fatal = existsSync(`${dir}/ctl/debug.log`) ? readFileSync(`${dir}/ctl/debug.log`, 'utf8').split('\n').filter((l) => /Fatal|Allowed memory|Uncaught/.test(l)).slice(-3).join(' | ') : '';
      const fatals = existsSync(`${dir}/ctl/fatal.jsonl`) ? readFileSync(`${dir}/ctl/fatal.jsonl`, 'utf8').trim() : '';
      const landed = /digitalisimo-backups/.test(page.url()) && !!(await page.$('#digitalisimo-backups-manual-form'));
      log(`· tras «Restaurar»: ${page.url()} · ${landed ? 'pantalla de Backups' : 'NO es la pantalla: ' + text.slice(0, 120)} · ${fatal} ${fatals.slice(0, 1200)}`);
      check(landed && !fatals, '«Restaurar» desde la fila vuelve a la pantalla (sin error fatal)', fatals || fatal);
    }
  } catch (e) { check(false, 'excepción', String(e.stack || e).slice(0, 800)); }
  for (const name of ['requests.jsonl', 'memory.jsonl', 'debug.log', 'fatal.jsonl']) if (existsSync(`${dir}/ctl/${name}`)) copyFileSync(`${dir}/ctl/${name}`, `${shots}/${name}`);
  await browser.close();
  rmSync(profile, { recursive: true, force: true });
  process.exit(failures ? 1 : 0);
}
try {
  check(await login(), 'inicio de sesión de admin');
  const original = await lab('inventory');

  // 1) Respaldar ahora. Sus peticiones, con BACKUP_MEMORY (128M por defecto, como el respaldo de referencia de
  // backups-restore-parts.sh); las restauraciones, con los 64 MB del propietario.
  const backupMemory = process.env.BACKUP_MEMORY || '128M';
  if (backupMemory !== '64M') writeFileSync(`${dir}/ctl/backup-memory`, backupMemory);
  await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
  await sleep(800);
  const reloaded = page.waitForNavigation({ waitUntil: 'load', timeout: 1_200_000 }).then(() => true).catch(() => false);
  await page.click('#digitalisimo-backups-manual-form button');
  let midway = null;
  let running = false;
  // Al terminar bien, la página se recarga enseguida: el panel vuelve vacío (oculto).
  const backup = await follow('respaldo', (s) => s.title === 'Respaldo terminado' || s.state === 'failed' || (running && !s.visible), { onSample: async (s) => { if (s.title === 'Respaldo en curso') running = true; if (!midway && parseInt(s.percent, 10) >= 40) { midway = s; await shot('respaldo-en-curso'); } } });
  check(midway && midway.overflow <= 0 && midway.width >= midway.inner - 2 && midway.bar >= midway.width - 40 && midway.role === 'progressbar' && midway.now === String(parseInt(midway.percent, 10)) && midway.live === 'status', 'respaldo: el panel va a todo lo ancho de su tarjeta, con la barra accesible y su valor', JSON.stringify(midway && { width: midway.width, inner: midway.inner, bar: midway.bar, now: midway.now, percent: midway.percent }));
  const phasesBackup = currentPhases(backup.seen);
  // Un tick (hasta 20 s) puede pasar varias fases: se pide que cada muestra marque hechas las anteriores a la actual.
  const stepper = (list) => list.filter((s) => s.phase).every((s) => { const chips = s.phases.split(' '); const at = chips.findIndex((c) => c.endsWith(':current')); return at >= 0 && chips.slice(0, at).every((c) => c.endsWith(':done')) && chips.slice(at + 1).every((c) => c.endsWith(':pending')); });
  const shown = (list) => list.filter((s) => s.visible && /\d/.test(s.percent));
  check(backup.seen.some((s) => s.title === 'Respaldo en curso') && phasesBackup.length >= 1 && stepper(backup.seen), `respaldo: el panel avanza por sus fases, las anteriores hechas (${phasesBackup.join(' → ')})`, JSON.stringify(backup.seen.slice(0, 12)));
  check(shown(backup.seen).every((s, i, all) => i === 0 || parseInt(s.percent, 10) >= parseInt(all[i - 1].percent, 10)), 'respaldo: el porcentaje nunca retrocede');
  check(await reloaded, 'respaldo: al terminar, la lista se recarga con él');
  rmSync(`${dir}/ctl/backup-memory`, { force: true });
  await sleep(1500);
  const rows = await page.$$eval('.digitalisimo-backups-row', (list) => list.length);
  check(rows >= 1, `la lista tiene el respaldo (${rows} fila)`);

  if (!network) {
    // 2) Restauración completa con otra sesión: tras el cambio de tablas, la de este navegador ya no existe.
    await lab('mutate', '&round=1');
    await page.deleteCookie(...(await page.cookies(base)));
    check(await login(), 'otra sesión (la del respaldo no es ésta)');
    if (await restoreFromRow(null)) {
      let reload = false;
      let maintenance = null;
      const full = await follow('completa', ended, { onSample: async (s) => {
        if (['database', 'files'].includes(s.phase) && !reload) {
          reload = true;
          maintenance = (await request('GET', '/')).status;
          await shot('completa-base-de-datos');
          await page.reload({ waitUntil: 'load' });
          await sleep(500);
          const again = await panel();
          check(again && again.visible && again.view && again.phase && again.title === 'Restauración en curso', `al recargar la página a mitad (en mantenimiento), el panel vuelve a la restauración (${again && again.phase})`, JSON.stringify(again));
          check(again && !again.strip, 'la franja no repite el estado que enseña el panel');
        }
        if (s.phase === 'files' && !samples.filesShot) { samples.filesShot = true; await shot('completa-archivos'); }
        if (s.phase === 'switch' && !samples.switchShot) { samples.switchShot = true; await shot('completa-cambio'); }
      } });
      const phases = currentPhases(full.seen);
      const order = ['inspect', 'safety', 'database', 'files', 'switch', 'finish', 'cleanup'];
      check(phases.length >= 3 && phases.every((p, i) => i === 0 || order.indexOf(p) > order.indexOf(phases[i - 1])) && (phases.includes('database') || phases.includes('files')), `completa: el panel avanza por las fases en orden (${phases.join(' → ')})`, JSON.stringify(full.seen.slice(0, 20)));
      check(maintenance === 503, `completa: el sitio estaba en mantenimiento para los visitantes (${maintenance}) mientras el panel seguía`);
      check(stepper(full.seen) && shown(full.seen).every((s, i, all) => i === 0 || parseInt(s.percent, 10) >= parseInt(all[i - 1].percent, 10)), 'completa: el porcentaje nunca retrocede y cada fase marca hechas las anteriores');
      const last = full.last;
      check(last && last.title === 'Restauración terminada' && last.percent === '100 %' && last.now === '100' && last.phases.every((p) => p.endsWith(':done')), 'completa: termina al 100 % con todas sus fases hechas', JSON.stringify(last));
      check(last && last.actions.includes('login') && /Entra al escritorio|resultado/.test(last.result), 'completa: sin sesión (la del respaldo no era ésta), el panel lo dice y ofrece «Iniciar sesión»', JSON.stringify(last));
      await shot('completa-terminada-sin-sesion');
      const after = await lab('inventory');
      check(after && original && after.blogname === original.blogname, `completa: el sitio volvió al respaldo (título «${after?.blogname}»)`);
      // Entrar otra vez: la pantalla enseña el resultado completo de la restauración que acaba de terminar.
      await page.click('#digitalisimo-backups-progress-login');
      await page.waitForNavigation({ waitUntil: 'load' }).catch(() => {});
      check(await login(), 'entrar otra vez tras la restauración');
      if (!/digitalisimo-backups/.test(page.url())) await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
      await sleep(1200);
      const result = await panel();
      check(result && result.visible && result.title === 'Restauración terminada' && /Restauración terminada desde/.test(result.result) && /copia previa/.test(result.result) && result.actions.includes('safety') && result.actions.includes('reload'), 'al entrar otra vez, el panel enseña el resultado completo con «Volver a la copia previa»', JSON.stringify(result));
      await shot('completa-resultado');
    }
  }

  // 3) Por partes (sólo plugins) a 390 px.
  await lab('mutate', '&round=2');
  await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  if (await restoreFromRow(['plugins'])) {
    let narrow = null;
    const parts = await follow('partes', ended, { onSample: async (s) => { if (!narrow && ['database', 'files'].includes(s.phase)) { narrow = s; await shot('partes-en-curso'); } } });
    const last = parts.last;
    check(narrow && narrow.overflow <= 0 && narrow.bar >= narrow.width - 40 && narrow.width >= narrow.inner - 2, 'a 390 px el panel ocupa todo el ancho de su tarjeta sin desplazamiento horizontal', JSON.stringify(narrow && { overflow: narrow.overflow, width: narrow.width, inner: narrow.inner, bar: narrow.bar }));
    check(last && /se restaura sólo: plugins/.test(last.what) && last.title === 'Restauración terminada' && /por partes: plugins/.test(last.result) && last.actions.includes('safety') && last.overflow <= 0, 'por partes: dice qué se restaura y termina con el resultado y «Volver a la copia previa»', JSON.stringify(last));
    await shot('partes-terminada');
  }
  await page.setViewport({ width: 1280, height: 900 });

  if (!network) {
    // 4) Una falla a mitad del cambio: vuelve sola a la copia previa y el panel lo dice.
    await lab('mutate', '&round=3');
    writeFileSync(`${dir}/ctl/fail-move`, '1');
    try {
      if (await restoreFromRow(['plugins'])) {
        let backShot = false;
        const back = await follow('falla', (s) => ended(s) && s.kind === 'rollback', { onSample: async (s) => { if (!backShot && s.title === 'Volviendo a la copia previa') { backShot = true; await shot('falla-vuelta-en-curso'); } } });
        const last = back.last;
        check(back.seen.some((s) => s.title === 'Volviendo a la copia previa'), 'falla: el panel sigue la vuelta a la copia previa', JSON.stringify(back.seen.slice(-8)));
        check(last && last.title === 'El sitio volvió a la copia previa' && /falló a mitad del cambio/.test(last.result) && /fallo inyectado al mover los archivos/.test(last.result) && /como antes/.test(last.result), 'falla: termina diciendo que el sitio volvió a como estaba y por qué falló', JSON.stringify(last));
        await shot('falla-vuelta-terminada');
        await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
        await sleep(500);
        const narrow = await panel();
        check(narrow && narrow.overflow <= 0, 'falla: a 390 px el resultado cabe sin desplazamiento horizontal', JSON.stringify(narrow && narrow.overflow));
        await shot('falla-vuelta-terminada');
        await page.setViewport({ width: 1280, height: 900 });
      }
    } finally {
      if (existsSync(`${dir}/ctl/fail-move`)) unlinkSync(`${dir}/ctl/fail-move`);
    }
  }
  const memory = existsSync(`${dir}/ctl/memory.jsonl`) ? readFileSync(`${dir}/ctl/memory.jsonl`, 'utf8').split('\n').filter(Boolean).map((line) => JSON.parse(line)) : [];
  const restoring = memory.filter((r) => r.action === 'digitalisimo_backups_restore_tick');
  check(restoring.length > 0 && restoring.every((r) => r.limit === '64M'), `las ${restoring.length} peticiones del tick por testigo corrieron con memory_limit=64M (pico ${Math.max(0, ...restoring.map((r) => r.peak_real)) / 1048576} MB)`);
  const fatals = existsSync(`${dir}/ctl/fatal.jsonl`) ? readFileSync(`${dir}/ctl/fatal.jsonl`, 'utf8').trim() : '';
  check(!fatals, 'ninguna petición terminó en la página de error crítico de WordPress', fatals.slice(0, 1500));
  check(jsErrors.length === 0, 'sin errores de JavaScript en la página', jsErrors.join(' | '));
} catch (e) {
  check(false, 'el ensayo terminó con una excepción', String(e.stack || e).slice(0, 1500));
} finally {
  writeFileSync(`${shots}/samples.json`, JSON.stringify(samples, null, 1));
  for (const name of ['requests.jsonl', 'memory.jsonl', 'fatal.jsonl', 'debug.log']) if (existsSync(`${dir}/ctl/${name}`)) copyFileSync(`${dir}/ctl/${name}`, `${shots}/${name}`);
  await browser.close();
  rmSync(profile, { recursive: true, force: true });
}
log(`${failures ? 'FALLA' : 'ok'} · ensayo del panel de avance (${mode}) en ${Math.round((Date.now() - start) / 1000)} s`);
process.exit(failures ? 1 : 0);
