// Pantalla «interfaz simple» de DIGITALÍSIMO Backups (1.0.74) en Chrome real, sin interfaz: tests/backups-simple-screen.php
// pinta cada pestaña con el código del plugin (--fixture) y aquí se abren con su CSS y su JavaScript a 1 280 y 390 px:
//   - nada se sale de la pantalla (sin desplazamiento horizontal);
//   - «Respaldos» cabe en 1 000 px con la barra de WordPress, ≤ 150 palabras visibles, ningún texto de más de 140
//     caracteres, y Restaurar, Descargar y Borrar de cada fila se ven dentro de la tabla (también en el teléfono);
//   - «Restaurar» abre su panel bajo la fila con el foco en la confirmación y «Cancelar» lo cierra;
//   - «Ajustes», «Destinos», «Herramientas» y el subsitio cumplen sus límites de palabras y de texto.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH, PUPPETEER_PATH (carpeta node_modules) y PHP_BIN
// cambian las rutas.
// Uso: timeout 300 node tests/backups-simple-screen-chrome.mjs
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readdirSync, rmSync } from 'node:fs';
import { tmpdir, homedir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const modules = (process.env.PUPPETEER_PATH || '').replace(/\/$/, '') || (() => {
  const base = join(homedir(), '.npm/_npx');
  try { for (const dir of readdirSync(base)) if (existsSync(join(base, dir, 'node_modules/puppeteer-core/package.json'))) return join(base, dir, 'node_modules'); } catch { /* sin caché de npx */ }
  return '';
})();
const pptr = modules ? join(modules, 'puppeteer-core/lib/puppeteer/puppeteer-core.js') : '';
if (!existsSync(chrome) || !pptr || !existsSync(pptr)) {
  console.log(`AVISO: se salta la prueba en Chrome de la pantalla de Backups (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const out = mkdtempSync(join(tmpdir(), 'digi-bk-screen-'));
const php = spawnSync(process.env.PHP_BIN || 'php', ['tests/backups-simple-screen.php', '--fixture', out], { encoding: 'utf8' });
assert.ok(php.status === 0 && !/Fatal error|Uncaught/.test(php.stdout + php.stderr), 'La prueba PHP de la pantalla no pintó las pantallas: ' + (php.stdout + php.stderr).slice(-1500));
const { default: puppeteer } = await import(pathToFileURL(pptr).href);
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu', '--host-resolver-rules=MAP * ~NOTFOUND'] });
const errors = [];

/** Lo que se ve en .wrap: palabras, el texto más largo (bloques sin otros bloques dentro) y campos. */
function measure() {
  const root = document.querySelector('.wrap');
  // checkVisibility(): también lo de un <details> cerrado (content-visibility: hidden) cuenta como no visible.
  const visible = (el) => { if (typeof el.checkVisibility === 'function' && !el.checkVisibility({ visibilityProperty: true })) return false; const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none'; };
  const clean = (t) => String(t || '').replace(/\s+/g, ' ').trim();
  const leaf = (e) => !e.querySelector('p, div, ul, ol, table, form, details, section, fieldset, dl');
  const texts = [...root.querySelectorAll('p, li, label, td, th, h1, h2, h3, summary, small, legend, dd, dt, .description')].filter(visible).filter(leaf).map((e) => clean(e.innerText)).filter(Boolean);
  const longest = texts.sort((a, b) => b.length - a.length)[0] || '';
  return {
    words: (clean(root.innerText).match(/[\p{L}\p{N}][\p{L}\p{N}'’._-]*/gu) || []).length,
    longest, longestLength: longest.length,
    fields: [...root.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea')].filter(visible).length,
    height: Math.ceil(document.documentElement.scrollHeight),
    overflow: document.documentElement.scrollWidth - window.innerWidth,
  };
}
async function open(name, width) {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(`${name}@${width}: ${e.message}`));
  await page.setViewport({ width, height: 900 });
  await page.goto(pathToFileURL(join(out, `${name}.html`)).href, { waitUntil: 'load' });
  return page;
}

try {
  const limits = { respaldos: 150, ajustes: 250, destinos: 80, herramientas: 120, subsitio: 40, 'red-respaldos': 150 };
  for (const [name, words] of Object.entries(limits)) {
    for (const width of [1280, 390]) {
      const page = await open(name, width);
      const m = await page.evaluate(measure);
      ok(m.overflow <= 0, `${name} a ${width} px se sale ${m.overflow} px de la pantalla.`);
      ok(m.longestLength <= 140, `${name} a ${width} px tiene un texto de ${m.longestLength} caracteres: ${m.longest}`);
      if (width === 1280) ok(m.words <= words, `${name} tiene ${m.words} palabras visibles (máximo ${words}).`);
      if (name === 'respaldos' || name === 'red-respaldos') {
        ok(m.fields === 0, `${name} a ${width} px enseña ${m.fields} campos.`);
        // La barra de WordPress (32 px) va en el relleno de #wpcontent; el pie de WordPress suma unos 60 px.
        if (width === 1280) ok(m.height + 60 <= 1000, `${name} mide ${m.height} px (+60 del pie): más de 1 000.`);
        const rows = await page.evaluate(() => [...document.querySelectorAll('.digitalisimo-backups-row')].map((row) => {
          const table = row.closest('table').getBoundingClientRect();
          return [...row.querySelectorAll('.digitalisimo-backups-actions > a.button, .digitalisimo-backups-actions > form .button')].map((b) => { const r = b.getBoundingClientRect(); return { text: b.textContent.trim(), inside: r.width > 0 && r.left >= table.left - 1 && r.right <= table.right + 1 && r.right <= window.innerWidth }; });
        }));
        ok(rows.length === 2 && rows.every((buttons) => ['Restaurar', 'Descargar', 'Borrar'].every((text) => buttons.some((b) => b.text === text && b.inside))), `${name} a ${width} px: Restaurar, Descargar y Borrar no se ven en cada fila: ${JSON.stringify(rows)}`);
      }
      await page.close();
    }
  }
  // «Restaurar» abre su panel bajo la fila, con el foco en la confirmación; «Cancelar» lo cierra.
  const page = await open('respaldos', 1280);
  const first = await page.$('[data-restore-toggle]');
  await first.click();
  const opened = await page.evaluate(() => { const link = document.querySelector('[data-restore-toggle]'); const row = document.getElementById(link.getAttribute('data-restore-toggle')); return { visible: !row.hidden && row.getBoundingClientRect().height > 0, expanded: link.getAttribute('aria-expanded'), focus: document.activeElement && document.activeElement.name, below: row.previousElementSibling === link.closest('tr') }; });
  ok(opened.visible && opened.expanded === 'true' && opened.focus === 'confirmation' && opened.below, '«Restaurar» no abrió su panel bajo la fila con el foco en la confirmación: ' + JSON.stringify(opened));
  const m = await page.evaluate(measure);
  ok(m.fields === 1 && m.longestLength <= 140, `Con el panel abierto se ven ${m.fields} campos o un texto largo: ${m.longest}`);
  await page.click('[data-restore-cancel]');
  ok(await page.evaluate(() => document.querySelector('.digitalisimo-backups-restore-row').hidden && document.activeElement.hasAttribute('data-restore-toggle')), '«Cancelar» no cerró el panel ni devolvió el foco a «Restaurar».');
  // «⋯» abre los detalles y «Bloquear» sin salirse de la pantalla.
  await page.click('.digitalisimo-backups-more > summary');
  const menu = await page.evaluate(() => { const r = document.querySelector('.digitalisimo-backups-more[open] .digitalisimo-backups-more-menu').getBoundingClientRect(); return { left: r.left, right: r.right, width: window.innerWidth }; });
  ok(menu.left >= 0 && menu.right <= menu.width, '«⋯» se sale de la pantalla: ' + JSON.stringify(menu));
  await page.close();
  ok(errors.length === 0, 'Errores de JavaScript: ' + errors.join(' | '));
} finally {
  await browser.close();
  rmSync(out, { recursive: true, force: true });
}
console.log(`DIGITALÍSIMO Backups: la pantalla simple cabe a 1 280 y 390 px, con textos cortos y las acciones de cada fila a la vista (${checks} comprobaciones en Chrome).`);
