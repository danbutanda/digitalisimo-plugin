<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * «Distintivo» (F8, §3.C.3; contrato C11): una etiqueta opcional («Nuevo», «Oferta»…) en una esquina de cualquier widget,
 * sección, columna o contenedor.
 *
 * - **Controles:** sección propia (pestaña Avanzado) tras `common/_section_style`, `section/section_advanced`,
 *   `container/section_layout` y `column/section_advanced`, como «Enlace del elemento». Ningún control lleva `default`:
 *   un elemento existente pinta el mismo HTML y el mismo CSS. El interruptor, el texto y el icono no son de estilo
 *   (`style_transfer => false`); el interruptor declara la hoja en `assets` y sólo llega a las páginas que la usan.
 * - **Marcado** (servidor, búfer de `before_render`/`after_render`, como `Element_Link` y `Figures`): primer hijo del
 *   envoltorio `<span class="digi-badge digi-badge--{esquina}">[icono aria-hidden]<span class="digi-badge__text">texto
 *   </span></span>`. El texto es contenido (sin `aria-hidden`) y va con `esc_html`. El envoltorio recibe
 *   `data-digi-badge`. Los elementos del núcleo ya llevan `position: relative` (`.elementor-widget`, `.elementor-section`,
 *   `.elementor-column`, `.e-con`), y uno absoluto o fijo sigue posicionado; «Recortar» pone `overflow: hidden`.
 * - **Posición** con `inset-inline-*`/`inset-block-*` (igual en RTL). **Forma**: rectángulo, cinta, etiqueta, píldora o
 *   un `clip-path` propio, que sólo admite `polygon()`, `inset()`, `circle()` y `ellipse()` con números, `%`, `px` y `em`
 *   (≤ 200 caracteres) y va en un atributo `style` escapado: nunca llega a una hoja como texto libre.
 * - **Flotación:** `@keyframes` sólo con `prefers-reduced-motion: no-preference`.
 * - **Recursos:** `badge.css` (≤ 1 KB), 0 JS. **WCAG 2.2:** 1.4.3 (colores por defecto `Canvas`/`CanvasText`), 1.4.12
 *   (sin alto fijo) y 2.3.3 (movimiento).
 */
final class Badge {
	const KEY     = 'digitalisimo_badge';
	const SECTION = 'digitalisimo_badge_section';
	const HANDLE  = 'digitalisimo-badge';
	const CSS     = 'modules/digitalisimo-widgets/assets/css/badge.css';

	/** Esquinas; `''` (el valor sin elegir) es arriba al final. */
	const CORNERS = array( 'top-end', 'top-start', 'bottom-start', 'bottom-end' );

	/** Formas con clase propia; `custom` usa `digitalisimo_badge_clip`. */
	const SHAPES = array( 'ribbon', 'tag', 'pill', 'custom' );

	/** Pilas y secciones tras las que va «Distintivo». */
	const PLACES = array( 'common/_section_style', 'section/section_advanced', 'container/section_layout', 'column/section_advanced' );

	/** Longitud máxima de un `clip-path` propio. */
	const CLIP_MAX = 200;

	/** Distintivo preparado en `before_render` para el `after_render` del mismo elemento (por `spl_object_id`). */
	private static $pending = array();

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		foreach ( self::PLACES as $place ) {
			add_action( 'elementor/element/' . $place . '/after_section_end', array( __CLASS__, 'register_controls' ), 10, 2 );
		}
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/after_render', array( __CLASS__, 'after_render' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ), 10, 0 );
	}

	/** Registra la hoja (sin encolarla): la encola el cargador del núcleo por `assets`. Idempotente. */
	public static function register_assets(): void {
		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		$file    = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? DIGITALISIMO_ELEMENTS_FILE : dirname( __DIR__, 2 ) . '/pro-elements.php';
		wp_register_style( self::HANDLE, plugins_url( self::CSS, $file ), array(), $version );
	}

	/**
	 * Sección «Distintivo». Una sola vez por pila.
	 *
	 * @param object $element Pila de controles.
	 * @param array  $args    Argumentos de la sección.
	 */
	public static function register_controls( $element, $args = array() ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) || ( method_exists( $element, 'get_controls' ) && null !== $element->get_controls( self::KEY ) ) ) {
			return;
		}
		$c   = '\\Elementor\\Controls_Manager';
		$on  = array( self::KEY => 'yes' );
		$sel = '{{WRAPPER}} > .digi-badge';
		$element->start_controls_section(
			self::SECTION,
			array(
				'label' => __( 'Distintivo', 'pro-elements' ),
				'tab'   => $c::TAB_ADVANCED,
			)
		);
		$element->add_control( self::KEY, self::switch_control() );
		$element->add_control(
			'digitalisimo_badge_text',
			array(
				'label'          => __( 'Texto', 'pro-elements' ),
				'type'           => $c::TEXT,
				'dynamic'        => array( 'active' => true ),
				'placeholder'    => __( 'Nuevo', 'pro-elements' ),
				'condition'      => $on,
				'style_transfer' => false,
			)
		);
		$element->add_control(
			'digitalisimo_badge_icon',
			array(
				'label'                  => __( 'Icono', 'pro-elements' ),
				'type'                   => $c::ICONS,
				'skin'                   => 'inline',
				'label_block'            => false,
				'exclude_inline_options' => array( 'svg' ),
				'condition'              => $on,
				'style_transfer'         => false,
			)
		);
		$element->add_control(
			'digitalisimo_badge_icon_position',
			array(
				'label'     => __( 'Posición del icono', 'pro-elements' ),
				'type'      => $c::SELECT,
				'options'   => array(
					'start' => __( 'Antes del texto', 'pro-elements' ),
					'end'   => __( 'Después del texto', 'pro-elements' ),
				),
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_badge_corner',
			array(
				'label'     => __( 'Esquina', 'pro-elements' ),
				'type'      => $c::SELECT,
				'options'   => array(
					''             => __( 'Arriba, al final', 'pro-elements' ),
					'top-start'    => __( 'Arriba, al inicio', 'pro-elements' ),
					'bottom-start' => __( 'Abajo, al inicio', 'pro-elements' ),
					'bottom-end'   => __( 'Abajo, al final', 'pro-elements' ),
				),
				'separator' => 'before',
				'condition' => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_badge_offset_x',
			array(
				'label'      => __( 'Desplazamiento horizontal', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array( 'px' => array( 'min' => -200, 'max' => 200 ) ),
				'selectors'  => array( $sel => '--digi-badge-x: {{SIZE}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_badge_offset_y',
			array(
				'label'      => __( 'Desplazamiento vertical', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array( 'px' => array( 'min' => -200, 'max' => 200 ) ),
				'selectors'  => array( $sel => '--digi-badge-y: {{SIZE}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_badge_rotate',
			array(
				'label'      => __( 'Giro', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'deg' ),
				'range'      => array( 'deg' => array( 'min' => -180, 'max' => 180 ) ),
				'selectors'  => array( $sel => 'rotate: {{SIZE}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_control(
			'digitalisimo_badge_overflow',
			array(
				'label'        => __( 'Recortar lo que sobresale', 'pro-elements' ),
				'description'  => __( 'Oculta la parte del distintivo (y de cualquier otro contenido) que salga del elemento.', 'pro-elements' ),
				'type'         => $c::SWITCHER,
				'return_value' => 'yes',
				'selectors'    => array( '{{WRAPPER}}' => 'overflow: hidden;' ),
				'condition'    => $on,
			)
		);
		$element->add_control(
			'digitalisimo_badge_shape',
			array(
				'label'     => __( 'Forma', 'pro-elements' ),
				'type'      => $c::SELECT,
				'options'   => array(
					''       => __( 'Rectángulo', 'pro-elements' ),
					'ribbon' => __( 'Cinta', 'pro-elements' ),
					'tag'    => __( 'Etiqueta', 'pro-elements' ),
					'pill'   => __( 'Píldora', 'pro-elements' ),
					'custom' => __( 'Recorte propio', 'pro-elements' ),
				),
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_badge_clip',
			array(
				'label'       => __( 'Recorte (clip-path)', 'pro-elements' ),
				'type'        => $c::TEXT,
				'placeholder' => 'polygon(0 0, 100% 0, 90% 100%, 0 100%)',
				'description' => __( 'Sólo polygon(), inset(), circle() o ellipse(), con números, %, px o em.', 'pro-elements' ),
				'condition'   => $on + array( 'digitalisimo_badge_shape' => 'custom' ),
			)
		);
		$element->add_control(
			'digitalisimo_badge_float',
			array(
				'label'        => __( 'Flotación', 'pro-elements' ),
				'description'  => __( 'Un vaivén suave. No se mueve si el visitante pidió reducir el movimiento.', 'pro-elements' ),
				'type'         => $c::SWITCHER,
				'return_value' => 'yes',
				'condition'    => $on,
			)
		);
		$element->add_control(
			'digitalisimo_badge_style_heading',
			array(
				'label'     => __( 'Estilo del distintivo', 'pro-elements' ),
				'type'      => $c::HEADING,
				'separator' => 'before',
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_badge_color',
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
				'name'      => 'digitalisimo_badge_bg',
				'types'     => array( 'classic', 'gradient' ),
				// `types` no retira los campos de vídeo (el núcleo hace `unset( $field )` sobre la referencia en
				// Group_Control_Background::filter_fields()): se excluyen aquí, o su `placeholder` con el vídeo del
				// proveedor quedaría en el panel (brand-audit.sh ONLY=f8, frente J). Nunca se podían elegir: no cambia nada.
				'exclude'   => array( 'image', 'video_link', 'video_start', 'video_end', 'play_once', 'play_on_mobile', 'privacy_mode', 'video_fallback' ),
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'digitalisimo_badge_typography',
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			array(
				'name'      => 'digitalisimo_badge_text_shadow',
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_badge_padding',
			array(
				'label'      => __( 'Relleno', 'pro-elements' ),
				'type'       => $c::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_badge_min_width',
			array(
				'label'      => __( 'Ancho mínimo', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 400 ) ),
				'selectors'  => array( $sel => 'min-width: {{SIZE}}{{UNIT}};' ),
				'condition'  => $on,
			)
		);
		$element->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'      => 'digitalisimo_badge_border',
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_responsive_control(
			'digitalisimo_badge_radius',
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
				'name'      => 'digitalisimo_badge_shadow',
				'selector'  => $sel,
				'condition' => $on,
			)
		);
		$element->add_control(
			'digitalisimo_badge_contrast_note',
			array(
				'type'            => $c::RAW_HTML,
				'raw'             => esc_html__( 'Sin colores propios usa los del sistema, con el contraste que pide la accesibilidad. Si los cambias, elige texto y fondo con un contraste de 4,5:1 como mínimo. El texto lo leen los lectores de pantalla; el icono, no.', 'pro-elements' ),
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
			'label'          => __( 'Mostrar distintivo', 'pro-elements' ),
			'description'    => __( 'Una etiqueta en una esquina del elemento. Se ve en la página y en su vista previa, no en el lienzo del editor.', 'pro-elements' ),
			'type'           => $c::SWITCHER,
			'return_value'   => 'yes',
			'style_transfer' => false,
			'assets'         => array(
				'styles' => array(
					array(
						'name'       => self::HANDLE,
						'conditions' => array(
							'terms' => array(
								array(
									'name'     => self::KEY,
									'operator' => '===',
									'value'    => 'yes',
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * `clip-path` propio validado, o '' si no lo es: una sola función `polygon()`, `inset()`, `circle()` o `ellipse()` con
	 * números (también negativos y decimales), `%`, `px`, `em`, comas, espacios y las palabras `at` y `round`.
	 *
	 * @param mixed $value Valor guardado.
	 */
	public static function clip( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( preg_replace( '/\s+/', ' ', $value ) );
		if ( '' === $value || strlen( $value ) > self::CLIP_MAX ) {
			return '';
		}
		$number = '-?(?:\d+(?:\.\d+)?|\.\d+)(?:%|px|em)?';
		$item   = '(?:' . $number . '|at|round|center|top|bottom|left|right)';
		$args   = $item . '(?:(?: ?, ?| )' . $item . ')*'; // Siempre con un separador: sin ambigüedad ni retroceso exponencial.
		return 1 === preg_match( '/^(polygon|inset|circle|ellipse)\( ?(' . $args . ')? ?\)$/', $value ) ? $value : '';
	}

	/**
	 * Distintivo de unos ajustes ya resueltos, o '' si está apagado o no tiene texto ni icono.
	 *
	 * @param array  $settings Ajustes (`digitalisimo_badge*`), con el texto dinámico resuelto.
	 * @param string $icon     HTML del icono (ya pintado por el gestor de iconos del núcleo), o ''.
	 */
	public static function markup( array $settings, string $icon = '' ): string {
		if ( 'yes' !== ( $settings[ self::KEY ] ?? '' ) ) {
			return '';
		}
		$text = $settings['digitalisimo_badge_text'] ?? '';
		$text = is_scalar( $text ) ? trim( wp_strip_all_tags( (string) $text ) ) : '';
		if ( '' === $text && '' === $icon ) {
			return '';
		}
		$corner  = $settings['digitalisimo_badge_corner'] ?? '';
		$corner  = is_string( $corner ) && in_array( $corner, self::CORNERS, true ) ? $corner : 'top-end';
		$classes = array( 'digi-badge', 'digi-badge--' . $corner );
		$shape   = $settings['digitalisimo_badge_shape'] ?? '';
		$style   = '';
		if ( is_string( $shape ) && in_array( $shape, self::SHAPES, true ) ) {
			if ( 'custom' === $shape ) {
				$clip  = self::clip( $settings['digitalisimo_badge_clip'] ?? '' );
				$style = '' !== $clip ? ' style="' . esc_attr( 'clip-path:' . $clip ) . '"' : '';
			} else {
				$classes[] = 'digi-badge--' . $shape;
			}
		}
		if ( 'yes' === ( $settings['digitalisimo_badge_float'] ?? '' ) ) {
			$classes[] = 'digi-badge--float';
		}
		$icon  = '' !== $icon ? '<span class="digi-badge__icon" aria-hidden="true">' . $icon . '</span>' : '';
		$label = '' !== $text ? '<span class="digi-badge__text">' . esc_html( $text ) . '</span>' : '';
		$inner = 'end' === ( $settings['digitalisimo_badge_icon_position'] ?? '' ) ? $label . $icon : $icon . $label;
		return '<span class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $style . '>' . $inner . '</span>';
	}

	/** HTML de un icono del control `ICONS`, o '' (sin valor, o sin el gestor de iconos del núcleo). */
	public static function icon( $icon ): string {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) || ! class_exists( '\Elementor\Icons_Manager' ) ) {
			return '';
		}
		$html = \Elementor\Icons_Manager::try_get_icon_html( $icon, array( 'aria-hidden' => 'true' ) );
		return is_string( $html ) ? $html : '';
	}

	/** ¿Se está pintando el lienzo del editor? Allí no se añade nada (como `Element_Link`). */
	private static function editing(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->editor ) || ! is_object( \Elementor\Plugin::$instance->editor ) ) {
			return false;
		}
		return method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' ) && (bool) \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/**
	 * `elementor/frontend/before_render`: prepara el distintivo y abre el búfer del elemento. Sólo resuelve el texto
	 * dinámico y el icono si el interruptor está encendido.
	 *
	 * @param object $element Elemento.
	 */
	public static function before_render( $element ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) || 'yes' !== $element->get_settings( self::KEY ) || self::editing() ) {
			return;
		}
		$settings = array( self::KEY => 'yes' );
		foreach ( array( 'corner', 'shape', 'clip', 'float', 'icon_position' ) as $key ) {
			$settings[ 'digitalisimo_badge_' . $key ] = $element->get_settings( 'digitalisimo_badge_' . $key );
		}
		$settings['digitalisimo_badge_text'] = method_exists( $element, 'get_settings_for_display' ) ? $element->get_settings_for_display( 'digitalisimo_badge_text' ) : '';
		$markup = self::markup( $settings, self::icon( $element->get_settings( 'digitalisimo_badge_icon' ) ) );
		if ( '' === $markup ) {
			return;
		}
		self::$pending[ spl_object_id( $element ) ] = $markup;
		ob_start();
	}

	/**
	 * `elementor/frontend/after_render`: el distintivo como primer hijo del envoltorio, que recibe `data-digi-badge`.
	 *
	 * @param object $element Elemento.
	 */
	public static function after_render( $element ): void {
		$key = is_object( $element ) ? spl_object_id( $element ) : 0;
		if ( ! isset( self::$pending[ $key ] ) ) {
			return;
		}
		$badge = self::$pending[ $key ];
		unset( self::$pending[ $key ] );
		echo self::insert( (string) ob_get_clean(), $badge ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML del constructor y un distintivo escapado.
	}

	/**
	 * Inserta el distintivo tras la etiqueta de apertura del envoltorio y le pone `data-digi-badge`. Sin envoltorio
	 * (contenido vacío), devuelve el HTML tal cual.
	 *
	 * @param string $html  HTML del elemento.
	 * @param string $badge Distintivo.
	 */
	public static function insert( string $html, string $badge ): string {
		if ( ! preg_match( '/<[a-zA-Z][^>]*>/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}
		$tag = preg_replace( '/^<([a-zA-Z][\w-]*)/', '<$1 data-digi-badge', $match[0][0], 1 );
		$at  = $match[0][1] + strlen( $match[0][0] );
		return substr( $html, 0, $match[0][1] ) . $tag . $badge . substr( $html, $at );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$pending     = array();
	}
}
