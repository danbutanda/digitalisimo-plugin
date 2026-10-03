<?php
/**
 * Plugin Name: Digitalisimo SEO
 * Description: SEO técnico, estrategia de contenidos y SEO AI para Digitalisimo.
 * Version: 1.0.218
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-seo
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Digitalísimo
 * Text Domain: digitalisimo-integrations
 */

defined( 'ABSPATH' ) || exit;

define( 'DIGITALISIMO_INTEGRATIONS_VERSION', '1.0.218' );
define( 'DIGITALISIMO_INTEGRATIONS_FILE', __FILE__ );
define( 'DIGITALISIMO_INTEGRATIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIGITALISIMO_INTEGRATIONS_URL', plugin_dir_url( __FILE__ ) );

require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-settings.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-keyword-match.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-digitalisimo-core.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-plugin-catalog.php';
Digitalisimo_Plugin_Catalog::init();
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-resolver.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-hide-login.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-suite.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/schema/class-schema-vocabulary.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/schema/class-schema-rules.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/schema/class-schema-graph.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/schema/class-schema-audit.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-sitemap.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-robots.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai-crawler-tools.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai-crawler-verifier.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai-audit-tools.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-front-inspector.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-audit-hub.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai-llms-tools.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai-indexnow-tools.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai-referral-tools.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai-entities-tools.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-ai-client.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-digitalisimo-updater.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-tools-update-bridge.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-seo-release-check.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-social-profiles.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-local-business.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-media-field.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-admin-ui.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-editorial.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-manager.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-css.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-css-audit.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-hero.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-fonts.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-font-guard.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-font-inline.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-preloads.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-images.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-javascript.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-image-alt.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-quality-rules.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-quality-audit.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-llms.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-favicon.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-migration.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-tracking.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-cache.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-console.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-debug.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-asset-diagnostics.php';
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/performance/class-performance-measurements.php';

// Debe decidirse antes de `plugins_loaded`: para entonces la ruta ya está resuelta.
if ( ! Digitalisimo_Integrations_Settings::tools_handles_site_options() ) Digitalisimo_Integrations_Hide_Login::boot();

function digitalisimo_integrations_boot() {
	Digitalisimo_Integrations_Settings::init();
	Digitalisimo_Integrations_Performance_Manager::init();
	Digitalisimo_Integrations_Performance_CSS::init();
	Digitalisimo_Integrations_Performance_CSS_Audit::init();
	Digitalisimo_Integrations_Performance_Hero::init();
	Digitalisimo_Integrations_Performance_Fonts::init();
	Digitalisimo_Integrations_Performance_Font_Guard::init();
	Digitalisimo_Integrations_Performance_Font_Inline::init();
	Digitalisimo_Integrations_Performance_Preloads::init();
	Digitalisimo_Integrations_Performance_Images::init();
	Digitalisimo_Integrations_Performance_JavaScript::init();
	Digitalisimo_Integrations_Quality_Audit::init();
	Digitalisimo_Integrations_LLMS::init();
	Digitalisimo_Integrations_Favicon::init();
	Digitalisimo_Integrations_Performance_Migration::init();
	Digitalisimo_Integrations_Performance_Tracking::init();
	Digitalisimo_Integrations_Performance_Cache::init();
	Digitalisimo_Integrations_Performance_Console::init();
	Digitalisimo_Integrations_Asset_Diagnostics::init();
	Digitalisimo_Integrations_Performance_Measurements::init();
	Digitalisimo_Integrations_SEO_Suite::init();
	Digitalisimo_Integrations_Schema_Graph::init();
	Digitalisimo_Integrations_Schema_Audit::init();
	Digitalisimo_Integrations_Sitemap::init();
	Digitalisimo_Integrations_Robots::init();
	// La política robots pertenece a SEO y funciona aunque AI y Chatbot no esté instalado.
	add_filter( 'robots_txt', array( 'Digitalisimo_Integrations_SEO_AI', 'robots' ), 70, 2 );
	Digitalisimo_Integrations_SEO_AI_Crawler_Tools::init();
	// SEO AI usa el motor y los proveedores del módulo AI y Chatbot.
	if ( class_exists( 'Digitalisimo_AI' ) ) {
		Digitalisimo_Integrations_SEO_AI::init();
		Digitalisimo_Integrations_SEO_AI_Crawler_Verifier::init();
		Digitalisimo_Integrations_SEO_AI_Audit_Tools::init();
		Digitalisimo_Integrations_SEO_AI_LLMS_Tools::init();
		Digitalisimo_Integrations_SEO_AI_IndexNow_Tools::init();
		Digitalisimo_Integrations_SEO_AI_Referral_Tools::init();
		Digitalisimo_Integrations_SEO_AI_Entities_Tools::init();
	}
	Digitalisimo_Integrations_Editorial::init();
	Digitalisimo_Updater::register( DIGITALISIMO_INTEGRATIONS_FILE, DIGITALISIMO_INTEGRATIONS_VERSION, 'digitalisimo-seo' );
	Digitalisimo_Tools_Update_Bridge::init();
	Digitalisimo_SEO_Release_Check::init();
	Digitalisimo_Media_Field::boot( DIGITALISIMO_INTEGRATIONS_URL, DIGITALISIMO_INTEGRATIONS_VERSION );
	Digitalisimo_Admin_UI::init();
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
