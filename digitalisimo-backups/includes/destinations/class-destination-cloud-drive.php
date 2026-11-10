<?php
defined( 'ABSPATH' ) || exit;

/**
 * Google Drive y OneDrive (OAuth con refresh token). Conservan el comportamiento de 1.0.47: subida reanudable por
 * fragmentos con el nombre final, descarga de la copia a un temporal privado y comparación de tamaño y SHA-256; si no
 * coincide se retira del destino. Desde esta versión `get()` descarga en streaming a disco (antes la descarga al
 * navegador cargaba el respaldo entero en memoria).
 */
abstract class Digitalisimo_Backups_Destination_Cloud_Drive extends Digitalisimo_Backups_Destination {
	const TOKEN_URL = '';
	const CHUNK = 8388608;
	private $access = '';

	public static function oauth() { return true; }
	public static function ready( array $s ) { return ! empty( $s[ static::PREFIX . 'refresh_token' ] ); }

	/** Token de acceso a partir del refresh token (las credenciales que no abren dicen qué volver a escribir). */
	public function token() {
		if ( '' !== $this->access ) return $this->access;
		return $this->access = self::refresh( static::TOKEN_URL, $this->s, rtrim( static::PREFIX, '_' ), (string) $this->context['connection'] );
	}
	public static function refresh( $url, array $s, $prefix, $connection = '' ) {
		if ( empty( $s[ $prefix . '_client_id' ] ) || empty( $s[ $prefix . '_client_secret' ] ) || empty( $s[ $prefix . '_refresh_token' ] ) ) {
			$problem = class_exists( 'Digitalisimo_Backups' ) ? Digitalisimo_Backups::destination_problem( '' !== $connection ? $connection : $prefix ) : '';
			throw new Digitalisimo_Backups_Permanent_Error( '' !== $problem ? $problem : 'Completa Client ID, Client secret y Refresh token del destino.' );
		}
		$response = wp_remote_post( $url, array( 'timeout' => 30, 'body' => array( 'client_id' => $s[ $prefix . '_client_id' ], 'client_secret' => $s[ $prefix . '_client_secret' ], 'refresh_token' => $s[ $prefix . '_refresh_token' ], 'grant_type' => 'refresh_token' ) ) );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_wp_error( $response ) || empty( $data['access_token'] ) ) throw new Exception( 'No se pudo renovar el acceso al almacenamiento remoto.' );
		return $data['access_token'];
	}

	/** URL de descarga, de borrado y de metadatos de un elemento. */
	abstract public static function content_url( $id );
	abstract public static function item_url( $id );

	public function put( $file, $name = '' ) {
		$token = $this->token();
		$id = $this->upload_with_token( $file, '' !== (string) $name ? (string) $name : self::basename( $file ), $token );
		$this->keep_verified( $id, $file, $token );
		return array( 'provider' => static::TYPE, 'id' => $id, 'sha256' => hash_file( 'sha256', $file ), 'connection' => (string) $this->context['connection'], 'size' => (int) filesize( $file ) );
	}
	abstract protected function upload_with_token( $file, $name, $token );
	protected function upload( $file, $name, array $source ) { return array( 'provider' => static::TYPE, 'id' => $this->upload_with_token( $file, $name, $this->token() ) ); }
	protected function publish( array $temp, $name ) { return $temp; }

	/**
	 * Una copia subida que no supera la verificación se retira del destino: con los reintentos de la transferencia, cada
	 * intento subiría otra, y ninguna copia sin verificar debe quedarse allí con el nombre de un respaldo.
	 */
	public function keep_verified( $id, $source, $token ) {
		try {
			$this->verify_download( $id, $source, $token );
		} catch ( Exception $e ) {
			$response = wp_remote_request( static::item_url( $id ), array( 'method' => 'DELETE', 'timeout' => 60, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
			$removed = ! is_wp_error( $response ) && in_array( (int) wp_remote_retrieve_response_code( $response ), array( 200, 204, 404 ), true );
			throw new Exception( $e->getMessage() . ( $removed ? ' Se retiró del destino la copia sin verificar.' : ' No se pudo retirar del destino la copia sin verificar; elimínala desde ' . static::label() . '.' ), 0, $e );
		}
	}
	/** Descarga a un temporal privado y comprueba bytes y SHA-256 antes de registrar éxito. */
	public function verify_download( $id, $source, $token ) {
		$temp = $this->temp_file( '.cloud-check-' );
		$provider = static::TYPE;
		try {
			$response = wp_remote_get( static::content_url( $id ), array( 'timeout' => 300, 'redirection' => 5, 'stream' => true, 'filename' => $temp, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) throw new Exception( 'No se pudo descargar la copia para verificarla desde ' . $provider . '.' );
			$source_size = @filesize( $source );
			$remote_size = @filesize( $temp );
			$source_hash = @hash_file( 'sha256', $source );
			$remote_hash = @hash_file( 'sha256', $temp );
			if ( false === $source_size || false === $remote_size || $source_size !== $remote_size || false === $source_hash || false === $remote_hash || ! hash_equals( $source_hash, $remote_hash ) ) throw new Exception( 'La copia remota de ' . $provider . ' no coincide con el ZIP local; no se registró como respaldo válido.' );
		} finally { @unlink( $temp ); }
	}
	public function verify( array $remote, $file, array $source ) { $this->verify_download( (string) $remote['id'], $file, $this->token() ); }

	/** Sube en fragmentos para que archivos grandes no agoten la memoria del hosting. */
	public function resumable_upload( $url, $file, $headers = array(), $chunk_size = 8388608 ) {
		$size = filesize( $file );
		$handle = @fopen( $file, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer el ZIP para transferirlo.' );
		$offset = 0;
		$response = array();
		try {
			while ( ! feof( $handle ) ) {
				// Latido y cancelación entre fragmentos.
				$this->progress( $offset, $size );
				$chunk = fread( $handle, $chunk_size );
				if ( false === $chunk || '' === $chunk ) break;
				$end = $offset + strlen( $chunk ) - 1;
				$response = wp_remote_request( $url, array( 'method' => 'PUT', 'timeout' => 120, 'headers' => array_merge( $headers, array( 'Content-Length' => strlen( $chunk ), 'Content-Range' => 'bytes ' . $offset . '-' . $end . '/' . $size ) ), 'body' => $chunk ) );
				$code = wp_remote_retrieve_response_code( $response );
				if ( is_wp_error( $response ) || ! in_array( $code, array( 200, 201, 202, 308 ), true ) ) throw new Exception( 'El almacenamiento remoto interrumpió la transferencia.' );
				$offset = $end + 1;
			}
			if ( $offset !== $size ) throw new Exception( 'La transferencia remota quedó incompleta.' );
		} catch ( Throwable $e ) {
			// Se retira la sesión de carga incompleta para no dejar temporales en el destino.
			@wp_remote_request( $url, array( 'method' => 'DELETE', 'timeout' => 15 ) );
			throw $e;
		} finally {
			fclose( $handle );
		}
		return $response;
	}

	/** Identificador de un recibo: sólo caracteres de un id de Drive u OneDrive (sin «/», «?», «#» ni de control). */
	protected static function own_id( array $remote ) {
		$id = (string) ( $remote['id'] ?? '' );
		if ( ! preg_match( '/^[A-Za-z0-9!_.\-]{1,200}$/D', $id ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El identificador de la copia en ' . static::label() . ' no es válido; no se toca.' );
		return $id;
	}
	/**
	 * Comprueba que el archivo del recibo está en la carpeta de esta conexión antes de tocarlo: false si ya no existe.
	 * Google Drive no lo necesita: con el alcance drive.file la app sólo ve los archivos que creó (sus respaldos).
	 */
	protected function confine( $id ) { return true; }
	/** Descarga en streaming a un archivo (memoria constante). */
	public function get( array $remote, $target ) {
		if ( ! $this->confine( self::own_id( $remote ) ) ) throw new Exception( 'El respaldo ya no está en ' . static::label() . '.' );
		$response = wp_remote_get( static::content_url( self::own_id( $remote ) ), array( 'timeout' => 600, 'redirection' => 5, 'stream' => true, 'filename' => $target, 'headers' => array( 'Authorization' => 'Bearer ' . $this->token() ) ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) throw new Exception( 'No se pudo descargar el respaldo remoto.' );
	}
	/** B1b: Google Drive (`alt=media`) y OneDrive (`/content`) admiten `Range`. */
	public function ranged() { return true; }
	public function get_range( array $remote, $target, $offset, $length, array $limits = array() ) {
		if ( ! $this->confine( self::own_id( $remote ) ) ) throw new Exception( 'El respaldo ya no está en ' . static::label() . '.' );
		$range = self::range_args( $offset, $length, $limits );
		// `limit_response_size`: la API HTTP de WordPress deja de escribir al pasar el tope (también desde el byte 0).
		$response = wp_remote_get( static::content_url( self::own_id( $remote ) ), array( 'timeout' => $range['timeout'], 'redirection' => 5, 'stream' => true, 'filename' => $target, 'limit_response_size' => $range['max'], 'headers' => array( 'Authorization' => 'Bearer ' . $this->token() ) + $range['headers'] ) );
		if ( is_wp_error( $response ) ) throw new Exception( 'No se pudo descargar un fragmento del respaldo remoto.' );
		$headers = function_exists( 'wp_remote_retrieve_header' ) ? array( 'content-range' => (string) wp_remote_retrieve_header( $response, 'content-range' ) ) : array();
		return self::range_reply( array( 'code' => (int) wp_remote_retrieve_response_code( $response ), 'headers' => $headers ), $target, $offset, $length, $limits );
	}
	public function size( array $remote ) {
		if ( ! $this->confine( self::own_id( $remote ) ) ) return false;
		$response = wp_remote_get( static::metadata_url( self::own_id( $remote ) ), array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $this->token() ) ) );
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $code ) return false;
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! isset( $data['size'] ) ) throw new Exception( 'No se pudo consultar el respaldo en ' . static::label() . '.' );
		if ( ! empty( $data['trashed'] ) ) return false;
		return (int) $data['size'];
	}
	public function delete( array $remote ) {
		if ( ! $this->confine( self::own_id( $remote ) ) ) return;
		$response = wp_remote_request( static::item_url( self::own_id( $remote ) ), array( 'method' => 'DELETE', 'timeout' => 60, 'headers' => array( 'Authorization' => 'Bearer ' . $this->token() ) ) );
		if ( is_wp_error( $response ) || ! in_array( (int) wp_remote_retrieve_response_code( $response ), array( 200, 204, 404 ), true ) ) throw new Exception( 'El proveedor remoto no confirmó la eliminación.' );
	}
	abstract protected static function metadata_url( $id );
}

final class Digitalisimo_Backups_Destination_Google extends Digitalisimo_Backups_Destination_Cloud_Drive {
	const TYPE = 'google';
	const PREFIX = 'google_';
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	public static function label() { return 'Google Drive'; }
	public static function fields() {
		return array(
			'google_client_id' => array( 'label' => 'Client ID', 'type' => 'text' ),
			'google_client_secret' => array( 'label' => 'Client secret', 'type' => 'password', 'secret' => true ),
			'google_refresh_token' => array( 'label' => 'Refresh token', 'type' => 'password', 'secret' => true ),
			'google_folder_id' => array( 'label' => 'ID de carpeta', 'type' => 'text' ),
		);
	}
	public static function content_url( $id ) { return 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $id ) . '?alt=media'; }
	public static function item_url( $id ) { return 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $id ); }
	protected static function metadata_url( $id ) { return 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $id ) . '?fields=id,name,size,trashed'; }
	protected function upload_with_token( $file, $name, $token ) {
		$s = $this->s;
		$metadata = array( 'name' => $name );
		if ( ! empty( $s['google_folder_id'] ) ) $metadata['parents'] = array( $s['google_folder_id'] );
		$response = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json; charset=UTF-8', 'X-Upload-Content-Type' => self::mime( $file ), 'X-Upload-Content-Length' => filesize( $file ) ), 'body' => wp_json_encode( $metadata ) ) );
		$session = wp_remote_retrieve_header( $response, 'location' );
		if ( is_wp_error( $response ) || ! $session ) throw new Exception( 'Google Drive no pudo iniciar la carga.' );
		$result = json_decode( wp_remote_retrieve_body( $this->resumable_upload( $session, $file, array( 'Content-Type' => self::mime( $file ) ) ) ), true );
		if ( empty( $result['id'] ) ) throw new Exception( 'Google Drive no confirmó el archivo cargado.' );
		return sanitize_text_field( $result['id'] );
	}
	public function list() {
		$folder = (string) ( $this->s['google_folder_id'] ?? '' );
		$query = "trashed=false and '" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), '' !== $folder ? $folder : 'root' ) . "' in parents";
		$out = array();
		$page = '';
		do {
			$url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query( array( 'q' => $query, 'fields' => 'nextPageToken,files(id,name,size)', 'pageSize' => 1000, 'pageToken' => $page ), '', '&', PHP_QUERY_RFC3986 );
			$response = wp_remote_get( $url, array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $this->token() ) ) );
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $data['files'] ?? null ) ) throw new Exception( 'No se pudo listar la carpeta de Google Drive.' );
			foreach ( $data['files'] as $item ) $out[] = array( 'name' => (string) ( $item['name'] ?? '' ), 'size' => (int) ( $item['size'] ?? 0 ), 'remote' => array( 'provider' => self::TYPE, 'connection' => (string) $this->context['connection'], 'id' => (string) ( $item['id'] ?? '' ) ) );
			$page = (string) ( $data['nextPageToken'] ?? '' );
		} while ( '' !== $page );
		return $out;
	}
	private static function mime( $file ) { return '.dgbk' === substr( (string) $file, -5 ) ? 'application/octet-stream' : 'application/zip'; }
}

final class Digitalisimo_Backups_Destination_Onedrive extends Digitalisimo_Backups_Destination_Cloud_Drive {
	const TYPE = 'onedrive';
	const PREFIX = 'onedrive_';
	const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

	public static function label() { return 'OneDrive'; }
	public static function fields() {
		return array(
			'onedrive_client_id' => array( 'label' => 'Client ID', 'type' => 'text' ),
			'onedrive_client_secret' => array( 'label' => 'Client secret', 'type' => 'password', 'secret' => true ),
			'onedrive_refresh_token' => array( 'label' => 'Refresh token', 'type' => 'password', 'secret' => true ),
			'onedrive_folder' => array( 'label' => 'Carpeta remota', 'type' => 'text', 'default' => 'Digitalisimo Backups' ),
		);
	}
	public static function content_url( $id ) { return 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode( $id ) . '/content'; }
	public static function item_url( $id ) { return 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode( $id ); }
	protected static function metadata_url( $id ) { return 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode( $id ) . '?$select=id,name,size'; }
	private function folder_path() {
		$folder = trim( (string) $this->s['onedrive_folder'], '/' );
		return implode( '/', array_map( 'rawurlencode', array_filter( explode( '/', $folder ) ) ) );
	}
	/**
	 * El token de OneDrive ve toda la unidad: un id del historial sólo se toca si su carpeta (parentReference.path, una
	 * ruta codificada como «/drive/root:/Carpeta»; lo anterior a los dos puntos es la unidad) es la de esta conexión.
	 */
	protected function confine( $id ) {
		$response = wp_remote_get( 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode( $id ) . '?$select=id,parentReference', array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $this->token() ) ) );
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $code ) return false;
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $data ) ) throw new Exception( 'No se pudo consultar el respaldo en OneDrive.' );
		$path = (string) ( $data['parentReference']['path'] ?? '' );
		$colon = strpos( $path, ':' );
		$where = false === $colon ? null : trim( rawurldecode( substr( $path, $colon + 1 ) ), '/' );
		$folder = implode( '/', array_filter( explode( '/', trim( (string) $this->s['onedrive_folder'], '/' ) ) ) );
		$lower = function( $text ) { return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text ); };
		if ( null === $where || $lower( $where ) !== $lower( $folder ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La copia remota no está en la carpeta de esta conexión de OneDrive; no se toca.' );
		return true;
	}
	protected function upload_with_token( $file, $name, $token ) {
		$url = 'https://graph.microsoft.com/v1.0/me/drive/root:/' . $this->folder_path() . '/' . rawurlencode( $name ) . ':/createUploadSession';
		$response = wp_remote_post( $url, array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'item' => array( '@microsoft.graph.conflictBehavior' => 'rename' ) ) ) ) );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_wp_error( $response ) || empty( $data['uploadUrl'] ) ) throw new Exception( 'OneDrive no pudo iniciar la carga. Verifica que exista la carpeta remota.' );
		$result = json_decode( wp_remote_retrieve_body( $this->resumable_upload( $data['uploadUrl'], $file, array(), 3276800 ) ), true );
		if ( empty( $result['id'] ) ) throw new Exception( 'OneDrive no confirmó el archivo cargado.' );
		return sanitize_text_field( $result['id'] );
	}
	public function list() {
		$path = $this->folder_path();
		$url = 'https://graph.microsoft.com/v1.0/me/drive/' . ( '' !== $path ? 'root:/' . $path . ':/children' : 'root/children' ) . '?$select=id,name,size,file&$top=1000';
		$out = array();
		while ( '' !== $url ) {
			$response = wp_remote_get( $url, array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $this->token() ) ) );
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $data['value'] ?? null ) ) throw new Exception( 'No se pudo listar la carpeta de OneDrive.' );
			foreach ( $data['value'] as $item ) if ( isset( $item['file'] ) ) $out[] = array( 'name' => (string) ( $item['name'] ?? '' ), 'size' => (int) ( $item['size'] ?? 0 ), 'remote' => array( 'provider' => self::TYPE, 'connection' => (string) $this->context['connection'], 'id' => (string) ( $item['id'] ?? '' ) ) );
			$next = (string) ( $data['@odata.nextLink'] ?? '' );
			$url = 0 === strpos( $next, 'https://graph.microsoft.com/' ) ? $next : '';
		}
		return $out;
	}
}
