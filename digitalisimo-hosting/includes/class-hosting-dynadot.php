<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-hosting-registrars.php';

/**
 * Cliente de la API de Dynadot (pedido del propietario del 2026-11-27): el segundo registrador de Hosting.
 *
 * Usa la API «api3.json» (https://www.dynadot.com/domain/api3.html): una clave (la «Production Key» de Dynadot → Tools →
 * API), sin secreto ni firma, y TODOS los comandos documentados con su respuesta. Dynadot la marca como «Legacy API,
 * Scheduled for Retirement» sin fecha; la REST nueva (https://www.dynadot.com/domain/api-document, «Beta»: sus campos
 * pueden cambiar sin aviso) firma con HMAC-SHA256 y su lista de comandos no se puede leer sin JavaScript. Cuando la REST
 * salga de beta, sólo esta clase cambia (la interfaz Digitalisimo_Hosting_Registrar es la misma).
 *
 * Comandos (todos con `key` y `command`; respuesta JSON con ResponseCode 0 = bien, -1 = error, Status y Error):
 *  - search               domain0…domain99 (una por comando en una cuenta «Regular»: con dos, «too many domains
 *                         entered, please search one domain per command»), show_price=1, currency=USD: SearchResults[]
 *                         con DomainName, Status, Available (yes/no) y Price («Registration Price: 23.54 in USD and
 *                         Renewal price: 42.52 in USD and Domain is not a Premium Domain»; un ocupado, sin Price).
 *  - get_account_balance  BalanceList[] con Currency y Amount; con saldo cero, ResponseCode -1 y «There is no balance in
 *                         your account» (se toma como saldo 0).
 *  - tld_price            currency=USD: TldPrice[] con Tld («.com», con el punto) y Price { Register, Renew, Transfer } por
 *                         un año; ResponseCode entero 0 (en search y balance llega como texto "0"/"-1").
 * Las respuestas reales de búsqueda, saldo y precios (prueba de sólo lectura del 2026-11-27, autorizada por el dueño)
 * están grabadas en tests/fixtures/dynadot/.
 *  - domain_info          domain: DomainInfo con Name, Expiration y Registration en milisegundos.
 *  - create_contact       name, email, phonecc, phonenum, address1, address2, city, state, zip, country, organization:
 *                         CreateContactContent.ContactId (el titular del cliente; sin él, Dynadot usaría el contacto
 *                         predeterminado de la cuenta, que es el del dueño).
 *  - register             domain, duration, registrant/admin/technical/billing_contact, currency=USD (exige saldo).
 *  - server_list, add_ns  los nameservers deben existir en la cuenta antes de set_ns.
 *  - set_ns               domain, ns0…ns12.
 *  - renew                domain, duration, currency=USD, no_renew_if_late_renew_fee_needed=1 (nunca un recargo a ciegas).
 *  - transfer             domain, auth (EPP), name_servers: documentado; la transferencia sigue haciéndose a mano con el
 *                         código del pedido («Ver código EPP»), como con Namecheap.
 * Límites (de la misma página): cuenta Regular, 1 hilo y 60 por minuto; Bulk 5 hilos y 600; Super Bulk 35 y 6000. Una
 * cuenta Regular sólo busca un dominio por comando: check_many() consulta de uno en uno (filtro
 * digitalisimo_hosting_dynadot_batch para una cuenta Bulk). Dynadot → Tools → API admite una lista de IP o CIDR
 * permitidas: la del servidor de este sitio.
 *
 * La clave viaja en la consulta de la URL (así la pide api3) por HTTPS y sin redirecciones; nunca se escribe en notas,
 * registros ni respuestas, y cualquier texto de Dynadot que la repitiera se enmascara. Con la constante
 * DIGITALISIMO_DYNADOT_SANDBOX (true) todo va a api-sandbox.dynadot.com con la «Sandbox Key» (cuenta de prueba con saldo).
 */
class Digitalisimo_Hosting_Dynadot implements Digitalisimo_Hosting_Registrar {
	const LIVE    = 'https://api.dynadot.com/api3.json';
	const SANDBOX = 'https://api-sandbox.dynadot.com/api3.json';
	/** Lista de precios por extensión: se guarda unas horas (es larga y casi no cambia). */
	const PRICES = 'digitalisimo_hosting_dd_prices';

	private $o;
	private $key;

	public function __construct( array $o ) { $this->o = $o; $this->key = trim( (string) ( $o['dynadot_api_key'] ?? '' ) ); }

	public static function from_settings() { return new self( Digitalisimo_Hosting::settings() ); }

	public function id() { return 'dynadot'; }
	public function label() { return 'Dynadot'; }
	public function configured() { return '' !== $this->key; }

	public static function endpoint() { return defined( 'DIGITALISIMO_DYNADOT_SANDBOX' ) && DIGITALISIMO_DYNADOT_SANDBOX ? self::SANDBOX : self::LIVE; }

	/**
	 * Una llamada. Devuelve array( kind ok|transient|auth|api|funds|config, data => el objeto de la respuesta, message ).
	 */
	public function call( $command, array $params = array(), $timeout = 60 ) {
		if ( ! $this->configured() ) return array( 'kind' => 'config', 'data' => null, 'message' => 'Falta la clave de la API de Dynadot en Digitalisimo → Hosting.', 'number' => '' );
		$url = self::endpoint() . '?' . http_build_query( array( 'key' => $this->key, 'command' => $command ) + $params, '', '&', PHP_QUERY_RFC3986 );
		$response = wp_remote_get( $url, array( 'timeout' => max( 5, (int) $timeout ), 'redirection' => 0, 'headers' => array( 'Accept' => 'application/json' ) ) );
		$r = self::classify( $response );
		$r['message'] = $this->mask( $r['message'] );
		return $r;
	}

	/** Nunca la clave en un texto (si Dynadot o el transporte la repitieran). */
	private function mask( $text ) {
		$text = (string) $text;
		if ( '' !== $this->key ) { $text = str_replace( array( $this->key, rawurlencode( $this->key ), urlencode( $this->key ) ), '••••', $text ); }
		return preg_replace( '/([?&]key=)[^&\s]+/i', '$1••••', $text );
	}

	/**
	 * La respuesta de Dynadot traducida. Errores de clave o IP (auth): HTTP 401/403 o un texto que habla de la clave, la IP
	 * o el permiso. Saldo (funds): un texto de saldo o fondos insuficientes. Se reintenta (transient): sin respuesta, HTTP
	 * 408/429/5xx o «demasiadas peticiones / hilos». Lo demás es api.
	 */
	public static function classify( $response ) {
		if ( is_wp_error( $response ) ) return array( 'kind' => 'transient', 'data' => null, 'message' => 'Sin respuesta de Dynadot: ' . self::clean( $response->get_error_message() ), 'number' => '' );
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 401 === $code || 403 === $code ) return array( 'kind' => 'auth', 'data' => null, 'message' => 'Dynadot rechazó la clave o la IP de este servidor (HTTP ' . $code . ').', 'number' => (string) $code );
		if ( 0 === $code || 408 === $code || 429 === $code || $code >= 500 ) return array( 'kind' => 'transient', 'data' => null, 'message' => 'Dynadot no pudo atender la consulta (HTTP ' . $code . ').', 'number' => (string) $code );
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) || ! $json ) return array( 'kind' => 'api', 'data' => null, 'message' => 'Respuesta no reconocida de Dynadot (HTTP ' . $code . ').', 'number' => '' );
		// {"SearchResponse": {...}}: el único objeto de la respuesta (con cualquier nombre).
		$data = reset( $json );
		if ( ! is_array( $data ) ) $data = $json;
		$rc = isset( $data['ResponseCode'] ) ? trim( (string) $data['ResponseCode'] ) : ( isset( $data['SuccessCode'] ) ? trim( (string) $data['SuccessCode'] ) : '' );
		$status = strtolower( trim( (string) ( $data['Status'] ?? '' ) ) );
		if ( '0' === $rc && 'error' !== $status ) return array( 'kind' => 'ok', 'data' => $data, 'message' => '', 'number' => '' );
		$text = self::clean( (string) ( $data['Error'] ?? $data['Message'] ?? '' ) );
		if ( '' === $text ) $text = 'error sin detalle' . ( '' !== $status ? ' (' . $status . ')' : '' );
		$kind = 'api';
		if ( preg_match( '/\b(api ?key|invalid key|key is (?:invalid|not valid|wrong)|ip address|\bip\b|whitelist|not allowed|unauthori[sz]ed|permission|forbidden|account (?:is )?(?:locked|disabled))/i', $text ) ) $kind = 'auth';
		elseif ( preg_match( '/(insufficient|not enough|balance|fund)/i', $text ) ) $kind = 'funds';
		elseif ( preg_match( '/(too many|thread|try again|rate limit|timed? ?out|busy|system error|server error)/i', $text ) ) $kind = 'transient';
		return array( 'kind' => $kind, 'data' => $data, 'message' => 'Dynadot: ' . $text, 'number' => $rc );
	}

	private static function clean( $text ) { return substr( trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) ), 0, 300 ); }

	/** «77.00 in USD» → 77.0 (también con separador de miles). */
	private static function amount( $text ) { return preg_match( '/(\d[\d,]*(?:\.\d+)?)/', (string) $text, $m ) ? (float) str_replace( ',', '', $m[1] ) : 0.0; }

	/** Saldo disponible en USD. */
	public function balance( $timeout = 60 ) {
		$r = $this->call( 'get_account_balance', array(), $timeout );
		// Con saldo cero Dynadot NO contesta 0 sino un error (respuesta real del 2026-11-27, cuenta del dueño):
		// {"GetAccountBalanceResponse":{"ResponseCode":"-1","Status":"error","Error":"There is no balance in your account"}}.
		// Es saldo 0: así el freno por saldo frena (un error de API dejaría pasar o detendría por otra causa).
		if ( in_array( $r['kind'], array( 'api', 'funds' ), true ) && preg_match( '/no balance in your account/i', (string) $r['message'] ) ) return array( 'kind' => 'ok', 'value' => 0.0, 'message' => '' ) + $r;
		if ( 'ok' !== $r['kind'] ) return $r;
		$list = $r['data']['BalanceList'] ?? ( $r['data']['GetAccountBalanceContent']['BalanceList'] ?? null );
		if ( isset( $list['Balance'] ) ) $list = $list['Balance'];
		if ( isset( $list['Currency'] ) ) $list = array( $list );
		foreach ( is_array( $list ) ? $list : array() as $row ) {
			if ( is_array( $row ) && 'USD' === strtoupper( trim( (string) ( $row['Currency'] ?? '' ) ) ) && '' !== trim( (string) ( $row['Amount'] ?? '' ) ) ) { $r['value'] = self::amount( $row['Amount'] ); return $r; }
		}
		return array( 'kind' => 'api', 'message' => 'Dynadot no informó el saldo en USD (la venta automática compara el saldo en dólares).' ) + $r;
	}

	/** La lista de precios de Dynadot por extensión (USD, un año), guardada unas horas. kind ok + prices => tld => array. */
	private function price_list( $timeout ) {
		$cached = function_exists( 'get_transient' ) ? get_transient( self::PRICES ) : false;
		if ( is_array( $cached ) && $cached ) return array( 'kind' => 'ok', 'prices' => $cached );
		$r = $this->call( 'tld_price', array( 'currency' => 'USD', 'count_per_page' => 1000 ), $timeout );
		if ( 'ok' !== $r['kind'] ) return $r;
		if ( '' !== (string) ( $r['data']['Currency'] ?? '' ) && 'USD' !== strtoupper( (string) $r['data']['Currency'] ) ) return array( 'kind' => 'api', 'message' => 'Dynadot dio los precios en ' . self::clean( $r['data']['Currency'] ) . ' y no en USD.' ) + $r;
		$out = array();
		foreach ( (array) ( $r['data']['TldPrice'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) continue;
			$tld = strtolower( ltrim( trim( (string) ( $row['Tld'] ?? '' ) ), '.' ) );
			if ( '' === $tld || ! is_array( $row['Price'] ?? null ) ) continue;
			$out[ $tld ] = array( 'REGISTER' => self::amount( $row['Price']['Register'] ?? '' ), 'RENEW' => self::amount( $row['Price']['Renew'] ?? '' ), 'TRANSFER' => self::amount( $row['Price']['Transfer'] ?? '' ) );
		}
		if ( ! $out ) return array( 'kind' => 'api', 'message' => 'Dynadot no devolvió la lista de precios por extensión.' ) + $r;
		if ( function_exists( 'set_transient' ) ) set_transient( self::PRICES, $out, 6 * HOUR_IN_SECONDS );
		return array( 'kind' => 'ok', 'prices' => $out );
	}

	/** Lo que cobra Dynadot por un año de la extensión (USD). */
	public function price( $tld, $action = 'REGISTER', $timeout = 60 ) {
		$list = $this->price_list( $timeout );
		if ( 'ok' !== $list['kind'] ) return $list;
		$name = strtolower( ltrim( (string) $tld, '.' ) );
		$action = strtoupper( (string) $action );
		$value = (float) ( $list['prices'][ $name ][ $action ] ?? 0 );
		if ( $value <= 0 ) return array( 'kind' => 'api', 'message' => 'Dynadot no informó el precio de ' . $action . ' para .' . $name . '.' );
		return array( 'kind' => 'ok', 'value' => $value, 'message' => '' );
	}

	/** Extensiones de la lista de precios: se transfiere la que tiene precio de transferencia. */
	public function tlds( $timeout = 60 ) {
		$list = $this->price_list( $timeout );
		if ( 'ok' !== $list['kind'] ) return $list;
		$out = array();
		foreach ( $list['prices'] as $tld => $p ) $out[ $tld ] = array( 'transfer' => $p['TRANSFER'] > 0, 'epp' => true );
		return array( 'kind' => 'ok', 'tlds' => $out, 'message' => '' );
	}

	/**
	 * ¿Está en esta cuenta? domain_info de un dominio ajeno contesta con error: un texto de «no encontrado / no es tuyo»
	 * vale como «no es nuestro»; cualquier otro error detiene (nunca se adivina).
	 */
	public function info( $domain, $timeout = 60 ) {
		$r = $this->call( 'domain_info', array( 'domain' => $domain ), $timeout );
		$not_ours = array( 'kind' => 'ok', 'ours' => false, 'expires' => 0, 'created' => 0, 'message' => '' );
		if ( 'api' === $r['kind'] && preg_match( '/(not found|could not find|cannot find|doesn\'?t exist|does not exist|not (?:in|under) (?:your|this) account|not belong|not own|don\'?t own|invalid domain|domain (?:name )?is invalid|no such domain)/i', $r['message'] ) ) return $not_ours;
		// Un texto de error que no se reconoce: si la búsqueda lo da por libre, no puede estar en ninguna cuenta. Si no,
		// se detiene con el error (nunca se adivina que no es nuestro).
		if ( 'api' === $r['kind'] ) {
			$c = $this->check( $domain, $timeout );
			if ( 'ok' === $c['kind'] && ! empty( $c['available'] ) ) return $not_ours;
			return $r;
		}
		if ( 'ok' !== $r['kind'] ) return $r;
		$info = $r['data']['DomainInfo'] ?? ( $r['data']['DomainInfoContent']['Domain'] ?? null );
		$name = is_array( $info ) ? strtolower( trim( (string) ( $info['Name'] ?? '' ) ) ) : '';
		$r['ours'] = '' !== $name && strtolower( $domain ) === $name;
		$r['expires'] = $r['ours'] ? (int) floor( (float) ( $info['Expiration'] ?? $info['ExpirationDate'] ?? 0 ) / 1000 ) : 0;
		$r['created'] = $r['ours'] ? (int) floor( (float) ( $info['Registration'] ?? 0 ) / 1000 ) : 0;
		return $r;
	}

	/**
	 * Una fila de SearchResults: array( available, premium, register_usd, renew_usd ) o null si Dynadot no la informó bien.
	 * El precio llega en texto (respuesta real del 2026-11-27): «Registration Price: 23.54 in USD and Renewal price: 42.52
	 * in USD and Domain is not a Premium Domain» (la documentación mostraba «77.00 in USD»). El registro y la renovación
	 * se separan (.mx renueva a 42.52 tras 23.54; .lat, 26.97 tras 1.54 de promoción). Un ocupado llega sin Price.
	 */
	private static function row( $row ) {
		if ( ! is_array( $row ) ) return null;
		$status = strtolower( trim( (string) ( $row['Status'] ?? '' ) ) );
		$avail = strtolower( trim( (string) ( $row['Available'] ?? '' ) ) );
		if ( 'error' === $status || ! in_array( $avail, array( 'yes', 'no' ), true ) ) return null;
		$price = (string) ( $row['Price'] ?? '' );
		$premium = false;
		if ( preg_match( '/premium/i', $price ) ) $premium = ! preg_match( '/\bnot\s+(?:a\s+)?premium/i', $price );
		if ( in_array( strtolower( trim( (string) ( $row['IsPremium'] ?? $row['Premium'] ?? '' ) ) ), array( 'yes', 'true', '1' ), true ) ) $premium = true;
		$out = array( 'available' => 'yes' === $avail, 'premium' => $premium );
		$reg = preg_match( '/registration\s+price:\s*(\d[\d,]*(?:\.\d+)?)\s*in\s+usd/i', $price, $m ) ? (float) str_replace( ',', '', $m[1] ) : ( preg_match( '/^\s*(\d[\d,]*(?:\.\d+)?)\s*in\s+usd/i', $price, $m ) ? (float) str_replace( ',', '', $m[1] ) : 0.0 );
		$ren = preg_match( '/renewal\s+price:\s*(\d[\d,]*(?:\.\d+)?)\s*in\s+usd/i', $price, $m ) ? (float) str_replace( ',', '', $m[1] ) : 0.0;
		if ( $reg > 0 ) $out['register_usd'] = $reg;
		if ( $ren > 0 ) $out['renew_usd'] = $ren;
		return $out;
	}

	private static function results( $data, &$errors = null ) {
		$rows = $data['SearchResults'] ?? ( $data['SearchResult'] ?? array() );
		if ( isset( $rows['DomainName'] ) ) $rows = array( $rows );
		$out = array(); $errors = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$d = is_array( $row ) ? strtolower( trim( (string) ( $row['DomainName'] ?? '' ) ) ) : '';
			$res = self::row( $row );
			if ( '' !== $d && $res ) $out[ $d ] = $res;
			// Un error por nombre llega con ResponseCode 0 y el error en su fila (respuesta real del 2026-11-27: xq.com.mx,
			// «unexpected registry error»): sólo ese nombre queda sin consultar.
			elseif ( '' !== $d ) $errors[ $d ] = self::clean( (string) ( $row['Error'] ?? 'sin respuesta para ese nombre' ) );
		}
		return $out;
	}

	/**
	 * Red de seguridad del premium: un precio de registro mayor que el de la lista de precios de su extensión (`tld_price`)
	 * es un precio especial aunque el texto no lo diga. Respuesta real del 2026-11-27: «Registration Price: 715.00 in USD
	 * and … Domain is a Premium Domain» (vino.lat, zx.lat).
	 */
	private function special( array $results, $timeout ) {
		$list = null;
		foreach ( $results as $d => $res ) {
			if ( ! empty( $res['premium'] ) || empty( $res['available'] ) || empty( $res['register_usd'] ) ) continue;
			if ( null === $list ) { $l = $this->price_list( min( 10, max( 5, (int) $timeout ) ) ); $list = 'ok' === $l['kind'] ? $l['prices'] : array(); }
			$tld = strtolower( substr( $d, strpos( $d, '.' ) + 1 ) );
			$std = (float) ( $list[ $tld ]['REGISTER'] ?? 0 );
			if ( $std > 0 && (float) $res['register_usd'] > $std + 0.01 ) $results[ $d ]['premium'] = true;
		}
		return $results;
	}

	/** ¿Disponible y premium? (search con show_price: el texto del precio dice si es premium). */
	public function check( $domain, $timeout = 60 ) {
		$domain = strtolower( (string) $domain );
		$r = $this->call( 'search', array( 'domain0' => $domain, 'show_price' => 1, 'currency' => 'USD' ), $timeout );
		if ( 'ok' !== $r['kind'] ) return $r;
		$all = self::results( $r['data'], $errors );
		$res = $all ? ( $this->special( $all, $timeout )[ $domain ] ?? null ) : null;
		if ( ! $res ) return array( 'kind' => 'api', 'message' => 'Dynadot no informó si ' . $domain . ' está disponible' . ( isset( $errors[ $domain ] ) ? ' (' . $errors[ $domain ] . ')' : '' ) . '.' ) + $r;
		return $res + $r;
	}

	/**
	 * Varios dominios, de $batch en $batch (1 en una cuenta Regular, que sólo busca uno por comando; hasta 100 con el
	 * filtro digitalisimo_hosting_dynadot_batch en una cuenta Bulk), uno detrás de otro (una cuenta Regular tiene un solo
	 * hilo) y sin pasar de $timeout segundos en total. Un dominio que Dynadot no informa (o rechaza, una extensión que no
	 * atiende) no aparece en 'results'. Una falla de red, de clave o de cupo corta y devuelve lo que ya se supo.
	 */
	public function check_many( array $domains, $timeout = 15 ) {
		$domains = array_values( array_unique( array_filter( array_map( 'strtolower', array_map( 'strval', $domains ) ) ) ) );
		$out = array();
		if ( ! $domains ) return array( 'kind' => 'ok', 'results' => $out );
		$batch = max( 1, min( 100, (int) apply_filters( 'digitalisimo_hosting_dynadot_batch', 1 ) ) );
		$start = microtime( true );
		$errs = array();
		foreach ( array_chunk( $domains, $batch ) as $chunk ) {
			$left = $timeout - ( microtime( true ) - $start );
			if ( $left < 1 ) return array( 'kind' => 'transient', 'message' => 'Dynadot tardó demasiado; faltaron dominios por consultar.', 'results' => $out );
			$params = array( 'show_price' => 1, 'currency' => 'USD' );
			foreach ( $chunk as $i => $d ) $params[ 'domain' . $i ] = $d;
			$r = $this->call( 'search', $params, (int) max( 5, ceil( $left ) ) );
			// Una cuenta Regular rechaza el lote entero (respuesta real: «too many domains entered, please search one
			// domain per command»): con el filtro subido por error, se sigue de uno en uno en lugar de fallar.
			if ( 'ok' !== $r['kind'] && count( $chunk ) > 1 && preg_match( '/too many domains|one domain per command/i', (string) $r['message'] ) ) {
				$rest = $this->check_many_one( array_slice( $domains, array_search( $chunk[0], $domains, true ) ), $timeout - ( microtime( true ) - $start ) );
				$rest['results'] = $out + $rest['results'];
				return $rest;
			}
			if ( 'api' === $r['kind'] && 1 === count( $chunk ) ) { $errs[ $chunk[0] ] = (string) $r['message']; continue; }
			if ( 'ok' !== $r['kind'] ) return array( 'kind' => $r['kind'], 'message' => $r['message'], 'results' => $out, 'errors' => $errs );
			foreach ( $this->special( self::results( $r['data'], $e ), $left ) as $d => $res ) if ( in_array( $d, $chunk, true ) ) $out[ $d ] = $res;
			foreach ( $e as $d => $text ) if ( in_array( $d, $chunk, true ) ) $errs[ $d ] = $text;
		}
		return array( 'kind' => 'ok', 'results' => $out, 'errors' => $errs );
	}

	/** check_many() de uno en uno (sin el filtro de lote). */
	private function check_many_one( array $domains, $timeout ) {
		$out = array(); $errs = array();
		$start = microtime( true );
		foreach ( $domains as $d ) {
			$left = $timeout - ( microtime( true ) - $start );
			if ( $left < 1 ) return array( 'kind' => 'transient', 'message' => 'Dynadot tardó demasiado; faltaron dominios por consultar.', 'results' => $out, 'errors' => $errs );
			$r = $this->call( 'search', array( 'domain0' => $d, 'show_price' => 1, 'currency' => 'USD' ), (int) max( 5, ceil( $left ) ) );
			if ( 'api' === $r['kind'] ) { $errs[ $d ] = (string) $r['message']; continue; }
			if ( 'ok' !== $r['kind'] ) return array( 'kind' => $r['kind'], 'message' => $r['message'], 'results' => $out, 'errors' => $errs );
			$res = $this->special( self::results( $r['data'], $e ), $left )[ $d ] ?? null;
			if ( $res ) $out[ $d ] = $res; elseif ( isset( $e[ $d ] ) ) $errs[ $d ] = $e[ $d ];
		}
		return array( 'kind' => 'ok', 'results' => $out, 'errors' => $errs );
	}

	/** El titular para create_contact a partir de los campos de Digitalisimo_Hosting_Provisioning::contact(). */
	public static function contact_params( array $c ) {
		$phone = (string) ( $c['Phone'] ?? '' );
		$cc = ''; $num = preg_replace( '/\D/', '', $phone );
		if ( preg_match( '/^\+(\d{1,4})\.(\d+)$/', $phone, $m ) ) { $cc = $m[1]; $num = $m[2]; }
		$p = array(
			'name'         => trim( ( $c['FirstName'] ?? '' ) . ' ' . ( $c['LastName'] ?? '' ) ),
			'organization' => (string) ( $c['OrganizationName'] ?? '' ),
			'email'        => (string) ( $c['EmailAddress'] ?? '' ),
			'phonecc'      => $cc,
			'phonenum'     => $num,
			'address1'     => (string) ( $c['Address1'] ?? '' ),
			'address2'     => (string) ( $c['Address2'] ?? '' ),
			'city'         => (string) ( $c['City'] ?? '' ),
			'state'        => (string) ( $c['StateProvince'] ?? '' ),
			'zip'          => (string) ( $c['PostalCode'] ?? '' ),
			'country'      => (string) ( $c['Country'] ?? '' ),
		);
		return array_filter( $p, function ( $v ) { return '' !== $v; } );
	}

	/**
	 * Registra a nombre del cliente: primero su contacto (create_contact) y después el dominio con ese contacto en los
	 * cuatro papeles. Sin contacto no se registra (Dynadot pondría el del dueño de la cuenta).
	 */
	public function create( $domain, array $contact, $years = 1 ) {
		$c = $this->call( 'create_contact', self::contact_params( $contact ) );
		if ( 'ok' !== $c['kind'] ) return $c;
		$id = trim( (string) ( $c['data']['CreateContactContent']['ContactId'] ?? ( $c['data']['ContactId'] ?? '' ) ) );
		if ( '' === $id || ! preg_match( '/^\d+$/', $id ) ) return array( 'kind' => 'api', 'message' => 'Dynadot no devolvió el contacto del titular de ' . $domain . ': no se registró.' ) + $c;
		$params = array( 'domain' => $domain, 'duration' => max( 1, (int) $years ), 'registrant_contact' => $id, 'admin_contact' => $id, 'technical_contact' => $id, 'billing_contact' => $id, 'currency' => 'USD' );
		/** Opciones de extensiones que las exigen (option0/option1 de .AT y .BE en la documentación de register). */
		$params = (array) apply_filters( 'digitalisimo_hosting_dynadot_create_params', $params, $domain, $contact );
		$r = $this->call( 'register', $params );
		if ( 'ok' !== $r['kind'] ) return $r;
		if ( 'success' !== strtolower( trim( (string) ( $r['data']['Status'] ?? '' ) ) ) ) return array( 'kind' => 'api', 'message' => 'Dynadot no confirmó el registro de ' . $domain . '.' ) + $r;
		$r['domain_id'] = '';
		$r['contact_id'] = $id;
		$r['charged'] = '';
		$r['expires'] = (int) floor( (float) ( $r['data']['Expiration'] ?? 0 ) / 1000 );
		return $r;
	}

	/** Apunta el dominio a los nameservers: los que faltan se agregan a la cuenta (add_ns) y después set_ns. */
	public function set_dns( $sld, $tld, array $nameservers ) {
		$domain = $sld . '.' . $tld;
		$nameservers = array_values( array_slice( array_filter( array_map( 'strtolower', array_map( 'trim', $nameservers ) ) ), 0, 13 ) );
		$list = $this->call( 'server_list' );
		if ( 'ok' !== $list['kind'] ) return $list;
		$have = array();
		$servers = $list['data']['ServerList'] ?? ( $list['data']['ServerListContent']['NameServerList']['List']['Server'] ?? array() );
		if ( isset( $servers['ServerName'] ) ) $servers = array( $servers );
		foreach ( is_array( $servers ) ? $servers : array() as $s ) if ( is_array( $s ) ) $have[] = strtolower( trim( (string) ( $s['ServerName'] ?? '' ) ) );
		foreach ( $nameservers as $ns ) {
			if ( in_array( $ns, $have, true ) ) continue;
			$add = $this->call( 'add_ns', array( 'host' => $ns ) );
			if ( 'ok' !== $add['kind'] ) return $add;
		}
		$params = array( 'domain' => $domain );
		foreach ( $nameservers as $i => $ns ) $params[ 'ns' . $i ] = $ns;
		$r = $this->call( 'set_ns', $params );
		if ( 'ok' !== $r['kind'] ) return $r;
		if ( 'success' !== strtolower( trim( (string) ( $r['data']['Status'] ?? '' ) ) ) ) return array( 'kind' => 'api', 'message' => 'Dynadot no confirmó los nameservers de ' . $domain . '.' ) + $r;
		return $r;
	}

	/** Renueva un año; con recargo por renovación tardía no renueva (no_renew_if_late_renew_fee_needed). */
	public function renew( $domain, $years = 1 ) {
		$r = $this->call( 'renew', array( 'domain' => $domain, 'duration' => max( 1, (int) $years ), 'currency' => 'USD', 'no_renew_if_late_renew_fee_needed' => 1 ) );
		if ( 'ok' !== $r['kind'] ) return $r;
		if ( 'success' !== strtolower( trim( (string) ( $r['data']['Status'] ?? '' ) ) ) ) return array( 'kind' => 'api', 'message' => 'Dynadot no confirmó la renovación de ' . $domain . '.' ) + $r;
		$r['charged'] = '';
		return $r;
	}
}
