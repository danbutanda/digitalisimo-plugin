<?php
/**
 * Paso del blueprint de woo-flow.sh: instala la sonda (mu-plugin) y el registro de errores fatales, activa los plugins
 * (WooCommerce montado, el plugin Elementor fijado con CORE=plugin y DIGITALÍSIMO Elements), el tema y, con NET=1, crea
 * los sitios 2 y 3 de la red de subdirectorios. La tienda de cada sitio se prepara después, en una petición propia de
 * ese sitio (`?digi_wf=setup`, `store.php`), con WooCommerce ya instalado y cargado.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

const DIGI_WF_PROBE = '/wordpress/wp-content/probe';

function digi_wf_fail( $message ) {
	file_put_contents( DIGI_WF_PROBE . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

$mode    = json_decode( (string) file_get_contents( DIGI_WF_PROBE . '/mode.json' ), true );
$mode    = is_array( $mode ) ? $mode : array();
$network = is_multisite();

if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
copy( DIGI_WF_PROBE . '/probe.php', WPMU_PLUGIN_DIR . '/digi-wf-probe.php' );
file_put_contents( WPMU_PLUGIN_DIR . '/digi-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );' );

if ( ! file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
	digi_wf_fail( 'WooCommerce no está montado en wp-content/plugins/woocommerce' );
	return;
}
$plugins = array( 'woocommerce/woocommerce.php' );
if ( 'plugin' === ( $mode['core'] ?? 'bundled' ) ) {
	if ( ! file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ) ) {
		digi_wf_fail( 'CORE=plugin: el blueprint no instaló el plugin Elementor' );
	} else {
		$plugins[] = 'elementor/elementor.php';
	}
}
$plugins[] = 'digitalisimo-elements/pro-elements.php';
foreach ( $plugins as $plugin ) {
	$result = activate_plugin( $plugin, '', $network );
	if ( is_wp_error( $result ) ) {
		digi_wf_fail( 'No se pudo activar ' . $plugin . ': ' . $result->get_error_message() );
	}
}

// Tema: el DIGITALÍSIMO (clásico, el de los sitios reales) salvo THEME=block, que deja el de bloques de Playground.
$theme = (string) ( $mode['theme'] ?? 'digitalisimo' );
if ( 'digitalisimo' === $theme ) {
	if ( ! file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
		digi_wf_fail( 'Falta el tema digitalisimo-theme montado' );
	} elseif ( $network ) {
		$allowed                       = (array) get_site_option( 'allowedthemes', array() );
		$allowed['digitalisimo-theme'] = true;
		update_site_option( 'allowedthemes', $allowed );
	}
}

// NET=1: red de subdirectorios con los sitios /dos/ (2) y /tres/ (3); cada uno prepara su tienda en su petición.
if ( $network ) {
	foreach ( array( 'dos' => 'Sitio dos', 'tres' => 'Sitio tres' ) as $path => $title ) {
		if ( ! get_blog_id_from_url( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . $path . '/' ) ) {
			$id = wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . $path . '/', $title, 1 );
			if ( is_wp_error( $id ) ) {
				digi_wf_fail( 'No se pudo crear el sitio /' . $path . '/: ' . $id->get_error_message() );
			}
		}
	}
}
$blogs = $network ? get_sites( array( 'fields' => 'ids', 'number' => 10 ) ) : array( get_current_blog_id() );
foreach ( $blogs as $blog_id ) {
	if ( $network ) {
		switch_to_blog( (int) $blog_id );
	}
	if ( 'digitalisimo' === $theme && file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
		switch_theme( 'digitalisimo-theme' );
	}
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'elementor_onboarded', true );
	// CACHE=1: la caché de elementos del núcleo queda en su valor por defecto (activa, 24 h); si no, desactivada.
	if ( ! empty( $mode['cache'] ) ) {
		delete_option( 'elementor_element_cache_ttl' );
	} else {
		update_option( 'elementor_element_cache_ttl', 'disable' );
	}
	if ( $network ) {
		restore_current_blog();
	}
}
update_option( 'digi_wf_activated', time() );
