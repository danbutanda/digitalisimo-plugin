<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Background;
use ElementorPro\Modules\QueryControl\Module as QueryControlModule;
use Elementor\Core\Base\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Mi cuenta (`digitalisimo-woo-my-account`): sustituye al widget «My Account» de PRO
 * (`modules/woocommerce/widgets/my-account.php`, mapa `modules/digitalisimo-convert/maps/woocommerce-my-account.php`).
 * Repite todos sus controles con el mismo nombre, valor por defecto, condiciones y selectores (sobre las clases propias)
 * y pinta lo mismo: WooCommerce dibuja la cuenta con `[woocommerce_my_account]` y sus plantillas, con los mismos ganchos
 * y filtros que PRO (nombres de las pestañas, navegación horizontal, envoltura del contenido, plantilla propia del
 * escritorio, enlace permanente y salida hacia la página del widget). En el editor pinta todas las páginas de la cuenta y
 * el script del widget las alterna, como PRO.
 *
 * Clases propias: `digi-acc` (envoltura; `digi-acc--<punto>`, `digi-acc--custom` con plantilla en el escritorio y
 * `digi-acc-s` en el resto), `digi-acc__nav` (navegación horizontal) y `digi-acc-tabs-<disposición>` en el elemento.
 * Script propio sin jQuery (`store/assets/my-account.js`): iguala la altura de las direcciones y de los formularios de
 * acceso, junta la nota de compra con su producto y pone la animación de los botones, como PRO.
 */
class My_Account extends Base {

	public function get_name() {
		return 'digitalisimo-woo-my-account';
	}

	public function get_title() {
		return esc_html__( 'Mi cuenta', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-my-account';
	}

	public function get_keywords() {
		return [ 'woocommerce', 'tienda', 'cuenta', 'mi cuenta', 'pedidos', 'acceso', 'account', 'my account' ];
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	protected function register_controls() {
		$start = is_rtl() ? 'end' : 'start';
		$end = is_rtl() ? 'start' : 'end';

		$this->start_controls_section(
			'section_menu_icon_content',
			[
				'label' => esc_html__( 'Pestañas', 'pro-elements' ),
			]
		);

		$this->add_control(
			'tabs_layout',
			[
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'vertical' => esc_html__( 'Vertical', 'pro-elements' ),
					'horizontal' => esc_html__( 'Horizontal', 'pro-elements' ),
				],
				'default' => 'vertical',
				'render_type' => 'template',
				'prefix_class' => 'digi-acc-tabs-',
			]
		);

		$this->add_responsive_control(
			'tabs_content_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--da-tcs: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tabs_position',
			[
				'label' => esc_html__( 'Posición de las pestañas', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => "eicon-align-$start-h",
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-align-center-h',
					],
					'end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => "eicon-align-$end-h",
					],
					'stretch' => [
						'title' => esc_html__( 'Estirar', 'pro-elements' ),
						'icon' => 'eicon-align-stretch-h',
					],
				],
				'condition' => [
					'tabs_layout' => 'horizontal',
				],
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--da-tcjc: flex-start; --da-tw: auto',
					'center' => '--da-tcjc: center; --da-tw: auto',
					'end' => '--da-tcjc: flex-end; --da-tw: auto',
					'stretch' => '--da-tcjc: space-between; --da-tw: 100%',
				],
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'tab_name',
			[
				'label' => esc_html__( 'Nombre de la pestaña', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->add_control(
			'order_display_description',
			[
				'raw' => esc_html__( 'Nota: al editar la sección de pedidos sólo se muestra el último pedido. Los demás se ven en el sitio publicado o en la sección de pedidos de WooCommerce.', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
				'condition' => [
					'field_key' => 'orders',
				],
			]
		);

		$this->add_control(
			'tabs',
			[
				'label' => '',
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
						'field_key' => 'dashboard',
						'field_label' => esc_html__( 'Escritorio', 'pro-elements' ),
						'tab_name' => esc_html__( 'Escritorio', 'pro-elements' ),
					],
					[
						'field_key' => 'orders',
						'field_label' => esc_html__( 'Pedidos', 'pro-elements' ),
						'tab_name' => esc_html__( 'Pedidos', 'pro-elements' ),
					],
					[
						'field_key' => 'downloads',
						'field_label' => esc_html__( 'Descargas', 'pro-elements' ),
						'tab_name' => esc_html__( 'Descargas', 'pro-elements' ),
					],
					[
						'field_key' => 'edit-address',
						'field_label' => esc_html__( 'Direcciones', 'pro-elements' ),
						'tab_name' => esc_html__( 'Direcciones', 'pro-elements' ),
					],
					[
						'field_key' => 'edit-account',
						'field_label' => esc_html__( 'Detalles de la cuenta', 'pro-elements' ),
						'tab_name' => esc_html__( 'Detalles de la cuenta', 'pro-elements' ),
					],
					[
						'field_key' => 'customer-logout',
						'field_label' => esc_html__( 'Cerrar sesión', 'pro-elements' ),
						'tab_name' => esc_html__( 'Cerrar sesión', 'pro-elements' ),
					],
				],
				'title_field' => '{{{ tab_name }}}',
			]
		);

		$this->add_responsive_control(
			'tabs_alignment',
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
					'{{WRAPPER}}' => '--da-ta: {{VALUE}};',
				],
				'conditions' => [
					'relation' => 'and',
					'terms' => [
						[
							'name' => 'tabs_position',
							'operator' => '!==',
							'value' => 'start',
						],
						[
							'name' => 'tabs_position',
							'operator' => '!==',
							'value' => 'center',
						],
						[
							'name' => 'tabs_position',
							'operator' => '!==',
							'value' => 'end',
						],
					],
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
			'customize_dashboard_check',
			[
				'label' => esc_html__( 'Personalizar el escritorio', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'frontend_available' => true,
				'render_type' => 'template',
			]
		);

		$this->add_control(
			'customize_dashboard_description',
			[
				'raw' => sprintf(
					/* translators: 1: Saved templates link opening tag. 2: Link closing tag. */
					esc_html__( 'Sustituye la pantalla de escritorio de la cuenta de WooCommerce por una plantilla. (¿No tienes una? Ve a %1$sPlantillas guardadas%2$s.)', 'pro-elements' ),
					sprintf( '<a href="%s" target="_blank">', admin_url( 'edit.php?post_type=elementor_library&tabs_group=library#add_new' ) ),
					'</a>'
				),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-control-field-description elementor-descriptor elementor-descriptor-subtle',
				'condition' => [
					'customize_dashboard_check' => 'yes',
				],
			]
		);

		$this->add_control(
			'customize_dashboard_select_heading',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Elegir plantilla', 'pro-elements' ),
				'condition' => [
					'customize_dashboard_check' => 'yes',
				],
			]
		);

		$document_types = \Elementor\Plugin::$instance->documents->get_document_types( [
			'show_in_library' => true,
		] );

		$this->add_control(
			'customize_dashboard_select',
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
				'condition' => [
					'customize_dashboard_check' => 'yes',
				],
				'render_type' => 'template',
			]
		);

		$this->add_control(
			'edit_button',
			[
				'raw' => sprintf( '<a href="#" target="_blank" class="elementor-button elementor-edit-template" style="margin-top:0px;"><i class="eicon-pencil" style="margin-left:10px;"></i>%s</a>', esc_html__( 'Editar plantilla', 'pro-elements' ) ),
				'type' => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-edit-template-wrapper',
				'condition' => [
					'customize_dashboard_check' => 'yes',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'tabs_style',
			[
				'label' => esc_html__( 'Pestañas', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'tabs_typography',
				'selector' => '{{WRAPPER}} .digi-acc .woocommerce .woocommerce-MyAccount-navigation ul li a',
			]
		);

		$this->start_controls_tabs( 'tabs_section' );

		$this->start_controls_tab( 'tabs_normal', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'tabs_normal_background',
				'selector' => '{{WRAPPER}} .digi-acc .woocommerce .woocommerce-MyAccount-navigation ul li:not(.is-active) a',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'tabs_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-acc .woocommerce .woocommerce-MyAccount-navigation ul li:not(.is-active) a',
			]
		);

		$this->add_control(
			'tabs_normal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tnc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'tabs_hover', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'tabs_hover_background',
				'selector' => '{{WRAPPER}} .digi-acc .woocommerce .woocommerce-MyAccount-navigation ul li a:hover',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'tabs_hover_box_shadow',
				'selector' => '{{WRAPPER}} .digi-acc .woocommerce .woocommerce-MyAccount-navigation ul li a:hover',
			]
		);

		$this->add_control(
			'tabs_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-thc: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tabs_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-thbc: {{VALUE}}',
				],
				'condition' => [
					'tabs_border_type!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'tabs_active', [ 'label' => esc_html__( 'Activa', 'pro-elements' ) ] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'tabs_active_background',
				'selector' => '{{WRAPPER}} .digi-acc .woocommerce .woocommerce-MyAccount-navigation ul li.is-active a',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'tabs_active_box_shadow',
				'selector' => '{{WRAPPER}} .woocommerce-MyAccount-navigation ul li.is-active a',
			]
		);

		$this->add_control(
			'tabs_active_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tac: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tabs_active_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tabc: {{VALUE}}',
				],
				'condition' => [
					'tabs_border_type!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'tabs_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--da-tbt: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'tabs_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-acc .woocommerce .woocommerce-MyAccount-navigation ul li a' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'tabs_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'tabs_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tbc: {{VALUE}};',
				],
				'condition' => [
					'tabs_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'tabs_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--da-tbr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'tabs_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--da-tp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'tabs_spacing',
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
					'{{WRAPPER}}' => '--da-ts: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tabs_divider_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Divisores', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tabs_divider_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tdc: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'tabs_divider_weight',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--da-tdw: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'sections_title',
			[
				'label' => esc_html__( 'Secciones', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'my_account_sections_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-sbc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'my_account_sections_box_shadow',
				'selector' => '{{WRAPPER}} .digi-acc--dashboard:not(.digi-acc--custom) .digi-acc__c, {{WRAPPER}} .digi-acc--orders .digi-acc__c, {{WRAPPER}} .digi-acc--downloads .digi-acc__c, {{WRAPPER}} address, {{WRAPPER}} .digi-acc--edit-account .digi-acc__c, {{WRAPPER}} .digi-acc--view-order .order_details, {{WRAPPER}} .woocommerce-form-login, {{WRAPPER}} .woocommerce-form-register, {{WRAPPER}} .woocommerce-ResetPassword, {{WRAPPER}} .digi-acc--payment-methods .digi-acc__c',
			]
		);

		$this->add_control(
			'sections_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--da-sbt: {{VALUE}};',
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
					'{{WRAPPER}}' => '--da-sbtw: {{TOP}}{{UNIT}}; --da-sbrw: {{RIGHT}}{{UNIT}}; --da-sbbw: {{BOTTOM}}{{UNIT}}; --da-sblw: {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--da-sbc2: {{VALUE}};',
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
					'{{WRAPPER}}' => '--da-sbr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--da-sp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; --da-elmt: {{TOP}}{{UNIT}}; --da-elms: {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .digi-acc--edit-address .woocommerce-Address address' => 'padding-top: calc( {{TOP}}{{UNIT}} + 40px );',
					'{{WRAPPER}} .woocommerce-pagination' => 'padding-bottom: {{BOTTOM}}{{UNIT}};',
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
			'typography_titles',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos de sección', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'typography_section_titles_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tstc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'section_titles_typography',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) h2, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) h3',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'section_titles_typography_text_shadow',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) h2, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) h3',
			]
		);

		$this->add_responsive_control(
			'section_title_spacing',
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
					'{{WRAPPER}}' => '--da-sts: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'typography_secondary_titles',
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
					'{{WRAPPER}}' => '--da-gtc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'general_text_typography',
				'selector' => '{{WRAPPER}} .woocommerce-MyAccount-content > div > p, {{WRAPPER}} address, {{WRAPPER}} .woocommerce-EditAccountForm fieldset legend, {{WRAPPER}} .woocommerce-ResetPassword p:nth-child(1), {{WRAPPER}} .woocommerce-OrderUpdate',
			]
		);

		$this->add_control(
			'typography_login_messages_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Mensajes de acceso', 'pro-elements' ),
			]
		);

		$this->add_control(
			'login_messages_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-lmc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'login_messages_typography',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .register p:not([class]), {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce em',
			]
		);

		$this->add_control(
			'checkboxes_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Casillas', 'pro-elements' ),
			]
		);

		$this->add_control(
			'checkboxes_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-cc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'checkboxes_typography',
				'selector' => '{{WRAPPER}} .woocommerce-form__label-for-checkbox span',
			]
		);

		$this->add_control(
			'payment_methods_radio_buttons_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botones de opción', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'payment_methods_radio_buttons_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-pmrbc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'payment_methods_radio_buttons_typography',
				'selector' => '{{WRAPPER}} .woocommerce-PaymentMethod .input-radio + label',
			]
		);

		$this->add_control(
			'links_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlaces', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->start_controls_tabs( 'links_colors' );

		$this->start_controls_tab( 'links_normal_colors', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'links_normal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-lnc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'links_hover_colors', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'links_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-lhc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'forms_section',
			[
				'label' => esc_html__( 'Formularios', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'forms_columns_gap',
			[
				'label' => esc_html__( 'Separación entre columnas', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--da-fcgpr: calc( {{SIZE}}{{UNIT}}/2 ); --da-fcgpl: calc( {{SIZE}}{{UNIT}}/2 ); --da-fcgml: calc( -{{SIZE}}{{UNIT}}/2 ); --da-fcgmr: calc( -{{SIZE}}{{UNIT}}/2 );',
				],
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
					'{{WRAPPER}}' => '--da-frg: {{SIZE}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--da-flc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'forms_label_typography',
				'selector' => '{{WRAPPER}} .woocommerce-form-row label, {{WRAPPER}} .woocommerce-address-fields label',
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
					'{{WRAPPER}}' => '--da-fls: {{SIZE}}{{UNIT}};',
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
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row .input-text, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row select, {{WRAPPER}} ::placeholder, {{WRAPPER}} .select2-container--default .select2-selection--single, .select2-results__option, {{WRAPPER}} .digi-acc--payment-methods input[type=text]',

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
					'{{WRAPPER}}' => '--da-ffnc: {{VALUE}};',
					'.e-woo-select2-wrapper .select2-results__option' => 'color: {{VALUE}};',
					// style select2 arrow
					'{{WRAPPER}} .select2-container--default .select2-selection--single .select2-selection__arrow b' => 'border-color: {{VALUE}} transparent transparent transparent;',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_fields_normal_background',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row .input-text, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row select, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default .select2-selection--single, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default, .select2-results__option, {{WRAPPER}} .digi-acc--payment-methods input[type=text]',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_fields_normal_box_shadow',
				'selector' => '{{WRAPPER}} .input-text, {{WRAPPER}} select, {{WRAPPER}} .select2-container--default .select2-selection--single, {{WRAPPER}} .digi-acc--payment-methods input[type=text]',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'forms_fields_focus_styles', [ 'label' => esc_html__( 'Foco', 'pro-elements' ) ] );

		$this->add_control(
			'forms_fields_focus_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-fffc: {{VALUE}}',
					'.e-woo-select2-wrapper .select2-results__option:focus' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_fields_focus_background',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row .input-text:focus, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row select:focus, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default.select2-container--focus .select2-selection--single, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default.select2-container--focus, {{WRAPPER}} .digi-acc--payment-methods input[type=text]:focus',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_fields_focus_box_shadow',
				'selector' => '{{WRAPPER}} .input-text:focus, {{WRAPPER}} select:focus, {{WRAPPER}} .select2-container--default .select2-selection--single:focus, {{WRAPPER}} .digi-acc--payment-methods input[type=text]:focus',
			]
		);

		$this->add_control(
			'forms_fields_focus_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row .input-text:focus, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row select:focus, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default.select2-container--focus, .select2-results__option:focus, {{WRAPPER}} .digi-acc--payment-methods input[type=text]:focus' => 'border-color: {{VALUE}}',
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
					'{{WRAPPER}}' => '--da-ffftd: {{SIZE}}ms',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'forms_fields_border',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row .input-text, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .form-row select, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default, {{WRAPPER}} .digi-acc--payment-methods input[type=text]',
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
					'{{WRAPPER}}' => '--da-ffbr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--da-ffp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					// style select2
					'{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default .select2-selection--single .select2-selection__rendered' => 'line-height: calc( ({{TOP}}{{UNIT}}*2) + 16px ); padding-left: {{LEFT}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}};',
					'{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default .select2-selection--single .select2-selection__arrow' => 'height: calc( ({{TOP}}{{UNIT}}*2) + 16px ); right: {{RIGHT}}{{UNIT}};',
					'{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .select2-container--default .select2-selection--single' => 'height: auto;',
				],
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
				'selector' => '{{WRAPPER}} button.button, {{WRAPPER}} #add_payment_method #payment #place_order',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'forms_button_text_shadow',
				'selector' => '{{WRAPPER}} button.button, {{WRAPPER}} #add_payment_method #payment #place_order',
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
					'{{WRAPPER}}' => '--da-fbntc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_buttons_background',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-EditAccountForm .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-address-fields .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .login .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .register .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .woocommerce-ResetPassword .button, {{WRAPPER}} #add_payment_method #payment #place_order',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_buttons_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-EditAccountForm .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-address-fields .button, {{WRAPPER}} button.button, {{WRAPPER}} #add_payment_method #payment #place_order',
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
					'{{WRAPPER}}' => '--da-fbhtc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'forms_buttons_hover_background',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-EditAccountForm .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-address-fields .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .login .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .register .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .woocommerce-ResetPassword .button:hover, {{WRAPPER}} #add_payment_method #payment #place_order:hover',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'forms_buttons_focus_box_shadow',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-EditAccountForm .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-address-fields .button:hover, {{WRAPPER}} button.button:hover, {{WRAPPER}} #add_payment_method #payment #place_order:hover',
			]
		);

		$this->add_control(
			'forms_buttons_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-EditAccountForm .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-address-fields .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .login .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .register .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .woocommerce-ResetPassword .button:hover, {{WRAPPER}} #add_payment_method #payment #place_order:hover' => 'border-color: {{VALUE}}',
				],
				'condition' => [
					'forms_buttons_border_border!' => '',
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
					'{{WRAPPER}}' => '--da-fbhtd: {{SIZE}}ms',
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

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'forms_buttons_border',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-EditAccountForm .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-address-fields .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .login .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .register .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .woocommerce-ResetPassword .button, {{WRAPPER}} #add_payment_method #payment #place_order',
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'forms_buttons_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--da-fbbr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}}' => '--da-fbp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'tables_section',
			[
				'label' => esc_html__( 'Detalles del pedido', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'tables_rows_gap',
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
					'{{WRAPPER}}' => '--da-osrgt: calc( {{SIZE}}{{UNIT}}/2 ); --da-osrgb: calc( {{SIZE}}{{UNIT}}/2 );',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'tables_titles',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Títulos y totales', 'pro-elements' ),
			]
		);

		$this->add_control(
			'tables_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-ttc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'tables_titles_typography',
				'selector' => '{{WRAPPER}} .order_details thead th, {{WRAPPER}} .order_details tfoot td, {{WRAPPER}} .order_details tfoot th, {{WRAPPER}} .nobr',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'tables_titles_text_shadow',
				'selector' => '{{WRAPPER}} .order_details thead th, {{WRAPPER}} .order_details tfoot td, {{WRAPPER}} .order_details tfoot th, {{WRAPPER}} .nobr',
			]
		);

		$this->add_control(
			'tables_items_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Elementos', 'pro-elements' ),
			]
		);

		$this->add_control(
			'tables_items_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tic: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'tables_items_typography',
				'selector' => '{{WRAPPER}} .digi-acc--orders tbody td, {{WRAPPER}} .digi-acc--downloads tbody td, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .product-quantity, {{WRAPPER}} .woocommerce-table--order-downloads tbody td, {{WRAPPER}} .woocommerce-table--order-details td a, {{WRAPPER}} td.product-total, {{WRAPPER}} td.payment-method-method, {{WRAPPER}} td.payment-method-expires',
			]
		);

		$this->add_control(
			'variations_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Variaciones', 'pro-elements' ),
			]
		);

		$this->add_control(
			'variations_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-vc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'variations_typography',
				'selector' => '{{WRAPPER}} .wc-item-meta',
			]
		);

		$this->add_control(
			'sections_links_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Enlace del producto', 'pro-elements' ),
			]
		);

		$this->start_controls_tabs( 'tables_links_colors' );

		$this->start_controls_tab( 'tables_links_normal_colors', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'tables_links_normal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tlnc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'tables_links_hover_colors', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'tables_links_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tlhc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'tables_divider_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Divisores', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tables_divider_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--da-tdbt: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'tables_divider_border_width',
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
					'{{WRAPPER}}' => '--da-tdbw: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'tables_divider_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'tables_divider_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tdbc: {{VALUE}};',
				],
				'condition' => [
					'tables_divider_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'tables_button_title',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botones', 'pro-elements' ),
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'tables_button_typography',
				'selector' => '{{WRAPPER}} .shop_table .button, {{WRAPPER}} .order-again .button, {{WRAPPER}} .woocommerce-pagination .button, {{WRAPPER}} .digi-acc--payment-methods .digi-acc__c .button',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'tables_button_text_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button, {{WRAPPER}} .order-again .button, {{WRAPPER}} .woocommerce-pagination .button, {{WRAPPER}} .digi-acc--payment-methods .digi-acc__c .button',
			]
		);

		$this->start_controls_tabs( 'tables_button_styles' );

		$this->start_controls_tab( 'tables_button_styles_normal', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'tables_button_normal_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--da-tbntc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'tables_button_normal_background',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .shop_table .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .order-again .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .woocommerce-pagination .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom).digi-acc--payment-methods .digi-acc__c .button',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'tables_button_normal_box_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button, {{WRAPPER}} .order-again .button, {{WRAPPER}} .woocommerce-pagination .button, {{WRAPPER}} .digi-acc--payment-methods .digi-acc__c .button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'tables_button_styles_hover', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'tables_button_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .shop_table .button:hover, {{WRAPPER}} .woocommerce-pagination .button:hover, {{WRAPPER}} .order-again .button:hover, {{WRAPPER}} .digi-acc--payment-methods .woocommerce .digi-acc__c .button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'tables_button_hover_background',
				'selector' => '{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .shop_table .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .order-again .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .woocommerce-pagination .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom).digi-acc--payment-methods .digi-acc__c .button:hover',
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'tables_button_hover_box_shadow',
				'selector' => '{{WRAPPER}} .shop_table .button:hover, {{WRAPPER}} .order-again .button:hover, {{WRAPPER}} .woocommerce-pagination .button:hover, {{WRAPPER}} .digi-acc--payment-methods .digi-acc__c .button:hover',
			]
		);

		$this->add_control(
			'tables_button_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .shop_table .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .order-again .button:hover, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce-pagination .button:hover, {{WRAPPER}} .digi-acc--payment-methods:not(.digi-acc--custom) .digi-acc__c .button:hover' => 'border-color: {{VALUE}}',
				],
				'condition' => [
					'tables_button_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'tables_button_hover_transition_duration',
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
					'{{WRAPPER}}' => '--da-tbhtd: {{SIZE}}ms',
				],
			]
		);

		$this->add_control(
			'tables_button_hover_animation',
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
			'tables_button_border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_border_type_options(),
				'selectors' => [
					'{{WRAPPER}}' => '--da-tbbt: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'tables_button_border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .shop_table .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .order-again .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom) .woocommerce .woocommerce-pagination .button, {{WRAPPER}} .digi-acc:not(.digi-acc--custom).digi-acc--payment-methods .digi-acc__c .button' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'tables_button_border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'tables_button_border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} ' => '--da-tbbc: {{VALUE}};',
				],
				'condition' => [
					'tables_button_border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'tables_button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--da-tbbr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'tables_button_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--da-tbp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	public function modify_menu_items( $items, $endpoints ) {
		$settings = $this->get_settings_for_display();

		if ( ! empty( $settings['tabs'] ) ) {
			foreach ( $settings['tabs'] as $tab ) {
				if ( isset( $tab['tab_name'] ) && isset( $items[ $tab['field_key'] ] ) ) {
					$items[ $tab['field_key'] ] = $tab['tab_name'];
				}
			}
		}

		return $items;
	}

	/**
	 * WooCommerce Get My Account Page Permalink
	 *
	 * Modify the permalinks of the My Account menu items. By default the permalinks will go to the
	 * set WooCommerce My Account Page, even if the widget is on a different page. This function will override
	 * the permalinks to use the widget page URL as the base URL instead.
	 *
	 * This is a callback function for the woocommerce_get_myaccount_page_permalink filter.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function woocommerce_get_myaccount_page_permalink( $bool ) {
		return get_permalink();
	}

	/**
	 * WooCommerce Logout Default Redirect URL
	 *
	 * Modify the permalink of the My Account Logout menu item. We add this so that we can add custom
	 * parameters to the URL, which we can later access to log the user out and redirect back to the widget
	 * page. Without this WooCommerce would have always just redirect back to the set My Account Page
	 * after log out.
	 *
	 * This is a callback function for the woocommerce_logout_default_redirect_url filter.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function woocommerce_logout_default_redirect_url( $redirect ) {
		return $redirect . '?elementor_wc_logout=true&elementor_my_account_redirect=' . esc_url( get_permalink() );
	}

	protected function render_widget() {
		// Add actions & filters before displaying our Widget.
		add_action( 'woocommerce_account_navigation', [ $this, 'woocommerce_account_navigation' ], 1 );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'modify_menu_items' ], 10, 2 );
		add_action( 'woocommerce_account_content', [ $this, 'before_account_content' ], 2 );
		add_action( 'woocommerce_account_content', [ $this, 'after_account_content' ], 95 );
		add_filter( 'woocommerce_get_myaccount_page_permalink', [ $this, 'woocommerce_get_myaccount_page_permalink' ], 10, 1 );
		add_filter( 'woocommerce_logout_default_redirect_url', [ $this, 'woocommerce_logout_default_redirect_url' ], 10, 1 );
		add_filter( 'woocommerce_is_account_page', '__return_true' );

		if ( $this->has_custom_template() && 'dashboard' === $this->get_current_endpoint() ) {
			remove_action( 'woocommerce_account_content', 'woocommerce_account_content', 10 );
			add_action( 'woocommerce_account_content', [ $this, 'display_custom_template' ], 10 );
		}

		// Display our Widget.
		if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$this->render_html_front_end();
		} else {
			$this->render_html_editor();
		}

		// Remove actions & filters after displaying our Widget.
		remove_action( 'woocommerce_account_navigation', [ $this, 'woocommerce_account_navigation' ], 1 );
		remove_filter( 'woocommerce_account_menu_items', [ $this, 'modify_menu_items' ], 10 );
		remove_action( 'woocommerce_account_content', [ $this, 'before_account_content' ], 2 );
		remove_action( 'woocommerce_account_content', [ $this, 'after_account_content' ], 95 );
		remove_filter( 'woocommerce_get_myaccount_page_permalink', [ $this, 'woocommerce_get_myaccount_page_permalink' ], 10, 1 );
		remove_filter( 'woocommerce_logout_default_redirect_url', [ $this, 'woocommerce_logout_default_redirect_url' ], 10, 1 );
		remove_filter( 'woocommerce_is_account_page', '__return_true' );

		if ( $this->has_custom_template() && 'dashboard' === $this->get_current_endpoint() ) {
			remove_action( 'woocommerce_account_content', [ $this, 'display_custom_template' ], 10 );
			add_action( 'woocommerce_account_content', 'woocommerce_account_content', 10 );
		}
	}

	/**
	 * Get Account Pages
	 *
	 * Get all the pages that would render on the My Account page.
	 * We will use this array to be able to render all these pages' content when the editor loads.
	 * We will then switch between the pages via JS as all the content is already on the page.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	private function get_account_pages() {
		$pages = [
			'dashboard' => '',
			'orders' => '',
			'downloads' => '',
			'edit-address' => '',
		];

		// Check if payment gateways support add new payment methods.
		$support_payment_methods = false;
		foreach ( WC()->payment_gateways->get_available_payment_gateways() as $gateway ) {
			if ( $gateway->supports( 'add_payment_method' ) || $gateway->supports( 'tokenization' ) ) {
				$support_payment_methods = true;
				break;
			}
		}

		if ( $support_payment_methods ) {
			$pages['payment-methods'] = '';
			$pages['add-payment-method'] = '';
		}

		// Edit account.
		$pages['edit-account'] = '';

		// Get the latest order (if there is one) for view-order (order preview) page.
		$recent_order = wc_get_orders( [
			'limit' => 1,
			'orderby'  => 'date',
			'order'    => 'DESC',
		] );

		if ( ! empty( $recent_order ) ) {
			$pages['view-order'] = $recent_order[0]->get_id();
		}

		return $pages;
	}

	/**
	 * Get Current Endpoint
	 *
	 * Used to determine which page Account Page the user is on currently.
	 * This is used so we can add a unique wrapper class around the page's content.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	private function get_current_endpoint() {
		global $wp_query;
		$current = '';

		$pages = $this->get_account_pages();

		foreach ( $pages as $page => $val ) {
			if ( isset( $wp_query->query[ $page ] ) ) {
				$current = $page;
				break;
			}
		}

		if ( '' === $current && isset( $wp_query->query_vars['page'] ) ) {
			$current = 'dashboard'; // Dashboard is not an endpoint so it needs a custom check.
		}

		return $current;
	}

	/**
	 * Render HTML Front End
	 *
	 * This function will output the content on the front-end.
	 *
	 * @since 3.5.0
	 */
	private function render_html_front_end() {
		$current_endpoint = $this->get_current_endpoint();
		$custom           = 'dashboard' === $current_endpoint && $this->has_custom_template() && is_user_logged_in();
		echo '<div class="' . esc_attr( $this->wrapper_classes( $current_endpoint, $custom ) ) . '">';
		echo do_shortcode( '[woocommerce_my_account]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salida de WooCommerce.
		echo '</div>';
	}

	/**
	 * Clases de la envoltura (`e-my-account-tab e-my-account-tab__<punto> [e-my-account-tab__dashboard--custom]` en PRO):
	 * `digi-acc digi-acc--<punto>` y `digi-acc--custom` con plantilla propia en el escritorio o `digi-acc-s` sin ella.
	 */
	private function wrapper_classes( $endpoint, $custom ) {
		return 'digi-acc digi-acc--' . sanitize_html_class( $endpoint ) . ( $custom ? ' digi-acc--custom' : ' digi-acc-s' );
	}

	/**
	 * Render HTML Editor
	 *
	 * This function will output the content in the Editor.
	 * One navigation will be rendered and the content for all pages will be rendered.
	 * Only the dashboard page's content will show on page load as the other pages' content
	 * will be hidden with CSS and toggled via JS when the user clicks on the menu items.
	 *
	 * @since 3.5.0
	 */
	private function render_html_editor() {
		$settings = $this->get_settings_for_display();
		// Add .digi-acc--dashboard as the default class when the editor loads.
		// This class will be replaced with JS when tabs are switched.

		echo '<div class="' . esc_attr( $this->wrapper_classes( 'dashboard', $this->has_custom_template() && is_user_logged_in() ) ) . '">';
		?>
			<div class="woocommerce">
			<?php
			if ( 'horizontal' === $settings['tabs_layout'] ) {
				?>
				<div class="digi-acc__nav">
					<?php wc_get_template( 'myaccount/navigation.php' ); ?>
				</div>
				<?php
			} else {
				wc_get_template( 'myaccount/navigation.php' );
			}

			// In the editor, output all the tabs in order to allow for switching between them via JS.
			$pages = $this->get_account_pages();

			global $wp_query;
			foreach ( $pages as $page => $page_value ) {
				foreach ( $pages as $unset_tab => $unset_tab_value ) {
					unset( $wp_query->query_vars[ $unset_tab ] );
				}
				$wp_query->query_vars[ $page ] = $page_value;

				$wrapper_class = $this->get_account_content_wrapper( [
					'context' => 'editor',
					'page' => $page,
				] );
				?>
				<div class="woocommerce-MyAccount-content" <?php echo $page ? 'data-digi-page="' . esc_attr( $page ) . '"' : ''; ?>>
					<div class="<?php echo esc_attr( $wrapper_class ); ?>">
						<?php
						if ( 'dashboard' === $page ) {
							if ( ! $this->has_custom_template() ) {
								wc_get_template(
									'myaccount/dashboard.php',
									[
										'current_user' => get_user_by( 'id', get_current_user_id() ),
									]
								);
							} else {
								$this->display_custom_template();
							}
						} else {
							do_action( 'woocommerce_account_' . $page . '_endpoint', $page_value );
						}
						?>
					</div>
				</div>
			<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Woocommerce Account Navigation
	 *
	 * Output a horizontal menu if the setting was selected. The default vertical menu will be hidden with CSS
	 * and this menu will show. We wrap this menu with a class '.digi-acc__nav' so that we
	 * can manipulate the display for this menu with CSS (make it horizontal).
	 *
	 * Callback function for the woocommerce_account_navigation hook.
	 *
	 * This eliminates the need for template overrides.
	 *
	 * @since 3.5.0
	 */
	public function woocommerce_account_navigation() {
		$settings = $this->get_settings_for_display();

		if ( 'horizontal' === $settings['tabs_layout'] ) {
			?>
			<div class="digi-acc__nav">
				<?php wc_get_template( 'myaccount/navigation.php' ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Check if the My Account dashboard intro content is replaced with a custom template
	 *
	 * Conditions:
	 * 1. Customize Dashboard = Show
	 * 2. A Template ID has been set
	 *
	 * @since 3.7.0
	 *
	 * @return boolean
	 */
	public function has_custom_template() {
		$template_id = intval( $this->get_dashboard_template_id() );

		return 0 < $template_id;
	}

	/**
	 * Get Account Content Wrapper
	 *
	 * This function will determine the wrapper class around the main content.
	 * There are different wrappers depending on the following scenarios:
	 * 1. Are there orders/downloads or not.
	 * 2. A custom template been selected for the dashboard intro or not
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	private function get_account_content_wrapper( $args ) {
		$user_id       = get_current_user_id();
		$num_orders    = wc_get_customer_order_count( $user_id );
		$num_downloads = count( wc_get_customer_available_downloads( $user_id ) );
		$empty         = false;

		/* we need to render a different css class if there are no orders/downloads to display
		 * as the no orders/downloads screen should not have the default padding and border
		 * around it but show the 'no orders/downloads' notification only
		 */
		if ( 'frontend' === $args['context'] ) { // Front-end display
			global $wp_query;
			$page  = $this->get_current_endpoint();
			$empty = ( 0 === $num_orders && isset( $wp_query->query_vars['orders'] ) ) || ( 0 === $num_downloads && isset( $wp_query->query_vars['downloads'] ) );
		} else { // Editor display
			$page  = $args['page'];
			$empty = ( 0 === $num_orders && 'orders' === $page ) || ( 0 === $num_downloads && 'downloads' === $page );
		}

		// `woocommerce-MyAccount-content-wrapper` en PRO (con `-no-data` sin pedidos o descargas). `digi-acc__box` marca
		// las páginas cuyo contenido va en una caja con borde (escritorio sin plantilla propia, pedidos, descargas, datos
		// de la cuenta y métodos de pago), como las reglas de PRO por punto de la cuenta.
		if ( $empty ) {
			return 'digi-acc__c--empty';
		}
		$box = in_array( $page, array( 'orders', 'downloads', 'edit-account', 'payment-methods' ), true ) || ( 'dashboard' === $page && ! ( $this->has_custom_template() && is_user_logged_in() ) );
		return 'digi-acc__c' . ( $box ? ' digi-acc__box' : '' );
	}

	/**
	 * Before Account Content
	 *
	 * Output containing elements. Callback function for the woocommerce_account_content hook.
	 *
	 * This eliminates the need for template overrides.
	 *
	 * @since 3.5.0
	 */
	public function before_account_content() {
		$wrapper_class = $this->get_account_content_wrapper( [ 'context' => 'frontend' ] );

		echo '<div class="' . esc_attr( $wrapper_class ) . '">';
	}

	/**
	 * Get Dashboard Template ID
	 *
	 * Get the template_id for the dashboard intro section if a custom template should be displayed
	 *
	 * @since 3.7.0
	 *
	 * @return int
	 */
	public function get_dashboard_template_id() {
		$settings = $this->get_settings_for_display();
		if ( 'yes' === $settings['customize_dashboard_check'] ) {
			$template_id = intval( $settings['customize_dashboard_select'] );
		} else {
			$template_id = 0;
		}

		return $template_id;
	}

	/**
	 * Display a custom template inside the My Account dashboard section
	 *
	 * @since 3.7.0
	 */
	public function display_custom_template() {
		$template_id = intval( $this->get_dashboard_template_id() );

		if ( 0 < $template_id ) {
			echo do_shortcode( '[elementor-template id="' . $template_id . '"]' );

			do_action( 'woocommerce_account_dashboard' );
			do_action( 'woocommerce_before_my_account' );
			do_action( 'woocommerce_after_my_account' );
		}
	}

	/**
	 * After Account Content
	 *
	 * Output containing elements. Callback function for the woocommerce_account_content hook.
	 *
	 * This eliminates the need for template overrides.
	 *
	 * @since 3.5.0
	 */
	public function after_account_content() {
		echo '</div>';
	}

	public function get_group_name() {
		return 'woocommerce';
	}
}
