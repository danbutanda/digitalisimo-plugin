<?php
/**
 * Entorno simulado de las pruebas de Entradas, Entradas del archivo y Portafolio propios (C5, frente I): un
 * `Widget_Base` del núcleo que guarda cada control con todos sus argumentos y dispara los ganchos de sección como el
 * núcleo (`elementor/element/{widget}/{sección}/before_section_end` y `after_section_end`), el `Skin_Base` real del
 * núcleo y lo mínimo de WordPress y de PRO para **ejecutar el código de PRO** (`modules/posts/`, `theme-builder/`) y el
 * propio y comparar sus controles. Lo cargan `tests/elements-pro-posts.php` y, para listar controles,
 * `php-wasm-cli tests/elements-pro-posts/env.php dump <widget>`.
 */
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors {
		const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent';
	}
	class Global_Typography {
		const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent';
	}
}
namespace Elementor\Core\Base {
	class Document { const TYPE_META_KEY = '_elementor_template_type'; }
}
namespace Elementor {
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SELECT2 = 'select2'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const NUMBER = 'number'; const HIDDEN = 'hidden';
		const MEDIA = 'media'; const URL = 'url'; const ICONS = 'icons'; const REPEATER = 'repeater'; const DIVIDER = 'divider'; const RAW_HTML = 'raw_html';
		const HOVER_ANIMATION = 'hover_animation'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content'; const TAB_ADVANCED = 'advanced';
	}
	foreach ( array( 'Typography' => 'typography', 'Text_Stroke' => 'text-stroke', 'Text_Shadow' => 'text-shadow', 'Css_Filter' => 'css-filter', 'Image_Size' => 'image-size', 'Box_Shadow' => 'box-shadow', 'Background' => 'background', 'Border' => 'border' ) as $class => $type ) {
		eval( 'namespace Elementor; class Group_Control_' . $class . ' { public static function get_type() { return "' . $type . '"; } }' ); // phpcs:ignore
	}
	class Utils {
		public static function validate_html_tag( $tag ) { return in_array( strtolower( (string) $tag ), array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p', 'article', 'aside', 'footer', 'header', 'main', 'nav', 'section' ), true ) ? strtolower( (string) $tag ) : 'div'; }
		public static function print_validated_html_tag( $tag ) { echo self::validate_html_tag( $tag ); }
		public static function print_unescaped_internal_string( $s ) { echo $s; }
	}
	class Icons_Manager {
		public static function render_icon( $icon, $attributes = array() ) { echo '<i aria-hidden="true" class="' . htmlspecialchars( (string) ( $icon['value'] ?? '' ), ENT_QUOTES ) . '"></i>'; return true; }
		public static function is_migration_allowed() { return true; }
	}
	/** Pila de controles del núcleo: guarda cada control con sus argumentos, como el núcleo los recibe. */
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		public $skins    = array();
		public $injected = array();
		public $tabs     = array();
		protected $_has_template_content = true; // phpcs:ignore
		private $attrs   = array();
		private $section = '';
		private $first   = true;
		public function __construct( $data = array(), $args = null ) {
			$this->register_skins();
		}
		protected function register_skins() {}
		public function get_name() { return ''; }
		public function get_id() { return 'w1d9e0a'; }
		public function add_skin( Skin_Base $skin ) { $this->skins[ $skin->get_id() ] = $skin; }
		public function get_skins() { return $this->skins; }
		public function remove_skin( $id ) { unset( $this->skins[ $id ] ); return true; }
		public function get_skin( $id ) { return $this->skins[ $id ] ?? false; }
		public function get_current_skin_id() { return $this->settings['_skin'] ?? array_key_first( $this->skins ); }
		public function get_current_skin() { return $this->get_skin( $this->get_current_skin_id() ); }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function set_settings( $key, $value = null ) { $this->settings[ $key ] = $value; }
		public function start_controls_section( $id, $args = array() ) {
			if ( $this->first && $this->skins ) {
				$this->first = false;
				$options     = array();
				if ( $this->_has_template_content ) {
					$options[''] = 'Default';
				}
				foreach ( $this->skins as $skin_id => $skin ) {
					$options[ $skin_id ] = $skin->get_title();
				}
				$this->add_control( '_skin', array( 'type' => count( $options ) > 1 ? 'select' : 'hidden', 'default' => array_key_first( $options ), 'options' => $options ) );
			}
			$this->first             = false;
			$this->sections[ $id ]   = $args;
			$this->section           = $id;
		}
		public function end_controls_section() {
			$id = $this->section;
			do_action( 'elementor/element/' . $this->get_name() . '/' . $id . '/before_section_end', $this, array() );
			$this->section = '';
			do_action( 'elementor/element/' . $this->get_name() . '/' . $id . '/after_section_end', $this, array() );
		}
		public function start_controls_tabs( $id, $args = array() ) { $this->tabs[] = $id; }
		public function start_controls_tab( $id, $args = array() ) { $this->tabs[] = $id; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function start_injection( $args ) { $this->injected[] = $args; }
		public function end_injection() {}
		public function add_control( $name, $args, $options = array() ) {
			$this->controls[ $name ] = $args + array( 'section' => $this->section );
			$this->order[]           = $name;
			return true;
		}
		public function add_responsive_control( $name, $args, $options = array() ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args = array(), $options = array() ) { $this->groups[ $args['name'] ] = array( 'type' => $type, 'section' => $this->section ) + $args; }
		public function update_control( $name, $args, $options = array() ) {
			if ( ! isset( $this->controls[ $name ] ) ) {
				return false;
			}
			$this->controls[ $name ] = ! empty( $options['recursive'] ) ? array_replace_recursive( $this->controls[ $name ], $args ) : array_merge( $this->controls[ $name ], $args );
			return true;
		}
		public function update_responsive_control( $name, $args, $options = array() ) { return $this->update_control( $name, $args, $options ); }
		public function remove_control( $name ) { unset( $this->controls[ $name ] ); }
		public function remove_responsive_control( $name ) { unset( $this->controls[ $name ] ); }
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function add_render_attribute( $element, $key = null, $value = null, $overwrite = false ) {
			if ( is_array( $key ) ) {
				foreach ( $key as $k => $v ) {
					$this->add_render_attribute( $element, $k, $v );
				}
				return $this;
			}
			foreach ( (array) $value as $v ) {
				$this->attrs[ $element ][ $key ][] = $v;
			}
			if ( array() === (array) $value ) {
				$this->attrs[ $element ][ $key ] = $this->attrs[ $element ][ $key ] ?? array();
			}
			return $this;
		}
		public function get_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $key => $values ) {
				$out[] = $key . '="' . htmlspecialchars( implode( ' ', $values ), ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $element ) { echo $this->get_render_attribute_string( $element ); }
		public function add_link_attributes( $element, array $url ) { $this->add_render_attribute( $element, 'href', $url['url'] ?? '' ); }
	}
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	require dirname( __DIR__, 2 ) . '/digitalisimo-elements/elementor/includes/base/sub-controls-stack.php';
	require dirname( __DIR__, 2 ) . '/digitalisimo-elements/elementor/includes/base/skin-base.php';
	class Plugin {
		public static $instance;
		public $editor;
		public $documents;
		public $widgets_manager;
	}
}
namespace ElementorPro\Base {
	abstract class Base_Widget extends \Elementor\Widget_Base {
		public function get_categories() { return array( 'pro-elements' ); }
	}
}
namespace ElementorPro\Core {
	class Utils {
		public static function _unstable_get_super_global_value( $g, $k ) { return $g[ $k ] ?? null; } // phpcs:ignore
		public static function get_current_post_id() { return 0; }
		public static function trim_words( $text, $length ) { return $text; }
	}
}
namespace ElementorPro {
	class Plugin {
		public static function elementor() {
			return (object) array( 'documents' => new class() { public function get_current() { return null; } }, 'editor' => new class() { public function is_edit_mode() { return false; } } );
		}
	}
}
namespace ElementorPro\Modules\LoopBuilder {
	class Module { const LOOP_POST_TAXONOMY_SKIN_ID = 'post_taxonomy'; }
}
namespace ElementorPro\Modules\Woocommerce {
	class Module { const LOOP_PRODUCT_TAXONOMY_SKIN_ID = 'product_taxonomy'; }
}
namespace ElementorPro\Modules\QueryControl\Controls {
	class Group_Control_Related { public static function get_type() { return 'related-query'; } }
}
namespace ElementorPro\Modules\QueryControl {
	class Module {
		public static $displayed_ids = array();
		public static $queries       = array();
		public static function instance() { return new self(); }
		public static function add_to_avoid_list( $ids ) { self::$displayed_ids = array_values( array_unique( array_merge( self::$displayed_ids, $ids ) ) ); }
		public function get_query( $widget, $name, $args = array(), $fallback = array() ) { self::$queries[] = array( $widget->get_name(), $name, $args ); $q = is_object( $GLOBALS['pe']['query'] ) ? clone $GLOBALS['pe']['query'] : null; if ( $q && method_exists( $q, 'rewind_posts' ) ) { $q->rewind_posts(); } return $q; }
	}
}
namespace {
	$GLOBALS['pe'] = array( 'actions' => array(), 'filters' => array(), 'query' => null, 'rtl' => false );
	function __( $v, $d = '' ) { return $v; }
	function esc_html__( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr__( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|/|\?)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function esc_url_raw( $v ) { return (string) $v; }
	function wp_kses_post( $v ) { return (string) $v; }
	function wp_kses_post_deep( $v ) { return $v; }
	function wp_unslash( $v ) { return $v; }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function is_rtl() { return $GLOBALS['pe']['rtl']; }
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
	function admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/' . $p; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['pe']['actions'][ $hook ][] = $cb; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['pe']['filters'][ $hook ][] = $cb; }
	function remove_filter( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['pe']['filters'][ $hook ] ?? array() as $i => $f ) {
			if ( $f === $cb ) {
				unset( $GLOBALS['pe']['filters'][ $hook ][ $i ] );
			}
		}
	}
	function do_action( $hook, ...$args ) {
		foreach ( $GLOBALS['pe']['actions'][ $hook ] ?? array() as $cb ) {
			call_user_func_array( $cb, $args );
		}
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['pe']['filters'][ $hook ] ?? array() as $cb ) {
			$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
		}
		return $value;
	}
	function get_taxonomies( $args = array(), $output = 'names' ) { return array( 'category' => (object) array( 'name' => 'category', 'label' => 'Categories' ), 'post_tag' => (object) array( 'name' => 'post_tag', 'label' => 'Tags' ) ); }
	function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['pe']['meta'][ $id ][ $key ] ?? ''; }
	function get_the_ID() { return $GLOBALS['pe']['post']->ID ?? 0; } // phpcs:ignore
	function check_pe( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	/** Carga los archivos de PRO y los propios (una vez). */
	function pe_load() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		$root = dirname( __DIR__, 2 ) . '/digitalisimo-elements/';
		foreach ( array( 'traits/button-widget-trait.php', 'traits/pagination-trait.php', 'traits/query-note-trait.php', 'traits/render-posts-markdown-trait.php', 'widgets/posts-base.php', 'widgets/posts.php', 'widgets/portfolio.php', 'skins/skin-base.php', 'skins/skin-classic.php', 'skins/skin-cards.php', 'skins/skin-content-base.php', 'skins/skin-full-content.php' ) as $file ) {
			require_once $root . 'modules/posts/' . $file;
		}
		foreach ( array( 'skins/posts-archive-skin-base.php', 'skins/posts-archive-skin-classic.php', 'skins/posts-archive-skin-cards.php', 'skins/posts-archive-skin-full-content.php', 'widgets/archive-posts.php' ) as $file ) {
			require_once $root . 'modules/theme-builder/' . $file;
		}
		require_once $root . 'modules/digitalisimo-loop/class-module.php';
		\Digitalisimo\Elements\Loop\Module::init();
	}

	/** Un widget recién construido con sus controles registrados (los ganchos de las pieles, sólo los suyos). */
	function pe_widget( string $class, array $settings = array() ) {
		pe_load();
		$GLOBALS['pe']['actions'] = array();
		$widget = new $class( array(), array() );
		( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
		$defaults = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$defaults[ $name ] = $control['default'];
			}
			foreach ( array( 'tablet', 'mobile' ) as $device ) {
				if ( ! empty( $control['responsive'] ) && array_key_exists( $device . '_default', $control ) ) {
					$defaults[ $name . '_' . $device ] = $control[ $device . '_default' ];
				}
			}
		}
		foreach ( $widget->groups as $name => $group ) {
			if ( 'image-size' === $group['type'] ) {
				$defaults[ $name . '_size' ] = $group['default'] ?? 'full';
			}
		}
		$widget->settings = array_merge( $defaults, $settings );
		return $widget;
	}

	/** Pares PRO ↔ propio. */
	function pe_pairs(): array {
		return array(
			'posts'         => array( '\ElementorPro\Modules\Posts\Widgets\Posts', '\Digitalisimo\Elements\Loop\Widgets\Posts' ),
			'archive-posts' => array( '\ElementorPro\Modules\ThemeBuilder\Widgets\Archive_Posts', '\Digitalisimo\Elements\Loop\Widgets\Archive_Posts' ),
			'portfolio'     => array( '\ElementorPro\Modules\Posts\Widgets\Portfolio', '\Digitalisimo\Elements\Loop\Widgets\Portfolio' ),
		);
	}

	if ( 'dump' === ( $GLOBALS['argv'][1] ?? '' ) && 'env.php' === basename( (string) ( $GLOBALS['argv'][0] ?? '' ) ) ) {
		$argv = $GLOBALS["argv"]; $pair   = pe_pairs()[ $argv[2] ?? 'posts' ];
		$widget = pe_widget( ltrim( $pair[ ( $argv[3] ?? 'pro' ) === 'pro' ? 0 : 1 ], '\\' ) );
		foreach ( $widget->controls as $name => $c ) {
			echo $name, ' ', $c['type'] ?? '', isset( $c['default'] ) ? ' =' . json_encode( $c['default'], JSON_UNESCAPED_UNICODE ) : '', "\n";
		}
		foreach ( $widget->groups as $name => $g ) {
			echo 'GROUP ', $name, ' ', $g['type'], "\n";
		}
	}
}
