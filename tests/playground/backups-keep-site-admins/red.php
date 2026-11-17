<?php
/**
 * Preparación del ensayo de red de «los administradores de este sitio siguen entrando» (último paso runPHP del blueprint
 * de backups-keep-site-admins-red.sh, ya con la red activa). Según /digi-probe/site.txt:
 *   a   la red de origen (prefijo wp_): su superadministrador «dueno» (el usuario 1 de Playground, con otro nombre y otra
 *       contraseña, con una contraseña de aplicación para crear el respaldo por REST), una entrada y el subsitio «uno».
 *   b   la red de destino (prefijo dst_, instalada por backups-relocate/prefix.php): los superadministradores «admin»
 *       (quien restaura, con una contraseña de aplicación) y «red-soporte» (sin rol en ningún sitio), el administrador del
 *       sitio principal «sitio-admin» (no superadministrador), el editor «colaborador» y el subsitio «dos» con su
 *       administrador «otro-admin» (que no administra el principal).
 * DIGITALÍSIMO Backups activo en la red y la sonda como mu-plugin (sin loopback ni correo). Las contraseñas se generan
 * aquí y sólo quedan en /digi-probe/secrets.json (0600). Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';
$dir = '/digi-probe';
try {
	if ( is_file( $dir . '/setup-error.txt' ) ) return;
	if ( ! is_multisite() ) throw new RuntimeException( 'La red no quedó activa.' );
	$site = trim( (string) file_get_contents( $dir . '/site.txt' ) );
	if ( ! in_array( $site, array( 'a', 'b' ), true ) ) throw new RuntimeException( 'Red desconocida: ' . $site );
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', true );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups en la red: ' . $activated->get_error_message() );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-kadm-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	global $wpdb;
	$secrets = array();
	$password = function() { return 'Pw-' . bin2hex( random_bytes( 10 ) ); };
	$main = get_main_site_id();
	$network = get_network();
	$user = function( $login, $email ) use ( &$secrets, $password ) {
		$secrets[ $login ] = $password();
		$id = wpmu_create_user( $login, $secrets[ $login ], $email );
		if ( ! $id ) throw new RuntimeException( 'No se creó ' . $login . '.' );
		return (int) $id;
	};
	if ( 'a' === $site ) {
		$wpdb->update( $wpdb->users, array( 'user_login' => 'dueno', 'user_nicename' => 'dueno', 'display_name' => 'Dueño', 'user_email' => 'dueno@red-a.test' ), array( 'ID' => 1 ) );
		update_site_option( 'site_admins', array( 'dueno' ) );
		clean_user_cache( 1 );
		$secrets['dueno'] = $password();
		wp_set_password( $secrets['dueno'], 1 );
		$app = WP_Application_Passwords::create_new_application_password( 1, array( 'name' => 'Respaldos de la red A' ) );
		if ( is_wp_error( $app ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $app->get_error_message() );
		$secrets['dueno_app'] = $app[0];
		update_option( 'blogname', 'Red A' );
		wp_set_current_user( 1 );
		wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada de la red A', 'post_content' => '<p>Sólo existe en la red A.</p>' ) );
		$uno = wp_insert_site( array( 'domain' => $network->domain, 'path' => $network->path . 'uno/', 'title' => 'Uno', 'user_id' => 1 ) );
		if ( is_wp_error( $uno ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $uno->get_error_message() );
	} else {
		$wpdb->update( $wpdb->users, array( 'user_email' => 'admin@red-b.test' ), array( 'ID' => 1 ) );
		clean_user_cache( 1 );
		$secrets['admin'] = $password();
		wp_set_password( $secrets['admin'], 1 );
		$app = WP_Application_Passwords::create_new_application_password( 1, array( 'name' => 'Restaurar la red B' ) );
		if ( is_wp_error( $app ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $app->get_error_message() );
		$secrets['admin_app'] = $app[0];
		$soporte = $user( 'red-soporte', 'soporte@red-b.test' );
		grant_super_admin( $soporte );
		$sitio = $user( 'sitio-admin', 'sitio@red-b.test' );
		add_user_to_blog( $main, $sitio, 'administrator' );
		$colaborador = $user( 'colaborador', 'colaborador@red-b.test' );
		add_user_to_blog( $main, $colaborador, 'editor' );
		$dos = wp_insert_site( array( 'domain' => $network->domain, 'path' => $network->path . 'dos/', 'title' => 'Dos', 'user_id' => 1 ) );
		if ( is_wp_error( $dos ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $dos->get_error_message() );
		$otro = $user( 'otro-admin', 'otro@red-b.test' );
		add_user_to_blog( $dos, $otro, 'administrator' );
		update_option( 'blogname', 'Red B' );
		wp_set_current_user( 1 );
		wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada de la red B', 'post_content' => '<p>Sólo existe en la red B.</p>' ) );
	}
	file_put_contents( $dir . '/secrets.json', wp_json_encode( $secrets ) );
	@chmod( $dir . '/secrets.json', 0600 );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'site' => $site, 'home' => home_url(), 'prefix' => $wpdb->base_prefix, 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
