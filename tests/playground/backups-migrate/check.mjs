// Ensayo HTTP de la migración en un clic de DIGITALÍSIMO Backups entre dos Playground que levantó backups-migrate.sh:
// A (origen) y B (destino) en dos puertos. Hace lo que haría un administrador con el navegador, por los handlers reales
// con su sesión y su nonce: en A, Backups → Migrar → «Generar clave de migración»; en B, pega la clave en «Migrar desde
// otro sitio» y deja la pestaña abierta (el tick AJAX de la tarjeta «Trabajos» hace avanzar la migración); después
// comprueba en B el contenido, el inicio de sesión con las cuentas del origen, los medios y el CSS de Elements, que A no
// cambió y que su clave quedó usada, y «Deshacer la migración». Escribe cada resultado en <dir>/result.txt
// («ok · …» o «FALLA · …») y termina con TERMINADO.
// Uso: node check.mjs <modo single|multisite> <dir>
import http from 'node:http';
import { appendFileSync, readFileSync } from 'node:fs';

const [mode, dir] = process.argv.slice(2);
const conf = JSON.parse(readFileSync(`${dir}/conf.json`, 'utf8'));
const network = mode === 'multisite';
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 800)}` : ''}`); }
  return ok;
};
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
const sites = {
  A: { ...conf.A, jar: {}, setup: JSON.parse(readFileSync(`${dir}/A/probe/setup.json`, 'utf8')) },
  B: { ...conf.B, jar: {}, setup: JSON.parse(readFileSync(`${dir}/B/probe/setup.json`, 'utf8')) },
};
const secrets = [];

function request(site, method, path, { headers = {}, body, raw = false, timeout = 3_600_000 } = {}) {
  return new Promise((resolve, reject) => {
    const s = sites[site];
    const cookie = Object.entries(s.jar).map(([k, v]) => `${k}=${v}`).join('; ');
    const all = { Host: s.host, ...(cookie ? { Cookie: cookie } : {}), ...headers };
    if (body !== undefined) all['Content-Length'] = Buffer.byteLength(body);
    const req = http.request({ host: '127.0.0.1', port: s.port, method, path, headers: all, timeout }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        for (const line of [].concat(res.headers['set-cookie'] || [])) {
          const [pair] = line.split(';');
          const at = pair.indexOf('=');
          const name = pair.slice(0, at).trim();
          const value = pair.slice(at + 1).trim();
          if (/expires=Thu, 01[- ]Jan[- ]1970|Max-Age=0/i.test(line) || value === 'deleted' || value === '+') delete s.jar[name];
          else s.jar[name] = value;
        }
        const buffer = Buffer.concat(chunks);
        const text = raw ? '' : buffer.toString('utf8');
        let json = null;
        if (!raw) { try { json = JSON.parse(text); } catch { /* no JSON */ } }
        resolve({ status: res.statusCode, headers: res.headers, text, json, buffer });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (body !== undefined) req.write(body);
    req.end();
  });
}
// GET que sigue las redirecciones dentro del mismo sitio (la canónica de WordPress lleva a los enlaces permanentes);
// una redirección a otro host (p. ej. al origen) se devuelve tal cual para que la comprobación falle con su destino.
async function get(site, path, options = {}) {
  let res = await request(site, 'GET', path, options);
  for (let i = 0; i < 5 && [301, 302, 307, 308].includes(res.status); i++) {
    const target = new URL(String(res.headers.location || ''), sites[site].home + '/');
    if (target.host !== new URL(sites[site].home).host) { res.offsite = target.href; return res; }
    res = await request(site, 'GET', target.pathname + target.search, options);
  }
  return res;
}
// Qué cambió entre dos huellas de la sonda: tablas, opciones y archivos de wp-content.
function changes(before, after) {
  const out = [];
  for (const key of ['tables', 'options', 'files']) {
    const a = before[key] || {};
    const b = after[key] || {};
    for (const name of new Set([...Object.keys(a), ...Object.keys(b)])) if (a[name] !== b[name]) out.push(`${key}:${name}${name in a ? (name in b ? '' : ' (borrado)') : ' (nuevo)'}`);
  }
  return out;
}
// Registros del origen que sí cambian (no son contenido): el uso de la clave, debug.log de PHP y el registro de
// Elementor. Se informan; un aviso de PHP del código de Backups es un fallo.
async function originLogs(when) {
  const logs = await probe('A', `logs&from=${originLogs.from}`);
  const lines = String(logs.debug || '').split('\n').filter((line) => line.trim());
  const ours = lines.filter((line) => /digitalisimo-backups/.test(line));
  check(!ours.length, `A: ningún aviso de PHP del código de Backups en debug.log (${when})`, ours.slice(0, 5).join(' | '));
  const sample = [...new Set(lines.map((line) => line.replace(/^\[[^\]]+\]\s*/, '').slice(0, 160)))].slice(0, 4);
  log(`· A (${when}): registros que cambiaron (no son contenido): uso de la clave ${logs.key_log.length} eventos (${[...new Set(logs.key_log.map((row) => row.event))].join(', ')}); debug.log +${lines.length} líneas${sample.length ? ': ' + sample.join(' | ') : ''}; elementor_log ${logs.elementor.length} entradas${logs.elementor.length ? ': ' + logs.elementor.slice(0, 3).map((row) => `${row.type} ${row.message.slice(0, 100)} (${row.file.replace(/^.*\/wp-content\//, '')}:${row.line}, ×${row.times})`).join(' | ') : ''}`);
}
originLogs.from = 0;
const form = (fields) => Object.entries(fields).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
const post = (site, path, fields) => request(site, 'POST', path, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form(fields) });
const probe = async (site, what, body) => {
  const res = await request(site, body ? 'POST' : 'GET', `/wp-content/digi-web/probe.php?token=${conf.token}&what=${what}`, body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {});
  if (!res.json) throw new Error(`la sonda de ${site} (${what}) respondió ${res.status}: ${res.text.slice(0, 300)}`);
  return res.json;
};
async function login(site, password) {
  sites[site].jar = { wordpress_test_cookie: 'WP%20Cookie%20check' };
  const res = await post(site, '/wp-login.php', { log: 'admin', pwd: password, 'wp-submit': 'Log In', testcookie: '1', redirect_to: '/wp-admin/' });
  return res.status === 302 && Object.keys(sites[site].jar).some((name) => name.startsWith('wordpress_logged_in_'));
}
const page = (site) => request(site, 'GET', network ? '/wp-admin/network/admin.php?page=digitalisimo-network-backups&tab=migrate' : '/wp-admin/admin.php?page=digitalisimo-backups&tab=migrate');
// Campos ocultos del formulario de una acción admin-post (su nonce incluido).
function formFields(html, action) {
  for (const match of html.matchAll(/<form\b[^>]*>([\s\S]*?)<\/form>/g)) {
    if (!match[1].includes(`name="action" value="${action}"`)) continue;
    const fields = {};
    for (const input of match[1].matchAll(/<input\b[^>]*>/g)) {
      const name = /name="([^"]+)"/.exec(input[0]);
      const value = /value="([^"]*)"/.exec(input[0]);
      if (name && /type="hidden"/.test(input[0])) fields[name[1]] = value ? value[1].replace(/&amp;/g, '&') : '';
    }
    return fields;
  }
  return null;
}
const location = (res) => {
  const target = String(res.headers.location || '');
  const query = new URLSearchParams(target.split('?')[1] || '');
  return { ok: query.get('backup') === 'ok', message: decodeURIComponent(query.get('message') || ''), url: target };
};
const hostOf = (site) => (network ? sites[site].host : `${sites[site].host}`);

try {
  log(`· ${mode}: A ${sites.A.setup.versions} en ${sites.A.home} · B en ${sites.B.home}`);

  // 1. A: «Generar clave de migración» por la pantalla, con la sesión y el nonce del administrador.
  check(await login('A', conf.A.password), 'A: inicio de sesión del administrador del origen');
  let res = await page('A');
  const create = formFields(res.text, 'digitalisimo_backups_migration_key');
  check(res.status === 200 && !!create && res.text.includes('Generar clave de migración'), 'A: la pestaña «Migrar» ofrece generar una clave', res.status);
  res = await post('A', '/wp-admin/admin-post.php', { ...create, hours: '6' });
  const key = (/>(DGMIG1\.[A-Za-z0-9_-]+)</.exec(res.text) || [])[1] || '';
  secrets.push(key);
  check(res.status === 200 && key && /no-store/.test(String(res.headers['cache-control'])) && res.text.includes('Se muestra una sola vez'), 'A: la clave se muestra una sola vez, sin caché', `${res.status} ${res.headers['cache-control']}`);
  try {
    const parts = JSON.parse(Buffer.from(key.slice(7).replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8'));
    secrets.push(parts.s);
  } catch { check(false, 'A: la clave tiene el formato DGMIG1'); }
  res = await page('A');
  check(!res.text.includes(key), 'A: la pestaña no vuelve a mostrar la clave');
  // Huella del origen después de su sesión y de la clave (lo que la migración no debe cambiar).
  const fingerprintA = await probe('A', 'fingerprint');
  originLogs.from = fingerprintA.logs?.debug_size || 0;

  // 2. B: «Migrar desde otro sitio» con la clave pegada y un clic; la pestaña hace avanzar la migración.
  check(await login('B', conf.B.password), 'B: inicio de sesión del administrador del destino');
  res = await page('B');
  const pull = formFields(res.text, 'digitalisimo_backups_migration_pull');
  const jobsNonce = (/"jobsNonce":"([0-9a-f]+)"/.exec(res.text) || [])[1];
  check(res.status === 200 && !!pull && !!jobsNonce, 'B: la pestaña «Migrar» ofrece pegar la clave', res.status);
  const begun = Date.now();
  res = await post('B', '/wp-admin/admin-post.php', { ...pull, migration_key: key });
  let target = location(res);
  check(res.status === 302 && target.ok && target.message.includes('Migración en cola desde') && !target.url.includes('DGMIG1'), 'B: un clic encola la migración', `${res.status} ${target.message}`);
  let job = null;
  let lost = false;
  for (let i = 0; i < 400; i++) {
    res = await post('B', '/wp-admin/admin-ajax.php', { action: 'digitalisimo_backups_job_tick', nonce: jobsNonce, context: network ? 'network' : 'site' });
    if (!res.json || !res.json.success) { lost = true; break; }
    job = (res.json.data.jobs || []).find((item) => item.kind === 'migration') || job;
    if (job && job.terminal) break;
    if (res.json.data.result !== 'worked') await sleep(Math.max(1000, Math.min(15000, (job?.retry_in || 2) * 1000)));
  }
  // La migración se aplica con la restauración por pasos (1.0.64): tras el cambio de tablas la sesión ya no vale y el
  // sitio está en mantenimiento. Como en el navegador, se sigue con el tick por testigo (la cookie que recibió esta
  // «pestaña» al encolarse la restauración) y, cuando la restauración termina, con la sesión de un administrador del
  // origen (los usuarios de B son ya los de A), que hace avanzar el cierre. (El ensayo corta el loopback de WP-Cron.)
  let byToken = 0;
  if (lost) {
    for (let i = 0; i < 600; i++) {
      res = await post('B', '/wp-admin/admin-ajax.php', { action: 'digitalisimo_backups_restore_tick', digitalisimo_backups_restore: '' });
      if (!res.json || !res.json.success) break;
      byToken++;
      job = (res.json.data.jobs || []).find((item) => item.kind === 'migration') || job;
      if (job && job.terminal) break;
      if (res.json.data.result !== 'worked') await sleep(1500);
    }
    if (!(job && job.terminal) && await login('B', conf.A.password)) {
      const again = await page('B');
      const nonce = (/"jobsNonce":"([0-9a-f]+)"/.exec(again.text) || [])[1];
      for (let i = 0; nonce && i < 400; i++) {
        res = await post('B', '/wp-admin/admin-ajax.php', { action: 'digitalisimo_backups_job_tick', nonce, context: network ? 'network' : 'site' });
        if (!res.json || !res.json.success) break;
        job = (res.json.data.jobs || []).find((item) => item.kind === 'migration') || job;
        if (job && job.terminal) break;
        if (res.json.data.result !== 'worked') await sleep(Math.max(1000, Math.min(15000, (job?.retry_in || 2) * 1000)));
      }
    }
  }
  let state = await probe('B', 'state');
  const migration = state.jobs.find((item) => item.kind === 'migration');
  log(`· B: migración ${migration?.status} en ${Math.round((Date.now() - begun) / 1000)} s (${lost ? `la sesión del navegador terminó al restaurar; ${byToken} tick(s) por testigo` : 'la sesión siguió'})`);
  check(migration && migration.status === 'success', 'B: la migración terminó', JSON.stringify(migration));
  check(lost || job?.status === 'success', 'B: la pestaña siguió la migración hasta el final o hasta que la sesión cambió');

  // 3. B es ahora el origen con su propia dirección.
  check(state.home.replace(/\/$/, '') === sites.B.home && state.blogname === 'Sitio de origen', 'B: conserva su dirección y tiene el contenido del origen', `${state.home} ${state.blogname}`);
  check(state.users.includes('editora'), 'B: tiene los usuarios del origen');
  check(await login('B', conf.A.password), 'B: inicio de sesión con la contraseña del administrador del origen');
  check(!(await login('B', conf.B.password)), 'B: la contraseña anterior del destino ya no sirve');
  await login('B', conf.A.password);
  res = await get('B', `/?p=${sites.A.setup.post}`);
  check(res.status === 200 && res.text.includes('Entrada del origen') && !res.text.includes(sites.A.home.replace(/^https?:\/\//, '')) && res.text.includes(`${sites.B.home}/wp-content/uploads/`), 'B: la entrada del origen apunta a los medios de B y no menciona a A', `${res.status} ${res.offsite || ''}`);
  const imagePath = sites.A.setup.image.url.replace(sites.A.home, '');
  res = await request('B', 'GET', imagePath, { raw: true });
  const crypto = await import('node:crypto');
  check(res.status === 200 && crypto.createHash('sha256').update(res.buffer).digest('hex') === sites.A.setup.image.sha256, 'B: el medio del origen está en B, byte a byte', `${res.status} ${imagePath}`);
  if (sites.A.setup.page) {
    res = await get('B', `/?page_id=${sites.A.setup.page}`);
    const css = (/href=['"]([^'"]*\/elementor\/css\/post-\d+\.css[^'"]*)['"]/.exec(res.text) || [])[1];
    check(res.status === 200 && res.text.includes('Página de Elements del origen') && res.text.includes(`${sites.B.home}/wp-content/uploads/`) && !res.text.includes(sites.A.home.replace(/^https?:\/\//, '')), 'B: la página de Elements carga con los medios de B', `${res.status} ${res.offsite || ''}`);
    if (check(!!css, 'B: la página de Elements enlaza su CSS generado')) {
      const cssRes = await get('B', css.replace(/^https?:\/\/[^/]+/, ''));
      check(cssRes.status === 200 && cssRes.text.includes('background-image') && cssRes.text.includes(sites.B.home.replace(/^https?:\/\//, '')) && !cssRes.text.includes(sites.A.home.replace(/^https?:\/\//, '')), 'B: el CSS de Elements se regeneró con la dirección de B', `${cssRes.status} ${cssRes.text.slice(0, 200)}`);
    }
  }
  if (network) {
    const sub = sites.A.setup.sub;
    check(state.sites.some((site) => site.path === '/uno/' && site.domain === sites.B.host), 'B: la red tiene el subsitio «uno» del origen en su dominio', JSON.stringify(state.sites));
    res = await get('B', `/uno/?p=${sub.post}`);
    check(res.status === 200 && res.text.includes('Entrada del subsitio uno') && res.text.includes(`${sites.B.home}/uno/`) && !res.text.includes(sites.A.host), 'B: el subsitio carga con su contenido en la dirección de B', res.status);
    const subImage = sub.image.url.replace(/^https?:\/\/[^/]+/, '');
    res = await request('B', 'GET', subImage, { raw: true });
    check(res.status === 200 && crypto.createHash('sha256').update(res.buffer).digest('hex') === sub.image.sha256, 'B: el medio del subsitio está en B', `${res.status} ${subImage}`);
  }
  const marked = state.history.find((row) => row.migration);
  check(marked && marked.locked && marked.policy === 'pre-restore' && marked.migration.state === 'migrated', 'B: la copia previa queda bloqueada y marcada para deshacer', JSON.stringify(marked));
  check((await probe('B', 'secrets', secrets)).found.length === 0, 'B: ni la base de datos ni el almacén guardan la clave');

  // 4. A no cambió y su clave quedó usada.
  const changedA = changes(fingerprintA, await probe('A', 'fingerprint'));
  check(!changedA.length, 'A: el contenido del origen no cambió (base de datos y archivos)', changedA.slice(0, 20).join(' · '));
  await originLogs('tras migrar');
  const stateA = await probe('A', 'state');
  check(stateA.keys[0]?.state === 'used', 'A: la clave quedó usada', JSON.stringify(stateA.keys[0]));
  check(['hello', 'package', 'ready', 'download', 'used'].every((event) => stateA.log.some((row) => row.event === event)), 'A: el registro anota cada uso', JSON.stringify(stateA.log.map((row) => row.event)));
  check(!stateA.history.length, 'A: el paquete no entró en el historial del origen');
  check((await probe('A', 'secrets', secrets)).found.length === 0, 'A: ni la base de datos ni el almacén guardan la clave');

  // 5. «Deshacer la migración» en B, con la sesión del administrador del origen (B es ahora el origen).
  res = await page('B');
  const undo = formFields(res.text, 'digitalisimo_backups_migration_undo');
  check(res.status === 200 && !!undo && res.text.includes('Deshacer la migración'), 'B: la pestaña ofrece «Deshacer la migración»', res.status);
  const undoStart = Date.now();
  res = await post('B', '/wp-admin/admin-post.php', undo || {});
  target = location(res);
  // Si la restauración de la copia previa sigue en segundo plano, se sigue con su testigo (la cookie de la redirección).
  if (res.status === 302 && target.ok && target.message.includes('Deshaciendo la migración')) {
    for (let i = 0; i < 600; i++) {
      res = await post('B', '/wp-admin/admin-ajax.php', { action: 'digitalisimo_backups_restore_tick', digitalisimo_backups_restore: '' });
      if (!res.json || !res.json.success || !(res.json.data.jobs || []).some((item) => !item.terminal)) break;
      if (res.json.data.result !== 'worked') await sleep(1500);
    }
  }
  log(`· B: deshacer en ${Math.round((Date.now() - undoStart) / 1000)} s`);
  check(target.ok && (target.message.includes('Migración deshecha') || target.message.includes('Deshaciendo la migración')), 'B: un clic deshace la migración', `${res.status} ${target.message}`);
  state = await probe('B', 'state');
  check(state.blogname === 'Sitio de destino' && !state.users.includes('editora') && state.home.replace(/\/$/, '') === sites.B.home, 'B: volvió a su contenido y sus usuarios', `${state.blogname}`);
  check(await login('B', conf.B.password), 'B: la contraseña del destino vuelve a servir');
  res = await get('B', `/?p=${sites.B.setup.post}`);
  check(res.status === 200 && res.text.includes('Entrada propia del destino'), 'B: su entrada propia vuelve', res.status);
  check(state.history.some((row) => row.migration && row.migration.state === 'undone') && !state.undo, 'B: la migración queda deshecha y no se puede deshacer otra vez');
  const changedA2 = changes(fingerprintA, await probe('A', 'fingerprint'));
  check(!changedA2.length, 'A: el contenido del origen sigue igual tras deshacer', changedA2.slice(0, 20).join(' · '));
  await originLogs('tras deshacer');
} catch (error) {
  check(false, 'el ensayo se interrumpió', error.stack || error.message);
}
log(failures ? `TERMINADO con ${failures} fallo(s)` : 'TERMINADO');
process.exit(failures ? 1 : 0);
