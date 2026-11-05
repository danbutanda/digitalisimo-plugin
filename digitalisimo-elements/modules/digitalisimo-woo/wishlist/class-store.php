<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

use Digitalisimo\Elements\Woo\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Almacén propio de la lista de deseos (D5): la meta de usuario `{prefijo del sitio}digitalisimo_wishlist`, la misma
 * clave que `update_user_option()`, así cada sitio de una red tiene la suya. Forma `{ v: 1, items: [ [ id, alta ] ] }`
 * en orden de alta, con un tope (`digitalisimo_elements_wishlist_limit`, 1–500, 100 por defecto).
 *
 * Los IDs se validan al escribir con `Products::is_public()` (la regla única de F4-Woo): lo que dejó de ser público se
 * purga en la escritura siguiente. La opción `digitalisimo_elements_wishlist_legacy` cuenta los usuarios del sitio con
 * datos propios: con la lista de WooCommerce activa, mientras no llegue a 0 sigue el exportador propio (V.4.1).
 */
final class Store {
	const META   = 'digitalisimo_wishlist';
	const LEGACY = 'digitalisimo_elements_wishlist_legacy';
	const LIMIT  = 100;

	/** Tope de productos por lista. */
	public static function limit(): int {
		$limit = (int) apply_filters( 'digitalisimo_elements_wishlist_limit', self::LIMIT );
		return max( 1, min( 500, $limit ) );
	}

	/** Clave de la meta en un sitio; sin sitio, el actual. */
	public static function key( int $blog_id = 0 ): string {
		global $wpdb;
		return $wpdb->get_blog_prefix( $blog_id ? $blog_id : null ) . self::META;
	}

	/**
	 * Lista guardada del usuario en este sitio, sin validar: array( id => alta en segundos ), en orden de alta.
	 *
	 * @return array<int, int>
	 */
	public static function read( int $user_id ): array {
		return $user_id ? self::normalize( get_user_meta( $user_id, self::key(), true ) ) : array();
	}

	/** El usuario tiene datos propios en este sitio. */
	public static function has_data( int $user_id ): bool {
		return (bool) self::read( $user_id );
	}

	/**
	 * Forma canónica de un valor guardado; descarta todo lo que no es `[ id, alta ]`.
	 *
	 * @return array<int, int>
	 */
	public static function normalize( $raw ): array {
		$items = array();
		if ( ! is_array( $raw ) || ! isset( $raw['items'] ) || ! is_array( $raw['items'] ) ) {
			return $items;
		}
		foreach ( $raw['items'] as $row ) {
			if ( ! is_array( $row ) || ! isset( $row[0] ) ) {
				continue;
			}
			$id = absint( $row[0] );
			if ( $id && ! isset( $items[ $id ] ) ) {
				$items[ $id ] = absint( $row[1] ?? 0 );
			}
		}
		return $items;
	}

	/**
	 * Sólo los productos públicos, en orden de alta y dentro del tope (se quedan los más recientes).
	 *
	 * @param array<int, int> $items
	 * @return array<int, int>
	 */
	public static function valid( array $items ): array {
		Products::prime( array_keys( $items ) );
		$out = array();
		foreach ( $items as $id => $added ) {
			if ( Products::is_public( (int) $id ) ) {
				$out[ (int) $id ] = (int) $added;
			}
		}
		$limit = self::limit();
		return count( $out ) > $limit ? array_slice( $out, -$limit, null, true ) : $out;
	}

	/**
	 * Guarda la lista (ya validada por quien llama, o se valida aquí). Una lista vacía borra la meta: no queda nada del
	 * usuario. El contador de listas propias se recalcula sólo cuando la meta aparece o desaparece.
	 *
	 * @param array<int, int> $items
	 * @return array<int, int> Lo guardado.
	 */
	public static function write( int $user_id, array $items, bool $validate = true ): array {
		if ( ! $user_id ) {
			return array();
		}
		$items = $validate ? self::valid( $items ) : $items;
		$key   = self::key();
		$had   = ! in_array( get_user_meta( $user_id, $key, true ), array( '', false ), true );
		if ( ! $items ) {
			delete_user_meta( $user_id, $key );
		} else {
			$rows = array();
			foreach ( $items as $id => $added ) {
				$rows[] = array( (int) $id, (int) $added );
			}
			update_user_meta( $user_id, $key, array( 'v' => 1, 'items' => $rows ) );
		}
		if ( $had !== (bool) $items ) {
			self::recount();
		}
		return $items;
	}

	/** Borra la lista del usuario en este sitio. Devuelve si había algo. */
	public static function clear( int $user_id ): bool {
		$had = self::has_data( $user_id );
		self::write( $user_id, array(), false );
		return $had;
	}

	/** Número de listas propias del sitio; se calcula una vez si la opción aún no existe. */
	public static function legacy_count(): int {
		$value = get_option( self::LEGACY, null );
		return null === $value || false === $value ? self::recount() : max( 0, (int) $value );
	}

	/** Cuenta las listas propias de un sitio con una sola consulta y lo guarda en su opción (carga automática). */
	public static function recount( int $blog_id = 0 ): int {
		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", self::key( $blog_id ) ) );
		if ( $blog_id && function_exists( 'is_multisite' ) && is_multisite() && $blog_id !== (int) get_current_blog_id() ) {
			update_blog_option( $blog_id, self::LEGACY, $count );
		} else {
			update_option( self::LEGACY, $count, true );
		}
		return $count;
	}
}
