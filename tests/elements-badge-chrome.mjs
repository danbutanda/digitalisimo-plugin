// F8, frente D: «Distintivo» en Chrome real (sin interfaz) sobre tests/fixtures/f8-badge.html, cuyos elementos pinta
// tests/elements-badge.php con la clase del rasgo: esquinas en LTR y en RTL, formas, recorte propio, flotación con y sin
// movimiento reducido, «recortar lo que sobresale», estilo generado, texto en el árbol de accesibilidad e icono fuera,
// el puntero atraviesa el distintivo, sin alto fijo con el espaciado de texto de 1.4.12, contraste por defecto y nada de
// desplazamiento horizontal en un teléfono.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-badge-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del distintivo (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/f8-badge.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ width = 1280, reduce = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  await page.goto(fixture, { waitUntil: 'load' });
  return page;
}
const badge = (page, id) => page.evaluate(id => {
  const h = document.querySelector(`[data-id="${id}"]`), b = h.querySelector(':scope>.digi-badge');
  const r = el => { const x = el.getBoundingClientRect(); return { l: x.left, r: x.right, t: x.top, b: x.bottom, w: x.width, h: x.height }; };
  const cs = getComputedStyle(b);
  return { first: h.firstElementChild === b, host: r(h), box: r(b), color: cs.color, bg: cs.backgroundColor, clip: cs.clipPath, radius: cs.borderTopLeftRadius, anim: cs.animationName, rotate: cs.rotate, top: cs.top, right: cs.right, pe: cs.pointerEvents, overflow: getComputedStyle(h).overflow, sw: b.scrollWidth, cw: b.clientWidth, sh: b.scrollHeight, ch: b.clientHeight };
}, id);
const near = (a, b, d = 1) => Math.abs(a - b) <= d;
const contrast = (a, b) => {
  const lum = c => { const [r, g, bl] = c.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * bl; };
  const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
  return (x + 0.05) / (y + 0.05);
};

try {
  {
    const page = await open();
    const s = {};
    for (const id of ['b1', 'b2', 'b3', 'b4', 'b5', 'b6', 'b7', 'b8', 'b9']) s[id] = await badge(page, id);
    ok(Object.values(s).every(b => b.first), 'Cada distintivo es el primer hijo del envoltorio de su elemento (widget, contenedor o sección).');
    ok(near(s.b1.box.t, s.b1.host.t) && near(s.b1.box.r, s.b1.host.r), 'Por defecto: arriba, al final (derecha en LTR).');
    ok(near(s.b2.box.t, s.b2.host.t) && near(s.b2.box.l, s.b2.host.l), 'Arriba, al inicio.');
    ok(near(s.b3.box.b, s.b3.host.b) && near(s.b3.box.l, s.b3.host.l), 'Abajo, al inicio.');
    ok(near(s.b4.box.b, s.b4.host.b) && near(s.b4.box.r, s.b4.host.r), 'Abajo, al final.');
    ok(near(s.b7.box.t, s.b7.host.t) && near(s.b7.box.l, s.b7.host.l) && near(s.b8.box.r, s.b8.host.r), 'En RTL, «al final» es la izquierda y «al inicio» la derecha (propiedades lógicas).');
    ok(near(s.b9.box.t, s.b9.host.t) && near(s.b9.box.r, s.b9.host.r), 'También en una sección.');
    ok(contrast(s.b1.color, s.b1.bg) >= 4.5, `Colores por defecto del sistema con contraste ≥ 4,5:1 (${contrast(s.b1.color, s.b1.bg).toFixed(1)}:1).`);
    ok(s.b2.radius !== '0px' && /^polygon/.test(s.b3.clip) && /^polygon/.test(s.b4.clip), `Formas: píldora redondeada, cinta y etiqueta recortadas (${s.b2.radius}).`);
    ok(s.b5.clip === 'polygon(0px 0px, 100% 0px, 90% 100%, 0px 100%)', `Recorte propio aplicado (${s.b5.clip}).`);
    ok(s.b5.anim === 'digi-badge-float' && s.b1.anim === 'none', 'La flotación sólo en el distintivo que la pide.');
    ok(s.b6.top === '8px' && s.b6.right === '12px' && s.b6.rotate === '10deg' && s.b6.bg === 'rgb(138, 29, 58)' && s.b6.color === 'rgb(255, 255, 255)', `Estilo generado: desplazamiento 12/8 px, giro de 10° y colores elegidos (${s.b6.rotate}).`);
    ok(s.b6.overflow === 'hidden' && s.b1.overflow === 'visible', '«Recortar lo que sobresale»: overflow hidden sólo en ese anfitrión.');
    ok(Object.values(s).every(b => b.pe === 'none'), 'Ningún distintivo captura el puntero.');
    const under = await page.evaluate(() => { const b = document.querySelector('[data-id="b1"] > .digi-badge').getBoundingClientRect(); const el = document.elementFromPoint(b.left + b.width / 2, b.top + b.height / 2); return el && el.className; });
    ok(under === 'digi-test-card', `El clic sobre el distintivo llega a lo que hay debajo (el enlace de la tarjeta): ${under}.`);
    // Árbol de accesibilidad: el texto es contenido; el icono, no.
    const tree = await page.accessibility.snapshot({ root: await page.$('[data-id="b2"]'), interestingOnly: false });
    const names = [];
    const walk = n => { names.push([n.role, n.name || '']); (n.children || []).forEach(walk); };
    walk(tree);
    ok(names.some(([, n]) => n === 'Inicio') && !names.some(([r]) => /image|img|graphics/i.test(r)), `El texto del distintivo está en el árbol de accesibilidad y el icono no (${names.map(n => n.join(':')).join(' | ')}).`);
    // 1.4.12: con el espaciado de texto aumentado no se recorta nada (sin alto fijo).
    const before = s.b1.box.h;
    await page.addStyleTag({ content: '*{line-height:1.5!important;letter-spacing:.12em!important;word-spacing:.16em!important}p{margin-bottom:2em!important}' });
    const spaced = await badge(page, 'b1');
    ok(spaced.sw <= spaced.cw + 1 && spaced.sh <= spaced.ch + 1 && spaced.box.h >= before, `Con el espaciado de texto de 1.4.12, el distintivo crece y no recorta su texto (${before.toFixed(1)} → ${spaced.box.h.toFixed(1)} px).`);
    ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
    ok(await page.evaluate(() => document.scripts.length === 0), '0 JS: la página no carga ningún script.');
    await page.close();
  }
  {
    const page = await open({ reduce: true });
    ok((await badge(page, 'b5')).anim === 'none', 'Con movimiento reducido, la flotación no se mueve (2.3.3).');
    await page.close();
  }
  {
    const page = await open({ width: 360 });
    ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'A 360 px, los distintivos no desplazan la página en horizontal.');
    const s = await badge(page, 'b1');
    ok(s.box.w <= s.host.w + 0.5, 'Nunca más ancho que su elemento (max-width).');
    await page.close();
  }
} finally {
  await browser.close();
}
console.log(`F8, distintivo en Chrome: ${checks} comprobaciones en verde.`);
