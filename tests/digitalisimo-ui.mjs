// Diseño compartido y paleta propia: los siete plugins llevan la misma copia de digitalisimo-ui/, sus tokens son
// válidos, cada respaldo `var(--digi-x, valor)` repite el valor del token, y nada propio que vea el administrador trae
// la paleta del constructor original.
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { createHash } from 'node:crypto';

const modules = ['digitalisimo-seo', 'digitalisimo.chatbot', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements', 'digitalisimo-migration'];
const loader = readFileSync('digitalisimo-elements/digitalisimo-ui/digitalisimo-ui.php', 'utf8');
const shared = readFileSync('digitalisimo-elements/digitalisimo-ui/digitalisimo-ui.css', 'utf8');
for (const module of modules) {
  assert.equal(readFileSync(`${module}/digitalisimo-ui/digitalisimo-ui.php`, 'utf8'), loader, `${module} debe llevar el mismo cargador del diseño compartido`);
  assert.equal(readFileSync(`${module}/digitalisimo-ui/digitalisimo-ui.css`, 'utf8'), shared, `${module} debe llevar la misma hoja compartida`);
}
// Cada versión publicada tiene un contenido fijo: cambiarlo sin subir la versión dejaría la hoja anterior en la caché
// de los navegadores (la versión va en la URL) y mezclaría copias distintas con la misma versión entre plugins.
const RELEASED = { '1.0.0': 'cdd8fd1af34e28fcac58839f5e70d73f4ca069f5cf7d0a5a70636967d3e9fbe7' };
const version = loader.match(/\$GLOBALS\['digitalisimo_ui_copies'\]\['([^']+)'\]/)[1];
const fingerprint = createHash('sha256').update(loader).update(shared).digest('hex');
assert.ok(RELEASED[version], `La versión ${version} del diseño compartido no tiene huella en tests/digitalisimo-ui.mjs`);
assert.equal(fingerprint, RELEASED[version], `El diseño compartido cambió sin subir su versión (${version}): súbela en digitalisimo-ui.php y registra la huella nueva (${fingerprint})`);
for (const module of ['digitalisimo.chatbot', 'digitalisimo-hosting', 'digitalisimo-backups']) {
  assert.ok(!existsSync(`${module}/assets/admin-ui.css`), `${module} no debe conservar su copia propia del estilo`);
}

// Tokens: valores literales, ninguno definido en función de sí mismo (una referencia circular invalida el token).
const root = shared.match(/:root\s*\{([^}]*)\}/)[1];
const tokens = new Map([...root.matchAll(/--(digi-[a-z-]+)\s*:\s*([^;]+);/g)].map(([, name, value]) => [name, value.trim()]));
for (const required of ['digi-blue', 'digi-violet', 'digi-pink', 'digi-ink', 'digi-line', 'digi-gradient', 'digi-gradient-hover', 'digi-radius-control']) assert.ok(tokens.has(required), `Falta el token --${required}`);
const resolve = (value, seen = []) => value.replace(/var\(--(digi-[a-z-]+)\)/g, (_, name) => {
  assert.ok(!seen.includes(name), `El token --${name} se define en función de sí mismo`);
  assert.ok(tokens.has(name), `El token --${name} no existe`);
  return resolve(tokens.get(name), [...seen, name]);
});
const normal = (value) => value.toLowerCase().replace(/\s+/g, '').replace(/#([0-9a-f])([0-9a-f])([0-9a-f])\b/g, '#$1$1$2$2$3$3');
const resolved = new Map([...tokens].map(([name, value]) => [name, normal(resolve(value, [name]))]));

// Cada respaldo repite el valor del token: sin la hoja compartida (lienzo del editor) se ve igual.
const files = [];
const walk = (dir) => { for (const entry of readdirSync(dir)) { const path = join(dir, entry); if (statSync(path).isDirectory()) { if (!['elementor', 'vendor', 'node_modules'].includes(entry)) walk(path); } else if (/\.(css|php|js)$/.test(entry) && !/\.min\./.test(entry)) files.push(path); } };
for (const module of modules) walk(module);
let fallbacks = 0;
for (const file of files) {
  const source = readFileSync(file, 'utf8');
  for (let at = source.indexOf('var(--digi-'); at >= 0; at = source.indexOf('var(--digi-', at + 1)) {
    let depth = 0, end = at;
    for (; end < source.length; end++) { if (source[end] === '(') depth++; else if (source[end] === ')' && --depth === 0) break; }
    const inner = source.slice(at + 4, end);
    const comma = inner.indexOf(',');
    if (comma < 0) continue;
    const name = inner.slice(2, comma).trim();
    if (!resolved.has(name)) continue; // variables propias de un widget (--digi-carousel-*, --digi-link-speed…)
    fallbacks++;
    assert.equal(normal(inner.slice(comma + 1)), resolved.get(name), `${file}: el respaldo de --${name} no coincide con el token`);
  }
}
assert.ok(fallbacks > 100, 'La paleta debe usar los tokens con respaldo');

// La paleta del constructor sólo cambia su interfaz: nunca el kit del sitio.
const palettes = ['palette.css', 'palette-dark.css', 'palette-preview.css'].map((name) => readFileSync(`digitalisimo-elements/assets/digitalisimo/${name}`, 'utf8').replace(/\/\*[\s\S]*?\*\//g, ''));
for (const css of palettes) {
  assert.ok(!/--e-global-/.test(css), 'La paleta no debe tocar --e-global-* (colores y tipografías del kit del sitio)');
  assert.ok(!/--digi-[a-z-]+\s*:/.test(css), 'La paleta usa los tokens compartidos; no los redefine');
  for (const [, name, value] of css.matchAll(/(--[a-z-]+)\s*:\s*([^;]+);/g)) assert.ok(!value.includes(`var(${name})`), `${name} se define en función de sí mismo`);
}
assert.ok(palettes[0].includes('html:root') && palettes[2].includes('html:root'), 'Las variables se redefinen con html:root, que gana a los :root del núcleo sin depender del orden');

// Ningún archivo propio (o derivado de PRO) que vea el administrador trae la paleta del constructor original.
const elementor = /#(F3BAFD|F5D0FE|F0ABFC|EB8EFB|E879F9|D004D4|C00BB9|E73CF6|D355CE|93003F|92003B|7E013B|B51243|8F1A4C|A93365|D6107C|310132|FAE8FF|524CFF|1DDDBF|5EEAD4|58D0F5|10BCF2)\b|rgba\(\s*255\s*,\s*0\s*,\s*191/i;
const own = [
  ...modules.flatMap((module) => [`${module}/digitalisimo-ui/digitalisimo-ui.css`]),
  'digitalisimo-elements/assets/digitalisimo/identity.css', 'digitalisimo-elements/assets/digitalisimo/palette.css', 'digitalisimo-elements/assets/digitalisimo/palette-dark.css', 'digitalisimo-elements/assets/digitalisimo/palette-preview.css',
  'digitalisimo-elements/digitalisimo-brand.php', 'digitalisimo-elements/assets/js/packages/editor-components-extended/editor-components-extended.js', 'digitalisimo-elements/assets/js/packages/editor-components-extended/editor-components-extended.min.js',
  'digitalisimo-elements/assets/js/notes/notes-app-initiator.js', 'digitalisimo-elements/assets/js/notes/notes-app-initiator.min.js', 'digitalisimo-elements/assets/css/modules/notes.css', 'digitalisimo-elements/assets/css/modules/notes.min.css', 'digitalisimo-elements/modules/notes/notifications/views/email.php',
  'digitalisimo-seo/assets/admin-ui.css', 'digitalisimo-backups/assets/backups-admin.css',
];
const svgs = [];
const walkSvg = (dir) => { for (const entry of readdirSync(dir)) { const path = join(dir, entry); if (statSync(path).isDirectory()) walkSvg(path); else if (entry.endsWith('.svg')) svgs.push(path); } };
walkSvg('digitalisimo-elements/modules/popup/assets/images');
walkSvg('digitalisimo-elements/modules/theme-builder/assets/images');
assert.ok(svgs.length >= 19, 'Deben revisarse las ilustraciones de popups y del Theme Builder');
for (const file of [...own, ...svgs]) assert.ok(!elementor.test(readFileSync(file, 'utf8')), `${file} trae un color de la paleta del constructor original`);
console.log(`Diseño compartido: 7 copias idénticas, ${tokens.size} tokens válidos, ${fallbacks} respaldos coherentes y sin la paleta del constructor original.`);
