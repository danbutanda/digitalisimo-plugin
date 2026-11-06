<?php
namespace Digitalisimo\Elements\Loop;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «Tarjetas» de Entradas del archivo: la de Entradas sin «Entradas por página», con los estilos tras la sección de
 * disposición, como `modules/theme-builder/skins/posts-archive-skin-cards.php` de PRO (del que deriva, GPL).
 */
class Archive_Skin_Cards extends Skin_Cards {
	use Archive_Skin_Base;

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- método del núcleo.
		$name = $this->parent->get_name();
		add_action( 'elementor/element/' . $name . '/section_layout/before_section_end', array( $this, 'register_controls' ) );
		add_action( 'elementor/element/' . $name . '/section_layout/after_section_end', array( $this, 'register_style_sections' ) );
		add_action( 'elementor/element/' . $name . '/archive_cards_section_design_image/before_section_end', array( $this, 'register_additional_design_image_controls' ) );
	}

	public function get_id() {
		return 'archive_cards';
	}

	public function get_title() {
		return esc_html__( 'Tarjetas', 'pro-elements' );
	}

	public function get_container_class() {
		return 'digi-posts--skin-cards';
	}

	protected function register_post_count_control() {}
}
