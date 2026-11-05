<?php
/**
 * Entorno común de las pruebas del frente M de C7 (ficha de producto, `docs/pro/c7.md`): un Elementor y un WordPress
 * mínimos con WooCommerce simulado, en los que se cargan **a la vez** los archivos reales del Encabezado y del Botón del
 * núcleo, los widgets de la ficha de PRO (`modules/woocommerce/widgets/`) y los propios
 * (`modules/digitalisimo-woo/single/`), para comparar sus controles y pintar los dos con los mismos datos.
 *
 * WooCommerce simulado: `$GLOBALS['ws']['products']` (objetos `WC_Product` de prueba), `wc_get_template()` pinta un
 * marcado fijo por plantilla con el producto global y las plantillas de añadir al carrito disparan los mismos ganchos que
 * las de WooCommerce, para comprobar las envolturas. Lo que se compara es lo que pone el widget alrededor.
 */
namespace Elementor\Modules\ContentSanitizer\Interfaces {
	interface Sanitizable {
		public function sanitize( $content ): string;
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors {
		const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent';
	}
	class Global_Typography {
		const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent';
	}
}
namespace Elementor\Core\Utils {
	class Hints { public static function should_display_hint( $id ) { return false; } }
}
namespace Elementor\Modules\MarkdownRender {
	class Html_To_Markdown { public static function convert( $html ) { return trim( strip_tags( (string) $html ) ); } }
}
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		private $attrs   = array();
		private $section = '';
		private $tab     = '';
		public function get_name() { return 'widget'; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_style_depends(): array { return array(); }
		public function get_script_depends(): array { return array(); }
		public function show_in_panel() { return true; }
		public function wrapper_class() { return $this->get_html_wrapper_class(); }
		protected function get_html_wrapper_class() { return 'elementor-widget-' . $this->get_name(); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args + array( 'tab' => 'content' ); $this->section = $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name ) { $this->order[] = 'tabs:' . $name; }
		public function start_controls_tab( $name, $args ) { $this->tab = $name; $this->order[] = 'tab:' . $name; }
		public function end_controls_tab() { $this->tab = ''; }
		public function end_controls_tabs() {}
		public function add_control( $name, $args, $options = array() ) { $this->controls[ $name ] = $args + array( 'section' => $this->section, 'in_tab' => $this->tab ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args, $options = array() ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args, $options = array() ) { $this->groups[ $args['name'] ] = array( 'type' => $type, 'section' => $this->section ) + $args; $this->order[] = 'group:' . $args['name']; }
		public function __construct( $data = array(), $args = null ) {}
		public function update_responsive_control( $name, $args, $options = array() ) { $this->update_control( $name, $args, $options ); }
		public function set_settings( $key, $value = null ) { if ( is_array( $key ) ) { $this->settings = $key; } else { $this->settings[ $key ] = $value; } }
		public function get_id() { return 'abc1234'; }
		public function get_data( $key = null ) { return null; }
		public function get_default_args( $key = null ) { return null; }
		public function add_link_attributes_x() {}
		public function attrs() { return $this->attrs; }
		public function reset_attrs() { $this->attrs = array(); }
		public function update_control( $name, $args, $options = array() ) {
			$merge = ! empty( $options['recursive'] ) ? 'array_replace_recursive' : 'array_merge';
			if ( isset( $this->controls[ $name ] ) ) {
				$this->controls[ $name ] = $merge( $this->controls[ $name ], $args );
			} elseif ( isset( $this->sections[ $name ] ) ) {
				$this->sections[ $name ] = $merge( $this->sections[ $name ], $args );
			} elseif ( isset( $this->controls[ $name . '_size' ] ) || isset( $this->groups[ preg_replace( '/_size$/', '', $name ) ] ) ) {
				$group = preg_replace( '/_size$/', '', $name );
				$this->groups[ $group ] = $merge( $this->groups[ $group ], $args );
			} else {
				throw new \RuntimeException( "update_control de un control que no existe: $name" );
			}
		}
		public function remove_control( $name ) { unset( $this->controls[ $name ] ); $this->order = array_values( array_diff( $this->order, array( $name ) ) ); }
		public function add_inline_editing_attributes( $key, $toolbar = 'basic' ) {}
		public function add_render_attribute( $element, $key = null, $value = null, $overwrite = false ) {
			if ( is_array( $element ) ) {
				foreach ( $element as $el => $attrs ) {
					$this->add_render_attribute( $el, $attrs );
				}
				return;
			}
			if ( is_array( $key ) ) {
				foreach ( $key as $k => $v ) {
					$this->add_render_attribute( $element, $k, $v );
				}
				return;
			}
			foreach ( (array) $value as $v ) {
				$this->attrs[ $element ][ $key ][] = $v;
			}
		}
		public function get_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $key => $values ) {
				$out[] = $key . '="' . htmlspecialchars( implode( ' ', $values ), ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $element ) { echo $this->get_render_attribute_string( $element ); }
		public function add_link_attributes( $element, array $url, $overwrite = false ) {
			$this->add_render_attribute( $element, 'href', $url['url'] );
			if ( ! empty( $url['is_external'] ) ) {
				$this->add_render_attribute( $element, 'target', '_blank' );
			}
			if ( ! empty( $url['nofollow'] ) ) {
				$this->add_render_attribute( $element, 'rel', 'nofollow' );
			}
		}
		public function add_lightbox_data_attributes( $element, $id = null, $open = null, $slideshow = null, $overwrite = false ) {
			$this->add_render_attribute( $element, 'data-elementor-open-lightbox', $open ? $open : 'default' );
			if ( $id ) {
				$this->add_render_attribute( $element, 'data-elementor-lightbox-id', (string) $id );
			}
		}
		public function print_unescaped_setting( $key ) { echo $this->settings[ $key ] ?? ''; }
	}
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const NUMBER = 'number';
		const MEDIA = 'media'; const URL = 'url'; const ICONS = 'icons'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
		const DIVIDER = 'divider'; const ALERT = 'alert'; const BUTTON = 'button'; const RAW_HTML = 'raw_html'; const HOVER_ANIMATION = 'hover_animation';
		const HIDDEN = 'hidden'; const POPOVER_TOGGLE = 'popover_toggle'; const TAB_ADVANCED = 'advanced'; const SELECT2 = 'select2'; const WYSIWYG = 'wysiwyg'; const NOTICE = 'notice';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Css_Filter { public static function get_type() { return 'css-filter'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Image_Size {
		public static function get_type() { return 'image-size'; }
		public static function print_attachment_image_html( array $settings, $key = 'image' ) {
			$image = $settings[ $key ];
			$class = ! empty( $settings['hover_animation'] ) ? 'elementor-animation-' . $settings['hover_animation'] : '';
			if ( ! empty( $image['id'] ) ) {
				echo wp_get_attachment_image( $image['id'], $settings[ $key . '_size' ] ?? 'large', false, array( 'class' => trim( $class . ' attachment-x' ) ) );
				return;
			}
			echo '<img src="' . htmlspecialchars( (string) ( $image['url'] ?? '' ), ENT_QUOTES, 'UTF-8' ) . '" alt="' . htmlspecialchars( (string) ( $image['alt'] ?? '' ), ENT_QUOTES, 'UTF-8' ) . '" />';
		}
	}
	class Utils {
		public static function get_placeholder_image_src() { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png'; }
		public static function has_pro() { return true; }
		public static function validate_html_tag( $tag ) { return in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ? $tag : 'div'; }
		public static function html_to_plain_text( $html ) { return trim( strip_tags( (string) $html ) ); }
		public static function is_empty( $v ) { return '' === $v || null === $v; }
	}
	/** Estado del «núcleo» de la prueba. */
	class Plugin {
		public static $instance;
		public $experiments;
		public $editor;
		public $dynamic_tags;
		public $controls_manager;
		public $documents;
		public $preview;
		public $frontend;
		public static function instance() { return self::$instance; }
	}
	class Test_State {
		public $optimized = false;
		public $edit      = false;
		public $preview   = 0;
		public $docs      = array();
		public $builder   = array();
		public $calls     = array();
		public function is_feature_active( $f ) { return 'e_optimized_markup' === $f && $this->optimized; }
		public function is_edit_mode() { return $this->edit; }
		public function set_edit_mode( $v ) { $this->calls[] = 'edit:' . ( $v ? 1 : 0 ); $this->edit = (bool) $v; }
		public function tag_data_to_tag_text( $id, $name, $settings = array() ) { return '[elementor-tag id="" name="' . $name . '" settings="%7B%7D"]'; }
		public function get_tag_data_content( $id, $name ) { return 'site-url' === $name ? 'https://sitio.test/' : ( 'site-logo' === $name ? array( 'id' => 12, 'url' => 'https://sitio.test/wp-content/uploads/logo.png' ) : null ); }
		public function get_control( $type ) { return 'media-preview' === $type ? $this : null; }
		public function get( $id ) { return $this->docs[ $id ] ?? null; }
		public $current = null;
		public function get_current() { return $this->current; }
		public function is_preview_mode( $id = 0 ) { return $this->preview && ( ! $id || (int) $id === $this->preview ); }
		public function builder_wrapper( $c ) { return '<div class="elementor-builder-wrapper">' . $c . '</div>'; }
		public function get_builder_content( $id, $css = false ) { $this->calls[] = 'builder:' . $id . ':' . ( $css ? 1 : 0 ); return $this->builder[ $id ] ?? ''; }
		public function remove_content_filter() { $this->calls[] = 'remove_content_filter'; }
		public function add_content_filter() { $this->calls[] = 'add_content_filter'; }
		public function remove_content_filters() { $this->calls[] = 'remove_content_filters'; }
		public function restore_content_filters() { $this->calls[] = 'restore_content_filters'; }
	}
	class Test_Document {
		public $settings;
		public function __construct( array $settings = array() ) { $this->settings = $settings; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function is_built_with_elementor() { return ! empty( $this->settings['built'] ); }
		public function get_type() { return $this->settings['type'] ?? 'wp-page'; }
	}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace ElementorPro\Base {
	trait Base_Widget_Trait {
		public function is_editable() { return true; }
		public function get_categories() { return array( 'pro-elements' ); }
	}
	trait On_Import_Trait {}
	abstract class Base_Widget extends \Elementor\Widget_Base {
		use Base_Widget_Trait;
		use On_Import_Trait;
		public function render_markdown(): string { return ''; }
	}
	class Markdown_Utils {
		public static function heading( string $text, string $tag, int $default = 2 ): string { $l = array( 'h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6 )[ $tag ] ?? $default; return str_repeat( '#', $l ) . ' ' . $text; }
		public static function plain_text( $t ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $t ) ) ); }
		public static function bullet_list( array $lines ) { return implode( "\n", $lines ); }
		public static function image( string $url, string $alt = '' ): string { return '![' . ( '' !== trim( $alt ) ? $alt : 'image' ) . '](' . $url . ')'; }
	}
}
namespace ElementorPro\Modules\ThemeBuilder\Classes {
	class Control_Media_Preview { const CONTROL_TYPE = 'media-preview'; }
}
namespace ElementorPro\Modules\ThemeBuilder\Documents {
	class Theme_Document extends \Elementor\Test_Document {}
}
namespace ElementorPro\Modules\ThemeBuilder {
	class Module {
		private static $instance;
		public static function instance() { return self::$instance ?? ( self::$instance = new self() ); }
		public function get_document( $id ) { $d = \Elementor\Plugin::$instance->documents->get( $id ); return $d instanceof Documents\Theme_Document ? $d : null; }
	}
}
namespace Elementor {
	class Icons_Manager {
		public static function is_migration_allowed() { return true; }
		public static function render_icon( $icon, $attributes = array(), $tag = 'i' ) {
			if ( empty( $icon['value'] ) ) {
				return false;
			}
			echo '<svg class="e-font-icon-svg" data-icon="' . htmlspecialchars( (string) $icon['value'], ENT_QUOTES, 'UTF-8' ) . '" aria-hidden="true"></svg>';
			return true;
		}
	}
	class Controls_Stack { const RESPONSIVE_DESKTOP = 'desktop'; const RESPONSIVE_TABLET = 'tablet'; const RESPONSIVE_MOBILE = 'mobile'; }
}
namespace Elementor\Core\Breakpoints {
	class Manager { public static function get_default_config() { return array( 'mobile' => array(), 'tablet' => array() ); } }
}
namespace Elementor\Modules\Promotions\Controls {
	class Promotion_Control { const TYPE = 'promotion_control'; }
}
namespace Elementor\Core\Admin {
	class Admin_Notices { public static function add_plg_campaign_data( $url, $data ) { return $url; } }
}
namespace ElementorPro\Core {
	class Utils {
		public static function _unstable_get_super_global_value( $a, $k ) { return $a[ $k ] ?? null; }
		public static function get_public_post_types( $args = array() ) { return array( 'post' => 'Entradas', 'page' => 'Páginas', 'product' => 'Productos' ); }
	}
}
namespace ElementorPro\Core\Utils {
	class Hints {}
}
namespace ElementorPro\Modules\QueryControl {
	class Module { const QUERY_CONTROL_ID = 'query'; const QUERY_OBJECT_POST = 'post'; }
}
namespace ElementorPro\Modules\Woocommerce\Classes {
	class Products_Renderer { const DEFAULT_COLUMNS_AND_ROWS = 4; }
}
namespace ElementorPro\Modules\CollectionLoop\Utils {
	class Loop_Iteration_Context { public static function is_v4_collection_loop_active() { return false; } }
}
namespace ElementorPro\Modules\Posts\Skins {
	trait Skin_Content_Base_Stub {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ELEMENTOR_ASSETS_URL', 'https://sitio.test/a/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__, 2 ) . '/digitalisimo-elements/pro-elements.php' );
	$GLOBALS['tt'] = array( 'posts' => array(), 'post' => null, 'password' => array(), 'meta' => array(), 'captions' => array(), 'actions' => array(), 'blog' => 'Sitio de prueba', 'rtl' => false );
	$GLOBALS['ws'] = array( 'products' => array(), 'product_id' => 0, 'options' => array(), 'filters' => array(), 'templates' => array(), 'post_type' => 'product', 'caps' => array( 'manage_options' => true ), 'action' => array(), 'comments' => true, 'queried' => 0 );

	/** Producto de prueba con lo que leen los widgets de la ficha. */
	class WC_Product {
		public $data;
		public function __construct( array $data ) {
			$this->data = $data + array( 'id' => 0, 'type' => 'simple', 'name' => 'Producto', 'sku' => '', 'status' => 'publish', 'cats' => array(), 'tags' => array(), 'purchasable' => true, 'in_stock' => true, 'ajax' => true, 'min' => 1, 'max' => -1, 'description' => '', 'short' => '', 'upsells' => array() );
		}
		public function get_id() { return $this->data['id']; }
		public function get_type() { return $this->data['type']; }
		public function is_type( $t ) { return in_array( $this->data['type'], (array) $t, true ); }
		public function get_name() { return $this->data['name']; }
		public function get_sku() { return $this->data['sku']; }
		public function get_status() { return $this->data['status']; }
		public function get_category_ids() { return array_keys( $this->data['cats'] ); }
		public function get_tag_ids() { return array_keys( $this->data['tags'] ); }
		public function is_purchasable() { return $this->data['purchasable']; }
		public function is_in_stock() { return $this->data['in_stock']; }
		public function supports( $f ) { return 'ajax_add_to_cart' === $f && $this->data['ajax']; }
		public function add_to_cart_url() { return '?add-to-cart=' . $this->data['id']; }
		public function get_min_purchase_quantity() { return $this->data['min']; }
		public function get_max_purchase_quantity() { return $this->data['max']; }
		public function get_description() { return $this->data['description']; }
		public function get_short_description() { return $this->data['short']; }
		public function get_upsell_ids() { return $this->data['upsells']; }
	}
	function ws_product( array $data ): WC_Product { $p = new WC_Product( $data ); $GLOBALS['ws']['products'][ $p->get_id() ] = $p; return $p; }
	function wc_get_product( $id = false ) {
		$id = false === $id || null === $id || '' === $id ? $GLOBALS['ws']['product_id'] : (int) $id;
		return $GLOBALS['ws']['products'][ $id ] ?? false;
	}

	// WordPress.
	function __( $v, $d = '' ) { return $v; }
	function esc_html__( $v, $d = '' ) { return esc_html( $v ); }
	function esc_attr__( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return apply_filters( 'esc_html', htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ), (string) $v ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|\?)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function is_rtl() { return $GLOBALS['tt']['rtl']; }
	function wp_kses_post( $v ) { return preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ); }
	function wp_kses( $v, $allowed = array() ) { return wp_kses_post( $v ); }
	function wp_kses_allowed_html( $c = '' ) { return array(); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
	function get_bloginfo( $k ) { return $GLOBALS['tt']['blog']; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['tt']['meta'][ $id ][ $key ] ?? ''; }
	function get_the_ID() { return $GLOBALS['tt']['post'] ? $GLOBALS['tt']['post']->ID : $GLOBALS['ws']['product_id']; }
	function get_post( $id = null ) { return null === $id ? $GLOBALS['tt']['post'] : ( $GLOBALS['tt']['posts'][ (int) $id ] ?? null ); }
	function get_post_type( $p = null ) { return $GLOBALS['ws']['post_type']; }
	function post_type_supports( $type, $feature ) { return $GLOBALS['ws']['comments']; }
	function post_password_required( $id ) { return ! empty( $GLOBALS['tt']['password'][ $id ] ); }
	function get_the_password_form( $id ) { return '<form class="post-password-form" data-post="' . (int) $id . '"></form>'; }
	function setup_postdata( $post ) { $GLOBALS['tt']['setup'] = is_object( $post ) ? $post->ID : (int) $post; return true; }
	function get_the_content() { $p = $GLOBALS['tt']['posts'][ $GLOBALS['tt']['setup'] ?? 0 ] ?? $GLOBALS['tt']['post']; return $p ? $p->post_content : ''; }
	function wp_link_pages( $args ) {}
	function get_option( $k, $d = false ) { return $GLOBALS['ws']['options'][ $k ] ?? $d; }
	function current_user_can( $c ) { return ! empty( $GLOBALS['ws']['caps'][ $c ] ); }
	function get_queried_object_id() { return $GLOBALS['ws']['queried']; }
	function wp_doing_ajax() { return false; }
	function current_action() { return end( $GLOBALS['ws']['action'] ) ?: ''; }
	function current_theme_supports( $f ) { return false; }
	function get_the_term_list( $id, $tax, $before = '', $sep = '', $after = '' ) {
		$p     = wc_get_product( $id );
		$terms = 'product_cat' === $tax ? $p->data['cats'] : $p->data['tags'];
		$out   = array();
		foreach ( $terms as $tid => $name ) {
			$out[] = '<a href="https://sitio.test/' . $tax . '/' . $tid . '/" rel="tag">' . htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ) . '</a>';
		}
		return $before . implode( $sep, $out ) . $after;
	}
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['ws']['filters'][ $hook ][ $priority ][] = $cb; $GLOBALS['tt']['actions'][] = array( $hook, $priority ); return true; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { return add_action( $hook, $cb, $priority, $args ); }
	function remove_action( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['ws']['filters'][ $hook ][ $priority ] ?? array() as $i => $f ) {
			if ( $f === $cb ) {
				unset( $GLOBALS['ws']['filters'][ $hook ][ $priority ][ $i ] );
			}
		}
		return true;
	}
	function remove_filter( $hook, $cb, $priority = 10 ) { return remove_action( $hook, $cb, $priority ); }
	function ws_callbacks( $hook ) {
		$all = $GLOBALS['ws']['filters'][ $hook ] ?? array();
		ksort( $all );
		$out = array();
		foreach ( $all as $list ) {
			foreach ( $list as $cb ) {
				$out[] = $cb;
			}
		}
		return $out;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['ws']['action'][] = $hook;
		foreach ( ws_callbacks( $hook ) as $cb ) {
			call_user_func_array( $cb, $args );
		}
		array_pop( $GLOBALS['ws']['action'] );
	}
	/** `the_content` como en la prueba de C4; los demás filtros, los enganchados. */
	function apply_filters( $hook, $value, ...$args ) {
		if ( 'the_content' === $hook ) {
			return '<div class="contenido">' . $value . '</div>';
		}
		foreach ( ws_callbacks( $hook ) as $cb ) {
			$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
		}
		return $value;
	}
	function __return_empty_string() { return ''; }
	function WC() { return (object) array( 'version' => '11.2.1' ); }

	// WooCommerce: plantillas y funciones que usan los widgets de la ficha.
	function wc_get_template( $name, $args = array() ) {
		global $product;
		$name = ltrim( (string) $name, '/' );
		$GLOBALS['ws']['templates'][] = $name;
		$id   = $product ? $product->get_id() : 0;
		echo '<div class="wc-tpl" data-tpl="' . $name . '" data-product="' . $id . '">' . $name . '</div>';
	}
	function wc_get_stock_html( $product ) { return apply_filters( 'woocommerce_get_stock_html', '<p class="stock in-stock">' . (int) $product->get_id() . ' disponibles</p>', $product ); }
	function wc_product_sku_enabled() { return true; }
	function woocommerce_template_single_add_to_cart() {
		global $product;
		echo '<form class="cart" data-type="' . $product->get_type() . '">';
		if ( in_array( $product->get_type(), array( 'simple', 'variable' ), true ) ) {
			do_action( 'woocommerce_before_add_to_cart_quantity' );
			echo '<div class="quantity"><input class="input-text qty text" value="1"></div>';
			do_action( 'woocommerce_after_add_to_cart_quantity' );
		}
		do_action( 'woocommerce_before_add_to_cart_button' );
		echo '<button type="submit" class="single_add_to_cart_button button alt">' . esc_html( apply_filters( 'woocommerce_product_single_add_to_cart_text', 'Add to cart', $product ) ) . '</button>';
		do_action( 'woocommerce_after_add_to_cart_button' );
		echo '</form>';
	}
	function woocommerce_template_loop_add_to_cart( $args = array() ) {
		global $product;
		echo '<a href="' . $product->add_to_cart_url() . '" data-quantity="' . (int) ( $args['quantity'] ?? 1 ) . '" class="button product_type_' . $product->get_type() . ' add_to_cart_button ajax_add_to_cart">Add to cart</a>';
	}
	function woocommerce_quantity_input( $args = array() ) { echo '<div class="quantity"><input class="' . implode( ' ', $args['classes'] ?? array() ) . '" min="' . (int) $args['min_value'] . '" value="' . (int) $args['input_value'] . '"></div>'; }
	function woocommerce_output_product_data_tabs() { $tabs = apply_filters( 'woocommerce_product_tabs', array( 'description' => 1, 'additional_information' => 1, 'reviews' => 1 ) ); echo '<div class="woocommerce-tabs" data-tabs="' . implode( ',', array_keys( $tabs ) ) . '"></div>'; }
	function woocommerce_template_single_title() { echo '<h1 class="product_title entry-title">' . wc_get_product()->get_name() . '</h1>'; }
	function woocommerce_template_single_rating() { echo '<div class="woocommerce-product-rating">4,5</div>'; }
	function woocommerce_template_single_price() { echo '<p class="price">10</p>'; }
	function woocommerce_template_single_excerpt() { echo '<div class="woocommerce-product-details__short-description">Corta</div>'; }
	function woocommerce_template_single_meta() { echo '<div class="product_meta">SKU</div>'; }
	function woocommerce_template_single_sharing() { echo '<div class="sharing"></div>'; }
	function woocommerce_show_product_sale_flash() { echo '<span class="onsale">Oferta</span>'; }
	function woocommerce_product_additional_information_tab() { echo '<table class="shop_attributes"></table>'; }
	function woocommerce_upsell_display() { echo '<section class="up-sells"></section>'; }

	function check_ws( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}

	$ws_root = dirname( __DIR__, 2 ) . '/digitalisimo-elements/';
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	$ws_state = new \Elementor\Test_State();
	foreach ( array( 'experiments', 'editor', 'dynamic_tags', 'controls_manager', 'documents', 'preview', 'frontend' ) as $ws_prop ) {
		\Elementor\Plugin::$instance->$ws_prop = $ws_state;
	}
	$GLOBALS['ws_state'] = $ws_state;

	// Núcleo, PRO y los propios (los archivos reales).
	require $ws_root . 'elementor/includes/widgets/heading.php';
	require $ws_root . 'elementor/includes/widgets/traits/button-trait.php';
	require $ws_root . 'elementor/includes/widgets/button.php';
	require $ws_root . 'modules/posts/skins/skin-content-base.php';
	require $ws_root . 'modules/theme-builder/widgets/post-content.php';
	require $ws_root . 'modules/woocommerce/traits/product-id-trait.php';
	require $ws_root . 'modules/woocommerce/widgets/base-widget.php';
	foreach ( array( 'product-title', 'product-price', 'product-rating', 'product-stock', 'product-meta', 'product-short-description', 'product-content', 'product-additional-information', 'product-data-tabs', 'product-images', 'product-add-to-cart', 'single-elements', 'add-to-cart' ) as $ws_file ) {
		require $ws_root . 'modules/woocommerce/widgets/' . $ws_file . '.php';
	}
	require $ws_root . 'modules/digitalisimo-theme-elements/class-module.php';
	\Digitalisimo\Elements\Theme_Elements\Module::init();
	require $ws_root . 'modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();
	require $ws_root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();

	/** Ruta de un archivo del plugin. */
	function ws_path( string $relative ): string {
		return dirname( __DIR__, 2 ) . '/digitalisimo-elements/' . ltrim( $relative, '/' );
	}

	/** Un widget con sus controles registrados. */
	function ws_widget( string $class ) {
		$widget = new $class();
		( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
		return $widget;
	}

	/** Valores por defecto de los controles (como `get_settings()` sin nada guardado). */
	function ws_defaults( $widget ): array {
		$out = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$out[ $name ] = $control['default'];
			} elseif ( in_array( $control['type'] ?? '', array( 'text', 'textarea', 'select', 'switcher', 'choose', 'color', 'hidden', 'number', 'query' ), true ) ) {
				$out[ $name ] = '';
			}
		}
		return $out;
	}

	/** Pinta un widget (su `render()`) con sus valores por defecto más `$settings`. */
	function ws_render( $widget, array $settings = array() ): string {
		$widget->reset_attrs();
		$widget->settings = array_merge( ws_defaults( $widget ), $settings );
		$GLOBALS['ws']['templates'] = array();
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	}

	/** Marcado normalizado para comparar el de PRO y el propio: espacios y las clases que cada uno pone a su nombre. */
	function ws_norm( string $html, array $classes = array() ): string {
		$html = strtr( $html, $classes );
		return trim( preg_replace( '/>\s+</', '><', preg_replace( '/\s+/', ' ', $html ) ) );
	}

	/** Partes de un control que deben coincidir con las de PRO (las demás son de presentación). */
	const WS_SAME = array( 'type', 'default', 'condition', 'conditions', 'size_units', 'range', 'responsive', 'render_type', 'return_value', 'global', 'exclude', 'fields_options', 'multiple', 'autocomplete', 'min', 'max', 'step', 'label_block', 'alpha', 'separator', 'tab', 'dynamic' );

	/** Selectores de PRO con sus clases traducidas a las propias, para comparar con los propios. */
	function ws_selectors( array $selectors, array $classes ): array {
		$out = array();
		foreach ( $selectors as $selector => $rule ) {
			$out[ strtr( (string) $selector, $classes ) ] = $rule;
		}
		return $out;
	}

	/**
	 * Compara los controles (y grupos) de dos widgets ya registrados: nombre, orden, sección, pestaña y las partes de
	 * `WS_SAME`, las claves de `options`, `prefix_class` (con las clases de PRO traducidas), `selectors` y `selector`
	 * (también traducidos). `$skip`: `nombre` o `nombre.parte` declarados por la prueba. Devuelve las diferencias.
	 */
	function ws_compare( $pro, $own, array $classes = array(), array $skip = array() ): array {
		$diff = array();
		foreach ( $pro->sections as $name => $section ) {
			if ( ! isset( $own->sections[ $name ] ) ) {
				$diff[] = "sección $name: falta";
			} elseif ( ( $section['tab'] ?? 'content' ) !== ( $own->sections[ $name ]['tab'] ?? 'content' ) || ( $section['condition'] ?? null ) !== ( $own->sections[ $name ]['condition'] ?? null ) ) {
				$diff[] = "sección $name: pestaña o condición";
			}
		}
		foreach ( array_diff_key( $own->sections, $pro->sections ) as $name => $section ) {
			$diff[] = "sección $name: sólo en el propio";
		}
		foreach ( array( 'controls', 'groups' ) as $kind ) {
			foreach ( $pro->$kind as $name => $control ) {
				if ( isset( $skip[ $name ] ) ) {
					continue;
				}
				$mine = $own->$kind[ $name ] ?? null;
				if ( null === $mine ) {
					$diff[] = "$name: falta";
					continue;
				}
				foreach ( array_unique( array_merge( array_keys( $control ), array_keys( $mine ) ) ) as $key ) {
					if ( isset( $skip[ "$name.$key" ] ) ) {
						continue;
					}
					if ( ( in_array( $key, WS_SAME, true ) || preg_match( '/_default$/', (string) $key ) || 'section' === $key || 'in_tab' === $key ) && ( $control[ $key ] ?? null ) !== ( $mine[ $key ] ?? null ) ) {
						$diff[] = "$name.$key: PRO " . json_encode( $control[ $key ] ?? null, JSON_UNESCAPED_UNICODE ) . ' · propio ' . json_encode( $mine[ $key ] ?? null, JSON_UNESCAPED_UNICODE );
					}
				}
				if ( isset( $control['options'] ) && array_map( 'strval', array_keys( (array) $control['options'] ) ) !== array_map( 'strval', array_keys( (array) ( $mine['options'] ?? array() ) ) ) && ! isset( $skip[ "$name.options" ] ) ) {
					$diff[] = "$name.options";
				}
				if ( ( isset( $control['prefix_class'] ) ? strtr( $control['prefix_class'], $classes ) : null ) !== ( $mine['prefix_class'] ?? null ) && ! isset( $skip[ "$name.prefix_class" ] ) ) {
					$diff[] = "$name.prefix_class: PRO " . ( $control['prefix_class'] ?? '-' ) . ' · propio ' . ( $mine['prefix_class'] ?? '-' );
				}
				if ( ws_selectors( (array) ( $control['selectors'] ?? array() ), $classes ) !== (array) ( $mine['selectors'] ?? array() ) && ! isset( $skip[ "$name.selectors" ] ) ) {
					$diff[] = "$name.selectors";
				}
				if ( strtr( (string) ( $control['selector'] ?? '' ), $classes ) !== (string) ( $mine['selector'] ?? '' ) && ! isset( $skip[ "$name.selector" ] ) ) {
					$diff[] = "$name.selector";
				}
				if ( ( $control['selectors_dictionary'] ?? null ) !== ( $mine['selectors_dictionary'] ?? null ) && ! isset( $skip[ "$name.selectors_dictionary" ] ) ) {
					$diff[] = "$name.selectors_dictionary";
				}
			}
			foreach ( array_diff_key( $own->$kind, $pro->$kind ) as $name => $control ) {
				if ( ! isset( $skip[ $name ] ) ) {
					$diff[] = "$name: sólo en el propio";
				}
			}
		}
		$order = static function ( $w, $other ) use ( $skip ) {
			return array_values( array_filter( $w->order, static function ( $n ) use ( $other, $skip ) { $b = preg_replace( '/^(group|tab|tabs):/', '', $n ); return ! isset( $skip[ $b ] ) && ( isset( $other->controls[ $b ] ) || isset( $other->groups[ $b ] ) || 0 === strpos( $n, 'tab' ) ); } ) );
		};
		if ( $order( $pro, $own ) !== $order( $own, $pro ) && ! isset( $skip['@order'] ) ) {
			$diff[] = 'orden de los controles distinto';
		}
		return $diff;
	}

	/** Lo que el empaquetado veta en DIGITALÍSIMO Elements (scripts/build-packages.sh). */
	const WS_VETOED = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';

	/** Código PHP sin comentarios. */
	function ws_code( string $file ): string {
		$out = '';
		foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
			$out .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
		}
		return $out;
	}

	/**
	 * Convierte widgets de PRO con su mapa (borradores incluidos): cada clave cae en un control o grupo propio, en una
	 * clave común o en las bolsas `__globals__`/`__dynamic__`, sin `pro.unmapped`, y lo convertido no se convierte otra vez.
	 *
	 * @param string[] $extra Claves admitidas sin control propio (p. ej. `icon`, el icono FA4 que pinta el botón).
	 * @return array{0: array<string, array>, 1: string[]} Ajustes convertidos por id y códigos `id:código:clave`.
	 */
	function ws_convert( string $pro, $own, array $cases, array $extra = array() ): array {
		$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
		foreach ( $cases as $id => $settings ) {
			$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $pro, 'settings' => $settings, 'elements' => array() );
		}
		$log       = array();
		$converted = \Digitalisimo\Elements\Convert\Converter::tree( $tree, $log, true );
		check_ws( count( $cases ) === count( $log ), "$pro: se convierte cada widget del árbol." );
		$lands = static function ( $key ) use ( $own, $extra ) {
			$base = \Digitalisimo\Elements\Convert\Translator::base( (string) $key );
			if ( isset( $own->controls[ $base ] ) || in_array( $base, $extra, true ) || \Digitalisimo\Elements\Convert\Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', '__fa4_migrated', \Digitalisimo\Elements\Convert\Translator::MARKER ), true ) ) {
				return true;
			}
			foreach ( array_keys( $own->groups ) as $group ) {
				if ( 0 === strpos( (string) $key, $group . '_' ) ) {
					return true;
				}
			}
			return false;
		};
		$out = array();
		foreach ( $converted[0]['elements'] as $element ) {
			check_ws( $own->get_name() === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa al widget propio con el mismo id.' );
			$out[ $element['id'] ] = $element['settings'];
			foreach ( $element['settings'] as $key => $value ) {
				check_ws( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
					check_ws( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
				}
			}
		}
		$codes = array();
		foreach ( $log as $entry ) {
			foreach ( $entry['codes'] as $code ) {
				$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
			}
		}
		check_ws( ! preg_grep( '/:pro\.unmapped:/', $codes ), "$pro: claves sin declarar en el mapa: " . implode( ', ', preg_grep( '/:pro\.unmapped:/', $codes ) ) );
		check_ws( $converted === \Digitalisimo\Elements\Convert\Converter::tree( $converted, $again, true ) && array() === $again, "$pro: lo convertido no se convierte otra vez." );
		return array( $out, $codes );
	}
}
