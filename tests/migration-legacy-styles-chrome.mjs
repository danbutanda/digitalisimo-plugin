// F8, frente B: los controles de estilo heredados de los 6 mapas corregidos y de Advanced Icon Box aplican de verdad
// sobre el marcado de su widget de destino, en Chrome real (sin interfaz) y sin Element Pack.
// - Marcado: tests/fixtures/legacy-styles/<widget>.html, copia del render() de cada destino (la prueba sin navegador
//   comprueba que cada clase sale de su PHP).
// - Cada control de `maps/<mapa>.styles.php` se convierte en CSS con un valor sintético (#123456, 17px, 1 2 3 4…, como
//   hace Elementor con {{WRAPPER}}, {{VALUE}}, {{SIZE}}{{UNIT}}, {{TOP}}…) y se exige que alguna de sus partes case con
//   un elemento del marcado y que, al añadirlo, cambie el estilo computado de ese elemento (o de su ::before/::after).
//   Los estados (:hover, :focus…) se fuerzan con un atributo.
// - Lo retirado por estructura (`unmatched` en tests/fixtures/f8-legacy-styles-dead.json) no casa con nada.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/migration-legacy-styles-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { parsePhp, splitSelector } from './fixtures/legacy-styles/styles-lib.mjs';
import { stylePairs, collectStyles, STYLE_PROPS } from './playground/legacy-compare.mjs';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los estilos heredados (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const root = fileURLToPath(new URL('..', import.meta.url));
const MAPS = {
  'bdt-fancy-slider': 'digitalisimo-fancy-slider',
  'bdt-trailer-box': 'digitalisimo-fancy-card',
  'bdt-animated-card': 'digitalisimo-fancy-card',
  'bdt-interactive-card': 'digitalisimo-fancy-card',
  'bdt-advanced-progress-bar': 'digitalisimo-progress-bars',
  'bdt-profile-card': 'author-box',
  'bdt-advanced-icon-box': 'digitalisimo-advanced-icon-box',
};
const dead = JSON.parse(readFileSync(join(root, 'tests/fixtures/f8-legacy-styles-dead.json'), 'utf8')).maps;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

const COLOR = 'rgb(18, 52, 86)';
const GROUP_CSS = {
  typography: 'font-size: 17px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;',
  background: `background-color: ${COLOR};`,
  border: `border-style: dashed; border-width: 3px; border-color: ${COLOR};`,
  'box-shadow': `box-shadow: ${COLOR} 1px 2px 3px 0px;`,
  'text-shadow': `text-shadow: ${COLOR} 1px 2px 3px;`,
  'css-filter': 'filter: blur(2px);',
  'text-stroke': `-webkit-text-stroke: 2px ${COLOR};`,
};

// CSS de un control con valores sintéticos; measurable = si se espera un cambio del estilo computado.
function cssOf(control) {
  if ('group' in control) return [{ selector: control.selector, css: GROUP_CSS[control.group] || '', measurable: control.group in GROUP_CSS }];
  return Object.entries(control.selectors).map(([selector, template]) => {
    let css = String(template);
    let measurable = ['color', 'slider', 'dimensions', 'number'].includes(control.type);
    const size = /opacity|scale\(/.test(css) ? '0.37' : '17';
    if (control.type === 'color') css = css.replaceAll('{{VALUE}}', COLOR);
    if (control.type === 'number') css = css.replaceAll('{{VALUE}}', '7');
    // La unidad del valor por defecto del control (deg en un giro, % o em…), o px.
    const unit = (control.default && control.default.unit) || (Array.isArray(control.size_units) && control.size_units[0]) || (/rotate\(/.test(css) ? 'deg' : 'px');
    css = css.replaceAll('{{SIZE}}', size).replaceAll('{{UNIT}}', unit)
      .replaceAll('{{TOP}}', '1').replaceAll('{{RIGHT}}', '2').replaceAll('{{BOTTOM}}', '3').replaceAll('{{LEFT}}', '4');
    if (css.includes('{{VALUE}}')) {
      const dict = control.selectors_dictionary && typeof control.selectors_dictionary === 'object' ? Object.values(control.selectors_dictionary) : [];
      const options = control.options && typeof control.options === 'object' && !Array.isArray(control.options) ? Object.keys(control.options).filter(Boolean) : [];
      const value = dict.length ? dict[dict.length - 1] : (options.length ? options[options.length - 1] : 'center');
      css = css.replaceAll('{{VALUE}}', value);
      measurable = false;
    }
    if (/\{\{/.test(css)) measurable = false;
    return { selector, css, measurable };
  });
}

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
try {
  for (const [id, fixture] of Object.entries(MAPS)) {
    const styles = parsePhp(readFileSync(join(root, `digitalisimo-migration/legacy/maps/${id}.styles.php`), 'utf8'), id);
    const rules = styles.map((control) => ({ name: control.name, rules: cssOf(control).map((r) => ({ ...r, parts: splitSelector(r.selector) })) }));
    const removed = (dead[id]?.controls || []).filter((row) => row.after === null && row.unmatched).flatMap((row) => row.unmatched.map((part) => ({ name: row.name, part })));
    const page = await browser.newPage();
    page.on('pageerror', (e) => errors.push(e.message));
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(pathToFileURL(join(root, `tests/fixtures/legacy-styles/${fixture}.html`)).href);
    const result = await page.evaluate((rules, removed) => {
      const STATE = /:(?:hover|focus-visible|focus-within|focus|active)\b/g;
      const PSEUDO = /::?(before|after)\b/;
      for (const el of document.querySelectorAll('*')) el.setAttribute('data-st', '');
      // Un ancho de borde sólo se computa con un estilo de borde: la base lo da (con ancho 0) para poder medirlo.
      const base = document.createElement('style');
      base.textContent = '.elementor-element-legacy *{border-style:solid;border-width:0}';
      document.head.appendChild(base);
      const prep = (part) => part.replaceAll('{{WRAPPER}}', '.elementor-element-legacy').replaceAll('{{CURRENT_ITEM}}', '.elementor-repeater-item-legacy').replace(STATE, '[data-st]');
      const query = (part) => {
        const sel = prep(part).replace(/::?(?:before|after|placeholder|marker)\b/g, '');
        try { return [...document.querySelectorAll(sel)]; } catch (e) { return null; }
      };
      const props = (css) => [...css.matchAll(/(--?[a-zA-Z-]+|[a-zA-Z-]+)\s*:/g)].map((m) => m[1]).filter((p, i, a) => a.indexOf(p) === i);
      const snapshot = (targets, list) => targets.map(([el, pseudo]) => { const cs = getComputedStyle(el, pseudo); return list.map((p) => cs.getPropertyValue(p)); });
      const out = [];
      for (const control of rules) {
        let matched = 0;
        let changed = false;
        let measurable = false;
        const bad = [];
        for (const rule of control.rules) {
          const targets = [];
          for (const part of rule.parts) {
            const nodes = query(part);
            if (nodes === null) { bad.push(part); continue; }
            matched += nodes.length;
            const pseudo = (PSEUDO.exec(part) || [])[1];
            for (const el of nodes) targets.push([el, pseudo ? '::' + pseudo : null]);
          }
          if (!rule.measurable || !targets.length) continue;
          measurable = true;
          const list = props(rule.css);
          const before = snapshot(targets, list);
          const style = document.createElement('style');
          style.textContent = rule.parts.map(prep).join(', ') + '{' + rule.css + '}';
          document.head.appendChild(style);
          const after = snapshot(targets, list);
          style.remove();
          if (JSON.stringify(before) !== JSON.stringify(after)) changed = true;
        }
        out.push({ name: control.name, matched, changed, measurable, bad });
      }
      const gone = removed.map(({ name, part }) => ({ name, part, count: (query(part) || []).length }));
      return { out, gone };
    }, rules, removed);
    // La firma de estilos de legacy-compare.mjs encuentra en el destino cada parte que el mapa traduce.
    const pairs = stylePairs(readFileSync(join(root, `digitalisimo-migration/legacy/maps/${id}.php`), 'utf8')).filter((pair) => !/:(?:hover|focus|active)/.test(pair.own));
    const signature = await page.evaluate(collectStyles, pairs, 'own', STYLE_PROPS);
    const lost = pairs.filter((pair) => !signature[pair.key] || !signature[pair.key].color);
    ok(pairs.length > 0 && lost.length === 0, `${id}: la firma de estilos encuentra cada parte traducida (${lost.map((pair) => pair.own).join(', ')})`);
    await page.close();
    for (const r of result.out) {
      ok(r.matched > 0 && r.bad.length === 0, `${id} ${r.name}: casa con el marcado de ${fixture}${r.bad.length ? ' (selector inválido: ' + r.bad.join(', ') + ')' : ''}`);
      if (r.measurable) ok(r.changed, `${id} ${r.name}: cambia el estilo computado de la parte mapeada`);
    }
    for (const g of result.gone) ok(g.count === 0, `${id} ${g.name}: lo retirado (${g.part}) no casa con nada`);
    const measured = result.out.filter((r) => r.measurable).length;
    ok(measured >= Math.min(10, result.out.length / 2), `${id}: ${measured} de ${result.out.length} controles con estilo computado medido`);
  }
} finally {
  await browser.close();
}
ok(errors.length === 0, `Sin errores de JavaScript: ${errors.join(' · ')}`);
console.log(`Migración: ${checks} comprobaciones en Chrome de los estilos heredados de los 6 mapas y Advanced Icon Box.`);
