<?php
defined( 'ABSPATH' ) || exit;

/**
 * Subida de un respaldo desde el navegador o por REST, por fragmentos y con reanudación (B3).
 *
 * Cada subida es una sesión en el almacén privado (`<almacén>/incoming/<uuid>/`, carpetas `0700` y archivos `0600`):
 * nunca pasa por `wp-content/uploads` ni por una ruta servida. Un fragmento sólo se anexa en el desplazamiento que la
 * sesión espera (el servidor dice cuánto recibió y el cliente sigue desde ahí), nunca pasa del tamaño declarado y, si
 * la escritura falla a medias, el archivo vuelve a su tamaño anterior. Al terminar, el archivo pasa toda la validación
 * de archivos no confiables de «Seguridad» dentro de la sesión y sólo entonces entra en el almacén con `rename()`, como
 * «sin registro»: restaurarlo pide RESTAURAR SIN FIRMA salvo que lleve una firma válida de una clave de este sitio.
 *
 * Los fragmentos tienen un tamaño seguro para el hosting: como mucho 8 MiB y por debajo de `upload_max_filesize` y
 * `post_max_size` (wp_max_upload_size()). Una sesión sin actividad durante 24 h se borra.
 */
final class Digitalisimo_Backups_Uploads {
	const DIRECTORY = 'incoming';
	const STATE = 'upload.json';
	const DATA = 'data.bin';
	const LOCK = '.lock';
	const NONCE = 'digitalisimo_backups_upload';
	const CHUNK_MAX = 8388608;
	const CHUNK_MIN = 65536;
	const MAX_SESSIONS = 3;
	const EXPIRES_AFTER = 86400;
	const MAX_SIZE = 68719476736;
	/** Comprueba que un archivo de $_FILES lo subió PHP en esta petición (sustituible en las pruebas). */
	public static $is_uploaded = 'is_uploaded_file';

	public static function init() {
		foreach ( array( 'start', 'chunk', 'status', 'finish', 'cancel' ) as $step ) add_action( 'wp_ajax_digitalisimo_backups_upload_' . $step, array( __CLASS__, 'ajax_' . $step ) );
	}
	public static function valid_id( $id ) { return Digitalisimo_Backups_Job_Store::valid_id( $id ); }

	/** Tamaño de fragmento: hasta 8 MiB, por debajo de lo que el hosting acepta en una petición (con margen). */
	public static function chunk_size() {
		$limit = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : 0;
		$size = $limit > 0 ? min( self::CHUNK_MAX, $limit - 65536 ) : self::CHUNK_MAX;
		$size = (int) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_upload_chunk_size', $size ) : $size );
		return max( self::CHUNK_MIN, min( self::CHUNK_MAX, $size ) );
	}

	/* ------------------------------------------------------------------ Sesiones */

	public static function root() {
		$root = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . self::DIRECTORY;
		if ( is_link( $root ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de subidas de Backups no puede ser un enlace simbólico.' );
		if ( ! is_dir( $root ) && ! @mkdir( $root, 0700 ) && ! is_dir( $root ) ) throw new Exception( 'No se pudo crear la carpeta de subidas en el almacén privado.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $root, 0700 );
		return $root;
	}
	public static function directory( $id ) {
		if ( ! self::valid_id( $id ) ) throw new InvalidArgumentException( 'Identificador de subida no válido.' );
		$dir = self::root() . '/' . $id;
		if ( is_link( $dir ) ) throw new Exception( 'La carpeta de la subida es un enlace simbólico.' );
		return $dir;
	}
	public static function data_path( $id ) { return self::directory( $id ) . '/' . self::DATA; }
	public static function read( $id ) {
		if ( ! self::valid_id( $id ) ) return null;
		$path = self::directory( $id ) . '/' . self::STATE;
		if ( is_link( $path ) || ! is_file( $path ) ) return null;
		$state = json_decode( (string) @file_get_contents( $path ), true );
		return is_array( $state ) && $id === ( $state['id'] ?? '' ) ? $state : null;
	}
	public static function write( array $state ) {
		$path = self::directory( $state['id'] ) . '/' . self::STATE;
		$temp = $path . '.tmp-' . bin2hex( random_bytes( 4 ) );
		$json = json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || strlen( $json ) !== @file_put_contents( $temp, $json ) || ! @chmod( $temp, 0600 ) || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			throw new Exception( 'No se pudo guardar el estado de la subida en el almacén privado.' );
		}
		return $state;
	}
	/** Crea la sesión con su archivo vacío; el estado existe antes que cualquier byte. */
	public static function create( array $state ) {
		$dir = self::directory( $state['id'] );
		if ( file_exists( $dir ) || ! @mkdir( $dir, 0700 ) ) throw new Exception( 'No se pudo crear la carpeta de la subida en el almacén privado.' );
		$data = $dir . '/' . self::DATA;
		if ( false === @file_put_contents( $data, '' ) || ! @chmod( $data, 0600 ) ) { self::remove( $state['id'] ); throw new Exception( 'No se pudo preparar el archivo de la subida.' ); }
		return self::write( $state );
	}
	public static function remove( $id ) {
		if ( ! self::valid_id( $id ) ) return;
		Digitalisimo_Backups_Job_Store::remove_tree( self::directory( $id ) );
	}
	/** Bytes recibidos: el tamaño real del archivo, no lo que diga el estado. */
	public static function received( $id ) {
		$path = self::data_path( $id );
		clearstatcache( true, $path );
		return is_file( $path ) && ! is_link( $path ) ? (int) filesize( $path ) : 0;
	}
	/** Cerrojo de una sesión: dos fragmentos (o un fragmento y el cierre) nunca escriben a la vez. Null si está ocupada. */
	public static function lock( $id ) {
		$handle = @fopen( self::directory( $id ) . '/' . self::LOCK, 'c' );
		if ( ! $handle ) return null;
		Digitalisimo_Backups::private_file( self::directory( $id ) . '/' . self::LOCK );
		if ( ! @flock( $handle, LOCK_EX | LOCK_NB ) ) { fclose( $handle ); return null; }
		return $handle;
	}
	public static function unlock( $handle ) {
		if ( ! is_resource( $handle ) ) return;
		flock( $handle, LOCK_UN );
		fclose( $handle );
	}
	/** Sesiones vivas; las de más de 24 h sin actividad (o sin estado) se borran. */
	public static function sessions() {
		$root = self::root();
		$alive = array();
		foreach ( (array) @scandir( $root ) as $name ) {
			if ( ! self::valid_id( $name ) ) continue;
			$state = self::read( $name );
			$touched = $state ? (int) ( $state['updated_at'] ?? 0 ) : (int) @filemtime( $root . '/' . $name );
			if ( ! $state || time() - $touched > self::EXPIRES_AFTER ) {
				// Una sesión que escribe ahora mismo no se borra; el cerrojo se suelta antes de borrarla.
				$lock = $state ? self::lock( $name ) : null;
				$idle = ! $state || $lock;
				self::unlock( $lock );
				if ( $idle ) self::remove( $name );
				continue;
			}
			$alive[] = $state;
		}
		return $alive;
	}
	/** Vista pública de una sesión: sin rutas ni usuario. */
	public static function view( array $state ) {
		return array(
			'id' => (string) $state['id'],
			'name' => (string) $state['name'],
			'size' => (int) $state['size'],
			'received' => self::received( $state['id'] ),
			'chunk_size' => (int) $state['chunk_size'],
			'expires_at' => gmdate( 'c', (int) $state['updated_at'] + self::EXPIRES_AFTER ),
		);
	}

	/* ------------------------------------------------------------------ Pantalla (AJAX) */

	/** Capability, nonce propio, contexto (sitio o red) y sitio principal, como la tarjeta «Trabajos». */
	private static function guard() {
		if ( ! Digitalisimo_Backups::can_manage_backups() ) wp_send_json_error( array( 'message' => 'No autorizado para gestionar respaldos.' ), 403 );
		check_ajax_referer( self::NONCE, 'nonce' );
		$context = sanitize_key( wp_unslash( $_POST['context'] ?? 'site' ) );
		if ( ! in_array( $context, array( 'site', 'network' ), true ) || ( 'network' === $context && ! is_multisite() ) ) wp_send_json_error( array( 'message' => 'Contexto de administración inválido.' ), 400 );
	}
	private static function reply( $result ) {
		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code(), 'received' => $data['received'] ?? null ), (int) ( $data['status'] ?? 400 ) );
		}
		wp_send_json_success( $result );
	}
	public static function ajax_start() {
		self::guard();
		self::reply( Digitalisimo_Backups::service_upload_start( array( 'name' => wp_unslash( $_POST['name'] ?? '' ), 'size' => $_POST['size'] ?? 0 ) ) );
	}
	public static function ajax_status() {
		self::guard();
		self::reply( Digitalisimo_Backups::service_upload_status( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) ) );
	}
	/** El fragmento llega como archivo de un formulario multipart: PHP lo deja en su temporal y aquí se anexa sin cargarlo en memoria. */
	public static function ajax_chunk() {
		self::guard();
		$file = isset( $_FILES['chunk'] ) && is_array( $_FILES['chunk'] ) ? $_FILES['chunk'] : array();
		$tmp = is_string( $file['tmp_name'] ?? null ) ? $file['tmp_name'] : '';
		if ( '' === $tmp || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! call_user_func( self::$is_uploaded, $tmp ) ) {
			$too_big = in_array( (int) ( $file['error'] ?? 0 ), array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true );
			wp_send_json_error( array( 'message' => $too_big ? 'El hosting rechazó el fragmento por su tamaño: recarga la página para usar fragmentos más pequeños.' : 'No llegó el fragmento del respaldo.' ), 400 );
		}
		self::reply( Digitalisimo_Backups::service_upload_chunk( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ), $_POST['offset'] ?? -1, array( 'file' => $tmp ) ) );
	}
	public static function ajax_finish() {
		self::guard();
		// La frase se usa tal cual (sanitize_text_field() la alteraría) y nunca se guarda.
		$secret = isset( $_POST['secret'] ) && is_string( $_POST['secret'] ) ? (string) wp_unslash( $_POST['secret'] ) : '';
		self::reply( Digitalisimo_Backups::service_upload_finish( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ), array( 'secret' => $secret ) ) );
	}
	public static function ajax_cancel() {
		self::guard();
		self::reply( Digitalisimo_Backups::service_upload_cancel( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) ) );
	}
	/** Tarjeta «Subir un respaldo» de la pestaña Backups (el script es assets/upload.js). */
	public static function panel( $network ) {
		$max = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : 0;
		echo '<section class="digitalisimo-backups-card digitalisimo-backups-upload" id="digitalisimo-backups-upload" data-chunk="' . esc_attr( (string) self::chunk_size() ) . '"><h2>Subir un respaldo</h2>';
		echo '<p>Sube un respaldo de DIGITALÍSIMO (<code>.zip</code> o <code>.dgbk</code>) desde este equipo, por ejemplo uno de otro servidor. Se envía por fragmentos de ' . esc_html( Digitalisimo_Backups_Archive_Inspector::megabytes( self::chunk_size() ) ) . ( $max ? ' (el hosting acepta ' . esc_html( Digitalisimo_Backups_Archive_Inspector::megabytes( $max ) ) . ' por petición)' : '' ) . ' directamente al almacén privado y, si la conexión se corta, vuelve a elegir el mismo archivo para continuar donde se quedó. Al terminar se comprueba como un archivo no confiable y aparece en «Restaurar» como «sin registro».</p>';
		echo '<noscript><p class="description">Subir un respaldo necesita JavaScript. Sin él, cópialo por SSH o SFTP a la carpeta privada de respaldos.</p></noscript>';
		echo '<form class="digitalisimo-backups-upload-form" hidden><p><input type="file" name="digitalisimo_backups_upload_file" accept=".zip,.dgbk,application/zip,application/octet-stream"></p><p><label>Frase o clave (sólo si está cifrado con una clave que este sitio no tiene) <input type="password" name="digitalisimo_backups_upload_secret" value="" autocomplete="off"></label></p><p><button class="button button-primary" type="submit">Subir respaldo</button> <button class="button" type="button" data-upload-cancel hidden>Cancelar la subida</button></p>';
		if ( $network ) echo '<input type="hidden" name="digitalisimo_backups_network" value="1">';
		echo '<progress max="100" value="0" hidden></progress><p class="digitalisimo-backups-upload-status" role="status" aria-live="polite"></p></form></section>';
	}
}
