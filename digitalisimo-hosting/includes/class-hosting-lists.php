<?php
defined( 'ABSPATH' ) || exit;

/**
 * Las listas de los desplegables del editor de producto y de los ajustes (pedido del propietario del 2026-11-27: «debe
 * mostrar un desplegable dinámico y elegir a cuál corresponde, si .com .net etc.»):
 *  - las extensiones que venden los registradores con cuenta (Namecheap: namecheap.domains.getTldList; Dynadot: la
 *    lista de precios `tld_price`), guardadas un día;
 *  - los paquetes del servidor Webuzo (act=plans, después de la consulta de sólo lectura de cuentas), guardados un día.
 * Sin cuenta, o si el registrador o Webuzo no responden, se usa la última lista guardada o una lista base, con un aviso
 * llano. Nunca un campo de texto libre. Un fallo no se reintenta antes de una hora (la pantalla no se queda esperando).
 */
class Digitalisimo_Hosting_Lists {
	const TLDS  = 'digitalisimo_hosting_tld_list';
	const PLANS = 'digitalisimo_hosting_webuzo_plans';
	/** Extensiones comunes: lo que se ofrece sin conexión con ningún registrador (las populares primero). */
	const BASE_TLDS = array( '.com', '.mx', '.com.mx', '.net', '.lat', '.org', '.info', '.biz', '.co', '.io', '.online', '.store', '.shop', '.site', '.tech', '.app', '.dev', '.xyz', '.us', '.es', '.org.mx', '.net.mx', '.me', '.tv', '.cloud', '.digital', '.agency', '.blog', '.club', '.academy' );
	/** Las que salen arriba en el desplegable. */
	const POPULAR = array( '.com', '.mx', '.com.mx', '.net', '.lat', '.org' );
	const TTL   = DAY_IN_SECONDS;
	const RETRY = HOUR_IN_SECONDS;
	/** Segundos de espera de cada registrador al refrescar la lista desde una pantalla. */
	const TIMEOUT = 10;

	private static $memo = array();

	public static function flush() { self::$memo = array(); }

	private static function stored( $key ) { $v = get_option( $key, array() ); return is_array( $v ) ? $v : array(); }

	/** Ordena: las populares primero y luego por orden alfabético. */
	private static function sort_tlds( array $list ) {
		$list = array_values( array_unique( array_filter( array_map( array( 'Digitalisimo_Hosting_Products', 'normalize' ), $list ) ) ) );
		usort( $list, function ( $a, $b ) {
			$pa = array_search( $a, Digitalisimo_Hosting_Lists::POPULAR, true ); $pb = array_search( $b, Digitalisimo_Hosting_Lists::POPULAR, true );
			if ( false !== $pa || false !== $pb ) return ( false === $pa ? 99 : $pa ) - ( false === $pb ? 99 : $pb );
			return strcmp( $a, $b );
		} );
		return $list;
	}

	/**
	 * Las extensiones para el desplegable: array( list => extensiones, source => registrar|cache|base, notice => texto
	 * llano o '' , at => cuándo se trajo ). $refresh: false = no consultar nunca (sólo lo guardado o la base).
	 */
	public static function tlds( $refresh = true ) {
		if ( isset( self::$memo['tlds'] ) ) return self::$memo['tlds'];
		$s = self::stored( self::TLDS );
		$fresh = ! empty( $s['list'] ) && time() - (int) ( $s['at'] ?? 0 ) < self::TTL;
		$accounts = class_exists( 'Digitalisimo_Hosting_Registrars' ) ? Digitalisimo_Hosting_Registrars::configured_all() : array();
		$error = '';
		if ( ! $fresh && $refresh && $accounts && time() - (int) ( $s['tried'] ?? 0 ) >= self::RETRY ) {
			$s['tried'] = time();
			$got = array(); $from = array();
			foreach ( $accounts as $id => $r ) {
				$t = $r->tlds( self::TIMEOUT );
				if ( 'ok' !== ( $t['kind'] ?? '' ) || empty( $t['tlds'] ) ) continue;
				foreach ( array_keys( (array) $t['tlds'] ) as $name ) $got[] = '.' . ltrim( strtolower( (string) $name ), '.' );
				$from[] = $id;
			}
			if ( $got ) { $s = array( 'list' => self::sort_tlds( $got ), 'at' => time(), 'tried' => time(), 'from' => $from ); $fresh = true; }
			else $error = 'failed';
			update_option( self::TLDS, $s, false );
		} elseif ( ! $fresh && $accounts && ! empty( $s['tried'] ) && empty( $s['at'] ) ) {
			$error = 'failed';
		}
		if ( $fresh ) $out = array( 'list' => $s['list'], 'source' => 'registrar', 'notice' => '', 'at' => (int) $s['at'] );
		elseif ( ! empty( $s['list'] ) ) $out = array( 'list' => $s['list'], 'source' => 'cache', 'notice' => 'No pudimos consultar a los registradores ahora. Se muestra la lista guardada el ' . self::date( (int) $s['at'] ) . '.', 'at' => (int) $s['at'] );
		else $out = array( 'list' => self::BASE_TLDS, 'source' => 'base', 'notice' => $accounts ? 'No pudimos consultar a los registradores ahora. Se muestran las extensiones más comunes; la lista completa aparece cuando respondan.' : 'Todavía no hay cuenta de registrador en Digitalisimo → Hosting. Se muestran las extensiones más comunes.', 'at' => 0 );
		if ( 'failed' === $error && 'registrar' !== $out['source'] && '' === $out['notice'] ) $out['notice'] = 'No pudimos consultar a los registradores ahora.';
		return self::$memo['tlds'] = $out;
	}

	/** ¿La extensión está en la lista (la de los registradores, la guardada o la base)? */
	public static function valid_tld( $ext, $refresh = true ) {
		$ext = Digitalisimo_Hosting_Products::normalize( $ext );
		if ( '' === $ext ) return false;
		$t = self::tlds( $refresh );
		return in_array( $ext, $t['list'], true ) || ( 'registrar' !== $t['source'] && in_array( $ext, self::BASE_TLDS, true ) );
	}

	/**
	 * Los paquetes de Webuzo para el desplegable: array( list => paquete => nombre, source => webuzo|cache|base, notice ).
	 * La base son los paquetes que ya usan los productos y las cuentas que se vieron. Nunca un campo libre.
	 */
	public static function plans( $refresh = true ) {
		if ( isset( self::$memo['plans'] ) ) return self::$memo['plans'];
		$s = self::stored( self::PLANS );
		$fresh = ! empty( $s['list'] ) && time() - (int) ( $s['at'] ?? 0 ) < self::TTL;
		$api = class_exists( 'Digitalisimo_Hosting_Webuzo' ) ? Digitalisimo_Hosting_Webuzo::from_settings() : null;
		$ready = $api && $api->configured() && ! $api->blocked();
		if ( ! $fresh && $refresh && $ready && time() - (int) ( $s['tried'] ?? 0 ) >= self::RETRY ) {
			$s['tried'] = time();
			$r = $api->plans();
			if ( 'ok' === $r['kind'] && ! empty( $r['plans'] ) ) { $s = array( 'list' => $r['plans'], 'at' => time(), 'tried' => time() ); $fresh = true; }
			update_option( self::PLANS, $s, false );
		}
		$used = array();
		if ( class_exists( 'Digitalisimo_Hosting_Products' ) ) foreach ( Digitalisimo_Hosting_Products::catalog()['hosting'] as $h ) if ( '' !== $h['plan'] ) $used[ $h['plan'] ] = $h['plan'];
		if ( $fresh ) $out = array( 'list' => (array) $s['list'] + $used, 'source' => 'webuzo', 'notice' => '' );
		elseif ( ! empty( $s['list'] ) ) $out = array( 'list' => (array) $s['list'] + $used, 'source' => 'cache', 'notice' => 'No pudimos consultar a Webuzo ahora. Se muestran los paquetes guardados el ' . self::date( (int) $s['at'] ) . '.' );
		else $out = array( 'list' => $used, 'source' => 'base', 'notice' => $ready ? 'No pudimos consultar a Webuzo ahora. Se muestran sólo los paquetes que ya usan tus productos; vuelve a abrir esta pantalla en un rato.' : 'Todavía no hay conexión con Webuzo en Digitalisimo → Hosting (URL y clave). Se muestran sólo los paquetes que ya usan tus productos.' );
		return self::$memo['plans'] = $out;
	}

	private static function date( $ts ) { return class_exists( 'Digitalisimo_Hosting_Provisioning' ) ? Digitalisimo_Hosting_Provisioning::date( $ts ) : gmdate( 'Y-m-d', $ts ); }
}
