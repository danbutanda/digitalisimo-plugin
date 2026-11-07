<?php
/**
 * Títulos y medios de tema propios (C4 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c4.md, frente G): Logotipo
 * del sitio, Título del sitio, de la página, de la entrada y del archivo, Extracto, Contenido de la entrada e Imagen
 * destacada, frente a los widgets de PRO que sustituyen (`modules/theme-builder/widgets/`), y sus mapas
 * (`modules/digitalisimo-convert/maps/theme-*.php`).
 *
 * Carga los archivos **reales** del Encabezado y la Imagen del núcleo y de los ocho widgets de PRO sobre una
 * `Widget_Base` que registra los controles, y compara cada par:
 * - registro: nombre, título y categoría, línea en `Module::WIDGETS`/`TITLES`, hojas (la del núcleo y la propia, ≤ 4 KB,
 *   con foco visible de 2 px y separación de 2 px, sin `--e-global-*` ni `url()`), ningún script, nada vetado por el
 *   empaquetado ni nombres de PRO en el código propio salvo la clase de compatibilidad del contenido;
 * - controles: los mismos (también los heredados del núcleo), con el mismo tipo, valor por defecto (también por
 *   dispositivo), opciones, condiciones, selectores, globales y dinámicos, con y sin el marcado optimizado;
 * - marcado: el mismo que PRO para los mismos ajustes (envoltorio con su clase propia en lugar de la de PRO), salvo las
 *   diferencias declaradas (nombre accesible del enlace del logotipo sin texto alternativo, guarda del contenido);
 * - Contenido de la entrada: contraseña, recursión, contenido del constructor y de WordPress con paginación, vista previa
 *   del editor (y el aviso de la plantilla) y vista previa de la plantilla del Theme Builder;
 * - mapas: cada control de PRO declarado (los del núcleo con `_theme-heading.php` y `_theme-image.php`) y conversión de
 *   ajustes reales (por defecto, dinámicos, globales, responsivos, CSS personalizado) sin `pro.unmapped`, con cada clave
 *   en un control propio y el mismo marcado que PRO.
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
		const HIDDEN = 'hidden'; const POPOVER_TOGGLE = 'popover_toggle';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Css_Filter { public static function get_type() { return 'css-filter'; } }
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
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ELEMENTOR_ASSETS_URL', 'https://sitio.test/a/' );
	$GLOBALS['tt'] = array( 'posts' => array(), 'post' => null, 'password' => array(), 'meta' => array(), 'captions' => array(), 'actions' => array(), 'blog' => 'Sitio de prueba', 'rtl' => false );
	function __( $v, $d = '' ) { return $v; }
	function esc_html__( $v, $d = '' ) { $v = $GLOBALS['tt']['l10n'][ $d ][ $v ] ?? $v; return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^https?://~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function is_rtl() { return $GLOBALS['tt']['rtl']; }
	function wp_kses_post( $v ) { return preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function get_bloginfo( $k ) { return $GLOBALS['tt']['blog']; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['tt']['meta'][ $id ][ $key ] ?? ''; }
	function wp_get_attachment_caption( $id ) { return $GLOBALS['tt']['captions'][ $id ] ?? ''; }
	function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
		$alt = $GLOBALS['tt']['meta'][ $id ]['_wp_attachment_image_alt'] ?? '';
		$out = '<img width="300" height="200" src="https://sitio.test/wp-content/uploads/a-' . (int) $id . '-' . $size . '.png" alt="' . htmlspecialchars( (string) $alt, ENT_QUOTES, 'UTF-8' ) . '" decoding="async" loading="lazy" srcset="https://sitio.test/wp-content/uploads/a-' . (int) $id . '-300.png 300w"';
		foreach ( $attr as $k => $v ) {
			$out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"';
		}
		return $out . ' />';
	}
	function get_the_ID() { return $GLOBALS['tt']['post'] ? $GLOBALS['tt']['post']->ID : false; }
	function get_post( $id = null ) { return null === $id ? $GLOBALS['tt']['post'] : ( $GLOBALS['tt']['posts'][ (int) $id ] ?? null ); }
	function get_the_title() { return $GLOBALS['tt']['post'] ? $GLOBALS['tt']['post']->post_title : ''; }
	function get_the_post_thumbnail_url() { return $GLOBALS['tt']['post'] && ! empty( $GLOBALS['tt']['post']->thumb ) ? $GLOBALS['tt']['post']->thumb : false; }
	function get_the_excerpt() { return $GLOBALS['tt']['post'] ? $GLOBALS['tt']['post']->post_excerpt : ''; }
	function post_password_required( $id ) { return ! empty( $GLOBALS['tt']['password'][ $id ] ); }
	function get_the_password_form( $id ) { return '<form class="post-password-form" data-post="' . (int) $id . '"></form>'; }
	function setup_postdata( $post ) { $GLOBALS['tt']['setup'] = $post->ID; return true; }
	function get_the_content() { $p = $GLOBALS['tt']['posts'][ $GLOBALS['tt']['setup'] ?? 0 ] ?? $GLOBALS['tt']['post']; return $p->post_content; }
	/** `the_content` de la prueba: pinta párrafos y, si el contenido lo pide, vuelve a pintar el widget (recursión). */
	function apply_filters( $hook, $value ) {
		if ( 'the_content' !== $hook ) {
			return $value;
		}
		if ( ! empty( $GLOBALS['tt']['nest'] ) && str_contains( (string) $value, '[anidar]' ) ) {
			ob_start();
			$GLOBALS['tt']['nest']();
			$value = str_replace( '[anidar]', '<div class="anidado">' . ob_get_clean() . '</div>', (string) $value );
		}
		return '<div class="contenido">' . $value . '</div>';
	}
	function wp_link_pages( $args ) { echo $args['before'] . '<a href="https://sitio.test/x/2/">' . $args['link_before'] . '2' . $args['link_after'] . '</a>' . $args['after']; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['tt']['actions'][] = array( $hook, $priority ); }
	function remove_action( $hook, $cb, $priority = 10 ) { return true; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {}
	function check_g( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function make_post( $id, array $data = array() ) { return $GLOBALS['tt']['posts'][ $id ] = (object) ( $data + array( 'ID' => $id, 'post_title' => "Entrada $id", 'post_excerpt' => '', 'post_content' => "Contenido $id", 'thumb' => '' ) ); }

	$root = __DIR__ . '/../digitalisimo-elements/';
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	$state = new \Elementor\Test_State();
	foreach ( array( 'experiments', 'editor', 'dynamic_tags', 'controls_manager', 'documents', 'preview', 'frontend' ) as $prop ) {
		\Elementor\Plugin::$instance->$prop = $state;
	}
	// Archivos reales del núcleo y de PRO.
	require $root . 'elementor/includes/widgets/heading.php';
	require $root . 'elementor/includes/widgets/image.php';
	require $root . 'modules/posts/skins/skin-content-base.php';
	foreach ( array( 'title-widget-base', 'site-title', 'page-title', 'post-title', 'archive-title', 'site-logo', 'post-featured-image', 'post-excerpt', 'post-content' ) as $file ) {
		require $root . 'modules/theme-builder/widgets/' . $file . '.php';
	}
	require $root . 'modules/digitalisimo-theme-elements/class-module.php';
	\Digitalisimo\Elements\Theme_Elements\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;
	use Digitalisimo\Elements\Theme_Elements\Module;
	use Digitalisimo\Elements\Theme_Elements\Widgets;

	$pairs = array(
		'theme-site-logo'           => array( 'pro' => \ElementorPro\Modules\ThemeBuilder\Widgets\Site_Logo::class, 'own' => Widgets\Site_Logo::class, 'name' => 'digitalisimo-site-logo', 'title' => 'Logotipo del sitio', 'base' => 'image', 'cat' => 'theme-elements' ),
		'theme-site-title'          => array( 'pro' => \ElementorPro\Modules\ThemeBuilder\Widgets\Site_Title::class, 'own' => Widgets\Site_Title::class, 'name' => 'digitalisimo-site-title', 'title' => 'Título del sitio', 'base' => 'heading', 'cat' => 'theme-elements' ),
		'theme-page-title'          => array( 'pro' => \ElementorPro\Modules\ThemeBuilder\Widgets\Page_Title::class, 'own' => Widgets\Page_Title::class, 'name' => 'digitalisimo-page-title', 'title' => 'Título de la página', 'base' => 'heading', 'cat' => 'theme-elements' ),
		'theme-post-title'          => array( 'pro' => \ElementorPro\Modules\ThemeBuilder\Widgets\Post_Title::class, 'own' => Widgets\Post_Title::class, 'name' => 'digitalisimo-post-title', 'title' => 'Título de la entrada', 'base' => 'heading', 'cat' => 'theme-elements-single' ),
		'theme-archive-title'       => array( 'pro' => \ElementorPro\Modules\ThemeBuilder\Widgets\Archive_Title::class, 'own' => Widgets\Archive_Title::class, 'name' => 'digitalisimo-archive-title', 'title' => 'Título del archivo', 'base' => 'heading', 'cat' => 'theme-elements-archive' ),
		'theme-post-excerpt'        => array( 'pro' => \ElementorPro\Modules\ThemeBuilder\Widgets\Post_Excerpt::class, 'own' => Widgets\Post_Excerpt::class, 'name' => 'digitalisimo-post-excerpt', 'title' => 'Extracto', 'base' => '', 'cat' => 'theme-elements-single' ),
		'theme-post-content'        => array( 'pro' => \ElementorPro\Modules\ThemeBuilder\Widgets\Post_Content::class, 'own' => Widgets\Post_Content::class, 'name' => 'digitalisimo-post-content', 'title' => 'Contenido de la entrada', 'base' => '', 'cat' => 'theme-elements-single' ),
		'theme-post-featured-image' => array( 'pro' => \ElementorPro\Modules\ThemeBuilder\Widgets\Post_Featured_Image::class, 'own' => Widgets\Featured_Image::class, 'name' => 'digitalisimo-featured-image', 'title' => 'Imagen destacada', 'base' => 'image', 'cat' => 'theme-elements-single' ),
	);
	$make = static function ( $class, array $settings = array() ) {
		$widget = new $class();
		( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
		$defaults = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$defaults[ $name ] = $control['default'];
			}
		}
		foreach ( $widget->groups as $name => $group ) {
			if ( 'image-size' === $group['type'] ) {
				$defaults[ $name . '_size' ] = $group['default'] ?? 'large';
			}
		}
		$widget->settings = array_merge( $defaults, $settings );
		return $widget;
	};
	$render = static function ( $class, array $settings = array() ) use ( $make ) {
		$widget = $make( $class, $settings );
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return trim( preg_replace( '/>\s+</', '><', preg_replace( '/\s+/', ' ', (string) ob_get_clean() ) ) );
	};
	// Lo comparable de un control: todo menos los textos traducidos (de las opciones de un desplegable, sólo las claves).
	$shape = static function ( array $control ) use ( &$shape ) {
		unset( $control['label'], $control['description'], $control['placeholder'], $control['text'], $control['title'] );
		if ( isset( $control['options'] ) && is_array( $control['options'] ) ) {
			$options = array();
			foreach ( $control['options'] as $key => $option ) {
				$options[ $key ] = is_array( $option ) ? ( $option['icon'] ?? '' ) : '';
			}
			$control['options'] = $options;
		}
		if ( isset( $control['content'] ) ) {
			$control['content'] = preg_match( '/onclick="([^"]*)"/', (string) $control['content'], $m ) ? $m[1] : 'texto';
		}
		ksort( $control );
		return $control;
	};
	// Lo que el empaquetado veta en DIGITALÍSIMO Elements (scripts/build-packages.sh).
	$vetoed = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	$mod    = $root . 'modules/digitalisimo-theme-elements/';
	$checks = 0;

	// 1. Registro y recursos.
	foreach ( $pairs as $pro => $p ) {
		$own = new $p['own']();
		check_g( $p['name'] === $own->get_name() && $p['title'] === $own->get_title() && array( $p['cat'] ) === $own->get_categories(), "$pro: nombre, título y categoría (la de PRO, para salir en las mismas plantillas)." );
		check_g( ( new $p['pro']() )->get_categories() === $own->get_categories() && ( new $p['pro']() )->get_icon() === $own->get_icon(), "$pro: misma categoría e icono que PRO." );
		check_g( isset( Module::WIDGETS[ $p['name'] ], Module::TITLES[ $p['name'] ] ) && $p['title'] === Module::TITLES[ $p['name'] ], "$pro: en Module::WIDGETS y TITLES." );
		check_g( is_file( $mod . Module::WIDGETS[ $p['name'] ]['file'] ) && $p['own'] === ltrim( Module::WIDGETS[ $p['name'] ]['class'], '\\' ), "$pro: archivo y clase en Module::WIDGETS." );
		check_g( ! is_file( $root . Module::WIDGETS[ $p['name'] ]['js'] ) && array() === $own->get_script_depends(), "$pro: sin script." );
		$css_file = $root . Module::WIDGETS[ $p['name'] ]['css'];
		$expect   = array_values( array_filter( array( 'heading' === $p['base'] ? 'widget-heading' : ( 'image' === $p['base'] ? 'widget-image' : '' ), is_file( $css_file ) ? $p['name'] : '' ) ) );
		check_g( $expect === $own->get_style_depends(), "$pro: hojas " . implode( ', ', $own->get_style_depends() ) . ' (la del núcleo que hereda y la propia).' );
		check_g( 'theme-post-content' === $pro ? ! is_file( $css_file ) : is_file( $css_file ), "$pro: hoja propia salvo el contenido (que no pinta controles propios)." );
		if ( is_file( $css_file ) ) {
			$css = (string) file_get_contents( $css_file );
			check_g( strlen( $css ) <= 4096 && ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(|#[0-9a-f]{3,6}\b/i', $css ), "$pro: hoja ≤ 4 KB, sin el kit, sin url() ni colores." );
			check_g( str_contains( $css, '.elementor-widget-' . $p['name'] . ' a:focus-visible{outline:2px solid;outline-offset:2px}' ), "$pro: foco visible de 2 px con separación de 2 px." );
			check_g( ! str_contains( $css, 'transition' ) || str_contains( $css, 'prefers-reduced-motion' ), "$pro: movimiento reducido." );
		}
		$source = '';
		foreach ( token_get_all( (string) file_get_contents( $mod . Module::WIDGETS[ $p['name'] ]['file'] ) ) as $token ) {
			$source .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
		}
		check_g( ! preg_match( '/jquery|swiper|wp_enqueue_script|wp_register_script|elementor-pro-frontend|pro-elements-handlers|ld\+json|wp_head|<title/i', $source ), "$pro: sin scripts ni señales SEO." );
		check_g( ! preg_match( '/extends\s+\\\\?ElementorPro|use\s+ElementorPro/', $source ), "$pro: no hereda ni importa clases de PRO." );
		foreach ( array( $mod . Module::WIDGETS[ $p['name'] ]['file'], $css_file, $root . 'modules/digitalisimo-convert/maps/' . $pro . '.php' ) as $file ) {
			check_g( ! is_file( $file ) || ! preg_match( $vetoed, (string) file_get_contents( $file ) ), basename( $file ) . ': nombra algo que el empaquetado veta.' );
		}
		$checks += 8;
	}
	foreach ( array( '_theme-heading.php', '_theme-image.php' ) as $file ) {
		check_g( ! preg_match( $vetoed, (string) file_get_contents( $root . 'modules/digitalisimo-convert/maps/' . $file ) ), "$file: nombra algo vetado." );
	}
	check_g( ! preg_match( $vetoed, (string) file_get_contents( $mod . 'widgets/class-title-base.php' ) ), 'class-title-base.php: nombra algo vetado.' );

	// 2. Los mismos controles que PRO (también los del núcleo), con y sin el marcado optimizado.
	foreach ( array( false, true ) as $optimized ) {
		$state->optimized = $optimized;
		foreach ( $pairs as $pro => $p ) {
			$a = $make( $p['pro'] );
			$b = $make( $p['own'] );
			check_g( array_keys( $a->controls ) === array_keys( $b->controls ), "$pro: los mismos controles en el mismo orden.\n PRO: " . implode( ', ', array_keys( $a->controls ) ) . "\n propio: " . implode( ', ', array_keys( $b->controls ) ) );
			check_g( $a->order === $b->order, "$pro: el mismo orden de controles, grupos y pestañas." );
			foreach ( $a->controls as $name => $control ) {
				check_g( $shape( $control ) === $shape( $b->controls[ $name ] ), "$pro: el control $name difiere.\n PRO: " . json_encode( $shape( $control ) ) . "\n propio: " . json_encode( $shape( $b->controls[ $name ] ) ) );
				$checks++;
			}
			check_g( array_keys( $a->groups ) === array_keys( $b->groups ), "$pro: los mismos grupos." );
			foreach ( $a->groups as $name => $group ) {
				check_g( $shape( $group ) === $shape( $b->groups[ $name ] ), "$pro: el grupo $name difiere." );
			}
			check_g( array_keys( $a->sections ) === array_keys( $b->sections ), "$pro: las mismas secciones." );
			foreach ( $a->sections as $name => $section ) {
				check_g( ( $section['tab'] ?? '' ) === ( $b->sections[ $name ]['tab'] ?? '' ), "$pro: la sección $name en la misma pestaña." );
			}
			check_g( $a->has_widget_inner_wrapper() === $b->has_widget_inner_wrapper(), "$pro: la misma envoltura interior." );
			check_g( str_replace( $pro, $p['name'], $a->wrapper_class() ) === $b->wrapper_class() || ( 'theme-post-content' === $pro && $a->wrapper_class() . ' ' . $a->wrapper_class() === str_replace( $p['name'], $pro, $b->wrapper_class() ) ), "$pro: clases del envoltorio «" . $b->wrapper_class() . '» frente a «' . $a->wrapper_class() . '».' );
			check_g( $a->show_in_panel() === $b->show_in_panel() || 'theme-post-content' === $pro, "$pro: visible en el panel como PRO." );
		}
	}
	$state->optimized = false;
	check_g( 'elementor-widget-digitalisimo-post-content elementor-widget-theme-post-content' === ( new Widgets\Post_Content() )->wrapper_class(), 'Contenido: conserva la clase de PRO en el envoltorio (indicador de progreso y figuras).' );
	check_g( 'elementor-widget-digitalisimo-post-title elementor-page-title elementor-widget-heading' === ( new Widgets\Post_Title() )->wrapper_class(), 'Título de la entrada: elementor-page-title y elementor-widget-heading, como PRO.' );
	check_g( '[elementor-tag id="" name="post-featured-image" settings="%7B%7D"]' === $make( Widgets\Featured_Image::class )->controls['image']['dynamic']['default'], 'Imagen destacada: la etiqueta dinámica de PRO por defecto.' );
	check_g( 'media-preview' === $make( Widgets\Site_Logo::class )->controls['image']['type'] && 'https://sitio.test/wp-content/uploads/logo.png' === $make( Widgets\Site_Logo::class )->controls['image']['src'], 'Logotipo: vista previa del Theme Builder con el logotipo del Personalizador.' );

	// 3. El mismo marcado que PRO.
	$GLOBALS['tt']['meta'][12]['_wp_attachment_image_alt'] = 'Logotipo de prueba';
	$GLOBALS['tt']['meta'][7]['_wp_attachment_image_alt']  = 'Foto destacada';
	$GLOBALS['tt']['captions'][7] = 'Leyenda del adjunto';
	$GLOBALS['tt']['captions'][12] = 'Leyenda del logotipo';
	$state->docs[100] = new \Elementor\Test_Document();
	$state->docs[101] = new \Elementor\Test_Document( array( 'hide_title' => 'yes' ) );
	$GLOBALS['tt']['post'] = make_post( 100, array( 'post_title' => 'Entrada de prueba', 'post_excerpt' => 'Un extracto con <a href="https://sitio.test/x/">enlace</a>.', 'thumb' => 'https://sitio.test/wp-content/uploads/a-7.png' ) );
	$heading_cases = array(
		array( 'title' => 'Entrada de prueba' ),
		array( 'title' => 'Con enlace', 'link' => array( 'url' => 'https://sitio.test/x/', 'is_external' => 'on', 'nofollow' => '' ) ),
		array( 'title' => 'Tamaño', 'size' => 'large', 'header_size' => 'h3' ),
		array( 'title' => 'Etiqueta div', 'header_size' => 'div' ),
		array( 'title' => '' ),
	);
	foreach ( array( 'theme-site-title', 'theme-page-title', 'theme-post-title', 'theme-archive-title' ) as $pro ) {
		foreach ( $heading_cases as $i => $settings ) {
			$a = $render( $pairs[ $pro ]['pro'], $settings );
			check_g( $a === $render( $pairs[ $pro ]['own'], $settings ), "$pro: marcado distinto en el caso $i: $a" );
			$checks++;
		}
	}
	check_g( '<h1 class="elementor-heading-title elementor-size-default">Entrada de prueba</h1>' === $render( Widgets\Post_Title::class, array( 'title' => 'Entrada de prueba' ) ), 'Título de la entrada: h1 por defecto.' );
	check_g( '<h2 class="elementor-heading-title elementor-size-default">Sitio</h2>' === $render( Widgets\Site_Title::class, array( 'title' => 'Sitio' ) ), 'Título del sitio: h2 por defecto, como PRO.' );
	$GLOBALS['tt']['post'] = make_post( 101, array( 'post_title' => 'Oculto' ) );
	foreach ( array( 'theme-page-title', 'theme-post-title', 'theme-archive-title' ) as $pro ) {
		check_g( '' === $render( $pairs[ $pro ]['own'], array( 'title' => 'Oculto' ) ) && '' === $render( $pairs[ $pro ]['pro'], array( 'title' => 'Oculto' ) ), "$pro: nada con «Ocultar título», como PRO." );
	}
	check_g( '' !== $render( Widgets\Site_Title::class, array( 'title' => 'Sitio' ) ), 'Título del sitio: no depende de «Ocultar título».' );
	$GLOBALS['tt']['post'] = $GLOBALS['tt']['posts'][100];

	$image_cases = array(
		array( 'image' => array( 'id' => 7, 'url' => 'https://sitio.test/wp-content/uploads/a-7.png' ) ),
		array( 'image' => array( 'id' => 7, 'url' => 'https://sitio.test/wp-content/uploads/a-7.png' ), 'link_to' => 'file', 'open_lightbox' => 'yes' ),
		array( 'image' => array( 'id' => 7, 'url' => 'https://sitio.test/wp-content/uploads/a-7.png' ), 'link_to' => 'custom', 'link' => array( 'url' => 'https://sitio.test/y/', 'is_external' => '', 'nofollow' => 'on' ) ),
		array( 'image' => array( 'id' => 7, 'url' => 'https://sitio.test/wp-content/uploads/a-7.png' ), 'link_to' => 'custom', 'link' => array( 'url' => '' ) ),
		array( 'image' => array( 'id' => 7, 'url' => 'https://sitio.test/wp-content/uploads/a-7.png' ), 'caption_source' => 'attachment', 'hover_animation' => 'grow' ),
		array( 'image' => array( 'id' => 7, 'url' => 'https://sitio.test/wp-content/uploads/a-7.png' ), 'caption_source' => 'custom', 'caption' => 'Mi leyenda', 'image_size' => 'medium' ),
		array( 'image' => array( 'id' => '', 'url' => 'https://otro.test/x.png', 'alt' => 'Externa' ), 'link_to' => 'none' ),
		array( 'image' => array( 'id' => '', 'url' => '' ) ),
	);
	foreach ( $image_cases as $i => $settings ) {
		$a = $render( $pairs['theme-post-featured-image']['pro'], $settings );
		check_g( $a === $render( Widgets\Featured_Image::class, $settings ), "Imagen destacada: marcado distinto en el caso $i: $a" );
		$checks++;
	}
	$logo_cases = array(
		array( 'image' => array( 'id' => 12, 'url' => 'https://sitio.test/wp-content/uploads/logo.png' ) ),
		array( 'image' => array( 'id' => 12, 'url' => 'https://sitio.test/wp-content/uploads/logo.png' ), 'link_to' => 'none' ),
		array( 'image' => array( 'id' => 12, 'url' => 'https://sitio.test/wp-content/uploads/logo.png' ), 'link_to' => 'file', 'open_lightbox' => 'no' ),
		array( 'image' => array( 'id' => 12, 'url' => 'https://sitio.test/wp-content/uploads/logo.png' ), 'link_to' => 'custom', 'link' => array( 'url' => 'https://sitio.test/z/', 'is_external' => 'on' ) ),
		array( 'image' => array( 'id' => 12, 'url' => 'https://sitio.test/wp-content/uploads/logo.png' ), 'caption_source' => 'attachment', 'image_size' => 'medium' ),
		array( 'image' => array( 'id' => '', 'url' => 'https://otro.test/logo.svg', 'alt' => 'Marca' ) ),
		array( 'image' => array( 'id' => 12, 'url' => '' ) ),
	);
	foreach ( $logo_cases as $i => $settings ) {
		$a = $render( $pairs['theme-site-logo']['pro'], $settings );
		check_g( $a === $render( Widgets\Site_Logo::class, $settings ), "Logotipo: marcado distinto en el caso $i:\n PRO: $a\n propio: " . $render( Widgets\Site_Logo::class, $settings ) );
		$checks++;
	}
	check_g( str_contains( $render( Widgets\Site_Logo::class, $logo_cases[0] ), '<a href="https://sitio.test/">' ) && ! str_contains( $render( Widgets\Site_Logo::class, $logo_cases[0] ), 'lightbox' ), 'Logotipo: enlace al inicio por defecto, sin lightbox.' );
	// Diferencia declarada: sin texto alternativo, el enlace al inicio lleva el nombre del sitio.
	$GLOBALS['tt']['meta'][12]['_wp_attachment_image_alt'] = '';
	$a = $render( $pairs['theme-site-logo']['pro'], $logo_cases[0] );
	$b = $render( Widgets\Site_Logo::class, $logo_cases[0] );
	check_g( str_replace( '<a href="https://sitio.test/" aria-label="Sitio de prueba">', '<a href="https://sitio.test/">', $b ) === $a && $a !== $b, "Logotipo sin texto alternativo: sólo cambia el nombre accesible del enlace.\n $b" );
	check_g( ! str_contains( $render( Widgets\Site_Logo::class, $logo_cases[3] ), 'aria-label' ) && ! str_contains( $render( Widgets\Site_Logo::class, $logo_cases[2] ), 'aria-label' ), 'Logotipo: el nombre del sitio sólo en el enlace al inicio.' );
	$GLOBALS['tt']['meta'][12]['_wp_attachment_image_alt'] = 'Logotipo de prueba';

	foreach ( array( array( 'excerpt' => 'Un extracto con <a href="https://sitio.test/x/">enlace</a>.' ), array( 'excerpt' => '' ) ) as $i => $settings ) {
		check_g( $render( $pairs['theme-post-excerpt']['pro'], $settings ) === $render( Widgets\Post_Excerpt::class, $settings ), "Extracto: marcado distinto en el caso $i." );
	}
	check_g( 'Un extracto con <a href="https://sitio.test/x/">enlace</a>.' === $render( Widgets\Post_Excerpt::class, array( 'excerpt' => 'Un extracto con <a href="https://sitio.test/x/">enlace</a>.' ) ), 'Extracto: el ajuste tal cual, sin envoltura propia, como PRO.' );
	check_g( 'Un extracto con enlace.' === $make( Widgets\Post_Excerpt::class )->render_markdown() && ( new $pairs['theme-post-excerpt']['pro']() )->render_markdown() === ( new Widgets\Post_Excerpt() )->render_markdown(), 'Extracto: el mismo Markdown que PRO.' );
	check_g( '![Entrada de prueba](https://sitio.test/wp-content/uploads/a-7.png)' === $make( Widgets\Featured_Image::class, array( 'image' => array( 'id' => '', 'url' => '' ) ) )->render_markdown(), 'Imagen destacada: Markdown de la imagen destacada si el widget no tiene imagen, como PRO.' );

	// 4. Contenido de la entrada: las mismas ramas que PRO.
	$content = static function ( $class, $post_id, array $settings = array() ) use ( $render, $state ) {
		$GLOBALS['tt']['post'] = $GLOBALS['tt']['posts'][ $post_id ];
		$state->calls          = array();
		return $render( $class, $settings );
	};
	$pro_content = $pairs['theme-post-content']['pro'];
	// Lo convertido de PRO lleva la marca del traductor (`Translator::MARKER`, la dejan «Migrar» y los kits): con ella, el
	// título de las páginas de la entrada es el de PRO; sin ella (contenido nuevo), «Páginas:», como desde C4.
	$converted = array( \Digitalisimo\Elements\Convert\Translator::MARKER => 'theme-post-content' );
	// a) Entrada sin constructor: `the_content` de WordPress y paginación (PRO se queda la entrada para toda la petición).
	make_post( 200, array( 'post_content' => '<p>Hola</p>' ) );
	make_post( 201, array( 'post_content' => '<p>Hola</p>' ) );
	$a = $content( $pro_content, 200 );
	$calls_a = $state->calls;
	$b = $content( Widgets\Post_Content::class, 201, $converted );
	check_g( '<div class="contenido"><p>Hola</p></div><div class="page-links elementor-page-links"><span class="page-links-title elementor-page-links-title">Pages:</span><a href="https://sitio.test/x/2/"><span>2</span></a></div>' === $a, "Contenido (PRO): the_content y paginación: $a" );
	check_g( $a === $b && str_replace( '200', '201', implode( ',', $calls_a ) ) === implode( ',', $state->calls ), "Contenido: the_content y paginación como PRO (y las mismas llamadas al núcleo).\n $b\n " . implode( ',', $state->calls ) );
	check_g( array( 'edit:0', 'builder:201:0', 'remove_content_filter', 'add_content_filter', 'edit:0' ) === $state->calls, 'Contenido: sin el CSS del contenido, sin modo de edición y con el filtro del constructor devuelto: ' . implode( ',', $state->calls ) );
	check_g( '' === $content( $pro_content, 200 ) && '' === $content( Widgets\Post_Content::class, 201, $converted ) && '' === $content( Widgets\Post_Content::class, 201 ), 'Contenido: como PRO, la misma entrada sin el constructor no se vuelve a pintar en la petición (convertido o nuevo).' );
	make_post( 292, array( 'post_content' => '<p>Hola</p>' ) );
	check_g( str_replace( 'Pages:', 'Páginas:', $a ) === $content( Widgets\Post_Content::class, 292 ), 'Contenido nuevo (sin la marca de la conversión): «Páginas:», como desde C4.' );
	// El título de la paginación es el texto de PRO con su dominio: con el paquete de idioma de PRO, la misma traducción.
	make_post( 290, array( 'post_content' => '<p>Hallo</p>' ) );
	make_post( 291, array( 'post_content' => '<p>Hallo</p>' ) );
	$GLOBALS['tt']['l10n'] = array( 'elementor-pro' => array( 'Pages:' => 'Seiten:' ) );
	$a = $content( $pro_content, 290 );
	$b = $content( Widgets\Post_Content::class, 291, $converted );
	make_post( 293, array( 'post_content' => '<p>Hallo</p>' ) );
	$c = $content( Widgets\Post_Content::class, 293 );
	unset( $GLOBALS['tt']['l10n'] );
	check_g( str_contains( $a, '<span class="page-links-title elementor-page-links-title">Seiten:</span>' ) && $a === $b, "Contenido convertido: el título de la paginación con el msgid y el dominio de PRO («Pages:» traducido igual).\n $a\n $b" );
	check_g( str_replace( 'Seiten:', 'Páginas:', $a ) === $c, "Contenido nuevo: «Páginas:» (dominio propio), sin la traducción de PRO.\n $c" );
	// b) Contenido del constructor.
	make_post( 202 );
	make_post( 203 );
	$state->builder[202] = $state->builder[203] = '<div class="elementor elementor-x">Del constructor</div>';
	$a = $content( $pro_content, 202 );
	$calls_a = $state->calls;
	$b = $content( Widgets\Post_Content::class, 203 );
	check_g( '<div class="contenido"><div class="elementor elementor-x">Del constructor</div></div>' === $a && $a === $b && str_replace( '202', '203', implode( ',', $calls_a ) ) === implode( ',', $state->calls ), "Contenido del constructor como PRO: $b · " . implode( ',', $state->calls ) );
	check_g( in_array( 'remove_content_filters', $state->calls, true ) && in_array( 'restore_content_filters', $state->calls, true ), 'Contenido del constructor: `the_content` sin los filtros del constructor.' );
	// c) Contraseña.
	make_post( 204 );
	$GLOBALS['tt']['password'][204] = true;
	check_g( '<form class="post-password-form" data-post="204"></form>' === $content( Widgets\Post_Content::class, 204 ) && $content( $pro_content, 204 ) === $content( Widgets\Post_Content::class, 204 ), 'Contenido: formulario de contraseña, como PRO.' );
	// d) Recursión: el contenido de la entrada vuelve a pintar el widget de la misma entrada.
	foreach ( array( 205 => $pro_content, 206 => Widgets\Post_Content::class ) as $id => $class ) {
		make_post( $id, array( 'post_content' => 'Antes [anidar] después' ) );
		$GLOBALS['tt']['nest'] = static function () use ( $class, $make ) {
			$widget = $make( $class );
			( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		};
		$out[ $id ] = $content( $class, $id, $pro_content === $class ? array() : $converted );
	}
	$GLOBALS['tt']['nest'] = null;
	check_g( str_contains( $out[206], '<div class="anidado"></div>' ) && $out[205] === $out[206], "Contenido: la entrada no se pinta dentro de sí misma (guarda de PRO): {$out[206]}" );
	// Como PRO, la guarda de una entrada sin el constructor no se libera: un segundo Contenido de la misma entrada en la
	// misma petición no pinta nada (en PRO, ni su envoltorio).
	check_g( '' === $content( Widgets\Post_Content::class, 206 ) && '' === $content( $pro_content, 205 ), 'Contenido: tras pintar una entrada sin el constructor, la misma entrada no se vuelve a pintar en la petición, como PRO.' );
	make_post( 208 );
	$state->builder[208] = '<div class="elementor elementor-y">Constructor</div>';
	check_g( '' !== $content( Widgets\Post_Content::class, 208 ) && '' !== $content( Widgets\Post_Content::class, 208 ), 'Contenido del constructor: la guarda se libera al terminar, como PRO.' );
	// e) Vista previa del editor: la envoltura del constructor y, una vez, el gancho que quita el aviso de la plantilla.
	make_post( 207 );
	$state->preview = 207;
	$GLOBALS['tt']['actions'] = array();
	$b = $content( Widgets\Post_Content::class, 207 );
	check_g( '<div class="elementor-builder-wrapper"></div>' === $b && $b === $content( $pro_content, 207 ), "Contenido: en la vista previa, la envoltura del constructor, como PRO: $b" );
	$content( Widgets\Post_Content::class, 207 );
	check_g( array( array( 'wp_footer', 9 ) ) === $GLOBALS['tt']['actions'], 'Contenido: en la vista previa quita (una vez) el aviso «falta el widget de contenido» antes de que corra.' );
	$state->preview = 0;
	// f) La plantilla del Theme Builder vista por sí misma: el contenido de su vista previa.
	make_post( 208 );
	make_post( 209 );
	make_post( 210, array( 'post_content' => '<p>Vista previa</p>' ) );
	make_post( 211, array( 'post_content' => '<p>Vista previa</p>' ) );
	$state->docs[208] = new \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document( array( 'preview_type' => 'single/post', 'preview_id' => 210 ) );
	$state->docs[209] = new \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document( array( 'preview_type' => 'single/post', 'preview_id' => 211 ) );
	$a = $content( $pro_content, 208 );
	$b = $content( Widgets\Post_Content::class, 209, $converted );
	check_g( str_contains( $b, '<p>Vista previa</p>' ) && $a === $b, "Contenido: en la plantilla, el de su vista previa, como PRO: $b" );
	$state->docs[212] = new \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document( array( 'preview_type' => 'single/post', 'preview_id' => 999 ) );
	make_post( 212 );
	check_g( '' === $content( Widgets\Post_Content::class, 212 ) && '' === $content( $pro_content, 212 ), 'Contenido: sin entrada de vista previa, nada (como PRO).' );
	$checks += 14;

	// 5. Mapas: cobertura (los controles de PRO, también los del núcleo) y conversión.
	foreach ( $pairs as $pro => $p ) {
		$map = Registry::map( $pro, true );
		check_g( null !== $map && $p['name'] === $map['target'] && in_array( $map['status'], array( 'draft', 'ready' ), true ) && 'hide' === $map['panel'], "$pro: mapa hacia {$p['name']}, oculto al estar listo." );
		$declared = array_merge( (array) ( $map['keep'] ?? array() ), array_keys( (array) ( $map['groups'] ?? array() ) ), (array) ( $map['ui'] ?? array() ), array_keys( (array) ( $map['drop'] ?? array() ) ), array_keys( (array) ( $map['approx'] ?? array() ) ) );
		$widget   = $make( $p['pro'] );
		$missing  = array_diff( array_merge( array_keys( $widget->controls ), array_keys( $widget->groups ) ), $declared );
		check_g( ! $missing, "$pro: el mapa no declara " . implode( ', ', $missing ) );
		$own      = $make( $p['own'] );
		$extra    = array_diff( $declared, array_keys( $own->controls ), array_keys( $own->groups ), array( 'caption' ) );
		check_g( ! $extra, "$pro: el mapa declara controles que el propio no tiene: " . implode( ', ', $extra ) );
		foreach ( (array) ( $map['groups'] ?? array() ) as $from => $to ) {
			check_g( $from === $to && ( isset( $own->groups[ $to ] ) || isset( $own->controls[ $to ] ) ), "$pro: grupo $from con el mismo nombre." );
		}
		$checks += 3;
	}
	// Ajustes reales, como los guarda el editor (sin lo que coincide con el valor por defecto).
	$saved = array(
		'theme-post-title'          => array( '__dynamic__' => array( 'title' => '[elementor-tag id="1a2b3c4" name="post-title" settings="%7B%22before%22%3A%22%22%7D"]', 'link' => '[elementor-tag id="5d6e7f8" name="post-url" settings="%7B%7D"]' ), 'header_size' => 'h2', 'align' => 'center', 'align_mobile' => 'left', 'typography_typography' => 'custom', 'typography_font_size' => array( 'unit' => 'px', 'size' => 44, 'sizes' => array() ), 'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 28, 'sizes' => array() ), '__globals__' => array( 'title_color' => 'globals/colors?id=secondary', 'typography_typography' => '' ), 'text_shadow_text_shadow_type' => 'yes', 'title_hover_color' => '#123456', '_css_classes' => 'entry-title', 'custom_css' => 'selector.elementor-widget-theme-post-title .elementor-heading-title{letter-spacing:1px}' ),
		'theme-page-title'          => array( 'title' => 'Título fijo', 'size' => 'xl', 'blend_mode' => 'multiply', 'text_stroke_text_stroke_type' => 'yes', 'text_stroke_text_stroke' => array( 'unit' => 'px', 'size' => 1 ) ),
		'theme-archive-title'       => array( '__dynamic__' => array( 'title' => '[elementor-tag id="aa11bb2" name="archive-title" settings="%7B%22include_context%22%3A%22yes%22%7D"]' ), 'title_color' => '#222222', 'title_hover_color_transition_duration' => array( 'unit' => 's', 'size' => 0.3 ) ),
		'theme-site-title'          => array( '__dynamic__' => array( 'title' => '[elementor-tag id="cc33dd4" name="site-title" settings="%7B%7D"]', 'link' => '[elementor-tag id="ee55ff6" name="site-url" settings="%7B%7D"]' ), 'header_size' => 'p', 'align_tablet' => 'right' ),
		'theme-site-logo'           => array( '__dynamic__' => array( 'image' => '[elementor-tag id="0a1b2c3" name="site-logo" settings="%7B%7D"]' ), 'width' => array( 'unit' => '%', 'size' => 50 ), 'width_mobile' => array( 'unit' => 'px', 'size' => 120 ), 'image_border_border' => 'solid', 'image_border_width' => array( 'unit' => 'px', 'top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1, 'isLinked' => true ), 'image_border_radius' => array( 'unit' => 'px', 'top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'isLinked' => true ), 'css_filters_css_filter' => 'custom', 'css_filters_blur' => array( 'unit' => 'px', 'size' => 1 ), 'opacity_hover' => array( 'unit' => 'px', 'size' => 0.8 ), 'hover_animation' => 'grow', 'object-fit' => 'contain', 'custom_css' => '.elementor-widget-theme-site-logo img{max-height:60px}' ),
		'theme-post-featured-image' => array( '__dynamic__' => array( 'image' => '[elementor-tag id="9f8e7d6" name="post-featured-image" settings="%7B%7D"]' ), 'image_size' => 'medium_large', 'image_custom_dimension' => array( 'width' => '', 'height' => '' ), 'caption_source' => 'attachment', 'caption_typography_typography' => 'custom', 'image_box_shadow_box_shadow_type' => 'yes', 'link_to' => 'file', 'open_lightbox' => 'yes', 'height' => array( 'unit' => 'px', 'size' => 300 ), 'object-position' => 'top center', '__globals__' => array( 'caption_typography_typography' => 'globals/typography?id=text', 'text_color' => 'globals/colors?id=primary' ) ),
		'theme-post-excerpt'        => array( '__dynamic__' => array( 'excerpt' => '[elementor-tag id="f1e2d3c" name="post-excerpt" settings="%7B%22max_length%22%3A%2220%22%7D"]' ), 'align' => 'justify', 'paragraph_spacing' => array( 'unit' => 'em', 'size' => 1 ), 'link_color' => '#0000ff', 'link_hover_color' => '#ff0000', 'link_hover_color_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ), '__globals__' => array( 'title_color' => 'globals/colors?id=text' ), 'text_shadow_text_shadow_type' => 'yes' ),
		'theme-post-content'        => array( 'align' => 'center', 'text_color' => '#333333', 'typography_typography' => 'custom', 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.8 ), '__globals__' => array( 'typography_typography' => 'globals/typography?id=secondary' ), 'custom_css' => 'selector.elementor-widget-theme-post-content p{margin:0}' ),
	);
	foreach ( $saved as $pro => $settings ) {
		$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => $pro, 'settings' => $settings, 'elements' => array() ) ) ) );
		$log  = array();
		$out  = Converter::tree( $tree, $log, true );
		$w    = $out[0]['elements'][0];
		check_g( $pairs[ $pro ]['name'] === $w['widgetType'] && 'w1' === $w['id'], "$pro: convertido al propio con el mismo id." );
		$codes = array();
		foreach ( $log as $entry ) {
			foreach ( (array) ( $entry['codes'] ?? array() ) as $code ) {
				$codes[] = $code;
			}
		}
		foreach ( $codes as $code ) {
			check_g( 'pro.unmapped' !== $code['code'] && 'pro.dropped' !== $code['code'], "$pro: {$code['code']} {$code['key']}" );
		}
		$own = $make( $pairs[ $pro ]['own'] );
		foreach ( array_keys( $w['settings'] ) as $key ) {
			if ( in_array( $key, array( '__dynamic__', '__globals__', Translator::MARKER, 'custom_css' ), true ) || 0 === strpos( $key, '_' ) ) {
				continue;
			}
			$base = preg_replace( '/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/', '', $key );
			$in_group = false;
			foreach ( array_keys( $own->groups ) as $group ) {
				$in_group = $in_group || 0 === strpos( $base, $group . '_' );
			}
			check_g( isset( $own->controls[ $base ] ) || $in_group, "$pro: la clave $key no cae en un control propio." );
		}
		foreach ( array( '__dynamic__', '__globals__' ) as $bag ) {
			check_g( ( $settings[ $bag ] ?? array() ) === ( $w['settings'][ $bag ] ?? array() ), "$pro: $bag pasa igual." );
		}
		if ( isset( $settings['custom_css'] ) ) {
			$expect = 'theme-post-content' === $pro ? $settings['custom_css'] : str_replace( 'elementor-widget-' . $pro, 'elementor-widget-' . $pairs[ $pro ]['name'], $settings['custom_css'] );
			check_g( $expect === $w['settings']['custom_css'], "$pro: CSS personalizado con la clase propia: {$w['settings']['custom_css']}" );
		}
		// El mismo marcado con los ajustes de PRO y los convertidos (los dinámicos, ya resueltos).
		$resolved = static function ( array $s ) {
			unset( $s['__dynamic__'], $s['__globals__'] );
			if ( isset( $s['image'] ) || in_array( 'image', array_keys( $s ), true ) ) {
				$s['image'] = array( 'id' => 7, 'url' => 'https://sitio.test/wp-content/uploads/a-7.png' );
			}
			return $s;
		};
		if ( 'theme-post-content' !== $pro ) {
			$sa = $resolved( $settings + ( isset( $settings['__dynamic__']['image'] ) ? array( 'image' => true ) : array() ) + ( isset( $settings['__dynamic__']['title'] ) ? array( 'title' => 'Dinámico' ) : array() ) + ( isset( $settings['__dynamic__']['excerpt'] ) ? array( 'excerpt' => 'Extracto dinámico' ) : array() ) );
			$sb = $resolved( $w['settings'] + ( isset( $settings['__dynamic__']['image'] ) ? array( 'image' => true ) : array() ) + ( isset( $settings['__dynamic__']['title'] ) ? array( 'title' => 'Dinámico' ) : array() ) + ( isset( $settings['__dynamic__']['excerpt'] ) ? array( 'excerpt' => 'Extracto dinámico' ) : array() ) );
			$GLOBALS['tt']['post'] = $GLOBALS['tt']['posts'][100];
			check_g( $render( $pairs[ $pro ]['pro'], $sa ) === $render( $pairs[ $pro ]['own'], $sb ), "$pro: lo convertido se pinta igual que PRO.\n PRO: " . $render( $pairs[ $pro ]['pro'], $sa ) . "\n propio: " . $render( $pairs[ $pro ]['own'], $sb ) );
		}
		// Por defecto (sin ajustes): sólo el marcador y lo que PRO cambia.
		$log = array();
		$d   = Converter::tree( array( array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => $pro, 'settings' => array(), 'elements' => array() ) ), $log, true );
		check_g( $pairs[ $pro ]['name'] === $d[0]['widgetType'] && ! array_diff( array_keys( $d[0]['settings'] ), array( Translator::MARKER, 'header_size', 'image_size', 'link_to' ) ), "$pro: por defecto, nada que informar: " . implode( ', ', array_keys( $d[0]['settings'] ) ) );
		$checks += 6;
	}

	echo "DIGITALÍSIMO Elements: títulos y medios de tema propios (8 widgets frente a PRO y al núcleo, mapas y conversión; {$checks} comprobaciones) en verde.\n";
}
