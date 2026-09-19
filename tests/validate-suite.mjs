import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const packageDir = process.env.DIGITALISIMO_PACKAGE_DIR || '.';

const modules = [
  { dir: 'digitalisimo-seo', file: 'digitalisimo-integrations.php', zip: 'digitalisimo-seo-1.0.35.zip', version: '1.0.35' },
  { dir: 'digitalisimo-ecommerce', file: 'digitalisimo-ecommerce.php', zip: 'digitalisimo-ecommerce-1.0.12.zip', version: '1.0.12' },
  { dir: 'digitalisimo-geolocalizacion', file: 'digitalisimo-geolocalizacion.php', zip: 'digitalisimo-geolocalizacion-1.0.10.zip', version: '1.0.10' },
  { dir: 'digitalisimo.chatbot', file: 'digitalisimo-chatbot.php', zip: 'digitalisimo-chatbot-1.0.15.zip', version: '1.0.15' },
  { dir: 'digitalisimo-hosting', file: 'digitalisimo-hosting.php', zip: 'digitalisimo-hosting-1.0.6.zip', version: '1.0.6' },
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

const seoSuite = readFileSync('digitalisimo-seo/includes/class-seo-suite.php', 'utf8');
const seoEditorial = readFileSync('digitalisimo-seo/includes/class-seo.php', 'utf8');
const seoAiTools = [
  'class-seo-ai-crawler-tools.php',
  'class-seo-ai-crawler-verifier.php',
  'class-seo-ai-audit-tools.php',
  'class-seo-ai-llms-tools.php',
  'class-seo-ai-indexnow-tools.php',
  'class-seo-ai-referral-tools.php',
  'class-seo-ai-entities-tools.php',
].map((file) => readFileSync(join('digitalisimo-seo/includes', file), 'utf8'));
if (!seoSuite.includes("add_submenu_page( 'digitalisimo', 'SEO', 'SEO'")) throw new Error('Falta el acceso unificado SEO');
if (!seoEditorial.includes("add_submenu_page( 'digitalisimo', 'Contenido', 'Contenido'")) throw new Error('Falta el acceso unificado Contenido');
if (seoAiTools.some((source) => source.includes("add_submenu_page( 'digitalisimo'"))) throw new Error('Una herramienta SEO AI sigue expuesta como submenú lateral');

console.log(`Suite validada: ${modules.length} módulos, ${phpFiles.length} archivos PHP, sin clases duplicadas no protegidas.`);
