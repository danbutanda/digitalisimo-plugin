<?php
namespace Digitalisimo\Elements\Loop;

use ElementorPro\Modules\LoopBuilder\Providers\Taxonomy_Loop_Provider;

defined( 'ABSPATH' ) || exit;

/** Piel «Taxonomía de entradas» (`post_taxonomy`, como `modules/loop-builder/skins/skin-loop-post-taxonomy.php` de PRO). */
class Loop_Skin_Post_Taxonomy extends Loop_Skin_Taxonomy {
	protected $post_type = 'post';

	public function get_id() {
		return Loop_Base::SKIN_POST_TAXONOMY;
	}

	public function get_title() {
		return esc_html__( 'Taxonomía de entradas', 'pro-elements' );
	}

	protected function get_default_source_option() {
		return Taxonomy_Loop_Provider::POST_CATEGORY_TAXONOMY;
	}
}
