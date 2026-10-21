// Kits (etapa K, §13.1 «static»): revisión estática del importador de kits y de los derivados de PRO que lo alimentan
// (CR-6). Sin Playground ni PHP.
// 1. Nada en modules/digitalisimo-import/ ni en core/app/modules/import-export-customization/ instala, activa o cambia
//    plugins o temas ni experimentos (activate_plugin, Plugin_Upgrader, Theme_Upgrader, switch_theme, delete_theme,
//    update_option de elementor_experiment-…), y el delegado de ajustes de PRO no llama a import_theme() ni a
//    import_experiments() (§7.8 #9).
// 2. El módulo no crea kits nuevos (create_new_kit, D2).
// 3. Ni dominios del proveedor (tests/playground/brand-audit/vendors.json) fuera de comentarios, ni nombres vetados en
//    ninguna parte: el paquete de Elements aborta si menciona Element Pack o Prime Slider (scripts/build-packages.sh).
// 4. Orden de carga en pro-elements.php: el módulo se carga dentro de pro_elements_load_plugin_func(), después de
//    Display_Conditions::init() (§5.1).
// 5. La carga del marcador nunca se codifica para esquivar kses (sin base64_encode donde se arma o se lee; §10.3).
// 6. assets/digitalisimo/kits.js no pasa de 2 KB (§12.3).
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const E = join(ROOT, 'digitalisimo-elements');
const MODULE = join(E, 'modules/digitalisimo-import');
const IEC = join(E, 'core/app/modules/import-export-customization');
const vendors = JSON.parse(readFileSync(join(ROOT, 'tests/playground/brand-audit/vendors.json'), 'utf8'));

assert.ok(existsSync(join(MODULE, 'class-module.php')), 'Falta modules/digitalisimo-import/class-module.php (contrato de K).');
assert.ok(existsSync(IEC), 'Falta core/app/modules/import-export-customization/ (derivado de PRO).');

const walk = (dir, out = []) => {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) walk(p, out);
    else if (/\.(php|js|json|css|html)$/.test(name)) out.push(p);
  }
  return out;
};
const moduleFiles = walk(MODULE);
const iecFiles = walk(IEC);
const rel = (p) => relative(ROOT, p);

// Código sin comentarios de PHP (/* */, //, #) y sin el contenido de cadenas de documentación: las prohibiciones
// cuentan sólo lo que se ejecuta. Los nombres vetados, en cambio, se buscan en el texto completo (el empaquetado
// también lo hace).
const stripPhpComments = (text) => text
  .replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, ' '))
  .split('\n')
  .map((line) => line.replace(/(^|[^:'"\\])\/\/.*$/, '$1').replace(/^\s*#(?!\[).*$/, ''))
  .join('\n');

const problems = [];
const report = (file, line, message) => problems.push(`${rel(file)}:${line} ${message}`);
const scan = (file, text, re, message) => {
  const lines = text.split('\n');
  lines.forEach((line, i) => { if (re.test(line)) report(file, i + 1, message); });
};

// 1 y 2. Llamadas que cambian el sitio sin permiso (D2, D5, §4.1).
const forbidden = [
  [/\bactivate_plugins?\s*\(/, 'activa plugins (D5): el importador nunca instala, activa ni desactiva plugins'],
  [/\bdeactivate_plugins\s*\(/, 'desactiva plugins (D5)'],
  [/\b(?:Plugin|Theme)_Upgrader\b/, 'usa un instalador de plugins o temas (D5)'],
  [/\bswitch_theme\s*\(/, 'cambia el tema (§4.1)'],
  [/\bdelete_theme\s*\(/, 'borra un tema (§4.1)'],
  [/\bupdate_option\s*\(\s*['"]elementor_experiment-/, 'cambia un experimento (§4.1)'],
  [/\bupdate_option\s*\(\s*['"]template['"]|\bupdate_option\s*\(\s*['"]stylesheet['"]/, 'cambia el tema por opción (§4.1)'],
];
for (const file of [...moduleFiles, ...iecFiles].filter((f) => f.endsWith('.php'))) {
  const code = stripPhpComments(readFileSync(file, 'utf8'));
  for (const [re, message] of forbidden) scan(file, code, re, message);
}
for (const file of moduleFiles.filter((f) => f.endsWith('.php'))) {
  scan(file, stripPhpComments(readFileSync(file, 'utf8')), /\bcreate_new_kit\s*\(/, 'crea un kit nuevo (D2): K escribe siempre en el kit activo');
}
// §7.8 #9: el delegado de ajustes de PRO nunca llama a import_theme() ni a import_experiments() (V-31, CR-6).
const delegate = join(IEC, 'runners/import/site-settings.php');
assert.ok(existsSync(delegate), `Falta ${rel(delegate)}`);
scan(delegate, stripPhpComments(readFileSync(delegate, 'utf8')), /(?<!function\s)\bimport_(?:theme|experiments)\s*\(/, 'el delegado de ajustes llama a import_theme() o import_experiments() (§7.8 #9)');

// 3. Dominios del proveedor (fuera de comentarios) y nombres vetados (en todo el texto).
const hosts = [...vendors.hosts, ...(vendors.future || [])];
const hostRe = new RegExp(`(?<![a-z0-9-])(?:[a-z0-9-]+\\.)*(?:${hosts.map((h) => h.replace(/\./g, '\\.')).join('|')})(?![a-z0-9-])`, 'i');
const vetoRe = /bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i;
for (const file of [...moduleFiles, join(E, 'assets/digitalisimo/kits.js')].filter((f) => existsSync(f))) {
  const text = readFileSync(file, 'utf8');
  const code = file.endsWith('.php') ? stripPhpComments(text) : text;
  scan(file, code, hostRe, 'nombra un dominio del proveedor (vendors.json)');
  scan(file, text, vetoRe, 'nombra Element Pack o Prime Slider: el paquete de Elements abortaría (scripts/build-packages.sh)');
}

// 4. Orden de carga (§5.1): dentro de pro_elements_load_plugin_func(), después de Display_Conditions::init().
const loader = readFileSync(join(E, 'pro-elements.php'), 'utf8');
const fnStart = loader.indexOf('function pro_elements_load_plugin_func');
assert.ok(fnStart >= 0, 'pro-elements.php: falta pro_elements_load_plugin_func()');
const fnEnd = loader.indexOf('\n}\n', fnStart);
const body = loader.slice(fnStart, fnEnd);
const requireAt = body.indexOf("require_once __DIR__ . '/modules/digitalisimo-import/class-module.php';");
const initAt = body.indexOf('\\Digitalisimo\\Elements\\Import\\Module::init();');
const conditionsAt = body.indexOf('\\Digitalisimo\\Elements\\Display_Conditions::init();');
assert.ok(conditionsAt >= 0, 'pro-elements.php: falta Display_Conditions::init() en pro_elements_load_plugin_func()');
assert.ok(requireAt > conditionsAt, 'pro-elements.php: el módulo de kits debe cargarse dentro de pro_elements_load_plugin_func(), después de Display_Conditions::init() (§5.1)');
assert.ok(initAt > requireAt, 'pro-elements.php: \\Digitalisimo\\Elements\\Import\\Module::init() debe ir justo después de cargar class-module.php');
assert.equal((loader.match(/modules\/digitalisimo-import\/class-module\.php/g) || []).length, 1, 'pro-elements.php: el módulo de kits se carga una sola vez');
assert.equal((loader.match(/Import\\Module::init\(\)/g) || []).length, 1, 'pro-elements.php: Module::init() se llama una sola vez');

// 5. La carga del marcador no se codifica para esquivar kses (§10.3, §18).
for (const file of moduleFiles.filter((f) => f.endsWith('.php'))) {
  const code = stripPhpComments(readFileSync(file, 'utf8'));
  const marker = /digitalisimo_unconverted|Unconverted_Widget|class Preserve\b/.test(code);
  if (marker) scan(file, code, /\bbase64_encode\s*\(|\bstr_rot13\s*\(|\bgzdeflate\s*\(|\bgzcompress\s*\(/, 'codifica la carga del marcador (§10.3): kses debe verla tal cual');
}

// 6. kits.js (§12.3, ELEMENTS_FRONT_OBJECTIVE): como mucho 2 KB y sólo para la App de kits.
const kitsJs = join(E, 'assets/digitalisimo/kits.js');
assert.ok(existsSync(kitsJs), 'Falta assets/digitalisimo/kits.js (§12.3)');
const kitsBytes = statSync(kitsJs).size;
if (kitsBytes > 2048) problems.push(`${rel(kitsJs)}: ${kitsBytes} bytes; el presupuesto es 2 KB (§12.3)`);

assert.deepEqual(problems, [], `Kits: revisión estática con problemas:\n  ${problems.join('\n  ')}`);
console.log(`Kits: revisión estática de ${moduleFiles.length} archivos del módulo y ${iecFiles.length} del importador de PRO sin cambios de plugins, tema, experimentos ni kit, sin dominios del proveedor ni nombres vetados, con el orden de carga de §5.1, sin codificar la carga del marcador y con kits.js de ${kitsBytes} bytes (≤ 2 KB).`);
