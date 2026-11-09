<?php
defined( 'ABSPATH' ) || exit;

/**
 * HTTP de los destinos nuevos (S3 y compatibles, WebDAV, Dropbox y pCloud).
 *
 * - Peticiones con cuerpo en memoria (un fragmento): API HTTP de WordPress.
 * - Subidas desde un archivo y descargas a un archivo: cURL en streaming, con memoria constante, latido y cancelación
 *   entre bloques; sin cURL, la API de WordPress (una subida de más de MEMORY_UPLOAD bytes se rechaza con un mensaje
 *   claro, y la descarga usa su modo `stream`).
 * - TLS verificado siempre, con el paquete de certificados de WordPress o una CA propia; sólo se desactiva si la conexión
 *   lo pide de forma explícita (`verify` => false, opción con advertencia en la pantalla).
 * - Nunca se registra ni se devuelve en un mensaje una cabecera de autorización, una URL firmada ni una consulta: los
 *   errores sólo nombran el servidor.
 */
final class Digitalisimo_Backups_Http {
	/**
	 * Sólo pruebas: callable( array $request ) que devuelve array( 'code' => int, 'headers' => array, 'body' => string ).
	 * Con `sink`, el transporte escribe el cuerpo en ese archivo; con `file`, lee el cuerpo de ahí (`offset`, `length`).
	 */
	public static $transport = null;
	/** Sin cURL, una subida desde archivo mayor que esto no se carga en memoria. */
	const MEMORY_UPLOAD = 33554432;

	/**
	 * @param string $method  GET, PUT, POST, DELETE, HEAD, MKCOL, MOVE, PROPFIND…
	 * @param string $url     http(s) solamente.
	 * @param array  $args    headers, body (texto), file + offset + length (cuerpo desde archivo), sink (archivo de
	 *                        destino), timeout, verify (bool), ca (ruta a un PEM), redirection, progress (callable( $hechos,
	 *                        $total ) que puede lanzar para cancelar).
	 * @return array{code:int,headers:array,body:string}
	 */
	public static function request( $method, $url, array $args = array() ) {
		$url = (string) $url;
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === (string) parse_url( $url, PHP_URL_HOST ) ) throw new Exception( 'La dirección del destino no es una URL http(s) válida.' );
		$request = array(
			'method' => strtoupper( (string) $method ),
			'url' => $url,
			'headers' => (array) ( $args['headers'] ?? array() ),
			'body' => (string) ( $args['body'] ?? '' ),
			'file' => (string) ( $args['file'] ?? '' ),
			'offset' => (int) ( $args['offset'] ?? 0 ),
			'length' => isset( $args['length'] ) ? (int) $args['length'] : null,
			'sink' => (string) ( $args['sink'] ?? '' ),
			'timeout' => (int) ( $args['timeout'] ?? 60 ),
			'verify' => ! array_key_exists( 'verify', $args ) || ! empty( $args['verify'] ),
			'ca' => (string) ( $args['ca'] ?? '' ),
			'redirection' => (int) ( $args['redirection'] ?? 0 ),
			'progress' => $args['progress'] ?? null,
		);
		if ( '' !== $request['file'] && null === $request['length'] ) {
			$size = @filesize( $request['file'] );
			if ( false === $size ) throw new Exception( 'No se pudo leer el archivo local para enviarlo.' );
			$request['length'] = max( 0, (int) $size - $request['offset'] );
		}
		if ( self::$transport ) $response = call_user_func( self::$transport, $request );
		elseif ( ( '' !== $request['file'] || '' !== $request['sink'] || ! function_exists( 'wp_remote_request' ) ) && self::curl_available() ) $response = self::curl( $request );
		else $response = self::wp( $request );
		$headers = array();
		foreach ( (array) ( $response['headers'] ?? array() ) as $name => $value ) $headers[ strtolower( (string) $name ) ] = is_array( $value ) ? (string) end( $value ) : (string) $value;
		return array( 'code' => (int) ( $response['code'] ?? 0 ), 'headers' => $headers, 'body' => (string) ( $response['body'] ?? '' ) );
	}

	public static function curl_available() {
		return function_exists( 'curl_init' ) && function_exists( 'curl_exec' ) && function_exists( 'curl_setopt_array' );
	}
	/** Paquete de certificados: la CA propia de la conexión o el de WordPress (el mismo que usa su API HTTP). */
	public static function ca_bundle( $ca ) {
		if ( '' !== (string) $ca ) return (string) $ca;
		if ( defined( 'ABSPATH' ) && defined( 'WPINC' ) && is_readable( ABSPATH . WPINC . '/certificates/ca-bundle.crt' ) ) return ABSPATH . WPINC . '/certificates/ca-bundle.crt';
		return '';
	}
	/** Nombre del servidor para los mensajes: nunca la ruta, la consulta ni las credenciales de la URL. */
	public static function host( $url ) {
		$host = (string) parse_url( (string) $url, PHP_URL_HOST );
		$port = parse_url( (string) $url, PHP_URL_PORT );
		return $host . ( $port ? ':' . (int) $port : '' );
	}
	/** Quita de un mensaje de error la URL completa, consultas, firmas y tokens que pudiera citar. */
	public static function redact( $message, $url = '' ) {
		$message = (string) $message;
		if ( '' !== (string) $url ) $message = str_replace( (string) $url, self::host( $url ), $message );
		$message = preg_replace( '#(https?://[^\s/?"\']+)[^\s"\']*#i', '$1', $message );
		$message = preg_replace( '/(X-Amz-[A-Za-z-]+|access_token|refresh_token|client_secret|code_verifier|Signature|auth)=[^&\s"\']*/i', '$1=…', $message );
		return preg_replace( '/(Bearer|Basic|AWS4-HMAC-SHA256)\s+[^\s"\']+/i', '$1 …', $message );
	}

	/* ------------------------------------------------------------------ API HTTP de WordPress */

	private static function wp( array $request ) {
		if ( ! function_exists( 'wp_remote_request' ) ) throw new Exception( 'La API HTTP de WordPress no está disponible.' );
		$body = $request['body'];
		if ( '' !== $request['file'] ) {
			if ( $request['length'] > self::MEMORY_UPLOAD ) throw new Digitalisimo_Backups_Permanent_Error( 'Este servidor no tiene cURL para PHP: sin él no se puede enviar en streaming un archivo de más de ' . (int) ( self::MEMORY_UPLOAD / 1048576 ) . ' MB a este destino.' );
			$body = (string) @file_get_contents( $request['file'], false, null, $request['offset'], max( 0, (int) $request['length'] ) );
			if ( strlen( $body ) !== (int) $request['length'] ) throw new Exception( 'No se pudo leer el archivo local para enviarlo.' );
		}
		$args = array( 'method' => $request['method'], 'headers' => $request['headers'], 'body' => $body, 'timeout' => $request['timeout'], 'redirection' => $request['redirection'], 'sslverify' => $request['verify'], 'user-agent' => self::agent() );
		if ( '' !== $request['ca'] ) $args['sslcertificates'] = $request['ca'];
		if ( '' !== $request['sink'] ) { $args['stream'] = true; $args['filename'] = $request['sink']; }
		if ( '' === $body && in_array( $request['method'], array( 'GET', 'HEAD', 'DELETE' ), true ) ) unset( $args['body'] );
		$response = wp_remote_request( $request['url'], $args );
		if ( is_wp_error( $response ) ) throw new Exception( 'No se pudo conectar con ' . self::host( $request['url'] ) . ': ' . self::redact( $response->get_error_message(), $request['url'] ) );
		$headers = wp_remote_retrieve_headers( $response );
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) $headers = $headers->getAll();
		return array( 'code' => (int) wp_remote_retrieve_response_code( $response ), 'headers' => (array) $headers, 'body' => '' !== $request['sink'] ? '' : (string) wp_remote_retrieve_body( $response ) );
	}

	/* ------------------------------------------------------------------ cURL en streaming */

	private static function curl( array $request ) {
		$handle = curl_init( $request['url'] );
		if ( ! $handle ) throw new Exception( 'No se pudo iniciar cURL.' );
		$headers = array();
		$lines = array();
		foreach ( $request['headers'] as $name => $value ) $lines[] = $name . ': ' . $value;
		$options = array(
			CURLOPT_CUSTOMREQUEST => $request['method'],
			CURLOPT_HTTPHEADER => $lines,
			CURLOPT_TIMEOUT => max( 0, (int) $request['timeout'] ),
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => $request['redirection'] > 0,
			CURLOPT_MAXREDIRS => max( 0, (int) $request['redirection'] ),
			CURLOPT_SSL_VERIFYPEER => $request['verify'],
			CURLOPT_SSL_VERIFYHOST => $request['verify'] ? 2 : 0,
			CURLOPT_USERAGENT => self::agent(),
			CURLOPT_HEADERFUNCTION => function( $curl, $line ) use ( &$headers ) {
				$parts = explode( ':', $line, 2 );
				if ( 2 === count( $parts ) ) $headers[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
				elseif ( 0 === stripos( $line, 'HTTP/' ) ) $headers = array();
				return strlen( $line );
			},
		);
		if ( defined( 'CURLOPT_PROTOCOLS' ) && defined( 'CURLPROTO_HTTP' ) ) $options[ CURLOPT_PROTOCOLS ] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
		if ( defined( 'CURLOPT_REDIR_PROTOCOLS' ) && defined( 'CURLPROTO_HTTP' ) ) $options[ CURLOPT_REDIR_PROTOCOLS ] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
		$bundle = self::ca_bundle( $request['ca'] );
		if ( $request['verify'] && '' !== $bundle ) $options[ CURLOPT_CAINFO ] = $bundle;
		self::proxy( $request['url'], $options );
		$failure = null;
		$progress = $request['progress'];
		if ( is_callable( $progress ) ) {
			$options[ CURLOPT_NOPROGRESS ] = false;
			$options[ CURLOPT_PROGRESSFUNCTION ] = function( $curl, $down_total, $down, $up_total, $up ) use ( $progress, &$failure ) {
				try {
					call_user_func( $progress, max( (int) $down, (int) $up ), max( (int) $down_total, (int) $up_total ) );
				} catch ( Throwable $e ) {
					$failure = $e;
					return 1;
				}
				return 0;
			};
		}
		$in = null;
		$out = null;
		if ( '' !== $request['file'] ) {
			$in = @fopen( $request['file'], 'rb' );
			if ( ! $in || ( $request['offset'] > 0 && 0 !== fseek( $in, $request['offset'] ) ) ) { if ( $in ) fclose( $in ); self::close( $handle ); throw new Exception( 'No se pudo leer el archivo local para enviarlo.' ); }
			$left = (int) $request['length'];
			$options[ CURLOPT_UPLOAD ] = true;
			$options[ CURLOPT_INFILESIZE ] = $left;
			$options[ CURLOPT_READFUNCTION ] = function( $curl, $stream, $max ) use ( $in, &$left ) {
				if ( $left <= 0 ) return '';
				$data = fread( $in, min( $max, $left ) );
				if ( false === $data ) return '';
				$left -= strlen( $data );
				return $data;
			};
		} elseif ( '' !== $request['body'] ) {
			$options[ CURLOPT_POSTFIELDS ] = $request['body'];
		}
		if ( 'HEAD' === $request['method'] ) $options[ CURLOPT_NOBODY ] = true;
		if ( '' !== $request['sink'] ) {
			$out = @fopen( $request['sink'], 'wb' );
			if ( ! $out ) { if ( $in ) fclose( $in ); self::close( $handle ); throw new Exception( 'No se pudo preparar el archivo temporal de la descarga.' ); }
			$options[ CURLOPT_FILE ] = $out;
		} else {
			$options[ CURLOPT_RETURNTRANSFER ] = true;
		}
		curl_setopt_array( $handle, $options );
		$body = curl_exec( $handle );
		$errno = curl_errno( $handle );
		$error = curl_error( $handle );
		$code = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		self::close( $handle );
		if ( $in ) fclose( $in );
		if ( $out ) fclose( $out );
		if ( $failure ) throw $failure;
		if ( $errno ) throw new Exception( 'No se pudo conectar con ' . self::host( $request['url'] ) . ': ' . self::redact( $error, $request['url'] ) );
		return array( 'code' => $code, 'headers' => $headers, 'body' => is_string( $body ) ? $body : '' );
	}
	/** curl_close() no hace nada desde PHP 8.0 y está obsoleto en 8.5. */
	private static function close( $handle ) { if ( PHP_VERSION_ID < 80000 ) curl_close( $handle ); }
	/** El proxy de wp-config.php (WP_PROXY_*), como la API HTTP de WordPress. */
	private static function proxy( $url, array &$options ) {
		if ( ! class_exists( 'WP_HTTP_Proxy' ) ) return;
		$proxy = new WP_HTTP_Proxy();
		if ( ! $proxy->is_enabled() || ! $proxy->send_through_proxy( $url ) ) return;
		$options[ CURLOPT_PROXY ] = $proxy->host();
		$options[ CURLOPT_PROXYPORT ] = $proxy->port();
		if ( $proxy->use_authentication() ) $options[ CURLOPT_PROXYUSERPWD ] = $proxy->authentication();
	}
	private static function agent() {
		return 'DIGITALISIMO-Backups/' . ( defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : 'dev' );
	}
}
