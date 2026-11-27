<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registradores de dominios (pedido del propietario del 2026-11-27: Dynadot como segundo registrador, junto a Namecheap).
 *
 * Todo lo que Hosting hace con un registrador pasa por esta interfaz: la disponibilidad (una o en lote, el buscador y el
 * carrito), el precio por extensión (precios diarios y el freno de pérdida), si un dominio está en nuestra cuenta, el
 * registro a nombre del cliente, los nameservers, la renovación y el saldo. Cada método devuelve el mismo arreglo que ya
 * usaba el cliente de Namecheap: kind ok|transient|auth|api|config|funds, message y los datos de cada método. transient
 * se reintenta; auth (clave o IP rechazadas) se trata como configuración; lo demás deja el pedido en espera con aviso.
 *
 * Qué registrador atiende cada dominio (Digitalisimo_Hosting_Registrars): el «Registrador predeterminado» de los ajustes
 * de Hosting y, opcional, uno distinto por extensión («Registrador por extensión»: una fila por extensión). Un dominio ya
 * registrado o en transferencia se queda con el registrador que lo tiene (meta `registrar` del servicio y del estado del
 * pedido): cambiar los ajustes nunca manda su renovación a otra cuenta. Un servicio anterior a esta versión es de
 * Namecheap (el único registrador que existía).
 */
interface Digitalisimo_Hosting_Registrar {
	/** namecheap | dynadot. */
	public function id();
	/** El nombre que ven el dueño y las notas del pedido. */
	public function label();
	/** ¿Tiene lo necesario para llamar a la API? (nunca dice cuál es la clave). */
	public function configured();
	/** Saldo disponible en USD: kind ok + value. */
	public function balance( $timeout = 60 );
	/** Costo de un año de la extensión en USD (REGISTER, RENEW o TRANSFER): kind ok + value. */
	public function price( $tld, $action = 'REGISTER', $timeout = 60 );
	/** ¿El dominio está en esta cuenta? kind ok + ours, expires y created (marcas de tiempo o 0). */
	public function info( $domain, $timeout = 60 );
	/** Disponible y premium de un dominio: kind ok + available + premium. */
	public function check( $domain, $timeout = 60 );
	/** Varios dominios: kind ok + results => array( dominio => array( available, premium ) ). */
	public function check_many( array $domains, $timeout = 15 );
	/** Extensiones que atiende: kind ok + tlds => array( 'com' => array( 'transfer' => bool, 'epp' => bool ) ). */
	public function tlds( $timeout = 60 );
	/** Registra a nombre del cliente ($contact con los campos de Digitalisimo_Hosting_Provisioning::contact()). */
	public function create( $domain, array $contact, $years = 1 );
	/** Apunta el dominio a los nameservers propios. */
	public function set_dns( $sld, $tld, array $nameservers );
	/** Renueva el dominio: kind ok + charged (USD, '' si el registrador no lo informa). */
	public function renew( $domain, $years = 1 );
}

require_once __DIR__ . '/class-hosting-namecheap.php';
require_once __DIR__ . '/class-hosting-dynadot.php';

/** Qué registrador atiende cada extensión y cada dominio. */
class Digitalisimo_Hosting_Registrars {
	/** Los registradores que Hosting sabe usar (id => nombre). */
	const IDS = array( 'namecheap' => 'Namecheap', 'dynadot' => 'Dynadot' );
	const DEFAULT_ID = 'namecheap';

	/** Una instancia por registrador y por ajustes (los ajustes pueden cambiar en la misma petición: se comparan). */
	private static $made = array();

	public static function valid( $id ) { return is_string( $id ) && isset( self::IDS[ $id ] ); }
	public static function label( $id ) { return self::IDS[ $id ] ?? self::IDS[ self::DEFAULT_ID ]; }

	/** El cliente del registrador con los ajustes del sitio (o con $o). */
	public static function make( $id, $o = null ) {
		$id = self::valid( $id ) ? $id : self::DEFAULT_ID;
		$o = null === $o ? Digitalisimo_Hosting::settings() : (array) $o;
		$key = $id . '|' . md5( serialize( $o ) );
		if ( ! isset( self::$made[ $key ] ) ) { if ( count( self::$made ) > 8 ) self::$made = array(); self::$made[ $key ] = 'dynadot' === $id ? new Digitalisimo_Hosting_Dynadot( $o ) : new Digitalisimo_Hosting_Namecheap( $o ); }
		return self::$made[ $key ];
	}

	/** El registrador predeterminado de los ajustes. */
	public static function default_id( $o = null ) {
		$o = null === $o ? Digitalisimo_Hosting::settings() : (array) $o;
		$id = (string) ( $o['registrar'] ?? '' );
		return self::valid( $id ) ? $id : self::DEFAULT_ID;
	}

	/** «.mx» desde «mx», «.MX» o « .mx »; '' si no parece una extensión. */
	public static function ext( $raw ) {
		$e = strtolower( trim( (string) $raw ) );
		if ( '' === $e ) return '';
		$e = '.' . ltrim( $e, '.' );
		return preg_match( '/^(\.[a-z0-9-]{2,63}){1,2}$/', $e ) ? $e : '';
	}

	/**
	 * El formato viejo de «Registrador por extensión» (`.mx|dynadot`, una línea por extensión), sólo para la migración.
	 * Devuelve array( map => ext => id, errors => líneas que no se entienden ). Una extensión repetida: manda la última.
	 */
	public static function parse( $raw ) {
		$map = array(); $errors = array();
		foreach ( preg_split( '/\r\n|\r|\n|;/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) continue;
			$parts = array_map( 'trim', preg_split( '/[|:=,\s]+/', $line, 2 ) );
			$ext = self::ext( $parts[0] );
			$id = strtolower( (string) ( $parts[1] ?? '' ) );
			if ( '' === $ext || ! self::valid( $id ) ) { $errors[] = $line; continue; }
			$map[ $ext ] = $id;
		}
		return array( 'map' => $map, 'errors' => $errors );
	}

	/** El texto guardado: sólo las líneas válidas, una por extensión. */
	public static function normalize( $raw ) {
		$lines = array();
		foreach ( self::parse( $raw )['map'] as $ext => $id ) $lines[] = $ext . '|' . $id;
		return implode( "\n", $lines );
	}

	/**
	 * Extensión => registrador que la atiende cuando no es el predeterminado (una fila por extensión en los ajustes). El
	 * texto viejo `.mx|dynadot` lo convirtió la migración; ya no se lee.
	 */
	public static function overrides( $o = null ) {
		$o = null === $o ? Digitalisimo_Hosting::settings() : (array) $o;
		$map = array();
		foreach ( is_array( $o['registrar_by_tld'] ?? null ) ? $o['registrar_by_tld'] : array() as $ext => $id ) { $ext = self::ext( $ext ); if ( '' !== $ext && self::valid( (string) $id ) ) $map[ $ext ] = (string) $id; }
		return $map;
	}

	/** El registrador de una extensión («.mx», «mx», «.com.mx»). */
	public static function id_for_ext( $ext, $o = null ) {
		$ext = self::ext( $ext );
		$map = self::overrides( $o );
		return '' !== $ext && isset( $map[ $ext ] ) ? $map[ $ext ] : self::default_id( $o );
	}

	/** La extensión de un dominio: la de split() (la más larga que se vende) o la más larga configurada por extensión. */
	public static function ext_of( $domain, $o = null ) {
		$domain = strtolower( trim( (string) $domain, ". \t" ) );
		if ( class_exists( 'Digitalisimo_Hosting_Provisioning' ) ) {
			$s = Digitalisimo_Hosting_Provisioning::split( $domain );
			if ( $s ) return $s[2];
		}
		$exts = array_keys( self::overrides( $o ) );
		usort( $exts, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		foreach ( $exts as $e ) if ( strlen( $domain ) > strlen( $e ) && substr( $domain, -strlen( $e ) ) === $e ) return $e;
		$dot = strrpos( $domain, '.' );
		return false === $dot ? '' : substr( $domain, $dot );
	}

	public static function id_for_domain( $domain, $o = null ) { return self::id_for_ext( self::ext_of( $domain, $o ), $o ); }
	public static function for_ext( $ext ) { return self::make( self::id_for_ext( $ext ) ); }
	public static function for_domain( $domain ) { return self::make( self::id_for_domain( $domain ) ); }

	/** Los registradores que tienen su cuenta puesta (id => cliente), en el orden de IDS. */
	public static function configured_all() {
		$out = array();
		foreach ( array_keys( self::IDS ) as $id ) { $r = self::make( $id ); if ( $r->configured() ) $out[ $id ] = $r; }
		return $out;
	}

	/** El registrador de un servicio: el que lo registró o lo recibe en transferencia; antes de esta versión, Namecheap. */
	public static function of_service( $service_id ) {
		$id = (string) get_post_meta( (int) $service_id, '_dhs_registrar', true );
		return self::valid( $id ) ? $id : self::DEFAULT_ID;
	}
}
