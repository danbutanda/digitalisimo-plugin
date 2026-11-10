<?php
defined( 'ABSPATH' ) || exit;

/**
 * Amazon S3 y almacenamiento compatible (DigitalOcean Spaces, Wasabi, Cloudflare R2, Backblaze B2, Akamai/Linode,
 * Vultr y otros como MinIO o Ceph), sólo con AWS Signature Version 4.
 *
 * - Cada petición firma el SHA-256 de su cuerpo (`x-amz-content-sha256`): el servicio rechaza una parte que no llegó
 *   íntegra. Nunca se usa UNSIGNED-PAYLOAD ni URL prefirmadas.
 * - Subida: un PutObject si cabe en una parte; si no, multipart (partes iguales de 8 MiB o más, hasta 9 000 partes)
 *   con el estado en el área del trabajo: un reintento repite sólo las partes que faltan.
 * - El objeto se sube con un nombre temporal (`<nombre>.part-<aleatorio>`), se descarga y compara por SHA-256, y sólo
 *   entonces se publica con una copia en el servidor (CopyObject, o UploadPartCopy por tramos de 1 GiB si pasa de
 *   5 GiB), se comprueba su tamaño y se borra el temporal.
 * - Las respuestas `200 OK` con un `<Error>` en el cuerpo (CompleteMultipartUpload, CopyObject) son errores.
 */
abstract class Digitalisimo_Backups_Destination_S3_Base extends Digitalisimo_Backups_Destination {
	const EMPTY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
	const MAX_PARTS = 9000;
	const COPY_LIMIT = 5368709120;
	const COPY_RANGE = 1073741824;
	/** Sólo pruebas: hora fija de la firma (UNIX). */
	public static $now = null;

	/** array( scheme, host (con puerto si no es el habitual), region, style: virtual|path, verify, ca ). */
	abstract protected function endpoint();
	/** Nombre de bucket válido (lo mismo que acepta el formulario): sin «/», «:», «@» ni nada que cambie el host. */
	const BUCKET_PATTERN = '/^[a-z0-9][a-z0-9.\-_]{1,61}[a-z0-9]$/D';
	/**
	 * El bucket se valida al usarse, no sólo al guardar: una escritura directa en la base no puede convertirlo en otro
	 * host (también va en el dato adicional de la Secret key).
	 */
	protected function bucket() {
		$bucket = (string) ( $this->s[ static::PREFIX . 'bucket' ] ?? '' );
		if ( ! preg_match( self::BUCKET_PATTERN, $bucket ) || false !== strpos( $bucket, '..' ) || filter_var( $bucket, FILTER_VALIDATE_IP ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El bucket de ' . static::label() . ' no es válido; revísalo en Backups → Servidores.' );
		return $bucket;
	}
	protected function prefix() {
		$path = trim( str_replace( '\\', '/', (string) ( $this->s[ static::PREFIX . 'path' ] ?? '' ) ), '/' );
		if ( '' === $path ) return '';
		return ltrim( self::clean_path( $path ), '/' ) . '/';
	}
	/** Estilo de ruta guardado: sólo los tres valores del formulario. */
	protected function style() {
		$style = (string) ( $this->s[ static::PREFIX . 'style' ] ?? 'auto' );
		if ( ! in_array( $style, array( 'auto', 'virtual', 'path' ), true ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El estilo de ruta de ' . static::label() . ' no es válido; revísalo en Backups → Servidores.' );
		return $style;
	}
	/**
	 * Una clave de un recibo sólo se usa si está en la carpeta de esta conexión. Sin carpeta el prefijo es '' (la raíz
	 * del bucket): strncmp() con longitud 0 lo acepta en cualquier PHP; strpos() con aguja vacía da false en PHP 7.4.
	 */
	protected function own_key( array $remote ) {
		$key = (string) ( $remote['key'] ?? '' );
		$prefix = $this->prefix();
		if ( '' === $key || preg_match( '/[\x00-\x1F\x7F]/', $key ) || ( isset( $remote['bucket'] ) && (string) $remote['bucket'] !== $this->bucket() ) || 0 !== strncmp( $key, $prefix, strlen( $prefix ) ) || false !== strpos( '/' . $key . '/', '/../' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La copia remota no está en el bucket y la carpeta de esta conexión de ' . static::label() . '; no se toca.' );
		return $key;
	}
	protected function key_for( $name ) { return $this->prefix() . $name; }
	/** Clases de almacenamiento con acceso inmediato (las de archivo impedirían verificar). */
	protected function storage_class() { return ''; }

	public static function ready( array $s ) {
		return ! empty( $s[ static::PREFIX . 'bucket' ] ) && ! empty( $s[ static::PREFIX . 'access_key' ] ) && ! empty( $s[ static::PREFIX . 'secret_key' ] );
	}
	protected static function sanitize_common( array $out ) {
		$bucket = strtolower( trim( (string) $out[ static::PREFIX . 'bucket' ] ) );
		if ( '' !== $bucket && ( ! preg_match( self::BUCKET_PATTERN, $bucket ) || false !== strpos( $bucket, '..' ) || filter_var( $bucket, FILTER_VALIDATE_IP ) ) ) throw new Exception( 'El nombre del bucket debe tener de 3 a 63 caracteres: minúsculas, números, puntos y guiones.' );
		$out[ static::PREFIX . 'bucket' ] = $bucket;
		$out[ static::PREFIX . 'access_key' ] = preg_replace( '/\s+/', '', (string) $out[ static::PREFIX . 'access_key' ] );
		$path = trim( str_replace( '\\', '/', (string) $out[ static::PREFIX . 'path' ] ), '/' );
		if ( '' !== $path ) $path = ltrim( self::clean_path( $path ), '/' );
		$out[ static::PREFIX . 'path' ] = $path;
		return $out;
	}

	/* ------------------------------------------------------------------ Firma SigV4 */

	/** UriEncode() de AWS: todo salvo A-Z a-z 0-9 - _ . ~; la barra sólo se conserva en la clave del objeto. */
	public static function uri_encode( $value, $encode_slash = true ) {
		$encoded = rawurlencode( (string) $value );
		$encoded = str_replace( '%7E', '~', $encoded );
		return $encode_slash ? $encoded : str_replace( '%2F', '/', $encoded );
	}
	/**
	 * Cabeceras firmadas (incluida Authorization) para una petición. $headers no incluye host ni x-amz-date.
	 * Devuelve array( 'headers' => …, 'canonical' => …, 'string_to_sign' => … ).
	 */
	public static function sign( $method, $host, $uri, array $query, array $headers, $payload_hash, $access, $secret, $region, $time, $service = 's3' ) {
		$date = gmdate( 'Ymd\THis\Z', $time );
		$day = substr( $date, 0, 8 );
		$headers = array_change_key_case( $headers, CASE_LOWER );
		$headers['host'] = $host;
		$headers['x-amz-date'] = $date;
		$headers['x-amz-content-sha256'] = $payload_hash;
		$canonical_headers = array();
		foreach ( $headers as $name => $value ) $canonical_headers[ $name ] = trim( preg_replace( '/\s+/', ' ', (string) $value ) );
		ksort( $canonical_headers, SORT_STRING );
		$signed = implode( ';', array_keys( $canonical_headers ) );
		$pairs = array();
		foreach ( $query as $name => $value ) $pairs[ self::uri_encode( $name ) ] = self::uri_encode( (string) $value );
		ksort( $pairs, SORT_STRING );
		$canonical_query = implode( '&', array_map( function( $name, $value ) { return $name . '=' . $value; }, array_keys( $pairs ), $pairs ) );
		$lines = '';
		foreach ( $canonical_headers as $name => $value ) $lines .= $name . ':' . $value . "\n";
		$canonical = $method . "\n" . $uri . "\n" . $canonical_query . "\n" . $lines . "\n" . $signed . "\n" . $payload_hash;
		$scope = $day . '/' . $region . '/' . $service . '/aws4_request';
		$string_to_sign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . hash( 'sha256', $canonical );
		$key = hash_hmac( 'sha256', 'aws4_request', hash_hmac( 'sha256', $service, hash_hmac( 'sha256', $region, hash_hmac( 'sha256', $day, 'AWS4' . $secret, true ), true ), true ), true );
		$signature = hash_hmac( 'sha256', $string_to_sign, $key );
		unset( $headers['host'] );
		$headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $access . '/' . $scope . ', SignedHeaders=' . $signed . ', Signature=' . $signature;
		return array( 'headers' => $headers, 'canonical' => $canonical, 'string_to_sign' => $string_to_sign, 'query' => $canonical_query );
	}

	/**
	 * Petición firmada a una clave del bucket ('' = el bucket). $body es texto (un fragmento); $args admite sink,
	 * progress y timeout. Devuelve la respuesta de Digitalisimo_Backups_Http.
	 */
	protected function call( $method, $key, array $query = array(), array $headers = array(), $body = '', array $args = array() ) {
		$endpoint = $this->endpoint();
		$bucket = $this->bucket();
		$virtual = 'virtual' === $endpoint['style'];
		$host = $virtual ? $bucket . '.' . $endpoint['host'] : $endpoint['host'];
		$uri = $virtual ? '/' . self::uri_encode( $key, false ) : '/' . self::uri_encode( $bucket ) . ( '' !== $key || 'GET' !== $method ? '/' . self::uri_encode( $key, false ) : '' );
		if ( ! $virtual && '' === $key ) $uri = '/' . self::uri_encode( $bucket );
		$time = null !== self::$now ? (int) self::$now : time();
		$signed = self::sign( $method, $host, $uri, $query, $headers, hash( 'sha256', (string) $body ), (string) $this->s[ static::PREFIX . 'access_key' ], (string) $this->s[ static::PREFIX . 'secret_key' ], $endpoint['region'], $time );
		$url = $endpoint['scheme'] . '://' . $host . $uri . ( '' !== $signed['query'] ? '?' . $signed['query'] : '' );
		return Digitalisimo_Backups_Http::request( $method, $url, array_merge( array( 'headers' => $signed['headers'], 'body' => (string) $body, 'verify' => $endpoint['verify'], 'ca' => $endpoint['ca'], 'timeout' => 300 ), $args ) );
	}
	/** Código y mensaje de un <Error> de S3 en el cuerpo, o null. */
	public static function error_of( $body ) {
		if ( ! preg_match( '#<Error>.*?<Code>(.*?)</Code>#s', (string) $body, $code ) ) return null;
		$message = preg_match( '#<Error>.*?<Message>(.*?)</Message>#s', (string) $body, $match ) ? self::xml_text( $match[1] ) : '';
		return array( 'code' => self::xml_text( $code[1] ), 'message' => $message );
	}
	public static function xml_text( $value ) { return htmlspecialchars_decode( (string) $value, ENT_QUOTES | ENT_XML1 ); }
	public static function xml_value( $xml, $tag ) { return preg_match( '#<' . $tag . '>(.*?)</' . $tag . '>#s', (string) $xml, $match ) ? self::xml_text( $match[1] ) : ''; }
	/** Lanza un error legible; los de credenciales, permisos, bucket o región no se arreglan reintentando. */
	protected function fail( array $response, $what ) {
		$error = self::error_of( $response['body'] );
		$code = $error ? $error['code'] : 'HTTP ' . $response['code'];
		$hints = array(
			'InvalidAccessKeyId' => 'la Access key no existe en este servicio',
			'SignatureDoesNotMatch' => 'la Secret access key no corresponde a la Access key',
			'AccessDenied' => 'la clave no tiene permiso para esta operación en el bucket',
			'NoSuchBucket' => 'el bucket no existe en este servicio o región',
			'AuthorizationHeaderMalformed' => 'la región elegida no es la del bucket',
			'PermanentRedirect' => 'el bucket está en otra región o requiere otro estilo de ruta',
			'IllegalLocationConstraintException' => 'la región elegida no es la del bucket',
			'InvalidBucketName' => 'el nombre del bucket no es válido para este servicio',
		);
		$message = static::label() . ' rechazó ' . $what . ' (' . $code . ( isset( $hints[ $code ] ) ? ': ' . $hints[ $code ] : '' ) . ').';
		if ( isset( $hints[ $code ] ) || in_array( (int) $response['code'], array( 301, 400, 401, 403 ), true ) ) throw new Digitalisimo_Backups_Permanent_Error( $message );
		throw new Exception( $message );
	}

	/* ------------------------------------------------------------------ Contrato */

	public function put( $file, $name = '' ) {
		$name = '' !== (string) $name ? (string) $name : self::basename( $file );
		$source = $this->source( $file );
		$final = $this->key_for( $name );
		$state = $this->resumable_state( $source );
		// Una muerte después de publicar: el objeto final ya está; se comprueba su tamaño y se termina.
		if ( ! empty( $state['published'] ) && ( $state['final'] ?? '' ) === $final && $this->size( array( 'key' => $final ) ) === $source['size'] ) {
			if ( ! empty( $state['temp'] ) ) $this->discard_quietly( $state['temp'] );
			$this->forget_state();
			return $this->receipt( $final, $source );
		}
		$temp = $this->upload( $file, $name, $source );
		try {
			$this->verify( $temp, $file, $source );
		} catch ( Digitalisimo_Backups_Job_Cancelled $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->discard_quietly( $temp );
			$this->forget_state();
			throw $e;
		}
		$this->publish( $temp, $name, $source );
		$this->forget_state();
		return $this->receipt( $final, $source );
	}
	private function receipt( $key, array $source ) {
		return array( 'provider' => static::TYPE, 'connection' => (string) $this->context['connection'], 'bucket' => $this->bucket(), 'key' => $key, 'sha256' => $source['sha256'], 'size' => $source['size'] );
	}
	public static function part_size( $size ) {
		$part = static::CHUNK;
		if ( $size > $part * self::MAX_PARTS ) $part = (int) ( ceil( $size / self::MAX_PARTS / 1048576 ) * 1048576 );
		return $part;
	}
	protected function upload( $file, $name, array $source ) {
		$state = $this->resumable_state( $source );
		$part_size = self::part_size( $source['size'] );
		if ( ! $state || ( $state['bucket'] ?? '' ) !== $this->bucket() || (int) ( $state['part_size'] ?? 0 ) !== $part_size ) {
			if ( $state && ! empty( $state['upload_id'] ) ) $this->abort_upload( $state );
			$state = array( 'connection' => (string) $this->context['connection'], 'size' => $source['size'], 'sha256' => $source['sha256'], 'bucket' => $this->bucket(), 'part_size' => $part_size, 'key' => $this->key_for( self::temporary_name( $name ) ), 'parts' => array() );
		}
		$state['final'] = $this->key_for( $name );
		$temp = array( 'provider' => static::TYPE, 'bucket' => $this->bucket(), 'key' => $state['key'] );
		$state['temp'] = $temp;
		if ( ! empty( $state['completed'] ) ) return $temp;
		$headers = array( 'content-type' => 'application/octet-stream' );
		if ( '' !== $this->storage_class() ) $headers['x-amz-storage-class'] = $this->storage_class();
		if ( $source['size'] <= $part_size ) {
			$this->save_state( $state );
			$body = (string) @file_get_contents( $file );
			if ( strlen( $body ) !== $source['size'] ) throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' );
			$this->progress( 0, $source['size'] );
			$response = $this->call( 'PUT', $state['key'], array(), $headers, $body );
			if ( 200 !== $response['code'] ) $this->fail( $response, 'el envío del respaldo' );
			$state['completed'] = true;
			$this->save_state( $state );
			return $temp;
		}
		for ( $attempt = 0; ; $attempt++ ) {
			if ( empty( $state['upload_id'] ) ) {
				$response = $this->call( 'POST', $state['key'], array( 'uploads' => '' ), $headers );
				$upload_id = self::xml_value( $response['body'], 'UploadId' );
				if ( 200 !== $response['code'] || '' === $upload_id ) $this->fail( $response, 'el inicio de la subida por partes' );
				$state['upload_id'] = $upload_id;
				$state['parts'] = array();
				$this->save_state( $state );
			}
			try {
				$this->upload_parts( $file, $source, $state );
				break;
			} catch ( Digitalisimo_Backups_S3_No_Upload $e ) {
				// La subida por partes caducó o se abortó fuera de aquí: se empieza de nuevo una sola vez.
				if ( $attempt > 0 ) throw new Exception( 'La subida por partes desapareció del servicio dos veces seguidas.' );
				$state['upload_id'] = '';
				$state['parts'] = array();
			}
		}
		$state['completed'] = true;
		$this->save_state( $state );
		return $temp;
	}
	private function upload_parts( $file, array $source, array &$state ) {
		$handle = @fopen( $file, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' );
		try {
			$size = $source['size'];
			$part_size = (int) $state['part_size'];
			$count = (int) ceil( $size / $part_size );
			for ( $number = 1; $number <= $count; $number++ ) {
				$offset = ( $number - 1 ) * $part_size;
				if ( ! empty( $state['parts'][ (string) $number ] ) ) continue;
				$this->progress( $offset, $size );
				if ( 0 !== fseek( $handle, $offset ) ) throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' );
				$length = min( $part_size, $size - $offset );
				$chunk = '';
				while ( strlen( $chunk ) < $length && ! feof( $handle ) ) { $read = fread( $handle, $length - strlen( $chunk ) ); if ( false === $read || '' === $read ) break; $chunk .= $read; }
				if ( strlen( $chunk ) !== $length ) throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' );
				$response = $this->call( 'PUT', $state['key'], array( 'partNumber' => (string) $number, 'uploadId' => $state['upload_id'] ), array(), $chunk );
				if ( 404 === $response['code'] && 'NoSuchUpload' === ( self::error_of( $response['body'] )['code'] ?? '' ) ) throw new Digitalisimo_Backups_S3_No_Upload( 'NoSuchUpload' );
				$etag = (string) ( $response['headers']['etag'] ?? '' );
				if ( 200 !== $response['code'] || '' === $etag ) $this->fail( $response, 'la parte ' . $number . ' del respaldo' );
				$state['parts'][ (string) $number ] = $etag;
				$this->save_state( $state );
			}
		} finally {
			fclose( $handle );
		}
		$xml = '<CompleteMultipartUpload xmlns="http://s3.amazonaws.com/doc/2006-03-01/">';
		for ( $number = 1; $number <= $count; $number++ ) $xml .= '<Part><PartNumber>' . $number . '</PartNumber><ETag>' . htmlspecialchars( (string) $state['parts'][ (string) $number ], ENT_XML1 ) . '</ETag></Part>';
		$xml .= '</CompleteMultipartUpload>';
		$response = $this->call( 'POST', $state['key'], array( 'uploadId' => $state['upload_id'] ), array( 'content-type' => 'application/xml' ), $xml );
		if ( 404 === $response['code'] && 'NoSuchUpload' === ( self::error_of( $response['body'] )['code'] ?? '' ) ) {
			// Ya completada por un intento anterior que murió antes de guardarlo: el objeto temporal existe.
			if ( $this->size( array( 'key' => $state['key'] ) ) === $source['size'] ) return;
			throw new Digitalisimo_Backups_S3_No_Upload( 'NoSuchUpload' );
		}
		if ( 200 !== $response['code'] || null !== self::error_of( $response['body'] ) || false === strpos( $response['body'], 'CompleteMultipartUploadResult' ) ) $this->fail( $response, 'la unión de las partes del respaldo' );
	}
	private function abort_upload( array $state ) {
		try { $this->call( 'DELETE', (string) $state['key'], array( 'uploadId' => (string) $state['upload_id'] ) ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	protected function publish( array $temp, $name, array $source = array() ) {
		$final = $this->key_for( $name );
		$state = $this->state();
		$copy_source = '/' . self::uri_encode( $this->bucket() ) . '/' . self::uri_encode( $temp['key'], false );
		$headers = array( 'x-amz-copy-source' => $copy_source );
		if ( '' !== $this->storage_class() ) $headers['x-amz-storage-class'] = $this->storage_class();
		$size = (int) ( $source['size'] ?? $this->size( $temp ) );
		if ( $size <= self::COPY_LIMIT ) {
			$response = $this->call( 'PUT', $final, array(), $headers );
			if ( 200 !== $response['code'] || null !== self::error_of( $response['body'] ) ) $this->fail( $response, 'la publicación del respaldo verificado' );
		} else {
			$this->copy_parts( $copy_source, $final, $size );
		}
		if ( $this->size( array( 'key' => $final ) ) !== $size ) {
			$this->discard_quietly( array( 'key' => $final ) );
			throw new Exception( 'La copia publicada en ' . static::label() . ' no tiene el tamaño del respaldo verificado.' );
		}
		if ( $state ) { $state['published'] = true; $state['final'] = $final; $this->save_state( $state ); }
		$this->discard_quietly( $temp );
		return array( 'provider' => static::TYPE, 'bucket' => $this->bucket(), 'key' => $final );
	}
	/** Copia en el servidor por tramos (más de 5 GiB). */
	private function copy_parts( $copy_source, $final, $size ) {
		$response = $this->call( 'POST', $final, array( 'uploads' => '' ), '' !== $this->storage_class() ? array( 'x-amz-storage-class' => $this->storage_class() ) : array() );
		$upload_id = self::xml_value( $response['body'], 'UploadId' );
		if ( 200 !== $response['code'] || '' === $upload_id ) $this->fail( $response, 'el inicio de la publicación por partes' );
		$parts = array();
		try {
			for ( $offset = 0, $number = 1; $offset < $size; $offset += self::COPY_RANGE, $number++ ) {
				$this->progress( $offset, $size, 'Publicados' );
				$end = min( $size, $offset + self::COPY_RANGE ) - 1;
				$response = $this->call( 'PUT', $final, array( 'partNumber' => (string) $number, 'uploadId' => $upload_id ), array( 'x-amz-copy-source' => $copy_source, 'x-amz-copy-source-range' => 'bytes=' . $offset . '-' . $end ) );
				$etag = self::xml_value( $response['body'], 'ETag' );
				if ( 200 !== $response['code'] || '' === $etag || null !== self::error_of( $response['body'] ) ) $this->fail( $response, 'la publicación de la parte ' . $number );
				$parts[ $number ] = $etag;
			}
			$xml = '<CompleteMultipartUpload xmlns="http://s3.amazonaws.com/doc/2006-03-01/">';
			foreach ( $parts as $number => $etag ) $xml .= '<Part><PartNumber>' . $number . '</PartNumber><ETag>' . htmlspecialchars( $etag, ENT_XML1 ) . '</ETag></Part>';
			$xml .= '</CompleteMultipartUpload>';
			$response = $this->call( 'POST', $final, array( 'uploadId' => $upload_id ), array( 'content-type' => 'application/xml' ), $xml );
			if ( 200 !== $response['code'] || null !== self::error_of( $response['body'] ) ) $this->fail( $response, 'la unión de la publicación' );
		} catch ( Throwable $e ) {
			$this->abort_upload( array( 'key' => $final, 'upload_id' => $upload_id ) );
			throw $e;
		}
	}
	public function get( array $remote, $target ) {
		$response = $this->call( 'GET', $this->own_key( $remote ), array(), array(), '', array( 'sink' => $target, 'timeout' => 0, 'progress' => function( $done, $total ) { if ( $total > 0 ) $this->progress( $done, $total, 'Descargados' ); } ) );
		if ( 200 !== $response['code'] ) {
			$body = is_file( $target ) && filesize( $target ) < 65536 ? (string) @file_get_contents( $target ) : '';
			if ( 404 === $response['code'] ) throw new Exception( 'El respaldo ya no está en ' . static::label() . '.' );
			$this->fail( array( 'code' => $response['code'], 'body' => $body ), 'la descarga del respaldo' );
		}
	}
	public function size( array $remote ) {
		$response = $this->call( 'HEAD', $this->own_key( $remote ) );
		if ( 404 === $response['code'] ) return false;
		if ( 200 !== $response['code'] ) $this->fail( $response, 'la consulta del respaldo' );
		return (int) ( $response['headers']['content-length'] ?? 0 );
	}
	public function delete( array $remote ) {
		$response = $this->call( 'DELETE', $this->own_key( $remote ) );
		if ( ! in_array( $response['code'], array( 200, 204, 404 ), true ) ) $this->fail( $response, 'el borrado del respaldo' );
	}
	/** B1b: GetObject con `Range` (firmado con SigV4 como el resto de cabeceras). */
	public function ranged() { return true; }
	public function get_range( array $remote, $target, $offset, $length ) {
		$range = self::range_args( $offset, $length );
		return self::range_reply( $this->call( 'GET', $this->own_key( $remote ), array(), $range['headers'], '', array( 'sink' => $target, 'timeout' => 120, 'progress' => $range['progress'] ) ), $target, $offset, $length );
	}
	public function list() {
		$prefix = $this->prefix();
		$out = array();
		$token = '';
		do {
			$query = array( 'list-type' => '2', 'prefix' => $prefix, 'delimiter' => '/', 'max-keys' => '1000' );
			if ( '' !== $token ) $query['continuation-token'] = $token;
			$response = $this->call( 'GET', '', $query );
			if ( 200 !== $response['code'] ) $this->fail( $response, 'el listado del bucket' );
			preg_match_all( '#<Contents>(.*?)</Contents>#s', $response['body'], $items );
			foreach ( $items[1] as $item ) {
				$key = self::xml_value( $item, 'Key' );
				$name = substr( $key, strlen( $prefix ) );
				if ( '' === $name || false !== strpos( $name, '/' ) ) continue;
				$out[] = array( 'name' => $name, 'size' => (int) self::xml_value( $item, 'Size' ), 'remote' => array( 'provider' => static::TYPE, 'connection' => (string) $this->context['connection'], 'bucket' => $this->bucket(), 'key' => $key ) );
			}
			$token = 'true' === self::xml_value( $response['body'], 'IsTruncated' ) ? self::xml_value( $response['body'], 'NextContinuationToken' ) : '';
		} while ( '' !== $token );
		return $out;
	}
	public function abandon() {
		$state = $this->state();
		// Con el bucket cambiado desde la subida, no se envía nada a otro sitio (el multipart caduca por su ciclo de vida).
		try { $same = ( $state['bucket'] ?? '' ) === $this->bucket(); } catch ( Exception $e ) { $same = false; }
		if ( ! $same ) { $this->forget_state(); return; }
		if ( ! empty( $state['upload_id'] ) && empty( $state['completed'] ) ) $this->abort_upload( $state );
		elseif ( ! empty( $state['key'] ) && empty( $state['published'] ) ) $this->discard_quietly( array( 'key' => (string) $state['key'] ) );
		$this->forget_state();
	}
}

/** Señal interna: la subida por partes ya no existe en el servicio (NoSuchUpload). */
final class Digitalisimo_Backups_S3_No_Upload extends Exception {}

final class Digitalisimo_Backups_Destination_S3 extends Digitalisimo_Backups_Destination_S3_Base {
	const TYPE = 's3';
	const PREFIX = 's3_';
	/** Regiones de Amazon S3 (docs.aws.amazon.com/general/latest/gr/s3.html, consultada el 2026-11-09). */
	const REGIONS = array( 'us-east-1' => 'EE. UU. Este (Norte de Virginia)', 'us-east-2' => 'EE. UU. Este (Ohio)', 'us-west-1' => 'EE. UU. Oeste (Norte de California)', 'us-west-2' => 'EE. UU. Oeste (Oregón)', 'ca-central-1' => 'Canadá (Central)', 'ca-west-1' => 'Canadá Oeste (Calgary)', 'mx-central-1' => 'México (Central)', 'sa-east-1' => 'Sudamérica (São Paulo)', 'eu-central-1' => 'Europa (Fráncfort)', 'eu-central-2' => 'Europa (Zúrich)', 'eu-west-1' => 'Europa (Irlanda)', 'eu-west-2' => 'Europa (Londres)', 'eu-west-3' => 'Europa (París)', 'eu-south-1' => 'Europa (Milán)', 'eu-south-2' => 'Europa (España)', 'eu-north-1' => 'Europa (Estocolmo)', 'af-south-1' => 'África (Ciudad del Cabo)', 'il-central-1' => 'Israel (Tel Aviv)', 'me-south-1' => 'Oriente Medio (Baréin)', 'me-central-1' => 'Oriente Medio (EAU)', 'ap-east-1' => 'Asia Pacífico (Hong Kong)', 'ap-east-2' => 'Asia Pacífico (Taipéi)', 'ap-south-1' => 'Asia Pacífico (Bombay)', 'ap-south-2' => 'Asia Pacífico (Hyderabad)', 'ap-southeast-1' => 'Asia Pacífico (Singapur)', 'ap-southeast-2' => 'Asia Pacífico (Sídney)', 'ap-southeast-3' => 'Asia Pacífico (Yakarta)', 'ap-southeast-4' => 'Asia Pacífico (Melbourne)', 'ap-southeast-5' => 'Asia Pacífico (Malasia)', 'ap-southeast-6' => 'Asia Pacífico (Nueva Zelanda)', 'ap-southeast-7' => 'Asia Pacífico (Tailandia)', 'ap-northeast-1' => 'Asia Pacífico (Tokio)', 'ap-northeast-2' => 'Asia Pacífico (Seúl)', 'ap-northeast-3' => 'Asia Pacífico (Osaka)', 'us-gov-east-1' => 'AWS GovCloud (EE. UU. Este)', 'us-gov-west-1' => 'AWS GovCloud (EE. UU. Oeste)' );

	public static function label() { return 'Amazon S3'; }
	public static function fields() {
		$regions = array();
		foreach ( self::REGIONS as $code => $name ) $regions[ $code ] = $code . ' · ' . $name;
		return array(
			's3_region' => array( 'label' => 'Región', 'type' => 'select', 'options' => $regions, 'default' => 'us-east-1', 'help' => 'La región donde creaste el bucket.' ),
			's3_bucket' => array( 'label' => 'Bucket', 'type' => 'text', 'required' => true, 'help' => 'Un bucket existente y privado (sin acceso público).' ),
			's3_access_key' => array( 'label' => 'Access key ID', 'type' => 'text', 'required' => true ),
			's3_secret_key' => array( 'label' => 'Secret access key', 'type' => 'password', 'secret' => true ),
			's3_path' => array( 'label' => 'Carpeta dentro del bucket', 'type' => 'text', 'default' => 'digitalisimo-backups', 'help' => 'Prefijo de las claves. Vacío: la raíz del bucket.' ),
			's3_style' => array( 'label' => 'Estilo de ruta', 'type' => 'select', 'default' => 'auto', 'options' => array( 'auto' => 'Automático (virtual; de ruta si el bucket lleva puntos)', 'virtual' => 'Virtual (bucket.s3.región.amazonaws.com)', 'path' => 'De ruta (s3.región.amazonaws.com/bucket)' ) ),
			's3_storage_class' => array( 'label' => 'Clase de almacenamiento', 'type' => 'select', 'default' => 'STANDARD', 'options' => array( 'STANDARD' => 'Standard', 'STANDARD_IA' => 'Standard-IA (acceso poco frecuente)', 'ONEZONE_IA' => 'One Zone-IA', 'INTELLIGENT_TIERING' => 'Intelligent-Tiering', 'GLACIER_IR' => 'Glacier Instant Retrieval' ), 'help' => 'Sólo clases con acceso inmediato: las de archivo impedirían verificar y restaurar.' ),
		);
	}
	public static function help() {
		return array( 'En la consola de AWS crea un bucket privado en la región elegida (con «Bloquear todo el acceso público»).', 'En IAM crea un usuario con una política limitada a ese bucket: s3:PutObject, s3:GetObject, s3:DeleteObject, s3:ListBucket y s3:AbortMultipartUpload.', 'Crea una Access key para ese usuario y pega aquí el Access key ID y la Secret access key.', 'Recomendado: una regla de ciclo de vida «AbortIncompleteMultipartUpload» de 7 días en el bucket.' );
	}
	public static function sanitize( array $input, array $old ) { return self::sanitize_common( parent::sanitize( $input, $old ) ); }
	protected function storage_class() { return (string) ( $this->s['s3_storage_class'] ?? 'STANDARD' ); }
	protected function endpoint() {
		$region = (string) $this->s['s3_region'];
		if ( ! isset( self::REGIONS[ $region ] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Elige la región de Amazon S3.' );
		$style = $this->style();
		if ( 'auto' === $style ) $style = false !== strpos( $this->bucket(), '.' ) ? 'path' : 'virtual';
		return array( 'scheme' => 'https', 'host' => 's3.' . $region . '.amazonaws.com', 'region' => $region, 'style' => $style, 'verify' => true, 'ca' => '' );
	}
}

final class Digitalisimo_Backups_Destination_S3_Compatible extends Digitalisimo_Backups_Destination_S3_Base {
	const TYPE = 's3compat';
	const PREFIX = 's3compat_';
	/** DigitalOcean Spaces (docs.digitalocean.com/products/spaces/details/availability, 2026-11-09). */
	const DO_REGIONS = array( 'nyc3' => 'Nueva York', 'sfo2' => 'San Francisco 2', 'sfo3' => 'San Francisco 3', 'tor1' => 'Toronto', 'atl1' => 'Atlanta', 'ric1' => 'Richmond', 'mkc1' => 'Kansas City', 'ams3' => 'Ámsterdam', 'fra1' => 'Fráncfort', 'lon1' => 'Londres', 'sgp1' => 'Singapur', 'blr1' => 'Bangalore', 'syd1' => 'Sídney' );
	/** Wasabi (docs.wasabi.com, «service URLs for Wasabi's storage regions», 2026-11-09). */
	const WASABI_REGIONS = array( 'us-east-1' => 'N. Virginia', 'us-east-2' => 'N. Virginia 2', 'us-central-1' => 'Texas', 'us-west-1' => 'Oregón', 'us-west-2' => 'San José', 'ca-central-1' => 'Toronto', 'eu-central-1' => 'Ámsterdam', 'eu-central-2' => 'Fráncfort', 'eu-west-1' => 'Reino Unido', 'eu-west-2' => 'París', 'eu-west-3' => 'Reino Unido 2', 'eu-south-1' => 'Milán', 'ap-northeast-1' => 'Tokio', 'ap-northeast-2' => 'Osaka', 'ap-southeast-1' => 'Singapur', 'ap-southeast-2' => 'Sídney' );
	const PROVIDERS = array( 'digitalocean' => 'DigitalOcean Spaces', 'wasabi' => 'Wasabi', 'r2' => 'Cloudflare R2', 'backblaze' => 'Backblaze B2 (API compatible con S3)', 'linode' => 'Akamai / Linode Object Storage', 'vultr' => 'Vultr Object Storage', 'other' => 'Otro compatible (MinIO, Ceph, Garage…)' );

	public static function label() { return 'S3 compatible'; }
	public static function fields() {
		$do = array();
		foreach ( self::DO_REGIONS as $code => $name ) $do[ $code ] = $code . ' · ' . $name;
		$wasabi = array();
		foreach ( self::WASABI_REGIONS as $code => $name ) $wasabi[ $code ] = $code . ' · ' . $name;
		return array(
			's3compat_provider' => array( 'label' => 'Proveedor', 'type' => 'select', 'default' => 'digitalocean', 'options' => self::PROVIDERS ),
			's3compat_do_region' => array( 'label' => 'Región de Spaces', 'type' => 'select', 'default' => 'nyc3', 'options' => $do, 'show' => 's3compat_provider=digitalocean' ),
			's3compat_wasabi_region' => array( 'label' => 'Región de Wasabi', 'type' => 'select', 'default' => 'us-east-1', 'options' => $wasabi, 'show' => 's3compat_provider=wasabi' ),
			's3compat_account' => array( 'label' => 'Account ID de Cloudflare', 'type' => 'text', 'show' => 's3compat_provider=r2', 'pattern' => '[0-9a-fA-F]{32}', 'help' => 'Los 32 caracteres que aparecen en R2 → «Account details».' ),
			's3compat_jurisdiction' => array( 'label' => 'Jurisdicción de R2', 'type' => 'select', 'default' => 'default', 'show' => 's3compat_provider=r2', 'options' => array( 'default' => 'Predeterminada', 'eu' => 'Unión Europea', 'fedramp' => 'FedRAMP' ) ),
			's3compat_endpoint' => array( 'label' => 'Endpoint S3', 'type' => 'text', 'show' => 's3compat_provider=backblaze|linode|vultr|other', 'help' => 'Backblaze: s3.<región>.backblazeb2.com (en los detalles del bucket). Akamai/Linode: <región>.linodeobjects.com. Vultr: <ubicación>.vultrobjects.com. Otro: la URL completa, p. ej. https://minio.ejemplo.com:9000.' ),
			's3compat_signing_region' => array( 'label' => 'Región para la firma', 'type' => 'text', 'default' => 'us-east-1', 'show' => 's3compat_provider=other', 'help' => 'La región configurada en el servidor (MinIO usa us-east-1 por defecto).' ),
			's3compat_bucket' => array( 'label' => 'Bucket', 'type' => 'text', 'required' => true ),
			's3compat_access_key' => array( 'label' => 'Access key', 'type' => 'text', 'required' => true, 'help' => 'Backblaze: el keyID de una application key normal (la maestra no sirve con la API S3).' ),
			's3compat_secret_key' => array( 'label' => 'Secret key', 'type' => 'password', 'secret' => true ),
			's3compat_path' => array( 'label' => 'Carpeta dentro del bucket', 'type' => 'text', 'default' => 'digitalisimo-backups' ),
			's3compat_style' => array( 'label' => 'Estilo de ruta', 'type' => 'select', 'default' => 'auto', 'options' => array( 'auto' => 'Automático (el que recomienda el proveedor)', 'virtual' => 'Virtual (bucket.servidor)', 'path' => 'De ruta (servidor/bucket)' ) ),
			's3compat_ca' => array( 'label' => 'CA propia (PEM)', 'type' => 'textarea', 'show' => 's3compat_provider=other', 'help' => 'Sólo si el servidor usa un certificado de una autoridad propia: pega su certificado PEM. Vacío: las autoridades públicas de WordPress.' ),
			's3compat_insecure' => array( 'label' => 'Verificación TLS', 'type' => 'checkbox', 'text' => 'No verificar el certificado del servidor (inseguro)', 'show' => 's3compat_provider=other', 'warning' => 'Sin verificar el certificado, cualquiera en la red puede hacerse pasar por el servidor y leer las claves y los respaldos. Usa mejor la CA propia.' ),
			's3compat_allow_http' => array( 'label' => 'HTTP sin cifrar', 'type' => 'checkbox', 'text' => 'Permitir un endpoint http:// (inseguro)', 'show' => 's3compat_provider=other', 'warning' => 'Por http la firma no expone la Secret key, pero los respaldos viajan sin cifrar.' ),
		);
	}
	public static function help() {
		return array( 'Crea un bucket privado en el proveedor y una clave de acceso con permiso de lectura, escritura, listado y borrado sobre ese bucket.', 'Elige el proveedor y su región o endpoint: los campos cambian según el proveedor.', 'Recomendado: una regla de ciclo de vida que retire las subidas por partes incompletas (R2 lo hace a los 7 días).' );
	}
	public static function sanitize( array $input, array $old ) {
		$out = self::sanitize_common( parent::sanitize( $input, $old ) );
		$provider = $out['s3compat_provider'];
		$endpoint = trim( (string) $out['s3compat_endpoint'] );
		if ( 'r2' === $provider && '' !== $out['s3compat_account'] && ! preg_match( '/^[0-9a-f]{32}$/i', $out['s3compat_account'] ) ) throw new Exception( 'El Account ID de Cloudflare son 32 caracteres hexadecimales.' );
		$out['s3compat_account'] = strtolower( $out['s3compat_account'] );
		if ( in_array( $provider, array( 'backblaze', 'linode', 'vultr' ), true ) ) {
			$endpoint = strtolower( preg_replace( '#^https://#i', '', rtrim( $endpoint, '/' ) ) );
			if ( '' !== $endpoint && '' === self::provider_region( $provider, $endpoint ) ) throw new Exception( array( 'backblaze' => 'El endpoint de Backblaze tiene la forma s3.<región>.backblazeb2.com.', 'linode' => 'El endpoint de Akamai/Linode tiene la forma <región>.linodeobjects.com.', 'vultr' => 'El endpoint de Vultr tiene la forma <ubicación>.vultrobjects.com.' )[ $provider ] );
		} elseif ( 'other' === $provider && '' !== $endpoint ) {
			$parts = parse_url( $endpoint );
			$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
			if ( ! in_array( $scheme, array( 'https', 'http' ), true ) || empty( $parts['host'] ) || ! self::valid_host( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['query'] ) || ( isset( $parts['path'] ) && '/' !== $parts['path'] ) ) throw new Exception( 'El endpoint debe ser una URL como https://servidor:9000, sin ruta ni credenciales.' );
			if ( 'http' === $scheme && empty( $out['s3compat_allow_http'] ) ) throw new Exception( 'El endpoint usa http:// sin cifrar: marca «Permitir un endpoint http://» si de verdad lo quieres, o usa https://.' );
			$endpoint = $scheme . '://' . strtolower( $parts['host'] ) . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
		}
		$out['s3compat_endpoint'] = $endpoint;
		if ( ! self::valid_pem( $out['s3compat_ca'] ) ) throw new Exception( 'La CA propia debe ser uno o más certificados PEM completos (-----BEGIN CERTIFICATE----- … -----END CERTIFICATE-----).' );
		if ( '' === trim( (string) $out['s3compat_signing_region'] ) ) $out['s3compat_signing_region'] = 'us-east-1';
		if ( ! preg_match( '/^[a-z0-9-]{1,40}$/', $out['s3compat_signing_region'] ) ) throw new Exception( 'La región para la firma sólo admite minúsculas, números y guiones.' );
		return $out;
	}
	/** El endpoint escrito (Backblaze, Linode, Vultr u otro); los de DigitalOcean, Wasabi y R2 salen de su región o cuenta. */
	public static function hosts( array $s ) {
		$endpoint = (string) ( $s['s3compat_endpoint'] ?? '' );
		if ( '' === $endpoint ) return array();
		return array( false !== strpos( $endpoint, '://' ) ? (string) parse_url( $endpoint, PHP_URL_HOST ) : (string) preg_replace( '#[:/].*$#', '', $endpoint ) );
	}
	public static function ready( array $s ) {
		if ( ! parent::ready( $s ) ) return false;
		$provider = (string) ( $s['s3compat_provider'] ?? '' );
		if ( 'r2' === $provider ) return ! empty( $s['s3compat_account'] );
		if ( in_array( $provider, array( 'backblaze', 'linode', 'vultr', 'other' ), true ) ) return ! empty( $s['s3compat_endpoint'] );
		return isset( self::PROVIDERS[ $provider ] );
	}
	/** Región de firma a partir del endpoint de Backblaze, Linode o Vultr ('' si no tiene su forma). */
	public static function provider_region( $provider, $host ) {
		$patterns = array( 'backblaze' => '/^s3\.([a-z0-9-]+)\.backblazeb2\.com$/', 'linode' => '/^([a-z0-9-]+)\.linodeobjects\.com$/', 'vultr' => '/^([a-z0-9-]+)\.vultrobjects\.com$/' );
		return isset( $patterns[ $provider ] ) && preg_match( $patterns[ $provider ], (string) $host, $match ) ? $match[1] : '';
	}
	protected function endpoint() {
		$s = $this->s;
		$provider = (string) $s['s3compat_provider'];
		$scheme = 'https';
		$verify = true;
		$ca = '';
		$default_style = 'path';
		switch ( $provider ) {
			case 'digitalocean':
				$region = (string) $s['s3compat_do_region'];
				if ( ! isset( self::DO_REGIONS[ $region ] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Elige la región de DigitalOcean Spaces.' );
				// Spaces firma con us-east-1 (así lo indican sus ejemplos con los SDK de AWS); la región va en el host.
				$host = $region . '.digitaloceanspaces.com';
				$signing = 'us-east-1';
				$default_style = 'virtual';
				break;
			case 'wasabi':
				$signing = (string) $s['s3compat_wasabi_region'];
				if ( ! isset( self::WASABI_REGIONS[ $signing ] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Elige la región de Wasabi.' );
				$host = 's3.' . $signing . '.wasabisys.com';
				$default_style = 'virtual';
				break;
			case 'r2':
				if ( ! preg_match( '/^[0-9a-f]{32}$/', (string) $s['s3compat_account'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Escribe el Account ID de Cloudflare.' );
				$jurisdiction = (string) ( $s['s3compat_jurisdiction'] ?? 'default' );
				$host = $s['s3compat_account'] . ( 'eu' === $jurisdiction ? '.eu' : ( 'fedramp' === $jurisdiction ? '.fedramp' : '' ) ) . '.r2.cloudflarestorage.com';
				$signing = 'auto';
				break;
			case 'backblaze':
			case 'linode':
			case 'vultr':
				$host = (string) $s['s3compat_endpoint'];
				$signing = self::provider_region( $provider, $host );
				if ( '' === $signing ) throw new Digitalisimo_Backups_Permanent_Error( 'Revisa el endpoint de ' . self::PROVIDERS[ $provider ] . '.' );
				break;
			case 'other':
				$parts = parse_url( (string) $s['s3compat_endpoint'] );
				if ( empty( $parts['host'] ) || ! self::valid_host( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ( isset( $parts['path'] ) && '/' !== $parts['path'] ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El endpoint del servicio compatible con S3 no es válido; revísalo en Backups → Servidores.' );
				$scheme = strtolower( (string) $parts['scheme'] );
				if ( 'http' === $scheme && empty( $s['s3compat_allow_http'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El endpoint usa http:// sin permitirlo de forma explícita.' );
				$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
				$host = $parts['host'] . ( $port && ! ( ( 'https' === $scheme && 443 === $port ) || ( 'http' === $scheme && 80 === $port ) ) ? ':' . $port : '' );
				$signing = (string) ( $s['s3compat_signing_region'] ?: 'us-east-1' );
				if ( ! preg_match( '/^[a-z0-9-]{1,40}$/D', $signing ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La región para la firma no es válida; revísala en Backups → Servidores.' );
				$verify = empty( $s['s3compat_insecure'] );
				$ca = $this->ca_file( (string) ( $s['s3compat_ca'] ?? '' ) );
				break;
			default:
				throw new Digitalisimo_Backups_Permanent_Error( 'Elige el proveedor compatible con S3.' );
		}
		$style = $this->style();
		if ( 'auto' === $style ) $style = 'virtual' === $default_style && false === strpos( $this->bucket(), '.' ) ? 'virtual' : 'path';
		return array( 'scheme' => $scheme, 'host' => $host, 'region' => $signing, 'style' => $style, 'verify' => $verify, 'ca' => $ca );
	}
}
