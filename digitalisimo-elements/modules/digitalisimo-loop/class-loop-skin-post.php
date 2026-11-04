<?php
namespace Digitalisimo\Elements\Loop;

defined( 'ABSPATH' ) || exit;

/** Piel «Entradas» de los bucles propios (`post`, como `modules/loop-builder/skins/skin-loop-post.php` de PRO). */
class Loop_Skin_Post extends Loop_Skin {
	public function get_id() {
		return Loop_Base::SKIN_POST;
	}

	public function get_title() {
		return esc_html__( 'Entradas', 'pro-elements' );
	}
}
