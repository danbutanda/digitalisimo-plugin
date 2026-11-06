<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Carrito del menú (`digitalisimo-woo-menu-cart`): sustituye al «Menu Cart» de PRO
 * (`modules/woocommerce/widgets/menu-cart.php`, mapa `modules/digitalisimo-convert/maps/woocommerce-menu-cart.php`).
 *
 * Repite todos los controles de PRO con el mismo nombre, valor por defecto, opciones y condiciones. Los selectores fijan
 * sobre el elemento las mismas variables CSS que PRO con un nombre corto (`--mc-` y las iniciales del de PRO:
 * `--toggle-button-text-color` → `--mc-tbtc`; dos que coinciden llevan un número) y apuntan a las clases propias
 * (`digi-mc__*`; las de prefijo, `digi-mc--*` en lugar de `elementor-menu-cart--*`): así las dos hojas caben en su tope.
 * Marcado y fragmentos en `Menu_Cart_Markup`; hojas `store/assets/menu-cart.css` (conmutador y panel) y
 * `menu-cart-2.css` (productos, subtotal y botones); script `store/assets/menu-cart.js` (defer, sin jQuery) en lugar
 * del manejador de PRO. Usa `wc-cart-fragments` de WooCommerce, como PRO. Los rasgos del panel (título, barra de envío
 * y cupón) lo reconocen igual que al de PRO.
 */
class Menu_Cart extends Base {

	public function get_name() {
		return 'digitalisimo-woo-menu-cart';
	}

	public function get_title() {
		return esc_html__( 'Carrito del menú', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-cart';
	}

	public function get_categories() {
		return array( 'theme-elements', 'woocommerce-elements' );
	}

	public function get_keywords() {
		return array( 'woocommerce', 'carrito', 'menú', 'mini carrito', 'tienda', 'cart', 'menu cart', 'mini cart' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_menu_icon_content',
			[
				'label' => esc_html__( 'Icono del menú', 'pro-elements' ),
			]
		);

		$this->add_control(
			'icon',
			[
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'cart-light' => esc_html__( 'Carrito fino', 'pro-elements' ),
					'cart-medium' => esc_html__( 'Carrito medio', 'pro-elements' ),
					'cart-solid' => esc_html__( 'Carrito sólido', 'pro-elements' ),
					'basket-light' => esc_html__( 'Cesta fino', 'pro-elements' ),
					'basket-medium' => esc_html__( 'Cesta medio', 'pro-elements' ),
					'basket-solid' => esc_html__( 'Cesta sólido', 'pro-elements' ),
					'bag-light' => esc_html__( 'Bolsa fino', 'pro-elements' ),
					'bag-medium' => esc_html__( 'Bolsa medio', 'pro-elements' ),
					'bag-solid' => esc_html__( 'Bolsa sólido', 'pro-elements' ),
					'custom' => esc_html__( 'Personalizado', 'pro-elements' ),
				],
				'default' => 'cart-medium',
				'prefix_class' => 'toggle-icon--', // Clase de prefijo sin uso en la hoja; se conserva como en PRO.
				'render_type' => 'template',
			]
		);

		$this->add_control(
			'menu_icon_svg',
			[
				'label' => esc_html__( 'Icono personalizado', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon_active',
				'default' => [
					'value' => 'fas fa-shopping-cart',
					'library' => 'fa-solid',
				],
				'skin_settings' => [
					'inline' => [
						'none' => [
							'label' => esc_html__( 'Ninguno', 'pro-elements' ),
						],
					],
				],
				'recommended' => [
					'fa-solid' => [
						'shopping-bag',
						'shopping-basket',
						'shopping-cart',
						'cart-arrow-down',
						'cart-plus',
					],
				],
				'skin' => 'inline',
				'label_block' => false,
				'condition' => [
					'icon' => 'custom',
				],
			]
		);

		$this->add_control(
			'items_indicator',
			[
				'label' => esc_html__( 'Indicador de artículos', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'none' => esc_html__( 'Ninguno', 'pro-elements' ),
					'bubble' => esc_html__( 'Burbuja', 'pro-elements' ),
					'plain' => esc_html__( 'Simple', 'pro-elements' ),
				],
				'prefix_class' => 'digi-mc--items-indicator-',
				'default' => 'bubble',
			]
		);

		$this->add_control(
			'hide_empty_indicator',
			[
				'label' => esc_html__( 'Ocultar si está vacío', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'hide',
				'prefix_class' => 'digi-mc--empty-indicator-',
				'condition' => [
					'items_indicator!' => 'none',
				],
			]
		);

		$this->add_control(
			'show_subtotal',
			[
				'label' => esc_html__( 'Subtotal', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'prefix_class' => 'digi-mc--show-subtotal-',
			]
		);

		$this->add_responsive_control(
			'alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ma: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart',
			[
				'label' => esc_html__( 'Carrito', 'pro-elements' ),
			]
		);

		$this->add_control(
			'cart_type',
			[
				'label' => esc_html__( 'Tipo de carrito', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'side-cart' => esc_html__( 'Carrito lateral', 'pro-elements' ),
					'mini-cart' => esc_html__( 'Mini carrito', 'pro-elements' ),
				],
				'default' => 'side-cart',
				'prefix_class' => 'digi-mc--cart-type-',
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'open_cart',
			[
				'label' => esc_html__( 'Abrir el carrito', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'click' => esc_html__( 'Al hacer clic', 'pro-elements' ),
					'mouseover' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
				],
				'default' => 'click',
				'frontend_available' => true,
				'render_type' => 'template',
			]
		);

		$this->add_responsive_control(
			'side_cart_alignment',
			[
				'label' => esc_html__( 'Posición del carrito', 'pro-elements' ),
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
				'condition' => [
					'cart_type' => 'side-cart',
				],
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--mc-scat: translateX(-100%); --mc-scar: auto; --mc-scal: 0;',
					'end' => '--mc-scat: translateX(100%); --mc-scal: auto; --mc-scar: 0;',
				],
			]
		);

		$this->add_responsive_control(
			'mini_cart_alignment',
			[
				'label' => esc_html__( 'Posición del carrito', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-h-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					],
				],
				'condition' => [
					'cart_type' => 'mini-cart',
				],
				'selectors' => [
					'{{WRAPPER}}.digi-mc--cart-type-mini-cart .digi-mc__box' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => 'left: 0; right: auto; transform: none;',
					'center' => 'left: 50%; right: auto; transform: translateX(-50%);',
					'end' => 'right: 0; left: auto; transform: none;',
				],
			]
		);

		$this->add_responsive_control(
			'mini_cart_spacing',
			[
				'label' => esc_html__( 'Distancia', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'min' => -300,
						'max' => 300,
					],
					'em' => [
						'min' => -30,
						'max' => 30,
					],
					'rem' => [
						'min' => -30,
						'max' => 30,
					],
					'%' => [
						'min' => -100,
						'max' => 100,
					],
				],
				'condition' => [
					'cart_type' => 'mini-cart',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-mcs: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'heading_close_cart_button',
			[
				'label' => esc_html__( 'Cerrar el carrito', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'close_cart_button_show',
			[
				'label' => esc_html__( 'Icono de cerrar', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'selectors' => [
					'{{WRAPPER}} .digi-mc__x, {{WRAPPER}} .digi-mc__xc' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'' => 'display: none;',
				],
				'control_type' => 'content',
			]
		);

		$this->add_control(
			'close_cart_icon_svg',
			[
				'label' => esc_html__( 'Icono personalizado', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon_active',
				'skin_settings' => [
					'inline' => [
						'none' => [
							'label' => esc_html__( 'Predeterminado', 'pro-elements' ),
							'icon' => 'fas fa-times',
						],
						'icon' => [
							'icon' => 'eicon-star',
						],
					],
				],
				'recommended' => [
					'fa-regular' => [
						'times-circle',
					],
					'fa-solid' => [
						'times',
						'times-circle',
					],
				],
				'skin' => 'inline',
				'label_block' => false,
				'condition' => [
					'close_cart_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'close_cart_button_alignment',
			[
				'label' => esc_html__( 'Posición del icono', 'pro-elements' ),
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
				'condition' => [
					'close_cart_button_show!' => '',
				],
				'selectors_dictionary' => [
					'start' => 'margin-right: auto',
					'end' => 'margin-left: auto',
				],
				'selectors' => [
					'{{WRAPPER}} .digi-mc__x, {{WRAPPER}} .digi-mc__xc' => '{{VALUE}};',
				],
			]
		);

		$this->add_control(
			'heading_remove_item_button',
			[
				'label' => esc_html__( 'Quitar artículo', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'show_remove_icon',
			[
				'label' => esc_html__( 'Icono de quitar artículo', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'prefix_class' => 'digi-mc--show-remove-button-',
			]
		);

		$this->add_control(
			'remove_item_button_position',
			[
				'label' => esc_html__( 'Posición del icono', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'top' => [
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					],
					'middle' => [
						'title' => esc_html__( 'En medio', 'pro-elements' ),
						'icon' => 'eicon-v-align-middle',
					],
					'bottom' => [
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					],
				],
				'default' => '',
				'prefix_class' => 'remove-item-position--',
				'condition' => [
					'show_remove_icon!' => '',
				],
			]
		);

		$this->add_control(
			'heading_price_quantity',
			[
				'label' => esc_html__( 'Precio y cantidad', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'price_quantity_position',
			[
				'label' => esc_html__( 'Posición', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'top' => [
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					],
					'bottom' => [
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'top' => '--mc-pqpgtr: auto 75%; --mc-pqpas: start;',
					'bottom' => '',
				],
			]
		);

		$this->add_control(
			'show_divider',
			[
				'label' => esc_html__( 'Separadores del carrito', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'separator' => 'before',
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ds: {{VALUE}}; --mc-sds: {{VALUE}};',
				],
				'selectors_dictionary' => [
					'' => 'none',
					'yes' => 'solid',
				],
			]
		);

		$this->add_control(
			'heading_buttons',
			[
				'label' => esc_html__( 'Botones', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'view_cart_button_show',
			[
				'label' => esc_html__( 'Ver carrito', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'' => '--mc-vcbd: none; --mc-cfl: 1fr;',
				],
			]
		);

		$this->add_control(
			'view_cart_button_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justificar', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'condition' => [
					'view_cart_button_show!' => '',
					'checkout_button_show' => '',
				],
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--mc-cfbad: block; --mc-cfbata: left; --mc-cfbabw: auto;',
					'center' => '--mc-cfbad: block; --mc-cfbata: center; --mc-cfbabw: auto;',
					'end' => '--mc-cfbad: block; --mc-cfbata: right; --mc-cfbabw: auto;',
					'justify' => '--mc-cfl: 1fr;',
				],
			]
		);

		$this->add_control(
			'checkout_button_show',
			[
				'label' => esc_html__( 'Finalizar compra', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'' => '--mc-cbd: none; --mc-cfl: 1fr;',
				],
			]
		);

		$this->add_control(
			'checkout_button_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justificar', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'condition' => [
					'checkout_button_show!' => '',
					'view_cart_button_show' => '',
				],
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'start' => '--mc-cfbad: block; --mc-cfbata: left; --mc-cfbabw: auto;',
					'center' => '--mc-cfbad: block; --mc-cfbata: center; --mc-cfbabw: auto;',
					'end' => '--mc-cfbad: block; --mc-cfbata: right; --mc-cfbabw: auto;',
					'justify' => '--mc-cfl: 1fr;',
				],
			]
		);

		$this->add_control(
			'checkout_button_display',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::HIDDEN,
				'condition' => [
					'checkout_button_show' => '',
					'view_cart_button_show' => '',
				],
				'default' => '--mc-cfbad: none;',
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
			]
		);

		$this->add_control(
			'buttons_position',
			[
				'label' => esc_html__( 'Posición vertical', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'top' => [
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					],
					'bottom' => [
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					],
				],
				'default' => '',
				'condition' => [
					'cart_type' => 'side-cart',
				],
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => 'view_cart_button_show',
							'operator' => '!=',
							'value' => '',
						],
						[
							'name' => 'checkout_button_show',
							'operator' => '!=',
							'value' => '',
						],
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'bottom' => '--mc-cbpm: auto;',
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
			'heading_additional_options',
			[
				'label' => esc_html__( 'Carrito', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'automatically_open_cart',
			[
				'label' => esc_html__( 'Abrir el carrito automáticamente', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Abre el carrito cada vez que se añade un artículo.', 'pro-elements' ),
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'no',
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'automatically_update_cart',
			[
				'label' => esc_html__( 'Actualizar el carrito automáticamente', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'description' => esc_html__( 'Los cambios del carrito (por ejemplo, quitar un artículo) se hacen sin recargar la página.', 'pro-elements' ),
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'yes' => '--mc-rld: none; --mc-rad: block;',
					''    => '--mc-rld: block; --mc-rad: none;',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_toggle_style',
			[
				'label' => esc_html__( 'Icono del menú', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->start_controls_tabs( 'toggle_button_colors' );

		$this->start_controls_tab( 'toggle_button_normal_colors', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'toggle_button_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbtc: {{VALUE}};',
				],
				'condition' => [
					'show_subtotal!' => '',
				],
			]
		);

		$this->add_control(
			'toggle_button_icon_color',
			[
				'label' => esc_html__( 'Color del icono', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbic: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'toggle_button_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbbc: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'toggle_button_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbbc2: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'toggle_button_normal_box_shadow',
				'selector' => '{{WRAPPER}} .digi-mc__tg .elementor-button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'toggle_button_hover_colors', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'toggle_button_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbhtc: {{VALUE}};',
				],
				'condition' => [
					'show_subtotal!' => '',
				],
			]
		);

		$this->add_control(
			'toggle_button_hover_icon_color',
			[
				'label' => esc_html__( 'Color del icono', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbihc: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'toggle_button_hover_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbhbc: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'toggle_button_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbhbc2: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'toggle_button_hover_box_shadow',
				'selector' => '{{WRAPPER}} .digi-mc__tg .elementor-button:hover',
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'toggle_button_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 20,
					],
					'em' => [
						'max' => 2,
					],
					'rem' => [
						'max' => 2,
					],
				],
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tbbw: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'toggle_button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
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
					'{{WRAPPER}}' => '--mc-tbbr: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'toggle_button_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .digi-mc__tg .elementor-button',
				'separator' => 'before',
				'condition' => [
					'show_subtotal!' => '',
				],
			]
		);

		$this->add_control(
			'heading_icon_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'toggle_icon_size',
			[
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
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
					'{{WRAPPER}}' => '--mc-tis: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'toggle_icon_spacing',
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
					'{{WRAPPER}} .digi-mc__tg .elementor-button' => 'gap: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'show_subtotal!' => '',
				],
			]
		);

		$start = is_rtl() ? 'right' : 'left';
		$end = is_rtl() ? 'left' : 'right';

		$this->add_control(
			'toggle_icon_position',
			[
				'label' => esc_html__( 'Posición', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'row-reverse' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => "eicon-h-align-{$start}",
					],
					'row' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => "eicon-h-align-{$end}",
					],
				],
				'selectors' => [
					'{{WRAPPER}} .digi-mc__tg .elementor-button' => 'flex-direction: {{VALUE}};',
				],
				'condition' => [
					'show_subtotal!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'toggle_button_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-tip: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'items_indicator_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Indicador de artículos', 'pro-elements' ),
				'separator' => 'before',
				'condition' => [
					'items_indicator!' => 'none',
				],
			]
		);
		$this->add_control(
			'items_indicator_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-iitc: {{VALUE}};',
				],
				'condition' => [
					'items_indicator!' => 'none',
				],
			]
		);

		$this->add_control(
			'items_indicator_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-iibc: {{VALUE}};',
				],
				'condition' => [
					'items_indicator' => 'bubble',
				],
			]
		);

		$this->add_responsive_control(
			'items_indicator_distance',
			[
				'label' => esc_html__( 'Distancia', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'range' => [
					'em' => [
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
					'body:not(.rtl) {{WRAPPER}} .digi-mc__tg .elementor-button-icon .elementor-button-icon-qty[data-counter]' => 'right: -{{SIZE}}{{UNIT}}; top: -{{SIZE}}{{UNIT}};',
					'body.rtl {{WRAPPER}} .digi-mc__tg .elementor-button-icon .elementor-button-icon-qty[data-counter]' => 'right: {{SIZE}}{{UNIT}}; top: -{{SIZE}}{{UNIT}}; left: auto;',
				],
				'condition' => [
					'items_indicator' => 'bubble',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_cart_style',
			[
				'label' => esc_html__( 'Carrito', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbc: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'border_type',
			[
				'label' => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'none' => esc_html__( 'Ninguno', 'pro-elements' ),
					'solid' => esc_html__( 'Sólido', 'pro-elements' ),
					'double' => esc_html__( 'Doble', 'pro-elements' ),
					'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
					'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
					'groove' => esc_html__( 'Ranura', 'pro-elements' ),
				],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbs: {{VALUE}};',
				],
				'default' => 'none',
			]
		);

		$this->add_responsive_control(
			'border_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-mc__main' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'border_type!' => 'none',
				],
			]
		);

		$this->add_control(
			'border_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbc2: {{VALUE}};',
				],
				'condition' => [
					'border_type!' => 'none',
				],
			]
		);

		$this->add_responsive_control(
			'border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbr: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'cart_box_shadow',
				'selector' => '{{WRAPPER}} .digi-mc__main',
			]
		);

		$this->add_responsive_control(
			'cart_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'heading_close',
			[
				'label' => esc_html__( 'Cerrar el carrito', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'condition' => [
					'close_cart_button_show!' => '',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'close_cart_icon_size',
			[
				'label' => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ccis: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'close_cart_button_show!' => '',
				],
			]
		);

		$this->start_controls_tabs( 'cart_icon_style' );

		$this->start_controls_tab(
			'icon_normal',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => [
					'close_cart_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'close_cart_icon_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ccbc: {{VALUE}};',
				],
				'condition' => [
					'close_cart_button_show!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'icon_hover',
			[
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
				'condition' => [
					'close_cart_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'close_cart_icon_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ccbhc: {{VALUE}};',
				],
				'condition' => [
					'close_cart_button_show!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'heading_remove_item_button_style',
			[
				'label' => esc_html__( 'Quitar artículo', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'show_remove_icon!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'remove_item_button_size',
			[
				'label' => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ribs: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'show_remove_icon!' => '',
				],
			]
		);

		$this->start_controls_tabs(
			'cart_remove_item_button_style',
			[
				'condition' => [
					'show_remove_icon!' => '',
				],
			]
		);

		$this->start_controls_tab(
			'remove_item_button_normal',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => [
					'show_remove_icon!' => '',
				],
			]
		);

		$this->add_control(
			'remove_item_button_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ribc: {{VALUE}}',
				],
				'condition' => [
					'show_remove_icon!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'remove_item_button_hover',
			[
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
				'condition' => [
					'show_remove_icon!' => '',
				],
			]
		);

		$this->add_control(
			'remove_item_button_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ribhc: {{VALUE}};',
				],
				'condition' => [
					'show_remove_icon!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'heading_subtotal_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Subtotal', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'subtotal_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-mcsc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'subtotal_typography',
				'selector' => '{{WRAPPER}} .digi-mc__sub',
			]
		);

		$this->add_responsive_control(
			'subtotal_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-mcsta: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'subtotal_divider_style',
			[
				'label' => esc_html__( 'Estilo del separador', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Ninguno', 'pro-elements' ),
					'solid' => esc_html__( 'Sólido', 'pro-elements' ),
					'double' => esc_html__( 'Doble', 'pro-elements' ),
					'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
					'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
					'groove' => esc_html__( 'Ranura', 'pro-elements' ),
				],
				'selectors' => [
					'{{WRAPPER}} .digi-mc__ct' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'' => '--mc-sdlw: 0; --mc-sdrw: 0;',
					'solid' => '--mc-sds: solid;',
					'double' => '--mc-sds: double;',
					'dotted' => '--mc-sds: dotted;',
					'dashed' => '--mc-sds: dashed;',
					'groove' => '--mc-sds: groove;',
				],
			]
		);

		$this->add_responsive_control(
			'subtotal_divider_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-mc__ct' => '--mc-sdtw: {{TOP}}{{UNIT}}; --mc-sdrw: {{RIGHT}}{{UNIT}}; --mc-sdbw: {{BOTTOM}}{{UNIT}}; --mc-sdlw: {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'subtotal_divider_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-mc__ct' => '--mc-sdc: {{VALUE}}',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_product_tabs_style',
			[
				'label' => esc_html__( 'Productos', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'heading_product_title_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título del producto', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'product_title_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .digi-mc__name a',
			]
		);

		$this->start_controls_tabs( 'product_title_colors' );

		$this->start_controls_tab( 'product_title_normal_colors', [ 'label' => esc_html__( 'Normal', 'pro-elements' ) ] );

		$this->add_control(
			'product_title_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-mc__name a' => 'color: {{VALUE}};',

				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'product_title_hover_colors', [ 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ] );

		$this->add_control(
			'product_title_hover_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-mc__name a:hover' => 'color: {{VALUE}};',

				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'heading_product_variations_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Variaciones', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'product_variations_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-pvc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'product_variations_typography',
				'selector' => '{{WRAPPER}} .digi-mc__item .variation',
			]
		);

		$this->add_control(
			'heading_product_price_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Precio del producto', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'product_price_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ppc: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'product_price_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .digi-mc__price',
			]
		);

		$this->add_control(
			'heading_quantity_title_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Cantidad', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'product_quantity_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-mc__price .product-quantity' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'product_quantity_typography',
				'selector' => '{{WRAPPER}} .digi-mc__price .product-quantity',
			]
		);

		$this->add_control(
			'heading_product_divider_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Separador', 'pro-elements' ),
				'separator' => 'before',
			]
		);

		$this->add_control(
			'divider_style',
			[
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Ninguno', 'pro-elements' ),
					'solid' => esc_html__( 'Sólido', 'pro-elements' ),
					'double' => esc_html__( 'Doble', 'pro-elements' ),
					'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
					'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
					'groove' => esc_html__( 'Ranura', 'pro-elements' ),
				],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ds: {{VALUE}}; --mc-sds: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'divider_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-dc: {{VALUE}}; --mc-sdc: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'divider_width',
			[
				'label' => esc_html__( 'Grosor', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 10,
					],
					'em' => [
						'max' => 1,
					],
					'rem' => [
						'max' => 1,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-dw: {{SIZE}}{{UNIT}}; --mc-sdtw: {{SIZE}}{{UNIT}}; --mc-sdrw: {{SIZE}}{{UNIT}}; --mc-sdbw: {{SIZE}}{{UNIT}}; --mc-sdlw: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'divider_gap',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--mc-pdg: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_buttons',
			[
				'label' => esc_html__( 'Botones', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => 'view_cart_button_show',
							'operator' => '!=',
							'value' => '',
						],
						[
							'name' => 'checkout_button_show',
							'operator' => '!=',
							'value' => '',
						],
					],
				],
			]
		);

		$this->add_responsive_control(
			'buttons_layout',
			[
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'inline' => esc_html__( 'En línea', 'pro-elements' ),
					'stacked' => esc_html__( 'Apilados', 'pro-elements' ),
				],
				'default' => 'inline',
				'devices' => [ 'desktop', 'tablet', 'mobile' ],
				'condition' => [
					'view_cart_button_show!' => '',
					'checkout_button_show!' => '',
				],
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'inline' => '--mc-cfl: 1fr 1fr; --mc-pmhs: calc(100vh - 240px); --mc-pmhm: calc(100vh - 385px)',
					'stacked' => '--mc-cfl: 1fr; --mc-pmhs: calc(100vh - 300px); --mc-pmhm: calc(100vh - 450px)',
				],
			]
		);

		$this->add_responsive_control(
			'space_between_buttons',
			[
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--mc-sbb: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'view_cart_button_show!' => '',
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'product_buttons_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .digi-mc__btns .elementor-button',
			]
		);

		$this->add_control(
			'button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
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
					'{{WRAPPER}}' => '--mc-cfbbr: {{SIZE}}{{UNIT}};',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'heading_view_cart_button_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Ver carrito', 'pro-elements' ),
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'view_cart_buttons_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .digi-mc__btns a.digi-mc__view',
				'separator' => 'before',
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->start_controls_tabs(
			'view_cart_button_text_colors',
			[
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->start_controls_tab(
			'heading_view_cart_button_normal_style',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'view_cart_button_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-vcbtc: {{VALUE}};',
				],
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'view_cart_button_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-vcbbc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'heading_view_cart_button_hover_style',
			[
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'view_cart_button_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-vcbhtc: {{VALUE}};',
				],
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'view_cart_button_hover_background',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-vcbhbc: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'view_cart_button_border_hover_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-mc__btns .digi-mc__view:hover' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'view_cart_border_border!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'view_cart_border',
				'selector' => '{{WRAPPER}} .digi-mc__view',
				'separator' => 'before',
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'view_cart_button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-mc__btns a.digi-mc__view' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'view_cart_button_box_shadow',
				'selector' => '{{WRAPPER}} .digi-mc__view',
				'condition' => [
					'view_cart_button_show!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'view_cart_button_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-vcbp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'view_cart_button_show!' => '',
				],
				'separator' => 'after',
			]
		);

		$this->add_control(
			'heading_checkout_button_style',
			[
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Finalizar compra', 'pro-elements' ),
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'cart_checkout_button_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .digi-mc__btns a.digi-mc__pay',
				'separator' => 'before',
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->start_controls_tabs(
			'cart_checkout_button_text_colors',
			[
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->start_controls_tab(
			'heading_cart_checkout_button_normal_style',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'checkout_button_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbtc: {{VALUE}};',
				],
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'checkout_button_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbbc: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'heading_cart_checkout_button_hover_style',
			[
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'checkout_button_hover_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbhtc: {{VALUE}};',
				],
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_control(
			'checkout_button_hover_background',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbhbc: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'checkout_button_border_hover_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .digi-mc__btns .digi-mc__pay:hover' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'checkout_border_border!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'checkout_border',
				'selector' => '{{WRAPPER}} .digi-mc__pay',
				'separator' => 'before',
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'view_checkout_button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .digi-mc__btns a.digi-mc__pay' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'view_checkout_button_box_shadow',
				'selector' => '{{WRAPPER}} .digi-mc__pay',
				'condition' => [
					'checkout_button_show!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'view_checkout_button_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-cbp: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'checkout_button_show!' => '',
				],
				'separator' => 'after',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_messages',
			[
				'label' => esc_html__( 'Mensajes', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'cart_empty_message_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .woocommerce-mini-cart__empty-message',
			]
		);

		$this->add_control(
			'empty_message_color',
			[
				'label' => esc_html__( 'Color del mensaje de carrito vacío', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--mc-emc: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'empty_message_alignment',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--mc-ema: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render_widget() {
		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			return;
		}
		$settings = $this->get_settings_for_display();
		if ( ! wp_script_is( 'wc-cart-fragments' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}
		// Como PRO anota su opción de la plantilla del mini carrito: con el widget en uso, el panel recibe su fragmento.
		if ( 'yes' !== get_option( Menu_Cart_Markup::OPTION ) ) {
			update_option( Menu_Cart_Markup::OPTION, 'yes', true );
		}
		echo Menu_Cart_Markup::widget( $settings, (string) $this->get_id(), self::is_editor(), (bool) did_action( 'woocommerce_add_to_cart' ) && ! wp_doing_ajax() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en Menu_Cart_Markup.
	}
}
