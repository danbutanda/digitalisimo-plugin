<?php
namespace Digitalisimo\Elements\Design\Backgrounds;

use Digitalisimo\Elements\Design\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Patrón: tesela vectorial coloreada y repetida sobre el fondo (F7a, frente G; D12 y V.5 de la especificación).
 *
 * Popover «Patrón» al final de la sección de fondo de secciones, columnas, contenedores y widgets. Se pinta en el
 * `::after` del mismo elemento que lleva el fondo del núcleo, con `isolation: isolate` en el elemento y
 * `z-index: -1` en la capa: queda sobre el fondo y la imagen, bajo la superposición (`::before`) y bajo el contenido.
 * El `::after` lleva `display: block` porque alguna hoja de PRO lo oculta en sus widgets (V-m5).
 *
 * «Elevar sobre lo siguiente» (activado por defecto, R-I13) pone el elemento en `z-index: 1`, para que un menú
 * desplegable de dentro, atrapado en el contexto de apilamiento, no quede bajo el contenedor siguiente. Nunca pisa un
 * `z-index` del usuario: en contenedores usa `--z-index` (el control del núcleo va detrás y gana) y en el resto, un
 * selector `:where()` de especificidad cero.
 */
final class Pattern {
	/** Pila → sección de fondo donde va el popover. */
	const SECTIONS = array(
		'section'   => 'section_background',
		'column'    => 'section_style',
		'container' => 'section_background',
		'common'    => '_section_background',
	);

	/** Elemento que lleva el fondo del núcleo (en widgets, `WRAPPER_SELECTOR_CHILD` de la pila). */
	const TARGETS = array(
		'section'   => '{{WRAPPER}}',
		'column'    => '{{WRAPPER}} > .elementor-widget-wrap',
		'container' => '{{WRAPPER}}',
		'common'    => '{{WRAPPER}} > .elementor-widget-container',
	);

	/** Modos de fusión admitidos. */
	const BLENDS = array(
		'normal'      => 'Normal',
		'multiply'    => 'Multiplicar',
		'screen'      => 'Trama',
		'overlay'     => 'Superponer',
		'darken'      => 'Oscurecer',
		'lighten'     => 'Aclarar',
		'color-dodge' => 'Sobreexponer color',
		'color-burn'  => 'Subexponer color',
		'soft-light'  => 'Luz suave',
		'difference'  => 'Diferencia',
		'exclusion'   => 'Exclusión',
		'luminosity'  => 'Luminosidad',
	);

	/** Repeticiones admitidas. */
	const REPEATS = array(
		'repeat' => 'Repetir',
		'space'  => 'Repetir con espacio',
		'round'  => 'Repetir ajustando',
	);

	/** @var bool */
	private static $started = false;

	/** Registra el popover y el CSS si el núcleo tiene lo que usan (V.9). */
	public static function init(): void {
		if ( self::$started ) {
			return;
		}
		$symbols = array(
			'Elementor\\Controls_Manager',
			'Elementor\\Stylesheet',
			'Elementor\\Core\\Files\\CSS\\Post',
			'Elementor\\Core\\Utils\\Svg\\Svg_Sanitizer',
			'Elementor\\Controls_Stack::start_popover',
		);
		if ( ! Module::requires( 'patrones', $symbols ) ) {
			return;
		}
		self::$started = true;
		foreach ( self::SECTIONS as $stack => $section ) {
			add_action( "elementor/element/{$stack}/{$section}/before_section_end", array( __CLASS__, 'controls' ), 10, 2 );
		}
		add_action( 'elementor/element/parse_css', array( __CLASS__, 'parse_css' ), 10, 2 );
		Library::init();
	}

	/** Selector del elemento que lleva el fondo en una pila. */
	public static function target( $element ): string {
		$name = is_object( $element ) && method_exists( $element, 'get_name' ) ? (string) $element->get_name() : '';
		if ( isset( self::TARGETS[ $name ] ) && 'common' !== $name ) {
			return self::TARGETS[ $name ];
		}
		$constant = is_object( $element ) ? get_class( $element ) . '::WRAPPER_SELECTOR_CHILD' : '';
		return '' !== $constant && defined( $constant ) ? (string) constant( $constant ) : self::TARGETS['common'];
	}

	/**
	 * Popover «Patrón» al final de la sección de fondo.
	 *
	 * @param \Elementor\Controls_Stack $element Sección, columna, contenedor o `common`/`common-optimized`.
	 */
	public static function controls( $element ): void {
		if ( ! is_object( $element ) || null !== $element->get_controls( 'digitalisimo_pattern' ) ) {
			return;
		}
		$name = method_exists( $element, 'get_name' ) ? (string) $element->get_name() : '';
		if ( ! isset( self::SECTIONS[ $name ] ) && 'common-optimized' !== $name ) {
			return;
		}
		$c     = '\\Elementor\\Controls_Manager';
		$t     = self::target( $element );
		$after = $t . '::after';
		$on    = array( 'digitalisimo_pattern' => 'yes' );

		$element->add_control(
			'digitalisimo_pattern',
			array(
				'label'        => 'Patrón',
				'type'         => $c::POPOVER_TOGGLE,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
				'selectors'    => array(
					$t                => 'isolation: isolate;',
					':where(' . $t . ')' => 'position: relative;',
					$after            => 'content: ""; display: block; position: absolute; inset: 0; z-index: -1; pointer-events: none; border-radius: inherit; background-color: currentColor; -webkit-mask-position: var(--digi-pattern-x, 0px) var(--digi-pattern-y, 0px); mask-position: var(--digi-pattern-x, 0px) var(--digi-pattern-y, 0px);',
				),
			)
		);
		$element->start_popover();
		$element->add_control(
			'digitalisimo_pattern_tile',
			array(
				'label'                => 'Tesela',
				'type'                 => $c::SELECT,
				'options'              => Library::options( 'patterns' ),
				'default'              => 'puntos',
				'selectors_dictionary' => Library::dictionary( 'patterns' ),
				'selectors'            => array( $after => '-webkit-mask-image: {{VALUE}}; mask-image: {{VALUE}};' ),
				'condition'            => $on,
			)
		);
		$element->add_control(
			'digitalisimo_pattern_media',
			array(
				'label'       => 'Archivo SVG',
				'type'        => $c::MEDIA,
				'media_types' => array( 'svg' ),
				'dynamic'     => array( 'active' => false ),
				'description' => 'Un SVG de la biblioteca de medios de este sitio, de hasta 8 KB. Al guardar se copia saneado.',
				'condition'   => $on + array( 'digitalisimo_pattern_tile' => Library::MEDIA ),
			)
		);
		$element->add_control(
			'digitalisimo_pattern_file',
			array(
				'type'    => $c::HIDDEN,
				'default' => '',
			)
		);
		$element->add_control(
			'digitalisimo_pattern_color',
			array(
				'label'     => 'Color',
				'type'      => $c::COLOR,
				'default'   => '',
				'selectors' => array( $after => 'background-color: {{VALUE}};' ),
				'condition' => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_pattern_size',
			array(
				'label'      => 'Tamaño de la tesela',
				'type'       => $c::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 4,
						'max' => 400,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 24,
				),
				'selectors'  => array( $after => '-webkit-mask-size: {{SIZE}}{{UNIT}}; mask-size: {{SIZE}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_control(
			'digitalisimo_pattern_repeat',
			array(
				'label'                => 'Repetición',
				'type'                 => $c::SELECT,
				'options'              => self::REPEATS,
				'default'              => 'repeat',
				'selectors_dictionary' => array_combine( array_keys( self::REPEATS ), array_keys( self::REPEATS ) ),
				'selectors'            => array( $after => '-webkit-mask-repeat: {{VALUE}}; mask-repeat: {{VALUE}};' ),
				'condition'            => $on,
			)
		);
		foreach ( array(
			'x' => 'Desplazamiento horizontal',
			'y' => 'Desplazamiento vertical',
		) as $axis => $label ) {
			$element->add_responsive_control(
				'digitalisimo_pattern_offset_' . $axis,
				array(
					'label'      => $label,
					'type'       => $c::SLIDER,
					'size_units' => array( 'px', '%' ),
					'range'      => array(
						'px' => array(
							'min' => -200,
							'max' => 200,
						),
						'%'  => array(
							'min' => -100,
							'max' => 100,
						),
					),
					'selectors'  => array( $after => '--digi-pattern-' . $axis . ': {{SIZE}}{{UNIT}};' ),
					'condition'  => $on,
				)
			);
		}
		$element->add_control(
			'digitalisimo_pattern_opacity',
			array(
				'label'     => 'Opacidad',
				'type'      => $c::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 1,
						'step' => 0.01,
					),
				),
				'default'   => array(
					'unit' => 'px',
					'size' => 0.2,
				),
				'selectors' => array( $after => 'opacity: {{SIZE}};' ),
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_pattern_blend',
			array(
				'label'                => 'Fusión',
				'type'                 => $c::SELECT,
				'options'              => self::BLENDS,
				'default'              => 'normal',
				'selectors_dictionary' => array_combine( array_keys( self::BLENDS ), array_keys( self::BLENDS ) ),
				'selectors'            => array( $after => 'mix-blend-mode: {{VALUE}};' ),
				'condition'            => $on,
			)
		);
		$element->add_control(
			'digitalisimo_pattern_flip',
			array(
				'label'                => 'Voltear',
				'type'                 => $c::SELECT,
				'options'              => array(
					''           => 'No',
					'horizontal' => 'En horizontal',
					'vertical'   => 'En vertical',
				),
				'default'              => '',
				'selectors_dictionary' => array(
					''           => '',
					'horizontal' => 'scale(-1, 1)',
					'vertical'   => 'scale(1, -1)',
				),
				'selectors'            => array( $after => 'transform: {{VALUE}};' ),
				'condition'            => $on,
			)
		);
		$element->add_control(
			'digitalisimo_pattern_raise',
			array(
				'label'        => 'Elevar sobre lo siguiente',
				'type'         => $c::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => 'Pone el elemento por encima del contenido que le sigue, para que un menú desplegable de dentro no quede debajo. Un índice Z propio del elemento manda sobre éste.',
				'selectors'    => 'container' === $name
					? array( '{{WRAPPER}}' => '--z-index: 1;' )
					: array( ':where(' . $t . ')' => 'z-index: 1;' ),
				'condition'    => $on,
			)
		);
		$element->end_popover();
	}

	/**
	 * `elementor/element/parse_css`: la variable de la tesela (o la copia del SVG del sitio) en la hoja del documento.
	 *
	 * @param \Elementor\Core\Files\CSS\Post $post_css Archivo CSS.
	 * @param \Elementor\Element_Base         $element  Elemento.
	 */
	public static function parse_css( $post_css, $element ): void {
		if ( ! Library::is_post_css( $post_css ) || ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) ) {
			return;
		}
		if ( null === $element->get_controls( 'digitalisimo_pattern_tile' ) ) {
			return;
		}
		$settings = $element->get_settings();
		if ( ! is_array( $settings ) || 'yes' !== ( isset( $settings['digitalisimo_pattern'] ) ? $settings['digitalisimo_pattern'] : '' ) ) {
			return;
		}
		Library::define( $post_css, $element, 'patterns', 'digitalisimo_pattern_tile', 'digitalisimo_pattern_file' );
	}
}
