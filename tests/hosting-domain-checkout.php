<?php
// El dominio antes de pagar (pedido del propietario del 2026-11-26 y B1/B2 de la auditoría SEO), sin WordPress:
//   1. dominio nuevo: el producto de dominio lleva el nombre y se comprueba con namecheap.domains.check ANTES de pagar
//      (disponible, no premium, de la extensión del producto); sin nombre no se paga;
//   2. hosting o correo: «Dominio nuevo» o «Uno que ya tengo» antes de pagar; con «ya tengo» se pide el nombre exacto,
//      queda en la línea del plan y no se exige producto de dominio, ni con «Exigir dominio y hosting juntos»;
//   3. transferencia: dominio y EPP antes de pagar; formato, registrado, extensión transferible y no nuestro; el EPP se
//      cifra al entrar al carrito, pasa cifrado al pedido y nunca sale en notas, correos, la línea, la REST ni la URL;
//   4. la Store API (checkout de bloques) recibe lo mismo en el cuerpo de cart/add-item;
//   5. el panel «Tu dominio» del carrito y del checkout guarda la elección con su nonce;
//   6. el aprovisionamiento lleva la transferencia por la misma revisión de dueño y la deja para hacerse a mano;
//   7. el buscador: un <div> (no un <form>, sirve dentro de un formulario atómico) con las extensiones configuradas y
//      la consulta por POST con la clave en el cuerpo.
// Uso: php tests/hosting-domain-checkout.php
require __DIR__ . '/fixtures/hosting-wp.php';
$C = 'Digitalisimo_Hosting_Checkout'; $H = 'Digitalisimo_Hosting'; $P = 'Digitalisimo_Hosting_Provisioning';

$GLOBALS['notices'] = array();
function wc_add_notice( $m, $t = 'success' ) { $GLOBALS['notices'][] = array( $t, $m ); }
function wp_verify_nonce( $n, $a ) { return 'n:' . $a === $n ? 1 : false; }
function wp_get_referer() { return 'https://tienda.test/carrito/'; }
function wc_get_cart_url() { return 'https://tienda.test/carrito/'; }
function home_url( $p = '' ) { return 'https://tienda.test' . $p; }
function rest_url( $p = '' ) { return 'https://tienda.test/wp-json/' . $p; }
function wp_enqueue_style() {} function wp_enqueue_script() {}
function wp_localize_script( $h, $n, $d ) { $GLOBALS['localized'][ $n ] = $d; }
function get_woocommerce_currency_symbol() { return '&#36;'; }
function wc_get_price_to_display( $p ) { return (float) $p->get_price(); }
class Digi_Test_Cart {
	public $cart_contents = array(); public $sessions = 0;
	public function get_cart() { return $this->cart_contents; }
	public function set_session() { $this->sessions++; }
	public function add( $pid, array $data = array() ) { $key = md5( $pid . serialize( $data ) . count( $this->cart_contents ) ); $this->cart_contents[ $key ] = array( 'product_id' => $pid, 'quantity' => 1 ) + $data; return $key; }
}
WC()->cart = new Digi_Test_Cart();
/** Petición REST: $p en el cuerpo JSON; $q en la URL (get_param lee los dos, como WordPress). */
class Digi_Test_Store_Request { public $p; public $q; public function __construct( $p, $q = array() ) { $this->p = $p; $this->q = $q; } public function get_param( $k ) { return $this->p[ $k ] ?? $this->q[ $k ] ?? null; } public function get_json_params() { return $this->p; } public function get_body_params() { return array(); } }
function cart_reset() { WC()->cart = new Digi_Test_Cart(); $GLOBALS['notices'] = array(); $_POST = $_REQUEST = $_GET = array(); $_SERVER['REQUEST_METHOD'] = 'GET'; }
function nc_checks() { return digi_nc_count( 'namecheap.domains.check' ); }
/** Añadir al carrito como WooCommerce: validación y datos de la línea con la petición actual. */
function add_to_cart( $pid, array $request = array(), $post = false ) {
	$_REQUEST = $request; $_POST = $post ? $request : array(); $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
	$GLOBALS['notices'] = array();
	if ( ! Digitalisimo_Hosting_Checkout::maybe_validate_add( true, $pid, 1 ) ) return false;
	$data = Digitalisimo_Hosting::cart_domain( array(), $pid, 0 );
	return WC()->cart->add( $pid, $data );
}
function errors() { return Digitalisimo_Hosting_Checkout::cart_errors(); }

digi_servers_reset();
digi_store_settings( array( 'domain_products' => ".com|20\n.mx|21" ) );
digi_product( 50, 'Transferencia de dominio', 250 );
update_post_meta( 50, '_digitalisimo_domain_kind', 'transfer' ); update_post_meta( 50, '_digitalisimo_tld', '*' );
Digitalisimo_Hosting_Products::flush();
$GLOBALS['nc']['domains']['nuestro.com'] = strtotime( '+200 days' );
$GLOBALS['nc']['taken'] = array( 'ocupado.com', 'miempresa.com', 'ajeno.com', 'ajeno.mx' );
$GLOBALS['nc']['premium'] = array( 'lujo.com' );

check( 'register' === $C::kind( 20 ) && 'register' === $C::kind( 21 ) && 'transfer' === $C::kind( 50 ) && 'hosting' === $C::kind( 14693 ) && '' === $C::kind( 999 ), 'Cada producto se reconoce: registro, transferencia y plan' );

// ── 1. Dominio nuevo (B1) ─────────────────────────────────────────────────────────────────────────────────────
cart_reset();
$before = nc_checks();
$k = add_to_cart( 20, array( 'domain_name' => 'Https://WWW.NuevoNegocio.com/' ) );
check( $k && 'nuevonegocio.com' === WC()->cart->cart_contents[ $k ]['digitalisimo_domain'] && nc_checks() === $before + 1, 'El buscador agrega el dominio limpio y se comprueba en Namecheap al añadirlo' );
$call = end( $GLOBALS['nc']['calls'] );
check( 'namecheap.domains.check' === $call['cmd'] && 'nuevonegocio.com' === $call['fields']['DomainList'] && 'CLAVE-NAMECHEAP' === $call['key'], 'La consulta va por POST con la clave en el cuerpo' );
check( ! errors(), 'Un dominio nuevo disponible se puede pagar' );
check( false === add_to_cart( 20, array( 'domain_name' => 'ocupado.com' ) ) && false !== strpos( $GLOBALS['notices'][0][1], 'ya está registrado' ), 'Un dominio ocupado no entra al carrito' );
check( false === add_to_cart( 20, array( 'domain_name' => 'lujo.com' ) ) && false !== strpos( $GLOBALS['notices'][0][1], 'premium' ), 'Un dominio premium no entra al carrito' );
check( false === add_to_cart( 20, array( 'domain_name' => 'otro.mx' ) ) && false !== strpos( $GLOBALS['notices'][0][1], 'no es .com' ), 'Un .mx no se paga con el producto .com' );
check( false === add_to_cart( 20, array( 'domain_name' => 'mal nombre.com' ) ), 'Un nombre con espacios no entra' );
cart_reset();
add_to_cart( 20 );
check( preg_grep( '/Escribe el dominio que quieres registrar/', errors() ), 'Sin nombre (p. ej. un botón ?add-to-cart= suelto) no se paga: lo pide el carrito, ya no «Notas del pedido»' );
cart_reset();
$k = WC()->cart->add( 20, array( 'digitalisimo_domain' => 'nuevonegocio.com' ) );
$GLOBALS['nc']['taken'][] = 'nuevonegocio.com';
check( $C::cart_errors() === array(), 'La comprobación del carrito se guarda unos minutos (no se repite en cada vista)' );
$GLOBALS['nc']['taken'] = array( 'ocupado.com', 'miempresa.com', 'ajeno.com', 'ajeno.mx' );

// ── 2. Hosting: dominio nuevo o uno que ya tengo ──────────────────────────────────────────────────────────────
cart_reset();
add_to_cart( 14693 );
check( preg_grep( '/Elige el dominio de tu plan/', errors() ), 'Un plan sin dominio no se paga: pide «Dominio nuevo» o «Uno que ya tengo»' );
add_to_cart( 20, array( 'domain_name' => 'nuevonegocio2.com' ) );
check( ! errors(), 'Plan + dominio nuevo: se paga' );
cart_reset();
$k = add_to_cart( 14693, array( 'digitalisimo_domain_mode' => 'own', 'digitalisimo_domain' => 'miempresa.com' ), true );
check( $k && 'miempresa.com' === WC()->cart->cart_contents[ $k ]['digitalisimo_domain'] && 'own' === WC()->cart->cart_contents[ $k ]['digitalisimo_domain_mode'] && ! errors(), '«Uno que ya tengo» con su nombre exacto: se paga sin producto de dominio' );
update_option( 'digitalisimo_hosting_options', array( 'require_domain_hosting' => 1 ) + get_option( 'digitalisimo_hosting_options' ) );
check( ! errors(), 'Con «Exigir dominio y hosting juntos», quien ya tiene dominio tampoco queda bloqueado' );
$ok_items = Digitalisimo_Hosting::cart_item_data( array(), WC()->cart->cart_contents[ $k ] );
check( 'Dominio (ya lo tengo)' === $ok_items[0]['name'], 'El carrito lo muestra como «Dominio (ya lo tengo)»' );
cart_reset();
$k = add_to_cart( 14693, array( 'digitalisimo_domain_mode' => 'new', 'digitalisimo_domain' => 'libre-del-todo.com' ), true );
check( $k && ! isset( WC()->cart->cart_contents[ $k ]['digitalisimo_domain'] ) && preg_grep( '/Elige el dominio de tu plan/', errors() ), '«Dominio nuevo» con texto en el campo de «ya tengo»: se ignora (la línea no lleva dominio y sigue pidiendo el dominio nuevo)' );
cart_reset();
$k = add_to_cart( 14693, array( 'digitalisimo_domain_mode' => 'new', 'digitalisimo_domain' => 'miempresa.com' ), true );
check( $k && ! isset( WC()->cart->cart_contents[ $k ]['digitalisimo_domain'] ) && preg_grep( '/Elige el dominio de tu plan/', errors() ), '«Dominio nuevo» con un dominio registrado en el campo oculto: no se cuela como dominio propio' );
$add = $C::store_api_data( array( 'id' => 14693, 'quantity' => 1, 'cart_item_data' => array() ), new Digi_Test_Store_Request( array( 'digitalisimo_domain_mode' => 'new', 'digitalisimo_domain' => 'miempresa.com' ) ) );
check( ! isset( Digitalisimo_Hosting::cart_domain( $add['cart_item_data'], 14693, 0 )['digitalisimo_domain'] ), 'Store API: con «new» el dominio del plan también se ignora' );
cart_reset();
check( false === add_to_cart( 14693, array( 'digitalisimo_domain_mode' => 'own' ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'nombre exacto' ), '«Uno que ya tengo» sin nombre no entra' );
check( false === add_to_cart( 14693, array( 'digitalisimo_domain_mode' => 'own', 'digitalisimo_domain' => 'gmail.com' ), true ), 'Un correo público no se hospeda como dominio propio' );
check( false === add_to_cart( 14693, array( 'digitalisimo_domain_mode' => 'own', 'digitalisimo_domain' => 'noexiste-aun.com' ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'no está registrado' ), '«Ya tengo» un dominio que Namecheap da por libre: error de escritura, no se paga' );
check( false === add_to_cart( 14693, array( 'digitalisimo_domain_mode' => 'own', 'digitalisimo_domain' => 'www.cliente.com.mx.otro' ), true ), 'Un subdominio no es un dominio propio' );
cart_reset();
add_to_cart( 20, array( 'domain_name' => 'solo-dominio.com' ) );
check( preg_grep( '/Agrega un plan de hosting/', errors() ), 'Con «Exigir dominio y hosting juntos», un dominio solo sigue sin pagarse' );
update_option( 'digitalisimo_hosting_options', array( 'require_domain_hosting' => 0 ) + get_option( 'digitalisimo_hosting_options' ) );
check( ! errors(), 'Sin esa casilla, un dominio solo se paga' );
update_option( 'digitalisimo_hosting_options', array( 'ask_domain' => 0 ) + get_option( 'digitalisimo_hosting_options' ) );
cart_reset(); add_to_cart( 14693 );
check( ! errors(), 'Con «pedir el dominio» apagado, un plan solo se paga como antes' );
$GLOBALS['product'] = $GLOBALS['products'][14693];
ob_start(); $C::maybe_product_fields(); $fields = ob_get_clean();
check( '' === $C::panel_html() && '' === $fields, 'Con «pedir el dominio» apagado no se pintan el panel ni los campos del plan' );
update_option( 'digitalisimo_hosting_options', array( 'ask_domain' => 1 ) + get_option( 'digitalisimo_hosting_options' ) );
ob_start(); $C::maybe_product_fields(); $fields = ob_get_clean();
check( false !== strpos( $fields, 'Uno que ya tengo' ), 'Encendido, el producto del plan pide el dominio' );
update_option( 'digitalisimo_hosting_options', array( 'ask_domain' => 0 ) + get_option( 'digitalisimo_hosting_options' ) );
update_option( 'digitalisimo_hosting_options', array( 'ask_domain' => 1 ) + get_option( 'digitalisimo_hosting_options' ) );

// ── 3. Transferencia + EPP ────────────────────────────────────────────────────────────────────────────────────
$EPP = 'Ab3$k9#Zq!7-EPP';
cart_reset();
$k = add_to_cart( 50, array( 'digitalisimo_domain' => 'ajeno.com', 'digitalisimo_epp' => $EPP ), true );
$line = WC()->cart->cart_contents[ $k ];
check( $k && 'ajeno.com' === $line['digitalisimo_domain'] && ! errors(), 'Transferencia con dominio registrado y EPP: se paga' );
check( isset( $line['digitalisimo_epp'] ) && false === strpos( serialize( $line ), $EPP ) && $EPP === $P::decrypt( $line['digitalisimo_epp'] ), 'El EPP entra cifrado al carrito (la sesión nunca lo guarda en claro)' );
check( ! isset( $line['digitalisimo_epp_raw'] ), 'No queda copia en claro en la línea' );
check( false === strpos( wp_json_encode( Digitalisimo_Hosting::cart_item_data( array(), $line ) ), $EPP ) && false === strpos( serialize( $GLOBALS['nc']['calls'] ), $EPP ), 'El EPP no se muestra en el carrito ni viaja a Namecheap al validar' );
cart_reset();
$k = add_to_cart( 50, array( 'digitalisimo_domain' => 'ajeno.com', 'digitalisimo_epp' => $EPP ), false );
check( $k && empty( WC()->cart->cart_contents[ $k ]['digitalisimo_epp'] ) && preg_grep( '/código de transferencia \(EPP\)/', errors() ), 'Un EPP en la URL (GET) se ignora: el carrito lo pide de nuevo' );
cart_reset();
check( false === add_to_cart( 50, array( 'digitalisimo_domain' => 'ajeno.com', 'digitalisimo_epp' => 'a b' ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'no es válido' ), 'Un EPP con espacios o muy corto no entra' );
check( false === add_to_cart( 50, array( 'digitalisimo_domain' => 'libre-del-todo.com', 'digitalisimo_epp' => $EPP ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'no está registrado' ), 'Un dominio libre no se transfiere (se registra)' );
check( false === add_to_cart( 50, array( 'digitalisimo_domain' => 'nuestro.com', 'digitalisimo_epp' => $EPP ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'no se puede transferir aquí' ) && false === strpos( $GLOBALS['notices'][0][1], 'nosotros' ), 'Un dominio que ya está en nuestra cuenta no se transfiere, con un texto que no lo revela' );
$GLOBALS['nc']['no_transfer'] = array( 'mx' );
check( false === add_to_cart( 50, array( 'digitalisimo_domain' => 'ajeno.mx', 'digitalisimo_epp' => $EPP ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'no se pueden transferir' ), 'Una extensión que Namecheap no transfiere por la API no entra' );
check( digi_nc_count( 'namecheap.domains.getTldList' ) >= 1, 'La extensión transferible se consulta con namecheap.domains.getTldList' );
cart_reset();
add_to_cart( 50 );
check( preg_grep( '/dominio que quieres transferir/', errors() ), 'Sin dominio no se paga la transferencia' );
// «*» sólo vale para las extensiones configuradas (con producto de registro, con el que se renueva).
$GLOBALS['nc']['taken'][] = 'ajeno.lat';
cart_reset();
check( false === add_to_cart( 50, array( 'digitalisimo_domain' => 'ajeno.lat', 'digitalisimo_epp' => $EPP ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'no se transfieren aquí' ), 'Transferencia «*» de una extensión sin producto de registro (.lat): no se vende' );
// Freno de pérdida con el costo de transferencia de Namecheap.
$GLOBALS['nc']['taken'][] = 'caro.com';
$GLOBALS['nc']['price']['com'] = array( 'REGISTER' => 9.99, 'RENEW' => 9.99, 'TRANSFER' => 50, 'extra' => 0 );
cart_reset();
check( false === add_to_cart( 50, array( 'digitalisimo_domain' => 'caro.com', 'digitalisimo_epp' => $EPP ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'a este precio' ) && false === strpos( $GLOBALS['notices'][0][1], 'USD' ), 'Transferencia cuyo costo en Namecheap no deja el margen mínimo: no se vende (sin mostrar costos al visitante)' );
check( preg_grep( '/TRANSFER/', array_map( function ( $c ) { return $c['fields']['ActionName'] ?? ''; }, $GLOBALS['nc']['calls'] ) ), 'Se consulta el costo de TRANSFER en Namecheap' );
$GLOBALS['nc']['taken'][] = 'caro2.com';
$GLOBALS['nc']['price']['com'] = array( 'REGISTER' => 60, 'RENEW' => 9.99, 'extra' => 0 );
cart_reset();
check( false === add_to_cart( 50, array( 'digitalisimo_domain' => 'caro2.com', 'digitalisimo_epp' => $EPP ), true ) && false !== strpos( $GLOBALS['notices'][0][1], 'a este precio' ), 'Sin costo de transferencia, se usa el de registro' );
unset( $GLOBALS['nc']['price']['com'] );

// ── 4. Store API ──────────────────────────────────────────────────────────────────────────────────────────────
cart_reset();
$add = $C::store_api_data( array( 'id' => 50, 'quantity' => 1, 'cart_item_data' => array() ), new Digi_Test_Store_Request( array( 'digitalisimo_domain' => 'ajeno.com', 'digitalisimo_epp' => $EPP ) ) );
check( $C::maybe_validate_add( true, 50, 1, 0, array(), $add['cart_item_data'] ), 'Store API: el cuerpo de cart/add-item lleva dominio y EPP y se valida' );
$data = Digitalisimo_Hosting::cart_domain( $add['cart_item_data'], 50, 0 );
check( 'ajeno.com' === $data['digitalisimo_domain'] && $EPP === $P::decrypt( $data['digitalisimo_epp'] ) && ! isset( $data['digitalisimo_epp_raw'] ), 'Store API: el EPP queda cifrado en la línea' );
$q = $C::store_api_data( array( 'id' => 50, 'quantity' => 1, 'cart_item_data' => array() ), new Digi_Test_Store_Request( array( 'digitalisimo_domain' => 'ajeno.com' ), array( 'digitalisimo_epp' => $EPP ) ) );
check( ! isset( $q['cart_item_data']['digitalisimo_epp_raw'] ) && ! isset( $q['cart_item_data']['digitalisimo_epp'] ), 'Store API: un EPP en la URL (?digitalisimo_epp=) se ignora' );
$add = $C::store_api_data( array( 'id' => 14693, 'quantity' => 1, 'cart_item_data' => array() ), new Digi_Test_Store_Request( array( 'digitalisimo_domain_mode' => 'own', 'digitalisimo_domain' => 'miempresa.com' ) ) );
check( 'own' === $add['cart_item_data']['digitalisimo_domain_mode'] && $C::maybe_validate_add( true, 14693, 1, 0, array(), $add['cart_item_data'] ), 'Store API: «Uno que ya tengo» en el cuerpo' );

// ── 3b. El pedido ─────────────────────────────────────────────────────────────────────────────────────────────
$order = wc_create_order( array( 'customer_id' => 0 ) );
$item = new Digi_Test_Item( 50, 'Transferencia de dominio' );
Digitalisimo_Hosting::order_item_domain( $item, 'k', $data, $order );
$order->items[] = $item;
check( 'ajeno.com' === $item->get_meta( 'Dominio' ) && false === strpos( serialize( $item->meta ), $EPP ) && ! preg_grep( '/epp/i', array_keys( $item->meta ) ), 'La línea del pedido lleva el dominio y nunca el EPP' );
$stored = $order->get_meta( '_digitalisimo_epp' );
check( is_array( $stored ) && false === strpos( serialize( $order->meta ), $EPP ) && $EPP === $C::epp( $order, 'ajeno.com' ), 'El pedido guarda el EPP cifrado (meta del pedido) y sólo «Ver código EPP» lo descifra' );
$resp = (object) array( 'data' => array( 'id' => $order->id, 'meta_data' => array( (object) array( 'key' => '_digitalisimo_epp', 'value' => $stored ), (object) array( 'key' => '_otra', 'value' => 1 ) ) ) );
$resp = $C::rest_strip( $resp );
check( array( '_otra' ) === array_map( function ( $m ) { return $m->key; }, $resp->data['meta_data'] ), 'La REST de pedidos (y el MCP de la tienda) no devuelve el EPP, ni cifrado' );
$v4 = $C::rest_strip( array( 'id' => 1, 'meta_data' => array( array( 'key' => '_digitalisimo_epp', 'value' => $stored ), array( 'key' => 'x', 'value' => 1 ) ) ) );
check( array( 'x' ) === array_column( $v4['meta_data'], 'key' ), 'Tampoco la REST v4 de pedidos (respuesta en arreglo)' );
$saved_hooks = $GLOBALS['hooks']; $GLOBALS['hooks'] = array();
$C::init();
check( isset( $GLOBALS['hooks']['woocommerce_add_to_cart_validation'][5] ) && isset( $GLOBALS['hooks']['woocommerce_cart_has_errors'][10] ) && isset( $GLOBALS['hooks']['woocommerce_rest_api_v4_orders_item_response'][99] ), 'Valida antes de «Reemplazar el plan»; el panel sale también en el checkout clásico con errores; filtro de la REST v4' );
$GLOBALS['hooks'] = $saved_hooks;

// ── 5. Panel «Tu dominio» ─────────────────────────────────────────────────────────────────────────────────────
cart_reset();
$kh = WC()->cart->add( 14693 );
$html = $C::panel_html();
check( false !== strpos( $html, 'Dominio nuevo' ) && false !== strpos( $html, 'Uno que ya tengo' ) && false !== strpos( $html, 'name="dhd[' . $kh . '][domain]"' ), 'Con un plan sin dominio, el panel pide «Dominio nuevo» o «Uno que ya tengo»' );
check( false === strpos( $html, '<form class="digitalisimo-domain-search' ) && false !== strpos( $html, '<div class="digitalisimo-domain-search"' ), 'El buscador del panel es un <div> (no anida formularios)' );
$kt = WC()->cart->add( 50 );
$html = $C::panel_html();
check( false === strpos( $html, 'Uno que ya tengo' ) && false !== strpos( $html, 'name="dhd[' . $kt . '][epp]"' ) && false !== strpos( $html, 'type="password"' ), 'Con una transferencia, el plan usa ese dominio y el panel pide el dominio y el EPP' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'digitalisimo_hosting_domain_choice' => 1, '_dhd_nonce' => 'otro', 'dhd' => array( $kh => array( 'mode' => 'own', 'domain' => 'miempresa.com' ) ) );
$C::handle_panel();
check( empty( WC()->cart->cart_contents[ $kh ]['digitalisimo_domain'] ), 'Sin el nonce, el panel no cambia nada' );
$_POST = array( 'digitalisimo_hosting_domain_choice' => 1, '_dhd_nonce' => 'n:' . $C::NONCE, 'dhd' => array( $kh => array( 'mode' => 'own', 'domain' => 'miempresa.com' ), $kt => array( 'domain' => 'ajeno.com', 'epp' => $EPP ) ) );
try { $C::handle_panel(); } catch ( Digi_Test_Redirect $e ) {}
$cc = WC()->cart->cart_contents;
check( 'miempresa.com' === $cc[ $kh ]['digitalisimo_domain'] && 'ajeno.com' === $cc[ $kt ]['digitalisimo_domain'] && $EPP === $P::decrypt( $cc[ $kt ]['digitalisimo_epp'] ) && WC()->cart->sessions > 0, 'El panel guarda «ya tengo» y la transferencia (EPP cifrado) y vuelve a la página' );
check( ! errors() && '' === $C::panel_html(), 'Con todo decidido no hay errores ni panel' );

// ── 6. Aprovisionamiento de una transferencia ─────────────────────────────────────────────────────────────────
$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array();
update_option( 'digitalisimo_hosting_options', array( 'own_domain_auto' => 0 ) + get_option( 'digitalisimo_hosting_options' ) );
$o = digi_order( array( new Digi_Test_Item( 50, 'Transferencia de dominio', 1, array( 'Dominio' => 'ajeno.com' ) ) ) );
$o->update_meta_data( '_digitalisimo_epp', array( 'ajeno.com' => $P::encrypt( $EPP ) ) );
$o->update_status( 'processing' );
digi_run_queue();
check( 'on-hold' === $o->status && preg_grep( '/Transferencia de ajeno\.com: por omisión se revisa a mano antes de tramitarla/', $o->notes ) && ! preg_grep( '/Hosting con dominio propio/', $o->notes ) && ! digi_nc_count( 'namecheap.domains.create' ), 'Transferencia: la misma revisión de dueño que un dominio propio, con su propio texto' );
update_option( 'digitalisimo_hosting_options', array( 'own_domain_auto' => 1 ) + get_option( 'digitalisimo_hosting_options' ) );
$o2 = digi_order( array( new Digi_Test_Item( 50, 'Transferencia de dominio', 1, array( 'Dominio' => 'ajeno.com' ) ) ) );
$o2->update_meta_data( '_digitalisimo_epp', array( 'ajeno.com' => $P::encrypt( $EPP ) ) );
$o2->update_status( 'processing' );
digi_run_queue();
$sid = (int) $o2->get_meta( '_digitalisimo_service' );
check( preg_grep( '/transferencia de ajeno\.com por hacer a mano/', $o2->notes ) && ! digi_nc_count( 'namecheap.domains.create' ) && ! digi_nc_count( 'namecheap.domains.dns.setCustom' ), 'Aprobada, la transferencia queda anotada para hacerse a mano: no se registra ni se tocan sus DNS' );
check( 1 === (int) $P::svc( $sid, 'transfer_pending' ) && array_filter( $GLOBALS['mails'], function ( $m ) { return false !== strpos( $m['subject'], 'Transferencia de dominio por hacer' ); } ), 'El servicio la marca pendiente y el administrador recibe el aviso' );
check( false === strpos( serialize( array( $o->notes, $o2->notes, $GLOBALS['mails'], $GLOBALS['nc']['calls'], $GLOBALS['webuzo']['calls'] ) ), $EPP ), 'El EPP nunca aparece en notas, correos ni llamadas' );
$client = array_values( array_filter( $GLOBALS['mails'], function ( $m ) { return 'ana@cliente.test' === $m['to']; } ) );
check( $client && false !== strpos( $client[0]['body'], 'transferencia de dominio' ) && false === strpos( $client[0]['body'], 'Registramos tu dominio' ), 'El correo al cliente dice que la transferencia está en trámite, no que se registró' );
check( 20 === (int) $P::svc( $sid, 'domain_product' ), 'El servicio se renueva con el producto de registro de su extensión' );
// B2: una renovación con la transferencia sin terminar no cobra el dominio y avisa.
$GLOBALS['mails'] = array();
$P::svc_set( $sid, array( 'hosting_product' => 14693 ) );
$ren = $P::renewal_order( $sid );
check( $ren && array( 14693 ) === array_map( function ( $i ) { return $i->get_product_id(); }, $ren->items ) && array_filter( $GLOBALS['mails'], function ( $m ) { return false !== strpos( $m['subject'], 'transferencia sin terminar' ); } ), 'Renovación con la transferencia sin terminar: no cobra el dominio y avisa al administrador' );
$P::svc_set( $sid, array( 'hosting_product' => 0, 'renewal_order' => 0 ) );
// La transferencia llega a la cuenta: la revisión diaria la da por hecha y borra el EPP del pedido.
$GLOBALS['nc']['domains']['ajeno.com'] = strtotime( '+300 days' );
$P::check_service( $sid, time(), 7 );
check( 0 === (int) $P::svc( $sid, 'transfer_pending' ) && 1 === (int) $P::svc( $sid, 'domain_registered' ) && '' === $o2->get_meta( '_digitalisimo_epp' ) && preg_grep( '/transferencia de ajeno\.com terminada/', $o2->notes ), 'Al llegar a la cuenta: transferencia terminada, se renueva con el dominio y el EPP se borra del pedido' );
$o3 = digi_order( array( new Digi_Test_Item( 50, 'Transferencia', 1, array( 'Dominio' => 'ajeno.com' ) ), new Digi_Test_Item( 20, 'Dominio', 1, array( 'Dominio' => 'otro-nuevo.com' ) ) ) );
$o3->update_status( 'processing' ); digi_run_queue();
check( 'on-hold' === $o3->status && preg_grep( '/más de un dominio/', $o3->notes ), 'Una transferencia y un registro en el mismo pedido se entregan a mano' );
$GLOBALS['nc']['price']['com'] = array( 'REGISTER' => 9.99, 'RENEW' => 9.99, 'TRANSFER' => 50, 'extra' => 0 );
$o4 = digi_order( array( new Digi_Test_Item( 50, 'Transferencia de dominio', 1, array( 'Dominio' => 'ajeno.com' ) ) ) );
$o4->update_status( 'processing' ); digi_run_queue();
check( 'on-hold' === $o4->status && preg_grep( '/costo real de transferir ajeno\.com/', $o4->notes ) && ! preg_grep( '/por hacer a mano/', $o4->notes ), 'Aprovisionamiento: una transferencia cobrada por debajo de su costo queda en espera (freno de pérdida)' );
unset( $GLOBALS['nc']['price']['com'] );
$o5 = digi_order( array( new Digi_Test_Item( 50, 'Transferencia de dominio', 1, array( 'Dominio' => 'ajeno.lat' ) ) ) );
$o5->update_status( 'processing' ); digi_run_queue();
check( 'on-hold' === $o5->status && preg_grep( '/No hay producto de registro de \.lat/', $o5->notes ), 'Aprovisionamiento: sin producto de registro para renovar, la transferencia no se tramita' );

// ── B7: el EPP se borra al cancelar o reembolsar, y al reintentar con otro dominio ──────────────────────────────
$o6 = digi_order( array( new Digi_Test_Item( 50, 'Transferencia', 1, array( 'Dominio' => 'ajeno.com' ) ) ) );
$o6->update_meta_data( '_digitalisimo_epp', array( 'ajeno.com' => $P::encrypt( $EPP ) ) );
$C::purge_epp( $o6->id );
check( '' === $o6->get_meta( '_digitalisimo_epp' ) && preg_grep( '/código EPP se borró/', $o6->notes ), 'Cancelado o reembolsado: el EPP se borra del pedido' );
$hooks_before = $GLOBALS['hooks']; $GLOBALS['hooks'] = array(); $C::init();
check( isset( $GLOBALS['hooks']['woocommerce_order_status_cancelled'][5], $GLOBALS['hooks']['woocommerce_order_status_refunded'][5], $GLOBALS['hooks']['woocommerce_order_status_failed'][5] ), 'Se borra al pasar a cancelado, reembolsado o fallido' );
$GLOBALS['hooks'] = $hooks_before;
$o7 = digi_order( array( new Digi_Test_Item( 50, 'Transferencia', 1, array( 'Dominio' => 'ajeno.com' ) ) ) );
$o7->update_meta_data( '_digitalisimo_epp', array( 'viejo-error.com' => $P::encrypt( 'OTRO-EPP-123' ), 'ajeno.com' => $P::encrypt( $EPP ) ) );
$o7->update_status( 'processing' ); digi_run_queue();
check( array( 'ajeno.com' ) === array_keys( (array) $o7->get_meta( '_digitalisimo_epp' ) ), 'Al reintentar con otro dominio, el EPP del dominio anterior se borra' );

// ── 7. El buscador ────────────────────────────────────────────────────────────────────────────────────────────
$w = Digitalisimo_Hosting::legacy_shortcode();
check( false === strpos( $w, '<form' ) && false !== strpos( $w, 'type="button"' ), '[digitalisimo_domain_search] es un <div> con un botón: funciona dentro de un formulario atómico' );
preg_match_all( '/<option value="([^"]+)"/', $w, $m );
check( array( '.com', '.mx' ) === $m[1], 'El shortcode ofrece sólo las extensiones configuradas' );
check( array( '.com' => 20, '.mx' => 21 ) === (array) $GLOBALS['localized']['digitalisimoHosting']['products'], 'El formulario de Elements recibe las extensiones configuradas' );
update_option( 'digitalisimo_hosting_options', array( 'domain_products' => ".com|\n.net|\n.mx|" ) + get_option( 'digitalisimo_hosting_options' ) );
foreach ( array( 20 => 'dominio-com', 21 => 'dominio-mx' ) as $id => $sku ) $GLOBALS['products'][ $id ]->sku = $sku;
digi_product( 22, 'Dominio .net', 280 ); $GLOBALS['products'][22]->sku = 'dominio-net';
Digitalisimo_Hosting_Products::flush();
preg_match_all( '/<option value="([^"]+)"/', Digitalisimo_Hosting::search_widget(), $m );
check( array( '.com', '.net', '.mx' ) === $m[1], 'Con la lista de fábrica sin IDs, el buscador ofrece .com, .net y .mx (resueltos por SKU), nunca .org' );
$r = Digitalisimo_Hosting::check_domain( new Digi_Test_Store_Request( array( 'domain' => 'libre.net' ) ) );
check( is_array( $r ) && true === $r['available'] && 22 === $r['product_id'] && '280' === $r['price'] && '$' === $r['currency'], 'La consulta pública devuelve disponibilidad, producto y precio en una respuesta' );
$last = end( $GLOBALS['nc']['calls'] );
check( 'namecheap.domains.check' === $last['cmd'] && ! isset( $last['fields']['ApiKey'] ) && 'CLAVE-NAMECHEAP' === $last['key'], 'La consulta pública va por el cliente de Namecheap (POST, clave en el cuerpo)' );
$js = file_get_contents( __DIR__ . '/../digitalisimo-hosting/assets/domain-form.js' );
check( false === strpos( $js, '.org' ) && false === strpos( $js, 'innerHTML' ) && false !== strpos( $js, "addEventListener('submit'" ) && false !== strpos( $js, ', true);' ), 'El script no trae extensiones fijas, no usa innerHTML e intercepta el envío en la fase de captura' );

echo "Hosting, el dominio antes de pagar: {$GLOBALS['checks']} comprobaciones correctas.\n";
