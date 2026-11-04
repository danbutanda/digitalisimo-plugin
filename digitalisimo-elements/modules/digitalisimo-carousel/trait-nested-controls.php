<?php
namespace Digitalisimo\Elements\Carousel;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Controles de carrusel de los widgets que no usan repetidor de diapositivas (C1, `docs/pro/c1.md` §1.4): el carrusel
 * anidado y, en C5, el Loop Carousel propio. Repite `base/base-carousel-trait.php` de PRO: los mismos métodos
 * `add_carousel_*_controls( $params )` con los mismos parámetros (`css_prefix` y los `*_custom_settings`, que se suman
 * igual que en PRO), y los mismos controles con el mismo nombre, valor por defecto, opciones y condiciones, también los
 * que PRO arma por concatenación (`arrow_normal_color`, `arrows_hover_background`, `dots_custom_position`,
 * `heading_dotspagination_vertical`…).
 *
 * Lo propio: textos; selectores sobre las variables de `Engine` (documentadas en su cabecera) en `{{WRAPPER}}`, o sobre
 * `Engine::scope()` para las reglas directas; sin `prefix_class` (las posiciones son modificadores de la raíz, que pone
 * `carousel_args()`), y `css_prefix` se acepta pero no se usa. Los valores por defecto de las hojas de PRO que no salen
 * de un control (p. ej. el desplazamiento de 15 px de las flechas del carrusel anidado, `--digi-crs-prev-cs`, o
 * `--digi-crs-align: flex-start` cuando «Misma altura» está apagado) los pone la hoja de cada widget.
 *
 * `carousel_args( array $settings ): array` devuelve los argumentos de `Engine::open()`: nombre (`carousel_name`),
 * diapositivas (`$this->num_of_carousel_items`, que fija el widget antes de pintar), reproducción automática, bucle
 * (`infinite`), pausas, velocidad, paginación y su posición (`dots_position` o `fraction_position`), flechas, su posición
 * y sus iconos (el chevron por defecto se pinta con el SVG propio de `Engine`, el mismo trazo; otro icono con
 * `Icons_Manager`), y la dirección (`direction`, en la pista).
 */
trait Nested_Controls {
	/** @var int|null Diapositivas del carrusel (lo fija el widget; el Loop Carousel, con las entradas). */
	public $num_of_carousel_items;

	public function add_carousel_layout_controls( $params ) {
		$slides_on_display = range( 1, $params['slides_on_display'] );
		$slides_on_display = array_combine( $slides_on_display, $slides_on_display );

		$slides_to_show_shared_settings = array(
			'label'                => esc_html__( 'Diapositivas visibles', 'pro-elements' ),
			'type'                 => Controls_Manager::SELECT,
			'options'              => array(
				'' => esc_html__( 'Predeterminado', 'pro-elements' ),
			) + $slides_on_display,
			'inherit_placeholders' => false,
			'frontend_available'   => true,
			'render_type'          => 'template',
			'content_classes'      => 'elementor-control-field-select-small',
			'selectors'            => array(
				'{{WRAPPER}}' => '--digi-crs-view: {{VALUE}}',
			),
		);

		$this->add_responsive_control(
			'slides_to_show',
			$params['slides_to_show_custom_settings'] + $slides_to_show_shared_settings
		);

		$slides_to_scroll_shared_settings = array(
			'label'                => esc_html__( 'Diapositivas por paso', 'pro-elements' ),
			'type'                 => Controls_Manager::SELECT,
			'options'              => array(
				'' => esc_html__( 'Predeterminado', 'pro-elements' ),
			) + $slides_on_display,
			'inherit_placeholders' => false,
			'frontend_available'   => true,
			'content_classes'      => 'elementor-control-field-select-small',
			'selectors'            => array(
				'{{WRAPPER}}' => '--digi-crs-scroll: {{VALUE}}',
			),
		);

		$this->add_responsive_control(
			'slides_to_scroll',
			$params['slides_to_scroll_custom_settings'] + $slides_to_scroll_shared_settings
		);

		$equal_height_shared_settings = array(
			'label'     => esc_html__( 'Misma altura', 'pro-elements' ),
			'type'      => Controls_Manager::SWITCHER,
			'label_off' => esc_html__( 'No', 'pro-elements' ),
			'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
			'default'   => 'yes',
			'selectors' => array(
				'{{WRAPPER}}' => '--digi-crs-align: stretch;',
			),
		);

		$this->add_control(
			'equal_height',
			$params['equal_height_custom_settings'] + $equal_height_shared_settings
		);
	}

	public function add_carousel_settings_controls( $params = array() ) {
		$this->start_controls_section(
			'section_carousel_settings',
			array(
				'label' => esc_html__( 'Ajustes', 'pro-elements' ),
			)
		);

		$autoplay_shared_settings = array(
			'label'              => esc_html__( 'Reproducción automática', 'pro-elements' ),
			'type'               => Controls_Manager::SWITCHER,
			'default'            => 'yes',
			'options'            => array(
				'yes' => esc_html__( 'Sí', 'pro-elements' ),
				'no'  => esc_html__( 'No', 'pro-elements' ),
			),
			'frontend_available' => true,
		);

		$this->add_control(
			'autoplay',
			array_key_exists( 'autoplay_custom_settings', $params )
				? $params['autoplay_custom_settings'] + $autoplay_shared_settings
				: $autoplay_shared_settings
		);

		$this->add_control(
			'autoplay_speed',
			array(
				'label'              => esc_html__( 'Intervalo', 'pro-elements' ) . ' (ms)',
				'type'               => Controls_Manager::NUMBER,
				'default'            => 5000,
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pause_on_hover',
			array(
				'label'              => esc_html__( 'Pausar con el puntero encima', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'options'            => array(
					'yes' => esc_html__( 'Sí', 'pro-elements' ),
					'no'  => esc_html__( 'No', 'pro-elements' ),
				),
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pause_on_interaction',
			array(
				'label'              => esc_html__( 'Detener tras una interacción', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'options'            => array(
					'yes' => esc_html__( 'Sí', 'pro-elements' ),
					'no'  => esc_html__( 'No', 'pro-elements' ),
				),
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$infinite_shared_settings = array(
			'label'              => esc_html__( 'Bucle infinito', 'pro-elements' ),
			'type'               => Controls_Manager::SWITCHER,
			'default'            => 'yes',
			'options'            => array(
				'yes' => esc_html__( 'Sí', 'pro-elements' ),
				'no'  => esc_html__( 'No', 'pro-elements' ),
			),
			'frontend_available' => true,
		);

		$this->add_control(
			'infinite',
			array_key_exists( 'infinite_custom_settings', $params )
				? $params['infinite_custom_settings'] + $infinite_shared_settings
				: $infinite_shared_settings
		);

		$this->add_control(
			'speed',
			array(
				'label'              => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (ms)',
				'type'               => Controls_Manager::NUMBER,
				'default'            => 500,
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'direction',
			array(
				'label'   => esc_html__( 'Dirección', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => is_rtl() ? 'rtl' : 'ltr',
				'options' => array(
					'ltr' => esc_html__( 'Izquierda', 'pro-elements' ),
					'rtl' => esc_html__( 'Derecha', 'pro-elements' ),
				),
			)
		);

		$offset_sides_shared_settings = array(
			'label'                => esc_html__( 'Asomo lateral', 'pro-elements' ),
			'type'                 => Controls_Manager::SELECT,
			'default'              => 'none',
			'separator'            => 'before',
			'frontend_available'   => true,
			'condition'            => array(
				'infinite' => 'yes',
			),
			'options'              => array(
				'none'  => esc_html__( 'Ninguno', 'pro-elements' ),
				'both'  => esc_html__( 'Ambos', 'pro-elements' ),
				'left'  => esc_html__( 'Izquierda', 'pro-elements' ),
				'right' => esc_html__( 'Derecha', 'pro-elements' ),
			),
			'selectors'            => array(
				'{{WRAPPER}}' => '{{VALUE}}',
			),
			'selectors_dictionary' => array(
				'none'  => '',
				'both'  => '--digi-crs-offset-start: var(--digi-crs-offset, 80px); --digi-crs-offset-end: var(--digi-crs-offset, 80px);',
				'left'  => '--digi-crs-offset-start: var(--digi-crs-offset, 80px);',
				'right' => '--digi-crs-offset-end: var(--digi-crs-offset, 80px);',
			),
		);

		$this->add_control(
			'offset_sides',
			array_key_exists( 'offset_sides_custom_settings', $params )
					? $params['offset_sides_custom_settings'] + $offset_sides_shared_settings
					: $offset_sides_shared_settings
		);

		$this->add_responsive_control(
			'offset_width',
			array(
				'label'              => esc_html__( 'Ancho del asomo', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px' ),
				'frontend_available' => true,
				'condition'          => array(
					'offset_sides!' => 'none',
					'infinite'      => 'yes',
				),
				'default'            => array(
					'size' => 80,
				),
				'selectors'          => array(
					'{{WRAPPER}}' => '--digi-crs-offset: {{SIZE}}px',
				),
			)
		);

		$this->end_controls_section();
	}

	public function add_carousel_navigation_controls( $params = array() ) {
		$navigation_shared_settings = array(
			'label' => esc_html__( 'Navegación', 'pro-elements' ),
		);

		$this->start_controls_section(
			'section_navigation_settings',
			array_key_exists( 'navigation_custom_settings', $params )
				? $params['navigation_custom_settings'] + $navigation_shared_settings
				: $navigation_shared_settings
		);

		$this->add_control(
			'arrows',
			array(
				'label'              => esc_html__( 'Flechas', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_off'          => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'           => esc_html__( 'Mostrar', 'pro-elements' ),
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);

		foreach ( array( 'previous', 'next' ) as $type ) {
			$this->add_nested_arrow_controls( $type );
		}

		$this->end_controls_section();
	}

	/**
	 * Controles de una flecha (`previous` o `next`), en el orden y con los nombres de PRO: encabezado, icono y, en
	 * horizontal y en vertical, encabezado, orientación y posición.
	 */
	private function add_nested_arrow_controls( string $type ) {
		$prev   = 'previous' === $type;
		$v      = $prev ? 'prev' : 'next';
		$side   = $prev ? 'left' : 'right';
		$recommended = array(
			'fa-regular' => array(
				'arrow-alt-circle-' . $side,
				'caret-square-' . $side,
			),
			'fa-solid'   => array(
				'angle-double-' . $side,
				'angle-' . $side,
				'arrow-alt-circle-' . $side,
				'arrow-circle-' . $side,
				'arrow-' . $side,
				'caret-' . $side,
				'caret-square-' . $side,
				'chevron-circle-' . $side,
				'chevron-' . $side,
				'long-arrow-alt-' . $side,
			),
		);
		$on     = array(
			'arrows' => 'yes',
		);

		$heading = array(
			'type'      => Controls_Manager::HEADING,
			'label'     => $prev ? esc_html__( 'Flecha anterior', 'pro-elements' ) : esc_html__( 'Flecha siguiente', 'pro-elements' ),
			'condition' => $on,
		);
		if ( $prev ) {
			$heading['separator'] = 'before';
		}
		$this->add_control( $prev ? 'heading_previous_arrow' : 'heading_next_arrow', $heading );

		$this->add_control(
			'navigation_' . $type . '_icon',
			array(
				'label'            => esc_html__( 'Icono', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin'             => 'inline',
				'label_block'      => false,
				'skin_settings'    => array(
					'inline' => array(
						'icon' => array(
							'icon' => 'eicon-star',
						),
					),
				),
				'recommended'      => $recommended,
				'condition'        => $on,
				'default'          => array(
					'value'   => 'eicon-chevron-' . $side,
					'library' => 'eicons',
				),
			)
		);

		$this->add_control(
			'heading_' . $type . '_arrow_horizontal',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Horizontal', 'pro-elements' ),
				'condition' => $on,
			)
		);

		// PRO mide la anterior desde la izquierda y la siguiente desde la derecha; aquí, desde el inicio y el final.
		$this->add_responsive_control(
			'navigation_' . $type . '_icon_horizontal_orientation',
			array(
				'label'                => esc_html__( 'Orientación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => $this->get_arrows_horizontal_navigation_controls(),
				'default'              => $prev ? 'start' : 'end',
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'selectors_dictionary' => $prev ? array(
					'start'  => '--digi-crs-prev-ax: 0%; --digi-crs-prev-tx: 0px;',
					'center' => '--digi-crs-prev-ax: 50%; --digi-crs-prev-tx: -50%;',
					'end'    => '--digi-crs-prev-ax: 100%; --digi-crs-prev-tx: -100%;',
				) : array(
					'start'  => '--digi-crs-next-ax: 100%; --digi-crs-next-tx: 100%;',
					'center' => '--digi-crs-next-ax: 50%; --digi-crs-next-tx: 50%;',
					'end'    => '--digi-crs-next-ax: 0%; --digi-crs-next-tx: 0%;',
				),
				'condition'            => $on,
			)
		);

		$this->add_responsive_control(
			'navigation_' . $type . '_icon_horizontal_position',
			array_merge(
				$this->get_position_slider_initial_configuration(),
				array(
					'selectors' => array(
						'{{WRAPPER}}' => '--digi-crs-' . $v . '-px: {{SIZE}}{{UNIT}};',
					),
					'default'   => array(
						'size' => 0,
					),
					'condition' => $on,
				)
			)
		);

		$this->add_control(
			'heading_' . $type . '_arrow_vertical',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Vertical', 'pro-elements' ),
				'condition' => $on,
			)
		);

		$this->add_responsive_control(
			'navigation_' . $type . '_icon_vertical_orientation',
			array(
				'label'                => esc_html__( 'Orientación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => self::vertical_options(),
				'default'              => 'center',
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'selectors_dictionary' => array(
					'start'  => '--digi-crs-' . $v . '-ay: 0%; --digi-crs-' . $v . '-cs: 0px; --digi-crs-' . $v . '-ty: 0px;',
					'center' => '--digi-crs-' . $v . '-ay: 50%; --digi-crs-' . $v . '-ty: -50%;',
					'end'    => '--digi-crs-' . $v . '-ay: 100%; --digi-crs-' . $v . '-ty: -100%;',
				),
				'condition'            => $on,
			)
		);

		$position = array(
			'selectors' => array(
				'{{WRAPPER}}' => '--digi-crs-' . $v . '-py: {{SIZE}}{{UNIT}};',
			),
			'default'   => array(
				'size' => 0,
			),
			'condition' => $on,
		);
		if ( $prev ) {
			$position['separator'] = 'after';
		}
		$this->add_responsive_control(
			'navigation_' . $type . '_icon_vertical_position',
			array_merge( $this->get_position_slider_initial_configuration(), $position )
		);
	}

	public function add_carousel_navigation_styling_controls( $params = array() ) {
		$navigation_styling_shared_settings = array(
			'label' => esc_html__( 'Navegación', 'pro-elements' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		);

		$this->start_controls_section(
			'section_design_navigation',
			array_key_exists( 'navigation_styling_custom_settings', $params )
				? $params['navigation_styling_custom_settings'] + $navigation_styling_shared_settings
				: $navigation_styling_shared_settings
		);

		$this->add_control(
			'heading_icons',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => esc_html__( 'Iconos', 'pro-elements' ),
			)
		);

		$this->add_responsive_control(
			'arrows_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 5,
						'max' => 400,
					),
					'em'  => array(
						'min' => 0.1,
						'max' => 10,
					),
					'rem' => array(
						'min' => 0.1,
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-arrow-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'arrows_colors_border_shadow' );

		foreach ( array( 'normal', 'hover' ) as $state ) {
			$this->add_navigation_state_based_style_controls( $state, $params['css_prefix'] ?? '' );
		}

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'arrows_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					Engine::scope( ' > :is(.digi-crs__prev,.digi-crs__next)' ) => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'range'      => array(
					'em'  => array(
						'min' => 0,
						'max' => 5,
					),
					'rem' => array(
						'min' => 0,
						'max' => 5,
					),
					'px'  => array(
						'max' => 50,
					),
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'arrows_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					Engine::scope( ' > :is(.digi-crs__prev,.digi-crs__next)' ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'arrows_position',
			array(
				'label'     => esc_html__( 'Posición', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'inside',
				'options'   => array(
					'inside'  => esc_html__( 'Dentro', 'pro-elements' ),
					'outside' => esc_html__( 'Fuera', 'pro-elements' ),
				),
				'condition' => array(
					'arrows' => 'yes',
				),
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	public function add_carousel_pagination_controls( $params = array() ) {
		$pagination_shared_settings = array(
			'label' => esc_html__( 'Paginación', 'pro-elements' ),
		);

		$this->start_controls_section(
			'section_carousel_pagination',
			array_key_exists( 'section_carousel_pagination', $params )
				? $params['section_carousel_pagination'] + $pagination_shared_settings
				: $pagination_shared_settings
		);

		$this->add_control(
			'pagination',
			array(
				'label'              => esc_html__( 'Paginación', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'bullets',
				'options'            => array(
					''            => esc_html__( 'Ninguna', 'pro-elements' ),
					'bullets'     => esc_html__( 'Puntos', 'pro-elements' ),
					'fraction'    => esc_html__( 'Fracción', 'pro-elements' ),
					'progressbar' => esc_html__( 'Progreso', 'pro-elements' ),
				),
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);

		$this->end_controls_section();
	}

	public function add_carousel_pagination_style_controls( $params ) {
		$this->start_controls_section(
			'section_pagination_design',
			array(
				'label'     => esc_html__( 'Paginación', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'pagination!' => '',
				),
			)
		);

		$this->add_control(
			'heading_pagination_dots',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Puntos', 'pro-elements' ),
				'condition' => array(
					'pagination' => 'bullets',
				),
			)
		);

		$this->add_responsive_control(
			'dots_gap',
			array(
				'label'      => esc_html__( 'Separación entre puntos', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px' => array(
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-dot-gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'pagination' => 'bullets',
				),
			)
		);

		$this->add_responsive_control(
			'dots_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 5,
						'max' => 200,
					),
					'em'  => array(
						'min' => 0.5,
						'max' => 20,
					),
					'rem' => array(
						'min' => 0.5,
						'max' => 20,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-dot-size: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'pagination' => 'bullets',
				),
			)
		);

		$this->start_controls_tabs( 'dots_colors' );

		$this->start_controls_tab(
			'dots_normal_colors',
			array(
				'label'     => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => array(
					'pagination' => 'bullets',
				),
			)
		);

		$this->add_control(
			'dots_normal_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-crs-dot-color: {{VALUE}};',
				),
				'condition' => array(
					'pagination' => 'bullets',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'dots_hover_colors',
			array(
				'label'     => esc_html__( 'Al pasar el puntero', 'pro-elements' ),
				'condition' => array(
					'pagination' => 'bullets',
				),
			)
		);

		$this->add_control(
			'dots_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-crs-dot-hover: {{VALUE}};',
				),
				'condition' => array(
					'pagination' => 'bullets',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_custom_pagination( $params['css_prefix'] ?? '', 'dots' );

		$this->add_control(
			'dots_position',
			array(
				'label'     => esc_html__( 'Espacio desde las diapositivas', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'outside',
				'options'   => array(
					'inside'  => esc_html__( 'Ninguno', 'pro-elements' ),
					'outside' => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'condition' => array(
					'pagination' => 'bullets',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'dots_pagination_spacing',
			array(
				'label'      => esc_html__( 'Espacio', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'conditions' => array(
					'relation' => 'and',
					'terms'    => array(
						array(
							'name'     => 'pagination',
							'operator' => '==',
							'value'    => 'bullets',
						),
						array(
							'name'     => 'dots_position',
							'operator' => '==',
							'value'    => 'outside',
						),
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-pag-space: calc(var(--digi-crs-dot-size, 6px) + {{SIZE}}{{UNIT}});',
				),
			)
		);

		$this->add_control(
			'heading_pagination_fraction',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Fracción', 'pro-elements' ),
				'condition' => array(
					'pagination' => 'fraction',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'typography_fraction',
				'selector'       => Engine::scope( ' > .digi-crs__pagination' ),
				'condition'      => array(
					'pagination' => 'fraction',
				),
				'fields_options' => array(
					'font_size'   => array(
						'selectors' => array(
							'{{WRAPPER}}' => '--digi-crs-fraction-size: {{SIZE}}{{UNIT}};',
							Engine::scope( ' > .digi-crs__pagination' ) => 'font-size: {{SIZE}}{{UNIT}};',
						),
					),
					'line_height' => array(
						'selectors' => array(
							'{{WRAPPER}}' => '--digi-crs-fraction-size: {{SIZE}}{{UNIT}};',
							Engine::scope( ' > .digi-crs__pagination' ) => 'line-height: {{SIZE}}{{UNIT}};',
						),
					),
				),
			)
		);

		$this->add_control(
			'fraction_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-crs-fraction-color: {{VALUE}};',
				),
				'condition' => array(
					'pagination' => 'fraction',
				),
			)
		);

		$this->add_custom_pagination( $params['css_prefix'] ?? '', 'fraction' );

		$this->add_control(
			'fraction_position',
			array(
				'label'     => esc_html__( 'Espacio desde las diapositivas', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'outside',
				'options'   => array(
					'inside'  => esc_html__( 'Ninguno', 'pro-elements' ),
					'outside' => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'condition' => array(
					'pagination' => 'fraction',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'fraction_pagination_spacing',
			array(
				'label'      => esc_html__( 'Espacio', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'conditions' => array(
					'relation' => 'and',
					'terms'    => array(
						array(
							'name'     => 'pagination',
							'operator' => '==',
							'value'    => 'fraction',
						),
						array(
							'name'     => 'fraction_position',
							'operator' => '==',
							'value'    => 'outside',
						),
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-pag-space: calc(var(--digi-crs-fraction-size, 16px) + {{SIZE}}{{UNIT}});',
				),
			)
		);

		$this->add_control(
			'heading_pagination_progress',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Barra de progreso', 'pro-elements' ),
				'condition' => array(
					'pagination' => 'progressbar',
				),
			)
		);

		$this->add_responsive_control(
			'progressbar_height',
			array(
				'label'      => esc_html__( 'Altura', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 200,
					),
					'em'  => array(
						'max' => 20,
					),
					'rem' => array(
						'max' => 20,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-bar-size: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'pagination' => 'progressbar',
				),
			)
		);

		$this->start_controls_tabs( 'progressbar_colors' );

		$this->start_controls_tab(
			'progressbar_normal_colors',
			array(
				'label'     => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => array(
					'pagination' => 'progressbar',
				),
			)
		);

		$this->add_control(
			'progressbar_normal_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-crs-bar-color: {{VALUE}};',
				),
				'condition' => array(
					'pagination' => 'progressbar',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'progressbar_hover_colors',
			array(
				'label'     => esc_html__( 'Al pasar el puntero', 'pro-elements' ),
				'condition' => array(
					'pagination' => 'progressbar',
				),
			)
		);

		$this->add_control(
			'progressbar_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-crs-bar-hover: {{VALUE}};',
				),
				'condition' => array(
					'pagination' => 'progressbar',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Argumentos de `Engine::open()` a partir de los ajustes de estos controles. El widget fija antes
	 * `$this->num_of_carousel_items`; `classes`, `id` y demás se suman después con `array_merge()`.
	 */
	public function carousel_args( array $settings ): array {
		$pagination = (string) ( $settings['pagination'] ?? '' );
		$position   = 'inside';
		if ( 'bullets' === $pagination ) {
			$position = 'outside' === ( $settings['dots_position'] ?? 'outside' ) ? 'outside' : 'inside';
		} elseif ( 'fraction' === $pagination ) {
			$position = 'outside' === ( $settings['fraction_position'] ?? 'outside' ) ? 'outside' : 'inside';
		}
		$arrows = 'yes' === ( $settings['arrows'] ?? '' );
		return array(
			'label'               => (string) ( $settings['carousel_name'] ?? '' ),
			'count'               => (int) ( $this->num_of_carousel_items ?? 0 ),
			'autoplay'            => 'yes' === ( $settings['autoplay'] ?? '' ) ? max( 0, (int) ( $settings['autoplay_speed'] ?? 5000 ) ) : 0,
			'loop'                => 'yes' === ( $settings['infinite'] ?? '' ),
			'hover'               => 'yes' === ( $settings['pause_on_hover'] ?? '' ),
			'interaction'         => 'yes' === ( $settings['pause_on_interaction'] ?? '' ),
			'effect'              => 'slide',
			'speed'               => (int) ( $settings['speed'] ?? 500 ),
			'pagination'          => $pagination,
			'pagination_position' => $position,
			'arrows'              => $arrows,
			'arrows_position'     => 'outside' === ( $settings['arrows_position'] ?? 'inside' ) ? 'outside' : 'inside',
			'prev'                => $arrows ? self::nested_arrow_icon( $settings['navigation_previous_icon'] ?? null, 'eicon-chevron-left' ) : null,
			'next'                => $arrows ? self::nested_arrow_icon( $settings['navigation_next_icon'] ?? null, 'eicon-chevron-right' ) : null,
			'dir'                 => in_array( $settings['direction'] ?? '', array( 'ltr', 'rtl' ), true ) ? $settings['direction'] : '',
		);
	}

	/**
	 * HTML del icono de una flecha: `null` (el SVG propio de `Engine`, mismo trazo) con el chevron por defecto o sin
	 * ajuste; '' con el icono vaciado (como PRO, la flecha queda sin icono); si no, el de `Icons_Manager`.
	 */
	private static function nested_arrow_icon( $icon, string $default ): ?string {
		if ( ! is_array( $icon ) || $default === ( $icon['value'] ?? $default ) ) {
			return null;
		}
		if ( empty( $icon['value'] ) || ! class_exists( Icons_Manager::class ) ) {
			return '';
		}
		$html = Icons_Manager::try_get_icon_html( $icon, array( 'aria-hidden' => 'true' ) );
		return is_string( $html ) ? $html : '';
	}

	/** Pestaña Normal o Al pasar el puntero de las flechas: color, fondo, borde y sombra (nombres de PRO). */
	private function add_navigation_state_based_style_controls( string $state, $css_prefix ) {
		$label    = esc_html__( 'Normal', 'pro-elements' );
		$selector = Engine::scope( ' > :is(.digi-crs__prev,.digi-crs__next)' );
		$var      = '--digi-crs-arrow-color';
		if ( 'hover' === $state ) {
			$label    = esc_html__( 'Al pasar el puntero', 'pro-elements' );
			$selector = Engine::scope( ' > :is(.digi-crs__prev:hover,.digi-crs__next:hover)' );
			$var      = '--digi-crs-arrow-hover';
		}
		$this->start_controls_tab(
			'arrows_' . $state . '_colors_border_shadow_tab',
			array(
				'label' => $label,
			)
		);

		$this->add_control(
			'arrow_' . $state . '_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => $var . ': {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'arrows_' . $state . '_background',
				'types'    => array( 'classic', 'gradient' ),
				'exclude'  => array( 'image' ),
				'selector' => $selector,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'arrows_' . $state . '_border_type',
				'selector'       => $selector,
				'fields_options' => array(
					'color' => array(
						'label' => esc_html__( 'Color del borde', 'pro-elements' ),
					),
					'width' => array(
						'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
					),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'arrows_' . $state . '_box_shadow',
				'selector' => $selector,
			)
		);

		$this->end_controls_tab();
	}

	/**
	 * Posición personalizada de la paginación (`dots` o `fraction`), con los nombres de PRO (también
	 * `heading_dotspagination_vertical`, sin guion bajo, como allí).
	 */
	private function add_custom_pagination( string $css_prefix, string $pagination_type ) {
		$start_logical = is_rtl() ? 'right' : 'left';
		$end_logical   = is_rtl() ? 'left' : 'right';
		$condition     = 'dots' === $pagination_type ? 'bullets' : $pagination_type;
		$pagination_type_custom_position_switcher = $pagination_type . '_custom_position';

		$this->add_control(
			$pagination_type_custom_position_switcher,
			array(
				'label'     => esc_html__( 'Posición personalizada', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'condition' => array(
					'pagination' => $condition,
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'heading_' . $pagination_type . '_pagination_horizontal',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Horizontal', 'pro-elements' ),
				'condition' => array(
					$pagination_type_custom_position_switcher => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			$pagination_type . '_horizontal_position',
			array(
				'label'                => esc_html__( 'Orientación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start'  => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-h-align-' . $start_logical,
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-h-align-center',
					),
					'end'    => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-h-align-' . $end_logical,
					),
				),
				'default'              => 'center',
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'selectors_dictionary' => array(
					'start'  => '--digi-crs-pag-ax: 0px; --digi-crs-pag-tx: 0%;',
					'center' => '--digi-crs-pag-ax: 50%; --digi-crs-pag-tx: -50%;',
					'end'    => '--digi-crs-pag-ax: 100%; --digi-crs-pag-tx: -100%;',
				),
				'condition'            => array(
					$pagination_type_custom_position_switcher => 'yes',
					'pagination'                              => $condition,
				),
			)
		);

		$this->add_responsive_control(
			$pagination_type . '_horizontal_offset',
			array_merge(
				$this->get_position_slider_initial_configuration(),
				array(
					'selectors' => array(
						'{{WRAPPER}}' => '--digi-crs-pag-ox: {{SIZE}}{{UNIT}};',
					),
					'default'   => array(
						'size' => 0,
					),
					'condition' => array(
						$pagination_type_custom_position_switcher => 'yes',
						'pagination'                              => $condition,
					),
				)
			)
		);

		$this->add_control(
			'heading_' . $pagination_type . 'pagination_vertical',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Vertical', 'pro-elements' ),
				'condition' => array(
					$pagination_type_custom_position_switcher => 'yes',
					'pagination'                              => $condition,
				),
			)
		);

		$this->add_responsive_control(
			$pagination_type . '_vertical_position',
			array(
				'label'                => esc_html__( 'Orientación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => self::vertical_options(),
				'default'              => 'end',
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'selectors_dictionary' => array(
					'start'  => '--digi-crs-pag-ay: 0%; --digi-crs-pag-ty: 0%;',
					'center' => '--digi-crs-pag-ay: 50%; --digi-crs-pag-ty: -50%;',
					'end'    => '--digi-crs-pag-ay: 100%; --digi-crs-pag-ty: -100%;',
				),
				'condition'            => array(
					$pagination_type_custom_position_switcher => 'yes',
					'pagination'                              => $condition,
				),
			)
		);

		$this->add_responsive_control(
			$pagination_type . '_vertical_offset',
			array_merge(
				$this->get_position_slider_initial_configuration(),
				array(
					'selectors' => array(
						'{{WRAPPER}}' => '--digi-crs-pag-oy: {{SIZE}}{{UNIT}};',
					),
					'default'   => array(
						'size' => 0,
					),
					'condition' => array(
						$pagination_type_custom_position_switcher => 'yes',
						'pagination'                              => $condition,
					),
					'separator' => 'after',
				)
			)
		);
	}

	/** Opciones de orientación vertical (inicio, centro y final). */
	private static function vertical_options(): array {
		return array(
			'start'  => array(
				'title' => esc_html__( 'Inicio', 'pro-elements' ),
				'icon'  => 'eicon-v-align-top',
			),
			'center' => array(
				'title' => esc_html__( 'Centro', 'pro-elements' ),
				'icon'  => 'eicon-v-align-middle',
			),
			'end'    => array(
				'title' => esc_html__( 'Final', 'pro-elements' ),
				'icon'  => 'eicon-v-align-bottom',
			),
		);
	}

	/** Configuración común de los deslizadores de posición (mismas unidades y rangos que PRO). */
	private function get_position_slider_initial_configuration(): array {
		return array(
			'label'      => esc_html__( 'Posición', 'pro-elements' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ),
			'range'      => array(
				'px'  => array(
					'min' => -1000,
					'max' => 1000,
				),
				'%'   => array(
					'min' => -200,
					'max' => 200,
				),
				'em'  => array(
					'min' => -50,
					'max' => 50,
				),
				'rem' => array(
					'min' => -50,
					'max' => 50,
				),
				'vw'  => array(
					'min' => -200,
					'max' => 200,
				),
				'vh'  => array(
					'min' => -200,
					'max' => 200,
				),
			),
		);
	}

	/** Opciones de orientación horizontal de las flechas (en RTL, invertidas como en PRO). */
	private function get_arrows_horizontal_navigation_controls(): array {
		$navigation_controls = array(
			'start'  => array(
				'title' => is_rtl() ? esc_html__( 'Final', 'pro-elements' ) : esc_html__( 'Inicio', 'pro-elements' ),
				'icon'  => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => esc_html__( 'Centro', 'pro-elements' ),
				'icon'  => 'eicon-h-align-center',
			),
			'end'    => array(
				'title' => is_rtl() ? esc_html__( 'Inicio', 'pro-elements' ) : esc_html__( 'Final', 'pro-elements' ),
				'icon'  => 'eicon-h-align-right',
			),
		);

		return is_rtl() ? array_reverse( $navigation_controls ) : $navigation_controls;
	}
}
