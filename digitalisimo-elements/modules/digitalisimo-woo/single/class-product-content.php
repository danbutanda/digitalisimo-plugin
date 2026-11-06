<?php
namespace Digitalisimo\Elements\Woo\Single;

use Digitalisimo\Elements\Woo\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Contenido del producto (`digitalisimo-woo-product-content`): sustituye a «Product Content» de PRO
 * (`modules/woocommerce/widgets/product-content.php`, mapa `maps/woocommerce-product-content.php`). Como el de PRO, que
 * hereda «Post Content», hereda el Contenido de la entrada propio de C4
 * (`Theme_Elements\Widgets\Post_Content`): mismos controles (alineación, color y tipografía), la misma forma de pintar el
 * contenido (contraseña, guarda contra la recursión, vista previa del editor y del Theme Builder) y la misma guarda.
 *
 * El envoltorio no lleva la clase de compatibilidad del contenido de la entrada (`elementor-widget-theme-post-content`),
 * igual que el de PRO: la regla del núcleo de las figuras y el Indicador de progreso tratan el contenido del producto
 * como PRO. Sin WooCommerce, `Inert` lo sustituye en el editor por su widget oculto, que conserva los ajustes.
 */
class Product_Content extends \Digitalisimo\Elements\Theme_Elements\Widgets\Post_Content {

	public function get_name() {
		return 'digitalisimo-woo-product-content';
	}

	public function get_title() {
		return esc_html__( 'Contenido del producto', 'pro-elements' );
	}

	public function get_categories() {
		return array( 'woocommerce-elements-single' );
	}

	public function get_keywords() {
		return array( 'contenido', 'producto', 'descripción', 'content', 'post', 'product' );
	}

	public function get_style_depends(): array {
		return Registry::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return array();
	}

	protected function get_html_wrapper_class() {
		return 'elementor-widget-' . $this->get_name();
	}

	public function get_group_name() {
		return 'woocommerce';
	}

	/** Como PRO: la descripción del producto (o su descripción corta) en Markdown; si no hay, la del contenido. */
	public function render_markdown(): string {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
		if ( $product ) {
			$description = $product->get_description();
			if ( empty( $description ) ) {
				$description = $product->get_short_description();
			}
			if ( ! empty( $description ) && class_exists( '\Elementor\Modules\MarkdownRender\Html_To_Markdown' ) ) {
				$content = str_replace( ']]>', ']]&gt;', (string) apply_filters( 'the_content', $description ) );
				return \Elementor\Modules\MarkdownRender\Html_To_Markdown::convert( $content );
			}
		}
		return parent::render_markdown();
	}
}
