<?php
/**
 * Plugin Name: DIGITALÍSIMO SEO
 * Description: SEO técnico, estrategia de contenidos y SEO AI para Digitalisimo.
 * Version: 1.0.266
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-seo
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Digitalísimo
 * Text Domain: digitalisimo-integrations
 */

defined( 'ABSPATH' ) || exit;

define( 'DIGITALISIMO_INTEGRATIONS_VERSION', '1.0.266' );
define( 'DIGITALISIMO_INTEGRATIONS_FILE', __FILE__ );
define( 'DIGITALISIMO_INTEGRATIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIGITALISIMO_INTEGRATIONS_URL', plugin_dir_url( __FILE__ ) );

require_once DIGITALISIMO_INTEGRATIONS_DIR . 'digitalisimo-ui/digitalisimo-ui.php';
/**
 * Clases propias de SEO y su archivo. Ya no se incluyen las 70 en cada petición (1,3 MB de código: ~35 ms y 6,5 MB de
 * memoria sin OPcache, también en el front, el heartbeat y la REST): cada una se carga la primera vez que se usa. Lo
 * compartido con otros plugins (diseño, núcleo, catálogo y actualizador) se sigue incluyendo arriba, como en ellos.
 * Una clase nueva se añade aquí (`tests/seo-fixed-cost.php` comprueba que estén todas).
 */
function digitalisimo_integrations_classes() {
	return array(
		'Digitalisimo_Integrations_Settings'                 => 'includes/class-settings.php',
		'Digitalisimo_Integrations_Keyword_Match'            => 'includes/class-keyword-match.php',
		'Digitalisimo_Integrations_SEO_Resolver'             => 'includes/class-seo-resolver.php',
		'Digitalisimo_Integrations_Hide_Login'               => 'includes/class-hide-login.php',
		'Digitalisimo_Integrations_SEO'                      => 'includes/class-seo.php',
		'Digitalisimo_Integrations_SEO_Suite'                => 'includes/class-seo-suite.php',
		'Digitalisimo_Integrations_SEO_Variables'            => 'includes/class-seo-variables.php',
		'Digitalisimo_Integrations_Breadcrumbs'              => 'includes/class-breadcrumbs.php',
		'Digitalisimo_Integrations_Schema_Vocabulary'        => 'includes/schema/class-schema-vocabulary.php',
		'Digitalisimo_Integrations_Schema_Rules'             => 'includes/schema/class-schema-rules.php',
		'Digitalisimo_Integrations_Schema_Graph'             => 'includes/schema/class-schema-graph.php',
		'Digitalisimo_Integrations_Schema_Audit'             => 'includes/schema/class-schema-audit.php',
		'Digitalisimo_Integrations_Sitemap'                  => 'includes/class-sitemap.php',
		'Digitalisimo_Integrations_Redirections'             => 'includes/class-redirections.php',
		'Digitalisimo_Integrations_404_Monitor'              => 'includes/class-404-monitor.php',
		'Digitalisimo_Integrations_Index_Hygiene'            => 'includes/class-index-hygiene.php',
		'Digitalisimo_Integrations_Site_Analysis'            => 'includes/class-site-analysis.php',
		'Digitalisimo_Integrations_SEO_Cleanup'              => 'includes/class-seo-cleanup.php',
		'Digitalisimo_Integrations_Verification'             => 'includes/class-verification.php',
		'Digitalisimo_Integrations_RankMath_Import'          => 'includes/class-rankmath-import.php',
		'Digitalisimo_Integrations_Links'                    => 'includes/class-links.php',
		'Digitalisimo_Integrations_Content_Analysis'         => 'includes/class-content-analysis.php',
		'Digitalisimo_Integrations_SEO_Bulk'                 => 'includes/class-seo-bulk.php',
		'Digitalisimo_Integrations_SEO_Abilities'            => 'includes/class-seo-abilities.php',
		'Digitalisimo_Integrations_SEO_Abilities_Write'      => 'includes/class-seo-abilities-write.php',
		'Digitalisimo_Integrations_SEO_Feed'                 => 'includes/class-seo-feed.php',
		'Digitalisimo_Integrations_Video'                    => 'includes/class-video.php',
		'Digitalisimo_Integrations_Media_Repair'             => 'includes/class-media-repair.php',
		// Avisos con la X y el cierre recordado (copia idéntica en los siete plugins): se carga al pintar un aviso.
		'Digitalisimo_Notices'                               => 'includes/class-digitalisimo-notices.php',
		'Digitalisimo_Integrations_URL_Bases'                => 'includes/class-url-bases.php',
		'Digitalisimo_Integrations_Product_Identifiers'      => 'includes/class-product-identifiers.php',
		'Digitalisimo_Integrations_Robots'                   => 'includes/class-robots.php',
		'Digitalisimo_Integrations_SEO_AI'                   => 'includes/class-seo-ai.php',
		'Digitalisimo_Integrations_SEO_AI_Crawler_Tools'     => 'includes/class-seo-ai-crawler-tools.php',
		'Digitalisimo_Integrations_SEO_AI_Crawler_Verifier'  => 'includes/class-seo-ai-crawler-verifier.php',
		'Digitalisimo_Integrations_SEO_AI_Audit_Tools'       => 'includes/class-seo-ai-audit-tools.php',
		'Digitalisimo_Integrations_SEO_Front_Inspector'      => 'includes/class-seo-front-inspector.php',
		'Digitalisimo_Integrations_SEO_Audit_Hub'            => 'includes/class-seo-audit-hub.php',
		'Digitalisimo_Integrations_SEO_AI_LLMS_Tools'        => 'includes/class-seo-ai-llms-tools.php',
		'Digitalisimo_Integrations_SEO_AI_IndexNow_Tools'    => 'includes/class-seo-ai-indexnow-tools.php',
		'Digitalisimo_Integrations_SEO_AI_Referral_Tools'    => 'includes/class-seo-ai-referral-tools.php',
		'Digitalisimo_Integrations_SEO_AI_Entities_Tools'    => 'includes/class-seo-ai-entities-tools.php',
		'Digitalisimo_SEO_AI_Client'                         => 'includes/class-seo-ai-client.php',
		'Digitalisimo_Tools_Update_Bridge'                   => 'includes/class-tools-update-bridge.php',
		'Digitalisimo_SEO_Release_Check'                     => 'includes/class-seo-release-check.php',
		'Digitalisimo_Integrations_Social_Profiles'          => 'includes/class-social-profiles.php',
		'Digitalisimo_Integrations_Local_Business'           => 'includes/class-local-business.php',
		'Digitalisimo_Integrations_Brand_Check'              => 'includes/class-brand-check.php',
		'Digitalisimo_Media_Field'                           => 'includes/class-media-field.php',
		'Digitalisimo_Admin_UI'                              => 'includes/class-admin-ui.php',
		'Digitalisimo_Integrations_Editorial'                => 'includes/class-editorial.php',
		'Digitalisimo_Integrations_Performance_Manager'      => 'includes/performance/class-performance-manager.php',
		'Digitalisimo_Integrations_Performance_CSS'          => 'includes/performance/class-performance-css.php',
		'Digitalisimo_Integrations_Performance_CSS_Audit'    => 'includes/performance/class-performance-css-audit.php',
		'Digitalisimo_Integrations_Performance_Hero'         => 'includes/performance/class-performance-hero.php',
		'Digitalisimo_Integrations_Performance_Fonts'        => 'includes/performance/class-performance-fonts.php',
		'Digitalisimo_Integrations_Performance_Font_Guard'   => 'includes/performance/class-performance-font-guard.php',
		'Digitalisimo_Integrations_Performance_Font_Inline'  => 'includes/performance/class-performance-font-inline.php',
		'Digitalisimo_Integrations_Performance_Preloads'     => 'includes/performance/class-performance-preloads.php',
		'Digitalisimo_Integrations_Performance_Images'       => 'includes/performance/class-performance-images.php',
		'Digitalisimo_Integrations_Performance_JavaScript'   => 'includes/performance/class-performance-javascript.php',
		'Digitalisimo_Integrations_Image_Alt'                => 'includes/performance/class-image-alt.php',
		'Digitalisimo_Integrations_Quality_Rules'            => 'includes/performance/class-quality-rules.php',
		'Digitalisimo_Integrations_Quality_Audit'            => 'includes/performance/class-quality-audit.php',
		'Digitalisimo_Integrations_LLMS'                     => 'includes/class-llms.php',
		'Digitalisimo_Integrations_Favicon'                  => 'includes/class-favicon.php',
		'Digitalisimo_Integrations_Performance_Migration'    => 'includes/performance/class-performance-migration.php',
		'Digitalisimo_Integrations_Performance_Tracking'     => 'includes/performance/class-performance-tracking.php',
		'Digitalisimo_Integrations_Performance_Cache'        => 'includes/performance/class-performance-cache.php',
		'Digitalisimo_Integrations_Performance_Console'      => 'includes/performance/class-performance-console.php',
		'Digitalisimo_Integrations_Performance_Debug'        => 'includes/performance/class-performance-debug.php',
		'Digitalisimo_Integrations_Asset_Diagnostics'        => 'includes/performance/class-asset-diagnostics.php',
		'Digitalisimo_Integrations_Performance_Measurements' => 'includes/performance/class-performance-measurements.php',
	);
}

function digitalisimo_integrations_autoload( $class ) {
	static $classes = null;
	if ( 0 !== strpos( $class, 'Digitalisimo_' ) ) return;
	if ( null === $classes ) $classes = digitalisimo_integrations_classes();
	if ( isset( $classes[ $class ] ) && is_readable( DIGITALISIMO_INTEGRATIONS_DIR . $classes[ $class ] ) ) require_once DIGITALISIMO_INTEGRATIONS_DIR . $classes[ $class ];
}
spl_autoload_register( 'digitalisimo_integrations_autoload' );
// La X de los avisos (Digitalisimo_Notices): el cierre por AJAX carga la clase sólo cuando llega (mismo callback que el
// de las copias de los demás plugins: WordPress no lo registra dos veces).
if ( is_admin() ) add_action( 'wp_ajax_digitalisimo_notice_dismiss', array( 'Digitalisimo_Notices', 'ajax' ) );

/**
 * Antes de que WordPress mueva o sustituya los archivos de un plugin (actualizar, reinstalar, subir un ZIP encima), SEO
 * compila todas sus clases, como hacía antes del autoloader: lo que se dispare después en esa petición (opciones, cron,
 * `upgrader_process_complete`, `shutdown` antes de que WordPress restaure la copia si la actualización falla) no necesita
 * sus archivos en disco ni mezcla clases de dos versiones.
 */
function digitalisimo_integrations_load_all( $response = true ) {
	foreach ( array_keys( digitalisimo_integrations_classes() ) as $class ) class_exists( $class );
	return $response;
}
add_filter( 'upgrader_pre_install', 'digitalisimo_integrations_load_all', 1 );

/**
 * Registra los ganchos de un módulo sin cargar su clase: los mismos que su init() (nombre, método, prioridad, argumentos
 * y condiciones de escritorio/front y sitio/red, en `includes/lazy-hooks.php`, generado de init() con
 * `php tests/seo-fixed-cost.php --write`). La clase se carga cuando WordPress dispara uno de sus ganchos: el importador
 * de Rank Math, las auditorías o el schema del front ya no se compilan en cada petición. Sin entrada, llama a init().
 */
function digitalisimo_integrations_lazy( $class ) {
	static $hooks = null;
	if ( null === $hooks ) $hooks = (array) require DIGITALISIMO_INTEGRATIONS_DIR . 'includes/lazy-hooks.php';
	if ( ! isset( $hooks[ $class ] ) ) { call_user_func( array( $class, 'init' ) ); return; }
	if ( isset( $hooks[ $class ]['requires'] ) && ! function_exists( $hooks[ $class ]['requires'] ) ) return;
	$admin = is_admin() ? 'a' : 'f';
	$net   = is_multisite() ? 'm' : 's';
	foreach ( $hooks[ $class ]['hooks'] as $hook ) {
		list( $kind, $name, $method, $priority, $args, $when ) = $hook;
		if ( ( '*' !== $when[0] && $admin !== $when[0] ) || ( '*' !== $when[1] && $net !== $when[1] ) ) continue;
		if ( 'a' === $kind ) add_action( $name, array( $class, $method ), $priority, $args );
		elseif ( 'f' === $kind ) add_filter( $name, array( $class, $method ), $priority, $args );
		else add_shortcode( $name, array( $class, $method ) );
	}
}

require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-digitalisimo-core.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-plugin-catalog.php';
Digitalisimo_Plugin_Catalog::init();
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-digitalisimo-updater.php';

// Antes de la primera lectura (la de abajo ya resuelve opciones): las opciones pequeñas de cada petición del
// escritorio en una sola consulta y, en una red, los ajustes de red y su marca de migración.
if ( is_admin() && function_exists( 'wp_prime_option_caches' ) ) wp_prime_option_caches( digitalisimo_integrations_admin_options() );
if ( is_admin() && is_multisite() && function_exists( 'wp_prime_site_option_caches' ) ) wp_prime_site_option_caches( array( 'digitalisimo_integrations_options', 'digitalisimo_seo_template_defaults_1_0_122', 'digitalisimo_seo_video_network_reset', 'digitalisimo_seo_network_cleanup_2', 'digitalisimo_seo_network_removed' ) );

// Debe decidirse antes de `plugins_loaded`: para entonces la ruta ya está resuelta.
if ( ! Digitalisimo_Integrations_Settings::tools_handles_site_options() ) Digitalisimo_Integrations_Hide_Login::boot();

/**
 * Opciones sin autoload que SEO lee en cada petición del escritorio (también el heartbeat y admin-ajax): marcas de
 * migración y de versión de tablas, estados y manifiestos. Sin caché de objetos persistente eran una consulta cada una
 * (unas diez por petición); se piden juntas en una sola. Sólo lee: no cambia valores ni autoload.
 */
function digitalisimo_integrations_admin_options() {
	return array( 'digitalisimo_integrations_options', 'digitalisimo_seo_template_defaults_1_0_122', 'digitalisimo_seo_redirections_db', 'digitalisimo_seo_404_db', 'digitalisimo_seo_hygiene_state', 'digitalisimo_seo_analysis_state', 'digitalisimo_seo_links_db', 'digitalisimo_seo_links_done', 'digitalisimo_seo_url_bases_state', 'digitalisimo_performance_font_inline_manifest', 'digitalisimo_performance_preload_manifest', 'digitalisimo_performance_hero_bootstrapped', 'digitalisimo_favicon_state', 'digitalisimo_image_roles', 'digitalisimo_seo_network_inherit', 'digitalisimo_module_network_inherit' );
}

function digitalisimo_integrations_boot() {
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Settings' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Manager' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_CSS' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_CSS_Audit' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Hero' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Fonts' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Font_Guard' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Font_Inline' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Preloads' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Images' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Media_Repair' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_JavaScript' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Quality_Audit' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_LLMS' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Favicon' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Migration' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Tracking' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Cache' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Console' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Asset_Diagnostics' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Performance_Measurements' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_Suite' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Schema_Graph' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Schema_Audit' );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Sitemap' );
	// Lo que SEO incorpora de Rank Math (docs/SEO_RANKMATH_OBJECTIVE.md): sólo con el SEO activo.
	if ( Digitalisimo_Integrations_Settings::get( 'enable_seo' ) ) {
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Redirections' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_404_Monitor' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Index_Hygiene' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Verification' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_RankMath_Import' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Links' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Content_Analysis' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_Bulk' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_Feed' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Video' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_URL_Bases' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Product_Identifiers' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_Abilities' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_Abilities_Write' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Brand_Check' );
		// Tanda B y C de la auditoría SEO: análisis del sitio en segundo plano y sus acciones con confirmación.
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Site_Analysis' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_Cleanup' );
	} elseif ( is_multisite() ) {
		// La importación de red (Red → SEO → Importar) no depende del SEO propio del sitio principal, que es el sitio en curso
		// de admin-post.php: sin esto, con el principal apagado, el botón de la red no tenía quien lo recibiera.
		add_action( 'admin_post_digitalisimo_seo_rankmath_import_network', array( 'Digitalisimo_Integrations_RankMath_Import', 'handle_network' ) );
	}
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Robots' );
	// La política robots pertenece a SEO y funciona aunque AI y Chatbot no esté instalado.
	add_filter( 'robots_txt', array( 'Digitalisimo_Integrations_SEO_AI', 'robots' ), 70, 2 );
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_AI_Crawler_Tools' );
	// SEO AI usa el motor y los proveedores del módulo AI y Chatbot.
	if ( class_exists( 'Digitalisimo_AI' ) ) {
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_AI' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_AI_Crawler_Verifier' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_AI_Audit_Tools' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_AI_LLMS_Tools' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_AI_IndexNow_Tools' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_AI_Referral_Tools' );
		digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_SEO_AI_Entities_Tools' );
	}
	digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Editorial' );
	Digitalisimo_Updater::register( DIGITALISIMO_INTEGRATIONS_FILE, DIGITALISIMO_INTEGRATIONS_VERSION, 'digitalisimo-seo' );
	// El puente sólo atiende a Tools anterior a 1.0.17. Tools activo en su carpeta dice su versión con su constante: así no
	// se lee su cabecera (get_file_data) ni se carga el puente en cada petición.
	$tools_active = defined( 'DIGITALISIMO_TOOLS_VERSION' ) && defined( 'DIGITALISIMO_TOOLS_FILE' ) && wp_normalize_path( (string) DIGITALISIMO_TOOLS_FILE ) === wp_normalize_path( WP_PLUGIN_DIR . '/digitalisimo-tools/digitalisimo-tools.php' );
	if ( ! $tools_active || version_compare( (string) DIGITALISIMO_TOOLS_VERSION, '1.0.17', '<' ) ) Digitalisimo_Tools_Update_Bridge::init();
	digitalisimo_integrations_lazy( 'Digitalisimo_SEO_Release_Check' );
	Digitalisimo_Media_Field::boot( DIGITALISIMO_INTEGRATIONS_URL, DIGITALISIMO_INTEGRATIONS_VERSION );
	digitalisimo_integrations_lazy( 'Digitalisimo_Admin_UI' );
	Digitalisimo_Core::boot( 'seo', 'SEO', null, array( 'Digitalisimo_Integrations_SEO_Suite', 'network_page' ) );
}
add_action( 'plugins_loaded', 'digitalisimo_integrations_boot' );

function digitalisimo_integrations_activate( $network_wide ) {
	add_option( 'digitalisimo_integrations_options', Digitalisimo_Integrations_Settings::defaults() );
	if ( $network_wide && is_multisite() ) {
		foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { switch_to_blog( (int) $site->blog_id ); Digitalisimo_Integrations_SEO_AI::refresh_rewrites(); restore_current_blog(); }
	} else Digitalisimo_Integrations_SEO_AI::refresh_rewrites();
}
register_activation_hook( __FILE__, 'digitalisimo_integrations_activate' );
