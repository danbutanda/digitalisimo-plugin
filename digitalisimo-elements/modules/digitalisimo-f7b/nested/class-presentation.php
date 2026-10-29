<?php
namespace Digitalisimo\Elements\F7b\Nested;

use Digitalisimo\Elements\F7b\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Presentación de pestañas y acordeón anidados (F7b, C6; D16, §3.5 y V.7; frente G).
 *
 * - **Control** `digitalisimo_presentation` en `nested-tabs` y `nested-accordion` del núcleo, en una sección de Estilo
 *   propia detrás de la primera de estilo del widget: `section_tabs_style` («Pestañas»,
 *   `elementor/modules/nested-tabs/widgets/nested-tabs.php:448`) y su equivalente del acordeón,
 *   `section_accordion_style` («Acordeón», `elementor/modules/nested-accordion/widgets/nested-accordion.php:399`).
 *   Valores: «Ninguno» (vacío, el valor que el control tiene sin `default`), `pills`, `underline`, `boxed`, `minimal`;
 *   un `none` guardado por otra vía equivale a «Ninguno» (`classes_dictionary`).
 * - **Clase** `digi-nested-<estilo>` en el envoltorio del widget con `prefix_class`: el núcleo la pinta desde los
 *   ajustes del propio elemento (`elementor/includes/base/element-base.php:806-824`, también desde la caché de
 *   elementos) y el editor la cambia en vivo (`render_type` `ui`). Sin estilo elegido no sale ninguna clase.
 * - **Hoja** `assets/css/nested-presentation.css` (≤ 4 KB, objetivo 2,5 KB), declarada en `assets` del control con
 *   su condición: sólo llega a las páginas que eligen un estilo. En la vista previa del editor se encola siempre.
 *   Los controles de Estilo del usuario ganan siempre: las variables del núcleo se fijan en el envoltorio con
 *   especificidad 0,2,0 (más que sus valores por defecto, `.elementor-widget-n-tabs` 0,1,0, y menos que `{{WRAPPER}}`,
 *   0,3,0) y todo lo demás va dentro de `:where()`; indicadores con `currentColor`, sin paleta propia.
 * - Sin `selectors` ni `default`: un widget existente genera el mismo HTML y el mismo CSS que en 4.3.0.168. Es un control
 *   de estilo (`style_transfer` verdadero): «Pegar estilo» y «Aplicar a todos» lo copian.
 */
final class Presentation {
	const CONTROL = 'digitalisimo_presentation';
	const SECTION = 'digitalisimo_presentation_section';
	const HANDLE  = 'digitalisimo-f7b-nested-presentation';
	const PREFIX  = 'digi-nested-';
	const STYLE   = 'modules/digitalisimo-f7b/assets/css/nested-presentation.css';

	/** Estilos propios, en el orden del selector. */
	const STYLES = array( 'pills', 'underline', 'boxed', 'minimal' );

	/** Widget => sección de estilo tras la que va «Presentación». */
	const TARGETS = array(
		'nested-tabs'      => 'section_tabs_style',
		'nested-accordion' => 'section_accordion_style',
	);

	/** @var bool */
	private static $initialized = false;

	/** Lo llama `Nested\Module::init()` (frente F) si la clase existe. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		$symbols = array( 'Elementor\Controls_Manager', 'wp_register_style()', 'wp_enqueue_style()', 'plugins_url()' );
		if ( class_exists( Module::class ) && ! Module::requires( $symbols, 'presentation' ) ) {
			return;
		}
		self::$initialized = true;
		foreach ( self::TARGETS as $widget => $section ) {
			add_action( "elementor/element/{$widget}/{$section}/after_section_end", array( __CLASS__, 'register_controls' ), 10, 2 );
		}
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_style' ), 10, 0 );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'preview_style' ), 10, 0 );
	}

	/**
	 * Sección «Presentación». Una sola vez por pila.
	 *
	 * @param object $element Widget.
	 * @param array  $args    Argumentos de la sección.
	 */
	public static function register_controls( $element, $args = array() ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) || ( method_exists( $element, 'get_controls' ) && null !== $element->get_controls( self::CONTROL ) ) ) {
			return;
		}
		$c = '\\Elementor\\Controls_Manager';
		$element->start_controls_section(
			self::SECTION,
			array(
				'label' => __( 'Presentación', 'pro-elements' ),
				'tab'   => $c::TAB_STYLE,
			)
		);
		$element->add_control( self::CONTROL, self::control() );
		$element->end_controls_section();
	}

	/** Argumentos del control (también los leen las pruebas). */
	public static function control(): array {
		$c = '\\Elementor\\Controls_Manager';
		return array(
			'label'              => __( 'Estilo de los títulos', 'pro-elements' ),
			'type'               => $c::SELECT,
			'options'            => array(
				''          => __( 'Ninguno', 'pro-elements' ),
				'pills'     => __( 'Píldoras', 'pro-elements' ),
				'underline' => __( 'Subrayado', 'pro-elements' ),
				'boxed'     => __( 'Recuadro', 'pro-elements' ),
				'minimal'   => __( 'Mínimo', 'pro-elements' ),
			),
			'prefix_class'       => self::PREFIX,
			'classes_dictionary' => array( 'none' => '' ),
			'render_type'        => 'ui',
			'style_transfer'     => true,
			'description'        => __( 'Da forma a los títulos y marca el activo con algo más que el color. Los colores, bordes, sombras y medidas que elijas en las demás secciones de Estilo siguen mandando.', 'pro-elements' ),
			'assets'             => array(
				'styles' => array(
					array(
						'name'       => self::HANDLE,
						'conditions' => array(
							'terms' => array(
								array(
									'name'     => self::CONTROL,
									'operator' => 'in',
									'value'    => self::STYLES,
								),
							),
						),
					),
				),
			),
		);
	}

	/** Registra la hoja; la encola el cargador del núcleo sólo en las páginas que eligen un estilo. */
	public static function register_style(): void {
		if ( function_exists( 'wp_style_is' ) && wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		$file    = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? DIGITALISIMO_ELEMENTS_FILE : dirname( __DIR__, 3 ) . '/pro-elements.php';
		wp_register_style( self::HANDLE, plugins_url( self::STYLE, $file ), array(), $version );
	}

	/** Vista previa del editor: la hoja siempre, para que el cambio de estilo se vea en vivo. */
	public static function preview_style(): void {
		self::register_style();
		wp_enqueue_style( self::HANDLE );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
