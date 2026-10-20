// Índice de versiones de una Release: versión y ZIP vigente de cada plugin (`plugins`) y del tema (`themes`), en digitalisimo-plugins.json.
// Uso: node scripts/release-manifest.mjs <carpeta con los ZIPs> <etiqueta de la Release>
// Los actualizadores lo leen como archivo de la última Release (releases/latest/download/…), sin pasar por la
// API de GitHub y su límite de 60 consultas por hora para cada servidor.
import { execFileSync } from 'node:child_process';
import { readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const [directory, tag] = process.argv.slice(2);
if (!directory || !/^v\d{4}\.\d{2}\.\d{2}\.\d+$/.test(tag || '')) throw new Error('Uso: release-manifest.mjs <carpeta> <vAAAA.MM.DD.N>');
const repository = process.env.GITHUB_REPOSITORY || 'danbutanda/digitalisimo-plugin';
const PLUGINS = ['digitalisimo-seo', 'digitalisimo-ia-tools', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements', 'digitalisimo-migration'];
const THEMES = ['digitalisimo-theme'];
const plugins = {}, themes = {};
for (const file of readdirSync(directory).filter((name) => name.endsWith('.zip')).sort()) {
  const match = file.match(/^(digitalisimo-[a-z]+(?:-[a-z]+)*)-(\d+(?:\.\d+)+)\.zip$/);
  if (!match) throw new Error(`Nombre de paquete inesperado: ${file}`);
  const [, slug, version] = match;
  const group = PLUGINS.includes(slug) ? plugins : THEMES.includes(slug) ? themes : null;
  if (!group) throw new Error(`Paquete desconocido: ${slug}`);
  if (group[slug]) throw new Error(`Dos ZIPs del mismo paquete: ${slug}`);
  const entry = { version, file, url: `https://github.com/${repository}/releases/download/${tag}/${file}` };
  if (group === themes) {
    // Requisitos de la cabecera del tema: con alguno incompatible, el actualizador del tema no deja que se instale solo.
    const header = execFileSync('unzip', ['-p', join(directory, file), `${slug}/style.css`], { encoding: 'utf8' });
    const wp = header.match(/^\s*\*?\s*Requires at least:\s*([0-9.]+)\s*$/m)?.[1];
    const php = header.match(/^\s*\*?\s*Requires PHP:\s*([0-9.]+)\s*$/m)?.[1];
    if (wp) entry.requires = wp;
    if (php) entry.requires_php = php;
  }
  group[slug] = entry;
}
const missing = [...PLUGINS.filter((slug) => !plugins[slug]), ...THEMES.filter((slug) => !themes[slug])];
if (missing.length) throw new Error(`Faltan paquetes en la Release: ${missing.join(', ')}`);
const manifest = { tag, release_url: `https://github.com/${repository}/releases/tag/${tag}`, generated: new Date().toISOString(), plugins, themes };
writeFileSync(join(directory, 'digitalisimo-plugins.json'), JSON.stringify(manifest, null, 2) + '\n');
const list = (group) => Object.entries(group).map(([slug, { version }]) => `${slug} ${version}`).join(', ');
console.log(`Índice de ${tag}: plugins ${list(plugins)}; tema ${list(themes)}`);
