<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «classic» que además pinta las categorías y etiquetas pedidas. No registra controles:
 * los de la piel original ya quedaron registrados al construirse, con el mismo identificador.
 */
class Posts_Terms_Skin extends \ElementorPro\Modules\Posts\Skins\Skin_Classic {
	protected function _register_controls_actions() {}

	protected function render_meta_data() {
		parent::render_meta_data();
		echo Posts_Terms::markup( (array) $this->parent->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
	}
}
