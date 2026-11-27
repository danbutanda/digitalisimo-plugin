<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente de la API XML de Namecheap para registrar, apuntar y renovar dominios.
 *
 * Métodos (documentación oficial, https://www.namecheap.com/support/api/methods/):
 *  - namecheap.users.getBalances   https://www.namecheap.com/support/api/methods/users/get-balances/ (AvailableBalance)
 *  - namecheap.users.getPricing    https://www.namecheap.com/support/api/methods/users/get-pricing/ (ProductType=DOMAIN,
 *                                  ActionName=REGISTER|RENEW, ProductName=<tld>; Price con Duration, YourPrice, AdditionalCost)
 *  - namecheap.domains.getInfo     https://www.namecheap.com/support/api/methods/domains/get-info/ (¿ya está en la cuenta?)
 *  - namecheap.domains.create      https://www.namecheap.com/support/api/methods/domains/create/ (DomainName, Years y los
 *                                  contactos Registrant, Tech, Admin y AuxBilling: FirstName, LastName, Address1, City,
 *                                  StateProvince, PostalCode, Country, Phone «+NNN.NNNNNNNNNN», EmailAddress)
 *  - namecheap.domains.dns.setCustom https://www.namecheap.com/support/api/methods/domains-dns/set-custom/ (SLD, TLD, Nameservers)
 *  - namecheap.domains.renew       https://www.namecheap.com/support/api/methods/domains/renew/ (DomainName, Years)
 *  - Códigos de error              https://www.namecheap.com/support/api/error-codes/
 * La clave viaja en el cuerpo del POST, nunca en la URL ni en notas o registros. Con la constante
 * DIGITALISIMO_NAMECHEAP_SANDBOX (true) todo va a api.sandbox.namecheap.com, para ensayar sin gastar saldo.
 */
class Digitalisimo_Hosting_Namecheap {
	const LIVE    = 'https://api.namecheap.com/xml.response';
	const SANDBOX = 'https://api.sandbox.namecheap.com/xml.response';
	/** «Domain not found» y «Domain is not associated with your account»: el dominio no está en esta cuenta. */
	const NOT_OURS = array( '2019166', '2016166' );

	private $o;

	public function __construct( array $o ) { $this->o = $o; }

	public static function from_settings() { return new self( Digitalisimo_Hosting::settings() ); }

	public function configured() { return '' !== (string) ( $this->o['api_user'] ?? '' ) && '' !== (string) ( $this->o['api_key'] ?? '' ) && '' !== (string) ( $this->o['username'] ?? '' ) && '' !== (string) ( $this->o['client_ip'] ?? '' ); }

	public static function endpoint() { return defined( 'DIGITALISIMO_NAMECHEAP_SANDBOX' ) && DIGITALISIMO_NAMECHEAP_SANDBOX ? self::SANDBOX : self::LIVE; }

	/**
	 * Una llamada. Devuelve array( 'kind' => ok|transient|auth|api|config, 'xml' => SimpleXMLElement|null, 'message', 'number' ).
	 * transient: no llegó o Namecheap no pudo atender (se puede reintentar); auth: clave, usuario o IP rechazados (códigos
	 * 1010xxx y 1011xxx); api: cualquier otro error de Namecheap (no se reintenta).
	 */
	public function call( $command, array $params = array(), $timeout = 60 ) {
		if ( ! $this->configured() ) return array( 'kind' => 'config', 'xml' => null, 'message' => 'Faltan los datos de la cuenta de Namecheap en Digitalisimo → Hosting.', 'number' => '' );
		$response = wp_remote_post(
			self::endpoint(),
			array(
				'timeout'     => max( 5, (int) $timeout ),
				'redirection' => 0,
				'body'        => array( 'ApiUser' => $this->o['api_user'], 'ApiKey' => $this->o['api_key'], 'UserName' => $this->o['username'], 'ClientIp' => $this->o['client_ip'], 'Command' => $command ) + $params,
			)
		);
		return self::classify( $response );
	}

	public static function classify( $response ) {
		if ( is_wp_error( $response ) ) return array( 'kind' => 'transient', 'xml' => null, 'message' => 'Sin respuesta de Namecheap: ' . self::clean( $response->get_error_message() ), 'number' => '' );
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$prev = function_exists( 'libxml_use_internal_errors' ) ? libxml_use_internal_errors( true ) : null;
		$xml  = '' !== trim( $body ) ? simplexml_load_string( $body ) : false;
		if ( null !== $prev ) libxml_use_internal_errors( $prev );
		if ( ! $xml ) return array( 'kind' => ( 0 === $code || 408 === $code || 429 === $code || $code >= 500 ) ? 'transient' : 'api', 'xml' => null, 'message' => 'Respuesta no reconocida de Namecheap (HTTP ' . $code . ')', 'number' => '' );
		if ( 'ERROR' === strtoupper( (string) $xml['Status'] ) ) {
			$error  = isset( $xml->Errors->Error ) ? $xml->Errors->Error[0] : null;
			$number = $error ? (string) $error['Number'] : '';
			$text   = $error ? self::clean( (string) $error ) : 'Error sin detalle';
			return array( 'kind' => preg_match( '/^101[01]\d{3}$/', $number ) ? 'auth' : 'api', 'xml' => $xml, 'message' => 'Namecheap: ' . $text . ( '' !== $number ? ' (' . $number . ')' : '' ), 'number' => $number );
		}
		return array( 'kind' => 'ok', 'xml' => $xml, 'message' => '', 'number' => '' );
	}

	private static function clean( $text ) { return substr( trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) ), 0, 300 ); }

	/** Saldo disponible en USD: array( 'kind' => ok, 'value' => float ) o el error. */
	public function balance( $timeout = 60 ) {
		$r = $this->call( 'namecheap.users.getBalances', array(), $timeout );
		if ( 'ok' !== $r['kind'] ) return $r;
		$row = $r['xml']->CommandResponse->UserGetBalancesResult ?? null;
		if ( ! $row || '' === (string) $row['AvailableBalance'] ) return array( 'kind' => 'api', 'message' => 'Namecheap no informó el saldo disponible.' ) + $r;
		$r['value'] = (float) str_replace( ',', '', (string) $row['AvailableBalance'] );
		return $r;
	}

	/** Lo que cobra Namecheap por un año de la extensión (YourPrice más el cargo ICANN, en USD). */
	public function price( $tld, $action = 'REGISTER', $timeout = 60 ) {
		$r = $this->call( 'namecheap.users.getPricing', array( 'ProductType' => 'DOMAIN', 'ActionName' => $action, 'ProductName' => ltrim( strtolower( $tld ), '.' ) ), $timeout );
		if ( 'ok' !== $r['kind'] ) return $r;
		$best = null;
		$name = ltrim( strtolower( $tld ), '.' );
		// Sólo los precios del producto pedido (si Namecheap devolviera varios, nunca el de otra extensión).
		foreach ( $r['xml']->xpath( '//*[local-name()="Product"][translate(@Name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="' . preg_replace( '/[^a-z0-9.-]/', '', $name ) . '"]/*[local-name()="Price"]' ) ?: array() as $price ) {
			if ( '1' !== (string) $price['Duration'] || 'YEAR' !== strtoupper( (string) $price['DurationType'] ) ) continue;
			$your = '' !== (string) $price['YourPrice'] ? (float) $price['YourPrice'] : (float) $price['Price'];
			// El cargo ICANN de la cuenta: Namecheap lo llama «YourAdditonalCost» (sic); se aceptan las otras formas.
			$extra = '';
			foreach ( array( 'YourAdditonalCost', 'YourAdditionalCost', 'AdditionalCost' ) as $attr ) if ( '' !== (string) $price[ $attr ] ) { $extra = (string) $price[ $attr ]; break; }
			$best = $your + (float) $extra;
			break;
		}
		if ( null === $best || $best <= 0 ) return array( 'kind' => 'api', 'message' => 'Namecheap no informó el precio de ' . $action . ' para .' . ltrim( $tld, '.' ) . '.' ) + $r;
		$r['value'] = $best;
		return $r;
	}

	/** ¿El dominio ya está en esta cuenta? kind ok + 'ours' (bool) + 'expires' (marca de tiempo o 0). */
	public function info( $domain, $timeout = 60 ) {
		$r = $this->call( 'namecheap.domains.getInfo', array( 'DomainName' => $domain ), $timeout );
		if ( 'api' === $r['kind'] && in_array( $r['number'], self::NOT_OURS, true ) ) return array( 'kind' => 'ok', 'ours' => false, 'expires' => 0 ) + $r;
		if ( 'ok' !== $r['kind'] ) return $r;
		$res = $r['xml']->CommandResponse->DomainGetInfoResult ?? null;
		$r['ours'] = (bool) $res;
		$r['expires'] = 0;
		$r['created'] = 0;
		if ( $res && isset( $res->DomainDetails->CreatedDate ) && preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', (string) $res->DomainDetails->CreatedDate, $c ) ) $r['created'] = gmmktime( 0, 0, 0, (int) $c[1], (int) $c[2], (int) $c[3] );
		if ( $res && isset( $res->DomainDetails->ExpiredDate ) ) {
			$date = (string) $res->DomainDetails->ExpiredDate; // MM/DD/YYYY
			$r['expires'] = preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date, $m ) ? gmmktime( 0, 0, 0, (int) $m[1], (int) $m[2], (int) $m[3] ) : 0;
		}
		return $r;
	}

	/** ¿Disponible y premium? namecheap.domains.check (https://www.namecheap.com/support/api/methods/domains/check/). */
	public function check( $domain, $timeout = 60 ) {
		$r = $this->call( 'namecheap.domains.check', array( 'DomainList' => $domain ), $timeout );
		if ( 'ok' !== $r['kind'] ) return $r;
		$res = $r['xml']->CommandResponse->DomainCheckResult ?? null;
		if ( ! $res ) return array( 'kind' => 'api', 'message' => 'Namecheap no informó si ' . $domain . ' está disponible.' ) + $r;
		$r['available'] = 'true' === strtolower( (string) $res['Available'] );
		$r['premium'] = self::special( $res );
		return $r;
	}

	/**
	 * Varios dominios en UNA consulta (el buscador estilo registrador): namecheap.domains.check admite una lista separada
	 * por comas en DomainList (https://www.namecheap.com/support/api/methods/domains/check/). kind ok + 'results' =>
	 * array( dominio => array( available, premium ) ). Un dominio que Namecheap no devuelve, o devuelve con ErrorNo (una
	 * extensión que no atiende), no aparece en 'results'. Nunca se devuelve nada más de la respuesta de Namecheap.
	 */
	/**
	 * ¿Precio especial? Premium (IsPremiumName) o en el periodo de acceso anticipado de una extensión nueva (EapFee mayor
	 * que 0): cuesta otra cosa que el producto, así que no se vende aquí.
	 */
	private static function special( $res ) {
		return 'true' === strtolower( (string) $res['IsPremiumName'] ) || (float) str_replace( ',', '', (string) $res['EapFee'] ) > 0;
	}

	public function check_many( array $domains, $timeout = 15 ) {
		$domains = array_values( array_unique( array_filter( array_map( 'strtolower', array_map( 'strval', $domains ) ) ) ) );
		if ( ! $domains ) return array( 'kind' => 'ok', 'results' => array() );
		$r = $this->call( 'namecheap.domains.check', array( 'DomainList' => implode( ',', $domains ) ), $timeout );
		if ( 'ok' !== $r['kind'] ) return array( 'kind' => $r['kind'], 'message' => $r['message'] );
		$out = array();
		foreach ( $r['xml']->xpath( '//*[local-name()="DomainCheckResult"]' ) ?: array() as $res ) {
			$d = strtolower( trim( (string) $res['Domain'] ) );
			$error = trim( (string) $res['ErrorNo'] );
			if ( ! in_array( $d, $domains, true ) || ( '' !== $error && '0' !== $error ) ) continue;
			$out[ $d ] = array( 'available' => 'true' === strtolower( (string) $res['Available'] ), 'premium' => self::special( $res ) );
		}
		return array( 'kind' => 'ok', 'results' => $out );
	}

	/**
	 * Extensiones que Namecheap atiende por la API: namecheap.domains.getTldList
	 * (https://www.namecheap.com/support/api/methods/domains/get-tld-list/), atributos IsApiTransferable e IsEppRequired.
	 * kind ok + 'tlds' => array( 'com' => array( 'transfer' => bool, 'epp' => bool ) ). Se guarda un día (la lista casi
	 * no cambia y la transferencia la consulta el carrito).
	 */
	public function tlds( $timeout = 60 ) {
		$cached = function_exists( 'get_transient' ) ? get_transient( 'digitalisimo_hosting_nc_tlds' ) : false;
		if ( is_array( $cached ) && $cached ) return array( 'kind' => 'ok', 'tlds' => $cached );
		$r = $this->call( 'namecheap.domains.getTldList', array(), $timeout );
		if ( 'ok' !== $r['kind'] ) return $r;
		$out = array();
		foreach ( $r['xml']->xpath( '//*[local-name()="Tld"]' ) ?: array() as $tld ) {
			$name = strtolower( trim( (string) $tld['Name'] ) );
			if ( '' === $name ) continue;
			$out[ $name ] = array( 'transfer' => 'true' === strtolower( (string) $tld['IsApiTransferable'] ), 'epp' => 'true' === strtolower( (string) $tld['IsEppRequired'] ) );
		}
		if ( ! $out ) return array( 'kind' => 'api', 'message' => 'Namecheap no devolvió la lista de extensiones.' ) + $r;
		if ( function_exists( 'set_transient' ) ) set_transient( 'digitalisimo_hosting_nc_tlds', $out, DAY_IN_SECONDS );
		$r['tlds'] = $out;
		return $r;
	}

	/**
	 * Extensiones genéricas con privacidad de WHOIS gratuita en Namecheap (WhoisGuard / Withheld for Privacy); en las de
	 * país (.mx, etc.) no se pide. Ampliable con el filtro digitalisimo_hosting_whoisguard_tlds.
	 */
	const WHOISGUARD_TLDS = array( 'com', 'net', 'org', 'info', 'biz', 'xyz', 'online', 'site', 'store', 'tech', 'lat', 'app', 'dev', 'shop', 'club' );

	/** Registra el dominio a nombre del cliente ($contact: FirstName, LastName, Address1, …), sus cuatro contactos iguales. */
	public function create( $domain, array $contact, $years = 1 ) {
		$params = array( 'DomainName' => $domain, 'Years' => max( 1, (int) $years ) );
		foreach ( array( 'Registrant', 'Tech', 'Admin', 'AuxBilling' ) as $type ) foreach ( $contact as $field => $value ) if ( '' !== (string) $value ) $params[ $type . $field ] = $value;
		// Privacidad de WHOIS donde la extensión lo permite (los datos del cliente no quedan públicos).
		$tld = strtolower( substr( $domain, strrpos( $domain, '.' ) + 1 ) );
		if ( false === strpos( substr( $domain, strpos( $domain, '.' ) + 1 ), '.' ) && in_array( $tld, (array) apply_filters( 'digitalisimo_hosting_whoisguard_tlds', self::WHOISGUARD_TLDS ), true ) ) { $params['AddFreeWhoisguard'] = 'yes'; $params['WGEnabled'] = 'yes'; }
		/** Atributos extendidos de extensiones que los exigen (https://www.namecheap.com/support/api/extended-attributes/). */
		$params = (array) apply_filters( 'digitalisimo_hosting_namecheap_create_params', $params, $domain, $contact );
		$r = $this->call( 'namecheap.domains.create', $params );
		if ( 'ok' !== $r['kind'] ) return $r;
		$res = $r['xml']->CommandResponse->DomainCreateResult ?? null;
		if ( ! $res || 'true' !== strtolower( (string) $res['Registered'] ) ) return array( 'kind' => 'api', 'message' => 'Namecheap no confirmó el registro de ' . $domain . '.' ) + $r;
		$r['domain_id'] = (string) $res['DomainID'];
		$r['charged'] = (string) $res['ChargedAmount'];
		return $r;
	}

	/** Apunta el dominio a los nameservers propios. */
	public function set_dns( $sld, $tld, array $nameservers ) {
		$r = $this->call( 'namecheap.domains.dns.setCustom', array( 'SLD' => $sld, 'TLD' => $tld, 'Nameservers' => implode( ',', $nameservers ) ) );
		if ( 'ok' !== $r['kind'] ) return $r;
		$res = $r['xml']->CommandResponse->DomainDNSSetCustomResult ?? null;
		if ( ! $res || 'true' !== strtolower( (string) $res['Updated'] ) ) return array( 'kind' => 'api', 'message' => 'Namecheap no confirmó los nameservers de ' . $sld . '.' . $tld . '.' ) + $r;
		return $r;
	}

	public function renew( $domain, $years = 1 ) {
		$r = $this->call( 'namecheap.domains.renew', array( 'DomainName' => $domain, 'Years' => max( 1, (int) $years ) ) );
		if ( 'ok' !== $r['kind'] ) return $r;
		$res = $r['xml']->CommandResponse->DomainRenewResult ?? null;
		if ( ! $res || 'true' !== strtolower( (string) $res['Renew'] ) ) return array( 'kind' => 'api', 'message' => 'Namecheap no confirmó la renovación de ' . $domain . '.' ) + $r;
		$r['charged'] = (string) $res['ChargedAmount'];
		return $r;
	}
}
