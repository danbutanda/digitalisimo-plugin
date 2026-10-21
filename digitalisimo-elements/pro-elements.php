<?php
/**
 * Plugin Name: DIGITALÍSIMO Elements
 * Description: Constructor visual completo de DIGITALÍSIMO: editor, funciones avanzadas, kits, plantillas y widgets propios en un solo plugin, compatible con el contenido, los kits y los complementos ya creados para el constructor.
 * Plugin URI: https://github.com/danbutanda/digitalisimo-plugin
 * Version: 4.3.0.163
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-elements
 * Author: DIGITALÍSIMO
 * Author URI: https://digitalisimo.mx/
 * Requires PHP: 7.4
 * Requires at least: 6.8
 * Elementor tested up to: 4.3.5
 * Text Domain: pro-elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.163' );
define( 'DIGITALISIMO_ELEMENTS_FILE', __FILE__ );
// Núcleo de Elementor incluido: se carga antes que todo lo demás y sólo si el plugin Elementor no está activo.
require_once __DIR__ . '/digitalisimo-core.php';
Digitalisimo_Elements_Core::load();
register_activation_hook( __FILE__, array( 'Digitalisimo_Elements_Core', 'activate' ) );
add_action( 'admin_notices', array( 'Digitalisimo_Elements_Core', 'external_notice' ) );
add_action( 'network_admin_notices', array( 'Digitalisimo_Elements_Core', 'external_notice' ) );
add_action( 'admin_notices', array( 'Digitalisimo_Elements_Core', 'duplicate_copies_notice' ) );
add_action( 'network_admin_notices', array( 'Digitalisimo_Elements_Core', 'duplicate_copies_notice' ) );
require_once __DIR__ . '/digitalisimo-ui/digitalisimo-ui.php';
require_once __DIR__ . '/digitalisimo-compat.php';
require_once __DIR__ . '/digitalisimo-updater.php';
require_once __DIR__ . '/digitalisimo-sibling-updates.php';
require_once __DIR__ . '/digitalisimo-brand.php';
require_once __DIR__ . '/digitalisimo-identity.php';
require_once __DIR__ . '/digitalisimo-vendor-free.php';
require_once __DIR__ . '/digitalisimo-duplicator.php';
require_once __DIR__ . '/modules/digitalisimo-widgets/class-google-reviews-service.php';
Digitalisimo_Elements_Updater::init();
// Los plugins Digitalisimo instalados pero inactivos (Migración antes de usarse) también se actualizan.
Digitalisimo_Elements_Sibling_Updates::init();
Digitalisimo_Elements_Brand::init();
Digitalisimo_Elements_Identity::init();
Digitalisimo_Elements_Vendor_Free::init();
Digitalisimo_Elements_Duplicator::init();
\Digitalisimo\Elements\Google_Reviews_Service::init();


function pro_elements_plugin_load_plugin() {
	if ( digitalisimo_elements_conflicting_plugin_active() ) {
		add_action( 'admin_notices', 'digitalisimo_elements_conflict_notice' );
		add_action( 'network_admin_notices', 'digitalisimo_elements_conflict_notice' );
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'pro_elements_plugin_fail_load' );

		return;
	}
	if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
		add_action( 'admin_notices', 'digitalisimo_elements_conflict_notice' );
		add_action( 'network_admin_notices', 'digitalisimo_elements_conflict_notice' );
		return;
	}

	define( 'ELEMENTOR_PRO_VERSION', '4.3.0' );

/**
 * All versions should be `major.minor`, without patch, in order to compare them properly.
 * Therefore, we can't set a patch version as a requirement.
 * (e.g. Core 3.14.0-beta1 and Core 3.14.0-cloud2 should be fine when requiring 3.14, while
 * requiring 3.14.2 is not allowed)
 */
define( 'ELEMENTOR_PRO_REQUIRED_CORE_VERSION', '4.0' );
define( 'ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION', '4.3' );

define( 'ELEMENTOR_PRO__FILE__', __FILE__ );
define( 'ELEMENTOR_PRO_PLUGIN_BASE', plugin_basename( ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_PATH', plugin_dir_path( ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_ASSETS_PATH', ELEMENTOR_PRO_PATH . 'assets/' );
define( 'ELEMENTOR_PRO_MODULES_PATH', ELEMENTOR_PRO_PATH . 'modules/' );
define( 'ELEMENTOR_PRO_URL', plugins_url( '/', ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_ASSETS_URL', ELEMENTOR_PRO_URL . 'assets/' );
define( 'ELEMENTOR_PRO_MODULES_URL', ELEMENTOR_PRO_URL . 'modules/' );
	define( 'IS_PRO_ELEMENTS', 'true' );
	pro_elements_load_plugin_func();
}



/**
 * Load gettext translate for our text domain.
 *
 * @since 1.0.0
 *
 * @return void
 */
function pro_elements_load_plugin_func() {
	$core_version = ELEMENTOR_VERSION;
	$core_version_required = ELEMENTOR_PRO_REQUIRED_CORE_VERSION;
	$core_version_recommended = ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION;

	if ( ! pro_elements_compare_major_version( $core_version, $core_version_required, '>=' ) ) {
		add_action( 'admin_notices', 'pro_elements_plugin_fail_load_out_of_date' );

		return;
	}

	if ( ! pro_elements_compare_major_version( $core_version, $core_version_recommended, '>=' ) ) {
		add_action( 'admin_notices', 'pro_elements_admin_notice_upgrade_recommendation' );
	}

	require ELEMENTOR_PRO_PATH . 'plugin.php';
	require_once __DIR__ . '/modules/digitalisimo-slider/class-module.php';
	\Digitalisimo\Elements\Elementor_Slider::init();
	require_once __DIR__ . '/modules/digitalisimo-widgets/class-registry.php';
	\Digitalisimo\Elements\Widget_Registry::init();
	require_once __DIR__ . '/modules/digitalisimo-conditions/class-module.php';
	\Digitalisimo\Elements\Display_Conditions::init();
	require_once __DIR__ . '/modules/digitalisimo-theme/class-module.php';
	\Digitalisimo\Elements\Theme_Compat::init();
	require_once __DIR__ . '/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::init();
	// Rendimiento del front (F-front): antes de `init` 0, donde el núcleo crea los experimentos.
	require_once __DIR__ . '/modules/digitalisimo-front/class-module.php';
	\Digitalisimo\Elements\Front\Module::init();
}

function pro_elements_compare_major_version( $left, $right, $operator ) {
	$pattern = '/^(\d+\.\d+).*/';
	$replace = '$1.0';

	$left  = preg_replace( $pattern, $replace, $left );
	$right = preg_replace( $pattern, $replace, $right );

	return version_compare( $left, $right, $operator );
}

// En Multisite este archivo puede cargarse antes que Elementor aunque ambos estén activos.
// Esperar a que WordPress termine de cargar todos los plugins evita un aviso falso.
add_action( 'plugins_loaded', 'pro_elements_plugin_load_plugin', 10 );

function digitalisimo_elements_conflict_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) return;
	echo '<div class="notice notice-error"><p>DIGITALÍSIMO Elements no se cargó porque Elementor Pro u otra versión derivada ya está activa. Deja activo sólo uno de estos plugins.</p></div>';
}

function pro_elements_print_error( $message ) {
	if ( ! $message ) {
		return;
	}
	// PHPCS - $message should not be escaped
	echo '<div class="error">' . $message . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
/**
 * Show in WP Dashboard notice about the plugin is not activated.
 *
 * @since 1.0.0
 *
 * @return void
 */
function pro_elements_plugin_fail_load() {
	$screen = get_current_screen();
	if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
		return;
	}
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	// El constructor viene incluido: nunca se pide instalar ni activar otro plugin.
	$message  = '<h3>' . esc_html__( 'No se pudo cargar el constructor incluido en DIGITALÍSIMO Elements.', 'elementor-pro' ) . '</h3>';
	$message .= '<p>' . esc_html__( 'Falta o no se puede leer su carpeta elementor/. Vuelve a instalar DIGITALÍSIMO Elements desde su ZIP.', 'elementor-pro' ) . '</p>';
	pro_elements_print_error( $message );
}

/** Un plugin Elementor externo anterior a 4.0 sigue activo y manda: basta desactivarlo. */
function pro_elements_external_builder_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$message  = '<h3>' . esc_html__( 'Hay otro constructor activo que reemplaza al incluido en DIGITALÍSIMO Elements.', 'elementor-pro' ) . '</h3>';
	$message .= '<p>' . esc_html__( 'Desactívalo en Plugins: DIGITALÍSIMO Elements trae su propio constructor y lo usa en cuanto el otro deja de estar activo.', 'elementor-pro' ) . '</p>';
	$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', esc_url( self_admin_url( 'plugins.php' ) ), esc_html__( 'Ir a Plugins', 'elementor-pro' ) ) . '</p>';
	pro_elements_print_error( $message );
}

function pro_elements_plugin_fail_load_out_of_date() {
	pro_elements_external_builder_notice();
}

/** Con un Elementor externo más viejo que el recomendado no se pide actualizarlo: la pantalla Plugins ya explica que sobra. */
function pro_elements_admin_notice_upgrade_recommendation() {
}

if ( ! function_exists( '_is_elementor_installed' ) ) {

	function _is_elementor_installed() {
		$file_path = 'elementor/elementor.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $file_path ] );
	}
}
