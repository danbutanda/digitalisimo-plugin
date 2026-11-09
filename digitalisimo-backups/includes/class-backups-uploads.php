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
 *
 * Estados (`phase`): `uploading` (llegan fragmentos; también completa y a la espera de cerrarse), `validating` (un
 * proceso la está comprobando: lleva el cerrojo), `done` (entró en el almacén; se guarda el resultado 24 h para que
 * repetir el cierre tras un corte devuelva lo mismo y nunca obligue a subirla otra vez) y `rejected` (no era un
 * respaldo válido: sus bytes se borran y queda el motivo). Una subida que no se pudo comprobar por espacio, por la
 * frase o por un error del servidor vuelve a `uploading` con el motivo y se conserva para reintentar o descartar.
 */
final class Digitalisimo_Backups_Uploads {
	const DIRECTORY = 'incoming';
	const STATE = 'upload.json';
	const DATA = 'data.bin';
	const LOCK = '.lock';
	const NONCE = 'digitalisimo_backups_upload';
	const CHUNK_MAX = 8388608;
	const CHUNK_MIN = 65536;
	/** Subidas sin terminar por cuenta, y en total (todas reservan espacio en el almacén). */
	const MAX_SESSIONS = 3;
	const MAX_TOTAL = 10;
	/** Comprobaciones cortadas (el proceso murió) antes de pedir WP-CLI, que no tiene límite de tiempo. */
	const MAX_ATTEMPTS = 3;
	const ACTION_DISCARD = 'digitalisimo_backups_upload_discard';
	const EXPIRES_AFTER = 86400;
	const MAX_SIZE = 68719476736;
	/** Comprueba que un archivo de $_FILES lo subió PHP en esta petición (sustituible en las pruebas). */
	public static $is_uploaded = 'is_uploaded_file';

	public static function init() {
		foreach ( array( 'start', 'chunk', 'status', 'finish', 'cancel' ) as $step ) add_action( 'wp_ajax_digitalisimo_backups_upload_' . $step, array( __CLASS__, 'ajax_' . $step ) );
		add_action( 'admin_post_' . self::ACTION_DISCARD, array( __CLASS__, 'discard' ) );
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
	/**
	 * Todas las sesiones (también las cerradas de las últimas 24 h). Las de más de 24 h sin actividad se borran, salvo
	 * la que alguien está escribiendo; una carpeta sin estado sólo si tiene más de una hora (puede ser un create() en curso).
	 */
	public static function sessions() {
		$root = self::root();
		$all = array();
		foreach ( (array) @scandir( $root ) as $name ) {
			if ( ! self::valid_id( $name ) ) continue;
			$state = self::read( $name );
			if ( ! $state ) {
				if ( time() - (int) @filemtime( $root . '/' . $name ) > 3600 ) self::remove( $name );
				continue;
			}
			if ( time() - (int) ( $state['updated_at'] ?? 0 ) > self::EXPIRES_AFTER ) {
				// El cerrojo se suelta antes de borrar (php-wasm y Windows no sueltan el de un archivo borrado).
				$lock = self::lock( $name );
				$idle = (bool) $lock;
				self::unlock( $lock );
				if ( $idle ) self::remove( $name );
				continue;
			}
			$all[] = $state;
		}
		return $all;
	}
	public static function phase( array $state ) { return (string) ( $state['phase'] ?? 'uploading' ); }
	/** Sin cerrar: reservan espacio y cuentan para el límite. */
	public static function open( array $state ) { return in_array( self::phase( $state ), array( 'uploading', 'validating' ), true ); }
	/** Espacio que una sesión abierta todavía necesita: lo que falta por llegar y, si es un .dgbk, descifrarlo. */
	public static function reserved( array $state ) {
		if ( ! self::open( $state ) ) return 0;
		$size = (int) $state['size'];
		return max( 0, $size - self::received( $state['id'] ) ) + ( self::encrypted_name( (string) $state['name'] ) ? (int) ceil( $size * 1.05 ) : 0 );
	}
	public static function encrypted_name( $name ) { return (bool) preg_match( '/\.dgbk$/i', (string) $name ); }
	/** Si un proceso está comprobando la sesión ahora mismo (tiene su cerrojo). */
	public static function busy( $id ) {
		$lock = self::lock( $id );
		if ( ! $lock ) return true;
		self::unlock( $lock );
		return false;
	}
	/** Vista pública de una sesión: sin rutas ni usuario (sólo si es de la cuenta en curso). */
	public static function view( array $state ) {
		$phase = self::phase( $state );
		$view = array(
			'id' => (string) $state['id'],
			'name' => (string) $state['name'],
			'size' => (int) $state['size'],
			'received' => 'done' === $phase ? (int) $state['size'] : self::received( $state['id'] ),
			'chunk_size' => (int) $state['chunk_size'],
			'phase' => $phase,
			'validating' => 'validating' === $phase && self::busy( $state['id'] ),
			'attempts' => (int) ( $state['attempts'] ?? 0 ),
			'mine' => (int) ( $state['user_id'] ?? 0 ) === (int) get_current_user_id(),
			'updated_at' => gmdate( 'c', (int) $state['updated_at'] ),
			'expires_at' => gmdate( 'c', (int) $state['updated_at'] + self::EXPIRES_AFTER ),
		);
		if ( ! empty( $state['error'] ) && is_array( $state['error'] ) ) $view['error'] = array( 'code' => (string) ( $state['error']['code'] ?? '' ), 'message' => (string) ( $state['error']['message'] ?? '' ) );
		if ( 'done' === $phase && is_array( $state['result'] ?? null ) ) $view['result'] = $state['result'];
		return $view;
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
		echo '<progress max="100" value="0" hidden></progress><p class="digitalisimo-backups-upload-status" role="status" aria-live="polite"></p></form>';
		// Subidas sin cerrar de cualquier cuenta: se pueden descartar (no hace falta el navegador que la empezó).
		$open = array();
		try { $open = array_values( array_filter( self::sessions(), array( __CLASS__, 'open' ) ) ); } catch ( Exception $e ) { $open = array(); }
		if ( $open ) {
			echo '<h3>Subidas sin terminar</h3><p class="description">Cada una reserva espacio en el almacén hasta que termina, se descarta o pasan 24 h sin actividad. Para continuar la tuya, vuelve a elegir el mismo archivo.</p><table class="widefat striped"><thead><tr><th>Archivo</th><th>Recibido</th><th>Cuenta</th><th>Estado</th><th></th></tr></thead><tbody>';
			foreach ( $open as $state ) {
				$view = self::view( $state );
				$status = $view['validating'] ? 'comprobándose' : ( isset( $view['error'] ) ? $view['error']['message'] : ( $view['received'] >= $view['size'] ? 'completa, sin cerrar' : 'subiendo' ) );
				echo '<tr><td>' . esc_html( $view['name'] ) . '</td><td>' . esc_html( Digitalisimo_Backups_Archive_Inspector::megabytes( $view['received'] ) . ' de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $view['size'] ) ) . '</td><td>' . ( $view['mine'] ? 'la tuya' : 'otra cuenta' ) . '</td><td>' . esc_html( $status ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Descartar esta subida y borrar lo recibido?\')">';
				wp_nonce_field( self::ACTION_DISCARD );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DISCARD ) . '"><input type="hidden" name="id" value="' . esc_attr( $view['id'] ) . '">' . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-small">Descartar</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
	}
	/** «Descartar» de la tabla de subidas sin terminar (admin-post: capability y nonce propio). */
	public static function discard() {
		if ( ! Digitalisimo_Backups::can_manage_backups() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_DISCARD );
		$result = Digitalisimo_Backups::service_upload_cancel( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) );
		Digitalisimo_Backups::redirect_result( ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : 'Subida descartada; se borró lo recibido.' );
	}
}
