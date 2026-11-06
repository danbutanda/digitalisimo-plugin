<?php
defined( 'ABSPATH' ) || exit;

/**
 * Casos de uso del motor que comparten la pantalla Backups, la API REST y WP-CLI.
 *
 * Cada superficie autoriza a su manera y después llama aquí, así que ninguna repite los pasos del motor:
 * - pantalla: capability y nonce de admin-post («Crear respaldo completo» hacia un destino remoto o sin JavaScript, y
 *   «Restaurar»);
 * - REST: autenticación de WordPress (con cookie exige el nonce `wp_rest`) y la misma capability;
 * - WP-CLI: la cuenta del sistema, que ya controla los archivos y wp-config.php.
 * La vista con avance del respaldo local (AJAX) todavía da los mismos pasos por su cuenta, con las mismas comprobaciones
 * previas (assert_full_files_ready()), hasta que pase por la cola de «Trabajos».
 *
 * Aquí se decide el alcance. En una red, crear, restaurar o ejecutar una automatización abarca todos los sitios: se
 * pide desde el sitio principal (de la red principal, si hay varias) y con el alcance de red explícito (`network`). En
 * una instalación individual, ese alcance se rechaza. Los resúmenes (estado, historial, verificación y automatizaciones)
 * nunca incluyen la ruta del almacén ni identificadores remotos; un aviso que impide respaldar nombra la carpeta que
 * hay que corregir.
 *
 * Con la cola de «Trabajos», crear y ejecutar una automatización encolan aquí; con «Seguridad», restaurar abre el
 * paquete y pide la confirmación de su nivel de confianza aquí. Ver «Cobertura» en docs/BACKUPS.md.
 */
trait Digitalisimo_Backups_Service {
	/** Administración de la red en Multisite; del sitio en una instalación individual. */
	public static function service_can_manage() {
		return self::can_manage();
	}
	private static function service_authorized() {
		return ( defined( 'WP_CLI' ) && WP_CLI ) || self::can_manage();
	}
	private static function service_error( $code, $message, $status ) {
		return new WP_Error( 'digitalisimo_backups_' . $code, $message, array( 'status' => $status ) );
	}
	/**
	 * Por qué este sitio no gestiona respaldos completos ('' si puede). El respaldo exporta todas las tablas de la
	 * instalación: en una red se gestiona desde el sitio principal y, con varias redes (todas comparten la base de
	 * datos), desde el de la red principal, porque administrar una red secundaria no autoriza a copiar ni a reemplazar
	 * las demás.
	 */
	private static function service_main_site_problem() {
		if ( ! is_multisite() ) return '';
		if ( ! is_main_site() ) return 'En una red, los respaldos se crean, consultan y restauran desde el sitio principal o la administración de red.';
		if ( function_exists( 'is_main_network' ) && ! is_main_network() ) return 'Esta instalación tiene varias redes y el respaldo completo las abarca todas: se crea, consulta y restaura desde el sitio principal de la red principal.';
		return '';
	}
	/** Las operaciones que escriben exigen, en una red, el sitio principal y el alcance de red explícito. */
	private static function service_scope( $network, $write ) {
		if ( ! self::service_authorized() ) return self::service_error( 'forbidden', 'No autorizado para gestionar respaldos.', 403 );
		if ( ! is_multisite() ) {
			return $network ? self::service_error( 'scope', 'Esta instalación no es una red: omite el alcance de red.', 400 ) : true;
		}
		$elsewhere = self::service_main_site_problem();
		if ( '' !== $elsewhere ) return self::service_error( 'main_site', $elsewhere, 400 );
		if ( $write && ! $network ) return self::service_error( 'scope', 'En una red, el respaldo y la restauración abarcan todos los sitios: confirma el alcance de red.', 400 );
		return true;
	}
	/** Otra petición tiene el bloqueo exclusivo del almacén; no espera ni crea el archivo del bloqueo. */
	private static function service_busy() {
		try { $path = trailingslashit( self::local_directory() ) . self::OPERATION_LOCK; } catch ( Exception $e ) { return false; }
		if ( ! is_file( $path ) ) return false;
		$handle = @fopen( $path, 'c' );
		if ( ! $handle ) return false;
		$free = @flock( $handle, LOCK_EX | LOCK_NB );
		if ( $free ) flock( $handle, LOCK_UN );
		fclose( $handle );
		return ! $free;
	}
	private static function service_lock() {
		try {
			return self::acquire_operation_lock();
		} catch ( Exception $e ) {
			return self::service_busy() ? self::service_error( 'busy', $e->getMessage(), 409 ) : self::service_error( 'storage', $e->getMessage(), 500 );
		}
	}
	/** Requisitos de un respaldo completo, antes de exportar nada: ZipArchive, almacén privado y carpetas de WordPress. */
	private static function service_preflight() {
		if ( ! class_exists( 'ZipArchive' ) ) throw new Exception( 'El servidor necesita la extensión PHP ZipArchive para crear el respaldo.' );
		self::local_directory();
		self::assert_full_files_ready();
	}
	private static function service_local_file( $file ) {
		$file = sanitize_file_name( (string) $file );
		try {
			$path = '' === $file ? '' : self::local_path( $file );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		return $path ? $path : self::service_error( 'not_found', 'El respaldo local solicitado no está disponible.', 404 );
	}
	/** Fila pública del historial. `restorable` sigue la lista de la pantalla: local, completo y presente. */
	private static function service_summary( $item ) {
		$item = (array) $item;
		$file = (string) ( $item['file'] ?? '' );
		$scope = (string) ( $item['scope'] ?? 'site' );
		$type = (string) ( $item['backup_type'] ?? 'full' );
		$available = false;
		if ( '' !== $file ) {
			try { $available = '' !== self::local_path( $file ); } catch ( Exception $e ) { $available = false; }
		}
		return array(
			'file' => $file,
			'date' => (string) ( $item['date'] ?? '' ),
			'size' => (int) ( $item['size'] ?? 0 ),
			'sha256' => (string) ( $item['sha256'] ?? '' ),
			'destination' => (string) ( $item['destination'] ?? 'local' ),
			'remote' => (string) ( ( (array) ( $item['remote'] ?? array() ) )['provider'] ?? '' ),
			'scope' => $scope,
			'type' => $type,
			'policy' => (string) ( $item['policy'] ?? 'manual' ),
			'locked' => ! empty( $item['locked'] ),
			'available' => $available,
			'restorable' => $available && 'local' === ( $item['destination'] ?? 'local' ) && 'full' === $type && in_array( $scope, array( 'network', 'installation' ), true ),
		);
	}

	/** Estado real: requisitos, operación en curso, destinos, último respaldo y automatizaciones. */
	public static function service_status() {
		if ( ! self::service_authorized() ) return self::service_error( 'forbidden', 'No autorizado para gestionar respaldos.', 403 );
		$elsewhere = self::service_main_site_problem();
		$main = '' === $elsewhere;
		$settings = self::settings();
		$problems = array();
		if ( ! $main ) $problems[] = $elsewhere;
		$storage = true;
		try { self::local_directory(); } catch ( Exception $e ) { $storage = false; $problems[] = $e->getMessage(); }
		if ( ! class_exists( 'ZipArchive' ) ) $problems[] = 'El servidor necesita la extensión PHP ZipArchive para crear el respaldo.';
		foreach ( Digitalisimo_Backups_Layout::problems() as $problem ) $problems[] = $problem;
		$config = '';
		try {
			$config = dirname( wp_normalize_path( self::wordpress_config_file() ) ) === rtrim( wp_normalize_path( (string) realpath( ABSPATH ) ), '/' ) ? 'abspath' : 'parent';
		} catch ( Exception $e ) {
			$problems[] = $e->getMessage();
		}
		$history = $main ? array_values( (array) get_option( self::HISTORY, array() ) ) : array();
		$automations = $main ? array_filter( self::policies(), function( $policy ) { return ! empty( $policy['enabled'] ) && 'full' === ( $policy['type'] ?? '' ); } ) : array();
		return array(
			'version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '',
			'multisite' => is_multisite(),
			'main_site' => $main,
			'scope' => is_multisite() ? 'network' : 'installation',
			'ready' => ! $problems,
			'problems' => array_values( array_unique( $problems ) ),
			'busy' => $storage && self::service_busy(),
			'wp_config' => $config,
			'destinations' => self::manual_destinations( $settings ),
			'default_destination' => (string) $settings['destination'],
			'retention' => (int) $settings['retention'],
			'backups' => count( $history ),
			'last_backup' => $history ? self::service_summary( $history[0] ) : null,
			'automations' => count( $automations ),
		);
	}
	/** Historial, del más reciente al más antiguo. */
	public static function service_backups() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		return array_map( array( __CLASS__, 'service_summary' ), array_values( (array) get_option( self::HISTORY, array() ) ) );
	}
	/** Automatizaciones con su próxima ejecución; las parciales antiguas figuran desactivadas. */
	public static function service_automations() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$out = array();
		foreach ( self::policies() as $policy ) {
			$id = (string) ( $policy['id'] ?? '' );
			$enabled = 'full' === ( $policy['type'] ?? '' ) && ! empty( $policy['enabled'] );
			$next = $enabled ? wp_next_scheduled( 'digitalisimo_backups_policy', array( $id, get_current_blog_id() ) ) : false;
			$out[] = array(
				'id' => $id,
				'label' => (string) ( $policy['label'] ?? '' ),
				'type' => (string) ( $policy['type'] ?? '' ),
				'schedule' => (string) ( $policy['schedule'] ?? 'disabled' ),
				'destination' => (string) ( $policy['destination'] ?? 'local' ),
				'retain' => (int) ( $policy['retain'] ?? 0 ),
				'enabled' => $enabled,
				'next_run' => $next ? gmdate( 'c', (int) $next ) : '',
			);
		}
		return $out;
	}
	/**
	 * Respaldo completo de la instalación o de la red, el del botón «Crear respaldo completo»: bloqueo del almacén,
	 * ZIP verificado, copia al destino, historial y rotación.
	 */
	public static function service_create( array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$settings = self::settings();
		$destination = sanitize_key( (string) ( $args['destination'] ?? '' ) );
		if ( '' === $destination ) $destination = (string) $settings['destination'];
		if ( ! isset( self::manual_destinations( $settings )[ $destination ] ) ) return self::service_error( 'destination', 'El destino seleccionado no está conectado o no es válido.', 400 );
		$settings['destination'] = $destination;
		try { self::service_preflight(); } catch ( Exception $e ) { return self::service_error( 'not_ready', $e->getMessage(), 422 ); }
		@set_time_limit( 0 );
		ignore_user_abort( true );
		$lock = self::service_lock();
		if ( is_wp_error( $lock ) ) return $lock;
		try {
			$file = self::create_network();
			$remote = 'local' === $destination ? array() : self::transfer( $file, $settings );
			self::remember( $file, $destination, is_multisite() ? 'network' : 'installation', array( 'id' => 'manual', 'type' => 'full' ), $remote );
			self::prune( dirname( $file ), (int) $settings['retention'] );
		} catch ( Throwable $e ) {
			return self::service_error( 'failed', $e->getMessage(), 500 );
		} finally {
			self::release_operation_lock( $lock );
		}
		return self::service_summary( self::history_item( wp_basename( $file ) ) );
	}
	/** Ejecuta ahora una automatización completa, por el mismo camino que su botón «Ejecutar ahora» y su evento de cron. */
	public static function service_run_automation( $id, array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$id = sanitize_key( (string) $id );
		if ( '' === $id || ! self::policy( $id ) ) return self::service_error( 'automation', 'La automatización no existe.', 404 );
		if ( self::service_busy() ) return self::service_error( 'busy', 'Ya hay un respaldo o una restauración en curso. Espera a que termine antes de iniciar otra operación.', 409 );
		@set_time_limit( 0 );
		ignore_user_abort( true );
		$result = self::run_policy( $id );
		if ( is_wp_error( $result ) ) {
			$status = array( 'digitalisimo_backup_policy' => 409, 'digitalisimo_backup_partial' => 400, 'digitalisimo_backup_scope' => 400 );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status[ $result->get_error_code() ] ?? 500 ) );
		}
		return self::service_summary( self::history_item( wp_basename( $result ) ) );
	}
	/**
	 * Restaura un respaldo local completo con la copia previa bloqueada y la recuperación del motor. Las tres superficies
	 * exigen la confirmación RESTAURAR.
	 */
	public static function service_restore( $file, array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		if ( 'RESTAURAR' !== strtoupper( trim( (string) ( $args['confirm'] ?? '' ) ) ) ) return self::service_error( 'confirm', 'Escribe RESTAURAR para confirmar una operación destructiva.', 400 );
		$path = self::service_local_file( $file );
		if ( is_wp_error( $path ) ) return $path;
		// La copia previa es un respaldo completo: sin él no se restaura (422, como al crear), y nada se toca.
		try { self::service_preflight(); } catch ( Exception $e ) { return self::service_error( 'not_ready', $e->getMessage(), 422 ); }
		@set_time_limit( 0 );
		ignore_user_abort( true );
		$lock = self::service_lock();
		if ( is_wp_error( $lock ) ) return $lock;
		try {
			self::restore_digitalisimo_zip( $path );
		} catch ( Throwable $e ) {
			return self::service_error( 'restore_failed', $e->getMessage(), 500 );
		} finally {
			self::release_operation_lock( $lock );
		}
		$safety = '';
		foreach ( (array) get_option( self::HISTORY, array() ) as $item ) {
			if ( 'pre-restore' === ( $item['policy'] ?? '' ) ) { $safety = (string) ( $item['file'] ?? '' ); break; }
		}
		return array( 'file' => wp_basename( $path ), 'restored' => true, 'safety_backup' => $safety );
	}
	/**
	 * Comprueba un respaldo local sin tocar el sitio: estructura y CRC del ZIP, rutas, SHA-256 de cada entrada contra su
	 * manifiesto y del archivo contra el historial. Las condiciones de la instalación de destino las aplica la
	 * restauración antes de escribir nada.
	 */
	public static function service_verify( $file ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$path = self::service_local_file( $file );
		if ( is_wp_error( $path ) ) return $path;
		if ( ! class_exists( 'ZipArchive' ) ) return self::service_error( 'zip', 'El servidor necesita la extensión PHP ZipArchive para verificar el respaldo.', 500 );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CHECKCONS ) ) return self::service_error( 'invalid', 'El ZIP no supera la comprobación estructural.', 422 );
		try {
			self::validate_zip_entries( $zip );
			self::verify_zip_contents( $zip );
			$raw = $zip->getFromName( 'manifest.json' );
			if ( false === $raw ) $raw = $zip->getFromName( 'migration.json' );
			$data = false === $raw ? null : json_decode( $raw, true );
			if ( ! is_array( $data ) ) throw new Exception( 'La metadata del respaldo no es válida.' );
			$checksum = @hash_file( 'sha256', $path );
			if ( false === $checksum ) throw new Exception( 'No se pudo calcular el SHA-256 del respaldo.' );
			$item = self::history_item( wp_basename( $path ) );
			if ( ! empty( $item['sha256'] ) && ! hash_equals( (string) $item['sha256'], $checksum ) ) throw new Exception( 'El SHA-256 del respaldo ya no coincide con el archivo registrado.' );
			return array(
				'file' => wp_basename( $path ),
				'verified' => true,
				'registered' => ! empty( $item ),
				'sha256' => $checksum,
				'size' => (int) filesize( $path ),
				'entries' => (int) $zip->numFiles,
				'format' => (string) ( $data['format'] ?? '' ),
				'scope' => (string) ( $data['scope'] ?? '' ),
				'type' => (string) ( $data['type'] ?? '' ),
				'multisite' => ! empty( $data['multisite'] ),
				'table_prefix' => (string) ( $data['table_prefix'] ?? '' ),
				'created_at' => (string) ( $data['created_at'] ?? '' ),
				'plugin_version' => (string) ( $data['plugin_version'] ?? '' ),
			);
		} catch ( Exception $e ) {
			return self::service_error( 'invalid', $e->getMessage(), 422 );
		} finally {
			$zip->close();
		}
	}
}
