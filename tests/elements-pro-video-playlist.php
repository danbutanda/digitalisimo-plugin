<?php
/**
 * Lista de videos (`digitalisimo-video-playlist`, C2 de docs/PRO_WIDGETS_OBJECTIVE.md, frente C de docs/pro/c2.md), el
 * widget propio que sustituye al Video Playlist de PRO, y su mapa `modules/digitalisimo-convert/maps/video-playlist.php`:
 * - registro y recursos (hoja ≤ 4 KB y script ≤ 6 KB sin jQuery, movimiento reducido, foco visible, sin `--e-global-*`,
 *   `url()`, clases del proveedor ni direcciones de terceros);
 * - un control propio por cada control de PRO (cargando el widget de PRO con los mismos dobles): mismo tipo, valor por
 *   defecto (también por dispositivo), opciones, condiciones, unidades, rangos y declaraciones de sus `selectors`; los
 *   textos por defecto de PRO, en `defaults` del mapa; cobertura del mapa;
 * - marcado: pestañas accesibles (`tablist` vertical, `tab`, `tabpanel`), fachadas sin iframe ni petición al servicio
 *   (YouTube sin cookies y Vimeo sólo en `data-src`), vídeo alojado con `preload="none"`, miniaturas con
 *   `wp_get_attachment_image()` y las de otro dominio sustituidas, imagen superpuesta, secciones, pestañas internas
 *   plegables, iconos por defecto en SVG, escape;
 * - conversión con `Converter::tree()` de ajustes reales de PRO (por defecto, con todo, `__dynamic__`, `__globals__`, CSS
 *   personalizado con clases de PRO): ningún `pro.unmapped`, cada clave cae en un control propio y lo convertido se pinta.
 */
namespace Elementor {
	class Plugin { public static $instance; public $editor; }
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const NUMBER = 'number'; const DIMENSIONS = 'dimensions';
		const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const HIDDEN = 'hidden'; const BUTTON = 'button';
		const WYSIWYG = 'wysiwyg'; const ICONS = 'icons'; const TAB_CONTENT = 'content'; const TAB_STYLE = 'style';
	}
	trait Stub_Controls {
		public $controls = array();
		public $groups   = array();
		public function start_controls_section( $name, $args = array() ) {}
		public function end_controls_section() {}
		public function start_controls_tabs( $name ) {}
		public function start_controls_tab( $name, $args = array() ) {}
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
	}
	class Repeater {
		use Stub_Controls;
		public function get_controls() { return $this->controls; }
	}
	class Widget_Base {
		use Stub_Controls;
		public $settings = array();
		public $id       = 'abc1234';
		public function get_id() { return $this->id; }
		public function get_id_int() { return hexdec( $this->id ); }
		public function get_settings( $key = null ) { $all = $this->get_settings_for_display(); return null === $key ? $all : ( $all[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) {
			$defaults = array();
			foreach ( $this->controls as $name => $control ) {
				if ( array_key_exists( 'default', $control ) ) {
					$defaults[ $name ] = $control['default'];
				}
			}
			$all = $this->settings + $defaults;
			if ( isset( $all['tabs'] ) && is_array( $all['tabs'] ) ) {
				foreach ( $all['tabs'] as $i => $row ) {
					foreach ( $this->controls['tabs']['fields'] as $field => $control ) {
						if ( ! array_key_exists( $field, $row ) ) {
							$row[ $field ] = $control['default'] ?? '';
						}
					}
					$all['tabs'][ $i ] = $row;
				}
			}
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		protected function parse_text_editor( $content ) { return '<!-- editor -->' . $content; }
	}
	class Utils {
		public static function get_placeholder_image_src() { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png'; }
	}
	class Group_Control_Image_Size {
		public static function get_type() { return 'image-size'; }
		public static function get_attachment_image_src( $id, $key, $settings ) { return $id ? 'https://sitio.test/wp-content/uploads/custom-' . $id . '.png' : ''; }
	}
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Icons_Manager {
		public static function try_get_icon_html( $icon, $attrs = array() ) { return '<svg class="icono-propio" data-icono="' . htmlspecialchars( (string) $icon['value'] ) . '" aria-hidden="true"></svg>'; }
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_TEXT = 'globals/colors?id=text'; }
	class Global_Typography { const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace Elementor\Modules\DynamicTags {
	class Module { const URL_CATEGORY = 'url'; const MEDIA_CATEGORY = 'media'; const POST_META_CATEGORY = 'post_meta'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Markdown_Utils {}
}
namespace ElementorPro {
	class Plugin {}
}
namespace Digitalisimo\Elements\Front {
	class Facades { public static function active() { return ! empty( $GLOBALS['vpl_facades'] ); } }
	class Facade_Images {
		public static function video( $service, $id ) { return array( 'kind' => 'video', 'service' => $service, 'id' => $id ); }
		public static function url( array $job ) { return 'https://sitio.test/wp-content/uploads/digitalisimo-elements/facades/' . $job['service'] . '-' . $job['id'] . '.webp'; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['vpl_posts'] = array(
		21 => (object) array( 'ID' => 21, 'post_type' => 'attachment' ),
		22 => (object) array( 'ID' => 22, 'post_type' => 'attachment' ),
	);
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|/)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function _n( $one, $many, $n ) { return 1 === (int) $n ? $one : $many; }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function home_url( $path = '' ) { return 'https://sitio.test/' . ltrim( $path, '/' ); }
	function is_rtl() { return false; }
	function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
		if ( ! isset( $GLOBALS['vpl_posts'][ $id ] ) ) {
			return '';
		}
		$out = '<img width="1200" height="800" src="https://sitio.test/wp-content/uploads/img-' . $id . '-' . $size . '.png"';
		foreach ( $attr as $k => $v ) {
			$out .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		return $out . ' srcset="https://sitio.test/wp-content/uploads/img-' . $id . '-300.png 300w">';
	}
	function check_vpl( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/digitalisimo-content/class-module.php';
	require $root . 'modules/digitalisimo-content/class-cards.php';
	require $root . 'modules/digitalisimo-content/widgets/class-video-playlist.php';
	require $root . 'modules/video-playlist/widgets/video-playlist.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content_Module;
	use Digitalisimo\Elements\Content\Widgets\Video_Playlist;
	use Digitalisimo\Elements\Convert\Converter;

	$controls = static function ( $w ) {
		( new ReflectionMethod( $w, 'register_controls' ) )->invoke( $w );
		return $w;
	};
	$widget = static function ( array $settings = array() ) use ( $controls ) {
		$w           = $controls( new Video_Playlist() );
		$w->settings = $settings;
		return $w;
	};
	$render = static function ( array $settings ) use ( $widget ) {
		$w = $widget( $settings );
		ob_start();
		( new ReflectionMethod( $w, 'render' ) )->invoke( $w );
		return (string) ob_get_clean();
	};
	$count = static function ( $needle, $html ) { return substr_count( $html, $needle ); };

	// 1. Registro y recursos.
	$w = $widget();
	check_vpl( 'digitalisimo-video-playlist' === $w->get_name() && 'Lista de videos' === $w->get_title() && array( 'digitalisimo' ) === $w->get_categories(), 'Nombre, título y categoría propios.' );
	check_vpl( array( 'digitalisimo-video-playlist', 'digitalisimo-video-playlist-inner' ) === $w->get_style_depends() && array( 'digitalisimo-video-playlist' ) === $w->get_script_depends(), 'En el editor (sin instancia), su hoja, la de las pestañas internas y su script, sin dependencias de PRO.' );
	check_vpl( array( 'digitalisimo-video-playlist' ) === Video_Playlist::styles_for( array( 'tabs' => array( array( 'type' => 'youtube' ), array( 'type' => 'section', 'inner_tab_is_content_visible' => 'show' ) ) ) ) && array( 'digitalisimo-video-playlist', 'digitalisimo-video-playlist-inner' ) === Video_Playlist::styles_for( array( 'tabs' => array( array( 'type' => 'hosted', 'inner_tab_is_content_visible' => 'show' ) ) ) ), 'La hoja de las pestañas internas, sólo si algún vídeo las muestra.' );
	check_vpl( 'modules/digitalisimo-content/assets/css/video-playlist-inner.css' === Content_Module::STYLES['digitalisimo-video-playlist-inner'] && array( 'wp-api-fetch' ) === Content_Module::EDITOR_DEPENDS['digitalisimo-video-playlist'], 'El módulo registra la hoja de las pestañas internas y el script del editor depende de wp-api-fetch.' );
	check_vpl( Video_Playlist::class === ltrim( Content_Module::WIDGETS['digitalisimo-video-playlist']['class'], '\\' ) && 'Lista de videos' === Content_Module::TITLES['digitalisimo-video-playlist'], 'El módulo de contenido registra la clase y el título.' );
	$css = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/video-playlist.css' );
	$js  = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/js/video-playlist.js' );
	$in  = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/video-playlist-inner.css' );
	$ed  = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/js/video-playlist-editor.js' );
	check_vpl( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_vpl( strlen( $js ) > 0 && strlen( $js ) <= 8192, 'El script pesa ' . strlen( $js ) . ' B (tope 8 192, excepción declarada en elements-front-budget.php).' );
	check_vpl( strlen( $in ) > 0 && strlen( $in ) <= 4096 && strlen( $ed ) <= 6144 && ! preg_match( '/jQuery|\$\(/', $ed ), 'Hoja de las pestañas internas ≤ 4 KB y script del editor ≤ 6 KB sin jQuery.' );
	check_vpl( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/\.(e-|elementor)/', $css ), 'La hoja no usa el kit, url() ni clases de PRO o del núcleo.' );
	check_vpl( str_contains( $css, '@media (prefers-reduced-motion:reduce)' ) && str_contains( $css, ':focus-visible{outline:2px solid;outline-offset:2px}' ) && str_contains( $css, 'min-height:24px;min-width:24px' ), 'Movimiento reducido, foco visible de 2 px y botón de 24 × 24 px.' );
	foreach ( array( 'height:434px', 'width:33%', '#d5d8dc', '#f1f2f3', 'height:55px', 'height:42px', 'width:75px', 'background:#000', '#3f444b', '#0c0d0e', '#1f2124', 'font-size:100px', 'max-width:767px' ) as $look ) {
		check_vpl( str_contains( $css . $in, $look ), "La hoja reproduce el aspecto de PRO ($look)." );
	}
	// Correcciones de fidelidad (docs/pro/c2.md): el título en un botón con el reinicio de PRO, los degradados de la lista y
	// las pestañas internas apiladas en el móvil.
	check_vpl( str_contains( $css, '.digi-vpl .digi-vpl__title button{background:none;border:none;border-radius:0;padding:0;text-align:start;transition-duration:unset;width:100%}' ) && str_contains( $css, ':where(.digi-vpl :not(.digi-vpl__title)>button){' ), 'El botón del título sólo con el reinicio de PRO (el común no lo toca).' );
	check_vpl( str_contains( $css, '.digi-vpl__shadow{display:none;height:98px;pointer-events:none;position:absolute;width:100%}' ) && str_contains( $css, 'linear-gradient(180deg,transparent,#fff)' ) && str_contains( $css, '.digi-vpl__list--top .digi-vpl__shadow--top,.digi-vpl__list--bottom .digi-vpl__shadow--bottom{display:block}' ), 'Degradados de 98 px de PRO arriba y abajo de la lista.' );
	check_vpl( str_contains( $in, '@media (max-width:767px){.digi-vpl__in:first-child{padding-top:24px}.digi-vpl__in-list{display:none}.digi-vpl__in-panels{border:solid #d5d8dc;border-width:1px 1px 0}' ) && str_contains( $in, 'font-size:16px;font-weight:700;height:38px' ) && str_contains( $in, '.digi-vpl__in-panels>.digi-vpl__in-mob{display:none}' ), 'Pestañas internas en el móvil como PRO: sin la fila, un título por panel y el contenido enmarcado.' );
	foreach ( array( "'digi-vpl__list--bottom'", "c.toggle('digi-vpl__list--top',t>0)", "'&mute=1'", "'&muted=1'", "has('embed')", "has('lazy')", '.digi-vpl__in-mob', 'make(auto,muted)' ) as $code ) {
		check_vpl( str_contains( $js, $code ), "El script lo hace como PRO ($code)." );
	}
	foreach ( array( "'digitalisimoPlaylist:fetchVideoData'", "'/oembed/1.0/proxy?url='", "'document/elements/settings'", "event: 'listening'", 'maxresdefault.jpg' ) as $code ) {
		check_vpl( str_contains( $ed, $code ), "«Obtener datos del vídeo» en el editor ($code)." );
	}
	check_vpl( ! preg_match( '/jQuery|\$\(|elementorModules|youtube\.com\/iframe_api|player\.vimeo\.com\/api|img\.youtube|vimeo\.com\/api|fetch\(/', $js ), 'El script no usa jQuery, los módulos de PRO ni las API o miniaturas de los servicios.' );
	check_vpl( str_contains( $js, "'&autoplay=1'" ) && str_contains( $js, 'replaceChildren(f)' ) && str_contains( $js, "aria-selected" ) && str_contains( $js, "'Home'" ) && str_contains( $js, "'End'" ), 'El iframe lo crea el script (al reproducir o, sin las fachadas, el del primer vídeo al cargar); teclado del patrón de pestañas.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-content/widgets/class-video-playlist.php', 'modules/digitalisimo-content/assets/css/video-playlist.css', 'modules/digitalisimo-content/assets/js/video-playlist.js', 'modules/digitalisimo-convert/maps/video-playlist.php' ) as $file ) {
		check_vpl( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file no nombra lo que la guarda del empaquetado prohíbe." );
	}

	// 2. Un control propio por cada control de PRO, comparando con el widget de PRO cargado con los mismos dobles.
	$map  = require $root . 'modules/digitalisimo-convert/maps/video-playlist.php';
	$pro  = $controls( new \ElementorPro\Modules\VideoPlaylist\Widgets\Video_Playlist() );
	$mine = $w;
	// «Get Video Data» (`vimeo_fetch_data`) es un botón del editor: no guarda valor (el mapa lo declara en `ui`) y se repite
	// con su nombre, su condición y su lugar, con el evento propio.
	$pf   = $pro->controls['tabs']['fields'];
	$mf   = $mine->controls['tabs']['fields'];
	check_vpl( array_keys( $pro->controls ) === array_keys( $mine->controls ), 'Mismos controles y en el mismo orden: ' . implode( ', ', array_diff( array_keys( $pro->controls ), array_keys( $mine->controls ) ) ) . ' / ' . implode( ', ', array_diff( array_keys( $mine->controls ), array_keys( $pro->controls ) ) ) );
	check_vpl( array_keys( $pro->groups ) === array_keys( $mine->groups ), 'Mismos grupos.' );
	check_vpl( array_keys( $pf ) === array_keys( $mf ), 'Mismos campos del repetidor, en el orden de PRO.' );
	// Textos por defecto: los del widget propio en español; los de PRO, en `defaults` del mapa.
	$texts      = array( 'playlist_title', 'inner_tab_title_1', 'inner_tab_title_2', 'inner_tab_label_show_more', 'inner_tab_label_show_less' );
	$row_texts  = array( 'title', 'inner_tab_content_1' );
	$vars       = array( '--playlist-item-icon-size' => '--digi-vpl-icon', '--inner-tabs-border-height' => '--digi-vpl-in-border', '--inner-tabs-border-color' => '--digi-vpl-in-color' );
	// Los degradados de desplazamiento y el título móvil de las pestañas internas también están en el propio.
	$extra_pro  = array();
	$decls      = static function ( array $control ) use ( $vars ) {
		$out = array();
		foreach ( (array) ( $control['selectors'] ?? array() ) as $value ) {
			$out[] = strtr( (string) $value, $vars );
		}
		foreach ( (array) ( $control['fields_options']['text_shadow']['selectors'] ?? array() ) as $value ) {
			$out[] = (string) $value;
		}
		return array_values( array_unique( $out ) );
	};
	$compare = static function ( array $a, array $b, string $name, array $texts ) use ( $decls, $extra_pro ) {
		foreach ( array( 'type', 'options', 'condition', 'conditions', 'size_units', 'range', 'responsive', 'tablet_default', 'mobile_default', 'return_value', 'frontend_available', 'render_type', 'global', 'media_types', 'dynamic', 'separator', 'fa4compatibility', 'skin', 'label_block', 'show_label', 'title_field', 'autocomplete' ) as $key ) {
			$x = $a[ $key ] ?? null;
			$y = $b[ $key ] ?? null;
			if ( 'options' === $key && is_array( $x ) && is_array( $y ) ) {
				$x = array_keys( $x );
				$y = array_keys( $y );
			}
			check_vpl( $x === $y, "$name: «{$key}» distinto de PRO (" . json_encode( $x ) . ' / ' . json_encode( $y ) . ').' );
		}
		if ( ! in_array( $name, $texts, true ) && 'tabs' !== $name ) {
			check_vpl( ( $a['default'] ?? null ) === ( $b['default'] ?? null ), "$name: valor por defecto distinto de PRO (" . json_encode( $a['default'] ?? null ) . ' / ' . json_encode( $b['default'] ?? null ) . ').' );
		}
		check_vpl( isset( $a['prefix_class'] ) === isset( $b['prefix_class'] ) && isset( $a['selectors'] ) === isset( $b['selectors'] ) && isset( $a['selector'] ) === isset( $b['selector'] ), "$name: prefix_class, selectors o selector." );
		$pa = array_values( array_diff( $decls( $a ), $extra_pro ) );
		$pb = $decls( $b );
		check_vpl( ! array_diff( $pa, $pb ) && ! array_diff( $pb, $pa ), "$name: declaraciones de los selectores distintas de PRO (" . implode( ' | ', array_diff( $pa, $pb ) ) . ' / ' . implode( ' | ', array_diff( $pb, $pa ) ) . ').' );
	};
	foreach ( $pro->controls as $name => $control ) {
		$compare( $control, $mine->controls[ $name ], $name, $texts );
	}
	foreach ( $pf as $name => $control ) {
		$compare( $control, $mf[ $name ], 'tabs.' . $name, array( 'tabs.title', 'tabs.inner_tab_content_1' ) );
	}
	foreach ( $pro->groups as $name => $group ) {
		$g = $mine->groups[ $name ];
		check_vpl( $group['type'] === $g['type'] && ( $group['default'] ?? null ) === ( $g['default'] ?? null ) && ( $group['global'] ?? null ) === ( $g['global'] ?? null ) && ( $group['condition'] ?? null ) === ( $g['condition'] ?? null ) && isset( $group['selector'] ) === isset( $g['selector'] ), "Grupo $name distinto de PRO." );
		$compare( $group, $g, 'grupo ' . $name, array() );
	}
	foreach ( $texts as $name ) {
		check_vpl( $map['defaults'][ $name ] === $pro->controls[ $name ]['default'], "$name: el mapa lleva el texto por defecto de PRO." );
	}
	foreach ( $row_texts as $name ) {
		check_vpl( $map['repeaters']['tabs']['defaults'][ $name ] === $pf[ $name ]['default'], "tabs.$name: el mapa lleva el texto por defecto de PRO." );
	}
	check_vpl( 3 === count( $pro->controls['tabs']['default'] ) && $map['repeaters']['tabs']['default_rows'] === $pro->controls['tabs']['default'] && 3 === count( $mine->controls['tabs']['default'] ), 'Las tres filas por defecto de PRO, en el mapa.' );
	foreach ( array( 'type', 'section_html_tag', 'video_html_tag', 'thumbnail', 'inner_tab_is_content_visible' ) as $name ) {
		check_vpl( $map['repeaters']['tabs']['defaults'][ $name ] === $pf[ $name ]['default'], "tabs.$name: valor por defecto de PRO en el mapa." );
	}
	// Cobertura del mapa: cada control de PRO declarado; `keep` sólo nombra controles propios; `ui`, lo que no guarda valor.
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), $map['ui'], array_keys( $map['repeaters'] ), $map['repeaters']['tabs']['keep'] );
	foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ), array_keys( $pf ) ) as $name ) {
		check_vpl( in_array( $name, $declared, true ), "$name: el mapa no lo declara." );
	}
	foreach ( $map['keep'] as $name ) {
		check_vpl( isset( $mine->controls[ $name ] ), "keep $name: no es un control de la Lista de videos." );
	}
	foreach ( $map['repeaters']['tabs']['keep'] as $name ) {
		check_vpl( isset( $mf[ $name ] ), "tabs.$name: no es un campo propio." );
	}
	foreach ( $map['ui'] as $name ) {
		$type = $pro->controls[ $name ]['type'] ?? ( $pro->controls['tabs']['fields'][ $name ]['type'] ?? '' );
		check_vpl( in_array( $type, array( 'heading', 'button' ), true ), "$name: no guarda valor ($type)." );
	}
	check_vpl( 'button' === $mf['vimeo_fetch_data']['type'] && 'digitalisimoPlaylist:fetchVideoData' === $mf['vimeo_fetch_data']['event'] && array( 'type' => array( 'youtube', 'vimeo' ) ) === $mf['vimeo_fetch_data']['condition'] && 'Obtener datos del vídeo' === $mf['vimeo_fetch_data']['text'], '«Obtener datos del vídeo», como «Get Video Data» de PRO, con su evento propio.' );
	check_vpl( 'digitalisimo-video-playlist' === $map['target'] && 'ready' === $map['status'] && 'hide' === $map['panel'], 'Destino, estado listo (A/B en verde) y panel del mapa.' );
	$markup = (string) file_get_contents( $root . 'modules/digitalisimo-content/widgets/class-video-playlist.php' );
	foreach ( $map['classes'] as $from => $to ) {
		$class = substr( $to, 1 );
		check_vpl( str_contains( $markup . $css . $js, $class ) || 'elementor-widget-digitalisimo-video-playlist' === $class, "Clase de destino $to inexistente." );
	}

	// 3. Marcado por defecto: tres vídeos de muestra sin dirección.
	$html = $render( array() );
	check_vpl( 1 === $count( 'role="region" aria-labelledby="digi-vpl-abc1234-name"', $html ) && str_contains( $html, '<h2 class="digi-vpl__name" id="digi-vpl-abc1234-name">Lista de videos</h2>' ), 'Región con el nombre de la lista.' );
	check_vpl( 1 === $count( 'role="tablist" aria-orientation="vertical" aria-labelledby="digi-vpl-abc1234-name"', $html ) && 3 === $count( 'role="tab"', $html ) && 3 === $count( 'role="tabpanel"', $html ), 'Lista de pestañas vertical: tres pestañas y tres paneles.' );
	check_vpl( 1 === $count( 'aria-selected="true"', $html ) && 2 === $count( 'aria-selected="false"', $html ) && 1 === $count( 'tabindex="-1" data-tab="2"', $html ) && str_contains( $html, 'aria-selected="true" aria-controls="digi-vpl-abc1234-panel-1" tabindex="0" data-tab="1"' ), 'La primera pestaña, elegida y en el orden del tabulador; las demás, con tabindex -1.' );
	check_vpl( 2 === $count( '" hidden>', $html ) && str_contains( $html, 'id="digi-vpl-abc1234-panel-1" aria-labelledby="digi-vpl-abc1234-tab-1"' ), 'Sólo el primer panel visible, rotulado por su pestaña.' );
	check_vpl( str_contains( $html, '<span class="digi-vpl__count">3 videos</span>' ) && str_contains( $html, 'aria-expanded="true" aria-controls="digi-vpl-abc1234-list"' ), 'Número de vídeos y botón que pliega la lista.' );
	check_vpl( 3 === $count( '<h4 class="digi-vpl__title"><button type="button" tabindex="-1">Video de muestra</button></h4>', $html ) && 3 === $count( 'class="digi-vpl__img" src="https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png" alt="" loading="lazy" decoding="async"', $html ), 'Títulos y miniaturas de muestra (decorativas: el título ya nombra la pestaña).' );
	check_vpl( 6 === $count( 'viewBox="0 0 512 512"', $html ) && ! str_contains( $html, 'icono-propio' ), 'Iconos de reproducir y de visto de PRO, en SVG en línea.' );
	check_vpl( ! preg_match( '/<iframe|youtube\.|vimeo\.|data-src|digi-vpl__play"/', $html ), 'Sin dirección no hay reproductor ni botón de reproducir.' );
	check_vpl( ! str_contains( $html, 'data-next' ) && ! str_contains( $html, 'data-watched' ) && ! str_contains( $html, 'data-onload' ) && ! str_contains( $html, 'data-lazy' ) && str_contains( $html, 'data-digi-vpl="abc1234" data-embed>' ), 'Opciones del script en atributos de datos; sin las fachadas, el reproductor del primer vídeo se crea al cargar (data-embed).' );
	check_vpl( str_contains( $html, '</div><div class="digi-vpl__shadow digi-vpl__shadow--top" aria-hidden="true"></div><div class="digi-vpl__shadow digi-vpl__shadow--bottom" aria-hidden="true"></div></div>' ), 'Degradados de la lista, decorativos.' );

	// 4. Servicios con fachada, vídeo alojado, secciones, miniaturas e imagen superpuesta.
	$items = array(
		array( '_id' => 'sec1', 'type' => 'section', 'title' => 'Primera parte', 'section_html_tag' => 'h5' ),
		array( '_id' => 'yt1', 'type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Vídeo <script>alert(1)</script>uno', 'duration' => '3:32', 'thumbnail' => array( 'url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg' ), 'video_html_tag' => 'marquee' ),
		array( '_id' => 'vm1', 'type' => 'vimeo', 'vimeo_url' => 'https://vimeo.com/76979871', 'title' => 'Vimeo', 'duration' => '', 'thumbnail' => array( 'id' => 21, 'url' => 'https://sitio.test/wp-content/uploads/img-21.png' ) ),
		array( '_id' => 'ho1', 'type' => 'hosted', 'hosted_url' => array( 'url' => 'https://sitio.test/wp-content/uploads/video.mp4' ), 'title' => 'Alojado', 'duration' => '0:02' ),
		array( '_id' => 'ex1', 'type' => 'hosted', 'is_external_url' => 'yes', 'external_url' => array( 'url' => 'https://cdn.ejemplo.test/v.webm' ), 'title' => 'Externo', 'inner_tab_is_content_visible' => 'show', 'inner_tab_content_1' => '<p>Uno</p>', 'inner_tab_content_2' => '<p>Dos</p>' ),
	);
	$all  = array( 'tabs' => $items, 'show_image_overlay' => 'yes', 'image_overlay' => array( 'id' => 22, 'url' => 'https://sitio.test/wp-content/uploads/img-22.png' ), 'image_overlay_size' => 'large', 'autoplay_next' => 'yes', 'show_watched_indication' => 'yes', 'inner_tab_is_content_collapsible' => 'collapsible' );
	$html = $render( $all );
	check_vpl( str_contains( $html, '<h5 class="digi-vpl__sec" role="presentation">Primera parte</h5>' ) && 4 === $count( 'role="tab"', $html ) - $count( 'class="digi-vpl__in-tab', $html ) && str_contains( $html, '<span class="digi-vpl__count">4 videos</span>' ), 'La sección es un separador de la lista y no cuenta como vídeo.' );
	check_vpl( str_contains( $html, 'id="digi-vpl-abc1234-tab-2" aria-selected="true"' ) && str_contains( $html, 'data-item="yt1"' ), 'Con una sección delante, la primera pestaña elegida es el primer vídeo.' );
	check_vpl( str_contains( $html, 'data-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&amp;enablejsapi=1"' ) && str_contains( $html, 'data-src="https://player.vimeo.com/video/76979871?dnt=1"' ), 'YouTube sin cookies y Vimeo sin seguimiento, sólo en data-src.' );
	check_vpl( ! preg_match( '/<iframe|href="https?:\/\/(?!sitio\.test)/', $html ) && 1 === preg_match_all( '/(?<![\w-])src="https?:\/\/(?!sitio\.test|cdn\.ejemplo\.test)/', $html ) && str_contains( $html, '<img class="digi-vpl__img" src="https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg" alt="" loading="lazy" decoding="async">' ), 'Sin las fachadas, la miniatura guardada del servicio se pinta como en PRO; ningún iframe en el marcado.' );
	check_vpl( str_contains( $html, '<button type="button" class="digi-vpl__in-mob" aria-expanded="true" aria-controls="digi-vpl-abc1234-inner-5-1">Pestaña 1</button><div class="digi-vpl__in-panel digi-vpl__in-panel--fold" role="tabpanel" id="digi-vpl-abc1234-inner-5-1"' ) && str_contains( $html, '<button type="button" class="digi-vpl__in-mob" aria-expanded="false" aria-controls="digi-vpl-abc1234-inner-5-2">Pestaña 2</button>' ), 'Título móvil de cada panel interno, con su estado.' );
	check_vpl( 2 === $count( 'aria-label="Reproducir el vídeo: Vídeo uno"', $html ) && 1 === $count( 'aria-label="Reproducir el vídeo: Vimeo"', $html ), 'Botón de la fachada y de la imagen superpuesta con nombre accesible.' );
	check_vpl( str_contains( $html, '<video class="digi-vpl__video" src="https://sitio.test/wp-content/uploads/video.mp4" controls preload="none" playsinline' ) && str_contains( $html, 'src="https://cdn.ejemplo.test/v.webm" controls preload="none"' ) && ! str_contains( $html, 'autoplay' ), 'Vídeos alojados con preload="none" y sin reproducción automática.' );
	check_vpl( str_contains( $html, '<img width="1200" height="800" src="https://sitio.test/wp-content/uploads/img-21-full.png" class="digi-vpl__img" alt="" loading="lazy" decoding="async" sizes="75px" srcset=' ), 'Miniatura del adjunto con wp_get_attachment_image() y srcset.' );
	check_vpl( str_contains( $html, '<div class="digi-vpl__overlay"><img width="1200" height="800" src="https://sitio.test/wp-content/uploads/img-22-large.png" class="digi-vpl__cover" alt=""' ) && 1 === $count( 'digi-vpl__overlay', $html ), 'Imagen superpuesta sólo en el primer vídeo, con el tamaño elegido.' );
	check_vpl( ! str_contains( $html, '<script' ) && str_contains( $html, '<h4 class="digi-vpl__title"><button type="button" tabindex="-1">Vídeo uno</button></h4>' ), 'Título sin scripts, en un botón fuera del orden del teclado como en PRO, y etiqueta no permitida → la de PRO.' );
	check_vpl( str_contains( $html, ' data-next data-watched' ), 'Siguiente vídeo y vistos.' );
	check_vpl( 1 === $count( 'class="digi-vpl__in" data-tab="5" hidden', $html ) && 1 === $count( 'role="tablist" aria-label="Contenido: Externo"', $html ) && 2 === $count( 'class="digi-vpl__in-tab', $html ), 'Pestañas internas del vídeo que las muestra, ocultas hasta elegirlo.' );
	check_vpl( str_contains( $html, 'aria-selected="false" aria-controls="digi-vpl-abc1234-inner-5-2" tabindex="-1">Pestaña 2</button>' ) && 1 === $count( 'class="digi-vpl__in-panel digi-vpl__in-panel--fold" role="tabpanel" id="digi-vpl-abc1234-inner-5-2" aria-labelledby="digi-vpl-abc1234-inner-5-2-tab" hidden', $html ), 'Segunda pestaña interna oculta y plegable.' );
	check_vpl( 2 === $count( 'class="digi-vpl__more" aria-expanded="false" data-more="Mostrar más" data-less="Mostrar menos" hidden>Mostrar más</button>', $html ) && str_contains( $html, '<!-- editor --><p>Uno</p>' ), '«Mostrar más» con aria-expanded; contenido como el editor de texto.' );
	// Edición en línea del lienzo, como PRO (`add_inline_editing_attributes( 'tabs.<índice>.inner_tab_content_<n>',
	// 'advanced' )` sobre el texto de cada pestaña interna): sólo en el editor; el front no cambia.
	$pro_vpl = (string) file_get_contents( $root . 'modules/video-playlist/widgets/video-playlist.php' );
	check_vpl( str_contains( $pro_vpl, "\$this->add_inline_editing_attributes( \$tab->tab_content_setting_key, 'advanced' );" ) && str_contains( $pro_vpl, "get_repeater_setting_key( 'inner_tab_content_' . \$tab_index, 'tabs', \$playlist_item_index )" ), 'Premisa: PRO edita el contenido de las pestañas internas en el lienzo con la barra avanzada.' );
	\Elementor\Plugin::$instance         = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return ! empty( $GLOBALS['vpl_edit'] ); } };
	$GLOBALS['vpl_edit'] = true;
	$edit                = $render( $all );
	$GLOBALS['vpl_edit'] = false;
	preg_match_all( '#<div class="digi-vpl__in-text"><div class="elementor-inline-editing" data-elementor-setting-key="([^"]+)" data-elementor-inline-editing-toolbar="advanced"><!-- editor -->(.*?)</div></div>#', $edit, $m );
	check_vpl( array( 'tabs.4.inner_tab_content_1', 'tabs.4.inner_tab_content_2' ) === $m[1] && array( '<p>Uno</p>', '<p>Dos</p>' ) === $m[2], 'En el editor, el contenido de cada pestaña interna se edita en el lienzo con la barra avanzada, con la clave de PRO: ' . implode( ', ', $m[1] ) );
	check_vpl( $html === $render( $all ) && ! str_contains( $html, 'elementor-inline-editing' ) && $html === str_replace( array( ' class="elementor-inline-editing" data-elementor-setting-key="tabs.4.inner_tab_content_1" data-elementor-inline-editing-toolbar="advanced"', ' class="elementor-inline-editing" data-elementor-setting-key="tabs.4.inner_tab_content_2" data-elementor-inline-editing-toolbar="advanced"' ), '', $edit ), 'Fuera del editor, el mismo marcado sin la edición en línea.' );
	$GLOBALS['vpl_facades'] = true;
	$html                   = $render( $all );
	check_vpl( str_contains( $html, '<img class="digi-vpl__img" src="https://sitio.test/wp-content/uploads/digitalisimo-elements/facades/youtube-dQw4w9WgXcQ.webp"' ) && str_contains( $html, '<img class="digi-vpl__cover" src="https://sitio.test/wp-content/uploads/digitalisimo-elements/facades/vimeo-76979871.webp"' ), 'Con las fachadas, la portada del servicio la sirve el sitio (miniatura y panel).' );
	check_vpl( ! preg_match( '/<iframe|(?<![\w-])src="https?:\/\/(?!sitio\.test|cdn\.ejemplo\.test)|img\.youtube/', $html ) && ! str_contains( $html, 'data-embed' ), 'Con las fachadas, ningún recurso de terceros (la miniatura del servicio se sustituye) y el reproductor espera al clic.' );
	check_vpl( array( array( 'kind' => 'video', 'service' => 'youtube', 'id' => 'dQw4w9WgXcQ' ), array( 'kind' => 'video', 'service' => 'vimeo', 'id' => '76979871' ) ) === Video_Playlist::facade_jobs( $all ), 'facade_jobs(): las portadas que pinta la lista.' );
	$GLOBALS['vpl_facades'] = false;
	$html = $render( array( 'tabs' => array( $items[3] ), 'autoplay_on_load' => 'yes', 'show_image_overlay' => 'yes', 'show_thumbnail' => '', 'show_duration' => '', 'show_video_count' => '', 'playlist_title' => '', 'play_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) ) );
	check_vpl( str_contains( $html, ' data-onload' ) && str_contains( $render( array( 'lazy_load' => 'yes' ) ), ' data-embed data-lazy>' ) && ! str_contains( $html, 'digi-vpl__overlay' ) && ! str_contains( $html, 'digi-vpl__thumb' ) && ! str_contains( $html, 'digi-vpl__time' ) && ! str_contains( $html, 'digi-vpl__count' ), 'Al cargar sin imagen superpuesta (como PRO); sin miniaturas, duración ni número.' );
	check_vpl( str_contains( $html, '<span class="digi-vpl__icon digi-vpl__icon--play"><svg class="icono-propio" data-icono="fas fa-star"' ), 'Un icono elegido por el usuario, con Icons_Manager.' );
	check_vpl( ! str_contains( $render( array( 'tabs' => array( array( 'type' => 'youtube', 'youtube_url' => 'https://vimeo.com/1', 'title' => 'x' ) ) ) ), 'data-src' ), 'Una dirección de otro servicio no crea reproductor.' );

	// 5. Conversión de ajustes reales de PRO.
	$cases = array(
		'por defecto' => array(),
		'con todo'    => array(
			'playlist_title' => 'Curso', 'playlist_title_tag' => 'h3', 'tabs' => $items, 'show_image_overlay' => 'yes', 'image_overlay' => array( 'id' => 22, 'url' => 'https://sitio.test/x.png' ), 'image_overlay_size' => 'medium',
			'show_play_icon' => array( 'value' => 'fas fa-play', 'library' => 'fa-solid' ), 'autoplay_on_load' => 'yes', 'autoplay_next' => 'yes', 'show_watched_indication' => 'yes', 'lazy_load' => 'yes', 'tabs_alignment' => 'start',
			'layout_height' => array( 'unit' => 'px', 'size' => 500 ), 'layout_height_mobile' => array( 'unit' => 'px', 'size' => 300 ), 'normal_background' => '#eeeeee', 'active_color' => '#ff0000', 'normal_typography_typography' => 'custom', 'normal_typography_font_size' => array( 'unit' => 'px', 'size' => 15 ),
			'normal_icon_top_text_shadow_text_shadow_type' => 'yes', 'normal_icon_top_text_shadow_text_shadow' => array( 'horizontal' => 1, 'vertical' => 1, 'blur' => 2, 'color' => '#000' ), 'normal_separator_style' => 'solid', 'normal_separator_weight' => array( 'unit' => 'px', 'size' => 2 ),
			'section_border_width' => array( 'unit' => 'px', 'top' => '1', 'right' => '0', 'bottom' => '1', 'left' => '0', 'isLinked' => false ), 'inner_tab_is_content_collapsible' => 'collapsible', 'inner_tab_collapsible_height' => array( 'unit' => 'px', 'size' => 80 ),
			'inner_tab_hover_show_more_color_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ), 'inner_tab_show_more_typography_typography' => 'custom', 'image_overlay_custom_dimension' => array( 'width' => 300, 'height' => 200 ),
		),
		'dinámicos'   => array(
			'tabs'        => array( array( '_id' => 'd1', 'type' => 'youtube', 'title' => 'x', '__dynamic__' => array( 'youtube_url' => '[elementor-tag id="1" name="post-custom-field" settings="%7B%7D"]' ) ) ),
			'__dynamic__' => array( 'playlist_title' => '[elementor-tag id="2" name="post-title" settings="%7B%7D"]' ),
			'__globals__' => array( 'active_color' => 'globals/colors?id=primary', 'normal_typography_typography' => 'globals/typography?id=secondary', 'inner_tab_normal_show_more_color' => '' ),
		),
		'css'         => array( 'custom_css' => 'selector .e-tabs-items-wrapper .e-tab-title.watched-video .icon-watched{color:red} selector .e-tabs-main-area{height:300px} selector .e-inner-tab-title{x:y}' ),
	);
	$own_keys = array_merge( array_keys( $mine->controls ), array( 'custom_css', '_digitalisimo_pro', '__dynamic__', '__globals__' ) );
	foreach ( $cases as $label => $settings ) {
		$log  = array();
		$tree = Converter::tree( array( array( 'id' => 'e1', 'elType' => 'widget', 'widgetType' => 'video-playlist', 'settings' => $settings, 'elements' => array() ) ), $log, true );
		$out  = $tree[0];
		check_vpl( 'digitalisimo-video-playlist' === $out['widgetType'] && 'e1' === $out['id'], "$label: widget propio con el mismo id." );
		$codes = array_column( array_merge( ...array_column( $log, 'codes' ) ?: array( array() ) ), 'code' );
		check_vpl( ! in_array( 'pro.unmapped', $codes, true ) && ! in_array( 'pro.dropped', $codes, true ), "$label: ninguna clave sin declarar ni descartada (" . implode( ',', $codes ) . ').' );
		foreach ( $out['settings'] as $key => $value ) {
			$ok = in_array( $key, $own_keys, true ) || preg_match( '/^(' . implode( '|', array_map( 'preg_quote', array_keys( $mine->groups ) ) ) . ')_/', $key ) || preg_match( '/_(tablet|mobile)$/', $key ) && isset( $mine->controls[ preg_replace( '/_(tablet|mobile)$/', '', $key ) ] );
			check_vpl( (bool) $ok, "$label: «{$key}» no cae en un control propio." );
		}
		foreach ( (array) ( $out['settings']['tabs'] ?? array() ) as $row ) {
			foreach ( array_keys( $row ) as $key ) {
				check_vpl( '_id' === $key || '__dynamic__' === $key || isset( $mf[ $key ] ), "$label: tabs.$key no es un campo propio." );
			}
		}
		$html = $render( $out['settings'] );
		check_vpl( str_contains( $html, 'class="digi-vpl"' ) && str_contains( $html, 'role="tablist"' ), "$label: lo convertido se pinta." );
		if ( 'por defecto' === $label ) {
			check_vpl( 'Playlist' === $out['settings']['playlist_title'] && 3 === count( $out['settings']['tabs'] ) && 'Sample Video' === $out['settings']['tabs'][0]['title'] && 'Tab #1' === $out['settings']['inner_tab_title_1'] && 'Show More' === $out['settings']['inner_tab_label_show_more'], 'Por defecto: los textos de PRO pasan al widget propio.' );
			check_vpl( 3 === $count( '<h4 class="digi-vpl__title"><button type="button" tabindex="-1">Sample Video</button></h4>', $html ) && str_contains( $html, '>Playlist</h2>' ), 'Por defecto: se pintan con los textos de PRO.' );
		}
		if ( 'con todo' === $label ) {
			check_vpl( ! in_array( 'pro.approx', $codes, true ) && 'yes' === $out['settings']['lazy_load'] && 'yes' === $out['settings']['autoplay_on_load'] && 'start' === $out['settings']['tabs_alignment'] && 300 === $out['settings']['layout_height_mobile']['size'], 'Con todo: sin aproximaciones (carga diferida y «Al cargar» como PRO) y ajustes por dispositivo.' );
		}
		if ( 'dinámicos' === $label ) {
			check_vpl( isset( $out['settings']['__dynamic__']['playlist_title'], $out['settings']['tabs'][0]['__dynamic__']['youtube_url'], $out['settings']['__globals__']['active_color'], $out['settings']['__globals__']['normal_typography_typography'] ), 'Dinámicos y globales, también en las filas.' );
		}
		if ( 'css' === $label ) {
			check_vpl( 'selector .digi-vpl__list .digi-vpl__tab.digi-vpl__tab--seen .digi-vpl__icon--seen{color:red} selector .digi-vpl__main{height:300px} selector .digi-vpl__in-tab{x:y}' === $out['settings']['custom_css'] && in_array( 'pro.css', $codes, true ), 'CSS personalizado con las clases propias: ' . $out['settings']['custom_css'] );
		}
	}

	// 6. Páginas de prueba del navegador (tests/elements-pro-video-playlist-chrome.mjs): el marcado de hoy. La primera, con
	// las fachadas de vídeo del sitio aplicadas (nada de terceros antes del clic; sus portadas, servidas por el sitio, pasan
	// a rutas locales); la segunda, sin ellas, como PRO (el reproductor del primer vídeo al cargar o al acercarse).
	$GLOBALS['vpl_facades'] = true;
	$mp4    = '/tests/playground/front-budget/digi-medicion.mp4';
	$widget_html = static function ( string $id, array $settings ) use ( $widget ): string {
		$w     = $widget( $settings );
		$w->id = $id;
		ob_start();
		( new ReflectionMethod( $w, 'render' ) )->invoke( $w );
		return '<div class="elementor-element elementor-widget-digitalisimo-video-playlist digi-vpl-layout-end" data-id="' . $id . '">' . ob_get_clean() . "</div>\n";
	};
	$long    = '<p>' . str_repeat( 'Texto largo de la transcripción. ', 30 ) . '</p>';
	$fixture  = "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width\"><title>Lista de videos</title>\n";
	$fixture .= "<!-- Generada por tests/elements-pro-video-playlist.php (--write); la usa tests/elements-pro-video-playlist-chrome.mjs. -->\n";
	$fixture .= "<style>body{margin:0;font:16px/1.5 sans-serif}.elementor-element{margin:0 0 40px}.spacer{height:1800px}button{border:1px solid #c36;padding:.5rem 1rem;color:#c36}[type=button]:hover{background-color:#c36;color:#fff}.elementor-element .digi-vpl__in-panel--fold:not(.digi-vpl__in-panel--open) .digi-vpl__in-text{height:54px}</style>\n";
	$fixture .= "<link rel=\"stylesheet\" href=\"/digitalisimo-elements/modules/digitalisimo-content/assets/css/video-playlist.css\">\n<link rel=\"stylesheet\" href=\"/digitalisimo-elements/modules/digitalisimo-content/assets/css/video-playlist-inner.css\">\n</head><body>\n";
	$fixture .= $widget_html(
		'w1',
		array(
			'playlist_title'                   => 'Curso',
			'show_watched_indication'          => 'yes',
			'autoplay_next'                    => 'yes',
			'inner_tab_is_content_collapsible' => 'collapsible',
			'show_image_overlay'               => 'yes',
			'image_overlay'                    => array( 'url' => '' ),
			'tabs'                             => array(
				array( '_id' => 'a', 'type' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Uno', 'duration' => '3:32', 'thumbnail' => array( 'url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg' ) ),
				array( '_id' => 's', 'type' => 'section', 'title' => 'Segunda parte' ),
				array( '_id' => 'b', 'type' => 'vimeo', 'vimeo_url' => 'https://vimeo.com/76979871', 'title' => 'Dos', 'thumbnail' => array( 'url' => '' ) ),
				array( '_id' => 'c', 'type' => 'hosted', 'hosted_url' => array( 'url' => $mp4 ), 'title' => 'Tres', 'thumbnail' => array( 'url' => '' ) ),
				array( '_id' => 'd', 'type' => 'hosted', 'is_external_url' => 'yes', 'external_url' => array( 'url' => $mp4 ), 'title' => 'Cuatro', 'thumbnail' => array( 'url' => '' ), 'inner_tab_is_content_visible' => 'show', 'inner_tab_content_1' => $long, 'inner_tab_content_2' => 'Enlaces' ),
			),
		)
	);
	$fixture .= $widget_html(
		'w2',
		array(
			'tabs' => array(
				array( '_id' => 'x', 'type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa', 'title' => 'Equis', 'thumbnail' => array( 'url' => '' ) ),
				array( '_id' => 'y', 'type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=bbbbbbbbbbb', 'title' => 'Ye', 'thumbnail' => array( 'url' => '' ) ),
			),
		)
	);
	$fixture .= "<div class=\"spacer\"></div>\n";
	$fixture .= $widget_html( 'w3', array( 'autoplay_on_load' => 'yes', 'tabs' => array( array( '_id' => 'z', 'type' => 'hosted', 'hosted_url' => array( 'url' => $mp4 ), 'title' => 'Al cargar', 'thumbnail' => array( 'url' => '' ) ) ) ) );
	$fixture .= "<script src=\"/digitalisimo-elements/modules/digitalisimo-content/assets/js/video-playlist.js\" defer></script>\n</body></html>\n";
	$fixture  = str_replace( 'https://sitio.test/', '/', $fixture );
	$GLOBALS['vpl_facades'] = false;
	list( $head ) = explode( '<div class="elementor-element', $fixture, 2 );
	$rows = array( array( '_id' => 'p1', 'type' => 'youtube', 'youtube_url' => 'https://youtu.be/aaaaaaaaaaa', 'title' => 'Primero', 'thumbnail' => array( 'url' => 'https://img.youtube.com/vi/aaaaaaaaaaa/maxresdefault.jpg' ) ), array( '_id' => 'p2', 'type' => 'vimeo', 'vimeo_url' => 'https://vimeo.com/76979871', 'title' => 'Segundo', 'thumbnail' => array( 'url' => '' ) ) );
	for ( $n = 3; $n <= 10; $n++ ) {
		$rows[] = array( '_id' => 'p' . $n, 'type' => 'hosted', 'hosted_url' => array( 'url' => $mp4 ), 'title' => 'Vídeo ' . $n, 'thumbnail' => array( 'url' => '' ) );
	}
	$pro  = str_replace( 'deo-playlist-chrome.mjs. -->', 'deo-playlist-chrome.mjs; sin las fachadas del sitio. -->', $head );
	$pro .= $widget_html( 'p1', array( 'tabs' => $rows ) );
	$pro .= $widget_html( 'p3', array( 'autoplay_on_load' => 'yes', 'tabs' => array( $rows[1], $rows[0] ) ) );
	$pro .= "<div class=\"spacer\"></div>\n";
	$pro .= $widget_html( 'p2', array( 'lazy_load' => 'yes', 'tabs' => array( $rows[0] ) ) );
	$pro .= "<script src=\"/digitalisimo-elements/modules/digitalisimo-content/assets/js/video-playlist.js\" defer></script>\n</body></html>\n";
	foreach ( array( 'video-playlist' => $fixture, 'video-playlist-pro' => $pro ) as $file => $page ) {
		$path = __DIR__ . '/fixtures/' . $file . '.html';
		if ( in_array( '--write', $argv ?? array(), true ) ) {
			file_put_contents( $path, $page );
		}
		check_vpl( is_file( $path ) && file_get_contents( $path ) === $page, "tests/fixtures/$file.html es el marcado de hoy (regenerar con --write)." );
	}

	echo 'DIGITALÍSIMO Elements: Lista de videos (' . count( $mine->controls ) . ' controles, ' . count( $mf ) . ' campos del repetidor, ' . count( $mine->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B) y su mapa desde el Video Playlist de PRO (' . count( $cases ) . " casos) validados.\n";
}
