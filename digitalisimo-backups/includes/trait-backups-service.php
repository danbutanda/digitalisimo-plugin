<?php
defined( 'ABSPATH' ) || exit;

/**
 * Casos de uso del motor que comparten la pantalla Backups, la API REST y WP-CLI.
 *
 * Cada superficie autoriza a su manera y después llama aquí, así que ninguna repite los pasos del motor:
 * - pantalla: capability y nonce de admin-post o AJAX («Crear respaldo completo», «Ejecutar ahora» y «Restaurar»);
 * - REST: autenticación de WordPress (con cookie exige el nonce `wp_rest`) y la misma capability;
 * - WP-CLI: la cuenta del sistema, que ya controla los archivos y wp-config.php.
 *
 * Aquí se decide el alcance. En una red, crear, restaurar o ejecutar una automatización abarca todos los sitios: se
 * pide desde el sitio principal (de la red principal, si hay varias) y con el alcance de red explícito (`network`). En
 * una instalación individual, ese alcance se rechaza. Los resúmenes (estado, historial, trabajos, verificación y
 * automatizaciones) nunca incluyen la ruta del almacén, identificadores remotos, una clave ni una frase: de una clave
 * sólo se nombra su huella corta. Un aviso que impide respaldar nombra la carpeta que hay que corregir.
 *
 * Crear y ejecutar una automatización encolan un trabajo en Digitalisimo_Backups_Jobs (la misma cola que la pantalla);
 * cada superficie lo hace avanzar a su manera (el navegador, WP-Cron, `POST /jobs/tick` o WP-CLI). Restaurar y
 * verificar abren el paquete con Digitalisimo_Backups_Security: descifran un `.dgbk` con la clave del sitio o con la
 * frase escrita, comprueban la firma y deciden la confirmación que pide su nivel de confianza. Ver «REST y WP-CLI» en
 * docs/BACKUPS.md.
 */
trait Digitalisimo_Backups_Service {
	/** Administración de la red en Multisite; del sitio en una instalación individual. */
	public static function service_can_manage() {
		return self::can_manage();
	}
	private static function service_authorized() {
		return ( defined( 'WP_CLI' ) && WP_CLI ) || self::can_manage();
	}
	private static function service_error( $code, $message, $status, array $data = array() ) {
		return new WP_Error( 'digitalisimo_backups_' . $code, $message, array( 'status' => $status ) + $data );
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
	/** La misma comprobación de permiso y alcance para los casos de uso de otras clases (p. ej. antes de actualizar). */
	public static function service_check( $network, $write ) {
		return self::service_scope( (bool) $network, (bool) $write );
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
			$lock = self::try_operation_lock();
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		return $lock ? $lock : self::service_error( 'busy', 'Ya hay un respaldo o una restauración en curso. Espera a que termine antes de iniciar otra operación.', 409 );
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
	/**
	 * Un respaldo cifrado cuya clave este sitio no abre necesita la frase o la clave escrita: sin ella el error es claro
	 * y estable (`secret_required`) antes de tomar el bloqueo. Un archivo con la cabecera dañada lo rechaza open().
	 */
	private static function service_secret_problem( $path, $secret ) {
		if ( '' !== $secret || ! Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) return null;
		try {
			$key_id = (string) Digitalisimo_Backups_Encrypted_File::read_header( $path )['header']['key']['id'];
		} catch ( Exception $e ) {
			return null;
		}
		return Digitalisimo_Backups_Keys::can_open( $key_id ) ? null : self::service_error( 'secret_required', Digitalisimo_Backups_Security::missing_key_text( $key_id ), 400, array( 'key' => Digitalisimo_Backups_Keys::short( $key_id ) ) );
	}
	/**
	 * Fila pública del historial. `restorable` sigue la lista de la pantalla: completo y con su ZIP en el almacén, tenga
	 * o no copia remota. `copies` enumera sólo las copias que existen: la local, si el ZIP sigue en el almacén, y la
	 * remota verificada (`ok`) o anterior sin identificador (`legacy`); `destination` es la de esa copia remota (o
	 * `local`), y una copia que se intentó y no llegó va en `transfer_destination` con su `transfer`.
	 */
	private static function service_summary( $item ) {
		$item = (array) $item;
		$file = (string) ( $item['file'] ?? '' );
		$scope = (string) ( $item['scope'] ?? 'site' );
		$type = (string) ( $item['backup_type'] ?? 'full' );
		$container = 'dgbk' === ( $item['container'] ?? '' ) ? 'dgbk' : 'zip';
		$key_id = (string) ( $item['key_id'] ?? '' );
		$available = '' !== self::local_copy( $item );
		$copy = self::remote_copy( $item );
		return array(
			'file' => $file,
			'date' => (string) ( $item['date'] ?? '' ),
			'size' => (int) ( $item['size'] ?? 0 ),
			'sha256' => (string) ( $item['sha256'] ?? '' ),
			// Dónde hay una copia remota que existe; la que se intentó y no llegó va en transfer_destination y transfer.
			'destination' => self::remote_exists( $item ) ? $copy['destination'] : 'local',
			'remote' => (string) ( ( (array) ( $item['remote'] ?? array() ) )['provider'] ?? '' ),
			'transfer' => (string) ( $item['transfer'] ?? '' ),
			'transfer_destination' => $copy ? (string) $copy['destination'] : '',
			'copies' => self::item_copies( $item, $available ),
			'scope' => $scope,
			'type' => $type,
			'policy' => (string) ( $item['policy'] ?? 'manual' ),
			'container' => $container,
			'encrypted' => 'dgbk' === $container,
			'signed' => ! empty( $item['signed'] ),
			'key' => '' === $key_id ? '' : Digitalisimo_Backups_Keys::short( $key_id ),
			'needs_secret' => 'dgbk' === $container && ! Digitalisimo_Backups_Keys::can_open( $key_id ),
			'locked' => ! empty( $item['locked'] ),
			'available' => $available,
			'restorable' => self::restorable_item( $item, $available ),
			'site' => (string) ( $item['extract']['label'] ?? '' ),
			'consistency' => (string) ( $item['consistency'] ?? '' ),
			'notices' => array_values( array_map( function( $notice ) { return (string) ( $notice['text'] ?? '' ); }, (array) ( $item['notices'] ?? array() ) ) ),
		);
	}
	/** Copias previas a una restauración en el historial: se registran (bloqueadas) justo antes del SQL. */
	private static function service_safety_copies() {
		return count( array_filter( (array) get_option( self::HISTORY, array() ), function( $row ) { return 'pre-restore' === ( $row['policy'] ?? '' ); } ) );
	}
	/** Vista pública de un trabajo (la de la tarjeta «Trabajos») con su destino y su origen. */
	public static function service_job_view( array $job ) {
		$spec = (array) ( $job['spec'] ?? array() );
		$view = Digitalisimo_Backups_Jobs::view( $job ) + array(
			'destination' => (string) ( $spec['destination'] ?? 'local' ),
			'policy' => (string) ( $spec['policy'] ?? '' ),
			'encrypt' => ! empty( $spec['security']['encrypt'] ),
			'scope' => (string) ( $spec['scope'] ?? '' ),
			'site' => Digitalisimo_Backups_Site_Extract::is_extract( $spec ) ? (int) $spec['extract']['site_id'] : 0,
		);
		// Una restauración: su informe al terminar y, si falló a mitad, la vuelta a la copia previa que la sigue.
		if ( Digitalisimo_Backups_Jobs::is_restore( $job ) ) {
			$view['restore'] = array( 'file' => basename( (string) ( $spec['file'] ?? '' ) ), 'safety_backup' => self::restore_safety_file( $job ), 'rollback' => ! empty( $spec['rollback'] ), 'result' => (array) ( $job['result']['restore'] ?? array() ), 'followed_by' => self::restore_rollback_info( (string) $job['id'] )['job'] );
		}
		return $view;
	}
	/** Encolar responde enseguida: el trabajo lo hacen avanzar WP-Cron, el navegador, `POST /jobs/tick` o WP-CLI. */
	private static function service_enqueued( array $queued, $duplicate_message, $spawn ) {
		if ( ! empty( $queued['duplicate'] ) ) return self::service_error( 'duplicate', $duplicate_message, 409, array( 'job' => (string) $queued['job']['id'] ) );
		Digitalisimo_Backups_Jobs::schedule( (bool) $spawn );
		return self::service_job_view( $queued['job'] );
	}

	/** Estado real: requisitos, operación y trabajos en curso, destinos, seguridad, último respaldo y automatizaciones. */
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
		// Con el cifrado activo y sin clave no se encola nada: también impide respaldar.
		$security = Digitalisimo_Backups_Security::settings();
		try { Digitalisimo_Backups_Security::job_settings( false ); } catch ( Exception $e ) { $problems[] = $e->getMessage(); }
		$constant = Digitalisimo_Backups_Keys::constant_state();
		$ring = Digitalisimo_Backups_Keys::keyring();
		// Credenciales de los destinos: sólo el estado y qué hacer, nunca un valor.
		$credentials = $main ? self::credentials_status() : array( 'state' => 'none', 'notice' => '' );
		$history = $main ? array_values( (array) get_option( self::HISTORY, array() ) ) : array();
		$automations = $main ? array_filter( self::policies(), function( $policy ) { return ! empty( $policy['enabled'] ) && 'full' === ( $policy['type'] ?? '' ); } ) : array();
		$jobs = 0;
		$restore = array( 'state' => 'none', 'notice' => '' );
		if ( $main && $storage ) {
			try { $jobs = count( Digitalisimo_Backups_Jobs::active() ); } catch ( Throwable $e ) { $jobs = 0; }
			// Una restauración sin terminar (o que no terminó bien) se avisa con claridad: qué fase, qué pasa con el sitio.
			$restore = self::restore_status();
			if ( in_array( $restore['state'], array( 'running', 'interrupted', 'rollback' ), true ) ) $problems[] = 'Hay una restauración en curso: los respaldos esperan a que termine. ' . $restore['notice'];
		}
		return array(
			'version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '',
			'multisite' => is_multisite(),
			'main_site' => $main,
			'scope' => is_multisite() ? 'network' : 'installation',
			'ready' => ! $problems,
			'problems' => array_values( array_unique( $problems ) ),
			'busy' => $storage && self::service_busy(),
			'jobs' => $jobs,
			'wp_config' => $config,
			'destinations' => self::manual_destinations( $settings ),
			'default_destination' => (string) $settings['destination'],
			'retention' => (int) $settings['retention'],
			'encrypt' => ! empty( $security['encrypt'] ),
			'require_signed' => ! empty( $security['require_signed'] ),
			'key_source' => 'none' !== $constant ? 'wp-config' : ( $ring['active'] ? 'passphrase' : 'none' ),
			'credentials' => (string) $credentials['state'],
			'credentials_notice' => (string) $credentials['notice'],
			'notifications' => Digitalisimo_Backups_Notify::status(),
			'backups' => count( $history ),
			'last_backup' => $history ? self::service_summary( $history[0] ) : null,
			'automations' => count( $automations ),
			'restoring' => in_array( $restore['state'], array( 'running', 'interrupted', 'rollback' ), true ),
			'restore' => $restore,
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
				'time' => self::fixed_time( $policy ),
				'destination' => (string) ( $policy['destination'] ?? 'local' ),
				'retain' => (int) ( $policy['retain'] ?? 0 ),
				'enabled' => $enabled,
				'next_run' => $next ? gmdate( 'c', (int) $next ) : '',
			);
		}
		return $out;
	}
	/**
	 * Encola el respaldo completo de la instalación o de la red, el del botón «Crear respaldo completo»: comprueba antes
	 * los requisitos (422) y nunca duplica un respaldo manual sin terminar (409). `via` y `context` anotan el origen.
	 */
	public static function service_create( array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$settings = self::settings();
		$destination = sanitize_key( (string) ( $args['destination'] ?? '' ) );
		if ( '' === $destination ) $destination = (string) $settings['destination'];
		if ( ! isset( self::manual_destinations( $settings )[ $destination ] ) ) {
			$problem = 'local' === $destination ? '' : self::credentials_problem( $destination );
			return self::service_error( 'destination', '' !== $problem ? $problem : 'El destino seleccionado no está conectado o no es válido.', 400 );
		}
		try {
			self::ensure_ready();
			$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => $destination, 'retain' => (int) $settings['retention'], 'retention' => 'global', 'via' => (string) ( $args['via'] ?? 'rest' ), 'network' => 'network' === ( $args['context'] ?? ( is_multisite() ? 'network' : 'site' ) ) ) ) );
		} catch ( Throwable $e ) {
			// Throwable, como hacía el respaldo con avance (AJAX): un fallo del almacén de trabajos es un error claro, no un fatal.
			return self::service_error( 'not_ready', $e->getMessage(), 422 );
		}
		return self::service_enqueued( $queued, 'Ya hay un respaldo manual pendiente o en curso; su avance está en «Trabajos».', $args['spawn'] ?? true );
	}
	/**
	 * «Extraer como WordPress individual»: encola un trabajo que produce el paquete de un sitio de la red, restaurable en
	 * una instalación individual (alcance `extracted-site`, ver Digitalisimo_Backups_Site_Extract). Es una acción de la
	 * red: sólo un superadministrador (o WP-CLI), desde el sitio principal y con el alcance de red explícito, como crear un
	 * respaldo de la red; un sitio no puede extraer otros. `super_admins` (managing, all o none) decide qué pasa con los
	 * superadministradores que no son miembros del sitio y `network_secrets`, si las claves de API que el sitio hereda de
	 * la red viajan. Nunca duplica la extracción pendiente de un mismo sitio (409).
	 */
	public static function service_extract( $site_id, array $args = array() ) {
		if ( ! is_multisite() ) return self::service_error( 'scope', 'Esta instalación no es una red: no hay sitios que extraer.', 400 );
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! is_super_admin() ) return self::service_error( 'forbidden', 'Sólo un superadministrador de la red puede extraer un sitio.', 403 );
		$site = Digitalisimo_Backups_Site_Extract::site( $site_id );
		if ( is_wp_error( $site ) ) return $site;
		$options = Digitalisimo_Backups_Site_Extract::options( $args );
		if ( is_wp_error( $options ) ) return $options;
		$settings = self::settings();
		$destination = sanitize_key( (string) ( $args['destination'] ?? '' ) );
		if ( '' === $destination ) $destination = 'local';
		if ( ! isset( self::manual_destinations( $settings )[ $destination ] ) ) {
			$problem = 'local' === $destination ? '' : self::credentials_problem( $destination );
			return self::service_error( 'destination', '' !== $problem ? $problem : 'El destino seleccionado no está conectado o no es válido.', 400 );
		}
		try {
			self::ensure_ready();
			foreach ( Digitalisimo_Backups_Site_Extract::problems( $site ) as $problem ) throw new Exception( $problem );
			// La seguridad del paquete es suya (own_key()), nunca la de la red: la especificación se pide sin el plan de la red
			// (inline, que no abre ni exige su clave) y job_spec() fija la propia.
			$spec = Digitalisimo_Backups_Jobs::spec( array( 'policy' => Digitalisimo_Backups_Site_Extract::policy_id( $site['id'] ), 'destination' => $destination, 'retain' => 1, 'retention' => 'none', 'via' => (string) ( $args['via'] ?? 'rest' ), 'network' => true, 'site_id' => get_main_site_id(), 'file' => Digitalisimo_Backups_Site_Extract::file_name( $site ), 'inline' => true ) );
			Digitalisimo_Backups_Site_Extract::forget_keys();
			$own = Digitalisimo_Backups_Site_Extract::own_key( $args['secret'] ?? '' );
			if ( is_wp_error( $own ) ) return $own;
			$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Site_Extract::job_spec( $spec, $site, $options, $own ) );
			if ( ! empty( $queued['duplicate'] ) && '' !== $own['key_id'] ) Digitalisimo_Backups_Site_Extract::forget_keys( $own['key_id'] );
		} catch ( Throwable $e ) {
			return self::service_error( 'not_ready', $e->getMessage(), 422 );
		}
		return self::service_enqueued( $queued, 'Ya hay una extracción de ' . $site['label'] . ' pendiente o en curso; su avance está en «Trabajos».', $args['spawn'] ?? true );
	}
	/** Encola ahora una automatización completa, por el mismo camino que su botón «Ejecutar ahora» y su evento de cron. */
	public static function service_run_automation( $id, array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$id = sanitize_key( (string) $id );
		if ( '' === $id || ! self::policy( $id ) ) return self::service_error( 'automation', 'La automatización no existe.', 404 );
		try { self::ensure_ready(); } catch ( Throwable $e ) { return self::service_error( 'not_ready', $e->getMessage(), 422 ); }
		$result = self::run_policy( $id, 0, (string) ( $args['via'] ?? 'rest' ) );
		if ( is_wp_error( $result ) ) {
			$status = array( 'digitalisimo_backup_policy' => 409, 'digitalisimo_backup_partial' => 400, 'digitalisimo_backup_scope' => 400, 'digitalisimo_backup_policy_failed' => 422 );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status[ $result->get_error_code() ] ?? 500 ) );
		}
		return self::service_enqueued( $result, 'Esta automatización ya tiene un respaldo pendiente o en curso; su avance está en «Trabajos».', $args['spawn'] ?? true );
	}

	/** Trabajos sin terminar y los últimos terminados (la tarjeta «Trabajos»), tras pasar el detector si nadie trabaja. */
	public static function service_jobs( $limit = 8 ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		Digitalisimo_Backups_Jobs::recover_if_idle();
		$out = array();
		try {
			$jobs = Digitalisimo_Backups_Jobs::all();
		} catch ( Throwable $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		$views = Digitalisimo_Backups_Jobs::views( max( 1, min( 200, (int) $limit ) ) );
		$by_id = array();
		foreach ( $jobs as $job ) $by_id[ (string) $job['id'] ] = $job;
		foreach ( $views as $view ) if ( isset( $by_id[ $view['id'] ] ) ) $out[] = self::service_job_view( $by_id[ $view['id'] ] );
		return $out;
	}
	public static function service_job( $id ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		if ( ! Digitalisimo_Backups_Job_Store::valid_id( $id ) ) return self::service_error( 'job_invalid', 'El identificador del trabajo no es válido.', 400 );
		Digitalisimo_Backups_Jobs::recover_if_idle();
		$job = Digitalisimo_Backups_Jobs::get( $id );
		return $job ? self::service_job_view( $job ) : self::service_error( 'job_missing', 'El trabajo ya no existe.', 404 );
	}
	/**
	 * Cancela un trabajo como el botón «Cancelar» de la tarjeta: uno en cola termina ya; uno en curso se detiene en su
	 * siguiente punto de control. No abarca más que ese trabajo, así que no pide el alcance de red.
	 */
	public static function service_cancel( $id ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$result = Digitalisimo_Backups_Jobs::cancel( (string) $id );
		Digitalisimo_Backups_Jobs::recover_if_idle();
		if ( empty( $result['ok'] ) ) {
			$status = array( 'invalid' => 400, 'site' => 400, 'missing' => 404 );
			return self::service_error( 'job_' . $result['code'], $result['message'], $status[ $result['code'] ] ?? 409 );
		}
		$job = Digitalisimo_Backups_Jobs::get( (string) $id );
		return array( 'result' => (string) $result['code'], 'message' => (string) $result['message'], 'job' => $job ? self::service_job_view( $job ) : null );
	}
	/**
	 * Un paso de la cola, como el navegador con la pantalla abierta: con el bloqueo ocupado responde `busy` y deja
	 * programado el siguiente tick. El presupuesto es el de la web (o sin límite en WP-CLI).
	 */
	public static function service_tick() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$tick = Digitalisimo_Backups_Jobs::tick();
		return array( 'result' => (string) $tick['result'], 'message' => (string) ( $tick['message'] ?? '' ), 'jobs' => self::service_jobs() );
	}

	/**
	 * Restaura un respaldo local completo (ZIP o `.dgbk`) con la copia previa bloqueada y la recuperación del motor. La
	 * confirmación depende de la confianza del paquete: RESTAURAR para uno firmado o registrado aquí, RESTAURAR SIN FIRMA
	 * para uno sin firma ni registro (que «Restaurar sólo respaldos firmados» bloquea). `secret` es la frase o la clave
	 * de un respaldo cifrado o firmado con una clave que este sitio no tiene; se usa tal cual y nunca vuelve en la
	 * respuesta. Con `adopt`, la frase escrita firma después los respaldos nuevos.
	 *
	 * Un respaldo de otra dirección, otra carpeta del servidor u otro prefijo se reubica (Digitalisimo_Backups_Relocate):
	 * `addresses` = `current` (por defecto: el sitio conserva sus direcciones) o `backup` (conserva las del respaldo). La
	 * respuesta lleva `relocation` con lo reescrito por tabla, las cachés vaciadas y la comprobación final.
	 */
	public static function service_restore( $file, array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$addresses = self::service_addresses( $args );
		if ( is_wp_error( $addresses ) ) return $addresses;
		$confirmation = strtoupper( trim( preg_replace( '/\s+/', ' ', (string) ( $args['confirm'] ?? '' ) ) ) );
		if ( ! in_array( $confirmation, array( 'RESTAURAR', 'RESTAURAR SIN FIRMA' ), true ) ) return self::service_error( 'confirm', 'Escribe RESTAURAR para confirmar una operación destructiva.', 400 );
		$path = self::service_local_file( $file );
		if ( is_wp_error( $path ) ) return $path;
		$secret = is_string( $args['secret'] ?? null ) ? $args['secret'] : '';
		$needs = self::service_secret_problem( $path, $secret );
		if ( $needs ) return $needs;
		// La copia previa es un respaldo completo: sin él no se restaura (422, como al crear), y nada se toca.
		try { self::ensure_ready(); } catch ( Throwable $e ) { return self::service_error( 'not_ready', $e->getMessage(), 422 ); }
		// Con lo que el hosting permita; cada paso está pensado para caber en 64 MB aunque no suba (H1).
		if ( function_exists( 'wp_raise_memory_limit' ) ) wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
		$lock = self::service_lock();
		if ( is_wp_error( $lock ) ) return $lock;
		$report = null;
		$copies = self::service_safety_copies();
		// Aviso por correo con los ajustes de antes del SQL (que trae los del respaldo); se envía al soltar el bloqueo.
		$mail = Digitalisimo_Backups_Notify::snapshot();
		$event = null;
		try {
			$active = Digitalisimo_Backups_Jobs::active();
			if ( $active ) return self::service_error( 'busy', Digitalisimo_Backups_Jobs::is_restore( $active[0] ) ? 'Ya hay una restauración en curso. Espera a que termine (la pantalla Backups muestra su avance).' : 'Hay un respaldo pendiente o en curso. Cancélalo en «Trabajos» o espera a que termine antes de restaurar.', 409, array( 'job' => (string) $active[0]['id'] ) );
			try {
				$report = Digitalisimo_Backups_Security::open( $path, self::history_item( wp_basename( $path ) ), $secret );
			} catch ( Exception $e ) {
				$event = array( 'ok' => false, 'started' => false, 'message' => $e->getMessage() );
				return self::service_error( 'invalid', $e->getMessage(), 422 );
			}
			try {
				Digitalisimo_Backups_Security::require_confirmation( $report, $confirmation );
			} catch ( Exception $e ) {
				return 'untrusted' === $report['trust'] && Digitalisimo_Backups_Security::settings()['require_signed'] ? self::service_error( 'signed_only', $e->getMessage(), 403 ) : self::service_error( 'confirm_unsigned', $e->getMessage(), 400 );
			}
			// La copia previa se firma con la clave que el sitio tiene ahora (no con la frase que se adopte): se fija ya.
			$safety_file = self::backup_file_name( 'safety-before-restore-' );
			$safety_spec = Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'restore', 'file' => $safety_file, 'inline' => true ) );
			// wp-config.php no se restaura: qué constantes del respaldo faltan o difieren aquí (sin valores secretos). Y un sitio
			// extraído de una red: con qué cuentas se entra ahora. Lo dice el informe al terminar.
			$config = self::config_report( $report['zip'] );
			$restored = null;
			try { $restored = Digitalisimo_Backups_Archive_Inspector::manifest( $report['zip'] ); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			$extra = is_array( $restored ) && Digitalisimo_Backups_Site_Extract::SCOPE === ( $restored['scope'] ?? '' ) ? Digitalisimo_Backups_Site_Extract::restored_notice( $restored ) : '';
			// La frase escrita sólo vive en esta petición: si se pidió, firma desde ya los respaldos nuevos.
			$adopted = false;
			$adopt_note = '';
			if ( ! empty( $args['adopt'] ) && 'secret' === $report['via'] && $report['root'] ) {
				try {
					Digitalisimo_Backups_Keys::adopt( $report['root'], $report['key'] );
					$adopted = true;
					$adopt_note = ' La frase escrita firma ahora los respaldos nuevos (clave ' . Digitalisimo_Backups_Keys::short( $report['key']['id'] ) . ').';
				} catch ( Exception $adopt_error ) {
					$adopt_note = ' No se guardó la frase: ' . $adopt_error->getMessage();
				}
			}
			try {
				$job = self::restore_enqueue( $report, array( 'addresses' => $addresses, 'via' => (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) ), 'adopted' => $adopted, 'safety_spec' => $safety_spec, 'sync' => ! empty( $args['sync'] ), 'wp_config' => $config, 'notice' => $extra ) );
			} catch ( Throwable $e ) {
				if ( false === strpos( $e->getMessage(), 'en curso' ) ) $event = array( 'ok' => false, 'started' => false, 'message' => $e->getMessage() );
				return self::service_error( false !== strpos( $e->getMessage(), 'en curso' ) ? 'busy' : 'restore_rejected', $e->getMessage(), false !== strpos( $e->getMessage(), 'en curso' ) ? 409 : 422 );
			}
		} finally {
			Digitalisimo_Backups_Security::close( $report );
			self::release_operation_lock( $lock );
			// Lo que esperaba en la cola (y la restauración misma) conserva su tick, también si se rechazó.
			try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
			if ( $event ) {
				$event += array( 'file' => wp_basename( $path ), 'via' => (string) ( $args['via'] ?? '' ), 'safety' => self::service_latest_safety() );
				try { Digitalisimo_Backups_Notify::restore( $mail, $event['ok'], $event ); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
			}
		}
		// El resto lo hacen ticks: los de esta petición (en WP-CLI, hasta terminar) y después la pantalla, WP-Cron o REST.
		$job = self::restore_drive( $job['id'], array_key_exists( 'drive_cli', $args ) ? (bool) $args['drive_cli'] : null );
		return self::restore_outcome( $job, $copies, $adopt_note );
	}
	/**
	 * Resultado de una restauración para la pantalla, REST y WP-CLI. Terminada: el mismo informe de siempre. Fallida: 422
	 * si se detuvo antes de la copia previa (rechazada), 500 si había empezado (con lo que se recuperó). Sin terminar en
	 * esta petición: `restoring` con el trabajo y el testigo del modo de mantenimiento (REST responde 202).
	 */
	private static function restore_outcome( $job, $copies, $note = '' ) {
		$outcome = self::restore_outcome_raw( $job, $copies, $note );
		return isset( $outcome['error'] ) ? self::service_error( $outcome['error'][0], $outcome['error'][1], $outcome['error'][2] ) : $outcome;
	}
	/** Lo mismo sin WP_Error (la envoltura en este proceso lo usa sin WordPress cargado): array( 'error' => array( código, mensaje, estado ) ). */
	private static function restore_outcome_raw( $job, $copies, $note = '' ) {
		if ( ! $job ) return array( 'error' => array( 'restore_failed', 'El trabajo de la restauración ya no existe.', 500 ) );
		$followed = self::restore_followed( $job );
		if ( ! Digitalisimo_Backups_Job_Store::is_terminal( $followed ) ) {
			return array( 'file' => basename( (string) $job['spec']['file'] ), 'restored' => false, 'restoring' => true, 'job' => self::service_job_view( $followed ), 'notice' => 'La restauración sigue en segundo plano (' . Digitalisimo_Backups_Jobs::phase_label( (string) $followed['phase'], $followed ) . '): la pantalla Backups, WP-Cron o POST /jobs/tick la hacen avanzar' . ( ! empty( $followed['spec']['rollback'] ) ? '; está volviendo a la copia previa' : '' ) . '.' . $note, 'safety_backup' => self::restore_safety_file( $job ), 'maintenance_token' => (string) $job['spec']['token'] );
		}
		if ( 'success' === $job['status'] ) {
			$result = (array) ( $job['result']['restore'] ?? array() );
			$result['notice'] = ltrim( (string) ( $result['notice'] ?? '' ) . $note );
			return $result + array( 'file' => basename( (string) $job['spec']['file'] ), 'restored' => true, 'trust' => '', 'key' => '', 'adopted' => false, 'safety_backup' => '', 'relocation' => null );
		}
		$message = is_array( $job['error'] ?? null ) ? (string) $job['error']['message'] : (string) ( $job['progress']['message'] ?? 'La restauración no terminó.' );
		if ( $followed !== $job ) {
			$safety = self::restore_safety_file( $job );
			$why = self::restore_rollback_info( $job['id'] )['cause'] ?: $message;
			$message = 'success' === $followed['status'] ? 'La restauración falló. Se recuperaron la base de datos y los archivos anteriores desde ' . $safety . ' y se retiraron los archivos nuevos: ' . $why : 'La restauración falló y la reversión también falló. Conserva ' . $safety . ' para recuperación manual: ' . ( is_array( $followed['error'] ?? null ) ? (string) $followed['error']['message'] : $why );
		}
		if ( 'cancelled' === $job['status'] ) return array( 'error' => array( 'restore_cancelled', $message, 409 ) );
		return array( 'error' => self::service_safety_copies() > $copies ? array( 'restore_failed', $message, 500 ) : array( 'restore_rejected', $message, 422 ) );
	}
	/** La copia previa más reciente del historial (la de esta restauración si llegó a crearse). */
	private static function service_latest_safety() {
		foreach ( (array) get_option( self::HISTORY, array() ) as $item ) {
			if ( 'pre-restore' === ( $item['policy'] ?? '' ) ) return (string) ( $item['file'] ?? '' );
		}
		return '';
	}
	/** `addresses` de restaurar y verificar: current (por defecto) o backup. */
	private static function service_addresses( array $args ) {
		$addresses = strtolower( trim( (string) ( $args['addresses'] ?? '' ) ) );
		if ( '' === $addresses ) return 'current';
		if ( ! in_array( $addresses, array( 'current', 'backup' ), true ) ) return self::service_error( 'addresses', 'addresses debe ser current (el sitio conserva sus direcciones) o backup (conserva las del respaldo).', 400 );
		return $addresses;
	}
	/** Informe público de una reubicación: lo reescrito por tabla, red, direcciones, cachés, comprobación y avisos. */
	private static function relocation_public( array $report ) {
		return array(
			'text' => (string) ( $report['summary']['text'] ?? '' ),
			'addresses' => (string) ( $report['summary']['addresses'] ?? 'current' ),
			'origin' => $report['summary']['origin'] ?? array(),
			'destination' => $report['summary']['destination'] ?? array(),
			'rewrites' => (array) ( $report['summary']['rewrites'] ?? array() ),
			'tables' => (array) ( $report['tables'] ?? array() ),
			'network' => (array) ( $report['network'] ?? array() ),
			'caches' => (array) ( $report['caches'] ?? array() ),
			'check' => $report['check'] ?? null,
			'warnings' => array_values( (array) ( $report['warnings'] ?? array() ) ),
		);
	}
	/** Una línea para el aviso de la restauración: qué se reescribió y qué quedó del origen. */
	private static function relocation_notice( array $report ) {
		$check = is_array( $report['check'] ?? null ) ? $report['check'] : null;
		$rows = 0;
		foreach ( (array) ( $report['tables'] ?? array() ) as $counts ) $rows += (int) ( $counts['rows'] ?? 0 );
		$text = 'Origen → este sitio: se reescribieron ' . $rows . ' filas' . ( empty( $report['summary']['rewrites'] ) ? '' : ' (' . implode( '; ', $report['summary']['rewrites'] ) . ')' ) . '.';
		if ( $check ) $text .= ' Comprobación: ' . (int) $check['urls'] . ' URL y ' . (int) $check['paths'] . ' rutas del origen pendientes; sin tocar a propósito ' . (int) $check['guid'] . ' guid y ' . (int) $check['emails'] . ' correos.';
		foreach ( (array) ( $report['warnings'] ?? array() ) as $warning ) $text .= ' ' . $warning;
		return $text;
	}
	/**
	 * Comprueba un respaldo local sin tocar el sitio, con las mismas comprobaciones del paquete que la restauración
	 * (inspect_package): firma o registro, descifrado completo de un `.dgbk`, entradas, CRC y SHA-256 de cada una contra
	 * el manifiesto, manifiesto v3 estricto y el SQL en seco. Las condiciones de esta instalación (red o sitio, prefijo
	 * de tablas) se informan en `problems`; el espacio libre lo comprueba la restauración antes de escribir nada.
	 */
	public static function service_verify( $file, array $args = array() ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$addresses = self::service_addresses( $args );
		if ( is_wp_error( $addresses ) ) return $addresses;
		$path = self::service_local_file( $file );
		if ( is_wp_error( $path ) ) return $path;
		if ( ! class_exists( 'ZipArchive' ) ) return self::service_error( 'zip', 'El servidor necesita la extensión PHP ZipArchive para verificar el respaldo.', 500 );
		$secret = is_string( $args['secret'] ?? null ) ? $args['secret'] : '';
		$needs = self::service_secret_problem( $path, $secret );
		if ( $needs ) return $needs;
		// Descifrar usa la carpeta .staging/ del almacén, que una restauración también usa: sólo con el bloqueo.
		$lock = null;
		if ( Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) {
			$lock = self::service_lock();
			if ( is_wp_error( $lock ) ) return $lock;
		}
		$item = self::history_item( wp_basename( $path ) );
		$report = null;
		try {
			try {
				$report = Digitalisimo_Backups_Security::open( $path, $item, $secret );
				$zip = $report['zip'];
				$source = array();
				$data = self::inspect_package( $zip, 'untrusted' !== $report['trust'], false, $source );
			} catch ( Exception $e ) {
				return self::service_error( 'invalid', $e->getMessage(), 422 );
			}
			$checksum = @hash_file( 'sha256', $path );
			if ( false === $checksum ) return self::service_error( 'invalid', 'No se pudo calcular el SHA-256 del respaldo.', 422 );
			$problems = self::package_installation_problems( $data );
			// «Origen → este sitio»: qué cambiaría al restaurarlo aquí (o por qué no se puede).
			$relocation = null;
			if ( ! $problems ) {
				$plan = self::relocation_plan( $data, $source, array( 'addresses' => $addresses ) );
				$problems = $plan->problems();
				$relocation = $plan->summary();
			}
			return array(
				'file' => wp_basename( $path ),
				'verified' => true,
				'registered' => ! empty( $item ),
				'sha256' => $checksum,
				'size' => (int) filesize( $path ),
				'entries' => (int) $zip->numFiles,
				'container' => (string) $report['container'],
				'trust' => (string) $report['trust'],
				'signature' => (string) $report['auth'],
				'key' => isset( $report['key']['id'] ) ? Digitalisimo_Backups_Keys::short( $report['key']['id'] ) : '',
				'confirmation' => 'untrusted' === $report['trust'] ? 'RESTAURAR SIN FIRMA' : 'RESTAURAR',
				'compatible' => ! $problems,
				'problems' => $problems,
				'format' => (string) ( $data['format'] ?? '' ),
				'scope' => (string) ( $data['scope'] ?? '' ),
				'type' => (string) ( $data['type'] ?? '' ),
				'multisite' => ! empty( $data['multisite'] ),
				'table_prefix' => (string) ( $data['table_prefix'] ?? '' ),
				'created_at' => (string) ( $data['created_at'] ?? '' ),
				'plugin_version' => (string) ( $data['plugin_version'] ?? '' ),
				'relocation' => $relocation,
				'extract' => Digitalisimo_Backups_Site_Extract::summary( $data ),
				'wp_config' => self::config_report( $zip ),
			);
		} finally {
			Digitalisimo_Backups_Security::close( $report );
			if ( $lock ) self::release_operation_lock( $lock );
		}
	}

	/* ------------------------------------------------------------------ Migración (Digitalisimo_Backups_Migrate) */

	/** Datos de la pestaña «Migrar» y de `GET /migration`: nunca una clave, su credencial ni su huella. */
	private static function migration_status_data() {
		$undo = null;
		$target = Digitalisimo_Backups_Migrate::undo_target();
		if ( $target ) {
			$available = false;
			try { $available = '' !== self::local_path( (string) $target['file'] ); } catch ( Exception $e ) { $available = false; }
			if ( $available ) $undo = array( 'file' => (string) $target['file'], 'from' => (string) ( $target['migration']['from'] ?? '' ), 'date' => (string) ( $target['date'] ?? '' ) );
		}
		$active = null;
		try {
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( 'migrate' === ( $job['spec']['kind'] ?? '' ) ) { $active = self::service_job_view( $job ); break; }
		} catch ( Throwable $e ) {
			$active = null;
		}
		return array(
			'site' => home_url(),
			'multisite' => is_multisite(),
			'subdomain_install' => is_multisite() && function_exists( 'is_subdomain_install' ) && is_subdomain_install(),
			'https' => 'https' === strtolower( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) ),
			'allow_http' => Digitalisimo_Backups_Migrate::allow_http(),
			'key_problem' => Digitalisimo_Backups_Migrate::key_problem(),
			'pull_problem' => Digitalisimo_Backups_Migrate::pull_problem(),
			'keys' => Digitalisimo_Backups_Migrate::keys(),
			'log' => Digitalisimo_Backups_Migrate::public_log(),
			'rejected' => Digitalisimo_Backups_Migrate::rejected(),
			'undo' => $undo,
			'active' => $active,
		);
	}
	public static function service_migration_status() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		try {
			return self::migration_status_data();
		} catch ( Throwable $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
	}
	/**
	 * Genera una clave de migración y la devuelve una sola vez (`key`). En una red, la clave se lleva la red entera:
	 * pide el alcance de red, como crear un respaldo.
	 */
	public static function service_migration_create_key( array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		try {
			return Digitalisimo_Backups_Migrate::create_key( (int) ( $args['hours'] ?? Digitalisimo_Backups_Migrate::DEFAULT_HOURS ) );
		} catch ( Throwable $e ) {
			return self::service_error( 'migration_key', $e->getMessage(), 422 );
		}
	}
	public static function service_migration_revoke_key( $id ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		try {
			return Digitalisimo_Backups_Migrate::revoke( (string) $id );
		} catch ( InvalidArgumentException $e ) {
			return self::service_error( 'migration_key_invalid', $e->getMessage(), 400 );
		} catch ( OutOfBoundsException $e ) {
			return self::service_error( 'migration_key_missing', $e->getMessage(), 404 );
		} catch ( Throwable $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
	}
	/**
	 * «Migrar desde otro sitio»: comprueba la clave con el origen (y que los dos sitios son compatibles) y encola la
	 * migración en la cola de «Trabajos». Reemplaza este sitio o esta red: pide `MIGRAR` y, en una red, el alcance de
	 * red. La clave se usa tal cual y nunca vuelve en la respuesta.
	 */
	public static function service_migrate( $key, array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		if ( 'MIGRAR' !== strtoupper( trim( (string) ( $args['confirm'] ?? '' ) ) ) ) return self::service_error( 'confirm', 'Escribe MIGRAR para confirmar: la migración reemplaza este sitio (con una copia previa para deshacerla).', 400 );
		if ( ! is_string( $key ) || '' === trim( $key ) ) return self::service_error( 'migration_key_invalid', 'Pega la clave de migración que generaste en el sitio de origen.', 400 );
		try {
			$queued = Digitalisimo_Backups_Migrate::enqueue_pull( $key, (string) ( $args['via'] ?? 'rest' ) );
		} catch ( InvalidArgumentException $e ) {
			return self::service_error( 'migration_key_invalid', $e->getMessage(), 400 );
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			return self::service_error( 'migration_rejected', $e->getMessage(), 422 );
		} catch ( Throwable $e ) {
			return self::service_error( 'migration_unreachable', $e->getMessage(), 502 );
		}
		if ( ! empty( $queued['duplicate'] ) ) return self::service_error( 'duplicate', 'Ya hay una migración pendiente o en curso; su avance está en «Trabajos».', 409, array( 'job' => (string) $queued['job']['id'] ) );
		Digitalisimo_Backups_Jobs::schedule( ! empty( $args['spawn'] ?? true ) );
		return self::service_job_view( $queued['job'] ) + array( 'source' => (string) ( $queued['job']['spec']['migration']['source_host'] ?? '' ) );
	}
	/**
	 * «Deshacer la migración»: restaura la copia previa que creó la última migración recibida, con su propia copia previa
	 * del estado migrado. Pide `DESHACER` y, en una red, el alcance de red. El origen no cambia.
	 */
	public static function service_migration_undo( array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		if ( 'DESHACER' !== strtoupper( trim( (string) ( $args['confirm'] ?? '' ) ) ) ) return self::service_error( 'confirm', 'Escribe DESHACER para confirmar: este sitio vuelve al estado anterior a la migración.', 400 );
		try { self::ensure_ready(); } catch ( Throwable $e ) { return self::service_error( 'not_ready', $e->getMessage(), 422 ); }
		if ( function_exists( 'wp_raise_memory_limit' ) ) wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
		// Tras una caída (H7): primero el detector de procesos muertos (un trabajo cuyo proceso murió deja de contar como
		// en curso, o vuelve a la cola) y el cerrojo (que aplica el diario de una restauración interrumpida); después se
		// decide qué deshacer, con el historial ya repuesto.
		Digitalisimo_Backups_Jobs::recover_if_idle();
		$lock = self::service_lock();
		if ( is_wp_error( $lock ) ) return $lock;
		$report = null;
		$copies = self::service_safety_copies();
		try {
			$active = Digitalisimo_Backups_Jobs::active();
			if ( $active ) {
				// La restauración en curso (si la hay) es la que decide la salida: la de la migración o su vuelta a la copia previa.
				$first = Digitalisimo_Backups_Jobs::restore_active() ?: $active[0];
				$applying = 'migrate' === ( $first['spec']['kind'] ?? '' ) && in_array( (string) $first['phase'], array( 'restore', 'close' ), true );
				if ( $applying ) $message = 'La migración se está aplicando (' . Digitalisimo_Backups_Jobs::phase_label( (string) $first['phase'], $first ) . '): espera a que termine (su restauración se reanuda sola y, si falla, vuelve sola a la copia previa) y después deshazla.';
				elseif ( Digitalisimo_Backups_Jobs::is_restore( $first ) ) $message = ! empty( $first['spec']['rollback'] ) ? 'El sitio está volviendo a su copia previa (' . Digitalisimo_Backups_Jobs::phase_label( (string) $first['phase'], $first ) . '): espera a que termine.' : 'Hay una restauración en curso (' . Digitalisimo_Backups_Jobs::phase_label( (string) $first['phase'], $first ) . '): si es la de la migración, espera a que termine (se reanuda sola y, si falla, vuelve sola a la copia previa) y después deshazla.';
				elseif ( 'migrate' === ( $first['spec']['kind'] ?? '' ) ) $message = 'Hay una migración en curso (' . Digitalisimo_Backups_Jobs::phase_label( (string) $first['phase'], $first ) . '): espera a que termine o cancélala en «Trabajos» antes de deshacer.';
				else $message = 'Hay un respaldo pendiente o en curso. Cancélalo en «Trabajos» o espera a que termine antes de deshacer.';
				return self::service_error( 'busy', $message, 409, array( 'job' => (string) $first['id'] ) );
			}
			$row = Digitalisimo_Backups_Migrate::undo_target();
			if ( ! $row ) return self::service_error( 'no_migration', 'No hay ninguna migración que deshacer en este sitio.', 404 );
			$path = self::service_local_file( (string) $row['file'] );
			if ( is_wp_error( $path ) ) return self::service_error( 'no_migration', 'La copia previa de la última migración (' . (string) $row['file'] . ') ya no está en el almacén.', 404 );
			try {
				$report = Digitalisimo_Backups_Security::open( $path, self::history_item( wp_basename( $path ) ) );
				Digitalisimo_Backups_Security::require_confirmation( $report, 'RESTAURAR' );
			} catch ( Exception $e ) {
				return self::service_error( 'invalid', $e->getMessage(), 422 );
			}
			// Con el motor de «Restaurar» (por pasos, con su propia copia previa del estado migrado) y tablas exactas: lo
			// que la migración trajo y la copia previa no tiene se retira, en archivos (núcleo, plugins, temas, mu-plugins,
			// drop-ins, medios) y en tablas (H2). El sitio queda idéntico a su copia previa.
			$safety_file = self::backup_file_name( 'safety-before-restore-' );
			$safety_spec = Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'restore', 'file' => $safety_file, 'inline' => true ) );
			try {
				$job = self::restore_enqueue( $report, array( 'addresses' => 'current', 'via' => (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) ), 'safety_spec' => $safety_spec, 'sync' => ! empty( $args['sync'] ), 'exact_tables' => true, 'exact_files' => true ) );
			} catch ( Throwable $e ) {
				return self::service_error( false !== strpos( $e->getMessage(), 'en curso' ) ? 'busy' : 'restore_rejected', $e->getMessage(), false !== strpos( $e->getMessage(), 'en curso' ) ? 409 : 422 );
			}
			self::history_update( (string) $row['file'], array( 'migration' => array_merge( (array) $row['migration'], array( 'state' => 'undoing', 'undo' => (string) $job['id'] ) ) ) );
		} finally {
			Digitalisimo_Backups_Security::close( $report );
			self::release_operation_lock( $lock );
			try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
		}
		Digitalisimo_Backups_Migrate::note( 'undo', (string) $row['file'] );
		// Como «Restaurar»: los ticks de esta petición (en WP-CLI, hasta terminar) y después la pantalla, WP-Cron o REST.
		$job = self::restore_drive( $job['id'], array_key_exists( 'drive_cli', $args ) ? (bool) $args['drive_cli'] : null );
		$outcome = self::restore_outcome( $job, $copies );
		if ( is_wp_error( $outcome ) ) return $outcome;
		$from = (string) ( $row['migration']['from'] ?? '' );
		if ( ! empty( $outcome['restored'] ) ) {
			self::history_update( (string) $row['file'], array( 'migration' => array_merge( (array) $row['migration'], array( 'state' => 'undone', 'undo' => (string) $job['id'], 'undone_at' => time() ) ) ) );
			Digitalisimo_Backups_Migrate::note( 'undone', (string) $row['file'] );
		}
		return array_merge( $outcome, array( 'undone' => ! empty( $outcome['restored'] ), 'file' => (string) $row['file'], 'from' => $from ) );
	}
	/**
	 * API del sitio de origen para el destino. No usa la sesión de WordPress: la autoriza la clave de migración
	 * (cabecera, nunca la URL), con su límite de intentos. `$action`: hello, package, step, download, finish o release.
	 */
	public static function service_migration_source( $action, array $record, $client, array $body = array(), $range = '' ) {
		switch ( $action ) {
			case 'hello': return Digitalisimo_Backups_Migrate::source_hello( $record, $body );
			case 'package': return Digitalisimo_Backups_Migrate::source_package( $record, $client, $body );
			case 'step': return Digitalisimo_Backups_Migrate::source_step( $record );
			case 'download': return Digitalisimo_Backups_Migrate::source_download( $record, $range );
			case 'finish': return Digitalisimo_Backups_Migrate::source_finish( $record, $body );
			case 'release': return Digitalisimo_Backups_Migrate::source_release( $record );
		}
		return self::service_error( 'migration_action', 'Acción de migración desconocida.', 404 );
	}
}
