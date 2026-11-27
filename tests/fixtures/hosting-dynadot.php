<?php
/**
 * Dynadot simulado por pre_http_request para las pruebas de Hosting (nada sale a internet). La forma de cada respuesta
 * sigue las respuestas REALES de la prueba de sólo lectura del 2026-11-27 contra la cuenta del dueño (grabadas sin la
 * clave en tests/fixtures/dynadot/: search libre, ocupado, en lote, .mx, .com.mx, .lat, .net, saldo cero y tld_price
 * recortado) y, para lo que esa prueba no tocó (registro, contactos, nameservers, renovación, domain_info), la
 * documentación de api3.json (https://www.dynadot.com/domain/api3.html): «<Comando>Response» con ResponseCode 0 (bien)
 * o -1 (error), Status y Error. Los textos de error que no salieron en la prueba real son supuestos.
 * Con $GLOBALS['dd']['replay'] = 'archivo' se contesta tal cual la respuesta grabada tests/fixtures/dynadot/archivo.json.
 *
 * Estado en $GLOBALS['dd']: dominios de la cuenta (dominio => vencimiento), ocupados, premium, saldo en USD, precios por
 * extensión (registro, renovación, transferencia), nameservers de la cuenta, contactos creados y cada llamada (comando,
 * parámetros sin la clave y la clave que llegó). Modos: 'down' (sin red: el error de cURL trae la URL con la clave),
 * 'http' => código (429, 503, 403…), 'auth' (clave rechazada), 'echo' (Dynadot repite la clave en el error),
 * 'register_error' (texto del error de register), 'info_error' (texto de domain_info de un dominio ajeno),
 * 'multi_ok' (una cuenta Bulk: acepta varios dominios por búsqueda).
 */
const DD_KEY = 'DD-CLAVE-SECRETA-123';

function dd_reset( array $extra = array() ) {
	delete_transient( 'digitalisimo_hosting_dd_prices' );
	$GLOBALS['dd'] = $extra + array(
		'domains' => array(), 'taken' => array(), 'premium' => array(), 'balance' => 300.0,
		'prices' => array( 'mx' => array( 20.0, 25.0, 25.0 ), 'com.mx' => array( 15.0, 15.0, 15.0 ), 'com' => array( 10.5, 11.5, 10.5 ) ),
		'servers' => array( 'ns1.dynadot.com' ), 'contacts' => array(), 'calls' => array(), 'dns' => array(),
		'mode' => '', 'http' => 0, 'register_error' => '', 'info_error' => 'domain not found', 'unsupported' => array( '.xyz' ),
	);
}
dd_reset();

/** Las llamadas a un comando (o todas). */
function dd_calls( $cmd = null ) { return array_values( array_filter( $GLOBALS['dd']['calls'], function ( $c ) use ( $cmd ) { return null === $cmd || $c['cmd'] === $cmd; } ) ); }
function dd_count( $cmd = null ) { return count( dd_calls( $cmd ) ); }
function dd_cmds() { return array_column( $GLOBALS['dd']['calls'], 'cmd' ); }

function dd_response( $cmd, array $data ) {
	$name = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $cmd ) ) ) . 'Response';
	return digi_json( array( $name => $data ) );
}
function dd_error( $cmd, $text ) { return dd_response( $cmd, array( 'ResponseCode' => '-1', 'Status' => 'error', 'Error' => $text ) ); }

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false !== $pre ) return $pre;
		if ( 0 !== strpos( $url, 'https://api.dynadot.com/api3.json' ) && 0 !== strpos( $url, 'https://api-sandbox.dynadot.com/api3.json' ) ) return $pre;
		$d = &$GLOBALS['dd'];
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
		$cmd = (string) ( $q['command'] ?? '' );
		$key = (string) ( $q['key'] ?? '' );
		$params = $q; unset( $params['key'], $params['command'] );
		$d['calls'][] = array( 'cmd' => $cmd, 'params' => $params, 'key' => $key, 'method' => $args['method'] ?? '', 'sandbox' => 0 === strpos( $url, 'https://api-sandbox.' ), 'body' => $args['body'] ?? null );
		if ( ! empty( $d['replay'] ) ) return array( 'response' => array( 'code' => 200 ), 'body' => (string) file_get_contents( __DIR__ . '/dynadot/' . basename( $d['replay'] ) . '.json' ), 'headers' => array() );
		if ( 'down' === $d['mode'] ) return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 15001 milliseconds with 0 bytes received (' . $url . ')' );
		if ( $d['http'] ) return array( 'response' => array( 'code' => (int) $d['http'] ), 'body' => '<html>error</html>', 'headers' => array() );
		if ( 'auth' === $d['mode'] || DD_KEY !== $key ) return dd_error( $cmd, 'invalid key' );
		if ( 'echo' === $d['mode'] ) return dd_error( $cmd, 'bad request for key ' . $key );
		switch ( $cmd ) {
			case 'search':
				// Respuesta real de una cuenta Regular con dos dominios en un comando.
				if ( isset( $params['domain1'] ) && 'multi_ok' !== $d['mode'] ) return dd_error( $cmd, 'too many domains entered, please search one domain per command' );
				$rows = array();
				for ( $i = 0; $i < 100; $i++ ) {
					if ( ! isset( $params[ 'domain' . $i ] ) ) break;
					$dom = strtolower( $params[ 'domain' . $i ] );
					if ( in_array( substr( $dom, strpos( $dom, '.' ) ), $d['unsupported'], true ) ) { if ( 0 === $i && ! isset( $params['domain1'] ) ) return dd_error( $cmd, 'tld is not supported' ); continue; }
					$taken = isset( $d['domains'][ $dom ] ) || in_array( $dom, $d['taken'], true );
					// Como la respuesta real: Status por fila, un ocupado sin Price, y el precio en texto con registro y renovación.
					$row = array( 'DomainName' => $dom, 'Status' => 'success', 'Available' => $taken ? 'no' : 'yes' );
					$pp = $d['prices'][ substr( $dom, strpos( $dom, '.' ) + 1 ) ] ?? array( 9.99, 9.99 );
					// El texto de un premium no salió en la prueba real: se supone el mismo con «is a Premium Domain».
					if ( ! empty( $params['show_price'] ) && ! $taken ) $row['Price'] = in_array( $dom, $d['premium'], true ) ? 'Registration Price: 2500.00 in USD and Renewal price: 45.00 in USD and Domain is a Premium Domain' : 'Registration Price: ' . number_format( $pp[0], 2, '.', '' ) . ' in USD and Renewal price: ' . number_format( $pp[1], 2, '.', '' ) . ' in USD and Domain is not a Premium Domain';
					$rows[] = $row;
				}
				return digi_json( array( 'SearchResponse' => array( 'ResponseCode' => '0', 'SearchResults' => $rows ) ) );
			case 'get_account_balance':
				// Respuesta real con saldo cero: error, no 0.
				if ( $d['balance'] <= 0 ) return dd_error( $cmd, 'There is no balance in your account' );
				return dd_response( $cmd, array( 'ResponseCode' => 0, 'Status' => 'success', 'BalanceList' => array( array( 'Currency' => $d['balance_currency'] ?? 'USD', 'Amount' => number_format( $d['balance'], 2, '.', '' ) ) ) ) );
			case 'tld_price':
				$list = array();
				foreach ( $d['prices'] as $tld => $p ) $list[] = array( 'Tld' => '.' . $tld, 'Usage' => 'General', 'IDN' => 'YES', 'Restrictions' => '--', 'OnSale' => 'Yes', 'Privacy' => '0.00', 'Price' => array( 'Unit' => '(Price/1 year)', 'Register' => number_format( $p[0], 2, '.', '' ), 'Renew' => number_format( $p[1], 2, '.', '' ), 'Transfer' => number_format( $p[2], 2, '.', '' ), 'Restore' => '80.00' ), 'GracePeriod' => array( 'Unit' => '10.00', 'Renew' => '10.00', 'Delete' => '30', 'Restore' => 'YES' ), 'maxDuration' => 10, 'minDuration' => 1 );
				return digi_json( array( 'TldPriceResponse' => array( 'ResponseCode' => 0, 'Status' => 'success', 'PriceLevel' => 'Regular Account', 'Currency' => 'USD', 'TldPrice' => $list ) ) );
			case 'domain_info':
				$dom = strtolower( (string) ( $params['domain'] ?? '' ) );
				if ( ! isset( $d['domains'][ $dom ] ) ) return dd_error( $cmd, $d['info_error'] );
				return digi_json( array( 'DomainInfoResponse' => array( 'ResponseCode' => 0, 'Status' => 'success', 'DomainInfo' => array( 'Name' => $dom, 'Expiration' => (string) ( $d['domains'][ $dom ] * 1000 ), 'Registration' => (string) ( ( $d['created'][ $dom ] ?? gmmktime( 0, 0, 0, 1, 1, 2026 ) ) * 1000 ), 'NameServerSettings' => array( 'Type' => 'Dynadot Parking', 'WithAds' => 'Yes' ), 'Locked' => 'yes', 'Status' => 'active' ) ) ) );
			case 'create_contact':
				$id = (string) ( 1000 + count( $d['contacts'] ) + 1 );
				$d['contacts'][ $id ] = $params;
				return digi_json( array( 'CreateContactResponse' => array( 'ResponseCode' => '0', 'Status' => 'success', 'CreateContactContent' => array( 'ContactId' => $id ) ) ) );
			case 'register':
				$dom = strtolower( (string) ( $params['domain'] ?? '' ) );
				if ( '' !== $d['register_error'] ) return dd_error( $cmd, $d['register_error'] );
				if ( isset( $d['domains'][ $dom ] ) || in_array( $dom, $d['taken'], true ) ) return dd_error( $cmd, 'domain not available' );
				$cost = $d['prices'][ substr( $dom, strpos( $dom, '.' ) + 1 ) ][0] ?? 9.99;
				if ( $d['balance'] < $cost ) return dd_error( $cmd, 'insufficient balance' );
				$d['balance'] -= $cost; $d['domains'][ $dom ] = strtotime( '+1 year' ); $d['created'][ $dom ] = time();
				return dd_response( $cmd, array( 'ResponseCode' => 0, 'Status' => 'success', 'DomainName' => $dom, 'Expiration' => $d['domains'][ $dom ] * 1000 ) );
			case 'server_list':
				$list = array();
				foreach ( $d['servers'] as $i => $s ) $list[] = array( 'ServerId' => (string) $i, 'ServerName' => $s );
				return digi_json( array( 'ServerListResponse' => array( 'ResponseCode' => 0, 'Status' => 'success', 'ServerList' => $list ) ) );
			case 'add_ns':
				$d['servers'][] = strtolower( (string) $params['host'] );
				return digi_json( array( 'AddNsResponse' => array( 'ResponseCode' => 0, 'Status' => 'success', 'AddNsContent' => array( 'Server' => array( 'Host' => $params['host'], 'ServerId' => count( $d['servers'] ) - 1 ) ) ) ) );
			case 'set_ns':
				$ns = array();
				for ( $i = 0; $i < 13; $i++ ) if ( isset( $params[ 'ns' . $i ] ) ) $ns[] = $params[ 'ns' . $i ];
				foreach ( $ns as $n ) if ( ! in_array( $n, $d['servers'], true ) ) return dd_error( $cmd, 'name server ' . $n . ' is not in your account' );
				$d['dns'][ $params['domain'] ] = $ns;
				return digi_json( array( 'SetNsResponse' => array( 'ResponseCode' => 0, 'Status' => 'success' ) ) );
			case 'renew':
				$dom = strtolower( (string) ( $params['domain'] ?? '' ) );
				if ( ! isset( $d['domains'][ $dom ] ) ) return dd_error( $cmd, 'domain not found' );
				$d['balance'] -= $d['prices'][ substr( $dom, strpos( $dom, '.' ) + 1 ) ][1] ?? 9.99;
				$d['domains'][ $dom ] = strtotime( '+' . max( 1, (int) ( $params['duration'] ?? 1 ) ) . ' year', $d['domains'][ $dom ] );
				return dd_response( $cmd, array( 'ResponseCode' => '0', 'Status' => 'success', 'DomainName' => $dom, 'Expiration' => (string) ( $d['domains'][ $dom ] * 1000 ) ) );
		}
		return dd_error( $cmd, 'unknown command' );
	},
	5,
	3
);
