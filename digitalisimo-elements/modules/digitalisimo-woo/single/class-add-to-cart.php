<?php
namespace Digitalisimo\Elements\Woo\Single;

use Digitalisimo\Elements\Woo\Registry;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Añadir al carrito personalizado (`digitalisimo-woo-add-to-cart`): sustituye a «Custom Add To Cart» de PRO
 * (`modules/woocommerce/widgets/add-to-cart.php`, mapa `maps/wc-add-to-cart.php`). Como el de PRO, hereda el Botón del
 * núcleo (`\Elementor\Widget_Button`): mismos controles (los del botón más producto, cantidad y disposición), con lo que
 * PRO cambia repetido igual (enlace oculto, texto e icono por defecto, alineación con la clase propia
 * `digi-atc*--align-*`).
 *
 * WooCommerce pinta lo suyo: sin «Mostrar la cantidad», el botón del núcleo con la URL, el producto y la cantidad que
 * WooCommerce usa para añadir por AJAX (`add_to_cart_url()`, `ajax_add_to_cart`); con ella, el formulario de la ficha de
 * WooCommerce (`woocommerce_template_single_add_to_cart()`) con el texto del botón del widget. PRO desactivaba el escape
 * de todo `esc_html()` mientras pintaba el formulario para que el texto pudiera llevar el icono; el propio sólo deja sin
 * escapar ese texto exacto, el que pinta el widget.
 *
 * Sin WooCommerce, `Inert` lo sustituye en el editor por su widget oculto, que conserva los ajustes.
 */
class Add_To_Cart extends \Elementor\Widget_Button {
	use Product_Trait;

	/** @var string Texto del botón ya pintado por el widget, que `unescape_html()` deja pasar sin escapar. */
	private $button_html = '';

	public function get_name() {
		return 'digitalisimo-woo-add-to-cart';
	}

	public function get_title() {
		return esc_html__( 'Añadir al carrito personalizado', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-woocommerce';
	}

	public function get_categories() {
		return array( 'woocommerce-elements' );
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'carrito', 'comprar', 'producto', 'botón', 'cart', 'product', 'button', 'add to cart' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	/** La hoja del botón del núcleo, la común de los dos «Añadir al carrito» y la propia. */
	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( parent::get_style_depends(), Registry::style_depends( 'digitalisimo-woo-product-add-to-cart' ), Registry::style_depends( $this->get_name() ) ) ) );
	}

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' digi-atc digi-atc--custom';
	}

	/** Un kit no lleva el producto elegido (como PRO). */
	public function on_export( $element ) {
		unset( $element['settings']['product_id'] );

		return $element;
	}

	/**
	 * `esc_html` mientras se pinta el formulario: deja sin escapar sólo el texto del botón que pintó el widget.
	 *
	 * @param string $safe_text Texto escapado.
	 * @param string $text      Texto original.
	 */
	public function unescape_html( $safe_text, $text ) {
		return '' !== $this->button_html && $text === $this->button_html ? $text : $safe_text;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_product',
			[
				'label' => esc_html__( 'Producto', 'pro-elements' ),
			]
		);

		$this->add_control(
			'product_id',
			[
				'label' => esc_html__( 'Producto', 'pro-elements' ),
				'type' => 'query',
				'options' => [],
				'label_block' => true,
				'autocomplete' => [
					'object' => 'post',
					'query' => [
						'post_type' => [ 'product' ],
					],
				],
			]
		);

		$this->add_control(
			'show_quantity',
			[
				'label' => esc_html__( 'Mostrar la cantidad', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'description' => esc_html__( 'Al activar esta opción se desactivan algunos controles de diseño.', 'pro-elements' ),
			]
		);

		$this->add_control(
			'quantity',
			[
				'label' => esc_html__( 'Cantidad', 'pro-elements' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 1,
				'condition' => [
					'show_quantity' => '',
				],
			]
		);

		$this->end_controls_section();

		parent::register_controls();

		$this->start_controls_section(
			'section_layout',
			[
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
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

		$this->update_control(
			'link',
			[
				'type' => Controls_Manager::HIDDEN,
				'default' => [
					'url' => '',
				],
			]
		);

		$this->update_control(
			'text',
			[
				'default' => esc_html__( 'Añadir al carrito', 'pro-elements' ),
				'placeholder' => esc_html__( 'Añadir al carrito', 'pro-elements' ),
			]
		);

		$this->update_responsive_control(
			'align',
			[
				'prefix_class' => 'digi-atc%s--align-',
				'selectors_dictionary' => Product_Add_To_Cart::ALIGN_VARIABLES,
				'selectors' => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
			]
		);

		$this->update_control(
			'selected_icon',
			[
				'default' => [
					'value' => 'fas fa-shopping-cart',
					'library' => 'fa-solid',
				],
			]
		);

		$this->update_control(
			'size',
			[
				'condition' => [
					'show_quantity' => '',
				],
			]
		);
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! empty( $settings['product_id'] ) ) {
			$product_id = $settings['product_id'];
		} else {
			$product_id = get_queried_object_id();
		}

		global $product;
		$product = $this->get_product( $product_id );

		if ( in_array( $settings['layout'], [ 'auto', 'stacked' ], true ) ) {
			add_action( 'woocommerce_before_add_to_cart_quantity', [ $this, 'before_add_to_cart_quantity' ], 95 );
			add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'after_add_to_cart_button' ], 5 );
		}

		if ( 'yes' === $settings['show_quantity'] ) {
			$this->render_form_button( $product );
		} else {
			$this->render_ajax_button( $product );
		}

		if ( in_array( $settings['layout'], [ 'auto', 'stacked' ], true ) ) {
			remove_action( 'woocommerce_before_add_to_cart_quantity', [ $this, 'before_add_to_cart_quantity' ], 95 );
			remove_action( 'woocommerce_after_add_to_cart_button', [ $this, 'after_add_to_cart_button' ], 5 );
		}
	}

	/** Abre la envoltura de cantidad y botón. */
	public function before_add_to_cart_quantity() {
		echo '<div class="digi-atc__holder">';
	}

	/** Cierra la envoltura. */
	public function after_add_to_cart_button() {
		echo '</div>';
	}

	/**
	 * El botón del núcleo con los datos que WooCommerce usa para añadir al carrito por AJAX.
	 *
	 * @param \WC_Product|false $product Producto.
	 */
	private function render_ajax_button( $product ) {
		$settings = $this->get_settings_for_display();

		if ( $product ) {
			$class = implode(
				' ',
				array_filter(
					[
						'product_type_' . $product->get_type(),
						$product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
						$product->supports( 'ajax_add_to_cart' ) ? 'ajax_add_to_cart' : '',
					]
				)
			);

			$this->add_render_attribute(
				'button',
				[
					'rel' => 'nofollow',
					'href' => $product->add_to_cart_url(),
					'data-quantity' => ( isset( $settings['quantity'] ) ? $settings['quantity'] : 1 ),
					'data-product_id' => $product->get_id(),
					'class' => $class,
				]
			);
		} elseif ( current_user_can( 'manage_options' ) ) {
			$settings['text'] = esc_html__( 'Elige un producto válido', 'pro-elements' );
			$this->set_settings( $settings );
		}

		parent::render();
	}

	/**
	 * El formulario de la ficha de WooCommerce con el texto del botón del widget.
	 *
	 * @param \WC_Product|false $product Producto.
	 */
	private function render_form_button( $product ) {
		if ( ! $product ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo esc_html__( 'Elige un producto válido', 'pro-elements' );
			}
			return;
		}

		ob_start();
		$this->render_text();
		$this->button_html = (string) ob_get_clean();

		$text_callback = function () {
			return $this->button_html;
		};

		add_filter( 'woocommerce_get_stock_html', '__return_empty_string' );
		add_filter( 'woocommerce_product_single_add_to_cart_text', $text_callback );
		add_filter( 'esc_html', [ $this, 'unescape_html' ], 10, 2 );

		ob_start();
		woocommerce_template_single_add_to_cart();
		$form = (string) ob_get_clean();
		$form = str_replace( 'single_add_to_cart_button', 'single_add_to_cart_button elementor-button', $form );

		echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formulario de WooCommerce.

		remove_filter( 'woocommerce_product_single_add_to_cart_text', $text_callback );
		remove_filter( 'woocommerce_get_stock_html', '__return_empty_string' );
		remove_filter( 'esc_html', [ $this, 'unescape_html' ] );
		$this->button_html = '';
	}

	/** Siempre desde el servidor (necesita el producto). */
	protected function content_template() {}

	public function get_group_name() {
		return 'woocommerce';
	}

	/** Como PRO: producto, cantidad y texto del botón en Markdown. */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$product  = $this->get_product( $settings['product_id'] ?? false );

		if ( ! $product ) {
			return '';
		}

		$lines = [ '- **' . esc_html__( 'Producto', 'pro-elements' ) . ':** ' . $product->get_name() ];

		if ( 'yes' === ( $settings['show_quantity'] ?? '' ) ) {
			$lines[] = '- **' . esc_html__( 'Cantidad', 'pro-elements' ) . ':** ' . esc_html__( 'A elegir', 'pro-elements' );
		} else {
			$lines[] = '- **' . esc_html__( 'Cantidad', 'pro-elements' ) . ':** ' . (int) ( $settings['quantity'] ?? 1 );
		}

		$lines[] = '- **' . esc_html__( 'Botón', 'pro-elements' ) . ':** ' . trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) ( $settings['text'] ?? '' ) ) ) );

		return implode( "\n", $lines );
	}
}
