import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const packageDir = process.env.DIGITALISIMO_PACKAGE_DIR || '.';

const modules = [
  { dir: 'digitalisimo-seo', file: 'digitalisimo-integrations.php', zip: 'digitalisimo-seo-1.0.75.zip', version: '1.0.75' },
  { dir: 'digitalisimo-ecommerce', file: 'digitalisimo-ecommerce.php', zip: 'digitalisimo-ecommerce-1.0.14.zip', version: '1.0.14' },
  { dir: 'digitalisimo-geolocalizacion', file: 'digitalisimo-geolocalizacion.php', zip: 'digitalisimo-geolocalizacion-1.0.12.zip', version: '1.0.12' },
  { dir: 'digitalisimo.chatbot', file: 'digitalisimo-chatbot.php', zip: 'digitalisimo-ia-tools-1.0.25.zip', version: '1.0.25' },
  { dir: 'digitalisimo-hosting', file: 'digitalisimo-hosting.php', zip: 'digitalisimo-hosting-1.0.8.zip', version: '1.0.8' },
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
const seoBootstrap = readFileSync('digitalisimo-seo/digitalisimo-integrations.php', 'utf8');
const seoSettings = readFileSync('digitalisimo-seo/includes/class-settings.php', 'utf8');
const seoAi = readFileSync('digitalisimo-seo/includes/class-seo-ai.php', 'utf8');
const chatbotAi = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai.php', 'utf8');
const chatbotImages = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai-images.php', 'utf8');
const chatbotMcpArticles = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-mcp-articles.php', 'utf8');
const coreFiles = modules.map((module) => readFileSync(join(module.dir, 'includes/class-digitalisimo-core.php'), 'utf8'));
const articleChatPublisher = readFileSync('digitalisimo-seo/includes/class-content-publisher.php','utf8');
const articleChatEditor = readFileSync('digitalisimo-seo/includes/class-editorial.php','utf8');
if (!articleChatEditor.includes("add_shortcode( 'digitalisimo_article_chat'") ||
    !articleChatEditor.includes("get_post_meta( get_the_ID(), 'digitalisimo_article_chat', true )"))
  throw new Error('Falta shortcode del chat conectado al metadato del artículo.');
if (!articleChatPublisher.includes("'article_chat_supported' => true") ||
    !articleChatPublisher.includes("self::sanitize_article_chat( $request->get_param( 'digitalisimo_article_chat' ) )") ||
    !articleChatPublisher.includes("update_post_meta( $post_id, 'digitalisimo_article_chat', $article_chat )"))
  throw new Error('El publisher debe validar y guardar el JSON del chat antes de confirmar el borrador.');

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
if (!seoSuite.includes('Digitalisimo_Integrations_SEO_Front_Inspector::render()')) throw new Error('Diagnóstico debe incluir el inspector del front');
if (!seoSuite.includes("'diagnostic' => 'SEO Front'")) throw new Error('SEO Front debe ser la última pestaña de SEO');
if (!seoSuite.includes("$title = $title ?: get_the_title( $id )")) throw new Error('SEO Front debe tener título de respaldo en contenido singular');
if (!seoSuite.includes("'Organization', 'LocalBusiness', 'Person'")) throw new Error('El tipo Organization debe validarse antes de emitir Schema');
if (!seoBootstrap.includes('class-seo-front-inspector.php')) throw new Error('SEO Front debe estar disponible');
if (!seoBootstrap.includes("includes/class-seo-front-inspector.php")) throw new Error('Falta cargar el inspector del front');
if (!seoEditorial.includes("'Rank Math' => get_post_meta") || !seoEditorial.includes("'Yoast SEO' => get_post_meta")) throw new Error('El inventario de keywords debe reunir Digitalisimo, Rank Math y Yoast SEO');
if (!seoEditorial.includes('data-content-panel') || !seoEditorial.includes('data-content-tab')) throw new Error('Contenido debe cambiar sus secciones dentro de la misma pantalla');
if (!seoEditorial.includes("add_submenu_page( null, 'Contenido', 'Contenido'")) throw new Error('Contenido debe abrirse dentro de la navegación SEO, no como submenú lateral');
if (!seoSuite.includes("'digitalisimo-content' => array( 'Contenido', 'content_page' )")) throw new Error('La pantalla Contenido no está registrada por el módulo SEO activo');
if (!seoSettings.includes('add_submenu_page( null,')) throw new Error('Los ajustes SEO heredados deben ocultarse del menú lateral');
if (!seoAi.includes("class_exists( 'Digitalisimo_AI' ) ) add_submenu_page( 'digitalisimo', 'SEO AI'")) throw new Error('SEO AI debe ser un submenú independiente condicionado por IA Tools');
if (!seoBootstrap.includes("if ( class_exists( 'Digitalisimo_AI' ) ) {")) throw new Error('SEO AI debe inicializarse sólo con IA Tools activo');
if (chatbotAi.includes("add_action( 'admin_menu', array( __CLASS__, 'menu' )")) throw new Error('IA Tools no debe registrar un segundo submenú');
if (!chatbotImages.includes("add_submenu_page( null, 'IA · Imágenes'")) throw new Error('El generador de imágenes debe abrirse desde la pestaña IA Tools');
if (!chatbotMcpArticles.includes("register_rest_route( 'digitalisimo-mcp/v1', '/create-draft'")) throw new Error('Falta el endpoint MCP para crear borradores SEO');
if (chatbotMcpArticles.includes("Digitalisimo_AI::complete( 'content'")) throw new Error('El MCP editorial debe usar el contenido de Codex, no una API de IA');
if (!chatbotMcpArticles.includes("preg_replace( '#<h1\\b[^>]*>.*?</h1>\\s*#is'")) throw new Error('El MCP editorial debe quitar el H1 duplicado del contenido');
if (!chatbotMcpArticles.includes("'post_status' => 'draft'")) throw new Error('El MCP debe crear sólo borradores');
if (seoAiTools.some((source) => source.includes("add_submenu_page( 'digitalisimo'"))) throw new Error('Una herramienta SEO AI sigue expuesta como submenú lateral');
if (coreFiles.some((source) => !source.includes("remove_submenu_page( 'digitalisimo', 'digitalisimo' )"))) throw new Error('El submenú automático Digitalisimo no se oculta en todos los módulos');

console.log(`Suite validada: ${modules.length} módulos, ${phpFiles.length} archivos PHP, sin clases duplicadas no protegidas.`);
