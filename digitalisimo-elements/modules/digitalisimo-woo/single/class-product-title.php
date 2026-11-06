<?php
namespace Digitalisimo\Elements\Woo\Single;

use Digitalisimo\Elements\Woo\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Título del producto (`digitalisimo-woo-product-title`): sustituye a «Product Title» de PRO
 * (`modules/woocommerce/widgets/product-title.php`, mapa `maps/woocommerce-product-title.php`). Como el de PRO, hereda el
 * Encabezado del núcleo (`\Elementor\Widget_Heading`): mismos controles, marcado y hoja (`widget-heading`), con lo que PRO
 * cambia repetido igual: el título toma por defecto la etiqueta dinámica del título del producto, el nivel por defecto
 * es `h1`, el encabezado lleva `product_title entry-title` y el envoltorio `elementor-page-title elementor-widget-heading`.
 * Un encabezado nunca es `<title>` ni lleva señales SEO. La hoja propia sólo añade el foco visible del enlace.
 *
 * Sin WooCommerce, `Inert` lo sustituye en el editor por su widget oculto, que conserva los ajustes.
 */
class Product_Title extends \Elementor\Widget_Heading {
	use Product_Trait;

	public function get_name() {
		return 'digitalisimo-woo-product-title';
	}

	public function get_title() {
		return esc_html__( 'Título del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-title';
	}

	public function get_categories() {
		return array( 'woocommerce-elements-single' );
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'título', 'encabezado', 'producto', 'title', 'heading', 'product' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( parent::get_style_depends(), Registry::style_depends( $this->get_name() ) ) ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->update_control(
			'title',
			array(
				'dynamic' => array(
					'default' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text( null, 'woocommerce-product-title-tag' ),
				),
			),
			array(
				'recursive' => true,
			)
		);

		$this->update_control(
			'header_size',
			array(
				'default' => 'h1',
			)
		);
	}

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' elementor-page-title elementor-widget-heading';
	}

	protected function render() {
		$this->add_render_attribute( 'title', 'class', array( 'product_title', 'entry-title' ) );
		parent::render();
	}

	protected function content_template() {
		?>
		<# view.addRenderAttribute( 'title', 'class', [ 'product_title', 'entry-title' ] ); #>
		<?php
		parent::content_template();
	}

	public function render_plain_content() {}

	/** Como PRO: el del Encabezado y, si queda vacío, el nombre del producto con su nivel. */
	public function render_markdown(): string {
		$markdown = parent::render_markdown();
		if ( '' !== $markdown ) {
			return $markdown;
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
		if ( ! $product ) {
			return '';
		}
		$tag   = (string) ( $this->get_settings_for_display()['header_size'] ?? 'h1' );
		$level = preg_match( '/^h([1-6])$/', $tag, $found ) ? (int) $found[1] : 2;
		return str_repeat( '#', $level ) . ' ' . $product->get_name();
	}

	public function get_group_name() {
		return 'woocommerce';
	}
}
