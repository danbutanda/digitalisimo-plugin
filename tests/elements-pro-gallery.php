<?php
/**
 * Galería avanzada (`digitalisimo-gallery`, C2 de docs/PRO_WIDGETS_OBJECTIVE.md, frente C de docs/pro/c2.md), el widget
 * propio que sustituye a la Gallery de PRO, y su mapa `modules/digitalisimo-convert/maps/gallery.php`:
 * - registro y recursos (hoja ≤ 4 KB y script ≤ 6 KB sin jQuery, sin la librería de galería ni el gestor de handlers
 *   del núcleo; movimiento reducido, foco visible, sin `--e-global-*`, `url()` ni clases del proveedor);
 * - los controles de PRO, cargando su clase real con su base simulada: el mismo nombre, tipo, valor por defecto (también
 *   por dispositivo y `device_args`), opciones, condiciones, globales, `fields_options` y las mismas declaraciones CSS
 *   en `selectors` y `selector` (con las clases propias); cobertura del mapa;
 * - marcado del servidor: cuadrícula, justificada y mampostería, una o varias galerías (filtros con `aria-pressed`,
 *   estado inicial del primer filtro, etiquetas, grupo del lightbox), enlaces (archivo con el lightbox del núcleo,
 *   URL personalizada, ninguno), superposición con título y descripción del adjunto, animaciones en línea, carga
 *   diferida, escape y el icono vacío del editor;
 * - conversión con `Converter::tree()` de ajustes reales de PRO: ningún `pro.unmapped`, cada clave cae en un control
 *   propio, sólo las aproximaciones declaradas, y lo convertido se pinta.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const NUMBER = 'number'; const DIMENSIONS = 'dimensions';
		const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const GALLERY = 'gallery'; const TAB_CONTENT = 'content'; const TAB_STYLE = 'style';
	}
	class Repeater {
		public $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function get_controls() { return $this->controls; }
	}
	class Widget_Base {
		public $settings   = array();
		public $controls   = array();
		public $groups     = array();
		public $sections   = array();
		public $id         = 'abc1234';
		private $attrs     = array();
		private $section   = '';
		public function get_id() { return $this->id; }
		public function get_settings( $key = null ) { $all = $this->get_settings_for_display(); return null === $key ? $all : ( $all[ $key ] ?? null ); }
		public function get_settings_for_display() {
			$defaults = array();
			foreach ( $this->controls as $name => $control ) {
				if ( array_key_exists( 'default', $control ) ) {
					$defaults[ $name ] = $control['default'];
				}
			}
			foreach ( $this->groups as $name => $group ) {
				if ( 'image-size' === $group['type'] ) {
					$defaults[ $name . '_size' ] = $group['default'] ?? 'full';
				}
			}
			return $this->settings + $defaults;
		}
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name ) {}
		public function end_controls_tabs() {}
		public function start_controls_tab( $name, $args ) {}
		public function end_controls_tab() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args + array( '_section' => $this->section ); }
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
		public function add_render_attribute( $element, $key = null, $value = null, $overwrite = false ) {
			if ( is_array( $key ) ) {
				foreach ( $key as $k => $v ) {
					$this->add_render_attribute( $element, $k, $v, $overwrite );
				}
				return $this;
			}
			$value = is_array( $value ) ? $value : array( $value );
			$this->attrs[ $element ][ $key ] = $overwrite ? $value : array_merge( $this->attrs[ $element ][ $key ] ?? array(), $value );
			return $this;
		}
		public function get_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $key => $values ) {
				$out[] = $key . '="' . esc_attr( implode( ' ', $values ) ) . '"';
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $element ) { echo $this->get_render_attribute_string( $element ); }
		public function add_link_attributes( $element, array $url ) {
			$this->add_render_attribute( $element, 'href', (string) ( $url['url'] ?? '' ) );
			if ( ! empty( $url['is_external'] ) ) {
				$this->add_render_attribute( $element, 'target', '_blank' );
			}
			if ( ! empty( $url['nofollow'] ) ) {
				$this->add_render_attribute( $element, 'rel', 'nofollow' );
			}
			return $this;
		}
		public function add_lightbox_data_attributes( $element, $id = null, $key = null, $group = null ) {
			if ( 'no' === $key ) {
				return $this->add_render_attribute( $element, 'data-elementor-open-lightbox', 'no' );
			}
			$attrs = array( 'data-elementor-open-lightbox' => $key );
			if ( $group ) {
				$attrs['data-elementor-lightbox-slideshow'] = $group;
			}
			if ( $id && isset( $GLOBALS['gl_posts'][ $id ] ) ) {
				$attrs['data-elementor-lightbox-title'] = $GLOBALS['gl_posts'][ $id ]->post_title;
			}
			$attrs['data-e-action-hash'] = '#elementor-action%3Aaction%3Dlightbox';
			return $this->add_render_attribute( $element, $attrs );
		}
	}
	class Group_Control_Image_Size {
		public static function get_type() { return 'image-size'; }
		// Como el núcleo con «Personalizado»: el recorte exacto de BFI_Thumb en uploads/elementor/thumbs/.
		public static function get_attachment_image_src( $id, $key, array $settings ) {
			$d = (array) ( $settings[ $key . '_custom_dimension' ] ?? array() );
			$GLOBALS['gl_crops'][] = array( $id, $key );
			return "https://sitio.test/wp-content/uploads/elementor/thumbs/img-$id-" . ( $d['width'] ?? '' ) . 'x' . ( $d['height'] ?? '' ) . '.png';
		}
	}
	class Group_Control_Css_Filter { public static function get_type() { return 'css-filter'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Utils { public static function print_validated_html_tag( $tag ) { echo $tag; } }
	class Editor_Stub { public function is_edit_mode() { return ! empty( $GLOBALS['gl_edit'] ); } }
	class Breakpoints_Stub { public function get_active_breakpoints() { return array( 'mobile' => (object) array( 'value' => 767 ), 'tablet' => (object) array( 'value' => 1024 ) ); } }
	class Plugin {
		public static $instance;
		public $breakpoints;
		public $editor;
	}
}
namespace Elementor\Core\Breakpoints {
	class Manager { const BREAKPOINT_KEY_DESKTOP = 'desktop'; const BREAKPOINT_KEY_TABLET = 'tablet'; }
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Markdown_Utils {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['gl_posts'] = array(
		11 => (object) array( 'ID' => 11, 'post_type' => 'attachment', 'post_title' => 'Faro al amanecer', 'post_excerpt' => 'Leyenda del faro', 'post_content' => 'Descripción <script>x()</script><em>larga</em>', 'alt' => 'Un faro', 'w' => 1200, 'h' => 800 ),
		12 => (object) array( 'ID' => 12, 'post_type' => 'attachment', 'post_title' => 'Bosque', 'post_excerpt' => '', 'post_content' => '', 'alt' => 'Pinos', 'w' => 800, 'h' => 1200 ),
		13 => (object) array( 'ID' => 13, 'post_type' => 'attachment', 'post_title' => 'Playa', 'post_excerpt' => 'Arena', 'post_content' => '', 'alt' => '', 'w' => 1600, 'h' => 900 ),
		14 => (object) array( 'ID' => 14, 'post_type' => 'page', 'post_title' => 'Página privada', 'post_excerpt' => 'secreto', 'post_content' => '', 'alt' => '', 'w' => 0, 'h' => 0 ),
	);
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|#)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function get_post( $id ) { return $GLOBALS['gl_posts'][ $id ] ?? null; }
	function get_post_meta( $id, $key, $single ) { return '_wp_attachment_image_alt' === $key && isset( $GLOBALS['gl_posts'][ $id ] ) ? $GLOBALS['gl_posts'][ $id ]->alt : ''; }
	function add_filter( $hook, $fn, $priority = 10, $args = 1 ) { $GLOBALS['gl_filters'][ $hook ][] = $fn; return true; }
	function remove_filter( $hook, $fn, $priority = 10 ) { $GLOBALS['gl_filters'][ $hook ] = array_values( array_filter( $GLOBALS['gl_filters'][ $hook ] ?? array(), static function ( $f ) use ( $fn ) { return $f !== $fn; } ) ); return true; }
	function wp_get_attachment_image_src( $id, $size ) {
		$image = gl_attachment_image_src( $id, $size );
		foreach ( $GLOBALS['gl_filters']['wp_get_attachment_image_src'] ?? array() as $fn ) {
			$image = $fn( $image, $id, $size, false );
		}
		return $image;
	}
	function gl_attachment_image_src( $id, $size ) {
		$post = $GLOBALS['gl_posts'][ $id ] ?? null;
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}
		if ( 'full' === $size ) {
			return array( "https://sitio.test/wp-content/uploads/img-$id.png", $post->w, $post->h, false );
		}
		$name = is_array( $size ) ? implode( 'x', $size ) : $size;
		$max  = 'medium' === $size ? 300 : 1024;
		$k    = min( 1, $max / max( $post->w, $post->h ) );
		return array( "https://sitio.test/wp-content/uploads/img-$id-$name.png", (int) round( $post->w * $k ), (int) round( $post->h * $k ), true );
	}
	function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
		$src = wp_get_attachment_image_src( $id, $size );
		if ( ! $src ) {
			return '';
		}
		$GLOBALS['gl_sizes'][] = $size;
		$attr += array( 'loading' => 'lazy' );
		if ( false === $attr['loading'] ) {
			unset( $attr['loading'] );
		}
		$out = '<img width="' . $src[1] . '" height="' . $src[2] . '" src="' . $src[0] . '"';
		foreach ( $attr as $k => $v ) {
			$out .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		// Como WordPress: sin `srcset` si el archivo no es un tamaño del adjunto (el recorte del núcleo).
		return $out . ( str_contains( $src[0], '/elementor/thumbs/' ) ? '>' : ' srcset="https://sitio.test/wp-content/uploads/img-' . $id . '-768.png 768w" sizes="(max-width: 300px) 100vw, 300px">' );
	}
	function check_gl( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->breakpoints = new \Elementor\Breakpoints_Stub();
	\Elementor\Plugin::$instance->editor      = new \Elementor\Editor_Stub();
	$root = __DIR__ . '/../digitalisimo-elements/';
	define( 'DIGITALISIMO_ELEMENTS_FILE', $root . 'pro-elements.php' );
	require $root . 'modules/digitalisimo-content/class-module.php';
	require $root . 'modules/digitalisimo-content/widgets/class-gallery.php';
	require $root . 'modules/gallery/widgets/gallery.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content_Module;
	use Digitalisimo\Elements\Content\Widgets\Gallery;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$widget = static function ( array $settings = array() ) {
		$w = new Gallery();
		( digi_test_accessible( new ReflectionMethod( $w, 'register_controls' ) ) )->invoke( $w );
		$w->settings = $settings;
		return $w;
	};
	$render = static function ( array $settings ) use ( $widget ) {
		$w = $widget( $settings );
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $w, 'render' ) ) )->invoke( $w );
		return (string) ob_get_clean();
	};
	$images = static function ( array $ids ) {
		return array_map( static function ( $id ) { return array( 'id' => $id, 'url' => "https://sitio.test/wp-content/uploads/img-$id.png" ); }, $ids );
	};
	$count = static function ( $needle, $html ) { return substr_count( $html, $needle ); };

	// 1. Registro y recursos.
	$w = $widget();
	check_gl( 'digitalisimo-gallery' === $w->get_name() && 'Galería avanzada' === $w->get_title() && array( 'digitalisimo' ) === $w->get_categories(), 'Nombre, título y categoría propios.' );
	$filter_sheets = array( 'digitalisimo-gallery-filters', 'digitalisimo-gallery-filters-framed', 'digitalisimo-gallery-filters-background' );
	check_gl( array_merge( array( 'digitalisimo-gallery' ), $filter_sheets ) === $w->get_style_depends() && array( 'digitalisimo-gallery' ) === $w->get_script_depends(), 'En el editor (sin instancia), su hoja y las de los filtros, y su script: ' . implode( ',', $w->get_style_depends() ) . ' / ' . implode( ',', $w->get_script_depends() ) );
	check_gl( array( 'digitalisimo-gallery' ) === Gallery::styles_for( array() ) && array( 'digitalisimo-gallery', 'digitalisimo-gallery-filters' ) === Gallery::styles_for( array( 'gallery_type' => 'multiple' ) ) && array( 'digitalisimo-gallery', 'digitalisimo-gallery-filters', 'digitalisimo-gallery-filters-framed' ) === Gallery::styles_for( array( 'gallery_type' => 'multiple', 'pointer' => 'framed' ) ) && array( 'digitalisimo-gallery', 'digitalisimo-gallery-filters', 'digitalisimo-gallery-filters-background' ) === Gallery::styles_for( array( 'gallery_type' => 'multiple', 'pointer' => 'background' ) ) && array( 'digitalisimo-gallery', 'digitalisimo-gallery-filters' ) === Gallery::styles_for( array( 'gallery_type' => 'multiple', 'pointer' => 'text' ) ), 'Las hojas de los filtros sólo con «Varias galerías», y la del marco o la del fondo sólo con ese indicador.' );
	foreach ( $filter_sheets as $handle ) {
		$file = Content_Module::STYLES[ $handle ] ?? '';
		$text = (string) @file_get_contents( $root . $file );
		check_gl( '' !== $text && strlen( $text ) <= 4096 && str_contains( $text, '@media (prefers-reduced-motion:reduce)' ) && ! preg_match( '/url\(|--e-global|elementor|e-gallery|e--pointer|e--animation/i', $text ), "$handle: registrada por el módulo, ≤ 4 KB (" . strlen( $text ) . ' B), con movimiento reducido y sin clases ni variables del proveedor.' );
	}
	check_gl( 'widgets/class-gallery.php' === Content_Module::WIDGETS['digitalisimo-gallery']['file'] && Gallery::class === Content_Module::WIDGETS['digitalisimo-gallery']['class'] && 'Galería avanzada' === Content_Module::TITLES['digitalisimo-gallery'], 'El módulo de contenido registra la clase y el título.' );
	$css     = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/gallery.css' );
	$filters = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/gallery-filters.css' );
	check_gl( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_gl( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( str_replace( '.elementor-widget-digitalisimo-gallery', '', $css ), 'elementor' ) && ! str_contains( $css, 'e-gallery' ), 'La hoja no usa el kit, url() ni clases del proveedor o de la librería de galería (sólo la del envoltorio del widget).' );
	check_gl( str_starts_with( $css, ':is(.e-con,.e-con-inner)>.elementor-widget-digitalisimo-gallery{width:var(--container-widget-width);--flex-grow:var(--container-widget-flex-grow)}' ), 'En un contenedor flexible, el widget ocupa el ancho del contenedor como el de PRO (si no, su alto se calcula con el tamaño propio de las imágenes).' );
	check_gl( (bool) preg_match( '/@media \(prefers-reduced-motion:reduce\)\{[^}]*\{transition:none!important\}\}/', $css ) && str_contains( $css, '.digi-gallery__item:focus-visible{outline:2px solid;outline-offset:2px}' ) && str_contains( $filters, '.digi-gallery__filter:focus-visible{outline:2px solid;outline-offset:2px}' ) && str_contains( $filters, 'min-height:24px' ), 'Movimiento reducido, foco visible propio y filtros de 24 px de alto como mínimo.' );
	preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css . $filters, $colors );
	check_gl( array() === array_diff( array_unique( array_map( 'strtolower', $colors[0] ) ), array( '#fff', '#3f444b' ) ), 'Sólo los colores que PRO usaba por defecto: ' . implode( ', ', $colors[0] ) );
	foreach ( array( 'padding:7px 14px', 'font-weight:500', 'margin-bottom:20px', '--space-between:10px', 'transform-origin:center top', 'aspect-ratio:var(--digi-gallery-ratio,3/2)', 'repeat(var(--columns,4)', 'var(--title-text-color,#fff)', 'border:solid var(--image-border-width) var(--image-border-color)' ) as $look ) {
		check_gl( str_contains( $css . $filters, $look ), "La hoja reproduce el aspecto de PRO ($look)." );
	}
	// Carga diferida como la librería de PRO (`.e-gallery--lazyload .e-gallery-image:not(.e-gallery-image-loaded)`): cada
	// imagen entra desde la mitad y sin opacidad, con la transición de la imagen.
	check_gl( str_contains( $css, '.digi-gallery__container--lazy .digi-gallery__image:not(.is-loaded){filter:opacity(0);transform:scale(.5)}' ) && str_contains( $css, 'transition-duration:var(--image-transition-duration);transition-property:filter,transform' ), 'Carga diferida: entrada con zoom y fundido de la librería de PRO.' );
	// Indicadores de los filtros: las reglas e--pointer-*/e--animation-* de PRO (hoja de su Menú).
	foreach ( array( '[data-digi-fx=line-grow]>:not(:hover,:focus,[aria-pressed=true]):before,[data-digi-fx=line-grow]>:not(:hover,:focus,[aria-pressed=true]):after{height:0;left:50%;width:0}', '[data-digi-fx=line-slide]>:not(:hover,:focus,[aria-pressed=true]):after{inset-inline-start:100%;width:10px}', '[data-digi-fx=line-drop-in]>:not(:hover,:focus,[aria-pressed=true]):before{top:-10px}', '[data-digi-fx=text-grow]>:is(:hover,:focus){transform:scale(1.2)}', '.digi-gallery [data-digi-fx$=-none]>*', 'transition:.3s cubic-bezier(.58,.3,.005,1)' ) as $rule ) {
		check_gl( str_contains( $filters, $rule ), "Indicador de los filtros como PRO: $rule" );
	}
	$framed = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/gallery-filters-framed.css' );
	$bg     = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/gallery-filters-background.css' );
	check_gl( str_contains( $framed, '[data-digi-fx=framed-grow]>:not(:hover,:focus,[aria-pressed=true]):before{transform:scale(.75)}' ) && str_contains( $framed, '[data-digi-fx=framed-draw]>:is(:hover,:focus,[aria-pressed=true]):after{transition:opacity .02s .2s,height .1s .2s,width .1s .3s}' ) && str_contains( $bg, '[data-digi-fx=background-sweep-left]>:not(:hover,:focus,[aria-pressed=true]):before{left:100%}' ) && str_contains( $bg, '[data-digi-fx=background-shutter-in-horizontal]>:is(:hover,:focus,[aria-pressed=true]):after{right:50%}' ), 'Marco y fondo con las animaciones de PRO.' );
	$js = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/js/gallery.js' );
	check_gl( str_contains( $js, "box.hasAttribute('data-digi-lazy')" ) && str_contains( $js, "img.classList.add('is-loaded')" ) && str_contains( $js, "'digi-gallery__container--lazy'" ), 'El script marca la carga diferida y cada imagen al cargarse.' );
	check_gl( strlen( $js ) <= 6144 && ! preg_match( '/\bjquery\s*\(|\bjquery\.[a-z]|\$\(|EGallery|elementsHandler|swiper/i', $js ) && str_contains( $js, 'ResizeObserver' ) && str_contains( $js, "'aria-pressed'" ) && str_contains( $js, 'data-elementor-lightbox-slideshow' ), 'El script pesa ' . strlen( $js ) . ' B (tope 6 144), sin jQuery, sin la librería de galería ni el gestor de handlers.' );
	$source = (string) file_get_contents( $root . 'modules/digitalisimo-content/widgets/class-gallery.php' );
	check_gl( ! preg_match( '/jquery\(|swiper|wp_enqueue_script|e-gallery|<iframe|ElementorPro/i', $source ), 'El widget no usa jQuery, Swiper, la librería de galería ni clases de PRO.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-content/widgets/class-gallery.php', 'modules/digitalisimo-content/assets/css/gallery.css', 'modules/digitalisimo-content/assets/js/gallery.js', 'modules/digitalisimo-convert/maps/gallery.php' ) as $file ) {
		check_gl( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file no nombra lo que la guarda del empaquetado prohíbe." );
	}

	// 2. Controles: los de la clase real de PRO frente a los propios.
	$map = require $root . 'modules/digitalisimo-convert/maps/gallery.php';
	$pro = new \ElementorPro\Modules\Gallery\Widgets\Gallery();
	( digi_test_accessible( new ReflectionMethod( $pro, 'register_controls' ) ) )->invoke( $pro );
	$classes = $map['classes'];
	$own_selector = static function ( string $selector ) use ( $classes ) {
		return str_replace( ':focus ', ':focus-visible ', Translator::rewrite_classes( $selector, $classes ) );
	};
	$same = array( 'type', 'default', 'tablet_default', 'mobile_default', 'device_args', 'condition', 'conditions', 'global', 'min', 'max', 'step', 'size_units', 'return_value', 'responsive', 'required', 'frontend_available', 'separator', 'devices', 'title_field', 'dynamic', 'selectors_dictionary', 'style_transfer', 'ai', '_section' );
	check_gl( array_keys( $pro->controls ) === array_keys( $w->controls ), 'Los mismos controles en el mismo orden: ' . implode( ', ', array_diff( array_keys( $pro->controls ), array_keys( $w->controls ) ) ) . ' / ' . implode( ', ', array_diff( array_keys( $w->controls ), array_keys( $pro->controls ) ) ) );
	check_gl( array_keys( $pro->groups ) === array_keys( $w->groups ) && array_keys( $pro->sections ) === array_keys( $w->sections ), 'Los mismos grupos y secciones en el mismo orden.' );
	foreach ( $pro->sections as $name => $section ) {
		check_gl( ( $section['condition'] ?? null ) === ( $w->sections[ $name ]['condition'] ?? null ) && ( $section['tab'] ?? null ) === ( $w->sections[ $name ]['tab'] ?? null ), "Sección $name: misma pestaña y condición." );
	}
	foreach ( $pro->controls as $name => $control ) {
		$mine = $w->controls[ $name ];
		foreach ( $same as $arg ) {
			if ( 'selectors_dictionary' === $arg && in_array( $name, array( 'aspect_ratio' ), true ) ) {
				continue; // PRO no tenía selectors en la proporción (la aplicaba su script); la propia la traduce a CSS.
			}
			if ( 'default' === $arg && in_array( $name, array( 'galleries', 'show_all_galleries_label' ), true ) ) {
				// Textos por defecto traducidos («New Gallery» → «Nueva galería», «All» → «Todas»): el mapa conserva los de PRO.
				check_gl( count( (array) $control['default'] ) === count( (array) $mine['default'] ), "$name: default con la misma forma." );
				continue;
			}
			check_gl( ( $control[ $arg ] ?? null ) === ( $mine[ $arg ] ?? null ), "$name: $arg distinto de PRO (" . var_export( $control[ $arg ] ?? null, true ) . ' ≠ ' . var_export( $mine[ $arg ] ?? null, true ) . ').' );
		}
		check_gl( array_keys( (array) ( $control['options'] ?? array() ) ) === array_keys( (array) ( $mine['options'] ?? array() ) ), "$name: las mismas opciones." );
		$pro_groups = array_map( static function ( $g ) { return array_keys( $g['options'] ); }, (array) ( $control['groups'] ?? array() ) );
		$own_groups = array_map( static function ( $g ) { return array_keys( $g['options'] ); }, (array) ( $mine['groups'] ?? array() ) );
		check_gl( $pro_groups === $own_groups, "$name: los mismos grupos de opciones." );
		foreach ( (array) ( $control['selectors'] ?? array() ) as $selector => $value ) {
			$target = $own_selector( $selector );
			check_gl( isset( $mine['selectors'][ $target ] ) && $mine['selectors'][ $target ] === $value, "$name: falta la declaración de PRO «{$target} → {$value}»." );
		}
		if ( isset( $control['fields'] ) ) {
			foreach ( $control['fields'] as $field => $args ) {
				check_gl( isset( $mine['fields'][ $field ] ) && ( $args['type'] ?? null ) === ( $mine['fields'][ $field ]['type'] ?? null ) && isset( $args['default'] ) === isset( $mine['fields'][ $field ]['default'] ), "$name.$field: el mismo campo del repetidor." );
			}
			check_gl( array_keys( $control['fields'] ) === array_keys( $mine['fields'] ), "$name: los mismos campos del repetidor." );
		}
	}
	// Lo que la propia añade: variables CSS de columnas, altura de fila, separación y proporción (PRO las ponía su script).
	check_gl( array( '{{WRAPPER}}' => '--columns: {{VALUE}}' ) === $w->controls['columns']['selectors'] && array( '{{WRAPPER}}' => '--hgap: {{SIZE}}px; --vgap: {{SIZE}}px' ) === $w->controls['gap']['selectors'] && array( '{{WRAPPER}}' => '--digi-gallery-row: {{SIZE}}' ) === $w->controls['ideal_row_height']['selectors'] && '3/2' === $w->controls['aspect_ratio']['selectors_dictionary']['3:2'], 'Columnas, separación, altura de fila y proporción como variables del dispositivo.' );
	check_gl( array( 'mobile' => array( 'default' => array( 'size' => 150 ) ), 'tablet' => array( 'default' => array( 'size' => 150 ) ) ) === $w->controls['ideal_row_height']['device_args'] && 'digi-gallery--filter-align-' === $w->controls['align_filter_bar_items']['prefix_class'], 'Valores por dispositivo de PRO y clase de alineación propia.' );
	foreach ( $pro->groups as $name => $group ) {
		$mine = $w->groups[ $name ];
		foreach ( array( 'type', 'default', 'global', 'types', 'exclude', 'condition', 'fields_options' ) as $arg ) {
			if ( 'fields_options' === $arg ) {
				$a = $group[ $arg ] ?? array();
				$b = $mine[ $arg ] ?? array();
				foreach ( $a as $field => $opts ) {
					unset( $a[ $field ]['label'] );
					unset( $b[ $field ]['label'] );
				}
				check_gl( $a === $b, "$name: fields_options distinto de PRO." );
				continue;
			}
			check_gl( ( $group[ $arg ] ?? null ) === ( $mine[ $arg ] ?? null ), "Grupo $name: $arg distinto de PRO." );
		}
		if ( isset( $group['selector'] ) ) {
			check_gl( $own_selector( $group['selector'] ) === $mine['selector'], "Grupo $name: selector «{$mine['selector']}» ≠ «" . $own_selector( $group['selector'] ) . '».' );
		}
	}
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), $map['ui'], array_keys( $map['repeaters'] ), $map['repeaters']['galleries']['keep'] );
	foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ), array_keys( $pro->controls['galleries']['fields'] ) ) as $name ) {
		check_gl( in_array( $name, $declared, true ), "$name: el mapa no lo declara." );
	}
	check_gl( ! isset( $map['approx'] ), 'Sin aproximaciones: la carga diferida y los indicadores de los filtros son los de PRO.' );
	foreach ( $map['keep'] as $name ) {
		check_gl( isset( $w->controls[ $name ] ), "keep/approx $name: no es un control de la Galería avanzada." );
	}
	foreach ( $map['ui'] as $name ) {
		check_gl( 'heading' === $w->controls[ $name ]['type'], "$name: encabezado del panel." );
	}
	foreach ( $map['classes'] as $from => $to ) {
		check_gl( (bool) preg_match( '/^(\.[a-z0-9_-]+|\[hidden\])$/', $to ) && ( '[hidden]' === $to || str_contains( $source . $css, substr( $to, 1 ) ) || 'digi-gallery--filter-align-' === substr( $to, 1, 27 ) || '.elementor-widget-digitalisimo-gallery' === $to ), "Clase de destino $to existe en el widget." );
	}

	// 3. Marcado del servidor.
	$html = $render( array( 'gallery' => $images( array( 11, 12, 13, 14 ) ) ) );
	check_gl( str_starts_with( $html, '<div class="digi-gallery digi-gallery--grid"><div id="digi-gallery-abc1234" class="digi-gallery__container" data-digi-lazy>' ) && ! str_contains( $html, 'data-digi-layout' ) && ! str_contains( $html, 'digi-gallery__filters' ), 'Cuadrícula por defecto: sin script de disposición ni filtros.' );
	check_gl( 3 === $count( '<a class="digi-gallery__item"', $html ) && ! str_contains( $html, 'img-14' ), 'Una entrada por imagen adjunta (lo que no es un adjunto se omite).' );
	check_gl( str_contains( $html, 'href="https://sitio.test/wp-content/uploads/img-11.png"' ) && str_contains( $html, 'data-elementor-open-lightbox="default" data-elementor-lightbox-slideshow="abc1234" data-elementor-lightbox-title="Faro al amanecer"' ), 'Archivo multimedia: enlace al archivo completo con el lightbox del núcleo agrupado por el widget.' );
	check_gl( str_contains( $html, 'data-w="300" data-h="200"' ) && str_contains( $html, 'data-w="200" data-h="300"' ) && str_contains( $html, 'src="https://sitio.test/wp-content/uploads/img-11-medium.png" class="digi-gallery__image" alt="Un faro" decoding="async" loading="lazy" srcset=' ), 'Imagen del tamaño elegido (medium) con wp_get_attachment_image(): alt del adjunto, decoding, loading y srcset; medidas para el diseño.' );
	check_gl( 3 === $count( '<div class="digi-gallery__overlay"></div>', $html ) && ! str_contains( $html, 'digi-gallery__content' ) && ! str_contains( $html, 'tabindex' ) && ! str_contains( $html, 'digi-gallery__fx' ), 'Fondo de la superposición sin contenido ni animaciones por defecto.' );
	$html = $render( array( 'gallery' => $images( array( 11, 12 ) ), 'gallery_layout' => 'justified', 'lazyload' => '', 'link_to' => '', 'overlay_background' => '', 'overlay_title' => 'title', 'overlay_description' => 'description', 'thumbnail_image_size' => 'large' ) );
	check_gl( str_contains( $html, 'class="digi-gallery digi-gallery--justified"' ) && str_contains( $html, 'data-digi-layout="justified"' ) && 2 === $count( '<div class="digi-gallery__item" data-w=', $html ) && 2 === $count( 'tabindex="0"', $html ) && ! str_contains( $html, 'loading=' ) && ! str_contains( $html, 'data-digi-lazy' ) && ! str_contains( $html, 'overlay"' ) && str_contains( $html, 'img-11-large.png' ), 'Justificada sin enlace: div enfocable con título, sin carga diferida (ni su entrada animada) ni fondo.' );
	check_gl( str_contains( $html, '<div class="digi-gallery__content"><div class="digi-gallery__title digi-gallery__fx" style="--_o0:0">Faro al amanecer</div><div class="digi-gallery__description digi-gallery__fx" style="--_o0:0">Descripción <em>larga</em></div></div>' ) && str_contains( $html, '<div class="digi-gallery__content"><div class="digi-gallery__title digi-gallery__fx" style="--_o0:0">Bosque</div></div>' ), 'Título y descripción del adjunto (sin script), con la animación por defecto (aparecer) en línea; lo vacío no se pinta.' );
	$html = $render( array( 'gallery' => $images( array( 11 ) ), 'gallery_layout' => 'masonry', 'image_hover_animation' => 'move-contained-left', 'background_overlay_hover_animation' => 'exit-to-top', 'overlay_title' => 'caption', 'content_hover_animation' => 'grow', 'content_sequenced_animation' => 'yes', 'link_to' => 'custom', 'url' => array( 'url' => 'https://destino.test/x', 'is_external' => 'on', 'nofollow' => '' ) ) );
	check_gl( str_contains( $html, 'data-digi-layout="masonry"' ) && str_contains( $html, '<a class="digi-gallery__item" data-w="300" data-h="200" href="https://destino.test/x" target="_blank" rel="noopener noreferrer">' ) && ! str_contains( $html, 'lightbox' ), 'URL personalizada externa con rel y sin lightbox.' );
	check_gl( str_contains( $html, 'class="digi-gallery__image digi-gallery__fx" alt="Un faro" decoding="async" loading="lazy" style="--_t0:scale(1.2);--_t1:scale(1.2) translate(-8%,0)"' ) && str_contains( $html, '<div class="digi-gallery__overlay digi-gallery__fx" style="--_t1:translateY(-600px);--_o1:0"></div>' ) && str_contains( $html, '<div class="digi-gallery__content digi-gallery__content--sequenced"><div class="digi-gallery__title digi-gallery__fx" style="--_t1:scale(1.1)">Leyenda del faro</div>' ), 'Animaciones de imagen, fondo y contenido (secuenciada) en línea.' );
	foreach ( Gallery::FX as $name => $style ) {
		check_gl( (bool) preg_match( '/^(--_[to][01]:[^;"<>]+;?)+$/', $style ), "$name: estilo de animación sin caracteres fuera de lugar." );
	}

	// Varias galerías: filtros, etiquetas, estado inicial y grupo del lightbox.
	$multi = array(
		'gallery_type' => 'multiple',
		'galleries'    => array(
			array( '_id' => 'g1', 'gallery_title' => 'Faros', 'multiple_gallery' => $images( array( 11, 12 ) ) ),
			array( '_id' => 'g2', 'gallery_title' => 'Vacía', 'multiple_gallery' => array() ),
			array( '_id' => 'g3', 'gallery_title' => 'Playas <script>x()</script>', 'multiple_gallery' => $images( array( 12, 13 ) ) ),
		),
	);
	$html = $render( $multi + array( 'show_all_galleries_label' => 'Todas' ) );
	check_gl( str_contains( $html, '<div class="digi-gallery__filters digi-gallery__filters--underline" data-digi-fx="line-fade" role="group" aria-label="Filtro de la galería"><button type="button" class="digi-gallery__filter digi-gallery__filter--active" data-digi-filter="all" aria-controls="digi-gallery-abc1234" aria-pressed="true">Todas</button><button type="button" class="digi-gallery__filter" data-digi-filter="0" aria-controls="digi-gallery-abc1234" aria-pressed="false">Faros</button><button type="button" class="digi-gallery__filter" data-digi-filter="2" aria-controls="digi-gallery-abc1234" aria-pressed="false">Playas </button></div>' ), 'Filtros: «Todas» activo, una por galería no vacía con su índice de PRO y el título sin script.' );
	check_gl( 3 === $count( 'class="digi-gallery__item"', $html ) && str_contains( $html, 'data-digi-tags="0,2"' ) && 3 === $count( 'data-elementor-lightbox-slideshow="all_abc1234"', $html ) && ! str_contains( $html, 'hidden' ), 'Una imagen repetida en dos galerías se pinta una vez con sus dos etiquetas; todas visibles en el grupo «all».' );
	$html = $render( $multi + array( 'show_all_galleries' => '', 'pointer' => 'framed', 'animation_framed' => 'none' ) );
	check_gl( str_contains( $html, '<div class="digi-gallery__filters digi-gallery__filters--framed" data-digi-fx="framed-none" role="group"' ) && ! str_contains( $html, 'data-digi-filter="all"' ) && str_contains( $html, 'data-digi-filter="0" aria-controls="digi-gallery-abc1234" aria-pressed="true"' ), 'Sin «Todas», el primer filtro activo; indicador de marco.' );
	check_gl( 1 === $count( 'hidden="hidden"', $html ) && str_contains( $html, 'data-digi-tags="2" hidden="hidden" href="https://sitio.test/wp-content/uploads/img-13.png" data-elementor-open-lightbox="default" data-elementor-lightbox-slideshow="abc1234"' ) && 2 === $count( 'data-elementor-lightbox-slideshow="0_abc1234"', $html ), 'Lo que no está en el filtro activo empieza oculto; el grupo del lightbox sólo reúne lo visible.' );
	foreach ( array( 'text|text-grow' => array( 'pointer' => 'text' ), 'text|text-rotate' => array( 'pointer' => 'text', 'animation_text' => 'rotate' ), 'double-line|line-drop-out' => array( 'pointer' => 'double-line', 'animation_line' => 'drop-out' ), 'background|background-shutter-in-vertical' => array( 'pointer' => 'background', 'animation_background' => 'shutter-in-vertical' ), 'overline|line-fade' => array( 'pointer' => 'overline', 'animation_line' => 'x"><script>' ) ) as $want => $extra ) {
		list( $pointer, $fx ) = explode( '|', $want );
		check_gl( str_contains( $render( $multi + $extra ), '<div class="digi-gallery__filters digi-gallery__filters--' . $pointer . '" data-digi-fx="' . $fx . '" role="group"' ), "Indicador $pointer: data-digi-fx=$fx (la animación de su familia, como la clase de PRO; un valor desconocido vuelve al de por defecto)." );
	}
	check_gl( str_contains( $render( $multi + array( 'pointer' => 'none' ) ), '<div class="digi-gallery__filters digi-gallery__filters--none" role="group"' ), 'Sin indicador, sin animación.' );
	// Grosor de las líneas: en PRO miden siempre 3 px (la regla de «Grosor del indicador» de su galería no coincide con
	// ellas). Lo convertido lo conserva; lo nuevo y el marco convertido aplican el grosor elegido.
	$wide = $multi + array( 'pointer_width' => array( 'unit' => 'px', 'size' => 6, 'sizes' => array() ) );
	check_gl( str_contains( $render( $wide + array( '_digitalisimo_pro' => 'gallery' ) ), 'data-digi-fx="line-fade" style="--galleries-pointer-border-width:3px" role="group"' ) && str_contains( $render( $wide + array( '_digitalisimo_pro' => 'gallery', 'pointer' => 'double-line', 'animation_line' => 'grow' ) ), 'data-digi-fx="line-grow" style="--galleries-pointer-border-width:3px" role="group"' ) && ! str_contains( $render( $wide ), '--galleries-pointer-border-width' ) && ! str_contains( $render( $wide + array( '_digitalisimo_pro' => 'gallery', 'pointer' => 'framed' ) ), '--galleries-pointer-border-width' ), 'Lo convertido de PRO conserva las líneas de 3 px de PRO; lo nuevo y el marco convertido aplican «Grosor del indicador».' );
	$html = $render( array( 'gallery_type' => 'multiple', 'galleries' => array( array( '_id' => 'g', 'gallery_title' => 'X', 'multiple_gallery' => array() ) ) ) );
	check_gl( '' === $html, 'Sin imágenes, nada en el front.' );
	$GLOBALS['gl_edit'] = true;
	$html = $render( array() );
	check_gl( '<i class="elementor-widget-empty-icon eicon-gallery-justified" aria-hidden="true"></i>' === $html, 'En el editor, el icono del widget vacío.' );
	$html = $render( array( 'gallery' => $images( array( 11 ) ) ) );
	check_gl( str_contains( $html, 'class="digi-gallery__item elementor-clickable"' ), 'En el editor, los enlaces del lightbox son pulsables como en PRO.' );
	$GLOBALS['gl_edit'] = false;
	$html = $render( array( 'gallery' => $images( array( 11, 12, 13 ) ), 'order_by' => 'random' ) );
	check_gl( 3 === $count( 'class="digi-gallery__item"', $html ), 'Orden aleatorio: las mismas imágenes.' );
	$GLOBALS['gl_sizes'] = array();
	$html = $render( array( 'gallery' => $images( array( 11 ) ), 'thumbnail_image_size' => 'custom', 'thumbnail_image_custom_dimension' => array( 'width' => '400', 'height' => '100' ) ) );
	check_gl( str_contains( $html, 'data-w="400" data-h="100"' ) && array( 'full' ) === $GLOBALS['gl_sizes'], 'Tamaño personalizado: el diseño usa sus medidas, como PRO.' );
	check_gl( str_contains( $html, '<img width="400" height="100" src="https://sitio.test/wp-content/uploads/elementor/thumbs/img-11-400x100.png" class="digi-gallery__image" alt="Un faro" decoding="async" loading="lazy">' ) && array( array( 11, 'thumbnail_image' ) ) === $GLOBALS['gl_crops'] && array() === ( $GLOBALS['gl_filters']['wp_get_attachment_image_src'] ?? array() ), 'Tamaño personalizado: el recorte exacto del núcleo (como PRO), con sus medidas y sin srcset; el filtro se quita al pintar.' );
	$html = $render( array( 'gallery' => $images( array( 11 ) ), 'open_lightbox' => 'no' ) );
	check_gl( str_contains( $html, 'data-elementor-open-lightbox="no"' ), 'Lightbox «No» como en PRO.' );

	// 4. Conversión de la Gallery de PRO con el mapa.
	Registry::reset();
	check_gl( null !== Registry::map( 'gallery', true ) && 'digitalisimo-gallery' === $map['target'] && 'hide' === $map['panel'], 'El mapa existe y oculta el widget de PRO.' );
	$own    = $widget();
	$fields = array_keys( $own->controls['galleries']['fields'] );
	$lands  = static function ( $key, $in_row = false ) use ( $own, $fields ) {
		$base = Translator::base( (string) $key );
		if ( Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		if ( $in_row ) {
			return in_array( $base, $fields, true );
		}
		if ( isset( $own->controls[ $base ] ) ) {
			return true;
		}
		foreach ( array_keys( $own->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$gallery = $images( array( 11, 12, 13 ) );
	$cases   = array(
		'defaults'  => array( 'gallery' => $gallery ),
		'justified' => array( 'gallery' => $gallery, 'gallery_layout' => 'justified', 'ideal_row_height' => array( 'unit' => 'px', 'size' => 180, 'sizes' => array() ), 'ideal_row_height_mobile' => array( 'unit' => 'px', 'size' => 120, 'sizes' => array() ), 'gap' => array( 'unit' => 'px', 'size' => 6, 'sizes' => array() ), 'gap_tablet' => array( 'unit' => 'px', 'size' => 4, 'sizes' => array() ), 'lazyload' => '', 'link_to' => 'custom', 'url' => array( 'url' => 'https://a.test/', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ), 'thumbnail_image_size' => 'custom', 'thumbnail_image_custom_dimension' => array( 'width' => '400', 'height' => '300' ) ),
		'masonry'   => array( 'gallery' => $gallery, 'gallery_layout' => 'masonry', 'columns' => 3, 'columns_tablet' => 2, 'columns_mobile' => 1, 'order_by' => 'random', 'open_lightbox' => 'yes', 'image_hover_animation' => 'grow', 'image_animation_duration' => array( 'unit' => 'px', 'size' => 400, 'sizes' => array() ), 'image_border_color' => '#ff0000', 'image_border_width' => array( 'unit' => 'px', 'size' => 2, 'sizes' => array() ), 'image_border_radius' => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ), 'image_border_color_hover' => '#00ff00', 'image_border_radius_hover' => array( 'unit' => '%', 'size' => 50, 'sizes' => array() ), 'image_css_filters_css_filter' => 'custom', 'image_css_filters_blur' => array( 'unit' => 'px', 'size' => 2, 'sizes' => array() ), 'image_css_filters_hover_css_filter' => 'custom', 'image_css_filters_hover_saturate' => array( 'unit' => 'px', 'size' => 0, 'sizes' => array() ) ),
		'multiple'  => array( 'gallery_type' => 'multiple', 'galleries' => array( array( '_id' => 'a1', 'gallery_title' => 'Uno', 'multiple_gallery' => $gallery ), array( '_id' => 'a2', 'gallery_title' => 'Dos', 'multiple_gallery' => array_slice( $gallery, 1 ) ) ), 'show_all_galleries_label' => 'Todo', 'pointer' => 'background', 'animation_background' => 'sweep-left', 'align_filter_bar_items' => 'left', 'galleries_title_color_normal' => '#333333', 'galleries_title_color_hover_pointer_bg' => '#ffffff', 'galleries_pointer_color_hover' => '#000000', 'galleries_title_color_active' => '#111111', 'galleries_pointer_color_active' => '#222222', 'galleries_titles_typography_typography' => 'custom', 'galleries_titles_typography_font_size' => array( 'unit' => 'px', 'size' => 18, 'sizes' => array() ), 'galleries_titles_space_between' => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ), 'galleries_titles_gap' => array( 'unit' => 'px', 'size' => 30, 'sizes' => array() ), 'pointer_width' => array( 'unit' => 'px', 'size' => 4, 'sizes' => array() ) ),
		'overlay'   => array( 'gallery' => $gallery, 'overlay_title' => 'title', 'overlay_description' => 'caption', 'overlay_background_background' => 'classic', 'overlay_background_color' => 'rgba(0,0,0,.2)', 'overlay_background_hover_background' => 'gradient', 'overlay_background_hover_color' => '#000000', 'overlay_background_hover_color_b' => '#ffffff', 'image_blend_mode' => 'multiply', 'background_overlay_hover_animation' => 'enter-from-bottom', 'background_overlay_animation_duration' => array( 'unit' => 'px', 'size' => 500, 'sizes' => array() ), 'content_alignment' => 'left', 'content_vertical_position' => 'bottom', 'content_padding' => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ), 'content_padding_mobile' => array( 'unit' => 'px', 'size' => 6, 'sizes' => array() ), 'title_color' => '#ffd166', 'title_typography_typography' => 'custom', 'title_typography_font_weight' => '400', 'title_spacing' => array( 'unit' => 'px', 'size' => 4, 'sizes' => array() ), 'description_color' => '#eeeeee', 'description_typography_typography' => 'custom', 'description_typography_font_size' => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ), 'content_hover_animation' => 'enter-zoom-in', 'content_animation_duration' => array( 'unit' => 'px', 'size' => 300, 'sizes' => array() ), 'content_sequenced_animation' => 'yes' ),
		'dynamic'   => array( 'gallery_type' => 'multiple', 'galleries' => array( array( '_id' => 'd1', '__dynamic__' => array( 'gallery_title' => '[elementor-tag id="t1" name="post-title" settings="%7B%7D"]', 'multiple_gallery' => '[elementor-tag id="t2" name="acf-gallery" settings="%7B%7D"]' ) ) ), '__dynamic__' => array( 'show_all_galleries_label' => '[elementor-tag id="t3" name="site-title" settings="%7B%7D"]' ) ),
		'globals'   => array( 'gallery' => $gallery, 'overlay_title' => 'alt', '__globals__' => array( 'title_typography_typography' => 'globals/typography?id=secondary', 'title_color' => 'globals/colors?id=primary', 'overlay_background_hover_color' => 'globals/colors?id=accent', 'galleries_title_color_normal' => 'globals/colors?id=text', 'galleries_pointer_color_active' => 'globals/colors?id=secondary' ) ),
		'css'       => array( 'gallery' => $gallery, 'lazyload' => 'yes', 'custom_css' => "selector .elementor-gallery-item{border-radius:12px}\nselector .e-gallery-item:hover .elementor-gallery-item__overlay{opacity:.5}\nselector .elementor-gallery-title.elementor-item-active{font-weight:700}\n.elementor-widget-gallery .e-gallery-image{filter:grayscale(1)}", '_css_classes' => 'mi-galeria' ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'gallery', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_gl( count( $cases ) === count( $log ), 'Se convierte cada Gallery del árbol.' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_gl( 'digitalisimo-gallery' === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa a la Galería avanzada con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_gl( $lands( $key ), $element['id'] . ": la clave $key no cae en un control de la Galería avanzada." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_gl( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control de la Galería avanzada." );
			}
		}
		foreach ( (array) ( $element['settings']['galleries'] ?? array() ) as $row ) {
			foreach ( $row as $key => $value ) {
				check_gl( $lands( $key, true ), $element['id'] . ": la clave galleries.$key no cae en un campo del repetidor." );
			}
			foreach ( array_keys( $row['__dynamic__'] ?? array() ) as $key ) {
				check_gl( $lands( $key, true ), $element['id'] . ": galleries.__dynamic__.$key no cae en un campo del repetidor." );
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	sort( $codes );
	$expected = array( 'css:pro.css:custom_css' );
	check_gl( $expected === $codes, 'Ningún pro.unmapped ni pro.dropped; sólo las aproximaciones declaradas y el CSS: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_gl( 'gallery' === $d['_digitalisimo_pro'] && $gallery === $d['gallery'] && 'All' === $d['show_all_galleries_label'] && 'yes' === $d['lazyload'], 'Una Gallery sin cambios llega con sus imágenes y el texto «All» de PRO.' );
	check_gl( 'New Gallery' === $out['dynamic']['galleries'][0]['gallery_title'] && isset( $out['dynamic']['galleries'][0]['__dynamic__']['multiple_gallery'], $out['dynamic']['__dynamic__']['show_all_galleries_label'] ), 'Etiquetas dinámicas en el widget y en las filas.' );
	check_gl( 'globals/colors?id=accent' === $out['globals']['__globals__']['overlay_background_hover_color'] && 'globals/typography?id=secondary' === $out['globals']['__globals__']['title_typography_typography'], 'Globales del kit en los grupos con el mismo nombre.' );
	check_gl( "selector .digi-gallery__item{border-radius:12px}\nselector .digi-gallery__item:hover .digi-gallery__overlay{opacity:.5}\nselector .digi-gallery__filter.digi-gallery__filter--active{font-weight:700}\n.elementor-widget-digitalisimo-gallery .digi-gallery__image{filter:grayscale(1)}" === $out['css']['custom_css'] && 'mi-galeria' === $out['css']['_css_classes'], 'El CSS personalizado pasa a las clases propias: ' . $out['css']['custom_css'] );
	check_gl( array( 'unit' => 'px', 'size' => 120, 'sizes' => array() ) === $out['justified']['ideal_row_height_mobile'] && 2 === $out['masonry']['columns_tablet'] && '#ffffff' === $out['multiple']['galleries_title_color_hover_pointer_bg'] && 'rgba(0,0,0,.2)' === $out['overlay']['overlay_background_color'] && '#ffffff' === $out['overlay']['overlay_background_hover_color_b'], 'Dispositivos, colores y grupos pasan con su nombre.' );
	foreach ( $out as $id => $settings ) {
		unset( $settings['__dynamic__'], $settings['__globals__'] );
		$html = $render( $settings );
		check_gl( 'dynamic' === $id ? '' === $html : str_contains( $html, 'class="digi-gallery__item' ), "$id: lo convertido se pinta." );
	}
	check_gl( str_contains( $render( $out['multiple'] ), 'data-digi-filter="all" aria-controls="digi-gallery-abc1234" aria-pressed="true">Todo</button>' ) && str_contains( $render( $out['overlay'] ), 'digi-gallery__content--sequenced' ), 'Filtros y superposición convertidos.' );
	check_gl( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	echo 'DIGITALÍSIMO Elements: Galería avanzada (' . count( $w->controls ) . ' controles, ' . count( $w->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B) y su mapa desde la Gallery de PRO (' . count( $cases ) . " casos) validados.\n";
}
