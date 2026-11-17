// Comprobaciones HTTP del ensayo de red de «los administradores de este sitio siguen entrando»
// (backups-keep-site-admins-red.sh), por la REST y wp-login.php como lo haría un cliente:
//   origin  la red A (prefijo wp_) crea un respaldo de la red por REST (POST /backups y POST /jobs/tick).
//   dest    la red B (prefijo dst_): verify dice quiénes conservarán su acceso; POST restore (network, addresses=current,
//           RESTAURAR SIN FIRMA) con la contraseña de aplicación de «admin», seguido con su ficha hasta el final. Después:
//           «admin» y «red-soporte» siguen siendo superadministradores y están en `site_admins` de `sitemeta` (junto a
//           «dueno», el del respaldo), «sitio-admin» administra el sitio principal sin ser superadministrador, sus
//           permisos tienen el prefijo final (dst_capabilities, dst_user_level; ninguno con wp_), «otro-admin» (sólo de
//           otro sitio) y «colaborador» (editor) ya no están, cada uno entra con su contraseña por wp-login.php, la cookie
//           de «red-soporte» de antes sigue valiendo y el contenido es el de la red A.
// Escribe cada resultado en <out>/result.txt («ok · …» o «FALLA · …»).
// Uso: node check-red.mjs origin <puerto> <host> <dir> <out> <etiqueta>
//      node check-red.mjs dest <puerto> <host> <dir> <out> <etiqueta> <dir-origen>
import http from 'node:http';
import { appendFileSync, readFileSync, writeFileSync } from 'node:fs';

const [phase, port, host, dir, out, label = 'nuevo', originDir = ''] = process.argv.slice(2);
const secrets = JSON.parse(readFileSync(`${dir}/probe/secrets.json`, 'utf8'));
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const tag = `red-${phase}-${label}`;
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
        seen.push(text);
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', (error) => resolve({ status: 0, headers: {}, text: String(error.message), json: null }));
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const basic = (user, password) => ({ Authorization: `Basic ${Buffer.from(`${user}:${password}`).toString('base64')}` });
const api = (method, route, { headers = {}, body } = {}) => request(method, `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1${route}`)}`, { headers: body === undefined ? headers : { 'Content-Type': 'application/json', ...headers }, body });
const probe = async () => (await request('GET', `/?digi_kadm=state&token=${token}`)).json || {};
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));
const cookieOf = (res) => [].concat(res.headers['set-cookie'] || []).map((value) => value.split(';')[0]).join('; ');
async function login(user, password) {
  const res = await request('POST', '/wp-login.php', {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
    body: `log=${encodeURIComponent(user)}&pwd=${encodeURIComponent(password)}&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
  });
  const cookie = cookieOf(res);
  return res.status === 302 && /wordpress_logged_in_/.test(cookie) ? cookie : '';
}
const allowed = (res) => res.status === 200 && /wpadminbar/.test(res.text) && !/not allowed to access|no tienes permisos/i.test(res.text);

const started = Date.now();
if (phase === 'origin') {
  const auth = basic('dueno', secrets.dueno_app);
  let res = await api('POST', '/backups', { headers: auth, body: { network: true } });
  const id = res.json?.id || res.json?.job?.id;
  check(res.status === 202 && id, 'POST /backups encola el respaldo de la red (202)', `${res.status} ${res.text.slice(0, 300)}`);
  for (let i = 0; i < 300 && id; i++) {
    const job = await api('GET', `/jobs/${id}`, { headers: auth });
    if (job.status !== 200 || job.json?.terminal) { res = job; break; }
    const tick = await api('POST', '/jobs/tick', { headers: auth, body: {} });
    if (tick.json?.result !== 'worked') await sleep(3000);
  }
  check(res.json?.status === 'success', 'el respaldo de la red A termina verificado', res.text.slice(0, 300));
  const list = await api('GET', '/backups', { headers: auth });
  const file = list.json?.[0]?.file;
  check(/^digitalisimo-.*\.zip$/.test(file || ''), 'el respaldo está en el historial de la red A', list.text.slice(0, 300));
  const state = await probe();
  check(JSON.stringify(state.site_admins) === '["dueno"]' && state.prefix === 'wp_', 'la red A tiene a «dueno» como superadministrador (prefijo wp_)', JSON.stringify({ admins: state.site_admins, prefix: state.prefix }));
  writeFileSync(`${dir}/origin.json`, JSON.stringify({ file }));
} else {
  const origin = JSON.parse(readFileSync(`${originDir}/probe/secrets.json`, 'utf8'));
  const { file } = JSON.parse(readFileSync(`${originDir}/origin.json`, 'utf8'));
  const forbidden = [...Object.values(secrets), ...Object.values(origin)].filter((value) => typeof value === 'string' && value.length > 8);
  const auth = basic('admin', secrets.admin_app);
  let state = await probe();
  check(['admin', 'red-soporte'].every((login) => (state.site_admins || []).includes(login)) && !(state.site_admins || []).includes('sitio-admin') && state.prefix === 'dst_', 'antes: la red B tiene a «admin» y «red-soporte» como superadministradores (prefijo dst_)', JSON.stringify({ admins: state.site_admins, prefix: state.prefix }));
  const soporteCookie = await login('red-soporte', secrets['red-soporte']);
  check(Boolean(soporteCookie), 'antes: «red-soporte» entra en la red B');

  // La confirmación por REST: verify dice quiénes conservarán su acceso.
  let res = await api('GET', `/backups/${file}/verify`, { headers: auth });
  const preview = res.json?.access || {};
  check(res.status === 200 && res.json?.compatible === true && preview.applies === true && preview.you === 'admin' && JSON.stringify(preview.admins) === '["red-soporte","sitio-admin"]', 'verify dice que conservarán su acceso «admin» (quien restaura), «red-soporte» y «sitio-admin» (no «otro-admin» ni «colaborador»)', JSON.stringify(preview).slice(0, 400) || res.text.slice(0, 300));

  // Restaurar la red por REST con la contraseña de aplicación de «admin», siguiendo el trabajo con su ficha.
  const begun = Date.now();
  res = await api('POST', `/backups/${file}/restore`, { headers: auth, body: { confirm: 'RESTAURAR SIN FIRMA', network: true, addresses: 'current' } });
  check([200, 202].includes(res.status) && (res.json?.restored || res.json?.job?.id), `POST restore de la red (${res.status})`, res.text.slice(0, 400));
  const follow = res.json?.follow || null;
  let final = res.json?.restored ? { status: 'success', restore: { result: res.json } } : null;
  for (let i = 0; i < 900 && !final && follow; i++) {
    const r = await api('GET', `/jobs/${follow.job}`, { headers: { [follow.header]: follow.token } });
    if (r.status === 200 && r.json?.terminal) { final = r.json; break; }
    await request('GET', '/wp-cron.php');
    await sleep(1000);
  }
  const result = final?.restore?.result || {};
  const access = result.access || {};
  check(final?.status === 'success', `la restauración de la red terminó (${Math.round((Date.now() - begun) / 1000)} s)`, JSON.stringify(final || {}).slice(0, 400));
  check(JSON.stringify(access.kept_accounts) === '["admin","red-soporte","sitio-admin"]' && access.admins_kept === true && access.kept === true, 'el informe dice quiénes conservaron su acceso', JSON.stringify(access).slice(0, 500));
  check(/administradores de este sitio: «red-soporte», «sitio-admin»/.test(String(result.notice || '')), 'el aviso final los nombra', String(result.notice || '').slice(0, 400));
  log(`· aviso: ${String(result.notice || '').slice(0, 500)}`);

  // Superadministradores en sitemeta, permisos con el prefijo final y quién ya no está.
  state = await probe();
  const users = state.users || {};
  const admins = [...(state.site_admins || [])].sort();
  check(JSON.stringify(admins) === '["admin","dueno","red-soporte"]', '`site_admins` de `sitemeta`: «admin» y «red-soporte» (de esta red) y «dueno» (del respaldo); no «sitio-admin»', JSON.stringify(state.site_admins));
  check(users.admin?.super && users['red-soporte']?.super && users.dueno?.super && users['sitio-admin'] && !users['sitio-admin'].super, 'is_super_admin(): «admin», «red-soporte» y «dueno» sí; «sitio-admin» no', JSON.stringify(Object.fromEntries(Object.entries(users).map(([l, u]) => [l, u.super]))));
  check((users['sitio-admin']?.roles || []).includes('administrator') && (users.admin?.roles || []).includes('administrator') && (users['red-soporte']?.roles || []).includes('administrator'), 'los conservados son administradores del sitio principal', JSON.stringify(Object.fromEntries(Object.entries(users).map(([l, u]) => [l, u.roles]))));
  const kept = ['admin', 'red-soporte', 'sitio-admin'];
  const caps = Object.fromEntries(kept.map((login) => [login, users[login]?.caps || []]));
  check(kept.every((login) => JSON.stringify(caps[login]) === '["dst_capabilities","dst_user_level"]'), 'lo conservado tiene el prefijo final: dst_capabilities y dst_user_level, ninguna con el prefijo del respaldo', JSON.stringify(caps));
  check(!Object.values(users).some((u) => (u.caps || []).some((key) => /^wp_/.test(key))), 'ningún usuario de la red restaurada tiene permisos con el prefijo del respaldo (wp_)', JSON.stringify(Object.fromEntries(Object.entries(users).map(([l, u]) => [l, u.caps]))));
  check(!users['otro-admin'] && !users.colaborador, '«otro-admin» (sólo de otro sitio) y «colaborador» (editor) ya no están', Object.keys(users).join(', '));
  check(state.blogname === 'Red A' && (state.posts || []).includes('Entrada de la red A') && !(state.posts || []).includes('Entrada de la red B') && state.home === `http://${host}`, 'el contenido es el de la red A en la dirección de la red B', JSON.stringify({ blog: state.blogname, home: state.home, posts: state.posts }));

  // Cada uno entra con su contraseña.
  for (const [user, password, network] of [['admin', secrets.admin, true], ['red-soporte', secrets['red-soporte'], true], ['sitio-admin', secrets['sitio-admin'], false], ['dueno', origin.dueno, true]]) {
    const cookie = await login(user, password);
    if (!check(Boolean(cookie), `«${user}» entra con su contraseña por wp-login.php`)) continue;
    const site = await request('GET', '/wp-admin/options-general.php', { headers: { Cookie: cookie } });
    const net = await request('GET', '/wp-admin/network/', { headers: { Cookie: cookie } });
    check(allowed(site) && allowed(net) === network, `«${user}» administra el sitio principal${network ? ' y la red' : ' y no la red'}`, `${site.status} ${net.status}`);
  }
  for (const [user, password] of [['otro-admin', secrets['otro-admin']], ['colaborador', secrets.colaborador]]) check(!(await login(user, password)), `«${user}» ya no entra`);
  const dash = await request('GET', '/wp-admin/network/', { headers: { Cookie: soporteCookie } });
  check(allowed(dash), 'la cookie del navegador de «red-soporte» de antes de restaurar sigue valiendo (escritorio de la red)', dash.status);
  const front = await request('GET', '/');
  check(front.status === 200 && /Red A/.test(front.text) && !/Fatal error|critical error/i.test(front.text), 'la portada responde con la red A, sin errores', front.status);
  check(!seen.some((text) => forbidden.some((secret) => text.includes(secret))), 'ninguna respuesta lleva una contraseña de prueba');
}
log(`${tag}: ${failures ? `${failures} fallo(s)` : 'todo correcto'} en ${Math.round((Date.now() - started) / 1000)} s`);
process.exit(failures ? 1 : 0);
