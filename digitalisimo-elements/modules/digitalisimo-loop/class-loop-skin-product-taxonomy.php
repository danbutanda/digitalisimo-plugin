<?php
namespace Digitalisimo\Elements\Loop;

use ElementorPro\Modules\LoopBuilder\Providers\Taxonomy_Loop_Provider;

defined( 'ABSPATH' ) || exit;

/** Piel «Taxonomía de productos» (`product_taxonomy`, sólo con WooCommerce; `skin-loop-product-taxonomy.php` de PRO). */
class Loop_Skin_Product_Taxonomy extends Loop_Skin_Taxonomy {
	protected $post_type = 'product';

	public function get_id() {
		return Loop_Base::SKIN_PRODUCT_TAXONOMY;
	}

	public function get_title() {
		return esc_html__( 'Taxonomía de productos', 'pro-elements' );
	}

	public function render() {
		$this->parent->add_render_attribute( '_wrapper', 'class', 'woocommerce' );
		parent::render();
	}

	protected function get_default_source_option() {
		return Taxonomy_Loop_Provider::PRODUCT_CATEGORY_TAXONOMY;
	}
}
