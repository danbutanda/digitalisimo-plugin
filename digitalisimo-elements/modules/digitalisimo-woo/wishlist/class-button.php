<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

use Digitalisimo\Elements\Woo\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Marcado del botón «Guardar» y del enlace con contador (v1 §5.1 a). Es el mismo para todos los visitantes: el botón
 * sale oculto y con `aria-pressed="false"`, el contador a 0, y el script los hidrata en el navegador (D21). Lo usan el
 * widget y la parte `wishlist` de Productos (frente G).
 */
final class Button {
	const ICON = '<svg class="digi-wish__icon" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false"><path d="M12 20.3 4.6 13a4.7 4.7 0 0 1 6.6-6.7l.8.8.8-.8a4.7 4.7 0 0 1 6.6 6.7z"/></svg>';

	/**
	 * Botón para guardar un producto; '' si el ID no es un producto público (una referencia de kit sin destino llega
	 * con el valor que nunca coincide y no pinta nada).
	 *
	 * @param array $args text (texto visible, «Guardar» por defecto; '' = sólo icono), icon (bool), guest ('browser' o
	 *                    'login': añade el enlace «Inicia sesión para guardar»), class (clases extra).
	 */
	public static function markup( int $product_id, array $args = array() ): string {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) || ! Products::is_public( $product_id ) ) {
			return '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}
		$text  = array_key_exists( 'text', $args ) ? trim( wp_strip_all_tags( (string) $args['text'] ) ) : 'Guardar';
		$name  = trim( wp_strip_all_tags( (string) $product->get_name() ) );
		$label = ( '' === $text ? 'Guardar ' : ' ' ) . $name . ' en la lista de deseos';
		$attrs = ' data-digi-wish="' . (int) $product->get_id() . '"';
		if ( $product->is_type( 'variable' ) ) {
			$attrs .= ' data-variable="1"';
		}
		$class = trim( 'digi-wish ' . preg_replace( '/[^A-Za-z0-9_ -]/', '', (string) ( $args['class'] ?? '' ) ) );
		$html  = '<button type="button" class="' . esc_attr( $class ) . '"' . $attrs . ' aria-pressed="false" hidden>';
		$html .= ( $args['icon'] ?? true ) ? self::ICON : '';
		$html .= '' === $text ? '' : '<span class="digi-wish__text">' . esc_html( $text ) . '</span>';
		$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span></button>';
		// «Pedir iniciar sesión» (por instancia o con el modo invitado apagado en el sitio): un enlace oculto, igual para
		// todos, que el script muestra sólo a un invitado. Con él en la página, ningún botón guarda en el navegador.
		if ( 'login' === ( $args['guest'] ?? '' ) || ( class_exists( Module::class ) && ! Module::guest_storage() ) ) {
			$url = class_exists( Module::class ) ? Module::login_url() : '';
			if ( '' !== $url ) {
				$html .= '<a class="' . esc_attr( $class ) . ' digi-wish--login" href="' . esc_url( $url ) . '" data-digi-wish-login hidden>Inicia sesión para guardar<span class="screen-reader-text"> ' . esc_html( $name ) . ' en la lista de deseos</span></a>';
			}
		}
		return $html;
	}

	/** Enlace a la página de la lista con el número de productos; '' sin página publicada. */
	public static function link( int $page_id, array $args = array() ): string {
		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}
		$url  = get_permalink( $page_id );
		$text = array_key_exists( 'text', $args ) ? trim( wp_strip_all_tags( (string) $args['text'] ) ) : 'Lista de deseos';
		if ( ! $url ) {
			return '';
		}
		$html  = '<a class="digi-wish-link" href="' . esc_url( $url ) . '" data-digi-wish-link>';
		$html .= ( $args['icon'] ?? true ) ? self::ICON : '';
		$html .= '' === $text ? '' : '<span class="digi-wish__text">' . esc_html( $text ) . '</span> ';
		$html .= '<span class="digi-wish-count" data-digi-wish-count>0</span><span class="screen-reader-text"> productos' . ( '' === $text ? ' en la lista de deseos' : '' ) . '</span></a>';
		return $html;
	}

	/** El producto del bucle o de la ficha en curso; 0 si no hay. */
	public static function current_product(): int {
		$id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $id && 'product' === get_post_type( $id ) ) {
			return $id;
		}
		global $product;
		return is_object( $product ) && method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
	}
}
