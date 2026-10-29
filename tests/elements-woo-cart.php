<?php
/**
 * F4-Woo, frente E: barra de envío gratis, cupón y rasgo del Menú del carrito (V.7).
 *
 * Con las clases reales del frente A (`Woo\Module` y su autocarga, `Flags`, `Assets`, `Widget`) y de `Cart_Title`, y
 * dobles del carrito, del envío y de los métodos de WooCommerce. Comprueba:
 * - los estados de V.7.1 desde las tarifas reales: `done` sólo con una tarifa `free_shipping`; «Te faltan X» sólo con
 *   destino conocido, un paquete, todos los «Envío gratuito» de la zona con `requires = min_amount` y sin filtros ajenos
 *   (`…_is_available`, `woocommerce_package_rates`); `min_amount` ya filtrado por `…_instance_option` (divisas);
 *   descuentos e impuestos con la regla de `is_available()`; dos paquetes, cliente sin país, zona 0 sin método,
 *   `requires = ''`, «o cupón», importe no numérico y carrito vacío dan `none`; memoria por petición;
 * - los fragmentos: iguales para todas las instancias y páginas, sólo con la bandera (sin ella no se calcula nada), los
 *   nonces sólo en el fragmento del cupón, nada con `id` ni formularios dentro de un fragmento;
 * - el marcado persistente: oculto, sin importes ni nonces; el formulario con el `uid` de su instancia;
 * - `Mini_Cart_Extras`: sin interruptores el HTML queda idéntico; «Arriba» detrás del título de `Cart_Title` y delante
 *   del contenido; «Abajo» antes del cierre de `.elementor-menu-cart__main`, también con el contenido del editor; los
 *   controles añadidos no tienen `default` y los de estilo dependen de un interruptor (D33): el CSS de un Menú del
 *   carrito existente no cambia; recursos por `assets` del interruptor (V.12.2);
 * - recursos y textos: hoja ≤ 4 KB, script ≤ 8 KB, ningún texto de urgencia inventada («venta falsa»).
 */

namespace Elementor {
	class Controls_Manager {
		const TAB_STYLE = 'style';
		const TEXT = 'text';
		const SWITCHER = 'switcher';
		const SELECT = 'select';
		const HEADING = 'heading';
		const COLOR = 'color';
		const SLIDER = 'slider';
		const RAW_HTML = 'raw_html';
	}
	class Group_Control_Typography {
		public static function get_type() { return 'typography'; }
	}
	class Plugin {
		public static $instance;
		public $editor;
	}
	abstract class Widget_Base {
		public $controls = array();
		public $settings = array();
		public function __construct( $settings = array() ) { $this->settings = $settings; }
		public function start_controls_section( $id, $args = array() ) {}
		public function end_controls_section() {}
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function add_group_control( $type, $args ) { $this->controls[ $args['name'] ] = $args; }
		public function get_settings_for_display( $key = null ) {
			$all = $this->settings;
			foreach ( $this->controls as $id => $c ) { if ( ! array_key_exists( $id, $all ) && array_key_exists( 'default', $c ) ) { $all[ $id ] = $c['default']; } }
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		public function boot() { $this->register_controls(); return $this; }
		public function html() { ob_start(); $this->render(); return ob_get_clean(); }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.165' );
	define( 'WC_VERSION', '11.2.1' );

	class WooCommerce {}

	// --- Dobles de WooCommerce ----------------------------------------------------------------------------------------
	class WT_Rate {
		public $method_id;
		public function __construct( $m ) { $this->method_id = $m; }
		public function get_method_id() { return $this->method_id; }
	}
	class WC_Cart {
		public $items = 1; public $needs = true; public $show = true; public $subtotal = 30.0; public $discount = 0.0; public $discount_tax = 0.0;
		public $incl_tax = false; public $coupons = array(); public $packages = 1;
		public function is_empty() { return 0 === $this->items; }
		public function needs_shipping() { return $this->needs; }
		public function show_shipping() { return $this->show; }
		public function get_shipping_packages() { $out = array(); for ( $i = 0; $i < $this->packages; $i++ ) { $out[] = array( 'destination' => array( 'country' => 'MX' ) ); } return $out; }
		public function get_displayed_subtotal() { return $this->subtotal; }
		public function get_discount_total() { return $this->discount; }
		public function get_discount_tax() { return $this->discount_tax; }
		public function display_prices_including_tax() { return $this->incl_tax; }
		public function get_applied_coupons() { return $this->coupons; }
	}
	class WT_Shipping {
		public $rates = array(); public $packages = array(); public $calls = 0;
		public function calculate_shipping( $packages ) { $this->calls++; $this->packages = array(); foreach ( $packages as $k => $p ) { $p['rates'] = $this->rates; $this->packages[ $k ] = $p; } return $this->packages; }
		public function get_packages() { return $this->packages; }
	}
	class WC_Customer {
		public $calculated = true;
		public function has_calculated_shipping() { return $this->calculated; }
	}
	class WT_Zone {
		public $id; public $methods;
		public function __construct( $id, $methods ) { $this->id = $id; $this->methods = $methods; }
		public function get_id() { return $this->id; }
		public function get_shipping_methods( $enabled = false ) { $GLOBALS['wt']['zone_reads']++; return $this->methods; }
	}
	class WC_Shipping_Zones {
		public static function get_zone_matching_package( $package ) { return $GLOBALS['wt']['zone']; }
	}
	class WT_WC {
		public $cart; public $customer; public $ship;
		public function shipping() { return $this->ship; }
	}
	function WC() { return $GLOBALS['wt']['wc']; }
	function free_method( $min = '50', $requires = 'min_amount', $ignore = 'no' ) { return (object) array( 'id' => 'free_shipping', 'requires' => $requires, 'min_amount' => $min, 'ignore_discounts' => $ignore ); }

	$GLOBALS['wt'] = array();
	function wt_reset( array $over = array() ) {
		$wc           = new WT_WC();
		$wc->cart     = new WC_Cart();
		$wc->customer = new WC_Customer();
		$wc->ship     = new WT_Shipping();
		$GLOBALS['wt'] = array_merge(
			array(
				'hooks'      => $GLOBALS['wt']['hooks'] ?? array(),
				'wc'         => $wc,
				'zone'       => new WT_Zone( 3, array( free_method() ) ),
				'zone_reads' => 0,
				'user'       => 0,
				'umeta'      => array(),
				'coupons'    => true,
				'options'    => array( 'digitalisimo_elements_woo_fragments' => 1 ),
				'edit'       => false,
			),
			$over
		);
		\Digitalisimo\Elements\Woo\Cart\State::reset();
		\Digitalisimo\Elements\Woo\Cart\Mini_Cart_Extras::reset();
		\Elementor\Plugin::$instance         = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return $GLOBALS['wt']['edit']; } };
	}

	// --- WordPress ---------------------------------------------------------------------------------------------------
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['wt']['hooks'][ $hook ][] = array( $cb, $priority ); return true; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { return add_action( $hook, $cb, $priority, $args ); }
	function has_filter( $hook, $cb = false ) { return ! empty( $GLOBALS['wt']['hooks'][ $hook ] ); }
	function has_action( $hook, $cb = false ) { return has_filter( $hook, $cb ); }
	function apply_filters( $hook, $value, ...$args ) {
		$rows = $GLOBALS['wt']['hooks'][ $hook ] ?? array();
		usort( $rows, function ( $a, $b ) { return $a[1] <=> $b[1]; } );
		foreach ( $rows as $row ) { $value = call_user_func_array( $row[0], array_merge( array( $value ), $args ) ); }
		return $value;
	}
	function do_action( $hook, ...$args ) {}
	function did_action( $hook ) { return 0; }
	function get_current_user_id() { return $GLOBALS['wt']['user']; }
	function get_user_meta( $uid, $key, $single = false ) { return $GLOBALS['wt']['umeta'][ $uid ][ $key ] ?? ''; }
	function wp_load_alloptions() { return $GLOBALS['wt']['options']; }
	function maybe_unserialize( $v ) { return $v; }
	function wc_coupons_enabled() { return $GLOBALS['wt']['coupons']; }
	function wc_get_price_decimals() { return 2; }
	function wc_price( $amount ) { return '<span class="woocommerce-Price-amount amount"><bdi>' . number_format( (float) $amount, 2, ',', '.' ) . '&nbsp;<span class="woocommerce-Price-currencySymbol">&euro;</span></bdi></span>'; }
	function wp_create_nonce( $action ) { return 'nonce-' . $action; }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8', false ); }
	function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); }
	function wp_kses_post( $t ) { return $t; }
	function plugins_url( $path, $file ) { return 'https://a.test/' . $path; }
	function __return_false() { return false; }

	function check( $ok, $message ) {
		if ( ! $ok ) { throw new RuntimeException( $message ); }
	}

	$root = dirname( __DIR__ ) . '/digitalisimo-elements';
	require_once $root . '/modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();
	require_once $root . '/modules/digitalisimo-woo/cart/class-module.php';
	require_once $root . '/modules/digitalisimo-woo/cart/class-state.php';
	require_once $root . '/modules/digitalisimo-woo/cart/class-markup.php';
	require_once $root . '/modules/digitalisimo-woo/cart/class-mini-cart-extras.php';
	require_once $root . '/modules/digitalisimo-widgets/class-cart-title.php';
	use Digitalisimo\Elements\Woo\Cart\Markup;
	use Digitalisimo\Elements\Woo\Cart\Mini_Cart_Extras;
	use Digitalisimo\Elements\Woo\Cart\Module as Cart;
	use Digitalisimo\Elements\Woo\Cart\Shipping_Bar_Widget;
	use Digitalisimo\Elements\Woo\Cart\State;

	$state = function ( ?callable $setup = null ) {
		wt_reset();
		$wc = WC();
		if ( $setup ) { $setup( $wc ); }
		State::reset();
		return State::compute();
	};
	$GLOBALS['wt']['hooks'] = array();
	wt_reset();
	Cart::init();
	check( isset( $GLOBALS['wt']['hooks']['woocommerce_add_to_cart_fragments'] ), 'Los fragmentos se enganchan con WooCommerce.' );
	// El hash del carrito decide si cart-fragments.js reutiliza los fragmentos guardados: debe cambiar al conocerse el destino (W11).
	check( isset( $GLOBALS['wt']['hooks']['woocommerce_cart_hash'] ), 'El hash del carrito se filtra con WooCommerce (los fragmentos guardados caducan al conocerse el destino).' );
	WC()->customer->calculated = false;
	$hash_unknown = Cart::cart_hash( 'abc' );
	WC()->customer->calculated = true;
	$hash_known = Cart::cart_hash( 'abc' );
	check( '' === Cart::cart_hash( '' ) && 32 === strlen( $hash_unknown ) && 'abc' !== $hash_unknown && $hash_unknown !== $hash_known && $hash_known === Cart::cart_hash( 'abc' ) && null === Cart::cart_hash( null ), 'El hash cambia al conocerse el destino, es estable, y sin hash (carrito vacío) o con otro tipo no se toca.' );
	$GLOBALS['wt']['options'] = array( 'digitalisimo_elements_woo_fragments' => 0 );
	check( 'abc' === Cart::cart_hash( 'abc' ), 'Sin la bandera de fragmentos, el hash de WooCommerce no se toca.' );
	$GLOBALS['wt']['options'] = array( 'digitalisimo_elements_woo_fragments' => 1 );
	check( isset( $GLOBALS['wt']['hooks']['elementor/widget/render_content'][0] ) && 11 === $GLOBALS['wt']['hooks']['elementor/widget/render_content'][0][1], 'El rasgo inserta tras Cart_Title (prioridad 11).' );
	$base_hooks = $GLOBALS['wt']['hooks'];

	// --- Estados (V.7.1) -------------------------------------------------------------------------------------------
	$progress = $state( function ( $wc ) { $wc->ship->rates = array( new WT_Rate( 'flat_rate' ) ); } );
	check( 'progress' === $progress['state'] && false !== strpos( $progress['remaining_html'], '20,00' ) && false !== strpos( $progress['threshold_html'], '50,00' ) && 60 === $progress['percent'], 'Con 30 de 50 y la tarifa gratis aún no ofrecida: «Te faltan 20» y 60 %.' );
	$done = $state( function ( $wc ) { $wc->cart->subtotal = 55; $wc->ship->rates = array( new WT_Rate( 'flat_rate' ), new WT_Rate( 'free_shipping' ) ); } );
	check( 'done' === $done['state'] && '' === $done['remaining_html'], 'Envío gratis sólo cuando WooCommerce ya ofrece la tarifa.' );
	$odd = $state( function ( $wc ) { $wc->cart->subtotal = 80; $wc->ship->rates = array( new WT_Rate( 'flat_rate' ) ); } );
	check( 'none' === $odd['state'], 'Superado el mínimo sin que WooCommerce ofrezca la tarifa: no se promete nada.' );
	$discounted = $state( function ( $wc ) { $wc->cart->subtotal = 55; $wc->cart->discount = 10; $wc->cart->discount_tax = 1.6; $wc->cart->incl_tax = true; } );
	check( 'progress' === $discounted['state'] && false !== strpos( $discounted['remaining_html'], '6,60' ), 'Descuento y su impuesto con precios con impuestos, como is_available().' );
	$ignore = $state( function ( $wc ) { $wc->cart->subtotal = 45; $wc->cart->discount = 10; $GLOBALS['wt']['zone'] = new WT_Zone( 3, array( free_method( '50', 'min_amount', 'yes' ) ) ); } );
	check( false !== strpos( $ignore['remaining_html'], '5,00' ), '«Aplicar el mínimo antes del cupón»: el descuento no cuenta.' );
	$converted = $state( function ( $wc ) { $GLOBALS['wt']['zone'] = new WT_Zone( 3, array( free_method( '1000.5' ) ) ); $wc->cart->subtotal = 400; } );
	check( false !== strpos( $converted['remaining_html'], '600,50' ) && false !== strpos( $converted['threshold_html'], '1.000,50' ), 'El mínimo es el de la instancia construida (ya convertido por …_instance_option), en la moneda del carrito.' );
	$two = $state( function ( $wc ) { $GLOBALS['wt']['zone'] = new WT_Zone( 3, array( free_method( '80' ), free_method( '60' ) ) ); } );
	check( false !== strpos( $two['remaining_html'], '30,00' ), 'Con dos métodos de importe mínimo, el que antes se alcanza.' );
	foreach ( array(
		'…_is_available filtrado por un tercero'  => function ( $wc ) { add_filter( 'woocommerce_shipping_free_shipping_is_available', '__return_false' ); },
		'woocommerce_package_rates filtrado'      => function ( $wc ) { add_filter( 'woocommerce_package_rates', function ( $r ) { return $r; } ); },
		'dos paquetes'                            => function ( $wc ) { $wc->cart->packages = 2; },
		'cliente sin destino'                     => function ( $wc ) { $wc->customer->calculated = false; },
		'zona 0 sin envío gratuito'               => function ( $wc ) { $GLOBALS['wt']['zone'] = new WT_Zone( 0, array( (object) array( 'id' => 'flat_rate' ) ) ); },
		'requires vacío sin tarifa'               => function ( $wc ) { $GLOBALS['wt']['zone'] = new WT_Zone( 3, array( free_method( '0', '' ) ) ); },
		'«importe mínimo o cupón»'                => function ( $wc ) { $GLOBALS['wt']['zone'] = new WT_Zone( 3, array( free_method( '50', 'either' ) ) ); },
		'un método con cupón junto al de importe' => function ( $wc ) { $GLOBALS['wt']['zone'] = new WT_Zone( 3, array( free_method( '50' ), free_method( '0', 'coupon' ) ) ); },
		'importe no numérico'                     => function ( $wc ) { $GLOBALS['wt']['zone'] = new WT_Zone( 3, array( free_method( '50,00' ) ) ); },
		'carrito vacío'                           => function ( $wc ) { $wc->cart->items = 0; },
		'sin envío necesario'                     => function ( $wc ) { $wc->cart->needs = false; },
		'envío sólo con dirección'                => function ( $wc ) { $wc->cart->show = false; },
	) as $case => $setup ) {
		$s = $state( $setup );
		$GLOBALS['wt']['hooks'] = $base_hooks;
		check( 'none' === $s['state'] && '' === $s['remaining_html'], 'Si no se puede saber, nada: ' . $case . '.' );
	}
	$free = $state( function ( $wc ) { $GLOBALS['wt']['zone'] = new WT_Zone( 3, array( free_method( '0', '' ) ) ); $wc->ship->rates = array( new WT_Rate( 'free_shipping' ) ); } );
	check( 'done' === $free['state'], 'requires vacío: «Tienes envío gratis» sólo si la tarifa está.' );
	$account = $state( function ( $wc ) { $wc->customer->calculated = false; $GLOBALS['wt']['user'] = 5; $GLOBALS['wt']['umeta'][5]['shipping_country'] = 'MX'; } );
	check( 'progress' === $account['state'], 'Destino conocido: un usuario con país de envío en su cuenta.' );
	$empty_ship = $state( function ( $wc ) { $wc->cart->items = 0; } );
	check( 0 === $GLOBALS['wt']['wc']->ship->calls && 0 === $GLOBALS['wt']['zone_reads'], 'Sin carrito que enviar no se calcula ni se leen zonas.' );
	$state( function ( $wc ) {} );
	State::compute();
	State::compute();
	check( 1 === $GLOBALS['wt']['wc']->ship->calls && 1 === $GLOBALS['wt']['zone_reads'], 'Una sola vez por petición (W-2: get_shipping_methods() consulta la base).' );
	$no_wc = function () { $GLOBALS['wt']['wc'] = (object) array( 'cart' => null ); State::reset(); return State::compute(); };
	check( 'none' === $no_wc()['state'], 'Sin carrito: none.' );

	// --- Cupones en el estado ----------------------------------------------------------------------------------------
	$coupons = $state( function ( $wc ) { $wc->cart->coupons = array( 'verano10', '<b>x</b>' ); } );
	check( true === $coupons['coupons_enabled'] && array( 'verano10', '<b>x</b>' ) === $coupons['coupons'], 'Cupones aplicados en el estado.' );
	$off = $state( function ( $wc ) { $GLOBALS['wt']['coupons'] = false; $wc->cart->coupons = array( 'verano10' ); } );
	check( false === $off['coupons_enabled'] && array() === $off['coupons'], 'Con los cupones apagados, nada.' );

	// --- Fragmentos ---------------------------------------------------------------------------------------------------
	$state( function ( $wc ) { $wc->cart->coupons = array( 'verano10' ); } );
	State::reset();
	WC()->ship->calls = 0;
	$GLOBALS['wt']['options'] = array( 'digitalisimo_elements_woo_fragments' => 0 );
	$in = array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content"></div>' );
	check( $in === Cart::fragments( $in ) && 0 === $GLOBALS['wt']['wc']->ship->calls, 'Con la bandera apagada, el filtro no calcula nada.' );
	$GLOBALS['wt']['options'] = array( 'digitalisimo_elements_woo_fragments' => 1 );
	$out = Cart::fragments( $in );
	check( array( 'div.widget_shopping_cart_content', 'div.digi-ship__state', 'div.digi-coupon__state' ) === array_keys( $out ), 'Con la bandera: los dos nodos de estado.' );
	State::reset();
	check( $out === Cart::fragments( $in ), 'El mismo HTML en cualquier petición (no depende de la página ni de la instancia).' );
	$ship = $out['div.digi-ship__state'];
	check( 0 === strpos( $ship, "<div class=\"digi-ship__state\" data-state=\"progress\" data-remaining=\"20,00\u{a0}€\" data-threshold=\"50,00\u{a0}€\" data-percent=\"60\">" ), 'Estado de la barra con los importes como texto para las plantillas.' );
	check( false !== strpos( $ship, 'Te faltan <span class="woocommerce-Price-amount' ) && false !== strpos( $ship, '<div class="digi-ship__bar" aria-hidden="true"><span></span></div>' ), 'Texto por defecto con el importe de wc_price() y barra decorativa.' );
	$coupon = $out['div.digi-coupon__state'];
	check( false !== strpos( $coupon, 'data-apply="nonce-apply-coupon" data-remove="nonce-remove-coupon"' ) && false !== strpos( $coupon, '<button type="button" class="digi-coupon__remove" data-coupon="verano10">Quitar<span class="screen-reader-text"> cupón verano10</span></button>' ), 'Nonces y «Quitar cupón {código}» sólo en el fragmento.' );
	foreach ( $out as $key => $html ) {
		if ( 'div.widget_shopping_cart_content' === $key ) { continue; }
		check( ! preg_match( '/\sid="|<form|<input|<script/i', $html ), 'Ningún id, formulario ni script dentro de un fragmento (' . $key . ').' );
	}
	$state( function ( $wc ) { $wc->cart->coupons = array( '"><img src=x onerror=1>' ); } );
	check( false === strpos( Markup::coupon_state( State::compute() ), '<img' ), 'El código del cupón se escapa.' );
	$GLOBALS['wt']['coupons'] = false;
	State::reset();
	check( '<div class="digi-coupon__state" hidden></div>' === Markup::coupon_state( State::compute() ), 'Con los cupones apagados, el estado del cupón va vacío: el envoltorio oculta el formulario.' );
	check( '<div class="digi-ship__state" data-state="none" hidden></div>' === Markup::ship_state( array( 'state' => 'none' ) ), 'Sin estado que mostrar, el nodo va oculto.' );

	// --- Marcado persistente -------------------------------------------------------------------------------------------
	$bar = Markup::bar( '', '' );
	check( '<div class="digi-ship" data-digi-ship data-t-progress="Te faltan {restante} para el envío gratis" data-t-done="Tienes envío gratis" hidden><div class="digi-ship__state" data-digi-initial hidden></div></div>' === $bar, 'La barra de la página: neutra, oculta y con las plantillas de su instancia.' );
	$form = Markup::coupon( 'abc1234-2' );
	check( false !== strpos( $form, '<label class="digi-coupon__label" for="digi-coupon-abc1234-2">Código de cupón</label>' ) && false !== strpos( $form, '<input id="digi-coupon-abc1234-2"' ) && false !== strpos( $form, '<p class="digi-coupon__status" role="status"></p>' ), 'Formulario y región viva en el envoltorio, con el uid de la instancia.' );
	check( ! preg_match( '/nonce-|€|data-apply/', $bar . $form ), 'Ni importes ni nonces en el HTML de la página (caché de página).' );
	check( false !== strpos( $form, ' hidden>' ), 'El cupón sale oculto hasta que llega su estado.' );

	// --- Rasgo del Menú del carrito --------------------------------------------------------------------------------------
	$menu = "<div class=\"elementor-menu-cart__wrapper\">\n\t\t\t\t\t\t\t<div class=\"elementor-menu-cart__toggle_wrapper\">\n\t\t\t\t\t<div class=\"elementor-menu-cart__container elementor-lightbox\" aria-hidden=\"true\">\n\t\t\t\t\t\t<div class=\"elementor-menu-cart__main\" aria-hidden=\"true\">\n\t\t\t\t\t\t\t\t\t<div class=\"elementor-menu-cart__close-button\">\n\t\t\t\t\t</div>\n\t\t\t\t\t\t\t\t<div class=\"widget_shopping_cart_content\">\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<div class=\"elementor-menu-cart__toggle elementor-button-wrapper\">\n\t\t\t<a id=\"elementor-menu-cart__toggle_button\" href=\"#\" class=\"elementor-menu-cart__toggle_button elementor-button elementor-size-sm\" aria-expanded=\"false\"></a>\n\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t</div> <!-- close elementor-menu-cart__wrapper -->\n";
	$editor_menu = str_replace( "<div class=\"widget_shopping_cart_content\">\n", "<div class=\"widget_shopping_cart_content\"><div class=\"woocommerce-mini-cart\"><div class=\"item\"></div></div><p class=\"total\"></p><div class=\"buttons\"></div>\n", $menu );
	class WT_Menu_Cart {
		public $settings; public $controls = array(); public $sections = array(); public $id;
		public function __construct( $settings = array(), $id = 'menu123' ) { $this->settings = $settings; $this->id = $id; }
		public function get_name() { return 'woocommerce-menu-cart'; }
		public function get_id() { return $this->id; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_controls( $id = null ) { return null === $id ? $this->controls : ( $this->controls[ $id ] ?? null ); }
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function add_group_control( $type, $args ) { $this->controls[ $args['name'] ] = $args + array( 'group' => $type ); }
		public function start_controls_section( $id, $args ) { $this->sections[ $id ] = $args; }
		public function end_controls_section() {}
	}
	$render = function ( $settings, $content = null, $title = '' ) use ( $menu ) {
		$widget  = new WT_Menu_Cart( $settings + array( 'digitalisimo_cart_title' => $title ) );
		$content = null === $content ? $menu : $content;
		// Como el núcleo: los filtros de render_content por prioridad (Cart_Title en 10, el rasgo en 11).
		$content = \Digitalisimo\Elements\Cart_Title::render_content( $content, $widget );
		return Mini_Cart_Extras::render_content( $content, $widget );
	};
	wt_reset();
	check( $menu === $render( array() ), 'Sin interruptores, el Menú del carrito pinta el mismo HTML.' );
	check( $menu === $render( array( 'digitalisimo_cart_bar' => '', 'digitalisimo_cart_coupon' => '' ) ), 'Con los interruptores vacíos (apagados), igual.' );
	$top = $render( array( 'digitalisimo_cart_bar' => 'yes', 'digitalisimo_cart_coupon' => 'yes' ), null, 'Tu carrito' );
	$t = strpos( $top, 'digi-cart-title' );
	$b = strpos( $top, 'data-digi-ship' );
	$c = strpos( $top, 'data-digi-coupon' );
	$w = strpos( $top, 'widget_shopping_cart_content' );
	check( false !== $t && $t < $b && $b < $c && $c < $w, '«Arriba»: título de Cart_Title, barra, cupón y después el contenido del carrito.' );
	check( false !== strpos( $top, 'for="digi-coupon-menu123"' ), 'El formulario lleva el uid de la instancia.' );
	$second = Mini_Cart_Extras::render_content( $menu, new WT_Menu_Cart( array( 'digitalisimo_cart_coupon' => 'yes' ) ) );
	check( false !== strpos( $second, 'for="digi-coupon-menu123-2"' ), 'La misma instancia dos veces en la página: dos uid distintos.' );
	foreach ( array( $menu, $editor_menu ) as $i => $source ) {
		$bottom = $render( array( 'digitalisimo_cart_coupon' => 'yes', 'digitalisimo_cart_extras_position' => 'bottom' ), $source );
		$main   = strpos( $bottom, 'elementor-menu-cart__main' );
		$extras = strpos( $bottom, 'digi-cart-extras' );
		check( $extras > strpos( $bottom, 'widget_shopping_cart_content' ) && $extras < strpos( $bottom, 'elementor-menu-cart__toggle elementor-button-wrapper' ), '«Abajo»: después del contenido del carrito y antes del botón (' . ( $i ? 'editor' : 'front' ) . ').' );
		check( 1 === preg_match( '#<div class="digi-cart-extras">.*?</p></div></div>\s*</div>\s*</div>\s*<div class="elementor-menu-cart__toggle elementor-button-wrapper">#s', $bottom ), '«Abajo»: justo antes del cierre de .elementor-menu-cart__main (' . ( $i ? 'editor' : 'front' ) . ').' );
		check( strlen( $bottom ) > strlen( $source ) && $main < $extras, 'El panel sigue entero.' );
	}
	$hidden = str_replace( '<div class="elementor-menu-cart__main" aria-hidden="true">', '', $menu );
	check( $hidden === $render( array( 'digitalisimo_cart_bar' => 'yes' ), $hidden ), 'Sin panel (carrito oculto por WooCommerce), nada se inserta.' );
	$other = new class() { public function get_name() { return 'heading'; } };
	check( 'x' === Mini_Cart_Extras::render_content( 'x', $other ), 'Otro widget no cambia.' );
	$GLOBALS['wt']['edit'] = true;
	$editor = $render( array( 'digitalisimo_cart_bar' => 'yes' ) );
	check( false === strpos( $editor, 'data-digi-ship data-t-progress="Te faltan {restante} para el envío gratis" data-t-done="Tienes envío gratis" hidden' ), 'En el editor, la barra se ve para poder darle estilo.' );
	$GLOBALS['wt']['edit'] = false;

	// --- Controles añadidos a un widget existente (D33, V.12.1, V.12.2) ---------------------------------------------------
	$element = new WT_Menu_Cart();
	Mini_Cart_Extras::before_section_end( $element, 'section_cart_style' );
	check( array() === $element->controls, 'Los controles van en la sección «Carrito» de contenido, no en otra.' );
	Mini_Cart_Extras::before_section_end( $element, 'section_cart' );
	Mini_Cart_Extras::after_section_end( $element, 'section_style_messages' );
	$count = count( $element->controls );
	Mini_Cart_Extras::before_section_end( $element, 'section_cart' );
	Mini_Cart_Extras::after_section_end( $element, 'section_style_messages' );
	check( $count === count( $element->controls ), 'Registrar dos veces no duplica controles.' );
	foreach ( $element->controls as $id => $control ) {
		check( ! array_key_exists( 'default', $control ), 'Ningún control añadido lleva default (' . $id . ').' );
		if ( isset( $control['selectors'] ) || isset( $control['group'] ) ) {
			check( isset( $control['condition'] ) || isset( $element->sections[ Mini_Cart_Extras::STYLE ]['conditions'] ), 'Cada control de estilo depende de un interruptor (' . $id . ').' );
		}
	}
	check( 'or' === $element->sections[ Mini_Cart_Extras::STYLE ]['conditions']['relation'], 'La sección de estilo sólo aparece con la barra o el cupón.' );
	foreach ( array( 'digitalisimo_cart_bar', 'digitalisimo_cart_coupon' ) as $switch ) {
		$assets = $element->controls[ $switch ]['assets'];
		check( 'digitalisimo-woo-cart-extras' === $assets['styles'][0]['name'] && 'digitalisimo-woo-cart-extras' === $assets['scripts'][0]['name'] && 'yes' === $assets['scripts'][0]['conditions']['terms'][0]['value'] && $switch === $assets['scripts'][0]['conditions']['terms'][0]['name'], 'Recursos por la declaración assets del interruptor (' . $switch . ').' );
	}
	check( array( 'digitalisimo_cart_bar' => 'yes' ) === $element->controls['digitalisimo_cart_bar_progress']['condition'] && array( 'digitalisimo_cart_bar' => 'yes' ) === $element->controls['digitalisimo_cart_bar_done']['condition'], 'Los textos de la barra sólo con su interruptor.' );

	// --- Widget de la barra ---------------------------------------------------------------------------------------------
	$widget = ( new Shipping_Bar_Widget() )->boot();
	check( array( 'digitalisimo-woo-cart-extras' ) === $widget->get_style_depends() && array( 'digitalisimo-woo-cart-extras' ) === $widget->get_script_depends(), 'El widget declara la capacidad cart-extras.' );
	check( ! isset( $widget->controls['threshold'] ) && ! preg_grep( '/umbral|min_amount|threshold/i', array_keys( $widget->controls ) ), 'Sin control de umbral: el umbral es el de WooCommerce (D13).' );
	check( Markup::bar( Markup::T_PROGRESS, Markup::T_DONE ) === $widget->html(), 'El widget pinta la barra neutra con sus textos.' );

	// --- Recursos y textos -------------------------------------------------------------------------------------------------
	$js  = (string) file_get_contents( $root . '/assets/js/cart-extras.js' );
	$css = (string) file_get_contents( $root . '/assets/css/cart-extras.css' );
	check( strlen( $js ) <= 8192 && strlen( $css ) <= 4096, 'cart-extras.js (' . strlen( $js ) . ' B) y cart-extras.css (' . strlen( $css ) . ' B) en su tope.' );
	check( false === stripos( $js, 'jquery' ) && false === strpos( $css, '--e-global' ) && false === strpos( $css, 'url(' ), 'Sin jQuery, sin la paleta global ni url().' );
	$php = '';
	foreach ( glob( $root . '/modules/digitalisimo-woo/cart/*.php' ) as $file ) { $php .= file_get_contents( $file ); }
	foreach ( array( 'personas', 'viendo', 'quedan pocos', 'últimas unidades', 'oferta termina', 'date prisa', 'solo hoy', 'sólo hoy', 'cuenta atrás' ) as $phrase ) {
		check( false === stripos( $php . $js, $phrase ), 'Ninguna urgencia inventada: «' . $phrase . '».' );
	}
	check( ! preg_match( '/<script(?!\s+type="text\/template")/', $php ) && false === strpos( str_replace( '\\\\', '\\', $php ), 'WooCommerce\\Internal' ), 'Ningún <script> propio ni clases internas de WooCommerce.' );
	$names = preg_replace( array( '/\\\\*Elementor(?:Pro)?(?:\\\\+[A-Za-z_]+)+/', '/[a-z_-]*elementor[A-Za-z_-]*/' ), '', $php . $js );
	check( false === stripos( $names, 'elementor' ), 'Ningún texto ni comentario nombra al proveedor del núcleo (sólo identificadores).' );

	echo "DIGITALÍSIMO Elements: barra de envío gratis, cupón y rasgo del Menú del carrito de F4-Woo validados.\n";
}
