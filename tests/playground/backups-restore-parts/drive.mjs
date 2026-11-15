// Ensayo «restaurar por partes» contra el Playground que levantó backups-restore-parts.sh, sin WP-CLI ni Chrome: el
// respaldo completo se crea desde la pantalla (AJAX), y cada restauración por partes se pide por REST (`parts`,
// `plugins`, `sites`) y avanza sólo con ticks web (AJAX con el testigo y wp-cron.php), como en backups-restore.sh. Con
// huellas de cada tabla y de cada archivo (lab.php), antes y después de cada restauración: lo elegido vuelve al respaldo
// y lo no elegido queda idéntico.
//   single     sólo la base de datos; sólo el plugin «demo»; sólo los medios (los subidos después se conservan, el .php
//              inyectado se retira); sólo los temas; los plugins con una falla a mitad del cambio que vuelve a la copia
//              previa (todo idéntico a antes); y deja el respaldo para el ensayo de otra dirección.
//   multisite  sólo los medios del subsitio (los del principal, intactos) y sólo la base de datos de la red.
//   relocate   en otro sitio (otra dirección), sólo la base de datos del respaldo del primero: vuelven sus datos con las
//              direcciones de este sitio y ningún archivo de este sitio cambia; quien restaura sigue entrando.
// Uso: node drive.mjs <puerto> <host> <dir> <single|multisite|relocate> [respaldo de otro sitio]
import http from 'node:http';
import { appendFileSync, existsSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';

const [port, host, dir, mode, foreign] = process.argv.slice(2);
const network = mode === 'multisite';
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 1200)}` : ''}`); }
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
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* el mantenimiento o una página */ }
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
const parts = async (what, extra = '') => (await request('GET', `/?digi_parts=${what}&token=${token}${extra}`)).json;
const jobFile = (id) => { try { return JSON.parse(readFileSync(`${store}/jobs/${id}/job.json`, 'utf8')); } catch { return null; } };
const terminal = (job) => job && ['success', 'failed', 'cancelled'].includes(job.status);

async function login() {
  keep(await request('POST', '/wp-login.php', {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
    body: `log=admin&pwd=password&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
  }));
  return [...cookies.keys()].some((name) => name.startsWith('wordpress_logged_in_'));
}
const start = Date.now();
if (process.env.LAB_MEMORY) writeFileSync(`${ctl}/backup-memory`, process.env.LAB_MEMORY);
check(await login(), 'inicio de sesión de admin');
const screen = network ? '/wp-admin/network/admin.php?page=digitalisimo-network-backups' : '/wp-admin/admin.php?page=digitalisimo-backups';
const page = await request('GET', screen, { headers: { Cookie: cookie() } });
const config = JSON.parse((page.text.match(/window\.digitalisimoBackupsManual=(\{.*?\});/) || [null, '{}'])[1]);
check(page.status === 200 && config.nonce && config.jobsNonce, 'la pantalla Backups entrega sus nonces', `${page.status}`);
const ajax = (action, nonce, fields = {}, query = '') => request('POST', `/wp-admin/admin-ajax.php${query}`, {
  headers: { Cookie: cookie(), 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({ action, nonce, context: network ? 'network' : 'site', ...fields }).toString(),
});

/** Restaura por REST con la selección y la hace avanzar con ticks web; devuelve el trabajo (y su vuelta, si la hubo). */
async function restoreByWeb(file, label, selection, confirm = 'RESTAURAR') {
  const nonce = (await request('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', { headers: { Cookie: cookie() } })).text.trim();
  const res = keep(await request('POST', `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1/backups/${file}/restore`)}`, {
    headers: { Cookie: cookie(), 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    body: JSON.stringify({ confirm, network, ...selection }),
  }));
  check(res.status === 202 || res.status === 200 || res.status === 500, `${label}: POST restore con ${JSON.stringify(selection)} aceptado (${res.status})`, res.text.slice(0, 400));
  check(res.json?.parts && res.json.parts.all === false, `${label}: la respuesta dice qué se restaura (${res.json?.parts?.label})`, JSON.stringify(res.json?.parts));
  const id = res.json?.job?.id || [...readdirSync(`${store}/jobs`)].map(jobFile).filter((job) => job?.kind === 'restore' && !Object.keys(job.spec?.rollback || {}).length).sort((a, b) => b.created_at - a.created_at)[0]?.id;
  const maintenance = res.json?.maintenance_token || '';
  let followed = id;
  let ticks = 0;
  for (let i = 0; i < 6000 && id; i++) {
    const job = jobFile(followed);
    if (terminal(job)) {
      const next = existsSync(`${store}/jobs/${followed}/rollback.json`) ? JSON.parse(readFileSync(`${store}/jobs/${followed}/rollback.json`, 'utf8')).job : '';
      if (next && next !== followed) { followed = next; continue; }
      break;
    }
    const kind = !maintenance || i % 3 === 2 ? 'wp-cron' : 'pantalla';
    const r = kind === 'wp-cron'
      ? await request('GET', '/wp-cron.php')
      : await ajax('digitalisimo_backups_restore_tick', '', { digitalisimo_backups_restore: maintenance }, `?digitalisimo_backups_restore=${maintenance}`);
    ticks++;
    await sleep(r.json?.data?.result === 'worked' ? 200 : 1500);
  }
  return { id, followed, job: jobFile(id), last: jobFile(followed), ticks, response: res.json };
}
/**
 * Huellas después de restaurar: cada tabla y cada archivo, como en `before` salvo lo elegido, que vuelve a `reference`.
 * $chosen(path) dice si un archivo es de lo elegido; $kept(path) si, siendo elegido y sin estar en el respaldo, se
 * conserva (los medios subidos después sin la base de datos); $database, si las tablas vuelven al respaldo.
 */
function expectAfter(label, reference, before, after, chosen, database, kept = () => false, options = null) {
  const tables = new Set([...Object.keys(before.tables), ...Object.keys(after.tables), ...(database ? Object.keys(reference.tables) : [])]);
  const wantTables = database ? reference.tables : before.tables;
  let badTables = [...tables].filter((t) => JSON.stringify(wantTables[t]) !== JSON.stringify(after.tables[t]));
  // wp_options distinta sólo por lo que WordPress escribe solo: se explica opción a opción y no cuenta.
  if (options && badTables.length === 1 && /options$/.test(badTables[0])) {
    const real = optionDiff(database ? options.reference : options.before, options.after);
    log(`· ${label}: ${badTables[0]} distinta sólo en ${real.length ? real.join(', ') : 'opciones que WordPress escribe solo (cachés, modo de recuperación)'}`);
    if (!real.length) badTables = [];
  }
  const files = new Set([...Object.keys(before.files), ...Object.keys(after.files), ...Object.keys(reference.files).filter(chosen)]);
  const bad = [];
  for (const f of files) {
    let want = before.files[f];
    if (chosen(f)) want = f in reference.files ? reference.files[f] : (kept(f) ? before.files[f] : undefined);
    if (want !== after.files[f]) bad.push(f);
  }
  const restored = [...files].filter((f) => chosen(f) && before.files[f] !== after.files[f]).length;
  check(!badTables.length && !bad.length, `${label}: ${database ? 'tablas como en el respaldo' : `${Object.keys(after.tables).length} tablas idénticas`}, ${restored} archivos elegidos cambiados y los demás ${Object.keys(after.files).length - restored} idénticos`, JSON.stringify({ tablas: badTables.slice(0, 8), archivos: bad.slice(0, 12) }));
}
// Opciones que WordPress escribe solo (cachés, el modo de recuperación tras un fatal de un tick que agotó la memoria):
// no son de la restauración. El resto de lo volátil lo filtra ya el inventario del laboratorio (lab.php).
const VOLATILE = /^(_site_transient_|_transient_|cron$|digitalisimo_backups_|rewrite_rules$|recently_activated$|doing_cron$|session_tokens$|wp_[0-9]*_?user-settings|auto_core_update|_wp_suggested_policy|can_compress_scripts$|db_upgraded$|wp_user_roles_update$|finished_updating_comment_type$|user_count$|_wp_session_|admin_email_lifespan$|recovery_keys$|fresh_site$|wp_calendar_block_has_published_posts$)/;
const optionDiff = (a, b) => Object.keys({ ...(a || {}), ...(b || {}) }).filter((k) => !VOLATILE.test(k) && (a || {})[k] !== (b || {})[k]);
const isPhp = (f) => /\.(php[0-9]?|phtml|pht|phar|phps)$/i.test(f);
let round = 1;
let referenceOptions = null;
async function scenario(file, reference, label, selection, chosen, database, kept, extra = {}) {
  round++;
  await probe('mutate', `&round=${round}`);
  await parts('mutate', `&round=${round}`);
  const before = await probe('inventory');
  const optionsBefore = await parts('options');
  const optionsReference = referenceOptions;
  if (extra.failMove) writeFileSync(`${ctl}/fail-move`, '');
  const run = await restoreByWeb(file, label, selection, extra.confirm);
  rmSync(`${ctl}/fail-move`, { force: true });
  const after = await probe('inventory');
  const optionsAfter = await parts('options');
  const changedOptions = Object.keys({ ...optionsBefore, ...optionsAfter }).filter((k) => optionsBefore?.[k] !== optionsAfter?.[k]);
  writeFileSync(`${dir}/inventory-${label.replace(/[^a-z0-9]+/gi, '-')}.json`, JSON.stringify({ before, after, changedOptions }, null, 1));
  log(`· ${label}: opciones distintas antes y después: ${changedOptions.slice(0, 30).join(', ') || 'ninguna'}`);
  if (extra.failMove) {
    check(run.job?.status === 'failed' && run.followed !== run.id && run.last?.status === 'success', `${label}: la falla a mitad del cambio volvió sola a la copia previa`, JSON.stringify({ job: run.job?.status, error: run.job?.error, rollback: run.last?.status, rerror: run.last?.error }));
    check(JSON.stringify(run.last?.spec?.parts) === JSON.stringify(run.job?.spec?.parts), `${label}: la vuelta a la copia previa repone sólo las mismas partes`, JSON.stringify(run.last?.spec?.parts));
    expectAfter(`${label} (tras la vuelta)`, reference, before, after, () => false, false);
  } else {
    check(run.job?.status === 'success', `${label}: terminada sólo con ticks web (${run.ticks} peticiones)`, JSON.stringify(run.job?.error || run.job?.status));
    expectAfter(label, reference, before, after, chosen, database, kept, { before: optionsBefore, after: optionsAfter, reference: optionsReference });
    const report = run.job?.result?.restore?.parts || {};
    check(report.all === false && Array.isArray(report.not_restored) && report.not_restored.length > 0 && String(run.job?.result?.restore?.notice || '').includes('Restauración por partes'), `${label}: el informe dice qué se restauró (${(report.restored || []).join(', ')}) y qué no`, JSON.stringify(report));
  }
  check((await request('GET', '/')).status === 200, `${label}: al terminar, el sitio salió del mantenimiento`);
  return { run, before, after };
}

const summary = { mode, scenarios: [] };
if (mode === 'relocate') {
  // El respaldo de otro sitio (otra dirección) llega como archivo suelto: sin firma ni registro aquí.
  const file = foreign;
  const reference = JSON.parse(readFileSync(`${dir}/reference.json`, 'utf8'));
  const info = await parts('info');
  const before = await probe('inventory');
  const run = await restoreByWeb(file, 'otra dirección, sólo la base de datos', { parts: ['database'], addresses: 'current' }, 'RESTAURAR SIN FIRMA');
  check(run.job?.status === 'success', `otra dirección: terminada (${run.ticks} peticiones)`, JSON.stringify(run.job?.error || run.job?.status));
  const after = await probe('inventory');
  writeFileSync(`${dir}/inventory-relocate.json`, JSON.stringify({ before, after }, null, 1));
  const badFiles = [...new Set([...Object.keys(before.files), ...Object.keys(after.files)])].filter((f) => before.files[f] !== after.files[f]);
  check(!badFiles.length, `otra dirección: ningún archivo de este sitio cambió (${Object.keys(after.files).length})`, JSON.stringify(badFiles.slice(0, 12)));
  const now = await parts('info');
  check(now?.blogname === reference.blogname && now?.home === info?.home && now?.siteurl === info?.siteurl, `otra dirección: vuelven los datos del respaldo («${now?.blogname}») con las direcciones de este sitio (${now?.home})`, JSON.stringify({ antes: info, ahora: now, respaldo: reference.blogname }));
  const relocation = run.job?.result?.restore?.relocation;
  check(Boolean(relocation) && (relocation.check?.urls ?? 1) === 0, 'otra dirección: la reubicación de E1 se aplicó (0 URL del origen pendientes)', JSON.stringify(relocation?.check));
  const home = await request('GET', '/');
  check(home.status === 200 && home.text.includes(host) && !home.text.includes(reference.host), `otra dirección: la portada responde con ${host} y sin la dirección del origen`, `${home.status}`);
  cookies.clear();
  check(await login(), 'otra dirección: quien restauró sigue entrando');
  summary.scenarios.push({ label: 'relocate', ticks: run.ticks, notice: run.job?.result?.restore?.notice });
} else {
  // Respaldo completo desde la pantalla (AJAX y ticks de la cola). Con BACKUP_MEMORY (por defecto 128M), sólo sus
  // peticiones corren con ese límite: con 64 MB, el empaquetado va al límite también con el código de main (ver
  // docs/BACKUPS.md, «Restaurar por partes» → «Memoria»). Con BACKUP_MEMORY=64M, todo con los límites del propietario.
  // Con LAB_MEMORY, todas las peticiones (también las de las restauraciones) corren con ese límite.
  const backupMemory = process.env.LAB_MEMORY || process.env.BACKUP_MEMORY || '128M';
  if (backupMemory !== '64M') writeFileSync(`${ctl}/backup-memory`, backupMemory);
  const started = await ajax('digitalisimo_backups_manual_start', config.nonce, { destination: 'local' });
  const backupId = started.json?.data?.id;
  check(started.status === 200 && backupId, 'respaldo completo encolado desde la pantalla', started.text);
  for (let i = 0; i < 3000 && backupId && !terminal(jobFile(backupId)); i++) {
    const res = i % 4 === 3 ? await request('GET', '/wp-cron.php') : await ajax('digitalisimo_backups_job_tick', config.jobsNonce);
    await sleep(res.json?.data?.result === 'worked' ? 200 : 1500);
  }
  if (!process.env.LAB_MEMORY) rmSync(`${ctl}/backup-memory`, { force: true });
  const backup = jobFile(backupId);
  const peaks = jsonl(`${ctl}/requests.jsonl`).filter((r) => r.kind === 'digitalisimo_backups_job_tick' || r.kind === 'digitalisimo_backups_manual_start').map((r) => r.peak_mb);
  check(backup?.status === 'success', `respaldo completo creado (pico de memoria de sus peticiones: ${Math.max(0, ...peaks)} MB, límite ${backupMemory})`, JSON.stringify(backup?.error));
  // Diagnóstico: sólo el respaldo (por ejemplo, para comparar la memoria con el código de main).
  if (process.env.ONLY_BACKUP) { log(failures ? `TERMINADO con ${failures} fallos` : 'TERMINADO'); process.exit(failures ? 1 : 0); }
  const file = backup.spec.file;
  // La fila del respaldo ofrece «Qué restaurar» (plegado, con «Todo») y, abierta con ?restore=…&parts=1, sus plugins.
  const opened = await request('GET', `${screen}&restore=${encodeURIComponent(file)}&parts=1`, { headers: { Cookie: cookie() } });
  check(opened.status === 200 && opened.text.includes('Qué restaurar: <span data-restore-parts-label>todo</span>') && /name="restore_all" value="1" checked/.test(opened.text) && opened.text.includes('name="restore_plugins[]" value="demo"') && opened.text.includes('name="restore_plugins[]" value="otro"'), 'la fila del respaldo ofrece «Qué restaurar» con «Todo» y los plugins del respaldo', `${opened.status}`);
  const reference = await probe('inventory');
  referenceOptions = await parts('options');
  const info = await parts('info');
  writeFileSync(`${dir}/reference.json`, JSON.stringify({ ...reference, blogname: info?.blogname, host, file }, null, 1));
  // verify dice qué trae y qué se reemplazaría.
  const nonce = (await request('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', { headers: { Cookie: cookie() } })).text.trim();
  const verify = await request('GET', `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1/backups/${file}/verify`)}&plugins=demo`, { headers: { Cookie: cookie(), 'X-WP-Nonce': nonce } });
  check(verify.status === 200 && (verify.json?.parts?.available?.plugins || []).includes('demo') && (verify.json?.parts?.available?.plugins || []).includes('otro') && (verify.json?.parts?.coherence || []).some((r) => r.code === 'plugins_without_database'), 'verify lista los plugins del respaldo y avisa de la coherencia', verify.text.slice(0, 400));
  const uploads = 'wp-content/uploads/';
  if (network) {
    const sub = JSON.parse(readFileSync(`${dir}/probe/parts.json`, 'utf8')).sub;
    const subDir = `${uploads}sites/${sub}/`;
    summary.scenarios.push(await scenario(file, reference, 'red, sólo los medios del subsitio', { sites: [sub] }, (f) => f.startsWith(subDir), false, (f) => !isPhp(f)).then((r) => ({ label: 'sitio', ticks: r.run.ticks })));
    summary.scenarios.push(await scenario(file, reference, 'red, sólo la base de datos', { parts: ['database'] }, () => false, true).then((r) => ({ label: 'base', ticks: r.run.ticks })));
  } else {
    summary.scenarios.push(await scenario(file, reference, 'sólo la base de datos', { parts: ['database'] }, () => false, true).then((r) => ({ label: 'base', ticks: r.run.ticks })));
    summary.scenarios.push(await scenario(file, reference, 'sólo el plugin demo', { plugins: ['demo'] }, (f) => f.startsWith('wp-content/plugins/demo/'), false).then((r) => ({ label: 'plugin', ticks: r.run.ticks })));
    summary.scenarios.push(await scenario(file, reference, 'sólo los medios', { parts: ['uploads'] }, (f) => f.startsWith(uploads) && !f.startsWith(`${uploads}sites/`), false, (f) => !isPhp(f)).then((r) => ({ label: 'medios', ticks: r.run.ticks })));
    summary.scenarios.push(await scenario(file, reference, 'sólo los temas', { parts: ['themes'] }, (f) => f.startsWith('wp-content/themes/'), false).then((r) => ({ label: 'temas', ticks: r.run.ticks })));
    summary.scenarios.push(await scenario(file, reference, 'plugins con una falla a mitad del cambio', { parts: ['plugins'] }, (f) => f.startsWith('wp-content/plugins/'), false, () => false, { failMove: true }).then((r) => ({ label: 'falla', ticks: r.run.ticks })));
  }
}
// Las restauraciones por partes corrieron con los límites del propietario: 64 MB y 30 s.
const restoreRequests = jsonl(`${ctl}/requests.jsonl`).filter((r) => r.restore_phase || r.kind === 'rest-restore' || r.kind === 'digitalisimo_backups_restore_tick');
if (restoreRequests.length && process.env.LAB_MEMORY) log(`· las restauraciones corrieron con memory_limit=${process.env.LAB_MEMORY} (LAB_MEMORY)`);
if (restoreRequests.length && !process.env.LAB_MEMORY) {
  check(restoreRequests.every((r) => r.memory_limit === '64M'), `las ${restoreRequests.length} peticiones de las restauraciones corrieron con memory_limit=64M`, JSON.stringify(restoreRequests.filter((r) => r.memory_limit !== '64M').slice(0, 2)));
  const heaviest = Math.max(0, ...restoreRequests.map((r) => r.peak_mb));
  // Una petición que agota la memoria muere como un SIGKILL y el trabajo sigue desde su punto de control (lo que se ensaya
  // es que todo termine y que lo no elegido no cambie); se anota cuántas fueron, para el informe.
  const died = restoreRequests.filter((r) => /Allowed memory|Maximum execution time/.test(r.error || ''));
  log(`· peticiones de las restauraciones: ${restoreRequests.length}, pico ${heaviest} MB; murieron por memoria o tiempo y se reanudaron: ${died.length}${died.length ? ` (fases: ${[...new Set(died.map((r) => r.restore_phase))].join(', ')})` : ''}`);
  check(heaviest < 64.5, `ninguna petición de las restauraciones pasó de 64 MB (pico: ${heaviest} MB)`);
  summary.restore = { requests: restoreRequests.length, heaviest, slowest: Math.max(0, ...restoreRequests.map((r) => r.work ?? r.seconds)) };
}
summary.seconds = Math.round((Date.now() - start) / 1000);
writeFileSync(`${dir}/summary.json`, JSON.stringify(summary, null, 2));
log(failures ? `TERMINADO con ${failures} fallos` : 'TERMINADO');
process.exit(failures ? 1 : 0);
