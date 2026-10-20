// Índice de la Release (scripts/release-manifest.mjs) y comprobación de paquetes (scripts/verify-release.mjs) con el
// tema: ocho ZIP mínimos cuyos nombres salen de las cabeceras del repositorio (la misma expresión que verify-release),
// el índice escrito se lee de vuelta y cada caso de error deja el índice sin escribir.
import { execFileSync, spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const MODULES = [
  ['digitalisimo-seo', 'digitalisimo-integrations.php', 'digitalisimo-seo'],
  ['digitalisimo-ia-tools', 'digitalisimo-chatbot.php', 'digitalisimo-ia-tools', 'digitalisimo.chatbot'],
  ['digitalisimo-hosting', 'digitalisimo-hosting.php', 'digitalisimo-hosting'],
  ['digitalisimo-backups', 'digitalisimo-backups.php', 'digitalisimo-backups'],
  ['digitalisimo-tools', 'digitalisimo-tools.php', 'digitalisimo-tools'],
  ['digitalisimo-elements', 'pro-elements.php', 'digitalisimo-elements'],
  ['digitalisimo-migration', 'digitalisimo-migration.php', 'digitalisimo-migration'],
];
const THEME = ['digitalisimo-theme', 'style.css', 'digitalisimo-theme'];
const TAG = 'v2026.10.17.999';
const REPOSITORY = 'danbutanda/digitalisimo-plugin';

function check(ok, message) { if (!ok) throw new Error(message); }
const header = (directory, file) => readFileSync(join(ROOT, directory, file), 'utf8');
const version = (directory, file) => {
  const found = header(directory, file).match(/^ \* Version: (\d+(?:\.\d+)+)\s*$/m)?.[1];
  check(found, `Falta versión numérica en ${directory}/${file}`);
  return found;
};
const themeHeader = header(THEME[0], THEME[1]);
const requires = themeHeader.match(/^\s*\*?\s*Requires at least:\s*([0-9.]+)\s*$/m)?.[1];
const requiresPhp = themeHeader.match(/^\s*\*?\s*Requires PHP:\s*([0-9.]+)\s*$/m)?.[1];
check(requires && requiresPhp, 'La cabecera del tema debe declarar «Requires at least» y «Requires PHP».');

const work = mkdtempSync(join(tmpdir(), 'digitalisimo-release-manifest-'));
try {
  // ZIP mínimo de cada paquete: su carpeta con el archivo principal (el style.css real, en el caso del tema).
  const source = join(work, 'source');
  const zips = new Map();
  // La carpeta dentro del ZIP es la de los sitios instalados (IA Tools: digitalisimo.chatbot), no la del repositorio.
  for (const [directory, file, slug, folder = directory] of [...MODULES, THEME]) {
    mkdirSync(join(source, folder), { recursive: true });
    if (slug === THEME[2]) copyFileSync(join(ROOT, directory, file), join(source, folder, file));
    else writeFileSync(join(source, folder, file), `<?php\n/**\n * Plugin Name: ${slug}\n * Version: ${version(directory, file)}\n */\n`);
    const zip = join(work, `${slug}-${version(directory, file)}.zip`);
    execFileSync('zip', ['-qr', zip, folder], { cwd: source });
    zips.set(slug, zip);
  }
  /** Carpeta de paquetes nueva con los ZIP indicados (por slug) y, opcionalmente, otros ya hechos. */
  const packages = (label, slugs, extra = []) => {
    const directory = join(work, label);
    mkdirSync(directory);
    for (const slug of slugs) copyFileSync(zips.get(slug), join(directory, zips.get(slug).split('/').pop()));
    for (const [name, from] of extra) copyFileSync(from, join(directory, name));
    return directory;
  };
  const node = (args, env = {}) => spawnSync(process.execPath, args, { cwd: ROOT, encoding: 'utf8', env: { ...process.env, GITHUB_REPOSITORY: REPOSITORY, ...env } });
  const manifest = (directory) => node([join(ROOT, 'scripts/release-manifest.mjs'), directory, TAG]);
  const index = (directory) => join(directory, 'digitalisimo-plugins.json');
  const all = [...MODULES.map((module) => module[2]), THEME[2]];

  // (a) Los ocho ZIP: «plugins» con los siete, «themes» con el tema y sus requisitos, leídos del JSON escrito.
  {
    const directory = packages('completa', all);
    const run = manifest(directory);
    check(0 === run.status, `El índice debe generarse con los ocho paquetes: ${run.stderr}`);
    const written = JSON.parse(readFileSync(index(directory), 'utf8'));
    check(TAG === written.tag && `https://github.com/${REPOSITORY}/releases/tag/${TAG}` === written.release_url && !Number.isNaN(Date.parse(written.generated)), 'Etiqueta, enlace y fecha de la Release en el índice.');
    check(7 === Object.keys(written.plugins || {}).length && MODULES.every(([directory, file, slug]) => written.plugins[slug]?.version === version(directory, file)), '«plugins» lleva exactamente los siete plugins con su versión.');
    check(!written.plugins[THEME[2]] && 1 === Object.keys(written.themes || {}).length, '«themes» lleva sólo el tema y «plugins» no lo incluye.');
    const theme = written.themes[THEME[2]];
    const zip = `${THEME[2]}-${version(THEME[0], THEME[1])}.zip`;
    check(theme && theme.version === version(THEME[0], THEME[1]) && theme.file === zip && theme.url === `https://github.com/${REPOSITORY}/releases/download/${TAG}/${zip}`, 'Entrada del tema con su versión, su ZIP y su enlace de la Release.');
    check(theme.requires === requires && theme.requires_php === requiresPhp, `Requisitos del tema según su cabecera (${requires}, ${requiresPhp}): ${theme.requires}, ${theme.requires_php}.`);
    check(Object.values(written.plugins).every((entry) => !('requires' in entry) && !('requires_php' in entry)), 'Las entradas de los plugins conservan su forma (sin requisitos).');
    check(/plugins .*digitalisimo-seo/.test(run.stdout) && /tema digitalisimo-theme/.test(run.stdout), `El mensaje lista los dos grupos: ${run.stdout}`);
  }
  // (b) Un noveno paquete que no es ni plugin ni tema hace fallar el script.
  {
    const directory = packages('desconocido', all, [['digitalisimo-foo-1.0.zip', zips.get('digitalisimo-seo')]]);
    const run = manifest(directory);
    check(0 !== run.status && /Paquete desconocido: digitalisimo-foo/.test(run.stderr) && !existsSync(index(directory)), 'Un paquete desconocido detiene el índice sin escribirlo.');
  }
  // (c) Dos ZIP del mismo plugin, o del tema, lo hacen fallar.
  for (const slug of ['digitalisimo-seo', THEME[2]]) {
    const directory = packages(`duplicado-${slug}`, all, [[`${slug}-0.0.1.zip`, zips.get(slug)]]);
    const run = manifest(directory);
    check(0 !== run.status && new RegExp(`Dos ZIPs del mismo paquete: ${slug}`).test(run.stderr) && !existsSync(index(directory)), `Un ZIP duplicado de ${slug} detiene el índice sin escribirlo.`);
  }
  // (d) Sin el ZIP del tema: «Faltan paquetes» y ningún índice.
  {
    const directory = packages('sin-tema', all.filter((slug) => slug !== THEME[2]));
    const run = manifest(directory);
    check(0 !== run.status && /Faltan paquetes en la Release: digitalisimo-theme/.test(run.stderr) && !existsSync(index(directory)), 'Sin el tema el índice no se escribe.');
  }
  // (e) verify-release exige los ocho: pasa con todos y falla si falta el tema.
  {
    const complete = packages('verificar-completa', all);
    const ok = node([join(ROOT, 'scripts/verify-release.mjs')], { DIGITALISIMO_PACKAGE_DIR: complete });
    check(0 === ok.status && /Paquetes completos: .*digitalisimo-theme-/.test(ok.stdout), `verify-release debe aceptar los ocho paquetes: ${ok.stderr}`);
    const partial = packages('verificar-sin-tema', all.filter((slug) => slug !== THEME[2]));
    const failed = node([join(ROOT, 'scripts/verify-release.mjs')], { DIGITALISIMO_PACKAGE_DIR: partial });
    check(0 !== failed.status && /digitalisimo-theme-/.test(failed.stderr), 'verify-release debe rechazar una entrega sin el tema.');
    const selected = node([join(ROOT, 'scripts/verify-release.mjs'), THEME[2]], { DIGITALISIMO_PACKAGE_DIR: packages('verificar-solo-tema', [THEME[2]]) });
    check(0 === selected.status, `verify-release debe aceptar el tema como paquete seleccionado: ${selected.stderr}`);
    check(readdirSync(complete).every((name) => name.endsWith('.zip')), 'verify-release no escribe nada en la carpeta de paquetes.');
  }
} finally {
  rmSync(work, { recursive: true, force: true });
}
console.log('Índice de la Release: siete plugins en «plugins», el tema en «themes» con sus requisitos, y verify-release exige los ocho ZIP.');
