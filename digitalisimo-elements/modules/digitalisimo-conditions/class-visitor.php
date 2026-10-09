<?php
namespace Digitalisimo\Elements\Conditions;

defined( 'ABSPATH' ) || exit;

/** Datos de la petición en curso, con filtros para servidores, proxies y pruebas. */
final class Visitor {
	const TIMEZONE_COOKIE = 'ep_visitor_tz';

	private static $timezone_script = false;

	public static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	public static function referer_host() {
		$referer = wp_get_raw_referer();
		$host    = $referer ? wp_parse_url( $referer, PHP_URL_HOST ) : '';
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	public static function request_uri() {
		return isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
	}

	public static function browser_language() {
		$header = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		return preg_match( '/^\s*([a-z]{2,3})\b/i', $header, $match ) ? strtolower( $match[1] ) : '';
	}

	/**
	 * País ISO alfa-2 en mayúsculas, o '' si no se conoce. Orden: fuente propia del sitio,
	 * cabecera del CDN o del servidor, zona horaria del navegador y, por último, la IP.
	 * Respeta los filtros `element_pack_visibility_*` para que una configuración previa de
	 * Element Pack siga funcionando igual. No sirve para control de acceso: las cabeceras,
	 * la cookie y la IP reenviada pueden falsificarse.
	 */
	public static function country() {
		self::$timezone_script = true;
		$ip      = self::client_ip();
		$country = apply_filters( 'digitalisimo_elements_visitor_country', null );
		if ( null === $country ) {
			$country = apply_filters( 'element_pack_visibility_country_pre', null, $ip );
		}
		if ( null === $country ) {
			$country = self::header_country();
			if ( '' === $country && apply_filters( 'element_pack_visibility_country_use_timezone', true ) ) {
				$country = self::timezone_country();
			}
			if ( '' === $country && '' !== $ip && apply_filters( 'digitalisimo_elements_country_remote_lookup', true ) ) {
				$country = self::ip_country( $ip );
			}
		}
		$country = strtoupper( trim( (string) $country ) );
		return preg_match( '/^[A-Z]{2}$/', $country ) && ! in_array( $country, array( 'XX', 'T1', 'ZZ' ), true ) ? $country : '';
	}

	private static function header_country() {
		foreach ( array( 'HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_X_APPENGINE_COUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_COUNTRY_CODE' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}
		return '';
	}

	/** La zona horaria del sistema sobrevive a una VPN; se valida contra la lista IANA. */
	private static function timezone_country() {
		if ( empty( $_COOKIE[ self::TIMEZONE_COOKIE ] ) ) {
			return '';
		}
		$zone = sanitize_text_field( wp_unslash( $_COOKIE[ self::TIMEZONE_COOKIE ] ) );
		if ( ! in_array( $zone, timezone_identifiers_list(), true ) ) {
			return '';
		}
		try {
			$location = ( new \DateTimeZone( $zone ) )->getLocation();
		} catch ( \Exception $e ) {
			return '';
		}
		return is_array( $location ) && ! empty( $location['country_code'] ) && '??' !== $location['country_code'] ? $location['country_code'] : '';
	}

	private static function client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( $parts[0] );
		}
		if ( '' === $ip && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		$ip = apply_filters( 'element_pack_visibility_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? (string) $ip : '';
	}

	/**
	 * Consulta por IP con la misma caché por IP que usaba Element Pack (aciertos 12 h, fallos 15 min),
	 * de modo que un sitio migrado no repite consultas que ya tenía resueltas.
	 */
	private static function ip_country( $ip ) {
		$cache_key = 'ep_geo_cc_' . md5( $ip );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}
		$endpoint = apply_filters( 'element_pack_visibility_country_endpoint', 'https://ipwho.is/' . rawurlencode( $ip ), $ip );
		$response = wp_remote_get( $endpoint, array( 'timeout' => 3 ) );
		$country  = '';
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$country = apply_filters(
				'element_pack_visibility_country_parse',
				is_array( $body ) && ! empty( $body['success'] ) && isset( $body['country_code'] ) ? (string) $body['country_code'] : '',
				$body,
				$ip
			);
		}
		$country = strtolower( trim( (string) $country ) );
		$ttl     = apply_filters( 'element_pack_visibility_country_cache_ttl', '' === $country ? 15 * MINUTE_IN_SECONDS : 12 * HOUR_IN_SECONDS, $country, $ip );
		set_transient( $cache_key, $country, $ttl );
		return $country;
	}

	/** Sólo las páginas que evaluaron un país guardan la zona horaria para la petición siguiente. */
	public static function print_timezone_script() {
		if ( ! self::$timezone_script || is_admin() || ! apply_filters( 'element_pack_visibility_tz_cookie', true ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo detecta la vista previa del editor.
		if ( ! empty( $_GET['elementor-preview'] ) ) {
			return;
		}
		echo '<script id="digitalisimo-visitor-tz">(function(){try{var z=Intl.DateTimeFormat().resolvedOptions().timeZone;if(z&&/^[A-Za-z0-9_\-\/+]+$/.test(z)){document.cookie="' . esc_js( self::TIMEZONE_COOKIE ) . '="+encodeURIComponent(z)+";path=/;max-age=2592000;SameSite=Lax";}}catch(e){}})();</script>' . "\n";
	}
}
