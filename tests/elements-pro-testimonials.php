<?php
/**
 * Carrusel de testimonios (`digitalisimo-testimonial-carousel`) y Reseñas (`digitalisimo-reviews`), los widgets propios
 * que sustituyen al Testimonial Carousel y a Reviews de PRO (C1 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato
 * docs/pro/c1.md §2 «T»), y sus mapas `modules/digitalisimo-convert/maps/testimonial-carousel.php` y `reviews.php`:
 * - recursos: el motor y su hoja (≤ 4 KB, sin script propio, sin `--e-global-*`, `url()` ni clases del proveedor,
 *   sólo los colores por defecto de PRO), sin nombres de terceros vetados por el empaquetado;
 * - un control propio por cada control de PRO (los suyos y los de la base, menos los que PRO quita), con el mismo
 *   nombre, valor por defecto, opciones y condiciones; la cobertura de cada mapa (también lo heredado de la base);
 * - marcado del servidor: pieles, disposiciones (dónde va el nombre), alineación por variables, estrellas (llenas,
 *   parciales a la décima, vacías, contorno, Unicode, nombre accesible), icono social con su color oficial y su enlace,
 *   escape, y los argumentos del motor (paginación que reserva espacio, flechas que estrechan la pista);
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, pieles, disposiciones, responsivos, globales,
 *   dinámicos en el widget y en las filas, CSS personalizado con clases de PRO, FA4 heredado): ningún `pro.unmapped`,
 *   cada clave cae en un control propio y lo convertido se pinta con los mismos textos.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		private $attrs   = array();
		private $section = '';
		public function get_settings_for_display() { return $this->settings; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name ) {}
		public function start_controls_tab( $name, $args ) {}
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function start_injection( $args ) {}
		public function end_injection() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'section' => $this->section ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type, 'section' => $this->section ) + $args; }
		public function update_control( $name, $args ) { $this->controls[ $name ] = array_merge( $this->controls[ $name ], $args ) + array( 'updated' => true ); }
		public function update_responsive_control( $name, $args ) { $this->controls[ $name ] = array_merge( $this->controls[ $name ], $args ) + array( 'updated_responsive' => true ); }
		public function remove_control( $name ) { unset( $this->controls[ $name ] ); }
		public function remove_responsive_control( $name ) { unset( $this->controls[ $name ] ); }
		public function add_render_attribute( $element, $key = null, $value = null ) { $this->attrs[ $element ][ $key ][] = $value; }
		public function get_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $key => $values ) {
				$out[] = $key . '="' . htmlspecialchars( implode( ' ', $values ), ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $out );
		}
		public function add_link_attributes( $element, array $url ) {
			$this->add_render_attribute( $element, 'href', $url['url'] );
			if ( ! empty( $url['is_external'] ) ) {
				$this->add_render_attribute( $element, 'target', '_blank' );
			}
			if ( ! empty( $url['nofollow'] ) ) {
				$this->add_render_attribute( $element, 'rel', 'nofollow' );
			}
		}
	}
	class Repeater {
		public $fields = array();
		public function add_control( $name, $args ) { $this->fields[ $name ] = $args; }
		public function get_controls() { return $this->fields; }
	}
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const NUMBER = 'number';
		const MEDIA = 'media'; const URL = 'url'; const ICONS = 'icons'; const REPEATER = 'repeater'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Image_Size {
		public static function get_type() { return 'image-size'; }
		public static function get_attachment_image_src( $id, $name, $settings ) { return 'https://sitio.test/wp-content/uploads/a-' . (int) $id . '-custom.jpg'; }
	}
	class Utils { public static function get_placeholder_image_src() { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png'; } }
	class Icons_Manager {
		public static function render_icon( $icon, $attributes = array() ) {
			if ( 'svg' === ( $icon['library'] ?? '' ) ) {
				echo '<svg class="e-font-icon-svg" aria-hidden="true" viewBox="0 0 1 1"></svg>';
				return;
			}
			echo '<i aria-hidden="true" class="' . htmlspecialchars( (string) ( $icon['value'] ?? '' ), ENT_QUOTES, 'UTF-8' ) . '"></i>';
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	function __( $v, $d = '' ) { return $v; }
	function esc_html__( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $v, $d = '' ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^https?://~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, ',', '.' ); }
	function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
		$attr += array( 'loading' => 'lazy' );
		$out   = '<img width="150" height="150" src="https://sitio.test/wp-content/uploads/a-' . (int) $id . '-' . $size . '.jpg" srcset="https://sitio.test/wp-content/uploads/a-' . (int) $id . '-300.jpg 300w"';
		foreach ( $attr as $k => $v ) {
			$out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"';
		}
		return $out . ' />';
	}
	function check_t( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$root = __DIR__ . '/../digitalisimo-elements/';
	$mod  = $root . 'modules/digitalisimo-carousel/';
	require $mod . 'class-module.php';
	\Digitalisimo\Elements\Carousel\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Carousel\Engine;
	use Digitalisimo\Elements\Carousel\Module;
	use Digitalisimo\Elements\Carousel\Widgets\Reviews;
	use Digitalisimo\Elements\Carousel\Widgets\Testimonial_Carousel;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$widgets = array(
		'testimonial-carousel' => array( 'class' => Testimonial_Carousel::class, 'name' => 'digitalisimo-testimonial-carousel', 'title' => 'Carrusel de testimonios', 'css' => 'testimonial-carousel.css', 'file' => 'class-testimonial-carousel.php' ),
		'reviews'              => array( 'class' => Reviews::class, 'name' => 'digitalisimo-reviews', 'title' => 'Reseñas', 'css' => 'reviews.css', 'file' => 'class-reviews.php' ),
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
		$defaults['image_size_size'] = 'full';
		$widget->settings = array_merge( $defaults, $settings );
		return $widget;
	};
	$render = static function ( $class, array $settings ) use ( $make ) {
		$widget = $make( $class, $settings );
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	// Lo que el empaquetado veta en DIGITALÍSIMO Elements (scripts/build-packages.sh).
	$vetoed = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';

	// 1. Registro y recursos.
	$colors_ok = array(
		'testimonial-carousel' => array( '#f9fafa' ),
		'reviews'              => array( '#fff', '#e1e8ed', '#1c2022', '#697882', '#ccd6df', '#f0ad4e' ),
	);
	foreach ( $widgets as $pro => $w ) {
		$widget = new $w['class']();
		check_t( $w['name'] === $widget->get_name() && $w['title'] === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories(), "$pro: nombre, título y categoría propios." );
		check_t( isset( Module::WIDGETS[ $w['name'] ] ) && 'modules/digitalisimo-carousel/widgets/' . $w['file'] === 'modules/digitalisimo-carousel/' . Module::WIDGETS[ $w['name'] ]['file'] && $w['title'] === Module::TITLES[ $w['name'] ], "$pro: en Module::WIDGETS con su archivo y título." );
		check_t( array( Engine::HANDLE, $w['name'] ) === $widget->get_style_depends() && array( Engine::HANDLE ) === $widget->get_script_depends(), "$pro: la hoja del motor y la suya; sólo el script del motor." );
		check_t( ! is_file( $mod . 'assets/js/' . substr( $w['name'], 13 ) . '.js' ), "$pro: sin script propio." );
		$css = (string) file_get_contents( $mod . 'assets/css/' . $w['css'] );
		check_t( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$pro: la hoja pesa " . strlen( $css ) . ' B (tope 4 096).' );
		check_t( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( str_replace( '.elementor-widget-' . $w['name'] . '{', '', $css ), 'elementor' ) && ! str_contains( $css, 'swiper' ), "$pro: la hoja no usa el kit, url() ni clases del proveedor." );
		check_t( (bool) preg_match( '/^\.elementor-widget-' . preg_quote( $w['name'], '/' ) . '\{--digi-crs-view:1;--digi-crs-gap:10px;[^}]*--digi-crs-pag-space:40px\}/', $css ), "$pro: la hoja declara en el envoltorio las variables de maquetación del motor (un carrusel anidado no hereda las de fuera), y 40 px para la paginación, como PRO." );
		check_t( ! preg_match( '/--digi-crs-(prev|next)-(px|cs)|--digi-crs-pag-oy|dot-hover-opacity/', $css ), "$pro: sin variables que el motor ya no lee." );
		preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $colors );
		check_t( array() === array_diff( array_unique( array_map( 'strtolower', $colors[0] ) ), $colors_ok[ $pro ] ), "$pro: sólo los colores que PRO usaba por defecto: " . implode( ', ', $colors[0] ) );
		$source = '';
		foreach ( token_get_all( (string) file_get_contents( $mod . 'widgets/' . $w['file'] ) ) as $token ) {
			$source .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
		}
		check_t( ! preg_match( '/jquery|swiper|wp_enqueue_script|wp_register_script|elementor-pro-frontend|pro-elements-handlers/i', $source ), "$pro: sin jQuery, Swiper ni scripts." );
		foreach ( array( $mod . 'widgets/' . $w['file'], $mod . 'assets/css/' . $w['css'], $root . 'modules/digitalisimo-convert/maps/' . $pro . '.php' ) as $file ) {
			check_t( ! preg_match( $vetoed, (string) file_get_contents( $file ) ), basename( $file ) . ': nombra algo que el empaquetado veta.' );
		}
	}
	$rcss = (string) file_get_contents( $mod . 'assets/css/reviews.css' );
	check_t( str_contains( $rcss, '.digi-review__header:focus-visible{outline:2px solid;outline-offset:-4px}' ), 'Reseñas: foco visible del enlace de la cabecera, dentro de la diapositiva (que recorta lo que sobresale).' );
	$tcss = (string) file_get_contents( $mod . 'assets/css/testimonial-carousel.css' );
	foreach ( array( 'skin-bubble', 'layout-image_inline', 'layout-image_stacked', 'layout-image_above', 'layout-image_left', 'layout-image_right' ) as $class ) {
		check_t( str_contains( $tcss, '.digi-testimonial--' . $class ), "La hoja de testimonios tiene la presentación $class." );
	}

	// 2. Un control propio por cada control de PRO (los suyos y los de la base, menos los que PRO quita).
	$base_php = (string) file_get_contents( $root . 'modules/carousel/widgets/base.php' );
	preg_match_all( "/->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $base_php, $base_controls );
	preg_match_all( "/->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'/s", $base_php, $base_groups );
	$built = array();
	foreach ( $widgets as $pro => $w ) {
		$php = (string) file_get_contents( $root . 'modules/carousel/widgets/' . $pro . '.php' );
		preg_match_all( "/->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $php, $own_controls );
		preg_match_all( "/->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'/s", $php, $own_groups );
		preg_match_all( "/remove_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $php, $removed );
		preg_match_all( "/\\\$repeater->add_control\(\s*'([a-z0-9_]+)'/", $php, $fields );
		$widget = $make( $w['class'] );
		$built[ $pro ] = $widget;
		$fields_own = array_keys( $widget->controls['slides']['fields'] );
		check_t( $fields[1] === $fields_own, "$pro: el repetidor tiene los mismos campos, en el mismo orden: " . implode( ', ', $fields_own ) );
		$expected = array_diff( array_unique( array_merge( $base_controls[1], array_diff( $own_controls[1], $fields[1] ) ) ), $removed[1] );
		$mine     = array_keys( $widget->controls );
		sort( $expected );
		sort( $mine );
		check_t( $expected === $mine, "$pro: los mismos controles que PRO. Faltan: " . implode( ', ', array_diff( $expected, $mine ) ) . '; sobran: ' . implode( ', ', array_diff( $mine, $expected ) ) );
		$groups_expected = array_unique( array_merge( $base_groups[1], $own_groups[1] ) );
		$groups_mine     = array_keys( $widget->groups );
		sort( $groups_expected );
		sort( $groups_mine );
		check_t( $groups_expected === $groups_mine, "$pro: los mismos grupos que PRO: " . implode( ', ', $groups_mine ) );
		foreach ( array( 'effect', 'height', 'pagination_position' ) as $gone ) {
			check_t( ! isset( $widget->controls[ $gone ] ), "$pro: $gone se quita como en PRO." );
		}
		check_t( null === $widget->controls['slides_per_view']['condition'] && null === $widget->controls['slides_to_scroll']['condition'] && '--digi-t-w: {{SIZE}}{{UNIT}}; --digi-t-wa: calc({{SIZE}}{{UNIT}} - 40px);' === $widget->controls['width']['selectors']['{{WRAPPER}}'], "$pro: diapositivas visibles y por paso sin condición; el ancho elegido pasa por --digi-t-w y, con flechas, --digi-t-wa (40 px menos)." );
		// Mismo valor por defecto y mismas opciones que PRO en cada control propio que los declara.
		$seen = array( 'default' => 0, 'condition' => 0, 'options' => 0, 'global' => 0 );
		foreach ( $widget->controls as $name => $control ) {
			if ( ! preg_match( "/->add_(?:responsive_)?control\(\s*'" . $name . "',\s*\[(.*?)\n\t\t\t\]\s*\);/s", $php, $block ) ) {
				continue;
			}
			if ( preg_match( "/'default' => '([^']*)'/", $block[1], $d ) ) {
				check_t( ( $control['default'] ?? null ) === $d[1], "$pro.$name: valor por defecto " . var_export( $control['default'] ?? null, true ) . " (PRO: {$d[1]})." );
				++$seen['default'];
			}
			if ( preg_match( "/'condition' => \[\s*'([a-z_!]+)' => '([^']*)',\s*\]/", $block[1], $c ) ) {
				check_t( array( $c[1] => $c[2] ) === ( $control['condition'] ?? null ), "$pro.$name: misma condición que PRO." );
				++$seen['condition'];
			}
			if ( preg_match( "/'options' => \[(.*?)\n\t\t\t\t\],/s", $block[1], $o ) ) {
				preg_match_all( "/^\t\t\t\t\t'([a-z_]*)' =>/m", $o[1], $keys );
				check_t( $keys[1] === array_map( 'strval', array_keys( $control['options'] ) ), "$pro.$name: mismas opciones que PRO." );
				++$seen['options'];
			}
			if ( preg_match( "/'global' => \[\s*'default' => Global_(Colors|Typography)::(\w+),/", $block[1], $g ) ) {
				$expected_global = ( 'Colors' === $g[1] ? 'globals/colors?id=' : 'globals/typography?id=' ) . strtolower( preg_replace( '/^(COLOR|TYPOGRAPHY)_/', '', $g[2] ) );
				check_t( $expected_global === ( $control['global']['default'] ?? null ), "$pro.$name: el mismo global por defecto que PRO ($expected_global)." );
				++$seen['global'];
			}
		}
		// La lectura del archivo de PRO tiene que haber encontrado algo que comparar.
		$least = 'reviews' === $pro ? array( 'default' => 4, 'condition' => 3, 'options' => 3, 'global' => 0 ) : array( 'default' => 5, 'condition' => 4, 'options' => 3, 'global' => 3 );
		foreach ( $least as $kind => $min ) {
			check_t( $seen[ $kind ] >= $min, "$pro: sólo se compararon {$seen[ $kind ]} $kind con PRO (mínimo $min)." );
		}
		foreach ( $widget->groups as $name => $group ) {
			if ( preg_match( "/'name' => '" . $name . "',[^;]*?'global' => \[\s*'default' => Global_Typography::TYPOGRAPHY_(\w+),/s", $php, $g ) ) {
				check_t( 'globals/typography?id=' . strtolower( $g[1] ) === ( $group['global']['default'] ?? null ), "$pro.$name: la misma tipografía global que PRO." );
			}
		}
	}
	$t = $built['testimonial-carousel'];
	check_t( array( 'skin' => 'bubble' ) === $t->sections['section_skin_style']['condition'], 'Testimonios: la sección Burbuja sólo con esa piel.' );
	check_t( 'digi-testimonial--skin-' === $t->controls['skin']['prefix_class'] && 'digi-testimonial--layout-' === $t->controls['layout']['prefix_class'] && 'digi-testimonial-%s-align-' === $t->controls['alignment']['prefix_class'] && ! empty( $t->controls['alignment']['responsive'] ), 'Testimonios: clases de piel, disposición y alineación (con su dispositivo) en el envoltorio.' );
	check_t( array( 'top' => '20', 'bottom' => '20', 'left' => '20', 'right' => '20', 'unit' => 'px' ) === $t->controls['text_padding']['default'] && '#000' === $t->controls['border_color']['default'] && '#000' === $t->controls['image_border_color']['default'] && false === $t->controls['background_color']['alpha'], 'Testimonios: relleno, colores del borde y fondo sin transparencia como en PRO.' );
	foreach ( $t->controls['alignment']['selectors_dictionary'] as $value => $rules ) {
		foreach ( array( '--digi-t-ta:', '--digi-t-fd:', '--digi-t-jc:', '--digi-t-l:', '--digi-t-r:', '--digi-t-x:' ) as $rule ) {
			check_t( str_contains( $rules, $rule ), "Alineación $value: falta $rule (cada dispositivo fija todas)." );
		}
		// Como PRO: sólo «centro» alinea al inicio el nombre junto a la imagen, y lo hereda un dispositivo menor.
		check_t( ( 'center' === $value ) === str_contains( $rules, '--digi-t-cta:start' ) && ( 'center' === $value || ! str_contains( $rules, '--digi-t-cta' ) ), "Alineación $value: --digi-t-cta sólo con «centro»." );
	}
	foreach ( array( '--digi-t-ta', '--digi-t-fd', '--digi-t-jc', '--digi-t-cta', '--digi-t-l', '--digi-t-r', '--digi-t-x', '--digi-t-at', '--digi-t-gap', '--digi-t-w' ) as $var ) {
		check_t( str_contains( $tcss, 'var(' . $var ), "La hoja de testimonios lee $var." );
	}
	check_t( str_contains( $t->controls['image_size']['selectors']['{{WRAPPER}}'], '{{text_padding.LEFT}}{{text_padding.UNIT}}' ), 'Testimonios: el tamaño de la imagen mueve el pico de la burbuja con el relleno, como PRO.' );
	$r = $built['reviews'];
	check_t( 'has-separator' === $r->controls['show_separator']['default'] && 'has-separator' === $r->controls['show_separator']['return_value'] && 'digi-review--' === $r->controls['show_separator']['prefix_class'], 'Reseñas: separador por defecto, con su clase en el envoltorio.' );
	check_t( array( 'show_separator!' => '' ) === $r->controls['separator_color']['condition'] && array( 'show_separator!' => '' ) === $r->controls['separator_size']['condition'] && array( 'icon_color' => 'custom' ) === $r->controls['icon_custom_color']['condition'], 'Reseñas: las mismas condiciones que PRO.' );
	check_t( 'digi-review--star-style-' === $r->controls['star_style']['prefix_class'] && 'template' === $r->controls['star_style']['render_type'] && empty( $r->controls['image_size']['responsive'] ) && ! empty( $r->controls['image_gap']['responsive'] ), 'Reseñas: estilo de estrella con su clase; tamaño de imagen no responsivo y separación responsiva, como PRO.' );
	check_t( ! empty( $r->controls['slides_to_scroll']['responsive'] ) && empty( $r->controls['slides_to_scroll']['updated_responsive'] ) && ! empty( $r->controls['slides_to_scroll']['updated'] ), 'Reseñas: como PRO, sólo el valor de escritorio de «Diapositivas por paso» deja de depender del efecto.' );
	$rf = $r->controls['slides']['fields'];
	check_t( 0 === $rf['rating']['min'] && 5 === $rf['rating']['max'] && 0.1 === $rf['rating']['step'] && 'social_icon' === $rf['selected_social_icon']['fa4compatibility'] && array( 'value' => 'fab fa-twitter', 'library' => 'fa-brands' ) === $rf['selected_social_icon']['default'] && 'url' === $rf['link']['type'], 'Reseñas: valoración de 0 a 5 por décimas, icono con compatibilidad FA4 y enlace por fila.' );
	check_t( '' === $r->controls['slide_padding']['selectors']['{{WRAPPER}} .digi-review__header'] ? false : str_contains( $r->controls['slide_padding']['selectors']['{{WRAPPER}} .digi-review__content'], 'padding-bottom: {{BOTTOM}}{{UNIT}}' ), 'Reseñas: el relleno de la diapositiva se reparte entre cabecera y texto, como PRO.' );

	// Cobertura de los mapas (también lo heredado de la base): cada control de PRO está declarado.
	foreach ( $widgets as $pro => $w ) {
		$map = require $root . 'modules/digitalisimo-convert/maps/' . $pro . '.php';
		check_t( $pro === $map['source'] && $w['name'] === $map['target'] && 'ready' === $map['status'] && 'hide' === $map['panel'], "$pro: mapa listo que oculta el widget de PRO." );
		$php = (string) file_get_contents( $root . 'modules/carousel/widgets/' . $pro . '.php' ) . $base_php;
		preg_match_all( "/->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $php, $controls );
		preg_match_all( "/->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'/s", $php, $grouped );
		$declared = array_merge( (array) $map['keep'], array_keys( (array) $map['groups'] ), array_keys( (array) $map['drop'] ), array_keys( (array) $map['approx'] ), array_keys( (array) $map['repeaters'] ), (array) $map['ui'], (array) $map['repeaters']['slides']['keep'] );
		$missing  = array_diff( array_unique( array_merge( $controls[1], $grouped[1] ) ), $declared );
		check_t( ! $missing, "$pro: el mapa no declara " . implode( ', ', $missing ) );
		foreach ( (array) $map['keep'] as $key ) {
			check_t( isset( $built[ $pro ]->controls[ $key ] ), "$pro: keep $key no es un control propio." );
		}
		foreach ( (array) $map['groups'] as $from => $to ) {
			check_t( isset( $built[ $pro ]->groups[ $to ] ), "$pro: el grupo $to no existe en el widget propio." );
		}
		foreach ( (array) $map['repeaters']['slides']['keep'] as $key ) {
			check_t( isset( $built[ $pro ]->controls['slides']['fields'][ $key ] ) || ( 'social_icon' === $key && 'reviews' === $pro ), "$pro: el campo $key no existe en el repetidor propio." );
		}
		foreach ( (array) $map['ui'] as $key ) {
			check_t( 'heading' === ( $built[ $pro ]->controls[ $key ]['type'] ?? '' ), "$pro: $key es un encabezado del panel." );
		}
	}

	// 3. Marcado del servidor.
	$row = static function ( $id, array $extra = array() ) {
		return $extra + array( '_id' => $id, 'content' => 'Muy <em>bueno</em>', 'name' => 'Ana', 'title' => 'Directora', 'image' => array( 'url' => 'https://sitio.test/a.jpg', 'id' => '' ) );
	};
	$tset = array( 'autoplay' => '', 'slides' => array( $row( 'a1' ), $row( 'a2' ) ) );
	$html = $render( Testimonial_Carousel::class, $tset );
	check_t( str_starts_with( $html, '<section class="digi-crs digi-crs--pag-out digi-testimonials digi-testimonials--arrows" aria-roledescription="carrusel" aria-label="Diapositivas" data-digi-loop="1" data-digi-effect="slide" data-digi-pagination="bullets" style="--digi-crs-speed:500ms;--digi-crs-n:2">' ), 'Raíz del motor con la reserva de la paginación y las flechas que estrechan la pista: ' . substr( $html, 0, 300 ) );
	check_t( ! str_contains( $html, 'data-digi-autoplay' ) && ! str_contains( $html, 'digi-crs__pause' ) && 2 === substr_count( $html, 'class="digi-crs__dot"' ) && str_contains( $html, 'digi-crs__prev"' ), 'Sin reproducción automática no hay pausa; dos puntos y flechas.' );
	check_t( str_contains( $html, '<div class="digi-crs__slide" role="group" aria-roledescription="diapositiva" aria-label="1 de 2"><div class="digi-testimonial elementor-repeater-item-a1"><div class="digi-testimonial__content"><div class="digi-testimonial__text">Muy <em>bueno</em></div></div><div class="digi-testimonial__footer"><div class="digi-testimonial__image"><img src="https://sitio.test/a.jpg" alt="Ana" decoding="async"></div><cite class="digi-testimonial__cite"><span class="digi-testimonial__name">Ana</span><span class="digi-testimonial__title">Directora</span></cite></div></div></div>' ), 'Imagen en línea: texto en el contenido; imagen, nombre y cargo en el pie: ' . $html );
	foreach ( array( 'image_above', 'image_left', 'image_right' ) as $layout ) {
		$out = $render( Testimonial_Carousel::class, array( 'layout' => $layout ) + $tset );
		check_t( str_contains( $out, '<div class="digi-testimonial__text">Muy <em>bueno</em></div><cite class="digi-testimonial__cite">' ) && str_contains( $out, '<div class="digi-testimonial__footer"><div class="digi-testimonial__image"><img src="https://sitio.test/a.jpg" alt="Ana" decoding="async"></div></div>' ), "Disposición $layout con la piel predeterminada: el nombre va con el texto (como PRO)." );
		$bubble = $render( Testimonial_Carousel::class, array( 'layout' => $layout, 'skin' => 'bubble' ) + $tset );
		check_t( str_contains( $bubble, '</div></div><div class="digi-testimonial__footer"><div class="digi-testimonial__image"><img src="https://sitio.test/a.jpg" alt="Ana" decoding="async"></div><cite class="digi-testimonial__cite">' ), "Disposición $layout con la burbuja: el nombre va siempre junto a la imagen (como PRO)." );
	}
	$stacked = $render( Testimonial_Carousel::class, array( 'layout' => 'image_stacked' ) + $tset );
	check_t( str_contains( $stacked, '<img src="https://sitio.test/a.jpg" alt="Ana" decoding="async"></div><cite class="digi-testimonial__cite">' ), 'Imagen apilada: nombre junto a la imagen.' );
	$plain = $render( Testimonial_Carousel::class, array( 'show_arrows' => '', 'pagination' => 'progressbar', 'lazyload' => 'yes', 'slides' => array( $row( 'b1', array( 'content' => '', 'image' => array( 'url' => '', 'id' => '' ) ) ), $row( 'b2', array( 'name' => '', 'title' => '', 'image' => array( 'url' => 'https://sitio.test/b.jpg', 'id' => '7', 'alt' => 'Retrato' ) ) ) ) ) + $tset );
	check_t( str_contains( $plain, '<section class="digi-crs digi-testimonials" ' ) && str_contains( $plain, 'data-digi-pagination="progressbar"' ) && ! str_contains( $plain, 'digi-crs__prev' ), 'Sin flechas la pista no se estrecha; la barra de progreso no reserva espacio debajo.' );
	check_t( str_contains( $plain, '<div class="digi-testimonial elementor-repeater-item-b1"><div class="digi-testimonial__footer"><cite class="digi-testimonial__cite">' ) && str_contains( $plain, '<div class="digi-testimonial elementor-repeater-item-b2"><div class="digi-testimonial__content"><div class="digi-testimonial__text">Muy <em>bueno</em></div></div><div class="digi-testimonial__footer"><div class="digi-testimonial__image"><img width="150" height="150" src="https://sitio.test/wp-content/uploads/a-7-full.jpg" srcset="https://sitio.test/wp-content/uploads/a-7-300.jpg 300w" alt="Retrato" decoding="async" loading="lazy" /></div></div></div>' ), 'Sin texto no hay contenido; sin nombre ni cargo no hay cite; la imagen del adjunto con srcset, su texto alternativo y carga diferida: ' . $plain );
	$evil = $render( Testimonial_Carousel::class, array( 'slides' => array( $row( 'x"><script>', array( 'content' => 'Hola<script>alert(1)</script><b onclick="x()">!</b>', 'name' => '<img src=x onerror=alert(1)>Ana', 'image' => array( 'url' => 'javascript:alert(1)', 'id' => '' ) ) ) ) ) + $tset );
	check_t( ! str_contains( $evil, '<script' ) && ! str_contains( $evil, 'onclick' ) && ! str_contains( $evil, 'onerror' ) && ! str_contains( $evil, 'javascript:' ) && ! str_contains( $evil, 'elementor-repeater-item-x' ) && str_contains( $evil, '<b>!</b>' ), 'Testimonios: texto y nombre con el HTML permitido, sin URL peligrosas ni identificadores de fila inválidos.' );
	check_t( ! str_contains( $render( Testimonial_Carousel::class, array( 'slides' => array( $row( 'c1' ) ) ) + $tset ), 'digi-crs__prev' ), 'Con una sola diapositiva no hay flechas ni paginación (como PRO).' );
	$markdown = $make( Testimonial_Carousel::class, $tset );
	check_t( "> Muy bueno\n— Ana\n— Directora\n\n> Muy bueno\n— Ana\n— Directora" === $markdown->render_markdown(), 'Markdown de los testimonios.' );

	// Reseñas: estrellas, icono social y enlace.
	check_t( '' === Reviews::stars( '' ) && '' === Reviews::stars( 0 ) && '' === Reviews::stars( 'x' ), 'Sin valoración (o 0) no hay estrellas, como PRO.' );
	$stars = Reviews::stars( 4.5 );
	check_t( str_starts_with( $stars, '<div class="digi-review__stars" role="img" aria-label="Valoración: 4,5 de 5">' ) && 4 === substr_count( $stars, 'digi-review__star--full' ) && 1 === substr_count( $stars, 'class="digi-review__star digi-review__star--5"' ) && str_contains( $stars, '<span class="digi-review__fill" style="width:50%">' ) && 0 === substr_count( $stars, '--empty' ), '4,5: cuatro llenas y media, con nombre accesible: ' . $stars );
	check_t( 5 === substr_count( Reviews::stars( 7 ), '--full' ) && str_contains( Reviews::stars( 7 ), 'aria-label="Valoración: 5 de 5"' ), 'Más de 5 cuenta como 5.' );
	$s43 = Reviews::stars( '4.3' );
	check_t( str_contains( $s43, 'digi-review__star--3' ) && str_contains( $s43, 'style="width:30%"' ) && 4 === substr_count( $s43, '--full' ), '4,3: la quinta estrella se recorta al 30 % (PRO la pintaba entera).' );
	$s2 = Reviews::stars( 2, false, true );
	check_t( 3 === substr_count( $s2, '--empty' ) && 5 === substr_count( $s2, Reviews::STAR_OUTLINE ) && 2 === substr_count( $s2, '<span class="digi-review__fill">' . Reviews::STAR . '</span>' ), 'Contorno: la base de cada estrella es la estrella vacía y las marcadas llevan la llena encima.' );
	$su = Reviews::stars( 3.5, true, true );
	check_t( ! str_contains( $su, '<svg' ) && 5 === substr_count( $su, '&#9734;' ) && 4 === substr_count( $su, '&#9733;' ), 'Unicode: ☆ de base y ★ en lo marcado, sin SVG.' );
	check_t( ! str_contains( Reviews::STAR . Reviews::STAR_OUTLINE, 'eicon' ) && str_contains( Reviews::STAR, 'aria-hidden="true"' ), 'Estrellas propias, decorativas.' );
	$rset = array(
		'autoplay' => '',
		'slides'   => array(
			array( '_id' => 'r1', 'image' => array( 'url' => 'https://sitio.test/a.jpg', 'id' => '' ), 'name' => 'Ana', 'title' => '@ana', 'rating' => '4.5', 'selected_social_icon' => array( 'value' => 'fab fa-twitter', 'library' => 'fa-brands' ), 'link' => array( 'url' => 'https://ejemplo.test/r', 'is_external' => 'on', 'nofollow' => '' ), 'content' => 'Excelente' ),
			array( '_id' => 'r2', 'image' => array( 'url' => '', 'id' => '' ), 'name' => 'Luis', 'title' => '', 'rating' => '', 'selected_social_icon' => array( 'value' => array( 'url' => 'https://sitio.test/i.svg', 'id' => 3 ), 'library' => 'svg' ), 'link' => array( 'url' => '' ), 'content' => '' ),
			array( '_id' => 'r3', 'image' => array( 'url' => '', 'id' => '' ), 'name' => '', 'title' => '', 'social_icon' => 'fa fa-yelp', 'selected_social_icon' => array( 'value' => '', 'library' => '' ), 'content' => 'Sólo texto' ),
		),
	);
	$rv = $render( Reviews::class, $rset );
	check_t( str_contains( $rv, '<section class="digi-crs digi-crs--pag-out digi-reviews digi-reviews--arrows"' ), 'Reseñas: raíz propia con las mismas reglas del motor que los testimonios.' );
	check_t( str_contains( $rv, '<div class="digi-review elementor-repeater-item-r1"><a class="digi-review__header" href="https://ejemplo.test/r" target="_blank"><div class="digi-review__image"><img src="https://sitio.test/a.jpg" alt="Ana" decoding="async"></div><cite class="digi-review__cite"><span class="digi-review__name">Ana</span><div class="digi-review__stars" role="img" aria-label="Valoración: 4,5 de 5">' ), 'Cabecera enlazada con imagen, nombre y valoración: ' . $rv );
	check_t( str_contains( $rv, '<span class="digi-review__title">@ana</span></cite><div class="digi-review__icon digi-review__icon--twitter" style="--digi-review-icon:#1da1f2" aria-hidden="true"><i aria-hidden="true" class="fab fa-twitter"></i></div></a><div class="digi-review__content"><div class="digi-review__text">Excelente</div></div></div>' ), 'Icono social decorativo con su color oficial; el texto debajo de la cabecera.' );
	check_t( str_contains( $rv, '<div class="digi-review elementor-repeater-item-r2"><div class="digi-review__header"><cite class="digi-review__cite"><span class="digi-review__name">Luis</span></cite><div class="digi-review__icon" aria-hidden="true"><svg class="e-font-icon-svg"' ) && ! str_contains( $rv, 'elementor-repeater-item-r2"><div class="digi-review__header"><cite class="digi-review__cite"><span class="digi-review__name">Luis</span><div class="digi-review__stars' ), 'Sin enlace la cabecera es un div; un icono SVG no tiene color oficial; sin valoración no hay estrellas.' );
	check_t( str_contains( $rv, '<div class="digi-review elementor-repeater-item-r3"><div class="digi-review__content"><div class="digi-review__text">Sólo texto</div></div></div>' ), 'Sin imagen, nombre ni cargo no hay cabecera (como PRO), aunque haya icono.' );
	check_t( 'yelp' === Reviews::social_name( array( 'social_icon' => 'fa fa-yelp' ) ) && 'x-twitter' === Reviews::social_name( array( 'selected_social_icon' => array( 'value' => 'fab fa-x-twitter', 'library' => 'fa-brands' ) ) ) && 'twitter' === Reviews::social_name( array( 'social_icon' => 'fa fa-yelp', '__fa4_migrated' => array( 'selected_social_icon' => true ), 'selected_social_icon' => array( 'value' => 'fab fa-twitter', 'library' => 'fa-brands' ) ) ), 'El nombre del icono se deduce como en PRO (FA4 heredado, FA5 y migrado).' );
	$legacy = $render( Reviews::class, array( 'slides' => array( array( '_id' => 'l1', 'image' => array( 'url' => '' ), 'name' => 'Eva', 'title' => '', 'social_icon' => 'fa fa-yelp', 'content' => '' ) ) ) + $rset );
	check_t( str_contains( $legacy, '<div class="digi-review__icon digi-review__icon--yelp" style="--digi-review-icon:#af0606" aria-hidden="true"><i class="fa fa-yelp" aria-hidden="true"></i></div>' ), 'Un icono FA4 heredado se pinta como en PRO, con su color.' );
	$runi = $render( Reviews::class, array( 'star_style' => 'star_unicode', 'unmarked_star_style' => 'outline' ) + $rset );
	check_t( str_contains( $runi, '&#9734;<span class="digi-review__fill">&#9733;</span>' ) && ! str_contains( $runi, 'digi-review__stars" role="img" aria-label="Valoración: 4,5 de 5"><i class="digi-review__star digi-review__star--full"><svg' ), 'Reseñas: el estilo Unicode con contorno llega a las estrellas.' );
	$revil = $render( Reviews::class, array( 'slides' => array( array( '_id' => 'e1', 'image' => array( 'url' => '' ), 'name' => '<script>x</script>Ana', 'title' => '<i onmouseover="x()">t</i>', 'rating' => '"><script>', 'selected_social_icon' => array( 'value' => 'fab fa-"><x', 'library' => 'fa-brands' ), 'link' => array( 'url' => 'https://a.test/"><script>' ), 'content' => '<script>y</script>Bien' ) ) ) + $rset );
	check_t( ! str_contains( $revil, '<script' ) && ! str_contains( $revil, 'onmouseover' ) && ! str_contains( $revil, '"><x' ) && str_contains( $revil, 'href="https://a.test/&quot;&gt;&lt;script&gt;"' ), 'Reseñas: escape del nombre, cargo, valoración, icono y enlace.' );

	// 4. Conversión de ajustes reales de PRO con los mapas (listos tras el A/B de C1).
	Registry::reset();
	$lands = static function ( $widget, $key ) {
		$base = Translator::base( (string) $key );
		if ( isset( $widget->controls[ $base ] ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		foreach ( array_keys( $widget->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$cases = array(
		'testimonial-carousel' => array(
			'tc-defaults'   => array(),
			'tc-bubble'     => array( 'skin' => 'bubble', 'layout' => 'image_above', 'alignment' => 'left', 'alignment_mobile' => 'center', 'background_color' => '#eeeeee', 'text_padding' => array( 'unit' => 'px', 'top' => '30', 'right' => '30', 'bottom' => '30', 'left' => '30', 'isLinked' => true ), 'border_radius' => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ), 'border' => 'yes', 'border_color' => '#333333', 'border_width' => array( 'unit' => 'px', 'size' => 2 ), 'border_width_tablet' => array( 'unit' => 'px', 'size' => 1 ) ),
			'tc-layout'     => array( 'layout' => 'image_left', 'content_gap' => array( 'unit' => 'px', 'size' => 30 ), 'image_size' => array( 'unit' => 'px', 'size' => 80 ), 'image_size_mobile' => array( 'unit' => 'px', 'size' => 40 ), 'image_gap' => array( 'unit' => 'px', 'size' => 12 ), 'image_border' => 'yes', 'image_border_color' => '#ff0000', 'image_border_width' => array( 'unit' => 'px', 'size' => 3 ), 'image_border_radius' => array( 'unit' => '%', 'size' => 10 ), 'image_size_size' => 'thumbnail', 'lazyload' => 'yes' ),
			'tc-carousel'   => array( 'slides_name' => 'Opiniones', 'slides_per_view' => '2', 'slides_per_view_mobile' => '1', 'slides_to_scroll' => '2', 'width' => array( 'unit' => '%', 'size' => 80 ), 'show_arrows' => '', 'pagination' => 'fraction', 'speed' => 300, 'autoplay' => '', 'loop' => '', 'space_between' => array( 'unit' => 'px', 'size' => 20 ), 'space_between_tablet' => array( 'unit' => 'px', 'size' => 10 ), 'slide_background_color' => '#fafafa', 'slide_border_size' => array( 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ), 'slide_border_radius' => array( 'unit' => 'px', 'size' => 6 ), 'slide_border_color' => '#dddddd', 'slide_padding' => array( 'unit' => 'px', 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'isLinked' => true ), 'arrows_size' => array( 'unit' => 'px', 'size' => 30 ), 'arrows_color' => '#111111', 'pagination_gap' => array( 'unit' => 'px', 'size' => 8 ), 'pagination_size' => array( 'unit' => 'px', 'size' => 10 ), 'pagination_color_inactive' => '#cccccc', 'pagination_color' => '#000000' ),
			'tc-typography' => array( 'content_color' => '#222222', 'content_typography_typography' => 'custom', 'content_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'content_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 15 ), 'text_stroke_text_stroke_type' => 'yes', 'text_stroke_text_stroke' => array( 'unit' => 'px', 'size' => 1 ), 'content_text_shadow_text_shadow_type' => 'yes', 'content_text_shadow_text_shadow' => array( 'horizontal' => 0, 'vertical' => 1, 'blur' => 2, 'color' => 'rgba(0,0,0,.3)' ), 'name_color' => '#000000', 'name_typography_typography' => 'custom', 'name_typography_font_weight' => '800', 'title_color' => '#555555', 'title_typography_typography' => 'custom', 'title_typography_font_style' => 'italic' ),
			'tc-globals'    => array( 'content_color' => '', 'name_color' => '', '__globals__' => array( 'content_color' => 'globals/colors?id=accent', 'name_color' => 'globals/colors?id=primary', 'title_color' => 'globals/colors?id=secondary', 'content_typography_typography' => 'globals/typography?id=accent', 'name_typography_typography' => 'globals/typography?id=text', 'background_color' => 'globals/colors?id=text', 'pagination_color' => 'globals/colors?id=accent', '_background_color' => 'globals/colors?id=text' ) ),
			'tc-dynamic'    => array( 'slides' => array( array( '_id' => 'd1', 'content' => '', 'name' => '', 'title' => 'Cliente', 'image' => array( 'url' => '', 'id' => '' ), '__dynamic__' => array( 'content' => '[elementor-tag id="t1" name="post-excerpt" settings="%7B%7D"]', 'name' => '[elementor-tag id="t2" name="author-name" settings="%7B%7D"]', 'image' => '[elementor-tag id="t3" name="author-profile-picture" settings="%7B%7D"]' ) ) ), '__dynamic__' => array( 'slides_name' => '[elementor-tag id="t4" name="site-title" settings="%7B%7D"]' ) ),
			'tc-css'        => array( 'custom_css' => "selector .elementor-testimonial__text{color:red}\nselector.elementor-testimonial--skin-bubble .elementor-testimonial__content{border-radius:0}\nselector.elementor-testimonial--layout-image_above .elementor-testimonial__name{font-size:9px}\n.elementor-widget-testimonial-carousel.elementor-testimonial--tablet-align-left .elementor-testimonial__cite{margin:0}", '_css_classes' => 'mis-testimonios', '_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
			'tc-legacy'     => array( 'effect' => 'cube', 'height' => array( 'unit' => 'px', 'size' => 300 ), 'pagination_position' => 'inside', 'loop' => '' ),
		),
		'reviews'              => array(
			'rv-defaults'   => array(),
			'rv-style'      => array( 'header_background_color' => '#f5f5f5', 'content_gap' => array( 'unit' => 'px', 'size' => 20 ), 'show_separator' => '', 'name_color' => '#111111', 'name_typography_typography' => 'custom', 'name_typography_font_size' => array( 'unit' => 'px', 'size' => 16 ), 'title_color' => '#777777', 'title_typography_typography' => 'custom', 'content_color' => '#333333', 'content_typography_typography' => 'custom', 'content_typography_line_height' => array( 'unit' => 'em', 'size' => 1.6 ), 'image_size' => array( 'unit' => 'px', 'size' => 48 ), 'image_gap' => array( 'unit' => 'px', 'size' => 14 ), 'image_gap_mobile' => array( 'unit' => 'px', 'size' => 8 ), 'image_border_radius' => array( 'unit' => 'px', 'size' => 4 ), 'icon_color' => 'custom', 'icon_custom_color' => '#00aa00', 'icon_size' => array( 'unit' => 'px', 'size' => 22 ), 'icon_size_tablet' => array( 'unit' => 'px', 'size' => 18 ) ),
			'rv-stars'      => array( 'star_style' => 'star_unicode', 'unmarked_star_style' => 'outline', 'star_size' => array( 'unit' => 'px', 'size' => 18 ), 'star_space' => array( 'unit' => 'px', 'size' => 2 ), 'stars_color' => '#ffaa00', 'stars_unmarked_color' => '#dddddd', 'separator_color' => '#cccccc', 'separator_size' => array( 'unit' => 'px', 'size' => 2 ), 'slides' => array( array( '_id' => 's1', 'image' => array( 'url' => '', 'id' => '' ), 'name' => 'Eva', 'title' => '@eva', 'rating' => '3.5', 'selected_social_icon' => array( 'value' => 'fab fa-yelp', 'library' => 'fa-brands' ), 'link' => array( 'url' => 'https://ejemplo.test/eva', 'is_external' => '', 'nofollow' => 'on', 'custom_attributes' => '' ), 'content' => 'Muy bien' ) ) ),
			'rv-globals'    => array( '__globals__' => array( 'name_color' => 'globals/colors?id=primary', 'title_color' => 'globals/colors?id=secondary', 'content_color' => 'globals/colors?id=text', 'stars_color' => 'globals/colors?id=accent', 'header_background_color' => 'globals/colors?id=secondary', 'name_typography_typography' => 'globals/typography?id=primary', 'content_typography_typography' => 'globals/typography?id=text', 'icon_custom_color' => 'globals/colors?id=accent' ) ),
			'rv-dynamic'    => array( 'slides' => array( array( '_id' => 'd1', 'image' => array( 'url' => '', 'id' => '' ), 'name' => '', 'title' => '', 'rating' => '', 'link' => array( 'url' => '' ), 'content' => '', '__dynamic__' => array( 'name' => '[elementor-tag id="r1" name="author-name" settings="%7B%7D"]', 'rating' => '[elementor-tag id="r2" name="post-custom-field" settings="%7B%22key%22%3A%22nota%22%7D"]', 'link' => '[elementor-tag id="r3" name="post-url" settings="%7B%7D"]', 'content' => '[elementor-tag id="r4" name="post-excerpt" settings="%7B%7D"]', 'image' => '[elementor-tag id="r5" name="featured-image" settings="%7B%7D"]' ) ) ) ),
			'rv-fa4'        => array( 'slides' => array( array( '_id' => 'f1', 'image' => array( 'url' => '', 'id' => '' ), 'name' => 'Pepe', 'title' => '', 'social_icon' => 'fa fa-facebook', 'content' => 'Hola' ) ) ),
			'rv-css'        => array( 'custom_css' => "selector .elementor-star-rating i{color:#000}\nselector .elementor-testimonial__header .elementor-icon-twitter{opacity:.5}\nselector.elementor-review--has-separator .elementor-testimonial__header{border-width:3px}\n.elementor-widget-reviews .elementor-star-full{x:y}" ),
		),
	);
	$total = 0;
	foreach ( $cases as $pro => $list ) {
		$tree = array( array( 'id' => 'c-' . $pro, 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
		foreach ( $list as $id => $settings ) {
			$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $pro, 'settings' => $settings, 'elements' => array() );
		}
		$converted = Converter::tree( $tree, $log, true );
		check_t( count( $list ) === count( $log ), "$pro: se convierte cada widget del árbol." );
		$total += count( $list );
		$out    = array();
		foreach ( $converted[0]['elements'] as $element ) {
			check_t( $widgets[ $pro ]['name'] === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa al widget propio con el mismo id.' );
			$out[ $element['id'] ] = $element['settings'];
			foreach ( $element['settings'] as $key => $value ) {
				check_t( $lands( $built[ $pro ], $key ), $element['id'] . ": la clave $key no cae en un control propio." );
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
					check_t( $lands( $built[ $pro ], $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
				}
			}
			foreach ( (array) ( $element['settings']['slides'] ?? array() ) as $slide ) {
				foreach ( array_merge( array_keys( $slide ), array_keys( (array) ( $slide['__dynamic__'] ?? array() ) ) ) as $key ) {
					check_t( isset( $built[ $pro ]->controls['slides']['fields'][ $key ] ) || Translator::common( (string) $key ) || ( 'social_icon' === $key && 'reviews' === $pro ), $element['id'] . ": el campo $key no cae en el repetidor propio." );
				}
			}
		}
		$codes = array();
		foreach ( $log as $entry ) {
			foreach ( $entry['codes'] as $code ) {
				$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
			}
		}
		$expected = 'testimonial-carousel' === $pro
			? array( 'tc-layout:pro.approx:lazyload', 'tc-carousel:pro.approx:loop', 'tc-css:pro.css:custom_css', 'tc-legacy:pro.dropped:effect', 'tc-legacy:pro.dropped:height', 'tc-legacy:pro.dropped:pagination_position', 'tc-legacy:pro.approx:loop' )
			: array( 'rv-css:pro.css:custom_css' );
		sort( $codes );
		sort( $expected );
		check_t( $expected === $codes, "$pro: ningún pro.unmapped; sólo lo declarado: " . implode( ', ', $codes ) );
		check_t( $converted === Converter::tree( $converted, $again, true ) && array() === $again, "$pro: lo convertido no se convierte otra vez." );
		$cases[ $pro ] = $out;
	}
	$tc = $cases['testimonial-carousel'];
	$d  = $tc['tc-defaults'];
	check_t( 'Slides' === $d['slides_name'] && 3 === count( $d['slides'] ) && 'John Doe' === $d['slides'][0]['name'] && 'CEO' === $d['slides'][0]['title'] && 0 === strpos( $d['slides'][0]['content'], 'Lorem ipsum dolor sit amet' ) && str_contains( $d['slides'][0]['image']['url'], 'placeholder.png' ) && 'testimonial-carousel' === $d['_digitalisimo_pro'], 'Un Testimonial Carousel sin cambios llega con las tres diapositivas y los textos que pintaba PRO.' );
	$painted = $render( Testimonial_Carousel::class, $d );
	check_t( 3 === substr_count( $painted, '<span class="digi-testimonial__name">John Doe</span><span class="digi-testimonial__title">CEO</span>' ) && str_contains( $painted, 'aria-label="Slides"' ) && 3 === substr_count( $painted, 'alt="John Doe"' ) && str_contains( $painted, 'data-digi-loop="1"' ), 'Lo convertido se pinta con los mismos textos, nombre accesible y texto alternativo que PRO.' );
	$b = $tc['tc-bubble'];
	check_t( 'bubble' === $b['skin'] && 'image_above' === $b['layout'] && 'left' === $b['alignment'] && 'center' === $b['alignment_mobile'] && '30' === $b['text_padding']['top'] && 'yes' === $b['border'] && 1 === $b['border_width_tablet']['size'] && '#333333' === $b['border_color'], 'Piel burbuja, disposición, alineación por dispositivo, relleno y borde.' );
	$l = $tc['tc-layout'];
	check_t( 'image_left' === $l['layout'] && 80 === $l['image_size']['size'] && 40 === $l['image_size_mobile']['size'] && 'thumbnail' === $l['image_size_size'] && 12 === $l['image_gap']['size'] && '%' === $l['image_border_radius']['unit'] && 'yes' === $l['lazyload'], 'Imagen: tamaño (y su tamaño registrado, del grupo de la base), separación, borde y radio.' );
	$c = $tc['tc-carousel'];
	check_t( 'Opiniones' === $c['slides_name'] && '2' === $c['slides_per_view'] && '1' === $c['slides_per_view_mobile'] && 80 === $c['width']['size'] && '' === $c['show_arrows'] && 'fraction' === $c['pagination'] && 300 === $c['speed'] && 10 === $c['space_between_tablet']['size'] && '#dddddd' === $c['slide_border_color'] && 30 === $c['arrows_size']['size'] && '#cccccc' === $c['pagination_color_inactive'], 'Ajustes del carrusel (base): todos pasan con su nombre.' );
	$cp = $render( Testimonial_Carousel::class, $c );
	check_t( str_contains( $cp, '<section class="digi-crs digi-crs--pag-out digi-testimonials" aria-roledescription="carrusel" aria-label="Opiniones" data-digi-effect="slide" data-digi-pagination="fraction" style="--digi-crs-speed:300ms;--digi-crs-n:3">' ), 'El carrusel convertido abre el motor con su nombre, sin bucle, fracción y velocidad: ' . substr( $cp, 0, 260 ) );
	$ty = $tc['tc-typography'];
	check_t( '#222222' === $ty['content_color'] && 15 === $ty['content_typography_font_size_mobile']['size'] && 'yes' === $ty['text_stroke_text_stroke_type'] && 2 === $ty['content_text_shadow_text_shadow']['blur'] && '800' === $ty['name_typography_font_weight'] && 'italic' === $ty['title_typography_font_style'], 'Colores, tipografías, contorno y sombra del texto.' );
	check_t( array( 'content_color' => 'globals/colors?id=accent', 'name_color' => 'globals/colors?id=primary', 'title_color' => 'globals/colors?id=secondary', 'content_typography_typography' => 'globals/typography?id=accent', 'name_typography_typography' => 'globals/typography?id=text', 'background_color' => 'globals/colors?id=text', 'pagination_color' => 'globals/colors?id=accent', '_background_color' => 'globals/colors?id=text' ) === $tc['tc-globals']['__globals__'], 'Testimonios: colores y tipografías globales del kit siguen a su control.' );
	$dy = $tc['tc-dynamic'];
	check_t( array( 'slides_name' => '[elementor-tag id="t4" name="site-title" settings="%7B%7D"]' ) === $dy['__dynamic__'] && array( 'content', 'name', 'image' ) === array_keys( $dy['slides'][0]['__dynamic__'] ) && 'd1' === $dy['slides'][0]['_id'] && 'Cliente' === $dy['slides'][0]['title'], 'Testimonios: etiquetas dinámicas del widget y de las filas siguen a su campo; el _id de la fila se conserva.' );
	check_t( "selector .digi-testimonial__text{color:red}\nselector.digi-testimonial--skin-bubble .digi-testimonial__content{border-radius:0}\nselector.digi-testimonial--layout-image_above .digi-testimonial__name{font-size:9px}\n.elementor-widget-digitalisimo-testimonial-carousel.digi-testimonial--tablet-align-left .digi-testimonial__cite{margin:0}" === $tc['tc-css']['custom_css'] && 'mis-testimonios' === $tc['tc-css']['_css_classes'], 'Testimonios: CSS personalizado con las clases propias (pieles, disposiciones y alineación con dispositivo); Avanzado intacto.' );
	check_t( ! isset( $tc['tc-legacy']['effect'] ) && ! isset( $tc['tc-legacy']['height'] ) && ! isset( $tc['tc-legacy']['pagination_position'] ), 'Los controles que PRO quita no pasan (se informan como descartados).' );
	foreach ( $map_classes = ( require $root . 'modules/digitalisimo-convert/maps/testimonial-carousel.php' )['classes'] as $from => $to ) {
		if ( str_starts_with( $to, '.digi-testimonial-' ) && str_contains( $to, 'align' ) ) {
			preg_match( '/^\.digi-testimonial-(-[a-z_]*)?-?align-(left|center|right)$/', $to, $mm );
			check_t( (bool) $mm, "Clase de alineación propia mal formada: $to" );
		}
	}

	$rc = $cases['reviews'];
	$d  = $rc['rv-defaults'];
	check_t( 3 === count( $d['slides'] ) && 'John Doe' === $d['slides'][0]['name'] && '@username' === $d['slides'][0]['title'] && 'Slides' === $d['slides_name'] && 'reviews' === $d['_digitalisimo_pro'], 'Un Reviews sin cambios llega con las tres reseñas que pintaba PRO.' );
	$painted = $render( Reviews::class, $d );
	check_t( 3 === substr_count( $painted, '<span class="digi-review__name">John Doe</span><span class="digi-review__title">@username</span>' ) && 3 === substr_count( $painted, '<div class="digi-review__text">Lorem ipsum' ) && 3 === substr_count( $painted, '<div class="digi-review__header">' ), 'Reseñas convertidas: mismos textos, cabecera sin enlace.' );
	$st = $rc['rv-style'];
	check_t( '#f5f5f5' === $st['header_background_color'] && '' === $st['show_separator'] && 48 === $st['image_size']['size'] && 8 === $st['image_gap_mobile']['size'] && 'custom' === $st['icon_color'] && '#00aa00' === $st['icon_custom_color'] && 18 === $st['icon_size_tablet']['size'] && 1.6 === $st['content_typography_line_height']['size'], 'Reseñas: cabecera, separador, imagen, icono y tipografías.' );
	$sr = $rc['rv-stars'];
	check_t( 'star_unicode' === $sr['star_style'] && 'outline' === $sr['unmarked_star_style'] && 18 === $sr['star_size']['size'] && '#ffaa00' === $sr['stars_color'] && '#dddddd' === $sr['stars_unmarked_color'] && '3.5' === $sr['slides'][0]['rating'] && 'on' === $sr['slides'][0]['link']['nofollow'], 'Reseñas: estilo y colores de las estrellas, valoración y enlace de la fila.' );
	$sp = $render( Reviews::class, $sr );
	check_t( str_contains( $sp, '<a class="digi-review__header" href="https://ejemplo.test/eva" rel="nofollow">' ) && str_contains( $sp, 'aria-label="Valoración: 3,5 de 5"' ) && str_contains( $sp, '&#9734;<span class="digi-review__fill" style="width:50%">&#9733;</span>' ) && str_contains( $sp, 'digi-review__icon--yelp' ), 'Reseña convertida: enlace con nofollow, valoración Unicode con media estrella e icono.' );
	check_t( array( 'name_color' => 'globals/colors?id=primary', 'title_color' => 'globals/colors?id=secondary', 'content_color' => 'globals/colors?id=text', 'stars_color' => 'globals/colors?id=accent', 'header_background_color' => 'globals/colors?id=secondary', 'name_typography_typography' => 'globals/typography?id=primary', 'content_typography_typography' => 'globals/typography?id=text', 'icon_custom_color' => 'globals/colors?id=accent' ) === $rc['rv-globals']['__globals__'], 'Reseñas: colores y tipografías globales siguen a su control.' );
	check_t( array( 'name', 'rating', 'link', 'content', 'image' ) === array_keys( $rc['rv-dynamic']['slides'][0]['__dynamic__'] ), 'Reseñas: etiquetas dinámicas de nombre, valoración, enlace, texto e imagen siguen a su campo.' );
	$fa4 = $rc['rv-fa4']['slides'][0];
	check_t( 'fa fa-facebook' === $fa4['social_icon'] && str_contains( $render( Reviews::class, $rc['rv-fa4'] ), '<div class="digi-review__icon digi-review__icon--facebook" style="--digi-review-icon:#3b5998" aria-hidden="true"><i class="fa fa-facebook" aria-hidden="true"></i></div>' ), 'Reseñas: el icono FA4 heredado pasa y se pinta con su color oficial.' );
	check_t( "selector .digi-review__stars i{color:#000}\nselector .digi-review__header .digi-review__icon--twitter{opacity:.5}\nselector.digi-review--has-separator .digi-review__header{border-width:3px}\n.elementor-widget-digitalisimo-reviews .digi-review__star--full{x:y}" === $rc['rv-css']['custom_css'], 'Reseñas: CSS personalizado con las clases propias (estrellas, iconos, separador).' );

	echo 'DIGITALÍSIMO Elements: Carrusel de testimonios (' . count( $t->controls ) . ' controles, ' . count( $t->groups ) . ' grupos, hoja de ' . strlen( $tcss ) . ' B) y Reseñas (' . count( $r->controls ) . ' controles, ' . count( $r->groups ) . ' grupos, hoja de ' . strlen( $rcss ) . " B), sus mapas y $total conversiones validados.\n";
}
