// Botones de control de los widgets propios frente al estilo de botón del kit de Elementor, en Chrome real (sin interfaz).
// El kit pinta `.elementor-kit-N button` (0,1,1) y `.elementor-kit-N button:hover/:focus` (0,2,1): un <button> propio con
// menos especificidad toma el color de botón del sitio (caso real: el botón invisible de cada submenú del menú de WordPress
// se veía como un rectángulo de color junto al nombre). Sobre los fixtures de los widgets (los generados por sus pruebas y
// tests/fixtures/control-buttons.html para los demás), con un kit llamativo cargado AL FINAL (el peor orden):
// - cada botón de control visible, en reposo, con el puntero encima, con el foco y con el foco del teclado (estados forzados por DevTools, también
//   en los que no reciben el puntero), no toma del kit el fondo, el borde, la sombra, el color ni el relleno, ni el radio
//   si pinta fondo, borde o sombra;
// - los botones de acción (`.elementor-button`, salvo el del menú del carrito, y el «Aplicar» del cupón) sí toman el fondo
//   del kit en reposo y el de hover con el puntero encima;
// - cada clase de la lista `expect` de su página se midió al menos una vez (si un fixture cambia, la prueba no se vacía).
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 300 node tests/elements-control-buttons-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const npx = '/home/digitalisimo/.npm/_npx';
const found = existsSync(npx) ? readdirSync(npx).map(d => `${npx}/${d}/node_modules/puppeteer-core`).find(d => existsSync(d + '/package.json')) : '';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : found;
if (!existsSync(chrome) || !pptrDir || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los botones de control (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
// Los fixtures se sirven por HTTP desde la raíz del repositorio: unos enlazan sus hojas con rutas relativas y otros con
// rutas absolutas (/digitalisimo-elements/…).
const root = fileURLToPath(new URL('..', import.meta.url));
const types = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml' };
const server = createServer((req, res) => {
  const path = normalize(join(root, decodeURIComponent(new URL(req.url, 'http://x').pathname)));
  if (!path.startsWith(root) || !existsSync(path) || !statSync(path).isFile()) { res.writeHead(404); res.end(); return; }
  res.writeHead(200, { 'Content-Type': types[extname(path)] || 'application/octet-stream' });
  res.end(readFileSync(path));
});
await new Promise(r => server.listen(0, '127.0.0.1', r));
const origin = `http://127.0.0.1:${server.address().port}/tests/fixtures/`;

// Kit con los selectores que genera Elementor (core/kits/documents/tabs/theme-style-buttons.php) y valores centinela.
const KIT_REST = { bg: 'rgb(255, 0, 170)', color: 'rgb(250, 250, 0)', border: 'rgb(1, 2, 3)', shadow: 'rgb(9, 9, 9)' };
const KIT_HOVER = { bg: 'rgb(0, 200, 0)', color: 'rgb(0, 0, 250)', border: 'rgb(4, 5, 6)', shadow: 'rgb(8, 8, 8)' };
const W = '.elementor-kit-9';
const KIT = `${W} button,${W} input[type="button"],${W} input[type="submit"],${W} .elementor-button{background-color:rgb(255,0,170);color:rgb(250,250,0);border:5px solid rgb(1,2,3);border-radius:31px;box-shadow:0 0 0 4px rgb(9,9,9);padding:21px 33px}
${W} button:hover,${W} button:focus,${W} input[type="button"]:hover,${W} input[type="button"]:focus,${W} input[type="submit"]:hover,${W} input[type="submit"]:focus,${W} .elementor-button:hover,${W} .elementor-button:focus{background-color:rgb(0,200,0);color:rgb(0,0,250);border:5px solid rgb(4,5,6);border-radius:31px;box-shadow:0 0 0 6px rgb(8,8,8)}
*,:before,:after{transition:none!important;animation:none!important}`;

// Páginas: fixture, anchos y clases de botón de control que deben medirse.
const PAGES = [
  { file: 'nav-menu.html', widths: [1280, 375], expect: ['digi-nav__sub-toggle', 'digi-nav__toggle'] },
  { file: 'mega-menu.html', widths: [1280, 375], expect: ['digi-mm__dropdown-icon', 'digi-mm__toggle'] },
  { file: 'crs-engine.html', widths: [1280], expect: ['digi-crs__arrow', 'digi-crs__dot', 'digi-crs__pause'] },
  { file: 'f7b-nested-slider.html', widths: [1280], expect: ['digi-nslider__btn', 'digi-nslider__dot'] },
  { file: 'pro-code-highlight.html', widths: [1280], expect: ['digi-code__copy'] },
  { file: 'pro-facebook.html', widths: [1280], expect: ['digi-fb__load'] },
  { file: 'pro-floating.html', widths: [1280], expect: ['digi-cb__chat-button', 'digi-fbar__close-button', 'digi-fbar__pause-button'] },
  { file: 'pro-gallery.html', widths: [1280], expect: ['digi-gallery__filter'] },
  { file: 'pro-hotspot.html', widths: [1280], expect: ['digi-hs__button'] },
  { file: 'pro-share-buttons.html', widths: [1280], expect: ['digi-share__btn'] },
  { file: 'pro-table-of-contents.html', widths: [1280], expect: ['digi-toc__toggle'] },
  { file: 'video-playlist.html', widths: [1280, 375], expect: ['digi-vpl__fold', 'digi-vpl__play', 'digi-vpl__title'] },
  { file: 'control-buttons.html', widths: [1280], expect: ['digi-carousel__button', 'digi-offcanvas__close', 'digi-notification__close', 'digi-vertical-menu__back-button', 'digi-vertical-menu__link', 'digi-vertical-menu__toggle', 'digi-video-player__item', 'digi-fancy-tabs__tab', 'digi-wish', 'digi-wishlist__remove', 'digi-wishlist__clear', 'digi-wish-merge', 'digi-coupon__remove', 'digi-mc__btn', 'digi-mc__x', 'digi-mc__xc', 'digi-search-form__submit', 'digi-search-form__toggle', 'digi-search-form__close', 'digi-search__clear', 'digi-search__submit', 'digi-facade__button', 'digi-portfolio__filter', 'digi-tfilter__item', 'digi-filter__submit', 'digi-qv__close', 'digi-qv__thumb'], actions: ['digi-offcanvas__button', 'digi-coupon__apply'] },
  { file: 'pro-payments.html', widths: [1280], expect: [], actions: ['digi-pay__button'] },
  { file: 'pro-form.html', widths: [1280], expect: [], actions: ['elementor-button'] },
];
// Botones de acción: toman el estilo global. El del menú del carrito lleva `elementor-button` pero es un control con sus
// propios colores (como en PRO).
const ACTION_EXTRA = ['digi-coupon__apply'];
// El título de la Lista de videos va en un botón con el color de los botones del sitio, como PRO (lo exigen
// elements-pro-video-playlist{,-chrome}): de él sólo se mide que no tome el fondo, el borde, la sombra ni el relleno.
const KIT_TEXT_AS_PRO = '.digi-vpl__title button';
// El filtro del Portafolio deja la sombra de foco del sitio con el teclado, como PRO (elements-pro-posts-chrome.mjs,
// diferencia declarada 1); en reposo, hover y foco con el ratón no la toma.
const KIT_FOCUS_SHADOW_AS_PRO = '.digi-portfolio__filter';
const isAction = cls => (cls.includes('elementor-button') && !cls.includes('digi-mc__btn')) || ACTION_EXTRA.some(c => cls.split(' ').includes(c));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
const failures = [];
const reported = new Set(); // una línea por fixture, clase y estado
let checks = 0;
// ONLY=nav-menu.html,crs-engine.html limita las páginas (depuración).
const only = (process.env.ONLY || '').split(',').filter(Boolean);
for (const pg of PAGES.filter(p => !only.length || only.includes(p.file))) {
  const seen = new Set();
  const actionsSeen = new Set();
  for (const width of pg.widths) {
    const page = await browser.newPage();
    page.on('pageerror', e => errors.push(`${pg.file}: ${e.message}`));
    await page.setRequestInterception(true);
    page.on('request', r => (r.url().startsWith(origin.slice(0, origin.indexOf('/tests/'))) ? r.continue() : r.abort()));
    await page.setViewport({ width, height: 900 });
    await page.goto(origin + pg.file, { waitUntil: 'load' });
    await new Promise(r => setTimeout(r, 400)); // los scripts de los widgets preparan sus botones
    const n = await page.evaluate(kit => {
      document.body.classList.add('elementor-kit-9');
      const s = document.createElement('style');
      s.textContent = kit;
      document.head.appendChild(s);
      const all = [...document.querySelectorAll('button')];
      all.forEach((b, i) => b.setAttribute('data-kit-probe', i));
      return all.length;
    }, KIT);
    const cdp = await page.createCDPSession();
    await cdp.send('DOM.enable');
    await cdp.send('CSS.enable');
    const { root } = await cdp.send('DOM.getDocument', { depth: -1 });
    for (let i = 0; i < n; i++) {
      const sel = `[data-kit-probe="${i}"]`;
      const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector: sel });
      const read = () => page.evaluate((s, kitTextAsPro, focusShadowAsPro) => {
        const b = document.querySelector(s), c = getComputedStyle(b), r = b.getBoundingClientRect();
        return { visible: r.width > 0 && r.height > 0 && c.visibility !== 'hidden', cls: String(b.className) || b.closest('[class]').className.split(' ')[0] + ' button', proText: b.matches(kitTextAsPro), focusShadowAsPro: b.matches(focusShadowAsPro), parent: String(b.parentNode.className), bg: c.backgroundColor, color: c.color, border: ['Top', 'Right', 'Bottom', 'Left'].filter(e => parseFloat(c[`border${e}Width`]) > 0).map(e => c[`border${e}Color`]).join(' '), shadow: c.boxShadow, radius: c.borderTopLeftRadius, padding: c.paddingTop + ' ' + c.paddingLeft };
      }, sel, KIT_TEXT_AS_PRO, KIT_FOCUS_SHADOW_AS_PRO);
      const states = { reposo: await read() };
      if (!states.reposo.visible) continue;
      // El foco con el teclado es :focus y :focus-visible a la vez.
      for (const [st, forced] of [['hover', ['hover']], ['focus', ['focus']], ['teclado', ['focus', 'focus-visible']]]) {
        await cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: forced });
        states[st] = await read();
      }
      await cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] });
      const cls = states.reposo.cls;
      const where = `${pg.file} @${width} «${cls}»`;
      if (isAction(cls)) {
        actionsSeen.add(cls);
        checks++;
        if (states.reposo.bg !== KIT_REST.bg || states.hover.bg !== KIT_HOVER.bg) failures.push(`${where}: botón de acción sin el fondo del kit (reposo ${states.reposo.bg}, hover ${states.hover.bg}).`);
        continue;
      }
      cls.split(/\s+/).concat(states.reposo.parent.split(/\s+/)).forEach(c => seen.add(c));
      for (const [st, s] of Object.entries(states)) {
        checks++;
        const bad = [];
        for (const k of [KIT_REST, KIT_HOVER]) {
          if (s.bg === k.bg) bad.push('fondo ' + s.bg);
          if (s.color === k.color && !s.proText) bad.push('color ' + s.color);
          if (s.border.includes(k.border)) bad.push('borde ' + s.border);
          if (s.shadow.includes(k.shadow) && !(st === 'teclado' && s.focusShadowAsPro)) bad.push('sombra ' + s.shadow);
        }
        // El radio sólo se ve si el botón pinta fondo, borde o sombra.
        if (s.radius === '31px' && (s.bg !== 'rgba(0, 0, 0, 0)' || s.border || s.shadow !== 'none')) bad.push('radio 31px');
        if (/21px|33px/.test(s.padding)) bad.push('relleno ' + s.padding);
        const key = `${pg.file} «${cls}» ${st}`;
        if (bad.length && !reported.has(key)) { reported.add(key); failures.push(`${where} ${st}: ${bad.join(', ')}`); }
      }
    }
    await page.close();
  }
  for (const c of pg.expect) {
    checks++;
    if (!seen.has(c)) failures.push(`${pg.file}: no se midió ningún botón visible «${c}».`);
  }
  for (const c of pg.actions || []) {
    checks++;
    if (![...actionsSeen].some(x => x.split(' ').includes(c))) failures.push(`${pg.file}: no se midió el botón de acción «${c}».`);
  }
}
await browser.close();
server.close();
if (failures.length) console.log(failures.join('\n'));
assert.equal(failures.length, 0, `${failures.length} botones toman el estilo de botón del kit (o faltan).`);
assert.deepEqual(errors, [], 'Sin errores de JavaScript en las páginas.');
console.log(`Botones de control frente al kit en Chrome: ${checks} comprobaciones correctas.`);
