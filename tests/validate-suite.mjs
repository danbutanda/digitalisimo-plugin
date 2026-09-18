import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const packageDir = process.env.DIGITALISIMO_PACKAGE_DIR || '.';

const modules = [
  { dir: 'digitalisimo-seo', file: 'digitalisimo-integrations.php', zip: 'digitalisimo-seo-1.0.29.zip', version: '1.0.29' },
  { dir: 'digitalisimo-ecommerce', file: 'digitalisimo-ecommerce.php', zip: 'digitalisimo-ecommerce-1.0.10.zip', version: '1.0.10' },
  { dir: 'digitalisimo-geolocalizacion', file: 'digitalisimo-geolocalizacion.php', zip: 'digitalisimo-geolocalizacion-1.0.8.zip', version: '1.0.8' },
  { dir: 'digitalisimo.chatbot', file: 'digitalisimo-chatbot.php', zip: 'digitalisimo-chatbot-1.0.13.zip', version: '1.0.13' },
  { dir: 'digitalisimo-hosting', file: 'digitalisimo-hosting.php', zip: 'digitalisimo-hosting-1.0.4.zip', version: '1.0.4' },
];
const phpFiles = [];
function walk(dir) {
  for (const entry of readdirSync(dir)) {
    const filename = join(dir, entry);
    if (statSync(filename).isDirectory()) walk(filename);
    else if (filename.endsWith('.php')) phpFiles.push(filename);
  }
}

for (const module of modules) {
  const main = join(module.dir, module.file);
	const zip = join(packageDir, module.zip);
  if (!existsSync(main) || !existsSync(zip)) throw new Error(`Falta ${main} o ${zip}`);
  const source = readFileSync(main, 'utf8');
  if (!source.includes(`Version: ${module.version}`)) throw new Error(`Versión incorrecta en ${main}`);
	const zipListing = execFileSync('unzip', ['-Z1', zip], { encoding: 'utf8' });
	if (!zipListing.includes(`${module.dir}/${module.file}`)) throw new Error(`El ZIP ${zip} no conserva su archivo principal`);
	if (zipListing.includes('.DS_Store')) throw new Error(`El ZIP ${zip} contiene archivos de sistema`);
  walk(module.dir);
}

const declarations = new Map();
for (const file of phpFiles) {
  const source = readFileSync(file, 'utf8');
  for (const match of source.matchAll(/(?:final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/g)) {
    const name = match[1];
    if (!declarations.has(name)) declarations.set(name, []);
    declarations.get(name).push({ file, guarded: source.includes(`class_exists( '${name}' )`) || source.includes(`class_exists('${name}')`) });
  }
}
for (const [name, records] of declarations) {
  if (records.length > 1 && !records.every((record) => record.guarded)) throw new Error(`Clase duplicada no protegida: ${name} en ${records.map((record) => record.file).join(', ')}`);
}

console.log(`Suite validada: ${modules.length} módulos, ${phpFiles.length} archivos PHP, sin clases duplicadas no protegidas.`);
