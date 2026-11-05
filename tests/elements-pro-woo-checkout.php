<?php
/**
 * Finalizar compra (`digitalisimo-woo-checkout`), el widget propio que sustituye al Checkout de PRO (C7 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c7.md, fila O, fork K), frente a `modules/woocommerce/widgets/
 * checkout.php` cargado a la vez sobre el entorno de `tests/elements-theme-elements/stubs.php`:
 *
 * - registro y recursos: nombre, título y archivo en `Woo\Registry`, categoría de PRO; dos hojas propias de ≤ 4 KB cada
 *   una (foco visible, movimiento reducido, sin el kit ni `url()`), un script propio ≤ 8 KB sin jQuery ni el front de PRO
 *   y los scripts y la hoja de WooCommerce que pedía PRO; nada vetado por el empaquetado ni dominios de proveedores;
 * - controles: los mismos que PRO, con y sin las funciones opcionales de WooCommerce (recordatorio de acceso, envío,
 *   cupones, alta desde el pago, sólo dirección de facturación), con el mismo tipo, valor por defecto (los textos, en
 *   español: el mapa completa los de PRO), opciones, condiciones, grupos y orden, y las mismas reglas en sus selectores
 *   con las clases propias;
 * - salida: con un `[woocommerce_checkout]` de prueba que dispara los ganchos de WooCommerce, los dos widgets envuelven
 *   lo mismo en las mismas columnas y secciones (clases de PRO pasadas a las propias), cambian los mismos textos y campos,
 *   y el propio pinta botones con `aria-expanded`/`aria-controls`, paneles con `hidden` y los textos de sus controles;
 * - mapa: cada control de PRO declarado (también los campos de los repetidores) y conversión de ajustes reales (por
 *   defecto, repetidores, globales, dinámicos, CSS personalizado) sin `pro.unmapped`, con los textos de PRO completados.
 */
namespace {
	$GLOBALS['h_wp'] = array( 'features' => array( 'e_optimized_markup' ), 'options' => array(), 'hooks' => array() );
	$GLOBALS['ck']   = array( 'logged_in' => false, 'coupons' => true, 'zones' => 1, 'billing_only' => false, 'needs_payment' => true );

	// Ganchos de verdad (ordenados por prioridad), para que el atajo de prueba dispare las retrollamadas de los widgets.
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['h_wp']['hooks'][ $hook ][ $priority ][] = $cb; return true; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
	function remove_filter( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['h_wp']['hooks'][ $hook ][ $priority ] ?? array() as $i => $f ) {
			if ( $f === $cb ) {
				unset( $GLOBALS['h_wp']['hooks'][ $hook ][ $priority ][ $i ] );
				return true;
			}
		}
		return false;
	}
	function remove_action( $hook, $cb, $priority = 10 ) { $GLOBALS['ck']['removed'][] = is_string( $cb ) ? $cb : $hook; return remove_filter( $hook, $cb, $priority ); }
	function apply_filters( $hook, $value, ...$args ) {
		$list = $GLOBALS['h_wp']['hooks'][ $hook ] ?? array();
		ksort( $list );
		foreach ( $list as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$value = call_user_func( $cb, $value, ...$args );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$list = $GLOBALS['h_wp']['hooks'][ $hook ] ?? array();
		ksort( $list );
		foreach ( $list as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				call_user_func( $cb, ...$args );
			}
		}
	}
	function wc_t( $text ) { return apply_filters( 'gettext', $text, $text, 'woocommerce' ); }
	// Un `[woocommerce_checkout]` mínimo con la estructura y los ganchos de las plantillas de WooCommerce.
	function do_shortcode( $code ) {
		$GLOBALS['ck']['shortcodes'][] = $code;
		ob_start();
		echo '<div class="woocommerce">';
		do_action( 'woocommerce_before_checkout_form' );
		echo '<form name="checkout" class="checkout woocommerce-checkout">';
		do_action( 'woocommerce_checkout_before_customer_details' );
		$field = apply_filters( 'woocommerce_form_field_args', array( 'label' => 'First name', 'placeholder' => '', 'default' => '' ), 'billing_first_name', '' );
		echo '<div class="col2-set" id="customer_details"><div class="col-1"><div class="woocommerce-billing-fields"><h3>' . wc_t( 'Billing details' ) . '</h3><p class="form-row form-row-first"><label>' . $field['label'] . '</label><input class="input-text" placeholder="' . $field['placeholder'] . '" /></p></div></div>';
		echo '<div class="col-2"><div class="woocommerce-additional-fields"><h3>' . wc_t( 'Additional information' ) . '</h3></div></div></div>';
		do_action( 'woocommerce_checkout_after_customer_details' );
		do_action( 'woocommerce_checkout_before_order_review_heading' );
		echo '<h3 id="order_review_heading">' . wc_t( 'Your order' ) . '</h3>';
		do_action( 'woocommerce_checkout_before_order_review' );
		echo '<div id="order_review" class="woocommerce-checkout-review-order">';
		do_action( 'woocommerce_checkout_order_review' );
		echo '</div>';
		do_action( 'woocommerce_checkout_after_order_review' );
		echo '</form></div>';
		return (string) ob_get_clean();
	}
	function get_option( $name, $default = false ) { return $GLOBALS['h_wp']['options'][ $name ] ?? $default; }
	function wc_coupons_enabled() { return $GLOBALS['ck']['coupons']; }
	function wc_ship_to_billing_address_only() { return $GLOBALS['ck']['billing_only']; }
	function wc_terms_and_conditions_page_id() { return 9; }
	function get_permalink( $id = 0 ) { return 'https://sitio.test/' . ( $id ? 'terminos/' : 'pago/' ); }
	function is_user_logged_in() { return $GLOBALS['ck']['logged_in']; }
	function wp_get_current_user() { return (object) array( 'ID' => 0 ); }
	function wp_set_current_user( $id ) { $GLOBALS['ck']['users'][] = $id; }
	function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . $name . '" value="n" />'; }
	function wp_lostpassword_url() { return 'https://sitio.test/perdida/'; }
	function get_the_ID() { return 21; }
	class WC_Shipping_Zones { public static function get_zones() { return array_fill( 0, $GLOBALS['ck']['zones'], array() ); } }
	class H_Cart { public function needs_payment() { return $GLOBALS['ck']['needs_payment']; } }
	function WC() { return (object) array( 'cart' => new H_Cart() ); }
	function plugins_url( $path = '', $file = '' ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function wp_style_is( $h, $s = 'enqueued' ) { return false; }
	function wp_script_is( $h, $s = 'enqueued' ) { return false; }
	function wp_register_style( ...$a ) { return true; }
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	// El entorno común, con pestañas en el repetidor (los repetidores del pago las usan; el archivo común no se toca).
	$ck_stubs = (string) file_get_contents( __DIR__ . '/elements-theme-elements/stubs.php' );
	$ck_stubs = str_replace( '__DIR__', var_export( __DIR__ . '/elements-theme-elements', true ), $ck_stubs );
	$ck_stubs = str_replace( 'public function get_controls() { return $this->fields; }', 'public function get_controls() { return $this->fields; } public function start_controls_tabs( $n, $a = array() ) {} public function start_controls_tab( $n, $a = array() ) {} public function end_controls_tab() {} public function end_controls_tabs() {}', $ck_stubs );
	eval( '?>' . $ck_stubs ); // phpcs:ignore Squiz.PHP.Eval -- sólo pruebas.

	eval( 'namespace Elementor; class Controls_Stack { const RESPONSIVE_DESKTOP = "desktop"; const RESPONSIVE_TABLET = "tablet"; const RESPONSIVE_MOBILE = "mobile"; } class Plugin { public static $instance; }' ); // phpcs:ignore Squiz.PHP.Eval -- sólo pruebas.
	eval( 'namespace Elementor\Core\Breakpoints; class Manager { public static function get_default_config() { return array( "mobile" => array(), "tablet" => array() ); } }' ); // phpcs:ignore
	eval( 'namespace ElementorPro\Modules\Woocommerce\Classes; class Products_Renderer { const DEFAULT_COLUMNS_AND_ROWS = 4; }' ); // phpcs:ignore
	$ck_docs = new class() { public function get_current() { return new class() { public function get_main_id() { return 33; } }; } };
	\Elementor\Plugin::$instance = (object) array( 'experiments' => \ElementorPro\Plugin::elementor()->experiments, 'editor' => \ElementorPro\Plugin::elementor()->editor, 'preview' => \ElementorPro\Plugin::elementor()->preview, 'documents' => $ck_docs );

	$ck_root = dirname( __DIR__ ) . '/digitalisimo-elements/';
	require_once $ck_root . 'modules/woocommerce/traits/product-id-trait.php';
	require_once $ck_root . 'modules/woocommerce/widgets/base-widget.php';
	require_once $ck_root . 'modules/woocommerce/widgets/checkout.php';
	require_once $ck_root . 'modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();

	use Digitalisimo\Elements\Woo\Registry;
	use Digitalisimo\Elements\Woo\Store\Checkout;
	use ElementorPro\Modules\Woocommerce\Widgets\Checkout as Pro_Checkout;

	$checks = 0;
	$ok     = static function ( $cond, $message ) use ( &$checks ) {
		$checks++;
		h_check( $cond, $message );
	};
	$name = 'digitalisimo-woo-checkout';
	$map  = require h_path( 'modules/digitalisimo-convert/maps/woocommerce-checkout-page.php' );

	// 1. Registro y recursos.
	$own = h_widget( Checkout::class );
	$pro = h_widget( Pro_Checkout::class );
	$ok( $name === $own->get_name() && 'Finalizar compra' === $own->get_title() && array( 'woocommerce-elements' ) === $own->get_categories() && $pro->get_categories() === $own->get_categories() && $pro->get_icon() === $own->get_icon() && 'woocommerce' === $own->get_group_name(), 'Nombre, título, categoría, icono y grupo.' );
	$ok( isset( Registry::WIDGETS[ $name ] ) && 'store/class-checkout.php' === Registry::WIDGETS[ $name ]['file'] && 'Finalizar compra' === Registry::TITLES[ $name ] && ltrim( Checkout::class, '\\' ) === ltrim( Registry::WIDGETS[ $name ]['class'], '\\' ), 'En Registry::WIDGETS y TITLES.' );
	$ok( ! method_exists( $pro, 'has_widget_inner_wrapper' ) && ! ( new ReflectionClass( $own ) )->hasMethod( 'has_widget_inner_wrapper' ), 'Envoltura interior como PRO (la del núcleo: ninguno la cambia).' );
	$ok( array( 'select2', $name, $name . '-2' ) === $own->get_style_depends(), 'Hojas: la de WooCommerce que pedía PRO y las dos propias: ' . json_encode( $own->get_style_depends() ) );
	$ok( array( 'wc-checkout', 'wc-password-strength-meter', 'selectWoo', $name ) === $own->get_script_depends(), 'Scripts: los de WooCommerce que pedía PRO y el propio: ' . json_encode( $own->get_script_depends() ) );
	$vendors = json_decode( (string) file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );
	$hosts   = array_merge( (array) ( $vendors['hosts'] ?? array() ), (array) ( $vendors['future'] ?? array() ) );
	$host_re = '/(?<![a-z0-9-])(?:[a-z0-9-]+\.)*(?:' . implode( '|', array_map( static function ( $h ) { return preg_quote( $h, '/' ); }, $hosts ) ) . ')(?![a-z0-9-])/i';
	$js = (string) file_get_contents( h_path( Registry::WIDGETS[ $name ]['js'] ) );
	$ok( strlen( $js ) <= 8192 && ! preg_match( '/\bjQuery\s*[.(]|\$\(|elementorProFrontend|pro-elements-handlers|elementor-pro-frontend/', $js ), 'Script propio ≤ 8 KB, sin jQuery ni el front de PRO (' . strlen( $js ) . ' B).' );
	foreach ( array( h_path( 'modules/digitalisimo-woo/store/class-checkout.php' ), h_path( 'modules/digitalisimo-woo/store/assets/checkout.css' ), h_path( 'modules/digitalisimo-woo/store/assets/checkout-2.css' ), h_path( 'modules/digitalisimo-woo/store/assets/checkout.js' ), h_path( 'modules/digitalisimo-convert/maps/woocommerce-checkout-page.php' ) ) as $file ) {
		$source = (string) file_get_contents( $file );
		$ok( ! preg_match( H_VETOED, $source ) && ! preg_match( $host_re, $source ), basename( $file ) . ': nada vetado por el empaquetado ni dominios de proveedores.' );
	}
	$code = h_code( h_path( 'modules/digitalisimo-woo/store/class-checkout.php' ) );
	$ok( ! preg_match( '/ElementorPro\\\\|wp_enqueue_script|<img|<script|<style|ld\+json|elementor-pro\'/', $code ), 'Sin clases de PRO, scripts encolados a mano, <img>, <script>, <style> ni JSON-LD.' );
	preg_match_all( "/(?:esc_html__|esc_attr__|__)\( '((?:[^'\\\\]|\\\\.)*)', '([a-z-]+)' \)/", $code, $strings );
	$ok( count( $strings[2] ) > 300 && array( 'pro-elements' ) === array_values( array_unique( $strings[2] ) ), 'Textos traducibles con el dominio pro-elements (' . count( $strings[2] ) . ').' );
	$ok( ! preg_grep( '/^(Typography|Alignment|Section Title|Your Order|Billing Details|Payment|Coupon)$/', $strings[1] ), 'Textos del panel en español.' );

	// 2. Controles, en cada combinación de funciones de WooCommerce.
	$texts = array( 'billing_details_section_title', 'shipping_details_section_title', 'additional_information_section_title', 'order_summary_section_title', 'returning_customer_section_title', 'returning_customer_link_text', 'create_account_text', 'coupon_section_title_text', 'coupon_section_title_link_text', 'terms_conditions_message_text', 'terms_conditions_link_text' );
	$skip  = array( 'sticky_right_column.render_type' => true, 'billing_details_form_fields.default' => true, 'shipping_details_form_fields.default' => true, 'additional_information_form_fields.default' => true );
	foreach ( $texts as $text ) {
		$skip[ $text . '.default' ] = true;
	}
	$combos = array(
		'por defecto'                  => array(),
		'todo activo'                  => array( 'woocommerce_enable_checkout_login_reminder' => 'yes', 'woocommerce_enable_signup_and_login_from_checkout' => 'yes' ),
		'sin cupones ni envío'         => array( '#coupons' => false, '#zones' => 0, 'woocommerce_enable_checkout_login_reminder' => 'no' ),
		'sólo dirección de facturación' => array( '#billing_only' => true ),
	);
	$all_pro = array();
	foreach ( $combos as $label => $env ) {
		$GLOBALS['h_wp']['options'] = array( 'woocommerce_enable_checkout_login_reminder' => 'no', 'woocommerce_enable_signup_and_login_from_checkout' => 'no' );
		$GLOBALS['ck']              = array( 'logged_in' => false, 'coupons' => true, 'zones' => 1, 'billing_only' => false, 'needs_payment' => true );
		foreach ( $env as $key => $value ) {
			if ( '#' === $key[0] ) {
				$GLOBALS['ck'][ substr( $key, 1 ) ] = $value;
			} else {
				$GLOBALS['h_wp']['options'][ $key ] = $value;
			}
		}
		$pro  = h_widget( Pro_Checkout::class );
		$own  = h_widget( Checkout::class );
		$diff = h_compare_widgets( $pro, $own, $skip );
		$ok( ! $diff, "Controles ($label) distintos de los de PRO:\n" . implode( "\n", $diff ) );
		$ok( 'template' === $own->controls['sticky_right_column']['render_type'] && 'none' === $pro->controls['sticky_right_column']['render_type'], "Columna fija ($label): el propio se vuelve a pintar (se resuelve en el servidor)." );
		foreach ( $pro->controls + $pro->groups as $control => $args ) {
			$mine = $own->controls[ $control ] ?? $own->groups[ $control ];
			foreach ( array( 'selectors', 'selector' ) as $part ) {
				if ( ! isset( $args[ $part ] ) ) {
					continue;
				}
				$pro_part = is_array( $args[ $part ] ) ? $args[ $part ] : array( $args[ $part ] => '' );
				$own_part = is_array( $mine[ $part ] ?? null ) ? $mine[ $part ] : array( (string) ( $mine[ $part ] ?? '' ) => '' );
				$ok( array_values( $pro_part ) === array_values( $own_part ) && count( $pro_part ) === count( $own_part ), "$control.$part ($label): las mismas reglas." );
				foreach ( array_keys( $own_part ) as $selector ) {
					$ok( ! preg_match( '/(?<![a-z0-9_-])(e-[a-z]|elementor-(?!animation))/', $selector ), "$control.$part ($label): selector con clases propias ($selector)." );
				}
			}
			$all_pro[ $control ] = true;
		}
		$map_here = require h_path( 'modules/digitalisimo-convert/maps/woocommerce-checkout-page.php' );
		foreach ( $texts as $text ) {
			if ( isset( $pro->controls[ $text ] ) ) {
				$ok( isset( $map_here['defaults'][ $text ] ) && $pro->controls[ $text ]['default'] === $map_here['defaults'][ $text ] && '' !== $own->controls[ $text ]['default'] && $own->controls[ $text ]['default'] !== $pro->controls[ $text ]['default'], "$text ($label): texto por defecto propio en español; el mapa completa el de PRO." );
			}
		}
		foreach ( array( 'billing_details_form_fields', 'shipping_details_form_fields', 'additional_information_form_fields' ) as $repeater ) {
			if ( isset( $pro->controls[ $repeater ] ) ) {
				$pro_rows = $pro->controls[ $repeater ]['default'];
				$own_rows = $own->controls[ $repeater ]['default'];
				$ok( $pro_rows === $map['repeaters'][ $repeater ]['default_rows'], "$repeater ($label): el mapa completa las filas por defecto de PRO." );
				$ok( array_column( $pro_rows, 'field_key' ) === array_column( $own_rows, 'field_key' ) && array_column( $pro_rows, 'repeater_state' ) === array_column( $own_rows, 'repeater_state' ), "$repeater ($label): las mismas filas y estados, con etiquetas propias." );
			}
		}
	}

	// 3. Mapa: cada control de PRO (en cualquier combinación) y cada campo de repetidor declarado.
	$declared = array_merge( (array) $map['keep'], array_keys( (array) $map['groups'] ), (array) $map['ui'], array_keys( (array) $map['repeaters'] ), array_keys( (array) ( $map['drop'] ?? array() ) ), array_keys( (array) ( $map['approx'] ?? array() ) ) );
	$missing  = array_diff( array_keys( $all_pro ), $declared );
	$ok( ! $missing, 'El mapa no declara ' . implode( ', ', $missing ) );
	$GLOBALS['h_wp']['options'] = array( 'woocommerce_enable_checkout_login_reminder' => 'yes', 'woocommerce_enable_signup_and_login_from_checkout' => 'yes' );
	$GLOBALS['ck']              = array( 'logged_in' => false, 'coupons' => true, 'zones' => 1, 'billing_only' => false, 'needs_payment' => true );
	$pro = h_widget( Pro_Checkout::class );
	$own = h_widget( Checkout::class );
	foreach ( $map['repeaters'] as $repeater => $spec ) {
		$fields = array_keys( $pro->controls[ $repeater ]['fields'] );
		$ok( ! array_diff( $fields, array_merge( $spec['keep'], $spec['ui'] ) ), "$repeater: el mapa declara cada campo (" . implode( ',', $fields ) . ').' );
	}
	$ok( 'woocommerce-checkout-page' === $map['source'] && $name === $map['target'] && 'hide' === $map['panel'] && in_array( $map['status'], array( 'draft', 'ready' ), true ), 'Mapa hacia el propio, oculto del panel.' );
	$ok( '.elementor-widget-' . $name === $map['classes']['.elementor-widget-woocommerce-checkout-page'], 'El CSS personalizado pasa a la clase del widget propio.' );

	// 4. Salida con los mismos ganchos de WooCommerce.
	$render = static function ( $widget, array $settings, array $env = array() ) {
		$GLOBALS['ck']           = array_merge( array( 'logged_in' => false, 'coupons' => true, 'zones' => 1, 'billing_only' => false, 'needs_payment' => true ), $env );
		$GLOBALS['h_wp']['hooks'] = array();
		add_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		add_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		add_action( 'woocommerce_checkout_order_review', static function () { echo '<table class="shop_table woocommerce-checkout-review-order-table"><tr><td>' . wc_t( 'Product' ) . '</td></tr></table>'; }, 10 );
		add_action( 'woocommerce_checkout_order_review', static function () { echo '<div id="payment" class="woocommerce-checkout-payment"><p>' . apply_filters( 'woocommerce_get_terms_and_conditions_checkbox_text', 'I have read' ) . '</p><button id="place_order">Place order</button></div>'; }, 20 );
		$html = h_render( $widget, $settings );
		return array( $html, $GLOBALS['h_wp']['hooks'] );
	};
	$classes = static function ( string $html ) use ( $map ): array {
		preg_match_all( '/class="([^"]*)"/', $html, $m );
		$out = array();
		foreach ( $m[1] as $list ) {
			foreach ( preg_split( '/\s+/', trim( $list ) ) as $class ) {
				$out[] = '.' . $class;
			}
		}
		return $out;
	};
	$mapped = static function ( array $list ) use ( $map ): array {
		$repl = $map['classes'];
		uksort( $repl, static function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		return array_map( static function ( $c ) use ( $repl ) { return $repl[ $c ] ?? $c; }, $list );
	};
	// Textos fijos de los formularios de acceso y cupón (no son controles): los propios, en español (diferencia declarada).
	$fixed = array( 'If you have shopped with us before, please enter your details below. If you are a new customer, please proceed to the Billing section.' => 'Si ya has comprado con nosotros, introduce tus datos abajo. Si eres un cliente nuevo, continúa en la sección de facturación.', 'Email' => 'Correo electrónico', 'Password' => 'Contraseña', 'Login' => 'Acceder', 'Remember me' => 'Recordarme', 'Lost your password?' => '¿Has olvidado tu contraseña?', 'If you have a coupon code, please apply it below.' => 'Si tienes un código de cupón, aplícalo abajo.', 'Apply' => 'Aplicar' );
	$text = static function ( string $html, bool $pro = false ) use ( $fixed ): string {
		$t = html_entity_decode( strip_tags( preg_replace( '/<!--.*?-->/s', '', $html ) ) );
		if ( $pro ) {
			uksort( $fixed, static function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
			$t = strtr( $t, $fixed );
		}
		return preg_replace( '/\s+/', '', $t );
	};
	$map          = require h_path( 'modules/digitalisimo-convert/maps/woocommerce-checkout-page.php' ); // Con todo activo.
	$pro_defaults = $map['defaults'] + array( 'forms_buttons_hover_animation' => '', 'purchase_button_hover_animation' => '', 'billing_details_form_fields' => $map['repeaters']['billing_details_form_fields']['default_rows'], 'shipping_details_form_fields' => $map['repeaters']['shipping_details_form_fields']['default_rows'], 'additional_information_form_fields' => $map['repeaters']['additional_information_form_fields']['default_rows'] );
	foreach ( array(
		'por defecto'          => array( array(), array() ),
		'una columna y fija'   => array( array( 'checkout_layout' => 'one-column', 'sticky_right_column' => 'yes', 'sticky_right_column_offset' => 40 ), array() ),
		'dos columnas y fija'  => array( array( 'sticky_right_column' => 'yes', 'sticky_right_column_offset' => 40, 'purchase_button_hover_animation' => 'grow', 'forms_buttons_hover_animation' => 'shrink' ), array() ),
		'sin cupón'            => array( array( 'coupon_section_display' => '' ), array() ),
		'con sesión'           => array( array(), array( 'logged_in' => true ) ),
		'textos'               => array( array( 'billing_details_section_title' => 'Facturación', 'order_summary_section_title' => 'Tu compra', 'terms_conditions_message_text' => 'Acepto los', 'terms_conditions_link_text' => 'términos' ), array() ),
	) as $label => list( $settings, $env ) ) {
		$pro = h_widget( Pro_Checkout::class ); // Instancias nuevas: PRO guarda sus textos de la primera pintura.
		$own = h_widget( Checkout::class );
		list( $a, $hooks_a ) = $render( $pro, $settings + $pro_defaults, $env );
		list( $b, $hooks_b ) = $render( $own, $settings + $pro_defaults, $env );
		$ok( ! array_filter( $hooks_b['woocommerce_checkout_before_customer_details'] ?? array() ) && ! array_filter( $hooks_b['gettext'] ?? array() ) && ! array_filter( $hooks_b['woocommerce_form_field_args'] ?? array() ), "Salida ($label): el propio quita sus ganchos al terminar." );
		$ok( str_starts_with( $b, '<div class="digi-co" data-digi-page="33"' ), "Salida ($label): envoltura propia con el documento que contiene el widget: " . substr( $b, 0, 80 ) );
		// Las clases de PRO, pasadas a las propias, en el mismo orden (sin la envoltura propia).
		$ca = $mapped( $classes( $a ) );
		$cb = array_values( array_filter( $classes( $b ), static function ( $c ) { return '.digi-co' !== $c && '.digi-co__sticky--active' !== $c; } ) );
		$ca = array_values( array_filter( $ca, static function ( $c ) { return '.digi-co__sticky--active' !== $c; } ) );
		$ok( $ca === $cb, "Salida ($label): las mismas clases, en el mismo orden:\nPRO " . implode( ' ', $ca ) . "\npropio " . implode( ' ', $cb ) );
		$ok( $text( $a, true ) === $text( $b ), "Salida ($label): los mismos textos:\nPRO " . $text( $a ) . "\npropio " . $text( $b ) );
	}
	// Columna fija resuelta en el servidor, sólo en dos columnas.
	list( $b ) = $render( $own, array( 'sticky_right_column' => 'yes', 'sticky_right_column_offset' => 40 ) + $pro_defaults );
	$ok( str_contains( $b, 'class="digi-co__col-inner digi-co__sticky digi-co__sticky--active" style="--digi-co-top:40px"' ), 'Columna derecha fija: clase activa y desplazamiento.' );
	list( $b ) = $render( $own, array( 'checkout_layout' => 'one-column', 'sticky_right_column' => 'yes' ) + $pro_defaults );
	$ok( ! str_contains( $b, 'digi-co__sticky--active' ), 'Columna fija: no en una columna (como la condición del control).' );
	// Acceso y cupón accesibles, con los textos de sus controles.
	$GLOBALS['h_wp']['options']['woocommerce_enable_checkout_login_reminder'] = 'yes';
	list( $b ) = $render( $own, array( 'returning_customer_section_title' => '¿Cliente?', 'returning_customer_link_text' => 'Entra', 'coupon_section_title_text' => '¿Cupón?', 'coupon_section_title_link_text' => 'Escríbelo', 'purchase_button_hover_animation' => 'grow' ) + $pro_defaults );
	$ok( preg_match( '/¿Cliente\?\s*<button type="button" class="digi-co__show-login" aria-controls="digi-co-login-abc1234" aria-expanded="false">Entra<\/button>/', $b ) && str_contains( $b, '<div class="digi-co__login-form" id="digi-co-login-abc1234" hidden>' ), 'Acceso: botón con aria-expanded y aria-controls, panel oculto, textos del control.' );
	$ok( preg_match( '/¿Cupón\? <button type="button" class="digi-co__show-coupon" aria-controls="digi-co-coupon-abc1234" aria-expanded="false">Escríbelo<\/button>/', $b ) && str_contains( $b, '<div class="digi-co__coupon-form" id="digi-co-coupon-abc1234" hidden>' ) && str_contains( $b, '<label class="digi-co__coupon-desc" for="coupon_code">' ), 'Cupón: botón con aria-expanded y aria-controls, panel oculto, etiqueta del campo.' );
	$ok( str_contains( $b, 'data-digi-ajax="https://sitio.test/wp-admin/admin-ajax.php"' ) && str_contains( $b, 'data-digi-animation="elementor-animation-grow"' ), 'Datos para el script: acceso por AJAX y animación del botón de compra.' );
	$ok( in_array( 'woocommerce_checkout_login_form', $GLOBALS['ck']['removed'], true ) && in_array( 'woocommerce_checkout_coupon_form', $GLOBALS['ck']['removed'], true ), 'Los formularios de acceso y cupón de WooCommerce se sustituyen, como en PRO.' );
	// En el editor, los dos paneles abiertos y una persona sin sesión.
	$GLOBALS['h_wp']['edit_mode'] = true;
	$GLOBALS['ck']['users']        = array();
	list( $b ) = $render( $own, $pro_defaults );
	$ok( str_contains( $b, 'aria-expanded="true"' ) && ! preg_match( '/ hidden>/', $b ) && array( 0, 0 ) === $GLOBALS['ck']['users'], 'Editor: paneles abiertos y la vista sin sesión, como PRO.' );
	$GLOBALS['h_wp']['edit_mode'] = false;
	// Textos de WooCommerce cambiados por los controles y campos de los repetidores.
	list( $b ) = $render( $own, array( 'billing_details_section_title' => 'Facturación', 'billing_details_form_fields' => array( array( 'field_key' => 'billing_first_name', 'label' => 'Tu nombre', 'placeholder' => 'Ana', 'default' => '' ) ) ) + $pro_defaults );
	$ok( str_contains( $b, '<h3>Facturación</h3>' ) && str_contains( $b, '<label>Tu nombre</label><input class="input-text" placeholder="Ana" />' ), 'Textos y campos de WooCommerce cambiados por los controles.' );
	list( $b ) = $render( $own, array( 'terms_conditions_message_text' => 'Acepto los', 'terms_conditions_link_text' => 'términos' ) + $pro_defaults );
	$ok( str_contains( $b, 'Acepto los <a href="https://sitio.test/terminos/" class="woocommerce-terms-and-conditions-link" target="_blank">términos</a>' ), 'Texto de términos y condiciones de los controles.' );

	// 5. Conversión de ajustes reales.
	// Como `h_convert()` de los stubs, salvo que las filas de los repetidores llevan además `field_key` y `field_label`, que
	// no son controles (en PRO tampoco): el widget las lee para saber qué campo de WooCommerce cambia cada fila.
	$ck_convert = static function ( string $source, $own, array $cases ): array {
		$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
		foreach ( $cases as $id => $settings ) {
			$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $source, 'settings' => $settings, 'elements' => array() );
		}
		$log       = array();
		$converted = \Digitalisimo\Elements\Convert\Converter::tree( $tree, $log, true );
		h_check( count( $cases ) === count( $log ), "$source: se convierte cada widget del árbol." );
		$lands = static function ( $key, array $controls, array $groups = array() ) {
			$base = \Digitalisimo\Elements\Convert\Translator::base( (string) $key );
			if ( isset( $controls[ $base ] ) || \Digitalisimo\Elements\Convert\Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', \Digitalisimo\Elements\Convert\Translator::MARKER ), true ) ) {
				return true;
			}
			foreach ( array_keys( $groups ) as $group ) {
				if ( 0 === strpos( (string) $key, $group . '_' ) ) {
					return true;
				}
			}
			return false;
		};
		$out = array();
		foreach ( $converted[0]['elements'] as $element ) {
			h_check( $own->get_name() === $element['widgetType'], $element['id'] . ': pasa al widget propio.' );
			$out[ $element['id'] ] = $element['settings'];
			foreach ( $element['settings'] as $key => $value ) {
				h_check( $lands( $key, $own->controls, $own->groups ), $element['id'] . ": la clave $key no cae en un control propio." );
				if ( isset( $own->controls[ $key ] ) && 'repeater' === $own->controls[ $key ]['type'] ) {
					foreach ( (array) $value as $row ) {
						foreach ( array_keys( (array) $row ) as $field ) {
							h_check( $lands( $field, $own->controls[ $key ]['fields'] ) || in_array( $field, array( 'field_key', 'field_label' ), true ), $element['id'] . ": el campo $key.$field no cae en un campo propio." );
						}
					}
				}
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
					h_check( $lands( $key, $own->controls, $own->groups ), $element['id'] . ": $bag.$key no cae en un control propio." );
				}
			}
		}
		$codes = array();
		foreach ( $log as $entry ) {
			foreach ( $entry['codes'] as $code ) {
				$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
			}
		}
		h_check( ! preg_grep( '/:pro\.unmapped:/', $codes ), "$source: claves sin declarar: " . implode( ', ', preg_grep( '/:pro\.unmapped:/', $codes ) ) );
		h_check( $converted === \Digitalisimo\Elements\Convert\Converter::tree( $converted, $again, true ) && array() === $again, "$source: lo convertido no se convierte otra vez." );
		return array( $out, $codes );
	};
	list( $out, $codes ) = $ck_convert(
		'woocommerce-checkout-page',
		$own,
		array(
			'd1' => array(),
			'd2' => array(
				'checkout_layout'             => 'one-column',
				'sticky_right_column'         => 'yes',
				'billing_details_section_title' => 'Datos',
				'billing_details_form_fields' => array( array( '_id' => 'r1', 'field_key' => 'billing_first_name', 'field_label' => 'First Name', 'label' => 'Nombre', 'placeholder' => 'Ana', 'repeater_state' => '' ) ),
				'sections_background_color'   => '#fafafa',
				'forms_field_typography_typography' => 'custom',
				'forms_field_typography_font_size_tablet' => array( 'size' => 13, 'unit' => 'px' ),
				'purchase_button_normal_background_background' => 'classic',
				'__globals__'                 => array( 'sections_title_color' => 'globals/colors?id=primary' ),
				'__dynamic__'                 => array( 'order_summary_section_title' => '[elementor-tag id="1" name="post-title" settings="%7B%7D"]' ),
				'custom_css'                  => 'selector .e-checkout__order_review{padding:0} selector .e-coupon-box .e-apply-coupon{color:red}',
			),
		)
	);
	$ok( 'Billing Details' === $out['d1']['billing_details_section_title'] && 'Your Order' === $out['d1']['order_summary_section_title'] && 'Have a coupon?' === $out['d1']['coupon_section_title_text'] && 10 === count( $out['d1']['billing_details_form_fields'] ) && 'First Name' === $out['d1']['billing_details_form_fields'][0]['label'], 'Por defecto: se completan los textos y las filas de PRO.' );
	$ok( 'one-column' === $out['d2']['checkout_layout'] && 'Datos' === $out['d2']['billing_details_section_title'] && 'r1' === $out['d2']['billing_details_form_fields'][0]['_id'] && 'Nombre' === $out['d2']['billing_details_form_fields'][0]['label'] && '#fafafa' === $out['d2']['sections_background_color'] && array( 'size' => 13, 'unit' => 'px' ) === $out['d2']['forms_field_typography_font_size_tablet'], 'Ajustes, filas, grupos y dispositivos pasan tal cual.' );
	$ok( 'selector .digi-co__review{padding:0} selector .digi-co__coupon .digi-co__apply{color:red}' === $out['d2']['custom_css'] && in_array( 'd2:pro.css:custom_css', $codes, true ), 'CSS personalizado con las clases propias.' );
	$ok( isset( $out['d2']['__globals__']['sections_title_color'], $out['d2']['__dynamic__']['order_summary_section_title'] ), 'Globales y dinámicos siguen a su control.' );
	list( $b ) = $render( $own, array( 'checkout_layout' => 'two-column' ) + $out['d1'] );
	h_classes_exist( array( 'classes' => array_diff_key( $map['classes'], array_flip( array( '.e-login-actions-wrap-start', '.e-login-actions-wrap-end', '.e-login-actions-wrap', '.e-login-wrap-start', '.e-login-wrap-end', '.e-login-wrap', '.e-login-label', '.e-woocommerce-login-section', '.e-woocommerce-login-anchor', '.e-woocommerce-login-nudge', '.e-woocommerce-form-login-submit', '.elementor-woocommerce-login-messages', '.e-show-login', '.e-description', '.e-checkout-message', '.e-woo-select2-wrapper', '.e-sticky-right-column--active' ) ) ) ), $own, $b );
	$checks++;

	// 6. Hojas (al final: el resto de la prueba no depende de ellas).
	$css_all = '';
	foreach ( array( 'checkout.css', 'checkout-2.css' ) as $sheet ) {
		$css = (string) file_get_contents( h_path( 'modules/digitalisimo-woo/store/assets/' . $sheet ) );
		$css_all .= $css;
		$ok( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$sheet: " . strlen( $css ) . ' B (tope 4 096).' );
		$ok( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/\.(e-|elementor-)/', $css ), "$sheet: sin el kit, url() ni clases del proveedor." );
	}
	$ok( str_contains( $css_all, ':focus-visible{outline:2px solid currentColor;outline-offset:2px}' ) && str_contains( $css_all, 'prefers-reduced-motion' ), 'Foco visible de 2 px con separación de 2 px y movimiento reducido.' );
	echo "DIGITALÍSIMO Elements: Finalizar compra (digitalisimo-woo-checkout) frente al Checkout de PRO en verde ($checks comprobaciones).\n";
}
