<?php
namespace Digitalisimo\Elements\Woo\Single;

defined( 'ABSPATH' ) || exit;

/**
 * Lo común de los widgets de la ficha de producto de C7 (frente M, `docs/pro/c7.md`), también de los que heredan un widget
 * del núcleo (Título del producto, Añadir al carrito personalizado) y no pueden heredar `Base`.
 *
 * - `get_product()`: el producto que pinta el widget (`Product_Lookup`, la regla de PRO).
 * - `add_style_warning()`: el aviso informativo que PRO pone al principio de la sección de estilo (`wc_style_warning`),
 *   con el mismo nombre de control.
 */
trait Product_Trait {
	use Product_Lookup;

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
