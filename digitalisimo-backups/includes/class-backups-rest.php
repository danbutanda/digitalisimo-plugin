<?php
defined( 'ABSPATH' ) || exit;

/**
 * API REST `digitalisimo-backups/v1`: los mismos casos de uso que la pantalla Backups (Digitalisimo_Backups_Service).
 *
 * Autenticación de WordPress: con cookie exige el nonce `wp_rest` (cabecera X-WP-Nonce); también sirven las contraseñas
 * de aplicación. Cada ruta pide la capability de la pantalla: administrar la red en Multisite, `manage_options` en una
 * instalación individual. En una red, las rutas responden en el sitio principal (el de la red principal, si hay
 * varias); crear, restaurar y ejecutar piden además `network=true`. Los resúmenes nunca incluyen la ruta del almacén,
 * identificadores remotos, una clave ni una frase; un aviso que impide respaldar nombra la carpeta que hay que corregir.
 *
 * - GET  /status                            requisitos, operación y trabajos en curso, destinos, seguridad y último respaldo
 * - GET  /backups                           historial
 * - POST /backups                           encola un respaldo completo (`destination`, `network`): 202 con el trabajo
 * - GET|POST /backups/<archivo>/verify      integridad sin tocar el sitio (`secret` sólo en el cuerpo de un POST)
 * - POST /backups/<archivo>/restore         restauración (`confirm`, `network`, `secret`, `adopt_secret`, `addresses`)
 * - GET  /automations                       automatizaciones y su próxima ejecución
 * - POST /automations/<id>/run              encola la automatización ahora (`network`): 202 con el trabajo
 * - GET  /jobs                              trabajos sin terminar y los últimos terminados
 * - GET  /jobs/<id>                         un trabajo
 * - POST /jobs/<id>/cancel                  cancela un trabajo
 * - POST /jobs/tick                         un paso de la cola, como el navegador con la pantalla abierta
 * - POST /sites/<id>/extract                extrae un sitio de la red como WordPress individual (`network`,
 *                                           `super_admins`, `network_secrets`, `destination`): 202 con el trabajo
 *
 * La frase o clave de un respaldo (`secret`) sólo se acepta en el cuerpo de la petición: en la URL quedaría en los
 * registros del servidor y del proxy, así que una petición que la lleve en la cadena de consulta se rechaza (400).
 */
final class Digitalisimo_Backups_Rest {
	const NAMESPACE_V1 = 'digitalisimo-backups/v1';
	const FILE = '(?P<file>[A-Za-z0-9][A-Za-z0-9._-]*)';
	const JOB = '(?P<id>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}
	public static function routes() {
		$network = array( 'type' => 'boolean', 'default' => false, 'description' => 'Confirma el alcance de red (obligatorio en Multisite para crear, restaurar y ejecutar).' );
		$secret = array( 'type' => 'string', 'default' => '', 'description' => 'Frase o clave de un respaldo cifrado o firmado con una clave que este sitio no tiene. Sólo en el cuerpo de la petición; nunca se devuelve.' );
		$route = function( $methods, $callback, $args = array() ) {
			return array( 'methods' => $methods, 'callback' => array( __CLASS__, $callback ), 'permission_callback' => array( __CLASS__, 'allowed' ), 'args' => $args );
		};
		register_rest_route( self::NAMESPACE_V1, '/status', array( $route( 'GET', 'status' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/backups', array(
			$route( 'GET', 'backups' ),
			$route( 'POST', 'create', array( 'destination' => array( 'type' => 'string', 'default' => '', 'description' => 'Destino conectado: local, sftp, google u onedrive. Vacío usa el de los ajustes.' ), 'network' => $network ) ),
		) );
		$addresses = array( 'type' => 'string', 'default' => 'current', 'enum' => array( 'current', 'backup' ), 'description' => 'Si el respaldo viene de otra dirección: current conserva las de este sitio (por defecto) y backup las del respaldo. Las rutas del servidor y el prefijo de tablas siguen siempre a este sitio.' );
		register_rest_route( self::NAMESPACE_V1, '/backups/' . self::FILE . '/verify', array( $route( 'GET, POST', 'verify', array( 'secret' => $secret, 'addresses' => $addresses ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/backups/' . self::FILE . '/restore', array(
			$route( 'POST', 'restore', array(
				'confirm' => array( 'type' => 'string', 'required' => true, 'description' => 'RESTAURAR, o RESTAURAR SIN FIRMA para un archivo sin firma válida ni registro: la restauración reemplaza la base de datos y los archivos.' ),
				'network' => $network,
				'secret' => $secret,
				'adopt_secret' => array( 'type' => 'boolean', 'default' => false, 'description' => 'Usar la frase escrita para firmar y cifrar los respaldos nuevos.' ),
				'addresses' => $addresses,
			) ),
		) );
		register_rest_route( self::NAMESPACE_V1, '/automations', array( $route( 'GET', 'automations' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/automations/(?P<id>[a-z0-9_-]+)/run', array( $route( 'POST', 'run_automation', array( 'network' => $network ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/jobs', array( $route( 'GET', 'jobs', array( 'limit' => array( 'type' => 'integer', 'default' => 8, 'minimum' => 1, 'maximum' => 200, 'description' => 'Cuántos trabajos terminados mostrar además de los que siguen en curso.' ) ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/jobs/tick', array( $route( 'POST', 'tick' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/jobs/' . self::JOB, array( $route( 'GET', 'job' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/jobs/' . self::JOB . '/cancel', array( $route( 'POST', 'cancel' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/sites/(?P<site>[0-9]+)/extract', array(
			$route( 'POST', 'extract', array(
				'network' => $network,
				'super_admins' => array( 'type' => 'string', 'default' => 'managing', 'enum' => Digitalisimo_Backups_Site_Extract::SUPER_ADMINS, 'description' => 'Superadministradores que no son miembros del sitio: managing (sólo los que lo administran, como administradores), all o none.' ),
				'network_secrets' => array( 'type' => 'boolean', 'default' => false, 'description' => 'Copiar también las claves de API que el sitio hereda de la red.' ),
				'destination' => array( 'type' => 'string', 'default' => '', 'description' => 'Destino conectado: local, sftp, google u onedrive. Vacío es local.' ),
			) ),
		) );
	}
	/** Sin sesión, WordPress responde 401; con una cuenta sin la capability, 403. */
	public static function allowed() {
		return Digitalisimo_Backups::service_can_manage();
	}
	private static function respond( $result, $status = 200 ) {
		if ( is_wp_error( $result ) ) {
			// Un respaldo cifrado sin su clave: el error dice dónde va la frase en esta superficie.
			if ( 'digitalisimo_backups_secret_required' === $result->get_error_code() ) return new WP_Error( $result->get_error_code(), $result->get_error_message() . ' Envíala en el cuerpo de la petición (POST), en `secret`.', $result->get_error_data() );
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->set_status( $status );
		return $response;
	}
	/** La frase sólo del cuerpo (JSON o formulario); en la cadena de consulta es un error, nunca un valor que se use. */
	private static function secret( $request ) {
		$query = method_exists( $request, 'get_query_params' ) ? (array) $request->get_query_params() : array();
		if ( array_key_exists( 'secret', $query ) ) return new WP_Error( 'digitalisimo_backups_secret_in_url', 'La frase o clave del respaldo nunca va en la URL, donde quedaría en los registros del servidor: envíala en el cuerpo de una petición POST.', array( 'status' => 400 ) );
		$body = method_exists( $request, 'get_json_params' ) ? (array) $request->get_json_params() : array();
		if ( ! array_key_exists( 'secret', $body ) && method_exists( $request, 'get_body_params' ) ) $body = (array) $request->get_body_params();
		return is_string( $body['secret'] ?? null ) ? $body['secret'] : '';
	}
	public static function status( $request ) {
		return self::respond( Digitalisimo_Backups::service_status() );
	}
	public static function backups( $request ) {
		return self::respond( Digitalisimo_Backups::service_backups() );
	}
	public static function create( $request ) {
		return self::respond( Digitalisimo_Backups::service_create( array( 'destination' => (string) $request['destination'], 'network' => (bool) $request['network'], 'via' => 'rest' ) ), 202 );
	}
	public static function verify( $request ) {
		$secret = self::secret( $request );
		if ( is_wp_error( $secret ) ) return $secret;
		return self::respond( Digitalisimo_Backups::service_verify( (string) $request['file'], array( 'secret' => $secret, 'addresses' => (string) $request['addresses'] ) ) );
	}
	public static function restore( $request ) {
		$secret = self::secret( $request );
		if ( is_wp_error( $secret ) ) return $secret;
		return self::respond( Digitalisimo_Backups::service_restore( (string) $request['file'], array( 'confirm' => (string) $request['confirm'], 'network' => (bool) $request['network'], 'secret' => $secret, 'adopt' => (bool) $request['adopt_secret'], 'addresses' => (string) $request['addresses'] ) ) );
	}
	public static function automations( $request ) {
		return self::respond( Digitalisimo_Backups::service_automations() );
	}
	public static function run_automation( $request ) {
		return self::respond( Digitalisimo_Backups::service_run_automation( (string) $request['id'], array( 'network' => (bool) $request['network'], 'via' => 'rest' ) ), 202 );
	}
	public static function jobs( $request ) {
		return self::respond( Digitalisimo_Backups::service_jobs( (int) $request['limit'] ) );
	}
	public static function job( $request ) {
		return self::respond( Digitalisimo_Backups::service_job( (string) $request['id'] ) );
	}
	public static function cancel( $request ) {
		return self::respond( Digitalisimo_Backups::service_cancel( (string) $request['id'] ) );
	}
	public static function extract( $request ) {
		return self::respond( Digitalisimo_Backups::service_extract( (int) $request['site'], array( 'network' => (bool) $request['network'], 'super_admins' => (string) $request['super_admins'], 'network_secrets' => (bool) $request['network_secrets'], 'destination' => (string) $request['destination'], 'via' => 'rest' ) ), 202 );
	}
	public static function tick( $request ) {
		return self::respond( Digitalisimo_Backups::service_tick() );
	}
}
