<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * «Información emergente» (F8, §3.C.3 y V.10.5; contrato C10): un globo de texto opcional en cualquier widget.
 *
 * - **Controles:** sección propia tras `common/_section_style` (pestaña Avanzado), sólo en widgets: el núcleo repite los
 *   ganchos de `common` en `common-optimized` (`elementor/includes/base/controls-stack.php`), así que basta uno. Ningún
 *   control lleva `default`: un widget existente pinta el mismo HTML y el mismo CSS. Lo que no es de estilo (interruptor,
 *   texto y disparador) lleva `style_transfer => false`. El interruptor declara la hoja y el script en `assets`: sólo
 *   llegan a las páginas que lo usan (coste 0 en las demás).
 * - **Marcado:** en `elementor/frontend/widget/before_render` el envoltorio recibe `data-digi-tooltip` (y
 *   `data-digi-tooltip-trigger="click"`); en `elementor/widget/render_content` se añade al contenido
 *   `<span class="digi-tooltip digi-tooltip--{lado}" role="tooltip">texto</span>`, con el texto saneado por `wp_kses`
 *   (`strong`, `em`, `b`, `i`, `br`). Sin `id`: lo pone el script, único en la página aunque el widget se repita en un
 *   bucle o en una plantilla (V.10.5). En el lienzo del editor no se pinta nada (como «Enlace del elemento»).
 * - **Sin JavaScript** se ve al pasar el puntero o al enfocar algo dentro del widget. **Con JavaScript**
 *   (`tooltip.js`, ≤ 1 KB, `defer`, sin jQuery): `aria-describedby` en los descendientes operables, Escape lo descarta,
 *   el disparador «clic» lo abre y lo cierra, y se voltea si no cabe en la ventana. Sin un descendiente operable el
 *   anfitrión no se hace enfocable (no hay foco vacío): el globo, recortado y no `visibility:hidden`, sigue en el árbol
 *   de accesibilidad y el lector de pantalla lo lee con el contenido (límite declarado en V.18).
 * - **WCAG 2.2:** 1.4.13 (se puede recorrer con el puntero y descartar con Escape), 2.1.1, 2.4.7, 4.1.2 y 1.4.3 con los
 *   colores por defecto `Canvas`/`CanvasText`.
 */
final class Tooltip {
	const KEY     = 'digitalisimo_tooltip';
	const SECTION = 'digitalisimo_tooltip_section';
	const HANDLE  = 'digitalisimo-tooltip';
	const CSS     = 'modules/digitalisimo-widgets/assets/css/tooltip.css';
	const JS      = 'modules/digitalisimo-widgets/assets/js/tooltip.js';

	/** Lados del globo; `''` (el valor sin elegir) es arriba. */
	const SIDES = array( 'top', 'right', 'bottom', 'left' );

	/** HTML admitido en el texto: énfasis y saltos de línea; todo lo demás (también los enlaces) se quita. */
	const TAGS = array(
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'br'     => array(),
	);

	/** Globo preparado en `before_render` para el `render_content` del mismo widget (por `spl_object_id`). */
	private static $pending = array();

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/element/common/_section_style/after_section_end', array( __CLASS__, 'register_controls' ), 10, 2 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/widget/after_render', array( __CLASS__, 'after_render' ) );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'render_content' ), 10, 3 );
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
	 * Sección «Información emergente». Una sola vez por pila.
	 *
	 * @param object $element Pila de controles (widget).
	 * @param array  $args    Argumentos de la sección.
	 */
	public static function register_controls( $element, $args = array() ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) || ( method_exists( $element, 'get_controls' ) && null !== $element->get_controls( self::KEY ) ) ) {
			return;
		}
		$c   = '\\Elementor\\Controls_Manager';
		$on  = array( self::KEY => 'yes' );
		$sel = self::selector();
		$element->start_controls_section(
			self::SECTION,
			array(
				'label' => __( 'Información emergente', 'pro-elements' ),
				'tab'   => $c::TAB_ADVANCED,
			)
		);
		$element->add_control( self::KEY, self::switch_control() );
		$element->add_control(
			'digitalisimo_tooltip_text',
			array(
				'label'          => __( 'Texto', 'pro-elements' ),
				'type'           => $c::TEXTAREA,
				'dynamic'        => array( 'active' => true ),
				'description'    => __( 'Admite negrita, cursiva y saltos de línea; los enlaces y el resto del HTML se quitan.', 'pro-elements' ),
				'condition'      => $on,
				'style_transfer' => false,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_placement',
			array(
				'label'     => __( 'Posición', 'pro-elements' ),
				'type'      => $c::SELECT,
				'options'   => array(
					''       => __( 'Arriba', 'pro-elements' ),
					'right'  => __( 'Derecha', 'pro-elements' ),
					'bottom' => __( 'Abajo', 'pro-elements' ),
					'left'   => __( 'Izquierda', 'pro-elements' ),
				),
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_trigger',
			array(
				'label'          => __( 'Se muestra', 'pro-elements' ),
				'type'           => $c::SELECT,
				'options'        => array(
					''      => __( 'Al pasar el puntero o al enfocar', 'pro-elements' ),
					'click' => __( 'Al hacer clic', 'pro-elements' ),
				),
				'condition'      => $on,
				'style_transfer' => false,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_arrow',
			array(
				'label'        => __( 'Flecha', 'pro-elements' ),
				'type'         => $c::SWITCHER,
				'return_value' => 'yes',
				'condition'    => $on,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_offset',
			array(
				'label'      => __( 'Distancia al elemento', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( $sel => '--digi-tt-gap: {{SIZE}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_tooltip_width',
			array(
				'label'      => __( 'Ancho máximo', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 50, 'max' => 600 ),
					'em' => array( 'min' => 3, 'max' => 40 ),
				),
				'selectors'  => array( $sel => 'max-width: {{SIZE}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_style_heading',
			array(
				'label'     => __( 'Estilo del globo', 'pro-elements' ),
				'type'      => $c::HEADING,
				'separator' => 'before',
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_color',
			array(
				'label'     => __( 'Color del texto', 'pro-elements' ),
				'type'      => $c::COLOR,
				'selectors' => array( $sel => 'color: {{VALUE}};' ),
				'condition' => $on,
			)
		);
		$element->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'      => 'digitalisimo_tooltip_bg',
				'types'     => array( 'classic', 'gradient' ),
				'exclude'   => array( 'image' ),
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_arrow_color',
			array(
				'label'       => __( 'Color de la flecha', 'pro-elements' ),
				'description' => __( 'Vacío: el fondo del globo.', 'pro-elements' ),
				'type'        => $c::COLOR,
				'selectors'   => array( self::selector( '::after' ) => 'background: {{VALUE}};' ),
				'condition'   => $on + array( 'digitalisimo_tooltip_arrow' => 'yes' ),
			)
		);
		$element->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'digitalisimo_tooltip_typography',
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_align',
			array(
				'label'     => __( 'Alineación del texto', 'pro-elements' ),
				'type'      => $c::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array( $sel => 'text-align: {{VALUE}};' ),
				'condition' => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_tooltip_padding',
			array(
				'label'      => __( 'Relleno', 'pro-elements' ),
				'type'       => $c::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'      => 'digitalisimo_tooltip_border',
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_tooltip_radius',
			array(
				'label'      => __( 'Radio del borde', 'pro-elements' ),
				'type'       => $c::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'digitalisimo_tooltip_shadow',
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_tooltip_contrast_note',
			array(
				'type'            => $c::RAW_HTML,
				'raw'             => esc_html__( 'Sin colores propios usa los del sistema, con el contraste que pide la accesibilidad. Si los cambias, elige texto y fondo con un contraste de 4,5:1 como mínimo.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => $on,
			)
		);
		$element->end_controls_section();
	}

	/** El interruptor (también lo leen las pruebas). */
	public static function switch_control(): array {
		$c = '\\Elementor\\Controls_Manager';
		return array(
			'label'          => __( 'Mostrar información emergente', 'pro-elements' ),
			'description'    => __( 'Un globo de texto al pasar el puntero o al enfocar el elemento. Se ve en la página y en su vista previa, no en el lienzo del editor.', 'pro-elements' ),
			'type'           => $c::SWITCHER,
			'return_value'   => 'yes',
			'style_transfer' => false,
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
	 * Selector del globo de este widget, con y sin el envoltorio interior (marcado optimizado): nunca alcanza el globo de
	 * un widget anidado dentro (pestañas, acordeón o slider anidados).
	 */
	public static function selector( string $suffix = '' ): string {
		return '{{WRAPPER}} > .digi-tooltip' . $suffix . ', {{WRAPPER}} > .elementor-widget-container > .digi-tooltip' . $suffix;
	}

	/**
	 * Globo de unos ajustes ya resueltos, o '' si el rasgo está apagado o el texto queda vacío.
	 *
	 * @param array $settings Ajustes del widget (`digitalisimo_tooltip*`), con el texto dinámico resuelto.
	 */
	public static function bubble( array $settings ): string {
		if ( 'yes' !== ( $settings[ self::KEY ] ?? '' ) ) {
			return '';
		}
		$text = self::text( $settings['digitalisimo_tooltip_text'] ?? '' );
		if ( '' === $text ) {
			return '';
		}
		$placement = $settings['digitalisimo_tooltip_placement'] ?? '';
		$side      = is_string( $placement ) && in_array( $placement, self::SIDES, true ) ? $placement : 'top';
		$arrow     = 'yes' === ( $settings['digitalisimo_tooltip_arrow'] ?? '' ) ? ' digi-tooltip--arrow' : '';
		return '<span class="digi-tooltip digi-tooltip--' . $side . $arrow . '" role="tooltip">' . $text . '</span>';
	}

	/** Texto saneado: sólo `strong`, `em`, `b`, `i` y `br`, sin atributos; los saltos de línea pasan a `<br>`. */
	public static function text( $text ): string {
		if ( ! is_scalar( $text ) ) {
			return '';
		}
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		$text = trim( wp_kses( str_replace( array( "\r\n", "\r", "\n" ), '<br>', $text ), self::TAGS ) );
		return '' === trim( preg_replace( '/<br\s*\/?>|&nbsp;|\s/i', '', $text ) ) ? '' : $text;
	}

	/** ¿Se está pintando el lienzo del editor? Allí no se añade nada (como `Element_Link`). */
	private static function editing(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->editor ) || ! is_object( \Elementor\Plugin::$instance->editor ) ) {
			return false;
		}
		return method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' ) && (bool) \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/**
	 * `elementor/frontend/widget/before_render`: atributos del envoltorio y globo para el `render_content` del mismo
	 * widget. Sólo resuelve el texto dinámico si el interruptor está encendido.
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
		$settings = array(
			self::KEY                        => 'yes',
			'digitalisimo_tooltip_text'      => method_exists( $element, 'get_settings_for_display' ) ? $element->get_settings_for_display( 'digitalisimo_tooltip_text' ) : '',
			'digitalisimo_tooltip_placement' => $element->get_settings( 'digitalisimo_tooltip_placement' ),
			'digitalisimo_tooltip_arrow'     => $element->get_settings( 'digitalisimo_tooltip_arrow' ),
		);
		$bubble = self::bubble( $settings );
		if ( '' === $bubble ) {
			return;
		}
		$element->add_render_attribute( '_wrapper', 'data-digi-tooltip', '1' );
		if ( 'click' === $element->get_settings( 'digitalisimo_tooltip_trigger' ) ) {
			$element->add_render_attribute( '_wrapper', 'data-digi-tooltip-trigger', 'click' );
		}
		self::$pending[ spl_object_id( $element ) ] = $bubble;
	}

	/**
	 * `elementor/widget/render_content`: el globo al final del contenido del widget. Sólo en el pintado normal (no en el
	 * de Markdown) y sólo si `before_render` lo preparó para este mismo widget.
	 *
	 * @param mixed  $content Contenido del widget.
	 * @param object $widget  Widget.
	 * @param array  $args    `mode`: `normal` o `markdown`.
	 */
	public static function render_content( $content, $widget = null, $args = array() ) {
		if ( ! is_string( $content ) || ! is_object( $widget ) ) {
			return $content;
		}
		$id = spl_object_id( $widget );
		if ( ! isset( self::$pending[ $id ] ) || ( is_array( $args ) && 'markdown' === ( $args['mode'] ?? '' ) ) ) {
			return $content;
		}
		$bubble = self::$pending[ $id ];
		unset( self::$pending[ $id ] );
		return $content . $bubble;
	}

	/**
	 * `elementor/frontend/widget/after_render`: olvida un globo que no llegó a pintarse (contenido vacío), para que su
	 * `spl_object_id` reutilizado no lo lleve a otro widget.
	 *
	 * @param object $element Widget.
	 */
	public static function after_render( $element ): void {
		if ( is_object( $element ) ) {
			unset( self::$pending[ spl_object_id( $element ) ] );
		}
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$pending     = array();
	}
}
