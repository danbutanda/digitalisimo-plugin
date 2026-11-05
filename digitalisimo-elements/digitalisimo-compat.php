<?php
/** Evita cargar dos implementaciones de los identificadores internos ElementorPro. */
defined( 'ABSPATH' ) || exit;

function digitalisimo_elements_conflicting_plugin_active() {
	$conflicts = array( 'elementor-pro/elementor-pro.php', 'pro-elements/pro-elements.php' );
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
		$active = array_merge( $active, array_keys( $network_active ) );
	}
	return (bool) array_intersect( $conflicts, $active );
}
