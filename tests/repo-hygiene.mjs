// Higiene del repositorio: .gitignore no puede dejar fuera archivos de un tema o de un plugin en una subcarpeta (un
// patrón sin ancla como `functions.php` o `dist/` valdría en cualquier nivel y la Release, que se compila desde el
// repositorio, saldría sin ellos), y debe seguir ignorando las copias de terceros (`reference/`), los paquetes
// generados (`dist/`) y cualquier ZIP, porque el repositorio es público.
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

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

console.log(`Higiene del repositorio: ${mustTrack.length} rutas de tema y plugin se versionan, ${mustIgnore.length} de terceros y paquetes se ignoran; ${themes.length} tema(s) en la raíz sin archivos ignorados.`);
