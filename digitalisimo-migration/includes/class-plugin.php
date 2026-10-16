<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Arranque de la compatibilidad heredada y de «Migrar».
 *
 * Necesita DIGITALÍSIMO Elements: sus widgets son el destino de cada adaptador y de la conversión.
 * Mientras Element Pack esté activo en un sitio, sus propios widgets se pintan con su código; Migrar
 * lo desactiva (junto con Elementor, Elementor Pro y PRO Elements) antes de convertir.
 */
final class Plugin {
	public static function boot() {
		Updater::init();
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_elements' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'missing_elements' ) );
			return;
		}
		$legacy = DIGITALISIMO_MIGRATION_DIR . 'legacy/';
		require_once $legacy . 'class-translator.php';
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

	public static function missing_elements() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html( 'DIGITALÍSIMO Migración necesita DIGITALÍSIMO Elements activo: sus widgets son el destino de la migración.' ) . '</p></div>';
	}
}
