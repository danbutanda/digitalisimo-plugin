// Botones para compartir (`digitalisimo-share-buttons`, C2 frente A de docs/pro/c2.md) en Chrome real (sin interfaz)
// sobre tests/fixtures/pro-share-buttons.html, que es el marcado del servidor (lo comprueba
// tests/elements-pro-share-buttons.php; `--write-fixture` lo regenera):
// - ninguna petición fuera del archivo antes de un clic (ni redes, ni fuentes, ni contadores) y ningún error;
// - enlaces normales a la URL de compartir con otra pestaña y `noopener`; el correo es un mailto; «Imprimir» abre el
//   diálogo de impresión (window.print) sin navegar ni pedir nada;
// - pieles y colores: degradado y plana con el color oficial o el personalizado (y el del cursor encima), caja con el
//   icono en su color, enmarcada con borde, mínima con el texto heredado; filtro al pasar el cursor con color oficial;
// - objetivos de 24 × 24 px como mínimo (también con el tamaño de botón más pequeño), foco visible de 2 px con 2 px de
//   separación, y sin transiciones con movimiento reducido.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-share-buttons-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los Botones para compartir (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-share-buttons.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
const outside = [];
async function open({ width = 1280, reduce = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setRequestInterception(true);
  page.on('request', r => { if (!r.url().startsWith('file:') && !r.url().startsWith('data:')) { outside.push(r.url()); r.abort().catch(() => {}); } else r.continue().catch(() => {}); });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  await page.evaluateOnNewDocument(() => { window.__prints = 0; window.print = () => { window.__prints++; }; });
  await page.goto(fixture, { waitUntil: 'load' });
  return page;
}
const style = (page, sel, props, pseudo = null) => page.evaluate((s, p, ps) => { const el = document.querySelector(s); const cs = getComputedStyle(el, ps); return Object.fromEntries(p.map(k => [k, cs.getPropertyValue(k)])); }, sel, props, pseudo);

try {
  for (const width of [1280, 375]) {
    const page = await open({ width });
    // 1. Enlaces normales, sin peticiones.
    const links = await page.evaluate(() => [...document.querySelectorAll('.digi-share__btn')].map(b => ({ tag: b.tagName, net: b.className.replace(/.*digi-share__btn--/, ''), href: b.getAttribute('href'), target: b.getAttribute('target'), rel: b.getAttribute('rel'), name: b.getAttribute('aria-label') || b.textContent.trim(), w: b.getBoundingClientRect().width, h: b.getBoundingClientRect().height })));
    ok(links.length === 16, `16 botones en el fixture (${links.length}).`);
    for (const l of links) {
      ok(l.w >= 24 && l.h >= 24, `${width}px · ${l.net}: objetivo de ${Math.round(l.w)} × ${Math.round(l.h)} px (mínimo 24 × 24).`);
      if (l.tag === 'A' && /^https:/.test(l.href)) ok(l.target === '_blank' && l.rel === 'noopener noreferrer', `${l.net}: abre otra pestaña sin dar acceso a la página.`);
      if (l.tag === 'A') ok(/^(https:|mailto:)/.test(l.href), `${l.net}: enlace con destino (${l.href}).`);
      ok(!/^javascript:/i.test(l.href || ''), `${l.net}: ningún enlace javascript:.`);
      ok(l.name && !/Share on/.test(l.name), `${l.net}: nombre accesible en español (${l.name}).`);
    }
    ok(links.filter(l => l.tag === 'BUTTON').length === 1 && links.filter(l => l.tag === 'SPAN').length === 1, 'Un botón (imprimir) y un elemento sin enlace (Mix).');
    // 2. Pieles y colores.
    const s1 = await style(page, '[data-id="s1"] .digi-share__btn--facebook', ['background-color', 'color', 'height', 'font-size']);
    ok(s1['background-color'] === 'rgb(59, 89, 152)' && s1.color === 'rgb(255, 255, 255)' && s1.height === '45px' && s1['font-size'] === '10px', `Degradado oficial: el azul de la red, texto blanco y 45 px de alto (${JSON.stringify(s1)}).`);
    ok((await style(page, '[data-id="s1"] .digi-share__btn--facebook .digi-share__text', ['background-image']))['background-image'].startsWith('linear-gradient'), 'Degradado sobre el texto.');
    ok((await style(page, '[data-id="s1"] .digi-share__btn--facebook .digi-share__icon svg', ['fill']))['fill'] === 'rgb(255, 255, 255)', 'Icono blanco.');
    const s2 = await style(page, '[data-id="s2"] .digi-share__btn--whatsapp', ['background-color', 'color', 'border-radius']);
    ok(s2['background-color'] === 'rgb(17, 34, 51)' && s2.color === 'rgb(255, 238, 221)' && s2['border-radius'] === '5px', `Plana personalizada y redondeada (${JSON.stringify(s2)}).`);
    await page.hover('[data-id="s2"] .digi-share__btn--whatsapp');
    await sleep(350);
    ok((await style(page, '[data-id="s2"] .digi-share__btn--whatsapp', ['background-color']))['background-color'] === 'rgb(51, 68, 85)', 'El color principal al pasar el cursor.');
    await page.hover('[data-id="s1"] .digi-share__btn--facebook');
    await sleep(350);
    ok((await style(page, '[data-id="s1"] .digi-share__btn--facebook', ['filter']))['filter'] === 'saturate(1.5) brightness(1.2)', 'Con color oficial, el filtro de PRO al pasar el cursor.');
    await page.mouse.move(0, 0);
    const s3 = await style(page, '[data-id="s3"] .digi-share__btn--pinterest', ['border-top-style', 'border-top-width', 'border-top-color', 'color']);
    const s3i = await style(page, '[data-id="s3"] .digi-share__btn--pinterest .digi-share__icon', ['background-color', 'color']);
    ok(s3['border-top-style'] === 'solid' && s3['border-top-width'] === '2px' && s3['border-top-color'] === 'rgb(189, 8, 28)' && s3.color === 'rgb(189, 8, 28)' && s3i['background-color'] === 'rgb(189, 8, 28)' && s3i.color === 'rgb(255, 255, 255)', `Caja oficial: borde e icono en el color de la red (${JSON.stringify([s3, s3i])}).`);
    const s4 = await style(page, '[data-id="s4"] .digi-share__btn--vk', ['border-top-color', 'color', 'background-color', 'justify-content']);
    ok(s4['border-top-color'] === 'rgb(17, 34, 51)' && s4.color === 'rgb(17, 34, 51)' && s4['background-color'] === 'rgba(0, 0, 0, 0)' && s4['justify-content'] === 'center', `Enmarcada personalizada, sólo texto centrado (${JSON.stringify(s4)}).`);
    const s5 = await style(page, '[data-id="s5"] .digi-share__btn--threads', ['color', 'border-top-width']);
    const s5i = await style(page, '[data-id="s5"] .digi-share__btn--threads .digi-share__icon', ['background-color', 'border-top-left-radius']);
    ok(s5.color === 'rgb(26, 26, 26)' && s5['border-top-width'] === '0px' && s5i['background-color'] === 'rgb(0, 0, 0)' && s5i['border-top-left-radius'] !== '0px', `Mínima oficial: texto heredado y el icono en un círculo del color de la red (${JSON.stringify([s5, s5i])}).`);
    // Columnas del núcleo.
    const cols = await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="s3"] .elementor-grid')).gridTemplateColumns.split(' ').length);
    ok(cols === 3, 'Tres columnas con la rejilla del núcleo.');
    // 3. Foco visible con el teclado.
    await page.keyboard.press('Tab');
    const focus = await page.evaluate(() => { const a = document.activeElement; const cs = getComputedStyle(a); return { cls: a.className, style: cs.outlineStyle, w: cs.outlineWidth, off: cs.outlineOffset, color: cs.outlineColor }; });
    ok(focus.cls.includes('digi-share__btn--facebook') && focus.style === 'solid' && focus.w === '2px' && focus.off === '2px' && focus.color === 'rgb(59, 89, 152)', `Foco visible de 2 px a 2 px en el color de la red (${JSON.stringify(focus)}).`);
    // 4. Imprimir: sin navegar ni pedir nada.
    const before = page.url();
    await page.click('[data-id="s1"] [data-digi-share-print]');
    await sleep(100);
    ok((await page.evaluate(() => window.__prints)) === 1 && page.url() === before, 'Imprimir abre el diálogo de impresión una vez, sin navegar.');
    await page.focus('[data-id="s1"] [data-digi-share-print]');
    await page.keyboard.press('Enter');
    await sleep(100);
    ok((await page.evaluate(() => window.__prints)) === 2, 'También con el teclado (Intro).');
    await page.close();
  }
  {
    const page = await open({ reduce: true });
    const t = await style(page, '[data-id="s1"] .digi-share__btn--facebook', ['transition-duration']);
    ok(/^0s/.test(t['transition-duration']), `Sin transiciones con movimiento reducido (${t['transition-duration']}).`);
    await page.close();
  }
  ok(outside.length === 0, `Ninguna petición fuera del archivo antes de un clic: ${outside.join(', ')}`);
  ok(errors.length === 0, `Sin errores de JavaScript: ${errors.join(' | ')}`);
  console.log(`DIGITALÍSIMO Elements: Botones para compartir en Chrome (${checks} comprobaciones) validados.`);
} finally {
  await browser.close();
}
