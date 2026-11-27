<?php
// Namecheap: «Domain is invalid» (2030166) en namecheap.domains.getInfo = el dominio no está en nuestra cuenta.
//
// Prueba real de sólo lectura del 2026-11-27 (github.com, php.net y google.com.mx): Namecheap contesta Status="ERROR"
// con <Error Number="2030166">Domain is invalid</Error> para cualquier dominio que no es de la cuenta. Antes sólo se
// aceptaban 2019166 y 2016166, así que TODA transferencia respondía «No pudimos comprobar si … se puede transferir».
// Las respuestas reales (sin claves) están en tests/fixtures/namecheap/getinfo-2030166-*.xml.
//
// Comprueba: las tres respuestas dan ours=false; la transferencia sigue su camino normal (sin «No pudimos comprobar»);
// un error de autenticación y un error distinto de la API siguen contando como falla; en Dynadot, el texto equivalente
// («Domain is invalid») también vale como «no es nuestro».
// Uso: php tests/hosting-namecheap-getinfo.php
require __DIR__ . '/fixtures/hosting-wp.php';
$C = 'Digitalisimo_Hosting_Checkout';

function wc_add_notice( $m, $t = 'success' ) {}
function get_woocommerce_currency() { return 'MXN'; }

$REAL = array();
foreach ( array( 'github.com', 'php.net', 'google.com.mx' ) as $d ) {
	$REAL[ $d ] = file_get_contents( __DIR__ . '/fixtures/namecheap/getinfo-2030166-' . $d . '.xml' );
	check( false !== strpos( $REAL[ $d ], 'Number="2030166"' ) && false === stripos( $REAL[ $d ], 'ApiKey' ), 'La respuesta real de ' . $d . ' trae el error 2030166 y ninguna clave' );
}
// getInfo de esos dominios contesta con la respuesta real; $GLOBALS['getinfo_mode'] fuerza otro error.
$GLOBALS['getinfo_mode'] = '';
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) use ( $REAL ) {
		if ( false !== $pre || 0 !== strpos( $url, 'https://api.namecheap.com/' ) ) return $pre;
		$body = (array) ( $args['body'] ?? array() );
		if ( 'namecheap.domains.getInfo' !== ( $body['Command'] ?? '' ) ) return $pre;
		$d = (string) ( $body['DomainName'] ?? '' );
		$GLOBALS['nc']['calls'][] = array( 'cmd' => 'namecheap.domains.getInfo', 'fields' => array( 'DomainName' => $d ), 'key' => '' );
		if ( 'auth' === $GLOBALS['getinfo_mode'] ) return digi_xml( '<Error Number="1011102">Parameter APIKey is invalid</Error>', false );
		if ( 'other' === $GLOBALS['getinfo_mode'] ) return digi_xml( '<Error Number="2030280">TLD is not supported in API</Error>', false );
		if ( isset( $REAL[ $d ] ) ) return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => $REAL[ $d ] );
		return $pre;
	},
	5,
	3
);

digi_servers_reset();
digi_store_settings();
digi_product( 22, 'Dominio .net', 300 );
digi_product( 23, 'Dominio .com.mx', 600 );
foreach ( array( 20 => '.com', 21 => '.mx', 22 => '.net', 23 => '.com.mx' ) as $pid => $ext ) { update_post_meta( $pid, '_digitalisimo_hosting_type', 'domain' ); update_post_meta( $pid, '_digitalisimo_tld', $ext ); }
digi_product( 50, 'Transferencia de dominio', 400 );
update_post_meta( 50, '_digitalisimo_hosting_type', 'domain' ); update_post_meta( 50, '_digitalisimo_domain_kind', 'transfer' ); update_post_meta( 50, '_digitalisimo_tld', '*' );
// Compatibilidad con el formato de «Productos por extensión» de la versión anterior (lo ignora la nueva).
$o = get_option( 'digitalisimo_hosting_options' ); $o['domain_products'] = ".com|20\n.mx|21\n.net|22\n.com.mx|23"; update_option( 'digitalisimo_hosting_options', $o );
Digitalisimo_Hosting_Products::flush();
// Los tres están registrados (en otra cuenta): check los da por ocupados.
$GLOBALS['nc']['taken'] = array( 'github.com', 'php.net', 'google.com.mx' );

$ncl = new Digitalisimo_Hosting_Namecheap( Digitalisimo_Hosting::settings() );
$EPP = 'Ab#12345xyz';
foreach ( array( 'github.com', 'php.net', 'google.com.mx' ) as $d ) {
	$info = $ncl->info( $d, 15 );
	check( 'ok' === $info['kind'] && false === $info['ours'] && 0 === (int) $info['expires'], 'getInfo de ' . $d . ' (2030166, «Domain is invalid») da «no es nuestro»: ' . json_encode( array( $info['kind'], $info['ours'] ?? null, $info['message'] ?? '' ) ) );
	$err = $C::line_error( 50, array( 'digitalisimo_domain' => $d, 'digitalisimo_epp_raw' => $EPP ), true );
	check( '' === $err, 'La transferencia de ' . $d . ' sigue su camino normal (sin «No pudimos comprobar»): «' . $err . '»' );
}
check( 3 <= digi_nc_count( 'namecheap.domains.getInfo' ), 'La transferencia consultó getInfo en Namecheap' );

// Un error de autenticación sigue siendo falla: la transferencia no avanza.
$GLOBALS['getinfo_mode'] = 'auth';
$info = $ncl->info( 'github.com', 15 );
check( 'auth' === $info['kind'] && empty( $info['ours'] ), 'Clave rechazada en getInfo: sigue contando como falla (auth), nunca como «no es nuestro»' );
$GLOBALS['nc']['taken'][] = 'otro-ajeno.com';
$err = $C::line_error( 50, array( 'digitalisimo_domain' => 'otro-ajeno.com', 'digitalisimo_epp_raw' => $EPP ), true );
check( false !== strpos( $err, 'No pudimos comprobar si otro-ajeno.com se puede transferir' ), 'Con la clave rechazada, la transferencia se frena con «No pudimos comprobar»: «' . $err . '»' );

// Otro error de la API (no de «no es nuestro») también es falla.
$GLOBALS['getinfo_mode'] = 'other';
$info = $ncl->info( 'php.net', 15 );
check( 'api' === $info['kind'] && '2030280' === $info['number'] && empty( $info['ours'] ), 'Un error distinto de getInfo (2030280) sigue contando como falla' );
$GLOBALS['getinfo_mode'] = '';

// Dynadot: el mismo texto («Domain is invalid») en domain_info vale como «no es nuestro», sin depender de la búsqueda.
require_once __DIR__ . '/../digitalisimo-hosting/includes/class-hosting-registrars.php';
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false !== $pre || 0 !== strpos( $url, 'https://api.dynadot.com/' ) ) return $pre;
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
		$GLOBALS['dyn_calls'][] = $q['command'] ?? '';
		if ( 'domain_info' === ( $q['command'] ?? '' ) ) return digi_json( array( 'DomainInfoResponse' => array( 'ResponseCode' => '-1', 'Status' => 'error', 'Error' => 'Domain is invalid' ) ) );
		return digi_json( array( 'SearchResponse' => array( 'ResponseCode' => '-1', 'Status' => 'error', 'Error' => 'no debería consultarse' ) ) );
	},
	5,
	3
);
$GLOBALS['dyn_calls'] = array();
$dyn = new Digitalisimo_Hosting_Dynadot( array( 'dynadot_api_key' => 'CLAVE-DYNADOT' ) );
$info = $dyn->info( 'github.com', 15 );
check( 'ok' === $info['kind'] && false === $info['ours'] && array( 'domain_info' ) === $GLOBALS['dyn_calls'], 'Dynadot: «Domain is invalid» en domain_info también es «no es nuestro» (sin consultar la búsqueda)' );

echo "OK: Namecheap 2030166 = no es nuestro (" . $GLOBALS['checks'] . " comprobaciones)\n";
