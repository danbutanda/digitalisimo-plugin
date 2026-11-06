<?php
namespace Digitalisimo\Elements\Woo\Single;

defined( 'ABSPATH' ) || exit;

/**
 * El producto que pinta un widget de la ficha de C7 (frente M), con la misma regla que PRO (`Product_Id_Trait` de
 * `modules/woocommerce/traits/product-id-trait.php`, GPL): el del bucle si ya está consultado, la variación si se pinta
 * una, el pedido por ID (sólo si está publicado) o el de la consulta actual. Lo usan `Product_Trait` y los widgets que
 * heredan la cuadrícula de productos (`Store\Products_Base`).
 */
trait Product_Lookup {

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
}
