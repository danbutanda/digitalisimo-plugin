<?php
/**
 * Fase «red» de backups-keep-access.sh (Playground con run-blueprint, un proceso PHP por paso), como la red de
 * digitalisimo.mx del incidente del 2026-11-12:
 *   setup    red por subdirectorios red.test; Backups, Tools y DIGITALÍSIMO Elements activos en la red; la ruta privada
 *            de acceso de la red en Tools («acceso-red»); el usuario 1 se llama «digitalisimo» (superadministrador y
 *            miembro del sitio 1) con contraseña y una contraseña de aplicación; el MCP de Elements encendido en el sitio 1.
 *   extract  POST /digitalisimo-backups/v1/sites/1/extract por la REST (network=true, super_admins=none,
 *            network_secrets=true) como superadministrador, con la cola avanzada por ticks.
 * Las contraseñas de prueba se generan aquí y sólo quedan en /digi-probe/secrets.json (0600) de la carpeta de resultados.
 */
$digi_step = (string) getenv( 'DIGI_STEP' );
$_SERVER['HTTP_HOST'] = 'red.test';
$_SERVER['REQUEST_URI'] = '/';
const DIGI_DIR = '/digi-probe';
register_shutdown_function( static function () use ( $digi_step ) {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( DIGI_DIR . '/fatal.log', $digi_step . ' ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line'] . "\n", FILE_APPEND );
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';
function digi_save( $key, array $problems, array $data = array() ) {
	$file = DIGI_DIR . '/result.json';
	$result = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	$result[ $key ] = array( 'problems' => array_values( $problems ), 'data' => $data );
	file_put_contents( $file, wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}
function digi_json( $name, array $add = null ) {
	$file = DIGI_DIR . '/' . $name;
	$state = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	if ( null !== $add ) { $state = array_merge( $state, $add ); file_put_contents( $file, wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); @chmod( $file, 0600 ); }
	return $state;
}
$problems = array();
try {
	switch ( $digi_step ) {
		case 'setup':
			foreach ( array( 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-elements/pro-elements.php' ) as $plugin ) {
				$done = activate_plugin( $plugin, '', true );
				if ( is_wp_error( $done ) ) throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $done->get_error_message() );
			}
			// La ruta privada de acceso de la red (Tools): el sitio 1 la hereda.
			update_site_option( 'digitalisimo_tools_site_defaults', array( 'login_slug' => 'acceso-red', 'redirect_attachments' => 1, 'mobile_scroll' => 0 ) );
			// El usuario 1 es «digitalisimo», como en la red real.
			global $wpdb;
			$wpdb->update( $wpdb->users, array( 'user_login' => 'digitalisimo', 'user_nicename' => 'digitalisimo', 'user_email' => 'dueno@digitalisimo.test', 'display_name' => 'Digitalísimo' ), array( 'ID' => 1 ) );
			update_site_option( 'site_admins', array( 'digitalisimo' ) );
			clean_user_cache( 1 );
			$password = 'red-' . bin2hex( random_bytes( 12 ) );
			wp_set_password( $password, 1 );
			$app = WP_Application_Passwords::create_new_application_password( 1, array( 'name' => 'MCP de la red' ) );
			if ( is_wp_error( $app ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $app->get_error_message() );
			if ( ! get_site_option( 'using_application_passwords' ) ) $problems[] = 'La red no anotó using_application_passwords en sitemeta.';
			update_option( 'elementor_mcp_enabled', true );
			wp_insert_post( array( 'post_title' => 'Portada de la red', 'post_content' => 'Hola desde la red.', 'post_status' => 'publish', 'post_type' => 'page' ) );
			digi_json( 'secrets.json', array( 'digitalisimo_password' => $password, 'digitalisimo_app' => $app[0] ) );
			digi_save( 'setup', $problems, array( 'login' => get_userdata( 1 )->user_login, 'super_admins' => get_super_admins(), 'in_use' => (bool) get_site_option( 'using_application_passwords' ), 'in_options' => (bool) get_option( 'using_application_passwords' ) ) );
			break;

		case 'extract':
			wp_set_current_user( 1 );
			$request = new WP_REST_Request( 'POST', '/digitalisimo-backups/v1/sites/1/extract' );
			foreach ( array( 'network' => true, 'super_admins' => 'none', 'network_secrets' => true ) as $name => $value ) $request->set_param( $name, $value );
			$response = rest_do_request( $request );
			if ( 202 !== $response->get_status() ) throw new RuntimeException( 'POST /sites/1/extract no respondió 202: ' . $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) );
			$id = (string) $response->get_data()['id'];
			$started = microtime( true );
			for ( $i = 0; $i < 80; $i++ ) {
				$job = Digitalisimo_Backups_Jobs::get( $id );
				if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
				Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
			}
			$view = Digitalisimo_Backups::service_job( $id );
			if ( is_wp_error( $view ) || 'success' !== $view['status'] ) throw new RuntimeException( 'La extracción no terminó: ' . wp_json_encode( is_wp_error( $view ) ? $view->get_error_message() : $view ) );
			digi_json( 'state.json', array( 'package' => $view['file'] ) );
			digi_save( 'extract', $problems, array( 'file' => $view['file'], 'seconds' => round( microtime( true ) - $started, 1 ), 'size' => (int) @filesize( DIGITALISIMO_BACKUPS_STORAGE_DIR . '/' . $view['file'] ) ) );
			break;

		default:
			throw new RuntimeException( 'Paso desconocido: ' . $digi_step );
	}
} catch ( Throwable $e ) {
	$problems[] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	digi_save( $digi_step, $problems );
}
