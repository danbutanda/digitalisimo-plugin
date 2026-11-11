<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cola persistente de respaldos: estados, arrendamiento, latido, detector de trabajos colgados, reintentos con espera,
 * caducidad y cancelación. Un trabajo avanza por pasos que caben en una petición o en una ejecución de cron; el
 * estado vive en el almacén privado (`Digitalisimo_Backups_Job_Store`) y WP-Cron sólo dispara.
 *
 * API estable para la interfaz, el cron, REST y WP-CLI (por Digitalisimo_Backups_Service): enqueue(), tick(), cancel(), get(),
 * all(), views().
 */
final class Digitalisimo_Backups_Jobs {
	const HOOK_TICK = 'digitalisimo_backups_job_tick';
	const HOOK_WATCHDOG = 'digitalisimo_backups_jobs_watchdog';
	const AJAX_TICK = 'digitalisimo_backups_job_tick';
	const AJAX_STATUS = 'digitalisimo_backups_job_status';
	const ACTION_CANCEL = 'digitalisimo_backups_job_cancel';
	/** Tick de una restauración con su testigo (sin sesión: tras el cambio, la cuenta puede no existir en el respaldo). */
	const AJAX_RESTORE_TICK = 'digitalisimo_backups_restore_tick';
	const NONCE = 'digitalisimo_backups_jobs';
	const PHASES = array( 'preflight', 'database', 'pack', 'verify', 'publish', 'transfer', 'prune' );
	/** Espera entre intentos de una fase de restauración: tras una muerte se reanuda pronto, desde su punto de control. */
	const RESTORE_BACKOFF = array( 10, 60, 300 );
	const STALE_AFTER = 600;
	const TAKEOVER_AFTER = 1800;
	const EXPIRES_AFTER = 86400;
	const BACKOFF = array( 60, 300, 900 );
	const MAIN_SITE_ONLY = 'Los respaldos completos de la red se gestionan desde el sitio principal o la administración de red.';
	/** Sustituye la ejecución de una fase (sólo pruebas): callable( $phase, $job ) que devuelve el resultado de la fase. */
	public static $phase_runner = null;
	private static $current = null;
	private static $shutdown_registered = false;
	/**
	 * Punto de extensión: otros tipos de trabajo (`spec.kind`) con sus propias fases, p. ej. la migración que este sitio
	 * tira de otro (Digitalisimo_Backups_Migrate). La clase registrada da job_phases(), job_phase( $phase ) (label,
	 * floor, max, cancellable, status), run_phase(), discard(), on_terminal() y job_view(); sus fases no repiten nombre
	 * con las del respaldo salvo `preflight`, que se trata como la del respaldo.
	 */
	private static $kinds = array();
	public static function register_kind( $kind, $class ) { self::$kinds[ (string) $kind ] = $class; }
	public static function kind_handler( array $spec ) { $kind = (string) ( $spec['kind'] ?? '' ); return '' !== $kind && isset( self::$kinds[ $kind ] ) ? self::$kinds[ $kind ] : null; }
	/**
	 * Datos de una fase de un tipo registrado. Con el trabajo (o su spec) se miran sólo las fases de su tipo (N7: dos tipos
	 * con una fase del mismo nombre nunca se mezclan, y una restauración o un respaldo no tienen); sin él, por nombre.
	 */
	private static function phase_info( $phase, $context = null ) {
		if ( is_array( $context ) ) {
			$spec = is_array( $context['spec'] ?? null ) ? $context['spec'] : $context;
			if ( 'restore' === ( $context['kind'] ?? '' ) || 'restore' === ( $spec['type'] ?? '' ) ) return null;
			$class = self::kind_handler( (array) $spec );
			return $class ? $class::job_phase( $phase ) : null;
		}
		foreach ( self::$kinds as $class ) { $info = $class::job_phase( $phase ); if ( $info ) return $info; }
		return null;
	}
	/** Lo de la spec del trabajo que dice su tipo (para el paso en curso): sin rutas ni secretos. */
	private static function current_spec( array $job ) {
		$spec = (array) ( $job['spec'] ?? array() );
		return array( 'kind' => (string) ( $spec['kind'] ?? '' ), 'type' => (string) ( $spec['type'] ?? '' ), 'rollback' => ! empty( $spec['rollback'] ), 'destination' => (string) ( $spec['destination'] ?? 'local' ) );
	}
	/** Deja en pausa el paso en curso para una copia sincrónica dentro de él (run_inline) y lo devuelve; resume() lo retoma. */
	public static function suspend() { $current = self::$current; self::$current = null; return $current; }
	public static function resume( $current ) { self::$current = $current; }

	public static function init() {
		add_action( self::HOOK_TICK, array( __CLASS__, 'cron_tick' ) );
		add_action( self::HOOK_WATCHDOG, array( __CLASS__, 'watchdog' ) );
		add_action( 'wp_ajax_' . self::AJAX_TICK, array( __CLASS__, 'ajax_tick' ) );
		add_action( 'wp_ajax_' . self::AJAX_STATUS, array( __CLASS__, 'ajax_status' ) );
		add_action( 'wp_ajax_' . self::ACTION_CANCEL, array( __CLASS__, 'ajax_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( __CLASS__, 'admin_cancel' ) );
		add_action( 'wp_ajax_' . self::AJAX_RESTORE_TICK, array( __CLASS__, 'ajax_restore_tick' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_RESTORE_TICK, array( __CLASS__, 'ajax_restore_tick' ) );
		add_filter( 'cron_request', array( __CLASS__, 'cron_request' ) );
		add_filter( 'pre_get_ready_cron_jobs', array( __CLASS__, 'restore_cron_jobs' ) );
		add_action( 'init', array( __CLASS__, 'ensure_watchdog' ) );
	}
	/**
	 * Mientras una restauración tiene el sitio en mantenimiento, WP-Cron sólo ejecuta sus eventos (el tick y el
	 * watchdog), nunca los demás que venzan: tras el cambio, la opción `cron` es la del respaldo, siempre atrasada, y sus
	 * eventos (actualizaciones automáticas, correos, tareas de plugins) correrían a mitad del cambio (revisión, P1). Los
	 * demás siguen en la opción y corren cuando la restauración termina.
	 */
	public static function restore_cron_jobs( $pre ) {
		if ( null !== $pre || ! function_exists( '_get_cron_array' ) ) return $pre;
		if ( ! defined( 'DIGITALISIMO_BACKUPS_RESTORE_MINIMAL' ) && ! Digitalisimo_Backups_Restore_Maintenance::ours() ) return $pre;
		$ready = array();
		$now = microtime( true );
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			if ( $timestamp > $now ) break;
			foreach ( array( self::HOOK_TICK, self::HOOK_WATCHDOG ) as $hook ) if ( isset( $hooks[ $hook ] ) ) $ready[ $timestamp ][ $hook ] = $hooks[ $hook ];
		}
		return $ready;
	}
	/** Al desactivar se retiran los disparadores; los trabajos se quedan en disco y se retoman o caducan al volver. */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK_TICK );
		wp_clear_scheduled_hook( self::HOOK_WATCHDOG );
	}

	/* ------------------------------------------------------------------ Cola */

	/**
	 * Especificación inmutable: destino, retención, nombre final, cifrado y huella de la clave se fijan al encolar; las
	 * credenciales y las claves nunca. Con el cifrado activo y sin clave lanza la excepción de plan(): no se encola un
	 * respaldo que saldría en claro. La copia previa a una restauración nunca se cifra.
	 */
	public static function spec( array $args ) {
		$destination = (string) ( $args['destination'] ?? 'local' );
		$retention = (string) ( $args['retention'] ?? 'global' );
		$security = Digitalisimo_Backups_Security::job_settings( ! empty( $args['inline'] ) );
		$file = (string) ( $args['file'] ?? Digitalisimo_Backups::backup_file_name() );
		if ( $security['encrypt'] && '.zip' === substr( $file, -4 ) ) $file = substr( $file, 0, -4 ) . '.dgbk';
		$destination = Digitalisimo_Backups_Destinations::exists( $destination ) ? $destination : 'local';
		return array(
			'scope' => is_multisite() ? 'network' : 'installation',
			'type' => 'full',
			'destination' => $destination,
			// «Sólo en el remoto» (I4): el ZIP local se borra cuando la copia remota está verificada. Nunca sin destino remoto
			// ni en una copia previa o un paquete en línea (se restauran desde el almacén).
			'remote_only' => ! empty( $args['remote_only'] ) && 'local' !== $destination && empty( $args['inline'] ),
			'policy' => (string) ( $args['policy'] ?? 'manual' ),
			'retain' => max( 1, min( 100, (int) ( $args['retain'] ?? 5 ) ) ),
			'retention' => in_array( $retention, array( 'global', 'policy', 'none' ), true ) ? $retention : 'global',
			'site_id' => (int) ( $args['site_id'] ?? 0 ) ?: (int) get_current_blog_id(),
			'file' => $file,
			'inline' => ! empty( $args['inline'] ),
			'security' => $security,
			'origin' => array(
				'via' => (string) ( $args['via'] ?? 'admin-post' ),
				'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'blog_id' => (int) get_current_blog_id(),
				'context' => ! empty( $args['network'] ) ? 'network' : 'site',
			),
			'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
		);
	}
	/** Encola sin duplicar: una política (o el respaldo manual) nunca tiene dos trabajos sin terminar. */
	public static function enqueue( array $spec ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $spec ) {
			foreach ( self::active() as $job ) {
				if ( empty( $job['spec']['inline'] ) && ( $job['spec']['policy'] ?? '' ) === $spec['policy'] ) return array( 'duplicate' => true, 'job' => $job );
			}
			$job = Digitalisimo_Backups_Job_Store::create( self::blank( Digitalisimo_Backups_Job_Store::new_id(), $spec, 'queued' ) );
			Digitalisimo_Backups_Job_Store::event( $job['id'], 'queued', array( 'policy' => $spec['policy'], 'destination' => $spec['destination'], 'via' => $spec['origin']['via'] ?? '' ) );
			return array( 'duplicate' => false, 'job' => $job );
		} );
	}
	private static function blank( $id, array $spec, $status, $kind = 'backup' ) {
		$now = time();
		return array(
			'format' => Digitalisimo_Backups_Job_Store::FORMAT,
			'id' => $id,
			'kind' => in_array( $kind, array( 'restore', 'import' ), true ) ? $kind : 'backup',
			'rev' => 0,
			'spec' => $spec,
			'status' => $status,
			'phase' => 'restore' === $kind ? Digitalisimo_Backups_Restore_Job::PHASES[0] : ( 'import' === $kind ? Digitalisimo_Backups_Import_Job::PHASES[0] : 'preflight' ),
			'progress' => array( 'percent' => 0, 'message' => 'En cola.' ),
			'checkpoint' => array(),
			'attempts' => array(),
			'retry_at' => 0,
			'lease' => null,
			'last_seen_at' => 0,
			'cancel_requested_at' => 0,
			'created_at' => $now,
			'queued_at' => microtime( true ),
			'started_at' => 0,
			'finished_at' => 0,
			'result' => array(),
			'error' => null,
			'warnings' => array(),
		);
	}
	/**
	 * Encola una restauración (Digitalisimo_Backups_Restore_Job): nunca con otro trabajo sin terminar, de respaldo o de
	 * restauración. Devuelve array( 'busy' => bool, 'job' => trabajo nuevo o el que lo impide ). $prepare( $job ) corre
	 * con el trabajo ya creado y antes de soltar el cerrojo (deja en su área de trabajo lo que la petición tiene, como el
	 * ZIP descifrado); si lanza, el trabajo se retira.
	 */
	public static function enqueue_restore( array $spec, $prepare = null, $except = '' ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $spec, $prepare, $except ) {
			// $except: el trabajo que pide la restauración y espera su resultado (una migración) no cuenta como otro a medias.
			$active = array_values( array_filter( self::active(), function( $job ) use ( $except ) { return '' === (string) $except || (string) $job['id'] !== (string) $except; } ) );
			if ( $active ) return array( 'busy' => true, 'job' => $active[0] );
			$job = Digitalisimo_Backups_Job_Store::create( self::blank( Digitalisimo_Backups_Job_Store::new_id(), $spec, 'queued', 'restore' ) );
			try {
				if ( $prepare ) $job = call_user_func( $prepare, $job ) ?: $job;
			} catch ( Throwable $e ) {
				Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
				throw $e;
			}
			Digitalisimo_Backups_Job_Store::event( $job['id'], 'queued', array( 'kind' => 'restore', 'file' => (string) ( $spec['file'] ?? '' ), 'via' => $spec['origin']['via'] ?? '', 'rollback' => ! empty( $spec['rollback'] ) ) );
			return array( 'busy' => false, 'job' => $job );
		} );
	}
	/**
	 * Encola la importación de un respaldo de Backuply (Digitalisimo_Backups_Import_Job): nunca durante una restauración ni
	 * dos veces el mismo archivo. Devuelve array( 'busy' | 'duplicate' => bool, 'job' => el nuevo o el que lo impide ).
	 */
	public static function enqueue_import( array $spec ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $spec ) {
			foreach ( self::active() as $job ) {
				if ( self::is_restore( $job ) ) return array( 'busy' => true, 'duplicate' => false, 'job' => $job );
				if ( Digitalisimo_Backups_Import_Job::is_import( $job ) && ( $job['spec']['policy'] ?? '' ) === $spec['policy'] ) return array( 'busy' => false, 'duplicate' => true, 'job' => $job );
			}
			$job = Digitalisimo_Backups_Job_Store::create( self::blank( Digitalisimo_Backups_Job_Store::new_id(), $spec, 'queued', 'import' ) );
			Digitalisimo_Backups_Job_Store::event( $job['id'], 'queued', array( 'kind' => 'import', 'source' => (string) ( $spec['source']['name'] ?? '' ), 'via' => $spec['origin']['via'] ?? '' ) );
			return array( 'busy' => false, 'duplicate' => false, 'job' => $job );
		} );
	}
	/** Crea una restauración sin comprobar la cola (la vuelta a la copia previa, encolada por la que falla, con el cerrojo tomado). */
	public static function create_restore( array $spec ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $spec ) {
			$job = Digitalisimo_Backups_Job_Store::create( self::blank( Digitalisimo_Backups_Job_Store::new_id(), $spec, 'queued', 'restore' ) );
			Digitalisimo_Backups_Job_Store::event( $job['id'], 'queued', array( 'kind' => 'restore', 'file' => (string) ( $spec['file'] ?? '' ), 'via' => $spec['origin']['via'] ?? '', 'rollback' => ! empty( $spec['rollback'] ) ) );
			return $job;
		} );
	}
	/** La restauración sin terminar, o null. Mientras exista, ningún respaldo empieza (la cola espera). */
	public static function restore_active() {
		foreach ( self::active() as $job ) if ( self::is_restore( $job ) ) return $job;
		return null;
	}
	public static function is_restore( $job ) { return is_array( $job ) && 'restore' === ( $job['kind'] ?? 'backup' ); }
	/** Fases del tipo de trabajo. */
	public static function phases( $job ) {
		if ( self::is_restore( $job ) ) return Digitalisimo_Backups_Restore_Job::PHASES;
		if ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) return Digitalisimo_Backups_Import_Job::PHASES;
		// Un tipo registrado (register_kind(): la migración) tiene sus propias fases.
		$class = is_array( $job ) ? self::kind_handler( (array) ( $job['spec'] ?? array() ) ) : null;
		return $class ? $class::job_phases() : self::PHASES;
	}
	public static function get( $id ) { return Digitalisimo_Backups_Job_Store::valid_id( $id ) ? Digitalisimo_Backups_Job_Store::read( $id ) : null; }
	public static function all() { return Digitalisimo_Backups_Job_Store::all(); }
	public static function active() { return array_values( array_filter( Digitalisimo_Backups_Job_Store::all(), function( $job ) { return ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); } ) ); }
	public static function has_active() { return (bool) self::active(); }

	/* ------------------------------------------------------------------ Ejecución */

	/** Presupuesto de un tick: la mitad de `max_execution_time` entre 5 y 20 s en la web; sin límite en CLI. */
	public static function budget() {
		if ( 'cli' === PHP_SAPI ) $budget = PHP_INT_MAX;
		else {
			$max = (int) ini_get( 'max_execution_time' );
			if ( $max <= 0 ) $max = 30;
			$budget = max( 5, min( 20, (int) floor( $max / 2 ) ) );
		}
		return (float) self::filter( 'digitalisimo_backups_job_budget', $budget );
	}
	/**
	 * Un tick: toma el cerrojo del almacén sin esperar (si está ocupado responde `busy`), reconoce los trabajos cuyo
	 * proceso murió, elige el primer trabajo listo y ejecuta pasos hasta agotar el presupuesto.
	 */
	public static function tick( $budget = null ) {
		if ( self::off_main_site() ) return array( 'result' => 'error', 'message' => self::MAIN_SITE_ONLY );
		try {
			$lock = Digitalisimo_Backups::try_operation_lock();
		} catch ( Throwable $e ) {
			return array( 'result' => 'error', 'message' => $e->getMessage() );
		}
		if ( ! $lock ) {
			// WP-Cron ya retiró el evento que disparó este tick: sin otro programado, un trabajo encolado mientras otra
			// operación (una restauración, un paso largo) tenía el cerrojo esperaría al watchdog horario.
			self::schedule( false, time() + 60 );
			return array( 'result' => 'busy' );
		}
		$result = 'idle';
		$finished = false;
		$guard = 0;
		try {
			self::recover();
			$start = microtime( true );
			$budget = null === $budget ? self::budget() : (float) $budget;
			// Nunca más allá del límite real de esta petición (T2): un tick que empieza tarde trabaja menos.
			$budget = min( $budget, Digitalisimo_Backups_Step_Budget::request_remaining() );
			do {
				$job = self::claim();
				if ( ! $job ) break;
				// WP-Cron retira el evento antes de llamar a este tick: si el proceso muere en el paso (límite del hosting,
				// un reinicio), sin otro programado el trabajo esperaría al watchdog horario. Con el arrendamiento, a 60 s.
				if ( 'worked' !== $result ) $guard = self::schedule_guard();
				$result = 'worked';
				$job = self::run( $job, $start, $budget );
				if ( ! $job || ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
				$finished = true;
			} while ( microtime( true ) - $start < $budget );
			// Subidas interrumpidas por un fallo o una cancelación: se retiran del destino fuera del cerrojo del registro.
			Digitalisimo_Backups::job_transfer_abandon_pending();
			// Copias remotas que la retención retiró (B6): con el cerrojo de operaciones de este tick, con esperas cortas.
			Digitalisimo_Backups::job_remote_retention_pending( 5, true );
			if ( $finished ) Digitalisimo_Backups_Job_Store::prune();
		} catch ( Throwable $e ) {
			$result = 'error';
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
		} finally {
			Digitalisimo_Backups::release_operation_lock( $lock );
		}
		// El tick de guardia ya no hace falta: el siguiente se programa a su hora (un reintento, a la suya).
		if ( $guard ) self::on_main_site( function() use ( $guard ) { if ( wp_next_scheduled( self::HOOK_TICK ) === $guard ) wp_unschedule_event( $guard, self::HOOK_TICK ); } );
		self::schedule();
		// Avisos de los trabajos que terminaron (aquí o en un proceso que murió después): fuera del cerrojo del almacén.
		Digitalisimo_Backups_Notify::flush_jobs();
		// Respaldo antes de actualizar: si el que se esperaba terminó, libera (o pospone) las actualizaciones retenidas.
		if ( class_exists( 'Digitalisimo_Backups_Pre_Update', false ) ) Digitalisimo_Backups_Pre_Update::after_tick();
		return array( 'result' => $result );
	}
	/** Elige el primer trabajo listo en orden de llegada y le pone un arrendamiento nuevo. */
	private static function claim() {
		return Digitalisimo_Backups_Job_Store::with_lock( function() {
			$now = time();
			$jobs = Digitalisimo_Backups_Job_Store::all();
			// Mientras una restauración no termina, ningún respaldo empieza: copiaría un sitio a medio restaurar.
			$restoring = false;
			foreach ( $jobs as $job ) if ( self::is_restore( $job ) && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) $restoring = true;
			foreach ( $jobs as $job ) {
				if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) || ! empty( $job['lease'] ) ) continue;
				if ( $restoring && ! self::is_restore( $job ) ) continue;
				// Un trabajo puede pedir más plazo (el respaldo antes de actualizar, en un hosting lento), nunca menos.
				$expiry = (int) self::filter( 'digitalisimo_backups_job_expiry', max( self::EXPIRES_AFTER, (int) ( $job['spec']['expires_after'] ?? 0 ) ) );
				if ( $now - (int) $job['created_at'] > $expiry ) {
					self::terminate( $job, 'failed', array( 'code' => 'expired', 'message' => 'El trabajo caducó sin terminar en ' . (int) round( $expiry / 3600 ) . ' horas. Vuelve a iniciarlo; si se repite, revisa el cron del sistema.', 'permanent' => true ) );
					continue;
				}
				if ( Digitalisimo_Backups_Job_Store::cancel_requested( $job['id'] ) && self::cancellable_phase( $job['phase'], $job ) ) {
					self::terminate( $job, 'cancelled' );
					continue;
				}
				if ( (int) $job['retry_at'] > $now ) continue;
				$job['lease'] = array( 'owner' => bin2hex( random_bytes( 16 ) ), 'host' => self::host(), 'pid' => (int) getmypid(), 'since' => $now, 'heartbeat_at' => $now );
				if ( ! $job['started_at'] ) $job['started_at'] = $now;
				$job['status'] = self::status_for( $job['phase'], $job['spec'] );
				$job['retry_at'] = 0;
				$job['last_seen_at'] = $now;
				$job = Digitalisimo_Backups_Job_Store::write( $job );
				Digitalisimo_Backups_Job_Store::event( $job['id'], 'claimed', array( 'phase' => $job['phase'], 'attempt' => self::attempt( $job ), 'pid' => $job['lease']['pid'] ) );
				return $job;
			}
			return null;
		} );
	}
	private static function run( array $job, $start, $budget ) {
		// `spec` (su tipo, sin secretos): el nombre y los datos de la fase son los de su tipo (K3), también al morir PHP.
		self::$current = array( 'kind' => self::is_restore( $job ) ? 'restore' : ( Digitalisimo_Backups_Import_Job::is_import( $job ) ? 'import' : 'backup' ), 'spec' => self::current_spec( $job ), 'id' => $job['id'], 'owner' => $job['lease']['owner'], 'phase' => $job['phase'], 'written' => microtime( true ), 'lost' => false, 'abort' => false, 'remote' => 'local' !== ( $job['spec']['destination'] ?? 'local' ), 'inline' => ! empty( $job['spec']['inline'] ), 'deadline' => $start + $budget );
		self::register_shutdown();
		@set_time_limit( 0 );
		if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
		try {
			while ( self::$current ) {
				if ( self::cancellable_phase( $job['phase'], $job ) && Digitalisimo_Backups_Job_Store::cancel_requested( $job['id'] ) ) throw new Digitalisimo_Backups_Job_Cancelled( 'Cancelado por el administrador.' );
				$job = self::step( $job );
				if ( ! self::$current || microtime( true ) - $start >= $budget ) break;
			}
		} catch ( Digitalisimo_Backups_Job_Cancelled $e ) {
			$job = self::cancel_owned( $job );
		} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
			self::$current = null;
			return null;
		} finally {
			if ( self::$current ) {
				try {
					$job = self::mutate_current( function( $job ) { $job['last_seen_at'] = time(); return $job; }, true );
				} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
					$job = null;
				}
			}
			self::$current = null;
		}
		return $job;
	}
	/** Ejecuta la fase actual y decide: avanzar, reintentar con espera, volver a una fase anterior o fallar. */
	private static function step( array $job ) {
		$phase = $job['phase'];
		self::$current['phase'] = $phase;
		try {
			if ( self::$phase_runner ) $outcome = call_user_func( self::$phase_runner, $phase, $job );
			elseif ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) $outcome = Digitalisimo_Backups_Import_Job::run_phase( $phase, $job );
			else $outcome = self::is_restore( $job ) ? Digitalisimo_Backups_Restore_Job::run_phase( $phase, $job ) : Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
		} catch ( Digitalisimo_Backups_Job_Cancelled $e ) {
			throw $e;
		} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
			throw $e;
		} catch ( Digitalisimo_Backups_Checkpoint_Invalid $e ) {
			return self::retry_owned( $job, $e->getMessage(), $e->phase );
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			return self::fail_owned( $job, 'permanent', $e->getMessage(), true );
		} catch ( Throwable $e ) {
			return self::retry_owned( $job, $e->getMessage(), $phase );
		}
		return self::advance( $job, $phase, is_array( $outcome ) ? $outcome : array() );
	}
	private static function advance( array $job, $phase, array $outcome ) {
		$next = $outcome['next'] ?? null;
		if ( null !== $next && ! in_array( $next, self::phases( $job ), true ) ) $next = null;
		$done = null === $next;
		$job = self::mutate_current( function( $job ) use ( $phase, $outcome, $next, $done ) {
			$job['checkpoint'][ $phase ] = isset( $outcome['checkpoint'] ) && is_array( $outcome['checkpoint'] ) ? $outcome['checkpoint'] : array( 'done' => time() );
			// Un tramo que guardó su avance (T2): los intentos cuentan fallos seguidos sin avanzar, no tramos.
			if ( $next === $phase ) unset( $job['attempts'][ $phase ] );
			// `progress_made`: una fase que se repite (una descarga por fragmentos) sólo cuenta fallos seguidos sin avanzar.
			if ( ! empty( $outcome['progress_made'] ) ) unset( $job['attempts'][ $phase ] );
			// `wait`: suelta el arrendamiento y espera sin gastar un intento (una migración que espera a su origen).
			if ( ! $done && ! empty( $outcome['wait'] ) ) $job['retry_at'] = time() + (int) $outcome['wait'];
			if ( ! empty( $outcome['result'] ) && is_array( $outcome['result'] ) ) $job['result'] = array_merge( (array) $job['result'], $outcome['result'] );
			foreach ( (array) ( $outcome['warnings'] ?? array() ) as $warning ) if ( count( $job['warnings'] ) < 200 ) $job['warnings'][] = $warning;
			$job['error'] = null;
			if ( $done ) {
				$job['status'] = 'success';
				$job['finished_at'] = time();
				$job['progress'] = array( 'percent' => 100, 'message' => (string) ( $outcome['message'] ?? ( self::is_restore( $job ) ? 'Restauración terminada.' : 'Respaldo completo creado y verificado.' ) ) );
				// El aviso por correo sale después, fuera del cerrojo (flush_jobs()); la marca sobrevive a una muerte del proceso.
				$job = Digitalisimo_Backups_Notify::mark( $job );
				// Una restauración que termina asienta las marcas de migración que esperaban su resultado (N3).
				if ( self::is_restore( $job ) && class_exists( 'Digitalisimo_Backups_Migrate', false ) ) Digitalisimo_Backups_Migrate::settle( $job );
			} else {
				$job['phase'] = $next;
				$job['status'] = self::status_for( $next, $job['spec'] );
				$job['progress'] = array( 'percent' => max( (int) ( $job['progress']['percent'] ?? 0 ), self::scale( self::phase_floor( $next, $job['spec'] ), $job['spec'], $next ) ), 'message' => (string) ( $outcome['message'] ?? self::phase_label( $next, $job ) . '…' ) );
			}
			return $job;
		}, $done || ! empty( $outcome['wait'] ), $done ? 'success' : 'phase', array( 'phase' => $phase, 'next' => $next ) );
		if ( $done ) {
			Digitalisimo_Backups_Job_Store::remove_staging( $job['id'] );
			Digitalisimo_Backups_Job_Store::clear_cancel( $job['id'] );
		}
		if ( ! $done && self::$current ) self::$current['phase'] = $next;
		return $job;
	}
	/** Un fallo transitorio cuenta un intento de la fase; con intentos libres espera y repite, si no, falla. */
	private static function retry_owned( array $job, $message, $back_to ) {
		$phase = $job['phase'];
		$count = (int) ( $job['attempts'][ $phase ] ?? 0 ) + 1;
		// Volver a una fase anterior (verify → pack) cuenta aparte, sin que los tramos que avanzan lo borren (T2).
		if ( in_array( $back_to, self::phases( $job ), true ) && $back_to !== $phase ) $count = max( $count, (int) ( $job['setbacks'][ $phase ] ?? 0 ) + 1 );
		$max = self::max_attempts( $phase, $job );
		if ( ! empty( $job['spec']['inline'] ) || $count >= $max ) return self::fail_owned( $job, 'attempts', $message . ( $count > 1 ? ' (se agotaron ' . $count . ' intentos de la fase ' . self::phase_label( $phase, $job ) . ')' : '' ), false );
		$wait = self::backoff( $count, $job );
		return self::mutate_current( function( $job ) use ( $phase, $count, $back_to, $wait, $message ) {
			self::discard( $job, in_array( $back_to, self::phases( $job ), true ) ? $back_to : $phase );
			return self::queue_retry( $job, $phase, $count, $back_to, $wait, 'retry', $message );
		}, true, 'retry', array( 'phase' => $phase, 'back_to' => $back_to, 'attempt' => $count, 'wait' => $wait, 'message' => $message ) );
	}
	private static function queue_retry( array $job, $phase, $count, $back_to, $wait, $code, $message ) {
		$job['attempts'][ $phase ] = $count;
		$phases = self::phases( $job );
		if ( in_array( $back_to, $phases, true ) && $back_to !== $phase ) {
			$job['setbacks'][ $phase ] = $count;
			$job['phase'] = $back_to;
			foreach ( array_slice( $phases, (int) array_search( $back_to, $phases, true ) ) as $later ) unset( $job['checkpoint'][ $later ] );
		}
		$job['status'] = 'queued';
		$job['progress']['percent'] = self::scale( self::phase_floor( $job['phase'], $job['spec'] ), $job['spec'], $job['phase'] );
		$job['retry_at'] = time() + $wait;
		$job['error'] = array( 'code' => $code, 'message' => (string) $message, 'phase' => $phase, 'permanent' => false );
		$job['progress']['message'] = 'Se reintentará (' . ( $count + 1 ) . ' de ' . self::max_attempts( $phase, $job ) . '): ' . $message;
		return $job;
	}
	private static function fail_owned( array $job, $code, $message, $permanent ) {
		return self::mutate_current( function( $job ) use ( $code, $message, $permanent ) {
			$message = self::on_terminal( $job, 'failed', $message );
			return self::terminal_state( $job, 'failed', array( 'code' => $code, 'message' => (string) $message, 'permanent' => (bool) $permanent ) );
		}, true, 'failed', array( 'code' => $code, 'message' => $message ) );
	}
	private static function cancel_owned( array $job ) {
		try {
			$job = self::mutate_current( function( $job ) {
				self::on_terminal( $job, 'cancelled', 'Cancelado por el administrador.' );
				return self::terminal_state( $job, 'cancelled' );
			}, true, 'cancelled' );
			Digitalisimo_Backups_Job_Store::clear_cancel( $job['id'] );
			return $job;
		} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
			self::$current = null;
			return null;
		}
	}
	/** Termina un trabajo que nadie ejecuta (en cola, caducado o con su proceso muerto). */
	private static function terminate( array $job, $status, $error = null ) {
		$message = self::on_terminal( $job, $status, $error['message'] ?? 'Cancelado por el administrador.' );
		if ( $error ) $error['message'] = $message;
		$job = Digitalisimo_Backups_Job_Store::write( self::terminal_state( $job, $status, $error ) );
		Digitalisimo_Backups_Job_Store::event( $job['id'], $status, $error ? array( 'code' => $error['code'], 'message' => $error['message'] ) : array() );
		if ( 'cancelled' === $status ) Digitalisimo_Backups_Job_Store::clear_cancel( $job['id'] );
		return $job;
	}
	private static function terminal_state( array $job, $status, $error = null ) {
		$job['status'] = $status;
		$job['lease'] = null;
		$job['retry_at'] = 0;
		$job['finished_at'] = time();
		if ( 'cancelled' === $status ) {
			$job['error'] = null;
			$job['progress']['message'] = 'Cancelado por el administrador. No se registró ningún respaldo parcial.';
			if ( self::is_restore( $job ) ) $job['progress']['message'] = 'Restauración cancelada antes de cambiar el sitio: la base de datos y los archivos siguen como estaban.';
			elseif ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) $job['progress']['message'] = 'Importación cancelada: no se dejó nada en el almacén.';
			elseif ( 'transfer' === $job['phase'] ) $job['progress']['message'] = 'Transferencia cancelada. El respaldo local verificado se conserva en el historial.';
		} else {
			$job['error'] = $error;
			$job['progress']['message'] = (string) ( $error['message'] ?? 'El trabajo falló.' );
		}
		// Una restauración que termina asienta las marcas de migración que esperaban su resultado (N3).
		if ( self::is_restore( $job ) && class_exists( 'Digitalisimo_Backups_Migrate', false ) ) Digitalisimo_Backups_Migrate::settle( $job );
		// Sólo un estado terminal avisa: los reintentos con espera (queue_retry) no pasan por aquí.
		return Digitalisimo_Backups_Notify::mark( $job );
	}

	/** Al terminar sin éxito: cada tipo deshace lo suyo; una restauración puede encolar la vuelta a la copia previa. Devuelve el mensaje. */
	private static function on_terminal( array $job, $status, $message ) {
		if ( self::is_restore( $job ) ) return Digitalisimo_Backups_Restore_Job::on_terminal( $job, $status, (string) $message );
		if ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) return Digitalisimo_Backups_Import_Job::on_terminal( $job, $status, (string) $message );
		Digitalisimo_Backups_Backup_Job::on_terminal( $job, $status, $message );
		return (string) $message;
	}
	private static function discard( array $job, $phase ) {
		if ( self::is_restore( $job ) ) Digitalisimo_Backups_Restore_Job::discard( $job, $phase );
		elseif ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) Digitalisimo_Backups_Import_Job::discard( $job, $phase );
		else Digitalisimo_Backups_Backup_Job::discard( $job, $phase );
	}

	/** Ejecuta todas las fases en este proceso (la copia previa de una restauración, con el cerrojo ya tomado). */
	public static function run_inline( array $spec ) {
		if ( self::$current ) throw new Exception( 'Ya hay un paso de respaldo en curso en este proceso.' );
		$now = time();
		$job = self::blank( Digitalisimo_Backups_Job_Store::new_id(), $spec, 'running' );
		$job['lease'] = array( 'owner' => bin2hex( random_bytes( 16 ) ), 'host' => self::host(), 'pid' => (int) getmypid(), 'since' => $now, 'heartbeat_at' => $now );
		$job['started_at'] = $now;
		$job['last_seen_at'] = $now;
		$job['progress']['message'] = 'Creando la copia previa…';
		$job = Digitalisimo_Backups_Job_Store::create( $job );
		Digitalisimo_Backups_Job_Store::event( $job['id'], 'inline', array( 'policy' => $spec['policy'] ) );
		self::$current = array( 'kind' => 'backup', 'spec' => self::current_spec( $job ), 'id' => $job['id'], 'owner' => $job['lease']['owner'], 'phase' => $job['phase'], 'written' => microtime( true ), 'lost' => false, 'abort' => false, 'remote' => false, 'inline' => true, 'deadline' => INF );
		self::register_shutdown();
		@set_time_limit( 0 );
		try {
			while ( self::$current ) $job = self::step( $job );
		} catch ( Throwable $e ) {
			if ( self::$current ) {
				try {
					self::fail_owned( $job, 'inline', $e->getMessage(), false );
				} catch ( Throwable $ignored ) {
					// El detector la dará por fallida.
				}
			}
			self::$current = null;
			throw $e;
		}
		if ( ! $job || 'success' !== $job['status'] ) throw new Exception( is_array( $job['error'] ?? null ) ? (string) $job['error']['message'] : 'No se pudo crear la copia previa.' );
		return rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . basename( (string) $spec['file'] );
	}

	/* ------------------------------------------------------------------ Latido y detector */

	/**
	 * Latido y avance del paso en curso. Escribe en el almacén (nunca en la base de datos, que puede estar dentro de
	 * la instantánea de la exportación) como mucho cada 2 s, y comprueba la cancelación en cada llamada.
	 */
	public static function progress( $percent, $message, $force = false ) {
		$current = self::$current;
		if ( ! $current ) return;
		if ( $current['lost'] ) throw new Digitalisimo_Backups_Job_Lease_Lost( 'Otro proceso tomó este trabajo; este paso se detiene sin modificarlo.' );
		if ( self::cancellable_phase( $current['phase'], $current ) && Digitalisimo_Backups_Job_Store::cancel_requested( $current['id'] ) ) throw new Digitalisimo_Backups_Job_Cancelled( 'Cancelado por el administrador.' );
		// La copia previa de una restauración usa las fases del respaldo: su avance ocupa su tramo de la barra.
		if ( 'restore' === ( $current['kind'] ?? '' ) ) $percent = Digitalisimo_Backups_Restore_Job::progress_percent( $current['phase'], (int) $percent );
		$now = microtime( true );
		if ( ! $force && $now - $current['written'] < self::filter( 'digitalisimo_backups_job_heartbeat', 2 ) ) return;
		self::$current['written'] = $now;
		self::mutate_current( function( $job ) use ( $percent, $message ) {
			$job['progress'] = array( 'percent' => min( 99, max( (int) ( $job['progress']['percent'] ?? 0 ), self::scale( (int) $percent, $job['spec'], $job['phase'] ) ) ), 'message' => (string) $message );
			$job['lease']['heartbeat_at'] = time();
			$job['last_seen_at'] = time();
			return $job;
		} );
	}
	/** Avance que llega desde libzip durante `close()`: nunca lanza dentro del callback, sólo pide abortar. */
	public static function zip_progress( $rate ) {
		try {
			self::progress( 78 + (int) floor( 2 * (float) $rate ), 'Escribiendo el ZIP en el área de trabajo…' );
		} catch ( Throwable $e ) {
			if ( self::$current ) self::$current['abort'] = true;
		}
	}
	public static function should_abort() {
		$current = self::$current;
		if ( ! $current ) return false;
		return ! empty( $current['abort'] ) || ! empty( $current['lost'] ) || ( self::cancellable_phase( $current['phase'], $current ) && Digitalisimo_Backups_Job_Store::cancel_requested( $current['id'] ) );
	}
	/** Tras abortar un `close()`, distingue cancelación y pérdida del trabajo de un fallo de escritura. */
	public static function throw_if_aborted() {
		$current = self::$current;
		if ( ! $current ) return;
		if ( ! empty( $current['lost'] ) ) throw new Digitalisimo_Backups_Job_Lease_Lost( 'Otro proceso tomó este trabajo.' );
		if ( self::cancellable_phase( $current['phase'], $current ) && Digitalisimo_Backups_Job_Store::cancel_requested( $current['id'] ) ) throw new Digitalisimo_Backups_Job_Cancelled( 'Cancelado por el administrador.' );
	}
	public static function current_id() { return self::$current ? self::$current['id'] : ''; }
	/** Fin del presupuesto del tick en curso (microtime); INF sin límite (WP-CLI, copia previa) o fuera de un paso. */
	public static function step_deadline() { return self::$current && isset( self::$current['deadline'] ) ? (float) self::$current['deadline'] : INF; }
	public static function current_owner_tag() { return self::$current ? substr( self::$current['owner'], 0, 8 ) : 'inline00'; }

	/** Escribe el trabajo del paso en curso sólo si este proceso sigue siendo su dueño (fencing). */
	private static function mutate_current( callable $mutator, $end = false, $event = '', array $data = array() ) {
		$current = self::$current;
		if ( ! $current ) throw new Digitalisimo_Backups_Job_Lease_Lost( 'No hay un paso en curso.' );
		$job = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $current, $mutator, $end, $event, $data ) {
			$job = Digitalisimo_Backups_Job_Store::read( $current['id'] );
			if ( ! $job || ( $job['lease']['owner'] ?? '' ) !== $current['owner'] ) {
				if ( self::$current ) self::$current['lost'] = true;
				throw new Digitalisimo_Backups_Job_Lease_Lost( 'Otro proceso tomó este trabajo; este paso se detiene sin modificarlo.' );
			}
			$job = $mutator( $job );
			if ( $end ) $job['lease'] = null;
			$job = Digitalisimo_Backups_Job_Store::write( $job );
			if ( $event ) Digitalisimo_Backups_Job_Store::event( $job['id'], $event, $data );
			return $job;
		} );
		if ( $end ) self::$current = null;
		return $job;
	}

	/**
	 * Detector de trabajos colgados. Sólo corre con el cerrojo del almacén tomado: entonces ningún proceso de este
	 * servidor ejecuta pasos, así que un arrendamiento de este servidor pertenece a un proceso muerto (el kernel libera
	 * `flock` al morir). Un arrendamiento de otro servidor sólo se toma tras 30 minutos sin latido.
	 */
	private static function recover() {
		$now = time();
		foreach ( Digitalisimo_Backups_Job_Store::all() as $job ) {
			if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) || empty( $job['lease'] ) ) continue;
			$lease = $job['lease'];
			if ( ( $lease['host'] ?? '' ) === self::host() ) {
				self::interrupted( $job, (string) $lease['owner'], 'interrupted', 'El proceso que ejecutaba «' . self::phase_label( $job['phase'], $job ) . '» se detuvo sin terminar el paso (reinicio, memoria agotada o límite del hosting).' );
			} elseif ( $now - (int) ( $lease['heartbeat_at'] ?? 0 ) > self::filter( 'digitalisimo_backups_job_takeover', self::TAKEOVER_AFTER ) ) {
				self::interrupted( $job, (string) $lease['owner'], 'takeover', 'El servidor ' . preg_replace( '/[^A-Za-z0-9._-]/', '', (string) ( $lease['host'] ?? '' ) ) . ' dejó de dar señales durante «' . self::phase_label( $job['phase'], $job ) . '»; este servidor retoma el trabajo.' );
			}
		}
	}
	/** Pasa por el detector sólo si nadie trabaja en este momento (para consultas de estado). */
	public static function recover_if_idle() {
		if ( self::off_main_site() ) return false;
		try {
			$lock = Digitalisimo_Backups::try_operation_lock();
		} catch ( Throwable $e ) {
			return false;
		}
		if ( ! $lock ) return false;
		try {
			self::recover();
		} catch ( Throwable $e ) {
			// La consulta de estado nunca falla por el detector.
		} finally {
			Digitalisimo_Backups::release_operation_lock( $lock );
		}
		// El detector pudo devolver a la cola un trabajo cuyo proceso murió: necesita su tick.
		self::schedule();
		return true;
	}
	/** Un paso interrumpido descarta lo que dejó a medias, cuenta un intento y espera para repetir desde su punto de control. */
	private static function interrupted( array $job, $owner, $code, $message ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $job, $owner, $code, $message ) {
			$job = Digitalisimo_Backups_Job_Store::read( $job['id'] );
			if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) || ( $job['lease']['owner'] ?? '' ) !== $owner ) return $job;
			$phase = $job['phase'];
			if ( Digitalisimo_Backups_Job_Store::cancel_requested( $job['id'] ) && self::cancellable_phase( $phase, $job ) ) return self::terminate( $job, 'cancelled' );
			$count = (int) ( $job['attempts'][ $phase ] ?? 0 ) + 1;
			// Un tipo registrado que avanzó antes de morir (los bytes ya en disco de una descarga `fetch`) no gasta un
			// intento: sólo cuentan las muertes seguidas sin avance, como los tramos que devuelven `progress_made`.
			$kind = self::kind_handler( (array) ( $job['spec'] ?? array() ) );
			$advanced = $kind && method_exists( $kind, 'advanced' ) && $kind::advanced( $job );
			if ( $advanced ) $count = 0;
			if ( ! empty( $job['spec']['inline'] ) || $count >= self::max_attempts( $phase, $job ) ) {
				$suffix = ! empty( $job['spec']['inline'] ) ? '' : ' Se agotaron los intentos de la fase. Si el hosting corta las peticiones largas, programa el cron del sistema para WP-Cron.';
				if ( self::kind_handler( $job['spec'] ) ) $suffix = (string) ( self::phase_info( $phase, $job )['interrupted'] ?? $suffix );
				return self::terminate( $job, 'failed', array( 'code' => $code, 'message' => $message . $suffix, 'permanent' => false ) );
			}
			self::discard( $job, $phase );
			$wait = $advanced ? (int) self::filter( 'digitalisimo_backups_job_backoff', 10, 0 ) : self::backoff( $count, $job );
			$job = self::queue_retry( $job, $phase, $count, $phase, $wait, $code, $message );
			$job['lease'] = null;
			$job = Digitalisimo_Backups_Job_Store::write( $job );
			Digitalisimo_Backups_Job_Store::event( $job['id'], $code, array( 'phase' => $phase, 'attempt' => $count, 'wait' => $wait ) );
			return $job;
		} );
	}
	private static function register_shutdown() {
		if ( self::$shutdown_registered ) return;
		self::$shutdown_registered = true;
		register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );
	}
	/** Ayuda, no garantía: si PHP termina a mitad de un paso (tiempo agotado o error fatal) lo anota en el almacén. */
	public static function on_shutdown() {
		$current = self::$current;
		if ( ! $current ) return;
		self::$current = null;
		$error = error_get_last();
		$fatal = $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true );
		$timeout = $fatal && false !== stripos( (string) $error['message'], 'maximum execution time' );
		$code = $timeout ? 'timeout' : ( $fatal ? 'fatal' : 'interrupted' );
		$message = $timeout ? 'PHP agotó su tiempo máximo durante «' . self::phase_label( $current['phase'], $current ) . '».' : ( $fatal ? 'PHP se detuvo durante «' . self::phase_label( $current['phase'], $current ) . '»: ' . $error['message'] : 'La petición terminó antes de cerrar «' . self::phase_label( $current['phase'], $current ) . '».' );
		try {
			$job = Digitalisimo_Backups_Job_Store::read( $current['id'] );
			if ( $job ) self::interrupted( $job, $current['owner'], $code, $message );
		} catch ( Throwable $e ) {
			// El detector lo reconocerá en el siguiente tick.
		}
	}

	/* ------------------------------------------------------------------ Cancelación */

	/** Cancela desde la interfaz, REST o WP-CLI. Un paso en curso se detiene en su siguiente punto de control. */
	public static function cancel( $id ) {
		if ( ! Digitalisimo_Backups_Job_Store::valid_id( $id ) ) return array( 'ok' => false, 'code' => 'invalid', 'message' => 'El identificador del trabajo no es válido.' );
		if ( self::off_main_site() ) return array( 'ok' => false, 'code' => 'site', 'message' => self::MAIN_SITE_ONLY );
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) {
			$job = Digitalisimo_Backups_Job_Store::read( $id );
			if ( ! $job ) return array( 'ok' => false, 'code' => 'missing', 'message' => 'El trabajo ya no existe.' );
			if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return array( 'ok' => false, 'code' => 'terminal', 'message' => 'El trabajo ya terminó.' );
			if ( ! empty( $job['spec']['inline'] ) ) return array( 'ok' => false, 'code' => 'inline', 'message' => 'La copia previa de una restauración no se puede cancelar.' );
			if ( self::is_restore( $job ) && ! self::cancellable_phase( $job['phase'], $job ) ) return array( 'ok' => false, 'code' => 'restoring', 'message' => ! empty( $job['spec']['rollback'] ) ? 'La vuelta a la copia previa no se puede cancelar: deja el sitio como estaba antes de restaurar.' : 'La restauración ya está cambiando el sitio; no se puede cancelar a mitad. Termina sola o, si falla, vuelve a la copia previa.' );
			// Un tipo registrado (la migración) explica con su propio texto por qué esa fase no se cancela (H7).
			if ( ! self::cancellable_phase( $job['phase'], $job ) ) return array( 'ok' => false, 'code' => 'verified', 'message' => (string) ( ( self::kind_handler( (array) $job['spec'] ) ? self::phase_info( (string) $job['phase'], $job )['refusal'] ?? '' : '' ) ?: 'El respaldo ya está verificado y registrado; si no lo quieres, elimínalo desde el historial cuando termine.' ) );
			Digitalisimo_Backups_Job_Store::request_cancel( $id );
			$job['cancel_requested_at'] = time();
			if ( empty( $job['lease'] ) ) {
				$job = self::terminate( $job, 'cancelled' );
				return array( 'ok' => true, 'code' => 'cancelled', 'message' => 'Trabajo cancelado.', 'job' => $job );
			}
			$job = Digitalisimo_Backups_Job_Store::write( $job );
			Digitalisimo_Backups_Job_Store::event( $id, 'cancel_requested' );
			return array( 'ok' => true, 'code' => 'requested', 'message' => 'Cancelación pedida: el paso en curso se detendrá en su siguiente punto de control.', 'job' => $job );
		} );
	}
	/** Un respaldo se cancela hasta registrarse; una restauración, hasta que empieza a cambiar el sitio (nunca la vuelta a la copia previa). */
	public static function cancellable_phase( $phase, $job = null ) {
		if ( is_array( $job ) && ( 'restore' === ( $job['kind'] ?? '' ) ) ) return empty( $job['spec']['rollback'] ) && Digitalisimo_Backups_Restore_Job::cancellable( $phase );
		if ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) return Digitalisimo_Backups_Import_Job::cancellable( $phase );
		$info = self::phase_info( $phase, is_array( $job ) ? $job : null );
		if ( $info ) return (bool) $info['cancellable'];
		return ! in_array( $phase, array( 'publish', 'prune' ), true );
	}

	/* ------------------------------------------------------------------ Disparadores */

	/**
	 * Mientras quede trabajo hay un tick único programado; con `spawn` se lanza ya el cron si toca ahora. `$not_before`
	 * evita que un tick que encontró el cerrojo ocupado se reprograme para ya mismo en cada carga de página.
	 */
	public static function schedule( $spawn = false, $not_before = 0 ) {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) return;
		try {
			$next = null;
			foreach ( self::active() as $job ) {
				$at = ! empty( $job['lease'] ) ? time() + 60 : max( time(), (int) $job['retry_at'] );
				$next = null === $next ? $at : min( $next, $at );
			}
			// Una subida cancelada o fallida que espera retirarse del destino también necesita su tick.
			if ( Digitalisimo_Backups::job_transfer_pending_abandons() ) $next = null === $next ? time() : min( $next, time() );
			// Avisos por correo que una petición con WordPress mínimo no envió: los manda un tick con WordPress entero.
			if ( Digitalisimo_Backups_Notify::deferred() && Digitalisimo_Backups_Notify::pending() ) $next = null === $next ? time() + 60 : min( $next, time() + 60 );
		} catch ( Throwable $e ) {
			return;
		}
		if ( null !== $next ) $next = max( $next, (int) $not_before );
		self::on_main_site( function() use ( $next, $spawn ) {
			$scheduled = wp_next_scheduled( self::HOOK_TICK );
			if ( null === $next ) return;
			if ( false === $scheduled || $scheduled > $next ) {
				if ( false !== $scheduled ) wp_unschedule_event( $scheduled, self::HOOK_TICK );
				wp_schedule_single_event( $next, self::HOOK_TICK );
			}
			if ( $spawn && $next <= time() && function_exists( 'spawn_cron' ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) spawn_cron();
		} );
	}
	/** Programa el tick de guardia de un trabajo recién reclamado; devuelve su hora si lo añadió este tick (si no, 0). */
	private static function schedule_guard() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_unschedule_event' ) ) return 0;
		$next = function() { return wp_next_scheduled( self::HOOK_TICK ); };
		$before = self::on_main_site( $next );
		self::schedule();
		$after = self::on_main_site( $next );
		return false !== $after && $after !== $before ? (int) $after : 0;
	}
	public static function cron_tick() { self::tick(); }
	public static function ensure_watchdog() {
		if ( ( function_exists( 'wp_installing' ) && wp_installing() ) || ( is_multisite() && ! is_main_site() ) ) return;
		if ( ! wp_next_scheduled( self::HOOK_WATCHDOG ) ) wp_schedule_event( time() + 3600, 'hourly', self::HOOK_WATCHDOG );
	}
	/** Cada hora: trabajos colgados, tick perdido (p. ej. tras restaurar la base de datos), huérfanos y poda. */
	public static function watchdog() {
		if ( self::off_main_site() ) return;
		try {
			$lock = Digitalisimo_Backups::try_operation_lock();
		} catch ( Throwable $e ) {
			return;
		}
		if ( $lock ) {
			try {
				self::recover();
				Digitalisimo_Backups::job_transfer_abandon_pending();
				Digitalisimo_Backups::job_remote_retention_pending( 5, true );
				if ( ! self::has_active() ) self::cleanup_orphans();
				Digitalisimo_Backups_Job_Store::prune();
				// Subidas de respaldos sin actividad durante 24 h.
				Digitalisimo_Backups_Uploads::sessions();
				// Tablas apartadas por una migración o su «Deshacer» que ya toca borrar (K1): plazo cumplido con su copia previa
				// en el almacén, o «Deshacer» confirmado. Sólo las del registro; nunca otra tabla.
				if ( class_exists( 'Digitalisimo_Backups_Migrate', false ) ) Digitalisimo_Backups_Migrate::kept_cleanup( false );
			} catch ( Throwable $e ) {
				if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() );
			} finally {
				Digitalisimo_Backups::release_operation_lock( $lock );
			}
			Digitalisimo_Backups_Notify::flush_jobs();
		}
		self::schedule( true );
	}
	/**
	 * Restos de versiones anteriores tras una muerte: el SQL temporal fuera del almacén y el temporal de libzip junto
	 * al ZIP. Sólo los de más de 24 h y sin trabajos activos; nunca un `*.zip` con nombre final.
	 */
	public static function cleanup_orphans( $older_than = 86400 ) {
		$now = time();
		$removed = 0;
		$candidates = array();
		foreach ( (array) @scandir( sys_get_temp_dir() ) as $name ) if ( preg_match( '/^digitalisimo-sql-[A-Za-z0-9]{6}$/', $name ) ) $candidates[] = rtrim( sys_get_temp_dir(), '/\\' ) . '/' . $name;
		$storage = Digitalisimo_Backups::storage_directory();
		// También los temporales de los destinos remotos: verificación, descarga, «Probar conexión», la clave SFTP en archivo
		// (que se borra al momento) y la copia a medias de «Traer al almacén».
		foreach ( (array) @scandir( $storage ) as $name ) if ( preg_match( '/^(?:digitalisimo-[A-Za-z0-9._-]+\.zip\.[A-Za-z0-9]{6}|\.(?:cloud-check|verify|download|connection-test|connection-back|sftp-pub|sftp-key)-[A-Za-z0-9]{6}|digitalisimo-[A-Za-z0-9._-]+\.(?:zip|dgbk)\.part-[0-9a-f]{12})$/', $name ) ) $candidates[] = $storage . '/' . $name;
		foreach ( $candidates as $path ) {
			if ( is_link( $path ) || ! is_file( $path ) || $now - (int) @filemtime( $path ) <= $older_than ) continue;
			if ( @unlink( $path ) ) $removed++;
		}
		return $removed;
	}

	/* ------------------------------------------------------------------ Interfaz */

	public static function ajax_tick() {
		self::ajax_guard();
		$result = self::tick();
		wp_send_json_success( array( 'result' => $result['result'], 'message' => $result['message'] ?? '', 'jobs' => self::views() ) );
	}
	/**
	 * Un paso de la cola mientras hay una restauración, autorizado por su testigo (la cookie del navegador que la empezó
	 * o el parámetro): tras el cambio de tablas la sesión puede ya no valer (las sesiones y las contraseñas de aplicación
	 * son las del respaldo) y el sitio está en mantenimiento. Sólo hace avanzar la cola y devuelve la vista pública.
	 */
	public static function ajax_restore_tick() {
		if ( self::off_main_site() ) wp_send_json_error( array( 'message' => self::MAIN_SITE_ONLY ), 400 );
		$job = self::restore_active();
		if ( ! $job || ! Digitalisimo_Backups_Restore_Maintenance::request_has( (string) ( $job['spec']['token'] ?? '' ) ) ) {
			// La terminó otro proceso (WP-Cron, otra pestaña, WP-CLI): quien la sigue recibe el estado final, no un error que la
			// pantalla reintentaría sin fin (H18 del ensayo E2). Sólo esa restauración, nunca otros trabajos. Con la sesión de
			// quien gestiona Backups, la vista completa; sin ella (la sesión puede no valer tras restaurar), sólo la pestaña
			// que la siguió (el testigo en su cookie, nunca en la URL como el de WP-Cron) y sólo durante FINISHED_ANSWER
			// segundos tras terminar, con el estado y un texto general, sin el error ni el archivo (revisión C4).
			$manager = Digitalisimo_Backups::can_manage_backups();
			$done = self::restore_finished_for_request( ! $manager );
			if ( $done && ( $manager || time() - (int) ( $done['finished_at'] ?? 0 ) <= self::FINISHED_ANSWER ) ) {
				$view = $manager ? self::view( $done ) : self::finished_view( $done );
				wp_send_json_success( array( 'result' => 'finished', 'message' => (string) $view['detail'], 'jobs' => array( $view ) ) );
			}
			wp_send_json_error( array( 'message' => 'No hay una restauración en curso con este testigo.' ), 403 );
		}
		$result = self::tick();
		wp_send_json_success( array( 'result' => $result['result'], 'message' => $result['message'] ?? '', 'jobs' => self::views() ) );
	}
	/** Segundos tras terminar en los que la pestaña que siguió una restauración, sin sesión, recibe su estado final (C4). */
	const FINISHED_ANSWER = 900;
	/** Vista mínima de una restauración terminada para quien no tiene sesión: estado y un texto general, sin detalle. */
	private static function finished_view( array $job ) {
		$status = (string) $job['status'];
		$texts = array( 'success' => 'Restauración terminada. Entra al escritorio para ver el resultado.', 'failed' => 'La restauración no terminó bien. Entra al escritorio para ver el detalle y la copia previa.', 'cancelled' => 'La restauración se canceló. Entra al escritorio para ver el detalle.' );
		return array( 'detail' => $texts[ $status ] ?? 'La restauración terminó. Entra al escritorio para ver el detalle.', 'id' => (string) $job['id'], 'status' => $status, 'status_label' => self::status_label( $status ), 'phase' => '', 'phase_label' => '', 'percent' => 'success' === $status ? 100 : 0, 'message' => '', 'terminal' => true, 'running' => false, 'retry_in' => 0, 'can_cancel' => false, 'kind' => 'restore', 'origin_label' => 'Restauración', 'destination_label' => '', 'created_label' => '' );
	}
	/**
	 * La restauración terminada más reciente cuyo testigo trae esta petición, o null. Con $cookie_only, sólo el de la
	 * cookie de la pestaña que la siguió (el parámetro lo lleva también la URL del tick de WP-Cron).
	 */
	private static function restore_finished_for_request( $cookie_only = false ) {
		try {
			$jobs = Digitalisimo_Backups_Job_Store::all();
		} catch ( Throwable $e ) {
			return null;
		}
		$found = null;
		foreach ( $jobs as $job ) {
			if ( ! self::is_restore( $job ) || ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) continue;
			$token = (string) ( $job['spec']['token'] ?? '' );
			if ( $cookie_only ? ( '' === $token || ! is_string( $_COOKIE[ Digitalisimo_Backups_Restore_Maintenance::COOKIE ] ?? null ) || ! hash_equals( hash( 'sha256', $token ), hash( 'sha256', (string) $_COOKIE[ Digitalisimo_Backups_Restore_Maintenance::COOKIE ] ) ) ) : ! Digitalisimo_Backups_Restore_Maintenance::request_has( $token ) ) continue;
			if ( ! $found || (int) ( $job['finished_at'] ?? 0 ) > (int) ( $found['finished_at'] ?? 0 ) ) $found = $job;
		}
		return $found;
	}
	/** El tick de WP-Cron lleva el testigo mientras una restauración tiene el sitio en mantenimiento (si no, WordPress lo corta). */
	public static function cron_request( $request ) {
		if ( ! is_array( $request ) || empty( $request['url'] ) || ! Digitalisimo_Backups_Restore_Maintenance::ours() ) return $request;
		try {
			$job = self::restore_active();
		} catch ( Throwable $e ) {
			return $request;
		}
		if ( $job && '' !== (string) ( $job['spec']['token'] ?? '' ) ) $request['url'] = add_query_arg( Digitalisimo_Backups_Restore_Maintenance::PARAM, (string) $job['spec']['token'], $request['url'] );
		return $request;
	}
	public static function ajax_status() {
		self::ajax_guard();
		self::recover_if_idle();
		wp_send_json_success( array( 'jobs' => self::views() ) );
	}
	public static function ajax_cancel() {
		self::ajax_guard();
		$result = self::cancel( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) );
		self::recover_if_idle();
		if ( empty( $result['ok'] ) ) wp_send_json_error( array( 'message' => $result['message'], 'jobs' => self::views() ), 409 );
		wp_send_json_success( array( 'message' => $result['message'], 'jobs' => self::views() ) );
	}
	public static function admin_cancel() {
		if ( ! Digitalisimo_Backups::can_manage_backups() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_CANCEL );
		$result = self::cancel( sanitize_text_field( wp_unslash( $_POST['job'] ?? '' ) ) );
		self::recover_if_idle();
		Digitalisimo_Backups::redirect_result( ! empty( $result['ok'] ), $result['message'] );
	}
	private static function ajax_guard() {
		if ( ! Digitalisimo_Backups::can_manage_backups() ) wp_send_json_error( array( 'message' => 'No autorizado para gestionar respaldos.' ), 403 );
		check_ajax_referer( self::NONCE, 'nonce' );
		$context = sanitize_key( wp_unslash( $_POST['context'] ?? 'site' ) );
		if ( ! in_array( $context, array( 'site', 'network' ), true ) || ( 'network' === $context && ! is_multisite() ) ) wp_send_json_error( array( 'message' => 'Contexto de administración inválido.' ), 400 );
		if ( self::off_main_site() ) wp_send_json_error( array( 'message' => self::MAIN_SITE_ONLY ), 400 );
	}

	/** Vista pública: lo único que llega al navegador. Sin rutas, arrendamiento, sesiones remotas ni usuarios. */
	public static function view( array $job ) {
		$now = time();
		$status = (string) $job['status'];
		$phase = (string) ( $job['phase'] ?? '' );
		$terminal = Digitalisimo_Backups_Job_Store::is_terminal( $job );
		$lease = $job['lease'] ?? null;
		$spec = (array) ( $job['spec'] ?? array() );
		$restore = self::is_restore( $job );
		$import = Digitalisimo_Backups_Import_Job::is_import( $job );
		$max = self::max_attempts( $phase, $job );
		$retry_at = 'queued' === $status ? (int) $job['retry_at'] : 0;
		$heartbeat = $lease ? (int) ( $lease['heartbeat_at'] ?? 0 ) : 0;
		$error = is_array( $job['error'] ?? null ) ? (string) ( $job['error']['message'] ?? '' ) : '';
		$message = (string) ( $job['progress']['message'] ?? '' );
		// Avisos que cambian lo que es el respaldo (T2): se muestran con su texto, no sólo se cuentan (el último de cada
		// clase: una fase repetida tras volver atrás lo da otra vez).
		$notices = array();
		foreach ( (array) ( $job['warnings'] ?? array() ) as $warning ) if ( is_array( $warning ) && ! empty( $warning['notice'] ) ) $notices[ (string) $warning['notice'] ] = (string) ( $warning['text'] ?? '' );
		$notices = array_values( $notices );
		$detail = array();
		if ( 'success' === $status && ( $restore || $import ) ) $detail[] = (string) ( $job['progress']['message'] ?? 'Restauración terminada.' );
		elseif ( 'success' === $status ) $detail[] = ( '.dgbk' === substr( (string) ( $spec['file'] ?? '' ), -5 ) ? 'Respaldo cifrado y verificado: ' : 'ZIP verificado: ' ) . basename( (string) ( $spec['file'] ?? '' ) ) . ( $notices ? ' · Con avisos: ' . implode( ' ', $notices ) : '' );
		elseif ( 'failed' === $status ) $detail[] = $error ?: $message;
		elseif ( 'cancelled' === $status ) $detail[] = $message;
		else {
			$detail[] = $message;
			if ( $retry_at > $now ) $detail[] = 'Reintento a las ' . self::clock( $retry_at ) . ' (' . min( $max, (int) ( $job['attempts'][ $phase ] ?? 0 ) + 1 ) . ' de ' . $max . ')';
			if ( $lease && $now - $heartbeat > self::STALE_AFTER ) $detail[] = 'Sin señal desde las ' . self::clock( $heartbeat ) . '; el proceso sigue vivo según el servidor';
			elseif ( ! $lease && 'queued' !== $status && ! empty( $job['last_seen_at'] ) ) $detail[] = 'Esperando el siguiente paso desde las ' . self::clock( (int) $job['last_seen_at'] );
			if ( ! empty( $job['cancel_requested_at'] ) ) $detail[] = 'Cancelación pedida';
		}
		if ( ! empty( $job['warnings'] ) ) $detail[] = count( (array) $job['warnings'] ) . ' aviso(s) en el registro del trabajo';
		$view = array(
			'detail' => implode( ' · ', array_filter( $detail, 'strlen' ) ),
			'id' => (string) $job['id'],
			'status' => $status,
			'status_label' => 'success' === $status && $notices ? 'Completo con avisos' : self::status_label( $status ),
			'phase' => $phase,
			'phase_label' => $terminal ? '' : self::phase_label( $phase, $job ),
			'percent' => 'success' === $status ? 100 : max( 0, min( 99, (int) ( $job['progress']['percent'] ?? 0 ) ) ),
			'message' => (string) ( $job['progress']['message'] ?? '' ),
			'attempt' => min( $max, (int) ( $job['attempts'][ $phase ] ?? 0 ) + 1 ),
			'max_attempts' => $max,
			'retry_in' => $retry_at > $now ? $retry_at - $now : 0,
			'retry_label' => $retry_at > $now ? self::clock( $retry_at ) : '',
			'last_seen_ago' => ! empty( $job['last_seen_at'] ) ? max( 0, $now - (int) $job['last_seen_at'] ) : null,
			'last_seen_label' => ! empty( $job['last_seen_at'] ) ? self::clock( (int) $job['last_seen_at'] ) : '',
			'running' => (bool) $lease,
			'stalled' => $lease && $now - $heartbeat > self::STALE_AFTER,
			'waiting' => ! $terminal && ! $lease && 'queued' !== $status,
			'cancel_requested' => ! empty( $job['cancel_requested_at'] ),
			'can_cancel' => ! $terminal && empty( $spec['inline'] ) && self::cancellable_phase( $phase, $job ) && empty( $job['cancel_requested_at'] ),
			'terminal' => $terminal,
			'file' => 'success' === $status ? basename( (string) ( $import ? ( $job['result']['import']['file'] ?? '' ) : ( $spec['file'] ?? '' ) ) ) : '',
			'error' => 'success' === $status ? '' : $error,
			'origin_label' => $restore ? ( ! empty( $spec['rollback'] ) ? 'Vuelta a la copia previa' : 'Restauración' ) : ( $import ? 'Importación de Backuply' : self::origin_label( $spec ) ),
			'destination_label' => $restore ? basename( (string) ( $spec['file'] ?? '' ) ) : ( $import ? basename( (string) ( $spec['source']['name'] ?? '' ) ) : Digitalisimo_Backups::destination_name( (string) ( $spec['destination'] ?? 'local' ) ) ),
			'kind' => $restore ? 'restore' : ( $import ? 'import' : 'backup' ),
			'created_label' => ! empty( $job['created_at'] ) ? self::clock( (int) $job['created_at'], true ) : '',
			'warnings' => count( (array) ( $job['warnings'] ?? array() ) ),
			'notices' => $notices,
		);
		foreach ( self::$kinds as $class ) $view = $class::job_view( $job, $view );
		return $view;
	}
	/** Trabajos sin terminar (en orden de llegada) y los últimos terminados. */
	public static function views( $limit = 8 ) {
		try {
			$jobs = Digitalisimo_Backups_Job_Store::all();
		} catch ( Throwable $e ) {
			return array();
		}
		$active = array();
		$done = array();
		foreach ( $jobs as $job ) {
			if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) $done[] = $job;
			else $active[] = $job;
		}
		usort( $done, function( $a, $b ) { return (int) ( $b['finished_at'] ?? 0 ) <=> (int) ( $a['finished_at'] ?? 0 ); } );
		$views = array();
		foreach ( array_slice( array_merge( $active, $done ), 0, max( count( $active ), (int) $limit ) ) as $job ) $views[] = self::view( $job );
		return $views;
	}

	/* ------------------------------------------------------------------ Utilidades */

	public static function status_for( $phase, array $spec ) {
		if ( 'restore' === ( $spec['type'] ?? '' ) ) return 'inspect' === $phase ? 'verifying' : 'running';
		if ( 'import' === ( $spec['type'] ?? '' ) ) return in_array( $phase, array( 'scan', 'finish' ), true ) ? 'verifying' : 'running';
		$info = self::phase_info( $phase, $spec ? $spec : null );
		if ( $info ) return (string) $info['status'];
		if ( in_array( $phase, array( 'verify', 'publish' ), true ) ) return 'verifying';
		if ( 'transfer' === $phase ) return 'transferring';
		if ( 'prune' === $phase ) return 'local' === ( $spec['destination'] ?? 'local' ) ? 'verifying' : 'transferring';
		return 'running';
	}
	public static function status_label( $status ) {
		$labels = array( 'queued' => 'Pendiente', 'running' => 'En curso', 'verifying' => 'Verificando', 'transferring' => 'Transfiriendo', 'success' => 'Completo', 'failed' => 'Fallido', 'cancelled' => 'Cancelado' );
		return $labels[ $status ] ?? $status;
	}
	/** Nombre de una fase. Con el trabajo, el de su tipo (respaldo, restauración, importación o un tipo registrado); sin él, por nombre. */
	public static function phase_label( $phase, $job = null ) {
		$backup = array( 'preflight' => 'Comprobaciones', 'database' => 'Base de datos', 'pack' => 'Archivos y ZIP', 'verify' => 'Verificación', 'publish' => 'Registro', 'transfer' => 'Transferencia', 'prune' => 'Retención' );
		if ( is_array( $job ) ) {
			if ( self::is_restore( $job ) ) return Digitalisimo_Backups_Restore_Job::LABELS[ $phase ] ?? ( $backup[ $phase ] ?? (string) $phase );
			// `files` es «Archivos» en una restauración y «ZIP convertido» en una importación.
			if ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) return Digitalisimo_Backups_Import_Job::LABELS[ $phase ] ?? ( $backup[ $phase ] ?? (string) $phase );
			$info = self::phase_info( $phase, $job );
			if ( $info ) return (string) $info['label'];
			return $backup[ $phase ] ?? (string) $phase;
		}
		$info = self::phase_info( $phase );
		if ( $info ) return (string) $info['label'];
		$labels = $backup + Digitalisimo_Backups_Restore_Job::LABELS;
		return $labels[ $phase ] ?? (string) $phase;
	}
	private static function phase_floor( $phase, array $spec = array() ) {
		if ( 'restore' === ( $spec['type'] ?? '' ) ) return Digitalisimo_Backups_Restore_Job::FLOORS[ $phase ] ?? 0;
		if ( 'import' === ( $spec['type'] ?? '' ) ) return Digitalisimo_Backups_Import_Job::FLOORS[ $phase ] ?? 0;
		$info = self::phase_info( $phase, $spec ? $spec : null );
		if ( $info ) return (int) $info['floor'];
		$floors = array( 'preflight' => 0, 'database' => 3, 'pack' => 40, 'verify' => 81, 'publish' => 98, 'transfer' => 99, 'prune' => 99 );
		return $floors[ $phase ] ?? 0;
	}
	/** Con destino remoto, lo local ocupa el 90 % de la barra y la transferencia el resto. */
	private static function scale( $percent, array $spec, $phase ) {
		$percent = max( 0, min( 99, (int) $percent ) );
		if ( 'local' === ( $spec['destination'] ?? 'local' ) ) return $percent;
		if ( 'transfer' === $phase ) return 90 + (int) floor( max( 0, $percent - 90 ) * 0.9 );
		if ( 'prune' === $phase ) return 99;
		return (int) floor( $percent * 0.9 );
	}
	/**
	 * Intentos seguidos sin avanzar de una fase. Una restauración tiene 5 por fase (cada tramo guardado vuelve a contar
	 * desde cero): una muerte se reanuda desde el último punto de control, no desde el principio.
	 */
	public static function max_attempts( $phase, $job = null ) {
		// En este proceso (WP-CLI, la envoltura sincrónica) no hay reintentos: un fallo tras el cambio vuelve enseguida a la copia previa.
		if ( self::is_restore( $job ) ) $max = ! empty( $job['spec']['sync'] ) ? 1 : 5;
		elseif ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) $max = 3;
		else {
			$info = self::phase_info( $phase, is_array( $job ) ? $job : null );
			if ( $info ) return max( 1, (int) self::filter( 'digitalisimo_backups_job_max_attempts', (int) $info['max'], $phase ) );
			$max = 'database' === $phase ? 2 : ( 'transfer' === $phase ? 5 : 3 );
		}
		return max( 1, (int) self::filter( 'digitalisimo_backups_job_max_attempts', $max, $phase ) );
	}
	private static function attempt( array $job ) { return (int) ( $job['attempts'][ $job['phase'] ] ?? 0 ) + 1; }
	/** Espera de 1, 5 y 15 minutos con ±20 % para que dos sitios no reintenten a la vez; 10 s, 1 y 5 min en una restauración. */
	public static function backoff( $count, $job = null ) {
		$steps = self::is_restore( $job ) || Digitalisimo_Backups_Import_Job::is_import( $job ) ? self::RESTORE_BACKOFF : self::BACKOFF;
		$base = $steps[ max( 0, min( count( $steps ) - 1, (int) $count - 1 ) ) ];
		$wait = (int) round( $base * ( 1 + random_int( -20, 20 ) / 100 ) );
		return max( 0, (int) self::filter( 'digitalisimo_backups_job_backoff', $wait, (int) $count ) );
	}
	public static function host() {
		$host = function_exists( 'gethostname' ) ? gethostname() : false;
		return (string) ( $host ?: php_uname( 'n' ) );
	}
	private static function clock( $timestamp, $date = false ) {
		$format = $date ? 'Y-m-d H:i' : 'H:i';
		return function_exists( 'wp_date' ) ? (string) wp_date( $format, $timestamp ) : gmdate( $format, $timestamp );
	}
	private static function filter( $hook, $value, ...$args ) { return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value, ...$args ) : $value; }
	/**
	 * En una red, la cola es de toda la instalación y su historial vive en el sitio principal: un paso, el detector o una
	 * cancelación que llegan desde un subsitio no corren (registrarían el respaldo o su copia remota en ese subsitio).
	 */
	public static function off_main_site() { return is_multisite() && ! is_main_site(); }
	private static function on_main_site( callable $callback ) {
		$switch = is_multisite() && function_exists( 'get_main_site_id' ) && (int) get_current_blog_id() !== (int) get_main_site_id();
		if ( $switch ) switch_to_blog( get_main_site_id() );
		try {
			return $callback();
		} finally {
			if ( $switch ) restore_current_blog();
		}
	}
	/**
	 * Punto de extensión: de dónde viene un trabajo, en la tarjeta «Trabajos», REST y WP-CLI. Un tipo de trabajo propio
	 * (p. ej. «Extraer como WordPress individual») da la suya con el filtro `digitalisimo_backups_job_origin`.
	 */
	public static function origin_label( array $spec ) {
		$label = (string) self::filter( 'digitalisimo_backups_job_origin', '', $spec );
		if ( '' !== $label ) return $label;
		return ! empty( $spec['inline'] ) ? 'Copia previa a una restauración' : ( 'manual' === ( $spec['policy'] ?? '' ) ? 'Respaldo manual' : 'Automatización' );
	}
}
