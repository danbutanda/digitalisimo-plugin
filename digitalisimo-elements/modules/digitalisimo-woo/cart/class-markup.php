<?php
namespace Digitalisimo\Elements\Woo\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Marcado de la barra de envío gratis y del cupón (V.7.1, V.7.2).
 *
 * - **Envoltorios persistentes**, en el HTML de la página: iguales para todos los visitantes, ocultos y sin importes ni
 *   nonces. El del cupón lleva el formulario con el `uid` de su instancia y la región `role="status"`; WooCommerce nunca
 *   los reemplaza, así el campo conserva el foco y lo escrito.
 * - **Nodos de estado** (`div.digi-ship__state`, `div.digi-coupon__state`): los reemplaza WooCommerce con sus fragmentos
 *   (`$( clave ).replaceWith( valor )`) y son iguales para todas las instancias y páginas, porque el refresco no sabe qué
 *   página lo pide. Sin `id` y sin formularios dentro; los nonces del cupón sólo viajan aquí.
 */
final class Markup {
	const T_PROGRESS = 'Te faltan {restante} para el envío gratis';
	const T_DONE     = 'Tienes envío gratis';

	/** Envoltorio de la barra con los textos de la instancia; el estado llega por fragmentos (o ya calculado en el editor). */
	public static function bar( string $progress = '', string $done = '', bool $editor = false ): string {
		$progress = '' !== trim( $progress ) ? trim( wp_strip_all_tags( $progress ) ) : self::T_PROGRESS;
		$done     = '' !== trim( $done ) ? trim( wp_strip_all_tags( $done ) ) : self::T_DONE;
		$html     = '<div class="digi-ship" data-digi-ship data-t-progress="' . esc_attr( $progress ) . '" data-t-done="' . esc_attr( $done ) . '"' . ( $editor ? '' : ' hidden' ) . '>';
		if ( $editor ) {
			$state  = State::compute();
			$html  .= 'none' === $state['state'] ? '<div class="digi-ship__state" data-state="none"><p class="digi-ship__text digi-ship__editor">La barra aparece cuando WooCommerce puede calcular el envío gratis del carrito del visitante.</p></div>' : self::ship_state( $state );
		} else {
			$html .= '<div class="digi-ship__state" data-digi-initial hidden></div>';
		}
		return $html . '</div>';
	}

	/** Envoltorio del cupón: formulario, nodo de estado y región viva, con el `uid` de la instancia. */
	public static function coupon( string $uid, bool $editor = false ): string {
		$uid  = 'digi-coupon-' . preg_replace( '/[^a-z0-9-]/', '', strtolower( $uid ) );
		$html = '<div class="digi-coupon" data-digi-coupon' . ( $editor ? '' : ' hidden' ) . '>';
		$html .= '<form class="digi-coupon__form" novalidate><label class="digi-coupon__label" for="' . esc_attr( $uid ) . '">Código de cupón</label>';
		$html .= '<span class="digi-coupon__row"><input id="' . esc_attr( $uid ) . '" class="digi-coupon__input" type="text" name="coupon_code" autocomplete="off" required>';
		$html .= '<button type="submit" class="digi-coupon__apply">Aplicar</button></span></form>';
		$html .= $editor ? self::coupon_state( State::compute(), false ) : '<div class="digi-coupon__state" data-digi-initial hidden></div>';
		return $html . '<p class="digi-coupon__status" role="status"></p></div>';
	}

	/** Fragmento `div.digi-ship__state`. */
	public static function ship_state( array $state ): string {
		$name = (string) ( $state['state'] ?? 'none' );
		if ( 'progress' === $name && '' !== (string) ( $state['remaining_html'] ?? '' ) ) {
			$remaining = (string) $state['remaining_html'];
			$text      = str_replace( '{restante}', $remaining, esc_html( self::T_PROGRESS ) );
			return '<div class="digi-ship__state" data-state="progress" data-remaining="' . esc_attr( self::plain( $remaining ) ) . '" data-threshold="' . esc_attr( self::plain( (string) ( $state['threshold_html'] ?? '' ) ) ) . '" data-percent="' . (int) ( $state['percent'] ?? 0 ) . '"><p class="digi-ship__text">' . wp_kses_post( $text ) . '</p>' . self::track() . '</div>';
		}
		if ( 'done' === $name ) {
			return '<div class="digi-ship__state" data-state="done" data-percent="100"><p class="digi-ship__text">' . esc_html( self::T_DONE ) . '</p>' . self::track() . '</div>';
		}
		return '<div class="digi-ship__state" data-state="none" hidden></div>';
	}

	/**
	 * Fragmento `div.digi-coupon__state`: los cupones aplicados con su «Quitar cupón {código}» y los nonces de
	 * `apply_coupon` y `remove_coupon`. Vacío con los cupones apagados o el carrito vacío.
	 */
	public static function coupon_state( array $state, bool $hidden_when_empty = true ): string {
		if ( empty( $state['coupons_enabled'] ) ) {
			return '<div class="digi-coupon__state"' . ( $hidden_when_empty ? ' hidden' : '' ) . '></div>';
		}
		$html = '<div class="digi-coupon__state" data-apply="' . esc_attr( wp_create_nonce( 'apply-coupon' ) ) . '" data-remove="' . esc_attr( wp_create_nonce( 'remove-coupon' ) ) . '">';
		$codes = (array) ( $state['coupons'] ?? array() );
		if ( $codes ) {
			$html .= '<ul class="digi-coupon__list">';
			foreach ( $codes as $code ) {
				$code  = (string) $code;
				$html .= '<li><span class="digi-coupon__code">' . esc_html( $code ) . '</span> <button type="button" class="digi-coupon__remove" data-coupon="' . esc_attr( $code ) . '">Quitar<span class="screen-reader-text"> cupón ' . esc_html( $code ) . '</span></button></li>';
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}

	private static function track(): string {
		return '<div class="digi-ship__bar" aria-hidden="true"><span></span></div>';
	}

	/** Importe de `wc_price()` como texto (para las plantillas de cada instancia). */
	private static function plain( string $html ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}
}
