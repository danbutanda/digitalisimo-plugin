<?php
defined( 'ABSPATH' ) || exit;

/**
 * pCloud (API HTTP/JSON de docs.pcloud.com). OAuth 2.0 con código: pCloud devuelve en la redirección el servidor de la
 * región del usuario (api.pcloud.com en EE. UU., eapi.pcloud.com en Europa) y un token de acceso sin caducidad ni
 * refresh token, que se guarda cifrado. Las llamadas llevan el token en el parámetro `access_token` (así lo usa el SDK
 * oficial de PHP): nunca se registra una URL.
 *
 * Subida: `uploadfile` por PUT con el cuerpo del archivo en streaming y `nopartial=1` (pCloud descarta lo recibido si la
 * conexión se corta), a `<nombre>.part-<aleatorio>`. Verificación con `checksumfile`: pCloud devuelve el SHA-256 en
 * Europa y el SHA-1 en ambas regiones, calculados sobre lo guardado; se comparan con los locales y el tamaño.
 * Publicación con `renamefile`. Sin reanudación: un reintento sube el archivo entero (límite declarado).
 */
final class Digitalisimo_Backups_Destination_Pcloud extends Digitalisimo_Backups_Destination {
	const TYPE = 'pcloud';
	const PREFIX = 'pcloud_';
	const HOSTS = array( 'api.pcloud.com' => 'EE. UU.', 'eapi.pcloud.com' => 'Europa' );
	private $folder_id = null;

	public static function label() { return 'pCloud'; }
	public static function oauth() { return true; }
	public static function fields() {
		return array(
			'pcloud_client_id' => array( 'label' => 'Client ID', 'type' => 'text' ),
			'pcloud_client_secret' => array( 'label' => 'Client secret', 'type' => 'password', 'secret' => true ),
			'pcloud_access_token' => array( 'label' => 'Token de acceso', 'type' => 'password', 'secret' => true, 'help' => 'Lo guarda «Conectar pCloud».' ),
			'pcloud_host' => array( 'label' => 'Región', 'type' => 'select', 'default' => 'api.pcloud.com', 'options' => array( 'api.pcloud.com' => 'EE. UU. (api.pcloud.com)', 'eapi.pcloud.com' => 'Europa (eapi.pcloud.com)' ), 'help' => 'La fija pCloud al conectar, según dónde está la cuenta.' ),
			'pcloud_folder' => array( 'label' => 'Carpeta', 'type' => 'text', 'default' => '/Digitalisimo Backups' ),
		);
	}
	public static function ready( array $s ) { return ! empty( $s['pcloud_access_token'] ) && isset( self::HOSTS[ (string) ( $s['pcloud_host'] ?? '' ) ] ); }
	public static function sanitize( array $input, array $old ) {
		$out = parent::sanitize( $input, $old );
		$out['pcloud_folder'] = self::clean_path( $out['pcloud_folder'], '/Digitalisimo Backups' );
		return $out;
	}
	/** Servidor de la API que indicó pCloud al volver de la autorización (sólo los dos oficiales). */
	public static function api_host( $hostname, $locationid = '' ) {
		$hostname = strtolower( trim( (string) $hostname ) );
		if ( isset( self::HOSTS[ $hostname ] ) ) return $hostname;
		return '2' === (string) $locationid ? 'eapi.pcloud.com' : 'api.pcloud.com';
	}
	/** Cambia el código por el token de acceso; '' si pCloud no lo da. */
	public static function exchange_code( $host, $client_id, $client_secret, $code ) {
		if ( ! isset( self::HOSTS[ $host ] ) ) return '';
		try {
			$response = Digitalisimo_Backups_Http::request( 'POST', 'https://' . $host . '/oauth2_token', array( 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ), 'body' => http_build_query( array( 'client_id' => $client_id, 'client_secret' => $client_secret, 'code' => $code ), '', '&' ), 'timeout' => 30 ) );
		} catch ( Exception $e ) {
			return '';
		}
		$data = json_decode( $response['body'], true );
		return 200 === $response['code'] && 0 === (int) ( $data['result'] ?? -1 ) && ! empty( $data['access_token'] ) ? (string) $data['access_token'] : '';
	}

	/* ------------------------------------------------------------------ API */

	private function host() {
		$host = (string) ( $this->s['pcloud_host'] ?? '' );
		if ( ! isset( self::HOSTS[ $host ] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Conecta pCloud de nuevo para conocer la región de la cuenta.' );
		return $host;
	}
	public function prepare() { $this->token(); }
	private function token() {
		$token = (string) ( $this->s['pcloud_access_token'] ?? '' );
		if ( '' === $token ) {
			$problem = class_exists( 'Digitalisimo_Backups' ) ? Digitalisimo_Backups::destination_problem( (string) $this->context['connection'] ) : '';
			throw new Digitalisimo_Backups_Permanent_Error( '' !== $problem ? $problem : 'Conecta pCloud antes de usarlo como destino.' );
		}
		return $token;
	}
	/** Método de la API con sus parámetros (POST, salvo $args['file'] para subir por PUT). Devuelve el JSON. */
	private function call( $method, array $params, array $args = array() ) {
		$params['access_token'] = $this->token();
		$url = 'https://' . $this->host() . '/' . $method;
		if ( ! empty( $args['file'] ) || ! empty( $args['get'] ) ) {
			unset( $args['get'] );
			$response = Digitalisimo_Backups_Http::request( empty( $args['file'] ) ? 'GET' : 'PUT', $url . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ), array_merge( array( 'timeout' => 0 ), $args ) );
		} else {
			$response = Digitalisimo_Backups_Http::request( 'POST', $url, array( 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ), 'body' => http_build_query( $params, '', '&' ), 'timeout' => 60 ) );
		}
		$data = json_decode( $response['body'], true );
		if ( 200 !== $response['code'] || ! is_array( $data ) ) throw new Exception( 'pCloud no respondió como se esperaba (HTTP ' . $response['code'] . ').' );
		return $data;
	}
	/** Errores de pCloud: los de sesión y permisos (1000, 2000, 2003, 2094…) no se arreglan reintentando. */
	private function check( array $data, $what ) {
		$result = (int) ( $data['result'] ?? -1 );
		if ( 0 === $result ) return $data;
		$message = 'pCloud rechazó ' . $what . ' (' . $result . ( isset( $data['error'] ) ? ': ' . (string) $data['error'] : '' ) . ').';
		if ( in_array( $result, array( 1000, 2000, 2003, 2008, 2094, 2095 ), true ) ) throw new Digitalisimo_Backups_Permanent_Error( $message . ( in_array( $result, array( 1000, 2000, 2094, 2095 ), true ) ? ' Conecta pCloud de nuevo.' : '' ) );
		throw new Exception( $message );
	}
	private function folder_id() {
		if ( null !== $this->folder_id ) return $this->folder_id;
		$folder = rtrim( self::clean_path( (string) ( $this->s['pcloud_folder'] ?? '' ), '/Digitalisimo Backups' ), '/' );
		if ( '' === $folder ) return $this->folder_id = 0;
		$id = 0;
		foreach ( explode( '/', ltrim( $folder, '/' ) ) as $part ) {
			$data = $this->check( $this->call( 'createfolderifnotexists', array( 'folderid' => $id, 'name' => $part ) ), 'la carpeta remota' );
			$id = (int) ( $data['metadata']['folderid'] ?? 0 );
		}
		return $this->folder_id = $id;
	}

	/* ------------------------------------------------------------------ Contrato */

	protected function upload( $file, $name, array $source ) {
		$temp_name = self::temporary_name( $name );
		$this->progress( 0, $source['size'] );
		$data = $this->check( $this->call( 'uploadfile', array( 'folderid' => $this->folder_id(), 'filename' => $temp_name, 'nopartial' => 1 ), array( 'file' => $file, 'progress' => function( $done, $total ) use ( $source ) { $this->progress( min( $done, $source['size'] ), $source['size'] ); } ) ), 'la subida del respaldo' );
		$id = (int) ( $data['metadata'][0]['fileid'] ?? ( $data['fileids'][0] ?? 0 ) );
		if ( $id <= 0 ) throw new Exception( 'pCloud no confirmó el archivo subido.' );
		return array( 'provider' => self::TYPE, 'id' => $id, 'name' => $temp_name );
	}
	/** checksumfile: SHA-256 (Europa) o SHA-1 (ambas regiones) calculado por pCloud sobre lo guardado, y el tamaño. */
	public function verify( array $remote, $file, array $source ) {
		$data = $this->check( $this->call( 'checksumfile', array( 'fileid' => (int) $remote['id'] ) ), 'la comprobación de la copia' );
		$size = (int) ( $data['metadata']['size'] ?? -1 );
		if ( ! empty( $data['sha256'] ) ) $match = hash_equals( $source['sha256'], strtolower( (string) $data['sha256'] ) );
		elseif ( ! empty( $data['sha1'] ) ) $match = hash_equals( (string) sha1_file( $file ), strtolower( (string) $data['sha1'] ) );
		else throw new Exception( 'pCloud no devolvió la suma de la copia subida.' );
		if ( ! $match || $size !== $source['size'] ) throw new Exception( 'La copia en pCloud no coincide con el respaldo local; no se registró como respaldo válido.' );
	}
	protected function publish( array $temp, $name ) {
		$data = $this->check( $this->call( 'renamefile', array( 'fileid' => (int) $temp['id'], 'toname' => $name ) ), 'la publicación del respaldo verificado' );
		return array( 'provider' => self::TYPE, 'id' => (int) ( $data['metadata']['fileid'] ?? $temp['id'] ), 'path' => (string) ( $data['metadata']['path'] ?? '' ) );
	}
	/** Un recibo de pCloud: su fileid numérico. */
	private static function own_id( array $remote ) {
		$id = $remote['id'] ?? '';
		if ( ! is_int( $id ) && ! ( is_string( $id ) && preg_match( '/^[0-9]{1,20}$/D', $id ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El identificador de la copia en pCloud no es válido; no se toca.' );
		return (int) $id;
	}
	/**
	 * Metadatos (`stat`) del archivo de un recibo, sólo si está en la carpeta de esta conexión (el token ve toda la
	 * cuenta: un fileid del historial no puede señalar otro archivo). null si ya no existe.
	 */
	private function own( array $remote ) {
		$data = $this->call( 'stat', array( 'fileid' => self::own_id( $remote ) ) );
		if ( 2009 === (int) ( $data['result'] ?? -1 ) ) return null;
		$data = $this->check( $data, 'la consulta del respaldo' );
		$meta = (array) ( $data['metadata'] ?? array() );
		if ( ! empty( $meta['isdeleted'] ) ) return null;
		if ( ! empty( $meta['isfolder'] ) || ! isset( $meta['parentfolderid'] ) || (int) $meta['parentfolderid'] !== $this->folder_id() ) throw new Digitalisimo_Backups_Permanent_Error( 'La copia remota no está en la carpeta de esta conexión de pCloud; no se toca.' );
		return $meta;
	}
	public function get( array $remote, $target ) {
		if ( null === $this->own( $remote ) ) throw new Exception( 'El respaldo ya no está en pCloud.' );
		$data = $this->check( $this->call( 'getfilelink', array( 'fileid' => self::own_id( $remote ), 'forcedownload' => 1 ) ), 'el enlace de descarga' );
		$hosts = (array) ( $data['hosts'] ?? array() );
		$path = (string) ( $data['path'] ?? '' );
		if ( ! $hosts || '' === $path || ! preg_match( '/^[a-z0-9.-]+\.pcloud\.com$/i', (string) $hosts[0] ) ) throw new Exception( 'pCloud no devolvió un enlace de descarga válido.' );
		$response = Digitalisimo_Backups_Http::request( 'GET', 'https://' . $hosts[0] . $path, array( 'sink' => $target, 'timeout' => 0, 'progress' => function( $done, $total ) { if ( $total > 0 ) $this->progress( $done, $total, 'Descargados' ); } ) );
		if ( 200 !== $response['code'] ) throw new Exception( 'No se pudo descargar el respaldo de pCloud (HTTP ' . $response['code'] . ').' );
	}
	public function size( array $remote ) {
		$meta = $this->own( $remote );
		return null === $meta ? false : (int) ( $meta['size'] ?? 0 );
	}
	public function delete( array $remote ) {
		if ( null === $this->own( $remote ) ) return;
		$data = $this->call( 'deletefile', array( 'fileid' => self::own_id( $remote ) ) );
		if ( 2009 === (int) ( $data['result'] ?? -1 ) ) return;
		$this->check( $data, 'el borrado del respaldo' );
	}
	/** B1b: el enlace de `getfilelink` admite `Range`; se pide uno por fragmento (caducan). */
	public function ranged() { return true; }
	public function get_range( array $remote, $target, $offset, $length, array $limits = array() ) {
		if ( null === $this->own( $remote ) ) throw new Exception( 'El respaldo ya no está en pCloud.' );
		$data = $this->check( $this->call( 'getfilelink', array( 'fileid' => self::own_id( $remote ), 'forcedownload' => 1 ) ), 'el enlace de descarga' );
		$hosts = (array) ( $data['hosts'] ?? array() );
		$path = (string) ( $data['path'] ?? '' );
		if ( ! $hosts || '' === $path || ! preg_match( '/^[a-z0-9.-]+\.pcloud\.com$/i', (string) $hosts[0] ) ) throw new Exception( 'pCloud no devolvió un enlace de descarga válido.' );
		$range = self::range_args( $offset, $length, $limits );
		return self::range_reply( Digitalisimo_Backups_Http::request( 'GET', 'https://' . $hosts[0] . $path, array( 'headers' => $range['headers'], 'sink' => $target, 'timeout' => $range['timeout'], 'max' => $range['max'] ) ), $target, $offset, $length, $limits );
	}
	public function list() {
		$data = $this->check( $this->call( 'listfolder', array( 'folderid' => $this->folder_id() ) ), 'el listado de la carpeta' );
		$out = array();
		foreach ( (array) ( $data['metadata']['contents'] ?? array() ) as $item ) {
			if ( ! empty( $item['isfolder'] ) ) continue;
			$out[] = array( 'name' => (string) ( $item['name'] ?? '' ), 'size' => (int) ( $item['size'] ?? 0 ), 'remote' => array( 'provider' => self::TYPE, 'connection' => (string) $this->context['connection'], 'id' => (int) ( $item['fileid'] ?? 0 ) ) );
		}
		return $out;
	}
}
