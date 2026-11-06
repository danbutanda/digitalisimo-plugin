<?php
/** El respaldo manual avisa antes de encolar si falta un archivo indispensable y su estado vive en la cola persistente. */
$root = sys_get_temp_dir() . '/digitalisimo-manual-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public/wp-content/plugins', 0700, true );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
$_SERVER['DOCUMENT_ROOT'] = $root . '/public';
$_POST = array( 'nonce' => 'ok', 'context' => 'site', 'destination' => 'local' );
$GLOBALS['options'] = array();

function is_multisite() { return false; }
function get_current_user_id() { return 17; }
function get_current_blog_id() { return 1; }
function current_user_can( $capability ) { return 'manage_options' === $capability; }
function check_ajax_referer( $action, $name ) { if ( 'digitalisimo_backups_manual' !== $action || 'ok' !== $_POST[ $name ] ) throw new RuntimeException( 'Nonce inválido.' ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) ); }
function sanitize_text_field( $value ) { return (string) $value; }
function sanitize_title( $value ) { return preg_replace( '/[^a-z0-9-]/', '-', strtolower( (string) $value ) ); }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function home_url() { return 'https://ejemplo.test'; }
function wp_generate_uuid4() { return Digitalisimo_Backups_Job_Store::new_id(); }
function wp_unslash( $value ) { return $value; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || @mkdir( $path, 0700, true ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_send_json_error( $data, $status = 200 ) { throw new RuntimeException( 'ERROR:' . $status . ':' . $data['message'] ); }
function wp_send_json_success( $data ) { throw new RuntimeException( 'OK:' . json_encode( $data ) ); }
// manual_start() encola por el caso de uso compartido con REST y WP-CLI, que responde con WP_Error.
class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }

require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
// La opción del avance manual de 1.0.58 ya no bloquea un respaldo nuevo aunque diga «running».
$GLOBALS['options']['digitalisimo_backups_manual_job_17'] = array( 'token' => 'anterior', 'status' => 'running', 'updated' => time() );
try {
	Digitalisimo_Backups::manual_start();
	throw new RuntimeException( 'Se inició un respaldo sin wp-config.php.' );
} catch ( RuntimeException $error ) {
	if ( false === strpos( $error->getMessage(), 'No se puede leer wp-config.php' ) ) throw $error;
}
if ( Digitalisimo_Backups_Job_Store::all() ) throw new RuntimeException( 'Se creó un trabajo aunque falló la validación temprana.' );
file_put_contents( $root . '/wp-config.php', '<?php' );
$_POST['destination'] = 'sftp';
try {
	Digitalisimo_Backups::manual_start();
	throw new RuntimeException( 'Se aceptó un destino sin conexión.' );
} catch ( RuntimeException $error ) {
	if ( false === strpos( $error->getMessage(), 'ERROR:400:El destino seleccionado' ) ) throw $error;
}
$_POST['destination'] = 'local';
try {
	Digitalisimo_Backups::manual_start();
	throw new RuntimeException( 'No se creó el trabajo después de corregir el requisito.' );
} catch ( RuntimeException $response ) {
	if ( 0 !== strpos( $response->getMessage(), 'OK:' ) ) throw $response;
	$started = json_decode( substr( $response->getMessage(), 3 ), true );
}
$jobs = Digitalisimo_Backups_Job_Store::all();
if ( 1 !== count( $jobs ) || 'queued' !== $jobs[0]['status'] || 1 !== $jobs[0]['spec']['origin']['blog_id'] || $jobs[0]['id'] !== ( $started['token'] ?? '' ) || 'queued' !== ( $started['job']['status'] ?? '' ) ) throw new RuntimeException( 'El trabajo no conserva estado, identificador y sitio en la cola persistente.' );
if ( isset( $started['view']['spec'] ) || isset( $started['view']['lease'] ) || false !== strpos( json_encode( $started ), DIGITALISIMO_BACKUPS_STORAGE_DIR ) ) throw new RuntimeException( 'La respuesta al navegador incluye datos internos del trabajo.' );
try {
	Digitalisimo_Backups::manual_start();
	throw new RuntimeException( 'Se encoló un segundo respaldo manual.' );
} catch ( RuntimeException $error ) {
	if ( false === strpos( $error->getMessage(), 'ERROR:409:' ) ) throw $error;
}
$_POST['token'] = $started['token'];
try {
	Digitalisimo_Backups::manual_status();
	throw new RuntimeException( 'No se devolvió el avance del trabajo.' );
} catch ( RuntimeException $response ) {
	if ( false === strpos( $response->getMessage(), '"status":"queued"' ) ) throw $response;
}
$_POST['token'] = '../../../etc/passwd';
try {
	Digitalisimo_Backups::manual_status();
	throw new RuntimeException( 'Se aceptó un identificador que no es de trabajo.' );
} catch ( RuntimeException $error ) {
	if ( false === strpos( $error->getMessage(), 'ERROR:404:' ) ) throw $error;
}
mkdir( ABSPATH . 'wp-admin' );
mkdir( ABSPATH . 'wp-includes' );
foreach ( array( 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php' ) as $required ) file_put_contents( ABSPATH . $required, '<?php' );
$archive = new ZipArchive();
$probe = $root . '/private/config-probe.zip';
$archive->open( $probe, ZipArchive::CREATE );
$entries = array();
$add_files = new ReflectionMethod( 'Digitalisimo_Backups', 'add_full_files' );
if ( PHP_VERSION_ID < 80100 ) $add_files->setAccessible( true );
$add_files->invokeArgs( null, array( $archive, $root . '/private', &$entries ) );
$archive->close();
$archive->open( $probe );
if ( '<?php' !== $archive->getFromName( 'wordpress/wp-config.php' ) || ! isset( $entries['wordpress/wp-config.php'] ) ) throw new RuntimeException( 'No se incluyó wp-config.php ubicado sobre ABSPATH.' );
$archive->close();
unlink( $probe );
foreach ( array( 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php' ) as $required ) unlink( ABSPATH . $required );
rmdir( ABSPATH . 'wp-admin' );
rmdir( ABSPATH . 'wp-includes' );
unlink( $root . '/wp-config.php' );
Digitalisimo_Backups_Job_Store::remove_tree( $root . '/private' );
rmdir( $root . '/public/wp-content/plugins' );
rmdir( $root . '/public/wp-content' );
rmdir( $root . '/public' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: requisitos tempranos y estado de avance verificados.\n";
