<?php
/**
 * Carrito del menú (`digitalisimo-woo-menu-cart`) y Páginas de la tienda (`digitalisimo-woo-pages`), los widgets propios
 * que sustituyen al «Menu Cart» y a «WooCommerce Pages» de PRO (C7 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato
 * docs/pro/c7.md, fila O), y sus mapas `maps/woocommerce-menu-cart.php` y `maps/wc-elements.php`:
 * - los controles de PRO y los propios cargados a la vez (las clases reales de PRO y de DIGITALÍSIMO sobre un Elementor y
 *   un WordPress mínimos, `tests/elements-theme-elements/stubs.php`): mismo nombre, orden, tipo, valor por defecto
 *   (también por dispositivo), opciones, condiciones, rangos y prefijos; los selectores sólo nombran clases y variables
 *   propias, y cada variable que fijan los controles la usa una hoja (salvo las que PRO tampoco usaba);
 * - marcado accesible (botón con `aria-expanded`/`aria-controls`, panel `inert` con nombre, aspa con nombre), panel vacío
 *   en el front y lleno en el editor, iconos propios, `woocommerce_widget_cart_is_hidden`;
 * - fragmentos de WooCommerce (subtotal, contador y panel, éste sólo con el widget en uso), el mini carrito con los
 *   filtros de WooCommerce y la plantilla del tema si se desactivó la del constructor;
 * - los rasgos del panel (título, barra de envío y cupón) reconocen el widget propio y se insertan en su marcado;
 * - recursos: dos hojas ≤ 4 KB y un script ≤ 8 KB sin jQuery, con foco visible y movimiento reducido, sin el kit, url()
 *   ni colores ajenos a los de PRO; nada vetado por el empaquetado ni marcas ajenas en las frases;
 * - mapas: declaran todos los controles de PRO, sólo nombran controles propios y convierten ajustes reales sin
 *   `pro.unmapped`, con el CSS personalizado (clases y variables) traducido.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Plugin { public static $instance; }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	$GLOBALS['m_wp'] = array( 'options' => array(), 'enqueued' => array(), 'filters' => array(), 'items' => array(), 'did' => 0, 'admin' => false, 'shortcodes' => array(), 'mini' => 0 );
	function get_option( $k, $d = false ) { return $GLOBALS['m_wp']['options'][ $k ] ?? $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['m_wp']['options'][ $k ] = $v; return true; }
	function wp_script_is( $h, $l = 'enqueued' ) { return in_array( $h, $GLOBALS['m_wp']['enqueued'], true ); }
	function wp_style_is( $h, $l = 'enqueued' ) { return false; }
	function wp_enqueue_script( $h ) { $GLOBALS['m_wp']['enqueued'][] = $h; }
	function wp_register_style( ...$a ) { $GLOBALS['m_wp']['styles'][ $a[0] ] = $a; }
	function plugins_url( $p, $f ) { return 'https://sitio.test/' . $p; }
	function did_action( $h ) { return $GLOBALS['m_wp']['did']; }
	function wp_doing_ajax() { return false; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function current_user_can( $c ) { return $GLOBALS['m_wp']['admin']; }
	function add_filter( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['m_wp']['hooks'][ $hook ][] = $cb; return true; }
	function add_action( $hook, $cb, $p = 10, $a = 1 ) { return add_filter( $hook, $cb, $p, $a ); }
	function remove_filter( $hook, $cb, $p = 10 ) { $GLOBALS['m_wp']['removed'][] = $hook; return true; }
	function wc_get_cart_url() { return 'https://sitio.test/carrito/'; }
	function wc_get_checkout_url() { return 'https://sitio.test/pago/'; }
	function wc_get_cart_remove_url( $key ) { return 'https://sitio.test/carrito/?remove_item=' . $key . '&_wpnonce=n'; }
	function wc_get_formatted_cart_item_data( $item ) { return empty( $item['variation'] ) ? '' : '<dl class="variation"><dt>Talla:</dt><dd><p>M</p></dd></dl>'; }
	function woocommerce_mini_cart() { $GLOBALS['m_wp']['mini']++; echo '<ul class="woocommerce-mini-cart">TEMA</ul>'; }
	function wc_setup_product_data( $post ) { return (object) array( 'id' => $post->ID ); }
	function get_post( $id ) { return 7 === (int) $id ? (object) array( 'ID' => 7, 'post_type' => 'product' ) : ( 9 === (int) $id ? (object) array( 'ID' => 9, 'post_type' => 'page' ) : null ); }
	function do_shortcode( $s ) { $GLOBALS['m_wp']['shortcodes'][] = $s; return '[checkout_empty]' === $s || ( ! empty( $GLOBALS['m_wp']['checkout_empty'] ) && 0 === strpos( $s, '[woocommerce_checkout' ) ) ? '<div class="woocommerce"></div>' : '<div class="woocommerce">' . $s . '</div>'; }
	class M_Product {
		public $n, $id, $sku;
		public function __construct( $n, $id, $sku ) { $this->n = $n; $this->id = $id; $this->sku = $sku; }
		public function exists() { return true; }
		public function is_visible() { return true; }
		public function get_permalink( $item = null ) { return 'https://sitio.test/producto/' . $this->id . '/'; }
		public function get_image() { return '<img src="https://sitio.test/p' . $this->id . '.png" alt="">'; }
		public function get_name() { return $this->n; }
		public function get_sku() { return $this->sku; }
	}
	class M_Cart {
		public function get_cart() { return $GLOBALS['m_wp']['items']; }
		public function get_cart_contents_count() { return array_sum( array_column( $GLOBALS['m_wp']['items'], 'quantity' ) ); }
		public function get_cart_subtotal() { return '<span class="woocommerce-Price-amount amount">' . number_format( 25 * $this->get_cart_contents_count(), 2, ',', '.' ) . '&nbsp;€</span>'; }
		public function get_product_price( $p ) { return '<span class="amount">25,00&nbsp;€</span>'; }
	}
	function WC() { static $wc; if ( ! $wc ) { $wc = (object) array( 'cart' => new M_Cart() ); } return $wc; } // phpcs:ignore
	$GLOBALS['h_wp'] = array( 'features' => array( 'e_optimized_markup', 'e_font_icon_svg' ) );
	require __DIR__ . '/elements-theme-elements/stubs.php';
	// apply_filters de stubs.php llama a `$GLOBALS['h_wp']['filters'][hook]`.
	\Elementor\Plugin::$instance = (object) array( 'experiments' => new \ElementorPro\H_Experiments(), 'editor' => new \ElementorPro\H_Mode(), 'preview' => new \ElementorPro\H_Mode() );
	$root = dirname( __DIR__ ) . '/digitalisimo-elements/';
	require_once $root . 'modules/woocommerce/traits/product-id-trait.php';
	require_once $root . 'modules/woocommerce/widgets/base-widget.php';
	require_once $root . 'modules/woocommerce/widgets/menu-cart.php';
	require_once $root . 'modules/woocommerce/widgets/elements.php';
	foreach ( array( 'class-widget.php', 'class-inert.php', 'class-assets.php', 'class-registry.php', 'cart/class-markup.php', 'cart/class-mini-cart-extras.php', 'store/class-base.php', 'store/class-menu-cart-markup.php', 'store/class-menu-cart.php', 'store/class-pages.php' ) as $file ) {
		require_once $root . 'modules/digitalisimo-woo/' . $file;
	}
	require_once $root . 'modules/digitalisimo-widgets/class-cart-title.php';

	use Digitalisimo\Elements\Woo\Store\Menu_Cart;
	use Digitalisimo\Elements\Woo\Store\Menu_Cart_Markup;
	use Digitalisimo\Elements\Woo\Store\Pages;
	use Digitalisimo\Elements\Woo\Registry;

	$checks = 0;
	$check  = static function ( $ok, $message ) use ( &$checks ) {
		if ( ! $ok ) {
			echo "FALLA: $message\n";
			exit( 1 );
		}
		$checks++;
	};

	$pro  = h_widget( \ElementorPro\Modules\Woocommerce\Widgets\Menu_Cart::class );
	$own  = h_widget( Menu_Cart::class );
	$ppro = h_widget( \ElementorPro\Modules\Woocommerce\Widgets\Elements::class );
	$pown = h_widget( Pages::class );
	$map  = require h_path( 'modules/digitalisimo-convert/maps/woocommerce-menu-cart.php' );
	$pmap = require h_path( 'modules/digitalisimo-convert/maps/wc-elements.php' );

	// 1. Nombre, registro y categorías.
	$check( 'digitalisimo-woo-menu-cart' === $own->get_name() && 'Carrito del menú' === $own->get_title() && array( 'theme-elements', 'woocommerce-elements' ) === $own->get_categories() && 'eicon-cart' === $own->get_icon(), 'Carrito del menú: nombre, título, categorías e icono.' );
	$check( 'digitalisimo-woo-pages' === $pown->get_name() && 'Páginas de la tienda' === $pown->get_title() && array( 'woocommerce-elements' ) === $pown->get_categories(), 'Páginas: nombre, título y categoría.' );
	$check( 'Carrito del menú' === Registry::TITLES['digitalisimo-woo-menu-cart'] && 'store/class-menu-cart.php' === Registry::WIDGETS['digitalisimo-woo-menu-cart']['file'] && 'store/class-pages.php' === Registry::WIDGETS['digitalisimo-woo-pages']['file'], 'En el registro de digitalisimo-woo.' );
	$check( false === $own->has_widget_inner_wrapper() && ! method_exists( Pages::class, 'has_widget_inner_wrapper' ) || false === $own->has_widget_inner_wrapper() && ! in_array( 'has_widget_inner_wrapper', get_class_methods( Pages::class ), true ), 'Envoltorio interno como PRO (el carrito, según el marcado optimizado).' );
	$check( array( 'digitalisimo-woo-menu-cart', 'digitalisimo-woo-menu-cart-2' ) === $own->get_style_depends() && array( 'digitalisimo-woo-menu-cart' ) === $own->get_script_depends(), 'Carrito: sus dos hojas y su script.' );
	$check( array( 'digitalisimo-woo-pages' ) === $pown->get_style_depends() && array() === $pown->get_script_depends(), 'Páginas: sólo su hoja.' );

	// 2. Controles uno a uno.
	$skip = array( 'checkout_button_display.default' => true ); // Regla CSS interna con las variables propias.
	$diff = h_compare_widgets( $pro, $own, $skip );
	$check( array() === $diff, "Carrito: controles distintos de los de PRO:\n" . implode( "\n", $diff ) );
	$diff = h_compare_widgets( $ppro, $pown );
	$check( array() === $diff, "Páginas: controles distintos de los de PRO:\n" . implode( "\n", $diff ) );
	$check( 105 === count( $own->controls ) && 17 === count( $own->groups ), 'Carrito: los 105 controles y 17 grupos de PRO (' . count( $own->controls ) . ', ' . count( $own->groups ) . ').' );
	$set_vars = array();
	foreach ( $own->controls + $own->groups as $name => $control ) {
		$rules = array_merge( array_keys( (array) ( $control['selectors'] ?? array() ) ), array_values( (array) ( $control['selectors'] ?? array() ) ), array_values( (array) ( $control['selectors_dictionary'] ?? array() ) ), array( (string) ( $control['selector'] ?? '' ), is_string( $control['default'] ?? null ) ? $control['default'] : '', (string) ( $control['prefix_class'] ?? '' ) ) );
		foreach ( $rules as $rule ) {
			$check( ! preg_match( '/elementor-menu-cart|widget_shopping_cart_content|elementor-button--|e-toggle|e-close/', (string) $rule ), "$name: selector o clase de PRO ($rule)." );
			preg_match_all( '/(?<![\w-])--[a-z][a-z0-9-]*/', (string) $rule, $vars );
			foreach ( $vars[0] as $var ) {
				$check( 0 === strpos( $var, '--mc-' ), "$name: variable que no es propia ($var)." );
				$set_vars[ $var ] = true;
			}
		}
	}
	$css1 = (string) file_get_contents( h_path( 'modules/digitalisimo-woo/store/assets/menu-cart.css' ) );
	$css2 = (string) file_get_contents( h_path( 'modules/digitalisimo-woo/store/assets/menu-cart-2.css' ) );
	$js   = (string) file_get_contents( h_path( 'modules/digitalisimo-woo/store/assets/menu-cart.js' ) );
	$pcss = (string) file_get_contents( h_path( 'modules/digitalisimo-woo/store/assets/pages.css' ) );
	preg_match_all( '/(?<![\w-])--mc-[a-z0-9]+/', preg_replace( '#/\*.*?\*/#s', '', $css1 . $css2 ), $used );
	$unused = array_diff( array_keys( $set_vars ), $used[0] );
	sort( $unused );
	// PRO tampoco usaba en su hoja el ancho de los botones alineados ni las alturas máximas de la lista por disposición.
	$check( array( '--mc-cfbabw', '--mc-pmhm', '--mc-pmhs' ) === array_values( $unused ), 'Cada variable que fijan los controles la usa una hoja: sobran ' . implode( ', ', $unused ) );

	// 3. Recursos.
	foreach ( array( 'menu-cart.css' => $css1, 'menu-cart-2.css' => $css2, 'pages.css' => $pcss ) as $file => $css ) {
		$check( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$file: " . strlen( $css ) . ' B (tope 4 096).' );
		$check( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ), "$file: sin el kit ni url()." );
		preg_match_all( '/#[0-9a-f]{3,8}\b/i', $css, $hex );
		$extra = array_diff( array_unique( array_map( 'strtolower', $hex[0] ) ), array( '#69727d', '#d9534f', '#fff', '#d5d8dc', '#1f2124' ) );
		$check( ! $extra, "$file: sólo los colores por defecto de PRO; sobran " . implode( ', ', $extra ) );
	}
	$check( strlen( $js ) <= 8192 && ! preg_match( '/jQuery|\$\(|elementorProFrontend|ajax|fetch\(/i', preg_replace( '#/\*.*?\*/#s', '', $js ) ), 'Script ≤ 8 KB, sin jQuery ni peticiones propias.' );
	$check( str_contains( $css1, 'prefers-reduced-motion' ) && str_contains( $css1, ':focus-visible{outline:2px solid currentColor;outline-offset:2px}' ) && str_contains( $pcss, 'outline-offset:2px' ), 'Movimiento reducido y foco visible de 2 px con separación de 2 px.' );
	$check( str_contains( $css2, 'var(--mc-ribs,22px)' ) && ! str_contains( $css2, 'var(--mc-ribs,24px)' ), 'Quitar un artículo: 22 × 22 px por defecto, como PRO (--remove-item-button-size).' );
	$vetoed = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-woo/store/class-menu-cart.php', 'modules/digitalisimo-woo/store/class-menu-cart-markup.php', 'modules/digitalisimo-woo/store/class-pages.php', 'modules/digitalisimo-woo/store/assets/menu-cart.js', 'modules/digitalisimo-woo/store/assets/menu-cart.css', 'modules/digitalisimo-woo/store/assets/menu-cart-2.css', 'modules/digitalisimo-woo/store/assets/pages.css', 'modules/digitalisimo-convert/maps/woocommerce-menu-cart.php', 'modules/digitalisimo-convert/maps/wc-elements.php' ) as $file ) {
		$source = (string) file_get_contents( h_path( $file ) );
		$check( ! preg_match( $vetoed, $source ) && ! preg_match( '/[a-z0-9-]+\.(com|io|net)\//i', $source ), "$file: nada vetado por el empaquetado ni dominios." );
		if ( str_ends_with( $file, '.php' ) && ! str_contains( $file, 'maps/' ) ) {
			preg_match_all( "/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/", h_code( h_path( $file ) ), $lits );
			foreach ( $lits[1] as $lit ) {
				$check( ! ( str_contains( $lit, ' ' ) && preg_match( '/\b(Elementor|Divi|Bricks)\b/', $lit ) ), "$file: frase con una marca ajena: $lit" );
			}
			$check( ! preg_match( '/jquery|wp_register_script|ElementorPro\\\\Modules\\\\Woocommerce/i', h_code( h_path( $file ) ) ), "$file: sin jQuery, scripts registrados a mano ni clases del módulo de PRO." );
		}
	}

	// 4. Marcado.
	$GLOBALS['m_wp']['items'] = array(
		'k1' => array( 'data' => new M_Product( 'Producto simple', 11, 'AB-1' ), 'product_id' => 11, 'quantity' => 2 ),
		'k2' => array( 'data' => new M_Product( 'Producto "variable"', 12, 'AB-2' ), 'product_id' => 12, 'quantity' => 1, 'variation' => array( 'talla' => 'M' ) ),
	);
	$own->id = 'abc1234';
	$front   = h_render( $own );
	$check( str_contains( $front, '<button type="button" class="digi-mc__btn elementor-button elementor-size-sm" aria-expanded="false" aria-controls="digi-menu-cart-abc1234">' ) && str_contains( $front, '<span class="elementor-button-icon-qty" data-counter="3">3</span>' ) && str_contains( $front, '75,00&nbsp;€' ) && str_contains( $front, '<span class="elementor-screen-only">Carrito</span>' ), 'Botón con aria-expanded y aria-controls, contador, subtotal y nombre accesible: ' . $front );
	$check( str_contains( $front, '<div class="digi-mc__box" id="digi-menu-cart-abc1234" inert><div class="digi-mc__main" role="dialog" aria-label="Carrito"><button type="button" class="digi-mc__x" aria-label="Cerrar el carrito"></button><div class="digi-mc__ct"></div></div><!-- /digi-mc__main -->' ), 'Panel inerte con nombre, aspa con nombre y contenido vacío en el front (lo llenan los fragmentos).' );
	$check( str_contains( $front, 'eicon-cart-medium' ) && strpos( $front, 'digi-mc__btn' ) < strpos( $front, 'digi-mc__box' ), 'Icono «Carrito medio» de la biblioteca del núcleo (como PRO) y el botón antes del panel (orden del foco).' );
	$check( in_array( 'wc-cart-fragments', $GLOBALS['m_wp']['enqueued'], true ) && 'yes' === get_option( Menu_Cart_Markup::OPTION ), 'Encola los fragmentos de WooCommerce y anota que el widget está en uso.' );
	$cfg = json_decode( html_entity_decode( preg_replace( '/^.*data-digi-menu-cart="([^"]*)".*$/s', '$1', $front ), ENT_QUOTES ), true );
	$check( array( 'open' => 'click', 'auto' => false, 'type' => 'side-cart', 'added' => false ) === $cfg, 'Configuración del script por defecto: ' . json_encode( $cfg ) );
	$GLOBALS['m_wp']['did'] = 1;
	$opts = h_render( $own, array( 'open_cart' => 'mouseover', 'automatically_open_cart' => 'yes', 'cart_type' => 'mini-cart', 'icon' => 'bag-solid', 'close_cart_icon_svg' => array( 'value' => 'fas fa-times', 'library' => 'fa-solid' ) ) );
	$cfg = json_decode( html_entity_decode( preg_replace( '/^.*data-digi-menu-cart="([^"]*)".*$/s', '$1', $opts ), ENT_QUOTES ), true );
	$check( array( 'open' => 'mouseover', 'auto' => true, 'type' => 'mini-cart', 'added' => true ) === $cfg && str_contains( $opts, 'eicon-bag-solid' ) && str_contains( $opts, 'class="digi-mc__xc" aria-label="Cerrar el carrito"' ), 'Al pasar el cursor, abrir al añadir (también tras un envío sin AJAX), mini carrito, bolsa sólida y aspa personalizada.' );
	$GLOBALS['m_wp']['did'] = 0;
	$custom = h_render( $own, array( 'icon' => 'custom', 'menu_icon_svg' => array( 'value' => 'fas fa-shopping-bag', 'library' => 'fa-solid' ) ) );
	$check( ! str_contains( $custom, 'eicon-' ) && str_contains( $custom, 'shopping-bag' ) && str_contains( h_render( $own, array( 'icon' => 'custom', 'menu_icon_svg' => array( 'value' => '', 'library' => '' ) ) ), 'shopping-cart' ), 'Icono personalizado por el gestor de iconos del núcleo: ' . $custom );
	$GLOBALS['h_wp']['edit_mode'] = true;
	$editor                       = h_render( $own );
	unset( $GLOBALS['h_wp']['edit_mode'] );
	$check( str_contains( $editor, '<div class="digi-mc__ct"><div class="digi-mc__list' ), 'En el editor el panel se llena al pintar.' );
	$GLOBALS['h_wp']['filters']['woocommerce_widget_cart_is_hidden'] = static function () { return true; };
	$check( '<div class="digi-mc"></div>' === h_render( $own ), 'Con woocommerce_widget_cart_is_hidden sólo queda el envoltorio, como en PRO.' );
	unset( $GLOBALS['h_wp']['filters']['woocommerce_widget_cart_is_hidden'] );

	// 5. Fragmentos y mini carrito.
	$frags = Menu_Cart_Markup::fragments( array( 'div.widget_shopping_cart_content' => 'x' ) );
	$check( 'x' === $frags['div.widget_shopping_cart_content'] && '<span class="elementor-button-icon-qty" data-counter="3">3</span>' === $frags['.digi-mc__btn span.elementor-button-icon-qty'] && str_contains( $frags['.digi-mc__btn span.elementor-button-text'], '75,00' ), 'Fragmentos del botón, sin tocar los de WooCommerce.' );
	$panel = $frags['div.digi-mc__ct'];
	$check( str_starts_with( $panel, '<div class="digi-mc__ct"><div class="digi-mc__list woocommerce-mini-cart cart woocommerce-cart-form__contents">' ) && 2 === substr_count( $panel, 'class="digi-mc__item woocommerce-cart-form__cart-item cart_item"' ), 'Fragmento del panel con una línea por artículo.' );
	$check( str_contains( $panel, '<span class="product-quantity">2 &times;</span>' ) && str_contains( $panel, '<dl class="variation">' ) && str_contains( $panel, 'Producto &quot;variable&quot;' ) || str_contains( $panel, 'Producto "variable"' ), 'Cantidad, datos de la variación y nombre del producto.' );
	$check( str_contains( $panel, 'class="digi-mc__rml" aria-label="Quitar «Producto simple» del carrito"' ) && str_contains( $panel, 'class="remove_from_cart_button" aria-label="Quitar «Producto simple» del carrito" data-product_id="11" data-cart_item_key="k1" data-product_sku="AB-1"' ), 'Quitar con y sin AJAX (la clase de WooCommerce), con el nombre del producto.' );
	$check( str_contains( $panel, '<div class="digi-mc__sub"><strong>Subtotal:</strong>' ) && str_contains( $panel, 'class="digi-mc__view elementor-button elementor-size-md"><span class="elementor-button-text">View cart</span>' ) && str_contains( $panel, 'href="https://sitio.test/pago/" class="digi-mc__pay' ), 'Subtotal y botones con los textos de WooCommerce.' );
	$check( str_contains( $panel, 'tabindex="-1" aria-hidden="true"><img' ), 'La miniatura enlazada no repite la parada del foco del nombre.' );
	$GLOBALS['h_wp']['filters']['woocommerce_cart_item_remove_link'] = static function ( $html ) { return str_replace( '></a>', ' data-x="1"></a>', $html ); };
	$check( 2 === substr_count( Menu_Cart_Markup::mini_cart(), 'data-x="1"' ) / 2, 'Los filtros de WooCommerce del mini carrito se aplican (enlace de quitar).' );
	unset( $GLOBALS['h_wp']['filters']['woocommerce_cart_item_remove_link'] );
	$GLOBALS['m_wp']['items'] = array();
	$check( '<div class="woocommerce-mini-cart__empty-message">No products in the cart.</div>' === Menu_Cart_Markup::mini_cart(), 'Carrito vacío: el mensaje de WooCommerce.' );
	// Con una traducción de WooCommerce y ninguna de PRO (un sitio en español que venía de PRO, que pintaba su texto con su
	// dominio): los dos textos, y la hoja muestra el de PRO sólo en lo convertido (`digi-mc--pro`), porque el panel llega
	// por los fragmentos, iguales para todos los carritos de la página.
	$h_esc = $GLOBALS['h_wp_fns']['esc_html__'];
	$GLOBALS['h_wp_fns']['esc_html__'] = static function ( $v, $d = '' ) use ( $h_esc ) { return 'woocommerce' === $d && 'No products in the cart.' === $v ? 'No hay productos en el carrito.' : $h_esc( $v, $d ); };
	$check( '<div class="woocommerce-mini-cart__empty-message"><span class="digi-mc__em">No hay productos en el carrito.</span><span class="digi-mc__em digi-mc__em--pro">No products in the cart.</span></div>' === Menu_Cart_Markup::mini_cart(), 'Carrito vacío con traducciones distintas: el texto de WooCommerce y el de PRO.' );
	$GLOBALS['h_wp_fns']['esc_html__'] = $h_esc;
	$check( str_starts_with( h_render( $own, array( '_digitalisimo_pro' => 'woocommerce-menu-cart' ) ), '<div class="digi-mc digi-mc--pro">' ) && str_starts_with( h_render( $own ), '<div class="digi-mc">' ), 'Lo convertido de PRO lleva `digi-mc--pro`; lo nuevo, no.' );
	$check( str_contains( $css2, '.digi-mc--pro .digi-mc__em,.digi-mc__em--pro{display:none}.digi-mc--pro .digi-mc__em--pro{display:inline}' ), 'La hoja muestra el texto de PRO sólo en lo convertido.' );
	$GLOBALS['m_wp']['options']['elementor_use_mini_cart_template'] = 'no';
	$check( '<ul class="woocommerce-mini-cart">TEMA</ul>' === Menu_Cart_Markup::mini_cart() && 1 === $GLOBALS['m_wp']['mini'], 'Con la plantilla del constructor desactivada, la del tema o de WooCommerce.' );
	unset( $GLOBALS['m_wp']['options']['elementor_use_mini_cart_template'], $GLOBALS['m_wp']['options'][ Menu_Cart_Markup::OPTION ] );
	$check( ! isset( Menu_Cart_Markup::fragments( array() )['div.digi-mc__ct'] ), 'Sin el widget en uso no se arma el fragmento del panel.' );

	// 6. Rasgos del panel (título, envío y cupón).
	$GLOBALS['m_wp']['items'] = array( 'k1' => array( 'data' => new M_Product( 'P', 1, 'S' ), 'product_id' => 1, 'quantity' => 1 ) );
	$base = h_render( $own );
	$check( str_contains( \Digitalisimo\Elements\Cart_Title::insert( $base, 'Tu carrito' ), '<p class="digi-cart-title">Tu carrito</p><div class="digi-mc__ct">' ), 'El título del panel va delante del contenido del panel propio.' );
	$apply = digi_test_accessible( new ReflectionMethod( \Digitalisimo\Elements\Cart_Title::class, 'applies' ) );
	$check( $apply->invoke( null, $own ) && \Digitalisimo\Elements\Woo\Cart\Mini_Cart_Extras::applies( $own ) && ! \Digitalisimo\Elements\Woo\Cart\Mini_Cart_Extras::applies( $pown ), 'Título, barra y cupón reconocen el Carrito del menú propio.' );
	$top    = \Digitalisimo\Elements\Woo\Cart\Mini_Cart_Extras::insert( $base, array( 'digitalisimo_cart_coupon' => 'yes' ), 'u1' );
	$bottom = \Digitalisimo\Elements\Woo\Cart\Mini_Cart_Extras::insert( $base, array( 'digitalisimo_cart_coupon' => 'yes', 'digitalisimo_cart_extras_position' => 'bottom' ), 'u1' );
	$check( preg_match( '#<div class="digi-cart-extras">.*</div><div class="digi-mc__ct">#s', $top ) && preg_match( '#<div class="digi-mc__ct"></div><div class="digi-cart-extras">.*</div></div><!-- /digi-mc__main -->#s', $bottom ), "Cupón arriba (antes del contenido) y abajo (al final del panel).\n$top\n$bottom" );
	$check( $base === \Digitalisimo\Elements\Woo\Cart\Mini_Cart_Extras::insert( $base, array(), 'u1' ), 'Sin interruptores, el marcado no cambia.' );
	$GLOBALS['m_wp']['hooks'] = array();
	\Digitalisimo\Elements\Woo\Cart\Mini_Cart_Extras::init();
	$check( in_array( array( Menu_Cart_Markup::class, 'fragments' ), $GLOBALS['m_wp']['hooks']['woocommerce_add_to_cart_fragments'] ?? array(), true ), 'Los fragmentos se enganchan con WooCommerce también fuera del widget (peticiones ?wc-ajax=).' );

	// 7. Páginas de la tienda.
	$GLOBALS['m_wp']['shortcodes'] = array();
	$check( '<div class="woocommerce">[woocommerce_cart ]</div>' === h_render( $pown, array( 'element' => 'woocommerce_cart' ) ) && '' === h_render( $pown, array( 'element' => '' ) ) && '' === h_render( $pown, array( 'element' => 'x"]' ) ), 'Shortcode de la página elegida; nada sin elegir o con un valor ajeno.' );
	$check( '<div class="woocommerce">[product_page id="7"]</div>' === h_render( $pown, array( 'element' => 'product_page', 'product_id' => '7' ) ), 'Página de un producto con su ID.' );
	$GLOBALS['m_wp']['admin'] = true;
	$m_out = h_render( $pown, array( 'element' => 'product_page', 'product_id' => '9' ) );
	$check( '<div class="woocommerce">Elige un producto válido</div>' === $m_out, 'Producto que no lo es: aviso para quien administra, como PRO: ' . $m_out );
	// Pago con el carrito vacío: el texto de PRO (su dominio) en lo convertido; el de WooCommerce en lo nuevo.
	$GLOBALS['m_wp']['checkout_empty'] = true;
	$h_esc = $GLOBALS['h_wp_fns']['esc_html__'];
	$GLOBALS['h_wp_fns']['esc_html__'] = static function ( $v, $d = '' ) use ( $h_esc ) { return 'woocommerce' === $d && 'Your cart is currently empty.' === $v ? 'Tu carrito está vacío.' : $h_esc( $v, $d ); };
	$empty_new = h_render( $pown, array( 'element' => 'woocommerce_checkout' ) );
	$empty_pro = h_render( $pown, array( 'element' => 'woocommerce_checkout', '_digitalisimo_pro' => 'woocommerce-checkout-page' ) );
	$GLOBALS['h_wp_fns']['esc_html__'] = $h_esc;
	$GLOBALS['m_wp']['checkout_empty'] = false;
	$check( '<div class="woocommerce">Tu carrito está vacío.</div>' === $empty_new && '<div class="woocommerce">Your cart is currently empty.</div>' === $empty_pro, 'Pago con el carrito vacío: el texto de WooCommerce en lo nuevo y el de PRO en lo convertido: ' . $empty_new . ' / ' . $empty_pro );
	$GLOBALS['m_wp']['admin'] = false;
	$check( array( 'id' => 'x', 'settings' => array( 'element' => 'product_page' ) ) === $pown->on_export( array( 'id' => 'x', 'settings' => array( 'element' => 'product_page', 'product_id' => '7' ) ) ), 'El producto elegido no viaja en una exportación (como PRO).' );

	// 8. Mapas.
	$names = static function ( string $file ): array {
		$php = (string) file_get_contents( $file );
		preg_match_all( "/->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $php, $c );
		preg_match_all( "/->add_group_control\([^;]*?'name' => '([a-z0-9_]+)'/s", $php, $g );
		return array_merge( $c[1], $g[1] );
	};
	foreach ( array( array( $map, 'menu-cart.php', $own ), array( $pmap, 'elements.php', $pown ) ) as list( $m, $file, $widget ) ) {
		$declared = array_merge( (array) ( $m['keep'] ?? array() ), array_keys( (array) ( $m['groups'] ?? array() ) ), array_keys( (array) ( $m['drop'] ?? array() ) ), (array) ( $m['ui'] ?? array() ) );
		$missing  = array_diff( $names( h_path( 'modules/woocommerce/widgets/' . $file ) ), $declared );
		$check( ! $missing, "$file: el mapa no declara " . implode( ', ', $missing ) );
		foreach ( (array) ( $m['keep'] ?? array() ) as $key ) {
			$check( isset( $widget->controls[ $key ] ), "$file: «{$key}» no es un control propio." );
		}
		foreach ( (array) ( $m['groups'] ?? array() ) as $to ) {
			$check( isset( $widget->groups[ $to ] ), "$file: «{$to}» no es un grupo propio." );
		}
		$check( $widget->get_name() === $m['target'] && in_array( $m['status'], array( 'draft', 'ready' ), true ) && 'hide' === $m['panel'], "$file: destino, estado y panel." );
	}
	list( $conv, $codes ) = h_convert( 'woocommerce-menu-cart', $own, array(
		'm1' => array(),
		'm2' => array( 'cart_type' => 'mini-cart', 'open_cart' => 'mouseover', 'icon' => 'basket-light', 'items_indicator' => 'plain', 'show_subtotal' => '', 'alignment_tablet' => 'center', 'toggle_button_text_color' => '#ff0000', 'toggle_button_typography_typography' => 'custom', 'toggle_button_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 12 ), '__globals__' => array( 'toggle_button_icon_color' => 'globals/colors?id=primary', 'subtotal_typography_typography' => 'globals/typography?id=text' ), 'view_cart_button_show' => '', 'buttons_layout' => 'stacked', 'divider_gap' => array( 'unit' => 'px', 'size' => 10 ), 'automatically_update_cart' => '', 'checkout_border_border' => 'solid', 'custom_css' => 'selector .elementor-menu-cart__toggle_button{--toggle-button-text-color:red} selector .elementor-menu-cart__product-name a{color:red} selector.elementor-menu-cart--shown .elementor-menu-cart__main{width:400px}' ),
		'm3' => array( 'checkout_button_display' => '--cart-footer-buttons-alignment-display: none;', 'close_cart_icon_svg' => array( 'value' => 'fas fa-times', 'library' => 'fa-solid' ), '__dynamic__' => array( 'view_cart_button_text_color' => '[elementor-tag x]' ) ),
	) );
	$check( 'selector .digi-mc__btn{--mc-tbtc:red} selector .digi-mc__name a{color:red} selector.digi-mc--shown .digi-mc__main{width:400px}' === $conv['m2']['custom_css'], 'CSS personalizado: clases y variables de PRO pasadas a las propias: ' . $conv['m2']['custom_css'] );
	$check( 'mini-cart' === $conv['m2']['cart_type'] && '#ff0000' === $conv['m2']['toggle_button_text_color'] && 'globals/colors?id=primary' === $conv['m2']['__globals__']['toggle_button_icon_color'] && array( 'unit' => 'px', 'size' => 12 ) === $conv['m2']['toggle_button_typography_font_size_mobile'] && 'center' === $conv['m2']['alignment_tablet'], 'Ajustes, dispositivos y globales pasan con el mismo nombre.' );
	$check( ! isset( $conv['m3']['checkout_button_display'] ) && '[elementor-tag x]' === $conv['m3']['__dynamic__']['view_cart_button_text_color'], 'El control oculto de PRO se descarta (el propio pone su regla) y los dinámicos pasan.' );
	$html = h_render( $own, array( 'cart_type' => 'mini-cart' ) ) . Menu_Cart_Markup::mini_cart() . '<a class="digi-mc__rml"></a><span class="digi-mc__ci digi-mc__xi"></span><div class="digi-mc__xc"></div>';
	foreach ( (array) $map['classes'] as $from => $to ) {
		if ( '.' !== $to[0] ) {
			$check( 0 === strpos( $to, '--mc-' ) && isset( $set_vars[ $to ] ) || in_array( $to, array( '--mc-tbbt' ), true ), "Variable $from → $to: no la fija ningún control propio." );
			continue;
		}
		$class = substr( $to, 1 );
		$check( preg_match( '/^(elementor-widget-digitalisimo-woo-menu-cart|digi-mc--[a-z-]+)$/', $class ) || preg_match( '/class="([^"]* )?' . preg_quote( $class, '/' ) . '( [^"]*)?"/', $html ), "La clase $to (de $from) no existe en el widget propio." );
	}
	h_convert( 'wc-elements', $pown, array( 'p1' => array( 'element' => 'product_page', 'product_id' => '7' ), 'p2' => array() ) );

	echo "DIGITALÍSIMO Elements: Carrito del menú y Páginas de la tienda (C7) en verde ($checks comprobaciones).\n";
}
