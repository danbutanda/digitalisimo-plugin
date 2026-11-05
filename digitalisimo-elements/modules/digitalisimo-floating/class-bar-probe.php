<?php
namespace Digitalisimo\Elements\Floating;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Modules\FloatingButtons\Base\Widget_Floating_Bars_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Sonda de la Barra flotante (C8): construye los controles de una presentación sobre la base del núcleo
 * (`Widget_Floating_Bars_Base`). La configuración y las secciones de cada presentación repiten las de las variantes de PRO
 * («Ticker», `floating-bars-var-2`, y «Coupon», `floating-bars-var-3`; código GPL de PRO Elements,
 * `modules/floating-buttons/widgets/floating-bars-var-{2,3}.php`, con los textos en español).
 */
class Bar_Probe extends Widget_Floating_Bars_Base {
	use Probe;

	const FAMILY = 'floating-bar';

	/** Presentaciones, en el orden del control `presentation`. */
	const PRESENTATIONS = array( '2', '3' );

	public static function get_configuration() {
		return self::configure( parent::get_configuration(), '' === self::presentation() ? '2' : self::presentation() );
	}

	/** Configuración de la base del núcleo con los cambios de cada variante de PRO. */
	public static function configure( array $config, string $presentation ): array {
		if ( '2' === $presentation ) {
			$config['content']['floating_bar_section']['close_switch_default'] = 'no';
			$config['content']['floating_bar_section']['has_pause_switch']     = true;
			$config['style']['floating_bar_section']['has_close_bg']               = true;
			$config['style']['floating_bar_section']['close_offset']               = '0';
			$config['style']['floating_bar_section']['close_position_selector']    = 'align-self: {{VALUE}}';
			$config['style']['floating_bar_section']['has_close_position_control'] = false;
		}
		if ( '3' === $presentation ) {
			$config['content']['announcement_section']['icon_default'] = array(
				'value'   => 'fas fa-star',
				'library' => 'fa-solid',
			);
			$config['content']['announcement_section']['text_label']              = esc_html__( 'Texto de la oferta', 'pro-elements' );
			$config['content']['announcement_section']['text_default']            = esc_html__( '¡10 % de descuento en tu primera compra!', 'pro-elements' );
			$config['content']['floating_bar_section']['accessible_name_default'] = esc_html__( 'Banner del cupón', 'pro-elements' );
			$config['style']['floating_bar_section']['align_elements_selector']   = array(
				'{{WRAPPER}} .e-floating-bars'                    => 'justify-content: {{VALUE}};',
				'{{WRAPPER}} .e-floating-bars__coupon-button'     => 'justify-self: {{VALUE}};',
				'{{WRAPPER}} .e-floating-bars__announcement-text' => 'text-align: {{VALUE}};',
			);
		}
		return $config;
	}

	protected function add_content_tab(): void {
		if ( '3' === self::presentation() ) {
			$this->add_announcement_content_section();
			$this->p3_coupon_content_section();
			$this->add_floating_bar_content_section();
			return;
		}
		$this->add_headlines_content_section();
		$this->p2_ticker_content_section();
		$this->add_floating_bar_content_section();
	}

	protected function add_style_tab(): void {
		if ( '3' === self::presentation() ) {
			$this->add_announcement_style_section();
			$this->p3_coupon_style_section();
			$this->add_floating_bar_style_section();
			return;
		}
		$this->add_headlines_style_section();
		$this->add_floating_bar_style_section();
	}

	protected function add_floating_bar_style_section(): void {
		if ( '2' === self::presentation() ) {
			$this->p2_floating_bar_style_section();
			return;
		}
		parent::add_floating_bar_style_section();
	}

	protected function add_accessible_name_control(): void {
		if ( '2' === self::presentation() ) {
			$this->p2_accessible_name_control();
			return;
		}
		parent::add_accessible_name_control();
	}

	protected function p2_floating_bar_style_section(): void {
		$this->start_controls_section(
			'style_floating_bar',
			[
				'label' => esc_html__( 'Barra flotante', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'style_floating_bar_horizontal_position',
			[
				'label' => esc_html__( 'Posición horizontal de los controles', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					],
					'end' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					],
				],
				'default' => 'end',
				'toggle' => true,
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-ticker-controls-order: {{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '-1',
					'end' => '2',
				],
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => 'floating_bar_close_switch',
							'operator' => '===',
							'value' => 'yes',
						],
						[
							'name' => 'floating_bar_pause_switch',
							'operator' => '===',
							'value' => 'yes',
						],
					],
				],
			]
		);

		$this->add_responsive_control(
			'style_floating_bar_controls_size',
			[
				'label' => esc_html__( 'Tamaño de los controles', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 150,
					],
				],
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-controls-icon-size: {{SIZE}}{{UNIT}}',
				],
				'separator' => 'after',
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => 'floating_bar_close_switch',
							'operator' => '===',
							'value' => 'yes',
						],
						[
							'name' => 'floating_bar_pause_switch',
							'operator' => '===',
							'value' => 'yes',
						],
					],
				],
			]
		);

		$this->add_floating_bar_pause_style_controls();

		$this->add_floating_bar_close_button_style_controls();

		$this->add_responsive_control(
			'style_floating_bar_element_spacing',
			[
				'label' => esc_html__( 'Espacio entre titulares', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-headlines-gap: {{SIZE}}{{UNIT}}',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'style_floating_bar_padding',
			[
				'label' => esc_html__( 'Relleno vertical', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-vertical-padding: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_floating_bar_background_style_controls();

		$this->end_controls_section();
	}

	protected function p2_accessible_name_control(): void {
		$this->add_control(
			'accessible_name',
			[
				'label' => esc_html__( 'Nombre accesible', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'placeholder' => esc_html__( 'Escribe un texto', 'pro-elements' ),
				'default' => esc_html__( 'Banner', 'pro-elements' ),
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => 'floating_bar_close_switch',
							'operator' => '===',
							'value' => 'yes',
						],
						[
							'name' => 'floating_bar_pause_switch',
							'operator' => '===',
							'value' => 'yes',
						],
					],
				],
			],
		);
	}

	protected function p2_ticker_content_section(): void {
		$this->start_controls_section(
			'style_ticker',
			[
				'label' => esc_html__( 'Cinta', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_responsive_control(
			'style_ticker_animation_type',
			[
				'label' => esc_html__( 'Tipo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'autoplay',
				'mobile_default' => 'autoplay',
				'options' => [
					'autoplay' => esc_html__( 'Reproducción automática', 'pro-elements' ),
					'scroll' => esc_html__( 'Al desplazar la página', 'pro-elements' ),
				],
				'frontend_available' => true,
			]
		);

		$this->add_responsive_control(
			'style_ticker_scroll_direction',
			[
				'label' => esc_html__( 'Dirección del desplazamiento', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'forwards',
				'mobile_default' => 'forwards',
				'toggle' => false,
				'options' => [
					'forwards' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-arrow-left',
					],
					'reverse' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-arrow-right',
					],
				],
				'selectors_dictionary' => [
					'forwards' => is_rtl() ? 'reverse' : 'forwards',
					'reverse' => is_rtl() ? 'forwards' : 'reverse',
				],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-scroll-direction: {{VALUE}};',
				],
				'frontend_available' => true,
				'condition' => [
					'style_ticker_animation_type' => 'autoplay',
				],
			]
		);

		$this->add_control(
			'style_ticker_scroll_speed',
			[
				'label' => esc_html__( 'Velocidad del desplazamiento', 'pro-elements' ) . ' (s)',
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0.5,
						'max' => 100,
						'step' => 0.5,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-scroll-duration: {{SIZE}}s;',
				],
				'condition' => [
					'style_ticker_animation_type' => 'autoplay',
				],
			]
		);

		$this->add_control(
			'style_ticker_pause_hover',
			[
				'label' => esc_html__( 'Pausar al pasar el cursor', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition' => [
					'style_ticker_animation_type' => 'autoplay',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function p3_coupon_content_section(): void {
		$this->start_controls_section(
			'coupon_content_section',
			[
				'label' => __( 'Cupón', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'coupon_code',
			[
				'label' => esc_html__( 'Código', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'label_block' => false,
				'placeholder' => esc_html__( 'Escribe el código del cupón', 'pro-elements' ),
				'default' => esc_html__( 'NEW10', 'pro-elements' ),
			],
		);

		$this->add_control(
			'coupon_copy_icon',
			[
				'label' => esc_html__( 'Icono de copiar', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin' => 'inline',
				'default' => [
					'value' => 'fas fa-copy',
					'library' => 'fa-solid',
				],
				'recommended' => [
					'fa-regular' => [
						'clipboard',
						'copy',
					],
					'fa-solid' => [
						'clipboard',
						'copy',
					],
				],
				'label_block' => false,
				'icon_exclude_inline_options' => [],
			]
		);

		$this->add_control(
			'coupon_success_message',
			[
				'label' => esc_html__( 'Mensaje de éxito', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'label_block' => false,
				'placeholder' => esc_html__( 'Escribe un texto', 'pro-elements' ),
				'default' => esc_html__( 'Copiado', 'pro-elements' ),
			],
		);

		$this->add_control(
			'coupon_success_icon',
			[
				'label' => esc_html__( 'Icono de éxito', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin' => 'inline',
				'default' => [
					'value' => 'fas fa-check',
					'library' => 'fa-solid',
				],
				'recommended' => [
					'fa-regular' => [
						'check-circle',
						'check-square',
					],
					'fa-solid' => [
						'check',
						'check-circle',
						'check-square',
					],
				],
				'label_block' => false,
				'icon_exclude_inline_options' => [],
			]
		);

		$this->end_controls_section();
	}

	protected function p3_coupon_style_section(): void {
		$this->start_controls_section(
			'style_coupon',
			[
				'label' => esc_html__( 'Cupón', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'style_coupon_type',
			[
				'label' => esc_html__( 'Tipo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'cutout',
				'options' => [
					'text' => esc_html__( 'Texto', 'pro-elements' ),
					'cutout' => esc_html__( 'Recorte', 'pro-elements' ),
					'price-tag' => esc_html__( 'Etiqueta de precio', 'pro-elements' ),
					'gift-tag' => esc_html__( 'Etiqueta de regalo', 'pro-elements' ),
					'round-ticket' => esc_html__( 'Boleto redondo', 'pro-elements' ),
					'square-ticket' => esc_html__( 'Boleto cuadrado', 'pro-elements' ),
				],
			]
		);

		$this->add_control(
			'style_coupon_icon_position',
			[
				'label' => esc_html__( 'Posición del icono', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => is_rtl() ? 'row-reverse' : 'row',
				'toggle' => false,
				'options' => [
					'row' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					],
					'row-reverse' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					],
				],
				'selectors_dictionary' => [
					'row' => is_rtl() ? 'row-reverse' : 'row',
					'row-reverse' => is_rtl() ? 'row' : 'row-reverse',
				],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars__coupon-text-group' => 'flex-direction: {{VALUE}};',
				],
				'condition' => [
					'coupon_copy_icon[value]!' => '',
				],
			]
		);

		$this->add_control(
			'style_coupon_icon_spacing',
			[
				'label' => esc_html__( 'Espacio del icono', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 50,
					],
					'em' => [
						'max' => 5,
					],
					'rem' => [
						'max' => 5,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-coupon-icon-gap: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'coupon_copy_icon[value]!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'style_coupon_typography',
				'selector' => '{{WRAPPER}} .e-floating-bars__coupon-button',
			]
		);

		$this->add_control(
			'style_coupon_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-coupon-button-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'style_coupon_bg_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-coupon-bg-color: {{VALUE}}',
				],
				'condition' => [
					'style_coupon_type!' => 'text',
				],
			]
		);

		$this->add_control(
			'style_coupon_show_border',
			[
				'label' => esc_html__( 'Borde', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition' => [
					'style_coupon_type!' => 'text',
				],
			]
		);

		$this->add_responsive_control(
			'style_coupon_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'%' => [
						'min' => 10,
						'max' => 100,
					],
					'px' => [
						'min' => 0,
						'max' => 10,
					],
				],
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-coupon-border-width: {{SIZE}}{{UNIT}}',
				],
				'conditions' => [
					'relation' => 'and',
					'terms' => [
						[
							'name' => 'style_coupon_show_border',
							'operator' => '===',
							'value' => 'yes',
						],
						[
							'name' => 'style_coupon_type',
							'operator' => '!==',
							'value' => 'text',
						],
					],
				],
			]
		);

		$this->add_control(
			'style_coupon_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-coupon-border-color: {{VALUE}}',
				],
				'conditions' => [
					'relation' => 'and',
					'terms' => [
						[
							'name' => 'style_coupon_show_border',
							'operator' => '===',
							'value' => 'yes',
						],
						[
							'name' => 'style_coupon_type',
							'operator' => '!==',
							'value' => 'text',
						],
					],
				],
			]
		);

		$this->add_control(
			'style_coupon_success_message_duration',
			[
				'label' => esc_html__( 'Duración del mensaje de éxito', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms' ],
				'default' => [
					'unit' => 's',
				],
				'frontend_available' => true,
			]
		);

		$this->add_responsive_control(
			'style_coupon_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem' ],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-coupon-padding-block-end: {{BOTTOM}}{{UNIT}} !important; --e-floating-bars-coupon-padding-block-start: {{TOP}}{{UNIT}} !important; --e-floating-bars-coupon-padding-inline-end: {{RIGHT}}{{UNIT}} !important; --e-floating-bars-coupon-padding-inline-start: {{LEFT}}{{UNIT}} !important;',
				],
				'condition' => [
					'style_coupon_type!' => [
						'text',
						'price-tag',
						'gift-tag',
						'round-ticket',
						'square-ticket',
					],
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'style_coupon_animation',
			[
				'label' => esc_html__( 'Animación de entrada', 'pro-elements' ),
				'type' => Controls_Manager::ANIMATION,
				'frontend_available' => true,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'style_coupon_animation_duration',
			[
				'label' => esc_html__( 'Duración de la animación', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => '1000',
				'options' => [
					'2000' => esc_html__( 'Lenta', 'pro-elements' ),
					'1000' => esc_html__( 'Normal', 'pro-elements' ),
					'800' => esc_html__( 'Rápida', 'pro-elements' ),
				],
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-coupon-animation-duration: {{VALUE}}ms',
				],
				'prefix_class' => 'animated-',
				'conditions' => [
					'relation' => 'and',
					'terms' => [
						[
							'name' => 'style_coupon_animation',
							'operator' => '!==',
							'value' => '',
						],
						[
							'name' => 'style_coupon_animation',
							'operator' => '!==',
							'value' => 'none',
						],
					],
				],
			]
		);

		$this->add_control(
			'style_coupon_animation_delay',
			[
				'label' => esc_html__( 'Retraso de la animación', 'pro-elements' ) . ' (ms)',
				'type' => Controls_Manager::NUMBER,
				'min' => 0,
				'step' => 100,
				'selectors' => [
					'{{WRAPPER}} .e-floating-bars' => '--e-floating-bars-coupon-animation-delay: {{SIZE}}ms;',
				],
				'render_type' => 'none',
				'frontend_available' => true,
				'conditions' => [
					'relation' => 'and',
					'terms' => [
						[
							'name' => 'style_coupon_animation',
							'operator' => '!==',
							'value' => '',
						],
						[
							'name' => 'style_coupon_animation',
							'operator' => '!==',
							'value' => 'none',
						],
					],
				],
			]
		);

		$this->end_controls_section();
	}
}
