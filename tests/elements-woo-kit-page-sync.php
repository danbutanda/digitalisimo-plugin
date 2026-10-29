<?php
/**
 * Páginas de WooCommerce copiadas al kit sin permiso (modules/woocommerce/module.php, derivado de PRO; hallazgo de
 * F4-Woo). WooCommerce cambia `woocommerce_cart_page_id` y las otras tres páginas también sin un usuario que pueda
 * editar el kit: al instalarse en un sitio nuevo de una red (primera petición, quizá de un visitante) y en WP-cron.
 * El guardado del kit lanzaba «Access denied» y la petición terminaba en un 500. Se carga el método real del módulo
 * con dobles del núcleo y se comprueba:
 * - con permiso o en WP-CLI, el camino de siempre (`update_kit_settings_based_on_option()`);
 * - sin permiso, el mismo valor llega a la meta del kit por el gestor de ajustes de página, sin excepción y sin
 *   perder los demás ajustes del kit;
 * - un kit que se está guardando no se toca, y una excepción del camino de siempre nunca escapa;
 * - los cuatro ganchos llaman al método nuevo y ninguno llama directamente al del núcleo.
 */
namespace Elementor {
	class Utils {
		public static $cli = false;
		public static function is_wp_cli() { return self::$cli; }
	}
}
namespace Elementor\Core\Settings\Page {
	class Manager { const META_KEY = '_elementor_page_settings'; }
}
namespace Elementor\Core\Settings {
	class Page_Saver {
		public function save_settings( array $settings, $id ) { Manager::$saved[] = array( $id, $settings ); }
	}
	class Manager {
		public static $saved = array();
		public static function get_settings_managers( $name ) { return 'page' === $name ? new Page_Saver() : null; }
	}
}
namespace ElementorPro {
	class Plugin {
		public static $elementor;
		public static function elementor() { return self::$elementor; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_sync( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['can'] = false;
	function current_user_can( $cap, $id = 0 ) { return 'edit_post' === $cap && 77 === $id && $GLOBALS['can']; }

	class Kit_Double {
		public $saving = false;
		public $meta = array( 'site_name' => 'Tienda', 'woocommerce_cart_page_id' => 5 );
		public function get_main_id() { return 77; }
		public function is_saving() { return $this->saving; }
		public function get_meta( $key ) { return '_elementor_page_settings' === $key ? $this->meta : null; }
	}
	class Kits_Double {
		public $kit;
		public $calls = array();
		public $throw = null;
		public function get_active_kit() { return $this->kit; }
		public function update_kit_settings_based_on_option( $key, $value ) {
			if ( $this->throw ) { throw $this->throw; }
			if ( ! $GLOBALS['can'] && ! \Elementor\Utils::is_wp_cli() ) { throw new Exception( 'Access denied.' ); }
			$this->calls[] = array( $key, $value );
		}
	}

	// El método real, tal cual está en el módulo derivado.
	$source = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/woocommerce/module.php' );
	$start  = strpos( $source, '	private function update_kit_setting_from_option(' );
	check_sync( false !== $start, 'module.php define update_kit_setting_from_option().' );
	$depth = 0;
	for ( $i = strpos( $source, '{', $start ), $n = strlen( $source ); $i < $n; $i++ ) {
		if ( '{' === $source[ $i ] ) { $depth++; }
		elseif ( '}' === $source[ $i ] && 0 === --$depth ) { break; }
	}
	$method = substr( $source, $start, $i - $start + 1 );
	eval( 'namespace ElementorPro\Modules\Woocommerce; use ElementorPro\Plugin; use Elementor\Utils; class Sync_Probe { public function run( $k, $v ) { $this->update_kit_setting_from_option( $k, $v ); } ' . $method . ' }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	$probe = new \ElementorPro\Modules\Woocommerce\Sync_Probe();

	$reset = static function () {
		$kits = new Kits_Double();
		$kits->kit = new Kit_Double();
		\ElementorPro\Plugin::$elementor = (object) array( 'kits_manager' => $kits );
		\Elementor\Core\Settings\Manager::$saved = array();
		\Elementor\Utils::$cli = false;
		return $kits;
	};

	// 1. Con permiso: el camino de siempre.
	$kits = $reset();
	$GLOBALS['can'] = true;
	$probe->run( 'woocommerce_cart_page_id', 9 );
	check_sync( array( array( 'woocommerce_cart_page_id', 9 ) ) === $kits->calls && array() === \Elementor\Core\Settings\Manager::$saved, 'Con permiso se guarda como siempre.' );

	// 2. Sin permiso (visitante en la instalación perezosa, WP-cron): sin excepción y con el mismo valor en el kit.
	$kits = $reset();
	$GLOBALS['can'] = false;
	$probe->run( 'woocommerce_checkout_page_id', 12 );
	check_sync( array() === $kits->calls, 'Sin permiso no se pasa por el guardado que comprueba edit_post.' );
	check_sync( array( array( 77, array( 'site_name' => 'Tienda', 'woocommerce_cart_page_id' => 5, 'woocommerce_checkout_page_id' => 12 ) ) ) === \Elementor\Core\Settings\Manager::$saved, 'Sin permiso, el valor llega a la meta del kit sin perder los demás ajustes.' );

	// 3. WP-CLI sin usuario: el camino de siempre, que en WP-CLI no comprueba permisos.
	$kits = $reset();
	\Elementor\Utils::$cli = true;
	$probe->run( 'woocommerce_terms_page_id', 3 );
	check_sync( array( array( 'woocommerce_terms_page_id', 3 ) ) === $kits->calls, 'En WP-CLI, el camino de siempre.' );

	// 4. Un kit que se está guardando no se toca (lo resuelve su propio guardado).
	$kits = $reset();
	$kits->kit->saving = true;
	$probe->run( 'woocommerce_myaccount_page_id', 4 );
	check_sync( array() === $kits->calls && array() === \Elementor\Core\Settings\Manager::$saved, 'Un kit que se está guardando no se toca.' );

	// 5. Una excepción del camino de siempre nunca escapa.
	$kits = $reset();
	$GLOBALS['can'] = true;
	$kits->throw = new Exception( 'Invalid post.' );
	$probe->run( 'woocommerce_cart_page_id', 9 );
	check_sync( true, 'Una excepción del guardado no termina la petición.' );

	// 6. Sin kit activo: el camino de siempre, dentro del try.
	$kits = $reset();
	$kits->kit = null;
	$GLOBALS['can'] = false;
	$probe->run( 'woocommerce_cart_page_id', 9 );
	check_sync( array() === \Elementor\Core\Settings\Manager::$saved, 'Sin kit activo no se escribe nada.' );

	// 7. Los cuatro ganchos usan el método nuevo.
	$hooks = substr( $source, strpos( $source, 'public function add_update_kit_settings_hooks()' ), 1400 );
	check_sync( 4 === substr_count( $hooks, '$this->update_kit_setting_from_option(' ) && false === strpos( $hooks, 'kits_manager->update_kit_settings_based_on_option(' ), 'Los cuatro ganchos llaman a update_kit_setting_from_option().' );

	echo "DIGITALÍSIMO Elements: las páginas de WooCommerce llegan al kit también sin permiso (sin «Access denied» ni error 500), y con permiso como siempre.\n";
}
