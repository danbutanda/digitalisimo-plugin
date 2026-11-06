<?php
namespace Digitalisimo\Elements\Woo\Single;

use Digitalisimo\Elements\Woo\Registry;
use Digitalisimo\Elements\Woo\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Base de los widgets de la ficha de producto de C7 (frente M, `docs/pro/c7.md`) que en PRO heredan
 * `modules/woocommerce/widgets/base-widget.php`. Sigue el contrato de `Woo\Widget` (la subclase implementa
 * `render_widget()`): sin WooCommerce, `Inert` la registra oculta con sus mismos controles y el aviso del editor.
 *
 * - Categoría «WooCommerce · Producto» (`woocommerce-elements-single`), la de PRO: el widget se ofrece en las mismas
 *   plantillas que el de PRO (las de producto; en las plantillas de bucle de productos, `Panel` los recomienda).
 * - Recursos propios por `Registry::style_depends()`/`script_depends()` (hoja y script del widget, si existen).
 * - WooCommerce pinta lo suyo: cada widget usa las plantillas y funciones de WooCommerce que usa PRO, con sus ganchos.
 */
abstract class Base extends Widget {
	use Product_Trait;

	public function get_categories() {
		return array( 'woocommerce-elements-single' );
	}

	public function get_style_depends(): array {
		return Registry::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Registry::script_depends( $this->get_name() );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	public function render_plain_content() {}

	public function get_group_name() {
		return 'woocommerce';
	}
}
