// Ensayo «los administradores de este sitio siguen entrando» con Chrome sin interfaz (puppeteer-core de PUPPETEER_PATH)
// contra el Playground que levantó backups-keep-site-admins.sh. Todo por la pantalla, como lo haría el propietario:
//   a   en el sitio A, «dueno» entra y hace «Respaldar ahora»; el ZIP sale del almacén para subirlo al sitio B.
//   b   en el sitio B recién instalado, «admin» sube el de A por «Subir un respaldo» (queda en la lista), abre «Restaurar»
//       de su fila (la confirmación dice quiénes siguen entrando y «Opciones» ofrece «Conservar los administradores de
//       este sitio» con sus nombres), escribe RESTAURAR y lo restaura. Después: «admin» sigue dentro (su navegador), «admin»,
//       «soporte» y «dueno» entran con su contraseña y son administradores (Ajustes → Generales), la cookie del navegador
//       de «soporte» de antes de restaurar sigue valiendo, su contraseña de aplicación vale en la REST, «editora» entra
//       como editora (no administra), «colaborador» (editor de B) ya no existe, el contenido es el de A con las
//       direcciones de B y nada se rompe (portada, escritorio, sin errores de PHP ni de JavaScript).
// Capturas a 1280 px en <out>/shots y el resultado de cada comprobación en <out>/result.txt; las contraseñas sólo se leen
// de <dir>/probe/secrets.json y nunca se escriben.
// Uso: node drive.mjs <a|b> <puerto> <host> <dir> <out> <etiqueta> [ZIP del sitio A] [secrets.json del sitio A]
import http from 'node:http';
import { appendFileSync, copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [phase, port, host, dir, out, label = 'nuevo', zipA = '', secretsA = ''] = process.argv.slice(2);
const shots = `${out}/shots`;
mkdirSync(shots, { recursive: true });
const store = `${dir}/store`;
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const secrets = JSON.parse(readFileSync(`${dir}/probe/secrets.json`, 'utf8'));
const fromA = secretsA && existsSync(secretsA) ? JSON.parse(readFileSync(secretsA, 'utf8')) : {};
const forbidden = [...Object.values(secrets), ...Object.values(fromA)].filter((value) => typeof value === 'string' && value.length > 8);
const base = `http://${host}`;
const tag = `${phase}-${label}`;
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
let failures = 0;
const log = (line) => { appendFileSync(`${out}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${tag} · ${what}`);
  else { failures++; log(`FALLA · ${tag} · ${what}${detail ? ` · ${String(detail).slice(0, 900)}` : ''}`); }
  return ok;
};
const seen = [];
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
        seen.push(text);
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', (error) => resolve({ status: 0, headers: {}, text: String(error.message), json: null }));
    req.on('timeout', () => req.destroy(new Error('tiempo agotado')));
    if (body !== undefined) req.write(body);
    req.end();
  });
}
const probe = async () => (await request('GET', `/?digi_kadm=state&token=${token}`)).json || {};
const jobs = () => (existsSync(`${store}/jobs`) ? readdirSync(`${store}/jobs`).map((id) => { try { return JSON.parse(readFileSync(`${store}/jobs/${id}/job.json`, 'utf8')); } catch { return null; } }).filter(Boolean) : []);
const terminal = (job) => job && ['success', 'failed', 'cancelled'].includes(job.status);
const zips = () => readdirSync(store).filter((name) => /^digitalisimo-.*\.(zip|dgbk)$/.test(name));

const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgk-' : `${tmpdir()}/dgk-`);
const browser = await puppeteer.launch({
  userDataDir: profile,
  env: { ...process.env, TMPDIR: existsSync('/tmp') ? '/tmp' : tmpdir() },
  executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
  headless: true,
  protocolTimeout: 900_000,
  args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', '--font-render-hinting=none', `--host-resolver-rules=MAP ${host} 127.0.0.1:${port}, MAP * ~NOTFOUND`],
});
const jsErrors = [];
async function newPage(context = null) {
  const page = await (context || browser).newPage();
  page.setDefaultTimeout(300_000);
  page.setDefaultNavigationTimeout(300_000);
  await page.setViewport({ width: 1280, height: 900 });
  page.on('pageerror', (e) => jsErrors.push(String(e.message || e).slice(0, 300)));
  page.on('dialog', async (d) => { await d.accept(); });
  return page;
}
const page = await newPage();
const screen = '/wp-admin/admin.php?page=digitalisimo-backups';
const cookieHeader = async (p = page) => (await p.cookies(base)).map((c) => `${c.name}=${c.value}`).join('; ');

async function shot(p, name) {
  await p.evaluate(() => window.scrollTo(0, 0)).catch(() => {});
  await sleep(400);
  await p.screenshot({ path: `${shots}/${tag}-${name}.png`, fullPage: true });
  log(`captura · ${tag}-${name}.png`);
}
/** Inicia sesión en $p con $user y su contraseña; true si llega al escritorio. */
async function login(p, user, password) {
  for (let attempt = 0; attempt < 2 && !/\/wp-admin\//.test(p.url()); attempt++) {
    await p.goto(`${base}/wp-login.php`, { waitUntil: 'load', timeout: 120_000 }).catch(() => {});
    if (!(await p.$('#user_login'))) continue;
    // wp-login.php enfoca y selecciona el campo de usuario a los 200 ms de cargar: si eso cae mientras se teclea, lo que
    // sigue reemplaza lo seleccionado y el usuario llega cortado. Se espera a que pase y se ponen los valores enteros.
    await sleep(700);
    await p.$eval('#user_login', (i, v) => { i.value = v; }, user);
    await p.$eval('#user_pass', (i, v) => { i.value = v; }, password);
    const typed = await p.$eval('#user_login', (i) => i.value);
    if (typed !== user) continue;
    await Promise.all([p.waitForNavigation({ waitUntil: 'load', timeout: 120_000 }).catch(() => null), p.click('#wp-submit')]);
    if (await p.$('#login_error')) break;
  }
  if (!/\/wp-admin\//.test(p.url())) p.loginError = await p.$eval('#login_error', (e) => e.textContent.trim()).catch(() => '');
  return /\/wp-admin\//.test(p.url());
}
/** Un navegador aparte (sin las cookies de los demás) para entrar como $user. */
async function asUser(user, password) {
  const context = await browser.createBrowserContext();
  const p = await newPage(context);
  const ok = await login(p, user, password);
  return { context, page: p, ok };
}
/** Si quien está en $p administra el sitio: Ajustes → Generales responde con su formulario. */
async function administers(p) {
  const res = await p.goto(`${base}/wp-admin/options-general.php`, { waitUntil: 'load' }).catch(() => null);
  return Boolean(res && res.status() === 200 && (await p.$('#blogname')));
}
async function go(path, p = page) {
  const res = await p.goto(base + path, { waitUntil: 'load' });
  await sleep(800);
  return res ? res.status() : 0;
}
/** Hace avanzar los trabajos sin terminar como WP-Cron (y, en una restauración, con su testigo) hasta que terminan. */
async function drive(seconds = 900) {
  const started = Date.now();
  while (Date.now() - started < seconds * 1000) {
    const open = jobs().filter((job) => !terminal(job));
    if (!open.length) break;
    const restoreCookie = (await page.cookies(base)).find((c) => c.name === 'digitalisimo_backups_restore');
    if (restoreCookie && open[0].kind === 'restore') {
      await request('POST', `/wp-admin/admin-ajax.php?digitalisimo_backups_restore=${restoreCookie.value}`, { headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: await cookieHeader() }, body: new URLSearchParams({ action: 'digitalisimo_backups_restore_tick', digitalisimo_backups_restore: restoreCookie.value }).toString() });
    }
    await request('GET', '/wp-cron.php');
    await sleep(1500);
  }
  return { open: jobs().filter((job) => !terminal(job)).length, seconds: Math.round((Date.now() - started) / 1000) };
}
async function rowOf(file) {
  return page.evaluate((f) => {
    const row = [...document.querySelectorAll('tr.digitalisimo-backups-row')].find((tr) => tr.dataset.file === f);
    if (!row) return null;
    const restore = row.querySelector('[data-restore-toggle]');
    return { text: row.innerText, restore: restore ? restore.getAttribute('aria-controls') : '' };
  }, file);
}

const started = Date.now();
if (phase === 'a') {
  check(await login(page, 'dueno', secrets.dueno), '«dueno» entra en el sitio A', page.url());
  const before = new Set(zips());
  await go(screen);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click('#digitalisimo-backups-manual-form button.button-hero')]);
  const run = await drive();
  const fresh = zips().filter((name) => !before.has(name));
  check(run.open === 0 && fresh.length === 1, `«Respaldar ahora» crea el respaldo del sitio A (${run.seconds} s)`, JSON.stringify({ run, fresh }));
  await go(screen);
  await shot(page, 'respaldo-hecho');
  if (fresh[0]) copyFileSync(`${store}/${fresh[0]}`, `${out}/sitio-a.zip`);
  const state = await probe();
  check(state.users?.dueno?.roles?.includes('administrator') && state.users?.editora?.roles?.includes('editor'), 'el sitio A tiene a «dueno» (administrador) y a «editora» (editora)', JSON.stringify(state.users));
} else {
  // 0. Antes: «admin» entra (el navegador que restaura) y «soporte» tiene su sesión abierta en otro navegador.
  check(await login(page, 'admin', secrets.admin), 'antes: «admin» entra en el sitio B', page.url());
  const soporteBefore = await asUser('soporte', secrets.soporte);
  check(soporteBefore.ok && (await administers(soporteBefore.page)), 'antes: «soporte» entra en el sitio B y lo administra', soporteBefore.page.loginError || soporteBefore.page.url());
  const soporteCookie = await cookieHeader(soporteBefore.page);
  await soporteBefore.context.close();
  const appAuth = { Authorization: `Basic ${Buffer.from(`soporte:${secrets.soporte_app}`).toString('base64')}` };
  let me = await request('GET', '/?rest_route=%2Fwp%2Fv2%2Fusers%2Fme&context=edit', { headers: appAuth });
  check(me.status === 200 && (me.json?.roles || []).includes('administrator'), 'antes: la contraseña de aplicación de «soporte» vale en la REST', `${me.status} ${me.text.slice(0, 200)}`);
  let state = await probe();
  check(['admin', 'colaborador', 'soporte'].every((login) => state.users?.[login]) && state.blogname === 'Sitio B', 'antes: el sitio B tiene a «admin», «soporte» y «colaborador»', JSON.stringify(state.users));

  // 1. Subir el respaldo de A: queda en la lista de respaldos.
  await go('/wp-admin/admin.php?page=digitalisimo-backups&tab=tools&open=upload');
  const input = await page.$('#digitalisimo-backups-upload input[type=file]');
  if (check(Boolean(input), '«Subir un respaldo» ofrece elegir el archivo')) {
    await input.uploadFile(zipA);
    await page.click('#digitalisimo-backups-upload button[type=submit]');
    let status = '';
    for (let i = 0; i < 400; i++) {
      await sleep(1500);
      status = await page.$eval('#digitalisimo-backups-upload .digitalisimo-backups-upload-status', (p) => p.textContent).catch(() => '');
      const busy = await page.$eval('#digitalisimo-backups-upload button[type=submit]', (b) => b.disabled).catch(() => false);
      if (!busy && status) break;
    }
    check(/Respaldo subido y verificado/.test(status) && /RESTAURAR/.test(status), '«Subir un respaldo» deja el de A en la lista', status);
  }
  await go(screen);
  const file = await page.evaluate(() => [...document.querySelectorAll('tr.digitalisimo-backups-row')].map((tr) => tr.dataset.file).find((f) => /sitio-a/.test(f || '')) || [...document.querySelectorAll('tr.digitalisimo-backups-row')].map((tr) => tr.dataset.file)[0] || '');
  check(Boolean(file), 'el respaldo subido está en la lista', zips().join(', '));

  // 2. La confirmación: quiénes siguen entrando y la casilla con sus nombres.
  await go(`${screen}&restore=${encodeURIComponent(file)}`);
  const row = await rowOf(file);
  const panel = row?.restore ? `#${row.restore}` : '';
  if (check(Boolean(panel), 'la fila del subido ofrece «Restaurar»', row?.text)) {
    const summary = await page.$eval(`${panel} .digitalisimo-backups-keep-summary`, (p) => p.textContent).catch(() => '');
    check(/«admin» \(tú\)/.test(summary) && /«soporte»/.test(summary) && !/colaborador/.test(summary), 'la confirmación dice que siguen entrando «admin» (tú) y «soporte» (no el editor)', summary || 'sin la línea de quiénes siguen entrando');
    await page.$eval(`${panel} details.digitalisimo-backups-restore-options`, (d) => { d.open = true; }).catch(() => {});
    const option = await page.$eval(`${panel} input[name=keep_admins]`, (i) => ({ checked: i.checked, label: i.closest('label').textContent })).catch(() => null);
    check(option && option.checked && /Conservar los administradores de este sitio: «soporte»/.test(option.label), '«Opciones» ofrece «Conservar los administradores de este sitio: «soporte»», marcada', JSON.stringify(option));
    await page.$eval(panel, (el) => el.scrollIntoView()).catch(() => {});
    await sleep(300);
    await page.screenshot({ path: `${shots}/${tag}-confirmacion.png`, fullPage: true });
    log(`captura · ${tag}-confirmacion.png`);

    // 3. Restaurar con RESTAURAR, como el propietario.
    const before = new Set(jobs().map((job) => job.id));
    await page.type(`${panel} input[name=confirmation]`, 'RESTAURAR');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.click(`${panel} button.button-primary`)]);
    const run = await drive();
    const job = jobs().filter((j) => !before.has(j.id) && j.kind === 'restore').sort((a, b) => Number(b.queued_at || 0) - Number(a.queued_at || 0))[0] || null;
    check(run.open === 0 && job?.status === 'success', `la restauración terminó (${run.seconds} s)`, JSON.stringify({ status: job?.status, error: job?.error }));
  }

  // 4. Después: el contenido es el de A en las direcciones de B.
  state = await probe();
  check(state.blogname === 'Sitio A' && state.posts?.includes('Entrada del sitio A') && state.posts?.includes('Página del sitio A') && !state.posts?.includes('Entrada del sitio B') && state.home === base, 'el contenido es el del sitio A, con las direcciones del sitio B', JSON.stringify({ blog: state.blogname, home: state.home, posts: state.posts }));
  const roles = Object.fromEntries(Object.entries(state.users || {}).map(([login, user]) => [login, (user.roles || []).join(',')]));
  check(roles.admin === 'administrator' && roles.soporte === 'administrator' && roles.dueno === 'administrator' && roles.editora === 'editor' && !('colaborador' in roles), 'usuarios: «admin», «soporte» y «dueno» administradores; «editora» editora; «colaborador» (editor de B) no está', JSON.stringify(roles));

  // 5. Quien restauró sigue dentro y el aviso dice quiénes conservaron su acceso.
  await go(screen);
  const stillIn = /\/wp-admin\//.test(page.url()) && Boolean(await page.$('#adminmenu'));
  check(stillIn, '«admin» sigue dentro en el navegador con el que restauró', page.url());
  const text = await page.evaluate(() => document.body.innerText);
  check(/Cómo entrar ahora/.test(text) && /administradores de este sitio: «soporte»/.test(text), 'el aviso de la restauración dice «Cómo entrar ahora» y que «soporte» conservó su acceso', (text.match(/Cómo entrar ahora[^\n]{0,600}/) || [''])[0]);
  await shot(page, 'despues-quien-restauro');

  // 6. Cada uno entra con su contraseña en un navegador aparte.
  for (const [user, password, admin] of [['admin', secrets.admin, true], ['soporte', secrets.soporte, true], ['dueno', fromA.dueno, true], ['editora', fromA.editora, false]]) {
    if (!password) { check(false, `falta la contraseña de «${user}» para el ensayo`); continue; }
    const session = await asUser(user, password);
    const isAdmin = session.ok ? await administers(session.page) : false;
    check(session.ok && isAdmin === admin, `«${user}» entra con su contraseña${admin ? ' y administra el sitio (Ajustes → Generales)' : ' y no administra el sitio'}`, `${session.page.url()} ${isAdmin} ${session.page.loginError || ''}`);
    if (session.ok) { await go('/wp-admin/', session.page); await shot(session.page, `entra-${user}`); }
    await session.context.close();
  }
  const gone = await asUser('colaborador', secrets.colaborador);
  check(!gone.ok, '«colaborador» (editor de B, no administrador) ya no entra');
  await gone.context.close();

  // 7. La cookie del navegador de «soporte» de antes de restaurar y su contraseña de aplicación siguen valiendo.
  const dash = await request('GET', '/wp-admin/', { headers: { Cookie: soporteCookie } });
  check(dash.status === 200 && /adminmenu/.test(dash.text), 'la cookie del navegador de «soporte» de antes de restaurar sigue valiendo', `${dash.status} ${dash.headers?.location || ''}`);
  me = await request('GET', '/?rest_route=%2Fwp%2Fv2%2Fusers%2Fme&context=edit', { headers: appAuth });
  check(me.status === 200 && me.json?.slug === 'soporte' && (me.json?.roles || []).includes('administrator'), 'la contraseña de aplicación de «soporte» vale en la REST y es administrador', `${me.status} ${me.text.slice(0, 200)}`);

  // 8. Nada se rompe: portada y escritorio.
  const front = await request('GET', '/');
  check(front.status === 200 && /Sitio A/.test(front.text) && !/Fatal error|critical error/i.test(front.text), 'la portada responde con el sitio A, sin errores', front.status);
  await go('/');
  await shot(page, 'portada');
  check(!seen.some((t) => forbidden.some((secret) => t.includes(secret))), 'ninguna respuesta lleva una contraseña de prueba');
}
check(!jsErrors.length, 'sin errores de JavaScript en la pantalla', jsErrors.join(' | '));
await browser.close();
log(`${tag}: ${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - started) / 1000)} s`);
process.exit(failures ? 1 : 0);
