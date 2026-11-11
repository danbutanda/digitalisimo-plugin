<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-backups-job-store.php';
require_once __DIR__ . '/class-backups-jobs.php';
require_once __DIR__ . '/class-backups-backup-job.php';
// Exportación, empaquetado y verificación por tramos (T2).
foreach ( array( 'step-budget', 'zip-writer', 'sql-export', 'packer' ) as $digitalisimo_backups_part ) require_once __DIR__ . '/steps/class-' . $digitalisimo_backups_part . '.php';
// Restauración por pasos (H1–H4): manifiesto por partes, trabajo de restauración y su modo de mantenimiento.
foreach ( array( 'manifest-stream', 'manifest-index', 'restore-maintenance' ) as $digitalisimo_backups_part ) require_once __DIR__ . '/restore/class-' . $digitalisimo_backups_part . '.php';
require_once __DIR__ . '/class-backups-restore-job.php';
require_once __DIR__ . '/restore/trait-backups-restore-engine.php';
// Seguridad: cifrado, firma y validación de archivos no confiables (ver docs/BACKUPS.md).
foreach ( array( 'sodium', 'keys', 'credentials', 'manifest-auth', 'encrypted-file', 'archive-inspector', 'sql-guard', 'security' ) as $digitalisimo_backups_part ) require_once __DIR__ . '/security/class-' . $digitalisimo_backups_part . '.php';
unset( $digitalisimo_backups_part );
// Destinos remotos: contrato común, HTTP y cada proveedor (ver docs/BACKUPS.md, «Destinos»).
foreach ( array( 'http', 'destination', 'destinations', 'destination-sftp', 'sftp-client', 'destination-ftp', 'destination-webdav', 'destination-cloud-drive', 'destination-dropbox', 'destination-s3', 'destination-pcloud' ) as $digitalisimo_backups_part ) require_once __DIR__ . '/destinations/class-' . $digitalisimo_backups_part . '.php';
unset( $digitalisimo_backups_part );
require_once __DIR__ . '/destinations/trait-backups-destinations.php';
require_once __DIR__ . '/destinations/trait-backups-remote-retention.php';

require_once __DIR__ . '/class-backups-layout.php';
// Exclusiones por defecto (B8): otros plugins de respaldo, la caché de páginas y los registros.
require_once __DIR__ . '/class-backups-exclusions.php';
require_once __DIR__ . '/class-backups-notify.php';
// Respaldo antes de actualizar el núcleo, los plugins y los temas (I1).
require_once __DIR__ . '/class-backups-pre-update.php';
require_once __DIR__ . '/class-backups-maintenance.php';
require_once __DIR__ . '/class-backups-config-diff.php';
require_once __DIR__ . '/class-backups-uploads.php';
require_once __DIR__ . '/trait-backups-upload-service.php';
// Restaurar desde un destino remoto y sincronizar sus respaldos (B1b, B2): trabajo `fetch` y casos de uso.
require_once __DIR__ . '/class-backups-fetch-job.php';
require_once __DIR__ . '/trait-backups-remote-service.php';
// La configuración de Backups de este sitio sobrevive a la restauración del respaldo de otro (H23).
require_once __DIR__ . '/trait-backups-keep-config.php';
// Restaurar en otro dominio, otra ruta del servidor u otro prefijo de tablas (ver «Restaurar en otro dominio»).
require_once __DIR__ . '/class-backups-relocate.php';
require_once __DIR__ . '/class-backups-migrate.php';
require_once __DIR__ . '/class-backups-site-extract.php';
require_once __DIR__ . '/trait-backups-service.php';
// Importar los respaldos de Backuply (I9): conversión por pasos a un ZIP de Backups sin firma.
foreach ( array( 'class-backuply-sql', 'class-backuply-import', 'trait-backups-backuply-service' ) as $digitalisimo_backups_part ) require_once __DIR__ . '/import/' . $digitalisimo_backups_part . '.php';
unset( $digitalisimo_backups_part );
/** Respaldo manual completo: base de datos, archivos y copia opcional remota. */
final class Digitalisimo_Backups {
	use Digitalisimo_Backups_Service; // Casos de uso que comparten la pantalla, REST y WP-CLI.
	use Digitalisimo_Backups_Restore_Engine; // Restauración por pasos (Digitalisimo_Backups_Restore_Job).
	use Digitalisimo_Backups_Destinations_Screen; // Destinos remotos: contrato, conexiones, «Servidores», «Probar conexión» y OAuth.
	use Digitalisimo_Backups_Upload_Service; // Subida de un respaldo por fragmentos (pantalla y REST).
	use Digitalisimo_Backups_Remote_Retention; // Retención en el destino remoto (B6) y «sólo en el remoto» (I4).
	use Digitalisimo_Backups_Remote_Service; // Respaldos en los destinos: listar, sincronizar y traer (B1b, B2, I13).
	use Digitalisimo_Backups_Keep_Config; // La configuración propia de Backups se conserva al restaurar (H23).
	/** @internal Foto de la configuración propia que anota el próximo diario de la restauración (H23); null: ninguna. */
	private static $config_kept = null;
	use Digitalisimo_Backups_Backuply_Service; // Importar los respaldos de Backuply (pantalla, REST y WP-CLI).
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
	/** Eliminar a mano un archivo del almacén sin fila en el historial (una subida o uno copiado por SSH). */
	const ACTION_DELETE_LOOSE = 'digitalisimo_backups_delete_loose';
	const ACTION_CREATE_AUTOMATION = 'digitalisimo_backups_create_automation';
	const ACTION_UPDATE_AUTOMATION = 'digitalisimo_backups_update_automation';
	const ACTION_DELETE_AUTOMATION = 'digitalisimo_backups_delete_automation';
	const ACTION_SAVE_CONNECTION = 'digitalisimo_backups_save_connection';
	const ACTION_DELETE_CONNECTION = 'digitalisimo_backups_delete_connection';
	const ACTION_TEST_DESTINATION = 'digitalisimo_backups_test_destination';
	const ACTION_FETCH = 'digitalisimo_backups_fetch';
	/** Respaldos en los destinos remotos (B2): sincronizar y traer uno de la lista, también de otro sitio. */
	const ACTION_SYNC = 'digitalisimo_backups_sync';
	const ACTION_REMOTE_FETCH = 'digitalisimo_backups_remote_fetch';
	const REMOTE_INDEX = 'digitalisimo_backups_remote_index';
	/** Respaldos traídos «sin registro»: cuándo y con qué SHA-256, para su retención (C5). */
	const FETCHED = 'digitalisimo_backups_fetched';
	/** «Importar» un respaldo de Backuply desde la tarjeta «Respaldos de Backuply». */
	const ACTION_BACKUPLY_IMPORT = 'digitalisimo_backups_backuply_import';
	/** «Eliminar» un `.tar.gz` de Backuply del almacén privado. */
	const ACTION_BACKUPLY_DELETE = 'digitalisimo_backups_backuply_delete';
	/** Estado de una subida interrumpida que espera retirarse del destino, en el área de su trabajo. */
	const ABANDON = 'abandon.json';
	/** Segundos de espera de cada llamada al retirarla (fuera del cerrojo del registro de trabajos). */
	const ABANDON_TIMEOUT = 20;
	public static function init() { self::activate_default_policies(); add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save' ) ); add_action( 'admin_post_' . self::ACTION_RUN, array( __CLASS__, 'run' ) ); add_action( 'wp_ajax_' . self::ACTION_MANUAL_START, array( __CLASS__, 'manual_start' ) ); add_action( 'wp_ajax_' . self::ACTION_MANUAL_WORK, array( __CLASS__, 'manual_work' ) ); add_action( 'wp_ajax_' . self::ACTION_MANUAL_STATUS, array( __CLASS__, 'manual_status' ) ); add_action( 'admin_post_' . self::ACTION_OAUTH, array( __CLASS__, 'oauth_callback' ) ); add_action( 'admin_post_' . self::ACTION_LOCK, array( __CLASS__, 'toggle_lock' ) ); add_action( 'admin_post_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'download' ) ); add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'delete_backup' ) ); add_action( 'admin_post_' . self::ACTION_SAVE_POLICIES, array( __CLASS__, 'save_policies' ) ); add_action( 'admin_post_' . self::ACTION_RUN_POLICY, array( __CLASS__, 'run_policy_request' ) ); add_action( 'admin_post_' . self::ACTION_CREATE_AUTOMATION, array( __CLASS__, 'create_automation' ) ); add_action( 'admin_post_' . self::ACTION_UPDATE_AUTOMATION, array( __CLASS__, 'update_automation' ) ); add_action( 'admin_post_' . self::ACTION_DELETE_AUTOMATION, array( __CLASS__, 'delete_automation' ) ); add_action( 'admin_post_' . self::ACTION_RESTORE, array( __CLASS__, 'restore_request' ) ); add_action( 'admin_post_' . self::ACTION_SAVE_CONNECTION, array( __CLASS__, 'save_connection' ) ); add_action( 'admin_post_' . self::ACTION_DELETE_CONNECTION, array( __CLASS__, 'delete_connection' ) ); add_action( 'admin_post_' . self::ACTION_TEST_DESTINATION, array( __CLASS__, 'test_destination' ) ); add_action( 'wp_ajax_' . self::ACTION_TEST_DESTINATION, array( __CLASS__, 'ajax_test_destination' ) ); add_action( 'admin_post_' . self::ACTION_FETCH, array( __CLASS__, 'fetch_request' ) ); add_action( 'admin_post_' . self::ACTION_DELETE_LOOSE, array( __CLASS__, 'delete_loose' ) ); add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); add_action( 'digitalisimo_backups_full', array( __CLASS__, 'scheduled_backup' ), 10, 1 ); add_action( 'digitalisimo_backups_db', array( __CLASS__, 'scheduled_backup' ), 10, 1 ); add_action( 'digitalisimo_backups_policy', array( __CLASS__, 'realign_policy' ), 5, 2 ); add_action( 'digitalisimo_backups_policy', array( __CLASS__, 'scheduled_policy' ), 10, 2 ); add_action( 'update_option_timezone_string', array( __CLASS__, 'timezone_changed' ) ); add_action( 'init', array( __CLASS__, 'ensure_policy_schedules' ) ); add_action( 'update_option_gmt_offset', array( __CLASS__, 'timezone_changed' ) ); Digitalisimo_Backups_Jobs::init(); Digitalisimo_Backups_Security::init(); Digitalisimo_Backups_Migrate::init(); add_action( 'admin_post_' . self::ACTION_BACKUPLY_IMPORT, array( __CLASS__, 'backuply_import_request' ) ); add_action( 'admin_post_' . self::ACTION_BACKUPLY_DELETE, array( __CLASS__, 'backuply_delete_request' ) ); Digitalisimo_Backups_Notify::init(); Digitalisimo_Backups_Pre_Update::init(); Digitalisimo_Backups_Uploads::init(); add_action( 'admin_init', array( __CLASS__, 'credentials_on_load' ) ); add_action( 'init', array( __CLASS__, 'credentials_on_cron' ) ); }
	private static function defaults() { return array( 'destination' => 'local', 'sftp_name' => 'SFTP', 'google_name' => 'Google Drive', 'onedrive_name' => 'OneDrive', 'sftp_host' => '', 'sftp_port' => '22', 'sftp_user' => '', 'sftp_path' => '/', 'sftp_password' => '', 'sftp_fingerprint' => '', 'google_client_id' => '', 'google_client_secret' => '', 'google_refresh_token' => '', 'google_folder_id' => '', 'onedrive_client_id' => '', 'onedrive_client_secret' => '', 'onedrive_refresh_token' => '', 'onedrive_folder' => 'Digitalisimo Backups', 'retention' => '5', 'full_schedule' => 'disabled', 'database_schedule' => 'disabled' ) + Digitalisimo_Backups_Destinations::defaults(); }
	/** Los ZIP no deben quedar bajo una ruta servida por Apache, Nginx o el CDN. */
	/** Carpeta del almacén ya comprobada en esta petición. */
	private static $storage_directory = null;
	/** @internal Olvida la carpeta comprobada (pruebas que alternan dos instalaciones en un proceso). */
	public static function forget_storage_directory() { self::$storage_directory = null; }
	private static function local_directory() {
		$directory = self::$storage_directory;
		if ( null !== $directory ) return $directory;
		$wp_root = realpath( ABSPATH );
		if ( false === $wp_root ) throw new Exception( 'No se pudo resolver la raíz de WordPress para ubicar los respaldos.' );
		$document_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( (string) $_SERVER['DOCUMENT_ROOT'] ) : false;
		$explicit = defined( 'DIGITALISIMO_BACKUPS_STORAGE_DIR' );
		$saved = is_multisite() ? get_site_option( self::STORAGE, array() ) : get_option( self::STORAGE, array() );
		if ( $explicit ) {
			$path = (string) DIGITALISIMO_BACKUPS_STORAGE_DIR;
		} elseif ( is_array( $saved ) && wp_normalize_path( $wp_root ) === ( $saved['wordpress_root'] ?? '' ) && ! empty( $saved['directory'] ) && ( @is_dir( (string) $saved['directory'] ) || false === $document_root ) ) {
			$path = (string) $saved['directory'];
		} else {
			// Sin registro, o con uno cuya carpeta no existe en este servidor: el de otro servidor que trajo una restauración
			// interrumpida antes de reponer el de aquí (H10 del ensayo E2). Se usa la carpeta que corresponde a esta
			// instalación, que es donde están su historial, su copia previa y el diario que lo repone. Sin DOCUMENT_ROOT
			// (WP-CLI, cron del sistema) no hay otra que calcular: el registro se usa como antes y su carpeta se intenta crear.
			if ( false === $document_root ) throw new Exception( 'No se conoce la raíz pública del servidor. Define DIGITALISIMO_BACKUPS_STORAGE_DIR en wp-config.php con una carpeta privada fuera del sitio.' );
			$path = self::default_storage_path( $document_root, $wp_root );
		}
		// Si la carpeta que corresponde a esta instalación tiene el diario de una restauración suya interrumpida, ése es el
		// almacén, aunque el registro de la base (el que trajo el SQL) apunte a otra carpeta que exista (revisión C1: un
		// clon de la misma cuenta).
		if ( ! $explicit && false !== $document_root && isset( $saved['directory'] ) && $path === (string) $saved['directory'] ) {
			$own = self::default_storage_path( $document_root, $wp_root, false );
			if ( '' !== $own && $own !== $path && self::journal_is_ours( $own ) ) $path = $own;
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
		self::$storage_directory = $real;
		return $real;
	}
	/**
	 * El registro del almacén privado de ESTE servidor (`digitalisimo_backups_private_storage`, en una red en sitemeta), o
	 * null. Una restauración lo conserva como el historial (H10 del ensayo E2): el SQL de un respaldo de otro servidor trae
	 * el suyo, con la carpeta de allá, y la reubicación sólo cambia su `wordpress_root` por el de aquí; sin reponerlo,
	 * Backups buscaba aquí la carpeta del origen y quedaba inservible, sin historial ni copia previa visibles.
	 */
	private static function storage_record() {
		$value = is_multisite() ? get_site_option( self::STORAGE, null ) : get_option( self::STORAGE, null );
		return is_array( $value ) ? $value : null;
	}
	/** Repone el registro del almacén de este servidor ($record de storage_record(); null lo retira). */
	private static function keep_storage_record( $record ) {
		if ( is_array( $record ) ) {
			if ( is_multisite() ) update_site_option( self::STORAGE, $record );
			else update_option( self::STORAGE, $record, false );
			return;
		}
		if ( null === self::storage_record() ) return;
		if ( is_multisite() ) delete_site_option( self::STORAGE );
		else delete_option( self::STORAGE );
	}
	/**
	 * La carpeta privada que corresponde a esta instalación: `.digitalisimo-backups-<hash de ABSPATH>` junto a la raíz
	 * pública. Si la carpeta de al lado también es pública (un dominio adicional con la raíz dentro de la de otro sitio:
	 * `public_html/<dominio>`), sube hasta una que no lo sea (revisión C6). Con $strict, si no la hay lanza el aviso para
	 * definir DIGITALISIMO_BACKUPS_STORAGE_DIR; sin él devuelve ''.
	 */
	private static function default_storage_path( $document_root, $wp_root, $strict = true ) {
		$parent = dirname( (string) $document_root );
		for ( $i = 0; $i < 6 && self::looks_public( $parent ); $i++ ) {
			$up = dirname( $parent );
			if ( $up === $parent ) break;
			$parent = $up;
		}
		if ( self::looks_public( $parent ) ) {
			if ( ! $strict ) return '';
			throw new Exception( 'La carpeta junto a la raíz pública de este sitio también es pública (por ejemplo, un dominio adicional dentro de public_html): ahí no se guardan respaldos. Define DIGITALISIMO_BACKUPS_STORAGE_DIR en wp-config.php con una carpeta privada fuera de toda raíz pública.' );
		}
		return rtrim( $parent, '/\\' ) . '/.digitalisimo-backups-' . substr( hash( 'sha256', wp_normalize_path( $wp_root ) ), 0, 16 );
	}
	/**
	 * Una carpeta que sirve el servidor web: la raíz de otro sitio (su índice o un WordPress) o un nombre de raíz pública.
	 * No cuentan `.htaccess` ni `wp-config.php`: WordPress admite `wp-config.php` una carpeta por encima de su raíz.
	 */
	private static function looks_public( $dir ) {
		if ( in_array( strtolower( basename( (string) $dir ) ), array( 'public_html', 'htdocs', 'httpdocs' ), true ) ) return true;
		foreach ( array( 'index.php', 'index.html', 'index.htm', 'wp-load.php' ) as $name ) if ( @file_exists( rtrim( (string) $dir, '/\\' ) . '/' . $name ) ) return true;
		return false;
	}
	/** Si la carpeta tiene el diario de una restauración interrumpida de esta instalación. */
	private static function journal_is_ours( $dir ) {
		$path = rtrim( (string) $dir, '/' ) . '/' . self::RESTORE_JOURNAL;
		if ( ! @is_file( $path ) || is_link( $path ) ) return false;
		$data = json_decode( (string) @file_get_contents( $path ), true );
		return is_array( $data ) && ( $data['wordpress_root'] ?? null ) === self::journal_owner();
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
		self::private_file( $path );
		if ( ! @flock( $handle, LOCK_EX | LOCK_NB ) ) {
			fclose( $handle );
			return null;
		}
		// Con el bloqueo tomado, lo que quede en .staging/ (un ZIP en claro o descifrado) y los *.part son de una
		// operación interrumpida: se borran en cualquier operación, no sólo en la siguiente que cifre.
		Digitalisimo_Backups_Security::clean_staging( self::local_directory() );
		self::private_storage_files( self::local_directory() );
		self::sweep_stale_temporaries( self::local_directory() );
		self::recover_interrupted_restore();
		return $handle;
	}
	/**
	 * Antes del SQL de una restauración: el historial (con la copia previa), el registro del almacén privado de este
	 * servidor, la seguridad y la parte pública del llavero
	 * (huellas y parámetros de cada frase, nunca una clave ni su envoltura). Si el proceso muere mientras corre el SQL,
	 * la siguiente operación lo aplica: la protección no queda más débil, el cifrado falla cerrado hasta volver a
	 * escribir la frase y la frase vuelve a abrir la misma clave. Sin diario no se restaura.
	 */
	private static function write_restore_journal( array $origin = array(), $kept = null ) {
		$path = trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL;
		// Las claves de las credenciales del servidor de origen van cifradas con las de este (nunca en claro): si el proceso
		// muere entre el SQL y su nuevo cifrado, la operación siguiente lo termina.
		$json = wp_json_encode( array( 'version' => 1, 'started' => time(), 'restore_id' => bin2hex( random_bytes( 16 ) ), 'wordpress_root' => self::journal_owner(), 'history' => (array) get_option( self::HISTORY, array() ), 'fetched' => self::fetched_records(), 'config_kept' => self::$config_kept, 'storage' => array( 'record' => self::storage_record() ), 'credentials' => Digitalisimo_Backups_Credentials::journal( $origin ), 'notify' => Digitalisimo_Backups_Notify::snapshot() ) + ( is_array( $kept ) ? array( 'extract_kept' => Digitalisimo_Backups_Site_Extract::journal_snapshot( $kept ) ) : array() ) + Digitalisimo_Backups_Keys::public_snapshot() );
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
			// La retención de lo traído de un destino (C5), como el historial (un diario anterior no la lleva: no se toca).
			if ( is_array( $data['fetched'] ?? null ) ) self::fetched_save( $data['fetched'] );
			// El registro del almacén de este servidor (el diario de una versión anterior no lo lleva: entonces no se toca).
			if ( is_array( $data['storage'] ?? null ) && array_key_exists( 'record', $data['storage'] ) ) self::keep_storage_record( $data['storage']['record'] );
			Digitalisimo_Backups_Keys::recover_public( $data );
			// Las credenciales que trajo el SQL: las del servidor de origen se cifran con las claves de éste y las que
			// venían en claro se cifran.
			$origin = Digitalisimo_Backups_Credentials::from_journal( $data['credentials'] ?? null );
			self::restore_credentials( $origin );
			Digitalisimo_Backups_Credentials::forget( $origin );
			// La configuración propia de este sitio (H23), después de cifrar las credenciales que trajo el SQL: vuelve la de aquí.
			if ( is_array( $data['config_kept'] ?? null ) ) self::keep_config_reapply( $data['config_kept'], self::keep_config_mark( $data ) );
			try { self::restore_notice_after( is_array( $data['config_kept'] ?? null ) ); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
		}
		@unlink( $path );
		// Una restauración por pasos sigue: el trabajo la reanuda, su mantenimiento no se toca y avisará al terminar.
		try {
			if ( Digitalisimo_Backups_Jobs::restore_active() ) return;
		} catch ( Throwable $e ) {
			return;
		}
		// El proceso murió con el sitio en mantenimiento: se retira, y se avisa a quien recibía los avisos antes del SQL.
		Digitalisimo_Backups_Maintenance::clear_stale();
		if ( is_array( $data ) && is_array( $data['notify'] ?? null ) ) {
			$safety = '';
			foreach ( (array) ( $data['history'] ?? array() ) as $row ) if ( 'pre-restore' === ( $row['policy'] ?? '' ) ) { $safety = (string) ( $row['file'] ?? '' ); break; }
			$started = (int) ( $data['started'] ?? 0 );
			$message = 'La restauración que empezó ' . ( $started ? 'el ' . ( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', $started ) : gmdate( 'Y-m-d H:i', $started ) . ' UTC' ) . ' ' : '' ) . 'se interrumpió antes de terminar (el proceso se detuvo: tiempo agotado, memoria o una recarga del servidor web). Se recuperaron el historial, la seguridad y las credenciales, pero la base de datos y los archivos pueden haber quedado a medias' . ( '' !== $safety ? ': restaura la copia previa ' . $safety . '.' : '.' );
			try { Digitalisimo_Backups_Notify::restore( $data['notify'], false, array( 'started' => true, 'message' => $message ) ); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
		}
	}
	public static function release_operation_lock( $handle ) {
		if ( is_resource( $handle ) ) {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}
	/** Registra la carpeta del almacén de esta instalación (sin la constante), como al abrir la pantalla. */
	private static function remember_storage() {
		if ( defined( 'DIGITALISIMO_BACKUPS_STORAGE_DIR' ) || null === self::$storage_directory ) return;
		$root = realpath( ABSPATH );
		$record = array( 'wordpress_root' => wp_normalize_path( false === $root ? ABSPATH : $root ), 'directory' => self::$storage_directory );
		if ( is_multisite() ) update_site_option( self::STORAGE, $record );
		else update_option( self::STORAGE, $record, false );
	}
	/** @internal Requisitos de un respaldo completo: la copia previa de una migración y el paquete del origen lo son. */
	public static function migration_ready() { self::ensure_ready(); }
	/**
	 * @internal Encola la restauración del paquete ya abierto de una migración con el motor de «Restaurar» (por pasos):
	 * direcciones de este sitio, copia previa bloqueada y marcada con $tag (la que «Deshacer la migración» restaura) y
	 * tablas exactas. $except es el trabajo de la migración, que espera el resultado: no cuenta como otro trabajo a medias.
	 * Lanza Exception si no se puede (otro trabajo en curso: «… en curso …»; o el paquete no sirve aquí).
	 */
	public static function migration_enqueue( array $report, array $tag, $except ) {
		$safety_file = self::backup_file_name( 'safety-before-restore-' );
		$safety_spec = Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'restore', 'file' => $safety_file, 'inline' => true ) );
		return self::restore_enqueue( $report, array( 'addresses' => 'current', 'via' => 'migration', 'safety_spec' => $safety_spec, 'safety_extra' => array( 'migration' => $tag ), 'exact_tables' => true, 'except' => (string) $except ) );
	}
	/** @internal La vuelta a la copia previa de una restauración que falló, si se encoló; si no, la restauración misma. */
	public static function migration_followed( array $job ) { return self::restore_followed( $job ); }
	/** @internal Cambia la marca de migración de la copia previa $file en el historial. */
	public static function migration_mark( $file, array $tag ) { self::history_update( (string) $file, array( 'migration' => $tag ) ); }
	/** @internal URL de la pantalla Backups (en el contexto de red si corresponde) con su pestaña. */
	public static function admin_page_url( $tab = '' ) { $url = self::admin_url(); return '' !== $tab ? add_query_arg( 'tab', $tab, $url ) : $url; }
	/** Traslada los respaldos anteriores; nunca deja un ZIP parcialmente copiado como válido. */
	private static function migrate_legacy_storage( $private ) {
		$legacy = WP_PLUGIN_DIR . '/digitalisimo-backups-storage';
		if ( ! file_exists( $legacy ) ) return;
		if ( is_link( $legacy ) || ! is_dir( $legacy ) ) throw new Exception( 'La carpeta de respaldos anterior no es una carpeta segura.' );
		$lock = @fopen( $private . '/.migration.lock', 'c' );
		self::private_file( $private . '/.migration.lock' );
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
					self::private_file( $temp );
					if ( false === $copied || ! $flushed || $copied !== @filesize( $source ) || false === $temp_hash || ! hash_equals( $source_hash, $temp_hash ) || ! @rename( $temp, $target ) ) { @unlink( $temp ); throw new Exception( 'La copia del respaldo anterior ' . $name . ' no superó la verificación.' ); }
				}
				self::private_file( $target );
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
			return Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => (string) $policy['id'], 'destination' => (string) ( $policy['destination'] ?? 'local' ), 'retain' => (int) ( $policy['retain'] ?? 4 ), 'retention' => 'policy', 'remote_only' => ! empty( $policy['remote_only'] ), 'via' => $via, 'site_id' => $site_id ) ) );
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
		$secrets = Digitalisimo_Backups_Credentials::read( self::credential_settings( $saved ) );
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
		return Digitalisimo_Backups_Credentials::status( array_keys( self::plain_secrets( $saved ) ), self::credential_settings( $saved ) );
	}
	/** Mensaje de un destino remoto cuyas credenciales no se abren ('' si no es el caso). */
	private static function credentials_problem( $destination ) {
		if ( ! self::credentials_here() ) return '';
		$saved = self::saved_settings();
		return Digitalisimo_Backups_Credentials::destination_problem( (string) $destination, array_keys( self::plain_secrets( $saved ) ), self::credential_settings( $saved ) );
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
			Digitalisimo_Backups_Credentials::reseal( $origin, self::credential_settings() );
			self::migrate_credentials();
		} catch ( Throwable $e ) {
			error_log( 'DIGITALÍSIMO Backups: credenciales tras restaurar: ' . $e->getMessage() );
		}
	}
	/** Catálogo persistente de conexiones; conserva la configuración previa como primer servidor. */
	/**
	 * I7: el catálogo «Servidores creados» de versiones anteriores (`digitalisimo_backups_servers`, con «Editar» y
	 * «Eliminar» deshabilitados) se retira. Su fila `legacy-ftp` sólo copiaba la conexión SFTP principal, que sigue en los
	 * ajustes; cualquier otra entrada con un tipo de destino pasa a ser una conexión con nombre del mismo tipo (sin datos:
	 * queda «incompleta» hasta completarla), así que nada se pierde. Idempotente.
	 */
	public static function migrate_server_catalog() {
		if ( false === get_option( self::SERVERS, false ) ) return 0;
		// Con el cerrojo del almacén (revisión P5): la pantalla y GET /destinations a la vez no migran dos veces.
		return Digitalisimo_Backups_Job_Store::with_lock( function() {
			if ( function_exists( 'wp_cache_delete' ) ) wp_cache_delete( self::SERVERS, 'options' );
			$servers = get_option( self::SERVERS, false );
			if ( false === $servers ) return 0;
			$connections = get_option( Digitalisimo_Backups_Destinations::CONNECTIONS, array() );
			$connections = is_array( $connections ) ? $connections : array();
			$moved = 0;
			$left = array();
			foreach ( is_array( $servers ) ? $servers : array() as $server ) {
				if ( ! is_array( $server ) || 'legacy-ftp' === ( $server['id'] ?? '' ) || ! Digitalisimo_Backups_Destinations::is_type( $server['type'] ?? '' ) ) continue;
				// Sin nombre, el del tipo; con el tope de conexiones lleno, la entrada se queda en el catálogo (no se pierde).
				$name = sanitize_text_field( (string) ( $server['name'] ?? '' ) );
				if ( '' === $name ) $name = Digitalisimo_Backups_Destinations::label( (string) $server['type'] );
				if ( count( $connections ) >= Digitalisimo_Backups_Destinations::MAX_CONNECTIONS ) { $left[] = $server; continue; }
				do { $id = $server['type'] . '-' . bin2hex( random_bytes( 4 ) ); } while ( isset( $connections[ $id ] ) );
				$connections[ $id ] = array( 'type' => (string) $server['type'], 'name' => $name, 'settings' => array() );
				$moved++;
			}
			if ( $moved ) Digitalisimo_Backups_Destinations::save_connections( $connections );
			if ( $left ) update_option( self::SERVERS, $left, false ); else delete_option( self::SERVERS );
			return $moved;
		} );
	}
	public static function cron_schedules( $schedules ) { $schedules['digitalisimo_every_6_hours'] = array( 'interval' => 6 * HOUR_IN_SECONDS, 'display' => 'Cada 6 horas' ); $schedules['digitalisimo_every_12_hours'] = array( 'interval' => 12 * HOUR_IN_SECONDS, 'display' => 'Cada 12 horas' ); $schedules['monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Mensual' ); return $schedules; }
	private static function schedule_options() { return array( 'disabled' => 'Desactivado', 'hourly' => 'Cada hora', 'digitalisimo_every_6_hours' => 'Cada 6 horas', 'digitalisimo_every_12_hours' => 'Cada 12 horas', 'daily' => 'Diario', 'weekly' => 'Semanal', 'monthly' => 'Mensual' ); }
	private static function sync_schedules( $settings ) { foreach ( array( 'full' => 'full_schedule', 'db' => 'database_schedule' ) as $type => $key ) { $hook = 'digitalisimo_backups_' . $type; wp_clear_scheduled_hook( $hook ); if ( 'full' === $type && 'disabled' !== $settings[ $key ] ) wp_schedule_event( time() + MINUTE_IN_SECONDS, $settings[ $key ], $hook, array( get_current_blog_id() ) ); } }
	/**
	 * Programa cada automatización. Se conserva lo ya programado si su frecuencia y su hora no cambiaron (guardar una
	 * automatización no adelanta las demás); lo nuevo o cambiado se programa a su hora fija o, sin ella, en un minuto.
	 */
	private static function sync_policy_schedules() {
		foreach ( self::policies() as $policy ) {
			$args = array( $policy['id'], get_current_blog_id() );
			$active = ! empty( $policy['enabled'] ) && 'disabled' !== ( $policy['schedule'] ?? 'disabled' );
			$time = self::fixed_time( $policy );
			$event = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( 'digitalisimo_backups_policy', $args ) : false;
			if ( $active && $event && ( $event->schedule ?? '' ) === $policy['schedule'] && ( '' === $time || self::local_clock( (int) $event->timestamp ) === $time ) ) continue;
			wp_clear_scheduled_hook( 'digitalisimo_backups_policy', $args );
			if ( $active ) wp_schedule_event( '' !== $time ? self::next_fixed_run( $time, time() + MINUTE_IN_SECONDS ) : time() + MINUTE_IN_SECONDS, $policy['schedule'], 'digitalisimo_backups_policy', $args );
		}
	}
	/** Hora fija válida (HH:MM) de una automatización diaria, semanal o mensual; '' si no tiene o no aplica. */
	private static function fixed_time( $policy ) {
		$time = trim( (string) ( ( (array) $policy )['time'] ?? '' ) );
		return in_array( (string) ( $policy['schedule'] ?? '' ), array( 'daily', 'weekly', 'monthly' ), true ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '';
	}
	private static function site_timezone() {
		if ( function_exists( 'wp_timezone' ) ) return wp_timezone();
		return new DateTimeZone( 'UTC' );
	}
	/** HH:MM de un instante en la hora del sitio. */
	private static function local_clock( $timestamp ) {
		return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( self::site_timezone() )->format( 'H:i' );
	}
	/**
	 * La primera vez, desde $from (incluido), que el reloj del sitio marca $time (HH:MM). Calculado en la zona horaria del
	 * sitio, así que el cambio de horario de verano no mueve la hora local; una hora que ese día no existe (el salto de
	 * primavera) pasa a la siguiente que sí.
	 */
	public static function next_fixed_run( $time, $from ) {
		list( $hour, $minute ) = array_map( 'intval', explode( ':', (string) $time ) );
		$day = ( new DateTimeImmutable( '@' . (int) $from ) )->setTimezone( self::site_timezone() );
		for ( $i = 0; $i < 3; $i++ ) {
			$candidate = $day->setTime( $hour, $minute, 0 );
			if ( $candidate->getTimestamp() >= (int) $from ) return $candidate->getTimestamp();
			$day = $day->setTime( 12, 0, 0 )->modify( '+1 day' );
		}
		return (int) $from + DAY_IN_SECONDS;
	}
	/**
	 * Antes de cada ejecución de una automatización con hora fija: WP-Cron ya programó la siguiente sumando el intervalo
	 * en UTC (también si corrió con retraso: la deja en la fase de su hora original). Si esa siguiente ya cae a la hora
	 * local elegida, se respeta; si el horario de verano o un cambio de zona la desplazaron, va a la HH:MM más cercana
	 * (nunca salta un día ni cambia el día de la semanal o la mensual). $now sólo en pruebas.
	 */
	public static function realign_policy( $policy_id, $site_id, $now = null ) {
		$policy = self::policy( (string) $policy_id );
		$time = self::fixed_time( $policy );
		if ( '' === $time || empty( $policy['enabled'] ) ) return;
		$now = null === $now ? time() : (int) $now;
		$intervals = array( 'daily' => DAY_IN_SECONDS, 'weekly' => 7 * DAY_IN_SECONDS, 'monthly' => 30 * DAY_IN_SECONDS );
		$args = array( (string) $policy_id, (int) $site_id );
		$next = wp_next_scheduled( 'digitalisimo_backups_policy', $args );
		if ( $next && self::local_clock( (int) $next ) === $time ) return;
		$want = $next ? self::next_fixed_run( $time, (int) $next - 12 * HOUR_IN_SECONDS ) : self::next_fixed_run( $time, $now + $intervals[ $policy['schedule'] ] - 12 * HOUR_IN_SECONDS );
		if ( $next && abs( (int) $next - $want ) < MINUTE_IN_SECONDS ) return;
		if ( $next ) wp_unschedule_event( (int) $next, 'digitalisimo_backups_policy', $args );
		wp_schedule_event( $want, $policy['schedule'], 'digitalisimo_backups_policy', $args );
	}
	/**
	 * Una automatización activa sin evento de WP-Cron se programa: la «Completo mensual» que viene activa por defecto en
	 * un sitio donde nunca se guardó la pantalla, o un evento que se perdió (la opción `cron` que trajo el SQL de una
	 * restauración). Va a su hora fija o, sin ella, a las 03:00 del sitio (las de cada hora, 6 o 12 horas, en un minuto).
	 * No añade consultas: las automatizaciones ya se leen en cada carga y `cron` se carga con las opciones.
	 */
	public static function ensure_policy_schedules() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) return;
		if ( ( function_exists( 'wp_installing' ) && wp_installing() ) || ( is_multisite() && ! is_main_site() ) ) return;
		// Con varias redes, el respaldo completo sólo se hace desde la principal (en otra fallaría cada vez y avisaría).
		if ( is_multisite() && function_exists( 'is_main_network' ) && ! is_main_network() ) return;
		$storage = null;
		foreach ( self::policies() as $policy ) {
			if ( empty( $policy['enabled'] ) || empty( $policy['id'] ) || 'full' !== ( $policy['type'] ?? '' ) || ! array_key_exists( (string) ( $policy['schedule'] ?? '' ), self::schedule_options() ) || 'disabled' === $policy['schedule'] ) continue;
			$args = array( (string) $policy['id'], get_current_blog_id() );
			if ( wp_next_scheduled( 'digitalisimo_backups_policy', $args ) ) continue;
			// Sin un almacén privado resoluble (WP-CLI o cron sin DOCUMENT_ROOT antes de abrir la pantalla) el respaldo
			// fallaría: no se programa hasta que lo haya (se comprueba sólo cuando falta un evento).
			if ( null === $storage ) { try { self::local_directory(); $storage = true; } catch ( Exception $e ) { $storage = false; } }
			if ( ! $storage ) return;
			$time = self::fixed_time( $policy );
			if ( '' === $time && in_array( $policy['schedule'], array( 'daily', 'weekly', 'monthly' ), true ) ) $time = '03:00';
			wp_schedule_event( '' !== $time ? self::next_fixed_run( $time, time() + MINUTE_IN_SECONDS ) : time() + MINUTE_IN_SECONDS, $policy['schedule'], 'digitalisimo_backups_policy', $args );
		}
	}
	/** Cambió la zona horaria del sitio: las automatizaciones con hora fija vuelven a su hora local (las demás no se tocan). */
	public static function timezone_changed() {
		if ( is_multisite() && ! is_main_site() ) return;
		self::sync_policy_schedules();
	}
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
	/**
	 * Archivo del almacén sólo para el usuario de PHP (0600): respaldos (llevan wp-config.php y la base de datos),
	 * temporales, descargas de verificación y cerrojos. La carpeta ya es 0700; esto no depende de ella.
	 */
	public static function private_file( $path ) {
		if ( DIRECTORY_SEPARATOR !== '/' || ! is_string( $path ) || is_link( $path ) || ! is_file( $path ) ) return;
		clearstatcache( true, $path );
		if ( 0600 !== ( (int) @fileperms( $path ) & 0777 ) ) @chmod( $path, 0600 );
	}
	/**
	 * Todo archivo de la raíz del almacén a 0600: los respaldos de una versión anterior a 1.0.63 (que los dejaba 0644),
	 * los subidos y los copiados a mano (H17 del ensayo E2: H5 sólo corregía lo que cada operación volvía a escribir).
	 * Lo hacen cada operación, con el bloqueo tomado, y la pantalla «Restaurar», que lista el almacén; sólo un chmod por
	 * archivo que lo necesite, y nunca sigue un enlace.
	 */
	/** Horas tras las que un temporal de descarga o de «Traer al almacén» que nadie retiró se borra (revisión C5). */
	const STALE_TEMPORARY_HOURS = 6;
	/**
	 * Retira de la raíz del almacén los temporales `.download-*` (una descarga remota que el navegador cortó o un proceso
	 * que murió) y `<respaldo>.part-*` (un «Traer al almacén» interrumpido) con más de STALE_TEMPORARY_HOURS horas: uno
	 * más nuevo puede estar en uso por otra petición.
	 */
	private static function sweep_stale_temporaries( $dir ) {
		$limit = time() - self::STALE_TEMPORARY_HOURS * 3600;
		foreach ( array_merge( glob( rtrim( $dir, '/' ) . '/.download-*' ) ?: array(), glob( rtrim( $dir, '/' ) . '/*.part-*' ) ?: array() ) as $path ) {
			if ( is_link( $path ) || ! is_file( $path ) ) continue;
			clearstatcache( true, $path );
			if ( (int) @filemtime( $path ) < $limit ) @unlink( $path );
		}
	}
	private static function private_storage_files( $dir ) {
		if ( DIRECTORY_SEPARATOR !== '/' ) return;
		$names = @scandir( $dir );
		if ( ! is_array( $names ) ) return;
		foreach ( $names as $name ) if ( '.' !== $name && '..' !== $name ) self::private_file( rtrim( $dir, '/' ) . '/' . $name );
	}
	public static function redirect_result( $ok, $message ) { self::redirect( $ok, $message ); }
	public static function destination_name( $destination ) { return self::destination_label( $destination ); }
	/**
	 * @internal Valores de los secretos de los destinos, descifrados por la única lectura (settings()), para que un aviso
	 * por correo los oculte si un mensaje de error llegara a contener alguno. Nunca salen de este proceso.
	 */
	public static function secret_values() {
		$settings = self::settings();
		$values = array();
		foreach ( self::secret_fields() as $field ) if ( is_string( $settings[ $field ] ?? null ) && '' !== $settings[ $field ] ) $values[] = $settings[ $field ];
		return $values;
	}
	/** Nombre de una automatización para los avisos: su etiqueta o, sin ella, su tipo y frecuencia. */
	public static function automation_name( array $policy ) { return '' !== trim( (string) ( $policy['label'] ?? '' ) ) ? (string) $policy['label'] : self::policy_label( $policy ); }
	public static function backup_file_name( $prefix = '' ) { return $prefix . 'digitalisimo-backup-' . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '-' . gmdate( 'Ymd-His' ) . '-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 ) . '.zip'; }
	private static function network_context() { if ( ! is_multisite() ) return false; if ( is_network_admin() || ! empty( $_REQUEST['digitalisimo_backups_network'] ) ) return true; $referer = wp_get_referer(); return $referer && false !== strpos( $referer, '/wp-admin/network/' ); }
	private static function admin_url() { return self::network_context() ? network_admin_url( 'admin.php?page=digitalisimo-network-backups' ) : admin_url( 'admin.php?page=digitalisimo-backups' ); }
	private static function context_args() { return self::network_context() ? array( 'digitalisimo_backups_network' => '1' ) : array(); }
	public static function page() { if ( ! self::can_manage() ) { echo '<div class="wrap"><h1>Backups · Digitalisimo</h1><p>El respaldo completo de esta red requiere permisos de administración de red.</p></div>'; return; } self::render( false ); }
	public static function network_page() { if ( ! self::can_manage() ) return; self::render( true ); }
	/** Secretos de los destinos: nunca vuelven al navegador y viven cifrados en Digitalisimo_Backups_Credentials. */
	private static function secret_fields() { return Digitalisimo_Backups_Credentials::FIELDS; }
	/** Sólo ofrece destinos remotos que ya cuentan con una conexión válida. */
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
		// Tras restaurar (H23): la configuración del respaldo que no se aplicó, o un sitio sin automatizaciones activas.
		try { echo self::restore_notice_html(); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		// Credenciales de los destinos que no se abren o que siguen sin cifrar: qué hacer, sin ningún valor.
		$credentials = self::credentials_status();
		if ( '' !== $credentials['notice'] && 'security' !== sanitize_key( $_GET['tab'] ?? 'backups' ) ) echo '<div class="notice notice-warning digitalisimo-backups-credentials" data-state="' . esc_attr( $credentials['state'] ) . '"><p>' . esc_html( $credentials['notice'] ) . '</p></div>';
		// Una restauración sin terminar (o que no terminó bien): qué fase y qué pasa con el sitio, en todas las pestañas.
		$restore = self::restore_status();
		if ( 'none' !== $restore['state'] ) {
			$titles = array( 'running' => 'Restauración en curso.', 'interrupted' => 'Restauración interrumpida: se reanudará sola.', 'rollback' => 'Volviendo a la copia previa.', 'failed' => 'La última restauración no se completó.', 'failed_midway' => 'La última restauración falló a mitad del cambio.', 'rolled_back' => 'La última restauración falló y el sitio volvió a la copia previa.', 'cancelled' => 'La última restauración se canceló.' );
			echo '<div class="notice notice-' . ( in_array( $restore['state'], array( 'failed_midway', 'rollback', 'interrupted' ), true ) ? 'error' : 'warning' ) . ' digitalisimo-backups-restore-state" data-state="' . esc_attr( $restore['state'] ) . '"><p><strong>' . esc_html( $titles[ $restore['state'] ] ?? '' ) . '</strong> ' . esc_html( $restore['notice'] ) . '</p>';
			// Un sitio a medio cambiar se recupera desde aquí: otra vuelta a la copia previa (revisión, C2).
			if ( 'failed_midway' === $restore['state'] && '' !== (string) ( $restore['recover'] ?? '' ) && ! Digitalisimo_Backups_Jobs::restore_active() ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( self::ACTION_RESTORE );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RESTORE ) . '"><input type="hidden" name="rollback_retry" value="1"><p><button class="button button-primary">Volver a la copia previa (' . esc_html( (string) $restore['recover'] ) . ')</button> <span class="description">Restaura la copia previa de esa restauración sin hacer otra; el sitio sigue en mantenimiento hasta que termine.</span></p></form>';
			}
			echo '</div>';
			$active = Digitalisimo_Backups_Jobs::restore_active();
			// La pantalla sigue la restauración con su testigo (vale aunque la sesión cambie con la base de datos restaurada).
			if ( $active && Digitalisimo_Backups_Restore_Maintenance::request_has( (string) $active['spec']['token'] ) ) echo '<script>window.digitalisimoBackupsRestore=' . wp_json_encode( array( 'token' => (string) $active['spec']['token'], 'action' => Digitalisimo_Backups_Jobs::AJAX_RESTORE_TICK ) ) . ';</script>';
		}
		$tab = sanitize_key( $_GET['tab'] ?? 'backups' ); $base = self::admin_url(); echo '<nav class="digitalisimo-backups-tabs"><a class="' . ( 'backups' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'backups', $base ) ) . '">Backups</a><a class="' . ( 'servers' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'servers', $base ) ) . '">Servidores</a><a class="' . ( 'security' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'security', $base ) ) . '">Seguridad</a><a class="' . ( 'notifications' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'notifications', $base ) ) . '">Avisos</a><a class="' . ( 'migrate' === $tab ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'migrate', $base ) ) . '">Migrar</a></nav>';
		if ( 'servers' === $tab ) { if ( self::credentials_here() ) { self::migrate_server_catalog(); self::servers_panel( $s, $credentials['locked'] ); } else echo '<section class="digitalisimo-backups-card"><h2>Servidores y nubes</h2><p>Las conexiones de los destinos son de toda la red: se gestionan desde la administración de red o el sitio principal.</p></section>'; echo '</div>'; return; }
		if ( 'security' === $tab ) { Digitalisimo_Backups_Security::panel( $network, (array) get_option( self::HISTORY, array() ) ); echo '</div>'; return; }
		// Migrar: la tarjeta «Trabajos» hace avanzar la migración con la página abierta, como un respaldo.
		if ( 'migrate' === $tab ) { Digitalisimo_Backups_Migrate::panel( $network, self::migration_status_data() ); self::jobs_panel( $network ); echo '</div>'; return; }
		if ( 'notifications' === $tab ) { Digitalisimo_Backups_Notify::panel( $network ); echo '</div>'; return; }
			echo '<div class="digitalisimo-backups-grid"><section class="digitalisimo-backups-card"><h2>Crear respaldo completo</h2><p>Incluye todos los sitios de la red cuando WordPress usa Multisite, o el sitio completo en una instalación individual.</p>';
			if ( is_multisite() && ! is_main_site() ) echo '<p class="notice notice-warning inline">El respaldo completo de la red debe iniciarse desde el sitio principal o la administración de red. Este subsitio no generará una copia incompleta.</p>';
			echo '<form id="digitalisimo-backups-manual-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_RUN );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN ) . '"><input type="hidden" name="digitalisimo_backup_scope" value="installation">';
		if ( $network ) echo '<input type="hidden" name="digitalisimo_backups_network" value="1">';
			echo '<p>' . self::manual_destination_select( $s ) . '</p>' . self::remote_only_field( false, 'digitalisimo_backup_remote_only' ) . '<button class="button button-primary button-hero"' . disabled( is_multisite() && ! is_main_site(), true, false ) . '>Crear respaldo completo ' . ( is_multisite() ? 'de la red' : 'de WordPress' ) . '</button></form><div id="digitalisimo-backups-manual-progress" class="digitalisimo-backups-manual-progress" hidden><p id="digitalisimo-backups-manual-message" role="status" aria-live="polite"></p><progress id="digitalisimo-backups-manual-bar" max="100" value="0"></progress><p id="digitalisimo-backups-manual-detail" aria-live="polite"></p></div>';

			echo '<p class="description">Destino actual: <strong>' . esc_html( self::destination_label( $s['destination'] ) ) . '</strong></p></section><section class="digitalisimo-backups-card"><h2>Últimos respaldos</h2>';
				if ( ! $history ) echo '<p>Aún no hay respaldos creados.</p>'; else { echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Archivo</th><th>Tipo</th><th>Tamaño</th><th>Destino</th><th>Seguridad</th><th>Protección</th><th>Acciones</th></tr></thead><tbody>'; foreach ( $history as $item ) { $file = $item['file'] ?? ''; echo '<tr><td>' . esc_html( $item['date'] ?? '' ) . '</td><td>' . esc_html( $file ) . '</td><td>' . esc_html( ( $item['scope'] ?? 'site' ) === 'network' ? 'Red completa' : ( ( $item['scope'] ?? 'site' ) === 'installation' ? 'Instalación completa' : ( Digitalisimo_Backups_Site_Extract::SCOPE === ( $item['scope'] ?? '' ) ? 'Sitio extraído: ' . (string) ( $item['extract']['label'] ?? '' ) : 'Exportación anterior de sitio' ) ) ) . '</td><td>' . esc_html( size_format( (int) ( $item['size'] ?? 0 ) ) ) . '</td><td>' . esc_html( self::history_destination( $item ) ) . '</td><td>' . Digitalisimo_Backups_Security::history_badge( $item ) . ( '' !== self::history_notices_text( $item ) ? ' <span class="digitalisimo-backups-badge is-warning">Con avisos</span><br><small>' . esc_html( self::history_notices_text( $item ) ) . '</small>' : '' ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_LOCK ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_LOCK ) . '"><input type="hidden" name="file" value="' . esc_attr( $file ) . '"><input type="hidden" name="locked" value="' . ( empty( $item['locked'] ) ? '1' : '0' ) . '"><button class="button button-small">' . ( empty( $item['locked'] ) ? 'Bloquear' : 'Bloqueado ✓' ) . '</button></form></td><td>'; $has_local = '' !== self::local_copy( $item ); if ( $has_local || ! empty( $item['remote']['provider'] ) ) echo '<a class="button button-small" href="' . esc_url( self::download_url( $file ) ) . '">Descargar</a> '; elseif ( self::remote_exists( $item ) ) echo 'Respaldo remoto anterior: sin identificador '; /* «Eliminar» siempre: también una fila que ya no tiene ninguna copia. */ echo '<form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Eliminar este respaldo?\')">'; wp_nonce_field( self::ACTION_DELETE ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE ) . '"><input type="hidden" name="file" value="' . esc_attr( $file ) . '"><button class="button button-small">Eliminar</button></form>'; self::fetch_button( $item ); echo '</td></tr>'; } echo '</tbody></table>'; } echo '</section></div>';
			if ( $network && is_multisite() ) self::extract_panel( $s );
			Digitalisimo_Backups_Pre_Update::panel( $network );
			self::jobs_panel( $network ); self::updraft_panel(); self::backuply_panel( $network ); if ( ! is_multisite() || is_main_site() ) Digitalisimo_Backups_Uploads::panel( $network ); self::restore_panel( $history ); self::automation_fields(); echo '</div>';
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
			echo '</label></p><p><label>Superadministradores que no son miembros del sitio<br><select name="super_admins"><option value="none" selected>Ninguno viaja con su contraseña (si escribieron contenido, quedan como autores sin acceso)</option><option value="managing">Como administrador, sólo quien tiene el correo de administración del sitio</option><option value="all">Todos, como administradores</option></select></label></p>';
			echo '<p><label>Frase propia de este paquete<br><input type="password" name="extract_secret" value="" autocomplete="new-password" minlength="16"></label><br><span class="description">Cifra y firma el paquete; quien lo restaura sólo necesita esta frase. Nunca se usa la clave de la red, que abre todos sus respaldos: no la entregues. Sin frase, el paquete sale sin cifrar ni firmar' . ( Digitalisimo_Backups_Security::settings()['encrypt'] ? ' (en esta red los respaldos se cifran: la frase es obligatoria)' : '' ) . '.</span></p>';
			$server = Digitalisimo_Backups_Site_Extract::server_files();
			if ( $server['mu_plugins'] ) echo '<p class="notice notice-warning inline">Viajan y se ejecutarán en el destino estos mu-plugins de la red (revísalos antes de entregar el sitio: pueden llevar personalizaciones de otros sitios o claves): ' . esc_html( implode( ', ', $server['mu_plugins'] ) ) . '.</p>';
			if ( $server['drop_ins'] ) echo '<p class="notice notice-info inline">No viajan estos drop-ins del servidor de la red (en otro servidor lo dejarían sin servicio; el destino conserva los suyos): ' . esc_html( implode( ', ', $server['drop_ins'] ) ) . '.</p>';
			echo '<p><label><input type="checkbox" name="network_secrets" value="1"> Copiar también las claves de API que el sitio hereda de la red (IA, Google Places, Namecheap, IPinfo)</label><br><span class="description">Sin marcar, esas claves quedan vacías y el informe del paquete dice cuáles volver a escribir: así un sitio entregado a otro dueño no se lleva las claves de la red.</span></p>';
			echo '<p>' . self::manual_destination_select( $s ) . '</p><p><button class="button button-primary">Extraer como WordPress individual</button></p><p class="description">Su avance aparece en «Trabajos». El sitio sigue en la red: la extracción no lo cambia ni lo retira.</p></form></section>';
		}
		/** admin-post de «Extraer como WordPress individual»: el mismo caso de uso que REST y WP-CLI, con el alcance de red. */
		public static function extract_request() {
			if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
			check_admin_referer( Digitalisimo_Backups_Site_Extract::ACTION );
			if ( ! is_multisite() || empty( $_POST['digitalisimo_backups_network'] ) ) self::redirect( false, 'Un sitio se extrae desde la administración de la red.', is_multisite() );
			// La frase se usa tal cual (sanitize_text_field() la alteraría).
			$secret = isset( $_POST['extract_secret'] ) && is_string( $_POST['extract_secret'] ) ? (string) wp_unslash( $_POST['extract_secret'] ) : '';
			$result = self::service_extract( absint( $_POST['site_id'] ?? 0 ), array( 'network' => true, 'secret' => $secret, 'super_admins' => sanitize_key( wp_unslash( $_POST['super_admins'] ?? 'none' ) ), 'network_secrets' => ! empty( $_POST['network_secrets'] ), 'destination' => sanitize_key( wp_unslash( $_POST['digitalisimo_backup_destination'] ?? 'local' ) ), 'via' => 'admin-post' ) );
			if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message(), true );
			self::redirect( true, 'Extracción en cola. Su avance aparece en «Trabajos»; el paquete quedará en el historial para descargarlo.', true );
		}
		/** Destino de una fila del historial con el estado de su copia remota (las filas antiguas no lo tienen). */
		private static function history_destination( $item ) {
			$local = '' !== self::local_copy( $item );
			$copy = self::remote_copy( $item );
			if ( ! $copy ) return self::destination_label( 'local' ) . ( $local ? '' : ' · el ZIP ya no está en el almacén' );
			$target = self::destination_label( $copy['destination'] );
			$states = array( 'ok' => 'copia en ' . $target . ' verificada', 'pending' => 'copia a ' . $target . ' pendiente', 'failed' => 'la copia a ' . $target . ' falló', 'cancelled' => 'copia a ' . $target . ' cancelada', 'legacy' => 'copia anterior en ' . $target . ' sin identificador', 'pruned' => 'la retención borró la copia en ' . $target );
			$remote = ( $states[ $copy['state'] ] ?? 'copia en ' . $target ) . self::remote_retention_text( $item );
			// «Sólo en el remoto»: el ZIP local se borró a propósito tras verificar la copia remota.
			if ( ! $local && 'remote-only' === ( $item['local'] ?? '' ) && 'ok' === $copy['state'] ) return $target . ' · sólo en el remoto (restaurar exige traerla al almacén)' . self::remote_retention_text( $item );
			if ( $local ) return self::destination_label( 'local' ) . ' · ' . $remote;
			// Sin el ZIP local y sin copia remota que exista: no queda ninguna copia (la fila sólo se puede eliminar).
			if ( ! self::remote_exists( $item ) ) return 'Sin copias: el ZIP ya no está en el almacén y ' . $remote;
			return $target . ( 'ok' === $copy['state'] ? ' · sólo la copia remota' . self::remote_retention_text( $item ) : ' · ' . $remote );
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
		private static function policy_types() { return array( 'full' => 'Respaldo completo' ); }
		private static function policy_label( $policy ) { $types = self::policy_types(); $schedules = self::schedule_options(); $time = self::fixed_time( $policy ); return ( $types[ $policy['type'] ] ?? 'Respaldo' ) . ' · ' . ( $schedules[ $policy['schedule'] ] ?? 'Sin programación' ) . ( '' !== $time ? ' a las ' . $time : '' ); }
		/** Campo de la hora fija de una automatización (hora del sitio). */
		private static function time_field( $value ) { return '<label>Hora del sitio<br><input type="time" step="60" name="digitalisimo_backup_automation[time]" value="' . esc_attr( (string) $value ) . '"></label>'; }
		private static function time_help() { $zone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC'; return '<p class="description">Hora fija para las frecuencias diaria, semanal y mensual, en la zona horaria del sitio (' . esc_html( $zone ) . '); la semanal y la mensual empiezan el primer día que llegue esa hora. Sin hora, WP-Cron la ejecuta cuando toca, como hasta ahora. WP-Cron necesita visitas: para una hora exacta en un sitio con poco tráfico, programa el cron del sistema (ver «REST y WP-CLI»).</p>'; }
		/** Cada automatización tiene un formulario propio: no existe una configuración global compartida. */
		private static function automation_fields() {
			$types = self::policy_types(); $schedules = self::schedule_options(); $destinations = array_keys( self::destination_choices() );
			echo '<section class="digitalisimo-backups-automations"><h2>Automatizaciones de respaldo</h2><p>Crea una automatización para cada estrategia de respaldo. Sus opciones se guardan de manera independiente y puedes crear, modificar, ejecutar o eliminar cada una sin afectar las demás.</p>';
			echo '<div class="digitalisimo-backups-card"><h3>Crear automatización</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_CREATE_AUTOMATION ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CREATE_AUTOMATION ) . '"><p><label>Tipo<br><select name="digitalisimo_backup_automation[type]">'; foreach ( $types as $value => $label ) echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label> <label>Frecuencia<br><select name="digitalisimo_backup_automation[schedule]">'; foreach ( $schedules as $value => $label ) if ( 'disabled' !== $value ) echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, 'weekly', false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label> <label>Destino<br><select name="digitalisimo_backup_automation[destination]">'; foreach ( $destinations as $destination ) echo '<option value="' . esc_attr( $destination ) . '">' . esc_html( self::destination_label( $destination ) ) . '</option>'; echo '</select></label> <label>Conservar<br><input type="number" min="1" max="100" name="digitalisimo_backup_automation[retain]" value="4"></label> ' . self::time_field( '' ) . '</p>' . self::time_help() . self::remote_only_field( false ) . '<p><button class="button button-primary">Crear automatización</button></p></form></div>';
			echo '<h3>Automatizaciones creadas</h3><div class="digitalisimo-backups-grid">';
			foreach ( self::policies() as $policy ) { $id = esc_attr( $policy['id'] ); if ( 'full' !== ( $policy['type'] ?? '' ) ) { echo '<section class="digitalisimo-backups-card"><h3>' . esc_html( $policy['label'] ?? $id ) . '</h3><p>Esta automatización parcial anterior está desactivada. Sus respaldos históricos se conservan; crea una automatización completa para proteger la instalación.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Eliminar esta automatización anterior?\')">'; wp_nonce_field( self::ACTION_DELETE_AUTOMATION ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE_AUTOMATION ) . '"><input type="hidden" name="policy_id" value="' . $id . '"><button class="button button-link-delete">Eliminar automatización</button></form></section>'; continue; } echo '<section class="digitalisimo-backups-card"><h3>' . esc_html( self::policy_label( $policy ) ) . '</h3><p class="description">Tipo fijo: Respaldo completo.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_UPDATE_AUTOMATION ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_UPDATE_AUTOMATION ) . '"><input type="hidden" name="policy_id" value="' . $id . '"><p><label><input type="checkbox" name="digitalisimo_backup_automation[enabled]" value="1" ' . checked( ! empty( $policy['enabled'] ), true, false ) . '> Activa</label></p><p><label>Frecuencia<br><select name="digitalisimo_backup_automation[schedule]">'; foreach ( $schedules as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $policy['schedule'], $value, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label> <label>Destino<br><select name="digitalisimo_backup_automation[destination]">'; foreach ( $destinations as $destination ) echo '<option value="' . esc_attr( $destination ) . '" ' . selected( $policy['destination'], $destination, false ) . '>' . esc_html( self::destination_label( $destination ) ) . '</option>'; echo '</select></label> <label>Conservar<br><input type="number" min="1" max="100" name="digitalisimo_backup_automation[retain]" value="' . esc_attr( $policy['retain'] ) . '"></label> ' . self::time_field( $policy['time'] ?? '' ) . '</p>' . self::remote_only_field( ! empty( $policy['remote_only'] ) ) . '<p><button class="button button-primary">Guardar esta automatización</button></p></form><div class="digitalisimo-backups-actions"><form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_RUN_POLICY ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN_POLICY ) . '"><input type="hidden" name="digitalisimo_backup_policy" value="' . $id . '"><button class="button">Ejecutar ahora</button></form> <form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Eliminar esta automatización? Sus respaldos ya creados se conservan.\')">'; wp_nonce_field( self::ACTION_DELETE_AUTOMATION ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE_AUTOMATION ) . '"><input type="hidden" name="policy_id" value="' . $id . '"><button class="button button-link-delete">Eliminar</button></form></div></section>'; }
			echo '</div></section>';
		}
		private static function updraft_panel() { $sets = self::detect_updraft_sets(); echo '<h2>Respaldos detectados de UpdraftPlus</h2><p>Se analizan los archivos estándar dentro de <code>wp-content/updraft</code>. Sólo los conjuntos completos y sin cifrado se pueden restaurar.</p>'; if ( ! $sets ) { echo '<p>No se detectaron respaldos de UpdraftPlus en el almacenamiento local.</p>'; return; } echo '<table class="widefat striped"><thead><tr><th>Conjunto</th><th>Componentes</th><th>Estado</th></tr></thead><tbody>'; foreach ( $sets as $set ) { $components = array_map( function( $name, $parts ) { return $name . ( count( $parts ) > 1 ? ' × ' . count( $parts ) : '' ); }, array_keys( $set['files'] ), $set['files'] ); echo '<tr><td>' . esc_html( $set['id'] ) . '</td><td>' . esc_html( implode( ', ', $components ) ) . '</td><td>' . ( $set['complete'] ? 'Listo para restaurar abajo' : 'Incompleto o cifrado' ) . '</td></tr>'; } echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_RESTORE ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RESTORE ) . '"><input type="hidden" name="updraft" value="1"><p><select name="updraft_set"><option value="">Elige un conjunto UpdraftPlus</option>'; foreach ( $sets as $set ) if ( $set['complete'] ) echo '<option value="' . esc_attr( $set['id'] ) . '">' . esc_html( $set['id'] ) . '</option>'; echo '</select> <input type="text" name="confirmation" placeholder="RESTAURAR SIN FIRMA" autocomplete="off"> <button class="button">Restaurar conjunto</button></p><p class="description">Un conjunto de UpdraftPlus no lleva firma de este sitio: su SQL se revisa entero antes de cambiar nada y se pide escribir RESTAURAR SIN FIRMA. Restaurarlo instala su código y sus datos.</p></form>'; }
		private static function schedule_fields( $s ) { echo '<h2>Políticas automáticas</h2><p>Cada política tiene su propia frecuencia, destino y retención. Un incremental guarda la base de datos y sólo los archivos modificados desde su último punto base; al restaurar se aplica después de su respaldo completo base.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( self::ACTION_SAVE_POLICIES ); echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE_POLICIES ) . '"><table class="widefat striped"><thead><tr><th>Activa</th><th>Nombre</th><th>Tipo</th><th>Frecuencia</th><th>Destino</th><th>Conservar</th><th>Ejecutar</th></tr></thead><tbody>'; foreach ( self::policies() as $policy ) { $id = esc_attr( $policy['id'] ); echo '<tr><td><input type="checkbox" name="digitalisimo_backups_policies[' . $id . '][enabled]" value="1" ' . checked( ! empty( $policy['enabled'] ), true, false ) . '></td><td><input type="hidden" name="digitalisimo_backups_policies[' . $id . '][id]" value="' . $id . '"><input type="text" name="digitalisimo_backups_policies[' . $id . '][label]" value="' . esc_attr( $policy['label'] ) . '"></td><td><strong>' . esc_html( array( 'full' => 'Completo', 'incremental' => 'Incremental', 'database' => 'Base de datos' )[ $policy['type'] ] ) . '</strong><input type="hidden" name="digitalisimo_backups_policies[' . $id . '][type]" value="' . esc_attr( $policy['type'] ) . '"></td><td><select name="digitalisimo_backups_policies[' . $id . '][schedule]">'; foreach ( self::schedule_options() as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $policy['schedule'], $value, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></td><td><select name="digitalisimo_backups_policies[' . $id . '][destination]">'; foreach ( array_keys( self::destination_choices() ) as $destination ) echo '<option value="' . esc_attr( $destination ) . '" ' . selected( $policy['destination'], $destination, false ) . '>' . esc_html( self::destination_label( $destination ) ) . '</option>'; echo '</select></td><td><input type="number" min="1" max="100" name="digitalisimo_backups_policies[' . $id . '][retain]" value="' . esc_attr( $policy['retain'] ) . '"></td><td><button class="button" name="action" value="' . esc_attr( self::ACTION_RUN_POLICY ) . '" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" formmethod="post">Ejecutar ahora<input type="hidden" name="digitalisimo_backup_policy" value="' . $id . '"></button></td></tr>'; } echo '</tbody></table><p><button class="button button-primary">Guardar políticas automáticas</button></p></form>'; }
	private static function download_url( $file ) { return wp_nonce_url( add_query_arg( array_merge( array( 'action' => self::ACTION_DOWNLOAD, 'file' => rawurlencode( $file ) ), self::context_args() ), admin_url( 'admin-post.php' ) ), self::ACTION_DOWNLOAD ); }
	/** Descarga el ZIP local si sigue en el almacén (también el de un respaldo con copia remota); si no, la copia remota. */
	public static function download() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_DOWNLOAD );
		$file = sanitize_file_name( wp_unslash( $_GET['file'] ?? '' ) );
		$item = self::history_item( $file );
		if ( ! $item ) wp_die( 'Respaldo no disponible.' );
		$path = self::local_path( $file );
		if ( ! $path ) {
			$copy = self::remote_copy( $item );
			if ( $copy && ( ! empty( $copy['remote']['provider'] ) || 'legacy' === $copy['state'] ) ) { self::download_remote( $item ); exit; }
			wp_die( 'Respaldo no disponible.' );
		}
		self::send_file( $path, $file );
		exit;
	}
	/** Bloque de cada escritura de una descarga al navegador. */
	const SEND_BLOCK = 1048576;
	/**
	 * @internal Envía un archivo al navegador por bloques, con memoria constante (H16 del ensayo E2): readfile() con el
	 * búfer de salida de PHP activo y sin límite (`output_buffering=On`, el «default output handler» del PHP del hosting)
	 * acumulaba el archivo entero y un respaldo mayor que memory_limit fallaba (500, o el proceso muerto por el límite
	 * duro del hosting); y lo que otro código hubiera impreso antes iba al principio del archivo. Cierra los búferes de
	 * salida que se pueden cerrar (lo que tuvieran no es parte del archivo) y la compresión de salida, anuncia
	 * Content-Length y escribe bloques de SEND_BLOCK bytes con flush(). Devuelve los bytes enviados.
	 */
	public static function send_file( $path, $name ) {
		clearstatcache( true, $path );
		$size = @filesize( $path );
		$handle = @fopen( $path, 'rb' );
		if ( false === $size || ! $handle ) {
			if ( $handle ) fclose( $handle );
			wp_die( 'No se pudo leer el respaldo en el almacén privado.' );
		}
		@set_time_limit( 0 );
		// Si el navegador corta, PHP no aborta a mitad: el bucle sale con connection_aborted() y quien llama limpia su
		// temporal (revisión C5: sin esto no corría ningún finally).
		@ignore_user_abort( true );
		@ini_set( 'zlib.output_compression', '0' );
		if ( function_exists( 'apache_setenv' ) ) @apache_setenv( 'no-gzip', '1' );
		while ( ob_get_level() > 0 && @ob_end_clean() ) continue;
		// Un búfer que no se puede cerrar (de otro plugin): si es el único y se puede vaciar, se vacía (lo impreso antes no
		// va al archivo) y recibe un bloque cada vez; si hay otro debajo (output_buffering=On) o no se puede vaciar, el
		// archivo saldría dañado o acumulado en memoria: no se envía (revisión C2/C3).
		$levels = ob_get_level() > 0 ? (array) ob_get_status( true ) : array();
		if ( $levels ) {
			$flags = (int) ( $levels[0]['flags'] ?? 0 );
			if ( 1 !== count( $levels ) || ! ( $flags & PHP_OUTPUT_HANDLER_CLEANABLE ) || ! ( $flags & PHP_OUTPUT_HANDLER_FLUSHABLE ) || ! @ob_clean() ) {
				fclose( $handle );
				wp_die( 'Otro plugin retiene la salida de PHP (' . esc_html( implode( ', ', array_map( function( $level ) { return (string) ( $level['name'] ?? '?' ); }, $levels ) ) ) . ') y la descarga saldría dañada o no cabría en la memoria: no se envió. Desactívalo para descargar, o copia el respaldo del almacén privado por SFTP o SSH.', 'Descarga no disponible', array( 'response' => 500 ) );
			}
		}
		nocache_headers();
		header( 'Content-Type: ' . self::content_type( $name ) );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'Content-Length: ' . (int) $size );
		header( 'X-Content-Type-Options: nosniff' );
		$sent = 0;
		try {
			while ( $sent < $size ) {
				$block = fread( $handle, (int) min( self::SEND_BLOCK, $size - $sent ) );
				if ( false === $block || '' === $block ) break;
				echo $block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido binario del respaldo.
				$sent += strlen( $block );
				unset( $block );
				if ( ob_get_level() > 0 ) @ob_flush();
				flush();
				if ( connection_aborted() ) break;
			}
		} finally {
			fclose( $handle );
		}
		return $sent;
	}
	/** Un respaldo cifrado no es un ZIP para el navegador ni para los destinos remotos. */
	private static function content_type( $file ) { return '.dgbk' === substr( (string) $file, -5 ) ? 'application/octet-stream' : 'application/zip'; }
	/**
	 * Elimina un respaldo: su copia remota verificada, su ZIP local y su fila. La copia remota va primero: si el destino no
	 * confirma la eliminación no se cambia nada. Una copia remota anterior a 1.0.59 sin identificador no se puede borrar
	 * desde aquí y el aviso dice dónde queda; una copia que nunca se verificó (pendiente, fallida o cancelada) no existe.
	 */
	public static function delete_backup() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_DELETE );
		$file = sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) );
		$item = self::history_item( $file );
		if ( ! $item ) self::redirect( false, 'El respaldo no está registrado.' );
		$gone = '';
		try {
			$path = self::local_path( $file );
			$copy = self::remote_copy( $item );
			$remote = $copy && ! empty( $copy['remote']['provider'] );
			$notice = $copy && 'legacy' === $copy['state'] ? ' La copia anterior en ' . self::destination_label( $copy['destination'] ) . ' no tiene identificador: elimínala desde ' . self::destination_label( $copy['destination'] ) . '.' : '';
			// Si su conexión ya se eliminó no hay a dónde llamar: sólo se quita la fila, con el aviso (1.0.62).
			$gone = $remote ? self::delete_remote( $item ) : '';
			if ( $path ) {
				try {
					if ( ! is_writable( dirname( $path ) ) ) throw new Exception( 'El hosting no permite escribir en la carpeta de respaldos: ' . dirname( $path ) );
					if ( ! @wp_delete_file( $path ) && file_exists( $path ) ) throw new Exception( 'El hosting rechazó eliminar el archivo local. Revisa permisos de la carpeta de respaldos.' );
				} catch ( Exception $local_error ) {
					// La copia remota ya no existe: la fila se queda sólo con el ZIP local que no se pudo borrar.
					if ( $remote ) self::history_update( $file, array( 'destination' => 'local', 'transfer' => '', 'transfer_destination' => '', 'transfer_error' => '', 'remote' => array() ) );
					throw $local_error;
				}
			}
			self::remove_history( $file );
		} catch ( Exception $e ) {
			self::redirect( false, $e->getMessage() );
		}
		if ( '' !== $gone ) self::redirect( true, $gone . $notice );
		if ( ! $path && ! $copy ) self::redirect( true, 'El archivo local ya no existía; se eliminó su registro del listado.' );
		self::redirect( true, 'Respaldo eliminado' . ( $remote ? ( $path ? ', también su copia en ' : ' de ' ) . self::destination_label( $copy['destination'] ) : '' ) . '.' . $notice );
	}
	public static function save_policies() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_SAVE_POLICIES );
		$allowed_types = array( 'full' ); $allowed_destinations = array_keys( self::destination_choices() ); $out = array();
		foreach ( (array) ( $_POST['digitalisimo_backups_policies'] ?? array() ) as $id => $policy ) {
			$id = sanitize_key( $id ); $type = sanitize_key( $policy['type'] ?? '' );
			if ( ! $id || ! in_array( $type, $allowed_types, true ) ) continue;
			$schedule = sanitize_key( $policy['schedule'] ?? 'disabled' );
			$time = trim( (string) ( $policy['time'] ?? ( self::policy( $id )['time'] ?? '' ) ) );
			$out[] = array( 'id' => $id, 'label' => sanitize_text_field( $policy['label'] ?? $id ), 'type' => $type, 'schedule' => array_key_exists( $schedule, self::schedule_options() ) ? $schedule : 'disabled', 'destination' => in_array( $policy['destination'] ?? '', $allowed_destinations, true ) ? $policy['destination'] : 'local', 'retain' => min( 100, max( 1, absint( $policy['retain'] ?? 4 ) ) ), 'enabled' => ! empty( $policy['enabled'] ), 'time' => preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '' );
		}
		if ( ! $out ) self::redirect( false, 'No se recibió una política válida.' );
		update_option( self::POLICIES, $out, false ); self::sync_policy_schedules(); self::redirect( true, 'Políticas automáticas guardadas.' );
	}
	private static function automation_input( $input, $id = '', $type = '' ) {
		$types = self::policy_types(); $schedules = self::schedule_options(); $destinations = array_keys( self::destination_choices() );
		$type = $type ?: sanitize_key( $input['type'] ?? '' );
		if ( ! isset( $types[ $type ] ) ) return new WP_Error( 'digitalisimo_backup_type', 'El tipo de respaldo no es válido.' );
		$schedule = sanitize_key( $input['schedule'] ?? 'daily' );
		if ( ! isset( $schedules[ $schedule ] ) ) $schedule = 'daily';
		$destination = in_array( $input['destination'] ?? '', $destinations, true ) ? $input['destination'] : 'local';
		if ( 'incremental' === $type && ! in_array( $destination, array( 'local', 'sftp' ), true ) ) return new WP_Error( 'digitalisimo_backup_incremental_destination', 'Los respaldos incrementales sólo se admiten en Local o SFTP. Google Drive y OneDrive siempre generan respaldos completos.' );
		// Hora fija (del sitio) para diaria, semanal y mensual; vacía, WP-Cron la ejecuta cuando toca, como hasta ahora.
		$time = trim( (string) ( $input['time'] ?? '' ) );
		if ( '' !== $time && ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) return new WP_Error( 'digitalisimo_backup_time', 'La hora de la automatización debe tener el formato HH:MM (de 00:00 a 23:59).' );
		// «Sólo en el remoto» (I4): sin destino remoto no tiene sentido y no se guarda.
		return array( 'id' => $id ?: 'automation-' . wp_generate_uuid4(), 'label' => '', 'type' => $type, 'schedule' => $schedule, 'destination' => $destination, 'retain' => min( 100, max( 1, absint( $input['retain'] ?? 4 ) ) ), 'enabled' => ! empty( $input['enabled'] ), 'time' => $time, 'remote_only' => 'local' !== $destination && ! empty( $input['remote_only'] ) );
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
		$result = self::service_create( array( 'destination' => wp_unslash( $_POST['digitalisimo_backup_destination'] ?? '' ), 'remote_only' => ! empty( $_POST['digitalisimo_backup_remote_only'] ), 'network' => is_multisite(), 'via' => 'admin-post', 'context' => $context ? 'network' : 'site' ) );
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
		return array( 'status' => $status, 'phase' => $view['phase_label'] ?: $view['status_label'], 'percent' => $view['percent'], 'message' => 'failed' === $status && $view['error'] ? $view['error'] : $view['message'] . ( $view['notices'] ? ' · Con avisos: ' . implode( ' ', $view['notices'] ) : '' ), 'file' => $view['file'] );
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
		$result = self::service_create( array( 'destination' => $destination, 'remote_only' => ! empty( $_POST['remote_only'] ), 'network' => is_multisite(), 'via' => 'ajax', 'context' => $context, 'spawn' => false ) );
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
			// Por tramos (T2): sin pasar del límite real de la petición; lo que falte lo siguen WP-Cron o la pantalla.
		} while ( $job && 'worked' === $tick['result'] && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) && (int) $job['retry_at'] <= time() && Digitalisimo_Backups_Step_Budget::request_remaining() > 0 );
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
	/**
	 * Diferencias de wp-config.php entre un respaldo abierto y el que carga este servidor
	 * (Digitalisimo_Backups_Config_Diff): `available`, `differences` (sin valores secretos ni rutas) y `text`.
	 */
	private static function config_report( $zip ) {
		try { $server = self::wordpress_config_file(); } catch ( Exception $e ) { $server = ''; }
		return Digitalisimo_Backups_Config_Diff::for_zip( $zip, $server );
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
	/**
	 * @internal Clave del trabajo en esta fase (devuelve la clave raíz: sólo para las fases del motor, que la borran de
	 * memoria al terminar). Un fallo es permanente y nunca deja salir en claro un respaldo que debía cifrarse.
	 */
	public static function job_security_plan( array $spec ) {
		// Un sitio extraído tiene su propia clave (la frase de la extracción) o ninguna; nunca la de la red.
		if ( Digitalisimo_Backups_Site_Extract::is_extract( $spec ) ) {
			try { return Digitalisimo_Backups_Site_Extract::job_plan( $spec ); } catch ( Exception $e ) { throw new Digitalisimo_Backups_Permanent_Error( $e->getMessage() ); }
		}
		try {
			$plan = Digitalisimo_Backups_Security::job_plan( $spec );
		} catch ( Exception $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( $e->getMessage() );
		}
		if ( ! empty( $spec['inline'] ) ) $plan['encrypt'] = false;
		return $plan;
	}
	/**
	 * Las tablas temporales y viejas de una restauración por pasos (`<prefijo>dgbkr_<marca>_<n>`, `…dgbko_…`) nunca
	 * entran en un respaldo: son del trabajo, no del sitio (una restauración que murió las deja hasta la siguiente).
	 * También cualquier `<prefijo>dgbkr_*`/`<prefijo>dgbko_*` con el prefijo de la base (la regla de T2 en la exportación
	 * por tramos, `Digitalisimo_Backups_Sql_Export::exportable()`, que usa ésta).
	 */
	public static function exportable_tables( $tables ) {
		if ( ! is_array( $tables ) ) return $tables;
		global $wpdb;
		$prefix = is_object( $wpdb ) ? (string) ( $wpdb->base_prefix ?? ( $wpdb->prefix ?? '' ) ) : '';
		// Otra instalación de WordPress en la misma base con un prefijo que empieza por el de este sitio (wp_ y wp_dev_): sus
		// tablas no son de este sitio y su respaldo no las lleva (la lista que se mira es la que se pasa: la de SHOW TABLES).
		$neighbors = self::other_installation_prefixes( $tables, $prefix );
		return array_values( array_filter( $tables, function( $table ) use ( $prefix, $neighbors ) {
			$table = (string) $table;
			foreach ( $neighbors as $neighbor ) if ( 0 === strncmp( $table, $neighbor, strlen( $neighbor ) ) ) return false;
			// Las temporales y viejas de una restauración por pasos, y las que una migración apartó y conserva (`dgbkk_`).
			if ( preg_match( '/(?:^|_)dgbk[rok]_[0-9a-f]{6}_[0-9]{3,}$/D', $table ) ) return false;
			foreach ( array( 'dgbkr_', 'dgbko_', 'dgbkk_' ) as $temporary ) if ( 0 === strpos( $table, $prefix . $temporary ) ) return false;
			return true;
		} ) );
	}
	/**
	 * Prefijos de otras instalaciones de WordPress en la misma base de datos que empiezan por `$base` (`wp_` y `wp_dev_`,
	 * `dst_` y `dst_otro_`): las que tienen entre `$tables` su `<prefijo>options` con `siteurl`. En una red,
	 * `<base><número>_` son sus propios subsitios, nunca otra instalación. Una tabla que no se puede leer no cuenta.
	 */
	public static function other_installation_prefixes( array $tables, $base ) {
		global $wpdb;
		$base = (string) $base;
		$others = array();
		// Sin una conexión que pueda leer una tabla (get_var) no hay nada que comprobar: nada se da por otra instalación.
		if ( '' === $base || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) return $others;
		foreach ( $tables as $table ) {
			if ( ! preg_match( '/^' . preg_quote( $base, '/' ) . '(.+_)options$/D', (string) $table, $m ) ) continue;
			if ( is_multisite() && preg_match( '/^[0-9]+_$/D', $m[1] ) ) continue;
			$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
			try {
				$siteurl = $wpdb->get_var( 'SELECT option_value FROM `' . str_replace( '`', '``', (string) $table ) . "` WHERE option_name = 'siteurl' LIMIT 1" );
			} catch ( Throwable $e ) {
				// Una tabla que no se puede leer no cuenta.
				$siteurl = null;
			} finally {
				if ( null !== $suppress ) $wpdb->suppress_errors( $suppress );
			}
			if ( null !== $siteurl && '' !== (string) $siteurl ) $others[] = $base . $m[1];
		}
		return array_values( array_unique( $others ) );
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
		$tables = self::exportable_tables( $wpdb->get_col( 'SHOW TABLES' ) );
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
			if ( is_array( $extract ) ) $manifest['extract'] = Digitalisimo_Backups_Site_Extract::manifest_block( $extract, $files );
			// Exclusiones por defecto (B8), como el empaquetado por tramos.
			elseif ( Digitalisimo_Backups_Exclusions::declared( (array) ( self::job_files_context()['exclusions'] ?? array() ) ) ) $manifest['excluded'] = Digitalisimo_Backups_Exclusions::declared( (array) self::job_files_context()['exclusions'] );
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
			// libzip crea el ZIP con los permisos por defecto (0644 en Linux): lleva wp-config.php y la base de datos.
			self::private_file( $target );
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
			// La cabecera dice lo que es el paquete: un sitio extraído es un WordPress individual.
			$extracted = Digitalisimo_Backups_Site_Extract::is_extract( $spec );
			return Digitalisimo_Backups_Security::seal( $zip_path, $target, $plan, array( 'backup_id' => (string) ( $meta['backup_id'] ?? '' ), 'created_at' => (string) ( $meta['created_at'] ?? '' ), 'plugin_version' => DIGITALISIMO_BACKUPS_VERSION, 'multisite' => $extracted ? false : is_multisite(), 'scope' => $extracted ? Digitalisimo_Backups_Site_Extract::SCOPE : 'installation' ) );
		} finally {
			if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
		}
	}
	/** @internal Fase «publish»: registra el ZIP verificado como local; la copia remota se anota al terminar la transferencia. */
	public static function job_register( $final, array $spec, $job_id ) {
		// La identidad de esta instalación (revisión H3): una copia de la base en otro sitio no puede hacer suyas estas filas.
		$extra = array( 'job' => (string) $job_id, 'installation' => self::installation_stamp() );
		if ( 'local' !== ( $spec['destination'] ?? 'local' ) ) $extra += array( 'transfer' => 'pending', 'transfer_destination' => (string) $spec['destination'] );
		// Un sitio extraído no es un respaldo de esta instalación: su fila lo dice y no aparece en «Restaurar» de la red.
		if ( Digitalisimo_Backups_Site_Extract::is_extract( $spec ) ) {
			// Lo que no viajó (nombres de la raíz, de plugins y temas de la red y de tablas de otros plugins) se queda aquí, en
			// el historial de la red: el paquete sólo lleva los recuentos.
			$detail = Digitalisimo_Backups_Site_Extract::network_detail( $job_id );
			self::remember( $final, 'local', Digitalisimo_Backups_Site_Extract::SCOPE, array( 'id' => (string) $spec['policy'], 'type' => Digitalisimo_Backups_Site_Extract::POLICY ), array(), (int) $spec['extract']['site_id'], false, $extra + array( 'extract' => array( 'site_id' => (int) $spec['extract']['site_id'], 'label' => (string) $spec['extract']['label'] ) + $detail ) );
			// La clave propia del paquete ya no hace falta: sólo la frase lo abre.
			Digitalisimo_Backups_Site_Extract::forget_keys( (string) ( $spec['security']['key_id'] ?? '' ) );
			return self::history_item( wp_basename( $final ) );
		}
		self::remember( $final, 'local', 'network' === ( $spec['scope'] ?? '' ) ? 'network' : 'installation', array( 'id' => (string) $spec['policy'], 'type' => 'full' ), array(), (int) ( $spec['site_id'] ?? 0 ), false, $extra );
		return self::history_item( wp_basename( $final ) );
	}
	/**
	 * @internal Avisos visibles del trabajo (T2: la base en varias instantáneas, archivos que faltaron, la memoria que
	 * necesitará restaurarlo) y la coherencia de su base de datos, en la fila del historial.
	 */
	public static function job_record_notices( $file, array $job ) {
		$notices = array();
		foreach ( (array) ( $job['warnings'] ?? array() ) as $warning ) if ( is_array( $warning ) && ! empty( $warning['notice'] ) ) $notices[ (string) $warning['notice'] ] = array( 'code' => (string) $warning['notice'], 'text' => (string) ( $warning['text'] ?? '' ) );
		$notices = array_values( $notices );
		$changes = array( 'consistency' => (string) ( $job['checkpoint']['database']['mode'] ?? '' ) );
		if ( $notices ) $changes['notices'] = $notices;
		self::history_update( $file, $changes );
	}
	/** Texto de los avisos de una fila del historial; vacío si no tiene. */
	public static function history_notices_text( $item ) {
		return trim( implode( ' ', array_map( function( $notice ) { return (string) ( $notice['text'] ?? '' ); }, (array) ( $item['notices'] ?? array() ) ) ) );
	}
	/**
	 * @internal Fase «prune»: «sólo en el remoto» (el ZIP local fuera, si su copia remota está verificada) y retención de
	 * la política o la global, sólo después de un respaldo verificado. Las copias remotas que la retención retira se
	 * anotan aquí y se borran después, fuera del cerrojo (job_remote_retention_pending()).
	 */
	public static function job_prune( array $spec ) {
		$dropped = self::job_remote_only( $spec );
		if ( 'policy' === ( $spec['retention'] ?? '' ) ) self::prune_policy( array( 'id' => (string) $spec['policy'], 'retain' => (int) $spec['retain'] ) );
		elseif ( 'global' === ( $spec['retention'] ?? '' ) ) self::prune( self::local_directory(), (int) $spec['retain'] );
		return $dropped;
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
	/**
	 * Copia remota de una fila del historial: destino, estado (ok, pending, failed, cancelled o legacy) y su referencia.
	 * Desde el lote corto la fila es la del ZIP local y la copia remota va en `transfer_destination`; de 1.0.59 a 1.0.61
	 * la transferencia cambiaba `destination` al proveedor, y antes de 1.0.59 no había `transfer` (legacy: sin
	 * identificador si tampoco hay `remote`). null si la fila no tiene copia remota.
	 */
	private static function remote_copy( $item ) {
		$item = (array) $item;
		$target = (string) ( $item['transfer_destination'] ?? '' );
		$recorded = (string) ( $item['destination'] ?? 'local' );
		if ( '' === $target && 'local' !== $recorded ) $target = $recorded;
		if ( '' === $target || 'local' === $target ) return null;
		$remote = (array) ( $item['remote'] ?? array() );
		$state = (string) ( $item['transfer'] ?? '' );
		if ( '' === $state ) $state = ! empty( $remote['provider'] ) ? 'ok' : 'legacy';
		return array( 'destination' => $target, 'state' => $state, 'remote' => $remote );
	}
	/** Ruta del ZIP local de una fila del historial ('' si ya no está en el almacén). */
	private static function local_copy( $item ) {
		$file = (string) ( ( (array) $item )['file'] ?? '' );
		if ( '' === $file ) return '';
		try { return self::local_path( $file ); } catch ( Exception $e ) { return ''; }
	}
	/** Restaurable desde el almacén: completo, de la instalación o la red, y con su ZIP local (tenga o no copia remota). */
	private static function restorable_item( $item, $local = null ) {
		$item = (array) $item;
		$local = null === $local ? '' !== self::local_copy( $item ) : (bool) $local;
		return $local && 'full' === (string) ( $item['backup_type'] ?? 'full' ) && in_array( (string) ( $item['scope'] ?? '' ), array( 'network', 'installation' ), true );
	}
	/**
	 * Copias que existen de una fila: la local (si el ZIP sigue en el almacén) y la remota verificada (`ok`) o anterior
	 * a 1.0.59 sin identificador (`legacy`). Una copia pendiente, fallida o cancelada no existe: va en `transfer`.
	 */
	private static function item_copies( $item, $local = null ) {
		$local = null === $local ? '' !== self::local_copy( $item ) : (bool) $local;
		$copies = $local ? array( array( 'destination' => 'local', 'state' => 'ok' ) ) : array();
		$remote = self::remote_copy( $item );
		if ( $remote && in_array( $remote['state'], array( 'ok', 'legacy' ), true ) ) $copies[] = array( 'destination' => $remote['destination'], 'state' => $remote['state'] );
		return $copies;
	}
	/** Si la fila tiene una copia remota que existe (verificada o anterior sin identificador). */
	private static function remote_exists( $item ) {
		$remote = self::remote_copy( $item );
		return $remote && in_array( $remote['state'], array( 'ok', 'legacy' ), true );
	}
	private static function bytes_label( $bytes ) { return function_exists( 'size_format' ) ? (string) size_format( (int) $bytes ) : round( $bytes / 1048576, 1 ) . ' MB'; }
	/**
	 * @internal Raíz, wp-config.php, almacén y archivos SQLite activos para el inventario por carpetas de la fase «pack»
	 * (T2); las mismas comprobaciones que add_full_files().
	 */
	public static function job_files_context() {
		$root = realpath( ABSPATH );
		$content = realpath( WP_CONTENT_DIR );
		if ( false === $root || false === $content ) throw new Exception( 'No se pudo resolver la ruta de WordPress o wp-content.' );
		$root = trailingslashit( wp_normalize_path( $root ) );
		$content = wp_normalize_path( $content );
		if ( 0 !== strpos( trailingslashit( $content ), $root ) ) throw new Exception( 'wp-content está fuera de ABSPATH; se necesita incluirlo explícitamente antes de crear un respaldo completo.' );
		return array( 'root' => $root, 'config' => self::wordpress_config_file(), 'backup' => trailingslashit( wp_normalize_path( realpath( self::local_directory() ) ) ), 'sqlite' => self::active_sqlite_database_files(), 'exclusions' => Digitalisimo_Backups_Exclusions::rules( $root, $content ) );
	}
	/** Inventario estricto de la instalación, sin omitir archivos sin avisar. */
	private static function add_full_files( $zip, $backup_dir, &$entries ) {
		// Las mismas comprobaciones que el inventario por tramos (una sola copia: job_files_context()).
		$context = self::job_files_context();
		$root = $context['root'];
		$config = $context['config'];
		$backup_dir = trailingslashit( wp_normalize_path( realpath( $backup_dir ) ) );
		$active_database_files = $context['sqlite'];
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY );
		$count = 0;
		foreach ( $iterator as $entry ) {
			$path = wp_normalize_path( $entry->getPathname() );
			if ( 0 === strpos( $path, $backup_dir ) ) continue;
			if ( isset( $active_database_files[ $path ] ) ) continue; // database.sql es la copia lógica consistente.
			if ( $root . '.maintenance' === $path ) continue; // El marcador de mantenimiento de una actualización en curso.
			// B8: otros plugins de respaldo, la caché de páginas y los registros, como el inventario por tramos.
			if ( Digitalisimo_Backups_Exclusions::excluded( substr( $path, strlen( $root ) ), false, (array) $context['exclusions'] ) ) continue;
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
				// Ningún nombre del paquete sale de su raíz: sin segmentos «.» ni «..» (vendrían de datos del sitio, no del disco).
				if ( preg_match( '#(?:^|/)\.{1,2}(?:/|$)#', $name ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Un nombre del sitio extraído sale de su carpeta: ' . $name . '.' );
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
		self::private_file( $file );
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
	/**
	 * Comprueba presencia, lectura completa y CRC de todos los miembros del archivo y, con un manifiesto v3, el tamaño y el
	 * SHA-256 de cada uno. Memoria constante (H1): el manifiesto se lee por partes a un índice en disco
	 * (Digitalisimo_Backups_Manifest_Index) y los duplicados se reconocen con la tabla de nombres de libzip; antes se
	 * decodificaba entero y se guardaban todos los nombres, que con 64 MB no cabía en un sitio de ~17 000 archivos. Los
	 * mensajes y su orden son los de siempre. $on_entry( $i, $name, $chunk ) recibe los bytes de cada entrada.
	 */
	private static function verify_zip_contents( $zip, $on_entry = null ) {
		$count = (int) $zip->numFiles;
		$head = null;
		$manifest_error = null;
		$has_manifest = false !== $zip->locateName( 'manifest.json' );
		if ( $has_manifest ) {
			try {
				$head = Digitalisimo_Backups_Manifest_Index::build( $zip, null, false );
			} catch ( Exception $e ) {
				$manifest_error = $e;
			}
		}
		$index = $head['index'] ?? null;
		try {
			$observed = 0;
			$total = 0;
			$mismatch = null;
			$required = array( 'database.sql', 'wordpress/wp-config.php', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' );
			$present = array();
			for ( $i = 0; $i < $count; $i++ ) {
				// Al recuperar la copia previa, el sitio sigue en mantenimiento mientras se lee entera (no hace nada si no lo está).
				Digitalisimo_Backups_Maintenance::heartbeat();
				if ( 0 === $i % 25 ) self::manual_progress( 81 + (int) floor( ( self::$verify_top - 81 ) * $i / max( 1, $count ) ), 'verificación', 'Verificando archivo ' . ( $i + 1 ) . ' de ' . $count );
				$stat = $zip->statIndex( $i );
				if ( ! $stat || ! isset( $stat['name'], $stat['size'], $stat['crc'] ) ) throw new Exception( 'Una entrada ZIP no tiene metadatos completos.' );
				$name = $stat['name'];
				if ( $zip->locateName( $name ) !== $i ) throw new Exception( 'El ZIP contiene nombres de archivo duplicados.' );
				if ( '/' === substr( $name, -1 ) ) continue;
				$actual = self::hash_zip_entry( $zip, $i, $name, $on_entry );
				if ( $actual['size'] !== (int) $stat['size'] || $actual['crc'] !== sprintf( '%08x', $stat['crc'] ) ) throw new Exception( 'Falló la integridad de ' . $name . ' dentro del ZIP.' );
				if ( 'manifest.json' === $name ) continue;
				$observed++;
				$total += $actual['size'];
				if ( in_array( $name, $required, true ) ) $present[ $name ] = true;
				if ( null === $mismatch && $index ) {
					$expected = $index->get( $i );
					if ( ! is_array( $expected ) || $expected['size'] !== $actual['size'] || ! hash_equals( $expected['sha256'], $actual['sha256'] ) ) $mismatch = $name;
				}
			}
			if ( false === $zip->locateName( 'database.sql' ) || ( ! $has_manifest && false === $zip->locateName( 'migration.json' ) ) ) throw new Exception( 'Falta SQL o metadata obligatoria en el ZIP.' );
			if ( ! $has_manifest ) return;
			if ( $manifest_error ) {
				if ( false !== strpos( $manifest_error->getMessage(), 'tamaño permitido' ) ) throw $manifest_error;
				throw new Exception( 'El manifiesto del ZIP no es JSON válido.' );
			}
			$manifest = $head['data'];
			if ( ! in_array( $manifest['format'] ?? '', array( '', 'digitalisimo-backup/v2', 'digitalisimo-backup/v3' ), true ) ) throw new Exception( 'El formato del manifiesto no es compatible.' );
			if ( 'digitalisimo-backup/v3' !== ( $manifest['format'] ?? '' ) ) return;
			// v3: la instalación (o red) completa, o un sitio extraído de una red (sin el wp-config.php de la red).
			$extracted = Digitalisimo_Backups_Site_Extract::SCOPE === ( $manifest['scope'] ?? '' );
			if ( 3 !== ( $manifest['backup_format_version'] ?? null ) || ( 'installation' !== ( $manifest['scope'] ?? '' ) && ! $extracted ) || 'full' !== ( $manifest['type'] ?? '' ) || null === $head['entries'] || (int) $head['entries'] !== $observed ) throw new Exception( 'El manifiesto completo no coincide con el ZIP.' );
			if ( $extracted && ( ! empty( $manifest['multisite'] ) || ! is_array( $manifest['extract'] ?? null ) ) ) throw new Exception( 'El manifiesto del sitio extraído no coincide con el ZIP.' );
			// Un sitio extraído pasa además el inspector estricto de entradas: sólo las raíces de un respaldo, sin «.» ni «..».
			if ( $extracted ) Digitalisimo_Backups_Archive_Inspector::entries( $zip, true );
			foreach ( $extracted ? Digitalisimo_Backups_Site_Extract::required_entries() : $required as $file ) if ( $extracted ? false === $zip->locateName( $file ) : ! isset( $present[ $file ] ) ) throw new Exception( 'Falta el archivo obligatorio ' . $file . ' en el respaldo completo.' );
			if ( (int) ( $manifest['file_count'] ?? -1 ) !== $observed - 1 || (int) ( $manifest['uncompressed_size'] ?? -1 ) !== $total ) throw new Exception( 'El tamaño o el número de archivos no coincide con el manifiesto.' );
			if ( null !== $mismatch ) throw new Exception( 'Falló el SHA-256 de ' . $mismatch . ' en el manifiesto.' );
		} finally {
			if ( $index ) $index->close();
		}
	}
	/** Hasta este tamaño una entrada se lee del ZIP ya abierto (ver zip_entry_reader()). */
	const ZIP_READ_INLINE = 262144;
	/**
	 * @internal Flujo de lectura de una entrada del ZIP abierto. Hasta 256 KiB se lee del archivo ya abierto: `getStream()`
	 * de PHP 7.4 a 8.1 vuelve a abrir el ZIP y relee su directorio central en cada llamada, así que recorrer decenas de
	 * miles de entradas era cuadrático (40 008 entradas: más de 15 minutos en PHP 7.4 frente a 96 s en 8.5). Lo leído vive
	 * a la vez en la cadena, en el flujo temporal y en el trozo que lee quien llama: con 256 KiB son menos de 1 MiB, que
	 * cabe en la reserva de memoria de la restauración y de T2 (4 MiB); las entradas mayores, pocas, van por
	 * `getStream()` como siempre. Se piden `$size + 1` bytes: si llega más de lo declarado (datos de más tras el tamaño
	 * del directorio central) o menos (truncada), y siempre con una entrada declarada vacía, también `getStream()`, que lo
	 * detecta con sus mensajes de siempre. Un CRC erróneo lo rechazan quienes llaman, que comparan su propio CRC32 y
	 * SHA-256 (`getFromName()` de PHP 7.4 y 8.2 no lo comprueba; el de 8.5, sí, y entonces también va por `getStream()`).
	 */
	public static function zip_entry_reader( $zip, $name, $size ) {
		$size = (int) $size;
		if ( $size > 0 && $size <= self::ZIP_READ_INLINE ) {
			$data = @$zip->getFromName( (string) $name, $size + 1 );
			if ( is_string( $data ) && strlen( $data ) === $size ) {
				$stream = fopen( 'php://temp/maxmemory:' . ( self::ZIP_READ_INLINE + 1 ), 'w+b' );
				$written = $stream ? fwrite( $stream, $data ) : false;
				unset( $data );
				if ( $stream && $size === (int) $written && rewind( $stream ) ) return $stream;
				if ( $stream ) fclose( $stream );
			}
		}
		return $zip->getStream( (string) $name );
	}
	/** Tamaño, CRC32 y SHA-256 de una entrada del ZIP leída por fragmentos de 1 MiB. */
	private static function hash_zip_entry( $zip, $i, $name, $on_entry = null ) {
		$entry = $zip->statIndex( $i );
		$stream = self::zip_entry_reader( $zip, $name, is_array( $entry ) && $entry['name'] === $name ? (int) $entry['size'] : -1 );
		if ( ! $stream ) throw new Exception( 'No se pudo leer ' . $name . ' del ZIP.' );
		$bytes = 0;
		$crc = hash_init( 'crc32b' );
		$sha = hash_init( 'sha256' );
		try {
			while ( ! feof( $stream ) ) {
				$chunk = fread( $stream, 1048576 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) throw new Exception( 'La entrada ' . $name . ' está truncada.' );
				$bytes += strlen( $chunk );
				hash_update( $crc, $chunk );
				hash_update( $sha, $chunk );
				if ( $on_entry && '' !== $chunk ) call_user_func( $on_entry, $i, $name, $chunk );
			}
		} finally {
			fclose( $stream );
		}
		return array( 'size' => $bytes, 'crc' => hash_final( $crc ), 'sha256' => hash_final( $sha ) );
	}

	private static function site_tables( $prefix ) { global $wpdb; $like = $wpdb->esc_like( $prefix ) . '%'; $tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); foreach ( array( $wpdb->base_prefix . 'users', $wpdb->base_prefix . 'usermeta' ) as $table ) if ( ! in_array( $table, $tables, true ) ) $tables[] = $table; return self::exportable_tables( $tables ); } // Nunca las tablas temporales o viejas de una restauración (revisión, P6): el sitio principal de una red tiene el prefijo de ellas.
	private static function database_sql( $tables = null, $source_prefix = '', $target_prefix = '' ) {
		global $wpdb;
		if ( null === $tables ) $tables = self::exportable_tables( $wpdb->get_col( 'SHOW TABLES' ) );
		if ( ! is_array( $tables ) || ! $tables ) throw new Exception( 'No se pudo obtener la lista de tablas de la base de datos.' );
		$sql = "-- Digitalisimo WordPress backup\n-- " . gmdate( 'c' ) . "\nSET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n";
		// Con un prefijo de origen vacío ($table_prefix = '' en wp-config.php) todas las tablas lo llevan: strncmp() con
		// longitud 0 lo acepta en cualquier PHP; strpos() con aguja vacía da false en PHP 7.4.
		foreach ( $tables as $table ) {
			$output = $target_prefix && 0 === strncmp( $table, $source_prefix, strlen( $source_prefix ) ) ? $target_prefix . substr( $table, strlen( $source_prefix ) ) : $table;
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
		// El SQL lleva toda la base de datos: sólo el usuario de PHP lo lee, también mientras se escribe.
		self::private_file( $path );
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
				// Un sitio extraído lee de usuarios y metas sólo las filas de sus usuarios.
				$where = $transform ? $transform->where( $table ) : null;
				if ( null !== $where ) $query .= ' WHERE ' . $where;
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
	/** @internal Ningún respaldo contiene la clave que lo protege: las filas digitalisimo_backups_vault_* no se exportan. */
	public static function secret_row( $table, $row ) {
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
	public static function export_row( $table, $row ) {
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

	public static function sql_value( $value ) { if ( null === $value ) return 'NULL'; return "'" . str_replace( array( '\\', "\0", "\n", "\r", "\x1a", "'" ), array( '\\\\', '\\0', '\\n', '\\r', '\\Z', "\\'" ), (string) $value ) . "'"; }
	/**
	 * Protección del almacén por si alguna vez quedara bajo una ruta servida (nunca debería: local_directory() lo
	 * rechaza): `index.php` vacío y `.htaccess` con «Deny from all». Se escriben sólo si faltan o su contenido no es el
	 * correcto, con temporal y rename() y en 0600: reescribir un `.htaccess` hace que algunos hostings recarguen el
	 * servidor web de todos los sitios (OpenLiteSpeed en Webuzo, cada 2 minutos), y una recarga puede cortar una
	 * restauración en curso.
	 */
	private static function protect( $dir ) {
		foreach ( array( 'index.php' => "<?php // Silence is golden.\n", '.htaccess' => "Deny from all\n" ) as $name => $content ) {
			$path = trailingslashit( $dir ) . $name;
			if ( is_link( $path ) ) continue;
			if ( is_file( $path ) && (string) @file_get_contents( $path ) === $content ) { self::private_file( $path ); continue; }
			$temp = $path . '.tmp-' . bin2hex( random_bytes( 4 ) );
			if ( strlen( $content ) === @file_put_contents( $temp, $content ) ) { self::private_file( $temp ); if ( ! @rename( $temp, $path ) ) @unlink( $temp ); }
			else @unlink( $temp );
		}
	}
	private static function remember( $file, $destination, $scope = 'site', $policy = array(), $remote = array(), $site_id = 0, $locked = false, $extra = array() ) {
		// El SHA-256 de todo el ZIP se calcula antes de leer el historial, así que lo que cambie otra petición mientras tanto cuenta.
		$checksum = hash_file( 'sha256', $file );
		if ( false === $checksum ) throw new Exception( 'No se pudo calcular el SHA-256 del respaldo terminado.' );
		$name = wp_basename( $file );
		$row = array( 'date' => current_time( 'mysql' ), 'file' => $name, 'size' => filesize( $file ), 'sha256' => $checksum, 'destination' => $destination, 'scope' => $scope, 'site_id' => $site_id ?: get_current_blog_id(), 'policy' => $policy['id'] ?? 'manual', 'backup_type' => $policy['type'] ?? ( false !== strpos( $name, '-db-' ) ? 'database' : 'full' ), 'remote' => (array) $remote, 'locked' => $locked ) + Digitalisimo_Backups_Security::describe( $file ) + array( 'origin' => 'created' ) + (array) $extra;
		// Una fila que entra de fuera (imported_row_fields(): sincronizar un destino, importar, subir) lo dice siempre y
		// nunca lleva la identidad de esta instalación: la retención no borra su copia remota.
		if ( 'imported' === ( $extra['origin'] ?? '' ) ) { $row['origin'] = 'imported'; unset( $row['installation'] ); }
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
	private static function prune( $dir, $keep ) { Digitalisimo_Backups_Job_Store::with_lock( function() use ( $dir, $keep ) { self::prune_locked( $dir, $keep ); self::retire_remote_global_locked( $keep ); } ); }
	private static function prune_policy( $policy ) { Digitalisimo_Backups_Job_Store::with_lock( function() use ( $policy ) { self::prune_policy_locked( $policy ); } ); }
	/**
	 * Retención global, con el cerrojo del historial: un «Bloquear» llega antes de la retención o después, nunca en medio.
	 * Sólo cuenta y borra respaldos con fila en el historial: un archivo «sin registro» (una subida o uno copiado al
	 * almacén por SSH) nunca ocupa un hueco de la retención ni se borra solo; se elimina a mano desde «Restaurar».
	 */
	private static function prune_locked( $dir, $keep ) {
		$busy = self::busy_backup_files();
		if ( null === $busy ) return;
		$history = self::fresh_history();
		$registered = array();
		$rank = array();
		foreach ( $history as $position => $item ) {
			if ( '' === (string) ( $item['file'] ?? '' ) ) continue;
			$rank[ wp_basename( (string) $item['file'] ) ] = $position;
			// Los respaldos previos a actualizar (I1) tienen su propia retención y no desplazan los del usuario.
			if ( empty( $item['locked'] ) && Digitalisimo_Backups_Pre_Update::POLICY !== (string) ( $item['policy'] ?? '' ) ) $registered[ wp_basename( (string) $item['file'] ) ] = true;
		}
		$files = array_merge( glob( trailingslashit( $dir ) . 'digitalisimo-*.zip' ) ?: array(), glob( trailingslashit( $dir ) . 'digitalisimo-*.dgbk' ) ?: array() );
		// Del más reciente al más antiguo; con la misma fecha (varios en el mismo segundo), el orden del historial.
		usort( $files, function( $a, $b ) use ( $rank ) { return array( filemtime( $b ), $rank[ wp_basename( $a ) ] ?? PHP_INT_MAX ) <=> array( filemtime( $a ), $rank[ wp_basename( $b ) ] ?? PHP_INT_MAX ); } );
		$rotating = array_values( array_filter( $files, function( $file ) use ( $registered ) { return isset( $registered[ wp_basename( $file ) ] ); } ) );
		foreach ( array_slice( $rotating, $keep ) as $file ) if ( ! isset( $busy[ wp_basename( $file ) ] ) ) @unlink( $file );
	}
	private static function prune_policy_locked( $policy ) { $busy = self::busy_backup_files(); if ( null === $busy ) return; $history = self::fresh_history(); $matching = array_values( array_filter( $history, function( $item ) use ( $policy ) { return ( $item['policy'] ?? '' ) === $policy['id'] && empty( $item['locked'] ); } ) ); $retired = array(); foreach ( array_slice( $matching, (int) $policy['retain'] ) as $item ) { $name = sanitize_file_name( $item['file'] ?? '' ); if ( isset( $busy[ $name ] ) ) continue; $retired[ (string) ( $item['file'] ?? '' ) ] = true; $path = trailingslashit( self::local_directory() ) . $name; if ( is_file( $path ) ) @unlink( $path ); } self::retire_remote_locked( $retired ); }
	/**
	 * ZIP que un trabajo sin terminar todavía necesita (el verificado que espera su copia remota, con la transferencia en
	 * espera de reintento): la retención de otro trabajo nunca los borra; cuentan para el tope y se retiran en una poda
	 * posterior. Sin poder leer la cola, la retención no borra nada (null).
	 */
	private static function busy_backup_files() {
		$busy = array();
		try {
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) {
				if ( ! empty( $job['spec']['file'] ) ) $busy[ wp_basename( (string) $job['spec']['file'] ) ] = true;
				// Una restauración también necesita su copia previa (la vuelta a ella la restaura).
				if ( Digitalisimo_Backups_Jobs::is_restore( $job ) && '' !== self::restore_safety_file( $job ) ) $busy[ wp_basename( self::restore_safety_file( $job ) ) ] = true;
			}
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
		// Abrir la pantalla tras actualizar ya deja en 0600 lo que dejó una versión anterior (H17), sin esperar a operar.
		self::private_storage_files( $dir );
		// Con su ZIP en el almacén, también los que tienen copia remota (verificada, pendiente o fallida) y las filas antiguas.
		$local = array_filter( (array) $history, function( $item ) { return self::restorable_item( $item ); } );
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
		if ( $local ) { echo '<optgroup label="Historial">'; foreach ( $local as $item ) { $hint = self::relocation_hint( $dir . $item['file'], $here ); echo '<option value="' . esc_attr( $item['file'] ) . '" data-relocation="' . esc_attr( $hint['text'] ) . '">' . esc_html( $item['date'] . ' · ' . $item['file'] . Digitalisimo_Backups_Security::restore_label( $dir . $item['file'], $item ) . $hint['label'] . ( '' !== self::history_notices_text( $item ) ? ' · con avisos' : '' ) ) . '</option>'; } echo '</optgroup>'; }
		if ( $loose ) { echo '<optgroup label="Archivos del almacén sin registro">'; foreach ( array_slice( $loose, 0, 100 ) as $file ) { $hint = self::relocation_hint( $dir . $file, $here ); echo '<option value="' . esc_attr( $file ) . '" data-relocation="' . esc_attr( $hint['text'] ) . '">' . esc_html( $file . ' · sin registro' . Digitalisimo_Backups_Security::restore_label( $dir . $file ) . $hint['label'] ) . '</option>'; } echo '</optgroup>'; }
		echo '</select></p><p class="description" id="digitalisimo-backups-relocation" aria-live="polite">Si el respaldo viene de otra dirección, de otra carpeta del servidor o con otro prefijo de tablas, se reescriben al restaurar: las URL (también en datos serializados, JSON y URL codificadas, con y sin www, http o https), las rutas del servidor, el prefijo de tablas y las claves que lo llevan y, en una red, el dominio y la ruta de cada sitio. No se tocan la columna guid de las entradas ni los correos.</p>';
		echo '<fieldset><legend>Direcciones del sitio después de restaurar</legend><p><label><input type="radio" name="addresses" value="current" checked> Conservar las de este sitio (' . esc_html( $here['home'] ) . '): las del respaldo se cambian por éstas</label><br><label><input type="radio" name="addresses" value="backup"> Conservar las del respaldo: el sitio quedará en su dirección original (las rutas del servidor y el prefijo siguen siendo los de este servidor)</label></p></fieldset>';
		echo '<p><label>Frase o clave del respaldo <input type="password" name="backup_secret" value="" autocomplete="off"></label><br><span class="description">Sólo para un respaldo cifrado o firmado con una clave que este sitio no tiene (por ejemplo, al restaurar en otro servidor). No se guarda salvo que marques la casilla.</span></p><p><label><input type="checkbox" name="backup_secret_adopt" value="1"> Usar esta frase para los respaldos nuevos</label></p><p><label>Confirmación <input type="text" name="confirmation" placeholder="RESTAURAR" autocomplete="off"></label></p><p><button class="button button-primary">Restaurar respaldo seleccionado</button></p></form>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var s=document.getElementById("digitalisimo-backups-restore-file"),p=document.getElementById("digitalisimo-backups-relocation");if(!s||!p)return;var d=p.textContent;s.addEventListener("change",function(){var o=s.options[s.selectedIndex];p.textContent=o&&o.dataset.relocation?o.dataset.relocation:d})})</script>';
		if ( $loose ) {
			// Sin fila no cuentan para la retención (prune_locked()): se eliminan a mano.
			echo '<h3>Archivos del almacén sin registro</h3><p class="description">Subidos desde el navegador o copiados al almacén a mano. La retención nunca los cuenta ni los borra: elimínalos aquí cuando ya no los necesites.</p><table class="widefat striped"><thead><tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th><th></th></tr></thead><tbody>';
			foreach ( array_slice( $loose, 0, 100 ) as $file ) {
				echo '<tr><td>' . esc_html( $file ) . '</td><td>' . esc_html( size_format( (int) @filesize( $dir . $file ) ) ) . '</td><td>' . esc_html( gmdate( 'Y-m-d H:i', (int) @filemtime( $dir . $file ) ) ) . ' UTC</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Eliminar este archivo del almacén?\')">';
				wp_nonce_field( self::ACTION_DELETE_LOOSE );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE_LOOSE ) . '"><input type="hidden" name="file" value="' . esc_attr( $file ) . '">' . ( self::network_context() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small">Eliminar</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}
		self::remote_panel();
		self::relocation_card();
		echo '</section>';
	}
	/**
	 * Elimina un archivo del almacén que no tiene fila en el historial (una subida o uno copiado a mano), con capability,
	 * nonce y, en una red, desde el sitio principal. Nunca toca un respaldo registrado (eso es «Eliminar» del historial).
	 */
	public static function delete_loose() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_DELETE_LOOSE );
		if ( is_multisite() && ! is_main_site() ) self::redirect( false, 'Los archivos del almacén se gestionan desde el sitio principal o la administración de red.' );
		$file = sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) );
		// También un paquete de un sitio extraído copiado al almacén a mano (H15 del ensayo E2): «Restaurar» lo lista aquí.
		if ( ! preg_match( '/^(?:digitalisimo-[A-Za-z0-9._-]+\.(?:zip|dgbk)|safety-before-restore-[A-Za-z0-9._-]+\.zip|' . preg_quote( Digitalisimo_Backups_Site_Extract::FILE_PREFIX, '/' ) . '[A-Za-z0-9._-]+\.(?:zip|dgbk))$/', $file ) ) self::redirect( false, 'Ese archivo no es un respaldo del almacén.' );
		if ( self::history_item( $file ) ) self::redirect( false, 'Ese respaldo está en el historial: elimínalo desde «Últimos respaldos».' );
		try {
			$path = self::local_path( $file );
			if ( '' === $path ) self::redirect( false, 'El archivo ya no está en el almacén.' );
			$removed = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $path, $file ) {
				// Con el cerrojo del historial: si entretanto se registró, ya no es «sin registro».
				if ( self::history_item( $file ) ) return false;
				return @unlink( $path ) || ! file_exists( $path );
			} );
		} catch ( Exception $e ) {
			self::redirect( false, $e->getMessage() );
		}
		if ( ! $removed ) self::redirect( false, 'No se pudo eliminar el archivo del almacén.' );
		self::redirect( true, 'Archivo eliminado del almacén: ' . $file . '.' );
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
		// Recuperar un sitio a medio cambiar: otra vuelta a la copia previa (revisión, C2).
		if ( ! empty( $_POST['rollback_retry'] ) ) {
			$result = self::service_restore_rollback( array( 'network' => is_multisite(), 'drive_cli' => false ) );
			if ( is_wp_error( $result ) ) self::redirect( false, 'No se pudo volver a la copia previa: ' . $result->get_error_message() );
			if ( ! empty( $result['restoring'] ) ) {
				Digitalisimo_Backups_Restore_Maintenance::send_cookie( (string) $result['maintenance_token'] );
				self::redirect( true, $result['notice'] );
			}
			self::redirect( true, (string) $result['message'] );
		}
		$confirmation = strtoupper( trim( preg_replace( '/\s+/', ' ', (string) wp_unslash( $_POST['confirmation'] ?? '' ) ) ) );
		if ( ! in_array( $confirmation, array( 'RESTAURAR', 'RESTAURAR SIN FIRMA' ), true ) ) self::redirect( false, 'Escribe RESTAURAR para confirmar una operación destructiva.' );
		if ( empty( $_POST['updraft'] ) ) {
			// Mismo caso de uso que REST y WP-CLI. En una red, esta pantalla restaura la red entera. La frase se usa tal
			// cual: sanitize_text_field() la alteraría.
			$secret = isset( $_POST['backup_secret'] ) && is_string( $_POST['backup_secret'] ) ? (string) wp_unslash( $_POST['backup_secret'] ) : '';
			$addresses = 'backup' === sanitize_key( wp_unslash( $_POST['addresses'] ?? '' ) ) ? 'backup' : 'current';
			// La restauración es un trabajo por pasos: esta petición avanza lo que cabe en su tiempo y la pantalla (o WP-Cron)
			// la termina; la cookie del testigo deja a este navegador verla durante el mantenimiento.
			$result = self::service_restore( wp_unslash( $_POST['backup_file'] ?? '' ), array( 'confirm' => $confirmation, 'network' => is_multisite(), 'secret' => $secret, 'adopt' => ! empty( $_POST['backup_secret_adopt'] ), 'addresses' => $addresses, 'via' => 'admin-post', 'sync' => false, 'drive_cli' => false ) );
			if ( is_wp_error( $result ) ) self::redirect( false, 'La restauración se detuvo: ' . $result->get_error_message() );
			if ( ! empty( $result['restoring'] ) ) {
				Digitalisimo_Backups_Restore_Maintenance::send_cookie( (string) $result['maintenance_token'] );
				self::redirect( true, 'Restauración en curso: sigue por pasos y esta pantalla muestra su avance en «Trabajos». Con la pantalla abierta avanza sin pausa; si la cierras, la siguen WP-Cron (las visitas al sitio en mantenimiento lo despiertan) o el cron del sistema; en un sitio sin visitas ni cron, se detiene hasta que vuelvas aquí o ejecutes wp digitalisimo-backups jobs run. Si se interrumpe, se reanuda o vuelve sola a la copia previa ' . (string) $result['safety_backup'] . '.' );
			}
			self::redirect( true, 'Restauración terminada. Entra de nuevo y revisa enlaces permanentes.' . ( '' !== $result['notice'] ? ' ' . $result['notice'] : '' ) );
		}
		// UpdraftPlus reemplaza también la instalación entera: en una red, sólo desde el sitio principal de la red principal.
		$allowed = self::service_scope( is_multisite(), true );
		if ( is_wp_error( $allowed ) ) self::redirect( false, 'La restauración se detuvo: ' . $allowed->get_error_message() );
		$set = sanitize_text_field( wp_unslash( $_POST['updraft_set'] ?? '' ) );
		$mail = Digitalisimo_Backups_Notify::snapshot();
		$event = null;
		try {
			$operation_lock = self::acquire_operation_lock();
			try {
				if ( Digitalisimo_Backups_Jobs::has_active() ) throw new Exception( Digitalisimo_Backups_Jobs::restore_active() ? 'Ya hay una restauración en curso. Espera a que termine.' : 'Hay un respaldo pendiente o en curso. Cancélalo en «Trabajos» o espera a que termine antes de restaurar.' );
				Digitalisimo_Backups_Security::require_confirmation( array( 'trust' => 'untrusted', 'auth' => 'unsigned' ), $confirmation );
				try {
					self::restore_updraft_set( $set );
					$event = array( 'ok' => true, 'started' => true );
				} catch ( Exception $failure ) {
					$event = array( 'ok' => false, 'started' => true, 'message' => $failure->getMessage() );
					throw $failure;
				}
			} finally {
				self::release_operation_lock( $operation_lock );
				// El SQL restaurado trae su propia opción `cron`: un trabajo encolado durante la restauración recupera su tick.
				try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
				if ( $event ) {
					try { Digitalisimo_Backups_Notify::restore( $mail, $event['ok'], $event + array( 'file' => $set, 'kind' => 'updraft', 'via' => 'admin-post' ) ); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
				}
			}
		} catch ( Exception $e ) { self::redirect( false, 'La restauración se detuvo: ' . $e->getMessage() ); }
		self::redirect( true, 'Restauración terminada. Entra de nuevo y revisa enlaces permanentes.' );
	}
	private static function local_path( $file ) { $file = sanitize_file_name( $file ); if ( '' === $file || '.' === $file[0] ) return ''; $root = realpath( self::local_directory() ); if ( ! $root || is_link( trailingslashit( $root ) . $file ) ) return ''; $path = realpath( trailingslashit( $root ) . $file ); return $path && dirname( wp_normalize_path( $path ) ) === wp_normalize_path( $root ) && is_file( $path ) ? $path : ''; }
	/**
	 * Restaura un ZIP propio en este proceso, hasta terminar: el mismo trabajo de restauración por pasos que la pantalla,
	 * REST y WP-CLI (Digitalisimo_Backups_Restore_Job), con sus ticks seguidos y sin reintentos (un fallo tras empezar el
	 * cambio vuelve enseguida a la copia previa). $report es el de Digitalisimo_Backups_Security::open(); sin él se abre
	 * aquí. Todo lo que puede rechazar el paquete se comprueba antes de la copia previa y de tocar el sitio. Devuelve el
	 * informe de la reubicación (o null si no hizo falta); lanza Exception con el mensaje de siempre si falla.
	 * `$options['addresses']`: `current` (por defecto) o `backup`.
	 */
	private static function restore_digitalisimo_zip( $path, $report = null, array $options = array() ) {
		if ( ! class_exists( 'ZipArchive' ) ) throw new Exception( 'El servidor no tiene ZipArchive.' );
		$owned = null === $report;
		if ( $owned ) $report = Digitalisimo_Backups_Security::open( $path, self::history_item( wp_basename( $path ) ) );
		$copies = self::service_safety_copies();
		try {
			$job = self::restore_enqueue( $report, array( 'addresses' => $options['addresses'] ?? 'current', 'via' => (string) ( $options['via'] ?? 'inline' ), 'sync' => true, 'path' => (string) $path ) );
		} finally {
			if ( $owned ) Digitalisimo_Backups_Security::close( $report );
		}
		$outcome = self::restore_outcome_raw( self::restore_drive( $job['id'], true ), $copies );
		if ( isset( $outcome['error'] ) ) throw new Exception( $outcome['error'][1] );
		if ( empty( $outcome['restored'] ) ) throw new Exception( 'La restauración no terminó en este proceso.' );
		return self::get_last_relocation_for( $job['id'] );
	}
	/** Informe completo de la reubicación que dejó la restauración $id (el de LAST_RELOCATION), o null. */
	private static function get_last_relocation_for( $id ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || empty( $job['checkpoint']['finish']['relocated'] ) ) return null;
		$saved = get_option( self::LAST_RELOCATION, null );
		return is_array( $saved ) ? $saved : null;
	}
	/** Tablas del respaldo que cambian de nombre en esta instalación (el mismo mapa que usa la importación). */
	/**
	 * Ajuste de los archivos de configuración del servidor al extraerlos (Digitalisimo_Backups_Relocate::server_file()):
	 * una ruta existirá si ya está en el disco o si el propio respaldo la trae bajo la raíz de WordPress.
	 */
	private static function server_file_transform( Digitalisimo_Backups_Relocate $plan, $zip ) {
		$roots = array_unique( array_filter( array( rtrim( wp_normalize_path( ABSPATH ), '/' ), false === realpath( ABSPATH ) ? '' : rtrim( wp_normalize_path( realpath( ABSPATH ) ), '/' ) ) ) );
		$exists = function( $path ) use ( $zip, $roots ) {
			$path = wp_normalize_path( (string) $path );
			if ( file_exists( $path ) ) return true;
			foreach ( $roots as $root ) if ( 0 === strpos( $path, $root . '/' ) && false !== $zip->locateName( 'wordpress/' . substr( $path, strlen( $root ) + 1 ) ) ) return true;
			return false;
		};
		return function( $relative, $content, $current ) use ( $plan, $exists ) { return $plan->server_file( $relative, $content, $exists, $current ); };
	}
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
		$data = self::package_metadata( $zip );
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
		// La sonda lee el origen del SQL y mide los valores que lo mencionan y no cabrían en la memoria para reescribirlos.
		$budget = Digitalisimo_Backups_Relocate::value_budget();
		$probe = new Digitalisimo_Backups_Relocate_Probe( (string) ( $data['table_prefix'] ?? '' ), array( 'budget' => $budget, 'needles' => Digitalisimo_Backups_Relocate::manifest_needles( $data ) ) );
		$guard = Digitalisimo_Backups_Sql_Guard::for_backup( $data, $trusted )->on_insert( $probe );
		// Con otro prefijo, la pasada en seco renombra igual que la importación, también en verify: lo que no se puede
		// renombrar (una vista, una tabla en un comentario ejecutable) queda anotado y el plan lo rechaza antes de la copia
		// previa, con la misma respuesta en verify y en restore. Dos tablas que chocarían, o un nombre de más de 64
		// caracteres, se rechazan aquí mismo.
		try { $renames = self::relocation_renames( $data ); } catch ( Exception $e ) { $renames = array(); } // El plan lo dice (problems()).
		if ( $renames ) $guard->rename_tables( $renames, false );
		$guard->scan_zip( $zip );
		$source = Digitalisimo_Backups_Relocate::origin_of( $data, $probe );
		$source['rename_problems'] = $guard->rename_problems();
		$source['budget'] = $budget;
		return $data;
	}
	/**
	 * Primer nivel de manifest.json (por partes y sin `entries`: memoria constante, H1) o, en un paquete anterior sin
	 * manifiesto, migration.json entero (pequeño).
	 */
	private static function package_metadata( $zip ) {
		if ( false !== $zip->locateName( 'manifest.json' ) ) {
			try {
				return Digitalisimo_Backups_Manifest_Stream::read( $zip )['data'];
			} catch ( Exception $e ) {
				throw new Exception( 'La metadata del respaldo no es válida.', 0, $e );
			}
		}
		$data = json_decode( (string) $zip->getFromName( 'migration.json' ), true );
		if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) throw new Exception( 'La metadata del respaldo no es válida.' );
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
	 * El SQL restaurado reemplaza wp_options (y sitemeta): el historial, el registro del almacén privado, el llavero y la
	 * seguridad deben reconstruirse, y las credenciales del respaldo cifrarse con las claves de este servidor. Idempotente.
	 */
	private static function after_restore_sql( $history_before, $vault_before, array $origin = array(), $kept = null, $storage = false ) {
		wp_cache_flush();
		// El almacén privado sigue siendo el de este servidor (H10), lo primero; false: quien llama no lo tomó (no se toca).
		if ( false !== $storage ) self::keep_storage_record( $storage );
		if ( is_array( $kept ) ) Digitalisimo_Backups_Site_Extract::reapply( $kept );
		update_option( self::HISTORY, $history_before, false );
		// La retención de lo traído «sin registro» (digitalisimo_backups_fetched) es de este almacén, como el historial: el
		// SQL trae la del respaldo. Vuelve la que anotó el diario justo antes del cambio.
		self::fetched_from_journal();
		// El SQL trae la carpeta del almacén del sitio de origen: esta instalación vuelve a registrar la suya (WP-CLI y el
		// cron no tienen DOCUMENT_ROOT), y el estado de la migración (fuera de todo respaldo) vuelve como estaba.
		self::remember_storage();
		Digitalisimo_Backups_Migrate::after_restore();
		Digitalisimo_Backups_Keys::restore_snapshot( $vault_before );
		self::restore_credentials( $origin );
		// La configuración propia de este sitio (H23): la que anotó el diario vuelve sobre la del respaldo, también sus
		// credenciales (cifradas con las claves de aquí, atadas a sus conexiones), y las automatizaciones a sus eventos.
		$config = self::keep_config_from_journal( $mark );
		if ( is_array( $config ) ) self::keep_config_reapply( $config, $mark );
		// El aviso tras restaurar: la configuración del respaldo que no se aplicó, o un sitio sin automatizaciones activas.
		try { self::restore_notice_after( is_array( $config ) ); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
	}
	private static function validate_zip_entries( $zip ) { Digitalisimo_Backups_Archive_Inspector::entries( $zip, true ); }
	private static function extract_digitalisimo_files( $zip, &$created_files = null, $transform = null ) {
		$real_root = realpath( ABSPATH );
		if ( false === $real_root ) throw new Exception( 'No se pudo resolver la instalación de destino.' );
		$root = trailingslashit( wp_normalize_path( $real_root ) );
		$active_database_files = self::active_sqlite_database_files();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			if ( 0 !== strpos( $name, 'wordpress/' ) || 'wordpress/wp-config.php' === $name || '/' === substr( $name, -1 ) ) continue;
			$relative = substr( $name, strlen( 'wordpress/' ) );
			if ( ! $relative || 0 === strpos( $relative, '.git/' ) ) continue;
			// El `.maintenance` que tuviera el respaldo (hecho durante una actualización) nunca sustituye al de esta restauración.
			if ( '.maintenance' === $relative ) continue;
			if ( isset( $active_database_files[ $root . $relative ] ) ) continue;
			$created_hash = self::write_entry( $zip, $i, $root, $relative, is_array( $created_files ), $transform );
			if ( null !== $created_hash ) $created_files[ $root . $relative ] = $created_hash;
		}
	}
	/**
	 * Escribe una entrada bajo $root mediante un temporal: sin seguir enlaces, sin salir de $root y sin escribir más
	 * de lo declarado (un tamaño falso no llena el disco). Devuelve el SHA-256 de un archivo nuevo si $track. Con
	 * $transform( $relativo, $contenido, $actual ), un archivo pequeño (hasta 1 MiB) se ajusta antes de publicarlo.
	 */
	private static function write_entry( $zip, $index, $root, $relative, $track, $transform = null ) {
		Digitalisimo_Backups_Maintenance::heartbeat();
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
		// Un archivo que ya es idéntico al del respaldo no se reescribe ni cambia de fecha: reescribir un .htaccess (o
		// web.config) recarga el servidor web en algunos hostings (OpenLiteSpeed en Webuzo) y puede cortar la restauración.
		// Se compara byte a byte (nunca por el CRC, que se puede falsificar), y el respaldo ya se comprobó contra su manifiesto.
		// Si la reubicación ajusta el archivo ($transform), se compara el resultado ya ajustado, más abajo.
		$adjusted = $transform && $stat && (int) $stat['size'] <= 1048576;
		if ( ! $adjusted && $was_present && $stat && is_file( $destination ) && (int) @filesize( $destination ) === (int) $stat['size'] && self::same_content( $zip, (string) $stat['name'], $destination ) ) return null;
		$input = $stat ? self::zip_entry_reader( $zip, $stat['name'], (int) $stat['size'] ) : false;
		$temp = $destination . '.digitalisimo-restore-' . wp_generate_uuid4();
		$output = @fopen( $temp, 'xb' );
		if ( ! $stat || ! $input || ! $output ) { if ( $input ) fclose( $input ); if ( $output ) fclose( $output ); @unlink( $temp ); throw new Exception( 'No se pudo preparar el archivo ' . $relative . ' para restaurarlo.' ); }
		$declared = (int) $stat['size'];
		$written = stream_copy_to_stream( $input, $output, $declared + 1 );
		$closed = fclose( $output );
		fclose( $input );
		$crc = @hash_file( 'crc32b', $temp );
		if ( false === $written || ! $closed || $written !== $declared || false === $crc || $crc !== sprintf( '%08x', $stat['crc'] ) ) { @unlink( $temp ); throw new Exception( 'El archivo restaurado ' . $relative . ' quedó incompleto.' ); }
		if ( $transform && $declared <= 1048576 ) {
			$content = (string) @file_get_contents( $temp );
			$changed = call_user_func( $transform, $relative, $content, is_file( $destination ) ? (string) @file_get_contents( $destination ) : null );
			if ( is_string( $changed ) && $changed !== $content && strlen( $changed ) !== @file_put_contents( $temp, $changed ) ) { @unlink( $temp ); throw new Exception( 'No se pudo ajustar el archivo ' . $relative . ' para este servidor.' ); }
			// El resultado ajustado ya es el archivo de este sitio: no se reescribe (ver arriba).
			if ( $was_present && is_file( $destination ) && (string) @file_get_contents( $destination ) === ( is_string( $changed ) ? $changed : $content ) ) { @unlink( $temp ); return null; }
		}
		$created_hash = ! $was_present && $track ? @hash_file( 'sha256', $temp ) : null;
		if ( false === $created_hash ) { @unlink( $temp ); throw new Exception( 'No se pudo verificar el archivo nuevo ' . $relative . '.' ); }
		if ( ! @rename( $temp, $destination ) ) { @unlink( $temp ); throw new Exception( 'No se pudo reemplazar el archivo ' . $relative . ' durante la restauración.' ); }
		return $created_hash;
	}

	/** Si una entrada del ZIP y un archivo tienen exactamente los mismos bytes. */
	private static function same_content( $zip, $name, $path ) {
		$stat = $zip->statName( $name );
		$entry = self::zip_entry_reader( $zip, $name, $stat ? (int) $stat['size'] : -1 );
		$file = @fopen( $path, 'rb' );
		try {
			if ( ! $entry || ! $file ) return false;
			while ( true ) {
				$left = self::read_exact( $entry, 1048576 );
				$right = self::read_exact( $file, 1048576 );
				if ( $left !== $right ) return false;
				if ( '' === $left ) return true;
			}
		} finally {
			if ( $entry ) fclose( $entry );
			if ( $file ) fclose( $file );
		}
	}
	private static function read_exact( $handle, $length ) {
		$out = '';
		while ( strlen( $out ) < $length && ! feof( $handle ) ) {
			$chunk = fread( $handle, $length - strlen( $out ) );
			if ( false === $chunk || '' === $chunk ) break;
			$out .= $chunk;
		}
		return $out;
	}
	/** Lee el SQL del ZIP por fragmentos; cada sentencia pasa el guardián antes de ejecutarse. */
	private static function import_zip_sql( $zip, $guard = null ) {
		global $wpdb;
		$guard = $guard ?: new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::EXPORTER, true );
		$stream = $zip->getStream( 'database.sql' );
		if ( ! $stream ) throw new Exception( 'No se pudo leer la base de datos del ZIP.' );
		try {
			$guard->scan( function() use ( $stream ) {
				Digitalisimo_Backups_Maintenance::heartbeat();
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
			$storage_before = self::storage_record();
			self::write_restore_journal();
			Digitalisimo_Backups_Maintenance::start();
			try {
				self::updraft_sql( $database, new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::MYSQLDUMP, false, null, $prefix ), true );
				try { self::after_restore_sql( $history_before, $vault_before, array(), null, $storage_before ); } catch ( Throwable $ignored ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				foreach ( $pieces as $piece ) self::extract_updraft_zip( $piece[0], $piece[1], $piece[2] );
			} finally {
				try {
					self::after_restore_sql( $history_before, $vault_before, array(), null, $storage_before );
					self::remove_restore_journal();
				} finally {
					Digitalisimo_Backups_Maintenance::stop();
				}
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
				Digitalisimo_Backups_Maintenance::heartbeat();
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
}
