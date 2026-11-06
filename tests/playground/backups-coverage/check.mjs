// Comprobaciones HTTP del ensayo de cobertura de DIGITALÍSIMO Backups contra el Playground que levantó
// backups-coverage.sh: la API REST con la autenticación real de WordPress y WP-CLI real (cli.php). Escribe cada
// resultado en <dir>/result.txt («ok · …» o «FALLA · …») y termina con TERMINADO.
// Uso: node check.mjs <modo single|multisite> <puerto> <host> <dir>
import http from 'node:http';
import { appendFileSync, readFileSync } from 'node:fs';

const [mode, port, host, dir] = process.argv.slice(2);
const setup = JSON.parse(readFileSync(`${dir}/probe/setup.json`, 'utf8'));
const token = readFileSync(`${dir}/web/token.txt`, 'utf8').trim();
const network = mode === 'multisite';
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 600)}` : ''}`); }
  return ok;
};

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : (typeof body === 'string' ? body : JSON.stringify(body));
    const all = { Host: host, ...headers };
    if (data !== undefined) all['Content-Length'] = Buffer.byteLength(data);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 1_800_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* respuesta no JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const basic = (login) => ({ Authorization: `Basic ${Buffer.from(`${login}:${setup.passwords[login]}`).toString('base64')}` });
// ?rest_route= funciona con y sin enlaces permanentes.
const api = (method, route, { sub = '', headers = {}, body } = {}) => request(method, `/${sub}?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1${route}`)}`, {
  headers: body === undefined ? headers : { 'Content-Type': 'application/json', ...headers },
  body,
});
const wp = async (...args) => {
  const res = await request('POST', `/wp-content/digi-web/cli.php?token=${token}`, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `args=${encodeURIComponent(JSON.stringify(args))}`,
  });
  const out = res.json || { stdout: '', stderr: res.text, notices: [], fatal: `HTTP ${res.status}` };
  // Sin colores ANSI; un aviso de PHP que venga de Backups es un fallo (los de wp-cli.phar con PHP 8.5 no).
  const plain = (text) => String(text || '').replace(/\x1b\[[0-9;]*m/g, '');
  out.stdout = plain(out.stdout);
  out.stderr = plain(out.stderr);
  for (const notice of out.notices || []) if (/digitalisimo-backups/.test(notice)) check(false, `aviso de PHP de Backups en wp ${args.join(' ')}`, notice);
  return out;
};
const siteName = async () => (await request('GET', '/?rest_route=%2F')).json?.name;

try {
  if (setup.versions) log(`· ${mode}: ${setup.versions}`);
  check(setup.application_passwords === true, 'contraseñas de aplicación disponibles (WP_ENVIRONMENT_TYPE=local)');

  // Sesión de admin por wp-login.php y nonce wp_rest de admin-ajax, como la pantalla.
  const login = await request('POST', '/wp-login.php', {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
    body: `log=admin&pwd=password&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
  });
  const cookie = [].concat(login.headers['set-cookie'] || []).map((value) => value.split(';')[0]).join('; ');
  check(login.status === 302 && /wordpress_logged_in_/.test(cookie), 'inicio de sesión de admin con cookie', `${login.status}`);
  const nonce = (await request('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', { headers: { Cookie: cookie } })).text.trim();
  check(/^[0-9a-f]{10}$/.test(nonce), 'nonce wp_rest de la sesión', nonce);

  // Autenticación real de WordPress.
  let res = await api('GET', '/status');
  check(res.status === 401 && res.json?.code === 'rest_forbidden', 'sin sesión: 401', `${res.status} ${res.text}`);
  res = await api('GET', '/status', { headers: { Cookie: cookie } });
  check(res.status === 401, 'cookie sin nonce: WordPress la trata como anónima (401)', `${res.status} ${res.text}`);
  res = await api('GET', '/status', { headers: { Cookie: cookie, 'X-WP-Nonce': '0123456789' } });
  check(res.status === 403 && res.json?.code === 'rest_cookie_invalid_nonce', 'cookie con nonce falso: 403', `${res.status} ${res.text}`);
  res = await api('GET', '/status', { headers: { Cookie: cookie, 'X-WP-Nonce': nonce } });
  check(res.status === 200 && res.json?.ready === true && res.json?.scope === (network ? 'network' : 'installation') && res.json?.wp_config === 'abspath', 'cookie con nonce: 200 y la instalación lista', res.text);
  res = await api('GET', '/status', { headers: basic('editora') });
  check(res.status === 403, 'contraseña de aplicación de una editora: 403', `${res.status} ${res.text}`);
  if (network) {
    res = await api('GET', '/backups', { headers: basic('sitio') });
    check(res.status === 403, 'administrador del sitio principal que no administra la red: 403', `${res.status} ${res.text}`);
  }
  res = await api('GET', '/backups', { headers: basic('admin') });
  check(res.status === 200 && Array.isArray(res.json), 'contraseña de aplicación de admin: 200', `${res.status} ${res.text}`);

  // Respaldo, verificación y restauración reales por REST.
  const original = await siteName();
  if (network) {
    res = await api('POST', '/backups', { headers: basic('admin'), body: {} });
    check(res.status === 400 && res.json?.code === 'digitalisimo_backups_scope', 'red: crear sin network=true es 400', `${res.status} ${res.text}`);
  }
  let started = Date.now();
  res = await api('POST', '/backups', { headers: basic('admin'), body: network ? { network: true } : {} });
  const backup = res.json || {};
  check(res.status === 201 && /^digitalisimo-backup-.+\.zip$/.test(backup.file || '') && backup.restorable === true && backup.scope === (network ? 'network' : 'installation'), `POST /backups creó y verificó el respaldo (${Math.round((Date.now() - started) / 1000)} s)`, `${res.status} ${res.text}`);
  res = await api('GET', `/backups/${backup.file}/verify`, { headers: basic('admin') });
  check(res.status === 200 && res.json?.verified === true && res.json?.sha256 === backup.sha256 && res.json?.registered === true, 'GET verify: íntegro y registrado', res.text);
  check(!res.text.includes('/digi-store') && !res.text.includes('/wordpress/'), 'la verificación no expone rutas del servidor');
  res = await request('POST', '/?rest_route=%2Fwp%2Fv2%2Fsettings', { headers: { ...basic('admin'), 'Content-Type': 'application/json' }, body: { title: 'Cambiado después del respaldo' } });
  check(res.status === 200 && (await siteName()) === 'Cambiado después del respaldo', 'cambio posterior al respaldo', `${res.status}`);
  res = await api('POST', `/backups/${backup.file}/restore`, { headers: basic('admin'), body: network ? { network: true } : {} });
  check(res.status === 400 && res.json?.code === 'rest_missing_callback_param', 'restaurar sin confirm: 400 de WordPress', `${res.status} ${res.text}`);
  started = Date.now();
  res = await api('POST', `/backups/${backup.file}/restore`, { headers: basic('admin'), body: { confirm: 'RESTAURAR', ...(network ? { network: true } : {}) } });
  check(res.status === 200 && res.json?.restored === true && /^safety-before-restore-/.test(res.json?.safety_backup || ''), `POST restore con RESTAURAR (${Math.round((Date.now() - started) / 1000)} s)`, `${res.status} ${res.text}`);
  check((await siteName()) === original, 'la restauración devolvió el nombre del sitio', await siteName());

  if (network) {
    res = await api('GET', '/backups', { sub: 'uno/', headers: basic('admin') });
    check(res.status === 400 && res.json?.code === 'digitalisimo_backups_main_site', 'subsitio: 400 con el motivo', `${res.status} ${res.text}`);
    res = await api('GET', '/status', { sub: 'uno/', headers: basic('admin') });
    check(res.status === 200 && res.json?.main_site === false && res.json?.ready === false, 'subsitio: el estado explica dónde respaldar', res.text);
  }

  // WP-CLI real. La ayuda y la validación de argumentos salen del PHPDoc: sin subcomando, WP-CLI imprime la sinopsis
  // que entendió.
  let out = await wp('digitalisimo-backups');
  const usage = out.stdout + out.stderr;
  check(['status', 'list', 'verify <file>', 'automation <command>'].every((name) => usage.includes(`wp digitalisimo-backups ${name}`)) && !out.fatal, 'WP-CLI registra status, list, create, verify, restore y automation', usage + out.fatal);
  check(usage.includes('wp digitalisimo-backups restore <file> --confirm=<confirm> [--network]') && usage.includes('wp digitalisimo-backups create [--destination=<destination>] [--network] [--porcelain]'), 'la sinopsis de restore y create sale del PHPDoc', usage);
  out = await wp('digitalisimo-backups', 'automation');
  check(/wp digitalisimo-backups automation list/.test(out.stdout + out.stderr) && /wp digitalisimo-backups automation run <id> \[--network\]/.test(out.stdout + out.stderr), 'WP-CLI registra automation list y run', out.stdout + out.stderr);
  let parsed = null;
  out = await wp('digitalisimo-backups', 'status', '--format=json');
  try { parsed = JSON.parse(out.stdout.trim()); } catch { /* no JSON */ }
  check(parsed?.ready === true && !out.fatal, 'wp digitalisimo-backups status --format=json', out.stdout + out.stderr + out.fatal);
  out = await wp('digitalisimo-backups', 'list', '--format=json');
  try { parsed = JSON.parse(out.stdout.trim()); } catch { parsed = null; }
  check(Array.isArray(parsed) && parsed.some((row) => row.file === backup.file), 'wp digitalisimo-backups list --format=json', out.stdout + out.stderr);
  out = await wp('digitalisimo-backups', 'verify', backup.file);
  check(out.stdout.includes(`Success: Respaldo íntegro: ${backup.file}`), 'wp digitalisimo-backups verify', out.stdout + out.stderr);
  out = await wp('digitalisimo-backups', 'restore', backup.file);
  check(/missing --confirm parameter/i.test(out.stderr + out.stdout) && !/Restauración terminada/.test(out.stdout), 'wp digitalisimo-backups restore sin --confirm no corre', out.stdout + out.stderr);
  out = await wp('digitalisimo-backups', 'automation', 'list', '--format=json');
  check(out.stdout.includes('monthly-full'), 'wp digitalisimo-backups automation list', out.stdout + out.stderr);
  if (network) {
    out = await wp('digitalisimo-backups', 'create');
    check(/alcance de red/.test(out.stderr + out.stdout), 'red: wp digitalisimo-backups create sin --network falla', out.stdout + out.stderr);
    out = await wp(`--url=http://${host}/uno/`, 'digitalisimo-backups', 'create', '--network');
    check(/sitio principal/.test(out.stderr + out.stdout), 'red: create desde el subsitio falla', out.stdout + out.stderr);
  }
  started = Date.now();
  out = await wp('digitalisimo-backups', 'create', '--porcelain', ...(network ? ['--network'] : []));
  const created = out.stdout.trim();
  check(/^digitalisimo-backup-.+\.zip$/.test(created) && !out.fatal, `wp digitalisimo-backups create --porcelain (${Math.round((Date.now() - started) / 1000)} s)`, out.stdout + out.stderr + out.fatal);
  res = await api('GET', '/backups', { headers: basic('admin') });
  check(res.json?.[0]?.file === created && res.json?.[0]?.policy === 'manual', 'el respaldo de WP-CLI aparece primero en el historial de REST', res.text);
  if (network) {
    // Un subsitio con upload_path propio fuera de ABSPATH (Red → Sitios → Editar → Ajustes): sus medios quedarían fuera
    // del ZIP, así que el estado y la creación lo rechazan con el sitio y el ajuste. Después vuelve su valor.
    const sub = `--url=http://${host}/uno/`;
    const previous = (await wp(sub, 'option', 'get', 'upload_path')).stdout.trim();
    out = await wp(sub, 'eval', "wp_mkdir_p( '/digi-probe/medios-uno/sites/' . get_current_blog_id() ); update_option( 'upload_path', '/digi-probe/medios-uno' ); echo wp_upload_dir( null, false )['basedir'];");
    const basedir = out.stdout.trim();
    out = await wp('digitalisimo-backups', 'status', '--format=json');
    try { parsed = JSON.parse(out.stdout.trim()); } catch { parsed = null; }
    const problem = (parsed?.problems || []).find((text) => /^La carpeta de medios del sitio \d+ \(la opción upload_path de ese sitio\) está en /.test(text)) || '';
    check(parsed?.ready === false && /^\/digi-probe\/medios-uno\/sites\/\d+$/.test(basedir) && problem.includes(`está en ${basedir}, fuera`), 'red: los medios de un subsitio fuera de ABSPATH se rechazan donde WordPress los guarda', `${basedir} · ${out.stdout}${out.stderr}`);
    res = await api('POST', '/backups', { headers: basic('admin'), body: { network: true } });
    check(res.status === 422 && res.json?.code === 'digitalisimo_backups_not_ready' && res.json?.message === problem, 'red: POST /backups con esos medios responde 422 sin exportar', `${res.status} ${res.text}`);
    await wp(sub, 'option', 'update', 'upload_path', previous);
    out = await wp('digitalisimo-backups', 'status', '--format=json');
    try { parsed = JSON.parse(out.stdout.trim()); } catch { parsed = null; }
    check(parsed?.ready === true, 'red: con el upload_path original la red vuelve a estar lista', out.stdout + out.stderr);
  }
} catch (error) {
  failures++;
  log(`FALLA · excepción · ${error.stack || error}`);
}
log(failures ? `TERMINADO con ${failures} fallo(s)` : 'TERMINADO');
process.exit(failures ? 1 : 0);
