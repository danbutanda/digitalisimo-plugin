// F7b, frente G: la hoja de la presentación de anidados (modules/digitalisimo-f7b/assets/css/nested-presentation.css).
// - Especificidad (C2-24): cada selector es 0,0,0 (todo dentro de :where()) salvo las declaraciones de variables del
//   núcleo en el envoltorio, que son exactamente 0,2,0 (.digi-nested-<estilo>.elementor-widget-n-<widget>): ganan a los
//   valores por defecto del núcleo (0,1,0) sin depender del orden de carga y pierden con cualquier control de Estilo,
//   que el núcleo escribe con {{WRAPPER}} (.elementor-<doc> .elementor-element.elementor-element-<id>, 0,3,0).
// - Esas variables existen en la hoja del núcleo del widget (son «las del control»), y ninguna regla del núcleo sobre
//   los títulos declara las propiedades que la hoja pone con especificidad 0 (box-shadow, outline, min-block-size,
//   text-decoration), así que no se pierden en silencio.
// - Sin paleta propia: sólo currentColor y transparent; sin url(), !important ni variables del kit.
// - Todo selector está acotado a un envoltorio con presentación; tope de 4 KB y objetivo de 2,5 KB.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const CORE = join(ROOT, 'digitalisimo-elements/elementor/assets/css');
const PATH = join(ROOT, 'digitalisimo-elements/modules/digitalisimo-f7b/assets/css/nested-presentation.css');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// Reglas de una hoja: [{ selectors: string[], body: string, media: string }].
const rules = (css) => {
  css = css.replace(/\/\*[\s\S]*?\*\//g, '');
  const out = [];
  const walk = (text, media) => {
    let i = 0;
    while (i < text.length) {
      const open = text.indexOf('{', i);
      if (open < 0) break;
      const prelude = text.slice(i, open).trim();
      let depth = 1;
      let j = open + 1;
      for (; j < text.length && depth > 0; j++) {
        if (text[j] === '{') depth++;
        else if (text[j] === '}') depth--;
      }
      const body = text.slice(open + 1, j - 1);
      if (prelude.startsWith('@media')) walk(body, prelude);
      else if (!prelude.startsWith('@')) out.push({ selectors: split(prelude), body, media });
      i = j;
    }
  };
  walk(css, '');
  return out;
};
// Separa por comas de primer nivel (no dentro de paréntesis ni corchetes).
const split = (text) => {
  const parts = [];
  let depth = 0;
  let start = 0;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (c === '(' || c === '[') depth++;
    else if (c === ')' || c === ']') depth--;
    else if (c === ',' && depth === 0) { parts.push(text.slice(start, i).trim()); start = i + 1; }
  }
  parts.push(text.slice(start).trim());
  return parts.filter(Boolean);
};
// Especificidad de un selector complejo (Selectors 4: :where() 0; :is()/:not()/:has() la mayor de sus argumentos).
const add = (a, b) => [a[0] + b[0], a[1] + b[1], a[2] + b[2]];
const cmp = (a, b) => a[0] - b[0] || a[1] - b[1] || a[2] - b[2];
const specificity = (selector) => {
  let s = [0, 0, 0];
  let i = 0;
  while (i < selector.length) {
    const c = selector[i];
    if (c === ':') {
      const pseudoElement = selector[i + 1] === ':';
      const m = /^::?([a-zA-Z-]+)/.exec(selector.slice(i));
      const name = m[1].toLowerCase();
      i += m[0].length;
      let args = null;
      if (selector[i] === '(') {
        let depth = 1;
        let j = i + 1;
        for (; j < selector.length && depth > 0; j++) {
          if (selector[j] === '(') depth++;
          else if (selector[j] === ')') depth--;
        }
        args = selector.slice(i + 1, j - 1);
        i = j;
      }
      if (pseudoElement) s = add(s, [0, 0, 1]);
      else if (name === 'where') { /* 0 */ }
      else if (['is', 'not', 'has', 'matches'].includes(name)) s = add(s, split(args).map(specificity).sort(cmp).pop() || [0, 0, 0]);
      else s = add(s, [0, 1, 0]);
      continue;
    }
    if (c === '[') { const j = selector.indexOf(']', i); s = add(s, [0, 1, 0]); i = j + 1; continue; }
    if (c === '.') { const m = /^\.[\w-]+/.exec(selector.slice(i)); s = add(s, [0, 1, 0]); i += m[0].length; continue; }
    if (c === '#') { const m = /^#[\w-]+/.exec(selector.slice(i)); s = add(s, [1, 0, 0]); i += m[0].length; continue; }
    if (/[a-zA-Z]/.test(c)) { const m = /^[a-zA-Z][\w-]*/.exec(selector.slice(i)); s = add(s, [0, 0, 1]); i += m[0].length; continue; }
    i++;
  }
  return s;
};
// La calculadora, contra casos conocidos.
ok(cmp(specificity('.elementor-1 .elementor-element.elementor-element-abc'), [0, 3, 0]) === 0, '{{WRAPPER}} es 0,3,0.');
ok(cmp(specificity('.elementor-widget-n-tabs'), [0, 1, 0]) === 0, 'El valor por defecto del núcleo es 0,1,0.');
ok(cmp(specificity(':where(.a .b:focus-visible)'), [0, 0, 0]) === 0 && cmp(specificity(':is(.a,#b) .c'), [1, 1, 0]) === 0 && cmp(specificity('a::after'), [0, 0, 2]) === 0, 'La calculadora conoce :where, :is y los seudoelementos.');

const css = readFileSync(PATH, 'utf8');
const bytes = statSync(PATH).size;
ok(bytes <= 4096, `nested-presentation.css cabe en 4 KB (${bytes} B).`);
ok(bytes <= 2560, `nested-presentation.css cumple el objetivo de 2,5 KB (${bytes} B).`);
const own = rules(css);
ok(own.length >= 10, `Se leyeron las reglas de la hoja (${own.length}).`);

// Variables por defecto de las hojas del núcleo.
const coreVars = new Set();
const coreRules = [];
for (const name of ['widget-nested-tabs.min.css', 'widget-nested-accordion.min.css']) {
  for (const rule of rules(readFileSync(join(CORE, name), 'utf8'))) {
    coreRules.push(rule);
    for (const m of rule.body.matchAll(/(--n-[a-z-]+)\s*:/g)) coreVars.add(m[1]);
  }
}
ok(coreVars.has('--n-tabs-title-border-radius') && coreVars.has('--n-accordion-border-width'), `Variables del núcleo leídas (${coreVars.size}).`);

const user = specificity('.elementor-1 .elementor-element.elementor-element-abc');
const coreDefault = specificity('.elementor-widget-n-tabs');
let wrapperRules = 0;
let whereSelectors = 0;
for (const rule of own) {
  for (const selector of rule.selectors) {
    const s = specificity(selector);
    ok(cmp(s, user) < 0, `${selector.slice(0, 80)}: pierde con cualquier control de Estilo (0,3,0).`);
    if (cmp(s, [0, 0, 0]) === 0) {
      whereSelectors++;
      ok(/digi-nested-/.test(selector), `${selector.slice(0, 80)}: acotado a un envoltorio con presentación.`);
      continue;
    }
    ok(cmp(s, [0, 2, 0]) === 0 && /^\.digi-nested-(pills|underline|boxed|minimal)\.elementor-widget-n-(tabs|accordion)$/.test(selector) && !rule.media, `${selector}: sólo el envoltorio puede pasar de 0, y con exactamente 0,2,0.`);
    ok(cmp(s, coreDefault) > 0, `${selector}: gana al valor por defecto del núcleo sin depender del orden de carga.`);
    const declarations = rule.body.split(';').map((d) => d.trim()).filter(Boolean);
    for (const d of declarations) {
      const [prop] = d.split(':');
      ok(coreVars.has(prop.trim()), `${selector}: sólo variables del núcleo (${prop.trim()}).`);
    }
    wrapperRules++;
  }
}
ok(wrapperRules >= 4 && whereSelectors >= 10, `Variables del envoltorio en ${wrapperRules} selectores; ${whereSelectors} selectores con especificidad 0.`);

// Lo que la hoja pone con especificidad 0 no lo pisa el núcleo en los títulos.
const zeroProps = ['box-shadow', 'outline', 'outline-offset', 'min-block-size', 'text-decoration-line', 'text-decoration-thickness', 'text-underline-offset'];
const hits = [];
for (const rule of coreRules) {
  if (!rule.selectors.some((s) => /e-n-tab-title|e-n-accordion-item-title/.test(s))) continue;
  for (const d of rule.body.split(';')) {
    const prop = d.split(':')[0].trim();
    if (zeroProps.includes(prop) || (prop === 'outline' && !/:not\(:focus-visible\)/.test(rule.selectors.join(',')))) hits.push(`${rule.selectors[0]} { ${d.trim()} }`);
  }
}
ok(hits.every((h) => /:not\(:focus-visible\)/.test(h)), `El núcleo no declara en los títulos lo que la hoja pone con especificidad 0: ${hits.join(' | ')}`);

// Sin paleta propia.
const values = own.flatMap((r) => r.body.split(';').map((d) => d.split(':').slice(1).join(':').trim()).filter(Boolean));
for (const value of values) {
  const words = value.replace(/currentColor|transparent|inset|solid|underline|none/g, '').replace(/-?[\d.]+(px|em|rem|%)?/g, '').replace(/[\s,]/g, '');
  ok(words === '', `Valor sin colores propios: ${value}`);
}
ok(!/!important|url\(|--e-global|#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i.test(css), 'Sin !important, url(), variables del kit ni colores literales.');
ok(/:focus-visible[^{]*\)\{outline:2px solid currentColor/.test(css), ':focus-visible con currentColor.');
ok(/min-block-size:24px/.test(css), 'Títulos de 24 px de alto como mínimo.');
ok(/@media \(forced-colors:active\)/.test(css), 'En colores forzados (sin sombras), el activo se subraya.');

console.log(`DIGITALÍSIMO Elements: hoja de la presentación de anidados en verde (${checks} comprobaciones, ${bytes} B).`);
