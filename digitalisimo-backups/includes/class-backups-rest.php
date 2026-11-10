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
 * - POST /backups/<file>/fetch               trae al almacén la copia remota, verificada por su SHA-256
 * - GET  /destinations                       destinos remotos y su estado (sin secretos)
 * - POST /destinations/<id>/test             «Probar conexión» de una conexión guardada
 * - GET|POST /backups/<archivo>/verify      integridad sin tocar el sitio (`secret` sólo en el cuerpo de un POST)
 * - POST /backups/<archivo>/restore         restauración (`confirm`, `network`, `secret`, `adopt_secret`, `addresses`)
 * - GET  /automations                       automatizaciones y su próxima ejecución
 * - POST /automations/<id>/run              encola la automatización ahora (`network`): 202 con el trabajo
 * - GET  /jobs                              trabajos sin terminar y los últimos terminados
 * - GET  /jobs/<id>                         un trabajo
 * - POST /jobs/<id>/cancel                  cancela un trabajo
 * - POST /jobs/tick                         un paso de la cola, como el navegador con la pantalla abierta
 * - POST /sites/<id>/extract                extrae un sitio de la red como WordPress individual (`network`,
 *                                           `super_admins`, `network_secrets`, `destination` y, en el cuerpo, `secret`:
 *                                           la frase propia del paquete): 202 con el trabajo
 * - GET  /uploads                           las subidas sin terminar y las cerradas de las últimas 24 h (todas las cuentas)
 * - POST /uploads                           empieza a subir un respaldo (`name`, `size`, `sha256` opcional): 201
 * - GET  /uploads/<id>                      fase, lo recibido y, si terminó, su resultado (para continuar o consultar)
 * - POST|PUT /uploads/<id>/chunk            un fragmento en `offset`: el cuerpo en bruto o el archivo `chunk` (multipart)
 * - POST /uploads/<id>/finish               valida el archivo como no confiable y lo deja en el almacén (`secret`); 202
 *                                           mientras otro proceso lo comprueba; repetirlo tras un corte da el mismo resultado
 * - DELETE /uploads/<id>                    cancela la subida y borra lo recibido
 * - GET  /pre-update                        respaldo antes de actualizar: ajustes por tipo, respaldo reciente válido,
 *                                           actualizaciones automáticas retenidas o pospuestas y el último fallo
 * - POST /pre-update                        guarda los ajustes (`core`, `plugin`, `theme`: all, auto u off; `max_age`)
 * - POST /pre-update/backup                 el respaldo previo ahora (`network`): `ready` si ya hay uno reciente, si no 202
 * - POST /pre-update/skip                   las manuales no esperan respaldo durante `minutes` (`network`)
 * - GET  /destinations/sync                 lo último sincronizado de cada destino (sin llamarlos)
 * - POST /destinations/sync                 sincroniza los respaldos de los destinos (`destination`: uno o todos)
 * - GET  /destinations/<id>/backups         los respaldos de DIGITALÍSIMO del destino, listados ahora: de este sitio
 *                                           (`registered`) y de otro sitio (`importable`, sin verificar)
 * - POST /destinations/<id>/backups/<nombre>/fetch  lo trae al almacén por fragmentos y lo comprueba (`network`): 200
 *                                           con el resultado, 202 con el trabajo si sigue; después, POST …/restore
 * - GET  /backuply                          si Backuply está en el sitio y sus respaldos que se pueden importar
 * - POST /backuply/import                   importa un respaldo de Backuply (`name`, `source`): 202 con el trabajo
 * - POST /backuply/fetch                    trae al almacén un respaldo de Backuply de un destino (`connection`, `name`)
 * - DELETE /backuply/<nombre>               borra un respaldo de Backuply del almacén privado (no los de su carpeta)
 *
 * - GET  /migration                         estado de la migración: claves (sin secretos), registro, la que se puede deshacer
 * - POST /migration/keys                    genera una clave de migración (`hours`, `network`): 201, la clave sólo esta vez
 * - POST /migration/keys/<id>/revoke        revoca una clave
 * - POST /migration/pull                    «Migrar desde otro sitio» (`key`, `confirm`=MIGRAR, `network`): 202 con el trabajo
 * - POST /migration/undo                    «Deshacer la migración» (`confirm`=DESHACER, `network`)
 *
 * API del sitio de origen para el destino (`/migrate/source/…`): no usa la sesión de WordPress, la autoriza la clave de
 * migración en la cabecera X-Digitalisimo-Migration (nunca en la URL) con su límite de intentos; `download` responde
 * los bytes del paquete (206, `Range`). Ver Digitalisimo_Backups_Migrate.
 *
 * La frase o clave de un respaldo (`secret`) sólo se acepta en el cuerpo de la petición: en la URL quedaría en los
 * registros del servidor y del proxy, así que una petición que la lleve en la cadena de consulta se rechaza (400). Lo
 * mismo con la clave de migración (`key`).
 */
final class Digitalisimo_Backups_Rest {
	const NAMESPACE_V1 = 'digitalisimo-backups/v1';
	const FILE = '(?P<file>[A-Za-z0-9][A-Za-z0-9._-]*)';
	const JOB = '(?P<id>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})';
	const DESTINATION = '(?P<id>[a-z0-9]+(?:-[a-f0-9]{8})?)';

	/** Registro de la clave que autorizó cada petición del origen (lo llena permission_callback). */
	private static $migration_records = array();
	/** Tramo del paquete que serve_package() envía (ruta, posición y largo). */
	private static $package_range = null;

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_package' ), 10, 4 );
	}
	public static function routes() {
		$network = array( 'type' => 'boolean', 'default' => false, 'description' => 'Confirma el alcance de red (obligatorio en Multisite para crear, restaurar, ejecutar y traer al almacén).' );
		$secret = array( 'type' => 'string', 'default' => '', 'description' => 'Frase o clave de un respaldo cifrado o firmado con una clave que este sitio no tiene. Sólo en el cuerpo de la petición; nunca se devuelve.' );
		$route = function( $methods, $callback, $args = array() ) {
			return array( 'methods' => $methods, 'callback' => array( __CLASS__, $callback ), 'permission_callback' => array( __CLASS__, 'allowed' ), 'args' => $args );
		};
		register_rest_route( self::NAMESPACE_V1, '/status', array( $route( 'GET', 'status' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/backups', array(
			$route( 'GET', 'backups' ),
			$route( 'POST', 'create', array( 'destination' => array( 'type' => 'string', 'default' => '', 'description' => 'Destino conectado: local, el tipo de una conexión principal (sftp, ftp, webdav, google, onedrive, dropbox, s3, s3compat, pcloud) o el identificador de una conexión adicional (como sftp-0a1b2c3d); ver GET /destinations. Vacío usa el de los ajustes.' ), 'network' => $network ) ),
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
		register_rest_route( self::NAMESPACE_V1, '/backups/' . self::FILE . '/fetch', array( $route( 'POST', 'fetch', array( 'network' => $network ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/destinations', array( $route( 'GET', 'destinations' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/destinations/(?P<id>[a-z0-9]+(?:-[a-f0-9]{8})?)/test', array( $route( 'POST', 'test_destination' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/automations', array( $route( 'GET', 'automations' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/automations/(?P<id>[a-z0-9_-]+)/run', array( $route( 'POST', 'run_automation', array( 'network' => $network ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/jobs', array( $route( 'GET', 'jobs', array( 'limit' => array( 'type' => 'integer', 'default' => 8, 'minimum' => 1, 'maximum' => 200, 'description' => 'Cuántos trabajos terminados mostrar además de los que siguen en curso.' ) ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/jobs/tick', array( $route( 'POST', 'tick' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/jobs/' . self::JOB, array( $route( 'GET', 'job' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/jobs/' . self::JOB . '/cancel', array( $route( 'POST', 'cancel' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/sites/(?P<site>[0-9]+)/extract', array(
			$route( 'POST', 'extract', array(
				'network' => $network,
				'super_admins' => array( 'type' => 'string', 'default' => 'none', 'enum' => Digitalisimo_Backups_Site_Extract::SUPER_ADMINS, 'description' => 'Superadministradores que no son miembros del sitio: none (ninguno viaja con su contraseña; por defecto), managing (como administrador sólo quien tiene el correo de administración del sitio) o all.' ),
				'secret' => $secret,
				'network_secrets' => array( 'type' => 'boolean', 'default' => false, 'description' => 'Copiar también las claves de API que el sitio hereda de la red.' ),
				'destination' => array( 'type' => 'string', 'default' => '', 'description' => 'Destino conectado: local, el tipo de una conexión principal (sftp, ftp, webdav, google, onedrive, dropbox, s3, s3compat, pcloud) o el identificador de una conexión adicional (como sftp-0a1b2c3d); ver GET /destinations. Vacío es local.' ),
			) ),
		) );
		register_rest_route( self::NAMESPACE_V1, '/uploads', array(
			$route( 'GET', 'upload_list' ),
			$route( 'POST', 'upload_start', array(
				'name' => array( 'type' => 'string', 'required' => true, 'description' => 'Nombre del archivo (.zip o .dgbk).' ),
				'size' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'description' => 'Tamaño exacto en bytes.' ),
				'sha256' => array( 'type' => 'string', 'default' => '', 'description' => 'SHA-256 del archivo, si se conoce: al terminar se comprueba.' ),
			) ),
		) );
		register_rest_route( self::NAMESPACE_V1, '/uploads/' . self::JOB, array( $route( 'GET', 'upload_status' ), $route( 'DELETE', 'upload_cancel' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/uploads/' . self::JOB . '/chunk', array( $route( 'POST, PUT', 'upload_chunk', array( 'offset' => array( 'type' => 'integer', 'required' => true, 'minimum' => 0, 'description' => 'Byte en el que empieza el fragmento: exactamente lo ya recibido.' ) ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/uploads/' . self::JOB . '/finish', array( $route( 'POST', 'upload_finish', array( 'secret' => $secret ) ) ) );
		// Respaldos en los destinos remotos (B1b, B2): listar, sincronizar y traer al almacén, también los de otro sitio.
		register_rest_route( self::NAMESPACE_V1, '/destinations/sync', array(
			$route( 'GET', 'remote_index' ),
			$route( 'POST', 'sync', array( 'destination' => array( 'type' => 'string', 'default' => '', 'description' => 'Un destino (ver GET /destinations); vacío: todos los que tienen conexión completa.' ) ) ),
		) );
		register_rest_route( self::NAMESPACE_V1, '/destinations/' . self::DESTINATION . '/backups', array( $route( 'GET', 'remote_backups' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/destinations/' . self::DESTINATION . '/backups/(?P<name>[A-Za-z0-9][A-Za-z0-9._-]*)/fetch', array( $route( 'POST', 'remote_fetch', array( 'network' => $network ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/backuply', array( $route( 'GET', 'backuply' ) ) );
		$backuply_name = array( 'type' => 'string', 'required' => true, 'description' => 'Nombre del respaldo de Backuply (.tar.gz), como aparece en GET /backuply o en el destino.' );
		register_rest_route( self::NAMESPACE_V1, '/backuply/import', array( $route( 'POST', 'backuply_import', array( 'name' => $backuply_name, 'source' => array( 'type' => 'string', 'default' => '', 'enum' => array( '', 'backuply', 'store' ), 'description' => 'backuply (la carpeta de Backuply de este sitio) o store (el almacén privado); vacío lo busca en los dos.' ) ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/backuply/(?P<name>[A-Za-z0-9][A-Za-z0-9._-]{0,190}\.tar\.gz)', array( $route( 'DELETE', 'backuply_delete' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/backuply/fetch', array( $route( 'POST', 'backuply_fetch', array( 'name' => $backuply_name, 'connection' => array( 'type' => 'string', 'required' => true, 'description' => 'Destino conectado (ver GET /destinations).' ) ) ) ) );
		// Migración: administración (la misma capability que la pantalla).
		register_rest_route( self::NAMESPACE_V1, '/migration', array( $route( 'GET', 'migration_status' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/migration/keys', array( $route( 'POST', 'migration_create_key', array( 'hours' => array( 'type' => 'integer', 'default' => Digitalisimo_Backups_Migrate::DEFAULT_HOURS, 'minimum' => 1, 'maximum' => Digitalisimo_Backups_Migrate::MAX_HOURS, 'description' => 'Horas hasta que caduca la clave.' ), 'network' => $network ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/migration/keys/(?P<key>[0-9a-f]{16})/revoke', array( $route( 'POST', 'migration_revoke_key' ) ) );
		register_rest_route( self::NAMESPACE_V1, '/migration/pull', array( $route( 'POST', 'migration_pull', array( 'key' => array( 'type' => 'string', 'default' => '', 'description' => 'Clave de migración del sitio de origen. Sólo en el cuerpo de la petición; nunca se devuelve.' ), 'confirm' => array( 'type' => 'string', 'required' => true, 'description' => 'MIGRAR: la migración reemplaza este sitio (con una copia previa para deshacerla).' ), 'network' => $network ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/migration/undo', array( $route( 'POST', 'migration_undo', array( 'confirm' => array( 'type' => 'string', 'required' => true, 'description' => 'DESHACER: el sitio vuelve al estado anterior a la última migración.' ), 'network' => $network ) ) ) );
		// Migración: API del sitio de origen, autorizada por la clave de migración.
		foreach ( array( 'hello', 'package', 'step', 'finish', 'release' ) as $action ) {
			register_rest_route( self::NAMESPACE_V1, '/migrate/source/' . $action, array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'migration_source' ), 'permission_callback' => array( __CLASS__, 'migration_source_allowed' ), 'args' => array() ) ) );
		}
		register_rest_route( self::NAMESPACE_V1, '/migrate/source/download', array( array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'migration_source' ), 'permission_callback' => array( __CLASS__, 'migration_source_allowed' ), 'args' => array() ) ) );
		// Respaldo antes de actualizar (I1).
		$mode = array( 'type' => 'string', 'enum' => array_keys( Digitalisimo_Backups_Pre_Update::MODES ), 'description' => 'all (manuales y automáticas), auto (sólo automáticas) u off.' );
		register_rest_route( self::NAMESPACE_V1, '/pre-update', array(
			$route( 'GET', 'pre_update' ),
			$route( 'POST', 'pre_update_save', array( 'core' => $mode, 'plugin' => $mode, 'theme' => $mode, 'max_age' => array( 'type' => 'integer', 'enum' => Digitalisimo_Backups_Pre_Update::AGES, 'description' => 'Horas: un respaldo completo válido de esta antigüedad o menos evita crear otro.' ) ) ),
		) );
		register_rest_route( self::NAMESPACE_V1, '/pre-update/backup', array( $route( 'POST', 'pre_update_backup', array( 'network' => $network ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/pre-update/skip', array( $route( 'POST', 'pre_update_skip', array( 'network' => $network, 'minutes' => array( 'type' => 'integer', 'default' => Digitalisimo_Backups_Pre_Update::SKIP_MINUTES, 'minimum' => 1, 'maximum' => 60, 'description' => 'Minutos sin respaldo previo para las actualizaciones manuales.' ) ) ) ) );
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
	/** Trae al almacén privado la copia remota de un respaldo del historial, verificada por su SHA-256. */
	public static function fetch( $request ) {
		// Un trabajo por fragmentos (B1b): 202 con el trabajo si no terminó en esta petición.
		$result = Digitalisimo_Backups::service_fetch( (string) $request['file'], array( 'network' => (bool) $request['network'], 'via' => 'rest' ) );
		return self::respond( $result, is_array( $result ) && ! empty( $result['running'] ) ? 202 : 200 );
	}
	/** Destinos remotos y su estado (sin valores secretos). */
	public static function destinations( $request ) { return self::respond( Digitalisimo_Backups::service_destinations() ); }
	/** «Probar conexión» de una conexión guardada: sube, verifica, descarga, lista y borra un archivo pequeño. */
	public static function test_destination( $request ) { return self::respond( Digitalisimo_Backups::service_test_saved( (string) $request['id'] ) ); }
	public static function verify( $request ) {
		$secret = self::secret( $request );
		if ( is_wp_error( $secret ) ) return $secret;
		return self::respond( Digitalisimo_Backups::service_verify( (string) $request['file'], array( 'secret' => $secret, 'addresses' => (string) $request['addresses'] ) ) );
	}
	public static function restore( $request ) {
		$secret = self::secret( $request );
		if ( is_wp_error( $secret ) ) return $secret;
		// La restauración es un trabajo por pasos: esta petición avanza lo que cabe en su tiempo y responde 202 si sigue
		// (con el trabajo y el testigo del mantenimiento); POST /jobs/tick, la pantalla o WP-Cron la terminan.
		$result = Digitalisimo_Backups::service_restore( (string) $request['file'], array( 'confirm' => (string) $request['confirm'], 'network' => (bool) $request['network'], 'secret' => $secret, 'adopt' => (bool) $request['adopt_secret'], 'addresses' => (string) $request['addresses'], 'via' => 'rest', 'sync' => false, 'drive_cli' => false ) );
		if ( is_array( $result ) && ! empty( $result['maintenance_token'] ) ) Digitalisimo_Backups_Restore_Maintenance::send_cookie( (string) $result['maintenance_token'] );
		return self::respond( $result, is_array( $result ) && ! empty( $result['restoring'] ) ? 202 : 200 );
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
		// La frase propia del paquete, sólo del cuerpo de la petición (nunca de la URL).
		$secret = self::secret( $request );
		if ( is_wp_error( $secret ) ) return $secret;
		return self::respond( Digitalisimo_Backups::service_extract( (int) $request['site'], array( 'network' => (bool) $request['network'], 'super_admins' => (string) $request['super_admins'], 'network_secrets' => (bool) $request['network_secrets'], 'destination' => (string) $request['destination'], 'secret' => $secret, 'via' => 'rest' ) ), 202 );
	}
	public static function tick( $request ) {
		return self::respond( Digitalisimo_Backups::service_tick() );
	}

	/* ------------------------------------------------------------------ Migración */

	public static function migration_status( $request ) {
		return self::respond( Digitalisimo_Backups::service_migration_status() );
	}
	public static function migration_create_key( $request ) {
		$result = Digitalisimo_Backups::service_migration_create_key( array( 'hours' => (int) $request['hours'], 'network' => (bool) $request['network'] ) );
		$response = self::respond( $result, 201 );
		if ( ! is_wp_error( $response ) && method_exists( $response, 'header' ) ) $response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
	public static function migration_revoke_key( $request ) {
		return self::respond( Digitalisimo_Backups::service_migration_revoke_key( (string) $request['key'] ) );
	}
	/** La clave sólo del cuerpo; en la cadena de consulta es un error y no se usa. */
	public static function migration_pull( $request ) {
		$query = method_exists( $request, 'get_query_params' ) ? (array) $request->get_query_params() : array();
		if ( array_key_exists( 'key', $query ) ) return new WP_Error( 'digitalisimo_backups_key_in_url', 'La clave de migración nunca va en la URL, donde quedaría en los registros del servidor: envíala en el cuerpo de una petición POST.', array( 'status' => 400 ) );
		$body = method_exists( $request, 'get_json_params' ) ? (array) $request->get_json_params() : array();
		if ( ! array_key_exists( 'key', $body ) && method_exists( $request, 'get_body_params' ) ) $body = (array) $request->get_body_params();
		$key = is_string( $body['key'] ?? null ) ? $body['key'] : '';
		return self::respond( Digitalisimo_Backups::service_migrate( $key, array( 'confirm' => (string) $request['confirm'], 'network' => (bool) $request['network'], 'via' => 'rest' ) ), 202 );
	}
	public static function migration_undo( $request ) {
		$result = Digitalisimo_Backups::service_migration_undo( array( 'confirm' => (string) $request['confirm'], 'network' => (bool) $request['network'] ) );
		// Como «Restaurar»: 202 si la restauración sigue en segundo plano (su trabajo y el testigo del mantenimiento van en la
		// respuesta, y el testigo también en su cookie).
		if ( is_array( $result ) && ! empty( $result['maintenance_token'] ) ) Digitalisimo_Backups_Restore_Maintenance::send_cookie( (string) $result['maintenance_token'] );
		return self::respond( $result, is_array( $result ) && ! empty( $result['restoring'] ) ? 202 : 200 );
	}
	private static function migration_action( $request ) {
		$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : (string) ( $request['_route'] ?? '' );
		return (string) substr( strrchr( '/' . trim( $route, '/' ), '/' ), 1 );
	}
	private static function header( $request, $name ) {
		return method_exists( $request, 'get_header' ) ? (string) $request->get_header( $name ) : '';
	}
	/**
	 * Autoriza una petición del destino con la clave de migración de la cabecera. Una clave en la cadena de consulta se
	 * rechaza sin usarla; sin cabecera responde 401 como una clave equivocada (y cuenta como intento fallido).
	 */
	public static function migration_source_allowed( $request ) {
		$query = method_exists( $request, 'get_query_params' ) ? (array) $request->get_query_params() : array();
		foreach ( array( 'key', 'auth', 'secret', 'migration' ) as $name ) if ( array_key_exists( $name, $query ) ) return new WP_Error( 'digitalisimo_backups_migration_in_url', 'La clave de migración nunca va en la URL: va en la cabecera ' . Digitalisimo_Backups_Migrate::HEADER . '.', array( 'status' => 400 ) );
		$record = Digitalisimo_Backups_Migrate::authenticate( self::header( $request, Digitalisimo_Backups_Migrate::HEADER ), self::header( $request, Digitalisimo_Backups_Migrate::CLIENT_HEADER ), self::migration_action( $request ) );
		if ( is_wp_error( $record ) ) return $record;
		self::$migration_records[ spl_object_id( $request ) ] = $record;
		return true;
	}
	public static function migration_source( $request ) {
		$id = spl_object_id( $request );
		$record = self::$migration_records[ $id ] ?? null;
		unset( self::$migration_records[ $id ] );
		if ( ! is_array( $record ) ) return new WP_Error( 'digitalisimo_backups_migration_unauthorized', 'La clave de migración no es válida para este sitio.', array( 'status' => 401 ) );
		$action = self::migration_action( $request );
		$body = method_exists( $request, 'get_json_params' ) ? (array) $request->get_json_params() : array();
		$result = Digitalisimo_Backups::service_migration_source( $action, $record, self::header( $request, Digitalisimo_Backups_Migrate::CLIENT_HEADER ), $body, self::header( $request, 'range' ) );
		if ( is_wp_error( $result ) || 'download' !== $action ) return self::respond( $result );
		// Bytes del paquete: rest_pre_serve_request los envía tal cual, leyendo el archivo por trozos (WordPress codificaría el
		// cuerpo como JSON y lo tendría entero en memoria).
		self::$package_range = array( 'path' => $result['path'], 'start' => (int) $result['start'], 'length' => (int) $result['end'] - (int) $result['start'] + 1 );
		$response = new WP_REST_Response( null, 206 );
		$response->header( 'Content-Length', (string) self::$package_range['length'] );
		$response->header( 'Content-Type', 'application/octet-stream' );
		$response->header( 'Content-Range', 'bytes ' . $result['start'] . '-' . $result['end'] . '/' . $result['total'] );
		$response->header( 'X-Digitalisimo-Package-Sha256', $result['sha256'] );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'X-Digitalisimo-Package', '1' );
		return $response;
	}
	/** Envía tal cual los bytes de un fragmento del paquete, por trozos; cualquier otra respuesta sigue su camino. */
	public static function serve_package( $served, $result, $request = null, $server = null ) {
		if ( $served || ! $result instanceof WP_REST_Response || 206 !== $result->get_status() ) return $served;
		$headers = method_exists( $result, 'get_headers' ) ? (array) $result->get_headers() : array();
		$range = self::$package_range;
		if ( '1' !== ( $headers['X-Digitalisimo-Package'] ?? '' ) || ! is_array( $range ) ) return $served;
		self::$package_range = null;
		Digitalisimo_Backups_Migrate::emit_range( $range['path'], $range['start'], $range['length'] );
		return true;
	}
	public static function upload_start( $request ) {
		return self::respond( Digitalisimo_Backups::service_upload_start( array( 'name' => (string) $request['name'], 'size' => $request['size'], 'sha256' => (string) $request['sha256'] ) ), 201 );
	}
	public static function upload_list( $request ) {
		return self::respond( Digitalisimo_Backups::service_upload_list() );
	}
	public static function upload_status( $request ) {
		return self::respond( Digitalisimo_Backups::service_upload_status( (string) $request['id'] ) );
	}
	/** El fragmento: el archivo `chunk` de un formulario multipart (sin cargarlo en memoria) o el cuerpo en bruto. */
	public static function upload_chunk( $request ) {
		$files = method_exists( $request, 'get_file_params' ) ? (array) $request->get_file_params() : array();
		$file = isset( $files['chunk'] ) && is_array( $files['chunk'] ) ? $files['chunk'] : array();
		if ( is_string( $file['tmp_name'] ?? null ) && '' !== $file['tmp_name'] && UPLOAD_ERR_OK === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) && call_user_func( Digitalisimo_Backups_Uploads::$is_uploaded, $file['tmp_name'] ) ) $source = array( 'file' => $file['tmp_name'] );
		else $source = array( 'data' => method_exists( $request, 'get_body' ) ? (string) $request->get_body() : '' );
		return self::respond( Digitalisimo_Backups::service_upload_chunk( (string) $request['id'], $request['offset'], $source ) );
	}
	public static function upload_finish( $request ) {
		$secret = self::secret( $request );
		if ( is_wp_error( $secret ) ) return $secret;
		return self::respond( Digitalisimo_Backups::service_upload_finish( (string) $request['id'], array( 'secret' => $secret ) ) );
	}
	/** Si Backuply está en el sitio y los respaldos suyos que se pueden importar (sin rutas). */
	public static function backuply( $request ) { return self::respond( Digitalisimo_Backups::service_backuply() ); }
	/** Encola la importación de un respaldo de Backuply: 202 con el trabajo. */
	public static function backuply_import( $request ) { return self::respond( Digitalisimo_Backups::service_backuply_import( (string) $request['name'], array( 'source' => (string) $request['source'], 'via' => 'rest' ) ), 202 ); }
	/** Borra un respaldo de Backuply del almacén privado. */
	public static function backuply_delete( $request ) { return self::respond( Digitalisimo_Backups::service_backuply_delete( (string) $request['name'] ) ); }
	/** Trae al almacén un respaldo de Backuply que está en un destino remoto. */
	public static function backuply_fetch( $request ) { return self::respond( Digitalisimo_Backups::service_backuply_fetch( (string) $request['connection'], (string) $request['name'] ) ); }
	public static function upload_cancel( $request ) {
		return self::respond( Digitalisimo_Backups::service_upload_cancel( (string) $request['id'] ) );
	}
	public static function pre_update( $request ) {
		return self::respond( Digitalisimo_Backups_Pre_Update::service_status() );
	}
	public static function pre_update_save( $request ) {
		$input = array();
		foreach ( array( 'core', 'plugin', 'theme', 'max_age' ) as $key ) if ( null !== $request[ $key ] ) $input[ $key ] = $request[ $key ];
		return self::respond( Digitalisimo_Backups_Pre_Update::service_save( $input ) );
	}
	public static function pre_update_backup( $request ) {
		$result = Digitalisimo_Backups_Pre_Update::service_backup( array( 'network' => (bool) $request['network'], 'via' => 'rest' ) );
		return self::respond( $result, is_array( $result ) && 'running' === $result['state'] ? 202 : 200 );
	}
	public static function pre_update_skip( $request ) {
		return self::respond( Digitalisimo_Backups_Pre_Update::service_skip( array( 'network' => (bool) $request['network'], 'minutes' => (int) $request['minutes'], 'via' => 'rest' ) ) );
	}
	/** Lo último sincronizado de cada destino, sin llamarlos. */
	public static function remote_index( $request ) { return self::respond( Digitalisimo_Backups::service_remote_index() ); }
	/** Sincroniza los respaldos de los destinos (o de uno) y guarda el índice. */
	public static function sync( $request ) { return self::respond( Digitalisimo_Backups::service_sync( (string) $request['destination'] ) ); }
	/** Respaldos de DIGITALÍSIMO del destino, listados ahora. */
	public static function remote_backups( $request ) { return self::respond( Digitalisimo_Backups::service_remote_backups( (string) $request['id'] ) ); }
	/**
	 * Trae al almacén un respaldo del destino (de este sitio o de otro) por fragmentos: 200 con el resultado si terminó en
	 * esta petición, 202 con el trabajo si sigue (lo terminan `POST /jobs/tick`, la pantalla o WP-Cron).
	 */
	public static function remote_fetch( $request ) {
		$result = Digitalisimo_Backups::service_remote_fetch( (string) $request['id'], (string) $request['name'], array( 'network' => (bool) $request['network'], 'via' => 'rest', 'drive_cli' => false ) );
		return self::respond( $result, is_array( $result ) && ! empty( $result['running'] ) ? 202 : 200 );
	}
}
