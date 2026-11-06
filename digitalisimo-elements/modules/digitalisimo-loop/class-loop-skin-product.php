<?php
namespace Digitalisimo\Elements\Loop;

use ElementorPro\Modules\Woocommerce\Traits\Products_Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «Productos» de los bucles propios (`product`, sólo con WooCommerce): el grupo de consulta de productos de PRO
 * (`Products_Trait`) y la consulta que el módulo de WooCommerce de PRO ajusta para todo widget con `is_loop` y esta piel
 * (`loop_query()`). Derivado de `modules/woocommerce/skins/skin-loop-product.php` de PRO Elements (GPL).
 */
class Loop_Skin_Product extends Loop_Skin {
	use Products_Trait;

	public function get_id() {
		return Loop_Base::SKIN_PRODUCT;
	}

	public function get_title() {
		return esc_html__( 'Productos', 'pro-elements' );
	}

	public function render() {
		$this->parent->add_render_attribute( '_wrapper', 'class', 'woocommerce' );
		global $wp_query;
		$wp_query->is_loop_product = true;
		parent::render();
		$wp_query->is_loop_product = null;
	}

	public function register_query_controls( $widget ) {
		$this->parent = $widget;
		$this->add_query_controls( Loop_Base::QUERY_ID );
	}

	protected function render_post() {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			$product = wc_get_product( get_the_ID() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- como PRO.
		}
		parent::render_post();
	}
}
