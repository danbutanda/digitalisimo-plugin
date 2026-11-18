<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * ¿Responde igual el sitio? Antes de tocar nada, Migrar guarda la huella de la portada y de unas pocas páginas y
 * entradas publicadas (código HTTP y palabras visibles del cuerpo); al final las vuelve a pedir y las compara. Es la
 * última barrera antes de borrar: si una no responde, muestra un error de PHP o pierde contenido visible, no se borra
 * nada. No compara el marcado (cambia, a propósito, al convertir widgets o cambiar de tema) sino lo que se lee.
 */
final class Front {
	/** Páginas y entradas además de la portada. */
	const PAGES = 3;
	/** Palabras guardadas por URL y fracción que debe seguir apareciendo. */
	const WORDS   = 400;
	const OVERLAP = 0.8;
	/** Textos de un error de PHP o de la pantalla de error crítico de WordPress. */
	const FATAL = array( 'Fatal error', 'Parse error', 'There has been a critical error', 'Ha habido un error crítico', 'Ha ocurrido un error crítico' );

	/** @var callable|null pruebas: sustituye la petición (url) y devuelve array( status, body ) o WP_Error. */
	public static $fetcher = null;

	/** URLs del sitio en curso que se comparan: portada y las páginas y entradas publicadas más recientes. */
	public static function urls() {
		$urls = array( home_url( '/' ) );
		if ( function_exists( 'get_posts' ) ) {
			$ids = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => self::PAGES, 'orderby' => 'modified', 'order' => 'DESC', 'fields' => 'ids', 'has_password' => false, 'suppress_filters' => true ) );
			foreach ( (array) $ids as $id ) {
				$link = get_permalink( (int) $id );
				if ( is_string( $link ) && '' !== $link ) {
					$urls[] = $link;
				}
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/** Pide una URL pública, sin sesión y saltando cachés de página. */
	public static function fetch( $url ) {
		if ( is_callable( self::$fetcher ) ) {
			return call_user_func( self::$fetcher, $url );
		}
		$response = wp_remote_get( add_query_arg( 'digitalisimo-migration-check', (string) time(), $url ), array(
			'timeout'     => 20,
			'redirection' => 3,
			'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ),
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array( (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ) );
	}

	/** Huella de una respuesta: código, si muestra un error de PHP y las palabras visibles del cuerpo (ordenadas). Pura. */
	public static function fingerprint( $status, $body ) {
		$body  = (string) $body;
		$fatal = false;
		foreach ( self::FATAL as $needle ) {
			$fatal = $fatal || false !== stripos( $body, $needle );
		}
		$text = preg_replace( '#<head\b.*?</head>#is', ' ', $body );
		$text = preg_replace( '#<(script|style|noscript|svg|template)\b.*?</\1>#is', ' ', (string) $text );
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		preg_match_all( '/[\p{L}\p{N}]{3,}/u', $text, $match );
		$words = array_values( array_unique( $match[0] ) );
		sort( $words );
		return array( 'status' => (int) $status, 'fatal' => $fatal, 'words' => array_slice( $words, 0, self::WORDS ) );
	}

	/** Huella de una URL (con `error` si no respondió). */
	public static function snapshot( $url ) {
		$result = self::fetch( $url );
		if ( is_wp_error( $result ) ) {
			return array( 'status' => 0, 'fatal' => false, 'words' => array(), 'error' => $result->get_error_message() );
		}
		return self::fingerprint( $result[0], $result[1] );
	}

	/**
	 * ¿Responde igual? Pura. Una URL que antes no respondía bien no se puede comparar (null). Devuelve [ok|null, motivo].
	 *
	 * @return array{0:?bool,1:string}
	 */
	public static function compare( array $before, array $after ) {
		$was = (int) ( $before['status'] ?? 0 );
		if ( $was < 200 || $was >= 300 || ! empty( $before['fatal'] ) ) {
			return array( null, 'Antes de migrar no respondía bien' . ( ! empty( $before['error'] ) ? ' (' . $before['error'] . ')' : ' (HTTP ' . $was . ')' ) . '.' );
		}
		$now = (int) ( $after['status'] ?? 0 );
		if ( $now < 200 || $now >= 300 ) {
			return array( false, ! empty( $after['error'] ) ? 'No respondió: ' . $after['error'] : sprintf( 'Respondía HTTP %d y ahora HTTP %d.', $was, $now ) );
		}
		if ( ! empty( $after['fatal'] ) ) {
			return array( false, 'Muestra un error de PHP.' );
		}
		$old = (array) ( $before['words'] ?? array() );
		if ( ! $old ) {
			return array( true, '' );
		}
		$kept  = count( array_intersect( $old, (array) ( $after['words'] ?? array() ) ) );
		$share = $kept / count( $old );
		if ( $share < self::OVERLAP ) {
			return array( false, sprintf( 'Sólo conserva el %d %% del texto visible que tenía.', (int) floor( $share * 100 ) ) );
		}
		return array( true, '' );
	}
}
