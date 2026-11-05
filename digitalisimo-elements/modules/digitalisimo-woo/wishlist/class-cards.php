<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

use Digitalisimo\Elements\Image_Markup;
use Digitalisimo\Elements\Woo\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Fichas del widget «Lista de deseos», que el navegador pide por `POST woo/cards` con los ajustes **guardados** de esa
 * instancia (nunca los de la petición). Datos reales de WooCommerce: precio de `get_price_html()`, disponibilidad de
 * `wc_get_stock_html()` y el botón nativo del bucle (`woocommerce_template_loop_add_to_cart()`: «Añadir al carrito», o
 * «Elegir opciones» en un variable). Sin datos del visitante salvo los productos que él mismo envió.
 */
final class Cards {
	const PARTS       = array( 'image', 'title', 'price', 'stock', 'cart', 'remove' );
	const TAGS        = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' );
	const IMAGE_SIZES = array( 'woocommerce_thumbnail', 'woocommerce_gallery_thumbnail', 'thumbnail', 'medium' );
	const DEFAULTS    = array(
		'parts'      => self::PARTS,
		'item_tag'   => 'h3',
		'image_size' => 'woocommerce_thumbnail',
	);

	/** Ajustes de una instancia con los valores por defecto y validados contra listas cerradas. */
	public static function settings( array $raw ): array {
		$parts = isset( $raw['parts'] ) && is_array( $raw['parts'] ) ? array_values( array_intersect( self::PARTS, $raw['parts'] ) ) : self::DEFAULTS['parts'];
		$tag   = (string) ( $raw['item_tag'] ?? '' );
		$size  = (string) ( $raw['image_size'] ?? '' );
		return array(
			'parts'      => $parts,
			'item_tag'   => in_array( $tag, self::TAGS, true ) ? $tag : self::DEFAULTS['item_tag'],
			'image_size' => in_array( $size, self::IMAGE_SIZES, true ) ? $size : self::DEFAULTS['image_size'],
		);
	}

	/**
	 * Fichas de los productos que siguen siendo públicos, en el orden pedido.
	 *
	 * @param int[] $ids
	 * @return array{html: string, ids: int[]}
	 */
	public static function render( array $settings, array $ids ): array {
		$settings = self::settings( $settings );
		$ids      = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( $ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids );
		}
		if ( $ids && function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $ids );
		}
		$html = '';
		$done = array();
		foreach ( $ids as $id ) {
			if ( ! Products::is_public( $id ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$html  .= self::card( $product, $settings );
			$done[] = $id;
		}
		return array( 'html' => $html, 'ids' => $done );
	}

	private static function card( $product, array $s ): string {
		$id    = (int) $product->get_id();
		$name  = trim( wp_strip_all_tags( (string) $product->get_name() ) );
		$url   = (string) $product->get_permalink();
		$parts = array_flip( $s['parts'] );
		$html  = '<li class="digi-wishlist__item" data-digi-wish-item="' . $id . '">';
		if ( isset( $parts['image'] ) ) {
			// En `POST woo/cards` no se registran los widgets propios, que son quienes cargan el generador de imágenes.
			if ( ! class_exists( Image_Markup::class ) ) {
				require_once dirname( __DIR__, 2 ) . '/digitalisimo-widgets/class-image-markup.php';
			}
			$image = Image_Markup::img( array( 'id' => (int) $product->get_image_id() ), array( 'size' => $s['image_size'], 'decorative' => true, 'class' => 'digi-wishlist__img' ) );
			if ( '' !== $image ) {
				$html .= '<a class="digi-wishlist__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $image . '</a>';
			}
		}
		$html .= '<div class="digi-wishlist__body">';
		if ( isset( $parts['title'] ) ) {
			$html .= '<' . $s['item_tag'] . ' class="digi-wishlist__name"><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></' . $s['item_tag'] . '>';
		}
		if ( isset( $parts['price'] ) ) {
			$price = (string) $product->get_price_html();
			$html .= '' === $price ? '' : '<div class="digi-wishlist__price">' . wp_kses_post( $price ) . '</div>';
		}
		if ( isset( $parts['stock'] ) && function_exists( 'wc_get_stock_html' ) ) {
			$html .= wp_kses_post( (string) wc_get_stock_html( $product ) );
		}
		if ( isset( $parts['cart'] ) ) {
			$html .= self::cart_button( $product );
		}
		if ( isset( $parts['remove'] ) ) {
			$html .= '<button type="button" class="digi-wishlist__remove" data-digi-wish-remove="' . $id . '">Quitar<span class="screen-reader-text"> ' . esc_html( $name ) . '</span></button>';
		}
		return $html . '</div></li>';
	}

	/** El botón nativo del bucle de WooCommerce con el producto como global, restaurado al terminar. */
	private static function cart_button( $product ): string {
		if ( ! function_exists( 'woocommerce_template_loop_add_to_cart' ) ) {
			return '';
		}
		global $post;
		$previous_product = $GLOBALS['product'] ?? null;
		$previous_post    = $post;
		$GLOBALS['product'] = $product;
		$post               = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		ob_start();
		woocommerce_template_loop_add_to_cart();
		$button = (string) ob_get_clean();
		$GLOBALS['product'] = $previous_product;
		$post               = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		return '' === trim( $button ) ? '' : '<div class="digi-wishlist__cart">' . $button . '</div>';
	}
}
