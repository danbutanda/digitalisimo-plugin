// F7b, frente G: la presentación de pestañas y acordeón anidados en Chrome real (sin interfaz), sobre
// tests/fixtures/f7b-nested-presentation.html, con las hojas del núcleo de los dos widgets y el marcado de su render()
// (pestañas con y sin .elementor-widget-container, unas pestañas anidadas dentro y un acordeón).
// - Sin estilo elegido, la hoja cargada no cambia nada: los estilos calculados de todos los títulos son los mismos que
//   sin la hoja (otro widget de la misma página que sí la usa no afecta a éste).
// - Cada estilo (píldoras, subrayado, recuadro, mínimo) en los dos widgets: el activo se marca con algo más que color
//   (sombra interior o subrayado), con el color del texto (currentColor), y las pestañas anidadas dentro no heredan.
// - Los controles de Estilo del usuario ganan ({{WRAPPER}}, 0,3,0): radio, fondo directo, sombra y borde.
// - Títulos de 24 px de alto como mínimo; :focus-visible con un contorno sólido del color del texto; contraste ≥ 3:1 del
//   indicador con colores de kit legibles; en colores forzados (sin sombras) el activo queda subrayado.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-f7b-presentation-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la presentación de anidados (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/f7b-nested-presentation.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const STYLES = ['pills', 'underline', 'boxed', 'minimal'];

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
try {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  await page.setViewport({ width: 1280, height: 900 });
  await page.goto(fixture, { waitUntil: 'load' });
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('#e-n-tab-title-11')).display === 'flex' && getComputedStyle(document.querySelector('#e-n-accordion-item-1 > summary')).display === 'flex' && !!document.getElementById('digi-presentation').sheet), 'Cargan las hojas del núcleo y la de la presentación.');

  // Utilidades en la página.
  await page.evaluate(() => {
    window.T = {
      el: (sel) => document.querySelector(sel),
      cs: (sel, props) => { const s = getComputedStyle(document.querySelector(sel)); return Object.fromEntries(props.map((p) => [p, s.getPropertyValue(p)])); },
      set: (id, style) => {
        const w = document.querySelector(`[data-id="${id}"]`);
        w.className = w.className.replace(/\s*digi-nested-\S+/g, '') + (style ? ` digi-nested-${style}` : '');
      },
      css: (id, text) => { document.getElementById(id).textContent = text; },
      sheet: (on) => { document.getElementById('digi-presentation').disabled = !on; },
      rgb: (c) => (c.match(/[\d.]+/g) || []).slice(0, 4).map(Number),
      lum: (c) => { const [r, g, b] = T.rgb(c).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; },
      bg: (el) => { for (let n = el; n; n = n.parentElement) { const c = getComputedStyle(n).backgroundColor; const a = T.rgb(c)[3]; if (!(c === 'transparent' || a === 0)) return c; } return 'rgb(255, 255, 255)'; },
      contrast: (a, b) => { const [x, y] = [T.lum(a), T.lum(b)].sort((m, n) => n - m); return (x + 0.05) / (y + 0.05); },
    };
  });
  const cs = (sel, props) => page.evaluate((s, p) => T.cs(s, p), sel, props);
  const set = (id, style) => page.evaluate((i, s) => T.set(i, s), id, style);
  const css = (id, text) => page.evaluate((i, t) => T.css(i, t), id, text);
  const SEL = {
    t1a: '#e-n-tab-title-11', t1i: '#e-n-tab-title-12', t9a: '#e-n-tab-title-91', t9i: '#e-n-tab-title-92',
    t2a: '#e-n-tab-title-21', t2i: '#e-n-tab-title-22',
    a1o: '#e-n-accordion-item-1 > summary', a1c: '#e-n-accordion-item-2 > summary',
  };
  const PROPS = ['box-shadow', 'border-top-left-radius', 'background-color', 'color', 'min-height', 'height', 'outline-style', 'text-decoration-line', 'border-top-width', 'padding-top'];
  const snapshot = async () => {
    const out = {};
    for (const [k, sel] of Object.entries(SEL)) out[k] = await cs(sel, PROPS);
    for (const sel of ['#e-n-tab-title-11 .e-n-tab-title-text', '#e-n-accordion-item-1 .e-n-accordion-item-title-text']) out[sel] = await cs(sel, ['text-decoration-line']);
    return JSON.stringify(out);
  };

  // 1. Sin estilo elegido, la hoja no cambia nada.
  await page.evaluate(() => T.sheet(false));
  const without = await snapshot();
  await page.evaluate(() => T.sheet(true));
  ok(without === await snapshot(), 'Sin estilo elegido, con la hoja cargada, los títulos se calculan igual que sin ella.');
  await set('t2', 'pills');
  const t1Alone = JSON.parse(await snapshot());
  ok(JSON.stringify(t1Alone.t1a) === JSON.stringify(JSON.parse(without).t1a) && JSON.stringify(t1Alone.a1o) === JSON.stringify(JSON.parse(without).a1o), 'Otro widget de la página con presentación no cambia a los que no la tienen.');
  await set('t2', '');

  // 2. Cada estilo.
  const shadow = (s) => s['box-shadow'];
  const sameColor = (s) => s['box-shadow'] === 'none' || s['box-shadow'].startsWith(s.color);
  for (const style of STYLES) {
    for (const id of ['t1', 't2', 'a1']) await set(id, style);
    const t1a = await cs(SEL.t1a, PROPS); const t1i = await cs(SEL.t1i, PROPS);
    const t2a = await cs(SEL.t2a, PROPS);
    const t9a = await cs(SEL.t9a, PROPS); const t9i = await cs(SEL.t9i, PROPS);
    const a1o = await cs(SEL.a1o, PROPS); const a1c = await cs(SEL.a1c, PROPS);
    const textA = (await cs('#e-n-tab-title-11 .e-n-tab-title-text', ['text-decoration-line']))['text-decoration-line'];
    const textI = (await cs('#e-n-tab-title-12 .e-n-tab-title-text', ['text-decoration-line']))['text-decoration-line'];
    const accA = (await cs('#e-n-accordion-item-1 .e-n-accordion-item-title-text', ['text-decoration-line']))['text-decoration-line'];
    const accC = (await cs('#e-n-accordion-item-2 .e-n-accordion-item-title-text', ['text-decoration-line']))['text-decoration-line'];
    const base = JSON.parse(without);
    ok(JSON.stringify(t9a) === JSON.stringify(base.t9a) && JSON.stringify(t9i) === JSON.stringify(base.t9i), `${style}: las pestañas anidadas dentro no heredan nada.`);
    ok([t1a, t1i, t2a, a1o, a1c].every(sameColor), `${style}: los indicadores usan el color del texto (currentColor).`);
    ok(shadow(t1a) === shadow(t2a), `${style}: igual con y sin .elementor-widget-container.`);
    const marked = (a, i, ta, ti) => shadow(a) !== shadow(i) || ta !== ti;
    ok(marked(t1a, t1i, textA, textI), `${style}: la pestaña activa se distingue por algo más que el color.`);
    ok(marked(a1o, a1c, accA, accC), `${style}: el elemento abierto del acordeón se distingue por algo más que el color.`);
    if (style === 'pills') {
      ok(t1a['border-top-left-radius'] === '999px' && /inset/.test(shadow(t1a)) && / 2px inset/.test(shadow(t1a)) && shadow(t1i) === 'none', 'pills: títulos redondos y anillo interior en el activo.');
      ok(a1o['border-top-left-radius'] !== '0px' && / 2px inset/.test(shadow(a1o)) && shadow(a1c) === 'none', 'pills: el acordeón, redondeado y con anillo en el abierto.');
    }
    if (style === 'underline') {
      ok(t1i['background-color'] === 'rgba(0, 0, 0, 0)' && /0px -1px 0px 0px inset/.test(shadow(t1i)) && /0px -3px 0px 0px inset/.test(shadow(t1a)), 'underline: pestañas sin fondo, raya fina y raya gruesa en la activa.');
      ok(a1o['border-top-width'] === '0px' && /0px -3px 0px 0px inset/.test(shadow(a1o)) && /0px -1px 0px 0px inset/.test(shadow(a1c)), 'underline: acordeón sin bordes, raya gruesa en el abierto.');
    }
    if (style === 'boxed') {
      ok(/0px 0px 0px 1px inset/.test(shadow(t1i)) && /0px 3px 0px 0px inset, .*0px 0px 0px 1px inset/.test(shadow(t1a)), 'boxed: recuadro fino y barra superior en la activa.');
      ok(a1o['border-top-width'] === '1px' && /0px 3px 0px 0px inset/.test(shadow(a1o)) && shadow(a1c) === 'none', 'boxed: el acordeón conserva sus bordes y marca el abierto con una barra.');
    }
    if (style === 'minimal') {
      ok(t1i['background-color'] === 'rgba(0, 0, 0, 0)' && textA === 'underline' && textI === 'none', 'minimal: sin fondo y el activo subrayado.');
      ok(a1o['border-top-width'] === '0px' && accA === 'underline' && accC === 'none', 'minimal: acordeón sin bordes y el abierto subrayado.');
    }
  }

  // 3. Los controles de Estilo del usuario ganan.
  for (const id of ['t1', 't2', 'a1']) await set(id, 'pills');
  await css('user', '.elementor-1 .elementor-element.elementor-element-t1{--n-tabs-title-border-radius:3px}.elementor-1 .elementor-element.elementor-element-t1 > .e-n-tabs > .e-n-tabs-heading > .e-n-tab-title[aria-selected="true"]{box-shadow:none}');
  let s = await cs(SEL.t1a, PROPS);
  ok(s['border-top-left-radius'] === '3px' && s['box-shadow'] === 'none', 'Radio y sombra del usuario ganan a las píldoras.');
  await set('t1', 'underline');
  await set('a1', 'underline');
  await css('user', '.elementor-1 .elementor-element.elementor-element-t1 > .e-n-tabs > .e-n-tabs-heading > .e-n-tab-title[aria-selected="false"]:not( :hover ){background:#123456}.elementor-1 .elementor-element.elementor-element-a1{--n-accordion-border-width:2px}');
  s = await cs(SEL.t1i, PROPS);
  ok(s['background-color'] === 'rgb(18, 52, 86)', 'El fondo del usuario gana al subrayado.');
  ok((await cs(SEL.a1o, PROPS))['border-top-width'] === '2px', 'El borde del usuario gana al acordeón sin bordes.');
  await css('user', '');

  // 4. 24 px como mínimo.
  await css('user', '.elementor-1 .elementor-element.elementor-element-t1{--n-tabs-title-padding-top:0px;--n-tabs-title-padding-bottom:0px;--n-tabs-title-font-size:8px;--n-tabs-title-padding-left:0px;--n-tabs-title-padding-right:0px}.elementor-1 .elementor-element.elementor-element-a1{--n-accordion-padding:0px;--n-accordion-title-font-size:8px}');
  for (const style of STYLES) {
    await set('t1', style);
    await set('a1', style);
    const h = await page.evaluate((a, b) => [document.querySelector(a).getBoundingClientRect().height, document.querySelector(b).getBoundingClientRect().height], SEL.t1a, SEL.a1c);
    ok(h[0] >= 23.9 && h[1] >= 23.9, `${style}: títulos de 24 px como mínimo aunque el usuario los encoja (${h.map(Math.round).join(', ')}).`);
  }
  await css('user', '');

  // 5. :focus-visible con el color del texto.
  for (const style of STYLES) {
    await set('t1', style);
    await set('a1', style);
    await page.evaluate(() => { document.activeElement && document.activeElement.blur(); window.scrollTo(0, 0); });
    await page.mouse.click(2, 2);
    let tab = null;
    let acc = null;
    for (let n = 0; n < 12 && !(tab && acc); n++) {
      await page.keyboard.press('Tab');
      const now = await page.evaluate(() => document.activeElement && (document.activeElement.id || (document.activeElement.parentElement && document.activeElement.parentElement.id)));
      if (now === 'e-n-tab-title-11') tab = await cs(SEL.t1a, ['outline-style', 'outline-width', 'outline-color', 'color']);
      if (now === 'e-n-accordion-item-1') acc = await cs(SEL.a1o, ['outline-style', 'outline-width', 'outline-color', 'color']);
    }
    ok(tab && tab['outline-style'] === 'solid' && tab['outline-width'] === '2px' && tab['outline-color'] === tab.color, `${style}: foco visible en la pestaña con el color del texto.`);
    ok(acc && acc['outline-style'] === 'solid' && acc['outline-color'] === acc.color, `${style}: foco visible en el acordeón con el color del texto.`);
  }

  // 6. Contraste del indicador con colores de kit legibles.
  await css('kit', ':root{--e-global-color-accent:#1d4ed8;--e-global-color-secondary:#374151}');
  for (const style of STYLES) {
    for (const id of ['t1', 'a1']) await set(id, style);
    const ratios = await page.evaluate((sels) => sels.map((sel) => {
      const el = document.querySelector(sel);
      const st = getComputedStyle(el);
      return T.contrast(st.color, T.bg(el));
    }), [SEL.t1a, SEL.t1i, SEL.a1o, SEL.a1c]);
    ok(ratios.every((r) => r >= 3), `${style}: indicador (color del texto sobre el fondo del título) con contraste ≥ 3:1 (${ratios.map((r) => r.toFixed(1)).join(', ')}).`);
  }
  await css('kit', '');

  // 7. Colores forzados: sin sombras, el activo queda subrayado.
  // puppeteer no admite forced-colors en emulateMediaFeatures(): se pide por CDP.
  const cdp = await page.createCDPSession();
  await cdp.send('Emulation.setEmulatedMedia', { features: [{ name: 'forced-colors', value: 'active' }] });
  ok(await page.evaluate(() => matchMedia('(forced-colors: active)').matches), 'Colores forzados emulados.');
  for (const style of STYLES) {
    for (const id of ['t1', 'a1']) await set(id, style);
    const lines = await page.evaluate(() => [
      getComputedStyle(document.querySelector('#e-n-tab-title-11 .e-n-tab-title-text')).textDecorationLine,
      getComputedStyle(document.querySelector('#e-n-tab-title-12 .e-n-tab-title-text')).textDecorationLine,
      getComputedStyle(document.querySelector('#e-n-accordion-item-1 .e-n-accordion-item-title-text')).textDecorationLine,
    ]);
    ok(lines[0] === 'underline' && lines[1] === 'none' && lines[2] === 'underline', `${style}: en colores forzados, activo y abierto subrayados.`);
  }
  await cdp.send('Emulation.setEmulatedMedia', { features: [{ name: 'forced-colors', value: 'none' }] });
  ok(errors.length === 0, `Sin errores en la página: ${errors.join(' | ')}`);
} finally {
  await browser.close();
}
console.log(`DIGITALÍSIMO Elements: presentación de anidados en Chrome en verde (${checks} comprobaciones).`);
