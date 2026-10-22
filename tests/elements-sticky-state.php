<?php
/**
 * F7a, frente K: sticky «al fijarse» (`modules/digitalisimo-design/sticky/`, V.6 de la especificación).
 *
 * - Guarda: sin el sticky de PRO no se registra nada.
 * - Control en sección, contenedor y `common` (una sola instancia con el marcado optimizado: el núcleo repite los
 *   ganchos de `common` para `common-optimized`), detrás de `sticky_divider`, con `prefix_class`, `render_type` `ui` y
 *   la declaración `assets` de la hoja, evaluada con el `Conditions::check()` real del núcleo.
 * - La clase sale sólo de los ajustes del propio elemento: no hay pila, `before_render` ni estado por petición, así
 *   que vale igual desde la caché de elementos o un shortcode `[elementor-element]`.
 * - El formato de `data-settings` que reconoce la hoja es el que imprimen el núcleo y PRO (lectura de sus fuentes y
 *   `json_encode` real); la hoja cabe en 0,5 KB, cada regla empieza por `body:not(.elementor-editor-active)`, sólo
 *   actúa en descendientes y nunca sobre el propio elemento fijo ni sobre el espaciador en estado normal.
 */
namespace Elementor {
	class Controls_Manager { const SELECT = 'select'; const RAW_HTML = 'raw_html'; const DIVIDER = 'divider'; const SWITCHER = 'switcher'; }
	/** Pila con la inyección como en `Controls_Stack` (`get_position_info()` con `at = after`). */
	class Controls_Stack {
		public $controls = array();
		public $order = array();
		public $injection = null;
		public $injections = array();
		public function __construct( array $base = array() ) { foreach ( $base as $name ) { $this->controls[ $name ] = array( 'type' => 'select' ); $this->order[] = $name; } }
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function start_injection( array $position ) {
			if ( null !== $this->injection || ! isset( $this->controls[ $position['of'] ] ) ) { throw new \RuntimeException( 'Inyección inválida.' ); }
			$this->injection    = array_search( $position['of'], $this->order, true ) + 1;
			$this->injections[] = $position['of'];
		}
		public function end_injection() { $this->injection = null; }
		public function add_control( $name, array $args ) {
			if ( null === $this->injection ) { throw new \RuntimeException( 'Control fuera de la inyección: ' . $name ); }
			if ( isset( $this->controls[ $name ] ) ) { throw new \RuntimeException( 'Control repetido: ' . $name ); }
			$this->controls[ $name ] = $args;
			array_splice( $this->order, $this->injection++, 0, array( $name ) );
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ELEMENTOR_VERSION', '4.3.5' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );

	function check_sticky( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	$GLOBALS['wp_filters'] = array();
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][] = array( $callback, $args ); return true; }
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return add_action( $hook, $callback, $priority, $args ); }
	function do_action( $hook, ...$args ) { foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } }
	$GLOBALS['styles'] = array();
	function wp_register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) { $GLOBALS['styles'][ $handle ] = compact( 'src', 'deps', 'ver' ); return true; }
	function wp_enqueue_style( $handle ) { $GLOBALS['enqueued'][] = $handle; }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }

	require $root . '/modules/digitalisimo-design/class-module.php';
	require $root . '/modules/digitalisimo-design/sticky/class-sticky-state.php';
	require $root . '/elementor/includes/conditions.php';
	$sticky = \Digitalisimo\Elements\Design\Sticky\Sticky_State::class;

	// Guarda: el sticky de PRO aún no existe (se declara abajo), así que no se engancha nada.
	$sticky::init();
	check_sticky( array() === array_diff( array_keys( $GLOBALS['wp_filters'] ), array( 'elementor/editor/after_enqueue_scripts' ) ), 'Sin el sticky de PRO no se registra ningún gancho propio.' );
	$missing = \Digitalisimo\Elements\Design\Module::missing();
	check_sticky( isset( $missing['sticky'] ) && in_array( 'ElementorPro\Modules\Sticky\Module', $missing['sticky'], true ), 'La guarda anota el símbolo que falta.' );
	$GLOBALS['wp_filters'] = array();
}
namespace ElementorPro\Modules\Sticky {
	if ( true ) { // Declarada en ejecución, después de la prueba de la guarda.
		class Module {}
	}
}
namespace {
	$sticky::init();
	$sticky::init();
	foreach ( array( 'section', 'container', 'common' ) as $stack ) {
		check_sticky( 1 === count( $GLOBALS['wp_filters'][ "elementor/element/{$stack}/section_effects/before_section_end" ] ?? array() ), 'Un solo gancho en ' . $stack . '.' );
	}
	check_sticky( empty( $GLOBALS['wp_filters']['elementor/element/common-optimized/section_effects/before_section_end'] ) && empty( $GLOBALS['wp_filters']['elementor/element/column/section_effects/before_section_end'] ), 'Nunca common-optimized (V-I1) ni columnas (no tienen sticky).' );
	foreach ( array_keys( $GLOBALS['wp_filters'] ) as $hook ) {
		check_sticky( false === strpos( $hook, 'render' ) || 'elementor/frontend/after_register_styles' === $hook, 'Sin before_render ni after_render: ' . $hook );
	}
	$reflection = new \ReflectionClass( $sticky );
	check_sticky( array( 'initialized' ) === array_keys( $reflection->getStaticProperties() ), 'Sin pila ni estado por petición.' );

	// Bloque de sticky de PRO: `sticky` … `sticky_divider`, y lo que venga después en `section_effects`.
	$pro_block = array( 'motion_fx_motion_fx_scrolling', 'sticky', 'sticky_on', 'sticky_offset', 'sticky_effects_offset', 'sticky_anchor_link_offset', 'anchor_offset_description', 'sticky_parent', 'sticky_divider', '_animation' );
	$container = new \Elementor\Controls_Stack( $pro_block );
	do_action( 'elementor/element/container/section_effects/before_section_end', $container, array() );
	$at = array_search( 'sticky_divider', $container->order, true );
	check_sticky( array( $sticky::CONTROL, $sticky::NOTE ) === array_slice( $container->order, $at + 1, 2 ) && '_animation' === $container->order[ $at + 3 ] && array( 'sticky_divider' ) === $container->injections, 'El control va justo detrás de sticky_divider.' );
	$control = $container->controls[ $sticky::CONTROL ];
	check_sticky( 'select' === $control['type'] && '' === $control['default'] && 'digi-sticky-' === $control['prefix_class'] && 'ui' === $control['render_type'], 'SELECT vacío por defecto, con prefix_class y render_type ui (clase en vivo en el editor).' );
	check_sticky( array( '', 'show', 'hide', 'show-effects', 'hide-effects' ) === array_keys( $control['options'] ) && array( 'show', 'hide', 'show-effects', 'hide-effects' ) === $sticky::STATES, 'Los cuatro estados y «Siempre visible».' );
	check_sticky( empty( $control['selectors'] ) && empty( $control['frontend_available'] ), 'Sin selectores ni ajustes en data-settings: sólo la clase.' );
	foreach ( array_merge( array( $control['label'], $control['description'], $container->controls[ $sticky::NOTE ]['raw'] ), array_values( $control['options'] ) ) as $text ) {
		check_sticky( false === stripos( $text, 'elementor' ) && 1 === preg_match( '/[a-záéíóúñ]/u', $text ), 'Texto visible en español y sin el nombre del núcleo: ' . $text );
	}
	check_sticky( false !== strpos( $control['description'], 'fuera del editor' ) && false !== strpos( $control['description'], 'dispositivos' ), 'La ayuda dice que actúa fuera del editor y qué pasa donde no se fija.' );
	$note = $container->controls[ $sticky::NOTE ];
	check_sticky( 'raw_html' === $note['type'] && array( 'sticky!' => '', $sticky::CONTROL . '!' => '' ) === $note['condition'], 'En el propio elemento fijo, el editor avisa de que no tiene efecto (V-m4).' );

	// Declaración `assets`: la hoja sólo donde el control tiene valor, con el Conditions::check() real del núcleo.
	$assets = $control['assets']['styles'] ?? array();
	check_sticky( 1 === count( $assets ) && $sticky::HANDLE === $assets[0]['name'] && array( 'styles' ) === array_keys( $control['assets'] ), 'Una hoja declarada, ningún script.' );
	foreach ( array( 'show' => true, 'hide-effects' => true, '' => false ) as $value => $expected ) {
		check_sticky( $expected === \Elementor\Conditions::check( $assets[0]['conditions'], array( $sticky::CONTROL => $value ) ), 'La hoja con el estado «' . $value . '»: ' . ( $expected ? 'sí' : 'no' ) );
	}
	$pro_module = file_get_contents( $root . '/modules/sticky/module.php' );
	check_sticky( preg_match( "/'name' => 'e-sticky',\s*'conditions' => \[\s*'terms' => \[\s*\[\s*'name' => 'sticky',\s*'operator' => '!==',\s*'value' => '',/", $pro_module ), 'Mismo mecanismo que e-sticky de PRO.' );

	// Una sola instancia con el marcado optimizado: el núcleo dispara el gancho de common una vez por pila.
	$widget = new \Elementor\Controls_Stack( $pro_block );
	do_action( 'elementor/element/common-optimized/section_effects/before_section_end', $widget, array() );
	check_sticky( ! isset( $widget->controls[ $sticky::CONTROL ] ), 'El gancho de common-optimized no añade nada.' );
	do_action( 'elementor/element/common/section_effects/before_section_end', $widget, array() );
	do_action( 'elementor/element/common/section_effects/before_section_end', $widget, array() );
	check_sticky( 1 === count( array_keys( $widget->order, $sticky::CONTROL, true ) ), 'Una sola instancia del control aunque el gancho se repita.' );
	$section = new \Elementor\Controls_Stack( $pro_block );
	do_action( 'elementor/element/section/section_effects/before_section_end', $section, array() );
	check_sticky( isset( $section->controls[ $sticky::CONTROL ] ), 'También en secciones.' );
	$column = new \Elementor\Controls_Stack( array( 'motion_fx_motion_fx_scrolling', '_animation' ) );
	$sticky::register_controls( $column, array() );
	check_sticky( ! isset( $column->controls[ $sticky::CONTROL ] ), 'Sin el bloque de sticky (columna, PRO ausente) no se inyecta nada.' );

	// La clase: `prefix_class` + valor, como `Element_Base::add_render_attributes()`; vacío no añade nada.
	$base = file_get_contents( $root . '/elementor/includes/base/element-base.php' );
	check_sticky( false !== strpos( $base, "\$this->add_render_attribute( '_wrapper', 'class', \$controls[ \$setting_key ]['prefix_class'] . \$setting );" ) && false !== strpos( $base, "if ( empty( \$setting ) && '0' !== \$setting ) {" ), 'El núcleo pinta prefix_class desde los ajustes del propio elemento.' );
	check_sticky( false !== strpos( $base, "\$this->add_render_attribute( '_wrapper', 'data-settings', wp_json_encode( \$frontend_settings ) );" ), 'data-settings sale de wp_json_encode() de los ajustes del front.' );
	$stack_source = file_get_contents( $root . '/elementor/includes/base/controls-stack.php' );
	check_sticky( false !== strpos( $stack_source, "if ( in_array( \$setting, [ null, '' ], true ) ) {" ), 'Los ajustes vacíos no llegan a data-settings.' );
	check_sticky( preg_match( "/'sticky',\s*\[\s*'label' => esc_html__\( 'Sticky', 'elementor-pro' \),\s*'type' => Controls_Manager::SELECT,\s*'options' => \[\s*'' => [^\]]*'top' => [^\]]*'bottom' => [^\]]*\],[^\]]*'frontend_available' => true,/s", $pro_module ), 'sticky de PRO: \'\', top o bottom, y frontend_available.' );

	// La hoja.
	$css_path = $root . '/modules/digitalisimo-design/sticky/assets/sticky-state.css';
	$css      = (string) file_get_contents( $css_path );
	check_sticky( strlen( $css ) <= 512, 'La hoja pesa ' . strlen( $css ) . ' B, más de 0,5 KB.' );
	$rules = preg_replace( '#/\*.*?\*/#s', '', $css );
	check_sticky( false === stripos( $rules, 'url(' ) && false === strpos( $rules, '@' ) && false === strpos( $rules, '--e-global' ), 'Sin url(, at-rules ni variables del kit.' );
	preg_match_all( '/([^{}]+)\{([^}]*)\}/', $rules, $found, PREG_SET_ORDER );
	check_sticky( $found && trim( implode( '', array_map( function ( $m ) { return $m[0]; }, $found ) ) ) === trim( $rules ), 'La hoja sólo contiene reglas.' );
	$ancestor = ":is([data-settings*='\"sticky\":\"top\"'],[data-settings*='\"sticky\":\"bottom\"'])";
	$expected = array(
		$ancestor . ':not(.elementor-sticky--active) .digi-sticky-show',
		'.elementor-sticky--active .digi-sticky-hide',
		$ancestor . ':not(.elementor-sticky--effects) .digi-sticky-show-effects',
		'.elementor-sticky--effects .digi-sticky-hide-effects',
	);
	foreach ( $found as $rule ) {
		$selector = trim( $rule[1] );
		check_sticky( 0 === strpos( $selector, 'body:not(.elementor-editor-active) ' ), 'Cada regla empieza por body:not(.elementor-editor-active).' );
		check_sticky( 'display:none!important' === trim( $rule[2] ), 'Sólo oculta.' );
		$inner = substr( $selector, strlen( 'body:not(.elementor-editor-active) :is(' ), -1 );
		check_sticky( implode( ',', $expected ) === $inner, 'Selectores inesperados: ' . $inner );
	}
	foreach ( $sticky::STATES as $state ) {
		$class = $sticky::PREFIX . $state;
		check_sticky( 1 === preg_match_all( '/ \.' . preg_quote( $class, '/' ) . '(?![a-z-])/', $rules ), 'Un selector por estado y siempre como descendiente (nunca el propio elemento fijo): ' . $class );
	}
	check_sticky( false === strpos( $rules, '__spacer' ), 'El espaciador (clon sin --active ni --effects) queda en el estado normal sin reglas propias.' );
	// El formato real de data-settings (json_encode sin opciones, como wp_json_encode) casa con los selectores.
	foreach ( array( 'top', 'bottom' ) as $value ) {
		$json = json_encode( array( 'sticky' => $value, 'sticky_on' => array( 'desktop', 'tablet' ), 'sticky_offset' => 0, 'sticky_effects_offset' => 90 ) );
		check_sticky( false !== strpos( $json, '"sticky":"' . $value . '"' ) && false !== strpos( $ancestor, '"sticky":"' . $value . '"' ), 'data-settings con sticky ' . $value . ' coincide.' );
	}
	foreach ( array( array( 'sticky_on' => array( 'desktop' ) ), array( 'motion_fx_motion_fx_scrolling' => 'yes' ), array( 'sticky_parent' => 'yes' ) ) as $settings ) {
		$json = json_encode( $settings );
		check_sticky( false === strpos( $json, '"sticky":"top"' ) && false === strpos( $json, '"sticky":"bottom"' ), 'Sin sticky no coincide: ' . $json );
	}

	// Registro de la hoja: sólo se registra; la encola la declaración `assets`.
	do_action( 'elementor/frontend/after_register_styles' );
	$style = $GLOBALS['styles'][ $sticky::HANDLE ] ?? array();
	check_sticky( isset( $style['src'] ) && str_ends_with( $style['src'], '/modules/digitalisimo-design/sticky/assets/sticky-state.css' ) && 'test' === $style['ver'] && array() === $style['deps'], 'La hoja se registra con su ruta y la versión del plugin.' );
	check_sticky( empty( $GLOBALS['enqueued'] ), 'Nunca se encola a mano.' );
	$class_source = file_get_contents( $root . '/modules/digitalisimo-design/sticky/class-sticky-state.php' );
	check_sticky( false === strpos( $class_source, 'wp_enqueue_style(' ) && false === strpos( $class_source, 'wp_register_script(' ) && ! preg_match( '/[\'"][^\'"]*(?:before|after)_render[\'"]/', $class_source ), '0 JS y sin encolado ni pila en el código.' );

	echo "DIGITALÍSIMO Elements: sticky «al fijarse» validado (" . strlen( $css ) . " B, sin pila).\n";
}
