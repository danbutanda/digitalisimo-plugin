<?php
/**
 * Cita (`digitalisimo-blockquote`), el widget propio que sustituye al Blockquote de PRO (C0 de
 * docs/PRO_WIDGETS_OBJECTIVE.md), y su mapa `modules/digitalisimo-convert/maps/blockquote.php`:
 * - registro, recursos (sólo su hoja, ≤ 4 KB, sin JavaScript, movimiento reducido, foco visible, sin `--e-global-*`);
 * - un control propio por cada control de PRO, con los mismos valores por defecto visibles y las mismas opciones;
 * - marcado semántico de cada piel y vista, enlace para compartir bien formado y codificado, nombre accesible y escape;
 * - conversión con `Converter::tree()` de varios Blockquote de PRO (por defecto, cada piel, globales, dinámicos, CSS
 *   personalizado con clases de PRO, responsivos): ningún `pro.unmapped`, cada clave cae en un control propio y lo
 *   convertido se pinta con los mismos textos.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name ) {}
		public function start_controls_tab( $name, $args ) {}
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
	}
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const TAB_STYLE = 'style'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['bq_home'] = 'https://sitio.test/';
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^https?://~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function wp_parse_url( $url ) { return parse_url( $url ); }
	function wp_unslash( $v ) { return stripslashes( $v ); }
	function home_url( $path = '' ) { return rtrim( $GLOBALS['bq_home'], '/' ) . '/' . ltrim( $path, '/' ); }
	function check_bq( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/digitalisimo-widgets/class-blockquote.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Blockquote_Widget;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;

	$render = static function ( array $settings ) {
		$widget           = new Blockquote_Widget();
		$widget->settings = $settings;
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};

	// 1. Registro y recursos.
	$widget = new Blockquote_Widget();
	check_bq( 'digitalisimo-blockquote' === $widget->get_name() && 'Cita' === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories(), 'Nombre, título y categoría propios.' );
	check_bq( array( 'digitalisimo-blockquote' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'Sólo su hoja, sin JavaScript.' );
	$registry = (string) file_get_contents( $root . 'modules/digitalisimo-widgets/class-registry.php' );
	check_bq( (bool) preg_match( "/'digitalisimo-blockquote'\s*=>\s*array\(\s*'file'\s*=>\s*'class-blockquote\.php',\s*'class'\s*=>\s*Blockquote_Widget::class,\s*'css'\s*=>\s*'assets\/css\/blockquote\.css',\s*\)/", $registry ), 'La Cita está en Widget_Registry::WIDGETS con su hoja.' );
	$css = (string) file_get_contents( $root . 'assets/css/blockquote.css' );
	check_bq( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_bq( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $css, 'elementor' ), 'La hoja no usa el kit, url() ni clases del proveedor.' );
	check_bq( (bool) preg_match( '/@media \(prefers-reduced-motion:reduce\)\{\.digi-blockquote,\.digi-blockquote__button\{transition:none\}\}/', $css ) && str_contains( $css, '.digi-blockquote__button:focus-visible{outline:2px solid' ), 'Movimiento reducido en las transiciones y foco visible propio.' );
	preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $colors );
	check_bq( array() === array_diff( array_unique( array_map( 'strtolower', $colors[0] ) ), array( '#3f444b', '#1da1f2', '#0967a0', '#fff', '#f9fafa' ) ), 'Sólo los colores que PRO usaba por defecto: ' . implode( ', ', $colors[0] ) );
	$source = (string) file_get_contents( $root . 'modules/digitalisimo-widgets/class-blockquote.php' );
	check_bq( ! preg_match( '/jquery|wp_enqueue_script|wp_register_script|Icons_Manager|fa-twitter|eicon-(?!blockquote|text-align)/i', $source ), 'Sin jQuery, scripts ni iconos del proveedor.' );

	// 2. Un control propio por cada control de PRO, con los mismos valores por defecto visibles.
	( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	$map = require $root . 'modules/digitalisimo-convert/maps/blockquote.php';
	$pro = (string) file_get_contents( $root . 'modules/blockquote/widgets/blockquote.php' );
	preg_match_all( "/->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $pro, $pro_controls );
	preg_match_all( "/->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'/s", $pro, $pro_groups );
	$targets = array_combine( $map['keep'], $map['keep'] ) + $map['rename'];
	foreach ( $pro_controls[1] as $name ) {
		if ( in_array( $name, $map['ui'], true ) ) {
			check_bq( isset( $widget->controls[ $name ] ) && 'heading' === $widget->controls[ $name ]['type'], "$name: encabezado del panel." );
			continue;
		}
		check_bq( isset( $targets[ $name ], $widget->controls[ $targets[ $name ] ] ), "$name: sin control propio." );
	}
	foreach ( $pro_groups[1] as $name ) {
		check_bq( isset( $map['groups'][ $name ], $widget->groups[ $map['groups'][ $name ] ] ), "$name: sin grupo propio." );
	}
	check_bq( count( $pro_controls[1] ) + count( $pro_groups[1] ) === count( $widget->controls ) + count( $widget->groups ), 'La Cita tiene exactamente un control por cada control de PRO.' );
	$own_default = static function ( $name ) use ( $widget ) { return $widget->controls[ $name ]['default'] ?? null; };
	foreach ( array( 'blockquote_skin' => 'border', 'share_button' => 'yes', 'share_view' => 'icon-text', 'share_skin' => 'classic', 'url_type' => 'current_page' ) as $name => $value ) {
		check_bq( $value === $own_default( $name ), "$name: el valor por defecto visible es el de PRO." );
	}
	check_bq( 20 === $own_default( 'author_gap' )['size'] && 1 === $own_default( 'quote_size' )['size'] && 'ms' === $own_default( 'box_transition_duration' )['unit'], 'Separación del autor, tamaño de las comillas y unidad de las transiciones como en PRO.' );
	check_bq( array( 'border', 'quotation', 'boxed', 'clean' ) === array_keys( $widget->controls['blockquote_skin']['options'] ) && array( 'icon-text', 'icon', 'text' ) === array_keys( $widget->controls['share_view']['options'] ) && array( 'classic', 'bubble', 'link' ) === array_keys( $widget->controls['share_skin']['options'] ) && array( 'current_page', 'none', 'custom' ) === array_keys( $widget->controls['url_type']['options'] ) && array( 'left', 'center', 'right' ) === array_keys( $widget->controls['alignment']['options'] ), 'Las mismas opciones que PRO: el mapa no traduce valores.' );
	check_bq( 'globals/colors?id=text' === $widget->controls['content_text_color']['global']['default'] && 'globals/colors?id=secondary' === $widget->controls['author_text_color']['global']['default'], 'Colores globales por defecto del contenido y del autor, como en PRO.' );
	check_bq( array( 'alignment' => 'center', 'share_button' => 'yes' ) === $widget->controls['author_gap']['condition'] && array( 'share_button' => 'yes', 'share_view!' => 'icon' ) === $widget->controls['share_label']['condition'] && array( 'share_skin!' => 'link' ) === $widget->controls['button_background_color']['condition'] && array( 'blockquote_skin' => 'border' ) === $widget->sections['section_border_style']['condition'] && array( 'blockquote_skin!' => 'border' ) === $widget->sections['section_box_style']['condition'] && array( 'blockquote_skin' => 'quotation' ) === $widget->sections['section_quote_style']['condition'], 'Las mismas condiciones que PRO.' );
	check_bq( array( 'box_shadow_position' ) === $widget->groups['box_box_shadow']['exclude'] && 'border' === $widget->groups['box_border_hover']['type'], 'Bordes y sombras de la caja como en PRO.' );
	// Cada valor de la alineación fija todas las variables: un dispositivo nunca hereda las de otro.
	foreach ( $widget->controls['alignment']['selectors_dictionary'] as $value => $rules ) {
		foreach ( array( 'text-align:', '--digi-bq-ta:', '--digi-bq-fd:', '--digi-bq-as:', '--digi-bq-s:' ) as $rule ) {
			check_bq( str_contains( $rules, $rule ), "Alineación $value: falta $rule" );
		}
	}
	check_bq( 'digi-blockquote%s--align-' === $widget->controls['alignment']['prefix_class'] && ! empty( $widget->controls['alignment']['responsive'] ), 'La alineación es responsiva y su clase lleva el dispositivo.' );
	foreach ( array( '--digi-bq-t' => 'box_transition_duration', '--digi-bq-bt' => 'button_transition_duration' ) as $var => $control ) {
		check_bq( str_contains( implode( '', $widget->controls[ $control ]['selectors'] ), $var . ':' ) && str_contains( $css, 'var(' . $var . ',.3s)' ), "$control fija $var, que la hoja usa con .3s por defecto." );
	}
	// Cada clase propia del CSS personalizado convertido existe en el marcado o en una clase de envoltorio.
	$prefixed = array();
	foreach ( $widget->controls as $control ) {
		if ( isset( $control['prefix_class'] ) ) {
			foreach ( array_keys( $control['options'] ) as $option ) {
				$prefixed[] = '.' . sprintf( $control['prefix_class'], '' ) . $option;
			}
		}
	}
	$html = $render( array( 'blockquote_content' => 'Hola', 'author_name' => 'Ana', 'share_button' => 'yes', 'share_view' => 'icon-text', 'share_label' => 'Compartir', 'url_type' => 'none' ) );
	foreach ( $map['classes'] as $from => $to ) {
		check_bq( in_array( $to, $prefixed, true ) || str_contains( $html, 'class="' . substr( $to, 1 ) . '"' ) || str_contains( $html, 'class="' . substr( $to, 1 ) . ' ' ) || '.elementor-widget-digitalisimo-blockquote' === $to, "La clase $to (de $from) no existe en la Cita." );
	}

	// 3. Marcado, vistas, enlace para compartir y escape.
	check_bq( '<blockquote class="digi-blockquote"><p class="digi-blockquote__content">Hola</p><footer class="digi-blockquote__footer"><cite class="digi-blockquote__author">Ana</cite><a class="digi-blockquote__button" href="https://twitter.com/intent/tweet?text=Hola%20%E2%80%94%20Ana" target="_blank" rel="noopener noreferrer">' . Blockquote_Widget::ICON . '<span class="digi-blockquote__label">Compartir</span></a></footer></blockquote>' === $html, 'Marcado semántico con icono y texto: ' . $html );
	foreach ( array( 'border', 'quotation', 'boxed', 'clean' ) as $skin ) {
		$skinned = $render( array( 'blockquote_skin' => $skin, 'blockquote_content' => 'Hola', 'author_name' => 'Ana', 'share_button' => 'yes', 'share_view' => 'icon-text', 'share_label' => 'Compartir', 'url_type' => 'none' ) );
		check_bq( $html === $skinned && str_contains( $css, '.digi-blockquote--skin-' . $skin ) || ( 'clean' === $skin && $html === $skinned ), "Piel $skin: mismo marcado y la clase del envoltorio en la hoja." );
	}
	$icon = $render( array( 'blockquote_content' => 'Hola', 'share_button' => 'yes', 'share_view' => 'icon', 'share_label' => 'Tweet', 'url_type' => 'none' ) );
	check_bq( str_contains( $icon, 'aria-label="Tweet"' ) && str_contains( $icon, '<svg class="digi-blockquote__icon"' ) && ! str_contains( $icon, 'digi-blockquote__label' ) && ! str_contains( $icon, '<cite' ), 'Vista «Icono»: sin texto visible, con nombre accesible y sin autor vacío.' );
	$text = $render( array( 'blockquote_content' => 'Hola', 'share_button' => 'yes', 'share_view' => 'text', 'share_label' => '', 'url_type' => 'none' ) );
	check_bq( ! str_contains( $text, '<svg' ) && str_contains( $text, 'aria-label="Compartir en X"' ) && str_contains( $text, '<span class="digi-blockquote__label"></span>' ), 'Vista «Texto» sin texto: el botón conserva un nombre accesible.' );
	check_bq( '<blockquote class="digi-blockquote"><p class="digi-blockquote__content">Hola</p></blockquote>' === $render( array( 'blockquote_content' => 'Hola', 'share_button' => '' ) ) && '' === $render( array( 'blockquote_content' => '', 'author_name' => '', 'share_button' => '' ) ), 'Sin autor ni botón no hay pie; sin nada, no se pinta nada.' );
	check_bq( 'https://twitter.com/intent/tweet?text=Hola%20%26%20mundo%20%E2%80%94%20Ana%20%3CB%3E&url=https%3A%2F%2Fa.test%2Fx%3Fy%3D1%26z%3D2&via=digi' === Blockquote_Widget::share_url( array( 'blockquote_content' => 'Hola &amp; <b>mundo</b>', 'author_name' => 'Ana &lt;B&gt;', 'url_type' => 'custom', 'url' => ' https://a.test/x?y=1&z=2 ', 'user_name' => '@digi' ) ), 'Enlace para compartir: texto sin etiquetas, autor, enlace personalizado y usuario sin @, todo codificado.' );
	$GLOBALS['bq_home']     = 'https://sitio.test/sub/';
	$_SERVER['REQUEST_URI'] = '/sub/pagina/?p=1&q=a b';
	check_bq( 'https://twitter.com/intent/tweet?text=Cita&url=https%3A%2F%2Fsitio.test%2Fsub%2Fpagina%2F%3Fp%3D1%26q%3Da%20b' === Blockquote_Widget::share_url( array( 'blockquote_content' => 'Cita', 'url_type' => 'current_page' ) ), 'Página actual: origen del sitio más la ruta pedida, sin duplicar la subcarpeta.' );
	$GLOBALS['bq_home'] = 'http://red.test:8080/';
	check_bq( 'https://twitter.com/intent/tweet?text=Cita&url=http%3A%2F%2Fred.test%3A8080%2Fsub%2Fpagina%2F%3Fp%3D1%26q%3Da%20b' === Blockquote_Widget::share_url( array( 'blockquote_content' => 'Cita' ) ) && 'https://twitter.com/intent/tweet?text=Cita' === Blockquote_Widget::share_url( array( 'blockquote_content' => 'Cita', 'url_type' => 'none' ) ), 'El puerto se conserva; «Ninguno» no añade enlace.' );
	$evil = $render( array( 'blockquote_content' => 'Uno <script>alert(1)</script><em onclick="x()">dos</em>', 'author_name' => '<img src=x onerror=alert(1)>Ana', 'share_button' => 'yes', 'share_view' => 'icon-text', 'share_label' => '<b>Pu"bl</b>', 'url_type' => 'custom', 'url' => '"><script>' ) );
	check_bq( ! str_contains( $evil, '<script' ) && ! str_contains( $evil, 'onclick' ) && ! str_contains( $evil, 'onerror' ) && str_contains( $evil, '<em>dos</em>' ) && str_contains( $evil, '>Pu&quot;bl</span>' ) && str_contains( $evil, 'url=%22%3E%3Cscript%3E' ), 'Contenido y autor con el HTML permitido; texto del botón y enlace escapados.' );
	$markdown       = new Blockquote_Widget();
	$markdown->settings = array( 'blockquote_content' => "Uno\nDos", 'author_name' => 'Ana' );
	check_bq( "> Uno\n> Dos\n>\n> — Ana" === $markdown->render_markdown(), 'Markdown de la cita como en PRO.' );
	ob_start();
	( new ReflectionMethod( $widget, 'content_template' ) )->invoke( $widget );
	$editor = (string) ob_get_clean();
	check_bq( str_contains( $editor, '<blockquote class="digi-blockquote">' ) && str_contains( $editor, '<footer class="digi-blockquote__footer">' ) && str_contains( $editor, 'rel="noopener noreferrer"' ) && str_contains( $editor, "aria-label=\"{{ label || 'Compartir en X' }}\"" ) && str_contains( $editor, Blockquote_Widget::ICON ) && ! preg_match( '/var[^;#]*[\s,]view\s*=/', $editor ) && ! str_contains( $editor, '{{{ settings.' ), 'La vista previa repite el marcado, escapa los textos y no tapa el objeto view.' );

	// 4. Conversión de Blockquote de PRO con el mapa (borrador: sólo pruebas).
	Registry::reset();
	check_bq( null === Registry::map( 'blockquote' ) && null !== Registry::map( 'blockquote', true ) && 'draft' === $map['status'] && 'hide' === $map['panel'], 'El mapa está en borrador hasta el A/B y oculta el widget de PRO cuando esté listo.' );
	$own_keys = array_keys( $widget->controls );
	$lands    = static function ( $key ) use ( $own_keys, $widget ) {
		$base = \Digitalisimo\Elements\Convert\Translator::base( (string) $key );
		if ( in_array( $base, $own_keys, true ) || \Digitalisimo\Elements\Convert\Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', \Digitalisimo\Elements\Convert\Translator::MARKER ), true ) ) {
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
		'defaults'  => array(),
		'border'    => array( 'blockquote_skin' => 'border', 'border_color' => '#ff0000', 'border_width' => array( 'unit' => 'px', 'size' => 4 ), 'border_width_tablet' => array( 'unit' => 'px', 'size' => 2 ), 'border_gap' => array( 'unit' => 'px', 'size' => 30 ), 'border_color_hover' => '#00ff00', 'border_width_hover' => array( 'unit' => 'px', 'size' => 8 ), 'border_gap_hover' => array( 'unit' => 'px', 'size' => 40 ), 'border_transition_duration' => array( 'unit' => 'ms', 'size' => 500 ), 'border_vertical_padding' => array( 'unit' => 'px', 'size' => 10 ) ),
		'quotation' => array( 'blockquote_skin' => 'quotation', 'quote_text_color' => '#cccccc', 'quote_size' => array( 'unit' => 'px', 'size' => 1.5 ), 'quote_gap' => array( 'unit' => 'px', 'size' => 5 ), 'tweet_button_skin' => 'bubble', 'alignment' => 'right' ),
		'boxed'     => array( 'blockquote_skin' => 'boxed', 'box_padding' => array( 'unit' => 'px', 'size' => 40 ), 'box_background_color' => '#eeeeee', 'box_border_border' => 'solid', 'box_border_width' => array( 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px', 'isLinked' => true ), 'box_border_color' => '#333333', 'box_border_radius' => array( 'unit' => 'px', 'size' => 6 ), 'box_box_shadow_box_shadow_type' => 'yes', 'box_box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 4, 'blur' => 10, 'spread' => 0, 'color' => 'rgba(0,0,0,.2)' ), 'box_background_color_hover' => '#dddddd', 'box_border_hover_border' => 'dashed', 'box_border_radius_hover' => array( 'unit' => 'px', 'size' => 12 ), 'box_box_shadow_hover_box_shadow_type' => 'yes', 'box_transition_duration' => array( 'unit' => 's', 'size' => 1 ) ),
		'clean'     => array( 'blockquote_skin' => 'clean', 'tweet_button' => '', 'author_name' => '' ),
		'button'    => array( 'tweet_button_view' => 'icon', 'tweet_button_skin' => 'link', 'tweet_button_label' => 'Publicar', 'user_name' => '@digi', 'url_type' => 'custom', 'url' => 'https://a.test/', 'button_size' => array( 'unit' => 'px', 'size' => 1.2 ), 'button_border_radius' => array( 'unit' => 'px', 'size' => 4 ), 'button_background_color' => '#111111', 'button_text_color' => '#ffffff', 'button_background_color_hover' => '#222222', 'button_text_color_hover' => '#eeeeee', 'button_transition_duration' => array( 'unit' => 'ms', 'size' => 200 ), 'button_typography_typography' => 'custom', 'button_typography_font_weight' => '700' ),
		'globals'   => array( 'content_text_color' => '', 'author_text_color' => '', '__globals__' => array( 'content_text_color' => 'globals/colors?id=accent', 'author_text_color' => 'globals/colors?id=primary', 'content_typography_typography' => 'globals/typography?id=text', 'author_typography_typography' => 'globals/typography?id=secondary', 'button_background_color' => 'globals/colors?id=accent', 'button_typography_typography' => 'globals/typography?id=accent', 'box_background_color' => 'globals/colors?id=secondary', '_background_color' => 'globals/colors?id=text' ) ),
		'dynamic'   => array( 'url_type' => 'custom', '__dynamic__' => array( 'blockquote_content' => '[elementor-tag id="a1" name="post-excerpt" settings="%7B%7D"]', 'author_name' => '[elementor-tag id="a2" name="author-name" settings="%7B%7D"]', 'tweet_button_label' => '[elementor-tag id="a3" name="site-title" settings="%7B%7D"]', 'url' => '[elementor-tag id="a4" name="post-url" settings="%7B%7D"]' ) ),
		'css'       => array( 'custom_css' => "selector .elementor-blockquote__content{color:red}\nselector.elementor-blockquote--skin-boxed .elementor-blockquote{padding:0}\nselector .e-q-footer .elementor-blockquote__tweet-button:hover .elementor-blockquote__tweet-label{color:#000}\n.elementor-widget-blockquote .elementor-blockquote__author{font-size:9px}", '_css_classes' => 'mi-cita', '_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
		'responsive' => array( 'alignment' => 'center', 'alignment_mobile' => 'left', 'content_gap' => array( 'unit' => 'px', 'size' => 20 ), 'content_gap_tablet' => array( 'unit' => 'px', 'size' => 12 ), 'author_gap_mobile' => array( 'unit' => 'px', 'size' => 8 ), 'box_padding_mobile' => array( 'unit' => 'px', 'size' => 10 ), 'content_typography_typography' => 'custom', 'content_typography_font_size' => array( 'unit' => 'px', 'size' => 22 ), 'content_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 16 ), 'quote_size_tablet' => array( 'unit' => 'px', 'size' => 0.8 ), 'button_size_mobile' => array( 'unit' => 'px', 'size' => 0.9 ) ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'blockquote', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_bq( count( $cases ) === count( $log ), 'Se convierte cada Blockquote del árbol.' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_bq( 'digitalisimo-blockquote' === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa a la Cita con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_bq( $lands( $key ), $element['id'] . ": la clave $key no cae en un control de la Cita." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_bq( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control de la Cita." );
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_bq( array( 'css:pro.css:custom_css', 'responsive:pro.approx:alignment_mobile' ) === $codes, 'Ningún pro.unmapped ni pro.dropped; sólo el CSS convertido y la alineación por dispositivo: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_bq( 'border' === $d['blockquote_skin'] && 'John Doe' === $d['author_name'] && 0 === strpos( $d['blockquote_content'], 'Lorem ipsum dolor sit amet, consectetur' ) && 'yes' === $d['share_button'] && 'icon-text' === $d['share_view'] && 'classic' === $d['share_skin'] && 'Tweet' === $d['share_label'] && 'current_page' === $d['url_type'] && 20 === $d['author_gap']['size'] && 1 === $d['quote_size']['size'] && 'blockquote' === $d['_digitalisimo_pro'], 'Un Blockquote sin cambios llega con los valores por defecto visibles de PRO.' );
	check_bq( ! array_intersect( array( 'tweet_button', 'tweet_button_view', 'tweet_button_skin', 'tweet_button_label' ), array_keys( $d ) ), 'Las claves del botón ya no nombran al servicio.' );
	$_SERVER['REQUEST_URI'] = '/hola/';
	$GLOBALS['bq_home']     = 'https://sitio.test';
	$painted                = $render( $d );
	check_bq( str_contains( $painted, '>' . $d['blockquote_content'] . '</p>' ) && str_contains( $painted, '<cite class="digi-blockquote__author">John Doe</cite>' ) && str_contains( $painted, '<span class="digi-blockquote__label">Tweet</span>' ) && str_contains( $painted, 'href="https://twitter.com/intent/tweet?text=Lorem%20ipsum%20dolor%20sit%20amet%2C%20consectetur' ) && str_contains( $painted, '%20%E2%80%94%20John%20Doe&amp;url=https%3A%2F%2Fsitio.test%2Fhola%2F"' ), 'Lo convertido se pinta con los mismos textos y el mismo enlace que PRO.' );
	check_bq( '#ff0000' === $out['border']['border_color'] && 2 === $out['border']['border_width_tablet']['size'] && 500 === $out['border']['border_transition_duration']['size'] && 10 === $out['border']['border_vertical_padding']['size'] && 40 === $out['border']['border_gap_hover']['size'], 'Piel borde: colores, anchos por dispositivo, separación, hover y transición.' );
	check_bq( 'quotation' === $out['quotation']['blockquote_skin'] && '#cccccc' === $out['quotation']['quote_text_color'] && 1.5 === $out['quotation']['quote_size']['size'] && 5 === $out['quotation']['quote_gap']['size'] && 'bubble' === $out['quotation']['share_skin'] && 'right' === $out['quotation']['alignment'], 'Piel comillas, botón burbuja y alineación.' );
	check_bq( 'solid' === $out['boxed']['box_border_border'] && '1' === $out['boxed']['box_border_width']['top'] && 'yes' === $out['boxed']['box_box_shadow_box_shadow_type'] && 10 === $out['boxed']['box_box_shadow_box_shadow']['blur'] && 'dashed' === $out['boxed']['box_border_hover_border'] && 'yes' === $out['boxed']['box_box_shadow_hover_box_shadow_type'] && 12 === $out['boxed']['box_border_radius_hover']['size'] && 's' === $out['boxed']['box_transition_duration']['unit'], 'Piel caja: borde, sombra, radio y hover pasan con sus grupos.' );
	check_bq( 'clean' === $out['clean']['blockquote_skin'] && '' === $out['clean']['share_button'] && '' === $out['clean']['author_name'], 'Piel limpia sin botón ni autor.' );
	check_bq( '<blockquote class="digi-blockquote"><p class="digi-blockquote__content">' . $out['clean']['blockquote_content'] . '</p></blockquote>' === $render( $out['clean'] ), 'Sin autor ni botón, sólo la cita.' );
	$b = $out['button'];
	check_bq( 'icon' === $b['share_view'] && 'link' === $b['share_skin'] && 'Publicar' === $b['share_label'] && '@digi' === $b['user_name'] && '#222222' === $b['button_background_color_hover'] && 200 === $b['button_transition_duration']['size'] && '700' === $b['button_typography_font_weight'] && 1.2 === $b['button_size']['size'], 'Botón: vista, piel, texto, usuario, colores, transición y tipografía.' );
	check_bq( str_contains( $render( $b ), 'aria-label="Publicar"' ) && str_contains( $render( $b ), '&amp;url=https%3A%2F%2Fa.test%2F&amp;via=digi"' ), 'El botón convertido conserva su nombre accesible y su enlace.' );
	check_bq( array( 'content_text_color' => 'globals/colors?id=accent', 'author_text_color' => 'globals/colors?id=primary', 'content_typography_typography' => 'globals/typography?id=text', 'author_typography_typography' => 'globals/typography?id=secondary', 'button_background_color' => 'globals/colors?id=accent', 'button_typography_typography' => 'globals/typography?id=accent', 'box_background_color' => 'globals/colors?id=secondary', '_background_color' => 'globals/colors?id=text' ) === $out['globals']['__globals__'], 'Colores y tipografías globales del kit siguen a su control.' );
	check_bq( array( 'blockquote_content', 'author_name', 'share_label', 'url' ) === array_keys( $out['dynamic']['__dynamic__'] ) && '[elementor-tag id="a3" name="site-title" settings="%7B%7D"]' === $out['dynamic']['__dynamic__']['share_label'], 'Las etiquetas dinámicas siguen a su control (también la del texto del botón).' );
	check_bq( "selector .digi-blockquote__content{color:red}\nselector.digi-blockquote--skin-boxed .digi-blockquote{padding:0}\nselector .digi-blockquote__footer .digi-blockquote__button:hover .digi-blockquote__label{color:#000}\n.elementor-widget-digitalisimo-blockquote .digi-blockquote__author{font-size:9px}" === $out['css']['custom_css'] && 'mi-cita' === $out['css']['_css_classes'] && '10' === $out['css']['_margin']['top'], 'CSS personalizado con las clases propias; Avanzado intacto.' );
	$r = $out['responsive'];
	check_bq( 'center' === $r['alignment'] && 'left' === $r['alignment_mobile'] && 12 === $r['content_gap_tablet']['size'] && 8 === $r['author_gap_mobile']['size'] && 10 === $r['box_padding_mobile']['size'] && 16 === $r['content_typography_font_size_mobile']['size'] && 0.8 === $r['quote_size_tablet']['size'] && 0.9 === $r['button_size_mobile']['size'], 'Los valores por dispositivo pasan a su control.' );
	check_bq( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	echo 'DIGITALÍSIMO Elements: Cita (' . count( $widget->controls ) . ' controles, ' . count( $widget->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B) y su mapa desde el Blockquote de PRO (' . count( $cases ) . " casos) validados.\n";
}
