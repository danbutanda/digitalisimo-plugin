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
 *   tree/new, tree/old  archivos que entran y los que salen (o, si el almacén está en otro disco, una carpeta oculta y
 *                  aleatoria junto a WordPress, porque mover exige el mismo disco)
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
	/** Plazo del tramo: el del tick, con la escala adaptativa de T2 y como mucho el tope de un tramo. */
	private static function restore_deadline( array $job, $phase, array $cursor ) {
		return Digitalisimo_Backups_Step_Budget::deadline( Digitalisimo_Backups_Step_Budget::scale( $job, $phase, $cursor ), max( Digitalisimo_Backups_Step_Budget::slice_cap(), 10.0 ) );
	}
	private static function restore_exhausted( $deadline, $units ) { return Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units, 4194304 ); }
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
		// El área de trabajo de una restauración junto a WordPress nunca se restaura.
		if ( 0 === strpos( $relative, '.digitalisimo-restore-' ) ) return null;
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
		$rollback = ! empty( $job['spec']['rollback'] );
		$args = array( 'addresses' => 'backup' === ( $job['spec']['addresses'] ?? '' ) ? 'backup' : 'current', 'tables' => is_array( $data['database_tables'] ?? null ) ? $data['database_tables'] : null );
		$destination = Digitalisimo_Backups_Relocate::describe();
		// La vuelta a la copia previa es de esta misma instalación: nada que reubicar, aunque el sitio esté a medio cambiar.
		if ( ! $rollback ) {
			$plan = Digitalisimo_Backups_Relocate::plan( $source, $destination, $args );
			if ( $plan->problems() ) throw new Digitalisimo_Backups_Permanent_Error( implode( ' ', $plan->problems() ) );
		}
		self::restore_write_json( $job, 'plan.json', array( 'origin' => $source, 'destination' => $destination, 'args' => $args, 'skip' => $rollback ) );
		// Tablas: del respaldo → de este sitio (con el prefijo de la reubicación) → temporal → vieja.
		$tables = is_array( $data['database_tables'] ?? null ) ? array_values( array_map( 'strval', $data['database_tables'] ) ) : array_values( (array) ( $cursor['tables'] ?? array() ) );
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
		foreach ( array( rtrim( ABSPATH, '/\\' ), rtrim( WP_CONTENT_DIR, '/\\' ) ) as $target ) {
			$probe = $private . '/.probe-' . bin2hex( random_bytes( 4 ) );
			$there = $target . '/.digitalisimo-restore-probe-' . bin2hex( random_bytes( 4 ) );
			if ( false === @file_put_contents( $probe, 'x' ) ) throw new Exception( 'No se pudo preparar el área de trabajo de los archivos.' );
			if ( @rename( $probe, $there ) ) {
				if ( ! @rename( $there, $probe ) ) @unlink( $there );
			} else {
				$same = false;
			}
			@unlink( $probe );
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
		if ( 'scan' === $mode ) {
			$probe = new Digitalisimo_Backups_Relocate_Probe( (string) ( $data['table_prefix'] ?? '' ) );
			$guard->on_insert( $probe );
			$renames = self::relocation_renames( $data );
			// Con la lista del manifiesto, la pasada en seco cambia los nombres como la importación: una vista o dos tablas
			// que chocarían se rechazan antes de la copia previa.
			if ( is_array( $data['database_tables'] ?? null ) ) {
				foreach ( $data['database_tables'] as $n => $table ) $renames[ (string) $table ] = 'dgbkr_scan_' . $n;
			}
			if ( $renames ) $guard->rename_tables( $renames );
		} else {
			$plan = self::restore_plan( $job );
			$tables = self::restore_read_json( $job, 'tables.json' );
			if ( $plan && $plan->needed() ) $plan->attach( $guard, array_column( $tables, 'backup' ) );
			foreach ( $tables as $row ) $temp[ (string) $row['backup'] ] = (string) $row['temp'];
			$guard->rename_tables( $temp );
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
			$base = $offset;
			$stop = false;
			$size = (int) ( $expected['size'] ?? 0 );
			while ( ! $stop ) {
				$chunk = fread( $handle, 262144 );
				if ( false === $chunk ) throw new Exception( 'No se pudo leer la base de datos del respaldo en el área de trabajo.' );
				$last = '' === $chunk;
				// Al final, la última sentencia puede no llevar «;»: termina con el archivo.
				$statements = $last ? $reader->finish() : $reader->feed( $chunk );
				$ends = $last ? array_fill( 0, count( $statements ), $size - $base ) : $reader->ends();
				foreach ( $statements as $k => $statement ) {
					$start = $offset;
					$end = $base + (int) ( $ends[ $k ] ?? 0 );
					if ( 'import' === $mode ) $cursor = self::restore_import_statement( $guard, $statement, $start, $cursor );
					else $cursor = self::restore_scan_statement( $guard, $statement, $cursor );
					$offset = $end;
					$cursor['offset'] = $offset;
					$units++;
					if ( 0 === $units % 200 ) self::restore_progress( 'scan' === $mode ? 'inspect' : 'database', 'scan' === $mode ? 0.9 + 0.1 * $offset / max( 1, $size ) : $offset / max( 1, $size ), 'scan' === $mode ? 'Comprobando la base de datos del respaldo en seco…' : 'Importando la base de datos a tablas temporales (' . Digitalisimo_Backups_Archive_Inspector::megabytes( $offset ) . ' de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $size ) . ')…' );
					if ( ! $last && self::restore_exhausted( $deadline, $units ) ) { $stop = true; break; }
				}
				if ( $last ) { $done = true; break; }
				$base += strlen( $chunk );
			}
		} finally {
			fclose( $handle );
		}
		$cursor['sentences'] = $guard->count();
		$cursor['units'] = $units;
		if ( $probe ) $cursor['probe'] = array_replace_recursive( (array) ( $cursor['probe'] ?? array() ), $probe->result() );
		if ( $plan ) $cursor['report'] = self::restore_add_report( (array) ( $cursor['report'] ?? array() ), $plan );
		if ( ! $done ) return $cursor;
		if ( empty( $cursor['statements'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del respaldo está vacía.' );
		$cursor['step'] = 'scan' === $mode ? 'plan' : 'done';
		return $cursor;
	}
	private static function restore_inspect_sql( array $job ) { return (array) ( $job['checkpoint']['inspect']['sql'] ?? array() ); }
	private static function restore_scan_statement( $guard, $statement, array $cursor ) {
		if ( ! $guard->check( $statement ) ) return $cursor;
		$cursor['statements'] = (int) ( $cursor['statements'] ?? 0 ) + 1;
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
	private static function restore_import_statement( $guard, $statement, $start, array $cursor ) {
		global $wpdb;
		if ( ! $guard->check( $statement ) ) return $cursor;
		if ( preg_match( '/^DROP\s+TABLE\s+IF\s+EXISTS\s+`((?:[^`]|``)+)`/i', $statement, $m ) ) {
			$cursor['table'] = str_replace( '``', '`', $m[1] );
			$cursor['table_start'] = (int) $start;
			$cursor['report_start'] = (array) ( $cursor['report'] ?? array() );
			$cursor['tables_done'] = (int) ( $cursor['tables_done'] ?? 0 ) + 1;
		}
		if ( preg_match( '/^SET\s+NAMES\s+(utf8mb4|utf8)\b/i', $statement, $m ) ) $cursor['names'] = strtolower( $m[1] );
		if ( preg_match( '/^SET\s+foreign_key_checks\s*=\s*([01])/i', $statement, $m ) ) $cursor['fk'] = (int) $m[1];
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
		return array( 'next' => 'files', 'checkpoint' => array( 'done' => time(), 'statements' => (int) $cursor['statements'], 'report' => (array) ( $cursor['report'] ?? array() ), 'names' => $cursor['names'] ?? '', 'restarted' => (int) ( $cursor['restarted'] ?? 0 ) ), 'message' => 'Base de datos lista en tablas temporales; preparando los archivos…' );
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
		return false !== strpos( $path, '/.git/' ) || false !== strpos( $path, '/node_modules/' ) || false !== strpos( $path, '/cache/' ) || 0 === strpos( $relative, '.digitalisimo-restore-' ) || 0 === strpos( $relative, '.digitalisimo-case-' );
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
				if ( 'compare' === $cursor['step'] ) {
					if ( $whole && ! empty( $units[ $unit ]['changed'] ) ) continue;
					$live = $root . $relative;
					$same = is_file( $live ) && ! is_link( $live ) && (int) @filesize( $live ) === (int) $stat['size'];
					if ( $same && $index ) {
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
				$cursor['staged'] = (int) ( $cursor['staged'] ?? 0 ) + 1;
			}
		} finally {
			fclose( $bitmap );
			if ( $index ) $index->close();
			self::close_quietly( $zip );
		}
		if ( $units_changed ) self::restore_write_json( $job, 'units.json', $units );
		$cursor['units'] = $work;
		if ( $i < $count ) { $cursor['i'] = $i; return $cursor; }
		if ( 'compare' === $cursor['step'] ) {
			$cursor['changed_units'] = count( array_filter( $units, function( $state ) { return ! empty( $state['changed'] ) && empty( $state['mixed'] ); } ) );
			return array( 'step' => 'extract', 'i' => 0 ) + $cursor;
		}
		$cursor['step'] = 'done';
		return $cursor;
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
	 * Mueve lo preparado a su sitio. Las unidades de código cambian de una vez (la vieja a tree/old, la nueva a su
	 * lugar: dos `rename()`), los archivos sueltos uno a uno (`rename()` sobre el anterior). Lo que queda en tree/new es
	 * lo que falta: un tramo que muere se retoma sin repetir nada.
	 */
	private static function restore_move( array $job, array $cursor, array $layout, $tree, $deadline ) {
		$units = (array) self::restore_read_json( $job, 'units.json' );
		$moved = 0;
		foreach ( $units as $unit => $state ) {
			if ( empty( $state['changed'] ) || ! empty( $state['mixed'] ) || $unit === $layout['self'] ) continue;
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
	/** Cambia una unidad de código de una vez; idempotente (si el proceso murió entre los dos `rename()`, sigue). */
	private static function restore_move_unit( $unit, array $layout, $tree, array &$cursor ) {
		$staged = $tree . '/new/' . $unit;
		$live = $layout['root'] . $unit;
		$old = $tree . '/old/' . $unit;
		if ( is_link( $staged ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El área de trabajo de los archivos tiene un enlace simbólico.' );
		if ( ! is_dir( $staged ) ) {
			// Una unidad que cambió y no está en el respaldo (un plugin instalado después): sale entera (queda en la copia previa).
			if ( ! is_dir( $live ) || is_link( $live ) || file_exists( $old ) ) return false;
			self::restore_assert_inside( dirname( $live ), $layout['root'] );
			if ( ! wp_mkdir_p( dirname( $old ) ) || ! @rename( $live, $old ) ) throw new Exception( 'No se pudo retirar ' . $unit . ', que el respaldo no tiene.' );
			$cursor['moved'] = (int) ( $cursor['moved'] ?? 0 ) + 1;
			return true;
		}
		self::restore_assert_inside( dirname( $live ), $layout['root'] );
		if ( file_exists( $live ) || is_link( $live ) ) {
			if ( is_link( $live ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Una carpeta de restauración es un enlace simbólico: ' . $unit );
			if ( ! wp_mkdir_p( dirname( $old ) ) ) throw new Exception( 'No se pudo preparar el área de trabajo de los archivos que salen.' );
			if ( file_exists( $old ) ) self::restore_remove_tree( $old );
			if ( ! @rename( $live, $old ) ) throw new Exception( 'No se pudo apartar ' . $unit . ' para cambiarla.' );
		}
		if ( ! wp_mkdir_p( dirname( $live ) ) || ! @rename( $staged, $live ) ) throw new Exception( 'No se pudo poner en su lugar ' . $unit . '.' );
		$cursor['moved'] = (int) ( $cursor['moved'] ?? 0 ) + 1;
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
				$aside = dirname( $old ) . '/.file-' . basename( $current ) . '-' . bin2hex( random_bytes( 3 ) );
				if ( ! wp_mkdir_p( dirname( $aside ) ) || ! @rename( $current, $aside ) ) throw new Exception( 'No se pudo apartar un archivo que estorba a ' . $relative . '.' );
			}
		}
		if ( is_link( $live ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Un archivo de restauración es un enlace simbólico: ' . $relative );
		if ( is_dir( $live ) ) {
			if ( ! wp_mkdir_p( dirname( $old ) ) || ! @rename( $live, $old ) ) throw new Exception( 'No se pudo apartar una carpeta que estorba a ' . $relative . '.' );
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
