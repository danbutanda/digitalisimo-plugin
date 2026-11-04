<?php
/**
 * Animación Lottie (`digitalisimo-lottie`, C2 de docs/PRO_WIDGETS_OBJECTIVE.md, frente C de docs/pro/c2.md), el widget
 * propio que sustituye al Lottie de PRO, y su mapa `modules/digitalisimo-convert/maps/lottie.php`:
 * - registro y recursos (hoja ≤ 4 KB y script ≤ 6 KB sin jQuery ni el gestor de handlers del núcleo; la librería local
 *   sólo la inserta el script, al acercarse a la pantalla; movimiento reducido, foco visible, sin `--e-global-*`,
 *   `url()` ni clases del proveedor; animación de muestra propia sin logotipos);
 * - los controles de PRO, cargando su clase real con su base simulada: el mismo nombre, tipo, valor por defecto (también
 *   por dispositivo), opciones, condiciones, globales y las mismas declaraciones CSS en `selectors` y `selector`, más
 *   los tres de descripción y botón de DIGITALÍSIMO (`Media_Text_Extras`); cobertura del mapa;
 * - marcado del servidor: archivo (adjunto, URL externa, muestra), leyenda con las reglas de PRO (permisos del adjunto),
 *   enlace con nombre accesible, botón de teclado con «Al pulsar», configuración del script y escape;
 * - conversión con `Converter::tree()` de ajustes reales de PRO: ningún `pro.unmapped`, cada clave cae en un control
 *   propio y lo convertido se pinta.
 */
namespace Elementor {
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const NUMBER = 'number'; const DIMENSIONS = 'dimensions';
		const MEDIA = 'media'; const URL = 'url'; const HIDDEN = 'hidden'; const TAB_CONTENT = 'content'; const TAB_STYLE = 'style';
	}
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $id       = 'abc1234';
		private $attrs   = array();
		private $section = '';
		public function get_id() { return $this->id; }
		public function get_settings_for_display() {
			$defaults = array();
			foreach ( $this->controls as $name => $control ) {
				if ( array_key_exists( 'default', $control ) ) {
					$defaults[ $name ] = $control['default'];
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
	}
	class Group_Control_Css_Filter { public static function get_type() { return 'css-filter'; } }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Experiments_Stub { public function is_feature_active( $f ) { return 'e_optimized_markup' === $f && ! empty( $GLOBALS['lt_optimized'] ); } }
	class Plugin {
		public static $instance;
		public $experiments;
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
}
namespace ElementorPro\Core\Isolation {
	class Wordpress_Adapter {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	// Adjuntos de prueba: 21 visible para todos, 22 privado (sólo quien puede editarlo).
	$GLOBALS['lt_posts'] = array(
		21 => array( 'title' => 'Cohete <b>verde</b>', 'caption' => 'Leyenda del cohete', 'private' => false ),
		22 => array( 'title' => 'Borrador privado', 'caption' => 'secreto', 'private' => true ),
	);
	$GLOBALS['lt_caps'] = array( 'publish_pages' => true );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|#)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function esc_url_raw( $v ) { return preg_match( '~^(https?://|/|#)~', (string) $v ) ? (string) $v : ''; }
	function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function wpautop( $v ) { return '<p>' . $v . '</p>'; }
	function wp_kses_post( $v ) { return preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ); }
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function current_user_can( $cap, $id = null ) {
		if ( null !== $id ) {
			return in_array( $cap, array( 'read_post', 'edit_post' ), true ) && isset( $GLOBALS['lt_posts'][ $id ] ) && ( ! $GLOBALS['lt_posts'][ $id ]['private'] || ! empty( $GLOBALS['lt_caps']['edit_post'] ) );
		}
		return ! empty( $GLOBALS['lt_caps'][ $cap ] );
	}
	function get_the_title( $id ) { return $GLOBALS['lt_posts'][ $id ]['title'] ?? ''; }
	function wp_get_attachment_caption( $id ) { return $GLOBALS['lt_posts'][ $id ]['caption'] ?? ''; }
	function check_lt( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new \Elementor\Experiments_Stub();
	$root = __DIR__ . '/../digitalisimo-elements/';
	define( 'DIGITALISIMO_ELEMENTS_FILE', $root . 'pro-elements.php' );
	require $root . 'modules/digitalisimo-content/class-module.php';
	require $root . 'modules/digitalisimo-content/widgets/class-lottie.php';
	require $root . 'modules/digitalisimo-widgets/class-media-text-extras.php';
	require $root . 'modules/lottie/classes/caption-helper.php';
	require $root . 'modules/lottie/widgets/lottie.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content_Module;
	use Digitalisimo\Elements\Content\Widgets\Lottie;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;
	use Digitalisimo\Elements\Media_Text_Extras;

	$widget = static function ( array $settings = array() ) {
		$w = new Lottie();
		( new ReflectionMethod( $w, 'register_controls' ) )->invoke( $w );
		$w->settings = $settings;
		return $w;
	};
	$render = static function ( array $settings ) use ( $widget ) {
		$w = $widget( $settings );
		ob_start();
		( new ReflectionMethod( $w, 'render' ) )->invoke( $w );
		return (string) ob_get_clean();
	};
	$config = static function ( string $html ) {
		check_lt( (bool) preg_match( '/data-digi-lottie="([^"]*)"/', $html, $m ), 'El contenedor lleva su configuración.' );
		return json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
	};
	$json = array( 'id' => 21, 'url' => 'https://sitio.test/wp-content/uploads/cohete.json' );

	// 1. Registro y recursos.
	$w = $widget();
	check_lt( 'digitalisimo-lottie' === $w->get_name() && 'Animación Lottie' === $w->get_title() && array( 'digitalisimo' ) === $w->get_categories(), 'Nombre, título y categoría propios.' );
	check_lt( array( 'digitalisimo-lottie' ) === $w->get_style_depends() && array( 'digitalisimo-lottie' ) === $w->get_script_depends(), 'Sólo su hoja y su script (la librería no se declara): ' . implode( ',', $w->get_style_depends() ) . ' / ' . implode( ',', $w->get_script_depends() ) );
	check_lt( 'widgets/class-lottie.php' === Content_Module::WIDGETS['digitalisimo-lottie']['file'] && Lottie::class === Content_Module::WIDGETS['digitalisimo-lottie']['class'] && 'Animación Lottie' === Content_Module::TITLES['digitalisimo-lottie'], 'El módulo de contenido registra la clase y el título.' );
	check_lt( $w->has_widget_inner_wrapper() && ( $GLOBALS['lt_optimized'] = true ) && ! $w->has_widget_inner_wrapper(), 'Envoltorio interior como PRO (sin él con el marcado optimizado).' );
	$css = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/lottie.css' );
	check_lt( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_lt( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(|#[0-9a-f]{3,6}\b/i', $css ) && ! str_contains( $css, 'elementor' ) && ! str_contains( $css, 'e-lottie' ), 'La hoja no usa el kit, url(), colores propios ni clases del proveedor.' );
	foreach ( array( 'display:inline-block;max-width:var(--lottie-container-max-width);opacity:var(--lottie-container-opacity);width:var(--lottie-container-width)', 'opacity:var(--lottie-container-opacity-hover);transition-duration:var(--lottie-container-transition-duration-hover)', 'svg *{transition:none!important}', 'color:var(--caption-color);margin-top:var(--caption-margin-top);text-align:var(--caption-text-align)' ) as $look ) {
		check_lt( str_contains( $css, $look ), "La hoja reproduce el aspecto de PRO ($look)." );
	}
	check_lt( str_contains( $css, '.digi-lottie__link:focus-visible,.digi-lottie__container[role=button]:focus-visible{outline:2px solid;outline-offset:2px}' ) && (bool) preg_match( '/@media \(prefers-reduced-motion:reduce\)\{[^}]*\{transition:none\}\}/', $css ), 'Foco visible propio del enlace y del botón, y movimiento reducido.' );
	$js = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/js/lottie.js' );
	check_lt( strlen( $js ) <= 6144 && ! preg_match( '/\bjquery\s*\(|\bjquery\.[a-z]|\$\(|elementsHandler|swiper|bodymovin/i', $js ), 'El script pesa ' . strlen( $js ) . ' B (tope 6 144), sin jQuery ni el gestor de handlers.' );
	foreach ( array( "IntersectionObserver", "'100% 0px'", "'0px 0px 200px'", 'prefers-reduced-motion: reduce', 'playSegments', 'goToAndStop', "'Enter'===ev.key||' '===ev.key", 'frontend/element_ready/digitalisimo-lottie.default', 'L.destroy(c.name)' ) as $piece ) {
		check_lt( str_contains( $js, $piece ), "El script: $piece." );
	}
	$source = (string) file_get_contents( $root . 'modules/digitalisimo-content/widgets/class-lottie.php' );
	check_lt( ! preg_match( '/jquery\(|swiper|wp_enqueue_script|<iframe|ElementorPro\\\\|defaultAnimationUrl|lottie\/assets\/animations/i', $source ), 'El widget no usa jQuery, clases ni archivos de PRO (tampoco su animación de muestra).' );
	check_lt( is_file( $root . 'assets/lib/lottie/lottie.min.js' ) && str_contains( (string) file_get_contents( $root . 'assets/lib/lottie/lottie.min.js', false, null, 0, 64 ), 'Version: ' . Lottie::LIBRARY_VERSION ), 'La librería local existe con la versión declarada.' );
	$sample = (string) file_get_contents( $root . Lottie::SAMPLE );
	$anim   = json_decode( $sample, true );
	check_lt( strlen( $sample ) < 4096 && is_array( $anim ) && isset( $anim['v'], $anim['fr'], $anim['op'], $anim['layers'] ) && ! preg_match( '/logo|elementor|lottiefiles|"E-/i', $sample ), 'Animación de muestra propia, pequeña y sin logotipos ni marcas.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-content/widgets/class-lottie.php', 'modules/digitalisimo-content/assets/css/lottie.css', 'modules/digitalisimo-content/assets/js/lottie.js', Lottie::SAMPLE, 'modules/digitalisimo-convert/maps/lottie.php' ) as $file ) {
		check_lt( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file no nombra lo que la guarda del empaquetado prohíbe." );
	}

	// 2. Controles: los de la clase real de PRO frente a los propios.
	$map = require $root . 'modules/digitalisimo-convert/maps/lottie.php';
	$pro = new \ElementorPro\Modules\Lottie\Widgets\Lottie();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	$extra = array( 'digitalisimo_lottie_description', 'digitalisimo_lottie_button_text', 'digitalisimo_lottie_button_link' );
	$own   = array_values( array_diff( array_keys( $w->controls ), $extra ) );
	check_lt( array_keys( $pro->controls ) === $own, 'Los mismos controles en el mismo orden: ' . implode( ', ', array_diff( array_keys( $pro->controls ), $own ) ) . ' / ' . implode( ', ', array_diff( $own, array_keys( $pro->controls ) ) ) );
	check_lt( array_keys( $pro->groups ) === array_keys( $w->groups ) && array_values( array_diff( array_keys( $w->sections ), array( 'digitalisimo_lottie_text' ) ) ) === array_keys( $pro->sections ), 'Los mismos grupos y secciones en el mismo orden.' );
	foreach ( $pro->sections as $name => $section ) {
		check_lt( ( $section['condition'] ?? null ) === ( $w->sections[ $name ]['condition'] ?? null ) && ( $section['tab'] ?? null ) === ( $w->sections[ $name ]['tab'] ?? null ), "Sección $name: misma pestaña y condición." );
	}
	// `render_type` y `frontend_available` no se repiten: el script lee su configuración del marcado, así que todo cambio
	// del panel vuelve a pintar el widget (en PRO, el handler leía los ajustes del editor sin pintar).
	$same = array( 'type', 'default', 'tablet_default', 'mobile_default', 'condition', 'conditions', 'global', 'min', 'max', 'step', 'range', 'size_units', 'return_value', 'responsive', 'separator', 'dynamic', 'prefix_class', 'media_types', 'handles', 'scales', 'show_label', '_section' );
	foreach ( $pro->controls as $name => $control ) {
		$mine = $w->controls[ $name ];
		foreach ( $same as $arg ) {
			check_lt( ( $control[ $arg ] ?? null ) === ( $mine[ $arg ] ?? null ), "$name: $arg distinto de PRO (" . var_export( $control[ $arg ] ?? null, true ) . ' ≠ ' . var_export( $mine[ $arg ] ?? null, true ) . ').' );
		}
		check_lt( array_keys( (array) ( $control['options'] ?? array() ) ) === array_keys( (array) ( $mine['options'] ?? array() ) ), "$name: las mismas opciones." );
		check_lt( count( (array) ( $control['labels'] ?? array() ) ) === count( (array) ( $mine['labels'] ?? array() ) ), "$name: las mismas etiquetas del rango." );
		foreach ( (array) ( $control['selectors'] ?? array() ) as $selector => $value ) {
			check_lt( isset( $mine['selectors'][ $selector ] ) && $mine['selectors'][ $selector ] === $value, "$name: falta la declaración de PRO «{$selector} → {$value}»." );
		}
		check_lt( count( (array) ( $control['selectors'] ?? array() ) ) === count( (array) ( $mine['selectors'] ?? array() ) ), "$name: ninguna declaración CSS de más." );
	}
	$GLOBALS['lt_caps'] = array();
	$guest = $widget();
	$prog  = new \ElementorPro\Modules\Lottie\Widgets\Lottie();
	( new ReflectionMethod( $prog, 'register_controls' ) )->invoke( $prog );
	check_lt( array( 'media_file' ) === array_keys( $guest->controls['source']['options'] ) && 'hidden' === $guest->controls['source_external_url']['type'] && array_keys( $prog->controls['source']['options'] ) === array_keys( $guest->controls['source']['options'] ), 'Sin permiso para publicar páginas, sin URL externa (como PRO).' );
	$GLOBALS['lt_caps'] = array( 'publish_pages' => true );
	foreach ( $pro->groups as $name => $group ) {
		$mine = $w->groups[ $name ];
		foreach ( array( 'type', 'global', 'condition' ) as $arg ) {
			check_lt( ( $group[ $arg ] ?? null ) === ( $mine[ $arg ] ?? null ), "Grupo $name: $arg distinto de PRO." );
		}
		check_lt( Translator::rewrite_classes( $group['selector'], $map['classes'] ) === $mine['selector'], "Grupo $name: selector «{$mine['selector']}»." );
	}
	foreach ( $extra as $name ) {
		check_lt( 'digitalisimo_lottie_text' === $w->controls[ $name ]['_section'] && isset( $w->controls[ $name ]['dynamic'] ), "$name: el de Media_Text_Extras, en su sección." );
	}
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ) );
	foreach ( array_keys( $pro->controls ) as $name ) {
		check_lt( in_array( $name, $map['keep'], true ), "$name: el mapa no lo conserva." );
	}
	foreach ( array_keys( $pro->groups ) as $name ) {
		check_lt( $name === ( $map['groups'][ $name ] ?? '' ), "$name: el mapa no conserva el grupo." );
	}
	check_lt( count( $declared ) === count( $pro->controls ) + count( $pro->groups ), 'El mapa declara exactamente los controles y grupos de PRO.' );
	foreach ( $map['classes'] as $from => $to ) {
		check_lt( str_contains( $source . $css, ltrim( str_replace( '.elementor-widget-digitalisimo-lottie', '', $to ), '.' ) ) || '.elementor-widget-digitalisimo-lottie' === $to, "Clase $to del mapa: existe en el widget." );
	}

	// 3. Marcado.
	$GLOBALS['lt_optimized'] = false;
	$html = $render( array() );
	$c    = $config( $html );
	check_lt( str_starts_with( $html, '<div class="digi-lottie__container" data-digi-lottie="' ) && str_contains( $html, '<div class="digi-lottie__animation" aria-hidden="true"></div></div>' ) && ! str_contains( $html, '<a ' ) && ! str_contains( $html, 'caption' ) && ! str_contains( $html, 'role=' ), 'Por defecto: contenedor y animación decorativa, sin enlace, leyenda ni botón.' );
	check_lt( 'https://sitio.test/wp-content/plugins/digitalisimo-elements/assets/lib/lottie/lottie.min.js?ver=5.6.8' === $c['lib'] && 'https://sitio.test/wp-content/plugins/digitalisimo-elements/modules/digitalisimo-content/assets/lottie/default.json' === $c['path'] && 'digi-lottie-abc1234' === $c['name'], 'Librería local con su versión y animación de muestra propia.' );
	check_lt( array( 'lib', 'path', 'name', 'renderer', 'trigger', 'viewport', 'relative', 'loop', 'times', 'hoverOut', 'area', 'speed', 'start', 'end', 'reverse', 'lazy' ) === array_keys( $c ) && 'svg' === $c['renderer'] && 'arriving_to_viewport' === $c['trigger'] && array( 0, 100 ) === $c['viewport'] && false === $c['loop'] && 1 === $c['speed'] && 0 === $c['start'] && 100 === $c['end'] && false === $c['lazy'], 'Configuración con los valores por defecto de PRO: ' . json_encode( $c ) );
	$c = $config( $render( array( 'source_json' => $json, 'renderer' => 'canvas', 'trigger' => 'bind_to_scroll', 'viewport' => array( 'sizes' => array( 'start' => 20, 'end' => 80 ), 'unit' => '%' ), 'effects_relative_to' => 'page', 'loop' => 'yes', 'number_of_times' => '3', 'play_speed' => array( 'size' => 2.5 ), 'start_point' => array( 'size' => 10, 'unit' => '%' ), 'end_point' => array( 'size' => '', 'unit' => '%' ), 'reverse_animation' => 'yes', 'lazyload' => 'yes', 'trigger_x' => 'x' ) ) );
	check_lt( $json['url'] === $c['path'] && 'canvas' === $c['renderer'] && 'bind_to_scroll' === $c['trigger'] && array( 20, 80 ) === $c['viewport'] && 'page' === $c['relative'] && true === $c['loop'] && 3 === $c['times'] && 2.5 === $c['speed'] && 10 === $c['start'] && 0 === $c['end'] && true === $c['reverse'] && true === $c['lazy'], 'Configuración de un adjunto con todos los ajustes: ' . json_encode( $c ) );
	check_lt( 'https://cdn.test/anim.json' === $config( $render( array( 'source' => 'external_url', 'source_external_url' => array( 'url' => 'https://cdn.test/anim.json' ) ) ) )['path'], 'URL externa.' );
	check_lt( 'https://cdn.test/anim.json' === $config( $render( array( 'source_json' => array( 'id' => 5, 'url' => 'https://sitio.test/x.png' ), 'source_external_url' => array( 'url' => 'https://cdn.test/anim.json' ) ) ) )['path'], 'Como PRO: un adjunto que no acaba en json cede a la URL externa guardada.' );
	check_lt( str_ends_with( $config( $render( array( 'source_json' => array( 'id' => 5, 'url' => 'https://sitio.test/x.JSON' ) ) ) )['path'], 'x.JSON' ) && 'arriving_to_viewport' === $config( $render( array( 'trigger' => 'x"><script>' ) ) )['trigger'], 'Extensión sin distinguir mayúsculas; un disparador desconocido vuelve al de por defecto.' );
	// Leyenda (reglas de Caption_Helper).
	$cap = static function ( array $s ) use ( $render ) {
		return preg_match( '~<p class="digi-lottie__caption">(.*?)</p>~', $render( $s ), $m ) ? $m[1] : null;
	};
	check_lt( 'Cohete &lt;b&gt;verde&lt;/b&gt;' === $cap( array( 'source_json' => $json, 'caption_source' => 'title' ) ) && 'Leyenda del cohete' === $cap( array( 'source_json' => $json, 'caption_source' => 'caption' ) ), 'Título y leyenda del adjunto, escapados.' );
	check_lt( null === $cap( array( 'source_json' => array( 'id' => 22, 'url' => 'https://sitio.test/p.json' ), 'caption_source' => 'caption' ) ), 'La leyenda de un adjunto que quien mira no puede leer no se muestra.' );
	$GLOBALS['lt_caps']['edit_post'] = true;
	check_lt( 'secreto' === $cap( array( 'source_json' => array( 'id' => 22, 'url' => 'https://sitio.test/p.json' ), 'caption_source' => 'caption' ) ), 'Quien puede editarlo, sí la ve.' );
	unset( $GLOBALS['lt_caps']['edit_post'] );
	check_lt( 'Mi &lt;texto&gt;' === $cap( array( 'source_json' => $json, 'caption_source' => 'custom', 'caption' => 'Mi <texto>' ) ) && 'Externa' === $cap( array( 'source' => 'external_url', 'caption' => 'Externa' ) ) && null === $cap( array( 'source' => 'external_url', 'caption' => '' ) ) && null === $cap( array( 'source_json' => $json, 'caption' => 'oculta' ) ), 'Leyenda personalizada con un archivo o con una URL externa; nada con «Ninguna».' );
	// Enlace y botón.
	$html = $render( array( 'link_to' => 'custom', 'custom_link' => array( 'url' => 'https://sitio.test/destino', 'is_external' => 'on', 'nofollow' => 'on' ), 'source_json' => $json ) );
	check_lt( str_starts_with( $html, '<a href="https://sitio.test/destino" target="_blank" rel="nofollow" class="digi-lottie__link" aria-label="Cohete verde"><div class="digi-lottie__container"' ) && str_ends_with( $html, '</div></a>' ), 'Enlace alrededor del contenedor con el título del adjunto como nombre: ' . $html );
	check_lt( str_contains( $render( array( 'link_to' => 'custom', 'custom_link' => array( 'url' => 'https://sitio.test/d' ) ) ), 'aria-label="Animación"' ) && ! str_contains( $render( array( 'link_to' => 'custom', 'custom_link' => array( 'url' => 'https://sitio.test/d' ), 'source_json' => $json, 'caption_source' => 'title' ) ), 'aria-label' ), 'Sin adjunto, «Animación»; con leyenda, el nombre sale del texto.' );
	check_lt( ! str_contains( $render( array( 'link_to' => 'none', 'custom_link' => array( 'url' => 'https://sitio.test/d' ) ) ), '<a ' ) && ! str_contains( $render( array( 'link_to' => 'custom', 'custom_link' => array( 'url' => '' ) ) ), '<a ' ), 'Como PRO: sin «URL personalizada» o sin dirección, sin enlace.' );
	$c = $config( $render( array( 'trigger' => 'on_click', 'link_to' => 'custom', 'custom_link' => array( 'url' => 'https://sitio.test/d', 'is_external' => 'on' ), 'link_timeout' => 800 ) ) );
	check_lt( 800 === $c['timeout'] && array( 'url' => 'https://sitio.test/d', 'external' => true ) === $c['link'], 'Espera antes del enlace con «Al pulsar».' );
	check_lt( ! isset( $config( $render( array( 'trigger' => 'on_hover', 'link_to' => 'custom', 'custom_link' => array( 'url' => 'https://sitio.test/d' ), 'link_timeout' => 800 ) ) )['timeout'] ), 'Sin espera con otro disparador.' );
	$html = $render( array( 'trigger' => 'on_click' ) );
	check_lt( str_contains( $html, 'role="button" tabindex="0" aria-label="Reproducir la animación"' ) && ! str_contains( $render( array( 'trigger' => 'on_click', 'link_to' => 'custom', 'custom_link' => array( 'url' => 'https://sitio.test/d' ) ) ), 'role="button"' ), '«Al pulsar» sin enlace: botón para el teclado.' );
	// Descripción y botón de DIGITALÍSIMO, como sobre el Lottie de PRO.
	$extras = array( 'digitalisimo_lottie_description' => 'Texto <script>x()</script>', 'digitalisimo_lottie_button_text' => 'Ver más', 'digitalisimo_lottie_button_link' => array( 'url' => 'https://sitio.test/mas' ) );
	$html   = $render( $extras );
	check_lt( str_ends_with( $html, Media_Text_Extras::extra( 'lottie', $extras ) ) && str_contains( $html, '<div class="digi-media-description"><p>Texto </p></div><a class="digi-media-button elementor-button" href="https://sitio.test/mas">Ver más</a>' ), 'Descripción y botón al final, con el marcado de Media_Text_Extras.' );
	check_lt( '' === Media_Text_Extras::kind( $w ), 'Media_Text_Extras no vuelve a añadirlos al widget propio.' );

	// 4. Conversión de ajustes reales de PRO.
	$cases = array(
		'defaults' => array(),
		'media'    => array( 'source_json' => $json, 'caption_source' => 'title', 'align' => 'left', 'align_mobile' => 'right', 'trigger' => 'on_hover', 'on_hover_out' => 'reverse', 'hover_area' => 'container', 'renderer' => 'canvas' ),
		'external' => array( 'source' => 'external_url', 'source_external_url' => array( 'url' => 'https://cdn.test/a.json', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ), 'caption' => 'Hola', 'trigger' => 'none', 'loop' => 'yes', 'number_of_times' => 2, 'play_speed' => array( 'unit' => 'px', 'size' => 0.5, 'sizes' => array() ), 'start_point' => array( 'unit' => '%', 'size' => 20, 'sizes' => array() ), 'end_point' => array( 'unit' => '%', 'size' => 70, 'sizes' => array() ) ),
		'click'    => array( 'source_json' => $json, 'trigger' => 'on_click', 'link_to' => 'custom', 'custom_link' => array( 'url' => 'https://sitio.test/d', 'is_external' => 'on', 'nofollow' => '', 'custom_attributes' => '' ), 'link_timeout' => 1000, 'reverse_animation' => 'yes', 'lazyload' => 'yes' ),
		'scroll'   => array( 'source_json' => $json, 'trigger' => 'bind_to_scroll', 'viewport' => array( 'unit' => '%', 'size' => '', 'sizes' => array( 'start' => 10, 'end' => 90 ) ), 'effects_relative_to' => 'page' ),
		'style'    => array( 'source_json' => $json, 'caption_source' => 'caption', 'width' => array( 'unit' => '%', 'size' => 60, 'sizes' => array() ), 'width_tablet' => array( 'unit' => 'px', 'size' => 300, 'sizes' => array() ), 'space' => array( 'unit' => 'px', 'size' => 500, 'sizes' => array() ), 'opacity' => array( 'unit' => 'px', 'size' => 0.8, 'sizes' => array() ), 'opacity_hover' => array( 'unit' => 'px', 'size' => 1, 'sizes' => array() ), 'css_filters_css_filter' => 'custom', 'css_filters_blur' => array( 'unit' => 'px', 'size' => 2, 'sizes' => array() ), 'css_filters_hover_css_filter' => 'custom', 'css_filters_hover_saturate' => array( 'unit' => 'px', 'size' => 150, 'sizes' => array() ), 'background_hover_transition' => array( 'unit' => 'px', 'size' => 0.6, 'sizes' => array() ), 'caption_align' => 'right', 'text_color' => '#123456', 'caption_typography_typography' => 'custom', 'caption_typography_font_size' => array( 'unit' => 'px', 'size' => 18, 'sizes' => array() ), 'caption_space' => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ) ),
		'dynamic'  => array( 'source' => 'external_url', '__dynamic__' => array( 'source_external_url' => '[elementor-tag id="1" name="acf-url" settings="%7B%7D"]', 'caption' => '[elementor-tag id="2" name="post-title" settings="%7B%7D"]' ), '__globals__' => array( 'text_color' => 'globals/colors?id=accent', 'caption_typography_typography' => 'globals/typography?id=accent' ) ),
		'css'      => array( 'source_json' => $json, 'caption_source' => 'title', 'digitalisimo_lottie_description' => 'Más', 'digitalisimo_lottie_button_text' => 'Ir', 'digitalisimo_lottie_button_link' => array( 'url' => 'https://sitio.test/ir' ), 'custom_css' => "selector .e-lottie__container{border-radius:50%}\nselector .e-lottie__container__link:hover .e-lottie__animation{opacity:.5}\n.elementor-widget-lottie .e-lottie__caption{font-weight:700}", '_css_classes' => 'mi-lottie' ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'lottie', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_lt( count( $cases ) === count( $log ), 'Se convierte cada Lottie del árbol.' );
	$lands = static function ( string $key ) use ( $w ) {
		if ( isset( $w->controls[ $key ] ) || in_array( $key, array( '_digitalisimo_pro', '__dynamic__', '__globals__', 'custom_css', '_css_classes' ), true ) ) {
			return true;
		}
		foreach ( array( '_tablet', '_mobile' ) as $device ) {
			if ( str_ends_with( $key, $device ) && isset( $w->controls[ substr( $key, 0, -strlen( $device ) ) ]['responsive'] ) ) {
				return true;
			}
		}
		foreach ( array_keys( $w->groups ) as $group ) {
			if ( str_starts_with( $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_lt( 'digitalisimo-lottie' === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa a la Animación Lottie con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_lt( $lands( (string) $key ), $element['id'] . ": la clave $key no cae en un control de la Animación Lottie." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_lt( $lands( (string) $key ), $element['id'] . ": $bag.$key no cae en un control de la Animación Lottie." );
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_lt( array( 'css:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni pro.approx; sólo el CSS: ' . implode( ', ', $codes ) );
	check_lt( 'lottie' === $out['defaults']['_digitalisimo_pro'] && 'arriving_to_viewport' === $out['defaults']['trigger'] && array( 'size' => 1 ) === $out['defaults']['play_speed'], 'Un Lottie sin cambios llega con los valores de PRO.' );
	check_lt( 'right' === $out['media']['align_mobile'] && 'reverse' === $out['media']['on_hover_out'] && 'container' === $out['media']['hover_area'], 'Dispositivos y ajustes del cursor.' );
	check_lt( isset( $out['dynamic']['__dynamic__']['source_external_url'], $out['dynamic']['__dynamic__']['caption'] ) && 'globals/colors?id=accent' === $out['dynamic']['__globals__']['text_color'] && 'globals/typography?id=accent' === $out['dynamic']['__globals__']['caption_typography_typography'], 'Etiquetas dinámicas y globales del kit.' );
	check_lt( '#123456' === $out['style']['text_color'] && 'custom' === $out['style']['css_filters_hover_css_filter'] && array( 'unit' => 'px', 'size' => 300, 'sizes' => array() ) === $out['style']['width_tablet'], 'Estilos, grupos y dispositivos con su nombre.' );
	check_lt( "selector .digi-lottie__container{border-radius:50%}\nselector .digi-lottie__link:hover .digi-lottie__animation{opacity:.5}\n.elementor-widget-digitalisimo-lottie .digi-lottie__caption{font-weight:700}" === $out['css']['custom_css'] && 'mi-lottie' === $out['css']['_css_classes'] && 'Ir' === $out['css']['digitalisimo_lottie_button_text'], 'El CSS personalizado pasa a las clases propias y los extras de DIGITALÍSIMO se conservan: ' . $out['css']['custom_css'] );
	foreach ( $out as $id => $settings ) {
		unset( $settings['__dynamic__'], $settings['__globals__'] );
		check_lt( str_contains( $render( $settings ), 'class="digi-lottie__container"' ), "$id: lo convertido se pinta." );
	}
	check_lt( 1000 === $config( $render( $out['click'] ) )['timeout'] && 'https://cdn.test/a.json' === $config( $render( $out['external'] ) )['path'] && 'Hola' === $cap( $out['external'] ) && str_contains( $render( $out['css'] ), 'href="https://sitio.test/ir">Ir</a>' ), 'Lo convertido se comporta como en PRO.' );
	check_lt( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	check_lt( 'lottie' === Registry::map( 'lottie', true )['source'], 'El registro encuentra el mapa.' );

	echo 'DIGITALÍSIMO Elements: Animación Lottie (' . count( $w->controls ) . ' controles, ' . count( $w->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B, librería local al acercarse) y su mapa desde el Lottie de PRO (' . count( $cases ) . " casos) validados.\n";
}
