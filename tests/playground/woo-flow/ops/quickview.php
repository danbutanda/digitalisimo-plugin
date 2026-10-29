<?php
/**
 * Operaciones de sonda del grupo `quickview` de woo-flow.sh (W4, W5 y W11), con `?digi_wf=<op>&token=…`:
 *
 * - `quickview_state`: lo que preparó setup/quickview.php visto desde el sitio: la plantilla (tipo, sin condiciones, que
 *   no se aplica a ninguna ficha), las páginas con su widget, las imágenes de la Camiseta y de las variaciones, y si el
 *   script de variaciones de WooCommerce está registrado (la vista rápida de un variable lo necesita).
 */

defined( 'ABSPATH' ) || exit;

return array(
	'quickview_state' => static function () {
		$stored = get_option( 'digi_wf_store' );
		$qv     = is_array( $stored ) && isset( $stored['quickview'] ) ? $stored['quickview'] : array();
		$tpl    = (int) ( $qv['template'] ?? 0 );
		$out    = array(
			'blog'       => get_current_blog_id(),
			'template'   => $tpl ? array(
				'type'       => get_post_meta( $tpl, '_elementor_template_type', true ),
				'status'     => get_post_status( $tpl ),
				'conditions' => get_post_meta( $tpl, '_elementor_conditions', true ),
			) : null,
			'pages'      => array(),
			'products'   => array(),
			'variation'  => function_exists( 'wp_scripts' ) && isset( wp_scripts()->registered['wc-add-to-cart-variation'] ),
			'redirect'   => get_option( 'woocommerce_cart_redirect_after_add' ),
		);
		foreach ( (array) ( $qv['buttons'] ?? array() ) as $slug => $button ) {
			$raw                    = (string) get_post_meta( (int) $button['post'], '_elementor_data', true );
			$out['pages'][ $slug ] = array( 'id' => (int) $button['post'], 'widgets' => substr_count( $raw, '"digitalisimo-woo-quick-view"' ), 'menu_cart' => false !== strpos( $raw, '"woocommerce-menu-cart"' ) );
		}
		foreach ( (array) ( $qv['products'] ?? array() ) as $key => $data ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $data['id'] ) : null;
			if ( ! $product ) {
				continue;
			}
			$entry = array( 'image' => (int) $product->get_image_id(), 'gallery' => array_map( 'intval', $product->get_gallery_image_ids() ) );
			if ( $product->is_type( 'variable' ) ) {
				$entry['variation_images'] = array();
				foreach ( $product->get_children() as $child ) {
					$variation                           = wc_get_product( $child );
					$entry['variation_images'][ $child ] = $variation ? (int) $variation->get_image_id() : 0;
				}
			}
			$out['products'][ $key ] = $entry;
		}
		return $out;
	},
);
