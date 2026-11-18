// Comprobaciones del ensayo backups-restore-files.sh. Fase «origin»: un respaldo por REST (POST /backups y /jobs/tick).
// Fase «dest»: el respaldo del origen (de otro servidor, sin registro aquí) se restaura por REST con «RESTAURAR SIN FIRMA»
// y después: los drop-ins del origen no se instalaron y el de aquí sigue, la caché de páginas vieja se vació, .htaccess
// tiene el bloque de WordPress (Playground no es Apache: la regla del ensayo lo fuerza), lo restaurado tiene permisos de
// WordPress (0755/0644, no el 0700 del área privada) y en Chrome la portada y la página interna (enlace permanente)
// responden 200 con el CSS, el JS y la imagen restaurados, aplicados. Luego se borra el tema del origen y se restaura sólo
// la base de datos: el tema que falta vuelve al tema por defecto de WordPress y la portada se ve. Escribe «ok · …» o
// «FALLA · …» en <dir>/result.txt.
// Uso: node check.mjs origin <puerto> <dir> | node check.mjs dest <puerto> <dir> <dir-origen>
import http from 'node:http';
import { appendFileSync, existsSync, mkdtempSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { homedir, tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const [phase, port, dir, originDir] = process.argv.slice(2);
const setup = JSON.parse(readFileSync(`${dir}/probe/setup.json`, 'utf8'));
const token = readFileSync(`${dir}/web/token.txt`, 'utf8').trim();
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 900)}` : ''}`); }
  return ok;
};
function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : (typeof body === 'string' ? body : JSON.stringify(body));
    const all = { ...headers };
    if (data !== undefined) all['Content-Length'] = Buffer.byteLength(data);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 3_600_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const basic = (password) => ({ Authorization: `Basic ${Buffer.from(`admin:${password}`).toString('base64')}` });
const api = (method, route, { headers = {}, body } = {}) => request(method, `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1${route}`)}`, { headers: body === undefined ? headers : { 'Content-Type': 'application/json', ...headers }, body });
const probe = async (action = 'state') => { const res = await request('GET', `/wp-content/digi-web/probe.php?token=${token}&do=${action}`); return res.json || { error: `${res.status} ${res.text.slice(0, 300)}` }; };
// Restaura por REST y sigue el trabajo con su ficha de lectura hasta el final.
async function restore(file, password, extra = {}) {
  const started = Date.now();
  const res = await api('POST', `/backups/${file}/restore`, { headers: basic(password), body: { confirm: 'RESTAURAR SIN FIRMA', addresses: 'current', ...extra } });
  let restored = res.status === 200 ? res.json : null;
  if (res.status === 202 && res.json?.follow) {
    const follow = res.json.follow;
    for (let i = 0; i < 900 && !restored; i++) {
      const job = await request('GET', `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1/jobs/${follow.job}`)}`, { headers: { [follow.header]: follow.token } });
      if (job.status === 200 && job.json?.terminal) { restored = job.json.restore?.result || { restored: false, status: job.json.status, error: job.json.error }; break; }
      await request('GET', '/wp-cron.php');
      await new Promise((done) => setTimeout(done, 1000));
    }
  }
  return { restored: restored || res.json || { text: res.text }, seconds: Math.round((Date.now() - started) / 1000), status: res.status };
}
// Chrome: carga la página y devuelve las respuestas de sus recursos y lo que se aplicó.
async function chrome(paths) {
  const exe = process.env.CHROME_PATH || '/usr/bin/google-chrome';
  const modules = (process.env.PUPPETEER_PATH || '').replace(/\/$/, '') || (() => {
    const base = join(homedir(), '.npm/_npx');
    try { for (const d of readdirSync(base)) if (existsSync(join(base, d, 'node_modules/puppeteer-core/package.json'))) return join(base, d, 'node_modules'); } catch { /* sin caché de npx */ }
    return '';
  })();
  const pptr = modules ? join(modules, 'puppeteer-core/lib/puppeteer/puppeteer-core.js') : '';
  if (!existsSync(exe) || !pptr || !existsSync(pptr)) return { skipped: `sin ${existsSync(exe) ? 'puppeteer-core' : 'Chrome'}` };
  const { default: puppeteer } = await import(pathToFileURL(pptr).href);
  const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgf-' : join(tmpdir(), 'dgf-'));
  const browser = await puppeteer.launch({ userDataDir: profile, env: { ...process.env, TMPDIR: existsSync('/tmp') ? '/tmp' : tmpdir() }, executablePath: exe, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
  const out = {};
  try {
    for (const path of paths) {
      const page = await browser.newPage();
      const responses = [];
      page.on('response', (r) => responses.push({ url: r.url(), status: r.status(), type: r.request().resourceType() }));
      const doc = await page.goto(`http://127.0.0.1:${port}${path}`, { waitUntil: 'load', timeout: 300_000 });
      const seen = await page.evaluate(() => ({
        background: getComputedStyle(document.body).backgroundColor,
        demo: window.digiDemo === 1,
        image: (() => { const img = document.getElementById('digi-foto'); return img ? img.complete && img.naturalWidth > 0 : null; })(),
        theme: document.body.classList.contains('tema-origen'),
        text: document.body.innerText.slice(0, 300),
      }));
      out[path] = { status: doc ? doc.status() : 0, responses, ...seen };
      await page.close();
    }
  } finally {
    await browser.close();
  }
  return out;
}

try {
  log(`· ${phase}: ${setup.versions}`);
  if (phase === 'origin') {
    let res = await api('POST', '/backups', { headers: basic(setup.password), body: {} });
    const id = res.json?.id || res.json?.job?.id;
    check(res.status === 202 && id, 'origen: POST /backups encola el respaldo (202)', `${res.status} ${res.text}`);
    for (let i = 0; i < 300 && id; i++) {
      const job = await api('GET', `/jobs/${id}`, { headers: basic(setup.password) });
      if (job.status !== 200 || job.json?.terminal) { res = job; break; }
      const tick = await api('POST', '/jobs/tick', { headers: basic(setup.password), body: {} });
      if (tick.json?.result !== 'worked') await new Promise((done) => setTimeout(done, 3000));
    }
    check(res.json?.status === 'success', 'origen: el respaldo termina verificado', res.text.slice(0, 400));
    const list = await api('GET', '/backups', { headers: basic(setup.password) });
    const file = list.json?.[0]?.file;
    check(/^digitalisimo-.*\.zip$/.test(file || ''), 'origen: el respaldo está en el historial', list.text.slice(0, 300));
    const state = await probe();
    check(state.object_cache && state.advanced_cache?.includes('ORIGEN'), 'origen: el respaldo se hizo con los drop-ins de su servidor', JSON.stringify(state).slice(0, 300));
    writeFileSync(`${dir}/origin.json`, JSON.stringify({ file }));
  } else {
    const origin = JSON.parse(readFileSync(`${originDir}/probe/setup.json`, 'utf8'));
    const { file } = JSON.parse(readFileSync(`${originDir}/origin.json`, 'utf8'));
    let before = await probe();
    check(before.cache_files?.includes('wp-content/cache/supercache/destino/pagina-interna/index.html') && before.htaccess === null, 'destino: antes, una caché de páginas vieja y sin .htaccess', JSON.stringify(before).slice(0, 300));
    const first = await restore(file, setup.password);
    check(first.restored?.restored === true, `destino: el respaldo del otro servidor se restaura por REST (${first.seconds} s)`, JSON.stringify(first.restored).slice(0, 900));
    const notice = String(first.restored?.notice || '');
    log(`· aviso: ${notice.slice(0, 1500)}`);
    const state = await probe();
    check(!state.error, 'destino: el sitio carga después de restaurar (ningún drop-in del origen lo rompe)', JSON.stringify(state).slice(0, 400));
    check(state.object_cache === null && /ESTE servidor/.test(state.advanced_cache || ''), 'destino: object-cache.php del origen no se instaló y advanced-cache.php de este servidor sigue', `${String(state.object_cache).slice(0, 120)} · ${state.advanced_cache}`);
    check(notice.includes('drop-ins') && notice.includes('object-cache.php') && notice.includes('advanced-cache.php'), 'destino: el informe nombra los drop-ins que no se instalaron y los que se conservaron', notice);
    check(JSON.stringify(state.cache_files) === JSON.stringify(['wp-content/cache/.htaccess']), 'destino: la caché de páginas vieja (WP Super Cache, Divi) se vació y su .htaccess se quedó', JSON.stringify(state.cache_files));
    check(notice.includes('caché de páginas'), 'destino: el informe dice que se vació la caché de páginas', notice);
    check(/# BEGIN WordPress[\s\S]*RewriteRule \. \/index\.php \[L\][\s\S]*# END WordPress/.test(state.htaccess || ''), 'destino: .htaccess tiene el bloque de WordPress (enlaces permanentes del respaldo)', state.htaccess);
    const modes = state.modes || {};
    // Al menos los de WordPress (FS_CHMOD_DIR 0755, FS_CHMOD_FILE 0644): el servidor web, otro usuario, los lee.
    const wrong = Object.entries(modes).filter(([path, mode]) => { const want = /\.(css|js|png)$/.test(path) ? 0o644 : 0o755; return !mode || (parseInt(mode, 8) & want) !== want; });
    check(Object.keys(modes).length >= 7 && !wrong.length, 'destino: lo restaurado tiene permisos de WordPress (carpetas 0755, archivos 0644, o más)', JSON.stringify(wrong.length ? wrong : modes));
    log(`· permisos: ${JSON.stringify(modes)}`);
    check(state.template === 'tema-origen' && (state.active_plugins || []).includes('digitalisimo-backups/digitalisimo-backups.php') && (state.active_plugins || []).includes('digi-demo-assets/digi-demo-assets.php'), 'destino: tema y plugins del respaldo activos, DIGITALÍSIMO Backups también', JSON.stringify([state.template, state.active_plugins]));
    const seen = await chrome(['/', origin.page]);
    if (seen.skipped) log(`AVISO · Chrome: ${seen.skipped}`);
    else {
      for (const [path, view] of Object.entries(seen)) {
        const assets = view.responses.filter((r) => /digi-demo-assets\/assets\/demo\.(css|js)|tema-origen\/style\.css|foto-origen\.png/.test(r.url));
        const bad = view.responses.filter((r) => r.status >= 400);
        check(view.status === 200 && !bad.length, `Chrome: ${path} responde 200 y ningún recurso falla`, JSON.stringify(bad.length ? bad : view.status));
        check(assets.filter((r) => /\.(css|js)/.test(r.url)).length >= 3 && assets.every((r) => r.status === 200), `Chrome: ${path} carga el CSS y el JS del plugin y del tema restaurados (200)`, JSON.stringify(assets));
        check(view.background === 'rgb(1, 2, 3)' && view.demo && view.theme, `Chrome: ${path} aplica el CSS y el JS restaurados con el tema del origen`, JSON.stringify(view));
      }
      const inner = seen[origin.page];
      check(inner && inner.image === true && inner.text.includes('Contenido interno'), `Chrome: la página interna ${origin.page} (enlace permanente) muestra su imagen restaurada`, JSON.stringify(inner));
    }
    // Tema que falta: se borra el del origen y se restaura sólo la base de datos.
    const dropped = await probe('drop-theme');
    check(dropped.dropped === true, 'destino: se borra el tema del origen', JSON.stringify(dropped));
    let second = await restore(file, setup.password, { parts: 'database' });
    if (second.restored?.restored !== true && /401|rest_forbidden|No autorizado/.test(JSON.stringify(second.restored))) second = await restore(file, origin.password, { parts: 'database' });
    check(second.restored?.restored === true, `destino: restaurar sólo la base de datos con el tema del origen ausente (${second.seconds} s)`, JSON.stringify(second.restored).slice(0, 900));
    const notice2 = String(second.restored?.notice || '');
    log(`· aviso: ${notice2.slice(0, 1500)}`);
    const after = await probe();
    check(after.template && after.template !== 'tema-origen' && after.template === after.stylesheet && /^twenty/.test(after.template), `destino: el tema que falta vuelve al tema por defecto de WordPress (${after.template})`, JSON.stringify([after.template, after.stylesheet, after.error]));
    check(notice2.includes('«tema-origen»') && notice2.includes(after.template || '-'), 'destino: el informe dice qué tema faltaba y cuál se activó', notice2);
    check(after.object_cache === null, 'destino: tampoco ahora se instaló el drop-in del origen', String(after.object_cache).slice(0, 120));
    const home = await request('GET', '/');
    check(home.status === 200 && home.text.length > 500 && !home.text.includes('tema-origen'), 'destino: la portada se ve con el tema por defecto (no en blanco)', `${home.status} ${home.text.length}`);
  }
} catch (error) {
  check(false, `${phase}: error inesperado`, error?.stack || error);
}
log('TERMINADO');
process.exit(failures ? 1 : 0);
