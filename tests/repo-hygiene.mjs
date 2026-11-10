// Higiene del repositorio: .gitignore no puede dejar fuera archivos de un tema o de un plugin en una subcarpeta (un
// patrón sin ancla como `functions.php` o `dist/` valdría en cualquier nivel y la Release, que se compila desde el
// repositorio, saldría sin ellos), y debe seguir ignorando las copias de terceros (`reference/`), los paquetes
// generados (`dist/`) y cualquier ZIP, porque el repositorio es público.
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { pendingTests, phpSuiteCommands } from '../scripts/ci-php-suite.mjs';

const root = fileURLToPath(new URL('..', import.meta.url));

function git(...args) {
  const result = spawnSync('git', ['-C', root, ...args], { encoding: 'utf8' });
  assert.ok(!result.error, `No se pudo ejecutar git: ${result.error?.message}`);
  return result;
}

// `--no-index` evalúa sólo las reglas de .gitignore, aunque la ruta no exista o ya esté versionada.
function ignored(path) {
  const result = git('check-ignore', '--no-index', '-v', '--', path);
  assert.ok(result.status === 0 || result.status === 1, `git check-ignore falló con ${path}: ${result.stderr.trim()}`);
  return result.status === 0 ? result.stdout.trim() : '';
}

const mustTrack = [
  'digitalisimo-theme/style.css',
  'digitalisimo-theme/functions.php',
  'digitalisimo-theme/index.php',
  'digitalisimo-theme/theme.json',
  'digitalisimo-theme/screenshot.png',
  'digitalisimo-theme/inc/x.php',
  'digitalisimo-theme/inc/updater/functions.php',
  'digitalisimo-theme/assets/css/style.css',
  'digitalisimo-theme/assets/dist/theme.css',
  'digitalisimo-theme/languages/digitalisimo-theme.pot',
  'otro-tema/functions.php',
  'otro-tema/style.css',
  'digitalisimo-tools/includes/functions.php',
  'digitalisimo-elements/assets/css/style.css',
  'digitalisimo-elements/assets/dist/x.js',
];
for (const path of mustTrack) {
  const rule = ignored(path);
  assert.equal(rule, '', `.gitignore ignora ${path} (${rule}): ancla el patrón a la raíz con «/» para no perder archivos de un tema o plugin`);
}

const mustIgnore = [
  'reference/',
  'reference/Divi.zip',
  'reference/samples/README.txt',
  'reference/otro/functions.php',
  'digitalisimo-theme/reference/x.php',
  'dist/',
  'dist/digitalisimo-seo-1.0.0.zip',
  'dist/nuevo.txt',
  'x.zip',
  'digitalisimo-theme/x.zip',
  'rollback/digitalisimo-seo-1.0.0.zip',
];
for (const path of mustIgnore) {
  assert.notEqual(ignored(path), '', `.gitignore dejó de ignorar ${path}: reference/, dist/ y los ZIP nunca se versionan`);
}

// Un tema real en la raíz (style.css con `Theme Name:`) no puede tener archivos ignorados en disco: o se versionan
// o no deberían estar ahí. Se toleran sólo restos locales que nunca forman parte del paquete.
const localLeftovers = /(^|\/)(node_modules|__pycache__)\/|(^|\/)\.DS_Store$|\.zip$/;
const themes = readdirSync(root, { withFileTypes: true })
  .filter((entry) => entry.isDirectory() && !entry.name.startsWith('.') && entry.name !== 'reference')
  .map((entry) => entry.name)
  .filter((dir) => {
    const style = join(root, dir, 'style.css');
    return existsSync(style) && /^[ \t/*#@]*Theme Name\s*:/im.test(readFileSync(style, 'utf8').slice(0, 8192));
  });
for (const theme of themes) {
  const leftovers = git('ls-files', '--others', '--ignored', '--exclude-standard', '--', `${theme}/`).stdout
    .split('\n').filter(Boolean).filter((path) => !localLeftovers.test(path));
  assert.deepEqual(leftovers, [], `Archivos del tema ${theme} ignorados por .gitignore: ${leftovers.join(', ')}`);
  const forced = git('ls-files', '--cached', '--ignored', '--exclude-standard', '--', `${theme}/`).stdout
    .split('\n').filter(Boolean).filter((path) => !localLeftovers.test(path));
  assert.deepEqual(forced, [], `Archivos del tema ${theme} versionados a pesar de .gitignore (se añadieron con -f): ${forced.join(', ')}`);
}

// validate.yml (cada push) y release.yml (publicación) corren la misma lista de pruebas, y toda prueba suelta de
// tests/ está en ella: una prueba que ningún workflow ejecuta no protege nada.
const workflowTests = (name) => readFileSync(join(root, '.github/workflows', name), 'utf8').split('\n')
  .map((line) => line.trim()).filter((line) => /^(?:(?:php|node) tests\/|for module in )/.test(line));
const releaseTests = workflowTests('release.yml');
assert.ok(releaseTests.length > 100, 'release.yml debe correr la suite de pruebas');
assert.deepEqual(workflowTests('validate.yml'), releaseTests, 'validate.yml y release.yml deben correr la misma lista de pruebas');
const listed = new Set(releaseTests.flatMap((line) => line.match(/tests\/[^ ;"]+/g) || []));
const standalone = readdirSync(join(root, 'tests')).filter((file) => /\.(php|mjs)$/.test(file) && file !== 'validate-suite.mjs').map((file) => `tests/${file}`);
assert.deepEqual(standalone.filter((file) => !listed.has(file)), [], 'Pruebas de tests/ que ningún workflow ejecuta');

// Trabajo «PHP 7.4» (el mínimo que declaran los plugins) en los dos workflows: la misma sintaxis que el trabajo
// principal, pero con PHP 7.4 (sin los bootstrap80.php de los polyfills de Symfony, que sólo se cargan con PHP 8), y
// las pruebas PHP de «Probar la suite» de su propio archivo, que scripts/ci-php-suite.mjs extrae de la lista (todas,
// sin copiarla), con la lista de pendientes, que sólo puede acortarse. La publicación no sale sin él.
const PHP74_SKIP = " -not -path '*/symfony/polyfill-*/bootstrap80.php'";
const PHP74_PENDING = 'scripts/ci-php74-pendientes.txt';
const jobBlock = (text, id) => {
  const lines = text.split('\n');
  const start = lines.indexOf(`  ${id}:`);
  if (start < 0) return '';
  const end = lines.findIndex((line, i) => i > start && /^ {0,2}\S/.test(line));
  return lines.slice(start, end < 0 ? lines.length : end).join('\n');
};
const phpListed = releaseTests.filter((line) => /^php tests\//.test(line) || / php tests\//.test(line));
for (const name of ['validate.yml', 'release.yml']) {
  const text = readFileSync(join(root, '.github/workflows', name), 'utf8');
  const lint = text.match(/- name: Validar sintaxis PHP\n\s+run: (.+)/);
  assert.ok(lint, `${name}: falta el paso «Validar sintaxis PHP» del trabajo principal`);
  const php74 = jobBlock(text, 'php74');
  assert.ok(php74, `${name} debe tener el trabajo php74 con PHP 7.4, el mínimo que declaran los plugins`);
  assert.match(php74, /php-version: '7\.4'\n/, `${name}: el trabajo php74 debe instalar PHP 7.4`);
  assert.ok(php74.includes(`run: ${lint[1].replace(" -name '*.php' ", ` -name '*.php'${PHP74_SKIP} `)}\n`), `${name}: el trabajo php74 debe validar la sintaxis de las mismas carpetas que el principal, sin más excepción que los bootstrap80.php de Symfony`);
  assert.ok(php74.includes(`run: node scripts/ci-php-suite.mjs .github/workflows/${name} --pendientes ${PHP74_PENDING}\n`), `${name}: el trabajo php74 debe correr las pruebas PHP de su propio «Probar la suite», con ${PHP74_PENDING}`);
  assert.deepEqual(phpSuiteCommands(text), phpListed, `${name}: scripts/ci-php-suite.mjs debe correr todas las pruebas PHP de la lista`);
}
assert.match(jobBlock(readFileSync(join(root, '.github/workflows/release.yml'), 'utf8'), 'release'), /\n {4}needs: (php74|\[[^\]]*\bphp74\b[^\]]*\])\n/, 'release.yml no debe publicar sin pasar el trabajo php74');
// Lo que la sintaxis 7.4 omite sólo puede ser un bootstrap80.php al que su bootstrap.php llega con PHP >= 8.
const skipped = git('ls-files', '--', '*/symfony/polyfill-*/bootstrap80.php').stdout.split('\n').filter(Boolean);
for (const file of skipped) {
  const bootstrap = readFileSync(join(root, file.replace(/bootstrap80\.php$/, 'bootstrap.php')), 'utf8');
  assert.match(bootstrap, /if \(\\?PHP_VERSION_ID >= 80000\) \{\s*return require __DIR__ ?\. ?'\/bootstrap80\.php';/, `${file} usa sintaxis de PHP 8 y su bootstrap.php debe cargarlo sólo con PHP >= 8`);
}
// Las pendientes en 7.4 son pruebas de la lista, sin repetir, y nunca de Backups ni de Migración, que corren enteras.
const pending = pendingTests(readFileSync(join(root, PHP74_PENDING), 'utf8'));
assert.deepEqual(pending.filter((test) => !phpListed.includes(`php ${test}`)), [], `${PHP74_PENDING} nombra pruebas que no están en la lista`);
assert.equal(new Set(pending).size, pending.length, `${PHP74_PENDING} repite pruebas`);
assert.deepEqual(pending.filter((test) => /^tests\/(backups|migration)-/.test(test)), [], `Las pruebas de Backups y de Migración corren enteras con PHP 7.4: no pueden estar en ${PHP74_PENDING}`);

console.log(`Higiene del repositorio: ${mustTrack.length} rutas de tema y plugin se versionan, ${mustIgnore.length} de terceros y paquetes se ignoran; ${themes.length} tema(s) en la raíz sin archivos ignorados; ${releaseTests.length} pruebas iguales en validate.yml y release.yml, de las que ${phpListed.length} PHP corren también con PHP 7.4 (${pending.length} pendientes en ${PHP74_PENDING}; ${skipped.length} bootstrap80.php de Symfony fuera de la sintaxis 7.4).`);
