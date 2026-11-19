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
 *  7. theme    — instala el tema propio y lo activa donde es seguro (Hello Elementor sin hijo, con el Theme Builder de
 *                Elements cargado); si no, lo dice.
 *  8. verify   — la portada y las páginas responden igual; si no, vuelve al tema anterior donde lo cambió.
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
			wp_send_json_error( 'DIGITALÍSIMO Elements no está activo en este sitio: la migración de la red lo activa (Red → Ajustes → Migrar a DIGITALÍSIMO Elements).', 409 );
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
	public static function cli( $network ) {
		self::$cli = true;
		$start     = true;
		$run       = 'cli' . time();
		for ( $i = 0; $i < 7200; $i++ ) {
			$state = self::step( $network, $start, 60, $run );
			$start = false;
			if ( ! empty( $state['busy'] ) ) {
				\WP_CLI::error( 'Otra petición está ejecutando la migración: espera a que termine.' );
			}
			if ( ! empty( $state['idle'] ) ) {
				\WP_CLI::error( (string) ( $state['refused'] ?? 'No hay ninguna migración en marcha.' ) );
			}
			if ( ! empty( $state['fresh_needed'] ) ) {
				\WP_CLI::log( self::LABELS[ $state['stage'] ] ?? '' );
				\WP_CLI::log( 'Plugins o tema cambiados: la migración sigue en un proceso nuevo.' );
				\WP_CLI::runcommand( 'digitalisimo-migration --run' . ( $network ? ' --network' : '' ), array( 'launch' => true, 'exit_error' => true, 'return' => false ) );
				return;
			}
			if ( $network && 'content' === ( $state['stage'] ?? '' ) && empty( $state['done'] ) && is_callable( array( '\\Digitalisimo\\Elements\\Legacy\\Cli', 'network_content' ) ) ) {
				// Cada sitio de la red se convierte en su propio proceso, como `--run --network` de siempre.
				\Digitalisimo\Elements\Legacy\Cli::network_content();
				continue;
			}
			if ( ! empty( $state['done'] ) ) {
				\WP_CLI::log( self::text( self::render( $network, $state ) ) );
				if ( ! empty( $state['failed'] ) ) {
					\WP_CLI::error( 'La migración se detuvo: ' . (string) $state['error'] );
				}
				\WP_CLI::success( 'Migración completada.' );
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
			return array( 'install' => true, 'activate' => true, 'delete' => true, 'themes' => true );
		}
		if ( $network ) {
			$super = is_super_admin() && current_user_can( 'manage_network_plugins' );
			return array( 'install' => $super && current_user_can( 'install_plugins' ), 'activate' => $super, 'delete' => $super && current_user_can( 'delete_plugins' ), 'themes' => $super && current_user_can( 'manage_network_themes' ) && current_user_can( 'install_themes' ) );
		}
		return array( 'install' => current_user_can( 'install_plugins' ), 'activate' => current_user_can( 'activate_plugins' ), 'delete' => current_user_can( 'delete_plugins' ), 'themes' => current_user_can( 'switch_themes' ) && current_user_can( 'install_themes' ) );
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
			return 'En una red, la sustitución completa se lanza desde Red → Ajustes → Migrar a DIGITALÍSIMO Elements.';
		}
		$caps = self::caps( $network );
		return empty( $caps['activate'] ) ? 'Quien pulsó Migrar no puede activar ni desactivar plugins.' : '';
	}

	private static function advance( array $state, $budget ) {
		$deadline       = microtime( true ) + max( 1, (float) $budget );
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

	/** ¿Empezó esta petición hace poco? (un importador con su propio presupuesto sólo corre al principio de una petición). */
	private static function early() {
		return self::$cli || microtime( true ) - self::request_started() < 3;
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
		return self::next( $state, 'backup' );
	}

	/**
	 * Huellas de la portada, fuera del estado (que se reescribe en cada paso): una opción sin carga automática por URL,
	 * en el sitio en curso (en una red, el principal, donde vive el estado de red).
	 */
	const SNAP = 'digitalisimo_migration_full_snap_';

	private static function put_snap( array $state, $key, array $snapshot ) {
		update_option( self::SNAP . md5( (string) $state['started'] . '|' . $key ), $snapshot, false );
	}

	private static function get_snap( array $state, $key ) {
		$snapshot = get_option( self::SNAP . md5( (string) $state['started'] . '|' . $key ), array() );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	private static function drop_snaps( array $state ) {
		foreach ( (array) $state['checks'] as $check ) {
			delete_option( self::SNAP . md5( (string) $state['started'] . '|' . $check[0] . '|' . $check[1] ) );
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
		}
		return true;
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
				$count = 0;
				foreach ( array( 'Digitalisimo_Backups_Updraft_Import', 'Digitalisimo_Backups_Backuply_Import' ) as $class ) {
					if ( ! class_exists( $class ) || ! method_exists( $class, 'sources' ) ) {
						return array( $state, 'fail', 'DIGITALÍSIMO Backups no cargó la lectura de los respaldos de UpdraftPlus y Backuply: actualízalo y vuelve a pulsar Migrar.' );
					}
					try {
						$count += count( (array) $class::sources() );
					} catch ( \Throwable $e ) {
						return array( $state, 'fail', 'DIGITALÍSIMO Backups no pudo leer los respaldos de UpdraftPlus o Backuply: ' . $e->getMessage() );
					}
				}
				$done[ $kind ] = array( 'done' => true, 'count' => $count );
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
	 * ¿Es seguro activar el tema propio en el sitio en curso? Sólo desde Hello Elementor sin hijo: el tema propio
	 * reproduce a Hello (sus hojas, sus filtros, sus ajustes y su logo) y DIGITALÍSIMO Elements convierte su cabecera y
	 * su pie dinámicos en plantillas del Theme Builder, así que lo que se ve no cambia. Devuelve [bool, motivo].
	 *
	 * @return array{0:bool,1:string}
	 */
	public static function theme_safety( array $theme, array $facts ) {
		if ( ! empty( $theme['own'] ) ) {
			return array( false, 'El tema DIGITALÍSIMO ya está activo.' );
		}
		if ( empty( $theme['hello'] ) ) {
			return array( false, sprintf( 'El tema activo («%s») no es Hello Elementor: el tema DIGITALÍSIMO sólo reproduce a Hello, así que con otro tema podría cambiar lo que se ve. Se queda; si quieres cambiarlo, hazlo en Apariencia después de comprobarlo.', (string) $theme['name'] ) );
		}
		if ( ! empty( $theme['child'] ) ) {
			return array( false, sprintf( 'El sitio usa un tema hijo de Hello («%s»): su CSS y su PHP no se trasladan solos (el PHP va al editor de fragmentos de DIGITALÍSIMO Tools). Se queda; cámbialo en Apariencia cuando los hayas pasado.', (string) $theme['name'] ) );
		}
		if ( empty( $facts['builder'] ) ) {
			return array( false, 'El Theme Builder de DIGITALÍSIMO Elements no está cargado: la cabecera y el pie de Hello no podrían pasar a plantillas. Se queda Hello.' );
		}
		if ( ! empty( $facts['conflict'] ) ) {
			return array( false, 'Elementor Pro o PRO Elements sigue activo: el Theme Builder de DIGITALÍSIMO Elements no carga mientras tanto. Se queda Hello.' );
		}
		if ( empty( $facts['can'] ) ) {
			return array( false, 'Quien pulsó Migrar no puede instalar ni cambiar temas. Se queda Hello.' );
		}
		return array( true, '' );
	}

	/** Hechos del sitio en curso para theme_safety(). */
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

	private static function stage_theme( array $state, $deadline ) {
		$safe = array();
		foreach ( self::targets( $state ) as $site_id ) {
			$row = self::on_site( $site_id, static function () use ( $state ) {
				$theme = Replacements::theme_state();
				list( $ok, $reason ) = self::theme_safety( $theme, self::theme_facts( $state ) );
				return array( 'from' => $theme['stylesheet'], 'name' => $theme['name'], 'hello' => ! empty( $theme['hello'] ), 'own' => ! empty( $theme['own'] ), 'safe' => $ok, 'reason' => $reason );
			} );
			$state['theme'][ $site_id ] = $row + array( 'switched' => false );
			if ( $row['safe'] ) {
				$safe[] = $site_id;
			}
		}
		if ( ! $safe ) {
			return self::next( $state, 'verify' );
		}
		$installed = Packages::install_theme();
		if ( is_wp_error( $installed ) ) {
			foreach ( $safe as $site_id ) {
				$state['theme'][ $site_id ]['safe']   = false;
				$state['theme'][ $site_id ]['reason'] = 'No se pudo instalar el tema DIGITALÍSIMO: ' . $installed->get_error_message() . ' Se queda Hello.';
			}
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
		// Antes de comparar, una petición a la portada de cada sitio que cambió de tema: ahí corre el paso desde Hello.
		if ( 0 === (int) $state['cursor'] && empty( $state['warmed'] ) ) {
			foreach ( (array) $state['theme'] as $site_id => $row ) {
				if ( ! empty( $row['switched'] ) ) {
					Front::fetch( self::on_site( (int) $site_id, static function () {
						return home_url( '/' );
					} ) );
				}
			}
			$state['warmed'] = true;
			return $state;
		}
		while ( $state['cursor'] < count( $checks ) ) {
			list( $site_id, $url ) = $checks[ $state['cursor'] ];
			$before                = self::get_snap( $state, $site_id . '|' . $url );
			list( $ok, $reason )   = Front::compare( $before, Front::snapshot( $url ) );
			$state['verify'][]     = array( 'site' => (int) $site_id, 'url' => (string) $url, 'ok' => $ok, 'reason' => $reason );
			++$state['cursor'];
			if ( microtime( true ) >= $deadline ) {
				return $state;
			}
		}
		$state['verified'] = self::verified( $state );
		if ( ! $state['verified'] ) {
			// Donde se cambió el tema y algo no responde igual, se vuelve al anterior.
			$back = false;
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
				$state['theme'][ $site_id ]['reason']   = 'La portada no respondió igual después de migrar: se volvió a ' . (string) $row['name'] . '.';
				$back                                   = true;
			}
			$state = self::next( $state, 'cleanup' );
			return $back ? self::changed( $state ) : $state;
		}
		return self::next( $state, 'cleanup' );
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
		// delete_plugins() y delete_theme() piden datos de FTP con un formulario y terminan la petición: sólo con escritura directa.
		if ( 'direct' !== get_filesystem_method() ) {
			foreach ( (array) $state['plan']['rows'] as $key => $row ) {
				$state['kept'][ $key ] = '' !== (string) $row['by'] ? 'WordPress no puede escribir directamente en la carpeta de plugins (pide datos de FTP): queda desactivado para que lo borres desde Plugins.' : (string) $row['reason'];
			}
			return self::next( $state, 'finish' );
		}
		foreach ( (array) $state['plan']['rows'] as $key => $row ) {
			if ( isset( $state['deleted'][ $key ] ) || isset( $state['kept'][ $key ] ) ) {
				continue;
			}
			$files = array_values( array_filter( array_keys( (array) $row['files'] ), array( Replacements::class, 'installed' ) ) );
			if ( ! $files ) {
				continue;
			}
			list( $ok, $reason ) = Replacements::deletable( $row, self::delete_facts( $state, $row, $files ) );
			if ( ! $ok ) {
				$state['kept'][ $key ] = $reason;
				continue;
			}
			$result = delete_plugins( $files );
			if ( true === $result ) {
				$state['deleted'][ $key ] = $row['label'];
			} else {
				$state['kept'][ $key ] = 'WordPress no pudo borrarlo' . ( is_wp_error( $result ) ? ': ' . $result->get_error_message() : ' (no puede escribir en la carpeta de plugins).' );
			}
			if ( microtime( true ) >= $deadline ) {
				return $state;
			}
		}
		$state = self::cleanup_theme( $state );
		return self::next( $state, 'finish' );
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
		return array(
			'active'         => $active,
			'by_active'      => Replacements::is_active( Replacements::own_file( $row['by'] ) ),
			'imported'       => self::imported( $state, $row ),
			'content'        => $content,
			'keeps_data'     => self::updraft_keeps_data(),
			'core_ready'     => Retire::core_ready(),
			// Con el núcleo incluido al mando, Elements quita de esta lista las rutinas de Elementor y Elementor Pro.
			'uninstall_safe' => ! array_intersect( $files, array_keys( $uninstall ) ),
		);
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

	/** Hello se borra si se reemplazó y ya nadie lo usa: ningún sitio lo tiene activo ni hay un tema hijo suyo instalado. */
	private static function cleanup_theme( array $state ) {
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
		if ( '' === $reason && empty( $state['caps']['themes'] ) ) {
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
		self::drop_snaps( $state );
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

	/** HTML del estado: avance de la tarea en marcha o informe de la terminada ('' sin ninguna). */
	public static function render( $network, $state ) {
		if ( ! is_array( $state ) || empty( $state['stage'] ) ) {
			return '';
		}
		return empty( $state['done'] ) ? self::render_progress( (bool) $network, $state ) : self::render_report( (bool) $network, $state );
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

	/** Informe de la tarea terminada. Todo pasa por esc_html(). */
	private static function render_report( $network, array $state ) {
		$rows  = (array) ( $state['plan']['rows'] ?? array() );
		$items = array();
		$kind  = '' !== (string) $state['failed'] ? 'error' : 'success';
		$title = '' !== (string) $state['failed'] ? sprintf( 'La migración se detuvo en «%s»: %s No se borró nada.', self::LABELS[ $state['failed'] ] ?? $state['failed'], (string) $state['error'] ) : 'Migración completada.';
		$html  = '<div class="notice notice-' . $kind . ' inline"><p><strong>' . esc_html( $title ) . '</strong></p></div>';
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
		$themes = array();
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			$where    = $network ? self::site_name( (int) $site_id ) . ': ' : '';
			$themes[] = $where . ( ! empty( $row['switched'] ) ? sprintf( 'de %s al tema DIGITALÍSIMO', $row['name'] ) : (string) $row['reason'] );
		}
		if ( $themes ) {
			$items[] = array( 'Tema', implode( ' ', $themes ) );
		}
		if ( $state['verify'] ) {
			$bad = array_filter( (array) $state['verify'], static function ( $row ) {
				return true !== $row['ok'];
			} );
			$items[] = array( 'Portada y páginas', $bad ? implode( ' ', array_map( static function ( $row ) {
				return $row['url'] . ': ' . $row['reason'];
			}, $bad ) ) : sprintf( '%d comprobadas: responden igual.', count( (array) $state['verify'] ) ) );
		}
		if ( $state['deleted'] ) {
			$items[] = array( 'Borrado', implode( ', ', array_values( (array) $state['deleted'] ) ) . '. Para recuperarlo, restaura el respaldo de arriba.' );
		}
		$kept = array();
		foreach ( (array) $state['kept'] as $key => $reason ) {
			$label  = $rows[ $key ]['label'] ?? ( 'hello-elementor' === $key ? 'Hello Elementor' : $key );
			$kept[] = $label . ': ' . $reason;
		}
		if ( $kept ) {
			$items[] = array( 'Quedó como estaba', implode( ' ', $kept ) );
		}
		foreach ( (array) $state['notes'] as $note ) {
			$items[] = array( 'Nota', $note );
		}
		$html .= '<table class="widefat striped digi-migrate__full"><tbody>';
		foreach ( $items as $item ) {
			$html .= '<tr><th scope="row">' . esc_html( $item[0] ) . '</th><td>' . esc_html( $item[1] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		if ( class_exists( Runner::class ) && ! empty( $state['content']['started'] ) ) {
			$html .= '<h3>' . esc_html( 'Contenido' ) . '</h3>' . ( $network ? Runner::render_network( Runner::network_state() ? Runner::network_summary( false ) : null ) : Runner::render( Runner::report() ) );
		}
		return $html;
	}

	private static function site_name( $site_id ) {
		return (string) self::on_site( $site_id, static function () {
			return get_bloginfo( 'name' );
		} );
	}

	/** El informe en texto para la terminal. */
	public static function text( $html ) {
		$text = str_replace( array( '</p>', '</li>', '</tr>', '</h3>', '</div>' ), "\n", (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( str_replace( array( '</td>', '</th>' ), ' · ', $text ) ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( "/\n\s*\n+/", "\n", $text ) );
	}
}
