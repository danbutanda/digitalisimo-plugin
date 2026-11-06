<?php
namespace Digitalisimo\Elements\Woo\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Base de los renderizadores de productos propios (C7): repite `modules/woocommerce/classes/base-products-renderer.php` de PRO
 * (sin resultados, nada de HTML, ni siquiera la envoltura de WooCommerce).
 */
abstract class Base_Products_Renderer extends \WC_Shortcode_Products {

	/**
	 * Override original `get_content` that returns an HTML wrapper even if no results found.
	 *
	 * @return string Products HTML
	 */
	public function get_content() {
		$result = $this->get_query_results();

		if ( empty( $result->total ) ) {
			return '';
		}

		return parent::get_content();
	}
}
