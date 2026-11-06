<?php
namespace Digitalisimo\Elements\Loop;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «Clásica» de Entradas del archivo: la clásica de Entradas sin «Entradas por página» (lo fija la configuración de
 * lectura) ni la sección «Caja», con los estilos tras la sección de disposición, como
 * `modules/theme-builder/skins/posts-archive-skin-classic.php` de PRO (del que deriva, GPL).
 */
class Archive_Skin_Classic extends Skin_Classic {
	use Archive_Skin_Base;

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- método del núcleo.
		$name = $this->parent->get_name();
		add_action( 'elementor/element/' . $name . '/section_layout/before_section_end', array( $this, 'register_controls' ) );
		add_action( 'elementor/element/' . $name . '/section_layout/after_section_end', array( $this, 'register_style_sections' ) );
	}

	public function get_id() {
		return 'archive_classic';
	}

	public function get_title() {
		return esc_html__( 'Clásica', 'pro-elements' );
	}

	public function get_container_class() {
		return 'digi-posts--skin-classic';
	}

	protected function register_post_count_control() {}
}
