<?php
namespace Digitalisimo\Elements\Design\Backgrounds;

use Digitalisimo\Elements\Design\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Máscara de recorte en contenedores, secciones y columnas (F7a, frente G; D11 y V.5 de la especificación).
 *
 * Sección «Máscara» en la pestaña Avanzado, detrás de «Efectos». Los widgets conservan la máscara del núcleo, sin
 * cambios. La forma sale de la biblioteca propia (`Library`, por variable del documento) o de un SVG de Medios del
 * sitio, copiado saneado al guardar. Todo es CSS del documento: sin archivos, sin URL y sin JS en el front.
 *
 * La columna recorta `> .elementor-widget-wrap`, donde el núcleo pinta su fondo.
 */
final class Mask {
	/** Selector del recorte por elemento. */
	const TARGETS = array(
		'section'   => '{{WRAPPER}}',
		'column'    => '{{WRAPPER}} > .elementor-widget-wrap',
		'container' => '{{WRAPPER}}',
	);

	/** Repeticiones admitidas (valor = valor CSS). */
	const REPEATS = array(
		'no-repeat' => 'Sin repetir',
		'repeat'    => 'Repetir',
		'repeat-x'  => 'Repetir en horizontal',
		'repeat-y'  => 'Repetir en vertical',
		'space'     => 'Repetir con espacio',
		'round'     => 'Repetir ajustando',
	);

	/** @var bool */
	private static $started = false;

	/** Registra la sección y el CSS si el núcleo tiene lo que usan (V.9). */
	public static function init(): void {
		if ( self::$started ) {
			return;
		}
		$symbols = array(
			'Elementor\\Controls_Manager',
			'Elementor\\Stylesheet',
			'Elementor\\Core\\Files\\CSS\\Post',
			'Elementor\\Core\\Utils\\Svg\\Svg_Sanitizer',
		);
		if ( ! Module::requires( 'máscaras', $symbols ) ) {
			return;
		}
		self::$started = true;
		foreach ( array_keys( self::TARGETS ) as $type ) {
			add_action( "elementor/element/{$type}/section_effects/after_section_end", array( __CLASS__, 'controls' ), 10, 2 );
		}
		add_action( 'elementor/element/parse_css', array( __CLASS__, 'parse_css' ), 10, 2 );
		Library::init();
	}

	/**
	 * Sección «Máscara».
	 *
	 * @param \Elementor\Controls_Stack $element Sección, columna o contenedor.
	 */
	public static function controls( $element ): void {
		$type = is_object( $element ) && method_exists( $element, 'get_name' ) ? (string) $element->get_name() : '';
		if ( ! isset( self::TARGETS[ $type ] ) || null !== $element->get_controls( 'digitalisimo_mask' ) ) {
			return;
		}
		$c   = '\\Elementor\\Controls_Manager';
		$t   = self::TARGETS[ $type ];
		$on  = array( 'digitalisimo_mask' => 'yes' );
		$img = '-webkit-mask-image: {{VALUE}}; mask-image: {{VALUE}};';

		$element->start_controls_section(
			'digitalisimo_section_mask',
			array(
				'label' => 'Máscara',
				'tab'   => $c::TAB_ADVANCED,
			)
		);
		$element->add_control(
			'digitalisimo_mask',
			array(
				'label'        => 'Máscara de recorte',
				'type'         => $c::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => 'Recorta el elemento con la forma elegida. La máscara recorta todo su contenido, también los menús desplegables.',
			)
		);
		$element->add_control(
			'digitalisimo_mask_shape',
			array(
				'label'                => 'Forma',
				'type'                 => $c::SELECT,
				'options'              => Library::options( 'masks' ),
				'default'              => 'circulo',
				'selectors_dictionary' => Library::dictionary( 'masks' ),
				'selectors'            => array( $t => $img ),
				'condition'            => $on,
			)
		);
		$element->add_control(
			'digitalisimo_mask_media',
			array(
				'label'       => 'Archivo SVG',
				'type'        => $c::MEDIA,
				'media_types' => array( 'svg' ),
				'dynamic'     => array( 'active' => false ),
				'description' => 'Un SVG de la biblioteca de medios de este sitio, de hasta 8 KB. Al guardar se copia saneado; si no lo supera, el elemento se ve sin recortar.',
				'condition'   => $on + array( 'digitalisimo_mask_shape' => Library::MEDIA ),
			)
		);
		$element->add_control(
			'digitalisimo_mask_file',
			array(
				'type'    => $c::HIDDEN,
				'default' => '',
			)
		);
		$element->add_control(
			'digitalisimo_mask_size',
			array(
				'label'                => 'Tamaño',
				'type'                 => $c::SELECT,
				'options'              => array(
					'contain' => 'Contener',
					'cover'   => 'Cubrir',
					'custom'  => 'A medida',
				),
				'default'              => 'contain',
				'selectors_dictionary' => array(
					'contain' => 'contain',
					'cover'   => 'cover',
					'custom'  => '',
				),
				'selectors'            => array( $t => '-webkit-mask-size: {{VALUE}}; mask-size: {{VALUE}};' ),
				'condition'            => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_mask_scale',
			array(
				'label'      => 'Escala',
				'type'       => $c::SLIDER,
				'size_units' => array( '%', 'px' ),
				'range'      => array(
					'%'  => array(
						'min' => 1,
						'max' => 200,
					),
					'px' => array(
						'min' => 1,
						'max' => 2000,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 100,
				),
				'selectors'  => array( $t => '-webkit-mask-size: {{SIZE}}{{UNIT}}; mask-size: {{SIZE}}{{UNIT}};' ),
				'condition'  => $on + array( 'digitalisimo_mask_size' => 'custom' ),
			)
		);
		$element->add_control(
			'digitalisimo_mask_position',
			array(
				'label'                => 'Posición',
				'type'                 => $c::SELECT,
				'options'              => Gradient::POSITION_LABELS + array( 'custom' => 'A medida' ),
				'default'              => 'center center',
				'selectors_dictionary' => array_combine( Gradient::POSITIONS, Gradient::POSITIONS ) + array( 'custom' => 'var(--digi-mask-x, 50%) var(--digi-mask-y, 50%)' ),
				'selectors'            => array( $t => '-webkit-mask-position: {{VALUE}}; mask-position: {{VALUE}};' ),
				'condition'            => $on,
			)
		);
		foreach ( array(
			'x' => 'Posición horizontal',
			'y' => 'Posición vertical',
		) as $axis => $label ) {
			$element->add_responsive_control(
				'digitalisimo_mask_' . $axis,
				array(
					'label'      => $label,
					'type'       => $c::SLIDER,
					'size_units' => array( '%', 'px' ),
					'range'      => array(
						'%'  => array(
							'min' => -100,
							'max' => 200,
						),
						'px' => array(
							'min' => -1000,
							'max' => 1000,
						),
					),
					'default'    => array(
						'unit' => '%',
						'size' => 50,
					),
					'selectors'  => array( $t => '--digi-mask-' . $axis . ': {{SIZE}}{{UNIT}};' ),
					'condition'  => $on + array( 'digitalisimo_mask_position' => 'custom' ),
				)
			);
		}
		$element->add_control(
			'digitalisimo_mask_repeat',
			array(
				'label'                => 'Repetición',
				'type'                 => $c::SELECT,
				'options'              => self::REPEATS,
				'default'              => 'no-repeat',
				'selectors_dictionary' => array_combine( array_keys( self::REPEATS ), array_keys( self::REPEATS ) ),
				'selectors'            => array( $t => '-webkit-mask-repeat: {{VALUE}}; mask-repeat: {{VALUE}};' ),
				'condition'            => $on,
			)
		);
		$element->end_controls_section();
	}

	/**
	 * `elementor/element/parse_css`: la variable de la forma (o la copia del SVG del sitio) en la hoja del documento.
	 *
	 * @param \Elementor\Core\Files\CSS\Post $post_css Archivo CSS.
	 * @param \Elementor\Element_Base         $element  Elemento.
	 */
	public static function parse_css( $post_css, $element ): void {
		if ( ! Library::is_post_css( $post_css ) || ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) ) {
			return;
		}
		if ( ! isset( self::TARGETS[ (string) $element->get_type() ] ) || null === $element->get_controls( 'digitalisimo_mask_shape' ) ) {
			return;
		}
		$settings = $element->get_settings();
		if ( ! is_array( $settings ) || 'yes' !== ( isset( $settings['digitalisimo_mask'] ) ? $settings['digitalisimo_mask'] : '' ) ) {
			return;
		}
		Library::define( $post_css, $element, 'masks', 'digitalisimo_mask_shape', 'digitalisimo_mask_file' );
	}
}
