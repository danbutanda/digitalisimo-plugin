<?php
/**
 * Degradados de N paradas de F7a (frente F, §3.2 y V.3 de la especificación).
 *
 * - Guardas: sin un símbolo del núcleo no registra nada; `Repeater`, que el núcleo declara en `init`, se comprueba al
 *   añadir los controles; con todo, registra sólo la pila `common` en widgets (V-I1) y una instancia de cada control
 *   con el marcado optimizado y sin él.
 * - Controles: inyectados tras `P.gradient_position` del grupo de fondo real del núcleo, en cada prefijo de D10, con
 *   el cónico propio (`digi_conic_from`, `digi_conic_at`; V-I12) y valores por defecto que no imprimen nada.
 * - Construcción: `rules()` da exactamente lo esperado en todas las fixtures de `tests/sources/design/gradients.json`
 *   (las mismas que comprueba `elements-design-gradient.mjs` en el editor).
 * - CSS: las reglas pasan por el `Stylesheet` real (V-B3), en los selectores reales del núcleo y en sus consultas;
 *   sustituyen la propiedad del núcleo sin duplicarla, y sin controles nuevos la hoja queda byte a byte igual.
 */

require __DIR__ . '/sources/design/backgrounds-doubles.php';

use Digitalisimo\Elements\Design\Backgrounds\Gradient;

bg_breakpoints(
	array(
		'mobile' => array( 767, 'max' ),
		'tablet' => array( 1024, 'max' ),
	)
);

// ── Guardas ─────────────────────────────────────────────────────────────────────────────────────────────────────
Gradient::start( array_merge( Gradient::SYMBOLS, array( 'Elementor\\Simbolo_Ausente_Del_Nucleo' ) ) );
bg_assert( 0 === bg_hook_count( 'elementor/element/parse_css' ), 'Sin un símbolo del núcleo, los degradados no deben registrar nada.' );
bg_assert( isset( \Digitalisimo\Elements\Design\Module::missing()['degradados'] ), 'La guarda debe anotar el símbolo que falta para el aviso del editor.' );
bg_assert( ! in_array( 'Elementor\\Repeater', Gradient::SYMBOLS, true ), 'Repeater no entra en la guarda: el núcleo la declara en init, después de plugins_loaded.' );

// Sin `Repeater` todavía (en un sitio real, en `plugins_loaded`) la guarda pasa; `controls()` no añade nada hasta
// que el núcleo la declara.
Gradient::init();
$early = bg_stack( '\\Elementor\\Element_Base', 'section', 'section', 'section_background', 'background', array( 'types' => array( 'classic', 'gradient' ) ), array( 'elementor/element/section/section_background/before_section_end' ) );
bg_assert( null === $early->get_controls( 'background_digi_kind' ), 'Sin Repeater, controls() no añade controles.' );

bg_declare_late();
Gradient::init();
Gradient::init();
bg_assert( 1 === bg_hook_count( 'elementor/element/parse_css' ), 'parse_css debe engancharse una sola vez.' );
foreach ( array( 'section/section_background', 'section/section_background_overlay', 'column/section_style', 'column/section_background_overlay', 'container/section_background', 'container/section_background_overlay', 'common/_section_background' ) as $place ) {
	bg_assert( 1 === bg_hook_count( 'elementor/element/' . $place . '/before_section_end' ), 'Falta el gancho de ' . $place );
}
foreach ( array_keys( $GLOBALS['bg_hooks'] ) as $hook ) {
	bg_assert( false === strpos( $hook, 'common-optimized' ), 'Nunca se engancha common-optimized: el núcleo repite los ganchos de common (V-I1).' );
}
bg_assert( 1 === bg_callback_count( 'elementor/editor/after_enqueue_scripts', array( Gradient::class, 'editor_script' ) ), 'El script del editor se encola en el editor, una vez.' );
foreach ( array( 'wp_enqueue_scripts', 'elementor/frontend/after_enqueue_scripts', 'elementor/frontend/after_register_scripts' ) as $hook ) {
	bg_assert( 0 === bg_hook_count( $hook ), 'Nada de los degradados en el front: ' . $hook );
}

// ── Controles sobre el grupo real del núcleo ────────────────────────────────────────────────────────────────────

/** Pila con el grupo de fondo del núcleo en una sección, y el gancho disparado como lo hace el núcleo. */
function bg_stack( string $class, string $name, string $type, string $section, string $group, array $group_args, array $hooks ) {
	$stack = new $class( $name, $type );
	$stack->start_controls_section( $section, array( 'tab' => 'style' ) );
	$stack->add_group_control( 'background', array( 'name' => $group ) + $group_args );
	foreach ( $hooks as $hook ) {
		do_action( $hook, $stack, array() );
	}
	$stack->end_controls_section();
	return $stack;
}

$section = bg_stack( '\\Elementor\\Element_Base', 'section', 'section', 'section_background', 'background', array( 'types' => array( 'classic', 'gradient', 'video', 'slideshow' ) ), array( 'elementor/element/section/section_background/before_section_end' ) );
$keys    = array_keys( $section->controls );
$at      = array_search( 'background_gradient_position', $keys, true );
bg_assert( false !== $at && 'background_digi_kind' === $keys[ $at + 1 ], 'Los controles van justo detrás de gradient_position del núcleo.' );
foreach ( array( 'digi_kind', 'digi_conic_from', 'digi_conic_from_tablet', 'digi_conic_from_mobile', 'digi_conic_at', 'digi_repeat', 'digi_stops' ) as $name ) {
	$control = $section->get_controls( 'background_' . $name );
	bg_assert( null !== $control, 'Falta background_' . $name );
	bg_assert( 'section_background' === $control['section'], 'background_' . $name . ' debe quedar en su sección.' );
	bg_assert( array( 'gradient' ) === $control['condition']['background_background'], 'background_' . $name . ' sólo con fondo degradado.' );
	bg_assert( 'ui' === $control['render_type'], 'background_' . $name . ' repinta sólo los estilos.' );
	bg_assert( empty( $control['selectors'] ), 'El CSS lo escribe parse_css, no un selector suelto: background_' . $name );
}
bg_assert( 'conic' === $section->get_controls( 'background_digi_conic_from' )['condition']['background_digi_kind'], 'El ángulo cónico es propio (V-I12).' );
bg_assert( 'conic' === $section->get_controls( 'background_digi_conic_at' )['condition']['background_digi_kind'], 'El centro cónico es propio (V-I12).' );
bg_assert( Gradient::POSITIONS === array_keys( $section->get_controls( 'background_digi_conic_at' )['options'] ), 'El centro cónico ofrece las 9 posiciones del núcleo.' );
bg_assert( '' === $section->get_controls( 'background_digi_kind' )['default'] && '' === $section->get_controls( 'background_digi_repeat' )['default'] && array() === $section->get_controls( 'background_digi_stops' )['default'], 'Valores por defecto que no imprimen nada.' );
$fields = $section->get_controls( 'background_digi_stops' )['fields'];
bg_assert( isset( $fields['color'], $fields['position'], $fields['position_tablet'], $fields['position_mobile'] ), 'Cada parada tiene color y posición responsiva.' );
bg_assert( array( '%' ) === $fields['position']['size_units'], 'La posición de una parada es en %.' );
bg_assert( array() === $section->redeclared, 'Ningún control se declara dos veces.' );

// Hover en su pestaña del núcleo: los controles heredan la sección del de anclaje.
$hover = bg_stack( '\\Elementor\\Element_Base', 'section', 'section', 'section_background', 'background_hover', array(), array( 'elementor/element/section/section_background/before_section_end' ) );
bg_assert( null !== $hover->get_controls( 'background_hover_digi_stops' ), 'El hover recibe sus controles.' );

// Widgets: una instancia con el marcado optimizado (el núcleo dispara common-optimized y repite common) y otra sin él.
$common    = bg_stack( '\\Elementor\\BG_Common', 'common', '', '_section_background', '_background', array( 'selector' => \Elementor\BG_Common::WRAPPER_SELECTOR_CHILD ), array( 'elementor/element/common/_section_background/before_section_end' ) );
$optimized = bg_stack( '\\Elementor\\BG_Common_Optimized', 'common-optimized', '', '_section_background', '_background', array( 'selector' => \Elementor\BG_Common_Optimized::WRAPPER_SELECTOR_CHILD ), array( 'elementor/element/common-optimized/_section_background/before_section_end', 'elementor/element/common/_section_background/before_section_end' ) );
foreach ( array( 'sin marcado optimizado' => $common, 'con marcado optimizado' => $optimized ) as $label => $stack ) {
	$count = count( preg_grep( '/^_background_digi_kind$/', array_keys( $stack->controls ) ) );
	bg_assert( 1 === $count && array() === $stack->redeclared, 'Una sola instancia de los controles ' . $label );
}

// ── Construcción: fixtures compartidas con el editor ────────────────────────────────────────────────────────────
$fixtures = json_decode( (string) file_get_contents( __DIR__ . '/sources/design/gradients.json' ), true );
bg_assert( is_array( $fixtures ) && count( $fixtures['cases'] ) >= 20, 'Faltan las fixtures de degradados.' );
bg_assert( json_decode( (string) json_encode( Gradient::editor_config() ), true ) === $fixtures['config'], 'La configuración del editor debe ser la de las fixtures (su única fuente es PHP).' );
foreach ( $fixtures['cases'] as $case ) {
	$got = Gradient::rules( $case['settings'], $case['prefix'], $case['devices'] );
	bg_assert( $got === $case['expected'], 'Degradado «' . $case['name'] . '»: ' . json_encode( $got, JSON_UNESCAPED_SLASHES ) . ' ≠ ' . json_encode( $case['expected'], JSON_UNESCAPED_SLASHES ) );
	foreach ( $got as $value ) {
		bg_assert( false === strpos( $value, ';' ) && false === strpos( $value, '{' ) && false === stripos( $value, 'url(' ), 'Un valor nunca rompe la regla: ' . $value );
	}
}
bg_assert( null === Gradient::build( array( 'background_background' => 'gradient' ), 'background_' ), 'Sin nada propio, build() da null.' );
bg_assert( Gradient::devices() === array( 'desktop', 'tablet', 'mobile' ), 'Dispositivos de mayor a menor, como el núcleo.' );

// ── CSS por el Stylesheet real ──────────────────────────────────────────────────────────────────────────────────

/** Contenedor con el grupo real, sus ajustes, la regla del núcleo y la de F7a en la misma hoja. */
function bg_css( array $settings, string $group = 'background', string $class = '\\Elementor\\Element_Base', string $name = 'container', string $type = 'container', array $args = array() ) {
	$hook    = 'common' === $name || 'common-optimized' === $name ? 'elementor/element/common/_section_background/before_section_end' : 'elementor/element/' . $name . '/section_background/before_section_end';
	$section = 'common' === $name || 'common-optimized' === $name ? '_section_background' : 'section_background';
	$element = bg_stack( $class, $name, $type, $section, $group, $args, array( $hook ) );
	$element->data = $settings;
	$css = new \Elementor\Core\Files\CSS\Post( 42 );
	$all = $element->get_settings();
	foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
		if ( 'radial' === $all[ $group . '_gradient_type' ] ) {
			bg_core_rule( $css, $element, $group . '_gradient_position' . $suffix, $all );
		} else {
			bg_core_rule( $css, $element, $group . '_gradient_angle' . $suffix, $all );
		}
	}
	$before = (string) $css->get_stylesheet();
	do_action( 'elementor/element/parse_css', $css, $element );
	return array( $before, (string) $css->get_stylesheet(), $css, $element );
}

$core = array(
	'background_background'    => 'gradient',
	'background_color'         => '#ff0000',
	'background_color_b'       => '#0000ff',
	'background_gradient_type' => 'linear',
);
list( $before, $after ) = bg_css( $core );
bg_assert( '' !== $before && false !== strpos( $before, 'linear-gradient(180deg, #ff0000 0%, #0000ff 100%)' ), 'El doble del núcleo debe escribir su degradado de dos paradas.' );
bg_assert( $before === $after, 'Sin controles nuevos, la hoja es byte a byte la del núcleo.' );

list( $before, $after ) = bg_css(
	$core + array(
		'background_gradient_angle_tablet' => array( 'unit' => 'deg', 'size' => 90 ),
		'background_color_stop_tablet'     => array( 'unit' => '%', 'size' => 10 ),
		'background_color_b_stop_tablet'   => array( 'unit' => '%', 'size' => 90 ),
		'background_digi_stops'            => array(
			array(
				'_id'      => 'a',
				'color'    => '#00ff00',
				'position' => array( 'unit' => '%', 'size' => 50 ),
			),
		),
	)
);
$selector = '.elementor-42 .elementor-element.elementor-element-abc1234:not(.elementor-motion-effects-element-type-background), .elementor-42 .elementor-element.elementor-element-abc1234 > .elementor-motion-effects-container > .elementor-motion-effects-layer';
bg_assert( false !== strpos( $before, '@media(max-width:1024px){' . $selector . '{background-color:transparent;background-image:linear-gradient(90deg, #ff0000 10%, #0000ff 90%);}}' ), 'El doble del núcleo escribe su regla de tableta: ' . $before );
bg_assert( 0 === strpos( $after, $selector . '{background-color:transparent;background-image:linear-gradient(180deg, #ff0000 0%, #00ff00 50%, #0000ff 100%);}' ), 'La regla de escritorio sustituye a la del núcleo, en su selector real: ' . $after );
bg_assert( false !== strpos( $after, '@media(max-width:1024px){' . $selector . '{background-color:transparent;background-image:linear-gradient(90deg, #ff0000 10%, #00ff00 50%, #0000ff 90%);}}' ), 'La de tableta sustituye a la del núcleo en su consulta: ' . $after );
bg_assert( 2 === substr_count( $after, 'background-image:' ), 'Una sola background-image por selector y consulta: ' . $after );
bg_assert( false === strpos( $after, '#ff0000 0%, #0000ff 100%' ), 'No queda el degradado de dos paradas.' );

// Con la capa de efectos de movimiento ya en el selector del núcleo y hover con el suyo propio.
list( , $after ) = bg_css(
	array(
		'background_hover_background' => 'gradient',
		'background_hover_color'      => '#ffffff',
		'background_hover_color_b'    => '#000000',
		'background_hover_digi_kind'  => 'conic',
	),
	'background_hover',
	'\\Elementor\\Element_Base',
	'container',
	'container',
	array( 'selector' => '{{WRAPPER}}:hover' )
);
bg_assert( false !== strpos( $after, '.elementor-42 .elementor-element.elementor-element-abc1234:hover{background-color:transparent;background-image:conic-gradient(from 0deg at center center, #ffffff 0%, #000000 100%);}' ), 'El hover usa el selector de su grupo: ' . $after );

// Widgets: el selector del núcleo con y sin marcado optimizado.
foreach ( array(
	'\\Elementor\\BG_Common'           => '.elementor-42 .elementor-element.elementor-element-abc1234 > .elementor-widget-container{',
	'\\Elementor\\BG_Common_Optimized' => '.elementor-42 .elementor-element.elementor-element-abc1234{',
) as $class => $prefix ) {
	list( , $after ) = bg_css(
		array(
			'_background_background' => 'gradient',
			'_background_color'      => '#ff0000',
			'_background_color_b'    => '#0000ff',
			'_background_digi_repeat' => 'yes',
		),
		'_background',
		$class,
		$class === '\\Elementor\\BG_Common' ? 'common' : 'common-optimized',
		'widget',
		array( 'selector' => constant( ltrim( $class, '\\' ) . '::WRAPPER_SELECTOR_CHILD' ) )
	);
	bg_assert( 0 === strpos( $after, $prefix . 'background-color:transparent;background-image:repeating-linear-gradient(180deg, #ff0000 0%, #0000ff 100%);}' ), 'Widget ' . $class . ': ' . $after );
}

// El CSS dinámico no repite las reglas.
$element = bg_stack( '\\Elementor\\Element_Base', 'container', 'container', 'section_background', 'background', array(), array( 'elementor/element/container/section_background/before_section_end' ) );
$element->data = $core + array( 'background_digi_repeat' => 'yes' );
$dynamic = new \Elementor\Core\DynamicTags\Dynamic_CSS( 42 );
do_action( 'elementor/element/parse_css', $dynamic, $element );
bg_assert( '' === (string) $dynamic->get_stylesheet(), 'El archivo dinámico no recibe degradados.' );

// ── Script del editor ───────────────────────────────────────────────────────────────────────────────────────────
$script = BG_ROOT . Gradient::SCRIPT;
bg_assert( is_file( $script ) && filesize( $script ) <= 8192, 'design-editor.js debe existir y pesar 8 KB o menos.' );
bg_assert( 1 === substr_count( (string) file_get_contents( $script ), "addFilter('editor/style/styleText'" ), 'Un solo filtro editor/style/styleText.' );

echo "DIGITALÍSIMO Elements: degradados de N paradas (" . count( $fixtures['cases'] ) . " fixtures, Stylesheet real, common sin duplicar) validados.\n";
