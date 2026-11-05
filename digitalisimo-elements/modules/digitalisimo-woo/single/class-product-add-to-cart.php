<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Añadir al carrito (`digitalisimo-woo-product-add-to-cart`): sustituye a «Add To Cart» de PRO
 * (`modules/woocommerce/widgets/product-add-to-cart.php`, mapa `maps/woocommerce-product-add-to-cart.php`). Mismos
 * controles, valores por defecto, condiciones y selectores (disposición, alineación y cantidad con las clases propias
 * `digi-atc--layout-*`, `digi-atc*--align-*` y `digi-atc--show-quantity-*`).
 *
 * WooCommerce pinta lo suyo: en una ficha, el formulario de su plantilla (`woocommerce_template_single_add_to_cart()`,
 * con variaciones, agrupados, externos y sus ganchos, de modo que pasarelas y extensiones siguen funcionando); en una
 * plantilla de bucle, su campo de cantidad y su botón de bucle (`woocommerce_template_loop_add_to_cart()`). El widget
 * sólo pone la envoltura (`digi-atc__holder` alrededor de cantidad y botón con «Apilado» o «Automático», en los mismos
 * ganchos que PRO) y los estilos. En el bucle, un script propio de unos cientos de bytes (`defer`, sin jQuery) pasa la
 * cantidad elegida al botón y coloca «Ver carrito» junto a él, como el controlador de PRO; sólo se carga en las plantillas
 * de bucle y en el editor.
 */
class Product_Add_To_Cart extends Base {

	public function get_name() {
		return 'digitalisimo-woo-product-add-to-cart';
	}

	public function get_title() {
		return esc_html__( 'Añadir al carrito', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-add-to-cart';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'carrito', 'comprar', 'botón', 'producto', 'cart', 'button', 'add to cart' );
	}

	/** El script del bucle sólo hace falta en una plantilla de bucle (y en el editor, que puede pintar una). */
	public function get_script_depends(): array {
		return self::is_edit_mode() || $this->is_loop_item() ? parent::get_script_depends() : array();
	}

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' digi-atc digi-atc--product';
	}

	protected function render_widget() {
		global $product;

		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'before_add_to_cart_quantity' ), 95 );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'before_add_to_cart_quantity' ), 5 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'after_add_to_cart_button' ), 5 );
		?>
		<div class="digi-atc__wrap digi-atc--type-<?php echo esc_attr( $product->get_type() ); ?>">
			<?php
			if ( $this->is_loop_item() ) {
				$this->render_loop_add_to_cart();
			} else {
				woocommerce_template_single_add_to_cart();
			}
			?>
		</div>
		<?php
		remove_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'before_add_to_cart_quantity' ), 95 );
		remove_action( 'woocommerce_before_add_to_cart_button', array( $this, 'before_add_to_cart_quantity' ), 5 );
		remove_action( 'woocommerce_after_add_to_cart_button', array( $this, 'after_add_to_cart_button' ), 5 );
	}

	/** En una plantilla de bucle: cantidad (productos simples, si se muestra) y el botón de bucle de WooCommerce. */
	private function render_loop_add_to_cart() {
		$quantity_args = $this->get_loop_quantity_args();
		$button_args   = array( 'quantity' => $quantity_args['min_value'] );
		?>
		<div class="digi-atc__loop">
			<form class="cart digi-atc__loop-form">
				<?php
				$this->before_add_to_cart_quantity();

				$this->render_loop_quantity_input( $quantity_args );
				woocommerce_template_loop_add_to_cart( $button_args );

				$this->after_add_to_cart_button();
				?>
			</form>
		</div>
		<?php
	}

	private function render_loop_quantity_input( $quantity_args ) {
		global $product;

		if ( 'simple' === $product->get_type() && 'yes' === $this->get_settings_for_display( 'show_quantity' ) ) {
			woocommerce_quantity_input( $quantity_args );
		}
	}

	private function get_loop_quantity_args() {
		global $product;

		$quantity_args = array(
			'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
			'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
			'input_value' => $product->get_min_purchase_quantity(),
			'classes'     => array( 'input-text', 'qty', 'text' ),
		);

		if ( 'no' === get_option( 'woocommerce_enable_ajax_add_to_cart' ) ) {
			$quantity_args['min_value']   = $product->get_min_purchase_quantity();
			$quantity_args['input_value'] = $product->get_min_purchase_quantity();
			$quantity_args['classes'][]   = 'disabled';
		}

		return $quantity_args;
	}

	/** ¿Se pinta en una plantilla de bucle (o en un bucle de colección v4)? Como PRO. */
	private function is_loop_item() {
		$plugin   = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		$document = is_object( $plugin ) && isset( $plugin->documents ) ? $plugin->documents->get_current() : null;
		$context  = '\ElementorPro\Modules\CollectionLoop\Utils\Loop_Iteration_Context';

		return ( $document && 'loop-item' === $document->get_type() )
			|| ( class_exists( $context ) && is_callable( array( $context, 'is_v4_collection_loop_active' ) ) && call_user_func( array( $context, 'is_v4_collection_loop_active' ) ) );
	}

	/** ¿Va la envoltura en este gancho? Las mismas reglas que PRO por tipo de producto. */
	public function should_add_container() {
		global $product;

		if ( ! in_array( $this->get_settings_for_display( 'layout' ), array( 'auto', 'stacked' ), true ) ) {
			return false;
		}

		switch ( current_action() ) {
			case 'woocommerce_before_add_to_cart_quantity':
				return in_array( $product->get_type(), array( 'simple', 'variable' ), true );
			case 'woocommerce_before_add_to_cart_button':
				return in_array( $product->get_type(), array( 'grouped', 'external' ), true );
			case 'woocommerce_after_add_to_cart_button':
			default:
				return true;
		}
	}

	/** Abre la envoltura de cantidad y botón (más sólida cuando otras extensiones añaden elementos al formulario). */
	public function before_add_to_cart_quantity() {
		if ( ! $this->should_add_container() ) {
			return;
		}
		echo '<div class="digi-atc__holder">';
	}

	/** Cierra la envoltura. */
	public function after_add_to_cart_button() {
		if ( ! $this->should_add_container() ) {
			return;
		}
		echo '</div>';
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_layout',
			[
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'layout',
			[
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'En línea', 'pro-elements' ),
					'stacked' => esc_html__( 'Apilado', 'pro-elements' ),
					'auto' => esc_html__( 'Automático', 'pro-elements' ),
				],
				'prefix_class' => 'digi-atc--layout-',
				'render_type' => 'template',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_atc_button_style',
			[
				'label' => esc_html__( 'Botón', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_style_warning();

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
					'justify' => [
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'prefix_class' => 'digi-atc%s--align-',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'button_typography',
				'selector' => '{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'button_border',
				'selector' => '{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button',
				'exclude' => [ 'color' ],
			]
		);

		$this->add_control(
			'button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'button_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs( 'button_style_tabs' );

		$this->start_controls_tab( 'button_style_normal',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'button_bg_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'button_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'button_style_hover',
			[
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			]
		);

		$this->add_control(
			'button_text_color_hover',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cart button:hover, {{WRAPPER}} .cart .button:hover' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'button_bg_color_hover',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cart button:hover, {{WRAPPER}} .cart .button:hover' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'button_border_color_hover',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .cart button:hover, {{WRAPPER}} .cart .button:hover' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'button_transition',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (s)',
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'size' => 0.2,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 3,
						'step' => 0.1,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button' => 'transition: all {{SIZE}}s',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'heading_view_cart_style',
			[
				'label' => esc_html__( 'Ver carrito', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'view_cart_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .added_to_cart' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'view_cart_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				],
				'selector' => '{{WRAPPER}} .added_to_cart',
			]
		);

		$this->add_responsive_control(
			'view_cart_spacing',
			[
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--view-cart-spacing: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_atc_quantity_style',
			[
				'label' => esc_html__( 'Cantidad', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'show_quantity',
			[
				'label' => esc_html__( 'Cantidad', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'prefix_class' => 'digi-atc--show-quantity-',
				'render_type' => 'template',
			]
		);

		$this->add_responsive_control(
			'spacing',
			[
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}}' => '--button-spacing: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'show_quantity!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'quantity_typography',
				'selector' => '{{WRAPPER}} .quantity .qty',
				'condition' => [
					'show_quantity!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'quantity_border',
				'selector' => '{{WRAPPER}} .quantity .qty',
				'exclude' => [ 'color' ],
				'condition' => [
					'show_quantity!' => '',
				],
			]
		);

		$this->add_control(
			'quantity_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .quantity .qty' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'show_quantity!' => '',
				],
			]
		);

		$this->add_control(
			'quantity_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .quantity .qty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'show_quantity!' => '',
				],
			]
		);

		$this->start_controls_tabs( 'quantity_style_tabs',
			[
				'condition' => [
					'show_quantity!' => '',
				],
			]
		);

		$this->start_controls_tab( 'quantity_style_normal',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			]
		);

		$this->add_control(
			'quantity_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .quantity .qty' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'quantity_bg_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .quantity .qty' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'quantity_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .quantity .qty' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'quantity_style_focus',
			[
				'label' => esc_html__( 'Foco', 'pro-elements' ),
			]
		);

		$this->add_control(
			'quantity_text_color_focus',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .quantity .qty:focus' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'quantity_bg_color_focus',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .quantity .qty:focus' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'quantity_border_color_focus',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .quantity .qty:focus' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'quantity_transition',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (s)',
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'size' => 0.2,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 3,
						'step' => 0.1,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .quantity .qty' => 'transition: all {{SIZE}}s',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'section_atc_variations_style',
			[
				'label' => esc_html__( 'Variaciones', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'variations_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'default' => [
					'unit' => '%',
				],
				'selectors' => [
					'.woocommerce {{WRAPPER}} form.cart .variations' => 'width: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'variations_spacing',
			[
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'.woocommerce {{WRAPPER}} form.cart .variations' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'variations_space_between',
			[
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'.woocommerce {{WRAPPER}} form.cart table.variations tr th, .woocommerce {{WRAPPER}} form.cart table.variations tr td' => 'padding-top: calc( {{SIZE}}{{UNIT}}/2 ); padding-bottom: calc( {{SIZE}}{{UNIT}}/2 );',
				],
			]
		);

		$this->add_control(
			'heading_variations_label_style',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'variations_label_color_focus',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} form.cart table.variations label' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'variations_label_typography',
				'selector' => '.woocommerce {{WRAPPER}} form.cart table.variations label',
			]
		);

		$this->add_control(
			'heading_variations_select_style',
			[
				'label' => esc_html__( 'Campo de selección', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'variations_select_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} form.cart table.variations td.value select' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'variations_select_bg_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} form.cart table.variations td.value select, .woocommerce {{WRAPPER}} form.cart table.variations td.value:before' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'variations_select_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'.woocommerce {{WRAPPER}} form.cart table.variations td.value select, .woocommerce {{WRAPPER}} form.cart table.variations td.value:before' => 'border: 1px solid {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'variations_select_typography',
				'selector' => '.woocommerce {{WRAPPER}} form.cart table.variations td.value select, .woocommerce div.product.elementor{{WRAPPER}} form.cart table.variations td.value:before',
			]
		);

		$this->add_control(
			'variations_select_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'.woocommerce {{WRAPPER}} form.cart table.variations td.value select, .woocommerce {{WRAPPER}} form.cart table.variations td.value:before' => 'border-radius: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_section();	}
}
