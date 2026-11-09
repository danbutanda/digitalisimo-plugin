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
	const KEY_PREFIX = 'DGMIG1.';
	const HEADER = 'X-Digitalisimo-Migration';
	const CLIENT_HEADER = 'X-Digitalisimo-Migration-Client';
	const DIRECTORY = 'migrations';
	const NAMESPACE_V1 = 'digitalisimo-backups/v1';
	const SOURCE_ROUTE = '/migrate/source';
	/** Fallos de autenticación con una clave antes de bloquearla, y fallos de cualquier origen en la ventana antes de cerrar. */
	const MAX_FAILURES = 5;
	const GLOBAL_FAILURES = 20;
	const WINDOW = 900;
	const DEFAULT_HOURS = 24;
	const MAX_HOURS = 72;
	const CHUNK = 4194304;
	const MAX_CHUNK = 16777216;
	const LOG_MAX = 100;
	const KEYS_MAX = 50;
	const LIVE = array( 'pending', 'in_use' );
	const ACTION_CREATE = 'digitalisimo_backups_migration_key';
	const ACTION_REVOKE = 'digitalisimo_backups_migration_revoke';
	const ACTION_PULL = 'digitalisimo_backups_migration_pull';
	const ACTION_UNDO = 'digitalisimo_backups_migration_undo';
	const ACTION_SETTINGS = 'digitalisimo_backups_migration_settings';
	/** Transporte HTTP del destino (sólo pruebas): callable( $method, $url, $args ) con la forma de wp_remote_request(). */
	public static $transport = null;
	/** Espera entre reintentos inmediatos de un fragmento (sustituible en las pruebas). */
	public static $pause = 'sleep';
	/** Fin de la página que muestra la clave (sustituible en las pruebas; en WordPress, exit). */
	public static $end = null;

	public static function init() {
		foreach ( array( self::ACTION_CREATE => 'create_request', self::ACTION_REVOKE => 'revoke_request', self::ACTION_PULL => 'pull_request', self::ACTION_UNDO => 'undo_request', self::ACTION_SETTINGS => 'settings_request' ) as $action => $method ) add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		add_action( Digitalisimo_Backups_Jobs::HOOK_WATCHDOG, array( __CLASS__, 'sweep' ), 20 );
	}

	/* ------------------------------------------------------------------ Tipo de trabajo `migrate` (Digitalisimo_Backups_Jobs::register_kind) */

	public static function job_phases() { return array( 'preflight', 'remote', 'download', 'check', 'restore', 'finish' ); }
	/** Datos de las fases propias (`preflight` es la del respaldo). Una restauración interrumpida no se repite sola. */
	public static function job_phase( $phase ) {
		$phases = array(
			'remote' => array( 'label' => 'Paquete en el origen', 'floor' => 3, 'max' => 5, 'cancellable' => true, 'status' => 'running' ),
			'download' => array( 'label' => 'Descarga', 'floor' => 40, 'max' => 5, 'cancellable' => true, 'status' => 'transferring' ),
			'check' => array( 'label' => 'Comprobación del paquete', 'floor' => 75, 'max' => 3, 'cancellable' => true, 'status' => 'verifying' ),
			'restore' => array( 'label' => 'Restauración', 'floor' => 78, 'max' => 1, 'cancellable' => false, 'status' => 'running', 'interrupted' => ' El sitio pudo quedar a medias: pulsa «Deshacer la migración» en Backups → Migrar (o `wp digitalisimo-backups migration undo`) para volver al estado anterior. Si el hosting corta las peticiones largas, migra con WP-CLI o con el cron del sistema.' ),
			'finish' => array( 'label' => 'Cierre', 'floor' => 98, 'max' => 3, 'cancellable' => false, 'status' => 'running' ),
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
			'fails' => is_array( $saved['fails'] ?? null ) ? array_values( array_map( 'intval', $saved['fails'] ) ) : array(),
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
	public static function before_restore() { self::$held = Digitalisimo_Backups_Keys::get( self::OPTION, null ); }
	public static function after_restore() {
		if ( is_array( self::$held ) ) Digitalisimo_Backups_Keys::put( self::OPTION, self::$held );
	}
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
					if ( in_array( $record['state'], self::LIVE, true ) && $now > (int) $record['expires'] ) {
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
				$state['fails'] = array_values( array_filter( $state['fails'], function( $at ) use ( $now ) { return $now - $at < self::WINDOW; } ) );
				return $closed;
			} );
			foreach ( (array) $closed as $record ) self::retire( $record );
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
		}
	}
	/** Si una huella de clave es la de un paquete de una clave viva de este sitio (la usa Digitalisimo_Backups_Keys). */
	public static function knows_key( $key_id ) {
		foreach ( self::state()['keys'] as $record ) if ( hash_equals( (string) ( $record['key_id'] ?? '' ), (string) $key_id ) ) return in_array( $record['state'], self::LIVE, true ) && time() <= (int) $record['expires'];
		return false;
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
	 * Autentica una petición del destino: límite global de intentos, credencial (comparación de tiempo constante con la
	 * huella guardada), límite por clave, caducidad, estado, HTTPS y, salvo para presentarse, el sitio al que quedó
	 * atada la clave. Devuelve la fila de la clave o un WP_Error sin ningún secreto.
	 */
	public static function authenticate( $header, $client, $action ) {
		$now = time();
		$state = self::state();
		$recent = array_filter( $state['fails'], function( $at ) use ( $now ) { return $now - $at < self::WINDOW; } );
		if ( count( $recent ) >= self::GLOBAL_FAILURES ) return self::error( 'locked', 'Demasiados intentos fallidos de migración en este sitio: espera 15 minutos antes de volver a intentarlo.', 429 );
		if ( ! is_ssl() && 'https' !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) ) && ! $state['allow_http'] ) return self::error( 'https', 'El sitio de origen exige HTTPS para migrar. En un laboratorio, activa «Permitir HTTP» en Backups → Migrar de los dos sitios.', 403 );
		$header = trim( (string) $header );
		$parts = explode( '.', $header, 2 );
		$auth = 2 === count( $parts ) ? Digitalisimo_Backups_Keys::b64url_decode( $parts[1], 32 ) : false;
		$id = $parts[0];
		if ( false === $auth || ! self::valid_id( $id ) || ! isset( $state['keys'][ $id ] ) ) {
			self::fail( '', 'Credencial ausente, mal formada o de una clave desconocida.' );
			return self::error( 'unauthorized', 'La clave de migración no es válida para este sitio.', 401 );
		}
		$record = $state['keys'][ $id ];
		if ( ! hash_equals( (string) $record['verifier'], self::verifier_of( $auth ) ) ) {
			$blocked = self::fail( $id, 'Credencial equivocada.' );
			return self::error( 'unauthorized', 'La clave de migración no es válida para este sitio.' . ( $blocked ? ' Se bloqueó tras ' . self::MAX_FAILURES . ' intentos fallidos.' : '' ), 401 );
		}
		Digitalisimo_Backups_Sodium::memzero( $auth );
		if ( in_array( $record['state'], self::LIVE, true ) && $now > (int) $record['expires'] ) {
			self::sweep();
			return self::error( 'expired', 'La clave de migración caducó. Genera otra en el sitio de origen.', 410 );
		}
		$closed = array( 'used' => 'Esta clave de migración ya se usó: genera otra en el sitio de origen.', 'revoked' => 'Esta clave de migración fue revocada en el sitio de origen.', 'expired' => 'La clave de migración caducó. Genera otra en el sitio de origen.', 'blocked' => 'Esta clave de migración se bloqueó por intentos fallidos. Genera otra en el sitio de origen.' );
		if ( isset( $closed[ $record['state'] ] ) ) return self::error( $record['state'], $closed[ $record['state'] ], 410 );
		$client = (string) $client;
		if ( 'hello' !== $action ) {
			if ( ! Digitalisimo_Backups_Job_Store::valid_id( $client ) ) return self::error( 'client', 'Falta el identificador de la migración del sitio de destino.', 400 );
			if ( '' !== $record['client'] && ! hash_equals( (string) $record['client'], hash( 'sha256', $client ) ) ) {
				self::log( $id, 'other_client', $action );
				return self::error( 'in_use', 'Otra migración ya usa esta clave. Una clave sirve para una sola migración: genera otra en el sitio de origen.', 409 );
			}
		}
		return $record;
	}
	/** Anota un intento fallido; con MAX_FAILURES en la misma clave, la bloquea. Devuelve si la bloqueó. */
	private static function fail( $id, $reason ) {
		$blocked = null;
		try {
			$blocked = self::change( function( &$state ) use ( $id, $reason ) {
				$state['fails'][] = time();
				$state['fails'] = array_slice( $state['fails'], -( self::GLOBAL_FAILURES * 2 ) );
				self::log_into( $state, $id, 'auth_failed', $reason );
				if ( '' === $id || ! isset( $state['keys'][ $id ] ) ) return null;
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
	 */
	public static function source_package( array $record, $client, array $body ) {
		$id = (string) $record['id'];
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
			return self::error( 'not_ready', 'El sitio de origen no puede preparar el paquete: ' . $e->getMessage(), 422 );
		}
		$job = $queued['job'];
		$site = self::site_label( $body['site'] ?? '' );
		$record = self::change( function( &$state ) use ( $id, $client, $job, $site ) {
			if ( ! isset( $state['keys'][ $id ] ) ) return false;
			$state['keys'][ $id ]['state'] = 'in_use';
			$state['keys'][ $id ]['client'] = hash( 'sha256', (string) $client );
			$state['keys'][ $id ]['job'] = (string) $job['id'];
			if ( '' !== $site ) $state['keys'][ $id ]['site'] = $site;
			self::log_into( $state, $id, 'package', $site );
			return $state['keys'][ $id ];
		} );
		if ( false === $record ) return self::error( 'unauthorized', 'La clave de migración ya no existe.', 410 );
		Digitalisimo_Backups_Jobs::schedule();
		return self::source_view( $record, $job );
	}
	/** Hace avanzar la cola de este sitio (donde espera el paquete) con el presupuesto de una petición web y responde el estado del paquete. */
	public static function source_step( array $record ) {
		$job = Digitalisimo_Backups_Job_Store::valid_id( (string) $record['job'] ) ? Digitalisimo_Backups_Jobs::get( (string) $record['job'] ) : null;
		if ( ! $job ) return self::error( 'no_package', 'El sitio de origen no tiene un paquete en preparación para esta clave.', 409 );
		$busy = false;
		if ( ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) {
			if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
			$tick = Digitalisimo_Backups_Jobs::tick();
			$busy = 'busy' === $tick['result'];
			$job = Digitalisimo_Backups_Jobs::get( (string) $job['id'] );
		}
		$view = self::source_view( $record, $job );
		if ( $busy && ! $view['ready'] ) $view['state'] = 'busy';
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
	 * Un fragmento del paquete (`Range: bytes=a-b`, como mucho MAX_CHUNK bytes): devuelve los bytes con su posición, el
	 * tamaño total y el SHA-256 del paquete. Un rango fuera del paquete responde 416.
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
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) return self::error( 'read', 'No se pudo leer el paquete en el sitio de origen.', 500 );
		try {
			if ( 0 !== fseek( $handle, $start ) ) return self::error( 'read', 'No se pudo leer el paquete en el sitio de origen.', 500 );
			$bytes = '';
			$length = $end - $start + 1;
			while ( strlen( $bytes ) < $length && ! feof( $handle ) ) {
				$chunk = fread( $handle, $length - strlen( $bytes ) );
				if ( false === $chunk || '' === $chunk ) break;
				$bytes .= $chunk;
			}
		} finally {
			fclose( $handle );
		}
		if ( strlen( $bytes ) !== $length ) return self::error( 'read', 'No se pudo leer el paquete completo en el sitio de origen.', 500 );
		if ( 0 === $start ) self::log( $record['id'], 'download' );
		return array( 'bytes' => $bytes, 'start' => $start, 'end' => $end, 'total' => $total, 'sha256' => (string) ( $job['result']['sha256'] ?? '' ) );
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
		$invalid = 'La clave de migración no es válida: cópiala completa desde Backups → Migrar del sitio de origen.';
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
	public static function enqueue_pull( $key, $via ) {
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
			if ( 'https' !== strtolower( (string) wp_parse_url( $parsed['url'], PHP_URL_SCHEME ) ) && ! self::allow_http() ) throw new Digitalisimo_Backups_Permanent_Error( 'La clave apunta a un sitio sin HTTPS (' . wp_parse_url( $parsed['url'], PHP_URL_HOST ) . '). Activa HTTPS en el origen o, sólo en un laboratorio, «Permitir HTTP» en Backups → Migrar de los dos sitios.' );
			$migration = array( 'role' => 'destination', 'url' => $parsed['url'], 'id' => $parsed['id'], 'key_id' => Digitalisimo_Backups_Keys::key_id( $root ), 'source_host' => (string) wp_parse_url( $parsed['url'], PHP_URL_HOST ), 'secret' => $token );
			self::seal_file( $file, $auth . $root );
			$hello = self::call( $migration, $auth, '', 'hello', 'POST', array( 'site' => home_url() ), 30 );
			self::compatible( $hello );
			$migration['source'] = self::source_summary( $hello );
			$migration['source_host'] = (string) wp_parse_url( (string) $hello['home'], PHP_URL_HOST ) ?: $migration['source_host'];
			$spec = self::pull_spec( $migration, $via );
			$queued = Digitalisimo_Backups_Jobs::enqueue( $spec );
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
	private static function call( array $migration, $auth, $client, $action, $method, $body = array(), $timeout = 60, array $headers = array() ) {
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
		$url = self::endpoint( (string) $migration['url'], $action );
		$response = self::$transport ? call_user_func( self::$transport, $method, $url, $args ) : wp_remote_request( $url, $args );
		$host = (string) wp_parse_url( (string) $migration['url'], PHP_URL_HOST );
		if ( is_wp_error( $response ) ) throw new Exception( 'No se pudo conectar con el sitio de origen (' . $host . '): ' . $response->get_error_message() );
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw = (string) wp_remote_retrieve_body( $response );
		if ( 'download' === $action && in_array( $code, array( 200, 206 ), true ) ) return array( 'code' => $code, 'body' => $raw, 'range' => (string) wp_remote_retrieve_header( $response, 'content-range' ), 'sha256' => strtolower( (string) wp_remote_retrieve_header( $response, 'x-digitalisimo-package-sha256' ) ) );
		$data = json_decode( $raw, true );
		$message = is_array( $data ) && is_string( $data['message'] ?? null ) ? $data['message'] : 'HTTP ' . $code;
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
			case 'finish': return self::finish( $job );
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
		if ( empty( $job['checkpoint']['remote']['requested'] ) ) self::call( $migration, $auth, (string) $job['id'], 'package', 'POST', $body, 60 );
		$view = self::call( $migration, $auth, (string) $job['id'], 'step', 'POST', $body, (int) self::filter( 'digitalisimo_backups_migration_step_timeout', 600 ) );
		Digitalisimo_Backups_Sodium::memzero( $auth );
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
						$piece = self::fetch_piece( $job, $auth, $offset, $end, $size, $sha );
					} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
						throw $e;
					} catch ( Exception $e ) {
						$failure = $e;
						if ( 0 === $try ) call_user_func( self::$pause, 1 );
					}
				}
				if ( null === $piece ) break;
				$failure = null;
				$handle = @fopen( $part, 'ab' );
				if ( ! $handle ) throw new Exception( 'No se pudo escribir la descarga de la migración en el almacén privado.' );
				$written = fwrite( $handle, $piece );
				$flushed = fflush( $handle );
				fclose( $handle );
				clearstatcache( true, $part );
				if ( strlen( $piece ) !== $written || ! $flushed || $offset + strlen( $piece ) !== (int) filesize( $part ) ) throw new Exception( 'La descarga de la migración no se pudo guardar completa (¿disco lleno?).' );
				$offset += strlen( $piece );
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
	/** Un fragmento exacto: 206, `Content-Range` con la posición pedida y el total anunciado, y el largo justo. */
	private static function fetch_piece( array $job, $auth, $start, $end, $size, $sha ) {
		$reply = self::call( $job['spec']['migration'], $auth, (string) $job['id'], 'download', 'GET', array(), 120, array( 'Range' => 'bytes=' . $start . '-' . $end ) );
		$body = (string) $reply['body'];
		if ( 200 === $reply['code'] ) {
			if ( 0 !== $start || strlen( $body ) !== $size ) throw new Exception( 'El origen no respetó el rango pedido.' );
			return $body;
		}
		if ( ! preg_match( '/^bytes (\d+)-(\d+)\/(\d+)$/D', trim( (string) $reply['range'] ), $m ) || (int) $m[1] !== $start || (int) $m[3] !== $size || (int) $m[2] < $start || (int) $m[2] > $end ) throw new Exception( 'El fragmento recibido no es el pedido (bytes ' . $start . '-' . $end . ').' );
		if ( '' !== $reply['sha256'] && ! hash_equals( $sha, $reply['sha256'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El paquete del origen cambió durante la descarga (otro SHA-256).' );
		if ( strlen( $body ) !== (int) $m[2] - $start + 1 ) throw new Exception( 'El fragmento llegó incompleto.' );
		return $body;
	}
	/** Antes de descifrar: la cabecera DGBK dice que el paquete está cifrado con la clave pegada y su tamaño cuadra. */
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
		return array( 'next' => 'restore', 'checkpoint' => array( 'at' => time() ), 'message' => 'Descifrando, comprobando la firma y restaurando con la copia previa…' );
	}
	/**
	 * Descifra y comprueba la firma, y restaura con el mismo motor que «Restaurar»: todo lo que puede rechazar el paquete
	 * ocurre antes de la copia previa; la copia previa (bloqueada y marcada como la de esta migración) es la que
	 * «Deshacer la migración» restaura. Este sitio conserva su dirección. No se repite sola si se interrumpe.
	 */
	private static function restore( array $job ) {
		list( $auth, $root ) = self::pull_secrets( $job );
		$path = self::work_dir( $job ) . '/package.dgbk';
		$migration = $job['spec']['migration'];
		$report = null;
		$tag = array( 'job' => (string) $job['id'], 'from' => (string) ( $migration['source']['home'] ?? $migration['source_host'] ), 'at' => time(), 'state' => 'migrated' );
		try {
			try {
				$report = Digitalisimo_Backups_Security::open( $path, array(), base64_encode( $root ) );
			} catch ( Exception $e ) {
				throw new Digitalisimo_Backups_Permanent_Error( 'El paquete no supera la comprobación de su cifrado o de su firma: ' . $e->getMessage() . ' No se cambió nada de este sitio.' );
			}
			if ( 'signed' !== $report['trust'] || 'migration' !== ( $report['key']['source'] ?? '' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El paquete no está firmado con la clave de esta migración. No se cambió nada de este sitio.' );
			// La copia previa se crea con el motor de trabajos (run_inline) dentro de este paso, que queda en pausa.
			$paused = Digitalisimo_Backups_Jobs::suspend();
			try {
				Digitalisimo_Backups::migration_restore( $report, array( 'migration' => $tag ) );
			} catch ( Throwable $e ) {
				$started = '' !== self::safety_of( (string) $job['id'] );
				throw new Digitalisimo_Backups_Permanent_Error( $started ? $e->getMessage() : 'El paquete se rechazó antes de tocar este sitio: ' . $e->getMessage() );
			} finally {
				Digitalisimo_Backups_Jobs::resume( $paused );
			}
		} finally {
			Digitalisimo_Backups_Security::close( $report );
			Digitalisimo_Backups_Sodium::memzero( $root );
		}
		@unlink( $path );
		$safety = self::safety_of( (string) $job['id'] );
		// Tras el SQL la sesión del navegador ya no vale (los usuarios son los del origen): el cierre se intenta aquí mismo.
		$closed = self::close_on_source( $job, $auth );
		Digitalisimo_Backups_Sodium::memzero( $auth );
		$message = 'Migración terminada: este sitio es ahora una copia de ' . $migration['source_host'] . ' con su propia dirección. Inicia sesión con un usuario del sitio de origen. La copia previa ' . $safety . ' la deshace.';
		if ( $closed ) {
			self::forget_secrets( $job );
			self::remove_work( $job );
			return array( 'next' => null, 'checkpoint' => array( 'safety' => $safety, 'closed' => true ), 'result' => array( 'safety' => $safety, 'source' => $migration['source_host'] ), 'message' => $message );
		}
		return array( 'next' => 'finish', 'checkpoint' => array( 'safety' => $safety ), 'result' => array( 'safety' => $safety, 'source' => $migration['source_host'] ), 'message' => $message );
	}
	/** Avisa al origen de que la clave quedó usada. Un fallo después de restaurar nunca deshace nada: la clave caduca sola. */
	private static function finish( array $job ) {
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
		$attempt = (int) ( $job['attempts']['finish'] ?? 0 ) + 1;
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
		foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) if ( (string) ( $row['migration']['job'] ?? '' ) === (string) $job_id ) return (string) ( $row['file'] ?? '' );
		return '';
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
			if ( ! in_array( (string) ( $job['phase'] ?? '' ), array( 'restore', 'finish' ), true ) && ! empty( $job['checkpoint']['preflight'] ) ) {
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
			if ( 'migrated' !== ( $row['migration']['state'] ?? '' ) ) return null;
			return $row;
		}
		return null;
	}

	/* ------------------------------------------------------------------ Pantalla */

	private static function megabytes( $bytes ) { return Digitalisimo_Backups_Archive_Inspector::megabytes( (int) $bytes ); }
	private static function filter( $hook, $value ) { return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value ) : $value; }
	private static function event_label( $event ) {
		$labels = array( 'created' => 'Clave generada', 'hello' => 'El destino se presentó', 'package' => 'Paquete pedido', 'ready' => 'Paquete listo', 'download' => 'Descarga iniciada', 'used' => 'Migración terminada: clave usada', 'released' => 'El destino abandonó: clave liberada', 'revoked' => 'Clave revocada', 'expired' => 'Clave caducada', 'blocked' => 'Clave bloqueada por intentos fallidos', 'auth_failed' => 'Intento fallido', 'other_client' => 'Otro sitio intentó usar una clave en uso', 'lab_on' => '«Permitir HTTP» activado', 'lab_off' => '«Permitir HTTP» desactivado', 'undone' => 'Migración deshecha', 'migrated' => 'Migración recibida' );
		return $labels[ $event ] ?? $event;
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
		echo '<section class="digitalisimo-backups-card" id="digitalisimo-backups-migrate-pull"><h2>Migrar desde otro sitio</h2><p>Pega la clave de migración que generaste en el sitio de origen y pulsa «Migrar». Este ' . ( is_multisite() ? 'red' : 'sitio' ) . ' se reemplaza por completo (base de datos y archivos) con el del origen y <strong>conserva su dirección</strong> (' . esc_html( home_url() ) . '). Antes se crea una copia previa bloqueada: «Deshacer la migración» vuelve a dejarlo como estaba. El origen no cambia.</p>';
		if ( ! $main ) echo '<p class="notice notice-warning inline">En una red, la migración se inicia desde la administración de red: reemplaza la red entera.</p>';
		elseif ( '' !== $status['pull_problem'] ) echo '<p class="notice notice-warning inline">' . esc_html( $status['pull_problem'] ) . '</p>';
		echo '<form method="post" action="' . $post . '" onsubmit="return confirm(\'¿Reemplazar este sitio por el del origen? Se crea antes una copia previa para deshacerlo.\')">';
		wp_nonce_field( self::ACTION_PULL );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_PULL ) . '">' . self::context_fields( $network ) . '<p><label for="digitalisimo-backups-migration-key">Clave de migración</label><br><input type="password" id="digitalisimo-backups-migration-key" name="migration_key" value="" autocomplete="off" spellcheck="false" class="large-text"></p><p><button class="button button-primary button-hero"' . disabled( ! $main || '' !== $status['pull_problem'], true, false ) . '>Migrar a este sitio</button></p></form>';
		if ( $status['active'] ) echo '<p class="description">Migración en curso: ' . esc_html( $status['active']['status_label'] . ' · ' . $status['active']['phase_label'] . ' (' . (int) $status['active']['percent'] . ' %)' ) . '. Su avance está en «Trabajos»; cuando termine, este sitio te pedirá iniciar sesión con un usuario del origen.</p>';
		if ( $status['undo'] ) {
			echo '<div class="digitalisimo-backups-migrate-undo"><h3>Deshacer la migración</h3><p>Última migración recibida de <strong>' . esc_html( $status['undo']['from'] ) . '</strong> el ' . esc_html( $status['undo']['date'] ) . '. Deshacerla restaura la copia previa <code>' . esc_html( $status['undo']['file'] ) . '</code> (y antes guarda el estado actual en otra copia previa).</p><form method="post" action="' . $post . '" onsubmit="return confirm(\'¿Deshacer la migración y volver al estado anterior de este sitio?\')">';
			wp_nonce_field( self::ACTION_UNDO );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_UNDO ) . '">' . self::context_fields( $network ) . '<button class="button">Deshacer la migración</button></form></div>';
		}
		echo '</section>';
		// Origen.
		echo '<section class="digitalisimo-backups-card" id="digitalisimo-backups-migrate-keys"><h2>Generar clave de migración</h2><p>Para llevar este ' . ( is_multisite() ? 'red' : 'sitio' ) . ' a otro servidor o dominio (o a una copia de pruebas), genera una clave y pégala en Backups → Migrar del sitio de destino. Sirve para una sola migración, caduca y se puede revocar; se muestra una sola vez. El paquete viaja cifrado de extremo a extremo y este sitio no cambia.</p>';
		if ( '' !== $status['key_problem'] ) echo '<p class="notice notice-warning inline">' . esc_html( $status['key_problem'] ) . '</p>';
		echo '<form method="post" action="' . $post . '">';
		wp_nonce_field( self::ACTION_CREATE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CREATE ) . '">' . self::context_fields( $network ) . '<p><label>Caduca en <select name="hours"><option value="1">1 hora</option><option value="6">6 horas</option><option value="24" selected>24 horas</option><option value="72">72 horas</option></select></label> <button class="button button-primary"' . disabled( '' !== $status['key_problem'], true, false ) . '>Generar clave de migración</button></p></form>';
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
		// Staging, laboratorio y registro.
		echo '<section class="digitalisimo-backups-card"><h2>Copia de pruebas (staging)</h2><p>La migración sirve también para una copia de pruebas en otro dominio o subdominio: genera la clave en producción y migra desde el sitio de pruebas. Para <strong>publicar</strong> la copia de pruebas, haz la migración en sentido contrario: genera la clave en el sitio de pruebas y migra desde producción; producción guarda su copia previa y «Deshacer la migración» la devuelve. No se fusionan cambios entre dos sitios vivos: lo que cambie en producción mientras tanto se reemplaza.</p><p>Las automatizaciones y los destinos remotos del origen llegan con la migración: en una copia de pruebas, revísalos para que no respalde sobre los destinos de producción.</p></section>';
		echo '<section class="digitalisimo-backups-card"><h2>Laboratorio</h2><form method="post" action="' . $post . '">';
		wp_nonce_field( self::ACTION_SETTINGS );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SETTINGS ) . '">' . self::context_fields( $network ) . '<p><label><input type="checkbox" name="allow_http" value="1"' . checked( $status['allow_http'], true, false ) . '> Permitir HTTP sin TLS (sólo laboratorio)</label></p><p class="description">Sin HTTPS, la credencial de la clave viaja legible por la red: quien vea el tráfico podría descargar el paquete (que sigue cifrado con una clave que nunca viaja) o interrumpir la migración. Actívalo en los dos sitios sólo para pruebas en una red propia.</p><p><button class="button">Guardar</button></p></form></section>';
		echo '<section class="digitalisimo-backups-card"><h2>Registro de la migración</h2>';
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
		echo '<h1>Clave de migración</h1><p class="aviso"><strong>Se muestra una sola vez.</strong> Cópiala ahora y pégala en Backups → Migrar del sitio de destino. Caduca el ' . esc_html( gmdate( 'Y-m-d H:i', (int) $result['expires'] ) ) . ' UTC, sirve para una sola migración y la puedes revocar en esta pantalla. Quien la tenga puede llevarse una copia completa de este sitio: no la envíes por un canal inseguro.</p>';
		echo '<textarea id="clave" rows="5" readonly autocomplete="off" spellcheck="false" onclick="this.select()">' . esc_html( $result['key'] ) . '</textarea>';
		echo '<p><button type="button" onclick="var t=document.getElementById(\'clave\');t.select();if(navigator.clipboard){navigator.clipboard.writeText(t.value);}this.textContent=\'Copiada\'">Copiar</button> · <a href="' . esc_url( $back ) . '">Volver a Backups → Migrar</a></p></body></html>';
		if ( self::$end ) { call_user_func( self::$end ); return; }
		exit;
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
		$result = Digitalisimo_Backups::service_migrate( $key, array( 'network' => is_multisite(), 'confirm' => 'MIGRAR', 'via' => 'admin-post' ) );
		if ( is_wp_error( $result ) ) Digitalisimo_Backups::redirect( false, 'La migración no empezó: ' . $result->get_error_message(), null, 'migrate' );
		Digitalisimo_Backups::redirect( true, 'Migración en cola desde ' . $result['source'] . '. Su avance aparece en «Trabajos»: deja esta página abierta (o WP-Cron la continúa). Al terminar, inicia sesión con un usuario del sitio de origen.', null, 'migrate' );
	}
	public static function undo_request() {
		self::guard( self::ACTION_UNDO );
		$result = Digitalisimo_Backups::service_migration_undo( array( 'network' => is_multisite(), 'confirm' => 'DESHACER' ) );
		if ( is_wp_error( $result ) ) Digitalisimo_Backups::redirect( false, 'No se deshizo la migración: ' . $result->get_error_message(), null, 'migrate' );
		Digitalisimo_Backups::redirect( true, 'Migración deshecha: el sitio volvió al estado de ' . $result['file'] . '. Inicia sesión con un usuario de este sitio anterior a la migración.', null, 'migrate' );
	}
	public static function settings_request() {
		self::guard( self::ACTION_SETTINGS );
		if ( is_multisite() && ! is_main_site() ) Digitalisimo_Backups::redirect( false, 'En una red, la migración se configura desde la administración de red.', null, 'migrate' );
		self::set_allow_http( ! empty( $_POST['allow_http'] ) );
		Digitalisimo_Backups::redirect( true, ! empty( $_POST['allow_http'] ) ? '«Permitir HTTP» activado: úsalo sólo en un laboratorio.' : 'La migración vuelve a exigir HTTPS.', null, 'migrate' );
	}
}
// La migración que este sitio tira de otro es un tipo de trabajo de la cola de «Trabajos».
Digitalisimo_Backups_Jobs::register_kind( 'migrate', 'Digitalisimo_Backups_Migrate' );
