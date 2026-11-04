<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Cards;
use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Tabla de precios (`digitalisimo-price-table`): sustituye al widget «Price Table» de PRO
 * (`modules/pricing/widgets/price-table.php`, mapa `modules/digitalisimo-convert/maps/price-table.php`). Tiene un control
 * por cada control de aquél (también los del repetidor de características), con el mismo nombre, los mismos valores por
 * defecto y las mismas condiciones; el marcado, las clases y la hoja (`assets/css/price-table.css`, sin JavaScript) son
 * propios. Controles derivados de PRO Elements (GPL); textos en español con el dominio `pro-elements`.
 *
 * - Cabecera (título y subtítulo), precio (símbolo antes o después, parte entera y decimal según el formato, precio
 *   original tachado en oferta y periodo debajo o al lado), características con su icono, pie con el botón y el texto
 *   adicional, y la cinta común de las tarjetas (`Cards::ribbon()`).
 * - El precio se anuncia completo («$39.99 Al mes»; en oferta, «Antes $59, ahora …») con un texto sólo para lectores
 *   de pantalla (`elementor-screen-only`, del núcleo), porque sus partes visibles van en etiquetas separadas que se leían
 *   sueltas («$ 39 99»); las partes visibles quedan ocultas a los lectores (`aria-hidden`). El precio original va en `del`.
 * - Los símbolos de moneda se imprimen como caracteres (PRO usaba entidades HTML; la del euro era la de Windows-1252).
 * - Sin plantilla JS del editor: el editor pinta lo mismo que el servidor.
 */
class Price_Table extends \Elementor\Widget_Base {
	/** Símbolos de moneda de PRO, como caracteres. */
	const SYMBOLS = array(
		'dollar'       => '$',
		'euro'         => '€',
		'franc'        => '₣',
		'pound'        => '£',
		'ruble'        => '₽',
		'shekel'       => '₪',
		'baht'         => '฿',
		'yen'          => '¥',
		'won'          => '₩',
		'guilder'      => 'ƒ',
		'peso'         => '₱',
		'peseta'       => '₧',
		'lira'         => '₤',
		'rupee'        => '₨',
		'indian_rupee' => '₹',
		'real'         => 'R$',
		'krona'        => 'kr',
	);

	public function get_name() {
		return 'digitalisimo-price-table';
	}

	public function get_title() {
		return esc_html__( 'Tabla de precios', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-price-table';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'tabla de precios', 'price table', 'precios', 'plan', 'tarifa', 'producto', 'botón', 'cinta' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	/** Como PRO: sin envoltorio interior con el marcado optimizado del núcleo. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_header',
			array(
				'label' => esc_html__( 'Cabecera', 'pro-elements' ),
			)
		);

		$this->add_control(
			'heading',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Escribe el título', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'sub_heading',
			array(
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Escribe la descripción', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'heading_tag',
			array(
				'label' => esc_html__( 'Etiqueta HTML del título', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				),
				'default' => 'h3',
				'condition' => array(
					'heading!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_pricing',
			array(
				'label' => esc_html__( 'Precio', 'pro-elements' ),
			)
		);

		$this->add_control(
			'currency_symbol',
			array(
				'label' => esc_html__( 'Símbolo de la moneda', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'' => esc_html__( 'Ninguno', 'pro-elements' ),
					'dollar' => '&#36; ' . _x( 'Dólar', 'Currency', 'pro-elements' ),
					'euro' => '&#8364; ' . _x( 'Euro', 'Currency', 'pro-elements' ),
					'baht' => '&#3647; ' . _x( 'Baht', 'Currency', 'pro-elements' ),
					'franc' => '&#8355; ' . _x( 'Franco', 'Currency', 'pro-elements' ),
					'guilder' => '&fnof; ' . _x( 'Florín', 'Currency', 'pro-elements' ),
					'krona' => 'kr ' . _x( 'Corona', 'Currency', 'pro-elements' ),
					'lira' => '&#8356; ' . _x( 'Lira', 'Currency', 'pro-elements' ),
					'peseta' => '&#8359 ' . _x( 'Peseta', 'Currency', 'pro-elements' ),
					'peso' => '&#8369; ' . _x( 'Peso', 'Currency', 'pro-elements' ),
					'pound' => '&#163; ' . _x( 'Libra esterlina', 'Currency', 'pro-elements' ),
					'real' => 'R$ ' . _x( 'Real', 'Currency', 'pro-elements' ),
					'ruble' => '&#8381; ' . _x( 'Rublo', 'Currency', 'pro-elements' ),
					'rupee' => '&#8360; ' . _x( 'Rupia', 'Currency', 'pro-elements' ),
					'indian_rupee' => '&#8377; ' . _x( 'Rupia (india)', 'Currency', 'pro-elements' ),
					'shekel' => '&#8362; ' . _x( 'Séquel', 'Currency', 'pro-elements' ),
					'yen' => '&#165; ' . _x( 'Yen/Yuan', 'Currency', 'pro-elements' ),
					'won' => '&#8361; ' . _x( 'Won', 'Currency', 'pro-elements' ),
					'custom' => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'default' => 'dollar',
			)
		);

		$this->add_control(
			'currency_symbol_custom',
			array(
				'label' => esc_html__( 'Símbolo personalizado', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'ai' => array(
					'active' => false,
				),
				'condition' => array(
					'currency_symbol' => 'custom',
				),
			)
		);

		$this->add_control(
			'price',
			array(
				'label' => esc_html__( 'Precio', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => '39.99',
				'dynamic' => array(
					'active' => true,
				),
				'ai' => array(
					'active' => false,
				),
			)
		);

		$this->add_control(
			'currency_format',
			array(
				'label' => esc_html__( 'Formato de la moneda', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'' => esc_html__( '1,234.56 (predeterminado)', 'pro-elements' ),
					',' => '1.234,56',
				),
			)
		);

		$this->add_control(
			'sale',
			array(
				'label' => esc_html__( 'Oferta', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'default' => '',
			)
		);

		$this->add_control(
			'original_price',
			array(
				'label' => esc_html__( 'Precio original', 'pro-elements' ),
				'type' => Controls_Manager::NUMBER,
				'default' => '59',
				'condition' => array(
					'sale' => 'yes',
				),
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'period',
			array(
				'label' => esc_html__( 'Periodo', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => array(
					'active' => true,
				),
				'ai' => array(
					'active' => false,
				),
				'default' => esc_html__( 'Al mes', 'pro-elements' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_features',
			array(
				'label' => esc_html__( 'Características', 'pro-elements' ),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'item_text',
			array(
				'label' => esc_html__( 'Texto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => array(
					'active' => true,
				),
				'default' => esc_html__( 'Elemento de la lista', 'pro-elements' ),
			)
		);

		$default_icon = array(
			'value' => 'far fa-check-circle',
			'library' => 'fa-regular',
		);

		$repeater->add_control(
			'selected_item_icon',
			array(
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'item_icon',
				'default' => $default_icon,
			)
		);

		$repeater->add_control(
			'item_icon_color',
			array(
				'label' => esc_html__( 'Color del icono', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} {{CURRENT_ITEM}} i' => 'color: {{VALUE}}',
					'{{WRAPPER}} {{CURRENT_ITEM}} svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'features_list',
			array(
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => array(
					array(
						'item_text' => esc_html__( 'Elemento de la lista n.º 1', 'pro-elements' ),
						'selected_item_icon' => $default_icon,
					),
					array(
						'item_text' => esc_html__( 'Elemento de la lista n.º 2', 'pro-elements' ),
						'selected_item_icon' => $default_icon,
					),
					array(
						'item_text' => esc_html__( 'Elemento de la lista n.º 3', 'pro-elements' ),
						'selected_item_icon' => $default_icon,
					),
				),
				'title_field' => '{{{ item_text }}}',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_footer',
			array(
				'label' => esc_html__( 'Pie', 'pro-elements' ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label' => esc_html__( 'Texto del botón', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Haz clic aquí', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'link',
			array(
				'label' => esc_html__( 'Enlace', 'pro-elements' ),
				'type' => Controls_Manager::URL,
				'default' => array(
					'url' => '#',
				),
				'dynamic' => array(
					'active' => true,
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'footer_additional_info',
			array(
				'label' => esc_html__( 'Información adicional', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'Texto adicional', 'pro-elements' ),
				'rows' => 3,
				'dynamic' => array(
					'active' => true,
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_ribbon',
			array(
				'label' => esc_html__( 'Cinta', 'pro-elements' ),
			)
		);

		$this->add_control(
			'show_ribbon',
			array(
				'label' => esc_html__( 'Mostrar', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'ribbon_title',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Popular', 'pro-elements' ),
				'condition' => array(
					'show_ribbon' => 'yes',
				),
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'ribbon_horizontal_position',
			array(
				'label' => esc_html__( 'Posición', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					),
				),
				'condition' => array(
					'show_ribbon' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_header_style',
			array(
				'label' => esc_html__( 'Cabecera', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'conditions' => array(
					'relation' => 'or',
					'terms' => array(
						array(
							'name' => 'heading',
							'operator' => '!==',
							'value' => '',
						),
						array(
							'name' => 'sub_heading',
							'operator' => '!==',
							'value' => '',
						),
					),
				),
			)
		);

		$this->add_control(
			'header_bg_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-pt-header-bg: {{VALUE}}',
				),
				'conditions' => array(
					'relation' => 'or',
					'terms' => array(
						array(
							'name' => 'heading',
							'operator' => '!==',
							'value' => '',
						),
						array(
							'name' => 'sub_heading',
							'operator' => '!==',
							'value' => '',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'header_padding',
			array(
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'conditions' => array(
					'relation' => 'or',
					'terms' => array(
						array(
							'name' => 'heading',
							'operator' => '!==',
							'value' => '',
						),
						array(
							'name' => 'sub_heading',
							'operator' => '!==',
							'value' => '',
						),
					),
				),
			)
		);

		$this->add_control(
			'heading_heading_style',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'heading!' => '',
				),
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__heading' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'heading!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'heading_typography',
				'selector' => '{{WRAPPER}} .digi-price-table__heading',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'condition' => array(
					'heading!' => '',
				),
			)
		);

		$this->add_control(
			'heading_sub_heading_style',
			array(
				'label' => esc_html__( 'Subtítulo', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'sub_heading!' => '',
				),
			)
		);

		$this->add_control(
			'sub_heading_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__subheading' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'sub_heading!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'sub_heading_typography',
				'selector' => '{{WRAPPER}} .digi-price-table__subheading',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				),
				'condition' => array(
					'sub_heading!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_pricing_element_style',
			array(
				'label' => esc_html__( 'Precio', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'pricing_element_bg_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__price' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'pricing_element_padding',
			array(
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__price' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'price_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__currency, {{WRAPPER}} .digi-price-table__integer-part, {{WRAPPER}} .digi-price-table__fractional-part' => 'color: {{VALUE}}',
				),
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'price_typography',
				// Targeting also the .digi-price-table class in order to get a higher specificity from the inline CSS.
				'selector' => '{{WRAPPER}} .digi-price-table .digi-price-table__price',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
			)
		);

		$this->add_control(
			'heading_currency_style',
			array(
				'label' => esc_html__( 'Símbolo de la moneda', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'currency_symbol!' => '',
				),
			)
		);

		$this->add_control(
			'currency_size',
			array(
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'range' => array(
					'px' => array(
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__price > .digi-price-table__currency' => 'font-size: calc({{SIZE}}em/100)',
				),
				'condition' => array(
					'currency_symbol!' => '',
				),
			)
		);

		$this->add_control(
			'currency_position',
			array(
				'label' => esc_html__( 'Posición', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'before',
				'options' => array(
					'before' => array(
						'title' => esc_html__( 'Antes', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					),
					'after' => array(
						'title' => esc_html__( 'Después', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					),
				),
			)
		);

		$this->add_control(
			'currency_vertical_position',
			array(
				'label' => esc_html__( 'Posición vertical', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'top' => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					),
					'middle' => array(
						'title' => esc_html__( 'En medio', 'pro-elements' ),
						'icon' => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'default' => 'top',
				'selectors_dictionary' => array(
					'top' => 'flex-start',
					'middle' => 'center',
					'bottom' => 'flex-end',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__currency' => 'align-self: {{VALUE}}',
				),
				'condition' => array(
					'currency_symbol!' => '',
				),
			)
		);

		$this->add_control(
			'fractional_part_style',
			array(
				'label' => esc_html__( 'Parte decimal', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'fractional-part_size',
			array(
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'range' => array(
					'px' => array(
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__fractional-part' => 'font-size: calc({{SIZE}}em/100)',
				),
			)
		);

		$this->add_control(
			'fractional_part_vertical_position',
			array(
				'label' => esc_html__( 'Posición vertical', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'top' => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					),
					'middle' => array(
						'title' => esc_html__( 'En medio', 'pro-elements' ),
						'icon' => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'default' => 'top',
				'selectors_dictionary' => array(
					'top' => 'flex-start',
					'middle' => 'center',
					'bottom' => 'flex-end',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__after-price' => 'justify-content: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'heading_original_price_style',
			array(
				'label' => esc_html__( 'Precio original', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'sale' => 'yes',
					'original_price!' => '',
				),
			)
		);

		$this->add_control(
			'original_price_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__original-price' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'sale' => 'yes',
					'original_price!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'original_price_typography',
				'selector' => '{{WRAPPER}} .digi-price-table__original-price',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'condition' => array(
					'sale' => 'yes',
					'original_price!' => '',
				),
			)
		);

		$this->add_control(
			'original_price_vertical_position',
			array(
				'label' => esc_html__( 'Posición vertical', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'top' => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					),
					'middle' => array(
						'title' => esc_html__( 'En medio', 'pro-elements' ),
						'icon' => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'selectors_dictionary' => array(
					'top' => 'flex-start',
					'middle' => 'center',
					'bottom' => 'flex-end',
				),
				'default' => 'bottom',
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__original-price' => 'align-self: {{VALUE}}',
				),
				'condition' => array(
					'sale' => 'yes',
					'original_price!' => '',
				),
			)
		);

		$this->add_control(
			'heading_period_style',
			array(
				'label' => esc_html__( 'Periodo', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'period!' => '',
				),
			)
		);

		$this->add_control(
			'period_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__period' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'period!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'period_typography',
				'selector' => '{{WRAPPER}} .digi-price-table__period',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				),
				'condition' => array(
					'period!' => '',
				),
			)
		);

		$this->add_control(
			'period_position',
			array(
				'label' => esc_html__( 'Posición', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'label_block' => false,
				'options' => array(
					'below' => esc_html__( 'Debajo', 'pro-elements' ),
					'beside' => esc_html__( 'Al lado', 'pro-elements' ),
				),
				'default' => 'below',
				'condition' => array(
					'period!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_features_list_style',
			array(
				'label' => esc_html__( 'Características', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'features_list_bg_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'features_list_padding',
			array(
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'features_list_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list' => '--digi-pt-features-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'features_list_typography',
				'selector' => '{{WRAPPER}} .digi-price-table__features-list li',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->add_control(
			'features_list_alignment',
			array(
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'start' => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					),
					'end' => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'classes' => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left' => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'item_width',
			array(
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'range' => array(
					'%' => array(
						'min' => 25,
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__feature-inner' => 'margin-left: calc((100% - {{SIZE}}%)/2); margin-right: calc((100% - {{SIZE}}%)/2)',
				),
			)
		);

		$this->add_control(
			'list_divider',
			array(
				'label' => esc_html__( 'Divisor', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'divider_style',
			array(
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'solid' => esc_html__( 'Continuo', 'pro-elements' ),
					'double' => esc_html__( 'Doble', 'pro-elements' ),
					'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
					'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
				),
				'default' => 'solid',
				'condition' => array(
					'list_divider' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list li:before' => 'border-top-style: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'divider_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ddd',
				'global' => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'condition' => array(
					'list_divider' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list li:before' => 'border-top-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'divider_weight',
			array(
				'label' => esc_html__( 'Grosor', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default' => array(
					'size' => 2,
				),
				'range' => array(
					'px' => array(
						'min' => 1,
						'max' => 10,
					),
					'em' => array(
						'max' => 1,
					),
					'rem' => array(
						'max' => 1,
					),
				),
				'condition' => array(
					'list_divider' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list li:before' => 'border-top-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'divider_width',
			array(
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'condition' => array(
					'list_divider' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list li:before' => 'margin-left: calc((100% - {{SIZE}}%)/2); margin-right: calc((100% - {{SIZE}}%)/2)',
				),
			)
		);

		$this->add_control(
			'divider_gap',
			array(
				'label' => esc_html__( 'Separación', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default' => array(
					'size' => 15,
				),
				'range' => array(
					'px' => array(
						'min' => 1,
						'max' => 50,
					),
					'em' => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'condition' => array(
					'list_divider' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__features-list li:before' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_footer_style',
			array(
				'label' => esc_html__( 'Pie', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'footer_bg_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__footer' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'footer_padding',
			array(
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__footer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'heading_footer_button',
			array(
				'label' => esc_html__( 'Botón', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'button_text!' => '',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_size',
			array(
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'md',
				'options' => array(
					'xs' => esc_html__( 'Extra pequeño', 'pro-elements' ),
					'sm' => esc_html__( 'Pequeño', 'pro-elements' ),
					'md' => esc_html__( 'Mediano', 'pro-elements' ),
					'lg' => esc_html__( 'Grande', 'pro-elements' ),
					'xl' => esc_html__( 'Extra grande', 'pro-elements' ),
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab(
			'tab_button_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__button' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'button_typography',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
				'selector' => '{{WRAPPER}} .digi-price-table__button',
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'button_background',
				'types' => array( 'classic', 'gradient' ),
				'exclude' => array( 'image' ),
				'selector' => '{{WRAPPER}} .digi-price-table__button',
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
					'color' => array(
						'global' => array(
							'default' => Global_Colors::COLOR_ACCENT,
						),
					),
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(), array(
				'name' => 'button_border',
				'selector' => '{{WRAPPER}} .digi-price-table__button',
				'separator' => 'before',
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_text_padding',
			array(
				'label' => esc_html__( 'Relleno del texto', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			array(
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__button:hover' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'button_background_hover',
				'types' => array( 'classic', 'gradient' ),
				'exclude' => array( 'image' ),
				'selector' => '{{WRAPPER}} .digi-price-table__button:hover',
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__button:hover' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_hover_animation',
			array(
				'label' => esc_html__( 'Animación', 'pro-elements' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'heading_additional_info',
			array(
				'label' => esc_html__( 'Información adicional', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'conditions' => array(
					'relation' => 'and',
					'terms' => array(
						array(
							'name' => 'button_text',
							'operator' => '!==',
							'value' => '',
						),
						array(
							'name' => 'footer_additional_info',
							'operator' => '!==',
							'value' => '',
						),
					),
				),
			)
		);

		$this->add_control(
			'additional_info_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__additional-info' => 'color: {{VALUE}}',
				),
				'conditions' => array(
					'relation' => 'and',
					'terms' => array(
						array(
							'name' => 'button_text',
							'operator' => '!==',
							'value' => '',
						),
						array(
							'name' => 'footer_additional_info',
							'operator' => '!==',
							'value' => '',
						),
					),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'additional_info_typography',
				'selector' => '{{WRAPPER}} .digi-price-table__additional-info',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'conditions' => array(
					'relation' => 'and',
					'terms' => array(
						array(
							'name' => 'button_text',
							'operator' => '!==',
							'value' => '',
						),
						array(
							'name' => 'footer_additional_info',
							'operator' => '!==',
							'value' => '',
						),
					),
				),
			)
		);

		$this->add_control(
			'additional_info_margin',
			array(
				'label' => esc_html__( 'Margen', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default' => array(
					'top' => 15,
					'right' => 30,
					'bottom' => 0,
					'left' => 30,
					'unit' => 'px',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-table__additional-info' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
				'conditions' => array(
					'relation' => 'and',
					'terms' => array(
						array(
							'name' => 'button_text',
							'operator' => '!==',
							'value' => '',
						),
						array(
							'name' => 'footer_additional_info',
							'operator' => '!==',
							'value' => '',
						),
					),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_ribbon_style',
			array(
				'label' => esc_html__( 'Cinta', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_ribbon' => 'yes',
				),
			)
		);

		$this->add_control(
			'ribbon_bg_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-ribbon__inner' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'show_ribbon' => 'yes',
				),
			)
		);

		$ribbon_distance_transform = is_rtl() ? 'translateY(-50%) translateX({{SIZE}}{{UNIT}}) rotate(-45deg)' : 'translateY(-50%) translateX(-50%) translateX({{SIZE}}{{UNIT}}) rotate(-45deg)';

		$this->add_responsive_control(
			'ribbon_distance',
			array(
				'label' => esc_html__( 'Distancia', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 50,
					),
					'em' => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-ribbon__inner' => 'margin-top: {{SIZE}}{{UNIT}}; transform: ' . $ribbon_distance_transform,
				),
				'condition' => array(
					'show_ribbon' => 'yes',
				),
			)
		);

		$this->add_control(
			'ribbon_text_color',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .digi-ribbon__inner' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'show_ribbon' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ribbon_typography',
				'selector' => '{{WRAPPER}} .digi-ribbon__inner',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
				'condition' => array(
					'show_ribbon' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'box_shadow',
				'selector' => '{{WRAPPER}} .digi-ribbon__inner',
				'condition' => array(
					'show_ribbon' => 'yes',
				),
			)
		);

		$this->end_controls_section();
		}

	/** Símbolo de la moneda elegida (o el personalizado). */
	public static function symbol( array $settings ): string {
		$name = (string) ( $settings['currency_symbol'] ?? '' );
		if ( '' === $name ) {
			return '';
		}
		return 'custom' === $name ? (string) ( $settings['currency_symbol_custom'] ?? '' ) : ( self::SYMBOLS[ $name ] ?? '' );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$symbol   = self::symbol( $settings );
		$position = 'after' === ( $settings['currency_position'] ?? 'before' ) ? 'after' : 'before';
		$currency = '' === $symbol ? '' : '<span class="digi-price-table__currency">' . esc_html( $symbol ) . '</span>';
		$before   = 'before' === $position ? $currency : '';
		$after    = 'after' === $position ? $currency : '';
		$format   = '' === (string) ( $settings['currency_format'] ?? '' ) ? '.' : (string) $settings['currency_format'];
		$price    = (string) ( $settings['price'] ?? '' );
		$parts    = explode( $format, $price );
		$integer  = (string) $parts[0];
		$fraction = 2 === count( $parts ) ? (string) $parts[1] : '';
		$period   = (string) ( $settings['period'] ?? '' );
		$beside   = '' !== $period && 'beside' === ( $settings['period_position'] ?? 'below' );
		$sale     = 'yes' === ( $settings['sale'] ?? '' ) && '' !== (string) ( $settings['original_price'] ?? '' );
		$heading  = (string) ( $settings['heading'] ?? '' );
		$sub      = (string) ( $settings['sub_heading'] ?? '' );
		$button   = (string) ( $settings['button_text'] ?? '' );
		$info     = (string) ( $settings['footer_additional_info'] ?? '' );
		$period_html = '<span class="digi-price-table__period digi-price-table__plain">' . wp_kses_post( $period ) . '</span>';

		// Precio completo para los lectores de pantalla: sus partes visibles van en etiquetas separadas.
		$amount = static function ( string $value ) use ( $symbol, $position ): string {
			return 'before' === $position ? $symbol . $value : $value . $symbol;
		};
		$label = $amount( Cards::plain( $price ) ) . ( '' !== $period ? ' ' . Cards::plain( $period ) : '' );
		if ( $sale ) {
			/* translators: 1: precio original, 2: precio actual. */
			$label = sprintf( esc_html__( 'Antes %1$s, ahora %2$s', 'pro-elements' ), $amount( Cards::plain( $settings['original_price'] ) ), $label );
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- textos con wp_kses_post()/esc_html(), atributos con Cards::attributes() e iconos del núcleo.
		echo '<div class="digi-price-table">';
		if ( '' !== $heading || '' !== $sub ) {
			echo '<div class="digi-price-table__header">';
			if ( '' !== $heading ) {
				$tag = in_array( $settings['heading_tag'] ?? 'h3', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $settings['heading_tag'] : 'h3';
				echo '<' . $tag . ' class="digi-price-table__heading">' . wp_kses_post( $heading ) . '</' . $tag . '>';
			}
			if ( '' !== $sub ) {
				echo '<span class="digi-price-table__subheading">' . wp_kses_post( $sub ) . '</span>';
			}
			echo '</div>';
		}
		echo '<span class="elementor-screen-only">' . esc_html( trim( $label ) ) . '</span><div class="digi-price-table__price" aria-hidden="true">';
		if ( $sale ) {
			echo '<del class="digi-price-table__original-price digi-price-table__plain">' . $before . wp_kses_post( (string) $settings['original_price'] ) . $after . '</del>';
		}
		echo $before;
		if ( '' !== $integer ) {
			echo '<span class="digi-price-table__integer-part">' . wp_kses_post( $integer ) . '</span>';
		}
		if ( '' !== $fraction || $beside ) {
			echo '<div class="digi-price-table__after-price"><span class="digi-price-table__fractional-part">' . wp_kses_post( $fraction ) . '</span>' . ( $beside ? $period_html : '' ) . '</div>';
		}
		echo $after;
		if ( '' !== $period && ! $beside ) {
			echo $period_html;
		}
		echo '</div>';
		$features = is_array( $settings['features_list'] ?? null ) ? $settings['features_list'] : array();
		if ( $features ) {
			echo '<ul class="digi-price-table__features-list">';
			foreach ( $features as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$text = (string) ( $item['item_text'] ?? '' );
				echo '<li class="elementor-repeater-item-' . esc_attr( (string) ( $item['_id'] ?? '' ) ) . '"><div class="digi-price-table__feature-inner">';
				echo Cards::icon( $item, 'selected_item_icon', 'item_icon' );
				echo '' !== $text ? '<span>' . wp_kses_post( $text ) . '</span>' : '&nbsp;';
				echo '</div></li>';
			}
			echo '</ul>';
		}
		if ( '' !== $button || '' !== $info ) {
			echo '<div class="digi-price-table__footer">';
			if ( '' !== $button ) {
				$class = 'digi-price-table__button elementor-button elementor-size-' . Cards::button_size( $settings['button_size'] ?? 'md', 'md' );
				if ( ! empty( $settings['button_hover_animation'] ) ) {
					$class .= ' elementor-animation-' . sanitize_html_class( (string) $settings['button_hover_animation'] );
				}
				$link = Cards::link( $settings['link'] ?? array() );
				$btag = $link ? 'a' : 'span';
				echo '<' . $btag . ' ' . Cards::attributes( array( 'class' => $class ) + $link ) . '>' . wp_kses_post( $button ) . '</' . $btag . '>';
			}
			if ( '' !== $info ) {
				echo '<div class="digi-price-table__additional-info">' . wp_kses_post( $info ) . '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
		if ( 'yes' === ( $settings['show_ribbon'] ?? '' ) ) {
			echo Cards::ribbon( $settings );
		}
		// phpcs:enable
	}

	/** Markdown como el de PRO: título, subtítulo, precio con periodo, características, texto adicional y botón. */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$lines    = array();
		$heading  = Cards::plain( $settings['heading'] ?? '' );
		$sub      = Cards::plain( $settings['sub_heading'] ?? '' );
		$price    = Cards::plain( $settings['price'] ?? '' );
		$period   = Cards::plain( $settings['period'] ?? '' );
		$symbol   = self::symbol( $settings );
		if ( '' !== $heading ) {
			$lines[] = '## ' . $heading;
		}
		if ( '' !== $sub ) {
			$lines[] = '### ' . $sub;
		}
		if ( '' !== $price || '' !== $symbol ) {
			$lines[] = '**' . $symbol . $price . ( '' !== $period ? ' ' . $period : '' ) . '**';
		}
		foreach ( (array) ( $settings['features_list'] ?? array() ) as $feature ) {
			$text = Cards::plain( $feature['item_text'] ?? '' );
			if ( '' !== $text ) {
				$lines[] = '- ' . $text;
			}
		}
		$info = Cards::plain( $settings['footer_additional_info'] ?? '' );
		if ( '' !== $info ) {
			$lines[] = $info;
		}
		$button = Cards::plain( $settings['button_text'] ?? '' );
		if ( '' !== $button ) {
			$lines[] = Cards::markdown_button( $button, (string) ( $settings['link']['url'] ?? '' ) );
		}
		return implode( "\n\n", $lines );
	}
}
