// Comprobaciones HTTP del ensayo de Backuply (backups-backuply.sh). Fase «origin»: Backuply 1.6.0 hace un respaldo
// completo con su propio código, tramo a tramo; Backups lo detecta (GET /backuply), lo importa (POST /backuply/import y
// POST /jobs/tick) y verify lo da por «sin firma». Fase «dest»: el ZIP importado, como archivo suelto, se restaura con
// RESTAURAR SIN FIRMA y la reubicación de E1; en el dominio nuevo, las entradas cargan sin el dominio de origen, la base
// de datos no lo menciona (salvo guid) y se inicia sesión con la cuenta del respaldo.
// Escribe cada resultado en <dir>/result.txt («ok · …» o «FALLA · …») y termina con TERMINADO.
// Uso: node check.mjs origin <puerto> <host> <dir> | node check.mjs dest <puerto> <host> <dir> <dir-origen> <host-origen>
import http from 'node:http';
import { appendFileSync, readFileSync, writeFileSync } from 'node:fs';

const [phase, port, host, dir, originDir, originHost] = process.argv.slice(2);
const setup = JSON.parse(readFileSync(`${dir}/probe/setup.json`, 'utf8'));
const token = () => readFileSync(`${dir}/web/token.txt`, 'utf8').trim();
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
    const all = { Host: host, ...headers };
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
const api = (method, route, { headers = {}, body } = {}) => request(method, `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1${route}`)}`, {
  headers: body === undefined ? headers : { 'Content-Type': 'application/json', ...headers },
  body,
});
const pathOf = (url) => { const u = new URL(url, `http://${host}`); return u.pathname + u.search; };
const wait = (ms) => new Promise((done) => setTimeout(done, ms));
/** Hace avanzar la cola de Backups por REST hasta que el trabajo termina; devuelve su vista. */
const drive = async (id, password) => {
  let res = null;
  for (let i = 0; i < 600 && id; i++) {
    res = await api('GET', `/jobs/${id}`, { headers: basic(password) });
    if (res.status !== 200 || res.json?.terminal) return res;
    const tick = await api('POST', '/jobs/tick', { headers: basic(password), body: {} });
    if (tick.json?.result !== 'worked') await wait(2000);
  }
  return res;
};

try {
  if (setup.versions) log(`· ${phase}: ${setup.versions}`);
  if (phase === 'origin') {
    // 1) Backuply: el primer tramo lo empieza (como el botón «Create Backup»); los siguientes, como sus llamadas a sí mismo.
    const started = Date.now();
    let state = null;
    await request('GET', `/wp-content/digi-web/backuply-run.php?token=${token()}&start=1`);
    for (let i = 0; i < 300; i++) {
      state = (await request('GET', `/wp-content/digi-web/backuply-state.php?token=${token()}`)).json;
      if (!state?.running) break;
      await request('GET', `/wp-content/digi-web/backuply-run.php?token=${token()}`);
    }
    const tar = state?.files?.[0];
    check(tar && /\.tar\.gz$/.test(tar.name) && !state.running && !/Backup failed|errors? :/i.test(state.log || ''), `origen: Backuply ${setup.backuply} termina su respaldo completo (${Math.round((Date.now() - started) / 1000)} s)`, JSON.stringify(state).slice(0, 900));
    log(`· Backuply: ${tar?.name} (${tar?.size} bytes)`);
    // 2) Backups detecta Backuply y su respaldo, y lo importa.
    let res = await api('GET', '/backuply', { headers: basic(setup.password) });
    const item = (res.json?.backups || []).find((b) => b.name === tar?.name);
    check(res.status === 200 && res.json?.detected === true && res.json?.active === true && item && item.source === 'backuply' && item.database === true && item.files === true, 'origen: GET /backuply detecta Backuply y su respaldo completo', res.text.slice(0, 600));
    check(!res.text.includes('/wordpress/wp-content'), 'origen: GET /backuply no revela rutas del servidor');
    res = await api('POST', '/backuply/import', { headers: basic(setup.password), body: { name: tar?.name, source: 'backuply' } });
    check(res.status === 202 && res.json?.kind === 'import', 'origen: POST /backuply/import encola la importación (202)', `${res.status} ${res.text.slice(0, 400)}`);
    const importStart = Date.now();
    res = await drive(res.json?.id, setup.password);
    check(res?.json?.status === 'success' && /^digitalisimo-backuply-.*\.zip$/.test(res.json.file || ''), `origen: la importación termina (${Math.round((Date.now() - importStart) / 1000)} s)`, res?.text?.slice(0, 900));
    const file = res?.json?.file;
    log(`· ${res?.json?.detail || ''}`);
    res = await api('GET', `/backups/${file}/verify`, { headers: basic(setup.password) });
    check(res.status === 200 && res.json?.verified === true && res.json?.trust === 'untrusted' && res.json?.confirmation === 'RESTAURAR SIN FIRMA' && res.json?.compatible === true && res.json?.multisite === setup.network, 'origen: verify comprueba el ZIP importado entero y pide RESTAURAR SIN FIRMA', res.text.slice(0, 900));
    writeFileSync(`${dir}/origin.json`, JSON.stringify({ file, tar: tar?.name }));
  } else {
    const origin = JSON.parse(readFileSync(`${originDir}/probe/setup.json`, 'utf8'));
    const { file } = JSON.parse(readFileSync(`${originDir}/origin.json`, 'utf8'));
    const network = setup.network;
    let res = await api('GET', `/backups/${file}/verify`, { headers: basic(setup.password) });
    check(res.status === 200 && res.json?.compatible === true && res.json?.relocation?.needed === true && res.json.relocation.text.includes(`→ este sitio http://${host} (prefijo ${setup.prefix})`), 'destino: verify dice «Origen → este sitio» para el respaldo de Backuply', res.text.slice(0, 900));
    log(`· ${res.json?.relocation?.text || ''}`);
    res = await api('POST', `/backups/${file}/restore`, { headers: basic(setup.password), body: { confirm: 'RESTAURAR', network } });
    check(res.status >= 400, 'destino: RESTAURAR (sin «SIN FIRMA») no restaura un respaldo de Backuply', `${res.status}`);
    const started = Date.now();
    res = await api('POST', `/backups/${file}/restore`, { headers: basic(setup.password), body: { confirm: 'RESTAURAR SIN FIRMA', network, addresses: 'current' } });
    check(res.status === 200 && res.json?.restored === true, `destino: POST restore restaura el respaldo de Backuply por pasos (${Math.round((Date.now() - started) / 1000)} s)`, res.text.slice(0, 900));
    const relocation = res.json?.relocation || {};
    check(relocation.check && relocation.check.urls === 0 && relocation.check.paths === 0, 'destino: la comprobación final cuenta 0 URL y 0 rutas del origen', JSON.stringify(relocation.check));
    log(`· aviso: ${res.json?.notice || ''}`);
    res = await api('GET', '/status', { headers: basic(origin.password) });
    check(res.status === 200, 'destino: REST responde con la cuenta del respaldo', `${res.status} ${res.text.slice(0, 300)}`);
    for (const [name, info] of Object.entries(origin.pages)) {
      const link = info.link.replace(`//${originHost}`, `//${host}`);
      const view = await request('GET', pathOf(link));
      check(view.status === 200 && view.text.includes('Hola desde Backuply') && view.text.includes('ñandú €'), `destino: la entrada «${name}» de Backuply carga en ${host}`, `${view.status} ${pathOf(link)} ${view.text.slice(0, 300)}`);
      check(!view.text.includes(originHost), `destino: la entrada «${name}» no menciona ${originHost}`, (view.text.match(new RegExp(`.{0,80}${originHost.replace('.', '\\.')}.{0,80}`)) || [''])[0]);
      const image = await request('GET', info.image.replace(/^\/[_0-9a-zA-Z-]+\/(wp-content\/)/, '/$1'));
      check(image.status === 200 && image.text.length > 50, `destino: la imagen de «${name}» se restauró`, `${image.status} ${info.image}`);
    }
    const dbres = await request('GET', `/wp-content/digi-web/db.php?token=${token()}&needle=${encodeURIComponent(originHost)}`);
    const db = dbres.json || {};
    check(db.prefix === setup.prefix && db.user_roles === true && Object.keys(db.hits || {}).length === 0, `destino: ninguna tabla del sitio menciona ${originHost} (salvo guid: ${db.guid})`, JSON.stringify(db.hits || db).slice(0, 900));
    if (network) check(Array.isArray(db.blogs) && db.blogs.length === 2 && db.blogs.every((b) => b.domain === host), 'destino: los sitios de la red tienen el dominio nuevo', JSON.stringify(db.blogs));
    const login = await request('POST', '/wp-login.php', {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
      body: `log=admin&pwd=password&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
    });
    const cookie = [].concat(login.headers['set-cookie'] || []).map((value) => value.split(';')[0]).join('; ');
    check(login.status === 302 && /wordpress_logged_in_/.test(cookie), 'destino: inicio de sesión con la cuenta del respaldo', `${login.status}`);
    const admin = await request('GET', network ? '/wp-admin/network/' : '/wp-admin/', { headers: { Cookie: cookie } });
    check(admin.status === 200 && admin.text.includes('wpadminbar') && !/not allowed to access|no tienes permisos/i.test(admin.text), `destino: el escritorio${network ? ' de la red' : ''} abre con sus permisos`, `${admin.status}`);
  }
} catch (error) {
  check(false, 'el ensayo terminó con un error', error.stack || error.message);
}
log(failures ? `TERMINADO con ${failures} fallos` : 'TERMINADO');
process.exit(failures ? 1 : 0);
