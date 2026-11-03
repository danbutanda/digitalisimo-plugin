<?php
/**
 * F8, frente D: «Distintivo» (`Digitalisimo\Elements\Badge`, §3.C.3 y contrato C11), con dobles de WordPress y del
 * núcleo.
 *
 * - Arranque: controles en widgets, secciones, columnas y contenedores; pintado de elementos y registro de la hoja.
 *   Nada en el contenido, el `head` ni `wp_enqueue_scripts`; 0 JS.
 * - Recursos: hoja ≤ 1 KB, sólo registrada (la encola el núcleo por `assets`), con posiciones lógicas, sin alto fijo,
 *   colores del sistema y flotación sólo sin movimiento reducido.
 * - Controles: las claves de C11, ninguno con `default`, `style_transfer => false` en interruptor, texto e icono, todos
 *   detrás del interruptor y con selectores acotados al distintivo del propio elemento.
 * - Marcado: primer hijo del envoltorio, `data-digi-badge` en el envoltorio, texto con `esc_html` y sin `aria-hidden`,
 *   icono con `aria-hidden`, esquinas y formas en listas cerradas, `clip-path` propio validado (nunca texto libre en una
 *   hoja), nada en el lienzo del editor ni sin texto ni icono.
 * - Fixture del navegador (`tests/fixtures/f8-badge.html`): sus elementos salen de este mismo código;
 *   `--write-fixture` lo regenera.
 * Uso: node <php-wasm-cli> tests/elements-badge.php
 */
namespace Elementor {
	class Controls_Manager {
		const TAB_ADVANCED = 'advanced'; const SWITCHER = 'switcher'; const TEXT = 'text'; const SELECT = 'select'; const SLIDER = 'slider';
		const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const HEADING = 'heading'; const RAW_HTML = 'raw_html'; const ICONS = 'icons';
	}
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Plugin { public static $instance; }
	/** Iconos del núcleo: un SVG propio de prueba para cualquier icono de la biblioteca. */
	class Icons_Manager {
		public static $calls = array();
		public static function try_get_icon_html( $icon, $attributes = array() ) {
			self::$calls[] = array( $icon, $attributes );
			return '<svg aria-hidden="true" class="e-font-icon-svg e-fas-star" viewBox="0 0 24 24"><path d="M12 2l3 7h7l-6 4 2 7-6-4-6 4 2-7-6-4h7z"/></svg>';
		}
	}
	class Stack_Double {
		public $name;
		public $sections = array();
		public $controls = array();
		public $groups   = array();
		private $section = '';
		public function __construct( $name ) { $this->name = $name; }
		public function get_controls( $id = null ) { return null === $id ? $this->controls : ( $this->controls[ $id ] ?? null ); }
		public function start_controls_section( $id, $args ) { $this->sections[ $id ] = $args; $this->section = $id; }
		public function end_controls_section() { $this->section = ''; }
		public function add_control( $id, $args ) {
			if ( isset( $this->controls[ $id ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $id ); }
			$this->controls[ $id ] = $args + array( '_section' => $this->section );
		}
		public function add_responsive_control( $id, $args ) { $this->add_control( $id, $args + array( '_responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = $args + array( '_type' => $type ); }
	}
	class Element_Double {
		public $settings;
		public $display = array();
		public $asked   = array();
		public function __construct( array $settings ) { $this->settings = $settings; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) {
			$this->asked[] = null === $key ? '*' : $key;
			$all = array_merge( $this->settings, $this->display );
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.170' );
	$root    = realpath( __DIR__ . '/../digitalisimo-elements' );
	$fixture = __DIR__ . '/fixtures/f8-badge.html';
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	$checks = 0;
	function check_badge( $ok, $message ) { global $checks; if ( ! $ok ) { throw new \RuntimeException( $message ); } $checks++; }

	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function hooked( $hook ) { $n = 0; foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $entries ) { $n += count( $entries ); } return $n; }
	function __( $text, $domain = 'default' ) { return $text; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_html( $text ) { return esc_attr( $text ); }
	function esc_html__( $text, $domain = 'default' ) { return esc_html( $text ); }
	function wp_strip_all_tags( $text ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text ) ) ); }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	$GLOBALS['styles'] = array(); $GLOBALS['enqueued'] = array();
	function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['styles'][ $handle ] ) : in_array( $handle, $GLOBALS['enqueued'], true ); }
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $handle ] = compact( 'src', 'deps', 'ver' ); return true; }

	$file = $root . '/modules/digitalisimo-widgets/class-badge.php';
	require $file;
	$B = '\Digitalisimo\Elements\Badge';
	\Elementor\Plugin::$instance         = new \stdClass();
	\Elementor\Plugin::$instance->editor = new class() { public $edit = false; public function is_edit_mode() { return $this->edit; } };

	// 1. Arranque.
	$B::init();
	$B::init();
	$places   = array( 'common/_section_style', 'section/section_advanced', 'container/section_layout', 'column/section_advanced' );
	$expected = array_merge( array_map( function ( $p ) { return 'elementor/element/' . $p . '/after_section_end'; }, $places ), array( 'elementor/frontend/before_render', 'elementor/frontend/after_render', 'elementor/frontend/after_register_styles' ) );
	check_badge( $expected === array_keys( $GLOBALS['wp_filters'] ), 'Engancha los controles de widgets, secciones, contenedores y columnas, el pintado y el registro de la hoja; nada más: ' . implode( ', ', array_keys( $GLOBALS['wp_filters'] ) ) );
	foreach ( $expected as $hook ) {
		check_badge( 1 === hooked( $hook ), 'Un solo enganche (init idempotente) en ' . $hook . '.' );
	}
	$source = file_get_contents( $file );
	$code   = php_strip_whitespace( $file );
	check_badge( ! preg_match( '/wp_(register|enqueue)_script|wp_enqueue_style\(|wp_head|wp_footer|the_content|wp_enqueue_scripts|common-optimized/', $code ), '0 JS, nada encolado a mano ni enganchado al contenido, y nunca common-optimized.' );
	check_badge( ! is_file( $root . '/modules/digitalisimo-widgets/assets/js/badge.js' ), 'No existe un badge.js: el distintivo no lleva JavaScript.' );

	// 2. Hoja.
	$B::register_assets();
	$B::register_assets();
	$style = $GLOBALS['styles']['digitalisimo-badge'] ?? null;
	check_badge( $style && str_ends_with( $style['src'], 'modules/digitalisimo-widgets/assets/css/badge.css' ) && array() === $style['deps'] && array() === $GLOBALS['enqueued'], 'La hoja se registra con su ruta y sin dependencias; no se encola.' );
	$css = file_get_contents( $root . '/' . $B::CSS );
	check_badge( strlen( $css ) <= 1024, 'badge.css pesa ' . strlen( $css ) . ' B (tope 1 KB, §5).' );
	check_badge( ! preg_match( '/url\(|#[0-9a-f]{3}(?:[0-9a-f]{3})?\b|rgba?\(|--e-global/i', $css ) && str_contains( $css, 'background:CanvasText;color:Canvas' ), 'Colores del sistema (Canvas/CanvasText), sin paleta propia ni url().' );
	check_badge( str_contains( $css, 'inset-block:' ) && str_contains( $css, 'inset-inline:' ) && ! preg_match( '/[{;](top|bottom|left|right|inset):/', $css ), 'Posición con propiedades lógicas: igual en RTL.' );
	preg_match( '/\.digi-badge\{([^}]*)\}/', $css, $base );
	check_badge( ! preg_match( '/(^|;)(height|max-height):/', $base[1] ?? 'height:' ) && str_contains( $base[1], 'pointer-events:none' ) && str_contains( $base[1], 'max-width:100%' ), 'Sin alto fijo (1.4.12), no captura el puntero y no pasa del ancho del elemento.' );
	check_badge( 1 === preg_match_all( '/animation:/', $css ) && (bool) preg_match( '/@media \(prefers-reduced-motion:no-preference\)\{\.digi-badge--float\{animation:digi-badge-float[^}]*\}\}/', $css ), 'La flotación sólo existe sin movimiento reducido (2.3.3).' );
	preg_match_all( '/\.([a-z][a-z0-9_-]*)/', preg_replace( '/\{[^{}]*\}/', '{}', $css ), $classes );
	check_badge( array() === array_filter( array_unique( $classes[1] ), function ( $c ) { return 0 !== strpos( $c, 'digi-badge' ); } ), 'La hoja sólo usa clases digi-badge.' );

	// 3. Controles, en las cuatro pilas.
	foreach ( array( 'common', 'section', 'container', 'column' ) as $name ) {
		$stack = new \Elementor\Stack_Double( $name );
		$B::register_controls( $stack );
		$B::register_controls( $stack );
		check_badge( 1 === count( $stack->sections ) && 'advanced' === $stack->sections['digitalisimo_badge_section']['tab'] && 'Distintivo' === $stack->sections['digitalisimo_badge_section']['label'], $name . ': una sola sección «Distintivo» en la pestaña Avanzado.' );
	}
	foreach ( array( 'digitalisimo_badge', 'digitalisimo_badge_text', 'digitalisimo_badge_icon', 'digitalisimo_badge_icon_position', 'digitalisimo_badge_corner', 'digitalisimo_badge_offset_x', 'digitalisimo_badge_offset_y', 'digitalisimo_badge_rotate', 'digitalisimo_badge_overflow', 'digitalisimo_badge_shape', 'digitalisimo_badge_clip', 'digitalisimo_badge_color', 'digitalisimo_badge_radius', 'digitalisimo_badge_padding', 'digitalisimo_badge_min_width', 'digitalisimo_badge_float' ) as $key ) {
		check_badge( isset( $stack->controls[ $key ] ), 'Contrato C11: existe ' . $key . '.' );
	}
	foreach ( array( 'digitalisimo_badge_bg' => 'background', 'digitalisimo_badge_border' => 'border', 'digitalisimo_badge_shadow' => 'box-shadow', 'digitalisimo_badge_typography' => 'typography', 'digitalisimo_badge_text_shadow' => 'text-shadow' ) as $group => $type ) {
		check_badge( $type === ( $stack->groups[ $group ]['_type'] ?? '' ) && ! isset( $stack->groups[ $group ]['fields_options'] ), 'Contrato C11: grupo ' . $group . ' sin valores por defecto.' );
	}
	$defaults = array_keys( array_filter( $stack->controls, function ( $c ) { return array_key_exists( 'default', $c ); } ) );
	check_badge( array() === $defaults, 'Ningún control nace con default: ' . implode( ', ', $defaults ) );
	foreach ( array( 'digitalisimo_badge', 'digitalisimo_badge_text', 'digitalisimo_badge_icon' ) as $id ) {
		check_badge( false === ( $stack->controls[ $id ]['style_transfer'] ?? null ), $id . ': no es de estilo (style_transfer => false).' );
	}
	foreach ( $stack->controls as $id => $control ) {
		if ( 'digitalisimo_badge' !== $id ) {
			check_badge( 'yes' === ( $control['condition']['digitalisimo_badge'] ?? '' ), $id . ': sólo con el interruptor encendido.' );
		}
	}
	$switch = $stack->controls['digitalisimo_badge'];
	check_badge( array( 'styles' ) === array_keys( $switch['assets'] ) && array( array( 'name' => 'digitalisimo-badge', 'conditions' => array( 'terms' => array( array( 'name' => 'digitalisimo_badge', 'operator' => '===', 'value' => 'yes' ) ) ) ) ) === $switch['assets']['styles'], 'El interruptor declara en assets sólo la hoja (0 JS), con el rasgo encendido.' );
	check_badge( true === ( $stack->controls['digitalisimo_badge_text']['dynamic']['active'] ?? false ) && 'icons' === $stack->controls['digitalisimo_badge_icon']['type'] && in_array( 'svg', $stack->controls['digitalisimo_badge_icon']['exclude_inline_options'], true ), 'Texto dinámico; icono de la biblioteca, sin SVG subido (nada que remapear en un kit).' );
	check_badge( array( '', 'top-start', 'bottom-start', 'bottom-end' ) === array_keys( $stack->controls['digitalisimo_badge_corner']['options'] ) && array( '', 'ribbon', 'tag', 'pill', 'custom' ) === array_keys( $stack->controls['digitalisimo_badge_shape']['options'] ) && array( 'start', 'end' ) === array_keys( $stack->controls['digitalisimo_badge_icon_position']['options'] ), 'Esquina, forma y posición del icono con los valores del contrato.' );
	check_badge( array( 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_shape' => 'custom' ) === $stack->controls['digitalisimo_badge_clip']['condition'] && ! isset( $stack->controls['digitalisimo_badge_clip']['selectors'] ), 'El recorte propio sólo con la forma «Recorte propio», y nunca como selector (no llega a una hoja como texto libre).' );
	check_badge( array( '{{WRAPPER}}' => 'overflow: hidden;' ) === $stack->controls['digitalisimo_badge_overflow']['selectors'], '«Recortar lo que sobresale»: overflow hidden en el anfitrión.' );
	foreach ( array( 'digitalisimo_badge_offset_x', 'digitalisimo_badge_offset_y', 'digitalisimo_badge_rotate', 'digitalisimo_badge_radius', 'digitalisimo_badge_padding', 'digitalisimo_badge_min_width' ) as $id ) {
		check_badge( ! empty( $stack->controls[ $id ]['_responsive'] ), $id . ': por dispositivo.' );
	}
	$selectors = array();
	foreach ( $stack->controls as $id => $control ) { if ( 'digitalisimo_badge_overflow' !== $id ) { $selectors = array_merge( $selectors, array_keys( $control['selectors'] ?? array() ) ); } }
	foreach ( $stack->groups as $group ) { $selectors[] = $group['selector']; }
	check_badge( count( $selectors ) >= 12 && array( '{{WRAPPER}} > .digi-badge' ) === array_values( array_unique( $selectors ) ), 'Todos los estilos van al distintivo del propio elemento ({{WRAPPER}} > .digi-badge).' );

	// 4. Marcado.
	$on = array( 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => 'Nuevo' );
	check_badge( '<span class="digi-badge digi-badge--top-end"><span class="digi-badge__text">Nuevo</span></span>' === $B::markup( $on ), 'Por defecto: arriba al final, texto como contenido (sin aria-hidden).' );
	check_badge( '' === $B::markup( array( 'digitalisimo_badge_text' => 'x' ) ) && '' === $B::markup( array( 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => '  ' ) ) && '' === $B::markup( array( 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => array( 'x' ) ) ), 'Apagado, o sin texto ni icono, no pinta nada.' );
	$xss = $B::markup( array( 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => 'Oferta "2x1" & <img src=x onerror=alert(1)> <script>alert(2)</script>' ) );
	check_badge( str_contains( $xss, '<span class="digi-badge__text">Oferta &quot;2x1&quot; &amp;' ) && ! preg_match( '/<img|<script|onerror|alert\(2\)/', $xss ), 'Texto con esc_html y sin etiquetas: ni imágenes, ni scripts, ni atributos.' );
	$icon = '<svg aria-hidden="true"></svg>';
	check_badge( '<span class="digi-badge digi-badge--top-end"><span class="digi-badge__icon" aria-hidden="true">' . $icon . '</span><span class="digi-badge__text">Nuevo</span></span>' === $B::markup( $on, $icon ), 'Icono antes del texto, con aria-hidden (decorativo).' );
	check_badge( $B::markup( $on, $icon ) === $B::markup( $on + array( 'digitalisimo_badge_icon_position' => 'start' ), $icon ), 'Sin elegir o con «start», el icono va antes del texto.' );
	check_badge( str_ends_with( $B::markup( $on + array( 'digitalisimo_badge_icon_position' => 'end' ), $icon ), '<span class="digi-badge__text">Nuevo</span><span class="digi-badge__icon" aria-hidden="true">' . $icon . '</span></span>' ), 'Icono después del texto.' );
	check_badge( '<span class="digi-badge digi-badge--top-end"><span class="digi-badge__icon" aria-hidden="true">' . $icon . '</span></span>' === $B::markup( array( 'digitalisimo_badge' => 'yes' ), $icon ), 'Sólo icono: se pinta (decorativo).' );
	foreach ( array( 'top-start', 'bottom-start', 'bottom-end' ) as $corner ) {
		check_badge( str_contains( $B::markup( $on + array( 'digitalisimo_badge_corner' => $corner ) ), 'class="digi-badge digi-badge--' . $corner . '"' ), 'Esquina ' . $corner . '.' );
	}
	check_badge( str_contains( $B::markup( $on + array( 'digitalisimo_badge_corner' => 'top-end" onmouseover="x' ) ), 'class="digi-badge digi-badge--top-end"' ), 'Una esquina forjada vuelve a la de por defecto (lista cerrada).' );
	foreach ( array( 'ribbon', 'tag', 'pill' ) as $shape ) {
		check_badge( str_contains( $B::markup( $on + array( 'digitalisimo_badge_shape' => $shape ) ), 'digi-badge--' . $shape . '"' ), 'Forma ' . $shape . '.' );
	}
	check_badge( ! str_contains( $B::markup( $on + array( 'digitalisimo_badge_shape' => 'star' ) ), 'star' ) && str_contains( $B::markup( $on + array( 'digitalisimo_badge_float' => 'yes' ) ), 'digi-badge--float' ), 'Una forma desconocida no deja clase; la flotación, sí.' );
	check_badge( str_contains( $B::markup( $on + array( 'digitalisimo_badge_shape' => 'custom', 'digitalisimo_badge_clip' => 'polygon(0 0, 100% 0, 90% 100%, 0 100%)' ) ), '<span class="digi-badge digi-badge--top-end" style="clip-path:polygon(0 0, 100% 0, 90% 100%, 0 100%)">' ), 'Recorte propio válido: en un atributo style escapado.' );
	check_badge( ! str_contains( $B::markup( $on + array( 'digitalisimo_badge_shape' => 'custom', 'digitalisimo_badge_clip' => 'url(#x)' ) ), 'style=' ), 'Recorte propio no válido: sin style.' );

	// 5. Validación del recorte propio.
	foreach ( array( 'polygon(0 0, 100% 0, 90% 100%, 0 100%)', 'polygon(0 0,100% 0,50% 100%)', 'inset(10% 20% round 5px)', 'inset(0)', 'circle(50% at 50% 50%)', 'circle(3em at center)', 'ellipse(25% 40% at 50% 50%)', 'polygon(-5px -0.5em, 10.5% .5em)', '  polygon( 0 0 , 1px 1px )  ' ) as $clip ) {
		check_badge( '' !== $B::clip( $clip ), 'Recorte válido: ' . $clip );
	}
	$hostile = array( 'url(#a)', 'polygon(0 0);}body{display:none', 'polygon(0 0)}', 'expression(alert(1))', 'polygon(var(--x) 0)', 'polygon(calc(1px + 2px) 0)', 'polygon(0 0) circle(1px)', 'path("M0 0")', 'polygon(0 0 /* x */)', 'polygon(0 0)<', 'polygon(0 0\\)', 'polygon(10vw 0)', 'polygon(1px1px)', 'POLYGON(0 0', '', array(), null, 'polygon(' . str_repeat( '1px ', 60 ) . ')' );
	foreach ( $hostile as $clip ) {
		check_badge( '' === $B::clip( $clip ), 'Recorte rechazado: ' . json_encode( $clip ) );
	}
	$start = microtime( true );
	check_badge( '' === $B::clip( 'polygon(' . str_repeat( '1', 190 ) . 'x)' ) && microtime( true ) - $start < 0.05, 'Una cadena hostil larga se rechaza sin retroceso exponencial.' );

	// 6. Pintado: búfer de before_render/after_render.
	function badge_render( $B, $element, $html ) {
		$B::before_render( $element );
		echo $html;
		$B::after_render( $element );
	}
	$level = ob_get_level();
	ob_start();
	$off = new \Elementor\Element_Double( array( 'digitalisimo_badge' => '' ) );
	badge_render( $B, $off, '<div class="elementor-element">contenido</div>' );
	check_badge( '<div class="elementor-element">contenido</div>' === ob_get_clean() && array() === $off->asked && ob_get_level() === $level, 'Apagado: el HTML no cambia, no se abre búfer ni se resuelve nada (coste 0).' );
	ob_start();
	$widget = new \Elementor\Element_Double( $on + array( 'digitalisimo_badge_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) ) );
	$widget->display = array( 'digitalisimo_badge_text' => 'Resuelto' );
	badge_render( $B, $widget, '<div class="elementor-element elementor-widget" data-id="w1"><div class="elementor-widget-container"><p>Hola</p></div></div>' );
	$html = ob_get_clean();
	check_badge( str_starts_with( $html, '<div data-digi-badge class="elementor-element elementor-widget" data-id="w1"><span class="digi-badge digi-badge--top-end"><span class="digi-badge__icon" aria-hidden="true"><svg' ) && str_contains( $html, '<span class="digi-badge__text">Resuelto</span></span><div class="elementor-widget-container"><p>Hola</p></div></div>' ), 'Encendido: data-digi-badge en el envoltorio y el distintivo como su primer hijo, antes del contenido.' );
	check_badge( array( 'digitalisimo_badge_text' ) === $widget->asked && array( 'aria-hidden' => 'true' ) === end( \Elementor\Icons_Manager::$calls )[1] && ob_get_level() === $level, 'Sólo se resuelve el texto dinámico; el icono, con aria-hidden; el búfer se cierra.' );
	ob_start();
	badge_render( $B, new \Elementor\Element_Double( $on ), '<section class="elementor-section"><div class="elementor-container">x</div></section>' );
	check_badge( str_starts_with( ob_get_clean(), '<section data-digi-badge class="elementor-section"><span class="digi-badge' ), 'Sección: el distintivo, primer hijo de <section>.' );
	// Contenedor con un widget dentro, los dos con distintivo (búferes anidados).
	ob_start();
	$container = new \Elementor\Element_Double( $on + array( 'digitalisimo_badge_corner' => 'bottom-start' ) );
	$inner     = new \Elementor\Element_Double( array( 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => 'Dentro' ) );
	$B::before_render( $container );
	echo '<div class="e-con"><div class="e-con-inner">';
	badge_render( $B, $inner, '<div class="elementor-widget">w</div>' );
	echo '</div></div>';
	$B::after_render( $container );
	$nested = ob_get_clean();
	check_badge( '<div data-digi-badge class="e-con"><span class="digi-badge digi-badge--bottom-start"><span class="digi-badge__text">Nuevo</span></span><div class="e-con-inner"><div data-digi-badge class="elementor-widget"><span class="digi-badge digi-badge--top-end"><span class="digi-badge__text">Dentro</span></span>w</div></div></div>' === $nested, 'Anidados: cada distintivo en su propio envoltorio.' );
	// Junto a otro rasgo con búfer (como «Enlace del elemento»): las dos modificaciones se aplican.
	ob_start();
	$both = new \Elementor\Element_Double( $on );
	ob_start(); // Búfer del otro rasgo, abierto antes.
	$B::before_render( $both );
	echo '<div class="elementor-widget"><p>x</p></div>';
	$other = ob_get_clean(); // El otro rasgo cierra primero (se registró antes) y vuelve a imprimir su HTML modificado.
	echo str_replace( '<p>', '<a class="digi-element-link"></a><p>', $other );
	$B::after_render( $both );
	$mixed = ob_get_clean();
	check_badge( str_contains( $mixed, '<span class="digi-badge digi-badge--top-end">' ) && str_contains( $mixed, '<a class="digi-element-link"></a><p>x</p>' ) && 1 === substr_count( $mixed, 'data-digi-badge' ), 'Con otro rasgo de búfer en el mismo elemento, se aplican los dos.' );
	ob_start();
	$empty = new \Elementor\Element_Double( $on );
	badge_render( $B, $empty, '' );
	check_badge( '' === ob_get_clean() && ob_get_level() === $level, 'Sin envoltorio (el elemento no se pinta), no queda nada y el búfer se cierra.' );
	\Elementor\Plugin::$instance->editor->edit = true;
	ob_start();
	badge_render( $B, new \Elementor\Element_Double( $on ), '<div class="x">y</div>' );
	check_badge( '<div class="x">y</div>' === ob_get_clean(), 'En el lienzo del editor no se pinta nada.' );
	\Elementor\Plugin::$instance->editor->edit = false;
	$B::after_render( new \Elementor\Element_Double( $on ) );
	check_badge( ob_get_level() === $level, 'Un after_render sin before_render no cierra búferes ajenos.' );

	// 7. Textos y guarda del paquete.
	preg_match_all( "/(?:__|esc_html__)\(\s*'[^']*',\s*'([a-z-]+)'\s*\)/", $source, $domains );
	check_badge( count( $domains[1] ) >= 25 && array( 'pro-elements' ) === array_values( array_unique( $domains[1] ) ), 'Textos traducibles con el dominio pro-elements (' . count( $domains[1] ) . ').' );
	check_badge( ! preg_match( "/__\(\s*'(Badge|Show|Text|Icon|Corner|Shape|Ribbon|Float|Background)\b/", $source ), 'Textos visibles en español.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	check_badge( ! preg_match( $guard, $source . $css ), 'Pasa la guarda de menciones del paquete.' );
	check_badge( str_contains( file_get_contents( $root . '/modules/digitalisimo-widgets/class-registry.php' ), "'class-badge.php' => Badge::class" ), 'Arranca desde la lista de rasgos de Widget_Registry::init().' );

	// 8. Fixture del navegador.
	function badge_element( $B, $id, array $settings, $inner, $tag = 'div', $class = 'elementor-widget' ) {
		$element = new \Elementor\Element_Double( $settings + array( 'digitalisimo_badge' => 'yes' ) );
		ob_start();
		$B::before_render( $element );
		echo '<' . $tag . ' class="elementor-element elementor-element-' . $id . ' ' . $class . '" data-id="' . $id . '">' . $inner . '</' . $tag . '>';
		$B::after_render( $element );
		return ob_get_clean();
	}
	$card = '<div class="elementor-widget-container"><a class="digi-test-card" href="#destino">Tarjeta con un enlace</a></div>';
	$star = array( 'value' => 'fas fa-star', 'library' => 'fa-solid' );
	$items = array(
		badge_element( $B, 'b1', array( 'digitalisimo_badge_text' => 'Nuevo' ), $card ),
		badge_element( $B, 'b2', array( 'digitalisimo_badge_text' => 'Inicio', 'digitalisimo_badge_corner' => 'top-start', 'digitalisimo_badge_shape' => 'pill', 'digitalisimo_badge_icon' => $star ), $card ),
		badge_element( $B, 'b3', array( 'digitalisimo_badge_text' => 'Abajo', 'digitalisimo_badge_corner' => 'bottom-start', 'digitalisimo_badge_shape' => 'ribbon' ), $card ),
		badge_element( $B, 'b4', array( 'digitalisimo_badge_text' => 'Final', 'digitalisimo_badge_corner' => 'bottom-end', 'digitalisimo_badge_shape' => 'tag', 'digitalisimo_badge_icon' => $star, 'digitalisimo_badge_icon_position' => 'end' ), $card ),
		badge_element( $B, 'b5', array( 'digitalisimo_badge_text' => 'Recorte', 'digitalisimo_badge_shape' => 'custom', 'digitalisimo_badge_clip' => 'polygon(0 0, 100% 0, 90% 100%, 0 100%)', 'digitalisimo_badge_float' => 'yes' ), $card ),
		badge_element( $B, 'b6', array( 'digitalisimo_badge_text' => 'Estilo', 'digitalisimo_badge_overflow' => 'yes' ), $card, 'div', 'e-con e-flex' ),
		'<div dir="rtl" class="digi-test-rtl">' . badge_element( $B, 'b7', array( 'digitalisimo_badge_text' => 'جديد' ), $card ) . badge_element( $B, 'b8', array( 'digitalisimo_badge_text' => 'بداية', 'digitalisimo_badge_corner' => 'top-start' ), $card ) . '</div>',
		badge_element( $B, 'b9', array( 'digitalisimo_badge_text' => 'Sección' ), '<div class="elementor-container">' . $card . '</div>', 'section', 'elementor-section' ),
	);
	$generated = '.elementor-element-b6 > .digi-badge{--digi-badge-x: 12px;--digi-badge-y: 8px;rotate: 10deg;background-color: #8a1d3a;color: #ffffff;}.elementor-element-b6{overflow: hidden;}';
	$page = "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Distintivo</title>\n"
		. "<!-- Generado por tests/elements-badge.php --write-fixture: no editar a mano. Lo usa elements-badge-chrome.mjs. -->\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/elementor/assets/css/frontend.min.css\">\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-widgets/assets/css/badge.css\">\n"
		. "<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#1a1a1a;background:#fff}.digi-test-page{padding:16px}.elementor-element{margin-bottom:24px}.digi-test-card{display:block;padding:48px 16px;border:1px solid #888;color:inherit}\n" . $generated . "</style>\n</head>\n<body>\n<div class=\"digi-test-page\">\n"
		. implode( "\n", $items ) . "\n</div>\n</body>\n</html>\n";
	if ( in_array( '--write-fixture', $argv ?? array(), true ) ) {
		file_put_contents( $fixture, $page );
		echo "Fixture escrito: tests/fixtures/f8-badge.html\n";
	}
	check_badge( is_file( $fixture ) && file_get_contents( $fixture ) === $page, 'El fixture del navegador es exactamente el marcado actual (regenerar con --write-fixture).' );
	check_badge( 9 === substr_count( $page, 'data-digi-badge' ) && ! str_contains( $page, '<script' ), 'El fixture tiene nueve distintivos y ningún script (0 JS).' );

	echo "F8, distintivo: {$checks} comprobaciones en verde.\n";
}
