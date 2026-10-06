import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const packageDir = process.env.DIGITALISIMO_PACKAGE_DIR || '.';

const modules = [
  { dir: 'digitalisimo-seo', file: 'digitalisimo-integrations.php', zip: 'digitalisimo-seo-1.0.223.zip', version: '1.0.223' },
  { dir: 'digitalisimo.chatbot', file: 'digitalisimo-chatbot.php', zip: 'digitalisimo-ia-tools-1.0.54.zip', version: '1.0.54' },
  { dir: 'digitalisimo-hosting', file: 'digitalisimo-hosting.php', zip: 'digitalisimo-hosting-1.0.25.zip', version: '1.0.25' },
  { dir: 'digitalisimo-backups', file: 'digitalisimo-backups.php', zip: 'digitalisimo-backups-1.0.52.zip', version: '1.0.52' },
];
const toolsModule = { dir: 'digitalisimo-tools', file: 'digitalisimo-tools.php', zip: 'digitalisimo-tools-1.0.28.zip', version: '1.0.28' };
const elementsModule = { dir: 'digitalisimo-elements', file: 'pro-elements.php', zip: 'digitalisimo-elements-4.3.0.14.zip', version: '4.3.0.14' };
const webpModule = readFileSync('digitalisimo-tools/modules/class-media-webp.php', 'utf8');
const toolsMenu = readFileSync('digitalisimo-tools/modules/elementor-network-templates/class-network-admin.php', 'utf8');
const toolsBootstrap = readFileSync('digitalisimo-tools/modules/elementor-network-templates/class-module.php', 'utf8');
if (!webpModule.includes('type="button" id="digitalisimo-webp-start"') || !webpModule.includes("admin_url( 'admin-ajax.php' )") || !webpModule.includes('self::site_ids( $network, $site_id )') || !webpModule.includes('$site_id !== get_current_blog_id()')) throw new Error('WebP debe conservar la pantalla y limitar el trabajo al sitio seleccionado.');
if (!webpModule.includes("wp_update_post( $change, true )") || !webpModule.includes('update_attached_file( $attachment_id, $saved[\'path\'] )') || !webpModule.includes('wp_generate_attachment_metadata( $attachment_id, $saved[\'path\'] )') || !webpModule.includes('wp_update_attachment_metadata( $attachment_id, $metadata )') || !webpModule.includes('wp_unique_filename') || !webpModule.includes('self::clear_errors( $network, $site_id )') || webpModule.includes("array( 'key' => self::META_DONE, 'compare' => 'NOT EXISTS' )")) throw new Error('WebP debe actualizar el adjunto real y volver a procesar los JPG/PNG marcados por versiones anteriores.');
if (!webpModule.includes("add_filter( 'display_media_states'") || !webpModule.includes("add_filter( 'wp_prepare_attachment_for_js'") || !webpModule.includes('self::META_DONE, true') || !webpModule.includes('wp_normalize_path( $file ) === wp_normalize_path( $optimized_file )') || !webpModule.includes("'image/webp' === get_post_mime_type")) throw new Error('La etiqueta de optimización debe identificar sólo adjuntos WebP convertidos por Tools.');
if (!toolsMenu.includes('self::network_context() ? network_admin_url') || !toolsMenu.includes('self::context_field()') || !toolsBootstrap.includes('Network_Admin::init(); if ( ! is_multisite() ) return;')) throw new Error('Tools debe conservar el contexto y ofrecer WebP en WordPress individual.');
for (const directory of ['digitalisimo-seo', 'digitalisimo.chatbot', 'digitalisimo-hosting']) if (!readFileSync(join(directory, 'includes/class-digitalisimo-updater.php'), 'utf8').includes('digitalisimo_context')) throw new Error(`El actualizador de ${directory} debe conservar el contexto de origen.`);
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
  if (!source.includes(`Update URI: https://github.com/danbutanda/digitalisimo-plugin/${module.zip.replace(/-[0-9.]+\.zip$/, '')}`)) throw new Error(`Falta Update URI en ${main}`);
	const zipListing = execFileSync('unzip', ['-Z1', zip], { encoding: 'utf8' });
	if (!zipListing.includes(`${module.dir}/${module.file}`)) throw new Error(`El ZIP ${zip} no conserva su archivo principal`);
	if (zipListing.includes('.DS_Store')) throw new Error(`El ZIP ${zip} contiene archivos de sistema`);
  if (module.dir === 'digitalisimo-seo' && ['class-performance-manager.php', 'class-performance-hero.php', 'class-performance-font-inline.php', 'class-performance-css-audit.php', 'class-asset-diagnostics.php', 'class-performance-measurements.php'].some((file) => !zipListing.includes(`includes/performance/${file}`))) throw new Error('El ZIP SEO no contiene el módulo de rendimiento.');
  if (module.dir === 'digitalisimo-seo' && !zipListing.includes('assets/js/performance-css-audit.js')) throw new Error('El ZIP SEO no contiene la auditoría CSS del navegador.');
  if (module.dir === 'digitalisimo-seo' && !zipListing.includes('includes/class-tools-update-bridge.php')) throw new Error('El ZIP SEO no contiene el puente de actualización de Tools.');
  walk(module.dir);
}
{
  const main = join(toolsModule.dir, toolsModule.file);
  const zip = join(packageDir, toolsModule.zip);
  if (!existsSync(main) || !existsSync(zip)) throw new Error(`Falta ${main} o ${zip}`);
  const source = readFileSync(main, 'utf8');
  if (!source.includes(`Version: ${toolsModule.version}`)) throw new Error(`Versión incorrecta en ${main}`);
  if (!source.includes('Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-tools')) throw new Error('Falta Update URI en DIGITALÍSIMO Tools');
  const listing = execFileSync('unzip', ['-Z1', zip], { encoding: 'utf8' });
  if (!listing.includes(`${toolsModule.dir}/${toolsModule.file}`) || listing.includes('.DS_Store')) throw new Error('El paquete de DIGITALÍSIMO Tools no tiene una estructura válida');
  if (!readFileSync(join(toolsModule.dir, 'modules/elementor-network-templates/class-network-admin.php'), 'utf8').includes("'Herramientas', $capability, self::PAGE")) throw new Error('El submenú de DIGITALÍSIMO Tools debe mostrarse como Herramientas.');
  for (const required of ['class-registry.php', 'class-sync.php', 'class-dependency-resolver.php', 'class-elementor-adapter.php', 'class-network-admin.php', 'assets/media-badges.js', 'assets/media-badges.css']) if (!listing.includes(required)) throw new Error(`Falta ${required} en DIGITALÍSIMO Tools`);
  if (listing.includes('modules/elementor-slider/') || listing.includes('assets/slider-optimizado.css') || source.includes('modules/elementor-slider/class-module.php')) throw new Error('El slider ya no debe formar parte de DIGITALÍSIMO Tools.');
}

const declarations = new Map();
{
  const main = join(elementsModule.dir, elementsModule.file);
  const zip = join(packageDir, elementsModule.zip);
  if (!existsSync(main) || !existsSync(zip)) throw new Error(`Falta ${main} o ${zip}`);
  const source = readFileSync(main, 'utf8');
  if (!source.includes(`Version: ${elementsModule.version}`) || !source.includes('Requires Plugins: elementor') || !source.includes('Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-elements')) throw new Error('La versión derivada debe exigir Elementor y tener su actualizador propio.');
  const listing = execFileSync('unzip', ['-Z1', zip], { encoding: 'utf8' });
  for (const required of ['pro-elements.php', 'plugin.php', 'license.txt', 'COPYING', 'DIGITALISIMO_CHANGES.md', 'digitalisimo-updater.php', 'digitalisimo-compat.php', 'digitalisimo-brand.php', 'vendor/autoload.php', 'vendor/composer/ClassLoader.php', 'vendor/composer/autoload_real.php', 'assets/digitalisimo/logo-negro.webp', 'assets/digitalisimo/logo-blanco.webp', 'assets/digitalisimo/icono.png', 'modules/digitalisimo-slider/class-module.php', 'modules/digitalisimo-slider/class-widget.php', 'assets/css/slider-optimizado.css', 'modules/digitalisimo-widgets/class-registry.php', 'modules/digitalisimo-widgets/class-animated-link.php', 'assets/css/animated-link.css', 'modules/digitalisimo-widgets/class-fancy-list.php', 'assets/css/fancy-list.css']) if (!listing.includes(`${elementsModule.dir}/${required}`)) throw new Error(`Falta ${required} en DIGITALÍSIMO Elements`);
  if (listing.split('\n').some((entry) => /(^|\/)(bdthemes-element-pack|element-pack-pro)(\/|$)|bdthemes/i.test(entry))) throw new Error('REFERENCE LEAK CHECK: el ZIP de Elements contiene la copia de Element Pack.');
  if (!source.includes('\\Digitalisimo\\Elements\\Elementor_Slider::init()') || !readFileSync(join(elementsModule.dir, 'modules/digitalisimo-slider/class-widget.php'), 'utf8').includes("return 'digitalisimo-slider-optimizado'")) throw new Error('Elements debe registrar el slider con el identificador que usan las páginas existentes.');
  if (listing.includes('.DS_Store')) throw new Error('El paquete Elements contiene archivos de sistema.');
  for (const quoteSheet of ['widget-blockquote.min.css', 'widget-blockquote-rtl.min.css', 'widget-blockquote-rtl-rtl.min.css']) {
    const css = readFileSync(join(elementsModule.dir, 'assets/css', quoteSheet), 'utf8');
    if (css.includes('вЂњ') || !css.includes('content:\"\\201C\"')) throw new Error(`La comilla del blockquote no es segura en ${quoteSheet}.`);
  }
  if (!readFileSync(join(elementsModule.dir, 'modules/blockquote/module.php'), 'utf8').includes('DIGITALISIMO_ELEMENTS_VERSION')) throw new Error('El CSS del blockquote debe cambiar de versión con DIGITALÍSIMO Elements.');
  if (!readFileSync(join(elementsModule.dir, 'license.txt'), 'utf8').includes('PRO Elements team') || !readFileSync(join(elementsModule.dir, 'plugin.php'), 'utf8').includes('DIGITALÍSIMO: este derivado se actualiza únicamente desde su propio asset.')) throw new Error('Se perdieron créditos o sigue activo el actualizador original de Elements.');
}
// Cada módulo debe llevar el mismo controlador probado; así una Release parcial
// no puede perder la actualización en la propia pantalla Plugins.
const inlineUpdater = readFileSync('digitalisimo-elements/assets/js/update-in-place.js', 'utf8');
for (const module of [...modules, toolsModule, elementsModule]) {
  const zip = join(packageDir, module.zip);
  const asset = `${module.dir}/assets/js/update-in-place.js`;
  const packaged = execFileSync('unzip', ['-p', zip, asset], { encoding: 'utf8' });
  if (packaged !== inlineUpdater || readFileSync(asset, 'utf8') !== inlineUpdater) throw new Error(`Controlador de actualizaciones ausente o distinto en ${zip}`);
}
for (const file of phpFiles) {
  const source = readFileSync(file, 'utf8');
  if (/delete_(?:site_)?transient\s*\(\s*['"]update_plugins['"]/.test(source)) throw new Error(`${file} borra el inventario global de actualizaciones`);
  for (const match of source.matchAll(/(?:final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/g)) {
    const name = match[1];
    if (!declarations.has(name)) declarations.set(name, []);
    declarations.get(name).push({ file, guarded: source.includes(`class_exists( '${name}' )`) || source.includes(`class_exists('${name}')`) });
  }
}
for (const [name, records] of declarations) {
  if (records.length > 1 && !records.every((record) => record.guarded)) throw new Error(`Clase duplicada no protegida: ${name} en ${records.map((record) => record.file).join(', ')}`);
}

const hosting = readFileSync('digitalisimo-hosting/includes/class-hosting.php', 'utf8');
if (!hosting.includes('migrate_legacy_options') || !hosting.includes('digitalisimo_integrations_options') || !hosting.includes('hosting_product_ids') || !hosting.includes('require_domain_hosting') || !hosting.includes("add_shortcode( 'digitalisimo_domain_search'" ) || !hosting.includes("'digitalisimo/v1'")) throw new Error('Hosting debe migrar las opciones de Ecommerce y conservar sus reglas, shortcode y ruta pública.');
if (!hosting.includes("array( 'digitalisimo-hosting/v1', 'digitalisimo/v1' )") || !hosting.includes('replace_hosting') || !hosting.includes('simplify_checkout')) throw new Error('Hosting debe conservar la compatibilidad de checkout y API de Ecommerce.');
const seoSuite = readFileSync('digitalisimo-seo/includes/class-seo-suite.php', 'utf8');
const seoEditorial = readFileSync('digitalisimo-seo/includes/class-seo.php', 'utf8');
const seoEditorialTools = readFileSync('digitalisimo-seo/includes/class-editorial.php', 'utf8');
const seoSnippetCss = readFileSync('digitalisimo-seo/assets/snippet-editor.css', 'utf8');
const seoSnippetModalCss = readFileSync('digitalisimo-seo/assets/snippet-modal.css', 'utf8');
const seoEditorUiCss = readFileSync('digitalisimo-seo/assets/editor-ui.css', 'utf8');
const seoSnippetJs = readFileSync('digitalisimo-seo/assets/snippet-editor.js', 'utf8');
const seoAdminUiCss = readFileSync('digitalisimo-seo/assets/admin-ui.css', 'utf8');
const seoBootstrap = readFileSync('digitalisimo-seo/digitalisimo-integrations.php', 'utf8');
const seoSettings = readFileSync('digitalisimo-seo/includes/class-settings.php', 'utf8');
const seoResolver = readFileSync('digitalisimo-seo/includes/class-seo-resolver.php', 'utf8');
const seoAi = readFileSync('digitalisimo-seo/includes/class-seo-ai.php', 'utf8');
if (!seoSettings.includes("'seo_ai_google_extended_policy' => 'allow'") || !seoSettings.includes("Digitalisimo_Integrations_SEO_AI::sanitize_google_extended_policy") || !seoSuite.includes("self::nf( 'seo_ai_google_extended_policy'") || !seoSuite.includes("self::f( 'seo_ai_google_extended_policy'") || !seoSuite.includes("$current[ $key ] = Digitalisimo_Integrations_SEO_AI::sanitize_google_extended_policy")) throw new Error('Google-Extended necesita opción independiente con paridad de sitio y red.');
if (!seoSettings.includes("'seo_title_post'         => '%title% %sep% %sitename%'") || !seoSettings.includes("'seo_description_post'   => '%excerpt%'") || !seoSettings.includes("'seo_title_page'         => '%title%'") || !seoSettings.includes("'seo_description_page'   => '%excerpt%'") || !seoSettings.includes("'seo_title_archive'      => '%title%'") || !seoSettings.includes("'seo_description_archive'=> '%excerpt%'") ) throw new Error('Los valores iniciales de títulos y descripciones SEO no coinciden con la configuración predeterminada.');
const chatbotAi = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai.php', 'utf8');
const chatbotImages = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-ai-images.php', 'utf8');
const chatbotDialogueCss = readFileSync('digitalisimo.chatbot/assets/article-dialogue.css', 'utf8');
const chatbotMcpArticles = readFileSync('digitalisimo.chatbot/includes/class-digitalisimo-mcp-articles.php', 'utf8');
const coreFiles = modules.map((module) => readFileSync(join(module.dir, 'includes/class-digitalisimo-core.php'), 'utf8'));
const sharedModules = modules.filter((module) => module.dir !== 'digitalisimo-backups');
const updaterFiles = sharedModules.map((module) => readFileSync(join(module.dir, 'includes/class-digitalisimo-updater.php'), 'utf8'));
if (updaterFiles.some((source) => !source.includes("'update_plugins_github.com'") || !source.includes('function uri_update('))) throw new Error('Los módulos deben usar el mecanismo Update URI de WordPress.');
if (!readFileSync('digitalisimo-backups/includes/class-digitalisimo-updater.php', 'utf8').includes("'update_plugins_github.com'") || !readFileSync('digitalisimo-tools/includes/class-updater.php', 'utf8').includes("'update_plugins_github.com'")) throw new Error('Backups y Tools deben usar Update URI de forma independiente.');
if (updaterFiles.some((source) =>
  !source.includes("current_user_can( 'manage_network_plugins' )") ||
	!source.includes("$base = admin_url( 'admin-post.php' );") ||
  !source.includes('$transient->no_update[ $file ]') ||
  source.includes("delete_site_transient( 'update_plugins' )") ||
  !source.includes('! is_object( $transient ) || ! isset( $transient->checked )')
)) throw new Error('Los módulos deben distribuir el mismo actualizador compatible con Multisite y caché de WordPress.');
const backups = readFileSync('digitalisimo-backups/includes/class-backups.php', 'utf8');
if (!backups.includes("'wp_ajax_' . self::ACTION_MANUAL_START") || !backups.includes('function manual_status()') || !backups.includes('function wordpress_config_file()') || !readFileSync('digitalisimo-backups/assets/manual-progress.js', 'utf8').includes('digitalisimo_backups_manual_status')) throw new Error('Backups debe incluir avance consultable y validar wp-config.php antes del trabajo largo.');
if (!backups.includes('create_network') || !backups.includes('create_site') || !backups.includes('create_incremental_site') || !backups.includes('network_context') || !backups.includes('servers_panel') || !backups.includes('manual_destinations') || !backups.includes('digitalisimo_backup_destination') || !backups.includes('incremental_destination') || !backups.includes('remove_history') || !backups.includes('restore_digitalisimo_zip') || !backups.includes('restore_updraft_set') || !backups.includes('download_remote') || !backups.includes('delete_remote') || !backups.includes('digitalisimo_backups_policy') || !backups.includes('create_automation') || !backups.includes('update_automation') || !backups.includes('delete_automation') || !backups.includes('automation_fields') || !backups.includes('MIGRAR-A-WORDPRESS-UNICO.txt') || !backups.includes('database.sql') || !backups.includes('manifest.json') || !backups.includes('ssh2_connect') || !backups.includes('oauth2.googleapis.com') || !backups.includes('graph.microsoft.com') || !backups.includes('resumable_upload'))
  throw new Error('Backups debe incluir archivos y base de datos, con destinos local, SFTP, Google Drive y OneDrive.');
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
  'digitalisimo.chatbot/includes/class-digitalisimo-updater.php',
  'digitalisimo-hosting/includes/class-digitalisimo-updater.php',
].map((file) => readFileSync(file, 'utf8'));
if (updaters.some((source) => !source.includes('const CACHE_TTL  = HOUR_IN_SECONDS') || !source.includes('set_site_transient( self::CACHE_KEY, $index, self::CACHE_TTL )')))
  throw new Error('Durante desarrollo todos los módulos deben consultar Releases cada minuto, incluso tras un fallo temporal.');
if (!seoSuite.includes("add_submenu_page( 'digitalisimo', 'SEO', 'SEO'")) throw new Error('Falta el acceso unificado SEO');
if (!seoSuite.includes("digitalisimo-seo-snippet-editor") || !seoSuite.includes('Vista previa en Google') || !seoSuite.includes('digitalisimo_native_slug'))
  throw new Error('SEO debe ofrecer editor de snippet con vista previa, título, descripción y permalink.');
if (!seoSnippetCss.includes('.digitalisimo-snippet-preview') || !seoSnippetJs.includes('data-snippet-preview') || !seoSuite.includes("'hidden_meta_boxes'"))
  throw new Error('El editor de snippet debe conservar la UI Digitalisimo y actualizar la vista previa en el navegador.');
if (!seoSuite.includes('digitalisimo-seo-snippet-modal') || !seoSnippetModalCss.includes('.is-expanded') || !seoSnippetJs.includes('data-snippet-expand') || !seoSnippetJs.includes('digitalisimoSnippetPlaceholder') || !seoSuite.includes('digitalisimo-snippet-launch'))
  throw new Error('El editor de snippet debe poder abrirse en una ventana amplia sin duplicar sus campos.');
if (!seoSuite.includes('data-snippet-save') || !seoSnippetJs.includes("savePost()") || !seoSnippetModalCss.includes('digitalisimo-snippet-actions'))
  throw new Error('La ventana de snippet debe conservar espaciado y permitir guardar la entrada.');
if (!seoSuite.includes('Digitalisimo_Integrations_Editorial::editor_panels( $post )') || seoEditorialTools.includes("'digitalisimo_editorial', 'Contenido SEO · Digitalisimo'"))
	throw new Error('Keywords, cluster y ubicación deben vivir en el bloque SEO lateral, fuera del popup de snippet.');
if (!seoSuite.includes("digitalisimo-editor-ui") || !seoEditorUiCss.includes('[id^="digitalisimo-"]') || !seoEditorUiCss.includes('.digitalisimo-editorial-panels'))
  throw new Error('Los paneles de Digitalisimo dentro del editor deben compartir la UI de la marca.');
if (!seoSuite.includes("'side', 'high'") || !seoSuite.includes("digitalisimo_native_seo"))
	throw new Error('El snippet SEO completo debe estar accesible directamente en la barra lateral del editor.');
if (!chatbotImages.includes('creative_direction') || !chatbotImages.includes('add_post_type_support') || !chatbotImages.includes('set_post_thumbnail'))
	throw new Error('La imagen generada debe variar su dirección creativa y asignarse siempre como destacada.');
if (seoEditorialTools.includes("update_post_meta( $id, 'digitalisimo_article_chat'"))
  throw new Error('SEO no debe sobrescribir el chat editorial, que pertenece exclusivamente a IA Tools.');
if (!readFileSync('digitalisimo-seo/includes/class-seo-audit-hub.php', 'utf8').includes('Digitalisimo_Integrations_SEO_Front_Inspector::render()')) throw new Error('Auditoría debe incluir el inspector del front');
const seoFrontInspector = readFileSync('digitalisimo-seo/includes/class-seo-front-inspector.php', 'utf8');
const seoKeywordMatch = readFileSync('digitalisimo-seo/includes/class-keyword-match.php', 'utf8');
if (!seoFrontInspector.includes('Keyword_Match::slug_matches') || !seoKeywordMatch.includes("'' === $path") || !seoKeywordMatch.includes('array_intersect( $terms')) throw new Error('SEO Front debe comparar la URL real sin recomendar cambios en portada ni exigir un slug literal.');
if (!seoKeywordMatch.includes('Normalizer::FORM_KD') || !seoKeywordMatch.includes('remove_accents') || !seoKeywordMatch.includes("preg_replace( '/\\\\s+/u', ' '") || !seoKeywordMatch.includes('public static function contains') || !seoKeywordMatch.includes('public static function slug_matches') || !seoBootstrap.includes('class-keyword-match.php')) throw new Error('Las keywords deben normalizar Unicode, acentos, mayúsculas y espacios antes de compararse.');
if (!seoFrontInspector.includes('missing_secondary_keywords') || !seoSuite.includes("<meta name=\"author\"") || !seoSuite.includes("<meta name=\"publisher\"") || !readFileSync('digitalisimo-seo/includes/schema/class-schema-graph.php', 'utf8').includes("'publisher' => array( '@id' => $ids['identity'] )") || !seoSuite.includes('ensure_post_author')) throw new Error('SEO Front debe agrupar keywords secundarias y el front debe emitir autor y publisher reales.');
const seoImages = readFileSync('digitalisimo-seo/includes/performance/class-performance-images.php', 'utf8');
if (seoSuite.includes('attachment_alt') || !seoSettings.includes("'seo_alt_keyword_mode'   => 'contextual'") || !seoSuite.includes("'seo_alt_keyword_mode'") || !seoImages.includes('Digitalisimo_Integrations_Image_Alt::apply') || !seoBootstrap.includes('class-image-alt.php')) throw new Error('El ALT debe resolverse en la salida con la palabra clave de apoyo por modo, sin plantilla que repita la keyword.');
if (!seoResolver.includes('site_only_keys') || !seoResolver.includes("'seo_site_name', 'seo_default_image', 'seo_default_image_alt'") || !seoSuite.includes('navigation_tabs( $tab, true )') || !seoSuite.includes('network_site_fields') || !seoSuite.includes('%sep%') || !readFileSync('digitalisimo-seo/assets/admin-ui.css', 'utf8').includes('digitalisimo-inherited-value')) throw new Error('La identidad e imágenes locales no deben heredarse y la red debe usar la navegación del sitio y mostrar valores heredados.');
if (!seoSuite.includes('digitalisimo-default-value') || !seoSettings.includes('digitalisimo-default-note') || !readFileSync('digitalisimo-seo/assets/admin-ui.css', 'utf8').includes('digitalisimo-default-note')) throw new Error('Los valores predeterminados del plugin deben distinguirse visualmente.');
if (!seoSettings.includes('migrate_template_defaults') || !seoSettings.includes('TEMPLATE_DEFAULTS_MIGRATION') || !seoSettings.includes('template_default_keys') || !seoSuite.includes('template_default_keys')) throw new Error('Las plantillas predeterminadas deben reemplazar valores vacíos de versiones anteriores y al guardar.');
const crawlerVerifier = readFileSync('digitalisimo-seo/includes/class-seo-ai-crawler-verifier.php', 'utf8');
if (!crawlerVerifier.includes('observe_public_request') || !crawlerVerifier.includes('PROCESS_HOOK') || !crawlerVerifier.includes('wp_schedule_single_event') || !crawlerVerifier.includes('Bloquear IP') || !crawlerVerifier.includes('block_fake_bot')) throw new Error('Los bots falsos deben detectarse en cola y ofrecer bloqueo manual.');
if (!seoEditorialTools.includes("'digitalisimo-seo/v1', '/editorial-suggestions'") || !seoEditorialTools.includes("Digitalisimo_AI::complete( 'seo'") || !seoEditorialTools.includes("array( 'keywords', 'description' )") || !seoEditorialTools.includes('delete_post_meta( $id, \'digitalisimo_seo_search_intent\' )') || !readFileSync('digitalisimo-seo/assets/keyword-manager.js', 'utf8').includes('applyDescription')) throw new Error('Keywords y descripción deben generarse con acciones independientes; la descripción se aplica a extracto y meta sin intención manual.');
if (!seoEditorialTools.includes('La ficha verde es la keyword principal') || !readFileSync('digitalisimo-seo/assets/keyword-manager.js', 'utf8').includes('is-primary') || !readFileSync('digitalisimo-seo/assets/keyword-manager.css', 'utf8').includes('digitalisimo-keyword-primary-badge')) throw new Error('La keyword principal debe destacarse y el orden de las fichas debe poder cambiarse por arrastre.');
if (!seoSuite.includes("'content' => 'Contenido', 'audit' => 'Auditoría' );") || seoSuite.includes("'tools' => 'Avanzado'")) throw new Error('Auditoría debe ser la última pestaña de SEO y reemplazar a Avanzado y SEO Front');
const seoAuditHub = readFileSync('digitalisimo-seo/includes/class-seo-audit-hub.php', 'utf8');
if (!seoAuditHub.includes('Schema_Audit::render()') || !seoAuditHub.includes('SEO_Front_Inspector::render()') || !seoAuditHub.includes('LLMS::render_status') || !seoAuditHub.includes('digitalisimo-performance&section=')) throw new Error('Auditoría debe reunir Schema, SEO Front, llms.txt y las pruebas de Rendimiento');
const toolsSiteOptions = readFileSync('digitalisimo-tools/modules/site-options/class-site-options.php', 'utf8');
if (!toolsSiteOptions.includes("'login_slug' => 'seo_hide_login_slug'") || !readFileSync('digitalisimo-tools/digitalisimo-tools.php', 'utf8').includes('Site_Options::boot()') || !seoBootstrap.includes('tools_handles_site_options() ) Digitalisimo_Integrations_Hide_Login::boot()')) throw new Error('Ruta privada, adjuntos y desplazamiento móvil deben vivir en Tools sin ejecutarse dos veces');
if (seoSuite.includes("'seo_hide_login_slug', 'Ruta privada") || seoSuite.includes("'seo_redirect_attachments', 'Redirigir")) throw new Error('SEO ya no debe mostrar las opciones que pasaron a Tools');
if (!seoSuite.includes("$title = $title ?: get_the_title( $id )")) throw new Error('SEO Front debe tener título de respaldo en contenido singular');
if (!readFileSync('digitalisimo-seo/includes/schema/class-schema-graph.php', 'utf8').includes("array( 'Organization', 'LocalBusiness', 'Person' )")) throw new Error('El tipo Organization debe validarse antes de emitir Schema');
if (!seoBootstrap.includes('class-seo-front-inspector.php')) throw new Error('SEO Front debe estar disponible');
if (!seoBootstrap.includes("includes/class-seo-front-inspector.php")) throw new Error('Falta cargar el inspector del front');
if (!seoEditorial.includes("'Rank Math' => get_post_meta") || !seoEditorial.includes("'Yoast SEO' => get_post_meta")) throw new Error('El inventario de keywords debe reunir Digitalisimo, Rank Math y Yoast SEO');
if (!seoEditorial.includes('data-content-panel') || !seoEditorial.includes('data-content-tab')) throw new Error('Contenido debe cambiar sus secciones dentro de la misma pantalla');
if (!seoEditorial.includes("'locations' => 'Ubicaciones'") || !seoEditorial.includes('digitalisimo_create_location') || !seoEditorial.includes("post_type' => self::content_types()")) throw new Error('Ubicaciones y clusters deben admitir todo tipo de contenido público.');
if (!seoEditorial.includes('Digitalisimo_Integrations_Editorial::locations()') || !seoEditorialTools.includes("register_taxonomy( 'digitalisimo_seo_location'") || !seoEditorialTools.includes("wp_insert_term( $name, 'digitalisimo_seo_location'")) throw new Error('El catálogo de ubicaciones debe usar un registro jerárquico independiente del contenido editorial.');
if (!seoEditorial.includes('location_edit') || !seoEditorial.includes('digitalisimo_delete_location') || !seoEditorialTools.includes('wp_update_term') || !seoEditorialTools.includes('wp_delete_term')) throw new Error('Las ubicaciones deben poder editarse y eliminarse sin dejar asignaciones rotas.');
if (!seoEditorialTools.includes("array_reverse( self::current_location_parts(), true )")) throw new Error('El shortcode de ubicación debe iniciar en el nivel más específico.');
const seoSchemaGraph = readFileSync('digitalisimo-seo/includes/schema/class-schema-graph.php', 'utf8');
if (!seoEditorialTools.includes('location_schema') || !seoSchemaGraph.includes('Digitalisimo_Integrations_Editorial::location_schema') || !seoSchemaGraph.includes("$webpage['spatialCoverage'] = $page['spatial']") || !seoSchemaGraph.includes("'areaServed' => $page['spatial']")) throw new Error('La ubicación elegida debe emitirse como cobertura geográfica dentro del grafo único de Schema.');
if (seoSuite.includes('content_location_schema') || !seoSuite.includes('Digitalisimo_Integrations_Schema_Graph::head()')) throw new Error('El schema debe publicarse como un solo grafo, sin bloques JSON-LD paralelos.');
for (const file of ['class-schema-vocabulary.php', 'class-schema-rules.php', 'class-schema-graph.php', 'class-schema-audit.php']) if (!seoBootstrap.includes(`includes/schema/${file}`)) throw new Error(`Falta cargar ${file}`);
if (!seoSettings.includes("'seo_schema_unify'       => 1") || !seoSuite.includes("self::nf( 'seo_schema_unify'") || !seoSuite.includes("self::f( 'seo_schema_unify'") || !seoSuite.includes("'seo_alt_optimize', 'seo_schema_unify' )")) throw new Error('Unificar schema debe existir en sitio y red.');
if (!seoSettings.includes('Schema_Vocabulary::local_type') || !seoSuite.includes("'seo_local_type' === $key ) { $current[ $key ] = Digitalisimo_Integrations_Schema_Vocabulary::local_type")) throw new Error('El tipo de negocio sólo admite tipos oficiales en sitio y red.');
if (!seoSettings.includes("'default_content_location' => 0") || !seoEditorial.includes('location_default_content') || !seoEditorialTools.includes('related_location_id')) throw new Error('La ubicación predeterminada debe aplicarse al texto y Schema de contenidos sin ubicación propia.');
if (!seoEditorialTools.includes('content_location_label') || !seoEditorialTools.includes("term_exists( $id, 'digitalisimo_seo_location' )") || !seoEditorialTools.includes('predeterminada')) throw new Error('La columna Ubicación debe mostrar sólo la ubicación válida asignada o heredada del catálogo.');
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

console.log(`Suite validada: ${modules.length + 2} módulos, ${phpFiles.length} archivos PHP base, sin clases duplicadas no protegidas.`);
