<?php
// Buscador de dominios estilo registrador (pedido del propietario del 2026-11-26; método «tienda de hosting y dominios»),
// sin WordPress ni salir a la red (Namecheap, Banxico y Webuzo simulados por pre_http_request):
//   F1  lo escrito: sólo el nombre o nombre.ext, limpio; IDN, longitud, guiones y subdominios se validan;
//   F2  nombre libre: TODAS las extensiones con producto en UNA consulta a Namecheap, en el orden configurado; el
//       principal es .com; precio del primer año y renovación salen del producto de WooCommerce;
//   F3  ocupado: el principal pasa a la mejor libre, con «¿Es tuyo? Transfiérelo» (producto de transferencia) y libres;
//   F4  premium: no se vende, sin filtrar su precio de Namecheap;
//   F5  nombre.ext: la escrita es la principal;
//   F6  una extensión sin producto no aparece ni se consulta (ni escrita);
//   F7  caché corta compartida con el carrito y F8 límite por visitante; F9 nonce o cabecera propia desde el mismo
//       sitio; F10 tope de extensiones; F11 la respuesta no lleva nada de la cuenta de Namecheap;
//   F12 agregar por la Store API: dos dominios + hosting con el nombre exacto, validado en el servidor (nunca sin nombre,
//       ni repetido, ni ocupado, ni de otra extensión, ni con precio del navegador); F13 la transferencia sugerida;
//   F14 invitado; F15 carrito vacío con el buscador; F16 marcado accesible y sin rastro del constructor; F17 elemento
//       atómico (plantilla Twig con el Twig del núcleo); F18 ajustes; F19 Namecheap caído o una extensión que no atiende.
// Uso: php tests/hosting-domain-finder.php
namespace Automattic\WooCommerce\StoreApi\Exceptions {
	class RouteException extends \Exception {
		public $error_code; public $status;
		public function __construct( $code, $message, $status = 400 ) { parent::__construct( $message ); $this->error_code = $code; $this->status = $status; }
	}
}

namespace {
require __DIR__ . '/fixtures/hosting-wp.php';
$F = 'Digitalisimo_Hosting_Finder'; $C = 'Digitalisimo_Hosting_Checkout'; $H = 'Digitalisimo_Hosting';
$GLOBALS['real_transients'] = 1; $GLOBALS['transients'] = array();

function wc_add_notice( $m, $t = 'success' ) { $GLOBALS['notices'][] = array( $t, $m ); }
function wp_verify_nonce( $n, $a ) { return 'n:' . $a . ':' . get_current_user_id() === $n || 'n:' . $a === $n ? 1 : false; }
function wc_get_cart_url() { return 'https://tienda.test/carrito/'; }
function wc_get_checkout_url() { return 'https://tienda.test/pagar/'; }
function home_url( $p = '' ) { return 'https://tienda.test' . $p; }
function rest_url( $p = '' ) { return 'https://tienda.test/wp-json/' . $p; }
$GLOBALS['assets'] = array();
function wp_enqueue_style( $h ) { $GLOBALS['assets']['style'][ $h ] = 1; } function wp_enqueue_script( $h ) { $GLOBALS['assets']['script'][ $h ] = 1; }
function wp_register_style( $h, $src = '' ) { $GLOBALS['assets']['reg'][ $h ] = $src; } function wp_register_script( $h, $src = '' ) { $GLOBALS['assets']['reg'][ $h ] = $src; }
function wp_script_is( $h, $what = 'enqueued' ) { return 'registered' === $what ? isset( $GLOBALS['assets']['reg'][ $h ] ) : isset( $GLOBALS['assets']['script'][ $h ] ); }
function wp_style_is( $h, $what = 'enqueued' ) { return isset( $GLOBALS['assets']['style'][ $h ] ); }
function wp_add_inline_script( $h, $js, $pos = 'after' ) { $GLOBALS['assets']['inline'][ $h ][] = $js; }
function wp_localize_script( $h, $n, $d ) { $GLOBALS['localized'][ $n ] = $d; }
function get_woocommerce_currency_symbol() { return '&#36;'; }
function wc_get_price_decimals() { return 2; }
function wc_get_price_to_display( $p, $args = array() ) { return (float) ( $args['price'] ?? $p->get_price() ); }
class Digi_Test_Cart {
	public $cart_contents = array();
	public function get_cart() { return $this->cart_contents; }
	public function add( $pid, array $data = array() ) { $key = md5( $pid . serialize( $data ) ); $this->cart_contents[ $key ] = array( 'product_id' => $pid, 'quantity' => 1 ) + $data; return $key; }
}
WC()->cart = new Digi_Test_Cart();
/** Petición REST: $p en el cuerpo JSON y cabeceras en minúsculas. */
class Digi_Test_Request {
	public $p; public $h;
	public function __construct( $p, $h = array() ) { $this->p = $p; $this->h = $h; }
	public function get_param( $k ) { return $this->p[ $k ] ?? null; }
	public function get_json_params() { return $this->p; }
	public function get_body_params() { return array(); }
	public function get_header( $k ) { return $this->h[ strtolower( $k ) ] ?? null; }
}
/** El producto que recibe la Store API. */
class Digi_Test_Store_Product { public $id; public function __construct( $id ) { $this->id = $id; } public function get_id() { return $this->id; } public function get_parent_id() { return 0; } }
function nc_checks() { return digi_nc_count( 'namecheap.domains.check' ); }
function last_list() { $calls = array_values( array_filter( $GLOBALS['nc']['calls'], function ( $c ) { return 'namecheap.domains.check' === $c['cmd']; } ) ); return $calls ? explode( ',', end( $calls )['fields']['DomainList'] ) : array(); }
function search( $q ) { return Digitalisimo_Hosting_Finder::search( $q ); }
function new_ip() { $_SERVER['REMOTE_ADDR'] = '198.51.100.' . ( ++$GLOBALS['ip'] ); }
$GLOBALS['ip'] = 1; new_ip();
/**
 * Agregar por la Store API como WooCommerce 11.2 (CartAddItem + CartController::add_to_cart): la ruta arma el arreglo con
 * woocommerce_store_api_add_to_cart_data; el controlador pasa cart_item_data por woocommerce_add_cart_item_data, corre
 * woocommerce_add_to_cart_validation (con cart_item_data como sexto argumento) y después la acción
 * woocommerce_store_api_validate_add_to_cart con el producto y ESE ARREGLO (no la petición REST). Puede lanzar
 * RouteException; si no, guarda la línea.
 */
function store_add( $pid, array $body ) {
	$req = new Digi_Test_Request( $body + array( 'id' => $pid, 'quantity' => 1 ) );
	$add = Digitalisimo_Hosting_Checkout::store_api_data( array( 'id' => $pid, 'quantity' => $body['quantity'] ?? 1, 'variation' => array(), 'cart_item_data' => array() ), $req );
	$add['cart_item_data'] = Digitalisimo_Hosting::cart_domain( $add['cart_item_data'], $pid, 0 );
	$GLOBALS['notices'] = array();
	if ( ! Digitalisimo_Hosting_Checkout::maybe_validate_add( true, $pid, $add['quantity'], 0, array(), $add['cart_item_data'] ) ) throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'woocommerce_rest_add_to_cart_error', $GLOBALS['notices'][0][1] ?? 'no', 400 );
	Digitalisimo_Hosting_Checkout::store_api_validate( new Digi_Test_Store_Product( $pid ), $add );
	$key = WC()->cart->add( $pid, $add['cart_item_data'] );
	WC()->cart->cart_contents[ $key ]['quantity'] = $add['quantity'];
	return $key;
}
function store_error( $pid, array $body ) {
	try { store_add( $pid, $body ); } catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e ) { return $e->getMessage(); }
	return '';
}

digi_servers_reset();
digi_store_settings( array( 'domain_products' => ".com|20\n.mx|21\n.net|22", 'hosting_product_ids' => '14693, 14696', 'finder_order' => '.com, .mx, .net' ) );
digi_product( 22, 'Dominio .net', 280 );
$GLOBALS['products'][20]->price = 509; $GLOBALS['products'][20]->sale = 309; // primer año 309, renueva a 509
digi_product( 50, 'Transferencia de dominio', 250 );
update_post_meta( 50, '_digitalisimo_domain_kind', 'transfer' ); update_post_meta( 50, '_digitalisimo_tld', '*' );
Digitalisimo_Hosting_Products::flush();
$GLOBALS['nc']['taken'] = array( 'ocupado.com', 'ocupado.net' );
$GLOBALS['nc']['premium'] = array( 'lujo.com' );

// ── F1. Lo escrito ────────────────────────────────────────────────────────────────────────────────────────────
$p = $F::parse( '  https://WWW.MiNegocio.MX/contacto ' );
check( 'minegocio' === $p['label'] && '.mx' === $p['ext'] && '' === $p['error'], 'F1 nombre.ext con protocolo, www y ruta: «minegocio» + .mx' );
check( 'minegocio' === $F::parse( 'mi negocio' )['label'], 'F1 los espacios se quitan («mi negocio» → minegocio)' );
check( '' === $F::parse( 'minegocio' )['ext'], 'F1 sólo el nombre: sin extensión escrita' );
foreach ( array( '-malo', 'malo-', 'ab--cd', 'mal_nombre', 'a.b.c.com', str_repeat( 'a', 64 ), '<script>', '', 'www.sub.minegocio.com' ) as $bad ) check( '' !== $F::parse( $bad )['error'], 'F1 se rechaza «' . substr( $bad, 0, 20 ) . '»' );
check( '' === $F::parse( str_repeat( 'a', 63 ) . '.com' )['error'], 'F1 63 letras sí' );
check( '' !== $F::parse( str_repeat( 'a', 400 ) )['error'], 'F1 un texto enorme se rechaza antes de procesarlo' );
$idn = $F::parse( 'piñata.com' );
if ( function_exists( 'idn_to_ascii' ) ) check( 'xn--piata-pta' === $idn['label'] && '.com' === $idn['ext'] && 'piñata' === $idn['display'], 'F1 IDN: piñata.com se consulta como xn--piata-pta.com y se muestra con ñ · ' . json_encode( $idn ) );
else check( '' !== $idn['error'] && false !== strpos( $idn['error'], 'sin acentos' ), 'F1 IDN sin la extensión intl: se pide sin acentos' );
check( 'minegocio' === $F::parse( 'minegocio.com.mx' )['label'] && '.com.mx' === $F::parse( 'minegocio.com.mx' )['ext'], 'F1 una extensión de dos niveles que no se vende se reconoce entera (.com.mx)' );

// ── F2. Nombre libre: todas las extensiones en una consulta ────────────────────────────────────────────────────
$before = nc_checks();
$r = search( 'minegocio' );
check( is_array( $r ) && $before + 1 === nc_checks(), 'F2 UNA sola llamada a namecheap.domains.check para todas las extensiones' );
check( array( 'minegocio.com', 'minegocio.mx', 'minegocio.net', 'minegocioonline.com', 'minegociomx.com' ) === last_list(), 'F2 DomainList lleva las tres extensiones con producto, en el orden configurado, y dos variantes del nombre para sugerir si todo está ocupado · ' . implode( ',', last_list() ) );
check( 'minegocio.com' === $r['main']['domain'] && 'available' === $r['main']['status'] && 20 === $r['main']['product_id'], 'F2 sin extensión escrita, el principal es .com' );
check( '$309' === $r['main']['price_text'] && '$509' === $r['main']['renewal_text'], 'F2 primer año y renovación del producto de WooCommerce (309 / 509) · ' . $r['main']['price_text'] . ' / ' . $r['main']['renewal_text'] );
check( array( 'minegocio.mx', 'minegocio.net' ) === array_column( $r['rows'], 'domain' ) && '$600' === $r['rows'][0]['price_text'] && '$280' === $r['rows'][1]['price_text'], 'F2 debajo, una fila por extensión con su precio' );
check( false !== strpos( $r['rows'][0]['requirement'], 'titular' ) && '' === $r['main']['requirement'], 'F2 .mx con su aviso de requisitos; .com sin aviso' );
check( array( 14693, 14696 ) === array_column( $r['together'], 'id' ) && '$970' === $r['together'][0]['price_text'], 'F2 «se compran juntos»: los planes de hosting con su precio' );
update_option( 'digitalisimo_hosting_options', array( 'finder_order' => '.net, .mx' ) + get_option( 'digitalisimo_hosting_options' ) );
$r2 = search( 'otronegocio' );
check( array( 'otronegocio.net', 'otronegocio.mx', 'otronegocio.com', 'miotronegocio.com', 'otronegociomx.com' ) === last_list() && 'otronegocio.com' === $r2['main']['domain'] && array( 'otronegocio.net', 'otronegocio.mx' ) === array_column( $r2['rows'], 'domain' ), 'F2 el orden se configura (populares primero); .com sigue siendo el principal' );
update_option( 'digitalisimo_hosting_options', array( 'finder_order' => '.com, .mx, .net' ) + get_option( 'digitalisimo_hosting_options' ) );

// ── F3. Ocupado: mejor alternativa libre y transferencia ──────────────────────────────────────────────────────
$r = search( 'ocupado' );
check( 'ocupado.mx' === $r['main']['domain'] && 'ocupado.com' === $r['instead_of'] && 'taken' === $r['reason'], 'F3 .com ocupado: el principal pasa a la mejor libre en el orden (.mx), con el motivo «ocupado»' );
check( 'taken' === $r['taken']['status'] && 50 === $r['taken']['transfer']['product_id'] && '$250' === $r['taken']['transfer']['price_text'], 'F3 «¿Es tuyo? Transfiérelo» con el producto de transferencia de esa extensión' );
$net = array_values( array_filter( $r['rows'], function ( $x ) { return 'ocupado.net' === $x['domain']; } ) )[0];
check( 'taken' === $net['status'] && isset( $net['transfer'] ), 'F3 cada fila ocupada también ofrece la transferencia' );
check( array() === $r['alternatives'] || ! in_array( 'ocupado.mx', $r['alternatives'], true ), 'F3 las libres no repiten la principal' );
update_post_meta( 50, '_digitalisimo_tld', '.mx' ); Digitalisimo_Hosting_Products::flush();
$r = search( 'ocupado' );
check( ! isset( $r['taken']['transfer'] ), 'F3 sin producto de transferencia de esa extensión (sólo .mx), no se ofrece' );
update_post_meta( 50, '_digitalisimo_tld', '*' ); Digitalisimo_Hosting_Products::flush();
$GLOBALS['nc']['taken'] = array( 'todo.com', 'todo.mx', 'todo.net' );
$r = search( 'todo' );
check( 'todo.com' === $r['main']['domain'] && 'taken' === $r['main']['status'] && '' === $r['instead_of'] && isset( $r['main']['transfer'] ) && array( 'mitodo.com', 'todomx.com' ) === $r['alternatives'] && array( 'mitodo.com', 'todomx.com' ) === array_column( $r['suggestions'], 'domain' ), 'F3 todo ocupado: el principal es el ocupado, con su transferencia, y las libres son variantes del nombre' );

// ── F4. Premium ───────────────────────────────────────────────────────────────────────────────────────────────
$r = search( 'lujo' );
$com = array_values( array_filter( $r['rows'], function ( $x ) { return 'lujo.com' === $x['domain']; } ) )[0];
check( 'lujo.mx' === $r['main']['domain'] && 'lujo.com' === $r['instead_of'] && 'premium' === $com['status'], 'F4 premium: no se vende; el principal pasa a una libre' );
check( 'premium' === $r['reason'] && null === $r['taken'] && ! isset( $com['transfer'] ), 'F4 el motivo es «premium» (nunca «ocupado») y no se ofrece transferirlo' );
$fjs = file_get_contents( __DIR__ . '/../digitalisimo-hosting/assets/finder.js' );
check( false !== strpos( $fjs, "premium: 'es premium (precio especial) y no se vende aquí.'" ) && false !== strpos( $fjs, "unknown: 'no se pudo consultar ahora.'" ) && false !== strpos( $fjs, 'WHY[d.reason]' ), 'F4 el script dice el motivo real (ocupado, premium o sin consultar)' );
check( false === strpos( json_encode( $r ), '2500' ), 'F4 el precio premium de Namecheap no sale en la respuesta' );

// ── F5. nombre.ext ────────────────────────────────────────────────────────────────────────────────────────────
$r = search( 'algo.net' );
check( 'algo.net' === $r['main']['domain'] && 22 === $r['main']['product_id'] && ! in_array( 'algo.net', array_column( $r['rows'], 'domain' ), true ) && 2 === count( $r['rows'] ), 'F5 nombre.ext: la escrita es la principal y las demás van debajo' );

// ── F6. Extensión sin producto ────────────────────────────────────────────────────────────────────────────────
update_option( 'digitalisimo_hosting_options', array( 'domain_products' => ".com|20\n.mx|21\n.net|22\n.org|" ) + get_option( 'digitalisimo_hosting_options' ) );
Digitalisimo_Hosting_Products::flush();
$r = search( 'sinorg' );
check( ! preg_grep( '/\.org$/', last_list() ) && ! preg_grep( '/\.org$/', array_column( $r['rows'], 'domain' ) ), 'F6 .org sin producto: no se consulta ni aparece' );
$r = search( 'sinorg2.org' );
check( ! preg_grep( '/\.org$/', last_list() ) && 'sinorg2.com' === $r['main']['domain'] && false !== strpos( $r['notice'], 'no vendemos dominios .org' ), 'F6 escrita sin producto (sinorg2.org): aviso, el principal es .com y .org no se consulta' );
update_option( 'digitalisimo_hosting_options', array( 'domain_products' => ".com|20\n.mx|21\n.net|22" ) + get_option( 'digitalisimo_hosting_options' ) );
Digitalisimo_Hosting_Products::flush();

// ── F7. Caché corta (compartida con el carrito) ───────────────────────────────────────────────────────────────
$before = nc_checks();
search( 'minegocio' );
check( $before === nc_checks(), 'F7 la misma búsqueda en los minutos siguientes no vuelve a consultar a Namecheap' );
check( null !== $C::cached( 'minegocio.mx' ) && '' === $C::line_error( 21, array( 'digitalisimo_domain' => 'minegocio.mx' ), false ) && $before === nc_checks(), 'F7 agregar lo buscado usa la misma caché (el carrito no vuelve a consultar)' );
$GLOBALS['now_offset'] = $C::CHECK_TTL + 1;
// Otra petición (sin lo que guardó ésta en memoria): la caché ya venció.
$m = new ReflectionProperty( $C, 'checked' ); $m->setAccessible( true ); $m->setValue( null, array() );
search( 'minegocio' );
check( $before + 1 === nc_checks(), 'F7 vencida la caché (10 minutos), se consulta de nuevo' );
$GLOBALS['now_offset'] = 0;

// ── F8. Límite por visitante ──────────────────────────────────────────────────────────────────────────────────
new_ip();
$codes = array();
for ( $i = 0; $i < $F::RATE + 1; $i++ ) { $x = $F::rest( new Digi_Test_Request( array( 'q' => 'repetido' ) ) ); $codes[] = is_wp_error( $x ) ? $x->code : 'ok'; }
check( $F::RATE === count( array_keys( $codes, 'ok', true ) ) && 'digitalisimo_finder_rate' === end( $codes ), 'F8 ' . $F::RATE . ' búsquedas por minuto; la siguiente recibe «espera un minuto» (429)' );
new_ip();
check( ! is_wp_error( $F::rest( new Digi_Test_Request( array( 'q' => 'repetido' ) ) ) ), 'F8 otro visitante no queda frenado' );

// ── F9. Nonce o cabecera propia desde el mismo sitio ──────────────────────────────────────────────────────────
check( true === $F::permission( new Digi_Test_Request( array( 'q' => 'x', 'nonce' => 'n:' . $F::NONCE ) ) ), 'F9 con el nonce del buscador, sí' );
check( is_wp_error( $F::permission( new Digi_Test_Request( array( 'q' => 'x', 'nonce' => 'otro' ) ) ) ), 'F9 sin nonce válido ni cabecera, no (403)' );
check( true === $F::permission( new Digi_Test_Request( array( 'q' => 'x', 'nonce' => 'vencido' ), array( 'x-digitalisimo-finder' => '1', 'origin' => 'https://tienda.test' ) ) ), 'F9 página en caché con el nonce vencido: la cabecera propia desde el mismo sitio' );
check( is_wp_error( $F::permission( new Digi_Test_Request( array( 'q' => 'x' ), array( 'x-digitalisimo-finder' => '1', 'origin' => 'https://otro-sitio.test' ) ) ) ), 'F9 la cabecera desde otro sitio, no' );

// ── F10. Tope de extensiones por consulta ─────────────────────────────────────────────────────────────────────
$lines = ".com|20\n.mx|21\n.net|22";
foreach ( array( 'lat', 'org', 'info', 'biz', 'xyz', 'online', 'site', 'store', 'tech', 'app', 'dev', 'shop', 'club', 'io' ) as $i => $t ) { digi_product( 100 + $i, 'Dominio .' . $t, 400 ); $lines .= "\n." . $t . '|' . ( 100 + $i ); }
update_option( 'digitalisimo_hosting_options', array( 'domain_products' => $lines ) + get_option( 'digitalisimo_hosting_options' ) );
Digitalisimo_Hosting_Products::flush();
$r = search( 'muchas' );
check( $F::MAX_EXTS + 2 === count( last_list() ) && $F::MAX_EXTS === 1 + count( $r['rows'] ), 'F10 17 extensiones con producto: se consultan ' . $F::MAX_EXTS . ' (' . count( last_list() ) . ')' );
$r = search( 'muchas2.io' );
check( in_array( 'muchas2.io', last_list(), true ) && 'muchas2.io' === $r['main']['domain'] && $F::MAX_EXTS + 2 === count( last_list() ), 'F10 la escrita entra aunque quede después del tope' );
update_option( 'digitalisimo_hosting_options', array( 'domain_products' => ".com|20\n.mx|21\n.net|22" ) + get_option( 'digitalisimo_hosting_options' ) );
Digitalisimo_Hosting_Products::flush();

// ── F11. Nada de la cuenta de Namecheap ───────────────────────────────────────────────────────────────────────
$json = json_encode( $F::rest( new Digi_Test_Request( array( 'q' => 'cuenta' ) ) ) );
foreach ( array( 'CLAVE-NAMECHEAP', 'nc-api', 'nc-user', '203.0.113.5', 'ApiUser', 'DomainCheckResult', 'xml' ) as $secret ) check( false === stripos( $json, $secret ), 'F11 la respuesta no lleva «' . $secret . '»' );
$call = end( $GLOBALS['nc']['calls'] );
check( 'CLAVE-NAMECHEAP' === $call['key'] && ! isset( $call['fields']['ApiKey'] ), 'F11 la clave viaja sólo en el cuerpo del POST a Namecheap' );

// ── F12. Dos dominios + hosting por la Store API, con el nombre exacto ─────────────────────────────────────────
WC()->cart = new Digi_Test_Cart();
$k1 = store_add( 20, array( 'digitalisimo_domain' => 'minegocio.com' ) );
$k2 = store_add( 21, array( 'digitalisimo_domain' => 'MiNegocio.mx' ) );
$k3 = store_add( 14693, array() );
$cc = WC()->cart->cart_contents;
check( 'minegocio.com' === $cc[ $k1 ]['digitalisimo_domain'] && 'minegocio.mx' === $cc[ $k2 ]['digitalisimo_domain'] && 3 === count( $cc ), 'F12 dos dominios y un plan en el carrito, cada dominio con su nombre exacto' );
check( array() === $C::cart_errors(), 'F12 el carrito se puede pagar (sin pedir otra vez el dominio del plan)' );
$shown = Digitalisimo_Hosting::cart_item_data( array(), $cc[ $k2 ] );
check( 'Dominio' === $shown[0]['name'] && 'minegocio.mx' === $shown[0]['value'], 'F12 el carrito (y la barra, que lee la Store API) muestra el nombre exacto' );
check( false !== strpos( store_error( 20, array() ), 'Busca primero el nombre' ), 'F12 un dominio sin nombre nunca entra' );
check( false !== strpos( store_error( 20, array( 'digitalisimo_domain' => 'minegocio.com' ) ), 'ya está en tu carrito' ), 'F12 el mismo dominio no se agrega dos veces' );
check( false !== strpos( store_error( 20, array( 'digitalisimo_domain' => 'ocupado.com' ) ), 'ya está registrado' ), 'F12 un ocupado no entra' );
check( false !== strpos( store_error( 20, array( 'digitalisimo_domain' => 'lujo.com' ) ), 'premium' ), 'F12 un premium no entra' );
check( false !== strpos( store_error( 20, array( 'digitalisimo_domain' => 'otro.mx' ) ), 'no es .com' ), 'F12 un .mx no entra con el producto .com' );
check( '' !== store_error( 20, array( 'digitalisimo_domain' => 'mal nombre.com' ) ), 'F12 un nombre inválido no entra' );
check( false !== strpos( store_error( 20, array( 'digitalisimo_domain' => 'dosanios.com', 'quantity' => 2 ) ), 'una sola vez' ), 'F12 un dominio no entra con cantidad 2' );
$kq = WC()->cart->add( 22, array( 'digitalisimo_domain' => 'cantidad.net' ) ); WC()->cart->cart_contents[ $kq ]['quantity'] = 3;
check( preg_grep( '/Cada dominio se compra por un año: deja la cantidad de cantidad\.net en 1/', $C::cart_errors() ), 'F12 un dominio con cantidad 3 en el carrito no se paga (la venta automática se detendría)' );
unset( WC()->cart->cart_contents[ $kq ] );
$noname = array( 'id' => 20, 'quantity' => 1, 'variation' => array(), 'cart_item_data' => array() );
try { $C::store_api_validate( new Digi_Test_Store_Product( 20 ), $noname ); $thrown = ''; } catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e ) { $thrown = $e->error_code; }
check( 'digitalisimo_hosting_domain' === $thrown, 'F12 la acción recibe el ARREGLO de WooCommerce (id, quantity, cart_item_data) y aun así frena un dominio sin nombre' );
$k4 = store_add( 22, array( 'digitalisimo_domain' => 'barato.net', 'price' => 1, 'prices' => array( 'price' => 1 ) ) );
check( ! preg_grep( '/price/', array_keys( WC()->cart->cart_contents[ $k4 ] ) ), 'F12 un precio enviado por el navegador no llega a la línea (el precio es el del producto)' );
$tf = file_get_contents( __DIR__ . '/../digitalisimo-hosting/assets/finder.js' );
check( preg_match( '/const body = \{ id: Number\(b\.dataset\.addProduct\), quantity: 1 \};/', $tf ) && false === stripos( $tf, 'price:' ), 'F12 el script sólo manda el producto, la cantidad 1 y el nombre (nunca un precio)' );

// ── F13. Transferencia sugerida ───────────────────────────────────────────────────────────────────────────────
$GLOBALS['nc']['taken'] = array( 'ocupado.com', 'ocupado.net' );
$k5 = store_add( 50, array( 'digitalisimo_domain' => 'ocupado.com' ) );
check( 'ocupado.com' === WC()->cart->cart_contents[ $k5 ]['digitalisimo_domain'] && preg_grep( '/código de transferencia \(EPP\)/', $C::cart_errors() ), 'F13 «Transferirlo» agrega la transferencia con el nombre; el EPP se pide antes de pagar' );
check( false !== strpos( store_error( 50, array( 'digitalisimo_domain' => 'librecito.com' ) ), 'no está registrado' ), 'F13 un libre no se transfiere' );
check( false !== strpos( store_error( 50, array() ), 'transferir' ), 'F13 una transferencia sin nombre no entra' );

// ── F14. Invitado ─────────────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['user_id'] = 0; $GLOBALS['caps'] = array();
new_ip();
WC()->cart = new Digi_Test_Cart();
$x = $F::rest( new Digi_Test_Request( array( 'q' => 'invitado', 'nonce' => wp_create_nonce( $F::NONCE ) ) ) );
check( ! is_wp_error( $x ) && true === $F::permission( new Digi_Test_Request( array( 'nonce' => wp_create_nonce( $F::NONCE ) ) ) ) && 'invitado.com' === $x['main']['domain'], 'F14 un invitado busca (nonce del invitado)' );
check( store_add( 20, array( 'digitalisimo_domain' => 'invitado.com' ) ) && 1 === count( WC()->cart->cart_contents ), 'F14 un invitado agrega sin iniciar sesión' );
$GLOBALS['user_id'] = 1; unset( $GLOBALS['caps'] );

// ── F15. Carrito vacío con el buscador ────────────────────────────────────────────────────────────────────────
$GLOBALS['assets'] = array();
ob_start(); $F::empty_cart(); $empty = ob_get_clean();
check( false !== strpos( $empty, 'data-digitalisimo-finder' ) && isset( $GLOBALS['assets']['script'][ $F::HANDLE ] ), 'F15 carrito vacío (clásico): el buscador, con su script' );
WC()->cart = new Digi_Test_Cart();
$blk = $F::empty_cart_block( '<div class="wp-block-woocommerce-empty-cart-block">Vacío</div>', array( 'blockName' => 'woocommerce/empty-cart-block' ) );
check( 0 === strpos( $blk, '<div class="wp-block-woocommerce-empty-cart-block">Vacío<div class="digitalisimo-finder-empty-cart">' ) && '</div></div>' === substr( $blk, -12 ) && false !== strpos( $blk, 'data-digitalisimo-finder' ), 'F15 carrito vacío (bloques): el buscador DENTRO del bloque «carrito vacío» (fuera se vería con el carrito lleno)' );
check( '<p>x</p>' === $F::empty_cart_block( '<p>x</p>', array( 'blockName' => 'woocommerce/cart' ) ), 'F15 los demás bloques no cambian' );
$F::footer_config();
$inline = implode( '', $GLOBALS['assets']['inline'][ $F::HANDLE ] ?? array() );
check( false !== strpos( $inline, 'window.digitalisimoFinder=' ) && false !== strpos( $inline, 'wc\/store\/v1' ) && false === stripos( $inline, 'CLAVE' ), 'F15 la configuración (rutas y nonces, sin claves) va delante del script' );
$GLOBALS['assets'] = array();
$F::footer_config();
check( empty( $GLOBALS['assets']['inline'] ), 'F15 una página sin buscador no carga su configuración' );
update_option( 'digitalisimo_hosting_options', array( 'enabled' => 0 ) + get_option( 'digitalisimo_hosting_options' ) );
ob_start(); $F::empty_cart(); check( '' === ob_get_clean() && '' === $F::shortcode(), 'F15 con Hosting apagado no se pinta nada' );
update_option( 'digitalisimo_hosting_options', array( 'enabled' => 1 ) + get_option( 'digitalisimo_hosting_options' ) );

// ── F16. Marcado accesible, móvil y sin rastro del constructor ────────────────────────────────────────────────
$_GET['dominio'] = 'desde-enlace"><b>';
$html = $F::shortcode( array( 'titulo' => 'Tu dominio <b>ya</b>', 'boton' => 'Buscar ahora' ) );
unset( $_GET['dominio'] );
preg_match( '/<input id="([^"]+)"/', $html, $id );
check( $id && false !== strpos( $html, '<label class="digitalisimo-finder__label" for="' . $id[1] . '">' ) && false !== strpos( $html, 'aria-describedby="' . $id[1] . '-hint"' ), 'F16 el campo tiene su etiqueta visible y su ayuda' );
check( false !== strpos( $html, 'role="search"' ) && false !== strpos( $html, 'type="search"' ) && false !== strpos( $html, 'role="status" aria-live="polite"' ) && false !== strpos( $html, '<div class="digitalisimo-finder__results" aria-busy="false">' ) && 1 === substr_count( $html, 'aria-live' ), 'F16 búsqueda con role=search; sólo el aviso (role=status) es región viva, los resultados no' );
check( false !== strpos( $html, 'Tu dominio &lt;b&gt;ya&lt;/b&gt;' ) && false !== strpos( $html, '>Buscar ahora<' ) && false === strpos( $html, '"><b>' ), 'F16 los textos y ?dominio= salen escapados' );
$css = file_get_contents( __DIR__ . '/../digitalisimo-hosting/assets/finder.css' );
foreach ( array( 'html' => $html, 'js' => $tf, 'css' => preg_replace( '/--e-global-[a-z-]+/', '', $css ) ) as $what => $src ) check( false === stripos( $src, 'elementor' ), 'F16 sin «elementor» en el ' . $what . ' que ve el visitante' );
check( false !== strpos( $css, 'var(--e-global-color-accent,var(--e-global-color-primary,#1f2937))' ) && false !== strpos( $css, 'var(--e-global-typography-text-font-family,inherit)' ), 'F16 colores y letra del kit del sitio con un respaldo neutro' );
check( ! preg_match( '/#(343aee|00ff99|0b1d3a)/i', $css ), 'F16 nunca la paleta de DIGITALÍSIMO' );
check( preg_match( '/\.digitalisimo-finder__bar\{display:flex;flex-direction:column/', $css ) && false !== strpos( $css, '@media (min-width:600px)' ) && false !== strpos( $css, 'min-height:44px' ) && false !== strpos( $css, 'env(safe-area-inset-bottom' ), 'F16 móvil primero: columna en el celular, fila desde 600 px, botones de 44 px y barra con el área segura' );
check( false === strpos( $tf, 'innerHTML' ) && false !== strpos( $tf, "setAttribute('aria-busy', 'true')" ) && false !== strpos( $tf, "'aria-label', 'Tu carrito'" ), 'F16 el script pinta con textContent, marca aria-busy y la barra es una región con nombre' );

// ── F17. Elemento del editor atómico ──────────────────────────────────────────────────────────────────────────
check( false === $F::atomic_widget( new stdClass() ), 'F17 sin el núcleo atómico no se registra ni se carga nada' );
$cls = file_get_contents( __DIR__ . '/../digitalisimo-hosting/includes/atomic/class-atomic-domain-finder.php' );
check( false !== strpos( $cls, "return 'e-digitalisimo-domain-finder';" ) && false !== strpos( $cls, "return 'Buscador de dominios';" ) && false !== strpos( $cls, 'Digitalisimo_Hosting_Finder::HANDLE' ), 'F17 «Buscador de dominios» (e-digitalisimo-domain-finder) con la hoja y el script del shortcode' );
$core = __DIR__ . '/../digitalisimo-elements/elementor';
if ( is_dir( $core . '/vendor_prefixed/twig/twig/twig/src' ) ) {
	foreach ( array( 'polyfill-ctype/bootstrap.php', 'polyfill-mbstring/bootstrap.php', 'polyfill-php80/bootstrap.php', 'polyfill-php81/bootstrap.php', 'deprecation-contracts/function.php' ) as $file ) if ( is_file( $core . '/vendor_prefixed/twig/symfony/' . $file ) ) require_once $core . '/vendor_prefixed/twig/symfony/' . $file;
	spl_autoload_register( static function ( $class ) use ( $core ) {
		foreach ( array( 'ElementorDeps\\Twig\\' => '/vendor_prefixed/twig/twig/twig/src/', 'ElementorDeps\\Symfony\\Polyfill\\Ctype\\' => '/vendor_prefixed/twig/symfony/polyfill-ctype/', 'ElementorDeps\\Symfony\\Polyfill\\Mbstring\\' => '/vendor_prefixed/twig/symfony/polyfill-mbstring/', 'ElementorDeps\\Symfony\\Polyfill\\Php80\\' => '/vendor_prefixed/twig/symfony/polyfill-php80/', 'ElementorDeps\\Symfony\\Polyfill\\Php81\\' => '/vendor_prefixed/twig/symfony/polyfill-php81/' ) as $prefix => $dir ) {
			if ( 0 === strpos( $class, $prefix ) ) { $f = $core . $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php'; if ( is_file( $f ) ) require_once $f; return; }
		}
	} );
	$twig = new \ElementorDeps\Twig\Environment( new \ElementorDeps\Twig\Loader\ArrayLoader( array( 'elementor/macros' => file_get_contents( $core . '/modules/atomic-widgets/elements/base/_macros.html.twig' ), 'digitalisimo/hosting/domain-finder' => file_get_contents( __DIR__ . '/../digitalisimo-hosting/includes/atomic/atomic-domain-finder.html.twig' ) ) ), array( 'autoescape' => 'name', 'strict_variables' => false ) );
	$tw = $twig->render( 'digitalisimo/hosting/domain-finder', array( 'id' => 'abc1234', 'interaction_id' => 'abc1234', 'type' => 'e-digitalisimo-domain-finder', 'settings' => array( 'classes' => array( 'e-abc1234-local' ), 'title' => 'Elige <i>tu</i> nombre', 'placeholder' => '', 'button' => 'Ver precios' ), 'tag' => 'div', 'base_styles' => array() ) );
	check( false !== strpos( $tw, 'class="e-abc1234-local digitalisimo-finder"' ) && false !== strpos( $tw, 'data-digitalisimo-finder' ), 'F17 la plantilla pinta la raíz del buscador con la clase del estilo del editor · ' . substr( $tw, 0, 120 ) );
	check( false !== strpos( $tw, '<label class="digitalisimo-finder__label" for="dhf-abc1234">Elige &lt;i&gt;tu&lt;/i&gt; nombre</label>' ) && false !== strpos( $tw, 'placeholder="minegocio"' ) && false !== strpos( $tw, '>Ver precios<' ), 'F17 textos escapados y los de fábrica si se dejan vacíos' );
	// Mismo marcado que el shortcode (salvo el id y la raíz).
	$norm = function ( $h ) { $h = preg_replace( '/dhf-[a-z0-9]+/', 'ID', $h ); $h = preg_replace( '/<div class="[^"]*digitalisimo-finder"[^>]*>/', '<div ROOT>', $h ); $h = preg_replace( '/<label class="digitalisimo-finder__label" for="ID">[^<]*<\/label>/', 'LABEL', $h ); $h = preg_replace( '/placeholder="[^"]*"|value="[^"]*"|>[^<>]*<\/button>/', '', $h ); return preg_replace( '/\s*\n\s*/', '', trim( $h ) ); };
	$plain = $norm( $F::html() );
	check( $norm( $tw ) === $plain, 'F17 el elemento atómico y el shortcode pintan el mismo marcado' . ( $norm( $tw ) === $plain ? '' : "\n" . $norm( $tw ) . "\n" . $plain ) );
	check( false === stripos( preg_replace( '/class="e-abc1234-local /', 'class="', $tw ), 'elementor' ), 'F17 sin «elementor» en lo que pinta el elemento' );
} else {
	echo "AVISO: sin el Twig del núcleo, se salta el render de la plantilla atómica.\n";
}

// ── F18. Ajustes ──────────────────────────────────────────────────────────────────────────────────────────────
$saved = Digitalisimo_Hosting::mcp_update( array( 'finder_order' => '.mx, .com', 'finder_upsell_ids' => '14696, abc, 14696, 0', 'finder_requirements' => ".mx|Requisito: datos reales" ) );
$o = get_option( 'digitalisimo_hosting_options' );
check( ! is_wp_error( $saved ) && '14696' === $o['finder_upsell_ids'] && '.mx, .com' === $o['finder_order'], 'F18 orden, avisos y «se compran juntos» se guardan limpios (también por MCP) · ' . json_encode( is_wp_error( $saved ) ? $saved->message : $o['finder_upsell_ids'] ) );
$r = search( 'ajustes' );
check( 'ajustes.com' === $r['main']['domain'] && 'ajustes.mx' === $r['rows'][0]['domain'] && 'Requisito: datos reales' === $r['rows'][0]['requirement'] && array( 14696 ) === array_column( $r['together'], 'id' ), 'F18 el buscador usa el orden, el aviso y los planes elegidos' );

// ── F19. Namecheap caído o una extensión que no atiende ───────────────────────────────────────────────────────
$GLOBALS['nc']['unsupported'] = array( '.net' );
$r = search( 'raro' );
$netrow = array_values( array_filter( $r['rows'], function ( $x ) { return 'raro.net' === $x['domain']; } ) )[0];
check( 'unknown' === $netrow['status'], 'F19 una extensión que Namecheap no atiende queda «no pudimos consultarlo», sin botón' );
unset( $GLOBALS['nc']['unsupported'] );
$GLOBALS['nc']['timeout']['namecheap.domains.check'] = 1;
$x = search( 'caido' );
check( is_wp_error( $x ) && 'digitalisimo_finder_unavailable' === $x->code && false === strpos( $x->message, 'cURL' ) && false !== strpos( $x->message, 'Intenta de nuevo' ), 'F19 Namecheap sin responder: aviso amable, sin detalles técnicos' );

unset( $GLOBALS['nc']['timeout']['namecheap.domains.check'] );

// ── F20. Tope del sitio a Namecheap (la cuota de la API es de la cuenta) ──────────────────────────────────────
$GLOBALS['now_offset'] = 7200; // ventanas nuevas
add_filter( 'digitalisimo_hosting_finder_global', function () { return array( 'minute' => 2, 'hour' => 400 ); } );
$before = nc_checks();
$a = search( 'tope-uno' ); $b = search( 'tope-dos' ); $c3 = search( 'tope-tres' );
check( is_array( $a ) && is_array( $b ) && is_wp_error( $c3 ) && 'digitalisimo_finder_busy' === $c3->code && $before + 2 === nc_checks(), 'F20 con el tope del sitio lleno no se consulta a Namecheap: «muchas búsquedas, intenta en un minuto»' );
check( is_array( search( 'tope-uno' ) ), 'F20 lo que ya está en la caché sigue saliendo con el tope lleno' );
$GLOBALS['now_offset'] = 7200 + 61;
check( is_array( search( 'tope-tres' ) ), 'F20 al minuto siguiente vuelve a consultar' );
$GLOBALS['hooks']['digitalisimo_hosting_finder_global'] = array();
// IPv6: el límite por visitante cuenta el /64 entero.
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2::10';
for ( $i = 0; $i < $F::RATE; $i++ ) $F::rest( new Digi_Test_Request( array( 'q' => 'seis' ) ) );
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:ffff::99';
$x = $F::rest( new Digi_Test_Request( array( 'q' => 'seis' ) ) );
check( is_wp_error( $x ) && 'digitalisimo_finder_rate' === $x->code, 'F20 IPv6: otra dirección del mismo /64 comparte el límite por visitante' );
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:3::10';
check( ! is_wp_error( $F::rest( new Digi_Test_Request( array( 'q' => 'seis' ) ) ) ), 'F20 IPv6: otro /64 no' );
new_ip();

// ── F21. Namecheap rechaza el lote entero ─────────────────────────────────────────────────────────────────────
$GLOBALS['nc']['batch_reject'] = array( '.net' );
$before = nc_checks();
$r = search( 'lote' );
$byd = array_column( array_merge( array( $r['main'] ), $r['rows'] ), 'status', 'domain' );
check( 'available' === $byd['lote.com'] && 'available' === $byd['lote.mx'] && 'unknown' === $byd['lote.net'] && $before + 6 === nc_checks(), 'F21 si Namecheap rechaza el lote por una extensión, se consultan una por una (las 3 extensiones y las 2 variantes) y las demás salen · ' . json_encode( $byd ) );
check( isset( $F::bad_exts()['.net'] ) && 1 === count( $F::bad_exts() ), 'F21 la extensión que rompe el lote queda anotada' );
$before = nc_checks();
$r = search( 'lote2' );
$byd = array_column( array_merge( array( $r['main'] ), $r['rows'] ), 'status', 'domain' );
check( $before + 1 === nc_checks() && ! preg_grep( '/\.net$/', last_list() ) && 'unknown' === $byd['lote2.net'] && 'available' === $byd['lote2.com'], 'F21 la búsqueda siguiente la saca del lote: una sola consulta, sin reintentos uno por uno' );
// El respaldo uno por uno tiene tiempo total: sin tiempo, no se hace (y no pasa de max_execution_time).
$GLOBALS['nc']['batch_reject'] = array( '.mx' );
add_filter( 'digitalisimo_hosting_finder_fallback_seconds', function () { return 0; } );
$before = nc_checks();
$x = search( 'sintiempo' );
check( $before + 1 === nc_checks() && is_wp_error( $x ), 'F21 sin tiempo para el respaldo no se consulta una por una (aviso amable, sin colgar la página)' );
$GLOBALS['hooks']['digitalisimo_hosting_finder_fallback_seconds'] = array();
unset( $GLOBALS['nc']['batch_reject'] );
delete_transient( $F::BAD_EXTS );

// ── F22. Sólo productos que se pueden comprar ─────────────────────────────────────────────────────────────────
$GLOBALS['product_status'][22] = 'private';
$r = search( 'privado' );
$net = array_values( array_filter( $r['rows'], function ( $x ) { return 'privado.net' === $x['domain']; } ) );
check( ! $net && ! preg_grep( '/\.net$/', last_list() ), 'F22 un producto de dominio privado no aparece ni se consulta' );
unset( $GLOBALS['product_status'][22] );
$GLOBALS['products'][22]->price = '';
$r = search( 'sinprecio' );
check( ! preg_grep( '/\.net$/', last_list() ) && ! preg_grep( '/\.net$/', array_column( $r['rows'], 'domain' ) ), 'F22 un producto sin precio no aparece ni se consulta' );
$GLOBALS['products'][22]->price = 280;
$saved22 = $GLOBALS['products'][22]; unset( $GLOBALS['products'][22] ); Digitalisimo_Hosting_Products::flush();
$rep = Digitalisimo_Hosting_Products::report( (string) get_option( 'digitalisimo_hosting_options' )['domain_products'] );
$r = search( 'borrado' );
check( ! isset( $rep['resolved']['.net'] ) && in_array( '.net', $rep['unresolved'], true ) && ! preg_grep( '/\.net$/', last_list() ), 'F22 un ID de «Productos por extensión» cuyo producto se borró ya no resuelve la extensión' );
$GLOBALS['products'][22] = $saved22; Digitalisimo_Hosting_Products::flush();

// ── F23. Todo ocupado: variantes del nombre en el mismo lote ──────────────────────────────────────────────────
$GLOBALS['nc']['taken'] = array( 'lleno.com', 'lleno.mx', 'lleno.net', 'llenomx.com' );
$before = nc_checks();
$r = search( 'lleno' );
check( $before + 1 === nc_checks() && 'taken' === $r['main']['status'] && array( 'milleno.com' ) === array_column( $r['suggestions'], 'domain' ) && 'available' === $r['suggestions'][0]['status'] && 20 === $r['suggestions'][0]['product_id'] && '$309' === $r['suggestions'][0]['price_text'], 'F23 todo ocupado: sugiere las variantes libres del nombre (sólo las libres, con producto y precio), sin otra consulta · ' . json_encode( array_column( $r['suggestions'], 'domain' ) ) );
check( array() === search( 'minegocio' )['suggestions'], 'F23 con algo libre no hay sugerencias' );

// ── F24. Acceso anticipado (EapFee) = precio especial ─────────────────────────────────────────────────────────
$GLOBALS['nc']['eap'] = array( 'anticipado.com' );
$r = search( 'anticipado' );
$com = array_values( array_filter( $r['rows'], function ( $x ) { return 'anticipado.com' === $x['domain']; } ) );
check( 'anticipado.mx' === $r['main']['domain'] && 'premium' === $r['reason'] && $com && 'premium' === $com[0]['status'], 'F24 un dominio con EapFee mayor que 0 no se vende (como premium)' );
check( false !== strpos( store_error( 20, array( 'digitalisimo_domain' => 'anticipado.com' ) ), 'premium' ), 'F24 tampoco entra al carrito' );
$one = Digitalisimo_Hosting_Namecheap::from_settings()->check( 'anticipado.com' );
check( 'ok' === $one['kind'] && true === $one['premium'], 'F24 check() de un solo dominio también lo trata como precio especial' );
unset( $GLOBALS['nc']['eap'] );

// ── F25. Saldo del registrador antes de cobrar ────────────────────────────────────────────────────────────────
$GLOBALS['nc']['balance'] = 0.02; $GLOBALS['mails'] = array();
delete_transient( 'digitalisimo_hosting_nc_balance' );
WC()->cart = new Digi_Test_Cart();
$ks = store_add( 20, array( 'digitalisimo_domain' => 'tiendaluz.com' ) );
$errs = $C::cart_errors();
check( preg_grep( '/Por ahora no podemos completar en línea la compra de tiendaluz\.com\. Escríbenos/', $errs ) && ! preg_grep( '/saldo|USD|Namecheap/i', $errs ), 'F25 sin saldo en el registrador, el carrito no deja pagar el dominio, con un texto llano (sin hablar de saldo) · ' . json_encode( $errs ) );
$owner = array_values( array_filter( $GLOBALS['mails'], function ( $m ) { return false !== strpos( $m['subject'], 'saldo del registrador' ); } ) );
check( 1 === count( $owner ) && false !== strpos( $owner[0]['body'], '0.02 USD' ) && false !== strpos( $owner[0]['body'], 'tiendaluz.com' ), 'F25 el dueño recibe el aviso con el saldo y el costo' );
$C::cart_errors();
check( 1 === count( array_filter( $GLOBALS['mails'], function ( $m ) { return false !== strpos( $m['subject'], 'saldo del registrador' ); } ) ), 'F25 el aviso al dueño no se repite en cada vista del carrito' );
$calls = digi_nc_count( 'namecheap.users.getBalances' ); $C::cart_errors();
check( $calls === digi_nc_count( 'namecheap.users.getBalances' ), 'F25 el saldo se guarda unos minutos (no se consulta en cada vista)' );
$GLOBALS['nc']['balance'] = 50.0; delete_transient( 'digitalisimo_hosting_nc_balance' );
check( array() === $C::cart_errors(), 'F25 con saldo suficiente se paga' );
$GLOBALS['nc']['balance'] = 15.0; delete_transient( 'digitalisimo_hosting_nc_balance' );
store_add( 21, array( 'digitalisimo_domain' => 'tiendaluz.mx' ) );
check( preg_grep( '/tiendaluz\.com, tiendaluz\.mx/', $C::cart_errors() ), 'F25 se suma el costo de todos los dominios del carrito del mismo registrador (11.46 + 25.18 > 15)' );
delete_transient( 'digitalisimo_hosting_nc_balance' ); $GLOBALS['nc']['timeout']['namecheap.users.getBalances'] = 1;
check( array() === $C::cart_errors(), 'F25 si no se puede consultar el saldo no se frena (el aprovisionamiento deja el pedido en espera y avisa)' );
unset( $GLOBALS['nc']['timeout']['namecheap.users.getBalances'] );
add_filter( 'digitalisimo_hosting_registrar_balance', function ( $pre, $ext ) { return '.mx' === $ext ? array( 'kind' => 'ok', 'value' => 0.5, 'registrar' => 'otro-registrador' ) : $pre; }, 10, 2 );
delete_transient( 'digitalisimo_hosting_nc_balance' ); $GLOBALS['nc']['balance'] = 50.0;
check( preg_grep( '/compra de tiendaluz\.mx\./', $C::cart_errors() ) && ! preg_grep( '/tiendaluz\.com/', $C::cart_errors() ), 'F25 punto único por extensión: otro registrador para .mx se engancha con un filtro y se mide aparte' );
$GLOBALS['hooks']['digitalisimo_hosting_registrar_balance'] = array();
WC()->cart = new Digi_Test_Cart();

// ── F26. Topes: por hora y por visitante, y la mitad del cupo del sitio sin nonce ─────────────────────────────
$GLOBALS['now_offset'] = 20000;
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
add_filter( 'digitalisimo_hosting_finder_rate', function () { return 1000; } );
$codes = array();
for ( $i = 0; $i < $F::RATE_HOUR + 1; $i++ ) { $x = $F::rest( new Digi_Test_Request( array( 'q' => 'porhora' ) ) ); $codes[] = is_wp_error( $x ) ? $x->code : 'ok'; }
check( $F::RATE_HOUR === count( array_keys( $codes, 'ok', true ) ) && 'digitalisimo_finder_rate' === end( $codes ), 'F26 un visitante hace como mucho ' . $F::RATE_HOUR . ' búsquedas por hora aunque no pase del tope por minuto' );
$GLOBALS['hooks']['digitalisimo_hosting_finder_rate'] = array();
new_ip();
$GLOBALS['now_offset'] = 40000;
add_filter( 'digitalisimo_hosting_finder_global', function () { return array( 'minute' => 4, 'hour' => 400 ); } );
$res = array();
foreach ( array( 'sinnonce1', 'sinnonce2', 'sinnonce3' ) as $i => $q ) { new_ip(); $x = $F::rest( new Digi_Test_Request( array( 'q' => $q ), array( 'x-digitalisimo-finder' => '1' ) ) ); $res[] = is_wp_error( $x ) ? $x->code : 'ok'; }
new_ip();
$withnonce = $F::rest( new Digi_Test_Request( array( 'q' => 'connonce1', 'nonce' => 'n:' . $F::NONCE ) ) );
check( array( 'ok', 'ok', 'digitalisimo_finder_busy' ) === $res && ! is_wp_error( $withnonce ), 'F26 sin nonce sólo se usa la mitad del cupo del sitio; quien trae el nonce sigue buscando · ' . json_encode( $res ) );
$GLOBALS['hooks']['digitalisimo_hosting_finder_global'] = array();
check( $F::same_origin( 'https://www.tienda.test' ) && $F::same_origin( 'https://tienda.test' ) && ! $F::same_origin( 'https://tienda.test.otro.com' ) && ! $F::same_origin( 'https://wwwtienda.test' ), 'F26 el mismo sitio con y sin www. cuenta como el mismo origen; otro no' );

// ── F27. Carrito de bloques con artículos: sin buscador en el bloque «vacío» ──────────────────────────────────
WC()->cart = new Digi_Test_Cart();
WC()->cart->add( 14693 );
$blk = '<div class="wp-block-woocommerce-empty-cart-block">Vacío</div>';
check( $blk === $F::empty_cart_block( $blk, array( 'blockName' => 'woocommerce/empty-cart-block' ) ), 'F27 con artículos en el carrito, el bloque «carrito vacío» (que WooCommerce pinta igual) no lleva el buscador' );
WC()->cart = new Digi_Test_Cart();
check( false !== strpos( $F::empty_cart_block( $blk, array( 'blockName' => 'woocommerce/empty-cart-block' ) ), 'data-digitalisimo-finder' ), 'F27 vacío de verdad, sí' );

echo "Hosting, buscador de dominios: {$GLOBALS['checks']} comprobaciones correctas.\n";
}
