import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const modules = [
  ['digitalisimo-seo', 'digitalisimo-integrations.php', 'digitalisimo-seo'],
  ['digitalisimo-ecommerce', 'digitalisimo-ecommerce.php', 'digitalisimo-ecommerce'],
  ['digitalisimo.chatbot', 'digitalisimo-chatbot.php', 'digitalisimo-ia-tools'],
  ['digitalisimo-hosting', 'digitalisimo-hosting.php', 'digitalisimo-hosting'],
  ['digitalisimo-backups', 'digitalisimo-backups.php', 'digitalisimo-backups'],
  ['digitalisimo-tools', 'digitalisimo-tools.php', 'digitalisimo-tools'],
  ['digitalisimo-elements', 'pro-elements.php', 'digitalisimo-elements'],
];
const packageDir = process.env.DIGITALISIMO_PACKAGE_DIR || 'dist';
const expected = new Map();
for (const [directory, mainFile, slug] of modules) {
  const source = readFileSync(join(directory, mainFile), 'utf8');
  const version = source.match(/^ \* Version: (\d+(?:\.\d+)+)\s*$/m)?.[1];
  if (!version) throw new Error(`Falta versión numérica en ${directory}/${mainFile}`);
  expected.set(slug, version);
}
const selected = process.argv.filter((argument) => argument.startsWith('digitalisimo-'));
for (const slug of selected) if (!expected.has(slug)) throw new Error(`Módulo desconocido: ${slug}`);
const selectedVersions = selected.length ? [...new Set(selected)].map((slug) => [slug, expected.get(slug)]) : [...expected];
const actual = readdirSync(packageDir).filter((file) => file.endsWith('.zip')).sort();
const wanted = selectedVersions.map(([slug, version]) => `${slug}-${version}.zip`).sort();
if (actual.length !== wanted.length || actual.some((file, index) => file !== wanted[index])) {
  throw new Error(`La entrega exige exactamente estos ZIPs: ${wanted.join(', ')}. Encontrados: ${actual.join(', ')}`);
}
console.log(`Paquetes completos: ${wanted.join(', ')}`);

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
