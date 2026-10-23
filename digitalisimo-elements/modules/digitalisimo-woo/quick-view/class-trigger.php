<?php
namespace Digitalisimo\Elements\Woo\Quick_View;

use Digitalisimo\Elements\Woo\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Botón de la vista rápida: un enlace a la ficha del producto con lo que necesita el script (V.5.2 y §5.2 a de la v1).
 *
 * `<a class="digi-qv__open" href="{ficha}" data-digi-qv="{producto}" data-post="{documento}" data-widget="{elemento}"
 * aria-haspopup="dialog">Vista rápida<span class="screen-reader-text"> de {nombre}</span></a>`
 *
 * - El HTML es igual para todos los visitantes: no lleva nonce, usuario, carrito ni precio.
 * - `data-post` es el documento que contiene el widget (dentro de un bucle, su plantilla): el que la ruta revisa.
 * - `data-variable` deja abrir la ficha sin pedir nada si la página no puede cargar el formulario de variaciones;
 *   `data-digi-qv-template`, que el contenido es una plantilla (trae sus propios scripts).
 * - Es el renderizador que usan el widget `digitalisimo-woo-quick-view` y la parte `quickview` de Productos (frente G).
 */
final class Trigger {
	const DEFAULT_TEXT = 'Vista rápida';

	/**
	 * @param int   $product_id Producto.
	 * @param array $args       post (documento), widget (ID del elemento), text, icon (HTML ya escapado del icono),
	 *                          template (bool, modo «Plantilla de producto»).
	 * @return string '' si el producto no es público.
	 */
	public static function markup( int $product_id, array $args = array() ): string {
		if ( ! Products::is_public( $product_id ) || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variation' ) ) {
			return '';
		}
		$widget = isset( $args['widget'] ) ? (string) $args['widget'] : '';
		$post   = isset( $args['post'] ) ? (int) $args['post'] : 0;
		if ( $post <= 0 || ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $widget ) ) {
			return '';
		}
		$text = isset( $args['text'] ) ? trim( (string) $args['text'] ) : '';
		$text = '' !== $text ? $text : self::DEFAULT_TEXT;
		$icon = isset( $args['icon'] ) ? (string) $args['icon'] : '';
		$html = '<a class="digi-qv__open" href="' . esc_url( $product->get_permalink() ) . '" data-digi-qv="' . (int) $product->get_id() . '" data-post="' . $post . '" data-widget="' . esc_attr( $widget ) . '" aria-haspopup="dialog"'
			. ( $product->is_type( 'variable' ) ? ' data-variable' : '' )
			. ( ! empty( $args['template'] ) ? ' data-digi-qv-template' : '' ) . '>';
		if ( '' !== $icon ) {
			$html .= '<span class="digi-qv__icon" aria-hidden="true">' . $icon . '</span>';
		}
		return $html . '<span class="digi-qv__text">' . esc_html( $text ) . '</span><span class="screen-reader-text"> de ' . esc_html( wp_strip_all_tags( (string) $product->get_name() ) ) . '</span></a>';
	}

	/** Producto actual: el del bucle o el de la ficha; 0 si no hay. */
	public static function current_product(): int {
		global $product;
		if ( $product instanceof \WC_Product ) {
			return (int) $product->get_id();
		}
		$id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		return $id > 0 && 'product' === get_post_type( $id ) ? $id : 0;
	}

	/** Documento que contiene el elemento que se está pintando (en un bucle, su plantilla). */
	public static function document_id(): int {
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			$document = \Elementor\Plugin::$instance->documents->get_current();
			if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
				return (int) $document->get_main_id();
			}
		}
		return function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	}
}
