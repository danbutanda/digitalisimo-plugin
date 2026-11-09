<?php
/**
 * Lote corto · B3: subir un respaldo desde el navegador o por REST, por fragmentos y con reanudación.
 *
 * Con el motor real: la subida vive en el almacén privado (nunca en wp-content/uploads ni en una ruta servida), cada
 * fragmento se anexa sólo en el byte que la sesión espera (un fragmento repetido o fuera de orden responde 409 con lo
 * recibido y el cliente sigue desde ahí), nunca pasa del tamaño declarado ni del tamaño de fragmento, y al terminar el
 * archivo pasa la validación de archivos no confiables antes de entrar en el almacén como «sin registro»: un archivo
 * hostil, alterado o con otro SHA-256 se descarta sin llegar nunca al almacén. Pide capability y nonce (pantalla) o la
 * autenticación de REST, es de la cuenta que la empezó y, en una red, del sitio principal.
 */
$root = sys_get_temp_dir() . '/digitalisimo-upload-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
// Un archivo que no se comprime, para que el respaldo necesite varios fragmentos.
file_put_contents( $root . '/public/wp-content/uploads-foto.bin', random_bytes( 300000 ) );
// Lo que la pantalla necesita y el entorno de cobertura no trae.
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
/** El hosting acepta 200 KB por petición: fragmentos de 136 KB. */
function wp_max_upload_size() { return 204800; }
function check_ajax_referer( $action, $field ) { if ( ( $_REQUEST[ $field ] ?? '' ) !== 'nonce-' . $action ) throw new Digi_Die( 'Nonce inválido para ' . $action ); return 1; }
class Digi_Json extends Exception { public $data; public $status; public $ok; public function __construct( $ok, $data, $status ) { parent::__construct( 'json' ); $this->ok = $ok; $this->data = $data; $this->status = $status; } }
function wp_send_json_success( $data = null ) { throw new Digi_Json( true, $data, 200 ); }
function wp_send_json_error( $data = null, $status = 400 ) { throw new Digi_Json( false, $data, $status ); }
// En la prueba, los «archivos subidos» son temporales propios: así se comprueba que PHP los subió de verdad.
if ( class_exists( 'Digitalisimo_Backups_Uploads' ) ) Digitalisimo_Backups_Uploads::$is_uploaded = function( $path ) { return 0 === strpos( (string) $path, $GLOBALS['tmp_uploads'] ); };

$B = 'Digitalisimo_Backups';
$U = 'Digitalisimo_Backups_Uploads';
$ns = 'digitalisimo-backups/v1';
$store = $root . '/private';
$GLOBALS['tmp_uploads'] = $root . '/php-tmp';
mkdir( $GLOBALS['tmp_uploads'], 0700 );
/** Una petición AJAX de la pantalla: nonce de la subida, contexto y, para un fragmento, el archivo de $_FILES. */
$ajax = function( $step, array $post, $chunk = null, $nonce = 'nonce-digitalisimo_backups_upload' ) use ( $U ) {
	$_POST = $post + array( 'nonce' => $nonce, 'context' => 'site' );
	$_REQUEST = $_POST;
	$_FILES = array();
	if ( null !== $chunk ) {
		$tmp = tempnam( $GLOBALS['tmp_uploads'], 'php' );
		file_put_contents( $tmp, $chunk );
		$_FILES['chunk'] = array( 'name' => 'blob', 'type' => 'application/octet-stream', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen( $chunk ) );
	}
	try {
		call_user_func( array( $U, 'ajax_' . $step ) );
	} catch ( Digi_Json $reply ) {
		return array( $reply->ok, $reply->data, $reply->status );
	}
	throw new RuntimeException( 'FALLA: ajax_' . $step . ' no respondió JSON.' );
};
/** Un fragmento por REST con el cuerpo en bruto (como `curl --data-binary`). */
class Digi_Raw_Request extends WP_REST_Request {
	private $raw;
	public function __construct( array $params, $raw ) { parent::__construct( $params ); $this->raw = $raw; }
	public function get_body() { return $this->raw; }
	public function get_file_params() { return array(); }
}
$rest_chunk = function( $id, $offset, $bytes ) {
	$result = Digitalisimo_Backups_Rest::upload_chunk( new Digi_Raw_Request( array( 'id' => $id, 'offset' => $offset ), $bytes ) );
	return is_wp_error( $result ) ? array( (int) $result->get_error_data()['status'], array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data() ) ) : array( $result->get_status(), $result->get_data() );
};
/** Lista de «Restaurar»: los archivos del almacén sin registro. */
$loose = function() use ( $B ) {
	$panel = new ReflectionMethod( $B, 'restore_panel' );
	if ( PHP_VERSION_ID < 80100 ) $panel->setAccessible( true );
	ob_start();
	$panel->invoke( null, (array) get_option( $B::HISTORY, array() ) );
	$html = (string) ob_get_clean();
	if ( ! preg_match( '#<optgroup label="Archivos del almacén sin registro">(.*?)</optgroup>#s', $html, $m ) ) return array();
	preg_match_all( '#<option value="([^"]+)"#', $m[1], $o );
	return $o[1];
};
$stored = function() use ( $store ) { return array_values( array_filter( (array) scandir( $store ), function( $name ) use ( $store ) { return is_file( $store . '/' . $name ) && preg_match( '/\.(zip|dgbk)$/', $name ); } ) ); };
/** Sube unos bytes entera por REST (fragmentos del tamaño de la sesión) y devuelve la respuesta de finish. */
$upload = function( $name, $bytes, array $extra = array() ) use ( $ns, $rest_chunk ) {
	list( $code, $session ) = digi_rest( 'POST', $ns . '/uploads', array( 'name' => $name, 'size' => strlen( $bytes ) ) + $extra );
	digi_assert( 201 === $code, 'POST /uploads no empezó la subida: ' . json_encode( $session ) );
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += $session['chunk_size'] ) {
		list( $code, $state ) = $rest_chunk( $session['id'], $offset, substr( $bytes, $offset, $session['chunk_size'] ) );
		digi_assert( 200 === $code, 'Un fragmento por REST falló: ' . json_encode( $state ) );
	}
	return array_merge( digi_rest( 'POST', $ns . '/uploads/' . $session['id'] . '/finish', $extra['finish'] ?? array() ), array( $session['id'] ) );
};

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	update_option( $B::OPTION, array( 'retention' => '100' ) );
	// El respaldo «de otro servidor»: se crea aquí y después se borran su archivo y su fila.
	$created = digi_backup();
	digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo de partida.' );
	$name = $created['file'];
	$bytes = file_get_contents( $store . '/' . $name );
	unlink( $store . '/' . $name );
	update_option( $B::HISTORY, array() );
	$size = strlen( $bytes );
	digi_assert( $size > 2 * 139264, 'El respaldo de partida es demasiado pequeño para varios fragmentos.' );

	// 1. Empezar (REST): sesión en el almacén privado, fragmentos por debajo de lo que acepta el hosting.
	list( $code, $session ) = digi_rest( 'POST', $ns . '/uploads', array( 'name' => $name, 'size' => $size ) );
	digi_assert( 201 === $code && $U::valid_id( $session['id'] ) && 0 === $session['received'] && $size === $session['size'] && 204800 - 65536 === $session['chunk_size'], 'POST /uploads no empezó la subida: ' . json_encode( $session ) );
	$id = $session['id'];
	$chunk = $session['chunk_size'];
	$dir = $store . '/incoming/' . $id;
	digi_assert( is_dir( $dir ) && 0 !== strpos( $dir, ABSPATH ) && 0700 === ( fileperms( $dir ) & 0777 ) && 0600 === ( fileperms( $dir . '/data.bin' ) & 0777 ), 'La subida no vive en una carpeta privada del almacén.' );
	digi_assert( ! is_dir( WP_CONTENT_DIR . '/uploads' ) || array() === glob( WP_CONTENT_DIR . '/uploads/*' ), 'La subida pasó por wp-content/uploads.' );
	digi_assert( false === strpos( json_encode( $session ), $root ), 'La sesión expone una ruta del servidor.' );

	// 2. Primer fragmento por la pantalla (multipart); repetirlo (respuesta perdida) o saltarse bytes responde 409 con lo recibido.
	list( $ok, $data, $status ) = $ajax( 'chunk', array( 'id' => $id, 'offset' => 0 ), substr( $bytes, 0, $chunk ) );
	digi_assert( $ok && $chunk === $data['received'], 'El primer fragmento por la pantalla no se anexó: ' . json_encode( $data ) );
	list( $ok, $data, $status ) = $ajax( 'chunk', array( 'id' => $id, 'offset' => 0 ), substr( $bytes, 0, $chunk ) );
	digi_assert( ! $ok && 409 === $status && $chunk === $data['received'] && 'digitalisimo_backups_upload_offset' === $data['code'], 'Repetir un fragmento no respondió 409 con lo recibido: ' . json_encode( $data ) );
	list( $code, $error ) = $rest_chunk( $id, 2 * $chunk, substr( $bytes, 2 * $chunk, $chunk ) );
	digi_assert( 409 === $code && $chunk === $error['data']['received'], 'Un fragmento fuera de orden se aceptó.' );
	digi_assert( $chunk === filesize( $dir . '/data.bin' ), 'Un fragmento rechazado escribió bytes.' );
	// Un fragmento mayor que el de la sesión o que pasa del tamaño declarado se rechaza sin escribir nada.
	list( $code, $error ) = $rest_chunk( $id, $chunk, str_repeat( 'x', $chunk + 1 ) );
	digi_assert( 413 === $code && $chunk === filesize( $dir . '/data.bin' ), 'Se aceptó un fragmento mayor que el de la sesión.' );
	// Sin nonce de la subida, sin capability o sin el fragmento, la pantalla no escribe nada.
	try { $ajax( 'chunk', array( 'id' => $id, 'offset' => $chunk ), 'x', 'nonce-otro' ); throw new RuntimeException( 'FALLA: se aceptó un fragmento con un nonce ajeno.' ); } catch ( Digi_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['digi']['caps'] = array( 'edit_posts' => true );
	list( $ok, $data, $status ) = $ajax( 'chunk', array( 'id' => $id, 'offset' => $chunk ), 'x' );
	digi_assert( ! $ok && 403 === $status, 'Se aceptó un fragmento sin capability.' );
	list( $code ) = digi_rest( 'GET', $ns . '/uploads/' . $id );
	digi_assert( 403 === $code, 'GET /uploads/<id> respondió sin capability.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	$_FILES = array();
	$_POST = $_REQUEST = array( 'nonce' => 'nonce-digitalisimo_backups_upload', 'context' => 'site', 'id' => $id, 'offset' => $chunk );
	try { $U::ajax_chunk(); } catch ( Digi_Json $reply ) { digi_assert( ! $reply->ok && 400 === $reply->status, 'Un fragmento sin archivo no se rechazó.' ); }
	// Un «archivo subido» que PHP no subió (una ruta cualquiera del servidor) nunca se anexa.
	$_FILES = array( 'chunk' => array( 'name' => 'x', 'tmp_name' => ABSPATH . 'wp-config.php', 'error' => UPLOAD_ERR_OK, 'size' => 10 ) );
	try { $U::ajax_chunk(); } catch ( Digi_Json $reply ) { digi_assert( ! $reply->ok && 400 === $reply->status, 'Se anexó un archivo del servidor como fragmento.' ); }
	digi_assert( $chunk === filesize( $dir . '/data.bin' ), 'Una petición rechazada escribió bytes.' );

	// 3. Reanudar tras recargar: el estado dice cuánto llegó y el resto va por REST con el cuerpo en bruto.
	list( $ok, $data ) = $ajax( 'status', array( 'id' => $id ) );
	digi_assert( $ok && $chunk === $data['received'] && $name === $data['name'], 'El estado no permite reanudar: ' . json_encode( $data ) );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/uploads/' . $id . '/finish' );
	digi_assert( 409 === $code && 'digitalisimo_backups_upload_incomplete' === $error['code'], 'Se terminó una subida incompleta.' );
	for ( $offset = $chunk; $offset < $size; $offset += $chunk ) {
		list( $code, $state ) = $rest_chunk( $id, $offset, substr( $bytes, $offset, $chunk ) );
		digi_assert( 200 === $code && min( $size, $offset + $chunk ) === $state['received'], 'Un fragmento por REST no se anexó: ' . json_encode( $state ) );
	}
	list( $code, $error ) = $rest_chunk( $id, $size, 'x' );
	digi_assert( 400 === $code && 'digitalisimo_backups_upload_overflow' === $error['code'], 'Se aceptó un byte más allá del tamaño declarado.' );
	digi_assert( array() === $stored(), 'El respaldo entró en el almacén antes de terminar y validarse.' );

	// 4. Terminar: validación de archivo no confiable y entrada en el almacén como «sin registro».
	list( $code, $result ) = digi_rest( 'POST', $ns . '/uploads/' . $id . '/finish' );
	digi_assert( 200 === $code && $name === $result['file'] && true === $result['verified'] && false === $result['registered'] && 'untrusted' === $result['trust'] && 'RESTAURAR SIN FIRMA' === $result['confirmation'] && true === $result['compatible'], 'La subida terminada no es la esperada: ' . json_encode( $result ) );
	digi_assert( hash( 'sha256', $bytes ) === hash_file( 'sha256', $store . '/' . $name ) && ! is_dir( $dir ) && array() === (array) get_option( $B::HISTORY, array() ), 'El archivo del almacén no es el subido, quedó la sesión o se creó una fila.' );
	digi_assert( in_array( $name, $loose(), true ), 'El respaldo subido no aparece en «Restaurar» como «sin registro».' );
	$error = $B::service_restore( $name, array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( is_wp_error( $error ) && false !== strpos( $error->get_error_message(), 'RESTAURAR SIN FIRMA' ), 'Un respaldo subido se restauró como si fuera de este sitio.' );
	$GLOBALS['wpdb']->set( 'blogname', 'Cambiado' );
	$restored = $B::service_restore( $name, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	digi_assert( ! is_wp_error( $restored ) && 'untrusted' === $restored['trust'] && 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ), 'El respaldo subido no se restauró con RESTAURAR SIN FIRMA.' );

	// 5. Nombres: uno ocupado o de una fila del historial nunca se reutiliza (no hereda la confianza de la fila).
	list( $code, $again ) = $upload( $name, $bytes );
	digi_assert( 200 === $code && $name !== $again['file'] && preg_match( '/^digitalisimo-upload-\d{8}-\d{6}-[0-9a-f]{8}\.zip$/', $again['file'] ), 'Una subida con el nombre de otro archivo lo pisó: ' . json_encode( $again ) );
	$registered = digi_backup();
	unlink( $store . '/' . $registered['file'] );
	list( $code, $other ) = $upload( $registered['file'], $bytes );
	digi_assert( 200 === $code && $registered['file'] !== $other['file'] && 'untrusted' === $other['trust'], 'Una subida tomó el nombre de una fila del historial: ' . json_encode( $other ) );
	list( $code, $renamed ) = $upload( 'mi copia (1).zip', $bytes );
	digi_assert( 200 === $code && 0 === strpos( $renamed['file'], 'digitalisimo-upload-' ) && in_array( $renamed['file'], $loose(), true ), 'Un nombre libre no quedó visible en «Restaurar»: ' . json_encode( $renamed ) );

	// 6. Archivos hostiles: se descartan y nunca llegan al almacén.
	$before = $stored();
	$hostile = array( 'no es un ZIP' => str_repeat( 'basura', 5000 ) );
	$zip_path = $root . '/hostil.zip';
	copy( $store . '/' . $name, $zip_path );
	$zip = new ZipArchive();
	$zip->open( $zip_path );
	$zip->addFromString( 'wordpress/../../fuera.php', '<?php echo 1;' );
	$zip->close();
	$hostile['ruta que sale de WordPress'] = file_get_contents( $zip_path );
	copy( $store . '/' . $name, $zip_path );
	$zip = new ZipArchive();
	$zip->open( $zip_path );
	$zip->addFromString( 'wordpress/wp-load.php', 'contenido cambiado' );
	$zip->close();
	$hostile['contenido distinto del manifiesto'] = file_get_contents( $zip_path );
	foreach ( $hostile as $label => $content ) {
		list( $code, $error, $upload_id ) = $upload( 'digitalisimo-hostil.zip', $content );
		digi_assert( 422 === $code && 'digitalisimo_backups_upload_invalid' === $error['code'] && ! is_dir( $store . '/incoming/' . $upload_id ), 'Un archivo hostil (' . $label . ') no se descartó: ' . json_encode( $error ) );
		digi_assert( $before === $stored(), 'Un archivo hostil (' . $label . ') llegó al almacén.' );
	}
	list( $code, $error, $upload_id ) = $upload( 'digitalisimo-sha.zip', $bytes, array( 'sha256' => str_repeat( 'a', 64 ) ) );
	digi_assert( 422 === $code && 'digitalisimo_backups_upload_checksum' === $error['code'] && $before === $stored() && ! is_dir( $store . '/incoming/' . $upload_id ), 'Un archivo con otro SHA-256 no se descartó.' );
	list( $code, $ok_sha ) = $upload( 'digitalisimo-sha.zip', $bytes, array( 'sha256' => hash( 'sha256', $bytes ) ) );
	digi_assert( 200 === $code && 'digitalisimo-sha.zip' === $ok_sha['file'], 'Un archivo con su SHA-256 correcto no se aceptó.' );
	foreach ( array( array( 'name' => 'respaldo.php', 'size' => 10 ), array( 'name' => 'respaldo.zip', 'size' => 0 ), array( 'name' => 'respaldo.zip', 'size' => 10, 'sha256' => 'xyz' ) ) as $bad ) {
		list( $code ) = digi_rest( 'POST', $ns . '/uploads', $bad );
		digi_assert( 400 === $code, 'Se empezó una subida no válida: ' . json_encode( $bad ) );
	}

	// 7. Límites de las sesiones: tres sin terminar como mucho; cancelar borra lo recibido; caducan a las 24 h.
	$ids = array();
	for ( $i = 0; $i < 3; $i++ ) { list( $code, $s ) = digi_rest( 'POST', $ns . '/uploads', array( 'name' => 'digitalisimo-' . $i . '.zip', 'size' => 1000 ) ); $ids[] = $s['id']; }
	list( $code, $error ) = digi_rest( 'POST', $ns . '/uploads', array( 'name' => 'digitalisimo-x.zip', 'size' => 1000 ) );
	digi_assert( 409 === $code && 'digitalisimo_backups_upload_limit' === $error['code'], 'Se empezó una cuarta subida sin terminar.' );
	list( $code, $cancelled ) = digi_rest( 'DELETE', $ns . '/uploads/' . $ids[0] );
	digi_assert( 200 === $code && true === $cancelled['cancelled'] && ! is_dir( $store . '/incoming/' . $ids[0] ), 'Cancelar no borró la subida.' );
	$state = $U::read( $ids[1] );
	$state['updated_at'] = time() - 90000;
	$U::write( $state );
	list( $code ) = digi_rest( 'GET', $ns . '/uploads/' . $ids[1] );
	digi_assert( 404 === $code, 'Una subida caducada siguió disponible.' );
	Digitalisimo_Backups_Jobs::watchdog();
	digi_assert( ! is_dir( $store . '/incoming/' . $ids[1] ) && is_dir( $store . '/incoming/' . $ids[2] ), 'El watchdog no borró la subida caducada o borró una viva.' );
	// De otra cuenta: no se continúa ni se termina.
	$state = $U::read( $ids[2] );
	$state['user_id'] = 99;
	$U::write( $state );
	list( $code ) = digi_rest( 'POST', $ns . '/uploads/' . $ids[2] . '/finish' );
	list( $code2 ) = $rest_chunk( $ids[2], 0, 'x' );
	digi_assert( 403 === $code && 403 === $code2, 'Otra cuenta continuó o terminó una subida ajena.' );
	foreach ( array( '../../wp-config', 'no-es-un-id' ) as $bad ) { list( $code ) = $rest_chunk( $bad, 0, 'x' ); digi_assert( 400 === $code, 'Se aceptó un identificador de subida inválido.' ); }

	// 8. Un respaldo cifrado de «otro servidor»: sin su frase se acepta tras comprobar su cabecera; con una frase
	// equivocada se conserva la subida; con la correcta se comprueba entero.
	$passphrase = 'frase de la subida con ñ y <signos>';
	$key = Digitalisimo_Backups_Keys::set_passphrase( $passphrase )['key_id'];
	update_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => true, 'require_signed' => false ) );
	$sealed = digi_backup();
	digi_assert( ! is_wp_error( $sealed ) && '.dgbk' === substr( $sealed['file'], -5 ), 'No se creó el respaldo cifrado.' );
	$dgbk = file_get_contents( $store . '/' . $sealed['file'] );
	update_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => false, 'require_signed' => false ) );
	delete_option( Digitalisimo_Backups_Keys::KEYRING );
	list( $code, $blind ) = $upload( 'copia-cifrada.zip', $dgbk );
	digi_assert( 200 === $code && '.dgbk' === substr( $blind['file'], -5 ) && true === $blind['needs_secret'] && false === $blind['verified'] && Digitalisimo_Backups_Keys::short( $key ) === $blind['key'], 'Un .dgbk sin su frase no se aceptó con la cabecera comprobada: ' . json_encode( $blind ) );
	list( $code, $error, $upload_id ) = $upload( 'copia-cifrada.dgbk', $dgbk, array( 'finish' => array( 'secret' => 'otra frase cualquiera' ) ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_secret_wrong' === $error['code'] && is_dir( $store . '/incoming/' . $upload_id ) && false === strpos( json_encode( $error ), 'otra frase' ), 'Una frase equivocada descartó la subida o volvió en la respuesta: ' . json_encode( $error ) );
	list( $code, $checked ) = digi_rest( 'POST', $ns . '/uploads/' . $upload_id . '/finish', array( 'secret' => $passphrase ) );
	digi_assert( 200 === $code && true === $checked['verified'] && 'signed' === $checked['trust'] && 'RESTAURAR' === $checked['confirmation'] && false === strpos( json_encode( $checked ), $passphrase ), 'Con la frase, el .dgbk no se comprobó entero: ' . json_encode( $checked ) );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/uploads/' . $upload_id . '/finish', array(), true, array( 'secret' => $passphrase ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_secret_in_url' === $error['code'], 'La frase en la URL se aceptó al terminar una subida.' );

	// 9. La tarjeta de la pantalla y, en una red, sólo el sitio principal.
	ob_start();
	$U::panel( false );
	$html = (string) ob_get_clean();
	digi_assert( false !== strpos( $html, 'data-chunk="' . ( 204800 - 65536 ) . '"' ) && false !== strpos( $html, 'type="file"' ) && false === strpos( $html, $root ), 'La tarjeta «Subir un respaldo» no es la esperada.' );
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['digi']['main'] = false;
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/uploads', array( 'name' => 'digitalisimo-red.zip', 'size' => 10 ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_main_site' === $error['code'], 'Un subsitio empezó una subida al almacén de la red.' );

	echo "DIGITALÍSIMO Backups: subida por fragmentos (pantalla y REST) al almacén privado, con reanudación, límites y validación de archivo no confiable antes de entrar como «sin registro».\n";
} finally {
	digi_rmtree( $root );
}
