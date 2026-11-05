// Código resaltado (digitalisimo-code-highlight, C2 frente A de docs/pro/c2.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-code-highlight.html, que es el marcado de Code_Highlight::render() (lo comprueba
// tests/elements-pro-code-highlight.php), servido por un servidor local de la carpeta del repositorio:
// - sin JavaScript: el código se lee tal cual (escapado en <code>) y el botón Copiar no se ve;
// - con JavaScript: sólo los bloques cerca de la pantalla cargan Prism del plugin (núcleo, gramáticas, espacios en
//   blanco y líneas resaltadas); el bloque lejano, con números de línea, no pide su complemento hasta acercarse; ninguna
//   petición sale del sitio; tokens con los colores del tema, sangría común quitada, líneas resaltadas en su sitio;
// - botón Copiar: aparece con el puntero o el foco, 24 × 24 px como mínimo, foco visible de 2 px, copia el código sin
//   las líneas resaltadas ni los números, anuncia «Copiado»; movimiento reducido sin transición;
// - editor: `frontend/element_ready/global` inicia un bloque nuevo.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-code-highlight-chrome.mjs
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del Código resaltado (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const root = fileURLToPath(new URL('..', import.meta.url));
const types = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css' };
const server = createServer((req, res) => {
  if (req.url === '/favicon.ico') { res.writeHead(204); res.end(); return; }
  const file = normalize(join(root, decodeURIComponent(new URL(req.url, 'http://x').pathname)));
  if (!file.startsWith(root) || !existsSync(file) || !statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
  res.writeHead(200, { 'content-type': types[extname(file)] || 'application/octet-stream' });
  res.end(readFileSync(file));
});
await new Promise(r => server.listen(0, '127.0.0.1', r));
const origin = `http://127.0.0.1:${server.address().port}`;
const url = `${origin}/tests/fixtures/pro-code-highlight.html`;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
await browser.defaultBrowserContext().overridePermissions(origin, ['clipboard-read', 'clipboard-write', 'clipboard-sanitized-write']);
const errors = [];
async function open({ js = true, reduce = false, width = 1280 } = {}) {
  const page = await browser.newPage();
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setJavaScriptEnabled(js);
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 800 });
  await page.goto(url, { waitUntil: 'load' });
  if (js) await page.waitForFunction(() => document.querySelectorAll('[data-id="top"] code .token').length > 3 && document.querySelector('[data-id="lines"] .line-highlight'), { timeout: 8000 });
  return { page, requests };
}
const prism = list => list.filter(u => u.includes('/assets/lib/prism/')).map(u => u.split('/assets/lib/prism/')[1]).sort();

try {
  // 1. Sin JavaScript.
  {
    const { page } = await open({ js: false });
    const s = await page.evaluate(() => ({
      text: document.querySelector('[data-id="lines"] code').textContent,
      tokens: document.querySelectorAll('.token').length,
      copy: [...document.querySelectorAll('.digi-code__copy')].map(b => getComputedStyle(b).display),
      bg: getComputedStyle(document.querySelector('[data-id="lines"] pre')).backgroundColor,
    }));
    ok(s.text.startsWith('<?php\n$a = 1 < 2 && 3 > 2;\necho "<p>&amp;</p>";') && 0 === s.tokens, 'Sin JavaScript el código se lee tal cual (también &amp;) y sin resaltar.');
    ok(s.copy.every(d => d === 'none'), 'Sin JavaScript el botón Copiar no se ve.');
    ok(s.bg === 'rgb(39, 40, 34)', 'La paleta del tema ya viene del servidor: ' + s.bg);
    await page.close();
  }

  // 2. Con JavaScript: carga diferida, complementos según el bloque, resaltado.
  {
    const { page, requests } = await open();
    await sleep(500);
    const loaded = prism(requests);
    ok(loaded.includes('components/prism-core.min.js') && loaded.includes('plugins/autoloader/prism-autoloader.min.js') && loaded.includes('plugins/normalize-whitespace/prism-normalize-whitespace.min.js') && loaded.includes('plugins/line-highlight/prism-line-highlight.min.js'), 'Núcleo, gramáticas, espacios y líneas resaltadas del plugin: ' + loaded.join(', '));
    ok(loaded.includes('components/prism-php.min.js') && loaded.includes('components/prism-markup-templating.min.js') && loaded.includes('components/prism-javascript.min.js'), 'Las gramáticas de los bloques cercanos (con sus dependencias) desde components/.');
    ok(!loaded.some(u => /line-numbers|toolbar|clipboard/.test(u)), 'Sin números de línea, barra ni clipboard.js mientras el bloque que los usa está lejos.');
    ok(requests.every(u => u.startsWith(origin)), 'Ninguna petición sale del sitio: ' + requests.filter(u => !u.startsWith(origin)).join(', '));
    ok(1 === requests.filter(u => u.endsWith('prism-core.min.js')).length, 'El núcleo se pide una sola vez para varios bloques.');
    const s = await page.evaluate(() => {
      const c = s => getComputedStyle(document.querySelector(s)).color;
      return {
        top: document.querySelector('[data-id="top"] code').textContent,
        comment: c('[data-id="top"] .token.comment'), keyword: c('[data-id="top"] .token.keyword'), regex: c('[data-id="top"] .token.regex'),
        php: c('[data-id="lines"] .token.variable'), delim: c('[data-id="lines"] .token.delimiter'),
        far: document.querySelectorAll('[data-id="far"] .token').length,
        hl: [...document.querySelectorAll('[data-id="lines"] .line-highlight')].map(h => [h.dataset.start, h.dataset.end || '', Math.round(h.getBoundingClientRect().top - h.closest('pre').getBoundingClientRect().top), Math.round(h.getBoundingClientRect().height)].join(':')),
      };
    });
    ok(s.top === "// Saludo\nconst re = /a+b/g;\nfunction f( a ) {\n\treturn 'x' + a;\n}", 'La sangría común se quita como en PRO (espacios en blanco de Prism): ' + JSON.stringify(s.top));
    ok(s.comment === 'rgb(112, 128, 144)' && s.keyword === 'rgb(0, 119, 170)' && s.regex === 'rgb(238, 153, 0)', 'Tokens con los colores del tema claro de PRO.');
    ok(s.php === 'rgb(248, 248, 242)' && s.delim !== '', 'PHP resaltado con su gramática en Okaidia.');
    ok(0 === s.far, 'El bloque lejano no se resalta todavía.');
    ok(JSON.stringify(s.hl) === JSON.stringify(['2::40:24', '4:5:88:48']), 'Líneas 2 y 4-5 resaltadas en su sitio: ' + JSON.stringify(s.hl));
    await page.evaluate(() => document.querySelector('[data-id="far"]').scrollIntoView());
    await page.waitForFunction(() => document.querySelector('[data-id="far"] .line-numbers-rows'), { timeout: 8000 });
    const far = await page.evaluate(() => ({
      rows: document.querySelectorAll('[data-id="far"] .line-numbers-rows>span').length,
      pad: getComputedStyle(document.querySelector('[data-id="far"] pre')).paddingLeft,
      hl: document.querySelectorAll('[data-id="far"] .line-highlight').length,
      before: getComputedStyle(document.querySelector('[data-id="far"] .line-highlight'), '::before').content,
      border: getComputedStyle(document.querySelector('[data-id="far"] .line-highlight')).borderTopStyle,
    }));
    ok(prism(requests).includes('plugins/line-numbers/prism-line-numbers.min.js'), 'Al acercarse el bloque con números de línea se pide su complemento.');
    ok(3 === far.rows && far.pad === '60.8px' && 1 === far.hl && far.before === 'none' && far.border === 'dashed', 'Números de línea (3), relleno de 3,8 em y línea resaltada de Twilight sin su etiqueta: ' + JSON.stringify(far));

    // 3. Botón Copiar.
    const btn = await page.evaluate(() => { const b = document.querySelector('[data-id="top"] .digi-code__copy'); const r = b.getBoundingClientRect(); return { hidden: b.hidden, w: r.width, h: r.height, opacity: getComputedStyle(b).opacity, type: b.type, text: b.textContent }; });
    ok(!btn.hidden && btn.w >= 24 && btn.h >= 24 && btn.opacity === '0' && btn.type === 'button' && btn.text === 'Copiar', 'Botón Copiar activado por el script, de 24 × 24 px como mínimo, oculto hasta el puntero o el foco: ' + JSON.stringify(btn));
    ok(null === await page.$('[data-id="lines"] .digi-code__copy'), 'Sin «Copiar» activado, no hay botón.');
    await page.evaluate(() => { window.scrollTo(0, 0); document.activeElement && document.activeElement.blur(); });
    await page.mouse.click(2, 2); // punto de partida del orden de foco: el principio de la página
    await page.keyboard.press('Tab');
    await sleep(400);
    const focus = await page.evaluate(() => { const b = document.activeElement; const cs = getComputedStyle(b); return { cls: b.className, id: b.closest('[data-id]')?.dataset.id, opacity: cs.opacity, outline: cs.outlineStyle + ' ' + cs.outlineWidth, offset: cs.outlineOffset }; });
    ok(focus.cls === 'digi-code__copy' && focus.id === 'top' && focus.opacity === '1' && focus.outline === 'solid 2px' && focus.offset === '2px', 'Con el teclado el botón se ve, con foco de 2 px separado 2 px: ' + JSON.stringify(focus));
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('[data-id="top"] .digi-code__copy').textContent === 'Copiado', { timeout: 3000 });
    const copied = await page.evaluate(() => navigator.clipboard.readText());
    ok(copied === "// Saludo\nconst re = /a+b/g;\nfunction f( a ) {\n\treturn 'x' + a;\n}", 'Copia el código normalizado: ' + JSON.stringify(copied));
    ok('polite' === await page.evaluate(() => document.querySelector('[data-id="top"] .digi-code__copy').getAttribute('aria-live')), 'El cambio a «Copiado» se anuncia.');
    await page.click('[data-id="far"] .digi-code__copy');
    await page.waitForFunction(() => document.querySelector('[data-id="far"] .digi-code__copy').textContent === 'Copiado', { timeout: 3000 });
    ok('const a = 1;\nconst b = 2;\nconst c = a + b;' === await page.evaluate(() => navigator.clipboard.readText()), 'Copia sin el texto de las líneas resaltadas ni los números de línea.');
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
    await page.mouse.move(0, 0);
    await page.hover('[data-id="lines"] pre');
    await sleep(500);
    ok('0' === await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="top"] .digi-code__copy')).opacity), 'El botón de un bloque sólo aparece con el puntero sobre su bloque.');
    await page.hover('[data-id="top"] pre');
    await sleep(500);
    ok('1' === await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="top"] .digi-code__copy')).opacity), 'Con el puntero encima del bloque, el botón aparece (como la barra de PRO).');

    // 4. Editor: un bloque nuevo se inicia con frontend/element_ready/global.
    const editor = await page.evaluate(async () => {
      let hook = null;
      window.elementorFrontend = { hooks: { addAction: (n, f) => { hook = [n, f]; } } };
      window.dispatchEvent(new Event('elementor/frontend/init'));
      const wrap = document.createElement('div');
      wrap.innerHTML = document.querySelector('[data-id="top"]').innerHTML.replace(/<code([^>]*)>[\s\S]*?<\/code>/, '<code$1>let nuevo = 42;</code>');
      document.body.prepend(wrap);
      hook[1]([wrap]);
      await new Promise(r => setTimeout(r, 800));
      return { name: hook[0], tokens: wrap.querySelectorAll('.token').length, copy: !wrap.querySelector('.digi-code__copy').hidden };
    });
    ok(editor.name === 'frontend/element_ready/global' && editor.tokens >= 3 && editor.copy, 'En el editor, un bloque pintado de nuevo se resalta y activa su botón: ' + JSON.stringify(editor));
    await page.close();
  }

  // 5. Movimiento reducido.
  {
    const { page } = await open({ reduce: true });
    ok('0s' === await page.evaluate(() => getComputedStyle(document.querySelector('.digi-code__copy')).transitionDuration), 'Con movimiento reducido, el botón aparece sin transición.');
    await page.close();
  }
  ok(0 === errors.length, 'Sin errores de JavaScript: ' + errors.join(' | '));
  console.log(`DIGITALÍSIMO Elements: Código resaltado en Chrome (${checks} comprobaciones) validado.`);
} finally {
  await browser.close();
  server.close();
}
