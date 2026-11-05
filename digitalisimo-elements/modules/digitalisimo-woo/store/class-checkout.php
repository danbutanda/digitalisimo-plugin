<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Border;
use Elementor\Repeater;
use Elementor\Group_Control_Background;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Finalizar compra (`digitalisimo-woo-checkout`): sustituye al widget «Checkout» de PRO
 * (`modules/woocommerce/widgets/checkout.php`, mapa `modules/digitalisimo-convert/maps/woocommerce-checkout-page.php`).
 *
 * Repite todos sus controles con el mismo nombre, valor por defecto, opciones y condiciones (también los que sólo existen
 * con una función de WooCommerce activa: recordatorio de acceso, envío, cupones, alta desde el pago), con los selectores
 * pasados a las clases propias (`digi-co__*`). WooCommerce pinta lo suyo: el pago sale de `[woocommerce_checkout]` y de
 * sus plantillas, con los mismos ganchos y filtros que PRO (columnas, secciones, textos por `gettext`, campos de los
 * repetidores por `woocommerce_form_field_args`, texto de términos), así que pasarelas, extensiones y plantillas del tema
 * siguen funcionando. Los formularios de acceso y de cupón se pintan dentro de las columnas, como en PRO.
 *
 * Diferencias con PRO (declaradas en el mapa y en los casos del A/B):
 * - Los textos de «¿Ya eres cliente?» y «¿Tienes un cupón?» salen de sus controles (en PRO el filtro de textos no los
 *   alcanzaba y siempre salía el texto por defecto).
 * - Los conmutadores de acceso y de cupón son `<button>` con `aria-expanded` y `aria-controls` (PRO: `<a href="#">`), los
 *   paneles se ocultan con `hidden`, y la tecla Intro en el cupón o en el acceso aplica el cupón o accede (en PRO enviaba
 *   el pedido).
 * - Columna derecha fija resuelta en el servidor (clase y desplazamiento); el script propio (`store/assets/checkout.js`,
 *   sin jQuery) sólo abre y cierra los paneles, aplica el cupón y accede por las mismas peticiones que PRO, marca el
 *   desplegable de países y la animación del botón de compra, y pone en `_wp_http_referer` la página y el elemento para
 *   que la revisión del pedido por `?wc-ajax=` vuelva a aplicar los ganchos del widget (`add_render_hooks()`).
 *
 * Derivado del código GPL de PRO Elements (`modules/woocommerce/widgets/checkout.php`), con atribución en
 * `docs/third-party-licenses.md`.
 */
class Checkout extends Base {
	/** Scripts y hoja de WooCommerce que necesita el pago fuera de la página de pago (los mismos que pedía PRO). */
	const WC_SCRIPTS = array( 'wc-checkout', 'wc-password-strength-meter', 'selectWoo' );
	const WC_STYLES  = array( 'select2' );

	/** @var array|null */
	private $reformatted_form_fields;

	public function get_name() {
		return 'digitalisimo-woo-checkout';
	}

	public function get_title() {
		return esc_html__( 'Finalizar compra', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-checkout';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'pago', 'finalizar compra', 'caja', 'checkout' );
	}

	public function get_script_depends(): array {
		return array_merge( self::WC_SCRIPTS, parent::get_script_depends() );
	}

	public function get_style_depends(): array {
		return array_merge( self::WC_STYLES, parent::get_style_depends() );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'General', 'pro-elements' ),
			]
		);

		$this->add_control(
			'checkout_layout',
			[
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'two-column' => esc_html__( 'Dos columnas', 'pro-elements' ),
					'one-column' => esc_html__( 'Una columna', 'pro-elements' ),
				],
				'default' => 'two-column',
				'prefix_class' => 'digi-checkout-layout-',
			]
		);

		$this->add_control(
			'sticky_right_column',
			[
				'label' => esc_html__( 'Columna derecha fija', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'yes',
				'description' => esc_html__( 'Las secciones de resumen del pedido y de pago se quedan fijas al desplazarse.', 'pro-elements' ),
				'frontend_available' => true,
				// Columna fija resuelta en el servidor: el editor vuelve a pintar el widget (PRO: `none`, con su script).
				'render_type' => 'template',
				'condition' => [
					'checkout_layout' => 'two-column',
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
							'name' => 'checkout_layout',
							'operator' => '=',
							'value' => 'two-column',
						],
					],
				],
			]
		);

		$this->end_controls_section();

		if ( $this->is_wc_feature_active( 'checkout_login_reminder' ) ) {
			$this->add_checkout_login_reminder_controls();
		}

		$this->start_controls_section(
			'billing_details_section',
			[
				'label' => $this->is_wc_feature_active( 'ship_to_billing_address_only' ) ? esc_html__( 'Detalles de facturación y envío', 'pro-elements' ) : esc_html__( 'Detalles de facturación', 'pro-elements' ),
			]
		);

		$this->add_control(
			'billing_details_section_title',
			[
				'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'placeholder' => $this->is_wc_feature_active( 'ship_to_billing_address_only' ) ? esc_html__( 'Detalles de facturación y envío', 'pro-elements' ) : esc_html__( 'Detalles de facturación', 'pro-elements' ),
				'default' => $this->is_wc_feature_active( 'ship_to_billing_address_only' ) ? esc_html__( 'Detalles de facturación y envío', 'pro-elements' ) : esc_html__( 'Detalles de facturación', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
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
					'{{WRAPPER}}' => '--billing-details-title-alignment: {{VALUE}};',
				],
			]
		);

		$repeater = new Repeater();

		$repeater->start_controls_tabs( 'tabs', [
			'condition' => [
				'repeater_state' => '',
			],
		] );

		$repeater->start_controls_tab( 'content_tab', [
			'label' => esc_html__( 'Contenido', 'pro-elements' ),
		] );

		$repeater->add_control(
			'label',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
			]
		);

		$repeater->add_control(
			'placeholder',
			[
				'label' => esc_html__( 'Texto de ejemplo', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
			]
		);

		$repeater->end_controls_tab();

		$repeater->start_controls_tab( 'advanced_tab', [
			'label' => esc_html__( 'Avanzado', 'pro-elements' ),
		] );

		$repeater->add_control(
			'default',
			[
				'label' => esc_html__( 'Valor por defecto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->end_controls_tab();

		$repeater->end_controls_tabs();

		$repeater->add_control(
			'repeater_state',
			[
				'label' => esc_html__( 'Estado del repetidor (oculto)', 'pro-elements' ),
				'type' => Controls_Manager::HIDDEN,
			]
		);

		$repeater->add_control(
			'locale_notice',
			[
				'raw' => __( 'Nota: este contenido no puede cambiarse por las normas locales.', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
				'condition' => [
					'repeater_state' => 'locale',
				],
			]
		);

		$repeater->add_control(
			'from_billing_notice',
			[
				'raw' => __( 'Nota: esta etiqueta y este texto de ejemplo salen de la sección de facturación. Puedes cambiarlos allí.', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
				'condition' => [
					'repeater_state' => 'from_billing',
				],
			]
		);

		$this->add_control(
			'billing_details_form_fields',
			[
				'label' => esc_html__( 'Elementos del formulario', 'pro-elements' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'item_actions' => [
					'add' => false,
					'duplicate' => false,
					'remove' => false,
					'sort' => false,
				],
				'default' => $this->get_billing_field_defaults(),
				'title_field' => '{{{ label }}}',
			]
		);

		$this->end_controls_section();

		if ( $this->is_wc_feature_active( 'shipping' ) && ! $this->is_wc_feature_active( 'ship_to_billing_address_only' ) ) {
			$this->add_shipping_controls();
		}

		$this->start_controls_section(
			'additional_information_section',
			[
				'label' => esc_html__( 'Información adicional', 'pro-elements' ),
			]
		);

		$this->add_control(
			'additional_information_active',
			[
				'label' => esc_html__( 'Información adicional', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default' => 'yes',
				'selectors' => [
					'{{WRAPPER}}' => '--additional-information-display: block;',
				],
			]
		);

		if ( $this->is_wc_feature_active( 'ship_to_billing_address_only' ) ) {
			$this->add_control(
				'additional_information_section_title',
				[
					'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
					'type' => Controls_Manager::TEXT,
					'placeholder' => esc_html__( 'Información adicional', 'pro-elements' ),
					'default' => esc_html__( 'Información adicional', 'pro-elements' ),
					'dynamic' => [
						'active' => true,
					],
					'condition' => [
						'additional_information_active!' => '',
					],
				]
			);

			$this->add_responsive_control(
				'additional_information_alignment',
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
						'{{WRAPPER}}' => '--additional-fields-title-alignment: {{VALUE}};',
					],
					'condition' => [
						'additional_information_active!' => '',
					],
				]
			);
		}

		$repeater = new Repeater();

		$repeater->start_controls_tabs( 'additional_information_form_fields_tabs' );

		$repeater->start_controls_tab( 'additional_information_form_fields_content_tab', [
			'label' => esc_html__( 'Contenido', 'pro-elements' ),
		] );

		$repeater->add_control(
			'label',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->add_control(
			'placeholder',
			[
				'label' => esc_html__( 'Texto de ejemplo', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->end_controls_tab();

		$repeater->start_controls_tab( 'additional_information_form_fields_advanced_tab', [
			'label' => esc_html__( 'Avanzado', 'pro-elements' ),
		] );

		$repeater->add_control(
			'default',
			[
				'label' => esc_html__( 'Valor por defecto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->end_controls_tab();

		$repeater->end_controls_tabs();

		$this->add_control(
			'additional_information_form_fields',
			[
				'label' => esc_html__( 'Elementos', 'pro-elements' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'item_actions' => [
					'add' => false,
					'duplicate' => false,
					'remove' => false,
					'sort' => false,
				],
				'default' => [
					[
						'field_key' => 'order_comments',
						'field_label' => esc_html__( 'Notas del pedido', 'pro-elements' ),
						'label' => esc_html__( 'Notas del pedido', 'pro-elements' ),
						'placeholder' => esc_html__( 'Notas sobre tu pedido, por ejemplo, indicaciones especiales para la entrega.', 'pro-elements' ),
					],
				],
				'title_field' => '{{{ label }}}',
				'condition' => [
					'additional_information_active!' => '',
				],
			]
		);

		$this->end_controls_section();

		if ( $this->is_wc_feature_active( 'signup_and_login_from_checkout' ) ) {
			$this->add_signup_and_login_from_checkout_controls();
		}

		$this->start_controls_section(
			'order_summary_section',
			[
				'label' => esc_html__( 'Tu pedido', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_section_title',
			[
				'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Tu pedido', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
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
					'{{WRAPPER}}' => '--order-review-title-alignment: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		if ( $this->is_wc_feature_active( 'coupons' ) ) {
			$this->add_coupon_controls();
		}

		$this->start_controls_section(
			'payment_section',
			[
				'label' => esc_html__( 'Pago', 'pro-elements' ),
			]
		);

		$this->add_control(
			'terms_conditions_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Términos y condiciones', 'pro-elements' ),
			]
		);

		$this->add_control(
			'terms_conditions_message_text',
			[
				'label' => esc_html__( 'Mensaje', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
				'default' => esc_html__( 'He leído y acepto los', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'terms_conditions_link_text',
			[
				'label' => esc_html__( 'Texto del enlace', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
				'default' => esc_html__( 'términos y condiciones', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'purchase_buttom_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón de compra', 'pro-elements' ),
			]
		);

		$this->add_responsive_control(
			'purchase_button_alignment',
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
					'{{WRAPPER}} .woocommerce-checkout' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--place-order-title-alignment: flex-start; --purchase-button-width: fit-content;',
					'center' => '--place-order-title-alignment: center; --purchase-button-width: fit-content;',
					'end' => '--place-order-title-alignment: flex-end; --purchase-button-width: fit-content;',
					'justify' => '--place-order-title-alignment: stretch; --purchase-button-width: 100%;',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_style',
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
					'{{WRAPPER}}' => '--sections-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'section_normal_box_shadow',
				'selector' => $this->get_main_woocommerce_sections_selectors(),
			]
		);

		$this->add_control(
			'sections_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--sections-border-type: {{VALUE}};',
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
					$this->get_main_woocommerce_sections_selectors() => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--sections-border-color: {{VALUE}};',
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
					'{{WRAPPER}}' => '--sections-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--sections-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					// move the 'Ship to a different address?' checkbox
					'{{WRAPPER}} .woocommerce-shipping-fields' => '--shipping-heading-padding-start: {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--sections-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_typography',
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
					'{{WRAPPER}}' => '--sections-title-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_titles_typography',
				'selector' => $this->get_main_woocommerce_sections_title_selectors(),
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'sections_titles_text_shadow',
				'selector' => $this->get_main_woocommerce_sections_title_selectors(),
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
					'{{WRAPPER}}' => '--sections-title-spacing: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'sections_secondary_typography',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos secundarios', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'sections_secondary_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--sections-secondary-title-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_secondary_titles_typography',
				'selector' => '{{WRAPPER}} .digi-co__secondary',
			]
		);

		$this->add_responsive_control(
			'sections_secondary_title_spacing',
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
					'{{WRAPPER}}' => '--sections-secondary-title-spacing: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'sections_descriptions_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Descripciones', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'sections_descriptions_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--sections-descriptions-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_descriptions_typography',
				'selector' => '{{WRAPPER}} .digi-co__desc',
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
					'{{WRAPPER}}' => '--sections-descriptions-spacing: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'sections_messages_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Mensajes', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'sections_messages_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--sections-messages-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_messages_typography',
				'selector' => '{{WRAPPER}} .woocommerce-checkout #payment .payment_box, {{WRAPPER}} .woocommerce-privacy-policy-text p, {{WRAPPER}} .digi-co__message',
			]
		);

		$this->add_control(
			'sections_checkboxes_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Casillas', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'sections_checkboxes_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--sections-checkboxes-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_checkboxes_typography',
				'selector' => '{{WRAPPER}} .woocommerce-form__label-for-checkbox span',
			]
		);

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
					'{{WRAPPER}}' => '--sections-radio-buttons-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'sections_radio_buttons_typography',
				'selector' => '{{WRAPPER}} .wc_payment_method label, {{WRAPPER}} #shipping_method li label',
			]
		);

		// Links
		$this->add_control(
			'sections_links_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlaces', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->start_controls_tabs( 'links_colors' );

		$this->start_controls_tab( 'links_normal_colors', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control(
			'links_normal_color',
			[
				'label' => esc_html__( 'Color del enlace', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--links-normal-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'links_hover_colors', [
			'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
		] );

		$this->add_control(
			'links_hover_color',
			[
				'label' => esc_html__( 'Color del enlace', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--links-hover-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_forms',
			[
				'label' => esc_html__( 'Formularios', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'forms_columns_gap',
			[
				'label' => esc_html__( 'Separación de columnas', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--forms-columns-gap-padding: calc( {{SIZE}}{{UNIT}}/2 ); --forms-columns-gap-margin: calc( -{{SIZE}}{{UNIT}}/2 );',
				],
			]
		);

		$this->add_responsive_control(
			'forms_rows_gap',
			[
				'label' => esc_html__( 'Separación de filas', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--forms-rows-gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'forms_label_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Etiquetas', 'pro-elements' ),
			]
		);

		$this->add_control(
			'forms_label_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--forms-labels-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'forms_label_typography',
				'selector' => '{{WRAPPER}} .woocommerce-billing-fields .form-row label, {{WRAPPER}} .woocommerce-shipping-fields .form-row label, {{WRAPPER}} .woocommerce-additional-fields .form-row label, {{WRAPPER}} .digi-co__login-form .form-row label, {{WRAPPER}} .digi-co__coupon-desc',
			]
		);

		$this->add_responsive_control(
			'forms_label_spacing',
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
					'{{WRAPPER}}' => '--forms-label-spacing: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'forms_field_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Campos', 'pro-elements' ),
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'forms_field_typography',
				'selector' => '{{WRAPPER}} #customer_details .input-text, {{WRAPPER}} #customer_details .form-row textarea, {{WRAPPER}} #customer_details .form-row select, {{WRAPPER}} .digi-co__login-form .input-text, {{WRAPPER}} #coupon_code, {{WRAPPER}} ::placeholder, {{WRAPPER}} .select2-container--default .select2-selection--single, .select2-results__option',
			]
		);

		$this->start_controls_tabs( 'forms_fields_styles' );

		$this->start_controls_tab( 'forms_fields_normal_styles', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control(
			'forms_fields_normal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--forms-fields-normal-color: {{VALUE}};',
					'.digi-co-select2 .select2-results__option' => 'color: {{VALUE}};',
					// style select2 arrow
					'{{WRAPPER}} .select2-container--default .select2-selection--single .select2-selection__arrow b' => 'border-color: {{VALUE}} transparent transparent transparent;',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_fields_normal_background',
				'selector' => '{{WRAPPER}} .woocommerce #customer_details .form-row .input-text, {{WRAPPER}}  .woocommerce #customer_details .form-row textarea, {{WRAPPER}} .woocommerce form #customer_details select, {{WRAPPER}} .woocommerce .digi-co__login-form .form-row .input-text, {{WRAPPER}} #coupon_code, {{WRAPPER}} .select2-container--default .select2-selection--single, {{WRAPPER}} .woocommerce-checkout #payment .payment_methods .payment_box',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_fields_normal_box_shadow',
				'selector' => '{{WRAPPER}} #customer_details .input-text, {{WRAPPER}}  #customer_details .form-row textarea, {{WRAPPER}} .woocommerce form #customer_details select, {{WRAPPER}} .digi-co__login-form .input-text, {{WRAPPER}} #coupon_code, {{WRAPPER}} .select2-container--default .select2-selection--single',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'forms_fields_focus_styles', [
			'label' => esc_html__( 'Foco', 'pro-elements' ),
		] );

		$this->add_control(
			'forms_fields_focus_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--forms-fields-focus-color: {{VALUE}}',
					'.digi-co-select2 .select2-results__option:focus' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_fields_focus_background',
				'selector' => '{{WRAPPER}} .woocommerce #customer_details .form-row .input-text:focus, {{WRAPPER}}  .woocommerce #customer_details .form-row textarea:focus, {{WRAPPER}} #customer_details select:focus, {{WRAPPER}} .woocommerce .digi-co__login-form .form-row .input-text:focus, {{WRAPPER}} #coupon_code:focus, {{WRAPPER}} .select2-container--default .select2-selection--single:focus',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_fields_focus_box_shadow',
				'selector' => '{{WRAPPER}} #customer_details .input-text:focus, {{WRAPPER}} #customer_details textarea:focus, {{WRAPPER}} #customer_details select:focus, {{WRAPPER}} .digi-co__login-form .input-text:focus, {{WRAPPER}} #coupon_code:focus, {{WRAPPER}} .select2-container--default .select2-selection--single:focus',
			]
		);

		$this->add_control(
			'forms_fields_focus_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce #customer_details .form-row .input-text:focus, {{WRAPPER}}  .woocommerce #customer_details .form-row textarea:focus, {{WRAPPER}} #customer_details select:focus, {{WRAPPER}} .woocommerce .digi-co__login-form .form-row .input-text:focus, {{WRAPPER}} #coupon_code:focus, {{WRAPPER}} .select2-container--default .select2-selection--single:focus' => 'border-color: {{VALUE}}',
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
					'{{WRAPPER}}' => '--forms-fields-focus-transition-duration: {{SIZE}}ms',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'forms_fields_border',
				'selector' => '{{WRAPPER}} .woocommerce #customer_details .form-row .input-text, {{WRAPPER}}  .woocommerce #customer_details .form-row textarea, {{WRAPPER}} .woocommerce form #customer_details select, {{WRAPPER}} .woocommerce .digi-co__login-form .form-row .input-text, {{WRAPPER}} #coupon_code, {{WRAPPER}} .select2-container--default .select2-selection--single',
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
					'{{WRAPPER}}' => '--forms-fields-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--forms-fields-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
				'label' => esc_html__( 'Botón', 'pro-elements' ),
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'forms_button_typography',
				'selector' => '{{WRAPPER}} .woocommerce-button',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'forms_button_text_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-button',
			]
		);

		$this->start_controls_tabs( 'forms_buttons_styles' );

		$this->start_controls_tab( 'forms_buttons_normal_styles', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control(
			'forms_buttons_normal_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--forms-buttons-normal-text-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_buttons_normal_background',
				'selector' => '{{WRAPPER}} .woocommerce-button',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_buttons_normal_box_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'forms_buttons_hover_styles', [
			'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
		] );

		$this->add_control(
			'forms_buttons_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--forms-buttons-hover-text-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_buttons_hover_background',
				'selector' => '{{WRAPPER}} .woocommerce-button:hover',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_buttons_focus_box_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-button:hover',
			]
		);

		$this->add_control(
			'forms_buttons_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__apply:hover, {{WRAPPER}} .woocommerce-form-login__submit:hover' => 'border-color: {{VALUE}}',
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
					'{{WRAPPER}}' => '--forms-buttons-hover-transition-duration: {{SIZE}}ms',
				],
			]
		);

		$this->add_control(
			'forms_buttons_hover_animation',
			[
				'label' => esc_html__( 'Animación al pasar el cursor', 'pro-elements' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
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
					'{{WRAPPER}}' => '--forms-buttons-border-type: {{VALUE}};',
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
					'{{WRAPPER}} .digi-co__apply, {{WRAPPER}} .woocommerce-form-login__submit' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} ' => '--forms-buttons-border-color: {{VALUE}};',
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
					'{{WRAPPER}}' => '--forms-buttons-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .woocommerce-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; width: auto;',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_order_summary',
			[
				'label' => esc_html__( 'Resumen del pedido', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'order_summary_rows_gap',
			[
				'label' => esc_html__( 'Separación de filas', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--order-summary-rows-gap-top: calc( {{SIZE}}{{UNIT}}/2 ); --order-summary-rows-gap-bottom: calc( {{SIZE}}{{UNIT}}/2 );',
				],
			]
		);

		$this->add_control(
			'order_summary_items_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Elementos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_items_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--order-summary-items-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_items_typography',
				'selector' => '{{WRAPPER}} .woocommerce-checkout-review-order-table .cart_item td',
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
					'{{WRAPPER}}' => '--order-summary-variations-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_variations_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
				'selector' => '{{WRAPPER}} .product-name .variation',
			]
		);

		$this->add_control(
			'order_summary_items_divider_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Separadores', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_items_divider_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--order-summary-items-divider-color: {{VALUE}};',
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
					'{{WRAPPER}}' => '--order-summary-items-divider-weight: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'order_summary_totals_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos y totales', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_totals_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--order-summary-totals-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_totals_typography',
				'selector' => '{{WRAPPER}} .woocommerce-checkout-review-order-table thead tr th, {{WRAPPER}} .woocommerce-checkout-review-order-table tfoot tr th, {{WRAPPER}} .woocommerce-checkout-review-order-table tfoot tr td',
			]
		);

		$this->add_control(
			'order_summary_dividers_total_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Separador del total', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_totals_divider_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--order-summary-totals-divider-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_totals_divider_weight',
			[
				'label' => esc_html__( 'Grosor', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--order-summary-totals-divider-weight: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_purchase_button',
			[
				'label' => esc_html__( 'Botón de compra', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'purchase_button_typography',
				'selector' => '{{WRAPPER}} .woocommerce #payment #place_order',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'purchase_button_text_shadow',
				'selector' => '{{WRAPPER}} .woocommerce #payment #place_order',
			]
		);

		$this->start_controls_tabs( 'purchase_button_styles' );

		$this->start_controls_tab( 'purchase_button_normal_styles', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control(
			'purchase_button_normal_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--purchase-button-normal-text-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'purchase_button_normal_background',
				'selector' => '{{WRAPPER}} #payment #place_order',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'purchase_button_normal_box_shadow',
				'selector' => '{{WRAPPER}} #place_order',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'purchase_button_hover_styles', [
			'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
		] );

		$this->add_control(
			'purchase_button_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--purchase-button-hover-text-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'purchase_button_hover_background',
				'selector' => '{{WRAPPER}} #payment #place_order:hover',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'purchase_button_hover_box_shadow',
				'selector' => '{{WRAPPER}} #place_order:hover',
			]
		);

		$this->add_control(
			'purchase_button_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--purchase-button-hover-border-color: {{VALUE}}',
				],
				'condition' => [
					'purchase_button_border_border!' => '',
				],
			]
		);

		$this->add_control(
			'purchase_button_hover_transition_duration',
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
					'{{WRAPPER}}' => '--purchase-button-hover-transition-duration: {{SIZE}}ms',
				],
			]
		);

		$this->add_control(
			'purchase_button_hover_animation',
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
				'name' => 'purchase_button_border',
				'selector' => '{{WRAPPER}} #place_order',
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'purchase_button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--purchase-button-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'purchase_button_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--purchase-button-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; --purchase-button-width: fit-content;',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_customize',
			[
				'label' => esc_html__( 'Personalizar', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$customize_options = [];

		if ( $this->is_wc_feature_active( 'checkout_login_reminder' ) ) {
			$customize_options += [
				'customize_returning_customer' => esc_html__( 'Cliente habitual', 'pro-elements' ),
			];
		}

		$customize_options += [
			'customize_billing_details' => esc_html__( 'Detalles de facturación', 'pro-elements' ),
			'customize_additional_info' => esc_html__( 'Información adicional', 'pro-elements' ),
		];

		if ( $this->is_wc_feature_active( 'shipping' ) ) {
			$customize_options += [
				'customize_shipping_address' => esc_html__( 'Dirección de envío', 'pro-elements' ),
			];
		}

		$customize_options += [
			'customize_order_summary' => esc_html__( 'Resumen del pedido', 'pro-elements' ),
		];

		if ( $this->is_wc_feature_active( 'coupons' ) ) {
			$customize_options += [
				'customize_coupon' => esc_html__( 'Cupón', 'pro-elements' ),
			];
		}

		$customize_options += [
			'customize_payment' => esc_html__( 'Pago', 'pro-elements' ),
		];

		$this->add_control(
			'section_checkout_show_customize_elements',
			[
				'label' => esc_html__( 'Elige las secciones del pago que quieres personalizar:', 'pro-elements' ),
				'type' => Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $customize_options,
				'render_type' => 'ui',
				'label_block' => true,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_customize_returning_customer',
			[
				'label' => esc_html__( 'Personalizar: cliente habitual', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_checkout_show_customize_elements' => 'customize_returning_customer',
				],
			]
		);

		$this->add_control(
			'returning_customers_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'customize_returning_customer_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--sections-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'returning_customers_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-co__login',
			]
		);

		$this->add_control(
			'returning_customers_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--sections-border-type: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'returning_customers_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'returning_customers_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'returning_customers_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--sections-border-color: {{VALUE}};',
				],
				'condition' => [
					'returning_customers_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'returning_customers_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--sections-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'returning_customers_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--sections-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'returning_customers_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--sections-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'returning_customers_secondary_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título secundario', 'pro-elements' ),
			]
		);

		$this->add_control(
			'returning_customers_secondary_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-form-login-toggle' => '--sections-secondary-title-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'returning_customers_content_typography',
				'selector' => '{{WRAPPER}} .woocommerce-form-login-toggle',
			]
		);

		$this->add_control(
			'returning_customers_description_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
			]
		);

		$this->add_control(
			'returning_customers_description_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__login-nudge' => '--sections-descriptions-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'returning_customers_description_typography',
				'selector' => '{{WRAPPER}} .digi-co__login-nudge.digi-co__desc',
			]
		);

		$this->add_control(
			'returning_customers_checkboxes_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Casilla', 'pro-elements' ),
			]
		);

		$this->add_control(
			'returning_customers_checkboxes_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--sections-checkboxes-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'returning_customers_checkboxes_typography',
				'selector' => '{{WRAPPER}} .digi-co__login .woocommerce-form__label-for-checkbox span',
			]
		);

		$this->add_control(
			'returning_customers_link_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlace', 'pro-elements' ),
			]
		);

		$this->start_controls_tabs( 'returning_customers_links' );

		$this->start_controls_tab( 'returning_customers_normal_links', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control(
			'returning_customers_normal_links_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--links-normal-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'returning_customers_hover_links', [
			'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
		] );

		$this->add_control(
			'returning_customers_hover_links_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__login' => '--links-hover-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_customize_billing_details',
			[
				'label' => esc_html__( 'Personalizar: detalles de facturación', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_checkout_show_customize_elements' => 'customize_billing_details',
				],
			]
		);

		$this->add_control(
			'customize_billing_details_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'customize_billing_details_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .col2-set .col-1' => '--sections-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'billing_details_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-co__col--start .col2-set .col-1',
			]
		);

		$this->add_control(
			'billing_details_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .col2-set .col-1' => '--sections-border-type: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'billing_details_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__col--start #customer_details .col-1' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'billing_details_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'billing_details_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .col2-set .col-1' => '--sections-border-color: {{VALUE}}',
				],
				'condition' => [
					'billing_details_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'billing_details_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .col2-set .col-1' => '--sections-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'billing_details_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .col2-set .col-1' => '--sections-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'billing_details_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .col2-set .col-1' => '--sections-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'billing_details_titles_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título', 'pro-elements' ),
			]
		);

		$this->add_control(
			'billing_details_titles_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .col2-set .col-1' => '--sections-title-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'billing_details_titles_typography',
				'selector' => '{{WRAPPER}} .woocommerce-billing-fields h3',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'billing_details_titles_text_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-billing-fields h3',
			]
		);

		$this->add_control(
			'billing_details_checkboxes_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Casilla', 'pro-elements' ),
			]
		);

		$this->add_control(
			'billing_details_checkboxes_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .col2-set .col-1' => '--sections-checkboxes-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'billing_details_checkboxes_typography',
				'selector' => '{{WRAPPER}} .col2-set .col-1 .woocommerce-form__label-for-checkbox span',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_customize_additional_info',
			[
				'label' => esc_html__( 'Personalizar: información adicional', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_checkout_show_customize_elements' => 'customize_additional_info',
				],
			]
		);

		$this->add_control(
			'customize_additional_information_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'customize_additional_information_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-additional-fields' => '--sections-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'additional_information_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-additional-fields',
			]
		);

		$this->add_control(
			'additional_information_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .woocommerce-additional-fields' => '--sections-border-type: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'additional_information_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-additional-fields' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'additional_information_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'additional_information_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-additional-fields' => '--sections-border-color: {{VALUE}};',
				],
				'condition' => [
					'additional_information_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'additional_information_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-additional-fields' => '--sections-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'additional_information_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-additional-fields' => '--sections-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'additional_information_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-additional-fields' => '--sections-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}}.digi-checkout-layout-one-column .digi-co__container' => 'grid-row-gap: {{BOTTOM}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'additional_information_titles_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título', 'pro-elements' ),
			]
		);

		$this->add_control(
			'additional_information_titles_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-additional-fields' => '--sections-title-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'additional_information_titles_typography',
				'selector' => '{{WRAPPER}} .woocommerce-additional-fields h3',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'additional_information_titles_text_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-additional-fields h3',
			]
		);

		$this->end_controls_section();

		if ( $this->is_wc_feature_active( 'shipping' ) ) {
			$this->add_shipping_style_controls();
		}

		if ( $this->is_wc_feature_active( 'coupons' ) ) {
			$this->add_coupons_style_controls();
		}

		$this->start_controls_section(
			'section_checkout_tabs_customize_order_summary',
			[
				'label' => esc_html__( 'Personalizar: resumen del pedido', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_checkout_show_customize_elements' => 'customize_order_summary',
				],
			]
		);

		$this->add_control(
			'customize_order_summary_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'customize_order_summary_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'order_summary_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-co__review',
			]
		);

		$this->add_control(
			'order_summary_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-border-type: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'order_summary_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'order_summary_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'order_summary_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-border-color: {{VALUE}}',
				],
				'condition' => [
					'order_summary_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'order_summary_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'order_summary_titles_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_titles_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-title-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_titles_typography',
				'selector' => '{{WRAPPER}} h3#order_review_heading',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'order_summary_titles_text_shadow',
				'selector' => '{{WRAPPER}} h3#order_review_heading',
			]
		);

		$this->add_control(
			'order_summary_descriptions_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Mensaje', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_descriptions_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-descriptions-color: {{VALUE}}; --sections-messages-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_descriptions_typography',
				'selector' => '{{WRAPPER}} .woocommerce-no-shipping-available-html.digi-co__desc, {{WRAPPER}} .woocommerce-no-shipping-available-html.digi-co__message',
			]
		);

		$this->add_control(
			'order_summary_radios_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón de opción', 'pro-elements' ),
			]
		);

		$this->add_control(
			'order_summary_radios_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__review' => '--sections-radio-buttons-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'order_summary_radio_typography',
				'selector' => '{{WRAPPER}} .woocommerce .digi-co__review ul#shipping_method li label',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_checkout_tabs_customize_payment',
			[
				'label' => esc_html__( 'Personalizar: pago', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_checkout_show_customize_elements' => 'customize_payment',
				],
			]
		);

		$this->add_control(
			'customize_payment_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'customize_payment_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout #payment' => '--sections-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'payment_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-checkout #payment',
			]
		);

		$this->add_control(
			'payment_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout #payment' => '--sections-border-type: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'payment_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout #payment' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'payment_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'payment_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout #payment' => '--sections-border-color: {{VALUE}};',
				],
				'condition' => [
					'payment_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'payment_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout #payment' => '--sections-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'payment_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout #payment' => '--sections-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'payment_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout #payment' => '--sections-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'payment_info_box_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Caja de información', 'pro-elements' ),
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'payment_info_box_title_background',
				'selector' => '{{WRAPPER}} .woocommerce-checkout #payment .payment_methods .payment_box',
			]
		);

		$this->add_control(
			'payment_description_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_description_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout-payment' => '--sections-descriptions-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'payment_description_typography',
				'selector' => '{{WRAPPER}} .woocommerce-checkout-payment .digi-co__desc',
			]
		);

		$this->add_control(
			'payment_messages_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Mensaje', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_messages_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout-payment' => '--sections-messages-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'payment_messages_typography',
				'selector' => '{{WRAPPER}} .woocommerce-checkout #payment .payment_box, {{WRAPPER}} .woocommerce-privacy-policy-text p',
			]
		);

		$this->add_control(
			'payment_checkboxes_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Casilla', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_checkboxes_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-terms-and-conditions-wrapper' => '--sections-checkboxes-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'payment_checkboxes_typography',
				'selector' => '{{WRAPPER}} .woocommerce-terms-and-conditions-wrapper .woocommerce-form__label-for-checkbox span',
			]
		);

		$this->add_control(
			'payment_radio_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón de opción', 'pro-elements' ),
			]
		);

		$this->add_control(
			'payment_radio_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout-payment' => '--sections-radio-buttons-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'payment_radio_typography',
				'selector' => '{{WRAPPER}} .woocommerce-checkout-payment .wc_payment_method label',
			]
		);

		$this->add_control(
			'payment_links_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlaces', 'pro-elements' ),
			]
		);

		$this->start_controls_tabs( 'payment_colors' );

		$this->start_controls_tab( 'payment_normal_colors', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control(
			'payment_normal_color',
			[
				'label' => esc_html__( 'Color del enlace', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout-payment' => '--links-normal-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'payment_hover_colors', [
			'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
		] );

		$this->add_control(
			'payment_hover_color',
			[
				'label' => esc_html__( 'Color del enlace', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-checkout-payment' => '--links-hover-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function add_checkout_login_reminder_controls() {
		$this->start_controls_section(
			'returning_customer_heading',
			[
				'label' => esc_html__( 'Cliente habitual', 'pro-elements' ),
			]
		);

		$this->add_control(
			'returning_customer_section_title',
			[
				'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( '¿Ya eres cliente?', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'returning_customer_link_text',
			[
				'label' => esc_html__( 'Texto del enlace', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Haz clic aquí para acceder', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_responsive_control(
			'returning_customer_title_alignment',
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
					'{{WRAPPER}}' => '--login-title-alignment: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'login_button_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón Acceder', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'login_button_alignment',
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
					'{{WRAPPER}} .digi-co__login-wrap' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--login-button-alignment: start; --login-button-width: 35%;',
					'center' => '--login-button-alignment: center;  --login-button-width: 35%;',
					'end' => '--login-button-alignment: end;  --login-button-width: 35%;',
					'justify' => '--login-button-alignment: center; --login-button-width: 100%;',
				],
			]
		);

		$this->add_control(
			'login_button_alignment_note',
			[
				'raw' => esc_html__( 'Nota: este control sólo afecta a pantallas de tableta y menores', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->end_controls_section();
	}

	private function add_shipping_controls() {
		$this->start_controls_section(
			'shipping_details_section',
			[
				'label' => esc_html__( 'Detalles de envío', 'pro-elements' ),
			]
		);

		$this->add_control(
			'shipping_details_section_title',
			[
				'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( '¿Enviar a una dirección diferente?', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'repeater_state',
			[
				'label' => esc_html__( 'Estado del repetidor (oculto)', 'pro-elements' ),
				'type' => Controls_Manager::HIDDEN,
			]
		);

		$repeater->add_control(
			'label_placeholder_notification',
			[
				'raw' => __( 'Nota: esta etiqueta y este texto de ejemplo salen de la sección de facturación. Puedes cambiarlos allí.', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
				'condition' => [
					'repeater_state' => 'from_billing',
				],
			]
		);

		$repeater->start_controls_tabs( 'tabs', [
			'condition' => [
				'repeater_state' => '',
			],
		] );

		$repeater->start_controls_tab( 'content_tab', [
			'label' => esc_html__( 'Contenido', 'pro-elements' ),
		] );

		$repeater->add_control(
			'label',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
			]
		);

		$repeater->add_control(
			'placeholder',
			[
				'label' => esc_html__( 'Texto de ejemplo', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
			]
		);

		$repeater->end_controls_tab();

		$repeater->start_controls_tab( 'advanced_tab', [
			'label' => esc_html__( 'Avanzado', 'pro-elements' ),
		] );

		$repeater->add_control(
			'default',
			[
				'label' => esc_html__( 'Valor por defecto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->end_controls_tab();

		$repeater->end_controls_tabs();

		$repeater->add_control(
			'locale_notice',
			[
				'raw' => __( 'Nota: este contenido no puede cambiarse por las normas locales.', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
				'condition' => [
					'repeater_state' => 'locale',
				],
			]
		);

		$repeater->add_control(
			'from_billing_notice',
			[
				'raw' => __( 'Nota: esta etiqueta y este texto de ejemplo salen de la sección de facturación. Puedes cambiarlos allí.', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
				'condition' => [
					'repeater_state' => 'from_billing',
				],
			]
		);

		$this->add_control(
			'shipping_details_form_fields',
			[
				'label' => esc_html__( 'Elementos del formulario', 'pro-elements' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'item_actions' => [
					'add' => false,
					'duplicate' => false,
					'remove' => false,
					'sort' => false,
				],
				'default' => $this->get_shipping_field_defaults(),
				'title_field' => '{{{ label }}}',
			]
		);

		$this->end_controls_section();
	}

	private function add_signup_and_login_from_checkout_controls() {
		$this->start_controls_section(
			'create_account_section',
			[
				'label' => esc_html__( 'Crear una cuenta', 'pro-elements' ),
			]
		);

		$this->add_control(
			'create_account_text',
			[
				'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( '¿Crear una cuenta?', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->end_controls_section();
	}

	private function add_coupon_controls() {
		$this->start_controls_section(
			'coupon_section',
			[
				'label' => esc_html__( 'Cupón', 'pro-elements' ),
			]
		);

		$this->add_control(
			'coupon_section_display',
			[
				'label' => esc_html__( 'Cupón', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default' => 'yes',
			]
		);

		$this->add_control(
			'coupon_section_title_text',
			[
				'label' => esc_html__( 'Título de la sección', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( '¿Tienes un cupón?', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
				'condition' => [
					'coupon_section_display' => 'yes',
				],
			]
		);

		$this->add_control(
			'coupon_section_title_link_text',
			[
				'label' => esc_html__( 'Texto del enlace', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Haz clic aquí para introducir tu código de cupón', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
				'condition' => [
					'coupon_section_display' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_alignment',
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
					'{{WRAPPER}}' => '--coupon-title-alignment: {{VALUE}};',
				],
				'condition' => [
					'coupon_section_display' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_button_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón Aplicar', 'pro-elements' ),
				'separator' => 'before',
				'condition' => [
					'coupon_section_display' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_button_alignment',
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
					'{{WRAPPER}} .coupon-container-grid' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--coupon-button-alignment: start;',
					'center' => '--coupon-button-alignment: center;',
					'end' => '--coupon-button-alignment: end;',
					'justify' => '--coupon-button-alignment: justify; --coupon-button-width: 100%;',
				],
				'condition' => [
					'coupon_section_display' => 'yes',
				],
			]
		);

		$this->add_control(
			'coupon_button_alignment_note',
			[
				'raw' => esc_html__( 'Nota: este control sólo afecta a pantallas de tableta y menores', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
				'condition' => [
					'coupon_section_display' => 'yes',
				],
			]
		);

		$this->end_controls_section();
	}

	private function add_shipping_style_controls() {
		$this->start_controls_section(
			'section_checkout_tabs_customize_shipping_address',
			[
				'label' => esc_html__( 'Personalizar: dirección de envío', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_checkout_show_customize_elements' => 'customize_shipping_address',
				],
			]
		);

		$this->add_control(
			'shipping_address_section_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Sección', 'pro-elements' ),
			]
		);

		$this->add_control(
			'customize_shipping_address_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-shipping-fields .shipping_address' => '--sections-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'shipping_address_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-shipping-fields .shipping_address',
			]
		);

		$this->add_control(
			'shipping_address_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .woocommerce-shipping-fields .shipping_address' => '--sections-border-type: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'shipping_address_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-shipping-fields .shipping_address' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'shipping_address_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'shipping_address_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-shipping-fields .shipping_address' => '--sections-border-color: {{VALUE}}',
				],
				'condition' => [
					'shipping_address_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'shipping_address_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-shipping-fields .shipping_address' => '--sections-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'shipping_address_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-shipping-fields .shipping_address' => '--sections-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'shipping_address_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-shipping-fields .shipping_address' => '--sections-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'shipping_address_checkboxes_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Casillas', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'shipping_address_checkboxes_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woocommerce-shipping-fields' => '--sections-checkboxes-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'shipping_address_checkboxes_typography',
				'selector' => '{{WRAPPER}} .woocommerce-shipping-fields .woocommerce-form__label-for-checkbox span',
			]
		);

		$this->end_controls_section();
	}

	private function add_coupons_style_controls() {
		$this->start_controls_section(
			'section_checkout_tabs_customize_coupon',
			[
				'label' => esc_html__( 'Personalizar: cupón', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'section_checkout_show_customize_elements' => 'customize_coupon',
				],
			]
		);

		$this->add_control(
			'customize_coupon_section_title',
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
					'{{WRAPPER}} .digi-co__coupon' => '--sections-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'coupon_section_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-co__coupon',
			]
		);

		$this->add_control(
			'coupon_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon' => '--sections-border-type: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'coupon_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'coupon_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'coupon_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon' => '--sections-border-color: {{VALUE}};',
				],
				'condition' => [
					'coupon_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon' => '--sections-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon' => '--sections-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'coupon_margin',
			[
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon' => '--sections-margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'coupon_secondary_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título secundario', 'pro-elements' ),
			]
		);

		$this->add_control(
			'coupon_secondary_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon-nudge' => '--sections-secondary-title-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'coupon_content_typography',
				'selector' => '{{WRAPPER}} .digi-co__coupon-nudge.digi-co__secondary',
			]
		);

		$this->add_control(
			'coupon_link_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlace', 'pro-elements' ),
			]
		);

		$this->start_controls_tabs( 'coupon_links' );

		$this->start_controls_tab( 'coupon_normal_links', [
			'label' => esc_html__( 'Normal', 'pro-elements' ),
		] );

		$this->add_control(
			'coupon_normal_links_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon' => '--links-normal-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'coupon_hover_links', [
			'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
		] );

		$this->add_control(
			'coupon_hover_links_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-co__coupon' => '--links-hover-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Get Billing Field Defaults
	 *
	 * Get defaults used for the billing details repeater control.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	private function get_billing_field_defaults() {
		$fields = [
			'billing_first_name' => [
				'label' => esc_html__( 'Nombre', 'pro-elements' ),
				'repeater_state' => '',
			],
			'billing_last_name' => [
				'label' => esc_html__( 'Apellidos', 'pro-elements' ),
				'repeater_state' => '',
			],
			'billing_company' => [
				'label' => esc_html__( 'Nombre de la empresa', 'pro-elements' ),
				'repeater_state' => '',
			],
			'billing_country' => [
				'label' => esc_html__( 'País / Región', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'billing_address_1' => [
				'label' => esc_html__( 'Dirección', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'billing_postcode' => [
				'label' => esc_html__( 'Código postal', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'billing_city' => [
				'label' => esc_html__( 'Localidad / Ciudad', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'billing_state' => [
				'label' => esc_html__( 'Región / Provincia', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'billing_phone' => [
				'label' => esc_html__( 'Teléfono', 'pro-elements' ),
				'repeater_state' => '',
			],
			'billing_email' => [
				'label' => esc_html__( 'Dirección de correo electrónico', 'pro-elements' ),
				'repeater_state' => '',
			],
		];

		return $this->reformat_address_field_defaults( $fields );
	}

	/**
	 * Get Shipping Field Defaults
	 *
	 * Get defaults used for the shipping details repeater control.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	private function get_shipping_field_defaults() {
		$fields = [
			'shipping_first_name' => [
				'label' => esc_html__( 'Nombre', 'pro-elements' ),
				'repeater_state' => '',
			],
			'shipping_last_name' => [
				'label' => esc_html__( 'Apellidos', 'pro-elements' ),
				'repeater_state' => '',
			],
			'shipping_company' => [
				'label' => esc_html__( 'Nombre de la empresa', 'pro-elements' ),
				'repeater_state' => '',
			],
			'shipping_country' => [
				'label' => esc_html__( 'País / Región', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'shipping_address_1' => [
				'label' => esc_html__( 'Dirección', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'shipping_postcode' => [
				'label' => esc_html__( 'Código postal', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'shipping_city' => [
				'label' => esc_html__( 'Localidad / Ciudad', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
			'shipping_state' => [
				'label' => esc_html__( 'Región / Provincia', 'pro-elements' ),
				'repeater_state' => 'locale',
			],
		];

		return $this->reformat_address_field_defaults( $fields );
	}

	/**
	 * Reformat Address Field Defaults
	 *
	 * Used with the `get_..._field_defaults()` methods.
	 * Takes the address array and converts it into the format expected by the repeater controls.
	 *
	 * @since 3.5.0
	 *
	 * @param $address
	 * @return array
	 */
	private function reformat_address_field_defaults( $address ) {
		$defaults = [];
		foreach ( $address as $key => $value ) {
			$defaults[] = [
				'field_key' => $key,
				'field_label' => $value['label'],
				'label' => $value['label'],
				'placeholder' => $value['label'],
				'repeater_state' => $value['repeater_state'],
			];
		}

		return $defaults;
	}

	/**
	 * Get Main Woocommerce Sections Selectors
	 *
	 * Get all the 'Sections' selectors. There are numerous controls that need these selectors so it was easier
	 * to consolidate them into one function. Especially when updates need to be made.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	private function get_main_woocommerce_sections_selectors() {
		$selector = '{{WRAPPER}} .digi-co__login, {{WRAPPER}} .woocommerce-checkout #customer_details .col-1, {{WRAPPER}} .woocommerce-additional-fields, {{WRAPPER}} .digi-co__review, {{WRAPPER}} .digi-co__coupon, {{WRAPPER}} .woocommerce-checkout #payment';
		if ( $this->is_wc_feature_active( 'shipping' ) ) {
			$selector .= ', {{WRAPPER}} .woocommerce-shipping-fields .shipping_address';
		}
		return $selector;
	}

	/**
	 * Get Main Woocommerce Sections Title Selectors
	 *
	 * Get all the 'Title' selectors. There are numerous controls that need these selectors so it was easier to
	 * consolidate them into one function. Especially when updates need to be made.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	private function get_main_woocommerce_sections_title_selectors() {
		return '{{WRAPPER}} h3#order_review_heading, {{WRAPPER}} .woocommerce-billing-fields h3, {{WRAPPER}} .woocommerce-additional-fields h3';
	}

	/**
	 * Init Gettext Modifications
	 *
	 * Sets the `$gettext_modifications` property used with the `filter_gettext()` in the extended Base_Widget.
	 *
	 * @since 3.5.0
	 */

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
			'Billing details' => isset( $instance['billing_details_section_title'] ) ? $instance['billing_details_section_title'] : '',
			'Billing &amp; Shipping' => isset( $instance['billing_details_section_title'] ) ? $instance['billing_details_section_title'] : '',
			'Ship to a different address?' => isset( $instance['shipping_details_section_title'] ) ? $instance['shipping_details_section_title'] : '',
			'Additional information' => isset( $instance['additional_information_section_title'] ) ? $instance['additional_information_section_title'] : '',
			'Your order' => isset( $instance['order_summary_section_title'] ) ? $instance['order_summary_section_title'] : '',
			'Have a coupon?' => isset( $instance['coupon_section_title_text'] ) ? $instance['coupon_section_title_text'] : '',
			'Click here to enter your coupon code' => isset( $instance['coupon_section_title_link_text'] ) ? $instance['coupon_section_title_link_text'] : '',
			'Returning customer?' => isset( $instance['returning_customer_section_title'] ) ? $instance['returning_customer_section_title'] : '',
			'Click here to login' => isset( $instance['returning_customer_link_text'] ) ? $instance['returning_customer_link_text'] : '',
			'Create an account?' => isset( $instance['create_account_text'] ) ? $instance['create_account_text'] : '',
		];
	}

	/**
	 * WooCommerce Terms and Conditions Checkbox Text.
	 *
	 * WooCommerce filter is used to apply widget settings to Checkout Terms & Conditions text and link text.
	 *
	 * @since 3.5.0
	 *
	 * @param string $text
	 * @return string
	 */
	public function woocommerce_terms_and_conditions_checkbox_text( $text ) {
		$instance = $this->get_settings_for_display();

		if ( ! isset( $instance['terms_conditions_message_text'] ) || ! isset( $instance['terms_conditions_link_text'] ) ) {
			return $text;
		}

		$message = $instance['terms_conditions_message_text'];
		$link = $instance['terms_conditions_link_text'];

		$terms_page_id = wc_terms_and_conditions_page_id();
		if ( $terms_page_id ) {
			$message .= ' <a href="' . esc_url( get_permalink( $terms_page_id ) ) . '" class="woocommerce-terms-and-conditions-link" target="_blank">' . $link . '</a>';
		}

		return $message;
	}

	/**
	 * Modify Form Field.
	 *
	 * WooCommerce filter is used to apply widget settings to the Checkout forms address fields
	 * from the Billing and Shipping Details widget sections, e.g. label, placeholder, default.
	 *
	 * @since 3.5.0
	 *
	 * @param array $args
	 * @param string $key
	 * @param string $value
	 * @return array
	 */
	public function modify_form_field( $args, $key, $value ) {
		$reformatted_form_fields = $this->get_reformatted_form_fields();

		// Check if we need to modify the args of this form field.
		if ( isset( $reformatted_form_fields[ $key ] ) ) {
			$apply_fields = [
				'label',
				'placeholder',
				'default',
			];

			foreach ( $apply_fields as $field ) {
				if ( ! empty( $reformatted_form_fields[ $key ][ $field ] ) ) {
					$args[ $field ] = $reformatted_form_fields[ $key ][ $field ];
				}
			}
		}

		return $args;
	}

	/**
	 * Get Reformatted Form Fields.
	 *
	 * Combines the 3 relevant repeater settings arrays into a one level deep associative array
	 * with the keys that match those that WooCommerce uses for its form fields.
	 *
	 * The result is cached so the conversion only ever happens once.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	private function get_reformatted_form_fields() {
		if ( ! isset( $this->reformatted_form_fields ) ) {
			$instance = $this->get_settings_for_display();

			// Reformat form repeater field into one usable array.
			$repeater_fields = [
				'billing_details_form_fields',
				'shipping_details_form_fields',
				'additional_information_form_fields',
			];

			$this->reformatted_form_fields = [];

			// Apply other modifications to inputs.
			foreach ( $repeater_fields as $repeater_field ) {
				if ( isset( $instance[ $repeater_field ] ) ) {
					foreach ( $instance[ $repeater_field ] as $item ) {
						if ( ! isset( $item['field_key'] ) ) {
							continue;
						}
						$this->reformatted_form_fields[ $item['field_key'] ] = $item;
					}
				}
			}
		}

		return $this->reformatted_form_fields;
	}

	/**
	 * Formulario de acceso dentro de la primera columna (PRO quita el de WooCommerce de `woocommerce_before_checkout_form`
	 * y pinta el suyo aquí). El conmutador es un botón y el panel queda oculto con `hidden` (abierto en el editor).
	 */
	private function render_woocommerce_checkout_login_form() {
		$settings = $this->get_settings_for_display();
		$editor   = self::is_editor();
		$panel    = 'digi-co-login-' . $this->get_id();
		$button_classes = array( 'woocommerce-button', 'button', 'woocommerce-form-login__submit', 'digi-co__login-submit' );
		if ( ! empty( $settings['forms_buttons_hover_animation'] ) ) {
			$button_classes[] = 'elementor-animation-' . $settings['forms_buttons_hover_animation'];
		}
		$this->add_render_attribute(
			'button_login',
			array(
				'class' => $button_classes,
				'name'  => 'login',
				'type'  => 'submit',
			)
		);
		$title = isset( $settings['returning_customer_section_title'] ) ? $settings['returning_customer_section_title'] : esc_html__( '¿Ya eres cliente?', 'pro-elements' );
		$link  = isset( $settings['returning_customer_link_text'] ) ? $settings['returning_customer_link_text'] : esc_html__( 'Haz clic aquí para acceder', 'pro-elements' );
		?>
		<div class="digi-co__login">
			<div class="digi-co__login-messages"></div>
			<div class="woocommerce-form-login-toggle digi-co__secondary">
				<?php echo esc_html( $title ); ?> <button type="button" class="digi-co__show-login" aria-controls="<?php echo esc_attr( $panel ); ?>" aria-expanded="<?php echo $editor ? 'true' : 'false'; ?>"><?php echo esc_html( $link ); ?></button>
			</div>
			<div class="digi-co__login-form" id="<?php echo esc_attr( $panel ); ?>"<?php echo $editor ? '' : ' hidden'; ?>>
				<p class="digi-co__login-nudge digi-co__desc"><?php echo esc_html__( 'Si ya has comprado con nosotros, introduce tus datos abajo. Si eres un cliente nuevo, continúa en la sección de facturación.', 'pro-elements' ); ?></p>
				<div class="digi-co__login-wrap">
					<div class="digi-co__login-wrap-start">
						<p class="form-row form-row-first">
							<label for="username"><?php echo esc_html__( 'Correo electrónico', 'pro-elements' ); ?> <span class="required">*</span></label>
							<input type="text" class="input-text" name="username" id="username" autocomplete="username" />
						</p>
						<p class="form-row form-row-last">
							<label for="password"><?php echo esc_html__( 'Contraseña', 'pro-elements' ); ?> <span class="required">*</span></label>
							<input class="input-text" type="password" name="password" id="password" autocomplete="current-password" />
						</p>
						<div class="clear"></div>
					</div>
					<div class="digi-co__login-wrap-end">
						<p class="form-row">
							<label for="login" class="digi-co__login-label">&nbsp;</label>
							<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
							<input type="hidden" name="redirect" value="<?php echo esc_url( get_permalink() ); ?>" />
							<button <?php $this->print_render_attribute_string( 'button_login' ); ?> value="<?php echo esc_attr__( 'Acceder', 'pro-elements' ); ?>"><?php echo esc_html__( 'Acceder', 'pro-elements' ); ?></button>
						</p>
						<div class="clear"></div>
					</div>
				</div>
				<div class="digi-co__login-actions">
					<div class="digi-co__login-actions-start">
						<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
							<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span class="digi-co__remember"><?php echo esc_html__( 'Recordarme', 'pro-elements' ); ?></span>
						</label>
					</div>
					<div class="digi-co__login-actions-end">
						<p class="lost_password">
							<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo esc_html__( '¿Has olvidado tu contraseña?', 'pro-elements' ); ?></a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/** Formulario de cupón dentro de la segunda columna, entre el resumen y el pago (como PRO). */
	private function render_woocommerce_checkout_coupon_form() {
		$settings = $this->get_settings_for_display();
		$editor   = self::is_editor();
		$panel    = 'digi-co-coupon-' . $this->get_id();
		$button_classes = array( 'woocommerce-button', 'button', 'digi-co__apply' );
		if ( ! empty( $settings['forms_buttons_hover_animation'] ) ) {
			$button_classes[] = 'elementor-animation-' . $settings['forms_buttons_hover_animation'];
		}
		$this->add_render_attribute(
			'button_coupon',
			array(
				'class' => $button_classes,
				'name'  => 'apply_coupon',
				'type'  => 'submit',
			)
		);
		$title = isset( $settings['coupon_section_title_text'] ) ? $settings['coupon_section_title_text'] : esc_html__( '¿Tienes un cupón?', 'pro-elements' );
		$link  = isset( $settings['coupon_section_title_link_text'] ) ? $settings['coupon_section_title_link_text'] : esc_html__( 'Haz clic aquí para introducir tu código de cupón', 'pro-elements' );
		?>
		<div class="digi-co__coupon">
			<p class="digi-co__coupon-nudge digi-co__secondary"><?php echo esc_html( $title ); ?> <button type="button" class="digi-co__show-coupon" aria-controls="<?php echo esc_attr( $panel ); ?>" aria-expanded="<?php echo $editor ? 'true' : 'false'; ?>"><?php echo esc_html( $link ); ?></button></p>
			<div class="digi-co__coupon-form" id="<?php echo esc_attr( $panel ); ?>"<?php echo $editor ? '' : ' hidden'; ?>>
				<label class="digi-co__coupon-desc" for="coupon_code"><?php echo esc_html__( 'Si tienes un código de cupón, aplícalo abajo.', 'pro-elements' ); ?></label>
				<div class="form-row">
					<div class="coupon-container-grid">
						<div class="col coupon-col-1 ">
							<input type="text" name="coupon_code" class="input-text" placeholder="<?php echo esc_attr__( 'Código de cupón', 'pro-elements' ); ?>" id="coupon_code" value="" />
						</div>
						<div class="col coupon-col-2">
							<button <?php $this->print_render_attribute_string( 'button_coupon' ); ?>><?php echo esc_html__( 'Aplicar', 'pro-elements' ); ?></button>
						</div>
					</div>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	/** Igual que PRO: el recordatorio de acceso activo y una persona sin sesión (o el editor). */
	private function should_render_login() {
		return 'no' !== get_option( 'woocommerce_enable_checkout_login_reminder' ) && ( ! is_user_logged_in() || self::is_edit_mode() );
	}

	/** Igual que PRO: cupones activos, «Cupón» a la vista y un pedido que pagar (o el editor). */
	private function should_render_coupon() {
		if ( ! function_exists( 'wc_coupons_enabled' ) || ! wc_coupons_enabled() ) {
			return false;
		}
		$settings = $this->get_settings_for_display();
		$display  = ! ( isset( $settings['coupon_section_display'] ) && '' === $settings['coupon_section_display'] );
		return ( ( WC()->cart && WC()->cart->needs_payment() ) || self::is_edit_mode() ) && $display;
	}

	/** ¿Se está editando el documento? (PRO mira sólo el modo de edición, no la vista previa). */
	private static function is_edit_mode(): bool {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/** `woocommerce_checkout_before_customer_details` (5): abre la rejilla y la primera columna; el acceso va dentro. */
	public function woocommerce_checkout_before_customer_details() {
		echo '<div class="digi-co__container"><div class="digi-co__col digi-co__col--start">';
		if ( $this->should_render_login() ) {
			$this->render_woocommerce_checkout_login_form();
		}
	}

	/** `woocommerce_checkout_after_customer_details` (95): cierra la primera columna. */
	public function woocommerce_checkout_after_customer_details() {
		echo '</div>';
	}

	/**
	 * `woocommerce_checkout_before_order_review_heading` (5): abre la segunda columna. Con «Columna derecha fija» (sólo
	 * en dos columnas, como la condición del control), la columna interior lleva la clase activa y su desplazamiento, que
	 * PRO ponía con su script.
	 */
	public function woocommerce_checkout_before_order_review_heading_1() {
		$settings = $this->get_settings_for_display();
		$sticky   = ! empty( $settings['sticky_right_column'] ) && 'two-column' === ( isset( $settings['checkout_layout'] ) ? $settings['checkout_layout'] : 'two-column' );
		$offset   = $sticky && isset( $settings['sticky_right_column_offset'] ) ? (int) $settings['sticky_right_column_offset'] : 0;
		echo '<div class="digi-co__col digi-co__col--end"><div class="digi-co__col-inner digi-co__sticky' . ( $sticky ? ' digi-co__sticky--active' : '' ) . '"' . ( $offset ? ' style="--digi-co-top:' . esc_attr( $offset ) . 'px"' : '' ) . '>';
	}

	/** `woocommerce_checkout_before_order_review_heading` (95): abre la caja del resumen. */
	public function woocommerce_checkout_before_order_review_heading_2() {
		echo '<div class="digi-co__review">';
	}

	/** `woocommerce_checkout_order_review` (15): cierra el resumen, pinta el cupón y abre la caja del pago. */
	public function woocommerce_checkout_order_review() {
		echo '</div></div>';
		if ( $this->should_render_coupon() ) {
			$this->render_woocommerce_checkout_coupon_form();
		}
		echo '<div class="digi-co__review-2">';
	}

	/** `woocommerce_checkout_after_order_review` (95): cierra la columna interior, la segunda columna y la rejilla. */
	public function woocommerce_checkout_after_order_review() {
		echo '</div></div></div>';
	}

	/** Ganchos y filtros del widget, también los que reaplica la revisión del pedido por `?wc-ajax=` (como PRO). */
	public function add_render_hooks() {
		// Ajustes de esta pintura (los textos y campos se leen otra vez si el mismo elemento se pinta de nuevo).
		$this->gettext_modifications   = null;
		$this->reformatted_form_fields = null;
		add_filter( 'woocommerce_form_field_args', array( $this, 'modify_form_field' ), 70, 3 );
		add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', array( $this, 'woocommerce_terms_and_conditions_checkbox_text' ), 10, 1 );
		add_filter( 'gettext', array( $this, 'filter_gettext' ), 20, 3 );
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'woocommerce_checkout_before_customer_details' ), 5 );
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'woocommerce_checkout_after_customer_details' ), 95 );
		add_action( 'woocommerce_checkout_before_order_review_heading', array( $this, 'woocommerce_checkout_before_order_review_heading_1' ), 5 );
		add_action( 'woocommerce_checkout_before_order_review_heading', array( $this, 'woocommerce_checkout_before_order_review_heading_2' ), 95 );
		add_action( 'woocommerce_checkout_order_review', array( $this, 'woocommerce_checkout_order_review' ), 15 );
		add_action( 'woocommerce_checkout_after_order_review', array( $this, 'woocommerce_checkout_after_order_review' ), 95 );
		// Los formularios de acceso y de cupón de WooCommerce se sustituyen por los del widget.
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form' );
	}

	/** Quita lo que puso `add_render_hooks()`. */
	public function remove_render_hooks() {
		remove_filter( 'woocommerce_form_field_args', array( $this, 'modify_form_field' ), 70 );
		remove_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', array( $this, 'woocommerce_terms_and_conditions_checkbox_text' ), 10 );
		remove_filter( 'gettext', array( $this, 'filter_gettext' ), 20 );
		remove_action( 'woocommerce_checkout_before_customer_details', array( $this, 'woocommerce_checkout_before_customer_details' ), 5 );
		remove_action( 'woocommerce_checkout_after_customer_details', array( $this, 'woocommerce_checkout_after_customer_details' ), 95 );
		remove_action( 'woocommerce_checkout_before_order_review_heading', array( $this, 'woocommerce_checkout_before_order_review_heading_1' ), 5 );
		remove_action( 'woocommerce_checkout_before_order_review_heading', array( $this, 'woocommerce_checkout_before_order_review_heading_2' ), 95 );
		remove_action( 'woocommerce_checkout_order_review', array( $this, 'woocommerce_checkout_order_review' ), 15 );
		remove_action( 'woocommerce_checkout_after_order_review', array( $this, 'woocommerce_checkout_after_order_review' ), 95 );
	}

	/**
	 * `[woocommerce_checkout]` con los ganchos del widget, dentro de `.digi-co` (página o plantilla que lo contiene y,
	 * con «Animación al pasar el cursor» del botón de compra, su clase, para el script). En el editor se simula una
	 * persona sin sesión, como PRO, para que se vean todas las secciones.
	 */
	protected function render_widget() {
		$settings = $this->get_settings_for_display();
		$editor   = self::is_edit_mode();
		if ( $editor ) {
			$user = wp_get_current_user()->ID;
			wp_set_current_user( 0 );
		}
		$document = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ? \Elementor\Plugin::$instance->documents->get_current() : null;
		$page     = $document && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : (int) get_the_ID();
		$attrs    = ' data-digi-page="' . esc_attr( $page ) . '"';
		if ( $this->should_render_login() ) {
			// Acceso por la acción AJAX del módulo de WooCommerce del constructor, la misma que usaba PRO.
			$attrs .= ' data-digi-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"';
		}
		if ( ! empty( $settings['purchase_button_hover_animation'] ) ) {
			$attrs .= ' data-digi-animation="' . esc_attr( 'elementor-animation-' . $settings['purchase_button_hover_animation'] ) . '"';
		}
		$this->add_render_hooks();
		echo '<div class="digi-co"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados arriba.
		echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salida de WooCommerce.
		echo '</div>';
		$this->remove_render_hooks();
		if ( $editor ) {
			wp_set_current_user( $user );
		}
	}
}
