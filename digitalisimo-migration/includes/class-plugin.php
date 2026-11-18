<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Arranque de «Migrar» y de la compatibilidad heredada.
 *
 * La tarea completa (respaldo, instalación, importación, tema y limpieza) no necesita nada más; la conversión y los
 * adaptadores necesitan DIGITALÍSIMO Elements (sus widgets son el destino), que Migrar instala y activa.
 * Mientras Element Pack esté activo en un sitio, sus propios widgets se pintan con su código; Migrar
 * lo desactiva (junto con Elementor, Elementor Pro y PRO Elements) antes de convertir.
 */
final class Plugin {
	public static function boot() {
		Updater::init();
		// «Migrar» lo hace todo y funciona con Migración sola (regla del propietario del 2026-11-18): la tarea completa,
		// su pantalla y WP-CLI se cargan siempre; lo que necesita DIGITALÍSIMO Elements llega cuando Migrar lo instala.
		require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-full.php';
		require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-screen.php';
		Full::init();
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			Screen::init();
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-cli.php';
				\WP_CLI::add_command( 'digitalisimo-migration', Cli::class );
			}
			return;
		}
		// Las opciones que Migración lee en cada petición (sin carga automática o inexistentes en un sitio que no
		// migró): juntas en una consulta en lugar de una cada una (auditoría del escritorio, 2026-11-18).
		if ( function_exists( 'wp_prime_option_caches' ) ) {
			wp_prime_option_caches( self::option_names( is_admin() ) );
		}
		$legacy = DIGITALISIMO_MIGRATION_DIR . 'legacy/';
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		require_once $legacy . 'class-translator.php';
		require_once $legacy . 'class-context.php';
		require_once $legacy . 'class-report.php';
		require_once $legacy . 'class-extensions.php';
		\Digitalisimo\Elements\Legacy\Extensions::init();
		require_once $legacy . 'class-shapes.php';
		\Digitalisimo\Elements\Legacy\Shapes::init();
		require_once $legacy . 'class-masks.php';
		\Digitalisimo\Elements\Legacy\Masks::init();
		require_once $legacy . 'conditions/class-legacy-visibility.php';
		\Digitalisimo\Elements\Legacy_Visibility::init();
		self::bridge_visitor_filters();
		add_filter( 'admin_memory_limit', array( __CLASS__, 'editor_memory' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_adapters' ), 30 );
		// Migrar en un clic: su paso AJAX, su continuación con WP-Cron y el origen de la pantalla de red.
		require_once $legacy . 'class-runner.php';
		\Digitalisimo\Elements\Legacy\Runner::init();
		// Plantillas copiadas del Theme Builder: publicadas sólo sin Element Pack cargado (V.5, frente G).
		self::call( 'Platform\\Publisher', 'init' );
		// Alias de los conjuntos de iconos copiados mientras quedan documentos sin convertir (V.11, frente H).
		self::call( 'Platform\\Icons', 'init' );
		// Marcadores de K con Element Pack o Prime Slider dentro: el editor los convierte con la conversión pura (V.12).
		if ( is_readable( $legacy . 'class-markers.php' ) ) {
			require_once $legacy . 'class-markers.php';
			if ( method_exists( '\\Digitalisimo\\Elements\\Legacy\\Markers', 'convert' ) ) {
				add_filter( 'digitalisimo_elements_unconverted_convert', array( '\\Digitalisimo\\Elements\\Legacy\\Markers', 'convert' ), 10, 3 );
			}
		}
		if ( is_admin() ) {
			require_once $legacy . 'class-migration.php';
			\Digitalisimo\Elements\Legacy\Migration::init();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once $legacy . 'class-cli.php';
			\WP_CLI::add_command( 'digitalisimo-migration', \Digitalisimo\Elements\Legacy\Cli::class );
		}
	}

	/**
	 * Opciones leídas en cada petición: el estado de las copias del Theme Builder (`Platform\Publisher::OPTION`) y los
	 * alias de iconos (`Platform\Icons::ALIAS`); en el escritorio, además, la recuperación de condiciones
	 * (`Migration::REPAIRED` y `Migration::REPAIR_TOTALS`). tests/migration-admin-load.php comprueba que coinciden.
	 *
	 * @return string[]
	 */
	public static function option_names( $admin ) {
		$names = array( 'digitalisimo_migration_tb_state', 'digitalisimo_migration_icon_alias' );
		return $admin ? array_merge( $names, array( 'digitalisimo_ep_conditions_repaired', 'digitalisimo_ep_conditions_repair_totals' ) ) : $names;
	}

	/**
	 * Autocarga de los subespacios de F8 (V.14 C1): `Legacy\Effects\Foo_Bar` → `legacy/effects/class-foo-bar.php`, e
	 * igual con `Prime_Slider` (`legacy/prime-slider/`) y `Platform` (`legacy/platform/`). Las clases de primer nivel
	 * siguen con require_once. El nombre se valida antes de construir la ruta.
	 */
	public static function autoload( $class ) {
		$prefix = 'Digitalisimo\\Elements\\Legacy\\';
		if ( 0 !== strpos( (string) $class, $prefix ) ) {
			return;
		}
		$parts = explode( '\\', substr( (string) $class, strlen( $prefix ) ) );
		$dirs  = array( 'Effects' => 'effects', 'Prime_Slider' => 'prime-slider', 'Platform' => 'platform' );
		if ( 2 !== count( $parts ) || ! isset( $dirs[ $parts[0] ] ) || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $parts[1] ) ) {
			return;
		}
		$file = DIGITALISIMO_MIGRATION_DIR . 'legacy/' . $dirs[ $parts[0] ] . '/class-' . str_replace( '_', '-', strtolower( $parts[1] ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/** Llama a un método estático de una clase de otro frente sólo si existe (sin ella, el comportamiento de 1.0.20). */
	private static function call( $class, $method ) {
		$class = '\\Digitalisimo\\Elements\\Legacy\\' . $class;
		if ( class_exists( $class ) && method_exists( $class, $method ) ) {
			call_user_func( array( $class, $method ) );
		}
	}

	/**
	 * Con los adaptadores el editor de Elementor carga los controles de unos 400 widgets (unos 165 MB, más con
	 * WooCommerce): en sus peticiones AJAX el límite de memoria de administración sube a 512 MB mientras dure la
	 * migración. Elementor ya pide ese límite con wp_raise_memory_limit( 'admin' ).
	 */
	public static function editor_memory( $limit ) {
		$ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && 'elementor_ajax' === ( $_REQUEST['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide el límite de memoria.
		return $ajax && wp_convert_hr_to_bytes( (string) $limit ) < 536870912 ? '512M' : $limit;
	}

	/** Los adaptadores se registran después de los widgets propios, de los que heredan. */
	public static function register_adapters( $manager ) {
		if ( \Digitalisimo\Elements\Legacy\Translator::element_pack_active() ) {
			return;
		}
		require_once DIGITALISIMO_MIGRATION_DIR . 'legacy/class-adapters.php';
		\Digitalisimo\Elements\Legacy\Adapters::register( $manager );
	}

	/**
	 * Un sitio que personalizó la detección del país con los filtros de Element Pack conserva ese
	 * comportamiento: se encadenan a los filtros propios de DIGITALÍSIMO Elements.
	 */
	private static function bridge_visitor_filters() {
		$filters = array( 'country_pre' => 2, 'country_use_timezone' => 1, 'client_ip' => 1, 'country_endpoint' => 2, 'country_parse' => 3, 'country_cache_ttl' => 3, 'tz_cookie' => 1 );
		foreach ( $filters as $name => $args ) {
			add_filter( 'digitalisimo_elements_visibility_' . $name, static function ( ...$values ) use ( $name ) {
				return apply_filters( 'element_pack_visibility_' . $name, ...$values );
			}, 5, $args );
		}
	}
}
