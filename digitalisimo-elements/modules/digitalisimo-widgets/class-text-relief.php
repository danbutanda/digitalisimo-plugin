<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * «Relieve 3D»: el título del Encabezado (`heading`) o del Encabezado avanzado se apila en capas a lo largo del eje Z,
 * con perspectiva, para darle volumen; opcionalmente la pila gira con el puntero o con el desplazamiento.
 *
 * - **Controles:** sección propia en la pestaña Estilo, tras la del título de cada widget. Ninguno lleva `default`: un
 *   encabezado existente pinta el mismo HTML y el mismo CSS. Lo que no es de estilo (evento, giro y sentido) lleva
 *   `style_transfer => false`. El interruptor declara la hoja y el script en `assets`: sólo llegan a las páginas que lo
 *   usan (coste 0 en las demás). Sin valor, la profundidad es de 30 px, las capas 8, la perspectiva 500 px y el giro
 *   35°; el desvanecido sólo con su interruptor.
 * - **Marcado:** el servidor no cambia el título; sólo el envoltorio recibe `data-digi-relief` con la configuración
 *   validada aquí (lista cerrada y topes). La hoja deja el título en `inline-block` con su perspectiva desde el primer
 *   pintado, y el script (`text-relief.js`, diferido y sin jQuery) mueve el contenido a la capa frontal y añade detrás
 *   las copias con `aria-hidden`, `inert` y sin selección ni puntero: el lector de pantalla, los buscadores y quien copia
 *   el texto lo encuentran una sola vez. Sin JavaScript se ve el título tal cual.
 * - **Capas:** la capa *i* de *N* se desplaza `profundidad/2 − i·profundidad/N` en Z; con el desvanecido, cada copia
 *   tiene opacidad `(1 − i/N)/2`. El color de la profundidad sólo pinta las copias.
 * - **Movimiento:** con «Al mover el puntero» la pila gira según la posición del puntero en la ventana; con «Al
 *   desplazar», según la del título respecto al centro de la ventana. Con movimiento reducido no gira.
 * - En el lienzo del editor no se añade nada (como «Información emergente»): se ve en la página y en su vista previa.
 */
final class Text_Relief {
	const KEY     = 'digitalisimo_relief';
	const SECTION = 'digitalisimo_relief_section';
	const HANDLE  = 'digitalisimo-text-relief';
	const CSS     = 'modules/digitalisimo-widgets/assets/css/text-relief.css';
	const JS      = 'modules/digitalisimo-widgets/assets/js/text-relief.js';

	/** Widgets que lo reciben => sección de estilo tras la que va la suya. */
	const WIDGETS = array(
		'heading'                       => 'section_title_style',
		'digitalisimo-advanced-heading' => 'section_heading_style',
	);

	/** Eventos que hacen girar la pila; `''` (sin elegir) es ninguno. */
	const EVENTS = array( 'pointer', 'scroll', 'scroll-x', 'scroll-y' );

	/** Unidades de la profundidad y su tope. */
	const DEPTH_UNITS = array(
		'px'  => 1000,
		'em'  => 100,
		'rem' => 100,
	);

	/** Valores cuando el control está vacío. */
	const FALLBACK = array(
		'depth'    => 30,
		'layers'   => 8,
		'rotation' => 35,
	);

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		foreach ( self::WIDGETS as $widget => $section ) {
			add_action( 'elementor/element/' . $widget . '/' . $section . '/after_section_end', array( __CLASS__, 'register_controls' ), 10, 2 );
		}
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ), 10, 0 );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ), 10, 0 );
	}

	/** Registra la hoja y el script (sin encolarlos): los encola el cargador del núcleo por `assets`. Idempotente. */
	public static function register_assets(): void {
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		$file    = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? DIGITALISIMO_ELEMENTS_FILE : dirname( __DIR__, 2 ) . '/pro-elements.php';
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			wp_register_style( self::HANDLE, plugins_url( self::CSS, $file ), array(), $version );
		}
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script( self::HANDLE, plugins_url( self::JS, $file ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}

	/**
	 * Sección «Relieve 3D». Una sola vez por pila.
	 *
	 * @param object $element Pila de controles (widget).
	 * @param array  $args    Argumentos de la sección.
	 */
	public static function register_controls( $element, $args = array() ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) || ( method_exists( $element, 'get_controls' ) && null !== $element->get_controls( self::KEY ) ) ) {
			return;
		}
		$c      = '\\Elementor\\Controls_Manager';
		$on     = array( self::KEY => 'yes' );
		$moving = $on + array( 'digitalisimo_relief_event!' => '' );
		$element->start_controls_section(
			self::SECTION,
			array(
				'label' => __( 'Relieve 3D', 'pro-elements' ),
				'tab'   => $c::TAB_STYLE,
			)
		);
		$element->add_control( self::KEY, self::switch_control() );
		$element->add_control(
			'digitalisimo_relief_depth',
			array(
				'label'          => __( 'Profundidad', 'pro-elements' ),
				'description'    => __( 'Vacía: 30 px.', 'pro-elements' ),
				'type'           => $c::SLIDER,
				'size_units'     => array_keys( self::DEPTH_UNITS ),
				'range'          => array(
					'px'  => array( 'min' => 0, 'max' => 100 ),
					'em'  => array( 'min' => 0, 'max' => 10, 'step' => 0.1 ),
					'rem' => array( 'min' => 0, 'max' => 10, 'step' => 0.1 ),
				),
				'condition'      => $on,
				'style_transfer' => true,
			)
		);
		$element->add_control(
			'digitalisimo_relief_layers',
			array(
				'label'          => __( 'Capas', 'pro-elements' ),
				'description'    => __( 'Incluye la capa del texto. Vacío: 8.', 'pro-elements' ),
				'type'           => $c::NUMBER,
				'min'            => 1,
				'max'            => 100,
				'step'           => 1,
				'condition'      => $on,
				'style_transfer' => true,
			)
		);
		$element->add_control(
			'digitalisimo_relief_color',
			array(
				'label'       => __( 'Color de la profundidad', 'pro-elements' ),
				'description' => __( 'Vacío: el color del texto.', 'pro-elements' ),
				'type'        => $c::COLOR,
				'selectors'   => array( '{{WRAPPER}} .digi-relief__copy' => 'color: {{VALUE}};' ),
				'condition'   => $on,
			)
		);
		$element->add_control(
			'digitalisimo_relief_perspective',
			array(
				'label'       => __( 'Perspectiva', 'pro-elements' ),
				'description' => __( 'Vacía: 500 px. Cuanto menor, más se nota el relieve.', 'pro-elements' ),
				'type'        => $c::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 2000 ) ),
				'selectors'   => array( '{{WRAPPER}}' => '--digi-relief-perspective: {{SIZE}}{{UNIT}};' ),
				'condition'   => $on,
			)
		);
		$element->add_control(
			'digitalisimo_relief_fade',
			array(
				'label'          => __( 'Desvanecer las capas', 'pro-elements' ),
				'type'           => $c::SWITCHER,
				'return_value'   => 'yes',
				'condition'      => $on,
				'style_transfer' => true,
			)
		);
		$element->add_control(
			'digitalisimo_relief_event',
			array(
				'label'          => __( 'Girar', 'pro-elements' ),
				'type'           => $c::SELECT,
				'options'        => array(
					''         => __( 'Nunca', 'pro-elements' ),
					'pointer'  => __( 'Al mover el puntero', 'pro-elements' ),
					'scroll'   => __( 'Al desplazar la página', 'pro-elements' ),
					'scroll-x' => __( 'Al desplazar en horizontal', 'pro-elements' ),
					'scroll-y' => __( 'Al desplazar en vertical', 'pro-elements' ),
				),
				'description'    => __( 'Con el movimiento reducido activado en el sistema, no gira.', 'pro-elements' ),
				'condition'      => $on,
				'style_transfer' => false,
			)
		);
		$element->add_control(
			'digitalisimo_relief_rotation',
			array(
				'label'          => __( 'Giro máximo', 'pro-elements' ),
				'description'    => __( 'Vacío: 35°.', 'pro-elements' ),
				'type'           => $c::SLIDER,
				'size_units'     => array( 'deg' ),
				'range'          => array( 'deg' => array( 'min' => -360, 'max' => 360 ) ),
				'condition'      => $moving,
				'style_transfer' => false,
			)
		);
		$element->add_control(
			'digitalisimo_relief_direction',
			array(
				'label'          => __( 'Sentido del giro', 'pro-elements' ),
				'type'           => $c::SELECT,
				'options'        => array(
					''        => __( 'Normal', 'pro-elements' ),
					'reverse' => __( 'Inverso', 'pro-elements' ),
				),
				'condition'      => $moving,
				'style_transfer' => false,
			)
		);
		$element->end_controls_section();
	}

	/** El interruptor (también lo leen las pruebas). */
	public static function switch_control(): array {
		$c = '\\Elementor\\Controls_Manager';
		return array(
			'label'          => __( 'Relieve 3D', 'pro-elements' ),
			'description'    => __( 'Apila el título en capas con perspectiva. Se ve en la página y en su vista previa, no en el lienzo del editor.', 'pro-elements' ),
			'type'           => $c::SWITCHER,
			'return_value'   => 'yes',
			'style_transfer' => true,
			'assets'         => array(
				'styles'  => array(
					array(
						'name'       => self::HANDLE,
						'conditions' => self::active(),
					),
				),
				'scripts' => array(
					array(
						'name'       => self::HANDLE,
						'conditions' => self::active(),
					),
				),
			),
		);
	}

	/** Condición de los recursos: el interruptor encendido. */
	private static function active(): array {
		return array(
			'terms' => array(
				array(
					'name'     => self::KEY,
					'operator' => '===',
					'value'    => 'yes',
				),
			),
		);
	}

	/**
	 * Configuración que lee el script, validada: `d` y `u` (profundidad), `l` (capas), `f` (desvanecido) y, si gira,
	 * `e` (evento), `r` (giro en grados) y `v` (sentido inverso). `null` si el rasgo está apagado.
	 *
	 * @param array $settings Ajustes del widget (`digitalisimo_relief*`).
	 */
	public static function config( array $settings ): ?array {
		if ( 'yes' !== ( $settings[ self::KEY ] ?? '' ) ) {
			return null;
		}
		$depth = $settings['digitalisimo_relief_depth'] ?? null;
		$unit  = is_array( $depth ) && is_string( $depth['unit'] ?? null ) && isset( self::DEPTH_UNITS[ $depth['unit'] ] ) ? $depth['unit'] : 'px';
		$size  = is_array( $depth ) ? self::number( $depth['size'] ?? null, 0, self::DEPTH_UNITS[ $unit ] ) : null;
		if ( null === $size ) {
			$unit = 'px';
			$size = (float) self::FALLBACK['depth'];
		}
		$layers = self::number( $settings['digitalisimo_relief_layers'] ?? null, 1, 100 );
		$config = array(
			'd' => $size,
			'u' => $unit,
			'l' => null === $layers ? self::FALLBACK['layers'] : (int) round( $layers ),
			'f' => 'yes' === ( $settings['digitalisimo_relief_fade'] ?? '' ) ? 1 : 0,
		);
		$event = $settings['digitalisimo_relief_event'] ?? '';
		if ( is_string( $event ) && in_array( $event, self::EVENTS, true ) ) {
			$rotation    = $settings['digitalisimo_relief_rotation'] ?? null;
			$rotation    = is_array( $rotation ) ? self::number( $rotation['size'] ?? null, -360, 360 ) : null;
			$config['e'] = $event;
			$config['r'] = null === $rotation ? (float) self::FALLBACK['rotation'] : $rotation;
			$config['v'] = 'reverse' === ( $settings['digitalisimo_relief_direction'] ?? '' ) ? 1 : 0;
		}
		return $config;
	}

	/** Número finito dentro de los topes, o `null` si el valor está vacío o no es un número. */
	private static function number( $value, float $min, float $max ): ?float {
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			return null;
		}
		$value = (float) $value;
		if ( ! is_finite( $value ) ) {
			return null;
		}
		return round( max( $min, min( $max, $value ) ), 4 );
	}

	/** ¿Se está pintando el lienzo del editor? Allí no se añade nada (como `Tooltip`). */
	private static function editing(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->editor ) || ! is_object( \Elementor\Plugin::$instance->editor ) ) {
			return false;
		}
		return method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' ) && (bool) \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/**
	 * `elementor/frontend/widget/before_render`: la configuración, en el envoltorio. Sólo lee los ajustes del rasgo y sólo
	 * con el interruptor encendido.
	 *
	 * @param object $element Widget.
	 */
	public static function before_render( $element ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) || ! method_exists( $element, 'add_render_attribute' ) ) {
			return;
		}
		if ( 'yes' !== $element->get_settings( self::KEY ) || self::editing() ) {
			return;
		}
		$settings = array( self::KEY => 'yes' );
		foreach ( array( 'depth', 'layers', 'fade', 'event', 'rotation', 'direction' ) as $key ) {
			$settings[ 'digitalisimo_relief_' . $key ] = $element->get_settings( 'digitalisimo_relief_' . $key );
		}
		$config = self::config( $settings );
		if ( null !== $config ) {
			$element->add_render_attribute( '_wrapper', 'data-digi-relief', (string) wp_json_encode( $config ) );
		}
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
