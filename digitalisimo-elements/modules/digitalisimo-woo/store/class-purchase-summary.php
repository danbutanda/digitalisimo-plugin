<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Background;

defined( 'ABSPATH' ) || exit;

/**
 * Resumen de la compra (`digitalisimo-woo-purchase-summary`): sustituye al widget «Purchase Summary» de PRO
 * (`modules/woocommerce/widgets/purchase-summary.php`, mapa `maps/woocommerce-purchase-summary.php`). Repite todos sus
 * controles con el mismo nombre, valor por defecto, condiciones y selectores, y pinta lo mismo: en la página de pedido
 * recibido (`order-received`), WooCommerce dibuja el resumen con `[woocommerce_checkout]` y sus plantillas; el widget
 * sólo cambia, con los filtros de PRO, el mensaje de confirmación y los títulos (`gettext`), y en el editor y la vista
 * previa muestra el último pedido o el elegido. Envoltura propia `digi-psum`; script propio sin jQuery
 * (`store/assets/purchase-summary.js`) que iguala la altura de las direcciones, junta la nota de compra con su producto y
 * pone la animación del botón, como PRO.
 */
class Purchase_Summary extends Base {

	private $order_id = null;
	private $order_key = null;

	public function get_name() {
		return 'digitalisimo-woo-purchase-summary';
	}

	public function get_title() {
		return esc_html__( 'Resumen de la compra', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-purchase-summary';
	}

	public function get_keywords() {
		return [ 'woocommerce', 'tienda', 'resumen', 'gracias', 'confirmación', 'compra', 'pedido', 'summary', 'thank you', 'purchase' ];
	}

	/** Vista previa del editor o de WordPress (`Module::is_preview()` de PRO). */
	public static function is_preview_mode() {
		return ( isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) || is_preview();
	}

	public function get_categories() {
		return [ 'woocommerce-elements' ];
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}


	protected function register_controls() {
		$this->start_controls_section(
			'confirmation_message',
			[
				'label' => esc_html__( 'Mensaje de confirmación', 'pro-elements' ),
			]
		);

		$this->add_control(
			'confirmation_message_active',
			[
				'label' => esc_html__( 'Mensaje de confirmación', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default' => 'yes',
				'selectors' => [
					'{{WRAPPER}}' => '--dp-cmd: block;',
				],
			]
		);

		$this->add_control(
			'confirmation_message_text',
			[
				'label' => esc_html__( 'Mensaje', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'default' => esc_html__( 'Gracias. Hemos recibido tu pedido.', 'pro-elements' ),
				'label_block' => true,
				'condition' => [
					'confirmation_message_active!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'confirmation_message_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'condition' => [
					'confirmation_message_active!' => '',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-cma: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'payment_details',
			[
				'label' => esc_html__( 'Datos del pago', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_details_number',
			[
				'label' => esc_html__( 'Número', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Número de pedido:', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_details_date',
			[
				'label' => esc_html__( 'Fecha:', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Fecha del pedido:', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_details_email',
			[
				'label' => esc_html__( 'Correo electrónico', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Correo del pedido:', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_details_total',
			[
				'label' => esc_html__( 'Total', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Total del pedido:', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_details_payment',
			[
				'label' => esc_html__( 'Pago', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Método de pago:', 'pro-elements' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'bank_details',
			[
				'label' => esc_html__( 'Datos bancarios', 'pro-elements' ),
			]
		);

		$this->add_control(
			'bank_details_text',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Nuestros datos bancarios', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'bank_details_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bda: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'downloads',
			[
				'label' => esc_html__( 'Descargas', 'pro-elements' ),
			]
		);

		$this->add_control(
			'downloads_text',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Descargas', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'downloads_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-da: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'order_summary',
			[
				'label' => esc_html__( 'Resumen de la compra', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_text',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Detalles del pedido', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'order_summary_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-osa: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'billing_details',
			[
				'label' => esc_html__( 'Datos de facturación', 'pro-elements' ),
			]
		);

		$this->add_control(
			'billing_details_text',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Datos de facturación', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'billing_details_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bda2: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'shipping_details',
			[
				'label' => esc_html__( 'Dirección de envío', 'pro-elements' ),
			]
		);

		$this->add_control(
			'shipping_details_text',
			[
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Datos de envío', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'shipping_details_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-sda: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'preview_order',
			[
				'label' => esc_html__( 'Ajustes de la vista previa', 'pro-elements' ),
			]
		);

		$this->add_control(
			'preview_order_type',
			[
				'label' => esc_html__( 'Previsualizar con', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Último pedido', 'pro-elements' ),
					'custom-order' => esc_html__( 'ID del pedido', 'pro-elements' ),
				],
			]
		);

		$this->add_control(
			'preview_order_custom',
			[
				'label' => esc_html__( 'ID del pedido', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'preview_order_type' => 'custom-order',
				],
				'ai' => [
					'active' => false,
				],
				'render_type' => 'template',
				'description' => esc_html__( 'Nota: el ID de un pedido está en el escritorio de WordPress, en WooCommerce > Pedidos.', 'pro-elements' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'sections_tabs_style',
			[
				'label' => esc_html__( 'Secciones', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'sections_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-sbc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'sections_box_shadow',
				'selector' => '{{WRAPPER}} .shop_table, {{WRAPPER}} address',
			]
		);

		$this->add_control(
			'sections_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--dp-sbt: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'sections_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .shop_table, {{WRAPPER}} address' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'sections_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'sections_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-sbc2: {{VALUE}};',
				],
				'condition' => [
					'sections_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'sections_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-sbr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'sections_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-sp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'sections_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-ss: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'typography_title',
			[
				'label' => esc_html__( 'Tipografía', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'confirmation_message_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Mensaje de confirmación', 'pro-elements' ),
			]
		);

		$this->add_control(
			'confirmation_message_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-cmc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'confirmation_message_typography',
				'selector' => '{{WRAPPER}} .woocommerce-thankyou-order-received',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'confirmation_message_text_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-thankyou-order-received',
			]
		);

		$this->add_control(
			'titles_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'titles_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-tc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'titles_typography',
				'selector' => '{{WRAPPER}} h2',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'titles_text_shadow',
				'selector' => '{{WRAPPER}} h2',
			]
		);

		$this->add_responsive_control(
			'titles_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-ts: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'general_text_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Texto general', 'pro-elements' ),
			]
		);

		$this->add_control(
			'general_text_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-gtc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'general_text_typography',
				'selector' => '{{WRAPPER}} address, {{WRAPPER}} .product-purchase-note, {{WRAPPER}} .woocommerce-thankyou-order-details + p',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'payment_details_title',
			[
				'label' => esc_html__( 'Datos del pago', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'payment_details_space_between',
			[
				'label' => esc_html__( 'Espacio entre elementos', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-pdsb: {{SIZE}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'payment_details_titles_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_details_titles_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-pdtc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'payment_details_titles_typography',
				'selector' => '{{WRAPPER}} .woocommerce-order-overview.order_details li',
				'exclude' => [
					'text_decoration',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'payment_details_titles_text_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-order-overview.order_details li',
			]
		);

		$this->add_responsive_control(
			'payment_details_titles_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-pdts: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'payment_details_items_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Elementos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_details_items_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-pdic: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'payment_details_items_typography',
				'selector' => '{{WRAPPER}} .woocommerce-order-overview.order_details li strong',
				'exclude' => [
					'text_decoration',
				],
			]
		);

		$this->add_control(
			'payment_details_dividers_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Divisores', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'payment_details_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--dp-pdbt: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'payment_details_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-pdbw: {{SIZE}}{{UNIT}};',
				],

				'condition' => [
					'payment_details_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'payment_details_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-pdbc: {{VALUE}};',
				],
				'condition' => [
					'payment_details_border_type!' => 'none',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'bank_details_title',
			[
				'label' => esc_html__( 'Datos bancarios', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'bank_details_space_between',
			[
				'label' => esc_html__( 'Espacio entre elementos', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bdsb: {{SIZE}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'bank_details_account_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Titular de la cuenta', 'pro-elements' ),
			]
		);

		$this->add_control(
			'account_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-atc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'account_title_typography',
				'selector' => '{{WRAPPER}} .wc-bacs-bank-details-account-name',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'account_title_text_shadow',
				'selector' => '{{WRAPPER}} .wc-bacs-bank-details-account-name',
			]
		);

		$this->add_responsive_control(
			'account_title_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-ats: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'bank_details_titles_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'bank_details_titles_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bdtc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'bank_details_titles_typography',
				'selector' => '{{WRAPPER}} .woocommerce-bacs-bank-details .wc-bacs-bank-details li',
				'exclude' => [
					'text_decoration',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'bank_details_titles_text_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-bacs-bank-details .wc-bacs-bank-details li',
			]
		);

		$this->add_responsive_control(
			'bank_details_titles_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bdts: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'bank_details_items_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Elementos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'bank_details_items_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bdic: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'bank_details_items_typography',
				'selector' => '{{WRAPPER}} .woocommerce-bacs-bank-details .wc-bacs-bank-details li strong',
				'exclude' => [
					'text_decoration',
				],
			]
		);

		$this->add_control(
			'bank_details_dividers_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Divisores', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'bank_details_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bdbt: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'bank_details_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bdbw: {{SIZE}}{{UNIT}};',
				],

				'condition' => [
					'bank_details_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'bank_details_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bdbc: {{VALUE}};',
				],
				'condition' => [
					'bank_details_border_type!' => 'none',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'order_details_title',
			[
				'label' => esc_html__( 'Detalles del pedido', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'order_details_rows_gap',
			[
				'label' => esc_html__( 'Separación entre filas', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--dp-odrg: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'order_details_titles_totals',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos y totales', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_details_titles_totals_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-odttc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_details_titles_totals_typography',
				'selector' => '{{WRAPPER}} .shop_table thead tr th, {{WRAPPER}} .shop_table tfoot th, {{WRAPPER}} .shop_table tfoot tr td, {{WRAPPER}} .shop_table tfoot tr td span, {{WRAPPER}} .woocommerce-table--order-downloads tr td:before',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'order_details_titles_totals_text_shadow',
				'selector' => '{{WRAPPER}} .shop_table thead tr th, {{WRAPPER}} .shop_table tfoot th, {{WRAPPER}} .shop_table tfoot tr td, {{WRAPPER}} .shop_table tfoot tr td span, {{WRAPPER}} .woocommerce-table--order-downloads tr td:before',
			]
		);

		$this->add_control(
			'order_details_items_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Elementos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_details_items_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-odic: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_details_items_typography',
				'selector' => '{{WRAPPER}} .product-quantity, {{WRAPPER}} .woocommerce-table--order-details td a, {{WRAPPER}} td.product-total, {{WRAPPER}} td.download-product, {{WRAPPER}} td.download-remaining, {{WRAPPER}} td.download-expires, {{WRAPPER}} td.download-file',
			]
		);

		$this->add_control(
			'order_details_variations_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Variaciones', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_details_variations_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-odvc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_details_variations_typography',
				'selector' => '{{WRAPPER}} .product-name .wc-item-meta .wc-item-meta-label, {{WRAPPER}} .wc-item-meta li p',
			]
		);

		$this->add_control(
			'order_details_product_links_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlace del producto', 'pro-elements' ),
			]
		);

		$this->start_controls_tabs( 'order_details_product_links_colors' );

		$this->start_controls_tab( 'order_details_product_links_normal_colors', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'order_details_product_links_normal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-odplnc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'order_details_product_links_hover_colors', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'order_details_product_links_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-odplhc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'order_details_dividers_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Divisores', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'order_details_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--dp-tdbt: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_details_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-tdbw: {{SIZE}}{{UNIT}};',
				],

				'condition' => [
					'order_details_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'order_details_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-tdbc: {{VALUE}};',
				],
				'condition' => [
					'order_details_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'order_details_button_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botones', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_details_button_typography',
				'selector' => '{{WRAPPER}} .shop_table .button, {{WRAPPER}} .order-again .button',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'order_details_button_text_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button, {{WRAPPER}} .order-again .button',
			]
		);

		$this->start_controls_tabs( 'order_details_button_styles' );

		$this->start_controls_tab( 'order_details_button_styles_normal', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'order_details_button_normal_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bntc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'order_details_button_normal_background',
				'selector' => '{{WRAPPER}} .shop_table .button, {{WRAPPER}} .order-again .button',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'order_details_button_normal_box_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button, {{WRAPPER}} .order-again .button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'order_details_button_styles_hover', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'order_details_button_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bhtc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'order_details_button_hover_background',
				'selector' => '{{WRAPPER}} .shop_table .button:hover, {{WRAPPER}} .order-again .button:hover',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'order_details_button_hover_box_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button:hover, {{WRAPPER}} .order-again .button:hover',
			]
		);

		$this->add_control(
			'order_details_button_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .shop_table .button:hover, {{WRAPPER}} .order-again .button:hover' => 'border-color: {{VALUE}}',
				],
				'condition' => [
					'order_details_button_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'order_details_button_hover_transition_duration',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (ms)',
				'type' => Controls_Manager::SLIDER,
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bhtd: {{SIZE}}ms',
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 3000,
						'step' => 100,
					],
				],
			]
		);

		$this->add_control(
			'order_details_button_hover_animation',
			[
				'label' => esc_html__( 'Animación al pasar el cursor', 'pro-elements' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
				'frontend_available' => true,
				'render_type' => 'template',
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'order_details_button_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bbt: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'order_details_button_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .shop_table .button, {{WRAPPER}} .order-again .button, {{WRAPPER}} .woocommerce-pagination .button' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'order_details_button_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'order_details_button_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} ' => '--dp-bbc: {{VALUE}};',
				],
				'condition' => [
					'order_details_button_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'order_details_button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bbr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_details_button_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--dp-bp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Init Gettext Modifications
	 *
	 * Sets the `$gettext_modifications` property used with the `filter_gettext()` of `Base`.
	 *
	 * @since 3.5.0
	 */
	protected function init_gettext_modifications() {
		$instance = $this->get_settings_for_display();

		$this->gettext_modifications = [
			'Order number:' => isset( $instance['payment_details_number'] ) ? $instance['payment_details_number'] : '',
			'Date:' => isset( $instance['payment_details_date'] ) ? $instance['payment_details_date'] : '',
			'Email:' => isset( $instance['payment_details_email'] ) ? $instance['payment_details_email'] : '',
			'Total:' => isset( $instance['payment_details_total'] ) ? $instance['payment_details_total'] : '',
			'Payment method:' => isset( $instance['payment_details_payment'] ) ? $instance['payment_details_payment'] : '',
			'Our bank details' => isset( $instance['bank_details_text'] ) ? $instance['bank_details_text'] : '',
			'Order details' => isset( $instance['order_summary_text'] ) ? $instance['order_summary_text'] : '',
			'Billing address' => isset( $instance['billing_details_text'] ) ? $instance['billing_details_text'] : '',
			'Shipping address' => isset( $instance['shipping_details_text'] ) ? $instance['shipping_details_text'] : '',
			'Downloads' => isset( $instance['downloads_text'] ) ? $instance['downloads_text'] : '',
		];
	}

	/**
	 * Modify Order Received Text.
	 *
	 * @since 3.5.0
	 *
	 * @param $text
	 * @return string
	 */
	public function modify_order_received_text( $text ) {
		$instance = $this->get_settings_for_display();

		if ( isset( $instance['confirmation_message_text'] ) ) {
			$text = $instance['confirmation_message_text'];
		}

		return $text;
	}

	public function get_modified_order_id() {
		return $this->order_id;
	}

	public function get_modified_order_key() {
		return $this->order_key;
	}

	protected function render_widget() {
		$is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
		$is_preview = self::is_preview_mode();

		if ( $is_editor || $is_preview ) {
			$this->set_preview_order();

			add_filter( 'woocommerce_thankyou_order_id', [ $this, 'get_modified_order_id' ] );
			add_filter( 'woocommerce_thankyou_order_key', [ $this, 'get_modified_order_key' ] );

			/**
			 * The action `template_redirect` is not run during the re-loading of the Widget and as a result the
			 * `wc_template_redirect` function is not run which is responsible for loading the following, so we
			 * must load them ourselves.
			 */
			WC()->payment_gateways();
			WC()->shipping();
		}

		/*
		 * Add actions & filters before displaying our Widget.
		 */
		add_filter( 'gettext', [ $this, 'filter_gettext' ], 20, 3 );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'modify_order_received_text' ] );

		/**
		 * Display our Widget.
		 */
		global $wp;
		if ( isset( $wp->query_vars['order-received'] ) && wc_get_order( intval( $wp->query_vars['order-received'] ) ) ) {
			echo '<div class="digi-psum">' . do_shortcode( '[woocommerce_checkout]' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salida de WooCommerce.
		} elseif ( $is_editor || $is_preview ) {
			$this->no_order_notice();
		}

		/*
		 * Remove actions & filters after displaying our Widget.
		 */
		remove_filter( 'gettext', [ $this, 'filter_gettext' ], 20 );
		remove_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'modify_order_received_text' ] );

		if ( $is_editor || $is_preview ) {
			remove_filter( 'woocommerce_thankyou_order_id', [ $this, 'get_modified_order_id' ] );
			remove_filter( 'woocommerce_thankyou_order_key', [ $this, 'get_modified_order_key' ] );
		}
	}

	public function no_order_notice() {
		?>
		<div class="woocommerce-error" role="alert">
			<?php echo esc_html__( 'Hace falta al menos un pedido de WooCommerce para previsualizarlo aquí.', 'pro-elements' ); ?>
		</div>
		<?php
	}

	public function set_preview_order() {
		$instance = $this->get_settings_for_display();
		$order = false;

		if ( 'custom-order' === $instance['preview_order_type'] ) {
			$order = wc_get_order( $instance['preview_order_custom'] );
		}

		if ( ! $order ) {
			$latest_order = wc_get_orders( [
				'limit' => 1,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'return'   => 'ids',
			] );

			if ( isset( $latest_order[0] ) ) {
				$order = wc_get_order( $latest_order[0] );
			}
		}

		if ( $order ) {
			global $wp;
			$wp->set_query_var( 'order-received', $order->get_id() );

			$this->order_id = $order->get_id();
			$this->order_key = $order->get_order_key();
		}
	}

	public function get_group_name() {
		return 'woocommerce';
	}
}
