<?php
/**
 * Preparación del ensayo del Asistente IA (paso runPHP del blueprint de ia-assistant.sh): activa IA Tools y
 * DIGITALÍSIMO Elements (en una red, en toda la red, y crea el sitio 2), enciende el MCP de Elements en cada sitio,
 * configura un proveedor falso de OpenAI (la sonda contesta por él) y crea la contraseña de aplicación de la
 * administradora para hablar con los servidores MCP. Deja /digi-asis/ready.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$dir = '/digi-asis';
try {
	$network = is_multisite();
	foreach ( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php' ) as $plugin ) {
		$done = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $done ) ) {
			throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $done->get_error_message() );
		}
	}
	$ai = array(
		'providers' => array( 'openai' => array( 'enabled' => 1, 'key' => 'sk-asis-FAKEKEY-0123456789abcdefABCDEF', 'url' => '' ) ),
		'profiles'  => array( 'content' => array( 'provider' => 'openai', 'model' => 'gpt-asis-fake', 'fallback' => '' ) ),
	);
	$sites = array( get_current_blog_id() );
	if ( $network ) {
		update_site_option( 'digitalisimo_ai_options', $ai );
		$id = wpmu_create_blog( get_network()->domain, '/sitio2/', 'Sitio 2', 1 );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( 'No se creó el sitio 2: ' . $id->get_error_message() );
		}
		$sites[] = (int) $id;
	} else {
		update_option( 'digitalisimo_ai_options', $ai, false );
	}
	foreach ( $sites as $site ) {
		if ( $network ) {
			switch_to_blog( $site );
		}
		update_option( 'elementor_mcp_enabled', true );
		update_option( 'permalink_structure', '' );
		if ( $network ) {
			restore_current_blog();
		}
	}
	$admin   = get_user_by( 'login', 'admin' );
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-asis' ) );
	if ( is_wp_error( $created ) ) {
		throw new RuntimeException( 'Contraseña de aplicación: ' . $created->get_error_message() );
	}
	file_put_contents( $dir . '/secrets.json', wp_json_encode( array( 'admin' => $created[0] ) ) );
	file_put_contents( $dir . '/ready.json', wp_json_encode( array( 'multisite' => $network, 'sites' => $sites, 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'abilities' => function_exists( 'wp_register_ability' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
