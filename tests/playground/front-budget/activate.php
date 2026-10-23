<?php
/**
 * Primer paso del blueprint de front-budget.sh: instala la sonda (mu-plugin), prepara el estado previo que pide el
 * modo (`mode.json`) y activa los plugins. Los documentos se crean en el paso siguiente (`setup.php`), en otra
 * petición, con el constructor ya cargado.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

const DIGI_FB_PROBE = '/wordpress/wp-content/probe';

function digi_fb_fail( $message ) {
	file_put_contents( DIGI_FB_PROBE . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

$mode     = json_decode( (string) file_get_contents( DIGI_FB_PROBE . '/mode.json' ), true );
$mode     = is_array( $mode ) ? $mode : array();
$defaults = (string) ( $mode['defaults'] ?? '' );
$old      = '' !== $defaults;
$network  = is_multisite();

if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
copy( DIGI_FB_PROBE . '/probe.php', WPMU_PLUGIN_DIR . '/digi-fb-probe.php' );
file_put_contents( WPMU_PLUGIN_DIR . '/digi-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );' );

// DEFAULTS=old*: un sitio que se instaló con 3.16.0, sin font-display ni estado guardado del experimento de iconos
// (el núcleo deja entonces los iconos de fuente y `auto`; la activación no añade `swap` porque hay historial).
if ( $old ) {
	update_option( 'elementor_install_history', array( '3.16.0' => time() - YEAR_IN_SECONDS ) );
	delete_option( 'elementor_font_display' );
	delete_option( 'elementor_experiment-e_font_icon_svg' );
	if ( 'old-inactive' === $defaults ) {
		update_option( 'elementor_experiment-e_font_icon_svg', 'inactive' );
	}
}

// CORE=plugin: Elementor fijado (lo instaló el blueprint) manda y el núcleo incluido no se carga.
if ( 'plugin' === ( $mode['core'] ?? 'bundled' ) ) {
	if ( ! file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ) ) {
		digi_fb_fail( 'CORE=plugin: el blueprint no instaló el plugin Elementor' );
	} else {
		$result = activate_plugin( 'elementor/elementor.php', '', $network );
		if ( is_wp_error( $result ) ) {
			digi_fb_fail( 'No se pudo activar Elementor: ' . $result->get_error_message() );
		}
	}
}
$plugins = array( 'digitalisimo-elements/pro-elements.php', 'digi-fb-fake-addon/digi-fb-fake-addon.php' );
// WOO=1 (F4-Woo): WooCommerce montado (o instalado sin activar por el blueprint) se activa antes que el constructor.
if ( ! empty( $mode['woo'] ) ) {
	if ( ! file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		digi_fb_fail( 'WOO=1: WooCommerce no está montado ni instalado' );
	} else {
		array_unshift( $plugins, 'woocommerce/woocommerce.php' );
	}
}
if ( ! empty( $mode['seo'] ) ) {
	$plugins[] = 'digitalisimo-seo/digitalisimo-integrations.php';
}
if ( ! empty( $mode['migration'] ) ) {
	$plugins[] = 'digitalisimo-migration/digitalisimo-migration.php';
}
foreach ( $plugins as $plugin ) {
	$result = activate_plugin( $plugin, '', $network );
	if ( is_wp_error( $result ) ) {
		digi_fb_fail( 'No se pudo activar ' . $plugin . ': ' . $result->get_error_message() );
	}
}
if ( ! empty( $mode['theme'] ) ) {
	if ( file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
		if ( $network ) {
			$allowed                       = (array) get_site_option( 'allowedthemes', array() );
			$allowed['digitalisimo-theme'] = true;
			update_site_option( 'allowedthemes', $allowed );
		}
		switch_theme( 'digitalisimo-theme' );
	} else {
		digi_fb_fail( 'THEME=1: falta el tema digitalisimo-theme montado' );
	}
}

if ( $old ) {
	// La activación con historial no añade `swap`; si algo lo hubiera escrito, se anota y se borra.
	if ( false !== get_option( 'elementor_font_display', false ) ) {
		file_put_contents( DIGI_FB_PROBE . '/setup-notes.txt', "elementor_font_display existía tras activar: se borra\n", FILE_APPEND );
		delete_option( 'elementor_font_display' );
	}
	if ( 'old-inactive' !== $defaults ) {
		delete_option( 'elementor_experiment-e_font_icon_svg' );
	}
}
if ( 'off' === $defaults ) {
	update_option( 'digitalisimo_elements_front', array( 'pro_conditional' => false, 'font_swap' => false, 'svg_icons' => false, 'lazy_libs' => false, 'element_cache_off' => false, 'image_loading_off' => false, 'own' => array( 'pro_conditional', 'font_swap', 'svg_icons', 'lazy_libs', 'element_cache_off', 'image_loading_off' ) ), true );
}
if ( 'old-dirty' === $defaults ) {
	// La red pide los iconos en línea; el sitio los hereda y su CSS adicional usa una clase de Font Awesome.
	update_site_option( 'digitalisimo_elements_front_defaults', array( 'svg_icons' => true ) );
	wp_update_custom_css_post( '.fa-star{}' );
}
if ( ! empty( $mode['lazy'] ) ) {
	update_option( 'digitalisimo_elements_front', array( 'pro_conditional' => true, 'font_swap' => true, 'svg_icons' => false, 'lazy_libs' => true, 'element_cache_off' => false, 'image_loading_off' => false, 'own' => array( 'pro_conditional', 'font_swap', 'svg_icons', 'lazy_libs', 'element_cache_off', 'image_loading_off' ) ), true );
}
if ( ! empty( $mode['v4'] ) ) {
	update_option( 'elementor_experiment-e_atomic_elements', 'active' );
}
// CACHE=1: la caché de elementos del núcleo queda en su valor por defecto (activa, 24 h); si no, desactivada.
if ( ! empty( $mode['cache'] ) ) {
	delete_option( 'elementor_element_cache_ttl' );
} else {
	update_option( 'elementor_element_cache_ttl', 'disable' );
}
if ( ! empty( $mode['seo'] ) ) {
	$seo = (array) get_option( 'digitalisimo_integrations_options', array() );
	if ( 'guard' === $mode['seo'] ) {
		$seo['perf_font_guard_mode'] = 'auto';
		$seo['perf_safe_mode']       = 0;
		update_option( 'elementor_local_google_fonts', '1' );
	} else {
		$seo['perf_js_jquery_mode'] = 'defer';
	}
	update_option( 'digitalisimo_integrations_options', $seo );
}
if ( ! empty( $mode['woo'] ) ) {
	// Tienda abierta (sin «Próximamente») y sin el asistente: las páginas de la tienda se sirven a los visitantes.
	update_option( 'woocommerce_coming_soon', 'no' );
	update_option( 'woocommerce_store_pages_only', 'no' );
	update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );
	update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
	update_option( 'woocommerce_allow_tracking', 'no' );
}
update_option( 'elementor_onboarded', true );
update_option( 'users_can_register', 1 );
update_option( 'blogdescription', 'Medición del front' );
update_option( 'permalink_structure', '/%postname%/' );
