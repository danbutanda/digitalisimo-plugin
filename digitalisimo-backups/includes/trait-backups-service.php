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
			// «Sólo en el remoto» (I4): el ZIP local se borró a propósito; restaurar exige traerlo (fetch).
			// Tras «Traer al almacén» el ZIP vuelve a estar aquí: ya no es «sólo en el remoto» (revisión P6).
			'remote_only' => 'remote-only' === ( $item['local'] ?? '' ) && ! $available,
			// Retención en el remoto (B6): pending (se borrará), failed (no se pudo; con el motivo) o vacío.
			'remote_retention' => (string) ( ( is_array( $item['remote_prune'] ?? null ) ? $item['remote_prune'] : array() )['state'] ?? ( '' !== (string) ( $item['remote_foreign'] ?? '' ) ? 'foreign' : '' ) ),
			'remote_retention_error' => (string) ( ( is_array( $item['remote_prune'] ?? null ) ? $item['remote_prune'] : array() )['error'] ?? '' ),
			// Incrementales (docs/BACKUPS.md, «Incrementales»): cada punto es completo; null si la fila no es de una cadena.
			'chain' => self::service_chain_summary( $item ),
			// De dónde vino: `created` (hecho aquí), `uploaded` («Subir un respaldo» o «Agregar al listado») o `imported`.
			'origin' => (string) ( $item['origin'] ?? 'created' ),
			'uploaded' => self::uploaded_summary( $item ),
			// El respaldo de una red en un WordPress individual: sus sitios (se restaura uno, `site` en restore).
			'network_sites' => self::network_restores_as_site( $item ) ? (object) self::network_row_sites( $item ) : null,
		);
	}
	/**
	 * La cadena de una fila: su lugar (0 es el completo de la cadena), su base, cuánto ocupa el punto entero (los archivos
	 * de su cadena) y cuánto ahorra frente a un completo (el tamaño del completo de su cadena). null si no es de una cadena.
	 */
	private static function service_chain_summary( $item ) {
		$chain = Digitalisimo_Backups_Chain::row_chain( $item );
		if ( ! $chain ) return null;
		$history = (array) get_option( self::HISTORY, array() );
		$base = $chain['sequence'] > 0 ? Digitalisimo_Backups_Chain::link_row( $history, $chain['links'][0] ) : $item;
		$size = (int) ( $item['size'] ?? 0 );
		$total = $size;
		foreach ( $chain['links'] as $link ) {
			$row = Digitalisimo_Backups_Chain::link_row( $history, $link );
			$total += $row ? (int) ( $row['size'] ?? 0 ) : 0;
		}
		$full = $base ? (int) ( $base['size'] ?? 0 ) : 0;
		return array(
			'id' => (string) $chain['id'],
			'sequence' => (int) $chain['sequence'],
			'role' => $chain['sequence'] > 0 ? 'incremental' : 'base',
			'complete' => true,
			'base' => $chain['sequence'] > 0 ? (string) $chain['links'][0]['file'] : (string) ( $item['file'] ?? '' ),
			'base_date' => $base ? (string) ( $base['date'] ?? '' ) : '',
			'links' => count( $chain['links'] ) + 1,
			'files' => (int) ( $chain['files'] ?? 0 ),
			'point_bytes' => (int) ( $chain['bytes'] ?? 0 ),
			'chain_size' => $total,
			'saved' => $chain['sequence'] > 0 ? max( 0, $full - $size ) : 0,
			'added' => (int) ( $chain['added'] ?? 0 ),
			'changed' => (int) ( $chain['changed'] ?? 0 ),
			'deleted' => (int) ( $chain['deleted'] ?? 0 ),
			'restorable' => array() === self::chain_missing( $item, $history ),
			'missing' => array_keys( self::chain_missing( $item, $history ) ),
		);
	}
	/**
	 * Ahorro de los incrementales del historial: cuántos puntos, cuánto ocupan y cuánto ocuparían como completos (el
	 * tamaño del completo de su cadena por cada uno).
	 */
	public static function chain_saving( array $history ) {
		$out = array( 'points' => 0, 'stored' => 0, 'saved' => 0 );
		foreach ( $history as $row ) {
			$chain = Digitalisimo_Backups_Chain::row_chain( $row );
			if ( ! $chain || $chain['sequence'] < 1 ) continue;
			$base = Digitalisimo_Backups_Chain::link_row( $history, $chain['links'][0] );
			$out['points']++;
			$out['stored'] += (int) ( $row['size'] ?? 0 );
			$out['saved'] += $base ? max( 0, (int) ( $base['size'] ?? 0 ) - (int) ( $row['size'] ?? 0 ) ) : 0;
		}
		return $out;
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
		// «Último respaldo» es el último de este sitio: uno subido está en la lista, pero no dice cuándo se respaldó éste.
		$own = array_values( array_filter( $history, function( $row ) { return ! self::is_uploaded_row( $row ); } ) );
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
			'last_backup' => $own ? self::service_summary( $own[0] ) : null,
			// Incrementales: cuántos puntos, cuánto ocupan y cuánto ahorran frente a respaldos completos.
			'incremental' => self::chain_saving( $history ),
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
				'remote_only' => ! empty( $policy['remote_only'] ),
				// Incremental: cadena con un completo cada `full_every` incrementales o `full_days` días (si no, `full`).
				'mode' => 'incremental' === ( $policy['mode'] ?? '' ) ? 'incremental' : 'full',
				'full_every' => 'incremental' === ( $policy['mode'] ?? '' ) ? (int) Digitalisimo_Backups_Chain::settings( $policy )['full_every'] : 0,
				'full_days' => 'incremental' === ( $policy['mode'] ?? '' ) ? (int) Digitalisimo_Backups_Chain::settings( $policy )['full_days'] : 0,
				// «Conservar» cuenta respaldos (`points`) o, en una incremental, cadenas enteras (`chains`), y lo que guardará.
				'retain_unit' => Digitalisimo_Backups_Chain::retain_unit( $policy ),
				'retain_estimate' => Digitalisimo_Backups_Chain::estimate( $policy ),
				'enabled' => $enabled,
				'next_run' => $next ? gmdate( 'c', (int) $next ) : '',
			);
		}
		return $out;
	}
	/**
	 * Crea una automatización de respaldo completo (`$id` vacío) o cambia una, como sus formularios de la pantalla: los
	 * mismos campos y las mismas reglas (automation_input(), con el tope de «Conservar»), y lo que no se envía conserva
	 * su valor (al crear: diaria, local, «Conservar 4» y «Completo cada vez»). Campos: `schedule`, `time`, `destination`,
	 * `retain`, `retain_unit` (`points` o `chains`; sólo cuenta en una incremental), `mode` (`full` o `incremental`),
	 * `full_every`, `full_days`, `remote_only` y `enabled`. Una frecuencia, un destino, un modo o una unidad que no existen
	 * se rechazan (400), en vez de cambiarse en silencio. Devuelve la automatización como GET /automations, con `notice`
	 * si «Conservar» se acotó.
	 */
	public static function service_save_automation( array $input, $id = '', array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$fields = array( 'schedule', 'time', 'destination', 'retain', 'retain_unit', 'mode', 'full_every', 'full_days', 'remote_only', 'enabled' );
		$input = array_intersect_key( $input, array_flip( $fields ) );
		$choices = array(
			'schedule' => array_keys( self::schedule_options() ),
			'destination' => array_map( 'strval', array_keys( self::destination_choices() ) ),
			'mode' => array( 'full', 'incremental' ),
			'retain_unit' => array( 'points', 'chains' ),
		);
		foreach ( $choices as $key => $allowed_values ) {
			if ( array_key_exists( $key, $input ) && ! in_array( (string) $input[ $key ], $allowed_values, true ) ) return self::service_error( 'automation_' . $key, 'Valor no válido para «' . $key . '»: ' . implode( ', ', $allowed_values ) . '.', 400 );
		}
		foreach ( array( 'retain', 'full_every', 'full_days' ) as $key ) {
			if ( array_key_exists( $key, $input ) && ! is_numeric( $input[ $key ] ) ) return self::service_error( 'automation_' . $key, '«' . $key . '» debe ser un número.', 400 );
		}
		$id = sanitize_key( (string) $id );
		$policies = self::policies();
		$index = null;
		if ( '' !== $id ) {
			foreach ( $policies as $position => $policy ) if ( $id === (string) ( $policy['id'] ?? '' ) ) { $index = $position; break; }
			if ( null === $index ) return self::service_error( 'automation', 'La automatización no existe.', 404 );
			if ( 'full' !== ( $policies[ $index ]['type'] ?? '' ) ) return self::service_error( 'automation_partial', 'Esta automatización parcial anterior está desactivada: crea una completa.', 400 );
			$input += array_intersect_key( (array) $policies[ $index ], array_flip( $fields ) );
		} else {
			$input += array( 'schedule' => 'daily', 'destination' => 'local', 'retain' => 4, 'mode' => 'full', 'enabled' => true );
		}
		self::$automation_notice = '';
		$automation = self::automation_input( $input, $id, 'full' );
		if ( is_wp_error( $automation ) ) return self::service_error( 'automation_invalid', $automation->get_error_message(), 400 );
		if ( null === $index ) $policies[] = $automation;
		else $policies[ $index ] = $automation;
		update_option( self::POLICIES, $policies, false );
		self::sync_policy_schedules();
		foreach ( self::service_automations() as $row ) if ( $row['id'] === $automation['id'] ) return $row + array( 'notice' => trim( self::$automation_notice ) );
		return self::service_error( 'automation', 'No se pudo guardar la automatización.', 500 );
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
			// «Sólo en el remoto» (I4): sin destino remoto no aplica (el spec lo ignora).
			// Manual: completo, salvo que se pida incremental (sigue la cadena de los manuales; ver «Incrementales»).
			$incremental = ! empty( $args['incremental'] ) ? Digitalisimo_Backups_Chain::spec_value( array( 'mode' => 'incremental' ) + array_intersect_key( (array) $args, array_flip( array( 'full_every', 'full_days' ) ) ) ) : null;
			$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => $destination, 'retain' => (int) $settings['retention'], 'retention' => 'global', 'remote_only' => ! empty( $args['remote_only'] ), 'via' => (string) ( $args['via'] ?? 'rest' ), 'network' => 'network' === ( $args['context'] ?? ( is_multisite() ? 'network' : 'site' ) ), 'incremental' => $incremental ) ) );
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
	/** Si $token es la ficha de lectura vigente del trabajo $id (sin sesión: GET /jobs/<id> de REST). */
	public static function service_job_reader_ok( $id, $token ) {
		if ( ! Digitalisimo_Backups_Job_Store::valid_id( $id ) ) return false;
		try {
			$job = Digitalisimo_Backups_Job_Store::read( $id );
		} catch ( Throwable $e ) {
			return false;
		}
		return is_array( $job ) && Digitalisimo_Backups_Restore_Access::reader_ok( $job, $token );
	}
	/**
	 * Un trabajo leído con su ficha de lectura: sólo ese, sólo leerlo (no reanuda ni recupera nada), con su informe al
	 * terminar. Lo que lo hace avanzar sigue siendo la pantalla, WP-Cron, el cron del sistema o WP-CLI.
	 */
	public static function service_job_follow( $id, $token ) {
		if ( ! self::service_job_reader_ok( (string) $id, $token ) ) return self::service_error( 'reader_invalid', 'La ficha de lectura no es de este trabajo o caducó.', 403 );
		$job = Digitalisimo_Backups_Job_Store::read( (string) $id );
		return $job ? self::service_job_view( $job ) : self::service_error( 'job_missing', 'El trabajo ya no existe.', 404 );
	}
	/** La ficha de lectura del trabajo que encoló esta petición: va una vez en su respuesta (REST y WP-CLI). */
	private static function service_follow( $job_id, $token ) {
		if ( '' === (string) $token || ! Digitalisimo_Backups_Job_Store::valid_id( (string) $job_id ) ) return null;
		$route = '/digitalisimo-backups/v1/jobs/' . $job_id;
		return array(
			'job' => (string) $job_id,
			'token' => (string) $token,
			'header' => Digitalisimo_Backups_Restore_Access::READER_HEADER,
			'url' => function_exists( 'rest_url' ) ? (string) rest_url( ltrim( $route, '/' ) ) : $route,
			'expires' => 'mientras el trabajo sigue y hasta 24 h después de terminar (como mucho 7 días)',
			'note' => 'Sólo lee el estado de este trabajo, también con el sitio en mantenimiento o con otros usuarios tras restaurar. Envíala sólo en esa cabecera, sin Authorization ni la cookie de sesión, y no la guardes en registros.',
		);
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
		// Un punto incremental se restaura igual que un completo: su cadena entera tiene que estar en el almacén.
		$point = self::service_chain_point( $file );
		if ( is_wp_error( $point ) ) return $point;
		// Restaurar por partes (`parts`, `plugins`, `themes`, `sites`): sin ellos, todo, como siempre.
		$parts = self::service_parts( $args );
		if ( is_wp_error( $parts ) ) return $parts;
		$path = self::service_local_file( $file );
		if ( is_wp_error( $path ) ) return $path;
		$secret = is_string( $args['secret'] ?? null ) ? $args['secret'] : '';
		$needs = $point ? self::service_chain_secret_problem( $point, $secret ) : self::service_secret_problem( $path, $secret );
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
			$loose = false;
			if ( ! $point ) {
				try {
					$report = Digitalisimo_Backups_Security::open( $path, self::history_item( wp_basename( $path ) ), $secret );
				} catch ( Exception $e ) {
					$event = array( 'ok' => false, 'started' => false, 'message' => $e->getMessage() );
					return self::service_error( 'invalid', $e->getMessage(), 422 );
				}
				// Un punto incremental sin su fila (base de datos perdida, servidor nuevo, subido o traído de un destino): su
				// manifiesto nombra su cadena, que se busca en el almacén por nombre y se comprueba por sus manifiestos.
				try { $point = Digitalisimo_Backups_Chain_Restore::loose_item( $report['zip'], wp_basename( $path ) ); } catch ( Exception $e ) { $point = null; }
				if ( $point ) {
					$loose = true;
					Digitalisimo_Backups_Security::close( $report );
					$report = null;
				}
			}
			// El respaldo de una red en un WordPress individual: el sitio elegido (`site`) se convierte por tramos y pasa a la
			// restauración de siempre (trait-backups-network-to-single.php). `site` no vale para otro respaldo.
			if ( ! $point ) {
				$network = null;
				try { $network = self::package_metadata( $report['zip'] ); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				$convert_job = self::network_converts( $network ) ? self::service_convert_restore( $report, $path, $confirmation, $args, $addresses, $parts ) : self::network_args_problem( $args );
				if ( is_wp_error( $convert_job ) ) return $convert_job;
				if ( ! $convert_job ) unset( $convert_job );
			}
			// Un punto incremental: su cadena se comprueba aquí (con la frase, si hace falta) y su respaldo completo se
			// reconstruye por tramos en un trabajo que, al terminar, encola la restauración de siempre.
			if ( $point ) {
				// Un punto de la cadena de un sitio, en una red: se importa como un sitio de la red (la fila dice de dónde viene).
				$point_into = self::service_import_into( $args, array( 'scope' => (string) ( $point['scope'] ?? '' ), 'multisite' => 'network' === (string) ( $point['scope'] ?? '' ), 'home_url' => (string) ( $point['upload']['home'] ?? '' ) ) );
				if ( is_wp_error( $point_into ) ) return $point_into;
				$started = self::service_chain_restore( $point, $path, $secret, $confirmation, $args + array( 'into_spec' => $point_into ), $addresses, $loose || ! empty( $point['loose_links'] ) );
				if ( is_wp_error( $started ) ) {
					if ( in_array( $started->get_error_code(), array( 'digitalisimo_backups_chain_broken', 'digitalisimo_backups_chain_incomplete' ), true ) ) $event = array( 'ok' => false, 'started' => false, 'message' => $started->get_error_message() );
					return $started;
				}
				$chain_job = $started;
			}
			// Un completo (o un archivo suelto): la apertura, la confirmación y el encolado de siempre. Su cuerpo no cambia de
			// sangría para no tocar sus líneas (la restauración por partes trabaja sobre ellas).
			if ( ! isset( $chain_job ) && ! isset( $convert_job ) ) {
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
			// En una red, el respaldo de un sitio se importa como un sitio de la red: nuevo (con su dirección) o reemplazando uno.
			$into = self::service_import_into( $args, $restored );
			if ( is_wp_error( $into ) ) return $into;
			if ( $into ) {
				$extra = '';
				$safety_spec = array();
			}
			// La frase escrita sólo vive en esta petición: si se pidió, firma desde ya los respaldos nuevos.
			$adopted = false;
			$adopt_note = '';
			if ( ! empty( $args['adopt'] ) && 'secret' === $report['via'] && $report['root'] ) {
				try {
					Digitalisimo_Backups_Keys::adopt( $report['root'], $report['key'] );
					$adopted = true;
					$adopt_note .= ' La frase escrita firma ahora los respaldos nuevos (clave ' . Digitalisimo_Backups_Keys::short( $report['key']['id'] ) . ').';
				} catch ( Exception $adopt_error ) {
					$adopt_note .= ' No se guardó la frase: ' . $adopt_error->getMessage();
				}
			}
			try {
				self::$restore_reader_token = '';
				$job = self::restore_enqueue( $report, array( 'addresses' => $addresses, 'via' => (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) ), 'adopted' => $adopted, 'safety_spec' => $safety_spec, 'sync' => ! empty( $args['sync'] ), 'wp_config' => $config, 'notice' => $extra, 'into' => $into, 'keep_access' => self::keep_choice( $args, 'keep_access' ), 'keep_admins' => self::keep_choice( $args, 'keep_admins' ), 'parts' => $parts, 'token' => self::service_token( $args ) ) );
				$reader = self::$restore_reader_token;
				self::$restore_reader_token = '';
			} catch ( Throwable $e ) {
				if ( false === strpos( $e->getMessage(), 'en curso' ) ) $event = array( 'ok' => false, 'started' => false, 'message' => $e->getMessage() );
				return self::service_error( false !== strpos( $e->getMessage(), 'en curso' ) ? 'busy' : 'restore_rejected', $e->getMessage(), false !== strpos( $e->getMessage(), 'en curso' ) ? 409 : 422 );
			}
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
		// Un punto incremental: la reconstrucción y después la restauración, por ticks (en WP-CLI, hasta terminar).
		if ( isset( $chain_job ) ) return self::chain_outcome( $chain_job, $copies, array_key_exists( 'drive_cli', $args ) ? (bool) $args['drive_cli'] : null );
		if ( isset( $convert_job ) ) return self::convert_outcome( $convert_job, $copies, array_key_exists( 'drive_cli', $args ) ? (bool) $args['drive_cli'] : null );
		// El resto lo hacen ticks: los de esta petición (en WP-CLI, hasta terminar) y después la pantalla, WP-Cron o REST.
		$id = (string) $job['id'];
		$job = self::restore_drive( $id, array_key_exists( 'drive_cli', $args ) ? (bool) $args['drive_cli'] : null );
		$outcome = self::restore_outcome( $job, $copies, $adopt_note );
		// La ficha de lectura, una sola vez: con ella se sigue el trabajo hasta el final aunque cambien los usuarios.
		if ( is_array( $outcome ) ) $outcome['follow'] = self::service_follow( $id, $reader );
		// Qué se restaura (todo o por partes) y los avisos de coherencia, también mientras sigue (202).
		if ( is_array( $outcome ) && ! isset( $outcome['parts'] ) ) $outcome['parts'] = Digitalisimo_Backups_Restore_Parts::report( $parts );
		return $outcome;
	}
	/**
	 * La selección de restaurar por partes de una petición (pantalla, REST o WP-CLI): `parts` (lista o texto con comas:
	 * database, core, plugins, themes, uploads, mu-plugins, content; `all` o nada es todo), `plugins` y `themes` (carpetas
	 * concretas) y `sites` (medios de sitios de la red). Normalizada (null: todo) o WP_Error 400.
	 */
	private static function service_parts( array $args ) {
		try {
			return Digitalisimo_Backups_Restore_Parts::normalize( array_intersect_key( $args, array_flip( array( 'parts', 'plugins', 'themes', 'sites' ) ) ) );
		} catch ( InvalidArgumentException $e ) {
			return self::service_error( 'parts', $e->getMessage(), 400 );
		}
	}
	// El testigo que la pantalla ya mandó en su cookie antes de pedir la restauración (restore_request()): así el navegador
	// la sigue aunque la petición que la empieza muera en sus primeros tramos. null: uno nuevo.
	private static function service_token( array $args ) { return isset( $args['token'] ) && is_string( $args['token'] ) && preg_match( '/^[0-9a-f]{64}$/D', $args['token'] ) ? $args['token'] : null; }
	/** Para la confirmación: ver Digitalisimo_Backups_Restore_Parts::preview(). */
	private static function service_parts_preview( $zip, $parts ) { return Digitalisimo_Backups_Restore_Parts::preview( $zip, $parts, self::restore_layout() ); }
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
			// Restaurar por partes: la vuelta repone sólo lo que se estaba restaurando; lo demás no se tocó.
			try {
				$parts = Digitalisimo_Backups_Restore_Parts::from_spec( (array) $job['spec'] );
			} catch ( Throwable $e ) {
				$parts = null;
			}
			$recovered = null === $parts ? 'Se recuperaron la base de datos y los archivos anteriores' : 'Se recuperó lo que se estaba restaurando (' . Digitalisimo_Backups_Restore_Parts::label( $parts ) . '; lo demás no se tocó)';
			$message = 'success' === $followed['status'] ? 'La restauración falló. ' . $recovered . ' desde ' . $safety . ' y se retiraron los archivos nuevos: ' . $why : 'La restauración falló y la reversión también falló. Conserva ' . $safety . ' para recuperación manual: ' . ( is_array( $followed['error'] ?? null ) ? (string) $followed['error']['message'] : $why );
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
	 * La fila de un punto incremental (docs/BACKUPS.md, «Incrementales») si `$file` lo es; null si es otra cosa (un
	 * completo, uno sin registro); o el error si a su cadena le falta algo en el almacén (422 `chain_incomplete`, con qué
	 * traer). Nunca toca el sitio.
	 */
	private static function service_chain_point( $file ) {
		$name = sanitize_file_name( (string) $file );
		$item = '' === $name ? array() : self::history_item( $name );
		if ( ! $item || ! Digitalisimo_Backups_Chain::is_incremental( $item ) ) return null;
		$missing = self::chain_missing( $item );
		// Un eslabón sin fila (salió del historial) pero con su archivo en el almacén se busca por nombre y se comprueba por
		// su manifiesto, como sin historial (chain_missing() ya no lo cuenta como que falta).
		$history = (array) get_option( self::HISTORY, array() );
		$unrowed = false;
		foreach ( $item['chain']['links'] as $link ) if ( ! Digitalisimo_Backups_Chain::link_row( $history, $link ) ) $unrowed = true;
		if ( ! $missing ) return $unrowed ? array( 'loose_links' => true ) + $item : $item;
		$files = Digitalisimo_Backups_Chain::files( $item );
		// Un punto subido: su cadena se busca en el almacén por nombre; lo que falta, se sube o se trae con ese nombre.
		if ( self::is_uploaded_row( $item ) ) return self::service_error( 'chain_incomplete', 'Este punto incremental subido se restaura con su cadena y faltan en el almacén ' . count( $missing ) . ' de sus ' . count( $files ) . ' archivos (' . implode( ', ', array_slice( array_keys( $missing ), 0, 3 ) ) . ( count( $missing ) > 3 ? '…' : '' ) . '). Súbelos (con ese mismo nombre) o tráelos de su destino y vuelve a restaurar. No se cambió nada del sitio.', 422, array( 'missing' => array_keys( $missing ) ) );
		foreach ( $missing as $name => $row ) if ( ! is_array( $row ) ) return self::service_error( 'chain_broken', Digitalisimo_Backups_Chain_Restore::broken( substr( (string) ( $item['date'] ?? '' ), 0, 16 ), (int) array_search( $name, $files, true ), count( $files ) - 1, 'no está en el historial (' . $name . ')', 'Ese punto no se puede restaurar; elige otro.' ), 422 );
		$remote = count( array_filter( $missing, function( $row ) { return is_array( $row ) && self::remote_exists( $row ); } ) );
		$text = 'Este punto incremental se restaura con su cadena y faltan en el almacén ' . count( $missing ) . ' de sus ' . count( Digitalisimo_Backups_Chain::files( $item ) ) . ' archivos (' . implode( ', ', array_slice( array_keys( $missing ), 0, 3 ) ) . ( count( $missing ) > 3 ? '…' : '' ) . ').';
		$text .= $remote === count( $missing ) ? ' Tráelos con «Traer al almacén» en su fila (o fetch por REST y WP-CLI), que trae la cadena entera, y vuelve a restaurar.' : ' Alguno ya no tiene ninguna copia: este punto no se puede restaurar; elige otro.';
		return self::service_error( 'chain_incomplete', $text . ' No se cambió nada del sitio.', 422, array( 'missing' => array_keys( $missing ) ) );
	}
	/** Como service_secret_problem(), para cada archivo de la cadena de un punto incremental. */
	private static function service_chain_secret_problem( array $point, $secret ) {
		foreach ( Digitalisimo_Backups_Chain::files( $point ) as $name ) {
			$path = '';
			try { $path = self::local_path( $name ); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			if ( '' === $path ) continue;
			$problem = self::service_secret_problem( $path, $secret );
			if ( $problem ) return $problem;
		}
		return null;
	}
	/**
	 * Abre un punto incremental para verificarlo (en esta petición, como la verificación de un completo): comprueba su
	 * cadena y reconstruye su respaldo completo (Digitalisimo_Backups_Chain_Restore::materialize()), y lo abre como
	 * cualquier respaldo. El informe lleva su carpeta (que Security::close() retira) y `chain`.
	 */
	private static function service_chain_open( array $point, $path, $secret, $loose = false ) {
		$virtual = Digitalisimo_Backups_Chain_Restore::materialize( $point, self::fresh_history(), $secret, self::local_directory(), function( $row ) { return self::local_copy( $row ); }, $loose );
		try {
			$report = Digitalisimo_Backups_Security::open( $virtual['path'], $virtual['item'], $virtual['secret'] );
		} catch ( Exception $e ) {
			Digitalisimo_Backups_Security::remove_tree( $virtual['staging'] );
			throw $e;
		}
		$report['source'] = $path;
		$report['staged'] = true;
		$report['staging'] = $virtual['staging'];
		if ( 'untrusted' === $report['trust'] ) $report['vouched'] = (string) ( $virtual['vouched'] ?? '' );
		$report['chain'] = array( 'links' => $virtual['links'], 'files' => $virtual['files'], 'bytes' => $virtual['bytes'], 'trust' => $virtual['trust'], 'summary' => $virtual['summary'] );
		return $report;
	}
	/**
	 * Restaurar un punto incremental (con el bloqueo de operaciones tomado y sin otros trabajos): comprueba su cadena con la
	 * frase si hace falta (Chain_Restore::prepare(), en una carpeta nueva de `.staging/`), pide la confirmación de su
	 * confianza (firmada, registrada aquí o «sin firma») y encola el trabajo `chain`, que reconstruye el punto por tramos y
	 * lo entrega a la restauración de siempre con los argumentos de esta petición. Devuelve el trabajo o el error.
	 */
	/**
	 * En una red, el paquete de un sitio (un WordPress individual, un sitio extraído o la copia de un sitio) se importa como
	 * un sitio de la red (Digitalisimo_Backups_Site_Import): el destino pedido (`into`: `new` o el ID de un sitio; `address`:
	 * la dirección del sitio nuevo, sugerida desde el respaldo si no se da). null si el paquete no es de un sitio o esta
	 * instalación no es una red; WP_Error si lo pedido no vale.
	 */
	private static function service_import_into( array $args, $manifest ) {
		if ( ! is_array( $manifest ) || ! Digitalisimo_Backups_Site_Import::applies( $manifest ) ) return null;
		$into = Digitalisimo_Backups_Site_Import::request( $args, (string) ( $manifest['home_url'] ?? '' ) );
		if ( ! is_wp_error( $into ) ) return $into;
		$data = (array) $into->get_error_data();
		return self::service_error( substr( (string) $into->get_error_code(), strlen( 'digitalisimo_backups_' ) ), $into->get_error_message(), (int) ( $data['status'] ?? 400 ) );
	}
	private static function service_chain_restore( array $point, $path, $secret, $confirmation, array $args, $addresses, $loose ) {
		$storage = self::local_directory();
		$dir = Digitalisimo_Backups_Security::new_staging( $storage );
		$signer = null;
		try {
			try {
				$plan = Digitalisimo_Backups_Chain_Restore::prepare( $point, self::fresh_history(), $secret, $storage, function( $row ) { return self::local_copy( $row ); }, $dir, $loose, $signer );
			} catch ( Exception $e ) {
				return self::service_error( false !== strpos( $e->getMessage(), 'le faltan en el almacén' ) ? 'chain_incomplete' : 'chain_broken', $e->getMessage(), 422 );
			}
			$trust = array( 'trust' => (string) $plan['trust'], 'auth' => 'signed' === $plan['trust'] ? 'signed' : 'unsigned', 'key' => $plan['key'], 'vouched' => (string) ( $plan['vouched'] ?? '' ) );
			try {
				Digitalisimo_Backups_Security::require_confirmation( $trust, $confirmation );
			} catch ( Exception $e ) {
				return 'untrusted' === $plan['trust'] && Digitalisimo_Backups_Security::settings()['require_signed'] ? self::service_error( 'signed_only', $e->getMessage(), 403 ) : self::service_error( 'confirm_unsigned', $e->getMessage(), 400 );
			}
			// La frase escrita sólo vive en esta petición: si se pidió, firma desde ya los respaldos nuevos.
			$adopted = false;
			$note = ' ' . Digitalisimo_Backups_Chain_Restore::summary( $plan, (int) $plan['files'] );
			if ( ! empty( $args['adopt'] ) && 'secret' === $plan['via'] && $signer && is_array( $plan['key'] ) ) {
				try {
					Digitalisimo_Backups_Keys::adopt( $signer, $plan['key'] );
					$adopted = true;
					$note .= ' La frase escrita firma ahora los respaldos nuevos (clave ' . Digitalisimo_Backups_Keys::short( $plan['key']['id'] ) . ').';
				} catch ( Exception $adopt_error ) {
					$note .= ' No se guardó la frase: ' . $adopt_error->getMessage();
				}
			}
			$safety_file = self::backup_file_name( 'safety-before-restore-' );
			$reader = Digitalisimo_Backups_Restore_Access::new_reader();
			$token = self::service_token( $args ) ?? Digitalisimo_Backups_Restore_Maintenance::new_token();
			$via = (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) );
			$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
			$spec = array(
				'scope' => is_multisite() ? 'network' : 'installation',
				'type' => 'chain',
				'kind' => Digitalisimo_Backups_Chain_Job::KIND,
				'destination' => 'local',
				'policy' => 'chain-restore',
				'retain' => 1,
				'retention' => 'none',
				'site_id' => (int) get_current_blog_id(),
				'file' => wp_basename( $path ),
				'inline' => false,
				'token' => $token,
				'safety' => $safety_file,
				'reader' => array( 'hash' => $reader['hash'], 'issued' => $reader['issued'] ),
				'origin' => array( 'via' => $via, 'user_id' => $user, 'blog_id' => (int) get_current_blog_id(), 'context' => is_multisite() ? 'network' : 'site' ),
				'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
				// Lo que la restauración recibe al terminar la reconstrucción (nunca la frase).
				'restore' => array(
					'addresses' => $addresses,
					'via' => $via,
					'adopted' => $adopted,
					'safety_spec' => Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'restore', 'file' => $safety_file, 'inline' => true ) ),
					'keep_access' => self::keep_choice( $args, 'keep_access' ),
					'keep_admins' => self::keep_choice( $args, 'keep_admins' ),
					'token' => $token,
					// Importar el punto de un sitio en una red (Digitalisimo_Backups_Site_Import).
					'into' => is_array( $args['into_spec'] ?? null ) ? $args['into_spec'] : null,
					'readers' => array( $reader['hash'] ),
					'user_id' => $user,
					'notice' => trim( $note ),
					// Un punto de una red en un WordPress individual: el sitio que se convierte al terminar la reconstrucción.
					'convert' => array_intersect_key( $args, array_flip( array( 'site', 'super_admins', 'network_secrets' ) ) ),
					'parts' => self::service_parts( $args ),
				),
			);
			$queued = Digitalisimo_Backups_Jobs::enqueue( $spec );
			if ( ! empty( $queued['duplicate'] ) ) return self::service_error( 'busy', 'Ya hay una reconstrucción de un punto en curso.', 409, array( 'job' => (string) $queued['job']['id'] ) );
			$job = $queued['job'];
			// Lo comprobado (el plan, el inventario y los eslabones descifrados) pasa al área del trabajo.
			$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
			foreach ( (array) @scandir( $dir ) as $name ) {
				if ( '.' === $name || '..' === $name ) continue;
				if ( ! @rename( $dir . '/' . $name, $staging . '/' . $name ) ) {
					Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
					return self::service_error( 'storage', 'No se pudo preparar la reconstrucción del punto en el área de trabajo.', 500 );
				}
			}
			foreach ( $plan['links'] as $index => $link ) if ( 0 === strpos( (string) $link['path'], $dir . '/' ) ) $plan['links'][ $index ]['path'] = $staging . '/' . basename( (string) $link['path'] );
			file_put_contents( $staging . '/' . Digitalisimo_Backups_Chain_Restore::PLAN, wp_json_encode( $plan ) );
			$job['reader_token'] = $reader['token'];
			return $job;
		} finally {
			if ( $signer ) Digitalisimo_Backups_Sodium::memzero( $signer );
			Digitalisimo_Backups_Security::remove_tree( $dir );
		}
	}
	/**
	 * Resultado de restaurar un punto incremental: la reconstrucción y la restauración que encola, por ticks (en WP-CLI,
	 * hasta terminar). Sin terminar en esta petición: `restoring` con el trabajo en curso y el testigo del mantenimiento.
	 */
	private static function chain_outcome( array $chain, $copies, $cli ) {
		$reader = (string) ( $chain['reader_token'] ?? '' );
		$id = (string) $chain['id'];
		$job = self::restore_drive( $id, $cli );
		$restore_id = (string) ( $job['result']['restore_job'] ?? '' );
		// Un punto de una red en un WordPress individual: después de reconstruirlo se convierte su sitio (trabajo `convert`).
		$next = '' !== $restore_id && $job && 'success' === $job['status'] ? Digitalisimo_Backups_Jobs::get( $restore_id ) : null;
		if ( is_array( $next ) && Digitalisimo_Backups_Site_Convert_Job::KIND === ( $next['spec']['kind'] ?? '' ) ) return self::convert_outcome( $next, $copies, $cli, $reader );
		if ( $job && 'success' === $job['status'] && '' !== $restore_id ) {
			$restore = self::restore_drive( $restore_id, $cli );
			$outcome = self::restore_outcome( $restore, $copies );
			if ( is_array( $outcome ) ) $outcome['follow'] = self::service_follow( $restore_id, $reader );
			return $outcome;
		}
		if ( ! $job ) return self::service_error( 'restore_failed', 'El trabajo de la reconstrucción del punto ya no existe.', 500 );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return self::service_error( 'cancelled' === $job['status'] ? 'restore_cancelled' : 'chain_broken', 'El punto no se pudo reconstruir: ' . (string) ( $job['error']['message'] ?? 'la reconstrucción no terminó.' ) . ' No se cambió nada del sitio.', 'cancelled' === $job['status'] ? 409 : 422 );
		return array( 'file' => basename( (string) $job['spec']['file'] ), 'restored' => false, 'restoring' => true, 'job' => self::service_job_view( $job ), 'notice' => 'Reconstruyendo el punto incremental (' . Digitalisimo_Backups_Jobs::phase_label( (string) $job['phase'], $job ) . '); después sigue la restauración. Las continúan la pantalla Backups, WP-Cron o POST /jobs/tick.', 'safety_backup' => (string) $job['spec']['safety'], 'maintenance_token' => (string) $job['spec']['token'], 'follow' => self::service_follow( $id, $reader ) );
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
		$point = self::service_chain_point( $file );
		if ( is_wp_error( $point ) ) return $point;
		$parts = self::service_parts( $args );
		if ( is_wp_error( $parts ) ) return $parts;
		$path = self::service_local_file( $file );
		if ( is_wp_error( $path ) ) return $path;
		if ( ! class_exists( 'ZipArchive' ) ) return self::service_error( 'zip', 'El servidor necesita la extensión PHP ZipArchive para verificar el respaldo.', 500 );
		$secret = is_string( $args['secret'] ?? null ) ? $args['secret'] : '';
		$needs = $point ? self::service_chain_secret_problem( $point, $secret ) : self::service_secret_problem( $path, $secret );
		if ( $needs ) return $needs;
		// Descifrar (y reconstruir un punto incremental) usa la carpeta .staging/ del almacén, que una restauración también
		// usa: sólo con el bloqueo.
		$lock = null;
		if ( $point || Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) {
			$lock = self::service_lock();
			if ( is_wp_error( $lock ) ) return $lock;
		}
		$item = self::history_item( wp_basename( $path ) );
		$report = null;
		try {
			try {
				$report = $point ? self::service_chain_open( $point, $path, $secret, ! empty( $point['loose_links'] ) ) : Digitalisimo_Backups_Security::open( $path, $item, $secret );
				// Un punto incremental sin su fila: su cadena, por sus manifiestos (recuperación ante desastre).
				if ( ! $point ) {
					try { $loose = Digitalisimo_Backups_Chain_Restore::loose_item( $report['zip'], wp_basename( $path ) ); } catch ( Exception $e ) { $loose = null; }
					if ( $loose ) {
						Digitalisimo_Backups_Security::close( $report );
						$report = null;
						$point = $loose;
						if ( ! $lock ) {
							$lock = self::service_lock();
							if ( is_wp_error( $lock ) ) { $busy = $lock; $lock = null; return $busy; }
						}
						$report = self::service_chain_open( $loose, $path, $secret, true );
					}
				}
				$zip = $report['zip'];
				$source = array();
				$data = self::inspect_package( $zip, 'untrusted' !== $report['trust'], false, $source );
			} catch ( Exception $e ) {
				return self::service_error( $point && ! $report ? 'chain_broken' : 'invalid', $e->getMessage(), 422 );
			}
			$checksum = @hash_file( 'sha256', $path );
			if ( false === $checksum ) return self::service_error( 'invalid', 'No se pudo calcular el SHA-256 del respaldo.', 422 );
			$problems = self::package_installation_problems( $data );
			// En una red, el respaldo de un sitio se importa como un sitio de la red: dónde y qué pasa.
			$import = null;
			if ( Digitalisimo_Backups_Site_Import::applies( $data ) ) {
				$target = Digitalisimo_Backups_Site_Import::request( $args, (string) ( $source['home'] ?? $data['home_url'] ?? '' ) );
				if ( is_wp_error( $target ) ) $problems[] = $target->get_error_message();
				else $import = array( 'mode' => $target['mode'], 'site_id' => (int) $target['site_id'], 'url' => (string) $target['url'], 'text' => Digitalisimo_Backups_Site_Import::summary( $target ) );
			}
			// «Origen → este sitio»: qué cambiaría al restaurarlo aquí (o por qué no se puede).
			$relocation = null;
			$convert = null;
			if ( self::network_converts( $data ) ) {
				// El respaldo de una red en un WordPress individual: qué sitio se restaura y su «origen → este sitio».
				if ( ! $lock ) {
					$lock = self::service_lock();
					if ( is_wp_error( $lock ) ) { $busy = $lock; $lock = null; return $busy; }
				}
				$network = self::network_verify( $report, array( 'addresses' => $addresses ) + $args );
				if ( is_wp_error( $network ) ) return $network;
				$problems = $network['problems'];
				$relocation = $network['relocation'];
				$convert = $network['convert'];
				if ( null !== $parts && ! empty( $parts['sites'] ) ) $problems[] = 'En un WordPress individual no se eligen los medios de un sitio de la red: elige el sitio con `site` y la parte «medios» entera.';
			} else {
				$wrong = self::network_args_problem( $args );
				if ( $wrong ) return $wrong;
			}
			if ( ! $problems && ! $convert ) {
				$plan = self::relocation_plan( $data, $source, array( 'addresses' => $addresses, 'into' => $import ? $target : null ) );
				$problems = $plan->problems();
				$relocation = $plan->summary();
			}
			// Restaurar por partes: qué trae, qué reemplazaría la selección pedida y qué se avisa antes de confirmar.
			$preview = self::service_parts_preview( $zip, $parts );
			if ( null !== $parts && Digitalisimo_Backups_Site_Extract::SCOPE === ( $data['scope'] ?? '' ) ) $problems[] = 'Un sitio extraído de una red se restaura entero: no admite restaurar por partes.';
			if ( $preview['missing'] ) $problems[] = 'El respaldo no tiene ' . Digitalisimo_Backups_Restore_Parts::list_text( $preview['missing'], 10 ) . '.';
			// La base de datos de este servidor: qué se sustituirá, qué se avisa (versiones) y qué impide restaurar.
			$database = Digitalisimo_Backups_Db_Compat::preview( $data, (array) ( $source['compat'] ?? array() ), $zip, $parts );
			if ( null === $parts || Digitalisimo_Backups_Restore_Parts::has_database( $parts ) ) $problems = array_merge( $problems, $database['problems'] );
			return array(
				'file' => wp_basename( $path ),
				'verified' => true,
				'registered' => ! empty( $item ),
				'sha256' => $checksum,
				'size' => (int) filesize( $path ),
				'entries' => (int) $zip->numFiles,
				'container' => (string) $report['container'],
				'trust' => (string) $report['trust'],
				// Subido y registrado con este SHA-256: sin firma, pero quien lo subió ya aceptó su origen (basta RESTAURAR).
				'vouched' => (string) ( $report['vouched'] ?? '' ),
				'signature' => (string) $report['auth'],
				'key' => isset( $report['key']['id'] ) ? Digitalisimo_Backups_Keys::short( $report['key']['id'] ) : '',
				'confirmation' => Digitalisimo_Backups_Security::confirmation_word( $report ),
				'compatible' => ! $problems,
				'problems' => $problems,
				'format' => (string) ( $data['format'] ?? '' ),
				'scope' => (string) ( $data['scope'] ?? '' ),
				'type' => $point ? 'incremental' : (string) ( $data['type'] ?? '' ),
				'multisite' => ! empty( $data['multisite'] ),
				'table_prefix' => (string) ( $data['table_prefix'] ?? '' ),
				'created_at' => (string) ( $data['created_at'] ?? '' ),
				'plugin_version' => (string) ( $data['plugin_version'] ?? '' ),
				'relocation' => $relocation,
				'extract' => Digitalisimo_Backups_Site_Extract::summary( $data ),
				// El respaldo de una red en un WordPress individual: el sitio que se restauraría y los de la red.
				'convert' => $convert,
				'import' => $import,
				'wp_config' => self::config_report( $zip ),
				// Un punto incremental: se verificó su cadena entera y el respaldo completo que reconstruye.
				'chain' => $point ? array( 'sequence' => (int) $point['chain']['sequence'], 'links' => (int) $report['chain']['links'], 'files' => (int) $report['chain']['files'], 'trust' => (string) $report['chain']['trust'], 'summary' => (string) $report['chain']['summary'] ) : null,
				'parts' => $preview,
				// Antes de confirmar: quiénes conservarían su acceso si se restaura aquí (sólo nombres de usuario).
				'access' => self::service_access_preview( wp_basename( $path ), $data ),
				'database' => $database,
			);
		} finally {
			Digitalisimo_Backups_Security::close( $report );
			if ( $lock ) self::release_operation_lock( $lock );
		}
	}
	/**
	 * Para la confirmación (`verify`, WP-CLI): de qué instalación es el respaldo $file como lo decide la restauración
	 * (`identity`: same, likely u other; revisión A3) y quiénes siguen entrando con su cuenta con `keep_access` y
	 * `keep_admins` sin decir (`default`) o encendidos: quien pregunta (`you`) y los administradores de este sitio
	 * (`admins`); con uno que parece de este mismo sitio, los administradores de ahora creados después del respaldo
	 * (`newer`). `unsealed`: sin claves propias en wp-config.php, el diario las guardaría sin cifrar (revisión B2). Sólo
	 * nombres de usuario; nunca falla la verificación.
	 */
	private static function service_access_preview( $file, array $manifest ) {
		$out = array( 'applies' => false, 'identity' => 'other', 'default' => false, 'you' => '', 'admins' => array(), 'admins_total' => 0, 'newer' => array(), 'unsealed' => false, 'text' => '' );
		try {
			$extracted = Digitalisimo_Backups_Site_Extract::SCOPE === ( $manifest['scope'] ?? '' );
			$out['identity'] = $extracted ? 'other' : self::restore_identity( (string) $file, $manifest );
			$out['applies'] = 'same' !== $out['identity'];
			if ( ! $out['applies'] ) {
				$out['text'] = 'El respaldo es de esta misma instalación: al restaurarlo vuelven las cuentas, contraseñas y contraseñas de aplicación del respaldo.';
				return $out;
			}
			$out['default'] = 'other' === $out['identity'];
			$out['unsealed'] = ! Digitalisimo_Backups_Credentials::can_encrypt();
			$preview = Digitalisimo_Backups_Restore_Access::preview( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0, null, 50 );
			$out = array_merge( $out, $preview );
			$names = array();
			if ( '' !== $preview['you'] ) $names[] = '«' . $preview['you'] . '» (keep_access)';
			foreach ( array_slice( $preview['admins'], 0, 10 ) as $login ) $names[] = '«' . $login . '»';
			$list = implode( ', ', $names ) . ( $preview['admins_total'] > 10 ? ' y ' . ( $preview['admins_total'] - 10 ) . ' más' : '' );
			if ( 'likely' === $out['identity'] ) {
				$since = (string) ( $manifest['created_at'] ?? '' );
				$out['newer'] = '' !== $since ? Digitalisimo_Backups_Restore_Access::newer_admins( $since, null, 10 ) : array();
				$out['text'] = 'Parece un respaldo de este mismo sitio (misma dirección, prefijo de tablas y carpeta de WordPress o base de datos) pero no lleva la identidad de esta instalación: sin keep_access ni keep_admins se restaura como el mismo sitio y vuelven las cuentas del respaldo con sus contraseñas (las de ahora no se conservan, por si están comprometidas).' . ( $out['newer'] ? ' Administradores de ahora creados después del respaldo: ' . implode( ', ', array_map( function( $login ) { return '«' . $login . '»'; }, $out['newer'] ) ) . '.' : '' ) . ( $names ? ' Si es de otra instalación, con keep_access=true y keep_admins=true (--keep-access --keep-admins) siguen entrando ' . $list . '.' : '' );
			} else {
				$out['text'] = $names ? 'Es el respaldo de otra instalación: con keep_access y keep_admins (por defecto) siguen entrando con su usuario y su contraseña, como administradores, ' . $list . '.' : 'Es el respaldo de otra instalación: este sitio no tiene administradores que conservar (sin --user, WP-CLI no tiene una cuenta de quien restaura).';
			}
			if ( $out['unsealed'] ) $out['text'] .= ' Este sitio no tiene AUTH_KEY y SECURE_AUTH_KEY propias en wp-config.php: mientras dura la restauración, su diario (en el almacén privado, 0600) guardaría sin cifrar los hashes de las contraseñas, las sesiones y las contraseñas de aplicación de las cuentas que se conservan y las claves de la base. Defínelas antes de restaurar o usa keep_access=false y keep_admins=false.';
		} catch ( Throwable $e ) {
			$out['text'] = '';
		}
		return $out;
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
			// Las tablas apartadas por las migraciones y sus «Deshacer» (K1), con cuándo se borran solas.
			'kept_tables' => Digitalisimo_Backups_Migrate::kept_tables(),
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
		// De una red a un WordPress individual: el sitio de la red que se migra (por defecto, el principal).
		$site = $args['site'] ?? null;
		if ( null !== $site && '' !== $site && ( ! is_scalar( $site ) || ! preg_match( '/^[0-9]{1,10}$/D', trim( (string) $site ) ) || (int) $site < 1 ) ) return self::service_error( 'site', 'El sitio de la red se elige por su número (1 es el principal).', 400 );
		try {
			$queued = Digitalisimo_Backups_Migrate::enqueue_pull( $key, (string) ( $args['via'] ?? 'rest' ), array( 'keep_access' => ! array_key_exists( 'keep_access', $args ) || ! empty( $args['keep_access'] ), 'keep_admins' => ! array_key_exists( 'keep_admins', $args ) || ! empty( $args['keep_admins'] ), 'site' => null === $site || '' === $site ? 0 : (int) $site, 'import' => array( 'into' => (string) ( $args['into'] ?? '' ), 'address' => (string) ( $args['address'] ?? '' ) ) ) );
		} catch ( InvalidArgumentException $e ) {
			return self::service_error( 'migration_key_invalid', $e->getMessage(), 400 );
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			return self::service_error( 'migration_rejected', $e->getMessage(), 422 );
		} catch ( Throwable $e ) {
			return self::service_error( 'migration_unreachable', $e->getMessage(), 502 );
		}
		if ( ! empty( $queued['duplicate'] ) ) return self::service_error( 'duplicate', 'Ya hay una migración pendiente o en curso; su avance está en «Trabajos».', 409, array( 'job' => (string) $queued['job']['id'] ) );
		Digitalisimo_Backups_Jobs::schedule( ! empty( $args['spawn'] ?? true ) );
		return self::service_job_view( $queued['job'] ) + array( 'source' => (string) ( $queued['job']['spec']['migration']['source_host'] ?? '' ), 'follow' => self::service_follow( (string) $queued['job']['id'], (string) ( $queued['reader_token'] ?? '' ) ) );
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
				$job = self::restore_enqueue( $report, array( 'addresses' => 'current', 'via' => (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) ), 'safety_spec' => $safety_spec, 'sync' => ! empty( $args['sync'] ), 'exact_tables' => true, 'exact_files' => true, 'aside' => array( 'kind' => 'undo', 'migration' => (string) ( $row['migration']['job'] ?? '' ), 'from' => (string) ( $row['migration']['from'] ?? '' ) ) ) );
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
	 * «Borrar ya» las tablas apartadas (K1): Backups → Herramientas → Migrar, `POST /migration/cleanup` y
	 * `wp digitalisimo-backups migration cleanup`. Sólo las del registro de tablas apartadas que no son de una restauración
	 * en curso, nunca otra tabla; `dry_run` sólo dice cuáles. Borrar pide, en una red, el alcance de red (la base de datos
	 * es de la red).
	 */
	public static function service_migration_cleanup( array $args = array() ) {
		$dry_run = ! empty( $args['dry_run'] );
		$allowed = self::service_scope( ! empty( $args['network'] ), ! $dry_run );
		if ( is_wp_error( $allowed ) ) return $allowed;
		try {
			$report = Digitalisimo_Backups_Migrate::kept_cleanup( true, $dry_run );
			$report['message'] = Digitalisimo_Backups_Migrate::kept_message( $report );
			$report['kept_tables'] = Digitalisimo_Backups_Migrate::kept_tables();
			return $report;
		} catch ( Throwable $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
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
