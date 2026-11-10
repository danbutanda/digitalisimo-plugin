<?php
/**
 * Entorno común de las pruebas del frente H de C4 (`tests/elements-pro-<widget>.php`, contrato docs/pro/c4.md): un
 * Elementor y un WordPress mínimos en los que se cargan **a la vez** el widget de PRO (`modules/theme-elements/widgets/`)
 * y el propio (`modules/digitalisimo-theme-elements/widgets/`), para comparar sus controles uno a uno
 * (`h_compare_controls()`) y pintar los dos con los mismos ajustes (`h_render()`).
 *
 * Cada prueba define antes de incluir este archivo las funciones de WordPress que su widget necesita con un valor propio
 * (aquí sólo se definen las que faltan) y ajusta `$GLOBALS['h_wp']` (opciones del entorno: `features`, `edit_mode`,
 * `preview_mode`, `taxonomies`, `post_types`, `rtl`, `home`).
 */
namespace {
	require_once dirname( __DIR__ ) . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		public $tabs     = array();
		public $id       = 'abc1234';
		public $attrs    = array();
		private $section = '';
		private $tab     = '';
		public function get_id() { return $this->id; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_active_settings() { return $this->settings; }
		public function set_settings( $key, $value = null ) { $this->settings[ $key ] = $value; }
		public function start_controls_section( $name, $args = array() ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name, $args = array() ) { $this->tabs[ $name ] = $args; }
		public function start_controls_tab( $name, $args = array() ) { $this->tabs[ $name ] = $args; $this->tab = $name; }
		public function end_controls_tab() { $this->tab = ''; }
		public function end_controls_tabs() {}
		public function start_injection( $args = array() ) {}
		public function end_injection() {}
		public function add_control( $name, $args, $options = array() ) {
			$this->controls[ $name ] = $args + array( 'section' => $this->section, 'tab_name' => $this->tab );
			$this->order[]           = $name;
			return true;
		}
		public function add_responsive_control( $name, $args, $options = array() ) { return $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args = array(), $options = array() ) {
			$this->groups[ $args['name'] ] = array( 'type' => $type, 'section' => $this->section, 'tab_name' => $this->tab ) + $args;
			$this->order[]                 = '@' . $args['name'];
		}
		public function update_control( $name, $args, $options = array() ) { $this->controls[ $name ] = array_merge( $this->controls[ $name ] ?? array(), $args ); }
		public function update_responsive_control( $name, $args, $options = array() ) { $this->update_control( $name, $args ); }
		public function remove_control( $name ) { unset( $this->controls[ $name ] ); }
		public function remove_responsive_control( $name ) { unset( $this->controls[ $name ] ); }
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function add_render_attribute( $element, $key = null, $value = null, $overwrite = false ) {
			if ( is_array( $element ) ) {
				foreach ( $element as $el => $attrs ) {
					$this->add_render_attribute( $el, $attrs, null, $overwrite );
				}
				return $this;
			}
			if ( is_array( $key ) ) {
				foreach ( $key as $k => $v ) {
					$this->add_render_attribute( $element, $k, $v, $overwrite );
				}
				return $this;
			}
			if ( $overwrite ) {
				$this->attrs[ $element ][ $key ] = array();
			}
			foreach ( (array) $value as $v ) {
				$this->attrs[ $element ][ $key ][] = $v;
			}
			return $this;
		}
		public function set_render_attribute( $element, $key = null, $value = null ) { return $this->add_render_attribute( $element, $key, $value, true ); }
		public function remove_render_attribute( $element, $key = null, $values = null ) {
			if ( null === $key ) {
				unset( $this->attrs[ $element ] );
			} else {
				unset( $this->attrs[ $element ][ $key ] );
			}
		}
		public function get_render_attributes( $element = '', $key = '' ) { return '' === $element ? $this->attrs : ( $this->attrs[ $element ] ?? array() ); }
		public function get_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $key => $values ) {
				$out[] = $key . '="' . htmlspecialchars( implode( ' ', array_map( 'strval', $values ) ), ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $element ) { echo $this->get_render_attribute_string( $element ); } // phpcs:ignore
		public function add_link_attributes( $element, array $url, $overwrite = false ) {
			if ( ! empty( $url['url'] ) ) {
				$this->add_render_attribute( $element, 'href', $url['url'], $overwrite );
			}
			if ( ! empty( $url['is_external'] ) ) {
				$this->add_render_attribute( $element, 'target', '_blank', $overwrite );
			}
			if ( ! empty( $url['nofollow'] ) ) {
				$this->add_render_attribute( $element, 'rel', 'nofollow', $overwrite );
			}
			return $this;
		}
		public function add_inline_editing_attributes( $key, $toolbar = 'basic' ) {}
		public function get_repeater_setting_key( $setting_key, $repeater_key, $repeater_item_index ) { return implode( '.', array( $repeater_key, $repeater_item_index, $setting_key ) ); }
		public function add_deprecation_message( $version, $message, $replacement ) {}
		public function get_group_name() { return ''; }
	}
	class Repeater {
		public $fields = array();
		public function add_control( $name, $args, $options = array() ) { $this->fields[ $name ] = $args + array( 'name' => $name ); return true; }
		public function add_responsive_control( $name, $args, $options = array() ) { return $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args = array(), $options = array() ) { $this->fields[ '@' . $args['name'] ] = array( 'type' => $type ) + $args; }
		public function get_controls() { return $this->fields; }
	}
	class Controls_Manager {
		const TAB_CONTENT = 'content'; const TAB_STYLE = 'style'; const TAB_ADVANCED = 'advanced'; const TAB_SETTINGS = 'settings';
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const WYSIWYG = 'wysiwyg'; const SELECT = 'select'; const SELECT2 = 'select2';
		const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider';
		const DIMENSIONS = 'dimensions'; const NUMBER = 'number'; const MEDIA = 'media'; const URL = 'url'; const ICONS = 'icons';
		const ICON = 'icon'; const REPEATER = 'repeater'; const HIDDEN = 'hidden'; const RAW_HTML = 'raw_html'; const DIVIDER = 'divider';
		const ALERT = 'alert'; const NOTICE = 'notice'; const DEPRECATED_NOTICE = 'deprecated_notice'; const BUTTON = 'button';
		const GAPS = 'gaps'; const POPOVER_TOGGLE = 'popover_toggle'; const FONT = 'font'; const IMAGE_DIMENSIONS = 'image_dimensions'; const HOVER_ANIMATION = 'hover_animation'; const ANIMATION = 'animation'; const DATE_TIME = 'date_time'; const CODE = 'code'; const GALLERY = 'gallery';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Css_Filter { public static function get_type() { return 'css-filter'; } }
	class Group_Control_Image_Size { public static function get_type() { return 'image-size'; } }
	class Utils {
		public static function get_placeholder_image_src() { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png'; }
		public static function validate_html_tag( $tag ) { return in_array( strtolower( (string) $tag ), array( 'a', 'article', 'aside', 'div', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'main', 'nav', 'p', 'section', 'span' ), true ) ? strtolower( (string) $tag ) : 'div'; }
		public static function print_validated_html_tag( $tag ) { echo self::validate_html_tag( $tag ); } // phpcs:ignore
		public static function print_unescaped_internal_string( $string ) { echo $string; } // phpcs:ignore
	}
	class Icons_Manager {
		public static function is_migration_allowed() { return true; }
		public static function is_font_icon_inline_svg() { return in_array( 'e_font_icon_svg', (array) ( $GLOBALS['h_wp']['features'] ?? array() ), true ); }
		public static function try_get_icon_html( $icon, $attributes = array(), $tag = 'i' ) {
			ob_start();
			self::render_icon( $icon, $attributes, $tag );
			return (string) ob_get_clean();
		}
		public static function render_icon( $icon, $attributes = array(), $tag = 'i' ) {
			if ( empty( $icon['value'] ) ) {
				return false;
			}
			$value = is_array( $icon['value'] ) ? 'svg-' . (int) ( $icon['value']['id'] ?? 0 ) : (string) $icon['value'];
			echo '<svg class="e-font-icon-svg" data-icon="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '" aria-hidden="true"></svg>';
			return true;
		}
		public static function render_font_icon( $icon, $attributes = array(), $tag = 'i' ) {
			ob_start();
			self::render_icon( $icon, $attributes, $tag );
			return (string) ob_get_clean();
		}
		public static function get_icons() { return array(); }
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro {
	class H_Experiments { public function is_feature_active( $f ) { return in_array( $f, (array) ( $GLOBALS['h_wp']['features'] ?? array() ), true ); } }
	class H_Db { public $stack = array(); public function switch_to_post( $id ) { $this->stack[] = $GLOBALS['post'] ?? null; $GLOBALS['h_wp']['switched'][] = $id; } public function restore_current_post() { array_pop( $this->stack ); } }
	class H_Mode { public function is_preview_mode( $id = 0 ) { return ! empty( $GLOBALS['h_wp']['preview_mode'] ); } public function is_edit_mode( $id = null ) { return ! empty( $GLOBALS['h_wp']['edit_mode'] ); } }
	class Plugin {
		public static $el;
		public static function elementor() {
			if ( ! self::$el ) {
				self::$el = (object) array( 'experiments' => new H_Experiments(), 'db' => new H_Db(), 'preview' => new H_Mode(), 'editor' => new H_Mode() );
			}
			return self::$el;
		}
		public static function instance() { return (object) array(); }
	}
}
namespace ElementorPro\Base {
	abstract class Base_Widget extends \Elementor\Widget_Base {
		public function get_categories() { return array( 'pro-elements' ); }
	}
	class Markdown_Utils {
		public static function plain_text( $t ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $t ) ) ); }
		public static function bullet_list( array $lines ) { return implode( "\n", $lines ); }
	}
}
namespace ElementorPro\Core {
	class Utils {
		public static function get_public_post_types( $args = array() ) { return $GLOBALS['h_wp']['post_types'] ?? array( 'post' => 'Entradas', 'page' => 'Páginas' ); }
		public static function get_taxonomies( $args = array(), $output = 'names', $operator = 'and' ) { return function_exists( 'get_taxonomies' ) ? get_taxonomies( $args, $output, $operator ) : array(); }
	}
}
namespace ElementorPro\Modules\QueryControl {
	class Module { const QUERY_CONTROL_ID = 'query'; const QUERY_OBJECT_POST = 'post'; const QUERY_OBJECT_TAX = 'tax'; const QUERY_OBJECT_AUTHOR = 'author'; const QUERY_OBJECT_USER = 'user'; }
}
namespace ElementorPro\Modules\ThemeElements {
	class Module { const SOURCE_TYPE_CURRENT_POST = 'current_post'; const SOURCE_TYPE_CUSTOM = 'custom'; }
}
namespace {
	defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['h_wp'] = ( $GLOBALS['h_wp'] ?? array() ) + array( 'features' => array( 'e_optimized_markup', 'e_font_icon_svg' ), 'home' => 'https://sitio.test/', 'rtl' => false );

	$h_fns = array(
		'__'                 => static function ( $v, $d = '' ) { return $v; },
		'_x'                 => static function ( $v, $c = '', $d = '' ) { return $v; },
		'_n'                 => static function ( $one, $many, $n, $d = '' ) { return 1 === (int) $n ? $one : $many; },
		'esc_html__'         => static function ( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); },
		'esc_attr__'         => static function ( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); },
		'esc_html_x'         => static function ( $v, $c = '', $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); },
		'esc_attr_x'         => static function ( $v, $c = '', $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); },
		'esc_html'           => static function ( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); },
		'esc_attr'           => static function ( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); },
		'esc_textarea'       => static function ( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); },
		'esc_url'            => static function ( $v ) { return preg_match( '~^(https?:)?//|^/|^#~', (string) $v ) ? htmlspecialchars( str_replace( ' ', '%20', (string) $v ), ENT_QUOTES, 'UTF-8' ) : ''; },
		'esc_url_raw'        => static function ( $v ) { return preg_match( '~^(https?:)?//|^/~', (string) $v ) ? (string) $v : ''; },
		'wp_strip_all_tags'  => static function ( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); },
		'wp_kses_post'       => static function ( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); },
		'wp_kses'            => static function ( $v, $allowed = array() ) { return strip_tags( (string) $v, '<' . implode( '><', array_keys( (array) $allowed ) ) . '>' ); },
		'wpautop'            => static function ( $v ) { return '' === trim( (string) $v ) ? '' : '<p>' . str_replace( "\n\n", "</p>\n<p>", trim( (string) $v ) ) . "</p>\n"; },
		'is_rtl'             => static function () { return ! empty( $GLOBALS['h_wp']['rtl'] ); },
		'home_url'           => static function ( $path = '' ) { return rtrim( $GLOBALS['h_wp']['home'], '/' ) . '/' . ltrim( (string) $path, '/' ); },
		'admin_url'          => static function ( $path = '' ) { return 'https://sitio.test/wp-admin/' . ltrim( (string) $path, '/' ); },
		'do_action'          => static function ( ...$args ) { $GLOBALS['h_wp']['actions'][] = $args[0]; },
		'apply_filters'      => static function ( $hook, $value, ...$args ) { return isset( $GLOBALS['h_wp']['filters'][ $hook ] ) ? call_user_func( $GLOBALS['h_wp']['filters'][ $hook ], $value, ...$args ) : $value; },
		'get_taxonomies'     => static function ( $args = array(), $output = 'names', $op = 'and' ) {
			$all = $GLOBALS['h_wp']['taxonomies'] ?? array( 'category' => 'Categorías', 'post_tag' => 'Etiquetas' );
			$out = array();
			foreach ( $all as $name => $label ) {
				$out[ $name ] = ( 'objects' === $output || false === $output ) ? (object) array( 'name' => $name, 'label' => $label, 'labels' => (object) array( 'name' => $label, 'singular_name' => $label ) ) : $name;
			}
			return $out;
		},
		'get_object_taxonomies' => static function ( $type, $output = 'names' ) { $t = $GLOBALS['h_wp']['object_taxonomies'][ is_string( $type ) ? $type : 'post' ] ?? ( 'post' === $type ? array( 'category', 'post_tag' ) : array() ); return 'objects' === $output ? array_combine( $t, array_map( 'get_taxonomy', $t ) ) : $t; },
		'get_taxonomy'       => static function ( $name ) { $all = $GLOBALS['h_wp']['taxonomies'] ?? array( 'category' => 'Categorías', 'post_tag' => 'Etiquetas' ); return isset( $all[ $name ] ) ? (object) array( 'name' => $name, 'label' => $all[ $name ], 'public' => true, 'labels' => (object) array( 'name' => $all[ $name ], 'singular_name' => $all[ $name ] ) ) : false; },
		'get_post_type_object' => static function ( $name ) { $all = $GLOBALS['h_wp']['post_types'] ?? array( 'post' => 'Entradas', 'page' => 'Páginas' ); return isset( $all[ $name ] ) ? (object) array( 'name' => $name, 'label' => $all[ $name ], 'labels' => (object) array( 'name' => $all[ $name ], 'singular_name' => $all[ $name ] ) ) : null; },
		'wp_parse_args'      => static function ( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); },
		'absint'             => static function ( $v ) { return abs( (int) $v ); },
		'is_admin'           => static function () { return false; },
		'wp_unique_id'       => static function ( $p = '' ) { static $n = 0; return $p . ( ++$n ); },
	);
	foreach ( $h_fns as $h_name => $h_fn ) {
		if ( ! function_exists( $h_name ) ) {
			$GLOBALS['h_wp_fns'][ $h_name ] = $h_fn;
			eval( 'function ' . $h_name . '( ...$a ) { return $GLOBALS[\'h_wp_fns\'][\'' . $h_name . '\']( ...$a ); }' ); // phpcs:ignore Squiz.PHP.Eval -- sólo pruebas.
		}
	}

	function h_check( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}

	$h_root = dirname( __DIR__, 2 ) . '/digitalisimo-elements/';
	require_once $h_root . 'modules/theme-elements/widgets/base.php';
	require_once $h_root . 'modules/digitalisimo-theme-elements/class-module.php';
	\Digitalisimo\Elements\Theme_Elements\Module::init();
	require_once $h_root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();

	/** Ruta de un archivo del plugin. */
	function h_path( string $relative ): string {
		return dirname( __DIR__, 2 ) . '/digitalisimo-elements/' . ltrim( $relative, '/' );
	}

	/** Un widget con sus controles registrados. */
	function h_widget( string $class ) {
		$widget = new $class();
		( digi_test_accessible( new ReflectionMethod( $widget, 'register_controls' ) ) )->invoke( $widget );
		return $widget;
	}

	/** Valores por defecto de los controles de un widget ya registrado (como `get_settings()` sin nada guardado). */
	function h_defaults( $widget ): array {
		$out = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$out[ $name ] = $control['default'];
			} elseif ( 'url' === ( $control['type'] ?? '' ) ) {
				// Como el control URL del núcleo sin valor por defecto.
				$out[ $name ] = array( 'url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' );
			} elseif ( in_array( $control['type'] ?? '', array( 'text', 'textarea', 'select', 'switcher', 'choose', 'color', 'wysiwyg', 'hidden' ), true ) ) {
				$out[ $name ] = '';
			}
		}
		return $out;
	}

	/** Pinta un widget con sus valores por defecto más `$settings`. */
	function h_render( $widget, array $settings = array(), bool $defaults = true ): string {
		$widget->attrs    = array();
		$widget->settings = $defaults ? array_merge( h_defaults( $widget ), $settings ) : $settings;
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $widget, 'render' ) ) )->invoke( $widget );
		return (string) ob_get_clean();
	}

	/** Partes de un control que deben coincidir con las de PRO (las demás son de presentación: etiquetas, ayudas, selectores). */
	const H_SAME = array( 'type', 'default', 'condition', 'conditions', 'size_units', 'range', 'responsive', 'frontend_available', 'render_type', 'return_value', 'global', 'exclude', 'fields_options', 'multiple', 'autocomplete', 'devices', 'min', 'max', 'step', 'label_block' );

	/**
	 * Compara dos listas de controles (o los campos de un repetidor) y devuelve las diferencias como texto. Coinciden el
	 * nombre, el orden y las partes de `H_SAME`, los valores por defecto por dispositivo (`*_default`), las claves de
	 * `options`, `prefix_class` (salvo el prefijo propio) y, en los repetidores, sus campos con las mismas reglas.
	 *
	 * @param array $pro   Controles del widget de PRO (`$widget->controls`, `$widget->groups` o los campos).
	 * @param array $own   Los del widget propio.
	 * @param array $skip  `nombre => true` o `nombre.parte => true`: diferencias declaradas por la prueba.
	 */
	function h_compare_controls( array $pro, array $own, array $skip = array(), string $prefix = '' ): array {
		$diff = array();
		foreach ( $pro as $name => $control ) {
			if ( isset( $skip[ $prefix . $name ] ) ) {
				continue;
			}
			if ( ! isset( $own[ $name ] ) ) {
				$diff[] = "$prefix$name: falta";
				continue;
			}
			$mine = $own[ $name ];
			$keys = array_unique( array_merge( array_keys( $control ), array_keys( $mine ) ) );
			foreach ( $keys as $key ) {
				if ( isset( $skip[ "$prefix$name.$key" ] ) ) {
					continue;
				}
				$check = in_array( $key, H_SAME, true ) || preg_match( '/_default$/', (string) $key );
				if ( $check && ( $control[ $key ] ?? null ) !== ( $mine[ $key ] ?? null ) ) {
					$diff[] = "$prefix$name.$key: PRO " . json_encode( $control[ $key ] ?? null, JSON_UNESCAPED_UNICODE ) . ' · propio ' . json_encode( $mine[ $key ] ?? null, JSON_UNESCAPED_UNICODE );
				}
			}
			if ( isset( $control['options'] ) && is_array( $control['options'] ) && ! isset( $skip[ "$prefix$name.options" ] ) ) {
				if ( array_map( 'strval', array_keys( $control['options'] ) ) !== array_map( 'strval', array_keys( (array) ( $mine['options'] ?? array() ) ) ) ) {
					$diff[] = "$prefix$name.options: PRO " . implode( ',', array_keys( $control['options'] ) ) . ' · propio ' . implode( ',', array_keys( (array) ( $mine['options'] ?? array() ) ) );
				}
			}
			if ( isset( $control['prefix_class'] ) !== isset( $mine['prefix_class'] ) && ! isset( $skip[ "$prefix$name.prefix_class" ] ) ) {
				$diff[] = "$prefix$name.prefix_class: sólo en " . ( isset( $control['prefix_class'] ) ? 'PRO' : 'el propio' );
			}
			if ( isset( $control['selectors'] ) !== isset( $mine['selectors'] ) && ! isset( $skip[ "$prefix$name.selectors" ] ) ) {
				$diff[] = "$prefix$name.selectors: sólo en " . ( isset( $control['selectors'] ) ? 'PRO' : 'el propio' );
			}
			if ( 'repeater' === ( $control['type'] ?? '' ) ) {
				$diff = array_merge( $diff, h_compare_controls( (array) ( $control['fields'] ?? array() ), (array) ( $mine['fields'] ?? array() ), $skip, "$prefix$name." ) );
				if ( array_keys( (array) ( $control['fields'] ?? array() ) ) !== array_keys( (array) ( $mine['fields'] ?? array() ) ) ) {
					$diff[] = "$prefix$name.fields: otro orden";
				}
			}
		}
		foreach ( array_diff_key( $own, $pro ) as $name => $control ) {
			if ( ! isset( $skip[ $prefix . $name ] ) ) {
				$diff[] = "$prefix$name: sólo en el propio";
			}
		}
		return $diff;
	}

	/** Compara secciones (nombre, pestaña y condiciones), controles, grupos y orden de dos widgets ya registrados. */
	function h_compare_widgets( $pro, $own, array $skip = array() ): array {
		$diff = array();
		foreach ( $pro->sections as $name => $section ) {
			if ( isset( $skip[ '§' . $name ] ) ) {
				continue;
			}
			if ( ! isset( $own->sections[ $name ] ) ) {
				$diff[] = "sección $name: falta";
				continue;
			}
			foreach ( array( 'tab', 'condition', 'conditions' ) as $key ) {
				if ( ( $section[ $key ] ?? null ) !== ( $own->sections[ $name ][ $key ] ?? null ) ) {
					$diff[] = "sección $name.$key: PRO " . json_encode( $section[ $key ] ?? null ) . ' · propio ' . json_encode( $own->sections[ $name ][ $key ] ?? null );
				}
			}
		}
		foreach ( array_diff_key( $own->sections, $pro->sections ) as $name => $section ) {
			if ( ! isset( $skip[ '§' . $name ] ) ) {
				$diff[] = "sección $name: sólo en el propio";
			}
		}
		$diff = array_merge( $diff, h_compare_controls( $pro->controls, $own->controls, $skip ), h_compare_controls( $pro->groups, $own->groups, $skip ) );
		foreach ( $pro->controls + $pro->groups as $name => $control ) {
			if ( isset( $own->controls[ $name ] ) || isset( $own->groups[ $name ] ) ) {
				$mine = $own->controls[ $name ] ?? $own->groups[ $name ];
				if ( ( $control['section'] ?? '' ) !== ( $mine['section'] ?? '' ) && ! isset( $skip[ $name . '.section' ] ) ) {
					$diff[] = "$name.section: PRO {$control['section']} · propio {$mine['section']}";
				}
			}
		}
		$order_pro = array_values( array_filter( $pro->order, static function ( $n ) use ( $own, $skip ) { return ! isset( $skip[ ltrim( $n, '@' ) ] ) && ( isset( $own->controls[ ltrim( $n, '@' ) ] ) || isset( $own->groups[ ltrim( $n, '@' ) ] ) ); } ) );
		$order_own = array_values( array_filter( $own->order, static function ( $n ) use ( $pro, $skip ) { return ! isset( $skip[ ltrim( $n, '@' ) ] ) && ( isset( $pro->controls[ ltrim( $n, '@' ) ] ) || isset( $pro->groups[ ltrim( $n, '@' ) ] ) ); } ) );
		if ( $order_pro !== $order_own && ! isset( $skip['@order'] ) ) {
			$diff[] = 'orden de los controles distinto';
		}
		return $diff;
	}

	/** Lo que el empaquetado veta en DIGITALÍSIMO Elements (scripts/build-packages.sh). */
	const H_VETOED = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';

	/** Código PHP sin comentarios (para buscar llamadas prohibidas sin confundirlas con la documentación). */
	function h_code( string $file ): string {
		$out = '';
		foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
			$out .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
		}
		return $out;
	}

	/**
	 * Comprobaciones comunes de recursos de un widget del frente H: archivos, registro en `Module::WIDGETS`/`TITLES`,
	 * hoja ≤ 4 KB sin el kit, `url()`, clases del proveedor ni colores fuera de `$colors`; foco visible; sin jQuery ni los
	 * paquetes de PRO; nada vetado por el empaquetado.
	 */
	function h_assets( $widget, string $title, string $pro, array $colors, bool $script = false ): string {
		$name = $widget->get_name();
		$slug = substr( $name, 13 );
		$mod  = \Digitalisimo\Elements\Theme_Elements\Module::class;
		h_check( 'digitalisimo-' . $slug === $name && $title === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories(), "$name: nombre, título y categoría propios." );
		h_check( isset( $mod::WIDGETS[ $name ], $mod::TITLES[ $name ] ) && $title === $mod::TITLES[ $name ] && 'widgets/class-' . $slug . '.php' === $mod::WIDGETS[ $name ]['file'], "$name: en Module::WIDGETS y TITLES con su archivo y título." );
		h_check( array( $name ) === $widget->get_style_depends(), "$name: sólo su hoja." );
		h_check( ( $script ? array( $name ) : array() ) === $widget->get_script_depends(), "$name: " . ( $script ? 'sólo su script.' : 'sin script.' ) );
		$css = (string) file_get_contents( h_path( $mod::WIDGETS[ $name ]['css'] ) );
		h_check( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$name: la hoja pesa " . strlen( $css ) . ' B (tope 4 096).' );
		h_check( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/\.elementor-(?!widget-' . preg_quote( $name, '/' ) . '\b)/', $css ), "$name: la hoja no usa el kit, url() ni clases del proveedor." );
		preg_match_all( '/#[0-9a-f]{3,8}\b/i', $css, $found );
		$extra = array_diff( array_unique( array_map( 'strtolower', $found[0] ) ), array_map( 'strtolower', $colors ) );
		h_check( ! $extra, "$name: sólo los colores que PRO usaba por defecto; sobran " . implode( ', ', $extra ) );
		if ( preg_match( '/transition|animation/', $css ) ) {
			h_check( str_contains( $css, 'prefers-reduced-motion' ), "$name: movimiento reducido para sus transiciones." );
		}
		$code = h_code( h_path( 'modules/digitalisimo-theme-elements/' . $mod::WIDGETS[ $name ]['file'] ) );
		h_check( ! preg_match( '/jquery|swiper|wp_enqueue_script|wp_register_script|elementor-pro-frontend|pro-elements-handlers|ElementorPro\\\\Modules\\\\ThemeElements/i', $code ), "$name: sin jQuery, Swiper, scripts encolados a mano ni clases del módulo de PRO." );
		$files = array( h_path( 'modules/digitalisimo-theme-elements/' . $mod::WIDGETS[ $name ]['file'] ), h_path( $mod::WIDGETS[ $name ]['css'] ), h_path( 'modules/digitalisimo-convert/maps/' . $pro . '.php' ) );
		if ( $script ) {
			$files[] = h_path( $mod::WIDGETS[ $name ]['js'] );
		}
		foreach ( $files as $file ) {
			h_check( is_file( $file ) && ! preg_match( H_VETOED, (string) file_get_contents( $file ) ), basename( $file ) . ': falta o nombra algo que el empaquetado veta.' );
		}
		return $css;
	}

	/**
	 * Convierte widgets de PRO con su mapa (borradores incluidos) y comprueba que ninguna clave quede sin destino:
	 * cada clave cae en un control o grupo propio, en una clave común o en las bolsas `__globals__`/`__dynamic__`.
	 *
	 * @return array{0: array<string, array>, 1: string[]} Ajustes convertidos por id y códigos `id:código:clave`.
	 */
	function h_convert( string $pro, $own, array $cases ): array {
		$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
		foreach ( $cases as $id => $settings ) {
			$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $pro, 'settings' => $settings, 'elements' => array() );
		}
		$log       = array();
		$converted = \Digitalisimo\Elements\Convert\Converter::tree( $tree, $log, true );
		h_check( count( $cases ) === count( $log ), "$pro: se convierte cada widget del árbol." );
		$lands = static function ( $key, $fields = null ) use ( $own ) {
			$base = \Digitalisimo\Elements\Convert\Translator::base( (string) $key );
			$controls = null === $fields ? $own->controls : $fields;
			if ( isset( $controls[ $base ] ) || \Digitalisimo\Elements\Convert\Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', '__fa4_migrated', \Digitalisimo\Elements\Convert\Translator::MARKER ), true ) ) {
				return true;
			}
			foreach ( array_keys( null === $fields ? $own->groups : array() ) as $group ) {
				if ( 0 === strpos( (string) $key, $group . '_' ) ) {
					return true;
				}
			}
			return false;
		};
		$out = array();
		foreach ( $converted[0]['elements'] as $element ) {
			h_check( $own->get_name() === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa al widget propio con el mismo id.' );
			$out[ $element['id'] ] = $element['settings'];
			foreach ( $element['settings'] as $key => $value ) {
				h_check( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
				if ( isset( $own->controls[ $key ] ) && 'repeater' === $own->controls[ $key ]['type'] && is_array( $value ) ) {
					foreach ( $value as $row ) {
						foreach ( array_keys( (array) $row ) as $field ) {
							h_check( $lands( $field, $own->controls[ $key ]['fields'] ) || in_array( $field, array( 'icon', 'selected_icon' ), true ), $element['id'] . ": el campo $key.$field no cae en un campo propio." );
						}
					}
				}
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
					h_check( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
				}
			}
		}
		$codes = array();
		foreach ( $log as $entry ) {
			foreach ( $entry['codes'] as $code ) {
				$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
			}
		}
		h_check( ! preg_grep( '/:pro\.unmapped:/', $codes ), "$pro: claves sin declarar en el mapa: " . implode( ', ', preg_grep( '/:pro\.unmapped:/', $codes ) ) );
		h_check( $converted === \Digitalisimo\Elements\Convert\Converter::tree( $converted, $again, true ) && array() === $again, "$pro: lo convertido no se convierte otra vez." );
		return array( $out, $codes );
	}

	/** Cada clase propia del CSS personalizado convertido aparece en `$html` (o es la del envoltorio, o la pone un `prefix_class`). */
	function h_classes_exist( array $map, $own, string $html ): void {
		$prefixed = array( '.elementor-widget-' . $own->get_name() );
		foreach ( $own->controls as $control ) {
			if ( isset( $control['prefix_class'], $control['options'] ) && is_array( $control['options'] ) ) {
				foreach ( array_keys( $control['options'] ) as $option ) {
					$prefixed[] = '.' . sprintf( $control['prefix_class'], '' ) . $option;
				}
			} elseif ( isset( $control['prefix_class'] ) ) {
				$prefixed[] = '.' . sprintf( $control['prefix_class'], '' ) . ( $control['return_value'] ?? 'yes' );
			}
		}
		foreach ( (array) ( $map['classes'] ?? array() ) as $from => $to ) {
			// Un estado se traduce a la clase propia con su condición (`.x:has(…)`, `.x[open]`): cuenta la clase.
			$class = preg_match( '/^\.([\w-]+)/', $to, $first ) ? $first[1] : substr( $to, 1 );
			h_check( in_array( $to, $prefixed, true ) || preg_match( '/class="([^"]* )?' . preg_quote( $class, '/' ) . '( [^"]*)?"/', $html ), "La clase $to (de $from) no existe en el widget propio." );
		}
	}
}
