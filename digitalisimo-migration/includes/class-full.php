<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-replacements.php';
require_once __DIR__ . '/class-packages.php';
require_once __DIR__ . '/class-front.php';

use Digitalisimo\Elements\Legacy\Retire;
use Digitalisimo\Elements\Legacy\Runner;

/**
 * «Migrar» lo hace todo (regla del propietario del 2026-11-18): una sola tarea por etapas, reanudable, con cerrojo y
 * WP-Cron, en el sitio (Herramientas) o en toda la red (Red → Ajustes), y `wp digitalisimo-migration --run [--network]`.
 *
 *  1. plan     — mapa de reemplazos (Replacements) y huella de la portada y unas páginas (Front). Sólo lee.
 *  2. backup   — instala y activa DIGITALÍSIMO Backups si falta y hace un respaldo completo (de la red, en una red) con
 *                su cola de trabajos; sólo sigue con el respaldo terminado y su archivo en el almacén. Si falla, para.
 *  3. install  — instala desde el índice de la Release y activa (en una red, en toda la red) lo que el mapa pide,
 *                Elements antes que lo demás. Sólo paquetes del repositorio oficial (Packages).
 *  4. content  — la migración de contenido de siempre (Runner): desactiva la familia de Elementor y convierte.
 *  5. import   — importadores de SEO (Rank Math, Yoast) y comprobación de los respaldos de UpdraftPlus y Backuply.
 *  6. retire   — desactiva lo reemplazado cuya importación terminó.
 *  7. theme    — instala el tema propio y lo activa donde es seguro (Hello Elementor o un hijo suyo cuyo PHP se puede
 *                trasladar, con el Theme Builder de Elements cargado), tras guardar cómo se pintaba cada página; si no, dice
 *                por qué y qué hacer.
 *  8. verify   — la portada y las páginas responden igual y, donde cambió el tema, se pintan con las mismas hojas, el
 *                mismo CSS adicional y el mismo kit; si no, vuelve al tema anterior donde lo cambió.
 *  9. cleanup  — sólo con el respaldo verificado, ninguna etapa fallida y la portada verificada: borra lo reemplazado
 *                que el mapa permite borrar y del que nada depende.
 * 10. finish   — informe: instalado, importado, reemplazado, borrado, lo que quedó como estaba y cómo volver.
 *
 * Un fallo en cualquier etapa la detiene y salta al informe: nunca se borra nada tras un fallo. Tras activar,
 * desactivar o cambiar de tema, la tarea sigue en una petición nueva (la que corre aún tiene cargado lo de antes).
 */
final class Full {
	const JOB          = 'digitalisimo_migration_full';
	const LAST         = 'digitalisimo_migration_full_last';
	const NETWORK_JOB  = 'digitalisimo_migration_full_network';
	const NETWORK_LAST = 'digitalisimo_migration_full_network_last';
	const LOCK         = 'digitalisimo_migration_full_lock';
	const NETWORK_LOCK = 'digitalisimo_migration_full_network_lock';
	const CRON         = 'digitalisimo_migration_full_continue';
	const NETWORK_CRON = 'digitalisimo_migration_full_network_continue';
	/** La misma acción AJAX que el botón de siempre (el paso de la tarea de contenido en cada sitio de una red). */
	const AJAX   = 'digitalisimo_migration_step';
	const POLICY = 'pre-migration';
	/** Transitorios con los que un gancho de activación pide llevar la próxima pantalla del escritorio a la suya. */
	const ACTIVATION_REDIRECTS = array( 'elementor_activation_redirect' );
	const STAGES = array( 'plan', 'backup', 'install', 'content', 'import', 'retire', 'theme', 'verify', 'cleanup', 'finish' );
	const LABELS = array(
		'plan'    => 'Revisando lo que hay y guardando cómo se ve la portada',
		'backup'  => 'Haciendo un respaldo completo antes de tocar nada',
		'install' => 'Instalando y activando los plugins DIGITALÍSIMO',
		'content' => 'Migrando el contenido a DIGITALÍSIMO Elements',
		'import'  => 'Importando datos de los plugins reemplazados',
		'retire'  => 'Desactivando los plugins reemplazados',
		'theme'   => 'Cambiando al tema DIGITALÍSIMO donde es seguro',
		'verify'  => 'Comprobando que la portada y las páginas responden igual',
		'cleanup' => 'Borrando lo reemplazado',
		'finish'  => 'Preparando el informe',
	);
	const BUDGET       = 20;
	const STALE        = 600;
	const CRON_DELAY   = 120;
	const MAX_ATTEMPTS = 3;
	/** Sitios de una red cuya portada se compara (los demás se informan). */
	const VERIFY_SITES = 100;

	/** @var array<string,string> tokens de los cerrojos tomados en esta petición. */
	private static $tokens = array();
	/** @var bool ¿Proceso de WP-CLI? (nunca espera a otra petición: se relanza). */
	private static $cli = false;
	/** @var float cuándo empezó el trabajo de la tarea en esta petición (no la petición: su arranque puede tardar). */
	private static $began = 0.0;

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax' ) );
		add_action( self::CRON, array( __CLASS__, 'cron' ) );
		add_action( self::NETWORK_CRON, array( __CLASS__, 'network_cron' ) );
	}

	/* ------------------------------------------------------------------ Entradas */

	/**
	 * Paso desde la pantalla. En la red, la tarea de la red; en un sitio de una red, la tarea de contenido de ese sitio
	 * (la que conduce la pantalla de red, como siempre); en un sitio individual, la tarea completa.
	 */
	public static function ajax() {
		$network = ! empty( $_POST['network'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- el nonce se comprueba a continuación según el ámbito.
		if ( ! $network && is_multisite() ) {
			if ( class_exists( Runner::class ) ) {
				Runner::ajax();
				return;
			}
			wp_send_json_error( 'DIGITALÍSIMO Elements no está activo en este sitio: la migración de la red lo activa (Red → Ajustes → Migrar a DIGITALÍSIMO).', 409 );
		}
		if ( $network ) {
			if ( ! is_multisite() || ! is_super_admin() ) {
				wp_send_json_error( 'No autorizado.', 403 );
			}
			check_ajax_referer( self::AJAX . '_network' );
		} else {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_send_json_error( 'No autorizado.', 403 );
			}
			check_ajax_referer( self::AJAX . '_' . get_current_blog_id() );
		}
		$start = ! empty( $_POST['start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
		$run   = isset( $_POST['run'] ) ? sanitize_key( wp_unslash( $_POST['run'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
		$state = self::step( $network, $start, null, $run );
		if ( ! empty( $state['refused'] ) ) {
			wp_send_json_error( (string) $state['refused'], 409 );
		}
		wp_send_json_success( self::response( $network, $state ) );
	}

	/** Respuesta para migrate.js: la del sitio o la de la red (con los sitios que la pantalla conduce en `content`). */
	public static function response( $network, array $state ) {
		$html = self::render( $network, empty( $state['stage'] ) || ! empty( $state['idle'] ) ? self::last( $network ) : $state );
		$out  = array(
			'done'  => ! empty( $state['done'] ) || ( ! empty( $state['idle'] ) && self::last( $network ) ),
			'idle'  => ! empty( $state['idle'] ),
			'busy'  => ! empty( $state['busy'] ),
			'pause' => ! empty( $state['pause'] ),
			'stage' => (string) ( $state['stage'] ?? '' ),
			'html'  => $html,
		);
		if ( $network ) {
			$out['error']   = (string) ( $state['refused'] ?? '' );
			$out['sites']   = (array) ( $state['drive'] ?? array() );
			$out['again']   = empty( $out['done'] ) && empty( $out['idle'] ) && ! $out['sites'];
			$out['barrier'] = false;
		} else {
			$out['wait_network'] = false;
		}
		return $out;
	}

	public static function cron() {
		$state = self::load( false );
		if ( $state && empty( $state['done'] ) ) {
			self::step( false, false, min( 50, self::budget() ) );
		}
	}

	public static function network_cron() {
		$state = self::load( true );
		if ( $state && empty( $state['done'] ) ) {
			self::step( true, false, min( 50, self::budget() ) );
		}
	}

	/**
	 * WP-CLI: la tarea entera en este proceso. Tras activar o desactivar plugins sigue en un proceso nuevo (con los
	 * mismos parámetros globales), que ya los carga como quedaron.
	 */
	public static function cli( $network, $run = '' ) {
		self::$cli = true;
		$start     = true;
		// El proceso relanzado trae el clic del primero (`--run-id`): si otro proceso o el WP-Cron terminó esa pasada,
		// muestra su informe en vez de empezar otra migración.
		$run     = '' !== (string) $run ? preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $run ) ) : 'cli' . time();
		$content = 0;
		for ( $i = 0; $i < 7200; $i++ ) {
			$state = self::step( $network, $start, 60, $run );
			$start = false;
			if ( ! empty( $state['busy'] ) ) {
				\WP_CLI::error( 'Otra petición está ejecutando la migración: espera a que termine.' );
			}
			if ( ! empty( $state['idle'] ) ) {
				$last = self::last( $network );
				if ( empty( $state['refused'] ) && $last && (string) ( $last['run'] ?? '' ) === $run ) {
					$state = $last;
				} else {
					\WP_CLI::error( (string) ( $state['refused'] ?? 'No hay ninguna migración en marcha.' ) );
				}
			}
			if ( ! empty( $state['fresh_needed'] ) ) {
				\WP_CLI::log( self::LABELS[ $state['stage'] ] ?? '' );
				\WP_CLI::log( 'Plugins o tema cambiados: la migración sigue en un proceso nuevo.' );
				\WP_CLI::runcommand( 'digitalisimo-migration --run' . ( $network ? ' --network' : '' ) . ' --run-id=' . $run, array( 'launch' => true, 'exit_error' => true, 'return' => false ) );
				return;
			}
			if ( $network && 'content' === ( $state['stage'] ?? '' ) && empty( $state['done'] ) && is_callable( array( '\\Digitalisimo\\Elements\\Legacy\\Cli', 'network_content' ) ) ) {
				// Cada sitio de la red se convierte en su propio proceso, como `--run --network` de siempre. Si tres
				// rondas no terminan el contenido, se para (no se relanzan procesos sin fin).
				if ( ++$content > 3 ) {
					\WP_CLI::error( 'La migración del contenido de la red no terminó tras tres rondas: revisa el informe de cada sitio y vuelve a ejecutar la orden; sigue donde quedó.' );
				}
				\Digitalisimo\Elements\Legacy\Cli::network_content();
				sleep( 1 );
				continue;
			}
			if ( ! empty( $state['done'] ) ) {
				$html = self::render( $network, $state, 'cli' );
				\WP_CLI::log( self::text( $html ) );
				if ( ! empty( $state['failed'] ) ) {
					\WP_CLI::error( 'La migración se detuvo: ' . (string) $state['error'] );
				}
				\WP_CLI::success( false !== strpos( $html, 'digi-migrate__attention-title' ) ? 'Migración completada, con asuntos que necesitan tu atención (arriba).' : 'Migración completada.' );
				return;
			}
			if ( ! empty( $state['pause'] ) ) {
				sleep( 1 );
			}
		}
		\WP_CLI::error( 'La migración no terminó: vuelve a ejecutar la orden; sigue donde quedó.' );
	}

	/* ------------------------------------------------------------------ Tarea */

	public static function load( $network ) {
		$state = $network ? get_site_option( self::NETWORK_JOB, null ) : get_option( self::JOB, null );
		return is_array( $state ) ? $state : null;
	}

	public static function last( $network ) {
		$state = $network ? get_site_option( self::NETWORK_LAST, null ) : get_option( self::LAST, null );
		return is_array( $state ) ? $state : null;
	}

	private static function store( array $state ) {
		if ( empty( $state['done'] ) ) {
			unset( $state['busy'], $state['idle'], $state['drive'], $state['fresh_needed'] );
			if ( ! empty( $state['network'] ) ) {
				update_site_option( self::NETWORK_JOB, $state );
			} else {
				update_option( self::JOB, $state, false );
			}
		}
		self::touch( ! empty( $state['network'] ) );
	}

	/** Capacidades de quien lanza la tarea (WP-CLI las tiene todas). */
	public static function caps( $network ) {
		if ( self::$cli || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			// WP-CLI no tiene cuenta, pero sí respeta DISALLOW_FILE_MODS (y su filtro): sin escritura, ni instala ni borra.
			$mods = ! function_exists( 'wp_is_file_mod_allowed' ) || wp_is_file_mod_allowed( 'digitalisimo_migration' );
			return array( 'install' => $mods, 'activate' => true, 'delete' => $mods, 'themes' => $mods, 'update_themes' => $mods, 'code' => $mods, 'delete_themes' => $mods );
		}
		if ( $network ) {
			$super = is_super_admin() && current_user_can( 'manage_network_plugins' );
			return array( 'install' => $super && current_user_can( 'install_plugins' ), 'activate' => $super, 'delete' => $super && current_user_can( 'delete_plugins' ), 'themes' => $super && current_user_can( 'manage_network_themes' ) && current_user_can( 'install_themes' ), 'update_themes' => $super && current_user_can( 'update_themes' ), 'code' => $super && current_user_can( 'edit_themes' ) && current_user_can( 'unfiltered_html' ), 'delete_themes' => $super && current_user_can( 'delete_themes' ) );
		}
		return array( 'install' => current_user_can( 'install_plugins' ), 'activate' => current_user_can( 'activate_plugins' ), 'delete' => current_user_can( 'delete_plugins' ), 'themes' => current_user_can( 'switch_themes' ) && current_user_can( 'install_themes' ), 'update_themes' => current_user_can( 'update_themes' ), 'code' => current_user_can( 'edit_themes' ) && current_user_can( 'unfiltered_html' ), 'delete_themes' => current_user_can( 'delete_themes' ) );
	}

	public static function fresh( $network, $run = '' ) {
		return array(
			'v'          => 1,
			'version'    => defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '',
			'network'    => (bool) $network,
			'run'        => (string) $run,
			'started'    => time(),
			'updated'    => time(),
			'user'       => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'caps'       => self::caps( $network ),
			'stage'      => 'plan',
			'done'       => false,
			'failed'     => '',
			'error'      => '',
			'attempts'   => 0,
			'cursor'     => 0,
			'changed_at' => 0,
			'plan'       => array(),
			'sites'      => array(),
			'checks'     => array(),
			'snap'       => array(),
			'backup'     => array(),
			'installed'  => array(),
			'activated'  => array(),
			'content'    => array(),
			'imports'    => array(),
			'retired'    => array(),
			'theme'      => array(),
			'verify'     => array(),
			'deleted'    => array(),
			'kept'       => array(),
			'notes'      => array(),
		);
	}

	/**
	 * Avanza la tarea hasta agotar el presupuesto, esperar o terminar. Con $start crea una tarea si no hay ninguna y el
	 * clic ($run) no es el de la última terminada (un reintento tras perder la respuesta no lanza otra).
	 */
	public static function step( $network, $start = false, $budget = null, $run = '' ) {
		$network = (bool) $network;
		$budget  = null === $budget ? self::budget() : (float) $budget;
		if ( ! self::lock( $network ) ) {
			$state = self::load( $network );
			if ( $state && empty( $state['done'] ) ) {
				self::schedule( $network );
			}
			return array( 'busy' => true ) + (array) $state;
		}
		try {
			$state = self::load( $network );
			if ( $start && ! $state ) {
				$last = self::last( $network );
				if ( '' === (string) $run || (string) $run !== (string) ( $last['run'] ?? '' ) ) {
					$refused = self::refused( $network );
					if ( '' !== $refused ) {
						return array( 'idle' => true, 'refused' => $refused );
					}
					$state = self::fresh( $network, $run );
					self::store( $state );
				}
			}
			if ( ! $state ) {
				return array( 'idle' => true );
			}
			self::schedule( $network );
			$state = self::advance( $state, $budget );
		} finally {
			self::unlock( $network );
		}
		if ( ! empty( $state['done'] ) ) {
			self::unschedule( $network );
		}
		return $state;
	}

	/** Motivo por el que no puede empezar (vacío si puede). */
	private static function refused( $network ) {
		if ( $network && ! is_multisite() ) {
			return 'Esta instalación no es una red.';
		}
		if ( ! $network && is_multisite() ) {
			return 'En una red, la sustitución completa se lanza desde Red → Ajustes → Migrar a DIGITALÍSIMO.';
		}
		$caps = self::caps( $network );
		if ( empty( $caps['activate'] ) ) {
			return 'Quien pulsó Migrar no puede activar ni desactivar plugins.';
		}
		return self::wp_too_old( Replacements::plan( $network ) );
	}

	/** Motivo si este WordPress es anterior al que piden los paquetes que se instalarían ('' si no). */
	public static function wp_too_old( array $plan ) {
		global $wp_version;
		$need = array_intersect_key( Replacements::REQUIRES_WP, array_flip( (array) ( $plan['needs'] ?? array() ) ) );
		if ( ! empty( $plan['theme']['hello'] ) ) {
			$need[ Replacements::THEME ] = Replacements::REQUIRES_WP[ Replacements::THEME ];
		}
		$min = '0';
		foreach ( $need as $version ) {
			$min = version_compare( $version, $min, '>' ) ? $version : $min;
		}
		return isset( $wp_version ) && version_compare( (string) $wp_version, $min, '<' ) ? sprintf( 'Migrar necesita WordPress %1$s o posterior (este sitio tiene %2$s): actualiza WordPress y vuelve a pulsar Migrar. No se tocó nada.', $min, (string) $wp_version ) : '';
	}

	private static function advance( array $state, $budget ) {
		self::$began    = microtime( true );
		$deadline       = self::$began + max( 1, (float) $budget );
		$state['pause'] = false;
		unset( $state['drive'] );
		// Un proceso que arrancó antes del último cambio de plugins o de tema aún tiene cargado lo de antes.
		if ( ! empty( $state['changed_at'] ) && self::request_started() < (float) $state['changed_at'] ) {
			return self::hold( $state );
		}
		while ( empty( $state['done'] ) && empty( $state['pause'] ) && microtime( true ) < $deadline ) {
			$stage = in_array( $state['stage'] ?? '', self::STAGES, true ) ? $state['stage'] : 'plan';
			// Una etapa que tumba la petición tres veces seguidas falla (y la tarea salta al informe sin borrar nada).
			$state['attempts'] = (int) ( $state['attempts'] ?? 0 ) + 1;
			if ( $state['attempts'] > self::MAX_ATTEMPTS ) {
				$state = self::fail( $state, sprintf( 'La etapa «%s» se cortó %d veces seguidas.', self::LABELS[ $stage ], self::MAX_ATTEMPTS ) );
				self::store( $state );
				continue;
			}
			self::store( $state );
			try {
				$state = call_user_func( array( __CLASS__, 'stage_' . $stage ), $state, $deadline );
			} catch ( \Throwable $e ) {
				// Lo que la etapa guardó antes de lanzar (p. ej. cada borrado de la limpieza) no se pierde.
				$saved = self::load( ! empty( $state['network'] ) );
				if ( is_array( $saved ) && ( $saved['stage'] ?? '' ) === $stage ) {
					$state = $saved;
				}
				$state = self::fail( $state, $e->getMessage() );
			}
			$state['attempts'] = 0;
			$state['updated']  = time();
			self::store( $state );
		}
		return $state;
	}

	private static function next( array $state, $stage ) {
		$state['stage']    = $stage;
		$state['cursor']   = 0;
		$state['attempts'] = 0;
		return $state;
	}

	/** Detiene la tarea: lo que falló queda en el informe y no se borra nada. */
	private static function fail( array $state, $message ) {
		$state['failed'] = (string) ( $state['stage'] ?? '' );
		$state['error']  = (string) $message;
		if ( in_array( $state['failed'], array( 'theme', 'verify' ), true ) ) {
			// El tema cambiado aún no se comprobó: se vuelve al anterior.
			$state = self::revert_themes( $state, 'La migración se detuvo antes de comprobar la portada: se volvió a %s.' );
		}
		return self::next( $state, 'finish' );
	}

	/** Plugins o tema cambiados: sigue en una petición (o un proceso) nuevo. */
	private static function changed( array $state ) {
		$state['changed_at'] = microtime( true );
		return self::hold( $state );
	}

	private static function hold( array $state ) {
		$state['pause']        = true;
		$state['fresh_needed'] = true;
		return $state;
	}

	/** Espera algo de fuera (el respaldo, otra petición, los sitios de la red): se repite en la siguiente petición. */
	private static function wait( array $state ) {
		$state['pause'] = true;
		return $state;
	}

	private static function note( array $state, $message ) {
		if ( ! in_array( $message, (array) $state['notes'], true ) ) {
			$state['notes'][] = (string) $message;
		}
		return $state;
	}

	private static function request_started() {
		return isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
	}

	/**
	 * ¿Empezó hace poco el trabajo de esta petición? Un importador con su propio presupuesto sólo corre al principio. Se
	 * mide desde advance(), no desde el inicio de la petición: en un servidor lento el arranque de WordPress ya pasa de
	 * 3 s y el importador no correría nunca.
	 */
	private static function early() {
		return self::$cli || microtime( true ) - self::$began < 3;
	}

	/** Sitios de la tarea: el actual o, en la red, los que no están archivados, marcados como spam ni borrados. */
	private static function targets( array $state ) {
		return empty( $state['network'] ) ? array( (int) get_current_blog_id() ) : array_map( 'intval', (array) $state['sites'] );
	}

	/** Ejecuta $callback en el sitio indicado. */
	private static function on_site( $site_id, callable $callback ) {
		$switch = is_multisite() && (int) $site_id !== (int) get_current_blog_id();
		if ( $switch ) {
			switch_to_blog( (int) $site_id );
		}
		try {
			return $callback();
		} finally {
			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	/* ------------------------------------------------------------------ 1. plan */

	private static function stage_plan( array $state, $deadline ) {
		if ( ! $state['plan'] ) {
			$state['plan'] = Replacements::plan( ! empty( $state['network'] ) );
			if ( ! empty( $state['network'] ) ) {
				$state['sites'] = array();
				foreach ( Retire::sites() as $site_id ) {
					$site = get_site( (int) $site_id );
					if ( is_object( $site ) && ( ! empty( $site->archived ) || ! empty( $site->spam ) || ! empty( $site->deleted ) ) ) {
						continue;
					}
					$state['sites'][] = (int) $site_id;
				}
			}
			// Tras una pasada que no terminó en verde (falló o no verificó la portada), se compara con la portada de antes
			// de la primera: la de ahora ya puede estar migrada y daría por buena cualquier cosa.
			$baseline = self::baseline( ! empty( $state['network'] ) );
			if ( $baseline ) {
				$state['checks']    = $baseline['checks'];
				$state['snap_from'] = (int) $baseline['started'];
				$state['cursor']    = count( $state['checks'] );
				return self::note( $state, 'Se compara con la portada de antes de la primera pasada de Migrar (' . gmdate( 'Y-m-d H:i', (int) $baseline['started'] ) . ' UTC), que aún no terminó en verde.' );
			}
			$state['checks'] = array();
			foreach ( array_slice( self::targets( $state ), 0, self::VERIFY_SITES ) as $site_id ) {
				$urls = self::on_site( $site_id, array( Front::class, 'urls' ) );
				foreach ( empty( $state['network'] ) ? $urls : array_slice( $urls, 0, 2 ) as $url ) {
					$state['checks'][] = array( (int) $site_id, (string) $url );
				}
			}
			if ( count( self::targets( $state ) ) > self::VERIFY_SITES ) {
				$state = self::note( $state, sprintf( 'Sólo se comparan las portadas de los primeros %d sitios de la red.', self::VERIFY_SITES ) );
			}
			return $state;
		}
		// Huella de cada URL antes de tocar nada, una por vuelta.
		$checks = (array) $state['checks'];
		while ( $state['cursor'] < count( $checks ) ) {
			list( $site_id, $url )                    = $checks[ $state['cursor'] ];
			self::put_snap( $state, $site_id . '|' . $url, Front::snapshot( $url ) );
			++$state['cursor'];
			if ( microtime( true ) >= $deadline ) {
				return $state;
			}
		}
		if ( empty( $state['snap_from'] ) && empty( $state['uniform_checked'] ) ) {
			// Un sitio cuyas URL dan todas el mismo texto (mantenimiento, acceso, cortafuegos) no es comparable.
			$by_site = array();
			foreach ( $checks as $check ) {
				$by_site[ (int) $check[0] ][ $check[0] . '|' . $check[1] ] = self::get_snap( $state, $check[0] . '|' . $check[1] );
			}
			foreach ( $by_site as $snaps ) {
				foreach ( Front::mark_uniform( $snaps ) as $key => $snap ) {
					self::put_snap( $state, $key, $snap );
				}
			}
			$state['uniform_checked'] = true;
		}
		return self::next( $state, 'backup' );
	}

	/**
	 * Huellas de la portada, fuera del estado (que se reescribe en cada paso): una opción sin carga automática por URL,
	 * en el sitio en curso (en una red, el principal, donde vive el estado de red).
	 */
	const SNAP = 'digitalisimo_migration_full_snap_';

	/** Referencia de la portada que sobrevive a una pasada que no terminó en verde (en una red, en `sitemeta`). */
	const BASELINE = 'digitalisimo_migration_full_baseline';

	/** Pasada dueña de las huellas: la propia o la primera que no terminó en verde. */
	private static function snap_owner( array $state ) {
		return (string) ( ! empty( $state['snap_from'] ) ? $state['snap_from'] : $state['started'] );
	}

	private static function put_snap( array $state, $key, array $snapshot ) {
		update_option( self::SNAP . md5( self::snap_owner( $state ) . '|' . $key ), $snapshot, false );
	}

	private static function get_snap( array $state, $key ) {
		$snapshot = get_option( self::SNAP . md5( self::snap_owner( $state ) . '|' . $key ), array() );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	private static function drop_snaps( array $state ) {
		foreach ( (array) $state['checks'] as $check ) {
			delete_option( self::SNAP . md5( self::snap_owner( $state ) . '|' . $check[0] . '|' . $check[1] ) );
			delete_option( self::SNAP . md5( self::snap_owner( $state ) . '|pre|' . $check[0] . '|' . $check[1] ) );
		}
	}

	/** La referencia guardada (o null). */
	public static function baseline( $network ) {
		$baseline = $network ? get_site_option( self::BASELINE, null ) : get_option( self::BASELINE, null );
		return is_array( $baseline ) && ! empty( $baseline['checks'] ) ? $baseline : null;
	}

	/**
	 * Al terminar: con la portada verificada, las huellas y la referencia se retiran; si no, las huellas se quedan como
	 * referencia de la pasada siguiente (la primera que no terminó en verde manda).
	 */
	private static function settle_baseline( array $state ) {
		$network = ! empty( $state['network'] );
		if ( ! empty( $state['verified'] ) ) {
			self::drop_snaps( $state );
			$network ? delete_site_option( self::BASELINE ) : delete_option( self::BASELINE );
			return;
		}
		if ( ! self::baseline( $network ) && ! empty( $state['checks'] ) ) {
			$baseline = array( 'started' => (int) self::snap_owner( $state ), 'checks' => array_values( (array) $state['checks'] ) );
			$network ? update_site_option( self::BASELINE, $baseline ) : update_option( self::BASELINE, $baseline, false );
		}
	}

	/* ------------------------------------------------------------------ 2. backup */

	/** ¿Está cargada la cola de trabajos de DIGITALÍSIMO Backups que se usa aquí? */
	public static function backups_ready() {
		return class_exists( 'Digitalisimo_Backups_Jobs' ) && class_exists( 'Digitalisimo_Backups_Job_Store' ) && class_exists( 'Digitalisimo_Backups' )
			&& method_exists( 'Digitalisimo_Backups_Jobs', 'spec' ) && method_exists( 'Digitalisimo_Backups_Jobs', 'enqueue' ) && method_exists( 'Digitalisimo_Backups_Jobs', 'tick' )
			&& method_exists( 'Digitalisimo_Backups', 'storage_directory' );
	}

	/**
	 * Instala y activa un plugin DIGITALÍSIMO si falta. Devuelve null si ya está cargado; si no, el estado siguiente
	 * (progreso, cambio o fallo).
	 */
	private static function ensure_plugin( array $state, $slug, $loaded ) {
		if ( $loaded ) {
			return null;
		}
		$file = Replacements::own_file( $slug );
		if ( ! Replacements::installed( $file ) ) {
			if ( empty( $state['caps']['install'] ) ) {
				return self::fail( $state, sprintf( 'Hace falta %s y quien pulsó Migrar no puede instalar plugins.', Replacements::own_label( $slug ) ) );
			}
			$result = Packages::install_plugin( $slug );
			if ( is_wp_error( $result ) ) {
				return self::fail( $state, sprintf( 'No se pudo instalar %s: %s', Replacements::own_label( $slug ), $result->get_error_message() ) );
			}
			$state['installed'][] = $slug;
			return $state;
		}
		if ( ! Replacements::is_active( $file ) ) {
			$result = self::activate( $state, array( $file ) );
			if ( is_wp_error( $result ) ) {
				return self::fail( $state, sprintf( 'No se pudo activar %s: %s', Replacements::own_label( $slug ), $result->get_error_message() ) );
			}
			$state['activated'][] = $slug;
			return self::changed( $state );
		}
		// Activo pero sin lo que se usa: o lo acaba de activar otra petición, o es una versión anterior.
		if ( ! empty( $state['changed_at'] ) && microtime( true ) - (float) $state['changed_at'] < 300 ) {
			return self::wait( $state );
		}
		return self::fail( $state, sprintf( '%s está activo pero no trae lo que Migrar necesita: actualízalo y vuelve a pulsar Migrar.', Replacements::own_label( $slug ) ) );
	}

	/** Activa archivos en este sitio o, en una red, en toda la red. */
	private static function activate( array $state, array $files ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( $files as $file ) {
			$result = activate_plugin( $file, '', ! empty( $state['network'] ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			self::forget_activation_redirects();
		}
		return true;
	}

	/**
	 * Tras cada activación, Migrar no deja que el gancho de activación del plugin lleve la próxima pantalla del escritorio
	 * a su bienvenida: al activarse en un sitio, el núcleo incluido en DIGITALÍSIMO Elements deja
	 * `elementor_activation_redirect` (60 s) en ese sitio, y la siguiente carga del escritorio saltaba sola a la página de
	 * Elements. Se borra en el sitio en curso, donde corre el gancho. Al activar en toda la red el núcleo no lo deja
	 * (`Maintenance::activation()` vuelve antes). Backups, SEO y Tools no dejan nada así.
	 */
	private static function forget_activation_redirects() {
		foreach ( self::ACTIVATION_REDIRECTS as $transient ) {
			delete_transient( $transient );
		}
	}

	private static function stage_backup( array $state, $deadline ) {
		if ( ! empty( $state['network'] ) && ! is_main_site() ) {
			// La cola y el almacén de respaldos de una red son del sitio principal.
			return self::on_site( (int) get_main_site_id(), static function () use ( $state, $deadline ) {
				return self::stage_backup( $state, $deadline );
			} );
		}
		$ensured = self::ensure_plugin( $state, 'digitalisimo-backups', self::backups_ready() );
		if ( null !== $ensured ) {
			return $ensured;
		}
		$jobs = 'Digitalisimo_Backups_Jobs';
		if ( empty( $state['backup']['job'] ) ) {
			$spec   = $jobs::spec( array( 'policy' => self::POLICY, 'destination' => 'local', 'retain' => 3, 'retention' => 'policy', 'via' => 'migration', 'network' => ! empty( $state['network'] ), 'site_id' => is_multisite() ? get_main_site_id() : 0 ) );
			$queued = $jobs::enqueue( $spec );
			$state['backup'] = array( 'job' => (string) ( $queued['job']['id'] ?? '' ), 'file' => basename( (string) ( $queued['job']['spec']['file'] ?? ( $spec['file'] ?? '' ) ) ), 'started' => time() );
			if ( method_exists( $jobs, 'schedule' ) ) {
				$jobs::schedule( true );
			}
			return $state;
		}
		$job = $jobs::get( (string) $state['backup']['job'] );
		if ( $job && ! \Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) {
			$jobs::tick( max( 5, (int) floor( $deadline - microtime( true ) ) ) );
			$job = $jobs::get( (string) $state['backup']['job'] );
		}
		if ( ! $job ) {
			// El registro de trabajos pudo retirarlo ya: el historial de Backups es la fuente de verdad del respaldo terminado.
			$job = self::backup_from_history( $state );
			if ( ! $job ) {
				return self::fail( $state, sprintf( 'El trabajo del respaldo (%s) ya no existe y su archivo (%s) no figura en el historial de DIGITALÍSIMO Backups.', (string) $state['backup']['job'], (string) ( $state['backup']['file'] ?? '' ) ) );
			}
		}
		if ( ! \Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) {
			$state['backup']['percent'] = (int) ( $job['progress']['percent'] ?? 0 );
			return self::wait( $state );
		}
		if ( 'success' !== ( $job['status'] ?? '' ) ) {
			return self::fail( $state, 'El respaldo falló: ' . (string) ( $job['error']['message'] ?? ( 'cancelled' === ( $job['status'] ?? '' ) ? 'se canceló.' : 'sin motivo.' ) ) );
		}
		$checked = self::backup_file( $job );
		if ( is_string( $checked ) ) {
			return self::fail( $state, $checked );
		}
		$state['backup'] = array_merge( $state['backup'], $checked, array( 'ok' => true, 'finished' => time() ) );
		return self::next( $state, 'install' );
	}

	/**
	 * El respaldo de esta tarea en el historial de Backups (por su archivo o, si Backups le cambió el nombre, por su
	 * política y su fecha), como un trabajo terminado; null si no está.
	 */
	private static function backup_from_history( array $state ) {
		$file    = (string) ( $state['backup']['file'] ?? '' );
		$started = (int) ( $state['backup']['started'] ?? 0 );
		foreach ( array_reverse( (array) get_option( 'digitalisimo_backups_history', array() ) ) as $item ) {
			$item = (array) $item;
			if ( 'full' !== (string) ( $item['backup_type'] ?? 'full' ) || 'created' !== (string) ( $item['origin'] ?? 'created' ) ) {
				continue;
			}
			$mine = ( '' !== $file && $file === (string) ( $item['file'] ?? '' ) ) || ( self::POLICY === (string) ( $item['policy'] ?? '' ) && strtotime( (string) ( $item['date'] ?? '' ) . ' ' . wp_timezone_string() ) >= $started - 60 );
			if ( $mine ) {
				return array( 'status' => 'success', 'result' => array( 'file' => (string) $item['file'], 'size' => (int) ( $item['size'] ?? 0 ), 'sha256' => (string) ( $item['sha256'] ?? '' ) ) );
			}
		}
		return null;
	}

	/**
	 * El archivo del respaldo terminado: en el almacén privado, con el tamaño y la suma SHA-256 que Backups registró al
	 * verificarlo, y entero (cabecera y fin del ZIP, o cabecera del cifrado). Devuelve sus datos o el motivo del fallo.
	 */
	public static function backup_file( array $job ) {
		$file = basename( (string) ( $job['result']['file'] ?? ( $job['spec']['file'] ?? '' ) ) );
		$size = (int) ( $job['result']['size'] ?? 0 );
		$sha  = (string) ( $job['result']['sha256'] ?? ( $job['checkpoint']['sha256'] ?? '' ) );
		try {
			$path = rtrim( (string) \Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . $file;
		} catch ( \Throwable $e ) {
			return 'No se encontró el almacén de respaldos: ' . $e->getMessage();
		}
		if ( '' === $file || ! is_file( $path ) ) {
			return 'El respaldo terminó, pero su archivo no está en el almacén.';
		}
		if ( ( $size && (int) filesize( $path ) !== $size ) || ! self::intact( $path ) ) {
			return 'El archivo del respaldo no está entero.';
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
			return 'El respaldo no trae la suma de comprobación de su verificación.';
		}
		return array( 'file' => $file, 'size' => (int) filesize( $path ), 'sha256' => $sha );
	}

	/** ¿ZIP con cabecera local y registro final, o archivo cifrado `.dgbk` con su cabecera? (como Backups). */
	private static function intact( $path ) {
		$handle = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return false;
		}
		try {
			$head = (string) fread( $handle, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( '.dgbk' === substr( $path, -5 ) ) {
				return 'DGBK' === substr( $head, 0, 4 );
			}
			if ( "PK\x03\x04" !== substr( $head, 0, 4 ) ) {
				return false;
			}
			$tail = min( (int) filesize( $path ), 65557 );
			if ( $tail < 22 || 0 !== fseek( $handle, -$tail, SEEK_END ) ) {
				return false;
			}
			$end = (string) fread( $handle, $tail ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$at  = strrpos( $end, "PK\x05\x06" );
			return false !== $at && strlen( $end ) - $at >= 22;
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
	}

	/* ------------------------------------------------------------------ 3. install */

	private static function stage_install( array $state, $deadline ) {
		$needs = array_values( array_diff( (array) $state['plan']['needs'], array( 'digitalisimo-backups' ) ) );
		foreach ( $needs as $slug ) {
			if ( Replacements::installed( Replacements::own_file( $slug ) ) ) {
				continue;
			}
			if ( empty( $state['caps']['install'] ) ) {
				return self::fail( $state, sprintf( 'Hace falta %s y quien pulsó Migrar no puede instalar plugins.', Replacements::own_label( $slug ) ) );
			}
			$result = Packages::install_plugin( $slug );
			if ( is_wp_error( $result ) ) {
				return self::fail( $state, sprintf( 'No se pudo instalar %s: %s', Replacements::own_label( $slug ), $result->get_error_message() ) );
			}
			$state['installed'][] = $slug;
			return $state;
		}
		$files = array();
		foreach ( $needs as $slug ) {
			$file = Replacements::own_file( $slug );
			if ( ! Replacements::is_active( $file ) ) {
				$files[ $slug ] = $file;
			}
		}
		if ( ! $files ) {
			return self::next( $state, 'content' );
		}
		$result = self::activate( $state, array_values( $files ) );
		if ( is_wp_error( $result ) ) {
			return self::fail( $state, 'No se pudo activar un plugin DIGITALÍSIMO: ' . $result->get_error_message() );
		}
		$state['activated'] = array_values( array_unique( array_merge( (array) $state['activated'], array_keys( $files ) ) ) );
		return self::changed( self::next( $state, 'content' ) );
	}

	/* ------------------------------------------------------------------ 4. content */

	/** ¿Hace falta la migración de contenido? Con Elements en el plan o ya activo. */
	private static function wants_content( array $state ) {
		return in_array( 'digitalisimo-elements', (array) $state['plan']['needs'], true ) || Replacements::is_active( Replacements::own_file( 'digitalisimo-elements' ) );
	}

	/** Resumen del informe de contenido de un sitio (Runner::report()) para la limpieza y el informe. */
	public static function content_summary( $report ) {
		$r = is_array( $report ) ? (array) ( $report['report'] ?? array() ) : array();
		return array(
			'done'      => is_array( $report ) && ! empty( $report['done'] ),
			'ep_needed' => ! empty( $r['ep_needed'] ),
			'late_kept' => ! empty( $r['late_kept'] ),
			'blocked'   => count( (array) ( $r['blocked'] ?? array() ) ),
			'failed'    => count( (array) ( $r['failed'] ?? array() ) ),
			'review'    => count( (array) ( $r['review'] ?? array() ) ) + (int) ( $r['f8']['pending'] ?? 0 ),
			'pending'   => is_array( $report ) && class_exists( Runner::class ) && Runner::has_pending( $report ),
		);
	}

	private static function stage_content( array $state, $deadline ) {
		if ( ! self::wants_content( $state ) ) {
			$state['content'] = array( 'skipped' => true );
			return self::next( $state, 'import' );
		}
		if ( ! class_exists( Runner::class ) ) {
			if ( ! empty( $state['changed_at'] ) && microtime( true ) - (float) $state['changed_at'] < 300 ) {
				return self::wait( $state );
			}
			return self::fail( $state, 'DIGITALÍSIMO Elements está activo, pero la conversión de Migración no se cargó.' );
		}
		Runner::load();
		$reason = Runner::requirements();
		if ( null !== $reason ) {
			return self::fail( $state, $reason );
		}
		return empty( $state['network'] ) ? self::content_site( $state, $deadline ) : self::content_network( $state );
	}

	private static function content_site( array $state, $deadline ) {
		$run    = (string) $state['run'] . '-content';
		$start  = empty( $state['content']['started'] );
		$result = Runner::site_step( $start, max( 1, $deadline - microtime( true ) ), true, $run );
		$state['content']['started'] = true;
		$report                      = Runner::report();
		if ( ! empty( $result['done'] ) || ( ! empty( $result['idle'] ) && $report && ! $start && (int) ( $report['finished'] ?? 0 ) >= (int) $state['started'] ) ) {
			$state['content'] = array( 'started' => true, 'sites' => array( (int) get_current_blog_id() => self::content_summary( $report ) ) );
			return self::next( $state, 'import' );
		}
		if ( ! empty( $result['idle'] ) && ! empty( $result['error'] ) ) {
			return self::fail( $state, (string) $result['error'] );
		}
		if ( ! empty( $result['pause'] ) && ! empty( $result['deactivated_at'] ) && self::request_started() < (float) $result['deactivated_at'] ) {
			// La tarea de contenido desactivó plugins: sigue en una petición (o un proceso) que ya no los carga.
			return self::hold( $state );
		}
		return self::wait( $state );
	}

	private static function content_network( array $state ) {
		if ( empty( $state['content']['started'] ) ) {
			$job = Runner::network_start( true );
			if ( ! empty( $job['error'] ) ) {
				return self::fail( $state, (string) $job['error'] );
			}
			$state['content']['started'] = (int) ( Runner::network_state()['started'] ?? time() );
		}
		Runner::network_release();
		$summary = Runner::network_summary();
		if ( ! empty( $summary['done'] ) ) {
			$sites = array();
			foreach ( (array) ( $summary['sites'] ?? array() ) as $site_id ) {
				$sites[ (int) $site_id ] = self::on_site( (int) $site_id, static function () {
					return self::content_summary( Runner::report() );
				} );
			}
			$state['content'] = array( 'started' => $state['content']['started'], 'sites' => $sites, 'stopped' => ! empty( $summary['stopped'] ) );
			if ( ! empty( $summary['stopped'] ) ) {
				return self::fail( $state, 'La migración del contenido de la red se detuvo: hay sitios con una cabecera o un pie de Element Pack visibles sin copia que pueda publicarse (su informe dice cuáles).' );
			}
			return self::next( $state, 'import' );
		}
		$state['drive'] = Runner::network_pending_sites( $summary );
		return self::wait( $state );
	}

	/* ------------------------------------------------------------------ 5. import */

	/** Filas del plan que se reemplazan (con reemplazo y sin bloqueo), con su importador. */
	private static function replaced_rows( array $state, $import = null ) {
		$out = array();
		foreach ( (array) ( $state['plan']['rows'] ?? array() ) as $key => $row ) {
			if ( '' === (string) $row['by'] || ( null !== $import && ! in_array( $row['import'], (array) $import, true ) ) ) {
				continue;
			}
			$out[ $key ] = $row;
		}
		return $out;
	}

	private static function stage_import( array $state, $deadline ) {
		$rows    = self::replaced_rows( $state, array( 'seo_rankmath', 'seo_yoast', 'backups_sets' ) );
		$targets = self::targets( $state );
		while ( $state['cursor'] < count( $targets ) ) {
			$site_id = $targets[ $state['cursor'] ];
			$result  = self::on_site( $site_id, static function () use ( $state, $rows, $site_id ) {
				return self::import_site( $state, $rows, $site_id );
			} );
			$state = $result[0];
			if ( 'fail' === $result[1] ) {
				return self::fail( $state, (string) $result[2] );
			}
			if ( 'wait' === $result[1] ) {
				return self::wait( $state );
			}
			++$state['cursor'];
			if ( microtime( true ) >= $deadline ) {
				return $state;
			}
		}
		return self::next( $state, 'retire' );
	}

	/**
	 * Importadores de un sitio. Devuelve [estado, 'done'|'wait'|'fail', motivo]. Cada importador se marca terminado en
	 * `imports[sitio][clave]`; uno que ya terminó en esta tarea no se repite.
	 */
	private static function import_site( array $state, array $rows, $site_id ) {
		$done = (array) ( $state['imports'][ $site_id ] ?? array() );
		foreach ( $rows as $key => $row ) {
			$kind = $row['import'];
			if ( ! empty( $done[ $kind ]['done'] ) ) {
				continue;
			}
			if ( 'seo_rankmath' === $kind ) {
				$class = 'Digitalisimo_Integrations_RankMath_Import';
				if ( ! class_exists( $class ) || ! method_exists( $class, 'start' ) || ! method_exists( $class, 'run_batch' ) ) {
					return array( $state, 'fail', 'DIGITALÍSIMO SEO no cargó su importador de Rank Math.' );
				}
				if ( empty( $done[ $kind ]['started'] ) ) {
					if ( empty( $class::detect()['any'] ) ) {
						$done[ $kind ] = array( 'done' => true, 'count' => 0 );
						continue;
					}
					$class::start();
					$done[ $kind ] = array( 'started' => time() );
				}
				$job = (array) $class::job();
				if ( 'running' === ( $job['status'] ?? '' ) && self::early() ) {
					$class::run_batch();
					$job = (array) $class::job();
				}
				if ( 'error' === ( $job['status'] ?? '' ) ) {
					return array( $state, 'fail', 'El importador de Rank Math se detuvo: ' . (string) ( $job['error'] ?? '' ) );
				}
				if ( 'done' !== ( $job['status'] ?? '' ) || (int) ( $job['started'] ?? 0 ) < (int) $done[ $kind ]['started'] ) {
					$state['imports'][ $site_id ] = $done;
					return array( $state, 'wait', '' );
				}
				$done[ $kind ] = array( 'done' => true, 'count' => array_sum( array_map( 'intval', (array) ( $job['done'] ?? array() ) ) ), 'notes' => count( (array) ( $job['notes'] ?? array() ) ) );
			} elseif ( 'seo_yoast' === $kind ) {
				$class = 'Digitalisimo_Integrations_SEO';
				if ( ! class_exists( $class ) || ! method_exists( $class, 'import_legacy' ) ) {
					return array( $state, 'fail', 'Esta versión de DIGITALÍSIMO SEO no puede importar Yoast SEO sin su pantalla: actualízala y vuelve a pulsar Migrar.' );
				}
				$cursor = (int) ( $done[ $kind ]['cursor'] ?? 0 );
				do {
					$batch  = (array) $class::import_legacy( array( 'yoast' ), $cursor, 200 );
					$cursor = (int) ( $batch['next'] ?? 0 );
					$done[ $kind ] = array( 'cursor' => $cursor, 'count' => (int) ( $done[ $kind ]['count'] ?? 0 ) + (int) ( $batch['imported'] ?? 0 ) );
				} while ( empty( $batch['done'] ) && ( self::$cli || self::early() ) );
				if ( empty( $batch['done'] ) ) {
					$state['imports'][ $site_id ] = $done;
					return array( $state, 'wait', '' );
				}
				$done[ $kind ]['done'] = true;
			} elseif ( 'backups_sets' === $kind ) {
				// Backups lee los respaldos de UpdraftPlus y Backuply en sus carpetas: se comprueba que los ve. El lector de
				// UpdraftPlus se carga sólo cuando hace falta (Digitalisimo_Backups::load_updraft_import()).
				if ( method_exists( 'Digitalisimo_Backups', 'load_updraft_import' ) ) {
					\Digitalisimo_Backups::load_updraft_import();
				}
				$counts = array();
				foreach ( array( 'updraft' => 'Digitalisimo_Backups_Updraft_Import', 'backuply' => 'Digitalisimo_Backups_Backuply_Import' ) as $name => $class ) {
					if ( ! class_exists( $class ) || ! method_exists( $class, 'sources' ) ) {
						return array( $state, 'fail', 'DIGITALÍSIMO Backups no cargó la lectura de los respaldos de UpdraftPlus y Backuply: actualízalo y vuelve a pulsar Migrar.' );
					}
					try {
						$counts[ $name ] = count( (array) $class::sources() );
					} catch ( \Throwable $e ) {
						return array( $state, 'fail', 'DIGITALÍSIMO Backups no pudo leer los respaldos de UpdraftPlus o Backuply: ' . $e->getMessage() );
					}
				}
				$done[ $kind ] = array( 'done' => true, 'count' => array_sum( $counts ) ) + $counts;
				$updraft       = isset( $rows['updraftplus'] ) ? Replacements::updraft_state() : null;
				if ( $updraft && 'manual' !== $updraft['interval'] ) {
					$state = self::note( $state, sprintf( 'UpdraftPlus hacía respaldos programados (%s) y deja de hacerlos al desactivarse: DIGITALÍSIMO Backups queda con su programación por defecto; revísala en Backups → Ajustes.', $updraft['interval'] ) );
				}
			}
		}
		$state['imports'][ $site_id ] = $done;
		return array( $state, 'done', '' );
	}

	/** ¿Terminó la importación de esta fila en todos los sitios de la tarea? */
	private static function imported( array $state, array $row ) {
		if ( '' === (string) $row['import'] || 'content' === $row['import'] ) {
			return true;
		}
		foreach ( self::targets( $state ) as $site_id ) {
			if ( empty( $state['imports'][ $site_id ][ $row['import'] ]['done'] ) ) {
				return false;
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ 6. retire */

	private static function stage_retire( array $state, $deadline ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$network = ! empty( $state['network'] );
		$any     = false;
		foreach ( self::replaced_rows( $state, array( 'seo_rankmath', 'seo_yoast', 'backups_sets' ) ) as $row ) {
			if ( ! self::imported( $state, $row ) || ! Replacements::is_active( Replacements::own_file( $row['by'] ) ) ) {
				continue;
			}
			$files = array_keys( (array) $row['files'] );
			// Una escritura por grupo y ámbito: PRO antes que el gratuito, en la misma llamada.
			$here = array_values( array_filter( array_reverse( $files ), array( Replacements::class, 'is_active' ) ) );
			if ( $network ) {
				$sitewide = array_values( array_filter( $here, static function ( $file ) {
					return isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $file ] );
				} ) );
				if ( $sitewide ) {
					deactivate_plugins( $sitewide, false, true );
					$any = true;
				}
				foreach ( self::targets( $state ) as $site_id ) {
					$any = self::on_site( $site_id, static function () use ( $files ) {
						$local = array_values( array_intersect( array_reverse( $files ), (array) get_option( 'active_plugins', array() ) ) );
						if ( $local ) {
							deactivate_plugins( $local, false, false );
						}
						return (bool) $local;
					} ) || $any;
				}
			} elseif ( $here ) {
				deactivate_plugins( $here, false, false );
				$any = true;
			}
			$state['retired'] = array_values( array_unique( array_merge( (array) $state['retired'], array( $row['key'] ) ) ) );
		}
		$state = self::next( $state, 'theme' );
		return $any ? self::changed( $state ) : $state;
	}

	/* ------------------------------------------------------------------ 7. theme */

	/**
	 * ¿Es seguro activar el tema propio en el sitio en curso? Desde Hello Elementor o un hijo suyo: el tema propio
	 * reproduce a Hello (sus hojas, sus filtros, sus ajustes, su logo, sus menús y su CSS adicional), vuelve a encolar lo
	 * que el functions.php del hijo encolaba (sus hojas y fuentes, desde su carpeta) y DIGITALÍSIMO Elements convierte
	 * su cabecera y su pie dinámicos en plantillas del Theme Builder, así que lo que se ve no cambia. Un hijo con PHP que
	 * no se puede trasladar sin ejecutarlo (otros ganchos, shortcodes, plantillas…) se queda hasta que ese PHP esté activo
	 * como fragmento de Tools (`covered`). Migrar lo importa inactivo y dentro de una condición que sólo lo deja correr con
	 * el tema propio activo, así que la persona puede revisarlo y activarlo con el hijo todavía activo (no choca con su
	 * functions.php) y, al volver a pulsar Migrar, el cambio de tema y ese PHP llegan juntos; nada se activa solo. Si el
	 * archivo no admite la condición, el motivo da el orden a mano: cambiar de tema y enseguida activar el fragmento. Las
	 * plantillas y demás archivos del hijo (`files`) no los sustituye ningún fragmento: con ellos el hijo siempre se queda.
	 * Tampoco cambia si su functions.php haría otra cosa con el tema propio (`risks`: constantes o funciones de Hello, rutas
	 * del tema, funciones usadas antes de declararse). `$facts['child']`: `analyzed`, `blockers` (texto, sólo
	 * functions.php), `files`, `risks`, `covered`, `imported` (lo que contestó Tools), `wrap` (admite la condición), `tools`
	 * (Tools activo en el sitio) y `filter` (su importación cargada en esta petición). Devuelve [bool, motivo].
	 *
	 * @return array{0:bool,1:string}
	 */
	public static function theme_safety( array $theme, array $facts ) {
		if ( ! empty( $theme['own'] ) ) {
			return array( false, 'El tema DIGITALÍSIMO ya está activo.' );
		}
		if ( empty( $theme['hello'] ) ) {
			return array( false, sprintf( 'El tema activo («%s») no es Hello Elementor ni un hijo suyo: el tema DIGITALÍSIMO sólo reproduce a Hello, así que con otro tema podría cambiar lo que se ve. Se queda; si quieres cambiarlo, hazlo en Apariencia después de comprobarlo.', (string) $theme['name'] ) );
		}
		if ( empty( $facts['builder'] ) ) {
			return array( false, 'El Theme Builder de DIGITALÍSIMO Elements no está cargado: la cabecera y el pie de Hello no podrían pasar a plantillas. Se queda ' . (string) $theme['name'] . '.' );
		}
		if ( ! empty( $facts['conflict'] ) ) {
			return array( false, 'Elementor Pro o PRO Elements sigue activo: el Theme Builder de DIGITALÍSIMO Elements no carga mientras tanto. Se queda ' . (string) $theme['name'] . '.' );
		}
		if ( empty( $facts['can'] ) ) {
			return array( false, 'Quien pulsó Migrar no puede instalar ni cambiar temas. Se queda ' . (string) $theme['name'] . '.' );
		}
		if ( ! empty( $theme['child'] ) ) {
			$child = (array) ( $facts['child'] ?? array() );
			if ( empty( $child['analyzed'] ) ) {
				return array( false, sprintf( 'El sitio usa un tema hijo de Hello («%s») y el tema DIGITALÍSIMO instalado no sabe leerlo (es anterior a 1.0.1). Se queda el tema hijo: actualiza el tema DIGITALÍSIMO y vuelve a pulsar Migrar.', (string) $theme['name'] ) );
			}
			$name = (string) $theme['name'];
			if ( '' !== (string) ( $child['files'] ?? '' ) ) {
				// Plantillas, theme.json u otros archivos del hijo: ningún fragmento de Tools los sustituye.
				return array( false, sprintf( 'El sitio usa un tema hijo de Hello («%s») con plantillas o archivos que el tema DIGITALÍSIMO no traslada: %s.%s Se queda el tema hijo para no perder cómo se pintan esas páginas. Qué hacer: si ya no hacen falta, quítalos del tema hijo (o cambia de tema a mano en Apariencia después de comprobar esas páginas) y vuelve a pulsar Migrar.', $name, (string) $child['files'], '' !== (string) ( $child['blockers'] ?? '' ) ? ' Además, su functions.php tiene PHP que no se traslada solo: ' . (string) $child['blockers'] . '.' : '' ) );
			}
			if ( '' === (string) ( $child['blockers'] ?? '' ) ) {
				return array( true, '' );
			}
			$intro = sprintf( 'El sitio usa un tema hijo de Hello («%s») con PHP que no se puede trasladar sin ejecutarlo: %s. Se queda el tema hijo para no perder lo que ese PHP hace.', $name, (string) $child['blockers'] );
			if ( '' !== (string) ( $child['risks'] ?? '' ) ) {
				return array( false, $intro . sprintf( ' Ese functions.php tampoco puede pasar tal cual a Código PHP de DIGITALÍSIMO Tools, porque con el tema DIGITALÍSIMO haría otra cosa: %s. Qué hacer: adapta esas líneas en el tema hijo (o cambia de tema a mano en Apariencia y lleva ese PHP, ya adaptado, a Código PHP) y vuelve a pulsar Migrar.', (string) $child['risks'] ) );
			}
			if ( ! empty( $child['covered'] ) ) {
				return array( true, '' );
			}
			$imported = (string) ( $child['imported'] ?? '' );
			$snippet  = '«PHP del tema hijo ' . $name . '»';
			if ( in_array( $imported, array( 'created', 'updated', 'unchanged', 'conflict' ), true ) && ! empty( $child['wrap'] ) ) {
				$why = array(
					'nocaps'    => ' Migrar no lo activó solo porque quien lo pulsó no puede editar código PHP (edit_themes y unfiltered_html).',
					'old_tools' => ' Migrar no lo activó solo porque la versión de DIGITALÍSIMO Tools instalada no lo permite (actualízala para que lo haga).',
					'changed'   => ' Migrar no lo activó solo porque ya no está como lo dejó: se editó, se revisó o se desactivó a mano, y activarlo de nuevo es de una persona.',
					'failed'    => ' Migrar no lo activó solo porque DIGITALÍSIMO Tools lo desactivó por un error: revisa ese error antes.',
					'blocked'   => ' Migrar no lo activó porque este servidor cierra la edición de código (DISALLOW_FILE_EDIT o DISALLOW_FILE_MODS).',
					'reduced'   => ' Migrar no lo activó porque Código PHP de DIGITALÍSIMO Tools está en modo seguro.',
					'check'     => ' Migrar no lo activó porque la revisión de DIGITALÍSIMO Tools encontró un error en el código.',
					'error'     => ' Migrar no lo pudo activar (otra acción sobre los fragmentos estaba en curso o no cabe en los topes de Tools).',
					'missing'   => ' Migrar no lo encontró en DIGITALÍSIMO Tools para activarlo.',
					'mismatch'  => ' Migrar no lo activó porque el fragmento de DIGITALÍSIMO Tools ya no es el functions.php que tiene ahora el tema hijo.',
					'signature' => ' Migrar no lo activó porque el fragmento no lleva la firma que le puso la importación (se cambió fuera de Tools o cambiaron las claves de wp-config.php): revísalo línea por línea antes de activarlo.',
				);
				$because = 'conflict' === $imported ? ' Migrar no lo activó solo porque se editó en Tools después de importarlo.' : ( $why[ (string) ( $child['activate'] ?? '' ) ] ?? '' );
				return array( false, $intro . $because . ' Qué hacer: en DIGITALÍSIMO Tools → Código PHP revisa ' . $snippet . ' y actívalo (Migrar lo dejó ahí, inactivo; va dentro de una condición que sólo lo deja correr con el tema DIGITALÍSIMO, así que puedes activarlo ya, con el hijo activo, sin que choque) y vuelve a pulsar Migrar: el tema cambia y ese PHP sigue corriendo.' );
			}
			if ( 'skipped' === $imported ) {
				return array( false, $intro . ' El fragmento ' . $snippet . ' que Migrar había dejado en DIGITALÍSIMO Tools → Código PHP se borró, así que no lo vuelve a crear. Qué hacer: restáuralo (o crea uno con el functions.php del hijo), revísalo, actívalo y vuelve a pulsar Migrar.' );
			}
			if ( empty( $child['tools'] ) ) {
				return array( false, $intro . ' Qué hacer: activa DIGITALÍSIMO Tools en este sitio y vuelve a pulsar Migrar; dejará ese PHP en su Código PHP para que lo revises y lo actives, y después el cambio de tema lo llevará consigo.' );
			}
			if ( array_key_exists( 'filter', $child ) && empty( $child['filter'] ) ) {
				return array( false, $intro . ' DIGITALÍSIMO Tools está activo en este sitio, pero Migrar corre desde otro sitio de la red donde Tools no está cargado, así que no pudo dejar ahí ese PHP. Qué hacer: activa DIGITALÍSIMO Tools también en el sitio principal (o en toda la red) y vuelve a pulsar Migrar.' );
			}
			if ( empty( $child['wrap'] ) ) {
				$do = $imported ? 'revisa en DIGITALÍSIMO Tools → Código PHP ' . $snippet . ' (Migrar lo dejó ahí, inactivo) y actívalo' : 'en DIGITALÍSIMO Tools → Código PHP crea un fragmento con su functions.php, revísalo y actívalo';
				return array( false, $intro . sprintf( ' Su functions.php no admite la condición que lo apaga con el hijo activo, así que se hace a mano, en este orden: 1) en Apariencia activa el tema DIGITALÍSIMO (trae sus hojas, sus fuentes, su CSS adicional y sus ajustes; desde ese momento el PHP del hijo deja de correr); 2) enseguida %s (no antes: con el hijo activo, ese fragmento choca con su functions.php); 3) revisa la portada y vuelve a pulsar Migrar para terminar.', $do ) );
			}
			return array( false, $intro . ' Migrar no pudo dejar su functions.php en DIGITALÍSIMO Tools → Código PHP. Qué hacer: crea ahí un fragmento con su functions.php, revísalo, actívalo y vuelve a pulsar Migrar.' );
		}
		return array( true, '' );
	}

	/** Hechos del sitio en curso para theme_safety() (sin los del tema hijo, que se leen con el tema ya instalado). */
	private static function theme_facts( array $state ) {
		$conflict = false;
		foreach ( Retire::status() as $info ) {
			$conflict = $conflict || ( 'pro' === $info['group'] && ( $info['site'] || $info['network'] ) );
		}
		return array(
			'builder'  => class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) && Replacements::is_active( Replacements::own_file( 'digitalisimo-elements' ) ),
			'conflict' => $conflict,
			'can'      => ! empty( $state['caps']['themes'] ),
		);
	}

	/**
	 * El lector del tema hijo del tema propio instalado (`inc/child.php`, funciones puras que no enganchan nada): la misma
	 * copia que leerá el hijo al activarse. false si esa copia no lo trae.
	 */
	public static function child_reader() {
		if ( function_exists( 'digitalisimo_theme_child_plan' ) ) {
			return true;
		}
		$file = function_exists( 'get_theme_root' ) ? get_theme_root( Replacements::THEME ) . '/' . Replacements::THEME . '/inc/child.php' : '';
		if ( '' === $file || ! is_file( $file ) ) {
			return false;
		}
		require_once $file;
		return function_exists( 'digitalisimo_theme_child_plan' );
	}

	/** Archivo principal de DIGITALÍSIMO Tools (dueño de «Código PHP»). */
	const TOOLS_FILE = 'digitalisimo-tools/digitalisimo-tools.php';

	/** Filtros de Tools (Import::activate_import() y ::deactivate_import()) para el functions.php del hijo. */
	const SNIPPET_ACTIVATE   = 'digitalisimo_tools_php_activate_import';
	const SNIPPET_DEACTIVATE = 'digitalisimo_tools_php_deactivate_import';

	/** Sufijo de la carpeta del hijo mientras se comprueba el sitio sin ella, justo antes de borrarlo. */
	const RETIRING = '.digitalisimo-retiring';

	/** Constantes de ruta de Hello o de Hello Plus que un hijo puede usar para leer archivos de Hello. */
	const HELLO_PATHS = '/\b(?:HELLO|EHP)_\w*(?:PATH|URL|URI|DIR)\w*\b/';

	/** `source:id` del fragmento que deja en Tools el tema propio con el functions.php del hijo. */
	private static function snippet_key( $stylesheet ) {
		return 'digitalisimo-theme:' . (string) $stylesheet;
	}

	/**
	 * Desactiva en Tools el fragmento del hijo que activó Migrar en cada sitio cuyo tema no cambió (o volvió atrás), sólo si
	 * sigue activo con ese mismo código. La vuelta atrás deja el sitio como estaba antes de Migrar.
	 */
	private static function drop_snippets( array $state, array $keep = array() ) {
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			if ( empty( $row['snippet'] ) || ! empty( $row['switched'] ) || in_array( (int) $site_id, $keep, true ) ) {
				continue;
			}
			$done = self::on_site( (int) $site_id, static function () use ( $row ) {
				if ( function_exists( 'wp_cache_delete' ) ) {
					wp_cache_delete( 'alloptions', 'options' );
				}
				return has_filter( self::SNIPPET_DEACTIVATE ) ? apply_filters( self::SNIPPET_DEACTIVATE, false, self::snippet_key( (string) $row['from'] ), array( 'hash' => (string) $row['snippet'] ) ) : false;
			} );
			$state['theme'][ $site_id ]['snippet']     = '';
			$state['theme'][ $site_id ]['snippet_off'] = true === $done ? 'off' : 'kept';
		}
		return $state;
	}

	/**
	 * Hechos del tema hijo activo en el sitio en curso: lo lee sin ejecutarlo y, si tiene PHP que no se traslada, deja su
	 * functions.php en Código PHP de Tools, inactivo y con revisión, sólo si Tools está activo en este sitio (en una red,
	 * su filtro puede estar cargado por el sitio principal aunque este sitio no lo use).
	 */
	private static function child_facts( array $state = array() ) {
		$child = wp_get_theme();
		if ( ! self::child_reader() || ! $child->exists() ) {
			return array( 'analyzed' => false );
		}
		$plan = digitalisimo_theme_child_plan( $child );
		$php  = array();
		$rest = array();
		foreach ( (array) $plan['blockers'] as $blocker ) {
			if ( 'functions.php' === ( $blocker['file'] ?? '' ) ) {
				$php[] = $blocker;
			} else {
				$rest[] = $blocker;
			}
		}
		// Un fragmento activo en Tools sólo sustituye functions.php: lo demás (plantillas, theme.json) va aparte.
		$blockers = $php ? digitalisimo_theme_child_blockers_text( $php ) : '';
		$files    = $rest ? digitalisimo_theme_child_blockers_text( $rest ) : '';
		$risks    = ! empty( $plan['risks'] ) ? digitalisimo_theme_child_blockers_text( (array) $plan['risks'] ) : '';
		$tools    = Replacements::is_active( self::TOOLS_FILE );
		$covered  = '' !== $blockers && '' === $risks && $tools && digitalisimo_theme_child_php_covered( $child );
		$code     = digitalisimo_theme_child_php_code( $child );
		// La misma envoltura que importa el tema (con la carpeta real del hijo y su contexto: reescribe sus rutas), para que
		// el hash que se pasa a Tools sea el del código importado.
		$wrap     = '' !== $code && "\0" !== $code ? ( function_exists( 'digitalisimo_theme_child_ctx' ) ? digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ), (string) $child->get_stylesheet(), digitalisimo_theme_child_ctx( $child ) ) : digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ) ) ) : '';
		$imported = '' !== $blockers && '' === $files && '' === $risks && $tools && ! $covered ? digitalisimo_theme_child_import_php( $child, $plan ) : '';
		$activate = '';
		if ( '' !== $wrap && in_array( $imported, array( 'created', 'updated', 'unchanged' ), true ) ) {
			// «Migrar lo hace todo» (regla del propietario del 2026-11-20): el functions.php del hijo, ya en Código PHP de Tools
			// y dentro de la condición del tema propio, se activa en la misma pasada por la vía de Tools (firma y guardián).
			// Con el hijo activo no hace nada; la prueba con el código corriendo es la de verify, que vuelve atrás y lo
			// desactiva si la portada no responde igual.
			if ( empty( $state['caps']['code'] ) ) {
				$activate = 'nocaps';
			} elseif ( ! has_filter( self::SNIPPET_ACTIVATE ) ) {
				$activate = 'old_tools';
			} else {
				// Tools compara el código con este hash, recalculado ahora del functions.php en disco: sólo se activa eso.
				$activate = (string) apply_filters( self::SNIPPET_ACTIVATE, null, self::snippet_key( $child->get_stylesheet() ), array( 'by' => (int) ( $state['user'] ?? 0 ), 'hash' => hash( 'sha256', $wrap ) ) );
				if ( in_array( $activate, array( 'activated', 'active' ), true ) ) {
					$covered = digitalisimo_theme_child_php_covered( $child );
				}
			}
		}
		return array(
			'analyzed' => true,
			'blockers' => $blockers,
			'files'    => $files,
			'risks'    => $risks,
			'covered'  => $covered,
			'carried'  => count( $plan['assets'] ),
			'tools'    => $tools,
			'filter'   => (bool) has_filter( 'digitalisimo_tools_php_import' ),
			'wrap'     => '' !== $wrap,
			'imported' => $imported,
			'activate' => $activate,
			'hash'     => '' !== $wrap ? hash( 'sha256', $wrap ) : '',
			// Constantes de ruta de Hello (HELLO_THEME_PATH, *_URL…): con ellas, el PHP del hijo puede leer la carpeta de
			// Hello, así que Hello no se borra.
			'hello_paths' => '' !== $code && "\0" !== $code && 1 === preg_match( self::HELLO_PATHS, $code ),
		);
	}

	/** Lee el hijo del sitio (con el tema propio instalado) y decide con theme_safety(); guarda sus hechos en la fila. */
	private static function child_check( array $state, $site_id ) {
		$facts               = self::on_site( $site_id, static function () use ( $state ) {
			return self::child_facts( $state );
		} );
		$theme               = array( 'own' => false, 'hello' => true, 'child' => true, 'name' => $state['theme'][ $site_id ]['name'] );
		list( $ok, $reason ) = self::theme_safety( $theme, array( 'builder' => true, 'conflict' => false, 'can' => true, 'child' => $facts ) );
		$state['theme'][ $site_id ]['child_facts'] = array_intersect_key( $facts, array_flip( array( 'blockers', 'files', 'risks', 'covered', 'carried', 'tools', 'filter', 'wrap', 'imported', 'activate', 'hello_paths' ) ) );
		if ( in_array( (string) ( $facts['activate'] ?? '' ), array( 'activated', 'active' ), true ) ) {
			// 'active' sólo vuelve si lo activó Migrar (una pasada que murió antes de guardar el estado).
			// Lo activó Migrar: si el tema no cambia o vuelve atrás, Migrar lo desactiva (sólo ese código).
			$state['theme'][ $site_id ]['snippet'] = (string) $facts['hash'];
			// Guardado enseguida: si la petición muere ahora, la vuelta atrás sigue sabiendo qué desactivar.
			self::store( $state );
		}
		$state['theme'][ $site_id ]['safe']        = $ok;
		$state['theme'][ $site_id ]['reason']      = $reason;
		return $state;
	}

	private static function stage_theme( array $state, $deadline ) {
		if ( empty( $state['theme_planned'] ) ) {
			if ( Packages::recover_theme() ) {
				$state['notes'][] = 'Una actualización del tema DIGITALÍSIMO se cortó a mitad (la petición terminó antes): su carpeta volvió a la copia de antes y se actualizó de nuevo.';
			}
			$safe = array();
			foreach ( self::targets( $state ) as $site_id ) {
				$row = self::on_site( $site_id, static function () use ( $state ) {
					$theme = Replacements::theme_state();
					// El hijo se lee después, con el tema propio instalado: aquí sólo lo demás.
					list( $ok, $reason ) = self::theme_safety( array( 'child' => false ) + $theme, self::theme_facts( $state ) );
					return array( 'from' => $theme['stylesheet'], 'name' => $theme['name'], 'hello' => ! empty( $theme['hello'] ), 'child' => ! empty( $theme['child'] ), 'own' => ! empty( $theme['own'] ), 'safe' => $ok, 'reason' => $reason );
				} );
				if ( $row['safe'] && ! self::site_verifiable( $state, (int) $site_id ) ) {
					// Sin una portada legible antes de empezar (un servidor que no puede pedirse a sí mismo), el cambio no
					// podría comprobarse: no se hace.
					$row['safe']   = false;
					$row['reason'] = 'La portada no se pudo leer antes de migrar (el servidor no puede pedirse a sí mismo o no respondía bien): sin poder comprobar el cambio, se queda ' . $row['name'] . '.';
				}
				$state['theme'][ $site_id ] = $row + array( 'switched' => false );
				if ( $row['safe'] ) {
					$safe[] = $site_id;
				}
			}
			$state['theme_planned'] = true;
			$state['theme_cursor']  = 0;
			if ( ! $safe ) {
				return self::next( $state, 'verify' );
			}
			$had       = function_exists( 'wp_get_theme' ) && wp_get_theme( Replacements::THEME )->exists();
			$from      = $had ? (string) wp_get_theme( Replacements::THEME )->get( 'Version' ) : '';
			$installed = Packages::ensure_theme( ! empty( $state['caps']['update_themes'] ?? $state['caps']['themes'] ) );
			$state['theme_installed_now'] = ! is_wp_error( $installed ) && 'installed' === $installed['action'];
			if ( ! is_wp_error( $installed ) && 'updated' === $installed['action'] ) {
				// El tema propio instalado era anterior al de la Release: se actualizó en la misma pasada (sin activarlo).
				$state['theme_updated_now'] = array( 'from' => $installed['from'], 'to' => $installed['to'] );
			}
			if ( is_wp_error( $installed ) ) {
				$what = $had ? sprintf( 'No se pudo actualizar el tema DIGITALÍSIMO instalado (%s) a la versión de la Release: ', '' === $from ? 'sin versión' : $from ) : 'No se pudo instalar el tema DIGITALÍSIMO: ';
				$todo = $had ? ' Qué hacer: actualiza el tema DIGITALÍSIMO en Apariencia → Temas (o en DIGITALÍSIMO Tools → Plugins) y vuelve a pulsar Migrar.' : ' Qué hacer: comprueba que WordPress puede escribir en wp-content/themes (sin pedir datos de FTP) y vuelve a pulsar Migrar.';
				foreach ( $safe as $site_id ) {
					$state['theme'][ $site_id ]['safe']   = false;
					$state['theme'][ $site_id ]['reason'] = $what . rtrim( $installed->get_error_message(), '.' ) . '. Se queda ' . $state['theme'][ $site_id ]['name'] . ', sin cambiar el tema.' . $todo;
				}
				return self::next( $state, 'verify' );
			}
			// Un hijo de Hello: se lee con el lector del tema propio ya instalado (lo que se trae y lo que no).
			foreach ( $safe as $site_id ) {
				if ( empty( $state['theme'][ $site_id ]['child'] ) ) {
					continue;
				}
				$state = self::child_check( $state, $site_id );
			}
			return $state;
		}
		// Huella de estilo de las páginas de cada sitio que va a cambiar, justo antes del cambio (con los plugins ya
		// reemplazados): verify compara con ella para saber si se pinta lo mismo. Una por vuelta, dentro del plazo.
		$checks = (array) $state['checks'];
		while ( $state['theme_cursor'] < count( $checks ) ) {
			list( $site_id, $url ) = $checks[ $state['theme_cursor'] ];
			++$state['theme_cursor'];
			if ( empty( $state['theme'][ $site_id ]['safe'] ) ) {
				continue;
			}
			$snap = Front::snapshot( $url, 1 );
			self::put_snap( $state, 'pre|' . $site_id . '|' . $url, array_intersect_key( $snap, array_flip( array( 'status', 'fatal', 'styles', 'error' ) ) ) );
			if ( microtime( true ) >= $deadline ) {
				return $state;
			}
		}
		$safe = array();
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			if ( empty( $row['safe'] ) ) {
				continue;
			}
			if ( ! empty( $row['child'] ) ) {
				// Las huellas pueden tomar varias vueltas: justo antes del cambio, el hijo se vuelve a leer (el fragmento de
				// Tools pudo desactivarse o pausarse, o el hijo cambiar).
				$state = self::child_check( $state, $site_id );
				if ( empty( $state['theme'][ $site_id ]['safe'] ) ) {
					continue;
				}
			}
			$readable = false;
			foreach ( $checks as $check ) {
				$readable = $readable || ( (int) $check[0] === (int) $site_id && null !== Front::compare_styles( self::get_snap( $state, 'pre|' . $check[0] . '|' . $check[1] ), array() )[0] );
			}
			if ( ! $readable ) {
				$state['theme'][ $site_id ]['safe']   = false;
				$state['theme'][ $site_id ]['reason'] = 'Justo antes de cambiar el tema la portada no se pudo leer: sin poder comprobar el cambio, se queda ' . $row['name'] . '.';
				continue;
			}
			$safe[] = (int) $site_id;
		}
		$state = self::drop_snippets( $state, $safe );
		if ( ! $safe ) {
			return self::next( $state, 'verify' );
		}
		if ( ! empty( $state['network'] ) && class_exists( '\\WP_Theme' ) && method_exists( '\\WP_Theme', 'network_enable_theme' ) ) {
			// Excepción declarada (AGENTS.md, «Migrar toda la red»): el tema propio queda habilitado en la red para
			// poder activarlo en los sitios donde es seguro. No toca ningún otro tema.
			\WP_Theme::network_enable_theme( Replacements::THEME );
			$state['theme_enabled'] = true;
		}
		foreach ( $safe as $site_id ) {
			self::on_site( $site_id, static function () {
				self::switch_theme_to( Replacements::THEME );
			} );
			$state['theme'][ $site_id ]['switched'] = true;
		}
		return self::changed( self::next( $state, 'verify' ) );
	}

	/** ¿Tiene el sitio alguna URL que respondía bien antes de empezar (comparable al final)? */
	private static function site_verifiable( array $state, $site_id ) {
		foreach ( (array) $state['checks'] as $check ) {
			if ( (int) $check[0] !== (int) $site_id ) {
				continue;
			}
			// Comparable: la misma regla que Front::compare() aplica a la huella de antes.
			$before = self::get_snap( $state, $check[0] . '|' . $check[1] );
			if ( null !== Front::compare( $before, $before )[0] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * El cambio de tema de Migrar (excepción declarada de `tests/theme-structure.php`, regla del propietario del
	 * 2026-11-18): activar el tema propio donde es seguro y, si la portada no responde igual, volver al anterior.
	 */
	private static function switch_theme_to( $stylesheet ) {
		switch_theme( (string) $stylesheet );
	}

	/* ------------------------------------------------------------------ 8. verify */

	private static function stage_verify( array $state, $deadline ) {
		$checks = (array) $state['checks'];
		// Antes de comparar, una petición a la portada de cada sitio que cambió de tema (ahí corre el paso desde Hello), un
		// sitio por vuelta y dentro del plazo: en una red no se corta la petición.
		if ( empty( $state['warmed_done'] ) ) {
			foreach ( (array) $state['theme'] as $site_id => $row ) {
				// También los sitios que ya usaban el tema propio, si Migrar lo actualizó: su carpeta cambió en vivo.
				$updated = ! empty( $row['own'] ) && ! empty( $state['theme_updated_now'] );
				if ( ( empty( $row['switched'] ) && ! $updated ) || in_array( (int) $site_id, (array) ( $state['warmed'] ?? array() ), true ) ) {
					continue;
				}
				$home   = (string) self::on_site( (int) $site_id, static function () {
					return home_url( '/' );
				} );
				$broken = Front::broken( Front::fetch( $home ) );
				if ( '' === $broken && ! empty( $row['switched'] ) ) {
					// Además de la portada, la API REST (el escritorio y el editor dependen de ella): un error de PHP del hijo
					// que sólo salta fuera de la portada también cuenta.
					$rest = Front::broken( Front::fetch( rtrim( $home, '/' ) . '/wp-json/' ) );
					if ( '' !== $rest ) {
						$state['verify'][] = array( 'site' => (int) $site_id, 'url' => rtrim( $home, '/' ) . '/wp-json/', 'ok' => false, 'reason' => 'Después de cambiar el tema, la API REST ' . $rest );
					}
				}
				// Sólo si antes del cambio la portada respondía bien y sin ese texto (un blog puede hablar de un «Fatal error»).
				$was    = self::get_snap( $state, 'pre|' . $site_id . '|' . $home ) ?: self::get_snap( $state, $site_id . '|' . $home );
				if ( '' !== $broken && ( ! $was || ( (int) ( $was['status'] ?? 0 ) >= 200 && (int) ( $was['status'] ?? 0 ) < 300 && empty( $was['fatal'] ) ) ) ) {
					// La primera petición con el tema propio (donde corre por primera vez el PHP del hijo en Tools) falló:
					// aunque las siguientes respondan, cuenta como falla y el tema vuelve atrás.
					$state['verify'][] = array( 'site' => (int) $site_id, 'url' => $home, 'ok' => false, 'reason' => ( ! empty( $row['switched'] ) ? 'La primera petición después de cambiar el tema ' : 'Este sitio ya usaba el tema DIGITALÍSIMO y, después de actualizarlo, su portada ' ) . $broken . ( ! empty( $row['switched'] ) ? '' : ' La actualización no se deshace sola: si hace falta, restaura el respaldo de esta migración.' ) );
				}
				$state['warmed'][] = (int) $site_id;
				if ( microtime( true ) >= $deadline ) {
					return $state;
				}
			}
			$state['warmed_done'] = true;
			return $state;
		}
		if ( empty( $state['snippets_checked'] ) ) {
			// El functions.php del hijo que corre en Tools tiene que seguir activo después de esa primera petición: si el
			// guardián de Tools lo pausó por un error (o alguien lo desactivó), sin él el PHP del hijo ya no corre.
			foreach ( (array) $state['theme'] as $site_id => $row ) {
				if ( empty( $row['switched'] ) || empty( $row['child_facts']['covered'] ) ) {
					continue;
				}
				$still = self::on_site( (int) $site_id, static function () use ( $row ) {
					if ( function_exists( 'wp_cache_delete' ) ) {
						wp_cache_delete( 'alloptions', 'options' ); // La pausa la escribió otra petición.
						wp_cache_delete( 'notoptions', 'options' );
					}
					return array( self::child_reader() && digitalisimo_theme_child_php_covered( wp_get_theme( (string) $row['from'] ) ), home_url( '/' ) );
				} );
				if ( ! $still[0] ) {
					$state['verify'][] = array( 'site' => (int) $site_id, 'url' => (string) $still[1], 'ok' => false, 'reason' => 'Después de cambiar el tema, «PHP del tema hijo ' . (string) $row['name'] . '» ya no está activo en DIGITALÍSIMO Tools → Código PHP (el guardián lo pausó por un error o se desactivó): sin él, el PHP del hijo no corre.' );
				}
			}
			$state['snippets_checked'] = true;
		}
		while ( $state['cursor'] < count( $checks ) ) {
			list( $site_id, $url ) = $checks[ $state['cursor'] ];
			$before                = self::get_snap( $state, $site_id . '|' . $url );
			// Tras cambiar de tema, el HTML de después debe enlazar el tema nuevo (si no, viene de una caché).
			$switched              = ! empty( $state['theme'][ $site_id ]['switched'] );
			$after                 = Front::snapshot( $url, 0 );
			list( $ok, $reason )   = Front::compare( $before, $after, $switched ? Replacements::THEME : '' );
			if ( $switched && true === $ok ) {
				// Con el tema cambiado, además se pinta lo mismo que justo antes del cambio (hojas, CSS adicional, kit).
				$pre = self::get_snap( $state, 'pre|' . $site_id . '|' . $url );
				if ( $pre ) {
					list( $styled, $why ) = Front::compare_styles( $pre, $after );
					if ( false === $styled ) {
						list( $ok, $reason ) = array( false, $why );
					}
				}
			}
			$state['verify'][]     = array( 'site' => (int) $site_id, 'url' => (string) $url, 'ok' => $ok, 'reason' => $reason );
			++$state['cursor'];
			if ( microtime( true ) >= $deadline ) {
				return $state;
			}
		}
		$state['verified'] = self::verified( $state );
		if ( ! $state['verified'] ) {
			// Donde se cambió el tema y algo no responde igual, se vuelve al anterior.
			$before = $state;
			$state  = self::next( self::revert_themes( $state, 'La portada no respondió igual después de migrar: se volvió a %s.' ), 'cleanup' );
			return $state['theme'] !== $before['theme'] ? self::changed( $state ) : $state;
		}
		return self::next( $state, 'cleanup' );
	}

	/**
	 * Vuelve al tema anterior donde Migrar lo cambió: con digitalisimo_theme_revert() si está cargada; si no, con
	 * switch_theme(), cuyo gancho en DIGITALÍSIMO Elements (`Theme\Module::left()`, activo en toda migración que cambia
	 * de tema) retira la cabecera y el pie generados al dejar el tema propio.
	 */
	private static function revert_themes( array $state, $reason ) {
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			if ( empty( $row['switched'] ) ) {
				continue;
			}
			self::on_site( (int) $site_id, static function () use ( $row ) {
				if ( function_exists( 'digitalisimo_theme_revert' ) ) {
					$result = digitalisimo_theme_revert();
					if ( ! is_wp_error( $result ) ) {
						return;
					}
				}
				self::switch_theme_to( (string) $row['from'] );
			} );
			$state['theme'][ $site_id ]['switched'] = false;
			$state['theme'][ $site_id ]['reverted'] = true;
			$state['theme'][ $site_id ]['reason']   = sprintf( $reason, (string) $row['name'] );
		}
		return self::drop_snippets( $state );
	}

	/** ¿Respondieron igual todas las URL comparables, y había alguna en cada sitio comparado? */
	public static function verified( array $state ) {
		$sites = array();
		foreach ( (array) $state['verify'] as $row ) {
			if ( false === $row['ok'] ) {
				return false;
			}
			$sites[ $row['site'] ] = ( $sites[ $row['site'] ] ?? false ) || true === $row['ok'];
		}
		return $sites && ! in_array( false, $sites, true );
	}

	/* ------------------------------------------------------------------ 9. cleanup */

	/** Motivo por el que no se borra nada (vacío si se puede). */
	public static function cleanup_gate( array $state ) {
		if ( empty( $state['backup']['ok'] ) ) {
			return 'No hay un respaldo verificado de esta migración.';
		}
		if ( '' !== (string) $state['failed'] ) {
			return 'Una etapa falló.';
		}
		if ( empty( $state['verified'] ) ) {
			return 'La portada o alguna página no respondió igual (o no se pudo comprobar), así que no se borró nada.';
		}
		if ( empty( $state['caps']['delete'] ) ) {
			return 'Quien pulsó Migrar no puede borrar plugins: quedan desactivados.';
		}
		return '';
	}

	private static function stage_cleanup( array $state, $deadline ) {
		$gate = self::cleanup_gate( $state );
		if ( '' !== $gate ) {
			foreach ( (array) ( $state['plan']['rows'] ?? array() ) as $key => $row ) {
				$state['kept'][ $key ] = '' !== (string) $row['by'] ? $gate : (string) $row['reason'];
			}
			if ( self::hello_replaced( $state ) ) {
				$state['kept']['hello-elementor'] = $gate;
			}
			return self::next( $state, 'finish' );
		}
		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		// Barreras que no dependen de cada fila: el respaldo sigue entero en el almacén (no basta lo anotado al hacerlo),
		// escritura directa (delete_plugins() y delete_theme() piden FTP con un formulario y terminan la petición) y una
		// sola red (lo de esta red puede estar en uso en otra de la misma instalación).
		$stop = self::backup_recheck( $state );
		if ( '' === $stop && 'direct' !== get_filesystem_method() ) {
			$stop = 'WordPress no puede escribir directamente en la carpeta de plugins (pide datos de FTP): queda desactivado para que lo borres desde Plugins.';
		}
		if ( '' === $stop && is_multisite() && function_exists( 'get_networks' ) && count( (array) get_networks( array( 'number' => 2, 'fields' => 'ids' ) ) ) > 1 ) {
			$stop = 'Esta instalación tiene más de una red y lo reemplazado puede estar en uso en otra: queda desactivado, sin borrar.';
		}
		if ( '' !== $stop ) {
			foreach ( (array) $state['plan']['rows'] as $key => $row ) {
				$state['kept'][ $key ] = '' !== (string) $row['by'] ? $stop : (string) $row['reason'];
			}
			if ( self::hello_replaced( $state ) ) {
				$state['kept']['hello-elementor'] = $stop;
			}
			return self::next( $state, 'finish' );
		}
		foreach ( (array) $state['plan']['rows'] as $key => $row ) {
			if ( isset( $state['deleted'][ $key ] ) || isset( $state['kept'][ $key ] ) ) {
				continue;
			}
			$files = array_values( array_filter( array_keys( (array) $row['files'] ), array( Replacements::class, 'installed' ) ) );
			if ( ! $files ) {
				// Estaba al empezar y ya no está: lo borró una vuelta de esta limpieza que se cortó (o alguien a mano).
				$state['deleted'][ $key ] = $row['label'];
				self::store( $state );
				continue;
			}
			list( $ok, $reason ) = Replacements::deletable( $row, self::delete_facts( $state, $row, $files ) );
			if ( ! $ok ) {
				$state['kept'][ $key ] = $reason;
				continue;
			}
			// El borrado en curso queda anotado antes de empezar y lo borrado, después de cada fila: si la petición
			// muere a mitad, el informe no pierde nada.
			$state['deleting'] = $key;
			self::store( $state );
			$result = delete_plugins( $files );
			unset( $state['deleting'] );
			if ( true === $result ) {
				$state['deleted'][ $key ] = $row['label'];
			} else {
				$state['kept'][ $key ] = 'WordPress no pudo borrarlo' . ( is_wp_error( $result ) ? ': ' . $result->get_error_message() : ' (no puede escribir en la carpeta de plugins).' );
			}
			self::store( $state );
			if ( microtime( true ) >= $deadline ) {
				return $state;
			}
		}
		$state = self::cleanup_theme( $state );
		return self::next( $state, 'finish' );
	}

	/** '' si el respaldo de esta tarea sigue en el almacén, entero y con su tamaño; si no, el motivo para no borrar. */
	private static function backup_recheck( array $state ) {
		if ( ! class_exists( 'Digitalisimo_Backups' ) ) {
			return 'DIGITALÍSIMO Backups no está cargado: no se puede comprobar que el respaldo de esta migración sigue ahí. Queda desactivado, sin borrar.';
		}
		$checked = self::backup_file( array( 'status' => 'success', 'result' => array( 'file' => (string) ( $state['backup']['file'] ?? '' ), 'size' => (int) ( $state['backup']['size'] ?? 0 ), 'sha256' => (string) ( $state['backup']['sha256'] ?? '' ) ) ) );
		return is_string( $checked ) ? 'El respaldo de esta migración ya no está entero en el almacén (' . $checked . '): sin él no se borra nada.' : '';
	}

	/** Hechos de una fila para Replacements::deletable(). */
	private static function delete_facts( array $state, array $row, array $files ) {
		$active = false;
		foreach ( $files as $file ) {
			$active = $active || Replacements::is_active( $file ) || ( is_multisite() && Retire::active_on_any_site( $file ) );
		}
		$content = array( 'done' => ! empty( $state['content']['sites'] ) );
		foreach ( (array) ( $state['content']['sites'] ?? array() ) as $summary ) {
			$content['done'] = $content['done'] && ! empty( $summary['done'] );
			foreach ( array( 'ep_needed', 'late_kept', 'blocked', 'failed', 'review' ) as $flag ) {
				$content[ $flag ] = ! empty( $content[ $flag ] ) || ! empty( $summary[ $flag ] );
			}
		}
		$uninstall = (array) get_option( 'uninstall_plugins', array() );
		// Con el núcleo incluido al mando, Elements quita de esa lista las rutinas de Elementor y Elementor Pro. Un
		// `uninstall.php` propio (una versión futura de Element Pack o Prime Slider) también correría su código.
		$safe = ! array_intersect( $files, array_keys( $uninstall ) );
		foreach ( $files as $file ) {
			$dir  = dirname( (string) $file );
			$safe = $safe && ( '.' === $dir || ! is_file( WP_PLUGIN_DIR . '/' . $dir . '/uninstall.php' ) );
		}
		$facts = array(
			'active'         => $active,
			'by_active'      => Replacements::is_active( Replacements::own_file( $row['by'] ) ),
			'imported'       => self::imported( $state, $row ),
			'content'        => $content,
			'keeps_data'     => self::updraft_keeps_data(),
			'core_ready'     => Retire::core_ready(),
			'uninstall_safe' => $safe,
			// Instalados ahora (pudieron llegar después de empezar).
			'dependents'     => Replacements::dependents( $row, (array) get_plugins() ),
		);
		if ( 'updraftplus' === $row['key'] ) {
			$updraft          = Replacements::updraft_state();
			$visible          = 0;
			foreach ( (array) $state['imports'] as $kinds ) {
				$visible += (int) ( $kinds['backups_sets']['updraft'] ?? 0 );
			}
			$facts['remote'] = $updraft['remote'];
			$facts['unseen'] = max( 0, $updraft['history'] - $visible );
		}
		return $facts;
	}

	/**
	 * ¿Seguirá DIGITALÍSIMO Backups viendo los respaldos de UpdraftPlus si se borra? Su desinstalación sólo borra
	 * opciones, y sólo con «borrar al desinstalar» encendido; entre ellas, la carpeta de respaldos si no es la habitual.
	 */
	public static function updraft_keeps_data() {
		$deinstall = is_multisite() ? get_site_option( 'updraftplus_deinstall_option', 'no' ) : get_option( 'updraftplus_deinstall_option', 'no' );
		if ( 'no' === (string) $deinstall ) {
			return true;
		}
		$dir = is_multisite() ? get_site_option( 'updraft_dir', '' ) : get_option( 'updraft_dir', '' );
		return '' === trim( (string) $dir ) || 'updraft' === trim( (string) $dir, " /\\" );
	}

	/** ¿Se cambió Hello por el tema propio en algún sitio y quedó así? */
	private static function hello_replaced( array $state ) {
		foreach ( (array) $state['theme'] as $row ) {
			if ( ! empty( $row['switched'] ) && ! empty( $row['hello'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Los temas hijos de Hello que Migrar cambió por el tema propio se borran cuando ya no hacen falta (regla del
	 * propietario del 2026-11-20: con «Migrar», Hello y el hijo se retiran como los plugins reemplazados): ningún sitio los
	 * usa, el tema propio de cada sitio que venía de él ya tiene sus hojas y fuentes en su carpeta de `uploads`
	 * (`digitalisimo_theme_child_assets_copied()` del tema propio instalado; sin esa función, el hijo se queda como antes)
	 * y su functions.php, si corre en Código PHP, no lee nada de la carpeta del hijo. Lo que se borra está en el respaldo.
	 * Devuelve carpeta => { name, sites } de los que pueden borrarse (los borra cleanup_theme()) y anota en `kept` por qué no.
	 */
	private static function retired_children( array &$state ) {
		$out  = array();
		$from = array();
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			if ( ! empty( $row['child'] ) && ! empty( $row['switched'] ) ) {
				$from[ (string) $row['from'] ][] = (int) $site_id;
			}
		}
		foreach ( $from as $slug => $sites ) {
			$theme = wp_get_theme( $slug );
			if ( ! $theme->exists() || Replacements::HELLO !== (string) $theme->get_template() || isset( $state['deleted'][ 'child:' . $slug ] ) || isset( $state['kept'][ 'child:' . $slug ] ) ) {
				continue;
			}
			$name   = (string) $theme->get( 'Name' );
			$reason = '';
			foreach ( is_multisite() ? Retire::sites() : array( (int) get_current_blog_id() ) as $site_id ) {
				$uses = self::on_site( (int) $site_id, static function () use ( $slug ) {
					return $slug === (string) get_option( 'stylesheet' ) || $slug === (string) get_option( 'template' );
				} );
				if ( $uses ) {
					$reason = 'Algún sitio sigue usándolo.';
					break;
				}
			}
			if ( '' === $reason ) {
				foreach ( $sites as $site_id ) {
					// La copia a uploads es del tema propio (idempotente): un sitio que cambió con una versión anterior no
					// tiene nada copiado hasta que se pide.
					$copied = self::on_site( $site_id, static function () {
						if ( ! self::child_reader() || ! function_exists( 'digitalisimo_theme_child_assets_copied' ) ) {
							return array( false, array() );
						}
						if ( function_exists( 'digitalisimo_theme_child_copy_assets' ) ) {
							digitalisimo_theme_child_copy_assets();
						}
						$issues = array();
						$ok     = true === digitalisimo_theme_child_assets_copied( $issues );
						return array( $ok, (array) $issues );
					} );
					if ( empty( $copied[0] ) ) {
						$why    = array_slice( array_filter( array_map( 'strval', (array) ( $copied[1] ?? array() ) ) ), 0, 3 );
						$reason = 'El tema DIGITALÍSIMO todavía carga sus hojas y fuentes desde su carpeta (no borrarlo)' . ( $why ? ': ' . implode( '; ', $why ) : '' ) . '.';
						break;
					}
				}
			}
			if ( '' === $reason && is_file( $theme->get_stylesheet_directory() . '/functions.php' ) ) {
				// Su functions.php sigue corriendo en Código PHP: si lee archivos (de su carpeta o por su nombre), la necesita.
				// Las rutas del tema fuera de lo que el tema propio ya trae son un riesgo que impide el cambio, así que un hijo
				// cambiado no las usa (las de sus encolados se quedan sin efecto: el tema registra antes esos handles).
				$code = (string) file_get_contents( $theme->get_stylesheet_directory() . '/functions.php' );
				// (Las rutas del tema y __DIR__/__FILE__ las reescribe el tema propio hacia su copia en uploads, que sólo trae
				// archivos estáticos: lo que incluye o lee archivos por su cuenta sí necesita la carpeta.)
				if ( preg_match( '/\b(?:include|require)(?:_once)?\b|\bfile_get_contents\s*\(|\bfopen\s*\(|\bfile\s*\(|\breadfile\s*\(|\bglob\s*\(|\bscandir\s*\(/i', $code ) || false !== stripos( $code, $slug ) ) {
					$reason = 'Su functions.php, que sigue corriendo en DIGITALÍSIMO Tools → Código PHP, lee archivos de su carpeta.';
				}
			}
			if ( '' === $reason ) {
				// Una imagen, una fuente o una hoja del hijo enlazada por su ruta en el contenido, los datos o el CSS de
				// Elementor, el CSS adicional o los widgets dejaría de verse al borrar su carpeta.
				foreach ( is_multisite() ? Retire::sites() : array( (int) get_current_blog_id() ) as $site_id ) {
					$where = (string) self::on_site( (int) $site_id, static function () use ( $slug ) {
						return self::child_references( $slug );
					} );
					if ( '' !== $where ) {
						$reason = sprintf( 'Todavía se enlazan archivos de su carpeta (wp-content/themes/%s/) en %s%s. Sube esos archivos a Medios, cambia los enlaces y vuelve a pulsar Migrar.', $slug, $where, is_multisite() ? ' de ' . self::site_name( (int) $site_id ) : '' );
						break;
					}
				}
			}
			if ( '' === $reason && empty( $state['caps']['delete_themes'] ) ) {
				$reason = 'Quien pulsó Migrar no puede borrar temas.';
			}
			if ( '' !== $reason ) {
				$state['kept'][ 'child:' . $slug ] = $reason;
				continue;
			}
			$out[ $slug ] = array( 'name' => $name, 'sites' => $sites );
		}
		return $out;
	}

	/**
	 * Dónde enlaza el sitio en curso algún archivo de la carpeta del hijo (`themes/<carpeta>/`): entradas y páginas (con el
	 * CSS adicional del Personalizador, que es una entrada `custom_css`), metadatos (los datos y el CSS de Elementor),
	 * opciones (widgets, menús, ajustes; no las del propio tema hijo, los transitorios ni las de Migrar, el tema propio y
	 * Código PHP) y las hojas que Elementor genera en uploads/elementor/css. '' si en ninguna.
	 */
	private static function child_references( $slug ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( 'themes/' . $slug . '/' ) . '%';
		$post = $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . $wpdb->posts . " WHERE post_content LIKE %s AND post_status NOT IN ('trash','auto-draft','inherit') LIMIT 1", $like ) ); // phpcs:ignore WordPress.DB
		if ( $post ) {
			return 'custom_css' === get_post_type( (int) $post ) ? 'el CSS adicional' : sprintf( 'el contenido de «%s» (ID %d)', get_the_title( (int) $post ), (int) $post );
		}
		$meta = $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_value LIKE %s LIMIT 1', $like ) ); // phpcs:ignore WordPress.DB
		if ( $meta ) {
			return sprintf( 'los datos de «%s» (ID %d; Elementor u otro plugin)', get_the_title( (int) $meta ), (int) $meta );
		}
		$option = $wpdb->get_var( $wpdb->prepare( 'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_value LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s LIMIT 1', $like, 'theme_mods_%', '%transient%', 'digitalisimo_migration%', 'digitalisimo_theme%', 'digitalisimo_tools_php%' ) ); // phpcs:ignore WordPress.DB
		if ( $option ) {
			return sprintf( 'la opción %s (widgets, menús o ajustes)', (string) $option );
		}
		$uploads = function_exists( 'wp_upload_dir' ) ? (array) wp_upload_dir( null, false ) : array();
		$css     = (string) ( $uploads['basedir'] ?? '' ) . '/elementor/css';
		foreach ( array_slice( '' !== (string) ( $uploads['basedir'] ?? '' ) && is_dir( $css ) ? (array) glob( $css . DIRECTORY_SEPARATOR . '*.css' ) : array(), 0, 2000 ) as $file ) {
			if ( false !== strpos( (string) file_get_contents( $file, false, null, 0, 2097152 ), 'themes/' . $slug . '/' ) ) {
				return 'el CSS de Elementor (' . basename( $file ) . ')';
			}
		}
		return '';
	}

	/**
	 * Sin la carpeta del hijo (apartada un momento), ¿responden igual las páginas comparadas de los sitios que venían de él?
	 * Mismas reglas que verify: el texto de antes de migrar y las hojas de justo antes del cambio. '' o el motivo.
	 */
	private static function recheck_without( array $state, array $sites ) {
		foreach ( (array) $state['checks'] as $check ) {
			list( $site_id, $url ) = $check;
			if ( ! in_array( (int) $site_id, $sites, true ) ) {
				continue;
			}
			$after               = Front::snapshot( $url, 0 );
			list( $ok, $reason ) = Front::compare( self::get_snap( $state, $site_id . '|' . $url ), $after, Replacements::THEME );
			$pre                 = self::get_snap( $state, 'pre|' . $site_id . '|' . $url );
			if ( true === $ok && $pre ) {
				list( $styled, $why ) = Front::compare_styles( $pre, $after );
				list( $ok, $reason )  = false === $styled ? array( false, $why ) : array( $ok, $reason );
			}
			if ( false === $ok ) {
				return $url . ' ' . rtrim( lcfirst( (string) $reason ), '. ' );
			}
		}
		return '';
	}

	/** Hello se borra si se reemplazó y ya nadie lo usa: ningún sitio lo tiene activo ni hay un tema hijo suyo instalado. */
	private static function cleanup_theme( array $state ) {
		if ( ! empty( $state['deleting_theme'] ) ) {
			// Un retiro que se cortó a mitad: la carpeta apartada vuelve a su sitio; si ya no está ninguna, se borró.
			$slug = (string) $state['deleting_theme'];
			$dir  = get_theme_root( $slug ) . '/' . $slug;
			if ( is_dir( $dir . self::RETIRING ) && ! is_dir( $dir ) ) {
				@rename( $dir . self::RETIRING, $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			if ( is_dir( $dir ) ) {
				$state['kept'][ 'child:' . $slug ] = 'Su retiro se interrumpió y la carpeta sigue en su sitio: vuelve a pulsar Migrar para terminar.';
			} else {
				$state['deleted'][ 'child:' . $slug ] = 'Tema hijo ' . $slug;
			}
			unset( $state['deleting_theme'] );
			self::store( $state );
		}
		foreach ( self::retired_children( $state ) as $slug => $info ) {
			// Antes de borrar: el estado se guarda, la carpeta se aparta y las páginas de los sitios que venían de él se
			// comprueban sin ella. Si algo no responde igual, vuelve a su sitio y se queda; si todo responde, se borra.
			$dir                     = get_theme_root( $slug ) . '/' . $slug;
			$state['deleting_theme'] = $slug;
			self::store( $state );
			if ( ! @rename( $dir, $dir . self::RETIRING ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$state['kept'][ 'child:' . $slug ] = 'No se pudo apartar su carpeta para comprobar el sitio sin ella.';
				unset( $state['deleting_theme'] );
				self::store( $state );
				continue;
			}
			if ( function_exists( 'wp_clean_themes_cache' ) ) {
				wp_clean_themes_cache( false );
			}
			$bad = self::recheck_without( $state, (array) $info['sites'] );
			@rename( $dir . self::RETIRING, $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( function_exists( 'wp_clean_themes_cache' ) ) {
				wp_clean_themes_cache( false );
			}
			if ( '' !== $bad ) {
				$state['kept'][ 'child:' . $slug ] = 'Sin su carpeta, ' . $bad . ': se queda.';
				unset( $state['deleting_theme'] );
				self::store( $state );
				continue;
			}
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			$result = delete_theme( $slug );
			if ( function_exists( 'wp_clean_themes_cache' ) ) {
				wp_clean_themes_cache( false );
			}
			if ( true === $result ) {
				$state['deleted'][ 'child:' . $slug ] = 'Tema hijo «' . $info['name'] . '»';
			} else {
				$state['kept'][ 'child:' . $slug ] = 'WordPress no pudo borrarlo' . ( is_wp_error( $result ) ? ': ' . $result->get_error_message() : '.' );
			}
			unset( $state['deleting_theme'] );
			self::store( $state );
		}
		if ( ! self::hello_replaced( $state ) || ! function_exists( 'wp_get_theme' ) || ! wp_get_theme( Replacements::HELLO )->exists() ) {
			return $state;
		}
		$reason = '';
		foreach ( is_multisite() ? Retire::sites() : array( (int) get_current_blog_id() ) as $site_id ) {
			$uses = self::on_site( (int) $site_id, static function () {
				return Replacements::HELLO === (string) get_option( 'template' ) || Replacements::HELLO === (string) get_option( 'stylesheet' );
			} );
			if ( $uses ) {
				$reason = 'Algún sitio sigue usando Hello Elementor.';
				break;
			}
		}
		if ( '' === $reason ) {
			foreach ( wp_get_themes() as $slug => $theme ) {
				if ( Replacements::HELLO !== (string) $slug && Replacements::HELLO === (string) $theme->get_template() ) {
					$reason = sprintf( 'El tema hijo «%s» depende de Hello Elementor.', (string) $theme->get( 'Name' ) );
					break;
				}
			}
		}
		if ( '' === $reason ) {
			foreach ( (array) $state['theme'] as $row ) {
				if ( ! empty( $row['switched'] ) && ! empty( $row['child_facts']['hello_paths'] ) ) {
					$reason = sprintf( 'El PHP del tema hijo «%s», que sigue corriendo en DIGITALÍSIMO Tools → Código PHP, usa constantes de ruta de Hello (HELLO_THEME_PATH, *_URL…): Hello se queda.', (string) $row['name'] );
					break;
				}
			}
		}
		if ( '' === $reason && empty( $state['caps']['delete_themes'] ) ) {
			$reason = 'Quien pulsó Migrar no puede borrar temas.';
		}
		if ( '' !== $reason ) {
			$state['kept']['hello-elementor'] = $reason;
			return $state;
		}
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$result = delete_theme( Replacements::HELLO );
		if ( true === $result ) {
			$state['deleted']['hello-elementor'] = 'Hello Elementor';
		} else {
			$state['kept']['hello-elementor'] = 'WordPress no pudo borrarlo' . ( is_wp_error( $result ) ? ': ' . $result->get_error_message() : '.' );
		}
		return $state;
	}

	/* ------------------------------------------------------------------ 10. finish */

	private static function stage_finish( array $state, $deadline ) {
		$state['done']     = true;
		$state['pause']    = false;
		$state['finished'] = time();
		if ( ! empty( $state['deleting'] ) ) {
			// Un borrado que se cortó a mitad: lo que ya no está, se dice borrado; lo que sigue, que sigue.
			$key   = (string) $state['deleting'];
			$row   = (array) ( $state['plan']['rows'][ $key ] ?? array() );
			$still = array_filter( array_keys( (array) ( $row['files'] ?? array() ) ), array( Replacements::class, 'installed' ) );
			if ( $still ) {
				$state['kept'][ $key ] = 'El borrado se interrumpió y sigue instalado (al menos en parte): revísalo en Plugins.';
			} else {
				$state['deleted'][ $key ] = (string) ( $row['label'] ?? $key );
			}
			unset( $state['deleting'] );
		}
		self::settle_baseline( $state );
		unset( $state['snap'], $state['checks'], $state['drive'] );
		if ( ! empty( $state['network'] ) ) {
			update_site_option( self::NETWORK_LAST, $state );
			delete_site_option( self::NETWORK_JOB );
		} else {
			update_option( self::LAST, $state, false );
			delete_option( self::JOB );
		}
		self::unschedule( ! empty( $state['network'] ) );
		return $state;
	}

	/* ------------------------------------------------------------------ Cerrojo, presupuesto y cron */

	/** Tabla de opciones del cerrojo: la del sitio o, en la red, la del sitio principal. */
	private static function lock_table( $network ) {
		global $wpdb;
		return $network ? $wpdb->get_blog_prefix( function_exists( 'get_main_site_id' ) ? (int) get_main_site_id() : 1 ) . 'options' : $wpdb->options;
	}

	/** Cerrojo atómico (INSERT IGNORE con token, como Runner); uno sin renovar en STALE segundos se roba. */
	private static function lock( $network ) {
		global $wpdb;
		$table = self::lock_table( $network );
		$name  = $network ? self::NETWORK_LOCK : self::LOCK;
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false ) : bin2hex( random_bytes( 10 ) );
		$value = time() . '|' . $token;
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", $name, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 !== $taken ) {
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( '' !== $held && time() - (int) $held < self::STALE ) {
				return false;
			}
			if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $name, $held ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return false;
			}
		}
		if ( ! self::$tokens ) {
			register_shutdown_function( array( __CLASS__, 'unlock_all' ) );
		}
		self::$tokens[ $network ? 'network' : 'site' ] = $token;
		return true;
	}

	private static function touch( $network ) {
		global $wpdb;
		$token = self::$tokens[ $network ? 'network' : 'site' ] ?? '';
		if ( '' !== $token ) {
			$table = self::lock_table( $network );
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET option_value = %s WHERE option_name = %s AND option_value LIKE %s", time() . '|' . $token, $network ? self::NETWORK_LOCK : self::LOCK, '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	private static function unlock( $network ) {
		global $wpdb;
		$key   = $network ? 'network' : 'site';
		$token = self::$tokens[ $key ] ?? '';
		unset( self::$tokens[ $key ] );
		if ( '' !== $token ) {
			$table = self::lock_table( $network );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name = %s AND option_value LIKE %s", $network ? self::NETWORK_LOCK : self::LOCK, '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/** Si la petición muere con un error fatal, suelta los cerrojos propios. */
	public static function unlock_all() {
		foreach ( array_keys( self::$tokens ) as $key ) {
			self::unlock( 'network' === $key );
		}
	}

	private static function budget() {
		$budget = (int) apply_filters( 'digitalisimo_migration_step_seconds', self::BUDGET );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- puede estar deshabilitada.
		}
		$max = (int) ini_get( 'max_execution_time' );
		return $max > 0 ? max( 5, min( $budget, $max - 10 ) ) : max( 5, $budget );
	}

	/** La continuación con WP-Cron (en una red, en el sitio principal) por si se cierra la pantalla. */
	private static function schedule( $network ) {
		if ( ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}
		$hook = $network ? self::NETWORK_CRON : self::CRON;
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( time() + self::CRON_DELAY, $hook );
		}
	}

	private static function unschedule( $network ) {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( $network ? self::NETWORK_CRON : self::CRON );
		}
	}

	/* ------------------------------------------------------------------ Vistas */

	/**
	 * HTML del estado: avance de la tarea en marcha o informe de la terminada ('' sin ninguna). `$where`: `site` (la
	 * pantalla) o `cli` (la terminal: el mismo resumen, sin enlaces ni el detalle por documento).
	 */
	public static function render( $network, $state, $where = 'site' ) {
		if ( ! is_array( $state ) || empty( $state['stage'] ) ) {
			return '';
		}
		return empty( $state['done'] ) ? self::render_progress( (bool) $network, $state ) : self::render_report( (bool) $network, $state, 'cli' === $where ? 'cli' : 'site' );
	}

	private static function render_progress( $network, array $state ) {
		$stage = (string) $state['stage'];
		$index = array_search( $stage, self::STAGES, true );
		$html  = '<div class="digi-migrate__progress notice notice-info inline"><p><span class="spinner is-active" style="float:none;margin:0 8px 0 0"></span><strong>' . esc_html( self::LABELS[ $stage ] ?? $stage ) . '…</strong> ' . esc_html( sprintf( '(paso %d de %d)', false === $index ? 1 : $index + 1, count( self::STAGES ) ) );
		if ( 'backup' === $stage && isset( $state['backup']['percent'] ) ) {
			$html .= ' · ' . esc_html( (int) $state['backup']['percent'] . ' %' );
		}
		$html .= '</p></div>';
		if ( 'content' === $stage && class_exists( Runner::class ) ) {
			$html .= $network ? Runner::render_network( Runner::network_state() ? Runner::network_summary( false ) : null ) : Runner::render( Runner::state() );
		}
		return $html;
	}

	/** Notas de la tarea que piden algo a una persona (por su comienzo): van en «Necesita tu atención». */
	const ACTION_NOTES = array( 'UpdraftPlus hacía respaldos programados' );

	/**
	 * Informe de la tarea terminada, para leerse en 30 segundos: arriba, el aviso (verde sólo si no queda nada para una
	 * persona) y el resultado en unas filas (respaldo y cómo volver, lo instalado, importado, reemplazado y borrado, el
	 * contenido, el tema y la portada); después «Necesita tu atención», con todo lo que pide algo a una persona (la
	 * portada que no responde igual y la vuelta del tema, lo desactivado sin borrar, el tema que se queda con algo por
	 * hacer, las notas con una acción y lo del contenido), o una línea que dice que no hay nada; y el detalle, plegado y
	 * agregado. Todo pasa por esc_html().
	 */
	private static function render_report( $network, array $state, $where = 'site' ) {
		$rows    = (array) ( $state['plan']['rows'] ?? array() );
		$content = null;
		$older   = '';
		if ( class_exists( Runner::class ) && ! empty( $state['content']['started'] ) ) {
			if ( $network ) {
				$job     = Runner::network_state() ? Runner::network_summary( false ) : null;
				$content = is_array( $job ) && $job ? Runner::network_parts( $job, $where, 4 ) : null;
			} else {
				$report  = Runner::report();
				$content = is_array( $report ) ? Runner::report_parts( $report, $where, 4 ) : null;
				// La tarea de contenido de esta pasada no terminó (la migración se detuvo antes): el informe es de otra.
				if ( $content && ! empty( $report['finished'] ) && ! empty( $state['started'] ) && (int) $report['finished'] < (int) $state['started'] ) {
					$older = sprintf( 'De la migración del %s: ', function_exists( 'wp_date' ) ? wp_date( 'd/m/Y H:i', (int) $report['finished'] ) : gmdate( 'd/m/Y H:i', (int) $report['finished'] ) );
				}
			}
		}
		$items     = array();
		$attention = array();
		if ( ! empty( $state['backup']['ok'] ) ) {
			$items[] = array( 'Respaldo', sprintf( '%s (%s MB, SHA-256 %s…). Para volver atrás: DIGITALÍSIMO Backups → Respaldos → Restaurar.', $state['backup']['file'], number_format( (float) $state['backup']['size'] / 1048576, 1 ), substr( (string) $state['backup']['sha256'], 0, 12 ) ) );
		}
		if ( $state['installed'] || $state['activated'] ) {
			$items[] = array( 'Instalado y activado', implode( ', ', array_map( array( Replacements::class, 'own_label' ), array_values( array_unique( array_merge( (array) $state['installed'], (array) $state['activated'] ) ) ) ) ) );
		}
		$imported = array();
		foreach ( (array) $state['imports'] as $site_id => $kinds ) {
			foreach ( (array) $kinds as $kind_name => $row ) {
				if ( ! empty( $row['done'] ) ) {
					$imported[ $kind_name ] = (int) ( $imported[ $kind_name ] ?? 0 ) + (int) ( $row['count'] ?? 0 );
				}
			}
		}
		$labels = array( 'seo_rankmath' => 'Rank Math → DIGITALÍSIMO SEO: %d datos', 'seo_yoast' => 'Yoast SEO → DIGITALÍSIMO SEO: %d campos', 'backups_sets' => 'Respaldos de UpdraftPlus y Backuply que DIGITALÍSIMO Backups ve para importar o restaurar: %d' );
		$lines  = array();
		foreach ( $imported as $kind_name => $count ) {
			$lines[] = sprintf( $labels[ $kind_name ] ?? '%d', $count );
		}
		if ( $lines ) {
			$items[] = array( 'Importado', implode( '; ', $lines ) );
		}
		$replaced = array();
		foreach ( $rows as $key => $row ) {
			if ( '' !== (string) $row['by'] && ( in_array( $key, (array) $state['retired'], true ) || 'content' === $row['import'] ) ) {
				$replaced[] = $row['label'] . ' → ' . Replacements::own_label( $row['by'] );
			}
		}
		if ( $replaced ) {
			$items[] = array( 'Reemplazado', implode( '; ', $replaced ) );
		}
		if ( $state['deleted'] ) {
			$items[] = array( 'Borrado', implode( ', ', array_values( (array) $state['deleted'] ) ) . '. Para recuperarlo, restaura el respaldo de arriba.' );
		}
		// La portada o una página que no responde igual: se volvió al tema anterior (si se cambió) y no se borró nada.
		$bad      = array_values( array_filter( (array) $state['verify'], static function ( $row ) {
			return false === $row['ok'];
		} ) );
		$reverted = array();
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			if ( ! empty( $row['reverted'] ) ) {
				$reverted[] = ( $network ? self::site_name( (int) $site_id ) . ': ' : '' ) . (string) $row['name'];
			}
		}
		$gate = self::cleanup_gate( $state );
		if ( $bad ) {
			$pages = array();
			foreach ( $bad as $row ) {
				$pages[] = $row['url'] . ' (' . rtrim( (string) $row['reason'], '. ' ) . ')';
			}
			$attention[] = esc_html( 'No responden igual después de migrar: ' . implode( '; ', $pages ) . '. Ábrelas y revisa qué cambió' . ( $reverted ? '; se volvió a ' . implode( ', ', $reverted ) : '' ) . '. No se borró nada. Cuando lo corrijas, vuelve a pulsar Migrar.' );
		}
		// Lo que Migrar desactivó y no borró pide algo a una persona; lo que no tocó, no.
		$off  = array();
		$same = array();
		foreach ( (array) $state['kept'] as $key => $reason ) {
			$label = $rows[ $key ]['label'] ?? ( 'hello-elementor' === $key ? 'Hello Elementor' : ( 0 === strpos( (string) $key, 'child:' ) ? 'Tema hijo ' . substr( (string) $key, 6 ) : $key ) );
			if ( isset( $rows[ $key ] ) && ! empty( $rows[ $key ]['active'] ) && ! Replacements::active_files( array_keys( (array) $rows[ $key ]['files'] ), $network ) ) {
				$off[ (string) $reason ][] = $label;
			} else {
				$same[] = $label . ': ' . $reason;
			}
		}
		foreach ( $off as $reason => $labels_off ) {
			// Con la portada que no responde igual, el motivo ya está en su entrada: sólo se dice qué quedó desactivado.
			$attention[] = $bad && $reason === $gate ? esc_html( 'Desactivado, sin borrar hasta que la portada responda igual: ' . implode( ', ', $labels_off ) . '.' ) : esc_html( 'Desactivado, sin borrar: ' . implode( ', ', $labels_off ) . '. ' . rtrim( $reason, '. ' ) . '. Cuando se resuelva, vuelve a pulsar Migrar (o bórralo desde Plugins).' );
		}
		if ( $same ) {
			$items[] = array( 'Quedó como estaba', implode( ' ', $same ) );
		}
		if ( $content ) {
			$items[] = array( 'Contenido', $older . (string) $content['summary'] );
		}
		$themes = array();
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			$where_name = $network ? self::site_name( (int) $site_id ) . ': ' : '';
			if ( ! empty( $row['switched'] ) ) {
				$themes[] = $where_name . sprintf( 'de %s al tema DIGITALÍSIMO', $row['name'] ) . ( ! empty( $row['snippet'] ) ? sprintf( '. Migrar activó «PHP del tema hijo %s» en DIGITALÍSIMO Tools → Código PHP: es el mismo functions.php que corría con el hijo, dentro de una condición que sólo lo deja correr con el tema DIGITALÍSIMO, y lo vigila el guardián de Tools. Para desactivarlo, pulsa «Desactivar» en esa fila (ese PHP deja de correr).', $row['name'] ) : '' );
			} elseif ( ! empty( $row['hello'] ) && empty( $row['own'] ) && ! ( ! empty( $row['reverted'] ) && $bad ) ) {
				// Hello (o su hijo) se queda por algo que una persona puede resolver: va en «Necesita tu atención» (la vuelta
				// por una portada que no responde igual ya la dice la entrada de la portada).
				$themes[]    = $where_name . sprintf( 'se queda %s (ver «Necesita tu atención»).', $row['name'] );
				$attention[] = esc_html( 'Tema: ' . $where_name . (string) $row['reason'] );
			} else {
				$themes[] = $where_name . (string) $row['reason'];
			}
			if ( ! empty( $row['snippet_off'] ) ) {
				// El fragmento que había activado Migrar vuelve a quedar inactivo con el hijo (o se dice que no se pudo).
				$themes[] = 'off' === $row['snippet_off'] ? sprintf( '«PHP del tema hijo %s», que Migrar había activado en DIGITALÍSIMO Tools, quedó otra vez desactivado y sin revisar, como estaba.', $row['name'] ) : sprintf( '«PHP del tema hijo %s», que Migrar había activado en DIGITALÍSIMO Tools, sigue activo (alguien lo cambió): con el hijo activo no hace nada.', $row['name'] );
			}
		}
		if ( ! empty( $state['theme_installed_now'] ) && ! array_filter( array_column( (array) $state['theme'], 'switched' ) ) ) {
			// Se instaló para leer el tema hijo y ningún sitio lo activó: queda instalado, sin activar, para el paso a mano.
			$themes[] = 'El tema DIGITALÍSIMO quedó instalado sin activar (hace falta para el cambio a mano de arriba; si no lo vas a usar, bórralo en Apariencia).';
		}
		if ( ! empty( $state['theme_updated_now'] ) ) {
			$themes[] = sprintf( 'El tema DIGITALÍSIMO instalado se actualizó de %s a %s antes del cambio, desde la Release y con la copia anterior guardada por WordPress hasta terminar.', '' === (string) $state['theme_updated_now']['from'] ? 'una versión sin número' : (string) $state['theme_updated_now']['from'], (string) $state['theme_updated_now']['to'] );
		}
		if ( $themes ) {
			$items[] = array( 'Tema', implode( ' ', $themes ) );
		}
		if ( $state['verify'] ) {
			$items[] = array( 'Portada y páginas', self::verify_text( (array) $state['verify'] ) );
		}
		foreach ( (array) $state['notes'] as $note ) {
			$action = false;
			foreach ( self::ACTION_NOTES as $prefix ) {
				$action = $action || 0 === strpos( (string) $note, $prefix );
			}
			if ( $action ) {
				$attention[] = esc_html( (string) $note );
			} else {
				$items[] = array( 'Nota', $note );
			}
		}
		if ( $content ) {
			$attention = array_merge( $attention, (array) $content['attention'] );
		}
		if ( '' !== (string) $state['failed'] ) {
			array_unshift( $attention, esc_html( 'Resuelve lo que dice el aviso de arriba y vuelve a pulsar Migrar: el respaldo, si se hizo, permite volver atrás.' ) );
		}
		// El aviso de arriba: rojo si se detuvo, amarillo si algo no respondió igual o queda algo para una persona.
		if ( '' !== (string) $state['failed'] ) {
			$kind  = 'error';
			$tail  = $state['deleted'] ? ' Ya se había borrado: ' . implode( ', ', array_values( (array) $state['deleted'] ) ) . '.' : ( 'cleanup' === $state['failed'] ? ' Revisa en Plugins qué quedó: el borrado se interrumpió.' : ' No se borró nada.' );
			$title = sprintf( 'La migración se detuvo en «%s»: %s', self::LABELS[ $state['failed'] ] ?? $state['failed'], (string) $state['error'] ) . $tail;
		} elseif ( $bad ) {
			$kind  = 'warning';
			$title = 'Migración terminada sin borrar nada: la portada o alguna página no responde igual' . ( $reverted ? ' y se volvió al tema anterior' : '' ) . '. Mira «Necesita tu atención».';
		} elseif ( $attention ) {
			$kind  = 'warning';
			$title = 'Migración completada, con asuntos que necesitan tu atención (abajo).';
		} else {
			$kind  = 'success';
			$title = 'Migración completada.';
		}
		$html  = '<div class="notice notice-' . $kind . ' inline"><p><strong>' . esc_html( $title ) . '</strong></p></div>';
		$html .= '<h2 class="digi-migrate__result-title">' . esc_html( 'Resultado' ) . '</h2><table class="widefat striped digi-migrate__full"><tbody>';
		foreach ( $items as $item ) {
			$html .= '<tr><th scope="row">' . esc_html( $item[0] ) . '</th><td>' . esc_html( $item[1] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		$html .= self::attention_html( $attention );
		if ( $content && '' !== trim( (string) $content['detail'] ) ) {
			$detail = '<h3>' . esc_html( '' !== $older ? 'Contenido (' . rtrim( $older, ': ' ) . ')' : 'Contenido' ) . '</h3>' . $content['detail'];
			$html  .= '<details class="digi-migrate__detail"><summary>' . esc_html( 'Detalle' ) . '</summary>' . $detail . '</details>';
		}
		return $html;
	}

	/**
	 * «Necesita tu atención» con sus entradas (HTML ya escapado), o una línea que dice que no hay nada. El mismo marcado
	 * que Report::attention_html() de la conversión, que puede no estar cargada.
	 */
	public static function attention_html( array $items ) {
		if ( ! $items ) {
			return '<p class="digi-migrate__attention digi-migrate__attention--none"><strong>' . esc_html( 'Nada necesita tu atención.' ) . '</strong></p>';
		}
		return '<h2 class="digi-migrate__attention-title">' . esc_html( 'Necesita tu atención' ) . '</h2><ul class="digi-migrate__attention" style="list-style:disc;margin-left:20px"><li>' . implode( '</li><li>', $items ) . '</li></ul>';
	}

	/** La comprobación de la portada y las páginas en una frase: cuántas responden igual, cuáles no y cuáles no se compararon. */
	private static function verify_text( array $verify ) {
		$same = 0;
		$bad  = array();
		$skip = array();
		foreach ( $verify as $row ) {
			if ( true === $row['ok'] ) {
				++$same;
			} elseif ( false === $row['ok'] ) {
				$bad[] = $row['url'] . ': ' . $row['reason'];
			} else {
				$skip[] = $row['url'] . ': ' . $row['reason'];
			}
		}
		$parts = array( sprintf( 'Responden igual: %d de %d.', $same, count( $verify ) ) );
		if ( $bad ) {
			$parts[] = 'No responden igual: ' . implode( ' ', $bad );
		}
		if ( $skip ) {
			$parts[] = 'Sin comparar: ' . implode( ' ', $skip );
		}
		return implode( ' ', $parts );
	}

	private static function site_name( $site_id ) {
		return (string) self::on_site( $site_id, static function () {
			return get_bloginfo( 'name' );
		} );
	}

	/**
	 * El informe en texto para la terminal: una línea por párrafo, entrada, fila, encabezado o desplegable (el informe
	 * para la terminal, `cli`, ya llega sin el detalle por documento).
	 */
	public static function text( $html ) {
		$html = str_replace( '</td></tr>', '</tr>', (string) $html );
		$text = str_replace( array( '</p>', '</li>', '</tr>', '</h2>', '</h3>', '</h4>', '</h5>', '</div>', '</summary>', '</caption>', '<ul>', '<ul ' ), array( "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n<ul>", "\n<ul " ), (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( str_replace( array( '</td>', '</th>' ), ' · ', $text ) ), ENT_QUOTES, 'UTF-8' );
		// Ni C0 (salvo el salto de línea y el tabulador) ni DEL ni C1 (U+0080–U+009F: U+009B es CSI) llegan a la terminal.
		$text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\xC2[\x80-\x9F]/', '', $text );
		return trim( (string) preg_replace( "/\n\s*\n+/", "\n", $text ) );
	}
}
