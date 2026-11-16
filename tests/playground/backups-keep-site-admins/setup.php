<?php
/**
 * Preparación del ensayo «los administradores de este sitio siguen entrando» (paso runPHP del blueprint de
 * backups-keep-site-admins.sh). Según /digi-probe/site.txt:
 *   a   «Sitio A»: su administrador «dueno» (el usuario 1 de Playground, con otro nombre y otra contraseña) y su editora
 *       «editora»; una entrada y una página propias.
 *   b   «Sitio B», recién instalado: los administradores «admin» y «soporte» (éste con una contraseña de aplicación) y el
 *       editor «colaborador»; una entrada propia.
 * Claves propias en wp-config.php (como un sitio real), la sonda como mu-plugin (sin loopback ni correo) y DIGITALÍSIMO
 * Backups activo. Las contraseñas se generan aquí y sólo quedan en /digi-probe/secrets.json (0600) de la carpeta de la
 * corrida. Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$dir = '/digi-probe';
try {
	$site = trim( (string) file_get_contents( $dir . '/site.txt' ) );
	if ( ! in_array( $site, array( 'a', 'b' ), true ) ) throw new RuntimeException( 'Sitio desconocido: ' . $site );
	$config = ABSPATH . 'wp-config.php';
	if ( is_file( $config ) && is_writable( $config ) ) {
		$text = (string) file_get_contents( $config );
		$text = preg_replace_callback( "/define\\(\\s*'(AUTH_KEY|SECURE_AUTH_KEY)'\\s*,\\s*'put your unique phrase here'\\s*\\);/", function( $m ) { return "define( '" . $m[1] . "', '" . bin2hex( random_bytes( 32 ) ) . "' );"; }, $text );
		file_put_contents( $config, $text );
	}
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-kadm-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	global $wpdb;
	$secrets = array();
	$password = function() { return 'Pw-' . bin2hex( random_bytes( 10 ) ); };
	$user = function( $login, $role, $email ) use ( &$secrets, $password ) {
		$secrets[ $login ] = $password();
		$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $secrets[ $login ], 'user_email' => $email, 'role' => $role, 'display_name' => ucfirst( $login ) ) );
		if ( is_wp_error( $id ) ) throw new RuntimeException( 'No se creó ' . $login . ': ' . $id->get_error_message() );
		return (int) $id;
	};
	if ( 'a' === $site ) {
		update_option( 'blogname', 'Sitio A' );
		$wpdb->update( $wpdb->users, array( 'user_login' => 'dueno', 'user_nicename' => 'dueno', 'display_name' => 'Dueño', 'user_email' => 'dueno@sitio-a.test' ), array( 'ID' => 1 ) );
		clean_user_cache( 1 );
		$secrets['dueno'] = $password();
		wp_set_password( $secrets['dueno'], 1 );
		$user( 'editora', 'editor', 'editora@sitio-a.test' );
		wp_set_current_user( 1 );
		wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada del sitio A', 'post_content' => '<p>Sólo existe en el sitio A.</p>' ) );
		wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página del sitio A', 'post_content' => '<p>Contenido de A.</p>' ) );
	} else {
		update_option( 'blogname', 'Sitio B' );
		$wpdb->update( $wpdb->users, array( 'user_email' => 'admin@sitio-b.test' ), array( 'ID' => 1 ) );
		clean_user_cache( 1 );
		$secrets['admin'] = $password();
		wp_set_password( $secrets['admin'], 1 );
		$soporte = $user( 'soporte', 'administrator', 'soporte@sitio-b.test' );
		$app = WP_Application_Passwords::create_new_application_password( $soporte, array( 'name' => 'Cliente de soporte' ) );
		if ( is_wp_error( $app ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $app->get_error_message() );
		$secrets['soporte_app'] = $app[0];
		$user( 'colaborador', 'editor', 'colaborador@sitio-b.test' );
		wp_set_current_user( 1 );
		wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada del sitio B', 'post_content' => '<p>Sólo existe en el sitio B.</p>' ) );
	}
	file_put_contents( $dir . '/secrets.json', wp_json_encode( $secrets ) );
	@chmod( $dir . '/secrets.json', 0600 );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'site' => $site, 'home' => home_url() ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
