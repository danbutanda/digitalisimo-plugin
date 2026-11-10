<?php
/**
 * Servidores simulados de los destinos remotos para las pruebas, fieles a la documentación de cada API (consultada el
 * 2026-11-09): S3 (SigV4 comprobada en cada petición, x-amz-content-sha256 contra el cuerpo, multipart, CopyObject,
 * ListObjectsV2, 200 con <Error>), Dropbox (OAuth, upload sessions con incorrect_offset y not_found, content_hash,
 * move_v2, list_folder), pCloud (uploadfile por PUT, checksumfile por región, renamefile, getfilelink), WebDAV (MKCOL,
 * PUT, GET, MOVE con Overwrite, PROPFIND 207, DELETE) y FTP (servidor en proceso como envoltorio de flujos).
 * Se enchufan con Digitalisimo_Backups_Http::$transport y Digitalisimo_Backups_Ftp_Client::$connector/$tls.
 */

/** Lee el cuerpo de una petición del transporte (texto o rango de archivo). */
function bk_fake_body( array $request ) {
	if ( '' !== (string) ( $request['file'] ?? '' ) ) return (string) file_get_contents( $request['file'], false, null, (int) $request['offset'], (int) $request['length'] );
	return (string) ( $request['body'] ?? '' );
}
/**
 * GET con `Range: bytes=a-b` (RFC 7233), como S3, WebDAV y los servicios con descarga por rangos: 206 con `Content-Range`,
 * 416 si empieza después del final; sin `Range`, 200 con todo. Devuelve array( código, cuerpo, cabeceras ).
 */
function bk_fake_range( array $request, $data ) {
	$range = '';
	foreach ( (array) $request['headers'] as $name => $value ) if ( 'range' === strtolower( (string) $name ) ) $range = (string) $value;
	if ( ! preg_match( '/^bytes=(\d+)-(\d*)$/', $range, $m ) ) return array( 200, $data, array() );
	$total = strlen( $data );
	$start = (int) $m[1];
	if ( $start >= $total ) return array( 416, '', array( 'Content-Range' => 'bytes */' . $total ) );
	$end = '' === $m[2] ? $total - 1 : min( $total - 1, (int) $m[2] );
	return array( 206, (string) substr( $data, $start, $end - $start + 1 ), array( 'Content-Range' => 'bytes ' . $start . '-' . $end . '/' . $total ) );
}
/** Respuesta del transporte; con `sink`, el cuerpo va al archivo. */
function bk_fake_response( array $request, $code, $body = '', array $headers = array() ) {
	if ( '' !== (string) ( $request['sink'] ?? '' ) ) { file_put_contents( $request['sink'], $body ); $body = ''; }
	return array( 'code' => $code, 'headers' => $headers, 'body' => $body );
}

/* ====================================================================== S3 */

final class Bk_Fake_S3 {
	public $objects = array();
	public $uploads = array();
	public $log = array();
	public $secret;
	public $bucket;
	public $region;
	/** Fallos a inyectar: corrupt_get, complete_error, copy_error, fail_part (número: lanza como un corte de red), forget_upload. */
	public $faults = array();
	public function __construct( $bucket, $secret, $region ) { $this->bucket = $bucket; $this->secret = $secret; $this->region = $region; }
	public function __invoke( array $request ) {
		$parts = parse_url( $request['url'] );
		$host = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		$path = (string) ( $parts['path'] ?? '/' );
		parse_str( (string) ( $parts['query'] ?? '' ), $query );
		$headers = array_change_key_case( (array) $request['headers'], CASE_LOWER );
		$body = bk_fake_body( $request );
		$this->log[] = array( 'method' => $request['method'], 'host' => $host, 'path' => $path, 'query' => $query, 'headers' => $headers, 'bytes' => strlen( $body ) );
		// SigV4: la firma se recalcula con los datos tal como llegaron (URI y consulta del cable).
		if ( ! preg_match( '#^AWS4-HMAC-SHA256 Credential=([^/]+)/(\d{8})/([^/]+)/s3/aws4_request, SignedHeaders=([a-z0-9;-]+), Signature=([0-9a-f]{64})$#', (string) ( $headers['authorization'] ?? '' ), $auth ) ) return $this->error( $request, 403, 'AccessDenied', 'Sin firma SigV4' );
		if ( $auth[3] !== $this->region ) return $this->error( $request, 400, 'AuthorizationHeaderMalformed', 'Región ' . $auth[3] );
		if ( ! hash_equals( hash( 'sha256', $body ), (string) ( $headers['x-amz-content-sha256'] ?? '' ) ) ) return $this->error( $request, 400, 'XAmzContentSHA256Mismatch', 'El cuerpo no coincide' );
		$sign_headers = array();
		foreach ( explode( ';', $auth[4] ) as $name ) if ( ! in_array( $name, array( 'host', 'x-amz-date', 'x-amz-content-sha256' ), true ) ) $sign_headers[ $name ] = $headers[ $name ] ?? '';
		$raw_query = array();
		foreach ( explode( '&', (string) ( $parts['query'] ?? '' ) ) as $pair ) { if ( '' === $pair ) continue; $kv = explode( '=', $pair, 2 ); $raw_query[ rawurldecode( $kv[0] ) ] = rawurldecode( $kv[1] ?? '' ); }
		$time = DateTime::createFromFormat( 'Ymd\THis\Z', (string) $headers['x-amz-date'], new DateTimeZone( 'UTC' ) )->getTimestamp();
		$expected = Digitalisimo_Backups_Destination_S3_Base::sign( $request['method'], $host, $path, $raw_query, $sign_headers, $headers['x-amz-content-sha256'], $auth[1], $this->secret, $auth[3], $time );
		if ( $expected['headers']['authorization'] !== $headers['authorization'] ) return $this->error( $request, 403, 'SignatureDoesNotMatch', 'Firma distinta' );
		// Bucket y clave según el estilo.
		if ( 0 === strpos( $parts['host'], $this->bucket . '.' ) ) $key = rawurldecode( ltrim( $path, '/' ) );
		else {
			$segments = explode( '/', ltrim( $path, '/' ), 2 );
			if ( rawurldecode( $segments[0] ) !== $this->bucket ) return $this->error( $request, 404, 'NoSuchBucket', 'No existe' );
			$key = rawurldecode( $segments[1] ?? '' );
		}
		$method = $request['method'];
		if ( 'GET' === $method && '' === $key && isset( $query['list-type'] ) ) return $this->list( $request, $query );
		if ( 'POST' === $method && array_key_exists( 'uploads', $query ) ) {
			$id = 'up-' . bin2hex( random_bytes( 6 ) );
			$this->uploads[ $id ] = array( 'key' => $key, 'parts' => array() );
			return bk_fake_response( $request, 200, '<?xml version="1.0" encoding="UTF-8"?><InitiateMultipartUploadResult><Bucket>' . $this->bucket . '</Bucket><Key>' . htmlspecialchars( $key, ENT_XML1 ) . '</Key><UploadId>' . $id . '</UploadId></InitiateMultipartUploadResult>' );
		}
		if ( isset( $query['uploadId'] ) ) {
			$id = $query['uploadId'];
			if ( ! empty( $this->faults['forget_upload'] ) ) { unset( $this->uploads[ $id ] ); $this->faults['forget_upload'] = false; }
			if ( ! isset( $this->uploads[ $id ] ) || $this->uploads[ $id ]['key'] !== $key ) return $this->error( $request, 404, 'NoSuchUpload', 'No existe la subida' );
			if ( 'PUT' === $method ) {
				$number = (int) $query['partNumber'];
				if ( isset( $headers['x-amz-copy-source'] ) ) {
					$source = rawurldecode( preg_replace( '#^/' . preg_quote( $this->bucket, '#' ) . '/#', '', $headers['x-amz-copy-source'] ) );
					preg_match( '/^bytes=(\d+)-(\d+)$/', (string) $headers['x-amz-copy-source-range'], $range );
					$this->uploads[ $id ]['parts'][ $number ] = substr( $this->objects[ $source ], (int) $range[1], (int) $range[2] - (int) $range[1] + 1 );
					return bk_fake_response( $request, 200, '<CopyPartResult><ETag>"' . md5( $this->uploads[ $id ]['parts'][ $number ] ) . '"</ETag></CopyPartResult>' );
				}
				if ( isset( $this->faults['fail_part'] ) && (int) $this->faults['fail_part'] === $number ) { unset( $this->faults['fail_part'] ); throw new Exception( 'cURL error 56: corte simulado' ); }
				$this->uploads[ $id ]['parts'][ $number ] = $body;
				return bk_fake_response( $request, 200, '', array( 'ETag' => '"' . md5( $body ) . '"' ) );
			}
			if ( 'POST' === $method ) {
				if ( ! empty( $this->faults['complete_error'] ) ) { $this->faults['complete_error'] = false; return bk_fake_response( $request, 200, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n\n<Error><Code>InternalError</Code><Message>We encountered an internal error. Please try again.</Message></Error>" ); }
				preg_match_all( '#<Part><PartNumber>(\d+)</PartNumber><ETag>(.*?)</ETag></Part>#', $body, $listed, PREG_SET_ORDER );
				$data = '';
				$previous = 0;
				foreach ( $listed as $part ) {
					$number = (int) $part[1];
					if ( $number <= $previous ) return $this->error( $request, 400, 'InvalidPartOrder', 'Orden' );
					$previous = $number;
					if ( ! isset( $this->uploads[ $id ]['parts'][ $number ] ) || htmlspecialchars_decode( $part[2] ) !== '"' . md5( $this->uploads[ $id ]['parts'][ $number ] ) . '"' ) return $this->error( $request, 400, 'InvalidPart', 'Parte' );
					$data .= $this->uploads[ $id ]['parts'][ $number ];
				}
				$this->objects[ $key ] = $data;
				unset( $this->uploads[ $id ] );
				return bk_fake_response( $request, 200, '<?xml version="1.0" encoding="UTF-8"?><CompleteMultipartUploadResult><Bucket>' . $this->bucket . '</Bucket><Key>' . htmlspecialchars( $key, ENT_XML1 ) . '</Key><ETag>"x-' . count( $listed ) . '"</ETag></CompleteMultipartUploadResult>' );
			}
			if ( 'DELETE' === $method ) { unset( $this->uploads[ $id ] ); return bk_fake_response( $request, 204 ); }
		}
		if ( 'PUT' === $method && isset( $headers['x-amz-copy-source'] ) ) {
			$source = rawurldecode( preg_replace( '#^/' . preg_quote( $this->bucket, '#' ) . '/#', '', $headers['x-amz-copy-source'] ) );
			if ( ! isset( $this->objects[ $source ] ) ) return $this->error( $request, 404, 'NoSuchKey', 'Origen' );
			if ( ! empty( $this->faults['copy_error'] ) ) { $this->faults['copy_error'] = false; return bk_fake_response( $request, 200, '<Error><Code>InternalError</Code><Message>copy</Message></Error>' ); }
			$this->objects[ $key ] = $this->objects[ $source ];
			return bk_fake_response( $request, 200, '<CopyObjectResult><ETag>"' . md5( $this->objects[ $key ] ) . '"</ETag></CopyObjectResult>' );
		}
		if ( 'PUT' === $method ) { $this->objects[ $key ] = $body; return bk_fake_response( $request, 200, '', array( 'ETag' => '"' . md5( $body ) . '"' ) ); }
		if ( ! isset( $this->objects[ $key ] ) ) return 'DELETE' === $method ? bk_fake_response( $request, 204 ) : $this->error( $request, 404, 'NoSuchKey', 'No existe' );
		if ( 'HEAD' === $method ) return bk_fake_response( $request, 200, '', array( 'Content-Length' => (string) strlen( $this->objects[ $key ] ) ) );
		if ( 'GET' === $method ) {
			$data = $this->objects[ $key ];
			if ( ! empty( $this->faults['corrupt_get'] ) && '' !== $data ) $data[0] = chr( ord( $data[0] ) ^ 1 );
			list( $code, $body, $range ) = bk_fake_range( $request, $data );
			return bk_fake_response( $request, $code, $body, $range );
		}
		if ( 'DELETE' === $method ) { unset( $this->objects[ $key ] ); return bk_fake_response( $request, 204 ); }
		return $this->error( $request, 405, 'MethodNotAllowed', $method );
	}
	private function list( array $request, array $query ) {
		$prefix = (string) ( $query['prefix'] ?? '' );
		$xml = '<?xml version="1.0" encoding="UTF-8"?><ListBucketResult><Name>' . $this->bucket . '</Name><Prefix>' . htmlspecialchars( $prefix, ENT_XML1 ) . '</Prefix><IsTruncated>false</IsTruncated>';
		ksort( $this->objects );
		foreach ( $this->objects as $key => $data ) if ( 0 === strpos( $key, $prefix ) ) $xml .= '<Contents><Key>' . htmlspecialchars( $key, ENT_XML1 ) . '</Key><Size>' . strlen( $data ) . '</Size></Contents>';
		return bk_fake_response( $request, 200, $xml . '</ListBucketResult>' );
	}
	private function error( array $request, $code, $error, $message ) {
		return bk_fake_response( $request, $code, '<?xml version="1.0" encoding="UTF-8"?><Error><Code>' . $error . '</Code><Message>' . $message . '</Message><RequestId>1</RequestId></Error>' );
	}
}

/* ====================================================================== Dropbox */

final class Bk_Fake_Dropbox {
	public $files = array();
	public $sessions = array();
	public $log = array();
	public $app_key = 'appkey1234';
	public $refresh = 'refresh-dropbox-0123';
	/** lose_session (append número), drop_reply (append número: Dropbox guarda pero la respuesta se pierde), corrupt_store. */
	public $faults = array();
	public $appends = 0;
	public static function content_hash( $data ) {
		$hashes = '';
		foreach ( str_split( $data, 4194304 ) as $block ) if ( '' !== $block ) $hashes .= hash( 'sha256', $block, true );
		return hash( 'sha256', $hashes );
	}
	public function __invoke( array $request ) {
		$url = $request['url'];
		$headers = (array) $request['headers'];
		$body = bk_fake_body( $request );
		$route = preg_replace( '#^https://(api|content)\.dropboxapi\.com/2/#', '', $url );
		$this->log[] = array( 'url' => $url, 'arg' => $headers['Dropbox-API-Arg'] ?? '', 'auth' => $headers['Authorization'] ?? '', 'bytes' => strlen( $body ) );
		if ( 'https://api.dropboxapi.com/oauth2/token' === $url ) {
			parse_str( $body, $form );
			if ( 'refresh_token' === ( $form['grant_type'] ?? '' ) && $this->refresh === ( $form['refresh_token'] ?? '' ) && $this->app_key === ( $form['client_id'] ?? '' ) && ! isset( $form['client_secret'] ) ) return bk_fake_response( $request, 200, json_encode( array( 'access_token' => 'sl.acceso', 'token_type' => 'bearer', 'expires_in' => 14400 ) ) );
			if ( 'authorization_code' === ( $form['grant_type'] ?? '' ) && 'codigo-dropbox' === ( $form['code'] ?? '' ) && hash_equals( rtrim( strtr( base64_encode( hash( 'sha256', (string) ( $form['code_verifier'] ?? '' ), true ) ), '+/', '-_' ), '=' ), (string) ( $this->faults['challenge'] ?? '' ) ) ) return bk_fake_response( $request, 200, json_encode( array( 'access_token' => 'sl.acceso', 'refresh_token' => $this->refresh, 'expires_in' => 14400 ) ) );
			return bk_fake_response( $request, 400, json_encode( array( 'error' => 'invalid_grant' ) ) );
		}
		if ( 'Bearer sl.acceso' !== ( $headers['Authorization'] ?? '' ) ) return bk_fake_response( $request, 401, json_encode( array( 'error_summary' => 'expired_access_token/', 'error' => array( '.tag' => 'expired_access_token' ) ) ) );
		$arg = isset( $headers['Dropbox-API-Arg'] ) ? json_decode( $headers['Dropbox-API-Arg'], true ) : json_decode( $body, true );
		if ( isset( $headers['Dropbox-API-Arg'] ) && preg_match( '/[\x80-\xff]/', $headers['Dropbox-API-Arg'] ) ) return bk_fake_response( $request, 400, 'Dropbox-API-Arg con caracteres no ASCII sin escapar' );
		switch ( $route ) {
			case 'files/upload_session/start':
				$id = 'sesion-' . bin2hex( random_bytes( 4 ) );
				$this->sessions[ $id ] = array( 'data' => $body, 'closed' => false );
				return bk_fake_response( $request, 200, json_encode( array( 'session_id' => $id ) ) );
			case 'files/upload_session/append_v2':
				$this->appends++;
				$id = $arg['cursor']['session_id'];
				if ( ( $this->faults['lose_session'] ?? 0 ) === $this->appends ) unset( $this->sessions[ $id ] );
				if ( ! isset( $this->sessions[ $id ] ) ) return $this->conflict( $request, 'not_found', array() );
				if ( (int) $arg['cursor']['offset'] !== strlen( $this->sessions[ $id ]['data'] ) ) return $this->conflict( $request, 'incorrect_offset', array( 'correct_offset' => strlen( $this->sessions[ $id ]['data'] ) ) );
				$this->sessions[ $id ]['data'] .= $body;
				if ( ( $this->faults['drop_reply'] ?? 0 ) === $this->appends ) throw new Exception( 'cURL error 52: Empty reply from server' );
				return bk_fake_response( $request, 200, 'null' );
			case 'files/upload_session/finish':
				$id = $arg['cursor']['session_id'];
				if ( ! isset( $this->sessions[ $id ] ) ) return $this->conflict( $request, 'lookup_failed', array(), 'not_found' );
				if ( (int) $arg['cursor']['offset'] !== strlen( $this->sessions[ $id ]['data'] . $body ) ) return $this->conflict( $request, 'lookup_failed', array( 'correct_offset' => strlen( $this->sessions[ $id ]['data'] ) ), 'incorrect_offset' );
				$data = $this->sessions[ $id ]['data'] . $body;
				unset( $this->sessions[ $id ] );
				$path = $arg['commit']['path'];
				if ( isset( $this->files[ strtolower( $path ) ] ) ) return $this->conflict( $request, 'path', array( 'reason' => array( '.tag' => 'conflict' ) ) );
				if ( ! empty( $this->faults['corrupt_store'] ) ) $data[0] = chr( ord( $data[0] ) ^ 1 );
				return bk_fake_response( $request, 200, json_encode( $this->store( $path, $data ) ) );
			case 'files/get_metadata':
				$file = $this->find( $arg['path'] );
				return $file ? bk_fake_response( $request, 200, json_encode( $this->meta( $file ) ) ) : $this->conflict( $request, 'path', array( '.tag' => 'not_found' ), 'not_found' );
			case 'files/move_v2':
				$from = $this->find( $arg['from_path'] );
				if ( ! $from ) return $this->conflict( $request, 'from_lookup', array( '.tag' => 'not_found' ), 'not_found' );
				if ( isset( $this->files[ strtolower( $arg['to_path'] ) ] ) ) return $this->conflict( $request, 'to', array( '.tag' => 'conflict' ) );
				unset( $this->files[ strtolower( $from['path'] ) ] );
				$from['path'] = $arg['to_path'];
				$this->files[ strtolower( $from['path'] ) ] = $from;
				return bk_fake_response( $request, 200, json_encode( array( 'metadata' => $this->meta( $from ) ) ) );
			case 'files/download':
				$file = $this->find( $arg['path'] );
				return $file ? bk_fake_response( $request, 200, $file['data'], array( 'Dropbox-API-Result' => json_encode( $this->meta( $file ) ) ) ) : $this->conflict( $request, 'path', array( '.tag' => 'not_found' ), 'not_found' );
			case 'files/delete_v2':
				$file = $this->find( $arg['path'] );
				if ( ! $file ) return $this->conflict( $request, 'path_lookup', array( '.tag' => 'not_found' ), 'not_found' );
				unset( $this->files[ strtolower( $file['path'] ) ] );
				return bk_fake_response( $request, 200, json_encode( array( 'metadata' => $this->meta( $file ) ) ) );
			case 'files/list_folder':
				$entries = array();
				foreach ( $this->files as $file ) if ( 0 === strpos( strtolower( $file['path'] ), strtolower( rtrim( $arg['path'], '/' ) ) . '/' ) ) $entries[] = array( '.tag' => 'file' ) + $this->meta( $file );
				return bk_fake_response( $request, 200, json_encode( array( 'entries' => $entries, 'cursor' => 'c', 'has_more' => false ) ) );
		}
		return bk_fake_response( $request, 400, 'ruta desconocida ' . $route );
	}
	private function store( $path, $data ) {
		$file = array( 'path' => $path, 'data' => $data, 'id' => 'id:' . bin2hex( random_bytes( 6 ) ) );
		$this->files[ strtolower( $path ) ] = $file;
		return $this->meta( $file );
	}
	private function find( $path ) {
		foreach ( $this->files as $file ) if ( strtolower( $file['path'] ) === strtolower( $path ) || $file['id'] === $path ) return $file;
		return null;
	}
	private function meta( array $file ) { return array( 'name' => basename( $file['path'] ), 'path_display' => $file['path'], 'path_lower' => strtolower( $file['path'] ), 'id' => $file['id'], 'size' => strlen( $file['data'] ), 'content_hash' => self::content_hash( $file['data'] ) ); }
	private function conflict( array $request, $tag, array $extra, $inner = '' ) {
		$error = array( '.tag' => $tag ) + ( '' !== $inner ? array( $tag => array( '.tag' => $inner ) + $extra ) : $extra );
		return bk_fake_response( $request, 409, json_encode( array( 'error_summary' => $tag . '/' . ( '' !== $inner ? $inner . '/' : '' ), 'error' => $error ) ) );
	}
}

/* ====================================================================== pCloud */

final class Bk_Fake_Pcloud {
	public $files = array();
	public $folders = array( 0 => array( 'name' => '', 'parent' => -1 ) );
	public $log = array();
	public $token = 'pcloud-token-1';
	public $europe = true;
	public $faults = array();
	private $next = 100;
	public function __invoke( array $request ) {
		$parts = parse_url( $request['url'] );
		$method = ltrim( (string) $parts['path'], '/' );
		parse_str( (string) ( $parts['query'] ?? '' ), $params );
		if ( 'POST' === $request['method'] ) { parse_str( (string) $request['body'], $form ); $params += $form; }
		$this->log[] = array( 'host' => $parts['host'], 'method' => $method, 'params' => array_diff_key( $params, array( 'access_token' => 1 ) ) );
		if ( preg_match( '/^c\d+\.pcloud\.com$/', $parts['host'] ) ) {
			$id = (int) basename( dirname( (string) $parts['path'] ) );
			return isset( $this->files[ $id ] ) ? bk_fake_response( $request, 200, $this->files[ $id ]['data'] ) : bk_fake_response( $request, 404, 'no' );
		}
		if ( $parts['host'] !== ( $this->europe ? 'eapi.pcloud.com' : 'api.pcloud.com' ) ) return bk_fake_response( $request, 200, json_encode( array( 'result' => 2000, 'error' => 'Log in failed (región equivocada).' ) ) );
		if ( 'oauth2_token' === $method ) return bk_fake_response( $request, 200, json_encode( 'cid' === ( $params['client_id'] ?? '' ) && 'csecret' === ( $params['client_secret'] ?? '' ) && 'codigo-pcloud' === ( $params['code'] ?? '' ) ? array( 'result' => 0, 'access_token' => $this->token, 'token_type' => 'bearer', 'uid' => 7 ) : array( 'result' => 2012, 'error' => 'Invalid code.' ) ) );
		if ( $this->token !== ( $params['access_token'] ?? '' ) ) return bk_fake_response( $request, 200, json_encode( array( 'result' => 2094, 'error' => 'Invalid access_token provided.' ) ) );
		switch ( $method ) {
			case 'createfolderifnotexists':
				foreach ( $this->folders as $id => $folder ) if ( $folder['parent'] === (int) $params['folderid'] && $folder['name'] === $params['name'] ) return $this->ok( $request, array( 'metadata' => array( 'folderid' => $id, 'isfolder' => true ) ) );
				$id = $this->next++;
				$this->folders[ $id ] = array( 'name' => $params['name'], 'parent' => (int) $params['folderid'] );
				return $this->ok( $request, array( 'metadata' => array( 'folderid' => $id, 'isfolder' => true ) ) );
			case 'uploadfile':
				if ( 'PUT' !== $request['method'] || empty( $params['nopartial'] ) ) return $this->ok( $request, array( 'result' => 2001, 'error' => 'Se esperaba PUT con nopartial.' ) );
				$data = bk_fake_body( $request );
				if ( ! empty( $this->faults['corrupt_store'] ) ) $data[0] = chr( ord( $data[0] ) ^ 1 );
				$id = $this->next++;
				$this->files[ $id ] = array( 'name' => $params['filename'], 'folder' => (int) $params['folderid'], 'data' => $data );
				return $this->ok( $request, array( 'fileids' => array( $id ), 'metadata' => array( $this->meta( $id ) ) ) );
			case 'checksumfile':
				if ( ! isset( $this->files[ (int) $params['fileid'] ] ) ) return $this->ok( $request, array( 'result' => 2009, 'error' => 'File not found.' ) );
				$data = $this->files[ (int) $params['fileid'] ]['data'];
				return $this->ok( $request, array( 'sha1' => sha1( $data ), 'metadata' => $this->meta( (int) $params['fileid'] ) ) + ( $this->europe ? array( 'sha256' => hash( 'sha256', $data ) ) : array( 'md5' => md5( $data ) ) ) );
			case 'renamefile':
				if ( ! isset( $this->files[ (int) $params['fileid'] ] ) ) return $this->ok( $request, array( 'result' => 2009, 'error' => 'File not found.' ) );
				$this->files[ (int) $params['fileid'] ]['name'] = $params['toname'];
				return $this->ok( $request, array( 'metadata' => $this->meta( (int) $params['fileid'] ) ) );
			case 'getfilelink':
				if ( ! isset( $this->files[ (int) $params['fileid'] ] ) ) return $this->ok( $request, array( 'result' => 2009, 'error' => 'File not found.' ) );
				return $this->ok( $request, array( 'path' => '/hash' . bin2hex( random_bytes( 3 ) ) . '/' . (int) $params['fileid'] . '/' . rawurlencode( $this->files[ (int) $params['fileid'] ]['name'] ), 'hosts' => array( 'c7.pcloud.com', 'c1.pcloud.com' ), 'expires' => 'x' ) );
			case 'stat':
				return isset( $this->files[ (int) $params['fileid'] ] ) ? $this->ok( $request, array( 'metadata' => $this->meta( (int) $params['fileid'] ) ) ) : $this->ok( $request, array( 'result' => 2009, 'error' => 'File not found.' ) );
			case 'deletefile':
				if ( ! isset( $this->files[ (int) $params['fileid'] ] ) ) return $this->ok( $request, array( 'result' => 2009, 'error' => 'File not found.' ) );
				$meta = $this->meta( (int) $params['fileid'] ) + array( 'isdeleted' => true );
				unset( $this->files[ (int) $params['fileid'] ] );
				return $this->ok( $request, array( 'metadata' => $meta ) );
			case 'listfolder':
				$contents = array();
				foreach ( $this->files as $id => $file ) if ( $file['folder'] === (int) $params['folderid'] ) $contents[] = $this->meta( $id );
				return $this->ok( $request, array( 'metadata' => array( 'folderid' => (int) $params['folderid'], 'contents' => $contents ) ) );
		}
		return $this->ok( $request, array( 'result' => 2000, 'error' => 'Método desconocido ' . $method ) );
	}
	private function meta( $id ) { return array( 'fileid' => $id, 'parentfolderid' => $this->files[ $id ]['folder'], 'name' => $this->files[ $id ]['name'], 'size' => strlen( $this->files[ $id ]['data'] ), 'isfolder' => false, 'path' => '/x/' . $this->files[ $id ]['name'] ); }
	private function ok( array $request, array $data ) { return bk_fake_response( $request, 200, json_encode( $data + array( 'result' => 0 ) ) ); }
}

/* ====================================================================== WebDAV */

final class Bk_Fake_Webdav {
	public $files = array();
	public $collections = array( '' => true );
	public $log = array();
	public $base;
	public $auth;
	public $faults = array();
	public function __construct( $base, $user, $password ) { $this->base = rtrim( $base, '/' ) . '/'; $this->auth = 'Basic ' . base64_encode( $user . ':' . $password ); }
	public function __invoke( array $request ) {
		$headers = (array) $request['headers'];
		$this->log[] = array( 'method' => $request['method'], 'url' => $request['url'], 'headers' => $headers );
		if ( ( $headers['Authorization'] ?? '' ) !== $this->auth ) return bk_fake_response( $request, 401, 'Unauthorized' );
		if ( 0 !== strpos( $request['url'], $this->base ) ) return bk_fake_response( $request, 404, 'fuera de la base' );
		$path = rawurldecode( rtrim( substr( $request['url'], strlen( $this->base ) ), '/' ) );
		$parent = false !== strpos( $path, '/' ) ? substr( $path, 0, strrpos( $path, '/' ) ) : '';
		switch ( $request['method'] ) {
			case 'MKCOL':
				if ( isset( $this->collections[ $path ] ) || isset( $this->files[ $path ] ) ) return bk_fake_response( $request, 405, '' );
				if ( ! isset( $this->collections[ $parent ] ) ) return bk_fake_response( $request, 409, '' );
				$this->collections[ $path ] = true;
				return bk_fake_response( $request, 201, '' );
			case 'PUT':
				if ( ! isset( $this->collections[ $parent ] ) ) return bk_fake_response( $request, 409, '' );
				$this->files[ $path ] = bk_fake_body( $request );
				return bk_fake_response( $request, 201, '' );
			case 'GET':
				if ( ! isset( $this->files[ $path ] ) ) return bk_fake_response( $request, 404, '' );
				$data = $this->files[ $path ];
				if ( ! empty( $this->faults['corrupt_get'] ) && '' !== $data ) $data[0] = chr( ord( $data[0] ) ^ 1 );
				if ( isset( $this->faults['get'] ) && is_callable( $this->faults['get'] ) ) { $fault = call_user_func( $this->faults['get'], $request, $data ); if ( null !== $fault ) return $fault; }
				list( $code, $body, $range ) = bk_fake_range( $request, $data );
				return bk_fake_response( $request, $code, $body, $range );
			case 'MOVE':
				if ( ! isset( $this->files[ $path ] ) ) return bk_fake_response( $request, 404, '' );
				$to = rawurldecode( rtrim( substr( (string) ( $headers['Destination'] ?? '' ), strlen( $this->base ) ), '/' ) );
				if ( 0 !== strpos( (string) ( $headers['Destination'] ?? '' ), $this->base ) ) return bk_fake_response( $request, 502, '' );
				$exists = isset( $this->files[ $to ] );
				if ( $exists && 'F' === ( $headers['Overwrite'] ?? 'T' ) ) return bk_fake_response( $request, 412, '' );
				$this->files[ $to ] = $this->files[ $path ];
				unset( $this->files[ $path ] );
				return bk_fake_response( $request, $exists ? 204 : 201, '' );
			case 'DELETE':
				if ( ! isset( $this->files[ $path ] ) ) return bk_fake_response( $request, 404, '' );
				unset( $this->files[ $path ] );
				return bk_fake_response( $request, 204, '' );
			case 'PROPFIND':
				$depth = $headers['Depth'] ?? 'infinity';
				$xml = '<?xml version="1.0" encoding="utf-8"?><d:multistatus xmlns:d="DAV:">';
				if ( isset( $this->files[ $path ] ) ) $xml .= $this->response( $path, strlen( $this->files[ $path ] ), false );
				elseif ( isset( $this->collections[ $path ] ) ) {
					$xml .= $this->response( $path, 0, true );
					if ( '1' === $depth ) foreach ( $this->files as $file => $data ) if ( ( false !== strpos( $file, '/' ) ? substr( $file, 0, strrpos( $file, '/' ) ) : '' ) === $path ) $xml .= $this->response( $file, strlen( $data ), false );
				} else return bk_fake_response( $request, 404, '' );
				return bk_fake_response( $request, 207, $xml . '</d:multistatus>' );
		}
		return bk_fake_response( $request, 405, '' );
	}
	private function response( $path, $size, $collection ) {
		$href = parse_url( $this->base, PHP_URL_PATH ) . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) . ( $collection ? '/' : '' );
		return '<d:response><d:href>' . htmlspecialchars( $href, ENT_XML1 ) . '</d:href><d:propstat><d:prop>' . ( $collection ? '<d:resourcetype><d:collection/></d:resourcetype>' : '<d:resourcetype/><d:getcontentlength>' . $size . '</d:getcontentlength>' ) . '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat><d:propstat><d:prop><d:quota/></d:prop><d:status>HTTP/1.1 404 Not Found</d:status></d:propstat></d:response>';
	}
}

/* ====================================================================== FTP en proceso */

/**
 * Servidor FTP en el mismo proceso: el cliente abre `bkftp://control` y `bkftp://data/<puerto>` (por el conector de
 * pruebas) y este envoltorio responde como un servidor RFC 959/4217 con un sistema de archivos en memoria.
 */
final class Bk_Fake_Ftp {
	public static $files = array();
	public static $dirs = array( '/' => true );
	public static $log = array();
	public static $tls = array();
	public static $mode = 'explicit';
	public static $user = 'respaldos';
	public static $password = 'Ftp-Clave-1';
	public static $pasv_ip = '10.0.0.7';
	public static $faults = array();
	public static $connected = array();
	private static $pending = null;
	private static $rename = null;
	private static $port = 50000;
	private static $authed = false;
	private static $protected = false;
	private static $feat = true;

	public static function reset( $mode = 'explicit' ) {
		self::$files = array(); self::$dirs = array( '/' => true ); self::$log = array(); self::$tls = array(); self::$mode = $mode; self::$faults = array(); self::$connected = array(); self::$pending = null; self::$rename = null; self::$authed = false; self::$protected = false;
	}
	public static function install() {
		if ( ! in_array( 'bkftp', stream_get_wrappers(), true ) ) stream_wrapper_register( 'bkftp', 'Bk_Fake_Ftp_Stream' );
		Digitalisimo_Backups_Ftp_Client::$connector = function( $host, $port, $timeout ) {
			self::$connected[] = $host . ':' . $port;
			// Los puertos de datos los da EPSV/PASV (desde 50001); el de control es el configurado.
			if ( $port > 50000 ) return fopen( 'bkftp://data/' . $port, 'r+' );
			return fopen( 'bkftp://control', 'r+' );
		};
		Digitalisimo_Backups_Ftp_Client::$tls = function( $stream, $host ) {
			$meta = stream_get_meta_data( $stream );
			$data = false !== strpos( (string) $meta['uri'], 'data' );
			self::$tls[] = ( $data ? 'data' : 'control' ) . '@' . $host;
			if ( ! $data && isset( $GLOBALS['bk_ftp_control'] ) ) $GLOBALS['bk_ftp_control']->enable_tls();
			return true;
		};
	}
	public static function state() { return null !== self::$pending ? 'data' : 'control'; }
	/** Saludo o respuesta a una línea de comando; también prepara los datos de STOR/RETR/MLSD. */
	public static function greeting() {
		self::$authed = false; self::$protected = false;
		return "220-DIGITALÍSIMO prueba\r\n220 Servicio listo\r\n";
	}
	public static function handle( $line, $tls_on ) {
		self::$log[] = 0 === strpos( $line, 'PASS ' ) ? 'PASS ***' : $line;
		list( $command, $arg ) = array_pad( explode( ' ', $line, 2 ), 2, '' );
		$command = strtoupper( $command );
		if ( 'implicit' === self::$mode && ! $tls_on ) return "421 TLS requerido desde el inicio\r\n";
		if ( 'explicit' === self::$mode && ! $tls_on && ! in_array( $command, array( 'AUTH', 'QUIT', 'FEAT' ), true ) ) return "530 Usa AUTH TLS primero\r\n";
		switch ( $command ) {
			case 'AUTH': return 'none' === self::$mode ? "500 Sin TLS\r\n" : "234 AUTH TLS correcto\r\n";
			case 'USER': return self::$user === $arg ? "331 Contraseña\r\n" : "530 No\r\n";
			case 'PASS': if ( self::$password !== $arg ) return "530 Contraseña incorrecta\r\n"; self::$authed = true; return "230 Dentro\r\n";
			case 'PBSZ': return "200 PBSZ=0\r\n";
			case 'PROT': self::$protected = 'P' === $arg; return "200 Protección " . $arg . "\r\n";
			case 'TYPE': return "200 Tipo " . $arg . "\r\n";
			case 'FEAT': return self::$feat ? "211-Funciones:\r\n MLSD\r\n SIZE\r\n EPSV\r\n211 Fin\r\n" : "211 Nada\r\n";
			case 'EPSV': return ! empty( self::$faults['no_epsv'] ) ? "500 EPSV no\r\n" : '229 Modo pasivo extendido (|||' . ( ++self::$port ) . "|)\r\n";
			case 'PASV': ++self::$port; return '227 Entrando en modo pasivo (' . str_replace( '.', ',', self::$pasv_ip ) . ',' . intdiv( self::$port, 256 ) . ',' . ( self::$port % 256 ) . ")\r\n";
			case 'MKD': if ( isset( self::$dirs[ $arg ] ) ) return "550 Ya existe\r\n"; self::$dirs[ $arg ] = true; return '257 "' . $arg . "\" creado\r\n";
			case 'CWD': return isset( self::$dirs[ rtrim( $arg, '/' ) ?: '/' ] ) ? "250 OK\r\n" : "550 No existe\r\n";
			case 'SIZE': return isset( self::$files[ $arg ] ) ? '213 ' . strlen( self::$files[ $arg ] ) . "\r\n" : "550 No existe\r\n";
			case 'DELE': if ( ! isset( self::$files[ $arg ] ) ) return "550 No existe\r\n"; unset( self::$files[ $arg ] ); return "250 Borrado\r\n";
			case 'RNFR': if ( ! isset( self::$files[ $arg ] ) ) return "550 No existe\r\n"; self::$rename = $arg; return "350 Listo para RNTO\r\n";
			case 'RNTO': if ( null === self::$rename ) return "503 Falta RNFR\r\n"; self::$files[ $arg ] = self::$files[ self::$rename ]; unset( self::$files[ self::$rename ] ); self::$rename = null; return "250 Renombrado\r\n";
			case 'STOR': case 'APPE': case 'RETR': case 'MLSD': case 'NLST':
				if ( 'none' !== self::$mode && ! self::$protected ) return "521 PROT P requerido\r\n";
				if ( 'RETR' === $command && ! isset( self::$files[ $arg ] ) ) return "550 No existe\r\n";
				if ( 'APPE' === $command && ! isset( self::$files[ $arg ] ) ) self::$files[ $arg ] = '';
				if ( 'STOR' === $command ) self::$files[ $arg ] = '';
				self::$pending = array( 'command' => $command, 'path' => $arg );
				return "150 Abriendo conexión de datos\r\n";
			case 'QUIT': return "221 Adiós\r\n";
		}
		return "502 No implementado\r\n";
	}
	/** Datos de RETR/MLSD para el canal de datos. */
	public static function data_out() {
		$pending = self::$pending;
		if ( 'RETR' === $pending['command'] ) {
			$data = self::$files[ $pending['path'] ];
			if ( ! empty( self::$faults['corrupt_get'] ) && '' !== $data ) $data[0] = chr( ord( $data[0] ) ^ 1 );
			return $data;
		}
		if ( in_array( $pending['command'], array( 'MLSD', 'NLST' ), true ) ) {
			$out = '';
			$dir = rtrim( $pending['path'], '/' );
			foreach ( self::$files as $path => $data ) if ( dirname( $path ) === ( '' === $dir ? '/' : $dir ) ) $out .= 'MLSD' === $pending['command'] ? 'type=file;size=' . strlen( $data ) . '; ' . basename( $path ) . "\r\n" : basename( $path ) . "\r\n";
			return $out;
		}
		return '';
	}
	public static function data_in( $chunk ) {
		if ( isset( self::$faults['cut_after'] ) && strlen( self::$files[ self::$pending['path'] ] ) + strlen( $chunk ) > self::$faults['cut_after'] ) {
			$keep = self::$faults['cut_after'] - strlen( self::$files[ self::$pending['path'] ] );
			self::$files[ self::$pending['path'] ] .= substr( $chunk, 0, max( 0, $keep ) );
			unset( self::$faults['cut_after'] );
			return false;
		}
		self::$files[ self::$pending['path'] ] .= $chunk;
		return strlen( $chunk );
	}
	public static function data_closed() { self::$pending = null; return "226 Transferencia completa\r\n"; }
}

final class Bk_Fake_Ftp_Stream {
	public $context;
	private $kind;
	private $out = '';
	private $in = '';
	private $data_sent = false;
	private $tls = false;
	public function stream_open( $path, $mode, $options, &$opened ) {
		$this->kind = 0 === strpos( $path, 'bkftp://data' ) ? 'data' : 'control';
		if ( 'control' === $this->kind ) { $GLOBALS['bk_ftp_control'] = $this; if ( 'implicit' !== Bk_Fake_Ftp::$mode ) $this->out = Bk_Fake_Ftp::greeting(); }
		return true;
	}
	/** El cliente activa TLS (seam de pruebas): en implícito el saludo llega después. */
	public function enable_tls() {
		$this->tls = true;
		if ( 'control' === $this->kind && 'implicit' === Bk_Fake_Ftp::$mode && '' === $this->out ) $this->out = Bk_Fake_Ftp::greeting();
	}
	public function stream_write( $data ) {
		if ( 'data' === $this->kind ) {
			$written = Bk_Fake_Ftp::data_in( $data );
			return false === $written ? 0 : $written;
		}
		$this->in .= $data;
		while ( false !== ( $pos = strpos( $this->in, "\r\n" ) ) ) {
			$line = substr( $this->in, 0, $pos );
			$this->in = substr( $this->in, $pos + 2 );
			$this->out .= Bk_Fake_Ftp::handle( $line, $this->tls );
		}
		return strlen( $data );
	}
	public function stream_read( $count ) {
		if ( 'data' === $this->kind ) {
			if ( ! $this->data_sent ) { $this->out = Bk_Fake_Ftp::data_out(); $this->data_sent = true; }
			$chunk = substr( $this->out, 0, $count );
			$this->out = (string) substr( $this->out, $count );
			return $chunk;
		}
		// Una línea por lectura, como fgets sobre un socket.
		$pos = strpos( $this->out, "\n" );
		$take = false === $pos ? strlen( $this->out ) : min( $count, $pos + 1 );
		$chunk = substr( $this->out, 0, $take );
		$this->out = (string) substr( $this->out, $take );
		return $chunk;
	}
	public function stream_eof() { return 'data' === $this->kind ? $this->data_sent && '' === $this->out : false; }
	public function stream_close() {
		if ( 'data' === $this->kind && isset( $GLOBALS['bk_ftp_control'] ) ) $GLOBALS['bk_ftp_control']->push( Bk_Fake_Ftp::data_closed() );
	}
	public function push( $reply ) { $this->out .= $reply; }
	public function stream_flush() { return true; }
	public function stream_set_option( $option, $arg1, $arg2 ) { return false; }
	public function stream_cast( $as ) { return false; }
}
