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
//                 termina con el resultado y «Volver a la copia previa»; después el sitio cambia (entradas, opciones,
//                 un plugin) y «Volver a la copia previa» abre la copia previa con sólo «Plugins» marcado: restaurarla
//                 devuelve los plugins a los de antes y la base de datos no cambia (huellas de cada tabla; revisión M1);
//              4) restauración (plugins) con una falla inyectada al mover el código (1 280 px): el panel sigue la vuelta
//                 a la copia previa y termina diciendo que el sitio volvió a como estaba y por qué falló;
//              5) restauración entera de un respaldo anterior a la sesión, que falla a mitad del cambio con la vuelta
//                 también fallida: sin la sesión, este navegador (con el testigo) entra por wp-login.php, llega a Backups y
//                 vuelve a la copia previa (verificación final N9).
//   los dos    tras 1), la puerta del mantenimiento con un testigo conocido: un visitante recibe 503 con la cabecera de
//                 Backups; con el testigo, sólo la pantalla de Backups pasa (el escritorio, las entradas y la portada no);
//   multisite  1) y 3) (con «Volver a la copia previa») en la administración de la red.
// Capturas (página y panel) en <dir>/shots; las muestras del panel, en <dir>/shots/samples.json.
// Uso: node drive.mjs <puerto> <host> <dir> <single|multisite>
import http from 'node:http';
import { appendFileSync, copyFileSync, existsSync, mkdtempSync, readdirSync, readFileSync, rmSync, unlinkSync, writeFileSync } from 'node:fs';
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
      res.on('end', () => { const text = Buffer.concat(chunks).toString('utf8'); let json = null; try { json = JSON.parse(text); } catch { /* página */ } resolve({ status: res.statusCode, text, json, headers: res.headers }); });
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
page.on('pageerror', (e) => { const text = String(e.message || e); if (!/ViewTransition|Transition was aborted/.test(text)) jsErrors.push(`${page.url()} · ${text.slice(0, 300)}${e && e.stack ? ' · ' + String(e.stack).replace(/\s+/g, ' ').slice(0, 400) : ''}`); });
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
/**
 * Abre «Restaurar» de la fila del respaldo, elige las partes (null: todo), escribe RESTAURAR y lo envía. `memory`: el
 * límite de la petición admin-post que encola la restauración y trabaja sus primeros 25 s (en php-wasm, con 64 MB, la
 * lectura de 1 MiB de la verificación la agota también con main); los ticks del panel corren siempre con 64 MB.
 * Devuelve 'landed' (volvió a la pantalla con el panel), 'died' (la petición murió) o '' (no se pudo enviar).
 */
const RESTORE_MEMORY = process.env.RESTORE_MEMORY || '128M';
/** Pone (o quita, con 64M) el límite de memoria de todas las peticiones mientras dura una restauración. */
function labMemory(memory) { if (memory && memory !== '64M') writeFileSync(`${dir}/ctl/backup-memory`, memory); else rmSync(`${dir}/ctl/backup-memory`, { force: true }); }
async function restoreFromRow(parts, { memory = RESTORE_MEMORY, label = parts ? 'partes' : 'completa' } = {}) {
  await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
  await sleep(800);
  // El respaldo de «Respaldar ahora», no una copia previa de las restauraciones anteriores.
  const toggle = await page.$('tr.digitalisimo-backups-row[data-file^="digitalisimo-"] [data-restore-toggle]');
  if (!check(!!toggle, 'la fila del respaldo tiene «Restaurar»')) return '';
  const row = await toggle.evaluate((a) => a.getAttribute('data-restore-toggle'));
  await toggle.click();
  await sleep(300);
  if (parts) {
    await page.click(`#${row} details[data-restore-parts] > summary`);
    await page.$eval(`#${row} [data-restore-all]`, (box) => { if (box.checked) box.click(); });
    for (const part of parts) await page.$eval(`#${row} [data-restore-part][value="${part}"]`, (box) => { if (!box.checked) box.click(); });
  }
  return confirmRow(row, { memory, label });
}
/** Escribe RESTAURAR en el panel abierto de la fila `row` y lo envía (ver restoreFromRow()). */
async function confirmRow(row, { memory = RESTORE_MEMORY, label = 'restauración' } = {}) {
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
  labMemory(memory);
  // El POST se retiene 2 s para capturar el panel «empieza» antes de que la página navegue.
  await page.setRequestInterception(true);
  const hold = (request) => { if (request.isInterceptResolutionHandled()) return; if (request.method() === 'POST' && /admin-post\.php/.test(request.url())) setTimeout(() => request.continue().catch(() => {}), 2000); else request.continue().catch(() => {}); };
  page.on('request', hold);
  const navigation = page.waitForNavigation({ waitUntil: 'load', timeout: 300_000 }).catch(() => null);
  await page.click(`#${row} .digitalisimo-backups-restore-confirm button`);
  await sleep(400);
  await shot(`${label}-empieza`);
  await navigation;
  // Lo que sigue de la restauración (los ticks del panel), con RESTORE_MEMORY. Lo que corrió antes con 64 MB a propósito
  // (la ventana de la petición que la empieza) no cuenta en el recuento de memoria del final.
  labMemory(RESTORE_MEMORY);
  if (memory === '64M') memorySwitch = Date.now() / 1000;
  page.off('request', hold);
  await page.setRequestInterception(false);
  const starting = await page.evaluate(() => JSON.parse(sessionStorage.getItem('digi-starting') || 'null')).catch(() => null);
  // Tras enviar, la pantalla vuelve con el panel (no la página de error crítico, ni otra).
  const landed = await page.$('#digitalisimo-backups-manual-progress');
  if (landed || memory !== '64M') check(starting && starting.visible && starting.state === 'starting' && /Empezando la restauración/.test(starting.message) && starting.now === null, `${label}: al confirmar «Restaurar», el panel dice al instante que empieza (barra indeterminada)`, JSON.stringify(starting));
  if (memory === '64M' && !landed) {
    const fatal = existsSync(`${dir}/ctl/fatal.jsonl`) ? readFileSync(`${dir}/ctl/fatal.jsonl`, 'utf8').trim().split('\n').pop() : '';
    log(`· ${label}: la petición que empieza la restauración murió con 64 MB (como con main en php-wasm): ${fatal.slice(0, 300)}`);
    return 'died';
  }
  if (!check(!!landed, `${label}: tras enviar «Restaurar», la pantalla vuelve con el panel de avance`, `${page.url()} · ${(await page.evaluate(() => document.body.innerText).catch(() => '')).slice(0, 200)} · ${existsSync(`${dir}/ctl/fatal.jsonl`) ? readFileSync(`${dir}/ctl/fatal.jsonl`, 'utf8').slice(-800) : ''} · ${existsSync(`${dir}/ctl/debug.log`) ? readFileSync(`${dir}/ctl/debug.log`, 'utf8').split('\n').filter((l) => /Fatal|Allowed memory|Uncaught/.test(l)).slice(-3).join(' | ') : ''}`)) return false;
  return 'landed';
}

/** Los archivos de los plugins (sin este, que el ensayo instala) de un inventario del laboratorio. */
const pluginFiles = (inv) => Object.fromEntries(Object.entries(inv?.files || {}).filter(([f]) => f.startsWith('wp-content/plugins/') && !f.startsWith('wp-content/plugins/digitalisimo-backups/')));
const parts = async (what) => (await request('GET', `/?digi_parts=${what}&token=${token}`)).json;
/**
 * Revisión M1: tras restaurar sólo los plugins, el sitio sigue vivo (entradas, opciones y un plugin nuevo) y «Volver a la
 * copia previa» del panel abre la copia previa con sólo «Plugins» marcado (no «Todo») y el aviso de qué no se toca;
 * restaurarla devuelve los plugins a los de antes de la restauración y la base de datos queda como estaba (huellas de
 * cada tabla del laboratorio; en wp_options, sólo lo que WordPress escribe solo).
 */
async function undoParts() {
  const safetyHref = await page.$eval('#digitalisimo-backups-progress-safety', (a) => a.href).catch(() => '');
  const safety = (() => { try { return new URL(safetyHref).searchParams.get('restore') || ''; } catch { return ''; } })();
  if (!check(/^safety-before-restore-/.test(safety) && /undo_parts=plugins/.test(safetyHref), 'deshacer: «Volver a la copia previa» lleva la copia previa y sólo «Plugins»', safetyHref)) return;
  // La vida del sitio después de restaurar: una entrada nueva, opciones y un plugin.
  await lab('mutate', '&round=5');
  const before = await lab('inventory');
  const options = await parts('options');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#digitalisimo-backups-progress-safety')]);
  await sleep(800);
  const form = await page.evaluate((file) => {
    const field = [...document.querySelectorAll('tr.digitalisimo-backups-restore-row input[name="backup_file"]')].find((f) => f.value === file);
    const row = field ? field.closest('tr') : null;
    if (!row) return null;
    const on = (sel) => [...row.querySelectorAll(sel)].filter((b) => b.checked).map((b) => b.value || 'todo');
    const undo = row.querySelector('[data-restore-undo]');
    return { id: row.id, shown: !row.hidden, all: !!row.querySelector('[data-restore-all]')?.checked, parts: on('[data-restore-part]'), items: on('[data-restore-item]'), undo: undo && !undo.hidden ? undo.textContent : '', summary: row.querySelector('[data-restore-parts-summary]')?.textContent || '' };
  }, safety);
  if (!check(form && form.shown && !form.all && form.parts.join() === 'plugins' && !form.items.length, 'deshacer: la copia previa se abre con sólo «Plugins» marcado (no «Todo»)', JSON.stringify(form))) return;
  check(/Deshaces sólo lo que se restauró/.test(form.undo) && /No se toca: base de datos/.test(form.undo) && /Se restaura sólo: plugins/.test(form.summary), 'deshacer: el aviso dice qué vuelve y que la base de datos no se toca', JSON.stringify(form));
  await shot('deshacer-confirmacion', false);
  if ('landed' !== await confirmRow(form.id, { label: 'deshacer' })) return;
  const undo = await follow('deshacer', ended);
  check(undo.last && undo.last.title === 'Restauración terminada' && /se restaura sólo: plugins/.test(undo.last.what), 'deshacer: la vuelta de sólo los plugins termina en el panel', JSON.stringify(undo.last));
  await shot('deshacer-terminada');
  const after = await lab('inventory');
  const optionsAfter = await parts('options');
  let changed = Object.keys({ ...before.tables, ...after.tables }).filter((t) => JSON.stringify(before.tables[t]) !== JSON.stringify(after.tables[t]));
  if (changed.length === 1 && /options$/.test(changed[0])) {
    const real = optionDiff(options, optionsAfter);
    log(`· deshacer: ${changed[0]} distinta sólo en ${real.length ? real.join(', ') : 'opciones que WordPress escribe solo'}`);
    if (!real.length) changed = [];
  }
  check(!changed.length, 'deshacer: la base de datos no cambió (la entrada y las opciones de después siguen; huellas de cada tabla)', changed.join(', '));
  const want = pluginFiles(beforeParts);
  const got = pluginFiles(after);
  const bad = Object.keys({ ...want, ...got }).filter((f) => want[f] !== got[f]);
  check(!bad.length, 'deshacer: los plugins volvieron a los de antes de restaurar (también se retiró el plugin que llegó después)', bad.slice(0, 8).join(', '));
  check(after.files['wp-content/uploads/2026/11/inyectado-5.php'] === before.files['wp-content/uploads/2026/11/inyectado-5.php'], 'deshacer: lo que no son plugins no se tocó');
}
const VOLATILE = /^(_site_transient_|_transient_|cron$|digitalisimo_backups_|rewrite_rules$|recently_activated$|doing_cron$|session_tokens$|wp_[0-9]*_?user-settings|auto_core_update|_wp_suggested_policy|can_compress_scripts$|db_upgraded$|wp_user_roles_update$|finished_updating_comment_type$|user_count$|_wp_session_|admin_email_lifespan$|recovery_keys$|fresh_site$|wp_calendar_block_has_published_posts$)/;
const optionDiff = (a, b) => Object.keys({ ...(a || {}), ...(b || {}) }).filter((k) => !VOLATILE.test(k) && (a || {})[k] !== (b || {})[k]);
let beforeParts = null;

/**
 * Verificación N1 y N2: la puerta del mantenimiento con un testigo conocido (lab.php la pone y la quita). Un visitante
 * recibe 503 con la cabecera de Backups; este navegador, con el testigo en su cookie, abre la pantalla de Backups pero no
 * el escritorio (503 con la cabecera); sin la cookie, tampoco Backups. Al quitarla, el sitio vuelve.
 */
async function gate() {
  const known = 'ab'.repeat(32);
  writeFileSync(`${dir}/ctl/gate-hold`, known);
  await request('GET', '/');
  const visitor = await request('GET', '/');
  check(visitor.status === 503 && visitor.headers?.['x-digitalisimo-backups-restore'] === 'maintenance', 'puerta: un visitante recibe 503 con la cabecera X-Digitalisimo-Backups-Restore (N2)', `${visitor.status} ${JSON.stringify(visitor.headers?.['x-digitalisimo-backups-restore'] || null)}`);
  const probe = () => page.evaluate(async (screenPath) => {
    const one = async (url) => { try { const r = await fetch(url, { credentials: 'same-origin' }); return [r.status, r.headers.get('X-Digitalisimo-Backups-Restore') || '']; } catch (e) { return [0, String(e)]; } };
    return { backups: await one(screenPath + '&tab=settings'), dashboard: await one('/wp-admin/index.php'), posts: await one('/wp-admin/edit.php'), front: await one('/') };
  }, screen).catch((e) => ({ error: String(e) }));
  const without = await probe();
  await page.setCookie({ name: 'digitalisimo_backups_restore', value: known, url: base });
  const withToken = await probe();
  await page.deleteCookie({ name: 'digitalisimo_backups_restore', url: base });
  check(without.backups?.[0] === 503 && without.dashboard?.[0] === 503, 'puerta: sin el testigo, el escritorio y la pantalla de Backups reciben 503', JSON.stringify(without));
  check(withToken.backups?.[0] === 200 && withToken.dashboard?.[0] === 503 && withToken.dashboard?.[1] === 'maintenance' && withToken.posts?.[0] === 503 && withToken.front?.[0] === 503, 'puerta: con el testigo, sólo la pantalla de Backups; el escritorio, las entradas y la portada, 503 con la cabecera (N1)', JSON.stringify(withToken));
  writeFileSync(`${dir}/ctl/gate-release`, '1');
  await request('GET', '/wp-cron.php');
  const after = await request('GET', '/');
  check(after.status === 200 && !existsSync(`${dir}/ctl/gate-release`), 'puerta: al quitarla, el sitio vuelve', `${after.status}`);
}

/**
 * Verificación final N9: una restauración del sitio entero de un respaldo anterior a la sesión de ahora (el de 1), que
 * falla a mitad del cambio, y cuya vuelta a la copia previa también falla (antes de su cambio): el sitio queda a medio
 * cambiar, en un mantenimiento que no caduca. Tras el cambio de tablas la sesión de este navegador ya no vale (las
 * sesiones son las del respaldo); con el testigo, la puerta deja pasar wp-login.php para entrar y la pantalla de Backups,
 * donde «Volver a la copia previa» recupera el sitio.
 */
async function halfChanged() {
  await lab('mutate', '&round=7');
  const beforeMid = await lab('inventory');
  await page.deleteCookie(...(await page.cookies(base)));
  check(await login(), 'medio: otra sesión, posterior al respaldo que se restaura');
  writeFileSync(`${dir}/ctl/fail-move`, '1');
  writeFileSync(`${dir}/ctl/fail-rollback`, '1');
  let mid = null;
  try {
    if ('landed' !== await restoreFromRow(null, { label: 'medio' })) return;
    mid = await follow('medio', (s) => ended(s) && s.kind === 'rollback');
  } finally {
    for (const flag of ['fail-move', 'fail-rollback']) if (existsSync(`${dir}/ctl/${flag}`)) unlinkSync(`${dir}/ctl/${flag}`);
  }
  check(mid && mid.last && mid.last.title === 'La vuelta a la copia previa no terminó' && mid.last.actions.includes('rollback'), 'medio: la vuelta a la copia previa también falla y el panel ofrece «Volver a la copia previa»', JSON.stringify(mid && mid.last));
  await shot('medio-a-medio-cambiar');
  const visitor = await request('GET', '/');
  check(visitor.status === 503 && visitor.headers?.['x-digitalisimo-backups-restore'] === 'maintenance', 'medio: el sitio sigue en mantenimiento (a medio cambiar)', `${visitor.status}`);
  // Sin sesión, la pantalla de Backups lleva a wp-login.php, que con el testigo pasa (antes, 503).
  await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
  let atLogin = /wp-login\.php/.test(page.url()) && !!(await page.$('#user_login'));
  if (!atLogin) {
    // Si Playground conservara la sesión, se quita la del navegador (el testigo se queda), como tras el cambio de tablas.
    log(`· medio: la sesión siguió valiendo tras el cambio de tablas (${page.url()}); se quita la del navegador`);
    for (const cookie of await page.cookies(base)) if (/^wordpress_/.test(cookie.name)) await page.deleteCookie({ name: cookie.name, url: base });
    await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
    atLogin = /wp-login\.php/.test(page.url()) && !!(await page.$('#user_login'));
  }
  check(atLogin, 'medio: sin la sesión, Backups lleva a wp-login.php y, con el testigo, se puede entrar (verificación final N9)', `${page.url()} · ${(await page.evaluate(() => document.title).catch(() => ''))}`);
  if (!atLogin) return;
  await shot('medio-entrar', false);
  await page.$eval('#user_login', (input) => { input.value = ''; });
  await page.type('#user_login', 'admin');
  await page.type('#user_pass', 'password');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  const back = await page.evaluate(() => {
    const panelForm = document.getElementById('digitalisimo-backups-progress-rollback');
    const strip = document.querySelector('form input[name="rollback_retry"]');
    return { url: location.href, panel: !!panelForm && !panelForm.hidden, strip: !!strip };
  }).catch(() => null);
  check(back && /page=digitalisimo-(network-)?backups/.test(back.url) && (back.panel || back.strip), 'medio: al entrar, el navegador vuelve a Backups con «Volver a la copia previa»', JSON.stringify(back));
  await shot('medio-de-vuelta');
  if (!back || !(back.panel || back.strip)) return;
  // «Volver a la copia previa» (la del panel o la de la franja: la misma acción).
  labMemory(RESTORE_MEMORY);
  const selector = back.panel ? '#digitalisimo-backups-progress-rollback button' : 'form:has(input[name="rollback_retry"]) button';
  await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 300_000 }).catch(() => null), page.click(selector)]);
  const recovered = await follow('medio-vuelta', (s) => ended(s) && s.kind === 'rollback');
  labMemory('64M');
  check(recovered.last && recovered.last.title === 'El sitio volvió a la copia previa', 'medio: desde el navegador, el sitio vuelve a la copia previa', JSON.stringify(recovered.last));
  await shot('medio-recuperado');
  const after = await lab('inventory');
  check(after && beforeMid && after.blogname === beforeMid.blogname && (await request('GET', '/')).status === 200, `medio: el sitio volvió a como estaba antes de restaurar (título «${after?.blogname}») y salió del mantenimiento`);
}

const start = Date.now();
let memoryMark = 0;
let memorySwitch = 0;
let fatalMark = 0;
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
      // Los ticks de la pantalla (los de esa versión) con 64 MB: ¿pasa la restauración de la verificación?
      await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
      const job = () => { for (const id of (existsSync(`${dir}/store/jobs`) ? readdirSync(`${dir}/store/jobs`) : [])) { try { const j = JSON.parse(readFileSync(`${dir}/store/jobs/${id}/job.json`, 'utf8')); if (j.kind === 'restore') return j; } catch { /* a medio escribir */ } } return null; };
      const until = Date.now() + 6 * 60_000;
      let j = job();
      while (Date.now() < until && j && !['success', 'failed', 'cancelled'].includes(j.status) && !['database', 'files', 'switch', 'finish', 'cleanup'].includes(j.phase)) { await sleep(5000); j = job(); }
      const ticks = existsSync(`${dir}/ctl/memory.jsonl`) ? readFileSync(`${dir}/ctl/memory.jsonl`, 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l)).filter((r) => r.limit === '64M') : [];
      log(`· con 64 MB, la restauración llega a: ${j ? `${j.status} · ${j.phase} · ${String((j.error && j.error.message) || j.progress.message).slice(0, 220)}` : 'sin trabajo'} · ${ticks.length} ticks con 64 MB, ${ticks.filter((r) => /Allowed memory/.test(r.error || '')).length} sin memoria; cargado tras los plugins ${ticks[0] ? ticks[0].loaded[0] : '?'} bytes`);
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
  memoryMark = existsSync(`${dir}/ctl/memory.jsonl`) ? readFileSync(`${dir}/ctl/memory.jsonl`, 'utf8').split('\n').filter(Boolean).length : 0;
  await sleep(1500);
  const rows = await page.$$eval('.digitalisimo-backups-row', (list) => list.length);
  check(rows >= 1, `la lista tiene el respaldo (${rows} fila)`);
  // La puerta del mantenimiento con el testigo (verificación N1 y N2), antes de que haya ninguna restauración. Desde la
  // portada: el escritorio pediría sus widgets (en php-wasm con 64 MB agotan la memoria) y la pantalla consultaría.
  await page.goto(`${base}/`, { waitUntil: 'load' });
  await gate();

  if (!network) {
    // 2a) Restauración completa con 64 MB también en la petición que la empieza y con la sesión del respaldo (sigue valiendo).
    // En php-wasm esa petición agota los 64 MB (también con main): volver a Backups enseña el panel y lo retoma.
    const order = ['inspect', 'safety', 'database', 'files', 'switch', 'finish', 'cleanup'];
    const inOrder = (phases) => phases.length >= 2 && phases.every((p, i) => i === 0 || order.indexOf(p) > order.indexOf(phases[i - 1])) && (phases.includes('database') || phases.includes('files'));
    await lab('mutate', '&round=1');
    const first = await restoreFromRow(null, { memory: '64M', label: 'completa' });
    fatalMark = existsSync(`${dir}/ctl/fatal.jsonl`) ? readFileSync(`${dir}/ctl/fatal.jsonl`, 'utf8').split('\n').filter(Boolean).length : 0;
    if (first) {
      if (first === 'died') {
        await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
        await sleep(800);
        const again = await panel();
        check(again && again.visible && again.view && again.title === 'Restauración en curso', 'completa: muerta la petición que la empezó, volver a Backups enseña el panel de la restauración y la retoma', JSON.stringify(again));
        await shot('completa-retoma');
      }
      let reload = false;
      let maintenance = null;
      let gateMark = '';
      const full = await follow('completa', ended, { onSample: async (s) => {
        if (['database', 'files'].includes(s.phase) && !reload) {
          reload = true;
          const reloadFrom = (await page.evaluate(() => { const p = document.getElementById('digitalisimo-backups-manual-progress'); try { return JSON.parse(p.getAttribute('data-view') || 'null'); } catch { return null; } }).catch(() => null))?.id || (await panel())?.view || '';
          const visitor = await request('GET', '/');
          maintenance = visitor.status;
          gateMark = String(visitor.headers?.['x-digitalisimo-backups-restore'] || '');
          await shot('completa-base-de-datos');
          await page.reload({ waitUntil: 'load' });
          await sleep(500);
          const again = await panel();
          // Con un solo worker, la recarga espera al tick en curso: puede volver con la restauración ya terminada.
          check(again && again.visible && again.view && again.view === reloadFrom && (again.title === 'Restauración en curso' || again.title === 'Restauración terminada'), `completa: al recargar la página a mitad (en mantenimiento), el panel vuelve a la misma restauración (${again && (again.phase || again.title)})`, JSON.stringify(again));
          check(again && !again.strip, 'completa: la franja no repite el estado que enseña el panel');
        }
        if (s.phase === 'files' && !samples.filesShot) { samples.filesShot = true; await shot('completa-archivos'); }
        if (s.phase === 'switch' && !samples.switchShot) { samples.switchShot = true; await shot('completa-cambio'); }
      } });
      const phases = currentPhases(full.seen);
      check(inOrder(phases), `completa: el panel avanza por las fases en orden (${phases.join(' → ')})`, JSON.stringify(full.seen.slice(0, 20)));
      // Con un solo proceso de PHP, la visita espera al tick en curso y a veces llega con la restauración ya terminada (la
      // puerta se prueba aparte, con un mantenimiento que no se acaba solo: gate()).
      if (maintenance === 503) check(gateMark === 'maintenance', 'completa: el sitio estaba en mantenimiento para los visitantes (503, con la cabecera de la puerta) mientras el panel seguía', gateMark);
      else log(`· completa: la visita llegó con la restauración ya terminada (${maintenance}); la puerta se comprueba en «puerta»`);
      check(stepper(full.seen) && shown(full.seen).every((s, i, all) => i === 0 || parseInt(s.percent, 10) >= parseInt(all[i - 1].percent, 10)), 'completa: el porcentaje nunca retrocede y cada fase marca hechas las anteriores');
      const last = full.last;
      check(last && last.title === 'Restauración terminada' && last.percent === '100 %' && last.now === '100' && last.phases.every((p) => p.endsWith(':done')), 'completa: termina al 100 % con todas sus fases hechas', JSON.stringify(last));
      check(last && /Restauración terminada desde/.test(last.result) && /copia previa/.test(last.result) && last.actions.includes('safety') && last.actions.includes('reload') && !last.actions.includes('login'), 'completa: con la sesión del respaldo, el resultado completo con «Volver a la copia previa» (sin pedir entrar)', JSON.stringify(last));
      await shot('completa-terminada');
      const after = await lab('inventory');
      check(after && original && after.blogname === original.blogname, `completa: el sitio volvió al respaldo (título «${after?.blogname}»)`);
    }

    labMemory('64M');
    // 2b) Otra restauración completa con otra sesión: tras el cambio de tablas, la de este navegador ya no existe. La
    // petición que la empieza, con 128 MB: vuelve a la pantalla con el panel y el testigo.
    await lab('mutate', '&round=2');
    await page.deleteCookie(...(await page.cookies(base)));
    check(await login(), 'otra sesión (la del respaldo no es ésta)');
    if ('landed' === await restoreFromRow(null, { label: 'sesion' })) {
      const lost = await follow('sesion', ended);
      const phases = currentPhases(lost.seen);
      check(inOrder(phases), `sesión: el panel avanza por las fases en orden con el testigo (${phases.join(' → ')})`, JSON.stringify(lost.seen.slice(0, 20)));
      const last = lost.last;
      const lostSession = !!last && last.actions.includes('login');
      check(last && last.title === 'Restauración terminada' && last.percent === '100 %' && (lostSession ? /perdió la sesión|Entra al escritorio/.test(last.result) : /Restauración terminada desde/.test(last.result) && last.actions.includes('safety')), `sesión: termina y ${lostSession ? 'dice que la sesión se perdió con el cambio de tablas, con «Iniciar sesión»' : 'la sesión siguió valiendo: enseña el resultado completo'}`, JSON.stringify(last));
      log(`· sesión: tras el cambio de tablas, la sesión de esta pestaña ${lostSession ? 'dejó de valer (el panel siguió con el testigo)' : 'siguió valiendo'}`);
      await shot('sesion-terminada-sin-sesion');
      if (last && last.actions.includes('login')) await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}), page.click('#digitalisimo-backups-progress-login')]);
      else await page.goto(`${base}/wp-login.php`, { waitUntil: 'load' });
      check(await login(), 'sesión: entrar otra vez tras la restauración');
      if (!/digitalisimo-backups/.test(page.url())) await page.goto(`${base}${screen}&tab=backups`, { waitUntil: 'load' });
      await sleep(1200);
      const result = await panel();
      check(result && result.visible && result.title === 'Restauración terminada' && /Restauración terminada desde/.test(result.result) && result.actions.includes('safety'), 'sesión: al entrar otra vez, el panel enseña el resultado completo con «Volver a la copia previa»', JSON.stringify(result));
      await shot('sesion-resultado');
    }
  }

  labMemory('64M');
  // 3) Por partes (sólo plugins) a 390 px.
  await lab('mutate', '&round=3');
  // Lo que guarda la copia previa de esta restauración: los plugins de antes (revisión M1, «Volver a la copia previa»).
  beforeParts = await lab('inventory');
  await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  if ('landed' === await restoreFromRow(['plugins'], { label: 'partes' })) {
    let narrow = null;
    const parts = await follow('partes', ended, { onSample: async (s) => { if (!narrow && s.state === 'running' && s.phase) { narrow = s; await shot('partes-en-curso'); } } });
    const last = parts.last;
    check(narrow && narrow.overflow <= 0 && narrow.bar >= narrow.width - 40 && narrow.width >= narrow.inner - 2, 'a 390 px el panel ocupa todo el ancho de su tarjeta sin desplazamiento horizontal', JSON.stringify(narrow && { overflow: narrow.overflow, width: narrow.width, inner: narrow.inner, bar: narrow.bar }));
    check(last && /se restaura sólo: plugins/.test(last.what) && last.title === 'Restauración terminada' && /por partes: plugins/.test(last.result) && last.actions.includes('safety') && last.overflow <= 0, 'por partes: dice qué se restaura y termina con el resultado y «Volver a la copia previa»', JSON.stringify(last));
    check(last && /sólo plugins marcado/.test(last.result) && /no se toca base de datos/.test(last.result) && !/se pierde lo que cambió después/.test(last.result), 'por partes: el resultado dice que «Volver a la copia previa» devuelve sólo los plugins y no toca la base de datos', JSON.stringify(last && last.result));
    await shot('partes-terminada');
    await page.setViewport({ width: 1280, height: 900 });
    await undoParts();
  }
  await page.setViewport({ width: 1280, height: 900 });
  labMemory('64M');

  if (!network) {
    // 4) Una falla a mitad del cambio: vuelve sola a la copia previa y el panel lo dice.
    await lab('mutate', '&round=4');
    writeFileSync(`${dir}/ctl/fail-move`, '1');
    try {
      if ('landed' === await restoreFromRow(['plugins'], { label: 'falla' })) {
        let backShot = false;
        const back = await follow('falla', (s) => ended(s) && s.kind === 'rollback', { onSample: async (s) => { if (!backShot && s.title === 'Volviendo a la copia previa') { backShot = true; await shot('falla-vuelta-en-curso'); } } });
        const last = back.last;
        // La vuelta de sólo los plugins cabe a veces en el mismo tick que da la restauración por fallida: entonces el panel
        // pasa directo a su resultado (lo comprueba el caso siguiente: ese título sólo sale siguiendo la vuelta).
        const backSeen = back.seen.some((s) => s.title === 'Volviendo a la copia previa');
        if (!backSeen) log('· falla: la vuelta a la copia previa terminó en el mismo tick que la restauración fallida; el panel pasó directo a su resultado');
        check(backSeen || (back.last && back.last.title === 'El sitio volvió a la copia previa'), 'falla: el panel sigue la vuelta a la copia previa', JSON.stringify(back.seen.slice(-8)));
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
    await halfChanged();
  }
  const memory = existsSync(`${dir}/ctl/memory.jsonl`) ? readFileSync(`${dir}/ctl/memory.jsonl`, 'utf8').split('\n').filter(Boolean).map((line) => JSON.parse(line)) : [];
  // Los ticks de las restauraciones (con nonce o con el testigo): todos con los 64 MB del propietario.
  // Sólo las que empezaron con una restauración en curso: terminada, la cola vuelve al tick con nonce (revisión B3).
  const before = (r) => memorySwitch && r.start && r.start < memorySwitch;
  const early = memory.slice(memoryMark).filter((r) => r.restoring !== false && before(r));
  if (early.length) log(`· ${early.length} petición(es) de la restauración empezaron antes de volver a ${RESTORE_MEMORY} (la ventana de la petición que la empieza con 64 MB a propósito): ${early.map((r) => `${r.action} ${r.limit}${r.error ? ' ' + r.error.slice(0, 60) : ''}`).join('; ')}`);
  const restoring = memory.slice(memoryMark).filter((r) => r.restoring !== false && !before(r));
  const died = restoring.filter((r) => /Allowed memory|Maximum execution/.test(r.error || ''));
  log(`· ${restoring.length} peticiones hicieron avanzar las restauraciones (${restoring.filter((r) => r.action === 'digitalisimo_backups_restore_tick').length} con el testigo), con memory_limit ${[...new Set(restoring.map((r) => r.limit))].join('/')}; pico ${Math.max(0, ...restoring.map((r) => r.peak)) / 1048576} MB; ${died.length} murieron por memoria o tiempo`);
  check(restoring.length > 0 && restoring.every((r) => r.limit === RESTORE_MEMORY), `las peticiones de las restauraciones corrieron con memory_limit=${RESTORE_MEMORY}`, JSON.stringify(restoring.filter((r) => r.limit !== RESTORE_MEMORY).slice(0, 3)));
  // Salvo la de la petición que empieza la primera restauración con 64 MB (php-wasm, también con main), ninguna.
  const fatals = existsSync(`${dir}/ctl/fatal.jsonl`) ? readFileSync(`${dir}/ctl/fatal.jsonl`, 'utf8').split('\n').filter(Boolean).slice(fatalMark).join('\n') : '';
  check(!fatals, `ninguna otra petición terminó en error fatal (la esperada: ${fatalMark})`, fatals.slice(0, 1500));
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
