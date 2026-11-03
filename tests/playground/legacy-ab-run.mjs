// Fases de legacy-ab.sh por HTTP (red de Playground en modo servidor) y, entre ellas, la firma de estilos computados de
// las partes mapeadas (F8, P10; frente B): Chrome abre la página de cada caso y `collectStyles()` (legacy-compare.mjs)
// guarda el estilo de cada parte de `classes` del mapa: la clase de Element Pack en la fase A y la propia en B, C y E.
// legacy-compare.mjs compara después A con B y con C. Deja $OUT/run.json con cada paso, su duración y sus problemas.
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import http from 'node:http';
import { stylePairs, collectStyles, STYLE_PROPS } from './legacy-compare.mjs';

const require = createRequire(import.meta.url);
const OUT = process.env.OUT;
const PORT = Number(process.env.PORT);
const HOST = 'playground.test';
const TOKEN = readFileSync(`${OUT}/token.txt`, 'utf8').trim();
const ONLY = process.env.ONLY || '';
const BLOG = process.env.BLOG || '';
const STYLES = process.env.STYLES !== '0';
const STYLE_ONLY = (process.env.STYLE_ONLY || 'bdt-trailer-box,bdt-animated-card,bdt-interactive-card').split(',').map((x) => x.trim()).filter(Boolean);
// La firma de estilos se mide en el sitio principal (el subsitio repite el mismo mapa y dobla el tiempo).
const STYLE_BLOGS = (process.env.STYLE_BLOGS || '1').split(',');
const MIGRATION_DIR = process.env.MIGRATION_DIR;
const run = { started: new Date().toISOString(), steps: [], styles: {}, problems: [] };
const T0 = Date.now();
const log = (m) => console.log(`[${String(Math.round((Date.now() - T0) / 1000)).padStart(4)} s] ${m}`);
const save = () => writeFileSync(`${OUT}/run.json`, JSON.stringify(run, null, 1));

function get(path, timeout = 590000) {
  return new Promise((resolve, reject) => {
    const req = http.request({ host: '127.0.0.1', port: PORT, path, method: 'GET', headers: { Host: HOST }, timeout }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => resolve({ status: res.statusCode, text: Buffer.concat(chunks).toString('utf8') }));
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${path}`)));
    req.end();
  });
}

const q = (o) => new URLSearchParams({ token: TOKEN, ...o }).toString();
async function phase(id) {
  const t0 = Date.now();
  const r = await get(`/wp-content/digi-tests/legacy-ab.php?${q({ phase: id, only: ONLY, blog: BLOG })}`);
  const file = `${OUT}/phase${id}.json`;
  const fatal = existsSync(`${OUT}/fatal-${id}.txt`) ? readFileSync(`${OUT}/fatal-${id}.txt`, 'utf8') : '';
  const ok = r.status === 200 && existsSync(file) && !fatal;
  run.steps.push({ step: `fase ${id}`, ok, http: r.status, ms: Date.now() - t0, fatal: fatal.slice(0, 600), out: r.text.slice(0, 600) });
  log(`fase ${id}: ${ok ? 'ok' : 'FALLA'} (${r.status}) en ${Math.round((Date.now() - t0) / 1000)} s`);
  if (!ok) throw new Error(`la fase ${id} no terminó: ${r.status} ${fatal || r.text.slice(0, 300)}`);
}
async function toggle(plugin, activate) {
  const t0 = Date.now();
  const r = await get(`/wp-content/digi-tests/legacy-deactivate.php?${q({ plugin, activate: activate ? '1' : '0' })}`);
  let json = null;
  try { json = JSON.parse(r.text); } catch (e) { /* */ }
  const ok = r.status === 200 && json && json.ok;
  run.steps.push({ step: `${activate ? 'activar' : 'desactivar'} ${plugin}`, ok, http: r.status, ms: Date.now() - t0, network: json && json.network });
  log(`${activate ? 'activar' : 'desactivar'} ${plugin}: ${ok ? 'ok' : 'FALLA'}`);
  if (!ok) throw new Error(`no se pudo ${activate ? 'activar' : 'desactivar'} ${plugin}: ${r.status} ${r.text.slice(0, 300)}`);
}

let browser = null;
async function styles(id, side) {
  if (!STYLES) return;
  const t0 = Date.now();
  const puppeteer = require('puppeteer-core');
  browser = browser || await puppeteer.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', '--font-render-hinting=none', `--host-resolver-rules=MAP ${HOST} 127.0.0.1:${PORT}, MAP * ~NOTFOUND`] });
  const file = `${OUT}/phase${id}.json`;
  const data = JSON.parse(readFileSync(file, 'utf8'));
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e).slice(0, 200)));
  let measured = 0;
  for (const [widget, cases] of Object.entries(data.widgets || {})) {
    if (!(STYLE_ONLY.includes('all') || STYLE_ONLY.includes(widget))) continue;
    const map = `${MIGRATION_DIR}/legacy/maps/${widget}.php`;
    const pairs = existsSync(map) ? stylePairs(readFileSync(map, 'utf8')) : [];
    if (!pairs.length) continue;
    for (const [name, blogs] of Object.entries(cases)) {
      for (const [blog, entry] of Object.entries(blogs)) {
        if (!STYLE_BLOGS.includes(String(blog)) || !entry.url) continue;
        const res = await page.goto(entry.url, { waitUntil: 'networkidle0', timeout: 120000 }).catch((e) => ({ status: () => `error ${e.message}` }));
        await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
        entry.signature.styles = await page.evaluate(collectStyles, pairs, side, STYLE_PROPS);
        entry.styles_http = res && res.status ? res.status() : 0;
        const found = Object.values(entry.signature.styles).filter(Boolean).length;
        (run.styles[`${widget}·${name}`] = run.styles[`${widget}·${name}`] || {})[id] = { parts: pairs.length, found, http: entry.styles_http };
        measured++;
      }
    }
  }
  await ctx.close();
  // Fase A: sólo cuenta lo que el caso cambia frente al caso `defaults` del mismo widget en Element Pack (lo que el
  // mapa debe trasladar). El estilo por defecto de cada widget es propio de su diseño y no se compara: el caso
  // `defaults` queda vacío y, sin él, el widget no tiene línea base (`styles_full` guarda la medida completa).
  if (side === 'legacy') {
    for (const [widget, cases] of Object.entries(data.widgets || {})) {
      for (const [name, blogs] of Object.entries(cases)) {
        for (const [blog, entry] of Object.entries(blogs)) {
          if (!entry.signature || !entry.signature.styles) continue;
          // Línea base: `<caso>-base` si existe (casos de estilo de P10, f8/legacy-styled/) o `defaults`.
          const baseCase = cases[`${name}-base`] ? `${name}-base` : 'defaults';
          const ref0 = cases[baseCase] && cases[baseCase][blog];
          const base = ref0 && ref0.signature && (ref0.styles_full || ref0.signature.styles);
          entry.styles_full = entry.styles_full || entry.signature.styles;
          if (name === 'defaults' || /-base$/.test(name) || !base) { entry.signature.styles = {}; continue; }
          const delta = {};
          for (const [part, values] of Object.entries(entry.styles_full)) {
            // Una parte que el caso `defaults` no pinta no tiene línea base: no se compara (queda como «sin la parte»).
            const ref = base[part];
            if (!values || !ref) { delta[part] = null; continue; }
            const changed = Object.fromEntries(Object.entries(values).filter(([prop, value]) => ref[prop] !== value));
            if (Object.keys(changed).length) delta[part] = changed;
          }
          entry.signature.styles = delta;
        }
      }
    }
  }
  writeFileSync(file, JSON.stringify(data, null, 1));
  run.steps.push({ step: `estilos ${id} (${side})`, ok: !errors.length, pages: measured, ms: Date.now() - t0, errors: errors.slice(0, 5) });
  log(`estilos ${id}: ${measured} páginas en ${Math.round((Date.now() - t0) / 1000)} s${errors.length ? ` · errores de JavaScript: ${errors.slice(0, 2).join(' | ')}` : ''}`);
}

try {
  await phase('A');
  await styles('A', 'legacy');
  await toggle('ep', false);
  await phase('B');
  await styles('B', 'own');
  await phase('C');
  await styles('C', 'own');
  await toggle('migration', false);
  await phase('E');
  await styles('E', 'own');
  await toggle('migration', true);
  await phase('D');
  run.ok = true;
} catch (e) {
  run.ok = false;
  run.problems.push(String(e.stack || e).slice(0, 1500));
  log(`FALLA: ${e.message}`);
} finally {
  if (browser) await browser.close().catch(() => {});
  run.duration_s = Math.round((Date.now() - T0) / 1000);
  save();
}
process.exit(run.ok ? 0 : 1);
