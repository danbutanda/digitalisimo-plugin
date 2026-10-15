// Índice de versiones de una Release: versión y ZIP vigente de cada plugin, en digitalisimo-plugins.json.
// Uso: node scripts/release-manifest.mjs <carpeta con los ZIPs> <etiqueta de la Release>
// Los actualizadores lo leen como archivo de la última Release (releases/latest/download/…), sin pasar por la
// API de GitHub y su límite de 60 consultas por hora para cada servidor.
import { readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const [directory, tag] = process.argv.slice(2);
if (!directory || !/^v\d{4}\.\d{2}\.\d{2}\.\d+$/.test(tag || '')) throw new Error('Uso: release-manifest.mjs <carpeta> <vAAAA.MM.DD.N>');
const repository = process.env.GITHUB_REPOSITORY || 'danbutanda/digitalisimo-plugin';
const plugins = {};
for (const file of readdirSync(directory).filter((name) => name.endsWith('.zip')).sort()) {
  const match = file.match(/^(digitalisimo-[a-z]+(?:-[a-z]+)*)-(\d+(?:\.\d+)+)\.zip$/);
  if (!match) throw new Error(`Nombre de paquete inesperado: ${file}`);
  if (plugins[match[1]]) throw new Error(`Dos ZIPs del mismo plugin: ${match[1]}`);
  plugins[match[1]] = { version: match[2], file, url: `https://github.com/${repository}/releases/download/${tag}/${file}` };
}
if (Object.keys(plugins).length !== 7) throw new Error(`El índice debe cubrir los siete plugins; hay ${Object.keys(plugins).length}`);
const manifest = { tag, release_url: `https://github.com/${repository}/releases/tag/${tag}`, generated: new Date().toISOString(), plugins };
writeFileSync(join(directory, 'digitalisimo-plugins.json'), JSON.stringify(manifest, null, 2) + '\n');
console.log(`Índice de ${tag}: ${Object.entries(plugins).map(([slug, { version }]) => `${slug} ${version}`).join(', ')}`);
