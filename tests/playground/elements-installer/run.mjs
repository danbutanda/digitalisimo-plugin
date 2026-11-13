// Conductor en Chrome del ensayo del instalador de DIGITALÍSIMO Elements (elements-installer.sh), con puppeteer-core de
// PUPPETEER_PATH. Todo por las pantallas reales de WordPress: inicia sesión, sube el ZIP por Plugins → Añadir nuevo →
// Subir plugin y pulsa los botones que vería el administrador. El estado lo lee de la sonda del mu-plugin del ensayo.
// Modos:
//   single   WordPress individual: el ZIP de Elements (31 MB) no entra por la subida (el problema real); el instalador
//            sí; con el índice inaccesible, al activarlo explica la causa, ofrece la alternativa manual y «Reintentar»
//            y se queda; con el índice de vuelta, «Reintentar» instala y activa Elements y el instalador se borra.
//   network  Red: subido y activado en toda la red, Elements queda activo en toda la red y el instalador se borra.
//   site     Red: subido en la red y activado sólo en el subsitio «uno», Elements queda activo sólo en «uno».
//   github   Como «single» sin fallo previo y sin origen de pruebas: Elements llega de la última Release real.
// Escribe <dir>/summary.json con cada comprobación y sale 1 si alguna falla.
// Uso: node run.mjs <modo> <puerto> <host> <dir>
import http from 'node:http';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, unlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [mode, port, host, dir] = process.argv.slice(2);
const cfg = JSON.parse(readFileSync(`${dir}/probe/mode.json`, 'utf8'));
const shots = `${dir}/shots`;
mkdirSync(shots, { recursive: true });
const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const base = `http://${host}`;
const SELF = 'digitalisimo-elements-installer/digitalisimo-elements-installer.php';
const ELEMENTS = 'digitalisimo-elements/pro-elements.php';
const LONG = 900_000;
const checks = [];
const ok = (name, pass, detail = '') => {
  checks.push({ name, pass: Boolean(pass), detail: String(detail).slice(0, 600) });
  console.log(`${pass ? 'ok' : 'FALLA'} · ${name}${pass ? '' : ` · ${String(detail).slice(0, 400)}`}`);
};

function probe() {
  return new Promise((resolve, reject) => {
    const req = http.get({ host: '127.0.0.1', port: Number(port), path: `/?digi_ei=state&t=${cfg.token}`, headers: { Host: host }, timeout: 300_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        try { resolve(JSON.parse(text)); } catch { reject(new Error(`Sonda ilegible (${res.statusCode}): ${text.slice(0, 300)}`)); }
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error('La sonda no respondió')));
  });
}
const requests = () => (existsSync(cfg.srv_log) ? readFileSync(cfg.srv_log, 'utf8').split('\n').filter(Boolean).map((line) => JSON.parse(line)).filter((entry) => entry.route) : []);
const phpErrors = (html) => (html.match(/(<b>)?(Fatal error|Parse error|Warning|Notice|Deprecated)(<\/b>)?:\s[^\n<]{0,200}/g) || []);

const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dei-' : `${tmpdir()}/dei-`);
const browser = await puppeteer.launch({
  userDataDir: profile,
  env: { ...process.env, TMPDIR: existsSync('/tmp') ? '/tmp' : tmpdir() },
  executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
  headless: true,
  protocolTimeout: LONG,
  args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${host} 127.0.0.1:${port}, MAP * ~NOTFOUND`],
});
const page = await browser.newPage();
page.setDefaultTimeout(LONG);
page.setDefaultNavigationTimeout(LONG);
await page.setViewport({ width: 1280, height: 900 });
// WordPress 7.x anima las navegaciones de la administración con View Transitions y Chrome lanza «InvalidStateError:
// Transition was aborted…» cuando una navegación corta una transición: es del escritorio, no del instalador (que no
// lleva JavaScript propio). Se cuentan aparte; cualquier otro error de JavaScript hace fallar el ensayo.
const jsErrors = [];
let viewTransitionErrors = 0;
page.on('pageerror', (e) => {
  const message = String(e.message || e).slice(0, 300);
  if (/Transition was aborted because of invalid state/.test(message)) viewTransitionErrors++;
  else jsErrors.push(`${page.url().replace(base, '')}: ${message}`);
});
page.on('dialog', (d) => d.accept());

async function shot(name) {
  try { await page.screenshot({ path: `${shots}/${name}.png`, fullPage: true }); } catch { /* sin captura */ }
  writeFileSync(`${shots}/${name}.html`, await page.content());
}
const text = () => page.evaluate(() => document.body.innerText);

async function login(path = '/wp-login.php') {
  for (let attempt = 0; attempt < 3 && !/wp-admin/.test(page.url()); attempt++) {
    await page.goto(base + path, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await page.$('#user_login'))) continue;
    await page.$eval('#user_login', (el) => { el.value = ''; });
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), page.click('#wp-submit')]);
  }
  ok('inicia sesión como admin', /wp-admin/.test(page.url()), page.url());
}

/** Sube un ZIP por la pantalla real de subida (sitio o red) y devuelve el código y el texto de la respuesta. */
async function upload(zip, network) {
  await page.goto(`${base}/wp-admin/${network ? 'network/' : ''}plugin-install.php?tab=upload`, { waitUntil: 'load' });
  const input = await page.$('#pluginzip');
  if (!input) return { status: 0, text: 'sin formulario de subida' };
  await input.uploadFile(zip);
  const [response] = await Promise.all([
    page.waitForNavigation({ waitUntil: 'load', timeout: LONG }),
    page.evaluate(() => { const button = document.querySelector('#install-plugin-submit'); button.disabled = false; button.click(); }),
  ]);
  return { status: response ? response.status() : 0, text: await text() };
}

/** Pulsa un enlace o botón y espera a que termine la navegación (con redirecciones y la instalación). */
async function follow(selector) {
  const target = await page.$(selector);
  if (!target) return { status: 0, text: `sin ${selector}` };
  const [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: LONG }), target.click()]);
  return { status: response ? response.status() : 0, text: await text(), url: page.url(), html: await page.content() };
}

const activateLink = 'a.button-primary[href*="action=activate"]';
const retryButton = 'form[action*="instalar=1"] button[type="submit"]';

/** El instalador terminó bien: Elements activo donde corresponde y el instalador desactivado y borrado. */
async function expectDone(label, result, where) {
  const state = await probe();
  ok(`${label}: la pantalla del instalador dice «Listo» con la versión y el ámbito`, /Listo: DIGITALÍSIMO Elements \d+(\.\d+)+ está instalado y activo/.test(result.text) && (!where || result.text.includes(where)), result.text.slice(0, 600));
  ok(`${label}: la pantalla dice que el instalador se borró y enlaza a Elements`, /se desactivó y se borró/.test(result.text) && /Ir a DIGITALÍSIMO Elements|Ver los plugins de la red/.test(result.text), result.text.slice(-400));
  ok(`${label}: la pantalla no muestra errores de PHP`, !phpErrors(result.html).length && !/critical error|error crítico/i.test(result.text), phpErrors(result.html).join(' | '));
  ok(`${label}: el instalador ya no existe ni está activo`, !state.dirs.installer && !state.plugins.includes(SELF) && !state.sitewide.includes(SELF) && Object.values(state.blogs).every((list) => !list.includes(SELF)), JSON.stringify(state));
  ok(`${label}: Elements quedó en ${ELEMENTS}`, state.dirs.elements && state.plugins.includes(ELEMENTS), JSON.stringify(state.plugins));
  return state;
}

const summary = { mode, host, started: new Date().toISOString() };
try {
  await login();
  let state = await probe();
  summary.versions = state.versions;
  // En una red, WordPress baja además el máximo de Medios a «fileupload_maxk» (1500 KB por defecto): «1 MB».
  ok('el sitio tiene los límites de un hosting típico (upload_max_filesize 2M, post_max_size 8M)', state.ini.upload_max_filesize === '2M' && state.ini.post_max_size === '8M' && state.wp_max_upload_size > 0 && state.wp_max_upload_size <= 2 * 1024 * 1024, `${JSON.stringify(state.ini)} wp_max_upload_size=${state.wp_max_upload_size}`);
  await page.goto(`${base}/wp-admin/media-new.php`, { waitUntil: 'load' });
  const announced = (await text()).match(/Maximum upload file size: [^\n]*/)?.[0] || '';
  summary.announced = announced;
  ok(`WordPress anuncia «Maximum upload file size: ${mode === 'network' || mode === 'site' ? '1' : '2'} MB»`, new RegExp(`Maximum upload file size: ${mode === 'network' || mode === 'site' ? '1' : '2'} MB`).test(announced), announced);
  ok('Elements no está instalado al empezar', !state.dirs.elements && !state.plugins.includes(ELEMENTS), JSON.stringify(state.plugins));

  if (mode === 'single' || mode === 'github') {
    if (mode === 'single') {
      // El problema real: el ZIP completo de Elements no entra por la subida de WordPress con estos límites.
      const big = await upload(cfg.elements_zip, false);
      await shot('single-1-elements-zip-rechazado');
      ok('subir el ZIP de Elements (31 MB) falla como en el hosting: «The link you followed has expired»', /The link you followed has expired|exceeds the upload_max_filesize/.test(big.text), `${big.status} ${big.text.slice(0, 200)}`);
      state = await probe();
      ok('y Elements sigue sin instalar', !state.dirs.elements, JSON.stringify(state.dirs));
    }
    const small = await upload(cfg.installer_zip, false);
    await shot(`${mode}-2-instalador-subido`);
    ok('el instalador (pocos KB) entra por la misma subida', /Plugin installed successfully/.test(small.text) && (await page.$(activateLink)), small.text.slice(0, 300));
    if (mode === 'single') {
      // Índice inaccesible: el instalador explica la causa, ofrece la alternativa manual y «Reintentar», y se queda.
      writeFileSync(cfg.fail_file, '1');
      const failed = await follow(activateLink);
      await shot('single-3-indice-inaccesible');
      ok('al activarlo lleva a su pantalla y empieza solo', failed.url.includes('page=instalar-digitalisimo-elements') && failed.url.includes('instalar=1'), failed.url);
      ok('índice inaccesible: mensaje con la causa (HTTP 503)', /No se pudo instalar DIGITALÍSIMO Elements/.test(failed.text) && /HTTP 503/.test(failed.text), failed.text.slice(0, 500));
      ok('índice inaccesible: alternativa manual y «Reintentar»', /Alternativa manual/.test(failed.text) && /wp-content\/plugins\//.test(failed.text) && (await page.$(retryButton)), failed.text.slice(-500));
      state = await probe();
      ok('índice inaccesible: el instalador sigue instalado y activo, y Elements sin instalar', state.dirs.installer && state.blogs[Object.keys(state.blogs)[0]].includes(SELF) && !state.dirs.elements, JSON.stringify(state));
      ok('índice inaccesible: sin errores de PHP en la pantalla', !phpErrors(failed.html).length, phpErrors(failed.html).join(' | '));
      unlinkSync(cfg.fail_file);
      const done = await follow(retryButton);
      await shot('single-4-reintentar-listo');
      state = await expectDone('«Reintentar» con el índice de vuelta', done, '');
    } else {
      const done = await follow(activateLink);
      await shot('github-3-listo');
      state = await expectDone('desde la Release real de GitHub', done, '');
    }
    ok('Elements activo en el sitio', state.blogs[Object.keys(state.blogs)[0]].includes(ELEMENTS), JSON.stringify(state.blogs));
  } else if (mode === 'network') {
    const small = await upload(cfg.installer_zip, true);
    await shot('network-1-instalador-subido');
    ok('red: el instalador entra por la subida de la red y ofrece «Network Activate»', /Plugin installed successfully/.test(small.text) && (await page.$(`${activateLink}[href*="networkwide=1"]`)), small.text.slice(0, 300));
    const done = await follow(`${activateLink}[href*="networkwide=1"]`);
    await shot('network-2-listo');
    ok('red: al activarlo en toda la red lleva a su pantalla de la red', done.url.includes('/wp-admin/network/plugins.php?page=instalar-digitalisimo-elements'), done.url);
    state = await expectDone('red', done, 'en toda la red');
    ok('red: Elements activo en toda la red', state.sitewide.includes(ELEMENTS), JSON.stringify(state.sitewide));
  } else if (mode === 'site') {
    const small = await upload(cfg.installer_zip, true);
    ok('red: el instalador entra por la subida de la red', /Plugin installed successfully/.test(small.text), small.text.slice(0, 300));
    await page.goto(`${base}/uno/wp-admin/plugins.php`, { waitUntil: 'load' });
    await shot('site-1-plugins-del-subsitio');
    const done = await follow(`tr[data-plugin="${SELF}"] .activate a`);
    await shot('site-2-listo');
    ok('subsitio: al activarlo en «uno» lleva a su pantalla de ese sitio', done.url.includes('/uno/wp-admin/plugins.php?page=instalar-digitalisimo-elements'), done.url);
    state = await expectDone('subsitio', done, 'en este sitio');
    const uno = String(JSON.parse(readFileSync(`${dir}/probe/setup.json`, 'utf8')).sites.uno);
    ok('subsitio: Elements activo sólo en «uno», ni en la red ni en el sitio principal', state.blogs[uno]?.includes(ELEMENTS) && !state.sitewide.includes(ELEMENTS) && !state.blogs['1']?.includes(ELEMENTS), JSON.stringify(state));
  } else {
    throw new Error(`Modo desconocido: ${mode}`);
  }

  // Después: Plugins muestra Elements activo y ya no el instalador; el escritorio carga sin errores.
  const after = mode === 'network' ? '/wp-admin/network/plugins.php' : mode === 'site' ? '/uno/wp-admin/plugins.php' : '/wp-admin/plugins.php';
  await page.goto(base + after, { waitUntil: 'load' });
  await shot(`${mode}-9-plugins`);
  const rows = await page.evaluate(() => [...document.querySelectorAll('tr[data-plugin]')].map((tr) => ({ plugin: tr.getAttribute('data-plugin'), active: tr.classList.contains('active') })));
  ok('Plugins: Elements activo y sin la fila del instalador', rows.some((row) => row.plugin === ELEMENTS && row.active) && !rows.some((row) => row.plugin === SELF), JSON.stringify(rows));
  ok('Plugins: sin errores de PHP', !phpErrors(await page.content()).length, phpErrors(await page.content()).join(' | '));
  if (mode !== 'github') {
    const log = requests();
    summary.requests = log;
    ok('el servidor local sirvió el índice y el ZIP de Elements (sin GitHub)', log.some((r) => r.route.endsWith('/digitalisimo-plugins.json') && r.code === 200) && log.some((r) => /\/releases\/download\/v[\d.]+\/digitalisimo-elements-[\d.]+\.zip$/.test(r.route) && r.code === 200 && r.bytes > 1_000_000), JSON.stringify(log));
  }
  ok('sin errores de JavaScript en las pantallas', !jsErrors.length, jsErrors.join(' | '));
} catch (error) {
  ok('el ensayo terminó sin excepciones', false, error.stack || error);
  await shot(`${mode}-excepcion`).catch(() => {});
} finally {
  await browser.close().catch(() => {});
  rmSync(profile, { recursive: true, force: true });
}
summary.view_transition_errors = viewTransitionErrors;
summary.finished = new Date().toISOString();
summary.checks = checks;
summary.failed = checks.filter((check) => !check.pass).length;
writeFileSync(`${dir}/summary.json`, JSON.stringify(summary, null, 1));
console.log(`${mode}: ${checks.length - summary.failed}/${checks.length} comprobaciones bien`);
process.exit(summary.failed ? 1 : 0);
