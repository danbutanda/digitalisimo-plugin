// Ensayo T2 de DIGITALÍSIMO Backups contra el Playground que levantó backups-steps.sh: el respaldo se encola desde la
// pantalla (AJAX manual_start, con la sesión y el nonce de admin) y sólo avanza con los ticks del navegador (AJAX
// digitalisimo_backups_job_tick, con las esperas de assets/manual-progress.js) y con llamadas a wp-cron.php (como un
// cron del sistema por HTTP; el loopback está cortado). Mata tres peticiones a mitad de un tramo (base de datos,
// empaquetado y verificación) y comprueba que el trabajo sigue desde su punto de control. Al terminar comprueba con
// lectores ajenos al plugin (unzip -t y zipfile de Python) el ZIP del almacén montado. Escribe <dir>/result.txt
// («ok · …» o «FALLA · …», y TERMINADO) y <dir>/summary.json.
// Uso: node drive.mjs <puerto> <host> <dir>
import http from 'node:http';
import { spawnSync } from 'node:child_process';
import { appendFileSync, existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';

const [port, host, dir] = process.argv.slice(2);
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 600)}` : ''}`); }
  return ok;
};
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const jsonl = (path) => (existsSync(path) ? readFileSync(path, 'utf8').split('\n').filter(Boolean).map((line) => JSON.parse(line)) : []);

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
        try { json = JSON.parse(text); } catch { /* respuesta no JSON (una petición muerta a propósito) */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json, ms: Date.now() - started });
      });
    });
    req.on('error', (error) => resolve({ status: 0, text: String(error.message), json: null, ms: Date.now() - started }));
    req.on('timeout', () => req.destroy(new Error('corte del cliente a los 100 s')));
    if (body !== undefined) req.write(body);
    req.end();
  });
}

const start = Date.now();
const login = await request('POST', '/wp-login.php', {
  headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
  body: `log=admin&pwd=password&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
});
const cookie = [].concat(login.headers?.['set-cookie'] || []).map((value) => value.split(';')[0]).join('; ');
check(login.status === 302 && /wordpress_logged_in_/.test(cookie), 'inicio de sesión de admin', `${login.status}`);
const page = await request('GET', '/wp-admin/admin.php?page=digitalisimo-backups', { headers: { Cookie: cookie } });
const config = JSON.parse((page.text.match(/window\.digitalisimoBackupsManual=(\{.*?\});/) || [null, '{}'])[1]);
check(page.status === 200 && config.nonce && config.jobsNonce, 'la pantalla Backups entrega sus nonces', `${page.status}`);
const ajax = (action, nonce, fields = {}) => request('POST', '/wp-admin/admin-ajax.php', {
  headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({ action, nonce, context: 'site', ...fields }).toString(),
});

// Las tres muertes se dejan preparadas: el mu-plugin mata la petición dentro de su fase cuando ya guardó avance.
for (const flag of ['die-db', 'die-pack', 'die-verify']) writeFileSync(`${dir}/ctl/${flag}`, '');
const started = await ajax('digitalisimo_backups_manual_start', config.nonce, { destination: 'local' });
const id = started.json?.data?.id;
check(started.status === 200 && id, 'respaldo encolado desde la pantalla (AJAX)', started.text);
const store = `${dir}/store`;
const jobFile = () => JSON.parse(readFileSync(`${store}/jobs/${id}/job.json`, 'utf8'));
const ticks = [];
let job = null;
for (let i = 0; i < 3000 && id; i++) {
  job = jobFile();
  if (['success', 'failed', 'cancelled'].includes(job.status)) break;
  const kind = i % 3 === 2 ? 'wp-cron' : 'navegador';
  const res = kind === 'wp-cron'
    ? await request('GET', `/wp-cron.php?doing_wp_cron=${(Date.now() / 1000).toFixed(4)}`)
    : await ajax('digitalisimo_backups_job_tick', config.jobsNonce);
  ticks.push({ kind, status: res.status, ms: res.ms, result: res.json?.data?.result ?? null, phase: job.phase });
  // Las esperas de manual-progress.js: 300 ms tras avanzar; si espera un reintento, hasta 15 s.
  const view = (res.json?.data?.jobs || []).find((item) => item.id === id);
  await sleep(res.json?.data?.result === 'worked' ? 300 : Math.max(1000, Math.min(15000, (view?.retry_in || 1) * 1000)));
}
job = jobFile();
const seconds = Math.round((Date.now() - start) / 1000);
check(job.status === 'success', `respaldo terminado sólo con ticks del navegador y de WP-Cron en ${seconds} s y ${ticks.length} peticiones`, `${job.status} ${JSON.stringify(job.error)}`);

// Límites: ninguna petición que hizo avanzar la cola pasó de 30 s ni de 64 MB.
const requests = jsonl(`${dir}/ctl/requests.jsonl`);
const worked = requests.filter((r) => r.kind !== 'start');
const slowest = Math.max(...worked.map((r) => r.seconds));
const heaviest = Math.max(...worked.map((r) => r.peak_mb));
check(worked.length > 5 && worked.every((r) => r.memory_limit === '64M' && r.request_limit === 30), 'cada petición corrió con memory_limit=64M y límite de 30 s', JSON.stringify(worked.slice(0, 2)));
check(slowest < 30, `ninguna petición pasó de 30 s (la más larga: ${slowest} s)`);
check(heaviest < 64, `ninguna petición pasó de 64 MB (pico: ${heaviest} MB)`);
check(!worked.some((r) => /Allowed memory|Maximum execution time/.test(r.error)), 'sin memoria ni tiempo agotados');

// Tramos y muertes, por el registro del trabajo.
const events = jsonl(`${store}/jobs/${id}/events.jsonl`);
const slices = (phase) => events.filter((e) => e.type === 'phase' && e.phase === phase).length;
const summary = { seconds, requests: worked.length, ticks: ticks.length, slowest, heaviest, slices: { database: slices('database'), pack: slices('pack'), verify: slices('verify') }, deaths: jsonl(`${dir}/ctl/deaths.jsonl`), checkpoint: job.checkpoint?.database ? { mode: job.checkpoint.database.mode, segments: job.checkpoint.database.segments, rows: job.checkpoint.database.rows } : null, warnings: job.warnings, sapi: worked[0]?.sapi };
check(summary.slices.pack > 2 && summary.slices.verify > 1, `el empaquetado y la verificación avanzaron por tramos (${summary.slices.pack} y ${summary.slices.verify})`);
const died = summary.deaths.map((d) => d.flag);
const deaths = { db: died.includes('die-db'), pack: died.includes('die-pack'), verify: died.includes('die-verify') };
check(deaths.pack && deaths.verify && events.filter((e) => ['interrupted', 'timeout', 'fatal'].includes(e.type)).length === died.length, `las ${died.length} peticiones muertas a mitad de un tramo retomado (${died.join(', ')}) se reconocieron y el trabajo siguió`, JSON.stringify(summary.deaths));
// La base de datos: en varios tramos (cada uno coherente, declarado) o, si cupo en uno, la instantánea única de siempre.
check(summary.checkpoint && (summary.checkpoint.segments > 1 ? summary.checkpoint.mode === 'transaction-steps' && deaths.db : summary.checkpoint.mode === 'transaction'), `base de datos en ${summary.checkpoint?.segments} tramo(s), modo ${summary.checkpoint?.mode}, ${summary.checkpoint?.rows} filas`);
check(Object.keys(job.attempts || {}).length === 0 || Object.values(job.attempts).every((n) => n <= 1), 'ninguna fase gastó más de un intento', JSON.stringify(job.attempts));

// El ZIP final, con lectores ajenos al plugin.
const file = `${store}/${job.spec.file}`;
const unzip = spawnSync('unzip', ['-tqq', file], { encoding: 'utf8' });
check(unzip.status === 0, 'unzip -t acepta el ZIP', `${unzip.stdout}${unzip.stderr}`.slice(0, 400));
const py = spawnSync('python3', ['-I', `${dir}/zipcheck.py`, file], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
let zipinfo = null;
try { zipinfo = JSON.parse(py.stdout); } catch { /* sin JSON */ }
check(py.status === 0 && zipinfo?.ok, `zipfile de Python lee todas las entradas y sus SHA-256 coinciden con el manifiesto (${zipinfo?.entries} entradas, ${zipinfo?.megabytes} MB)`, `${py.stdout}${py.stderr}`.slice(0, 600));
summary.zip = zipinfo;
check(zipinfo?.megabytes >= 150, `el sitio respaldado tiene unos 200 MB (${zipinfo?.megabytes} MB sin comprimir)`);
check(zipinfo?.manifest?.packer === 'digitalisimo-zip/1' && zipinfo?.manifest?.format === 'digitalisimo-backup/v3', 'manifiesto v3 del empaquetador por tramos', JSON.stringify(zipinfo?.manifest));
writeFileSync(`${dir}/summary.json`, JSON.stringify({ ...summary, tickLog: ticks }, null, 2));
log(failures ? `TERMINADO con ${failures} fallos` : 'TERMINADO');
process.exit(failures ? 1 : 0);
