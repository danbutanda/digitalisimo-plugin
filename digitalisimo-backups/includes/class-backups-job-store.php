<?php
defined( 'ABSPATH' ) || exit;

/** Fallo que repetir no arregla: falta un requisito, hay un enlace simbólico, un archivo ilegible o credenciales rechazadas. */
class Digitalisimo_Backups_Permanent_Error extends Exception {}
/** El administrador pidió cancelar: la fase en curso deshace lo suyo y el trabajo termina en «cancelado». */
class Digitalisimo_Backups_Job_Cancelled extends Exception {}
/** Otro proceso tomó el trabajo (almacén compartido entre servidores): éste deja de escribir sin tocar su estado. */
class Digitalisimo_Backups_Job_Lease_Lost extends Exception {}
/** Un punto de control ya no describe el área de trabajo: el trabajo vuelve a la fase que lo produjo. */
class Digitalisimo_Backups_Checkpoint_Invalid extends Exception {
	/** @var string Fase desde la que se repite. */
	public $phase;
	public function __construct( $message, $phase ) {
		parent::__construct( $message );
		$this->phase = $phase;
	}
}

/**
 * Estado persistente de los trabajos en el almacén privado (`<almacén>/jobs/<uuid>/job.json`), nunca en la base de
 * datos: queda fuera del respaldo, una restauración de SQL no lo pisa y se puede escribir mientras la exportación
 * mantiene abierta su instantánea consistente sin entrar en ella.
 */
final class Digitalisimo_Backups_Job_Store {
	const FORMAT = 'digitalisimo-backups-job/v1';
	const JOB_FORMAT = 1;
	const DIRECTORY = 'jobs';
	const LOCK = '.jobs.lock';
	const STATE = 'job.json';
	const EVENTS = 'events.jsonl';
	const CANCEL = 'cancel';
	const STAGING = 'staging';
	const EVENTS_MAX = 262144;
	const KEEP_TERMINAL = 20;
	const KEEP_DAYS = 30;
	const KEEP_MAX = 200;
	const STATUSES = array( 'queued', 'running', 'verifying', 'transferring', 'success', 'failed', 'cancelled' );
	const TERMINAL = array( 'success', 'failed', 'cancelled' );
	private static $lock = null;
	private static $depth = 0;
	private static $prepared = array();

	public static function valid_id( $id ) { return is_string( $id ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id ); }
	public static function new_id() {
		$bytes = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
	}
	public static function is_terminal( $job ) { return in_array( $job['status'] ?? '', self::TERMINAL, true ); }

	/** Carpeta de trabajos dentro del almacén privado, sólo accesible para el usuario de PHP. */
	public static function root() {
		$root = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . self::DIRECTORY;
		if ( isset( self::$prepared[ $root ] ) ) return $root;
		if ( is_link( $root ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de trabajos de Backups no puede ser un enlace simbólico.' );
		if ( ! is_dir( $root ) && ! @mkdir( $root, 0700 ) && ! is_dir( $root ) ) throw new Exception( 'No se pudo crear la carpeta de trabajos de Backups en el almacén privado.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $root, 0700 );
		self::$prepared[ $root ] = true;
		return $root;
	}
	public static function directory( $id ) {
		if ( ! self::valid_id( $id ) ) throw new InvalidArgumentException( 'Identificador de trabajo no válido.' );
		return self::root() . '/' . $id;
	}
	public static function staging( $id ) {
		$path = self::directory( $id ) . '/' . self::STAGING;
		if ( is_link( $path ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El área de trabajo del respaldo no puede ser un enlace simbólico.' );
		if ( ! is_dir( $path ) && ! @mkdir( $path, 0700, true ) && ! is_dir( $path ) ) throw new Exception( 'No se pudo preparar el área de trabajo privada del respaldo.' );
		return $path;
	}

	/** Cerrojo corto y reentrante para leer, modificar y escribir un estado sin que otro proceso se cruce. */
	public static function with_lock( callable $callback ) {
		if ( 0 === self::$depth ) {
			$path = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . self::LOCK;
			$handle = @fopen( $path, 'c' );
			if ( ! $handle ) throw new Exception( 'No se pudo abrir el registro de trabajos de Backups.' );
			if ( ! flock( $handle, LOCK_EX ) ) { fclose( $handle ); throw new Exception( 'No se pudo bloquear el registro de trabajos de Backups.' ); }
			self::$lock = $handle;
		}
		self::$depth++;
		try {
			return $callback();
		} finally {
			self::$depth--;
			if ( 0 === self::$depth && self::$lock ) {
				flock( self::$lock, LOCK_UN );
				fclose( self::$lock );
				self::$lock = null;
			}
		}
	}

	/** Crea la carpeta del trabajo y su primer estado; el estado existe antes que cualquier archivo de trabajo. */
	public static function create( array $job ) {
		return self::with_lock( function() use ( $job ) {
			$dir = self::directory( $job['id'] ?? '' );
			if ( file_exists( $dir ) ) throw new Exception( 'Ya existe un trabajo con ese identificador.' );
			if ( ! @mkdir( $dir, 0700 ) ) throw new Exception( 'No se pudo crear la carpeta del trabajo de Backups.' );
			$job['rev'] = 0;
			return self::write( $job );
		} );
	}

	/** Escritura atómica: temporal propio, vaciado y `rename()`; un lector ve el estado anterior o el nuevo, nunca uno a medias. */
	public static function write( array $job ) {
		$dir = self::directory( $job['id'] ?? '' );
		if ( ! is_dir( $dir ) ) throw new Exception( 'La carpeta del trabajo de Backups ya no existe.' );
		$job['format'] = self::FORMAT;
		$job['rev'] = (int) ( $job['rev'] ?? 0 ) + 1;
		$job['updated_at'] = time();
		$json = json_encode( $job, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
		if ( ! is_string( $json ) ) throw new Exception( 'No se pudo codificar el estado del trabajo de Backups.' );
		self::atomic_put( $dir . '/' . self::STATE, $json . "\n" );
		return $job;
	}
	private static function atomic_put( $path, $contents ) {
		$temp = $path . '.tmp-' . bin2hex( random_bytes( 6 ) );
		$handle = @fopen( $temp, 'xb' );
		if ( ! $handle ) throw new Exception( 'No se pudo escribir el estado del trabajo de Backups.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $temp, 0600 );
		$complete = false;
		try {
			$length = strlen( $contents );
			$offset = 0;
			while ( $offset < $length ) {
				$written = fwrite( $handle, substr( $contents, $offset ) );
				if ( false === $written || 0 === $written ) break;
				$offset += $written;
			}
			$complete = $offset === $length && fflush( $handle );
			if ( $complete && function_exists( 'fsync' ) ) @fsync( $handle );
		} finally {
			fclose( $handle );
		}
		if ( ! $complete || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			throw new Exception( 'No se pudo guardar el estado del trabajo de Backups; revisa el espacio libre del almacén privado.' );
		}
	}

	/** Lee y valida un estado. Uno ilegible se aparta como `job.json.bad-*` y el trabajo queda fallido sin área de trabajo. */
	public static function read( $id ) {
		$path = self::directory( $id ) . '/' . self::STATE;
		if ( ! is_file( $path ) ) return null;
		$job = self::decode( @file_get_contents( $path ), $id );
		if ( $job ) return $job;
		return self::with_lock( function() use ( $id, $path ) {
			if ( ! is_file( $path ) ) return null;
			$raw = @file_get_contents( $path );
			$job = self::decode( $raw, $id );
			if ( $job ) return $job;
			$quarantine = $path . '.bad-' . gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 3 ) );
			$created = (int) @filemtime( $path );
			if ( ! @rename( $path, $quarantine ) ) @unlink( $path );
			self::remove_staging( $id );
			$failed = self::write( array(
				'id' => $id,
				'kind' => 'backup',
				'rev' => 0,
				'spec' => array(),
				'status' => 'failed',
				'phase' => '',
				'progress' => array( 'percent' => 0, 'message' => 'El estado del trabajo estaba dañado.' ),
				'checkpoint' => array(),
				'attempts' => array(),
				'retry_at' => 0,
				'lease' => null,
				'last_seen_at' => 0,
				'cancel_requested_at' => 0,
				'created_at' => $created ?: time(),
				'started_at' => 0,
				'finished_at' => time(),
				'result' => array(),
				'error' => array( 'code' => 'corrupt', 'message' => 'El estado del trabajo estaba dañado y se conservó aparte (' . basename( $quarantine ) . '). El trabajo se dio por fallido y no registró ningún respaldo parcial.', 'phase' => '', 'permanent' => true ),
				'warnings' => array(),
			) );
			self::event( $id, 'corrupt', array( 'kept' => basename( $quarantine ) ) );
			return $failed;
		} );
	}
	private static function decode( $raw, $id ) {
		if ( ! is_string( $raw ) || '' === $raw ) return null;
		$job = json_decode( $raw, true );
		if ( ! is_array( $job ) || self::FORMAT !== ( $job['format'] ?? '' ) || $id !== ( $job['id'] ?? '' ) || ! in_array( $job['kind'] ?? '', array( 'backup', 'restore' ), true ) || ! in_array( $job['status'] ?? '', self::STATUSES, true ) || ! is_array( $job['spec'] ?? null ) ) return null;
		foreach ( array( 'checkpoint', 'attempts', 'result', 'warnings', 'progress' ) as $key ) if ( ! is_array( $job[ $key ] ?? null ) ) $job[ $key ] = array();
		if ( ! is_array( $job['lease'] ?? null ) ) $job['lease'] = null;
		return $job;
	}

	/** Identificadores con estado; las carpetas sin `job.json` (creación interrumpida) las retira `prune()`. */
	public static function ids() {
		$root = self::root();
		$ids = array();
		foreach ( (array) @scandir( $root ) as $name ) {
			if ( self::valid_id( $name ) && is_file( $root . '/' . $name . '/' . self::STATE ) ) $ids[] = $name;
		}
		return $ids;
	}
	/** Todos los trabajos en orden de llegada (FIFO). */
	public static function all() {
		$jobs = array();
		foreach ( self::ids() as $id ) {
			$job = self::read( $id );
			if ( $job ) $jobs[] = $job;
		}
		usort( $jobs, function( $a, $b ) { return array( (float) ( $a['queued_at'] ?? $a['created_at'] ), $a['id'] ) <=> array( (float) ( $b['queued_at'] ?? $b['created_at'] ), $b['id'] ); } );
		return $jobs;
	}

	/** Registro de transiciones, intentos y avisos; conserva el final cuando supera 256 KB. */
	public static function event( $id, $type, array $data = array() ) {
		try {
			self::with_lock( function() use ( $id, $type, $data ) {
				$path = self::directory( $id ) . '/' . self::EVENTS;
				$line = json_encode( array( 'at' => time(), 'type' => (string) $type ) + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
				if ( ! is_string( $line ) ) return;
				if ( false === @file_put_contents( $path, $line . "\n", FILE_APPEND ) ) return;
				if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
				clearstatcache( true, $path );
				if ( (int) @filesize( $path ) <= self::EVENTS_MAX ) return;
				$handle = @fopen( $path, 'rb' );
				if ( ! $handle ) return;
				fseek( $handle, -(int) ( self::EVENTS_MAX / 2 ), SEEK_END );
				$tail = (string) stream_get_contents( $handle );
				fclose( $handle );
				$cut = strpos( $tail, "\n" );
				self::atomic_put( $path, false === $cut ? '' : substr( $tail, $cut + 1 ) );
			} );
		} catch ( Throwable $e ) {
			// El registro de eventos es auxiliar: nunca detiene un trabajo.
		}
	}
	public static function events( $id ) {
		$path = self::directory( $id ) . '/' . self::EVENTS;
		$events = array();
		foreach ( is_file( $path ) ? (array) @file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array() as $line ) {
			$event = json_decode( $line, true );
			if ( is_array( $event ) ) $events[] = $event;
		}
		return $events;
	}

	/** La marca de cancelación es un archivo: el trabajador la comprueba con `file_exists()` sin analizar JSON. */
	public static function request_cancel( $id ) {
		$path = self::directory( $id ) . '/' . self::CANCEL;
		if ( false === @file_put_contents( $path, (string) time() ) ) throw new Exception( 'No se pudo registrar la cancelación del trabajo.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
	}
	public static function cancel_requested( $id ) { return file_exists( self::directory( $id ) . '/' . self::CANCEL ); }
	public static function clear_cancel( $id ) { @unlink( self::directory( $id ) . '/' . self::CANCEL ); }

	public static function remove_staging( $id ) { self::remove_tree( self::directory( $id ) . '/' . self::STAGING ); }
	/** Borra una carpeta del almacén sin seguir enlaces simbólicos. */
	public static function remove_tree( $path ) {
		if ( is_link( $path ) || is_file( $path ) ) { @unlink( $path ); return; }
		if ( ! is_dir( $path ) ) return;
		foreach ( (array) @scandir( $path ) as $name ) {
			if ( '.' === $name || '..' === $name || '' === $name ) continue;
			self::remove_tree( $path . '/' . $name );
		}
		@rmdir( $path );
	}

	/**
	 * Conserva los últimos 20 trabajos terminados o los de 30 días (nunca más de 200) y retira las carpetas que
	 * quedaron sin estado o los temporales de escritura sueltos de más de una hora.
	 */
	public static function prune() {
		self::with_lock( function() {
			$root = self::root();
			$now = time();
			$terminal = array();
			foreach ( (array) @scandir( $root ) as $name ) {
				if ( ! self::valid_id( $name ) ) continue;
				$dir = $root . '/' . $name;
				if ( ! is_file( $dir . '/' . self::STATE ) ) {
					if ( is_dir( $dir ) && $now - (int) @filemtime( $dir ) > 3600 ) self::remove_tree( $dir );
					continue;
				}
				foreach ( (array) glob( $dir . '/' . self::STATE . '.tmp-*' ) as $temp ) if ( $now - (int) @filemtime( $temp ) > 3600 ) @unlink( $temp );
				$job = self::read( $name );
				if ( $job && self::is_terminal( $job ) ) $terminal[] = $job;
			}
			usort( $terminal, function( $a, $b ) { return (int) ( $b['finished_at'] ?? 0 ) <=> (int) ( $a['finished_at'] ?? 0 ); } );
			foreach ( $terminal as $rank => $job ) {
				$old = $now - (int) ( $job['finished_at'] ?? 0 ) > self::KEEP_DAYS * 86400;
				if ( $rank >= self::KEEP_MAX || ( $rank >= self::KEEP_TERMINAL && $old ) ) self::remove_tree( $root . '/' . $job['id'] );
			}
		} );
	}
}
