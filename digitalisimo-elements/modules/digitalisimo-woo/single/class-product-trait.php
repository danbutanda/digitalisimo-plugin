<?php
namespace Digitalisimo\Elements\Woo\Single;

defined( 'ABSPATH' ) || exit;

/**
 * Lo común de los widgets de la ficha de producto de C7 (frente M, `docs/pro/c7.md`), también de los que heredan un widget
 * del núcleo (Título del producto, Añadir al carrito personalizado) y no pueden heredar `Base`.
 *
 * - `get_product()`: el producto que pinta el widget, con la misma regla que PRO (`Product_Id_Trait` de
 *   `modules/woocommerce/traits/product-id-trait.php`, GPL): el del bucle si ya está consultado, la variación si se pinta
 *   una, el pedido por ID (sólo si está publicado) o el de la consulta actual.
 * - `add_style_warning()`: el aviso informativo que PRO pone al principio de la sección de estilo (`wc_style_warning`),
 *   con el mismo nombre de control.
 */
trait Product_Trait {

	/**
	 * Producto que se pinta, o `false`.
	 *
	 * @param int|false $product_id ID pedido por el widget, o `false` para el de la consulta.
	 * @return \WC_Product|false
	 */
	public function get_product( $product_id = false ) {
		global $product;

		if ( $this->product_already_queried( $product ) ) {
			return $product;
		}

		if ( 'product_variation' === get_post_type() ) {
			return $this->get_product_variation();
		}

		$product_data = wc_get_product( $product_id );

		if ( ! $product_data ) {
			$product_data = wc_get_product();
		}

		if ( $product_id && $product_data && 'publish' !== $product_data->get_status() ) {
			return false;
		}

		return $product_data;
	}

	/** ¿Se pinta dentro de un bucle de productos que ya fijó `$product`? */
	private function product_already_queried( $product ): bool {
		global $wp_query;

		if ( empty( $wp_query->is_loop_product ) ) {
			return false;
		}

		return $product instanceof \WC_Product;
	}

	/** La variación que se está viendo. */
	public function get_product_variation() {
		return wc_get_product( get_the_ID() );
	}

	/** Aviso de la sección de estilo (mismo control que PRO: `wc_style_warning`). */
	protected function add_style_warning(): void {
		$this->add_control(
			'wc_style_warning',
			array(
				'type'       => \Elementor\Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content'    => esc_html__( 'El tema y otros plugins suelen afectar al estilo de este widget. Si ves algún problema, prueba con un tema básico y desactiva los plugins relacionados.', 'pro-elements' ),
			)
		);
	}

	/** ¿Se pinta en el editor? */
	protected static function is_edit_mode(): bool {
		$plugin = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		return is_object( $plugin ) && isset( $plugin->editor ) && is_object( $plugin->editor ) && $plugin->editor->is_edit_mode();
	}

	/** Marcado optimizado del núcleo: sin envoltura interior (como PRO). */
	protected static function optimized_markup(): bool {
		$plugin = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		return is_object( $plugin ) && isset( $plugin->experiments ) && is_object( $plugin->experiments ) && $plugin->experiments->is_feature_active( 'e_optimized_markup' );
	}
}
