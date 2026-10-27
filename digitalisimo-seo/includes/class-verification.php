<?php
defined( 'ABSPATH' ) || exit;

/**
 * Códigos de verificación de buscadores (RM5 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Un campo por buscador; el valor admite el código o la etiqueta completa, de la que sólo se
 * conserva `content`. Las etiquetas propias se piden como «nombre|contenido» y sólo producen
 * `<meta name>`: nunca HTML libre. Se imprimen únicamente en la portada.
 */
class Digitalisimo_Integrations_Verification {
	const CUSTOM = 'seo_verify_custom';

	/** Clave de ajuste => array( etiqueta, nombre de la meta ). */
	public static function engines() {
		return array(
			'seo_verify_google'    => array( 'Google Search Console', 'google-site-verification' ),
			'seo_verify_bing'      => array( 'Bing Webmaster Tools', 'msvalidate.01' ),
			'seo_verify_yandex'    => array( 'Yandex Webmaster', 'yandex-verification' ),
			'seo_verify_baidu'     => array( 'Baidu', 'baidu-site-verification' ),
			'seo_verify_pinterest' => array( 'Pinterest', 'p:domain_verify' ),
			'seo_verify_norton'    => array( 'Norton Safe Web', 'norton-safeweb-site-verification' ),
		);
	}

	public static function keys() { return array_merge( array_keys( self::engines() ), array( self::CUSTOM ) ); }

	public static function init() { add_action( 'wp_head', array( __CLASS__, 'output' ), 2 ); }

	/** Código limpio: de una etiqueta pegada sólo queda su `content`. Vacío si no parece un código. */
	public static function sanitize_code( $value ) {
		$value = trim( is_scalar( $value ) ? (string) $value : '' );
		if ( preg_match( '/content\s*=\s*["\']([^"\']*)["\']/i', $value, $match ) ) $value = $match[1];
		$value = trim( $value );
		return preg_match( '/^[A-Za-z0-9_\-=:.+\/]{1,200}$/', $value ) ? $value : '';
	}

	/** Líneas «nombre|contenido» válidas; el resto se descarta. */
	public static function sanitize_custom( $value ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', is_scalar( $value ) ? (string) $value : '' ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 !== count( $parts ) ) continue;
			$name = strtolower( $parts[0] );
			$code = self::sanitize_code( $parts[1] );
			if ( preg_match( '/^[a-z0-9:._-]{1,64}$/', $name ) && '' !== $code ) $out[ $name ] = $name . '|' . $code;
		}
		return implode( "\n", array_slice( array_values( $out ), 0, 20 ) );
	}

	/** Pares nombre => contenido que se publican, con los valores efectivos (sitio → red → default). */
	public static function tags() {
		$tags = array();
		foreach ( self::engines() as $key => $engine ) {
			$code = self::sanitize_code( Digitalisimo_Integrations_SEO_Resolver::option( $key, '' ) );
			if ( '' !== $code ) $tags[ $engine[1] ] = $code;
		}
		foreach ( preg_split( '/\r\n|\r|\n/', self::sanitize_custom( Digitalisimo_Integrations_SEO_Resolver::option( self::CUSTOM, '' ) ) ) as $line ) {
			if ( '' === $line ) continue;
			list( $name, $code ) = explode( '|', $line, 2 );
			if ( ! isset( $tags[ $name ] ) ) $tags[ $name ] = $code;
		}
		return $tags;
	}

	public static function output() {
		if ( is_admin() || ! is_front_page() || is_paged() ) return;
		foreach ( self::tags() as $name => $code ) echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $code ) . '">' . "\n";
	}
}
