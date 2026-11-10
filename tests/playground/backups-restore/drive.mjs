// Ensayo de la restauración robusta contra el Playground que levantó backups-restore.sh, sin WP-CLI: el respaldo se
// crea desde la pantalla (AJAX), se toma el inventario, se cambia el sitio (base de datos y archivos, también un .php
// inyectado en uploads y un plugin nuevo) y se restaura por REST; la restauración sólo avanza con ticks web (AJAX con el
// testigo de la restauración, como la pantalla, y wp-cron.php sin testigo ni `doing_wp_cron`, como el cron del sistema),
// con dos peticiones muertas a propósito (a mitad de la base de datos y a mitad de los archivos). En la red muere además
// la propia petición de REST, ya en mantenimiento: sin trabajo ni testigo en la respuesta, la restauración sólo sigue con
// wp-cron.php (la puerta del .maintenance). Al final el inventario debe ser idéntico al de después del respaldo y lo
// inyectado no debe sobrevivir. En el sitio individual, una segunda restauración falla
// a mitad del cambio y debe volver a la copia previa (inventario idéntico al de antes de restaurar). Comprueba que
// ninguna petición de la restauración pasó de 30 s ni de 64 MB. Escribe <dir>/result.txt y <dir>/summary.json.
// Uso: node drive.mjs <puerto> <host> <dir> <single|multisite>
import http from 'node:http';
import { appendFileSync, existsSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';

const [port, host, dir, mode] = process.argv.slice(2);
const network = mode === 'multisite';
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 900)}` : ''}`); }
  return ok;
};
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const jsonl = (path) => (existsSync(path) ? readFileSync(path, 'utf8').split('\n').filter(Boolean).map((line) => JSON.parse(line)) : []);
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const store = `${dir}/store`;
const ctl = `${dir}/ctl`;

function request(method, path, { headers = {}, body, timeout = 100_000 } = {}) {
  return new Promise((resolve) => {
    const started = Date.now();
    const all = { Host: host, ...headers };
    if (body !== undefined) all['Content-Length'] = Buffer.byteLength(body);
    // Como Cloudflare: una petición que pasa de 100 s se corta del lado del cliente.
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* una petición muerta a propósito o el mantenimiento */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json, ms: Date.now() - started });
      });
    });
    req.on('error', (error) => resolve({ status: 0, text: String(error.message), json: null, ms: Date.now() - started }));
    req.on('timeout', () => req.destroy(new Error('corte del cliente a los 100 s')));
    if (body !== undefined) req.write(body);
    req.end();
  });
}
const cookies = new Map();
const keep = (res) => { for (const value of [].concat(res.headers?.['set-cookie'] || [])) { const [pair] = value.split(';'); const [name, ...rest] = pair.split('='); cookies.set(name.trim(), rest.join('=')); } return res; };
const cookie = () => [...cookies].map(([name, value]) => `${name}=${value}`).join('; ');
const probe = async (what, extra = '') => (await request('GET', `/?digi_lab=${what}&token=${token}${extra}`)).json;
const jobFile = (id) => { try { return JSON.parse(readFileSync(`${store}/jobs/${id}/job.json`, 'utf8')); } catch { return null; } };
const terminal = (job) => job && ['success', 'failed', 'cancelled'].includes(job.status);

// Inventario: la misma base de datos y los mismos archivos, sin lo volátil (ver lab.php).
// robots-ensayo.txt se crea después del respaldo fuera de las raíces gestionadas: debe quedarse, así que no cuenta.
function compare(a, b, label, ignore = ['robots-ensayo.txt']) {
  const tables = new Set([...Object.keys(a.tables), ...Object.keys(b.tables)]);
  const diffTables = [...tables].filter((t) => JSON.stringify(a.tables[t]) !== JSON.stringify(b.tables[t]));
  const files = new Set([...Object.keys(a.files), ...Object.keys(b.files)].filter((f) => !ignore.includes(f)));
  const diffFiles = [...files].filter((f) => a.files[f] !== b.files[f]);
  check(!diffTables.length && !diffFiles.length, `${label}: ${Object.keys(b.tables).length} tablas y ${Object.keys(b.files).length} archivos idénticos`, JSON.stringify({ tablas: diffTables.slice(0, 8), archivos: diffFiles.slice(0, 12) }));
  return { tables: diffTables, files: diffFiles };
}

const start = Date.now();
keep(await request('POST', '/wp-login.php', {
  headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
  body: `log=admin&pwd=password&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
}));
check([...cookies.keys()].some((name) => name.startsWith('wordpress_logged_in_')), 'inicio de sesión de admin');
const screen = network ? '/wp-admin/network/admin.php?page=digitalisimo-network-backups' : '/wp-admin/admin.php?page=digitalisimo-backups';
const page = await request('GET', screen, { headers: { Cookie: cookie() } });
const config = JSON.parse((page.text.match(/window\.digitalisimoBackupsManual=(\{.*?\});/) || [null, '{}'])[1]);
check(page.status === 200 && config.nonce && config.jobsNonce, 'la pantalla Backups entrega sus nonces', `${page.status}`);
const ajax = (action, nonce, fields = {}, query = '') => request('POST', `/wp-admin/admin-ajax.php${query}`, {
  headers: { Cookie: cookie(), 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({ action, nonce, context: network ? 'network' : 'site', ...fields }).toString(),
});

// 1. Respaldo desde la pantalla (AJAX y ticks de la cola).
const started = await ajax('digitalisimo_backups_manual_start', config.nonce, { destination: 'local' });
const backupId = started.json?.data?.id;
check(started.status === 200 && backupId, 'respaldo encolado desde la pantalla', started.text);
for (let i = 0; i < 3000 && backupId && !terminal(jobFile(backupId)); i++) {
  const res = i % 4 === 3 ? await request('GET', '/wp-cron.php') : await ajax('digitalisimo_backups_job_tick', config.jobsNonce);
  await sleep(res.json?.data?.result === 'worked' ? 200 : 1500);
}
const backup = jobFile(backupId);
check(backup?.status === 'success', 'respaldo completo creado', JSON.stringify(backup?.error));
const reference = await probe('inventory');
writeFileSync(`${dir}/inventory-reference.json`, JSON.stringify(reference, null, 1));

/** Restaura por REST y la hace avanzar sólo con ticks web; devuelve el trabajo de la restauración (y su vuelta, si la hubo). */
async function restoreByWeb(file, label) {
  const nonce = (await request('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', { headers: { Cookie: cookie() } })).text.trim();
  const posted = Date.now();
  const res = keep(await request('POST', `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1/backups/${file}/restore`)}`, {
    headers: { Cookie: cookie(), 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    body: JSON.stringify({ confirm: 'RESTAURAR', network }),
  }));
  // La petición de REST muere a propósito (die-rest, en la red) o le toca una de las otras muertes si llega lejos.
  const killed = res.json?.job?.id ? [] : jsonl(`${ctl}/deaths.jsonl`).filter((d) => d.at * 1000 >= posted).map((d) => d.flag);
  const died = killed.length > 0;
  let id = res.json?.job?.id;
  let maintenance = res.json?.maintenance_token || '';
  if (died) {
    // Como un SIGKILL o un corte del proxy: al cliente no le llega nada de esa respuesta (ni la cookie del testigo).
    cookies.delete('digitalisimo_backups_restore');
    maintenance = '';
    const restores = readdirSync(`${store}/jobs`).map(jobFile).filter((job) => job?.kind === 'restore' && !Object.keys(job.spec?.rollback || {}).length && job.created_at * 1000 >= posted - 2000);
    id = restores.sort((a, b) => b.created_at - a.created_at)[0]?.id;
    check(Boolean(id), `${label}: la petición de REST murió (${killed.join(', ')}), sin trabajo ni testigo para el cliente; la restauración sigue sólo con wp-cron.php`, `${res.status} ${res.text.slice(0, 200)}`);
  } else {
    const restoring = res.status === 202 && res.json?.restoring;
    check(restoring || res.status === 200, `${label}: POST restore responde 202 y la restauración sigue por pasos`, `${res.status} ${res.text.slice(0, 300)}`);
  }
  let followed = id;
  const ticks = [];
  let maintenanceSeen = false;
  for (let i = 0; i < 6000 && id; i++) {
    const job = jobFile(followed);
    if (terminal(job)) {
      const next = existsSync(`${store}/jobs/${followed}/rollback.json`) ? JSON.parse(readFileSync(`${store}/jobs/${followed}/rollback.json`, 'utf8')).job : '';
      if (next && next !== followed) { followed = next; continue; }
      break;
    }
    const visitor = i % 10 === 5 ? await request('GET', '/') : null;
    if (visitor && visitor.status === 503) maintenanceSeen = true;
    // Sin testigo (la petición de REST murió) sólo queda el cron del sistema: wp-cron.php, como la línea que da la pantalla.
    const kind = !maintenance || i % 3 === 2 ? 'wp-cron' : 'pantalla';
    const r = kind === 'wp-cron'
      ? await request('GET', '/wp-cron.php')
      : await ajax('digitalisimo_backups_restore_tick', '', { digitalisimo_backups_restore: maintenance }, `?digitalisimo_backups_restore=${maintenance}`);
    ticks.push({ kind, status: r.status, ms: r.ms, result: r.json?.data?.result ?? null, phase: job?.phase });
    const view = (r.json?.data?.jobs || []).find((item) => item.id === followed);
    await sleep(r.json?.data?.result === 'worked' ? 200 : Math.max(500, Math.min(5000, (view?.retry_in || 1) * 1000)));
  }
  return { id, followed, job: jobFile(id), last: jobFile(followed), ticks, maintenanceSeen, died };
}

// 2. Cambios después del respaldo y restauración con dos muertes (base de datos y archivos).
const mutated = await probe('mutate', '&round=1');
check(mutated?.round === 1, 'el sitio cambió después del respaldo (base de datos, inyectado.php, plugin nuevo, tema)');
for (const flag of network ? ['die-rest', 'die-db', 'die-files'] : ['die-db', 'die-files']) writeFileSync(`${ctl}/${flag}`, '');
const first = await restoreByWeb(backup.spec.file, 'restauración');
check(first.job?.status === 'success', `restauración terminada sólo con ticks web (${first.ticks.length} peticiones)`, JSON.stringify(first.job?.error || first.job?.status));
const deaths = jsonl(`${ctl}/deaths.jsonl`).map((d) => d.flag);
const events = jsonl(`${store}/jobs/${first.id}/events.jsonl`);
const expected = network ? ['die-rest', 'die-db', 'die-files'] : ['die-db', 'die-files'];
check(expected.every((flag) => deaths.includes(flag)) && events.filter((e) => e.type === 'interrupted').length >= expected.length, `las peticiones muertas (${deaths.join(', ')}) se reconocieron y la restauración siguió desde su punto de control`, JSON.stringify(events.filter((e) => !['phase', 'claimed'].includes(e.type)).slice(0, 12)));
// WP-Cron por la puerta del mantenimiento: wp-cron.php, sin testigo, hizo avanzar la restauración.
const cronWorked = jsonl(`${ctl}/requests.jsonl`).filter((r) => r.kind === 'wp-cron' && r.from && r.from !== r.to).length;
check(cronWorked >= (first.died ? 2 : 1), `wp-cron.php sin testigo hizo avanzar la restauración (${cronWorked} peticiones${first.died ? '; la única vía tras morir la de REST' : ''})`);
const slices = (phase) => events.filter((e) => e.type === 'phase' && e.phase === phase && e.next === phase).length;
check(slices('database') >= 2 && slices('files') >= 2 && slices('inspect') >= 1, `base de datos, archivos y verificación por tramos (${slices('inspect')}, ${slices('database')}, ${slices('files')})`);
const restored = await probe('inventory');
writeFileSync(`${dir}/inventory-restored.json`, JSON.stringify(restored, null, 1));
const diff = compare(reference, restored, 'tras la restauración, igual que tras el respaldo');
check(!Object.keys(restored.files).some((f) => /inyectado-1\.php$|plugins\/nuevo-1\//.test(f)) && Object.keys(restored.files).includes('robots-ensayo.txt'), 'lo inyectado en uploads y el plugin nuevo no sobrevivieron; lo de fuera de las raíces gestionadas, sí');
const result = first.job?.result?.restore || {};
check((result.removed?.count || 0) >= 2 && (result.removed?.sample || []).some((p) => /inyectado-1\.php$/.test(p)), `el informe nombra lo retirado (${result.removed?.count} archivos)`, JSON.stringify(result.removed));
check(first.maintenanceSeen, 'durante la restauración, un visitante sin el testigo recibió el mantenimiento (503)');
check((await request('GET', '/')).status === 200, 'al terminar, el sitio salió del mantenimiento');

let second = null;
if (!network) {
  // 3. Plan B: la restauración falla a mitad del cambio (al mover los archivos) y vuelve a la copia previa.
  await probe('mutate', '&round=2');
  const before = await probe('inventory');
  writeFileSync(`${ctl}/fail-move`, '');
  second = await restoreByWeb(backup.spec.file, 'restauración que falla a mitad del cambio');
  rmSync(`${ctl}/fail-move`, { force: true });
  check(second.job?.status === 'failed' && second.followed !== second.id && second.last?.status === 'success', 'la restauración fallida volvió sola a la copia previa', JSON.stringify({ job: second.job?.status, error: second.job?.error, rollback: second.last?.status, rerror: second.last?.error }));
  const back = await probe('inventory');
  writeFileSync(`${dir}/inventory-rollback.json`, JSON.stringify(back, null, 1));
  compare(before, back, 'tras volver a la copia previa, igual que antes de restaurar');
}

// 4. Límites: ninguna petición de la restauración pasó de 30 s ni de 64 MB.
const requests = jsonl(`${ctl}/requests.jsonl`).filter((r) => r.restore_phase || r.kind === 'rest-restore' || r.kind === 'digitalisimo_backups_restore_tick');
const safety = requests.filter((r) => r.restore_phase === 'safety');
const own = requests.filter((r) => r.restore_phase !== 'safety');
const slowest = Math.max(0, ...own.map((r) => r.work ?? r.seconds));
const heaviest = Math.max(0, ...requests.map((r) => r.peak_mb));
check(requests.length > 5 && requests.every((r) => r.memory_limit === '64M' && r.request_limit === 30), 'cada petición de la restauración corrió con memory_limit=64M y límite de 30 s', JSON.stringify(requests.slice(0, 2)));
check(slowest < 30, `ninguna petición de las fases de la restauración pasó de 30 s (la más larga: ${slowest} s)`);
check(heaviest < 64, `ninguna petición de la restauración pasó de 64 MB (pico: ${heaviest} MB)`);
const safetySlowest = Math.max(0, ...safety.map((r) => r.work ?? r.seconds));
log(`· copia previa (fases del respaldo, de T2): ${safety.length} peticiones, la más larga ${safetySlowest} s`);
check(!requests.some((r) => /Allowed memory|Maximum execution time/.test(r.error || '')), 'sin memoria ni tiempo agotados (salvo las muertes inyectadas)');
const summary = { mode, seconds: Math.round((Date.now() - start) / 1000), first: { ticks: first.ticks.length, deaths, slices: { inspect: slices('inspect'), database: slices('database'), files: slices('files'), switch: slices('switch') }, removed: result.removed?.count, notice: result.notice, diff }, second: second ? { job: second.job?.status, rollback: second.last?.status, ticks: second.ticks.length } : null, requests: requests.length, slowest, safetySlowest, heaviest };
writeFileSync(`${dir}/summary.json`, JSON.stringify(summary, null, 2));
log(failures ? `TERMINADO con ${failures} fallos` : 'TERMINADO');
process.exit(failures ? 1 : 0);
