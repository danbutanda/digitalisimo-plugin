// Botones para compartir (`digitalisimo-share-buttons`, C2 frente A de docs/pro/c2.md) en Chrome real (sin interfaz)
// sobre tests/fixtures/pro-share-buttons.html, que es el marcado del servidor (lo comprueba
// tests/elements-pro-share-buttons.php; `--write-fixture` lo regenera):
// - ninguna petición fuera del archivo antes de un clic (ni redes, ni fuentes, ni contadores) y ningún error;
// - enlaces normales a la URL de compartir con otra pestaña y `noopener`; el correo es un mailto; «Imprimir» abre el
//   diálogo de impresión (window.print) sin navegar ni pedir nada;
// - al pulsar, como PRO: la URL del navegador sin un parámetro `u` de otro dominio y, para http(s), la ventana emergente
//   centrada de 640 × 480 sin acceso a la página; el correo y Ctrl+clic, en el navegador;
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
async function open({ width = 1280, reduce = false, query = '' } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setRequestInterception(true);
  page.on('request', r => { if (!r.url().startsWith('file:') && !r.url().startsWith('data:')) { outside.push(r.url()); r.abort().catch(() => {}); } else r.continue().catch(() => {}); });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  await page.evaluateOnNewDocument(() => {
    window.__prints = 0; window.print = () => { window.__prints++; };
    // window.open anotado (devuelve una ventana falsa para ver que se le quita `opener`) y, después de los manejadores
    // del documento, cada clic en un enlace anotado y sin navegar.
    window.__opened = []; window.open = (url, name, features) => { const w = { opener: window }; window.__opened.push({ url, name, features, w }); return w; };
    window.__clicks = []; window.addEventListener('click', e => { const a = e.target.closest && e.target.closest('a'); if (a) { window.__clicks.push({ prevented: e.defaultPrevented, href: a.getAttribute('href') }); e.preventDefault(); } });
  });
  await page.goto(fixture + query, { waitUntil: 'load' });
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
    const s1 = await style(page, '[data-id="s1"] .digi-share__btn--facebook', ['background-color', 'height', 'font-size']);
    s1.color = (await style(page, '[data-id="s1"] .digi-share__btn--facebook .digi-share__title', ['color'])).color;
    ok(s1['background-color'] === 'rgb(59, 89, 152)' && s1.color === 'rgb(255, 255, 255)' && s1.height === '45px' && s1['font-size'] === '10px', `Degradado oficial: el azul de la red, texto blanco y 45 px de alto (${JSON.stringify(s1)}).`);
    ok((await style(page, '[data-id="s1"] .digi-share__btn--facebook .digi-share__text', ['background-image']))['background-image'].startsWith('linear-gradient'), 'Degradado sobre el texto.');
    ok((await style(page, '[data-id="s1"] .digi-share__btn--facebook .digi-share__icon svg', ['fill']))['fill'] === 'rgb(255, 255, 255)', 'Icono blanco.');
    const s2 = await style(page, '[data-id="s2"] .digi-share__btn--whatsapp', ['background-color', 'border-radius']);
    s2.color = (await style(page, '[data-id="s2"] .digi-share__btn--whatsapp .digi-share__title', ['color'])).color;
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
    // 4. Imprimir: sin navegar ni pedir nada. Como el `div` de PRO, hereda la alineación del texto (la hoja del navegador
    // centra la de un <button>).
    ok(await page.evaluate(() => { const b = document.querySelector('[data-id="s1"] [data-digi-share-print]'); return getComputedStyle(b).textAlign === getComputedStyle(b.parentElement).textAlign && getComputedStyle(b).textAlign !== 'center'; }), 'El botón de imprimir hereda la alineación del texto, como el div de PRO.');
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
    // 5. Al pulsar, como PRO: la URL del navegador (sin un «u» de otro dominio) en lugar de la del servidor, y la ventana
    // emergente centrada de 640 × 480 para http(s), sin acceso a la página; el correo, en la misma ventana.
    const page = await open({ query: '?s=foo+bar&u=https%3A%2F%2Fotro.test%2Fx&lang=en&page=2#sec' });
    const server = 'https%3A%2F%2Fsitio.test%2Fhola-mundo%2F';
    const here = await page.evaluate(() => { const u = new URL(location.href); u.searchParams.delete('u'); return encodeURIComponent(u.toString()); });
    ok(here.includes('s%3Dfoo%2Bbar%26lang%3Den%26page%3D2%23sec') && !here.includes('otro.test'), 'La URL del navegador conserva la búsqueda, el idioma, la página y el ancla, y pierde el «u» de otro dominio: ' + decodeURIComponent(here));
    ok(await page.evaluate(s => document.querySelector('[data-id="s1"] .digi-share').getAttribute('data-digi-share-url') === s, server), 'El servidor deja su URL codificada en el envoltorio.');
    await page.click('[data-id="s1"] .digi-share__btn--facebook');
    await sleep(50);
    let r = await page.evaluate(() => ({ opened: window.__opened.map(o => ({ url: o.url, name: o.name, features: o.features, opener: o.w.opener })), clicks: window.__clicks, href: document.querySelector('[data-id="s1"] .digi-share__btn--facebook').getAttribute('href'), sw: screen.width, sh: screen.height }));
    const features = `toolbar=0,status=0,width=640,height=480,top=${r.sh / 2 - 240},left=${r.sw / 2 - 320}`;
    ok(r.opened.length === 1 && r.opened[0].url === 'https://www.facebook.com/sharer.php?u=' + here && r.opened[0].name === '' && r.opened[0].features === features && r.opened[0].opener === null, 'Facebook: ventana emergente de PRO con la URL del navegador y sin acceso a la página: ' + JSON.stringify(r.opened));
    ok(r.clicks.length === 1 && r.clicks[0].prevented && r.href === r.opened[0].url, 'El enlace no navega en esta ventana y queda con la URL del navegador.');
    await page.click('[data-id="s1"] .digi-share__btn--linkedin');
    await sleep(50);
    r = await page.evaluate(() => window.__opened.map(o => o.url));
    ok(r.length === 2 && r[1].split(here).length === 3 && !r[1].includes(server) && r[1].includes('title=Hola%20%26%20adi%C3%B3s'), 'LinkedIn: las dos apariciones de la URL cambian; el título no: ' + r[1]);
    await page.click('[data-id="s1"] .digi-share__btn--linkedin');
    await sleep(50);
    r = await page.evaluate(() => window.__opened.map(o => o.url));
    ok(r.length === 3 && r[2] === r[1], 'Un segundo clic da el mismo enlace.');
    await page.click('[data-id="s1"] .digi-share__btn--email');
    await sleep(50);
    r = await page.evaluate(() => ({ opened: window.__opened.length, last: window.__clicks[window.__clicks.length - 1] }));
    ok(r.opened === 3 && !r.last.prevented && r.last.href.startsWith('mailto:') && r.last.href.endsWith('%0A' + here), 'Correo: sin ventana emergente, en la misma ventana, con la URL del navegador: ' + JSON.stringify(r.last));
    await page.keyboard.down('Control');
    await page.click('[data-id="s2"] .digi-share__btn--whatsapp');
    await page.keyboard.up('Control');
    await sleep(50);
    r = await page.evaluate(() => ({ opened: window.__opened.length, last: window.__clicks[window.__clicks.length - 1] }));
    ok(r.opened === 3 && !r.last.prevented && r.last.href.endsWith('%0A' + here), 'Con Ctrl, el navegador abre el enlace (con la URL del navegador) sin ventana emergente: ' + JSON.stringify(r.last));
    await page.focus('[data-id="s2"] .digi-share__btn--telegram');
    await page.keyboard.press('Enter');
    await sleep(50);
    r = await page.evaluate(() => window.__opened.map(o => o.url));
    ok(r.length === 4 && r[3].startsWith('https://telegram.me/share/url?url=' + here), 'Con el teclado (Intro), también la ventana emergente.');
    // Sin la URL del servidor (URL personalizada), el enlace no cambia, pero se abre igual en la ventana emergente.
    const custom = await page.evaluate(() => { const box = document.querySelector('[data-id="s3"] .digi-share'); box.removeAttribute('data-digi-share-url'); return box.querySelector('.digi-share__btn--reddit').getAttribute('href'); });
    await page.click('[data-id="s3"] .digi-share__btn--reddit');
    await sleep(50);
    r = await page.evaluate(() => window.__opened.map(o => o.url));
    ok(r.length === 5 && r[4] === custom, 'Con la URL personalizada, la del enlace, en la ventana emergente.');
    // En la portada, la URL del servidor es el comienzo de la de la imagen destacada: sólo cambia la URL entera (la de
    // `url`), nunca la imagen (`media`), como PRO, que la tomaba aparte.
    const home = 'https%3A%2F%2Fsitio.test%2F', media = home + 'wp-content%2Fuploads%2Ffoto.jpg';
    await page.evaluate((h, m) => { const box = document.querySelector('[data-id="s3"] .digi-share'); box.setAttribute('data-digi-share-url', h); const a = box.querySelector('.digi-share__btn--pinterest'); a.digiHref = undefined; a.setAttribute('href', 'https://www.pinterest.com/pin/create/button/?url=' + h + '&media=' + m); }, home, media);
    await page.click('[data-id="s3"] .digi-share__btn--pinterest');
    await sleep(50);
    r = await page.evaluate(() => window.__opened.map(o => o.url));
    ok(r.length === 6 && r[5] === 'https://www.pinterest.com/pin/create/button/?url=' + here + '&media=' + media, 'Portada: la URL del navegador en `url` y la imagen intacta en `media`: ' + r[5]);
    // En el editor (modo edición del núcleo, también en su vista previa), como `isActive()` de PRO: pulsar un botón sólo
    // selecciona el widget; ni ventana emergente ni diálogo de impresión.
    await page.evaluate(() => { window.elementorFrontend = { isEditMode: () => true }; });
    const prints = await page.evaluate(() => window.__prints);
    await page.click('[data-id="s1"] .digi-share__btn--facebook');
    await page.click('[data-id="s1"] [data-digi-share-print]');
    await sleep(50);
    r = await page.evaluate(() => ({ opened: window.__opened.length, prints: window.__prints }));
    ok(r.opened === 6 && r.prints === prints, 'En el editor, ni ventana emergente ni impresión: ' + JSON.stringify(r));
    await page.evaluate(() => { window.elementorFrontend = { isEditMode: () => false }; });
    await page.click('[data-id="s1"] .digi-share__btn--facebook');
    await sleep(50);
    ok((await page.evaluate(() => window.__opened.length)) === 7, 'Fuera del modo edición (front con el núcleo), la ventana emergente.');
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
