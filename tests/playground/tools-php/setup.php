<?php
/**
 * Preparación de tools-php.sh: DIGITALÍSIMO Tools activo (en toda la red si es una red), enlaces permanentes, el plugin
 * falso que redirige la portada y, en un sitio individual, la ruta privada de acceso; en una red, los sitios 2 y 3.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$out = '/wordpress/wp-content/probe/';
try {
	$network = is_multisite();
	foreach ( array( 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-php-redirect/digitalisimo-php-redirect.php' ) as $plugin ) {
		$done = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $done ) ) throw new Exception( $plugin . ': ' . $done->get_error_message() );
	}
	update_option( 'permalink_structure', '/%postname%/' );
	$sites = array();
	if ( $network ) {
		$domain = get_network()->domain;
		foreach ( array( 2, 3 ) as $n ) {
			$id = wpmu_create_blog( $domain, '/site' . $n . '/', 'Sitio ' . $n, 1 );
			if ( is_wp_error( $id ) ) throw new Exception( $id->get_error_message() );
			switch_to_blog( $id );
			update_option( 'permalink_structure', '/%postname%/' );
			flush_rewrite_rules();
			restore_current_blog();
			$sites[ $n ] = (int) $id;
		}
	} else {
		update_option( 'digitalisimo_tools_site_options', array( 'values' => array( 'login_slug' => 'acceso-equipo' ), 'inherit' => array() ), true );
	}
	flush_rewrite_rules();
	file_put_contents( $out . 'ready.json', wp_json_encode( array( 'multisite' => $network, 'sites' => $sites, 'admin_email' => get_option( 'admin_email' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $out . 'setup-error.txt', $e->getMessage() );
}
