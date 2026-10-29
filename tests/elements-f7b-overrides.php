<?php
/**
 * F7b · frente E: sobrescrituras de contenido en el widget Plantilla (V.2, C1-1, C1-3, C1-7, C1-24, C2-18, C2-31).
 *
 * Con el **núcleo real** incluido (`digitalisimo-elements/elementor/`): `Controls_Stack`, `Element_Base`,
 * `Widget_Base`, `Document::print_elements()` (caché de elementos por documento, `document.php:1834-1880`),
 * `Frontend::get_builder_content()` (`builder_content_data`, `frontend.php:1134-1251`), el shortcode de la caché de
 * elementos (`modules/element-cache/module.php`) y los widgets Encabezado, Editor de texto, Botón y el Plantilla de PRO
 * (`modules/library/widgets/template.php`). Lo de WordPress, los gestores y las piezas del frente A (`F7b\Module`,
 * `F7b\Flags`, `F7b\Usages`) son dobles mínimos con la misma firma.
 *
 * Comprueba: clave y validación; filas con forma; sustitución por cada `kind` en los siete tipos; `hide`; filtros
 * retirados al terminar; caché apagada sólo para esa plantilla y sólo durante esa instancia (la meta
 * `_elementor_element_cache` de la plantilla ni se lee ni se escribe); plantilla sin sobrescrituras byte a byte igual;
 * dos instancias de la misma plantilla en una página con caché; Plantilla dentro de otro Plantilla; Markdown; controles
 * sin `default` y con `style_transfer => false`; acciones AJAX con un suscriptor y un autor ajeno; arranque con
 * `Flags::active()` falso sin ningún gancho.
 *
 * Uso: node …/php-wasm-cli tests/elements-f7b-overrides.php (sale con 0 aunque haya un Fatal: mirar la salida).
 */
namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ELEMENTOR_VERSION', '4.3.5' );
	define( 'ELEMENTOR_PATH', realpath( __DIR__ . '/../digitalisimo-elements/elementor' ) . '/' );
	define( 'ELEMENTOR_URL', 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/' );
	define( 'ELEMENTOR_ASSETS_URL', ELEMENTOR_URL . 'assets/' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.169' );
	define( 'F7B_ROOT', realpath( __DIR__ . '/../digitalisimo-elements' ) );

	$GLOBALS['ov'] = array();

	function ov_reset(): void {
		$GLOBALS['ov'] = array(
			'hooks'      => array(),
			'did'        => array(),
			'options'    => array(),
			'option_log' => array(),
			'posts'      => array(),
			'meta'       => array(),
			'meta_log'   => array(),
			'user'       => 1,
			'users'      => array(),
			'scripts'    => array(),
			'inline'     => array(),
			'shortcodes' => array(),
			'admin'      => false,
			'wrong'      => array(),
		);
	}
	ov_reset();

	// Ganchos con prioridad; quitar un gancho mientras corre no altera la vuelta en curso (como `WP_Hook`).
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['ov']['added'][] = $hook;
		$GLOBALS['ov']['hooks'][ $hook ][ $priority ][] = array( $callback, (int) $args );
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['ov']['hooks'][ $hook ][ $priority ] ?? array() as $i => $entry ) {
			if ( $entry[0] === $callback ) {
				unset( $GLOBALS['ov']['hooks'][ $hook ][ $priority ][ $i ] );
				if ( ! $GLOBALS['ov']['hooks'][ $hook ][ $priority ] ) {
					unset( $GLOBALS['ov']['hooks'][ $hook ][ $priority ] );
				}
				if ( ! $GLOBALS['ov']['hooks'][ $hook ] ) {
					unset( $GLOBALS['ov']['hooks'][ $hook ] );
				}
				return true;
			}
		}
		return false;
	}
	function remove_action( $hook, $callback, $priority = 10 ) {
		return remove_filter( $hook, $callback, $priority );
	}
	function has_filter( $hook, $callback = false ) {
		foreach ( $GLOBALS['ov']['hooks'][ $hook ] ?? array() as $priority => $entries ) {
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
		$hooks = $GLOBALS['ov']['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) {
			foreach ( $entries as $entry ) {
				$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $entry[1] ) ) );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['ov']['did'][ $hook ] = ( $GLOBALS['ov']['did'][ $hook ] ?? 0 ) + 1;
		$hooks = $GLOBALS['ov']['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) {
			foreach ( $entries as $entry ) {
				call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
			}
		}
	}
	function did_action( $hook ) {
		return $GLOBALS['ov']['did'][ $hook ] ?? 0;
	}
	/** Ganchos registrados, como `[ hook => n ]`. */
	function ov_hooks(): array {
		$out = array();
		foreach ( $GLOBALS['ov']['hooks'] as $name => $priorities ) {
			$count = 0;
			foreach ( $priorities as $entries ) {
				$count += count( $entries );
			}
			if ( $count ) {
				$out[ $name ] = $count;
			}
		}
		return $out;
	}

	// Traducción y escape.
	function __( $text, $domain = '' ) { return $text; }
	function _x( $text, $context, $domain = '' ) { return $text; }
	function esc_html__( $text, $domain = '' ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $text, $domain = '' ) { return $text; }
	function esc_html_e( $text, $domain = '' ) { echo esc_html__( $text, $domain ); }
	function esc_html_x( $text, $context, $domain = '' ) { return $text; }
	function _n( $one, $many, $n, $domain = '' ) { return 1 === (int) $n ? $one : $many; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_textarea( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return 0 === stripos( trim( (string) $url ), 'javascript:' ) ? '' : (string) $url; }
	function esc_url_raw( $url ) { return esc_url( $url ); }
	function wp_kses_post( $text ) { return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $text ); }
	function wp_allowed_protocols() { return array( 'http', 'https', 'mailto', 'tel' ); }
	function wp_strip_all_tags( $text ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text ) ) ); }
	function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class ); }
	function wp_unslash( $value ) { return $value; }
	function wp_slash( $value ) { return $value; }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
	function wp_json_encode( $data, $flags = 0, $depth = 512 ) { return json_encode( $data, $flags, $depth ); }
	function _doing_it_wrong( $function, $message, $version ) { $GLOBALS['ov']['wrong'][] = $function . ': ' . $message; }
	function _deprecated_function( $function, $version, $replacement = '' ) {}
	function wp_die( $message = '' ) { throw new RuntimeException( 'wp_die: ' . $message ); }
	function is_rtl() { return false; }
	function is_admin() { return ! empty( $GLOBALS['ov']['admin'] ); }
	function wp_doing_ajax() { return false; }
	function is_customize_preview() { return false; }
	function is_singular( $types = '' ) { return true; }
	function wp_installing() { return false; }
	function is_multisite() { return false; }
	function get_current_blog_id() { return 1; }
	function get_locale() { return 'es_MX'; }
	function get_bloginfo( $key = '' ) { return ''; }
	function home_url( $path = '' ) { return 'https://sitio.test/' . ltrim( $path, '/' ); }
	function site_url( $path = '' ) { return home_url( $path ); }
	function admin_url( $path = '' ) { return 'https://sitio.test/wp-admin/' . ltrim( $path, '/' ); }
	function plugins_url( $path = '', $file = '' ) {
		$base = $file ? str_replace( F7B_ROOT, '', dirname( $file ) ) : '';
		return 'https://sitio.test/wp-content/plugins/digitalisimo-elements' . $base . '/' . ltrim( $path, '/' );
	}
	function set_url_scheme( $url ) { return $url; }
	function wp_create_nonce( $action = -1 ) { return 'nonce'; }
	function wp_generate_password( $length = 12, $special = true ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
	function wp_is_post_revision( $post ) { return false; }
	function wp_is_post_autosave( $post ) { return false; }
	function post_password_required( $post = null ) { return false; }
	function current_time( $type ) { return 1700000000; }
	function has_blocks( $content = null ) { return false; }
	function do_blocks( $content ) { return $content; }
	function wpautop( $text ) { $text = trim( (string) $text ); return '' === $text ? '' : ( 0 === strpos( $text, '<p' ) ? $text : '<p>' . $text . '</p>' ); }
	function shortcode_unautop( $text ) { return $text; }
	function wptexturize( $text ) { return $text; }
	function convert_smilies( $text ) { return $text; }
	function get_extended( $content ) { return array( 'main' => $content, 'extended' => '', 'more_text' => '' ); }
	function wp_cache_get( $key, $group = '' ) { return false; }
	function wp_cache_set( $key, $value, $group = '' ) { return true; }
	function get_transient( $key ) { return false; }
	function set_transient( $key, $value, $expiration = 0 ) { return true; }
	function is_wp_error( $thing ) { return false; }
	function __return_true() { return true; }
	function __return_false() { return false; }
	function map_deep( $value, $callback ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				$value[ $index ] = map_deep( $item, $callback );
			}
			return $value;
		}
		return is_object( $value ) ? $value : call_user_func( $callback, $value );
	}

	// Shortcodes (sólo el de la caché de elementos).
	function add_shortcode( $tag, $callback ) { $GLOBALS['ov']['shortcodes'][ $tag ] = $callback; }
	function do_shortcode( $content, $ignore_html = false ) {
		return preg_replace_callback(
			'/\[([a-z-]+)((?:\s+[a-z]+="[^"]*")*)\]/',
			function ( $m ) {
				if ( ! isset( $GLOBALS['ov']['shortcodes'][ $m[1] ] ) ) {
					return $m[0];
				}
				preg_match_all( '/([a-z]+)="([^"]*)"/', $m[2], $pairs, PREG_SET_ORDER );
				$atts = array();
				foreach ( $pairs as $pair ) {
					$atts[ $pair[1] ] = html_entity_decode( $pair[2], ENT_QUOTES );
				}
				return call_user_func( $GLOBALS['ov']['shortcodes'][ $m[1] ], $atts );
			},
			(string) $content
		);
	}

	// Scripts.
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
		$GLOBALS['ov']['scripts'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args );
	}
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) { $GLOBALS['ov']['styles'][ $handle ] = true; }
	function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) { return true; }
	function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) { return true; }
	function wp_style_is( $handle, $list = 'enqueued' ) { return false; }
	function wp_script_is( $handle, $list = 'enqueued' ) { return false; }
	function wp_add_inline_script( $handle, $data, $position = 'after' ) { $GLOBALS['ov']['inline'][] = array( $handle, $data, $position ); return true; }

	// Opciones, con `pre_option_{$name}` como WordPress (`wp-includes/option.php`).
	function get_option( $name, $default = false ) {
		$pre = apply_filters( "pre_option_{$name}", false, $name, $default );
		if ( false !== $pre ) {
			$GLOBALS['ov']['option_log'][] = 'pre:' . $name . '=' . $pre;
			return $pre;
		}
		$GLOBALS['ov']['option_log'][] = 'db:' . $name;
		return array_key_exists( $name, $GLOBALS['ov']['options'] ) ? $GLOBALS['ov']['options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['ov']['options'][ $name ] = $value; return true; }
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) { $GLOBALS['ov']['options'][ $name ] = $value; return true; }
	function delete_option( $name ) { unset( $GLOBALS['ov']['options'][ $name ] ); return true; }

	// Entradas y metas, con registro de lecturas y escrituras.
	class WP_Post {
		public $ID = 0;
		public $post_type = 'post';
		public $post_status = 'publish';
		public $post_parent = 0;
		public $post_title = '';
		public $post_author = 1;
		public function __construct( $data = null ) {
			foreach ( (array) $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
	function get_post( $post = null ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		$row = $GLOBALS['ov']['posts'][ $id ] ?? null;
		return $row ? new WP_Post( $row ) : null;
	}
	function get_post_status( $post = null ) { $p = get_post( $post ); return $p ? $p->post_status : false; }
	function get_post_type( $post = null ) { $p = get_post( $post ); return $p ? $p->post_type : false; }
	function get_post_type_object( $type ) { return (object) array( 'cap' => (object) array( 'publish_posts' => 'publish_posts' ) ); }
	function get_the_ID() { return (int) ( $GLOBALS['ov']['the_id'] ?? 0 ); }
	function get_post_meta( $id, $key = '', $single = false ) {
		$GLOBALS['ov']['meta_log'][] = 'get:' . (int) $id . ':' . $key;
		$value = $GLOBALS['ov']['meta'][ (int) $id ][ $key ] ?? null;
		return null === $value ? ( $single ? '' : array() ) : $value;
	}
	function update_post_meta( $id, $key, $value, $prev = '' ) {
		$GLOBALS['ov']['meta_log'][] = 'set:' . (int) $id . ':' . $key;
		$GLOBALS['ov']['meta'][ (int) $id ][ $key ] = $value;
		return true;
	}
	function delete_post_meta( $id, $key, $value = '' ) {
		$GLOBALS['ov']['meta_log'][] = 'del:' . (int) $id . ':' . $key;
		unset( $GLOBALS['ov']['meta'][ (int) $id ][ $key ] );
		return true;
	}
	function update_metadata( $type, $id, $key, $value, $prev = '' ) { return update_post_meta( $id, $key, $value ); }
	function delete_metadata( $type, $id, $key, $value = '', $all = false ) { return delete_post_meta( $id, $key ); }
	/** Lecturas o escrituras de `$key` en `$id` desde el último `ov_log_reset()`. */
	function ov_meta_touches( int $id, string $key ): array {
		return array_values( array_filter( $GLOBALS['ov']['meta_log'], function ( $entry ) use ( $id, $key ) {
			return ':' . $id . ':' . $key === substr( $entry, strpos( $entry, ':' ) );
		} ) );
	}
	function ov_log_reset(): void {
		$GLOBALS['ov']['meta_log']   = array();
		$GLOBALS['ov']['option_log'] = array();
	}

	// Usuarios: `edit_post` del autor de la entrada o de quien edita las de otros.
	function get_current_user_id() { return (int) $GLOBALS['ov']['user']; }
	function get_user_meta( $user, $key = '', $single = false ) { return $single ? '' : array(); }
	function update_user_meta( $user, $key, $value ) { return true; }
	function is_user_logged_in() { return 0 < (int) $GLOBALS['ov']['user']; }
	function current_user_can( $cap, ...$args ) {
		$user = $GLOBALS['ov']['users'][ get_current_user_id() ] ?? array( 'caps' => array() );
		if ( 'edit_post' === $cap ) {
			$post = get_post( $args[0] ?? 0 );
			if ( ! $post ) {
				return false;
			}
			if ( in_array( 'edit_others_posts', $user['caps'], true ) ) {
				return true;
			}
			return in_array( 'edit_posts', $user['caps'], true ) && (int) $post->post_author === get_current_user_id();
		}
		return in_array( $cap, $user['caps'], true );
	}

	final class OV_Failure extends RuntimeException {}
	$GLOBALS['ov_checks'] = 0;
	function check_ov( $ok, string $message ) {
		if ( ! $ok ) {
			throw new OV_Failure( $message );
		}
		++$GLOBALS['ov_checks'];
	}
}

namespace Elementor {
	/** El `Plugin` real no se carga: sus gestores se arman a mano en `ov_boot()`. */
	class Plugin {
		public static $instance;
		public static function instance() { return self::$instance; }
	}
}

namespace ElementorPro {
	/** Lo único que el Plantilla pide a PRO. */
	class Plugin {
		public static function elementor() { return \Elementor\Plugin::$instance; }
	}
}

namespace ElementorPro\License {
	class API {
		public static function is_license_active() { return true; }
	}
}

namespace ElementorPro\Modules\QueryControl {
	/** Constantes del control de consulta; el tipo de control se registra como texto en `ov_boot()`. */
	class Module {
		const QUERY_CONTROL_ID = 'query';
		const QUERY_OBJECT_LIBRARY_TEMPLATE = 'library_template';
	}
}

namespace Digitalisimo\Elements\F7b {
	/** Doble del frente A: `requires()`, `editing()`. */
	final class Module {
		public static $editing = false;
		public static $missing = array();
		public static $fail = false;
		public static $asked = array();
		public static function requires( array $symbols, string $who ): bool {
			self::$asked = $symbols;
			if ( self::$fail ) {
				self::$missing[ $who ] = $symbols;
				return false;
			}
			foreach ( $symbols as $symbol ) {
				if ( ! class_exists( $symbol ) ) {
					self::$missing[ $who ][] = $symbol;
					return false;
				}
			}
			return true;
		}
		public static function editing(): bool { return self::$editing; }
	}
	/** Doble del frente A: `active()` = `has() || Module::editing()`. */
	final class Flags {
		public static $has = array();
		public static function has( string $key ): bool { return ! empty( self::$has[ $key ] ); }
		public static function active( string $key ): bool { return self::has( $key ) || Module::editing(); }
	}
	/** Doble del frente A: `Usages::of()` con la forma del contrato. */
	final class Usages {
		const META = '_digitalisimo_uses';
		const KIND_TEMPLATE = 't';
		const KIND_QUERY = 'q';
		public static $map = array();
		public static $calls = array();
		public static function of( int $id, string $kind ): array {
			self::$calls[] = array( $id, $kind );
			return self::$map[ $kind . ':' . $id ] ?? array( 'count' => 0, 'posts' => array(), 'drafts' => array() );
		}
	}
}

namespace {
	require ELEMENTOR_PATH . 'includes/autoloader.php';
	require ELEMENTOR_PATH . 'includes/managers/controls.php';
	\Elementor\Autoloader::run();
	foreach ( array( 'includes/base/controls-stack.php', 'includes/base/sub-controls-stack.php', 'includes/base/element-base.php', 'includes/base/widget-base.php', 'includes/elements/repeater.php', 'includes/widgets/common-base.php', 'includes/widgets/common.php', 'includes/widgets/common-optimized.php', 'includes/widgets/heading.php', 'includes/widgets/text-editor.php', 'includes/widgets/button.php', 'includes/elements/container.php', 'core/base/document.php', 'includes/frontend.php' ) as $file ) {
		require_once ELEMENTOR_PATH . $file;
	}
	require_once F7B_ROOT . '/base/base-widget-trait.php';
	require_once F7B_ROOT . '/base/on-import-trait.php';
	require_once F7B_ROOT . '/base/base-widget.php';
	require_once F7B_ROOT . '/modules/library/widgets/template.php';
	foreach ( array( 'apply', 'render', 'controls', 'ajax', 'module' ) as $file ) {
		require_once F7B_ROOT . '/modules/digitalisimo-f7b/overrides/class-' . $file . '.php';
	}

	use Digitalisimo\Elements\F7b\Overrides\Apply;
	use Digitalisimo\Elements\F7b\Overrides\Render;

	/** Un documento real del núcleo sin controles de página (no hacen falta para pintar). */
	class OV_Doc extends \Elementor\Core\Base\Document {
		public static function get_type() { return 'page'; }
		public static function get_title() { return 'Documento'; }
		protected function register_controls() {}
		public function get_css_wrapper_selector() { return '.elementor-' . $this->get_main_id(); }
	}

	final class OV_Experiments {
		public function is_feature_active( $feature, $check_dependencies = false ) { return in_array( $feature, array( 'container', 'additional_custom_breakpoints' ), true ); }
	}
	final class OV_Widgets {
		public $types = array();
		public function get_widget_types( $name = null ) { return null === $name ? $this->types : ( $this->types[ $name ] ?? null ); }
	}
	final class OV_Elements extends \Elementor\Elements_Manager {
		public function __construct() {}
		public function get_element_types( $name = null ) {
			$types = array( 'container' => true );
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
	/** Gestor de documentos: `switch_to_document()`/`restore_document()` copiados del núcleo (`documents-manager.php:657-703`). */
	final class OV_Documents {
		public $docs = array();
		private $current_doc;
		private $switched_data = array();
		public function get( $id, $from_cache = true ) {
			$id = (int) $id;
			if ( ! isset( $this->docs[ $id ] ) && get_post( $id ) ) {
				$this->docs[ $id ] = new OV_Doc( array( 'post_id' => $id ) );
			}
			return $this->docs[ $id ] ?? null;
		}
		public function get_doc_for_frontend( $id ) { return $this->get( $id ); }
		public function get_doc_or_auto_save( $id, $user_id = 0 ) { return $this->get( $id ); }
		public function switch_to_document( $document ) {
			if ( $this->current_doc === $document ) {
				$this->switched_data[] = false;
				return;
			}
			$this->switched_data[] = array( 'switched_doc' => $document, 'original_doc' => $this->current_doc );
			$this->current_doc = $document;
		}
		public function restore_document() {
			$data = array_pop( $this->switched_data );
			if ( ! $data ) {
				return;
			}
			$this->current_doc = $data['original_doc'];
		}
		public function get_current() { return $this->current_doc; }
		public function get_document_types( $args = array(), $operator = 'and' ) { return array( 'section' => 'OV_Doc', 'container' => 'OV_Doc' ); }
		public function check_permissions( $id ) {}
	}
	final class OV_Flag {
		public $on = false;
		public function is_preview_mode( $id = 0 ) { return false; }
		public function is_preview() { return false; }
		public function is_edit_mode( $id = null ) { return $this->on; }
		public function set_edit_mode( $on ) { $this->on = (bool) $on; }
	}
	final class OV_Dynamic_Tags {
		public function tag_text_to_tag_data( $text ) { return array( 'name' => 'post-title', 'id' => 'x', 'settings' => array() ); }
		public function get_tag_info( $name ) { return array( 'name' => $name ); }
		public function parse_tags( $settings, $controls ) { return $settings; }
	}

	/** Núcleo nuevo: controles, widgets, documentos, frontend real sin constructor y el shortcode de la caché. */
	function ov_boot(): void {
		$plugin = new stdClass();
		\Elementor\Plugin::$instance = $plugin;
		$plugin->experiments      = new OV_Experiments();
		$plugin->breakpoints      = new \Elementor\Core\Breakpoints\Manager();
		$plugin->widgets_manager  = new OV_Widgets();
		$plugin->elements_manager = new OV_Elements();
		$plugin->documents        = new OV_Documents();
		$plugin->preview          = new OV_Flag();
		$plugin->editor           = new OV_Flag();
		$plugin->dynamic_tags     = new OV_Dynamic_Tags();
		$plugin->skins_manager    = new class() {
			public function get_skins( $widget ) { return array(); }
		};
		$plugin->kits_manager = new class() {
			public function get_active_id() { return 5; }
			public function get_current_settings( $key = null ) { return null; }
			public function get_active_kit() { return $this->get_active_kit_for_frontend(); }
			public function get_active_kit_for_frontend() {
				return new class() {
					public function get_settings_for_display( $key = null ) { return null; }
					public function get_settings( $key = null ) { return null; }
					public function get_id() { return 5; }
					public function get_main_id() { return 5; }
					public function get_meta( $key ) { return array(); }
				};
			}
			public function is_custom_colors_enabled() { return true; }
			public function is_custom_typography_enabled() { return true; }
		};
		$plugin->controls_manager = new \Elementor\Controls_Manager();
		$frontend = ( new ReflectionClass( \Elementor\Frontend::class ) )->newInstanceWithoutConstructor();
		$excerpt  = new ReflectionProperty( \Elementor\Frontend::class, '_is_excerpt' );
		if ( PHP_VERSION_ID < 80100 ) {
			$excerpt->setAccessible( true );
		}
		// Sin CSS de documento: `get_builder_content()` sólo omite su encolado con `_is_excerpt`.
		$excerpt->setValue( $frontend, true );
		$plugin->frontend = $frontend;
		foreach ( array( 'common' => '\\Elementor\\Widget_Common', 'heading' => '\\Elementor\\Widget_Heading', 'text-editor' => '\\Elementor\\Widget_Text_Editor', 'button' => '\\Elementor\\Widget_Button', 'template' => '\\ElementorPro\\Modules\\Library\\Widgets\\Template' ) as $name => $class ) {
			$plugin->widgets_manager->types[ $name ] = new $class( array(), null );
		}
		// El shortcode `[elementor-element]` real (`modules/element-cache/module.php:48-70`).
		$cache    = ( new ReflectionClass( \Elementor\Modules\ElementCache\Module::class ) )->newInstanceWithoutConstructor();
		$register = new ReflectionMethod( $cache, 'register_shortcode' );
		if ( PHP_VERSION_ID < 80100 ) {
			$register->setAccessible( true );
		}
		$register->invoke( $cache );
		add_filter( 'elementor/element_cache/unique_id', array( $cache, 'get_unique_id' ) );
	}

	/** Entrada con datos de Elementor. */
	function ov_post( int $id, string $type, array $elements, string $status = 'publish', int $author = 1 ): void {
		$GLOBALS['ov']['posts'][ $id ] = array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_author' => $author, 'post_title' => 'Entrada ' . $id );
		$GLOBALS['ov']['meta'][ $id ]['_elementor_data']        = wp_json_encode( $elements );
		$GLOBALS['ov']['meta'][ $id ]['_elementor_edit_mode']   = 'builder';
		$GLOBALS['ov']['meta'][ $id ]['_elementor_version']     = '3.30.0';
		$GLOBALS['ov']['meta'][ $id ]['_elementor_page_assets'] = array();
		unset( $GLOBALS['ov']['meta'][ $id ][ \Elementor\Core\Base\Document::CACHE_META_KEY ], \Elementor\Plugin::$instance->documents->docs[ $id ] );
	}

	function ov_widget( string $id, string $type, array $settings ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}
	function ov_container( string $id, array $children, array $settings = array() ): array {
		return array( 'id' => $id, 'elType' => 'container', 'isInner' => false, 'settings' => $settings, 'elements' => $children );
	}

	/** HTML de un documento como lo pinta el front: `get_builder_content_for_display()`. */
	function ov_render( int $id ): string {
		return \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id );
	}


	/** HTML de la instancia `$id` (desde su `data-id` hasta el de `$next` o el final). */
	function ov_section( string $html, string $id, string $next = '' ): string {
		$start = strpos( $html, 'data-id="' . $id . '"' );
		if ( false === $start ) {
			return '';
		}
		$end = '' !== $next ? strpos( $html, 'data-id="' . $next . '"', $start ) : false;
		return false === $end ? substr( $html, $start ) : substr( $html, $start, $end - $start );
	}

	/** ¿Queda enganchado alguno de los cuatro filtros que `Render` pone mientras pinta una instancia? */
	function ov_render_hooked(): bool {
		$r = \Digitalisimo\Elements\F7b\Overrides\Render::class;
		return false !== has_filter( $r::HOOK_DATA, array( $r, 'data' ) ) || false !== has_filter( $r::HOOK_CACHE, array( $r, 'cache_ttl' ) )
			|| false !== has_filter( $r::HOOK_AFTER, array( $r, 'after' ) ) || false !== has_filter( $r::HOOK_CONTENT, array( $r, 'content' ) );
	}

	/** Pone una propiedad estática privada (reinicio entre casos). */
	function ov_static( string $class, string $property, $value ): void {
		$prop = new ReflectionProperty( $class, $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true );
		}
		$prop->setValue( null, $value );
	}

	/** Reinicia ganchos, registro y estado de los submódulos y rearma el núcleo. */
	function ov_fresh( bool $with_render = true ): void {
		$keep = array( 'posts' => $GLOBALS['ov']['posts'], 'meta' => $GLOBALS['ov']['meta'], 'options' => $GLOBALS['ov']['options'], 'users' => $GLOBALS['ov']['users'], 'user' => $GLOBALS['ov']['user'] );
		ov_reset();
		$GLOBALS['ov'] = array_merge( $GLOBALS['ov'], $keep );
		foreach ( array( 'Module', 'Render', 'Controls', 'Ajax' ) as $class ) {
			ov_static( 'Digitalisimo\\Elements\\F7b\\Overrides\\' . $class, 'initialized', false );
		}
		ov_static( Render::class, 'frames', array() );
		ov_static( Render::class, 'hooked', false );
		ov_boot();
		if ( $with_render ) {
			Render::init();
		}
	}

	/** Valor de `elementor_element_cache_ttl` que ve cada elemento al pintarse, por su ID. */
	function ov_spy_cache(): void {
		$GLOBALS['ov']['seen'] = array();
		add_action( 'elementor/frontend/before_render', function ( $element ) {
			$GLOBALS['ov']['seen'][ $element->get_id() ] = get_option( 'elementor_element_cache_ttl', '' );
		} );
	}

	const OV_TPL  = 300;
	const OV_PAGE = 1000;

	/** Plantilla 300: un encabezado, un editor de texto, un botón y un encabezado que se oculta. */
	function ov_template(): void {
		ov_post(
			OV_TPL,
			'elementor_library',
			array(
				ov_container(
					'c300',
					array(
						ov_widget( 'h301', 'heading', array( 'title' => 'Original', '_digitalisimo_slot' => 'titulo' ) ),
						ov_widget( 'x302', 'text-editor', array( 'editor' => '<p>Texto original</p>', '_digitalisimo_slot' => 'texto' ) ),
						ov_widget( 'b303', 'button', array( 'text' => 'Ir', 'link' => array( 'url' => 'https://a.test/', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => 'data-x|1' ), '_digitalisimo_slot' => 'boton' ) ),
						ov_widget( 'h304', 'heading', array( 'title' => 'Se oculta', '_digitalisimo_slot' => 'oculto' ) ),
					)
				),
			)
		);
	}

	/** Filas de la instancia con sobrescrituras: como las guarda el editor (`kind` de texto sin guardar, por defecto). */
	function ov_rows(): array {
		return array(
			array( '_id' => 'r1', 'slot' => 'titulo', 'text' => 'Nuevo <título>' ),
			array( '_id' => 'r2', 'slot' => 'texto', 'kind' => 'html', 'html' => '<p><strong>Rico</strong></p>' ),
			array( '_id' => 'r3', 'slot' => 'boton', 'kind' => 'link', 'link' => array( 'url' => 'https://b.test/', 'is_external' => 'on', 'nofollow' => '', 'custom_attributes' => 'onclick|alert(1)' ) ),
			array( '_id' => 'r4', 'slot' => 'oculto', 'kind' => 'hide' ),
		);
	}

	function ov_tpl_widget( string $id, int $template, array $rows = array(), array $extra = array() ): array {
		$settings = array( 'template_id' => (string) $template ) + $extra;
		if ( $rows ) {
			$settings[ Apply::REPEATER ] = $rows;
		}
		return ov_widget( $id, 'template', $settings );
	}

	function ov_page( int $id, array $widgets ): void {
		ov_post( $id, 'page', array( ov_container( 'p' . $id, $widgets ) ) );
	}

	$GLOBALS['ov']['users'] = array(
		1 => array( 'caps' => array( 'edit_posts', 'edit_others_posts', 'unfiltered_html', 'manage_options' ) ),
		2 => array( 'caps' => array( 'read' ) ),
		3 => array( 'caps' => array( 'edit_posts' ) ),
		4 => array( 'caps' => array( 'edit_posts' ) ),
	);
	$GLOBALS['ov']['options']['elementor_element_cache_ttl'] = '24';
	$GLOBALS['wp_embed'] = null;
	ov_boot();

	try {
		// ── 1. Clave y forma de las filas ─────────────────────────────────────────────────────────────────────────────
		foreach ( array( 'titulo', 'a', 'a-b_c9', str_repeat( 'x', 40 ) ) as $ok ) {
			check_ov( Apply::valid_slot( $ok ), "Clave válida: {$ok}." );
		}
		foreach ( array( '', 'Titulo', 'título', str_repeat( 'x', 41 ), 'a b', 'a.b', 12, null, array( 'a' ) ) as $bad ) {
			check_ov( ! Apply::valid_slot( $bad ), 'Clave no válida: ' . json_encode( $bad ) . '.' );
		}
		check_ov( 'titulo' === Apply::slot_of( ov_widget( 'a', 'heading', array( '_digitalisimo_slot' => 'titulo' ) ) ), 'slot_of() lee la clave de un encabezado.' );
		check_ov( '' === Apply::slot_of( ov_widget( 'a', 'heading', array( '_digitalisimo_slot' => 'Título' ) ) ), 'Una clave con otra forma nunca coincide.' );
		check_ov( '' === Apply::slot_of( ov_widget( 'a', 'html', array( '_digitalisimo_slot' => 'titulo' ) ) ), 'Un tipo sin clave (HTML) nunca coincide.' );
		check_ov( '' === Apply::slot_of( ov_container( 'a', array(), array( '_digitalisimo_slot' => 'titulo' ) ) ), 'Un contenedor nunca coincide.' );
		check_ov( array( 'heading', 'text-editor', 'image', 'button', 'icon', 'video', 'icon-box' ) === array_keys( Apply::TARGETS ), 'Los siete tipos de D12 y ninguno más.' );
		check_ov( array( 'link', 'hide' ) === Apply::kinds_for( 'icon' ) && array() === Apply::kinds_for( 'html' ), 'kinds_for(): los del tipo y «hide»; nada para un tipo sin clave.' );

		$rows = Apply::rows(
			array(
				'no',
				array( 'slot' => 'Malo', 'kind' => 'text', 'text' => 'x' ),
				array( 'slot' => 'a', 'kind' => 'script', 'text' => 'x' ),
				array( 'slot' => 'a', 'kind' => 'text', 'text' => '   ' ),
				array( 'slot' => 'a', 'kind' => 'image', 'image' => array( 'id' => '', 'url' => '' ) ),
				array( 'slot' => 'a', 'kind' => 'link', 'link' => array( 'url' => 'javascript:alert(1)' ) ),
				array( 'slot' => 'a', 'text' => '<b>x</b> & y' ),
				array( 'slot' => 'b', 'kind' => 'html', 'html' => '<em>y</em><span onclick="z">w</span>' ),
				array( 'slot' => 'c', 'kind' => 'image', 'image' => array( 'id' => '12', 'url' => 'https://s.test/i.jpg', 'alt' => 'no' ) ),
				array( 'slot' => 'd', 'kind' => 'link', 'link' => array( 'url' => ' https://d.test/ ', 'is_external' => 'on', 'nofollow' => 'x', 'custom_attributes' => 'onclick|a' ) ),
				array( 'slot' => 'e', 'kind' => 'hide' ),
			)
		);
		check_ov( 5 === count( $rows ), 'rows(): se descartan en silencio las filas sin clave válida, con un kind fuera de la lista o sin valor.' );
		check_ov( array( 'slot' => 'a', 'kind' => 'text', 'value' => '&lt;b&gt;x&lt;/b&gt; &amp; y' ) === $rows[0], 'Una fila sin kind (el editor no guarda el valor por defecto) es texto, y el texto se escapa.' );
		check_ov( '<em>y</em><span onclick="z">w</span>' === $rows[1]['value'], 'El HTML guardado se confía como en el núcleo: sin kses al pintar (C1-24).' );
		check_ov( array( 'id' => 12, 'url' => 'https://s.test/i.jpg' ) === $rows[2]['value'], 'Imagen: ID entero y URL, nada más.' );
		check_ov( array( 'url' => 'https://d.test/', 'is_external' => 'on', 'nofollow' => '' ) === $rows[3]['value'], 'Enlace: sólo url, is_external y nofollow; nunca custom_attributes (C2-31 b).' );
		check_ov( true === $rows[4]['value'] && 'hide' === $rows[4]['kind'], 'hide sin valor.' );
		$many = array_fill( 0, 60, array( 'slot' => 'a', 'kind' => 'hide' ) );
		check_ov( Apply::MAX_ROWS === count( Apply::rows( $many ) ), 'Como mucho ' . Apply::MAX_ROWS . ' filas por instancia.' );
		check_ov( array() === Apply::rows( 'x' ) && array() === Apply::rows( null ), 'Un repetidor que no es una lista no aplica nada.' );

		// ── 2. Sustitución por cada kind en los siete tipos ───────────────────────────────────────────────────────────
		$set = function ( string $type, array $settings, array $row ) {
			$rows = Apply::rows( array( array( 'slot' => 'k' ) + $row ) );
			$out  = Apply::apply( array( ov_widget( 'w1', $type, $settings + array( '_digitalisimo_slot' => 'k' ) ) ), $rows );
			return $out ? $out[0]['settings'] : null;
		};
		$dyn = array( '__dynamic__' => array( 'title' => '[elementor-tag id="1" name="post-title"]', 'link' => '[elementor-tag id="2" name="post-url"]' ) );
		$s = $set( 'heading', array( 'title' => 'A' ) + $dyn, array( 'kind' => 'text', 'text' => 'B <i>' ) );
		check_ov( 'B &lt;i&gt;' === $s['title'] && ! isset( $s['__dynamic__']['title'] ) && isset( $s['__dynamic__']['link'] ), 'Encabezado/text: título escapado y sin su etiqueta dinámica; el resto de etiquetas intactas.' );
		$s = $set( 'heading', array( 'title' => 'A' ), array( 'kind' => 'html', 'html' => 'B <i>c</i>' ) );
		check_ov( 'B <i>c</i>' === $s['title'], 'Encabezado/html: título con HTML.' );
		$s = $set( 'heading', array( 'link' => array( 'url' => 'https://a.test/', 'custom_attributes' => 'data-a|1' ) ) + $dyn, array( 'kind' => 'link', 'link' => array( 'url' => 'https://b.test/', 'nofollow' => 'on', 'custom_attributes' => 'onclick|x' ) ) );
		check_ov( array( 'url' => 'https://b.test/', 'custom_attributes' => 'data-a|1', 'is_external' => '', 'nofollow' => 'on' ) === $s['link'] && ! isset( $s['__dynamic__']['link'] ), 'Encabezado/link: URL y casillas de la fila; los atributos del elemento de la plantilla se conservan.' );
		$s = $set( 'heading', array( 'title' => 'A' ), array( 'kind' => 'image', 'image' => array( 'id' => 3 ) ) );
		check_ov( array( 'title' => 'A', '_digitalisimo_slot' => 'k' ) === $s, 'Un kind que el tipo no declara (imagen en un encabezado) se ignora.' );
		$s = $set( 'text-editor', array( 'editor' => '<p>A</p>' ), array( 'kind' => 'text', 'text' => 'x<y' ) );
		check_ov( 'x&lt;y' === $s['editor'], 'Editor de texto/text.' );
		$s = $set( 'text-editor', array( 'editor' => '<p>A</p>' ), array( 'kind' => 'html', 'html' => '<ul><li>1</li></ul>' ) );
		check_ov( '<ul><li>1</li></ul>' === $s['editor'], 'Editor de texto/html.' );
		$s = $set( 'image', array( 'image' => array( 'id' => 1, 'url' => 'https://s.test/a.jpg' ), '__dynamic__' => array( 'image' => '[elementor-tag]' ) ), array( 'kind' => 'image', 'image' => array( 'id' => 9, 'url' => 'https://s.test/b.jpg' ) ) );
		check_ov( array( 'id' => 9, 'url' => 'https://s.test/b.jpg' ) === $s['image'] && array() === $s['__dynamic__'], 'Imagen/image.' );
		$s = $set( 'image', array( 'link_to' => 'none' ), array( 'kind' => 'link', 'link' => array( 'url' => 'https://b.test/' ) ) );
		check_ov( 'custom' === $s['link_to'] && 'https://b.test/' === $s['link']['url'], 'Imagen/link: enlace personalizado.' );
		$s = $set( 'image', array( 'caption_source' => 'none' ), array( 'kind' => 'text', 'text' => 'Pie' ) );
		check_ov( 'custom' === $s['caption_source'] && 'Pie' === $s['caption'], 'Imagen/text: pie personalizado.' );
		$s = $set( 'button', array( 'text' => 'Ir' ), array( 'kind' => 'text', 'text' => 'Ver' ) );
		check_ov( 'Ver' === $s['text'], 'Botón/text.' );
		$s = $set( 'button', array(), array( 'kind' => 'link', 'link' => array( 'url' => 'https://b.test/', 'is_external' => 'on' ) ) );
		check_ov( array( 'url' => 'https://b.test/', 'is_external' => 'on', 'nofollow' => '' ) === $s['link'], 'Botón/link.' );
		$s = $set( 'icon', array(), array( 'kind' => 'link', 'link' => array( 'url' => 'https://b.test/' ) ) );
		check_ov( 'https://b.test/' === $s['link']['url'], 'Icono/link.' );
		$s = $set( 'icon', array(), array( 'kind' => 'text', 'text' => 'x' ) );
		check_ov( ! isset( $s['text'] ), 'Icono/text se ignora.' );
		$s = $set( 'video', array(), array( 'kind' => 'link', 'link' => array( 'url' => 'https://v.test/1' ) ) );
		check_ov( 'https://v.test/1' === $s['youtube_url'], 'Vídeo/link sin fuente: la de YouTube, la fuente por defecto.' );
		$s = $set( 'video', array( 'video_type' => 'vimeo', '__dynamic__' => array( 'vimeo_url' => '[x]' ) ), array( 'kind' => 'link', 'link' => array( 'url' => 'https://v.test/2' ) ) );
		check_ov( 'https://v.test/2' === $s['vimeo_url'] && ! isset( $s['youtube_url'] ) && array() === $s['__dynamic__'], 'Vídeo/link con Vimeo.' );
		$s = $set( 'video', array( 'video_type' => 'hosted', 'hosted_url' => array( 'url' => 'https://s.test/a.mp4', 'id' => 4 ) ), array( 'kind' => 'link', 'link' => array( 'url' => 'https://s.test/b.mp4' ) ) );
		check_ov( 'yes' === $s['insert_url'] && 'https://s.test/b.mp4' === $s['external_url']['url'], 'Vídeo/link alojado: URL externa.' );
		$s = $set( 'video', array( 'video_type' => 'otro' ), array( 'kind' => 'link', 'link' => array( 'url' => 'https://s.test/b.mp4' ) ) );
		check_ov( array( 'video_type' => 'otro', '_digitalisimo_slot' => 'k' ) === $s, 'Vídeo/link con una fuente desconocida no cambia nada.' );
		$s = $set( 'video', array(), array( 'kind' => 'image', 'image' => array( 'id' => 5 ) ) );
		check_ov( array( 'id' => 5, 'url' => '' ) === $s['image_overlay'], 'Vídeo/image: imagen superpuesta.' );
		$s = $set( 'icon-box', array(), array( 'kind' => 'text', 'text' => 'T' ) );
		check_ov( 'T' === $s['title_text'], 'Caja de icono/text: título.' );
		$s = $set( 'icon-box', array(), array( 'kind' => 'html', 'html' => '<b>D</b>' ) );
		check_ov( '<b>D</b>' === $s['description_text'], 'Caja de icono/html: descripción.' );
		$s = $set( 'icon-box', array(), array( 'kind' => 'link', 'link' => array( 'url' => 'https://b.test/' ) ) );
		check_ov( 'https://b.test/' === $s['link']['url'], 'Caja de icono/link.' );
		foreach ( array_keys( Apply::TARGETS ) as $type ) {
			check_ov( null === $set( $type, array(), array( 'kind' => 'hide' ) ), "{$type}/hide: el elemento sale de los datos." );
		}
		$data = array(
			ov_container(
				'c1',
				array(
					ov_widget( 'b1', 'button', array( 'text' => 'A', '_digitalisimo_slot' => 'k' ) ),
					ov_container( 'c2', array( ov_widget( 'b2', 'button', array( 'text' => 'B', '_digitalisimo_slot' => 'k' ) ), ov_widget( 'h1', 'heading', array( 'title' => 'H', '_digitalisimo_slot' => 'fuera' ) ) ) ),
					ov_widget( 'w1', 'html', array( 'html' => 'x', '_digitalisimo_slot' => 'k' ) ),
				)
			),
		);
		$out = Apply::apply( $data, Apply::rows( array( array( 'slot' => 'k', 'text' => 'Z' ), array( 'slot' => 'k', 'kind' => 'link', 'link' => array( 'url' => 'https://z.test/' ) ), array( 'slot' => 'fuera', 'kind' => 'hide' ) ) ) );
		check_ov( 'Z' === $out[0]['elements'][0]['settings']['text'] && 'https://z.test/' === $out[0]['elements'][0]['settings']['link']['url'], 'Varias filas de la misma clave se suman (texto y enlace).' );
		check_ov( 'Z' === $out[0]['elements'][1]['elements'][0]['settings']['text'], 'Una clave repetida cambia todos sus elementos, también anidados.' );
		check_ov( 1 === count( $out[0]['elements'][1]['elements'] ) && 'b2' === $out[0]['elements'][1]['elements'][0]['id'], 'hide quita el elemento anidado; el contenedor queda.' );
		check_ov( array( 'html' => 'x', '_digitalisimo_slot' => 'k' ) === $out[0]['elements'][2]['settings'], 'Un widget sin clave admitida no cambia aunque tenga la misma clave.' );
		check_ov( array( 'c1', 'b1', 'c2', 'b2', 'w1' ) === array( $out[0]['id'], $out[0]['elements'][0]['id'], $out[0]['elements'][1]['id'], $out[0]['elements'][1]['elements'][0]['id'], $out[0]['elements'][2]['id'] ), 'Los IDs no cambian (C1-7: sin sufijos).' );
		check_ov( $data === Apply::apply( $data, array() ), 'Sin filas, los datos tal cual.' );

		echo "1-2 · forma y sustitución: {$GLOBALS['ov_checks']} comprobaciones.\n";

		// ── 3. Pintar con el núcleo real: una instancia con y otra sin, caché por documento ──────────────────────────
		$cache_key = \Elementor\Core\Base\Document::CACHE_META_KEY;
		ov_template();
		ov_fresh( false );
		ov_page( 1001, array( ov_tpl_widget( 't2', OV_TPL ) ) );
		$base    = ov_render( 1001 );
		$base_t2 = ov_section( $base, 't2' );
		check_ov( false !== strpos( $base_t2, '>Original</h2>' ) && false !== strpos( $base_t2, 'Se oculta' ) && false !== strpos( $base_t2, 'Texto original' ), 'Línea base sin el módulo: el Plantilla pinta la plantilla.' );
		check_ov( false !== strpos( (string) ( $GLOBALS['ov']['meta'][ OV_TPL ][ $cache_key ] ?? '' ), 'Original' ), 'Línea base: la plantilla guarda su caché de elementos (por documento).' );
		$tpl_cache = $GLOBALS['ov']['meta'][ OV_TPL ][ $cache_key ];

		ov_fresh( true );
		ov_page( 1001, array( ov_tpl_widget( 't2', OV_TPL ) ) );
		$GLOBALS['ov']['added'] = array();
		ov_log_reset();
		$html = ov_render( 1001 );
		check_ov( $base === $html, 'Con el módulo, un Plantilla sin sobrescrituras pinta el mismo HTML byte a byte.' );
		check_ov( array( 'get:300:' . $cache_key ) === ov_meta_touches( OV_TPL, $cache_key ), 'Sin sobrescrituras se usa la caché de la plantilla, como hoy.' );
		check_ov( array() === array_intersect( $GLOBALS['ov']['added'], array( Render::HOOK_DATA, Render::HOOK_CACHE, Render::HOOK_AFTER, Render::HOOK_CONTENT ) ) && ! Render::frames(), 'Una instancia sin sobrescrituras no engancha nada.' );
		ov_fresh( false );
		$hooks = ov_hooks();
		Render::init();
		check_ov( array( 'elementor/widget/before_render_content', 'elementor/markdown/element_output' ) === array_values( array_diff( array_keys( ov_hooks() ), array_keys( $hooks ) ) ), 'Ganchos permanentes de Render: before_render_content y el del Markdown.' );

		ov_page( OV_PAGE, array( ov_tpl_widget( 't1', OV_TPL, ov_rows() ), ov_tpl_widget( 't2', OV_TPL ) ) );
		$GLOBALS['ov']['seen'] = array();
		$GLOBALS['ov']['data_calls'] = array();
		add_action( 'elementor/frontend/before_render', function ( $element ) {
			$GLOBALS['ov']['seen'][] = $element->get_id() . '=' . get_option( 'elementor_element_cache_ttl', '' );
		} );
		add_filter( 'elementor/frontend/builder_content_data', function ( $data, $post_id ) {
			$GLOBALS['ov']['data_calls'][] = $post_id . ':' . ( false === has_filter( Render::HOOK_DATA, array( Render::class, 'data' ) ) ? 'sin' : 'con' ) . ':' . ( false === has_filter( Render::HOOK_CACHE, array( Render::class, 'cache_ttl' ) ) ? 'sin' : 'con' );
			return $data;
		}, 99, 2 );
		ov_log_reset();
		$html = ov_render( OV_PAGE );
		$t1   = ov_section( $html, 't1', 't2' );
		$t2   = ov_section( $html, 't2' );
		check_ov( false !== strpos( $t1, '<h2 class="elementor-heading-title elementor-size-default">Nuevo &lt;título&gt;</h2>' ), 'text: el título de la instancia, escapado.' );
		check_ov( false !== strpos( $t1, '<p><strong>Rico</strong></p>' ) && false === strpos( $t1, 'Texto original' ), 'html: el contenido del editor de texto.' );
		check_ov( false !== strpos( $t1, 'href="https://b.test/"' ) && false !== strpos( $t1, 'target="_blank"' ) && false === strpos( $t1, 'onclick' ) && false === strpos( $t1, 'https://a.test/' ), 'link: URL y «nueva ventana» de la fila, sin sus atributos.' );
		check_ov( false !== strpos( $t1, 'data-x="1"' ), 'link: los atributos del botón de la plantilla se conservan.' );
		check_ov( false === strpos( $t1, 'Se oculta' ) && false === strpos( $t1, 'elementor-element-h304' ), 'hide: el elemento no se pinta.' );
		check_ov( false !== strpos( $t1, 'elementor-element-h301' ) && false !== strpos( $t1, 'data-id="b303"' ), 'Los IDs HTML son los de la plantilla (C1-7).' );
		check_ov( $base_t2 === $t2, 'La otra instancia, sin sobrescrituras, pinta byte a byte lo mismo que sin el módulo.' );
		check_ov( $tpl_cache === $GLOBALS['ov']['meta'][ OV_TPL ][ $cache_key ], 'La caché de la plantilla queda intacta.' );
		check_ov( array( 'get:300:' . $cache_key ) === ov_meta_touches( OV_TPL, $cache_key ), 'Sólo la instancia sin sobrescrituras lee la caché de la plantilla; la otra ni la lee ni la escribe.' );
		check_ov( in_array( 'pre:elementor_element_cache_ttl=disable', $GLOBALS['ov']['option_log'], true ), 'La instancia con sobrescrituras apaga la caché con pre_option_elementor_element_cache_ttl.' );
		$seen = $GLOBALS['ov']['seen'];
		$t1_at = array_search( 't1=24', $seen, true );
		$t2_at = array_search( 't2=24', $seen, true );
		check_ov( false !== $t1_at && false !== $t2_at && $t1_at < $t2_at, 'Los dos Plantilla se pintan con la caché del sitio (la página sigue en caché).' );
		check_ov( array( 'c300=disable', 'h301=disable', 'x302=disable', 'b303=disable' ) === array_slice( $seen, $t1_at + 1, 4 ), 'Dentro de la instancia con sobrescrituras, la caché está apagada para su plantilla.' );
		check_ov( array( 't2=24', 'x302=24' ) === array_slice( $seen, $t2_at ), 'Al terminar esa instancia la caché vuelve: la otra sale de la caché de la plantilla (sólo el editor de texto, dinámico, se pinta en vivo).' );
		check_ov( array( '1000:sin:sin', '300:con:con', '300:sin:sin' ) === $GLOBALS['ov']['data_calls'], 'builder_content_data y pre_option sólo existen durante la instancia con sobrescrituras.' );
		check_ov( ! ov_render_hooked() && ! Render::frames(), 'Al terminar, los filtros se retiran y no queda ninguna instancia apilada.' );
		$page_cache = (string) ( $GLOBALS['ov']['meta'][ OV_PAGE ][ $cache_key ] ?? '' );
		check_ov( 2 === substr_count( $page_cache, '[elementor-element k=' ) && false === strpos( $page_cache, 'Nuevo' ), 'La página guarda su caché con los dos Plantilla como shortcode (se pintan en cada visita).' );

		ov_log_reset();
		$again = ov_render( OV_PAGE );
		check_ov( $html === $again, 'Segunda visita con la página en caché: el mismo HTML.' );
		check_ov( array( 'get:1000:' . $cache_key ) === ov_meta_touches( OV_PAGE, $cache_key ) && array( 'get:300:' . $cache_key ) === ov_meta_touches( OV_TPL, $cache_key ), 'Segunda visita: página y plantilla desde su caché; la instancia con sobrescrituras, fresca.' );
		check_ov( $tpl_cache === $GLOBALS['ov']['meta'][ OV_TPL ][ $cache_key ], 'Segunda visita: la caché de la plantilla sigue intacta.' );

		ov_page( 1002, array( ov_tpl_widget( 't2', OV_TPL ), ov_tpl_widget( 't1', OV_TPL, ov_rows() ) ) );
		unset( $GLOBALS['ov']['meta'][ OV_TPL ][ $cache_key ] );
		ov_log_reset();
		$html = ov_render( 1002 );
		$t2   = ov_section( $html, 't2', 't1' );
		$t1   = ov_section( $html, 't1' );
		check_ov( false !== strpos( $t2, '>Original</h2>' ) && false !== strpos( $t2, 'Se oculta' ) && false === strpos( $t2, 'Nuevo' ), 'Orden inverso: la instancia sin sobrescrituras pinta la plantilla (y escribe su caché).' );
		check_ov( false !== strpos( $t1, 'Nuevo &lt;título&gt;' ) && false === strpos( $t1, 'Se oculta' ), 'Orden inverso: la instancia con sobrescrituras no lee la caché recién escrita.' );
		check_ov( array( 'get:300:' . $cache_key, 'set:300:' . $cache_key ) === ov_meta_touches( OV_TPL, $cache_key ), 'Orden inverso: sólo la instancia sin sobrescrituras lee y escribe la caché de la plantilla.' );
		check_ov( false === strpos( (string) $GLOBALS['ov']['meta'][ OV_TPL ][ $cache_key ], 'Nuevo' ), 'La caché escrita es la de la plantilla sin sobrescribir.' );

		// Caché del sitio apagada: igual, sin tocar metas.
		$GLOBALS['ov']['options']['elementor_element_cache_ttl'] = 'disable';
		ov_page( 1003, array( ov_tpl_widget( 't1', OV_TPL, ov_rows() ) ) );
		ov_log_reset();
		$html = ov_render( 1003 );
		check_ov( false !== strpos( $html, 'Nuevo &lt;título&gt;' ) && array() === ov_meta_touches( OV_TPL, $cache_key ), 'Con la caché del sitio apagada también se aplica, sin leer ni escribir cachés.' );
		$GLOBALS['ov']['options']['elementor_element_cache_ttl'] = '24';

		// Caché «Activa» en el propio Plantilla: se resuelve al guardar la caché de la página.
		ov_page( 1006, array( ov_tpl_widget( 't6', OV_TPL, ov_rows(), array( '_element_cache' => 'no' ) ) ) );
		ov_log_reset();
		$html       = ov_render( 1006 );
		$page_cache = (string) ( json_decode( (string) ( $GLOBALS['ov']['meta'][1006][ $cache_key ] ?? '' ), true )['value']['content'] ?? '' );
		check_ov( false !== strpos( $html, 'Nuevo &lt;título&gt;' ) && false !== strpos( $html, '<strong>Rico</strong>' ) && false === strpos( $html, 'Se oculta' ), 'Plantilla con caché «Activa»: sobrescrito igual.' );
		check_ov( false !== strpos( $page_cache, 'Nuevo &lt;título&gt;' ) && array() === ov_meta_touches( OV_TPL, $cache_key ), 'Plantilla con caché «Activa»: la página guarda la instancia ya resuelta y la plantilla no toca su caché.' );
		check_ov( ! ov_render_hooked() && ! Render::frames(), 'Plantilla con caché «Activa»: filtros retirados.' );

		// Plantilla en borrador: el núcleo no la pinta y el módulo no apila nada.
		ov_post( 301, 'elementor_library', array( ov_widget( 'h311', 'heading', array( 'title' => 'Borrador', '_digitalisimo_slot' => 'titulo' ) ) ), 'draft' );
		ov_page( 1007, array( ov_tpl_widget( 't7', 301, ov_rows() ) ) );
		$GLOBALS['ov']['added'] = array();
		$html = ov_render( 1007 );
		check_ov( false === strpos( $html, 'Borrador' ) && ! in_array( Render::HOOK_DATA, $GLOBALS['ov']['added'], true ) && ! Render::frames(), 'Una plantilla sin publicar no se pinta y no engancha nada.' );
		// Plantilla publicada que el núcleo no pinta (sin datos del constructor): el marco se retira igual.
		ov_post( 304, 'elementor_library', array( ov_widget( 'h341', 'heading', array( 'title' => 'Sin constructor', '_digitalisimo_slot' => 'titulo' ) ) ) );
		unset( $GLOBALS['ov']['meta'][304]['_elementor_edit_mode'] );
		ov_page( 1009, array( ov_tpl_widget( 't9', 304, ov_rows() ) ) );
		$html = ov_render( 1009 );
		check_ov( false !== strpos( $html, '<div class="elementor-template">' ) && false === strpos( $html, 'Sin constructor' ) && ! ov_render_hooked() && ! Render::frames(), 'Si el núcleo no llega a builder_content_data, el marco sin aplicar se retira igual.' );
		// Una entrada que no es de la biblioteca: se pinta como hoy, sin sobrescribir.
		ov_post( 302, 'page', array( ov_widget( 'h321', 'heading', array( 'title' => 'Pagina', '_digitalisimo_slot' => 'titulo' ) ) ) );
		ov_page( 1008, array( ov_tpl_widget( 't8', 302, ov_rows() ) ) );
		$html = ov_render( 1008 );
		check_ov( false !== strpos( $html, '>Pagina</h2>' ) && false === strpos( $html, 'Nuevo' ), 'Sólo se sobrescriben plantillas de la biblioteca.' );

		echo "3 · front con el núcleo real: {$GLOBALS['ov_checks']} comprobaciones.\n";

		// ── 4. Editor: render_content() sin print_element() y la caché del HTML del editor ──────────────────────────
		$plugin = \Elementor\Plugin::$instance;
		$plugin->editor->set_edit_mode( true );
		$widget = $plugin->elements_manager->create_element_instance( ov_tpl_widget( 'e1', OV_TPL, ov_rows() ) );
		ob_start();
		$widget->render_content();
		$out = ob_get_clean();
		check_ov( false !== strpos( $out, 'Nuevo &lt;título&gt;' ) && false === strpos( $out, 'Se oculta' ), 'Editor (render_widget): la vista previa muestra las sobrescrituras.' );
		check_ov( ! ov_render_hooked() && ! Render::frames(), 'Editor: sin print_element() los filtros se retiran en render_content.' );
		$raw = $plugin->elements_manager->create_element_instance( ov_tpl_widget( 'e2', OV_TPL, ov_rows() ) )->get_raw_data( true );
		check_ov( false !== strpos( $raw['htmlCache'], 'Nuevo &lt;título&gt;' ) && ! Render::frames() && ! ov_render_hooked(), 'Editor (htmlCache de la configuración): igual, y sin restos.' );
		$plugin->editor->set_edit_mode( false );
		check_ov( $tpl_cache !== null && false === strpos( (string) $GLOBALS['ov']['meta'][ OV_TPL ][ $cache_key ], 'Nuevo' ), 'Editor: la caché de la plantilla no recibe lo sobrescrito.' );

		// ── 5. Plantilla dentro de otro Plantilla ────────────────────────────────────────────────────────────────────
		ov_post( 500, 'elementor_library', array( ov_container( 'c500', array( ov_widget( 'h501', 'heading', array( 'title' => 'B', '_digitalisimo_slot' => 'x' ) ) ) ) ) );
		ov_post( 400, 'elementor_library', array( ov_container( 'c400', array( ov_widget( 'h401', 'heading', array( 'title' => 'A', '_digitalisimo_slot' => 'x' ) ), ov_tpl_widget( 'ta', 500, array( array( 'slot' => 'x', 'text' => 'desde A' ) ) ) ) ) ) );
		ov_post( 401, 'elementor_library', array( ov_container( 'c401', array( ov_widget( 'h402', 'heading', array( 'title' => 'A2', '_digitalisimo_slot' => 'x' ) ), ov_tpl_widget( 'tb', 500 ) ) ) ) );
		ov_page( 1004, array( ov_tpl_widget( 't4', 400, array( array( 'slot' => 'x', 'text' => 'desde página' ) ) ) ) );
		$GLOBALS['ov']['seen'] = array();
		ov_log_reset();
		$html = ov_render( 1004 );
		check_ov( false !== strpos( $html, '>desde página</h2>' ) && false !== strpos( $html, '>desde A</h2>' ) && false === strpos( $html, '>A</h2>' ) && false === strpos( $html, '>B</h2>' ), 'Anidado: cada instancia sobrescribe sólo su plantilla.' );
		check_ov( in_array( 'h401=disable', $GLOBALS['ov']['seen'], true ) && in_array( 'h501=disable', $GLOBALS['ov']['seen'], true ), 'Anidado: las dos plantillas sin caché mientras se pintan sus instancias.' );
		check_ov( array() === ov_meta_touches( 400, $cache_key ) && array() === ov_meta_touches( 500, $cache_key ), 'Anidado: ninguna de las dos cachés se toca.' );
		check_ov( ! ov_render_hooked() && ! Render::frames(), 'Anidado: al terminar no queda nada.' );
		ov_page( 1005, array( ov_tpl_widget( 't5', 401, array( array( 'slot' => 'x', 'text' => 'desde página' ) ) ) ) );
		$GLOBALS['ov']['seen'] = array();
		ov_log_reset();
		$html = ov_render( 1005 );
		check_ov( false !== strpos( $html, '>desde página</h2>' ) && false !== strpos( $html, '>B</h2>' ), 'Anidado sin sobrescrituras dentro: la plantilla interior no recibe las de fuera aunque tenga la misma clave.' );
		check_ov( in_array( 'h501=24', $GLOBALS['ov']['seen'], true ) && array( 'get:500:' . $cache_key, 'set:500:' . $cache_key ) === ov_meta_touches( 500, $cache_key ) && array() === ov_meta_touches( 401, $cache_key ), 'Anidado: la plantilla interior usa su caché; la de la instancia con sobrescrituras, no.' );
		check_ov( false === strpos( (string) $GLOBALS['ov']['meta'][500][ $cache_key ], 'desde' ), 'Anidado: la caché interior es la de la plantilla sin sobrescribir.' );

		// ── 6. Markdown (experimento del núcleo; el conversor HTML necesita WordPress, así que sólo encabezados) ──────
		ov_post( 310, 'elementor_library', array( ov_widget( 'h311', 'heading', array( 'title' => 'Original', '_digitalisimo_slot' => 'titulo' ) ), ov_widget( 'h312', 'heading', array( 'title' => 'Se oculta', '_digitalisimo_slot' => 'oculto' ) ) ) );
		$md = ( new \Elementor\Modules\MarkdownRender\Markdown_Renderer() )->render_elements_data( array( ov_tpl_widget( 'm1', 310, ov_rows() ), ov_tpl_widget( 'm2', 310 ) ) );
		$at = strpos( $md, 'Original' );
		check_ov( false !== strpos( $md, '## Nuevo' ) && false !== $at && strpos( $md, 'Nuevo' ) < $at, 'Markdown: la instancia con sobrescrituras sale sobrescrita y la otra, original.' );
		check_ov( 1 === substr_count( $md, 'Se oculta' ), 'Markdown: lo ocultado no aparece en la instancia que lo oculta.' );
		check_ov( ! ov_render_hooked() && ! Render::frames(), 'Markdown: sin marcos ni filtros.' );

		echo "4-6 · editor, anidado y Markdown: {$GLOBALS['ov_checks']} comprobaciones.\n";

		// ── 7. Arranque: con Flags::active() falso no se engancha nada ───────────────────────────────────────────────
		$f7b   = 'Digitalisimo\\Elements\\F7b\\Module';
		$flags = 'Digitalisimo\\Elements\\F7b\\Flags';
		$mod   = 'Digitalisimo\\Elements\\F7b\\Overrides\\Module';
		ov_fresh( false );
		$f7b::$editing = false;
		$flags::$has   = array();
		$base_hooks    = ov_hooks();
		$mod::init();
		check_ov( $base_hooks === ov_hooks(), 'Sin sobrescrituras en el sitio y fuera del editor: ningún gancho.' );
		$flags::$has = array( 'overrides' => true );
		ov_static( $mod, 'initialized', false );
		$f7b::$missing = array();
		$mod::init();
		check_ov( array( 'elementor/widget/before_render_content', 'elementor/markdown/element_output' ) === array_values( array_diff( array_keys( ov_hooks() ), array_keys( $base_hooks ) ) ), 'Con sobrescrituras, en el front: sólo before_render_content y el Markdown; ni controles ni AJAX ni scripts.' );
		ov_fresh( false );
		$f7b::$editing = true;
		$flags::$has   = array();
		$mod::init();
		$added = array_values( array_diff( array_keys( ov_hooks() ), array_keys( $base_hooks ) ) );
		foreach ( array( 'elementor/widget/before_render_content', 'elementor/element/heading/section_title/before_section_end', 'elementor/element/video/section_video/before_section_end', 'elementor/element/template/section_template/before_section_end', 'elementor/ajax/register_actions', 'elementor/editor/after_enqueue_scripts', 'elementor/document/config' ) as $hook ) {
			check_ov( in_array( $hook, $added, true ), "En el editor (aunque el sitio aún no tenga sobrescrituras): {$hook}." );
		}
		check_ov( ! in_array( 'wp_enqueue_scripts', $added, true ) && ! in_array( 'elementor/frontend/after_enqueue_scripts', $added, true ), 'El script sólo se encola en el editor.' );
		ov_fresh( false );
		$f7b::$editing = true;
		$f7b::$fail    = true;
		$hooks         = ov_hooks();
		$mod::init();
		check_ov( $hooks === ov_hooks() && in_array( 'Elementor\\Plugin', $f7b::$asked, true ), 'Si F7b\\Module::requires() falla (falta un símbolo del núcleo), el submódulo no engancha nada.' );
		$f7b::$fail    = false;
		$f7b::$editing = false;

		// ── 8. Controles en las pilas reales, como en el editor (pilas sin optimizar) ─────────────────────────────────
		ov_static( \Elementor\Core\Frontend\Performance::class, 'is_frontend', false );
		ov_fresh( false );
		\Digitalisimo\Elements\F7b\Overrides\Controls::init();
		$plugin = \Elementor\Plugin::$instance;
		foreach ( array( 'heading', 'text-editor', 'button' ) as $type ) {
			$controls = $plugin->elements_manager->create_element_instance( ov_widget( 'z1', $type, array() ) )->get_controls();
			$slot     = $controls[ Apply::SLOT ] ?? null;
			check_ov( is_array( $slot ) && 'text' === $slot['type'] && false === $slot['style_transfer'] && '' === (string) ( $slot['default'] ?? '' ), "{$type}: «Clave de sobrescritura» de texto, sin valor por defecto y con style_transfer => false." );
			check_ov( 'content' === $slot['tab'] && 'none' === $slot['render_type'] && empty( $slot['selectors'] ) && empty( $slot['prefix_class'] ) && empty( $slot['frontend_available'] ), "{$type}: la clave no cambia el HTML ni el CSS." );
			check_ov( isset( $controls[ \Digitalisimo\Elements\F7b\Overrides\Controls::NOTE ] ) && false === $controls[ \Digitalisimo\Elements\F7b\Overrides\Controls::NOTE ]['style_transfer'], "{$type}: el aviso de la clave." );
		}
		$template = $plugin->elements_manager->create_element_instance( ov_tpl_widget( 'z2', OV_TPL ) );
		$controls = $template->get_controls();
		$rep      = $controls[ Apply::REPEATER ] ?? null;
		check_ov( is_array( $rep ) && 'repeater' === $rep['type'] && false === $rep['style_transfer'] && false === $rep['prevent_empty'] && array() === $rep['default'], 'Plantilla: repetidor sin filas por defecto, vaciable y con style_transfer => false.' );
		check_ov( array( 'template_id!' => '' ) === $rep['condition'], 'Plantilla: el repetidor sólo aparece con una plantilla elegida.' );
		$fields = array();
		foreach ( $rep['fields'] as $field ) {
			$fields[ $field['name'] ] = $field;
		}
		check_ov( array( 'slot', 'kind', 'text', 'html', 'image', 'link', 'hide_note' ) === array_values( array_diff( array_keys( $fields ), array( '_id' ) ) ), 'Plantilla: campos slot, kind, text, html, image, link y el aviso de «Ocultar».' );
		foreach ( $fields as $name => $field ) {
			check_ov( '_id' === $name || false === $field['style_transfer'], "Campo {$name}: style_transfer => false." );
		}
		check_ov( 'select' === $fields['slot']['type'] && 'select' === $fields['kind']['type'] && true === $fields['kind']['save_default'] && Apply::KINDS === array_keys( $fields['kind']['options'] ), 'slot y kind son listas; kind guarda su valor aunque sea el de por defecto.' );
		check_ov( array( 'is_external', 'nofollow' ) === $fields['link']['options'], 'link sólo ofrece is_external y nofollow (nunca custom_attributes).' );
		check_ov( array( 'kind' => 'hide' ) === $fields['hide_note']['condition'] && false !== strpos( $fields['hide_note']['raw'], 'vuelve a verse' ), '«Ocultar» avisa de la vuelta a una versión anterior (V.11).' );
		check_ov( isset( $controls[ \Digitalisimo\Elements\F7b\Overrides\Controls::NOTICE ] ) && false !== strpos( $controls[ \Digitalisimo\Elements\F7b\Overrides\Controls::NOTICE ]['raw'], 'digitalisimo-overrides-notice' ), 'Plantilla: aviso de sobrescrituras sin destino.' );
		// Cada sección de Controls::SECTIONS existe en su widget del núcleo.
		foreach ( \Digitalisimo\Elements\F7b\Overrides\Controls::SECTIONS as $type => $section ) {
			$source = (string) file_get_contents( ELEMENTOR_PATH . 'includes/widgets/' . $type . '.php' );
			check_ov( false !== strpos( $source, "'" . $section . "'" ) && false !== strpos( $source, "return '" . $type . "';" ), "La sección {$section} existe en el widget {$type} del núcleo." );
		}
		ov_static( \Elementor\Core\Frontend\Performance::class, 'is_frontend', null );
		// Un encabezado sin clave pinta igual con los controles registrados.
		ov_post( 303, 'elementor_library', array( ov_widget( 'h331', 'heading', array( 'title' => 'Igual' ) ) ) );
		ov_fresh( false );
		$plain = ov_render( 303 );
		ov_fresh( false );
		\Digitalisimo\Elements\F7b\Overrides\Controls::init();
		unset( $GLOBALS['ov']['meta'][303][ $cache_key ] );
		check_ov( $plain === ov_render( 303 ), 'Un widget sin clave pinta el mismo HTML con los controles nuevos registrados.' );

		echo "7-8 · arranque y controles: {$GLOBALS['ov_checks']} comprobaciones.\n";

		// ── 9. AJAX de sólo lectura: permisos ────────────────────────────────────────────────────────────────────────
		$ajax = 'Digitalisimo\\Elements\\F7b\\Overrides\\Ajax';
		ov_template();
		ov_post( OV_PAGE, 'page', array( ov_container( 'p', array( ov_tpl_widget( 't1', OV_TPL, ov_rows() ), ov_tpl_widget( 't2', OV_TPL ) ) ) ), 'publish', 1 );
		ov_post( 1101, 'page', array(), 'draft', 3 );
		ov_post( 600, 'elementor_library', array( ov_widget( 'h601', 'heading', array( 'title' => 'Privada', '_digitalisimo_slot' => 'p' ) ) ), 'draft', 1 );
		ov_post( 700, 'page', array( ov_widget( 'h701', 'heading', array( 'title' => 'Pagina', '_digitalisimo_slot' => 'p' ) ) ), 'publish', 1 );
		$call = function ( string $method, array $data, int $user ) use ( $ajax ) {
			$GLOBALS['ov']['user'] = $user;
			try {
				return $ajax::$method( $data );
			} catch ( \Exception $e ) {
				return 'error:' . $e->getCode();
			} finally {
				$GLOBALS['ov']['user'] = 1;
			}
		};
		check_ov( 'error:403' === $call( 'slots', array( 'editor_post_id' => OV_PAGE, 'template_id' => OV_TPL ), 2 ), 'slots: un suscriptor recibe 403.' );
		check_ov( 'error:403' === $call( 'slots', array( 'editor_post_id' => OV_PAGE, 'template_id' => OV_TPL ), 4 ), 'slots: un autor ajeno al documento recibe 403.' );
		check_ov( 'error:403' === $call( 'slots', array( 'template_id' => OV_TPL ), 1 ), 'slots: sin documento del editor, 403.' );
		check_ov( 'error:403' === $call( 'slots', array( 'editor_post_id' => 1101, 'template_id' => 600 ), 3 ), 'slots: una plantilla sin publicar que el autor no puede editar, 403.' );
		check_ov( 'error:403' === $call( 'slots', array( 'editor_post_id' => 1101, 'template_id' => 700 ), 3 ), 'slots: una entrada que no es de la biblioteca, 403.' );
		check_ov( 'error:403' === $call( 'slots', array( 'editor_post_id' => 1101, 'template_id' => 99999 ), 3 ), 'slots: una plantilla que no existe, 403 (sin distinguirla).' );
		$res = $call( 'slots', array( 'editor_post_id' => 1101, 'template_id' => (string) OV_TPL ), 3 );
		check_ov( is_array( $res ) && array( 'titulo', 'texto', 'boton', 'oculto' ) === array_column( $res['slots'], 'key' ), 'slots: el autor de su documento lee las claves de una plantilla publicada, en orden.' );
		check_ov( array( 'key' => 'titulo', 'type' => 'heading', 'count' => 1, 'label' => 'Original' ) === $res['slots'][0] && 'Texto original' === $res['slots'][1]['label'], 'slots: clave, tipo, recuento y extracto en texto plano.' );
		$res = $call( 'slots', array( 'editor_post_id' => OV_PAGE, 'template_id' => 600 ), 1 );
		check_ov( is_array( $res ) && array( 'p' ) === array_column( $res['slots'], 'key' ), 'slots: quien puede editar una plantilla sin publicar lee sus claves.' );
		ov_post( 601, 'elementor_library', array( ov_widget( 'a', 'heading', array( 'title' => str_repeat( 'Largo ', 20 ), '_digitalisimo_slot' => 'r' ) ), ov_widget( 'b', 'button', array( 'text' => '<b>x</b>', '_digitalisimo_slot' => 'r' ) ), ov_widget( 'c', 'html', array( '_digitalisimo_slot' => 'h' ) ) ) );
		$res = $call( 'slots', array( 'editor_post_id' => OV_PAGE, 'template_id' => 601 ), 1 );
		check_ov( 1 === count( $res['slots'] ) && 2 === $res['slots'][0]['count'] && 40 >= mb_strlen( $res['slots'][0]['label'] ), 'slots: una clave repetida cuenta 2 (aviso de D12) y el extracto se corta; un tipo sin clave no aparece.' );

		$usages = 'Digitalisimo\\Elements\\F7b\\Usages';
		ov_post( 1002, 'page', array( ov_tpl_widget( 'u1', OV_TPL, array( array( 'slot' => 'titulo', 'text' => 'a' ), array( 'slot' => 'titulo', 'kind' => 'link', 'link' => array( 'url' => 'https://x.test/' ) ) ) ), ov_tpl_widget( 'u2', 500, array( array( 'slot' => 'texto', 'text' => 'b' ) ) ) ), 'publish', 1 );
		ov_post( 1003, 'page', array( ov_container( 'cc', array( ov_tpl_widget( 'u3', OV_TPL, array( array( 'slot' => 'boton', 'kind' => 'hide' ) ) ) ) ) ), 'draft', 1 );
		$usages::$map = array( 't:' . OV_TPL => array( 'count' => 2, 'posts' => array( OV_PAGE, 1002 ), 'drafts' => array( 1003 ) ) );
		check_ov( 'error:403' === $call( 'uses', array( 'editor_post_id' => OV_TPL ), 2 ), 'uses: un suscriptor recibe 403.' );
		check_ov( 'error:403' === $call( 'uses', array( 'editor_post_id' => OV_TPL ), 4 ), 'uses: un autor ajeno a la plantilla recibe 403.' );
		$res = $call( 'uses', array( 'editor_post_id' => OV_TPL ), 1 );
		check_ov( true === $res['ready'] && 3 === $res['documents'] && array( 'titulo' => 2, 'texto' => 1, 'boton' => 2, 'oculto' => 1 ) === $res['slots'], 'uses: instancias por clave en los documentos de Usages::of( $plantilla, \'t\' ), una vez por instancia, borradores incluidos.' );
		check_ov( array( OV_TPL, 't' ) === end( $usages::$calls ), 'uses: lee la meta de usos del frente A con el tipo «t».' );
		$res = $call( 'uses', array( 'editor_post_id' => OV_PAGE ), 1 );
		check_ov( false === $res['ready'], 'uses: con el editor en un documento que no es plantilla no lee nada.' );
		$registered = array();
		$ajax::register( new class( $registered ) {
			private $out;
			public function __construct( &$out ) { $this->out = &$out; }
			public function register_ajax_action( $tag, $callback ) { $this->out[] = $tag; }
		} );
		check_ov( array( 'digitalisimo_f7b_slots', 'digitalisimo_f7b_slot_uses' ) === $registered, 'Las dos acciones se registran con elementor/ajax/register_actions.' );

		// ── 10. Configuración del editor ─────────────────────────────────────────────────────────────────────────────
		check_ov( array( 'digitalisimoOverrides' => array( 'library' => true ) ) === $mod::document_config( array(), OV_TPL ) && array( 'x' => 1, 'digitalisimoOverrides' => array( 'library' => false ) ) === $mod::document_config( array( 'x' => 1 ), OV_PAGE ), 'document_config(): marca las plantillas de la biblioteca.' );
		$GLOBALS['ov']['scripts'] = array();
		$GLOBALS['ov']['inline']  = array();
		$mod::enqueue();
		$script = $GLOBALS['ov']['scripts'][ $mod::HANDLE ] ?? null;
		check_ov( $script && array( 'elementor-editor' ) === $script['deps'] && '/modules/digitalisimo-f7b/assets/js/overrides-editor.js' === substr( $script['src'], -strlen( '/modules/digitalisimo-f7b/assets/js/overrides-editor.js' ) ), 'enqueue(): el script del módulo, detrás del editor.' );
		$inline = $GLOBALS['ov']['inline'][0] ?? array();
		check_ov( $mod::HANDLE === ( $inline[0] ?? '' ) && 'before' === ( $inline[2] ?? '' ) && 0 === strpos( $inline[1], 'window.digitalisimoOverrides=' ), 'enqueue(): configuración en línea antes del script.' );
		$config = json_decode( substr( $inline[1], strlen( 'window.digitalisimoOverrides=' ), -1 ), true );
		check_ov( '_digitalisimo_slot' === $config['slot'] && Apply::kinds_map() === $config['kinds'] && array( 'slots' => 'digitalisimo_f7b_slots', 'uses' => 'digitalisimo_f7b_slot_uses' ) === $config['actions'], 'Configuración: clave, kinds por tipo y acciones.' );
		foreach ( array( 'choose', 'orphanOpt', 'repeated', 'orphans1', 'orphans', 'wrongKind1', 'wrongKind', 'noSlots', 'loadError', 'invalid', 'duplicate', 'uses1', 'uses' ) as $key ) {
			check_ov( isset( $config['i18n'][ $key ] ) && '' !== $config['i18n'][ $key ], "Texto del editor «{$key}» desde PHP." );
		}

		// ── 11. Revisión estática de los archivos del frente ─────────────────────────────────────────────────────────
		$dir = F7B_ROOT . '/modules/digitalisimo-f7b/overrides/';
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$source = (string) file_get_contents( $file );
			check_ov( 0 === strpos( $source, "<?php\nnamespace Digitalisimo\\Elements\\F7b\\Overrides;" ) && false !== strpos( $source, "defined( 'ABSPATH' ) || exit;" ), basename( $file ) . ': espacio de nombres y guarda ABSPATH.' );
			check_ov( 0 === preg_match( '/\bwp_kses(_post)?\s*\(/', $source ), basename( $file ) . ': sin kses al pintar (C1-24).' );
			check_ov( 0 === preg_match( '/element_cache\/unique_id|switch_theme\(|wp_die\(/', $source ), basename( $file ) . ': sin tocar el secreto del shortcode (C1-1).' );
			preg_match_all( '/->add_control\(/', $source, $adds );
			preg_match_all( "/'style_transfer'\s*=>\s*false/", $source, $transfers );
			check_ov( count( $adds[0] ) === count( $transfers[0] ), basename( $file ) . ': cada control nuevo declara style_transfer => false (V.12).' );
		}
		$controls_src = (string) file_get_contents( $dir . 'class-controls.php' );
		$slot_block   = substr( $controls_src, strpos( $controls_src, 'Apply::SLOT,' ), 900 );
		check_ov( false === strpos( substr( $slot_block, 0, strpos( $slot_block, ');' ) ), "'default'" ), 'La clave nace sin default.' );
		$js = F7B_ROOT . '/modules/digitalisimo-f7b/assets/js/overrides-editor.js';
		check_ov( filesize( $js ) <= 8192, 'overrides-editor.js pesa ' . filesize( $js ) . ' B (≤ 8 KB).' );

		echo "OK: {$GLOBALS['ov_checks']} comprobaciones.\n";
	} catch ( Throwable $e ) {
		echo 'FALLO: ', $e->getMessage(), "\n", $e->getFile(), ':', $e->getLine(), "\n";
		exit( 1 );
	}
}
