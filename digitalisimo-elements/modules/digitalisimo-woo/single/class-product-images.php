<?php
namespace Digitalisimo\Elements\Woo\Single;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;

defined( 'ABSPATH' ) || exit;

/**
 * Imágenes del producto (`digitalisimo-woo-product-images`): sustituye a «Product Images» de PRO
 * (`modules/woocommerce/widgets/product-images.php`, mapa `maps/woocommerce-product-images.php`). Mismos controles,
 * valores por defecto y selectores. WooCommerce pinta lo suyo: el distintivo de oferta (`loop/sale-flash.php`) y la
 * galería (`single-product/product-image.php`, con sus imágenes de WooCommerce y su `srcset`), que su script de la ficha
 * convierte en carrusel, lupa y caja de luz según lo que declare el tema. En la vista previa de la biblioteca encola lo
 * que WooCommerce no encola fuera de una ficha, como PRO; en el editor, `Single\Module` inicia la galería recién pintada
 * (PRO lo hacía con un `<script>` en el marcado). La hoja repite la de PRO dentro del widget.
 */
class Product_Images extends Base {

	public function get_name() {
		return 'digitalisimo-woo-product-images';
	}

	public function get_title() {
		return esc_html__( 'Imágenes del producto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-product-images';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'imagen', 'galería', 'lupa', 'producto', 'image', 'gallery', 'product' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_product_gallery_style',
			[
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_style_warning();

		$this->add_control(
			'sale_flash',
			[
				'label' => esc_html__( 'Distintivo de oferta', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'render_type' => 'template',
				'return_value' => 'yes',
				'default' => 'yes',
				'prefix_class' => '',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'image_border',
				'selector' => '.woocommerce {{WRAPPER}} .woocommerce-product-gallery__trigger + .woocommerce-product-gallery__wrapper,
				.woocommerce {{WRAPPER}} .flex-viewport, .woocommerce {{WRAPPER}} .flex-control-thumbs img',
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'image_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'.woocommerce {{WRAPPER}} .woocommerce-product-gallery__trigger + .woocommerce-product-gallery__wrapper,
					.woocommerce {{WRAPPER}} .flex-viewport' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'spacing',
			[
				'label' => esc_html__( 'Separación', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'.woocommerce {{WRAPPER}} .flex-viewport:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'heading_thumbs_style',
			[
				'label' => esc_html__( 'Miniaturas', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'thumbs_border',
				'selector' => '.woocommerce {{WRAPPER}} .flex-control-thumbs img',
			]
		);

		$this->add_responsive_control(
			'thumbs_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'.woocommerce {{WRAPPER}} .flex-control-thumbs img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'spacing_thumbs',
			[
				'label' => esc_html__( 'Separación', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'selectors' => [
					'.woocommerce {{WRAPPER}} .flex-control-thumbs li' => 'padding-right: calc({{SIZE}}{{UNIT}} / 2); padding-left: calc({{SIZE}}{{UNIT}} / 2); padding-bottom: {{SIZE}}{{UNIT}}',
					'.woocommerce {{WRAPPER}} .flex-control-thumbs' => 'margin-right: calc(-{{SIZE}}{{UNIT}} / 2); margin-left: calc(-{{SIZE}}{{UNIT}} / 2)',
				],
			]
		);

		$this->end_controls_section();	}

	protected function render_widget() {
		global $product;
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		$settings = $this->get_settings_for_display();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide qué recursos encolar.
		if ( ! empty( $_GET['elementor_library'] ) && ! empty( $_GET['preview_id'] ) ) {
			// La vista previa de la biblioteca no es una ficha: WooCommerce no encola la galería.
			$this->load_assets_dependencies();
		}

		if ( 'yes' === $settings['sale_flash'] ) {
			wc_get_template( 'loop/sale-flash.php' );
		}

		wc_get_template( 'single-product/product-image.php' );
	}

	/** Lo que WooCommerce encola en una ficha, según lo que declare el tema (como PRO). */
	private function load_assets_dependencies() {
		if ( current_theme_supports( 'wc-product-gallery-zoom' ) ) {
			wp_enqueue_script( 'zoom' );
		}
		if ( current_theme_supports( 'wc-product-gallery-slider' ) ) {
			wp_enqueue_script( 'flexslider' );
		}
		if ( current_theme_supports( 'wc-product-gallery-lightbox' ) ) {
			wp_enqueue_script( 'photoswipe-ui-default' );
			wp_enqueue_style( 'photoswipe-default-skin' );
			add_action( 'wp_footer', 'woocommerce_photoswipe' );
		}
		wp_enqueue_script( 'wc-single-product' );
		wp_enqueue_style( 'photoswipe' );
		wp_enqueue_style( 'photoswipe-default-skin' );
		wp_enqueue_style( 'woocommerce_prettyPhoto_css' );
	}
}
