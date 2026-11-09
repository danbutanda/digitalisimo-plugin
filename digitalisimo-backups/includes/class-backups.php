<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-backups-job-store.php';
require_once __DIR__ . '/class-backups-jobs.php';
require_once __DIR__ . '/class-backups-backup-job.php';
// Seguridad: cifrado, firma y validación de archivos no confiables (ver docs/BACKUPS.md).
foreach ( array( 'sodium', 'keys', 'credentials', 'manifest-auth', 'encrypted-file', 'archive-inspector', 'sql-guard', 'security' ) as $digitalisimo_backups_part ) require_once __DIR__ . '/security/class-' . $digitalisimo_backups_part . '.php';
unset( $digitalisimo_backups_part );

require_once __DIR__ . '/class-backups-layout.php';
// Restaurar en otro dominio, otra ruta del servidor u otro prefijo de tablas (ver «Restaurar en otro dominio»).
require_once __DIR__ . '/class-backups-relocate.php';
require_once __DIR__ . '/class-backups-site-extract.php';
require_once __DIR__ . '/trait-backups-service.php';
/** Respaldo manual completo: base de datos, archivos y copia opcional remota. */
final class Digitalisimo_Backups {
	use Digitalisimo_Backups_Service; // Casos de uso que comparten la pantalla, REST y WP-CLI.
	const OPTION = 'digitalisimo_backups_settings';
	const STORAGE = 'digitalisimo_backups_private_storage';
	const HISTORY = 'digitalisimo_backups_history';
	const OPERATION_LOCK = '.operation.lock';
	const POLICIES = 'digitalisimo_backups_policies';
	const SERVERS = 'digitalisimo_backups_servers';
	const ACTION_SAVE = 'digitalisimo_backups_save';
	const ACTION_RUN = 'digitalisimo_backups_run';
	const ACTION_MANUAL_START = 'digitalisimo_backups_manual_start';
	const ACTION_MANUAL_WORK = 'digitalisimo_backups_manual_work';
	const ACTION_MANUAL_STATUS = 'digitalisimo_backups_manual_status';
	/** Diario de una restauración en curso, en el almacén y fuera de la base de datos que el SQL reemplaza. */
	const RESTORE_JOURNAL = '.restore-journal.json';
	/** Informe de la última restauración que cambió direcciones, rutas o prefijo (sin carga automática). */
	const LAST_RELOCATION = 'digitalisimo_backups_last_relocation';
	/** Tope del avance de la verificación: con cifrado, la verificación deja sitio a la fase de cifrado. */
	private static $verify_top = 98;
	const ACTION_OAUTH = 'digitalisimo_backups_oauth';
	const ACTION_LOCK = 'digitalisimo_backups_lock';
	const ACTION_DOWNLOAD = 'digitalisimo_backups_download';
	const ACTION_DELETE = 'digitalisimo_backups_delete';
	const ACTION_SAVE_POLICIES = 'digitalisimo_backups_save_policies';
	const ACTION_RUN_POLICY = 'digitalisimo_backups_run_policy';
	const ACTION_RESTORE = 'digitalisimo_backups_restore';
	const ACTION_CREATE_AUTOMATION = 'digitalisimo_backups_create_automation';
	const ACTION_UPDATE_AUTOMATION = 'digitalisimo_backups_update_automation';
	const ACTION_DELETE_AUTOMATION = 'digitalisimo_backups_delete_automation';
	public static function init() { self::activate_default_policies(); add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save' ) ); add_action( 'admin_post_' . self::ACTION_RUN, array( __CLASS__, 'run' ) ); add_action( 'wp_ajax_' . self::ACTION_MANUAL_START, array( __CLASS__, 'manual_start' ) ); add_action( 'wp_ajax_' . self::ACTION_MANUAL_WORK, array( __CLASS__, 'manual_work' ) ); add_action( 'wp_ajax_' . self::ACTION_MANUAL_STATUS, array( __CLASS__, 'manual_status' ) ); add_action( 'admin_post_' . self::ACTION_OAUTH, array( __CLASS__, 'oauth_callback' ) ); add_action( 'admin_post_' . self::ACTION_LOCK, array( __CLASS__, 'toggle_lock' ) ); add_action( 'admin_post_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'download' ) ); add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'delete_backup' ) ); add_action( 'admin_post_' . self::ACTION_SAVE_POLICIES, array( __CLASS__, 'save_policies' ) ); add_action( 'admin_post_' . self::ACTION_RUN_POLICY, array( __CLASS__, 'run_policy_request' ) ); add_action( 'admin_post_' . self::ACTION_CREATE_AUTOMATION, array( __CLASS__, 'create_automation' ) ); add_action( 'admin_post_' . self::ACTION_UPDATE_AUTOMATION, array( __CLASS__, 'update_automation' ) ); add_action( 'admin_post_' . self::ACTION_DELETE_AUTOMATION, array( __CLASS__, 'delete_automation' ) ); add_action( 'admin_post_' . self::ACTION_RESTORE, array( __CLASS__, 'restore_request' ) ); add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); add_action( 'digitalisimo_backups_full', array( __CLASS__, 'scheduled_backup' ), 10, 1 ); add_action( 'digitalisimo_backups_db', array( __CLASS__, 'scheduled_backup' ), 10, 1 ); add_action( 'digitalisimo_backups_policy', array( __CLASS__, 'scheduled_policy' ), 10, 2 ); Digitalisimo_Backups_Jobs::init(); Digitalisimo_Backups_Security::init(); add_action( 'admin_init', array( __CLASS__, 'credentials_on_load' ) ); add_action( 'init', array( __CLASS__, 'credentials_on_cron' ) ); }
	private static function defaults() { return array( 'destination' => 'local', 'sftp_name' => 'SFTP', 'google_name' => 'Google Drive', 'onedrive_name' => 'OneDrive', 'sftp_host' => '', 'sftp_port' => '22', 'sftp_user' => '', 'sftp_path' => '/', 'sftp_password' => '', 'sftp_fingerprint' => '', 'google_client_id' => '', 'google_client_secret' => '', 'google_refresh_token' => '', 'google_folder_id' => '', 'onedrive_client_id' => '', 'onedrive_client_secret' => '', 'onedrive_refresh_token' => '', 'onedrive_folder' => 'Digitalisimo Backups', 'retention' => '5', 'full_schedule' => 'disabled', 'database_schedule' => 'disabled' ); }
	/** Los ZIP no deben quedar bajo una ruta servida por Apache, Nginx o el CDN. */
	private static function local_directory() {
		static $directory = null;
		if ( null !== $directory ) return $directory;
		$wp_root = realpath( ABSPATH );
		if ( false === $wp_root ) throw new Exception( 'No se pudo resolver la raíz de WordPress para ubicar los respaldos.' );
		$document_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( (string) $_SERVER['DOCUMENT_ROOT'] ) : false;
		$explicit = defined( 'DIGITALISIMO_BACKUPS_STORAGE_DIR' );
		$saved = is_multisite() ? get_site_option( self::STORAGE, array() ) : get_option( self::STORAGE, array() );
		if ( $explicit ) {
			$path = (string) DIGITALISIMO_BACKUPS_STORAGE_DIR;
		} elseif ( is_array( $saved ) && wp_normalize_path( $wp_root ) === ( $saved['wordpress_root'] ?? '' ) && ! empty( $saved['directory'] ) ) {
			$path = (string) $saved['directory'];
		} else {
			if ( false === $document_root ) throw new Exception( 'No se conoce la raíz pública del servidor. Define DIGITALISIMO_BACKUPS_STORAGE_DIR en wp-config.php con una carpeta privada fuera del sitio.' );
			$path = dirname( $document_root ) . '/.digitalisimo-backups-' . substr( hash( 'sha256', wp_normalize_path( $wp_root ) ), 0, 16 );
		}
		if ( ! preg_match( '#^(?:/|[a-zA-Z]:[/\\\\])#', $path ) || is_link( $path ) ) throw new Exception( 'La carpeta de respaldos debe ser una ruta absoluta y no un enlace simbólico.' );
		if ( ! wp_mkdir_p( $path ) ) throw new Exception( 'No se pudo crear la carpeta privada de respaldos. Define DIGITALISIMO_BACKUPS_STORAGE_DIR con una ruta escribible fuera de la raíz pública.' );
		$real = realpath( $path );
		if ( false === $real ) throw new Exception( 'No se pudo resolver la carpeta privada de respaldos.' );
		foreach ( array_filter( array( $wp_root, realpath( WP_CONTENT_DIR ), realpath( WP_PLUGIN_DIR ), $document_root ) ) as $public_root ) {
			if ( self::path_within( $real, $public_root ) ) throw new Exception( 'La carpeta de respaldos está dentro de una ruta pública. Elige una ruta privada fuera del sitio.' );
		}
		if ( ! is_writable( $real ) ) throw new Exception( 'La carpeta privada de respaldos no permite escritura.' );
		if ( DIRECTORY_SEPARATOR === '/' ) {
			@chmod( $real, 0700 );
			clearstatcache( true, $real );
			$permissions = @fileperms( $real );
			if ( false === $permissions || ( $permissions & 0077 ) !== 0 ) throw new Exception( 'La carpeta privada de respaldos permite acceso a otros usuarios del servidor.' );
		}
		if ( ! $explicit && ( ! is_array( $saved ) || ( $saved['directory'] ?? '' ) !== $real || ( $saved['wordpress_root'] ?? '' ) !== wp_normalize_path( $wp_root ) ) ) {
			$record = array( 'wordpress_root' => wp_normalize_path( $wp_root ), 'directory' => $real );
			if ( is_multisite() ) update_site_option( self::STORAGE, $record );
			else update_option( self::STORAGE, $record, false );
		}
		self::migrate_legacy_storage( $real );
		$directory = $real;
		return $directory;
	}
	private static function path_within( $path, $root ) {
		$path = wp_normalize_path( $path );
		$root = rtrim( wp_normalize_path( $root ), '/' );
		return $path === $root || 0 === strpos( $path, $root . '/' );
	}
	/** Un lock del sistema de archivos cubre toda la instalación, incluidos los subsitios. */
	private static function acquire_operation_lock() {
		$handle = self::try_operation_lock();
		if ( ! $handle ) throw new Exception( 'Ya hay un respaldo o una restauración en curso. Espera a que termine antes de iniciar otra operación.' );
		return $handle;
	}
	/** Toma el cerrojo del almacén sin esperar; devuelve null si otra operación lo tiene. El kernel lo libera si el proceso muere. */
	public static function try_operation_lock() {
		$path = trailingslashit( self::local_directory() ) . self::OPERATION_LOCK;
		$handle = @fopen( $path, 'c' );
		if ( ! $handle ) throw new Exception( 'No se pudo abrir el bloqueo de operaciones de Backups.' );
		if ( ! @flock( $handle, LOCK_EX | LOCK_NB ) ) {
			fclose( $handle );
			return null;
		}
		// Con el bloqueo tomado, lo que quede en .staging/ (un ZIP en claro o descifrado) y los *.part son de una
		// operación interrumpida: se borran en cualquier operación, no sólo en la siguiente que cifre.
		Digitalisimo_Backups_Security::clean_staging( self::local_directory() );
		self::recover_interrupted_restore();
		return $handle;
	}
	/**
	 * Antes del SQL de una restauración: el historial (con la copia previa), la seguridad y la parte pública del llavero
	 * (huellas y parámetros de cada frase, nunca una clave ni su envoltura). Si el proceso muere mientras corre el SQL,
	 * la siguiente operación lo aplica: la protección no queda más débil, el cifrado falla cerrado hasta volver a
	 * escribir la frase y la frase vuelve a abrir la misma clave. Sin diario no se restaura.
	 */
	private static function write_restore_journal( array $origin = array(), $kept = null ) {
		$path = trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL;
		// Las claves de las credenciales del servidor de origen van cifradas con las de este (nunca en claro): si el proceso
		// muere entre el SQL y su nuevo cifrado, la operación siguiente lo termina.
		$json = wp_json_encode( array( 'version' => 1, 'started' => time(), 'wordpress_root' => self::journal_owner(), 'history' => (array) get_option( self::HISTORY, array() ), 'credentials' => Digitalisimo_Backups_Credentials::journal( $origin ) ) + ( is_array( $kept ) ? array( 'extract_kept' => Digitalisimo_Backups_Site_Extract::journal_snapshot( $kept ) ) : array() ) + Digitalisimo_Backups_Keys::public_snapshot() );
		$temp = $path . '.tmp';
		if ( ! is_string( $json ) || is_link( $path ) || is_link( $temp ) || strlen( $json ) !== @file_put_contents( $temp, $json ) || ! @chmod( $temp, 0600 ) || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			throw new Exception( 'No se pudo escribir el diario de la restauración en el almacén. No se cambió nada del sitio.' );
		}
	}
	private static function remove_restore_journal() { @unlink( trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL ); }
	/** La instalación dueña del diario: un almacén compartido por DIGITALISIMO_BACKUPS_STORAGE_DIR no mezcla llaveros. */
	private static function journal_owner() { $root = realpath( ABSPATH ); return wp_normalize_path( false === $root ? ABSPATH : $root ); }
	/** Aplica el diario de una restauración interrumpida (con el bloqueo tomado, nada más la está usando). */
	private static function recover_interrupted_restore() {
		$path = trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL;
		if ( is_link( $path ) ) { @unlink( $path ); return; }
		if ( ! is_file( $path ) ) return;
		$data = json_decode( (string) @file_get_contents( $path ), true );
		if ( is_array( $data ) && is_string( $data['wordpress_root'] ?? null ) && $data['wordpress_root'] !== self::journal_owner() ) return; // De otra instalación: es suyo.
		if ( is_array( $data ) && 1 === ( $data['version'] ?? null ) ) {
			wp_cache_flush();
			// Un sitio extraído: la configuración de Backups de este servidor vuelve (sin los secretos en claro, que no van al diario).
			if ( is_array( $data['extract_kept'] ?? null ) ) Digitalisimo_Backups_Site_Extract::reapply( $data['extract_kept'] );
			if ( is_array( $data['history'] ?? null ) ) update_option( self::HISTORY, $data['history'], false );
			Digitalisimo_Backups_Keys::recover_public( $data );
			// Las credenciales que trajo el SQL: las del servidor de origen se cifran con las claves de éste y las que
			// venían en claro se cifran.
			$origin = Digitalisimo_Backups_Credentials::from_journal( $data['credentials'] ?? null );
			self::restore_credentials( $origin );
			Digitalisimo_Backups_Credentials::forget( $origin );
		}
		@unlink( $path );
	}
	public static function release_operation_lock( $handle ) {
		if ( is_resource( $handle ) ) {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}
	/** Traslada los respaldos anteriores; nunca deja un ZIP parcialmente copiado como válido. */
	private static function migrate_legacy_storage( $private ) {
		$legacy = WP_PLUGIN_DIR . '/digitalisimo-backups-storage';
		if ( ! file_exists( $legacy ) ) return;
		if ( is_link( $legacy ) || ! is_dir( $legacy ) ) throw new Exception( 'La carpeta de respaldos anterior no es una carpeta segura.' );
		$lock = @fopen( $private . '/.migration.lock', 'c' );
		if ( ! $lock || ! flock( $lock, LOCK_EX ) ) { if ( $lock ) fclose( $lock ); throw new Exception( 'No se pudo bloquear la migración de respaldos anteriores.' ); }
		try {
			$names = @scandir( $legacy );
			if ( false === $names ) throw new Exception( 'No se pudo leer la carpeta anterior de respaldos.' );
			foreach ( $names as $name ) {
				if ( in_array( $name, array( '.', '..', 'index.php', '.htaccess' ), true ) ) continue;
				$source = $legacy . '/' . $name;
				if ( is_link( $source ) || ! is_file( $source ) ) throw new Exception( 'Hay un elemento no migrable en la carpeta anterior de respaldos: ' . $name );
				$target = $private . '/' . $name;
				$source_hash = @hash_file( 'sha256', $source );
				if ( false === $source_hash ) throw new Exception( 'No se pudo verificar el respaldo anterior ' . $name . '.' );
				if ( file_exists( $target ) ) {
					$target_hash = is_file( $target ) && ! is_link( $target ) ? @hash_file( 'sha256', $target ) : false;
					if ( false === $target_hash || ! hash_equals( $source_hash, $target_hash ) ) throw new Exception( 'Dos respaldos con el mismo nombre tienen distinto contenido: ' . $name );
				} elseif ( ! @rename( $source, $target ) ) {
					$temp = $target . '.migrating';
					$input = @fopen( $source, 'rb' );
					$output = @fopen( $temp, 'wb' );
					if ( ! $input || ! $output ) { if ( $input ) fclose( $input ); if ( $output ) fclose( $output ); @unlink( $temp ); throw new Exception( 'No se pudo copiar el respaldo anterior ' . $name . ' a la carpeta privada.' ); }
					$copied = stream_copy_to_stream( $input, $output );
					$flushed = fflush( $output );
					fclose( $input );
					fclose( $output );
					$temp_hash = @hash_file( 'sha256', $temp );
					if ( false === $copied || ! $flushed || $copied !== @filesize( $source ) || false === $temp_hash || ! hash_equals( $source_hash, $temp_hash ) || ! @rename( $temp, $target ) ) { @unlink( $temp ); throw new Exception( 'La copia del respaldo anterior ' . $name . ' no superó la verificación.' ); }
				}
				if ( file_exists( $source ) && ! @unlink( $source ) ) throw new Exception( 'Se copió ' . $name . ' a la carpeta privada, pero no se pudo retirar de la carpeta pública.' );
			}
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}
	/** Políticas independientes: cada una conserva su propia cadena y retención. */
	public static function default_policies() { return array( array( 'id' => 'monthly-full', 'label' => 'Completo mensual', 'type' => 'full', 'schedule' => 'monthly', 'retain' => 4, 'destination' => 'local', 'enabled' => true ) ); }
	public static function policies() { $saved = (array) get_option( self::POLICIES, self::default_policies() ); $defaults = array(); foreach ( self::default_policies() as $policy ) $defaults[ $policy['id'] ] = $policy; foreach ( $saved as &$policy ) { $base = $defaults[ $policy['id'] ?? '' ] ?? array( 'enabled' => true ); $policy = wp_parse_args( (array) $policy, $base ); if ( 'full' !== ( $policy['type'] ?? '' ) ) { $policy['enabled'] = false; $policy['schedule'] = 'disabled'; } } unset( $policy ); return $saved; }
	public static function activate_default_policies() {
		$saved = get_option( self::POLICIES, false );
		if ( false === $saved ) { add_option( self::POLICIES, self::default_policies(), '', false ); return; }
		$saved = (array) $saved;
		$changed = false;
		foreach ( $saved as &$policy ) {
			if ( 'full' === ( $policy['type'] ?? '' ) ) continue;
			$was_active = ! empty( $policy['enabled'] ) || 'disabled' !== ( $policy['schedule'] ?? '' );
			if ( $was_active ) $changed = true;
			$policy['enabled'] = false;
			$policy['schedule'] = 'disabled';
			if ( $was_active && ! empty( $policy['id'] ) ) wp_clear_scheduled_hook( 'digitalisimo_backups_policy', array( $policy['id'], get_current_blog_id() ) );
		}
		unset( $policy );
		if ( $changed ) update_option( self::POLICIES, $saved, false );
	}
	public static function policy( $id ) { foreach ( self::policies() as $policy ) if ( ( $policy['id'] ?? '' ) === $id ) return $policy; return array(); }
	/** Encola el respaldo de una política; el trabajo avanza por ticks y conserva el destino y la retención de este momento. */
	public static function run_policy( $id, $site_id = 0, $via = 'cron' ) {
		$policy = self::policy( $id );
		if ( ! $policy || empty( $policy['enabled'] ) ) return new WP_Error( 'digitalisimo_backup_policy', 'La política no existe o está desactivada.' );
		if ( 'full' !== ( $policy['type'] ?? '' ) ) return new WP_Error( 'digitalisimo_backup_partial', 'Una exportación parcial no puede ejecutarse como respaldo completo.' );
		$site_id = $site_id ?: get_current_blog_id();
		if ( is_multisite() && ( (int) $site_id !== (int) get_main_site_id() || (int) get_current_blog_id() !== (int) get_main_site_id() ) ) return new WP_Error( 'digitalisimo_backup_scope', 'La política completa de Multisite debe ejecutarse desde el sitio principal de la red.' );
		try {
			return Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => (string) $policy['id'], 'destination' => (string) ( $policy['destination'] ?? 'local' ), 'retain' => (int) ( $policy['retain'] ?? 4 ), 'retention' => 'policy', 'via' => $via, 'site_id' => $site_id ) ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'digitalisimo_backup_policy_failed', $e->getMessage() );
		}
	}

	/** El manifiesto enlaza incrementales con su completo base para restaurarlos en orden. */
	public static function incremental_manifest( $policy_id, $base_file, $changes ) { return array( 'format' => 'digitalisimo-backup/v2', 'policy' => sanitize_key( $policy_id ), 'type' => 'incremental', 'base' => sanitize_file_name( $base_file ), 'created_at' => gmdate( 'c' ), 'changes' => array_values( (array) $changes ), 'checksum' => hash( 'sha256', wp_json_encode( (array) $changes ) ) ); }
	/** Detecta y agrupa los archivos estándar de UpdraftPlus sin asumir que un ZIP aislado es restaurable. */
	public static function detect_updraft_sets( $directory = '' ) { $directory = $directory ?: WP_CONTENT_DIR . '/updraft'; $sets = array(); foreach ( glob( trailingslashit( $directory ) . 'backup_*' ) ?: array() as $path ) { $name = wp_basename( $path ); if ( ! preg_match( '/^(backup_[0-9-]{15}_.+?_[0-9a-f]{12})-(db|plugins|themes|uploads|others)([0-9]+)?\.(zip|gz)(?:\.crypt)?$/i', $name, $m ) ) continue; $key = $m[1]; if ( ! isset( $sets[ $key ] ) ) $sets[ $key ] = array( 'id' => $key, 'files' => array(), 'complete' => false ); $sets[ $key ]['files'][ strtolower( $m[2] ) ][] = array( 'path' => $path, 'part' => absint( $m[3] ), 'encrypted' => false !== strpos( $name, '.crypt' ) ); } foreach ( $sets as &$set ) { foreach ( $set['files'] as &$parts ) usort( $parts, function( $a, $b ) { return $a['part'] <=> $b['part']; } ); $set['complete'] = ! empty( $set['files']['db'] ) && ! array_filter( $set['files'], function( $parts ) { return array_filter( $parts, function( $part ) { return $part['encrypted']; } ); } ); } return $sets; }
	/** Rechaza piezas ausentes, cifradas o ilegibles antes de extraer cualquier archivo. */
	public static function validate_backup_set( $set ) { if ( empty( $set['complete'] ) || empty( $set['files']['db'] ) ) return new WP_Error( 'digitalisimo_backup_incomplete', 'El conjunto no incluye una base de datos legible o tiene piezas cifradas.' ); foreach ( $set['files'] as $parts ) foreach ( $parts as $part ) if ( ! is_readable( $part['path'] ) ) return new WP_Error( 'digitalisimo_backup_unreadable', 'Una pieza del respaldo no se puede leer.' ); return true; }
	/**
	 * En una red, las credenciales de los destinos son de toda la red y sólo las lee y escribe el sitio principal (la
	 * administración de red corre en él): ni un subsitio ni su migración tocan el almacén de la red.
	 */
	private static function credentials_here() { return ! is_multisite() || is_main_site(); }
	/** Los ajustes tal como están guardados (sin valores por defecto). */
	private static function saved_settings() { $saved = get_option( self::OPTION, array() ); return is_array( $saved ) ? $saved : array(); }
	/**
	 * Ajustes con los secretos de los destinos descifrados en memoria, la única lectura de las credenciales. Un secreto
	 * todavía en claro en los ajustes (sitios actualizados desde 1.0.60 o anteriores, o sin AUTH_KEY y SECURE_AUTH_KEY
	 * propias) manda y, si ya se puede cifrar, se cifra aquí mismo; uno cifrado con otras claves de wp-config.php, o para
	 * otros datos de conexión, queda vacío (el destino pide volver a escribirlo): nunca se cae al texto en claro. En un
	 * subsitio de una red no hay secretos: ni los de la red ni los que quedaran en claro en su propia opción.
	 */
	private static function settings() {
		$saved = self::saved_settings();
		if ( ! self::credentials_here() ) return wp_parse_args( array_diff_key( $saved, array_flip( self::secret_fields() ) ), self::defaults() );
		if ( self::plain_secrets( $saved ) && Digitalisimo_Backups_Credentials::can_encrypt() ) {
			try {
				self::migrate_credentials();
				$saved = self::saved_settings();
			} catch ( Throwable $e ) {
				error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
			}
		}
		$secrets = Digitalisimo_Backups_Credentials::read( wp_parse_args( $saved, self::defaults() ) );
		foreach ( self::secret_fields() as $field ) {
			if ( is_string( $saved[ $field ] ?? null ) && '' !== $saved[ $field ] ) continue;
			unset( $saved[ $field ] );
			if ( isset( $secrets[ $field ] ) ) $saved[ $field ] = $secrets[ $field ];
		}
		return wp_parse_args( $saved, self::defaults() );
	}
	/** Secretos que siguen en claro en unos ajustes guardados: campo => valor. */
	private static function plain_secrets( array $saved ) {
		$plain = array();
		foreach ( self::secret_fields() as $field ) if ( is_string( $saved[ $field ] ?? null ) && '' !== $saved[ $field ] ) $plain[ $field ] = $saved[ $field ];
		return $plain;
	}
	/**
	 * Único camino para guardar los ajustes: los secretos van cifrados al almacén, atados a los datos de su conexión, y
	 * nunca en claro a digitalisimo_backups_settings. Un secreto vacío no cambia lo guardado. Sin AUTH_KEY y
	 * SECURE_AUTH_KEY propias se guardan en claro, como hasta 1.0.60, con aviso en la pantalla y en `status` (sin ellas no
	 * hay con qué cifrar y no guardar dejaría el sitio sin destino); se cifran solos en cuanto existan. Los ajustes se
	 * guardan con autoload: comprobarlos al cargar el escritorio no cuesta ninguna consulta. Devuelve si quedaron cifrados.
	 */
	private static function store_settings( array $settings ) {
		if ( ! self::credentials_here() ) throw new Exception( self::subsite_credentials_text() );
		$secrets = array();
		foreach ( self::secret_fields() as $field ) {
			$value = is_string( $settings[ $field ] ?? null ) ? $settings[ $field ] : '';
			unset( $settings[ $field ] );
			if ( '' !== $value ) $secrets[ $field ] = $value;
		}
		if ( Digitalisimo_Backups_Credentials::put( $secrets, $settings ) ) {
			update_option( self::OPTION, $settings, true );
			return true;
		}
		update_option( self::OPTION, $settings + $secrets, true );
		return false;
	}
	private static function subsite_credentials_text() { return 'En una red, las conexiones de los destinos son de toda la red: se gestionan desde la administración de red o el sitio principal. No se cambió nada.'; }
	/**
	 * Los ajustes con autoload, para que comprobarlos al cargar no sea una consulta. Una versión anterior (1.0.60 tras
	 * volver desde rollback/) los reescribe sin autoload: la primera carga los lee (una consulta), migra lo que traigan en
	 * claro y vuelve a ponerlos con autoload. Sin wp_set_option_autoload() (WordPress anterior a 6.4) se espera al próximo
	 * guardado.
	 */
	private static function autoload_settings() {
		if ( ! function_exists( 'wp_load_alloptions' ) || ! function_exists( 'wp_set_option_autoload' ) ) return;
		if ( array_key_exists( self::OPTION, (array) wp_load_alloptions() ) || false === get_option( self::OPTION, false ) ) return;
		wp_set_option_autoload( self::OPTION, true );
	}
	/**
	 * Migración idempotente de los secretos en claro de los ajustes al almacén cifrado: se cifran, se comprueba que
	 * vuelven a abrirse y sólo entonces se retiran de los ajustes. Devuelve 'clean', 'migrated', 'unprotected' (no hay
	 * con qué cifrar; siguen en claro) o 'skipped' (subsitio de una red: los ajustes que usa el motor son los del
	 * principal). Corre al cargar el escritorio, el cron o WP-CLI, antes de volcar el SQL de cada respaldo y tras restaurar.
	 */
	public static function migrate_credentials() {
		if ( ! self::credentials_here() ) return 'skipped';
		$saved = self::saved_settings();
		$plain = self::plain_secrets( $saved );
		if ( ! $plain ) { self::autoload_settings(); return 'clean'; }
		if ( ! Digitalisimo_Backups_Credentials::put( $plain, wp_parse_args( $saved, self::defaults() ) ) ) { self::autoload_settings(); return 'unprotected'; }
		update_option( self::OPTION, array_diff_key( $saved, $plain ), true );
		return 'migrated';
	}
	/**
	 * Al cargar el escritorio: los ajustes tienen autoload, así que leerlos no cuesta ninguna consulta, y lo que aparezca
	 * en claro (también lo que reescribió 1.0.60 tras volver desde rollback/ y actualizar otra vez) se cifra. En un
	 * subsitio de una red no hace nada.
	 */
	public static function credentials_on_load() {
		if ( ! self::credentials_here() ) return;
		try { self::migrate_credentials(); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
	}
	/** Lo mismo en una petición de WP-Cron o de WP-CLI, que no pasan por admin_init. */
	public static function credentials_on_cron() {
		if ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) self::credentials_on_load();
	}
	/** Estado público de las credenciales de los destinos (sin valores) para la pantalla, REST y WP-CLI. */
	private static function credentials_status() {
		if ( ! self::credentials_here() ) return array( 'state' => 'none', 'locked' => array(), 'plain' => array(), 'notice' => '' );
		self::settings();
		$saved = self::saved_settings();
		return Digitalisimo_Backups_Credentials::status( array_keys( self::plain_secrets( $saved ) ), wp_parse_args( $saved, self::defaults() ) );
	}
	/** Mensaje de un destino remoto cuyas credenciales no se abren ('' si no es el caso). */
	private static function credentials_problem( $destination ) {
		if ( ! self::credentials_here() ) return '';
		$saved = self::saved_settings();
		return Digitalisimo_Backups_Credentials::destination_problem( (string) $destination, array_keys( self::plain_secrets( $saved ) ), wp_parse_args( $saved, self::defaults() ) );
	}
	/**
	 * Tras el SQL de una restauración (también si se recuperó la copia previa o se aplica el diario): las credenciales del
	 * respaldo mandan sobre las que tenía el servidor. Las cifradas con las claves del servidor de origen se vuelven a
	 * cifrar con las de éste ($origin sale del wp-config.php del respaldo), atadas a los datos de conexión restaurados, y
	 * las que venían en claro (respaldos hasta 1.0.60) se cifran. Nunca falla la restauración: lo que no se abre queda en
	 * «vuelve a escribirla» con su aviso.
	 */
	private static function restore_credentials( array $origin ) {
		try {
			Digitalisimo_Backups_Credentials::reseal( $origin, wp_parse_args( self::saved_settings(), self::defaults() ) );
			self::migrate_credentials();
		} catch ( Throwable $e ) {
			error_log( 'DIGITALÍSIMO Backups: credenciales tras restaurar: ' . $e->getMessage() );
		}
	}
	/** Catálogo persistente de conexiones; conserva la configuración previa como primer servidor. */
	private static function servers() { self::migrate_servers(); return (array) get_option( self::SERVERS, array() ); }
	private static function migrate_servers() { if ( false !== get_option( self::SERVERS, false ) ) return; $s = self::settings(); $servers = array(); if ( $s['sftp_host'] && $s['sftp_user'] ) $servers[] = array( 'id' => 'legacy-ftp', 'type' => 'sftp', 'name' => $s['sftp_name'] ?: 'SFTP', 'host' => $s['sftp_host'], 'port' => $s['sftp_port'], 'user' => $s['sftp_user'], 'path' => $s['sftp_path'], 'verified' => false ); add_option( self::SERVERS, $servers, '', false ); }
	private static function server_list() { $servers = self::servers(); echo '<section class="digitalisimo-backups-card"><h2>Servidores creados</h2>'; if ( ! $servers ) { echo '<p>Aún no hay servidores verificados.</p></section>'; return; } echo '<table class="widefat striped"><thead><tr><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>'; foreach ( $servers as $server ) echo '<tr><td>' . esc_html( $server['name'] ?? '' ) . '</td><td>' . esc_html( self::destination_label( $server['type'] ?? '' ) ) . '</td><td>' . ( empty( $server['verified'] ) ? 'Pendiente de verificación' : 'Verificado' ) . '</td><td><button class="button" disabled>Editar</button> <button class="button" disabled>Eliminar</button></td></tr>'; echo '</tbody></table><p class="description">Editar y eliminar se habilitarán al terminar la verificación de conexión de cada servidor.</p></section>'; }
	public static function cron_schedules( $schedules ) { $schedules['digitalisimo_every_6_hours'] = array( 'interval' => 6 * HOUR_IN_SECONDS, 'display' => 'Cada 6 horas' ); $schedules['digitalisimo_every_12_hours'] = array( 'interval' => 12 * HOUR_IN_SECONDS, 'display' => 'Cada 12 horas' ); $schedules['monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Mensual' ); return $schedules; }
	private static function schedule_options() { return array( 'disabled' => 'Desactivado', 'hourly' => 'Cada hora', 'digitalisimo_every_6_hours' => 'Cada 6 horas', 'digitalisimo_every_12_hours' => 'Cada 12 horas', 'daily' => 'Diario', 'weekly' => 'Semanal', 'monthly' => 'Mensual' ); }
	private static function sync_schedules( $settings ) { foreach ( array( 'full' => 'full_schedule', 'db' => 'database_schedule' ) as $type => $key ) { $hook = 'digitalisimo_backups_' . $type; wp_clear_scheduled_hook( $hook ); if ( 'full' === $type && 'disabled' !== $settings[ $key ] ) wp_schedule_event( time() + MINUTE_IN_SECONDS, $settings[ $key ], $hook, array( get_current_blog_id() ) ); } }
	private static function sync_policy_schedules() { foreach ( self::policies() as $policy ) { $args = array( $policy['id'], get_current_blog_id() ); wp_clear_scheduled_hook( 'digitalisimo_backups_policy', $args ); if ( ! empty( $policy['enabled'] ) && 'disabled' !== $policy['schedule'] ) wp_schedule_event( time() + MINUTE_IN_SECONDS, $policy['schedule'], 'digitalisimo_backups_policy', $args ); } }
	/** El evento de la política encola y da el primer paso; los siguientes los dan el tick, el navegador o el watchdog. */
	public static function scheduled_policy( $policy_id, $site_id ) {
		$result = self::run_policy( $policy_id, $site_id, 'cron' );
		if ( is_wp_error( $result ) ) { error_log( 'DIGITALÍSIMO Backups: ' . $result->get_error_message() ); return; }
		Digitalisimo_Backups_Jobs::tick();
	}
	public static function scheduled_backup( $site_id ) {
		if ( current_filter() === 'digitalisimo_backups_db' ) {
			error_log( 'DIGITALÍSIMO Backups: se omitió la exportación parcial de base de datos; requiere una política de respaldo completo.' );
			return;
		}
		if ( is_multisite() && ( (int) $site_id !== (int) get_main_site_id() || (int) get_current_blog_id() !== (int) get_main_site_id() ) ) {
			error_log( 'DIGITALÍSIMO Backups: se omitió una tarea heredada de subsitio que no cubre la red completa.' );
			return;
		}
		try {
			$settings = self::settings();
			Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'legacy-full', 'destination' => (string) $settings['destination'], 'retain' => (int) $settings['retention'], 'retention' => 'global', 'via' => 'cron', 'site_id' => $site_id ) ) );
		} catch ( Exception $e ) {
			error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
			return;
		}
		Digitalisimo_Backups_Jobs::tick();
	}

	public static function can_manage() { return is_multisite() ? current_user_can( 'manage_network_options' ) : current_user_can( 'manage_options' ); }
	/** @internal Acceso del motor de trabajos a las piezas de esta clase. */
	public static function can_manage_backups() { return self::can_manage(); }
	public static function storage_directory() { return self::local_directory(); }
	public static function redirect_result( $ok, $message ) { self::redirect( $ok, $message ); }
	public static function destination_name( $destination ) { return self::destination_label( $destination ); }
	public static function backup_file_name( $prefix = '' ) { return $prefix . 'digitalisimo-backup-' . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '-' . gmdate( 'Ymd-His' ) . '-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 ) . '.zip'; }
	private static function network_context() { if ( ! is_multisite() ) return false; if ( is_network_admin() || ! empty( $_REQUEST['digitalisimo_backups_network'] ) ) return true; $referer = wp_get_referer(); return $referer && false !== strpos( $referer, '/wp-admin/network/' ); }
	private static function admin_url() { return self::network_context() ? network_admin_url( 'admin.php?page=digitalisimo-network-backups' ) : admin_url( 'admin.php?page=digitalisimo-backups' ); }
	private static function context_args() { return self::network_context() ? array( 'digitalisimo_backups_network' => '1' ) : array(); }
	public static function page() { if ( ! self::can_manage() ) { echo '<div class="wrap"><h1>Backups · Digitalisimo</h1><p>El respaldo completo de esta red requiere permisos de administración de red.</p></div>'; return; } self::render( false ); }
	public static function network_page() { if ( ! self::can_manage() ) return; self::render( true ); }
	/** Secretos de los destinos: nunca vuelven al navegador y viven cifrados en Digitalisimo_Backups_Credentials. */
	private static function secret_fields() { return Digitalisimo_Backups_Credentials::FIELDS; }
	/** Sólo ofrece destinos remotos que ya cuentan con una conexión válida. */
	private static function manual_destinations( $settings ) { $destinations = array( 'local' => self::destination_label( 'local' ) ); if ( ! empty( $settings['sftp_host'] ) && ! empty( $settings['sftp_user'] ) && ! empty( $settings['sftp_password'] ) && ! empty( $settings['sftp_fingerprint'] ) ) $destinations['sftp'] = $settings['sftp_name'] ?: self::destination_label( 'sftp' ); if ( ! empty( $settings['google_refresh_token'] ) ) $destinations['google'] = $settings['google_name'] ?: self::destination_label( 'google' ); if ( ! empty( $settings['onedrive_refresh_token'] ) ) $destinations['onedrive'] = $settings['onedrive_name'] ?: self::destination_label( 'onedrive' ); return $destinations; }
	private static function manual_destination_select( $settings ) { $html = '<label>Destino<br><select name="digitalisimo_backup_destination">'; foreach ( self::manual_destinations( $settings ) as $value => $label ) $html .= '<option value="' . esc_attr( $value ) . '"' . selected( $settings['destination'], $value, false ) . '>' . esc_html( $label ) . '</option>'; return $html . '</select></label>'; }
	private static function render( $network ) {
		try { self::local_directory(); }
		catch ( Throwable $e ) { echo '<div class="wrap digitalisimo-backups"><h1>Backups · Digitalisimo</h1><div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div></div>'; return; }
		delete_option( self::manual_job_key() );
		// Una restauración interrumpida: al tomar el bloqueo se aplica su diario (si otra operación lo tiene, espera).
		if ( is_file( trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL ) ) { try { self::release_operation_lock( self::acquire_operation_lock() ); } catch ( Exception $e ) {} } // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$s = self::settings(); $history = array_slice( (array) get_option( self::HISTORY, array() ), 0, 10 );
		echo '<div class="wrap digitalisimo-backups"><h1>Backups · Digitalisimo</h1><p>Genera un ZIP integral: archivos de WordPress, plugins, temas, medios y base de datos. El respaldo se crea primero en el hosting y después se copia al destino elegido.</p>';
		if ( isset( $_GET['backup'] ) ) echo '<div class="notice notice-' . ( 'ok' === $_GET['backup'] ? 'success' : 'error' ) . '"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['message'] ?? '' ) ) ) . '</p></div>';
		// Credenciales de los destinos que no se abren o que siguen sin cifrar: qué hacer, sin ningún valor.
		$credentials = self::credentials_status();
		if ( '' !== $credentials['notice'] && 'security' !== sanitize_key( $_GET['tab'] ?? 'backups' ) ) echo '<div class="notice notice-warning digitalisimo-backups-credentials" data-state="' . esc_attr( $credentials['state'] ) . '"><p>' . esc_html( $credentials['notice'] ) . '</p></div>';
		$tab = sanitize_key( $_GET['tab'] ?? 'backups' ); $base = self::admin_url(); echo '<nav class="digitalisimo-backups-tabs"><a class="' . ( 'backups' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'backups', $base ) ) . '">Backups</a><a class="' . ( 'servers' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'servers', $base ) ) . '">Servidores</a><a class="' . ( 'security' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'security', $base ) ) . '">Seguridad</a></nav>';
		if ( 'servers' === $tab ) { if ( self::credentials_here() ) { self::servers_panel( $s, $credentials['locked'] ); self::server_list(); } else echo '<section class="digitalisimo-backups-card"><h2>Servidores y nubes</h2><p>Las conexiones de los destinos son de toda la red: se gestionan desde la administración de red o el sitio principal.</p></section>'; echo '</div>'; return; }
		if ( 'security' === $tab ) { Digitalisimo_Backups_Security::panel( $network, (array) get_option( self::HISTORY, array() ) ); echo '</div>'; return; }
			echo '<div class="digitalisimo-backups-grid"><section class="digitalisimo-backups-card"><h2>Crear respaldo completo</h2><p>Incluye todos los sitios de la red cuando WordPress usa Multisite, o el sitio completo en una instalación individual.</p>';
			if ( is_multisite() && ! is_main_site() ) echo '<p class="notice notice-warning inline">El respaldo completo de la red debe iniciarse desde el sitio principal o la administración de red. Este subsitio no generará una copia incompleta.</p>';
			echo '<form id="digitalisimo-backups-manual-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_RUN );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN ) . '"><input type="hidden" name="digitalisimo_backup_scope" value="installation">';
		if ( $network ) echo '<input type="hidden" name="digitalisimo_backups_network" value="1">';
			echo '<p>' . self::manual_destination_select( $s ) . '</p><button class="button button-primary button-hero"' . disabled( is_multisite() && ! is_main_site(), true, false ) . '>Crear respaldo completo ' . ( is_multisite() ? 'de la red' : 'de WordPress' ) . '</button></form><div id="digitalisimo-backups-manual-progress" class="digitalisimo-backups-manual-progress" hidden><p id="digitalisimo-backups-manual-message" role="status" aria-live="polite"></p><progress id="digitalisimo-backups-manual-bar" max="100" value="0"></progress><p id="digitalisimo-backups-manual-detail" aria-live="polite"></p></div>';

			echo '<p class="description">Destino actual: <strong>' . esc_html( self::destination_label( $s['destination'] ) ) . '</strong></p></section><section class="digitalisimo-backups-card"><h2>Últimos respaldos</h2>';
				if ( ! $history ) echo '<p>Aún no hay respaldos creados.</p>'; else { echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Archivo</th><th>Tipo</th><th>Tamaño</th><th>Destino</th><th>Seguridad</th><th>Protección</th><th>Acciones</th></tr></thead><tbody>'; foreach ( $history as $item ) { $file = $item['file'] ?? ''; echo '<tr><td>' . esc_html( $item['date'] ?? '' ) . '</td><td>' . esc_html( $file ) . '</td><td>' . esc_html( ( $item['scope'] ?? 'site' ) === 'network' ? 'Red completa' : ( ( $item['scope'] ?? 'site' ) === 'installation' ? 'Instalación completa' : ( Digitalisimo_Backups_Site_Extract::SCOPE === ( $item['scope'] ?? '' ) ? 'Sitio extraído: ' . (string) ( $item['extract']['label'] ?? '' ) : 'Exportación anterior de sitio' ) ) ) . '</td><td>' . esc_html( size_format( (int) ( $item['size'] ?? 0 ) ) ) . '</td><td>' . esc_html( self::history_destination( $item ) ) . '</td><td>' . Digitalisimo_Backups_Security::history_badge( $item ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_LOCK ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_LOCK ) . '"><input type="hidden" name="file" value="' . esc_attr( $file ) . '"><input type="hidden" name="locked" value="' . ( empty( $item['locked'] ) ? '1' : '0' ) . '"><button class="button button-small">' . ( empty( $item['locked'] ) ? 'Bloquear' : 'Bloqueado ✓' ) . '</button></form></td><td>'; if ( 'local' === ( $item['destination'] ?? 'local' ) || ! empty( $item['remote'] ) ) { echo '<a class="button button-small" href="' . esc_url( self::download_url( $file ) ) . '">Descargar</a> <form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Eliminar este respaldo?\')">'; wp_nonce_field( self::ACTION_DELETE ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE ) . '"><input type="hidden" name="file" value="' . esc_attr( $file ) . '"><button class="button button-small">Eliminar</button></form>'; } else echo 'Respaldo remoto anterior: sin identificador'; echo '</td></tr>'; } echo '</tbody></table>'; } echo '</section></div>';
			if ( $network && is_multisite() ) self::extract_panel( $s );
			self::jobs_panel( $network ); self::updraft_panel(); self::restore_panel( $history ); self::automation_fields(); echo '</div>';
		}
		/**
		 * «Extraer como WordPress individual»: sólo en la administración de la red (un superadministrador). Encola un
		 * trabajo como «Crear respaldo completo»; el paquete aparece en el historial y se descarga para restaurarlo en un
		 * WordPress individual recién instalado con Backups.
		 */
		private static function extract_panel( $s ) {
			$sites = function_exists( 'get_sites' ) ? get_sites( array( 'number' => 500, 'orderby' => 'id' ) ) : array();
			echo '<section class="digitalisimo-backups-card digitalisimo-backups-extract" id="digitalisimo-backups-extract"><h2>Extraer un sitio como WordPress individual</h2><p>Crea un paquete con un sitio de la red listo para restaurarse en un WordPress individual en otro servidor y otro dominio: sus tablas, sus usuarios (sólo los del sitio, con su contraseña y su rol), sus medios, sus plugins activos (también los activados en la red), su tema y los ajustes de red de los plugins DIGITALÍSIMO que hereda. Al restaurarlo, las direcciones pasan a las del sitio de destino.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( Digitalisimo_Backups_Site_Extract::ACTION );
			echo '<input type="hidden" name="action" value="' . esc_attr( Digitalisimo_Backups_Site_Extract::ACTION ) . '"><input type="hidden" name="digitalisimo_backups_network" value="1"><p><label>Sitio<br>';
			if ( count( $sites ) < 500 ) {
				echo '<select name="site_id" required><option value="">Elige un sitio de la red</option>';
				foreach ( $sites as $site ) { $site = is_object( $site ) ? $site : get_site( $site ); if ( ! $site ) continue; echo '<option value="' . (int) $site->blog_id . '">' . esc_html( Digitalisimo_Backups_Site_Extract::label( $site->domain, $site->path ) . ' (ID ' . (int) $site->blog_id . ')' ) . '</option>'; }
				echo '</select>';
			} else echo '<input type="number" min="1" name="site_id" required placeholder="ID del sitio">';
			echo '</label></p><p><label>Superadministradores que no son miembros del sitio<br><select name="super_admins"><option value="managing" selected>Sólo los que lo administran (su correo de administración o autores de su contenido), como administradores</option><option value="all">Todos, como administradores</option><option value="none">Ninguno (si escribieron contenido, quedan como autores sin acceso)</option></select></label></p>';
			echo '<p><label><input type="checkbox" name="network_secrets" value="1"> Copiar también las claves de API que el sitio hereda de la red (IA, Google Places, Namecheap, IPinfo)</label><br><span class="description">Sin marcar, esas claves quedan vacías y el informe del paquete dice cuáles volver a escribir: así un sitio entregado a otro dueño no se lleva las claves de la red.</span></p>';
			echo '<p>' . self::manual_destination_select( $s ) . '</p><p><button class="button button-primary">Extraer como WordPress individual</button></p><p class="description">Su avance aparece en «Trabajos». El sitio sigue en la red: la extracción no lo cambia ni lo retira.</p></form></section>';
		}
		/** admin-post de «Extraer como WordPress individual»: el mismo caso de uso que REST y WP-CLI, con el alcance de red. */
		public static function extract_request() {
			if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
			check_admin_referer( Digitalisimo_Backups_Site_Extract::ACTION );
			if ( ! is_multisite() || empty( $_POST['digitalisimo_backups_network'] ) ) self::redirect( false, 'Un sitio se extrae desde la administración de la red.', is_multisite() );
			$result = self::service_extract( absint( $_POST['site_id'] ?? 0 ), array( 'network' => true, 'super_admins' => sanitize_key( wp_unslash( $_POST['super_admins'] ?? 'managing' ) ), 'network_secrets' => ! empty( $_POST['network_secrets'] ), 'destination' => sanitize_key( wp_unslash( $_POST['digitalisimo_backup_destination'] ?? 'local' ) ), 'via' => 'admin-post' ) );
			if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message(), true );
			self::redirect( true, 'Extracción en cola. Su avance aparece en «Trabajos»; el paquete quedará en el historial para descargarlo.', true );
		}
		/** Destino de una fila del historial con el estado de su copia remota (las filas antiguas no lo tienen). */
		private static function history_destination( $item ) {
			$label = self::destination_label( $item['destination'] ?? 'local' );
			$target = self::destination_label( $item['transfer_destination'] ?? '' );
			$states = array( 'pending' => ' · copia a ' . $target . ' pendiente', 'failed' => ' · la copia a ' . $target . ' falló', 'cancelled' => ' · copia a ' . $target . ' cancelada' );
			return $label . ( $states[ $item['transfer'] ?? '' ] ?? '' );
		}
		/** Tarjeta «Trabajos»: el mismo estado que consultan el navegador, el cron, REST y WP-CLI. */
		private static function jobs_panel( $network ) {
			$views = Digitalisimo_Backups_Jobs::views();
			// En un subsitio la tarjeta sólo informa: los trabajos de la red avanzan y se cancelan desde el sitio principal.
			$manage = ! Digitalisimo_Backups_Jobs::off_main_site();
			$active = $manage && (bool) array_filter( $views, function( $view ) { return ! $view['terminal']; } );
			echo '<section class="digitalisimo-backups-card digitalisimo-backups-jobs" id="digitalisimo-backups-jobs" data-active="' . ( $active ? '1' : '0' ) . '"><h2>Trabajos</h2><p class="description">Cada respaldo avanza por pasos: pendiente, en curso, verificando y, con destino remoto, transfiriendo. Con esta página abierta el navegador lo hace avanzar; si la cierras, lo continúa WP-Cron. Sólo un ZIP verificado llega al historial, y un paso interrumpido se repite desde su último punto de control.' . ( $manage ? '' : ' Los trabajos de la red se gestionan desde el sitio principal o la administración de red.' ) . '</p><div id="digitalisimo-backups-jobs-list">';
			if ( ! $views ) echo '<p>Aún no hay trabajos.</p>';
			else {
				echo '<table class="widefat striped"><thead><tr><th>Estado</th><th>Trabajo</th><th>Fase</th><th>Avance</th><th>Detalle</th><th>Acciones</th></tr></thead><tbody>';
				foreach ( $views as $view ) {
					echo '<tr data-job="' . esc_attr( $view['id'] ) . '"><td><span class="digitalisimo-backups-job-status" data-status="' . esc_attr( $view['status'] ) . '">' . esc_html( $view['status_label'] ) . '</span></td><td>' . esc_html( $view['origin_label'] . ' · ' . $view['destination_label'] ) . '<br><small>' . esc_html( $view['created_label'] ) . '</small></td><td>' . esc_html( $view['phase_label'] ) . '</td><td><progress max="100" value="' . (int) $view['percent'] . '"></progress> ' . (int) $view['percent'] . '%</td><td>' . esc_html( $view['detail'] ) . '</td><td>';
					if ( $manage && $view['can_cancel'] ) {
						echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
						wp_nonce_field( Digitalisimo_Backups_Jobs::ACTION_CANCEL );
						echo '<input type="hidden" name="action" value="' . esc_attr( Digitalisimo_Backups_Jobs::ACTION_CANCEL ) . '"><input type="hidden" name="job" value="' . esc_attr( $view['id'] ) . '">' . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small" data-cancel="' . esc_attr( $view['id'] ) . '">Cancelar</button></form>';
					}
					echo '</td></tr>';
				}
				echo '</tbody></table>';
			}
			echo '</div></section>';
		}
		private static function servers_panel( $s, array $locked = array() ) { echo '<section class="digitalisimo-backups-card"><h2>Servidores y nubes</h2><p>Configura las conexiones disponibles para respaldos. En Backups sólo aparecerán Local y las conexiones que ya estén listas.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_SAVE ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '"><table class="form-table"><tr><th><label for="destination">Destino predeterminado</label></th><td><select name="digitalisimo_backups[destination]" id="destination"><option value="local" ' . selected( $s['destination'], 'local', false ) . '>Hosting / almacenamiento local</option><option value="sftp" ' . selected( $s['destination'], 'sftp', false ) . '>SFTP</option><option value="google" ' . selected( $s['destination'], 'google', false ) . '>Google Drive</option><option value="onedrive" ' . selected( $s['destination'], 'onedrive', false ) . '>Microsoft OneDrive</option></select></td></tr><tr data-server="sftp"><th>Nombre SFTP</th><td><input name="digitalisimo_backups[sftp_name]" value="' . esc_attr( $s['sftp_name'] ) . '"></td></tr><tr data-server="google"><th>Nombre Google Drive</th><td><input name="digitalisimo_backups[google_name]" value="' . esc_attr( $s['google_name'] ) . '"></td></tr><tr data-server="onedrive"><th>Nombre OneDrive</th><td><input name="digitalisimo_backups[onedrive_name]" value="' . esc_attr( $s['onedrive_name'] ) . '"></td></tr></table>'; self::connection_fields( $s, $locked ); submit_button( 'Guardar conexiones' ); echo '</form></section>'; }
		private static function policy_types() { return array( 'full' => 'Respaldo completo' ); }
		private static function policy_label( $policy ) { $types = self::policy_types(); $schedules = self::schedule_options(); return ( $types[ $policy['type'] ] ?? 'Respaldo' ) . ' · ' . ( $schedules[ $policy['schedule'] ] ?? 'Sin programación' ); }
		/** Cada automatización tiene un formulario propio: no existe una configuración global compartida. */
		private static function automation_fields() {
			$types = self::policy_types(); $schedules = self::schedule_options(); $destinations = array( 'local', 'sftp', 'google', 'onedrive' );
			echo '<section class="digitalisimo-backups-automations"><h2>Automatizaciones de respaldo</h2><p>Crea una automatización para cada estrategia de respaldo. Sus opciones se guardan de manera independiente y puedes crear, modificar, ejecutar o eliminar cada una sin afectar las demás.</p>';
			echo '<div class="digitalisimo-backups-card"><h3>Crear automatización</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_CREATE_AUTOMATION ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CREATE_AUTOMATION ) . '"><p><label>Tipo<br><select name="digitalisimo_backup_automation[type]">'; foreach ( $types as $value => $label ) echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label> <label>Frecuencia<br><select name="digitalisimo_backup_automation[schedule]">'; foreach ( $schedules as $value => $label ) if ( 'disabled' !== $value ) echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, 'weekly', false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label> <label>Destino<br><select name="digitalisimo_backup_automation[destination]">'; foreach ( $destinations as $destination ) echo '<option value="' . esc_attr( $destination ) . '">' . esc_html( self::destination_label( $destination ) ) . '</option>'; echo '</select></label> <label>Conservar<br><input type="number" min="1" max="100" name="digitalisimo_backup_automation[retain]" value="4"></label></p><p><button class="button button-primary">Crear automatización</button></p></form></div>';
			echo '<h3>Automatizaciones creadas</h3><div class="digitalisimo-backups-grid">';
			foreach ( self::policies() as $policy ) { $id = esc_attr( $policy['id'] ); if ( 'full' !== ( $policy['type'] ?? '' ) ) { echo '<section class="digitalisimo-backups-card"><h3>' . esc_html( $policy['label'] ?? $id ) . '</h3><p>Esta automatización parcial anterior está desactivada. Sus respaldos históricos se conservan; crea una automatización completa para proteger la instalación.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Eliminar esta automatización anterior?\')">'; wp_nonce_field( self::ACTION_DELETE_AUTOMATION ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE_AUTOMATION ) . '"><input type="hidden" name="policy_id" value="' . $id . '"><button class="button button-link-delete">Eliminar automatización</button></form></section>'; continue; } echo '<section class="digitalisimo-backups-card"><h3>' . esc_html( self::policy_label( $policy ) ) . '</h3><p class="description">Tipo fijo: Respaldo completo.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_UPDATE_AUTOMATION ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_UPDATE_AUTOMATION ) . '"><input type="hidden" name="policy_id" value="' . $id . '"><p><label><input type="checkbox" name="digitalisimo_backup_automation[enabled]" value="1" ' . checked( ! empty( $policy['enabled'] ), true, false ) . '> Activa</label></p><p><label>Frecuencia<br><select name="digitalisimo_backup_automation[schedule]">'; foreach ( $schedules as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $policy['schedule'], $value, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label> <label>Destino<br><select name="digitalisimo_backup_automation[destination]">'; foreach ( $destinations as $destination ) echo '<option value="' . esc_attr( $destination ) . '" ' . selected( $policy['destination'], $destination, false ) . '>' . esc_html( self::destination_label( $destination ) ) . '</option>'; echo '</select></label> <label>Conservar<br><input type="number" min="1" max="100" name="digitalisimo_backup_automation[retain]" value="' . esc_attr( $policy['retain'] ) . '"></label></p><p><button class="button button-primary">Guardar esta automatización</button></p></form><div class="digitalisimo-backups-actions"><form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_RUN_POLICY ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN_POLICY ) . '"><input type="hidden" name="digitalisimo_backup_policy" value="' . $id . '"><button class="button">Ejecutar ahora</button></form> <form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Eliminar esta automatización? Sus respaldos ya creados se conservan.\')">'; wp_nonce_field( self::ACTION_DELETE_AUTOMATION ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE_AUTOMATION ) . '"><input type="hidden" name="policy_id" value="' . $id . '"><button class="button button-link-delete">Eliminar</button></form></div></section>'; }
			echo '</div></section>';
		}
		private static function updraft_panel() { $sets = self::detect_updraft_sets(); echo '<h2>Respaldos detectados de UpdraftPlus</h2><p>Se analizan los archivos estándar dentro de <code>wp-content/updraft</code>. Sólo los conjuntos completos y sin cifrado se pueden restaurar.</p>'; if ( ! $sets ) { echo '<p>No se detectaron respaldos de UpdraftPlus en el almacenamiento local.</p>'; return; } echo '<table class="widefat striped"><thead><tr><th>Conjunto</th><th>Componentes</th><th>Estado</th></tr></thead><tbody>'; foreach ( $sets as $set ) { $components = array_map( function( $name, $parts ) { return $name . ( count( $parts ) > 1 ? ' × ' . count( $parts ) : '' ); }, array_keys( $set['files'] ), $set['files'] ); echo '<tr><td>' . esc_html( $set['id'] ) . '</td><td>' . esc_html( implode( ', ', $components ) ) . '</td><td>' . ( $set['complete'] ? 'Listo para restaurar abajo' : 'Incompleto o cifrado' ) . '</td></tr>'; } echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_RESTORE ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RESTORE ) . '"><input type="hidden" name="updraft" value="1"><p><select name="updraft_set"><option value="">Elige un conjunto UpdraftPlus</option>'; foreach ( $sets as $set ) if ( $set['complete'] ) echo '<option value="' . esc_attr( $set['id'] ) . '">' . esc_html( $set['id'] ) . '</option>'; echo '</select> <input type="text" name="confirmation" placeholder="RESTAURAR SIN FIRMA" autocomplete="off"> <button class="button">Restaurar conjunto</button></p><p class="description">Un conjunto de UpdraftPlus no lleva firma de este sitio: su SQL se revisa entero antes de cambiar nada y se pide escribir RESTAURAR SIN FIRMA. Restaurarlo instala su código y sus datos.</p></form>'; }
		private static function schedule_fields( $s ) { echo '<h2>Políticas automáticas</h2><p>Cada política tiene su propia frecuencia, destino y retención. Un incremental guarda la base de datos y sólo los archivos modificados desde su último punto base; al restaurar se aplica después de su respaldo completo base.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_SAVE_POLICIES ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE_POLICIES ) . '"><table class="widefat striped"><thead><tr><th>Activa</th><th>Nombre</th><th>Tipo</th><th>Frecuencia</th><th>Destino</th><th>Conservar</th><th>Ejecutar</th></tr></thead><tbody>'; foreach ( self::policies() as $policy ) { $id = esc_attr( $policy['id'] ); echo '<tr><td><input type="checkbox" name="digitalisimo_backups_policies[' . $id . '][enabled]" value="1" ' . checked( ! empty( $policy['enabled'] ), true, false ) . '></td><td><input type="hidden" name="digitalisimo_backups_policies[' . $id . '][id]" value="' . $id . '"><input type="text" name="digitalisimo_backups_policies[' . $id . '][label]" value="' . esc_attr( $policy['label'] ) . '"></td><td><strong>' . esc_html( array( 'full' => 'Completo', 'incremental' => 'Incremental', 'database' => 'Base de datos' )[ $policy['type'] ] ) . '</strong><input type="hidden" name="digitalisimo_backups_policies[' . $id . '][type]" value="' . esc_attr( $policy['type'] ) . '"></td><td><select name="digitalisimo_backups_policies[' . $id . '][schedule]">'; foreach ( self::schedule_options() as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $policy['schedule'], $value, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></td><td><select name="digitalisimo_backups_policies[' . $id . '][destination]">'; foreach ( array( 'local', 'sftp', 'google', 'onedrive' ) as $destination ) echo '<option value="' . esc_attr( $destination ) . '" ' . selected( $policy['destination'], $destination, false ) . '>' . esc_html( self::destination_label( $destination ) ) . '</option>'; echo '</select></td><td><input type="number" min="1" max="100" name="digitalisimo_backups_policies[' . $id . '][retain]" value="' . esc_attr( $policy['retain'] ) . '"></td><td><button class="button" name="action" value="' . esc_attr( self::ACTION_RUN_POLICY ) . '" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" formmethod="post">Ejecutar ahora<input type="hidden" name="digitalisimo_backup_policy" value="' . $id . '"></button></td></tr>'; } echo '</tbody></table><p><button class="button button-primary">Guardar políticas automáticas</button></p></form>'; }
	private static function connection_fields( $s, array $locked = array() ) {
		echo '<h2>Conexiones externas</h2><p>Elige un tipo para ver únicamente sus campos. Los secretos ya guardados no se muestran; deja el campo vacío para conservarlos.</p><p><label for="digitalisimo-server-type"><strong>Tipo de servidor</strong><br><select id="digitalisimo-server-type"><option value="sftp">SFTP</option><option value="google">Google Drive</option><option value="onedrive">OneDrive</option></select></label></p><table class="form-table"><tr data-server="sftp"><th colspan="2"><h3>SFTP</h3></th></tr>';
		self::field( 'sftp_host', 'Host o IP', $s ); self::field( 'sftp_port', 'Puerto', $s, 'number' ); self::field( 'sftp_user', 'Usuario', $s ); self::field( 'sftp_password', 'Contraseña', $s, 'password', $locked ); self::field( 'sftp_path', 'Carpeta remota', $s ); self::field( 'sftp_fingerprint', 'Huella SHA-1 de la clave SSH del servidor', $s ); echo '<tr data-server="sftp"><th></th><td><p class="description">Confírmala con el administrador del servidor por un canal independiente. Se acepta una huella hexadecimal de 40 caracteres, con o sin dos puntos. Sin ella no se enviarán respaldos por SFTP.</p></td></tr>'; self::forget_row( 'sftp', $s, $locked );
		echo '<tr data-server="google"><th colspan="2"><h3>Google Drive</h3></th></tr>'; self::field( 'google_client_id', 'Client ID', $s ); self::field( 'google_client_secret', 'Client secret', $s, 'password', $locked ); self::field( 'google_refresh_token', 'Refresh token', $s, 'password', $locked ); self::field( 'google_folder_id', 'ID de carpeta', $s ); self::forget_row( 'google', $s, $locked ); echo '<tr data-server="google"><th>Cómo obtener Google Drive</th><td><ol><li>Abre <strong>Google Cloud Console</strong> y crea o elige un proyecto.</li><li>Activa <strong>Google Drive API</strong> en APIs y servicios.</li><li>En Credenciales crea un cliente OAuth de tipo <strong>Aplicación web</strong>.</li><li>Agrega como URI de redirección autorizada: <code>' . esc_html( self::oauth_callback_url() ) . '</code></li><li>Copia Client ID y Client secret aquí, guarda y usa «Conectar Google Drive con mi sesión».</li></ol></td></tr>';
		echo '<tr data-server="onedrive"><th colspan="2"><h3>Microsoft OneDrive</h3></th></tr>'; self::field( 'onedrive_client_id', 'Client ID', $s ); self::field( 'onedrive_client_secret', 'Client secret', $s, 'password', $locked ); self::field( 'onedrive_refresh_token', 'Refresh token', $s, 'password', $locked ); self::field( 'onedrive_folder', 'Carpeta remota', $s ); self::forget_row( 'onedrive', $s, $locked ); echo '<tr data-server="onedrive"><th>Cómo obtener OneDrive</th><td><ol><li>Abre <strong>Microsoft Entra</strong> → App registrations → New registration.</li><li>En Authentication agrega esta URL: <code>' . esc_html( self::oauth_callback_url() ) . '</code></li><li>Copia Client ID y Client secret, guarda y conecta la sesión.</li></ol></td></tr></table><p data-server="google"><a class="button button-secondary" href="' . esc_url( self::oauth_url( 'google' ) ) . '">Conectar Google Drive</a></p><p data-server="onedrive"><a class="button button-secondary" href="' . esc_url( self::oauth_url( 'onedrive' ) ) . '">Conectar OneDrive</a></p><script>document.addEventListener("DOMContentLoaded",function(){var s=document.getElementById("digitalisimo-server-type");if(!s)return;function f(){document.querySelectorAll("[data-server]").forEach(function(e){e.style.display=e.dataset.server===s.value?"":"none"})}s.addEventListener("change",f);f()})</script>';
		}
	/**
	 * «Olvidar» las credenciales guardadas de un destino que ya no se usa, también las que ya no se abren (si no, su aviso
	 * quedaría para siempre): una casilla del mismo formulario, con su nonce y su capability. Sólo si hay algo guardado.
	 */
	private static function forget_row( $destination, $s, array $locked ) {
		$fields = Digitalisimo_Backups_Credentials::DESTINATIONS[ $destination ] ?? array();
		if ( ! array_filter( $fields, function( $field ) use ( $s, $locked ) { return ! empty( $s[ $field ] ) || in_array( $field, $locked, true ); } ) ) return;
		echo '<tr data-server="' . esc_attr( $destination ) . '"><th></th><td><label><input type="checkbox" name="digitalisimo_backups[forget][' . esc_attr( $destination ) . ']" value="1"> Olvidar las credenciales guardadas de ' . esc_html( self::destination_label( $destination ) ) . '</label><p class="description">Al guardar se retiran del sitio, también las que ya no se pueden abrir. Para un destino que ya no usas; lo que escribas ahora en sus campos sí se guarda.</p></td></tr>';
	}
	/** Un secreto guardado nunca vuelve al navegador: el campo va vacío y su estado se anuncia en el placeholder. */
	private static function field( $key, $label, $s, $type = 'text', array $locked = array() ) { $secret = in_array( $key, self::secret_fields(), true ); $server = 0 === strpos( $key, 'sftp_' ) ? 'sftp' : ( 0 === strpos( $key, 'google_' ) ? 'google' : 'onedrive' ); $placeholder = ''; if ( $secret && $s[ $key ] ) $placeholder = 'Guardado · escribe sólo para reemplazar'; elseif ( $secret && in_array( $key, $locked, true ) ) $placeholder = 'Guardado con otras claves de wp-config.php · vuelve a escribirlo'; echo '<tr data-server="' . esc_attr( $server ) . '"><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="' . esc_attr( $type ) . '" id="' . esc_attr( $key ) . '" name="digitalisimo_backups[' . esc_attr( $key ) . ']" value="' . esc_attr( $secret ? '' : $s[ $key ] ) . '"' . ( '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . ( $secret ? ' autocomplete="new-password"' : '' ) . '></td></tr>'; }
	private static function oauth_callback_url() { return add_query_arg( self::context_args(), admin_url( 'admin-post.php?action=' . self::ACTION_OAUTH ) ); }
	private static function oauth_url( $provider ) { return wp_nonce_url( add_query_arg( array_merge( array( 'action' => self::ACTION_OAUTH, 'provider' => $provider ), self::context_args() ), admin_url( 'admin-post.php' ) ), self::ACTION_OAUTH ); }
	private static function download_url( $file ) { return wp_nonce_url( add_query_arg( array_merge( array( 'action' => self::ACTION_DOWNLOAD, 'file' => rawurlencode( $file ) ), self::context_args() ), admin_url( 'admin-post.php' ) ), self::ACTION_DOWNLOAD ); }
	public static function download() { if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::ACTION_DOWNLOAD ); $file = sanitize_file_name( wp_unslash( $_GET['file'] ?? '' ) ); $item = self::history_item( $file ); if ( ! $item ) wp_die( 'Respaldo no disponible.' ); if ( 'local' !== ( $item['destination'] ?? 'local' ) ) { self::download_remote( $item ); exit; } $path = self::local_path( $file ); if ( ! $path ) wp_die( 'Respaldo no disponible.' ); nocache_headers(); header( 'Content-Type: ' . self::content_type( $file ) ); header( 'Content-Disposition: attachment; filename="' . $file . '"' ); header( 'Content-Length: ' . filesize( $path ) ); readfile( $path ); exit; }
	/** Un respaldo cifrado no es un ZIP para el navegador ni para los destinos remotos. */
	private static function content_type( $file ) { return '.dgbk' === substr( (string) $file, -5 ) ? 'application/octet-stream' : 'application/zip'; }
	public static function delete_backup() { if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::ACTION_DELETE ); $file = sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) ); $item = self::history_item( $file ); if ( ! $item ) self::redirect( false, 'El respaldo no está registrado.' ); try { if ( 'local' === ( $item['destination'] ?? 'local' ) ) { $path = self::local_path( $file ); if ( ! $path ) { self::remove_history( $file ); self::redirect( true, 'El archivo local ya no existía; se eliminó su registro del listado.' ); } if ( ! is_writable( dirname( $path ) ) ) throw new Exception( 'El hosting no permite escribir en la carpeta de respaldos: ' . dirname( $path ) ); if ( ! @wp_delete_file( $path ) && file_exists( $path ) ) throw new Exception( 'El hosting rechazó eliminar el archivo local. Revisa permisos de la carpeta de respaldos.' ); } else self::delete_remote( $item ); self::remove_history( $file ); self::redirect( true, 'Respaldo eliminado.' ); } catch ( Exception $e ) { self::redirect( false, $e->getMessage() ); } }
	public static function oauth_callback() { if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); $provider = sanitize_key( $_REQUEST['provider'] ?? '' ); if ( ! in_array( $provider, array( 'google', 'onedrive' ), true ) ) self::redirect( false, 'Proveedor no válido.' ); if ( ! self::credentials_here() ) self::redirect( false, self::subsite_credentials_text() ); $settings = self::settings(); if ( empty( $_GET['code'] ) ) { check_admin_referer( self::ACTION_OAUTH ); if ( empty( $settings[ $provider . '_client_id' ] ) || empty( $settings[ $provider . '_client_secret' ] ) ) self::redirect( false, 'Guarda antes el Client ID y Client secret del proveedor.' ); $state = wp_generate_password( 32, false, false ); set_transient( 'digitalisimo_backup_oauth_' . get_current_user_id(), array( 'state' => $state, 'provider' => $provider ), 15 * MINUTE_IN_SECONDS ); $redirect = self::oauth_callback_url(); if ( 'google' === $provider ) $url = add_query_arg( array( 'client_id' => $settings['google_client_id'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'access_type' => 'offline', 'prompt' => 'consent', 'scope' => 'https://www.googleapis.com/auth/drive.file', 'state' => $state ), 'https://accounts.google.com/o/oauth2/v2/auth' ); else $url = add_query_arg( array( 'client_id' => $settings['onedrive_client_id'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'response_mode' => 'query', 'scope' => 'offline_access Files.ReadWrite', 'state' => $state ), 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize' ); wp_redirect( $url ); exit; } $saved = get_transient( 'digitalisimo_backup_oauth_' . get_current_user_id() ); if ( empty( $saved['state'] ) || ! hash_equals( $saved['state'], (string) $_GET['state'] ) || $provider !== $saved['provider'] ) self::redirect( false, 'La autorización expiró o no coincide. Inténtalo nuevamente.' ); delete_transient( 'digitalisimo_backup_oauth_' . get_current_user_id() ); $endpoint = 'google' === $provider ? 'https://oauth2.googleapis.com/token' : 'https://login.microsoftonline.com/common/oauth2/v2.0/token'; $response = wp_remote_post( $endpoint, array( 'timeout' => 30, 'body' => array( 'client_id' => $settings[ $provider . '_client_id' ], 'client_secret' => $settings[ $provider . '_client_secret' ], 'code' => sanitize_text_field( wp_unslash( $_GET['code'] ) ), 'redirect_uri' => self::oauth_callback_url(), 'grant_type' => 'authorization_code' ) ) ); $data = json_decode( wp_remote_retrieve_body( $response ), true ); if ( is_wp_error( $response ) || empty( $data['refresh_token'] ) ) self::redirect( false, 'El proveedor no devolvió un refresh token. Revisa la URL de redirección registrada en su aplicación.' ); $problem = Digitalisimo_Backups_Credentials::secret_problem( $data['refresh_token'] ); if ( '' !== $problem ) self::redirect( false, 'El proveedor devolvió un refresh token que no se puede guardar (' . $problem . '). No se cambió nada.' ); $settings[ $provider . '_refresh_token' ] = $data['refresh_token']; try { self::store_settings( $settings ); } catch ( Exception $e ) { self::redirect( false, $e->getMessage() ); } self::redirect( true, ( 'google' === $provider ? 'Google Drive' : 'OneDrive' ) . ' conectado correctamente.' ); }
	public static function save() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::ACTION_SAVE ); if ( ! self::credentials_here() ) self::redirect( false, self::subsite_credentials_text() ); $old = self::settings(); $input = (array) ( $_POST['digitalisimo_backups'] ?? array() ); $out = self::defaults();
			$out['destination'] = in_array( $input['destination'] ?? '', array( 'local', 'sftp', 'google', 'onedrive' ), true ) ? $input['destination'] : 'local'; $out['retention'] = min( 100, max( 1, absint( $input['retention'] ?? 5 ) ) );
		foreach ( array( 'sftp_name', 'google_name', 'onedrive_name', 'sftp_host', 'sftp_user', 'sftp_path', 'google_client_id', 'google_folder_id', 'onedrive_client_id', 'onedrive_folder' ) as $key ) $out[ $key ] = sanitize_text_field( $input[ $key ] ?? '' ); $out['sftp_port'] = min( 65535, max( 1, absint( $input['sftp_port'] ?? 22 ) ) );
		$fingerprint = trim( (string) ( $input['sftp_fingerprint'] ?? '' ) );
		if ( '' !== $fingerprint && ! preg_match( '/^(?:[a-f0-9]{40}|(?:[a-f0-9]{2}:){19}[a-f0-9]{2})$/i', $fingerprint ) ) self::redirect( false, 'La huella SFTP debe contener 40 caracteres hexadecimales SHA-1, con o sin dos puntos.' );
		$out['sftp_fingerprint'] = strtoupper( str_replace( ':', '', $fingerprint ) );
			// Un secreto se guarda exactamente como se escribió (WordPress añade barras a $_POST: se quitan, y nada más). Un
			// campo vacío o sólo con espacios conserva lo guardado; NUL, caracteres de control o más de 8 KiB se rechazan sin
			// cambiar nada. Los demás campos conservan su saneado.
			foreach ( self::secret_fields() as $key ) { $value = $input[ $key ] ?? ''; $value = is_string( $value ) ? wp_unslash( $value ) : $value; if ( is_string( $value ) && '' === trim( $value ) ) { $out[ $key ] = $old[ $key ]; continue; } $problem = Digitalisimo_Backups_Credentials::secret_problem( $value ); if ( '' !== $problem ) self::redirect( false, Digitalisimo_Backups_Credentials::label( $key ) . ' no se guardó: ' . $problem . '. Corrígelo y vuelve a guardar; no se cambió nada.' ); $out[ $key ] = $value; } $out['full_schedule'] = array_key_exists( $input['full_schedule'] ?? '', self::schedule_options() ) ? $input['full_schedule'] : 'disabled'; $out['database_schedule'] = 'disabled'; 
		// «Olvidar»: las credenciales de los destinos marcados se retiran (también las que no se abren), salvo lo que se acabe de escribir.
		$forget_input = is_array( $input['forget'] ?? null ) ? $input['forget'] : array(); $forget = array();
		foreach ( Digitalisimo_Backups_Credentials::DESTINATIONS as $destination => $fields ) { if ( empty( $forget_input[ $destination ] ) ) continue; foreach ( $fields as $key ) { $typed = $input[ $key ] ?? ''; if ( is_string( $typed ) && '' !== trim( $typed ) ) continue; $out[ $key ] = ''; $forget[ $key ] = $destination; } }
		// Los secretos van cifrados al almacén, nunca en claro a los ajustes (sin AUTH_KEY y SECURE_AUTH_KEY propias, como hasta ahora y con aviso).
		try { $encrypted = self::store_settings( $out ); if ( $forget ) Digitalisimo_Backups_Credentials::remove( array_keys( $forget ) ); } catch ( Exception $e ) { self::redirect( false, $e->getMessage() ); }
		$forgotten = array_map( array( __CLASS__, 'destination_label' ), array_values( array_unique( $forget ) ) );
		$unprotected = ! $encrypted && array_filter( array_intersect_key( $out, array_flip( self::secret_fields() ) ), function( $value ) { return is_string( $value ) && '' !== $value; } );
		self::sync_schedules( $out ); wp_safe_redirect( add_query_arg( array( 'backup' => 'ok', 'message' => rawurlencode( 'Destino y automatizaciones guardados.' . ( $forgotten ? ' Se olvidaron las credenciales de ' . implode( ' y ', $forgotten ) . '.' : '' ) . ( $unprotected ? ' Aviso: las credenciales se guardaron sin cifrar porque wp-config.php no define AUTH_KEY y SECURE_AUTH_KEY propias; defínelas para cifrarlas.' : '' ) ) ), self::admin_url() ) ); exit;
	}
	public static function save_policies() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_SAVE_POLICIES );
		$allowed_types = array( 'full' ); $allowed_destinations = array( 'local', 'sftp', 'google', 'onedrive' ); $out = array();
		foreach ( (array) ( $_POST['digitalisimo_backups_policies'] ?? array() ) as $id => $policy ) {
			$id = sanitize_key( $id ); $type = sanitize_key( $policy['type'] ?? '' );
			if ( ! $id || ! in_array( $type, $allowed_types, true ) ) continue;
			$schedule = sanitize_key( $policy['schedule'] ?? 'disabled' );
			$out[] = array( 'id' => $id, 'label' => sanitize_text_field( $policy['label'] ?? $id ), 'type' => $type, 'schedule' => array_key_exists( $schedule, self::schedule_options() ) ? $schedule : 'disabled', 'destination' => in_array( $policy['destination'] ?? '', $allowed_destinations, true ) ? $policy['destination'] : 'local', 'retain' => min( 100, max( 1, absint( $policy['retain'] ?? 4 ) ) ), 'enabled' => ! empty( $policy['enabled'] ) );
		}
		if ( ! $out ) self::redirect( false, 'No se recibió una política válida.' );
		update_option( self::POLICIES, $out, false ); self::sync_policy_schedules(); self::redirect( true, 'Políticas automáticas guardadas.' );
	}
	private static function automation_input( $input, $id = '', $type = '' ) {
		$types = self::policy_types(); $schedules = self::schedule_options(); $destinations = array( 'local', 'sftp', 'google', 'onedrive' );
		$type = $type ?: sanitize_key( $input['type'] ?? '' );
		if ( ! isset( $types[ $type ] ) ) return new WP_Error( 'digitalisimo_backup_type', 'El tipo de respaldo no es válido.' );
		$schedule = sanitize_key( $input['schedule'] ?? 'daily' );
		if ( ! isset( $schedules[ $schedule ] ) ) $schedule = 'daily';
		$destination = in_array( $input['destination'] ?? '', $destinations, true ) ? $input['destination'] : 'local';
		if ( 'incremental' === $type && ! in_array( $destination, array( 'local', 'sftp' ), true ) ) return new WP_Error( 'digitalisimo_backup_incremental_destination', 'Los respaldos incrementales sólo se admiten en Local o SFTP. Google Drive y OneDrive siempre generan respaldos completos.' );
		return array( 'id' => $id ?: 'automation-' . wp_generate_uuid4(), 'label' => '', 'type' => $type, 'schedule' => $schedule, 'destination' => $destination, 'retain' => min( 100, max( 1, absint( $input['retain'] ?? 4 ) ) ), 'enabled' => ! empty( $input['enabled'] ) );
	}
	public static function create_automation() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::ACTION_CREATE_AUTOMATION );
		$input = (array) ( $_POST['digitalisimo_backup_automation'] ?? array() ); $input['enabled'] = 1;
		$automation = self::automation_input( $input ); if ( is_wp_error( $automation ) ) self::redirect( false, $automation->get_error_message() );
		$policies = self::policies(); $policies[] = $automation; update_option( self::POLICIES, $policies, false ); self::sync_policy_schedules(); self::redirect( true, 'Automatización creada.' );
	}
	public static function update_automation() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::ACTION_UPDATE_AUTOMATION );
		$id = sanitize_key( $_POST['policy_id'] ?? '' ); $policies = self::policies(); $found = false;
		foreach ( $policies as $index => $policy ) if ( $id === ( $policy['id'] ?? '' ) ) { $automation = self::automation_input( (array) ( $_POST['digitalisimo_backup_automation'] ?? array() ), $id, $policy['type'] ); if ( is_wp_error( $automation ) ) self::redirect( false, $automation->get_error_message() ); $policies[ $index ] = $automation; $found = true; break; }
		if ( ! $found ) self::redirect( false, 'La automatización ya no existe.' ); update_option( self::POLICIES, $policies, false ); self::sync_policy_schedules(); self::redirect( true, 'Automatización guardada.' );
	}
	public static function delete_automation() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::ACTION_DELETE_AUTOMATION );
		$id = sanitize_key( $_POST['policy_id'] ?? '' ); $policies = self::policies(); $remaining = array(); $found = false;
		foreach ( $policies as $policy ) { if ( $id === ( $policy['id'] ?? '' ) ) { $found = true; continue; } $remaining[] = $policy; }
		if ( ! $found ) self::redirect( false, 'La automatización ya no existe.' ); wp_clear_scheduled_hook( 'digitalisimo_backups_policy', array( $id, get_current_blog_id() ) ); update_option( self::POLICIES, $remaining, false ); self::sync_policy_schedules(); self::redirect( true, 'Automatización eliminada. Los respaldos existentes se conservan.' );
	}
	public static function run_policy_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_RUN_POLICY );
		// Mismo caso de uso que REST y WP-CLI; en una red, la automatización completa abarca la red entera.
		$result = self::service_run_automation( sanitize_key( $_POST['digitalisimo_backup_policy'] ?? '' ), array( 'network' => is_multisite(), 'via' => 'admin-post' ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, 'Automatización en cola. Su avance aparece en «Trabajos»; si cierras esta página, WP-Cron la continuará.' );
	}
	public static function run() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_RUN );
		$context = self::network_context();
		if ( is_multisite() && ! is_main_site() ) self::redirect( false, 'Inicia el respaldo completo desde el sitio principal o la administración de red.', $context );
		$scope = sanitize_key( wp_unslash( $_POST['digitalisimo_backup_scope'] ?? 'installation' ) );
		if ( ! in_array( $scope, array( 'installation', 'network' ), true ) ) self::redirect( false, 'La exportación de un subsitio no constituye un respaldo completo de WordPress.', $context );
		// En una red, el botón anuncia «de la red» y sólo lo ve quien la administra: ese es el alcance que autoriza.
		$result = self::service_create( array( 'destination' => wp_unslash( $_POST['digitalisimo_backup_destination'] ?? '' ), 'network' => is_multisite(), 'via' => 'admin-post', 'context' => $context ? 'network' : 'site' ) );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message(), $context );
		self::redirect( true, 'Respaldo completo en cola hacia ' . self::destination_label( $result['destination'] ) . '. Su avance aparece en «Trabajos»; si cierras esta página, WP-Cron lo continuará.', $context );
	}

	/** Opción del avance manual de 1.0.58 y anteriores: ya no bloquea nada y se retira al abrir la pantalla. */
	private static function manual_job_key() { return 'digitalisimo_backups_manual_job_' . get_current_user_id(); }
	private static function manual_request( $token = false ) {
		if ( ! self::can_manage() ) wp_send_json_error( array( 'message' => 'No autorizado para crear respaldos.' ), 403 );
		check_ajax_referer( 'digitalisimo_backups_manual', 'nonce' );
		if ( Digitalisimo_Backups_Jobs::off_main_site() ) wp_send_json_error( array( 'message' => 'Inicia el respaldo completo desde el sitio principal o la administración de red.' ), 400 );
		if ( ! $token ) return array();
		$job = Digitalisimo_Backups_Jobs::get( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
		if ( ! $job ) wp_send_json_error( array( 'message' => 'La sesión de respaldo no existe o expiró.' ), 404 );
		return $job;
	}
	/** Forma de respuesta del JS de 1.0.58 (una pestaña abierta durante la actualización sigue funcionando). */
	private static function manual_view( array $job ) {
		$view = Digitalisimo_Backups_Jobs::view( $job );
		$map = array( 'success' => 'complete', 'failed' => 'failed', 'cancelled' => 'failed', 'queued' => 'queued' );
		$status = $map[ $job['status'] ] ?? 'running';
		return array( 'status' => $status, 'phase' => $view['phase_label'] ?: $view['status_label'], 'percent' => $view['percent'], 'message' => 'failed' === $status && $view['error'] ? $view['error'] : $view['message'], 'file' => $view['file'] );
	}
	/** Avance para las fases que viven fuera de esta clase (cifrado y descifrado). Sin un trabajo en curso en este proceso no hace nada. */
	public static function report_progress( $percent, $phase, $message, $force = false ) { self::manual_progress( $percent, $phase, $message, $force ); }
	/** Encola el respaldo manual tras comprobar los requisitos; el trabajo avanza con los ticks del navegador o de WP-Cron. */
	public static function manual_start() {
		self::manual_request();
		if ( is_multisite() && ! is_main_site() ) wp_send_json_error( array( 'message' => 'Inicia el respaldo completo desde el sitio principal o la administración de red.' ), 400 );
		$context = sanitize_key( wp_unslash( $_POST['context'] ?? 'site' ) );
		if ( ! in_array( $context, array( 'site', 'network' ), true ) || ( 'network' === $context && ! is_multisite() ) ) wp_send_json_error( array( 'message' => 'Contexto de administración inválido.' ), 400 );
		// El destino vacío no toma el de los ajustes: esta vista siempre envía el elegido.
		$destination = sanitize_key( wp_unslash( $_POST['destination'] ?? '' ) );
		if ( '' === $destination ) wp_send_json_error( array( 'message' => 'El destino seleccionado no está conectado o no es válido.' ), 400 );
		// Mismo caso de uso que el formulario, REST y WP-CLI; en una red, el botón anuncia «de la red». Aquí el navegador da los ticks.
		$result = self::service_create( array( 'destination' => $destination, 'network' => is_multisite(), 'via' => 'ajax', 'context' => $context, 'spawn' => false ) );
		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			if ( ! empty( $data['job'] ) ) wp_send_json_error( array( 'message' => $result->get_error_message(), 'id' => $data['job'] ), 409 );
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		$job = Digitalisimo_Backups_Jobs::get( $result['id'] );
		if ( ! $job ) wp_send_json_error( array( 'message' => 'La sesión de respaldo no existe o expiró.' ), 404 );
		wp_send_json_success( array( 'token' => $job['id'], 'id' => $job['id'], 'job' => self::manual_view( $job ), 'view' => Digitalisimo_Backups_Jobs::view( $job ) ) );
	}
	public static function manual_status() {
		$job = self::manual_request( true );
		Digitalisimo_Backups_Jobs::recover_if_idle();
		wp_send_json_success( self::manual_view( Digitalisimo_Backups_Jobs::get( $job['id'] ) ?: $job ) );
	}
	/** Compatibilidad: el JS de 1.0.58 espera que esta petición haga todo; corre ticks hasta terminar o tener que esperar. */
	public static function manual_work() {
		$job = self::manual_request( true );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) wp_send_json_error( array( 'message' => 'El respaldo ya terminó.' ), 409 );
		@set_time_limit( 0 );
		ignore_user_abort( true );
		do {
			$tick = Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
			$job = Digitalisimo_Backups_Jobs::get( $job['id'] );
		} while ( $job && 'worked' === $tick['result'] && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) && (int) $job['retry_at'] <= time() );
		wp_send_json_success( array( 'status' => $job ? self::manual_view( $job )['status'] : 'failed' ) );
	}
	/** Latido y avance del trabajo en curso: se escribe en el almacén, nunca en la base de datos (puede estar en su instantánea). */
	private static function manual_progress( $percent, $phase, $message, $force = false ) { Digitalisimo_Backups_Jobs::progress( $percent, $message, $force ); }
	/** Requisitos que un respaldo completo necesita antes de encolarse. */
	private static function ensure_ready() {
		if ( ! class_exists( 'ZipArchive' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor necesita la extensión PHP ZipArchive para crear el respaldo.' );
		$dir = self::local_directory();
		if ( ! is_writable( $dir ) ) throw new Digitalisimo_Backups_Permanent_Error( 'No se puede escribir en la carpeta protegida de DIGITALÍSIMO Backups.' );
		self::assert_full_files_ready();
	}
	/** wp-content, plugins, medios y temas dentro de ABSPATH y sin enlaces; wp-config.php legible donde WordPress lo carga. */
	private static function assert_full_files_ready() {
		// Con varias redes el respaldo exporta todas: nunca desde una red secundaria (vista con avance, cron y automatizaciones incluidos).
		if ( is_multisite() && function_exists( 'is_main_network' ) && ! is_main_network() ) throw new Exception( self::service_main_site_problem() );
		Digitalisimo_Backups_Layout::assert_complete();
		self::wordpress_config_file();
	}
	private static function wordpress_config_file() {
		$root = realpath( ABSPATH );
		foreach ( array( trailingslashit( $root ) . 'wp-config.php', dirname( $root ) . '/wp-config.php' ) as $candidate ) {
			if ( is_file( $candidate ) && is_readable( $candidate ) ) return $candidate;
		}
		throw new Exception( 'No se puede leer wp-config.php en la raíz de WordPress ni en su carpeta superior.' );
	}

	/**
	 * Respaldo completo sincrónico (copia previa de una restauración) con el mismo motor y área de trabajo que la cola.
	 * Nunca se cifra: es local, se firma si hay clave y se recupera sin depender de una clave que el SQL restaurado
	 * podría no traer.
	 */
	private static function create_network( $prefix = '' ) {
		return Digitalisimo_Backups_Backup_Job::run_inline( self::backup_file_name( $prefix ) );
	}
	/** @internal Fase «preflight»: un fallo aquí es permanente. */
	public static function job_preflight( array $spec ) {
		self::ensure_ready();
		$dir = self::local_directory();
		self::protect( $dir );
		if ( is_multisite() && (int) get_current_blog_id() !== (int) get_main_site_id() ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo completo de la red sólo se ejecuta desde el sitio principal.' );
		$file = (string) ( $spec['file'] ?? '' );
		$extension = self::job_encrypts( $spec ) ? '.dgbk' : '.zip';
		if ( '' === $file || basename( $file ) !== $file || '.' === $file[0] || substr( $file, -strlen( $extension ) ) !== $extension || preg_match( '#[\x00-\x1f/\\\\]#', $file ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El nombre del respaldo no es válido.' );
		$free = function_exists( 'disk_free_space' ) ? @disk_free_space( $dir ) : false;
		if ( is_numeric( $free ) && $free < 64 * 1048576 ) throw new Digitalisimo_Backups_Permanent_Error( 'Quedan ' . self::bytes_label( $free ) . ' libres en el almacén privado; libera espacio antes de respaldar.' );
		// Un sitio extraído: el sitio sigue existiendo y su base de datos y sus medios tienen la forma de un sitio de la red.
		if ( Digitalisimo_Backups_Site_Extract::is_extract( $spec ) ) Digitalisimo_Backups_Site_Extract::preflight( $spec );
		// La clave que el trabajo fijó al encolarse se puede abrir: si no, falla ahora, antes de exportar nada.
		$plan = self::job_security_plan( $spec );
		if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
	}
	/** El trabajo cifra si su especificación inmutable lo fijó al encolarse (la copia previa nunca). */
	public static function job_encrypts( array $spec ) { return empty( $spec['inline'] ) && ! empty( $spec['security']['encrypt'] ); }
	/** Clave del trabajo en esta fase: un fallo es permanente y nunca deja salir en claro un respaldo que debía cifrarse. */
	private static function job_security_plan( array $spec ) {
		try {
			$plan = Digitalisimo_Backups_Security::job_plan( $spec );
		} catch ( Exception $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( $e->getMessage() );
		}
		if ( ! empty( $spec['inline'] ) ) $plan['encrypt'] = false;
		return $plan;
	}
	/** @internal Fase «database»: SQL por filas dentro de la instantánea, escrito en el área de trabajo privada. */
	public static function job_export_database( $path, $spec = null ) {
		global $wpdb;
		// Ningún respaldo lleva un secreto en claro si hay salts propias: lo que quede en claro en los ajustes (por ejemplo,
		// lo que reescribió 1.0.60 tras volver desde rollback/) se cifra antes del volcado; si no se puede comprobar, la
		// fase falla y se reintenta. export_row() es la última defensa, fila a fila.
		self::migrate_credentials();
		$at = gmdate( 'c' );
		if ( null === $spec ) { $current = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::current_id() ); $spec = is_array( $current['spec'] ?? null ) ? $current['spec'] : array(); }
		// «Extraer como WordPress individual»: sólo las tablas del sitio, con sus usuarios y metas (ver Digitalisimo_Backups_Site_Extract).
		// Su informe queda junto al SQL en el área de trabajo (`database.*`: se borra con él si la fase se repite) para «pack».
		if ( Digitalisimo_Backups_Site_Extract::is_extract( (array) $spec ) ) {
			$export = Digitalisimo_Backups_Site_Extract::exporter( $spec );
			$mode = self::database_sql_to_file( $export->tables(), $path, $export );
			Digitalisimo_Backups_Site_Extract::write_report( Digitalisimo_Backups_Site_Extract::report_path( $path ), $export->report() );
			return array( 'tables' => array_values( array_map( array( $export, 'name' ), $export->tables() ) ), 'mode' => $mode, 'at' => $at );
		}
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		$mode = self::database_sql_to_file( $tables, $path );
		return array( 'tables' => array_values( (array) $tables ), 'mode' => $mode, 'at' => $at );
	}
	/**
	 * @internal Fase «pack»: ZIP con el SQL exportado, los archivos y el manifiesto v3, escrito en el área de trabajo.
	 * Con la clave del trabajo, el manifiesto lleva su bloque `auth` y el ZIP el MAC de sus bytes exactos.
	 */
	public static function job_build_package( $target, $sql_path, array $database, array $extra = array() ) {
		global $wpdb;
		if ( ! class_exists( 'ZipArchive' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor necesita la extensión PHP ZipArchive para crear el respaldo.' );
		$dir = self::local_directory();
		$spec = (array) ( $extra['spec'] ?? array() );
		$extract = Digitalisimo_Backups_Site_Extract::is_extract( $spec ) ? Digitalisimo_Backups_Site_Extract::read_report( Digitalisimo_Backups_Site_Extract::report_path( $sql_path ) ) : null;
		if ( Digitalisimo_Backups_Site_Extract::is_extract( $spec ) && empty( $extract['site'] ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Falta el informe de la exportación del sitio; se exportará otra vez.', 'database' );
		$plan = self::job_security_plan( $spec );
		if ( file_exists( $target ) ) @unlink( $target );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $target, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
			throw new Exception( 'No se pudo crear el archivo ZIP.' );
		}
		$closing = false;
		try {
			self::zip_add_file( $zip, $sql_path, 'database.sql' );
			$entries = array( 'database.sql' => array( 'size' => (int) $database['size'], 'sha256' => (string) $database['sha256'] ) );
			self::manual_progress( 40, 'archivos', 'Comprimiendo archivos de WordPress…', true );
			$files_started_at = gmdate( 'c' );
			$files = null;
			if ( is_array( $extract ) ) $files = self::add_extract_files( $zip, $dir, $entries, $extract );
			else self::add_full_files( $zip, $dir, $entries );
			$files_completed_at = gmdate( 'c' );
			$manifest = array(
				'format' => 'digitalisimo-backup/v3',
				'backup_format_version' => 3,
				'backup_id' => wp_generate_uuid4(),
				'scope' => is_array( $extract ) ? Digitalisimo_Backups_Site_Extract::SCOPE : 'installation',
				'type' => 'full',
				'created_at' => (string) $database['at'],
				'completed_at' => gmdate( 'c' ),
				'home_url' => is_array( $extract ) ? (string) $extract['site']['url'] : home_url(),
				'site_url' => is_array( $extract ) ? (string) $extract['site']['site_url'] : site_url(),
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
				'database_version' => method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : '',
				'database_name' => DB_NAME,
				'database_size' => $entries['database.sql']['size'],
				'database_consistency' => (string) $database['mode'],
				// Un sitio extraído es un WordPress individual con el prefijo que tenía en la red (wp_N_).
				'table_prefix' => is_array( $extract ) ? (string) $extract['source_prefix'] : $wpdb->base_prefix,
				// Direcciones, rutas del servidor y red del origen: con ellas se restaura en otro dominio o servidor (un sitio
				// extraído se describe como el WordPress individual que será).
				'origin' => is_array( $extract ) ? Digitalisimo_Backups_Site_Extract::origin_block( $extract ) : Digitalisimo_Backups_Relocate::describe( false ),
				'database_tables' => (array) $database['tables'],
				'multisite' => is_array( $extract ) ? false : is_multisite(),
				'file_count' => count( $entries ) - 1,
				'uncompressed_size' => array_sum( array_column( $entries, 'size' ) ),
				'checksum_algorithm' => 'sha256',
				'plugin_version' => DIGITALISIMO_BACKUPS_VERSION,
				'excluded_secrets' => array( 'options:' . Digitalisimo_Backups_Keys::SECRET_PREFIX . '*', 'sitemeta:' . Digitalisimo_Backups_Keys::SECRET_PREFIX . '*' ),
				'entries' => $entries,
				'job_id' => (string) ( $extra['job_id'] ?? '' ),
				'packer' => 'ziparchive',
				'database_snapshot_at' => (string) $database['at'],
				'files_started_at' => $files_started_at,
				'files_completed_at' => $files_completed_at,
			);
			if ( is_array( $extract ) ) $manifest['extract'] = Digitalisimo_Backups_Site_Extract::manifest_block( $extract + array( 'files' => array( 'excluded' => $files['excluded'], 'excluded_count' => $files['excluded_count'] ) ) );
			if ( $plan['root'] ) $manifest['auth'] = Digitalisimo_Backups_Manifest_Auth::block( $plan['descriptor'], $plan['encrypt'] );
			$manifest_json = wp_json_encode( $manifest, JSON_PRETTY_PRINT );
			self::zip_add_string( $zip, 'manifest.json', $manifest_json );
			if ( $plan['root'] && ! $zip->setArchiveComment( Digitalisimo_Backups_Manifest_Auth::comment( $plan['root'], $manifest_json ) ) ) throw new Exception( 'No se pudo firmar el respaldo.' );
			self::manual_progress( 78, 'archivos', 'Escribiendo el ZIP en el área de trabajo…', true );
			if ( method_exists( $zip, 'registerCancelCallback' ) ) $zip->registerCancelCallback( function() { return Digitalisimo_Backups_Jobs::should_abort() ? 1 : 0; } );
			if ( method_exists( $zip, 'registerProgressCallback' ) ) $zip->registerProgressCallback( 0.05, array( 'Digitalisimo_Backups_Jobs', 'zip_progress' ) );
			$closing = true;
			if ( ! $zip->close() || ! is_file( $target ) || ! filesize( $target ) ) {
				Digitalisimo_Backups_Jobs::throw_if_aborted();
				throw new Exception( 'El ZIP no se pudo cerrar correctamente.' );
			}
			return array( 'file_count' => count( $entries ) - 1, 'files_started_at' => $files_started_at, 'files_completed_at' => $files_completed_at, 'backup_id' => (string) $manifest['backup_id'], 'created_at' => (string) $manifest['created_at'], 'signed' => (bool) $plan['root'] );
		} catch ( Throwable $e ) {
			if ( ! $closing ) { @$zip->unchangeAll(); self::close_quietly( $zip ); }
			@unlink( $target );
			throw $e;
		} finally {
			if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
		}
	}
	/** @internal Fase «verify»: estructura y cada entrada contra el manifiesto; con la clave del trabajo, también su firma. */
	public static function job_verify_package( $path, array $spec = array() ) {
		$plan = self::job_security_plan( $spec );
		try {
			$check = new ZipArchive();
			if ( true !== $check->open( $path, ZipArchive::CHECKCONS ) ) throw new Exception( 'El ZIP generado no supera la comprobación estructural.' );
			try {
				self::$verify_top = $plan['encrypt'] ? 89 : 98;
				self::verify_zip_contents( $check );
				$auth = Digitalisimo_Backups_Manifest_Auth::verify( $check, '', $plan['root'] );
				if ( $plan['root'] && 'signed' !== $auth['state'] ) throw new Exception( 'El respaldo no supera la comprobación de su propia firma.' );
				if ( ! $plan['root'] && 'unsigned' !== $auth['state'] ) throw new Exception( 'El respaldo lleva una firma que este trabajo no puede comprobar.' );
			} finally {
				self::$verify_top = 98;
				self::close_quietly( $check );
			}
		} finally {
			if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
		}
	}
	/**
	 * @internal Última parte de la fase «verify» de un trabajo cifrado: cifra el ZIP verificado a un DGBK dentro del
	 * área de trabajo, lo descifra completo para comprobarlo y sólo entonces lo deja listo para publicarse.
	 */
	public static function job_seal_package( $zip_path, $target, array $spec, array $meta ) {
		$plan = self::job_security_plan( $spec );
		try {
			if ( ! $plan['encrypt'] || ! $plan['root'] ) throw new Digitalisimo_Backups_Permanent_Error( 'Este trabajo no cifra; no hay nada que sellar.' );
			if ( file_exists( $target ) || is_link( $target ) ) @unlink( $target );
			return Digitalisimo_Backups_Security::seal( $zip_path, $target, $plan, array( 'backup_id' => (string) ( $meta['backup_id'] ?? '' ), 'created_at' => (string) ( $meta['created_at'] ?? '' ), 'plugin_version' => DIGITALISIMO_BACKUPS_VERSION, 'multisite' => is_multisite() ) );
		} finally {
			if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
		}
	}
	/** @internal Fase «publish»: registra el ZIP verificado como local; la copia remota se anota al terminar la transferencia. */
	public static function job_register( $final, array $spec, $job_id ) {
		$extra = array( 'job' => (string) $job_id );
		if ( 'local' !== ( $spec['destination'] ?? 'local' ) ) $extra += array( 'transfer' => 'pending', 'transfer_destination' => (string) $spec['destination'] );
		// Un sitio extraído no es un respaldo de esta instalación: su fila lo dice y no aparece en «Restaurar» de la red.
		if ( Digitalisimo_Backups_Site_Extract::is_extract( $spec ) ) {
			self::remember( $final, 'local', Digitalisimo_Backups_Site_Extract::SCOPE, array( 'id' => (string) $spec['policy'], 'type' => Digitalisimo_Backups_Site_Extract::POLICY ), array(), (int) $spec['extract']['site_id'], false, $extra + array( 'extract' => array( 'site_id' => (int) $spec['extract']['site_id'], 'label' => (string) $spec['extract']['label'] ) ) );
			return self::history_item( wp_basename( $final ) );
		}
		self::remember( $final, 'local', 'network' === ( $spec['scope'] ?? '' ) ? 'network' : 'installation', array( 'id' => (string) $spec['policy'], 'type' => 'full' ), array(), (int) ( $spec['site_id'] ?? 0 ), false, $extra );
		return self::history_item( wp_basename( $final ) );
	}
	/** @internal Fase «transfer»: credenciales leídas ahora, nunca guardadas en el trabajo. */
	public static function job_transfer( $file, $destination ) {
		$settings = self::settings();
		// Credenciales cifradas con otras claves de wp-config.php: repetir no lo arregla, así que el fallo es permanente y dice qué hacer.
		$problem = self::credentials_problem( $destination );
		if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem . ' El respaldo local verificado se conserva.' );
		if ( ! isset( self::manual_destinations( $settings )[ $destination ] ) || 'local' === $destination ) throw new Digitalisimo_Backups_Permanent_Error( 'El destino ' . self::destination_label( $destination ) . ' ya no tiene una conexión completa; el respaldo local verificado se conserva.' );
		$settings['destination'] = $destination;
		$remote = self::transfer( $file, $settings );
		if ( ! is_array( $remote ) || empty( $remote['provider'] ) ) throw new Exception( 'El destino remoto no confirmó la copia.' );
		return $remote;
	}
	/** @internal Fase «prune»: retención de la política o la global, sólo después de un respaldo verificado. */
	public static function job_prune( array $spec ) {
		if ( 'policy' === ( $spec['retention'] ?? '' ) ) self::prune_policy( array( 'id' => (string) $spec['policy'], 'retain' => (int) $spec['retain'] ) );
		elseif ( 'global' === ( $spec['retention'] ?? '' ) ) self::prune( self::local_directory(), (int) $spec['retain'] );
	}
	/** Actualiza claves de una fila del historial; una fila antigua sin ellas se sigue leyendo igual. */
	public static function history_update( $file, array $changes ) {
		return self::change_history( function( &$history ) use ( $file, $changes ) {
			$found = false;
			foreach ( $history as &$row ) {
				if ( hash_equals( (string) ( $row['file'] ?? '' ), (string) $file ) ) { $row = array_merge( (array) $row, $changes ); $found = true; }
			}
			unset( $row );
			return $found;
		} );
	}
	private static function bytes_label( $bytes ) { return function_exists( 'size_format' ) ? (string) size_format( (int) $bytes ) : round( $bytes / 1048576, 1 ) . ' MB'; }
	/** Inventario estricto de la instalación, sin omitir archivos sin avisar. */
	private static function add_full_files( $zip, $backup_dir, &$entries ) {
		$root = realpath( ABSPATH );
		$content = realpath( WP_CONTENT_DIR );
		if ( false === $root || false === $content ) throw new Exception( 'No se pudo resolver la ruta de WordPress o wp-content.' );
		$root = trailingslashit( wp_normalize_path( $root ) );
		$content = wp_normalize_path( $content );
		if ( 0 !== strpos( trailingslashit( $content ), $root ) ) throw new Exception( 'wp-content está fuera de ABSPATH; se necesita incluirlo explícitamente antes de crear un respaldo completo.' );
		$config = self::wordpress_config_file();
		$backup_dir = trailingslashit( wp_normalize_path( realpath( $backup_dir ) ) );
		$active_database_files = self::active_sqlite_database_files();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY );
		$count = 0;
		foreach ( $iterator as $entry ) {
			$path = wp_normalize_path( $entry->getPathname() );
			if ( 0 === strpos( $path, $backup_dir ) ) continue;
			if ( isset( $active_database_files[ $path ] ) ) continue; // database.sql es la copia lógica consistente.
			if ( $entry->isLink() ) {
				// wp-config.php enlazado (archivos compartidos de un despliegue): se guarda su contenido abajo y nunca se restaura encima.
				if ( $root . 'wp-config.php' === $path ) continue;
				// Permanente: repetir la fase no lo arregla; el trabajo falla al momento con la ruta y qué hacer.
				throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo completo no puede omitir el enlace simbólico ' . $path . ' (apunta a ' . ( realpath( $path ) ?: (string) @readlink( $path ) ) . '). Sustitúyelo por el archivo o la carpeta real para respaldar la instalación completa.' );
			}
			if ( ! $entry->isFile() ) continue;
			$relative = substr( $path, strlen( $root ) );
			$name = 'wordpress/' . $relative;
			$size = @filesize( $path );
			$hash = @hash_file( 'sha256', $path );
			if ( false === $size || false === $hash ) throw new Exception( 'No se pudo inventariar el archivo ' . $relative . '.' );
			self::zip_add_file( $zip, $path, $name );
			$entries[ $name ] = array( 'size' => $size, 'sha256' => $hash );
			$count++;
			if ( 0 === $count % 25 ) self::manual_progress( min( 78, 40 + (int) floor( $count / 100 ) ), 'archivos', 'Archivos incluidos: ' . $count . ' · último: ' . $relative );
		}
		if ( ! isset( $entries['wordpress/wp-config.php'] ) ) {
			$size = @filesize( $config );
			$hash = @hash_file( 'sha256', $config );
			if ( false === $size || false === $hash ) throw new Exception( 'No se pudo verificar wp-config.php fuera de ABSPATH.' );
			self::zip_add_file( $zip, $config, 'wordpress/wp-config.php' );
			$entries['wordpress/wp-config.php'] = array( 'size' => $size, 'sha256' => $hash );
		}
		foreach ( array( 'wordpress/wp-config.php', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' ) as $required ) {
			if ( ! isset( $entries[ $required ] ) ) throw new Exception( 'Falta el archivo obligatorio ' . $required . ' en el respaldo.' );
		}
	}
	/**
	 * Inventario de un sitio extraído (Digitalisimo_Backups_Site_Extract::file_map()): cada origen a su nombre en el ZIP,
	 * con las mismas reglas que el respaldo completo (sin omitir archivos sin avisar, sin seguir enlaces, sin el almacén ni
	 * la base SQLite en uso). Devuelve lo que no viajó, para el informe.
	 */
	private static function add_extract_files( $zip, $backup_dir, &$entries, array $extract ) {
		$files = Digitalisimo_Backups_Site_Extract::file_map( $extract );
		$backup_dir = trailingslashit( wp_normalize_path( realpath( $backup_dir ) ) );
		$active_database_files = self::active_sqlite_database_files();
		$count = 0;
		foreach ( $files['map'] as $item ) {
			$from = rtrim( wp_normalize_path( $item['from'] ), '/' );
			$skip = array_map( function( $path ) { return trailingslashit( wp_normalize_path( $path ) ); }, (array) ( $item['skip'] ?? array() ) );
			if ( is_link( $from ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio extraído no puede omitir el enlace simbólico ' . $from . ' (apunta a ' . ( realpath( $from ) ?: (string) @readlink( $from ) ) . '). Sustitúyelo por el archivo o la carpeta real.' );
			$sources = is_dir( $from ) ? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY ) : array( new SplFileInfo( $from ) );
			foreach ( $sources as $entry ) {
				$path = wp_normalize_path( $entry->getPathname() );
				if ( 0 === strpos( $path, $backup_dir ) || isset( $active_database_files[ $path ] ) ) continue;
				foreach ( $skip as $excluded ) if ( 0 === strpos( $path, $excluded ) ) continue 2;
				if ( $entry->isLink() ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio extraído no puede omitir el enlace simbólico ' . $path . ' (apunta a ' . ( realpath( $path ) ?: (string) @readlink( $path ) ) . '). Sustitúyelo por el archivo o la carpeta real.' );
				if ( ! $entry->isFile() ) continue;
				$name = $path === $from ? $item['to'] : $item['to'] . substr( $path, strlen( $from ) );
				if ( isset( $entries[ $name ] ) ) throw new Exception( 'Dos archivos del sitio irían al mismo lugar del paquete: ' . $name . '.' );
				$size = @filesize( $path );
				$hash = @hash_file( 'sha256', $path );
				if ( false === $size || false === $hash ) throw new Exception( 'No se pudo inventariar el archivo ' . $path . '.' );
				self::zip_add_file( $zip, $path, $name );
				$entries[ $name ] = array( 'size' => $size, 'sha256' => $hash );
				$count++;
				if ( 0 === $count % 25 ) self::manual_progress( min( 78, 40 + (int) floor( $count / 100 ) ), 'archivos', 'Archivos incluidos: ' . $count . ' · último: ' . substr( $name, strlen( 'wordpress/' ) ) );
			}
		}
		foreach ( Digitalisimo_Backups_Site_Extract::required_entries() as $required ) {
			if ( ! isset( $entries[ $required ] ) ) throw new Exception( 'Falta el archivo obligatorio ' . $required . ' en el sitio extraído.' );
		}
		return $files;
	}
	/** El archivo SQLite en uso no puede copiarse ni reemplazarse mientras la conexión está abierta. */
	private static function active_sqlite_database_files() {
		global $wpdb;
		if ( ! defined( 'DB_ENGINE' ) || 'sqlite' !== strtolower( (string) DB_ENGINE ) || 'WP_SQLite_DB' !== get_class( $wpdb ) ) return array();
		if ( ! isset( $wpdb->dbh ) || ! is_object( $wpdb->dbh ) || ! method_exists( $wpdb->dbh, 'get_connection' ) ) throw new Exception( 'No se pudo identificar el archivo SQLite activo.' );
		$connection = $wpdb->dbh->get_connection();
		if ( ! is_object( $connection ) || ! method_exists( $connection, 'query' ) ) throw new Exception( 'No se pudo consultar la ruta de la base de datos SQLite.' );
		$statement = $connection->query( 'PRAGMA database_list' );
		$rows = is_object( $statement ) && method_exists( $statement, 'fetchAll' ) ? $statement->fetchAll( PDO::FETCH_ASSOC ) : false;
		$files = array();
		foreach ( (array) $rows as $row ) {
			if ( 'main' !== ( $row['name'] ?? '' ) || empty( $row['file'] ) ) continue;
			$path = realpath( $row['file'] );
			if ( false === $path ) throw new Exception( 'No se pudo resolver el archivo SQLite activo.' );
			$path = wp_normalize_path( $path );
			foreach ( array( '', '-wal', '-shm', '-journal' ) as $suffix ) $files[ $path . $suffix ] = true;
		}
		if ( ! $files ) throw new Exception( 'SQLite no informó la ruta de su base de datos activa.' );
		return $files;
	}

	/**
	 * Vía antigua (v2) de exportar un sitio de la red: tablas renombradas a `wp_` y MIGRAR-A-WORDPRESS-UNICO.txt con pasos
	 * manuales. Sólo la llama una automatización incremental anterior a 1.0.38; su paquete (alcance `site`) nunca se
	 * restaura. Se conserva por compatibilidad: la vía recomendada es «Extraer como WordPress individual»
	 * (Digitalisimo_Backups_Site_Extract, alcance `extracted-site`), que pasa por el motor de trabajos y se restaura.
	 */
	private static function create_site( $site_id, $type = 'full' ) {
		if ( ! class_exists( 'ZipArchive' ) ) throw new Exception( 'El servidor necesita la extensión PHP ZipArchive para crear el respaldo.' );
		$dir = self::local_directory();
		if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) throw new Exception( 'No se puede escribir en la carpeta protegida de DIGITALÍSIMO Backups.' );
		self::protect( $dir );
		$site = get_site( $site_id );
		if ( ! $site ) throw new Exception( 'El sitio elegido ya no existe.' );
		$name = 'digitalisimo-site-' . $site_id . '-' . sanitize_title( $site->domain ) . '-' . ( 'database' === $type ? 'db-' : 'full-' ) . gmdate( 'Ymd-His' ) . '.zip';
		$file = trailingslashit( $dir ) . $name;
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) throw new Exception( 'No se pudo crear el archivo ZIP.' );
		try {
			switch_to_blog( $site_id );
			try {
				global $wpdb;
				$prefix = $wpdb->prefix;
				$upload = wp_upload_dir();
				$source_url = home_url();
				self::zip_add_string( $zip, 'database.sql', self::database_sql( self::site_tables( $prefix ), $prefix, 'wp_' ) );
				self::zip_add_string( $zip, 'migration.json', wp_json_encode( array( 'scope' => 'site', 'type' => $type, 'source_url' => $source_url, 'target_table_prefix' => 'wp_', 'upload_directory' => $upload['basedir'], 'created_at' => gmdate( 'c' ) ), JSON_PRETTY_PRINT ) );
				if ( 'full' === $type ) {
					$instructions = "1. Instala WordPress limpio.\n2. Copia wordpress/ sobre la instalación, excepto wp-config.php.\n3. Importa database.sql en la base de datos destino (usa prefijo wp_).\n4. Sustituye " . $source_url . " por la URL nueva en la base de datos con WP-CLI search-replace o una herramienta que preserve datos serializados.\n5. Revisa Ajustes > Enlaces permanentes y guarda una vez.\n";
					self::zip_add_string( $zip, 'MIGRAR-A-WORDPRESS-UNICO.txt', $instructions );
					self::add_directory( $zip, ABSPATH, $dir, true );
					self::add_upload_directory( $zip, $upload['basedir'] );
				}
			} finally {
				restore_current_blog();
			}
			self::finish_zip( $zip, $file );
			return $file;
		} catch ( Throwable $e ) {
			self::close_quietly( $zip );
			@unlink( $file );
			throw $e;
		}
	}

		private static function add_directory( $zip, $root, $backup_dir, $exclude_uploads = false ) { $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY ); $backup_dir = trailingslashit( wp_normalize_path( $backup_dir ) ); foreach ( $iterator as $file ) { if ( ! $file->isFile() || $file->isLink() ) continue; $path = wp_normalize_path( $file->getPathname() ); if ( 0 === strpos( $path, $backup_dir ) || false !== strpos( $path, '/.git/' ) || false !== strpos( $path, '/node_modules/' ) || false !== strpos( $path, '/cache/' ) || ( $exclude_uploads && 0 === strpos( $path, trailingslashit( wp_normalize_path( WP_CONTENT_DIR . '/uploads' ) ) ) ) ) continue; $relative = ltrim( substr( $path, strlen( wp_normalize_path( ABSPATH ) ) ), '/' ); if ( $exclude_uploads && 'wp-config.php' === $relative ) continue; self::zip_add_file( $zip, $path, 'wordpress/' . $relative ); } }
		private static function add_upload_directory( $zip, $directory ) { if ( ! is_dir( $directory ) ) return; $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY ); foreach ( $iterator as $file ) if ( $file->isFile() && ! $file->isLink() ) { $path = wp_normalize_path( $file->getPathname() ); $relative = ltrim( substr( $path, strlen( wp_normalize_path( $directory ) ) ), '/' ); self::zip_add_file( $zip, $path, 'wordpress/wp-content/uploads/' . $relative ); } }
	/** En PHP 8 cerrar dos veces un ZipArchive lanza ValueError, que ocultaría el error original. */
	private static function close_quietly( $zip ) { try { @$zip->close(); } catch ( Throwable $e ) {} } // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	private static function zip_add_string( $zip, $name, $content ) {
		if ( ! is_string( $content ) || '' === $content || ! $zip->addFromString( $name, $content ) ) throw new Exception( 'No se pudo agregar ' . $name . ' al respaldo.' );
	}
	private static function zip_add_file( $zip, $path, $name ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) || ! $zip->addFile( $path, $name ) ) throw new Exception( 'No se pudo agregar el archivo ' . $name . ' al respaldo.' );
	}
	/** Reabre y lee cada entrada antes de aceptar el ZIP como candidato a respaldo; con clave, también su firma. */
	private static function finish_zip( $zip, $file, $root = null ) {
		if ( ! $zip->close() || ! is_file( $file ) || ! filesize( $file ) ) {
			@unlink( $file );
			throw new Exception( 'El ZIP no se pudo cerrar correctamente.' );
		}
		$check = new ZipArchive();
		if ( true !== $check->open( $file, ZipArchive::CHECKCONS ) ) {
			@unlink( $file );
			throw new Exception( 'El ZIP generado no supera la comprobación estructural.' );
		}
		try {
			self::verify_zip_contents( $check );
			if ( $root ) {
				$auth = Digitalisimo_Backups_Manifest_Auth::verify( $check, '', $root );
				if ( 'signed' !== $auth['state'] ) throw new Exception( 'El respaldo no supera la comprobación de su propia firma.' );
			}
		} catch ( Exception $e ) {
			$check->close();
			@unlink( $file );
			throw $e;
		}
		$check->close();
	}
	/** Comprueba presencia, lectura completa y CRC de todos los miembros del archivo. */
	private static function verify_zip_contents( $zip ) {
		$names = array();
		$observed = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			if ( 0 === $i % 25 ) self::manual_progress( 81 + (int) floor( ( self::$verify_top - 81 ) * $i / max( 1, $zip->numFiles ) ), 'verificación', 'Verificando archivo ' . ( $i + 1 ) . ' de ' . $zip->numFiles );
			$stat = $zip->statIndex( $i );
			if ( ! $stat || ! isset( $stat['name'], $stat['size'], $stat['crc'] ) ) throw new Exception( 'Una entrada ZIP no tiene metadatos completos.' );
			$name = $stat['name'];
			if ( isset( $names[ $name ] ) ) throw new Exception( 'El ZIP contiene nombres de archivo duplicados.' );
			$names[ $name ] = true;
			if ( '/' === substr( $name, -1 ) ) continue;
			$stream = $zip->getStream( $name );
			if ( ! $stream ) throw new Exception( 'No se pudo leer ' . $name . ' del ZIP.' );
			$bytes = 0;
			$crc = hash_init( 'crc32b' );
			$sha = hash_init( 'sha256' );
			while ( ! feof( $stream ) ) {
				$chunk = fread( $stream, 1048576 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) { fclose( $stream ); throw new Exception( 'La entrada ' . $name . ' está truncada.' ); }
				$bytes += strlen( $chunk );
				hash_update( $crc, $chunk );
				hash_update( $sha, $chunk );
			}
			fclose( $stream );
			if ( $bytes !== (int) $stat['size'] || hash_final( $crc ) !== sprintf( '%08x', $stat['crc'] ) ) throw new Exception( 'Falló la integridad de ' . $name . ' dentro del ZIP.' );
			if ( 'manifest.json' !== $name ) $observed[ $name ] = array( 'size' => $bytes, 'sha256' => hash_final( $sha ) );
		}
		if ( ! isset( $names['database.sql'] ) || ( ! isset( $names['manifest.json'] ) && ! isset( $names['migration.json'] ) ) ) throw new Exception( 'Falta SQL o metadata obligatoria en el ZIP.' );
		if ( isset( $names['manifest.json'] ) ) {
			$raw_manifest = $zip->getFromName( 'manifest.json' );
			$manifest = false === $raw_manifest ? null : json_decode( $raw_manifest, true );
			if ( ! is_array( $manifest ) || JSON_ERROR_NONE !== json_last_error() ) throw new Exception( 'El manifiesto del ZIP no es JSON válido.' );
			if ( ! in_array( $manifest['format'] ?? '', array( '', 'digitalisimo-backup/v2', 'digitalisimo-backup/v3' ), true ) ) throw new Exception( 'El formato del manifiesto no es compatible.' );
			if ( 'digitalisimo-backup/v3' === ( $manifest['format'] ?? '' ) ) {
				// v3: la instalación (o red) completa, o un sitio extraído de una red (sin el wp-config.php de la red).
				$extracted = Digitalisimo_Backups_Site_Extract::SCOPE === ( $manifest['scope'] ?? '' );
				if ( 3 !== ( $manifest['backup_format_version'] ?? null ) || ( 'installation' !== ( $manifest['scope'] ?? '' ) && ! $extracted ) || 'full' !== ( $manifest['type'] ?? '' ) || ! is_array( $manifest['entries'] ?? null ) || count( $manifest['entries'] ) !== count( $observed ) ) throw new Exception( 'El manifiesto completo no coincide con el ZIP.' );
				if ( $extracted && ( ! empty( $manifest['multisite'] ) || ! is_array( $manifest['extract'] ?? null ) ) ) throw new Exception( 'El manifiesto del sitio extraído no coincide con el ZIP.' );
				foreach ( $extracted ? Digitalisimo_Backups_Site_Extract::required_entries() : array( 'database.sql', 'wordpress/wp-config.php', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' ) as $required ) if ( ! isset( $observed[ $required ] ) ) throw new Exception( 'Falta el archivo obligatorio ' . $required . ' en el respaldo completo.' );
				if ( (int) ( $manifest['file_count'] ?? -1 ) !== count( $observed ) - 1 || (int) ( $manifest['uncompressed_size'] ?? -1 ) !== array_sum( array_column( $observed, 'size' ) ) ) throw new Exception( 'El tamaño o el número de archivos no coincide con el manifiesto.' );
				foreach ( $observed as $name => $actual ) {
					$expected = $manifest['entries'][ $name ] ?? null;
					if ( ! is_array( $expected ) || (int) ( $expected['size'] ?? -1 ) !== $actual['size'] || ! is_string( $expected['sha256'] ?? null ) || ! hash_equals( $expected['sha256'], $actual['sha256'] ) ) throw new Exception( 'Falló el SHA-256 de ' . $name . ' en el manifiesto.' );
				}
			}
		}
	}

	private static function site_tables( $prefix ) { global $wpdb; $like = $wpdb->esc_like( $prefix ) . '%'; $tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); foreach ( array( $wpdb->base_prefix . 'users', $wpdb->base_prefix . 'usermeta' ) as $table ) if ( ! in_array( $table, $tables, true ) ) $tables[] = $table; return $tables; }
	private static function database_sql( $tables = null, $source_prefix = '', $target_prefix = '' ) {
		global $wpdb;
		if ( null === $tables ) $tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! is_array( $tables ) || ! $tables ) throw new Exception( 'No se pudo obtener la lista de tablas de la base de datos.' );
		$sql = "-- Digitalisimo WordPress backup\n-- " . gmdate( 'c' ) . "\nSET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n";
		foreach ( $tables as $table ) {
			$output = $target_prefix && 0 === strpos( $table, $source_prefix ) ? $target_prefix . substr( $table, strlen( $source_prefix ) ) : $table;
			$escaped = str_replace( '`', '``', $table );
			$create = $wpdb->get_row( "SHOW CREATE TABLE `" . $escaped . "`", ARRAY_N );
			if ( ! is_array( $create ) || empty( $create[1] ) ) throw new Exception( 'No se pudo exportar la definición de la tabla ' . $table . '.' );
			$definition = str_replace( '`' . $table . '`', '`' . $output . '`', $create[1] );
			$sql .= "DROP TABLE IF EXISTS `" . str_replace( '`', '``', $output ) . "`;\n" . $definition . ";\n";
			$rows = $wpdb->get_results( "SELECT * FROM `" . $escaped . "`", ARRAY_A );
			if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudieron exportar los datos de la tabla ' . $table . '.' );
			foreach ( $rows as $row ) {
				if ( self::secret_row( $table, $row ) ) continue;
				$row = self::export_row( $table, $row );
				$columns = array_map( function( $column ) { return '`' . str_replace( '`', '``', $column ) . '`'; }, array_keys( $row ) );
				$values = array_map( array( __CLASS__, 'sql_value' ), array_values( $row ) );
				$sql .= 'INSERT INTO `' . str_replace( '`', '``', $output ) . '` (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ");\n";
			}
			$sql .= "\n";
		}
		return $sql . "SET foreign_key_checks = 1;\n";
	}
	/**
	 * Exporta el SQL por filas sin construir toda la base de datos en memoria. Con $transform (un sitio extraído,
	 * Digitalisimo_Backups_Site_Extract_Export), cada tabla sale con su nombre en el paquete y cada fila pasa por él:
	 * prepare() consulta lo que necesita ya dentro de la instantánea y rows()/finish() no consultan nada.
	 */
	private static function database_sql_to_file( $tables, $path, $transform = null ) {
		global $wpdb;
		if ( ! is_array( $tables ) || ! $tables ) throw new Exception( 'No se pudo obtener la lista de tablas de la base de datos.' );
		$output = @fopen( $path, 'wb' );
		if ( ! $output ) throw new Exception( 'No se pudo abrir el archivo SQL temporal.' );
		$snapshot = '';
		$complete = false;
		try {
			$snapshot = self::begin_database_snapshot( $transform ? array_values( array_unique( array_merge( $tables, $transform->read_tables() ) ) ) : $tables );
			if ( $transform ) $transform->prepare();
			self::write_sql( $output, "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n" );
			foreach ( $tables as $index => $table ) {
				self::manual_progress( 5 + (int) floor( 33 * $index / count( $tables ) ), 'base de datos', 'Exportando tabla ' . ( $index + 1 ) . ' de ' . count( $tables ) . ': ' . $table );
				$escaped = str_replace( '`', '``', $table );
				$create = $wpdb->get_row( "SHOW CREATE TABLE `" . $escaped . "`", ARRAY_N );
				if ( ! is_array( $create ) || empty( $create[1] ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudo exportar la definición de la tabla ' . $table . '.' );
				self::write_sql( $output, "DROP TABLE IF EXISTS `" . str_replace( '`', '``', $transform ? $transform->name( $table ) : $table ) . "`;\n" . ( $transform ? $transform->create( $table, $create[1] ) : $create[1] ) . ";\n" );
				$query = "SELECT * FROM `" . $escaped . "`";
				if ( class_exists( 'mysqli' ) && isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli ) {
					$result = @mysqli_query( $wpdb->dbh, $query, MYSQLI_USE_RESULT );
					if ( false === $result ) throw new Exception( 'No se pudo leer la tabla ' . $table . ' en modo streaming.' );
					try {
						$row_count = 0;
						while ( $row = mysqli_fetch_assoc( $result ) ) { self::write_sql_row( $output, $table, $row, $transform ); $row_count++; if ( 0 === $row_count % 1000 ) self::manual_progress( 5 + (int) floor( 33 * $index / count( $tables ) ), 'base de datos', 'Tabla ' . $table . ': ' . $row_count . ' filas exportadas' ); }
						if ( mysqli_errno( $wpdb->dbh ) ) throw new Exception( 'Se interrumpió la lectura de la tabla ' . $table . '.' );
					} finally {
						mysqli_free_result( $result );
					}
				} else {
					$order = self::unique_order_columns( $table );
					if ( ! $order ) {
						$rows = $wpdb->get_results( $query, ARRAY_A );
						if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudieron exportar los datos de la tabla ' . $table . '.' );
						foreach ( $rows as $row ) self::write_sql_row( $output, $table, $row, $transform );
						if ( $transform ) foreach ( $transform->finish( $table ) as $row ) self::write_sql_insert( $output, $transform->name( $table ), $row );
						self::write_sql( $output, "\n" );
						continue;
					}
					$offset = 0;
					$batch = 500;
					do {
						$rows = $wpdb->get_results( $query . ' ORDER BY ' . implode( ', ', $order ) . ' LIMIT ' . $offset . ', ' . $batch, ARRAY_A );
						if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudieron exportar los datos de la tabla ' . $table . '.' );
						foreach ( $rows as $row ) self::write_sql_row( $output, $table, $row, $transform );
						$offset += count( $rows );
						self::manual_progress( 5 + (int) floor( 33 * $index / count( $tables ) ), 'base de datos', 'Tabla ' . $table . ': ' . $offset . ' filas exportadas' );
					} while ( count( $rows ) === $batch );
				}
				if ( $transform ) foreach ( $transform->finish( $table ) as $row ) self::write_sql_insert( $output, $transform->name( $table ), $row );
				self::write_sql( $output, "\n" );
			}
			self::write_sql( $output, "SET foreign_key_checks = 1;\n" );
			if ( ! fflush( $output ) ) throw new Exception( 'No se pudo completar el SQL temporal.' );
			$complete = true;
			return $snapshot;
		} finally {
			try {
				if ( $snapshot ) self::end_database_snapshot( $snapshot, $complete );
			} finally {
				fclose( $output );
			}
		}
	}
	/** InnoDB usa snapshot; tablas no transaccionales requieren bloqueo de lectura. */
	private static function begin_database_snapshot( $tables ) {
		global $wpdb;
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === strtolower( (string) DB_ENGINE ) && 'WP_SQLite_DB' === get_class( $wpdb ) ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new Exception( 'No se pudo iniciar una transacción consistente de SQLite.' );
			return 'transaction';
		}
		$transactional = true;
		foreach ( $tables as $table ) {
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( ! is_array( $status ) || empty( $status['Engine'] ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudo determinar el motor de la tabla ' . $table . '; no se creará un respaldo sin consistencia.' );
			if ( 'innodb' !== strtolower( $status['Engine'] ) ) $transactional = false;
		}
		if ( $transactional ) {
			if ( false === $wpdb->query( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ) || false === $wpdb->query( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' ) ) throw new Exception( 'No se pudo iniciar una instantánea consistente de la base de datos.' );
			return 'transaction';
		}
		$locks = array_map( function( $table ) { return '`' . str_replace( '`', '``', $table ) . '` READ'; }, $tables );
		if ( false === $wpdb->query( 'LOCK TABLES ' . implode( ', ', $locks ) ) ) throw new Exception( 'No se pudieron bloquear las tablas no transaccionales durante el respaldo.' );
		return 'lock';
	}
	private static function end_database_snapshot( $mode, $complete ) {
		global $wpdb;
		$command = 'lock' === $mode ? 'UNLOCK TABLES' : ( $complete ? 'COMMIT' : 'ROLLBACK' );
		if ( false === $wpdb->query( $command ) ) throw new Exception( 'No se pudo cerrar la instantánea consistente de la base de datos.' );
	}
	/** Orden estable para paginar en un DB drop-in que no expone mysqli. */
	private static function unique_order_columns( $table ) {
		global $wpdb;
		$escaped = str_replace( '`', '``', $table );
		$indexes = $wpdb->get_results( 'SHOW INDEX FROM `' . $escaped . '`', ARRAY_A );
		if ( ! is_array( $indexes ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudieron analizar los índices de la tabla ' . $table . '.' );
		$primary = array();
		foreach ( $indexes as $index ) if ( 'PRIMARY' === ( $index['Key_name'] ?? '' ) && isset( $index['Column_name'] ) ) $primary[ (int) ( $index['Seq_in_index'] ?? 1 ) ] = '`' . str_replace( '`', '``', $index['Column_name'] ) . '`';
		if ( ! $primary ) return array();
		ksort( $primary );
		return array_values( $primary );
	}
	/** Ningún respaldo contiene la clave que lo protege: las filas digitalisimo_backups_vault_* no se exportan. */
	private static function secret_row( $table, $row ) {
		global $wpdb;
		$prefix = (string) ( $wpdb->base_prefix ?? '' );
		if ( '' === $prefix || 0 !== strpos( $table, $prefix ) ) return false;
		$rest = substr( $table, strlen( $prefix ) );
		if ( preg_match( '/^(?:[0-9]+_)?options$/D', $rest ) ) $name = $row['option_name'] ?? '';
		elseif ( 'sitemeta' === $rest ) $name = $row['meta_key'] ?? '';
		else return false;
		return is_string( $name ) && 0 === strpos( $name, Digitalisimo_Backups_Keys::SECRET_PREFIX );
	}
	/**
	 * Con salts propias ningún respaldo lleva un secreto de destino en claro: si la fila de digitalisimo_backups_settings
	 * de cualquier tabla de opciones los trae (la del sitio principal ya se cifró antes del volcado; la de un subsitio de
	 * una red, que el motor no usa, puede conservar unos de una versión anterior), se exporta sin ellos. Sin salts propias
	 * se exporta tal cual: es la decisión del punto 7 (no hay con qué cifrarlos y sin ellos no se restauraría el destino).
	 */
	private static function export_row( $table, $row ) {
		global $wpdb;
		if ( ! is_array( $row ) || self::OPTION !== ( $row['option_name'] ?? null ) || ! is_string( $row['option_value'] ?? null ) ) return $row;
		$prefix = (string) ( $wpdb->base_prefix ?? '' );
		if ( '' === $prefix || 0 !== strpos( $table, $prefix ) || ! preg_match( '/^(?:[0-9]+_)?options$/D', substr( $table, strlen( $prefix ) ) ) ) return $row;
		$value = @unserialize( $row['option_value'], array( 'allowed_classes' => false ) );
		$plain = is_array( $value ) ? self::plain_secrets( $value ) : array();
		if ( ! $plain || ! Digitalisimo_Backups_Credentials::can_encrypt() ) return $row;
		$row['option_value'] = serialize( array_diff_key( $value, $plain ) );
		return $row;
	}
	private static function write_sql_row( $handle, $table, $row, $transform = null ) {
		if ( self::secret_row( $table, $row ) ) return;
		$row = self::export_row( $table, $row );
		if ( ! $transform ) { self::write_sql_insert( $handle, $table, $row ); return; }
		foreach ( $transform->rows( $table, $row ) as $out ) self::write_sql_insert( $handle, $transform->name( $table ), $out );
	}
	private static function write_sql_insert( $handle, $table, $row ) {
		$columns = array_map( function( $column ) { return '`' . str_replace( '`', '``', $column ) . '`'; }, array_keys( $row ) );
		$values = array_map( array( __CLASS__, 'sql_value' ), array_values( $row ) );
		self::write_sql( $handle, 'INSERT INTO `' . str_replace( '`', '``', $table ) . '` (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ");\n" );
	}
	private static function write_sql( $handle, $sql ) {
		$length = strlen( $sql );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, substr( $sql, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir el SQL temporal completo.' );
			$offset += $written;
		}
	}

	private static function sql_value( $value ) { if ( null === $value ) return 'NULL'; return "'" . str_replace( array( '\\', "\0", "\n", "\r", "\x1a", "'" ), array( '\\\\', '\\0', '\\n', '\\r', '\\Z', "\\'" ), (string) $value ) . "'"; }
	private static function protect( $dir ) { if ( ! file_exists( trailingslashit( $dir ) . 'index.php' ) ) file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php // Silence is golden.\n" ); if ( ! file_exists( trailingslashit( $dir ) . '.htaccess' ) ) file_put_contents( trailingslashit( $dir ) . '.htaccess', "Deny from all\n" ); }
	private static function transfer( $file, $s ) { if ( 'sftp' === $s['destination'] ) return self::sftp( $file, $s ); if ( 'google' === $s['destination'] ) return self::google( $file, $s ); if ( 'onedrive' === $s['destination'] ) return self::onedrive( $file, $s ); }
	private static function sftp( $file, $s ) {
		$connection = self::sftp_connection( $s );
		$sftp = @ssh2_sftp( $connection );
		if ( ! $sftp ) throw new Exception( 'No se pudo iniciar SFTP en el servidor remoto.' );
		$directory = rtrim( (string) $s['sftp_path'], '/' );
		if ( '' === $directory ) $directory = '/';
		if ( '/' !== substr( $directory, 0, 1 ) ) throw new Exception( 'La carpeta SFTP debe ser una ruta absoluta.' );
		$remote = rtrim( $directory, '/' ) . '/' . wp_basename( $file );
		$temporary = $remote . '.part-' . bin2hex( random_bytes( 6 ) );
		$source_size = @filesize( $file );
		$source_hash = @hash_file( 'sha256', $file );
		if ( false === $source_size || false === $source_hash ) throw new Exception( 'No se puede verificar el ZIP local antes de subirlo.' );
		$source = @fopen( $file, 'rb' );
		$target_url = 'ssh2.sftp://' . intval( $sftp ) . $temporary;
		$target = @fopen( $target_url, 'wb' );
		if ( ! $source || ! $target ) {
			if ( $source ) fclose( $source );
			if ( $target ) fclose( $target );
			throw new Exception( 'No se pudo abrir el archivo local o el destino SFTP.' );
		}
		try {
			$copied = @stream_copy_to_stream( $source, $target );
			$flushed = @fflush( $target );
		} finally {
			fclose( $source );
			fclose( $target );
		}
		if ( false === $copied || $copied !== $source_size || ! $flushed ) {
			@ssh2_sftp_unlink( $sftp, $temporary );
			throw new Exception( 'La transferencia SFTP quedó incompleta.' );
		}
		$remote_hash = @hash_file( 'sha256', $target_url );
		if ( false === $remote_hash || ! hash_equals( $source_hash, $remote_hash ) ) {
			@ssh2_sftp_unlink( $sftp, $temporary );
			throw new Exception( 'La copia SFTP no coincide con el ZIP local.' );
		}
		if ( ! @ssh2_sftp_rename( $sftp, $temporary, $remote ) ) {
			@ssh2_sftp_unlink( $sftp, $temporary );
			throw new Exception( 'No se pudo publicar el respaldo verificado en SFTP.' );
		}
		return array( 'provider' => 'sftp', 'path' => $remote, 'sha256' => $source_hash );
	}
	private static function token( $url, $s, $prefix ) { if ( ! $s[ $prefix . '_client_id' ] || ! $s[ $prefix . '_client_secret' ] || ! $s[ $prefix . '_refresh_token' ] ) { $problem = self::credentials_problem( $prefix ); throw new Digitalisimo_Backups_Permanent_Error( '' !== $problem ? $problem : 'Completa Client ID, Client secret y Refresh token del destino.' ); } $response = wp_remote_post( $url, array( 'timeout' => 30, 'body' => array( 'client_id' => $s[ $prefix . '_client_id' ], 'client_secret' => $s[ $prefix . '_client_secret' ], 'refresh_token' => $s[ $prefix . '_refresh_token' ], 'grant_type' => 'refresh_token' ) ) ); $data = json_decode( wp_remote_retrieve_body( $response ), true ); if ( is_wp_error( $response ) || empty( $data['access_token'] ) ) throw new Exception( 'No se pudo renovar el acceso al almacenamiento remoto.' ); return $data['access_token']; }
	private static function google( $file, $s ) { $token = self::token( 'https://oauth2.googleapis.com/token', $s, 'google' ); $metadata = array( 'name' => wp_basename( $file ) ); if ( $s['google_folder_id'] ) $metadata['parents'] = array( $s['google_folder_id'] ); $response = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json; charset=UTF-8', 'X-Upload-Content-Type' => self::content_type( $file ), 'X-Upload-Content-Length' => filesize( $file ) ), 'body' => wp_json_encode( $metadata ) ) ); $session = wp_remote_retrieve_header( $response, 'location' ); if ( is_wp_error( $response ) || ! $session ) throw new Exception( 'Google Drive no pudo iniciar la carga.' ); $result = json_decode( wp_remote_retrieve_body( self::resumable_upload( $session, $file, array( 'Content-Type' => self::content_type( $file ) ) ) ), true ); if ( empty( $result['id'] ) ) throw new Exception( 'Google Drive no confirmó el archivo cargado.' ); $id = sanitize_text_field( $result['id'] ); self::keep_verified_cloud_copy( 'google', $id, $file, $token ); return array( 'provider' => 'google', 'id' => $id, 'sha256' => hash_file( 'sha256', $file ) ); }
	private static function onedrive( $file, $s ) { $token = self::token( 'https://login.microsoftonline.com/common/oauth2/v2.0/token', $s, 'onedrive' ); $folder = trim( $s['onedrive_folder'], '/' ); $path = implode( '/', array_map( 'rawurlencode', array_filter( explode( '/', $folder ) ) ) ); $url = 'https://graph.microsoft.com/v1.0/me/drive/root:/' . $path . '/' . rawurlencode( wp_basename( $file ) ) . ':/createUploadSession'; $response = wp_remote_post( $url, array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'item' => array( '@microsoft.graph.conflictBehavior' => 'rename' ) ) ) ) ); $data = json_decode( wp_remote_retrieve_body( $response ), true ); if ( is_wp_error( $response ) || empty( $data['uploadUrl'] ) ) throw new Exception( 'OneDrive no pudo iniciar la carga. Verifica que exista la carpeta remota.' ); $result = json_decode( wp_remote_retrieve_body( self::resumable_upload( $data['uploadUrl'], $file, array(), 3276800 ) ), true ); if ( empty( $result['id'] ) ) throw new Exception( 'OneDrive no confirmó el archivo cargado.' ); $id = sanitize_text_field( $result['id'] ); self::keep_verified_cloud_copy( 'onedrive', $id, $file, $token ); return array( 'provider' => 'onedrive', 'id' => $id, 'sha256' => hash_file( 'sha256', $file ) ); }
	/**
	 * Una copia subida que no supera la verificación se retira del destino: con los reintentos de la transferencia, cada
	 * intento subiría otra, y ninguna copia sin verificar debe quedarse allí con el nombre de un respaldo.
	 */
	private static function keep_verified_cloud_copy( $provider, $id, $source, $token ) {
		try {
			self::verify_cloud_copy( $provider, $id, $source, $token );
		} catch ( Exception $e ) {
			$url = 'google' === $provider ? 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $id ) : 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode( $id );
			$response = wp_remote_request( $url, array( 'method' => 'DELETE', 'timeout' => 60, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
			$removed = ! is_wp_error( $response ) && in_array( (int) wp_remote_retrieve_response_code( $response ), array( 200, 204, 404 ), true );
			throw new Exception( $e->getMessage() . ( $removed ? ' Se retiró del destino la copia sin verificar.' : ' No se pudo retirar del destino la copia sin verificar; elimínala desde ' . self::destination_label( $provider ) . '.' ), 0, $e );
		}
	}
	/** Descarga a un temporal privado y comprueba bytes y SHA-256 antes de registrar éxito. */
	private static function verify_cloud_copy( $provider, $id, $source, $token ) {
		$temp = tempnam( self::local_directory(), '.cloud-check-' );
		if ( false === $temp ) throw new Exception( 'No se pudo preparar la verificación del respaldo remoto.' );
		try {
			$url = 'google' === $provider
				? 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $id ) . '?alt=media'
				: 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode( $id ) . '/content';
			$response = wp_remote_get( $url, array( 'timeout' => 300, 'redirection' => 5, 'stream' => true, 'filename' => $temp, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) throw new Exception( 'No se pudo descargar la copia para verificarla desde ' . $provider . '.' );
			$source_size = @filesize( $source );
			$remote_size = @filesize( $temp );
			$source_hash = @hash_file( 'sha256', $source );
			$remote_hash = @hash_file( 'sha256', $temp );
			if ( false === $source_size || false === $remote_size || $source_size !== $remote_size || false === $source_hash || false === $remote_hash || ! hash_equals( $source_hash, $remote_hash ) ) throw new Exception( 'La copia remota de ' . $provider . ' no coincide con el ZIP local; no se registró como respaldo válido.' );
		} finally { @unlink( $temp ); }
	}
	/** Sube en fragmentos para que archivos grandes no agoten la memoria del hosting. */
	private static function resumable_upload( $url, $file, $headers = array(), $chunk_size = 8388608 ) {
		$size = filesize( $file );
		$handle = @fopen( $file, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer el ZIP para transferirlo.' );
		$offset = 0;
		$response = array();
		try {
			while ( ! feof( $handle ) ) {
				// Latido y cancelación entre fragmentos.
				self::manual_progress( 90 + (int) floor( 9 * $offset / max( 1, $size ) ), 'transferencia', 'Transferidos ' . self::bytes_label( $offset ) . ' de ' . self::bytes_label( $size ) . '…' );
				$chunk = fread( $handle, $chunk_size );
				if ( false === $chunk || '' === $chunk ) break;
				$end = $offset + strlen( $chunk ) - 1;
				$response = wp_remote_request( $url, array( 'method' => 'PUT', 'timeout' => 120, 'headers' => array_merge( $headers, array( 'Content-Length' => strlen( $chunk ), 'Content-Range' => 'bytes ' . $offset . '-' . $end . '/' . $size ) ), 'body' => $chunk ) );
				$code = wp_remote_retrieve_response_code( $response );
				if ( is_wp_error( $response ) || ! in_array( $code, array( 200, 201, 202, 308 ), true ) ) throw new Exception( 'El almacenamiento remoto interrumpió la transferencia.' );
				$offset = $end + 1;
			}
			if ( $offset !== $size ) throw new Exception( 'La transferencia remota quedó incompleta.' );
		} catch ( Throwable $e ) {
			// Se retira la sesión de carga incompleta para no dejar temporales en el destino.
			@wp_remote_request( $url, array( 'method' => 'DELETE', 'timeout' => 15 ) );
			throw $e;
		} finally {
			fclose( $handle );
		}
		return $response;
	}
	private static function remember( $file, $destination, $scope = 'site', $policy = array(), $remote = array(), $site_id = 0, $locked = false, $extra = array() ) {
		// El SHA-256 de todo el ZIP se calcula antes de leer el historial, así que lo que cambie otra petición mientras tanto cuenta.
		$checksum = hash_file( 'sha256', $file );
		if ( false === $checksum ) throw new Exception( 'No se pudo calcular el SHA-256 del respaldo terminado.' );
		$name = wp_basename( $file );
		$row = array( 'date' => current_time( 'mysql' ), 'file' => $name, 'size' => filesize( $file ), 'sha256' => $checksum, 'destination' => $destination, 'scope' => $scope, 'site_id' => $site_id ?: get_current_blog_id(), 'policy' => $policy['id'] ?? 'manual', 'backup_type' => $policy['type'] ?? ( false !== strpos( $name, '-db-' ) ? 'database' : 'full' ), 'remote' => (array) $remote, 'locked' => $locked ) + Digitalisimo_Backups_Security::describe( $file ) + array( 'origin' => 'created' ) + (array) $extra;
		self::change_history( function( &$history ) use ( $name, $row ) {
			// Registrar otra vez el mismo ZIP (un paso repetido tras una muerte) deja una sola fila y conserva su bloqueo.
			foreach ( $history as $entry ) if ( ( $entry['file'] ?? '' ) === $name && ! empty( $entry['locked'] ) ) $row['locked'] = true;
			$history = array_values( array_filter( $history, function( $entry ) use ( $name ) { return ( $entry['file'] ?? '' ) !== $name; } ) );
			array_unshift( $history, $row );
			$history = array_merge( array_slice( $history, 0, 100 ), array_values( array_filter( array_slice( $history, 100 ), function( $entry ) { return ! empty( $entry['locked'] ); } ) ) );
		} );
	}
	/**
	 * Lee el historial de la base de datos, nunca de la caché de esta petición, lo cambia y lo guarda con el cerrojo corto
	 * del almacén. Un proceso largo (WP-CLI o varios trabajos seguidos) guardaría en su caché una copia anterior, y dos
	 * peticiones a la vez escribirían una sobre la otra: un «Bloquear» o un «Eliminar» se perderían y la retención podría
	 * borrar un respaldo bloqueado. Si `$change` devuelve false no se escribe nada.
	 */
	private static function change_history( callable $change ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $change ) {
			$history = self::fresh_history();
			$result = $change( $history );
			if ( false !== $result ) update_option( self::HISTORY, array_values( (array) $history ), false );
			return $result;
		} );
	}
	private static function fresh_history() {
		if ( function_exists( 'wp_cache_delete' ) && function_exists( 'wp_cache_get' ) && function_exists( 'wp_cache_set' ) ) {
			wp_cache_delete( self::HISTORY, 'options' );
			$missing = wp_cache_get( 'notoptions', 'options' );
			if ( is_array( $missing ) && isset( $missing[ self::HISTORY ] ) ) { unset( $missing[ self::HISTORY ] ); wp_cache_set( 'notoptions', $missing, 'options' ); }
			if ( function_exists( 'wp_load_alloptions' ) && array_key_exists( self::HISTORY, (array) wp_load_alloptions() ) ) wp_cache_delete( 'alloptions', 'options' );
		}
		return (array) get_option( self::HISTORY, array() );
	}
	public static function toggle_lock() { if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::ACTION_LOCK ); $file = sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) ); $locked = ! empty( $_POST['locked'] ); try { self::change_history( function( &$history ) use ( $file, $locked ) { foreach ( $history as &$item ) if ( hash_equals( (string) ( $item['file'] ?? '' ), $file ) ) $item['locked'] = $locked; unset( $item ); } ); } catch ( Exception $e ) { self::redirect( false, $e->getMessage() ); } self::redirect( true, ! empty( $_POST['locked'] ) ? 'Respaldo bloqueado: no se eliminará durante la rotación.' : 'Respaldo desbloqueado.' ); }
	private static function prune( $dir, $keep ) { Digitalisimo_Backups_Job_Store::with_lock( function() use ( $dir, $keep ) { self::prune_locked( $dir, $keep ); } ); }
	private static function prune_policy( $policy ) { Digitalisimo_Backups_Job_Store::with_lock( function() use ( $policy ) { self::prune_policy_locked( $policy ); } ); }
	/** Con el cerrojo del historial: un «Bloquear» llega antes de la retención o después, nunca en medio. */
	private static function prune_locked( $dir, $keep ) { $busy = self::busy_backup_files(); if ( null === $busy ) return; $history = self::fresh_history(); $locked = array_filter( array_column( array_filter( $history, function( $item ) { return ! empty( $item['locked'] ); } ), 'file' ) ); $files = array_merge( glob( trailingslashit( $dir ) . 'digitalisimo-*.zip' ) ?: array(), glob( trailingslashit( $dir ) . 'digitalisimo-*.dgbk' ) ?: array() ); usort( $files, function( $a, $b ) { return filemtime( $b ) <=> filemtime( $a ); } ); $rotating = array_values( array_filter( $files, function( $file ) use ( $locked ) { return ! in_array( wp_basename( $file ), $locked, true ); } ) ); foreach ( array_slice( $rotating, $keep ) as $file ) if ( ! isset( $busy[ wp_basename( $file ) ] ) ) @unlink( $file ); }
	private static function prune_policy_locked( $policy ) { $busy = self::busy_backup_files(); if ( null === $busy ) return; $history = self::fresh_history(); $matching = array_values( array_filter( $history, function( $item ) use ( $policy ) { return ( $item['policy'] ?? '' ) === $policy['id'] && empty( $item['locked'] ); } ) ); foreach ( array_slice( $matching, (int) $policy['retain'] ) as $item ) { $name = sanitize_file_name( $item['file'] ?? '' ); if ( isset( $busy[ $name ] ) ) continue; $path = trailingslashit( self::local_directory() ) . $name; if ( is_file( $path ) ) @unlink( $path ); } }
	/**
	 * ZIP que un trabajo sin terminar todavía necesita (el verificado que espera su copia remota, con la transferencia en
	 * espera de reintento): la retención de otro trabajo nunca los borra; cuentan para el tope y se retiran en una poda
	 * posterior. Sin poder leer la cola, la retención no borra nada (null).
	 */
	private static function busy_backup_files() {
		$busy = array();
		try {
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( ! empty( $job['spec']['file'] ) ) $busy[ wp_basename( (string) $job['spec']['file'] ) ] = true;
		} catch ( Throwable $e ) {
			return null;
		}
		return $busy;
	}
		public static function redirect( $ok, $message, $network = null, $tab = '' ) { $network = null === $network ? self::network_context() : $network; $url = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-backups' ) : admin_url( 'admin.php?page=digitalisimo-backups' ); $args = array( 'backup' => $ok ? 'ok' : 'error', 'message' => rawurlencode( $message ) ); if ( $tab ) $args['tab'] = $tab; wp_safe_redirect( add_query_arg( $args, $url ) ); exit; }
	private static function create_for_policy( $policy, $site_id ) { if ( 'incremental' === $policy['type'] && ! self::latest_full_backup( $site_id ) ) return self::create_site( $site_id, 'full' ); return 'incremental' === $policy['type'] ? self::create_incremental_site( $site_id, $policy ) : self::create_site( $site_id, 'database' === $policy['type'] ? 'database' : 'full' ); }
	private static function latest_full_backup( $site_id ) { foreach ( (array) get_option( self::HISTORY, array() ) as $item ) if ( (int) ( $item['site_id'] ?? 0 ) === (int) $site_id && 'full' === ( $item['backup_type'] ?? '' ) && file_exists( trailingslashit( self::local_directory() ) . ( $item['file'] ?? '' ) ) ) return $item; return array(); }
	/** Delta restaurable: base de datos completa y sólo archivos modificados desde el último completo. */
	private static function create_incremental_site( $site_id, $policy ) {
		if ( ! class_exists( 'ZipArchive' ) ) throw new Exception( 'El servidor necesita la extensión PHP ZipArchive para crear el respaldo.' );
		$site = get_site( $site_id );
		if ( ! $site ) throw new Exception( 'El sitio de la política ya no existe.' );
		$dir = self::local_directory();
		if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) throw new Exception( 'No se puede escribir en la carpeta protegida de DIGITALÍSIMO Backups.' );
		self::protect( $dir );
		$base = self::latest_full_backup( $site_id );
		$since = $base ? (int) @filemtime( trailingslashit( $dir ) . $base['file'] ) : 0;
		$file = trailingslashit( $dir ) . 'digitalisimo-site-' . $site_id . '-' . sanitize_title( $site->domain ) . '-incremental-' . gmdate( 'Ymd-His' ) . '.zip';
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) throw new Exception( 'No se pudo crear el ZIP incremental.' );
		try {
			switch_to_blog( $site_id );
			try {
				global $wpdb;
				$prefix = $wpdb->prefix;
				$upload = wp_upload_dir();
				self::zip_add_string( $zip, 'database.sql', self::database_sql( self::site_tables( $prefix ), $prefix, 'wp_' ) );
				self::add_changed_directory( $zip, ABSPATH, $dir, $since, true );
				self::add_changed_directory( $zip, $upload['basedir'], $dir, $since, false, 'wordpress/wp-content/uploads/' );
				self::zip_add_string( $zip, 'manifest.json', wp_json_encode( array( 'format' => 'digitalisimo-backup/v2', 'scope' => 'site', 'type' => 'incremental', 'policy' => $policy['id'], 'base' => $base['file'] ?? '', 'requires_full_backup' => empty( $base ), 'since' => $since, 'created_at' => gmdate( 'c' ) ), JSON_PRETTY_PRINT ) );
			} finally {
				restore_current_blog();
			}
			self::finish_zip( $zip, $file );
			return $file;
		} catch ( Throwable $e ) {
			self::close_quietly( $zip );
			@unlink( $file );
			throw $e;
		}
	}

	private static function add_changed_directory( $zip, $root, $backup_dir, $since, $exclude_uploads = false, $zip_prefix = 'wordpress/' ) { if ( ! is_dir( $root ) ) return; $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY ); $root = trailingslashit( wp_normalize_path( $root ) ); $backup_dir = trailingslashit( wp_normalize_path( $backup_dir ) ); foreach ( $iterator as $entry ) { if ( ! $entry->isFile() || $entry->isLink() || ( $since && $entry->getMTime() <= $since ) ) continue; $path = wp_normalize_path( $entry->getPathname() ); $relative = ltrim( substr( $path, strlen( $root ) ), '/' ); if ( 0 === strpos( $path, $backup_dir ) || false !== strpos( $path, '/.git/' ) || false !== strpos( $path, '/node_modules/' ) || false !== strpos( $path, '/cache/' ) || ( $exclude_uploads && 0 === strpos( $path, trailingslashit( wp_normalize_path( WP_CONTENT_DIR . '/uploads' ) ) ) ) || ( $exclude_uploads && 'wp-config.php' === $relative ) ) continue; self::zip_add_file( $zip, $path, $zip_prefix . $relative ); } }
	/** Respaldos del historial y archivos sueltos del almacén; un archivo sin registro nunca cuenta como propio. */
	private static function restore_panel( $history ) {
		$dir = trailingslashit( self::local_directory() );
		$local = array_filter( (array) $history, function( $item ) use ( $dir ) { return 'local' === ( $item['destination'] ?? 'local' ) && in_array( $item['scope'] ?? '', array( 'network', 'installation' ), true ) && 'full' === ( $item['backup_type'] ?? 'full' ) && is_file( $dir . ( $item['file'] ?? '' ) ); } );
		$known = array_flip( array_map( 'strval', array_column( (array) get_option( self::HISTORY, array() ), 'file' ) ) );
		$loose = array();
		$extracted = is_multisite() ? array() : array_merge( glob( $dir . Digitalisimo_Backups_Site_Extract::FILE_PREFIX . '*.zip' ) ?: array(), glob( $dir . Digitalisimo_Backups_Site_Extract::FILE_PREFIX . '*.dgbk' ) ?: array() );
		foreach ( array_merge( glob( $dir . 'digitalisimo-*.zip' ) ?: array(), glob( $dir . 'digitalisimo-*.dgbk' ) ?: array(), glob( $dir . 'safety-before-restore-*.zip' ) ?: array(), $extracted ) as $path ) if ( is_file( $path ) && ! is_link( $path ) && ! isset( $known[ wp_basename( $path ) ] ) ) $loose[] = wp_basename( $path );
		sort( $loose );
		echo '<section class="digitalisimo-backups-card"><h2>Restaurar respaldo local</h2><p>Antes de reemplazar la instalación, se creará y bloqueará un respaldo de seguridad actual. La restauración reemplaza la base de datos y los archivos incluidos. Escribe <code>RESTAURAR</code> para un respaldo firmado o registrado en este sitio. Un archivo sin firma válida ni registro pide <code>RESTAURAR SIN FIRMA</code>: restaurarlo instala su código y sus datos, así que revisa antes su origen. Un respaldo cifrado sólo se restaura con su frase o con la clave de <code>wp-config.php</code> que lo cifró: sin ella no se puede restaurar y nadie puede recuperarlo, tampoco DIGITALÍSIMO.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_RESTORE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RESTORE ) . '"><p><select name="backup_file" id="digitalisimo-backups-restore-file"><option value="">Elige un respaldo de Digitalisimo</option>';
		// «Origen → este sitio» de cada archivo, antes de confirmar (de su manifiesto; un .dgbk lo dice al descifrarlo).
		$here = Digitalisimo_Backups_Relocate::describe();
		if ( $local ) { echo '<optgroup label="Historial">'; foreach ( $local as $item ) { $hint = self::relocation_hint( $dir . $item['file'], $here ); echo '<option value="' . esc_attr( $item['file'] ) . '" data-relocation="' . esc_attr( $hint['text'] ) . '">' . esc_html( $item['date'] . ' · ' . $item['file'] . Digitalisimo_Backups_Security::restore_label( $dir . $item['file'], $item ) . $hint['label'] ) . '</option>'; } echo '</optgroup>'; }
		if ( $loose ) { echo '<optgroup label="Archivos del almacén sin registro">'; foreach ( array_slice( $loose, 0, 100 ) as $file ) { $hint = self::relocation_hint( $dir . $file, $here ); echo '<option value="' . esc_attr( $file ) . '" data-relocation="' . esc_attr( $hint['text'] ) . '">' . esc_html( $file . ' · sin registro' . Digitalisimo_Backups_Security::restore_label( $dir . $file ) . $hint['label'] ) . '</option>'; } echo '</optgroup>'; }
		echo '</select></p><p class="description" id="digitalisimo-backups-relocation" aria-live="polite">Si el respaldo viene de otra dirección, de otra carpeta del servidor o con otro prefijo de tablas, se reescriben al restaurar: las URL (también en datos serializados, JSON y URL codificadas, con y sin www, http o https), las rutas del servidor, el prefijo de tablas y las claves que lo llevan y, en una red, el dominio y la ruta de cada sitio. No se tocan la columna guid de las entradas ni los correos.</p>';
		echo '<fieldset><legend>Direcciones del sitio después de restaurar</legend><p><label><input type="radio" name="addresses" value="current" checked> Conservar las de este sitio (' . esc_html( $here['home'] ) . '): las del respaldo se cambian por éstas</label><br><label><input type="radio" name="addresses" value="backup"> Conservar las del respaldo: el sitio quedará en su dirección original (las rutas del servidor y el prefijo siguen siendo los de este servidor)</label></p></fieldset>';
		echo '<p><label>Frase o clave del respaldo <input type="password" name="backup_secret" value="" autocomplete="off"></label><br><span class="description">Sólo para un respaldo cifrado o firmado con una clave que este sitio no tiene (por ejemplo, al restaurar en otro servidor). No se guarda salvo que marques la casilla.</span></p><p><label><input type="checkbox" name="backup_secret_adopt" value="1"> Usar esta frase para los respaldos nuevos</label></p><p><label>Confirmación <input type="text" name="confirmation" placeholder="RESTAURAR" autocomplete="off"></label></p><p><button class="button button-primary">Restaurar respaldo seleccionado</button></p></form>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var s=document.getElementById("digitalisimo-backups-restore-file"),p=document.getElementById("digitalisimo-backups-relocation");if(!s||!p)return;var d=p.textContent;s.addEventListener("change",function(){var o=s.options[s.selectedIndex];p.textContent=o&&o.dataset.relocation?o.dataset.relocation:d})})</script>';
		self::relocation_card();
		echo '</section>';
	}
	/**
	 * «Origen → este sitio» de un archivo del almacén a partir de su manifiesto (sin abrir el SQL): una etiqueta corta
	 * para la lista y el texto completo para el párrafo bajo ella. Un .dgbk no se puede leer sin descifrarlo.
	 */
	private static function relocation_hint( $path, array $here ) {
		static $cache = array();
		$key = $path . '|' . @filemtime( $path );
		if ( isset( $cache[ $key ] ) ) return $cache[ $key ];
		$none = array( 'label' => '', 'text' => '' );
		if ( ! class_exists( 'ZipArchive' ) || Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) return $cache[ $key ] = array( 'label' => '', 'text' => 'Respaldo cifrado: su origen se conoce al descifrarlo; si viene de otra dirección o con otro prefijo, se reescribe igual.' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) return $cache[ $key ] = $none;
		$manifest = self::manifest_head( $zip );
		$zip->close();
		if ( ! is_array( $manifest ) ) return $cache[ $key ] = $none;
		$args = array_key_exists( 'database_tables', $manifest ) ? array( 'tables' => is_array( $manifest['database_tables'] ) ? $manifest['database_tables'] : null ) : array();
		$plan = Digitalisimo_Backups_Relocate::plan( Digitalisimo_Backups_Relocate::origin_of( $manifest ), $here, $args );
		$summary = $plan->summary();
		if ( ! $summary['needed'] && ! $summary['problems'] ) return $cache[ $key ] = array( 'label' => '', 'text' => $summary['text'] );
		$label = ' · origen ' . ( '' !== $summary['origin']['home'] ? $summary['origin']['home'] : 'desconocido' ) . ( $summary['origin']['prefix'] !== $summary['destination']['prefix'] && '' !== $summary['origin']['prefix'] ? ' (' . $summary['origin']['prefix'] . ')' : '' ) . ' → este sitio' . ( $summary['problems'] ? ': no se puede restaurar aquí' : '' );
		return $cache[ $key ] = array( 'label' => $label, 'text' => $summary['text'] );
	}
	/**
	 * Lo que la lista de Restaurar necesita del manifiesto (direcciones, prefijo, tipo de instalación, `origin` y, si
	 * cabe, la lista de tablas) sin cargarlo entero: el de un sitio con cientos de miles de archivos pesa decenas de MB y
	 * la pantalla lo lee de cada respaldo. Más de 1 MiB: sólo su comienzo, donde van esas claves (antes de `entries`).
	 */
	private static function manifest_head( $zip ) {
		$stat = $zip->statName( 'manifest.json' );
		if ( ! $stat ) return null;
		if ( (int) $stat['size'] <= 1048576 ) return json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$stream = $zip->getStream( 'manifest.json' );
		if ( ! $stream ) return null;
		$head = (string) fread( $stream, 1048576 );
		fclose( $stream );
		$out = array();
		foreach ( array( 'home_url', 'site_url', 'table_prefix' ) as $name ) if ( preg_match( '/"' . $name . '"\s*:\s*("(?:[^"\\\\]|\\\\.)*")/', $head, $m ) ) $out[ $name ] = json_decode( $m[1] );
		if ( preg_match( '/"multisite"\s*:\s*(true|false)/', $head, $m ) ) $out['multisite'] = 'true' === $m[1];
		if ( preg_match( '/"origin"\s*:\s*(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})/', $head, $m ) ) $out['origin'] = json_decode( $m[1], true );
		if ( ! isset( $out['multisite'] ) && isset( $out['origin']['multisite'] ) ) $out['multisite'] = (bool) $out['origin']['multisite'];
		return $out;
	}
	/** Informe de la última restauración que reescribió direcciones, rutas o prefijo. */
	private static function relocation_card() {
		$last = get_option( self::LAST_RELOCATION );
		if ( ! is_array( $last ) || empty( $last['summary'] ) ) return;
		$check = is_array( $last['check'] ?? null ) ? $last['check'] : null;
		echo '<h3>Última restauración en otro dominio</h3><p>' . esc_html( (string) ( $last['date'] ?? '' ) . ' · ' . (string) ( $last['file'] ?? '' ) ) . '</p><p>' . esc_html( (string) ( $last['summary']['text'] ?? '' ) ) . '</p>';
		if ( $check ) echo '<p>' . esc_html( 'Comprobación final: ' . (int) $check['urls'] . ' URL y ' . (int) $check['paths'] . ' rutas del origen pendientes (debe ser 0); sin tocar a propósito: ' . (int) $check['guid'] . ' guid, ' . (int) ( $check['kept'] ?? 0 ) . ' en datos que no se pueden reescribir sin riesgo (serializado dañado, objeto con formato propio, clave que chocaría), ' . (int) $check['emails'] . ' correos y ' . (int) $check['other'] . ' menciones sin esquema.' . ( empty( $check['complete'] ) ? ' Revisión parcial: había más filas candidatas de las que se revisaron.' : '' ) ) . '</p>';
		$rows = array();
		foreach ( (array) ( $last['tables'] ?? array() ) as $table => $counts ) if ( ! empty( $counts['rows'] ) || ! empty( $counts['keys'] ) || ! empty( $counts['malformed'] ) ) $rows[ $table ] = $counts;
		if ( $rows ) {
			echo '<table class="widefat striped"><thead><tr><th>Tabla</th><th>Filas reescritas</th><th>Valores</th><th>Claves con prefijo</th><th>Sin tocar (dañados o con formato propio)</th></tr></thead><tbody>';
			foreach ( $rows as $table => $counts ) echo '<tr><td><code>' . esc_html( $table ) . '</code></td><td>' . (int) ( $counts['rows'] ?? 0 ) . '</td><td>' . (int) ( $counts['values'] ?? 0 ) . '</td><td>' . (int) ( $counts['keys'] ?? 0 ) . '</td><td>' . (int) ( ( $counts['malformed'] ?? 0 ) + ( $counts['opaque'] ?? 0 ) + ( $counts['too_large'] ?? 0 ) ) . '</td></tr>';
			echo '</tbody></table>';
		}
		foreach ( (array) ( $last['warnings'] ?? array() ) as $warning ) echo '<p class="notice notice-warning inline">' . esc_html( (string) $warning ) . '</p>';
	}
	public static function restore_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_RESTORE );
		@set_time_limit( 0 );
		$confirmation = strtoupper( trim( preg_replace( '/\s+/', ' ', (string) wp_unslash( $_POST['confirmation'] ?? '' ) ) ) );
		if ( ! in_array( $confirmation, array( 'RESTAURAR', 'RESTAURAR SIN FIRMA' ), true ) ) self::redirect( false, 'Escribe RESTAURAR para confirmar una operación destructiva.' );
		if ( empty( $_POST['updraft'] ) ) {
			// Mismo caso de uso que REST y WP-CLI. En una red, esta pantalla restaura la red entera. La frase se usa tal
			// cual: sanitize_text_field() la alteraría.
			$secret = isset( $_POST['backup_secret'] ) && is_string( $_POST['backup_secret'] ) ? (string) wp_unslash( $_POST['backup_secret'] ) : '';
			$addresses = 'backup' === sanitize_key( wp_unslash( $_POST['addresses'] ?? '' ) ) ? 'backup' : 'current';
			$result = self::service_restore( wp_unslash( $_POST['backup_file'] ?? '' ), array( 'confirm' => $confirmation, 'network' => is_multisite(), 'secret' => $secret, 'adopt' => ! empty( $_POST['backup_secret_adopt'] ), 'addresses' => $addresses ) );
			if ( is_wp_error( $result ) ) self::redirect( false, 'La restauración se detuvo: ' . $result->get_error_message() );
			self::redirect( true, 'Restauración terminada. Entra de nuevo y revisa enlaces permanentes.' . ( '' !== $result['notice'] ? ' ' . $result['notice'] : '' ) );
		}
		// UpdraftPlus reemplaza también la instalación entera: en una red, sólo desde el sitio principal de la red principal.
		$allowed = self::service_scope( is_multisite(), true );
		if ( is_wp_error( $allowed ) ) self::redirect( false, 'La restauración se detuvo: ' . $allowed->get_error_message() );
		try {
			$operation_lock = self::acquire_operation_lock();
			try {
				if ( Digitalisimo_Backups_Jobs::has_active() ) throw new Exception( 'Hay un respaldo pendiente o en curso. Cancélalo en «Trabajos» o espera a que termine antes de restaurar.' );
				Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'untrusted', 'auth' => 'unsigned' ), $confirmation );
				self::restore_updraft_set( sanitize_text_field( wp_unslash( $_POST['updraft_set'] ?? '' ) ) );
			} finally {
				self::release_operation_lock( $operation_lock );
				// El SQL restaurado trae su propia opción `cron`: un trabajo encolado durante la restauración recupera su tick.
				try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
			}
			self::redirect( true, 'Restauración terminada. Entra de nuevo y revisa enlaces permanentes.' );
		} catch ( Exception $e ) { self::redirect( false, 'La restauración se detuvo: ' . $e->getMessage() ); }
	}
	private static function local_path( $file ) { $file = sanitize_file_name( $file ); if ( '' === $file || '.' === $file[0] ) return ''; $root = realpath( self::local_directory() ); if ( ! $root || is_link( trailingslashit( $root ) . $file ) ) return ''; $path = realpath( trailingslashit( $root ) . $file ); return $path && dirname( wp_normalize_path( $path ) ) === wp_normalize_path( $root ) && is_file( $path ) ? $path : ''; }
	/**
	 * Restaura un ZIP propio. Todo lo que puede rechazarlo (firma, entradas, contenido, manifiesto, espacio y SQL) se
	 * comprueba antes de la copia previa y antes de cualquier consulta o escritura. $report es el de
	 * Digitalisimo_Backups_Security::open(); sin él se abre aquí.
	 *
	 * Si el respaldo viene de otra dirección, otra carpeta del servidor u otro prefijo de tablas, el plan de
	 * Digitalisimo_Backups_Relocate se calcula antes de la copia previa (y lo que lo impide la detiene) y se aplica en la
	 * importación, fila por fila; después de extraer los archivos, finish() ajusta la red y las direcciones, vacía las
	 * cachés y cuenta lo que quedó del origen. `$options['addresses']`: `current` (por defecto, el sitio conserva sus
	 * direcciones) o `backup` (conserva las del respaldo). Devuelve el informe de la reubicación, o null si no hizo falta.
	 */
	private static function restore_digitalisimo_zip( $path, $report = null, array $options = array() ) {
		if ( ! class_exists( 'ZipArchive' ) ) throw new Exception( 'El servidor no tiene ZipArchive.' );
		$owned = null === $report;
		if ( $owned ) $report = Digitalisimo_Backups_Security::open( $path, self::history_item( wp_basename( $path ) ) );
		$zip = $report['zip'];
		$origin = array();
		$relocation = null;
		try {
			$trusted = 'untrusted' !== $report['trust'];
			$source = array();
			$data = self::inspect_package( $zip, $trusted, true, $source );
			// Con esta instalación tal como está ahora: después del SQL sus direcciones serían las del respaldo.
			$plan = self::relocation_plan( $data, $source, $options );
			if ( $plan->problems() ) throw new Exception( implode( ' ', $plan->problems() ) );
			// Un sitio extraído de una red no trae la configuración de Backups: la de este servidor se conserva.
			$kept = Digitalisimo_Backups_Site_Extract::SCOPE === ( $data['scope'] ?? '' ) ? Digitalisimo_Backups_Site_Extract::destination_snapshot() : null;
			// Claves de las credenciales del servidor de origen, del wp-config.php del propio respaldo (ya comprobado contra
			// su manifiesto): sólo literales de cadena, nunca se ejecuta. Con ellas, las credenciales se vuelven a cifrar aquí.
			$origin = Digitalisimo_Backups_Credentials::origin_keys( $zip );
			// Copia previa con el motor de trabajos: nunca se cifra, se firma si hay clave y nace con su nombre protegido.
			$safety = self::create_network( 'safety-before-restore-' );
			$policy = array( 'id' => 'pre-restore', 'type' => 'full' );
			$vault_before = Digitalisimo_Backups_Keys::snapshot();
			self::remember( $safety, 'local', is_multisite() ? 'network' : 'installation', $policy, array(), 0, true );
			// El historial que debe quedar, ya con la copia previa: reconstruirlo no vuelve a leer la copia entera.
			$history_before = self::fresh_history();
			self::write_restore_journal( $origin, $kept );
			$created_files = array();
			try {
				$guard = Digitalisimo_Backups_Sql_Guard::for_backup( $data, $trusted );
				// Prefijo, direcciones y rutas cambian en cada fila antes de ejecutarla: memoria constante y, si el proceso
				// muere a mitad, lo ya importado está completo (también los roles y permisos con el prefijo nuevo).
				if ( $plan->needed() ) $plan->attach( $guard, (array) ( $data['database_tables'] ?? array() ) );
				self::import_zip_sql( $zip, $guard );
				// Antes de la extracción, la parte larga: si el proceso muere mientras escribe archivos, el historial, el
				// llavero, la seguridad y las credenciales ya están en su sitio. El finally lo repite y es quien avisa si falla.
				try { self::after_restore_sql( $history_before, $vault_before, $origin, $kept ); } catch ( Throwable $ignored ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				self::extract_digitalisimo_files( $zip, $created_files );
				// Red, direcciones, cachés (también el CSS generado que trajeron los archivos) y comprobación final.
				if ( $plan->needed() ) $relocation = $plan->finish( $plan->table_names() );
			} catch ( Throwable $failure ) {
				try {
					self::rollback_from_safety( $safety );
					self::remove_new_restore_files( $created_files );
					throw new Exception( 'La restauración falló. Se recuperaron la base de datos y los archivos anteriores desde ' . wp_basename( $safety ) . ' y se retiraron los archivos nuevos: ' . $failure->getMessage(), 0, $failure );
				} catch ( Throwable $rollback_error ) {
					if ( $rollback_error->getPrevious() === $failure ) throw $rollback_error;
					throw new Exception( 'La restauración falló y la reversión también falló. Conserva ' . wp_basename( $safety ) . ' para recuperación manual: ' . $rollback_error->getMessage(), 0, $failure );
				}
			} finally {
				self::after_restore_sql( $history_before, $vault_before, $origin, $kept );
				self::remove_restore_journal();
			}
			if ( null !== $relocation ) update_option( self::LAST_RELOCATION, array( 'date' => current_time( 'mysql' ), 'file' => wp_basename( $path ) ) + $relocation, false );
			return $relocation;
		} finally {
			Digitalisimo_Backups_Credentials::forget( $origin );
			if ( $owned ) Digitalisimo_Backups_Security::close( $report );
		}
	}
	/** Tablas del respaldo que cambian de nombre en esta instalación (el mismo mapa que usa la importación). */
	private static function relocation_renames( array $data ) {
		$from = (string) ( $data['table_prefix'] ?? '' );
		$to = (string) ( $GLOBALS['wpdb']->base_prefix ?? '' );
		if ( '' === $from || '' === $to || $from === $to || ! is_array( $data['database_tables'] ?? null ) ) return array();
		$relocate = new Digitalisimo_Backups_Relocate();
		$map = $relocate->set_prefixes( array( $from => $to ), $to )->table_map( $data['database_tables'] );
		return array_filter( $map, function( $new, $old ) { return $new !== $old; }, ARRAY_FILTER_USE_BOTH );
	}
	/**
	 * Plan de reubicación de un paquete ya inspeccionado contra esta instalación (Digitalisimo_Backups_Relocate::plan()).
	 * Un respaldo sin lista de tablas (formato anterior al v3) sólo se restaura con su mismo prefijo.
	 */
	private static function relocation_plan( array $data, array $source, array $options ) {
		$args = array( 'addresses' => 'backup' === ( $options['addresses'] ?? 'current' ) ? 'backup' : 'current' );
		$args['tables'] = is_array( $data['database_tables'] ?? null ) ? $data['database_tables'] : null;
		// Un sitio extraído de una red: además, la URL de sus medios en la red (`/sites/N`) pasa a la de un sitio individual.
		return Digitalisimo_Backups_Site_Extract::adjust_plan( Digitalisimo_Backups_Relocate::plan( $source, Digitalisimo_Backups_Relocate::describe(), $args ), $data );
	}
	/**
	 * Todo lo que puede rechazar un paquete antes de la copia previa y de cualquier consulta o escritura: entradas,
	 * contenido contra el manifiesto, alcance completo, manifiesto v3 estricto, base de datos y guardián SQL en seco.
	 * Con $installation, también las condiciones de esta instalación (red o sitio, sitio principal, prefijo de tablas y
	 * espacio libre), en el mismo orden de siempre. La restauración y `verify` (REST y WP-CLI) usan esta misma función.
	 * Devuelve el manifiesto (el estricto si es v3). En $source deja el origen del respaldo para la reubicación: su bloque
	 * `origin` completado con lo que dice su SQL (Digitalisimo_Backups_Relocate_Probe, en la misma pasada en seco).
	 */
	private static function inspect_package( $zip, $trusted, $installation, &$source = null ) {
		Digitalisimo_Backups_Archive_Inspector::entries( $zip, $trusted );
		self::verify_zip_contents( $zip );
		$manifest = $zip->getFromName( 'manifest.json' );
		$migration = $zip->getFromName( 'migration.json' );
		$metadata = false !== $manifest ? $manifest : $migration;
		$data = json_decode( (string) $metadata, true );
		if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) throw new Exception( 'La metadata del respaldo no es válida.' );
		if ( in_array( $data['type'] ?? '', array( 'incremental', 'database' ), true ) ) throw new Exception( 'Este paquete parcial no admite restauración completa sin su cadena de respaldo.' );
		if ( 'site' === ( $data['scope'] ?? '' ) ) throw new Exception( 'Una exportación anterior de subsitio no puede reemplazar una instalación completa.' );
		// Un sitio extraído de una red (alcance explícito, sólo con manifiesto v3) se restaura en un WordPress individual.
		$extracted = Digitalisimo_Backups_Site_Extract::SCOPE === ( $data['scope'] ?? '' );
		if ( $extracted && 'digitalisimo-backup/v3' !== ( $data['format'] ?? '' ) ) throw new Exception( 'El alcance del respaldo completo no es compatible.' );
		if ( $extracted && $installation && is_multisite() ) throw new Exception( 'Este paquete es un sitio extraído de una red: se restaura en un WordPress individual recién instalado, no en una red.' );
		if ( ! $extracted && ! in_array( $data['scope'] ?? '', array( 'network', 'installation' ), true ) ) throw new Exception( 'El alcance del respaldo completo no es compatible.' );
		if ( $installation ) foreach ( self::package_installation_problems( $data ) as $problem ) throw new Exception( $problem );
		if ( 'digitalisimo-backup/v3' === ( $data['format'] ?? '' ) ) $data = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
		$sql_stat = $zip->statName( 'database.sql' );
		if ( ! $sql_stat || empty( $sql_stat['size'] ) ) throw new Exception( 'El ZIP no contiene una base de datos válida.' );
		if ( $installation ) Digitalisimo_Backups_Archive_Inspector::space( $zip, ABSPATH );
		$probe = new Digitalisimo_Backups_Relocate_Probe( (string) ( $data['table_prefix'] ?? '' ) );
		$guard = Digitalisimo_Backups_Sql_Guard::for_backup( $data, $trusted )->on_insert( $probe );
		// Al restaurar con otro prefijo, la pasada en seco renombra igual que la importación: lo que no se puede renombrar
		// (una vista, una tabla en un comentario ejecutable) o dos tablas que chocarían se rechazan antes de la copia previa.
		if ( $installation ) {
			$renames = self::relocation_renames( $data );
			if ( $renames ) $guard->rename_tables( $renames );
		}
		$guard->scan_zip( $zip );
		$source = Digitalisimo_Backups_Relocate::origin_of( $data, $probe );
		return $data;
	}
	/** Por qué un paquete íntegro no puede restaurarse en esta instalación (vacío si puede). */
	private static function package_installation_problems( array $data ) {
		$problems = array();
		// Un prefijo de tablas distinto ya no impide restaurar: la reubicación lo cambia (ver relocation_plan()).
		if ( Digitalisimo_Backups_Site_Extract::SCOPE === ( $data['scope'] ?? '' ) && is_multisite() ) $problems[] = 'Este paquete es un sitio extraído de una red: se restaura en un WordPress individual recién instalado, no en una red.';
		elseif ( isset( $data['multisite'] ) && (bool) $data['multisite'] !== is_multisite() ) $problems[] = 'No se puede restaurar directamente entre WordPress individual y Multisite: ' . ( $data['multisite'] ? 'el respaldo es una red y este WordPress es un sitio individual. Convierte esta instalación en una red del mismo tipo (subdominios o subdirectorios) desde Herramientas → Configuración de la red y vuelve a restaurar.' : 'el respaldo es un sitio individual y este WordPress es una red. Restáuralo en una instalación individual.' );
		if ( is_multisite() && (int) get_current_blog_id() !== (int) get_main_site_id() ) $problems[] = 'La red completa sólo puede restaurarse desde su sitio principal.';
		return $problems;
	}
	/**
	 * El SQL restaurado reemplaza wp_options (y sitemeta): el historial, el llavero y la seguridad deben reconstruirse, y
	 * las credenciales del respaldo cifrarse con las claves de este servidor. Idempotente.
	 */
	private static function after_restore_sql( $history_before, $vault_before, array $origin = array(), $kept = null ) {
		wp_cache_flush();
		if ( is_array( $kept ) ) Digitalisimo_Backups_Site_Extract::reapply( $kept );
		update_option( self::HISTORY, $history_before, false );
		Digitalisimo_Backups_Keys::restore_snapshot( $vault_before );
		self::restore_credentials( $origin );
	}
	/** Sólo borra archivos creados por este intento y que no cambiaron desde que se escribieron. */
	private static function remove_new_restore_files( $created_files ) {
		$real_root = realpath( ABSPATH );
		if ( false === $real_root ) throw new Exception( 'No se puede comprobar la ruta de los archivos nuevos.' );
		$root = trailingslashit( wp_normalize_path( $real_root ) );
		foreach ( $created_files as $path => $expected_hash ) {
			$normalized = wp_normalize_path( $path );
			if ( 0 !== strpos( $normalized, $root ) || is_link( $path ) ) throw new Exception( 'Un archivo nuevo quedó fuera de la ruta segura de restauración.' );
			if ( ! file_exists( $path ) ) continue;
			$actual_hash = is_file( $path ) ? @hash_file( 'sha256', $path ) : false;
			if ( false === $actual_hash || ! hash_equals( $expected_hash, $actual_hash ) ) throw new Exception( 'Un archivo nuevo cambió durante la reversión y requiere revisión manual: ' . $path );
			if ( ! @unlink( $path ) ) throw new Exception( 'No se pudo retirar un archivo nuevo durante la reversión: ' . $path );
		}
	}
	/** Reintenta la instalación anterior sin crear otra copia ni entrar en recursión. */
	private static function rollback_from_safety( $path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CHECKCONS ) ) throw new Exception( 'No se pudo abrir la copia de seguridad previa.' );
		try {
			self::validate_zip_entries( $zip );
			self::verify_zip_contents( $zip );
			$sql_stat = $zip->statName( 'database.sql' );
			if ( ! $sql_stat || empty( $sql_stat['size'] ) ) throw new Exception( 'La copia previa no contiene SQL.' );
			self::import_zip_sql( $zip );
			self::extract_digitalisimo_files( $zip );
		} finally {
			$zip->close();
		}
	}

	private static function validate_zip_entries( $zip ) { Digitalisimo_Backups_Archive_Inspector::entries( $zip, true ); }
	private static function extract_digitalisimo_files( $zip, &$created_files = null ) {
		$real_root = realpath( ABSPATH );
		if ( false === $real_root ) throw new Exception( 'No se pudo resolver la instalación de destino.' );
		$root = trailingslashit( wp_normalize_path( $real_root ) );
		$active_database_files = self::active_sqlite_database_files();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			if ( 0 !== strpos( $name, 'wordpress/' ) || 'wordpress/wp-config.php' === $name || '/' === substr( $name, -1 ) ) continue;
			$relative = substr( $name, strlen( 'wordpress/' ) );
			if ( ! $relative || 0 === strpos( $relative, '.git/' ) ) continue;
			if ( isset( $active_database_files[ $root . $relative ] ) ) continue;
			$created_hash = self::write_entry( $zip, $i, $root, $relative, is_array( $created_files ) );
			if ( null !== $created_hash ) $created_files[ $root . $relative ] = $created_hash;
		}
	}
	/**
	 * Escribe una entrada bajo $root mediante un temporal: sin seguir enlaces, sin salir de $root y sin escribir más
	 * de lo declarado (un tamaño falso no llena el disco). Devuelve el SHA-256 de un archivo nuevo si $track.
	 */
	private static function write_entry( $zip, $index, $root, $relative, $track ) {
		$destination = $root . $relative;
		$current = rtrim( $root, '/' );
		foreach ( explode( '/', dirname( $relative ) ) as $part ) {
			if ( '.' === $part || '' === $part ) continue;
			$current .= '/' . $part;
			if ( is_link( $current ) ) throw new Exception( 'Una carpeta de restauración es un enlace simbólico: ' . $relative );
		}
		if ( is_link( $destination ) ) throw new Exception( 'Un archivo de restauración es un enlace simbólico: ' . $relative );
		$was_present = file_exists( $destination );
		if ( ! wp_mkdir_p( dirname( $destination ) ) ) throw new Exception( 'No se pudo preparar una carpeta de destino segura.' );
		$real_parent = realpath( dirname( $destination ) );
		if ( false === $real_parent || 0 !== strpos( trailingslashit( wp_normalize_path( $real_parent ) ), $root ) ) throw new Exception( 'La carpeta de destino sale de WordPress.' );
		$stat = $zip->statIndex( $index );
		$input = $stat ? $zip->getStream( $stat['name'] ) : false;
		$temp = $destination . '.digitalisimo-restore-' . wp_generate_uuid4();
		$output = @fopen( $temp, 'xb' );
		if ( ! $stat || ! $input || ! $output ) { if ( $input ) fclose( $input ); if ( $output ) fclose( $output ); @unlink( $temp ); throw new Exception( 'No se pudo preparar el archivo ' . $relative . ' para restaurarlo.' ); }
		$declared = (int) $stat['size'];
		$written = stream_copy_to_stream( $input, $output, $declared + 1 );
		$closed = fclose( $output );
		fclose( $input );
		$crc = @hash_file( 'crc32b', $temp );
		if ( false === $written || ! $closed || $written !== $declared || false === $crc || $crc !== sprintf( '%08x', $stat['crc'] ) ) { @unlink( $temp ); throw new Exception( 'El archivo restaurado ' . $relative . ' quedó incompleto.' ); }
		$created_hash = ! $was_present && $track ? @hash_file( 'sha256', $temp ) : null;
		if ( false === $created_hash ) { @unlink( $temp ); throw new Exception( 'No se pudo verificar el archivo nuevo ' . $relative . '.' ); }
		if ( ! @rename( $temp, $destination ) ) { @unlink( $temp ); throw new Exception( 'No se pudo reemplazar el archivo ' . $relative . ' durante la restauración.' ); }
		return $created_hash;
	}

	/** Lee el SQL del ZIP por fragmentos; cada sentencia pasa el guardián antes de ejecutarse. */
	private static function import_zip_sql( $zip, $guard = null ) {
		global $wpdb;
		$guard = $guard ?: new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::EXPORTER, true );
		$stream = $zip->getStream( 'database.sql' );
		if ( ! $stream ) throw new Exception( 'No se pudo leer la base de datos del ZIP.' );
		try {
			$guard->scan( function() use ( $stream ) {
				if ( feof( $stream ) ) return null;
				$chunk = fread( $stream, 65536 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) throw new Exception( 'La base de datos del ZIP está truncada.' );
				return $chunk;
			}, function( $statement ) use ( $wpdb ) {
				if ( false === $wpdb->query( $statement ) ) throw new Exception( 'Falló una sentencia SQL: ' . $wpdb->last_error );
			} );
		} finally {
			fclose( $stream );
		}
	}
	/**
	 * UpdraftPlus siempre es un archivo no confiable: se inspeccionan todas sus piezas y se analiza su SQL (dialecto
	 * mysqldump, tablas con el prefijo de este sitio) antes de cambiar nada; después se importa por fragmentos y se
	 * extrae con el mismo escritor seguro que el respaldo propio, sin wp-config.php en ninguna profundidad.
	 */
	private static function restore_updraft_set( $id ) {
		global $wpdb;
		$sets = self::detect_updraft_sets();
		if ( empty( $sets[ $id ] ) || true !== self::validate_backup_set( $sets[ $id ] ) ) throw new Exception( 'El conjunto UpdraftPlus no está completo, es cifrado o no se puede leer.' );
		$set = $sets[ $id ];
		$database = $set['files']['db'][0]['path'];
		$pieces = array();
		try {
			foreach ( array( 'plugins' => WP_PLUGIN_DIR, 'themes' => get_theme_root(), 'uploads' => wp_upload_dir()['basedir'], 'others' => WP_CONTENT_DIR ) as $component => $root ) {
				foreach ( (array) ( $set['files'][ $component ] ?? array() ) as $part ) {
					if ( 'zip' !== strtolower( pathinfo( $part['path'], PATHINFO_EXTENSION ) ) ) throw new Exception( 'El archivo UpdraftPlus de ' . $component . ' no es ZIP.' );
					if ( is_link( $part['path'] ) ) throw new Exception( 'Una pieza UpdraftPlus es un enlace simbólico.' );
					$zip = new ZipArchive();
					if ( true !== $zip->open( $part['path'], ZipArchive::CHECKCONS ) ) throw new Exception( 'No se pudo abrir una pieza UpdraftPlus.' );
					$pieces[] = array( $zip, $root, $component );
					Digitalisimo_Backups_Archive_Inspector::entries( $zip, false, 'loose' );
				}
			}
			$prefix = (string) $wpdb->base_prefix;
			self::updraft_sql( $database, new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::MYSQLDUMP, false, null, $prefix ), false );
			// Su SQL reemplaza wp_options como el propio: el historial, el llavero y la seguridad no se pierden.
			$history_before = (array) get_option( self::HISTORY, array() );
			$vault_before = Digitalisimo_Backups_Keys::snapshot();
			self::write_restore_journal();
			try {
				self::updraft_sql( $database, new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::MYSQLDUMP, false, null, $prefix ), true );
				try { self::after_restore_sql( $history_before, $vault_before ); } catch ( Throwable $ignored ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				foreach ( $pieces as $piece ) self::extract_updraft_zip( $piece[0], $piece[1], $piece[2] );
			} finally {
				self::after_restore_sql( $history_before, $vault_before );
				self::remove_restore_journal();
			}
		} finally {
			foreach ( $pieces as $piece ) self::close_quietly( $piece[0] );
		}
	}
	private static function updraft_sql( $path, $guard, $execute ) {
		global $wpdb;
		$handle = function_exists( 'gzopen' ) ? @gzopen( $path, 'rb' ) : false;
		if ( ! $handle ) throw new Exception( 'No se pudo descomprimir la base de datos de UpdraftPlus.' );
		try {
			return $guard->scan( function() use ( $handle ) {
				if ( gzeof( $handle ) ) return null;
				$chunk = gzread( $handle, 65536 );
				if ( false === $chunk || ( '' === $chunk && ! gzeof( $handle ) ) ) throw new Exception( 'No se pudo descomprimir la base de datos de UpdraftPlus.' );
				return '' === $chunk ? null : $chunk;
			}, $execute ? function( $statement ) use ( $wpdb ) {
				if ( false === $wpdb->query( $statement ) ) throw new Exception( 'Falló una sentencia SQL: ' . $wpdb->last_error );
			} : null );
		} finally {
			gzclose( $handle );
		}
	}
	private static function extract_updraft_zip( $zip, $root, $component ) {
		if ( ! wp_mkdir_p( $root ) ) throw new Exception( 'No se pudo crear una carpeta para UpdraftPlus.' );
		$real_root = realpath( $root );
		if ( false === $real_root ) throw new Exception( 'No se pudo resolver la carpeta de ' . $component . '.' );
		$real_root = trailingslashit( wp_normalize_path( $real_root ) );
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( '/' === substr( $name, -1 ) ) continue;
			$relative = preg_replace( '#^(wp-content/)?' . preg_quote( $component, '#' ) . '/#', '', $name );
			if ( ! $relative || 'wp-config.php' === basename( $relative ) ) continue;
			self::write_entry( $zip, $i, $real_root, $relative, false );
		}
	}
	private static function history_item( $file ) { foreach ( (array) get_option( self::HISTORY, array() ) as $item ) if ( hash_equals( (string) ( $item['file'] ?? '' ), $file ) ) return $item; return array(); }
	private static function remove_history( $file ) { self::change_history( function( &$history ) use ( $file ) { $history = array_values( array_filter( $history, function( $row ) use ( $file ) { return ! hash_equals( (string) ( $row['file'] ?? '' ), $file ); } ) ); } ); }
	private static function download_remote( $item ) { $remote = (array) ( $item['remote'] ?? array() ); $provider = $remote['provider'] ?? ''; $settings = self::settings(); if ( ! $provider ) wp_die( 'Este respaldo remoto fue creado antes de guardar su identificador. Conserva la copia remota o genera un respaldo nuevo.' ); if ( 'sftp' === $provider ) { $connection = self::sftp_connection( $settings ); $sftp = ssh2_sftp( $connection ); $stream = @fopen( 'ssh2.sftp://' . intval( $sftp ) . (string) $remote['path'], 'rb' ); if ( ! $stream ) wp_die( 'No se pudo leer el respaldo SFTP.' ); nocache_headers(); header( 'Content-Type: ' . self::content_type( $item['file'] ) ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $item['file'] ) . '"' ); fpassthru( $stream ); fclose( $stream ); return; } $token = self::token( 'google' === $provider ? 'https://oauth2.googleapis.com/token' : 'https://login.microsoftonline.com/common/oauth2/v2.0/token', $settings, $provider ); $url = 'google' === $provider ? 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $remote['id'] ) . '?alt=media' : 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode( $remote['id'] ) . '/content'; $response = wp_remote_get( $url, array( 'timeout' => 120, 'redirection' => 5, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) ); if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) wp_die( 'No se pudo descargar el respaldo remoto.' ); nocache_headers(); header( 'Content-Type: ' . self::content_type( $item['file'] ) ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $item['file'] ) . '"' ); echo wp_remote_retrieve_body( $response ); }
	private static function delete_remote( $item ) { $remote = (array) ( $item['remote'] ?? array() ); $provider = $remote['provider'] ?? ''; $settings = self::settings(); if ( ! $provider ) throw new Exception( 'Este respaldo remoto no tiene identificador para eliminarse desde WordPress.' ); if ( 'sftp' === $provider ) { $connection = self::sftp_connection( $settings ); $sftp = ssh2_sftp( $connection ); if ( ! @ssh2_sftp_unlink( $sftp, (string) $remote['path'] ) ) throw new Exception( 'No se pudo eliminar el archivo SFTP.' ); return; } $token = self::token( 'google' === $provider ? 'https://oauth2.googleapis.com/token' : 'https://login.microsoftonline.com/common/oauth2/v2.0/token', $settings, $provider ); $url = 'google' === $provider ? 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $remote['id'] ) : 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode( $remote['id'] ); $response = wp_remote_request( $url, array( 'method' => 'DELETE', 'timeout' => 60, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) ); if ( is_wp_error( $response ) || ! in_array( wp_remote_retrieve_response_code( $response ), array( 200, 204 ), true ) ) throw new Exception( 'El proveedor remoto no confirmó la eliminación.' ); }
	private static function sftp_connection( $s ) {
		if ( empty( $s['sftp_password'] ) && '' !== ( $problem = self::credentials_problem( 'sftp' ) ) ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		if ( ! function_exists( 'ssh2_connect' ) || ! function_exists( 'ssh2_fingerprint' ) || empty( $s['sftp_host'] ) || empty( $s['sftp_user'] ) || empty( $s['sftp_password'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Completa la conexión SFTP para gestionar este respaldo.' );
		$expected = strtoupper( str_replace( ':', '', trim( (string) ( $s['sftp_fingerprint'] ?? '' ) ) ) );
		if ( ! preg_match( '/^[A-F0-9]{40}$/', $expected ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Configura y verifica la huella SHA-1 de la clave SSH del servidor antes de usar SFTP.' );
		$connection = @ssh2_connect( $s['sftp_host'], (int) $s['sftp_port'] );
		if ( ! $connection ) throw new Exception( 'No se pudo conectar al servidor SFTP.' );
		$actual = @ssh2_fingerprint( $connection, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX );
		if ( ! is_string( $actual ) || ! hash_equals( $expected, strtoupper( $actual ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La huella de la clave SSH del servidor SFTP no coincide con la configurada.' );
		if ( ! @ssh2_auth_password( $connection, $s['sftp_user'], $s['sftp_password'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo autenticar en SFTP.' );
		return $connection;
	}
	private static function destination_label( $destination ) { return array( 'local' => 'Hosting / local', 'sftp' => 'SFTP', 'google' => 'Google Drive', 'onedrive' => 'OneDrive' )[ $destination ] ?? 'Local'; }
	public static function save_server_catalog() { if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' ); check_admin_referer( 'digitalisimo_backups_server' ); $input = (array) ( $_POST['digitalisimo_backup_server'] ?? array() ); $type = sanitize_key( $input['type'] ?? '' ); $name = sanitize_text_field( $input['name'] ?? '' ); if ( ! $name || ! in_array( $type, array( 'sftp', 'google', 'onedrive' ), true ) ) wp_die( 'Nombre o tipo de servidor inválido.' ); $servers = self::servers(); $servers[] = array( 'id' => 'server-' . wp_generate_uuid4(), 'type' => $type, 'name' => $name, 'verified' => false ); update_option( self::SERVERS, $servers, false ); wp_safe_redirect( self::admin_url() ); exit; }
}
