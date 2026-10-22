// Escenarios de kit-roundtrip.sh (etapa K, §13.3) y resultado de A1 a A14 en $OUT/summary.json.
// Lo arranca run.cjs (main). Cada escenario usa un B nuevo; A se prepara una vez y exporta su kit por REST (como la
// App) y por WP-CLI. Cuando algo depende de otro frente de K (pantalla «Kits», WP-CLI propio, informe), se busca por
// su forma pública y, si falta, el escenario falla con el motivo.
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { pathToFileURL } = require('url');
const R = require('./run.cjs');

const { OUT, ROOT, K, CORE, WOO, SLOW, QUICK, GOLDEN, SCENARIOS, REST, REST_LIMIT_MS, log } = R;
const SHARED = path.join(OUT, 'shared');
const IN_SITE = '/wordpress/wp-content/digi-shared';
const WIDTHS = QUICK ? [1280] : [360, 768, 1280];
const vendors = JSON.parse(fs.readFileSync(path.join(ROOT, 'tests/playground/brand-audit/vendors.json'), 'utf8'));
const VENDOR_HOSTS = [...vendors.hosts, ...(vendors.future || [])];
const isVendor = (host) => VENDOR_HOSTS.some((h) => host === h || host.endsWith('.' + h)) || host === 'wordpress.org' || host.endsWith('.wordpress.org');

let compare;
// Opciones que WordPress o el constructor escriben solos (known.json, «opciones», cada una con su motivo).
const CONTENT_OPTIONS = (JSON.parse(fs.readFileSync(path.join(K, 'known.json'), 'utf8')).opciones || []).map((o) => o.nombre);
let build;
const summary = {
  version: 1,
  sha: '',
  branch: '',
  date: new Date().toISOString(),
  options: { core: CORE, woo: WOO, slow: SLOW, quick: QUICK, scenarios: SCENARIOS },
  scenarios: {},
  max_rest_ms: 0,
  acceptance: {},
};
const ACCEPTANCE = {
  A1: 'Ida y vuelta propia (R1)', A2: 'Origen fuera de línea', A3: 'Terceros (R2, R3, R3b)', A4: 'Sin pérdida', A5: 'Sin peticiones al proveedor',
  A6: 'Sin cambios sin permiso, por modo', A7: 'Sólo agregar (R4)', A8: 'Reimportación (R5)', A9: 'Multisite (R6)', A10: 'Reversión (R8)',
  A11: 'Entradas hostiles (R9)', A12: 'Marca y regresión', A13: 'Tiempo por petición', A14: 'Sin ampliar visibilidad',
};
for (const [id, name] of Object.entries(ACCEPTANCE)) summary.acceptance[id] = { nombre: name, estado: 'no medido', detalle: [] };
summary.acceptance.A12.detalle.push('Se mide con brand-audit.sh STATE=kit, front-budget.sh, conditions.sh, CORE=bundled interaction.sh y las pruebas unitarias.');

function verdict(id, ok, detail) {
  const a = summary.acceptance[id];
  if (!a) return;
  if (!ok) a.estado = 'falla';
  else if (a.estado !== 'falla') a.estado = 'ok';
  for (const d of [].concat(detail || [])) if (d && a.detalle.length < 60 && !a.detalle.includes(d)) a.detalle.push(d);
}

// ── Utilidades de los sitios ──────────────────────────────────────────────────────────────────────────────────
const sessions = {};
async function adminSession(site, base = '') {
  const key = `${site}${base}`;
  if (!sessions[key]) sessions[key] = await R.login(site, site === 'net' && base ? 'admin-sitio' : 'admin', site === 'net' && base ? 'admin-sitio-k' : 'password', key, base);
  return { nonce: sessions[key], jar: key, base };
}
function forget(site) { for (const k of Object.keys(sessions)) if (k.startsWith(site)) delete sessions[k]; }

async function rest(site, auth, method, route, body, { label = '', multipart = null } = {}) {
  const headers = { 'X-WP-Nonce': auth.nonce };
  let payload = null;
  if (multipart) { headers['Content-Type'] = multipart.type; payload = multipart.body; } else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
  const res = await R.request(site, method, `${auth.base}${REST}/${route}`, { body: payload, headers, jar: auth.jar, label });
  summary.max_rest_ms = Math.max(summary.max_rest_ms, res.ms);
  return res;
}

/** Importación por la App (Herramientas → Importar): /upload con el archivo, /import y cada runner. */
async function importApp(site, zipFile, { base = '', include = ['templates', 'content', 'settings', 'plugins'], customization = { settings: null, templates: null, content: null, plugins: null }, label = '' } = {}) {
  const auth = await adminSession(site, base);
  const form = R.multipart({}, { e_import_file: { filename: path.basename(zipFile), data: fs.readFileSync(zipFile) } });
  const up = await rest(site, auth, 'POST', 'upload', undefined, { multipart: form, label: `${label} upload` });
  if (up.status !== 200 || !up.json || !up.json.data || !up.json.data.session) return { ok: false, step: 'upload', status: up.status, message: up.text.slice(0, 300) };
  return runImport(site, auth, up.json.data.session, { include, customization, label, upload: up.json.data });
}

async function runImport(site, auth, session, { include, customization, label, upload, id, referrer }) {
  const body = { id, referrer, session, include, customization };
  const imp = await rest(site, auth, 'POST', 'import', body, { label: `${label} import` });
  if (imp.status !== 200 || !imp.json || !imp.json.data) return { ok: false, step: 'import', status: imp.status, message: imp.text.slice(0, 300), session };
  const runners = imp.json.data.runners || [];
  const results = {};
  const failed = [];
  for (const runner of runners) {
    const res = await rest(site, auth, 'POST', 'import-runner', { session, runner }, { label: `${label} ${runner}` });
    results[runner] = { status: res.status, ms: res.ms, data: res.json && res.json.data };
    if (res.status !== 200) failed.push(`${runner}: ${res.status} ${res.text.slice(0, 200)}`);
    const data = res.json && res.json.data;
    if (data && Array.isArray(data.failed) && data.failed.length) failed.push(`${runner}: failed ${JSON.stringify(data.failed).slice(0, 200)}`);
    if (data && data.status === 'error') failed.push(`${runner}: ${JSON.stringify(data).slice(0, 200)}`);
  }
  return { ok: !failed.length, session, runners, results, failed, upload, body };
}

/** Pantalla «Kits» (frente F): su formulario de subida con «nuevo» y la App con el token (V-8). */
async function importScreen(site, zipFile, { fresh = true, base = '', label = '' } = {}) {
  const auth = await adminSession(site, base);
  const page = await R.request(site, 'GET', `${base}/wp-admin/admin.php?page=digitalisimo-elements-kits`, { jar: auth.jar });
  const form = (page.text.match(/<form[^>]*action="[^"]*admin-post\.php[^"]*"[^>]*>[\s\S]*?<\/form>/gi) || []).find((f) => /name="action"\s+value="digitalisimo_elements_kit_upload"/.test(f));
  if (!form) return { ok: false, step: 'pantalla', message: 'La pantalla «Kits» no tiene el formulario de subida (frente F)' };
  const fields = {};
  for (const m of form.matchAll(/<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"/g)) fields[m[1]] = m[2];
  const file = (form.match(/<input[^>]*type="file"[^>]*name="([^"]+)"/) || [])[1];
  const freshName = (form.match(/<input[^>]*type="checkbox"[^>]*name="([^"]+)"[^>]*value="1"[^>]*>[^<]*sitio es nuevo/i) || form.match(/<input[^>]*type="checkbox"[^>]*name="(fresh|nuevo|mode)"/) || [])[1];
  if (!file) return { ok: false, step: 'pantalla', message: 'El formulario de la pantalla «Kits» no tiene campo de archivo' };
  if (fresh && freshName) fields[freshName] = '1';
  const mp = R.multipart(fields, { [file]: { filename: path.basename(zipFile), data: fs.readFileSync(zipFile) } });
  const post = await R.request(site, 'POST', `${base}/wp-admin/admin-post.php`, { body: mp.body, headers: { 'Content-Type': mp.type }, jar: auth.jar });
  const location = post.headers.location || '';
  const token = (location.match(/[#?&]id=([0-9a-f]{32,64})/) || [])[1];
  if (post.status !== 302 || !token) return { ok: false, step: 'admin-post', status: post.status, message: `Sin redirección a la App con token: ${location || post.text.slice(0, 200)}` };
  // El cuerpo exacto de la App con un kit preparado (app.js: uploadData con referrer, source y kit_id).
  const up = await rest(site, auth, 'POST', 'upload', { referrer: 'digitalisimo', source: 'digitalisimo', kit_id: token }, { label: `${label} upload token` });
  if (up.status !== 200 || !up.json || !up.json.data || !up.json.data.session) return { ok: false, step: 'upload', status: up.status, message: up.text.slice(0, 300), token };
  const run = await runImport(site, auth, up.json.data.session, { include: ['templates', 'content', 'settings', 'plugins'], customization: { settings: null, templates: null, content: null, plugins: null }, label, upload: up.json.data, id: token, referrer: 'digitalisimo' });
  return { ...run, token, location };
}

async function importCli(site, zipInSite, args = [], url = '') {
  const res = await R.wpcli(site, ['digitalisimo-elements', 'kit', 'import', zipInSite, '--user=admin', '--format=json', ...args, ...(url ? [`--url=${url}`] : [])]);
  let data = null;
  try { data = JSON.parse((res.stdout.match(/\{[\s\S]*\}\s*$/) || ['null'])[0]); } catch (e) { data = null; }
  return { ok: res.ok, data, stdout: res.stdout, stderr: res.stderr, fatal: res.fatal };
}

async function signature(site, label, origin = 'a.test', blog = 0) {
  await R.control(site, 'signature', { label, origin, ...(blog ? { blog } : {}) });
  return R.readJson(site, `sig-${label}.json`);
}
async function snapshot(site, label) {
  await R.control(site, 'snapshot', { label });
  return R.readJson(site, `snap-${label}.json`);
}
function jsonl(site, file) {
  const f = path.join(OUT, site, file);
  return fs.existsSync(f) ? fs.readFileSync(f, 'utf8').split('\n').filter(Boolean).map((l) => { try { return JSON.parse(l); } catch (e) { return null; } }).filter(Boolean) : [];
}
// Cada escenario empieza con los registros vacíos; los tiempos REST de los anteriores pasan a rest-all.jsonl (como
// hace startSite), porque A13 mide todas las peticiones de la corrida y no sólo las del último escenario.
function resetLogs(site) {
  const file = path.join(OUT, site, 'rest.jsonl');
  if (fs.existsSync(file)) fs.appendFileSync(path.join(OUT, site, 'rest-all.jsonl'), fs.readFileSync(file));
  for (const f of ['net.jsonl', 'rest.jsonl']) fs.rmSync(path.join(OUT, site, f), { force: true });
}

/** A5: nada al proveedor ni a *.wordpress.org con el contexto de K activo; fuera de él, sólo se listan. */
function networkCheck(site, label) {
  const entries = jsonl(site, 'net.jsonl');
  const bad = entries.filter((e) => isVendor(e.host) && e.context === true);
  const outside = entries.filter((e) => isVendor(e.host) && e.context !== true);
  const toA = entries.filter((e) => e.host === 'a.test');
  verdict('A5', !bad.length, bad.slice(0, 5).map((e) => `${label}: ${e.url} con el contexto activo (${e.caller})`));
  if (outside.length) summary.acceptance.A5.detalle.push(`${label}: ${outside.length} consulta(s) fuera de la sesión (actualizaciones de WordPress u otras), sólo informadas`);
  return { entries, bad, outside, toA };
}
function restTimes(site, label, { all = false } = {}) {
  const entries = (all ? jsonl(site, 'rest-all.jsonl') : []).concat(jsonl(site, 'rest.jsonl')).filter((e) => /import-export-customization/.test(e.route));
  const slow = entries.filter((e) => e.ms > REST_LIMIT_MS);
  for (const e of entries) summary.max_rest_ms = Math.max(summary.max_rest_ms, e.ms);
  return { entries, slow, max: entries.reduce((m, e) => Math.max(m, e.ms), 0), label };
}

// ── Exportación desde A ───────────────────────────────────────────────────────────────────────────────────────
async function exportFromA() {
  const auth = await adminSession('a');
  const body = { include: ['templates', 'content', 'settings', 'plugins'], kitInfo: { title: 'Kit de A', description: 'Ida y vuelta de K', source: 'local' }, customization: { settings: null, templates: null, content: null, plugins: null }, plugins: [], selectedCustomPostTypes: [] };
  const res = await rest('a', auth, 'POST', 'export', body, { label: 'export' });
  const out = {};
  if (res.status === 200 && res.json && res.json.data && res.json.data.file) {
    out.rest = path.join(SHARED, 'exports', 'a-rest.zip');
    fs.writeFileSync(out.rest, Buffer.from(res.json.data.file, 'base64'));
    out.restManifest = res.json.data.manifest;
  } else {
    out.restError = `${res.status} ${res.text.slice(0, 300)}`;
  }
  const cli = await R.wpcli('a', ['digitalisimo-elements', 'kit', 'export', `${IN_SITE}/exports/a-cli.zip`, '--user=admin']);
  if (cli.ok && fs.existsSync(path.join(SHARED, 'exports', 'a-cli.zip'))) out.cli = path.join(SHARED, 'exports', 'a-cli.zip');
  else out.cliError = (cli.stderr || cli.fatal || cli.stdout || '').slice(0, 300);
  return out;
}

function zipEntries(file) {
  const out = execFileSync('python3', ['-I', '-c', 'import sys,zipfile,json\nz=zipfile.ZipFile(sys.argv[1])\nprint(json.dumps({n:z.read(n).decode("utf-8","replace") if n.endswith((".json",".xml",".svg",".css",".txt",".html")) else "" for n in z.namelist()}))', file], { maxBuffer: 256 * 1024 * 1024 });
  return JSON.parse(out.toString('utf8'));
}

/** Lo que no se exporta a propósito (not-exported.json): A lo tiene y el kit no lo lleva. */
async function notExportedCheck(files) {
  const entries = compare.loadNotExported();
  const problems = [];
  for (const entry of entries) {
    const [kind, value] = Object.entries(entry.origen || {})[0] || [];
    if (kind) {
      const found = await R.control('a', 'find', { kind, value });
      if (!found.found) { problems.push(`not-exported.json «${entry.clave}»: sin usar (A no tiene ${kind} ${value})`); continue; }
    }
    for (const [label, file] of Object.entries(files)) {
      for (const [name, text] of Object.entries(zipEntries(file))) {
        if (text.includes(entry.buscar)) problems.push(`${label}: ${name} lleva «${entry.clave}» (${entry.motivo})`);
      }
    }
  }
  return problems;
}

// ── Render (A1): capturas de A y B y diferencia de píxeles ──────────────────────────────────────────────────
let browser = null;
async function chrome() {
  if (browser) return browser;
  const puppeteer = require('puppeteer-core');
  browser = await puppeteer.launch({
    executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', `--host-resolver-rules=MAP a.test 127.0.0.1:${R.PORTS.a}, MAP b.test 127.0.0.1:${R.PORTS.b}, MAP playground.test 127.0.0.1:${R.PORTS.net}, MAP * ~NOTFOUND`, `--log-net-log=${path.join(OUT, 'netlog.json')}`, '--net-log-capture-mode=Default'],
  });
  return browser;
}
/**
 * Zonas que la captura oculta (known.json, «capturas»): lo que depende de algo que nunca viaja o del host, y que la
 * firma ya compara por dato (el texto de un código QR, la autora de una caja de autor, autor y comentarios de una
 * entrada). Se quitan (display: none) en A y en B: su alto también depende de ese dato y movería lo de debajo.
 */
const MASKS = (JSON.parse(fs.readFileSync(path.join(K, 'known.json'), 'utf8')).capturas || []).map((m, i) => {
  if (!m.pagina || !m.selector || !m.motivo) throw new Error(`known.json capturas[${i}]: falta «pagina», «selector» o «motivo»`);
  return { ...m, re: new RegExp(m.pagina), used: 0 };
});
async function shoot(host, slug, width, file, hide = []) {
  const b = await chrome();
  const ctx = await b.createBrowserContext();
  const page = await ctx.newPage();
  const errors = [];
  const missing = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e).slice(0, 200)));
  page.on('response', (r) => { if (r.status() === 404 && r.url().includes(host)) missing.push(r.url()); });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await page.setViewport({ width, height: 900 });
  try {
    await page.goto(`http://${host}/${slug ? slug + '/' : ''}`, { waitUntil: 'networkidle0', timeout: 120000 });
    await page.evaluate(() => (document.fonts ? document.fonts.ready : null)).catch(() => {});
    if (hide.length) await page.addStyleTag({ content: `${hide.join(', ')} { display: none !important; }` });
    await R.sleep(500);
    await page.screenshot({ path: file, fullPage: true, captureBeyondViewport: true });
  } catch (e) {
    errors.push('carga: ' + String(e.message || e).slice(0, 160));
  }
  await ctx.close();
  return { errors, missing };
}
async function pixelDiff(fileA, fileB) {
  const b = await chrome();
  const page = await b.newPage();
  const out = await page.evaluate(async (a, bb) => {
    const load = (src) => new Promise((resolve, reject) => { const i = new Image(); i.onload = () => resolve(i); i.onerror = () => reject(new Error('imagen')); i.src = `data:image/png;base64,${src}`; });
    const [ia, ib] = await Promise.all([load(a), load(bb)]);
    const w = Math.max(ia.width, ib.width); const h = Math.max(ia.height, ib.height);
    const draw = (img) => { const c = document.createElement('canvas'); c.width = w; c.height = h; const x = c.getContext('2d'); x.fillStyle = '#ff00ff'; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0); return x.getImageData(0, 0, w, h).data; };
    const da = draw(ia); const db = draw(ib);
    let diff = 0;
    for (let i = 0; i < da.length; i += 4) if (Math.abs(da[i] - db[i]) > 16 || Math.abs(da[i + 1] - db[i + 1]) > 16 || Math.abs(da[i + 2] - db[i + 2]) > 16) diff++;
    return { ratio: diff / (w * h), a: `${ia.width}x${ia.height}`, b: `${ib.width}x${ib.height}` };
  }, fs.readFileSync(fileA).toString('base64'), fs.readFileSync(fileB).toString('base64'));
  await page.close();
  return out;
}
async function renderCompare(sigA, sigB, label) {
  // Cada página publicada de A con su ruta en A y en B (el slug puede cambiar: «la ruta cambió» va al informe).
  // Una página que B deja en borrador (una regla de acceso sin destino, §5.5) no se ve sin sesión: su estado ya lo
  // compara la firma (y known.json lo declara), así que aquí sólo se anota.
  const published = Object.entries(sigA.documents).filter(([k, d]) => /^page:/.test(k) && d.status === 'publish' && sigB.documents[k]);
  const skipped = published.filter(([k]) => sigB.documents[k].status !== 'publish').map(([k]) => `${k.slice(5)} (${sigB.documents[k].status} en B)`);
  const pages = [['portada', '', '']].concat(published.filter(([k]) => sigB.documents[k].status === 'publish').map(([k, d]) => [k.slice(5), d.path, sigB.documents[k].path]));
  const pick = QUICK ? pages.filter(([key]) => ['portada', 'padre', 'widget-digitalisimo-accordion', 'widget-digitalisimo-logos'].includes(key)) : pages;
  const problems = [];
  if (skipped.length) R.log(`   ${label}: sin captura en B (no publicadas): ${skipped.join(', ')}`);
  fs.mkdirSync(path.join(OUT, 'shots', label), { recursive: true });
  for (const [key, pathA, pathB] of pick) {
    const masks = MASKS.filter((m) => m.re.test(key));
    for (const m of masks) m.used++;
    const hide = masks.map((m) => m.selector);
    for (const width of WIDTHS) {
      const name = `${key}-${width}`;
      const fa = path.join(OUT, 'shots', label, `A-${name}.png`);
      const fb = path.join(OUT, 'shots', label, `B-${name}.png`);
      const ra = await shoot('a.test', String(pathA || '').replace(/^\/|\/$/g, ''), width, fa, hide);
      const rb = await shoot('b.test', String(pathB || '').replace(/^\/|\/$/g, ''), width, fb, hide);
      for (const e of rb.errors) problems.push(`${name}: error JS o de carga en B: ${e}`);
      for (const m of rb.missing) problems.push(`${name}: 404 en B: ${m}`);
      if (fs.existsSync(fa) && fs.existsSync(fb)) {
        const px = await pixelDiff(fa, fb);
        if (px.ratio > 0.005) problems.push(`${name}: ${(px.ratio * 100).toFixed(2)} % de píxeles distintos (A ${px.a}, B ${px.b})`);
      }
    }
  }
  if (!QUICK) for (const m of MASKS.filter((x) => !x.used)) problems.push(`known.json: la captura oculta «${m.selector}» en «${m.pagina}», que no se comparó: sobra`);
  return { pages: pick.length, skipped, problems };
}

// ── Escenarios ────────────────────────────────────────────────────────────────────────────────────────────────
const K_DIR = path.join(SHARED, 'kits');
const kitPath = (name) => path.join(K_DIR, name);
const kitInSite = (name) => `${IN_SITE}/kits/${name}`;
const expectOf = (name) => JSON.parse(fs.readFileSync(path.join(K_DIR, name.replace(/\.zip$/, '.expect.json')), 'utf8'));
let kits = {};
let sigA = null;
const known = () => compare.loadKnown();
let knownAll = null;

/**
 * Primera visita del administrador: WordPress y el constructor crean entonces opciones propias (claves de sesión,
 * roles, fecha de instalación). Se hace antes de cualquier instantánea para no confundirlas con la importación.
 */
async function warmUp(site, base = '') {
  const auth = await adminSession(site, base);
  for (const url of [`${base}/wp-admin/`, `${base}/wp-admin/admin.php?page=elementor`, `${base}/wp-admin/admin.php?page=digitalisimo-elements-kits`, `${base}/`]) {
    await R.request(site, 'GET', url, { jar: auth.jar }).catch(() => null);
  }
}

async function freshB(extra = null) {
  forget('b');
  await R.startSite('b');
  await warmUp('b');
  if (extra) {
    const res = await R.control('b', extra);
    if (res.errors) throw new Error(`B (${extra}): ${res.errors}`);
  }
  resetLogs('b');
}

async function withReview(sig, session, site = 'b', blog = 0) {
  const res = await R.control(site, 'review', { session, ...(blog ? { blog } : {}) }).catch(() => ({ keys: [] }));
  sig.review = res.keys || [];
  return sig;
}

/** R1: ida y vuelta en «nuevo» por la pantalla «Kits» (REST) y por --mode=fresh; export(A) = export(B); REST = WP-CLI. */
async function R1(s) {
  const problems = [];
  if (!kits.rest) { s.problems.push(`A no exportó por REST: ${kits.restError}`); verdict('A1', false, 'sin kit de A'); return; }
  const variants = QUICK ? [['pantalla', kits.rest]] : [['pantalla', kits.rest], ['wp-cli', kits.cli]];
  const sigs = {};
  for (const [via, zip] of variants) {
    if (!zip) { problems.push(`${via}: no hay kit (${kits.cliError || 'exportación fallida'})`); continue; }
    await freshB();
    const before = await snapshot('b', `R1-${via}-antes`);
    const run = via === 'pantalla' ? await importScreen('b', zip, { fresh: true, label: 'R1' }) : await importCli('b', `${IN_SITE}/exports/${path.basename(zip)}`, ['--mode=fresh']);
    if (!run.ok) { problems.push(`${via}: la importación falló: ${run.message || (run.failed || []).join('; ') || run.stderr}`); continue; }
    const after = await snapshot('b', `R1-${via}-despues`);
    const sigB = await signature('b', `R1-${via}`);
    sigs[via] = sigB;
    const diffs = compare.compareSignatures(sigA, sigB, { known: knownAll, scenario: 'R1' });
    verdict('A1', !diffs.length, diffs.slice(0, 20).map((d) => `${via}: ${d}`));
    verdict('A4', !diffs.some((d) => /^counts\./.test(d)), diffs.filter((d) => /^counts\./.test(d)).slice(0, 5));
    // A6 «nuevo»: además de lo que crea el contenido, sólo cambian la portada (el kit la trae) y, con WOO=1, las páginas
    // de la tienda; el kit activo conserva su ID y nunca cambian el tema, los plugins ni los experimentos.
    const a6 = compare.compareSnapshots(before, after, { allowOptions: [...CONTENT_OPTIONS, ...F7A_OPTIONS, '^page_on_front$', '^show_on_front$', ...(WOO ? ['^woocommerce_.*_page_id$'] : [])], allowTables: { posts: 'libre', postmeta: 'libre', terms: 'libre', termmeta: 'libre', term_taxonomy: 'libre', term_relationships: 'libre', comments: 'libre', commentmeta: 'libre', options: 'libre' }, uploads: 'libre', kit: 'libre', caches: 'libre' });
    verdict('A6', !a6.length, a6.slice(0, 10).map((p) => `R1 ${via}: ${p}`));
    const net = networkCheck('b', `R1 ${via}`);
    verdict('A1', !net.toA.length, net.toA.slice(0, 5).map((e) => `${via}: petición al host de A ${e.url} (CV-4)`));
    verdict('A2', !net.toA.length, net.toA.length ? `${via}: ${net.toA.length} petición(es) al host de A` : `${via}: el host de A estuvo bloqueado y no hizo falta`);
    const report = await R.control('b', 'report', { session: run.session || (run.data && run.data.session) || '' }).catch(() => null);
    s.reports = s.reports || {};
    s.reports[via] = report && report.session ? { media: report.session.report && report.session.report.counts } : null;
    // export(A) = export(B) normalizado (CV-15): B vuelve a exportar y su firma de manifiesto coincide.
    if (via === 'pantalla') {
      const auth = await adminSession('b');
      const res = await rest('b', auth, 'POST', 'export', { include: ['templates', 'content', 'settings', 'plugins'], kitInfo: { title: 'Kit de A', description: 'Ida y vuelta de K', source: 'local' }, customization: { settings: null, templates: null, content: null, plugins: null }, plugins: [], selectedCustomPostTypes: [] }, { label: 'R1 export B' });
      if (res.status === 200 && res.json && res.json.data && res.json.data.manifest) {
        const ma = kits.restManifest || {};
        const mb = res.json.data.manifest;
        // El núcleo sólo exporta lo publicado: lo que B dejó en borrador a propósito (known.json) no vuelve a salir.
        const published = new Set(Object.values(sigB.documents).filter((d) => d.status === 'publish').map((d) => `${d.doc_type}:${d.title}`));
        const strip = (m) => ({
          templates: Object.values(m.templates || {}).map((t) => `${t.doc_type}:${t.title}`).filter((k) => published.has(k)).sort(),
          content: Object.entries(m.content || {}).flatMap(([type, items]) => Object.values(items || {}).map((v) => `${v.doc_type}:${v.title}`)).filter((k) => published.has(k)).sort(),
        });
        const d = compare.diff(strip(ma), strip(mb));
        verdict('A1', !d.length, d.slice(0, 5).map((x) => `export(A) ≠ export(B): ${x.path}`));
      } else problems.push(`B no pudo volver a exportar: ${res.status}`);
      const render = await renderCompare(sigA, sigB, `R1-${via}`);
      verdict('A1', !render.problems.length, render.problems.slice(0, 20));
      s.render = { pages: render.pages, widths: WIDTHS };
      const f7a = await f7aRoundtrip(sigB);
      verdict('A1', !f7a.length, f7a.map((p) => `R1 (F7a): ${p}`));
    }
  }
  if (sigs.pantalla && sigs['wp-cli']) {
    const d = compare.compareSignatures(sigs.pantalla, sigs['wp-cli'], { known: [], scenario: 'R1' });
    verdict('A1', !d.length, d.slice(0, 10).map((x) => `REST ≠ WP-CLI: ${x}`));
  }
  if (GOLDEN && sigs.pantalla) writeGolden('R1', sigs.pantalla);
  else if (sigs.pantalla) goldenCheck('R1', sigs.pantalla, s);
  s.problems.push(...problems);
  if (problems.length) verdict('A1', false, problems);
}

/** R1c: el cuerpo exacto de la App (Chrome) y una variante con selección parcial (CV-14). */
async function R1c(s) {
  if (!kits.rest) { s.problems.push('Sin kit de A'); return; }
  await freshB();
  const auth = await adminSession('b');
  const b = await chrome();
  const ctx = await b.createBrowserContext();
  const page = await ctx.newPage();
  const host = 'b.test';
  let captured = null;
  await page.setRequestInterception(true);
  page.on('request', (r) => { if (/import-export-customization\/import$/.test(r.url()) && r.method() === 'POST') captured = r.postData(); r.continue(); });
  await page.setCookie(...Object.entries(R.jars[auth.jar]).map(([name, value]) => ({ name, value, domain: host, path: '/' })));
  // La pantalla «Kits» prepara el kit; la App lo sube con el token y muestra la personalización.
  const run = await importScreenToken('b', kits.rest);
  if (!run.token) { s.problems.push(`R1c: ${run.message}`); await ctx.close(); return; }
  await page.goto(`http://${host}/wp-admin/admin.php?page=elementor-app&ver=k#/import-customization?referrer=digitalisimo&id=${run.token}`, { waitUntil: 'networkidle2', timeout: 180000 }).catch(() => {});
  for (let i = 0; i < 40 && !captured; i++) {
    await page.evaluate(() => { const b2 = [...document.querySelectorAll('button, a[role="button"]')].find((x) => /^(importar|import|siguiente|next|continuar|continue)\b/i.test((x.textContent || '').trim()) && !x.disabled); if (b2) b2.click(); }).catch(() => {});
    await R.sleep(3000);
  }
  // La App pide cada runner después de /import: no se sale de la página hasta que termina el último (el de K),
  // o la importación quedaría a medias (hasta 15 minutos).
  for (let i = 0; i < 300 && captured; i++) {
    if (jsonl('b', 'rest.jsonl').some((e) => /import-runner$/.test(e.route) && e.runner === 'digitalisimo')) break;
    await R.sleep(3000);
  }
  if (captured && !jsonl('b', 'rest.jsonl').some((e) => /import-runner$/.test(e.route) && e.runner === 'digitalisimo')) s.problems.push('R1c: la App no llegó al último runner (digitalisimo) en 15 minutos');
  await page.goto(`http://${host}/wp-admin/admin.php?page=elementor-app#/import-customization/complete`, { waitUntil: 'networkidle2', timeout: 120000 }).catch(() => {});
  await ctx.close();
  if (!captured) { s.problems.push('R1c: no se pudo conducir la App hasta /import (cambió su interfaz)'); verdict('A1', false, 'R1c sin cuerpo de la App'); return; }
  s.app_body = JSON.parse(captured);
  fs.writeFileSync(path.join(OUT, 'scenarios', 'R1c-app-body.json'), JSON.stringify(s.app_body, null, 1));
  const sigB = await signature('b', 'R1c');
  const diffs = compare.compareSignatures(sigA, sigB, { known: knownAll, scenario: 'R1c' });
  verdict('A1', !diffs.length, diffs.slice(0, 10).map((d) => `R1c (cuerpo de la App): ${d}`));
  // Selección parcial: sin una de las páginas del contenido.
  await freshB();
  const body = JSON.parse(captured);
  const pages = body.customization && body.customization.content && body.customization.content.page;
  if (Array.isArray(pages) && pages.length > 1) body.customization.content.page = pages.slice(1);
  const partial = await importScreen('b', kits.rest, { fresh: true, label: 'R1c parcial' });
  s.partial = { ok: partial.ok, quitada: Array.isArray(pages) ? pages[0] : null };
}
async function importScreenToken(site, zip) {
  const auth = await adminSession(site);
  const page = await R.request(site, 'GET', '/wp-admin/admin.php?page=digitalisimo-elements-kits', { jar: auth.jar });
  const form = (page.text.match(/<form[^>]*admin-post\.php[\s\S]*?<\/form>/gi) || []).find((f) => /digitalisimo_elements_kit_upload/.test(f));
  if (!form) return { message: 'sin formulario en la pantalla «Kits»' };
  const fields = {};
  for (const m of form.matchAll(/<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"/g)) fields[m[1]] = m[2];
  const file = (form.match(/<input[^>]*type="file"[^>]*name="([^"]+)"/) || [])[1];
  fields.fresh = '1';
  const mp = R.multipart(fields, { [file]: { filename: 'kit.zip', data: fs.readFileSync(zip) } });
  const post = await R.request(site, 'POST', '/wp-admin/admin-post.php', { body: mp.body, headers: { 'Content-Type': mp.type }, jar: auth.jar });
  const token = ((post.headers.location || '').match(/[#?&]id=([0-9a-f]{32,64})/) || [])[1];
  return token ? { token } : { message: `sin token (${post.status})` };
}

/**
 * El kit, campo a campo, en «agregar» (CV-3, D11, B1): lo previo de los globales igual (_id, valor y orden) y lo
 * añadido al final; los ajustes de diseño, las variables y las clases v4, y los valores por defecto de los tipos
 * que el sitio ya tenía, sin cambios. Las metas del kit van aparte de la instantánea (kit_meta) por esto.
 */
function kitAddProblems(kitBefore, kitAfter, tag = '') {
  const problems = [];
  const sb = kitBefore.settings || {};
  const sa = kitAfter.settings || {};
  for (const key of ['system_colors', 'system_typography', 'custom_typography', 'custom_colors']) {
    const prev = (sb[key] || []).map((c) => JSON.stringify(c));
    const now = (sa[key] || []).map((c) => JSON.stringify(c));
    if (!compare.same(prev, now.slice(0, prev.length))) problems.push(`kit: lo previo de ${key} cambió (CV-3)`);
  }
  for (const key of ['custom_css', 'container_width', 'viewport_md', 'viewport_lg', 'body_typography_font_family']) if (!compare.same(sb[key], sa[key])) problems.push(`kit: ${key} cambió en «agregar» (D11): ${JSON.stringify(sb[key] === undefined ? null : sb[key])} → ${JSON.stringify(sa[key] === undefined ? null : sa[key])}`);
  for (const meta of ['_elementor_global_variables', '_elementor_global_classes']) {
    if (!compare.same((kitBefore.meta || {})[meta], (kitAfter.meta || {})[meta])) problems.push(`kit: ${meta} cambió (A7${tag})`);
  }
  const parse = (v) => { try { return JSON.parse([].concat(v || ['{}'])[0]); } catch (e) { return {}; } };
  const defBefore = parse((kitBefore.meta || {})._elementor_elements_default_values);
  const defAfter = parse((kitAfter.meta || {})._elementor_elements_default_values);
  for (const [type, value] of Object.entries(defBefore)) if (!compare.same(value, defAfter[type])) problems.push(`kit: los valores por defecto de ${type} cambiaron (B1: en «agregar» sólo se añaden tipos)`);
  return problems;
}

/** Un kit de terceros en «agregar» sobre B vacío, frente a su «expect» (A3, A4, A6). */
async function thirdParty(s, zipName, { applySettings = false, editor = '' } = {}) {
  await freshB();
  const before = await snapshot('b', `${s.id}-antes`);
  const kitBefore = await R.control('b', 'kit');
  const run = await importApp('b', kitPath(zipName), { label: s.id });
  if (!run.ok) { s.problems.push(`${zipName}: ${run.step || ''} ${run.message || run.failed.join('; ')}`); verdict('A3', false, `${s.id}: la importación falló`); return null; }
  verdict('A3', !run.failed.length, run.failed.map((f) => `${s.id}: ${f}`));
  const after = await snapshot('b', `${s.id}-despues`);
  const sigB = await withReview(await signature('b', s.id, 'demo-autor.test'), run.session);
  const expect = expectOf(zipName);
  const problems = compare.checkExpect(sigB, expect, { mode: 'add' });
  verdict('A3', !problems.length, problems.map((p) => `${s.id}: ${p}`));
  verdict('A14', !problems.some((p) => /A14|revisar/.test(p)), problems.filter((p) => /A14|revisar/.test(p)));
  const a6 = compare.compareSnapshots(before, after, { allowOptions: CONTENT_OPTIONS, allowTables: { posts: 'nuevas', postmeta: 'nuevas', terms: 'nuevas', termmeta: 'nuevas', term_taxonomy: 'nuevas', term_relationships: 'nuevas', comments: 'nuevas', commentmeta: 'nuevas' }, uploads: 'libre', kit: 'libre', caches: 'nuevas' });
  a6.push(...kitAddProblems(kitBefore, await R.control('b', 'kit')));
  verdict('A6', !a6.length, a6.slice(0, 10).map((p) => `${s.id} («agregar»): ${p}`));
  networkCheck('b', s.id);
  if (applySettings) {
    const applied = await applyKitSettings('b', run.session);
    if (!applied.ok) s.problems.push(`«Aplicar los ajustes del kit»: ${applied.message}`);
    const kit = await R.control('b', 'kit');
    const want = expect.kit_after_apply || {};
    const sys = Object.fromEntries((kit.settings.system_colors || []).map((c) => [c._id, String(c.color).toUpperCase()]));
    const miss = Object.entries(want.system_colors || {}).filter(([id, color]) => sys[id] !== color.toUpperCase()).map(([id, color]) => `color del sistema ${id} ${sys[id]} ≠ ${color}`);
    verdict('A3', !miss.length, miss.map((m) => `${s.id} tras «Aplicar»: ${m}`));
  }
  if (editor) {
    const e = await openEditor('b', editor);
    verdict('A3', !e.errors.length, e.errors.map((x) => `${s.id} editor: ${x}`));
  }
  return { run, sigB };
}

async function applyKitSettings(site, session) {
  const auth = await adminSession(site);
  const page = await R.request(site, 'GET', `/wp-admin/admin.php?page=digitalisimo-elements-kits&session=${encodeURIComponent(session)}`, { jar: auth.jar });
  const form = (page.text.match(/<form[^>]*admin-post\.php[\s\S]*?<\/form>/gi) || []).find((f) => /digitalisimo_elements_kit_apply_settings/.test(f));
  if (!form) {
    fs.writeFileSync(path.join(OUT, 'scenarios', `kits-${session}.html`), page.text);
    return { ok: false, message: `la pantalla «Kits» no ofrece «Aplicar los ajustes del kit» (guardada en scenarios/kits-${session}.html)` };
  }
  const fields = {};
  for (const m of form.matchAll(/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"/g)) fields[m[1]] = m[2];
  const post = await R.request(site, 'POST', '/wp-admin/admin-post.php', { body: new URLSearchParams(fields).toString(), headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, jar: auth.jar });
  return { ok: post.status === 302 && !/error|notice=apply_fail/.test(post.headers.location || ''), message: `${post.status} ${post.headers.location || ''}` };
}

async function openEditor(site, slug) {
  const ids = await R.control(site, 'option', { name: 'page_on_front' });
  const b = await chrome();
  const ctx = await b.createBrowserContext();
  const page = await ctx.newPage();
  const errors = [];
  const stacks = [];
  page.on('pageerror', (e) => { errors.push(String(e.message || e).slice(0, 200)); stacks.push(String(e.stack || e.message || e).slice(0, 4000)); });
  const auth = await adminSession(site);
  await page.setCookie(...Object.entries(R.jars[auth.jar]).map(([name, value]) => ({ name, value, domain: R.HOSTS[site], path: '/' })));
  const found = await R.request(site, 'GET', `/wp-json/wp/v2/pages?slug=${slug}&status=draft,publish&_fields=id`, { jar: auth.jar, headers: { 'X-WP-Nonce': auth.nonce } });
  const id = (found.json && found.json[0] && found.json[0].id) || ids.value;
  try {
    await page.goto(`http://${R.HOSTS[site]}/wp-admin/post.php?post=${id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 180000 });
    await page.waitForSelector('#elementor-panel', { timeout: 180000 });
    await R.sleep(4000);
  } catch (e) { errors.push('el editor no cargó: ' + String(e.message || e).slice(0, 160)); }
  await ctx.close();
  // La pila completa de cada error, para el triaje (el veredicto lleva sólo el mensaje).
  if (stacks.length) fs.writeFileSync(path.join(OUT, 'scenarios', `editor-errors-${site}-${slug}.json`), JSON.stringify(stacks, null, 1));
  return { errors };
}

async function R2(s) { await thirdParty(s, 'tercero-envato.zip', { applySettings: true }); }
async function R3(s) { await thirdParty(s, 'tercero-pro3.zip', { editor: 'inicio-acme' }); }
async function R3b(s) { await thirdParty(s, 'tercero-1x.zip'); }

// ── F7a: presets, máscaras, prioridad y condiciones en los kits (V.10, V.13 #6 y #10) ─────────────────────────────
// Opciones que una importación cambia a propósito: los presets del kit (parte `presets`) y los índices derivados del
// contenido (páginas rastreadas por «Visitó la página» y prioridad manual del Theme Builder). Sólo se permiten en las
// instantáneas de R1 y R4; R5, R6 y R8 los comprueban aparte.
const F7A_OPTIONS = ['^digitalisimo_elements_presets$', '^digitalisimo_elements_tracked_pages$', '^digitalisimo_elements_tb_priority$'];
const F7A = {
  presets: 'page:presets-de-estilo',
  design: 'page:fondos-de-diseno',
  priority: 'page:prioridad-de-cabeceras',
  missing: 'page:condicion-visita-sin-destino',
  headers: { auto: 'elementor_library:cabecera-automatica', manual: 'elementor_library:cabecera-prioritaria' },
  // Páginas con una condición de F7a: «Condicionado: <título>» sólo se ve si se cumple; «Para todos: <título>», siempre.
  conditions: ['Condición cookie', 'Condición visita', 'Condición campo', 'Condición campo de usuario', 'Condición autor', 'Condición búsqueda', 'Condición existencias'],
};
const condKey = (title) => `page:${title.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}`;
const at2 = (value, ...keys) => keys.reduce((node, k) => (node === undefined || node === null ? undefined : node[k]), value);
const withoutGlobals = (settings) => { const { __globals__, ...rest } = settings || {}; return rest; };
const maskCopies = (sig) => Object.keys(sig.files || {}).filter((f) => /^masks\/[0-9a-f]{8,64}\.svg$/.test(f));

/** HTML de una página como visitante (sin cookies) y el CSS del documento que enlaza. */
async function visit(site, docPath) {
  const res = await R.request(site, 'GET', docPath || '/', { jar: '__anon' });
  const css = [];
  for (const m of res.text.matchAll(/href=['"]([^'"]*\/elementor\/css\/post-\d+\.css[^'"]*)['"]/g)) {
    const r = await R.request(site, 'GET', m[1].replace(/^https?:\/\/[^/]+/, ''), { jar: '__anon' }).catch(() => null);
    if (r && r.status === 200) css.push(r.text);
  }
  for (const m of res.text.matchAll(/<style[^>]*id=['"]elementor-post-\d+-inline-css['"][^>]*>([\s\S]*?)<\/style>/g)) css.push(m[1]);
  return { status: res.status, html: res.text, css: css.join('\n') };
}

/** Las páginas con condiciones de F7a vistas por un visitante: qué «Condicionado» se ve y si el control se pinta. */
async function conditionsSeen(site, sig) {
  const out = {};
  for (const title of F7A.conditions) {
    const d = (sig.documents || {})[condKey(title)];
    if (!d) continue;
    if (d.status !== 'publish') { out[title] = { estado: d.status }; continue; }
    const page = await visit(site, d.path);
    out[title] = { estado: d.status, http: page.status, condicionado: page.html.includes(`Condicionado: ${title}`), control: page.html.includes(`Para todos: ${title}`) };
  }
  return out;
}

/** R1: la cabecera con prioridad manual gana en A y en B (D20) y A tiene lo que la firma compara. */
async function f7aRoundtrip(sigB) {
  const problems = [];
  for (const [site, sig] of [['a', sigA], ['b', sigB]]) {
    const d = (sig.documents || {})[F7A.priority];
    if (!d) { problems.push(`${site}: falta ${F7A.priority}`); continue; }
    const page = await visit(site, d.path);
    if (!page.html.includes('Cabecera prioritaria') || page.html.includes('Cabecera automática')) problems.push(`${site}: ${d.path} no pinta la cabecera con prioridad manual 10 (D20)`);
    const design = (sig.documents || {})[F7A.design];
    if (design && design.status === 'publish') {
      const p2 = await visit(site, design.path);
      if (!/--digi-mask-[a-z0-9-]+:url\("data:image\/svg\+xml,/.test(p2.css)) problems.push(`${site}: el CSS de ${design.path} no define la variable de máscara con data: en porcentaje (V.5)`);
      if (/\/digitalisimo-elements\/masks\//.test(p2.css)) problems.push(`${site}: el CSS de ${design.path} nombra la copia en uploads (V.5: sólo data:)`);
    }
  }
  return problems;
}

/** R4 y R4v4: lo que F7a añade a «sólo agregar» (V.13 #6): choque de preset, mapa sin falsos, globales, máscara, prioridad. */
async function f7aClash(sigAfter, kitAfter, session) {
  const out = { A7: [], A4: [], A14: [] };
  const presets = sigAfter.presets || {};
  const own = presets.tarjeta;
  if (!own || own.label !== 'Tarjeta de B' || at2(own, 'settings', 'title_color') !== '#00AA55') out.A7.push(`el preset tarjeta de B cambió: ${JSON.stringify(own).slice(0, 160)}`);
  const renamed = Object.keys(presets).filter((k) => /^tarjeta-k[0-9a-f]{6}$/.test(k));
  if (renamed.length !== 1) out.A7.push(`choque de tarjeta: se esperaba un tarjeta-k<hex>, hay ${JSON.stringify(Object.keys(presets))}`);
  const tk = renamed[0];
  if (tk && !compare.same(withoutGlobals(presets[tk].settings), withoutGlobals(at2(sigA, 'presets', 'tarjeta', 'settings')))) out.A7.push(`${tk} no tiene el estilo de la tarjeta de A`);
  if (!presets['tipo-ausente'] || presets['tipo-ausente'].inert !== true) out.A7.push('el preset inerte tipo-ausente no llegó, o no llegó inerte');
  if (!presets.separador) out.A7.push('falta el preset separador');
  // El color que sólo usa `caja` choca con «Propio de B» (bb00001): debe llegar como otro _id con el valor de A.
  const global = String(at2(presets, 'caja', 'settings', '__globals__', 'background_color') || '');
  const gid = (global.match(/id=([a-z0-9]+)$/) || [])[1];
  const color = ((kitAfter.settings || {}).custom_colors || []).find((c) => c._id === gid);
  if (!presets.caja) out.A7.push('falta el preset caja');
  else if (!gid || gid === 'bb00001' || !color || String(color.color).toUpperCase() !== '#F0E6D2') out.A7.push(`caja: el color global que sólo usa un preset no se tradujo (${global || 'sin global'}; ${JSON.stringify(color || null)})`);
  // Vínculos reescritos en el documento importado y en los valores por defecto añadidos.
  const doc = (sigAfter.documents || {})[F7A.presets];
  const tree = doc ? compare.normalizeTree(doc.data) : [];
  const links = { contenedor: at2(tree, 0, 'settings', 'digitalisimo_preset'), local: at2(tree, 0, 'elements', 0, 'settings', 'digitalisimo_preset'), tal: at2(tree, 0, 'elements', 1, 'settings', 'digitalisimo_preset'), divisor: at2(tree, 0, 'elements', 2, 'settings', 'digitalisimo_preset') };
  if (!doc) out.A7.push(`falta ${F7A.presets} en B`);
  else if (links.local !== tk || links.tal !== tk || links.contenedor !== 'caja' || links.divisor !== 'separador') out.A7.push(`vínculos de ${F7A.presets} sin reescribir: ${JSON.stringify(links)}`);
  if (at2(kitAfter, 'defaults', 'divider', 'digitalisimo_preset') !== 'separador') out.A7.push(`el valor por defecto del divisor no llegó con su preset: ${JSON.stringify(at2(kitAfter, 'defaults', 'divider') || null)}`);
  // Informe: el renombrado figura y el mapa de identidad no deja `map.miss` de presets (V-I8).
  const report = await R.control('b', 'report', { session }).catch(() => null);
  const entries = (((report || {}).session || {}).report || {}).entries || [];
  if (!entries.some((e) => e.code === 'settings.presets.renamed' && at2(e, 'data', 'from') === 'tarjeta' && at2(e, 'data', 'to') === tk)) out.A7.push('el informe no dice que tarjeta se renombró');
  const miss = entries.filter((e) => e.code === 'map.miss' && at2(e, 'data', 'map') === 'presets');
  if (miss.length) out.A7.push(`map.miss falso de presets: ${JSON.stringify(miss.slice(0, 3).map((e) => e.data))}`);
  // Copia saneada de la máscara del sitio, por la parte `files` con la subida de SVG sin filtrar apagada (R-I8).
  const copies = maskCopies(sigA);
  if (!copies.length) out.A4.push('A no tiene ninguna copia masks/<sha8>.svg: el caso de la máscara del sitio no se probó');
  for (const f of copies) if (sigAfter.files[f] !== sigA.files[f]) out.A4.push(`la copia ${f} no llegó a B igual que en A (${sigAfter.files[f] || 'falta'})`);
  const fileOf = (sig) => at2(compare.normalizeTree(((sig.documents || {})[F7A.design] || {}).data || []), 1, 'elements', 0, 'settings', 'digitalisimo_mask_file');
  if (fileOf(sigAfter) !== fileOf(sigA)) out.A4.push(`la máscara de la columna apunta a ${fileOf(sigAfter)} y en A a ${fileOf(sigA)}`);
  // Las dos cabeceras con prioridad llegan en borrador: no cambian la cabecera de B (A7).
  for (const key of Object.values(F7A.headers)) if (at2(sigAfter, 'documents', key, 'status') !== 'draft') out.A7.push(`${key} llegó ${at2(sigAfter, 'documents', key, 'status') || 'sin crear'}, no en borrador`);
  // Una referencia sin destino de «Visitó la página» deja el documento en borrador y para revisar (A14).
  const missing = (sigAfter.documents || {})[F7A.missing];
  if (!missing || missing.status !== 'draft') out.A14.push(`${F7A.missing}: ${missing ? missing.status : 'no está'} (esperado borrador por la página que no viaja)`);
  if (!(sigAfter.review || []).some((k) => /^page:condicion-visita-sin-destino/.test(k))) out.A14.push(`${F7A.missing} no quedó en «Revisar antes de publicar»`);
  return out;
}

/** R4: el kit de A en un B con 4.3.0.163; lo condicionado por una condición de F7a no se ve (centinela, V.13 #10). */
async function f7aOldVersion(s) {
  const old = process.env.OLD_ELEMENTS || '';
  if (!old || !fs.existsSync(path.join(old, 'pro-elements.php'))) { verdict('A14', false, 'R4 · 4.3.0.163: falta la copia de la versión anterior (OLD_ELEMENTS)'); s.problems.push('R4 · 4.3.0.163: sin OLD_ELEMENTS'); return; }
  forget('b');
  await R.startSite('b', { elements: old });
  await warmUp('b');
  resetLogs('b');
  const run = await importApp('b', kits.rest, { label: 'R4 4.3.0.163' });
  if (!run.ok) { s.problems.push(`R4 · 4.3.0.163: la importación falló: ${run.message || run.failed.join('; ')}`); verdict('A14', false, 'R4 · 4.3.0.163: la importación falló'); return; }
  const pub = await publish('b', run.session);
  if (!pub.ok) s.problems.push(`R4 · 4.3.0.163: «Publicar páginas y plantillas»: ${pub.message}`);
  const sig = await signature('b', 'R4-163');
  const seen = await conditionsSeen('b', sig);
  s.v163 = seen;
  const problems = [];
  for (const [title, r] of Object.entries(seen)) {
    if (r.condicionado) problems.push(`4.3.0.163 muestra «Condicionado: ${title}» a un visitante: una condición de F7a amplió la visibilidad`);
    if (r.estado === 'publish' && !r.control) problems.push(`4.3.0.163: ${title} no pinta su control «Para todos» (la página no se midió)`);
  }
  const measured = Object.values(seen).filter((r) => r.estado === 'publish' && r.control).length;
  if (measured < 4) problems.push(`4.3.0.163: sólo ${measured} página(s) con condiciones de F7a publicadas y medidas (esperadas al menos 4)`);
  verdict('A14', !problems.length, problems.map((p) => `R4: ${p}`).concat(`R4 · 4.3.0.163: ${measured} página(s) con condiciones de F7a, ninguna muestra lo condicionado`));
  s.problems.push(...problems);
}

/** R4 y R4v4: «sólo agregar» sobre B con el contenido de choque (A6, A7). */
async function clash(s, v4) {
  if (!kits.rest) { s.problems.push('Sin kit de A'); return; }
  await freshB(v4 ? 'clash-v4' : 'clash');
  const before = await snapshot('b', `${s.id}-antes`);
  const sigBefore = await signature('b', `${s.id}-antes`);
  const kitBefore = await R.control('b', 'kit');
  const run = await importApp('b', kits.rest, { label: s.id });
  if (!run.ok) { s.problems.push(`${s.id}: ${run.message || run.failed.join('; ')}`); verdict('A7', false, `${s.id}: la importación falló`); return; }
  const after = await snapshot('b', `${s.id}-despues`);
  const sigAfter = await signature('b', `${s.id}-despues`);
  const kitAfter = await R.control('b', 'kit');
  const problems = [];
  // Lo previo no cambia: _elementor_data, post_content, estado y condiciones (A7).
  for (const [key, doc] of Object.entries(sigBefore.documents)) {
    const now = sigAfter.documents[key];
    if (!now) { problems.push(`${key} desapareció`); continue; }
    for (const field of ['data', 'status', 'conditions', 'settings']) if (!compare.same(doc[field], now[field])) problems.push(`${key}: ${field} cambió`);
  }
  for (const [key, post] of Object.entries(sigBefore.posts)) {
    const now = sigAfter.posts[key];
    if (!now || now.content !== post.content || now.status !== post.status) problems.push(`${key}: contenido o estado cambió`);
  }
  problems.push(...kitAddProblems(kitBefore, kitAfter, v4 ? ', R4v4' : ''));
  if (!compare.same(sigBefore.options, sigAfter.options)) problems.push(`opciones de portada cambiaron: ${JSON.stringify(sigAfter.options)}`);
  verdict('A7', !problems.length, problems.map((p) => `${s.id}: ${p}`));
  const a6 = compare.compareSnapshots(before, after, { allowOptions: [...CONTENT_OPTIONS, ...F7A_OPTIONS], allowTables: { posts: 'nuevas', postmeta: 'nuevas', terms: 'nuevas', termmeta: 'nuevas', term_taxonomy: 'nuevas', term_relationships: 'nuevas' }, uploads: 'libre', kit: 'libre', caches: 'nuevas' });
  verdict('A6', !a6.length, a6.slice(0, 10).map((p) => `${s.id}: ${p}`));
  // F7a (V.13 #6): presets, globales de un preset, máscara del sitio, prioridad en borrador y referencia sin destino.
  await withReview(sigAfter, run.session);
  const f7a = await f7aClash(sigAfter, kitAfter, run.session);
  for (const [id, list] of Object.entries(f7a)) verdict(id, !list.length, list.map((p) => `${s.id} (F7a): ${p}`));
  s.f7a = f7a;
  // Tras «Publicar», una página importada pinta su fuente y su icono propios (CV-17): se comprueba en el HTML.
  const pub = await publish('b', run.session);
  if (!pub.ok) s.problems.push(`«Publicar páginas y plantillas»: ${pub.message}`);
  // Lo condicionado por una condición de F7a que no se cumple para un visitante tampoco se ve aquí (A14).
  s.condiciones = await conditionsSeen('b', await signature('b', `${s.id}-publicado`));
  const shown = Object.entries(s.condiciones).filter(([t, r]) => r.condicionado && !['Condición campo', 'Condición autor'].includes(t)).map(([t]) => t);
  verdict('A14', !shown.length, shown.map((t) => `${s.id}: un visitante ve «Condicionado: ${t}»`));
  networkCheck('b', s.id);
  // Con QUICK=1, kit-roundtrip.sh no prepara 4.3.0.163: el paso sólo corre si se pasa OLD_ELEMENTS.
  if (!v4 && (!QUICK || process.env.OLD_ELEMENTS)) await f7aOldVersion(s);
}
async function publish(site, session) {
  const auth = await adminSession(site);
  const page = await R.request(site, 'GET', `/wp-admin/admin.php?page=digitalisimo-elements-kits&session=${encodeURIComponent(session)}`, { jar: auth.jar });
  const form = (page.text.match(/<form[^>]*admin-post\.php[\s\S]*?<\/form>/gi) || []).find((f) => /digitalisimo_elements_kit_publish/.test(f));
  if (!form) {
    fs.writeFileSync(path.join(OUT, 'scenarios', `kits-${session}.html`), page.text);
    return { ok: false, message: `la pantalla «Kits» no ofrece «Publicar páginas y plantillas» (guardada en scenarios/kits-${session}.html)` };
  }
  const fields = {};
  for (const m of form.matchAll(/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"/g)) fields[m[1]] = m[2];
  const post = await R.request(site, 'POST', '/wp-admin/admin-post.php', { body: new URLSearchParams(fields).toString(), headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, jar: auth.jar });
  return { ok: post.status === 302, message: `${post.status} ${post.headers.location || ''}` };
}
async function R4(s) { await clash(s, false); }
async function R4v4(s) { await clash(s, true); }

/** R5: reimportar sobre B con contenido: la segunda vez no crea filas ni archivos (A8, CV-1, CV-10). */
async function R5(s) {
  if (!kits.rest) { s.problems.push('Sin kit de A'); return; }
  await freshB('content');
  const first = await importApp('b', kits.rest, { label: 'R5 primera' });
  if (!first.ok) { s.problems.push(`primera importación: ${first.message || first.failed.join('; ')}`); verdict('A8', false, 'R5: la primera importación falló'); return; }
  const kit1 = await R.control('b', 'kit');
  const presets1 = (await R.control('b', 'option', { name: 'digitalisimo_elements_presets' })).value;
  const before = await snapshot('b', 'R5-antes');
  const second = await importApp('b', kits.rest, { label: 'R5 segunda' });
  if (!second.ok) { s.problems.push(`segunda importación: ${second.message || second.failed.join('; ')}`); verdict('A8', false, 'R5: la segunda importación falló'); return; }
  const after = await snapshot('b', 'R5-despues');
  const kit2 = await R.control('b', 'kit');
  const problems = compare.compareSnapshots(before, after, { allowOptions: CONTENT_OPTIONS, trees: true, uploads: 'igual' })
    .filter((p) => !/opción digitalisimo_elements_kit_session_/.test(p));
  for (const key of ['custom_colors', 'custom_typography']) if ((kit1.settings[key] || []).length !== (kit2.settings[key] || []).length) problems.push(`${key}: ${(kit1.settings[key] || []).length} → ${(kit2.settings[key] || []).length} (CV-10)`);
  // F7a: la primera importación trae los presets; la segunda los reutiliza (misma huella) sin crear ninguno.
  const presets2 = (await R.control('b', 'option', { name: 'digitalisimo_elements_presets' })).value;
  const slugs = (v) => Object.keys((v && v.presets) || {}).sort();
  if (!slugs(presets1).length) problems.push('la primera importación no trajo presets (F7a)');
  if (!compare.same(presets1, presets2)) problems.push(`la segunda importación cambió los presets: ${JSON.stringify(slugs(presets1))} → ${JSON.stringify(slugs(presets2))} (F7a)`);
  const report = await R.control('b', 'report', { session: second.session });
  const counts = (((report || {}).session || {}).report || {}).counts || {};
  s.reutilizados = counts['origin.reuse'] || counts.reused || null;
  verdict('A8', !problems.length, problems.map((p) => `R5: ${p}`));
}

/** R6: red; importación en el sitio 2 por un administrador del sitio sin unfiltered_html (A9, CV-24). */
async function R6(s) {
  if (!kits.rest) { s.problems.push('Sin kit de A'); return; }
  forget('net');
  await R.startSite('net', { network: true });
  // Las sales de sitemeta (auth_*, logged_in_*) y los roles nacen con el primer inicio de sesión: antes de la instantánea.
  await warmUp('net');
  await warmUp('net', '/sub');
  const before = await snapshot('net', 'R6-antes');
  const run = await importApp('net', kits.rest, { base: '/sub', label: 'R6' });
  if (!run.ok) s.problems.push(`R6: ${run.message || run.failed.join('; ')}`);
  const after = await snapshot('net', 'R6-despues');
  const problems = compare.compareSnapshots(before, after, { blogs: ['1', '3'], allowOptions: CONTENT_OPTIONS, trees: true });
  const uploads2 = Object.keys(((after.sites || {})['2'] || {}).uploads || {});
  if (run.ok && !uploads2.length) problems.push('el sitio 2 no tiene archivos en uploads/sites/2/');
  const report = await R.control('net', 'report', { session: run.session || '', blog: 2 }).catch(() => null);
  const counts = (((report || {}).session || {}).report || {}).counts || {};
  if (!Object.keys(counts).some((c) => /custom_code\.skipped/.test(c))) problems.push('el informe no dice que Custom Code no se importó sin unfiltered_html');
  // F7a: los presets del kit sólo en el sitio 2; los sitios 1 y 3 siguen sin la opción.
  for (const blog of [1, 2, 3]) {
    const value = (await R.control('net', 'option', { name: 'digitalisimo_elements_presets', blog })).value;
    const n = Object.keys((value && value.presets) || {}).length;
    if (blog === 2 && run.ok && !n) problems.push('el sitio 2 no recibió los presets del kit (F7a)');
    if (blog !== 2 && value !== false) problems.push(`el sitio ${blog} tiene presets (${n}) tras importar en el sitio 2 (F7a)`);
  }
  verdict('A9', !problems.length && run.ok, problems.map((p) => `R6: ${p}`));
  networkCheck('net', 'R6');
  await R.stopSite('net');
}

/** R7: el proveedor nunca recibe nada (A5): file_url y process-media → 403, y los registros de red de toda la corrida. */
async function R7(s) {
  await freshB();
  const auth = await adminSession('b');
  const vendorUrl = `https://${vendors.hosts[0]}/kits/kit.zip`;
  const fu = await rest('b', auth, 'POST', 'upload', { file_url: vendorUrl, kit_id: '1' }, { label: 'R7 file_url' });
  const pm = await rest('b', auth, 'POST', 'process-media', { media: [vendorUrl] }, { label: 'R7 process-media' });
  const cloud = await rest('b', auth, 'POST', 'upload', { source: 'cloud', kit_id: '1' }, { label: 'R7 cloud' });
  const problems = [];
  if (fu.status !== 403) problems.push(`upload con file_url → ${fu.status}, esperado 403`);
  if (pm.status !== 403) problems.push(`process-media → ${pm.status}, esperado 403 (V-23)`);
  if (cloud.status !== 403) problems.push(`upload con source=cloud → ${cloud.status}, esperado 403`);
  const net = networkCheck('b', 'R7');
  if (net.entries.some((e) => isVendor(e.host))) problems.push('una petición llegó a un dominio del proveedor');
  verdict('A5', !problems.length, problems.map((p) => `R7: ${p}`));
}

/** R8: revertir deja B como su instantánea, salvo la papelera y los adjuntos (A10, D16, V-29). */
async function R8(s) {
  if (!kits.rest) { s.problems.push('Sin kit de A'); return; }
  await freshB('content');
  const before = await snapshot('b', 'R8-antes');
  const sigBefore = await signature('b', 'R8-antes', 'a.test');
  const kitBefore = await R.control('b', 'kit');
  const presetsBefore = (await R.control('b', 'option', { name: 'digitalisimo_elements_presets' })).value;
  const run = await importApp('b', kits.rest, { label: 'R8' });
  if (!run.ok) { s.problems.push(`importación: ${run.message || run.failed.join('; ')}`); verdict('A10', false, 'R8: la importación falló'); return; }
  // Lo que creó la sesión: lo que hay después de importar y no estaba antes (B ya traía su contenido).
  const sigImported = await signature('b', 'R8-importado', 'a.test');
  const created = Object.keys(sigImported.documents).filter((k) => !sigBefore.documents[k]);
  const auth = await adminSession('b');
  const rev = await rest('b', auth, 'POST', 'revert', {}, { label: 'R8 revert' });
  if (rev.status !== 200) s.problems.push(`revert → ${rev.status} ${rev.text.slice(0, 200)}`);
  const after = await snapshot('b', 'R8-despues');
  const kitAfter = await R.control('b', 'kit');
  const problems = compare.compareSnapshots(before, after, { allowOptions: [...CONTENT_OPTIONS, '^digitalisimo_elements_kit_session_'], allowTables: { posts: 'libre', postmeta: 'libre' }, uploads: 'igual' });
  const sig = await signature('b', 'R8-despues', 'a.test');
  const trashed = Object.values(sig.documents).filter((d) => d.status === 'trash').length + Object.values(sig.posts).filter((p) => p.status === 'trash').length;
  // Cada documento de la sesión sale de B: a la papelera (D16, «como el núcleo») las páginas y entradas, y del todo lo
  // demás, porque Document::delete() llama a wp_delete_post() sin forzar y WordPress sólo manda a la papelera
  // `post` y `page` (N:core/base/document.php:1291-1299). Nada de la sesión queda publicado ni en borrador.
  const live = created.filter((k) => sig.documents[k] && sig.documents[k].status !== 'trash');
  const gone = created.filter((k) => !sig.documents[k]);
  const goneTrashable = gone.filter((k) => /^(page|post):/.test(k));
  if (live.length) problems.push(`quedan documentos de la sesión fuera de la papelera: ${live.slice(0, 5).join(', ')}`);
  if (goneTrashable.length) problems.push(`la reversión borró del todo ${goneTrashable.length} página(s) o entrada(s) en vez de mandarlas a la papelera (D16): ${goneTrashable.slice(0, 5).join(', ')}`);
  s.borrados = gone.length - goneTrashable.length;
  if (!created.length) problems.push('la importación no creó documentos: la reversión no se pudo medir');
  for (const [k, d] of Object.entries(sigBefore.documents)) if (!sig.documents[k] || sig.documents[k].status !== d.status) problems.push(`${k}, que ya estaba en B, cambió con la reversión`);
  if (!compare.same(kitBefore.settings, kitAfter.settings)) problems.push('los ajustes del kit no volvieron a los de antes');
  // F7a: la parte `presets` revierte su opción y los valores por defecto que reescribió.
  const presetsAfter = (await R.control('b', 'option', { name: 'digitalisimo_elements_presets' })).value;
  if (!compare.same(presetsBefore, presetsAfter)) problems.push(`la opción digitalisimo_elements_presets no volvió a la de antes: ${JSON.stringify(Object.keys((presetsAfter && presetsAfter.presets) || {}))}`);
  if (!compare.same(kitBefore.defaults, kitAfter.defaults)) problems.push(`los valores por defecto del kit no volvieron a los de antes: ${JSON.stringify(Object.keys(kitAfter.defaults || {}))}`);
  s.papelera = trashed;
  verdict('A10', !problems.length, problems.map((p) => `R8: ${p}`).concat(`R8: de ${created.length} documento(s) creados, ${trashed} en la papelera y ${s.borrados} plantilla(s) borradas del todo, como hace el núcleo (D16)`));
}

/** R9: permisos y entradas hostiles (A11). */
async function R9(s) {
  await freshB('users');
  const content = await R.control('b', 'content');
  const problems = [];
  const editor = { nonce: await R.login('b', 'editora', 'editora-k', 'b-editora'), jar: 'b-editora', base: '' };
  for (const route of ['upload', 'import', 'import-runner', 'revert', 'export']) {
    const res = await rest('b', editor, 'POST', route, route === 'import' || route === 'import-runner' ? { session: 'x', runner: 'templates' } : {}, { label: `R9 editora ${route}` });
    // El núcleo sólo registra el módulo de kits para quien tiene manage_options (N:app/app.php:321-325): una editora
    // recibe 404 rest_no_route, no 403. Las dos son un rechazo; se anota el código.
    if (![401, 403, 404].includes(res.status)) problems.push(`editora → ${route}: ${res.status}, esperado un rechazo`);
    else s.editora = Object.assign(s.editora || {}, { [route]: res.status });
  }
  // F7a: la editora abre el editor (deja _edit_lock y puede generar CSS) antes de la instantánea; sus acciones de
  // presets van después y no deben cambiar nada.
  const pageId = (content.ids || {}).page;
  const editorPage = pageId ? await R.request('b', 'GET', `/wp-admin/post.php?post=${pageId}&action=elementor`, { jar: editor.jar }) : null;
  const ajaxNonce = editorPage ? (editorPage.text.match(/"ajax":\{"url":"[^"]*","nonce":"([0-9a-f]{10})"/) || [])[1] : '';
  const before = await snapshot('b', 'R9-antes');
  const { zipStore } = build;
  const manifest = JSON.stringify({ name: 'hostil', title: 'Hostil', version: '3.0', site: 'http://hostil.test', templates: {}, content: {} });
  const base = [{ name: 'manifest.json', data: manifest }];
  const hostile = {
    'traversal': [...base, { name: '../fuera.json', data: '{}' }],
    'absoluta': [...base, { name: '/abs.json', data: '{}' }],
    'barra-invertida': [...base, { name: 'a\\b.json', data: '{}' }],
    'php': [...base, { name: 'templates/x.php', data: '<?php echo 1;' }],
    'phtml-mayusculas': [...base, { name: 'docs/x.PHTML', data: '<?php echo 1;' }],
    'htaccess': [...base, { name: '.htaccess', data: 'Require all granted' }],
    'doctype': [...base, { name: 'wp-content/post/post.xml', data: '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><rss>&e;</rss>' }],
    'enlace': [...base, { name: 'templates/1.json', data: '/etc/passwd', symlink: true }],
    'tamano-falso': [...base, { name: 'templates/1.json', data: '{"content":[]}'.padEnd(4096, ' '), size: 10 }],
    'tipos-erroneos': [...base, { name: 'content/page/1.json', data: '{"content":"no es una lista"}' }],
    'anidado-doble': [{ name: 'a.zip', data: zipStore([{ name: 'b.zip', data: zipStore(base) }]) }],
    'media-ruta': [...base, { name: 'digitalisimo/media.json', data: JSON.stringify({ 'http://hostil.test/x.png': { file: '../../x.php', sha256: '0', mime: 'image/png', bytes: 1 } }) }],
  };
  const dir = path.join(OUT, 'scenarios', 'hostiles');
  fs.mkdirSync(dir, { recursive: true });
  const auth = await adminSession('b');
  for (const [name, entries] of Object.entries(hostile)) {
    const file = path.join(dir, `${name}.zip`);
    fs.writeFileSync(file, zipStore(entries));
    const form = R.multipart({}, { e_import_file: { filename: `${name}.zip`, data: fs.readFileSync(file) } });
    const res = await rest('b', auth, 'POST', 'upload', undefined, { multipart: form, label: `R9 ${name}` });
    const message = res.json && res.json.data ? (res.json.data.message || res.json.message || '') : (res.json && res.json.message) || '';
    if (res.status < 400) problems.push(`${name}: la subida no se rechazó (${res.status})`);
    else if (!message) problems.push(`${name}: rechazo sin mensaje`);
  }
  // F7a (V.13 #5, V-m14): la editora abre el editor y pide crear y borrar presets por elementor_ajax; el núcleo responde
  // 200 y cada acción lleva su `code`, que debe ser 403.
  const nonce = ajaxNonce;
  if (!nonce) problems.push(`editora: sin el nonce de elementor_ajax en el editor (${editorPage ? editorPage.status : 'sin página'})`);
  else {
    const actions = {
      guardar: { action: 'digitalisimo_preset_save', data: { slug: 'intruso', type: 'heading', label: 'Intruso', settings: { title_color: '#ff0000' } } },
      borrar: { action: 'digitalisimo_preset_delete', data: { slug: 'intruso' } },
    };
    const body = new URLSearchParams({ action: 'elementor_ajax', _nonce: nonce, editor_post_id: String(pageId), initial_document_id: String(pageId), actions: JSON.stringify(actions) }).toString();
    const res = await R.request('b', 'POST', '/wp-admin/admin-ajax.php', { body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, jar: editor.jar, label: 'R9 editora presets' });
    const responses = (res.json && res.json.data && res.json.data.responses) || {};
    s.presets_ajax = { http: res.status, codes: Object.fromEntries(Object.entries(responses).map(([k, v]) => [k, v && v.code])) };
    for (const id of Object.keys(actions)) {
      const r = responses[id];
      if (!r || r.success !== false || Number(r.code) !== 403) problems.push(`editora → ${actions[id].action}: code ${r ? r.code : 'sin respuesta'} (HTTP ${res.status}), esperado 403`);
    }
  }
  const after = await snapshot('b', 'R9-despues');
  const changed = compare.compareSnapshots(before, after, { allowOptions: CONTENT_OPTIONS, uploads: 'igual' })
    .filter((p) => !/uploads\/digitalisimo-elements\/tmp\/(index\.php|\.htaccess)/.test(p));
  problems.push(...changed);
  if (fs.existsSync(path.join(OUT, 'b', 'fatal.log'))) problems.push('hubo errores fatales');
  verdict('A11', !problems.length, problems.map((p) => `R9: ${p}`));
}

/** R10: ZIP subido en Plantillas → App → importado con sus medios, más los negativos del token (CV-16). */
async function R10(s) {
  if (!kits.rest) { s.problems.push('Sin kit de A'); return; }
  await freshB();
  const auth = await adminSession('b');
  const list = await R.request('b', 'GET', '/wp-admin/edit.php?post_type=elementor_library', { jar: auth.jar });
  const form = (list.text.match(/<form[^>]*id="elementor-import-template-form"[\s\S]*?<\/form>/i) || [])[0];
  if (!form) { s.problems.push('La lista de plantillas no tiene el formulario de importación'); verdict('A11', false, 'R10 sin formulario'); return; }
  const fields = {};
  for (const m of form.matchAll(/<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"/g)) fields[m[1]] = m[2];
  const mp = R.multipart(fields, { file: { filename: 'kit-de-a.zip', data: fs.readFileSync(kits.rest) } });
  const res = await R.request('b', 'POST', '/wp-admin/admin-ajax.php', { body: mp.body, headers: { 'Content-Type': mp.type }, jar: auth.jar });
  const location = res.headers.location || (res.json && res.json.data && (res.json.data.redirect || res.json.data.url)) || res.text;
  const token = (String(location).match(/[#?&]id=([0-9a-f]{32,64})/) || [])[1];
  const problems = [];
  if (!token) { problems.push(`la biblioteca no redirigió al importador de kits con un token (${res.status})`); verdict('A11', false, problems); s.problems.push(...problems); return; }
  // Otro administrador no puede usar el token; el dueño sí, una sola vez.
  const other = await rest('b', { nonce: await R.login('b', 'admin', 'password', 'b-otro'), jar: 'b-otro', base: '' }, 'POST', 'upload', { referrer: 'digitalisimo', source: 'digitalisimo', kit_id: 'f'.repeat(token.length) }, { label: 'R10 token falso' });
  if (other.status === 200) problems.push('un token inventado se aceptó');
  const up = await rest('b', auth, 'POST', 'upload', { referrer: 'digitalisimo', source: 'digitalisimo', kit_id: token }, { label: 'R10 token' });
  if (up.status !== 200 || !up.json || !up.json.data || !up.json.data.session) problems.push(`el token de la biblioteca no se aceptó: ${up.status}`);
  const again = await rest('b', auth, 'POST', 'upload', { referrer: 'digitalisimo', source: 'digitalisimo', kit_id: token }, { label: 'R10 token usado' });
  if (again.status === 200) problems.push('un token ya usado se aceptó (CV-16)');
  if (up.status === 200 && up.json.data.session) {
    const run = await runImport('b', auth, up.json.data.session, { include: ['templates', 'content', 'settings', 'plugins'], customization: { settings: null, templates: null, content: null, plugins: null }, label: 'R10', id: token, referrer: 'digitalisimo' });
    if (!run.ok) problems.push(`importación: ${run.failed.join('; ')}`);
    const net = networkCheck('b', 'R10');
    if (net.toA.length) problems.push(`${net.toA.length} petición(es) al host de A: los medios no viajaron con el kit`);
  }
  verdict('A11', !problems.length, problems.map((p) => `R10: ${p}`));
  s.problems.push(...problems);
}

/** R13 (SLOW=1): 100 imágenes en un host lento; ninguna petición REST pasa de 25 s (A13, CV-23). */
async function R13(s) {
  await freshB();
  resetLogs('b');
  const run = await importApp('b', kitPath('tercero-lento.zip'), { label: 'R13' });
  const times = restTimes('b', 'R13');
  const problems = [];
  if (!run.ok) problems.push(`importación: ${run.message || run.failed.join('; ')}`);
  for (const e of times.slow) problems.push(`${e.route} ${e.runner} tardó ${e.ms} ms`);
  s.max_ms = times.max;
  verdict('A13', !problems.length, problems.map((p) => `R13: ${p}`).concat(`R13: petición más lenta ${times.max} ms`));
}

// ── Golden (KIT_GOLDEN=write) ─────────────────────────────────────────────────────────────────────────────────
function goldenFile(name) { return path.join(K, 'golden', `${name}.json`); }
function writeGolden(name, sig) {
  fs.mkdirSync(path.join(K, 'golden'), { recursive: true });
  const clean = JSON.parse(JSON.stringify(sig));
  fs.writeFileSync(goldenFile(name), JSON.stringify(clean, null, 1) + '\n');
  log(`   golden/${name}.json reescrito`);
}
function goldenCheck(name, sig, s) {
  if (!fs.existsSync(goldenFile(name))) { s.golden = 'sin golden (KIT_GOLDEN=write para crearlo)'; return; }
  const d = compare.compareSignatures(JSON.parse(fs.readFileSync(goldenFile(name), 'utf8')), sig, { known: [], scenario: name });
  s.golden = d.length ? `${d.length} diferencia(s) con golden/${name}.json` : 'igual que golden';
  if (d.length) s.problems.push(...d.slice(0, 10).map((x) => `golden: ${x}`));
}

/** RX (diagnóstico, no cuenta para A1–A14): importa KIT=<ZIP> en «agregar» sobre B vacío y anota runners y fatales. */
async function RX(s) {
  const file = process.env.KIT || '';
  if (!file || !fs.existsSync(file)) { s.problems.push('RX necesita KIT=<ruta de un ZIP>'); return; }
  await freshB();
  const run = await importApp('b', file, { label: 'RX' });
  s.kit = path.basename(file);
  s.runners = Object.fromEntries(Object.entries(run.results || {}).map(([k, v]) => [k, { status: v.status, ms: v.ms }]));
  if (!run.ok) s.problems.push(`${run.step || 'runner'}: ${run.message || (run.failed || []).join('; ')}`.slice(0, 600));
}

const RUN = { R1, R1c, R2, R3, R3b, R4, R4v4, R5, R6, R7, R8, R9, R10, R13, RX };

async function main() {
  compare = await import(pathToFileURL(path.join(K, 'compare.mjs')).href);
  build = await import(pathToFileURL(path.join(ROOT, 'tests/sources/kits/build.mjs')).href);
  knownAll = known();
  try { summary.sha = execFileSync('git', ['-C', ROOT, 'rev-parse', 'HEAD']).toString().trim(); summary.branch = execFileSync('git', ['-C', ROOT, 'rev-parse', '--abbrev-ref', 'HEAD']).toString().trim(); } catch (e) { /* sin git */ }
  // Qué código vieron los sitios: la copia fija ($OUT/src) con los archivos sin confirmar de ese momento, o el árbol vivo.
  summary.src = R.SRC === ROOT ? { live: true } : { live: false, dirty: (fs.existsSync(path.join(R.SRC, 'dirty.txt')) ? fs.readFileSync(path.join(R.SRC, 'dirty.txt'), 'utf8').split('\n').filter(Boolean) : []) };
  const started = Date.now();
  log(`kit-roundtrip: escenarios ${SCENARIOS.join(', ')} · núcleo ${CORE} · WOO=${WOO ? 1 : 0} · SLOW=${SLOW ? 1 : 0}${QUICK ? ' · QUICK' : ''}`);
  const unknown = SCENARIOS.filter((id) => !RUN[id]);
  if (unknown.length) throw new Error(`Escenarios desconocidos: ${unknown.join(', ')}`);

  const needsA = SCENARIOS.some((id) => !['R2', 'R3', 'R3b', 'R7', 'R9', 'R13', 'RX'].includes(id));
  if (needsA) {
    log('A: preparación');
    await R.startSite('a');
    fs.copyFileSync(path.join(OUT, 'a', 'refs.json'), path.join(SHARED, 'refs-a.json'));
    sigA = await signature('a', 'a', '');
    // F7a: A tiene lo que R1 y R4 comparan (presets sembrados, uno inerte, copias de máscara e índices).
    {
      const sane = [];
      const p = sigA.presets || {};
      if (Object.keys(p).length !== 4 || !(p['tipo-ausente'] || {}).inert) sane.push(`presets de A: ${JSON.stringify(Object.keys(p))}`);
      if (!maskCopies(sigA).length) sane.push('A no tiene copias masks/<sha8>.svg de su máscara del sitio');
      if (!at2(sigA, 'indexes', 'tracked_pages', 'union', 'length')) sane.push('A no tiene páginas rastreadas');
      if (!Object.keys(at2(sigA, 'indexes', 'tb_priority') || {}).length) sane.push('A no tiene prioridades manuales');
      verdict('A1', !sane.length, sane.map((x) => `preparación de A (F7a): ${x}`));
    }
    const controls = R.readJson('a', 'controls.json');
    const refsFile = path.join(OUT, 'a', 'references.json');
    if (fs.existsSync(refsFile)) {
      const inv = compare.checkInventory(controls, JSON.parse(fs.readFileSync(refsFile, 'utf8')), JSON.parse(fs.readFileSync(path.join(ROOT, 'tests/sources/kits/no-reference.json'), 'utf8')));
      summary.inventory = { problems: inv.length, ejemplos: inv.slice(0, 20) };
      verdict('A1', !inv.length, inv.slice(0, 20).map((p) => `inventario: ${p}`));
    } else {
      verdict('A1', false, 'inventario: sin references.json (el registro de referencias de K no cargó en A)');
    }
    // Como un sitio en uso: las páginas ya se vieron (CSS generado) antes de exportar.
    await warmUp('a');
    for (const slug of ['digi-k-padre', 'digi-k-hija', 'digi-k-cierre']) await R.request('a', 'GET', `/${slug}/`, { jar: '__control' }).catch(() => null);
    kits = await exportFromA();
    if (kits.restError) log(`   exportación REST: ${kits.restError}`);
    if (kits.cliError) log(`   exportación WP-CLI: ${kits.cliError}`);
    const files = Object.fromEntries(Object.entries({ rest: kits.rest, cli: kits.cli }).filter(([, f]) => f));
    if (Object.keys(files).length) {
      const ne = await notExportedCheck(files);
      summary.not_exported = ne;
      verdict('A1', !ne.length, ne);
    }
    // El mismo kit por REST y por WP-CLI (A1, «REST = WP-CLI» también al exportar): los mismos archivos y el mismo
    // JSON en cada uno. Sólo cambian la fecha del manifiesto y el WXR (fechas de exportación), que compara la firma.
    if (files.rest && files.cli) {
      const er = zipEntries(files.rest);
      const ec = zipEntries(files.cli);
      const problems = [];
      for (const name of [...new Set([...Object.keys(er), ...Object.keys(ec)])].sort()) {
        if (!(name in er) || !(name in ec)) { problems.push(`exportación REST ≠ WP-CLI: ${name} sólo en ${name in er ? 'REST' : 'WP-CLI'}`); continue; }
        if (!name.endsWith('.json') || name === 'manifest.json') continue;
        let a; let b;
        try { a = JSON.parse(er[name]); b = JSON.parse(ec[name]); } catch (e) { if (er[name] !== ec[name]) problems.push(`exportación REST ≠ WP-CLI: ${name}`); continue; }
        for (const d of compare.diff(a, b).slice(0, 3)) problems.push(`exportación REST ≠ WP-CLI: ${name} ${d.path}`);
      }
      verdict('A1', !problems.length, problems.slice(0, 20));
    }
    const net = networkCheck('a', 'exportación');
    if (net.bad.length) log(`   A: ${net.bad.length} petición(es) al proveedor durante la exportación`);
  }

  for (const id of SCENARIOS) {
    const s = { id, problems: [], started: new Date().toISOString() };
    const t0 = Date.now();
    log(`${id}: inicio`);
    try {
      await RUN[id](s);
    } catch (e) {
      s.problems.push(`excepción: ${String(e.stack || e.message || e).slice(0, 600)}`);
    }
    s.ms = Date.now() - t0;
    s.ok = !s.problems.length;
    summary.scenarios[id] = s;
    for (const site of ['b', 'net']) if (fs.existsSync(path.join(OUT, site, 'fatal.log'))) s.problems.push(`fatal.log en ${site}: ${fs.readFileSync(path.join(OUT, site, 'fatal.log'), 'utf8').slice(0, 400)}`);
    log(`${id}: ${s.problems.length ? 'FALLA' : 'ok'} en ${Math.round(s.ms / 1000)} s${s.problems.length ? '\n   - ' + s.problems.slice(0, 8).join('\n   - ') : ''}`);
  }
  await R.stopSite('b');
  await R.stopSite('a');
  if (browser) await browser.close().catch(() => {});

  // known.json sin usar (en los escenarios que corrieron) → falla.
  const unused = compare.unusedKnown(knownAll, SCENARIOS);
  if (unused.length) verdict('A1', false, unused.map((k) => `known.json sin usar: ${k.ruta} (${k.motivo})`));
  if (SCENARIOS.some((id) => ['R1', 'R2', 'R3', 'R4', 'R5', 'R8', 'R10'].includes(id))) {
    const times = { a: restTimes('a', 'A', { all: true }), b: restTimes('b', 'B', { all: true }), net: restTimes('net', 'red', { all: true }) };
    const slow = [...times.a.slow, ...times.b.slow, ...times.net.slow];
    verdict('A13', !slow.length, slow.map((e) => `${e.label || ''} ${e.route} ${e.runner} ${e.ms} ms`).concat(`petición REST más lenta: ${Math.max(times.a.max, times.b.max, times.net.max)} ms`));
  }
  summary.ms = Date.now() - started;
  fs.writeFileSync(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 1) + '\n');
  log('\nResultado (A1 a A14):');
  for (const [id, a] of Object.entries(summary.acceptance)) log(`  ${id} ${a.nombre}: ${a.estado}${a.estado === 'falla' ? ' · ' + a.detalle.slice(0, 3).join(' · ') : ''}`);
  const failed = Object.values(summary.scenarios).filter((s) => !s.ok).length + Object.values(summary.acceptance).filter((a) => a.estado === 'falla').length;
  return failed ? 1 : 0;
}

module.exports = { main };
