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

	/**
	 * Pide una URL pública, sin sesión, saltando cachés de página (parámetro único, cabeceras y la cookie de un
	 * comentarista, que las cachés de página habituales no sirven desde caché) y siguiendo a mano hasta 3 redirecciones
	 * del mismo sitio. Una redirección al acceso (wp-login.php, wp-admin) no se sigue: devuelve [código, '', 'login'].
	 *
	 * @return array{0:int,1:string,2?:string}|\WP_Error
	 */
	public static function fetch( $url ) {
		if ( is_callable( self::$fetcher ) ) {
			return call_user_func( self::$fetcher, $url );
		}
		$url = add_query_arg( 'digitalisimo-migration-check', (string) microtime( true ), $url );
		// Las cookies que pone una redirección (consentimiento, idioma) viajan al salto siguiente, como en un navegador.
		$jar = array( 'comment_author_digitalisimo_migration' => '1' );
		for ( $hop = 0; $hop <= 3; $hop++ ) {
			$response = wp_remote_get( $url, array(
				'timeout'     => 20,
				'redirection' => 0,
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ),
				'headers'     => array( 'Cache-Control' => 'no-cache, no-store', 'Pragma' => 'no-cache' ),
				'cookies'     => $jar,
			) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			foreach ( (array) wp_remote_retrieve_cookies( $response ) as $cookie ) {
				if ( is_object( $cookie ) && isset( $cookie->name ) ) {
					$jar[ (string) $cookie->name ] = (string) $cookie->value;
				}
			}
			$code     = (int) wp_remote_retrieve_response_code( $response );
			$location = (string) wp_remote_retrieve_header( $response, 'location' );
			if ( $code < 300 || $code >= 400 || '' === $location ) {
				return array( $code, (string) wp_remote_retrieve_body( $response ) );
			}
			if ( preg_match( '#wp-login\.php|/wp-admin(/|$)#', $location ) ) {
				return array( $code, '', 'login' );
			}
			$url = 0 === strpos( $location, '/' ) ? home_url( $location ) : $location;
		}
		return array( $code, '' );
	}

	/**
	 * Huella de una respuesta: código, si muestra un error de PHP, los temas cuyos archivos enlaza y las palabras visibles
	 * del cuerpo. Con $limit, una muestra de ese tamaño sin sesgo (por el hash de cada palabra, no por orden alfabético);
	 * con 0, todas. Pura.
	 */
	public static function fingerprint( $status, $body, $limit = self::WORDS ) {
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
		usort( $words, static function ( $a, $b ) {
			return strcmp( md5( $a ), md5( $b ) );
		} );
		preg_match_all( '#/wp-content/themes/([A-Za-z0-9._-]+)/#', $body, $themes );
		return array( 'status' => (int) $status, 'fatal' => $fatal, 'themes' => array_values( array_unique( $themes[1] ) ), 'words' => $limit > 0 ? array_slice( $words, 0, (int) $limit ) : $words );
	}

	/** Huella de una URL (con `error` si no respondió y `login` si redirige al acceso). Con $limit 0, todas las palabras. */
	public static function snapshot( $url, $limit = self::WORDS ) {
		$result = self::fetch( $url );
		if ( is_wp_error( $result ) ) {
			return array( 'status' => 0, 'fatal' => false, 'words' => array(), 'error' => $result->get_error_message() );
		}
		return self::fingerprint( $result[0], $result[1], $limit ) + ( 'login' === ( $result[2] ?? '' ) ? array( 'login' => true ) : array() );
	}

	/**
	 * Marca como no comparables las URL de un sitio que dan todas el mismo texto (una página de mantenimiento, de
	 * «próximamente», un muro de acceso o un reto de un cortafuegos): ahí no se ve el sitio. Con una sola URL no se puede
	 * saber. Pura: recibe y devuelve huellas.
	 *
	 * @param array<string,array> $snaps
	 * @return array<string,array>
	 */
	public static function mark_uniform( array $snaps ) {
		$seen = array();
		foreach ( $snaps as $key => $snap ) {
			$words = (array) ( $snap['words'] ?? array() );
			sort( $words );
			$seen[ $key ] = md5( implode( ' ', $words ) );
		}
		if ( count( $snaps ) >= 2 && 1 === count( array_unique( $seen ) ) ) {
			foreach ( array_keys( $snaps ) as $key ) {
				$snaps[ $key ]['uniform'] = true;
			}
		}
		return $snaps;
	}

	/**
	 * ¿Responde igual? Pura. Una URL que antes no respondía bien no se puede comparar (null). Devuelve [ok|null, motivo].
	 *
	 * @return array{0:?bool,1:string}
	 */
	public static function compare( array $before, array $after, $theme = '' ) {
		$was = (int) ( $before['status'] ?? 0 );
		if ( ! empty( $before['login'] ) ) {
			return array( null, 'Antes de migrar pedía iniciar sesión: no se ve el sitio.' );
		}
		if ( $was < 200 || $was >= 300 || ! empty( $before['fatal'] ) ) {
			return array( null, 'Antes de migrar no respondía bien' . ( ! empty( $before['error'] ) ? ' (' . $before['error'] . ')' : ' (HTTP ' . $was . ')' ) . '.' );
		}
		if ( ! empty( $before['uniform'] ) ) {
			return array( null, 'Antes de migrar todas sus páginas daban el mismo texto (mantenimiento, «próximamente», acceso o un cortafuegos): no se ve el sitio.' );
		}
		if ( ! (array) ( $before['words'] ?? array() ) ) {
			return array( null, 'Antes de migrar no tenía texto visible que comparar.' );
		}
		$now = (int) ( $after['status'] ?? 0 );
		if ( $now < 200 || $now >= 300 ) {
			return array( false, ! empty( $after['error'] ) ? 'No respondió: ' . $after['error'] : sprintf( 'Respondía HTTP %d y ahora HTTP %d.', $was, $now ) );
		}
		if ( ! empty( $after['fatal'] ) ) {
			return array( false, 'Muestra un error de PHP.' );
		}
		if ( '' !== (string) $theme && ! in_array( (string) $theme, (array) ( $after['themes'] ?? array() ), true ) ) {
			// Tras cambiar de tema, el HTML tiene que enlazar sus archivos: si no, llegó de una caché de antes.
			return array( false, 'No enlaza los archivos del tema nuevo: la respuesta parece venir de una caché de antes de migrar.' );
		}
		$old = (array) ( $before['words'] ?? array() );
		$kept  = count( array_intersect( $old, (array) ( $after['words'] ?? array() ) ) );
		$share = $kept / count( $old );
		if ( $share < self::OVERLAP ) {
			return array( false, sprintf( 'Sólo conserva el %d %% del texto visible que tenía.', (int) floor( $share * 100 ) ) );
		}
		return array( true, '' );
	}
}
