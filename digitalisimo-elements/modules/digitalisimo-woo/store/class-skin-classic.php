<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;
use Elementor\Skin_Base;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Presentación «Clásico» de Productos (clásico) (C7, frente N): repite `modules/woocommerce/skins/skin-classic.php` de
 * PRO. Control `columns` de la presentación (guardado como `classic_columns`, 4 por defecto) y el mismo bucle:
 * `query_posts()` del widget, `woocommerce_product_loop_start()`, `content-product` por producto y la clase `product`
 * en cada entrada mientras se pinta.
 *
 * @property Products_Classic $parent
 */
class Skin_Classic extends Skin_Base {

	public function get_id() {
		return 'classic';
	}

	public function get_title() {
		return esc_html__( 'Clásico', 'pro-elements' );
	}

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- método del núcleo.
		add_action( 'elementor/element/digitalisimo-woo-products-classic/section_layout/after_section_start', [ $this, 'register_controls' ] );
	}

	public function register_controls( Widget_Base $widget ) {
		$this->parent = $widget;

		$this->add_control(
			'columns',
			[
				'label'   => esc_html__( 'Columnas', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				],
				'default' => '4',
			]
		);
	}

	/** Clase `product` en cada entrada del bucle (`add_product_post_class()` del módulo de WooCommerce de PRO). */
	public static function product_post_class( $classes ) {
		$classes[] = 'product';
		return $classes;
	}

	public function render() {
		// Sin WooCommerce (registro oculto del editor): el aviso, nunca el bucle.
		if ( \Digitalisimo\Elements\Woo\Inert::active() ) {
			echo \Digitalisimo\Elements\Woo\Inert::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texto propio escapado.
			return;
		}

		$this->parent->query_posts();

		/** @var \WP_Query $query */
		$query = $this->parent->get_query();

		if ( ! $query->have_posts() ) {
			return;
		}

		global $woocommerce_loop;

		$woocommerce_loop['columns'] = (int) $this->get_instance_value( 'columns' );

		add_filter( 'post_class', [ __CLASS__, 'product_post_class' ] );

		echo '<div class="woocommerce columns-' . esc_attr( $woocommerce_loop['columns'] ) . '">';

		woocommerce_product_loop_start();

		while ( $query->have_posts() ) {
			$query->the_post();

			wc_get_template_part( 'content', 'product' );
		}

		woocommerce_product_loop_end();

		woocommerce_reset_loop();

		wp_reset_postdata();

		echo '</div>';

		remove_filter( 'post_class', [ __CLASS__, 'product_post_class' ] );
	}
}
