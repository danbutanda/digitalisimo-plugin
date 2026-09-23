import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const packageDir = process.env.DIGITALISIMO_PACKAGE_DIR || '.';

const modules = [
  { dir: 'digitalisimo-seo', file: 'digitalisimo-integrations.php', zip: 'digitalisimo-seo-1.0.92.zip', version: '1.0.92' },
  { dir: 'digitalisimo-ecommerce', file: 'digitalisimo-ecommerce.php', zip: 'digitalisimo-ecommerce-1.0.17.zip', version: '1.0.17' },
  { dir: 'digitalisimo-geolocalizacion', file: 'digitalisimo-geolocalizacion.php', zip: 'digitalisimo-geolocalizacion-1.0.15.zip', version: '1.0.15' },
  { dir: 'digitalisimo.chatbot', file: 'digitalisimo-chatbot.php', zip: 'digitalisimo-ia-tools-1.0.40.zip', version: '1.0.40' },
  { dir: 'digitalisimo-hosting', file: 'digitalisimo-hosting.php', zip: 'digitalisimo-hosting-1.0.11.zip', version: '1.0.11' },
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
const seoEditorialTools = readFileSync('digitalisimo-seo/includes/class-editorial.php', 'utf8');
const seoSnippetCss = readFileSync('digitalisimo-seo/assets/snippet-editor.css', 'utf8');
const seoSnippetJs = readFileSync('digitalisimo-seo/assets/snippet-editor.js', 'utf8');
const seoAdminUiCss = readFileSync('digitalisimo-seo/assets/admin-ui.css', 'utf8');
const seoBootstrap = readFileSync('digitalisimo-seo/digitalisimo-integrations.php', 'utf8');
const seoSettings = readFileSync('digitalisimo-seo/includes/class-settings.php', 'utf8');
const seoAi = readFileSync('digitalisimo-seo/includes/class-seo-ai.php', 'utf8');
const chatbotAi = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai.php', 'utf8');
const chatbotImages = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai-images.php', 'utf8');
const chatbotDialogueCss = readFileSync('digitalisimo.chatbot/assets/article-dialogue.css', 'utf8');
const chatbotMcpArticles = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-mcp-articles.php', 'utf8');
const coreFiles = modules.map((module) => readFileSync(join(module.dir, 'includes/class-digitalisimo-core.php'), 'utf8'));
const updaterFiles = modules.map((module) => readFileSync(join(module.dir, 'includes/class-digitalisimo-updater.php'), 'utf8'));
if (new Set(updaterFiles).size !== 1 || updaterFiles.some((source) =>
  !source.includes("current_user_can( 'manage_network_plugins' )") ||
  !source.includes("network_admin_url( 'admin-post.php' )") ||
  !source.includes('$transient->no_update[ $file ]') ||
  !source.includes("delete_site_transient( 'update_plugins' )")
)) throw new Error('Los módulos deben distribuir el mismo actualizador compatible con Multisite y caché de WordPress.');
const articleChatPublisher = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai-content-publisher.php','utf8');
const articleChatEditor = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai-article-chat.php','utf8');
const contentPlaybook = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai-content-playbook.php','utf8');
if (!articleChatEditor.includes("add_shortcode( 'digitalisimo_article_chat'") ||
    !articleChatEditor.includes("get_post_meta( self::current_content_id(), self::META_KEY, true )"))
  throw new Error('Falta shortcode del chat conectado al metadato del artículo.');
if (!articleChatPublisher.includes("'article_chat_supported' => true") ||
    !articleChatPublisher.includes("self::sanitize_article_chat( $request->get_param( 'digitalisimo_article_chat' ) )") ||
    !articleChatPublisher.includes("update_post_meta( $post_id, 'digitalisimo_article_chat', $article_chat )"))
  throw new Error('El publisher debe validar y guardar el JSON del chat antes de confirmar el borrador.');
if (articleChatPublisher.includes('digitalisimo_chat_shortcode_missing'))
  throw new Error('El publisher no debe exigir el shortcode en el cuerpo: Elementor puede colocarlo en la plantilla.');
if (!articleChatEditor.includes('$wp_query instanceof WP_Query') || !articleChatEditor.includes('if ( is_singular() ) wp_enqueue_style'))
	throw new Error('El chat debe resolver la entrada principal y cargar estilos sin depender del orden de Elementor.');
if (!articleChatEditor.includes("isset( $_POST['digitalisimo_article_chat_speakers'], $_POST['digitalisimo_article_chat_messages'] )") ||
    !articleChatEditor.includes('if ( ! $speakers || ! $messages ) return;'))
	throw new Error('Guardar una publicación sólo puede sobrescribir el chat con personajes y mensajes válidos.');
if (!chatbotAi.includes("'writing' => 'Redacción'") || !chatbotAi.includes('Digitalisimo_AI_Content_Playbook::fields'))
	throw new Error('IA Tools debe administrar el método editorial en su propia pestaña.');
if (!contentPlaybook.includes('resources/editorial-profile.json') || !articleChatPublisher.includes('Digitalisimo_AI_Content_Playbook::generate'))
	throw new Error('El publisher debe usar el perfil editorial propio de IA Tools.');
if (!articleChatPublisher.includes('default_article_chat') || !articleChatPublisher.includes("'article_chat_saved' => true"))
	throw new Error('Todo borrador editorial debe guardar un chat de artículo.');
if (!articleChatPublisher.includes("'speaker' => 'empresario'") || !articleChatPublisher.includes('Mi pequeño radar de palabras repetidas') || !articleChatPublisher.includes("'align' => 'start'"))
	throw new Error('El chat de respaldo debe seguir el patrón narrativo de publisher.');
if (!chatbotDialogueCss.includes('start{justify-self:start;background:#fff8df') || !chatbotDialogueCss.includes('center{justify-self:center;background:#fff2f8') || !chatbotDialogueCss.includes('end{justify-self:end;background:#edf7ff'))
	throw new Error('La paleta del chat debe ser amarillo Empresario, rosa MaryIA y azul Daniel.');
const mcpWordPress = readFileSync('mcp/digitalisimo-wordpress/server.mjs', 'utf8');
if (!mcpWordPress.includes('digitalisimo_article_chat: articleChat') || !mcpWordPress.includes('digitalisimo-publisher/v1/articles'))
	throw new Error('El MCP de WordPress debe enviar el chat editorial obligatorio al publisher de IA Tools.');
const chatbotBootstrap = readFileSync('digitalisimo.chatbot/digitalisimo-chatbot.php', 'utf8');
if (!chatbotBootstrap.includes('Digitalisimo_AI_Article_Chat::init') || !chatbotBootstrap.includes('Digitalisimo_AI_Content_Publisher::init'))
	throw new Error('IA Tools debe inicializar el chat y el publisher editorial.');
if (seoBootstrap.includes('class-content-publisher.php') || seoBootstrap.includes('Digitalisimo_Integrations_Content_Publisher::init'))
	throw new Error('SEO no debe cargar el publisher editorial de IA Tools.');

const seoAiTools = [
  'class-seo-ai-crawler-tools.php',
  'class-seo-ai-crawler-verifier.php',
  'class-seo-ai-audit-tools.php',
  'class-seo-ai-llms-tools.php',
  'class-seo-ai-indexnow-tools.php',
  'class-seo-ai-referral-tools.php',
  'class-seo-ai-entities-tools.php',
].map((file) => readFileSync(join('digitalisimo-seo/includes', file), 'utf8'));
const updaters = [
  'digitalisimo-seo/includes/class-digitalisimo-updater.php',
  'digitalisimo-ecommerce/includes/class-digitalisimo-updater.php',
  'digitalisimo-geolocalizacion/includes/class-digitalisimo-updater.php',
  'digitalisimo.chatbot/includes/class-digitalisimo-updater.php',
  'digitalisimo-hosting/includes/class-digitalisimo-updater.php',
].map((file) => readFileSync(file, 'utf8'));
if (updaters.some((source) => !source.includes('const CACHE_TTL  = MINUTE_IN_SECONDS') || !source.includes('set_site_transient( self::CACHE_KEY, $index, self::CACHE_TTL )')))
  throw new Error('Durante desarrollo todos los módulos deben consultar Releases cada minuto, incluso tras un fallo temporal.');
if (!seoSuite.includes("add_submenu_page( 'digitalisimo', 'SEO', 'SEO'")) throw new Error('Falta el acceso unificado SEO');
if (!seoSuite.includes("digitalisimo-seo-snippet-editor") || !seoSuite.includes('Vista previa en Google') || !seoSuite.includes('digitalisimo_native_slug'))
  throw new Error('SEO debe ofrecer editor de snippet con vista previa, título, descripción y permalink.');
if (!seoSnippetCss.includes('.digitalisimo-snippet-preview') || !seoSnippetJs.includes('data-snippet-preview') || !seoSuite.includes("'hidden_meta_boxes'"))
  throw new Error('El editor de snippet debe conservar la UI Digitalisimo y actualizar la vista previa en el navegador.');
if (seoEditorialTools.includes("update_post_meta( $id, 'digitalisimo_article_chat'"))
  throw new Error('SEO no debe sobrescribir el chat editorial, que pertenece exclusivamente a IA Tools.');
if (!seoSuite.includes('Digitalisimo_Integrations_SEO_Front_Inspector::render()')) throw new Error('Diagnóstico debe incluir el inspector del front');
if (!seoSuite.includes("'diagnostic' => 'SEO Front'")) throw new Error('SEO Front debe ser la última pestaña de SEO');
if (!seoSuite.includes("$title = $title ?: get_the_title( $id )")) throw new Error('SEO Front debe tener título de respaldo en contenido singular');
if (!seoSuite.includes("'Organization', 'LocalBusiness', 'Person'")) throw new Error('El tipo Organization debe validarse antes de emitir Schema');
if (!seoBootstrap.includes('class-seo-front-inspector.php')) throw new Error('SEO Front debe estar disponible');
if (!seoBootstrap.includes("includes/class-seo-front-inspector.php")) throw new Error('Falta cargar el inspector del front');
if (!seoEditorial.includes("'Rank Math' => get_post_meta") || !seoEditorial.includes("'Yoast SEO' => get_post_meta")) throw new Error('El inventario de keywords debe reunir Digitalisimo, Rank Math y Yoast SEO');
if (!seoEditorial.includes('data-content-panel') || !seoEditorial.includes('data-content-tab')) throw new Error('Contenido debe cambiar sus secciones dentro de la misma pantalla');
if (!seoEditorial.includes("'locations' => 'Ubicaciones'") || !seoEditorial.includes('digitalisimo_create_location') || !seoEditorial.includes("post_type' => self::content_types()")) throw new Error('Ubicaciones y clusters deben admitir todo tipo de contenido público.');
if (!seoEditorial.includes("'post_status' => array( 'publish', 'private', 'draft', 'pending' )") || !seoEditorialTools.includes('self::locations_cpt();') || !seoEditorialTools.includes("'post_author' => get_current_user_id()") || !seoEditorialTools.includes("'digitalisimo_location' === get_post_type")) throw new Error('El catálogo debe registrar, confirmar y mostrar las ubicaciones creadas.');
if (!seoEditorialTools.includes('cluster_candidates') || !seoEditorialTools.includes("update_post_meta( $parent, 'digitalisimo_seo_pillar', 'on' )") || !seoEditorialTools.includes("'elementor_library'")) throw new Error('Todo contenido público debe poder ser pilar de un cluster, sin plantillas privadas.');
if (seoEditorialTools.includes('! empty( $type->publicly_queryable )') || !seoAdminUiCss.includes('.widefat:not(select)') || !seoAdminUiCss.includes('width: min(100%, 460px)')) throw new Error('Los selectores de cluster deben incluir contenido público y conservar una UI compacta.');
if (!seoEditorial.includes("'defaults' => 'Configuración'") || !seoEditorial.includes('defaults_content') || !seoEditorialTools.includes('apply_default_cluster') || !seoSettings.includes("'default_cluster_pillar' => 0") || !seoSuite.includes('network_cluster_field')) throw new Error('Falta el pilar predeterminado global para contenido nuevo con paridad de red.');
if (!seoEditorial.includes("add_submenu_page( null, 'Contenido', 'Contenido'")) throw new Error('Contenido debe abrirse dentro de la navegación SEO, no como submenú lateral');
if (!seoSuite.includes("'digitalisimo-content' => array( 'Contenido', 'content_page' )")) throw new Error('La pantalla Contenido no está registrada por el módulo SEO activo');
if (!seoSettings.includes('add_submenu_page( null,')) throw new Error('Los ajustes SEO heredados deben ocultarse del menú lateral');
if (!seoAi.includes("class_exists( 'Digitalisimo_AI' ) ) add_submenu_page( 'digitalisimo', 'SEO AI'")) throw new Error('SEO AI debe ser un submenú independiente condicionado por IA Tools');
if (!seoBootstrap.includes("if ( class_exists( 'Digitalisimo_AI' ) ) {")) throw new Error('SEO AI debe inicializarse sólo con IA Tools activo');
if (chatbotAi.includes("add_action( 'admin_menu', array( __CLASS__, 'menu' )")) throw new Error('IA Tools no debe registrar un segundo submenú');
if (!chatbotImages.includes("add_submenu_page( null, 'IA · Imágenes de artículos'")) throw new Error('El generador de imágenes debe abrirse desde la pestaña IA Tools');
if (!chatbotImages.includes('META_COST') || !chatbotImages.includes('estimate_cost') || !chatbotImages.includes('Historial y costo estimado'))
  throw new Error('Las imágenes IA deben guardar modelo, calidad, costo estimado y mostrar historial con miniaturas.');
if (!chatbotAi.includes("'image_quality' => 'medium'") || !chatbotAi.includes('Modelo de generación'))
  throw new Error('IA Tools debe permitir configurar el modelo y la calidad de generación de imágenes.');
if (!chatbotImages.includes("const OUTPUT_WIDTH = 1536") || !chatbotImages.includes("const OUTPUT_HEIGHT = 864") || !chatbotImages.includes('optimize_to_webp')) throw new Error('Las imágenes de artículos deben terminar en formato 16:9');
if (!chatbotImages.includes("get_post_meta( $post->ID, 'digitalisimo_seo_keywords'") || !chatbotImages.includes("set_post_thumbnail( $post_id, $attachment )")) throw new Error('El generador debe usar el contexto SEO y asignar la imagen destacada');
if (!chatbotImages.includes("'image/webp'") || !chatbotImages.includes('WEBP_QUALITY = 82') || !chatbotImages.includes('optimize_to_webp')) throw new Error('Las imágenes de artículos deben convertirse a WebP optimizado.');
if (!chatbotImages.includes('.editor-post-featured-image') || !chatbotImages.includes('Imagen destacada|Featured image') || !chatbotImages.includes('MutationObserver') || !chatbotImages.includes('digitalisimo-ai-article-image-button') || !chatbotImages.includes('get_post_thumbnail_id( $post_id )')) throw new Error('El generador debe vivir en Imagen destacada de Gutenberg y confirmar su asignación.');
if (!chatbotMcpArticles.includes("register_rest_route( 'digitalisimo-mcp/v1', '/create-draft'")) throw new Error('Falta el endpoint MCP para crear borradores SEO');
if (chatbotMcpArticles.includes("Digitalisimo_AI::complete( 'content'")) throw new Error('El MCP editorial debe usar el contenido de Codex, no una API de IA');
if (!chatbotMcpArticles.includes("preg_replace( '#<h1\\b[^>]*>.*?</h1>\\s*#is'")) throw new Error('El MCP editorial debe quitar el H1 duplicado del contenido');
if (!chatbotMcpArticles.includes("'post_status' => 'draft'")) throw new Error('El MCP debe crear sólo borradores');
if (seoAiTools.some((source) => source.includes("add_submenu_page( 'digitalisimo'"))) throw new Error('Una herramienta SEO AI sigue expuesta como submenú lateral');
if (coreFiles.some((source) => !source.includes("remove_submenu_page( 'digitalisimo', 'digitalisimo' )"))) throw new Error('El submenú automático Digitalisimo no se oculta en todos los módulos');

console.log(`Suite validada: ${modules.length} módulos, ${phpFiles.length} archivos PHP, sin clases duplicadas no protegidas.`);
