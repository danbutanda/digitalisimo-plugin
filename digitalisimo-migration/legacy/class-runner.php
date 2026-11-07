<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-context.php';
require_once __DIR__ . '/class-report.php';

/**
 * «Migrar» en un clic: la migración completa de un sitio como una tarea por etapas, reanudable y con cerrojo. Avanza
 * en peticiones AJAX mientras la pantalla está abierta y, si se cierra, con el WP-Cron del sitio;
 * `wp digitalisimo-migration --run` la ejecuta entera. Etapas, en orden:
 *
 *  1. prepare          — qué plugins sustituidos están instalados y dónde están activos; quién lanzó la tarea; fija el
 *                        corte de la reparación de reglas.
 *  2. platform_prepare — con Element Pack aún cargado, los pasos de plataforma (PREPARE_STEPS) crean borradores
 *                        (plantillas, fragmentos) y registran fuentes e iconos. Nada visible cambia.
 *  3. deactivate       — desactiva los grupos de plugins (Element Pack, Elementor Pro/PRO Elements, Elementor), cada
 *                        uno en una escritura. El grupo de Element Pack sólo cae si sus plantillas tienen copia
 *                        publicable (V.5.3), y sólo entonces se permite publicarlas (Publisher). En una red con
 *                        Element Pack activado en toda la red, el sitio espera aquí a la barrera (`wait_network`).
 *  4. platform_convert — las copias del registro `tb`, primero.
 *  5. recover, repair  — páginas sobrescritas y reglas perdidas por versiones antiguas.
 *  6. convert          — documentos con contenido de Element Pack o Prime Slider, y ajustes de página, con copia.
 *  7. revisions        — revisiones y autoguardados, cada uno en su registro.
 *  8. markers          — marcadores de K con Element Pack o Prime Slider dentro (Markers).
 *  9. platform         — pasos de plataforma tras convertir (PLATFORM_STEPS).
 * 10. references, aliases — widgets fusionados.
 * 11. pro              — widgets de PRO con widget propio publicado (Pro), por lotes.
 * 12. retire_late      — Prime Slider, sólo sin widgets suyos por convertir.
 * 13. report           — el informe de lo convertido en pasadas anteriores, por trozos.
 * 14. finish           — cachés e informe.
 *
 * Protocolo (V.3.1): una etapa sin documentos suma un intento al entrar y se salta tras dos entradas sin progreso; si
 * avanzó (cursor guardado) o espera con motivo, pone `stage_attempts = 0`. El salto es seguro: nunca desactiva Element
 * Pack ni da por convertido lo que no se convirtió. Una espera pone `pause` y no suma `pauses`.
 *
 * Sólo toca el sitio en curso. En una red, cada petición carga los plugins del sitio que la atiende: la petición de red
 * desactiva al inicio lo retirable activado en toda la red (salvo Element Pack, que cae en la liberación de la barrera,
 * y Prime Slider, al final), encola la tarea de cada sitio con la autorización de quien la lanzó, libera la barrera y
 * lee. Cada sitio se convierte en sus propias peticiones.
 */
final class Runner {
	const JOB         = 'digitalisimo_migration_job';
	const LOCK        = 'digitalisimo_migration_lock';
	const REPORT      = 'digitalisimo_migration_report';
	const CRON        = 'digitalisimo_migration_continue';
	const NETWORK_JOB = 'digitalisimo_migration_network_job';
	const AJAX        = 'digitalisimo_migration_step';
	/** Evento de WP-Cron del sitio principal que libera la barrera y cierra la red sin pantalla (V.6). */
	const NETWORK_TICK = 'digitalisimo_migration_network_tick';
	/** Cerrojo de la red: en `options` del sitio principal, con INSERT IGNORE y token; se roba a los 300 s. */
	const NETWORK_LOCK  = 'digitalisimo_migration_network_lock';
	const NETWORK_STALE = 300;
	/** Listas de IDs por etapa, en trozos sin carga automática (V.3.5). */
	const IDS        = 'digitalisimo_migration_ids_';
	const IDS_CHUNK  = 5000;
	/** DIGITALÍSIMO Elements mínimo (V.3.3). */
	const ELEMENTS_MIN = '4.3.0.170';
	const STAGES      = array( 'prepare', 'platform_prepare', 'deactivate', 'platform_convert', 'recover', 'repair', 'convert', 'revisions', 'markers', 'platform', 'references', 'aliases', 'pro', 'pro_css', 'retire_late', 'report', 'finish' );
	/** Etapas que recorren documentos (o avanzan con cursor): sus intentos se cuentan por documento. */
	const LOOP_STAGES = array( 'platform_convert', 'recover', 'convert', 'revisions', 'markers', 'aliases', 'pro', 'pro_css', 'retire_late', 'report' );
	/** Pasos de plataforma (clases de `Legacy\Platform`, V.14 C5): con Element Pack cargado y tras convertir el contenido. */
	const PREPARE_STEPS  = array( 'Theme_Builder', 'Fonts', 'Icons', 'Snippets', 'Sidebar' );
	const PLATFORM_STEPS = array( 'Theme_Builder', 'Mega_Menu', 'Kit', 'Snippets', 'Swatches', 'Inventory' );
	/** Lo que se revierte tras los pasos de plataforma y antes de los documentos (V.8). */
	const REVERT_EXTRA = array( 'Prime_Slider\\Loop_Item', 'Prime_Slider\\Demo_Media' );
	const LABELS      = array(
		'prepare'          => 'Revisando el sitio',
		'platform_prepare' => 'Preparando plantillas, fuentes e iconos',
		'deactivate'       => 'Desactivando los plugins sustituidos',
		'platform_convert' => 'Convirtiendo las plantillas copiadas',
		'recover'          => 'Recuperando páginas sobrescritas',
		'repair'           => 'Recuperando reglas de visibilidad',
		'convert'          => 'Convirtiendo el contenido de Element Pack y Prime Slider',
		'revisions'        => 'Convirtiendo revisiones y autoguardados',
		'markers'          => 'Convirtiendo el contenido conservado de kits importados',
		'platform'         => 'Terminando plantillas, menús, kit y código',
		'references'       => 'Buscando CSS que nombre widgets fusionados',
		'aliases'          => 'Actualizando widgets fusionados',
		'pro'              => 'Convirtiendo los widgets de PRO a widgets propios',
		'pro_css'          => 'Llevando a los widgets propios el CSS que nombra los de PRO',
		'retire_late'      => 'Retirando Prime Slider',
		'report'           => 'Preparando el informe',
		'finish'           => 'Vaciando cachés y preparando el informe',
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
	/** Tipo de los marcadores de K (como Native::MARKER_WIDGET, sin cargar la conversión). */
	const MARKER_WIDGET = 'digitalisimo-unconverted';
	/** Tope del cursor de un paso de plataforma (V.14 C5). */
	const CURSOR_MAX = 4096;

	/** @var string token del cerrojo de esta petición, por sitio. */
	private static $tokens = array();
	/** @var string token del cerrojo de red de esta petición. */
	private static $network_token = '';

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax' ) );
		add_action( self::CRON, array( __CLASS__, 'cron' ) );
		add_action( self::NETWORK_TICK, array( __CLASS__, 'network_tick' ) );
		add_filter( 'allowed_http_origins', array( __CLASS__, 'allowed_origins' ) );
	}

	/** Carga el motor de conversión sólo cuando hace falta (AJAX, cron, CLI y las pantallas). */
	public static function load() {
		foreach ( array( 'Migration' => 'migration', 'Retire' => 'retire', 'Aliases' => 'aliases', 'Markers' => 'markers', 'Pro' => 'pro' ) as $class => $file ) {
			if ( ! class_exists( __NAMESPACE__ . '\\' . $class, false ) && is_readable( __DIR__ . '/class-' . $file . '.php' ) ) {
				require_once __DIR__ . '/class-' . $file . '.php';
			}
		}
		self::dropped();
	}

	/** Lo que la migración aún no convierte (extensiones sin equivalente): sólo cuando hace falta. */
	private static function dropped() {
		require_once __DIR__ . '/class-dropped.php';
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

	/**
	 * Motivo por el que no puede migrarse con esta versión de DIGITALÍSIMO Elements (V.3.3), o null. Sin la constante
	 * (las pruebas) no se comprueba.
	 */
	public static function requirements(): ?string {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ) {
			return null;
		}
		$version = (string) constant( 'DIGITALISIMO_ELEMENTS_VERSION' );
		if ( version_compare( $version, self::ELEMENTS_MIN, '<' ) ) {
			return sprintf( 'Migrar necesita DIGITALÍSIMO Elements %1$s o posterior (este sitio tiene %2$s): actualízalo y vuelve a pulsar Migrar.', self::ELEMENTS_MIN, $version );
		}
		return null;
	}

	/* ------------------------------------------------------------------ Entradas */

	/** Un paso desde la pantalla: la tarea del sitio, o con `network` el arranque, la barrera y el resumen de la red. */
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
		$start = ! empty( $_POST['start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
		// Con un DIGITALÍSIMO Elements anterior, el botón no arranca nada ni toca el informe guardado.
		$reason = $start ? self::requirements() : null;
		if ( null !== $reason ) {
			wp_send_json_error( $reason, 409 );
		}
		if ( $network ) {
			if ( $start ) {
				$job = self::network_start();
			} else {
				self::network_release();
				$job = self::network_summary();
			}
			wp_send_json_success( self::network_response( $job ) );
		}
		// El clic genera un identificador: si se perdió la respuesta de una pasada que sí terminó, el reintento no lanza otra.
		$run   = isset( $_POST['run'] ) ? sanitize_key( wp_unslash( $_POST['run'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
		$state = self::site_step( $start, null, null, $run );
		wp_send_json_success( self::site_response( $state ) );
	}

	/** Respuesta del paso de un sitio para la pantalla (también la de red, que conduce cada sitio). */
	public static function site_response( array $state ) {
		// Sin tarea en marcha (otra pestaña, el cron o la red la terminaron), se muestra el informe guardado.
		$html = empty( $state['stage'] ) || ! empty( $state['idle'] ) ? self::render( self::report() ) : self::render( $state );
		return array(
			'done'         => ! empty( $state['done'] ) || ( empty( $state['stage'] ) && ! empty( $state['idle'] ) && self::report() ),
			'idle'         => ! empty( $state['idle'] ),
			'busy'         => ! empty( $state['busy'] ),
			'pause'        => ! empty( $state['pause'] ),
			'wait_network' => ! empty( $state['wait_network'] ) && empty( $state['done'] ),
			'stage'        => (string) ( $state['stage'] ?? '' ),
			'html'         => $html,
		);
	}

	/** Respuesta del paso de red: sitios que conducir (no los que esperan a la barrera) y estado de la barrera. */
	public static function network_response( $job ) {
		$job     = is_array( $job ) ? $job : array();
		$waiting = 0;
		foreach ( (array) ( $job['status'] ?? array() ) as $row ) {
			$waiting += ! empty( $row['waiting'] ) ? 1 : 0;
		}
		return array(
			'done'     => ! empty( $job['done'] ),
			'idle'     => ! empty( $job['idle'] ),
			'error'    => (string) ( $job['error'] ?? '' ),
			'barrier'  => ! empty( $job['barrier'] ),
			'released' => ! empty( $job['released'] ),
			'waiting'  => $waiting,
			'sites'    => self::network_pending_sites( $job ),
			'html'     => isset( $job['error'] ) ? '<div class="notice notice-error inline"><p>' . esc_html( (string) $job['error'] ) . '</p></div>' : self::render_network( $job ),
		);
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
		Report::begin( 'run' );
		try {
			self::raise_memory();
			$state = self::state();
			$last  = self::report();
			if ( $start && ( ! $state || ! empty( $state['done'] ) ) && ( '' === (string) $run || (string) $run !== (string) ( $last['run'] ?? '' ) ) ) {
				if ( null !== self::requirements() ) {
					return array( 'idle' => true, 'error' => self::requirements() );
				}
				$state        = self::fresh( $capable );
				$state['run'] = (string) $run;
				self::save( $state );
			}
			if ( ! $state ) {
				return array( 'idle' => true );
			}
			// La continuación se programa antes de trabajar: si esta petición muere, el WP-Cron la retoma.
			self::schedule();
			// Una tarea en curso con un DIGITALÍSIMO Elements anterior se pausa (nunca se cierra) hasta actualizarlo.
			$reason = self::requirements();
			if ( null !== $reason ) {
				$state['pause']        = true;
				$state['elements_old'] = $reason;
				Report::site( 'core.elements_old' );
				self::save( $state );
				return $state;
			}
			unset( $state['elements_old'] );
			// Un proceso que arrancó antes de que la tarea desactivara plugins aún los tiene cargados: no trabaja.
			if ( ! empty( $state['deactivated_at'] ) && self::request_started() < (float) $state['deactivated_at'] ) {
				$state['pause'] = true;
				return $state;
			}
			// La primera petición posterior a cada desactivación empieza sin pausas acumuladas (V.3.2).
			if ( ! empty( $state['deactivated_at'] ) && (string) ( $state['pauses_reset_at'] ?? '' ) !== (string) $state['deactivated_at'] ) {
				$state['pauses']          = 0;
				$state['pauses_reset_at'] = (string) $state['deactivated_at'];
			}
			$state = self::advance( $state, $budget );
		} finally {
			Report::flush();
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
	public static function fresh( $capable = null, $network = 0, $user = null ) {
		return array(
			'version'         => defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '',
			'started'         => time(),
			'updated'         => time(),
			'network'         => (int) $network,
			'stage'           => 'prepare',
			'done'            => false,
			'pause'           => false,
			'pauses'          => 0,
			'deactivated_at'  => 0,
			'cursor'          => 0,
			'processed'       => 0,
			'total'           => 0,
			'current'         => 0,
			'attempts'        => 0,
			'stage_attempts'  => 0,
			'capable'         => null === $capable ? ( function_exists( 'current_user_can' ) && current_user_can( 'activate_plugins' ) ) : (bool) $capable,
			// Quien pulsó (o quien lanzó la red): Snippets lo revalida al publicar CSS (V.7). 0 en WP-CLI sin --user.
			'user'            => null === $user ? ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 ) : (int) $user,
			'counts'          => array( 'deactivated' => array(), 'recovered' => 0, 'repaired' => 0, 'converted' => 0, 'elements' => 0, 'revisions' => 0, 'aliases' => 0, 'alias_elements' => 0, 'pro' => 0, 'pro_elements' => 0, 'pro_css' => 0, 'pages' => 0, 'copies' => 0, 'markers' => 0, 'failed' => array() ),
			'warnings'        => array(),
			'review'          => array(),
			'keep_revisions'  => array(),
			'plugins'         => array(),
			'blocked'         => array(),
			// Extensiones heredadas sin equivalente que la conversión retira y la copia original no recoge (Dropped::uncovered()).
			'dropped'         => array( 'docs' => array(), 'omitted' => 0 ),
			'report'          => array(),
			// Pasos de plataforma: cursores por clase y los ya terminados.
			'platform'        => array(),
			'platform_done'   => array(),
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
			// Una tarea de Migración ≤ 1.0.20 que ya pasó `deactivate` prepara y convierte sus copias antes de seguir (V.3.4).
			if ( self::legacy_task( $state ) && empty( $state['late_checked'] ) && array_search( $stage, self::STAGES, true ) > array_search( 'platform_convert', self::STAGES, true ) ) {
				$state['late_checked'] = true;
				$state['late']         = true;
				$state                 = self::next( $state, 'platform_convert' );
				self::save( $state );
				continue;
			}
			if ( ! in_array( $stage, self::LOOP_STAGES, true ) ) {
				// Una etapa sin documentos que tumba la petición dos veces se salta, sin desactivar Element Pack.
				$state['stage_attempts'] = (int) ( $state['stage_attempts'] ?? 0 ) + 1;
				if ( $state['stage_attempts'] > self::MAX_ATTEMPTS ) {
					$state = self::skip( $state, $stage );
					self::save( $state );
					continue;
				}
				self::save( $state );
			}
			$state = call_user_func( array( __CLASS__, 'stage_' . $stage ), $state, $deadline );
			// Esperar a la barrera no es avanzar: el plazo de la red mide el último avance de verdad.
			if ( empty( $state['wait_network'] ) ) {
				$state['updated'] = time();
			}
			self::save( $state );
		}
		return $state;
	}

	/** ¿Tarea creada por Migración ≤ 1.0.20? */
	private static function legacy_task( array $state ) {
		$version = (string) ( $state['version'] ?? '' );
		return '' !== $version && version_compare( $version, '1.0.21', '<' );
	}

	/** Salto seguro de una etapa que cayó dos veces (V.3.1). */
	private static function skip( array $state, $stage ) {
		$state = self::warn( $state, sprintf( 'La etapa «%s» no pudo completarse (la petición se cortó dos veces) y se saltó.', self::LABELS[ $stage ] ?? $stage ) );
		switch ( $stage ) {
			case 'platform_prepare':
				// Sin preparación, el grupo de Element Pack no se desactiva (sí los demás).
				$state['platform_failed'] = true;
				Report::site( 'core.tb_unprepared' );
				return self::next( $state, 'deactivate' );
			case 'deactivate':
				// Lo ya desactivado queda; si Element Pack sigue cargado, la conversión lleva al informe.
				unset( $state['wait_network'] );
				return self::next( $state, 'platform_convert' );
			case 'platform':
				Report::site( 'core.stage_skipped', array( 'name' => self::LABELS['platform'] ) );
				return self::next( $state, 'references' );
			case 'finish':
				return self::close( $state, array() );
		}
		$index = array_search( $stage, self::STAGES, true );
		return self::next( $state, self::STAGES[ false === $index ? 0 : $index + 1 ] ?? 'finish' );
	}

	private static function next( array $state, $stage, $total = 0 ) {
		self::drop_ids( (string) ( $state['stage'] ?? '' ) );
		Report::flush();
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

	/** Etapa que espera con motivo: se repite en la siguiente petición sin contar como intento ni como pausa. */
	private static function hold( array $state ) {
		$state['pause']          = true;
		$state['stage_attempts'] = 0;
		return $state;
	}

	/** Etapa que avanzó por partes: sigue en la siguiente vuelta (o petición) sin contar como intento. */
	private static function progressed( array $state ) {
		$state['stage_attempts'] = 0;
		return $state;
	}

	/* ------------------------------------------------------------------ Listas de IDs por etapa (V.3.5) */

	/**
	 * IDs de la etapa, calculados una vez (la consulta LIKE corre una vez por etapa) y guardados en trozos de
	 * IDS_CHUNK sin carga automática. Se borran al cerrar la etapa.
	 *
	 * Pública sólo para la etapa `markers` (`Markers::stage()`, frente I), que guarda su lista con el mismo formato para
	 * que `next()` la borre igual.
	 *
	 * @return int[]
	 */
	public static function ids( $stage, callable $compute ) {
		$meta = get_option( self::IDS . $stage, null );
		if ( is_array( $meta ) && isset( $meta['chunks'] ) ) {
			$ids = array();
			for ( $i = 0; $i < (int) $meta['chunks']; $i++ ) {
				$ids = array_merge( $ids, array_map( 'intval', (array) get_option( self::IDS . $stage . '_' . $i, array() ) ) );
			}
			return $ids;
		}
		$ids = array_values( array_unique( array_map( 'intval', (array) $compute() ) ) );
		sort( $ids );
		$chunks = array_chunk( $ids, self::IDS_CHUNK );
		foreach ( $chunks as $i => $chunk ) {
			update_option( self::IDS . $stage . '_' . $i, $chunk, false );
		}
		update_option( self::IDS . $stage, array( 'chunks' => count( $chunks ), 'total' => count( $ids ) ), false );
		return $ids;
	}

	private static function drop_ids( $stage ) {
		if ( '' === $stage ) {
			return;
		}
		$meta = get_option( self::IDS . $stage, null );
		if ( ! is_array( $meta ) ) {
			return;
		}
		for ( $i = 0; $i < (int) ( $meta['chunks'] ?? 0 ); $i++ ) {
			delete_option( self::IDS . $stage . '_' . $i );
		}
		delete_option( self::IDS . $stage );
	}

	/* ------------------------------------------------------------------ Etapas */

	private static function stage_prepare( array $state, $deadline ) {
		$state['plugins'] = array();
		foreach ( Retire::status() as $file => $info ) {
			$state['plugins'][ $file ] = array( 'label' => $info['label'], 'site' => $info['site'], 'network' => $info['network'], 'core' => $info['core'], 'group' => $info['group'], 'late' => $info['late'] );
		}
		// ¿Estaba activo algún miembro del grupo de Element Pack, en cualquier ámbito? (C1-4: sólo entonces se publica.)
		$state['ep_was_active'] = self::ep_was_active( $state );
		// Un sitio de la barrera que prepara después de la liberación (vencido el plazo sin haber respondido) ya no ve
		// el grupo activo porque lo desactivó la propia red, no el administrador: cuenta como activo, para que sus copias
		// se publiquen en su turno (V.18) en lugar de quedarse en borrador sin cabecera (Playground P3 «deadline»).
		if ( ! $state['ep_was_active'] ) {
			$job = self::barrier_job( $state );
			if ( $job && ! empty( $job['released'] ) && in_array( self::blog(), array_map( 'intval', (array) ( $job['barrier_sites'] ?? array() ) ), true ) ) {
				$state['ep_was_active'] = true;
			}
		}
		// Sólo los documentos convertidos antes de esta migración pueden haber perdido sus reglas de visibilidad.
		if ( ! (int) get_option( Migration::REPAIR_CUTOFF, 0 ) ) {
			update_option( Migration::REPAIR_CUTOFF, (int) $state['started'], false );
		}
		// El informe de F8 empieza con la tarea (el anterior queda resumido en el informe guardado).
		Report::clear();
		return self::next( $state, 'platform_prepare' );
	}

	/** ¿Algún miembro del grupo de Element Pack estaba activo al preparar? Lee también tareas de 1.0.20 (sin `group`). */
	private static function ep_was_active( array $state ) {
		foreach ( (array) ( $state['plugins'] ?? array() ) as $file => $info ) {
			$group = (string) ( $info['group'] ?? ( 0 === strpos( basename( (string) $file ), 'bdthemes-element-pack' ) ? 'element-pack' : '' ) );
			if ( 'element-pack' === $group && ( ! empty( $info['site'] ) || ! empty( $info['network'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** Llama a un método de un paso de plataforma (`Legacy\Platform\<Paso>`) si existe; si no, null. */
	private static function platform_call( $step, $method, array $args = array() ) {
		$class = __NAMESPACE__ . '\\Platform\\' . $step;
		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			return null;
		}
		return call_user_func_array( array( $class, $method ), $args );
	}

	/** ¿Existe el método de una clase de otro frente? */
	private static function has( $class, $method ) {
		$class = __NAMESPACE__ . '\\' . $class;
		return class_exists( $class ) && method_exists( $class, $method );
	}

	/**
	 * Recorre los pasos de plataforma con su método (`prepare` o `finish`) y su cursor (V.14 C5). Devuelve el estado y
	 * si terminaron todos. Una excepción se informa y el paso se da por terminado (falla cerrado: el de plantillas en
	 * la preparación deja Element Pack activo).
	 */
	private static function run_steps( array $state, array $steps, $method, $deadline ) {
		foreach ( $steps as $step ) {
			if ( ! empty( $state['platform_done'][ $method ][ $step ] ) ) {
				continue;
			}
			$class = __NAMESPACE__ . '\\Platform\\' . $step;
			if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
				$state['platform_done'][ $method ][ $step ] = true;
				continue;
			}
			$cursor = (array) ( $state['platform'][ $method . ':' . $step ] ?? array() );
			Context::begin( 0, 'run' );
			try {
				$done = (bool) $class::$method( $cursor, (float) $deadline );
			} catch ( \Exception $e ) {
				// Una excepción se informa y el paso se da por terminado; un error de PHP tumba la petición como un fallo
				// fatal, y el protocolo de intentos la salta tras dos caídas (V.3.1).
				$done = true;
				Report::site( 'core.step_failed', array( 'name' => $step, 'note' => $e->getMessage() ) );
				if ( 'Theme_Builder' === $step && 'prepare' === $method ) {
					$state['platform_failed'] = true;
				}
			} finally {
				Context::end();
			}
			if ( strlen( (string) serialize( $cursor ) ) > self::CURSOR_MAX ) {
				// Un cursor que no cabe no se guarda: el paso vuelve a empezar (y debe consultar el registro).
				$cursor = array();
			}
			$state['platform'][ $method . ':' . $step ] = $cursor;
			if ( ! $done ) {
				return array( $state, false );
			}
			$state['platform_done'][ $method ][ $step ] = true;
			unset( $state['platform'][ $method . ':' . $step ] );
			if ( microtime( true ) >= $deadline ) {
				return array( $state, false );
			}
		}
		return array( $state, true );
	}

	/** Con Element Pack aún cargado: borradores y registros de plantillas, fuentes, iconos, código y barra lateral. */
	private static function stage_platform_prepare( array $state, $deadline ) {
		list( $state, $complete ) = self::run_steps( $state, self::PREPARE_STEPS, 'prepare', $deadline );
		if ( ! $complete ) {
			return self::progressed( $state );
		}
		$state['platform_prepared'] = empty( $state['platform_failed'] );
		return self::next( $state, 'deactivate' );
	}

	/**
	 * Las cinco condiciones para permitir la publicación de las copias y desactivar el grupo de Element Pack (V.5.3).
	 * Devuelve null si se cumplen o el código que falla. `plugin_inactive` no impide desactivar (no había nada activo).
	 */
	private static function ep_gate( array $state ) {
		if ( empty( $state['ep_was_active'] ) ) {
			return 'tb.plugin_inactive';
		}
		if ( empty( $state['platform_prepared'] ) || ! empty( $state['platform_failed'] ) ) {
			return 'tb.unprepared';
		}
		$copies = self::has( 'Platform\\Registry', 'all' ) ? Platform\Registry::all( 'tb' ) : array();
		$blocks = self::platform_call( 'Theme_Builder', 'blocked' );
		if ( null === $blocks ) {
			$blocks = false;
			foreach ( $copies as $row ) {
				$blocks = $blocks || ! empty( $row['extra']['blocks'] );
			}
		}
		if ( $blocks ) {
			return 'tb.blocked_retire';
		}
		if ( ! $copies ) {
			// Sin plantillas copiadas no queda ningún hueco que guardar.
			return null;
		}
		// Tras esta etapa no puede quedar Elementor Pro ni PRO Elements: el sitio sólo quita los suyos.
		foreach ( Retire::status() as $info ) {
			if ( 'pro' === $info['group'] && ( $info['network'] || ( $info['site'] && empty( $state['capable'] ) && ! self::network_authorizes() ) ) ) {
				return 'tb.conflict';
			}
		}
		if ( ! self::builder_loaded() ) {
			return 'tb.no_builder';
		}
		return null;
	}

	/** @var bool|null pruebas: simula si el Theme Builder de Elements está cargado en esta petición (null: la clase real). */
	public static $builder = null;

	/** ¿Está cargado el Theme Builder de DIGITALÍSIMO Elements (condición 5 de V.5.3)? */
	private static function builder_loaded(): bool {
		return null !== self::$builder ? (bool) self::$builder : class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' );
	}

	/** Permite publicar las copias (sólo con las cinco condiciones) y deja la barra lateral lista, justo antes de desactivar. */
	private static function before_ep_retire( array $state ) {
		if ( Translator::element_pack_active() ) {
			$result = self::platform_call( 'Sidebar', 'before_deactivate' );
			foreach ( is_array( $result ) ? $result : array() as $code ) {
				if ( is_array( $code ) && isset( $code[0] ) ) {
					Report::site( (string) $code[0], (array) ( $code[1] ?? array() ) );
				}
			}
		}
		self::platform_call( 'Publisher', 'allow', array( true ) );
		$state['allowed'] = true;
		return $state;
	}

	/** Desactiva en este sitio; anota lo desactivado, los avisos y los códigos. */
	private static function retire_site( array $state, array $skip ) {
		$result                         = Retire::deactivate( false, false, $skip );
		$state['counts']['deactivated'] = array_values( array_unique( array_merge( (array) $state['counts']['deactivated'], array_values( $result['deactivated'] ) ) ) );
		foreach ( $result['warnings'] as $warning ) {
			$state = self::warn( $state, $warning );
		}
		foreach ( (array) ( $result['codes'] ?? array() ) as $code ) {
			Report::site( (string) $code[0], (array) ( $code[1] ?? array() ) );
		}
		if ( in_array( 'element-pack', (array) ( $result['kept'] ?? array() ), true ) ) {
			$state['ep_kept'] = true;
		}
		if ( $result['deactivated'] ) {
			// La siguiente petición ya corre sin estos plugins; ésta y las que arrancaron antes no pueden convertir.
			$state['pause']          = true;
			$state['deactivated_at'] = microtime( true );
		}
		return $state;
	}

	private static function stage_deactivate( array $state, $deadline ) {
		// Sin preparación (tarea de 1.0.20, o preparación que nunca corrió) se vuelve a preparar antes.
		if ( empty( $state['platform_prepared'] ) && empty( $state['platform_failed'] ) ) {
			return self::next( $state, 'platform_prepare' );
		}
		$job = self::barrier_job( $state );
		if ( $job && ! empty( $job['released'] ) ) {
			// La red ya liberó: una petición anterior a la liberación aún tiene Element Pack cargado (V.3.2).
			if ( self::request_started() < (float) $job['released'] ) {
				$state['deactivated_at'] = (float) $job['released'];
				return self::hold( $state );
			}
			unset( $state['wait_network'] );
		}
		$status      = Retire::status();
		$site_active = array();
		foreach ( $status as $info ) {
			if ( ! empty( $info['site'] ) ) {
				$site_active[] = $info['label'];
			}
			if ( ! empty( $info['network'] ) ) {
				// En una migración de red, lo activado en toda la red lo retira la red (es información); fuera de ella, un aviso.
				if ( self::network_authorizes() ) {
					Report::site( 'core.network_scope', array( 'name' => $info['label'] ) );
				} else {
					$state = self::warn( $state, sprintf( '%s está activo en toda la red: lo desactiva la migración de la red (Red → Ajustes → Migrar a DIGITALÍSIMO Elements).', $info['label'] ) );
				}
			}
		}
		$capable = ! empty( $state['capable'] ) || self::network_authorizes();
		if ( ! $capable ) {
			if ( $site_active ) {
				$state = self::warn( $state, sprintf( 'Quien lanzó la migración no puede desactivar plugins, así que siguen activos: %s.', implode( ', ', $site_active ) ) );
			}
			return self::next( $state, 'platform_convert' );
		}
		$gate = self::ep_gate( $state );
		// Elementor Pro o PRO Elements activos en este sitio: mientras sigan cargados, el Theme Builder de Elements no
		// carga y la condición 5 nunca se cumple en esta petición (V.5.3). Se desactivan primero (sin el grupo de
		// Element Pack) y la etapa se repite en la petición siguiente, ya con el Theme Builder; antes, la tarea cerraba
		// con Element Pack activo y sin convertir, y había que volver a pulsar Migrar (Playground P2 con PRO=1, frente J).
		if ( 'tb.no_builder' === $gate && empty( $state['pro_first'] ) ) {
			$pro_active = false;
			foreach ( Retire::status() as $info ) {
				$pro_active = $pro_active || ( 'pro' === $info['group'] && ! empty( $info['site'] ) );
			}
			if ( $pro_active ) {
				$state['pro_first'] = true;
				$state              = self::retire_site( $state, array( 'element-pack' ) );
				if ( ! empty( $state['deactivated_at'] ) ) {
					return self::hold( $state );
				}
			}
		}
		if ( 'tb.plugin_inactive' === $gate ) {
			// No había Element Pack activo: las copias se quedan en borrador y lo demás se desactiva.
			if ( self::has( 'Platform\\Registry', 'all' ) && Platform\Registry::all( 'tb' ) ) {
				Report::site( 'tb.plugin_inactive' );
			}
			$gate = null;
			$skip = array();
		} elseif ( null === $gate ) {
			$skip = array();
			// Sólo se permite publicar si tras esta etapa Element Pack deja de estar en el sitio: activado en toda la red
			// fuera de una migración de red, lo retira la red y las copias siguen en borrador hasta entonces.
			if ( ( $job || ! Retire::group_active( 'element-pack', 'network' ) ) && ( Retire::group_active( 'element-pack', 'any' ) || ! Translator::element_pack_active() ) ) {
				$state = self::before_ep_retire( $state );
			}
		} else {
			// Quedaría un hueco: el grupo de Element Pack no cae (sí los demás) y el sitio sigue como estaba. Con una
			// plantilla bloqueante, Theme_Builder::blocked() ya informó cada una con su detalle.
			if ( 'tb.blocked_retire' !== $gate || ! self::has( 'Platform\\Theme_Builder', 'blocked' ) ) {
				Report::site( $gate );
			}
			$skip             = array( 'element-pack' );
			$state['ep_kept'] = true;
		}
		if ( $job && empty( $job['released'] ) ) {
			// Barrera de red: el sitio desactiva lo suyo (salvo lo que dependa de la red) y espera la liberación.
			$state                   = self::retire_site( $state, $skip );
			$state['barrier_ready']  = null === $gate;
			$state['barrier_blocked'] = in_array( $gate, array( 'tb.blocked_retire', 'tb.conflict', 'tb.no_builder' ), true ) ? $gate : '';
			$state['wait_network']   = true;
			$state                   = self::hold( $state );
			// Un sitio que espera puede liberar la barrera, como mucho cada minuto (con la autorización de la red).
			if ( ! empty( $job['capable'] ) && time() - (int) ( $job['checked'] ?? 0 ) >= 60 ) {
				self::save( $state );
				self::network_release();
			}
			return $state;
		}
		if ( $site_active ) {
			$state = self::retire_site( $state, $skip );
		}
		return self::next( $state, 'platform_convert' );
	}

	/**
	 * Tarea de red con barrera que incluye este sitio (V.6), o null. Sólo dentro de una tarea de red: «Migrar» de un
	 * sitio con Element Pack activado en toda la red sigue como siempre (aviso). Un sitio de la tarea sin nada que
	 * preparar también espera (para convertir después, sin Element Pack), pero no retiene la liberación.
	 */
	private static function barrier_job( array $state ) {
		if ( ! is_multisite() || empty( $state['network'] ) ) {
			return null;
		}
		$job = self::network_state();
		if ( ! $job || ! empty( $job['done'] ) || empty( $job['barrier'] ) || (int) $job['started'] !== (int) $state['network'] ) {
			return null;
		}
		return in_array( self::blog(), array_map( 'intval', (array) ( $job['sites'] ?? array() ) ), true ) ? $job : null;
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
				$state = self::warn( $state, 'Element Pack sigue activo (en toda la red, sin permiso para desactivarlo aquí, o porque sus plantillas aún no tienen copia publicable): su contenido no se convirtió y se sigue viendo con Element Pack. Cuando esté desactivado, vuelve a pulsar Migrar.' );
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

	/** Etapa en pausa por un proceso viejo: se repite en la siguiente petición. */
	private static function wait( array $state ) {
		$state['pause'] = true;
		return $state;
	}

	/**
	 * Copias del Theme Builder (registro `tb`), antes que nada: se convierten y su huella pasa al registro (C1-28). Una
	 * tarea de 1.0.20 que llega aquí sin copias las prepara ahora (V.3.4) y las publica en la misma petición.
	 */
	private static function stage_platform_convert( array $state, $deadline ) {
		$can = self::can_convert( $state );
		if ( 'pause' === $can ) {
			return self::wait( $state );
		}
		if ( true !== $can ) {
			return self::next( $state, 'recover' );
		}
		$registry = self::has( 'Platform\\Registry', 'all' );
		if ( ! empty( $state['late'] ) && empty( $state['late_prepared'] ) ) {
			// Preparación tardía: EP ya no está; las copias nacen, se permiten (si EP figuraba activo) y se convierten.
			if ( ! $registry || ! Platform\Registry::all( 'tb' ) ) {
				list( $state, $complete ) = self::run_steps( $state, array( 'Theme_Builder' ), 'prepare', $deadline );
				if ( ! $complete ) {
					return self::progressed( $state );
				}
				if ( $registry && Platform\Registry::all( 'tb' ) ) {
					Report::site( 'tb.late' );
				}
			}
			if ( self::ep_was_active( $state ) ) {
				self::platform_call( 'Publisher', 'allow', array( true ) );
				self::platform_call( 'Publisher', 'sync' );
			}
			$state['late_prepared']     = true;
			$state['platform_prepared'] = true;
		}
		$ids = array();
		foreach ( $registry ? Platform\Registry::all( 'tb' ) : array() as $row ) {
			if ( (int) $row['dest'] > 0 && ! in_array( $row['state'], array( 'reverted', 'failed' ), true ) ) {
				$ids[] = (int) $row['dest'];
			}
		}
		sort( $ids );
		list( $state, $complete ) = self::loop( $state, $ids, $deadline, static function ( $post_id, array &$state ) {
			$result = Migration::convert_document( $post_id );
			if ( ! empty( $result['native'] ) || ! empty( $result['page'] ) ) {
				++$state['counts']['copies'];
			}
			$found = Platform\Registry::by_dest( 'tb', (int) $post_id );
			if ( $found && self::has( 'Platform\\Documents', 'hash' ) ) {
				$found[1]['hash'] = Platform\Documents::hash( (int) $post_id );
				Platform\Registry::put( 'tb', $found[0], $found[1] );
			}
		} );
		return $complete ? self::next( $state, 'recover' ) : $state;
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
				return self::progressed( $state );
			}
		}
		return self::next( $state, 'convert' );
	}

	/** Documentos de la etapa de conversión: con contenido heredado o con ajustes de página heredados (V.9). */
	private static function documents_to_convert() {
		return method_exists( __NAMESPACE__ . '\\Migration', 'all_documents' ) ? Migration::all_documents() : Migration::documents();
	}

	private static function stage_convert( array $state, $deadline ) {
		$can = self::can_convert( $state );
		if ( 'pause' === $can ) {
			return self::wait( $state );
		}
		if ( true !== $can ) {
			// Sin convertir Element Pack tampoco se tocan los alias: una página sobrescrita por una conversión antigua
			// dejaría de reconocerse, y se hará todo junto al volver a pulsar Migrar.
			return self::next( $state, 'report' );
		}
		self::dropped();
		$ids            = self::ids( 'convert', array( __CLASS__, 'documents_to_convert' ) );
		$state['total'] = count( $ids );
		// Con las extensiones de F8 (frente C), los descartes los informa su convertidor (`ext.dropped.*`) y la etapa
		// `report` relee las copias por trozos (V.9, V.3.5); sin ellas, como en 1.0.20 (Dropped).
		$legacy_dropped = ! self::has( 'Effects\\Dispatcher', 'element' );
		list( $state, $complete ) = self::loop( $state, $ids, $deadline, static function ( $post_id, array &$state ) use ( $legacy_dropped ) {
			// La conversión retira las claves heredadas y el informe las lee de la copia original: sólo se anota antes lo
			// que una copia guardada por una conversión anterior no recoge.
			if ( $legacy_dropped ) {
				$found = Dropped::uncovered( $post_id );
				if ( $found ) {
					$state['dropped'] = Dropped::add( (array) ( $state['dropped'] ?? array() ), $post_id, $found );
				}
			} elseif ( method_exists( __NAMESPACE__ . '\\Dropped', 'report' ) ) {
				// Los descartes al informe de F8 antes de convertir (lo que la conversión retira ya no se vería).
				Dropped::report( $post_id );
			}
			$result = Migration::convert_document( $post_id );
			if ( ! empty( $result['native'] ) ) {
				++$state['counts']['converted'];
				$state['counts']['elements'] += (int) $result['native'];
			}
			if ( ! empty( $result['page'] ) ) {
				++$state['counts']['pages'];
			}
		} );
		if ( ! $complete ) {
			return $state;
		}
		$state = self::next( $state, 'revisions' );
		$state['total'] = count( self::ids( 'revisions', static function () use ( $state ) {
			return self::revisions_to_convert( $state );
		} ) );
		return $state;
	}

	private static function revisions_to_convert( array $state ) {
		return array_values( array_diff( Migration::legacy_revisions(), (array) $state['keep_revisions'] ) );
	}

	private static function stage_revisions( array $state, $deadline ) {
		$ids = self::ids( 'revisions', static function () use ( $state ) {
			return self::revisions_to_convert( $state );
		} );
		list( $state, $complete ) = self::loop( $state, $ids, $deadline, static function ( $post_id, array &$state ) {
			$result = Migration::convert_document( $post_id );
			if ( ! empty( $result['native'] ) ) {
				++$state['counts']['revisions'];
			}
		} );
		return $complete ? self::next( $state, 'markers' ) : $state;
	}

	/**
	 * Marcadores de K con Element Pack o Prime Slider dentro (V.12): la etapa es de Markers (I), que devuelve el estado
	 * y pone `markers_done` al terminar. Dos caídas seguidas sin volver la saltan.
	 */
	private static function stage_markers( array $state, $deadline ) {
		if ( ! self::has( 'Markers', 'stage' ) ) {
			return self::next( $state, 'platform' );
		}
		$state['markers_attempts'] = (int) ( $state['markers_attempts'] ?? 0 ) + 1;
		if ( $state['markers_attempts'] > self::MAX_ATTEMPTS ) {
			unset( $state['markers_attempts'], $state['markers_cursor'], $state['markers_done'] );
			Report::site( 'core.stage_skipped', array( 'name' => self::LABELS['markers'] ) );
			return self::next( $state, 'platform' );
		}
		self::save( $state );
		$state = (array) Markers::stage( $state, (float) $deadline );
		unset( $state['markers_attempts'] );
		if ( ! empty( $state['markers_done'] ) ) {
			unset( $state['markers_done'], $state['markers_cursor'] );
			return self::next( $state, 'platform' );
		}
		return self::progressed( $state );
	}

	/** Pasos de plataforma tras convertir el contenido (Element Pack ya no está cargado). */
	private static function stage_platform( array $state, $deadline ) {
		$can = self::can_convert( $state );
		if ( 'pause' === $can ) {
			return self::wait( $state );
		}
		if ( true !== $can ) {
			return self::next( $state, 'references' );
		}
		$steps = self::PLATFORM_STEPS;
		if ( empty( $state['ep_was_active'] ) ) {
			// Las muestras de variación sólo se activan si Element Pack estaba activo al empezar (V.11).
			$steps = array_values( array_diff( $steps, array( 'Swatches' ) ) );
		}
		list( $state, $complete ) = self::run_steps( $state, $steps, 'finish', $deadline );
		if ( ! $complete ) {
			return self::progressed( $state );
		}
		return self::next( $state, 'references' );
	}

	/** Qué alias no pueden convertirse en este sitio (CSS que nombra sus clases) y cuántos documentos hay. */
	private static function stage_references( array $state, $deadline ) {
		if ( Aliases::unreadable() ) {
			$state = self::warn( $state, 'No se pudieron leer los widgets fusionados de DIGITALÍSIMO Elements en esta petición: los alias no se actualizaron. Vuelve a pulsar Migrar.' );
			return self::next( $state, 'pro', self::pro_total() );
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
		return $complete && count( $batch ) < self::BATCH ? self::next( $state, 'pro', self::pro_total() ) : $state;
	}

	/** ¿Puede convertir los widgets de PRO esta petición? (Pro cargado y el núcleo de conversión de Elements) */
	private static function pro_ready() {
		return self::has( 'Pro', 'convert_document' ) && Pro::available();
	}

	/** Documentos con widgets de PRO por convertir, para el avance de la etapa. */
	private static function pro_total() {
		return self::pro_ready() ? count( Pro::documents() ) : 0;
	}

	/**
	 * Widgets de PRO con widget propio publicado (Pro, docs/PRO_WIDGETS_OBJECTIVE.md), por lotes como los alias. Sin el
	 * núcleo de conversión de DIGITALÍSIMO Elements no se toca nada (el análisis pide actualizarlo); lo que no tiene
	 * mapa listo se queda como está y se sigue viendo con el widget de PRO registrado.
	 */
	private static function stage_pro( array $state, $deadline ) {
		if ( ! self::pro_ready() ) {
			return self::next( $state, 'retire_late' );
		}
		$batch = Pro::documents( (int) $state['cursor'], self::BATCH );
		list( $state, $complete ) = self::loop( $state, $batch, $deadline, static function ( $post_id, array &$state ) {
			$result = Pro::convert_document( $post_id );
			if ( ! empty( $result['converted'] ) ) {
				$state['counts']['pro']          = (int) ( $state['counts']['pro'] ?? 0 ) + 1;
				$state['counts']['pro_elements'] = (int) ( $state['counts']['pro_elements'] ?? 0 ) + (int) $result['converted'];
			}
			if ( ! empty( $result['css'] ) ) {
				$state['counts']['pro_css'] = (int) ( $state['counts']['pro_css'] ?? 0 ) + 1;
			}
		} );
		if ( ! $complete || count( $batch ) >= self::BATCH ) {
			return $state;
		}
		return self::has( 'Pro', 'css_documents' ) ? self::next( $state, 'pro_css', count( Pro::css_documents() ) ) : self::next( $state, 'retire_late' );
	}

	/**
	 * CSS que nombra los widgets de PRO desde fuera (contenedores, página, kit y Personalizador; Pro): en los documentos
	 * que no tenían widgets de PRO por convertir (o que se convirtieron con una versión anterior) y, al final, el kit
	 * activo y el CSS adicional. Cada regla gana su copia con las clases del widget propio; `--revert` lo deshace.
	 */
	private static function stage_pro_css( array $state, $deadline ) {
		if ( ! self::pro_ready() || ! self::has( 'Pro', 'css_documents' ) ) {
			return self::next( $state, 'retire_late' );
		}
		$batch = Pro::css_documents( (int) $state['cursor'], self::BATCH );
		list( $state, $complete ) = self::loop( $state, $batch, $deadline, static function ( $post_id, array &$state ) {
			if ( Pro::convert_css_document( $post_id ) ) {
				$state['counts']['pro_css'] = (int) ( $state['counts']['pro_css'] ?? 0 ) + 1;
			}
		} );
		if ( ! $complete || count( $batch ) >= self::BATCH ) {
			return $state;
		}
		$state['counts']['pro_css'] = (int) ( $state['counts']['pro_css'] ?? 0 ) + Pro::convert_site_css();
		return self::next( $state, 'retire_late' );
	}

	/** Documentos (no revisiones) con widgets de Prime Slider, para la retirada tardía. */
	public static function prime_slider_documents() {
		return method_exists( __NAMESPACE__ . '\\Migration', 'documents_with' ) ? Migration::documents_with( array( '"widgetType":"prime-slider-' ) ) : array();
	}

	/**
	 * Prime Slider se retira al final (V.10.6), sólo si no queda ningún widget suyo fuera de un marcador ni ningún
	 * marcador suyo sin destino. Lo activado en toda la red lo retira la red (network_finish()).
	 */
	private static function stage_retire_late( array $state, $deadline ) {
		if ( ! Retire::group_installed( 'prime-slider' ) ) {
			return self::next( $state, 'report' );
		}
		$ids = self::ids( 'retire_late', array( __CLASS__, 'prime_slider_documents' ) );
		list( $state, $complete ) = self::loop( $state, $ids, $deadline, static function ( $post_id, array &$state ) {
			list( $loose, $blocked ) = self::prime_slider_left( (int) $post_id );
			if ( $loose || $blocked ) {
				$state['late_count'] = (int) ( $state['late_count'] ?? 0 ) + $loose + $blocked;
				Report::add( (int) $post_id, 'core.late_kept', array( 'count' => $loose + $blocked ) );
				if ( $blocked ) {
					Report::add( (int) $post_id, 'ps.blocked.no_target', array( 'count' => $blocked ) );
				}
			}
		} );
		if ( ! $complete ) {
			return $state;
		}
		if ( empty( $state['late_count'] ) ) {
			unset( $state['late_kept'] );
			if ( Retire::group_active( 'prime-slider', 'site' ) && ( ! empty( $state['capable'] ) || self::network_authorizes() ) ) {
				$result = Retire::deactivate( false, true );
				if ( $result['deactivated'] ) {
					$state['counts']['deactivated'] = array_values( array_unique( array_merge( (array) $state['counts']['deactivated'], array_values( $result['deactivated'] ) ) ) );
					Report::site( 'core.late_retired' );
				}
			}
		} else {
			$state['late_kept'] = true;
			Report::site( 'core.late_kept', array( 'count' => (int) $state['late_count'] ) );
		}
		return self::next( $state, 'report' );
	}

	/**
	 * Widgets de Prime Slider de un documento: los que siguen sueltos (fuera de un marcador) y los marcadores con Prime
	 * Slider sin destino según Markers (los descartes declarados no cuentan).
	 *
	 * @return array{0:int,1:int}
	 */
	public static function prime_slider_left( int $post_id ) {
		$raw  = get_metadata( 'post', $post_id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) ) {
			return array( 0, 0 );
		}
		$loose   = 0;
		$markers = array();
		$walk    = static function ( array $elements ) use ( &$walk, &$loose, &$markers ) {
			foreach ( $elements as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				$type = (string) ( $element['widgetType'] ?? '' );
				if ( self::MARKER_WIDGET === $type ) {
					if ( Translator::is_prime_slider_id( Migration::marker_type( $element ) ) ) {
						$markers[] = $element;
					}
					continue;
				}
				if ( 'widget' === ( $element['elType'] ?? '' ) && Translator::is_prime_slider_id( $type ) && ! self::prime_slider_declared( $type ) ) {
					++$loose;
				}
				$walk( (array) ( $element['elements'] ?? array() ) );
			}
		};
		$walk( $data );
		$blocked = 0;
		if ( $markers && self::has( 'Markers', 'classify' ) ) {
			$groups  = (array) Markers::classify( $markers );
			$blocked = count( array_filter( (array) ( $groups['blocked'] ?? array() ), static function ( $type ) {
				return Translator::is_prime_slider_id( preg_replace( '#^widget/#', '', (string) $type ) );
			} ) );
		}
		return array( $loose, $blocked );
	}

	/**
	 * ¿Es un descarte declarado de Prime Slider (mapa `DESCARTAR`, o familia dinámica en estado `declared`, como
	 * event-calendar sin The Events Calendar)? No impide retirar Prime Slider (V.10.6).
	 */
	private static function prime_slider_declared( $type ) {
		if ( self::has( 'Prime_Slider\\Maps', 'get' ) ) {
			$map = Prime_Slider\Maps::get( (string) $type );
			if ( is_array( $map ) && in_array( strtoupper( (string) ( $map['decision'] ?? '' ) ), array( 'DESCARTAR', 'DISCARD' ), true ) ) {
				return true;
			}
		}
		return self::has( 'Prime_Slider\\Dynamic', 'state' ) && 'declared' === Prime_Slider\Dynamic::state( (string) $type );
	}

	/**
	 * El informe de lo convertido en pasadas anteriores (V.3.5): volver a pulsar Migrar no borra nada. Se relee de la
	 * copia original de cada documento con la descripción de cada frente, por trozos.
	 */
	private static function stage_report( array $state, $deadline ) {
		$ids     = self::ids( 'report', array( __CLASS__, 'report_documents' ) );
		$started = (int) $state['started'];
		$dropped = method_exists( __NAMESPACE__ . '\\Dropped', 'report' ) && self::has( 'Effects\\Dispatcher', 'element' );
		list( $state, $complete ) = self::loop( $state, $ids, $deadline, static function ( $post_id ) use ( $started, $dropped ) {
			// Descartes y claves ambiguas de cada documento (también de los no convertidos), de la copia y de lo actual.
			if ( $dropped ) {
				Dropped::report( (int) $post_id );
			}
			$mark = Migration::f8_mark( $post_id );
			if ( $mark && (int) ( $mark['time'] ?? 0 ) >= $started ) {
				// Convertido en esta pasada: lo suyo ya está en el informe.
				return;
			}
			self::describe_backup( (int) $post_id );
		} );
		return $complete ? self::next( $state, 'finish' ) : $state;
	}

	/** Documentos de la etapa `report`: con copia original y, con las extensiones de F8, los de Dropped::documents(). */
	public static function report_documents() {
		$ids = Migration::backups();
		if ( method_exists( __NAMESPACE__ . '\\Dropped', 'documents' ) && self::has( 'Effects\\Dispatcher', 'element' ) ) {
			$ids = array_merge( $ids, (array) Dropped::documents( 0, 0 ) );
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		sort( $ids );
		return $ids;
	}

	/** Describe la copia original de un documento (sin escribir nada) y lo añade al informe. */
	public static function describe_backup( int $post_id ) {
		$backup = json_decode( (string) get_metadata( 'post', $post_id, Migration::BACKUP, true ), true );
		$data   = is_array( $backup ) && isset( $backup['data'] ) && is_string( $backup['data'] ) ? json_decode( $backup['data'], true ) : null;
		if ( ! is_array( $data ) ) {
			return;
		}
		Context::begin( $post_id, 'analysis' );
		try {
			foreach ( self::describe( $data ) as $code => $count ) {
				Report::add( $post_id, (string) $code, array( 'count' => (int) $count ) );
			}
			// Widgets de PRO convertidos en una pasada anterior: sólo si el documento lleva hoy widgets convertidos desde PRO.
			if ( self::has( 'Pro', 'describe_backup' ) ) {
				Pro::describe_backup( $post_id, $data );
			}
		} finally {
			Context::end();
		}
	}

	/**
	 * Descripción estática de unos elementos (código => recuento) por las clases de los frentes que la ofrecen:
	 * Effects\Dispatcher::describe() y Prime_Slider\Converter::describe() de cada widget de Prime Slider.
	 *
	 * @return array<string,int>
	 */
	public static function describe( array $elements ) {
		$out = array();
		if ( self::has( 'Effects\\Dispatcher', 'describe' ) ) {
			foreach ( (array) Effects\Dispatcher::describe( $elements ) as $code => $count ) {
				$out[ (string) $code ] = (int) ( $out[ (string) $code ] ?? 0 ) + (int) $count;
			}
		}
		foreach ( array( 'Platform\\Icons', 'Platform\\Dynamic_Tags' ) as $class ) {
			if ( self::has( $class, 'describe' ) ) {
				foreach ( (array) call_user_func( array( __NAMESPACE__ . '\\' . $class, 'describe' ), $elements ) as $code => $count ) {
					$out[ (string) $code ] = (int) ( $out[ (string) $code ] ?? 0 ) + (int) $count;
				}
			}
		}
		if ( self::has( 'Prime_Slider\\Converter', 'describe' ) ) {
			$walk = static function ( array $list ) use ( &$walk, &$out ) {
				foreach ( $list as $element ) {
					if ( ! is_array( $element ) ) {
						continue;
					}
					if ( 'widget' === ( $element['elType'] ?? '' ) && Translator::is_prime_slider_id( (string) ( $element['widgetType'] ?? '' ) ) ) {
						foreach ( (array) Prime_Slider\Converter::describe( $element ) as $code => $count ) {
							$out[ (string) $code ] = (int) ( $out[ (string) $code ] ?? 0 ) + (int) $count;
						}
					}
					$walk( (array) ( $element['elements'] ?? array() ) );
				}
			};
			$walk( $elements );
		}
		return $out;
	}

	private static function stage_finish( array $state, $deadline ) {
		try {
			if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		} catch ( \Throwable $e ) {
			$state = self::warn( $state, 'No se pudo vaciar la caché del constructor (' . $e->getMessage() . '): hazlo en DIGITALÍSIMO Elements → Herramientas → Vaciar archivos y datos.' );
		}
		try {
			$report = self::build_report( $state );
		} catch ( \Throwable $e ) {
			$state  = self::warn( $state, 'El informe quedó incompleto: ' . $e->getMessage() );
			$report = array();
		}
		// Lo anotado ya está en el informe, con título y enlace.
		unset( $state['dropped'] );
		return self::close( $state, $report );
	}

	/** Da la tarea por terminada con el informe indicado. */
	private static function close( array $state, array $report ) {
		$state['report']   = $report;
		$state['done']     = true;
		$state['pause']    = false;
		$state['finished'] = time();
		unset( $state['platform'], $state['wait_network'] );
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
		foreach ( $failed as $post_id ) {
			Report::add( (int) $post_id, 'core.document_failed' );
		}
		$plugins = array();
		$others  = is_multisite() ? Retire::network_summary() : array();
		foreach ( Retire::status() as $file => $info ) {
			$plugins[ $file ] = array(
				'label'        => $info['label'],
				'site'         => $info['site'],
				'network'      => $info['network'],
				'element_pack' => $info['element_pack'],
				'late'         => ! empty( $info['late'] ),
				// En una red, cuántos sitios lo tienen activo (este incluido).
				'sites'        => (int) ( $others[ $file ]['sites'] ?? 0 ),
			);
		}
		$blocked = array_keys( (array) $state['blocked'] );
		$map     = Aliases::map();
		// Extensiones sin equivalente: lo que guarda cada documento y su copia original (claves sin prefijo heredado,
		// ajustes de la página, lo retirado al convertir) más lo anotado que esa copia no recoge.
		self::dropped();
		// Sin las extensiones de F8, el listado de 1.0.20; con ellas, los descartes van en el informe de F8 (sin releer
		// aquí todas las copias, V.3.5).
		$dropped = self::has( 'Effects\\Dispatcher', 'element' ) ? array( 'docs' => array(), 'omitted' => 0 ) : Dropped::merge( Dropped::analyze(), (array) ( $state['dropped'] ?? array() ) );
		$kept    = self::has( 'Effects\\Dispatcher', 'element' ) ? array() : Dropped::kept();
		Report::flush();
		$f8 = Report::stored();
		return array(
			'convertible'     => count( $groups['convertible'] ),
			'blocked'         => self::documents_info( $groups['blocked'] ),
			'unsupported'     => $unsupported,
			'missing_figures' => (array) $figures['missing'],
			'review'          => self::documents_info( (array) $state['review'] ),
			'failed'          => self::documents_info( $failed ),
			'aliases_left'    => $map ? count( Aliases::documents( array_values( array_diff( array_keys( $map ), $blocked ) ) ) ) : count( Aliases::documents_with( Aliases::KNOWN ) ),
			'alias_blocked'   => (array) $state['blocked'],
			// Documentos con widgets de PRO que tienen widget propio y siguen sin convertir (fallaron o sin destino).
			'pro_left'        => self::pro_ready() ? count( Pro::documents() ) : 0,
			'alias_targets'   => array_map( static function ( $spec ) {
				return array( 'target' => $spec['target'], 'prefix' => $spec['prefix'], 'target_prefix' => $spec['target_prefix'] );
			}, array_intersect_key( $map, (array) $state['blocked'] ) ),
			// Element Pack aún hace falta (sus ajustes y figuras) mientras quede contenido suyo por convertir.
			'ep_needed'       => count( $groups['convertible'] ) > 0 || (bool) array_intersect( $failed, Migration::documents() ) || ! empty( $state['ep_kept'] ),
			'late_kept'       => ! empty( $state['late_kept'] ),
			'plugins'         => $plugins,
			'dropped'         => Dropped::describe( $dropped ),
			'dropped_omitted' => (int) $dropped['omitted'],
			// Plugins de la familia que la migración no desactiva: con algo para una persona, y los que no.
			'kept'            => array_values( array_filter( $kept, array( Dropped::class, 'kept_pending' ) ) ),
			'kept_idle'       => array_values( array_filter( $kept, static function ( $row ) {
				return ! Dropped::kept_pending( $row );
			} ) ),
			// Resumen del informe de F8 (el detalle sigue en su opción): marca también que el informe es de F8.
			'f8'              => array( 'v' => Report::VERSION ) + Report::summary( $f8 ),
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

	/**
	 * ¿Quedó algo para una persona en el informe de una tarea terminada? Con un informe de F8, además de lo de siempre,
	 * cuenta su sección «Pendiente de revisar»; un informe de 1.0.20 (sin `f8`) usa la regla anterior.
	 */
	public static function has_pending( array $state ) {
		$r = (array) ( $state['report'] ?? array() );
		if ( ! empty( $r['f8']['pending'] ) ) {
			return true;
		}
		// Los efectos heredados sin equivalente y los plugins de la familia activos son información: nadie podría hacerlos
		// desaparecer. Sólo cuentan los widgets de Prime Slider que hoy no se ven.
		foreach ( array( 'blocked', 'unsupported', 'review', 'failed', 'alias_blocked', 'missing_figures' ) as $key ) {
			if ( ! empty( $r[ $key ] ) ) {
				return true;
			}
		}
		if ( ! empty( $r['kept'] ) ) {
			self::dropped();
			foreach ( (array) $r['kept'] as $row ) {
				if ( is_array( $row ) && Dropped::kept_hidden( $row ) ) {
					return true;
				}
			}
		}
		foreach ( (array) ( $r['plugins'] ?? array() ) as $plugin ) {
			// Prime Slider activado en toda la red lo retira la red al final (network_finish()): no es del sitio.
			if ( ! empty( $plugin['late'] ) && ! empty( $plugin['network'] ) && empty( $plugin['site'] ) && empty( $r['late_kept'] ) ) {
				continue;
			}
			if ( ! empty( $plugin['site'] ) || ! empty( $plugin['network'] ) ) {
				return true;
			}
		}
		return ! empty( $r['convertible'] ) || ! empty( $r['aliases_left'] ) || ! empty( $r['pro_left'] ) || ! empty( $state['warnings'] );
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

	/** ¿Sitio archivado, marcado como spam o borrado? (No entra en la tarea ni en la barrera.) */
	private static function site_excluded( $site_id ) {
		$site = function_exists( 'get_site' ) ? get_site( (int) $site_id ) : null;
		return is_object( $site ) && ( ! empty( $site->archived ) || ! empty( $site->spam ) || ! empty( $site->deleted ) );
	}

	/**
	 * ¿Tiene el sitio en curso algo de Element Pack que preparar antes de la liberación? Plantillas del Theme Builder
	 * activas (`_bdthemes_builder_%`) o CSS global de Element Pack o Prime Slider. Sólo lectura.
	 */
	private static function has_preparation() {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_bdthemes_builder_' ) . '%', $wpdb->esc_like( 'ep_custom_css' ) . '%', $wpdb->esc_like( 'ps_custom_css' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $row ) {
			$value = maybe_unserialize( $row->option_value );
			if ( is_array( $value ) ? (bool) array_filter( $value ) : ( '' !== trim( (string) $value ) && '0' !== (string) $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Arranca la migración de red (o devuelve la que está en marcha). Si DIGITALÍSIMO Elements está activado en toda la
	 * red (si no, algún sitio se quedaría sin constructor): en cada sitio con él desactiva Elementor Pro y PRO Elements y
	 * después lo retirable activado en toda la red, salvo Element Pack (cae en la liberación de la barrera, V.6) y Prime
	 * Slider (al final). Encola en cada sitio con DIGITALÍSIMO Elements su tarea, autorizada a desactivar plugins por
	 * quien lanzó la de red. Los sitios archivados, marcados como spam o borrados se listan y no se tocan.
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
		$reason = self::requirements();
		if ( null !== $reason ) {
			return array( 'idle' => true, 'error' => $reason );
		}
		if ( ! self::network_lock() ) {
			return self::network_summary();
		}
		try {
			$capable          = null === $capable ? ( function_exists( 'current_user_can' ) && current_user_can( 'manage_network_plugins' ) ) : (bool) $capable;
			$job              = array(
				'version'       => defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '',
				'started'       => time(),
				'done'          => false,
				'sites'         => array(),
				'skipped'       => array(),
				'excluded'      => array(),
				'deactivated'   => array(),
				'warnings'      => array(),
				'capable'       => $capable,
				'user'          => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'barrier'       => false,
				'barrier_sites' => array(),
				'released'      => 0,
				'unprepared'    => array(),
				'checked'       => 0,
			);
			$file             = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) : '';
			$elements_network = '' !== $file && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $file ] );
			$retire_network   = $elements_network && $capable;
			if ( ! $elements_network ) {
				$job['warnings'][] = 'DIGITALÍSIMO Elements no está activado en toda la red: los plugins activados en toda la red no se desactivaron, porque los sitios sin DIGITALÍSIMO Elements se quedarían sin ellos. Actívalo en toda la red y vuelve a pulsar Migrar toda la red.';
			} elseif ( ! $capable ) {
				$job['warnings'][] = 'Quien lanzó la migración no puede gestionar los plugins de la red: los activados en toda la red siguen activos.';
			}
			// Barrera: sólo con Element Pack activado en toda la red y quien puede retirarlo.
			$job['barrier'] = $retire_network && Retire::group_active( 'element-pack', 'network' );
			$conflicts      = array();
			foreach ( Retire::sites() as $site_id ) {
				if ( self::site_excluded( $site_id ) ) {
					$job['excluded'][] = (int) $site_id;
					continue;
				}
				if ( ! self::elements_active_on( $site_id ) ) {
					$job['skipped'][] = $site_id;
					continue;
				}
				$job['sites'][] = $site_id;
				switch_to_blog( $site_id );
				try {
					$conflicts[ $site_id ] = $retire_network ? Retire::deactivate_conflicts() : array();
					if ( $job['barrier'] && self::has_preparation() ) {
						$job['barrier_sites'][] = (int) $site_id;
					}
				} finally {
					restore_current_blog();
				}
			}
			if ( $retire_network ) {
				// Elementor Pro, PRO Elements y Elementor al inicio: nunca conviven con copias publicables (C1-6).
				$result             = Retire::deactivate( true, false, array( 'element-pack' ) );
				$job['deactivated'] = array_values( $result['deactivated'] );
				$job['warnings']    = array_merge( $job['warnings'], $result['warnings'] );
			}
			if ( $job['barrier'] && ! $job['barrier_sites'] ) {
				// Ningún sitio tiene nada que preparar: se libera ya.
				$job = self::release( $job );
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
								$state = self::fresh( $capable, $job['started'], $job['user'] );
							} else {
								$state['network'] = (int) $job['started'];
								$state['user']    = (int) $job['user'];
								if ( $capable && empty( $state['capable'] ) ) {
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
			$job['updated'] = time();
			update_site_option( self::NETWORK_JOB, $job );
			self::schedule_tick();
		} finally {
			self::network_unlock();
		}
		return self::network_summary();
	}

	/** Programa el evento de red en el WP-Cron del sitio principal (cada 5 minutos mientras dure la tarea de red). */
	private static function schedule_tick() {
		if ( ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}
		$main   = function_exists( 'get_main_site_id' ) ? (int) get_main_site_id() : 1;
		$switch = is_multisite() && $main !== self::blog();
		if ( $switch ) {
			switch_to_blog( $main );
		}
		try {
			if ( ! wp_next_scheduled( self::NETWORK_TICK ) ) {
				wp_schedule_single_event( time() + 300, self::NETWORK_TICK );
			}
		} finally {
			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	/** WP-Cron del sitio principal: libera la barrera y cierra la red sin pantalla; se reprograma hasta terminar. */
	public static function network_tick() {
		if ( ! is_multisite() ) {
			return;
		}
		self::load();
		self::network_release();
		self::network_finish();
		$job = self::network_state();
		if ( $job && empty( $job['done'] ) ) {
			self::schedule_tick();
		}
	}

	/**
	 * Libera la barrera de red (V.6), idempotente y bajo el cerrojo de red: con todos los sitios de la barrera listos y
	 * ninguno bloqueado, o al vencer el plazo (relativo al último avance, con un tope absoluto). Con un sitio bloqueado
	 * al vencer el plazo, la tarea de red se detiene (V.19.1 a).
	 *
	 * @return array la tarea de red (releída).
	 */
	public static function network_release(): array {
		if ( ! is_multisite() ) {
			return array();
		}
		$job = self::network_state();
		if ( ! $job || ! empty( $job['done'] ) || empty( $job['barrier'] ) || ! empty( $job['released'] ) ) {
			return (array) $job;
		}
		if ( ! self::network_lock() ) {
			return $job;
		}
		try {
			$job = (array) self::network_state();
			if ( ! empty( $job['done'] ) || ! empty( $job['released'] ) ) {
				return $job;
			}
			$ready   = array();
			$blocked = array();
			$pending = array();
			$last    = (int) ( $job['started'] ?? 0 );
			foreach ( (array) $job['barrier_sites'] as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					$state  = self::state();
					$report = self::report();
				} finally {
					restore_current_blog();
				}
				if ( $state && empty( $state['done'] ) ) {
					$last = max( $last, (int) ( $state['updated'] ?? 0 ) );
					if ( 'deactivate' === ( $state['stage'] ?? '' ) && ! empty( $state['wait_network'] ) ) {
						if ( ! empty( $state['barrier_blocked'] ) ) {
							$blocked[] = (int) $site_id;
						} elseif ( ! empty( $state['barrier_ready'] ) ) {
							$ready[] = (int) $site_id;
						} else {
							$pending[] = (int) $site_id;
						}
					} else {
						$pending[] = (int) $site_id;
					}
				} elseif ( $report && (int) ( $report['finished'] ?? 0 ) >= (int) $job['started'] ) {
					// Terminado: ya no necesita a Element Pack para su cabecera (o no la tenía).
					$ready[] = (int) $site_id;
				} else {
					$pending[] = (int) $site_id;
				}
			}
			$job['checked'] = time();
			$count          = max( 1, count( (array) $job['barrier_sites'] ) );
			$idle           = (int) apply_filters( 'digitalisimo_migration_barrier_idle', 900 );
			$max            = (int) apply_filters( 'digitalisimo_migration_barrier_max', max( 3600, 120 * $count ) );
			$expired        = time() - $last >= $idle || time() >= (int) $job['started'] + $max;
			if ( ! $pending && ! $blocked ) {
				$job = self::release( $job );
			} elseif ( $expired && ! $blocked ) {
				$job['unprepared'] = $pending;
				$job               = self::release( $job );
			} elseif ( $blocked && ( ! $pending || $expired ) ) {
				// V.19.1 (a), decisión del propietario: un sitio con una cabecera o un pie visibles sin copia publicable no
				// deja liberar. En cuanto no queda nadie más por preparar (o vence el plazo), la red se detiene con su
				// informe; Element Pack sigue en la red y ningún visitante pierde la cabecera. Al volver a pulsar, sigue.
				$job['done']     = true;
				$job['stopped']  = true;
				$job['blocked']  = $blocked;
				$job['finished'] = time();
				$job['warnings'][] = 'La migración de la red se detuvo: hay sitios con una cabecera o un pie de Element Pack visibles sin copia que pueda publicarse. Revisa su informe, corrígelo y vuelve a pulsar Migrar toda la red.';
			} else {
				$job['blocked'] = $blocked;
			}
			$job['updated'] = time();
			update_site_option( self::NETWORK_JOB, $job );
			return $job;
		} finally {
			self::network_unlock();
		}
	}

	/** Desactiva Element Pack en toda la red, en una escritura (llamar bajo el cerrojo de red). */
	private static function release( array $job ) {
		$result             = Retire::deactivate( true, false, array( 'pro', 'core' ) );
		$job['deactivated'] = array_values( array_unique( array_merge( (array) $job['deactivated'], array_values( $result['deactivated'] ) ) ) );
		$job['warnings']    = array_merge( (array) $job['warnings'], $result['warnings'] );
		$job['released']    = microtime( true );
		return $job;
	}

	/**
	 * Cierra la migración de red: con todos los sitios terminados y ninguno con widgets de Prime Slider por convertir,
	 * desactiva Prime Slider activado en toda la red; marca la tarea terminada. Bajo el cerrojo de red.
	 *
	 * @return array la tarea de red.
	 */
	public static function network_finish(): array {
		if ( ! is_multisite() ) {
			return array();
		}
		$summary = self::network_summary( false );
		$job     = self::network_state();
		if ( ! $job || ! empty( $job['done'] ) || empty( $summary['all_done'] ) ) {
			return (array) $job;
		}
		if ( ! self::network_lock() ) {
			return $job;
		}
		try {
			$job = (array) self::network_state();
			if ( ! empty( $job['done'] ) ) {
				return $job;
			}
			$late = false;
			foreach ( (array) ( $summary['status'] ?? array() ) as $row ) {
				$late = $late || ! empty( $row['late_kept'] );
			}
			if ( ! empty( $job['capable'] ) && Retire::group_active( 'prime-slider', 'network' ) ) {
				if ( $late ) {
					$job['warnings'][] = 'Prime Slider sigue activo en toda la red: algún sitio conserva widgets suyos sin convertir (su informe dice cuáles).';
				} else {
					$result             = Retire::deactivate( true, true );
					$job['deactivated'] = array_values( array_unique( array_merge( (array) $job['deactivated'], array_values( $result['deactivated'] ) ) ) );
				}
			}
			$job['done']     = true;
			$job['finished'] = time();
			update_site_option( self::NETWORK_JOB, $job );
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				$main   = function_exists( 'get_main_site_id' ) ? (int) get_main_site_id() : 1;
				$switch = $main !== self::blog();
				if ( $switch ) {
					switch_to_blog( $main );
				}
				wp_clear_scheduled_hook( self::NETWORK_TICK );
				if ( $switch ) {
					restore_current_blog();
				}
			}
			return $job;
		} finally {
			self::network_unlock();
		}
	}

	/**
	 * Estado de cada sitio de la migración de red (en marcha, esperando a la barrera, terminado o pendiente). Sólo lee;
	 * con todos terminados llama a network_finish(), que escribe `done` bajo el cerrojo de red.
	 */
	public static function network_summary( $finish = true ) {
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
					$row = array( 'status' => 'running', 'stage' => (string) $state['stage'], 'waiting' => ! empty( $state['wait_network'] ) && empty( $job['released'] ) );
				} elseif ( $report && (int) ( $report['finished'] ?? 0 ) >= (int) $job['started'] ) {
					$done = (array) ( $report['report'] ?? array() );
					$row  = array(
						'status'    => 'done',
						'counts'    => (array) $report['counts'],
						'pending'   => self::has_pending( $report ),
						'ep_needed' => ! empty( $done['ep_needed'] ),
						'late_kept' => ! empty( $done['late_kept'] ),
						// Lo que el informe del sitio lista como información: documentos con efectos heredados descartados
						// y plugins de la familia sin desactivar.
						'dropped'   => count( (array) ( $done['dropped'] ?? array() ) ) + (int) ( $done['dropped_omitted'] ?? 0 ),
						'kept'      => array_values( array_map( 'strval', array_column( (array) ( $done['kept'] ?? array() ), 'label' ) ) ),
						// Columnas de F8, leídas de lo guardado (V.3.5).
						'f8'        => (array) ( $done['f8'] ?? array() ),
					);
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
		$job['all_done'] = $all_done;
		if ( $finish && $all_done && empty( $job['done'] ) ) {
			$closed = self::network_finish();
			if ( ! empty( $closed['done'] ) ) {
				$job = array_merge( $job, $closed, array( 'status' => $job['status'], 'all_done' => true ) );
			}
		}
		return $job;
	}

	/** Sitios que la pantalla de red debe conducir: su admin-ajax.php y un nonce para la tarea de cada uno. */
	public static function network_pending_sites( $job ) {
		$out = array();
		foreach ( (array) ( $job['status'] ?? array() ) as $site_id => $row ) {
			if ( 'running' === $row['status'] ) {
				$out[] = array( 'id' => (int) $site_id, 'name' => $row['name'], 'url' => $row['url'], 'ajax' => $row['ajax'], 'nonce' => wp_create_nonce( self::AJAX . '_' . (int) $site_id ), 'waiting' => ! empty( $row['waiting'] ) );
			}
		}
		return $out;
	}

	/**
	 * Cerrojo de la red (C16): INSERT IGNORE con token en `options` del sitio principal; se roba a los NETWORK_STALE s
	 * sólo si nadie lo renovó entre la lectura y el robo. Nunca `add_option` ni sitemeta.
	 */
	public static function network_lock(): bool {
		global $wpdb;
		$table = $wpdb->get_blog_prefix( function_exists( 'get_main_site_id' ) ? (int) get_main_site_id() : 1 ) . 'options';
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false ) : bin2hex( random_bytes( 10 ) );
		$value = time() . '|' . $token;
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", self::NETWORK_LOCK, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( 1 !== $taken ) {
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", self::NETWORK_LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( '' !== $held && time() - (int) $held < self::NETWORK_STALE ) {
				return false;
			}
			if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, self::NETWORK_LOCK, $held ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return false;
			}
		}
		self::$network_token = $token;
		return true;
	}

	/** Suelta el cerrojo de red propio; nunca el de otra petición. */
	public static function network_unlock(): void {
		global $wpdb;
		$token               = self::$network_token;
		self::$network_token = '';
		if ( '' === $token ) {
			return;
		}
		$table = $wpdb->get_blog_prefix( function_exists( 'get_main_site_id' ) ? (int) get_main_site_id() : 1 ) . 'options';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name = %s AND option_value LIKE %s", self::NETWORK_LOCK, '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/* ------------------------------------------------------------------ Reversión (V.8) */

	/**
	 * `--revert`: primero la plataforma (pasos en orden inverso y REVERT_EXTRA), después los documentos, sin tocar
	 * nunca lo que creó la migración. Exige el grupo de Element Pack activo en algún ámbito (si no, el sitio se quedaría
	 * sin cabecera) salvo con $force. Lo editado después de migrar se omite y se informa, salvo con $force.
	 *
	 * @return array{refused:bool, documents:int, skipped:int, codes:array}
	 */
	public static function revert( bool $force = false ): array {
		self::load();
		$out = array( 'refused' => false, 'documents' => 0, 'skipped' => 0, 'codes' => array() );
		if ( ! $force && ! Retire::group_active( 'element-pack', 'any' ) && Retire::group_installed( 'element-pack' ) ) {
			$out['refused']  = true;
			$out['codes'][] = array( 'core.revert_refused', array() );
			return $out;
		}
		Report::begin( 'run' );
		$steps = array_values( array_unique( array_merge( self::PREPARE_STEPS, self::PLATFORM_STEPS ) ) );
		foreach ( array_reverse( $steps ) as $step ) {
			$codes = self::platform_call( $step, 'revert', array( $force ) );
			foreach ( is_array( $codes ) ? $codes : array() as $code ) {
				$out['codes'][] = $code;
			}
		}
		foreach ( self::REVERT_EXTRA as $class ) {
			if ( self::has( $class, 'revert' ) ) {
				foreach ( (array) call_user_func( array( __NAMESPACE__ . '\\' . $class, 'revert' ), $force ) as $code ) {
					$out['codes'][] = $code;
				}
			}
		}
		// El CSS de la página, del kit y del Personalizador con copias de reglas de PRO: antes que los documentos, cuya
		// copia de los ajustes de la página (si la hay) es anterior a todo.
		if ( self::has( 'Pro', 'revert_css' ) ) {
			foreach ( Pro::revert_css( $force ) as $code ) {
				$out['codes'][] = $code;
			}
		}
		$touched = array();
		foreach ( array_unique( array_merge( Migration::backups(), Migration::page_backups() ) ) as $post_id ) {
			$post_id = (int) $post_id;
			if ( self::has( 'Platform\\Documents', 'origin' ) && null !== Platform\Documents::origin( $post_id ) ) {
				continue;
			}
			$mark = Migration::f8_mark( $post_id );
			if ( $mark && ! $force ) {
				$raw = (string) get_metadata( 'post', $post_id, '_elementor_data', true );
				if ( '' !== (string) ( $mark['hash'] ?? '' ) && '' !== $raw && md5( $raw ) !== (string) $mark['hash'] ) {
					++$out['skipped'];
					$out['codes'][] = array( 'core.revert_skipped', array( 'id' => $post_id ) );
					continue;
				}
			}
			if ( Migration::revert_document( $post_id ) ) {
				++$out['documents'];
				$touched[] = $post_id;
			}
		}
		$preserve = '\\Digitalisimo\\Elements\\Import\\Kits\\Preserve';
		foreach ( $touched as $post_id ) {
			if ( class_exists( $preserve ) && ! ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) ) {
				$data = json_decode( (string) get_metadata( 'post', $post_id, '_elementor_data', true ), true );
				$preserve::recount_document( $post_id, is_array( $data ) ? $data : array() );
			}
		}
		self::clear_platform_caches();
		Report::flush();
		return $out;
	}

	/** Caché de condiciones regenerada y cachés de fuentes, iconos y CSS vacías (tras revertir). */
	private static function clear_platform_caches() {
		try {
			if ( class_exists( '\\ElementorPro\\Plugin' ) && isset( \ElementorPro\Plugin::$instance ) && is_object( \ElementorPro\Plugin::$instance ) && method_exists( \ElementorPro\Plugin::$instance, 'modules_manager' ) ) {
				$module = \ElementorPro\Plugin::$instance->modules_manager->get_modules( 'theme-builder' );
				if ( $module && method_exists( $module, 'get_conditions_manager' ) ) {
					$cache = $module->get_conditions_manager()->get_cache();
					if ( $cache && method_exists( $cache, 'regenerate' ) ) {
						$cache->regenerate();
					}
				}
			}
			if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		foreach ( array( 'elementor_fonts_manager_fonts', 'elementor_fonts_manager_font_types', 'elementor_custom_icon_sets_config' ) as $option ) {
			delete_option( $option );
		}
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
		if ( ! empty( $state['wait_network'] ) ) {
			$html .= '<p>' . esc_html( 'Plantillas preparadas. Esperando a que los demás sitios de la red preparen las suyas para desactivar Element Pack en toda la red sin dejar ningún sitio sin cabecera.' ) . '</p>';
		}
		if ( ! empty( $state['elements_old'] ) ) {
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html( (string) $state['elements_old'] ) . '</p></div>';
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
		if ( ! empty( $c['copies'] ) ) {
			$rows[] = sprintf( 'Plantillas copiadas del Theme Builder de Element Pack convertidas: %d.', (int) $c['copies'] );
		}
		if ( ! empty( $c['converted'] ) ) {
			$rows[] = sprintf( 'Documentos de Element Pack y Prime Slider convertidos: %d (%d elementos).', (int) $c['converted'], (int) $c['elements'] );
		}
		if ( ! empty( $c['pages'] ) ) {
			$rows[] = sprintf( 'Ajustes de página con efectos de Element Pack convertidos: %d.', (int) $c['pages'] );
		}
		if ( ! empty( $c['markers'] ) ) {
			$rows[] = sprintf( 'Contenido conservado de kits importados convertido: %d documentos.', (int) $c['markers'] );
		}
		if ( ! empty( $c['revisions'] ) ) {
			$rows[] = sprintf( 'Revisiones y autoguardados convertidos: %d.', (int) $c['revisions'] );
		}
		if ( ! empty( $c['aliases'] ) ) {
			$rows[] = sprintf( 'Documentos con widgets fusionados actualizados: %d (%d widgets).', (int) $c['aliases'], (int) $c['alias_elements'] );
		}
		if ( ! empty( $c['pro'] ) ) {
			$rows[] = sprintf( 'Documentos con widgets de PRO convertidos a widgets propios: %d (%d widgets).', (int) $c['pro'], (int) ( $c['pro_elements'] ?? 0 ) );
		}
		if ( ! empty( $c['pro_css'] ) ) {
			$rows[] = sprintf( 'Documentos, kit o CSS adicional cuyo CSS nombra widgets de PRO, con la copia de esas reglas para los widgets propios: %d.', (int) $c['pro_css'] );
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
		if ( empty( $c['deactivated'] ) && empty( $c['converted'] ) && empty( $c['revisions'] ) && empty( $c['aliases'] ) && empty( $c['pro'] ) && empty( $c['recovered'] ) && empty( $c['repaired'] ) ) {
			$html .= '<p>No había nada que migrar en este sitio.</p>';
		}
		$still     = array();
		$deletable = array();
		$later     = array();
		foreach ( (array) ( $r['plugins'] ?? array() ) as $plugin ) {
			if ( ! empty( $plugin['late'] ) && ! empty( $plugin['network'] ) && empty( $plugin['site'] ) && empty( $r['late_kept'] ) ) {
				$later[] = $plugin['label'] . ' (activo en toda la red: lo desactiva la migración de la red al terminar todos los sitios)';
			} elseif ( ! empty( $plugin['network'] ) ) {
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
		// Lo que no tiene equivalente es información (no deja el sitio pendiente), salvo el contenido que hoy no se ve.
		$info = '';
		// Con el informe de F8 los descartes van en él (`ext.dropped.*`): la tabla antigua sólo para informes anteriores.
		if ( ( ! empty( $r['dropped'] ) || ! empty( $r['dropped_omitted'] ) ) && ! isset( $r['f8'] ) ) {
			self::dropped();
			$info .= Dropped::render( (array) ( $r['dropped'] ?? array() ), (int) ( $r['dropped_omitted'] ?? 0 ), Dropped::INTRO );
		}
		$kept = array( 'pending' => array(), 'info' => array(), 'idle' => array() );
		if ( ( ! empty( $r['kept'] ) || ! empty( $r['kept_idle'] ) ) && ! isset( $r['f8'] ) ) {
			self::dropped();
			$kept = Dropped::kept_messages( array_merge( (array) ( $r['kept'] ?? array() ), (array) ( $r['kept_idle'] ?? array() ) ) );
		}
		foreach ( $kept['pending'] as $message ) {
			$pending .= '<div class="notice notice-warning inline"><p>' . esc_html( $message ) . '</p></div>';
		}
		foreach ( $kept['info'] as $message ) {
			$info .= '<div class="notice notice-info inline"><p>' . esc_html( $message ) . '</p></div>';
		}
		if ( $kept['idle'] ) {
			$info .= '<p>' . esc_html( Dropped::idle_message( $kept['idle'] ) ) . '</p>';
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
		if ( ! empty( $r['pro_left'] ) ) {
			$pending .= '<p>' . esc_html( sprintf( 'Quedan %d documentos con widgets de PRO que ya tienen widget propio sin convertir: se siguen viendo igual; el informe dice por qué. Vuelve a pulsar Migrar cuando se resuelva.', (int) $r['pro_left'] ) ) . '</p>';
		}
		$info = '' !== $info ? '<h3>Sin equivalente</h3>' . $info : '';
		// Informe de F8 (por documento y por sitio), leído de su opción: cada campo escapado por Report.
		if ( isset( $r['f8'] ) ) {
			$f8 = Report::stored();
			if ( ! Report::is_empty( $f8 ) ) {
				$info .= '<div class="digi-migrate__f8">' . Report::render( $f8, 'site' ) . '</div>';
			}
			if ( ! empty( $r['f8']['pending'] ) && '' === $pending ) {
				$pending = '<p>' . esc_html( 'Hay asuntos en «Pendiente de revisar», más abajo.' ) . '</p>';
			}
		}
		if ( '' !== $pending ) {
			return $html . '<h3>Pendiente de revisar</h3>' . $pending . $info;
		}
		return $html . $info . '<div class="notice notice-success inline"><p>Este sitio ya no depende de Elementor, Elementor Pro, PRO Elements ni Element Pack. Si usas una caché de página o una CDN, vacíala. Cuando hayas comprobado el sitio, borra esos plugins y DIGITALÍSIMO Migración.</p></div>';
	}

	/** Nombres de sitios para un aviso. */
	private static function site_names( array $ids ) {
		$names = array();
		foreach ( $ids as $site_id ) {
			$details = function_exists( 'get_blog_details' ) ? get_blog_details( (int) $site_id ) : null;
			$names[] = $details ? $details->blogname . ' (' . $details->siteurl . ')' : '#' . (int) $site_id;
		}
		return implode( ', ', $names );
	}

	/** Plugins de la familia que la migración no desactiva, con su estado en la red (para la pantalla de red). */
	public static function kept_network() {
		$out = array();
		foreach ( Retire::network_summary( true ) as $row ) {
			if ( $row['network'] ) {
				$out[] = $row['label'] . ' (activo en toda la red)';
			} elseif ( $row['sites'] ) {
				$out[] = sprintf( '%s (activo en %d sitios)', $row['label'], (int) $row['sites'] );
			} else {
				$out[] = $row['label'] . ' (desactivado)';
			}
		}
		return $out;
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
		if ( ! empty( $job['barrier'] ) && empty( $job['released'] ) && empty( $job['done'] ) ) {
			$waiting = 0;
			foreach ( $status as $row ) {
				$waiting += ! empty( $row['waiting'] ) ? 1 : 0;
			}
			$html .= '<p>' . esc_html( sprintf( 'Esperando a que los sitios preparen sus plantillas para desactivar Element Pack en toda la red (%d de %d listos).', $waiting, count( (array) ( $job['barrier_sites'] ?? array() ) ) ) ) . '</p>';
		}
		if ( ! empty( $job['unprepared'] ) ) {
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html( 'Element Pack se desactivó en la red al vencer el plazo sin que estos sitios terminaran de preparar sus plantillas (pueden haberse quedado un tiempo sin su cabecera de Element Pack): ' . self::site_names( (array) $job['unprepared'] ) . '.' ) . '</p></div>';
		}
		if ( ! empty( $job['blocked'] ) ) {
			// Cada sitio enlaza a su herramienta, donde el informe dice qué plantilla bloquea (con su enlace).
			$links = array();
			foreach ( (array) $job['blocked'] as $site_id ) {
				$details = function_exists( 'get_blog_details' ) ? get_blog_details( (int) $site_id ) : null;
				$name    = $details ? $details->blogname . ' (' . $details->siteurl . ')' : '#' . (int) $site_id;
				$url     = function_exists( 'get_admin_url' ) ? get_admin_url( (int) $site_id, 'tools.php?page=' . Migration::PAGE ) : '';
				$links[] = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name );
			}
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html( 'Sitios con una cabecera o un pie de Element Pack visibles sin copia que pueda publicarse; Element Pack sigue activo en la red por ellos y nadie pierde su cabecera. Corrige la plantilla que indica su informe y vuelve a pulsar Migrar toda la red:' ) . ' ' . implode( ', ', $links ) . '.</p></div>';
		}
		if ( ! empty( $job['excluded'] ) ) {
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html( 'Archivados, marcados como spam o borrados: no se migraron. Migrarán al volver a pulsar Migrar toda la red cuando estén activos: ' . self::site_names( (array) $job['excluded'] ) . '.' ) . '</p></div>';
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
				$f8        = (array) ( $row['f8'] ?? array() );
				$result    = sprintf( '%d documentos y %d revisiones convertidos, %d con widgets fusionados actualizados', (int) ( $c['converted'] ?? 0 ), (int) ( $c['revisions'] ?? 0 ), (int) ( $c['aliases'] ?? 0 ) )
					. ( ! empty( $c['pro'] ) ? sprintf( ', %d con widgets de PRO convertidos', (int) $c['pro'] ) : '' ) . ( ! empty( $c['deactivated'] ) ? '; desactivados: ' . implode( ', ', (array) $c['deactivated'] ) : '' )
					. ( ! empty( $c['copies'] ) ? sprintf( '; plantillas: %d', (int) $c['copies'] ) : '' )
					. ( $f8 ? sprintf( '; creado: %d, con diferencias: %d, pendientes: %d', (int) ( $f8['created'] ?? 0 ), (int) ( $f8['approx'] ?? 0 ), (int) ( $f8['review'] ?? 0 ) ) : '' )
					. ( ! empty( $row['dropped'] ) ? sprintf( '; efectos heredados sin equivalente en %d documentos', (int) $row['dropped'] ) : '' )
					. ( ! empty( $row['kept'] ) ? '; sin desactivar: ' . implode( ', ', (array) $row['kept'] ) : '' );
			} elseif ( 'running' === $row['status'] ) {
				$ep_needed = true;
				$state     = ! empty( $row['waiting'] ) ? 'Esperando a la red' : 'En marcha';
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
