<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dropbox (API HTTP v2). OAuth con PKCE y refresh token, sin App secret (una app de Dropbox con «Allow public
 * clients»: el plugin se instala en servidores que no controla quien creó la app). El token de acceso dura poco y se
 * renueva en memoria; nunca se guarda.
 *
 * Subida: upload_session/start, append_v2 por fragmentos de 8 MiB (múltiplos de 4 MiB, como recomienda Dropbox) y
 * finish a `<nombre>.part-<aleatorio>`. La sesión (identificador y desplazamiento confirmado) se guarda en el área del
 * trabajo: un reintento sigue desde ahí; si Dropbox dice `incorrect_offset`, se continúa desde su `correct_offset`, y si
 * la sesión caducó (48 h), se empieza de nuevo.
 *
 * Verificación: el `content_hash` que Dropbox calcula sobre lo guardado (SHA-256 de los SHA-256 de cada bloque de 4 MiB,
 * documentado para comprobar la integridad) se compara con el calculado aquí, y también el tamaño. Publicación con
 * files/move_v2 sin renombrar si ya existe.
 */
final class Digitalisimo_Backups_Destination_Dropbox extends Digitalisimo_Backups_Destination {
	const TYPE = 'dropbox';
	const PREFIX = 'dropbox_';
	const API = 'https://api.dropboxapi.com/2/';
	const CONTENT = 'https://content.dropboxapi.com/2/';
	const TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';
	const BLOCK = 4194304;
	private $access = '';

	public static function label() { return 'Dropbox'; }
	public static function oauth() { return true; }
	public static function fields() {
		return array(
			'dropbox_app_key' => array( 'label' => 'App key', 'type' => 'text', 'help' => 'El App key de tu app de Dropbox (no hace falta el App secret: se conecta con PKCE).' ),
			'dropbox_refresh_token' => array( 'label' => 'Refresh token', 'type' => 'password', 'secret' => true, 'help' => 'Lo guarda «Conectar Dropbox». Sólo pégalo si lo obtuviste con PKCE para este mismo App key.' ),
			'dropbox_folder' => array( 'label' => 'Carpeta', 'type' => 'text', 'default' => '/Digitalisimo Backups', 'help' => 'Dentro de la carpeta de la app (acceso «App folder») o de todo Dropbox.' ),
		);
	}
	public static function ready( array $s ) { return ! empty( $s['dropbox_app_key'] ) && ! empty( $s['dropbox_refresh_token'] ); }
	public static function sanitize( array $input, array $old ) {
		$out = parent::sanitize( $input, $old );
		if ( '' !== $out['dropbox_app_key'] && ! preg_match( '/^[a-z0-9]{8,32}$/i', $out['dropbox_app_key'] ) ) throw new Exception( 'El App key de Dropbox son letras y números (lo muestra la consola de la app).' );
		$out['dropbox_folder'] = self::clean_path( $out['dropbox_folder'], '/Digitalisimo Backups' );
		return $out;
	}

	/* ------------------------------------------------------------------ OAuth con PKCE */

	/** code_verifier de 64 caracteres de [A-Za-z0-9-._~] (entre 43 y 128, como pide Dropbox). */
	public static function code_verifier() { return rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' ); }
	/** code_challenge S256: SHA-256 del verificador en base64url sin relleno. */
	public static function code_challenge( $verifier ) { return rtrim( strtr( base64_encode( hash( 'sha256', (string) $verifier, true ) ), '+/', '-_' ), '=' ); }
	/** Cambia el código por el refresh token (sin App secret: con el code_verifier). '' si Dropbox no lo da. */
	public static function exchange_code( $app_key, $code, $verifier, $redirect ) {
		try {
			$response = Digitalisimo_Backups_Http::request( 'POST', self::TOKEN_URL, array( 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ), 'body' => http_build_query( array( 'code' => $code, 'grant_type' => 'authorization_code', 'client_id' => $app_key, 'code_verifier' => $verifier, 'redirect_uri' => $redirect ), '', '&' ), 'timeout' => 30 ) );
		} catch ( Exception $e ) {
			return '';
		}
		$data = json_decode( $response['body'], true );
		return 200 === $response['code'] && ! empty( $data['refresh_token'] ) ? (string) $data['refresh_token'] : '';
	}
	/** Token de acceso de corta duración a partir del refresh token (en memoria, nunca guardado). */
	private function token() {
		if ( '' !== $this->access ) return $this->access;
		if ( empty( $this->s['dropbox_app_key'] ) || empty( $this->s['dropbox_refresh_token'] ) ) {
			$problem = class_exists( 'Digitalisimo_Backups' ) ? Digitalisimo_Backups::destination_problem( (string) $this->context['connection'] ) : '';
			throw new Digitalisimo_Backups_Permanent_Error( '' !== $problem ? $problem : 'Conecta Dropbox antes de usarlo como destino.' );
		}
		$response = Digitalisimo_Backups_Http::request( 'POST', self::TOKEN_URL, array( 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ), 'body' => http_build_query( array( 'grant_type' => 'refresh_token', 'refresh_token' => $this->s['dropbox_refresh_token'], 'client_id' => $this->s['dropbox_app_key'] ), '', '&' ), 'timeout' => 30 ) );
		$data = json_decode( $response['body'], true );
		if ( 400 === $response['code'] || 401 === $response['code'] ) throw new Digitalisimo_Backups_Permanent_Error( 'Dropbox rechazó el refresh token (' . ( (string) ( $data['error'] ?? 'HTTP ' . $response['code'] ) ) . '): conecta Dropbox de nuevo.' );
		if ( 200 !== $response['code'] || empty( $data['access_token'] ) ) throw new Exception( 'No se pudo renovar el acceso a Dropbox.' );
		return $this->access = (string) $data['access_token'];
	}

	/* ------------------------------------------------------------------ API */

	/** content_hash de Dropbox de un archivo local. */
	public static function content_hash( $file ) {
		$handle = @fopen( $file, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer el respaldo local para calcular su suma.' );
		$blocks = '';
		try {
			while ( ! feof( $handle ) ) {
				$block = '';
				while ( strlen( $block ) < self::BLOCK && ! feof( $handle ) ) { $read = fread( $handle, self::BLOCK - strlen( $block ) ); if ( false === $read || '' === $read ) break; $block .= $read; }
				if ( '' === $block ) break;
				$blocks .= hash( 'sha256', $block, true );
			}
		} finally {
			fclose( $handle );
		}
		return hash( 'sha256', $blocks );
	}
	/** Dropbox-API-Arg: JSON con todo carácter no ASCII escapado como \uXXXX (json_encode lo hace por defecto). */
	private static function arg( array $arg ) { return json_encode( $arg, JSON_UNESCAPED_SLASHES ); }
	private function rpc( $route, array $arg ) {
		$response = Digitalisimo_Backups_Http::request( 'POST', self::API . $route, array( 'headers' => array( 'Authorization' => 'Bearer ' . $this->token(), 'Content-Type' => 'application/json' ), 'body' => json_encode( $arg, JSON_UNESCAPED_SLASHES ), 'timeout' => 60 ) );
		return array( $response['code'], json_decode( $response['body'], true ), $response );
	}
	private function content( $route, array $arg, $body = '', array $args = array() ) {
		$headers = array( 'Authorization' => 'Bearer ' . $this->token(), 'Dropbox-API-Arg' => self::arg( $arg ) );
		if ( empty( $args['sink'] ) ) $headers['Content-Type'] = 'application/octet-stream';
		$response = Digitalisimo_Backups_Http::request( 'POST', self::CONTENT . $route, array_merge( array( 'headers' => $headers, 'body' => (string) $body, 'timeout' => 300 ), $args ) );
		$data = empty( $args['sink'] ) ? json_decode( $response['body'], true ) : null;
		return array( $response['code'], $data, $response );
	}
	/** Error legible; los de autorización y ruta no se arreglan reintentando. */
	private function fail( $code, $data, $what ) {
		$summary = is_array( $data ) ? (string) ( $data['error_summary'] ?? '' ) : '';
		$message = 'Dropbox rechazó ' . $what . ' (' . ( '' !== $summary ? $summary : 'HTTP ' . $code ) . ').';
		if ( 401 === $code || ( 409 === $code && preg_match( '/^(path\/(malformed_path|no_write_permission|insufficient_space|disallowed_name)|insufficient_space)/', $summary ) ) ) throw new Digitalisimo_Backups_Permanent_Error( $message );
		throw new Exception( $message );
	}
	private function folder() { return rtrim( self::clean_path( (string) ( $this->s['dropbox_folder'] ?? '' ), '/Digitalisimo Backups' ), '/' ); }

	/* ------------------------------------------------------------------ Contrato */

	protected function upload( $file, $name, array $source ) {
		$state = $this->resumable_state( $source );
		if ( ! $state || empty( $state['session'] ) ) $state = array( 'connection' => (string) $this->context['connection'], 'size' => $source['size'], 'sha256' => $source['sha256'], 'path' => $this->folder() . '/' . self::temporary_name( $name ), 'session' => '', 'offset' => 0 );
		$temp = array( 'provider' => self::TYPE, 'path' => $state['path'] );
		$state['temp'] = $temp;
		if ( ! empty( $state['finished'] ) ) return $temp + array( 'content_hash' => (string) ( $state['content_hash'] ?? '' ), 'size' => $source['size'] );
		$handle = @fopen( $file, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' );
		try {
			for ( $restarts = 0; ; ) {
				if ( '' === $state['session'] ) {
					list( $code, $data ) = $this->content( 'files/upload_session/start', array( 'close' => false ) );
					if ( 200 !== $code || empty( $data['session_id'] ) ) $this->fail( $code, $data, 'el inicio de la subida' );
					$state['session'] = (string) $data['session_id'];
					$state['offset'] = 0;
					$this->save_state( $state );
				}
				$lost = false;
				while ( $state['offset'] < $source['size'] ) {
					$this->progress( $state['offset'], $source['size'] );
					if ( 0 !== fseek( $handle, (int) $state['offset'] ) ) throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' );
					$length = (int) min( self::CHUNK, $source['size'] - $state['offset'] );
					$chunk = '';
					while ( strlen( $chunk ) < $length && ! feof( $handle ) ) { $read = fread( $handle, $length - strlen( $chunk ) ); if ( false === $read || '' === $read ) break; $chunk .= $read; }
					if ( strlen( $chunk ) !== $length ) throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' );
					list( $code, $data ) = $this->content( 'files/upload_session/append_v2', array( 'cursor' => array( 'session_id' => $state['session'], 'offset' => (int) $state['offset'] ), 'close' => false ), $chunk );
					if ( 200 === $code ) { $state['offset'] += $length; $this->save_state( $state ); continue; }
					$tag = is_array( $data ) ? (string) ( $data['error']['.tag'] ?? '' ) : '';
					if ( 409 === $code && 'incorrect_offset' === $tag && isset( $data['error']['correct_offset'] ) && (int) $data['error']['correct_offset'] <= $source['size'] ) {
						// Dropbox ya recibió hasta otro punto (una respuesta perdida): se continúa desde allí.
						$state['offset'] = (int) $data['error']['correct_offset'];
						$this->save_state( $state );
						continue;
					}
					if ( 409 === $code && in_array( $tag, array( 'not_found', 'closed' ), true ) ) { $lost = true; break; }
					$this->fail( $code, $data, 'un fragmento del respaldo' );
				}
				if ( ! $lost ) {
					list( $code, $data ) = $this->content( 'files/upload_session/finish', array( 'cursor' => array( 'session_id' => $state['session'], 'offset' => (int) $state['offset'] ), 'commit' => array( 'path' => $state['path'], 'mode' => 'add', 'autorename' => false, 'mute' => true, 'strict_conflict' => true ) ) );
					if ( 200 === $code ) {
						$state['finished'] = true;
						$state['content_hash'] = (string) ( $data['content_hash'] ?? '' );
						$state['id'] = (string) ( $data['id'] ?? '' );
						$this->save_state( $state );
						return $temp + array( 'id' => $state['id'], 'content_hash' => $state['content_hash'], 'size' => (int) ( $data['size'] ?? -1 ) );
					}
					$tag = is_array( $data ) ? (string) ( $data['error']['lookup_failed']['.tag'] ?? '' ) : '';
					if ( 409 !== $code || ! in_array( $tag, array( 'not_found', 'closed' ), true ) ) $this->fail( $code, $data, 'el cierre de la subida' );
				}
				// La sesión caducó (48 h) o ya no existe: se empieza de nuevo una sola vez.
				if ( ++$restarts > 1 ) throw new Exception( 'La sesión de subida de Dropbox desapareció dos veces seguidas.' );
				$state['session'] = '';
				$state['offset'] = 0;
			}
		} finally {
			fclose( $handle );
		}
	}
	/** content_hash y tamaño que Dropbox calcula sobre lo que guardó, contra los del respaldo local. */
	public function verify( array $remote, $file, array $source ) {
		list( $code, $data ) = $this->rpc( 'files/get_metadata', array( 'path' => (string) $remote['path'] ) );
		if ( 200 !== $code ) $this->fail( $code, $data, 'la consulta de la copia subida' );
		if ( (int) ( $data['size'] ?? -1 ) !== $source['size'] || ! hash_equals( self::content_hash( $file ), (string) ( $data['content_hash'] ?? '' ) ) ) throw new Exception( 'La copia en Dropbox no coincide con el respaldo local (content_hash o tamaño distintos); no se registró como respaldo válido.' );
	}
	protected function publish( array $temp, $name ) {
		$final = $this->folder() . '/' . $name;
		list( $code, $data ) = $this->rpc( 'files/move_v2', array( 'from_path' => (string) $temp['path'], 'to_path' => $final, 'autorename' => false, 'allow_ownership_transfer' => false ) );
		if ( 200 !== $code ) {
			$this->discard_quietly( $temp );
			$this->fail( $code, $data, 'la publicación del respaldo verificado' );
		}
		return array( 'provider' => self::TYPE, 'path' => (string) ( $data['metadata']['path_display'] ?? $final ), 'id' => (string) ( $data['metadata']['id'] ?? '' ) );
	}
	/**
	 * Un recibo de Dropbox: su ruta, y sólo dentro de la carpeta de esta conexión. Se opera siempre por la ruta (no por
	 * el id del recibo, que podría señalar cualquier archivo de la cuenta).
	 */
	private function own( array $remote ) {
		$path = (string) ( $remote['path'] ?? '' );
		if ( '' === $path ) throw new Digitalisimo_Backups_Permanent_Error( 'La copia en Dropbox no tiene ruta; no se toca.' );
		// Dropbox no distingue mayúsculas (`path_display` lleva las de la carpeta que ya existía).
		$lower = function( $text ) { return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text ); };
		self::inside( $lower( $path ), $lower( $this->folder() ), self::label() );
		return $path;
	}
	public function get( array $remote, $target ) {
		list( $code, , $response ) = $this->content( 'files/download', array( 'path' => $this->own( $remote ) ), '', array( 'sink' => $target, 'timeout' => 0, 'progress' => function( $done, $total ) { if ( $total > 0 ) $this->progress( $done, $total, 'Descargados' ); } ) );
		if ( 200 !== $code ) {
			$data = is_file( $target ) && filesize( $target ) < 65536 ? json_decode( (string) @file_get_contents( $target ), true ) : null;
			if ( 409 === $code && false !== strpos( (string) ( $data['error_summary'] ?? '' ), 'not_found' ) ) throw new Exception( 'El respaldo ya no está en Dropbox.' );
			$this->fail( $code, $data, 'la descarga del respaldo' );
		}
	}
	public function size( array $remote ) {
		list( $code, $data ) = $this->rpc( 'files/get_metadata', array( 'path' => $this->own( $remote ) ) );
		if ( 409 === $code && false !== strpos( (string) ( $data['error_summary'] ?? '' ), 'not_found' ) ) return false;
		if ( 200 !== $code ) $this->fail( $code, $data, 'la consulta del respaldo' );
		return (int) ( $data['size'] ?? 0 );
	}
	public function delete( array $remote ) {
		list( $code, $data ) = $this->rpc( 'files/delete_v2', array( 'path' => $this->own( $remote ) ) );
		if ( 200 === $code || ( 409 === $code && false !== strpos( (string) ( $data['error_summary'] ?? '' ), 'not_found' ) ) ) return;
		$this->fail( $code, $data, 'el borrado del respaldo' );
	}
	public function list() {
		list( $code, $data ) = $this->rpc( 'files/list_folder', array( 'path' => $this->folder(), 'recursive' => false, 'limit' => 2000 ) );
		$out = array();
		while ( true ) {
			if ( 409 === $code && false !== strpos( (string) ( $data['error_summary'] ?? '' ), 'not_found' ) ) return $out;
			if ( 200 !== $code ) $this->fail( $code, $data, 'el listado de la carpeta' );
			foreach ( (array) ( $data['entries'] ?? array() ) as $entry ) if ( 'file' === ( $entry['.tag'] ?? '' ) ) $out[] = array( 'name' => (string) $entry['name'], 'size' => (int) ( $entry['size'] ?? 0 ), 'remote' => array( 'provider' => self::TYPE, 'connection' => (string) $this->context['connection'], 'path' => (string) ( $entry['path_display'] ?? '' ), 'id' => (string) ( $entry['id'] ?? '' ) ) );
			if ( empty( $data['has_more'] ) ) return $out;
			list( $code, $data ) = $this->rpc( 'files/list_folder/continue', array( 'cursor' => (string) $data['cursor'] ) );
		}
	}
	public function abandon() {
		// Una sesión sin cerrar caduca sola en 48 horas; un temporal ya cerrado se borra.
		$state = $this->state();
		if ( ! empty( $state['finished'] ) && ! empty( $state['path'] ) ) $this->discard_quietly( array( 'path' => (string) $state['path'] ) );
		$this->discard_stale();
		$this->forget_state();
	}
}
