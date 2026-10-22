<?php
/**
 * F7a · arnés compartido de `tests/elements-design-css.php` y `tests/elements-design-presets.php`.
 *
 * Carga el **núcleo real** incluido (`digitalisimo-elements/elementor/`): gestor de controles, puntos de corte,
 * grupos de controles (tipografía, fondo, sombras…), widgets `common`, `common-optimized`, Encabezado, Divisor y
 * Lista de iconos (con repetidor), el contenedor, el saneador de valores por defecto, el saneador SVG y el generador
 * del CSS del documento (`Core\Files\CSS\Post`). Así las pruebas compilan grupos reales (V.2.2 y V.14 de la especificación) y no dobles.
 *
 * Sólo se simula lo que el núcleo pide a WordPress y a sus otros gestores:
 * - ganchos con prioridad (`add_action`, `apply_filters`…), opciones con su carga automática, usuarios y roles;
 * - `$wpdb` con lo que usan el cerrojo (`INSERT IGNORE`, `SELECT`, `UPDATE`, `DELETE`) y los usos de un preset;
 * - experimentos, kit activo, globales (`data_manager_v2`), documentos y `files_manager` (cuenta los vaciados).
 *
 * `design_boot()` reconstruye el núcleo (otra pila de controles y otros experimentos); `design_css()` genera el CSS
 * real de un documento con sus elementos.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ELEMENTOR_VERSION', '4.3.5' );
	define( 'ELEMENTOR_PATH', realpath( __DIR__ . '/../../digitalisimo-elements/elementor' ) . '/' );
	define( 'ELEMENTOR_URL', 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/' );
	define( 'ELEMENTOR_ASSETS_URL', ELEMENTOR_URL . 'assets/' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.164' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', realpath( __DIR__ . '/../../digitalisimo-elements' ) . '/pro-elements.php' );

	$GLOBALS['dh'] = array();
	$GLOBALS['blog_id'] = 1;

	/** Estado de WordPress en memoria. */
	function design_wp_reset(): void {
		$GLOBALS['dh'] = array(
			'hooks'    => array(),
			'did'      => array(),
			'options'  => array(),
			'user'     => 1,
			'users'    => array( 1 => array( 'roles' => array( 'administrator' ), 'caps' => array( 'manage_options', 'edit_posts', 'unfiltered_html', 'edit_theme_options' ) ) ),
			'posts'    => array(),
			'meta'     => array(),
			'wrong'    => array(),
			'scripts'  => array(),
			'inline'   => array(),
			'admin'    => false,
			'queries'  => array(),
			'shutdown' => array(),
		);
	}
	design_wp_reset();

	// Ganchos con prioridad y número de argumentos.
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['dh']['hooks'][ $hook ][ $priority ][] = array( $callback, (int) $args );
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['dh']['hooks'][ $hook ][ $priority ] ?? array() as $i => $entry ) {
			if ( $entry[0] === $callback ) {
				unset( $GLOBALS['dh']['hooks'][ $hook ][ $priority ][ $i ] );
				return true;
			}
		}
		return false;
	}
	function remove_action( $hook, $callback, $priority = 10 ) {
		return remove_filter( $hook, $callback, $priority );
	}
	function has_filter( $hook, $callback = false ) {
		foreach ( $GLOBALS['dh']['hooks'][ $hook ] ?? array() as $priority => $entries ) {
			foreach ( $entries as $entry ) {
				if ( false === $callback || $entry[0] === $callback ) {
					return false === $callback ? true : $priority;
				}
			}
		}
		return false;
	}
	function has_action( $hook, $callback = false ) {
		return has_filter( $hook, $callback );
	}
	function apply_filters( $hook, $value, ...$args ) {
		$hooks = $GLOBALS['dh']['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) {
			foreach ( $entries as $entry ) {
				$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $entry[1] ) ) );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['dh']['did'][ $hook ] = ( $GLOBALS['dh']['did'][ $hook ] ?? 0 ) + 1;
		$hooks = $GLOBALS['dh']['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) {
			foreach ( $entries as $entry ) {
				call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
			}
		}
	}
	function did_action( $hook ) {
		return $GLOBALS['dh']['did'][ $hook ] ?? 0;
	}
	/** Ganchos registrados bajo `$hook` (o todos), como `[ hook => n ]`. */
	function design_hooks( string $hook = '' ): array {
		$out = array();
		foreach ( $GLOBALS['dh']['hooks'] as $name => $priorities ) {
			$count = 0;
			foreach ( $priorities as $entries ) {
				$count += count( $entries );
			}
			if ( $count && ( '' === $hook || $hook === $name ) ) {
				$out[ $name ] = $count;
			}
		}
		return $out;
	}

	// Traducción y escape.
	function __( $text, $domain = '' ) { return $text; }
	function _x( $text, $context, $domain = '' ) { return $text; }
	function esc_html__( $text, $domain = '' ) { return $text; }
	function esc_attr__( $text, $domain = '' ) { return $text; }
	function esc_html_x( $text, $context, $domain = '' ) { return $text; }
	function _n( $one, $many, $n, $domain = '' ) { return 1 === (int) $n ? $one : $many; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return (string) $url; }
	function esc_url_raw( $url ) { return (string) $url; }
	function wp_kses_post( $text ) { return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $text ); }
	function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function wp_unslash( $value ) { return $value; }
	function wp_slash( $value ) { return $value; }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
	function wp_json_encode( $data, $flags = 0, $depth = 512 ) { return json_encode( $data, $flags, $depth ); }
	function _doing_it_wrong( $function, $message, $version ) { $GLOBALS['dh']['wrong'][] = $function . ': ' . $message; }
	function wp_die( $message = '' ) { throw new RuntimeException( 'wp_die: ' . $message ); }
	function is_rtl() { return false; }
	function is_admin() { return ! empty( $GLOBALS['dh']['admin'] ); }
	function wp_doing_ajax() { return false; }
	function wp_installing() { return false; }
	function is_multisite() { return false; }
	function get_current_blog_id() { return 1; }
	function get_locale() { return 'es_MX'; }
	function get_bloginfo( $key = '' ) { return ''; }
	function home_url( $path = '' ) { return 'https://sitio.test/' . ltrim( $path, '/' ); }
	function site_url( $path = '' ) { return home_url( $path ); }
	function admin_url( $path = '' ) { return 'https://sitio.test/wp-admin/' . ltrim( $path, '/' ); }
	function self_admin_url( $path = '' ) { return admin_url( $path ); }
	function plugins_url( $path = '', $file = '' ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function set_url_scheme( $url ) { return $url; }
	function wp_nonce_url( $url ) { return $url; }
	function wp_create_nonce( $action = -1 ) { return 'nonce'; }
	function wp_generate_password( $length = 12, $special = true ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
	function wp_upload_dir( $time = null, $create = true, $refresh = false ) { return array( 'basedir' => '/tmp/digitalisimo-uploads', 'baseurl' => 'https://sitio.test/wp-content/uploads', 'error' => false ); }
	function wp_mkdir_p( $dir ) { return true; }
	function get_plugins() { return array(); }
	function is_plugin_active( $plugin ) { return false; }
	function get_transient( $key ) { return false; }
	function set_transient( $key, $value, $expiration = 0 ) { return true; }
	function wp_cache_get( $key, $group = '' ) { return false; }
	function wp_cache_set( $key, $value, $group = '' ) { return true; }
	function wp_cache_delete( $key, $group = '' ) { return true; }
	function register_shutdown_function_design( $callback ) { $GLOBALS['dh']['shutdown'][] = $callback; }
	function get_post_type( $post = null ) { return 'page'; }
	function get_the_ID() { return 10; }
	function get_post( $post = null ) { return null; }
	function get_the_title( $post = 0 ) { return $GLOBALS['dh']['posts'][ (int) $post ]['title'] ?? ''; }
	function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['dh']['meta'][ (int) $id ][ $key ] ?? ( $single ? '' : array() ); }
	function update_post_meta( $id, $key, $value ) { $GLOBALS['dh']['meta'][ (int) $id ][ $key ] = $value; return true; }
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
	function __return_true() { return true; }
	function map_deep( $value, $callback ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				$value[ $index ] = map_deep( $item, $callback );
			}
			return $value;
		}
		return is_object( $value ) ? $value : call_user_func( $callback, $value );
	}
	function __return_false() { return false; }

	class WP_Error {
		public $errors = array();
		public $error_data = array();
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][] = $message;
				if ( '' !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}
		public function get_error_code() { $codes = array_keys( $this->errors ); return $codes ? $codes[0] : ''; }
		public function get_error_message( $code = '' ) { $code = $code ? $code : $this->get_error_code(); return $this->errors[ $code ][0] ?? ''; }
		public function get_error_data( $code = '' ) { $code = $code ? $code : $this->get_error_code(); return $this->error_data[ $code ] ?? null; }
	}

	// Scripts.
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['dh']['scripts'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args, 'enqueued' => false ); return true; }
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
		if ( $src ) {
			wp_register_script( $handle, $src, $deps, $ver, $args );
		}
		$GLOBALS['dh']['scripts'][ $handle ]['enqueued'] = true;
	}
	function wp_add_inline_script( $handle, $data, $position = 'after' ) { $GLOBALS['dh']['inline'][] = array( $handle, $data, $position ); return true; }
	function wp_localize_script( $handle, $name, $data ) { $GLOBALS['dh']['inline'][] = array( $handle, 'var ' . $name . ' = ' . json_encode( $data ) . ';', 'before' ); return true; }

	// Opciones, con su carga automática.
	function get_option( $name, $default = false ) {
		$GLOBALS['dh']['queries'][] = 'get_option:' . $name;
		return isset( $GLOBALS['dh']['options'][ $name ] ) ? $GLOBALS['dh']['options'][ $name ]['value'] : $default;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
		if ( isset( $GLOBALS['dh']['options'][ $name ] ) ) {
			return false;
		}
		$GLOBALS['dh']['options'][ $name ] = array( 'value' => $value, 'autoload' => false === $autoload || 'off' === $autoload || 'no' === $autoload ? 'off' : 'on' );
		return true;
	}
	function update_option( $name, $value, $autoload = null ) {
		if ( ! isset( $GLOBALS['dh']['options'][ $name ] ) ) {
			return add_option( $name, $value, '', $autoload );
		}
		$GLOBALS['dh']['options'][ $name ]['value'] = $value;
		if ( null !== $autoload ) {
			$GLOBALS['dh']['options'][ $name ]['autoload'] = false === $autoload || 'off' === $autoload || 'no' === $autoload ? 'off' : 'on';
		}
		return true;
	}
	function delete_option( $name ) { unset( $GLOBALS['dh']['options'][ $name ] ); return true; }
	function wp_load_alloptions() {
		$out = array();
		foreach ( $GLOBALS['dh']['options'] as $name => $row ) {
			if ( 'on' === $row['autoload'] ) {
				$out[ $name ] = $row['value'];
			}
		}
		return $out;
	}

	// Usuarios y roles.
	function get_current_user_id() { return (int) $GLOBALS['dh']['user']; }
	function is_user_logged_in() { return 0 < (int) $GLOBALS['dh']['user']; }
	function get_userdata( $id ) {
		$user = $GLOBALS['dh']['users'][ (int) $id ] ?? null;
		return $user ? (object) array( 'ID' => (int) $id, 'roles' => $user['roles'] ) : false;
	}
	function wp_get_current_user() {
		$user = get_userdata( get_current_user_id() );
		return $user ? $user : (object) array( 'ID' => 0, 'roles' => array() );
	}
	function user_can( $user, $cap ) {
		$id = is_object( $user ) ? (int) $user->ID : (int) $user;
		return in_array( $cap, $GLOBALS['dh']['users'][ $id ]['caps'] ?? array(), true );
	}
	function current_user_can( $cap ) { return user_can( get_current_user_id(), $cap ); }
	function is_super_admin( $user = false ) { return false; }
	function get_user_meta( $user, $key = '', $single = false ) { return $single ? '' : array(); }
	function update_user_meta( $user, $key, $value ) { return true; }

	/**
	 * `$wpdb` en memoria: `prepare()` guarda la plantilla y sus argumentos, y las consultas se resuelven sobre las
	 * opciones, las entradas y las metas de `$GLOBALS['dh']`. Reconoce las del cerrojo compartido y la de los usos.
	 */
	class Design_WPDB {
		public $options  = 'wp_options';
		public $posts    = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public $prefix   = 'wp_';
		public $prepared = array();
		public $log      = array();
		public function prepare( $query, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}
			$this->prepared[] = array( $query, $args );
			return '#prepared:' . ( count( $this->prepared ) - 1 );
		}
		public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }
		private function resolve( $sql ) {
			if ( is_string( $sql ) && 0 === strpos( $sql, '#prepared:' ) ) {
				return $this->prepared[ (int) substr( $sql, 10 ) ];
			}
			return array( (string) $sql, array() );
		}
		private static function like( string $pattern, string $value ): bool {
			$regex = '';
			$length = strlen( $pattern );
			for ( $i = 0; $i < $length; $i++ ) {
				$char = $pattern[ $i ];
				if ( '\\' === $char && $i + 1 < $length ) {
					$regex .= preg_quote( $pattern[ ++$i ], '/' );
				} elseif ( '%' === $char ) {
					$regex .= '.*';
				} elseif ( '_' === $char ) {
					$regex .= '.';
				} else {
					$regex .= preg_quote( $char, '/' );
				}
			}
			return 1 === preg_match( '/^' . $regex . '$/s', $value );
		}
		public function query( $sql ) {
			list( $query, $args ) = $this->resolve( $sql );
			$this->log[] = array( $query, $args );
			$options = &$GLOBALS['dh']['options'];
			if ( 0 === strpos( $query, 'INSERT IGNORE INTO' ) ) {
				if ( isset( $options[ $args[0] ] ) ) {
					return 0;
				}
				$options[ $args[0] ] = array( 'value' => $args[1], 'autoload' => 'off' );
				return 1;
			}
			if ( 0 === strpos( $query, 'UPDATE' ) ) {
				if ( isset( $options[ $args[1] ] ) && $options[ $args[1] ]['value'] === $args[2] ) {
					$options[ $args[1] ]['value'] = $args[0];
					return 1;
				}
				return 0;
			}
			if ( 0 === strpos( $query, 'DELETE' ) ) {
				if ( isset( $options[ $args[0] ] ) && self::like( $args[1], (string) $options[ $args[0] ]['value'] ) ) {
					unset( $options[ $args[0] ] );
					return 1;
				}
				return 0;
			}
			return 0;
		}
		public function get_var( $sql ) {
			list( $query, $args ) = $this->resolve( $sql );
			$this->log[] = array( $query, $args );
			if ( false !== strpos( $query, 'SELECT option_value' ) ) {
				return isset( $GLOBALS['dh']['options'][ $args[0] ] ) ? (string) $GLOBALS['dh']['options'][ $args[0] ]['value'] : null;
			}
			return null;
		}
		/** Usos de un preset: `meta_key`, `LIKE`, tipos y estados excluidos y tope, en el orden en que los pasa `Store`. */
		public function get_col( $sql ) {
			list( $query, $args ) = $this->resolve( $sql );
			$this->log[] = array( $query, $args );
			if ( false === strpos( $query, 'meta_value LIKE' ) ) {
				return array();
			}
			$key      = (string) array_shift( $args );
			$pattern  = (string) array_shift( $args );
			$limit    = (int) array_pop( $args );
			$excluded = $args;
			$ids = array();
			foreach ( $GLOBALS['dh']['meta'] as $id => $meta ) {
				$post = $GLOBALS['dh']['posts'][ $id ] ?? null;
				if ( ! $post || ! isset( $meta[ $key ] ) || in_array( $post['type'], $excluded, true ) || in_array( $post['status'], $excluded, true ) ) {
					continue;
				}
				if ( self::like( $pattern, (string) $meta[ $key ] ) ) {
					$ids[] = (string) $id;
				}
			}
			sort( $ids );
			return array_slice( $ids, 0, $limit );
		}
	}
	$GLOBALS['wpdb'] = new Design_WPDB();

	function check_design( $ok, string $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}
}

namespace Elementor {
	/** El `Plugin` real no se carga: sus gestores se arman a mano en `design_boot()`. */
	class Plugin {
		public static $instance;
	}
}

namespace {
	require ELEMENTOR_PATH . 'includes/autoloader.php';
	require ELEMENTOR_PATH . 'includes/managers/controls.php';
	\Elementor\Autoloader::run();
	foreach ( array( 'includes/base/controls-stack.php', 'includes/base/sub-controls-stack.php', 'includes/base/element-base.php', 'includes/base/widget-base.php', 'includes/elements/repeater.php', 'includes/widgets/common-base.php', 'includes/widgets/common.php', 'includes/widgets/common-optimized.php', 'includes/widgets/heading.php', 'includes/widgets/divider.php', 'includes/widgets/icon-list.php', 'includes/elements/container.php' ) as $file ) {
		require_once ELEMENTOR_PATH . $file;
	}

	final class Design_Experiments {
		public $active = array( 'additional_custom_breakpoints' => true, 'container' => true, 'e_optimized_markup' => false );
		public function is_feature_active( $feature, $check_dependencies = false ) { return ! empty( $this->active[ $feature ] ); }
	}
	final class Design_Widgets {
		public $types = array();
		public function get_widget_types( $name = null ) { return null === $name ? $this->types : ( $this->types[ $name ] ?? null ); }
	}
	final class Design_Elements extends \Elementor\Elements_Manager {
		public function __construct() {}
		public function get_element_types( $name = null ) {
			$types = array( 'container' => true, 'section' => true, 'column' => true );
			return null === $name ? $types : ( $types[ $name ] ?? null );
		}
		public function create_element_instance( array $data, array $args = array(), ?\Elementor\Element_Base $element_type = null ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( 'widget' === ( $data['elType'] ?? '' ) ) {
				$type = $plugin->widgets_manager->get_widget_types( $data['widgetType'] ?? '' );
				if ( ! $type ) {
					return null;
				}
				$class = get_class( $type );
				return new $class( $data, $type->get_default_args() );
			}
			if ( 'container' === ( $data['elType'] ?? '' ) ) {
				return new \Elementor\Includes\Elements\Container( $data, array() );
			}
			return null;
		}
	}
	final class Design_Kit {
		public function get_meta( $key ) { return array(); }
		public function get_id() { return 5; }
		public function get_main_id() { return 5; }
		public function get_settings_for_display( $key = null ) { return null; }
	}
	final class Design_Kits {
		public function get_active_id() { return 5; }
		public function get_active_kit_for_frontend() { return new Design_Kit(); }
		public function get_active_kit() { return new Design_Kit(); }
		public function is_custom_colors_enabled() { return true; }
		public function is_custom_typography_enabled() { return true; }
		public function get_current_settings( $key = null ) { return null; }
	}
	final class Design_Document {
		public $id;
		public $data;
		public function __construct( int $id, array $data ) { $this->id = $id; $this->data = $data; }
		public function get_elements_data() { return $this->data; }
		public function get_main_id() { return $this->id; }
		public function get_id() { return $this->id; }
	}
	final class Design_Documents {
		public $docs = array();
		public function get( $id, $from_cache = true ) { return $this->docs[ (int) $id ] ?? null; }
	}
	final class Design_Files {
		public $cleared = 0;
		public function clear_cache() { ++$this->cleared; }
	}
	final class Design_Flag {
		public $on = false;
		public function is_preview_mode( $id = 0 ) { return $this->on; }
		public function is_edit_mode( $id = null ) { return $this->on; }
	}
	/** Globales del kit que resuelve `Core\Files\CSS\Base::get_selector_global_value()`. */
	final class Design_Globals {
		public function run( $key ) {
			$globals = array(
				'globals/colors?id=primary'      => array( 'value' => '#6EC1E4' ),
				'globals/colors?id=accent'       => array( 'value' => '#61CE70' ),
				'globals/colors?id=a1b2c3d'      => array( 'value' => '#123456' ),
				'globals/typography?id=primary'  => array( 'value' => array( 'typography_typography' => 'custom', 'typography_font_family' => 'Roboto', 'typography_font_weight' => '600' ) ),
				'globals/typography?id=accent'   => array( 'value' => array( 'typography_typography' => 'custom', 'typography_font_family' => 'Roboto', 'typography_font_weight' => '500' ) ),
			);
			return $globals[ $key ] ?? array();
		}
	}

	/**
	 * Núcleo nuevo: gestor de controles (pilas vacías), puntos de corte y los widgets registrados.
	 *
	 * @param array $options `optimized` (marcado optimizado), `admin`, `preview`.
	 */
	function design_boot( array $options = array() ): void {
		$plugin = new stdClass();
		\Elementor\Plugin::$instance = $plugin;
		$plugin->experiments = new Design_Experiments();
		$plugin->experiments->active['e_optimized_markup'] = ! empty( $options['optimized'] );
		$plugin->kits_manager     = new Design_Kits();
		$plugin->breakpoints      = new \Elementor\Core\Breakpoints\Manager();
		$plugin->widgets_manager  = new Design_Widgets();
		$plugin->elements_manager = new Design_Elements();
		$plugin->preview          = new Design_Flag();
		$plugin->preview->on      = ! empty( $options['preview'] );
		$plugin->editor           = new Design_Flag();
		$plugin->documents        = new Design_Documents();
		$plugin->files_manager    = new Design_Files();
		$plugin->data_manager_v2  = new Design_Globals();
		$plugin->skins_manager    = new class() {
			public function get_skins( $widget ) { return array(); }
		};
		$plugin->controls_manager = new \Elementor\Controls_Manager();
		$GLOBALS['dh']['admin']   = ! empty( $options['admin'] );
		foreach ( array( 'common' => 'Widget_Common', 'common-optimized' => 'Widget_Common_Optimized', 'heading' => 'Widget_Heading', 'divider' => 'Widget_Divider', 'icon-list' => 'Widget_Icon_List' ) as $name => $class ) {
			$class = '\\Elementor\\' . $class;
			$plugin->widgets_manager->types[ $name ] = new $class( array(), null );
		}
	}

	/**
	 * Fija el contexto de `Performance::should_optimize_controls()`, que el núcleo calcula una vez por petición: `true`
	 * = front o WP-CLI (pilas optimizadas, sin `options` ni `size_units`, y controles de estilo aparte); `false` =
	 * administración, vista previa o REST. Hay que llamar después a `design_boot()` para rehacer las pilas.
	 */
	function design_frontend( bool $front ): void {
		$property = new ReflectionProperty( \Elementor\Core\Frontend\Performance::class, 'is_frontend' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, $front );
	}

	/** Instancia real de un elemento a partir de sus datos. */
	function design_element( array $data ) {
		$data += array( 'id' => 'e' . substr( md5( json_encode( $data ) ), 0, 6 ), 'settings' => array(), 'elements' => array() );
		return \Elementor\Plugin::$instance->elements_manager->create_element_instance( $data );
	}

	/**
	 * CSS real del documento `$post_id` con `$elements`: `Core\Files\CSS\Post::parse_content()`, que dispara
	 * `elementor/element/before_parse_css` y `elementor/element/parse_css` en cada elemento.
	 */
	function design_css( array $elements, int $post_id = 10 ): string {
		\Elementor\Plugin::$instance->documents->docs[ $post_id ] = new Design_Document( $post_id, $elements );
		$css    = new \Elementor\Core\Files\CSS\Post( $post_id );
		$method = new ReflectionMethod( $css, 'parse_content' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return (string) $method->invoke( $css );
	}

	/** Reglas de un CSS como `[ "@media…|selector" => "declaraciones" ]`, para comparar sin depender del orden. */
	function design_rules( string $css ): array {
		$out = array();
		$media = '';
		$depth = 0;
		$offset = 0;
		$length = strlen( $css );
		while ( $offset < $length ) {
			$open = strpos( $css, '{', $offset );
			$close = strpos( $css, '}', $offset );
			if ( false === $close ) {
				break;
			}
			if ( false !== $open && $open < $close ) {
				$head = trim( substr( $css, $offset, $open - $offset ) );
				if ( 0 === strpos( $head, '@media' ) ) {
					$media = $head;
					$offset = $open + 1;
					continue;
				}
				$body = substr( $css, $open + 1, $close - $open - 1 );
				$out[ ( '' !== $media ? $media . '|' : '' ) . $head ] = trim( $body );
				$offset = $close + 1;
				continue;
			}
			$media = '';
			$offset = $close + 1;
		}
		return $out;
	}
}
