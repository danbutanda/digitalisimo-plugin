<?php
/**
 * Instalador de DIGITALÍSIMO Elements (digitalisimo-elements-installer/): lista blanca del índice y del ZIP, origen de
 * pruebas sólo de bucle local y sólo con su filtro, permisos y nonce, decisión de activación en la red o en el sitio,
 * Elements ya instalado (no reinstala), autoborrado sólo si todo salió bien, redirección tras activarlo, cabecera
 * (mismos requisitos que Elements) y que su ZIP, sin versión en el nombre, no lo toma ninguna expresión de assets de
 * los actualizadores y catálogos.
 */
define( 'ABSPATH', sys_get_temp_dir() . '/digitalisimo-installer-test/' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
define( 'MINUTE_IN_SECONDS', 60 );
$root = dirname( __DIR__ );
$fails = 0;
function check_installer( $ok, $message ) {
	if ( ! $ok ) {
		$GLOBALS['fails']++;
		echo "FALLA: $message\n";
	}
}

// ── Núcleo de WordPress simulado ────────────────────────────────────────────────────────────────────────────────────
class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
$hooks = array();
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; return true; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; return true; }
function remove_filter( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][ $hook ] = array_values( array_filter( $GLOBALS['hooks'][ $hook ] ?? array(), function ( $c ) use ( $callback ) { return $c !== $callback; } ) ); return true; }
function apply_filters( $hook, $value, ...$args ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $callback ) { $value = $callback( $value, ...$args ); } return $value; }
function register_activation_hook( $file, $callback ) { $GLOBALS['activation_hook'] = $callback; }
function plugin_basename( $file ) { return $GLOBALS['self_file']; }
function is_multisite() { return $GLOBALS['multisite']; }
function is_network_admin() { return $GLOBALS['network_admin']; }
function get_current_blog_id() { return $GLOBALS['blog']; }
function get_main_site_id() { return 1; }
function get_current_user_id() { return $GLOBALS['user']; }
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'], true ); }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function wp_verify_nonce( $nonce, $action ) { return 'nonce-' . $action === $nonce ? 1 : false; }
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { return '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; }
function wp_unslash( $value ) { return $value; }
function admin_url( $path = '' ) { return 'https://example.test' . ( 1 === $GLOBALS['blog'] ? '' : '/sitio' . $GLOBALS['blog'] ) . '/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://example.test/wp-admin/network/' . $path; }
function get_admin_url( $blog, $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $value = null, $url = null ) {
	if ( ! is_array( $args ) ) { $args = array( $args => $value ); } else { $url = $value; }
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function wp_strip_all_tags( $text ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text ) ) ); }
function wp_doing_ajax() { return ! empty( $GLOBALS['ajax'] ); }
function add_submenu_page( ...$args ) { $GLOBALS['calls'][] = array( 'submenu', $args ); return 'plugins_page_' . $args[4]; }
function wp_raise_memory_limit( $context ) { return '256M'; }
function get_site_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_site_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function get_plugins() { return $GLOBALS['plugins']; }
function is_plugin_active_for_network( $file ) { return in_array( $file, $GLOBALS['sitewide'], true ); }
function is_plugin_active( $file ) { return in_array( $file, $GLOBALS['active'], true ) || is_plugin_active_for_network( $file ); }
function activate_plugin( $file, $redirect = '', $network = false, $silent = false ) {
	$GLOBALS['calls'][] = array( 'activate', $file, $network );
	if ( ! empty( $GLOBALS['activation_fatal'] ) ) ( $GLOBALS['activation_fatal'] )();
	// Con «unexpected_output» WordPress deja el plugin activo y sólo avisa de lo que imprimió.
	$output = is_wp_error( $GLOBALS['activation_error'] ) && 'unexpected_output' === $GLOBALS['activation_error']->get_error_code();
	if ( $GLOBALS['activation_error'] && ! $output ) return $GLOBALS['activation_error'];
	if ( $network ) $GLOBALS['sitewide'][] = $file; else $GLOBALS['active'][] = $file;
	return $output ? $GLOBALS['activation_error'] : null;
}
function deactivate_plugins( $plugins, $silent = false, $network = null ) {
	$GLOBALS['calls'][] = array( 'deactivate', $plugins, $network );
	$list = $network ? 'sitewide' : 'active';
	$GLOBALS[ $list ] = array_values( array_diff( $GLOBALS[ $list ], (array) $plugins ) );
}
function delete_plugins( $plugins ) { $GLOBALS['calls'][] = array( 'delete', $plugins ); return true; }
function request_filesystem_credentials( $form_post, $type = '', $error = false, $context = '', $extra = null ) { return $GLOBALS['fs_credentials']; }
function WP_Filesystem( $credentials = false ) { return true; }
function wp_safe_remote_get( $url, $args = array() ) { $GLOBALS['calls'][] = array( 'get', $url ); return $GLOBALS['index_response']; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
/** Las redirecciones lanzan una excepción con el destino final, para no llegar al `exit`. */
class Redirected extends Exception {}
function home_url( $path = '' ) { return $GLOBALS['home'] . $path; }
function wp_redirect( $url, $status = 302 ) { throw new Redirected( $url ); }
/** Como WordPress: un host distinto del de home_url() cae en admin_url(). */
function wp_safe_redirect( $url, $status = 302 ) { throw new Redirected( parse_url( $url, PHP_URL_HOST ) === parse_url( home_url(), PHP_URL_HOST ) ? $url : admin_url() ); }
function redirect_to() { try { Digitalisimo_Elements_Installer::maybe_redirect(); } catch ( Redirected $r ) { return $r->getMessage(); } return ''; }
function get_temp_dir() { return ABSPATH . 'tmp/'; }
function show_message( $message ) { echo is_wp_error( $message ) ? $message->get_error_message() : $message, "\n"; }
class WP_Upgrader {
	public static function create_lock( $name, $timeout = null ) { $GLOBALS['calls'][] = array( 'lock', $name ); return $GLOBALS['lock_free']; }
	public static function release_lock( $name ) { $GLOBALS['calls'][] = array( 'unlock', $name ); return true; }
}
class WP_Upgrader_Skin {
	public $options; public $done_header = false; public $done_footer = false; public $result = false; public $upgrader;
	public function __construct( $args = array() ) { $this->options = $args; }
	public function request_filesystem_credentials( $error = false, $context = '', $allow_relaxed_file_ownership = false ) { return $GLOBALS['fs_credentials']; }
	public function error( $errors ) { show_message( $errors ); }
	public function feedback( $feedback, ...$args ) { show_message( $feedback ); }
	public function set_result( $result ) { $this->result = $result; }
}
/** Plugin_Upgrader simulado: $GLOBALS['install'] decide qué pasa al instalar. */
class Plugin_Upgrader {
	public $skin;
	public function __construct( $skin ) { $this->skin = $skin; $GLOBALS['calls'][] = array( 'upgrader' ); }
	public function install( $package ) { $GLOBALS['calls'][] = array( 'install', $package ); return ( $GLOBALS['install'] )( $this->skin, $package ); }
}

// Estado base de cada caso.
const SELF_FILE = 'digitalisimo-elements-installer/digitalisimo-elements-installer.php';
const ELEMENTS = 'digitalisimo-elements/pro-elements.php';
const TAG_URL = 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.12.481/';
function index_body( $url = null, $version = '4.3.0.185' ) {
	return json_encode( array( 'tag' => 'v2026.11.12.481', 'plugins' => array( 'digitalisimo-seo' => array( 'version' => '1.0.253', 'url' => TAG_URL . 'digitalisimo-seo-1.0.253.zip' ), 'digitalisimo-elements' => array( 'version' => $version, 'url' => $url ?? TAG_URL . 'digitalisimo-elements-' . $version . '.zip' ) ), 'themes' => array() ) );
}
function elements_data() { return array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.185', 'UpdateURI' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-elements' ); }
function reset_state( $overrides = array(), $wipe_temp = true ) {
	$GLOBALS['self_file'] = SELF_FILE;
	$GLOBALS['multisite'] = false; $GLOBALS['network_admin'] = false; $GLOBALS['blog'] = 1; $GLOBALS['user'] = 7;
	$GLOBALS['caps'] = array( 'install_plugins', 'activate_plugins', 'manage_network_plugins' );
	$GLOBALS['plugins'] = array( SELF_FILE => array( 'Name' => 'DIGITALÍSIMO Elements · Instalador', 'Version' => '1.0.0', 'UpdateURI' => 'false' ) );
	$GLOBALS['active'] = array( SELF_FILE ); $GLOBALS['sitewide'] = array();
	$GLOBALS['calls'] = array(); $GLOBALS['transients'] = array(); $GLOBALS['activation_error'] = null; $GLOBALS['activation_fatal'] = null; $GLOBALS['lock_free'] = true; $GLOBALS['ajax'] = false;
	$GLOBALS['fs_credentials'] = true; $GLOBALS['home'] = 'https://example.test';
	$GLOBALS['index_response'] = array( 'code' => 200, 'body' => index_body() );
	// Instalación correcta: deja Elements en su carpeta.
	$GLOBALS['install'] = $GLOBALS['install_ok'] = function ( $skin, $package ) { $GLOBALS['plugins'][ ELEMENTS ] = elements_data(); @mkdir( WP_PLUGIN_DIR . '/digitalisimo-elements', 0777, true ); touch( WP_PLUGIN_DIR . '/' . ELEMENTS ); return true; };
	$_REQUEST = array( 'instalar' => '1', '_wpnonce' => 'nonce-digitalisimo-elements-installer' ); $_GET = array();
	foreach ( $overrides as $key => $value ) $GLOBALS[ $key ] = $value;
	@unlink( WP_PLUGIN_DIR . '/' . ELEMENTS );
	@mkdir( get_temp_dir(), 0777, true );
	if ( $wipe_temp ) array_map( 'unlink', (array) glob( get_temp_dir() . '*.tmp' ) );
}
function called( $what ) { return array_values( array_filter( $GLOBALS['calls'], function ( $c ) use ( $what ) { return $c[0] === $what; } ) ); }
/** Corre handle() en su ámbito y devuelve el resultado (la salida del skin se descarta). */
function run_handle( $scope = null ) { $scope = $scope ?? Digitalisimo_Elements_Installer::scope(); ob_start(); $result = Digitalisimo_Elements_Installer::handle( $scope ); ob_end_clean(); return $result; }

reset_state( array(), ! ( isset( $argv[1] ) && '--child' === $argv[1] ) ); // un hijo no borra los ZIP temporales del padre
require $root . '/digitalisimo-elements-installer/digitalisimo-elements-installer.php';
$I = 'Digitalisimo_Elements_Installer';

// Proceso hijo (--child <caso>): la petición se corta a mitad de la instalación sin pasar por el `finally`, como cuando
// PHP la mata porque el navegador cortó o por un error fatal; sólo corren las funciones de cierre. El fatal es el de
// tiempo agotado: tras agotar la memoria, PHP 7.4 no llama a ninguna función de cierre (ni a la del manejador de
// WordPress), y PHP 8 sí.
// Las llamadas registradas (cerrojo incluido) quedan en ABSPATH/hijo-<caso>.json.
if ( isset( $argv[1] ) && '--child' === $argv[1] ) {
	reset_state( array(), false );
	$case = (string) ( $argv[2] ?? '' );
	$report = function () use ( $case ) { file_put_contents( ABSPATH . 'hijo-' . $case . '.json', json_encode( $GLOBALS['calls'] ) ); };
	$timeout = function () { set_time_limit( 1 ); for ( $i = 0; ; $i++ ) { $GLOBALS['spin'] = $i; } };
	if ( 'descarga' === $case ) {
		// Tiempo agotado a mitad de la descarga, con el ZIP temporal ya creado.
		$GLOBALS['install'] = function () use ( $report, $timeout ) {
			file_put_contents( get_temp_dir() . 'digitalisimo-elements-4.3.0.185-abc123.tmp', 'ZIP a medias' );
			register_shutdown_function( $report );
			$timeout();
		};
	} elseif ( 'activacion' === $case ) {
		// Error fatal al incluir Elements en activate_plugin(), que define WP_SANDBOX_SCRAPING como WordPress: su
		// manejador de errores fatales no actúa y la causa sólo puede darla la función de cierre del instalador.
		$GLOBALS['activation_fatal'] = function () use ( $report, $timeout ) {
			if ( ! defined( 'WP_SANDBOX_SCRAPING' ) ) define( 'WP_SANDBOX_SCRAPING', true );
			register_shutdown_function( $report );
			$timeout();
		};
	} elseif ( 'aborto' === $case ) {
		// Servida por `php -S`: la instalación escribe y vacía la salida durante ~3 s mientras el cliente ya cortó.
		$GLOBALS['install'] = function ( $skin, $package ) use ( $report ) {
			register_shutdown_function( $report );
			for ( $i = 0; $i < 20; $i++ ) { echo str_repeat( ' ', 8192 ), "\n"; flush(); usleep( 150000 ); }
			return ( $GLOBALS['install_ok'] )( $skin, $package );
		};
	}
	ob_implicit_flush( true );
	Digitalisimo_Elements_Installer::handle( 'site' );
	$report();
	exit( 0 );
}
/** Corre un caso hijo en otro proceso de PHP y devuelve su salida y sus llamadas. */
function child( $case ) {
	@unlink( ABSPATH . 'hijo-' . $case . '.json' );
	$out = array();
	exec( escapeshellarg( PHP_BINARY ) . ' -d display_errors=1 ' . escapeshellarg( __FILE__ ) . ' --child ' . escapeshellarg( $case ) . ' 2>&1', $out );
	return array( implode( "\n", $out ), (array) json_decode( (string) @file_get_contents( ABSPATH . 'hijo-' . $case . '.json' ), true ) );
}
/**
 * El caso «aborto» detrás de `php -S`: el cliente lee un poco y corta, como un navegador que recarga o un proxy que se
 * cansa. Devuelve las llamadas del hijo (null si no se pudo levantar el servidor).
 */
function aborted_child() {
	$dir = ABSPATH . 'aborto/';
	@mkdir( $dir, 0777, true );
	@unlink( ABSPATH . 'hijo-aborto.json' );
	file_put_contents( $dir . 'index.php', '<?php $argv = array( "x", "--child", "aborto" ); require ' . var_export( __FILE__, true ) . ';' );
	for ( $try = 0; $try < 5; $try++ ) {
		$port = random_int( 20000, 60000 );
		$server = proc_open( array( PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $dir ), array( 1 => array( 'file', ABSPATH . 'aborto.log', 'a' ), 2 => array( 'file', ABSPATH . 'aborto.log', 'a' ) ), $pipes );
		$socket = false;
		for ( $wait = 0; $wait < 30 && ! $socket; $wait++ ) { usleep( 100000 ); $socket = @fsockopen( '127.0.0.1', $port, $errno, $errstr, 1 ); }
		if ( ! $socket ) { proc_terminate( $server ); proc_close( $server ); continue; }
		fwrite( $socket, "GET /index.php HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n" );
		stream_set_timeout( $socket, 2 );
		fread( $socket, 4096 );
		fclose( $socket );
		for ( $wait = 0; $wait < 150 && ! is_file( ABSPATH . 'hijo-aborto.json' ); $wait++ ) usleep( 100000 );
		usleep( 300000 );
		proc_terminate( $server );
		proc_close( $server );
		return (array) json_decode( (string) @file_get_contents( ABSPATH . 'hijo-aborto.json' ), true );
	}
	return null;
}

// ── 1. Cabecera: nombre visible, versión propia y los mismos requisitos que Elements ───────────────────────────────
$source = file_get_contents( $root . '/digitalisimo-elements-installer/digitalisimo-elements-installer.php' );
$elements = file_get_contents( $root . '/digitalisimo-elements/pro-elements.php' );
$header = function ( $text, $key ) { return preg_match( '/^ \* ' . preg_quote( $key, '/' ) . ': (.+)$/m', $text, $m ) ? trim( $m[1] ) : null; };
check_installer( 'DIGITALÍSIMO Elements · Instalador' === $header( $source, 'Plugin Name' ), 'Nombre visible «DIGITALÍSIMO Elements · Instalador».' );
check_installer( (bool) preg_match( '/^\d+\.\d+\.\d+$/', (string) $header( $source, 'Version' ) ) && $header( $source, 'Version' ) === $I::VERSION, 'Versión propia en la cabecera y en VERSION.' );
check_installer( $header( $source, 'Requires at least' ) === $header( $elements, 'Requires at least' ) && '7.4' === $header( $source, 'Requires PHP' ), 'Los mismos requisitos de WordPress que Elements y PHP 7.4.' );
check_installer( 'false' === $header( $source, 'Update URI' ) && null === $header( $source, 'Network' ), 'Sin actualizador (Update URI: false) y activable en un sitio o en la red.' );
check_installer( ! preg_match( '/wp_enqueue_style|wp_register_style|digitalisimo_ui_|require[^;]*digitalisimo-ui/', $source ), 'No carga la hoja compartida digitalisimo-ui/ ni estilos propios (pantalla efímera del núcleo).' );
check_installer( 0 !== strpos( $I::PAGE, 'digitalisimo' ), 'Su página no empieza por «digitalisimo»: el cargador del diseño compartido no la toma.' );
check_installer( ! preg_match( '/<script|wp_enqueue_script|wp_add_inline_script/i', $source ), 'Sin JavaScript propio.' );

// ── 2. Lista blanca del ZIP ─────────────────────────────────────────────────────────────────────────────────────────
$good = TAG_URL . 'digitalisimo-elements-4.3.0.185.zip';
check_installer( $I::accepts( $good, '4.3.0.185' ), 'Acepta el ZIP de Elements de una Release oficial.' );
$bad = array(
	'otra cuenta' => 'https://github.com/otro/digitalisimo-plugin/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
	'otro repositorio' => 'https://github.com/danbutanda/otro-plugin/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
	'http' => 'http://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
	'.. en la etiqueta' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/../digitalisimo-elements-4.3.0.185.zip',
	'.. en la ruta' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.12.481/../../../otro/repo/releases/download/v1/digitalisimo-elements-4.3.0.185.zip',
	'.. codificado' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/%2e%2e/digitalisimo-elements-4.3.0.185.zip',
	'otro ZIP' => TAG_URL . 'digitalisimo-seo-4.3.0.185.zip',
	'el propio instalador' => TAG_URL . 'digitalisimo-elements-installer.zip',
	'otra versión en el nombre' => TAG_URL . 'digitalisimo-elements-4.3.0.184.zip',
	'sufijo en el nombre' => TAG_URL . 'digitalisimo-elements-4.3.0.185-evil.zip',
	'otro host' => 'https://objects.githubusercontent.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
	'host parecido' => 'https://github.com.evil.example/danbutanda/digitalisimo-plugin/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
	'usuario en la URL' => 'https://github.com@evil.example/danbutanda/digitalisimo-plugin/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
	'puerto' => 'https://github.com:8443/danbutanda/digitalisimo-plugin/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
	'consulta' => $good . '?x=1',
	'fragmento' => $good . '#x',
	'salto de línea final' => $good . "\n",
	'mayúsculas' => 'HTTPS://GITHUB.COM/danbutanda/digitalisimo-plugin/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
	'etiqueta ajena' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/latest/digitalisimo-elements-4.3.0.185.zip',
	'prefijo ajeno' => 'https://evil.example/' . $good,
	'prefijo ajeno en la consulta' => 'https://evil.example/?u=' . $good,
	'origen de pruebas sin su filtro' => 'http://127.0.0.1:9539/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip',
);
foreach ( $bad as $why => $url ) {
	check_installer( ! $I::accepts( $url, '4.3.0.185' ), "Rechaza el ZIP con $why: $url" );
}
foreach ( array( '', '4', '4.3.0.185x', '../4.3', "4.3.0.185\n" ) as $version ) {
	check_installer( ! $I::accepts( TAG_URL . 'digitalisimo-elements-' . $version . '.zip', $version ), 'Rechaza la versión «' . addcslashes( $version, "\n" ) . '».' );
}
// Índice: entrada ausente, ilegible o con enlace ajeno.
check_installer( is_wp_error( $I::package_from_index( null ) ) && 'indice_ilegible' === $I::package_from_index( 'x' )->get_error_code(), 'Un índice que no es el JSON esperado es ilegible.' );
check_installer( 'indice_sin_elements' === $I::package_from_index( array( 'plugins' => array( 'digitalisimo-seo' => array() ) ) )->get_error_code(), 'Un índice sin Elements se informa.' );
check_installer( 'paquete_rechazado' === $I::package_from_index( json_decode( index_body( $bad['otra cuenta'] ), true ) )->get_error_code(), 'Un índice con un enlace ajeno se rechaza.' );
$package = $I::package_from_index( json_decode( index_body(), true ) );
check_installer( is_array( $package ) && $good === $package['url'] && '4.3.0.185' === $package['version'], 'El índice oficial da el ZIP y la versión de Elements.' );

// ── 3. Origen de pruebas: sólo con su filtro y sólo de bucle local ─────────────────────────────────────────────────
check_installer( 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json' === $I::index_url(), 'Sin filtro, el índice es el de la última Release oficial.' );
$test_url = 'http://127.0.0.1:9539/releases/download/v2026.11.12.481/digitalisimo-elements-4.3.0.185.zip';
foreach ( array( 'https://evil.example/', 'http://evil.example:9539/', 'http://127.0.0.1/', 'http://127.0.0.1:9539', 'http://10.0.0.5:9539/', 'file:///tmp/', 'http://127.0.0.1:9539/../' ) as $base ) {
	$filter = function () use ( $base ) { return $base; };
	add_filter( $I::TEST_SOURCE_FILTER, $filter );
	check_installer( '' === $I::test_base() && 0 === strpos( $I::index_url(), 'https://github.com/danbutanda/' ) && ! $I::accepts( $test_url, '4.3.0.185' ), "Un origen de pruebas que no es de bucle local se ignora: $base" );
	remove_filter( $I::TEST_SOURCE_FILTER, $filter );
}
$filter = function () { return 'http://127.0.0.1:9539/'; };
add_filter( $I::TEST_SOURCE_FILTER, $filter );
check_installer( 'http://127.0.0.1:9539/releases/latest/download/digitalisimo-plugins.json' === $I::index_url() && $I::accepts( $test_url, '4.3.0.185' ) && ! $I::accepts( $good, '4.3.0.185' ), 'Con el filtro de pruebas sólo vale el servidor local (y no GitHub).' );
check_installer( ! $I::accepts( 'http://127.0.0.1:9539/releases/download/v2026.11.12.481/digitalisimo-seo-1.0.253.zip', '1.0.253' ), 'El origen de pruebas también exige el ZIP de Elements.' );
remove_filter( $I::TEST_SOURCE_FILTER, $filter );

// ── 4. Permisos y nonce: nada sale del servidor sin ellos ──────────────────────────────────────────────────────────
foreach ( array( 'install_plugins' => array( 'activate_plugins', 'manage_network_plugins' ), 'activate_plugins' => array( 'install_plugins', 'manage_network_plugins' ) ) as $missing => $caps ) {
	reset_state( array( 'caps' => $caps ) );
	$result = run_handle();
	check_installer( 'error' === $result['status'] && 'permisos' === $result['code'] && ! called( 'get' ) && ! called( 'upgrader' ) && ! called( 'activate' ) && ! called( 'delete' ), "Sin $missing no se descarga, activa ni borra nada." );
}
reset_state( array( 'multisite' => true, 'network_admin' => true, 'sitewide' => array( SELF_FILE ), 'active' => array(), 'caps' => array( 'install_plugins', 'activate_plugins' ) ) );
$result = run_handle( 'network' );
check_installer( 'permisos' === ( $result['code'] ?? '' ) && ! called( 'get' ), 'En la red hace falta además manage_network_plugins.' );
reset_state();
$_REQUEST['_wpnonce'] = 'nonce-otra-accion';
$result = run_handle();
check_installer( 'nonce' === ( $result['code'] ?? '' ) && ! called( 'get' ) && ! called( 'lock' ) && ! called( 'delete' ), 'Un nonce ajeno se rechaza antes de descargar nada.' );
reset_state();
unset( $_REQUEST['_wpnonce'] );
check_installer( 'nonce' === ( run_handle()['code'] ?? '' ) && ! called( 'get' ), 'Sin nonce no se descarga nada.' );
reset_state( array( 'lock_free' => false ) );
$result = run_handle();
check_installer( 'en_curso' === ( $result['code'] ?? '' ) && ! called( 'get' ) && ! called( 'unlock' ), 'Con otra instalación en curso no se empieza otra.' );
check_installer( false !== strpos( $result['cause'] ?? '', 'aparecerá activo' ), 'Con otra instalación en curso avisa de que, al terminar, Elements aparecerá activo.' );

// ── 5. Instalación correcta en un WordPress individual: activa en el sitio y se borra ──────────────────────────────
reset_state();
$result = run_handle();
$activate = called( 'activate' );
check_installer( 'instalado' === $result['status'] && '4.3.0.185' === $result['version'] && true === $result['removed'], 'Instala, activa y se retira: ' . json_encode( $result ) );
check_installer( array( array( 'get', 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json' ) ) === called( 'get' ) && array( array( 'install', $good ) ) === called( 'install' ), 'Lee el índice oficial e instala su ZIP de Elements.' );
check_installer( array( array( 'activate', ELEMENTS, false ) ) === $activate, 'En un WordPress individual activa Elements en el sitio.' );
check_installer( array( array( 'deactivate', SELF_FILE, false ) ) === called( 'deactivate' ) && array( array( 'delete', array( SELF_FILE ) ) ) === called( 'delete' ), 'Se desactiva y se borra con delete_plugins().' );
$order = array_map( function ( $c ) { return $c[0]; }, $GLOBALS['calls'] );
check_installer( array_search( 'activate', $order, true ) < array_search( 'deactivate', $order, true ) && array_search( 'deactivate', $order, true ) < array_search( 'delete', $order, true ) && 'unlock' === end( $order ), 'Primero activa Elements, después se desactiva y se borra, y libera el cerrojo.' );

// ── 6. Activación en red o en sitio ────────────────────────────────────────────────────────────────────────────────
reset_state( array( 'multisite' => true, 'network_admin' => true, 'sitewide' => array( SELF_FILE ), 'active' => array() ) );
check_installer( 'network' === $I::scope(), 'Instalador activo en toda la red: ámbito de red.' );
$result = run_handle();
check_installer( 'instalado' === $result['status'] && array( array( 'activate', ELEMENTS, true ) ) === called( 'activate' ) && in_array( ELEMENTS, $GLOBALS['sitewide'], true ), 'Activado en toda la red: Elements se activa en toda la red.' );
check_installer( array( array( 'deactivate', SELF_FILE, true ) ) === called( 'deactivate' ) && array( array( 'delete', array( SELF_FILE ) ) ) === called( 'delete' ), 'Y el instalador se desactiva en la red y se borra.' );
reset_state( array( 'multisite' => true, 'blog' => 3, 'active' => array( SELF_FILE ) ) );
check_installer( 'site' === $I::scope(), 'Instalador activo en un sitio de la red: ámbito de sitio.' );
$result = run_handle();
check_installer( 'instalado' === $result['status'] && array( array( 'activate', ELEMENTS, false ) ) === called( 'activate' ) && ! in_array( ELEMENTS, $GLOBALS['sitewide'], true ), 'Activado en un sitio: Elements sólo se activa en ese sitio.' );

// ── 7. Elements ya instalado: no reinstala, activa si hace falta y se retira ───────────────────────────────────────
reset_state();
$GLOBALS['plugins'][ ELEMENTS ] = elements_data();
$result = run_handle();
check_installer( 'ya' === $result['status'] && 'activado' === $result['activated'] && ! called( 'get' ) && ! called( 'upgrader' ) && array( array( 'activate', ELEMENTS, false ) ) === called( 'activate' ) && true === $result['removed'], 'Ya instalado e inactivo: no descarga, lo activa y se retira.' );
reset_state( array( 'active' => array( SELF_FILE, ELEMENTS ) ) );
$GLOBALS['plugins'][ ELEMENTS ] = elements_data();
$result = run_handle();
check_installer( 'ya' === $result['status'] && 'ya' === $result['activated'] && ! called( 'activate' ) && ! called( 'get' ) && called( 'delete' ), 'Ya instalado y activo: no toca Elements y se retira.' );
ob_start(); $I::print_result( $result, 'site' ); $html = ob_get_clean();
check_installer( false !== strpos( $html, 'ya estaba instalado' ) && false !== strpos( $html, 'Actualizaciones' ) && false !== strpos( $html, 'se borró' ), 'Explica que no se reinstaló y cómo actualizarlo con WordPress.' );
reset_state( array( 'multisite' => true, 'network_admin' => true, 'sitewide' => array( SELF_FILE ), 'active' => array() ) );
$GLOBALS['plugins']['digitalisimo-elements-4.3.0.150/pro-elements.php'] = elements_data();
$result = run_handle();
check_installer( 'ya' === $result['status'] && array( array( 'activate', 'digitalisimo-elements-4.3.0.150/pro-elements.php', true ) ) === called( 'activate' ) && ! called( 'upgrader' ), 'Ya instalado en otra carpeta (por su Update URI): se activa ésa en la red, sin instalar otra copia.' );
reset_state( array( 'multisite' => true, 'network_admin' => true, 'sitewide' => array( SELF_FILE ), 'active' => array( ELEMENTS ) ) );
$GLOBALS['plugins'][ ELEMENTS ] = elements_data();
$result = run_handle();
check_installer( 'ya' === $result['status'] && 'activado' === $result['activated'] && array( array( 'activate', ELEMENTS, true ) ) === called( 'activate' ), 'Instalador activo en la red y Elements activo sólo en un sitio: lo activa en toda la red.' );
reset_state();
$GLOBALS['plugins']['pro-elements/pro-elements.php'] = array( 'Name' => 'PRO Elements', 'UpdateURI' => '' );
check_installer( '' === $I::find_elements( $GLOBALS['plugins'] ), 'Otro plugin con pro-elements.php no cuenta como Elements.' );

// ── 8. Autoborrado sólo si todo salió bien ─────────────────────────────────────────────────────────────────────────
$failures = array(
	'sin salida a GitHub' => array( array( 'index_response' => new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: github.com' ) ), 'sin_salida', 'conectarse con GitHub' ),
	'índice HTTP 404' => array( array( 'index_response' => array( 'code' => 404, 'body' => 'Not Found' ) ), 'indice_http', 'HTTP 404' ),
	'índice ilegible' => array( array( 'index_response' => array( 'code' => 200, 'body' => '<html>' ) ), 'indice_ilegible', 'no se pudo leer' ),
	'enlace ajeno en el índice' => array( array( 'index_response' => array( 'code' => 200, 'body' => index_body( $bad['otro host'] ) ) ), 'paquete_rechazado', 'por seguridad' ),
	'descarga fallida' => array( array( 'install' => function ( $skin ) { $skin->error( new WP_Error( 'download_failed', 'Download failed.', 'cURL error 28: Operation timed out after 300001 milliseconds <b>con 0 bytes</b>' ) ); return null; } ), 'instalar', 'No se pudo descargar el paquete desde GitHub: Download failed. (cURL error 28: Operation timed out after 300001 milliseconds con 0 bytes)' ),
	'descarga fallida sin detalle' => array( array( 'install' => function ( $skin ) { $skin->error( new WP_Error( 'download_failed', 'Download failed.', array( 'code' => 7 ) ) ); return null; } ), 'instalar', 'GitHub: Download failed.' ),
	'sin permisos de escritura' => array( array( 'install' => function ( $skin ) { $skin->error( new WP_Error( 'mkdir_failed_copy_dir', 'Could not create directory.' ) ); return null; } ), 'instalar', 'permisos de escritura' ),
	'carpeta a medias' => array( array( 'install' => function ( $skin ) { $skin->set_result( new WP_Error( 'folder_exists', 'Destination folder already exists.' ) ); return new WP_Error( 'folder_exists', 'Destination folder already exists.' ); } ), 'instalar', 'Ya existe la carpeta' ),
	'sin espacio' => array( array( 'install' => function ( $skin ) { $skin->error( new WP_Error( 'disk_full_unzip_file', 'Could not copy files. You may have run out of disk space.' ) ); return null; } ), 'instalar', 'espacio libre' ),
	'paquete dañado' => array( array( 'install' => function ( $skin ) { $skin->error( new WP_Error( 'incompatible_archive', 'Incompatible Archive.' ) ); return null; } ), 'instalar', 'no se pudo abrir' ),
	'requisitos' => array( array( 'install' => function ( $skin ) { return new WP_Error( 'incompatible_php_required_version', 'The PHP version on your server is 7.3.' ); } ), 'instalar', 'requisitos' ),
	'otro plugin en el ZIP' => array( array( 'install' => function () { $GLOBALS['plugins']['otro/otro.php'] = array( 'Name' => 'Otro' ); return true; } ), 'sin_elements', 'no dejó' ),
	'activación fallida' => array( array( 'activation_error' => new WP_Error( 'plugin_not_found', 'Plugin file does not exist.' ) ), 'activar', 'no se pudo activar' ),
);
foreach ( $failures as $why => list( $state, $code, $text ) ) {
	reset_state( $state );
	$result = run_handle();
	check_installer( 'error' === $result['status'] && $code === $result['code'] && false !== strpos( $result['cause'], $text ), "Con $why la causa es clara ($code): " . json_encode( $result, JSON_UNESCAPED_UNICODE ) );
	check_installer( ! called( 'delete' ) && ! called( 'deactivate' ) && in_array( SELF_FILE, $GLOBALS['active'], true ), "Con $why el instalador no se desactiva ni se borra." );
	check_installer( 1 === count( called( 'unlock' ) ), "Con $why el cerrojo se libera." );
	ob_start(); $I::print_result( $result, 'site' ); $html = ob_get_clean();
	check_installer( false !== strpos( $html, 'Reintentar' ) && false !== strpos( $html, 'nonce-digitalisimo-elements-installer' ) && false !== strpos( $html, 'wp-content/plugins/' ) && false !== strpos( $html, 'sigue en Plugins' ), "Con $why: causa, alternativa manual, «Reintentar» con nonce y el instalador se queda." );
}
// Pidió datos FTP: el formulario es del actualizador de WordPress; nada se borra.
reset_state( array( 'fs_credentials' => false, 'install' => function ( $skin ) { $skin->request_filesystem_credentials(); return false; } ) );
$result = run_handle();
check_installer( 'credenciales' === $result['status'] && ! called( 'delete' ) && ! called( 'activate' ), 'Si WordPress pide los datos de acceso a los archivos, no se activa ni se borra nada.' );
// Instalado y activado, pero sin acceso para borrarse: Elements queda activo y el instalador lo dice.
reset_state();
$GLOBALS['install'] = function ( $skin, $package ) { $GLOBALS['plugins'][ ELEMENTS ] = elements_data(); @mkdir( WP_PLUGIN_DIR . '/digitalisimo-elements', 0777, true ); touch( WP_PLUGIN_DIR . '/' . ELEMENTS ); $GLOBALS['fs_credentials'] = false; return true; };
$result = run_handle();
check_installer( 'instalado' === $result['status'] && is_wp_error( $result['removed'] ) && ! called( 'delete' ), 'Sin acceso a los archivos para borrarse, no llama a delete_plugins() (que imprimiría otro formulario y saldría).' );
// Nunca borra la carpeta de Elements: un instalador dentro de ella no se borra.
reset_state( array( 'self_file' => 'digitalisimo-elements/digitalisimo-elements-installer.php' ) );
$GLOBALS['plugins'][ ELEMENTS ] = elements_data();
check_installer( is_wp_error( $I::remove_self( false ) ) && ! called( 'delete' ), 'Un instalador dentro de la carpeta de Elements nunca se borra solo.' );
reset_state( array( 'self_file' => 'digitalisimo-elements-installer.php' ) );
check_installer( is_wp_error( $I::remove_self( false ) ) && ! called( 'delete' ), 'Un instalador suelto en wp-content/plugins no borra nada.' );
// Error fatal que atiende WordPress a mitad de la instalación: su mensaje explica, ofrece la alternativa, libera el
// cerrojo y borra el ZIP temporal (WordPress termina con wp_die() y la función de cierre ya no correría).
reset_state();
$GLOBALS['install'] = function () {
	file_put_contents( get_temp_dir() . 'digitalisimo-elements-4.3.0.185-def456.tmp', 'ZIP a medias' );
	$GLOBALS['fatal_text'] = Digitalisimo_Elements_Installer::fatal_message( '<p>There has been a critical error.</p>', array( 'message' => "Allowed memory size of 134217728 bytes exhausted\nmore" ) );
	return null;
};
run_handle();
$message = (string) ( $GLOBALS['fatal_text'] ?? '' );
check_installer( false !== strpos( $message, 'Allowed memory size' ) && false !== strpos( $message, 'wp-content/plugins/' ) && 1 === count( called( 'unlock' ) ), 'Un error fatal deja la causa, la alternativa manual y el cerrojo libre (una sola vez).' );
check_installer( ! glob( get_temp_dir() . 'digitalisimo-elements-*.tmp' ), 'Un error fatal borra el ZIP temporal de la descarga.' );

// La causa de una descarga fallida lleva el motivo real (los datos de download_failed), escapado en la pantalla.
reset_state( array( 'install' => function ( $skin ) { $skin->error( new WP_Error( 'download_failed', 'Download failed.', 'cURL error 6: Could not resolve host <script>alert(4)</script>' ) ); return null; } ) );
$result = run_handle();
ob_start(); $I::print_result( $result, 'site' ); $html = ob_get_clean();
check_installer( false !== strpos( $html, 'cURL error 6: Could not resolve host' ) && false === strpos( $html, '<script>' ) && false === strpos( $html, 'alert(4)' ), 'La causa de una descarga fallida dice el motivo real, sin etiquetas: ' . strip_tags( $html ) );

// ── 8b. El navegador corta a mitad: la petición sigue y, si PHP la mata, la función de cierre libera el cerrojo ───
reset_state();
ignore_user_abort( false );
run_handle();
check_installer( 1 === (int) ignore_user_abort() && 1 === count( called( 'unlock' ) ), 'Al empezar, la instalación sigue aunque el navegador corte (ignore_user_abort) y libera el cerrojo al terminar.' );
if ( method_exists( $I, 'shutdown' ) ) $I::shutdown();
check_installer( 1 === count( called( 'unlock' ) ), 'Tras terminar bien, la función de cierre no libera otra vez el cerrojo (podría ser ya el de otra petición).' );
reset_state();
file_put_contents( get_temp_dir() . 'digitalisimo-elements-4.3.0.184-viejo1.tmp', 'de antes' );
touch( get_temp_dir() . 'digitalisimo-elements-4.3.0.184-viejo1.tmp', time() - 3600 );
list( $text, $calls ) = child( 'descarga' );
$names = array_map( function ( $c ) { return $c[0]; }, $calls );
check_installer( false !== strpos( $text, 'Maximum execution time' ) && in_array( 'lock', $names, true ) && in_array( 'install', $names, true ) && in_array( 'unlock', $names, true ), 'Si PHP muere a mitad de la descarga (sin pasar por el finally), la función de cierre libera el cerrojo: ' . substr( $text, -300 ) . ' ' . json_encode( $names ) );
check_installer( ! is_file( get_temp_dir() . 'digitalisimo-elements-4.3.0.185-abc123.tmp' ) && is_file( get_temp_dir() . 'digitalisimo-elements-4.3.0.184-viejo1.tmp' ), 'Y borra el ZIP temporal de esa descarga, no los anteriores: ' . implode( ', ', array_map( 'basename', (array) glob( get_temp_dir() . '*' ) ) ) );
// Error fatal durante activate_plugin() (WP_SANDBOX_SCRAPING apaga el manejador de WordPress): la función de cierre
// imprime, escapados, la causa, la alternativa manual y «Reintentar» (POST con nonce), y libera el cerrojo.
reset_state();
list( $text, $calls ) = child( 'activacion' );
$names = array_map( function ( $c ) { return $c[0]; }, $calls );
check_installer( false !== strpos( $text, 'se interrumpió por un error de PHP (Maximum execution time of 1 second exceeded)' ) && false !== strpos( $text, 'Alternativa manual' ) && false !== strpos( $text, 'wp-content/plugins/' ), 'Un error fatal al activar Elements explica la causa y la alternativa manual: ' . substr( $text, -400 ) );
check_installer( (bool) preg_match( '#<form method="post" action="[^"]*page=instalar-digitalisimo-elements[^"]*instalar=1"><input type="hidden" name="_wpnonce" value="nonce-digitalisimo-elements-installer">.*Reintentar</button>#s', $text ), 'Y ofrece «Reintentar» por POST con nonce.' );
check_installer( in_array( 'activate', $names, true ) && in_array( 'unlock', $names, true ) && ! in_array( 'deactivate', $names, true ) && ! in_array( 'delete', $names, true ), 'Y libera el cerrojo sin retirar el instalador: ' . json_encode( $names ) );
reset_state();
$calls = aborted_child();
$names = array_map( function ( $c ) { return $c[0]; }, (array) $calls );
check_installer( null !== $calls && in_array( 'activate', $names, true ) && in_array( 'delete', $names, true ) && 'unlock' === end( $names ), 'Si el navegador corta mientras se instala (php -S, el cliente se va), la instalación termina igual y libera el cerrojo: ' . json_encode( $names ) . ' ' . substr( (string) @file_get_contents( ABSPATH . 'aborto.log' ), -300 ) );

// ── 8c. Escapado, aviso, menú, formulario y activación con salida inesperada ─────────────────────────────────────
reset_state();
ob_start(); $I::print_result( array( 'status' => 'error', 'code' => 'x', 'cause' => '<script>alert(1)</script>' ), 'site' ); $html = ob_get_clean();
check_installer( false !== strpos( $html, '&lt;script&gt;alert(1)&lt;/script&gt;' ) && false === strpos( $html, '<script>' ), 'La causa se escapa en la pantalla.' );
check_installer( (bool) preg_match( '#<form method="post" action="[^"]*instalar=1">#', $html ) && false === stripos( $html, 'method="get"' ), '«Reintentar» va por POST.' );
$fatal = method_exists( $I, 'fatal_html' ) ? $I::fatal_html( array( 'type' => E_ERROR, 'message' => "<script>alert(2)</script>\nmás" ) ) : '';
check_installer( false !== strpos( $fatal, '&lt;script&gt;alert(2)&lt;/script&gt;' ) && false === strpos( $fatal, '<script>' ) && false === strpos( $fatal, 'más' ), 'El detalle de un error fatal se escapa (sólo su primera línea).' );
reset_state();
$evil = 'digitalisimo-<script>alert(3)</script>/pro-elements.php';
$GLOBALS['plugins'][ $evil ] = elements_data();
unset( $_REQUEST['instalar'] );
ob_start(); $I::render(); $html = ob_get_clean();
check_installer( false !== strpos( $html, '&lt;script&gt;alert(3)&lt;/script&gt;' ) && false === strpos( $html, '<script>' ) && false !== strpos( $html, 'ya está instalado' ), 'La ruta de un Elements ya instalado se escapa en la pantalla.' );
// Aviso con botón: sólo para quien puede instalar y activar, y nunca en la propia pantalla.
reset_state();
ob_start(); $I::notice(); $html = ob_get_clean();
check_installer( false !== strpos( $html, 'Instalar DIGITALÍSIMO Elements' ) && false !== strpos( $html, '_wpnonce=nonce-digitalisimo-elements-installer' ), 'El aviso ofrece el botón (con nonce) a quien puede instalar.' );
foreach ( array( 'sin permisos' => array( 'caps' => array( 'activate_plugins' ) ), 'sin activar plugins' => array( 'caps' => array( 'install_plugins' ) ) ) as $why => $state ) {
	reset_state( $state );
	ob_start(); $I::notice(); $html = ob_get_clean();
	check_installer( '' === $html, "El aviso no sale $why." );
}
reset_state();
$_GET['page'] = $I::PAGE;
ob_start(); $I::notice(); $html = ob_get_clean();
check_installer( '' === $html, 'El aviso no sale en la propia pantalla del instalador.' );
reset_state( array( 'multisite' => true, 'network_admin' => false, 'sitewide' => array( SELF_FILE ), 'active' => array() ) );
ob_start(); $I::notice(); $html = ob_get_clean();
check_installer( '' === $html, 'Activo en la red, el aviso no sale en la administración de un sitio.' );
// Submenú: con su capacidad y sólo en la administración de su ámbito.
reset_state();
$I::menu();
$sub = called( 'submenu' );
check_installer( 1 === count( $sub ) && 'plugins.php' === $sub[0][1][0] && 'activate_plugins' === $sub[0][1][3] && $I::PAGE === $sub[0][1][4], 'Submenú en Plugins con la capacidad activate_plugins: ' . json_encode( $sub ) );
reset_state( array( 'multisite' => true, 'network_admin' => true, 'sitewide' => array( SELF_FILE ), 'active' => array() ) );
$I::menu();
$sub = called( 'submenu' );
check_installer( 1 === count( $sub ) && 'manage_network_plugins' === $sub[0][1][3], 'En la red, el submenú pide manage_network_plugins.' );
reset_state( array( 'multisite' => true, 'network_admin' => true, 'active' => array( SELF_FILE ) ) );
$I::menu();
check_installer( ! called( 'submenu' ), 'Activo sólo en un sitio, no hay submenú en la administración de la red.' );
reset_state( array( 'multisite' => true, 'network_admin' => false, 'sitewide' => array( SELF_FILE ), 'active' => array() ) );
$I::menu();
check_installer( ! called( 'submenu' ), 'Activo en la red, no hay submenú en la administración de un sitio.' );
// En la red, si además estaba activo en el sitio principal, también se desactiva ahí antes de borrarse.
reset_state( array( 'multisite' => true, 'network_admin' => true, 'sitewide' => array( SELF_FILE ), 'active' => array( SELF_FILE ) ) );
$result = run_handle();
check_installer( 'instalado' === $result['status'] && array( array( 'deactivate', SELF_FILE, true ), array( 'deactivate', SELF_FILE, false ) ) === called( 'deactivate' ) && ! in_array( SELF_FILE, $GLOBALS['active'], true ), 'En la red se desactiva en la red y en el sitio donde también estaba activo: ' . json_encode( called( 'deactivate' ) ) );
// Elements imprime algo al activarse («unexpected_output»): WordPress lo deja activo y la instalación sigue bien.
reset_state( array( 'activation_error' => new WP_Error( 'unexpected_output', 'The plugin generated unexpected output.', 'x' ) ) );
$result = run_handle();
check_installer( 'instalado' === $result['status'] && in_array( ELEMENTS, $GLOBALS['active'], true ) && true === $result['removed'], 'Con «unexpected_output» Elements queda activo y el instalador se retira: ' . json_encode( $result ) );

// ── 9. Redirección a su pantalla tras activarlo ─────────────────────────────────────────────────────────────────────
reset_state();
( $GLOBALS['activation_hook'] )( false );
$target = $I::redirect_target();
check_installer( 'https://example.test/wp-admin/plugins.php?page=instalar-digitalisimo-elements&instalar=1&_wpnonce=nonce-digitalisimo-elements-installer' === $target && '' === $I::redirect_target(), 'Tras activarlo lleva una vez a su pantalla, con nonce: ' . $target );
reset_state();
( $GLOBALS['activation_hook'] )( false );
$_GET['activate-multi'] = 'true';
check_installer( '' === $I::redirect_target() && false === get_site_transient( $I::REDIRECT ), 'En una activación en bloque no redirige (queda el aviso con botón).' );
reset_state();
( $GLOBALS['activation_hook'] )( false );
$GLOBALS['user'] = 8;
check_installer( '' === $I::redirect_target() && false !== get_site_transient( $I::REDIRECT ), 'Otra persona no es redirigida.' );
reset_state( array( 'multisite' => true, 'network_admin' => true, 'sitewide' => array( SELF_FILE ), 'active' => array() ) );
( $GLOBALS['activation_hook'] )( true );
check_installer( 0 === strpos( $I::redirect_target(), 'https://example.test/wp-admin/network/plugins.php?page=instalar-digitalisimo-elements&instalar=1' ), 'Activado en la red: lleva a su pantalla en la administración de la red.' );
reset_state( array( 'multisite' => true, 'blog' => 3 ) );
( $GLOBALS['activation_hook'] )( false );
$GLOBALS['blog'] = 4;
check_installer( '' === $I::redirect_target(), 'Activado en un sitio: no redirige en otro sitio.' );
$GLOBALS['blog'] = 3;
check_installer( 0 === strpos( $I::redirect_target(), 'https://example.test/sitio3/wp-admin/plugins.php?page=instalar-digitalisimo-elements' ), 'Activado en un sitio: lleva a su pantalla en ese sitio.' );
reset_state( array( 'caps' => array( 'activate_plugins' ) ) );
( $GLOBALS['activation_hook'] )( false );
check_installer( '' === $I::redirect_target(), 'Sin install_plugins no redirige.' );
// La redirección real (maybe_redirect): también con siteurl y home en hosts distintos (con y sin www, dominio mapeado).
foreach ( array( 'https://example.test', 'https://www.example.test', 'https://tienda.example.com' ) as $home ) {
	reset_state( array( 'home' => $home ) );
	( $GLOBALS['activation_hook'] )( false );
	$to = redirect_to();
	check_installer( 'https://example.test/wp-admin/plugins.php?page=instalar-digitalisimo-elements&instalar=1&_wpnonce=nonce-digitalisimo-elements-installer' === $to, "Tras activarlo redirige a su pantalla con home en $home y siteurl en example.test: $to" );
}
reset_state();
check_installer( '' === redirect_to(), 'Sin activación anotada no redirige.' );
reset_state( array( 'ajax' => true ) );
( $GLOBALS['activation_hook'] )( false );
check_installer( '' === $I::redirect_target() && false === get_site_transient( $I::REDIRECT ), 'En una petición AJAX no redirige (y la anotación se consume).' );

// ── 10. Su ZIP sin versión no lo toma ninguna expresión de assets de la Release ────────────────────────────────────
$patterns = array();
$files = array_merge( glob( $root . '/digitalisimo-*/includes/*.php' ), glob( $root . '/digitalisimo-*/*.php' ), glob( $root . '/digitalisimo-*/inc/*.php' ) );
foreach ( $files as $file ) {
	if ( preg_match_all( "~'(/\\^[^']*?zip\\$/)'~", (string) file_get_contents( $file ), $m ) ) {
		foreach ( $m[1] as $pattern ) $patterns[] = array( str_replace( '\\\\', '\\', $pattern ), basename( dirname( $file ) ) . '/' . basename( $file ) );
	}
}
foreach ( array( 'scripts/verify-release.mjs', 'scripts/release-manifest.mjs' ) as $script ) {
	if ( preg_match_all( '~match\((/\^\(digitalisimo-[^)]*\)[^/]*?zip\$/)\)~', (string) file_get_contents( $root . '/' . $script ), $m ) ) {
		foreach ( $m[1] as $pattern ) $patterns[] = array( $pattern, $script );
	}
}
check_installer( count( $patterns ) >= 12, 'Se encontraron las expresiones de assets de los actualizadores, catálogos y scripts (' . count( $patterns ) . ').' );
foreach ( $patterns as list( $pattern, $where ) ) {
	check_installer( 0 === preg_match( $pattern, 'digitalisimo-elements-installer.zip' ), "La expresión de $where no toma digitalisimo-elements-installer.zip: $pattern" );
	check_installer( 1 === preg_match( $pattern, preg_match( '/digitalisimo-([a-z]+)-/', $pattern, $s ) ? 'digitalisimo-' . $s[1] . '-1.0.1.zip' : 'digitalisimo-seo-1.0.1.zip' ) || false !== strpos( $pattern, '[a-z]+(?:' ), "La expresión de $where sigue tomando un ZIP con versión: $pattern" );
}

@unlink( WP_PLUGIN_DIR . '/' . ELEMENTS );
@rmdir( WP_PLUGIN_DIR . '/digitalisimo-elements' );
if ( $fails ) {
	echo "FALLA: $fails comprobaciones del instalador de Elements\n";
	exit( 1 );
}
echo "Instalador de DIGITALÍSIMO Elements: lista blanca, origen de pruebas local, permisos y nonce, red o sitio, ya instalado, autoborrado sólo al terminar bien, redirección y ZIP sin versión fuera de los assets validados.\n";
