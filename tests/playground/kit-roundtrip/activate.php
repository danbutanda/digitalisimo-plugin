<?php
/**
 * Primer paso de cada sitio de kit-roundtrip.sh: instala los mu-plugins del arnés (mu-kit.php y net-sim.php) y activa
 * los plugins. Los documentos se crean en el paso siguiente (setup-a.php o setup-b.php), en otra petición, con el
 * constructor ya cargado.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once __DIR__ . '/lib.php';

$digi_opts = digi_k_options();
digi_k_install_mu( (string) ( $digi_opts['site'] ?? 'a' ) );

$network = is_multisite();
// CORE=plugin: el plugin Elementor de wordpress.org (lo instaló el blueprint) manda y el núcleo incluido no se carga.
if ( 'plugin' === ( $digi_opts['core'] ?? 'bundled' ) ) {
	if ( ! file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ) ) {
		digi_k_fail( 'CORE=plugin: el blueprint no instaló el plugin Elementor' );
	} else {
		activate_plugin( 'elementor/elementor.php', '', $network );
	}
}
$result = activate_plugin( 'digitalisimo-elements/pro-elements.php', '', $network );
if ( is_wp_error( $result ) ) {
	digi_k_fail( 'No se pudo activar DIGITALÍSIMO Elements: ' . $result->get_error_message() );
}
if ( ! empty( $digi_opts['woo'] ) ) {
	if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		activate_plugin( 'woocommerce/woocommerce.php', '', $network );
	} else {
		digi_k_fail( 'WOO=1: el blueprint no instaló WooCommerce' );
	}
}
// El tema DIGITALÍSIMO (clásico, cede cabecera y pie al Theme Builder) en A, B y la red: un tema de bloques pinta su
// propia navegación de reserva con las páginas del sitio y no imprime las ubicaciones del Theme Builder, así que la
// comparación de capturas mediría el tema y no el kit. Se activa en la preparación, antes de cualquier instantánea.
if ( file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
	if ( $network ) {
		$allowed = (array) get_site_option( 'allowedthemes', array() );
		$allowed['digitalisimo-theme'] = true;
		update_site_option( 'allowedthemes', $allowed );
	}
	switch_theme( 'digitalisimo-theme' );
} else {
	digi_k_fail( 'Falta el tema digitalisimo-theme montado en wp-content/themes' );
}
update_option( 'elementor_element_cache_ttl', 'disable' );
update_option( 'elementor_onboarded', true );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'uploads_use_yearmonth_folders', 0 );
update_option( 'WPLANG', '' );
