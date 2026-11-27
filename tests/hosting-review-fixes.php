<?php
// Revisión crítica de hosting/dynadot (2026-11-27): cada punto con su comprobación, con Webuzo, Namecheap y Dynadot
// simulados (y las respuestas reales de Dynadot de tests/fixtures/dynadot/).
//   B1. Pago manual de un servicio que es sólo el dominio: por años (12, 24, 36), renovado en el registrador esos años
//       por omisión (también por MCP); el vencimiento sólo se mueve con la confirmación del registrador o con «ya lo
//       renové en el registrador»; si no, el dominio queda pendiente con aviso (nunca en silencio).
//   I1. Hosting mensual + dominio: en el mes 12 el dominio no se cobra dos veces (pedido sólo del dominio vivo).
//   I2. Varios dominios en un pedido: cada uno con su registrador y su servicio; el plan en el dominio con el que se
//       agregó (o el primero, anotado); uno que falla no detiene a los demás.
//   I3. Dynadot con respuestas reales: «could not find domain in your account» = no es nuestro; un premium real; la red
//       de seguridad del precio; un error por nombre; check_owner pregunta primero al registrador de la extensión; sin
//       vencimiento legible no se renueva otra vez.
//   I4. Pago manual: la clave queda «en curso» antes de extender (un corte a media tarea no extiende dos veces).
//   I5. Un nombre que falla no aparta la extensión entera (sólo un error de extensión no atendida; desde la segunda
//       revisión, varios nombres fallidos seguidos tampoco).
//   Menores: aviso de periodos que quedaron «Anual» tras migrar; variación en «Igual que el producto»; memos por sitio.
// Uso: php tests/hosting-review-fixes.php
namespace Automattic\WooCommerce\StoreApi\Exceptions { class RouteException extends \Exception { public function __construct( $code, $message, $status = 400 ) { parent::__construct( $message ); } } }
namespace {
require __DIR__ . '/fixtures/hosting-wp.php';
require __DIR__ . '/fixtures/hosting-dynadot.php';
$P = 'Digitalisimo_Hosting_Provisioning'; $PR = 'Digitalisimo_Hosting_Products'; $F = 'Digitalisimo_Hosting_Finder'; $C = 'Digitalisimo_Hosting_Checkout';
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k = '', $s = false ) { return ''; } }
function home_url( $p = '' ) { return 'https://tienda.test' . $p; }
function near( $a, $b ) { return abs( (int) $a - (int) $b ) <= 120; }
function ymd( $t ) { return gmdate( 'Y-m-d', (int) $t ); }
function line_ids( $order ) { return array_map( function ( $i ) { return (int) ( $i->get_variation_id() ?: $i->get_product_id() ); }, $order->get_items() ); }
function store( array $extra = array() ) {
	digi_servers_reset(); dd_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['transients'] = array();
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'grace_days' => 7, 'dynadot_api_key' => DD_KEY ) );
	$GLOBALS['nc']['balance'] = 5000;
	Digitalisimo_Hosting_Products::flush();
}
function paid( array $items, $email ) { $o = digi_order( $items, array( 'email' => $email ) ); $o->update_status( 'processing' ); digi_run_queue( true, 200 ); return $o; }
function alerts( $subject ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $subject ) { return false !== strpos( $m['subject'], $subject ); } ) ); }

// ── B1. Pago manual de un servicio que es sólo el dominio ─────────────────────────────────────────────────────
store();
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'solodominio.com' ) ) ), 'ana@solodominio.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' );
$reg = $GLOBALS['nc']['domains']['solodominio.com'];
$e = $P::manual_payment( $s, array( 'months' => 6, 'method' => 'cash' ) );
check( is_wp_error( $e ) && false !== strpos( $e->message, '12, 24 o 36' ), 'B1 sólo el dominio: un periodo que no es de años se rechaza' );
$r = $P::manual_payment( $s, array( 'months' => 36, 'method' => 'cash', 'renew_domain' => true, 'key' => 'b1a' ) );
check( ! is_wp_error( $r ) && 'renewed' === $r['domain_renewal'] && near( $GLOBALS['nc']['domains']['solodominio.com'], strtotime( '+3 year', $reg ) ) && $GLOBALS['nc']['domains']['solodominio.com'] === (int) $P::svc( $s, 'expires' ) && $P::domain_due( $s ) === (int) $P::svc( $s, 'expires' ), 'B1 36 meses: se renuevan 3 años en el registrador y el servicio vence lo mismo que el registrador · ' . ymd( $GLOBALS['nc']['domains']['solodominio.com'] ) . ' / ' . ymd( $P::svc( $s, 'expires' ) ) );
// Por MCP, sin decir nada del dominio: se renueva (es sólo el dominio).
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'pormcp.com' ) ) ), 'ana@pormcp.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $reg = $GLOBALS['nc']['domains']['pormcp.com'];
$r = $P::manual_payment( $s, array( 'method' => 'transfer' ) );
check( ! is_wp_error( $r ) && 'renewed' === $r['domain_renewal'] && near( $GLOBALS['nc']['domains']['pormcp.com'], strtotime( '+1 year', $reg ) ) && $GLOBALS['nc']['domains']['pormcp.com'] === (int) $P::svc( $s, 'expires' ), 'B1 sin decir nada del dominio (lo que manda el MCP), un servicio que es sólo el dominio se renueva en el registrador' );
// «Ya lo renové en el registrador» (el administrador lo renovó allá): no se pide, queda anotado y el vencimiento avanza
// al del registrador (la segunda revisión lo comprueba allá: N5).
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'afuera.com' ) ) ), 'ana@afuera.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $exp = (int) $P::svc( $s, 'expires' ); $n = digi_nc_count( 'namecheap.domains.renew' );
$GLOBALS['nc']['domains']['afuera.com'] = strtotime( '+1 year', $GLOBALS['nc']['domains']['afuera.com'] );
$r = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'renewed_elsewhere' => true, 'key' => 'b1c' ) );
check( 'elsewhere' === $r['domain_renewal'] && $n === digi_nc_count( 'namecheap.domains.renew' ) && near( (int) $P::svc( $s, 'expires' ), strtotime( '+1 year', $exp ) ) && $GLOBALS['nc']['domains']['afuera.com'] === (int) $P::svc( $s, 'expires' ) && preg_grep( '/indicó que ya renovó afuera\.com directamente en el registrador/', wc_get_order( $r['order'] )->notes ), 'B1 «ya lo renové en el registrador»: no se pide la renovación, queda anotado y el vencimiento es el del registrador' );
// Sin renovar ni marcar esa opción: desde la segunda revisión (R1) es un error y no se registra nada.
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'pendiente.com' ) ) ), 'ana@pendiente.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $exp = (int) $P::svc( $s, 'expires' ); $GLOBALS['mails'] = array(); $no = count( $GLOBALS['orders'] );
$r = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'renew_domain' => false, 'key' => 'b1d' ) );
check( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'elige «Renovar el dominio con su registrador» o «Ya lo renové yo en el registrador»' ) && $exp === (int) $P::svc( $s, 'expires' ) && $no === count( $GLOBALS['orders'] ) && ! $P::svc( $s, 'domain_pending_order' ), 'B1/R1 sin renovar ni marcar «ya lo renové»: error y nada registrado (ni pedido ni vencimiento)' );
// Sin saldo en el registrador: lo mismo; con saldo, «Reintentar» lo renueva y entonces se mueve el vencimiento.
$o = paid( array( new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'sinsaldo.com' ) ) ), 'ana@sinsaldo.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $exp = (int) $P::svc( $s, 'expires' ); $reg = $GLOBALS['nc']['domains']['sinsaldo.com'];
$GLOBALS['nc']['balance'] = 1;
$r = $P::manual_payment( $s, array( 'months' => 24, 'method' => 'deposit', 'key' => 'b1e' ) );
check( 'failed' === $r['domain_renewal'] && $exp === (int) $P::svc( $s, 'expires' ) && $reg === $GLOBALS['nc']['domains']['sinsaldo.com'], 'B1 sin saldo: el dominio no se renueva y el vencimiento NO se mueve' );
$GLOBALS['nc']['balance'] = 5000;
check( $P::retry( $s ), 'B1 «Reintentar» encola la renovación pendiente' );
digi_run_queue( true, 200 );
check( near( $GLOBALS['nc']['domains']['sinsaldo.com'], strtotime( '+2 year', $reg ) ) && $GLOBALS['nc']['domains']['sinsaldo.com'] === (int) $P::svc( $s, 'expires' ) && ! $P::svc( $s, 'domain_pending_order' ), 'B1 con saldo, «Reintentar» renueva los 2 años y recién entonces vence igual que el registrador' );

// ── I1. Hosting mensual + dominio: el mes 12 no cobra el dominio dos veces ────────────────────────────────────
store();
digi_variable( 70, 'Plan Nube', array( 71 => array( 'Mensual', 150, array( '_digitalisimo_hosting_period' => 1 ) ) ), array( '_digitalisimo_hosting_type' => 'hosting', '_digitalisimo_webuzo_plan' => 'digitalisimo_starter', '_digitalisimo_hosting_period' => 12 ) );
$o = paid( array( new Digi_Test_Item( 71, 'Plan Nube' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'coincide.com' ) ) ), 'ana@coincide.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' );
for ( $i = 1; $i <= 10; $i++ ) { $P::daily( (int) $P::svc( $s, 'expires' ) - 6 * DAY_IN_SECONDS ); wc_get_order( (int) $P::svc( $s, 'renewal_order' ) )->update_status( 'processing' ); digi_run_queue( true, 200 ); }
$exp = (int) $P::svc( $s, 'expires' ); $dd = $P::domain_due( $s );
$P::daily( $exp - 6 * DAY_IN_SECONDS ); $r11 = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
$P::daily( $dd - 29 * DAY_IN_SECONDS ); $dro = wc_get_order( (int) $P::svc( $s, 'domain_renewal_order' ) );
$r11->update_status( 'processing' ); digi_run_queue( true, 200 );
$P::daily( (int) $P::svc( $s, 'expires' ) - 6 * DAY_IN_SECONDS ); $r12 = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
check( $dro && array( 20 ) === line_ids( $dro ) && array( 71 ) === line_ids( $r12 ), 'I1 con el pedido sólo del dominio vivo, la renovación del mes 12 no lleva el dominio · ' . json_encode( line_ids( $r12 ) ) );
$n = digi_nc_count( 'namecheap.domains.renew' ); $was = $GLOBALS['nc']['domains']['coincide.com'];
$r12->update_status( 'processing' ); digi_run_queue( true, 200 ); $dro->update_status( 'processing' ); digi_run_queue( true, 200 );
check( $n + 1 === digi_nc_count( 'namecheap.domains.renew' ) && near( $GLOBALS['nc']['domains']['coincide.com'], strtotime( '+1 year', $was ) ) && $GLOBALS['nc']['domains']['coincide.com'] === $P::domain_due( $s ), 'I1 pagados los dos: el dominio se renueva UNA vez y el servicio vence igual que el registrador' );

// ── I2. Varios dominios en un pedido ──────────────────────────────────────────────────────────────────────────
store();
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'uno.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'uno.mx' ) ) ), 'ana@uno.com' );
$main = (int) $o->get_meta( '_digitalisimo_service' );
$svcs = array(); foreach ( $P::services() as $id ) if ( (int) $P::svc( $id, 'order' ) === $o->get_id() ) $svcs[ $P::svc( $id, 'domain' ) ] = $id;
check( 'completed' === $o->get_status() && isset( $GLOBALS['nc']['domains']['uno.com'], $GLOBALS['nc']['domains']['uno.mx'] ), 'I2 plan + uno.com + uno.mx pagado: los dos dominios registrados y el pedido entregado (nada en espera)' );
check( 'uno.com' === $P::svc( $main, 'domain' ) && 'uno.com' === $GLOBALS['webuzo']['users']['uno']['domain'] && 2 === count( $svcs ) && 'active' === $P::svc( $svcs['uno.mx'], 'status' ) && 21 === (int) $P::svc( $svcs['uno.mx'], 'domain_product' ) && preg_grep( '/se usa el primero del pedido, uno\.com/', $o->notes ), 'I2 sin asociación, el hosting va en el primer dominio (anotado) y uno.mx tiene su propio servicio activo' );
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter', 1, array( '_digitalisimo_for_domain' => 'dos.mx' ) ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'dos.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'dos.mx' ) ) ), 'ana@dos.mx' );
check( 'dos.mx' === $P::svc( (int) $o->get_meta( '_digitalisimo_service' ), 'domain' ) && isset( $GLOBALS['webuzo']['users']['dos'] ) && 'dos.mx' === $GLOBALS['webuzo']['users']['dos']['domain'] && isset( $GLOBALS['nc']['domains']['dos.com'] ), 'I2 con la asociación del buscador, el hosting va en el dominio con el que se agregó (dos.mx)' );
$GLOBALS['nc']['taken'] = array( 'tres.mx' ); $GLOBALS['mails'] = array();
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'tres.com' ) ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'tres.mx' ) ) ), 'ana@tres.com' );
$bad = 0; foreach ( $P::services() as $id ) if ( (int) $P::svc( $id, 'order' ) === $o->get_id() && 'tres.mx' === $P::svc( $id, 'domain' ) ) $bad = $id;
check( 'completed' === $o->get_status() && isset( $GLOBALS['nc']['domains']['tres.com'] ) && $bad && 'on-hold' === $P::svc( $bad, 'status' ) && alerts( 'dominio en espera: tres.mx' ), 'I2 uno falla (ya no está libre): los demás siguen, el pedido se entrega y el fallido queda en espera con aviso' );
// La asociación viaja del buscador al pedido: Store API → carrito → línea del pedido.
$add = $C::store_api_data( array( 'cart_item_data' => array() ), new class() { public function get_param( $k ) { return 'digitalisimo_for_domain' === $k ? 'Mi-Negocio.MX' : null; } public function get_json_params() { return array(); } public function get_body_params() { return array(); } } );
$data = $C::cart_item_data( $add['cart_item_data'], 14693 );
$item = new Digi_Test_Item( 14693, 'Plan' ); $C::order_item( $item, $data, digi_order( array() ) );
check( 'mi-negocio.mx' === ( $data['digitalisimo_for_domain'] ?? '' ) && 'mi-negocio.mx' === $item->get_meta( '_digitalisimo_for_domain' ) && ! isset( $C::cart_item_data( $add['cart_item_data'], 20 )['digitalisimo_for_domain'] ), 'I2 el dominio del plan llega del buscador (Store API) al carrito y a la línea del pedido; un dominio no lo lleva' );
check( false !== strpos( (string) file_get_contents( __DIR__ . '/../digitalisimo-hosting/assets/finder.js' ), 'body.digitalisimo_for_domain = b.dataset.forDomain' ), 'I2 el buscador manda el dominio de la fila al agregar desde «Se compran juntos»' );

// ── I3. Dynadot con respuestas reales ─────────────────────────────────────────────────────────────────────────
store( array( 'registrar_by_tld' => array( '.mx' => 'dynadot', '.lat' => 'dynadot', '.com.mx' => 'dynadot' ) ) );
$dyn = new Digitalisimo_Hosting_Dynadot( array( 'dynadot_api_key' => DD_KEY ) );
$GLOBALS['dd']['replay'] = 'domain_info-ajeno';
$i = $dyn->info( 'github.com', 15 );
check( 'ok' === $i['kind'] && false === $i['ours'] && array( 'domain_info' ) === dd_cmds(), 'I3 domain_info real de un dominio ajeno («could not find domain in your account») = no es nuestro, sin más consultas' );
$GLOBALS['dd']['calls'] = array(); $GLOBALS['dd']['replay'] = 'search-premium-vino.lat';
$c = $dyn->check( 'vino.lat', 15 );
check( 'ok' === $c['kind'] && true === $c['available'] && true === $c['premium'] && 715.0 === $c['register_usd'], 'I3 premium real («Domain is a Premium Domain», 715 USD): premium' );
$GLOBALS['dd']['replay'] = 'search-premium-xq.com.mx';
$m = $dyn->check_many( array( 'xq.com.mx' ) );
check( 'ok' === $m['kind'] && array() === $m['results'] && 'unexpected registry error' === $m['errors']['xq.com.mx'], 'I3 error real por nombre (ResponseCode 0 con «unexpected registry error»): ese nombre queda sin resultado, con su error' );
unset( $GLOBALS['dd']['replay'] );
// Red de seguridad: un precio de registro mayor que el de la lista de su extensión es especial aunque el texto no lo diga.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== $pre || 0 !== strpos( $url, 'https://api.dynadot.com/api3.json' ) ) return $pre;
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	if ( 'search' === $q['command'] && 'caro.lat' === ( $q['domain0'] ?? '' ) ) return dd_response( 'search', array( 'ResponseCode' => '0', 'SearchResults' => array( array( 'DomainName' => 'caro.lat', 'Status' => 'success', 'Available' => 'yes', 'Price' => 'Registration Price: 715.00 in USD and Renewal price: 715.00 in USD' ) ) ) );
	return $pre;
}, 1, 3 );
$GLOBALS['dd']['prices']['lat'] = array( 1.54, 26.97, 26.97 );
$c = $dyn->check( 'caro.lat', 15 );
check( true === $c['premium'], 'I3 red de seguridad: 715 USD contra 1.54 de la lista de .lat = precio especial aunque el texto no diga premium' );
// check_owner: el registrador de la extensión primero; uno que no la atiende y contesta algo raro no frena.
store( array( 'own_domain_auto' => 1 ) );
$GLOBALS['nc']['taken'] = array( 'yaeselmio.com' ); $GLOBALS['dd']['taken'] = array( 'yaeselmio.com' );
$GLOBALS['dd']['info_error'] = 'this domain is registered with another registrar';
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter', 1, array( 'Dominio' => 'yaeselmio.com' ) ) ), 'ana@yaeselmio.com' );
check( 'completed' === $o->get_status() && isset( $GLOBALS['webuzo']['users']['yaeselmio'] ) && preg_grep( '/Dynadot no atiende esa extensión, así que no frena/', $o->notes ), 'I3 «ya tengo dominio»: un texto desconocido de Dynadot (que no atiende .com) no frena la cuenta' );
store( array( 'own_domain_auto' => 1, 'registrar_by_tld' => array( '.com' => 'dynadot' ) ) );
$GLOBALS['nc']['taken'] = array( 'yaeselmio.com' ); $GLOBALS['dd']['taken'] = array( 'yaeselmio.com' );
$GLOBALS['dd']['info_error'] = 'this domain is registered with another registrar';
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter', 1, array( 'Dominio' => 'yaeselmio.com' ) ) ), 'ana@yaeselmio.com' );
check( 'on-hold' === $o->get_status() && ! isset( $GLOBALS['webuzo']['users']['yaeselmio'] ) && 'domain_info' === dd_cmds()[0], 'I3 el mismo texto del registrador que SÍ atiende la extensión sigue frenando (el lado seguro), y se le pregunta primero' );
// Transferencia en el carrito: el texto raro del registrador que no atiende la extensión no la frena.
store();
digi_product( 50, 'Transferencia', 400 ); digi_domain( 50, '*', 'transfer' );
$GLOBALS['nc']['taken'] = array( 'ajeno.com' ); $GLOBALS['dd']['taken'] = array( 'ajeno.com' ); $GLOBALS['dd']['info_error'] = 'this domain is registered with another registrar';
check( '' === $C::line_error( 50, array( 'digitalisimo_domain' => 'ajeno.com', 'digitalisimo_epp_raw' => 'Ab#12345xyz' ), true ), 'I3 transferencia: el texto raro de Dynadot (no atiende .com) no frena el carrito' );
// Renovación con el vencimiento ilegible: se detiene y avisa en vez de renovar otra vez.
store( array( 'registrar_by_tld' => array( '.mx' => 'dynadot' ) ) );
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 21, 'Dominio .mx', 1, array( 'Dominio' => 'sinfecha.mx' ) ) ), 'ana@sinfecha.mx' );
$s = (int) $o->get_meta( '_digitalisimo_service' );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== $pre || 0 !== strpos( $url, 'https://api.dynadot.com/api3.json' ) ) return $pre;
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	if ( 'domain_info' === $q['command'] && 'sinfecha.mx' === ( $q['domain'] ?? '' ) ) { $GLOBALS['dd']['calls'][] = array( 'cmd' => 'domain_info', 'params' => $q ); return dd_response( 'domain_info', array( 'ResponseCode' => 0, 'Status' => 'success', 'DomainInfo' => array( 'Name' => 'sinfecha.mx' ) ) ); }
	return $pre;
}, 1, 3 );
$P::daily( (int) $P::svc( $s, 'expires' ) - 29 * DAY_IN_SECONDS ); $GLOBALS['mails'] = array();
$r = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) ); $r->update_status( 'processing' ); digi_run_queue( true, 200 );
check( 0 === dd_count( 'renew' ) && preg_grep( '/no informó cuándo vence sinfecha\.mx: no se renovó/', $r->notes ) && $GLOBALS['mails'], 'I3 sin vencimiento legible del registrador no se renueva (podría cobrarse dos veces): se detiene y avisa' );

// ── I4. Pago manual: un corte a media tarea no extiende dos veces ─────────────────────────────────────────────
store();
$o = paid( array( new Digi_Test_Item( 14693, 'Web Hosting Starter' ), new Digi_Test_Item( 20, 'Dominio .com', 1, array( 'Dominio' => 'corte.com' ) ) ), 'ana@corte.com' );
$s = (int) $o->get_meta( '_digitalisimo_service' ); $exp = (int) $P::svc( $s, 'expires' );
$GLOBALS['boom'] = 1;
add_action( 'woocommerce_order_status_completed', function () { if ( ! empty( $GLOBALS['boom'] ) ) { $GLOBALS['boom'] = 0; throw new RuntimeException( 'falla de otro plugin' ); } }, 1 );
try { $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'key' => 'mismo' ) ); } catch ( RuntimeException $e ) {}
$r = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'key' => 'mismo' ) );
check( true === $r['duplicate'] && near( (int) $P::svc( $s, 'expires' ), strtotime( '+1 year', $exp ) ) && false === get_option( 'digitalisimo_hosting_manual_lock_' . $s ), 'I4 un error de otro plugin a media tarea y el mismo formulario otra vez: se extiende UNA vez y el candado se suelta' );
$keys = $P::svc( $s, 'manual_keys' ); $keys['k:enmedio'] = array( 't' => time(), 'state' => 'running' ); $P::svc_set( $s, array( 'manual_keys' => $keys ) );
$before = (int) $P::svc( $s, 'expires' );
$r = $P::manual_payment( $s, array( 'months' => 12, 'method' => 'cash', 'key' => 'enmedio' ) );
check( true === $r['duplicate'] && false !== strpos( $r['message'], 'quedó a medias' ) && $before === (int) $P::svc( $s, 'expires' ), 'I4 una clave que quedó «en curso» no vuelve a extender (lo dice)' );

// ── I5. Un nombre que falla no aparta la extensión entera ─────────────────────────────────────────────────────
store( array( 'registrar_by_tld' => array( '.mx' => 'dynadot' ) ) );
$GLOBALS['real_transients'] = 1; $GLOBALS['transients'] = array();
$fail_names = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$fail_names ) {
	if ( false !== $pre || 0 !== strpos( $url, 'https://api.dynadot.com/api3.json' ) ) return $pre;
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	if ( 'search' === $q['command'] && in_array( $q['domain0'] ?? '', $GLOBALS['fail_names'] ?? array(), true ) ) return dd_response( 'search', array( 'ResponseCode' => '0', 'SearchResults' => array( array( 'DomainName' => $q['domain0'], 'Status' => 'error', 'Error' => 'unexpected registry error' ) ) ) );
	return $pre;
}, 1, 3 );
$GLOBALS['fail_names'] = array( 'ab.mx' );
$st = function ( $r ) { $o = array(); foreach ( array_merge( array( $r['main'] ), $r['rows'] ) as $row ) $o[ $row['domain'] ] = $row['status']; return $o; };
$r = $st( $F::search( 'ab' ) );
check( 'unknown' === $r['ab.mx'] && 'available' === $r['ab.com'] && ! $F::bad_exts(), 'I5 un nombre que Dynadot no pudo consultar sale «no se pudo consultar» y la extensión sigue' );
check( 'available' === $st( $F::search( 'otronegocio' ) )['otronegocio.mx'], 'I5 el siguiente visitante sí ve el .mx' );
$GLOBALS['fail_names'] = array( 'uno1.mx', 'dos2.mx', 'tres3.mx' );
foreach ( array( 'uno1', 'dos2', 'tres3' ) as $q ) $F::search( $q );
check( ! isset( $F::bad_exts()['.mx'] ), 'I5 tres nombres distintos con error (como los de dos letras en .com.mx) NO apartan la extensión (segunda revisión)' );
$GLOBALS['transients'] = array();
$GLOBALS['dd']['unsupported'] = array( '.mx' ); $GLOBALS['fail_names'] = array();
$F::search( 'otro' );
check( isset( $F::bad_exts()['.mx'] ), 'I5 un error de extensión no atendida («tld is not supported») la aparta de inmediato' );
// Namecheap: igual (un nombre rechazado en el respaldo uno por uno no aparta la extensión).
store();
$GLOBALS['real_transients'] = 1; $GLOBALS['transients'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== $pre || 0 !== strpos( $url, 'https://api.namecheap.com/' ) ) return $pre;
	$b = (array) ( $args['body'] ?? array() );
	if ( 'namecheap.domains.check' === ( $b['Command'] ?? '' ) && false !== strpos( (string) $b['DomainList'], 'raro.mx' ) ) return digi_xml( '<Error Number="2011166">Domain name raro.mx is invalid</Error>', false );
	return $pre;
}, 1, 3 );
$r = $st( $F::search( 'raro' ) );
check( 'unknown' === $r['raro.mx'] && 'available' === $r['raro.com'] && ! $F::bad_exts(), 'I5 Namecheap: un nombre rechazado en el lote sale sin consultar y la extensión sigue' );
$GLOBALS['real_transients'] = 0; $GLOBALS['transients'] = array();

// ── Menores ───────────────────────────────────────────────────────────────────────────────────────────────────
store();
// Aviso de periodos tras la migración: hasta que el dueño guarda cada plan.
foreach ( array( 14693, 14696 ) as $id ) { delete_post_meta( $id, '_digitalisimo_hosting_type' ); delete_post_meta( $id, '_digitalisimo_hosting_period' ); }
delete_option( 'digitalisimo_hosting_products_migrated' ); $PR::flush(); $PR::migrate();
ob_start(); $PR::hosting_table(); $t = ob_get_clean();
check( array( 14693, 14696 ) === $PR::migrated_periods() && false !== strpos( $t, 'Revisa el periodo de cobro de estos planes' ) && false !== strpos( $t, 'post.php?post=14693&amp;action=edit' ), 'Aviso: los planes que la migración dejó «Anual», con el enlace a cada uno' );
Digitalisimo_Hosting_Product_Editor::apply( 14693, array( 'type' => 'hosting', 'plan' => 'digitalisimo_starter', 'period' => '12' ), false );
check( array( 14696 ) === $PR::migrated_periods(), 'Aviso: se quita de cada plan al guardarlo en el editor' );
// Variación: «Igual que el producto» manda el tipo del producto y limpia las marcas sueltas.
digi_variable( 70, 'Plan Nube', array( 71 => array( 'Mensual', 150, array( '_digitalisimo_hosting_period' => 1 ) ), 72 => array( 'Anual', 1500, array( '_digitalisimo_hosting_period' => 12 ) ) ), array( '_digitalisimo_hosting_type' => 'hosting', '_digitalisimo_webuzo_plan' => 'digitalisimo_starter', '_digitalisimo_hosting_period' => 12 ) );
Digitalisimo_Hosting_Product_Editor::apply( 72, array( 'type' => 'domain', 'tld' => '.com', 'action' => 'register', 'plan' => '', 'period' => '12' ), true );
$e = Digitalisimo_Hosting_Product_Editor::apply( 72, array( 'type' => 'inherit', 'tld' => '.com', 'action' => '', 'plan' => '', 'period' => '12' ), true );
check( 'hosting' === $PR::kind( 72 ) && '' === get_post_meta( 72, '_digitalisimo_tld', true ) && array() === $e, 'Variación en «Igual que el producto»: es el hosting del producto y la extensión que quedó elegida se limpia' );
update_post_meta( 71, '_digitalisimo_tld', '.com' );
check( 'hosting' === $PR::kind( 71 ), 'Variación sin tipo propio con una marca suelta: manda el tipo explícito del producto' );
// Memos por sitio: en una red, cada sitio ve sus productos.
$GLOBALS['multisite'] = true;
$m1 = $PR::map();
switch_blog( 2 ); $GLOBALS['postmeta_backup'] = $GLOBALS['postmeta'];
foreach ( array( 20, 21 ) as $id ) update_post_meta( $id, '_digitalisimo_hosting_type', 'none' );
$m2 = $PR::map();
switch_blog( 1 ); $GLOBALS['postmeta'] = $GLOBALS['postmeta_backup']; foreach ( array( 20, 21 ) as $id ) update_post_meta( $id, '_digitalisimo_hosting_type', 'domain' );
check( isset( $m1['.com'] ) && ! isset( $m2['.com'] ), 'Memo por sitio: tras cambiar de sitio no se reusa el catálogo del otro' );
$GLOBALS['multisite'] = false;
$PR::map(); $m = new ReflectionProperty( $PR, 'memo' ); $m->setAccessible( true );
check( is_array( $m->getValue() ) && array_key_exists( 1, $m->getValue() ), 'El memo del catálogo lleva el ID del sitio en la llave' );

echo "Hosting, arreglos de la revisión crítica: {$GLOBALS['checks']} comprobaciones correctas.\n";
}
