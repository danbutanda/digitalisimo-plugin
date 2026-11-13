import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

// Carpeta del repositorio, archivo principal, slug del ZIP y carpeta dentro del ZIP: la del ZIP es la que tienen los
// sitios instalados y nunca cambia; la del repositorio puede llamarse distinto (IA Tools).
const modules = [
  ['digitalisimo-seo', 'digitalisimo-integrations.php', 'digitalisimo-seo', 'digitalisimo-seo'],
  ['digitalisimo-ia-tools', 'digitalisimo-chatbot.php', 'digitalisimo-ia-tools', 'digitalisimo.chatbot'],
  ['digitalisimo-hosting', 'digitalisimo-hosting.php', 'digitalisimo-hosting', 'digitalisimo-hosting'],
  ['digitalisimo-backups', 'digitalisimo-backups.php', 'digitalisimo-backups', 'digitalisimo-backups'],
  ['digitalisimo-tools', 'digitalisimo-tools.php', 'digitalisimo-tools', 'digitalisimo-tools'],
  ['digitalisimo-elements', 'pro-elements.php', 'digitalisimo-elements', 'digitalisimo-elements'],
  ['digitalisimo-migration', 'digitalisimo-migration.php', 'digitalisimo-migration', 'digitalisimo-migration'],
  ['digitalisimo-theme', 'style.css', 'digitalisimo-theme', 'digitalisimo-theme'],
];
const packageDir = process.env.DIGITALISIMO_PACKAGE_DIR || 'dist';
// Instalador de DIGITALÍSIMO Elements: un asset más de la Release, sin versión en el nombre (enlace permanente
// releases/latest/download/digitalisimo-elements-installer.zip). No es uno de los siete plugins: no cuenta entre los ZIP
// versionados. Con --installer es obligatorio; si está en la carpeta, se comprueba aunque no se pida.
const INSTALLER = 'digitalisimo-elements-installer.zip';
const INSTALLER_MAIN = 'digitalisimo-elements-installer/digitalisimo-elements-installer.php';
const INSTALLER_MAX = 1024 * 1024;
const expected = new Map();
const installed = new Map();
for (const [directory, mainFile, slug, folder] of modules) {
  const source = readFileSync(join(directory, mainFile), 'utf8');
  const version = source.match(/^ \* Version: (\d+(?:\.\d+)+)\s*$/m)?.[1];
  if (!version) throw new Error(`Falta versión numérica en ${directory}/${mainFile}`);
  expected.set(slug, version);
  installed.set(slug, `${folder}/${mainFile}`);
}
const selected = process.argv.filter((argument) => argument.startsWith('digitalisimo-'));
for (const slug of selected) if (!expected.has(slug)) throw new Error(`Módulo desconocido: ${slug}`);
const selectedVersions = selected.length ? [...new Set(selected)].map((slug) => [slug, expected.get(slug)]) : [...expected];
const actual = readdirSync(packageDir).filter((file) => file.endsWith('.zip') && file !== INSTALLER).sort();
const wanted = selectedVersions.map(([slug, version]) => `${slug}-${version}.zip`).sort();
if (actual.length !== wanted.length || actual.some((file, index) => file !== wanted[index])) {
  throw new Error(`La entrega exige exactamente estos ZIPs: ${wanted.join(', ')}. Encontrados: ${actual.join(', ')}`);
}
for (const [slug, version] of selectedVersions) {
  const entries = execFileSync('unzip', ['-Z1', join(packageDir, `${slug}-${version}.zip`)], { encoding: 'utf8' }).split('\n').filter(Boolean);
  const folder = installed.get(slug).split('/')[0];
  if (!entries.includes(installed.get(slug))) throw new Error(`${slug}-${version}.zip debe contener ${installed.get(slug)}: es la ruta del plugin en los sitios instalados`);
  const strays = entries.filter((entry) => !entry.startsWith(`${folder}/`));
  if (strays.length) throw new Error(`${slug}-${version}.zip tiene archivos fuera de ${folder}/: ${strays.slice(0, 5).join(', ')}`);
}
console.log(`Paquetes completos: ${wanted.join(', ')}`);

const installerPath = join(packageDir, INSTALLER);
if (process.argv.includes('--installer') && !existsSync(installerPath)) throw new Error(`Falta ${INSTALLER}: la Release lleva el instalador de Elements (enlace permanente)`);
if (existsSync(installerPath)) {
  const size = statSync(installerPath).size;
  if (size >= INSTALLER_MAX) throw new Error(`${INSTALLER} pesa ${size} bytes: debe quedar por debajo de 1 MB`);
  const entries = execFileSync('unzip', ['-Z1', installerPath], { encoding: 'utf8' }).split('\n').filter(Boolean);
  if (!entries.includes(INSTALLER_MAIN)) throw new Error(`${INSTALLER} debe contener ${INSTALLER_MAIN}`);
  const strays = entries.filter((entry) => !entry.startsWith('digitalisimo-elements-installer/'));
  if (strays.length) throw new Error(`${INSTALLER} tiene archivos fuera de digitalisimo-elements-installer/: ${strays.slice(0, 5).join(', ')}`);
  const header = execFileSync('unzip', ['-p', installerPath, INSTALLER_MAIN], { encoding: 'utf8' });
  if (!/^ \* Plugin Name: DIGITALÍSIMO Elements · Instalador\s*$/m.test(header)) throw new Error(`${INSTALLER_MAIN} no es el instalador de DIGITALÍSIMO Elements`);
  console.log(`Instalador de Elements: ${INSTALLER} (${size} bytes)`);
}

if (process.argv.includes('--remote')) {
  const repository = process.env.GITHUB_REPOSITORY || 'danbutanda/digitalisimo-plugin';
  const token = process.env.GITHUB_TOKEN;
  const latest = new Map();
  for (let page = 1; page <= 20; page++) {
    const response = await fetch(`https://api.github.com/repos/${repository}/releases?per_page=100&page=${page}`, {
      headers: { Accept: 'application/vnd.github+json', ...(token ? { Authorization: `Bearer ${token}` } : {}), 'User-Agent': 'digitalisimo-release-check' },
    });
    if (!response.ok) throw new Error(`No se pudieron consultar las Releases: HTTP ${response.status}`);
    const releases = await response.json();
    if (!Array.isArray(releases)) throw new Error('Respuesta inválida de GitHub Releases');
    for (const release of releases) {
      if (release.draft || release.prerelease) continue;
      for (const asset of release.assets || []) {
        const match = asset.name?.match(/^(digitalisimo-[a-z]+(?:-[a-z]+)*)-(\d+(?:\.\d+)+)\.zip$/);
        if (!match || !expected.has(match[1])) continue;
        const previous = latest.get(match[1]);
        if (!previous || compareVersions(match[2], previous) > 0) latest.set(match[1], match[2]);
      }
    }
    if (releases.length < 100) break;
    if (page === 20) throw new Error('Hay más de 2000 Releases; amplía la paginación antes de publicar');
  }
  for (const [slug, version] of selectedVersions) {
    const previous = latest.get(slug);
    if (previous && compareVersions(version, previous) <= 0) {
      throw new Error(`${slug} ${version} debe superar la última versión publicada ${previous}`);
    }
  }
  console.log('Las versiones seleccionadas superan las publicadas en GitHub.');
}

function compareVersions(left, right) {
  const a = left.split('.').map(Number);
  const b = right.split('.').map(Number);
  for (let index = 0; index < Math.max(a.length, b.length); index++) {
    if ((a[index] || 0) !== (b[index] || 0)) return (a[index] || 0) - (b[index] || 0);
  }
  return 0;
}
