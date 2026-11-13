<?php
defined( 'ABSPATH' ) || exit;

/**
 * Migración en un clic entre servidores y dominios, por «tirar» desde el destino (ver «Migración» en docs/BACKUPS.md).
 *
 * En el sitio de origen, «Generar clave de migración» crea una clave de un solo uso que caduca (24 h por defecto) y se
 * puede revocar. Sólo se muestra una vez: lleva la dirección REST del origen, un identificador y 32 bytes aleatorios
 * `s`. De `s` salen, con BLAKE2b y separación de dominio:
 *
 * - `A = BLAKE2b-256("DGBK1/migrate-auth", clave = s)`: la credencial con la que el destino se presenta (cabecera
 *   `X-Digitalisimo-Migration: <id>.<base64url(A)>`, nunca en la URL). El origen sólo guarda su huella,
 *   `hex(BLAKE2b-256("DGBK1/migrate-verifier", clave = A))`, en `digitalisimo_backups_vault_migration` (de la red en
 *   Multisite), que como todo `digitalisimo_backups_vault_*` nunca entra en un respaldo.
 * - `K = BLAKE2b-256("DGBK1/migrate-root", clave = s)`: la clave raíz del paquete (contenedor DGBK de «Seguridad», con el
 *   manifiesto firmado por la misma clave). Nunca viaja: el origen la guarda envuelta (secretbox) con las claves de su
 *   wp-config.php en el almacén privado (`migrations/<id>.key`) mientras la clave de migración vive, y el destino la
 *   saca de la clave pegada. Así el paquete va cifrado de extremo a extremo aunque la conexión no lo esté.
 *
 * En el destino, «Migrar desde otro sitio» con la clave pegada encola un trabajo `migrate` en la cola de «Trabajos»
 * (fases preflight → remote → download → check → restore → finish): pide al origen que cree el paquete con su motor de
 * trabajos, lo hace avanzar, lo descarga por fragmentos con `Range` y reanudación, comprueba tamaño, SHA-256, cabecera,
 * descifrado y firma, y lo restaura con la copia previa bloqueada (que es la que deshace la migración) y la reescritura
 * de direcciones del motor de restauración en otro dominio. Al terminar, el origen da la clave por usada. El origen
 * nunca se modifica ni se borra: sólo crea el paquete en su almacén privado y anota el uso de la clave.
 */
final class Digitalisimo_Backups_Migrate {
	/** Estado de la migración: claves (huellas), registro de usos, intentos fallidos y opciones. Nunca entra en un respaldo. */
	const OPTION = 'digitalisimo_backups_vault_migration';
	/** Contador de peticiones sin una clave válida (fuera del estado: se escribe sin su cerrojo, como mucho una vez por minuto). */
	const REJECTED = 'digitalisimo_backups_vault_migration_rejected';
	const KEY_PREFIX = 'DGMIG1.';
	const HEADER = 'X-Digitalisimo-Migration';
	const CLIENT_HEADER = 'X-Digitalisimo-Migration-Client';
	const DIRECTORY = 'migrations';
	const NAMESPACE_V1 = 'digitalisimo-backups/v1';
	const SOURCE_ROUTE = '/migrate/source';
	/** Fallos de autenticación con una clave antes de bloquearla, y fallos de cualquier origen en la ventana antes de cerrar. */
	const MAX_FAILURES = 5;
	/**
	 * Una clave en uso cuyo destino no da señales en este tiempo (y cuyo paquete ya no se está preparando) se puede
	 * retomar desde otro trabajo del destino y, pasada su caducidad, caduca: una migración que falló sin poder liberar la
	 * clave no la deja atada a un trabajo muerto, y una descarga activa nunca caduca a mitad.
	 */
	const STALE = 1800;
	const FORM_TOKENS = 20;
	const DEFAULT_HOURS = 24;
	const MAX_HOURS = 72;
	const CHUNK = 4194304;
	const MAX_CHUNK = 16777216;
	/** Tiempos agotados seguidos al preguntar al origen (que sigue trabajando) antes de contar un intento. */
	const TIMEOUTS = 12;
	const LOG_MAX = 100;
	const KEYS_MAX = 50;
	const LIVE = array( 'pending', 'in_use' );
	const ACTION_CREATE = 'digitalisimo_backups_migration_key';
	const ACTION_REVOKE = 'digitalisimo_backups_migration_revoke';
	const ACTION_PULL = 'digitalisimo_backups_migration_pull';
	const ACTION_UNDO = 'digitalisimo_backups_migration_undo';
	const ACTION_SETTINGS = 'digitalisimo_backups_migration_settings';
	const ACTION_CLEANUP = 'digitalisimo_backups_migration_cleanup';
	/** Transporte HTTP del destino (sólo pruebas): callable( $method, $url, $args ) con la forma de wp_remote_request(). */
	public static $transport = null;
	/** Espera entre reintentos inmediatos de un fragmento (sustituible en las pruebas). */
	public static $pause = 'sleep';
	/** Fin de la página que muestra la clave (sustituible en las pruebas; en WordPress, exit). */
	public static $end = null;
	/** Resolución de nombres del destino (sólo pruebas): callable( $host ) que devuelve sus direcciones IP. */
	public static $resolver = null;
	/** Saltos de redirección que sigue el destino (cada uno validado como la dirección de la clave). */
	const REDIRECTS = 3;
	/** Tope de una respuesta JSON del origen (un fragmento del paquete tiene el suyo: su largo pedido). */
	const RESPONSE_MAX = 1048576;

	public static function init() {
		add_action( 'http_api_curl', array( __CLASS__, 'pin_curl' ), 10, 3 );
		foreach ( array( self::ACTION_CREATE => 'create_request', self::ACTION_REVOKE => 'revoke_request', self::ACTION_PULL => 'pull_request', self::ACTION_UNDO => 'undo_request', self::ACTION_SETTINGS => 'settings_request', self::ACTION_CLEANUP => 'cleanup_request' ) as $action => $method ) add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		add_action( Digitalisimo_Backups_Jobs::HOOK_WATCHDOG, array( __CLASS__, 'sweep' ), 20 );
	}

	/* ------------------------------------------------------------------ Tipo de trabajo `migrate` (Digitalisimo_Backups_Jobs::register_kind) */

	public static function job_phases() { return array( 'preflight', 'remote', 'download', 'check', 'restore', 'close' ); }
	/** Datos de las fases propias (`preflight` es la del respaldo). Una restauración interrumpida no se repite sola. */
	public static function job_phase( $phase ) {
		$phases = array(
			'remote' => array( 'label' => 'Paquete en el origen', 'floor' => 3, 'max' => 5, 'cancellable' => true, 'status' => 'running' ),
			'download' => array( 'label' => 'Descarga', 'floor' => 40, 'max' => 5, 'cancellable' => true, 'status' => 'transferring' ),
			'check' => array( 'label' => 'Comprobación del paquete', 'floor' => 75, 'max' => 3, 'cancellable' => true, 'status' => 'verifying' ),
			// La restauración es un trabajo del motor de restauración por pasos (copia previa, tablas temporales, cambio atómico,
			// archivos, vuelta automática a la copia previa); esta fase lo encola y espera su resultado (repetirla no lo repite).
			'restore' => array( 'label' => 'Restauración', 'floor' => 78, 'max' => 3, 'cancellable' => false, 'status' => 'running', 'refusal' => 'La migración ya se está aplicando con la restauración por pasos: su tarjeta en «Trabajos» se puede cancelar mientras no empiece a cambiar el sitio; si falla después, vuelve sola a la copia previa.', 'interrupted' => ' La restauración por pasos sigue sola (o vuelve a la copia previa); si el sitio quedó a medias, «Volver a la copia previa» en Backups lo recupera.' ),
			'close' => array( 'label' => 'Cierre', 'floor' => 98, 'max' => 3, 'cancellable' => false, 'status' => 'running', 'refusal' => 'La migración ya se aplicó; sólo falta avisar al sitio de origen. Para volver atrás, usa «Deshacer la migración» en Backups → Herramientas → Migrar.' ),
		);
		return $phases[ $phase ] ?? null;
	}
	/** Lo que la tarjeta «Trabajos» dice de una migración recibida y del paquete que este sitio prepara para otro. */
	public static function job_view( array $job, array $view ) {
		$spec = (array) ( $job['spec'] ?? array() );
		if ( 'migrate' === ( $spec['kind'] ?? '' ) ) {
			$host = (string) ( $spec['migration']['source_host'] ?? '' );
			if ( 'preflight' === $view['phase'] && ! $view['terminal'] ) $view['phase_label'] = 'Conexión con el origen';
			if ( 'success' === $view['status'] ) $view['detail'] = (string) $view['message'] . ( $view['warnings'] ? ' · ' . $view['warnings'] . ' aviso(s) en el registro del trabajo' : '' );
			return array_merge( $view, array( 'kind' => 'migration', 'origin_label' => 'Migración desde otro sitio', 'destination_label' => 'desde ' . $host, 'file' => '' ) );
		}
		if ( 'source' === ( $spec['migration']['role'] ?? '' ) ) {
			if ( 'success' === $view['status'] ) $view['detail'] = 'Paquete cifrado listo para el sitio de destino';
			return array_merge( $view, array( 'kind' => 'migration-package', 'origin_label' => 'Paquete de migración', 'destination_label' => 'para otro sitio', 'file' => '' ) );
		}
		return $view;
	}
	/** Fase «publish» del paquete en el origen: queda en la carpeta del trabajo con su SHA-256, sin entrar en el historial. */
	public static function published( $final ) {
		$sha256 = @hash_file( 'sha256', $final );
		if ( false === $sha256 ) throw new Exception( 'No se pudo calcular el SHA-256 del paquete de migración.' );
		return array( 'next' => null, 'checkpoint' => array( 'renamed' => true, 'sha256' => $sha256 ), 'result' => array( 'size' => (int) @filesize( $final ), 'sha256' => $sha256 ), 'message' => 'Paquete de migración cifrado y verificado; esperando a que el sitio de destino lo descargue.' );
	}

	/* ------------------------------------------------------------------ Estado (de la red en Multisite) */

	public static function state() {
		$saved = Digitalisimo_Backups_Keys::get( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'version' => 1,
			'instance' => is_string( $saved['instance'] ?? null ) && preg_match( '/^[0-9a-f]{16}$/D', $saved['instance'] ) ? $saved['instance'] : '',
			'allow_http' => ! empty( $saved['allow_http'] ),
			'keys' => is_array( $saved['keys'] ?? null ) ? $saved['keys'] : array(),
			'log' => is_array( $saved['log'] ?? null ) ? array_values( $saved['log'] ) : array(),
			'rejected' => is_array( $saved['rejected'] ?? null ) ? $saved['rejected'] : array(),
			'forms' => is_array( $saved['forms'] ?? null ) ? array_values( $saved['forms'] ) : array(),
			// Registro de las tablas que apartaron las migraciones y sus «Deshacer» (K1): sólo estas se borran, nunca otra.
			'kept' => is_array( $saved['kept'] ?? null ) ? $saved['kept'] : array(),
		);
	}
	/** Lee, cambia y guarda el estado con el cerrojo corto del almacén: dos peticiones del destino nunca se pisan. */
	private static function change( callable $change ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $change ) {
			self::fresh();
			$state = self::state();
			$result = $change( $state );
			if ( false !== $result ) Digitalisimo_Backups_Keys::put( self::OPTION, $state );
			return $result;
		} );
	}
	/** Otra petición pudo cambiar el estado: se lee de la base de datos, no de la caché de esta petición. */
	private static function fresh() {
		if ( ! function_exists( 'wp_cache_delete' ) ) return;
		if ( is_multisite() ) {
			$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
			wp_cache_delete( $network . ':' . self::OPTION, 'site-options' );
			wp_cache_delete( $network . ':notoptions', 'site-options' );
			return;
		}
		wp_cache_delete( self::OPTION, 'options' );
		$missing = function_exists( 'wp_cache_get' ) ? wp_cache_get( 'notoptions', 'options' ) : false;
		if ( is_array( $missing ) && isset( $missing[ self::OPTION ] ) && function_exists( 'wp_cache_set' ) ) { unset( $missing[ self::OPTION ] ); wp_cache_set( 'notoptions', $missing, 'options' ); }
	}
	/** Identificador aleatorio de esta instalación (no viaja en los respaldos): detecta una clave de este mismo sitio. */
	public static function instance() {
		$state = self::state();
		if ( '' !== $state['instance'] ) return $state['instance'];
		return self::change( function( &$state ) {
			if ( '' === $state['instance'] ) $state['instance'] = bin2hex( random_bytes( 8 ) );
			return $state['instance'];
		} );
	}
	/**
	 * Lo que una restauración no puede llevarse: el SQL restaurado reemplaza options y sitemeta, y el estado de la
	 * migración (claves, registro, «Permitir HTTP» e identificador de la instalación) nunca viaja en un respaldo.
	 * restore_digitalisimo_zip() lo guarda antes del SQL y after_restore_sql() lo devuelve (idempotente).
	 */
	private static $held = null;
	public static function before_restore() {
		self::$held = Digitalisimo_Backups_Keys::get( self::OPTION, null );
		// También sellado en el almacén: si el proceso muere entre el cambio de tablas y la reposición, el diario de la
		// restauración repone lo demás en la operación siguiente (otro proceso) y esto, el estado de la migración.
		try {
			if ( is_array( self::$held ) ) self::seal_file( self::held_file(), (string) wp_json_encode( self::$held ) );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
		}
	}
	public static function after_restore() {
		$held = self::$held;
		if ( ! is_array( $held ) ) {
			$sealed = self::open_file( self::held_file() );
			$held = is_string( $sealed ) ? json_decode( $sealed, true ) : null;
		}
		if ( is_array( $held ) ) Digitalisimo_Backups_Keys::put( self::OPTION, $held );
		self::$held = null;
		@unlink( self::held_file() );
	}
	private static function held_file() { return self::directory() . '/held.state'; }
	/** Anota un evento en el registro (lo usan los casos de uso de deshacer). */
	public static function note( $event, $detail = '' ) { self::log( '', $event, $detail ); }
	public static function allow_http() { return self::state()['allow_http']; }
	public static function set_allow_http( $allow ) {
		self::change( function( &$state ) use ( $allow ) {
			$state['allow_http'] = (bool) $allow;
			self::log_into( $state, '', $allow ? 'lab_on' : 'lab_off' );
		} );
	}
	private static function log_into( array &$state, $id, $event, $detail = '' ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9A-Fa-f:.]/', '', (string) $_SERVER['REMOTE_ADDR'] ) : '';
		$state['log'][] = array( 'at' => time(), 'key' => substr( preg_replace( '/[^0-9a-f]/', '', (string) $id ), 0, 8 ), 'event' => (string) $event, 'ip' => substr( $ip, 0, 45 ), 'detail' => substr( (string) $detail, 0, 300 ) );
		$state['log'] = array_slice( $state['log'], -self::LOG_MAX );
	}
	private static function log( $id, $event, $detail = '' ) {
		try {
			self::change( function( &$state ) use ( $id, $event, $detail ) { self::log_into( $state, $id, $event, $detail ); } );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: registro de migración: ' . $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------ Derivación de claves */

	private static function h( $message, $key ) { return sodium_crypto_generichash( $message, $key, 32 ); }
	public static function auth_of( $secret ) { return self::h( 'DGBK1/migrate-auth', $secret ); }
	public static function root_of( $secret ) { return self::h( 'DGBK1/migrate-root', $secret ); }
	public static function verifier_of( $auth ) { return bin2hex( self::h( 'DGBK1/migrate-verifier', $auth ) ); }
	/** Clave que envuelve lo que la migración guarda en el almacén; false sin AUTH_KEY y SECURE_AUTH_KEY propias. */
	private static function wrap_key() {
		$base = Digitalisimo_Backups_Keys::wrap_key();
		return false === $base ? false : self::h( 'DGBK1/migrate-wrap', $base );
	}
	private static function seal_file( $path, $plain ) {
		$key = self::wrap_key();
		if ( false === $key ) throw new Exception( 'Para migrar, wp-config.php debe definir AUTH_KEY y SECURE_AUTH_KEY propias (al menos 16 caracteres, no el texto de ejemplo de WordPress): con ellas se guarda la clave mientras dura la migración.' );
		$nonce = random_bytes( 24 );
		$json = wp_json_encode( array( 'v' => 1, 'kw' => Digitalisimo_Backups_Keys::wrap_id( $key ), 'box' => Digitalisimo_Backups_Keys::b64url_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) ) ) );
		$dir = dirname( $path );
		if ( is_link( $dir ) ) throw new Exception( 'La carpeta de migraciones del almacén no puede ser un enlace simbólico.' );
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) throw new Exception( 'No se pudo preparar la carpeta de migraciones en el almacén privado.' );
		$temp = $path . '.tmp-' . bin2hex( random_bytes( 4 ) );
		if ( is_link( $path ) || strlen( $json ) !== @file_put_contents( $temp, $json ) || ! @chmod( $temp, 0600 ) || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			throw new Exception( 'No se pudo guardar la clave de migración en el almacén privado.' );
		}
	}
	private static function open_file( $path ) {
		if ( is_link( $path ) || ! is_file( $path ) ) return false;
		$key = self::wrap_key();
		$data = json_decode( (string) @file_get_contents( $path ), true );
		if ( false === $key || ! is_array( $data ) || Digitalisimo_Backups_Keys::wrap_id( $key ) !== ( $data['kw'] ?? '' ) ) return false;
		$raw = Digitalisimo_Backups_Keys::b64url_decode( $data['box'] ?? null );
		if ( false === $raw || strlen( $raw ) < 24 + 16 ) return false;
		try { $plain = sodium_crypto_secretbox_open( substr( $raw, 24 ), substr( $raw, 0, 24 ), $key ); }
		catch ( Throwable $e ) { return false; }
		return is_string( $plain ) ? $plain : false;
	}
	private static function directory() { return rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . self::DIRECTORY; }
	private static function key_file( $id ) { return self::directory() . '/' . $id . '.key'; }
	private static function valid_id( $id ) { return is_string( $id ) && 1 === preg_match( '/^[0-9a-f]{16}$/D', $id ); }

	/* ------------------------------------------------------------------ Origen: claves */

	/** URL de la API de migración del origen (en una red, la del sitio principal): va dentro de la clave. */
	public static function source_base() {
		$path = self::NAMESPACE_V1 . self::SOURCE_ROUTE;
		if ( is_multisite() && function_exists( 'get_rest_url' ) ) return get_rest_url( get_main_site_id(), $path );
		return rest_url( $path );
	}
	/** Por qué este sitio no puede generar una clave ('' si puede). */
	public static function key_problem() {
		if ( is_multisite() && ! is_main_site() ) return 'En una red, las claves de migración se generan desde el sitio principal o la administración de red.';
		if ( ! Digitalisimo_Backups_Sodium::available() ) return 'Este servidor no tiene las funciones de libsodium para cifrar el paquete (' . implode( ', ', Digitalisimo_Backups_Sodium::missing() ) . ').';
		if ( false === Digitalisimo_Backups_Keys::wrap_key() ) return 'Para generar una clave de migración, wp-config.php debe definir AUTH_KEY y SECURE_AUTH_KEY propias (al menos 16 caracteres, no el texto de ejemplo de WordPress).';
		if ( 'https' !== strtolower( (string) wp_parse_url( self::source_base(), PHP_URL_SCHEME ) ) && ! self::allow_http() ) return 'Este sitio no usa HTTPS: la credencial de la clave viajaría legible. Activa HTTPS o, sólo en un laboratorio, «Permitir HTTP».';
		return '';
	}
	/**
	 * Crea una clave de migración y la devuelve una sola vez. En la base de datos sólo queda la huella de su credencial;
	 * la clave del paquete se guarda envuelta en el almacén privado y desaparece al usarse, revocarse o caducar.
	 */
	public static function create_key( $hours = self::DEFAULT_HOURS ) {
		$problem = self::key_problem();
		if ( '' !== $problem ) throw new Exception( $problem );
		$hours = max( 1, min( self::MAX_HOURS, (int) $hours ) );
		self::sweep();
		$secret = random_bytes( 32 );
		$auth = self::auth_of( $secret );
		$root = self::root_of( $secret );
		$id = bin2hex( random_bytes( 8 ) );
		$now = time();
		try {
			self::seal_file( self::key_file( $id ), $root );
			$record = array( 'id' => $id, 'verifier' => self::verifier_of( $auth ), 'key_id' => Digitalisimo_Backups_Keys::key_id( $root ), 'created' => $now, 'expires' => $now + $hours * 3600, 'state' => 'pending', 'failures' => 0, 'client' => '', 'job' => '', 'site' => '', 'used_at' => 0, 'closed_at' => 0, 'by' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
			self::change( function( &$state ) use ( $record ) {
				$state['keys'][ $record['id'] ] = $record;
				self::log_into( $state, $record['id'], 'created', 'caduca ' . gmdate( 'Y-m-d H:i', $record['expires'] ) . ' UTC' );
			} );
			$url = self::source_base();
			$key = self::KEY_PREFIX . Digitalisimo_Backups_Keys::b64url_encode( wp_json_encode( array( 'v' => 1, 'u' => $url, 'i' => $id, 's' => Digitalisimo_Backups_Keys::b64url_encode( $secret ) ) ) );
		} catch ( Throwable $e ) {
			@unlink( self::key_file( $id ) );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $secret );
			Digitalisimo_Backups_Sodium::memzero( $auth );
			Digitalisimo_Backups_Sodium::memzero( $root );
		}
		return array( 'key' => $key, 'id' => $id, 'expires' => $now + $hours * 3600, 'expires_at' => gmdate( 'c', $now + $hours * 3600 ), 'source' => (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}
	/** Fila pública de una clave: nunca la credencial, su huella ni la clave del paquete. */
	public static function public_key( array $record ) {
		$labels = array( 'pending' => 'Sin usar', 'in_use' => 'En uso', 'used' => 'Usada', 'revoked' => 'Revocada', 'expired' => 'Caducada', 'blocked' => 'Bloqueada por intentos fallidos' );
		$state = (string) ( $record['state'] ?? '' );
		return array(
			'id' => (string) ( $record['id'] ?? '' ),
			'state' => $state,
			'state_label' => $labels[ $state ] ?? $state,
			'created_at' => gmdate( 'c', (int) ( $record['created'] ?? 0 ) ),
			'expires_at' => gmdate( 'c', (int) ( $record['expires'] ?? 0 ) ),
			'site' => (string) ( $record['site'] ?? '' ),
			'failures' => (int) ( $record['failures'] ?? 0 ),
			'live' => in_array( $state, self::LIVE, true ),
		);
	}
	public static function keys() {
		self::sweep();
		$rows = array_map( array( __CLASS__, 'public_key' ), array_values( self::state()['keys'] ) );
		usort( $rows, function( $a, $b ) { return strcmp( $b['created_at'], $a['created_at'] ); } );
		return $rows;
	}
	/** Revoca una clave: su paquete y la clave del paquete se borran y su trabajo se cancela. El sitio no cambia. */
	public static function revoke( $id ) {
		if ( ! self::valid_id( $id ) ) throw new InvalidArgumentException( 'El identificador de la clave no es válido.' );
		$record = self::change( function( &$state ) use ( $id ) {
			if ( ! isset( $state['keys'][ $id ] ) ) return false;
			if ( in_array( $state['keys'][ $id ]['state'], self::LIVE, true ) ) {
				$state['keys'][ $id ]['state'] = 'revoked';
				$state['keys'][ $id ]['closed_at'] = time();
				self::log_into( $state, $id, 'revoked' );
			}
			return $state['keys'][ $id ];
		} );
		if ( false === $record ) throw new OutOfBoundsException( 'Esa clave de migración no existe.' );
		self::retire( $record );
		return self::public_key( $record );
	}
	/** Borra lo que una clave cerrada dejó: la clave envuelta, el paquete y su trabajo (si sigue en cola o en curso). */
	private static function retire( array $record ) {
		@unlink( self::key_file( (string) $record['id'] ) );
		$job_id = (string) ( $record['job'] ?? '' );
		if ( ! Digitalisimo_Backups_Job_Store::valid_id( $job_id ) ) return;
		try {
			$job = Digitalisimo_Backups_Jobs::get( $job_id );
			if ( $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) Digitalisimo_Backups_Jobs::cancel( $job_id );
			$package = Digitalisimo_Backups_Job_Store::directory( $job_id ) . '/' . basename( (string) ( $job['spec']['file'] ?? 'migration.dgbk' ) );
			if ( is_file( $package ) && ! is_link( $package ) ) @unlink( $package );
		} catch ( Throwable $e ) {
			// La poda del almacén retira la carpeta del trabajo más adelante.
		}
	}
	/** Caducidad y tope de registros: lo cerrado conserva su fila (sin secretos) para el registro, hasta KEYS_MAX. */
	public static function sweep() {
		try {
			$closed = self::change( function( &$state ) {
				$now = time();
				$closed = array();
				foreach ( $state['keys'] as $id => &$record ) {
					if ( self::lapsed( $record, $now ) ) {
						$record['state'] = 'expired';
						$record['closed_at'] = $now;
						self::log_into( $state, $id, 'expired' );
						$closed[] = $record;
					}
				}
				unset( $record );
				if ( count( $state['keys'] ) > self::KEYS_MAX ) {
					uasort( $state['keys'], function( $a, $b ) { return (int) $b['created'] <=> (int) $a['created']; } );
					$keep = array();
					foreach ( $state['keys'] as $id => $record ) if ( count( $keep ) < self::KEYS_MAX || in_array( $record['state'], self::LIVE, true ) ) $keep[ $id ] = $record;
					$state['keys'] = $keep;
				}
				return $closed;
			} );
			foreach ( (array) $closed as $record ) self::retire( $record );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
		}
	}
	/** Si una huella de clave es la de un paquete de una clave viva de este sitio (la usa Digitalisimo_Backups_Keys). */
	public static function knows_key( $key_id ) {
		foreach ( self::state()['keys'] as $record ) if ( hash_equals( (string) ( $record['key_id'] ?? '' ), (string) $key_id ) ) return in_array( $record['state'], self::LIVE, true ) && ! self::lapsed( $record, time() );
		return false;
	}
	/**
	 * Si una clave viva caducó. Sin usar, al pasar su caducidad. En uso, sólo si además su destino lleva STALE segundos
	 * sin dar señales: la caducidad nunca corta una migración que avanza (el paquete puede tardar horas en un sitio grande).
	 */
	private static function lapsed( array $record, $now ) {
		if ( ! in_array( $record['state'] ?? '', self::LIVE, true ) || $now <= (int) ( $record['expires'] ?? 0 ) ) return false;
		return 'in_use' !== $record['state'] || $now - self::seen( $record ) > self::STALE;
	}
	/** Última señal del destino al que está atada la clave. */
	private static function seen( array $record ) { return max( (int) ( $record['created'] ?? 0 ), (int) ( $record['seen'] ?? 0 ) ); }
	/**
	 * Si otro trabajo del destino puede retomar una clave en uso: su destino lleva STALE segundos sin dar señales y su
	 * paquete ya no se está preparando (la migración falló o se canceló sin poder liberarla).
	 */
	private static function stale( array $record, $now ) {
		// Un paquete ya entregado entero nunca se vuelve a servir (N2): la clave es de un solo uso aunque el destino tarde
		// en restaurar (o espere a otro trabajo) más que STALE sin hablar con el origen.
		if ( ! empty( $record['delivered'] ) ) return false;
		if ( 'in_use' !== ( $record['state'] ?? '' ) || $now - self::seen( $record ) <= self::STALE ) return false;
		$job = Digitalisimo_Backups_Job_Store::valid_id( (string) ( $record['job'] ?? '' ) ) ? Digitalisimo_Backups_Jobs::get( (string) $record['job'] ) : null;
		return ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job );
	}
	/** La clave del paquete de una clave viva, desenvuelta del almacén; false si ya no existe o cambiaron las salts. */
	public static function package_key( $key_id ) {
		foreach ( self::state()['keys'] as $record ) {
			if ( ! hash_equals( (string) ( $record['key_id'] ?? '' ), (string) $key_id ) || ! in_array( $record['state'], self::LIVE, true ) ) continue;
			$root = self::open_file( self::key_file( (string) $record['id'] ) );
			return is_string( $root ) && 32 === strlen( $root ) && hash_equals( (string) $key_id, Digitalisimo_Backups_Keys::key_id( $root ) ) ? $root : false;
		}
		return false;
	}

	/* ------------------------------------------------------------------ Origen: API para el destino */

	private static function error( $code, $message, $status ) { return new WP_Error( 'digitalisimo_backups_migration_' . $code, $message, array( 'status' => $status ) ); }
	/**
	 * Autentica una petición del destino: credencial (comparación de tiempo constante con la huella guardada), límite de
	 * intentos de esa clave, caducidad, estado, HTTPS y, salvo para presentarse, el trabajo del destino al que quedó atada
	 * la clave. Devuelve la fila de la clave o un WP_Error sin ningún secreto.
	 *
	 * No hay un límite global: una petición sin una clave válida (sin cabecera, mal formada o de una clave que no existe)
	 * nunca cierra la API ni gasta los intentos de nadie, y sólo suma a un contador agregado que no desplaza el registro
	 * de usos. Adivinar no es viable (identificador de 64 bits y credencial de 256), y cada clave se bloquea sola tras
	 * MAX_FAILURES credenciales equivocadas.
	 */
	public static function authenticate( $header, $client, $action ) {
		$now = time();
		$state = self::state();
		if ( ! is_ssl() && 'https' !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) ) && ! $state['allow_http'] ) return self::error( 'https', 'El sitio de origen exige HTTPS para migrar. En un laboratorio, activa «Permitir HTTP» en Backups → Herramientas → Migrar de los dos sitios.', 403 );
		// En una red, la API responde sólo en el sitio principal (la presentación lo explica en sus problemas).
		if ( 'hello' !== $action && is_multisite() && ! is_main_site() ) return self::error( 'not_main', 'La API de migración de una red responde en su sitio principal.', 404 );
		$header = trim( (string) $header );
		$parts = explode( '.', $header, 2 );
		$auth = 2 === count( $parts ) ? Digitalisimo_Backups_Keys::b64url_decode( $parts[1], 32 ) : false;
		$id = $parts[0];
		if ( false === $auth || ! self::valid_id( $id ) || ! isset( $state['keys'][ $id ] ) ) {
			self::reject();
			return self::error( 'unauthorized', 'La clave de migración no es válida para este sitio.', 401 );
		}
		$record = $state['keys'][ $id ];
		if ( ! hash_equals( (string) $record['verifier'], self::verifier_of( $auth ) ) ) {
			// Una clave cerrada ya no tiene intentos que contar: como una desconocida (el registro no crece sin fin).
			if ( ! in_array( $record['state'], self::LIVE, true ) ) {
				self::reject();
				return self::error( 'unauthorized', 'La clave de migración no es válida para este sitio.', 401 );
			}
			$blocked = self::fail( $id, 'Credencial equivocada.' );
			return self::error( 'unauthorized', 'La clave de migración no es válida para este sitio.' . ( $blocked ? ' Se bloqueó tras ' . self::MAX_FAILURES . ' intentos fallidos.' : '' ), 401 );
		}
		Digitalisimo_Backups_Sodium::memzero( $auth );
		if ( self::lapsed( $record, $now ) ) {
			self::sweep();
			return self::error( 'expired', 'La clave de migración caducó. Genera otra en el sitio de origen.', 410 );
		}
		$closed = array( 'used' => 'Esta clave de migración ya se usó: genera otra en el sitio de origen.', 'revoked' => 'Esta clave de migración fue revocada en el sitio de origen.', 'expired' => 'La clave de migración caducó. Genera otra en el sitio de origen.', 'blocked' => 'Esta clave de migración se bloqueó por intentos fallidos. Genera otra en el sitio de origen.' );
		if ( isset( $closed[ $record['state'] ] ) ) return self::error( $record['state'], $closed[ $record['state'] ], 410 );
		$client = (string) $client;
		if ( 'hello' !== $action ) {
			if ( ! Digitalisimo_Backups_Job_Store::valid_id( $client ) ) return self::error( 'client', 'Falta el identificador de la migración del sitio de destino.', 400 );
			$mine = '' === $record['client'] || hash_equals( (string) $record['client'], hash( 'sha256', $client ) );
			// Otro trabajo del destino puede retomar con «package» una clave abandonada (source_package() lo decide con el cerrojo).
			if ( ! $mine && ! ( 'package' === $action && self::stale( $record, $now ) ) ) {
				self::log( $id, 'other_client', $action );
				return self::in_use_error();
			}
			if ( $mine && '' !== $record['client'] && $now - (int) ( $record['seen'] ?? 0 ) >= 60 ) self::touch( $id, (string) $record['client'] );
		}
		return $record;
	}
	private static function in_use_error() {
		return self::error( 'in_use', 'Otra migración ya usa esta clave. Una clave sirve para una sola migración: genera otra en el sitio de origen (o, si la migración anterior falló, vuelve a intentarlo dentro de ' . (int) ( self::STALE / 60 ) . ' minutos).', 409 );
	}
	/** Señal del destino al que está atada la clave (como mucho una escritura por minuto). */
	private static function touch( $id, $client ) {
		if ( '' === (string) $client ) return;
		try {
			self::change( function( &$state ) use ( $id, $client ) {
				if ( ! isset( $state['keys'][ $id ] ) || ! hash_equals( (string) $state['keys'][ $id ]['client'], (string) $client ) || time() - (int) ( $state['keys'][ $id ]['seen'] ?? 0 ) < 60 ) return false;
				$state['keys'][ $id ]['seen'] = time();
			} );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
		}
	}
	/**
	 * Una petición sin una clave válida de este sitio: suma a un contador agregado (desde, última vez e IP), sin entrar en
	 * el registro de usos ni cerrar nada.
	 */
	private static function reject() {
		// Como mucho una escritura por minuto, en su propia opción y sin el cerrojo de los trabajos (N4): una avalancha de
		// peticiones anónimas no escribe en la base de datos en cada una ni espera al cerrojo de la cola. No es exacto.
		try {
			$now = time();
			$seen = Digitalisimo_Backups_Keys::get( self::REJECTED, array() );
			$seen = is_array( $seen ) ? $seen : array();
			if ( $now - (int) ( $seen['last'] ?? 0 ) < 60 ) return;
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? substr( preg_replace( '/[^0-9A-Fa-f:.]/', '', (string) $_SERVER['REMOTE_ADDR'] ), 0, 45 ) : '';
			if ( $now - (int) ( $seen['since'] ?? 0 ) > 86400 ) $seen = array( 'count' => 0, 'since' => $now );
			Digitalisimo_Backups_Keys::put( self::REJECTED, array( 'count' => (int) ( $seen['count'] ?? 0 ) + 1, 'since' => (int) $seen['since'], 'last' => $now, 'ip' => $ip ) );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
		}
	}
	/** Anota una credencial equivocada de una clave que existe; con MAX_FAILURES, la bloquea. Devuelve si la bloqueó. */
	private static function fail( $id, $reason ) {
		$blocked = null;
		try {
			$blocked = self::change( function( &$state ) use ( $id, $reason ) {
				if ( ! isset( $state['keys'][ $id ] ) ) return null;
				self::log_into( $state, $id, 'auth_failed', $reason );
				$state['keys'][ $id ]['failures'] = (int) $state['keys'][ $id ]['failures'] + 1;
				if ( $state['keys'][ $id ]['failures'] < self::MAX_FAILURES || ! in_array( $state['keys'][ $id ]['state'], self::LIVE, true ) ) return null;
				$state['keys'][ $id ]['state'] = 'blocked';
				$state['keys'][ $id ]['closed_at'] = time();
				self::log_into( $state, $id, 'blocked' );
				return $state['keys'][ $id ];
			} );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
		}
		if ( is_array( $blocked ) ) self::retire( $blocked );
		return is_array( $blocked );
	}
	/** Cómo es este sitio, para que el destino compruebe que la migración es posible antes de pedir nada. */
	public static function source_hello( array $record, array $body ) {
		global $wpdb;
		$problems = array();
		try { Digitalisimo_Backups::migration_ready(); } catch ( Throwable $e ) { $problems[] = $e->getMessage(); }
		if ( is_multisite() && ! is_main_site() ) $problems[] = 'La API de migración de una red responde en su sitio principal.';
		self::log( $record['id'], 'hello', self::site_label( $body['site'] ?? '' ) );
		return array(
			'version' => 1,
			'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '',
			'instance' => self::instance(),
			'home' => home_url(),
			'siteurl' => site_url(),
			'multisite' => is_multisite(),
			'subdomain_install' => is_multisite() && function_exists( 'is_subdomain_install' ) && is_subdomain_install(),
			'network' => is_multisite() ? array( 'domain' => defined( 'DOMAIN_CURRENT_SITE' ) ? (string) DOMAIN_CURRENT_SITE : '', 'path' => defined( 'PATH_CURRENT_SITE' ) ? (string) PATH_CURRENT_SITE : '/' ) : null,
			'table_prefix' => (string) $wpdb->base_prefix,
			'wordpress_version' => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
			'php_version' => PHP_VERSION,
			'expires_at' => gmdate( 'c', (int) $record['expires'] ),
			'ready' => ! $problems,
			'problems' => $problems,
		);
	}
	/** Sitio que el destino declara (sólo su host y ruta, para el registro); nunca se usa para decidir nada. */
	private static function site_label( $site ) {
		$host = (string) wp_parse_url( (string) $site, PHP_URL_HOST );
		$path = (string) wp_parse_url( (string) $site, PHP_URL_PATH );
		return substr( preg_replace( '/[^A-Za-z0-9._:\/-]/', '', $host . rtrim( $path, '/' ) ), 0, 200 );
	}
	/**
	 * Ata la clave al trabajo del destino y encola el paquete con el motor de trabajos: un respaldo completo cifrado con la
	 * clave del paquete, que no entra en el historial ni en la retención. Repetirlo devuelve el mismo trabajo.
	 *
	 * Atar es atómico: con el cerrojo del almacén se comprueba otra vez que la clave siga libre (o sea de este trabajo, o
	 * esté abandonada) antes de escribir; dos destinos que piden el paquete a la vez nunca reciben los dos 200.
	 */
	public static function source_package( array $record, $client, array $body ) {
		$id = (string) $record['id'];
		$mine = hash( 'sha256', (string) $client );
		$site = self::site_label( $body['site'] ?? '' );
		$bound = self::change( function( &$state ) use ( $id, $mine, $site ) {
			$now = time();
			if ( ! isset( $state['keys'][ $id ] ) ) return array( 'error' => 'gone' );
			$row = $state['keys'][ $id ];
			if ( ! in_array( $row['state'], self::LIVE, true ) || self::lapsed( $row, $now ) ) return array( 'error' => 'gone' );
			$abandoned = '';
			if ( '' !== (string) $row['client'] && ! hash_equals( (string) $row['client'], $mine ) ) {
				if ( ! self::stale( $row, $now ) ) return array( 'error' => 'taken' );
				$abandoned = (string) $row['job'];
				$row['job'] = '';
				self::log_into( $state, $id, 'taken_over', $site );
			}
			$row['state'] = 'in_use';
			$row['client'] = $mine;
			$row['seen'] = $now;
			if ( '' !== $site ) $row['site'] = $site;
			$state['keys'][ $id ] = $row;
			return array( 'record' => $row, 'abandoned' => $abandoned );
		} );
		if ( 'taken' === ( $bound['error'] ?? '' ) ) {
			self::log( $id, 'other_client', 'package' );
			return self::in_use_error();
		}
		if ( isset( $bound['error'] ) ) return self::error( 'unauthorized', 'La clave de migración ya no sirve: genera otra en el sitio de origen.', 410 );
		$record = $bound['record'];
		if ( '' !== $bound['abandoned'] ) self::drop_package( $bound['abandoned'] );
		$job = Digitalisimo_Backups_Job_Store::valid_id( (string) $record['job'] ) ? Digitalisimo_Backups_Jobs::get( (string) $record['job'] ) : null;
		if ( $job && ( ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) || 'success' === $job['status'] ) ) return self::source_view( $record, $job );
		try {
			Digitalisimo_Backups::migration_ready();
			// Un respaldo completo del motor de trabajos con su propia clave (la del paquete), nunca la de los respaldos del
			// sitio: con `inline` la especificación no consulta la seguridad del sitio, y después deja de ser inline.
			$spec = Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'migration-' . $id, 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'migration', 'file' => 'migration-' . $id . '.dgbk', 'network' => is_multisite(), 'inline' => true ) );
			$spec['inline'] = false;
			$spec['security'] = array( 'encrypt' => true, 'key_id' => (string) $record['key_id'] );
			$spec['migration'] = array( 'role' => 'source', 'key' => $id );
			$queued = Digitalisimo_Backups_Jobs::enqueue( $spec );
		} catch ( Throwable $e ) {
			// La clave vuelve a quedar libre: este intento no la deja atada a nada.
			self::change( function( &$state ) use ( $id, $mine ) {
				if ( ! isset( $state['keys'][ $id ] ) || 'in_use' !== $state['keys'][ $id ]['state'] || ! hash_equals( (string) $state['keys'][ $id ]['client'], $mine ) || '' !== (string) $state['keys'][ $id ]['job'] ) return false;
				$state['keys'][ $id ]['state'] = 'pending';
				$state['keys'][ $id ]['client'] = '';
			} );
			return self::error( 'not_ready', 'El sitio de origen no puede preparar el paquete: ' . $e->getMessage(), 422 );
		}
		$job = $queued['job'];
		$record = self::change( function( &$state ) use ( $id, $mine, $job, $site ) {
			if ( ! isset( $state['keys'][ $id ] ) || 'in_use' !== $state['keys'][ $id ]['state'] || ! hash_equals( (string) $state['keys'][ $id ]['client'], $mine ) ) return false;
			$state['keys'][ $id ]['job'] = (string) $job['id'];
			self::log_into( $state, $id, 'package', $site );
			return $state['keys'][ $id ];
		} );
		if ( false === $record ) {
			// Revocada, caducada o retomada mientras se encolaba: el paquete recién encolado sobra.
			self::drop_package( (string) $job['id'] );
			return self::error( 'unauthorized', 'La clave de migración ya no sirve para este destino.', 410 );
		}
		Digitalisimo_Backups_Jobs::schedule();
		return self::source_view( $record, $job );
	}
	/** Cancela el trabajo de un paquete de migración y borra el paquete (si ya existe). */
	private static function drop_package( $job_id ) {
		if ( ! Digitalisimo_Backups_Job_Store::valid_id( (string) $job_id ) ) return;
		try {
			$job = Digitalisimo_Backups_Jobs::get( (string) $job_id );
			if ( $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) Digitalisimo_Backups_Jobs::cancel( (string) $job_id );
			$package = $job ? self::package_path( $job ) : '';
			if ( '' !== $package && is_file( $package ) && ! is_link( $package ) ) @unlink( $package );
		} catch ( Throwable $e ) {
			// La poda del almacén retira la carpeta del trabajo más adelante.
		}
	}
	/**
	 * El almacén del origen está ocupado por OTRA operación: el tick no pudo tomarlo y el paquete no está en marcha. Si
	 * quien lo tiene es el propio trabajo del paquete (otra petición o WP-Cron con su arrendamiento vivo, latiendo), no es
	 * «otra operación»: el paquete avanza, y el destino lo dice así (observación del ensayo E4).
	 */
	private static function source_busy( $tick_busy, $job ) {
		if ( ! $tick_busy ) return false;
		$lease = is_array( $job ) ? ( $job['lease'] ?? null ) : null;
		if ( is_array( $lease ) && time() - (int) ( $lease['heartbeat_at'] ?? 0 ) <= Digitalisimo_Backups_Jobs::STALE_AFTER ) return false;
		return true;
	}
	/** Hace avanzar la cola de este sitio (donde espera el paquete) con el presupuesto de una petición web y responde el estado del paquete. */
	public static function source_step( array $record ) {
		$job = Digitalisimo_Backups_Job_Store::valid_id( (string) $record['job'] ) ? Digitalisimo_Backups_Jobs::get( (string) $record['job'] ) : null;
		if ( ! $job ) return self::error( 'no_package', 'El sitio de origen no tiene un paquete en preparación para esta clave.', 409 );
		$busy = false;
		if ( ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) {
			if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
			$tick = Digitalisimo_Backups_Jobs::tick();
			$job = Digitalisimo_Backups_Jobs::get( (string) $job['id'] );
			$busy = self::source_busy( 'busy' === $tick['result'], $job );
		}
		$view = self::source_view( $record, $job );
		if ( $busy && ! $view['ready'] ) $view['state'] = 'busy';
		// Un paso puede durar tanto como el destino espera: al terminarlo, el destino sigue dando señales.
		self::touch( (string) $record['id'], (string) $record['client'] );
		if ( $view['ready'] ) {
			$id = (string) $record['id'];
			$size = self::megabytes( (int) $view['size'] );
			self::change( function( &$state ) use ( $id, $size ) {
				if ( ! isset( $state['keys'][ $id ] ) || ! empty( $state['keys'][ $id ]['announced'] ) ) return false;
				$state['keys'][ $id ]['announced'] = time();
				self::log_into( $state, $id, 'ready', $size );
			} );
		}
		return $view;
	}
	/** Estado público del paquete: nunca rutas del almacén ni claves. */
	private static function source_view( array $record, $job ) {
		$view = $job ? Digitalisimo_Backups_Jobs::view( $job ) : null;
		$package = $job ? self::package_path( $job ) : '';
		$ready = $job && 'success' === $job['status'] && '' !== $package && is_file( $package );
		$state = ! $job ? 'missing' : ( $ready ? 'ready' : ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ? ( 'success' === $job['status'] ? 'missing' : (string) $job['status'] ) : (string) $job['status'] ) );
		return array(
			'state' => $state,
			'ready' => $ready,
			'percent' => $view ? (int) $view['percent'] : 0,
			'phase' => $view ? (string) $view['phase_label'] : '',
			'message' => $view ? (string) ( $view['error'] ?: $view['message'] ) : '',
			'size' => $ready ? (int) filesize( $package ) : 0,
			'sha256' => $ready ? (string) ( $job['result']['sha256'] ?? '' ) : '',
			'key_id' => (string) $record['key_id'],
		);
	}
	private static function package_path( array $job ) {
		if ( 'source' !== ( $job['spec']['migration']['role'] ?? '' ) ) return '';
		return Digitalisimo_Backups_Job_Store::directory( (string) $job['id'] ) . '/' . basename( (string) $job['spec']['file'] );
	}
	/**
	 * Un fragmento del paquete (`Range: bytes=a-b`, como mucho MAX_CHUNK bytes): devuelve dónde está (ruta, posición y
	 * largo, que la API envía leyendo el archivo por trozos, con memoria constante), el tamaño total y el SHA-256 del
	 * paquete. Un rango fuera del paquete responde 416.
	 */
	public static function source_download( array $record, $range ) {
		$job = Digitalisimo_Backups_Job_Store::valid_id( (string) $record['job'] ) ? Digitalisimo_Backups_Jobs::get( (string) $record['job'] ) : null;
		$path = $job && 'success' === $job['status'] ? self::package_path( $job ) : '';
		if ( '' === $path || is_link( $path ) || ! is_file( $path ) ) return self::error( 'no_package', 'El paquete de esta clave no está listo en el sitio de origen.', 409 );
		clearstatcache( true, $path );
		$total = (int) filesize( $path );
		$max = max( 65536, min( self::MAX_CHUNK, (int) self::filter( 'digitalisimo_backups_migration_chunk', self::CHUNK ) ) );
		$start = 0;
		$end = min( $total, $max ) - 1;
		if ( '' !== (string) $range ) {
			if ( ! preg_match( '/^bytes=(\d+)-(\d*)$/D', trim( (string) $range ), $m ) ) return self::error( 'range', 'El rango pedido no es válido.', 416 );
			$start = (int) $m[1];
			$end = '' === $m[2] ? $total - 1 : (int) $m[2];
			$end = min( $end, $total - 1, $start + $max - 1 );
			if ( $start >= $total || $end < $start ) return self::error( 'range', 'El rango pedido está fuera del paquete.', 416 );
		}
		if ( ! is_readable( $path ) ) return self::error( 'read', 'No se pudo leer el paquete en el sitio de origen.', 500 );
		if ( 0 === $start ) self::log( $record['id'], 'download' );
		// El último fragmento: el paquete quedó entregado entero (la clave ya no se puede retomar desde otro destino).
		if ( $end === $total - 1 && empty( $record['delivered'] ) ) {
			$id = (string) $record['id'];
			self::change( function( &$state ) use ( $id ) {
				if ( ! isset( $state['keys'][ $id ] ) || ! empty( $state['keys'][ $id ]['delivered'] ) ) return false;
				$state['keys'][ $id ]['delivered'] = time();
			} );
		}
		return array( 'path' => $path, 'start' => $start, 'end' => $end, 'total' => $total, 'sha256' => (string) ( $job['result']['sha256'] ?? '' ) );
	}
	/**
	 * Envía un tramo de un archivo por trozos de 64 KiB: la memoria no depende del tamaño del fragmento (un hosting de
	 * 64 MB con WordPress y sus plugins cargados no tiene margen para guardar varios MiB en una cadena).
	 */
	public static function emit_range( $path, $start, $length ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) return false;
		$sent = 0;
		try {
			if ( 0 !== fseek( $handle, (int) $start ) ) return false;
			while ( $sent < $length && ! feof( $handle ) ) {
				$piece = fread( $handle, (int) min( 65536, $length - $sent ) );
				if ( false === $piece || '' === $piece ) break;
				echo $piece; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bytes del paquete cifrado.
				$sent += strlen( $piece );
				if ( function_exists( 'flush' ) && 0 === ob_get_level() ) flush();
			}
		} finally {
			fclose( $handle );
		}
		return $sent === (int) $length;
	}
	/** La migración terminó en el destino: la clave queda usada (no sirve más) y su paquete se borra. */
	public static function source_finish( array $record, array $body ) {
		$id = (string) $record['id'];
		$closed = self::change( function( &$state ) use ( $id ) {
			if ( ! isset( $state['keys'][ $id ] ) || ! in_array( $state['keys'][ $id ]['state'], self::LIVE, true ) ) return false;
			$state['keys'][ $id ]['state'] = 'used';
			$state['keys'][ $id ]['used_at'] = time();
			$state['keys'][ $id ]['closed_at'] = time();
			self::log_into( $state, $id, 'used' );
			return $state['keys'][ $id ];
		} );
		if ( is_array( $closed ) ) self::retire( $closed );
		return array( 'state' => 'used' );
	}
	/** El destino abandonó antes de restaurar: la clave vuelve a servir (hasta caducar) y su paquete se borra. */
	public static function source_release( array $record ) {
		$id = (string) $record['id'];
		$released = self::change( function( &$state ) use ( $id ) {
			if ( ! isset( $state['keys'][ $id ] ) || 'in_use' !== $state['keys'][ $id ]['state'] ) return false;
			$before = $state['keys'][ $id ];
			$state['keys'][ $id ]['state'] = 'pending';
			$state['keys'][ $id ]['client'] = '';
			$state['keys'][ $id ]['job'] = '';
			unset( $state['keys'][ $id ]['delivered'] );
			self::log_into( $state, $id, 'released' );
			return $before;
		} );
		if ( is_array( $released ) ) {
			try {
				$job = Digitalisimo_Backups_Job_Store::valid_id( (string) $released['job'] ) ? Digitalisimo_Backups_Jobs::get( (string) $released['job'] ) : null;
				if ( $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) Digitalisimo_Backups_Jobs::cancel( (string) $job['id'] );
				$package = $job ? self::package_path( $job ) : '';
				if ( '' !== $package && is_file( $package ) ) @unlink( $package );
			} catch ( Throwable $e ) {
				// La poda del almacén la retira después.
			}
		}
		return array( 'state' => is_array( $released ) ? 'pending' : 'unchanged' );
	}

	/* ------------------------------------------------------------------ Destino */

	/**
	 * Lee una clave pegada: prefijo, base64url, JSON {v, u, i, s}. Los saltos de línea y espacios de un pegado se
	 * ignoran. Devuelve array( url, id, secret ); el mensaje de error nunca repite la clave.
	 */
	public static function parse_key( $key ) {
		$key = preg_replace( '/\s+/', '', (string) $key );
		$invalid = 'La clave de migración no es válida: cópiala completa desde Backups → Herramientas → Migrar del sitio de origen.';
		if ( 0 !== strpos( $key, self::KEY_PREFIX ) || strlen( $key ) > 4096 ) throw new InvalidArgumentException( $invalid );
		$json = Digitalisimo_Backups_Keys::b64url_decode( substr( $key, strlen( self::KEY_PREFIX ) ) );
		$data = false === $json ? null : json_decode( $json, true );
		$secret = is_array( $data ) ? Digitalisimo_Backups_Keys::b64url_decode( $data['s'] ?? null, 32 ) : false;
		$url = is_array( $data ) ? (string) ( $data['u'] ?? '' ) : '';
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( 1 !== ( $data['v'] ?? null ) || false === $secret || ! self::valid_id( $data['i'] ?? null ) || ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === (string) wp_parse_url( $url, PHP_URL_HOST ) ) throw new InvalidArgumentException( $invalid );
		return array( 'url' => $url, 'id' => (string) $data['i'], 'secret' => $secret );
	}
	/** Por qué este sitio no puede recibir una migración ('' si puede). La copia previa es un respaldo completo. */
	public static function pull_problem() {
		if ( is_multisite() && ! is_main_site() ) return 'En una red, la migración se inicia desde el sitio principal o la administración de red: reemplaza la red entera.';
		if ( ! Digitalisimo_Backups_Sodium::available() ) return 'Este servidor no tiene las funciones de libsodium para descifrar el paquete (' . implode( ', ', Digitalisimo_Backups_Sodium::missing() ) . ').';
		if ( false === Digitalisimo_Backups_Keys::wrap_key() ) return 'Para migrar, wp-config.php de este sitio debe definir AUTH_KEY y SECURE_AUTH_KEY propias (al menos 16 caracteres, no el texto de ejemplo de WordPress).';
		try { Digitalisimo_Backups::migration_ready(); } catch ( Throwable $e ) { return $e->getMessage(); }
		return '';
	}
	/**
	 * Comprueba la clave con el origen (se presenta y compara los dos sitios) y encola la migración. Todo lo que puede
	 * impedirla se dice aquí, antes de encolar nada; la clave queda envuelta con las claves de wp-config.php de este
	 * sitio en el almacén privado mientras dura el trabajo.
	 */
	public static function enqueue_pull( $key, $via, array $options = array() ) {
		$parsed = self::parse_key( $key );
		$secret = $parsed['secret'];
		$auth = self::auth_of( $secret );
		$root = self::root_of( $secret );
		Digitalisimo_Backups_Sodium::memzero( $secret );
		$token = bin2hex( random_bytes( 16 ) );
		$file = self::directory() . '/pull-' . $token . '.key';
		try {
			$problem = self::pull_problem();
			if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
			$problem = self::target_problem( $parsed['url'] );
			if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
			self::sweep_pulls();
			$migration = array( 'role' => 'destination', 'url' => $parsed['url'], 'id' => $parsed['id'], 'key_id' => Digitalisimo_Backups_Keys::key_id( $root ), 'source_host' => self::host_label( $parsed['url'] ), 'secret' => $token );
			self::seal_file( $file, $auth . $root );
			$hello = self::call( $migration, $auth, '', 'hello', 'POST', array( 'site' => home_url() ), 30 );
			self::compatible( $hello );
			$migration['source'] = self::source_summary( $hello );
			$migration['source_host'] = self::host_label( (string) $hello['home'] ) ?: $migration['source_host'];
			$spec = self::pull_spec( $migration, $via );
			// Quien migra sigue entrando (encendido por defecto) y puede seguir el trabajo con su ficha de lectura.
			$spec['keep_access'] = ! array_key_exists( 'keep_access', $options ) || ! empty( $options['keep_access'] );
			$reader = Digitalisimo_Backups_Restore_Access::new_reader();
			$spec['reader'] = array( 'hash' => $reader['hash'], 'issued' => $reader['issued'] );
			$queued = Digitalisimo_Backups_Jobs::enqueue( $spec );
			if ( empty( $queued['duplicate'] ) ) $queued['reader_token'] = $reader['token'];
			if ( ! empty( $queued['duplicate'] ) ) {
				@unlink( $file );
				return $queued;
			}
			return $queued;
		} catch ( Throwable $e ) {
			@unlink( $file );
			throw $e;
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $auth );
			Digitalisimo_Backups_Sodium::memzero( $root );
		}
	}
	/**
	 * Por qué el destino no puede pedir nada a $url ('' si puede): HTTPS (o HTTP con el laboratorio) y un host público,
	 * nunca loopback, red privada, enlace local (los metadatos de la nube están en 169.254.169.254), CGNAT, multidifusión
	 * ni reservado, tampoco si un nombre resuelve a una de ellas. El laboratorio («Permitir HTTP y direcciones internas»)
	 * permite las dos cosas, para un ensayo en una red propia o dos sitios del mismo servidor.
	 */
	public static function target_problem( $url ) {
		$scheme = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_SCHEME ) );
		$host = strtolower( trim( (string) wp_parse_url( (string) $url, PHP_URL_HOST ), '[]' ) );
		if ( '' === $host || ! in_array( $scheme, array( 'http', 'https' ), true ) ) return 'La dirección del sitio de origen no es válida.';
		$lab = self::allow_http();
		if ( 'https' !== $scheme && ! $lab ) return 'La clave apunta a un sitio sin HTTPS (' . $host . '). Activa HTTPS en el origen o, sólo en un laboratorio, «Permitir HTTP y direcciones internas» en Backups → Herramientas → Migrar de los dos sitios.';
		if ( $lab ) return '';
		$internal = 'localhost' === $host || '.localhost' === substr( $host, -10 ) || false !== strpos( $host, '%' ) ? $host : '';
		$addresses = '' === $internal ? self::addresses( $host ) : array();
		// Un nombre que no resuelve (o un DNS que no contesta) no se da por público (N5).
		if ( '' === $internal && ! $addresses ) return 'No se pudo resolver la dirección del sitio de origen (' . $host . '): el destino sólo se conecta a un sitio cuya dirección pública puede comprobar.';
		if ( '' === $internal ) foreach ( $addresses as $ip ) if ( ! self::public_ip( $ip ) ) { $internal = $host === $ip ? $ip : $host . ' → ' . $ip; break; }
		if ( '' !== $internal ) return 'La clave apunta a una dirección interna (' . $internal . '): loopback, red privada, enlace local o metadatos de la nube. El destino sólo se conecta a sitios públicos; en un laboratorio, activa «Permitir HTTP y direcciones internas» en Backups → Herramientas → Migrar.';
		return '';
	}
	/** Direcciones IP de un host (la IP misma si lo es; IPv4 y, si se puede, IPv6). Sin resolver, ninguna. */
	/**
	 * La dirección comprobada para un host, en la forma de CURLOPT_RESOLVE (`host:puerto:ip`), o '' (una IP, el
	 * laboratorio o sin resolver): la conexión va a esa IP y no a lo que resuelva cURL otra vez (DNS rebinding, N5).
	 */
	private static function pin_for( $url ) {
		if ( self::allow_http() ) return '';
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		if ( '' === $host || false !== @inet_pton( trim( $host, '[]' ) ) ) return '';
		$addresses = self::addresses( $host );
		if ( ! $addresses ) return '';
		$port = (int) ( wp_parse_url( (string) $url, PHP_URL_PORT ) ?: ( 'http' === strtolower( (string) wp_parse_url( (string) $url, PHP_URL_SCHEME ) ) ? 80 : 443 ) );
		$ip = (string) $addresses[0];
		return $host . ':' . $port . ':' . ( false !== strpos( $ip, ':' ) ? '[' . $ip . ']' : $ip );
	}
	/** `http_api_curl`: una petición de la migración se conecta a la IP que se comprobó (la que lleva `digitalisimo_pin`). */
	public static function pin_curl( $handle, $request = array(), $url = '' ) {
		if ( ! is_array( $request ) || empty( $request['digitalisimo_pin'] ) || ! defined( 'CURLOPT_RESOLVE' ) || ! function_exists( 'curl_setopt' ) ) return;
		@curl_setopt( $handle, CURLOPT_RESOLVE, array( (string) $request['digitalisimo_pin'] ) );
	}
	private static function addresses( $host ) {
		if ( false !== @inet_pton( $host ) ) return array( $host );
		if ( self::$resolver ) return (array) call_user_func( self::$resolver, $host );
		static $cache = array();
		if ( ! isset( $cache[ $host ] ) ) {
			$ips = function_exists( 'gethostbynamel' ) ? @gethostbynamel( $host ) : false;
			$ips = is_array( $ips ) ? $ips : array();
			if ( function_exists( 'dns_get_record' ) && defined( 'DNS_AAAA' ) ) {
				$records = @dns_get_record( $host, DNS_AAAA );
				foreach ( is_array( $records ) ? $records : array() as $record ) if ( ! empty( $record['ipv6'] ) ) $ips[] = (string) $record['ipv6'];
			}
			$cache[ $host ] = $ips;
		}
		return $cache[ $host ];
	}
	/** Si una IP es pública (ni loopback, privada, enlace local, CGNAT, multidifusión ni reservada). */
	public static function public_ip( $ip ) {
		$bin = @inet_pton( (string) $ip );
		if ( false === $bin ) return false;
		if ( 16 === strlen( $bin ) ) {
			// IPv4 mapeada (::ffff:a.b.c.d), NAT64 (64:ff9b::/96) y 6to4 (2002::/16) se juzgan por su IPv4.
			if ( self::in_cidr( $bin, '::ffff:0:0/96' ) || self::in_cidr( $bin, '64:ff9b::/96' ) ) return self::public_ip( inet_ntop( substr( $bin, 12 ) ) );
			if ( self::in_cidr( $bin, '2002::/16' ) ) return self::public_ip( inet_ntop( substr( $bin, 2, 4 ) ) );
			foreach ( array( '::/127', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8', '100::/64' ) as $range ) if ( self::in_cidr( $bin, $range ) ) return false;
			return true;
		}
		foreach ( array( '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.168.0.0/16', '198.18.0.0/15', '224.0.0.0/4', '240.0.0.0/4' ) as $range ) if ( self::in_cidr( $bin, $range ) ) return false;
		return true;
	}
	private static function in_cidr( $bin, $cidr ) {
		list( $net, $bits ) = explode( '/', $cidr );
		$net = inet_pton( $net );
		if ( strlen( $net ) !== strlen( $bin ) ) return false;
		$bits = (int) $bits;
		$bytes = intdiv( $bits, 8 );
		if ( $bytes > 0 && 0 !== strncmp( $bin, $net, $bytes ) ) return false;
		$rest = $bits % 8;
		if ( 0 === $rest ) return true;
		$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
		return ( ord( $bin[ $bytes ] ) & $mask ) === ( ord( $net[ $bytes ] ) & $mask );
	}
	/** Dirección absoluta de una redirección (`Location` absoluto, de protocolo relativo, de raíz o relativo). */
	private static function absolute_url( $location, $base ) {
		if ( preg_match( '#^https?://#i', $location ) ) return $location;
		$parts = wp_parse_url( $base );
		$origin = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
		if ( 0 === strpos( $location, '//' ) ) return ( $parts['scheme'] ?? 'https' ) . ':' . $location;
		if ( 0 === strpos( $location, '/' ) ) return $origin . $location;
		$path = (string) ( $parts['path'] ?? '/' );
		return $origin . substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) . $location;
	}
	/**
	 * Claves de destino que quedaron en el almacén sin trabajo (el proceso murió durante la presentación, antes de
	 * encolar): se borran pasada una hora si ningún trabajo activo las usa.
	 */
	private static function sweep_pulls() {
		$files = @glob( self::directory() . '/pull-*.key' );
		if ( ! $files ) return;
		$live = array();
		try {
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( '' !== (string) ( $job['spec']['migration']['secret'] ?? '' ) ) $live[ (string) $job['spec']['migration']['secret'] ] = true;
		} catch ( Throwable $e ) {
			return;
		}
		foreach ( $files as $file ) {
			if ( ! preg_match( '/pull-([0-9a-f]{32})\.key$/D', $file, $m ) || isset( $live[ $m[1] ] ) ) continue;
			if ( time() - (int) @filemtime( $file ) > 3600 ) @unlink( $file );
		}
	}
	/** Host (con su puerto, si lo tiene) para los mensajes: dos sitios en la misma máquina se distinguen. */
	private static function host_label( $url ) {
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		$port = wp_parse_url( (string) $url, PHP_URL_PORT );
		return '' === $host ? '' : $host . ( $port ? ':' . (int) $port : '' );
	}
	private static function pull_spec( array $migration, $via ) {
		$now_scope = is_multisite() ? 'network' : 'installation';
		return array(
			'kind' => 'migrate',
			'scope' => $now_scope,
			'type' => 'migration',
			'destination' => 'local',
			'policy' => 'migrate',
			'retain' => 1,
			'retention' => 'none',
			'site_id' => (int) get_current_blog_id(),
			'file' => '',
			'inline' => false,
			'security' => array( 'encrypt' => false, 'key_id' => '' ),
			'migration' => $migration,
			// A quién avisar: los ajustes de avisos de este sitio antes de migrar (el SQL del origen trae los suyos).
			'notify' => Digitalisimo_Backups_Notify::snapshot(),
			'origin' => array( 'via' => (string) $via, 'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0, 'blog_id' => (int) get_current_blog_id(), 'context' => is_multisite() ? 'network' : 'site' ),
			'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
		);
	}
	private static function source_summary( array $hello ) {
		return array( 'home' => (string) ( $hello['home'] ?? '' ), 'siteurl' => (string) ( $hello['siteurl'] ?? '' ), 'multisite' => ! empty( $hello['multisite'] ), 'subdomain_install' => ! empty( $hello['subdomain_install'] ), 'table_prefix' => (string) ( $hello['table_prefix'] ?? '' ), 'wordpress_version' => (string) ( $hello['wordpress_version'] ?? '' ), 'plugin_version' => (string) ( $hello['plugin_version'] ?? '' ) );
	}
	/**
	 * Individual → individual y red → red del mismo tipo. Si el origen es una red y este WordPress no la declara en
	 * wp-config.php, el mensaje dice qué definir; nada se descarga ni se toca antes.
	 */
	public static function compatible( array $hello ) {
		if ( 1 !== ( $hello['version'] ?? null ) || ! is_string( $hello['instance'] ?? null ) || ! preg_match( '/^[0-9a-f]{16}$/D', $hello['instance'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La dirección de la clave no responde como la API de migración de DIGITALÍSIMO Backups.' );
		if ( empty( $hello['ready'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio de origen no puede preparar el paquete: ' . implode( ' ', array_map( 'strval', (array) ( $hello['problems'] ?? array() ) ) ) );
		if ( (string) ( $hello['instance'] ?? '' ) === self::instance() ) throw new Digitalisimo_Backups_Permanent_Error( 'Esta clave es de este mismo sitio: pégala en el sitio de destino.' );
		$source_network = ! empty( $hello['multisite'] );
		$source_type = ! empty( $hello['subdomain_install'] ) ? 'subdominios' : 'subdirectorios';
		if ( $source_network && ! is_multisite() ) throw new Digitalisimo_Backups_Permanent_Error( 'El origen es una red de WordPress (por ' . $source_type . ') y el wp-config.php de este sitio no declara una red. Conviértelo antes en una red del mismo tipo: define WP_ALLOW_MULTISITE, crea la red en Herramientas → Configuración de la red y añade a wp-config.php MULTISITE, SUBDOMAIN_INSTALL (' . ( ! empty( $hello['subdomain_install'] ) ? 'true' : 'false' ) . '), DOMAIN_CURRENT_SITE y PATH_CURRENT_SITE con la dirección de este sitio. Después vuelve a pegar la clave.' );
		if ( ! $source_network && is_multisite() ) throw new Digitalisimo_Backups_Permanent_Error( 'El origen es un WordPress individual y este sitio es una red: una migración sólo va de sitio individual a sitio individual o de red a red.' );
		if ( $source_network ) {
			$here = function_exists( 'is_subdomain_install' ) && is_subdomain_install();
			if ( $here !== ! empty( $hello['subdomain_install'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El origen es una red por ' . $source_type . ' y esta red es por ' . ( $here ? 'subdominios' : 'subdirectorios' ) . ': las dos deben ser del mismo tipo (SUBDOMAIN_INSTALL en wp-config.php).' );
		}
		return true;
	}
	/** La credencial y la clave del paquete de un trabajo, desenvueltas del almacén. */
	private static function pull_secrets( array $job ) {
		$token = (string) ( $job['spec']['migration']['secret'] ?? '' );
		$plain = preg_match( '/^[0-9a-f]{32}$/D', $token ) ? self::open_file( self::directory() . '/pull-' . $token . '.key' ) : false;
		if ( ! is_string( $plain ) || 64 !== strlen( $plain ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Este sitio ya no puede abrir la clave de la migración (cambiaron AUTH_KEY o SECURE_AUTH_KEY de wp-config.php o se borró del almacén). Vuelve a pegar la clave.' );
		return array( substr( $plain, 0, 32 ), substr( $plain, 32 ) );
	}
	private static function forget_secrets( array $job ) {
		$token = (string) ( $job['spec']['migration']['secret'] ?? '' );
		if ( preg_match( '/^[0-9a-f]{32}$/D', $token ) ) @unlink( self::directory() . '/pull-' . $token . '.key' );
	}
	/** URL de una acción de la API del origen, con enlaces permanentes o con `?rest_route=`. */
	public static function endpoint( $base, $action ) {
		$query = (string) wp_parse_url( $base, PHP_URL_QUERY );
		if ( '' !== $query ) {
			parse_str( $query, $args );
			if ( isset( $args['rest_route'] ) ) {
				$route = rtrim( (string) $args['rest_route'], '/' ) . '/' . $action;
				return preg_replace( '/([?&])rest_route=[^&]*/', '$1rest_route=' . str_replace( '%2F', '/', rawurlencode( $route ) ), $base );
			}
		}
		return rtrim( $base, '/' ) . '/' . $action;
	}
	/**
	 * Una petición al origen. La credencial va en la cabecera, nunca en la URL ni en el cuerpo. 401/403/404/409/410 y
	 * 422 son permanentes (repetir no lo arregla); 429 y los fallos de red o del servidor, transitorios.
	 */
	private static function call( array $migration, $auth, $client, $action, $method, $body = array(), $timeout = 60, array $headers = array(), $file = '', $limit = self::RESPONSE_MAX ) {
		$args = array(
			'method' => $method,
			'timeout' => $timeout,
			'redirection' => 0,
			'sslverify' => true,
			'user-agent' => 'DIGITALISIMO-Backups/' . ( defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '' ) . ' migration',
			'headers' => array( self::HEADER => (string) $migration['id'] . '.' . Digitalisimo_Backups_Keys::b64url_encode( $auth ), 'Accept' => 'application/json' ) + ( '' !== $client ? array( self::CLIENT_HEADER => (string) $client ) : array() ) + $headers,
		);
		if ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( (object) $body );
		}
		// Un fragmento del paquete va directo a un archivo: la memoria no depende de su tamaño. Ninguna respuesta pasa de
		// su tope (un fragmento, su largo pedido): un origen o un intermediario que no termina no llena el disco.
		if ( '' !== $file ) {
			$args['stream'] = true;
			$args['filename'] = $file;
		}
		$args['limit_response_size'] = max( 1, (int) $limit );
		// Cada salto de una redirección se valida como la dirección de la clave (HTTPS y host público) antes de pedirlo.
		$url = self::endpoint( (string) $migration['url'], $action );
		for ( $hop = 0; ; $hop++ ) {
			$problem = self::target_problem( $url );
			if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
			$args['digitalisimo_pin'] = self::pin_for( $url );
			$response = self::$transport ? call_user_func( self::$transport, $method, $url, $args ) : wp_remote_request( $url, $args );
			$host = self::host_label( $url );
			if ( is_wp_error( $response ) ) throw new Exception( 'No se pudo conectar con el sitio de origen (' . $host . '): ' . $response->get_error_message() );
			$code = (int) wp_remote_retrieve_response_code( $response );
			$location = in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ? trim( (string) wp_remote_retrieve_header( $response, 'location' ) ) : '';
			if ( '' === $location ) break;
			if ( $hop >= self::REDIRECTS ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio de origen (' . $host . ') redirige más de ' . self::REDIRECTS . ' veces: revisa su dirección.' );
			$next = self::absolute_url( $location, $url );
			// Sólo dentro del mismo host: la credencial de la clave nunca viaja a otro (N5). Una clave nueva del origen lleva
			// su dirección de ahora.
			if ( strtolower( (string) wp_parse_url( $next, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio de origen (' . $host . ') redirige a otro host (' . self::host_label( $next ) . '): la credencial de la clave no se envía fuera de su host. Genera la clave en el sitio de origen con su dirección definitiva.' );
			$url = $next;
			if ( '' !== $file ) @unlink( $file );
		}
		if ( 'download' === $action && in_array( $code, array( 200, 206 ), true ) ) return array( 'code' => $code, 'file' => $file, 'range' => (string) wp_remote_retrieve_header( $response, 'content-range' ), 'sha256' => strtolower( (string) wp_remote_retrieve_header( $response, 'x-digitalisimo-package-sha256' ) ) );
		// Con la respuesta en un archivo, un error trae su JSON allí (pequeño).
		$raw = '' !== $file ? (string) @file_get_contents( $file, false, null, 0, 65536 ) : (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		// Sólo los errores de la API de migración se muestran: el cuerpo de cualquier otra respuesta (otro servicio, un
		// proxy, una página de error) nunca llega al administrador.
		$ours = is_array( $data ) && is_string( $data['code'] ?? null ) && 0 === strpos( $data['code'], 'digitalisimo_backups_migration_' ) && is_string( $data['message'] ?? null );
		$message = $ours ? substr( $data['message'], 0, 500 ) : 'HTTP ' . $code;
		if ( $code >= 200 && $code < 300 && is_array( $data ) ) return $data;
		if ( 429 === $code ) throw new Exception( 'El sitio de origen pide esperar: ' . $message );
		if ( in_array( $code, array( 400, 401, 403, 404, 409, 410, 416, 422 ), true ) ) {
			$hint = 404 === $code ? ' (¿tiene el origen una versión de DIGITALÍSIMO Backups con migración y la API REST abierta?)' : '';
			throw new Digitalisimo_Backups_Permanent_Error( 'El sitio de origen (' . $host . ') rechazó la migración: ' . $message . $hint );
		}
		throw new Exception( 'El sitio de origen (' . $host . ') respondió ' . ( $code ?: 'sin código' ) . ': ' . $message );
	}

	/* ------------------------------------------------------------------ Fases del trabajo `migrate` */

	public static function run_phase( $phase, array $job ) {
		switch ( $phase ) {
			case 'preflight': return self::connect( $job );
			case 'remote': return self::remote( $job );
			case 'download': return self::download( $job );
			case 'check': return self::check( $job );
			case 'restore': return self::restore( $job );
			case 'close': return self::close( $job );
		}
		throw new Digitalisimo_Backups_Permanent_Error( 'La migración está en una fase desconocida.' );
	}
	private static function work_dir( array $job ) {
		$dir = Digitalisimo_Backups_Job_Store::directory( (string) $job['id'] ) . '/download';
		if ( is_link( $dir ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de descarga de la migración no puede ser un enlace simbólico.' );
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) throw new Exception( 'No se pudo preparar la carpeta de descarga de la migración.' );
		return $dir;
	}
	/** Vuelve a presentarse (el origen pudo cambiar desde que se encoló) y comprueba otra vez que los sitios son compatibles. */
	private static function connect( array $job ) {
		if ( Digitalisimo_Backups_Job_Store::JOB_FORMAT !== (int) ( $job['spec']['engine']['job_format'] ?? 0 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La migración se encoló con otra versión del motor; vuelve a pegar la clave.' );
		$problem = self::pull_problem();
		if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		list( $auth ) = self::pull_secrets( $job );
		$hello = self::call( $job['spec']['migration'], $auth, (string) $job['id'], 'hello', 'POST', array( 'site' => home_url() ), 30 );
		self::compatible( $hello );
		Digitalisimo_Backups_Sodium::memzero( $auth );
		return array( 'next' => 'remote', 'checkpoint' => array( 'at' => time() ), 'message' => 'Conectado con ' . $job['spec']['migration']['source_host'] . '; el origen prepara el paquete…' );
	}
	/** El origen crea el paquete con su motor de trabajos; cada llamada lo hace avanzar y el destino espera entre llamadas. */
	private static function remote( array $job ) {
		list( $auth ) = self::pull_secrets( $job );
		$migration = $job['spec']['migration'];
		$body = array( 'site' => home_url() );
		$requested = ! empty( $job['checkpoint']['remote']['requested'] );
		try {
			if ( ! $requested ) self::call( $migration, $auth, (string) $job['id'], 'package', 'POST', $body, 60 );
			$requested = true;
			$view = self::call( $migration, $auth, (string) $job['id'], 'step', 'POST', $body, (int) self::filter( 'digitalisimo_backups_migration_step_timeout', 600 ) );
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			throw $e;
		} catch ( Exception $e ) {
			// Un tiempo agotado no es un fallo del origen: sigue trabajando en su paso (o atiende otra petición, con pocos
			// procesos de PHP). Se espera sin gastar un intento, hasta TIMEOUTS seguidos.
			$timeouts = (int) ( $job['checkpoint']['remote']['timeouts'] ?? 0 ) + 1;
			if ( ! preg_match( '/timed out|cURL error 28|tiempo de espera/i', $e->getMessage() ) || $timeouts > self::TIMEOUTS ) throw $e;
			Digitalisimo_Backups_Jobs::progress( (int) ( $job['checkpoint']['remote']['percent'] ?? 3 ), 'El origen sigue preparando el paquete (la petición superó el tiempo de espera); se vuelve a preguntar…', true );
			return array( 'next' => 'remote', 'checkpoint' => array( 'requested' => $requested, 'percent' => (int) ( $job['checkpoint']['remote']['percent'] ?? 3 ), 'timeouts' => $timeouts ), 'wait' => 30, 'message' => 'El origen sigue preparando el paquete; se vuelve a preguntar en 30 s.' );
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $auth );
		}
		if ( ! is_string( $view['state'] ?? null ) || ! is_string( $view['key_id'] ?? null ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La dirección de la clave no responde como la API de migración de DIGITALÍSIMO Backups.' );
		$state = (string) ( $view['state'] ?? '' );
		$percent = 3 + (int) floor( 0.37 * max( 0, min( 100, (int) ( $view['percent'] ?? 0 ) ) ) );
		if ( in_array( $state, array( 'failed', 'cancelled', 'missing' ), true ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio de origen no pudo preparar el paquete: ' . (string) ( $view['message'] ?? $state ) );
		if ( ! empty( $view['ready'] ) ) {
			$size = (int) ( $view['size'] ?? 0 );
			$sha = strtolower( (string) ( $view['sha256'] ?? '' ) );
			if ( $size <= 0 || ! preg_match( '/^[0-9a-f]{64}$/D', $sha ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El origen anunció un paquete sin tamaño o sin SHA-256.' );
			if ( ! hash_equals( (string) $migration['key_id'], (string) ( $view['key_id'] ?? '' ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El origen preparó el paquete con otra clave: no corresponde a la clave pegada.' );
			return array( 'next' => 'download', 'checkpoint' => array( 'requested' => true, 'size' => $size, 'sha256' => $sha ), 'message' => 'Paquete cifrado listo en el origen (' . self::megabytes( $size ) . '); descargándolo…' );
		}
		$before = (int) ( $job['checkpoint']['remote']['percent'] ?? 0 );
		$moved = $percent > $before;
		$message = 'En el origen: ' . ( '' !== (string) ( $view['phase'] ?? '' ) ? $view['phase'] . ' · ' : '' ) . ( 'busy' === $state ? 'otra operación ocupa el almacén del origen; se espera' : (string) ( $view['message'] ?? '' ) );
		Digitalisimo_Backups_Jobs::progress( $percent, $message, true );
		return array(
			'next' => 'remote',
			'checkpoint' => array( 'requested' => true, 'percent' => $percent ),
			'progress_made' => $moved,
			'wait' => 'busy' === $state || ! $moved ? (int) self::filter( 'digitalisimo_backups_migration_poll', 5 ) : 0,
			'message' => $message,
		);
	}
	/**
	 * Descarga por fragmentos con `Range`, siempre desde lo que ya está en disco: una muerte o un corte de red reanudan
	 * donde quedó. Un fragmento que no es exactamente el pedido no se escribe. Al completar, el SHA-256 debe ser el que
	 * anunció el origen; si no, se descarta y se descarga otra vez.
	 */
	private static function download( array $job ) {
		$remote = (array) ( $job['checkpoint']['remote'] ?? array() );
		$size = (int) ( $remote['size'] ?? 0 );
		$sha = (string) ( $remote['sha256'] ?? '' );
		if ( $size <= 0 ) return array( 'next' => 'remote', 'checkpoint' => array( 'missing' => time() ), 'message' => 'Falta el tamaño del paquete; se pregunta otra vez al origen…' );
		$dir = self::work_dir( $job );
		$part = $dir . '/package.dgbk.download';
		$final = $dir . '/package.dgbk';
		$tmp = $dir . '/piece.tmp';
		if ( is_link( $part ) || is_link( $final ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La descarga de la migración no puede ser un enlace simbólico.' );
		if ( is_file( $final ) ) return array( 'next' => 'check', 'checkpoint' => array( 'size' => $size, 'at' => time() ), 'message' => 'Paquete descargado; comprobándolo…' );
		clearstatcache( true, $part );
		$offset = is_file( $part ) ? (int) filesize( $part ) : 0;
		if ( $offset > $size ) { @unlink( $part ); $offset = 0; }
		if ( 0 === $offset ) {
			$free = function_exists( 'disk_free_space' ) ? @disk_free_space( $dir ) : false;
			$needed = (int) ceil( $size * 2.1 ) + 64 * 1048576;
			if ( is_numeric( $free ) && $free < $needed ) throw new Digitalisimo_Backups_Permanent_Error( 'No hay espacio en el almacén privado para descargar y descifrar el paquete: se necesitan ' . self::megabytes( $needed ) . ' y hay ' . self::megabytes( (int) $free ) . '.' );
		}
		list( $auth ) = self::pull_secrets( $job );
		$chunk = max( 65536, min( self::MAX_CHUNK, (int) self::filter( 'digitalisimo_backups_migration_chunk', self::CHUNK ) ) );
		$deadline = microtime( true ) + max( 1, min( 20, (float) self::filter( 'digitalisimo_backups_migration_slice', 15 ) ) );
		$start = $offset;
		$failure = null;
		try {
			while ( $offset < $size ) {
				$end = min( $size, $offset + $chunk ) - 1;
				$piece = null;
				for ( $try = 0; $try < 2 && null === $piece; $try++ ) {
					try {
						$piece = self::fetch_piece( $job, $auth, $offset, $end, $size, $sha, $tmp );
					} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
						throw $e;
					} catch ( Exception $e ) {
						$failure = $e;
						if ( 0 === $try ) call_user_func( self::$pause, 1 );
					}
				}
				if ( null === $piece ) break;
				$failure = null;
				// Del archivo del fragmento al de la descarga, por flujo (sin cargar el fragmento en memoria).
				$handle = @fopen( $part, 'ab' );
				$input = @fopen( $tmp, 'rb' );
				if ( ! $handle || ! $input ) { if ( $handle ) fclose( $handle ); if ( $input ) fclose( $input ); throw new Exception( 'No se pudo escribir la descarga de la migración en el almacén privado.' ); }
				$written = stream_copy_to_stream( $input, $handle );
				$flushed = fflush( $handle );
				fclose( $input );
				fclose( $handle );
				@unlink( $tmp );
				clearstatcache( true, $part );
				if ( $piece !== $written || ! $flushed || $offset + $piece !== (int) filesize( $part ) ) throw new Exception( 'La descarga de la migración no se pudo guardar completa (¿disco lleno?).' );
				$offset += $piece;
				Digitalisimo_Backups_Jobs::progress( 40 + (int) floor( 35 * $offset / $size ), 'Descargando el paquete: ' . self::megabytes( $offset ) . ' de ' . self::megabytes( $size ) . '…' );
				if ( microtime( true ) >= $deadline ) break;
			}
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $auth );
		}
		if ( $failure ) {
			// Sin avance en este paso el corte cuenta un intento; con avance, se espera un poco y se reanuda sin gastarlo.
			if ( $offset === $start ) throw $failure;
			return array( 'next' => 'download', 'checkpoint' => array( 'offset' => $offset ), 'progress_made' => true, 'wait' => 5, 'message' => 'Corte en la descarga (' . $failure->getMessage() . '); se reanuda desde ' . self::megabytes( $offset ) . '.' );
		}
		if ( $offset < $size ) return array( 'next' => 'download', 'checkpoint' => array( 'offset' => $offset ), 'progress_made' => $offset > $start, 'message' => 'Descargando el paquete: ' . self::megabytes( $offset ) . ' de ' . self::megabytes( $size ) . '…' );
		$actual = @hash_file( 'sha256', $part );
		if ( false === $actual || ! hash_equals( $sha, $actual ) ) {
			@unlink( $part );
			throw new Exception( 'El paquete descargado no coincide con el SHA-256 que anunció el origen (se alteró o se dañó en el camino); se descargará otra vez.' );
		}
		if ( ! @rename( $part, $final ) ) throw new Exception( 'No se pudo cerrar la descarga de la migración.' );
		return array( 'next' => 'check', 'checkpoint' => array( 'size' => $size, 'at' => time() ), 'message' => 'Paquete descargado y con el SHA-256 anunciado; comprobándolo…' );
	}
	/**
	 * Un fragmento exacto, recibido en $file: 206, `Content-Range` con la posición pedida y el total anunciado, y el largo
	 * justo. Devuelve su largo; nunca lo tiene entero en memoria.
	 */
	private static function fetch_piece( array $job, $auth, $start, $end, $size, $sha, $file ) {
		@unlink( $file );
		// Tope: el largo pedido y un byte más (lo que sobre se detecta abajo como un fragmento que no es el pedido).
		$reply = self::call( $job['spec']['migration'], $auth, (string) $job['id'], 'download', 'GET', array(), 120, array( 'Range' => 'bytes=' . $start . '-' . $end ), $file, $end - $start + 2 );
		clearstatcache( true, $file );
		$got = is_file( $file ) ? (int) filesize( $file ) : -1;
		if ( 200 === $reply['code'] ) {
			if ( 0 !== $start || $got !== $size ) throw new Exception( 'El origen no respetó el rango pedido.' );
			return $got;
		}
		if ( ! preg_match( '/^bytes (\d+)-(\d+)\/(\d+)$/D', trim( (string) $reply['range'] ), $m ) || (int) $m[1] !== $start || (int) $m[3] !== $size || (int) $m[2] < $start || (int) $m[2] > $end ) throw new Exception( 'El fragmento recibido no es el pedido (bytes ' . $start . '-' . $end . ').' );
		if ( '' !== $reply['sha256'] && ! hash_equals( $sha, $reply['sha256'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El paquete del origen cambió durante la descarga (otro SHA-256).' );
		if ( $got !== (int) $m[2] - $start + 1 ) throw new Exception( 'El fragmento llegó incompleto.' );
		return $got;
	}
	/**
	 * Antes de descifrar: la cabecera DGBK dice que el paquete está cifrado con la clave pegada. Y el turno: la migración
	 * nunca reemplaza el sitio con otro trabajo a medias (H1, como «Restaurar» y «Deshacer»): un respaldo exportaría la
	 * base de datos de antes y empaquetaría los archivos de después. Mientras otro trabajo esté en curso o en cola, espera
	 * aquí, sin gastar intentos y todavía cancelable; la restauración que encola después impide que empiece otro.
	 */
	private static function check( array $job ) {
		$final = self::work_dir( $job ) . '/package.dgbk';
		if ( ! is_file( $final ) ) return array( 'next' => 'download', 'checkpoint' => array( 'missing' => time() ), 'message' => 'El paquete descargado desapareció; se descarga otra vez…' );
		try {
			$parsed = Digitalisimo_Backups_Encrypted_File::read_header( $final );
		} catch ( Exception $e ) {
			@unlink( $final );
			throw new Digitalisimo_Backups_Permanent_Error( 'El paquete no es un respaldo cifrado válido: ' . $e->getMessage() . ' No se cambió nada de este sitio.' );
		}
		$key = (array) ( $parsed['header']['key'] ?? array() );
		if ( 'migration' !== ( $key['source'] ?? '' ) || ! hash_equals( (string) $job['spec']['migration']['key_id'], (string) ( $key['id'] ?? '' ) ) ) {
			@unlink( $final );
			throw new Digitalisimo_Backups_Permanent_Error( 'El paquete no está cifrado con la clave de esta migración. No se cambió nada de este sitio.' );
		}
		$other = self::other_job( (string) $job['id'] );
		if ( $other ) return array( 'next' => 'check', 'checkpoint' => array( 'at' => time(), 'waiting' => (string) $other['id'] ), 'wait' => 30, 'message' => 'Paquete comprobado. Esperando a que termine ' . $other['label'] . ' antes de reemplazar el sitio (una migración nunca convive con otro trabajo a medias)…' );
		return array( 'next' => 'restore', 'checkpoint' => array( 'at' => time() ), 'message' => 'Descifrando, comprobando la firma y aplicando la migración con la restauración por pasos…' );
	}
	/** Otro trabajo sin terminar (en curso o en cola) además de la migración $id, con su nombre para el mensaje; o null. */
	private static function other_job( $id ) {
		foreach ( Digitalisimo_Backups_Jobs::active() as $other ) {
			if ( (string) $other['id'] === (string) $id ) continue;
			$label = Digitalisimo_Backups_Jobs::is_restore( $other ) ? 'la restauración en curso' : ( 'migrate' === ( $other['spec']['kind'] ?? '' ) ? 'otra migración' : 'el respaldo pendiente o en curso' );
			return array( 'id' => (string) $other['id'], 'label' => $label . ' (' . Digitalisimo_Backups_Jobs::phase_label( (string) $other['phase'], $other ) . ')' );
		}
		return null;
	}
	/**
	 * Aplica la migración con el motor de «Restaurar» (la restauración por pasos de 1.0.64), sin camino propio: descifra y
	 * comprueba la firma con la clave del paquete y encola una restauración del paquete (direcciones de este sitio; su
	 * copia previa, bloqueada y marcada «restoring» con esta migración; tablas exactas: las de este sitio que el paquete
	 * no trae se retiran con el cambio). Después espera su resultado: la restauración se reanuda sola tras una muerte y,
	 * si falla tras empezar el cambio, vuelve sola a la copia previa. La marca pasa a «migrated» sólo si terminó bien
	 * (H4): una migración que no se aplicó, o que volvió sola a su copia previa, nunca se ofrece para «Deshacer».
	 */
	private static function restore( array $job ) {
		$cursor = (array) ( $job['checkpoint']['restore'] ?? array() );
		$restore_id = (string) ( $cursor['job'] ?? '' );
		// El proceso pudo morir entre encolar la restauración y guardar el punto de control: nunca se encola dos veces.
		if ( '' === $restore_id ) $restore_id = self::restore_of( (string) $job['id'] );
		if ( '' === $restore_id ) {
			$other = self::other_job( (string) $job['id'] );
			if ( $other ) return array( 'next' => 'restore', 'checkpoint' => $cursor, 'wait' => 30, 'message' => 'Esperando a que termine ' . $other['label'] . ' antes de reemplazar el sitio…' );
			$path = self::work_dir( $job ) . '/package.dgbk';
			if ( ! is_file( $path ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El paquete descargado ya no está en el almacén. No se cambió nada de este sitio: vuelve a pegar la clave.' );
			list( $auth, $root ) = self::pull_secrets( $job );
			Digitalisimo_Backups_Sodium::memzero( $auth );
			$migration = $job['spec']['migration'];
			$tag = array( 'job' => (string) $job['id'], 'from' => (string) ( $migration['source']['home'] ?? $migration['source_host'] ), 'at' => time(), 'state' => 'restoring' );
			$report = null;
			try {
				try {
					$report = Digitalisimo_Backups_Security::open( $path, array(), base64_encode( $root ) );
				} catch ( Exception $e ) {
					throw new Digitalisimo_Backups_Permanent_Error( 'El paquete no supera la comprobación de su cifrado o de su firma: ' . $e->getMessage() . ' No se cambió nada de este sitio.' );
				}
				if ( 'signed' !== $report['trust'] || 'migration' !== ( $report['key']['source'] ?? '' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El paquete no está firmado con la clave de esta migración. No se cambió nada de este sitio.' );
				try {
					$restore = Digitalisimo_Backups::migration_enqueue( $report, $tag, (string) $job['id'] );
				} catch ( Exception $e ) {
					// Otro trabajo se adelantó entre la comprobación y aquí: se espera, nada cambió.
					if ( false !== strpos( $e->getMessage(), 'en curso' ) ) return array( 'next' => 'restore', 'checkpoint' => $cursor, 'wait' => 30, 'message' => 'Esperando su turno para reemplazar el sitio: ' . $e->getMessage() );
					throw new Digitalisimo_Backups_Permanent_Error( 'El paquete se rechazó antes de tocar este sitio: ' . $e->getMessage() );
				}
			} finally {
				Digitalisimo_Backups_Security::close( $report );
				Digitalisimo_Backups_Sodium::memzero( $root );
			}
			// El ZIP descifrado pasó al área de trabajo de la restauración; el paquete cifrado ya no hace falta.
			@unlink( $path );
			Digitalisimo_Backups_Jobs::schedule();
			// Como «Restaurar»: el navegador que hace avanzar la migración (el tick de «Trabajos» de un administrador) recibe
			// el testigo de la restauración, con el que la sigue durante el mantenimiento y después del cambio de tablas.
			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) Digitalisimo_Backups_Restore_Maintenance::send_cookie( (string) $restore['spec']['token'] );
			return array( 'next' => 'restore', 'checkpoint' => array( 'job' => (string) $restore['id'] ), 'progress_made' => true, 'wait' => 1, 'message' => 'Aplicando la migración con la restauración por pasos: copia previa, base de datos, archivos y cambio…' );
		}
		$restore = Digitalisimo_Backups_Jobs::get( $restore_id );
		if ( ! $restore ) throw new Digitalisimo_Backups_Permanent_Error( 'La restauración de esta migración ya no existe en «Trabajos».' );
		$followed = Digitalisimo_Backups::migration_followed( $restore );
		if ( ! Digitalisimo_Backups_Job_Store::is_terminal( $followed ) ) {
			$view = Digitalisimo_Backups_Jobs::view( $followed );
			$label = ! empty( $followed['spec']['rollback'] ) ? 'Volviendo a la copia previa' : 'Aplicando la migración';
			Digitalisimo_Backups_Jobs::progress( 78 + (int) floor( 0.2 * max( 0, min( 100, (int) $view['percent'] ) ) ), $label . ': ' . (string) $view['phase_label'] . '…', true );
			return array( 'next' => 'restore', 'checkpoint' => array( 'job' => $restore_id ), 'wait' => 5, 'message' => $label . ' (' . (string) $view['phase_label'] . ')…' );
		}
		if ( 'success' !== $restore['status'] ) {
			$rolled = $followed !== $restore && 'success' === $followed['status'];
			self::mark_safety( (string) $job['id'], array( 'state' => $rolled ? 'rolled_back' : 'failed', 'closed_at' => time() ) );
			$why = is_array( $restore['error'] ?? null ) ? (string) $restore['error']['message'] : (string) ( $restore['progress']['message'] ?? '' );
			if ( 'cancelled' === $restore['status'] ) throw new Digitalisimo_Backups_Permanent_Error( 'La restauración de la migración se canceló antes de cambiar el sitio. No se cambió nada de este sitio.' );
			if ( $rolled ) throw new Digitalisimo_Backups_Permanent_Error( 'La migración no se aplicó: la restauración falló a mitad y el sitio volvió solo a su copia previa (' . self::safety_of( (string) $job['id'] ) . '); no hay nada que deshacer. ' . $why );
			if ( $followed !== $restore ) throw new Digitalisimo_Backups_Permanent_Error( 'La migración falló a mitad y la vuelta a la copia previa también: el sitio sigue en mantenimiento. Usa «Volver a la copia previa» en Backups (o `wp digitalisimo-backups jobs rollback`). ' . ( is_array( $followed['error'] ?? null ) ? (string) $followed['error']['message'] : $why ) );
			throw new Digitalisimo_Backups_Permanent_Error( 'El paquete se rechazó antes de tocar este sitio: ' . $why );
		}
		self::mark_safety( (string) $job['id'], array( 'state' => 'migrated', 'migrated_at' => time() ) );
		$migration = $job['spec']['migration'];
		$safety = self::safety_of( (string) $job['id'] );
		$notice = (string) ( $restore['result']['restore']['notice'] ?? '' );
		$access = (array) ( $restore['result']['restore']['access'] ?? array() );
		$message = 'Migración terminada: este sitio es ahora una copia de ' . $migration['source_host'] . ' con su propia dirección. ' . ( ! empty( $access['kept'] ) ? 'Quien migró sigue entrando con su cuenta («' . (string) $access['user'] . '»)' . ( '' !== (string) ( $access['login_url'] ?? '' ) ? ' por ' . $access['login_url'] : '' ) . '.' : 'Inicia sesión con un usuario del sitio de origen' . ( '' !== (string) ( $access['login_url'] ?? '' ) ? ' por ' . $access['login_url'] : '' ) . '.' ) . ' La copia previa ' . $safety . ' la deshace.';
		$warnings = '' !== $notice ? array( $notice ) : array();
		// Tras el SQL la sesión del navegador ya no vale (los usuarios son los del origen): el cierre se intenta aquí mismo.
		list( $auth ) = self::pull_secrets( $job );
		$closed = self::close_on_source( $job, $auth );
		Digitalisimo_Backups_Sodium::memzero( $auth );
		if ( $closed ) {
			self::forget_secrets( $job );
			self::remove_work( $job );
			return array( 'next' => null, 'checkpoint' => array( 'job' => $restore_id, 'safety' => $safety, 'closed' => true ), 'result' => array( 'safety' => $safety, 'source' => $migration['source_host'], 'restore' => $restore_id ), 'warnings' => $warnings, 'message' => $message );
		}
		return array( 'next' => 'close', 'checkpoint' => array( 'job' => $restore_id, 'safety' => $safety ), 'result' => array( 'safety' => $safety, 'source' => $migration['source_host'], 'restore' => $restore_id ), 'warnings' => $warnings, 'message' => $message );
	}
	/** La restauración que ya encoló la migración $id (su copia previa lleva la marca), o ''. */
	private static function restore_of( $id ) {
		foreach ( Digitalisimo_Backups_Jobs::all() as $other ) if ( Digitalisimo_Backups_Jobs::is_restore( $other ) && empty( $other['spec']['rollback'] ) && (string) ( $other['spec']['safety_extra']['migration']['job'] ?? '' ) === (string) $id ) return (string) $other['id'];
		return '';
	}
	/** Avisa al origen de que la clave quedó usada. Un fallo después de restaurar nunca deshace nada: la clave caduca sola. */
	private static function close( array $job ) {
		list( $auth ) = self::pull_secrets( $job );
		$closed = false;
		$error = '';
		try {
			self::call( $job['spec']['migration'], $auth, (string) $job['id'], 'finish', 'POST', array( 'result' => 'restored' ), 30 );
			$closed = true;
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			$closed = true; // Ya usada, revocada o caducada: no queda nada que cerrar.
		} catch ( Exception $e ) {
			$error = $e->getMessage();
		}
		Digitalisimo_Backups_Sodium::memzero( $auth );
		$attempt = (int) ( $job['attempts']['close'] ?? 0 ) + 1;
		if ( ! $closed && $attempt < 3 ) throw new Exception( $error );
		self::forget_secrets( $job );
		self::remove_work( $job );
		$message = (string) ( $job['progress']['message'] ?? 'Migración terminada.' );
		return array( 'next' => null, 'checkpoint' => array( 'closed' => $closed ), 'warnings' => $closed ? array() : array( 'No se pudo avisar al sitio de origen: su clave caduca sola. ' . $error ), 'message' => $message );
	}
	private static function close_on_source( array $job, $auth ) {
		try {
			self::call( $job['spec']['migration'], $auth, (string) $job['id'], 'finish', 'POST', array( 'result' => 'restored' ), 30 );
			return true;
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}
	/** Copia previa que creó la restauración del trabajo $job_id ('' si aún no hay). */
	public static function safety_of( $job_id ) {
		$row = self::safety_row( $job_id );
		return $row ? (string) ( $row['file'] ?? '' ) : '';
	}
	private static function safety_row( $job_id ) {
		foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) if ( (string) ( $row['migration']['job'] ?? '' ) === (string) $job_id ) return $row;
		return null;
	}
	/** Cambia la marca de la copia previa de la migración $job_id (estado y lo que se añada). */
	private static function mark_safety( $job_id, array $changes ) {
		$row = self::safety_row( $job_id );
		if ( ! $row ) return;
		Digitalisimo_Backups::migration_mark( (string) $row['file'], array_merge( (array) $row['migration'], $changes ) );
	}
	private static function remove_work( array $job ) {
		try { Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( (string) $job['id'] ) . '/download' ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	/** La descarga se conserva al reintentar (se reanuda); sólo una vuelta a «remote» la descarta. */
	public static function discard( array $job, $phase ) {
		if ( in_array( $phase, array( 'preflight', 'remote' ), true ) ) self::remove_work( $job );
	}
	/**
	 * Al fallar o cancelar: la descarga y la clave guardada se borran. Si no se llegó a restaurar, se pide al origen que
	 * libere la clave (vuelve a servir para otro intento hasta caducar).
	 */
	public static function on_terminal( array $job, $status, $message ) {
		try {
			// Si la migración no llegó a aplicarse (antes de restaurar, o la restauración falló o volvió a la copia previa), la
			// clave vuelve a servir para otro intento.
			$row = self::safety_row( (string) $job['id'] );
			$applied = 'close' === (string) ( $job['phase'] ?? '' ) || ( $row && 'migrated' === self::effective_state( $row ) );
			if ( ! $applied && ! empty( $job['checkpoint']['preflight'] ) ) {
				list( $auth ) = self::pull_secrets( $job );
				try { self::call( $job['spec']['migration'], $auth, (string) $job['id'], 'release', 'POST', array(), 10 ); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				Digitalisimo_Backups_Sodium::memzero( $auth );
			}
		} catch ( Throwable $e ) {
			// Sin clave legible no hay nada que avisar: la clave del origen caduca sola.
		}
		self::forget_secrets( $job );
		self::remove_work( $job );
		try { Digitalisimo_Backups_Job_Store::remove_staging( (string) $job['id'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}

	/* ------------------------------------------------------------------ Deshacer */

	/** La última migración que se puede deshacer: su copia previa sigue en el almacén y no se deshizo. */
	public static function undo_target() {
		foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) {
			if ( ! is_array( $row['migration'] ?? null ) ) continue;
			return 'migrated' === self::effective_state( $row ) ? $row : null;
		}
		return null;
	}
	/**
	 * Estado real de la migración de una copia previa marcada. La marca se escribe al crear la copia («restoring») y la
	 * asienta en el historial quien ve el resultado: la migración («migrated», «rolled_back», «failed»), «Deshacer»
	 * («undoing» → «undone»), el final de cualquier restauración (settle(), desde el motor de trabajos) o esta misma
	 * consulta. Una marca que nadie asentó y cuyo trabajo ya no existe (lo podó el almacén) es «unknown»: nunca se
	 * ofrece para «Deshacer» (N3), porque podría devolver el sitio a la copia de antes de migrar y borrar lo de después.
	 */
	public static function effective_state( array $row ) {
		$tag = (array) ( $row['migration'] ?? array() );
		$state = (string) ( $tag['state'] ?? '' );
		if ( ! in_array( $state, array( 'restoring', 'undoing' ), true ) ) return $state;
		$ref = 'restoring' === $state ? (string) ( $row['job'] ?? '' ) : (string) ( $tag['undo'] ?? '' );
		$job = Digitalisimo_Backups_Job_Store::valid_id( $ref ) ? Digitalisimo_Backups_Jobs::get( $ref ) : null;
		if ( ! $job ) return 'unknown';
		if ( ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return $state;
		return self::settle_row( $row, (string) $job['status'] );
	}
	/**
	 * Asienta en el historial las marcas que esperaban a la restauración $ended (la que acaba de terminar, con su estado)
	 * o, sin ella, a cualquier restauración ya terminada. Nunca lanza.
	 */
	public static function settle( array $ended = array() ) {
		try {
			foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) {
				$tag = (array) ( $row['migration'] ?? array() );
				$state = (string) ( $tag['state'] ?? '' );
				if ( ! in_array( $state, array( 'restoring', 'undoing' ), true ) ) continue;
				$ref = 'restoring' === $state ? (string) ( $row['job'] ?? '' ) : (string) ( $tag['undo'] ?? '' );
				if ( $ended && (string) ( $ended['id'] ?? '' ) === $ref ) {
					self::settle_row( $row, (string) $ended['status'] );
					continue;
				}
				$job = Digitalisimo_Backups_Job_Store::valid_id( $ref ) ? Digitalisimo_Backups_Jobs::get( $ref ) : null;
				if ( $job && Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) self::settle_row( $row, (string) $job['status'] );
			}
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
		}
	}
	/** Estado resuelto de una marca según cómo terminó su restauración, guardado en el historial. */
	private static function settle_row( array $row, $status ) {
		$tag = (array) $row['migration'];
		$resolved = 'restoring' === ( $tag['state'] ?? '' ) ? ( 'success' === $status ? 'migrated' : 'failed' ) : ( 'success' === $status ? 'undone' : 'migrated' );
		$tag['state'] = $resolved;
		$tag['settled_at'] = time();
		if ( 'undone' === $resolved && empty( $tag['undone_at'] ) ) $tag['undone_at'] = time();
		Digitalisimo_Backups::migration_mark( (string) $row['file'], $tag );
		return $resolved;
	}

	/* ------------------------------------------------------------------ Tablas apartadas (K1) */

	/**
	 * Días que se conservan las tablas apartadas antes de borrarse solas, cuando se borran solas (filtro
	 * `digitalisimo_backups_migration_kept_days`, 7; de 1 a 365).
	 */
	public static function kept_days() {
		return max( 1, min( 365, (int) self::filter( 'digitalisimo_backups_migration_kept_days', 7 ) ) );
	}
	/**
	 * Si las tablas propias que apartó una migración también se borran solas (filtro
	 * `digitalisimo_backups_migration_kept_auto`, falso por defecto). Sin él sólo las borra «Borrar ya»: entre ellas puede
	 * haber tablas de otra aplicación con un prefijo que empieza por el de este WordPress (que no se distinguen de las de
	 * un plugin; revisión de K1, H1), y nunca se borran sin que el administrador las vea.
	 */
	public static function kept_auto() {
		return true === (bool) self::filter( 'digitalisimo_backups_migration_kept_auto', false );
	}
	/**
	 * Qué aparta la restauración $job con `exact_tables`: `migration` (las tablas propias que el paquete no trae) o `undo`
	 * (las que trajo el origen y la copia previa no tiene); '' si no aparta nada.
	 */
	public static function kept_kind( array $job ) {
		if ( empty( $job['spec']['exact_tables'] ) || ! empty( $job['spec']['rollback'] ) ) return '';
		$kind = (string) ( $job['spec']['aside']['kind'] ?? '' );
		if ( in_array( $kind, array( 'migration', 'undo' ), true ) ) return $kind;
		// Encolada por 1.0.66–1.0.70 (sin `aside`): la de una migración lleva su marca y `via: migration`.
		return 'migration' === (string) ( $job['spec']['origin']['via'] ?? '' ) || ! empty( $job['spec']['safety_extra']['migration'] ) ? 'migration' : 'undo';
	}
	/** Si $name es una tabla apartada por la restauración de marca $tag: `<prefijo>dgbkk_<marca>_9…` (o sin prefijo, si no cabía). */
	private static function kept_name( $name, $tag ) {
		if ( ! is_string( $name ) || ! is_string( $tag ) || ! preg_match( '/^[0-9a-f]{6}$/D', $tag ) || ! preg_match( '/^(.*)dgbkk_' . $tag . '_9[0-9]{3,}$/D', $name, $m ) ) return false;
		return self::kept_prefix_ok( $m[1], $name );
	}
	/**
	 * El prefijo de un nombre de tabla apartada: el de este WordPress o, sin prefijo, sólo si con él no cabía en 64
	 * caracteres (como lo nombra restore_inspect(); revisión de K1, H2).
	 */
	private static function kept_prefix_ok( $prefix, $name ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) return false;
		$base = (string) $wpdb->base_prefix;
		return '' === $prefix ? '' === $base || strlen( $base . $name ) > 64 : $prefix === $base;
	}
	/** Si $name tiene el nombre de una tabla apartada de este WordPress, de cualquier marca (para listar las que no están en el registro). */
	private static function kept_like( $name ) {
		if ( ! is_string( $name ) || ! preg_match( '/^(.*)dgbkk_[0-9a-f]{6}_9[0-9]{3,}$/D', $name, $m ) ) return false;
		return self::kept_prefix_ok( $m[1], $name );
	}
	/** Una entrada del registro, normalizada; sus tablas, sólo las que tienen el nombre de una apartada de su marca. */
	private static function kept_entry( $tag, $entry ) {
		if ( ! is_array( $entry ) || ! is_string( $tag ) || ! preg_match( '/^[0-9a-f]{6}$/D', $tag ) ) return null;
		$tables = array();
		foreach ( (array) ( $entry['tables'] ?? array() ) as $dest => $old ) if ( self::kept_name( $old, $tag ) ) $tables[ (string) $dest ] = (string) $old;
		return array(
			'tag' => $tag,
			'kind' => 'undo' === ( $entry['kind'] ?? '' ) ? 'undo' : 'migration',
			'restore' => (string) ( $entry['restore'] ?? '' ),
			'migration' => (string) ( $entry['migration'] ?? '' ),
			'from' => (string) ( $entry['from'] ?? '' ),
			'safety' => (string) ( $entry['safety'] ?? '' ),
			'at' => (int) ( $entry['at'] ?? 0 ),
			'tables' => $tables,
			'bytes' => isset( $entry['bytes'] ) && is_numeric( $entry['bytes'] ) ? (int) $entry['bytes'] : null,
			'adopted' => ! empty( $entry['adopted'] ),
		);
	}
	/**
	 * @internal Anota en el registro, al verificar la restauración $job (antes de su copia previa y del cambio de tablas),
	 * las tablas que va a apartar ($retire, el de retire.json): qué migración o «Deshacer», cuándo, su copia previa y su
	 * tamaño. Sólo lo anotado se borra después (por plazo, al confirmarse un «Deshacer» o a mano); si no se puede anotar,
	 * la verificación falla y se repite, así que nunca se aparta una tabla sin registro.
	 */
	public static function kept_register( array $job, array $retire ) {
		$tag = (string) ( $job['spec']['tag'] ?? '' );
		$tables = array();
		foreach ( $retire as $row ) if ( is_array( $row ) && self::kept_name( (string) ( $row['old'] ?? '' ), $tag ) ) $tables[ (string) ( $row['dest'] ?? '' ) ] = (string) $row['old'];
		$aside = (array) ( $job['spec']['aside'] ?? array() );
		$extra = (array) ( $job['spec']['safety_extra']['migration'] ?? array() );
		$entry = array(
			'tag' => $tag,
			'kind' => self::kept_kind( $job ),
			'restore' => (string) ( $job['id'] ?? '' ),
			'migration' => (string) ( $aside['migration'] ?? ( $extra['job'] ?? '' ) ),
			'from' => (string) ( $aside['from'] ?? ( $extra['from'] ?? '' ) ),
			'safety' => (string) ( $job['spec']['safety'] ?? '' ),
			'at' => time(),
			'tables' => $tables,
			// El de las tablas vivas que se apartarán (los mismos datos).
			'bytes' => self::kept_bytes( array_keys( $tables ) ),
		);
		self::change( function( &$state ) use ( $tag, $entry ) {
			if ( $entry['tables'] ) $state['kept'][ $tag ] = $entry;
			else unset( $state['kept'][ $tag ] );
		} );
	}
	/** Tamaño aproximado (datos e índices) de $tables según el servidor de base de datos, o null si no lo dice. */
	private static function kept_bytes( array $tables ) {
		global $wpdb;
		$tables = array_values( array_filter( array_map( 'strval', $tables ), 'strlen' ) );
		if ( ! $tables || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) return null;
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		try {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_NAME, DATA_LENGTH + INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . implode( ', ', array_fill( 0, count( $tables ), '%s' ) ) . ')', ...$tables ), ARRAY_N );
		} catch ( Throwable $e ) {
			$rows = null;
		} finally {
			if ( null !== $suppress ) $wpdb->suppress_errors( $suppress );
		}
		if ( ! is_array( $rows ) || ! $rows ) return null;
		$bytes = 0;
		foreach ( $rows as $row ) $bytes += (int) ( is_array( $row ) ? ( $row[1] ?? 0 ) : 0 );
		return $bytes;
	}
	private static function kept_live() {
		global $wpdb;
		return array_flip( array_map( 'strval', (array) $wpdb->get_col( 'SHOW TABLES' ) ) );
	}
	private static function kept_job( array $entry ) {
		return Digitalisimo_Backups_Job_Store::valid_id( $entry['restore'] ) ? Digitalisimo_Backups_Jobs::get( $entry['restore'] ) : null;
	}
	/**
	 * Cuándo se borra solo un juego que ya no está en uso, y por qué no si no. Sólo los de un «Deshacer» (las tablas que
	 * trajo el origen, con sus datos: el motivo de K1): confirmado, en cuanto termina; si no terminó bien, pasados
	 * kept_days() días. Los de una migración (tablas propias que el paquete no traía, entre las que puede haber una de
	 * otra aplicación con prefijo anidado: revisión de K1, H1), sólo con kept_auto() y pasados kept_days() días; si no,
	 * con «Borrar ya». Y siempre sólo si su copia previa sigue en el almacén (nunca se borra solo lo único que queda de
	 * esas tablas). Devuelve [momento (0: nunca solo), nota].
	 */
	private static function kept_term( array $entry, $job ) {
		if ( 'undo' === $entry['kind'] && is_array( $job ) && 'success' === ( $job['status'] ?? '' ) ) return array( max( 1, $entry['at'] ), 'Las trajo el origen: se borran al confirmarse el «Deshacer».' );
		if ( 'migration' === $entry['kind'] && ! self::kept_auto() ) return array( 0, 'No se borran solas (podría haber tablas de otra aplicación que comparte la base de datos): revisa los nombres y usa «Borrar ya» cuando no las necesites.' );
		if ( '' === $entry['safety'] || ! Digitalisimo_Backups::migration_safety_present( $entry['safety'] ) ) return array( 0, 'No se borran solas: su copia previa' . ( '' !== $entry['safety'] ? ' ' . $entry['safety'] : '' ) . ' ya no está en el almacén (bórralas a mano si no las necesitas).' );
		return array( max( 1, $entry['at'] ) + self::kept_days() * 86400, '' );
	}
	/**
	 * El registro de tablas apartadas, con las que una restauración de 1.0.66–1.0.70 dejó sin anotar: se toman de su
	 * trabajo, si sigue en el almacén (una restauración con `exact_tables` ya terminada): sólo las tablas que siguen en la
	 * base de datos con el nombre de una apartada de su marca, que sólo ese trabajo pudo crear; lo que eran, de su
	 * retire.json si aún lo tiene. Con $persist se guardan en el registro (el vigilante y «Borrar ya»); la consulta nunca
	 * escribe.
	 */
	private static function kept_registry( $persist ) {
		$registry = self::state()['kept'];
		try {
			$live = null;
			$adopt = array();
			foreach ( Digitalisimo_Backups_Jobs::all() as $job ) {
				$tag = (string) ( $job['spec']['tag'] ?? '' );
				if ( ! Digitalisimo_Backups_Jobs::is_restore( $job ) || '' === self::kept_kind( $job ) || isset( $registry[ $tag ] ) || ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) continue;
				if ( null === $live ) $live = self::kept_live();
				$tables = array();
				foreach ( array_keys( $live ) as $table ) if ( self::kept_name( (string) $table, $tag ) ) $tables[ (string) $table ] = (string) $table;
				if ( ! $tables ) continue;
				$path = Digitalisimo_Backups_Job_Store::directory( (string) $job['id'] ) . '/' . Digitalisimo_Backups_Job_Store::STAGING . '/retire.json';
				$retire = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
				foreach ( is_array( $retire ) ? $retire : array() as $row ) {
					if ( ! is_array( $row ) || ! isset( $tables[ (string) ( $row['old'] ?? '' ) ] ) || '' === (string) ( $row['dest'] ?? '' ) ) continue;
					unset( $tables[ (string) $row['old'] ] );
					$tables[ (string) $row['dest'] ] = (string) $row['old'];
				}
				$extra = (array) ( $job['spec']['safety_extra']['migration'] ?? array() );
				$adopt[ $tag ] = array( 'tag' => $tag, 'kind' => self::kept_kind( $job ), 'restore' => (string) $job['id'], 'migration' => (string) ( $extra['job'] ?? '' ), 'from' => (string) ( $extra['from'] ?? '' ), 'safety' => (string) ( $job['spec']['safety'] ?? '' ), 'at' => (int) ( $job['finished_at'] ?? 0 ) ?: time(), 'tables' => $tables, 'bytes' => self::kept_bytes( array_values( $tables ) ), 'adopted' => true );
			}
			if ( $adopt && $persist ) self::change( function( &$state ) use ( $adopt ) { foreach ( $adopt as $tag => $entry ) if ( ! isset( $state['kept'][ $tag ] ) ) $state['kept'][ $tag ] = $entry; } );
			$registry += $adopt;
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: tablas apartadas: ' . $e->getMessage() );
		}
		return $registry;
	}
	/**
	 * Las tablas apartadas por las migraciones y sus «Deshacer» que siguen en la base de datos: un juego por restauración,
	 * con qué las apartó, de dónde, cuándo, su tamaño aproximado y cuándo se borran solas (o por qué no). `unregistered`:
	 * tablas con nombre de apartada que no están en el registro; nunca se tocan.
	 */
	public static function kept_tables( $now = null ) {
		$now = null === $now ? time() : (int) $now;
		$registry = self::kept_registry( false );
		$live = self::kept_live();
		$known = array();
		$sets = array();
		foreach ( $registry as $tag => $entry ) {
			$entry = self::kept_entry( (string) $tag, $entry );
			if ( ! $entry ) continue;
			foreach ( $entry['tables'] as $old ) $known[ $old ] = true;
			$present = array_filter( $entry['tables'], function( $old ) use ( $live ) { return isset( $live[ $old ] ); } );
			if ( ! $present ) continue;
			$job = self::kept_job( $entry );
			$busy = is_array( $job ) && ! Digitalisimo_Backups_Job_Store::is_terminal( $job );
			list( $due, $note ) = $busy ? array( 0, 'La restauración que las apartó sigue en curso.' ) : self::kept_term( $entry, $job );
			$tables = array();
			// Lo que era cada una ('' si no se sabe: una tomada de una restauración anterior al registro sin su retire.json).
			foreach ( $present as $dest => $old ) $tables[] = array( 'table' => $old, 'was' => (string) $dest === $old ? '' : (string) $dest );
			$from = '' !== self::host_label( $entry['from'] ) ? self::host_label( $entry['from'] ) : $entry['from'];
			$sets[] = array(
				'tag' => $entry['tag'],
				'kind' => $entry['kind'],
				'label' => 'undo' === $entry['kind'] ? 'Deshacer la migración' . ( '' !== $from ? ' de ' . $from : '' ) . ': tablas que trajo el origen' : 'Migración' . ( '' !== $from ? ' desde ' . $from : '' ) . ': tablas propias que el paquete no traía',
				'from' => $entry['from'],
				'at' => gmdate( 'c', $entry['at'] ),
				'count' => count( $tables ),
				'tables' => $tables,
				'bytes' => $entry['bytes'],
				'safety' => $entry['safety'],
				'expires_at' => $due ? gmdate( 'c', max( $due, $now ) ) : '',
				'note' => $note,
				'busy' => $busy,
				'adopted' => $entry['adopted'],
			);
		}
		$unregistered = array();
		foreach ( array_keys( $live ) as $table ) if ( self::kept_like( (string) $table ) && ! isset( $known[ (string) $table ] ) ) $unregistered[] = (string) $table;
		return array( 'days' => self::kept_days(), 'auto' => self::kept_auto(), 'count' => (int) array_sum( array_column( $sets, 'count' ) ), 'sets' => $sets, 'unregistered' => $unregistered );
	}
	/** Borra las tablas $present de un juego (sólo nombres que pasaron kept_entry()). Devuelve [borradas, errores]. */
	private static function kept_drop( array $present ) {
		global $wpdb;
		$dropped = array();
		$errors = array();
		foreach ( $present as $old ) {
			if ( false === $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( (string) $old ) ) ) $errors[] = $old . ': ' . ( '' !== (string) $wpdb->last_error ? (string) $wpdb->last_error : 'no se pudo borrar' );
			else $dropped[] = (string) $old;
		}
		return array( $dropped, $errors );
	}
	/** Quita del registro los juegos $gone (marca → `at` de la entrada que se vio; otra anotada después se queda). */
	private static function kept_forget( array $gone ) {
		if ( ! $gone ) return;
		self::change( function( &$state ) use ( $gone ) {
			$changed = false;
			foreach ( $gone as $tag => $at ) {
				if ( ! isset( $state['kept'][ $tag ] ) || ( null !== $at && (int) ( $state['kept'][ $tag ]['at'] ?? 0 ) !== (int) $at ) ) continue;
				unset( $state['kept'][ $tag ] );
				$changed = true;
			}
			return $changed ? null : false;
		} );
	}
	/**
	 * Borra tablas apartadas del registro: todas las que no son de una restauración en curso ($all: «Borrar ya» en la
	 * pantalla, `POST /migration/cleanup` y `wp digitalisimo-backups migration cleanup`) o sólo las que tocan (el vigilante
	 * horario del motor de trabajos: plazo cumplido con su copia previa en el almacén, o «Deshacer» confirmado). Nunca una
	 * tabla que no esté en el registro con el nombre de una apartada de su marca. $dry_run sólo dice qué borraría.
	 */
	public static function kept_cleanup( $all, $dry_run = false, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		$report = array( 'dry_run' => (bool) $dry_run, 'count' => 0, 'tables' => array(), 'bytes' => 0, 'kept' => array(), 'errors' => array(), 'unregistered' => array() );
		try {
			$registry = self::kept_registry( ! $dry_run );
			$live = self::kept_live();
			$known = array();
			$gone = array();
			$sized = true;
			foreach ( $registry as $tag => $raw ) {
				$entry = self::kept_entry( (string) $tag, $raw );
				if ( ! $entry ) { $gone[ (string) $tag ] = null; continue; }
				foreach ( $entry['tables'] as $old ) $known[ $old ] = true;
				$job = self::kept_job( $entry );
				$busy = is_array( $job ) && ! Digitalisimo_Backups_Job_Store::is_terminal( $job );
				$present = array_filter( $entry['tables'], function( $old ) use ( $live ) { return isset( $live[ $old ] ); } );
				if ( ! $present ) {
					// Nada apartado (la restauración no llegó al cambio) o ya borrado: fuera del registro.
					if ( ! $busy ) $gone[ $entry['tag'] ] = $entry['at'];
					continue;
				}
				if ( $busy ) {
					$report['kept'][] = array( 'tag' => $entry['tag'], 'count' => count( $present ), 'reason' => 'La restauración que las apartó sigue en curso.' );
					continue;
				}
				if ( ! $all ) {
					list( $due ) = self::kept_term( $entry, $job );
					if ( ! $due || $due > $now ) continue;
				}
				if ( null === $entry['bytes'] || count( $present ) !== count( $entry['tables'] ) ) $sized = false;
				else $report['bytes'] += $entry['bytes'];
				if ( $dry_run ) {
					$report['tables'] = array_merge( $report['tables'], array_values( $present ) );
					continue;
				}
				list( $dropped, $errors ) = self::kept_drop( $present );
				$report['tables'] = array_merge( $report['tables'], $dropped );
				$report['errors'] = array_merge( $report['errors'], $errors );
				if ( ! $errors ) $gone[ $entry['tag'] ] = $entry['at'];
			}
			if ( ! $dry_run ) self::kept_forget( $gone );
			foreach ( array_keys( $live ) as $table ) if ( self::kept_like( (string) $table ) && ! isset( $known[ (string) $table ] ) ) $report['unregistered'][] = (string) $table;
			if ( ! $sized ) $report['bytes'] = null;
		} catch ( Throwable $e ) {
			$report['errors'][] = $e->getMessage();
		}
		$report['count'] = count( $report['tables'] );
		if ( ! $dry_run && $report['count'] ) self::log( '', 'kept_dropped', $report['count'] . ' tabla(s) ' . ( $all ? 'a mano' : 'por plazo o tras deshacer' ) . ': ' . implode( ', ', array_slice( $report['tables'], 0, 5 ) ) . ( $report['count'] > 5 ? '…' : '' ) );
		if ( $report['errors'] && function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: tablas apartadas: ' . implode( ' · ', $report['errors'] ) );
		return $report;
	}
	/** Mensaje de un informe de kept_cleanup() para la pantalla, REST y WP-CLI. */
	public static function kept_message( array $report ) {
		$size = null !== $report['bytes'] && $report['count'] ? ' (' . self::megabytes( (int) $report['bytes'] ) . ' aprox.)' : '';
		if ( $report['dry_run'] ) $message = $report['count'] ? 'Se borrarían ' . $report['count'] . ' tabla(s) apartadas' . $size . ': ' . implode( ', ', array_slice( $report['tables'], 0, 10 ) ) . ( $report['count'] > 10 ? '…' : '' ) . '. Sus datos siguen en las copias previas de sus migraciones.' : 'No hay tablas apartadas que borrar.';
		else $message = $report['count'] ? 'Se borraron ' . $report['count'] . ' tabla(s) apartadas' . $size . '.' : 'No había tablas apartadas que borrar.';
		foreach ( $report['kept'] as $kept ) $message .= ' Se dejaron ' . (int) $kept['count'] . ': ' . $kept['reason'];
		if ( $report['errors'] ) $message .= ' No se pudieron borrar: ' . implode( '; ', array_slice( $report['errors'], 0, 3 ) ) . ' (siguen en el registro).';
		if ( $report['unregistered'] ) $message .= ' ' . count( $report['unregistered'] ) . ' tabla(s) con nombre de apartada no están en el registro y no se tocan (' . implode( ', ', array_slice( $report['unregistered'], 0, 5 ) ) . ( count( $report['unregistered'] ) > 5 ? '…' : '' ) . ').';
		return $message;
	}
	/**
	 * @internal Al terminar «Deshacer la migración» (su limpieza, con el cambio ya hecho): las tablas que trajo el origen y
	 * este deshacer apartó se borran ya (K1): son datos del origen que este sitio no usa, y siguen en la copia previa del
	 * deshacer y en el origen. Devuelve ['dropped' => n, 'errors' => [...]]; lo que no se pudo borrar queda en el registro.
	 */
	public static function kept_confirmed( array $job ) {
		$out = array( 'dropped' => 0, 'errors' => array() );
		if ( 'undo' !== self::kept_kind( $job ) ) return $out;
		$tag = (string) ( $job['spec']['tag'] ?? '' );
		$entry = self::kept_entry( $tag, self::state()['kept'][ $tag ] ?? null );
		if ( ! $entry || (string) $entry['restore'] !== (string) $job['id'] ) return $out;
		$live = self::kept_live();
		list( $dropped, $errors ) = self::kept_drop( array_filter( $entry['tables'], function( $old ) use ( $live ) { return isset( $live[ $old ] ); } ) );
		if ( ! $errors ) self::kept_forget( array( $tag => $entry['at'] ) );
		if ( $dropped ) self::log( '', 'kept_dropped', count( $dropped ) . ' tabla(s) del origen al confirmarse el deshacer: ' . implode( ', ', array_slice( $dropped, 0, 5 ) ) . ( count( $dropped ) > 5 ? '…' : '' ) );
		return array( 'dropped' => count( $dropped ), 'errors' => $errors );
	}

	/* ------------------------------------------------------------------ Pantalla */

	private static function megabytes( $bytes ) { return Digitalisimo_Backups_Archive_Inspector::megabytes( (int) $bytes ); }
	private static function filter( $hook, $value ) { return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value ) : $value; }
	private static function event_label( $event ) {
		$labels = array( 'created' => 'Clave generada', 'hello' => 'El destino se presentó', 'package' => 'Paquete pedido', 'ready' => 'Paquete listo', 'download' => 'Descarga iniciada', 'used' => 'Migración terminada: clave usada', 'released' => 'El destino abandonó: clave liberada', 'revoked' => 'Clave revocada', 'expired' => 'Clave caducada', 'blocked' => 'Clave bloqueada por intentos fallidos', 'auth_failed' => 'Intento fallido', 'other_client' => 'Otro sitio intentó usar una clave en uso', 'lab_on' => 'Laboratorio activado (HTTP y direcciones internas)', 'lab_off' => 'Laboratorio desactivado', 'taken_over' => 'Otro destino retomó una clave abandonada', 'undo' => 'Deshacer la migración: en curso', 'undone' => 'Migración deshecha', 'migrated' => 'Migración recibida', 'kept_dropped' => 'Tablas apartadas borradas' );
		return $labels[ $event ] ?? $event;
	}
	/** Peticiones sin una clave válida (agregadas, fuera del registro de usos): cuántas, desde cuándo, la última e IP. */
	public static function rejected() {
		$seen = Digitalisimo_Backups_Keys::get( self::REJECTED, array() );
		if ( ! is_array( $seen ) || empty( $seen['count'] ) ) return array( 'count' => 0, 'since' => '', 'last' => '', 'ip' => '' );
		return array( 'count' => (int) $seen['count'], 'since' => gmdate( 'c', (int) $seen['since'] ), 'last' => gmdate( 'c', (int) ( $seen['last'] ?? 0 ) ), 'ip' => (string) ( $seen['ip'] ?? '' ) );
	}
	public static function public_log( $limit = 20 ) {
		$out = array();
		foreach ( array_reverse( array_slice( self::state()['log'], -$limit ) ) as $event ) $out[] = array( 'at' => gmdate( 'c', (int) $event['at'] ), 'key' => (string) $event['key'], 'event' => (string) $event['event'], 'label' => self::event_label( (string) $event['event'] ), 'ip' => (string) $event['ip'], 'detail' => (string) $event['detail'] );
		return $out;
	}
	private static function context_fields( $network ) { return ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ); }
	/** Pestaña «Migrar»: recibir una migración (destino), su vuelta atrás, generar claves (origen), registro y laboratorio. */
	public static function panel( $network, array $status ) {
		$post = esc_url( admin_url( 'admin-post.php' ) );
		$main = ! ( is_multisite() && ! is_main_site() );
		echo '<div class="digitalisimo-backups-grid digitalisimo-backups-migrate">';
		// Destino.
		echo '<section class="digitalisimo-backups-card" id="digitalisimo-backups-migrate-pull"><h2>Migrar desde otro sitio</h2><p>Pega la clave de migración que generaste en el sitio de origen y pulsa «Migrar». Se reemplazan su base de datos y sus archivos; antes se crea una copia previa bloqueada: «Deshacer la migración» vuelve a dejarlo como estaba. El origen no cambia.</p><p class="digitalisimo-backups-warning">' . ( is_multisite() ? 'Esta red se reemplaza por completo con la del origen' : 'Este sitio se reemplaza por completo con el del origen' ) . ' y <strong>conserva su dirección</strong> (' . esc_html( home_url() ) . ').</p>';
		if ( ! $main ) echo '<p class="notice notice-warning inline">En una red, la migración se inicia desde la administración de red: reemplaza la red entera.</p>';
		elseif ( '' !== $status['pull_problem'] ) echo '<p class="notice notice-warning inline">' . esc_html( $status['pull_problem'] ) . '</p>';
		echo '<form method="post" action="' . $post . '" onsubmit="return confirm(\'¿Reemplazar este sitio por el del origen? Se crea antes una copia previa para deshacerlo.\')">';
		wp_nonce_field( self::ACTION_PULL );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_PULL ) . '">' . self::context_fields( $network ) . '<p><label for="digitalisimo-backups-migration-key">Clave de migración</label><br><textarea id="digitalisimo-backups-migration-key" name="migration_key" rows="3" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="large-text code"></textarea></p><p><label><input type="checkbox" name="keep_access" value="1" checked> Conservar mi acceso</label><input type="hidden" name="keep_access_shown" value="1"><br><span class="description">Tu usuario, tu contraseña y tus contraseñas de aplicación siguen sirviendo después de migrar, y este sitio conserva su ruta de acceso.</span></p><p><button class="button button-primary button-hero"' . disabled( ! $main || '' !== $status['pull_problem'], true, false ) . '>Migrar a este sitio</button></p></form>';
		if ( $status['active'] ) echo '<p class="description">Migración en curso: ' . esc_html( $status['active']['status_label'] . ' · ' . $status['active']['phase_label'] . ' (' . (int) $status['active']['percent'] . ' %)' ) . '. Su avance está en «Trabajos»; cuando termine, el aviso de Backups dice cómo entrar (con «Conservar mi acceso», tu cuenta sigue sirviendo).</p>';
		if ( $status['undo'] ) {
			echo '<div class="digitalisimo-backups-migrate-undo"><h3>Deshacer la migración</h3><p>Última migración recibida de <strong>' . esc_html( $status['undo']['from'] ) . '</strong> el ' . esc_html( $status['undo']['date'] ) . '.</p><p>Deshacerla restaura la copia previa <code>' . esc_html( $status['undo']['file'] ) . '</code> (y antes guarda el estado actual en otra copia previa).</p><form method="post" action="' . $post . '" onsubmit="return confirm(\'¿Deshacer la migración y volver al estado anterior de este sitio?\')">';
			wp_nonce_field( self::ACTION_UNDO );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_UNDO ) . '">' . self::context_fields( $network ) . '<button class="button">Deshacer la migración</button></form></div>';
		}
		echo '</section>';
		// Origen.
		echo '<section class="digitalisimo-backups-card" id="digitalisimo-backups-migrate-keys"><h2>Generar clave de migración</h2><p>Para llevar ' . ( is_multisite() ? 'esta red' : 'este sitio' ) . ' a otro servidor o dominio (o a una copia de pruebas), genera una clave y pégala en Backups → Herramientas → Migrar del sitio de destino. Sirve para una sola migración, caduca y se puede revocar; se muestra una sola vez. El paquete viaja cifrado de extremo a extremo y este sitio no cambia.</p>';
		if ( '' !== $status['key_problem'] ) echo '<p class="notice notice-warning inline">' . esc_html( $status['key_problem'] ) . '</p>';
		echo '<form method="post" action="' . $post . '">';
		wp_nonce_field( self::ACTION_CREATE );
		// Testigo de un solo uso: recargar la página que muestra la clave (que reenvía el formulario) no crea otra.
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CREATE ) . '"><input type="hidden" name="form_token" value="' . esc_attr( bin2hex( random_bytes( 16 ) ) ) . '">' . self::context_fields( $network ) . '<p><label>Caduca en <select name="hours"><option value="1">1 hora</option><option value="6">6 horas</option><option value="24" selected>24 horas</option><option value="72">72 horas</option></select></label> <button class="button button-primary"' . disabled( '' !== $status['key_problem'], true, false ) . '>Generar clave de migración</button></p></form>';
		if ( $status['keys'] ) {
			echo '<table class="widefat striped"><thead><tr><th>Clave</th><th>Estado</th><th>Caduca (UTC)</th><th>Destino</th><th></th></tr></thead><tbody>';
			foreach ( array_slice( $status['keys'], 0, 10 ) as $key ) {
				echo '<tr><td><code>' . esc_html( substr( $key['id'], 0, 8 ) ) . '</code></td><td>' . esc_html( $key['state_label'] ) . '</td><td>' . esc_html( str_replace( 'T', ' ', substr( $key['expires_at'], 0, 16 ) ) ) . '</td><td>' . esc_html( $key['site'] ) . '</td><td>';
				if ( $key['live'] ) {
					echo '<form method="post" action="' . $post . '">';
					wp_nonce_field( self::ACTION_REVOKE );
					echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_REVOKE ) . '"><input type="hidden" name="key" value="' . esc_attr( $key['id'] ) . '">' . self::context_fields( $network ) . '<button class="button button-small">Revocar</button></form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section></div>';
		// Tablas apartadas (K1): las del registro, con «Borrar ya».
		$kept = (array) ( $status['kept_tables'] ?? array() );
		if ( ! empty( $kept['sets'] ) || ! empty( $kept['unregistered'] ) ) {
			echo '<section class="digitalisimo-backups-card" id="digitalisimo-backups-migrate-kept"><h2>Tablas apartadas</h2><p>Una migración aparta con otro nombre (<code>…dgbkk_…</code>, fuera de los respaldos) las tablas de este WordPress que el paquete no traía, y «Deshacer la migración», las que trajo el origen. Las del origen se borran en cuanto el deshacer termina. Las de una migración ' . ( ! empty( $kept['auto'] ) ? 'se borran solas a los ' . (int) ( $kept['days'] ?? self::kept_days() ) . ' días si su copia previa sigue en el almacén' : 'no se borran solas: bórralas con «Borrar ya» cuando no las necesites' ) . '. Sólo se borran las de esta lista, nunca otra tabla.</p>' . ( ! empty( $kept['auto'] ) ? '' : '<p class="digitalisimo-backups-warning">Revisa sus nombres de antes: también se aparta una tabla de otra aplicación cuyo prefijo empiece por el de este sitio.</p>' );
			if ( ! empty( $kept['sets'] ) ) {
				echo '<table class="widefat striped"><thead><tr><th>Apartadas por</th><th>Fecha (UTC)</th><th>Tablas</th><th>Tamaño</th><th>Se borran solas</th></tr></thead><tbody>';
				foreach ( $kept['sets'] as $set ) {
					$names = array();
					// Todas, con su nombre de antes: el administrador ve qué se borraría (revisión de K1, H1).
					foreach ( (array) $set['tables'] as $table ) $names[] = ( '' !== $table['was'] ? $table['was'] . ' → ' : '' ) . $table['table'];
					echo '<tr><td>' . esc_html( $set['label'] ) . '</td><td>' . esc_html( str_replace( 'T', ' ', substr( (string) $set['at'], 0, 16 ) ) ) . '</td><td>' . (int) $set['count'] . ': <code>' . esc_html( implode( ', ', $names ) ) . '</code></td><td>' . esc_html( null === $set['bytes'] ? '—' : self::megabytes( (int) $set['bytes'] ) ) . '</td><td>' . esc_html( '' !== (string) $set['expires_at'] ? str_replace( 'T', ' ', substr( (string) $set['expires_at'], 0, 16 ) ) : (string) $set['note'] ) . '</td></tr>';
				}
				echo '</tbody></table>';
				$ready = array_filter( (array) $kept['sets'], function( $set ) { return empty( $set['busy'] ); } );
				echo '<form method="post" action="' . $post . '" onsubmit="return confirm(\'¿Borrar ya las tablas apartadas? Sus datos siguen en las copias previas de sus migraciones.\')">';
				wp_nonce_field( self::ACTION_CLEANUP );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CLEANUP ) . '">' . self::context_fields( $network ) . '<p><button class="button"' . disabled( ! $main || ! $ready, true, false ) . '>Borrar ya las tablas apartadas</button></p></form>';
			}
			if ( ! empty( $kept['unregistered'] ) ) echo '<p class="description">' . count( (array) $kept['unregistered'] ) . ' tabla(s) con nombre de apartada no están en el registro y nunca se tocan (bórralas a mano si sabes que sobran): <code>' . esc_html( implode( ', ', array_slice( (array) $kept['unregistered'], 0, 10 ) ) ) . '</code></p>';
			echo '</section>';
		}
		// Staging, laboratorio y registro.
		echo '<section class="digitalisimo-backups-card"><h2>Copia de pruebas (staging)</h2><p>La migración sirve también para una copia de pruebas en otro dominio o subdominio: genera la clave en producción y migra desde el sitio de pruebas. Para publicar la copia de pruebas, haz la migración en sentido contrario: genera la clave en el sitio de pruebas y migra desde producción; producción guarda su copia previa y «Deshacer la migración» la devuelve.</p><p class="digitalisimo-backups-warning">No se fusionan cambios entre dos sitios vivos: lo que cambie en producción mientras tanto se reemplaza.</p><p class="digitalisimo-backups-warning">En una copia de pruebas, revisa las automatizaciones y los destinos que llegan del origen: podrían respaldar sobre los de producción.</p></section>';
		echo '<section class="digitalisimo-backups-card"><h2>Laboratorio</h2><form method="post" action="' . $post . '">';
		wp_nonce_field( self::ACTION_SETTINGS );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SETTINGS ) . '">' . self::context_fields( $network ) . '<p><label><input type="checkbox" name="allow_http" value="1"' . checked( $status['allow_http'], true, false ) . '> Permitir HTTP y direcciones internas (sólo laboratorio)</label></p><p class="description digitalisimo-backups-warning">Sin HTTPS la credencial de la clave viaja legible por la red. Actívalo en los dos sitios sólo para pruebas en una red propia.</p><p class="description">Quien vea el tráfico podría descargar el paquete (que sigue cifrado con una clave que nunca viaja) o interrumpir la migración. Con direcciones internas, este sitio acepta claves que apunten a loopback, a la red privada o al mismo servidor.</p><p><button class="button">Guardar</button></p></form></section>';
		echo '<section class="digitalisimo-backups-card"><h2>Registro de la migración</h2>';
		if ( ! empty( $status['rejected']['count'] ) ) echo '<p class="description">Peticiones a la API de migración sin una clave válida (rechazadas, no cuentan como usos): ' . (int) $status['rejected']['count'] . ' desde el ' . esc_html( str_replace( 'T', ' ', substr( (string) $status['rejected']['since'], 0, 16 ) ) ) . ' UTC; la última, el ' . esc_html( str_replace( 'T', ' ', substr( (string) $status['rejected']['last'], 0, 16 ) ) ) . ' UTC desde ' . esc_html( (string) $status['rejected']['ip'] ) . '.</p>';
		if ( ! $status['log'] ) echo '<p>Aún no hay usos.</p>';
		else {
			echo '<table class="widefat striped"><thead><tr><th>Fecha (UTC)</th><th>Clave</th><th>Evento</th><th>IP</th><th>Detalle</th></tr></thead><tbody>';
			foreach ( $status['log'] as $event ) echo '<tr><td>' . esc_html( str_replace( 'T', ' ', substr( $event['at'], 0, 19 ) ) ) . '</td><td><code>' . esc_html( $event['key'] ) . '</code></td><td>' . esc_html( $event['label'] ) . '</td><td>' . esc_html( $event['ip'] ) . '</td><td>' . esc_html( $event['detail'] ) . '</td></tr>';
			echo '</tbody></table>';
		}
		echo '</section>';
	}

	/* ------------------------------------------------------------------ admin-post */

	private static function guard( $action ) {
		if ( ! Digitalisimo_Backups::service_can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( $action );
	}
	/**
	 * La clave sólo se muestra aquí, una vez, en una respuesta que no se guarda en caché ni pasa por una redirección
	 * (una URL quedaría en el historial del navegador y en los registros del servidor).
	 */
	public static function create_request() {
		self::guard( self::ACTION_CREATE );
		$token = isset( $_POST['form_token'] ) && is_string( $_POST['form_token'] ) ? strtolower( (string) $_POST['form_token'] ) : '';
		if ( ! self::spend_form( $token ) ) Digitalisimo_Backups::redirect( false, '' === $token ? 'Recarga Backups → Herramientas → Migrar y vuelve a generar la clave.' : 'Esa página ya generó una clave (se muestra una sola vez; recargarla no crea otra). Si no la copiaste, revócala aquí y genera otra.', null, 'migrate' );
		// En una red, la clave se lleva la red entera: esta pantalla es la de la red (alcance explícito, como migrar).
		$result = Digitalisimo_Backups::service_migration_create_key( array( 'hours' => (int) ( $_POST['hours'] ?? self::DEFAULT_HOURS ), 'network' => is_multisite() ) );
		if ( is_wp_error( $result ) ) Digitalisimo_Backups::redirect( false, $result->get_error_message(), null, 'migrate' );
		if ( function_exists( 'nocache_headers' ) ) nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
		$back = Digitalisimo_Backups::admin_page_url( 'migrate' );
		echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Clave de migración</title><style>body{font:15px/1.5 system-ui,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#17213a}textarea{width:100%;font:13px/1.4 ui-monospace,monospace;padding:10px;border:1px solid #c6cfe0;border-radius:9px}.aviso{padding:12px 16px;border-left:4px solid #c72f91;background:#fbf2f8}</style></head><body>';
		echo '<h1>Clave de migración</h1><p class="aviso"><strong>Se muestra una sola vez.</strong> Cópiala ahora y pégala en Backups → Herramientas → Migrar del sitio de destino. Caduca el ' . esc_html( gmdate( 'Y-m-d H:i', (int) $result['expires'] ) ) . ' UTC, sirve para una sola migración y la puedes revocar en esta pantalla. Quien la tenga puede llevarse una copia completa de este sitio: no la envíes por un canal inseguro.</p>';
		echo '<textarea id="clave" rows="5" readonly autocomplete="off" spellcheck="false" onclick="this.select()">' . esc_html( $result['key'] ) . '</textarea>';
		echo '<p><button type="button" onclick="var t=document.getElementById(\'clave\');t.select();if(navigator.clipboard){navigator.clipboard.writeText(t.value);}this.textContent=\'Copiada\'">Copiar</button> · <a href="' . esc_url( $back ) . '">Volver a Backups → Herramientas → Migrar</a></p></body></html>';
		if ( self::$end ) { call_user_func( self::$end ); return; }
		exit;
	}
	/** Gasta el testigo de un formulario de «Generar clave»: false si falta, no es válido o ya se usó. */
	private static function spend_form( $token ) {
		if ( ! preg_match( '/^[0-9a-f]{32}$/D', (string) $token ) ) return false;
		$hash = hash( 'sha256', (string) $token );
		return true === self::change( function( &$state ) use ( $hash ) {
			if ( in_array( $hash, $state['forms'], true ) ) return false;
			$state['forms'] = array_slice( array_merge( $state['forms'], array( $hash ) ), -self::FORM_TOKENS );
			return true;
		} );
	}
	public static function revoke_request() {
		self::guard( self::ACTION_REVOKE );
		$result = Digitalisimo_Backups::service_migration_revoke_key( sanitize_key( wp_unslash( $_POST['key'] ?? '' ) ) );
		Digitalisimo_Backups::redirect( ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : 'Clave revocada: ya no sirve y su paquete se borró.', null, 'migrate' );
	}
	public static function pull_request() {
		self::guard( self::ACTION_PULL );
		// La clave se usa tal cual (sanitize_text_field la alteraría) y nunca vuelve en la redirección.
		$key = isset( $_POST['migration_key'] ) && is_string( $_POST['migration_key'] ) ? (string) wp_unslash( $_POST['migration_key'] ) : '';
		$keep = empty( $_POST['keep_access_shown'] ) || ! empty( $_POST['keep_access'] );
		$result = Digitalisimo_Backups::service_migrate( $key, array( 'network' => is_multisite(), 'confirm' => 'MIGRAR', 'keep_access' => $keep, 'via' => 'admin-post' ) );
		if ( is_wp_error( $result ) ) Digitalisimo_Backups::redirect( false, 'La migración no empezó: ' . $result->get_error_message(), null, 'migrate' );
		Digitalisimo_Backups::redirect( true, 'Migración en cola desde ' . $result['source'] . '. Su avance aparece en «Trabajos»: deja esta página abierta (o WP-Cron la continúa). ' . ( $keep ? 'Al terminar sigues entrando con tu cuenta; el aviso de Backups dice la URL de acceso.' : 'Al terminar, inicia sesión con un usuario del sitio de origen.' ), null, 'migrate' );
	}
	public static function undo_request() {
		self::guard( self::ACTION_UNDO );
		$result = Digitalisimo_Backups::service_migration_undo( array( 'network' => is_multisite(), 'confirm' => 'DESHACER', 'via' => 'admin-post' ) );
		if ( is_wp_error( $result ) ) Digitalisimo_Backups::redirect( false, 'No se deshizo la migración: ' . $result->get_error_message(), null, 'migrate' );
		if ( ! empty( $result['restoring'] ) ) Digitalisimo_Backups_Restore_Maintenance::send_cookie( (string) ( $result['maintenance_token'] ?? '' ) );
		if ( ! empty( $result['restoring'] ) ) Digitalisimo_Backups::redirect( true, 'Deshaciendo la migración: la copia previa ' . $result['file'] . ' se está restaurando por pasos (su avance está en «Trabajos»; WP-Cron la continúa). Al terminar, inicia sesión con un usuario de este sitio anterior a la migración.', null, 'migrate' );
		Digitalisimo_Backups::redirect( true, 'Migración deshecha: el sitio volvió al estado de ' . $result['file'] . '. Inicia sesión con un usuario de este sitio anterior a la migración.', null, 'migrate' );
	}
	public static function cleanup_request() {
		self::guard( self::ACTION_CLEANUP );
		// En una red, las tablas son de la red: esta pantalla es la de la red (alcance explícito, como deshacer).
		$result = Digitalisimo_Backups::service_migration_cleanup( array( 'network' => is_multisite() ) );
		if ( is_wp_error( $result ) ) Digitalisimo_Backups::redirect( false, 'No se borraron las tablas apartadas: ' . $result->get_error_message(), null, 'migrate' );
		Digitalisimo_Backups::redirect( ! $result['errors'], $result['message'], null, 'migrate' );
	}
	public static function settings_request() {
		self::guard( self::ACTION_SETTINGS );
		if ( is_multisite() && ! is_main_site() ) Digitalisimo_Backups::redirect( false, 'En una red, la migración se configura desde la administración de red.', null, 'migrate' );
		self::set_allow_http( ! empty( $_POST['allow_http'] ) );
		Digitalisimo_Backups::redirect( true, ! empty( $_POST['allow_http'] ) ? 'Laboratorio activado (HTTP y direcciones internas): úsalo sólo en un laboratorio.' : 'La migración vuelve a exigir HTTPS y sitios públicos.', null, 'migrate' );
	}
}
// La migración que este sitio tira de otro es un tipo de trabajo de la cola de «Trabajos».
Digitalisimo_Backups_Jobs::register_kind( 'migrate', 'Digitalisimo_Backups_Migrate' );
