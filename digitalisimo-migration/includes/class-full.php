<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-replacements.php';
require_once __DIR__ . '/class-packages.php';
require_once __DIR__ . '/class-front.php';

use Digitalisimo\Elements\Legacy\Retire;
use Digitalisimo\Elements\Legacy\Runner;

/** La caché de objetos devuelve los datos de otro sitio: Full::blocked() detiene la tarea sin escribir nada más. */
final class Same_Site_Exception extends \RuntimeException {}

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
	/** Días tras los que una tarea quieta o una referencia de la portada ya no valen (Full::orphan(), baseline()). */
	const STALE_DAYS = 1;
	/** Un paso de un clic cuya pasada ya no está guardada (Full::step(), `lost`). */
	const LOST = 'La migración que empezó este clic no aparece guardada en el sitio, así que no se muestra el informe anterior como si fuera el de ahora. Recarga esta pantalla: si la migración sigue en marcha, continúa sola; si no, pulsa Migrar.';

	/** @var array<string,string> tokens de los cerrojos tomados en esta petición. */
	private static $tokens = array();
	/** @var bool ¿Proceso de WP-CLI? (nunca espera a otra petición: se relanza). */
	private static $cli = false;
	/** @var float cuándo empezó el trabajo de la tarea en esta petición (no la petición: su arranque puede tardar). */
	private static $began = 0.0;
	/** @var array|null sal de la caché de objetos en esta petición (Digitalisimo_Cache_Salt::ensure()). */
	private static $salt = null;
	/** @var bool esta petición lee directo de la base porque la caché da datos de otro sitio (no por una sal recién puesta). */
	private static $foreign = false;

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
		if ( $start && ! empty( $_POST['fresh_baseline'] ) && ! self::load( $network ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
			// «Migrar tomando el sitio de ahora como referencia» (informe de una pasada que no coincidió con la de antes).
			self::drop_baseline( $network );
		}
		$run   = isset( $_POST['run'] ) ? sanitize_key( wp_unslash( $_POST['run'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
		$state = self::step( $network, $start, null, $run );
		if ( ! empty( $state['refused'] ) ) {
			wp_send_json_error( (string) $state['refused'], 409 );
		}
		if ( ! empty( $state['lost'] ) ) {
			wp_send_json_error( self::LOST, 409 );
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
			// La pasada que conduce la pantalla: un segundo clic sobre una en marcha la sigue a ella hasta su informe.
			'run'   => (string) ( $state['run'] ?? '' ),
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

	/**
	 * La tarea en marcha, leída de la base (stored()). Una tarea guardada como terminada (`done`) no está en marcha: pasa a
	 * ser la última, si es más reciente, y deja de estorbar al siguiente clic.
	 */
	public static function load( $network ) {
		$state = self::stored( $network ? self::NETWORK_JOB : self::JOB, $network );
		if ( ! is_array( $state ) ) {
			return null;
		}
		if ( ! empty( $state['done'] ) ) {
			$last = self::last( $network );
			if ( ! $last || (int) ( $last['started'] ?? 0 ) <= (int) ( $state['started'] ?? 0 ) ) {
				$network ? update_site_option( self::NETWORK_LAST, $state ) : update_option( self::LAST, $state, false );
			}
			$network ? delete_site_option( self::NETWORK_JOB ) : delete_option( self::JOB );
			return null;
		}
		if ( self::orphan( $state ) ) {
			// Revisión adversarial (bajo): una tarea que no pasó del respaldo, de otra versión de Migración o quieta desde hace
			// más de un día (como la que quedó huérfana en digitalisimo.lat), no se retoma con sus permisos y su versión de
			// entonces: se descarta (no había tocado nada) y el clic siguiente empieza una pasada nueva.
			$network ? delete_site_option( self::NETWORK_JOB ) : delete_option( self::JOB );
			return null;
		}
		return $state;
	}

	/** ¿Tarea que no llegó a tocar nada (plan o respaldo) y es de otra versión o lleva más de un día quieta? */
	private static function orphan( array $state ) {
		if ( ! in_array( (string) ( $state['stage'] ?? '' ), array( 'plan', 'backup' ), true ) || ! empty( $state['installed'] ) || ! empty( $state['activated'] ) ) {
			return false;
		}
		$version = defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '';
		return (string) ( $state['version'] ?? '' ) !== (string) $version || (int) ( $state['updated'] ?? 0 ) < time() - self::STALE_DAYS * 86400;
	}

	public static function last( $network ) {
		$state = self::stored( $network ? self::NETWORK_LAST : self::LAST, $network );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * Una opción de Migrar (la tarea, el último informe, la referencia de la portada) leída de la base, no de la caché de
	 * objetos (caso real de digitalisimo.lat, 2026-11-23): una petición que trabaja sin escribir en la caché compartida
	 * (Digitalisimo_Cache_Salt::bypass()) guarda la tarea sólo en la base, y la caché persistente sigue diciendo que no
	 * existe (`notoptions`) o dando la de antes; la petición siguiente, ya sin desvío, no la veía, contestaba «no hay
	 * tarea» y la pantalla pintaba el informe viejo como si el clic hubiera terminado. Sin caché persistente es get_option().
	 */
	public static function stored( $name, $network = false ) {
		self::same_site();
		if ( class_exists( '\Digitalisimo_Same_Site', false ) ) {
			return $network ? \Digitalisimo_Same_Site::db_site_value( $name, null ) : \Digitalisimo_Same_Site::db_value( $name, null );
		}
		return $network ? get_site_option( $name, null ) : get_option( $name, null );
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
			return array( 'install' => $mods, 'activate' => true, 'delete' => $mods, 'switch' => true, 'themes' => $mods, 'update_themes' => $mods, 'code' => $mods, 'delete_themes' => $mods );
		}
		if ( $network ) {
			$super = is_super_admin() && current_user_can( 'manage_network_plugins' );
			return array( 'install' => $super && current_user_can( 'install_plugins' ), 'activate' => $super, 'delete' => $super && current_user_can( 'delete_plugins' ), 'switch' => $super && current_user_can( 'manage_network_themes' ), 'themes' => $super && current_user_can( 'manage_network_themes' ) && current_user_can( 'install_themes' ), 'update_themes' => $super && current_user_can( 'update_themes' ), 'code' => $super && current_user_can( 'edit_themes' ) && current_user_can( 'unfiltered_html' ), 'delete_themes' => $super && current_user_can( 'delete_themes' ) );
		}
		return array( 'install' => current_user_can( 'install_plugins' ), 'activate' => current_user_can( 'activate_plugins' ), 'delete' => current_user_can( 'delete_plugins' ), 'switch' => current_user_can( 'switch_themes' ), 'themes' => current_user_can( 'switch_themes' ) && current_user_can( 'install_themes' ), 'update_themes' => current_user_can( 'update_themes' ), 'code' => current_user_can( 'edit_themes' ) && current_user_can( 'unfiltered_html' ), 'delete_themes' => current_user_can( 'delete_themes' ) );
	}

	/** ¿Deja el servidor modificar archivos? (DISALLOW_FILE_MODS y su filtro). */
	private static function file_mods() {
		return ! function_exists( 'wp_is_file_mod_allowed' ) || wp_is_file_mod_allowed( 'digitalisimo_migration' );
	}

	/** Por qué no se instala ni se actualiza: la regla del servidor o el permiso de quien pulsó. */
	private static function no_install() {
		return self::file_mods() ? 'quien pulsó Migrar no puede instalar ni actualizar plugins' : 'una regla del servidor (DISALLOW_FILE_MODS) no deja instalar ni actualizar plugins';
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
			'upgraded'   => array(),
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
			self::isolation();
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
				// Un paso de un clic ($run) cuya pasada no está en marcha ni es la última terminada: se perdió. Nunca se
				// contesta con el informe anterior como si fuera el de este clic.
				if ( ! $start && '' !== (string) $run && (string) $run !== (string) ( self::last( $network )['run'] ?? '' ) ) {
					return array( 'idle' => true, 'lost' => true );
				}
				return array( 'idle' => true );
			}
			$state = self::salted( $state );
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
		// La sal ya se puso (o esta petición lee directo de la base) en isolation(), antes de mirar nada.
		// Candado «mismo sitio»: con la caché de objetos devolviendo los datos de otro sitio, nada empieza.
		$alien = self::alien();
		if ( '' !== $alien ) {
			return $alien . ' No se tocó nada.';
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

	/**
	 * Candado «mismo sitio» (class-digitalisimo-same-site.php, copia idéntica en Backups y Tools): '' si este WordPress
	 * lee sus propios datos; si la caché de objetos devuelve los de otro sitio (Redis, Memcached o LSMCD compartidos sin
	 * WP_CACHE_KEY_SALT), el motivo. Se comprueba antes de empezar, antes de cada etapa y justo antes de activar un plugin.
	 */
	public static function alien() {
		self::same_site();
		return class_exists( '\Digitalisimo_Same_Site', false ) ? \Digitalisimo_Same_Site::refusal( 'Migrar' ) : '';
	}

	/** Carga el candado «mismo sitio» (y sus lecturas directas de la base) si hace falta. */
	private static function same_site() {
		if ( ! class_exists( '\Digitalisimo_Same_Site', false ) && is_file( __DIR__ . '/class-digitalisimo-same-site.php' ) ) {
			require_once __DIR__ . '/class-digitalisimo-same-site.php';
		}
	}

	/**
	 * Sal propia de la caché de objetos (reglas del propietario: «nadie edita wp-config.php a mano» y «no se trata de
	 * bloquear el config, se trata de que uno al migrar no haga nada»). Migración lleva su copia de
	 * Digitalisimo_Cache_Salt (idéntica a la de Tools): con caché externa sin sal, la agrega antes de nada; con un clon
	 * o con la caché dando datos ajenos, la cambia. $retry repite un intento que no pudo escribir.
	 */
	private static function cache_salt( $retry = false ) {
		self::alien(); // Carga el candado si hace falta.
		if ( ! class_exists( '\\Digitalisimo_Cache_Salt', false ) && is_file( __DIR__ . '/class-digitalisimo-cache-salt.php' ) ) {
			require_once __DIR__ . '/class-digitalisimo-cache-salt.php';
		}
		if ( ! class_exists( '\\Digitalisimo_Cache_Salt', false ) || ! class_exists( '\\Digitalisimo_Same_Site', false ) ) {
			return array();
		}
		return (array) \Digitalisimo_Cache_Salt::ensure( 'migration', $retry );
	}

	/**
	 * Al principio de cada petición de Migrar: la sal (cache_salt()) y, sólo si la sal se acaba de escribir (vale desde
	 * la petición siguiente) o el candado ve de verdad datos de otro sitio, las opciones, entradas y cuentas se leen
	 * directo de la base en esta petición, sin escribir ni vaciar la caché compartida (Digitalisimo_Cache_Salt::bypass()).
	 * Sin choque, aunque no se haya podido poner la sal, Migrar trabaja como siempre (el candado sigue protegiendo).
	 */
	private static function isolation( $retry = false ) {
		self::$salt = self::cache_salt( $retry );
		$foreign    = class_exists( '\\Digitalisimo_Same_Site', false ) && ! \Digitalisimo_Same_Site::ok();
		if ( 'written' === (string) ( self::$salt['state'] ?? '' ) || $foreign ) {
			\Digitalisimo_Cache_Salt::bypass();
			self::$foreign = $foreign && 'written' !== (string) ( self::$salt['state'] ?? '' );
		}
		return self::$salt;
	}

	/** Anota la sal en la tarea; si se acaba de escribir, sigue en una petición nueva (la constante vale desde ahí). */
	private static function salted( array $state ) {
		$salt  = (array) self::$salt;
		$now   = (string) ( $salt['state'] ?? '' );
		if ( 'written' === $now ) {
			$state = self::note( $state, sprintf( 'La caché de objetos no tenía una sal propia que la separara de otros sitios: se agregó a %1$s (%2$s), con copia previa en %3$s.', basename( (string) ( $salt['file'] ?? 'wp-config.php' ) ), (string) ( $salt['salt'] ?? '' ), (string) ( $salt['backup'] ?? '' ) ) );
			$state['salt'] = $now;
			return self::changed( $state );
		}
		if ( self::$foreign ) {
			// Lo que se escribe en esta petición no llega a la caché persistente: al volver a una petición normal, se
			// invalida lo de este sitio para que no siga sirviendo lo de antes de Migrar.
			$state             = self::note( $state, 'La caché de objetos daba datos de otro sitio y no se pudo separar (' . (string) ( $salt['message'] ?? '' ) . '): Migrar trabajó leyendo directo de la base, sin escribir ni vaciar la caché compartida.' );
			$state['bypassed'] = true;
		} elseif ( ! empty( $state['bypassed'] ) && class_exists( '\\Digitalisimo_Same_Site', false ) ) {
			$plan               = (array) ( $state['cache_plan'] ?? array() );
			$clean              = $plan ? \Digitalisimo_Same_Site::resume_invalidation( $plan, 5 ) : \Digitalisimo_Same_Site::invalidate_site( ! empty( $state['network'] ) ? 'all' : null, null, 5 );
			$state['cache_plan'] = empty( $clean['done'] ) ? (array) ( $clean['plan'] ?? array() ) : array();
			if ( ! empty( $clean['done'] ) ) {
				unset( $state['bypassed'], $state['cache_plan'] );
			}
		}
		if ( '' !== $now ) {
			$state['salt'] = $now;
		}
		return $state;
	}

	/** Lanza Same_Site_Exception si la caché da otro sitio (justo antes de una escritura de «leer y volver a guardar»). */
	private static function assert_same_site() {
		$alien = self::alien();
		if ( '' !== $alien ) {
			throw new Same_Site_Exception( $alien );
		}
	}

	/**
	 * La caché da los datos de otro sitio: la tarea termina aquí sin escribir nada más en el sitio (ni volver al tema
	 * anterior, ni tocar el cron: leer y volver a guardar mezclaría los dos sitios). El informe lo dice; al volver a pulsar
	 * Migrar, con la caché corregida, la tarea empieza de nuevo y comprueba lo que ya estuviera hecho.
	 */
	private static function blocked( array $state, $message ) {
		$stage             = (string) ( $state['stage'] ?? 'plan' );
		$state['failed']   = $stage;
		$state['blocked']  = true;
		$state['error']    = (string) $message . ( in_array( $stage, array( 'plan', 'backup' ), true ) && empty( $state['activated'] ) && empty( $state['installed'] ) ? ' No se tocó nada.' : sprintf( ' La migración se detuvo antes de «%s» sin escribir nada más; lo hecho antes queda como estaba y se comprueba al volver a pulsar Migrar.', self::LABELS[ $stage ] ?? $stage ) );
		$state['done']     = true;
		$state['pause']    = false;
		$state['finished'] = time();
		unset( $state['snap'], $state['checks'], $state['drive'] );
		if ( ! empty( $state['network'] ) ) {
			update_site_option( self::NETWORK_LAST, $state );
			delete_site_option( self::NETWORK_JOB );
		} else {
			update_option( self::LAST, $state, false );
			delete_option( self::JOB );
		}
		return $state;
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
			// Antes de cada etapa: la caché de objetos puede empezar a dar los datos de otro sitio a mitad de la tarea.
			$alien = self::alien();
			if ( '' !== $alien ) {
				return self::blocked( $state, $alien );
			}
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
			} catch ( Same_Site_Exception $e ) {
				return self::blocked( $state, $e->getMessage() );
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

	/** ¿Dejó esta pasada el sitio a medias? (contenido convertido, plugins retirados o algún sitio con el tema cambiado). */
	private static function left_altered( array $state ) {
		if ( ! empty( $state['retired'] ) || ! empty( $state['content']['started'] ) || ! empty( $state['deleted'] ) ) {
			return true;
		}
		foreach ( (array) $state['theme'] as $row ) {
			if ( ! empty( $row['switched'] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Borra la referencia de la portada: el dueño pidió empezar de nuevo desde el sitio de ahora. */
	public static function drop_baseline( $network ) {
		$network ? delete_site_option( self::BASELINE ) : delete_option( self::BASELINE );
	}

	/** La referencia guardada (o null). */
	public static function baseline( $network ) {
		$baseline = self::stored( self::BASELINE, $network );
		$last     = self::last( $network );
		$version  = defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '';
		// Revisiones adversariales H6 y NUEVA 3: la referencia de la portada de antes de la primera pasada sólo protege la
		// pasada siguiente a una que dejó el sitio a medias (contenido convertido, plugins retirados o el tema cambiado):
		// ahí la portada de ahora ya puede estar migrada y daría por buena cualquier cosa. Deja de valer si una pasada
		// posterior terminó verificada, si la pasada anterior no dejó el sitio alterado (volvió atrás bien o se detuvo antes
		// de tocar nada) o si es de otra versión de Migración (como la del 19-nov de digitalisimo.lat): entonces se toma la
		// del sitio de ahora. Si aun así no coincide, el informe ofrece empezar de nuevo desde el sitio de ahora (Full::ajax(),
		// `fresh_baseline`), así que nunca bloquea para siempre.
		$stale = is_array( $baseline ) && (
			( $last && ! empty( $last['verified'] ) && (int) ( $last['started'] ?? 0 ) >= (int) ( $baseline['started'] ?? 0 ) )
			|| empty( $baseline['altered'] )
			|| (string) ( $baseline['version'] ?? '' ) !== (string) $version
		);
		if ( $stale ) {
			$network ? delete_site_option( self::BASELINE ) : delete_option( self::BASELINE );
			return null;
		}
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
		if ( ! self::baseline( $network ) && ! empty( $state['checks'] ) && self::left_altered( $state ) ) {
			$baseline = array( 'started' => (int) self::snap_owner( $state ), 'at' => time(), 'version' => defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '', 'altered' => true, 'checks' => array_values( (array) $state['checks'] ) );
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
				return self::fail( $state, sprintf( 'Hace falta %s y %s.', Replacements::own_label( $slug ), self::no_install() ) );
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
		// Activo pero sin lo que se usa: o lo acaba de activar o actualizar otra petición, o es una versión anterior, que
		// Migrar actualiza desde la Release en la misma pasada (una vez), como el tema.
		if ( ! empty( $state['changed_at'] ) && microtime( true ) - (float) $state['changed_at'] < 300 ) {
			return self::wait( $state );
		}
		$updated = self::update_own( $state, $slug );
		if ( null !== $updated ) {
			return $updated;
		}
		return self::fail( $state, sprintf( '%s está activo pero no trae lo que Migrar necesita, ni siquiera con la versión de la Release publicada.', Replacements::own_label( $slug ) ) );
	}

	/**
	 * Actualiza desde la Release un plugin DIGITALÍSIMO instalado que no trae lo que Migrar necesita (una vez por pasada).
	 * Devuelve el estado siguiente (sigue en una petición nueva, con el código nuevo cargado, o falla con el motivo) o null
	 * si ya se intentó o la Release no trae una versión más nueva.
	 */
	private static function update_own( array $state, $slug ) {
		if ( in_array( $slug, (array) ( $state['update_tried'] ?? array() ), true ) ) {
			return null;
		}
		$state['update_tried'][] = $slug;
		if ( empty( $state['caps']['install'] ) ) {
			return self::fail( $state, sprintf( '%s es una versión anterior a la que Migrar necesita y %s.', Replacements::own_label( $slug ), self::no_install() ) );
		}
		// La actualización y la reactivación leen y guardan `active_plugins`: con la caché de otro sitio, nada.
		self::assert_same_site();
		$result = Packages::update_plugin( $slug );
		if ( is_wp_error( $result ) ) {
			return self::fail( $state, sprintf( 'digitalisimo_migration_reactivate' === $result->get_error_code() ? '%s se actualizó, pero %s' : 'No se pudo actualizar %s a la versión de la Release: %s', Replacements::own_label( $slug ), $result->get_error_message() ) );
		}
		if ( 'updated' !== $result['action'] ) {
			return null;
		}
		$state['upgraded'][ $slug ] = array( 'from' => $result['from'], 'to' => $result['to'] );
		return self::changed( $state );
	}

	/** Activa archivos en este sitio o, en una red, en toda la red. */
	private static function activate( array $state, array $files ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( $files as $file ) {
			// activate_plugin() lee `active_plugins` y lo vuelve a guardar: con la caché de otro sitio guardaría su lista aquí.
			$alien = self::alien();
			if ( '' !== $alien ) {
				throw new Same_Site_Exception( $alien );
			}
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
			// Sólo el respaldo que encoló Migrar: nunca otro trabajo de la cola (ni el de otra instalación).
			$jobs::tick( max( 5, (int) floor( $deadline - microtime( true ) ) ), (string) $state['backup']['job'] );
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
				return self::fail( $state, sprintf( 'Hace falta %s y %s.', Replacements::own_label( $slug ), self::no_install() ) );
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
		if ( in_array( (string) ( self::$salt['state'] ?? '' ), array( 'manual', 'failed' ), true ) ) {
			// Lo instalado pudo cambiar lo que impedía poner la sal: otro intento antes de seguir.
			self::isolation( true );
			$state = self::salted( $state );
			if ( ! empty( $state['pause'] ) ) {
				return $state;
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
			if ( 'outdated' === $result[1] ) {
				// Un plugin DIGITALÍSIMO anterior al que Migrar usa: se actualiza desde la Release y la etapa sigue en una
				// petición nueva, con el código nuevo cargado (una vez por pasada).
				$updated = self::update_own( $state, (string) $result[2] );
				if ( null !== $updated ) {
					return $updated;
				}
				return self::fail( $state, (string) $result[3] . ' Ni la versión de la Release publicada lo trae.' );
			}
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
					return array( $state, 'outdated', 'digitalisimo-seo', 'Esta versión de DIGITALÍSIMO SEO no puede importar Yoast SEO sin su pantalla.' );
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
						return array( $state, 'outdated', 'digitalisimo-backups', 'DIGITALÍSIMO Backups no cargó la lectura de los respaldos de UpdraftPlus y Backuply.' );
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
					self::assert_same_site();
					deactivate_plugins( $sitewide, false, true );
					$any = true;
				}
				foreach ( self::targets( $state ) as $site_id ) {
					$any = self::on_site( $site_id, static function () use ( $files ) {
						$local = array_values( array_intersect( array_reverse( $files ), (array) get_option( 'active_plugins', array() ) ) );
						if ( $local ) {
							self::assert_same_site();
							deactivate_plugins( $local, false, false );
						}
						return (bool) $local;
					} ) || $any;
				}
			} elseif ( $here ) {
				self::assert_same_site();
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
	 * ¿Se puede pasar este sitio al tema propio? Desde Hello y desde un hijo suyo, y lo hace Migrar entero (reglas del
	 * propietario del 2026-11-20 y del 2026-11-23: «no tengo por qué cambiar el tema yo, debe ser Migrar: todo lo debe
	 * hacer»): las hojas y fuentes del hijo las carga el tema propio; el resto de su functions.php va a Código PHP de
	 * DIGITALÍSIMO Tools, que Migrar instala, activa o actualiza si hace falta, y lo activa por la vía firmada (dentro de la
	 * condición del tema propio o, si el archivo no la admite, justo después del cambio); sus plantillas, su theme.json o un
	 * PHP que podría hacer otra cosa con el tema propio (`risks`) no impiden el cambio: la verificación compara la portada,
	 * las páginas y las que usan esas plantillas y, si algo no responde o no se pinta igual, Migrar vuelve solo al hijo y el
	 * informe dice qué página y qué cambió. Nunca termina en «cámbialo a mano»: sólo se queda por un permiso o una regla del
	 * servidor que Migrar no puede saltarse, y el motivo lo dice. `$facts['child']`: `analyzed`, `blockers` (texto, sólo
	 * functions.php), `files`, `risks`, `covered`, `imported` (lo que contestó Tools), `wrap`, `tools`, `filter`, `activate`
	 * y `tools_error` (por qué Migrar no pudo dejar Tools listo). Devuelve [bool, motivo].
	 *
	 * @return array{0:bool,1:string}
	 */
	public static function theme_safety( array $theme, array $facts ) {
		if ( ! empty( $theme['own'] ) ) {
			return array( false, 'El tema DIGITALÍSIMO ya está activo.' );
		}
		if ( empty( $theme['hello'] ) ) {
			return array( false, sprintf( 'El tema activo («%s») no es Hello Elementor ni un hijo suyo: el tema DIGITALÍSIMO reproduce a Hello, así que con este tema cambiaría lo que se ve. Se queda %s.', (string) $theme['name'], (string) $theme['name'] ) );
		}
		if ( empty( $facts['builder'] ) ) {
			return array( false, 'El Theme Builder de DIGITALÍSIMO Elements no está cargado: la cabecera y el pie de Hello no podrían pasar a plantillas. Se queda ' . (string) $theme['name'] . '.' );
		}
		if ( ! empty( $facts['conflict'] ) ) {
			return array( false, 'Elementor Pro o PRO Elements sigue activo: el Theme Builder de DIGITALÍSIMO Elements no carga mientras tanto. Se queda ' . (string) $theme['name'] . '.' );
		}
		if ( empty( $facts['can'] ) ) {
			return array( false, ( self::file_mods() ? 'Quien pulsó Migrar no puede cambiar de tema (ni instalar el tema DIGITALÍSIMO) en este sitio.' : 'Una regla del servidor (DISALLOW_FILE_MODS) no deja instalar el tema DIGITALÍSIMO, que aún no está instalado.' ) . ' Se queda ' . (string) $theme['name'] . '.' );
		}
		if ( empty( $theme['child'] ) ) {
			return array( true, '' );
		}
		$child = (array) ( $facts['child'] ?? array() );
		$name  = (string) $theme['name'];
		if ( ! empty( $child['not_child'] ) ) {
			return array( false, sprintf( 'El tema activo ya no es el tema hijo «%s»: no se lee ni se importa nada de él.', $name ) );
		}
		if ( empty( $child['analyzed'] ) ) {
			return array( false, sprintf( 'El tema DIGITALÍSIMO instalado no sabe leer el tema hijo («%s»): es anterior a 1.0.1 y no se pudo poner al día. Se queda el tema hijo.', $name ) );
		}
		// Sin PHP propio en functions.php (sólo hojas y fuentes, plantillas o theme.json): el cambio se hace y se comprueba.
		if ( '' === (string) ( $child['blockers'] ?? '' ) || ! empty( $child['covered'] ) ) {
			return array( true, '' );
		}
		$activate = (string) ( $child['activate'] ?? '' );
		if ( in_array( $activate, array( 'activated', 'active', 'after_switch' ), true ) ) {
			return array( true, '' );
		}
		$intro    = sprintf( 'El tema hijo de Hello («%s») tiene PHP propio en su functions.php (%s), que con el tema DIGITALÍSIMO corre desde Código PHP de DIGITALÍSIMO Tools.', $name, (string) $child['blockers'] );
		$imported = (string) ( $child['imported'] ?? '' );
		$why      = array(
			'nocaps'    => 'quien pulsó Migrar no puede activar código PHP en este sitio (le faltan los permisos edit_themes y unfiltered_html; en un sitio individual suele ser DISALLOW_FILE_EDIT en wp-config.php)',
			'old_tools' => 'la versión de DIGITALÍSIMO Tools instalada no puede activarlo y la de la Release no se pudo instalar',
			'changed'   => 'una persona desactivó o cambió su fragmento en DIGITALÍSIMO Tools, y Migrar no deshace esa decisión: si quieres que ese PHP corra con el tema DIGITALÍSIMO, actívalo tú en DIGITALÍSIMO Tools → Código PHP y Migrar lo llevará en la siguiente pasada',
			'failed'    => 'DIGITALÍSIMO Tools ya lo detuvo antes por un error de PHP',
			'blocked'   => 'este servidor cierra la edición de código (DISALLOW_FILE_EDIT o DISALLOW_FILE_MODS) y DIGITALÍSIMO Tools no activa código PHP con esa regla',
			'reduced'   => 'Código PHP de DIGITALÍSIMO Tools está en modo seguro',
			'check'     => 'la revisión de DIGITALÍSIMO Tools encontró un error en ese código',
			'error'     => 'DIGITALÍSIMO Tools no pudo activarlo (otra acción sobre los fragmentos estaba en curso o no cabe en sus topes)',
			'missing'   => 'no apareció en DIGITALÍSIMO Tools para activarlo',
			'mismatch'  => 'una persona editó su fragmento en DIGITALÍSIMO Tools y ya no es el functions.php del hijo; Migrar no sustituye lo editado: revisa esa versión en DIGITALÍSIMO Tools → Código PHP y, si es la buena, actívala',
			'signature' => 'el fragmento no lleva la firma de su importación (se cambió fuera de Tools o cambiaron las claves de wp-config.php)',
		);
		if ( empty( $child['tools'] ) ) {
			$because = 'DIGITALÍSIMO Tools no quedó activo en este sitio' . ( '' !== (string) ( $child['tools_error'] ?? '' ) ? ': ' . rtrim( (string) $child['tools_error'], '. ' ) : '' );
		} elseif ( array_key_exists( 'filter', $child ) && empty( $child['filter'] ) ) {
			$because = 'Migrar corre desde el sitio principal de la red y ahí DIGITALÍSIMO Tools no está cargado';
		} elseif ( isset( $why[ $activate ] ) ) {
			$because = $why[ $activate ] . ( 'old_tools' === $activate && '' !== (string) ( $child['tools_error'] ?? '' ) ? ' (' . rtrim( (string) $child['tools_error'], '. ' ) . ')' : '' );
		} elseif ( 'conflict' === $imported ) {
			$because = 'una persona editó su fragmento en DIGITALÍSIMO Tools después de importarlo, y Migrar no sustituye lo editado: revisa esa versión en DIGITALÍSIMO Tools → Código PHP y, si es la buena, actívala';
		} elseif ( 'skipped' === $imported ) {
			$because = 'una persona borró su fragmento en DIGITALÍSIMO Tools (o no cabe en sus topes), y Migrar no vuelve a crear lo que alguien borró';
		} else {
			$because = 'su functions.php no se pudo pasar a DIGITALÍSIMO Tools (no se pudo leer, no empieza con <?php o pasa del tamaño que admite)';
		}
		return array( false, $intro . ' No se activó porque ' . $because . '. Se queda el tema hijo para no perder lo que ese PHP hace.' );
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
			// Con el tema propio ya instalado basta con poder cambiar de tema (switch_themes); si falta, también instalarlo.
			'can'      => ! empty( $state['caps']['switch'] ?? $state['caps']['themes'] ) && ( ! empty( $state['caps']['themes'] ) || wp_get_theme( Replacements::THEME )->exists() ),
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
	/** Filtro de Tools (Import::reset_import()): vuelve a dejar importable un functions.php del hijo borrado o cambiado. */
	const SNIPPET_RESET      = 'digitalisimo_tools_php_reset_import';

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
			if ( self::own_active( (int) $site_id ) ) {
				// Revisión adversarial H1: con el tema propio activo, el PHP del hijo sólo corre por ese fragmento: nunca se
				// apaga aquí (lo comprueba verify o lo apaga la vuelta atrás de verdad).
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
	 * Hechos del tema hijo activo en el sitio en curso: lo lee sin ejecutarlo y, si su functions.php tiene PHP propio, lo
	 * deja en Código PHP de Tools (si Tools está activo en este sitio; en una red, su filtro puede estar cargado por el
	 * sitio principal) y lo activa por la vía firmada de Tools. Un fragmento que se borró o se cambió en Tools (sin estar
	 * corriendo) se vuelve a traer del hijo tal como está en disco; uno que no admite la condición del tema propio se activa
	 * justo después del cambio (`after_switch`). Lo que el tema propio no traslada (plantillas, theme.json, `risks`) no frena
	 * la importación: lo decide la verificación de la portada.
	 */
	private static function child_facts( array $state = array() ) {
		$child = wp_get_theme();
		if ( ! self::child_reader() || ! $child->exists() ) {
			return array( 'analyzed' => false );
		}
		if ( (string) $child->get_template() === (string) $child->get_stylesheet() || Replacements::is_own_theme( (string) $child->get_stylesheet() ) ) {
			// El tema activo ya no es un hijo (p. ej. el propio, tras un cambio que no llegó a guardarse): nunca se lee ni se
			// importa su functions.php como si lo fuera.
			return array( 'analyzed' => false, 'not_child' => true );
		}
		$plan = digitalisimo_theme_child_plan( $child );
		$php  = array();
		$rest = array();
		foreach ( (array) $plan['blockers'] as $blocker ) {
			if ( 'functions.php' === ( $blocker['file'] ?? '' ) ) {
				$php[] = $blocker;
			}
		}
		// Lo que no es functions.php lo lee Migrar (child_other_files()), no el lector del tema instalado.
		$rest = self::child_other_files( $child );
		// Un fragmento activo en Tools sólo sustituye functions.php: lo demás (plantillas, theme.json) va aparte.
		$blockers = $php ? digitalisimo_theme_child_blockers_text( $php ) : '';
		$files    = $rest ? digitalisimo_theme_child_blockers_text( $rest ) : '';
		$risks    = ! empty( $plan['risks'] ) ? digitalisimo_theme_child_blockers_text( (array) $plan['risks'] ) : '';
		$tools    = Replacements::is_active( self::TOOLS_FILE );
		$covered  = '' !== $blockers && $tools && digitalisimo_theme_child_php_covered( $child );
		$code     = digitalisimo_theme_child_php_code( $child );
		$readable = '' !== $code && "\0" !== $code;
		// La misma envoltura que importa el tema (con la carpeta real del hijo y su contexto: reescribe sus rutas), para que
		// el hash que se pasa a Tools sea el del código importado.
		$wrap     = $readable ? ( function_exists( 'digitalisimo_theme_child_ctx' ) ? digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ), (string) $child->get_stylesheet(), digitalisimo_theme_child_ctx( $child ) ) : digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ) ) ) : '';
		// Sin la condición, Tools guarda el functions.php tal cual (digitalisimo_theme_child_import_php()).
		$hash     = '' !== $wrap ? hash( 'sha256', $wrap ) : ( $readable ? hash( 'sha256', $code ) : '' );
		$key      = self::snippet_key( $child->get_stylesheet() );
		// Lo que podría cambiar de sentido con el tema propio (`risks`) también se importa: lo comprueba la verificación.
		$import   = array( 'risks' => array() ) + $plan;
		$imported = '' !== $blockers && $tools && ! $covered ? (string) digitalisimo_theme_child_import_php( $child, $import ) : '';
		$activate = '';
		if ( '' !== $hash && in_array( $imported, array( 'created', 'updated', 'unchanged' ), true ) ) {
			// «Migrar lo hace todo» (reglas del propietario del 2026-11-20 y del 2026-11-23): el functions.php del hijo, ya en
			// Código PHP de Tools, se activa en la misma pasada por la vía de Tools (firma y guardián). Dentro de la condición
			// del tema propio, ya ahora (con el hijo activo no hace nada); sin ella, justo después del cambio de tema y en la
			// misma petición, antes de que ninguna otra corra con los dos a la vez. La prueba con el código corriendo es la de
			// verify, que vuelve atrás y lo desactiva si la portada no responde igual.
			if ( empty( $state['caps']['code'] ) ) {
				$activate = 'nocaps';
			} elseif ( ! has_filter( self::SNIPPET_ACTIVATE ) ) {
				$activate = 'old_tools';
			} elseif ( '' === $wrap ) {
				$activate = 'after_switch';
			} else {
				// Tools compara el código con este hash, recalculado ahora del functions.php en disco: sólo se activa eso.
				// Un fragmento que una persona borró, editó o desactivó en Tools, o que el guardián detuvo, no se toca: Tools
				// contesta el motivo (`skipped`, `conflict`, `mismatch`, `changed`, `failed`) y el informe lo dice.
				$activate = self::activate_snippet( $state, $key, $hash );
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
			// Tools sin lo que Migrar usa: hay que ponerlo al día (ensure_tools()).
			'tools_old' => $tools && ( ! has_filter( self::SNIPPET_ACTIVATE ) || ! has_filter( self::SNIPPET_RESET ) ),
			'hash'     => $hash,
			// Constantes de ruta de Hello (HELLO_THEME_PATH, *_URL…): con ellas, el PHP del hijo puede leer la carpeta de
			// Hello, así que Hello no se borra.
			'hello_paths' => $readable && 1 === preg_match( self::HELLO_PATHS, $code ),
		);
	}

	/**
	 * Revisión adversarial H2: los archivos del hijo que no son functions.php y pueden pintar algo (plantillas de página que
	 * algún contenido usa, PHP con código, HTML, theme.json, plantillas de bloques), como digitalisimo_theme_child_other_files()
	 * del tema, pero contando sólo esos archivos: imágenes, SVG y fuentes no agotan el tope de 2000. Si aun así se llega al
	 * tope, lo que queda sin revisar cuenta como plantilla desconocida, así que el hijo se queda. Lo lee Migrar (no el tema
	 * instalado) para no depender de su versión. Devuelve bloqueos { file, line, what }.
	 */
	public static function child_other_files( $child ) {
		$dir      = untrailingslashit( (string) $child->get_stylesheet_directory() );
		$blockers = array();
		if ( ! is_dir( $dir ) ) {
			return $blockers;
		}
		$templates = array();
		if ( function_exists( 'digitalisimo_theme_child_page_templates' ) ) {
			foreach ( digitalisimo_theme_child_page_templates( $child ) as $row ) {
				$templates[ (string) $row['template'] ] = (int) $row['pages'];
			}
		}
		try {
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD );
			$seen  = 0;
			foreach ( $files as $file ) {
				$rel = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) ) ), '/' );
				$ext = strtolower( (string) $file->getExtension() );
				if ( 'functions.php' === $rel || ! $file->isFile() ) {
					continue;
				}
				$block = 'theme.json' === $rel || preg_match( '#^(templates|parts|block-templates|block-template-parts)/#', $rel );
				if ( ! $block && ! in_array( $ext, array( 'php', 'phtml', 'inc', 'html', 'htm' ), true ) ) {
					continue;
				}
				if ( ++$seen > 2000 || count( $blockers ) >= 20 ) {
					$blockers[] = array( 'file' => '', 'line' => 0, 'what' => 'más archivos de plantilla de los que se revisan (quedan sin revisar)' );
					break;
				}
				if ( $block ) {
					$blockers[] = array( 'file' => $rel, 'line' => 0, 'what' => 'theme.json' === $rel ? 'estilos globales propios (theme.json)' : 'plantilla de bloques' );
					continue;
				}
				if ( ! in_array( $ext, array( 'php', 'phtml', 'inc' ), true ) ) {
					continue;
				}
				if ( isset( $templates[ $rel ] ) ) {
					if ( $templates[ $rel ] > 0 ) {
						$blockers[] = array( 'file' => $rel, 'line' => 0, 'what' => sprintf( 'plantilla de página que usan %d contenidos', $templates[ $rel ] ) );
					}
					continue;
				}
				$code = $file->isReadable() && $file->getSize() <= 1048576 ? (string) file_get_contents( $file->getPathname() ) : '<?php x();'; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( function_exists( 'digitalisimo_theme_child_php_is_empty' ) && digitalisimo_theme_child_php_is_empty( $code ) ) {
					continue;
				}
				$blockers[] = array( 'file' => $rel, 'line' => 0, 'what' => preg_match( '#^(header|footer|index|single|page|archive|search|404|comments|sidebar|front-page|home)\.php$|^(template-parts|woocommerce)/#', $rel ) ? 'plantilla que sustituye la del tema' : 'archivo PHP propio' );
			}
		} catch ( \UnexpectedValueException $e ) {
			$blockers[] = array( 'file' => '', 'line' => 0, 'what' => 'carpeta del hijo ilegible' );
		}
		return $blockers;
	}

	/** Activa en Tools el functions.php importado del hijo ($key) si su código es exactamente $hash. */
	private static function activate_snippet( array $state, $key, $hash ) {
		return (string) apply_filters( self::SNIPPET_ACTIVATE, null, $key, array( 'by' => (int) ( $state['user'] ?? 0 ), 'hash' => (string) $hash ) );
	}

	/** Lee el hijo del sitio (con el tema propio instalado) y decide con theme_safety(); guarda sus hechos en la fila. */
	private static function child_check( array $state, $site_id ) {
		$facts               = self::on_site( $site_id, static function () use ( $state ) {
			return self::child_facts( $state );
		} );
		$theme               = array( 'own' => false, 'hello' => true, 'child' => true, 'name' => $state['theme'][ $site_id ]['name'] );
		$facts['tools_error'] = (string) ( $state['tools_error'] ?? '' );
		list( $ok, $reason ) = self::theme_safety( $theme, array( 'builder' => true, 'conflict' => false, 'can' => true, 'child' => $facts ) );
		$state['theme'][ $site_id ]['child_facts'] = array_intersect_key( $facts, array_flip( array( 'blockers', 'files', 'risks', 'covered', 'carried', 'tools', 'tools_old', 'filter', 'wrap', 'imported', 'activate', 'hash', 'hello_paths' ) ) );
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
				if ( ! empty( $row['own'] ) ) {
					// Revisión adversarial (hueco de H1): el tema propio ya está activo, pero el PHP del hijo del que venía
					// pudo quedar sin activar (una pasada que murió en el cambio y no se retomó). Migrar lo activa o lo dice.
					$state = self::own_child_php( $state, (int) $site_id );
				}
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
				foreach ( $safe as $site_id ) {
					$state['theme'][ $site_id ]['safe']   = false;
					$state['theme'][ $site_id ]['reason'] = $what . rtrim( $installed->get_error_message(), '.' ) . '. Se queda ' . $state['theme'][ $site_id ]['name'] . ', sin cambiar el tema.';
				}
				return self::next( $state, 'verify' );
			}
			if ( ! empty( $state['theme_updated_now'] ) ) {
				// Recién actualizado: el lector del hijo de la versión anterior puede seguir cargado en esta petición (el tema
				// activo en el sitio principal, o una lectura anterior). Los hijos se leen en una petición nueva, justo antes
				// del cambio (la segunda vuelta de esta etapa vuelve a leer cada hijo).
				return self::changed( $state );
			}
			// Un hijo de Hello: se lee con el lector del tema propio ya instalado (lo que se trae y lo que no).
			return self::read_children( $state, $deadline );
		}
		if ( empty( $state['children_read'] ) ) {
			// El tema propio se actualizó en la vuelta anterior, o la lectura de los hijos o las huellas de sus plantillas no
			// terminaron: se leen ahora (las huellas siguen donde quedaron), siempre antes del cambio.
			$state = self::read_children( $state, $deadline );
			if ( ! empty( $state['pause'] ) ) {
				return $state;
			}
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
		$safe    = array();
		$already = array();
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			if ( empty( $row['safe'] ) ) {
				continue;
			}
			if ( ! empty( $row['switching'] ) ) {
				// Una vuelta murió durante el cambio: si el tema activo ya es el propio, cuenta como cambiado.
				unset( $state['theme'][ $site_id ]['switching'] );
				if ( self::own_active( (int) $site_id ) ) {
					$state['theme'][ $site_id ]['switched'] = true;
					$row['switched']                        = true;
				}
			}
			if ( ! empty( $row['switched'] ) ) {
				// Cambiado en una vuelta que murió antes de pasar a verify: el tema activo ya es el propio (no se vuelve a leer
				// como si fuera el hijo ni se cambia otra vez).
				$already[] = (int) $site_id;
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
		$state = self::drop_snippets( $state, array_merge( $safe, $already ) );
		foreach ( $already as $site_id ) {
			$facts = (array) ( $state['theme'][ $site_id ]['child_facts'] ?? array() );
			if ( 'after_switch' === (string) ( $facts['activate'] ?? '' ) && empty( $state['theme'][ $site_id ]['snippet'] ) ) {
				$state = self::activate_after_switch( $state, (int) $site_id, $facts );
			}
		}
		if ( ! $safe ) {
			return $already ? self::changed( self::next( $state, 'verify' ) ) : self::next( $state, 'verify' );
		}
		if ( ! empty( $state['network'] ) && class_exists( '\\WP_Theme' ) && method_exists( '\\WP_Theme', 'network_enable_theme' ) ) {
			// Excepción declarada (AGENTS.md, «Migrar toda la red»): el tema propio queda habilitado en la red para
			// poder activarlo en los sitios donde es seguro. No toca ningún otro tema.
			\WP_Theme::network_enable_theme( Replacements::THEME );
			$state['theme_enabled'] = true;
		}
		foreach ( $safe as $site_id ) {
			// Revisión adversarial H1: la intención se guarda antes del cambio. WordPress escribe el tema nuevo antes de correr
			// los ganchos de switch_theme: si uno lanza o la petición muere ahí, la vuelta siguiente (y la vuelta atrás) ven por
			// `switching` y el tema activo que este sitio ya cambió.
			$state['theme'][ $site_id ]['switching'] = Replacements::THEME;
			self::store( $state );
			self::on_site( $site_id, static function () {
				self::switch_theme_to( Replacements::THEME );
			} );
			unset( $state['theme'][ $site_id ]['switching'] );
			$state['theme'][ $site_id ]['switched'] = true;
			// Guardado enseguida, antes de nada más (activar el fragmento firma, revisa y manda correos): si la petición muere
			// o algo lanza, la vuelta siguiente y la vuelta atrás saben que este sitio ya cambió y desde qué tema.
			self::store( $state );
			$facts = (array) ( $state['theme'][ $site_id ]['child_facts'] ?? array() );
			if ( 'after_switch' === (string) ( $facts['activate'] ?? '' ) ) {
				$state = self::activate_after_switch( $state, (int) $site_id, $facts );
			}
			if ( ! empty( $state['theme'][ $site_id ]['switched'] ) && '' !== (string) ( $facts['files'] ?? '' ) ) {
				$state = self::note( $state, sprintf( 'El tema hijo «%s» tenía plantillas o archivos que el tema DIGITALÍSIMO no usa (%s): la verificación compara también una vista de cada tipo que pintaban, y el hijo no se borra.', (string) $state['theme'][ $site_id ]['name'], (string) $facts['files'] ) );
			}
		}
		return self::changed( self::next( $state, 'verify' ) );
	}

	/**
	 * Con el tema propio ya activo: el hijo de Hello del que venía el sitio (el de la última pasada de Migrar o el que
	 * anotó el tema al cambiar) tiene PHP propio en su functions.php y ese PHP no corre en Código PHP de Tools. Migrar lo
	 * importa (si falta) y lo activa por la vía firmada, o el informe dice por qué no.
	 */
	private static function own_child_php( array $state, $site_id ) {
		$prev = (array) ( self::last( ! empty( $state['network'] ) )['theme'][ $site_id ] ?? array() );
		$from = ! empty( $prev['child'] ) ? (string) ( $prev['from'] ?? '' ) : '';
		$out  = self::on_site( $site_id, static function () use ( $state, $from ) {
			$slug = $from;
			if ( '' === $slug ) {
				$record = (array) get_option( 'digitalisimo_theme_switch', array() );
				foreach ( array( (array) ( $record['last'] ?? array() ), (array) ( $record['origin'] ?? array() ) ) as $o ) {
					if ( '' !== (string) ( $o['stylesheet'] ?? '' ) && Replacements::HELLO === (string) ( $o['template'] ?? '' ) && $o['stylesheet'] !== $o['template'] ) {
						$slug = (string) $o['stylesheet'];
						break;
					}
				}
			}
			if ( '' === $slug || ! self::child_reader() ) {
				return null;
			}
			$child = wp_get_theme( $slug );
			$code  = $child->exists() ? digitalisimo_theme_child_php_code( $child ) : '';
			if ( '' === $code || "\0" === $code || Replacements::HELLO !== (string) $child->get_template() || digitalisimo_theme_child_php_covered( $child ) ) {
				return null;
			}
			$plan = digitalisimo_theme_child_plan( $child );
			$php  = array_values( array_filter( (array) $plan['blockers'], static function ( $b ) {
				return 'functions.php' === ( $b['file'] ?? '' );
			} ) );
			if ( ! $php ) {
				return null;
			}
			$wrap     = function_exists( 'digitalisimo_theme_child_ctx' ) ? digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ), (string) $child->get_stylesheet(), digitalisimo_theme_child_ctx( $child ) ) : digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ) );
			$hash     = '' !== $wrap ? hash( 'sha256', $wrap ) : hash( 'sha256', $code );
			$imported = has_filter( 'digitalisimo_tools_php_import' ) ? (string) digitalisimo_theme_child_import_php( $child, array( 'risks' => array() ) + $plan ) : '';
			if ( ! in_array( $imported, array( 'created', 'updated', 'unchanged' ), true ) ) {
				$got = '' === $imported ? ( Replacements::is_active( self::TOOLS_FILE ) ? 'missing' : 'no_tools' ) : $imported;
			} elseif ( empty( $state['caps']['code'] ) ) {
				$got = 'nocaps';
			} elseif ( ! has_filter( self::SNIPPET_ACTIVATE ) ) {
				$got = 'old_tools';
			} else {
				$got = self::activate_snippet( $state, self::snippet_key( $slug ), $hash );
			}
			return array( 'slug' => $slug, 'name' => (string) $child->get( 'Name' ), 'got' => $got, 'hash' => $hash, 'blockers' => digitalisimo_theme_child_blockers_text( $php ), 'wrap' => '' !== $wrap, 'imported' => $imported );
		} );
		if ( ! is_array( $out ) ) {
			return $state;
		}
		if ( in_array( $out['got'], array( 'activated', 'active' ), true ) ) {
			$state['theme'][ $site_id ]['snippet'] = (string) $out['hash'];
			return self::note( $state, sprintf( 'El tema DIGITALÍSIMO ya estaba activo, pero el PHP del tema hijo «%s» no corría: Migrar activó «PHP del tema hijo %s» en DIGITALÍSIMO Tools → Código PHP.', $out['name'], $out['name'] ) );
		}
		$facts = array( 'analyzed' => true, 'blockers' => $out['blockers'], 'tools' => 'no_tools' !== $out['got'], 'filter' => true, 'wrap' => $out['wrap'], 'imported' => in_array( $out['got'], array( 'skipped', 'conflict' ), true ) ? $out['got'] : $out['imported'], 'activate' => $out['got'] );
		list( , $why ) = self::theme_safety( array( 'own' => false, 'hello' => true, 'child' => true, 'name' => $out['name'] ), array( 'builder' => true, 'conflict' => false, 'can' => true, 'child' => $facts ) );
		return self::note( $state, 'El tema DIGITALÍSIMO ya está activo, pero el PHP de su tema hijo de antes no corre. ' . str_replace( ' Se queda el tema hijo para no perder lo que ese PHP hace.', '', $why ) );
	}

	/**
	 * Lee el hijo de cada sitio que va a cambiar (child_check()), suma a la comparación las páginas que usan plantillas del
	 * hijo y deja DIGITALÍSIMO Tools (dueño de Código PHP) listo como los plugins: si el PHP de un hijo lo necesita y falta,
	 * está inactivo o es anterior al que Migrar usa, Migrar lo instala, lo actualiza y lo activa (una vez) y sigue en una
	 * petición nueva, con Tools cargado; el hijo se vuelve a leer justo antes del cambio.
	 */
	private static function read_children( array $state, $deadline = null ) {
		$safe = array();
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			if ( ! empty( $row['safe'] ) && ! empty( $row['child'] ) ) {
				$safe[] = (int) $site_id;
			}
		}
		foreach ( $safe as $site_id ) {
			$state = self::child_check( $state, $site_id );
			if ( '' !== (string) ( $state['theme'][ $site_id ]['child_facts']['files'] ?? '' ) ) {
				// Plantillas o archivos del hijo que el tema propio no usa: las páginas que los usan también se comparan. Las
				// huellas se toman por partes dentro del plazo de la etapa y se retoman donde quedaron (revisión NUEVA 2).
				$state = self::template_checks( $state, $site_id, $deadline );
				if ( ! empty( $state['theme'][ $site_id ]['tpl']['pending'] ) ) {
					return self::wait( $state );
				}
			}
		}
		// Hijos leídos y huellas de sus plantillas completas: sólo ahora (si la petición muere antes, se vuelven a leer).
		$state['children_read'] = true;
		$need = array();
		foreach ( $safe as $site_id ) {
			if ( ! empty( $state['theme'][ $site_id ]['templates_blocked'] ) ) {
				continue; // Plantillas sin una vista que comparar: el hijo se queda pase lo que pase con Tools.
			}
			$facts = (array) ( $state['theme'][ $site_id ]['child_facts'] ?? array() );
			if ( '' !== (string) ( $facts['blockers'] ?? '' ) && empty( $facts['covered'] ) && ( empty( $facts['tools'] ) || ! empty( $facts['tools_old'] ) ) ) {
				$need[] = $site_id;
			}
		}
		if ( ! $need || ! empty( $state['tools_ensured'] ) ) {
			return $state;
		}
		$state['tools_ensured'] = true;
		list( $state, $error )  = self::ensure_tools( $state );
		if ( '' === $error ) {
			foreach ( $need as $site_id ) {
				$state['theme'][ $site_id ]['safe']   = true;
				$state['theme'][ $site_id ]['reason'] = '';
			}
			return self::changed( $state );
		}
		$state['tools_error'] = $error;
		foreach ( $need as $site_id ) {
			$state = self::child_check( $state, $site_id );
		}
		return $state;
	}

	/**
	 * El functions.php de un hijo que no admite la condición del tema propio se activa en Tools justo después del cambio, en
	 * la misma petición (ninguna otra corre con el hijo y el fragmento a la vez). Si Tools no lo activa, el sitio vuelve al
	 * hijo en esa misma petición y el motivo queda en la fila.
	 */
	private static function activate_after_switch( array $state, $site_id, array $facts ) {
		$row = (array) $state['theme'][ $site_id ];
		$got = (string) self::on_site( $site_id, static function () use ( $state, $row, $facts ) {
			return self::activate_snippet( $state, self::snippet_key( (string) $row['from'] ), (string) ( $facts['hash'] ?? '' ) );
		} );
		if ( in_array( $got, array( 'activated', 'active' ), true ) ) {
			$state['theme'][ $site_id ]['snippet'] = (string) ( $facts['hash'] ?? '' );
			// Como uno con la condición: verify comprueba, en una petición posterior, que siga activo y sin error antes de
			// permitir la limpieza (si el guardián lo pausó, vuelve al hijo y el hijo no se borra).
			$state['theme'][ $site_id ]['child_facts']['covered']  = true;
			$state['theme'][ $site_id ]['child_facts']['activate'] = $got;
			self::store( $state );
			return $state;
		}
		self::on_site( $site_id, static function () use ( $row ) {
			if ( function_exists( 'digitalisimo_theme_revert' ) && ! is_wp_error( digitalisimo_theme_revert() ) ) {
				return;
			}
			self::switch_theme_to( (string) $row['from'] );
		} );
		$facts['activate']   = $got;
		list( , $reason )    = self::theme_safety( array( 'own' => false, 'hello' => true, 'child' => true, 'name' => (string) $row['name'] ), array( 'builder' => true, 'conflict' => false, 'can' => true, 'child' => array( 'analyzed' => true, 'covered' => false ) + $facts ) );
		$state['theme'][ $site_id ]['switched'] = false;
		$state['theme'][ $site_id ]['safe']     = false;
		$state['theme'][ $site_id ]['reason']   = $reason;
		return $state;
	}

	/**
	 * Deja DIGITALÍSIMO Tools instalado, al día y activo (en una red, en toda la red), como Migrar deja los plugins que
	 * reemplazan. Devuelve [estado, motivo] (motivo '' si quedó listo).
	 *
	 * @return array{0:array,1:string}
	 */
	private static function ensure_tools( array $state ) {
		$slug = 'digitalisimo-tools';
		$file = self::TOOLS_FILE;
		if ( ! Replacements::installed( $file ) ) {
			if ( empty( $state['caps']['install'] ) ) {
				return array( $state, self::no_install() );
			}
			$result = Packages::install_plugin( $slug );
			if ( is_wp_error( $result ) ) {
				return array( $state, 'no se pudo instalar: ' . $result->get_error_message() );
			}
			$state['installed'][] = $slug;
		} else {
			if ( ! empty( $state['caps']['install'] ) ) {
				self::assert_same_site();
			}
			$result = ! empty( $state['caps']['install'] ) ? Packages::update_plugin( $slug ) : new \WP_Error( 'digitalisimo_migration_caps', self::no_install() );
			if ( is_wp_error( $result ) && 'digitalisimo_migration_reactivate' === $result->get_error_code() ) {
				return array( $state, 'se actualizó, pero ' . $result->get_error_message() );
			}
			if ( ! is_wp_error( $result ) && 'updated' === $result['action'] ) {
				$state['upgraded'][ $slug ] = array( 'from' => $result['from'], 'to' => $result['to'] );
			} elseif ( Replacements::is_active( $file ) && ( ! has_filter( self::SNIPPET_ACTIVATE ) || ! has_filter( self::SNIPPET_RESET ) ) ) {
				// Activo, anterior al que Migrar usa y sin una versión más nueva que instalar.
				return array( $state, is_wp_error( $result ) ? 'no se pudo actualizar: ' . $result->get_error_message() : 'la versión instalada (' . $result['from'] . ') es la de la Release publicada y aún no trae lo que Migrar usa' );
			}
		}
		if ( ! Replacements::is_active( $file ) ) {
			$result = self::activate( $state, array( $file ) );
			if ( is_wp_error( $result ) ) {
				return array( $state, 'no se pudo activar: ' . $result->get_error_message() );
			}
			$state['activated'][] = $slug;
		}
		return array( $state, '' );
	}

	/**
	 * Revisiones adversariales A2 y H5: las vistas que pintan las plantillas y archivos del hijo que el tema propio no usa
	 * entran en la comparación de la portada (verify y la comprobación sin su carpeta antes de borrarlo), con su huella de
	 * ahora (con el hijo activo) como referencia, y del tipo exacto de cada plantilla: las páginas que usan sus plantillas
	 * de página; `single-<tipo>.php` → una entrada de ese tipo (o la entrada de ese slug); `page-<slug|id>.php` → esa página;
	 * `category-`, `tag-`, `taxonomy-<tax>[-<término>].php` → ese término (o uno con entradas); `archive-<tipo>.php` → su
	 * archivo; `author`, `date`, `home`, `index`, `archive`, `single`, `page` → uno de cada; 404 y búsqueda; `woocommerce/` →
	 * la ficha de un producto, la tienda, el carrito, el pago o la cuenta según la carpeta; y lo que pinta todo (header,
	 * footer, sidebar, comments, template-parts, theme.json, plantillas de bloques u otro PHP) → una vista de cada tipo.
	 * Una plantilla para la que no hay una URL fiable (sin contenido de su tipo, o los correos de WooCommerce) deja el
	 * hijo: el tema no cambia y el motivo nombra el archivo. Hasta 12 URL y 60 s en total (H10); lo que no dé tiempo a
	 * comprobar también deja el hijo. Un hijo con esos archivos nunca se borra al final (retired_children()).
	 */
	private static function template_checks( array $state, $site_id, $deadline = null ) {
		$tpl = (array) ( $state['theme'][ $site_id ]['tpl'] ?? array() );
		if ( ! isset( $tpl['urls'] ) ) {
			$tpl = self::template_views( $site_id ) + array( 'at' => 0 );
		}
		$missed = (array) $tpl['missed'];
		$urls   = array_slice( (array) $tpl['urls'], 0, 12 );
		if ( count( (array) $tpl['urls'] ) > 12 ) {
			$missed[] = sprintf( '%d vistas más de las que se comparan', count( (array) $tpl['urls'] ) - 12 );
		}
		if ( ! $missed ) {
			// Revisión adversarial NUEVA 2: una huella por vuelta dentro del plazo de la etapa (al menos una por petición); lo
			// que falte sigue en la petición siguiente, donde quedó. Nunca se cambia el tema sin todas.
			while ( (int) $tpl['at'] < count( $urls ) ) {
				$url = (string) $urls[ (int) $tpl['at'] ];
				$dup = false;
				foreach ( (array) $state['checks'] as $check ) {
					$dup = $dup || ( (int) $check[0] === (int) $site_id && (string) $check[1] === $url );
				}
				if ( ! $dup ) {
					self::put_snap( $state, $site_id . '|' . $url, Front::snapshot( $url ) );
					$state['checks'][] = array( (int) $site_id, $url );
				}
				$tpl['at'] = (int) $tpl['at'] + 1;
				if ( null !== $deadline && microtime( true ) >= $deadline && (int) $tpl['at'] < count( $urls ) ) {
					$tpl['pending']                       = true;
					$state['theme'][ $site_id ]['tpl'] = $tpl;
					return $state;
				}
			}
		}
		unset( $tpl['pending'] );
		$state['theme'][ $site_id ]['tpl'] = $tpl;
		if ( $missed ) {
			$state['theme'][ $site_id ]['templates_blocked'] = true;
			$state['theme'][ $site_id ]['safe']              = false;
			$state['theme'][ $site_id ]['reason']            = sprintf( 'El tema hijo «%s» tiene plantillas que el tema DIGITALÍSIMO no usa y cuya vista Migrar no puede comprobar: %s. Se queda el tema hijo para no cambiar cómo se pintan sin poder verlo. Si esas plantillas ya no hacen falta, quítalas del tema hijo y Migrar hará el cambio.', (string) $state['theme'][ $site_id ]['name'], implode( ', ', array_slice( $missed, 0, 6 ) ) );
		}
		return $state;
	}

	/** Las URL que comparar por las plantillas del hijo del sitio y las plantillas sin una vista fiable (template_checks()). */
	private static function template_views( $site_id ) {
		$found = (array) self::on_site( $site_id, static function () {
			if ( ! function_exists( 'digitalisimo_theme_child_page_templates' ) ) {
				return array( 'urls' => array(), 'unresolved' => array() );
			}
			$child  = wp_get_theme();
			$out    = array();
			$missed = array();
			foreach ( digitalisimo_theme_child_page_templates( $child ) as $row ) {
				if ( (int) $row['pages'] < 1 ) {
					continue;
				}
				$ids = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 2, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => '_wp_page_template', 'meta_value' => (string) $row['template'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				foreach ( (array) $ids as $id ) {
					$out[] = (string) get_permalink( (int) $id );
				}
			}
			$latest = static function ( $type ) {
				if ( function_exists( 'post_type_exists' ) && ! post_type_exists( $type ) ) {
					return '';
				}
				$ids = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) );
				return $ids ? (string) get_permalink( (int) reset( $ids ) ) : '';
			};
			$by_path = static function ( $slug, $type ) {
				if ( ctype_digit( (string) $slug ) ) {
					return 'publish' === get_post_status( (int) $slug ) ? (string) get_permalink( (int) $slug ) : '';
				}
				$post = function_exists( 'get_page_by_path' ) ? get_page_by_path( (string) $slug, 'OBJECT', $type ) : null;
				return $post && 'publish' === get_post_status( $post ) ? (string) get_permalink( $post ) : '';
			};
			$term = static function ( $taxonomy, $slug = '' ) {
				if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( $taxonomy ) ) {
					return '';
				}
				if ( '' !== $slug ) {
					$t = ctype_digit( (string) $slug ) ? get_term( (int) $slug, $taxonomy ) : get_term_by( 'slug', (string) $slug, $taxonomy );
				} else {
					$list = get_terms( array( 'taxonomy' => $taxonomy, 'number' => 1, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
					$t    = is_array( $list ) && $list ? reset( $list ) : null;
				}
				$link = is_object( $t ) && ! is_wp_error( $t ) ? get_term_link( $t ) : '';
				return is_string( $link ) ? $link : '';
			};
			$wc = static function ( $page ) {
				return function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_page_id' ) && wc_get_page_id( $page ) > 0 ? (string) wc_get_page_permalink( $page ) : '';
			};
			$everything = static function () use ( $latest, $term, $wc ) {
				return array( $latest( 'post' ), $latest( 'page' ), $term( 'category' ), home_url( '/digitalisimo-migrar-comprobacion-404/' ), home_url( '/?s=a' ), $latest( 'product' ), $wc( 'shop' ) );
			};
			foreach ( self::child_other_files( $child ) as $blocker ) {
				$file = (string) ( $blocker['file'] ?? '' );
				if ( false !== strpos( (string) ( $blocker['what'] ?? '' ), 'plantilla de página' ) ) {
					continue;
				}
				$want = array();
				$base = basename( $file, '.php' );
				if ( '' === $file ) {
					// Revisión adversarial NUEVA 1: el tope de archivos revisados o una carpeta ilegible dejan plantillas sin
					// revisar, que pueden ser de cualquier tipo (también correos): cuentan como plantilla sin vista fiable.
					$missed[] = (string) ( $blocker['what'] ?? 'archivos sin revisar' );
					continue;
				}
				if ( 0 === strpos( $file, 'woocommerce/' ) ) {
					if ( preg_match( '#^woocommerce/emails/#', $file ) ) {
						$want = array( '' ); // Los correos no tienen una URL que comparar.
					} elseif ( preg_match( '#^woocommerce/(single-product|content-single-product)#', $file ) ) {
						$want = array( $latest( 'product' ) );
					} elseif ( preg_match( '#^woocommerce/(archive-product|taxonomy-product|content-product|loop/)#', $file ) ) {
						$want = array( $wc( 'shop' ), $term( 'product_cat' ) );
					} elseif ( preg_match( '#^woocommerce/cart/#', $file ) ) {
						$want = array( $wc( 'cart' ) );
					} elseif ( preg_match( '#^woocommerce/checkout/#', $file ) ) {
						$want = array( $wc( 'checkout' ) );
					} elseif ( preg_match( '#^woocommerce/myaccount/#', $file ) ) {
						$want = array( $wc( 'myaccount' ) );
					} else {
						$want = array( $latest( 'product' ), $wc( 'shop' ) );
					}
				} elseif ( '' !== $file && false === strpos( $file, '/' ) && preg_match( '#^single-(.+)$#', $base, $m ) ) {
					$want = array( function_exists( 'post_type_exists' ) && post_type_exists( $m[1] ) ? $latest( $m[1] ) : $by_path( $m[1], 'post' ) );
				} elseif ( in_array( $base, array( 'single', 'singular' ), true ) && false === strpos( $file, '/' ) ) {
					$want = array( $latest( 'post' ) );
				} elseif ( preg_match( '#^page-(.+)$#', $base, $m ) && false === strpos( $file, '/' ) ) {
					$want = array( $by_path( $m[1], 'page' ) );
				} elseif ( 'page' === $base && false === strpos( $file, '/' ) ) {
					$want = array( $latest( 'page' ) );
				} elseif ( preg_match( '#^(category|tag)(?:-(.+))?$#', $base, $m ) && false === strpos( $file, '/' ) ) {
					$want = array( $term( 'category' === $m[1] ? 'category' : 'post_tag', (string) ( $m[2] ?? '' ) ) );
				} elseif ( preg_match( '#^taxonomy(?:-(.+))?$#', $base, $m ) && false === strpos( $file, '/' ) ) {
					$tax = (string) ( $m[1] ?? '' );
					$hit = '';
					foreach ( '' === $tax ? array() : array( array( $tax, '' ) ) as $try ) {
						$hit = $term( $try[0], $try[1] );
					}
					if ( '' === $hit && false !== strpos( $tax, '-' ) ) {
						// taxonomy-<tax>-<término>.php: la taxonomía puede llevar guiones; se prueba cada corte.
						$parts = explode( '-', $tax );
						for ( $i = count( $parts ) - 1; $i > 0 && '' === $hit; $i-- ) {
							$hit = $term( implode( '-', array_slice( $parts, 0, $i ) ), implode( '-', array_slice( $parts, $i ) ) );
						}
					}
					$want = array( $hit );
				} elseif ( preg_match( '#^archive-(.+)$#', $base, $m ) && false === strpos( $file, '/' ) ) {
					$link = function_exists( 'get_post_type_archive_link' ) ? get_post_type_archive_link( $m[1] ) : '';
					$want = array( is_string( $link ) ? $link : '' );
				} elseif ( preg_match( '#^author(?:-(.+))?$#', $base, $m ) && false === strpos( $file, '/' ) ) {
					// author-<nombre|id>.php compara a ese autor; author.php, al de la entrada más reciente.
					$user = '';
					if ( '' !== (string) ( $m[1] ?? '' ) ) {
						$who  = function_exists( 'get_user_by' ) ? get_user_by( ctype_digit( (string) $m[1] ) ? 'id' : 'slug', (string) $m[1] ) : false;
						$user = $who && function_exists( 'get_author_posts_url' ) ? (string) get_author_posts_url( (int) $who->ID ) : '';
					} else {
						$ids  = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true ) );
						$user = $ids && function_exists( 'get_author_posts_url' ) ? (string) get_author_posts_url( (int) reset( $ids )->post_author ) : '';
					}
					$want = array( $user );
				} elseif ( 'date' === $base && false === strpos( $file, '/' ) ) {
					$want = array( function_exists( 'get_year_link' ) ? (string) get_year_link( (int) gmdate( 'Y' ) ) : '' );
				} elseif ( 'home' === $base && false === strpos( $file, '/' ) ) {
					// home.php pinta la página de entradas: con portada estática, la página elegida para las entradas.
					$posts = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_for_posts' ) : 0;
					$want  = array( 'page' === get_option( 'show_on_front' ) ? ( $posts ? (string) get_permalink( $posts ) : '' ) : home_url( '/' ) );
				} elseif ( in_array( $base, array( 'archive', 'index' ), true ) && false === strpos( $file, '/' ) ) {
					$want = array( $term( 'category' ), $latest( 'post' ) );
				} elseif ( '404' === $base && false === strpos( $file, '/' ) ) {
					$want = array( home_url( '/digitalisimo-migrar-comprobacion-404/' ) );
				} elseif ( preg_match( '#^search#', $base ) && false === strpos( $file, '/' ) ) {
					$want = array( home_url( '/?s=a' ) );
				} else {
					// header, footer, sidebar, comments, template-parts, theme.json, plantillas de bloques u otro PHP: pinta todo.
					$want = array_filter( $everything() );
					$want = $want ? $want : array( '' );
				}
				if ( in_array( '', $want, true ) ) {
					$missed[] = '' !== $file ? $file : (string) ( $blocker['what'] ?? '' );
				}
				$out = array_merge( $out, array_filter( $want ) );
			}
			return array( 'urls' => array_values( array_unique( array_filter( $out ) ) ), 'unresolved' => array_values( array_unique( $missed ) ) );
		} );
		return array( 'urls' => (array) ( $found['urls'] ?? array() ), 'missed' => (array) ( $found['unresolved'] ?? array() ) );
	}

	/** ¿Es el tema propio el activo en ese sitio? (leído de la base: lo pudo escribir otra petición). */
	private static function own_active( $site_id ) {
		return (bool) self::on_site( (int) $site_id, static function () {
			return Replacements::is_own_theme( (string) self::stored( 'stylesheet' ) );
		} );
	}

	/** ¿Cambió ya este sitio al tema propio? (`switched`, o `switching` con el tema propio ya activo). */
	private static function was_switched( array $row, $site_id ) {
		return ! empty( $row['switched'] ) || ( ! empty( $row['switching'] ) && self::own_active( (int) $site_id ) );
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
		// switch_theme() lee y guarda `theme_mods_*` y `sidebars_widgets`: con la caché de otro sitio guardaría los suyos.
		self::assert_same_site();
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
		// Volver al tema anterior también lee y guarda opciones: con la caché de otro sitio no se toca (el informe lo dice).
		if ( '' !== self::alien() ) {
			return self::note( $state, 'El tema no se devolvió al anterior porque la caché de objetos estaba dando los datos de otro sitio: corrígela y vuelve a pulsar Migrar, que lo comprueba.' );
		}
		foreach ( (array) $state['theme'] as $site_id => $row ) {
			if ( ! self::was_switched( $row, $site_id ) ) {
				continue;
			}
			$snippet = (string) ( $row['snippet'] ?? '' );
			$off     = null;
			if ( '' !== $snippet ) {
				// Revisión adversarial M2: primero se desactiva el fragmento del hijo que activó Migrar y sólo después se vuelve
				// al hijo. Si no se puede desactivar y no lleva la condición del tema propio, volver al hijo haría correr su
				// functions.php dos veces (ganchos duplicados o «Cannot redeclare»): el sitio se queda con el tema propio y lo dice.
				$off = self::on_site( (int) $site_id, static function () use ( $row ) {
					if ( function_exists( 'wp_cache_delete' ) ) {
						wp_cache_delete( 'alloptions', 'options' );
					}
					return has_filter( self::SNIPPET_DEACTIVATE ) ? apply_filters( self::SNIPPET_DEACTIVATE, false, self::snippet_key( (string) $row['from'] ), array( 'hash' => (string) $row['snippet'] ) ) : false;
				} );
				$state['theme'][ $site_id ]['snippet']     = '';
				$state['theme'][ $site_id ]['snippet_off'] = true === $off ? 'off' : 'kept';
				if ( true !== $off && empty( $row['child_facts']['wrap'] ) ) {
					$state['theme'][ $site_id ]['snippet']        = $snippet;
					$state['theme'][ $site_id ]['revert_blocked'] = true;
					$state = self::note( $state, sprintf( 'No se volvió a %1$s: «PHP del tema hijo %1$s» no se pudo desactivar en DIGITALÍSIMO Tools y, sin la condición del tema propio, correría junto con el functions.php del hijo. El sitio sigue con el tema DIGITALÍSIMO; nada se borró.', (string) $row['name'] ) );
					continue;
				}
			}
			// En una red, el candado se mira en cada sitio (la caché puede dar otro sitio sólo en éste): ese sitio se queda
			// como está y el informe lo dice; la tarea no se atasca (esto corre también desde fail()).
			$why = (string) self::on_site( (int) $site_id, static function () use ( $row ) {
				try {
					self::assert_same_site();
					if ( function_exists( 'digitalisimo_theme_revert' ) ) {
						$result = digitalisimo_theme_revert();
						if ( ! is_wp_error( $result ) ) {
							return '';
						}
					}
					self::switch_theme_to( (string) $row['from'] );
				} catch ( Same_Site_Exception $e ) {
					return 'la caché de objetos estaba dando los datos de otro sitio: corrígela y vuelve a pulsar Migrar, que lo comprueba';
				} catch ( \Throwable $e ) {
					return $e->getMessage();
				}
				return '';
			} );
			// Lo que manda es el tema activo de verdad (revisión NUEVA 4): un gancho que lanza después de escribir el hijo no
			// deja la fila como cambiada.
			if ( self::own_active( (int) $site_id ) ) {
				// No se pudo volver al hijo: el sitio sigue con el tema propio, así que su PHP tiene que seguir corriendo
				// (revisión adversarial H3): el fragmento que se acababa de apagar se vuelve a activar.
				if ( true === $off ) {
					$again = self::on_site( (int) $site_id, static function () use ( $state, $row, $snippet ) {
						return self::activate_snippet( $state, self::snippet_key( (string) $row['from'] ), $snippet );
					} );
					if ( in_array( $again, array( 'activated', 'active' ), true ) ) {
						$state['theme'][ $site_id ]['snippet'] = $snippet;
						unset( $state['theme'][ $site_id ]['snippet_off'] );
					}
				}
				unset( $state['theme'][ $site_id ]['switching'] );
				$state['theme'][ $site_id ]['switched'] = true;
				$state = self::note( $state, sprintf( 'El tema de %s no se devolvió al anterior porque %s. Sigue el tema DIGITALÍSIMO%s.', (string) $row['name'], '' !== $why ? rtrim( $why, '. ' ) : 'WordPress no lo cambió', ! empty( $state['theme'][ $site_id ]['snippet'] ) ? ', con el PHP del hijo activo en DIGITALÍSIMO Tools' : '' ) );
				continue;
			}
			unset( $state['theme'][ $site_id ]['switching'] );
			$state['theme'][ $site_id ]['switched'] = false;
			$state['theme'][ $site_id ]['reverted'] = true;
			$state['theme'][ $site_id ]['reason']   = sprintf( $reason, (string) $row['name'] );
			if ( '' !== $why ) {
				$state = self::note( $state, sprintf( 'Se volvió a %s, pero algo que corre al cambiar de tema dio un error: %s.', (string) $row['name'], rtrim( $why, '. ' ) ) );
			}
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
		// Ante cualquier duda el hijo se queda: con el tema propio activo ni él ni Hello se cargan, así que dejarlo no
		// estorba y borrarlo es sólo limpieza. El informe lo dice en una línea, sin pedir nada.
		$keep = static function ( $why ) {
			return 'Se queda instalado (no estorba: con el tema DIGITALÍSIMO no se carga) porque ' . $why . '.';
		};
		$all = is_multisite() ? Retire::sites() : array( (int) get_current_blog_id() );
		foreach ( $from as $slug => $sites ) {
			$theme = wp_get_theme( $slug );
			if ( ! $theme->exists() || Replacements::HELLO !== (string) $theme->get_template() || isset( $state['deleted'][ 'child:' . $slug ] ) || isset( $state['kept'][ 'child:' . $slug ] ) ) {
				continue;
			}
			$name   = (string) $theme->get( 'Name' );
			$reason = '';
			// Cada sitio de la red: no lo usa como tema y, si el tema propio guardó que viene de él (en esta pasada o en un
			// cambio anterior), su copia en uploads está completa (copy_assets() la hace o la pone al día).
			$users = array();
			foreach ( $all as $site_id ) {
				$seen = self::on_site( (int) $site_id, static function () use ( $slug ) {
					if ( $slug === (string) get_option( 'stylesheet' ) || $slug === (string) get_option( 'template' ) ) {
						return array( 'uses', array() );
					}
					$saved = get_option( 'digitalisimo_theme_child' );
					if ( ! is_array( $saved ) || $slug !== (string) ( $saved['child'] ?? '' ) ) {
						return array( '', array() );
					}
					if ( ! self::child_reader() || ! function_exists( 'digitalisimo_theme_child_assets_copied' ) || ! function_exists( 'digitalisimo_theme_child_copy_assets' ) ) {
						return array( 'old', array() );
					}
					digitalisimo_theme_child_copy_assets();
					$issues = array();
					return array( true === digitalisimo_theme_child_assets_copied( $issues ) ? 'copied' : 'missing', (array) $issues );
				} );
				if ( 'uses' === $seen[0] ) {
					$reason = $keep( 'algún sitio lo tiene activo' );
				} elseif ( 'old' === $seen[0] ) {
					$reason = $keep( 'el tema DIGITALÍSIMO instalado no sabe copiar sus archivos a uploads' );
				} elseif ( 'missing' === $seen[0] ) {
					$why    = array_slice( array_filter( array_map( 'strval', (array) $seen[1] ) ), 0, 2 );
					$reason = $keep( 'el tema DIGITALÍSIMO todavía carga archivos desde su carpeta' . ( is_multisite() ? ' en ' . self::site_name( (int) $site_id ) : '' ) . ( $why ? ' (' . implode( '; ', $why ) . ')' : '' ) );
				} elseif ( 'copied' === $seen[0] ) {
					$users[] = (int) $site_id;
				}
				if ( '' !== $reason ) {
					break;
				}
			}
			if ( '' === $reason ) {
				// Revisión adversarial A2: plantillas o archivos propios que el tema propio no usa (single.php, header.php,
				// woocommerce/, theme.json…): sus vistas se compararon, pero ante la duda el hijo no se borra.
				foreach ( $sites as $site_id ) {
					$files = (string) ( $state['theme'][ $site_id ]['child_facts']['files'] ?? '' );
					if ( '' !== $files ) {
						$reason = $keep( 'tiene plantillas o archivos propios que el tema DIGITALÍSIMO no usa (' . $files . '); sus vistas se compararon y responden igual' );
						break;
					}
				}
			}
			if ( '' === $reason ) {
				// Revisión adversarial H7: justo antes de borrarlo, el PHP del hijo tiene que seguir activo en Tools en cada sitio
				// donde corría por ahí (si el guardián lo pausó después de verify, el hijo se queda).
				foreach ( $sites as $site_id ) {
					if ( empty( $state['theme'][ $site_id ]['child_facts']['covered'] ) ) {
						continue;
					}
					$alive = self::on_site( (int) $site_id, static function () use ( $theme ) {
						if ( function_exists( 'wp_cache_delete' ) ) {
							wp_cache_delete( 'alloptions', 'options' );
						}
						return self::child_reader() && digitalisimo_theme_child_php_covered( $theme );
					} );
					if ( ! $alive ) {
						$reason = $keep( 'su PHP ya no está activo en DIGITALÍSIMO Tools (el guardián lo pausó o se desactivó)' );
						break;
					}
				}
			}
			if ( '' === $reason && is_file( $theme->get_stylesheet_directory() . '/functions.php' ) ) {
				// Su functions.php sigue corriendo en Código PHP. Si lee o incluye archivos, usa __FILE__/__DIR__ (que después
				// apuntarían a otra carpeta) o nombra su ruta, la necesita.
				$code = (string) file_get_contents( $theme->get_stylesheet_directory() . '/functions.php' );
				if ( preg_match( '/\b(?:include|require)(?:_once)?\b|\bfile_get_contents\s*\(|\bfopen\s*\(|\bfile\s*\(|\breadfile\s*\(|\bglob\s*\(|\bscandir\s*\(|__FILE__|__DIR__/i', $code ) || false !== stripos( $code, 'themes/' . $slug ) ) {
					$reason = $keep( 'su PHP, que sigue corriendo en Código PHP, lee archivos o rutas de su carpeta' );
				}
			}
			if ( '' === $reason ) {
				foreach ( $all as $site_id ) {
					$where = (string) self::on_site( (int) $site_id, static function () use ( $slug ) {
						return self::child_references( $slug );
					} );
					if ( '' !== $where ) {
						$reason = $keep( sprintf( 'todavía se enlazan archivos de su carpeta en %s%s', $where, is_multisite() ? ' de ' . self::site_name( (int) $site_id ) : '' ) );
						break;
					}
				}
			}
			if ( '' === $reason && empty( $state['caps']['delete_themes'] ) ) {
				$reason = $keep( 'quien pulsó Migrar no puede borrar temas' );
			}
			if ( '' !== $reason ) {
				$state['kept'][ 'child:' . $slug ] = $reason;
				continue;
			}
			$out[ $slug ] = array( 'name' => $name, 'sites' => array_values( array_unique( array_merge( $sites, $users ) ) ) );
		}
		return $out;
	}

	/**
	 * Dónde enlaza el sitio en curso algún archivo de la carpeta del hijo (`themes/<carpeta>/`, también con las barras
	 * escapadas de JSON —los datos de Elementor— o codificada en una URL): entradas y páginas (con el CSS adicional del
	 * Personalizador, que es una entrada `custom_css`), metadatos, opciones (widgets, menús, ajustes, también los del
	 * tema propio; no los ajustes del propio hijo, los transitorios, ni las de Migrar, el registro del hijo del tema propio
	 * y Código PHP) y las hojas que Elementor genera en uploads/elementor/css. '' si en ninguna.
	 */
	private static function child_references( $slug ) {
		global $wpdb;
		foreach ( array( 'themes/' . $slug . '/', 'themes\\/' . $slug . '\\/', rawurlencode( 'themes/' . $slug . '/' ) ) as $needle ) {
			$like = '%' . $wpdb->esc_like( $needle ) . '%';
			$post = $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . $wpdb->posts . " WHERE post_content LIKE %s AND post_status NOT IN ('trash','auto-draft','inherit') LIMIT 1", $like ) ); // phpcs:ignore WordPress.DB
			if ( $post ) {
				return 'custom_css' === get_post_type( (int) $post ) ? 'el CSS adicional' : sprintf( 'el contenido de «%s» (ID %d)', get_the_title( (int) $post ), (int) $post );
			}
			$meta = $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_value LIKE %s LIMIT 1', $like ) ); // phpcs:ignore WordPress.DB
			if ( $meta ) {
				return sprintf( 'los datos de «%s» (ID %d)', get_the_title( (int) $meta ), (int) $meta );
			}
			$option = $wpdb->get_var( $wpdb->prepare( 'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_value LIKE %s AND option_name <> %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s LIMIT 1', $like, 'theme_mods_' . $slug, '%transient%', 'digitalisimo_migration%', 'digitalisimo_theme_child%', 'digitalisimo_theme_switch%', 'digitalisimo_tools_php%' ) ); // phpcs:ignore WordPress.DB
			if ( $option ) {
				return sprintf( 'la opción %s', (string) $option );
			}
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
		// La opción `cron` se lee y se vuelve a guardar: con la caché de otro sitio se guardarían sus eventos aquí.
		if ( '' !== self::alien() ) {
			return;
		}
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
		if ( ! empty( $state['upgraded'] ) ) {
			$up = array();
			foreach ( (array) $state['upgraded'] as $slug => $row ) {
				$up[] = sprintf( '%s %s → %s', Replacements::own_label( $slug ), (string) ( $row['from'] ?? '' ), (string) ( $row['to'] ?? '' ) );
			}
			$items[] = array( 'Actualizado', implode( ', ', $up ) );
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
			if ( ! empty( $state['snap_from'] ) && 'cli' !== $where ) {
				// Revisión adversarial NUEVA 3: se comparó con el sitio de antes de una pasada que lo dejó a medias. Si lo distinto
				// son cambios del dueño, puede empezar de nuevo desde el sitio de ahora, aquí mismo.
				$attention[] = esc_html( sprintf( 'No responden igual que antes de la primera pasada de Migrar (%s), que dejó el sitio a medias: %s. Se volvió a como estaba%s y no se borró nada. Si lo distinto son cambios que hiciste tú después (textos, páginas), migra tomando el sitio de ahora como referencia; si no, ábrelas y revisa qué cambió.', gmdate( 'd/m/Y', (int) $state['snap_from'] ), implode( '; ', $pages ), $reverted ? ' (' . implode( ', ', $reverted ) . ')' : '' ) ) . ' <button type="button" class="button button-primary digi-migrate__fresh">' . esc_html( 'Migrar tomando el sitio de ahora como referencia' ) . '</button>';
			} else {
				$attention[] = esc_html( 'No responden igual después de migrar: ' . implode( '; ', $pages ) . '. Ábrelas y revisa qué cambió' . ( $reverted ? '; se volvió a ' . implode( ', ', $reverted ) : '' ) . '. No se borró nada. Cuando lo corrijas, vuelve a pulsar Migrar.' );
			}
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
			// Se instaló y ningún sitio lo activó (el motivo de cada uno va arriba): queda instalado, sin activar.
			$themes[] = 'El tema DIGITALÍSIMO quedó instalado, sin activar.';
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
