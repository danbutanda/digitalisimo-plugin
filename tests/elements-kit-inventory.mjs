// Kits (etapa K, §13.1 «inventory» y §9.2): inventario estático de lo que debe viajar en un kit.
// - Cada widget propio del registro (los 44 visibles y los 7 alias ocultos de modules/digitalisimo-widgets/
//   class-registry.php) y el Slider Optimizado tienen su caso en tests/playground/kit-roundtrip/cases.json, con la
//   marca de alias igual a la del registro; ningún caso nombra un widget propio que no existe.
// - Cada rasgo propio que inicia el registro tiene su caso, y cada «base» de front-budget existe.
// - Cada parte de K (Parts::BUILTIN) tiene su caso con motivo (§11.2: obligación de cada etapa).
// - tests/sources/kits/no-reference.json: cada control que parece referencia y no lo es lleva su motivo.
// El inventario en tiempo de ejecución (todos los controles de todos los widgets) lo hace kit-roundtrip.sh.
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const E = join(ROOT, 'digitalisimo-elements');
const read = (p) => readFileSync(join(ROOT, p), 'utf8');
const cases = JSON.parse(read('tests/playground/kit-roundtrip/cases.json'));
const budget = JSON.parse(read('tests/playground/front-budget/cases.json'));

// Registro de widgets propios: las entradas sin «css» son alias ocultos de un widget fusionado.
const registry = read('digitalisimo-elements/modules/digitalisimo-widgets/class-registry.php');
const block = registry.slice(registry.indexOf('const WIDGETS = array('), registry.indexOf('const VARIANT_STYLES'));
const names = [...block.matchAll(/^\t\t'(digitalisimo-[a-z0-9-]+)' => array\(/gm)].map((m) => m[1]);
assert.ok(names.length >= 51, `El registro de widgets propios debería tener al menos 51 entradas (tiene ${names.length})`);
const own = {};
names.forEach((name, i) => {
  const start = block.indexOf(`'${name}' => array(`);
  const end = i + 1 < names.length ? block.indexOf(`'${names[i + 1]}' => array(`) : block.length;
  own[name] = { alias: !block.slice(start, end).includes("'css'") };
});
const slider = read('digitalisimo-elements/modules/digitalisimo-slider/class-widget.php').match(/get_name\(\)\s*\{\s*return '([a-z0-9-]+)'/);
assert.ok(slider, 'No se encontró el nombre del Slider Optimizado');
own[slider[1]] = { alias: false };
const visible = Object.values(own).filter((w) => !w.alias).length;
const aliases = Object.values(own).filter((w) => w.alias).length;

const problems = [];
for (const [name, w] of Object.entries(own)) {
  const c = cases.widgets[name];
  if (!c) { problems.push(`${name}: sin caso en kit-roundtrip/cases.json`); continue; }
  if (!!c.alias !== w.alias) problems.push(`${name}: el caso dice alias=${!!c.alias} y el registro ${w.alias}`);
  if (!c.base && !c.settings) problems.push(`${name}: el caso no tiene ajustes ni «base»`);
  if (c.base) {
    const m = String(c.base).match(/^front-budget:(.+)$/);
    if (!m || !budget[m[1]]) problems.push(`${name}: «base» ${c.base} no existe en front-budget/cases.json`);
    else if (!budget[m[1]].widgets.some((x) => x.type === name)) problems.push(`${name}: el caso ${c.base} de front-budget no tiene ese widget`);
  }
}
for (const name of Object.keys(cases.widgets)) {
  if (!own[name]) problems.push(`${name}: caso de un widget propio que no está en el registro`);
}

// Rasgos que inicia el registro (archivos class-*.php de Registry::init()).
const traitFiles = [...registry.matchAll(/'class-([a-z0-9-]+)\.php' => [A-Za-z_]+::class/g)].map((m) => m[1]);
const extensionFiles = [...registry.matchAll(/require_once __DIR__ \. '\/class-([a-z0-9-]+)\.php';\s*\n\s*[A-Za-z_]+_Extension::init\(\);/g)].map((m) => m[1]);
for (const trait of [...new Set([...traitFiles, ...extensionFiles])]) {
  if (!cases.traits[trait]) problems.push(`rasgo ${trait}: sin caso en kit-roundtrip/cases.json`);
}
for (const [trait, c] of Object.entries(cases.traits)) {
  if (!c.widget) problems.push(`rasgo ${trait}: el caso no dice en qué widget se prueba`);
}

// Partes de K (§11.1): las que declara Parts::BUILTIN, cada una con su caso y su motivo.
const partsFile = join(E, 'modules/digitalisimo-import/kits/class-parts.php');
assert.ok(existsSync(partsFile), 'Falta kits/class-parts.php (contrato de K)');
const builtin = readFileSync(partsFile, 'utf8').match(/const BUILTIN\s*=\s*array\(([\s\S]*?)\);/);
assert.ok(builtin, 'class-parts.php: falta la constante BUILTIN');
const parts = [...builtin[1].matchAll(/'([a-z0-9-]+)'\s*=>/g)].map((m) => m[1]);
assert.ok(parts.length >= 7, `Parts::BUILTIN debería declarar las 7 partes de K (declara ${parts.length})`);
for (const part of parts) {
  const c = (cases.parts || {})[part];
  if (!c || !c.case || !c.motivo) problems.push(`parte ${part}: sin caso con «case» y «motivo» en kit-roundtrip/cases.json`);
}
for (const part of Object.keys(cases.parts || {})) {
  if (!parts.includes(part)) problems.push(`parte ${part}: caso de una parte que Parts::BUILTIN no declara`);
}

// Controles que parecen referencia y no lo son (compare.mjs los acepta sólo con motivo).
const noRef = JSON.parse(read('tests/sources/kits/no-reference.json'));
for (const entry of noRef.controles || []) {
  if (!entry.widget || !entry.control || !entry.motivo) problems.push(`no-reference.json: entrada sin widget, control o motivo: ${JSON.stringify(entry)}`);
}

assert.deepEqual(problems, [], `Inventario de kits incompleto:\n  ${problems.join('\n  ')}`);
console.log(`Kits: inventario con ${visible} widgets propios visibles (con el Slider Optimizado) y ${aliases} alias en kit-roundtrip, ${Object.keys(cases.traits).length} rasgos, ${parts.length} partes con caso y ${(noRef.controles || []).length} controles sin referencia justificados.`);
