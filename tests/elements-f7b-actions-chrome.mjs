// F7b, frente B: `actions.js` en Chrome sin interfaz con `tests/fixtures/f7b-actions.html` (V.3 y V.14).
// - `getComputedStyle().display === 'none'` con `hidden` en un contenedor flex, uno grid, una columna y un widget, con
//   la hoja real del núcleo y la regla de `Hidden_Rule`; sin la regla, el contenedor y la columna se verían (C2-2).
// - Sin JavaScript, los `<noscript>` de cada destino los muestran con su `display` (flex, grid, flex y block).
// - Recorrido sólo con teclado: Tab, Intro y Espacio en el envoltorio-botón y en el «a» sin enlace, foco devuelto al
//   ocultar lo que lo tenía, `aria-expanded` y el árbol de accesibilidad.
// - `hover` con el foco y con el puntero real: 300 ms de margen, el destino se puede recorrer y Escape lo cierra.
// - Popups por `createActionHash()` + `runAction()`; un `set_attribute` fuera de la lista se ignora; `scroll_in` real.
// Sin Chrome (CI), salta con un aviso.
import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';

const CHROME = process.env.CHROME_BIN || '/usr/bin/google-chrome';
const PUPPETEER = process.env.PUPPETEER_CORE || '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(CHROME) || !existsSync(PUPPETEER)) {
  console.log(`AVISO: sin Chrome o puppeteer-core (${CHROME}); se salta elements-f7b-actions-chrome.mjs.`);
  process.exit(0);
}
const puppeteer = createRequire(import.meta.url)(PUPPETEER);
const fixture = pathToFileURL(fileURLToPath(new URL('./fixtures/f7b-actions.html', import.meta.url))).href;
let checks = 0;
const failures = [];
const ok = (value, message) => { checks++; if (!value) failures.push(message); };

const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1024, height: 700 });
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  const display = (id) => page.$eval(`[data-id="${id}"]`, (el) => getComputedStyle(el).display);
  const active = () => page.evaluate(() => (document.activeElement && (document.activeElement.id || document.activeElement.getAttribute('data-id'))) || '');
  const attr = (id, name) => page.$eval(`[data-id="${id}"]`, (el, n) => el.getAttribute(n), name);

  // ------------------------------------------------------------------ Sin JavaScript: los <noscript> muestran todo.
  await page.setJavaScriptEnabled(false);
  await page.goto(fixture, { waitUntil: 'load' });
  for (const [id, expected] of [['f1e2d3c', 'flex'], ['g2g2g2g', 'grid'], ['m3m3m3m', 'flex'], ['w4w4w4w', 'block']]) {
    ok((await display(id)) === expected, `Sin JavaScript, ${id} se ve con display ${expected} (${await display(id)}).`);
  }
  await page.setJavaScriptEnabled(true);

  // ------------------------------------------------------------------ Con JavaScript: ocultos con la regla [hidden].
  await page.goto(fixture, { waitUntil: 'load' });
  await page.waitForFunction(() => document.querySelector('[data-digi-actions-ready]'));
  ok(await page.evaluate(() => window.F7B.ready.length === 1 && window.F7B.ready[0][0] === 'frontend/element_ready/global'), 'Arranca tras elementor/frontend/init y se engancha a element_ready/global.');
  for (const id of ['f1e2d3c', 'g2g2g2g', 'm3m3m3m', 'w4w4w4w']) {
    ok((await display(id)) === 'none', `Oculto al cargar: ${id} con display none (${await display(id)}).`);
  }
  const without = await page.evaluate(() => {
    const rule = document.getElementById('hidden-rule');
    rule.disabled = true;
    const out = ['f1e2d3c', 'g2g2g2g', 'm3m3m3m', 'w4w4w4w'].map((id) => getComputedStyle(document.querySelector(`[data-id="${id}"]`)).display);
    rule.disabled = false;
    return out;
  });
  ok(without[0] === 'flex' && without[1] === 'grid' && without[2] === 'flex', `Sin la regla, hidden no oculta contenedores ni columnas (C2-2): ${without.join(', ')}.`);
  ok((await attr('a1b2c3d', 'aria-expanded')) === 'false' && (await attr('a1b2c3d', 'aria-controls')) === 'digi-t-f1e2d3c' && (await attr('f1e2d3c', 'id')) === 'digi-t-f1e2d3c', 'aria-expanded y aria-controls en el envoltorio-botón; id asignado al destino.');

  // ------------------------------------------------------------------ Teclado.
  await page.focus('body');
  await page.keyboard.press('Tab');
  ok((await active()) === 'a1b2c3d', `Tab llega primero al contenedor convertido en botón (${await active()}).`);
  const tree = await page.accessibility.snapshot({ interestingOnly: true });
  const find = (node, pred) => (pred(node) ? node : (node.children || []).map((c) => find(c, pred)).find(Boolean));
  const button = find(tree, (n) => n.role === 'button' && /Abrir panel/.test(n.name || ''));
  ok(button && button.expanded === false, `Árbol de accesibilidad: botón «Abrir panel», contraído (${JSON.stringify(button)}).`);
  await page.keyboard.press('Enter');
  ok((await display('f1e2d3c')) === 'flex' && (await attr('a1b2c3d', 'aria-expanded')) === 'true', 'Intro lo muestra (flex) y aria-expanded pasa a true.');
  await page.keyboard.press('Tab');
  ok((await active()) === 'cerrar', `Tab entra en el panel mostrado (${await active()}).`);
  await page.keyboard.press('Enter');
  ok((await display('f1e2d3c')) === 'none', 'Intro en «Cerrar panel» lo oculta.');
  ok((await active()) === 'a1b2c3d', `El foco vuelve al botón que lo abrió (${await active()}).`);
  ok(await page.evaluate(() => window.scrollY === 0 && location.hash === ''), 'El enlace "#" no salta ni cambia la dirección.');
  await page.keyboard.press('Space');
  ok((await display('f1e2d3c')) === 'flex', 'Espacio también lo muestra.');
  await page.keyboard.press('Space');
  ok((await display('f1e2d3c')) === 'none' && (await page.evaluate(() => window.scrollY)) === 0, 'Espacio lo oculta sin desplazar la página.');
  await page.keyboard.press('Tab');
  ok((await active()) === 'ver-rejilla', `Tab llega al botón sin enlace (${await active()}).`);
  await page.keyboard.press('Space');
  ok((await display('g2g2g2g')) === 'grid', 'Espacio en el «a» sin enlace muestra la rejilla (grid).');
  ok(await page.$eval('[data-id="g2g2g2g"]', (el) => el.classList.contains('animated') && el.classList.contains('fadeIn')), 'Con su animación.');
  await page.keyboard.press('Tab');
  ok((await active()) === 'ver-columna', `Tab llega al enlace del encabezado (${await active()}).`);
  await page.keyboard.press('Enter');
  ok((await display('m3m3m3m')) === 'flex' && (await page.$eval('#ver-columna', (a) => a.getAttribute('aria-expanded'))) === 'true', 'Intro muestra la columna (flex); aria-expanded en el enlace.');

  // ------------------------------------------------------------------ hover con el foco, Escape.
  await page.keyboard.press('Tab');
  ok((await active()) === 'servicios', `Tab llega a «Servicios» (${await active()}).`);
  ok((await display('w4w4w4w')) === 'block' && (await page.$eval('[data-id="n4n4n4n"]', (el) => el.classList.contains('revelado'))), 'Con el foco se muestra el submenú (block) y la clase del disparador.');
  await page.keyboard.press('Tab');
  ok((await active()) === 'sub-web' && (await display('w4w4w4w')) === 'block', 'Tab entra en el submenú, que sigue visible.');
  await page.keyboard.press('Escape');
  ok((await display('w4w4w4w')) === 'none' && (await active()) === 'servicios', 'Escape lo cierra y el foco vuelve a «Servicios».');
  await new Promise((r) => setTimeout(r, 50));
  ok((await display('w4w4w4w')) === 'none', 'Volver por Escape no lo reabre.');
  await page.keyboard.down('Shift');
  await page.keyboard.press('Tab');
  await page.keyboard.up('Shift');
  await page.keyboard.press('Tab');
  ok((await active()) === 'servicios' && (await display('w4w4w4w')) === 'block', 'Al volver a enfocarlo, se muestra de nuevo.');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  await new Promise((r) => setTimeout(r, 30));
  ok((await active()) === 'popup' && (await display('w4w4w4w')) === 'none', `El foco fuera lo oculta (${await active()}).`);

  // ------------------------------------------------------------------ Popup y set_attribute fuera de la lista.
  await page.keyboard.press('Enter');
  const actions = await page.evaluate(() => window.F7B.actions);
  ok(actions.length === 1 && actions[0].action === 'popup:open' && actions[0].settings.id === '77' && actions[0].settings.toggle === false, `popup_open por createActionHash() + runAction(): ${JSON.stringify(actions)}.`);
  ok((await attr('o5o5o5o', 'aria-hidden')) === null, 'set_attribute aria-hidden (fuera de la lista) no se aplica.');

  // ------------------------------------------------------------------ hover con el puntero y 300 ms de margen.
  await page.mouse.move(1000, 690);
  const box = await page.$eval('#servicios', (a) => { const r = a.getBoundingClientRect(); return { x: r.left + 5, y: r.top + r.height / 2 }; });
  await page.mouse.move(box.x, box.y);
  ok((await display('w4w4w4w')) === 'block', 'Puntero encima: se muestra.');
  const sub = await page.$eval('[data-id="w4w4w4w"]', (el) => { const r = el.getBoundingClientRect(); return { x: r.left + 20, y: r.top + r.height / 2 }; });
  await page.mouse.move(sub.x, sub.y, { steps: 4 });
  await new Promise((r) => setTimeout(r, 450));
  ok((await display('w4w4w4w')) === 'block', 'Del disparador al destino, sigue visible (1.4.13).');
  await page.mouse.move(1000, 690);
  await new Promise((r) => setTimeout(r, 120));
  ok((await display('w4w4w4w')) === 'block', 'Al salir, se mantiene durante el margen…');
  await new Promise((r) => setTimeout(r, 300));
  ok((await display('w4w4w4w')) === 'none', '…y después se oculta.');

  // ------------------------------------------------------------------ Al aparecer en pantalla.
  ok(!(await page.$eval('[data-id="z5z5z5z"]', (el) => el.classList.contains('visto'))), 'Fuera de pantalla, nada.');
  await page.$eval('[data-id="z5z5z5z"]', (el) => el.scrollIntoView());
  await page.waitForFunction(() => document.querySelector('[data-id="z5z5z5z"]').classList.contains('visto'), { timeout: 3000 }).catch(() => {});
  ok(await page.$eval('[data-id="z5z5z5z"]', (el) => el.classList.contains('visto')), 'Al aparecer: la clase, una vez.');
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.$eval('[data-id="z5z5z5z"]', (el) => el.scrollIntoView());
  await new Promise((r) => setTimeout(r, 200));
  ok(await page.$eval('[data-id="z5z5z5z"]', (el) => el.classList.contains('visto')), 'Volver a verlo no la quita.');

  // ------------------------------------------------------------------ Movimiento reducido.
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await page.goto(fixture, { waitUntil: 'load' });
  await page.waitForFunction(() => document.querySelector('[data-digi-actions-ready]'));
  await page.click('#ver-rejilla');
  ok((await display('g2g2g2g')) === 'grid' && !(await page.$eval('[data-id="g2g2g2g"]', (el) => el.classList.contains('animated'))), 'Con movimiento reducido se muestra sin animación.');
  ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
} finally {
  await browser.close();
}
if (failures.length) {
  console.error(`elements-f7b-actions-chrome.mjs: ${failures.length} de ${checks} comprobaciones fallan:\n - ${failures.join('\n - ')}`);
  process.exit(1);
}
console.log(`DIGITALÍSIMO Elements F7b: actions.js en Chrome en verde (${checks} comprobaciones).`);
