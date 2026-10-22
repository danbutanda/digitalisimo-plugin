<?php
/**
 * F7a · casos compartidos de presets (`tests/sources/design/presets.json`), entre `tests/elements-design-presets.php`
 * (PHP, núcleo real) y la prueba del editor (`tests/elements-design-presets-editor.mjs`, frente D).
 *
 * Cada caso trae su entrada (`type`, `slug`, `element`, `preset`, `local`) y su salida esperada:
 * - `settings`: los ajustes del preset ya saneados (`Compiler::validated()`), los que reciben el aplanado y el editor;
 * - `fill`: lo que el preset aporta al elemento, `{ values, globals }` (`Compiler::fill()`; en JS, `flatten()`);
 * - `css`: las declaraciones que el preset añade o cambia en el CSS real del documento, por regla:
 *   `{ "[@media…|]selector": { propiedad: valor } }`. Se calcula comparando el CSS del elemento con el vínculo y sin
 *   él, así que sólo contiene lo que el preset aporta (el valor por defecto local que sustituye incluido, V-B1).
 *
 * El documento es el 10 y el selector del elemento, el del núcleo: `.elementor-10 .elementor-element.elementor-element-<id>`.
 */

use Digitalisimo\Elements\Design\Css;
use Digitalisimo\Elements\Design\Presets\Compiler;
use Digitalisimo\Elements\Design\Presets\Store;

/** Entradas de los casos (las salidas las calcula `design_presets_fixture()`). */
function design_presets_cases(): array {
	return array(
		array(
			'name'    => 'heading-typography',
			'note'    => 'Grupo de tipografía con su conmutador (R-B1): tamaño de escritorio y de tableta y grosor.',
			'type'    => 'heading',
			'slug'    => 'titulo-grande',
			'element' => array( 'id' => 'h1a', 'elType' => 'widget', 'widgetType' => 'heading' ),
			'preset'  => array(
				'typography_typography'       => 'custom',
				'typography_font_size'        => array( 'unit' => 'px', 'size' => 32, 'sizes' => array() ),
				'typography_font_size_tablet' => array( 'unit' => 'px', 'size' => 24, 'sizes' => array() ),
				'typography_font_weight'      => '700',
			),
			'local'   => array(),
		),
		array(
			'name'    => 'heading-typography-global',
			'note'    => 'Tipografía y color globales: el global vive en la clave del conmutador del grupo.',
			'type'    => 'heading',
			'slug'    => 'titulo-global',
			'element' => array( 'id' => 'h2b', 'elType' => 'widget', 'widgetType' => 'heading' ),
			'preset'  => array(
				'__globals__' => array(
					'typography_typography' => 'globals/typography?id=primary',
					'title_color'           => 'globals/colors?id=accent',
				),
			),
			'local'   => array(),
		),
		array(
			'name'    => 'container-background-classic',
			'note'    => 'Tipo de fondo (CHOOSE sin selectores, frontend_available) más su color (R-B1).',
			'type'    => 'container',
			'slug'    => 'fondo-oscuro',
			'element' => array( 'id' => 'c3c', 'elType' => 'container' ),
			'preset'  => array(
				'background_background' => 'classic',
				'background_color'      => '#112233',
			),
			'local'   => array(),
		),
		array(
			'name'    => 'container-background-gradient',
			'note'    => 'Degradado: color_b y las paradas sólo se leen como {{otro.VALUE}} desde el ángulo (V-B2).',
			'type'    => 'container',
			'slug'    => 'fondo-degradado',
			'element' => array( 'id' => 'c4d', 'elType' => 'container' ),
			'preset'  => array(
				'background_background'     => 'gradient',
				'background_color'          => '#ff0000',
				'background_color_stop'     => array( 'unit' => '%', 'size' => 10, 'sizes' => array() ),
				'background_color_b'        => '#0000ff',
				'background_color_b_stop'   => array( 'unit' => '%', 'size' => 90, 'sizes' => array() ),
				'background_gradient_angle' => array( 'unit' => 'deg', 'size' => 45, 'sizes' => array() ),
			),
			'local'   => array(),
		),
		array(
			'name'    => 'heading-box-shadow',
			'note'    => 'Sombra de caja con su conmutador (POPOVER_TOGGLE) en la pila common.',
			'type'    => 'heading',
			'slug'    => 'sombra-suave',
			'element' => array( 'id' => 'h5e', 'elType' => 'widget', 'widgetType' => 'heading' ),
			'preset'  => array(
				'_box_shadow_box_shadow_type' => 'yes',
				'_box_shadow_box_shadow'      => array( 'horizontal' => 0, 'vertical' => 4, 'blur' => 12, 'spread' => 0, 'color' => 'rgba(0,0,0,0.25)' ),
			),
			'local'   => array(),
		),
		array(
			'name'    => 'heading-padding',
			'note'    => 'Dimensiones responsivas en el contenedor interno del widget.',
			'type'    => 'heading',
			'slug'    => 'con-aire',
			'element' => array( 'id' => 'h6f', 'elType' => 'widget', 'widgetType' => 'heading' ),
			'preset'  => array(
				'_padding'        => array( 'unit' => 'px', 'top' => 10, 'right' => 20, 'bottom' => 10, 'left' => 20, 'isLinked' => false ),
				'_padding_mobile' => array( 'unit' => 'em', 'top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1, 'isLinked' => true ),
			),
			'local'   => array(),
		),
		array(
			'name'    => 'divider-weight',
			'note'    => 'El preset sustituye los valores por defecto locales del divisor (V-B1): 1px y #000.',
			'type'    => 'divider',
			'slug'    => 'linea-gruesa',
			'element' => array( 'id' => 'd7a', 'elType' => 'widget', 'widgetType' => 'divider' ),
			'preset'  => array(
				'weight' => array( 'unit' => 'px', 'size' => 6, 'sizes' => array() ),
				'color'  => '#ff0000',
			),
			'local'   => array(),
		),
		array(
			'name'    => 'heading-offset-rtl',
			'note'    => 'Selectores que empiezan antes de {{WRAPPER}} (body:not(.rtl) y body.rtl, R-I10). La posición (prefix_class) es local.',
			'type'    => 'heading',
			'slug'    => 'desplazado',
			'element' => array( 'id' => 'h8b', 'elType' => 'widget', 'widgetType' => 'heading' ),
			'preset'  => array(
				'_offset_x' => array( 'unit' => 'px', 'size' => 20, 'sizes' => array() ),
			),
			'local'   => array( '_position' => 'absolute' ),
		),
		array(
			'name'    => 'heading-local-wins',
			'note'    => 'Un control es local o del preset (R-M10): el color y el tamaño (por su variante móvil) son locales; el grosor viene del preset.',
			'type'    => 'heading',
			'slug'    => 'tarjeta',
			'element' => array( 'id' => 'h9c', 'elType' => 'widget', 'widgetType' => 'heading' ),
			'preset'  => array(
				'title_color'            => '#ff0000',
				'typography_typography'  => 'custom',
				'typography_font_size'   => array( 'unit' => 'px', 'size' => 40, 'sizes' => array() ),
				'typography_font_weight' => '800',
			),
			'local'   => array(
				'title_color'                 => '#000000',
				'typography_typography'       => 'custom',
				'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ),
			),
		),
		array(
			'name'    => 'heading-local-global-wins',
			'note'    => 'Un global local también es local: el color del preset no entra.',
			'type'    => 'heading',
			'slug'    => 'rojo',
			'element' => array( 'id' => 'h0d', 'elType' => 'widget', 'widgetType' => 'heading' ),
			'preset'  => array(
				'title_color' => '#ff0000',
			),
			'local'   => array(
				'__globals__' => array( 'title_color' => 'globals/colors?id=primary' ),
			),
		),
	);
}

/** CSS de un documento como `[ "[@media…|]selector" => [ propiedad => valor ] ]`. */
function design_declarations( string $css ): array {
	$out = array();
	foreach ( design_rules( $css ) as $rule => $body ) {
		foreach ( explode( ';', $body ) as $declaration ) {
			$pair = explode( ':', $declaration, 2 );
			if ( 2 === count( $pair ) && '' !== trim( $pair[0] ) ) {
				$out[ $rule ][ trim( $pair[0] ) ] = trim( $pair[1] );
			}
		}
	}
	return $out;
}

/**
 * Salida esperada de un caso con el núcleo real: `fill` y `css`.
 *
 * @param array $case Caso con su entrada.
 */
function design_presets_expected( array $case ): array {
	Store::reset_cache();
	Compiler::reset_cache();
	Store::replace(
		array(
			'v'       => 1,
			'presets' => array(
				$case['slug'] => array(
					'type'     => $case['type'],
					'label'    => $case['name'],
					'settings' => $case['preset'],
				),
			),
		)
	);
	$plain = $case['element'] + array( 'settings' => $case['local'], 'elements' => array() );
	$link  = $plain;
	$link['settings']['digitalisimo_preset'] = $case['slug'];
	$base  = design_declarations( design_css( array( $plain ) ) );
	$with  = design_declarations( design_css( array( $link ) ) );
	$delta = array();
	foreach ( $with as $rule => $declarations ) {
		foreach ( $declarations as $property => $value ) {
			if ( ! isset( $base[ $rule ][ $property ] ) || $base[ $rule ][ $property ] !== $value ) {
				$delta[ $rule ][ $property ] = $value;
			}
		}
	}
	$element = design_element( $plain );
	$clean   = Compiler::validated( $case['slug'] );
	$fill    = Css::with_style_controls(
		function () use ( $element, $clean, $case ) {
			return Compiler::fill( (array) $clean, $case['local'], $element->get_controls() );
		}
	);
	return array(
		'settings' => $clean ? $clean : new stdClass(),
		'fill'     => array(
			'values'  => $fill['values'] ? $fill['values'] : new stdClass(),
			'globals' => $fill['globals'] ? $fill['globals'] : new stdClass(),
		),
		'css'      => $delta ? $delta : new stdClass(),
	);
}

/** El archivo de fixtures completo a partir de las entradas. */
function design_presets_fixture( array $cases ): array {
	$out = array();
	foreach ( $cases as $case ) {
		$out[] = $case + design_presets_expected( $case );
	}
	return array(
		'description' => 'Presets de estilo (F7a): casos compartidos entre tests/elements-design-presets.php (núcleo real) y la prueba del editor. settings = ajustes del preset ya saneados; fill = lo que el preset aporta al elemento; css = declaraciones que añade o cambia en el CSS del documento 10, por regla.',
		'document'    => 10,
		'selector'    => '.elementor-{document} .elementor-element.elementor-element-{id}',
		'breakpoints' => array( 'tablet' => '@media(max-width:1024px)', 'mobile' => '@media(max-width:767px)' ),
		'cases'       => $out,
	);
}
