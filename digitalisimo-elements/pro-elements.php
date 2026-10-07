<?php
/**
 * Plugin Name: DIGITALÍSIMO Elements
 * Description: Funciones avanzadas para Elementor gratuito. Versión derivada de PRO Elements 4.3.0.
 * Plugin URI: https://github.com/danbutanda/digitalisimo-plugin
 * Version: 4.3.0.27
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-elements
 * Author: DIGITALÍSIMO
 * Author URI: https://digitalisimo.mx/
 * Requires PHP: 7.4
 * Requires at least: 6.8
 * Requires Plugins: elementor
 * Elementor tested up to: 4.3.0
 * Text Domain: pro-elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.27' );
define( 'DIGITALISIMO_ELEMENTS_FILE', __FILE__ );
require_once __DIR__ . '/digitalisimo-compat.php';
require_once __DIR__ . '/digitalisimo-updater.php';
require_once __DIR__ . '/digitalisimo-brand.php';
Digitalisimo_Elements_Updater::init();
Digitalisimo_Elements_Brand::init();


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

	$plugin = 'elementor/elementor.php';

	if ( _is_elementor_installed() ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );

		$message = '<h3>' . esc_html__( 'DIGITALÍSIMO Elements requiere Elementor gratuito.', 'elementor-pro' ) . '</h3>';
		$message .= '<p>' . esc_html__( 'Activa Elementor para usar sus funciones avanzadas.', 'elementor-pro' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $activation_url, esc_html__( 'Activate Now', 'elementor-pro' ) ) . '</p>';
	} else {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );

		$message = '<h3>' . esc_html__( 'DIGITALÍSIMO Elements requiere Elementor gratuito.', 'elementor-pro' ) . '</h3>';
		$message .= '<p>' . esc_html__( 'Instala y activa Elementor para usar sus funciones avanzadas.', 'elementor-pro' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, esc_html__( 'Install Now', 'elementor-pro' ) ) . '</p>';
	}

	pro_elements_print_error($message);
}

function pro_elements_plugin_fail_load_out_of_date() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$file_path = 'elementor/elementor.php';

	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );
	$message = sprintf(
	/* translators: 1: Title opening tag, 2: Title closing tag */
		esc_html__( '%1$sDIGITALÍSIMO Elements requiere Elementor 4.0 o posterior.%2$s Actualiza Elementor para reactivar este plugin.', 'elementor-pro' ),
		'<h3>',
		'</h3>'
	);
	$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $upgrade_link, esc_html__( 'Update Now', 'elementor-pro' ) ) . '</p>';

	pro_elements_print_error($message);
}

function pro_elements_admin_notice_upgrade_recommendation() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$file_path = 'elementor/elementor.php';

	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );
	$message = sprintf(
	/* translators: 1: Title opening tag, 2: Title closing tag */
		esc_html__( '%1$sDon’t miss out on the new version of Elementor%2$s Update to the latest version of Elementor to enjoy new features, better performance and compatibility.', 'elementor-pro' ),
		'<h3>',
		'</h3>'
	);
	$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $upgrade_link, esc_html__( 'Update Now', 'elementor-pro' ) ) . '</p>';

	pro_elements_print_error($message);
}

if ( ! function_exists( '_is_elementor_installed' ) ) {

	function _is_elementor_installed() {
		$file_path = 'elementor/elementor.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $file_path ] );
	}
}
