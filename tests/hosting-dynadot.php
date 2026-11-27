<?php
// Dynadot como segundo registrador de DIGITALÍSIMO Hosting (pedido del propietario del 2026-11-27), sin WordPress y sin
// salir a la red: Namecheap, Webuzo, Banxico y Dynadot simulados por pre_http_request (Dynadot con las respuestas de su
// documentación, tests/fixtures/hosting-dynadot.php).
//   D1  traducción de las respuestas de Dynadot: libre, ocupado, premium, errores, clave o IP rechazadas, sin red,
//       cupo, saldo, precios por extensión y «¿está en nuestra cuenta?»; una cuenta Regular busca de uno en uno;
//   D2  registrador predeterminado y por extensión: el buscador, el carrito y la consulta pública van con el de cada
//       extensión (Namecheap en lote, Dynadot de uno en uno); una extensión sin cuenta sale «sin consultar»;
//   D3  aprovisionamiento con Dynadot: contacto del cliente, registro, nameservers; el servicio recuerda su registrador y
//       la renovación va con él aunque los ajustes cambien;
//   D4  Dynadot falla o no hay saldo: aviso al dueño y pedido en espera, nunca un cobro sin dominio;
//   D5  la clave de Dynadot nunca sale (REST, MCP, notas, correos, servicios, pantalla, «Probar conexión»);
//   D6  «Probar conexión» consulta el saldo (nonce y permiso);
//   D7  la herramienta MCP de ajustes lee y cambia el registrador, nunca la clave;
//   D8  Namecheap sin cambios con los ajustes de siempre (ni una llamada a Dynadot);
//   D9  precios diarios con el costo del registrador de cada extensión.
// Uso: php tests/hosting-dynadot.php
namespace Automattic\WooCommerce\StoreApi\Exceptions {
	class RouteException extends \Exception {
		public function __construct( $code, $message, $status = 400 ) { parent::__construct( $message ); }
	}
}

namespace {
require __DIR__ . '/fixtures/hosting-wp.php';
require __DIR__ . '/fixtures/hosting-dynadot.php';
$GLOBALS['real_transients'] = 1; $GLOBALS['transients'] = array();
$H = 'Digitalisimo_Hosting'; $P = 'Digitalisimo_Hosting_Provisioning'; $R = 'Digitalisimo_Hosting_Registrars'; $F = 'Digitalisimo_Hosting_Finder'; $C = 'Digitalisimo_Hosting_Checkout';
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k = '', $s = false ) { return ''; } }
function get_woocommerce_currency_symbol() { return '&#36;'; }
function wc_get_price_decimals() { return 2; }
function wc_get_price_to_display( $p, $args = array() ) { return (float) ( $args['price'] ?? $p->get_price() ); }
function home_url( $p = '' ) { return 'https://tienda.test' . $p; }
function digi_notes( $order ) { return implode( "\n", $order->notes ); }
function digi_mails_to( $to ) { return array_values( array_filter( $GLOBALS['mails'], function ( $m ) use ( $to ) { return $m['to'] === $to; } ) ); }
function nc_cmds() { return array_column( $GLOBALS['nc']['calls'], 'cmd' ); }
function store( array $extra = array() ) {
	digi_servers_reset(); dd_reset();
	$GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['transients'] = array();
	delete_option( Digitalisimo_Hosting_Webuzo::BLOCK ); delete_option( Digitalisimo_Hosting_Provisioning::WAITING );
	digi_store_settings( $extra + array( 'dynadot_api_key' => DD_KEY, 'registrar_by_tld' => '.mx|dynadot', 'domain_products' => ".com|20\n.mx|21" ) );
	Digitalisimo_Hosting_Products::flush();
}
function buy( $domain, $product = 21, $hosting = 14693 ) {
	$items = array();
	if ( $hosting ) $items[] = new Digi_Test_Item( $hosting, 'Web Hosting' );
	$items[] = new Digi_Test_Item( $product, 'Dominio', 1, array( 'Dominio' => $domain ) );
	$o = digi_order( $items );
	$o->update_status( 'processing' );
	digi_run_queue();
	return $o;
}
/** Todo lo que el sitio guarda o muestra (opciones salvo la de ajustes, meta de servicios, notas, correos). */
function everything_but_settings() {
	$opts = $GLOBALS['options']; unset( $opts['digitalisimo_hosting_options'] );
	$notes = array(); foreach ( $GLOBALS['orders'] as $o ) $notes[] = $o->notes; $notes[] = array_map( function ( $o ) { return $o->meta; }, $GLOBALS['orders'] );
	return serialize( array( $opts, $GLOBALS['postmeta'], $notes, $GLOBALS['mails'], $GLOBALS['transients'] ) );
}
store();
$dyn = Digitalisimo_Hosting_Registrars::make( 'dynadot' );

// ── D0. Respuestas REALES de Dynadot (prueba de sólo lectura del 2026-11-27, cuenta del dueño, sin la clave) ──────
function replay( $file ) { $GLOBALS['dd']['replay'] = $file; }
replay( 'balance' );
$b = $dyn->balance();
check( 'ok' === $b['kind'] && 0.0 === $b['value'], 'D0 saldo cero: Dynadot contesta «There is no balance in your account» (ResponseCode "-1") y se toma como saldo 0, no como error' );
replay( 'search-libre' );
$r = $dyn->check( 'digitalisimo-prueba-8812.com' );
check( 'ok' === $r['kind'] && true === $r['available'] && false === $r['premium'] && 11.62 === $r['register_usd'] && 11.62 === $r['renew_usd'], 'D0 .com libre: «Registration Price: 11.62 … Renewal price: 11.62 … Domain is not a Premium Domain» (no premium)' );
replay( 'search-mx' );
$r = $dyn->check( 'digitalisimo-prueba-8812.mx' );
check( 'ok' === $r['kind'] && true === $r['available'] && false === $r['premium'] && 23.54 === $r['register_usd'] && 42.52 === $r['renew_usd'], 'D0 .mx: registro 23.54 y renovación 42.52 por separado' );
replay( 'search-lat' );
$r = $dyn->check( 'digitalisimo-prueba-8812.lat' );
check( 1.54 === $r['register_usd'] && 26.97 === $r['renew_usd'] && false === $r['premium'], 'D0 .lat: primer año de promoción 1.54 y renovación 26.97' );
replay( 'search-com.mx' );
$r = $dyn->check( 'digitalisimo-prueba-8812.com.mx' );
check( 11.77 === $r['register_usd'] && 23.91 === $r['renew_usd'], 'D0 .com.mx: 11.77 y 23.91' );
replay( 'search-net' );
$r = $dyn->check( 'digitalisimo-prueba-8812.net' );
check( 7.49 === $r['register_usd'] && 12.52 === $r['renew_usd'], 'D0 .net: 7.49 y 12.52' );
replay( 'search-ocupado' );
$r = $dyn->check( 'google.com' );
check( 'ok' === $r['kind'] && false === $r['available'] && false === $r['premium'] && ! isset( $r['register_usd'] ), 'D0 ocupado (google.com): Available «no» sin Price' );
replay( 'search-lote' );
$r = $dyn->check( 'google.com' );
check( 'ok' !== $r['kind'] && false !== strpos( $r['message'], 'too many domains entered' ), 'D0 el rechazo del lote (cuenta Regular) es un error, no un resultado' );
replay( 'tld_price' );
delete_transient( 'digitalisimo_hosting_dd_prices' );
$p = array();
foreach ( array( 'com', 'net', 'mx', 'com.mx', 'lat' ) as $tld ) foreach ( array( 'REGISTER', 'RENEW', 'TRANSFER' ) as $a ) $p[ $tld ][ $a ] = $dyn->price( $tld, $a )['value'] ?? null;
check( array( 'REGISTER' => 23.54, 'RENEW' => 42.52, 'TRANSFER' => 42.52 ) === $p['mx'] && array( 'REGISTER' => 11.62, 'RENEW' => 11.62, 'TRANSFER' => 11.62 ) === $p['com'] && array( 'REGISTER' => 11.77, 'RENEW' => 23.91, 'TRANSFER' => 23.91 ) === $p['com.mx'] && 26.97 === $p['lat']['RENEW'], 'D0 tld_price real (ResponseCode entero 0, Tld «.mx» con su punto, PriceLevel «Regular Account»): registro, renovación y transferencia' );
$t = $dyn->tlds();
check( 'ok' === $t['kind'] && ! empty( $t['tlds']['mx']['transfer'] ) && ! empty( $t['tlds']['com.mx']['transfer'] ), 'D0 las extensiones con precio de transferencia se pueden transferir' );
$GLOBALS['dd']['replay'] = '';
store();
$dyn = Digitalisimo_Hosting_Registrars::make( 'dynadot' );

// ── D1. Traducción de las respuestas ─────────────────────────────────────────────────────────────────────────
check( $dyn instanceof Digitalisimo_Hosting_Registrar && Digitalisimo_Hosting_Registrars::make( 'namecheap' ) instanceof Digitalisimo_Hosting_Registrar, 'D1 Namecheap y Dynadot implementan la misma interfaz de registrador' );
check( 'dynadot' === $dyn->id() && 'Dynadot' === $dyn->label() && $dyn->configured(), 'D1 Dynadot con su clave guardada está configurado' );
$r = $dyn->check( 'libre.mx' );
$c = dd_calls( 'search' )[0];
check( 'ok' === $r['kind'] && true === $r['available'] && false === $r['premium'] && 20.0 === $r['register_usd'] && 25.0 === $r['renew_usd'], 'D1 libre: Available «yes», «not a Premium Domain», registro y renovación' );
check( array( 'domain0' => 'libre.mx', 'show_price' => '1', 'currency' => 'USD' ) === $c['params'] && DD_KEY === $c['key'], 'D1 search con domain0, show_price=1 y currency=USD (api3.json)' );
$GLOBALS['dd']['taken'][] = 'ocupado.mx';
$r = $dyn->check( 'ocupado.mx' );
check( 'ok' === $r['kind'] && false === $r['available'], 'D1 ocupado: Available «no»' );
$GLOBALS['dd']['premium'][] = 'lujo.mx';
$r = $dyn->check( 'lujo.mx' );
check( 'ok' === $r['kind'] && true === $r['available'] && true === $r['premium'], 'D1 premium: «Domain is a Premium Domain» (sin «not»)' );
$r = $dyn->check( 'algo.xyz' );
check( 'api' === $r['kind'] && 0 === strpos( $r['message'], 'Dynadot: tld is not supported' ), 'D1 un error de Dynadot (ResponseCode -1) es «api» con su texto' );
$GLOBALS['dd']['mode'] = 'auth';
$r = $dyn->check( 'libre.mx' );
check( 'auth' === $r['kind'], 'D1 clave rechazada («invalid key») es «auth»' );
$GLOBALS['dd']['mode'] = ''; $GLOBALS['dd']['http'] = 403;
check( 'auth' === $dyn->check( 'libre.mx' )['kind'], 'D1 HTTP 403 (IP fuera de la lista permitida) es «auth»' );
$GLOBALS['dd']['http'] = 429;
check( 'transient' === $dyn->check( 'libre.mx' )['kind'], 'D1 HTTP 429 (cupo de la cuenta) se reintenta' );
$GLOBALS['dd']['http'] = 503;
check( 'transient' === $dyn->check( 'libre.mx' )['kind'], 'D1 HTTP 503 se reintenta' );
$GLOBALS['dd']['http'] = 0; $GLOBALS['dd']['mode'] = 'down';
$r = $dyn->check( 'libre.mx' );
check( 'transient' === $r['kind'] && false === strpos( $r['message'], DD_KEY ) && false !== strpos( $r['message'], 'key=••••' ), 'D1 sin red: se reintenta y el texto de cURL (con la URL) no lleva la clave · ' . $r['message'] );
$GLOBALS['dd']['mode'] = '';
$e = Digitalisimo_Hosting_Dynadot::classify( digi_json( array( 'RegisterResponse' => array( 'ResponseCode' => '-1', 'Status' => 'error', 'Error' => 'Insufficient account balance' ) ) ) );
check( 'funds' === $e['kind'], 'D1 «saldo insuficiente» al registrar es «funds»' );
check( 'transient' === Digitalisimo_Hosting_Dynadot::classify( digi_json( array( 'SearchResponse' => array( 'ResponseCode' => '-1', 'Status' => 'error', 'Error' => 'too many threads, please try again' ) ) ) )['kind'], 'D1 «demasiados hilos» (cuenta Regular de 1 hilo) se reintenta' );
check( 'api' === Digitalisimo_Hosting_Dynadot::classify( array( 'response' => array( 'code' => 200 ), 'body' => 'no json', 'headers' => array() ) )['kind'], 'D1 una respuesta que no es JSON es «api»' );
check( 'ok' === Digitalisimo_Hosting_Dynadot::classify( digi_json( array( 'SetNsResponse' => array( 'ResponseCode' => 0, 'Status' => 'success' ) ) ) )['kind'], 'D1 ResponseCode 0 numérico (como en varios ejemplos de la documentación) es «ok»' );
$b = $dyn->balance();
check( 'ok' === $b['kind'] && 300.0 === $b['value'], 'D1 saldo: BalanceList en USD (300.00)' );
$GLOBALS['dd']['balance_currency'] = 'EUR';
check( 'api' === $dyn->balance()['kind'], 'D1 sin saldo en USD no se adivina: error' );
unset( $GLOBALS['dd']['balance_currency'] );
$n = dd_count( 'tld_price' );
$p = $dyn->price( 'mx', 'REGISTER' ); $q = $dyn->price( '.mx', 'RENEW' ); $t = $dyn->price( 'mx', 'TRANSFER' );
check( 20.0 === $p['value'] && 25.0 === $q['value'] && 25.0 === $t['value'] && $n + 1 === dd_count( 'tld_price' ), 'D1 tld_price en USD: registro, renovación y transferencia, una sola consulta (se guarda unas horas)' );
check( 'api' === $dyn->price( 'org', 'REGISTER' )['kind'], 'D1 una extensión que Dynadot no lista: error, sin precio inventado' );
$GLOBALS['dd']['domains']['mio.mx'] = gmmktime( 0, 0, 0, 3, 1, 2027 );
$i = $dyn->info( 'mio.mx' );
check( 'ok' === $i['kind'] && true === $i['ours'] && gmmktime( 0, 0, 0, 3, 1, 2027 ) === $i['expires'], 'D1 domain_info: está en la cuenta, con su vencimiento (milisegundos → segundos)' );
$i = $dyn->info( 'ajeno.mx' );
check( 'ok' === $i['kind'] && false === $i['ours'], 'D1 domain_info de un dominio ajeno («domain not found»): no es nuestro' );
$GLOBALS['dd']['info_error'] = 'some new message';
check( 'ok' === $dyn->info( 'nuevo.mx' )['kind'] && false === $dyn->info( 'nuevo.mx' )['ours'], 'D1 un error desconocido de domain_info con la búsqueda «libre»: no es nuestro' );
check( 'api' === $dyn->info( 'ocupado.mx' )['kind'], 'D1 un error desconocido de domain_info de un ocupado: se detiene, nunca se adivina' );
$GLOBALS['dd']['info_error'] = 'domain not found';
$GLOBALS['dd']['calls'] = array();
$m = $dyn->check_many( array( 'uno.mx', 'dos.mx', 'tres.xyz', 'ocupado.mx' ) );
check( 4 === dd_count( 'search' ) && array( 'domain0' ) === array_values( array_filter( array_keys( dd_calls( 'search' )[1]['params'] ), function ( $k ) { return 0 === strpos( $k, 'domain' ); } ) ), 'D1 check_many: una cuenta Regular busca un dominio por comando' );
check( 'ok' === $m['kind'] && array( 'uno.mx', 'dos.mx', 'ocupado.mx' ) === array_keys( $m['results'] ) && false === $m['results']['ocupado.mx']['available'], 'D1 check_many: la extensión que no atiende queda fuera; las demás salen' );
add_filter( 'digitalisimo_hosting_dynadot_batch', function () { return 100; } );
$GLOBALS['dd']['mode'] = 'multi_ok'; $GLOBALS['dd']['calls'] = array();
$m = $dyn->check_many( array( 'uno.mx', 'dos.mx' ) );
check( 1 === dd_count( 'search' ) && 2 === count( $m['results'] ), 'D1 con el filtro de lote (cuenta Bulk), varios dominios en una búsqueda' );
$GLOBALS['dd']['mode'] = ''; $GLOBALS['dd']['calls'] = array();
$m = $dyn->check_many( array( 'uno.mx', 'dos.mx', 'ocupado.mx' ) );
check( 'ok' === $m['kind'] && array( 'uno.mx', 'dos.mx', 'ocupado.mx' ) === array_keys( $m['results'] ) && 4 === dd_count( 'search' ) && isset( dd_calls( 'search' )[0]['params']['domain1'] ) && ! isset( dd_calls( 'search' )[1]['params']['domain1'] ), 'D1 filtro de lote subido en una cuenta Regular: el rechazo («too many domains entered») hace seguir de uno en uno, sin fallar' );
$GLOBALS['hooks']['digitalisimo_hosting_dynadot_batch'] = array(); $GLOBALS['dd']['mode'] = '';
$GLOBALS['dd']['mode'] = 'down';
$m = $dyn->check_many( array( 'uno.mx', 'dos.mx' ) );
check( 'transient' === $m['kind'] && array() === $m['results'], 'D1 check_many sin red: corta y lo dice' );
$GLOBALS['dd']['mode'] = '';

// ── D2. Registrador por extensión ──────────────────────────────────────────────────────────────────────────────
store( array( 'registrar_by_tld' => '' ) );
check( 'namecheap' === $R::default_id() && 'namecheap' === $R::id_for_ext( '.mx' ) && 'namecheap' === $R::id_for_domain( 'algo.com.mx' ), 'D2 de fábrica todo va con Namecheap' );
store( array( 'registrar_by_tld' => ".MX | dynadot\n.com.mx|dynadot\nbasura\n.net|godaddy" ) );
check( 'dynadot' === $R::id_for_ext( 'mx' ) && 'namecheap' === $R::id_for_ext( '.com' ) && 'dynadot' === $R::id_for_domain( 'negocio.com.mx' ) && 'dynadot' === $R::id_for_domain( 'negocio.mx' ), 'D2 .mx y .com.mx con Dynadot; .com con el predeterminado' );
check( array( 'basura', '.net|godaddy' ) === $R::parse( ".mx|dynadot\nbasura\n.net|godaddy" )['errors'] && ".mx|dynadot\n.com.mx|dynadot" === $R::normalize( ".MX | dynadot\n.com.mx|dynadot\nbasura\n.net|godaddy" ), 'D2 las líneas que no se entienden se señalan y no se guardan' );
store( array( 'registrar' => 'dynadot', 'registrar_by_tld' => '.com|namecheap' ) );
check( 'dynadot' === $R::id_for_ext( '.mx' ) && 'namecheap' === $R::id_for_ext( '.com' ), 'D2 predeterminado Dynadot con .com de vuelta en Namecheap' );
store();
$GLOBALS['nc']['taken'] = array( 'ocupado.com' );
$GLOBALS['dd']['taken'][] = 'ocupado.mx';
$res = $F::search( 'minegocio' );
check( 1 === digi_nc_count( 'namecheap.domains.check' ) && 'minegocio.com' === $GLOBALS['nc']['calls'][0]['fields']['DomainList'], 'D2 buscador: Namecheap sólo consulta las extensiones que atiende (.com), en lote' );
check( 1 === dd_count( 'search' ) && 'minegocio.mx' === dd_calls( 'search' )[0]['params']['domain0'], 'D2 buscador: .mx con Dynadot' );
check( 'minegocio.com' === $res['main']['domain'] && 'available' === $res['main']['status'] && 'available' === $res['rows'][0]['status'] && 'minegocio.mx' === $res['rows'][0]['domain'], 'D2 buscador: las dos filas libres, cada una de su registrador' );
check( false === strpos( wp_json_encode( $res ), DD_KEY ) && false === strpos( wp_json_encode( $res ), 'Dynadot' ), 'D2 la respuesta pública no dice nada de la cuenta ni del registrador' );
$res = $F::search( 'ocupado' );
check( 'taken' === $res['rows'][0]['status'] || 'taken' === $res['taken']['status'], 'D2 buscador: ocupado en Dynadot sale ocupado' );
$n = dd_count( 'search' );
check( '' === $C::line_error( 21, array( 'digitalisimo_domain' => 'minegocio.mx' ), false ) && $n === dd_count( 'search' ), 'D2 carrito: agregar lo buscado no vuelve a consultar (misma caché)' );
$err = $C::line_error( 21, array( 'digitalisimo_domain' => 'otro-ocupado.mx' ), false );
check( '' === $err && $n + 1 === dd_count( 'search' ) && 'otro-ocupado.mx' === end( $GLOBALS['dd']['calls'] )['params']['domain0'], 'D2 carrito: un .mx nuevo se comprueba con Dynadot' );
$GLOBALS['dd']['premium'][] = 'caro.mx';
check( false !== strpos( $C::line_error( 21, array( 'digitalisimo_domain' => 'caro.mx' ), false ), 'premium' ), 'D2 carrito: un premium de Dynadot no se vende' );
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
$req = new class { public function get_param( $k ) { return 'consulta.mx'; } };
$out = $H::check_domain( $req );
check( is_array( $out ) && true === $out['available'] && 'consulta.mx' === end( $GLOBALS['dd']['calls'] )['params']['domain0'], 'D2 la consulta pública /check-domain va con el registrador de la extensión' );
store( array( 'dynadot_api_key' => '' ) );
$res = $F::search( 'sinclave' );
check( 0 === dd_count() && 'available' === $res['main']['status'] && 'unknown' === $res['rows'][0]['status'] && true === $res['partial'], 'D2 Dynadot sin clave: .mx sale «sin consultar», .com sigue con Namecheap y ni una llamada a Dynadot' );
store( array( 'api_key' => '', 'dynadot_api_key' => '' ) );
check( is_wp_error( $F::search( 'nadie' ) ), 'D2 sin ninguna cuenta, el buscador no consulta' );

// ── D3. Aprovisionamiento con Dynadot ─────────────────────────────────────────────────────────────────────────
store();
$o = buy( 'mitienda.mx' );
check( 'completed' === $o->get_status(), 'D3 el pedido de hosting + .mx se entrega' );
check( array( 'domain_info', 'tld_price', 'search', 'get_account_balance', 'create_contact', 'register', 'server_list', 'add_ns', 'add_ns', 'set_ns' ) === dd_cmds(), 'D3 Dynadot: ¿ya es nuestro?, precio, libre y no premium, saldo, contacto, registro y nameservers, en ese orden · ' . implode( ',', dd_cmds() ) );
check( ! array_intersect( array( 'namecheap.domains.create', 'namecheap.domains.check', 'namecheap.domains.dns.setCustom' ), nc_cmds() ), 'D3 Namecheap no registra ni consulta un .mx que va con Dynadot' );
$contact = array_values( $GLOBALS['dd']['contacts'] )[0];
check( 'Ana López' === $contact['name'] && 'ana@cliente.test' === $contact['email'] && '52' === $contact['phonecc'] && '3312345678' === $contact['phonenum'] && 'Av. Juárez 100' === $contact['address1'] && 'Guadalajara' === $contact['city'] && 'Jalisco' === $contact['state'] && '44100' === $contact['zip'] && 'MX' === $contact['country'] && ! isset( $contact['organization'] ), 'D3 el contacto del titular con los datos de facturación del cliente (nunca el del dueño de la cuenta)' );
$reg = dd_calls( 'register' )[0]['params'];
check( 'mitienda.mx' === $reg['domain'] && '1' === $reg['duration'] && 'USD' === $reg['currency'] && '1001' === $reg['registrant_contact'] && '1001' === $reg['admin_contact'] && '1001' === $reg['technical_contact'] && '1001' === $reg['billing_contact'], 'D3 register de un año con el contacto del cliente en los cuatro papeles' );
check( array( 'ns1.digitalisimo.website', 'ns2.digitalisimo.website' ) === $GLOBALS['dd']['dns']['mitienda.mx'] && array( 'ns1.digitalisimo.website', 'ns2.digitalisimo.website' ) === array_column( array_column( dd_calls( 'add_ns' ), 'params' ), 'host' ), 'D3 los nameservers se agregan a la cuenta y el dominio apunta a ellos' );
$s = (int) $o->get_meta( '_digitalisimo_service' );
check( 'dynadot' === $P::svc( $s, 'registrar' ) && 'dynadot' === $P::state( $o )['domain']['registrar'], 'D3 el servicio y el pedido recuerdan que el dominio está en Dynadot' );
check( false !== strpos( digi_notes( $o ), 'dominio mitienda.mx registrado en Dynadot a nombre de Ana López.' ), 'D3 la nota dice dónde quedó registrado' );
// Otro pedido: los nameservers ya están en la cuenta, no se agregan otra vez.
$GLOBALS['dd']['calls'] = array();
buy( 'segunda.mx' );
check( 0 === dd_count( 'add_ns' ) && 1 === dd_count( 'set_ns' ), 'D3 los nameservers que ya están en la cuenta no se vuelven a agregar' );
// La renovación va con Dynadot aunque .mx ya vaya con Namecheap.
$o2 = get_option( 'digitalisimo_hosting_options' ); $o2['registrar_by_tld'] = ''; update_option( 'digitalisimo_hosting_options', $o2 );
$GLOBALS['dd']['calls'] = array(); $GLOBALS['nc']['calls'] = array();
$expires = (int) $P::svc( $s, 'expires' );
$P::daily( $expires - 29 * DAY_IN_SECONDS );
$renewal = wc_get_order( (int) $P::svc( $s, 'renewal_order' ) );
$renewal->status = 'pending'; $renewal->update_status( 'processing' );
digi_run_queue();
check( 1 === dd_count( 'renew' ) && 0 === digi_nc_count( 'namecheap.domains.renew' ) && 0 === digi_nc_count( 'namecheap.domains.getInfo' ), 'D3 la renovación va a Dynadot (donde está el dominio) aunque .mx ya vaya con Namecheap' );
$rn = dd_calls( 'renew' )[0]['params'];
check( 'mitienda.mx' === $rn['domain'] && '1' === $rn['duration'] && '1' === $rn['no_renew_if_late_renew_fee_needed'], 'D3 renew de un año, sin recargo por renovación tardía a ciegas' );
check( 'completed' === $renewal->get_status() && false !== strpos( digi_notes( $renewal ), 'renovado en Dynadot un año.' ), 'D3 la renovación queda completada con su nota' );
// Un servicio de antes de esta versión (sin registrador anotado) es de Namecheap.
check( 'namecheap' === $R::of_service( 99999 ), 'D3 un servicio sin registrador anotado (anterior a esta versión) es de Namecheap' );

// ── D4. Dynadot falla o no hay saldo ──────────────────────────────────────────────────────────────────────────
store();
$GLOBALS['dd']['balance'] = 1.0;
$o = buy( 'sinsaldo.mx' );
check( 0 === dd_count( 'register' ) && 0 === dd_count( 'create_contact' ) && 'on-hold' === $o->get_status(), 'D4 saldo insuficiente en Dynadot: no se registra y el pedido queda en espera' );
check( false !== strpos( digi_notes( $o ), 'Saldo insuficiente en Dynadot para registrar sinsaldo.mx: hay 1.00 USD y cuesta 20.00 USD' ) && 1 === count( digi_mails_to( 'admin@tienda.test' ) ) && ! $GLOBALS['queue'], 'D4 nota con saldo y costo, aviso al dueño y sin reintentos solos' );
check( ! digi_mails_to( 'ana@cliente.test' ), 'D4 el cliente no recibe accesos sin su dominio' );
$s = (int) $o->get_meta( '_digitalisimo_service' );
$GLOBALS['dd']['balance'] = 100.0; $GLOBALS['mails'] = array();
check( true === $P::retry( $s ), 'D4 con saldo, «Reintentar» encola' );
digi_run_queue();
check( 'completed' === $o->get_status() && 1 === dd_count( 'register' ) && isset( $GLOBALS['dd']['domains']['sinsaldo.mx'] ), 'D4 el reintento registra una sola vez' );
// Saldo en cero: Dynadot contesta un error («There is no balance in your account»); el freno frena igual.
store(); $GLOBALS['dd']['balance'] = 0.0;
$o = buy( 'cero.mx' );
check( 'on-hold' === $o->get_status() && 0 === dd_count( 'register' ) && false !== strpos( digi_notes( $o ), 'Saldo insuficiente en Dynadot para registrar cero.mx: hay 0.00 USD y cuesta 20.00 USD' ), 'D4 saldo cero (la respuesta real de hoy): no se registra, la nota dice 0.00 USD' );
// Dynadot rechaza el registro por saldo (aunque get_account_balance alcanzaba): en espera, nunca completado.
store( array() ); $GLOBALS['dd']['register_error'] = 'Insufficient account balance';
$o = buy( 'rechazado.mx' );
check( 'on-hold' === $o->get_status() && ! isset( $GLOBALS['dd']['domains']['rechazado.mx'] ) && false !== strpos( digi_notes( $o ), 'Dynadot: Insufficient account balance' ) && 1 === count( digi_mails_to( 'admin@tienda.test' ) ) && ! $GLOBALS['queue'], 'D4 register con error de saldo: en espera, aviso y sin reintentar' );
check( empty( $P::state( $o )['domain']['create_sent'] ), 'D4 un error definitivo borra la marca del intento (un «Reintentar» nunca adopta un dominio ajeno)' );
// Premium.
store(); $GLOBALS['dd']['premium'][] = 'premium.mx';
$o = buy( 'premium.mx' );
check( 'on-hold' === $o->get_status() && 0 === dd_count( 'register' ) && false !== strpos( digi_notes( $o ), 'es premium en Dynadot' ), 'D4 premium en Dynadot: no se registra solo' );
// Clave o IP rechazadas: configuración, sin reintentar y sin tocar Webuzo de más.
store(); $GLOBALS['dd']['mode'] = 'auth';
$o = buy( 'clave.mx' );
check( 'on-hold' === $o->get_status() && 1 === dd_count() && ! $GLOBALS['queue'] && 0 === dd_count( 'register' ), 'D4 clave rechazada: una sola llamada, en espera y sin reintentos' );
// Sin red: reintento con espera, nada se cobra.
store(); $GLOBALS['dd']['mode'] = 'down';
$o = digi_order( array( new Digi_Test_Item( 14693, 'Web Hosting' ), new Digi_Test_Item( 21, 'Dominio', 1, array( 'Dominio' => 'sinred.mx' ) ) ) );
$o->update_status( 'processing' ); digi_run_queue( false );
check( 'completed' !== $o->get_status() && 1 === count( $GLOBALS['queue'] ) && 0 === dd_count( 'register' ), 'D4 sin red: reintento programado, ningún registro' );
// Dynadot sin clave con .mx asignado: en espera con aviso, nunca un cobro sin dominio.
store( array( 'dynadot_api_key' => '' ) );
$o = buy( 'sincuenta.mx' );
check( 'on-hold' === $o->get_status() && 0 === dd_count() && false !== strpos( digi_notes( $o ), 'Faltan los datos de la cuenta de Dynadot' ), 'D4 sin la cuenta de Dynadot: en espera con el motivo' );

// ── D5. La clave nunca sale ───────────────────────────────────────────────────────────────────────────────────
store();
buy( 'secreto.mx' );
$GLOBALS['dd']['mode'] = 'echo';
buy( 'eco.mx' );
$GLOBALS['dd']['mode'] = '';
check( false === strpos( everything_but_settings(), DD_KEY ), 'D5 ni notas, ni correos, ni servicios, ni opciones, ni transitorios guardan la clave (aunque Dynadot la repita en un error)' );
check( false !== strpos( everything_but_settings(), 'key ••••' ), 'D5 el error que repetía la clave quedó enmascarado' );
$g = $H::mcp_settings();
check( false === strpos( wp_json_encode( $g ), DD_KEY ) && ! array_key_exists( 'dynadot_api_key', $g['settings'] ) && true === $g['secrets_set']['dynadot_api_key'] && true === $g['dynadot']['configured'], 'D5 MCP get: sólo dice que la clave de Dynadot está puesta' );
$res = $F::search( 'otra' );
check( false === strpos( wp_json_encode( $res ), DD_KEY ), 'D5 la respuesta del buscador no lleva la clave' );
foreach ( $GLOBALS['http'] as $h ) if ( 0 === strpos( $h['url'], 'https://api.dynadot.com/' ) ) check( 0 === strpos( $h['url'], 'https://api.dynadot.com/api3.json?key=' ) && 0 === ( $h['args']['redirection'] ?? -1 ), 'D5 la clave sólo viaja a api.dynadot.com por HTTPS y sin redirecciones' );
ob_start(); $H::page(); $html = ob_get_clean();
check( false === strpos( $html, DD_KEY ) && preg_match( '#name="hosting\[dynadot_api_key\]" value="' . preg_quote( esc_attr( Digitalisimo_Hosting::MASK ), '#' ) . '"#', $html ), 'D5 la pantalla muestra la clave enmascarada, nunca la clave' );
check( false !== strpos( $html, 'name="hosting[registrar]"' ) && false !== strpos( $html, 'name="hosting[registrar_by_tld]"' ) && false !== strpos( $html, 'Probar conexión con Dynadot' ), 'D5 la pantalla tiene el registrador predeterminado, el de cada extensión y «Probar conexión»' );
// Guardar el formulario con la máscara conserva la clave; «Quitar la clave» la borra.
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'dynadot_api_key' => Digitalisimo_Hosting::MASK, 'registrar' => 'godaddy', 'registrar_by_tld' => ".mx|dynadot\nbasura" ) );
ob_start(); $H::page(); ob_end_clean();
$saved = get_option( 'digitalisimo_hosting_options' );
check( DD_KEY === $saved['dynadot_api_key'] && ! isset( $saved['registrar'] ) && 'namecheap' === $H::settings()['registrar'] && '.mx|dynadot' === $saved['registrar_by_tld'], 'D5 formulario: la máscara conserva la clave; un registrador desconocido y una línea rota no se guardan' );
$_POST = array( '_wpnonce' => 'n:digitalisimo_hosting_save', 'digitalisimo_hosting_save' => 1, 'hosting' => array( 'dynadot_api_key' => '', 'dynadot_api_key_remove' => 1 ) );
ob_start(); $H::page(); ob_end_clean();
check( '' === get_option( 'digitalisimo_hosting_options' )['dynadot_api_key'], 'D5 «Quitar la clave» la borra' );
$_POST = array();

// ── D6. Probar conexión ───────────────────────────────────────────────────────────────────────────────────────
store();
$_POST = $_REQUEST = array( '_wpnonce' => 'n:' . Digitalisimo_Hosting::TEST_ACTION, 'registrar' => 'dynadot' );
try { $H::test_registrar(); } catch ( Digi_Test_Redirect $e ) {}
check( 'get_account_balance' === end( $GLOBALS['dd']['calls'] )['cmd'], 'D6 «Probar conexión» consulta el saldo' );
ob_start(); $H::page(); $html = ob_get_clean();
check( false !== strpos( $html, 'Conexión con Dynadot correcta: saldo disponible 300.00 USD.' ), 'D6 y lo muestra una vez en la pantalla' );
ob_start(); $H::page(); $html = ob_get_clean();
check( false === strpos( $html, 'Conexión con Dynadot correcta' ), 'D6 el resultado no se repite' );
$GLOBALS['dd']['mode'] = 'echo';
$t = $H::test_result( 'dynadot' );
check( 0 === $t['ok'] && false === strpos( $t['text'], DD_KEY ) && 0 === strpos( $t['text'], 'No se pudo conectar con Dynadot: bad request for key ••••' ), 'D6 un error se muestra sin la clave · ' . $t['text'] );
$GLOBALS['dd']['mode'] = 'auth';
check( false !== strpos( $H::test_result( 'dynadot' )['text'], 'que la IP de este servidor esté autorizada en Dynadot' ), 'D6 clave o IP rechazadas: dice qué revisar' );
$GLOBALS['dd']['mode'] = '';
$t = $H::test_result( 'namecheap' );
check( 1 === $t['ok'] && false !== strpos( $t['text'], 'Conexión con Namecheap correcta: saldo disponible 50.00 USD.' ), 'D6 también prueba Namecheap' );
$_POST = $_REQUEST = array( '_wpnonce' => 'otro', 'registrar' => 'dynadot' );
$n = dd_count();
try { $H::test_registrar(); $died = false; } catch ( Digi_Test_Die $e ) { $died = true; }
check( $died && $n === dd_count(), 'D6 sin el nonce no se consulta nada' );
$GLOBALS['caps'] = array();
$_POST = $_REQUEST = array( '_wpnonce' => 'n:' . Digitalisimo_Hosting::TEST_ACTION, 'registrar' => 'dynadot' );
try { $H::test_registrar(); $died = false; } catch ( Digi_Test_Die $e ) { $died = 403 === $e->status; }
check( $died && $n === dd_count(), 'D6 sin manage_options no se consulta nada' );
unset( $GLOBALS['caps'] ); $_POST = $_REQUEST = array();
store( array( 'dynadot_api_key' => '' ) );
check( 0 === $H::test_result( 'dynadot' )['ok'] && 0 === dd_count(), 'D6 sin clave lo dice sin llamar' );

// ── D7. Herramienta MCP de ajustes ────────────────────────────────────────────────────────────────────────────
store();
$g = $H::mcp_settings();
check( 'namecheap' === $g['settings']['registrar'] && '.mx|dynadot' === $g['settings']['registrar_by_tld'] && in_array( 'registrar', $g['writable'], true ) && in_array( 'registrar_by_tld', $g['writable'], true ) && in_array( 'dynadot_api_key', $g['read_only'], true ), 'D7 get: registrador predeterminado y por extensión (escribibles); la clave de Dynadot, de sólo lectura' );
check( 'dynadot' === $g['registrars']['effective']->{'.mx'} && 'namecheap' === $g['registrars']['effective']->{'.com'} && true === $g['registrars']['configured']['dynadot'] && array( 'namecheap', 'dynadot' ) === $g['registrars']['available'], 'D7 get: qué registrador atiende cada extensión que se vende y cuáles tienen cuenta' );
$u = $H::mcp_update( array( 'registrar' => 'dynadot', 'registrar_by_tld' => ".com|namecheap\n.mx|dynadot" ) );
check( ! is_wp_error( $u ) && array( 'registrar', 'registrar_by_tld' ) === $u['changed'] && 'dynadot' === get_option( 'digitalisimo_hosting_options' )['registrar'] && 'namecheap' === $R::id_for_ext( '.com' ), 'D7 update cambia el predeterminado y el de cada extensión' );
$snap = serialize( get_option( 'digitalisimo_hosting_options' ) );
foreach ( array( array( 'registrar' => 'godaddy' ), array( 'registrar' => array( 'dynadot' ) ), array( 'registrar_by_tld' => ".mx|dynadot\n.net|godaddy" ), array( 'registrar_by_tld' => array() ), array( 'dynadot_api_key' => 'ROBADA' ), array( 'registrar' => 'dynadot', 'dynadot_api_key' => 'ROBADA' ) ) as $bad ) {
	$e = $H::mcp_update( $bad );
	check( is_wp_error( $e ) && serialize( get_option( 'digitalisimo_hosting_options' ) ) === $snap, 'D7 no se guarda nada con ' . json_encode( $bad ) . ' · ' . ( is_wp_error( $e ) ? $e->code : 'sin error' ) );
}
check( 'digitalisimo_hosting_read_only' === $H::mcp_update( array( 'dynadot_api_key' => 'ROBADA' ) )->code, 'D7 la clave de Dynadot nunca se escribe por MCP' );
$GLOBALS['caps'] = array();
check( is_wp_error( $H::mcp_update( array( 'registrar' => 'namecheap' ) ) ) && serialize( get_option( 'digitalisimo_hosting_options' ) ) === $snap, 'D7 sin manage_options no se cambia el registrador' );
unset( $GLOBALS['caps'] );

// ── D8. Namecheap sin cambios ─────────────────────────────────────────────────────────────────────────────────
digi_servers_reset(); dd_reset(); $GLOBALS['mails'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['transients'] = array();
digi_store_settings();
Digitalisimo_Hosting_Products::flush();
$o = buy( 'siempre.com', 20 );
check( 'completed' === $o->get_status() && 0 === dd_count() && array( 'namecheap.domains.getInfo', 'namecheap.users.getPricing', 'namecheap.domains.check', 'namecheap.users.getBalances', 'namecheap.domains.create', 'namecheap.domains.dns.setCustom' ) === nc_cmds(), 'D8 con los ajustes de siempre: la misma secuencia de Namecheap y ni una llamada a Dynadot' );
check( false !== strpos( digi_notes( $o ), 'dominio siempre.com registrado en Namecheap a nombre de Ana López (ID ' ) && 'namecheap' === $P::svc( (int) $o->get_meta( '_digitalisimo_service' ), 'registrar' ), 'D8 la nota de Namecheap con su ID y cargo, como antes; el servicio queda en Namecheap' );
$o = buy( 'tambien.mx', 21 );
check( 'completed' === $o->get_status() && 0 === dd_count(), 'D8 sin «Registrador por extensión», .mx también va con Namecheap' );

// ── D9. Precios diarios por registrador ───────────────────────────────────────────────────────────────────────
store( array( 'pricing_enabled' => 1 ) );
$e = Digitalisimo_Hosting_Pricing::run();
$rows = array(); foreach ( $e['rows'] as $row ) $rows[ $row['ext'] ] = $row;
check( 'ok' === $e['status'] && 'dynadot' === $rows['.mx']['registrar'] && 20.0 === $rows['.mx']['register_usd'] && 25.0 === $rows['.mx']['renew_usd'] && 'namecheap' === $rows['.com']['registrar'] && abs( 11.46 - $rows['.com']['register_usd'] ) < 0.001, 'D9 el precio de .mx sale del costo de Dynadot y el de .com del de Namecheap' );
store( array( 'pricing_enabled' => 1, 'dynadot_api_key' => '' ) );
$e = Digitalisimo_Hosting_Pricing::run();
check( 'error' === $e['status'] && 'Faltan los datos de la cuenta de Dynadot.' === $e['message'], 'D9 sin la cuenta de Dynadot no se cambia ningún precio y se avisa' );

// ── D10. Entorno de pruebas de Dynadot ────────────────────────────────────────────────────────────────────────
store();
check( 0 === strpos( Digitalisimo_Hosting_Dynadot::endpoint(), 'https://api.dynadot.com/' ), 'D10 sin la constante, la API real' );
define( 'DIGITALISIMO_DYNADOT_SANDBOX', true );
Digitalisimo_Hosting_Registrars::make( 'dynadot' )->check( 'prueba.mx' );
check( end( $GLOBALS['dd']['calls'] )['sandbox'], 'D10 con DIGITALISIMO_DYNADOT_SANDBOX todo va a api-sandbox.dynadot.com (para una prueba real controlada con la «Sandbox Key»)' );

echo "hosting-dynadot: {$GLOBALS['checks']} comprobaciones, todo bien\n";
}
