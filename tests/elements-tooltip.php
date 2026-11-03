<?php
/**
 * F8, frente D: «Información emergente» (`Digitalisimo\Elements\Tooltip`, §3.C.3, V.10.5 y contrato C10), con dobles de
 * WordPress y del núcleo.
 *
 * - Arranque: sólo los ganchos de sus controles (en `common`, que el núcleo repite en `common-optimized`), del pintado de
 *   widgets y del registro de recursos; nada en el contenido, el `head` ni `wp_enqueue_scripts` (coste 0 donde no se usa).
 * - Recursos: hoja ≤ 1,5 KB y script ≤ 1 KB, `defer`, sin dependencias ni jQuery, sólo registrados: los encola el núcleo
 *   por la declaración `assets` del interruptor.
 * - Controles: las claves de C10, ninguno con `default`, `style_transfer => false` en interruptor, texto y disparador,
 *   todos detrás del interruptor y con selectores acotados al globo del propio widget.
 * - Marcado: globo con `role="tooltip"` y sin `id` (lo pone el script, V.10.5); texto saneado (sólo `strong`, `em`, `b`,
 *   `i`, `br`); lados en lista cerrada; envoltorio con `data-digi-tooltip` (y el disparador «clic»); nada en el lienzo
 *   del editor ni en el pintado de Markdown; nada si el texto queda vacío.
 * - Textos en español con el dominio `pro-elements`, sin nombres de los productos de origen (guarda del paquete).
 * - Fixture del navegador (`tests/fixtures/f8-tooltip.html`): sus widgets salen de este mismo código; `--write-fixture`
 *   lo regenera.
 * Uso: node <php-wasm-cli> tests/elements-tooltip.php
 */
namespace Elementor {
	class Controls_Manager {
		const TAB_ADVANCED = 'advanced'; const TAB_STYLE = 'style'; const SWITCHER = 'switcher'; const TEXTAREA = 'textarea'; const TEXT = 'text';
		const SELECT = 'select'; const SLIDER = 'slider'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const RAW_HTML = 'raw_html'; const ICONS = 'icons';
	}
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Plugin { public static $instance; }
	/** Pila de controles: secciones, controles y grupos, como los guarda el núcleo. */
	class Stack_Double {
		public $name;
		public $sections = array();
		public $controls = array();
		public $groups   = array();
		private $section = '';
		public function __construct( $name ) { $this->name = $name; }
		public function get_name() { return $this->name; }
		public function get_controls( $id = null ) { return null === $id ? $this->controls : ( $this->controls[ $id ] ?? null ); }
		public function start_controls_section( $id, $args ) { $this->sections[ $id ] = $args; $this->section = $id; }
		public function end_controls_section() { $this->section = ''; }
		public function add_control( $id, $args ) {
			if ( isset( $this->controls[ $id ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $id ); }
			$this->controls[ $id ] = $args + array( '_section' => $this->section );
		}
		public function add_responsive_control( $id, $args ) { $this->add_control( $id, $args + array( '_responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = $args + array( '_type' => $type, '_section' => $this->section ); }
	}
	/** Elemento en el front: ajustes, ajustes resueltos (anota cuáles se piden) y atributos de render. */
	class Element_Double {
		public $settings;
		public $display = array();
		public $asked   = array();
		public $attributes = array();
		public $type;
		public function __construct( array $settings, $type = 'widget' ) { $this->settings = $settings; $this->type = $type; }
		public function get_type() { return $this->type; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) {
			$this->asked[] = null === $key ? '*' : $key;
			$all = array_merge( $this->settings, $this->display );
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		public function add_render_attribute( $element, $key, $value = null ) { $this->attributes[ $element ][ $key ] = $value; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.170' );
	$root    = realpath( __DIR__ . '/../digitalisimo-elements' );
	$fixture = __DIR__ . '/fixtures/f8-tooltip.html';
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	$checks = 0;
	function check_tooltip( $ok, $message ) { global $checks; if ( ! $ok ) { throw new \RuntimeException( $message ); } $checks++; }

	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function apply_filters( $hook, $value, ...$args ) {
		$callbacks = $GLOBALS['wp_filters'][ $hook ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $entries ) { foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
		return $value;
	}
	function hooked( $hook ) { $n = 0; foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $entries ) { $n += count( $entries ); } return $n; }
	function __( $text, $domain = 'default' ) { $GLOBALS['domains'][ $domain ] = true; return $text; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
	function esc_html( $text ) { return esc_attr( $text ); }
	function esc_html__( $text, $domain = 'default' ) { return esc_html( __( $text, $domain ) ); }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	/** `wp_kses` mínimo: deja las etiquetas permitidas sin atributos y quita las demás (conserva su texto), como el núcleo. */
	function wp_kses( $text, $allowed ) {
		$out = '';
		foreach ( preg_split( '/(<[^>]*>)/', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE ) as $part ) {
			if ( preg_match( '/^<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>$/', $part, $m ) ) {
				$out .= isset( $allowed[ strtolower( $m[2] ) ] ) ? '<' . $m[1] . strtolower( $m[2] ) . '>' : '';
			} else {
				$out .= str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $part );
			}
		}
		return $out;
	}
	$GLOBALS['styles'] = array(); $GLOBALS['scripts'] = array(); $GLOBALS['enqueued'] = array();
	function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['styles'][ $handle ] ) : in_array( 'style:' . $handle, $GLOBALS['enqueued'], true ); }
	function wp_script_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['scripts'][ $handle ] ) : in_array( 'script:' . $handle, $GLOBALS['enqueued'], true ); }
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $handle ] = compact( 'src', 'deps', 'ver' ); return true; }
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' ); return true; }
	function wp_enqueue_style( $handle ) { $GLOBALS['enqueued'][] = 'style:' . $handle; }
	function wp_enqueue_script( $handle ) { $GLOBALS['enqueued'][] = 'script:' . $handle; }

	$file = $root . '/modules/digitalisimo-widgets/class-tooltip.php';
	require $file;
	$T = '\Digitalisimo\Elements\Tooltip';
	\Elementor\Plugin::$instance         = new \stdClass();
	\Elementor\Plugin::$instance->editor = new class() { public $edit = false; public function is_edit_mode() { return $this->edit; } };

	// 1. Arranque y coste 0.
	$T::init();
	$T::init();
	$expected = array( 'elementor/element/common/_section_style/after_section_end', 'elementor/frontend/widget/before_render', 'elementor/frontend/widget/after_render', 'elementor/widget/render_content', 'elementor/frontend/after_register_styles', 'elementor/frontend/after_register_scripts' );
	check_tooltip( $expected === array_keys( $GLOBALS['wp_filters'] ), 'Sólo engancha controles, pintado de widgets y registro de recursos: ' . implode( ', ', array_keys( $GLOBALS['wp_filters'] ) ) );
	foreach ( $expected as $hook ) {
		check_tooltip( 1 === hooked( $hook ), 'Un solo enganche (init idempotente) en ' . $hook . '.' );
	}
	check_tooltip( 3 === $GLOBALS['wp_filters']['elementor/widget/render_content'][10][0][1], 'render_content recibe el modo de pintado (tercer argumento).' );
	$source = file_get_contents( $file );
	$code   = php_strip_whitespace( $file );
	check_tooltip( ! preg_match( '/common-optimized|section\/section_advanced|container\/section_layout|column\/section_advanced/', $code ), 'Sólo en widgets: ni secciones, ni columnas, ni contenedores, y nunca common-optimized (el núcleo repite allí los ganchos de common).' );
	check_tooltip( ! preg_match( '/wp_enqueue_(style|script)\(|wp_head|wp_footer|the_content|wp_enqueue_scripts/', $code ), 'Nada encolado a mano ni enganchado al contenido o al head: los recursos llegan por assets.' );

	// 2. Recursos.
	$T::register_assets();
	$T::register_assets();
	$style  = $GLOBALS['styles']['digitalisimo-tooltip'] ?? null;
	$script = $GLOBALS['scripts']['digitalisimo-tooltip'] ?? null;
	check_tooltip( $style && str_ends_with( $style['src'], 'modules/digitalisimo-widgets/assets/css/tooltip.css' ) && array() === $style['deps'] && '4.3.0.170' === $style['ver'], 'La hoja se registra con su ruta, sin dependencias y con la versión.' );
	check_tooltip( $script && str_ends_with( $script['src'], 'modules/digitalisimo-widgets/assets/js/tooltip.js' ) && array() === $script['deps'] && 'defer' === ( $script['args']['strategy'] ?? '' ) && true === ( $script['args']['in_footer'] ?? false ), 'El script se registra en el pie, con defer y sin dependencias (ni jQuery ni el frontend del núcleo).' );
	check_tooltip( array() === $GLOBALS['enqueued'], 'Registrar no encola nada.' );
	$css = file_get_contents( $root . '/' . $T::CSS );
	$js  = file_get_contents( $root . '/' . $T::JS );
	check_tooltip( strlen( $css ) <= 1536, 'tooltip.css pesa ' . strlen( $css ) . ' B (tope 1,5 KB, §5).' );
	check_tooltip( strlen( $js ) <= 1024, 'tooltip.js pesa ' . strlen( $js ) . ' B (tope 1 KB, §5).' );
	check_tooltip( ! preg_match( '/jQuery|\$\(|https?:\/\//', $js ), 'El script, sin jQuery ni URL.' );
	check_tooltip( ! preg_match( '/url\(|#[0-9a-f]{3}(?:[0-9a-f]{3})?\b|rgba?\(|--e-global/i', $css ) && str_contains( $css, 'background:CanvasText;color:Canvas' ), 'La hoja usa los colores del sistema (Canvas/CanvasText), sin paleta propia ni url().' );
	check_tooltip( ! str_contains( $css, 'visibility:hidden' ) && str_contains( $css, 'clip-path:inset(50%)' ), 'El globo se recorta para ocultarlo (no visibility:hidden): sigue en el árbol de accesibilidad (V.10.5).' );
	check_tooltip( str_contains( $css, '@media (prefers-reduced-motion:reduce){.digi-tooltip{transition:none}}' ), 'Sin transición con movimiento reducido.' );
	check_tooltip( (bool) preg_match( '/:is\(:hover,:focus-within\)/', $css ) && str_contains( $css, '.digi-tooltip::before{content:"";position:absolute;inset:calc(-1*var(--g))}' ), 'Se muestra al pasar el puntero o al enfocar, y el puente de ::before cubre la distancia: se puede recorrer con el puntero (1.4.13).' );
	preg_match_all( '/\.([a-z][a-z0-9_-]*)/', preg_replace( '/\{[^{}]*\}/', '{}', $css ), $classes );
	check_tooltip( array() === array_filter( array_unique( $classes[1] ), function ( $c ) { return ! preg_match( '/^(digi-|elementor-widget-container$)/', $c ); } ), 'La hoja sólo usa clases digi- (y el envoltorio interior del núcleo).' );

	// 3. Controles.
	$stack = new \Elementor\Stack_Double( 'common' );
	$T::register_controls( $stack, array() );
	$T::register_controls( $stack, array() );
	$section = $stack->sections['digitalisimo_tooltip_section'] ?? array();
	check_tooltip( 1 === count( $stack->sections ) && 'advanced' === ( $section['tab'] ?? '' ) && 'Información emergente' === ( $section['label'] ?? '' ), 'Una sola sección «Información emergente» en la pestaña Avanzado, aunque el gancho llegue dos veces.' );
	$keys = array( 'digitalisimo_tooltip', 'digitalisimo_tooltip_text', 'digitalisimo_tooltip_placement', 'digitalisimo_tooltip_trigger', 'digitalisimo_tooltip_arrow', 'digitalisimo_tooltip_offset', 'digitalisimo_tooltip_width', 'digitalisimo_tooltip_color', 'digitalisimo_tooltip_arrow_color', 'digitalisimo_tooltip_padding', 'digitalisimo_tooltip_radius', 'digitalisimo_tooltip_align' );
	foreach ( $keys as $key ) {
		check_tooltip( isset( $stack->controls[ $key ] ), 'Contrato C10: existe ' . $key . '.' );
	}
	foreach ( array( 'digitalisimo_tooltip_bg' => 'background', 'digitalisimo_tooltip_border' => 'border', 'digitalisimo_tooltip_shadow' => 'box-shadow', 'digitalisimo_tooltip_typography' => 'typography' ) as $group => $type ) {
		check_tooltip( $type === ( $stack->groups[ $group ]['_type'] ?? '' ) && ! isset( $stack->groups[ $group ]['default'] ) && ! isset( $stack->groups[ $group ]['fields_options'] ), 'Contrato C10: grupo ' . $group . ' (' . $type . ') sin valores por defecto.' );
	}
	check_tooltip( array( 'classic', 'gradient' ) === $stack->groups['digitalisimo_tooltip_bg']['types'] && in_array( 'image', $stack->groups['digitalisimo_tooltip_bg']['exclude'], true ), 'Fondo: color y degradado, sin imagen (nunca url() en el globo).' );
	check_tooltip( ! empty( $stack->controls['digitalisimo_tooltip_width']['_responsive'] ) && ! empty( $stack->controls['digitalisimo_tooltip_padding']['_responsive'] ) && ! empty( $stack->controls['digitalisimo_tooltip_radius']['_responsive'] ), 'Ancho, relleno y radio, por dispositivo.' );
	$defaults = array();
	foreach ( $stack->controls as $id => $control ) {
		if ( array_key_exists( 'default', $control ) ) { $defaults[] = $id; }
	}
	check_tooltip( array() === $defaults, 'Ningún control nace con default (un widget existente no cambia): ' . implode( ', ', $defaults ) );
	foreach ( array( 'digitalisimo_tooltip', 'digitalisimo_tooltip_text', 'digitalisimo_tooltip_trigger' ) as $id ) {
		check_tooltip( false === ( $stack->controls[ $id ]['style_transfer'] ?? null ), $id . ': no es de estilo (style_transfer => false).' );
	}
	foreach ( $stack->controls as $id => $control ) {
		if ( 'digitalisimo_tooltip' === $id ) { continue; }
		check_tooltip( 'yes' === ( $control['condition']['digitalisimo_tooltip'] ?? '' ), $id . ': sólo con el interruptor encendido.' );
	}
	$switch = $stack->controls['digitalisimo_tooltip'];
	$terms  = array( 'terms' => array( array( 'name' => 'digitalisimo_tooltip', 'operator' => '===', 'value' => 'yes' ) ) );
	check_tooltip( 'switcher' === $switch['type'] && 'yes' === $switch['return_value'] && array( 'styles', 'scripts' ) === array_keys( $switch['assets'] ) && array( array( 'name' => 'digitalisimo-tooltip', 'conditions' => $terms ) ) === $switch['assets']['styles'] && $switch['assets']['styles'] === $switch['assets']['scripts'], 'El interruptor declara en assets la hoja y el script, sólo con el rasgo encendido.' );
	check_tooltip( true === ( $stack->controls['digitalisimo_tooltip_text']['dynamic']['active'] ?? false ) && 'textarea' === $stack->controls['digitalisimo_tooltip_text']['type'], 'El texto admite etiquetas dinámicas (área de texto).' );
	check_tooltip( array( '', 'right', 'bottom', 'left' ) === array_keys( $stack->controls['digitalisimo_tooltip_placement']['options'] ) && array( '', 'click' ) === array_keys( $stack->controls['digitalisimo_tooltip_trigger']['options'] ), 'Posición y disparador con los valores del contrato ("" es arriba y «al pasar el puntero o al enfocar»).' );
	$selectors = array();
	foreach ( $stack->controls as $control ) { $selectors = array_merge( $selectors, array_keys( $control['selectors'] ?? array() ) ); }
	foreach ( $stack->groups as $group ) { $selectors[] = $group['selector']; }
	check_tooltip( count( $selectors ) >= 11, 'Hay controles de estilo (' . count( $selectors ) . ').' );
	foreach ( $selectors as $selector ) {
		foreach ( explode( ',', $selector ) as $part ) {
			check_tooltip( (bool) preg_match( '/^\{\{WRAPPER\}\} > (\.elementor-widget-container > )?\.digi-tooltip(::after)?$/', trim( $part ) ), 'Selector acotado al globo del propio widget (nunca al de uno anidado): ' . $part );
		}
	}
	check_tooltip( str_contains( implode( '', array_column( $stack->controls, 'raw' ) ), '4,5:1' ), 'Una nota recuerda el contraste mínimo si se cambian los colores.' );

	// 4. Globo.
	$on = array( 'digitalisimo_tooltip' => 'yes', 'digitalisimo_tooltip_text' => 'Texto de ayuda' );
	check_tooltip( '<span class="digi-tooltip digi-tooltip--top" role="tooltip">Texto de ayuda</span>' === $T::bubble( $on ), 'Globo por defecto: arriba, role="tooltip", sin id (lo genera el script) y sin aria-hidden.' );
	check_tooltip( '' === $T::bubble( array( 'digitalisimo_tooltip_text' => 'x' ) ) && '' === $T::bubble( array( 'digitalisimo_tooltip' => 'no', 'digitalisimo_tooltip_text' => 'x' ) ), 'Apagado no pinta nada.' );
	foreach ( array( '', '   ', '<br>', "\n\n", '<a href="#"></a>', '<strong> </strong>', array( 'x' ), null ) as $empty ) {
		check_tooltip( '' === $T::bubble( array( 'digitalisimo_tooltip' => 'yes', 'digitalisimo_tooltip_text' => $empty ) ), 'Un texto vacío (' . json_encode( $empty ) . ') no pinta un globo vacío.' );
	}
	foreach ( array( 'right', 'bottom', 'left' ) as $side ) {
		check_tooltip( str_contains( $T::bubble( $on + array( 'digitalisimo_tooltip_placement' => $side ) ), 'class="digi-tooltip digi-tooltip--' . $side . '"' ), 'Lado ' . $side . '.' );
	}
	check_tooltip( str_contains( $T::bubble( $on + array( 'digitalisimo_tooltip_placement' => 'top-start" onmouseover="x' ) ), 'class="digi-tooltip digi-tooltip--top"' ) && str_contains( $T::bubble( $on + array( 'digitalisimo_tooltip_placement' => array( 'left' ) ) ), 'digi-tooltip--top' ), 'Un lado forjado vuelve a arriba (lista cerrada).' );
	check_tooltip( str_contains( $T::bubble( $on + array( 'digitalisimo_tooltip_arrow' => 'yes' ) ), 'class="digi-tooltip digi-tooltip--top digi-tooltip--arrow"' ) && ! str_contains( $T::bubble( $on + array( 'digitalisimo_tooltip_arrow' => '' ) ), 'arrow' ), 'La flecha, sólo con el interruptor.' );
	$dirty = $T::bubble( array( 'digitalisimo_tooltip' => 'yes', 'digitalisimo_tooltip_text' => "Hola <strong onclick=\"x()\">fuerte</strong>\n<em class=\"y\">ver</em> <a href=\"https://example.test\">enlace</a> <img src=x onerror=alert(1)> <script>alert(2)</script><b>b</b><i>i</i>" ) );
	check_tooltip( str_contains( $dirty, 'Hola <strong>fuerte</strong><br>' ) && str_contains( $dirty, '<em>ver</em> enlace' ) && str_contains( $dirty, '<b>b</b><i>i</i>' ), 'Texto saneado: énfasis sin atributos, salto de línea como <br> y el texto de un enlace sin el enlace.' );
	check_tooltip( ! preg_match( '/<a\b|<img|<script|onclick|onerror|class="y"|href=/i', $dirty ), 'Ni enlaces, ni imágenes, ni scripts, ni atributos en el texto del globo.' );

	// 5. Pintado: before_render, render_content y after_render.
	$element = new \Elementor\Element_Double( array( 'digitalisimo_tooltip' => '', 'digitalisimo_tooltip_text' => 'x' ) );
	$T::before_render( $element );
	check_tooltip( array() === $element->attributes && array() === $element->asked && 'contenido' === $T::render_content( 'contenido', $element, array( 'mode' => 'normal' ) ), 'Apagado: ni atributos, ni texto dinámico resuelto, ni globo (coste 0).' );
	$element = new \Elementor\Element_Double( $on + array( 'digitalisimo_tooltip_placement' => 'bottom' ) );
	$element->display = array( 'digitalisimo_tooltip_text' => 'Resuelto <b>dinámico</b>' );
	$T::before_render( $element );
	check_tooltip( array( '_wrapper' => array( 'data-digi-tooltip' => '1' ) ) === $element->attributes, 'Encendido: el envoltorio recibe data-digi-tooltip.' );
	check_tooltip( array( 'digitalisimo_tooltip_text' ) === $element->asked, 'Sólo se resuelve el texto dinámico (no todos los ajustes del widget).' );
	check_tooltip( 'contenido' === $T::render_content( 'contenido', $element, array( 'mode' => 'markdown' ) ), 'En el pintado de Markdown no se añade el globo.' );
	$painted = $T::render_content( 'contenido', $element, array( 'mode' => 'normal' ) );
	check_tooltip( 'contenido<span class="digi-tooltip digi-tooltip--bottom" role="tooltip">Resuelto <b>dinámico</b></span>' === $painted, 'El globo va al final del contenido del widget, con el texto dinámico resuelto.' );
	check_tooltip( 'otra vez' === $T::render_content( 'otra vez', $element, array( 'mode' => 'normal' ) ), 'Una sola vez por pintado.' );
	check_tooltip( 'contenido' === $T::render_content( 'contenido', new \Elementor\Element_Double( $on ), array() ) && null === $T::render_content( null, $element ), 'Un widget que no pasó por before_render, o un contenido que no es texto, no cambian.' );
	$click = new \Elementor\Element_Double( $on + array( 'digitalisimo_tooltip_trigger' => 'click' ) );
	$T::before_render( $click );
	check_tooltip( array( 'data-digi-tooltip' => '1', 'data-digi-tooltip-trigger' => 'click' ) === $click->attributes['_wrapper'], 'Disparador «clic»: data-digi-tooltip-trigger="click".' );
	$T::after_render( $click );
	check_tooltip( 'vacío' === $T::render_content( 'vacío', $click, array( 'mode' => 'normal' ) ), 'after_render olvida un globo que no llegó a pintarse (contenido vacío): nunca pasa a otro widget.' );
	$empty = new \Elementor\Element_Double( array( 'digitalisimo_tooltip' => 'yes', 'digitalisimo_tooltip_text' => '' ) );
	$T::before_render( $empty );
	check_tooltip( array() === $empty->attributes, 'Sin texto: ni atributos ni globo.' );
	\Elementor\Plugin::$instance->editor->edit = true;
	$editing = new \Elementor\Element_Double( $on );
	$T::before_render( $editing );
	check_tooltip( array() === $editing->attributes && 'x' === $T::render_content( 'x', $editing, array() ), 'En el lienzo del editor no se pinta nada.' );
	\Elementor\Plugin::$instance->editor->edit = false;
	check_tooltip( '{{WRAPPER}} > .digi-tooltip, {{WRAPPER}} > .elementor-widget-container > .digi-tooltip' === $T::selector(), 'Selector del globo con y sin envoltorio interior.' );

	// 6. Textos, dominio y guarda del paquete.
	preg_match_all( "/(?:__|esc_html__)\(\s*'[^']*',\s*'([a-z-]+)'\s*\)/", $source, $domains );
	check_tooltip( count( $domains[1] ) >= 20 && array( 'pro-elements' ) === array_values( array_unique( $domains[1] ) ), 'Textos traducibles con el dominio pro-elements (' . count( $domains[1] ) . ').' );
	check_tooltip( ! preg_match( "/__\(\s*'(Tooltip|Show|Text|Position|Top|Bottom|Left|Right|Arrow|Click|Hover)\b/", $source ), 'Textos visibles en español.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	check_tooltip( ! preg_match( $guard, $source . $css . $js ), 'Pasa la guarda de menciones del paquete (scripts/build-packages.sh): ningún nombre ni prefijo de los productos de origen.' );
	$registry = file_get_contents( $root . '/modules/digitalisimo-widgets/class-registry.php' );
	check_tooltip( str_contains( $registry, "'class-tooltip.php' => Tooltip::class" ), 'Arranca desde la lista de rasgos de Widget_Registry::init().' );

	// 7. Fixture del navegador: widgets pintados con este código (clásico y optimizado) y el estilo generado.
	function tooltip_widget( $T, $id, array $settings, $inner, $optimized = false, $extra = '', $type = 'text-editor' ) {
		$element = new \Elementor\Element_Double( $settings + array( 'digitalisimo_tooltip' => 'yes' ) );
		$T::before_render( $element );
		$attrs = '';
		foreach ( $element->attributes['_wrapper'] ?? array() as $name => $value ) { $attrs .= ' ' . $name . '="' . esc_attr( $value ) . '"'; }
		$content = $T::render_content( $inner, $element, array( 'mode' => 'normal' ) );
		$T::after_render( $element );
		$body = $optimized ? $extra . $content : $extra . '<div class="elementor-widget-container">' . $content . '</div>';
		return '<div class="elementor-element elementor-element-' . $id . ' elementor-widget elementor-widget-' . $type . '" data-id="' . $id . '" data-element_type="widget" data-widget_type="' . $type . '.default"' . $attrs . '>' . $body . '</div>';
	}
	$long  = 'Un texto de ayuda bastante largo que ocupa varias líneas en un teléfono y no debe salirse de la ventana por ningún lado.';
	$cases = array(
		tooltip_widget( $T, 't5top', array( 'digitalisimo_tooltip_text' => 'Arriba no cabe: se voltea', 'digitalisimo_tooltip_arrow' => 'yes' ), '<p class="digi-test-chip">Pegado arriba</p>' ),
		'<div class="digi-test-gap"></div>',
		tooltip_widget( $T, 't1text', array( 'digitalisimo_tooltip_text' => "Sólo texto: <strong>se lee</strong>\nsin foco vacío", 'digitalisimo_tooltip_arrow' => 'yes' ), '<h2 class="digi-test-chip">Encabezado sin enlaces</h2>', false, '', 'heading' ),
		tooltip_widget( $T, 't2link', array( 'digitalisimo_tooltip_text' => 'Describe el botón', 'digitalisimo_tooltip_placement' => 'bottom' ), '<a class="digi-test-chip" href="#destino">Un botón</a>', false, '', 'button' ),
		tooltip_widget( $T, 't3two', array( 'digitalisimo_tooltip_text' => 'Dos enlaces, marcado optimizado', 'digitalisimo_tooltip_placement' => 'right' ), '<p class="digi-test-chip"><a href="#uno">Uno</a> y <a href="#dos" aria-describedby="digi-test-previa">dos</a></p><span id="digi-test-previa" hidden>Descripción previa</span>', true ),
		tooltip_widget( $T, 't4click', array( 'digitalisimo_tooltip_text' => 'Se abre con un clic', 'digitalisimo_tooltip_placement' => 'left', 'digitalisimo_tooltip_trigger' => 'click', 'digitalisimo_tooltip_arrow' => 'yes' ), '<button class="digi-test-chip" type="button">Ayuda</button>' ),
		tooltip_widget( $T, 't6edge', array( 'digitalisimo_tooltip_text' => $long ), '<a class="digi-test-chip" href="#borde">i</a>', false, '', 'icon' ),
		tooltip_widget( $T, 't7nested', array( 'digitalisimo_tooltip_text' => 'El de fuera' ), '<div class="digi-test-chip"><a href="#fuera">Fuera</a>' . tooltip_widget( $T, 't7inner', array( 'digitalisimo_tooltip_text' => 'El de dentro', 'digitalisimo_tooltip_placement' => 'bottom' ), '<a href="#dentro">Dentro</a>' ) . '</div>', false, '', 'nested-tabs' ),
		tooltip_widget( $T, 't9overlay', array( 'digitalisimo_tooltip_text' => 'Todo el elemento es un enlace' ), '<p class="digi-test-chip">Tarjeta enlazada</p>', false, '<a class="digi-element-link" href="#tarjeta" aria-label="Abrir la tarjeta"></a>' ),
		tooltip_widget( $T, 't10styled', array( 'digitalisimo_tooltip_text' => 'Estilo propio', 'digitalisimo_tooltip_arrow' => 'yes' ), '<a class="digi-test-chip" href="#estilo">Con estilo</a>' ),
	);
	$generated = '.elementor-element-t10styled > .elementor-widget-container > .digi-tooltip, .elementor-element-t10styled > .digi-tooltip{--digi-tt-gap: 20px;max-width: 140px;color: #ffffff;background-color: #1d3a8a;padding: 10px 14px 10px 14px;}';
	$page = "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Información emergente</title>\n"
		. "<!-- Generado por tests/elements-tooltip.php --write-fixture: no editar a mano. Lo usa elements-tooltip-chrome.mjs. -->\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/elementor/assets/css/frontend.min.css\">\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-widgets/assets/css/tooltip.css\">\n"
		. "<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#1a1a1a;background:#fff}.digi-test-page{padding:0 16px 1200px}.elementor-widget{margin-bottom:96px}.digi-test-chip{display:inline-block;margin:0;padding:4px 8px}.elementor-element-t6edge{margin-left:-16px;width:24px}.elementor-element-t4click{margin-left:min(400px,50vw);width:120px}.elementor-element-t3two{width:300px}.digi-test-gap{height:96px}.digi-element-link{position:absolute;inset:0;z-index:1}\n" . $generated . "</style>\n</head>\n<body>\n<div class=\"digi-test-page\">\n"
		. implode( "\n", $cases ) . "\n"
		. "<p><a href=\"#fin\" id=\"fin\">Enlace final</a></p>\n</div>\n"
		. "<script src=\"../../digitalisimo-elements/modules/digitalisimo-widgets/assets/js/tooltip.js\" defer></script>\n</body>\n</html>\n";
	if ( in_array( '--write-fixture', $argv ?? array(), true ) ) {
		file_put_contents( $fixture, $page );
		echo "Fixture escrito: tests/fixtures/f8-tooltip.html\n";
	}
	check_tooltip( is_file( $fixture ) && file_get_contents( $fixture ) === $page, 'El fixture del navegador es exactamente el marcado actual (regenerar con --write-fixture).' );
	check_tooltip( 10 === substr_count( $page, 'role="tooltip"' ) && 0 === preg_match( '/class="digi-tooltip[^"]*"[^>]*\sid=/', $page ), 'El fixture tiene diez globos, ninguno con id del servidor.' );

	echo "F8, información emergente: {$checks} comprobaciones en verde.\n";
}
