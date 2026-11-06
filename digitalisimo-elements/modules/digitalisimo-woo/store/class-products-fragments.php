<?php
namespace Digitalisimo\Elements\Woo\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Fragmentos de WooCommerce de los Productos propios (C7, frente N): repite `products_query_sources_fragments()` del
 * módulo de WooCommerce de PRO para `digitalisimo-woo-products`. Cuando cambia el carrito, las secciones de
 * relacionados, ventas dirigidas y ventas cruzadas del documento desde el que se pide el refresco se vuelven a pintar
 * (con el carrito vacío, las de ventas se vacían), igual que PRO hace con su widget.
 *
 * Arranca con `Woo\Module::BOOT` (un filtro; ninguna consulta hasta que WooCommerce pide sus fragmentos).
 */
final class Products_Fragments {
	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
	}

	/**
	 * @param array $fragments Fragmentos de WooCommerce.
	 * @return array
	 */
	public static function fragments( $fragments ) {
		if ( ! is_array( $fragments ) || ! function_exists( 'WC' ) || ! WC()->cart || ! class_exists( '\Elementor\Plugin' ) ) {
			return $fragments;
		}
		if ( 0 === WC()->cart->get_cart_contents_count() ) {
			return $fragments;
		}
		$document = \Elementor\Plugin::$instance->documents->get( url_to_postid( wp_get_referer() ) );
		if ( ! is_object( $document ) ) {
			return $fragments;
		}
		$data = $document->get_elements_data();
		\Elementor\Plugin::$instance->db->iterate_data(
			$data,
			static function ( $element ) use ( &$fragments ) {
				if ( ! isset( $element['widgetType'] ) || 'digitalisimo-woo-products' !== $element['widgetType'] ) {
					return $element;
				}
				$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
				$type     = isset( $settings[ Products_Base::QUERY_CONTROL_NAME . '_post_type' ] ) ? $settings[ Products_Base::QUERY_CONTROL_NAME . '_post_type' ] : '';
				$content  = null;
				if ( 'related_products' === $type ) {
					$content = Products::get_products_related_content( $settings );
				} elseif ( 'upsells' === $type ) {
					$content = Products::get_upsells_content( $settings );
				} elseif ( 'cross_sells' === $type ) {
					$content = Products::get_cross_sells_content( $settings );
				}
				if ( $content ) {
					$fragments[ 'div.elementor-element-' . $element['id'] . ' div.elementor-widget-container' ] = '<div class="elementor-widget-container">' . $content . '</div>';
				}
				return $element;
			}
		);
		return $fragments;
	}
}
