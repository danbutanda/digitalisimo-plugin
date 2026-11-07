<?php
/**
 * Puntos de interés (`digitalisimo-hotspot`, C2 de docs/PRO_WIDGETS_OBJECTIVE.md, frente C de docs/pro/c2.md), el widget
 * propio que sustituye al Hotspot de PRO, y su mapa `modules/digitalisimo-convert/maps/hotspot.php`:
 * - registro y recursos (hoja ≤ 4 KB y script ≤ 6 KB sin dependencias, movimiento reducido, foco visible, sin
 *   `--e-global-*`, `url()`, jQuery ni clases del proveedor);
 * - los controles se comparan con los del Hotspot de PRO **cargado de verdad** (con su padre, el widget Imagen del
 *   núcleo, y los `remove_control`/`update_control` de PRO): mismos nombres, tipos, valores por defecto (también por
 *   dispositivo), opciones, condiciones, unidades, rangos y selectores (con las clases y variables propias); cobertura
 *   del mapa;
 * - marcado del servidor: botón con `aria-expanded`/`aria-controls`, enlace con `aria-describedby`, nombre de los
 *   puntos sin texto, «siempre visible», animaciones de dirección (la misma dirección que ganaba en la hoja de PRO),
 *   secuencia, disparador por dispositivo, imagen del núcleo y escape;
 * - conversión con `Converter::tree()` de ajustes reales del Hotspot (por defecto, filas propias, estilos, `__dynamic__`,
 *   `__globals__`, CSS personalizado con clases de PRO): ningún `pro.unmapped`, cada clave cae en un control propio y
 *   lo convertido se pinta.
 */
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro\Base {
	trait Base_Widget_Trait {}
}
namespace Elementor {
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const NUMBER = 'number'; const DIMENSIONS = 'dimensions';
		const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const ICONS = 'icons'; const WYSIWYG = 'wysiwyg';
		const DIVIDER = 'divider'; const HOVER_ANIMATION = 'hover_animation'; const TAB_CONTENT = 'content'; const TAB_STYLE = 'style'; const HIDDEN = 'hidden';
	}
	trait Stub_Controls {
		public $controls = array();
		public function start_controls_tabs( $id, $args = array() ) {}
		public function end_controls_tabs() {}
		public function start_controls_tab( $id, $args ) {}
		public function end_controls_tab() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function get_controls() { return $this->controls; }
	}
	class Repeater { use Stub_Controls; }
	class Widget_Base {
		use Stub_Controls;
		public $settings = array();
		public $groups   = array();
		public $sections = array();
		public $id       = 'abc1234';
		private $attrs   = array();
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
			$out = $this->settings + $defaults;
			// Como el repetidor del núcleo: cada fila con los valores por defecto de sus campos.
			foreach ( $this->controls as $name => $control ) {
				if ( 'repeater' === ( $control['type'] ?? '' ) && is_array( $out[ $name ] ?? null ) ) {
					foreach ( $out[ $name ] as $i => $row ) {
						foreach ( $control['fields'] as $field => $spec ) {
							if ( ! array_key_exists( $field, $row ) && array_key_exists( 'default', $spec ) ) {
								$row[ $field ] = $spec['default'];
							}
						}
						$out[ $name ][ $i ] = $row;
					}
				}
			}
			return $out;
		}
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function update_control( $name, $args ) { $this->controls[ $name ] = array_merge( $this->controls[ $name ], $args ); }
		public function remove_control( $name ) { unset( $this->controls[ $name ], $this->sections[ $name ] ); }
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
			$this->add_render_attribute( $element, 'href', esc_url( (string) ( $url['url'] ?? '' ) ) );
			if ( ! empty( $url['is_external'] ) ) {
				$this->add_render_attribute( $element, 'target', '_blank' );
			}
			if ( ! empty( $url['nofollow'] ) ) {
				$this->add_render_attribute( $element, 'rel', 'nofollow' );
			}
			return $this;
		}
	}
	class Group_Control_Image_Size {
		public static function get_type() { return 'image-size'; }
		public static function get_attachment_image_html( $settings, $size_key = 'image', $image_key = null ) {
			$image = $settings[ $image_key ?? $size_key ];
			$size  = $settings[ $size_key . '_size' ] ?? '';
			if ( ! empty( $image['id'] ) ) {
				return wp_get_attachment_image( $image['id'], $size, false, array( 'class' => "attachment-$size size-$size wp-image-{$image['id']}" ) );
			}
			return '<img src="' . esc_url( $image['url'] ) . '" title="" alt="" loading="lazy" />';
		}
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Css_Filter { public static function get_type() { return 'css-filter'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Utils { public static function get_placeholder_image_src() { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png'; } }
	class Icons_Manager {
		public static function render_icon( $icon, $attrs = array() ) {
			echo '<svg class="e-font-icon-svg" data-icon="' . esc_attr( (string) $icon['value'] ) . '"' . ( isset( $attrs['aria-hidden'] ) ? ' aria-hidden="true"' : '' ) . '></svg>';
		}
	}
	class Plugin { public static $instance; }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|#)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function wpautop( $v ) { return '<p>' . $v . '</p>'; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function is_rtl() { return ! empty( $GLOBALS['hs_rtl'] ); }
	function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
		$attr += array( 'loading' => 'lazy', 'decoding' => 'async' );
		$out = '<img width="1200" height="800" src="https://sitio.test/wp-content/uploads/img-' . (int) $id . '-' . $size . '.png"';
		foreach ( $attr as $k => $v ) {
			$out .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		return $out . ' alt="Texto alternativo ' . (int) $id . '" srcset="https://sitio.test/wp-content/uploads/img-' . (int) $id . '-768.png 768w" sizes="(max-width: 1200px) 100vw, 1200px">';
	}
	function check_hs( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'elementor/includes/widgets/image.php';
	require $root . 'modules/hotspot/widgets/hotspot.php';
	require $root . 'modules/digitalisimo-widgets/class-media-text-extras.php';
	require $root . 'modules/digitalisimo-content/class-module.php';
	require $root . 'modules/digitalisimo-content/widgets/class-hotspot.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content_Module;
	use Digitalisimo\Elements\Content\Widgets\Hotspot;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$widget = static function ( array $settings = array() ) {
		$w = new Hotspot();
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
	$count = static function ( $needle, $html ) { return substr_count( $html, $needle ); };

	// 1. Registro y recursos.
	$w = $widget();
	check_hs( 'digitalisimo-hotspot' === $w->get_name() && 'Puntos de interés' === $w->get_title() && array( 'digitalisimo' ) === $w->get_categories() && 'eicon-image-hotspot' === $w->get_icon(), 'Nombre, título, icono y categoría propios.' );
	check_hs( array( 'digitalisimo-hotspot' ) === $w->get_style_depends() && array( 'digitalisimo-hotspot' ) === $w->get_script_depends() && false === $w->has_widget_inner_wrapper(), 'Su hoja y su script; sin envoltorio interior: ' . implode( ',', $w->get_style_depends() ) . ' / ' . implode( ',', $w->get_script_depends() ) );
	check_hs( 'widgets/class-hotspot.php' === Content_Module::WIDGETS['digitalisimo-hotspot']['file'] && Hotspot::class === Content_Module::WIDGETS['digitalisimo-hotspot']['class'] && 'Puntos de interés' === Content_Module::TITLES['digitalisimo-hotspot'] && ! in_array( 'digitalisimo-hotspot', Content_Module::NESTED, true ), 'El módulo de contenido registra la clase y el título.' );
	$css = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/hotspot.css' );
	$js  = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/js/hotspot.js' );
	check_hs( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_hs( strlen( $js ) > 0 && strlen( $js ) <= 6144, 'El script pesa ' . strlen( $js ) . ' B (tope 6 144).' );
	check_hs( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( str_replace( '.elementor-widget-digitalisimo-hotspot', '', $css ), 'elementor' ) && ! str_contains( $css, 'e-hotspot' ), 'La hoja no usa el kit, url(), ni clases del proveedor (sólo la del envoltorio del widget).' );
	check_hs( str_contains( $css, '@media(prefers-reduced-motion:reduce){.digi-hotspots:not(.digi-hotspots--motion) *{animation:none!important}.digi-hotspots *{transition:none!important}}' ) && str_contains( $css, '.digi-hs__button:focus-visible,a.digi-hs:focus-visible{outline:2px solid;outline-offset:2px}' ), 'Movimiento reducido (sin latido, expansión, secuencia ni transiciones) y foco visible de 2 px a 2 px.' );
	check_hs( str_contains( $css, '.digi-hs__button:before{content:"";inset:-5px;' ), 'Área de pulsación de 5 px alrededor del punto, como PRO (26 + 10 px por defecto: más de 24 × 24).' );
	preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $colors );
	check_hs( array() === array_diff( array_unique( array_map( 'strtolower', $colors[0] ) ), array( '#fff' ) ), 'Sólo los colores que PRO usaba por defecto: ' . implode( ', ', $colors[0] ) );
	foreach ( array( '--hs-size,18px', '--hs-padding,4px', '--hs-border-radius,3px', '--tip-padding,8px', '--tip-transition-duration,.5s', '--white-space,nowrap', 'scale(1.1)', 'scale(1.5)', 'calc(100% + 1px)', 'opacity:.7' ) as $look ) {
		check_hs( str_contains( $css, $look ), "La hoja reproduce el aspecto de PRO ($look)." );
	}
	check_hs( ! preg_match( '/jquery|\$\(|elementorModules|swiper/i', $js ) && str_contains( $js, "'Escape' !== e.key" ) && str_contains( $js, 'IntersectionObserver' ) && str_contains( $js, "frontend/element_ready/digitalisimo-hotspot.default" ), 'Script sin jQuery ni módulos del núcleo; Escape, secuencia al entrar en la pantalla y editor.' );
	$source = (string) file_get_contents( $root . 'modules/digitalisimo-content/widgets/class-hotspot.php' );
	check_hs( ! preg_match( '/jquery\(|swiper|wp_enqueue_script|<iframe|font-awesome|ElementorPro/i', $source ), 'Sin jQuery, Swiper, iframes, fuentes de iconos ni clases de PRO.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-content/widgets/class-hotspot.php', 'modules/digitalisimo-content/assets/css/hotspot.css', 'modules/digitalisimo-content/assets/js/hotspot.js', 'modules/digitalisimo-convert/maps/hotspot.php' ) as $file ) {
		check_hs( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file no nombra lo que la guarda del empaquetado prohíbe." );
	}

	// 2. Controles frente al Hotspot de PRO cargado (con el widget Imagen del núcleo y sus cambios).
	$pro = new \ElementorPro\Modules\Hotspot\Widgets\Hotspot();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	$map     = require $root . 'modules/digitalisimo-convert/maps/hotspot.php';
	$rename  = $map['classes'] + array(
		'{{WRAPPER}} img'                                   => '{{WRAPPER}} .digi-hotspots>img',
		'{{WRAPPER}}:hover img'                             => '{{WRAPPER}}:hover .digi-hotspots>img',
		'{{WRAPPER}} .elementor-widget-container>img:hover' => '{{WRAPPER}} .digi-hotspots>img:hover',
	);
	$own_sel = static function ( array $selectors ) use ( $rename ) {
		$out = array();
		foreach ( $selectors as $selector => $rule ) {
			$selector = trim( (string) preg_replace( '/\s+/', ' ', $selector ) );
			if ( isset( $rename[ $selector ] ) ) {
				$selector = $rename[ $selector ];
			} else {
				$selector = Translator::rewrite_classes( $selector, $rename );
			}
			$out[ $selector ] = (string) preg_replace( array( '/(?<![a-z])--hotspot-/', '/(?<![a-z])--tooltip-/' ), array( '--hs-', '--tip-' ), (string) $rule );
		}
		return $out;
	};
	$compare = static function ( array $a, array $b, string $where ) use ( $own_sel ) {
		check_hs( array_keys( $a ) === array_keys( $b ), "$where: los mismos controles en el mismo orden. PRO: " . implode( ',', array_keys( $a ) ) . ' · propio: ' . implode( ',', array_keys( $b ) ) );
		foreach ( $a as $name => $pc ) {
			$oc = $b[ $name ];
			foreach ( array( 'type', 'default', 'tablet_default', 'mobile_default', 'condition', 'conditions', 'size_units', 'range', 'frontend_available', 'render_type', 'toggle', 'responsive', 'selectors_dictionary', 'return_value', 'min', 'max', 'step', 'media_types', 'dynamic', 'skin', 'label_block', 'title_field', 'exclude', 'style', 'classes', 'separator' ) as $prop ) {
				$pv = $pc[ $prop ] ?? null;
				$ov = $oc[ $prop ] ?? null;
				if ( 'default' === $prop && 'hotspot_tooltip_content' === $name ) {
					check_hs( 'Escribe aquí la información emergente' === $ov && 'Add Your Tooltip Text Here' === $pv, "$where.$name: texto de muestra propio en español (el de PRO llega por el mapa)." );
					continue;
				}
				check_hs( $pv === $ov, "$where.$name: $prop distinto. PRO " . json_encode( $pv ) . ' · propio ' . json_encode( $ov ) );
			}
			check_hs( array_keys( (array) ( $pc['options'] ?? array() ) ) === array_keys( (array) ( $oc['options'] ?? array() ) ), "$where.$name: las mismas opciones en el mismo orden." );
			check_hs( isset( $pc['global'] ) === isset( $oc['global'] ) && ( $pc['global'] ?? null ) === ( $oc['global'] ?? null ), "$where.$name: el mismo color o tipografía global por defecto." );
			check_hs( $own_sel( (array) ( $pc['selectors'] ?? array() ) ) === (array) ( $oc['selectors'] ?? array() ), "$where.$name: los mismos selectores con las clases y variables propias. PRO " . json_encode( $own_sel( (array) ( $pc['selectors'] ?? array() ) ) ) . ' · propio ' . json_encode( $oc['selectors'] ?? array() ) );
			check_hs( ! empty( $oc['label'] ) || in_array( $oc['type'], array( 'divider' ), true ), "$where.$name: con su etiqueta." );
		}
	};
	// Leyenda que `Media_Text_Extras` añade al Hotspot de PRO (rasgo propio en section_image y su sección de estilo): los
	// mismos controles, en las mismas secciones, con los mismos selectores.
	$extras = new \Elementor\Widget_Base();
	\Digitalisimo\Elements\Media_Text_Extras::hotspot_controls( $extras );
	\Digitalisimo\Elements\Media_Text_Extras::hotspot_style( $extras );
	check_hs( array( 'digitalisimo_hotspot_caption', 'digitalisimo_hotspot_caption_align', 'digitalisimo_hotspot_caption_color', 'digitalisimo_hotspot_caption_space' ) === array_keys( $extras->controls ) && array( 'digitalisimo_hotspot_caption_typography' ) === array_keys( $extras->groups ) && isset( $w->sections['digitalisimo_hotspot_caption_style'] ), 'Leyenda: los controles de Media_Text_Extras y su sección de estilo.' );
	foreach ( $extras->controls as $name => $control ) {
		unset( $control['label'], $control['description'], $w->controls[ $name ]['label'], $w->controls[ $name ]['description'] );
		foreach ( (array) ( $control['options'] ?? array() ) as $k => $o ) {
			unset( $control['options'][ $k ]['title'], $w->controls[ $name ]['options'][ $k ]['title'] );
		}
		check_hs( $control === $w->controls[ $name ], "Leyenda: $name igual que en Media_Text_Extras (salvo los textos traducibles)." );
	}
	check_hs( $extras->groups['digitalisimo_hotspot_caption_typography'] === $w->groups['digitalisimo_hotspot_caption_typography'], 'Leyenda: la misma tipografía.' );
	$w = $widget();
	$pro_main = $pro->controls;
	// Controles ocultos de lo convertido (`pro_img_*`): los selectores de los dispositivos que PRO dejaba al widget Imagen
	// del núcleo (`{{WRAPPER}} img`, sobre la imagen dentro del contenedor del escritorio), sin valor por defecto.
	$hidden = array( 'pro_img_width' => 'width', 'pro_img_space' => 'max-width', 'pro_img_height' => 'height' );
	foreach ( $hidden as $name => $property ) {
		check_hs( array( 'type' => 'hidden', 'selectors' => array( '{{WRAPPER}} .digi-hotspots>img' => $property . ': {{VALUE}};' ), 'responsive' => true ) === $w->controls[ $name ], "$name: oculto, por dispositivo y sin valor por defecto, con el selector del widget Imagen (" . json_encode( $w->controls[ $name ] ) . ').' );
	}
	$image_widget = (string) file_get_contents( $root . 'elementor/includes/widgets/image.php' );
	foreach ( array( 'width' => 'width', 'space' => 'max-width', 'height' => 'height' ) as $name => $property ) {
		check_hs( 1 === preg_match( "/'" . $name . "',\s*\[.*?'selectors' => \[\s*'\{\{WRAPPER\}\} img' => '" . $property . ": \{\{SIZE\}\}\{\{UNIT\}\};',/s", $image_widget ), "Premisa: el $name del widget Imagen del núcleo se aplica a la imagen ($property)." );
		check_hs( isset( $pro->controls[ $name ]['selectors']['{{WRAPPER}}'] ), "Premisa: PRO cambia el selector de escritorio de $name con update_control() (no los de los dispositivos)." );
	}
	$own_main = array_diff_key( $w->controls, $extras->controls, $hidden );
	$pro_rows = $pro_main['hotspot']['fields'];
	$own_rows = $own_main['hotspot']['fields'];
	unset( $pro_main['hotspot']['fields'], $own_main['hotspot']['fields'] );
	// Orden del panel por pestaña: PRO añade sus secciones después de las del widget Imagen; aquí cada pestaña queda igual.
	$order = array( 'image', 'hotspot', 'hotspot_animation', 'hotspot_sequenced_animation', 'hotspot_sequenced_animation_duration', 'tooltip_position', 'tooltip_trigger', 'tooltip_animation', 'tooltip_animation_duration' );
	$sort  = static function ( array $controls ) use ( $order ) {
		$first = array_intersect_key( array_merge( array_flip( $order ), $controls ), $controls );
		return $first + $controls;
	};
	$compare( $sort( $pro_main ), $sort( $own_main ), 'widget' );
	$compare( $pro_rows, $own_rows, 'hotspot' );
	// `remove_control()` de PRO no quita los grupos de la leyenda (`caption_typography`, `caption_text_shadow`): quedan sin
	// sección ni marcado que estilar, así que no tienen equivalente.
	unset( $pro->groups['caption_typography'], $pro->groups['caption_text_shadow'] );
	$own_groups = array_diff_key( $w->groups, $extras->groups );
	check_hs( array_keys( $pro->groups ) === array_keys( $own_groups ), 'Los mismos grupos: ' . implode( ',', array_keys( $own_groups ) ) );
	foreach ( $pro->groups as $name => $group ) {
		$own = $w->groups[ $name ];
		check_hs( $group['type'] === $own['type'] && ( $group['default'] ?? null ) === ( $own['default'] ?? null ) && ( $group['condition'] ?? null ) === ( $own['condition'] ?? null ) && ( $group['global'] ?? null ) === ( $own['global'] ?? null ) && ( $group['exclude'] ?? null ) === ( $own['exclude'] ?? null ), "Grupo $name: tipo, valor por defecto, condición, global y exclusiones de PRO." );
		if ( isset( $group['selector'] ) ) {
			check_hs( array_keys( $own_sel( array( $group['selector'] => '' ) ) ) === array( $own['selector'] ), "Grupo $name: el mismo selector con las clases propias (" . $own['selector'] . ').' );
		}
	}
	check_hs( ! isset( $w->controls['caption_source'], $w->controls['link_to'], $w->controls['hover_animation'], $w->controls['caption_typography'] ), 'Sin los controles del widget Imagen que PRO quita (leyenda, enlace, lightbox, animación al pasar).' );
	// Cobertura del mapa: cada control de PRO (los del Hotspot y los heredados que quedan) declarado, y `keep` sólo de controles propios.
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), $map['ui'], array_keys( $map['repeaters'] ), $map['repeaters']['hotspot']['keep'] );
	foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro_rows ) ) as $name ) {
		if ( in_array( $pro->controls[ $name ]['type'] ?? '', array( 'divider' ), true ) ) {
			continue;
		}
		check_hs( in_array( $name, $declared, true ) || isset( $map['groups'][ $name ] ), "$name: el mapa no lo declara." );
	}
	foreach ( $map['keep'] as $name ) {
		check_hs( isset( $w->controls[ $name ] ), "keep $name: no es un control de los Puntos de interés." );
	}
	foreach ( $map['repeaters']['hotspot']['keep'] as $name ) {
		check_hs( isset( $own_rows[ $name ] ), "keep hotspot.$name: no es un campo del repetidor." );
	}
	foreach ( $map['ui'] as $name ) {
		check_hs( 'heading' === ( $w->controls[ $name ]['type'] ?? $own_rows[ $name ]['type'] ?? '' ), "$name: encabezado del panel." );
	}
	foreach ( $map['classes'] as $from => $to ) {
		$plain = ltrim( $to, '.' );
		check_hs( in_array( $to, array( '.elementor-widget-digitalisimo-hotspot', '.digi-hotspots' ), true ) || str_contains( $css, $to ) || str_contains( $source, $plain ) || str_contains( $source, "'" . preg_replace( '/-(left|right|top|bottom)$/', '-', $plain ) . "' ." ), "Clase $to del mapa: existe en la hoja o en el marcado." );
	}

	// 3. Marcado del servidor.
	check_hs( str_starts_with( $render( array( '_digitalisimo_pro' => 'hotspot' ) ), '<div class="digi-hotspots digi-hotspots--motion" ' ), 'Lo convertido de PRO sigue animado con movimiento reducido, como PRO (digi-hotspots--motion).' );
	$html = $render( array() );
	check_hs( str_starts_with( $html, '<div class="digi-hotspots" data-digi-trigger="{&quot;desktop&quot;:&quot;click&quot;}">' ) && str_contains( $html, '<img src="https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png"' ), 'Por defecto: la imagen de muestra y el disparador «al pulsar».' );
	check_hs( 1 === $count( '<div class="digi-hs ', $html ) && str_contains( $html, 'digi-hs--pos-left digi-hs--pos-top digi-hs--circle' ) && str_contains( $html, '<button class="digi-hs__button digi-hs--expand" type="button" aria-expanded="false" aria-controls="digi-hotspot-abc1234-0" aria-label="Punto de interés 1"><span class="digi-hs__outer"></span><span class="digi-hs__inner"></span></button>' ), 'Un punto circular: botón con estado, control y nombre, y la animación «Expansión».' );
	check_hs( str_contains( $html, '<div id="digi-hotspot-abc1234-0" class="digi-hs__tip digi-hs--tip-pos digi-hs--fade-in-out">Escribe aquí la información emergente</div>' ), 'Información emergente con su id, su posición y la animación por defecto.' );
	$rows = array(
		array( '_id' => 'a1', 'hotspot_label' => 'Cocina', 'hotspot_tooltip_content' => '<p>Isla <strong>central</strong></p><script>x()</script>' ),
		array( '_id' => 'a2', 'hotspot_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ), 'hotspot_link' => array( 'url' => 'https://a.test/x', 'is_external' => 'on' ) ),
		array( '_id' => 'a3', 'hotspot_label' => 'Baño', 'hotspot_icon' => array( 'value' => 'fas fa-bath', 'library' => 'fa-solid' ), 'hotspot_tooltip_content' => '', 'hotspot_horizontal' => 'right', 'hotspot_vertical' => 'bottom' ),
	);
	$html = $render( array( 'hotspot' => $rows, 'hotspot_animation' => 'e-hotspot--soft-beat' ) );
	check_hs( str_contains( $html, '<button class="digi-hs__button digi-hs--soft-beat" type="button" aria-expanded="false" aria-controls="digi-hotspot-abc1234-a1"><span class="digi-hs__label">Cocina</span></button>' ) && str_contains( $html, '<p>Isla <strong>central</strong></p></div>' ) && ! str_contains( $html, '<script' ), 'Con texto: el texto es el nombre; la información emergente pasa por wp_kses_post.' );
	check_hs( str_contains( $html, '<a class="digi-hs elementor-repeater-item-a2 digi-hs--pos-left digi-hs--pos-top digi-hs--link digi-hs--no-tip digi-hs--icon" href="https://a.test/x" target="_blank" aria-label="Punto de interés 2"><span class="digi-hs__button digi-hs--soft-beat"><span class="digi-hs__icon"><svg class="e-font-icon-svg" data-icon="fas fa-star" aria-hidden="true"></svg></span></span></a>' ), 'Con enlace y «al pulsar»: el punto es el enlace (como PRO), sin información emergente, con nombre e icono decorativo.' );
	check_hs( str_contains( $html, '<div class="digi-hs elementor-repeater-item-a3 digi-hs--pos-right digi-hs--pos-bottom"><span class="digi-hs__button digi-hs--soft-beat"><span class="digi-hs__icon">' ) && str_contains( $html, '<span class="digi-hs__label">Baño</span></span></div>' ), 'Sin información emergente ni enlace: sólo el dibujo, fuera del orden del foco; orientación derecha y abajo.' );
	$hover = $render( array( 'hotspot' => $rows, 'tooltip_trigger' => 'mouseenter', 'tooltip_trigger_mobile' => 'click', 'tooltip_trigger_tablet' => 'bogus' ) );
	check_hs( str_contains( $hover, 'data-digi-trigger="{&quot;desktop&quot;:&quot;mouseenter&quot;,&quot;mobile&quot;:&quot;click&quot;}"' ), 'Disparador por dispositivo; un valor desconocido no pasa.' );
	check_hs( str_contains( $hover, 'digi-hs--link digi-hs--icon" href="https://a.test/x" target="_blank" aria-label="Punto de interés 2"><span class="digi-hs__button digi-hs--expand">' ) && str_contains( $hover, '<div id="digi-hotspot-abc1234-a2" class="digi-hs__tip digi-hs--tip-pos digi-hs--fade-in-out">Escribe aquí la información emergente</div></a>' ) && ! str_contains( $hover, 'digi-hs--no-tip' ), 'Con enlace y «al pasar»: la información emergente va dentro del enlace, como en PRO.' );
	$none = $render( array( 'hotspot' => array( $rows[0] ), 'tooltip_trigger' => 'none' ) );
	check_hs( str_contains( $none, '<span class="digi-hs__button digi-hs--expand"><span class="digi-hs__label">Cocina</span></span>' ) && str_contains( $none, 'class="digi-hs__tip digi-hs--show-tip digi-hs--tip-pos">' ), '«Siempre visible»: sin botón (no hay nada que abrir) y la información emergente a la vista.' );
	$mixed = $render( array( 'hotspot' => array( $rows[0] ), 'tooltip_trigger' => 'none', 'tooltip_trigger_mobile' => 'click' ) );
	check_hs( str_contains( $mixed, 'type="button" aria-expanded="true"' ) && str_contains( $mixed, 'digi-hs--show-tip' ), '«Siempre visible» en el escritorio y «al pulsar» en el móvil: botón abierto, como la clase de PRO.' );
	$dir = $render( array( 'hotspot' => array( $rows[0], array( '_id' => 'b2', 'hotspot_tooltip_position' => 'yes', 'hotspot_position' => 'bottom' ), array( '_id' => 'b3', 'hotspot_tooltip_position' => 'yes', 'hotspot_position' => 'top' ) ), 'tooltip_animation' => 'e-hotspot--slide-direction', 'tooltip_position' => 'left' ) );
	check_hs( str_contains( $dir, '<div class="digi-hs__mask digi-hs--tip-pos"><div id="digi-hotspot-abc1234-a1" class="digi-hs__tip digi-hs--slide-direction digi-hs--from-left">' ), 'Deslizar desde la dirección: máscara con la posición y la dirección del widget.' );
	check_hs( str_contains( $dir, 'id="digi-hotspot-abc1234-b2" class="digi-hs__tip digi-hs--slide-direction digi-hs--from-bottom"' ) && str_contains( $dir, 'id="digi-hotspot-abc1234-b3" class="digi-hs__tip digi-hs--slide-direction digi-hs--from-left"' ), 'Con «información emergente propia», la dirección que ganaba en la hoja de PRO (abajo gana a izquierda; izquierda gana a arriba).' );
	check_hs( 'top' === Hotspot::from( 'top', '' ) && 'right' === Hotspot::from( 'left', 'right' ) && 'left' === Hotspot::from( 'left', 'top' ) && 'bottom' === Hotspot::from( 'bogus', 'bottom' ), 'Orden de la hoja de PRO: arriba, izquierda, derecha, abajo.' );
	$seq = $render( array( 'hotspot_sequenced_animation' => 'yes', 'hotspot_sequenced_animation_duration' => array( 'size' => 600, 'unit' => 'px' ), 'hotspot_animation' => '' ) );
	check_hs( str_contains( $seq, 'data-digi-sequence="600"' ) && str_contains( $seq, 'digi-hs digi-hs--sequenced elementor-repeater-item-' ) === false && str_contains( $seq, 'digi-hs--sequenced' ) && str_contains( $seq, '<button class="digi-hs__button" type="button"' ), 'Secuencia con su duración, clase en cada punto y sin animación del punto.' );
	check_hs( str_contains( $render( array( 'hotspot_sequenced_animation' => 'yes' ) ), 'data-digi-sequence="1000"' ), 'Secuencia sin duración: 1 000 ms, como PRO.' );
	$img = $render( array( 'image' => array( 'id' => 11, 'url' => 'https://sitio.test/wp-content/uploads/img-11.png' ), 'image_size' => 'medium_large' ) );
	check_hs( str_contains( $img, 'src="https://sitio.test/wp-content/uploads/img-11-medium_large.png" class="attachment-medium_large size-medium_large wp-image-11" loading="lazy" decoding="async" alt="Texto alternativo 11" srcset=' ), 'Imagen del adjunto con wp_get_attachment_image(): tamaño, srcset, alt, carga y decodificación.' );
	check_hs( '' === $render( array( 'image' => array( 'url' => '' ) ) ), 'Sin imagen no se pinta nada, como PRO.' );
	$cap = $render( array( 'digitalisimo_hotspot_caption' => 'Plano <b>de la planta</b> & baja' ) );
	check_hs( str_ends_with( $cap, '</div><p class="digi-media-caption">Plano de la planta &amp; baja</p></div>' ), 'Leyenda bajo la imagen, dentro del contenedor y escapada, como la añadía Media_Text_Extras al Hotspot de PRO.' );
	check_hs( '' === \Digitalisimo\Elements\Media_Text_Extras::kind( $widget() ), 'Media_Text_Extras no reconoce el widget propio: la leyenda no se pinta dos veces.' );
	$evil = $render( array( 'tooltip_trigger' => 'mouseenter', 'hotspot' => array( array( '_id' => '"><x', 'hotspot_label' => '<img src=x onerror=alert(1)>Hola', 'hotspot_link' => array( 'url' => 'javascript:alert(1)' ) ) ) ) );
	check_hs( ! str_contains( $evil, 'onerror' ) && ! str_contains( $evil, 'javascript:' ) && str_contains( $evil, 'id="digi-hotspot-abc1234-x"' ) && str_contains( $evil, 'class="digi-hs elementor-repeater-item-&quot;&gt;&lt;x ' ) && ! str_contains( $evil, '"><x' ), 'Texto, enlace e id escapados.' );
	$GLOBALS['hs_rtl'] = true;
	$rtl = $widget();
	check_hs( 'right' === $rtl->controls['hotspot']['fields']['hotspot_horizontal']['default'] && 'end' === $rtl->controls['align']['selectors_dictionary']['left'], 'RTL: la orientación por defecto y el diccionario de alineación siguen al idioma, como en PRO.' );
	$GLOBALS['hs_rtl'] = false;

	// El fixture de la prueba en Chrome (tests/fixtures/pro-hotspot.html) es este marcado, con la imagen de muestra en línea.
	$fixture = (string) file_get_contents( __DIR__ . '/fixtures/pro-hotspot.html' );
	$frows   = array(
		array( '_id' => 'a1', 'hotspot_tooltip_content' => 'Primer punto' ),
		array( '_id' => 'a2', 'hotspot_label' => 'Cocina', 'hotspot_tooltip_content' => '<p>Isla <a href="#isla">central</a></p>' ),
		array( '_id' => 'a3', 'hotspot_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ), 'hotspot_link' => array( 'url' => 'https://a.test/x' ), 'hotspot_tooltip_content' => 'Enlace con información' ),
	);
	$fcases  = array(
		'click'  => array( 'hotspot' => $frows ),
		'hover'  => array( 'hotspot' => $frows, 'tooltip_trigger' => 'mouseenter', 'hotspot_animation' => 'e-hotspot--soft-beat' ),
		'none'   => array( 'hotspot' => array( $frows[1] ), 'tooltip_trigger' => 'none' ),
		'device' => array( 'hotspot' => array( $frows[1] ), 'tooltip_trigger' => 'mouseenter', 'tooltip_trigger_mobile' => 'click' ),
		'seq'    => array( 'hotspot' => $frows, 'hotspot_sequenced_animation' => 'yes', 'hotspot_sequenced_animation_duration' => array( 'size' => 900, 'unit' => 'px' ), 'tooltip_animation' => 'e-hotspot--slide-direction' ),
	);
	foreach ( $fcases as $id => $settings ) {
		$fw     = $widget( $settings );
		$fw->id = $id;
		ob_start();
		( new ReflectionMethod( $fw, 'render' ) )->invoke( $fw );
		$html = (string) preg_replace( '/<img src="[^"]+"/', '<img src="IMG"', (string) ob_get_clean() );
		check_hs( preg_match( '/data-case="' . $id . '">(.*?)<\/div>\n/s', (string) preg_replace( '/<img src="[^"]+"/', '<img src="IMG"', $fixture ), $m ) && $m[1] === $html, "El fixture de Chrome «{$id}» es el marcado del servidor." );
	}

	// 4. Conversión de Hotspot de PRO con el mapa.
	Registry::reset();
	check_hs( null !== Registry::map( 'hotspot', true ) && 'hide' === $map['panel'] && 'digitalisimo-hotspot' === $map['target'] && 'ready' === $map['status'], 'Mapa del Hotspot listo tras el A/B; oculta el widget de PRO.' );
	$own    = $widget();
	$fields = array_keys( $own->controls['hotspot']['fields'] );
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
	$size  = static function ( $n, $unit = 'px' ) { return array( 'unit' => $unit, 'size' => $n, 'sizes' => array() ); };
	$box   = static function ( $n, $unit = 'px' ) { return array( 'unit' => $unit, 'top' => (string) $n, 'right' => (string) $n, 'bottom' => (string) $n, 'left' => (string) $n, 'isLinked' => true ); };
	$prow  = array(
		array( '_id' => 'p1', 'hotspot_label' => 'Salón', 'hotspot_icon' => array( 'value' => 'fas fa-couch', 'library' => 'fa-solid' ), 'hotspot_icon_position' => 'end', 'hotspot_icon_spacing' => $size( 8 ), 'hotspot_custom_size' => 'yes', 'hotspot_width' => $size( 60 ), 'hotspot_height' => $size( 30 ), 'hotspot_tooltip_content' => '<p>Sofá</p>', 'hotspot_horizontal' => 'right', 'hotspot_offset_x' => $size( 20, '%' ), 'hotspot_offset_x_mobile' => $size( 10, '%' ), 'hotspot_vertical' => 'bottom', 'hotspot_offset_y' => $size( 30, '%' ), 'hotspot_tooltip_position' => 'yes', 'hotspot_position' => 'left', 'hotspot_position_mobile' => 'top', 'hotspot_tooltip_width' => $size( 200 ), 'hotspot_tooltip_text_wrap' => 'yes' ),
		array( '_id' => 'p2', 'hotspot_link' => array( 'url' => 'https://a.test/', 'is_external' => '', 'nofollow' => 'on', 'custom_attributes' => '' ) ),
	);
	$cases = array(
		'defaults' => array(),
		'rows'     => array( 'image' => array( 'id' => 11, 'url' => 'https://sitio.test/wp-content/uploads/img-11.png' ), 'image_size' => 'custom', 'image_custom_dimension' => array( 'width' => 600, 'height' => 400 ), 'hotspot' => $prow, 'hotspot_animation' => 'e-hotspot--overlay', 'hotspot_sequenced_animation' => 'yes', 'hotspot_sequenced_animation_duration' => $size( 2000 ), 'tooltip_position' => 'right', 'tooltip_position_tablet' => 'bottom', 'tooltip_trigger' => 'mouseenter', 'tooltip_trigger_mobile' => 'click', 'tooltip_animation' => 'e-hotspot--fade-direction', 'tooltip_animation_duration' => $size( 300 ) ),
		'styles'   => array( 'align' => 'flex-start', 'align_mobile' => 'center', 'width' => $size( 80, '%' ), 'space' => $size( 900 ), 'height' => $size( 400 ), 'object-fit' => 'cover', 'object-position' => 'top center', 'opacity' => $size( 0.8 ), 'opacity_hover' => $size( 1 ), 'background_hover_transition' => $size( 0.4 ), 'css_filters_css_filter' => 'custom', 'css_filters_blur' => $size( 1 ), 'css_filters_hover_css_filter' => 'custom', 'css_filters_hover_saturate' => $size( 150 ), 'image_border_border' => 'solid', 'image_border_width' => $box( 2 ), 'image_border_color' => '#222222', 'image_border_radius' => $box( 12 ), 'image_box_shadow_box_shadow_type' => 'yes', 'image_box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 4, 'blur' => 10, 'spread' => 0, 'color' => 'rgba(0,0,0,.3)' ), 'style_hotspot_color' => '#ff0055', 'style_hotspot_size' => $size( 24 ), 'style_hotspot_size_mobile' => $size( 14 ), 'style_typography_typography' => 'custom', 'style_typography_font_size' => $size( 15 ), 'style_hotspot_width' => $size( 40 ), 'style_hotspot_height' => $size( 20 ), 'style_hotspot_box_color' => '#ffffff', 'style_hotspot_padding' => $size( 6 ), 'style_hotspot_border_radius' => $box( 20 ), 'style_hotspot_box_shadow_box_shadow_type' => 'yes', 'style_hotspot_box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 2, 'blur' => 6, 'spread' => 0, 'color' => 'rgba(0,0,0,.4)' ), 'style_tooltip_text_color' => '#111111', 'style_tooltip_typography_typography' => 'custom', 'style_tooltip_typography_font_weight' => '600', 'style_tooltip_align' => 'center', 'style_tooltip_width' => $size( 120 ), 'style_tooltip_max_width' => $size( 300 ), 'style_tooltip_padding' => $box( 12 ), 'style_tooltip_color' => '#fafafa', 'style_tooltip_border_radius' => $box( 6 ), 'style_tooltip_box_shadow_box_shadow_type' => 'yes', 'style_tooltip_box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 8, 'blur' => 24, 'spread' => 0, 'color' => 'rgba(0,0,0,.2)' ) ),
		'dynamic'  => array( 'hotspot' => array( array( '_id' => 'd1', '__dynamic__' => array( 'hotspot_label' => '[elementor-tag id="t1" name="post-title" settings="%7B%7D"]', 'hotspot_link' => '[elementor-tag id="t2" name="post-url" settings="%7B%7D"]' ) ) ), '__dynamic__' => array( 'image' => '[elementor-tag id="t3" name="post-featured-image" settings="%7B%7D"]' ) ),
		'globals'  => array( '__globals__' => array( 'style_hotspot_color' => 'globals/colors?id=accent', 'style_hotspot_box_color' => 'globals/colors?id=text', 'style_tooltip_color' => 'globals/colors?id=primary', 'style_typography_typography' => 'globals/typography?id=accent', 'style_tooltip_typography_typography' => 'globals/typography?id=text', '_background_color' => 'globals/colors?id=text' ) ),
		'caption'  => array( 'digitalisimo_hotspot_caption' => 'Planta baja', 'digitalisimo_hotspot_caption_align' => 'center', 'digitalisimo_hotspot_caption_color' => '#333333', 'digitalisimo_hotspot_caption_typography_typography' => 'custom', 'digitalisimo_hotspot_caption_typography_font_size' => $size( 14 ), 'digitalisimo_hotspot_caption_space_mobile' => $size( 6 ) ),
		'devices'  => array( 'width' => $size( 60, '%' ), 'width_tablet' => $size( 80, '%' ), 'width_mobile' => $size( 100, '%' ), 'space_mobile' => $size( 300 ), 'height_tablet' => $size( 'calc(10vh + 20px)', 'custom' ), 'height_mobile' => $size( '' ), 'height' => $size( 400 ) ),
		'vars'     => array( 'custom_css' => "selector{--hotspot-color:red;--hotspot-size:30px;--tooltip-text-color:#000;--tooltip-max-width:200px}\nselector .e-hotspot--tooltip-position{--tooltip-padding:2px;--hotspot-border-radius:0}" ),
		'css'      => array( 'custom_css' => "selector .e-hotspot__button{border-radius:50%}\nselector .e-hotspot--active .e-hotspot__tooltip{color:red}\nselector .e-hotspot__outer-circle,selector .e-hotspot--tooltip-animation-from-top{opacity:.5}\nselector .elementor-widget-container>img{filter:none}\n.elementor-widget-hotspot .e-hotspot--circle{z-index:3}", '_css_classes' => 'mi-plano' ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'hotspot', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_hs( count( $cases ) === count( $log ), 'Se convierte cada Hotspot del árbol.' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_hs( 'digitalisimo-hotspot' === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa a los Puntos de interés con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_hs( $lands( $key ), $element['id'] . ": la clave $key no cae en un control de los Puntos de interés." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_hs( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control." );
			}
		}
		foreach ( (array) ( $element['settings']['hotspot'] ?? array() ) as $row ) {
			foreach ( $row as $key => $value ) {
				check_hs( $lands( $key, true ), $element['id'] . ": la clave hotspot.$key no cae en un campo del repetidor." );
			}
			foreach ( array_keys( $row['__dynamic__'] ?? array() ) as $key ) {
				check_hs( $lands( $key, true ), $element['id'] . ": hotspot.__dynamic__.$key no cae en un campo del repetidor." );
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
	check_hs( array( 'css:pro.css:custom_css', 'vars:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni aproximación; sólo el CSS: ' . implode( ', ', $codes ) );
	// Ancho, ancho máximo y alto de los dispositivos: el contenedor conserva los de escritorio y la imagen recibe los del
	// dispositivo, como PRO.
	$dv = $out['devices'];
	check_hs( 60 === $dv['width']['size'] && 400 === $dv['height']['size'] && ! array_intersect( array( 'width_tablet', 'width_mobile', 'space_mobile', 'height_tablet', 'height_mobile' ), array_keys( $dv ) ), 'El contenedor conserva las medidas de escritorio en todos los tamaños: ' . json_encode( $dv ) );
	check_hs( '80%' === $dv['pro_img_width_tablet'] && '100%' === $dv['pro_img_width_mobile'] && '300px' === $dv['pro_img_space_mobile'] && 'calc(10vh + 20px)' === $dv['pro_img_height_tablet'] && ! isset( $dv['pro_img_height_mobile'], $dv['pro_img_width'], $dv['pro_img_space'], $dv['pro_img_height'] ), 'Las de los dispositivos pasan a la imagen (controles ocultos), sin las vacías ni las de escritorio.' );
	check_hs( "selector{--hs-color:red;--hs-size:30px;--tip-text-color:#000;--tip-max-width:200px}\nselector .digi-hs--tip-pos{--tip-padding:2px;--hs-border-radius:0}" === $out['vars']['custom_css'], 'Las variables CSS de PRO en el CSS personalizado pasan a las propias: ' . $out['vars']['custom_css'] );
	$d = $out['defaults'];
	check_hs( 1 === count( $d['hotspot'] ) && 'Add Your Tooltip Text Here' === $d['hotspot'][0]['hotspot_tooltip_content'] && 'e-hotspot--expand' === $d['hotspot_animation'] && 'click' === $d['tooltip_trigger'] && 'hotspot' === $d['_digitalisimo_pro'], 'Un Hotspot sin cambios llega con su punto de muestra y el texto de PRO.' );
	$painted = $render( $d );
	check_hs( 1 === $count( '<div class="digi-hs ', $painted ) && str_contains( $painted, '>Add Your Tooltip Text Here</div>' ) && str_contains( $painted, 'digi-hs--circle' ), 'Lo convertido por defecto se pinta como PRO.' );
	$r = $out['rows'];
	check_hs( 'custom' === $r['image_size'] && 600 === $r['image_custom_dimension']['width'] && 2 === count( $r['hotspot'] ) && 'p1' === $r['hotspot'][0]['_id'] && 'end' === $r['hotspot'][0]['hotspot_icon_position'] && 10 === $r['hotspot'][0]['hotspot_offset_x_mobile']['size'] && 'top' === $r['hotspot'][0]['hotspot_position_mobile'] && 'yes' === $r['hotspot'][0]['hotspot_tooltip_text_wrap'] && 'on' === $r['hotspot'][1]['hotspot_link']['nofollow'] && 'mouseenter' === $r['tooltip_trigger'] && 'click' === $r['tooltip_trigger_mobile'] && 'bottom' === $r['tooltip_position_tablet'] && 300 === $r['tooltip_animation_duration']['size'], 'Filas con su _id, posición por dispositivo, enlace, disparador y posición por dispositivo.' );
	$painted = $render( $r );
	check_hs( str_contains( $painted, 'data-digi-trigger="{&quot;desktop&quot;:&quot;mouseenter&quot;,&quot;mobile&quot;:&quot;click&quot;}" data-digi-sequence="2000"' ) && str_contains( $painted, 'digi-hs__button digi-hs--overlay' ) && str_contains( $painted, 'class="digi-hs__tip digi-hs--fade-direction digi-hs--from-right"' ) && str_contains( $painted, 'href="https://a.test/" rel="nofollow"' ), 'Lo convertido se pinta: disparadores, secuencia, superposición, dirección y enlace.' );
	$s = $out['styles'];
	foreach ( $cases['styles'] as $key => $value ) {
		check_hs( array_key_exists( $key, $s ) && $s[ $key ] === $value, "Estilo $key: pasa tal cual." );
	}
	foreach ( $cases['caption'] as $key => $value ) {
		check_hs( $out['caption'][ $key ] === $value, "Leyenda $key: pasa tal cual." );
	}
	check_hs( str_contains( $render( $out['caption'] ), '<p class="digi-media-caption">Planta baja</p></div>' ), 'La leyenda convertida se pinta.' );
	$dy = $out['dynamic'];
	check_hs( array( 'image' => '[elementor-tag id="t3" name="post-featured-image" settings="%7B%7D"]' ) === $dy['__dynamic__'] && array( 'hotspot_label', 'hotspot_link' ) === array_keys( $dy['hotspot'][0]['__dynamic__'] ), 'Las etiquetas dinámicas siguen a su control, también en las filas.' );
	check_hs( $cases['globals']['__globals__'] === $out['globals']['__globals__'], 'Colores y tipografías globales del kit siguen a su control.' );
	check_hs( "selector .digi-hs__button{border-radius:50%}\nselector .digi-hs--active .digi-hs__tip{color:red}\nselector .digi-hs__outer,selector .digi-hs--from-top{opacity:.5}\nselector .digi-hotspots>img{filter:none}\n.elementor-widget-digitalisimo-hotspot .digi-hs--circle{z-index:3}" === $out['css']['custom_css'] && 'mi-plano' === $out['css']['_css_classes'], 'CSS personalizado con las clases propias; Avanzado intacto: ' . $out['css']['custom_css'] );
	check_hs( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	echo 'DIGITALÍSIMO Elements: Puntos de interés (' . count( $w->controls ) . ' controles, ' . count( $own_rows ) . ' campos del repetidor, ' . count( $w->groups ) . ' grupos, iguales a los del Hotspot de PRO cargado; hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B) y su mapa (' . count( $cases ) . " casos de conversión) validados.\n";
}
