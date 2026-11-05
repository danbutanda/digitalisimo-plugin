<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Background;
use Elementor\Core\Base\Document;
use ElementorPro\Modules\QueryControl\Module as QueryControlModule;

defined( 'ABSPATH' ) || exit;

/**
 * Carrito (`digitalisimo-woo-cart`): sustituye al widget «Cart» de PRO (`modules/woocommerce/widgets/cart.php`, mapa
 * `modules/digitalisimo-convert/maps/woocommerce-cart.php`). Repite sus controles con el mismo nombre, valor por defecto,
 * opciones, condiciones y selectores (sobre las clases propias `digi-cart__*`); las etiquetas, en español.
 *
 * WooCommerce pinta lo suyo: el carrito es `[woocommerce_cart]` con sus plantillas, y el widget sólo engancha, como PRO,
 * las envolturas de las dos columnas y de las secciones (`woocommerce_before_cart`, `…_cart_table`, `…_cart_collaterals`,
 * `woocommerce_after_cart`), su propio formulario de cupón, los textos de los botones (`gettext`), la URL del carrito en
 * la página actual, las ventas cruzadas fuera y la plantilla del carrito vacío. Raíz propia `div.digi-cart`.
 *
 * Recursos: `store/assets/cart.css` y `cart-2.css` (cada una ≤ 4 KB) y `cart.js` (sin jQuery: actualización automática
 * al cambiar una cantidad, columna derecha fija y el destino de los formularios para las peticiones de WooCommerce); los
 * de WooCommerce que pide PRO (`wc-cart`, `selectWoo`, `select2`).
 *
 * Derivado de `modules/woocommerce/widgets/cart.php` de PRO Elements (GPL): controles, ganchos y textos sustituidos.
 */
class Cart extends Base {
	/** Filtros de los mensajes de envío del carrito (los que PRO envuelve con la clase de sus descripciones). */
	const SHIPPING_MESSAGES = [ 'woocommerce_shipping_may_be_available_html', 'woocommerce_shipping_not_enabled_on_cart_html', 'woocommerce_shipping_estimate_html', 'woocommerce_cart_no_shipping_available_html' ];


	public function get_name() {
		return 'digitalisimo-woo-cart';
	}

	public function get_title() {
		return esc_html__( 'Carrito', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-woo-cart';
	}

	public function get_keywords() {
		return [ 'woocommerce', 'tienda', 'carrito', 'cesta', 'cart' ];
	}

	public function get_categories() {
		return [ 'woocommerce-elements' ];
	}

	public function get_script_depends(): array {
		// Los de WooCommerce que PRO pide (calculadora de envío y select de país) y el propio, si existe.
		return array_merge( [ 'wc-cart', 'selectWoo' ], parent::get_script_depends() );
	}

	public function get_style_depends(): array {
		return array_merge( [ 'select2' ], parent::get_style_depends() );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'General', 'pro-elements' ),
			]
		);

		$this->add_control(
			'cart_layout',
			[
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'two-column' => esc_html__( 'Dos columnas', 'pro-elements' ),
					'one-column' => esc_html__( 'Una columna', 'pro-elements' ),
				],
				'default' => 'two-column',
				'prefix_class' => 'digi-cart-layout-',
			]
		);

		$this->add_control(
			'sticky_right_column',
			[
				'label' => esc_html__( 'Columna derecha fija', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'description' => esc_html__( 'La columna derecha (por ejemplo, los totales del carrito) queda fija al desplazarse.', 'pro-elements' ),
				'frontend_available' => true,
				'render_type' => 'none',
				'condition' => [
					'cart_layout!' => 'one-column',
				],
			]
		);

		$this->add_control(
			'sticky_right_column_offset',
			[
				'label' => esc_html__( 'Desplazamiento', 'pro-elements' ),
				'type' => Controls_Manager::NUMBER,
				'frontend_available' => true,
				'conditions' => [
					'relation' => 'and',
					'terms' => [
						[
							'name' => 'sticky_right_column',
							'operator' => '!==',
							'value' => '',
						],
						[
							'name' => 'cart_layout',
							'operator' => '!==',
							'value' => 'one-column',
						],
					],
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_order_summary',
			[
				'label' => esc_html__( 'Resumen del pedido', 'pro-elements' ),
				'condition' => [
					'update_cart_automatically' => '',
				],
			]
		);

		$this->add_control(
			'update_cart_button_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón «Actualizar carrito»', 'pro-elements' ),
			]
		);

		$this->add_control(
			'update_cart_button_text',
			[
				'label' => esc_html__( 'Texto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'placeholder' => esc_html__( 'Actualizar carrito', 'pro-elements' ),
				'default' => esc_html__( 'Actualizar carrito', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'update_cart_button_alignment',
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
					'justify' => [
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-cart-form' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--cucb-a: start; --cucb-w: auto;',
					'center' => '--cucb-a: center; --cucb-w: auto;',
					'end' => '--cucb-a: end; --cucb-w: auto;',
					'justify' => '--cucb-a: justify; --cucb-w: 100%;',
				],
			]
		);

		$this->end_controls_section();

		if ( $this->is_wc_feature_active( 'coupons' ) ) {

			$this->start_controls_section(
				'section_coupon',
				[
					'label' => esc_html__( 'Cupón', 'pro-elements' ),
				]
			);

			$this->add_control(
				'section_coupon_display',
				[
					'label' => esc_html__( 'Cupón', 'pro-elements' ),
					'type' => Controls_Manager::SWITCHER,
					'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
					'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
					'default' => 'yes',
				]
			);

			$this->add_control(
				'apply_coupon_heading',
				[
					'type' => Controls_Manager::HEADING,
					'label' => esc_html__( 'Botón «Aplicar cupón»', 'pro-elements' ),
					'condition' => [
						'section_coupon_display' => 'yes',
					],
				]
			);

			$this->add_control(
				'apply_coupon_button_text',
				[
					'label' => esc_html__( 'Texto', 'pro-elements' ),
					'type' => Controls_Manager::TEXT,
					'dynamic' => [
						'active' => true,
					],
					'placeholder' => esc_html__( 'Aplicar cupón', 'pro-elements' ),
					'default' => esc_html__( 'Aplicar cupón', 'pro-elements' ),
					'condition' => [
						'section_coupon_display' => 'yes',
					],
				]
			);

			$this->add_responsive_control(
				'apply_coupon_button_alignment',
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
						'justify' => [
							'title' => esc_html__( 'Justificado', 'pro-elements' ),
							'icon' => 'eicon-text-align-justify',
						],
					],
					'selectors' => [
						'{{WRAPPER}} .coupon' => '{{VALUE}}',
					],
					'selectors_dictionary' => [
						'start' => '--cacb-a: start; --cacb-w: auto;',
						'center' => '--cacb-a: center;  --cacb-w: auto;',
						'end' => '--cacb-a: end;  --cacb-w: auto;',
						'justify' => '--cacb-a: center; --cacb-w: 100%;',
					],
					'condition' => [
						'section_coupon_display' => 'yes',
					],
				]
			);

			$this->add_control(
				'coupon_button_alignment_note',
				[
					'raw' => esc_html__( 'Nota: este control sólo afecta a las pantallas de tableta y menores.', 'pro-elements' ),
					'type' => Controls_Manager::RAW_HTML,
					'content_classes' => 'elementor-descriptor',
					'condition' => [
						'section_coupon_display' => 'yes',
					],
				]
			);

			$this->end_controls_section();
		}

		$this->start_controls_section(
			'section_totals',
			[
				'label' => esc_html__( 'Totales', 'pro-elements' ),
			]
		);

		$this->add_control(
			'totals_section_title',
			[
				'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'placeholder' => esc_html__( 'Totales del carrito', 'pro-elements' ),
				'default' => esc_html__( 'Totales del carrito', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'totals_section_title_alignment',
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
					'{{WRAPPER}}' => '--ct-ta: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'update_shipping_button_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón «Actualizar envío»', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'update_shipping_button_text',
			[
				'label' => esc_html__( 'Texto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'placeholder' => esc_html__( 'Actualizar', 'pro-elements' ),
				'default' => esc_html__( 'Actualizar', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'update_shipping_button_alignment',
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
					'justify' => [
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .shipping-calculator-form' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--cusb-a: start; --cusb-w: auto;',
					'center' => '--cusb-a: center;  --cusb-w: auto;',
					'end' => '--cusb-a: end;  --cusb-w: auto;',
					'justify' => '--cusb-a: center; --cusb-w: 100%;',
				],
			]
		);

		$this->add_control(
			'checkout_button_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón de pago', 'pro-elements' ),
			]
		);

		$this->add_control(
			'checkout_button_text',
			[
				'label' => esc_html__( 'Texto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'placeholder' => esc_html__( 'Finalizar compra', 'pro-elements' ),
				'default' => esc_html__( 'Finalizar compra', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'checkout_button_alignment',
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
					'justify' => [
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .wc-proceed-to-checkout' => '{{VALUE}};',
				],
				'selectors_dictionary' => [
					'start' => '--cpot-a: flex-start; --ccb-w: fit-content;',
					'center' => '--cpot-a: center; --ccb-w: fit-content;',
					'end' => '--cpot-a: flex-end; --ccb-w: fit-content;',
					'justify' => '--cpot-a: stretch; --ccb-w: 100%;',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_additional_options',
			[
				'label' => esc_html__( 'Opciones adicionales', 'pro-elements' ),
			]
		);

		$this->add_control(
			'update_cart_automatically',
			[
				'label' => esc_html__( 'Actualizar el carrito automáticamente', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}};',
				],
				'selectors_dictionary' => [
					'yes' => '--cuca-d: none;',
				],
				'frontend_available' => true,
				'render_type' => 'template',
			]
		);

		$this->add_control(
			'update_cart_automatically_description',
			[
				'raw' => esc_html__( 'Los cambios en el carrito se actualizan solos.', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->add_control(
			'additional_template_switch',
			[
				'label' => esc_html__( 'Personalizar el carrito vacío', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'active',
				'default' => '',
				'render_type' => 'template',
				'prefix_class' => 'digi-cart-empty-template-',
			]
		);

		$this->add_control(
			'additional_template_description',
			[
				'raw' => sprintf(
					/* translators: 1: Saved templates link opening tag, 2: Link closing tag. */
					esc_html__( 'Sustituye la pantalla de carrito vacío de WooCommerce por una plantilla. (¿No tienes una? Ve a %1$sPlantillas guardadas%2$s)', 'pro-elements' ),
					sprintf( '<a href="%s" target="_blank">', admin_url( 'edit.php?post_type=elementor_library&tabs_group=library#add_new' ) ),
					'</a>'
				),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor elementor-descriptor-subtle',
				'condition' => [
					'additional_template_switch' => 'active',
				],
			]
		);

		$this->add_control(
			'additional_template_select_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Elegir plantilla', 'pro-elements' ),
				'condition' => [
					'additional_template_switch' => 'active',
				],
			]
		);

		$document_types = \Elementor\Plugin::$instance->documents->get_document_types( [
			'show_in_library' => true,
		] );

		$this->add_control(
			'additional_template_select',
			[
				'type' => QueryControlModule::QUERY_CONTROL_ID,
				'label_block' => true,
				'show_label' => false,
				'autocomplete' => [
					'object' => QueryControlModule::QUERY_OBJECT_LIBRARY_TEMPLATE,
					'query' => [
						'meta_query' => [
							[
								'key' => Document::TYPE_META_KEY,
								'value' => array_keys( $document_types ),
								'compare' => 'IN',
							],
						],
					],
				],
				'frontend_available' => true,
				'condition' => [
					'additional_template_switch' => 'active',
				],
				'render_type' => 'template',
			]
		);

		$this->add_control(
			'edit_button',
			[
				'raw' => sprintf( '<a href="#" target="_blank" class="elementor-button elementor-edit-template" style="margin-top:0px;"><i class="eicon-pencil"> %s</i></a>', esc_html__( 'Editar plantilla', 'pro-elements' ) ),
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-edit-template-wrapper',
				'condition' => [
					'additional_template_switch' => 'active',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_tabs_style',
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
					'{{WRAPPER}}' => '--cs-bg: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-cart__section',
			]
		);

		$this->add_control(
			'sections_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--cs-bt: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'sections_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__section' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--cs-bc: {{VALUE}};',
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
					'{{WRAPPER}}' => '--cs-br: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--cs-p: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'sections_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--cs-m: {{BOTTOM}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_tabs_typography',
			[
				'label' => esc_html__( 'Tipografía', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'sections_typography',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'sections_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cs-tc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_titles_typography',
				'selector' => '{{WRAPPER}} .cart_totals h2',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'sections_titles_text_shadow',
				'selector' => '{{WRAPPER}} .cart_totals h2',
			]
		);

		$this->add_responsive_control(
			'sections_title_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--cs-ts: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'sections_descriptions_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Descripciones', 'pro-elements' ),
			]
		);

		$this->add_control(
			'sections_descriptions_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} ' => '--cs-dc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_descriptions_typography',
				'selector' => '{{WRAPPER}} .digi-cart__content, {{WRAPPER}} .woocommerce-shipping-destination, {{WRAPPER}} .shipping-calculator-button',
			]
		);

		$this->add_responsive_control(
			'sections_descriptions_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--cs-ds: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'sections_links_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlaces', 'pro-elements' ),
			]
		);

		$this->start_controls_tabs( 'links_colors' );

		$this->start_controls_tab( 'links_normal_colors', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'links_normal_color',
			[
				'label' => esc_html__( 'Color del enlace', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cl-n: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'links_hover_colors', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'links_hover_color',
			[
				'label' => esc_html__( 'Color del enlace', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cl-hc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'sections_radio_buttons_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botones de opción', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'sections_radio_buttons_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cs-rc: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_radio_buttons_typography',
				'selector' => '{{WRAPPER}} #shipping_method li label',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_tabs_forms',
			[
				'label' => esc_html__( 'Formularios', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'forms_rows_gap',
			[
				'label' => esc_html__( 'Separación entre filas', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--cf-rg: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'forms_field_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Campo', 'pro-elements' ),
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'forms_field_typography',
				'selector' => '{{WRAPPER}} .coupon .input-text, {{WRAPPER}} .cart-collaterals .input-text, {{WRAPPER}} select, {{WRAPPER}} .select2-selection--single',
			]
		);

		$this->start_controls_tabs( 'forms_fields_styles' );

		$this->start_controls_tab( 'forms_fields_normal_styles', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'forms_fields_normal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cff-n: {{VALUE}};',
					'.digi-cart-select2 .select2-results__option' => 'color: {{VALUE}};',
					// style select2 arrow
					'{{WRAPPER}} .select2-container--default .select2-selection--single .select2-selection__arrow b' => 'border-color: {{VALUE}} transparent transparent transparent;',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_fields_normal_background',
				'selector' => '{{WRAPPER}} .coupon .input-text, {{WRAPPER}} .digi-cart__totals .input-text, {{WRAPPER}} select, {{WRAPPER}} .select2-selection--single',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_fields_normal_box_shadow',
				'selector' => '{{WRAPPER}} .coupon .input-text, {{WRAPPER}} .digi-cart__totals .input-text, {{WRAPPER}} select, {{WRAPPER}} .select2-selection--single',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'forms_fields_focus_styles', [ 'label' => esc_html__( 'Con foco', 'pro-elements' ) ] );

		$this->add_control(
			'forms_fields_focus_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cff-fc: {{VALUE}}',
					'.digi-cart-select2 .select2-results__option:focus' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_fields_focus_background',
				'selector' => '{{WRAPPER}} .coupon .input-text:focus, {{WRAPPER}} .digi-cart__totals .input-text:focus, {{WRAPPER}} select:focus, {{WRAPPER}} .select2-selection--single:focus',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_fields_focus_box_shadow',
				'selector' => '{{WRAPPER}} .coupon .input-text:focus, {{WRAPPER}} .digi-cart__totals .input-text:focus, {{WRAPPER}} select:focus, {{WRAPPER}} .select2-selection--single:focus',
			]
		);

		$this->add_control(
			'forms_fields_focus_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cff-fbc: {{VALUE}}',
				],
				'condition' => [
					'forms_fields_border_border!' => '',
				],
			]
		);

		$this->add_control(
			'forms_fields_focus_transition_duration',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (ms)',
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 3000,
						'step' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--cff-ftd: {{SIZE}}ms',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'forms_fields_border',
				'selector' => '{{WRAPPER}} .coupon .input-text, {{WRAPPER}} .cart-collaterals .input-text, {{WRAPPER}} select, {{WRAPPER}} .select2-selection--single',
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'forms_fields_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--cff-br: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'forms_fields_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} ' => '--cff-p: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					// style select2
					'{{WRAPPER}} .select2-container--default .select2-selection--single .select2-selection__rendered' => 'line-height: calc( ({{TOP}}{{UNIT}}*2) + 16px ); padding-left: {{LEFT}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}};',
					'{{WRAPPER}} .select2-container--default .select2-selection--single .select2-selection__arrow' => 'height: calc( ({{TOP}}{{UNIT}}*2) + 16px ); right: {{RIGHT}}{{UNIT}};',
					'{{WRAPPER}} .select2-container--default .select2-selection--single' => 'height: auto;',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'forms_button_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botones', 'pro-elements' ),
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'forms_button_typography',
				'selector' => '{{WRAPPER}} .shop_table .button',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'forms_button_text_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button',
			]
		);

		$this->start_controls_tabs( 'forms_buttons_styles' );

		$this->start_controls_tab( 'forms_buttons_normal_styles', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'forms_buttons_normal_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cfb-ntc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_buttons_normal_background',
				'selector' => '{{WRAPPER}} .shop_table .button',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_buttons_normal_box_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'forms_buttons_hover_styles', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'forms_buttons_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cfb-htc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_buttons_hover_background',
				'selector' => '{{WRAPPER}} .shop_table .button:hover, {{WRAPPER}} .shop_table .button:disabled[disabled]:hover',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_buttons_focus_box_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button:hover',
			]
		);

		$this->add_control(
			'forms_buttons_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cfb-hbc: {{VALUE}}',
				],
				'condition' => [
					'forms_buttons_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'forms_buttons_hover_transition_duration',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (ms)',
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 3000,
						'step' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--cfb-htd: {{SIZE}}ms',
				],
			]
		);

		$this->add_control(
			'forms_buttons_hover_animation',
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
			'forms_buttons_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--cfb-bt: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'forms_buttons_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .shop_table .button' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'forms_buttons_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'forms_buttons_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cfb-bc: {{VALUE}};',
				],
				'condition' => [
					'forms_buttons_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'forms_buttons_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--cfb-br: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'forms_buttons_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--cfb-p: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; --cfb-w: auto;',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'tabs_order_summary',
			[
				'label' => esc_html__( 'Resumen del pedido', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'order_summary_rows_gap',
			[
				'label' => esc_html__( 'Separación entre filas', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--cos-rgt: calc( {{SIZE}}{{UNIT}}/2 ); --cos-rgb: calc( {{SIZE}}{{UNIT}}/2 );',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'order_summary_titles_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-cart-form' => '--cos-tc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_title_typography',
				'selector' => '{{WRAPPER}} .digi-cart__table .cart th, {{WRAPPER}} .digi-cart__table .cart td:before',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'order_summary_title_text_shadow',
				'selector' => '{{WRAPPER}} .digi-cart__table .cart th, {{WRAPPER}} .digi-cart__table .cart td:before',
			]
		);

		$this->add_responsive_control(
			'order_summary_title_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--cos-ts: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'order_summary_items_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Artículos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cos-c: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_items_typography',
				'selector' => '{{WRAPPER}} .cart td span, {{WRAPPER}} .cart td, {{WRAPPER}} .input-text.qty',
			]
		);

		$this->add_control(
			'order_summary_variations_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Variaciones', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_variations_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cos-vc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_variations_typography',
				'selector' => '{{WRAPPER}} .product-name .variation',
			]
		);

		$this->add_control(
			'order_summary_product_link_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlace del producto', 'pro-elements' ),
			]
		);

		$this->start_controls_tabs( 'order_summary' );

		$this->start_controls_tab( 'order_summary_normal', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'product_link_normal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cpl-n: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'order_summary_hover', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'product_link_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cpl-hc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'order_summary_divider_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Separadores', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'order_summary_items_divider_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cos-idc: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_items_divider_weight',
			[
				'label' => esc_html__( 'Grosor', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--cos-idw: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'order_summary_quantity_border_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Bordes de la cantidad', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'order_summary_quantity_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cos-qbc: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_quantity_border_weight',
			[
				'label' => esc_html__( 'Grosor', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--cos-qbw: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'order_summary_remove_icon_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Icono para quitar', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->start_controls_tabs( 'order_summary_remove_icon' );

		$this->start_controls_tab( 'order_summary_remove_icon_normal', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'order_summary_remove_icon_normal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cos-rnc: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'order_summary_remove_icon_hover', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'order_summary_remove_icon_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--cos-rhc: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_totals',
			[
				'label' => esc_html__( 'Totales', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'totals_rows_gap',
			[
				'label' => esc_html__( 'Separación entre filas', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--ct-rgt: calc( {{SIZE}}{{UNIT}}/2 ); --ct-rgb: calc( {{SIZE}}{{UNIT}}/2 );',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'totals_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos y totales', 'pro-elements' ),
			]
		);

		$this->add_control(
			'totals_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--ct-c: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'totals_typography',
				'selector' => '{{WRAPPER}} .cart_totals .shop_table td:before, {{WRAPPER}} .cart_totals .shop_table td .woocommerce-Price-amount',
			]
		);

		$this->add_control(
			'totals_divider_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Separador del total', 'pro-elements' ),
			]
		);

		$this->add_control(
			'totals_divider_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--ct-dvc: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'totals_divider_weight',
			[
				'label' => esc_html__( 'Grosor', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--ct-dvw: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_tabs_checkout_button',
			[
				'label' => esc_html__( 'Botón de pago', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'checkout_button_typography',
				'selector' => '{{WRAPPER}} .checkout-button',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'checkout_button_text_shadow',
				'selector' => '{{WRAPPER}} .checkout-button',
			]
		);

		$this->start_controls_tabs( 'checkout_button_styles' );

		$this->start_controls_tab( 'checkout_button_normal_styles', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'checkout_button_normal_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--ccb-ntc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'checkout_button_normal_background',
				'selector' => '{{WRAPPER}} .woocommerce .wc-proceed-to-checkout .checkout-button',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'checkout_button_normal_box_shadow',
				'selector' => '{{WRAPPER}} .checkout-button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'checkout_button_hover_styles', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'checkout_button_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--ccb-htc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'checkout_button_hover_background',
				'selector' => '{{WRAPPER}} .woocommerce .wc-proceed-to-checkout .checkout-button:hover',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'checkout_button_hover_box_shadow',
				'selector' => '{{WRAPPER}} .checkout-button:hover',
			]
		);

		$this->add_control(
			'checkout_button_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--ccb-hbc: {{VALUE}}',
				],
				'condition' => [
					'checkout_button_border_border!' => '',
				],
			]
		);

		$this->add_control(
			'checkout_button_hover_transition_duration',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (ms)',
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 3000,
						'step' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--ccb-htd: {{SIZE}}ms',
				],
			]
		);

		$this->add_control(
			'checkout_button_hover_animation',
			[
				'label' => esc_html__( 'Animación al pasar el cursor', 'pro-elements' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
				'frontend_available' => true,
				'render_type' => 'template',
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'checkout_button_border',
				'selector' => '{{WRAPPER}} .checkout-button',
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'checkout_button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--ccb-br: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'checkout_button_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--ccb-p: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; --ccb-w: fit-content;',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_tabs_customize',
			[
				'label' => esc_html__( 'Personalizar', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$customize_options = [];

		$customize_options += [
			'customize_order_summary' => esc_html__( 'Resumen del pedido', 'pro-elements' ),
		];

		if ( $this->is_wc_feature_active( 'coupons' ) ) {
			$customize_options += [
				'customize_coupon' => esc_html__( 'Cupón', 'pro-elements' ),
			];
		}

		$customize_options += [
			'customize_totals' => esc_html__( 'Totales', 'pro-elements' ),
		];

		$this->add_control(
			'section_cart_show_customize_elements',
			[
				'label' => esc_html__( 'Elige las secciones del carrito que quieres personalizar:', 'pro-elements' ),
				'type' => Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $customize_options,
				'render_type' => 'ui',
				'label_block' => true,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_tabs_customize_order_summary',
			[
				'label' => esc_html__( 'Personalizar: resumen del pedido', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_cart_show_customize_elements' => 'customize_order_summary',
				],
			]
		);

		$this->add_control(
			'order_summary_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_section_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-cart__table' => '--cs-bg: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'order_summary_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-cart__table',
				'separator' => 'after',
			]
		);

		$this->add_control(
			'order_summary_section_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .digi-cart__table' => '--cs-bt: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'order_summary_section_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__table' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'order_summary_section_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'order_summary_section_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-cart__table' => '--cs-bc: {{VALUE}};',
				],
				'condition' => [
					'order_summary_section_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_section_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__table' => '--cs-br: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_section_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__table' => '--cs-p: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_section_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__table' => '--cs-m: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_tabs_customize_totals',
			[
				'label' => esc_html__( 'Personalizar: totales', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_cart_show_customize_elements' => 'customize_totals',
				],
			]
		);

		$this->add_control(
			'customize_totals_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'sections_totals_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cs-bg: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'totals_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-cart__totals',
			]
		);

		$this->add_control(
			'totals_section_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cs-bt: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'totals_section_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'totals_section_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'totals_section_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cs-bc: {{VALUE}};',
				],
				'condition' => [
					'totals_section_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'totals_section_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cs-br: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'checkout_sections_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cs-p: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'totals_section_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cs-m: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'totals_section_titles_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título', 'pro-elements' ),
			]
		);

		$this->add_control(
			'totals_section_titles_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cart_totals' => '--cs-tc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'totals_section_titles_typography',
				'selector' => '{{WRAPPER}} .cart_totals h2',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'totals_section_titles_text_shadow',
				'selector' => '{{WRAPPER}} .cart_totals h2',
				'separator' => 'after',
			]
		);

		$this->add_control(
			'totals_section_content_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
			]
		);

		$this->add_control(
			'totals_section_content_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cs-dc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'totals_section_content_typography',
				'selector' => '{{WRAPPER}} .digi-cart__totals .digi-cart__content, {{WRAPPER}} .digi-cart__totals .woocommerce-shipping-destination, {{WRAPPER}} .digi-cart__totals .shipping-calculator-button',
			]
		);

		$this->add_control(
			'totals_section_link_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlace', 'pro-elements' ),
			]
		);

		$this->start_controls_tabs( 'totals_section_links_colors' );

		$this->start_controls_tab( 'totals_section_links_normal_colors', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'totals_section_links_normal_color',
			[
				'label' => esc_html__( 'Color del enlace', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cl-n: {{VALUE}} !important;',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'totals_section_links_hover_colors', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'totals_section_links_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-cart__totals' => '--cl-hc: {{VALUE}} !important;',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_tabs_customize_coupon',
			[
				'label' => esc_html__( 'Personalizar: cupón', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_cart_show_customize_elements' => 'customize_coupon',
				],
			]
		);

		$this->add_control(
			'coupon_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'customize_coupon_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .coupon' => '--cs-bg: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'customize_coupon_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .coupon',
			]
		);

		$this->add_control(
			'customize_coupon_section_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .coupon' => '--cs-bt: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'customize_coupon_section_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .coupon' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'customize_coupon_section_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'customize_coupon_section_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .coupon' => '--cs-bc: {{VALUE}};',
				],
				'condition' => [
					'customize_coupon_section_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'customize_coupon_section_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .coupon' => '--cs-br: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'customize_coupon_section_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .coupon' => '--cs-p: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'customize_coupon_section_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .coupon' => '--cs-m: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .digi-cart__container' => 'grid-row-gap: {{BOTTOM}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Init Gettext Modifications
	 *
	 * Sets the `$gettext_modifications` property used with the `filter_gettext()` in the extended Base_Widget.
	 *
	 * @since 3.5.0
	 */
	protected function init_gettext_modifications() {
		$instance = $this->get_settings_for_display();

		$this->gettext_modifications = [
			'Update cart' => isset( $instance['update_cart_button_text'] ) ? $instance['update_cart_button_text'] : '',
			'Cart totals' => isset( $instance['totals_section_title'] ) ? $instance['totals_section_title'] : '',
			'Proceed to checkout' => isset( $instance['checkout_button_text'] ) ? $instance['checkout_button_text'] : '',
			'Update' => isset( $instance['update_shipping_button_text'] ) ? $instance['update_shipping_button_text'] : '',
			'Apply coupon' => isset( $instance['apply_coupon_button_text'] ) ? $instance['apply_coupon_button_text'] : '',
		];
	}

	/**
	 * Check if an Elementor template has been selected to display the empty cart notification
	 *
	 * @since 3.7.0
	 * @return boolean
	 */
	protected function has_empty_cart_template() {
		$additional_template_select = $this->get_settings_for_display( 'additional_template_select' );
		return ! empty( $additional_template_select ) && 0 < $additional_template_select;
	}

	/**
	 * Render Woocommerce Cart Coupon Form
	 *
	 * A custom function to render a coupon form on the Cart widget. The default WC coupon form
	 * was removed in this file's render() method.
	 *
	 * We are doing this in order to match the placement of the coupon form to the provided design.
	 *
	 * @since 3.5.0
	 */
	private function render_woocommerce_cart_coupon_form() {
		$settings = $this->get_settings_for_display();
		$button_classes = [ 'button', 'digi-cart__apply-coupon' ];
		if ( $settings['forms_buttons_hover_animation'] ) {
			$button_classes[] = 'elementor-animation-' . $settings['forms_buttons_hover_animation'];
		}
		$this->add_render_attribute(
			'button_coupon', [
				'class' => $button_classes,
				'name' => 'apply_coupon',
				'type' => 'submit',
			]
		);
		?>
		<div class="coupon digi-cart__section shop_table">
			<div class="form-row coupon-col">
				<div class="coupon-col-start">
					<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php echo esc_attr__( 'Código del cupón', 'pro-elements' ); ?>" />
				</div>
				<div class="coupon-col-end">
					<button <?php $this->print_render_attribute_string( 'button_coupon' ); ?> value="<?php echo esc_attr__( 'Aplicar cupón', 'pro-elements' ); ?>"><?php echo esc_html__( 'Aplicar cupón', 'pro-elements' ); ?></button>
				</div>
				<?php do_action( 'woocommerce_cart_coupon' ); ?>
			</div>
		</div>
		<?php
	}

	public function hide_coupon_field_on_cart( $enabled ) {
		return is_cart() ? false : $enabled;
	}

	/**
	 * Woocommerce Before Cart
	 *
	 * Output containing elements. Callback function for the woocommerce_before_cart hook
	 *
	 * This eliminates the need for template overrides.
	 *
	 * @since 3.5.0
	 */
	public function woocommerce_before_cart() {
		?>
		<div class="digi-cart__container">
						<!--open container-->
						<div class="digi-cart__column digi-cart__column--start">
							<!--open column-1-->
		<?php
	}

	/**
	 * Should Render Coupon
	 *
	 * Decide if the coupon form should be rendered.
	 * The coupon form should be rendered if:
	 * 1) The WooCommerce setting is enabled
	 * 2) And the Coupon Display toggle hasn't been set to 'no'
	 *
	 * @since 3.6.0
	 *
	 * @return boolean
	 */
	private function should_render_coupon() {
		if ( ! wc_coupons_enabled() ) {
			return false;
		}

		$settings = $this->get_settings_for_display();

		if ( isset( $settings['section_coupon_display'] ) && '' === $settings['section_coupon_display'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Woocommerce Before Cart Table
	 *
	 * Output containing elements. Callback function for the woocommerce_before_cart_table hook
	 *
	 * This eliminates the need for template overrides.
	 *
	 * @since 3.5.0
	 */
	public function woocommerce_before_cart_table() {
		$section_classes = [ 'digi-cart__table', 'digi-cart__section' ];

		if ( ! $this->should_render_coupon() ) {
			$section_classes[] = 'digi-cart__section--no-coupon';
		}

		$this->add_render_attribute(
			'before_cart_table', [
				'class' => $section_classes,
			]
		);
		?>
		<div <?php $this->print_render_attribute_string( 'before_cart_table' ); ?>>
						<!--open shop table div -->
		<?php
	}

	/**
	 * Woocommerce After Cart Table
	 *
	 * Output containing elements. Callback function for the woocommerce_after_cart_table hook
	 *
	 * This eliminates the need for template overrides.
	 *
	 * @since 3.5.0
	 */
	public function woocommerce_after_cart_table() {
		?>
						</div>
					<!--close shop table div -->
					<div class="digi-cart__clear"></div>
		<?php
		if ( $this->should_render_coupon() ) {
			$this->render_woocommerce_cart_coupon_form();
		}
	}

	/**
	 * Woocommerce Before Cart Collaterals
	 *
	 * Output containing elements. * Callback function for the woocommerce_before_cart_collaterals hook
	 *
	 * This eliminates the need for template overrides.
	 *
	 * @since 3.5.0
	 */
	public function woocommerce_before_cart_collaterals() {
		?>
						<!--close column-1-->
						</div>
						<div class="digi-cart__column digi-cart__column--end">
							<!--open column-2-->
								<div class="digi-cart__column-inner digi-cart__sticky">
									<!--open column-inner-->
									<div class="digi-cart__totals digi-cart__section">
										<!--open cart-totals-->
		<?php
	}

	/**
	 * Woocommerce After Cart
	 *
	 * Output containing elements. Callback function for the woocommerce_after_cart hook.
	 *
	 * This eliminates the need for template overrides.
	 *
	 * @since 3.5.0
	 */
	public function woocommerce_after_cart() {
		?>
									<!--close cart-totals-->
									</div>
									<!--close column-inner-->
								</div>
							<!--close column-2-->
						</div>
						<!--close container-->
					</div>
		<?php
	}

	/**
	 * WooCommerce Get Cart Url
	 *
	 * Used with the `woocommerce_get_cart_url`. This sets the url to the current page, so links like the `remove_url`
	 * are set to the current page, and not the existing WooCommerce cart endpoint.
	 *
	 * @since 3.7.0
	 *
	 * @param $url
	 * @return string
	 */
	public function woocommerce_get_cart_url( $url ) {
		global $post;

		if ( ! $post ) {
			return $url;
		}

		if ( self::is_wc_preview() || \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return \Elementor\Plugin::$instance->documents->get_current()->get_wp_preview_url();
		}

		return get_permalink( $post->ID );
	}

	/**
	 * The following disabling of cart coupon needs to be done this way so that
	 * we only disable the display of coupon interface in our cart widget and
	 * `wc_coupons_enabled()` can still be reliably used elsewhere.
	 */
	public function disable_cart_coupon() {
		add_filter( 'woocommerce_coupons_enabled', [ $this, 'cart_coupon_return_false' ], 90 );
	}
	public function enable_cart_coupon() {
		remove_filter( 'woocommerce_coupons_enabled', [ $this, 'cart_coupon_return_false' ], 90 );
	}
	public function cart_coupon_return_false() {
		return false;
	}

	/**
	 * Add Render Hooks
	 *
	 * Add actions & filters before displaying our widget.
	 *
	 * @since 3.7.0
	 */
	public function add_render_hooks() {
		$is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
		$is_preview = self::is_wc_preview();

		/**
		 * Add actions & filters before displaying our Widget.
		 */
		add_filter( 'gettext', [ $this, 'filter_gettext' ], 20, 3 );

		add_action( 'woocommerce_before_cart', [ $this, 'woocommerce_before_cart' ] );
		add_action( 'woocommerce_after_cart_table', [ $this, 'woocommerce_after_cart_table' ] );
		add_action( 'woocommerce_before_cart_table', [ $this, 'woocommerce_before_cart_table' ] );
		add_action( 'woocommerce_before_cart_collaterals', [ $this, 'woocommerce_before_cart_collaterals' ] );
		add_action( 'woocommerce_after_cart', [ $this, 'woocommerce_after_cart' ] );
		// The following disabling of cart coupon needs to be done this way so that
		// we only disable the display of coupon interface in our cart widget and
		// `wc_coupons_enabled()` can still be reliably used elsewhere.
		add_action( 'woocommerce_cart_contents', [ $this, 'disable_cart_coupon' ] );
		add_action( 'woocommerce_after_cart_contents', [ $this, 'enable_cart_coupon' ] );
		add_filter( 'woocommerce_get_cart_url', [ $this, 'woocommerce_get_cart_url' ] );
		foreach ( self::SHIPPING_MESSAGES as $filter ) {
			add_filter( $filter, [ $this, 'shipping_message' ], 20 );
		}

		if ( $this->has_empty_cart_template() ) {
			remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
		}

		// Remove cross-sells in cart.
		remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
	}

	/**
	 * Remove Render Hooks
	 *
	 * Remove actions & filters after displaying our widget.
	 *
	 * @since 3.7.0
	 */
	public function remove_render_hooks() {
		remove_filter( 'gettext', [ $this, 'filter_gettext' ], 20 );

		remove_action( 'woocommerce_before_cart', [ $this, 'woocommerce_before_cart' ] );
		remove_action( 'woocommerce_after_cart_table', [ $this, 'woocommerce_after_cart_table' ] );
		remove_action( 'woocommerce_before_cart_table', [ $this, 'woocommerce_before_cart_table' ] );
		remove_action( 'woocommerce_before_cart_collaterals', [ $this, 'woocommerce_before_cart_collaterals' ] );
		remove_action( 'woocommerce_after_cart', [ $this, 'woocommerce_after_cart' ] );
		remove_filter( 'woocommerce_coupons_enabled', [ $this, 'hide_coupon_field_on_cart' ] );
		remove_action( 'woocommerce_cart_contents', [ $this, 'disable_cart_coupon' ] );
		remove_action( 'woocommerce_after_cart_contents', [ $this, 'enable_cart_coupon' ] );
		remove_action( 'woocommerce_get_cart_url', [ $this, 'woocommerce_get_cart_url' ] );
		foreach ( self::SHIPPING_MESSAGES as $filter ) {
			remove_filter( $filter, [ $this, 'shipping_message' ], 20 );
		}
		add_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );

		if ( $this->has_empty_cart_template() ) {
			add_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
		}
	}

	protected function render_widget() {
		// Ganchos y filtros antes de pintar (los mismos que PRO), dentro de la raíz propia `digi-cart`.
		$this->add_render_hooks();
		echo '<div class="digi-cart">';
		if ( $this->has_empty_cart_template() && WC()->cart && WC()->cart->get_cart_contents_count() === 0 ) {
			$template_id = intval( $this->get_settings_for_display( 'additional_template_select' ) );
			echo do_shortcode( '[elementor-template id="' . $template_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plantilla del constructor.
		} else {
			echo do_shortcode( '[woocommerce_cart]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salida de WooCommerce.
		}
		echo '</div>';
		$this->remove_render_hooks();
	}

	/** Vista previa del editor o de WordPress (`Module::is_preview()` de PRO). */
	public static function is_wc_preview() {
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() ) || is_preview();
	}

	/**
	 * Mensajes de envío de WooCommerce dentro del widget con la clase de las descripciones (`digi-cart__content`), como
	 * los envuelve PRO para su carrito.
	 */
	public function shipping_message( $html ) {
		return '<span class="digi-cart__content">' . $html . '</span>';
	}

}
