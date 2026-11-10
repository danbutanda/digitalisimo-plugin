<?php
defined( 'ABSPATH' ) || exit;

/**
 * WebDAV (RFC 4918) con autenticación básica sobre HTTPS verificado (CA pública o propia). http:// y no verificar el
 * certificado sólo con una casilla explícita y su advertencia.
 *
 * - MKCOL crea la carpeta remota por niveles (405 = ya existe; 409 = falta el padre).
 * - PUT del archivo entero en streaming (cURL, memoria constante) a `<nombre>.part-<aleatorio>`: WebDAV no define
 *   subidas por partes, así que un reintento lo repite entero (límite declarado).
 * - Verificación: GET en streaming a un temporal privado y comparación de tamaño y SHA-256.
 * - Publicación: MOVE con `Destination` absoluto y `Overwrite: F` (412 si ya existe); PROPFIND Depth 0 para el tamaño y
 *   Depth 1 para listar; DELETE (404 = ya no estaba).
 */
final class Digitalisimo_Backups_Destination_Webdav extends Digitalisimo_Backups_Destination {
	const TYPE = 'webdav';
	const PREFIX = 'webdav_';
	private $collection_ready = false;

	public static function label() { return 'WebDAV'; }
	public static function fields() {
		return array(
			'webdav_url' => array( 'label' => 'URL del servidor WebDAV', 'type' => 'text', 'placeholder' => 'https://nube.ejemplo.com/remote.php/dav/files/usuario/', 'help' => 'La dirección WebDAV de la cuenta (Nextcloud, ownCloud, Synology, Apache mod_dav…).' ),
			'webdav_user' => array( 'label' => 'Usuario', 'type' => 'text' ),
			'webdav_password' => array( 'label' => 'Contraseña', 'type' => 'password', 'secret' => true, 'help' => 'Mejor una contraseña de aplicación del servicio que la de la cuenta.' ),
			'webdav_path' => array( 'label' => 'Carpeta remota', 'type' => 'path', 'default' => '/digitalisimo-backups' ),
			'webdav_ca' => array( 'label' => 'CA propia (PEM)', 'type' => 'textarea', 'help' => 'Sólo si el servidor usa un certificado de una autoridad propia. Vacío: las autoridades públicas de WordPress.' ),
			'webdav_insecure' => array( 'label' => 'Verificación TLS', 'type' => 'checkbox', 'text' => 'No verificar el certificado del servidor (inseguro)', 'warning' => 'Sin verificar el certificado, cualquiera en la red puede hacerse pasar por el servidor y leer la contraseña y los respaldos. Usa mejor la CA propia.' ),
			'webdav_allow_http' => array( 'label' => 'HTTP sin cifrar', 'type' => 'checkbox', 'text' => 'Permitir una URL http:// (inseguro)', 'warning' => 'Por http la contraseña y los respaldos viajan sin cifrar.' ),
		);
	}
	public static function help() { return array( 'Usa la URL WebDAV que indica tu servicio (en Nextcloud: Archivos → Ajustes de archivos → WebDAV).', 'La carpeta remota se crea si no existe.' ); }
	public static function hosts( array $s ) { return array( (string) parse_url( (string) ( $s['webdav_url'] ?? '' ), PHP_URL_HOST ) ); }
	public static function ready( array $s ) { return ! empty( $s['webdav_url'] ) && ! empty( $s['webdav_user'] ) && ! empty( $s['webdav_password'] ); }
	public static function sanitize( array $input, array $old ) {
		$out = parent::sanitize( $input, $old );
		$url = trim( (string) $out['webdav_url'] );
		if ( '' !== $url ) {
			$parts = parse_url( $url );
			$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
			if ( ! in_array( $scheme, array( 'https', 'http' ), true ) || empty( $parts['host'] ) || ! self::valid_host( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) throw new Exception( 'La URL WebDAV debe ser como https://servidor/ruta/, sin usuario ni consulta.' );
			if ( 'http' === $scheme && empty( $out['webdav_allow_http'] ) ) throw new Exception( 'La URL WebDAV usa http:// sin cifrar: marca «Permitir una URL http://» si de verdad lo quieres, o usa https://.' );
			$url = $scheme . '://' . strtolower( $parts['host'] ) . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) . rtrim( (string) ( $parts['path'] ?? '' ), '/' ) . '/';
		}
		$out['webdav_url'] = $url;
		$out['webdav_path'] = self::clean_path( $out['webdav_path'], '/' );
		if ( ! self::valid_pem( $out['webdav_ca'] ) ) throw new Exception( 'La CA propia debe ser uno o más certificados PEM completos (-----BEGIN CERTIFICATE----- … -----END CERTIFICATE-----).' );
		return $out;
	}

	/* ------------------------------------------------------------------ HTTP */

	/** URL de una ruta relativa a la base (cada segmento codificado). */
	private function url( $relative ) {
		$base = (string) $this->s['webdav_url'];
		$segments = array_filter( explode( '/', (string) $relative ), 'strlen' );
		// Una colección conserva su barra final (Apache responde 301 a PROPFIND sin ella).
		return $base . implode( '/', array_map( 'rawurlencode', $segments ) ) . ( $segments && '/' === substr( (string) $relative, -1 ) ? '/' : '' );
	}
	private function folder() { return trim( self::clean_path( (string) ( $this->s['webdav_path'] ?? '/' ), '/' ), '/' ); }
	private function relative( $name ) { return ( '' !== $this->folder() ? $this->folder() . '/' : '' ) . $name; }
	private function call( $method, $relative, array $headers = array(), array $args = array() ) {
		if ( empty( $this->s['webdav_url'] ) || empty( $this->s['webdav_user'] ) || empty( $this->s['webdav_password'] ) ) {
			$problem = class_exists( 'Digitalisimo_Backups' ) ? Digitalisimo_Backups::destination_problem( (string) $this->context['connection'] ) : '';
			throw new Digitalisimo_Backups_Permanent_Error( '' !== $problem ? $problem : 'Completa la URL, el usuario y la contraseña de WebDAV.' );
		}
		if ( 0 === strpos( (string) $this->s['webdav_url'], 'http://' ) && empty( $this->s['webdav_allow_http'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La URL WebDAV usa http:// sin permitirlo de forma explícita.' );
		$headers['Authorization'] = 'Basic ' . base64_encode( $this->s['webdav_user'] . ':' . $this->s['webdav_password'] );
		$response = Digitalisimo_Backups_Http::request( $method, $this->url( $relative ), array_merge( array( 'headers' => $headers, 'verify' => empty( $this->s['webdav_insecure'] ), 'ca' => $this->ca_file( (string) ( $this->s['webdav_ca'] ?? '' ) ), 'timeout' => 120 ), $args ) );
		if ( 401 === $response['code'] || 403 === $response['code'] ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor WebDAV rechazó el usuario o la contraseña, o no da permiso en la carpeta (HTTP ' . $response['code'] . ').' );
		return $response;
	}
	/** Crea la carpeta remota nivel por nivel. */
	private function ensure_collection() {
		if ( $this->collection_ready ) return;
		$path = '';
		foreach ( array_filter( explode( '/', $this->folder() ), 'strlen' ) as $segment ) {
			$path .= ( '' === $path ? '' : '/' ) . $segment;
			$response = $this->call( 'MKCOL', $path . '/' );
			if ( ! in_array( $response['code'], array( 201, 405, 301 ), true ) ) throw new Exception( 'El servidor WebDAV no creó la carpeta remota «' . $path . '» (HTTP ' . $response['code'] . ').' );
		}
		$this->collection_ready = true;
	}

	/* ------------------------------------------------------------------ Contrato */

	protected function upload( $file, $name, array $source ) {
		$this->ensure_collection();
		$relative = $this->relative( self::temporary_name( $name ) );
		$temp = array( 'provider' => self::TYPE, 'path' => $relative );
		$this->save_state( array( 'connection' => (string) $this->context['connection'], 'size' => $source['size'], 'sha256' => $source['sha256'], 'temp' => $temp ) );
		$response = $this->call( 'PUT', $relative, array( 'Content-Type' => 'application/octet-stream' ), array( 'file' => $file, 'timeout' => 0, 'progress' => function( $done, $total ) use ( $source ) { $this->progress( min( $done, $source['size'] ), $source['size'] ); } ) );
		if ( ! in_array( $response['code'], array( 200, 201, 204 ), true ) ) {
			$this->discard_quietly( $temp );
			if ( 507 === $response['code'] ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor WebDAV no tiene espacio para el respaldo (HTTP 507).' );
			throw new Exception( 'El servidor WebDAV no aceptó el respaldo (HTTP ' . $response['code'] . ').' );
		}
		return $temp;
	}
	protected function publish( array $temp, $name ) {
		$relative = $this->relative( $name );
		$response = $this->call( 'MOVE', (string) $temp['path'], array( 'Destination' => $this->url( $relative ), 'Overwrite' => 'F' ) );
		if ( ! in_array( $response['code'], array( 201, 204 ), true ) ) {
			$this->discard_quietly( $temp );
			throw new Exception( 412 === $response['code'] ? 'Ya existe en WebDAV un archivo con el nombre del respaldo; no se sobrescribe.' : 'El servidor WebDAV no publicó el respaldo verificado (HTTP ' . $response['code'] . ').' );
		}
		return array( 'provider' => self::TYPE, 'path' => $relative );
	}
	private function own( array $remote ) { return self::inside( (string) ( $remote['path'] ?? '' ), $this->folder(), self::label() ); }
	public function get( array $remote, $target ) {
		$response = $this->call( 'GET', $this->own( $remote ), array(), array( 'sink' => $target, 'timeout' => 0, 'progress' => function( $done, $total ) { if ( $total > 0 ) $this->progress( $done, $total, 'Descargados' ); } ) );
		if ( 404 === $response['code'] ) throw new Exception( 'El respaldo ya no está en el servidor WebDAV.' );
		if ( 200 !== $response['code'] ) throw new Exception( 'No se pudo descargar el respaldo de WebDAV (HTTP ' . $response['code'] . ').' );
	}
	/** DAV:getcontentlength de PROPFIND Depth 0. */
	public function size( array $remote ) {
		$response = $this->call( 'PROPFIND', $this->own( $remote ), array( 'Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8' ), array( 'body' => self::propfind_body() ) );
		if ( 404 === $response['code'] ) return false;
		if ( 207 !== $response['code'] ) throw new Exception( 'El servidor WebDAV no informó el tamaño del respaldo (HTTP ' . $response['code'] . ').' );
		$items = self::multistatus( $response['body'] );
		return $items ? (int) $items[0]['size'] : false;
	}
	public function delete( array $remote ) {
		$response = $this->call( 'DELETE', $this->own( $remote ) );
		if ( ! in_array( $response['code'], array( 200, 204, 404 ), true ) ) throw new Exception( 'El servidor WebDAV no borró el archivo (HTTP ' . $response['code'] . ').' );
	}
	/** B1b: GET con `Range` (RFC 7233), un fragmento por llamada. */
	public function ranged() { return true; }
	public function get_range( array $remote, $target, $offset, $length ) {
		$range = self::range_args( $offset, $length );
		return self::range_reply( $this->call( 'GET', $this->own( $remote ), $range['headers'], array( 'sink' => $target, 'timeout' => 120, 'progress' => $range['progress'] ) ), $target, $offset, $length );
	}
	public function list() {
		$folder = $this->folder();
		$response = $this->call( 'PROPFIND', '' !== $folder ? $folder . '/' : '', array( 'Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8' ), array( 'body' => self::propfind_body() ) );
		if ( 404 === $response['code'] ) return array();
		if ( 207 !== $response['code'] ) throw new Exception( 'El servidor WebDAV no listó la carpeta (HTTP ' . $response['code'] . ').' );
		$out = array();
		foreach ( self::multistatus( $response['body'] ) as $item ) {
			if ( $item['collection'] ) continue;
			$name = rawurldecode( basename( rtrim( (string) parse_url( $item['href'], PHP_URL_PATH ), '/' ) ) );
			if ( '' === $name ) continue;
			$out[] = array( 'name' => $name, 'size' => (int) $item['size'], 'remote' => array( 'provider' => self::TYPE, 'connection' => (string) $this->context['connection'], 'path' => $this->relative( $name ) ) );
		}
		return $out;
	}
	private static function propfind_body() { return '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/><d:resourcetype/></d:prop></d:propfind>'; }
	/** Respuestas de un 207 Multi-Status: href, tamaño y si es colección (sin cargar entidades externas). */
	public static function multistatus( $xml ) {
		$out = array();
		if ( ! preg_match_all( '#<(?:[A-Za-z0-9_-]+:)?response(?:\s[^>]*)?>(.*?)</(?:[A-Za-z0-9_-]+:)?response>#s', (string) $xml, $responses ) ) return $out;
		foreach ( $responses[1] as $response ) {
			if ( ! preg_match( '#<(?:[A-Za-z0-9_-]+:)?href(?:\s[^>]*)?>(.*?)</(?:[A-Za-z0-9_-]+:)?href>#s', $response, $href ) ) continue;
			// Sólo el bloque propstat con estado 200 cuenta.
			$size = 0;
			$collection = false;
			if ( preg_match_all( '#<(?:[A-Za-z0-9_-]+:)?propstat(?:\s[^>]*)?>(.*?)</(?:[A-Za-z0-9_-]+:)?propstat>#s', $response, $stats ) ) {
				foreach ( $stats[1] as $stat ) {
					if ( ! preg_match( '#<(?:[A-Za-z0-9_-]+:)?status(?:\s[^>]*)?>[^<]*\s200\s#', $stat ) ) continue;
					if ( preg_match( '#<(?:[A-Za-z0-9_-]+:)?getcontentlength(?:\s[^>]*)?>\s*(\d+)\s*<#', $stat, $length ) ) $size = (int) $length[1];
					if ( preg_match( '#<(?:[A-Za-z0-9_-]+:)?collection\s*/?>#', $stat ) ) $collection = true;
				}
			}
			$out[] = array( 'href' => htmlspecialchars_decode( trim( $href[1] ), ENT_QUOTES | ENT_XML1 ), 'size' => $size, 'collection' => $collection );
		}
		return $out;
	}
}
