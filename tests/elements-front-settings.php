<?php
/**
 * F-front, frente B: ajustes de rendimiento del front y pantalla «Rendimiento» (§3.2, §4, §6.1).
 *
 * Sin WordPress ni el constructor: stubs en memoria (opciones por sitio, `sitemeta`, nonces, capacidades, filtros y
 * una tabla de opciones para el cerrojo de la transición). El análisis de iconos (frente C) se simula con su contrato
 * (`Icon_Scan::run_batch()`, `verdict()`, `state()`). Comprueba:
 * - paridad: las seis claves en `defaults()`, `sanitize()`, `network_fields()`, `save_network()` y la pantalla;
 * - «Heredar de la red» sólo en el sitio de una red (nunca en la red ni en un sitio único);
 * - herencia por defecto, `own` acotado a las seis y resolución `sitio → red → default`;
 * - capacidad por acción (un editor no guarda; un administrador de sitio no guarda la red), un nonce por acción y por
 *   sitio, sólo `POST`, y redirección construida en el servidor para la misma superficie;
 * - «Aplicar» y «Revertir» sólo cambian `svg_icons`; «Analizar» copia el veredicto al aplicado;
 * - extractos del análisis con `esc_html()`, nunca dentro de `<style>` ni `<script>`;
 * - textos sin el nombre del constructor original, sin dominios de `vendors.json` y sin enlaces fuera del sitio;
 * - F7a: `page_visits_no_consent` («Privacidad») con los cuatro puntos de paridad, «Heredar de la red» sólo en el sitio,
 *   apagada por defecto y fuera de la transición (ni huella ni vaciado).
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $files_manager;
		public $experiments;
	}
}

namespace Digitalisimo\Elements\Front {
	/** Contrato del análisis de iconos (frente C). */
	final class Icon_Scan {
		const OPTION = 'digitalisimo_elements_icon_scan';
		public static $calls    = array();
		public static $verdicts = array();
		public static $state    = array();
		public static function state(): array {
			return self::$state;
		}
		public static function run_batch( int $limit = 200 ): array {
			self::$calls[] = 'run_batch:' . $limit;
			return self::$state;
		}
		public static function scan_text( string $text, string $kind ): array {
			return array();
		}
		public static function verdict(): string {
			return self::$verdicts ? array_shift( self::$verdicts ) : 'pending';
		}
		public static function reset(): void {
			self::$calls[] = 'reset';
		}
	}
}

namespace {
	define( 'ABSPATH', '/srv/sitio/' );

	final class Fs_Redirect extends RuntimeException {
		public $url;
		public function __construct( $url ) {
			parent::__construct( 'redirect' );
			$this->url = $url;
		}
	}

	final class Fs_Die extends RuntimeException {
		public $status;
		public function __construct( $message, $status ) {
			parent::__construct( 'wp_die: ' . $message );
			$this->status = $status;
		}
	}

	final class Digitalisimo_Elements_Core {
		public static function bundled() {
			return ! empty( $GLOBALS['fs']['bundled'] );
		}
	}

	final class Digitalisimo_Elements_Brand {
		const PAGE = 'digitalisimo-elements-about';
	}

	final class Fs_Files {
		public $clears = 0;
		public function clear_cache() {
			++$this->clears;
		}
	}

	final class Fs_Experiments {
		public function is_feature_active( $name ) {
			return ! empty( $GLOBALS['fs']['svg_real'] );
		}
	}

	/** Tabla de opciones en memoria para el cerrojo (`INSERT IGNORE`, robo y borrado con token). */
	final class Fs_Wpdb {
		public $options = 'wp_options';
		public function prepare( $sql, ...$args ) {
			return array( 'sql' => $sql, 'args' => $args );
		}
		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}
		private function &table() {
			return $GLOBALS['fs']['options'][ $GLOBALS['fs']['blog'] ];
		}
		public function query( $query ) {
			$table = &$this->table();
			$sql   = $query['sql'];
			$args  = $query['args'];
			if ( 0 === strpos( $sql, 'INSERT IGNORE INTO' ) ) {
				if ( array_key_exists( $args[0], $table ) ) {
					return 0;
				}
				$table[ $args[0] ] = $args[1];
				return 1;
			}
			if ( 0 === strpos( $sql, 'UPDATE' ) ) {
				if ( isset( $table[ $args[1] ] ) && $table[ $args[1] ] === $args[2] ) {
					$table[ $args[1] ] = $args[0];
					return 1;
				}
				return 0;
			}
			if ( 0 === strpos( $sql, 'DELETE' ) ) {
				$suffix = stripcslashes( substr( $args[1], 1 ) );
				if ( isset( $table[ $args[0] ] ) && substr( (string) $table[ $args[0] ], -strlen( $suffix ) ) === $suffix ) {
					unset( $table[ $args[0] ] );
					return 1;
				}
				return 0;
			}
			throw new RuntimeException( 'Consulta no prevista: ' . $sql );
		}
		public function get_var( $query ) {
			$table = $this->table();
			if ( 0 === strpos( $query['sql'], 'SELECT option_value' ) ) {
				return array_key_exists( $query['args'][0], $table ) ? (string) $table[ $query['args'][0] ] : null;
			}
			if ( 0 === strpos( $query['sql'], 'SELECT option_id' ) ) {
				return array_key_exists( $query['args'][0], $table ) ? '1' : null;
			}
			throw new RuntimeException( 'Consulta no prevista: ' . $query['sql'] );
		}
	}
	$GLOBALS['wpdb'] = new Fs_Wpdb();

	function fs_reset( array $over = array() ) {
		$GLOBALS['fs'] = array_merge(
			array(
				'multisite' => true,
				'network'   => false,
				'admin'     => true,
				'bundled'   => true,
				'svg_real'  => false,
				'blog'      => 1,
				'user'      => 1,
				'caps'      => array( 'manage_options' ),
				'options'   => array( 1 => array(), 2 => array() ),
				'site'      => array(),
				'notopts'   => array(),
				'filters'   => array(),
				'actions'   => array(),
				'submenus'  => array(),
				'events'    => array(),
				'writes'    => array(),
				'site_reads' => 0,
			),
			$over
		);
		$_POST   = array();
		$_GET    = array();
		$_SERVER = array( 'REQUEST_METHOD' => 'POST' );
		$GLOBALS['menu'] = array();
		\Elementor\Plugin::$instance                = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->files_manager = new Fs_Files();
		\Elementor\Plugin::$instance->experiments   = new Fs_Experiments();
		\Digitalisimo\Elements\Front\Icon_Scan::$calls    = array();
		\Digitalisimo\Elements\Front\Icon_Scan::$verdicts = array();
		\Digitalisimo\Elements\Front\Icon_Scan::$state    = array();
		\Digitalisimo\Elements\Front\Transition::forget();
	}
	function fs_clears() {
		return \Elementor\Plugin::$instance->files_manager->clears;
	}

	// Stubs de WordPress.
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['fs']['actions'][] = array( $hook, $callback, $priority );
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['fs']['filters'][ $hook ][] = $callback;
	}
	function has_filter( $hook, $callback = false ) {
		return ! empty( $GLOBALS['fs']['filters'][ $hook ] );
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['fs']['filters'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
	function is_admin() {
		return $GLOBALS['fs']['admin'];
	}
	function is_multisite() {
		return $GLOBALS['fs']['multisite'];
	}
	function is_network_admin() {
		return $GLOBALS['fs']['network'];
	}
	function wp_installing() {
		return false;
	}
	function wp_doing_ajax() {
		return false;
	}
	function get_current_blog_id() {
		return $GLOBALS['fs']['blog'];
	}
	function get_current_user_id() {
		return $GLOBALS['fs']['user'];
	}
	function current_user_can( $cap, ...$args ) {
		return in_array( $cap, $GLOBALS['fs']['caps'], true );
	}
	function get_option( $name, $default = false ) {
		$pre = apply_filters( "pre_option_{$name}", false, $name, $default );
		if ( false !== $pre ) {
			return $pre;
		}
		$options = $GLOBALS['fs']['options'][ $GLOBALS['fs']['blog'] ];
		if ( array_key_exists( $name, $options ) ) {
			return $options[ $name ];
		}
		$GLOBALS['fs']['notopts'][ $GLOBALS['fs']['blog'] ][ $name ] = true;
		return apply_filters( "default_option_{$name}", $default, $name, func_num_args() > 1 );
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['fs']['writes'][] = array( $GLOBALS['fs']['blog'], $name, $autoload );
		$GLOBALS['fs']['options'][ $GLOBALS['fs']['blog'] ][ $name ] = $value;
		unset( $GLOBALS['fs']['notopts'][ $GLOBALS['fs']['blog'] ][ $name ] );
		return true;
	}
	function get_site_option( $name, $default = false ) {
		++$GLOBALS['fs']['site_reads'];
		return array_key_exists( $name, $GLOBALS['fs']['site'] ) ? $GLOBALS['fs']['site'][ $name ] : $default;
	}
	function update_site_option( $name, $value ) {
		$GLOBALS['fs']['writes'][] = array( 'network', $name, null );
		$GLOBALS['fs']['site'][ $name ] = $value;
		return true;
	}
	function wp_load_alloptions() {
		return $GLOBALS['fs']['options'][ $GLOBALS['fs']['blog'] ];
	}
	function wp_cache_get( $key, $group = '' ) {
		if ( 'notoptions' === $key ) {
			return $GLOBALS['fs']['notopts'][ $GLOBALS['fs']['blog'] ] ?? false;
		}
		return false;
	}
	function wp_cache_delete( $key, $group = '' ) {
		return true;
	}
	function wp_next_scheduled( $hook ) {
		foreach ( $GLOBALS['fs']['events'] as $event ) {
			if ( $event[0] === $GLOBALS['fs']['blog'] && $event[1] === $hook ) {
				return $event[2];
			}
		}
		return false;
	}
	function wp_schedule_single_event( $time, $hook ) {
		$GLOBALS['fs']['events'][] = array( $GLOBALS['fs']['blog'], $hook, $time );
		return true;
	}
	function wp_generate_password( $length = 12, $special = true ) {
		return substr( str_repeat( 'abcdef0123456789', 4 ), 0, $length );
	}
	function switch_to_blog( $id ) {
		$GLOBALS['fs']['blog'] = (int) $id;
	}
	function restore_current_blog() {
	}
	function wp_create_nonce( $action ) {
		return 'n' . substr( md5( $action . '|' . $GLOBALS['fs']['user'] ), 0, 10 );
	}
	function wp_verify_nonce( $nonce, $action ) {
		return hash_equals( wp_create_nonce( $action ), (string) $nonce ) ? 1 : false;
	}
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$field = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '">';
		if ( $echo ) {
			echo $field; // phpcs:ignore
		}
		return $field;
	}
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return esc_html( $text );
	}
	function esc_url( $url ) {
		return esc_html( $url );
	}
	function admin_url( $path = '' ) {
		return 'https://b.test/wp-admin/' . ltrim( $path, '/' );
	}
	function network_admin_url( $path = '' ) {
		return 'https://b.test/wp-admin/network/' . ltrim( $path, '/' );
	}
	function add_query_arg( $args, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
	}
	function wp_safe_redirect( $url, $status = 302 ) {
		throw new Fs_Redirect( $url );
	}
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new Fs_Die( $message, is_array( $args ) && isset( $args['response'] ) ? $args['response'] : 500 );
	}
	function add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug, $callback = null ) {
		$GLOBALS['fs']['submenus'][] = compact( 'parent', 'page_title', 'menu_title', 'capability', 'slug' );
		return $slug;
	}

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	};
	$capture = function ( callable $render ) {
		ob_start();
		try {
			$render();
		} finally {
			$html = ob_get_clean();
		}
		return $html;
	};
	/** Corre un handle_*() y devuelve la URL de la redirección o el estado de wp_die(). */
	$handle = function ( callable $handler ) {
		try {
			$handler();
		} catch ( Fs_Redirect $redirect ) {
			return array( 'redirect' => $redirect->url );
		} catch ( Fs_Die $die ) {
			return array( 'die' => $die->status );
		}
		throw new RuntimeException( 'El handler debía redirigir o terminar con wp_die().' );
	};

	$front = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-front';
	require $front . '/class-module.php';
	\Digitalisimo\Elements\Front\Module::register_autoloader();

	use Digitalisimo\Elements\Front\Icon_Scan;
	use Digitalisimo\Elements\Front\Settings;
	use Digitalisimo\Elements\Front\Settings_Page;
	use Digitalisimo\Elements\Front\Transition;

	$keys     = array( 'pro_conditional', 'font_swap', 'svg_icons', 'lazy_libs', 'element_cache_off', 'image_loading_off' );
	$privacy  = array( 'page_visits_no_consent' );
	$all      = array_merge( $keys, $privacy );
	$site     = Settings::OPTION;
	$network  = Settings::NETWORK_OPTION;
	$page_url = 'https://b.test/wp-admin/admin.php?page=digitalisimo-elements-front';
	$net_url  = 'https://b.test/wp-admin/network/admin.php?page=digitalisimo-elements-front';
	$nonce    = function ( $action ) {
		return wp_create_nonce( Settings_Page::nonce_action( $action ) );
	};

	// 1. Paridad: las seis claves en los cuatro puntos y en la pantalla.
	fs_reset();
	$check( $keys === Settings::KEYS, 'Settings::KEYS son las seis claves de §3.2, en su orden.' );
	$check( $privacy === Settings::PRIVACY_KEYS && $all === Settings::keys(), 'F7a: page_visits_no_consent es la clave de «Privacidad» y keys() suma las seis y ella.' );
	$check( 'digitalisimo_elements_front' === $site && 'digitalisimo_elements_front_defaults' === $network, 'Nombres de las opciones de sitio y de red (§4).' );
	$check(
		array(
			'pro_conditional'   => true,
			'font_swap'         => true,
			'svg_icons'         => false,
			'lazy_libs'         => false,
			'element_cache_off' => false,
			'image_loading_off' => false,
			'page_visits_no_consent' => false,
		) === Settings::defaults(),
		'defaults(): las siete claves con su valor neutro (la de privacidad, apagada).'
	);
	$check( array_merge( $all, array( 'own' ) ) === array_keys( Settings::sanitize( array() ) ), 'sanitize(): las siete claves y own.' );
	$check( $all === array_keys( Settings::save_network( array() ) ), 'save_network(): las siete claves.' );
	$check( $all === array_keys( Settings::LABELS ) && $all === array_keys( Settings::HELP ), 'Cada clave tiene etiqueta y ayuda, y ninguna más.' );
	$network_html = $capture( array( Settings::class, 'network_fields' ) );
	$site_html    = $capture( array( Settings_Page::class, 'site_fields' ) );
	foreach ( $keys as $key ) {
		$check( 2 === substr_count( $network_html, 'name="' . $network . '[' . $key . ']"' ), "network_fields() pinta {$key} (oculto y casilla)." );
		$check( false !== strpos( $site_html, 'name="' . $site . '[' . $key . ']"' ), "La pantalla del sitio pinta {$key}." );
		$check( false !== strpos( $site_html, 'name="' . $site . '[inherit][' . $key . ']"' ), "La pantalla del sitio de una red pinta «Heredar de la red» para {$key}." );
	}
	$check( false !== strpos( $network_html, Settings::NETWORK_SVG_LABEL ), 'En la red, svg_icons dice que sólo se aplica donde el análisis salga limpio.' );

	// 2. «Heredar de la red» sólo en el sitio de una red.
	$check( 6 === substr_count( $site_html, 'Heredar de la red' ), 'El sitio de una red tiene «Heredar de la red» en los seis interruptores.' );
	$check( false === strpos( $network_html, 'Heredar de la red' ) && false === strpos( $network_html, '[inherit]' ), 'network_fields() nunca muestra «Heredar de la red».' );
	fs_reset( array( 'network' => true, 'caps' => array( 'manage_network_options' ) ) );
	$network_page = $capture( array( Settings_Page::class, 'render_network' ) );
	$check( false === strpos( $network_page, 'Heredar de la red' ) && false !== strpos( $network_page, 'name="action" value="' . Settings_Page::ACTION_SAVE_NETWORK . '"' ), 'La pantalla de red guarda con su propia acción y sin «Heredar de la red».' );
	$check( false === strpos( $capture( array( Settings_Page::class, 'site_fields' ) ), 'Heredar de la red' ), 'Un mismo formulario en la red (is_network_admin) no muestra la casilla.' );
	fs_reset( array( 'multisite' => false ) );
	$single = $capture( array( Settings_Page::class, 'render' ) );
	$check( false === strpos( $single, 'Heredar de la red' ) && false === strpos( $single, '[inherit]' ) && false === strpos( $single, '<script' ), 'Un sitio único no muestra «Heredar de la red» ni su script.' );

	// 3. sanitize(): cada clave con ! empty() y own acotado a las seis.
	fs_reset();
	$clean = Settings::sanitize(
		array(
			'pro_conditional'   => '1',
			'font_swap'         => '0',
			'svg_icons'         => 'yes',
			'lazy_libs'         => '',
			'element_cache_off' => array(),
			'evil'              => '1',
			'inherit'           => array( 'pro_conditional' => '0', 'font_swap' => '1', 'lazy_libs' => '0', 'evil' => '0', 'own' => '0' ),
		)
	);
	$check( true === $clean['pro_conditional'] && false === $clean['font_swap'] && true === $clean['svg_icons'] && false === $clean['lazy_libs'] && false === $clean['element_cache_off'] && false === $clean['image_loading_off'], 'sanitize(): cada clave con ! empty(), siempre booleana.' );
	$check( ! isset( $clean['evil'] ) && ! isset( $clean['inherit'] ) && array( 'pro_conditional', 'lazy_libs' ) === $clean['own'], 'sanitize(): own sale del mapa inherit, sólo con las seis y en su orden; lo demás se descarta.' );
	$check( array( 'font_swap', 'svg_icons' ) === Settings::sanitize( array( 'own' => array( 'svg_icons', 'bogus', 'svg_icons', 5, array( 'x' ), 'font_swap', '__proto__' ) ) )['own'], 'sanitize(): una lista own explícita queda acotada a las seis, sin repetir.' );
	$check( array() === Settings::sanitize( array( 'font_swap' => '1' ) )['own'] && array() === Settings::sanitize( array( 'inherit' => 'x', 'own' => 'svg_icons' ) )['own'], 'sanitize(): sin mapa ni lista válidos, en una red todo hereda.' );
	fs_reset( array( 'multisite' => false ) );
	$check( $all === Settings::sanitize( array( 'inherit' => array( 'font_swap' => '1' ) ) )['own'], 'sanitize(): en un sitio único no hay nada que heredar: own son todas.' );

	// 4. save_network(): la misma regla, clave por clave, sin saneado genérico.
	fs_reset();
	$saved = Settings::save_network( array( 'pro_conditional' => '0', 'font_swap' => '<script>', 'svg_icons' => '1', 'evil' => 'x', 'inherit' => array( 'font_swap' => '1' ) ) );
	$check( array( 'pro_conditional' => false, 'font_swap' => true, 'svg_icons' => true, 'lazy_libs' => false, 'element_cache_off' => false, 'image_loading_off' => false, 'page_visits_no_consent' => false ) === $saved && $saved === $GLOBALS['fs']['site'][ $network ], 'save_network(): siete booleanos en sitemeta; lo demás se descarta.' );
	$check( array( array( 'network', $network, null ) ) === $GLOBALS['fs']['writes'], 'save_network() sólo escribe la opción de red: ningún sitio cambia.' );
	fs_reset( array( 'multisite' => false ) );
	Settings::save_network( array( 'font_swap' => '0' ) );
	$check( array() === $GLOBALS['fs']['writes'], 'save_network() no escribe nada fuera de una red.' );

	// 5. Resolución sitio → red → default y herencia por defecto.
	fs_reset( array( 'blog' => 2 ) );
	foreach ( $keys as $key ) {
		$check( Settings::inherits_network( $key ) && Settings::defaults()[ $key ] === Settings::get( $key ) && 'default' === Settings::origin( $key ), "Un sitio que nunca guardó hereda {$key} y vale su neutro." );
	}
	$GLOBALS['fs']['site'][ $network ] = array( 'font_swap' => false, 'lazy_libs' => true );
	$check( false === Settings::get( 'font_swap' ) && true === Settings::get( 'lazy_libs' ) && 'network' === Settings::origin( 'font_swap' ), 'Un valor de red recién definido rige de inmediato en el sitio que nunca lo personalizó.' );
	update_option( $site, Settings::sanitize( array( 'font_swap' => '1', 'lazy_libs' => '1', 'inherit' => array( 'font_swap' => '0', 'lazy_libs' => '1' ) ) ) );
	$check( true === Settings::get( 'font_swap' ) && ! Settings::inherits_network( 'font_swap' ) && 'site' === Settings::origin( 'font_swap' ), 'El sitio que personaliza conserva su valor aunque la red diga otro.' );
	$check( true === Settings::get( 'lazy_libs' ) && Settings::inherits_network( 'lazy_libs' ) && 'network' === Settings::origin( 'lazy_libs' ), 'Lo que el sitio no personaliza sigue a la red.' );
	$GLOBALS['fs']['site'][ $network ]['lazy_libs'] = false;
	$check( false === Settings::get( 'lazy_libs' ) && true === Settings::get( 'font_swap' ), 'Cambiar la red mueve lo heredado y no lo personalizado.' );
	update_option( $site, array( 'own' => array( 'image_loading_off' ) ) );
	$check( false === Settings::get( 'image_loading_off' ) && false === Settings::get( 'lazy_libs' ), 'Clave propia sin valor guardado: red y luego neutro.' );
	update_option( $site, 'basura' );
	$check( Settings::inherits_network( 'font_swap' ) && false === Settings::get( 'font_swap' ), 'Una opción del sitio dañada hereda todo.' );
	$check( false === Settings::get( 'otra' ) && false === Settings::inherits_network( 'otra' ), 'Una clave desconocida es false y no hereda.' );
	fs_reset( array( 'multisite' => false ) );
	update_option( $site, Settings::sanitize( array( 'font_swap' => '0', 'pro_conditional' => '1' ) ) );
	$GLOBALS['fs']['site'][ $network ] = array( 'font_swap' => true );
	$check( false === Settings::get( 'font_swap' ) && ! Settings::inherits_network( 'font_swap' ) && 0 === $GLOBALS['fs']['site_reads'], 'En un sitio único manda el sitio y no se lee sitemeta.' );

	// 6. Capacidad por acción, un nonce por acción y por sitio, y sólo POST.
	$site_actions = array(
		Settings_Page::ACTION_SAVE   => 'process_save',
		Settings_Page::ACTION_SCAN   => 'process_scan',
		Settings_Page::ACTION_APPLY  => 'process_apply',
		Settings_Page::ACTION_REVERT => 'process_revert',
	);
	$check( Settings_Page::SITE_ACTIONS === array_keys( $site_actions ) && 5 === count( array_unique( array_merge( array_keys( $site_actions ), array( Settings_Page::ACTION_SAVE_NETWORK ) ) ) ), 'Cinco acciones distintas: cuatro del sitio y una de la red.' );
	$check( 'digitalisimo_elements_front_save' === Settings_Page::ACTION_SAVE && 'digitalisimo_elements_front_save_network' === Settings_Page::ACTION_SAVE_NETWORK && 'digitalisimo_elements_front_icons_scan' === Settings_Page::ACTION_SCAN && 'digitalisimo_elements_front_icons_apply' === Settings_Page::ACTION_APPLY && 'digitalisimo_elements_front_icons_revert' === Settings_Page::ACTION_REVERT, 'Nombres de las acciones de §3.2.' );
	foreach ( $site_actions as $action => $method ) {
		fs_reset( array( 'caps' => array( 'edit_posts', 'manage_network_options' ) ) );
		$_POST    = array( '_wpnonce' => $nonce( $action ), $site => array( 'font_swap' => '0' ) );
		$result   = call_user_func( array( Settings_Page::class, $method ) );
		$check( 403 === $result['status'] && 'forbidden' === $result['notice'] && array() === $GLOBALS['fs']['writes'], "{$action}: sin manage_options (un editor, aunque gestione la red) no cambia nada." );

		fs_reset();
		foreach ( array_merge( array_keys( $site_actions ), array( Settings_Page::ACTION_SAVE_NETWORK ) ) as $other ) {
			if ( $other === $action ) {
				continue;
			}
			$_POST  = array( '_wpnonce' => $nonce( $other ) );
			$result = call_user_func( array( Settings_Page::class, $method ) );
			$check( 403 === $result['status'] && 'nonce' === $result['notice'] && array() === $GLOBALS['fs']['writes'], "{$action}: el nonce de {$other} no vale." );
		}
		$GLOBALS['fs']['blog'] = 2;
		$foreign               = $nonce( $action );
		$GLOBALS['fs']['blog'] = 1;
		$_POST                 = array( '_wpnonce' => $foreign );
		$result                = call_user_func( array( Settings_Page::class, $method ) );
		$check( 403 === $result['status'] && 'nonce' === $result['notice'], "{$action}: el nonce de otro sitio de la red no vale." );
		$_POST   = array( '_wpnonce' => $nonce( $action ) );
		$_SERVER = array( 'REQUEST_METHOD' => 'GET' );
		$result  = call_user_func( array( Settings_Page::class, $method ) );
		$check( 'method' === $result['notice'] && empty( $result['network'] ) && array() === $GLOBALS['fs']['writes'], "{$action}: sólo POST." );
	}
	fs_reset( array( 'caps' => array( 'manage_options' ) ) );
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE_NETWORK ), $network => array( 'font_swap' => '0' ) );
	$result = Settings_Page::process_save_network();
	$check( 403 === $result['status'] && ! empty( $result['network'] ) && array() === $GLOBALS['fs']['writes'], 'Un administrador de sitio no guarda la red.' );
	fs_reset( array( 'multisite' => false, 'caps' => array( 'manage_options', 'manage_network_options' ) ) );
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE_NETWORK ), $network => array( 'font_swap' => '0' ) );
	$result = Settings_Page::process_save_network();
	$check( 403 === $result['status'] && array() === $GLOBALS['fs']['writes'], 'Fuera de una red no hay valores de red que guardar.' );
	fs_reset( array( 'caps' => array( 'manage_network_options' ) ) );
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE ), $network => array( 'font_swap' => '0' ) );
	$result = Settings_Page::process_save_network();
	$check( 403 === $result['status'] && 'nonce' === $result['notice'], 'El nonce del sitio no guarda la red.' );
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE_NETWORK ), $network => array( 'font_swap' => '0', 'lazy_libs' => '1' ) );
	$result = Settings_Page::process_save_network();
	$check( 'network-saved' === $result['notice'] && ! empty( $result['network'] ) && false === $GLOBALS['fs']['site'][ $network ]['font_swap'] && true === $GLOBALS['fs']['site'][ $network ]['lazy_libs'], 'La red guarda con manage_network_options y su propio nonce.' );
	$check( array( array( 'network', $network, null ) ) === $GLOBALS['fs']['writes'] && 0 === fs_clears() && array() === $GLOBALS['fs']['events'], 'Guardar la red sólo escribe su opción: ni vacía cachés ni agenda sitios.' );

	// 7. Guardar en el sitio transita en el momento.
	fs_reset( array( 'blog' => 2 ) );
	$_POST  = array(
		'_wpnonce' => $nonce( Settings_Page::ACTION_SAVE ),
		$site      => array(
			'pro_conditional' => '1',
			'font_swap'       => '0',
			'lazy_libs'       => '1',
			'inherit'         => array( 'pro_conditional' => '1', 'font_swap' => '0', 'lazy_libs' => '0' ),
		),
	);
	$result = Settings_Page::process_save();
	$stored = $GLOBALS['fs']['options'][2][ $site ];
	$check( 'saved' === $result['notice'] && array( 'font_swap', 'lazy_libs' ) === $stored['own'] && false === $stored['font_swap'] && true === $stored['lazy_libs'], 'Guardar en el sitio sanea y guarda lo deseado con own.' );
	$applied = Transition::applied();
	$check( true === $applied['pro_conditional'] && false === $applied['font_swap'] && true === $applied['lazy_libs'] && 1 === fs_clears() && array() === $GLOBALS['fs']['options'][1], 'Guardar en el sitio transita en el momento, sólo en ese sitio, con un vaciado.' );
	$check( in_array( array( 2, $site, true ), $GLOBALS['fs']['writes'], true ) && in_array( array( 2, Transition::APPLIED, true ), $GLOBALS['fs']['writes'], true ), 'Lo deseado y lo aplicado se guardan con carga automática.' );

	// 8. Redirección construida en el servidor, para la misma superficie.
	fs_reset();
	$_POST    = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE ), '_wp_http_referer' => 'https://malo.test/robar', 'redirect_to' => 'https://malo.test/', $site => array( 'font_swap' => '1' ) );
	$_REQUEST = $_POST;
	$_GET     = array( 'redirect_to' => 'https://malo.test/' );
	$out      = $handle( array( Settings_Page::class, 'handle_save' ) );
	$check( isset( $out['redirect'] ) && $page_url . '&front_notice=saved' === $out['redirect'], 'handle_save() vuelve a la pantalla del sitio, construida en el servidor: ' . json_encode( $out ) );
	fs_reset( array( 'caps' => array( 'manage_network_options' ) ) );
	$_POST = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE_NETWORK ), '_wp_http_referer' => 'https://malo.test/', $network => array() );
	$out   = $handle( array( Settings_Page::class, 'handle_save_network' ) );
	$check( isset( $out['redirect'] ) && $net_url . '&front_notice=network-saved' === $out['redirect'], 'handle_save_network() vuelve a la pantalla de red: ' . json_encode( $out ) );
	fs_reset( array( 'caps' => array() ) );
	$_POST = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE ) );
	$out   = $handle( array( Settings_Page::class, 'handle_save' ) );
	$check( isset( $out['die'] ) && 403 === $out['die'], 'Sin capacidad, wp_die() con 403.' );
	fs_reset();
	$_POST = array( '_wpnonce' => $nonce( Settings_Page::ACTION_APPLY ) );
	$out   = $handle( array( Settings_Page::class, 'handle_apply' ) );
	$check( isset( $out['redirect'] ) && $page_url . '&front_notice=icons-applied' === $out['redirect'], 'handle_apply() vuelve a la pantalla del sitio.' );
	$_SERVER = array( 'REQUEST_METHOD' => 'GET' );
	$out     = $handle( array( Settings_Page::class, 'handle_revert' ) );
	$check( isset( $out['redirect'] ) && $page_url . '&front_notice=method' === $out['redirect'], 'Un GET vuelve a la pantalla con el aviso, sin cambiar nada.' );

	// 9. «Aplicar», «Revertir» y «Analizar».
	fs_reset( array( 'multisite' => false ) );
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_APPLY ) );
	$result = Settings_Page::process_apply();
	$check( 'icons-applied' === $result['notice'] && true === Settings::get( 'svg_icons' ) && true === Settings::get( 'pro_conditional' ) && true === Settings::get( 'font_swap' ) && false === Settings::get( 'lazy_libs' ), '«Aplicar» en un sitio único sólo cambia svg_icons.' );
	$check( true === Transition::applied()['svg_icons'] && 1 === fs_clears(), '«Aplicar» transita en el momento.' );
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_REVERT ) );
	$result = Settings_Page::process_revert();
	$check( 'icons-reverted' === $result['notice'] && false === Settings::get( 'svg_icons' ) && false === Transition::applied()['svg_icons'] && 2 === fs_clears() && true === Settings::get( 'pro_conditional' ), '«Revertir» apaga svg_icons y vacía otra vez.' );
	fs_reset( array( 'blog' => 2 ) );
	$GLOBALS['fs']['site'][ $network ] = array( 'svg_icons' => true, 'lazy_libs' => true );
	update_option( $site, Settings::sanitize( array( 'font_swap' => '0', 'inherit' => array( 'font_swap' => '0' ) ) ) );
	Icon_Scan::$verdicts = array( 'findings' );
	Transition::run_now();
	$check( false === Transition::applied()['svg_icons'] && 'findings' === Transition::applied()['icons'] && array( 'run_batch:200' ) === Icon_Scan::$calls, 'La red pide los iconos: el sitio que hereda analiza y, con hallazgos, no los aplica.' );
	$_POST = array( '_wpnonce' => $nonce( Settings_Page::ACTION_APPLY ) );
	Settings_Page::process_apply();
	$stored = $GLOBALS['fs']['options'][2][ $site ];
	$check( array( 'font_swap', 'svg_icons' ) === $stored['own'] && true === $stored['svg_icons'] && false === $stored['font_swap'] && Settings::inherits_network( 'lazy_libs' ), '«Aplicar» en una red añade svg_icons a own y conserva lo demás.' );
	$check( true === Transition::applied()['svg_icons'], '«Aplicar» manda aunque el análisis tenga hallazgos (el administrador los acaba de ver).' );
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_REVERT ) );
	Settings_Page::process_revert();
	$check( false === Settings::get( 'svg_icons' ) && ! Settings::inherits_network( 'svg_icons' ) && false === Transition::applied()['svg_icons'], '«Revertir» deja svg_icons apagado como elección del sitio: la red no lo vuelve a poner.' );
	fs_reset( array( 'bundled' => false ) );
	foreach ( array( Settings_Page::ACTION_APPLY => 'process_apply', Settings_Page::ACTION_REVERT => 'process_revert', Settings_Page::ACTION_SCAN => 'process_scan' ) as $action => $method ) {
		$_POST  = array( '_wpnonce' => $nonce( $action ) );
		$result = call_user_func( array( Settings_Page::class, $method ) );
		$check( 'core-original' === $result['notice'] && array() === $GLOBALS['fs']['writes'], "{$action}: con el constructor original activo no cambia nada." );
	}
	fs_reset();
	Icon_Scan::$verdicts = array( 'findings' );
	$_POST               = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SCAN ) );
	$result              = Settings_Page::process_scan();
	$check( 'scan-done' === $result['notice'] && array( 'reset', 'run_batch:200' ) === Icon_Scan::$calls && 'findings' === Transition::applied()['icons'], '«Analizar» reinicia el análisis, ejecuta un lote y copia el veredicto al aplicado.' );
	fs_reset();
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SCAN ) );
	$result = Settings_Page::process_scan();
	$check( 'scan-done-pending' === $result['notice'] && true === Transition::applied()['scanning'] && '' === Transition::applied()['icons'] && false !== wp_next_scheduled( Transition::HOOK ), '«Analizar» sin terminar sigue en segundo plano y la transición lo vigila.' );
	fs_reset();
	$GLOBALS['fs']['options'][1][ Transition::LOCK ] = time() . '|ajeno';
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SCAN ) );
	$result = Settings_Page::process_scan();
	$check( 'busy' === $result['notice'] && time() . '|ajeno' === $GLOBALS['fs']['options'][1][ Transition::LOCK ], 'Con el cerrojo tomado, «Analizar» lo dice y no lo toca.' );

	// 10. Extractos del análisis con esc_html(), nunca dentro de <style> ni <script>.
	fs_reset();
	Icon_Scan::$verdicts = array( 'findings', 'findings' );
	Icon_Scan::$state    = array(
		'findings' => array(
			array( 'source' => '<b>css</b>', 'id' => '7"><img src=x onerror=alert(1)>', 'excerpt' => '</code></td><script>alert(1)</script><style>body{display:none}</style>' ),
			array( 'source' => 'post_content', 'id' => 12, 'excerpt' => str_repeat( 'fa-star ', 40 ) ),
		),
	);
	$html = $capture( array( Settings_Page::class, 'render' ) );
	$check( false === strpos( $html, '<b>css' ) && false === strpos( $html, '<img' ) && false === strpos( $html, 'alert(1)</script>' ) && false === stripos( $html, '<style' ), 'Fuente, elemento y extracto del análisis van escapados.' );
	$check( false !== strpos( $html, '&lt;script&gt;alert(1)&lt;/script&gt;' ) && false !== strpos( $html, '&lt;b&gt;css&lt;/b&gt;' ), 'Los hallazgos se ven como texto.' );
	$scripts = array();
	preg_match_all( '#<script\b[^>]*>(.*?)</script>#s', $html, $scripts );
	$check( 1 === count( $scripts[1] ) && false === strpos( $scripts[1][0], 'alert' ) && false === strpos( $scripts[1][0], 'fa-star' ), 'El único <script> es el de «Heredar de la red» y no lleva ningún extracto.' );
	preg_match( '#<code>(fa-star[^<]*)</code>#', $html, $long );
	$check( isset( $long[1] ) && mb_strlen( html_entity_decode( $long[1] ) ) <= 120, 'Un extracto largo se corta a 120 caracteres.' );
	$_GET = array( Settings_Page::NOTICE_PARAM => '<script>' );
	$html = $capture( array( Settings_Page::class, 'render' ) );
	$check( false === strpos( $html, 'notice-' ), 'Un aviso desconocido en la URL no imprime nada.' );

	// 11. Textos: español, marca propia, sin el constructor original, sin dominios del proveedor ni enlaces externos.
	$vendors = json_decode( file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );
	$hosts   = array_merge( $vendors['hosts'], $vendors['future'] );
	$pages   = array();
	fs_reset();
	$_GET           = array( Settings_Page::NOTICE_PARAM => 'saved' );
	$pages['sitio'] = $capture( array( Settings_Page::class, 'render' ) );
	fs_reset( array( 'bundled' => false ) );
	$pages['sitio con el constructor original'] = $capture( array( Settings_Page::class, 'render' ) );
	fs_reset( array( 'multisite' => false ) );
	$pages['sitio único'] = $capture( array( Settings_Page::class, 'render' ) );
	fs_reset( array( 'network' => true, 'caps' => array( 'manage_network_options' ) ) );
	$_GET         = array( Settings_Page::NOTICE_PARAM => 'network-saved' );
	$pages['red'] = $capture( array( Settings_Page::class, 'render_network' ) );
	$texts        = json_encode( array( Settings::LABELS, Settings::HELP, Settings::NETWORK_SVG_LABEL, Settings::NETWORK_SVG_HELP, Settings::CORE_NOTE, Settings_Page::NOTICES ), JSON_UNESCAPED_UNICODE );
	$pages['textos'] = $texts;
	foreach ( $pages as $name => $html ) {
		$check( false === stripos( $html, 'elementor' ), "Pantalla {$name}: sin el nombre del constructor original." );
		foreach ( $hosts as $host ) {
			$check( false === stripos( $html, $host ), "Pantalla {$name}: sin {$host}." );
		}
		preg_match_all( '#\b(?:href|action|src)="([^"]*)"#', $html, $links );
		foreach ( $links[1] as $link ) {
			$check( '#' === substr( $link, 0, 1 ) || 0 === strpos( $link, 'https://b.test/wp-admin/' ), "Pantalla {$name}: enlace fuera del sitio ({$link})." );
		}
	}
	foreach ( array( 'sitio', 'red' ) as $name ) {
		$check( false !== strpos( $pages[ $name ], '<h1>Rendimiento</h1>' ) && false !== strpos( $pages[ $name ], 'DIGITALÍSIMO Elements' ) && false !== strpos( $pages[ $name ], 'notice-success' ), "Pantalla {$name}: título en español, marca DIGITALÍSIMO Elements y aviso." );
	}
	$check( 1 === substr_count( $pages['red'], '<form' ), 'Red: un solo formulario, sin pestañas.' );
	$check( 3 === substr_count( $pages['sitio'], '<form' ) && 1 === substr_count( $pages['sitio'], 'name="action" value="' . Settings_Page::ACTION_SAVE . '"' ), 'Sitio: un formulario de ajustes y los pequeños «Analizar» y «Aplicar» (sin nada aplicado, no hay «Revertir»).' );
	$check( false !== strpos( $pages['sitio'], 'name="action" value="' . Settings_Page::ACTION_SCAN . '"' ) && false !== strpos( $pages['sitio'], 'name="action" value="' . Settings_Page::ACTION_APPLY . '"' ) && false === strpos( $pages['sitio'], Settings_Page::ACTION_REVERT ), 'Sitio: «Analizar» y «Aplicar» con sus acciones.' );
	$check( false === strpos( $pages['sitio con el constructor original'], Settings_Page::ACTION_APPLY ) && false !== strpos( $pages['sitio con el constructor original'], Settings_Page::NOTE_ORIGINAL ), 'Con el constructor original activo, sin botones de iconos y diciendo quién decide.' );
	$check( false === strpos( $pages['red'], Settings_Page::ACTION_SCAN ) && false === strpos( $pages['red'], Settings_Page::ACTION_APPLY ), 'La red no lista el análisis de cada sitio ni sus botones.' );
	foreach ( array( 'digitalisimo-effective-state', 'digitalisimo-inheritance', 'digitalisimo-inherited-value' ) as $component ) {
		$check( false !== strpos( $pages['sitio'], $component ), "Sitio de una red: usa el componente {$component} de digitalisimo-ui." );
	}
	foreach ( array( 'digitalisimo-effective-state', 'digitalisimo-default-note', 'digitalisimo-default-value' ) as $component ) {
		$check( false !== strpos( $pages['sitio único'], $component ), "Sitio único: usa el componente {$component} de digitalisimo-ui." );
	}
	$check( false !== strpos( $pages['red'], 'digitalisimo-default-note' ), 'Red: marca los valores predeterminados.' );
	$check( 0 === preg_match( '/<link\b|<style\b|\sstyle="/i', $pages['sitio'] . $pages['red'] ), 'Sin hoja ni estilos propios.' );

	// 12. Quién decide cada interruptor.
	fs_reset();
	update_option( 'elementor_font_display', 'auto' );
	update_option( 'elementor_element_cache_ttl', '24' );
	update_option( 'elementor_experiment-e_font_icon_svg', 'inactive' );
	foreach ( array( 'font_swap', 'element_cache_off', 'svg_icons' ) as $key ) {
		$state = Settings_Page::state( $key );
		$check( false === $state['active'] && Settings_Page::NOTE_BUILDER === $state['note'], "{$key}: la fila del núcleo dice que lo eligió el sitio." );
	}
	add_filter( 'pre_option_elementor_font_display', function () {
		return 'swap';
	} );
	define( 'DIGITALISIMO_INTEGRATIONS_VERSION', '1.0.232' );
	$state = Settings_Page::state( 'font_swap' );
	$check( true === $state['active'] && 'Lo fuerza DIGITALÍSIMO SEO.' === $state['note'], 'font_swap: un pre_option_ de DIGITALÍSIMO SEO gana y se dice.' );
	fs_reset( array( 'bundled' => false ) );
	foreach ( Settings::CORE_KEYS as $key ) {
		$check( Settings_Page::NOTE_ORIGINAL === Settings_Page::state( $key )['note'], "{$key}: con el constructor original activo lo decide su configuración." );
	}
	$check( Settings_Page::NOTE_ORIGINAL !== Settings_Page::state( 'pro_conditional' )['note'] && Settings_Page::NOTE_ORIGINAL !== Settings_Page::state( 'lazy_libs' )['note'], 'La puerta y el cargador funcionan también con el constructor original.' );
	fs_reset( array( 'svg_real' => true ) );
	$state = Settings_Page::state( 'svg_icons' );
	$check( true === $state['active'] && '' !== $state['note'], 'svg_icons: un sitio nuevo ya los usa por el núcleo y se dice.' );

	// 13. Menús y ganchos.
	fs_reset();
	$GLOBALS['menu'] = array( array( 'DIGITALÍSIMO', 'read', 'digitalisimo' ), array( 'Constructor', 'edit_posts', 'elementor-home' ) );
	Settings_Page::menu();
	$sub = $GLOBALS['fs']['submenus'][0];
	$check( 'digitalisimo' === $sub['parent'] && 'Rendimiento de Elements' === $sub['menu_title'] && 'manage_options' === $sub['capability'] && Settings_Page::PAGE === $sub['slug'] && 0 === strpos( $sub['slug'], 'digitalisimo' ), 'Sitio: bajo DIGITALÍSIMO, con manage_options y el prefijo de digitalisimo-ui.' );
	foreach ( array( array( true, array( 'elementor-home', 'digitalisimo-elements-about' ), 'elementor-home' ), array( false, array( 'elementor-home', 'digitalisimo-elements-about' ), 'digitalisimo-elements-about' ), array( false, array( 'elementor' ), 'tools.php' ) ) as $case ) {
		fs_reset( array( 'bundled' => $case[0] ) );
		$GLOBALS['menu'] = array_map(
			function ( $slug ) {
				return array( $slug, 'read', $slug );
			},
			$case[1]
		);
		Settings_Page::menu();
		$check( $case[2] === $GLOBALS['fs']['submenus'][0]['parent'] && 'Rendimiento' === $GLOBALS['fs']['submenus'][0]['menu_title'], 'Sitio: menú padre ' . $case[2] . '; el del constructor sólo con el núcleo incluido.' );
	}
	foreach ( array( array( array( 'digitalisimo-network', 'digitalisimo-elements-about' ), 'digitalisimo-network', 'Rendimiento de Elements' ), array( array( 'digitalisimo-elements-about' ), 'digitalisimo-elements-about', 'Rendimiento' ) ) as $case ) {
		fs_reset( array( 'network' => true ) );
		$GLOBALS['menu'] = array_map(
			function ( $slug ) {
				return array( $slug, 'read', $slug );
			},
			$case[0]
		);
		Settings_Page::network_menu();
		$sub = $GLOBALS['fs']['submenus'][0];
		$check( $case[1] === $sub['parent'] && $case[2] === $sub['menu_title'] && 'manage_network_options' === $sub['capability'], 'Red: menú padre ' . $case[1] . ' con manage_network_options.' );
	}
	fs_reset( array( 'admin' => false ) );
	Settings_Page::init();
	$check( array() === $GLOBALS['fs']['actions'], 'Fuera de la administración la pantalla no engancha nada.' );
	fs_reset();
	Settings_Page::init();
	$hooks = array_column( $GLOBALS['fs']['actions'], 0 );
	foreach ( array( 'admin_menu', 'network_admin_menu', 'admin_post_' . Settings_Page::ACTION_SAVE, 'admin_post_' . Settings_Page::ACTION_SAVE_NETWORK, 'admin_post_' . Settings_Page::ACTION_SCAN, 'admin_post_' . Settings_Page::ACTION_APPLY, 'admin_post_' . Settings_Page::ACTION_REVERT ) as $hook ) {
		$check( in_array( $hook, $hooks, true ), "Settings_Page::init() engancha {$hook}." );
	}
	$check( array() === preg_grep( '/^admin_post_nopriv_|^wp_ajax/', $hooks ), 'Ninguna acción para visitantes sin sesión ni por AJAX.' );
	$check( false !== strpos( file_get_contents( $front . '/class-settings-page.php' ), 'wp_safe_redirect( self::url(' ) && false === strpos( file_get_contents( $front . '/class-settings-page.php' ), 'wp_get_referer' ), 'La redirección se construye con url(), nunca con el referer.' );

	// 14. F7a · «Privacidad»: page_visits_no_consent con los cuatro puntos de paridad, sin transición ni vaciado.
	$key = 'page_visits_no_consent';
	fs_reset();
	$net_priv  = $capture( array( Settings::class, 'network_privacy_fields' ) );
	$site_priv = $capture( array( Settings_Page::class, 'privacy_fields' ) );
	$check( 2 === substr_count( $net_priv, 'name="' . $network . '[' . $key . ']"' ) && false === strpos( $net_priv, 'Heredar de la red' ) && false === strpos( $net_priv, '[inherit]' ), 'Privacidad · red: oculto y casilla, sin «Heredar de la red».' );
	$check( 2 === substr_count( $site_priv, 'name="' . $site . '[' . $key . ']"' ) && 1 === substr_count( $site_priv, 'Heredar de la red' ) && false !== strpos( $site_priv, 'name="' . $site . '[inherit][' . $key . ']"' ) && false !== strpos( $site_priv, 'data-field="digitalisimo-front-page-visits-no-consent"' ), 'Privacidad · sitio de una red: la casilla y «Heredar de la red» con el script común.' );
	$check( false === strpos( $capture( array( Settings::class, 'network_fields' ) ), $key ) && false === strpos( $capture( array( Settings_Page::class, 'site_fields' ) ), $key ), 'Privacidad: la clave no se repite en las filas de rendimiento (ningún name duplicado).' );
	$check( false !== strpos( $site_priv, 'WP Consent API' ) && false !== strpos( $site_priv, 'En vigor en este sitio:' ) && false === stripos( $site_priv . $net_priv, 'elementor' ), 'Privacidad: la ayuda nombra la WP Consent API, dice el estado y no nombra el constructor original.' );
	fs_reset( array( 'network' => true ) );
	$check( false === strpos( $capture( array( Settings_Page::class, 'privacy_fields' ) ), 'Heredar de la red' ), 'Privacidad: en la red (is_network_admin) el mismo formulario no muestra la casilla.' );
	fs_reset( array( 'multisite' => false ) );
	$single_priv = $capture( array( Settings_Page::class, 'privacy_fields' ) );
	$check( false !== strpos( $single_priv, 'name="' . $site . '[' . $key . ']"' ) && false === strpos( $single_priv, 'Heredar de la red' ) && false === strpos( $single_priv, '[inherit]' ), 'Privacidad: un sitio único no muestra «Heredar de la red».' );

	fs_reset();
	$clean = Settings::sanitize( array( $key => 'yes', 'inherit' => array( $key => '0' ) ) );
	$check( true === $clean[ $key ] && array( $key ) === $clean['own'], 'Privacidad · sanitize(): booleano, y own sale del mapa inherit.' );
	$check( false === Settings::sanitize( array( $key => '' ) )[ $key ] && false === Settings::sanitize( array( $key => array() ) )[ $key ] && false === Settings::sanitize( array() )[ $key ], 'Privacidad · sanitize(): vacío, lista o ausente dan false.' );
	$saved = Settings::save_network( array( $key => '<b>1</b>', 'inherit' => array( $key => '1' ) ) );
	$check( true === $saved[ $key ] && true === $GLOBALS['fs']['site'][ $network ][ $key ] && ! isset( $saved['inherit'] ), 'Privacidad · save_network(): booleano en sitemeta, sin inherit.' );
	$check( false === Settings::save_network( array( $key => '0' ) )[ $key ], 'Privacidad · save_network(): «0» da false.' );

	fs_reset( array( 'blog' => 2 ) );
	$check( false === Settings::get( $key ) && Settings::inherits_network( $key ) && 'default' === Settings::origin( $key ), 'Privacidad: un sitio que nunca la guardó hereda y vale false (apagada por defecto).' );
	$GLOBALS['fs']['site'][ $network ] = array( $key => true );
	$check( true === Settings::get( $key ) && 'network' === Settings::origin( $key ), 'Privacidad: un valor de red recién definido rige de inmediato.' );
	update_option( $site, Settings::sanitize( array( $key => '0', 'inherit' => array( $key => '0' ) ) ) );
	$check( false === Settings::get( $key ) && ! Settings::inherits_network( $key ) && 'site' === Settings::origin( $key ), 'Privacidad: el sitio que la personaliza manda sobre la red.' );
	fs_reset( array( 'multisite' => false ) );
	update_option( $site, Settings::sanitize( array( $key => '1' ) ) );
	$GLOBALS['fs']['site'][ $network ] = array( $key => false );
	$check( true === Settings::get( $key ) && 0 === $GLOBALS['fs']['site_reads'], 'Privacidad: en un sitio único manda el sitio y no se lee sitemeta.' );

	fs_reset( array( 'blog' => 2 ) );
	$_POST = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE ), $site => array( 'pro_conditional' => '1', 'font_swap' => '1', 'inherit' => array( 'pro_conditional' => '1', 'font_swap' => '1', $key => '1' ) ) );
	Settings_Page::process_save();
	$hash   = Transition::applied()['hash'];
	$clears = fs_clears();
	$_POST  = array( '_wpnonce' => $nonce( Settings_Page::ACTION_SAVE ), $site => array( 'pro_conditional' => '1', 'font_swap' => '1', $key => '1', 'inherit' => array( 'pro_conditional' => '1', 'font_swap' => '1', $key => '0' ) ) );
	$result = Settings_Page::process_save();
	$check( 'saved' === $result['notice'] && true === Settings::get( $key ) && $hash === Transition::applied()['hash'] && $clears === fs_clears() && ! array_key_exists( $key, Transition::applied() ), 'Privacidad: guardarla no transita (misma huella, ningún vaciado y fuera de lo aplicado).' );
	$_POST = array( '_wpnonce' => $nonce( Settings_Page::ACTION_APPLY ) );
	Settings_Page::process_apply();
	$check( true === Settings::get( $key ) && ! Settings::inherits_network( $key ), 'Privacidad: «Aplicar» los iconos no pierde la clave.' );

	fs_reset();
	$page = $capture( array( Settings_Page::class, 'render' ) );
	$form = substr( $page, (int) strpos( $page, 'value="' . Settings_Page::ACTION_SAVE . '"' ) );
	$form = substr( $form, 0, (int) strpos( $form, '</form>' ) );
	$check( false !== strpos( $form, '<h2 id="digitalisimo-front-privacy">Privacidad</h2>' ) && false !== strpos( $form, 'name="' . $site . '[' . $key . ']"' ), 'Privacidad · sitio: la sección va dentro del formulario de ajustes.' );
	fs_reset( array( 'network' => true, 'caps' => array( 'manage_network_options' ) ) );
	$page = $capture( array( Settings_Page::class, 'render_network' ) );
	$check( false !== strpos( $page, '>Privacidad</h2>' ) && 2 === substr_count( $page, 'name="' . $network . '[' . $key . ']"' ) && false === strpos( $page, 'Heredar de la red' ) && 1 === substr_count( $page, '<form' ), 'Privacidad · red: la sección en el único formulario, sin «Heredar de la red».' );

	echo "F-front · ajustes: paridad sitio/red, herencia, own, capacidades, nonces, redirección, iconos, escapado, textos y la sección «Privacidad» de F7a en verde.\n";
}
