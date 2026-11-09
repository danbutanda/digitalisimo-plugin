<?php
defined( 'ABSPATH' ) || exit;

/**
 * Motor de la restauración por pasos (H1–H4): las fases de Digitalisimo_Backups_Restore_Job, dentro de
 * Digitalisimo_Backups para usar sus piezas (inspección, historial, llavero, credenciales, reubicación) sin abrirlas.
 *
 * Todo lo intermedio vive en el área de trabajo del trabajo (`jobs/<id>/staging/`, privada):
 *   manifest.idx   índice del manifiesto por posición del ZIP (Digitalisimo_Backups_Manifest_Index)
 *   metadata.json  primer nivel del manifiesto (sin `entries`)
 *   source.sql     database.sql del respaldo, ya comprobado contra su CRC y su SHA-256
 *   source.zip     el ZIP de un `.dgbk`, descifrado al encolar (la frase sólo vive en esa petición)
 *   plan.json      origen, destino y opciones de la reubicación (E1), calculados con el sitio intacto
 *   tables.json    tabla del respaldo → tabla de este sitio → temporal → vieja
 *   credentials.json  claves de credenciales del origen, cifradas con las de este servidor (como el diario)
 *   units.json, changed.bin, walk.json, extraneous.txt, removed.txt  decisiones sobre los archivos
 *   tree/new, tree/old  archivos que entran y los sueltos que salen (o, si el almacén está en otro disco, una carpeta
 *                  oculta y aleatoria junto a WordPress, porque mover exige el mismo disco). Las carpetas de código que
 *                  salen se apartan junto a la suya (`.digitalisimo-old-<marca>-<nombre>`, mismo disco); la limpieza las
 *                  retira.
 * Nada de eso entra en un respaldo, se puede descargar ni lleva un secreto en claro.
 */
trait Digitalisimo_Backups_Restore_Engine {

	/* =========================================================================================== Encolar y conducir */

	/**
	 * Encola la restauración de un paquete ya abierto y aceptado (Security::open y la confirmación), con el bloqueo del
	 * almacén tomado. Un `.dgbk` se descifró en esta petición: su ZIP pasa al área de trabajo del trabajo (los ticks
	 * siguientes no tienen la frase). Devuelve el trabajo, o lanza Exception con el que lo impide (`busy`).
	 */
	private static function restore_enqueue( array $report, array $args ) {
		$rollback = is_array( $args['rollback'] ?? null ) ? $args['rollback'] : array();
		// Lo evidente se dice ya, sin crear el trabajo: un paquete parcial, de un subsitio o de otro tipo de instalación.
		if ( $report['zip'] instanceof ZipArchive ) self::restore_precheck( $report['zip'] );
		$file = wp_basename( (string) $report['source'] );
		$spec = array(
			'type' => 'restore',
			'scope' => is_multisite() ? 'network' : 'installation',
			'policy' => 'restore',
			'destination' => 'local',
			'inline' => false,
			'retain' => 1,
			'retention' => 'none',
			'file' => $file,
			'source' => array(
				'container' => (string) $report['container'],
				'trust' => (string) $report['trust'],
				'auth' => (string) $report['auth'],
				'key' => isset( $report['key']['id'] ) ? Digitalisimo_Backups_Keys::short( $report['key']['id'] ) : '',
				'staged' => 'dgbk' === $report['container'],
				'fp' => array(),
				// Sólo la envoltura en este proceso (pruebas, compatibilidad) restaura un archivo que no está en el almacén.
				'path' => ! empty( $args['sync'] ) && '' !== (string) ( $args['path'] ?? '' ) && 'dgbk' !== $report['container'] && dirname( wp_normalize_path( (string) $args['path'] ) ) !== wp_normalize_path( (string) realpath( self::local_directory() ) ) ? (string) $args['path'] : '',
			),
			'addresses' => 'backup' === ( $args['addresses'] ?? '' ) ? 'backup' : 'current',
			'rollback' => $rollback,
			// La copia previa: fijada al encolar (pantalla, REST, WP-CLI) o, en la envoltura, al empezar su fase.
			'safety' => $rollback ? '' : (string) ( $args['safety_spec']['file'] ?? '' ),
			'safety_spec' => $rollback || ! is_array( $args['safety_spec'] ?? null ) ? array() : $args['safety_spec'],
			'tag' => bin2hex( random_bytes( 3 ) ),
			'token' => (string) ( $args['token'] ?? Digitalisimo_Backups_Restore_Maintenance::new_token() ),
			'adopted' => ! empty( $args['adopted'] ),
			'sync' => ! empty( $args['sync'] ),
			'site_id' => (int) get_current_blog_id(),
			'origin' => array(
				'via' => (string) ( $args['via'] ?? 'admin-post' ),
				'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'blog_id' => (int) get_current_blog_id(),
				'context' => is_multisite() ? 'network' : 'site',
			),
			'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
		);
		$queued = Digitalisimo_Backups_Jobs::enqueue_restore( $spec, function( $job ) use ( $report ) {
			if ( 'dgbk' === $report['container'] ) {
				$target = Digitalisimo_Backups_Job_Store::staging( $job['id'] ) . '/source.zip';
				if ( ! @rename( (string) $report['path'], $target ) && ! @copy( (string) $report['path'], $target ) ) throw new Exception( 'No se pudo preparar el respaldo descifrado en el área de trabajo.' );
				if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target, 0600 );
			}
			$job['spec']['source']['fp'] = Digitalisimo_Backups_Backup_Job::fingerprint( self::restore_source_path( $job ) );
			return Digitalisimo_Backups_Job_Store::write( $job );
		} );
		if ( ! empty( $queued['busy'] ) ) {
			$other = $queued['job'];
			throw new Exception( Digitalisimo_Backups_Jobs::is_restore( $other ) ? 'Ya hay una restauración en curso (' . Digitalisimo_Backups_Jobs::phase_label( (string) $other['phase'] ) . '). Espera a que termine.' : 'Hay un respaldo pendiente o en curso. Cancélalo en «Trabajos» o espera a que termine antes de restaurar.' );
		}
		return $queued['job'];
	}
	/**
	 * Alcance del paquete contra esta instalación, con los mismos mensajes que la verificación del trabajo. Si sus
	 * metadatos no se leen, lo dirá la verificación completa en su orden de siempre (primero el contenido).
	 */
	private static function restore_precheck( $zip ) {
		try {
			$data = self::package_metadata( $zip );
		} catch ( Exception $e ) {
			return;
		}
		if ( in_array( $data['type'] ?? '', array( 'incremental', 'database' ), true ) ) throw new Exception( 'Este paquete parcial no admite restauración completa sin su cadena de respaldo.' );
		if ( 'site' === ( $data['scope'] ?? '' ) ) throw new Exception( 'Una exportación anterior de subsitio no puede reemplazar una instalación completa.' );
		if ( ! in_array( $data['scope'] ?? '', array( 'network', 'installation' ), true ) ) throw new Exception( 'El alcance del respaldo completo no es compatible.' );
		foreach ( self::package_installation_problems( $data ) as $problem ) throw new Exception( $problem );
	}
	/**
	 * Hace avanzar la restauración $id (y, si falla tras empezar el cambio, su vuelta a la copia previa) en esta
	 * petición: en la web hasta el límite de la petición (lo demás lo siguen la pantalla, WP-Cron o `POST /jobs/tick`); en
	 * WP-CLI hasta terminar, esperando los reintentos. Devuelve el trabajo, ya terminado o no.
	 */
	private static function restore_drive( $id, $cli = null ) {
		$cli = null === $cli ? 'cli' === PHP_SAPI : (bool) $cli;
		$job = Digitalisimo_Backups_Jobs::get( $id );
		$guard = 0;
		while ( $job && $guard++ < 100000 ) {
			$current = self::restore_followed( $job );
			if ( Digitalisimo_Backups_Job_Store::is_terminal( $current ) ) break;
			if ( ! $cli && Digitalisimo_Backups_Step_Budget::request_remaining() < 2 ) break;
			$wait = (int) $current['retry_at'] - time();
			if ( $wait > 0 ) {
				if ( ! $cli ) break;
				sleep( min( $wait, 30 ) );
			}
			$tick = Digitalisimo_Backups_Jobs::tick( $cli ? PHP_INT_MAX : null );
			if ( 'busy' === $tick['result'] || 'error' === $tick['result'] ) {
				if ( ! $cli ) break;
				sleep( 1 );
			}
			$job = Digitalisimo_Backups_Jobs::get( $id );
		}
		return $job;
	}
	/** La vuelta a la copia previa de una restauración fallida, si se encoló; si no, la restauración misma. */
	private static function restore_followed( array $job ) {
		if ( 'failed' !== $job['status'] ) return $job;
		$info = self::restore_rollback_info( $job['id'] );
		$rollback = '' !== $info['job'] ? Digitalisimo_Backups_Jobs::get( $info['job'] ) : null;
		return $rollback ? $rollback : $job;
	}
	/** La vuelta a la copia previa que encoló la restauración $id al fallar, y por qué falló: array( job, cause ). */
	private static function restore_rollback_info( $id ) {
		$path = Digitalisimo_Backups_Job_Store::directory( $id ) . '/rollback.json';
		$data = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		return array( 'job' => is_array( $data ) && Digitalisimo_Backups_Job_Store::valid_id( $data['job'] ?? '' ) ? (string) $data['job'] : '', 'cause' => is_array( $data ) ? (string) ( $data['cause'] ?? '' ) : '' );
	}

	/* =========================================================================================== Fases */

	/** @internal Una fase de Digitalisimo_Backups_Restore_Job. */
	public static function restore_phase( $phase, array $job ) {
		// Con lo que permita el hosting (WP_MAX_MEMORY_LIMIT); las fases están pensadas para caber en 64 MB aunque no suba.
		if ( function_exists( 'wp_raise_memory_limit' ) ) wp_raise_memory_limit( 'admin' );
		if ( Digitalisimo_Backups_Job_Store::JOB_FORMAT !== (int) ( $job['spec']['engine']['job_format'] ?? 0 ) || 'restore' !== ( $job['spec']['type'] ?? '' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El trabajo de restauración se creó con otra versión del motor; vuelve a iniciarlo.' );
		// Desde la base de datos hasta el final, el sitio está en mantenimiento (los ticks con el testigo pasan).
		if ( in_array( $phase, array( 'database', 'files', 'switch', 'finish' ), true ) ) Digitalisimo_Backups_Restore_Maintenance::hold( (string) $job['spec']['token'], self::restore_switched( $job ), $job['id'] );
		switch ( $phase ) {
			case 'inspect': return self::restore_inspect( $job );
			case 'safety': return self::restore_safety( $job );
			case 'database': return self::restore_database( $job );
			case 'files': return self::restore_files( $job );
			case 'switch': return self::restore_switch( $job );
			case 'finish': return self::restore_finish( $job );
			case 'cleanup': return self::restore_cleanup( $job );
		}
		throw new Digitalisimo_Backups_Permanent_Error( 'La restauración está en una fase desconocida.' );
	}
	/** @internal Nombre de la copia previa de una restauración (el que tendrá o tiene; '' si aún no empezó o es la vuelta a ella). */
	public static function restore_safety_file( array $job ) {
		return (string) ( $job['checkpoint']['safety']['file'] ?? $job['spec']['safety'] ?? '' );
	}
	/** Ya llegó al cambio del sitio (tablas o archivos): desde aquí, el mantenimiento no caduca. */
	private static function restore_switched( array $job ) {
		return in_array( (string) $job['phase'], array( 'switch', 'finish', 'cleanup' ), true ) || ! empty( $job['checkpoint']['switch'] );
	}
	/**
	 * El sitio cambió de verdad: pasó el RENAME (o la transacción) de las tablas. Un fallo en el paso de las tablas antes
	 * de cambiarlas (el diario, un RENAME rechazado, que es atómico) no tocó nada: no hace falta volver a la copia previa.
	 */
	private static function restore_changed( array $job ) {
		if ( ! self::restore_switched( $job ) ) return false;
		$cursor = (array) ( $job['checkpoint']['switch'] ?? array() );
		if ( 'switch' !== (string) $job['phase'] || 'tables' !== (string) ( $cursor['step'] ?? 'tables' ) ) return true;
		try {
			if ( ! is_file( self::restore_staging( $job ) . '/swap.started' ) ) return false;
			$tables = self::restore_read_json( $job, 'tables.json', false );
			if ( ! is_array( $tables ) || ! $tables ) return true;
			foreach ( $tables as $row ) if ( ! self::restore_table_exists( (string) $row['temp'] ) ) return true;
			return false;
		} catch ( Throwable $e ) {
			return true;
		}
	}

	/* ------------------------------------------------------------------------------------------- Comunes */

	private static function restore_staging( array $job ) { return Digitalisimo_Backups_Job_Store::staging( $job['id'] ); }
	/** ZIP que se restaura: el del almacén, o el descifrado al encolar. */
	private static function restore_source_path( array $job ) {
		if ( ! empty( $job['spec']['source']['staged'] ) ) return Digitalisimo_Backups_Job_Store::directory( $job['id'] ) . '/' . Digitalisimo_Backups_Job_Store::STAGING . '/source.zip';
		if ( '' !== (string) ( $job['spec']['source']['path'] ?? '' ) ) return (string) $job['spec']['source']['path'];
		return rtrim( self::local_directory(), '/\\' ) . '/' . basename( (string) $job['spec']['file'] );
	}
	/** Abre el ZIP de la restauración comprobando que es el mismo archivo que se aceptó al encolar. */
	private static function restore_zip( array $job, $flags = 0 ) {
		$path = self::restore_source_path( $job );
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo que se estaba restaurando ya no está en el almacén.' );
		$fp = (array) ( $job['spec']['source']['fp'] ?? array() );
		if ( $fp && ! Digitalisimo_Backups_Backup_Job::same_fingerprint( Digitalisimo_Backups_Backup_Job::fingerprint( $path ), $fp ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo cambió en el almacén durante la restauración; no se sigue con un archivo distinto del comprobado.' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, $flags ) ) throw new Digitalisimo_Backups_Permanent_Error( 'No se puede abrir el ZIP del respaldo.' );
		return $zip;
	}
	private static function restore_read_json( array $job, $name, $required = true ) {
		$path = self::restore_staging( $job ) . '/' . $name;
		$data = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		if ( ! is_array( $data ) ) {
			if ( ! $required ) return null;
			throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Falta lo que la verificación dejó en el área de trabajo; se verificará otra vez.', 'inspect' );
		}
		return $data;
	}
	private static function restore_write_json( array $job, $name, $data ) {
		$path = self::restore_staging( $job ) . '/' . $name;
		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) || is_link( $path ) || strlen( $json ) !== @file_put_contents( $path . '.tmp', $json ) || ! @rename( $path . '.tmp', $path ) ) throw new Exception( 'No se pudo guardar el avance de la restauración en el área de trabajo; revisa el espacio libre del almacén.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
	}
	private static function restore_trusted( array $job ) { return 'untrusted' !== ( $job['spec']['source']['trust'] ?? 'untrusted' ); }
	/** Plazo del tramo: el del tick, con la escala adaptativa de T2 y como mucho el tope de un tramo. Anota la memoria con la que empieza. */
	private static function restore_deadline( array $job, $phase, array $cursor ) {
		self::$restore_memory_start = memory_get_usage( true );
		return Digitalisimo_Backups_Step_Budget::deadline( Digitalisimo_Backups_Step_Budget::scale( $job, $phase, $cursor ), max( Digitalisimo_Backups_Step_Budget::slice_cap(), 10.0 ) );
	}
	/** @var int|null Memoria real (memory_get_usage( true )) al empezar el tramo en curso. */
	private static $restore_memory_start = null;
	/**
	 * El tramo debe cerrar: su plazo o su tope de unidades (el medidor de T2), o la memoria. La memoria no se mide con la
	 * reserva fija de T2 bajo memory_limit (un octavo, 8 MB con 64 MB): WordPress con sus plugins ya ocupa 52 a 56 de 64 MB
	 * en Playground, así que la reserva cerraría cada tramo tras una sola unidad (miles de tramos, cada uno con su punto
	 * de control y su evento). Lo que una fase de la restauración añade por unidad es poco y se libera (lecturas por
	 * trozos, una sentencia), así que el tramo cierra si su memoria creció más de 4 MB desde que empezó (algo se acumula)
	 * o si quedan menos de 2 MB hasta memory_limit. La primera unidad de cada tramo siempre se hace.
	 */
	private static function restore_exhausted( $deadline, $units ) {
		$cap = Digitalisimo_Backups_Step_Budget::units();
		if ( ( $cap && $units >= $cap ) || microtime( true ) >= $deadline ) return true;
		$limit = Digitalisimo_Backups_Step_Budget::memory_limit();
		if ( ! $limit ) return false;
		$used = memory_get_usage( true );
		if ( $limit - $used < 2097152 ) return true;
		return null !== self::$restore_memory_start && $used - self::$restore_memory_start > 4194304;
	}
	/** El tramo terminó sin acabar la fase: la misma fase sigue desde $cursor (los intentos vuelven a cero). */
	private static function restore_slice( $phase, array $cursor, $message, $started, $units, array $job ) {
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, $phase, $cursor );
		$cursor['last'] = Digitalisimo_Backups_Step_Budget::measure( $started, $units, $cursor['scale'] );
		return array( 'next' => $phase, 'checkpoint' => $cursor, 'message' => $message );
	}
	private static function restore_progress( $phase, $fraction, $message ) {
		$floors = Digitalisimo_Backups_Restore_Job::FLOORS;
		$phases = Digitalisimo_Backups_Restore_Job::PHASES;
		$next = $phases[ array_search( $phase, $phases, true ) + 1 ] ?? null;
		$top = null === $next ? 99 : $floors[ $next ];
		Digitalisimo_Backups_Jobs::progress( $floors[ $phase ] + (int) floor( ( $top - $floors[ $phase ] ) * max( 0, min( 1, (float) $fraction ) ) ), $message );
	}
	/** Ruta relativa a la raíz de WordPress de una entrada del ZIP que se restaura, o null si no se escribe. */
	private static function restore_target( $name, array $skip ) {
		$name = str_replace( '\\', '/', (string) $name );
		if ( 0 !== strpos( $name, 'wordpress/' ) || '/' === substr( $name, -1 ) ) return null;
		$relative = substr( $name, strlen( 'wordpress/' ) );
		if ( '' === $relative || 'wp-config.php' === $relative || '.maintenance' === $relative || 0 === strpos( $relative, '.git/' ) || isset( $skip[ $relative ] ) ) return null;
		// Las áreas de trabajo y las carpetas apartadas de una restauración nunca se restauran.
		if ( false !== strpos( '/' . $relative, '/.digitalisimo-' ) ) return null;
		return $relative;
	}
	/** Archivos activos de la base de datos SQLite (Playground): nunca se sobrescriben. Relativos a la raíz. */
	private static function restore_skip() {
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$skip = array();
		foreach ( array_keys( self::active_sqlite_database_files() ) as $path ) {
			$path = wp_normalize_path( $path );
			if ( 0 === strpos( $path, $root ) ) $skip[ substr( $path, strlen( $root ) ) ] = true;
		}
		return $skip;
	}

	/* ------------------------------------------------------------------------------------------- 1. Verificación */

	/**
	 * Todo lo que puede rechazar el paquete, antes de la copia previa y de cualquier escritura: las mismas reglas que la
	 * restauración en una petición (inspect_package()), por tramos y con memoria constante. Un rechazo es permanente.
	 */
	private static function restore_inspect( array $job ) {
		$cursor = (array) ( $job['checkpoint']['inspect'] ?? array() );
		$started = microtime( true );
		$deadline = self::restore_deadline( $job, 'inspect', $cursor );
		$step = (string) ( $cursor['step'] ?? 'open' );
		try {
			if ( 'open' === $step ) {
				$cursor = self::restore_inspect_open( $job );
				return self::restore_slice( 'inspect', $cursor, 'Comprobando cada archivo del respaldo…', $started, 1, $job );
			}
			if ( 'content' === $step ) {
				$cursor = self::restore_inspect_content( $job, $cursor, $deadline );
				return self::restore_slice( 'inspect', $cursor, 'content' === $cursor['step'] ? 'Comprobando cada archivo del respaldo (' . (int) $cursor['i'] . ' de ' . (int) $cursor['count'] . ')…' : 'Comprobando la base de datos del respaldo en seco…', $started, (int) ( $cursor['units'] ?? 0 ), $job );
			}
			if ( 'sql' === $step ) {
				$cursor = self::restore_sql( $job, 'scan', $cursor, $deadline );
				return self::restore_slice( 'inspect', $cursor, 'sql' === $cursor['step'] ? 'Comprobando la base de datos del respaldo en seco…' : 'Calculando el plan de la restauración…', $started, (int) ( $cursor['units'] ?? 0 ), $job );
			}
			if ( 'plan' === $step ) {
				self::restore_inspect_plan( $job, $cursor );
				$next = empty( $job['spec']['rollback'] ) ? 'safety' : 'database';
				return array( 'next' => $next, 'checkpoint' => array( 'done' => time(), 'entries' => (int) ( $cursor['count'] ?? 0 ), 'v3' => ! empty( $cursor['v3'] ), 'sql' => (array) ( $cursor['sql'] ?? array() ), 'statements' => (int) ( $cursor['statements'] ?? 0 ) ), 'message' => 'safety' === $next ? 'Respaldo comprobado; creando la copia previa…' : 'Copia previa comprobada; importando su base de datos…' );
			}
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			throw $e;
		} catch ( Digitalisimo_Backups_Checkpoint_Invalid $e ) {
			throw $e;
		} catch ( Digitalisimo_Backups_Job_Cancelled $e ) {
			throw $e;
		} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
			throw $e;
		} catch ( Exception $e ) {
			// Lo que dice del paquete no cambia al repetir: es un rechazo, antes de tocar nada.
			if ( self::restore_transient( $e ) ) throw $e;
			throw new Digitalisimo_Backups_Permanent_Error( $e->getMessage() );
		}
		throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La verificación perdió su punto de control; se repetirá.', 'inspect' );
	}
	/** Fallos de disco o de lectura que pueden pasar al repetir (no dicen nada del paquete). */
	private static function restore_transient( Exception $e ) {
		return (bool) preg_match( '/espacio libre del almacén|No se pudo guardar el avance|No se pudo escribir el índice|No se pudo preparar el índice|área de trabajo/u', $e->getMessage() );
	}
	/**
	 * Entradas del ZIP e índice del manifiesto en disco. Como la restauración de siempre (inspect_package()), lo que diga
	 * el manifiesto se juzga después de leer cada entrada: aquí sólo se anota.
	 */
	private static function restore_inspect_open( array $job ) {
		$zip = self::restore_zip( $job, ZipArchive::CHECKCONS );
		try {
			Digitalisimo_Backups_Archive_Inspector::entries( $zip, self::restore_trusted( $job ) );
			$staging = self::restore_staging( $job );
			$cursor = array( 'step' => 'content', 'i' => 0, 'count' => (int) $zip->numFiles, 'v3' => false, 'manifest' => false !== $zip->locateName( 'manifest.json' ), 'manifest_entries' => null, 'manifest_error' => '', 'format' => '', 'observed' => 0, 'total' => 0, 'present' => array(), 'mismatch' => null, 'sql' => null );
			if ( $cursor['manifest'] ) {
				try {
					$built = Digitalisimo_Backups_Manifest_Index::build( $zip, $staging . '/manifest.idx', false );
					$cursor['manifest_entries'] = $built['entries'];
					$cursor['format'] = (string) ( $built['data']['format'] ?? '' );
					$cursor['v3'] = 'digitalisimo-backup/v3' === $cursor['format'] && null !== $built['index'];
					if ( $built['index'] ) $built['index']->close();
				} catch ( Exception $e ) {
					$cursor['manifest_error'] = $e->getMessage();
				}
				if ( ! $cursor['v3'] ) @unlink( $staging . '/manifest.idx' );
			}
			return $cursor;
		} finally {
			self::close_quietly( $zip );
		}
	}
	/**
	 * Lo que se comprueba tras leer todas las entradas, en el orden de siempre: piezas obligatorias, manifiesto (JSON,
	 * formato, v3, archivos obligatorios, totales y SHA-256), metadatos y alcance, condiciones de esta instalación,
	 * manifiesto v3 estricto, base de datos y espacio.
	 */
	private static function restore_inspect_after( array $job, array $cursor, $zip ) {
		if ( false === $zip->locateName( 'database.sql' ) || ( ! $cursor['manifest'] && false === $zip->locateName( 'migration.json' ) ) ) throw new Exception( 'Falta SQL o metadata obligatoria en el ZIP.' );
		if ( $cursor['manifest'] ) {
			if ( '' !== $cursor['manifest_error'] ) throw new Exception( false !== strpos( $cursor['manifest_error'], 'tamaño permitido' ) ? $cursor['manifest_error'] : 'El manifiesto del ZIP no es JSON válido.' );
			if ( ! in_array( $cursor['format'], array( '', 'digitalisimo-backup/v2', 'digitalisimo-backup/v3' ), true ) ) throw new Exception( 'El formato del manifiesto no es compatible.' );
		}
		$data = self::package_metadata( $zip );
		if ( 'digitalisimo-backup/v3' === $cursor['format'] ) {
			$required = array( 'database.sql', 'wordpress/wp-config.php', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' );
			if ( 3 !== ( $data['backup_format_version'] ?? null ) || 'installation' !== ( $data['scope'] ?? '' ) || 'full' !== ( $data['type'] ?? '' ) || null === $cursor['manifest_entries'] || (int) $cursor['manifest_entries'] !== (int) $cursor['observed'] ) throw new Exception( 'El manifiesto completo no coincide con el ZIP.' );
			foreach ( $required as $file ) if ( empty( $cursor['present'][ $file ] ) ) throw new Exception( 'Falta el archivo obligatorio ' . $file . ' en el respaldo completo.' );
			if ( (int) ( $data['file_count'] ?? -1 ) !== (int) $cursor['observed'] - 1 || (int) ( $data['uncompressed_size'] ?? -1 ) !== (int) $cursor['total'] ) throw new Exception( 'El tamaño o el número de archivos no coincide con el manifiesto.' );
			if ( null !== $cursor['mismatch'] ) throw new Exception( 'Falló el SHA-256 de ' . $cursor['mismatch'] . ' en el manifiesto.' );
		}
		if ( in_array( $data['type'] ?? '', array( 'incremental', 'database' ), true ) ) throw new Exception( 'Este paquete parcial no admite restauración completa sin su cadena de respaldo.' );
		if ( 'site' === ( $data['scope'] ?? '' ) ) throw new Exception( 'Una exportación anterior de subsitio no puede reemplazar una instalación completa.' );
		if ( ! in_array( $data['scope'] ?? '', array( 'network', 'installation' ), true ) ) throw new Exception( 'El alcance del respaldo completo no es compatible.' );
		foreach ( self::package_installation_problems( $data ) as $problem ) throw new Exception( $problem );
		if ( 'digitalisimo-backup/v3' === ( $data['format'] ?? '' ) ) $data = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
		$sql_stat = $zip->statName( 'database.sql' );
		if ( ! $sql_stat || empty( $sql_stat['size'] ) ) throw new Exception( 'El ZIP no contiene una base de datos válida.' );
		// Una ruta que es a la vez archivo y carpeta no puede quedar como dice el respaldo: se rechaza antes de tocar nada.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			for ( $parent = dirname( $name ); '.' !== $parent && '' !== $parent && 'wordpress' !== $parent; $parent = dirname( $parent ) ) {
				if ( false !== $zip->locateName( $parent ) ) throw new Exception( 'El ZIP contiene una ruta que es a la vez archivo y carpeta: ' . substr( $parent, 0, 120 ) . '.' );
			}
		}
		Digitalisimo_Backups_Archive_Inspector::space( $zip, ABSPATH );
		// Además, lo que cambia convive con lo anterior hasta el cambio.
		self::restore_space( $zip );
		self::restore_write_json( $job, 'metadata.json', $data );
		$cursor['v3'] = 'digitalisimo-backup/v3' === ( $data['format'] ?? '' ) && $cursor['v3'];
		return array( 'step' => 'sql', 'offset' => 0 ) + $cursor;
	}
	/** CRC y SHA-256 de cada entrada contra el índice, por tramos; database.sql queda en el área de trabajo. */
	private static function restore_inspect_content( array $job, array $cursor, $deadline ) {
		$zip = self::restore_zip( $job );
		$staging = self::restore_staging( $job );
		$index = $cursor['v3'] ? Digitalisimo_Backups_Manifest_Index::open( $staging . '/manifest.idx', (int) $cursor['count'] ) : null;
		$required = array( 'database.sql', 'wordpress/wp-config.php', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' );
		$units = 0;
		try {
			$count = (int) $cursor['count'];
			if ( (int) $zip->numFiles !== $count ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo cambió durante la restauración.' );
			for ( $i = (int) $cursor['i']; $i < $count; $i++ ) {
				if ( $units > 0 && self::restore_exhausted( $deadline, $units ) ) break;
				if ( 0 === $i % 50 ) self::restore_progress( 'inspect', 0.9 * $i / max( 1, $count ), 'Comprobando archivo ' . ( $i + 1 ) . ' de ' . $count );
				$stat = $zip->statIndex( $i );
				if ( ! $stat || ! isset( $stat['name'], $stat['size'], $stat['crc'] ) ) throw new Exception( 'Una entrada ZIP no tiene metadatos completos.' );
				$name = $stat['name'];
				if ( $zip->locateName( $name ) !== $i ) throw new Exception( 'El ZIP contiene nombres de archivo duplicados.' );
				$units++;
				if ( '/' === substr( $name, -1 ) ) { $cursor['i'] = $i + 1; continue; }
				$sql = null;
				$part = $staging . '/source.sql.part';
				if ( 'database.sql' === $name ) {
					@unlink( $part );
					$sql = @fopen( $part, 'xb' );
					if ( ! $sql ) throw new Exception( 'No se pudo preparar la base de datos del respaldo en el área de trabajo.' );
				}
				try {
					$actual = self::hash_zip_entry( $zip, $i, $name, $sql ? function( $at, $entry, $chunk ) use ( $sql ) { if ( strlen( $chunk ) !== fwrite( $sql, $chunk ) ) throw new Exception( 'No se pudo copiar la base de datos del respaldo al área de trabajo; revisa el espacio libre del almacén.' ); } : null );
				} finally {
					if ( $sql ) fclose( $sql );
				}
				if ( $actual['size'] !== (int) $stat['size'] || $actual['crc'] !== sprintf( '%08x', $stat['crc'] ) ) throw new Exception( 'Falló la integridad de ' . $name . ' dentro del ZIP.' );
				if ( 'manifest.json' !== $name ) {
					$cursor['observed']++;
					$cursor['total'] += $actual['size'];
					if ( in_array( $name, $required, true ) ) $cursor['present'][ $name ] = true;
					if ( $index && null === $cursor['mismatch'] ) {
						$expected = $index->get( $i );
						if ( ! is_array( $expected ) || $expected['size'] !== $actual['size'] || ! hash_equals( $expected['sha256'], $actual['sha256'] ) ) $cursor['mismatch'] = $name;
					}
				}
				if ( 'database.sql' === $name ) {
					if ( ! @rename( $part, $staging . '/source.sql' ) ) throw new Exception( 'No se pudo cerrar la base de datos del respaldo en el área de trabajo.' );
					$cursor['sql'] = array( 'size' => $actual['size'], 'sha256' => $actual['sha256'], 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $staging . '/source.sql' ) );
				}
				$cursor['i'] = $i + 1;
			}
			$cursor['units'] = $units;
			if ( (int) $cursor['i'] < $count ) return $cursor;
			return self::restore_inspect_after( $job, $cursor, $zip );
		} finally {
			if ( $index ) $index->close();
			self::close_quietly( $zip );
		}
	}
	/**
	 * Espacio libre: lo que crece cada archivo que cambia, más el mayor y 64 MiB, como siempre; los archivos que cambian
	 * conviven con los anteriores hasta el cambio, así que cuenta su tamaño completo.
	 */
	private static function restore_space( $zip ) {
		$root = rtrim( wp_normalize_path( ABSPATH ), '/' ) . '/';
		$needed = Digitalisimo_Backups_Archive_Inspector::MARGIN;
		$largest = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = str_replace( '\\', '/', (string) $stat['name'] );
			if ( '/' === substr( $name, -1 ) || 0 !== strpos( $name, 'wordpress/' ) ) continue;
			$relative = substr( $name, 10 );
			if ( 'wp-config.php' === $relative ) continue;
			$size = max( 0, (int) $stat['size'] );
			$largest = max( $largest, $size );
			if ( ! is_file( $root . $relative ) || (int) @filesize( $root . $relative ) !== $size ) $needed += $size;
		}
		$needed += $largest;
		$free = Digitalisimo_Backups_Archive_Inspector::free_space( $root );
		if ( null !== $free && $free < $needed ) throw new Exception( 'No hay espacio suficiente para restaurar: se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres en el disco de WordPress y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '.' );
	}
	/** Reubicación, tablas temporales, claves de credenciales del origen y dónde se preparan los archivos. */
	private static function restore_inspect_plan( array $job, array $cursor ) {
		global $wpdb;
		$data = self::restore_read_json( $job, 'metadata.json' );
		$probe = (array) ( $cursor['probe'] ?? array() );
		$source = Digitalisimo_Backups_Relocate::origin_of( $data, $probe );
		$source['rename_problems'] = (array) ( $cursor['rename_problems'] ?? array() );
		if ( isset( $cursor['budget'] ) ) $source['budget'] = (int) $cursor['budget'];
		$rollback = ! empty( $job['spec']['rollback'] );
		$args = array( 'addresses' => 'backup' === ( $job['spec']['addresses'] ?? '' ) ? 'backup' : 'current', 'tables' => is_array( $data['database_tables'] ?? null ) ? $data['database_tables'] : null );
		$destination = Digitalisimo_Backups_Relocate::describe();
		// La vuelta a la copia previa es de esta misma instalación: nada que reubicar, aunque el sitio esté a medio cambiar.
		if ( ! $rollback ) {
			$plan = Digitalisimo_Backups_Relocate::plan( $source, $destination, $args );
			if ( $plan->problems() ) throw new Digitalisimo_Backups_Permanent_Error( implode( ' ', $plan->problems() ) );
		}
		// Con el mismo prefijo, las tablas temporales también renombran: una vista no se puede restaurar por pasos.
		if ( ! empty( $cursor['views'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La restauración por pasos no restaura vistas SQL ni otros objetos que no son tablas (' . implode( ', ', array_slice( (array) $cursor['views'], 0, 5 ) ) . '): quítalos del origen y crea un respaldo nuevo. No se cambió nada del sitio.' );
		self::restore_write_json( $job, 'plan.json', array( 'origin' => $source, 'destination' => $destination, 'args' => $args, 'skip' => $rollback ) );
		// Tablas: del respaldo → de este sitio (con el prefijo de la reubicación) → temporal → vieja. Sólo las del WordPress
		// que se restaura (las que llevan su prefijo): una tabla de otra aplicación que comparte la base de datos (otro
		// WordPress, una tienda) nunca se borra ni se recrea; se informa (revisión de E1, H3).
		$tables = is_array( $data['database_tables'] ?? null ) ? array_values( array_map( 'strval', $data['database_tables'] ) ) : array_values( (array) ( $cursor['tables'] ?? array() ) );
		$own_prefix = (string) ( $data['table_prefix'] ?? '' );
		$foreign = '' === $own_prefix ? array() : array_values( array_filter( $tables, function( $table ) use ( $own_prefix ) { return 0 !== strpos( $table, $own_prefix ); } ) );
		$tables = array_values( array_diff( $tables, $foreign ) );
		self::restore_write_json( $job, 'foreign.json', $foreign );
		$map = $tables;
		if ( ! $rollback && ! empty( $plan ) && $plan->needed() ) $map = array_values( self::restore_dest_names( $plan, $tables ) );
		$base = (string) $wpdb->base_prefix;
		$tag = (string) $job['spec']['tag'];
		$rows = array();
		foreach ( $tables as $n => $table ) {
			$suffix = 'dgbkr_' . $tag . '_' . str_pad( (string) $n, 3, '0', STR_PAD_LEFT );
			$temp = strlen( $base . $suffix ) <= 64 ? $base . $suffix : $suffix;
			$old = str_replace( 'dgbkr_', 'dgbko_', $temp );
			$rows[] = array( 'backup' => $table, 'dest' => (string) $map[ $n ], 'temp' => $temp, 'old' => $old );
		}
		self::restore_write_json( $job, 'tables.json', $rows );
		// Memoria (revisión de E1, H4): las sentencias más grandes pasan ahora, sin ejecutarse, por el guardián y la
		// reubicación que usará la importación. Si no caben, esta petición muere aquí (y la verificación falla tras sus
		// intentos con el aviso de PHP), antes de la copia previa: nunca a mitad de la importación.
		if ( ! $rollback && ! empty( $plan ) && $plan->needed() ) self::restore_dry_transform( $job, $data, $plan, $rows, (array) ( $cursor['largest'] ?? array() ) );
		// Tablas temporales o viejas que dejó otra restauración que murió (nunca las de la que esta deshace).
		$keep = (string) ( $job['spec']['rollback']['tag'] ?? '' );
		foreach ( self::restore_leftover_tables( $base, array( $tag, $keep ) ) as $leftover ) $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( $leftover ) );
		// Las claves de las credenciales del origen, cifradas con las de este servidor (nunca en claro), para tras el cambio.
		$zip = self::restore_zip( $job );
		$origin = array();
		try {
			$origin = Digitalisimo_Backups_Credentials::origin_keys( $zip );
			self::restore_write_json( $job, 'credentials.json', Digitalisimo_Backups_Credentials::journal( $origin ) );
		} finally {
			Digitalisimo_Backups_Credentials::forget( $origin );
			self::close_quietly( $zip );
		}
		self::restore_write_json( $job, 'tree.json', array( 'root' => self::restore_tree_root( $job ) ) );
		// Sin diario no se restaura: se comprueba ahora, antes de la copia previa, y no en el cambio.
		$journal = trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL;
		$probe = $journal . '.tmp';
		if ( is_link( $journal ) || is_link( $probe ) || 1 !== @file_put_contents( $probe, '{' ) || ! @unlink( $probe ) ) {
			@unlink( $probe );
			throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo escribir el diario de la restauración en el almacén. No se cambió nada del sitio.' );
		}
		// Áreas de trabajo junto a WordPress que dejó otra restauración que murió.
		foreach ( (array) glob( rtrim( ABSPATH, '/\\' ) . '/.digitalisimo-restore-*', GLOB_ONLYDIR ) as $leftover ) {
			if ( ! preg_match( '/\.digitalisimo-restore-([0-9a-f]{6})/', (string) $leftover, $m ) || in_array( $m[1], array( (string) $job['spec']['tag'], $keep ), true ) ) continue;
			self::restore_remove_tree( (string) $leftover );
		}
	}
	/** Pasa las sentencias anotadas por el guardián y la reubicación sin ejecutarlas, con la memoria de la importación. */
	private static function restore_dry_transform( array $job, array $data, $plan, array $rows, array $largest ) {
		if ( ! $largest ) return;
		$handle = @fopen( self::restore_staging( $job ) . '/source.sql', 'rb' );
		if ( ! $handle ) return;
		try {
			foreach ( $largest as $item ) {
				$length = (int) ( $item[1] ?? 0 );
				if ( $length <= 0 || 0 !== fseek( $handle, (int) $item[0] ) ) continue;
				$guard = Digitalisimo_Backups_Sql_Guard::for_backup( $data, self::restore_trusted( $job ) );
				$plan->attach( $guard, array_column( $rows, 'backup' ) );
				$reader = new Digitalisimo_Backups_Sql_Reader();
				$statements = $reader->feed( (string) fread( $handle, $length ) );
				$statements = array_merge( $statements, $reader->finish() );
				foreach ( $statements as $statement ) if ( $guard->check( $statement ) ) $guard->rewritten();
				unset( $guard, $reader, $statements );
			}
		} finally {
			fclose( $handle );
		}
	}
	/** Nombre en este sitio de cada tabla del respaldo (el prefijo de la reubicación). */
	private static function restore_dest_names( $plan, array $tables ) {
		$relocate = new Digitalisimo_Backups_Relocate();
		$prefixes = array();
		$origin = (string) ( $plan->origin()['prefix'] ?? '' );
		$destination = (string) ( $plan->destination()['prefix'] ?? '' );
		if ( '' !== $origin && '' !== $destination ) $prefixes[ $origin ] = $destination;
		return $relocate->set_prefixes( $prefixes, $destination )->table_map( $tables );
	}
	/** Tablas de restauraciones anteriores (`<prefijo>dgbkr_…`, `<prefijo>dgbko_…`) salvo las de las marcas dadas. */
	private static function restore_leftover_tables( $base, array $keep_tags ) {
		global $wpdb;
		$found = array();
		foreach ( array( 'dgbkr_', 'dgbko_' ) as $kind ) {
			foreach ( array( $base . $kind, $kind ) as $prefix ) {
				foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::restore_like( $prefix ) . '%' ) ) as $table ) {
					if ( ! preg_match( '/(?:^|_)dgbk[ro]_([0-9a-f]{6})_[0-9]{3,}$/D', (string) $table, $m ) || in_array( $m[1], array_filter( $keep_tags ), true ) ) continue;
					$found[ (string) $table ] = true;
				}
			}
		}
		return array_keys( $found );
	}
	/**
	 * Dónde se preparan los archivos: en el almacén privado si `rename()` llega de ahí a la raíz y a wp-content (mismo
	 * disco); si no, en una carpeta oculta con nombre aleatorio junto a WordPress.
	 */
	private static function restore_tree_root( array $job ) {
		$private = Digitalisimo_Backups_Job_Store::directory( $job['id'] ) . '/tree';
		if ( ! is_dir( $private ) && ! @mkdir( $private, 0700, true ) ) throw new Exception( 'No se pudo preparar el área de trabajo de los archivos.' );
		$same = true;
		// Con una carpeta (un archivo puede cruzar de disco en algunos sistemas y una carpeta no).
		foreach ( array( rtrim( ABSPATH, '/\\' ), rtrim( WP_CONTENT_DIR, '/\\' ) ) as $target ) {
			$probe = $private . '/.probe-' . bin2hex( random_bytes( 4 ) );
			$there = $target . '/.digitalisimo-restore-probe-' . bin2hex( random_bytes( 4 ) );
			if ( ! @mkdir( $probe, 0700 ) || false === @file_put_contents( $probe . '/x', 'x' ) ) throw new Exception( 'No se pudo preparar el área de trabajo de los archivos.' );
			if ( @rename( $probe, $there ) ) {
				if ( ! @rename( $there, $probe ) ) { self::restore_remove_tree( $there ); $same = false; }
			} else {
				$same = false;
			}
			self::restore_remove_tree( $probe );
			if ( ! $same ) break;
		}
		if ( $same ) return $private;
		@rmdir( $private );
		$hidden = rtrim( ABSPATH, '/\\' ) . '/.digitalisimo-restore-' . (string) $job['spec']['tag'] . bin2hex( random_bytes( 12 ) );
		if ( ! @mkdir( $hidden, 0700 ) ) throw new Exception( 'No se pudo preparar el área de trabajo de los archivos junto a WordPress.' );
		return $hidden;
	}

	/* ------------------------------------------------------------------------------------------- SQL por tramos */

	/**
	 * Recorre source.sql desde `offset` hasta el plazo, con un guardián nuevo que sigue la cuenta. $mode `scan` (en seco:
	 * sonda de la reubicación, tablas de un respaldo sin lista y sentencias permitidas) o `import` (a las tablas
	 * temporales, con la reubicación de E1). Cada tramo termina justo después de un «;»: el siguiente sigue desde ahí.
	 */
	private static function restore_sql( array $job, $mode, array $cursor, $deadline ) {
		global $wpdb;
		$staging = self::restore_staging( $job );
		$path = $staging . '/source.sql';
		$expected = (array) ( 'scan' === $mode ? ( $cursor['sql'] ?? array() ) : ( $job['checkpoint']['inspect']['sql'] ?? self::restore_inspect_sql( $job ) ) );
		if ( ! is_file( $path ) || is_link( $path ) || ( $expected && ! Digitalisimo_Backups_Backup_Job::same_fingerprint( Digitalisimo_Backups_Backup_Job::fingerprint( $path ), $expected['fp'] ?? array() ) ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La base de datos del respaldo ya no está íntegra en el área de trabajo; se verificará otra vez.', 'inspect' );
		$data = self::restore_read_json( $job, 'metadata.json' );
		$trusted = self::restore_trusted( $job );
		$guard = Digitalisimo_Backups_Sql_Guard::for_backup( $data, $trusted );
		$guard->start_at( (int) ( $cursor['sentences'] ?? 0 ) );
		$probe = null;
		$plan = null;
		$temp = array();
		$foreign = array();
		if ( 'scan' === $mode ) {
			// La sonda de E1: origen del SQL y valores que lo mencionan y no cabrían en la memoria para reescribirlos.
			$budget = method_exists( 'Digitalisimo_Backups_Relocate', 'value_budget' ) ? Digitalisimo_Backups_Relocate::value_budget() : 0;
			$cursor['budget'] = $budget;
			$probe = method_exists( 'Digitalisimo_Backups_Relocate', 'manifest_needles' ) ? new Digitalisimo_Backups_Relocate_Probe( (string) ( $data['table_prefix'] ?? '' ), array( 'budget' => $budget, 'needles' => Digitalisimo_Backups_Relocate::manifest_needles( $data ) ) ) : new Digitalisimo_Backups_Relocate_Probe( (string) ( $data['table_prefix'] ?? '' ) );
			$guard->on_insert( $probe );
			try { $renames = self::relocation_renames( $data ); } catch ( Exception $e ) { $renames = array(); } // El plan lo dice.
			// Sin estricto (como verify de E1): lo que no se puede renombrar se anota y el plan lo rechaza antes de la copia previa.
			if ( $renames ) $guard->rename_tables( $renames, false );
		} else {
			$plan = self::restore_plan( $job );
			$tables = self::restore_read_json( $job, 'tables.json' );
			if ( $plan && $plan->needed() ) $plan->attach( $guard, array_column( $tables, 'backup' ) );
			foreach ( $tables as $row ) $temp[ (string) $row['backup'] ] = (string) $row['temp'];
			$guard->rename_tables( $temp );
			$foreign = array_fill_keys( (array) self::restore_read_json( $job, 'foreign.json', false ), true );
			// La sesión de cada petición es nueva: lo que el SQL fijó antes de este tramo se repite.
			if ( ! empty( $cursor['names'] ) ) $wpdb->query( 'SET NAMES ' . ( 'utf8' === $cursor['names'] ? 'utf8' : 'utf8mb4' ) );
			if ( isset( $cursor['fk'] ) ) $wpdb->query( 'SET foreign_key_checks = ' . ( (int) $cursor['fk'] ? 1 : 0 ) );
		}
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer la base de datos del respaldo en el área de trabajo.' );
		$offset = (int) ( $cursor['offset'] ?? 0 );
		$units = 0;
		$done = false;
		try {
			if ( $offset > 0 && 0 !== fseek( $handle, $offset ) ) throw new Exception( 'No se pudo seguir la base de datos del respaldo donde se quedó.' );
			$reader = new Digitalisimo_Backups_Sql_Reader();
			// ends() cuenta desde el primer byte que lee este lector, que es el de esta posición (y no desde cada fragmento).
			$origin = $offset;
			$stop = false;
			$size = (int) ( $expected['size'] ?? 0 );
			while ( ! $stop ) {
				$chunk = fread( $handle, 262144 );
				if ( false === $chunk ) throw new Exception( 'No se pudo leer la base de datos del respaldo en el área de trabajo.' );
				$last = '' === $chunk;
				// Al final, la última sentencia puede no llevar «;»: termina con el archivo.
				$statements = $last ? $reader->finish() : $reader->feed( $chunk );
				$ends = $last ? array_fill( 0, count( $statements ), $size - $origin ) : $reader->ends();
				foreach ( $statements as $k => $statement ) {
					$start = $offset;
					$end = $origin + (int) ( $ends[ $k ] ?? 0 );
					if ( 'import' === $mode ) $cursor = self::restore_import_statement( $guard, $statement, $start, $cursor, $foreign );
					else $cursor = self::restore_scan_statement( $guard, $statement, $cursor, $start, $end );
					$offset = $end;
					$cursor['offset'] = $offset;
					$units++;
					if ( 0 === $units % 200 ) self::restore_progress( 'scan' === $mode ? 'inspect' : 'database', 'scan' === $mode ? 0.9 + 0.1 * $offset / max( 1, $size ) : $offset / max( 1, $size ), 'scan' === $mode ? 'Comprobando la base de datos del respaldo en seco…' : 'Importando la base de datos a tablas temporales (' . Digitalisimo_Backups_Archive_Inspector::megabytes( $offset ) . ' de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $size ) . ')…' );
					if ( ! $last && self::restore_exhausted( $deadline, $units ) ) { $stop = true; break; }
				}
				if ( $last ) { $done = true; break; }
			}
		} finally {
			fclose( $handle );
		}
		$cursor['sentences'] = $guard->count();
		$cursor['units'] = $units;
		if ( $probe ) {
			$found = $probe->result();
			$merged = (array) ( $cursor['probe'] ?? array() );
			$oversized = array_merge( (array) ( $merged['oversized'] ?? array() ), (array) ( $found['oversized'] ?? array() ) );
			unset( $merged['oversized'], $found['oversized'] );
			$cursor['probe'] = array_replace_recursive( $merged, $found );
			$cursor['probe']['oversized'] = array_slice( $oversized, 0, 50 );
			if ( method_exists( $guard, 'rename_problems' ) ) $cursor['rename_problems'] = array_values( array_unique( array_merge( (array) ( $cursor['rename_problems'] ?? array() ), $guard->rename_problems() ) ) );
		}
		if ( $plan ) {
			$cursor['report'] = self::restore_add_report( (array) ( $cursor['report'] ?? array() ), $plan );
			// Claves de usermeta que chocan (E1, after_import()): se reúnen tramo a tramo y se resuelven antes del cambio.
			foreach ( self::restore_plan_private( $plan, 'collisions' ) as $table => $rows ) foreach ( (array) $rows as $row ) $cursor['collisions'][ (string) $table ][ (int) $row[0] ] = $row;
		}
		if ( ! $done ) return $cursor;
		if ( empty( $cursor['statements'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del respaldo está vacía.' );
		$cursor['step'] = 'scan' === $mode ? 'plan' : 'done';
		return $cursor;
	}
	private static function restore_inspect_sql( array $job ) { return (array) ( $job['checkpoint']['inspect']['sql'] ?? array() ); }
	private static function restore_scan_statement( $guard, $statement, array $cursor, $start = 0, $end = 0 ) {
		if ( ! $guard->check( $statement ) ) return $cursor;
		$cursor['statements'] = (int) ( $cursor['statements'] ?? 0 ) + 1;
		// Las tres sentencias más grandes (posición y longitud): el plan las pasa por la reubicación antes de la copia previa.
		if ( $end > $start ) {
			$largest = (array) ( $cursor['largest'] ?? array() );
			$largest[] = array( (int) $start, (int) ( $end - $start ) );
			usort( $largest, function( $a, $b ) { return $b[1] <=> $a[1]; } );
			$cursor['largest'] = array_slice( $largest, 0, 3 );
		}
		// Una vista u otro objeto (lo escribió SHOW CREATE TABLE): con tablas temporales no se puede asegurar a qué apunta.
		if ( preg_match( '/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?(VIEW|TRIGGER|FUNCTION|PROCEDURE|EVENT)\s+`?([^`\s(]+)/i', $statement, $view ) ) $cursor['views'][] = $view[2];
		// Un respaldo sin lista de tablas (anterior al v3): las tablas salen de sus CREATE TABLE.
		if ( preg_match( '/^CREATE\s+TABLE\s+`((?:[^`]|``)+)`/i', $statement, $m ) ) {
			$table = str_replace( '``', '`', $m[1] );
			if ( ! in_array( $table, (array) ( $cursor['tables'] ?? array() ), true ) ) $cursor['tables'][] = $table;
		}
		return $cursor;
	}
	/**
	 * Ejecuta una sentencia en la tabla temporal. Cada tabla empieza con su DROP TABLE IF EXISTS: ahí se anota dónde
	 * empieza, para repetirla entera si el tramo muere a mitad (lo ejecutado tras el último punto de control no consta).
	 */
	private static function restore_import_statement( $guard, $statement, $start, array $cursor, array $foreign = array() ) {
		global $wpdb;
		if ( ! $guard->check( $statement ) ) return $cursor;
		// La tabla de otra aplicación que traiga el respaldo: comprobada, nunca ejecutada (ni DROP, ni CREATE, ni INSERT).
		if ( $foreign && preg_match( '/^(?:DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`((?:[^`]|``)+)`/i', $statement, $m ) && isset( $foreign[ str_replace( '``', '`', $m[1] ) ] ) ) {
			$cursor['foreign_skipped'] = (int) ( $cursor['foreign_skipped'] ?? 0 ) + 1;
			return $cursor;
		}
		if ( preg_match( '/^DROP\s+TABLE\s+IF\s+EXISTS\s+`((?:[^`]|``)+)`/i', $statement, $m ) ) {
			$cursor['table'] = str_replace( '``', '`', $m[1] );
			$cursor['table_start'] = (int) $start;
			$cursor['report_start'] = (array) ( $cursor['report'] ?? array() );
			$cursor['tables_done'] = (int) ( $cursor['tables_done'] ?? 0 ) + 1;
		}
		if ( preg_match( '/^SET\s+NAMES\s+(utf8mb4|utf8)\b/i', $statement, $m ) ) $cursor['names'] = strtolower( $m[1] );
		if ( preg_match( '/^SET\s+foreign_key_checks\s*=\s*([01])/i', $statement, $m ) ) $cursor['fk'] = (int) $m[1];
		// Lo que el guardián (o la reubicación) decide no ejecutar: una tabla omitida o una fila descartada.
		if ( method_exists( $guard, 'skipped' ) && $guard->skipped() ) {
			$cursor['guard_skipped'] = (int) ( $cursor['guard_skipped'] ?? 0 ) + 1;
			return $cursor;
		}
		$sql = $guard->rewritten();
		if ( preg_match( '/^CREATE\s+TABLE/i', $statement ) && isset( $cursor['table'] ) ) $cursor['created'][ (string) $cursor['table'] ] = true;
		if ( false === $wpdb->query( $sql ) ) {
			$error = (string) $wpdb->last_error;
			// Nombre de una clave foránea que ya usa la tabla viva (en MySQL es único por base de datos): la temporal lo
			// crea sin nombre y MySQL le pone uno propio.
			if ( preg_match( '/^CREATE\s+TABLE/i', $sql ) && preg_match( '/Duplicate foreign key|errno: ?121|\b1826\b/i', $error ) ) {
				$retry = preg_replace( '/\bCONSTRAINT\s+`(?:[^`]|``)+`\s+(?=FOREIGN\s+KEY)/i', '', $sql );
				if ( $retry !== $sql && false !== $wpdb->query( $retry ) ) { $cursor['statements'] = (int) ( $cursor['statements'] ?? 0 ) + 1; $cursor['fk_renamed'][] = (string) ( $cursor['table'] ?? '' ); return $cursor; }
			}
			throw new Exception( 'Falló una sentencia SQL: ' . $error );
		}
		$cursor['statements'] = (int) ( $cursor['statements'] ?? 0 ) + 1;
		return $cursor;
	}
	/** Una propiedad interna del plan de E1 (si existe en esta versión), o array(). */
	private static function restore_plan_private( $plan, $name, $value = null ) {
		if ( ! is_object( $plan ) || ! property_exists( $plan, $name ) ) return array();
		$property = new ReflectionProperty( $plan, $name );
		if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
		if ( null !== $value ) { $property->setValue( $plan, $value ); return $value; }
		return (array) $property->getValue( $plan );
	}
	/** Suma a lo reunido lo que la reubicación contó en este tramo (por tabla y en total). */
	private static function restore_add_report( array $report, $plan ) {
		$report += array( 'tables' => array(), 'stats' => array() );
		foreach ( (array) $plan->tables_report() as $table => $counts ) foreach ( (array) $counts as $what => $n ) $report['tables'][ $table ][ $what ] = (int) ( $report['tables'][ $table ][ $what ] ?? 0 ) + (int) $n;
		foreach ( (array) $plan->stats() as $what => $n ) $report['stats'][ $what ] = (int) ( $report['stats'][ $what ] ?? 0 ) + (int) $n;
		return $report;
	}
	/** El plan de reubicación que se calculó con el sitio intacto, o null si no hace falta (o es la vuelta a la copia previa). */
	private static function restore_plan( array $job ) {
		$saved = self::restore_read_json( $job, 'plan.json' );
		if ( ! empty( $saved['skip'] ) ) return null;
		$plan = Digitalisimo_Backups_Relocate::plan( (array) $saved['origin'], (array) $saved['destination'], (array) $saved['args'] );
		return $plan->problems() || ! $plan->needed() ? null : $plan;
	}

	/* ------------------------------------------------------------------------------------------- 2. Copia previa */

	/**
	 * La copia previa con las fases del respaldo (preflight, database, pack, verify, publish), una por paso, en el área de
	 * trabajo de esta restauración. Sus puntos de control viven en el de esta fase; si las fases del respaldo trabajan por
	 * tramos (T2), cada tramo es un paso. Queda bloqueada en el historial.
	 */
	private static function restore_safety( array $job ) {
		$cursor = (array) ( $job['checkpoint']['safety'] ?? array() );
		if ( empty( $cursor['spec'] ) && ! empty( $job['spec']['safety_spec']['file'] ) ) {
			$cursor = array( 'file' => (string) $job['spec']['safety_spec']['file'], 'spec' => (array) $job['spec']['safety_spec'] );
		} elseif ( empty( $cursor['spec'] ) ) {
			$file = self::backup_file_name( 'safety-before-restore-' );
			$cursor = array( 'file' => $file, 'spec' => Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'restore', 'file' => $file, 'inline' => true ) ) );
		}
		$cursor += array( 'sub' => 'preflight', 'checkpoint' => array(), 'attempts' => array(), 'retries' => 0 );
		$sub = (string) $cursor['sub'];
		$spec = (array) $cursor['spec'];
		$pseudo = array( 'id' => $job['id'], 'kind' => 'backup', 'spec' => $spec, 'phase' => $sub, 'checkpoint' => (array) $cursor['checkpoint'], 'attempts' => (array) $cursor['attempts'], 'status' => 'running', 'created_at' => (int) $job['created_at'] );
		try {
			$outcome = Digitalisimo_Backups_Backup_Job::run_phase( $sub, $pseudo );
		} catch ( Digitalisimo_Backups_Checkpoint_Invalid $e ) {
			// La fase del respaldo pide volver a una anterior: se anota aquí (el motor no conoce las fases de la copia).
			$phases = Digitalisimo_Backups_Jobs::PHASES;
			$back = in_array( $e->phase, $phases, true ) ? $e->phase : $sub;
			foreach ( array_slice( $phases, (int) array_search( $back, $phases, true ) ) as $later ) unset( $cursor['checkpoint'][ $later ] );
			Digitalisimo_Backups_Backup_Job::discard( $pseudo, $back );
			$cursor['sub'] = $back;
			$cursor['retries'] = (int) $cursor['retries'] + 1;
			if ( $cursor['retries'] > 3 ) throw new Exception( 'No se pudo crear la copia previa: ' . $e->getMessage() );
			return array( 'next' => 'safety', 'checkpoint' => $cursor, 'message' => 'Copia previa: ' . $e->getMessage() );
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo crear la copia previa, así que no se cambió nada del sitio: ' . $e->getMessage() );
		}
		$outcome = is_array( $outcome ) ? $outcome : array();
		$next = $outcome['next'] ?? null;
		$cursor['checkpoint'][ $sub ] = is_array( $outcome['checkpoint'] ?? null ) ? $outcome['checkpoint'] : array( 'done' => time() );
		$cursor['retries'] = 0;
		if ( $next === $sub ) {
			unset( $cursor['attempts'][ $sub ] );
			return array( 'next' => 'safety', 'checkpoint' => $cursor, 'message' => 'Creando la copia previa: ' . (string) ( $outcome['message'] ?? '' ) );
		}
		if ( null !== $next && in_array( $next, Digitalisimo_Backups_Jobs::PHASES, true ) ) {
			$cursor['sub'] = $next;
			return array( 'next' => 'safety', 'checkpoint' => $cursor, 'message' => 'Creando la copia previa: ' . (string) ( $outcome['message'] ?? Digitalisimo_Backups_Jobs::phase_label( $next ) ) );
		}
		$safety = rtrim( self::local_directory(), '/\\' ) . '/' . basename( (string) $spec['file'] );
		if ( ! is_file( $safety ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La copia previa no quedó en el almacén; se repetirá.', 'safety' );
		self::remember( $safety, 'local', is_multisite() ? 'network' : 'installation', array( 'id' => 'pre-restore', 'type' => 'full' ), array(), 0, true, array( 'job' => (string) $job['id'] ) );
		return array( 'next' => 'database', 'checkpoint' => array( 'done' => time(), 'file' => basename( $safety ) ), 'message' => 'Copia previa creada y bloqueada (' . basename( $safety ) . '); importando la base de datos a tablas temporales…' );
	}

	/* ------------------------------------------------------------------------------------------- 3. Base de datos */

	/** Importa el SQL a las tablas temporales, por tramos; el sitio sigue sirviéndose de las suyas. */
	private static function restore_database( array $job ) {
		$cursor = (array) ( $job['checkpoint']['database'] ?? array() );
		$started = microtime( true );
		// El tramo anterior murió o falló a mitad: lo ejecutado después de su punto de control no consta; la tabla en
		// curso se repite desde su DROP TABLE IF EXISTS (y lo reunido de la reubicación, desde su principio).
		if ( (int) ( $job['attempts']['database'] ?? 0 ) > 0 && isset( $cursor['table_start'] ) ) {
			$cursor['offset'] = (int) $cursor['table_start'];
			$cursor['report'] = (array) ( $cursor['report_start'] ?? array() );
			$cursor['restarted'] = (int) ( $cursor['restarted'] ?? 0 ) + 1;
		}
		$deadline = self::restore_deadline( $job, 'database', $cursor );
		$cursor = self::restore_sql( $job, 'import', $cursor, $deadline );
		if ( 'done' !== ( $cursor['step'] ?? '' ) ) return self::restore_slice( 'database', $cursor, 'Importando la base de datos a tablas temporales (tabla ' . (int) ( $cursor['tables_done'] ?? 0 ) . ')…', $started, (int) $cursor['units'], $job );
		// Todas las tablas temporales se crearon: el cambio las necesita completas.
		foreach ( self::restore_read_json( $job, 'tables.json' ) as $row ) if ( empty( $cursor['created'][ (string) $row['backup'] ] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del respaldo no crea la tabla ' . $row['backup'] . ' que lista su manifiesto.' );
		// Red y direcciones en las tablas temporales, antes del cambio (revisión de E1, H1): las tablas pasan de una vez ya
		// coherentes (blogs, site, sitemeta y home/siteurl de cada sitio), así que ningún punto intermedio deja la red sin cargar.
		self::restore_preswap_addresses( $job );
		// De dos claves de usermeta iguales, la del sitio restaurado (E1, after_import()), también en las temporales.
		foreach ( self::restore_preswap_collisions( $job, (array) ( $cursor['collisions'] ?? array() ) ) as $table => $removed ) $cursor['report']['tables'][ $table ]['collisions'] = (int) ( $cursor['report']['tables'][ $table ]['collisions'] ?? 0 ) + $removed;
		return array( 'next' => 'files', 'checkpoint' => array( 'done' => time(), 'statements' => (int) $cursor['statements'], 'report' => (array) ( $cursor['report'] ?? array() ), 'names' => $cursor['names'] ?? '', 'restarted' => (int) ( $cursor['restarted'] ?? 0 ) ), 'message' => 'Base de datos lista en tablas temporales; preparando los archivos…' );
	}
	/**
	 * Lo que finish() de la reubicación fija en la red y las direcciones, aplicado a las tablas temporales (su nombre en
	 * este sitio → su temporal). Idempotente: finish() lo vuelve a comprobar después sobre las tablas ya cambiadas.
	 */
	private static function restore_preswap_addresses( array $job ) {
		global $wpdb;
		$plan = self::restore_plan( $job );
		if ( ! $plan || 'current' !== $plan->mode() ) return;
		$temp = array();
		foreach ( self::restore_read_json( $job, 'tables.json' ) as $row ) $temp[ (string) $row['dest'] ] = (string) $row['temp'];
		$o = $plan->origin();
		$d = $plan->destination();
		$prefix = (string) $d['prefix'];
		$q = array( 'Digitalisimo_Backups_Relocate', 'quote_name' );
		$run = function( $sql ) use ( $wpdb ) { if ( false === $wpdb->query( $sql ) ) throw new Exception( 'No se pudo fijar la red o las direcciones en las tablas temporales: ' . $wpdb->last_error ); };
		$targets = array();
		if ( ! empty( $o['multisite'] ) ) {
			$from = (array) $o['network'];
			$to = (array) $d['network'];
			$scheme = (string) ( wp_parse_url( (string) $d['home'], PHP_URL_SCHEME ) ?: 'https' );
			$ids = array();
			foreach ( (array) ( $o['networks'] ?? array() ) as $id => $row ) if ( ( $row['domain'] ?? '' ) === ( $from['domain'] ?? null ) && ( $row['path'] ?? '' ) === ( $from['path'] ?? null ) ) $ids[] = (int) $id;
			if ( ! $ids ) $ids = array( 1 );
			foreach ( $ids as $id ) {
				if ( isset( $temp[ $prefix . 'site' ] ) && $from !== $to ) $run( $wpdb->prepare( 'UPDATE ' . call_user_func( $q, $temp[ $prefix . 'site' ] ) . ' SET domain = %s, path = %s WHERE id = %d', $to['domain'], $to['path'], $id ) );
				if ( isset( $temp[ $prefix . 'sitemeta' ] ) ) $run( $wpdb->prepare( 'UPDATE ' . call_user_func( $q, $temp[ $prefix . 'sitemeta' ] ) . " SET meta_value = %s WHERE meta_key = 'siteurl' AND site_id = %d", $scheme . '://' . $to['domain'] . $to['path'], $id ) );
			}
			foreach ( (array) $plan->sites() as $id => $site ) {
				if ( isset( $temp[ $prefix . 'blogs' ] ) && $site['from'] !== $site['to'] ) $run( $wpdb->prepare( 'UPDATE ' . call_user_func( $q, $temp[ $prefix . 'blogs' ] ) . ' SET domain = %s, path = %s WHERE blog_id = %d', $site['to']['domain'], $site['to']['path'], $id ) );
				if ( isset( $site['home'] ) ) $targets[ 1 === (int) $id ? $prefix . 'options' : $prefix . $id . '_options' ] = array( 'home' => $site['home'], 'siteurl' => $site['siteurl'] );
			}
		} elseif ( '' !== (string) $d['home'] && ( $plan->rewrites_values() || (string) $o['prefix'] !== $prefix ) ) {
			$targets[ $prefix . 'options' ] = array( 'home' => $d['home'], 'siteurl' => '' !== (string) $d['siteurl'] ? $d['siteurl'] : $d['home'] );
		}
		foreach ( $targets as $table => $values ) {
			if ( ! isset( $temp[ $table ] ) ) continue;
			foreach ( $values as $name => $value ) if ( '' !== (string) $value ) $run( $wpdb->prepare( 'UPDATE ' . call_user_func( $q, $temp[ $table ] ) . ' SET option_value = %s WHERE option_name = %s', $value, $name ) );
		}
	}
	/** Devuelve, por tabla de este sitio, cuántas claves repetidas se retiraron. */
	private static function restore_preswap_collisions( array $job, array $collisions ) {
		if ( ! $collisions ) return array();
		$plan = self::restore_plan( $job );
		if ( ! $plan || ! method_exists( $plan, 'after_import' ) || ! property_exists( $plan, 'collisions' ) ) return array();
		$temp = array();
		foreach ( self::restore_read_json( $job, 'tables.json' ) as $row ) $temp[ (string) $row['dest'] ] = (string) $row['temp'];
		$removed = array();
		foreach ( $collisions as $table => $rows ) {
			if ( ! isset( $temp[ $table ] ) ) continue;
			self::restore_plan_private( $plan, 'collisions', array( $temp[ $table ] => array_values( (array) $rows ) ) );
			$removed[ $table ] = (int) $plan->after_import();
		}
		return $removed;
	}
	private static function restore_table_exists( $table ) {
		global $wpdb;
		return in_array( (string) $table, (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::restore_like( (string) $table ) ) ), true );
	}
	private static function restore_like( $text ) {
		global $wpdb;
		return is_object( $wpdb ) && method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $text ) : addcslashes( (string) $text, '_%\\' );
	}

	/* ------------------------------------------------------------------------------------------- 4. Archivos */

	/**
	 * Raíces que el respaldo gestiona (H3) y unidades de código. Lo que el respaldo excluye a propósito (`.git`,
	 * `node_modules`, carpetas `cache`, enlaces simbólicos, el almacén y la base de datos SQLite activa) nunca se retira.
	 */
	private static function restore_layout() {
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$relative = function( $path ) use ( $root ) {
			$real = realpath( (string) $path );
			$path = false === $real ? '' : trailingslashit( wp_normalize_path( $real ) );
			return '' !== $path && 0 === strpos( $path, $root ) && $path !== $root ? rtrim( substr( $path, strlen( $root ) ), '/' ) : '';
		};
		$uploads = function_exists( 'wp_upload_dir' ) ? (array) wp_upload_dir( null, false ) : array();
		$layout = array(
			'root' => $root,
			'plugins' => $relative( WP_PLUGIN_DIR ),
			'themes' => $relative( function_exists( 'get_theme_root' ) ? get_theme_root() : WP_CONTENT_DIR . '/themes' ),
			'mu' => $relative( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' ),
			'uploads' => $relative( (string) ( $uploads['basedir'] ?? WP_CONTENT_DIR . '/uploads' ) ),
			'self' => '',
		);
		if ( defined( 'DIGITALISIMO_BACKUPS_FILE' ) && '' !== $layout['plugins'] ) $layout['self'] = $layout['plugins'] . '/' . basename( dirname( DIGITALISIMO_BACKUPS_FILE ) );
		elseif ( '' !== $layout['plugins'] ) $layout['self'] = $layout['plugins'] . '/' . basename( dirname( __DIR__, 2 ) );
		return $layout;
	}
	/** Unidad de una ruta: carpeta de código que cambia entera (wp-admin, wp-includes, cada plugin o tema, mu-plugins) o '' (archivo suelto). */
	private static function restore_unit( $relative, array $layout ) {
		foreach ( array( 'wp-admin', 'wp-includes' ) as $core ) if ( 0 === strpos( $relative, $core . '/' ) ) return $core;
		if ( '' !== $layout['mu'] && 0 === strpos( $relative, $layout['mu'] . '/' ) ) return $layout['mu'];
		foreach ( array( 'plugins', 'themes' ) as $kind ) {
			$base = $layout[ $kind ];
			if ( '' === $base || 0 !== strpos( $relative, $base . '/' ) ) continue;
			$rest = substr( $relative, strlen( $base ) + 1 );
			$slash = strpos( $rest, '/' );
			return false === $slash ? '' : $base . '/' . substr( $rest, 0, $slash );
		}
		return '';
	}
	/** Lo que el respaldo excluye a propósito (nunca es «ajeno»): `.git`, `node_modules`, carpetas `cache`. */
	private static function restore_excluded( $relative ) {
		// wp-config.php nunca se restaura ni se retira (es de este servidor).
		if ( in_array( strtolower( $relative ), array( 'wp-config.php', '.maintenance' ), true ) ) return true;
		$path = '/' . $relative . '/';
		return false !== strpos( $path, '/.git/' ) || false !== strpos( $path, '/node_modules/' ) || false !== strpos( $path, '/cache/' ) || false !== strpos( $path, '/.digitalisimo-' );
	}
	/** La carpeta está dentro de una raíz gestionada o lleva a una. */
	private static function restore_walk_into( $relative, array $layout ) {
		foreach ( array( 'wp-admin', 'wp-includes', $layout['plugins'], $layout['themes'], $layout['mu'], $layout['uploads'] ) as $base ) {
			if ( '' === $base ) continue;
			if ( $relative === $base || 0 === strpos( $relative . '/', $base . '/' ) || 0 === strpos( $base . '/', $relative . '/' ) ) return true;
		}
		return false;
	}
	/** Raíces donde se retira lo que el respaldo no tiene: núcleo (wp-admin, wp-includes y los .php de la raíz), plugins, temas, mu-plugins y medios. */
	private static function restore_managed( $relative, array $layout ) {
		if ( false === strpos( $relative, '/' ) ) return '.php' === strtolower( substr( $relative, -4 ) );
		foreach ( array( 'wp-admin', 'wp-includes', $layout['plugins'], $layout['themes'], $layout['mu'], $layout['uploads'] ) as $base ) if ( '' !== $base && 0 === strpos( $relative, $base . '/' ) ) return true;
		return false;
	}

	/**
	 * Prepara los archivos sin tocar el sitio: (1) recorre las raíces gestionadas y anota lo ajeno, (2) compara cada
	 * archivo del respaldo con el del sitio (tamaño y SHA-256 del manifiesto) y (3) escribe en el área de trabajo lo que
	 * cambia: una unidad de código entera si algo suyo cambia; un medio o un archivo suelto, solo. Por tramos.
	 */
	private static function restore_files( array $job ) {
		$cursor = (array) ( $job['checkpoint']['files'] ?? array() );
		$cursor += array( 'step' => 'walk' );
		$started = microtime( true );
		$deadline = self::restore_deadline( $job, 'files', $cursor );
		$layout = self::restore_layout();
		// Un recorrido que murió a mitad pudo anotar carpetas dos veces: se repite desde el principio (sólo mira, es barato).
		if ( 'walk' === $cursor['step'] && (int) ( $job['attempts']['files'] ?? 0 ) > 0 ) $cursor = array( 'step' => 'walk' );
		if ( 'walk' === $cursor['step'] ) {
			$cursor = self::restore_walk( $job, $cursor, $layout, $deadline );
			return self::restore_slice( 'files', $cursor, 'Buscando lo que el respaldo no tiene (' . (int) ( $cursor['seen'] ?? 0 ) . ' archivos revisados)…', $started, (int) ( $cursor['units'] ?? 0 ), $job );
		}
		if ( 'compare' === $cursor['step'] || 'extract' === $cursor['step'] ) {
			$cursor = self::restore_extract( $job, $cursor, $layout, $deadline );
			if ( 'done' !== $cursor['step'] ) return self::restore_slice( 'files', $cursor, ( 'compare' === $cursor['step'] ? 'Comparando archivos (' : 'Preparando los archivos que cambian (' ) . (int) $cursor['i'] . ' de ' . (int) ( $cursor['count'] ?? 0 ) . ')…', $started, (int) ( $cursor['units'] ?? 0 ), $job );
			return array( 'next' => 'switch', 'checkpoint' => array( 'done' => time(), 'staged' => (int) ( $cursor['staged'] ?? 0 ), 'same' => (int) ( $cursor['same'] ?? 0 ), 'units' => (int) ( $cursor['changed_units'] ?? 0 ) ), 'message' => 'Archivos preparados; cambiando el sitio…' );
		}
		throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La preparación de los archivos perdió su punto de control; se repetirá.', 'files' );
	}
	/**
	 * Recorre las raíces gestionadas del sitio, carpeta por carpeta (pila en walk.json): lo que el respaldo no tiene y no
	 * excluye a propósito queda en extraneous.txt (archivo suelto o de medios) o marca su unidad como cambiada. Una unidad
	 * con algo excluido (un `.git` de desarrollo, un enlace simbólico) no se cambia entera: sus archivos van uno a uno y
	 * lo excluido se queda.
	 */
	private static function restore_walk( array $job, array $cursor, array $layout, $deadline ) {
		$staging = self::restore_staging( $job );
		$root = $layout['root'];
		$units = (array) ( self::restore_read_json( $job, 'units.json', false ) ?? array() );
		$stack = isset( $cursor['stack'] ) ? (array) $cursor['stack'] : null;
		if ( null === $stack ) {
			@unlink( $staging . '/extraneous.txt' );
			@unlink( $staging . '/removed.txt' );
			$stack = array( '' );
			$units = array();
		}
		$zip = self::restore_zip( $job );
		// Sólo un respaldo completo v3 (con su inventario comprobado) dice qué sobra; uno anterior puede no traer el árbol entero.
		$complete = ! empty( $job['checkpoint']['inspect']['v3'] );
		$extraneous = @fopen( $staging . '/extraneous.txt', 'ab' );
		$removed = @fopen( $staging . '/removed.txt', 'ab' );
		if ( ! $extraneous || ! $removed ) throw new Exception( 'No se pudo anotar lo que el respaldo no tiene en el área de trabajo.' );
		$count = 0;
		try {
			while ( $stack ) {
				if ( $count > 0 && self::restore_exhausted( $deadline, $count ) ) break;
				$dir = (string) array_pop( $stack );
				$path = rtrim( $root . $dir, '/' );
				if ( is_link( $path ) || ! is_dir( $path ) ) continue;
				$names = @scandir( $path );
				if ( ! is_array( $names ) ) continue;
				foreach ( $names as $name ) {
					if ( '.' === $name || '..' === $name ) continue;
					$relative = '' === $dir ? $name : $dir . '/' . $name;
					$full = $root . $relative;
					$unit = self::restore_unit( $relative, $layout );
					$count++;
					if ( self::restore_excluded( $relative ) || is_link( $full ) || ( ! is_dir( $full ) && ! is_file( $full ) ) ) {
						if ( '' !== $unit && 0 !== strpos( $relative, '.digitalisimo-' ) ) $units[ $unit ]['mixed'] = true;
						continue;
					}
					if ( is_dir( $full ) ) {
						// Sólo se entra en las raíces gestionadas y en las carpetas que llevan a ellas (wp-content).
						if ( self::restore_walk_into( $relative, $layout ) ) $stack[] = $relative;
						continue;
					}
					if ( ! self::restore_managed( $relative, $layout ) || ! $complete ) continue;
					if ( false !== $zip->locateName( 'wordpress/' . $relative ) ) continue;
					// Ajeno al respaldo, dentro de lo que gestiona: se retira (queda en la copia previa) y se informa.
					fwrite( $removed, self::restore_line( $relative ) );
					if ( '' !== $unit ) {
						$units[ $unit ]['changed'] = true;
						$units[ $unit ]['pending'][] = $relative;
					} else {
						fwrite( $extraneous, self::restore_line( $relative ) );
					}
				}
			}
		} finally {
			fclose( $extraneous );
			fclose( $removed );
			self::close_quietly( $zip );
		}
		$cursor['seen'] = (int) ( $cursor['seen'] ?? 0 ) + $count;
		$cursor['units'] = $count;
		if ( $stack ) {
			$cursor['stack'] = array_values( $stack );
			self::restore_write_json( $job, 'units.json', $units );
			return $cursor;
		}
		// Una unidad mixta no se cambia entera: lo ajeno suyo se retira uno a uno.
		$handle = @fopen( $staging . '/extraneous.txt', 'ab' );
		foreach ( $units as $unit => $state ) {
			if ( ! empty( $state['mixed'] ) ) foreach ( (array) ( $state['pending'] ?? array() ) as $relative ) fwrite( $handle, self::restore_line( $relative ) );
			unset( $units[ $unit ]['pending'] );
		}
		if ( $handle ) fclose( $handle );
		self::restore_write_json( $job, 'units.json', $units );
		$zip = self::restore_zip( $job );
		$count = (int) $zip->numFiles;
		self::close_quietly( $zip );
		$bitmap = @fopen( $staging . '/changed.bin', 'wb' );
		if ( ! $bitmap || ( $count > 0 && $count !== fwrite( $bitmap, str_repeat( "\0", $count ) ) ) ) throw new Exception( 'No se pudo preparar la lista de archivos que cambian en el área de trabajo.' );
		fclose( $bitmap );
		return array( 'step' => 'compare', 'i' => 0, 'count' => $count, 'seen' => $cursor['seen'], 'staged' => 0, 'same' => 0 );
	}
	private static function restore_line( $relative ) { return str_replace( array( '%', "\n", "\r" ), array( '%25', '%0A', '%0D' ), (string) $relative ) . "\n"; }
	private static function restore_unline( $line ) { return str_replace( array( '%0A', '%0D', '%25' ), array( "\n", "\r", '%' ), rtrim( (string) $line, "\n" ) ); }

	/**
	 * (2) compare: marca en changed.bin cada archivo del respaldo distinto del sitio y su unidad; (3) extract: escribe en
	 * tree/new lo que cambia (la unidad entera, o el archivo suelto), con temporal, CRC y sin seguir enlaces.
	 */
	private static function restore_extract( array $job, array $cursor, array $layout, $deadline ) {
		$staging = self::restore_staging( $job );
		$tree = (string) self::restore_read_json( $job, 'tree.json' )['root'];
		$units = (array) self::restore_read_json( $job, 'units.json' );
		$v3 = ! empty( $job['checkpoint']['inspect']['v3'] ) && is_file( $staging . '/manifest.idx' );
		$zip = self::restore_zip( $job );
		$count = (int) $cursor['count'];
		$index = $v3 ? Digitalisimo_Backups_Manifest_Index::open( $staging . '/manifest.idx', $count ) : null;
		$bitmap = @fopen( $staging . '/changed.bin', 'r+b' );
		if ( ! $bitmap ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Falta la lista de archivos que cambian; se preparará otra vez.', 'files' );
		$skip = self::restore_skip();
		$root = $layout['root'];
		$work = 0;
		$config = array( 'plan' => self::restore_plan( $job ), 'zip' => $zip, 'root' => $root, 'notes' => array() );
		$units_changed = false;
		try {
			for ( $i = (int) $cursor['i']; $i < $count; $i++ ) {
				if ( $work > 0 && self::restore_exhausted( $deadline, $work ) ) break;
				if ( 0 === $i % 100 ) self::restore_progress( 'files', ( 'compare' === $cursor['step'] ? 0.1 : 0.55 ) + 0.45 * $i / max( 1, $count ), ( 'compare' === $cursor['step'] ? 'Comparando archivo ' : 'Preparando archivo ' ) . ( $i + 1 ) . ' de ' . $count );
				$cursor['i'] = $i;
				$stat = $zip->statIndex( $i );
				$relative = $stat ? self::restore_target( (string) $stat['name'], $skip ) : null;
				if ( null === $relative ) continue;
				$work++;
				$unit = self::restore_unit( $relative, $layout );
				$whole = '' !== $unit && empty( $units[ $unit ]['mixed'] );
				if ( '' !== $unit && empty( $units[ $unit ]['in_backup'] ) ) { $units[ $unit ]['in_backup'] = true; $units_changed = true; }
				if ( 'compare' === $cursor['step'] ) {
					if ( $whole && ! empty( $units[ $unit ]['changed'] ) ) continue;
					$live = $root . $relative;
					$fixed = self::restore_is_config( $relative ) ? self::restore_config_content( $zip, $i, $relative, $config ) : null;
					$same = is_file( $live ) && ! is_link( $live ) && (int) @filesize( $live ) === ( null === $fixed ? (int) $stat['size'] : strlen( $fixed ) );
					if ( $same && null !== $fixed ) {
						$same = hash_equals( hash( 'sha256', $fixed ), (string) @hash_file( 'sha256', $live ) );
					} elseif ( $same && $index ) {
						$expected = $index->get( $i );
						$same = is_array( $expected ) && hash_equals( $expected['sha256'], (string) @hash_file( 'sha256', $live ) );
					} elseif ( $same ) {
						$same = sprintf( '%08x', $stat['crc'] ) === (string) @hash_file( 'crc32b', $live );
					}
					if ( $same ) { $cursor['same'] = (int) ( $cursor['same'] ?? 0 ) + 1; continue; }
					if ( $whole ) { $units[ $unit ]['changed'] = true; $units_changed = true; continue; }
					fseek( $bitmap, $i );
					fwrite( $bitmap, "\1" );
					continue;
				}
				// extract
				if ( $whole ) {
					if ( empty( $units[ $unit ]['changed'] ) ) continue;
				} else {
					fseek( $bitmap, $i );
					if ( "\1" !== fread( $bitmap, 1 ) ) continue;
				}
				self::restore_stage_entry( $zip, $i, $stat, $tree . '/new', $relative );
				if ( self::restore_is_config( $relative ) ) {
					$fixed = self::restore_config_content( $zip, $i, $relative, $config );
					if ( null !== $fixed && strlen( $fixed ) !== @file_put_contents( $tree . '/new/' . $relative, $fixed ) ) throw new Exception( 'No se pudo preparar ' . $relative . ' para este servidor.' );
				}
				$cursor['staged'] = (int) ( $cursor['staged'] ?? 0 ) + 1;
			}
		} finally {
			fclose( $bitmap );
			if ( $index ) $index->close();
			self::close_quietly( $zip );
		}
		if ( $units_changed ) self::restore_write_json( $job, 'units.json', $units );
		if ( $config['notes'] ) {
			$notes = (array) ( self::restore_read_json( $job, 'config-notes.json', false ) ?? array() );
			foreach ( $config['notes'] as $relative => $note ) $notes[ $relative ] = $note;
			self::restore_write_json( $job, 'config-notes.json', $notes );
		}
		$cursor['units'] = $work;
		if ( $i < $count ) { $cursor['i'] = $i; return $cursor; }
		if ( 'compare' === $cursor['step'] ) {
			$cursor['changed_units'] = count( array_filter( $units, function( $state ) { return ! empty( $state['changed'] ) && empty( $state['mixed'] ); } ) );
			return array( 'step' => 'extract', 'i' => 0 ) + $cursor;
		}
		$cursor['step'] = 'done';
		return $cursor;
	}
	/** `.htaccess`, `.user.ini` y `php.ini`: pueden llevar rutas absolutas o el dominio del origen (revisión de E1, P1). */
	private static function restore_is_config( $relative ) {
		if ( defined( 'Digitalisimo_Backups_Relocate::SERVER_FILES' ) && in_array( (string) $relative, Digitalisimo_Backups_Relocate::SERVER_FILES, true ) ) return true;
		return in_array( strtolower( basename( (string) $relative ) ), array( '.htaccess', '.user.ini', 'php.ini' ), true );
	}
	/**
	 * Contenido con el que se restaura un archivo de configuración del servidor, o null si se restaura tal cual. Las rutas
	 * del origen pasan a las de este servidor; en un `.htaccess`, el dominio del origen en las reglas (un host canónico
	 * fijo) pasa al de este sitio si el sitio conserva sus direcciones; y un `auto_prepend_file`/`auto_append_file` que
	 * apunta a un archivo que aquí no existirá (ni en el sitio ni en el respaldo) se desactiva comentándolo, porque PHP
	 * respondería 500 a todo, también al escritorio desde el que se revierte. Cada cambio queda en $config['notes'].
	 */
	private static function restore_config_content( $zip, $index, $relative, array &$config ) {
		$stat = $zip->statIndex( $index );
		if ( ! $stat || (int) $stat['size'] > 1048576 ) return null;
		$original = $zip->getFromIndex( $index );
		if ( ! is_string( $original ) ) return null;
		$content = $original;
		$notes = array();
		$plan = $config['plan'];
		$root = rtrim( (string) $config['root'], '/' );
		// Con reubicación, la regla de E1 (Digitalisimo_Backups_Relocate::server_file(): rutas, dominio, auto_prepend_file
		// que aquí no existe y el de este servidor que sí) para los archivos de la raíz que conoce.
		if ( $plan && method_exists( $plan, 'server_file' ) && defined( 'Digitalisimo_Backups_Relocate::SERVER_FILES' ) && in_array( $relative, Digitalisimo_Backups_Relocate::SERVER_FILES, true ) ) {
			$live = is_file( $root . '/' . $relative ) && ! is_link( $root . '/' . $relative ) ? (string) @file_get_contents( $root . '/' . $relative, false, null, 0, 1048576 ) : null;
			$before = count( self::restore_plan_private( $plan, 'server_notes' ) );
			$content = call_user_func( self::server_file_transform( $plan, $config['zip'] ), $relative, $original, $live );
			foreach ( array_slice( self::restore_plan_private( $plan, 'server_notes' ), $before ) as $note ) $notes[] = (string) $note;
			if ( ! is_string( $content ) ) $content = $original;
			if ( $content !== $original && ! $notes ) $notes[] = 'se ajustaron las rutas o el dominio del origen para este servidor';
			if ( $notes ) $config['notes'][ $relative ] = $notes;
			return $content === $original ? null : $content;
		}
		if ( $plan ) {
			$o = $plan->origin();
			$d = $plan->destination();
			$paths = array();
			foreach ( array( 'abspath', 'abspath_real' ) as $key ) if ( '' !== (string) ( $o[ $key ] ?? '' ) && (string) $o[ $key ] !== (string) $d['abspath'] ) $paths[ rtrim( (string) $o[ $key ], '/' ) ] = rtrim( (string) $d['abspath'], '/' );
			uksort( $paths, function( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
			foreach ( $paths as $from => $to ) {
				$count = 0;
				$content = preg_replace( '#' . preg_quote( $from, '#' ) . '(?=/|[\s"\'\]]|$)#m', str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $to ), $content, -1, $count );
				if ( $count ) $notes[] = 'ruta del servidor ' . $from . ' → ' . $to;
			}
			$from_host = (string) wp_parse_url( (string) $o['home'], PHP_URL_HOST );
			$to_host = (string) wp_parse_url( (string) $d['home'], PHP_URL_HOST );
			if ( '.htaccess' === strtolower( basename( $relative ) ) && 'current' === $plan->mode() && '' !== $from_host && '' !== $to_host && strtolower( $from_host ) !== strtolower( $to_host ) ) {
				$count = 0;
				$content = preg_replace_callback( '/^\s*(?:RewriteCond|RewriteRule|Redirect\w*)\b.*$/mi', function( $line ) use ( $from_host, $to_host, &$count ) {
					$out = str_ireplace( array( preg_quote( $from_host, '/' ), $from_host ), array( preg_quote( $to_host, '/' ), $to_host ), $line[0], $n );
					$count += $n;
					return $out;
				}, $content );
				if ( $count ) $notes[] = 'dominio ' . $from_host . ' → ' . $to_host . ' en las reglas de reescritura';
			}
		}
		// auto_prepend_file / auto_append_file que aquí no existirán: se desactivan.
		$htaccess = '.htaccess' === strtolower( basename( $relative ) );
		$pattern = $htaccess ? '/^(\s*php_(?:admin_)?value\s+auto_(?:prepend|append)_file\s+)(["\']?)([^"\'\r\n]+)\2(.*)$/mi' : '/^(\s*auto_(?:prepend|append)_file\s*=\s*)(["\']?)([^"\'\r\n;]*)\2(.*)$/mi';
		$content = preg_replace_callback( $pattern, function( $m ) use ( $root, $config, $htaccess, &$notes ) {
			$target = trim( $m[3] );
			if ( '' === $target || 'none' === strtolower( $target ) || '/' !== $target[0] ) return $m[0];
			$inside = 0 === strpos( wp_normalize_path( $target ), $root . '/' );
			$present = file_exists( $target ) || ( $inside && false !== $config['zip']->locateName( 'wordpress/' . substr( wp_normalize_path( $target ), strlen( $root ) + 1 ) ) );
			if ( $present ) return $m[0];
			$notes[] = 'se desactivó ' . trim( $m[1] ) . ' ' . $target . ' (no existe en este servidor)';
			return ( $htaccess ? '# ' : '; ' ) . 'Desactivado por DIGITALÍSIMO Backups al restaurar (no existe en este servidor): ' . trim( $m[0] );
		}, $content );
		if ( $notes ) $config['notes'][ $relative ] = $notes;
		return $content === $original ? null : $content;
	}
	/** Escribe una entrada en el área de trabajo: temporal, sin pasar de lo declarado, CRC comprobado y `rename()`. */
	private static function restore_stage_entry( $zip, $index, array $stat, $base, $relative ) {
		$target = $base . '/' . $relative;
		if ( ! wp_mkdir_p( dirname( $target ) ) ) throw new Exception( 'No se pudo preparar una carpeta en el área de trabajo de los archivos.' );
		$input = $zip->getStream( $stat['name'] );
		$temp = $target . '.part-' . bin2hex( random_bytes( 3 ) );
		$output = @fopen( $temp, 'xb' );
		if ( ! $input || ! $output ) { if ( $input ) fclose( $input ); if ( $output ) fclose( $output ); @unlink( $temp ); throw new Exception( 'No se pudo preparar el archivo ' . $relative . ' para restaurarlo.' ); }
		$declared = (int) $stat['size'];
		$written = stream_copy_to_stream( $input, $output, $declared + 1 );
		$closed = fclose( $output );
		fclose( $input );
		$crc = @hash_file( 'crc32b', $temp );
		if ( false === $written || ! $closed || $written !== $declared || false === $crc || $crc !== sprintf( '%08x', $stat['crc'] ) ) { @unlink( $temp ); throw new Exception( 'El archivo restaurado ' . $relative . ' quedó incompleto.' ); }
		if ( ! @rename( $temp, $target ) ) { @unlink( $temp ); throw new Exception( 'No se pudo preparar el archivo ' . $relative . ' en el área de trabajo.' ); }
		Digitalisimo_Backups_Jobs::progress( Digitalisimo_Backups_Restore_Job::FLOORS['files'], 'Preparando ' . $relative );
	}

	/* ------------------------------------------------------------------------------------------- 5. Cambio */

	/**
	 * El cambio: tablas (de una vez), historial, llavero y credenciales; archivos movidos (cada unidad de código de una
	 * vez), lo ajeno retirado y, al final, la carpeta de este plugin. Reanudable desde cualquier punto.
	 */
	private static function restore_switch( array $job ) {
		$cursor = (array) ( $job['checkpoint']['switch'] ?? array() );
		$cursor += array( 'step' => 'tables' );
		$started = microtime( true );
		$deadline = self::restore_deadline( $job, 'switch', $cursor );
		Digitalisimo_Backups_Restore_Maintenance::hold( (string) $job['spec']['token'], true, $job['id'] );
		if ( 'tables' === $cursor['step'] ) {
			$cursor = self::restore_swap( $job, $cursor );
			return array( 'next' => 'switch', 'checkpoint' => $cursor, 'message' => 'Base de datos cambiada; moviendo los archivos…' );
		}
		$layout = self::restore_layout();
		$layout['tag'] = (string) $job['spec']['tag'];
		$layout['units'] = (array) ( self::restore_read_json( $job, 'units.json', false ) ?? array() );
		$tree = (string) self::restore_read_json( $job, 'tree.json' )['root'];
		if ( 'move' === $cursor['step'] ) {
			$cursor = self::restore_move( $job, $cursor, $layout, $tree, $deadline );
			if ( 'move' === $cursor['step'] ) return self::restore_slice( 'switch', $cursor, 'Moviendo los archivos restaurados (' . (int) ( $cursor['moved'] ?? 0 ) . ')…', $started, (int) ( $cursor['units'] ?? 0 ), $job );
		}
		if ( 'remove' === $cursor['step'] ) {
			$cursor = self::restore_remove( $job, $cursor, $layout, $deadline );
			if ( 'remove' === $cursor['step'] ) return self::restore_slice( 'switch', $cursor, 'Retirando lo que el respaldo no tenía (' . (int) ( $cursor['removed'] ?? 0 ) . ')…', $started, (int) ( $cursor['units'] ?? 0 ), $job );
		}
		if ( 'self' === $cursor['step'] ) {
			// Este plugin, lo último y de una vez: los tramos anteriores lo necesitaban entero.
			if ( '' !== $layout['self'] ) self::restore_move_unit( $layout['self'], $layout, $tree, $cursor );
			// Y los .htaccess sueltos, una sola vez y al final de todo (cada escritura recarga OpenLiteSpeed en Webuzo).
			$cursor['htaccess'] = self::restore_place_htaccess( $tree, $layout );
			$cursor['step'] = 'done';
		}
		return array( 'next' => 'finish', 'checkpoint' => $cursor, 'message' => 'Sitio cambiado; ajustando direcciones y cachés…' );
	}
	/**
	 * Tablas viejas → `dgbko_`, temporales → su nombre (MySQL: un solo RENAME TABLE, atómico; SQLite, que no lo tiene:
	 * una transacción que copia cada temporal en su tabla). Después, como en la restauración de siempre, se reponen el
	 * historial (con la copia previa), el llavero, la seguridad y las credenciales del respaldo cifradas aquí. El diario
	 * del almacén cubre la muerte entre el cambio y la reposición.
	 */
	private static function restore_swap( array $job, array $cursor ) {
		global $wpdb;
		$tables = self::restore_read_json( $job, 'tables.json' );
		$marker = self::restore_staging( $job ) . '/swap.started';
		// Sólo si el proceso murió con el RENAME en marcha hay que mirar la base de datos para saber si se hizo.
		$pending = ! is_file( $marker ) || array_filter( $tables, function( $row ) { return self::restore_table_exists( (string) $row['temp'] ); } );
		$origin = Digitalisimo_Backups_Credentials::from_journal( self::restore_read_json( $job, 'credentials.json', false ) );
		try {
			if ( $pending ) {
				$history_before = self::fresh_history();
				$vault_before = Digitalisimo_Backups_Keys::snapshot();
				self::write_restore_journal( $origin );
				try {
					if ( false === @file_put_contents( $marker, (string) time() ) ) throw new Exception( 'No se pudo anotar el cambio de tablas en el área de trabajo.' );
					$mode = self::restore_swap_tables( $tables );
				} catch ( Throwable $e ) {
					@unlink( $marker );
					self::remove_restore_journal();
					throw $e;
				}
				$cursor['mode'] = $mode;
				try {
					self::after_restore_sql( $history_before, $vault_before, $origin );
				} finally {
					self::remove_restore_journal();
				}
			} else {
				// Ya cambiadas (el proceso murió después del RENAME): el diario se aplicó al tomar el bloqueo.
				$cursor['mode'] = $cursor['mode'] ?? 'resumed';
				self::restore_credentials( $origin );
			}
		} finally {
			Digitalisimo_Backups_Credentials::forget( $origin );
		}
		// El SQL restaurado trae su propia opción `cron`: esta restauración (y lo que espere) recupera su tick.
		try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$cursor['step'] = 'move';
		$cursor['swapped_at'] = time();
		return $cursor;
	}
	/** 'rename' (MySQL) o 'copy' (SQLite de WordPress: sin RENAME TABLE). */
	private static function restore_swap_mode() {
		global $wpdb;
		$forced = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_restore_swap', null ) : null;
		if ( in_array( $forced, array( 'rename', 'copy' ), true ) ) return $forced;
		$sqlite = defined( 'SQLITE_DRIVER_VERSION' ) || defined( 'SQLITE_DB_DROPIN_VERSION' ) || ( defined( 'DB_ENGINE' ) && 'sqlite' === strtolower( (string) DB_ENGINE ) ) || ( is_object( $wpdb ) && class_exists( 'WP_SQLite_DB', false ) && $wpdb instanceof WP_SQLite_DB );
		return $sqlite ? 'copy' : 'rename';
	}
	private static function restore_swap_tables( array $tables ) {
		global $wpdb;
		$q = array( 'Digitalisimo_Backups_Relocate', 'quote_name' );
		$live = array_flip( array_map( 'strval', (array) $wpdb->get_col( 'SHOW TABLES' ) ) );
		$mode = self::restore_swap_mode();
		if ( ! $tables ) return 'none'; // Un SQL sin tablas (paquetes muy antiguos): nada que cambiar.
		if ( 'rename' === $mode ) {
			$pairs = array();
			foreach ( $tables as $row ) if ( isset( $live[ (string) $row['dest'] ] ) ) $pairs[] = call_user_func( $q, $row['dest'] ) . ' TO ' . call_user_func( $q, $row['old'] );
			foreach ( $tables as $row ) $pairs[] = call_user_func( $q, $row['temp'] ) . ' TO ' . call_user_func( $q, $row['dest'] );
			if ( false === $wpdb->query( 'RENAME TABLE ' . implode( ', ', $pairs ) ) ) throw new Exception( 'No se pudieron cambiar las tablas de una vez: ' . $wpdb->last_error );
			return 'rename';
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new Exception( 'No se pudo empezar la transacción del cambio de tablas: ' . $wpdb->last_error );
		try {
			foreach ( $tables as $row ) {
				$create = $wpdb->get_row( 'SHOW CREATE TABLE ' . call_user_func( $q, $row['temp'] ), ARRAY_N );
				if ( ! is_array( $create ) || empty( $create[1] ) ) throw new Exception( 'No se pudo leer la definición de la tabla temporal de ' . $row['dest'] . '.' );
				$definition = preg_replace( '/^CREATE\s+TABLE\s+(?:`(?:[^`]|``)+`|"(?:[^"]|"")+"|\S+)/i', 'CREATE TABLE ' . call_user_func( $q, $row['dest'] ), (string) $create[1], 1 );
				foreach ( array( 'DROP TABLE IF EXISTS ' . call_user_func( $q, $row['dest'] ), $definition, 'INSERT INTO ' . call_user_func( $q, $row['dest'] ) . ' SELECT * FROM ' . call_user_func( $q, $row['temp'] ), 'DROP TABLE ' . call_user_func( $q, $row['temp'] ) ) as $sql ) {
					if ( false === $wpdb->query( $sql ) ) throw new Exception( 'No se pudo cambiar la tabla ' . $row['dest'] . ': ' . $wpdb->last_error );
				}
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new Exception( 'No se pudo confirmar el cambio de tablas: ' . $wpdb->last_error );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
		return 'copy';
	}
	/**
	 * Mueve lo preparado a su sitio. Las unidades de código cambian de una vez (la vieja se aparta junto a la suya como
	 * `.digitalisimo-old-<marca>-<nombre>` y la nueva va a su lugar: dos `rename()`), los archivos sueltos uno a uno
	 * (`rename()` sobre el anterior). Lo que queda en tree/new es lo que falta: un tramo que muere se retoma sin repetir nada.
	 */
	private static function restore_move( array $job, array $cursor, array $layout, $tree, $deadline ) {
		$units = (array) self::restore_read_json( $job, 'units.json' );
		$moved = 0;
		foreach ( $units as $unit => $state ) {
			if ( empty( $state['changed'] ) || ! empty( $state['mixed'] ) || $unit === $layout['self'] || ! empty( $cursor['units_done'][ $unit ] ) ) continue;
			if ( $moved > 0 && self::restore_exhausted( $deadline, $moved ) ) { $cursor['units'] = $moved; return $cursor; }
			if ( self::restore_move_unit( (string) $unit, $layout, $tree, $cursor ) ) $moved++;
		}
		// Archivos sueltos y de unidades mixtas: lo que quede en tree/new fuera de las unidades que cambian enteras.
		$new = $tree . '/new';
		$stack = array( '' );
		while ( $stack ) {
			$dir = (string) array_pop( $stack );
			$path = rtrim( $new . '/' . $dir, '/' );
			if ( is_link( $path ) || ! is_dir( $path ) ) continue;
			foreach ( (array) @scandir( $path ) as $name ) {
				if ( '.' === $name || '..' === $name ) continue;
				$relative = '' === $dir ? $name : $dir . '/' . $name;
				$full = $new . '/' . $relative;
				$unit = self::restore_unit( $relative . ( is_dir( $full ) ? '/x' : '' ), $layout );
				if ( is_dir( $full ) && ! is_link( $full ) ) {
					// Una unidad entera que todavía no se movió la lleva restore_move_unit() (la del plugin, al final).
					if ( '' !== $unit && $unit === $relative && ! empty( $units[ $unit ]['changed'] ) && empty( $units[ $unit ]['mixed'] ) ) continue;
					$stack[] = $relative;
					continue;
				}
				// Los .htaccess, al final y una sola vez: escribirlos recarga OpenLiteSpeed en Webuzo (H6 del laboratorio).
				if ( '.htaccess' === $name ) continue;
				if ( $moved > 0 && self::restore_exhausted( $deadline, $moved ) ) { $cursor['units'] = $moved; $cursor['moved'] = (int) ( $cursor['moved'] ?? 0 ) + $moved; return $cursor; }
				self::restore_place( $full, $layout['root'] . $relative, $tree . '/old/' . $relative, $layout['root'] );
				$moved++;
			}
		}
		$cursor['moved'] = (int) ( $cursor['moved'] ?? 0 ) + $moved;
		$cursor['units'] = $moved;
		$cursor['step'] = 'remove';
		$cursor['line'] = 0;
		return $cursor;
	}
	/** Mueve a su lugar los .htaccess preparados que no van dentro de una unidad (la raíz, wp-content, medios). */
	private static function restore_place_htaccess( $tree, array $layout ) {
		$placed = 0;
		$stack = array( '' );
		while ( $stack ) {
			$dir = (string) array_pop( $stack );
			$path = rtrim( $tree . '/new/' . $dir, '/' );
			if ( is_link( $path ) || ! is_dir( $path ) ) continue;
			foreach ( (array) @scandir( $path ) as $name ) {
				if ( '.' === $name || '..' === $name ) continue;
				$relative = '' === $dir ? $name : $dir . '/' . $name;
				if ( is_dir( $path . '/' . $name ) && ! is_link( $path . '/' . $name ) ) { $stack[] = $relative; continue; }
				if ( '.htaccess' !== $name ) continue;
				self::restore_place( $path . '/' . $name, $layout['root'] . $relative, $tree . '/old/' . $relative, $layout['root'] );
				$placed++;
			}
		}
		return $placed;
	}
	/** Cambia una unidad de código de una vez; idempotente (si el proceso murió entre los dos `rename()`, sigue). */
	private static function restore_move_unit( $unit, array $layout, $tree, array &$cursor ) {
		if ( ! empty( $cursor['units_done'][ $unit ] ) ) return false;
		$units = $layout['units'] ?? array();
		$staged = $tree . '/new/' . $unit;
		$live = $layout['root'] . $unit;
		if ( is_link( $staged ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El área de trabajo de los archivos tiene un enlace simbólico.' );
		if ( is_link( $live ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Una carpeta de restauración es un enlace simbólico: ' . $unit );
		self::restore_assert_inside( dirname( $live ), $layout['root'] );
		// Lo viejo se aparta junto a lo vivo (mismo disco: un rename() de carpeta no cruza discos) con un nombre oculto que
		// WordPress no carga; la limpieza lo retira.
		$old = dirname( $live ) . '/.digitalisimo-old-' . $layout['tag'] . '-' . basename( $live );
		if ( empty( $units[ $unit ]['in_backup'] ) ) {
			// Una unidad que no está en el respaldo (un plugin instalado después): sale entera (queda en la copia previa).
			if ( is_dir( $live ) ) {
				if ( file_exists( $old ) ) self::restore_remove_tree( $old );
				if ( ! @rename( $live, $old ) ) throw new Exception( 'No se pudo retirar ' . $unit . ', que el respaldo no tiene.' );
				$cursor['old_dirs'][] = substr( $old, strlen( $layout['root'] ) );
			}
		} else {
			if ( ! is_dir( $staged ) ) {
				// Ya se movió (el proceso murió después del segundo rename()).
				$cursor['units_done'][ $unit ] = true;
				return false;
			}
			if ( file_exists( $live ) ) {
				if ( file_exists( $old ) ) self::restore_remove_tree( $old );
				if ( ! @rename( $live, $old ) ) throw new Exception( 'No se pudo apartar ' . $unit . ' para cambiarla.' );
				$cursor['old_dirs'][] = substr( $old, strlen( $layout['root'] ) );
			}
			if ( ! wp_mkdir_p( dirname( $live ) ) || ! self::restore_rename_dir( $staged, $live, $layout['tag'] ) ) throw new Exception( 'No se pudo poner en su lugar ' . $unit . '.' );
		}
		$cursor['units_done'][ $unit ] = true;
		$cursor['moved'] = (int) ( $cursor['moved'] ?? 0 ) + 1;
		return true;
	}
	/**
	 * `rename()` de una carpeta preparada a su lugar. Si cruza de disco, se copia entera junto al destino (con un nombre
	 * oculto, en su mismo disco) y entonces se renombra allí: el cambio sigue siendo de una vez.
	 */
	private static function restore_rename_dir( $from, $to, $tag ) {
		if ( @rename( $from, $to ) ) return true;
		$temp = dirname( $to ) . '/.digitalisimo-new-' . $tag . '-' . basename( $to );
		if ( file_exists( $temp ) ) self::restore_remove_tree( $temp );
		if ( ! self::restore_copy_tree( $from, $temp ) || ! @rename( $temp, $to ) ) { self::restore_remove_tree( $temp ); return false; }
		self::restore_remove_tree( $from );
		return true;
	}
	private static function restore_copy_tree( $from, $to ) {
		if ( is_link( $from ) ) return false;
		if ( is_file( $from ) ) return @copy( $from, $to ) && @filesize( $from ) === @filesize( $to );
		if ( ! is_dir( $from ) || ( ! is_dir( $to ) && ! @mkdir( $to, 0755, true ) ) ) return false;
		foreach ( (array) @scandir( $from ) as $name ) {
			if ( '.' === $name || '..' === $name ) continue;
			if ( ! self::restore_copy_tree( $from . '/' . $name, $to . '/' . $name ) ) return false;
		}
		return true;
	}
	/** Un archivo preparado a su lugar, apartando lo que estorbe (una carpeta donde va un archivo, un archivo donde va una carpeta). */
	private static function restore_place( $staged, $live, $old, $root ) {
		$relative = substr( $live, strlen( $root ) );
		$current = rtrim( $root, '/' );
		foreach ( explode( '/', dirname( $relative ) ) as $part ) {
			if ( '.' === $part || '' === $part ) continue;
			$current .= '/' . $part;
			if ( is_link( $current ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Una carpeta de restauración es un enlace simbólico: ' . $relative );
			if ( file_exists( $current ) && ! is_dir( $current ) ) {
				// Un archivo donde va una carpeta: se retira (está en la copia previa).
				if ( ! @unlink( $current ) ) throw new Exception( 'No se pudo apartar un archivo que estorba a ' . $relative . '.' );
			}
		}
		if ( is_link( $live ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Un archivo de restauración es un enlace simbólico: ' . $relative );
		if ( is_dir( $live ) ) {
			// Una carpeta donde va un archivo: se retira (está en la copia previa).
			self::restore_remove_tree( $live );
			if ( file_exists( $live ) ) throw new Exception( 'No se pudo apartar una carpeta que estorba a ' . $relative . '.' );
		}
		if ( ! wp_mkdir_p( dirname( $live ) ) ) throw new Exception( 'No se pudo preparar una carpeta de destino segura.' );
		self::restore_assert_inside( dirname( $live ), $root );
		if ( ! self::restore_rename_file( $staged, $live ) ) throw new Exception( 'No se pudo reemplazar el archivo ' . $relative . ' durante la restauración.' );
	}
	/**
	 * `rename()` de un archivo; si cruza de disco (los medios montados aparte, un almacén en otro volumen), lo copia junto
	 * al destino, comprueba tamaño y SHA-256 y entonces lo renombra allí (sigue siendo un cambio atómico en el destino).
	 */
	private static function restore_rename_file( $from, $to ) {
		if ( @rename( $from, $to ) ) return true;
		if ( ! is_file( $from ) || is_link( $from ) ) return false;
		$temp = $to . '.digitalisimo-restore-' . bin2hex( random_bytes( 4 ) );
		if ( ! @copy( $from, $temp ) ) { @unlink( $temp ); return false; }
		clearstatcache();
		if ( @filesize( $temp ) !== @filesize( $from ) || @hash_file( 'sha256', $temp ) !== @hash_file( 'sha256', $from ) || ! @rename( $temp, $to ) ) { @unlink( $temp ); return false; }
		@unlink( $from );
		return true;
	}
	private static function restore_assert_inside( $directory, $root ) {
		$real = realpath( $directory );
		if ( false === $real || 0 !== strpos( trailingslashit( wp_normalize_path( $real ) ), $root ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de destino sale de WordPress.' );
	}
	/** Retira, uno a uno, los archivos sueltos o de medios que el respaldo no tiene (extraneous.txt, por tramos). */
	private static function restore_remove( array $job, array $cursor, array $layout, $deadline ) {
		$path = self::restore_staging( $job ) . '/extraneous.txt';
		$handle = is_file( $path ) ? @fopen( $path, 'rb' ) : false;
		$count = 0;
		if ( $handle ) {
			try {
				fseek( $handle, (int) ( $cursor['line'] ?? 0 ) );
				while ( false !== ( $line = fgets( $handle ) ) ) {
					$relative = self::restore_unline( $line );
					$full = $layout['root'] . $relative;
					// Sólo un archivo normal dentro de las raíces gestionadas; nunca un enlace ni lo que el respaldo trae ahora.
					if ( '' !== $relative && false === strpos( '/' . $relative . '/', '/../' ) && self::restore_managed( $relative, $layout ) && ! self::restore_excluded( $relative ) && is_file( $full ) && ! is_link( $full ) ) {
						$real = realpath( dirname( $full ) );
						if ( false !== $real && 0 === strpos( trailingslashit( wp_normalize_path( $real ) ), $layout['root'] ) && @unlink( $full ) ) $count++;
					}
					$cursor['line'] = ftell( $handle );
					if ( self::restore_exhausted( $deadline, $count + 1 ) ) break;
				}
				$eof = feof( $handle );
			} finally {
				fclose( $handle );
			}
			$cursor['removed'] = (int) ( $cursor['removed'] ?? 0 ) + $count;
			$cursor['units'] = $count;
			if ( ! $eof ) return $cursor;
		}
		$cursor['step'] = 'self';
		return $cursor;
	}

	/* ------------------------------------------------------------------------------------------- 6. Direcciones */

	/** Red, direcciones, cachés y comprobación final de la reubicación (E1), con lo que contó la importación. */
	private static function restore_finish( array $job ) {
		$plan = self::restore_plan( $job );
		$cursor = array( 'done' => time() );
		if ( $plan ) {
			// Los nombres de las tablas en este sitio (los del plan, que se calcularon con el sitio intacto).
			$report = $plan->finish( array_column( self::restore_read_json( $job, 'tables.json' ), 'dest' ) );
			$imported = (array) ( $job['checkpoint']['database']['report'] ?? array() );
			foreach ( (array) ( $imported['tables'] ?? array() ) as $table => $counts ) foreach ( (array) $counts as $what => $n ) $report['tables'][ $table ][ $what ] = (int) ( $report['tables'][ $table ][ $what ] ?? 0 ) + (int) $n;
			foreach ( (array) ( $imported['stats'] ?? array() ) as $what => $n ) $report['stats'][ $what ] = (int) ( $report['stats'][ $what ] ?? 0 ) + (int) $n;
			update_option( self::LAST_RELOCATION, array( 'date' => current_time( 'mysql' ), 'file' => basename( (string) $job['spec']['file'] ) ) + $report, false );
			self::restore_write_json( $job, 'relocation.json', $report );
			$cursor['relocated'] = true;
		}
		return array( 'next' => 'cleanup', 'checkpoint' => $cursor, 'message' => 'Restauración aplicada; terminando…' );
	}

	/* ------------------------------------------------------------------------------------------- 7. Limpieza */

	/**
	 * Fin del mantenimiento, informe (lo retirado, la copia previa, la reubicación, las credenciales) y retirada de lo
	 * viejo. Nunca hace fallar la restauración: lo que no se pueda borrar se informa y lo retira la operación siguiente.
	 */
	private static function restore_cleanup( array $job ) {
		global $wpdb;
		$cursor = (array) ( $job['checkpoint']['cleanup'] ?? array() );
		Digitalisimo_Backups_Restore_Maintenance::release();
		$warnings = array();
		$started = microtime( true );
		$deadline = self::restore_deadline( $job, 'cleanup', $cursor );
		try {
			if ( empty( $cursor['reported'] ) ) {
				$cursor['result'] = self::restore_result( $job );
				$cursor['reported'] = true;
			}
			foreach ( (array) self::restore_read_json( $job, 'tables.json', false ) as $row ) {
				foreach ( array( (string) $row['old'], (string) $row['temp'] ) as $table ) if ( false === $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( $table ) ) ) $warnings[] = 'No se pudo retirar la tabla ' . $table . '.';
			}
			// Las tablas viejas de la restauración que ésta deshizo, si la había.
			$undone = (string) ( $job['spec']['rollback']['tag'] ?? '' );
			if ( '' !== $undone ) foreach ( self::restore_leftover_tables( (string) $wpdb->base_prefix, array() ) as $table ) if ( false !== strpos( $table, '_' . $undone . '_' ) ) $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( $table ) );
			$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
			$olds = (array) ( $job['checkpoint']['switch']['old_dirs'] ?? array() );
			// Y las de la restauración que esta vuelta a la copia previa deshizo.
			$undone_job = ! empty( $job['spec']['rollback']['of'] ) ? Digitalisimo_Backups_Jobs::get( (string) $job['spec']['rollback']['of'] ) : null;
			if ( $undone_job ) $olds = array_merge( $olds, (array) ( $undone_job['checkpoint']['switch']['old_dirs'] ?? array() ) );
			// También las que un tramo apartó sin llegar a anotarlas (murió o falló antes de guardar su punto de control).
			$layout = self::restore_layout();
			foreach ( array_filter( array( (string) $job['spec']['tag'], (string) ( $job['spec']['rollback']['tag'] ?? '' ) ) ) as $mark ) {
				foreach ( array_unique( array( '', $layout['plugins'], $layout['themes'], '' === $layout['mu'] ? '' : (string) dirname( $layout['mu'] ) ) ) as $parent ) {
					foreach ( array( '.digitalisimo-old-', '.digitalisimo-new-' ) as $kind ) foreach ( (array) glob( rtrim( $root . $parent, '/' ) . '/' . $kind . $mark . '-*', GLOB_ONLYDIR ) as $dir ) $olds[] = substr( wp_normalize_path( $dir ), strlen( $root ) );
				}
			}
			foreach ( array_unique( $olds ) as $old ) {
				$old = (string) $old;
				if ( ( false === strpos( '/' . $old, '/.digitalisimo-old-' ) && false === strpos( '/' . $old, '/.digitalisimo-new-' ) ) || false !== strpos( $old, '..' ) ) continue;
				if ( ! self::restore_remove_tree( $root . $old, $deadline ) ) return self::restore_slice( 'cleanup', $cursor, 'Retirando los archivos anteriores…', $started, 1, $job );
			}
			$tree = self::restore_read_json( $job, 'tree.json', false );
			if ( is_array( $tree ) && ! empty( $tree['root'] ) ) {
				if ( ! self::restore_remove_tree( (string) $tree['root'], $deadline ) ) return self::restore_slice( 'cleanup', $cursor, 'Retirando los archivos anteriores…', $started, 1, $job );
			}
		} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
			throw $e;
		} catch ( Throwable $e ) {
			$warnings[] = 'Limpieza: ' . $e->getMessage();
		}
		$result = (array) ( $cursor['result'] ?? array( 'file' => basename( (string) $job['spec']['file'] ), 'restored' => true ) );
		$removed = Digitalisimo_Backups_Job_Store::directory( $job['id'] ) . '/removed.txt';
		$list = self::restore_staging( $job ) . '/removed.txt';
		if ( is_file( $list ) ) @rename( $list, $removed );
		if ( function_exists( 'update_option' ) ) update_option( 'digitalisimo_backups_last_restore', array( 'date' => current_time( 'mysql' ), 'job' => (string) $job['id'], 'file' => basename( (string) $job['spec']['file'] ), 'rollback' => ! empty( $job['spec']['rollback'] ), 'safety' => (string) ( $result['safety_backup'] ?? '' ), 'removed' => (int) ( $result['removed']['count'] ?? 0 ), 'sample' => array_slice( (array) ( $result['removed']['sample'] ?? array() ), 0, 50 ) ), false );
		try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		// La caché del servidor web (LSCache de OpenLiteSpeed) seguiría sirviendo las páginas de antes de restaurar (E1).
		if ( method_exists( 'Digitalisimo_Backups_Relocate', 'purge_server_cache' ) ) { try { Digitalisimo_Backups_Relocate::purge_server_cache(); } catch ( Throwable $e ) {} } // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$message = ! empty( $job['spec']['rollback'] ) ? 'Se volvió a la copia previa ' . basename( (string) $job['spec']['file'] ) . ': el sitio está como antes de la restauración que falló.' : 'Restauración terminada desde ' . basename( (string) $job['spec']['file'] ) . '.';
		if ( $warnings ) $result['warnings'] = $warnings;
		return array( 'next' => null, 'checkpoint' => array( 'done' => time(), 'warnings' => $warnings ), 'result' => array( 'restore' => $result ), 'warnings' => $warnings, 'message' => $message );
	}
	/** Informe público: lo que vuelven la pantalla, REST y WP-CLI. Sin rutas del servidor ni secretos. */
	private static function restore_result( array $job ) {
		$spec = (array) $job['spec'];
		$staging = self::restore_staging( $job );
		$count = 0;
		$sample = array();
		$handle = is_file( $staging . '/removed.txt' ) ? @fopen( $staging . '/removed.txt', 'rb' ) : false;
		if ( $handle ) {
			while ( false !== ( $line = fgets( $handle ) ) ) {
				$count++;
				if ( count( $sample ) < 200 ) $sample[] = self::restore_unline( $line );
			}
			fclose( $handle );
		}
		$relocation = self::restore_read_json( $job, 'relocation.json', false );
		$notice = '';
		$config = (array) ( self::restore_read_json( $job, 'config-notes.json', false ) ?? array() );
		foreach ( $config as $relative => $notes ) $notice .= ' ' . $relative . ': ' . implode( '; ', (array) $notes ) . '.';
		// Limpieza (revisión de E1, P1): un auto_prepend_file o auto_append_file que haya quedado apuntando a un archivo que
		// no existe daría 500 a todo; se avisa con el archivo y la ruta.
		foreach ( array( '.user.ini', 'php.ini', '.htaccess' ) as $name ) {
			$text = is_file( ABSPATH . $name ) ? (string) @file_get_contents( ABSPATH . $name, false, null, 0, 1048576 ) : '';
			if ( preg_match_all( '/^\s*(?:php_(?:admin_)?value\s+)?auto_(?:prepend|append)_file\s*=?\s*["\']?(\/[^"\'\r\n;]+)/mi', $text, $m ) ) foreach ( $m[1] as $target ) if ( ! file_exists( trim( $target ) ) ) $notice .= ' Atención: ' . $name . ' carga ' . trim( $target ) . ', que no existe en este servidor; corrígelo o quítalo (PHP respondería con error).';
		}
		$foreign = (array) ( self::restore_read_json( $job, 'foreign.json', false ) ?? array() );
		if ( $foreign ) $notice .= ' No se restauraron ' . count( $foreign ) . ' tabla(s) de otra aplicación que traía el respaldo (sin el prefijo de este WordPress): ' . implode( ', ', array_slice( $foreign, 0, 10 ) ) . ( count( $foreign ) > 10 ? '…' : '' ) . '.';
		$safety = self::restore_safety_file( $job );
		if ( $count ) $notice .= ' Se retiraron ' . $count . ' archivo(s) que el respaldo no tenía (quedan en la copia previa' . ( '' !== $safety ? ' ' . $safety : '' ) . ').';
		try {
			$credentials = self::credentials_status();
			if ( 'needs_reentry' === $credentials['state'] ) $notice .= ' ' . $credentials['notice'];
		} catch ( Throwable $e ) {
			$notice .= ' No se pudo comprobar el estado de las credenciales de los destinos: ' . $e->getMessage();
		}
		if ( $relocation ) $notice .= ' ' . self::relocation_notice( $relocation );
		return array(
			'file' => basename( (string) $spec['file'] ),
			'restored' => true,
			'trust' => (string) ( $spec['source']['trust'] ?? '' ),
			'key' => (string) ( $spec['source']['key'] ?? '' ),
			'adopted' => ! empty( $spec['adopted'] ),
			'notice' => ltrim( $notice ),
			'safety_backup' => ! empty( $spec['rollback'] ) ? basename( (string) $spec['file'] ) : $safety,
			'relocation' => $relocation ? self::relocation_public( $relocation ) : null,
			'removed' => array( 'count' => $count, 'sample' => $sample ),
			'config' => $config,
			'foreign_tables' => $foreign,
			'rollback' => ! empty( $spec['rollback'] ),
		);
	}
	/** Borra un árbol sin seguir enlaces; con plazo, devuelve false si no le dio tiempo (se sigue en el tramo siguiente). */
	private static function restore_remove_tree( $path, $deadline = INF ) {
		if ( is_link( $path ) || is_file( $path ) ) { @unlink( $path ); return true; }
		if ( ! is_dir( $path ) ) return true;
		foreach ( (array) @scandir( $path ) as $name ) {
			if ( '.' === $name || '..' === $name || '' === $name ) continue;
			if ( microtime( true ) >= $deadline ) return false;
			if ( ! self::restore_remove_tree( $path . '/' . $name, $deadline ) ) return false;
		}
		@rmdir( $path );
		return true;
	}

	/* =========================================================================================== Al terminar sin éxito */

	/**
	 * @internal Una restauración que falla o se cancela. Antes del cambio: se retira lo temporal (tablas, área de
	 * trabajo, mantenimiento) y el sitio queda como estaba. Después: se encola la vuelta a la copia previa, que la
	 * restaura entera con este mismo motor; si lo que falla es esa vuelta, se avisa para recuperar a mano. Devuelve el
	 * mensaje del trabajo.
	 */
	public static function restore_on_terminal( array $job, $status, $message ) {
		global $wpdb;
		$spec = (array) ( $job['spec'] ?? array() );
		$switched = self::restore_changed( $job );
		$safety = self::restore_safety_file( $job );
		// En la limpieza el sitio ya está restaurado: un fallo ahí no se deshace (lo que quede, lo retira la siguiente).
		if ( 'cleanup' === (string) ( $job['phase'] ?? '' ) && 'cancelled' !== $status ) {
			Digitalisimo_Backups_Restore_Maintenance::release();
			return 'El sitio quedó restaurado, pero la limpieza no terminó: ' . $message;
		}
		if ( $switched && 'cancelled' !== $status ) {
			if ( empty( $spec['rollback'] ) && '' !== $safety && is_file( rtrim( self::local_directory(), '/\\' ) . '/' . $safety ) ) {
				try {
					$rollback = self::restore_rollback_job( $job );
					Digitalisimo_Backups_Job_Store::event( $job['id'], 'rollback', array( 'job' => $rollback['id'], 'file' => $safety ) );
					self::restore_note_rollback( $job['id'], $rollback['id'], $message );
					return 'La restauración falló a mitad del cambio (' . Digitalisimo_Backups_Jobs::phase_label( (string) $job['phase'] ) . '): ' . $message . ' Se está restaurando la copia previa ' . $safety . ' para dejar el sitio como estaba; el sitio sigue en mantenimiento hasta que termine.';
				} catch ( Throwable $e ) {
					$message .= ' No se pudo encolar la vuelta a la copia previa: ' . $e->getMessage();
				}
			}
			$copy = ! empty( $spec['rollback'] ) ? basename( (string) $spec['file'] ) : $safety;
			return ( ! empty( $spec['rollback'] ) ? 'La vuelta a la copia previa también falló: ' : 'La restauración falló a mitad del cambio: ' ) . $message . ' El sitio sigue en mantenimiento para no servirse a medias. Restaura a mano ' . ( '' !== $copy ? $copy : 'la copia previa' ) . ' (WP-CLI: wp digitalisimo-backups restore ' . ( '' !== $copy ? $copy : '<archivo>' ) . ' --confirm=RESTAURAR' . ( is_multisite() ? ' --network' : '' ) . '); si hace falta, borra .maintenance de la raíz para entrar.';
		}
		// Antes del cambio: nada del sitio se tocó; se retira lo temporal (las tablas temporales nacen en la base de datos).
		try {
			$tables = in_array( (string) ( $job['phase'] ?? '' ), array( 'inspect', 'safety' ), true ) ? array() : self::restore_read_json( $job, 'tables.json', false );
			foreach ( (array) $tables as $row ) $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( (string) $row['temp'] ) );
			$tree = self::restore_read_json( $job, 'tree.json', false );
			if ( is_array( $tree ) && ! empty( $tree['root'] ) ) self::restore_remove_tree( (string) $tree['root'] );
		} catch ( Throwable $e ) {
			// La operación siguiente retira lo que quede (tablas de otra marca y áreas de trabajo de trabajos terminados).
		}
		try { Digitalisimo_Backups_Job_Store::remove_staging( $job['id'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		Digitalisimo_Backups_Restore_Maintenance::release();
		if ( 'cancelled' === $status ) return (string) $message;
		return rtrim( (string) $message, '.' ) . '. La restauración se detuvo antes de cambiar el sitio: la base de datos y los archivos siguen como estaban' . ( '' !== $safety && ! empty( $job['checkpoint']['safety']['file'] ) ? ' (la copia previa ' . $safety . ' queda en el historial)' : '' ) . '.';
	}
	/** Encola la vuelta a la copia previa de una restauración que falló a mitad del cambio. */
	private static function restore_rollback_job( array $job ) {
		$spec = (array) $job['spec'];
		$file = self::restore_safety_file( $job );
		$path = rtrim( self::local_directory(), '/\\' ) . '/' . $file;
		$item = self::history_item( $file );
		$rollback = array(
			'type' => 'restore', 'scope' => $spec['scope'], 'policy' => 'restore', 'destination' => 'local', 'inline' => false, 'retain' => 1, 'retention' => 'none',
			'file' => $file,
			'source' => array( 'container' => 'zip', 'trust' => ! empty( $item['sha256'] ) ? 'provenance' : 'untrusted', 'auth' => 'unsigned', 'key' => '', 'staged' => false, 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $path ) ),
			'addresses' => 'current',
			'rollback' => array( 'of' => (string) $job['id'], 'file' => (string) $spec['file'], 'tag' => (string) $spec['tag'] ),
			'safety' => '', 'safety_spec' => array(),
			'tag' => bin2hex( random_bytes( 3 ) ),
			'token' => (string) $spec['token'],
			'adopted' => false, 'sync' => ! empty( $spec['sync'] ),
			'site_id' => (int) ( $spec['site_id'] ?? 1 ),
			'origin' => array( 'via' => 'rollback' ) + (array) ( $spec['origin'] ?? array() ),
			'engine' => $spec['engine'],
		);
		return Digitalisimo_Backups_Jobs::create_restore( $rollback );
	}
	/** El trabajo que falló guarda el de su vuelta a la copia previa y la causa (lo siguen la pantalla, REST y WP-CLI). */
	private static function restore_note_rollback( $id, $rollback, $cause ) {
		$path = Digitalisimo_Backups_Job_Store::directory( $id ) . '/rollback.json';
		@file_put_contents( $path, (string) wp_json_encode( array( 'job' => (string) $rollback, 'cause' => (string) $cause ) ) );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
	}

	/* =========================================================================================== Estado público */

	/**
	 * Estado de la restauración para la pantalla, `status` de REST y WP-CLI: la que está en curso (o su vuelta a la copia
	 * previa) o la última que no terminó bien. Sin rutas ni secretos.
	 */
	private static function restore_status() {
		try {
			$jobs = Digitalisimo_Backups_Jobs::all();
		} catch ( Throwable $e ) {
			return array( 'state' => 'none', 'notice' => '' );
		}
		$active = null;
		$last = null;
		foreach ( $jobs as $job ) {
			if ( ! Digitalisimo_Backups_Jobs::is_restore( $job ) ) continue;
			if ( ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) $active = $job;
			elseif ( null === $last || (int) $job['finished_at'] >= (int) $last['finished_at'] ) $last = $job;
		}
		if ( $active ) {
			$view = Digitalisimo_Backups_Jobs::view( $active );
			$rollback = ! empty( $active['spec']['rollback'] );
			$waiting = ! $active['lease'] && (int) $active['retry_at'] > time();
			$state = $rollback ? 'rollback' : ( ! empty( $active['attempts'][ $active['phase'] ] ) ? 'interrupted' : 'running' );
			$text = $rollback ? 'Volviendo a la copia previa ' . basename( (string) $active['spec']['file'] ) . ' tras una restauración que falló a mitad: ' : 'Restauración en curso de ' . basename( (string) $active['spec']['file'] ) . ': ';
			$text .= $view['phase_label'] . ' (' . $view['percent'] . ' %). ';
			if ( 'interrupted' === $state ) $text .= 'Se interrumpió y se reanudará desde su último punto de control' . ( $waiting ? ' a las ' . $view['retry_label'] : '' ) . '. ';
			$text .= Digitalisimo_Backups_Restore_Job::cancellable( (string) $active['phase'] ) && ! $rollback ? 'El sitio sigue entero hasta el cambio.' : 'El sitio está en mantenimiento hasta que termine.';
			return array( 'state' => $state, 'job' => (string) $active['id'], 'phase' => (string) $active['phase'], 'phase_label' => $view['phase_label'], 'percent' => $view['percent'], 'file' => basename( (string) $active['spec']['file'] ), 'safety' => self::restore_safety_file( $active ), 'notice' => trim( $text ), 'maintenance' => Digitalisimo_Backups_Restore_Maintenance::ours() );
		}
		// La última fue la vuelta a la copia previa: el sitio está como antes, pero la restauración pedida no se hizo.
		if ( $last && 'success' === $last['status'] && ! empty( $last['spec']['rollback'] ) && time() - (int) $last['finished_at'] < 7 * 86400 ) {
			$failed = Digitalisimo_Backups_Jobs::get( (string) ( $last['spec']['rollback']['of'] ?? '' ) );
			$why = $failed ? self::restore_rollback_info( $failed['id'] )['cause'] : '';
			return array( 'state' => 'rolled_back', 'job' => (string) $last['id'], 'file' => basename( (string) ( $last['spec']['rollback']['file'] ?? '' ) ), 'safety' => basename( (string) $last['spec']['file'] ), 'notice' => 'La restauración de ' . basename( (string) ( $last['spec']['rollback']['file'] ?? '' ) ) . ' falló a mitad del cambio' . ( '' !== $why ? ' (' . $why . ')' : '' ) . ' y el sitio volvió a la copia previa ' . basename( (string) $last['spec']['file'] ) . ': está como antes de restaurar.', 'maintenance' => Digitalisimo_Backups_Restore_Maintenance::ours() );
		}
		if ( $last && 'success' !== $last['status'] && time() - (int) $last['finished_at'] < 7 * 86400 ) {
			$message = is_array( $last['error'] ?? null ) ? (string) $last['error']['message'] : (string) ( $last['progress']['message'] ?? '' );
			$state = 'cancelled' === $last['status'] ? 'cancelled' : ( self::restore_switched( $last ) ? 'failed_midway' : 'failed' );
			return array( 'state' => $state, 'job' => (string) $last['id'], 'file' => basename( (string) $last['spec']['file'] ), 'safety' => self::restore_safety_file( $last ), 'notice' => $message, 'maintenance' => Digitalisimo_Backups_Restore_Maintenance::ours() );
		}
		return array( 'state' => 'none', 'notice' => '' );
	}
}
