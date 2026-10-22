<?php
namespace Digitalisimo\Elements\Design\Sticky;

use Digitalisimo\Elements\Design\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Sticky «al fijarse» (F7a, frente K; V.6 de la especificación).
 *
 * Un elemento **dentro** de un contenedor fijo puede verse sólo mientras está fijado, o esconderse mientras lo está
 * (o, en las variantes `-effects`, desde que se pasa el «Desplazamiento de efectos»). Todo es CSS:
 *
 * - el hijo lleva su clase `digi-sticky-{estado}` por `prefix_class`, que sale de sus propios ajustes: el servidor la
 *   pinta igual desde la caché de elementos o desde un shortcode `[elementor-element]`, y el editor la aplica en vivo;
 * - la hoja reconoce al ancestro fijo por su propio `data-settings`, donde PRO imprime `"sticky":"top"` o
 *   `"sticky":"bottom"` (`sticky` es `frontend_available`), y el estado por las clases `elementor-sticky--active` y
 *   `elementor-sticky--effects` que pone el manejador de PRO.
 *
 * No hay pila de ancestros ni `before_render`, no cambia el HTML de ningún elemento existente y no hay destello: `show`
 * está oculto desde el primer pintado hasta que el ancestro se fija. El espaciador que deja PRO es un clon sin
 * `--active` ni `--effects`, así que conserva la altura del estado normal. Sin ancestro fijo no coincide ninguna regla
 * y el elemento se ve; en el editor nada se oculta. La hoja (≤ 0,5 KB) sólo llega a las páginas que usan el control,
 * por su declaración `assets`, el mismo mecanismo que PRO usa para `e-sticky`. 0 JS.
 */
final class Sticky_State {
	const CONTROL = 'digitalisimo_sticky_state';
	const HANDLE  = 'digitalisimo-sticky-state';
	const PREFIX  = 'digi-sticky-';
	const NOTE    = 'digitalisimo_sticky_state_note';
	const AFTER   = 'sticky_divider';
	const STYLE   = 'modules/digitalisimo-design/sticky/assets/sticky-state.css';

	/** Pilas donde PRO pone `sticky`. Sólo `common` para widgets: el núcleo repite sus ganchos en `common-optimized`. */
	const STACKS = array( 'section', 'container', 'common' );

	/** Estados admitidos, en el orden del selector. */
	const STATES = array( 'show', 'hide', 'show-effects', 'hide-effects' );

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		$symbols = array(
			'Elementor\Controls_Manager',
			'Elementor\Controls_Stack::start_injection',
			'Elementor\Controls_Stack::get_controls',
			'ElementorPro\Modules\Sticky\Module',
			'wp_register_style()',
			'plugins_url()',
		);
		if ( ! Module::requires( 'sticky', $symbols ) ) {
			return;
		}
		self::$initialized = true;
		foreach ( self::STACKS as $stack ) {
			add_action( "elementor/element/{$stack}/section_effects/before_section_end", array( __CLASS__, 'register_controls' ), 10, 2 );
		}
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_style' ) );
	}

	/** Control detrás del bloque de sticky de PRO (`sticky_divider`). Sin ese bloque, nada. */
	public static function register_controls( $element, $args = array() ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_controls' ) || null === $element->get_controls( self::AFTER ) || null !== $element->get_controls( self::CONTROL ) ) {
			return;
		}
		$c = '\Elementor\Controls_Manager';
		$element->start_injection( array( 'of' => self::AFTER ) );
		$element->add_control(
			self::CONTROL,
			array(
				'label'        => 'Al fijarse',
				'type'         => $c::SELECT,
				'options'      => array(
					''             => 'Siempre visible',
					'show'         => 'Sólo mientras está fijado',
					'hide'         => 'Oculto mientras está fijado',
					'show-effects' => 'Sólo tras el desplazamiento de efectos',
					'hide-effects' => 'Oculto tras el desplazamiento de efectos',
				),
				'default'      => '',
				'prefix_class' => self::PREFIX,
				'render_type'  => 'ui',
				'description'  => 'Para un elemento dentro de un contenedor fijo: se aplica cuando ese contenedor se fija, sólo fuera del editor. En los dispositivos donde no se fija, «Sólo mientras está fijado» queda oculto. Sin contenedor fijo alrededor, el elemento se ve siempre.',
				'assets'       => array(
					'styles' => array(
						array(
							'name'       => self::HANDLE,
							'conditions' => array(
								'terms' => array(
									array(
										'name'     => self::CONTROL,
										'operator' => '!==',
										'value'    => '',
									),
								),
							),
						),
					),
				),
			)
		);
		$element->add_control(
			self::NOTE,
			array(
				'type'      => $c::RAW_HTML,
				'raw'       => 'Este elemento es el que se fija: el estado sólo actúa en los elementos que contiene.',
				'condition' => array(
					'sticky!'           => '',
					self::CONTROL . '!' => '',
				),
			)
		);
		$element->end_injection();
	}

	/** Registra la hoja; la encola el cargador del núcleo sólo en las páginas que declaran el control. */
	public static function register_style(): void {
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		$file    = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? DIGITALISIMO_ELEMENTS_FILE : dirname( __DIR__, 3 ) . '/pro-elements.php';
		wp_register_style( self::HANDLE, plugins_url( self::STYLE, $file ), array(), $version );
	}
}
