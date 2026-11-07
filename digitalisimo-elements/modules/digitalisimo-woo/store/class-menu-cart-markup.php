<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Marcado y fragmentos del Carrito del menú propio (`digitalisimo-woo-menu-cart`, C7 frente N/O). Deriva del render del
 * Menú del carrito de PRO (`modules/woocommerce/module.php`: `render_menu_cart()`, `render_menu_cart_toggle_button()`,
 * `render_menu_cart_close_button()`, `e_cart_count_fragments()`) y de su plantilla del mini carrito
 * (`modules/woocommerce/wc-templates/cart/mini-cart.php`), con clases propias y sin cargar nada de PRO.
 *
 * - **Fragmentos de WooCommerce** (`woocommerce_add_to_cart_fragments`): el subtotal y el contador del botón
 *   (`.digi-mc__btn …`) y el contenido del panel (`div.digi-mc__ct`). El panel sale vacío del
 *   servidor (como el de PRO, apto para la caché de página) y lo llena `wc-cart-fragments`. El contenido del panel sólo
 *   se arma si el sitio usa el widget (opción `digitalisimo_woo_menu_cart`, que el widget anota al pintarse).
 * - **El mini carrito** sigue los filtros y acciones de WooCommerce que sigue la plantilla de PRO (producto, enlace,
 *   miniatura, nombre, datos, cantidad, enlace de quitar, `woocommerce_before_mini_cart_contents`,
 *   `woocommerce_mini_cart_contents`). Si quien administra desactivó la plantilla de mini carrito del constructor
 *   (opción de PRO `elementor_use_mini_cart_template` = `no`), se usa la del tema o de WooCommerce (`woocommerce_mini_cart()`).
 * - **Accesible:** el conmutador es un `<button>` con `aria-expanded` y `aria-controls`; el panel cerrado lleva `inert`
 *   (nada enfocable a la vista de nadie) y es un diálogo con nombre; cerrar es un `<button>` con nombre; quitar un
 *   artículo nombra el producto. El script `store/assets/menu-cart.js` abre, cierra (clic fuera, Escape con el foco
 *   devuelto), abre al pasar el cursor y al añadir un producto, y ajusta la altura del mini carrito.
 */
final class Menu_Cart_Markup {
	/** Opción que anota que el sitio usa el widget (activa el fragmento del panel). */
	const OPTION = 'digitalisimo_woo_menu_cart';

	/** Engancha los fragmentos. Lo llama `Cart\Mini_Cart_Extras::init()` con WooCommerce (también en `?wc-ajax=`). */
	public static function init(): void {
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
	}

	/** Fragmentos del botón (siempre) y del panel (si el sitio usa el widget). */
	public static function fragments( $fragments ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $fragments;
		}
		$fragments = (array) $fragments;
		$count     = (int) WC()->cart->get_cart_contents_count();
		$fragments['.digi-mc__btn span.elementor-button-text']     = '<span class="elementor-button-text">' . WC()->cart->get_cart_subtotal() . '</span>';
		$fragments['.digi-mc__btn span.elementor-button-icon-qty'] = '<span class="elementor-button-icon-qty" data-counter="' . $count . '">' . $count . '</span>';
		if ( 'yes' === get_option( self::OPTION ) ) {
			$fragments['div.digi-mc__ct'] = '<div class="digi-mc__ct">' . self::mini_cart() . '</div>';
		}
		return $fragments;
	}

	/** Icono del conmutador: el elegido (biblioteca del núcleo) o el personalizado (`menu_icon_svg`), como `render_menu_icon()` de PRO. */
	public static function icon( array $settings ): string {
		$icon = ! empty( $settings['icon'] ) ? (string) $settings['icon'] : 'cart-medium';
		if ( 'custom' === $icon ) {
			// Sin icono elegido, el carrito de Font Awesome, como PRO.
			$custom = ! empty( $settings['menu_icon_svg']['value'] ) ? $settings['menu_icon_svg'] : array( 'value' => 'fas fa-shopping-cart', 'library' => 'fa-solid' );
			ob_start();
			Icons_Manager::render_icon( $custom, array( 'class' => 'digi-mc__ci', 'aria-hidden' => 'true' ) );
			return (string) ob_get_clean();
		}
		// Los mismos iconos que PRO: los de la biblioteca del núcleo (con «iconos en SVG», un SVG en línea).
		ob_start();
		Icons_Manager::render_icon( array( 'library' => 'eicons', 'value' => 'eicon-' . preg_replace( '/[^a-z-]/', '', $icon ) ) );
		return (string) ob_get_clean();
	}

	/** Botón que abre el carrito (subtotal, icono y contador). */
	public static function toggle( array $settings, string $panel_id ): string {
		$count    = (int) WC()->cart->get_cart_contents_count();
		$subtotal = WC()->cart->get_cart_subtotal();
		return '<div class="digi-mc__tg elementor-button-wrapper">'
			. '<button type="button" class="digi-mc__btn elementor-button elementor-size-sm" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">'
			. '<span class="elementor-button-text">' . $subtotal . '</span>'
			. '<span class="elementor-button-icon">'
			. '<span class="elementor-button-icon-qty" data-counter="' . $count . '">' . $count . '</span>'
			. self::icon( $settings )
			. '<span class="elementor-screen-only">' . esc_html__( 'Carrito', 'pro-elements' ) . '</span>'
			. '</span></button></div>';
	}

	/** Botón de cerrar: el aspa propia o el icono personalizado (`close_cart_icon_svg`). */
	public static function close( array $settings ): string {
		$custom = ! empty( $settings['close_cart_icon_svg']['value'] ) && 'yes' === ( $settings['close_cart_button_show'] ?? '' );
		$icon   = '';
		if ( $custom ) {
			ob_start();
			Icons_Manager::render_icon( $settings['close_cart_icon_svg'], array( 'class' => 'digi-mc__xi', 'aria-hidden' => 'true' ) );
			$icon = (string) ob_get_clean();
		}
		return '<button type="button" class="' . ( $custom ? 'digi-mc__xc' : 'digi-mc__x' ) . '" aria-label="' . esc_attr__( 'Cerrar el carrito', 'pro-elements' ) . '">' . $icon . '</button>';
	}

	/**
	 * Widget completo. `$editor`: en el editor el panel se llena aquí (allí no hay fragmentos).
	 *
	 * @param array  $settings Ajustes para mostrar.
	 * @param string $id       ID del elemento.
	 * @param bool   $pro      Lo convertido de PRO (`Base::from_pro()`): textos fijos de PRO (`digi-mc--pro`).
	 */
	public static function widget( array $settings, string $id, bool $editor = false, bool $added = false, bool $pro = false ): string {
		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			return '';
		}
		$out = '<div class="digi-mc' . ( $pro ? ' digi-mc--pro' : '' ) . '">';
		if ( ! apply_filters( 'woocommerce_widget_cart_is_hidden', false ) ) {
			$panel = 'digi-menu-cart-' . preg_replace( '/[^a-z0-9_-]/i', '', $id );
			$out  .= '<div class="digi-mc__tw" data-digi-menu-cart="' . esc_attr( wp_json_encode( array(
				'open'  => 'mouseover' === ( $settings['open_cart'] ?? '' ) ? 'mouseover' : 'click',
				'auto'  => 'yes' === ( $settings['automatically_open_cart'] ?? '' ),
				'type'  => 'mini-cart' === ( $settings['cart_type'] ?? '' ) ? 'mini-cart' : 'side-cart',
				'added' => $added,
			) ) ) . '">'
				. self::toggle( $settings, $panel )
				. '<div class="digi-mc__box" id="' . esc_attr( $panel ) . '" inert>'
				. '<div class="digi-mc__main" role="dialog" aria-label="' . esc_attr__( 'Carrito', 'pro-elements' ) . '">'
				. self::close( $settings )
				. '<div class="digi-mc__ct">' . ( $editor ? self::mini_cart() : '' ) . '</div>'
				. '</div><!-- /digi-mc__main -->'
				. '</div></div>';
		}
		return $out . '</div>';
	}

	/** ¿Quiere el sitio la plantilla del mini carrito del constructor? (sí, salvo que se desactivara expresamente). */
	public static function own_template(): bool {
		return 'no' !== get_option( 'elementor_use_mini_cart_template', '' );
	}

	/** Contenido del panel: el mini carrito propio, o el del tema o WooCommerce si se desactivó la plantilla. */
	public static function mini_cart(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		ob_start();
		if ( ! self::own_template() ) {
			woocommerce_mini_cart();
			return (string) ob_get_clean();
		}
		$items = WC()->cart->get_cart();
		if ( empty( $items ) ) {
			// El texto de WooCommerce y, si su traducción es otra, el de PRO (su plantilla usa su dominio): el panel llega por
			// los fragmentos, iguales para todos los carritos de la página, así que la hoja muestra el de PRO sólo en lo
			// convertido (`digi-mc--pro`) y el de WooCommerce en lo nuevo.
			$empty = esc_html__( 'No products in the cart.', 'woocommerce' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- texto de WooCommerce.
			$pro   = esc_html__( 'No products in the cart.', 'elementor-pro' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- texto de PRO (wc-templates/cart/mini-cart.php) en lo convertido.
			echo '<div class="woocommerce-mini-cart__empty-message">' . ( $pro === $empty ? $empty : '<span class="digi-mc__em">' . $empty . '</span><span class="digi-mc__em digi-mc__em--pro">' . $pro . '</span>' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapados arriba.
		} else {
			echo '<div class="digi-mc__list woocommerce-mini-cart cart woocommerce-cart-form__contents">';
			do_action( 'woocommerce_before_mini_cart_contents' );
			foreach ( $items as $key => $item ) {
				self::item( (string) $key, $item );
			}
			do_action( 'woocommerce_mini_cart_contents' );
			echo '</div>';
			echo '<div class="digi-mc__sub"><strong>' . esc_html__( 'Subtotal', 'woocommerce' ) . ':</strong> ' . WC()->cart->get_cart_subtotal() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.TextDomainMismatch -- HTML de WooCommerce.
			echo '<div class="digi-mc__btns">'
				. '<a href="' . esc_url( wc_get_cart_url() ) . '" class="digi-mc__view elementor-button elementor-size-md"><span class="elementor-button-text">' . esc_html__( 'View cart', 'woocommerce' ) . '</span></a>' // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				. '<a href="' . esc_url( wc_get_checkout_url() ) . '" class="digi-mc__pay elementor-button elementor-size-md"><span class="elementor-button-text">' . esc_html__( 'Checkout', 'woocommerce' ) . '</span></a>' // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				. '</div>';
		}
		return (string) ob_get_clean();
	}

	/** Una línea del mini carrito, con los filtros de WooCommerce de la plantilla de PRO. */
	private static function item( string $key, array $item ): void {
		$product = apply_filters( 'woocommerce_cart_item_product', $item['data'], $item, $key );
		if ( ! $product || ! $product->exists() || $item['quantity'] <= 0 || ! apply_filters( 'woocommerce_widget_cart_item_visible', true, $item, $key ) ) {
			return;
		}
		$product_id = apply_filters( 'woocommerce_cart_item_product_id', $item['product_id'], $item, $key );
		$price      = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $product ), $item, $key );
		$link       = apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $item ) : '', $item, $key );
		$thumbnail  = apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image(), $item, $key );
		echo '<div class="digi-mc__item woocommerce-cart-form__cart-item ' . esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $item, $key ) ) . '">';
		echo '<div class="digi-mc__img product-thumbnail">' . ( $link ? '<a href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . wp_kses_post( $thumbnail ) . '</a>' : wp_kses_post( $thumbnail ) ) . '</div>';
		echo '<div class="digi-mc__name product-name" data-title="' . esc_attr__( 'Producto', 'pro-elements' ) . '">';
		if ( ! $link ) {
			echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $item, $key ) . '&nbsp;' );
		} else {
			echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $link ), $product->get_name() ), $item, $key ) );
		}
		do_action( 'woocommerce_after_cart_item_name', $item, $key );
		echo wc_get_formatted_cart_item_data( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de WooCommerce.
		echo '</div>';
		echo '<div class="digi-mc__price product-price" data-title="' . esc_attr__( 'Precio', 'pro-elements' ) . '">';
		echo apply_filters( 'woocommerce_widget_cart_item_quantity', '<span class="quantity">' . sprintf( '<span class="product-quantity">%s &times;</span> %s', $item['quantity'], $price ) . '</span>', $item, $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de WooCommerce.
		echo '</div>';
		/* translators: %s: nombre del producto. */
		$label = sprintf( __( 'Quitar «%s» del carrito', 'pro-elements' ), wp_strip_all_tags( $product->get_name() ) );
		echo '<div class="digi-mc__rm product-remove">';
		foreach ( array( 'digi-mc__rml', 'remove_from_cart_button' ) as $class ) {
			echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- enlace escapado aquí; el filtro es de WooCommerce.
				'woocommerce_cart_item_remove_link',
				sprintf(
					'<a href="%s" class="%s" aria-label="%s" data-product_id="%s" data-cart_item_key="%s" data-product_sku="%s"></a>',
					esc_url( wc_get_cart_remove_url( $key ) ),
					esc_attr( $class ),
					esc_attr( $label ),
					esc_attr( $product_id ),
					esc_attr( $key ),
					esc_attr( $product->get_sku() )
				),
				$key
			);
		}
		echo '</div></div>';
	}
}
