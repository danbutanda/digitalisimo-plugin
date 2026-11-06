<?php
/**
 * Preparación del ensayo de cobertura de DIGITALÍSIMO Backups (paso runPHP del blueprint de backups-coverage.sh).
 * Activa el plugin (en toda la red si es Multisite), crea una editora sin la capability, en la red un administrador de
 * sitio que no administra la red y el subsitio «uno», y una contraseña de aplicación para cada cuenta. Deja todo en
 * /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$dir = '/digi-probe';
try {
	$network = is_multisite();
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', $network );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	$users = array( 'admin' => $admin->ID );
	$users['editora'] = wp_insert_user( array( 'user_login' => 'editora', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'editora@example.test', 'role' => 'editor' ) );
	if ( $network ) {
		// Administra el sitio principal, pero no la red: no puede gestionar respaldos de toda la red.
		$users['sitio'] = wp_insert_user( array( 'user_login' => 'sitio', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'sitio@example.test', 'role' => 'administrator' ) );
		$network_row = get_network();
		$site = wp_insert_site( array( 'domain' => $network_row->domain, 'path' => $network_row->path . 'uno/', 'title' => 'Uno', 'user_id' => $admin->ID ) );
		if ( is_wp_error( $site ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $site->get_error_message() );
		add_user_to_blog( $site, $users['sitio'], 'administrator' );
	}
	$passwords = array();
	foreach ( $users as $login => $id ) {
		if ( is_wp_error( $id ) ) throw new RuntimeException( 'No se creó ' . $login . ': ' . $id->get_error_message() );
		$created = WP_Application_Passwords::create_new_application_password( $id, array( 'name' => 'digi-coverage' ) );
		if ( is_wp_error( $created ) ) throw new RuntimeException( 'Sin contraseña de aplicación para ' . $login . ': ' . $created->get_error_message() );
		$passwords[ $login ] = $created[0];
	}
	file_put_contents( $dir . '/setup.json', wp_json_encode( array(
		'network' => $network,
		'passwords' => $passwords,
		'application_passwords' => wp_is_application_passwords_available(),
		'blogname' => get_option( 'blogname' ),
		'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION . ( defined( 'DB_ENGINE' ) ? ' · ' . DB_ENGINE : '' ),
	) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
