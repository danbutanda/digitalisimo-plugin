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
	public function call( $command, array $params = array() ) {
		if ( ! $this->configured() ) return array( 'kind' => 'config', 'xml' => null, 'message' => 'Faltan los datos de la cuenta de Namecheap en Digitalisimo → Hosting.', 'number' => '' );
		$response = wp_remote_post(
			self::endpoint(),
			array(
				'timeout'     => 60,
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
	public function balance() {
		$r = $this->call( 'namecheap.users.getBalances' );
		if ( 'ok' !== $r['kind'] ) return $r;
		$row = $r['xml']->CommandResponse->UserGetBalancesResult ?? null;
		if ( ! $row || '' === (string) $row['AvailableBalance'] ) return array( 'kind' => 'api', 'message' => 'Namecheap no informó el saldo disponible.' ) + $r;
		$r['value'] = (float) str_replace( ',', '', (string) $row['AvailableBalance'] );
		return $r;
	}

	/** Lo que cobra Namecheap por un año de la extensión (YourPrice más el cargo ICANN, en USD). */
	public function price( $tld, $action = 'REGISTER' ) {
		$r = $this->call( 'namecheap.users.getPricing', array( 'ProductType' => 'DOMAIN', 'ActionName' => $action, 'ProductName' => ltrim( strtolower( $tld ), '.' ) ) );
		if ( 'ok' !== $r['kind'] ) return $r;
		$best = null;
		foreach ( $r['xml']->xpath( '//*[local-name()="Price"]' ) ?: array() as $price ) {
			if ( '1' !== (string) $price['Duration'] || 'YEAR' !== strtoupper( (string) $price['DurationType'] ) ) continue;
			$your = '' !== (string) $price['YourPrice'] ? (float) $price['YourPrice'] : (float) $price['Price'];
			// El cargo ICANN de la cuenta: Namecheap lo llama «YourAdditonalCost» (sic); se aceptan las otras formas.
			$extra = '';
			foreach ( array( 'YourAdditonalCost', 'YourAdditionalCost', 'AdditionalCost' ) as $attr ) if ( '' !== (string) $price[ $attr ] ) { $extra = (string) $price[ $attr ]; break; }
			$best = $your + (float) $extra;
			break;
		}
		if ( null === $best ) return array( 'kind' => 'api', 'message' => 'Namecheap no informó el precio de ' . $action . ' para .' . ltrim( $tld, '.' ) . '.' ) + $r;
		$r['value'] = $best;
		return $r;
	}

	/** ¿El dominio ya está en esta cuenta? kind ok + 'ours' (bool) + 'expires' (marca de tiempo o 0). */
	public function info( $domain ) {
		$r = $this->call( 'namecheap.domains.getInfo', array( 'DomainName' => $domain ) );
		if ( 'api' === $r['kind'] && in_array( $r['number'], self::NOT_OURS, true ) ) return array( 'kind' => 'ok', 'ours' => false, 'expires' => 0 ) + $r;
		if ( 'ok' !== $r['kind'] ) return $r;
		$res = $r['xml']->CommandResponse->DomainGetInfoResult ?? null;
		$r['ours'] = (bool) $res;
		$r['expires'] = 0;
		if ( $res && isset( $res->DomainDetails->ExpiredDate ) ) {
			$date = (string) $res->DomainDetails->ExpiredDate; // MM/DD/YYYY
			$r['expires'] = preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date, $m ) ? gmmktime( 0, 0, 0, (int) $m[1], (int) $m[2], (int) $m[3] ) : 0;
		}
		return $r;
	}

	/** Registra el dominio a nombre del cliente ($contact: FirstName, LastName, Address1, …), sus cuatro contactos iguales. */
	public function create( $domain, array $contact, $years = 1 ) {
		$params = array( 'DomainName' => $domain, 'Years' => max( 1, (int) $years ) );
		foreach ( array( 'Registrant', 'Tech', 'Admin', 'AuxBilling' ) as $type ) foreach ( $contact as $field => $value ) if ( '' !== (string) $value ) $params[ $type . $field ] = $value;
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
