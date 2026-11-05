<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Una sola regla de «producto público» para la lista de deseos, la vista rápida y los filtros (§4.3).
 *
 * Público = entrada `product` publicada, sin contraseña y visible en el catálogo (`WC_Product::is_visible()`, que ya
 * aplica la visibilidad, la papelera y «Ocultar productos agotados»), o una `product_variation` publicada cuyo padre
 * cumple lo mismo. Cualquier otro ID se omite (listas) o da 404 (vista rápida) sin decir por qué.
 */
final class Products {
	/** Tope de IDs por petición (D5, V.4.3). */
	const MAX_IDS = 100;

	/** ¿Puede un visitante ver este producto o esta variación? */
	public static function is_public( int $id ): bool {
		if ( $id <= 0 || ! function_exists( 'get_post' ) || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		$post = get_post( $id );
		if ( ! is_object( $post ) ) {
			return false;
		}
		$visible = $id;
		if ( 'product_variation' === ( isset( $post->post_type ) ? $post->post_type : '' ) ) {
			if ( 'publish' !== ( isset( $post->post_status ) ? $post->post_status : '' ) ) {
				return false;
			}
			$visible = (int) ( isset( $post->post_parent ) ? $post->post_parent : 0 );
			$post    = $visible > 0 ? get_post( $visible ) : null;
		}
		if ( ! self::public_post( $post ) ) {
			return false;
		}
		$product = wc_get_product( $visible );
		return is_object( $product ) && method_exists( $product, 'is_visible' ) && (bool) $product->is_visible();
	}

	/**
	 * Lista de IDs válidos: enteros positivos, sin repetir, como mucho `$max`, y sólo los públicos. Conserva el orden.
	 *
	 * @param mixed $ids Lo recibido (lista de números o cadenas numéricas).
	 * @return int[]
	 */
	public static function filter_public( $ids, int $max = self::MAX_IDS ): array {
		$clean = self::ids( $ids, $max );
		self::prime( $clean );
		return array_values( array_filter( $clean, array( __CLASS__, 'is_public' ) ) );
	}

	/**
	 * Enteros positivos sin repetir, como mucho `$max` (sin comprobar si son públicos).
	 *
	 * @param mixed $ids Lo recibido.
	 * @return int[]
	 */
	public static function ids( $ids, int $max = self::MAX_IDS ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( is_int( $id ) || ( is_string( $id ) && preg_match( '/^[1-9][0-9]{0,18}$/', $id ) ) ) {
				$id = (int) $id;
				if ( $id > 0 && ! isset( $out[ $id ] ) ) {
					$out[ $id ] = $id;
				}
			}
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return array_values( $out );
	}

	/**
	 * Precarga entradas y metas de una lista de productos (V.4.3: `_prime_post_caches()` y `update_meta_cache()`).
	 *
	 * @param int[] $ids IDs ya saneados.
	 */
	public static function prime( array $ids ): void {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return;
		}
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, true ); // Entradas y sus metas (`update_meta_cache()`), en dos consultas.
		} elseif ( function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $ids );
		}
	}

	/** Entrada `product` publicada y sin contraseña. */
	private static function public_post( $post ): bool {
		return is_object( $post )
			&& 'product' === ( isset( $post->post_type ) ? $post->post_type : '' )
			&& 'publish' === ( isset( $post->post_status ) ? $post->post_status : '' )
			&& '' === (string) ( isset( $post->post_password ) ? $post->post_password : '' );
	}
}
