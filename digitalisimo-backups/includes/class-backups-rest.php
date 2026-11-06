<?php
defined( 'ABSPATH' ) || exit;

/**
 * API REST `digitalisimo-backups/v1`: los mismos casos de uso que la pantalla Backups (Digitalisimo_Backups_Service).
 *
 * Autenticación de WordPress: con cookie exige el nonce `wp_rest` (cabecera X-WP-Nonce); también sirven las contraseñas
 * de aplicación. Cada ruta pide la capability de la pantalla: administrar la red en Multisite, `manage_options` en una
 * instalación individual. En una red, las rutas responden en el sitio principal; crear, restaurar y ejecutar piden
 * además `network=true`. Las respuestas nunca incluyen rutas del servidor ni identificadores remotos.
 *
 * - GET  /status                            requisitos, operación en curso, destinos y último respaldo
 * - GET  /backups                           historial
 * - POST /backups                           respaldo completo (`destination`, `network`)
 * - GET  /backups/<archivo>/verify          integridad sin tocar el sitio
 * - POST /backups/<archivo>/restore         restauración (`confirm=RESTAURAR`, `network`)
 * - GET  /automations                       automatizaciones y su próxima ejecución
 * - POST /automations/<id>/run              ejecutar ahora (`network`)
 */
final class Digitalisimo_Backups_Rest {
	const NAMESPACE_V1 = 'digitalisimo-backups/v1';
	const FILE = '(?P<file>[A-Za-z0-9][A-Za-z0-9._-]*)';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}
	public static function routes() {
		$network = array( 'type' => 'boolean', 'default' => false, 'description' => 'Confirma el alcance de red (obligatorio en Multisite para crear, restaurar y ejecutar).' );
		$route = function( $methods, $callback, $args = array() ) {
			return array( 'methods' => $methods, 'callback' => array( __CLASS__, $callback ), 'permission_callback' => array( __CLASS__, 'allowed' ), 'args' => $args );
		};
		register_rest_route( self::NAMESPACE_V1, '/status', array( $route( 'GET', 'status' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/backups', array(
			$route( 'GET', 'backups' ),
			$route( 'POST', 'create', array( 'destination' => array( 'type' => 'string', 'default' => '', 'description' => 'Destino conectado: local, sftp, google u onedrive. Vacío usa el de los ajustes.' ), 'network' => $network ) ),
		) );
		register_rest_route( self::NAMESPACE_V1, '/backups/' . self::FILE . '/verify', array( $route( 'GET', 'verify' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/backups/' . self::FILE . '/restore', array(
			$route( 'POST', 'restore', array( 'confirm' => array( 'type' => 'string', 'required' => true, 'description' => 'Escribe RESTAURAR: la restauración reemplaza la base de datos y los archivos.' ), 'network' => $network ) ),
		) );
		register_rest_route( self::NAMESPACE_V1, '/automations', array( $route( 'GET', 'automations' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/automations/(?P<id>[a-z0-9_-]+)/run', array( $route( 'POST', 'run_automation', array( 'network' => $network ) ) ) );
	}
	/** Sin sesión, WordPress responde 401; con una cuenta sin la capability, 403. */
	public static function allowed() {
		return Digitalisimo_Backups::service_can_manage();
	}
	private static function respond( $result, $status = 200 ) {
		if ( is_wp_error( $result ) ) return $result;
		$response = rest_ensure_response( $result );
		$response->set_status( $status );
		return $response;
	}
	public static function status( $request ) {
		return self::respond( Digitalisimo_Backups::service_status() );
	}
	public static function backups( $request ) {
		return self::respond( Digitalisimo_Backups::service_backups() );
	}
	public static function create( $request ) {
		return self::respond( Digitalisimo_Backups::service_create( array( 'destination' => (string) $request['destination'], 'network' => (bool) $request['network'] ) ), 201 );
	}
	public static function verify( $request ) {
		return self::respond( Digitalisimo_Backups::service_verify( (string) $request['file'] ) );
	}
	public static function restore( $request ) {
		return self::respond( Digitalisimo_Backups::service_restore( (string) $request['file'], array( 'confirm' => (string) $request['confirm'], 'network' => (bool) $request['network'] ) ) );
	}
	public static function automations( $request ) {
		return self::respond( Digitalisimo_Backups::service_automations() );
	}
	public static function run_automation( $request ) {
		return self::respond( Digitalisimo_Backups::service_run_automation( (string) $request['id'], array( 'network' => (bool) $request['network'] ) ), 201 );
	}
}
