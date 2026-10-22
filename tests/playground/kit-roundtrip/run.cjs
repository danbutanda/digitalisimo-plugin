// Conductor de kit-roundtrip.sh (etapa K, §13.3). Arranca y detiene los sitios de Playground (A fijo; un B nuevo por
// escenario; la red sólo en R6), habla con ellos por HTTP con su host (a.test, b.test, playground.test) y cookies de
// sesión, usa el controlador de mu-kit.php y wp-cli dentro del sitio (cli.php), recorre las pantallas en Chrome y
// escribe $OUT/summary.json con el resultado de A1 a A14.
//
// Escenarios (§13.3): R1, R1c, R2, R3, R3b, R4, R4v4, R5, R6, R7, R8, R9, R10 y, con SLOW=1, R13 (A13).
// Todo lo que depende de otro frente de K (pantalla «Kits», WP-CLI propio, informe) se busca por su forma pública y, si
// falta, el escenario falla con el nombre del frente que debe entregarlo: el arnés no inventa atajos.
'use strict';
const fs = require('fs');
const path = require('path');
const http = require('http');
const crypto = require('crypto');
const { spawn, execFileSync } = require('child_process');
const { pathToFileURL } = require('url');

const OUT = process.env.OUT;
const ROOT = process.env.ROOT;
// Plugin y tema montados: la copia fija de la corrida ($OUT/src) o, con LIVE=1, el árbol de trabajo.
const SRC = process.env.SRC || ROOT;
const K = process.env.K || path.join(ROOT, 'tests/playground/kit-roundtrip');
const TOKEN = process.env.TOKEN;
const CLI = (process.env.PLAYGROUND_CLI || 'npx --yes @wp-playground/cli@3.1.56').split(/\s+/);
const CORE = process.env.CORE || 'bundled';
const WOO = process.env.WOO === '1';
const SLOW = process.env.SLOW === '1';
const QUICK = process.env.QUICK === '1';
const GOLDEN = process.env.KIT_GOLDEN === 'write';
const PREPARE = process.env.PREPARE || '';
const PORTS = { a: +process.env.PORT_A || 9461, b: +process.env.PORT_B || 9462, net: +process.env.PORT_NET || 9463 };
const HOSTS = { a: 'a.test', b: 'b.test', net: 'playground.test' };
const ALL = ['R1', 'R1c', 'R2', 'R3', 'R3b', 'R4', 'R4v4', 'R5', 'R6', 'R7', 'R8', 'R9', 'R10'].concat(SLOW ? ['R13'] : []);
const SCENARIOS = QUICK ? ['R1', 'R4', 'R9'] : (process.env.SCENARIOS ? process.env.SCENARIOS.split(',').map((s) => s.trim()).filter(Boolean) : ALL);
const REST = '/wp-json/elementor/v1/import-export-customization';
const REST_LIMIT_MS = 25000;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => { const line = a.join(' '); console.log(line); fs.appendFileSync(path.join(OUT, 'run.log'), line + '\n'); };

// ── Sitios de Playground ──────────────────────────────────────────────────────────────────────────────────────
const servers = {};
function blueprint(site, { network = false } = {}) {
  const steps = [];
  if (network) steps.push({ step: 'enableMultisite' });
  if (CORE === 'plugin') steps.push({ step: 'installPlugin', pluginData: { resource: 'wordpress.org/plugins', slug: 'elementor' }, options: { activate: false } });
  if (WOO) steps.push({ step: 'installPlugin', pluginData: { resource: 'wordpress.org/plugins', slug: 'woocommerce' }, options: { activate: false } });
  steps.push({ step: 'runPHP', code: "<?php require '/wordpress/wp-content/probe/activate.php';" });
  steps.push({ step: 'runPHP', code: `<?php require '/wordpress/wp-content/probe/${site === 'a' ? 'setup-a' : network ? 'setup-net' : 'setup-b'}.php';` });
  const file = path.join(OUT, site, 'blueprint.json');
  fs.writeFileSync(file, JSON.stringify({ steps }, null, 1));
  return file;
}

// `elements`: otra carpeta de DIGITALÍSIMO Elements para ese sitio (R4: la 4.3.0.163 publicada, OLD_ELEMENTS).
async function startSite(site, { network = false, fresh = true, elements = '' } = {}) {
  await stopSite(site);
  const dir = path.join(OUT, site);
  if (fresh) {
    // Un sitio nuevo empieza con los registros vacíos, pero los errores fatales y los tiempos REST del escenario
    // anterior se conservan para el final (fatal-all.log en kit-roundtrip.sh, rest-all.jsonl en A13).
    for (const [f, all] of [['fatal.log', 'fatal-all.log'], ['rest.jsonl', 'rest-all.jsonl']]) {
      const file = path.join(dir, f);
      if (fs.existsSync(file)) fs.appendFileSync(path.join(dir, all), fs.readFileSync(file));
    }
    for (const f of ['setup-error.txt', 'fatal.log', 'net.jsonl', 'rest.jsonl']) fs.rmSync(path.join(dir, f), { force: true });
  }
  const port = PORTS[site];
  const args = [...CLI.slice(1), 'server', `--port=${port}`, '--workers=1', `--site-url=http://${HOSTS[site]}`, `--blueprint=${blueprint(site, { network })}`,
    `--mount=${elements || path.join(SRC, 'digitalisimo-elements')}:/wordpress/wp-content/plugins/digitalisimo-elements`,
    `--mount=${path.join(SRC, 'digitalisimo-theme')}:/wordpress/wp-content/themes/digitalisimo-theme`,
    `--mount=${dir}:/wordpress/wp-content/probe`,
    `--mount=${path.join(OUT, 'shared')}:/wordpress/wp-content/digi-shared`];
  const logFile = fs.openSync(path.join(dir, 'server.log'), 'w');
  const child = spawn(CLI[0], args, { stdio: ['ignore', logFile, logFile], detached: true, env: process.env });
  servers[site] = child;
  const started = Date.now();
  for (;;) {
    await sleep(3000);
    if (child.exitCode !== null) throw new Error(`Playground de ${site} terminó: ${tail(path.join(dir, 'server.log'))}`);
    if (fs.existsSync(path.join(dir, 'setup-error.txt'))) throw new Error(`Preparación de ${site} incompleta: ${fs.readFileSync(path.join(dir, 'setup-error.txt'), 'utf8').trim()}`);
    const ping = await control(site, 'ping').catch(() => null);
    if (ping && ping.ready > 0) {
      if (ping.errors) throw new Error(`Preparación de ${site} incompleta: ${ping.errors.trim()}`);
      log(`   ${site}: listo en ${Math.round((Date.now() - started) / 1000)} s (núcleo ${ping.core}, K ${ping.k ? 'cargado' : 'ausente'})`);
      return ping;
    }
    if (Date.now() - started > 600000) throw new Error(`El sitio ${site} no quedó listo en 10 min: ${tail(path.join(dir, 'server.log'))}`);
  }
}

async function stopSite(site) {
  const child = servers[site];
  if (child && child.exitCode === null) {
    try { process.kill(-child.pid, 'SIGTERM'); } catch (e) { /* ya terminó */ }
    for (let i = 0; i < 20 && child.exitCode === null; i++) await sleep(250);
  }
  delete servers[site];
  try { execFileSync('fuser', ['-k', '-n', 'tcp', String(PORTS[site])], { stdio: 'ignore' }); } catch (e) { /* libre */ }
  jars[site] = {};
}
process.on('exit', () => { for (const child of Object.values(servers)) { try { process.kill(-child.pid, 'SIGKILL'); } catch (e) { /* nada */ } } });
const tail = (file) => { try { return fs.readFileSync(file, 'utf8').split('\n').slice(-12).join('\n'); } catch (e) { return ''; } };

// ── HTTP con host y cookies ───────────────────────────────────────────────────────────────────────────────────
const jars = { a: {}, b: {}, net: {} };
function request(site, method, url, { body = null, headers = {}, jar = site, label = '' } = {}) {
  const u = new URL(url, `http://${HOSTS[site]}`);
  const cookies = Object.entries(jars[jar] || {}).map(([k, v]) => `${k}=${v}`).join('; ');
  const h = { Host: HOSTS[site], ...(cookies ? { Cookie: cookies } : {}), ...(label ? { 'X-Digi-K-Label': label } : {}), ...headers };
  if (body !== null && !Buffer.isBuffer(body)) body = Buffer.from(typeof body === 'string' ? body : JSON.stringify(body));
  if (body !== null) h['Content-Length'] = body.length;
  const started = Date.now();
  return new Promise((resolve, reject) => {
    const req = http.request({ host: '127.0.0.1', port: PORTS[site], method, path: u.pathname + u.search, headers: h, timeout: 600000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        for (const c of [].concat(res.headers['set-cookie'] || [])) {
          const [pair] = c.split(';');
          const i = pair.indexOf('=');
          const name = pair.slice(0, i).trim();
          const value = pair.slice(i + 1).trim();
          if (!jars[jar]) jars[jar] = {};
          if (/expires=Thu, 01[- ]Jan[- ]1970/i.test(c) || value === 'deleted') delete jars[jar][name]; else jars[jar][name] = value;
        }
        const buffer = Buffer.concat(chunks);
        let json = null;
        try { json = JSON.parse(buffer.toString('utf8')); } catch (e) { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, body: buffer, text: buffer.toString('utf8'), json, ms: Date.now() - started });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${url}`)));
    if (body !== null) req.write(body);
    req.end();
  });
}

function multipart(fields, files) {
  const boundary = '----digik' + crypto.randomBytes(8).toString('hex');
  const parts = [];
  for (const [name, value] of Object.entries(fields)) {
    for (const v of [].concat(value)) parts.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${name}"\r\n\r\n${v}\r\n`));
  }
  for (const [name, file] of Object.entries(files)) {
    parts.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${name}"; filename="${file.filename}"\r\nContent-Type: ${file.type || 'application/zip'}\r\n\r\n`));
    parts.push(file.data);
    parts.push(Buffer.from('\r\n'));
  }
  parts.push(Buffer.from(`--${boundary}--\r\n`));
  return { body: Buffer.concat(parts), type: `multipart/form-data; boundary=${boundary}` };
}

// Sesión de un usuario: cookies en su propio tarro y el nonce REST del sitio (`base` = '/sub' en un subsitio de la red).
async function login(site, user = 'admin', pass = 'password', jar = site, base = '') {
  jars[jar] = {};
  await request(site, 'GET', '/wp-login.php', { jar });
  const form = new URLSearchParams({ log: user, pwd: pass, 'wp-submit': 'Acceder', testcookie: '1', redirect_to: `${base}/wp-admin/` }).toString();
  jars[jar].wordpress_test_cookie = 'WP%20Cookie%20check';
  const res = await request(site, 'POST', '/wp-login.php', { jar, body: form, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
  if (res.status !== 302) throw new Error(`No se pudo iniciar sesión en ${site} como ${user} (${res.status})`);
  const nonce = await request(site, 'GET', `${base}/wp-admin/admin-ajax.php?action=rest-nonce`, { jar });
  if (nonce.status !== 200 || !/^[0-9a-f]{10}$/.test(nonce.text.trim())) throw new Error(`Sin nonce REST en ${site} (${nonce.status})`);
  return nonce.text.trim();
}

async function control(site, op, params = {}, base = '') {
  const q = new URLSearchParams({ 'digi-kit': op, token: TOKEN, ...params }).toString();
  const res = await request(site, 'GET', `${base}/?${q}`, { jar: '__control' });
  if (res.status !== 200 || !res.json) throw new Error(`Controlador ${op} en ${site}: ${res.status} ${res.text.slice(0, 200)}`);
  return res.json;
}
const readJson = (site, file) => JSON.parse(fs.readFileSync(path.join(OUT, site, file), 'utf8'));

async function wpcli(site, args) {
  const q = new URLSearchParams({ token: TOKEN, args: JSON.stringify(args) }).toString();
  const res = await request(site, 'GET', `/wp-content/probe/cli.php?${q}`, { jar: '__control' });
  const out = res.json || { stdout: '', stderr: res.text, fatal: '' };
  out.status = res.status;
  out.ok = res.status === 200 && !out.fatal && !/^Error:/m.test(out.stderr || '');
  return out;
}

module.exports = { startSite, stopSite, request, multipart, login, control, wpcli, readJson, log, sleep, jars, PORTS, HOSTS, REST, OUT, ROOT, SRC, K, TOKEN, CORE, WOO, SLOW, QUICK, GOLDEN, SCENARIOS, REST_LIMIT_MS };

// ── Preparación sola (PREPARE=a|b) ────────────────────────────────────────────────────────────────────────────
async function prepareOnly() {
  await startSite(PREPARE);
  const ids = PREPARE === 'a' ? readJson('a', 'ids.json') : {};
  log(`Sitio ${PREPARE} preparado en http://${HOSTS[PREPARE]} (puerto ${PORTS[PREPARE]}); ${Object.keys(ids).length} objetos creados. Ctrl+C para salir.`);
  await new Promise(() => {});
}

if (require.main === module) {
  (async () => {
    if (PREPARE) return prepareOnly();
    const { main } = require('./scenarios.cjs');
    process.exitCode = await main();
  })().catch((e) => { log('FALLO:', e.stack || e.message); process.exitCode = 1; });
}
