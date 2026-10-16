<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * «Migrar» en un clic: la migración completa de un sitio como una tarea por etapas, reanudable y con cerrojo. Avanza
 * en peticiones AJAX mientras la pantalla está abierta y, si se cierra, con el WP-Cron del sitio;
 * `wp digitalisimo-migration --run` la ejecuta entera. Etapas, en orden:
 *
 *  1. prepare    — qué plugins sustituidos están instalados y dónde están activos; fija el corte de la reparación de
 *                  reglas para que las copias que crea esta migración nunca cuenten como dañadas.
 *  2. deactivate — desactiva en este sitio Element Pack, Elementor Pro, PRO Elements y Elementor (éste sólo si el
 *                  núcleo incluido puede sustituirlo). Si desactivó algo, la petición termina ahí, y cualquier proceso
 *                  que hubiera arrancado antes (con esos plugins cargados) se pausa sin trabajar.
 *  3. recover    — páginas sobrescritas por conversiones antiguas (Migración ≤ 1.0.13), revisión a revisión.
 *  4. repair     — reglas de visibilidad perdidas por conversiones antiguas (Migración ≤ 1.0.10), una sola vez.
 *  5. convert    — documentos con contenido de Element Pack, al formato propio, con copia del original.
 *  6. revisions  — revisiones y autoguardados con contenido de Element Pack, cada uno en su propio registro: restaurar
 *                  una revisión ya no devuelve widgets que dejaron de existir. Se dejan las que delatan una página
 *                  sobrescrita que no pudo recuperarse, para que siga listada hasta que alguien la revise.
 *  7. references — qué alias de widgets fusionados no pueden convertirse porque otro CSS del sitio nombra sus clases.
 *  8. aliases    — los demás alias, del alias oculto al widget que lo absorbió (Aliases).
 *  9. finish     — cachés de Elementor vaciadas e informe.
 *
 * Cada documento y cada etapa sin documentos se anotan antes de empezar con su número de intento: si una petición
 * muere en ellos dos veces, se saltan y se listan. El cerrojo es atómico, propio de cada petición y se renueva con cada
 * documento. La continuación queda programada antes de trabajar. Lo que no puede migrarse solo (widgets sin
 * equivalente, CSS que nombra clases de un alias) se lista con su lugar. La tarea nunca borra plugins.
 *
 * Sólo toca el sitio en curso. En una red, cada petición carga los plugins del sitio que la atiende, así que la
 * migración de red (network_start) no convierte otros sitios desde la petición de red: desactiva lo activado en toda
 * la red (y antes, en cada sitio, Elementor Pro y PRO Elements, sin los que DIGITALÍSIMO Elements no carga los widgets
 * de los adaptadores), encola la tarea de cada sitio con la autorización de quien la lanzó, y la pantalla de red
 * conduce cada sitio con peticiones a la administración de ese sitio; un sitio que el navegador no alcanza (dominio
 * propio) la completa con su WP-Cron o al abrir su herramienta.
 */
final class Runner {
	const JOB         = 'digitalisimo_migration_job';
	const LOCK        = 'digitalisimo_migration_lock';
	const REPORT      = 'digitalisimo_migration_report';
	const CRON        = 'digitalisimo_migration_continue';
	const NETWORK_JOB = 'digitalisimo_migration_network_job';
	const AJAX        = 'digitalisimo_migration_step';
	const STAGES      = array( 'prepare', 'deactivate', 'recover', 'repair', 'convert', 'revisions', 'references', 'aliases', 'finish' );
	/** Etapas que recorren documentos con loop(): sus intentos se cuentan por documento. */
	const LOOP_STAGES = array( 'recover', 'convert', 'revisions', 'aliases' );
	const LABELS      = array(
		'prepare'    => 'Revisando el sitio',
		'deactivate' => 'Desactivando los plugins sustituidos',
		'recover'    => 'Recuperando páginas sobrescritas',
		'repair'     => 'Recuperando reglas de visibilidad',
		'convert'    => 'Convirtiendo el contenido de Element Pack',
		'revisions'  => 'Convirtiendo revisiones y autoguardados',
		'references' => 'Buscando CSS que nombre widgets fusionados',
		'aliases'    => 'Actualizando widgets fusionados',
		'finish'     => 'Vaciando cachés y preparando el informe',
	);
	/** Segundos de trabajo por petición (filtro digitalisimo_migration_step_seconds). */
	const BUDGET = 20;
	/** Un cerrojo sin renovar durante este tiempo es de una petición que murió (se renueva con cada documento). */
	const STALE = 600;
	/** Intentos por documento o por etapa antes de saltarlos, y pausas seguidas que se esperan a un proceso viejo. */
	const MAX_ATTEMPTS = 2;
	const MAX_PAUSES   = 3;
	/** Espera del WP-Cron que continúa una tarea abandonada, y documentos por consulta en la etapa de alias. */
	const CRON_DELAY = 120;
	const BATCH      = 200;

	/** @var string token del cerrojo de esta petición, por sitio. */
	private static $tokens = array();

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax' ) );
		add_action( self::CRON, array( __CLASS__, 'cron' ) );
		add_filter( 'allowed_http_origins', array( __CLASS__, 'allowed_origins' ) );
	}

	/** Carga el motor de conversión sólo cuando hace falta (AJAX, cron, CLI y las pantallas). */
	public static function load() {
		require_once __DIR__ . '/class-migration.php';
		require_once __DIR__ . '/class-retire.php';
		require_once __DIR__ . '/class-aliases.php';
	}

	/**
	 * En una red con subdominios, la pantalla de red conduce cada sitio con peticiones a su admin-ajax.php: sólo para
	 * esta acción, el origen de la administración de red puede leer la respuesta (la petición sigue exigiendo la
	 * sesión y el nonce de quien la lanzó).
	 */
	public static function allowed_origins( $origins ) {
		if ( ! is_multisite() || ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() || self::AJAX !== ( $_REQUEST['action'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide la cabecera de origen; el handler verifica el nonce.
			return $origins;
		}
		$parts = wp_parse_url( network_admin_url() );
		if ( ! empty( $parts['host'] ) ) {
			$origins   = (array) $origins;
			$origins[] = ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		}
		return array_values( array_unique( (array) $origins ) );
	}

	/* ------------------------------------------------------------------ Entradas */

	/** Un paso desde la pantalla: la tarea del sitio, o con `network` el arranque y el resumen de la red. */
	public static function ajax() {
		$network = ! empty( $_POST['network'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- el nonce se comprueba a continuación según el ámbito.
		if ( $network ) {
			if ( ! is_multisite() || ! is_super_admin() ) {
				wp_send_json_error( 'No autorizado.', 403 );
			}
			check_ajax_referer( self::AJAX . '_network' );
		} else {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'No autorizado.', 403 );
			}
			check_ajax_referer( self::AJAX . '_' . get_current_blog_id() );
		}
		self::load();
		if ( $network ) {
			$job = ! empty( $_POST['start'] ) ? self::network_start() : self::network_summary();
			wp_send_json_success( array(
				'done'  => ! empty( $job['done'] ),
				'idle'  => ! empty( $job['idle'] ),
				'sites' => self::network_pending_sites( $job ),
				'html'  => self::render_network( $job ),
			) );
		}
		// El clic genera un identificador: si se perdió la respuesta de una pasada que sí terminó, el reintento no lanza otra.
		$run   = isset( $_POST['run'] ) ? sanitize_key( wp_unslash( $_POST['run'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
		$state = self::site_step( ! empty( $_POST['start'] ), null, null, $run );
		// Sin tarea en marcha (otra pestaña, el cron o la red la terminaron), se muestra el informe guardado.
		$html = empty( $state['stage'] ) || ! empty( $state['idle'] ) ? self::render( self::report() ) : self::render( $state );
		wp_send_json_success( array(
			'done'  => ! empty( $state['done'] ) || ( empty( $state['stage'] ) && ! empty( $state['idle'] ) && self::report() ),
			'idle'  => ! empty( $state['idle'] ),
			'busy'  => ! empty( $state['busy'] ),
			'pause' => ! empty( $state['pause'] ),
			'stage' => (string) ( $state['stage'] ?? '' ),
			'html'  => $html,
		) );
	}

	/** Continuación en segundo plano de la tarea del sitio en curso, con el mismo límite de tiempo que una petición. */
	public static function cron() {
		self::load();
		$state = self::state();
		if ( $state && empty( $state['done'] ) ) {
			self::site_step( false, min( 50, self::budget() ) );
		}
	}

	/* ------------------------------------------------------------------ Tarea del sitio */

	/** Estado de la tarea del sitio en curso (null si no hay ninguna). */
	public static function state() {
		$state = get_option( self::JOB, null );
		return is_array( $state ) ? $state : null;
	}

	/** Último informe terminado del sitio en curso (null si nunca terminó una). */
	public static function report() {
		$report = get_option( self::REPORT, null );
		return is_array( $report ) ? $report : null;
	}

	/**
	 * Avanza la tarea del sitio hasta agotar el presupuesto de segundos, pausarse o terminar. Con $start crea una tarea
	 * nueva si no hay ninguna en marcha. Devuelve el estado; `busy` si otra petición la tiene tomada e `idle` si no hay
	 * tarea.
	 */
	public static function site_step( $start = false, $budget = null, $capable = null, $run = '' ) {
		$budget = null === $budget ? self::budget() : (float) $budget;
		if ( ! self::lock() ) {
			$state = self::state();
			// La petición que la tiene puede morir sin programar nada: la continuación queda programada igualmente.
			if ( $state && empty( $state['done'] ) ) {
				self::schedule();
			}
			return array( 'busy' => true ) + (array) $state;
		}
		try {
			self::raise_memory();
			$state = self::state();
			$last  = self::report();
			if ( $start && ( ! $state || ! empty( $state['done'] ) ) && ( '' === (string) $run || (string) $run !== (string) ( $last['run'] ?? '' ) ) ) {
				$state        = self::fresh( $capable );
				$state['run'] = (string) $run;
				self::save( $state );
			}
			if ( ! $state ) {
				return array( 'idle' => true );
			}
			// La continuación se programa antes de trabajar: si esta petición muere, el WP-Cron la retoma.
			self::schedule();
			// Un proceso que arrancó antes de que la tarea desactivara plugins aún los tiene cargados: no trabaja.
			if ( ! empty( $state['deactivated_at'] ) && self::request_started() < (float) $state['deactivated_at'] ) {
				$state['pause'] = true;
				return $state;
			}
			$state = self::advance( $state, $budget );
		} finally {
			self::unlock();
		}
		if ( ! empty( $state['done'] ) && function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		return $state;
	}

	/** Ejecuta la tarea del sitio en este proceso (CLI), en pasos cortos, hasta terminar o pausarse tras desactivar plugins. */
	public static function run( $start = true, $capable = true ) {
		$state = self::site_step( $start, 60, $capable );
		while ( empty( $state['done'] ) && empty( $state['pause'] ) && empty( $state['busy'] ) && empty( $state['idle'] ) ) {
			$state = self::site_step( false, 60, $capable );
		}
		return $state;
	}

	/** Tarea nueva. $capable: si puede desactivar plugins (por defecto, si quien la lanza puede). */
	public static function fresh( $capable = null, $network = 0 ) {
		return array(
			'version'        => defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '',
			'started'        => time(),
			'updated'        => time(),
			'network'        => (int) $network,
			'stage'          => 'prepare',
			'done'           => false,
			'pause'          => false,
			'pauses'         => 0,
			'deactivated_at' => 0,
			'cursor'         => 0,
			'processed'      => 0,
			'total'          => 0,
			'current'        => 0,
			'attempts'       => 0,
			'stage_attempts' => 0,
			'capable'        => null === $capable ? ( function_exists( 'current_user_can' ) && current_user_can( 'activate_plugins' ) ) : (bool) $capable,
			'counts'         => array( 'deactivated' => array(), 'recovered' => 0, 'repaired' => 0, 'converted' => 0, 'elements' => 0, 'revisions' => 0, 'aliases' => 0, 'alias_elements' => 0, 'failed' => array() ),
			'warnings'       => array(),
			'review'         => array(),
			'keep_revisions' => array(),
			'plugins'        => array(),
			'blocked'        => array(),
			'report'         => array(),
		);
	}

	/** Guarda la tarea y renueva el cerrojo: mientras una petición avanza, nadie la da por muerta. */
	private static function save( array $state ) {
		if ( empty( $state['done'] ) ) {
			unset( $state['busy'], $state['idle'] );
			update_option( self::JOB, $state, false );
		}
		self::touch();
	}

	private static function advance( array $state, $budget ) {
		$deadline       = microtime( true ) + max( 1, (float) $budget );
		$state['pause'] = false;
		while ( empty( $state['done'] ) && empty( $state['pause'] ) && microtime( true ) < $deadline ) {
			$stage = in_array( $state['stage'] ?? '', self::STAGES, true ) ? $state['stage'] : 'prepare';
			if ( ! in_array( $stage, self::LOOP_STAGES, true ) ) {
				// Una etapa sin documentos que tumba la petición dos veces se salta (finish cierra con un informe mínimo).
				$state['stage_attempts'] = (int) ( $state['stage_attempts'] ?? 0 ) + 1;
				if ( $state['stage_attempts'] > self::MAX_ATTEMPTS ) {
					$state = self::warn( $state, sprintf( 'La etapa «%s» no pudo completarse (la petición se cortó dos veces) y se saltó.', self::LABELS[ $stage ] ) );
					$state = 'finish' === $stage ? self::close( $state, array() ) : self::next( $state, self::STAGES[ array_search( $stage, self::STAGES, true ) + 1 ] );
					self::save( $state );
					continue;
				}
				self::save( $state );
			}
			$state            = call_user_func( array( __CLASS__, 'stage_' . $stage ), $state, $deadline );
			$state['updated'] = time();
			self::save( $state );
		}
		return $state;
	}

	private static function next( array $state, $stage, $total = 0 ) {
		$state['stage']          = $stage;
		$state['cursor']         = 0;
		$state['processed']      = 0;
		$state['total']          = (int) $total;
		$state['current']        = 0;
		$state['attempts']       = 0;
		$state['stage_attempts'] = 0;
		return $state;
	}

	private static function warn( array $state, $message ) {
		if ( ! in_array( $message, (array) $state['warnings'], true ) ) {
			$state['warnings'][] = $message;
		}
		return $state;
	}

	private static function request_started() {
		return isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
	}

	private static function stage_prepare( array $state, $deadline ) {
		$state['plugins'] = array();
		foreach ( Retire::status() as $file => $info ) {
			$state['plugins'][ $file ] = array( 'label' => $info['label'], 'site' => $info['site'], 'network' => $info['network'], 'core' => $info['core'] );
		}
		// Sólo los documentos convertidos antes de esta migración pueden haber perdido sus reglas de visibilidad.
		if ( ! (int) get_option( Migration::REPAIR_CUTOFF, 0 ) ) {
			update_option( Migration::REPAIR_CUTOFF, (int) $state['started'], false );
		}
		return self::next( $state, 'deactivate' );
	}

	private static function stage_deactivate( array $state, $deadline ) {
		$site_active = array();
		foreach ( $state['plugins'] as $info ) {
			if ( ! empty( $info['site'] ) ) {
				$site_active[] = $info['label'];
			}
			if ( ! empty( $info['network'] ) ) {
				$state = self::warn( $state, sprintf( '%s está activo en toda la red: lo desactiva la migración de la red (Red → Ajustes → Migrar a DIGITALÍSIMO Elements).', $info['label'] ) );
			}
		}
		if ( $site_active ) {
			if ( ! empty( $state['capable'] ) || self::network_authorizes() ) {
				$result                         = Retire::deactivate( false );
				$state['counts']['deactivated'] = array_values( array_unique( array_merge( (array) $state['counts']['deactivated'], array_values( $result['deactivated'] ) ) ) );
				foreach ( $result['warnings'] as $warning ) {
					$state = self::warn( $state, $warning );
				}
				if ( $result['deactivated'] ) {
					// La siguiente petición ya corre sin estos plugins; ésta y las que arrancaron antes no pueden convertir.
					$state['pause']          = true;
					$state['deactivated_at'] = microtime( true );
				}
			} else {
				$state = self::warn( $state, sprintf( 'Quien lanzó la migración no puede desactivar plugins, así que siguen activos: %s.', implode( ', ', $site_active ) ) );
			}
		}
		return self::next( $state, 'recover', count( Migration::recover_candidates() ) );
	}

	/**
	 * ¿Puede convertirse en esta petición? Exige los widgets propios cargados y, para Element Pack, que no lo esté.
	 * Devuelve 'pause' si el proceso tiene cargado algo que las opciones ya no tienen activo (arrancó antes de una
	 * desactivación): la siguiente petición sí podrá.
	 */
	private static function can_convert( array &$state, $element_pack = true ) {
		$stale = false;
		$ok    = true;
		if ( $element_pack && Translator::element_pack_active() ) {
			$listed = false;
			foreach ( Retire::status() as $info ) {
				$listed = $listed || ( $info['element_pack'] && ( $info['site'] || $info['network'] ) );
			}
			$stale = $stale || ! $listed;
			if ( $listed ) {
				$state = self::warn( $state, 'Element Pack sigue activo (en toda la red, o sin permiso para desactivarlo aquí): su contenido no se convirtió y se sigue viendo con Element Pack. Cuando esté desactivado, vuelve a pulsar Migrar.' );
			}
			$ok = false;
		}
		if ( $ok && function_exists( 'digitalisimo_elements_conflicting_plugin_active' ) && digitalisimo_elements_conflicting_plugin_active() ) {
			$state = self::warn( $state, 'Elementor Pro o PRO Elements sigue activo y DIGITALÍSIMO Elements no carga sus widgets mientras tanto: la conversión se dejó para cuando esté desactivado. Vuelve a pulsar Migrar entonces.' );
			$ok    = false;
		}
		if ( $ok && ! class_exists( '\\Digitalisimo\\Elements\\Widget_Registry' ) ) {
			$stale = true;
			$ok    = false;
		}
		if ( $ok ) {
			return true;
		}
		if ( $stale && (int) $state['pauses'] < self::MAX_PAUSES ) {
			++$state['pauses'];
			return 'pause';
		}
		if ( $stale ) {
			$state = self::warn( $state, 'Esta petición no tenía cargados los widgets de DIGITALÍSIMO Elements, o aún tenía cargado Element Pack: la conversión se dejó para después. Vuelve a pulsar Migrar.' );
		}
		return false;
	}

	/** ¿Hay una migración de red en marcha, lanzada por quien puede gestionar plugins, que incluye este sitio? */
	private static function network_authorizes() {
		if ( ! is_multisite() ) {
			return false;
		}
		$job = self::network_state();
		return $job && empty( $job['done'] ) && ! empty( $job['capable'] ) && in_array( self::blog(), array_map( 'intval', (array) $job['sites'] ), true );
	}

	/** Etapa en pausa: se repite en la siguiente petición. */
	private static function wait( array $state ) {
		$state['pause'] = true;
		return $state;
	}

	private static function stage_recover( array $state, $deadline ) {
		$can = self::can_convert( $state );
		if ( 'pause' === $can ) {
			return self::wait( $state );
		}
		if ( true !== $can ) {
			return self::next( $state, 'repair' );
		}
		list( $state, $complete ) = self::loop( $state, Migration::recover_candidates(), $deadline, static function ( $revision_id, array &$state ) {
			$info = Migration::overwritten_by( $revision_id );
			if ( ! $info ) {
				return;
			}
			if ( '' !== Migration::recover_from_revision( $info['parent'], $info['revision'] ) ) {
				++$state['counts']['recovered'];
				$state['review'] = array_values( array_diff( $state['review'], array( (int) $info['parent'] ) ) );
				return;
			}
			// Sin revisión ni copia de la que recuperarla: se lista, y la revisión que la delata no se convierte.
			if ( ! in_array( (int) $info['parent'], $state['review'], true ) ) {
				$state['review'][] = (int) $info['parent'];
			}
			$state['keep_revisions'][] = (int) $revision_id;
		} );
		return $complete ? self::next( $state, 'repair' ) : $state;
	}

	/** La recuperación de reglas perdidas se hace una sola vez por sitio: si ya terminó, no se repite. */
	private static function stage_repair( array $state, $deadline ) {
		if ( '1' !== (string) get_option( Migration::REPAIRED, '' ) ) {
			do {
				$step                         = Migration::repair_step( Migration::REPAIR_BATCH );
				$state['counts']['repaired'] += (int) $step['documents'];
				self::save( $state );
			} while ( empty( $step['done'] ) && microtime( true ) < $deadline );
			if ( empty( $step['done'] ) ) {
				// Continúa desde su cursor en la siguiente petición; no cuenta como intento fallido.
				$state['stage_attempts'] = 0;
				return $state;
			}
		}
		return self::next( $state, 'convert', count( Migration::documents() ) );
	}

	private static function stage_convert( array $state, $deadline ) {
		$can = self::can_convert( $state );
		if ( 'pause' === $can ) {
			return self::wait( $state );
		}
		if ( true !== $can ) {
			// Sin convertir Element Pack tampoco se tocan los alias: una página sobrescrita por una conversión antigua
			// dejaría de reconocerse, y se hará todo junto al volver a pulsar Migrar.
			return self::next( $state, 'finish' );
		}
		list( $state, $complete ) = self::loop( $state, Migration::documents(), $deadline, static function ( $post_id, array &$state ) {
			$result = Migration::convert_document( $post_id );
			if ( ! empty( $result['native'] ) ) {
				++$state['counts']['converted'];
				$state['counts']['elements'] += (int) $result['native'];
			}
		} );
		return $complete ? self::next( $state, 'revisions', count( self::revisions_to_convert( $state ) ) ) : $state;
	}

	private static function revisions_to_convert( array $state ) {
		return array_values( array_diff( Migration::legacy_revisions(), (array) $state['keep_revisions'] ) );
	}

	private static function stage_revisions( array $state, $deadline ) {
		list( $state, $complete ) = self::loop( $state, self::revisions_to_convert( $state ), $deadline, static function ( $post_id, array &$state ) {
			$result = Migration::convert_document( $post_id );
			if ( ! empty( $result['native'] ) ) {
				++$state['counts']['revisions'];
			}
		} );
		return $complete ? self::next( $state, 'references' ) : $state;
	}

	/** Qué alias no pueden convertirse en este sitio (CSS que nombra sus clases) y cuántos documentos hay. */
	private static function stage_references( array $state, $deadline ) {
		if ( Aliases::unreadable() ) {
			$state = self::warn( $state, 'No se pudieron leer los widgets fusionados de DIGITALÍSIMO Elements en esta petición: los alias no se actualizaron. Vuelve a pulsar Migrar.' );
			return self::next( $state, 'finish' );
		}
		$total            = count( Aliases::documents() );
		$state['blocked'] = $total ? Aliases::references() : array();
		return self::next( $state, 'aliases', $total );
	}

	private static function stage_aliases( array $state, $deadline ) {
		$skip  = array_keys( (array) $state['blocked'] );
		$batch = Aliases::documents( null, (int) $state['cursor'], self::BATCH );
		list( $state, $complete ) = self::loop( $state, $batch, $deadline, static function ( $post_id, array &$state ) use ( $skip ) {
			$changes = Aliases::convert_document( $post_id, $skip );
			if ( $changes ) {
				++$state['counts']['aliases'];
				$state['counts']['alias_elements'] += (int) $changes;
			}
		} );
		return $complete && count( $batch ) < self::BATCH ? self::next( $state, 'finish' ) : $state;
	}

	private static function stage_finish( array $state, $deadline ) {
		try {
			if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		} catch ( \Throwable $e ) {
			$state = self::warn( $state, 'No se pudo vaciar la caché de Elementor (' . $e->getMessage() . '): hazlo en Elementor → Herramientas → Regenerar archivos.' );
		}
		try {
			$report = self::build_report( $state );
		} catch ( \Throwable $e ) {
			$state  = self::warn( $state, 'El informe quedó incompleto: ' . $e->getMessage() );
			$report = array();
		}
		return self::close( $state, $report );
	}

	/** Da la tarea por terminada con el informe indicado. */
	private static function close( array $state, array $report ) {
		$state['report']   = $report;
		$state['done']     = true;
		$state['pause']    = false;
		$state['finished'] = time();
		update_option( self::REPORT, $state, false );
		delete_option( self::JOB );
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		return $state;
	}

	/**
	 * Recorre los documentos a partir del cursor dentro del plazo. Antes de tocar un documento lo anota como «en curso»
	 * con su número de intento: si la petición cae, la siguiente lo reintenta y, pasado MAX_ATTEMPTS, lo salta y lo
	 * apunta en `failed`.
	 *
	 * @return array{0:array,1:bool} estado y si la lista se recorrió entera.
	 */
	private static function loop( array $state, array $ids, $deadline, callable $work ) {
		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= (int) $state['cursor'] ) {
				continue;
			}
			if ( (int) $state['current'] !== $post_id ) {
				$state['current']  = $post_id;
				$state['attempts'] = 0;
			}
			++$state['attempts'];
			if ( $state['attempts'] > self::MAX_ATTEMPTS ) {
				if ( ! in_array( $post_id, $state['counts']['failed'], true ) ) {
					$state['counts']['failed'][] = $post_id;
				}
			} else {
				self::save( $state );
				$work( $post_id, $state );
			}
			$state['cursor']   = $post_id;
			$state['current']  = 0;
			$state['attempts'] = 0;
			++$state['processed'];
			self::save( $state );
			if ( microtime( true ) >= $deadline ) {
				return array( $state, false );
			}
		}
		return array( $state, true );
	}

	/** Lo que queda para una persona, con el estado final de los plugins. */
	private static function build_report( array $state ) {
		$groups      = Migration::classify();
		$unsupported = array();
		foreach ( Migration::inventory() as $type => $row ) {
			if ( ! $row['map'] ) {
				$unsupported[ $type ] = array( 'elements' => (int) $row['elements'], 'documents' => (int) $row['documents'], 'reason' => Migration::reason( $type ) );
			}
		}
		$figures = Migration::figures();
		$failed  = (array) $state['counts']['failed'];
		$plugins = array();
		$others  = is_multisite() ? Retire::network_summary() : array();
		foreach ( Retire::status() as $file => $info ) {
			$plugins[ $file ] = array(
				'label'        => $info['label'],
				'site'         => $info['site'],
				'network'      => $info['network'],
				'element_pack' => $info['element_pack'],
				// En una red, cuántos sitios lo tienen activo (este incluido).
				'sites'        => (int) ( $others[ $file ]['sites'] ?? 0 ),
			);
		}
		$blocked = array_keys( (array) $state['blocked'] );
		$map     = Aliases::map();
		return array(
			'convertible'     => count( $groups['convertible'] ),
			'blocked'         => self::documents_info( $groups['blocked'] ),
			'unsupported'     => $unsupported,
			'missing_figures' => (array) $figures['missing'],
			'review'          => self::documents_info( (array) $state['review'] ),
			'failed'          => self::documents_info( $failed ),
			'aliases_left'    => $map ? count( Aliases::documents( array_values( array_diff( array_keys( $map ), $blocked ) ) ) ) : count( Aliases::documents_with( Aliases::KNOWN ) ),
			'alias_blocked'   => (array) $state['blocked'],
			'alias_targets'   => array_map( static function ( $spec ) {
				return array( 'target' => $spec['target'], 'prefix' => $spec['prefix'], 'target_prefix' => $spec['target_prefix'] );
			}, array_intersect_key( $map, (array) $state['blocked'] ) ),
			// Element Pack aún hace falta (sus ajustes y figuras) mientras quede contenido suyo por convertir.
			'ep_needed'       => count( $groups['convertible'] ) > 0 || (bool) array_intersect( $failed, Migration::documents() ),
			'plugins'         => $plugins,
		);
	}

	/**
	 * Título y enlace de cada documento del informe. Una revisión se presenta como tal, con su fecha, y enlaza a la
	 * pantalla de revisiones de WordPress, no al editor (guardar desde allí escribiría en la revisión).
	 *
	 * @return array<int, array{title:string, link:string}>
	 */
	private static function documents_info( array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$parent = function_exists( 'wp_is_post_revision' ) ? (int) wp_is_post_revision( $id ) : 0;
			$title  = function_exists( 'get_the_title' ) ? (string) get_the_title( $parent ? $parent : $id ) : '';
			$title  = '' !== $title ? $title : '#' . ( $parent ? $parent : $id );
			if ( $parent ) {
				$post       = get_post( $id );
				$out[ $id ] = array( 'title' => sprintf( 'Revisión de «%s»%s', $title, $post ? ' del ' . self::date( strtotime( (string) $post->post_modified_gmt . ' UTC' ) ) : '' ), 'link' => admin_url( 'revision.php?revision=' . $id ) );
			} else {
				$out[ $id ] = array( 'title' => $title, 'link' => admin_url( 'post.php?post=' . $id . '&action=elementor' ) );
			}
		}
		return $out;
	}

	/** ¿Quedó algo para una persona en el informe de una tarea terminada? */
	public static function has_pending( array $state ) {
		$r = (array) ( $state['report'] ?? array() );
		foreach ( array( 'blocked', 'unsupported', 'review', 'failed', 'alias_blocked', 'missing_figures' ) as $key ) {
			if ( ! empty( $r[ $key ] ) ) {
				return true;
			}
		}
		foreach ( (array) ( $r['plugins'] ?? array() ) as $plugin ) {
			if ( ! empty( $plugin['site'] ) || ! empty( $plugin['network'] ) ) {
				return true;
			}
		}
		return ! empty( $r['convertible'] ) || ! empty( $r['aliases_left'] ) || ! empty( $state['warnings'] );
	}

	/* ------------------------------------------------------------------ Red */

	/** Estado de la migración de red (null si nunca se lanzó). */
	public static function network_state() {
		$job = get_site_option( self::NETWORK_JOB, null );
		return is_array( $job ) ? $job : null;
	}

	/** ¿Está DIGITALÍSIMO Elements activo en el sitio indicado (por sitio o en toda la red)? */
	public static function elements_active_on( $site_id ) {
		$file = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) : 'digitalisimo-elements/pro-elements.php';
		if ( isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $file ] ) ) {
			return true;
		}
		return in_array( $file, (array) get_blog_option( (int) $site_id, 'active_plugins', array() ), true );
	}

	/**
	 * Arranca la migración de red (o devuelve la que está en marcha). Si DIGITALÍSIMO Elements está activado en toda la
	 * red (si no, algún sitio se quedaría sin constructor): en cada sitio con él desactiva Elementor Pro y PRO Elements
	 * (sin ellos activos, sus adaptadores pintan Element Pack desde ya) y después desactiva lo activado en toda la red.
	 * Encola en cada sitio con DIGITALÍSIMO Elements su tarea, autorizada a desactivar plugins por quien lanzó la de red.
	 * La conversión corre después en peticiones de cada sitio. Sólo los sitios de esta red.
	 */
	public static function network_start( $capable = null ) {
		$job = self::network_state();
		if ( $job && empty( $job['done'] ) ) {
			// Una migración de red en marcha se continúa; si ya no le queda ningún sitio en marcha (la tarea de alguno se
			// perdió), se lanza de nuevo.
			$summary = self::network_summary();
			foreach ( (array) ( $summary['status'] ?? array() ) as $row ) {
				if ( 'running' === $row['status'] ) {
					return $summary;
				}
			}
			if ( ! empty( $summary['done'] ) ) {
				return $summary;
			}
		}
		$capable          = null === $capable ? ( function_exists( 'current_user_can' ) && current_user_can( 'manage_network_plugins' ) ) : (bool) $capable;
		$job              = array(
			'version'     => defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '',
			'started'     => time(),
			'done'        => false,
			'sites'       => array(),
			'skipped'     => array(),
			'deactivated' => array(),
			'warnings'    => array(),
			'capable'     => $capable,
		);
		$file             = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) : '';
		$elements_network = '' !== $file && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $file ] );
		$retire_network   = $elements_network && $capable;
		if ( ! $elements_network ) {
			$job['warnings'][] = 'DIGITALÍSIMO Elements no está activado en toda la red: los plugins activados en toda la red no se desactivaron, porque los sitios sin DIGITALÍSIMO Elements se quedarían sin ellos. Actívalo en toda la red y vuelve a pulsar Migrar toda la red.';
		} elseif ( ! $capable ) {
			$job['warnings'][] = 'Quien lanzó la migración no puede gestionar los plugins de la red: los activados en toda la red siguen activos.';
		}
		$conflicts = array();
		foreach ( Retire::sites() as $site_id ) {
			if ( ! self::elements_active_on( $site_id ) ) {
				$job['skipped'][] = $site_id;
				continue;
			}
			$job['sites'][] = $site_id;
			switch_to_blog( $site_id );
			try {
				$conflicts[ $site_id ] = $retire_network ? Retire::deactivate_conflicts() : array();
			} finally {
				restore_current_blog();
			}
		}
		if ( $retire_network ) {
			$result             = Retire::deactivate( true );
			$job['deactivated'] = array_values( $result['deactivated'] );
			$job['warnings']    = array_merge( $job['warnings'], $result['warnings'] );
		}
		$now = microtime( true );
		foreach ( $job['sites'] as $site_id ) {
			switch_to_blog( $site_id );
			try {
				// Bajo el cerrojo del sitio, para no pisar a una petición que esté avanzando su tarea. Si está ocupado, esa
				// tarea sigue y la autorización de la red le llega por network_authorizes().
				if ( self::lock() ) {
					try {
						$state = self::state();
						if ( ! $state || ! empty( $state['done'] ) ) {
							$state = self::fresh( $capable, $job['started'] );
						} elseif ( $capable && empty( $state['capable'] ) ) {
							// Una tarea en marcha sin permiso para desactivar recibe la autorización de la red y, si ya pasó
							// esa etapa con plugins sustituidos aún activos, vuelve a empezar (las etapas son repetibles).
							$state['capable'] = true;
							$active           = array_filter( Retire::status(), static function ( $info ) {
								return $info['site'];
							} );
							if ( $active && array_search( $state['stage'], self::STAGES, true ) > array_search( 'deactivate', self::STAGES, true ) ) {
								$state = self::next( $state, 'prepare' );
							}
						}
						if ( $conflicts[ $site_id ] || $job['deactivated'] ) {
							$state['counts']['deactivated'] = array_values( array_unique( array_merge( (array) $state['counts']['deactivated'], $conflicts[ $site_id ] ) ) );
							$state['deactivated_at']        = $now;
						}
						update_option( self::JOB, $state, false );
					} finally {
						self::unlock();
					}
				}
				self::schedule();
			} finally {
				restore_current_blog();
			}
		}
		update_site_option( self::NETWORK_JOB, $job );
		return self::network_summary();
	}

	/**
	 * Estado de cada sitio de la migración de red (en marcha, terminado o pendiente) y, si todos terminaron, la da por
	 * terminada. Sólo lee datos de cada sitio.
	 */
	public static function network_summary() {
		$job = self::network_state();
		if ( ! $job ) {
			return array( 'idle' => true );
		}
		$job['status'] = array();
		$all_done      = true;
		foreach ( (array) $job['sites'] as $site_id ) {
			switch_to_blog( (int) $site_id );
			try {
				$state  = self::state();
				$report = self::report();
				if ( $state && empty( $state['done'] ) ) {
					$row = array( 'status' => 'running', 'stage' => (string) $state['stage'] );
				} elseif ( $report && (int) ( $report['finished'] ?? 0 ) >= (int) $job['started'] ) {
					$row = array( 'status' => 'done', 'counts' => (array) $report['counts'], 'pending' => self::has_pending( $report ), 'ep_needed' => ! empty( $report['report']['ep_needed'] ) );
				} else {
					$row = array( 'status' => 'missing' );
				}
				$row['name'] = (string) get_bloginfo( 'name' );
				$row['url']  = (string) home_url( '/' );
				$row['tool'] = (string) admin_url( 'tools.php?page=' . Migration::PAGE );
				$row['ajax'] = (string) admin_url( 'admin-ajax.php' );
			} finally {
				restore_current_blog();
			}
			$all_done                        = $all_done && 'done' === $row['status'];
			$job['status'][ (int) $site_id ] = $row;
		}
		if ( $all_done && empty( $job['done'] ) ) {
			$job['done']     = true;
			$job['finished'] = time();
			$stored          = $job;
			unset( $stored['status'] );
			update_site_option( self::NETWORK_JOB, $stored );
		}
		return $job;
	}

	/** Sitios que la pantalla de red debe conducir: su admin-ajax.php y un nonce para la tarea de cada uno. */
	public static function network_pending_sites( $job ) {
		$out = array();
		foreach ( (array) ( $job['status'] ?? array() ) as $site_id => $row ) {
			if ( 'running' === $row['status'] ) {
				$out[] = array( 'id' => (int) $site_id, 'name' => $row['name'], 'url' => $row['url'], 'ajax' => $row['ajax'], 'nonce' => wp_create_nonce( self::AJAX . '_' . (int) $site_id ) );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ Cerrojo, presupuesto, memoria y cron */

	/**
	 * Toma el cerrojo del sitio en curso de forma atómica (INSERT IGNORE, como WP_Upgrader::create_lock()) con un token
	 * propio. Un cerrojo sin renovar durante STALE segundos se roba sólo si nadie lo renovó entre la lectura y el robo.
	 */
	private static function lock() {
		global $wpdb;
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false ) : bin2hex( random_bytes( 10 ) );
		$value = time() . '|' . $token;
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", self::LOCK, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( 1 !== $taken ) {
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( '' !== $held && time() - (int) $held < self::STALE ) {
				return false;
			}
			if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, self::LOCK, $held ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return false;
			}
		}
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::LOCK, 'options' );
		}
		if ( ! self::$tokens ) {
			register_shutdown_function( array( __CLASS__, 'unlock_all' ) );
		}
		self::$tokens[ (string) self::blog() ] = $token;
		return true;
	}

	/** Renueva la marca del cerrojo propio (sólo si sigue siendo el nuestro). */
	private static function touch() {
		global $wpdb;
		$token = self::$tokens[ (string) self::blog() ] ?? '';
		if ( '' !== $token ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value LIKE %s", time() . '|' . $token, self::LOCK, '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/** Suelta el cerrojo propio; nunca el de otra petición. */
	private static function unlock() {
		global $wpdb;
		$blog  = (string) self::blog();
		$token = self::$tokens[ $blog ] ?? '';
		unset( self::$tokens[ $blog ] );
		if ( '' !== $token ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value LIKE %s", self::LOCK, '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( self::LOCK, 'options' );
			}
		}
	}

	/** Si la petición termina con un error fatal, suelta los cerrojos propios que quedaran tomados (en su sitio). */
	public static function unlock_all() {
		foreach ( array_keys( self::$tokens ) as $blog ) {
			$switch = is_multisite() && (int) $blog && (int) $blog !== self::blog();
			if ( $switch ) {
				switch_to_blog( (int) $blog );
			}
			self::unlock();
			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	private static function blog() {
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
	}

	/** Segundos de trabajo de esta petición, con el límite de PHP ampliado y sin acercarse a él. */
	private static function budget() {
		$budget = (int) apply_filters( 'digitalisimo_migration_step_seconds', self::BUDGET );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- puede estar deshabilitada.
		}
		$max = (int) ini_get( 'max_execution_time' );
		return $max > 0 ? max( 5, min( $budget, $max - 10 ) ) : max( 5, $budget );
	}

	/** La conversión carga los controles de cientos de widgets: hasta 512 MB mientras dura, como el editor con Migración. */
	private static function raise_memory() {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			add_filter( 'admin_memory_limit', array( __CLASS__, 'memory' ), 20 );
			wp_raise_memory_limit( 'admin' );
		}
	}

	public static function memory( $limit ) {
		return wp_convert_hr_to_bytes( (string) $limit ) < 536870912 ? '512M' : $limit;
	}

	private static function schedule() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + self::CRON_DELAY, self::CRON );
		}
	}

	/* ------------------------------------------------------------------ Vistas */

	/** HTML del estado del sitio: avance de la tarea en marcha o informe de la terminada. */
	public static function render( $state ) {
		if ( ! is_array( $state ) || ! empty( $state['idle'] ) || empty( $state['stage'] ) ) {
			return '';
		}
		return empty( $state['done'] ) ? self::render_progress( $state ) : self::render_report( $state );
	}

	private static function render_progress( array $state ) {
		$label = self::LABELS[ $state['stage'] ] ?? (string) $state['stage'];
		$step  = array_search( $state['stage'], self::STAGES, true );
		$html  = '<div class="digi-migrate__progress notice notice-info inline"><p><span class="spinner is-active" style="float:none;margin:0 8px 0 0"></span><strong>' . esc_html( $label ) . '…</strong> ' . esc_html( sprintf( '(paso %d de %d)', false === $step ? 1 : $step + 1, count( self::STAGES ) ) );
		if ( ! empty( $state['total'] ) ) {
			$done  = min( (int) $state['processed'], (int) $state['total'] );
			$html .= ' · ' . esc_html( sprintf( '%d de %d documentos', $done, (int) $state['total'] ) ) . '</p><progress max="' . (int) $state['total'] . '" value="' . $done . '" style="width:100%;max-width:480px"></progress>';
		} else {
			$html .= '</p>';
		}
		$html .= self::render_counts( (array) $state['counts'] );
		if ( ! empty( $state['busy'] ) ) {
			$html .= '<p>Otra petición está trabajando en esta migración; se muestra su avance.</p>';
		}
		return $html . '</div>';
	}

	private static function render_counts( array $c ) {
		$rows = array();
		if ( ! empty( $c['deactivated'] ) ) {
			$rows[] = 'Plugins desactivados en este sitio: ' . implode( ', ', (array) $c['deactivated'] ) . '.';
		}
		if ( ! empty( $c['converted'] ) ) {
			$rows[] = sprintf( 'Documentos de Element Pack convertidos: %d (%d elementos).', (int) $c['converted'], (int) $c['elements'] );
		}
		if ( ! empty( $c['revisions'] ) ) {
			$rows[] = sprintf( 'Revisiones y autoguardados convertidos: %d.', (int) $c['revisions'] );
		}
		if ( ! empty( $c['aliases'] ) ) {
			$rows[] = sprintf( 'Documentos con widgets fusionados actualizados: %d (%d widgets).', (int) $c['aliases'], (int) $c['alias_elements'] );
		}
		if ( ! empty( $c['recovered'] ) ) {
			$rows[] = sprintf( 'Páginas sobrescritas recuperadas: %d.', (int) $c['recovered'] );
		}
		if ( ! empty( $c['repaired'] ) ) {
			$rows[] = sprintf( 'Documentos con reglas de visibilidad recuperadas: %d.', (int) $c['repaired'] );
		}
		if ( ! $rows ) {
			return '';
		}
		$html = '<ul class="digi-migrate__counts" style="list-style:disc;margin-left:20px">';
		foreach ( $rows as $row ) {
			$html .= '<li>' . esc_html( $row ) . '</li>';
		}
		return $html . '</ul>';
	}

	private static function date( $time ) {
		return function_exists( 'wp_date' ) ? (string) wp_date( 'd/m/Y H:i', (int) $time ) : gmdate( 'd/m/Y H:i', (int) $time );
	}

	/** Lista de documentos del informe (también los de informes anteriores, guardados como título). */
	private static function render_documents( $label, array $docs ) {
		$html = '<p>' . esc_html( $label ) . '</p><ul style="list-style:disc;margin-left:20px">';
		foreach ( $docs as $id => $doc ) {
			$title = is_array( $doc ) ? (string) $doc['title'] : (string) $doc;
			$link  = is_array( $doc ) ? (string) $doc['link'] : admin_url( 'post.php?post=' . (int) $id . '&action=elementor' );
			$html .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></li>';
		}
		return $html . '</ul>';
	}

	private static function render_report( array $state ) {
		$r    = (array) ( $state['report'] ?? array() );
		$c    = (array) $state['counts'];
		$html = '<div class="digi-migrate__done notice notice-success inline"><p><strong>Migración completada</strong>' . ( ! empty( $state['finished'] ) ? ' el ' . esc_html( self::date( $state['finished'] ) ) : '' ) . '.</p></div>';
		$html .= self::render_counts( $c );
		if ( empty( $c['deactivated'] ) && empty( $c['converted'] ) && empty( $c['revisions'] ) && empty( $c['aliases'] ) && empty( $c['recovered'] ) && empty( $c['repaired'] ) ) {
			$html .= '<p>No había nada que migrar en este sitio.</p>';
		}
		$still     = array();
		$deletable = array();
		$later     = array();
		foreach ( (array) ( $r['plugins'] ?? array() ) as $plugin ) {
			if ( ! empty( $plugin['network'] ) ) {
				$still[] = $plugin['label'] . ' (activo en toda la red)';
			} elseif ( ! empty( $plugin['site'] ) ) {
				$still[] = $plugin['label'] . ' (sigue activo en este sitio)';
			} elseif ( ! empty( $plugin['element_pack'] ) && ( ! empty( $r['ep_needed'] ) || is_multisite() ) ) {
				// Otros sitios de la red, o lo que falta convertir aquí, aún pueden necesitar sus ajustes y sus figuras.
				$later[] = $plugin['label'];
			} elseif ( ! empty( $plugin['sites'] ) ) {
				$later[] = sprintf( '%s (sigue activo en %d sitios de la red)', $plugin['label'], (int) $plugin['sites'] );
			} else {
				$deletable[] = $plugin['label'];
			}
		}
		if ( $deletable ) {
			$html .= '<p>' . esc_html( sprintf( 'Desactivados y listos para borrarse desde %s cuando hayas comprobado el sitio: %s. La migración no los borra.', is_multisite() ? 'Red → Plugins' : 'Plugins', implode( ', ', $deletable ) ) ) . '</p>';
		}
		if ( $later ) {
			$html .= '<p>' . esc_html( 'Desactivados en este sitio, pero aún no los borres: ' . implode( ', ', $later ) . '. ' . ( is_multisite() ? 'El resumen de Red → Ajustes → Migrar a DIGITALÍSIMO Elements dice cuándo pueden borrarse.' : 'Element Pack hace falta hasta convertir lo que queda pendiente.' ) ) . '</p>';
		}
		$pending = '';
		if ( $still ) {
			$pending .= '<div class="notice notice-warning inline"><p>' . esc_html( 'Siguen activos: ' . implode( ', ', $still ) . '. Los avisos de abajo explican por qué y qué quedó pendiente.' ) . '</p></div>';
		}
		foreach ( (array) $state['warnings'] as $warning ) {
			$pending .= '<div class="notice notice-warning inline"><p>' . esc_html( (string) $warning ) . '</p></div>';
		}
		if ( ! empty( $r['unsupported'] ) ) {
			$pending .= '<p>Widgets de Element Pack sin equivalente. Sin Element Pack no se muestran: sustitúyelos en el editor por la alternativa indicada o acepta que desaparezcan.</p><table class="widefat striped" style="max-width:860px"><thead><tr><th>Widget</th><th>Elementos</th><th>Documentos</th><th>Motivo y alternativa</th></tr></thead><tbody>';
			foreach ( (array) $r['unsupported'] as $type => $row ) {
				$pending .= '<tr><td><code>' . esc_html( (string) $type ) . '</code></td><td>' . (int) $row['elements'] . '</td><td>' . (int) $row['documents'] . '</td><td>' . esc_html( (string) $row['reason'] ) . '</td></tr>';
			}
			$pending .= '</tbody></table>';
		}
		foreach ( array(
			'blocked' => 'Páginas que conservan esos widgets:',
			'review'  => 'Páginas sobrescritas por una conversión antigua sin revisión ni copia de la que recuperarlas: revísalas en el editor y guárdalas (dejan de listarse al guardarlas).',
			'failed'  => 'Documentos que no pudieron convertirse (la conversión se cortó dos veces): se siguen viendo como antes; vuelve a pulsar Migrar o revísalos en el editor.',
		) as $key => $label ) {
			if ( ! empty( $r[ $key ] ) ) {
				$pending .= self::render_documents( $label, (array) $r[ $key ] );
			}
		}
		foreach ( (array) ( $r['alias_blocked'] ?? array() ) as $type => $places ) {
			$spec     = (array) ( $r['alias_targets'][ $type ] ?? array() );
			$pending .= '<p>' . esc_html( sprintf( 'Los widgets %s siguen como alias de %s porque CSS, marcado o código del sitio nombra sus clases o su envoltorio (o el del widget fusionado): se siguen viendo igual. Para terminar, cambia ese código a las clases del widget fusionado (.%s en lugar de .%s, y elementor-widget-%s) y vuelve a pulsar Migrar. Lugares: %s.', $type, (string) ( $spec['target'] ?? '' ), (string) ( $spec['target_prefix'] ?? '' ), (string) ( $spec['prefix'] ?? '' ), (string) ( $spec['target'] ?? '' ), implode( '; ', (array) $places ) ) ) . '</p>';
		}
		if ( ! empty( $r['missing_figures'] ) ) {
			$pending .= '<p>' . esc_html( 'Máscaras o separadores de Element Pack sin archivo, retirados para que la imagen siga visible: ' . implode( ', ', (array) $r['missing_figures'] ) . '.' ) . '</p>';
		}
		if ( ! empty( $r['convertible'] ) ) {
			$pending .= '<p>' . esc_html( sprintf( 'Quedan %d documentos con contenido de Element Pack por convertir: vuelve a pulsar Migrar cuando se resuelvan los avisos.', (int) $r['convertible'] ) ) . '</p>';
		}
		if ( ! empty( $r['aliases_left'] ) ) {
			$pending .= '<p>' . esc_html( sprintf( 'Quedan %d documentos con widgets fusionados por actualizar: vuelve a pulsar Migrar cuando se resuelvan los avisos.', (int) $r['aliases_left'] ) ) . '</p>';
		}
		if ( '' !== $pending ) {
			return $html . '<h3>Pendiente de revisar</h3>' . $pending;
		}
		return $html . '<div class="notice notice-success inline"><p>Este sitio ya no depende de Elementor, Elementor Pro, PRO Elements ni Element Pack. Si usas una caché de página o una CDN, vacíala. Cuando hayas comprobado el sitio, borra esos plugins y DIGITALÍSIMO Migración.</p></div>';
	}

	/** HTML de la migración de red: estado de cada sitio y, al terminar, qué plugins pueden borrarse. */
	public static function render_network( $job ) {
		if ( ! is_array( $job ) || ! empty( $job['idle'] ) ) {
			return '';
		}
		$status = (array) ( $job['status'] ?? array() );
		$done   = count( array_filter( $status, static function ( $row ) {
			return 'done' === $row['status'];
		} ) );
		if ( empty( $job['done'] ) ) {
			$html = '<div class="digi-migrate__progress notice notice-info inline"><p><span class="spinner is-active" style="float:none;margin:0 8px 0 0"></span><strong>' . esc_html( sprintf( 'Migrando la red: %d de %d sitios terminados.', $done, count( $status ) ) ) . '</strong></p><progress max="' . max( 1, count( $status ) ) . '" value="' . $done . '" style="width:100%;max-width:480px"></progress></div>';
		} else {
			$html = '<div class="digi-migrate__done notice notice-success inline"><p><strong>Migración de la red completada</strong>' . ( ! empty( $job['finished'] ) ? ' el ' . esc_html( self::date( $job['finished'] ) ) : '' ) . '.</p></div>';
		}
		if ( ! empty( $job['deactivated'] ) ) {
			$html .= '<p>' . esc_html( 'Desactivados en toda la red: ' . implode( ', ', (array) $job['deactivated'] ) . '.' ) . '</p>';
		}
		foreach ( (array) $job['warnings'] as $warning ) {
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html( (string) $warning ) . '</p></div>';
		}
		if ( ! empty( $job['skipped'] ) ) {
			$names = array();
			foreach ( (array) $job['skipped'] as $site_id ) {
				$details = get_blog_details( (int) $site_id );
				$names[] = $details ? $details->blogname . ' (' . $details->siteurl . ')' : '#' . (int) $site_id;
			}
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html( 'Sin DIGITALÍSIMO Elements activo, no se tocaron: ' . implode( ', ', $names ) . '.' ) . '</p></div>';
		}
		$html     .= '<table class="widefat striped digi-migrate__sites"><thead><tr><th>Sitio</th><th>Estado</th><th>Resultado</th><th></th></tr></thead><tbody>';
		$ep_needed = false;
		foreach ( $status as $site_id => $row ) {
			$c = (array) ( $row['counts'] ?? array() );
			if ( 'done' === $row['status'] ) {
				$ep_needed = $ep_needed || ! empty( $row['ep_needed'] );
				$state     = ! empty( $row['pending'] ) ? 'Terminado, con asuntos por revisar' : 'Terminado';
				$result    = sprintf( '%d documentos y %d revisiones convertidos, %d con widgets fusionados actualizados', (int) ( $c['converted'] ?? 0 ), (int) ( $c['revisions'] ?? 0 ), (int) ( $c['aliases'] ?? 0 ) ) . ( ! empty( $c['deactivated'] ) ? '; desactivados: ' . implode( ', ', (array) $c['deactivated'] ) : '' );
			} elseif ( 'running' === $row['status'] ) {
				$ep_needed = true;
				$state     = 'En marcha';
				$result    = self::LABELS[ $row['stage'] ] ?? (string) $row['stage'];
			} else {
				$ep_needed = true;
				$state     = 'Sin tarea';
				$result    = 'Vuelve a pulsar Migrar toda la red.';
			}
			$html .= '<tr data-site="' . (int) $site_id . '"><td><strong>' . esc_html( (string) $row['name'] ) . '</strong><br><code>' . esc_html( (string) $row['url'] ) . '</code></td><td class="digi-migrate__site-status">' . esc_html( $state ) . '</td><td>' . esc_html( $result ) . '</td><td><a href="' . esc_url( (string) $row['tool'] ) . '">Informe del sitio</a></td></tr>';
		}
		$html .= '</tbody></table>';
		if ( ! empty( $job['done'] ) ) {
			$deletable = array();
			$still     = array();
			foreach ( Retire::network_summary() as $row ) {
				if ( $row['network'] ) {
					$still[] = $row['label'] . ' (activo en toda la red)';
				} elseif ( $row['sites'] ) {
					$still[] = sprintf( '%s (activo en %d sitios)', $row['label'], (int) $row['sites'] );
				} elseif ( $row['element_pack'] && $ep_needed ) {
					$still[] = $row['label'] . ' (desactivado, pero algún sitio aún tiene contenido suyo por convertir: no lo borres todavía)';
				} else {
					$deletable[] = $row['label'];
				}
			}
			if ( $deletable ) {
				$html .= '<p>' . esc_html( 'Ya no están activos en ningún sitio de la red: puedes borrarlos desde Red → Plugins cuando hayas comprobado los sitios. La migración no los borra: ' . implode( ', ', $deletable ) . '.' ) . '</p>';
			}
			if ( $still ) {
				$html .= '<div class="notice notice-warning inline"><p>' . esc_html( 'Aún no: ' . implode( ', ', $still ) . '. El informe de cada sitio explica por qué.' ) . '</p></div>';
			}
		}
		return $html;
	}
}
