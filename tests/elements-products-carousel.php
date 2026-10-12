<?php
/** Presentaciones en carrusel y en tabla del widget Productos, y título del panel del carrito. */
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_carousel( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-products-parts.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-cart-title.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-products-table.php';
	use Digitalisimo\Elements\Products_Table;
	use Digitalisimo\Elements\Cart_Title;
	use Digitalisimo\Elements\Products_Parts;

	$items = '<li class="product type-product post-1"><a href="/p1">Uno</a><ul class="variations"><li>x</li></ul></li><li class="product type-product post-2"><a href="/p2">Dos</a></li><li class="product type-product post-3"><a href="/p3">Tres</a></li>';
	$html  = '<div class="woocommerce columns-3 "><ul class="products elementor-grid columns-3">' . $items . '</ul><nav class="woocommerce-pagination"><ul class="page-numbers"><li>1</li></ul></nav></div>';
	$out   = Products_Parts::carousel( $html, array( 'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '1', 'digitalisimo_wc_navigation' => 'yes' ) );
	check_carousel( null !== $out && str_starts_with( $out, '<div class="woocommerce columns-3 "><section class="digi-carousel digi-products-carousel" role="region" aria-roledescription="carrusel" aria-label="Productos" style="--digi-carousel-count:3;--digi-products-visible:3;--digi-products-visible-tablet:2;--digi-products-visible-mobile:1"><div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="products digi-carousel__slides elementor-grid columns-3">' ), 'La lista entra en la región y la ventana del carrusel con las columnas de cada dispositivo.' );
	check_carousel( str_contains( $out, '</ul></div><div class="digi-carousel__controls"><button class="digi-carousel__button" type="button" data-digi-carousel-prev' ) && str_contains( $out, '</section><nav class="woocommerce-pagination"><ul class="page-numbers">' ), 'La lista se cierra antes de las flechas, con listas anidadas dentro y la paginación fuera.' );
	$quiet = Products_Parts::carousel( $html, array( 'digitalisimo_wc_navigation' => '' ) );
	check_carousel( ! str_contains( $quiet, 'digi-carousel__controls' ) && str_contains( $quiet, 'style="--digi-carousel-count:3"' ), 'Sin flechas sólo queda el desplazamiento.' );
	check_carousel( null === Products_Parts::carousel( '<p class="woocommerce-info">No products were found matching your selection.</p>', array() ), 'Sin lista de productos el contenido queda igual.' );
	$panel = '<div class="elementor-menu-cart__main" aria-hidden="true"><div class="elementor-menu-cart__close-button"></div><div class="widget_shopping_cart_content"></div></div>';
	check_carousel( '<div class="elementor-menu-cart__main" aria-hidden="true"><div class="elementor-menu-cart__close-button"></div><p class="digi-cart-title">Tu carrito $1</p><div class="widget_shopping_cart_content"></div></div>' === Cart_Title::insert( $panel, 'Tu carrito <b>$1</b>' ) && $panel === Cart_Title::insert( $panel, '  ' ), 'El título del carrito va antes de su contenido, escapado, y sin título nada cambia.' );
	check_carousel( array( 1, 2, 3 ) === Products_Table::ids( $html ) && array() === Products_Table::ids( '<p>Sin productos</p>' ), 'La tabla toma los productos y su orden del HTML del widget.' );
	check_carousel( array( 'image', 'title', 'price', 'cart' ) === Products_Table::parts( array() ) && array( 'image', 'title', 'price', 'cart' ) === Products_Table::parts( array( 'digitalisimo_wc_parts' => array( 'title' ) ) ), 'Sin partes elegidas la tabla usa imagen, producto, precio y carrito.' );
	check_carousel( array( 'title', 'sku', 'quantity' ) === Products_Table::parts( array( 'digitalisimo_wc_parts' => 'title,sku,quantity' ) ) && array( 'title' ) === Products_Table::parts( array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'title' ) ) ), 'Las partes elegidas, en lista o separadas por comas, son las columnas.' );
	check_carousel( '' === Products_Table::html( array(), array() ), 'Sin productos no hay tabla.' );
	echo "DIGITALÍSIMO Elements: carrusel y tabla de productos, y título del carrito validados.\n";
}
