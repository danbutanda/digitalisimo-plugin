<?php
/**
 * Actualizador del tema DIGITALÍSIMO: ofrece la versión del índice de la Release (arrays, sin `autoupdate`, con
 * `disable_autoupdate` si es incompatible), sustituye una respuesta homónima, no atiende a hijos ni a temas ajenos,
 * usa la API sólo si el índice no se pudo descargar, valida el paquete, conserva la carpeta real del tema o se
 * detiene con WP_Error antes de tocar nada, y vacía sus cachés en los momentos justos.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'DIGITALISIMO_THEME_VERSION', '1.0.0' );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['cache']        = array();
$GLOBALS['ttl']          = array();
$GLOBALS['site_options'] = array();
$GLOBALS['calls']        = array();
$GLOBALS['responses']    = array();
$GLOBALS['hooks']        = array();
$GLOBALS['template']     = 'digitalisimo-theme';
$GLOBALS['update_uri']   = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme';
$GLOBALS['can']          = true;
$GLOBALS['cron']         = false;
$GLOBALS['wp_version']   = '7.1';
$GLOBALS['installed']    = '1.0.0'; // Versión del style.css en disco (la constante es la del código cargado).

class WP_Error {
	public $code;
	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
	}
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = array( $callback, $priority, $args );
	return true;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $hook, $callback, $priority, $args );
}
function has_action( $hook ) {
	return ! empty( $GLOBALS['hooks'][ $hook ] );
}
function get_template() {
	return $GLOBALS['template'];
}
class Fake_Theme {
	public function get( $header ) {
		if ( 'Version' === $header ) {
			return $GLOBALS['installed'];
		}
		return 'UpdateURI' === $header ? $GLOBALS['update_uri'] : '';
	}
}
function wp_get_theme() {
	return new Fake_Theme();
}
function get_site_transient( $key ) {
	return $GLOBALS['cache'][ $key ] ?? false;
}
function set_site_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['cache'][ $key ] = $value;
	$GLOBALS['ttl'][ $key ]   = $ttl;
	return true;
}
function delete_site_transient( $key ) {
	foreach ( $GLOBALS['hooks'][ 'delete_site_transient_' . $key ] ?? array() as $hook ) {
		call_user_func( $hook[0], $key );
	}
	unset( $GLOBALS['cache'][ $key ] );
	return true;
}
function get_site_option( $key, $default = false ) {
	return $GLOBALS['site_options'][ $key ] ?? $default;
}
function update_site_option( $key, $value ) {
	$GLOBALS['site_options'][ $key ] = $value;
	return true;
}
function wp_remote_get( $url ) {
	$GLOBALS['calls'][] = $url;
	$kind               = false !== strpos( $url, 'api.github.com' ) ? 'api' : 'manifest';
	return $GLOBALS['responses'][ $kind ] ?? array( 'code' => 404, 'body' => '' );
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}
function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}
function esc_url_raw( $url ) {
	return $url;
}
function is_wp_version_compatible( $required ) {
	return version_compare( $GLOBALS['wp_version'], $required, '>=' );
}
function is_php_version_compatible( $required ) {
	return version_compare( PHP_VERSION, $required, '>=' );
}
function wp_doing_cron() {
	return $GLOBALS['cron'];
}
function current_user_can( $capability ) {
	return 'update_themes' === $capability && $GLOBALS['can'];
}
function admin_url( $path = '' ) {
	return 'https://sitio.test/wp-admin/' . $path;
}
function add_query_arg( array $args, $url ) {
	return $url . '?' . http_build_query( $args );
}
function trailingslashit( $path ) {
	return rtrim( $path, '/\\' ) . '/';
}
function untrailingslashit( $path ) {
	return rtrim( $path, '/\\' );
}

require __DIR__ . '/../digitalisimo-theme/inc/class-updater.php';
$u = 'Digitalisimo_Theme_Updater';

function check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
}
function manifest( $version = '1.0.1', array $entry = array(), $themes = true ) {
	$tag  = 'v2026.10.17.410';
	$base = 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/' . $tag . '/';
	$data = array(
		'tag'         => $tag,
		'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/' . $tag,
		'plugins'     => array( 'digitalisimo-seo' => array( 'version' => '1.0.229', 'file' => 'digitalisimo-seo-1.0.229.zip', 'url' => $base . 'digitalisimo-seo-1.0.229.zip' ) ),
	);
	if ( $themes ) {
		$data['themes'] = array(
			'digitalisimo-theme' => $entry + array(
				'version'      => $version,
				'file'         => 'digitalisimo-theme-' . $version . '.zip',
				'url'          => $base . 'digitalisimo-theme-' . $version . '.zip',
				'requires'     => '6.8',
				'requires_php' => '7.4',
			),
		);
	}
	return array( 'code' => 200, 'body' => json_encode( $data ) );
}
function api( $version = '1.0.2' ) {
	return array(
		'code' => 200,
		'body' => json_encode(
			array(
				array(
					'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v9',
					'assets'   => array(
						array( 'name' => 'digitalisimo-seo-1.0.300.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v9/digitalisimo-seo-1.0.300.zip' ),
						array( 'name' => 'digitalisimo-theme-' . $version . '.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v9/digitalisimo-theme-' . $version . '.zip' ),
					),
				),
			)
		),
	);
}
function reset_state( array $responses ) {
	$GLOBALS['cache']        = array();
	$GLOBALS['ttl']          = array();
	$GLOBALS['site_options'] = array();
	$GLOBALS['calls']        = array();
	$GLOBALS['responses']    = $responses;
}
function updates( $transient = null ) {
	return Digitalisimo_Theme_Updater::check( $transient ?? (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0', 'twentytwentyfive' => '1.3' ) ) );
}
$data = array( 'Name' => 'DIGITALÍSIMO', 'Version' => '1.0.0', 'UpdateURI' => $GLOBALS['update_uri'], 'Template' => 'digitalisimo-theme', 'Stylesheet' => 'digitalisimo-theme' );

// 9 (primero, con los ganchos vacíos). init() registra los filtros en cualquier sitio, también en un subsitio.
$u::init();
foreach ( array( 'update_themes_github.com' => 30, 'pre_set_site_transient_update_themes' => 30, 'site_transient_update_themes' => 30, 'upgrader_source_selection' => 10, 'upgrader_process_complete' => 10, 'load-update-core.php' => 1, 'delete_site_transient_digitalisimo_plugins_manifest' => 10 ) as $hook => $priority ) {
	check( isset( $GLOBALS['hooks'][ $hook ][0] ) && $priority === $GLOBALS['hooks'][ $hook ][0][1], "init() registra {$hook} en {$priority}." );
}
check( 4 === $GLOBALS['hooks']['update_themes_github.com'][0][2] && 4 === $GLOBALS['hooks']['upgrader_source_selection'][0][2], 'Con sus cuatro argumentos.' );
check( false === strpos( file_get_contents( __DIR__ . '/../digitalisimo-theme/inc/class-updater.php' ), 'is_main_site' ), 'Sin condición de sitio principal: wp --url=<subsitio> también rehace el inventario de la red.' );
check( ! isset( $GLOBALS['hooks']['auto_update_theme'] ), 'Nunca filtra auto_update_theme.' );
$GLOBALS['hooks']      = array();
$GLOBALS['update_uri'] = 'https://example.com/otro';
$u::init();
check( array() === $GLOBALS['hooks'], 'Sin el Update URI propio en el tema activo, no registra nada.' );
$GLOBALS['update_uri'] = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme';
$GLOBALS['hooks']      = array();

// 1. Índice con `themes`: entrada array, sin autoupdate; uri_update con `version`.
reset_state( array( 'manifest' => manifest( '1.0.1' ) ) );
$t    = updates();
$item = $t->response['digitalisimo-theme'] ?? null;
check( is_array( $item ), 'La oferta es un array (WordPress la usa así en update_themes).' );
check( array( 'theme', 'new_version', 'url', 'package', 'requires', 'requires_php', 'id' ) === array_keys( $item ), 'Claves de la oferta: ' . implode( ', ', array_keys( $item ) ) );
check( 'digitalisimo-theme' === $item['theme'] && '1.0.1' === $item['new_version'] && '6.8' === $item['requires'] && '7.4' === $item['requires_php'] && $u::URI === $item['id'], 'Versión, requisitos e id de la oferta.' );
check( 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.17.410/digitalisimo-theme-1.0.1.zip' === $item['package'], 'Paquete de la Release.' );
check( 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.17.410' === $item['url'], 'Sin Tools, «Ver detalles» lleva a la Release.' );
check( ! isset( $item['autoupdate'] ) && ! isset( $item['disable_autoupdate'] ), 'Sin autoupdate (lo decide auto_update_themes).' );
check( '1.0.0' === $t->checked['digitalisimo-theme'] && ! isset( $t->no_update['digitalisimo-theme'] ) && ! isset( $t->response['twentytwentyfive'] ), 'Sólo la entrada del tema.' );
check( 1 === count( $GLOBALS['calls'] ) && false !== strpos( $GLOBALS['calls'][0], '/releases/latest/download/digitalisimo-plugins.json' ), 'Sólo se descargó el índice.' );
$offer = $u::uri_update( false, $data, 'digitalisimo-theme', array() );
check( is_array( $offer ) && '1.0.1' === $offer['version'] && '1.0.1' === $offer['new_version'] && ! isset( $offer['autoupdate'] ) && 'digitalisimo-theme' === $offer['theme'], 'uri_update devuelve un array con version.' );
add_action( 'admin_post_digitalisimo_theme_details', 'strlen' );
$item = updates()->response['digitalisimo-theme'];
check( 'https://sitio.test/wp-admin/admin-post.php?action=digitalisimo_theme_details&theme=digitalisimo-theme' === $item['url'], 'Con la ficha de Tools, «Ver detalles» la abre.' );
$GLOBALS['hooks'] = array();
reset_state( array( 'manifest' => manifest( '1.0.0' ) ) );
$t = updates();
check( ! isset( $t->response['digitalisimo-theme'] ) && '1.0.0' === $t->no_update['digitalisimo-theme']['new_version'] && '' === $t->no_update['digitalisimo-theme']['package'], 'Al día: va a no_update sin paquete.' );

// 2. Requisitos incompatibles: la oferta se ve, pero no se instala sola.
reset_state( array( 'manifest' => manifest( '1.0.1', array( 'requires' => '99.0' ) ) ) );
$item = updates()->response['digitalisimo-theme'];
check( '99.0' === $item['requires'] && true === $item['disable_autoupdate'], 'requires 99.0 lleva disable_autoupdate.' );
reset_state( array( 'manifest' => manifest( '1.0.1', array( 'requires_php' => '99.0' ) ) ) );
check( true === updates()->response['digitalisimo-theme']['disable_autoupdate'], 'requires_php 99.0 lleva disable_autoupdate.' );
check( true === $u::uri_update( false, $data, 'digitalisimo-theme', array() )['disable_autoupdate'], 'También en uri_update.' );

// 3. Una respuesta de wordpress.org con el mismo slug se sustituye.
reset_state( array( 'manifest' => manifest( '1.0.1' ) ) );
$foreign = (object) array(
	'checked'   => array( 'digitalisimo-theme' => '1.0.0' ),
	'response'  => array( 'digitalisimo-theme' => array( 'theme' => 'digitalisimo-theme', 'new_version' => '9.9', 'package' => 'https://downloads.wordpress.org/theme/digitalisimo-theme.9.9.zip', 'url' => 'https://wordpress.org/themes/digitalisimo-theme/' ) ),
	'no_update' => array(),
);
$item    = updates( $foreign )->response['digitalisimo-theme'];
check( '1.0.1' === $item['new_version'] && $u::URI === $item['id'] && false === strpos( $item['package'], 'wordpress.org' ), 'La respuesta homónima de wordpress.org se sustituye.' );
reset_state( array( 'manifest' => manifest( '1.0.0' ) ) );
$foreign = (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0' ), 'response' => array( 'digitalisimo-theme' => array( 'new_version' => '9.9' ) ) );
$t       = updates( $foreign );
check( ! isset( $t->response['digitalisimo-theme'] ) && isset( $t->no_update['digitalisimo-theme'] ), 'Y no queda una oferta ajena cuando el tema está al día.' );

// 4. No reciben nada: un hijo, un Update URI ajeno y un $update ya resuelto.
reset_state( array( 'manifest' => manifest( '1.0.1' ) ) );
check( false === $u::uri_update( false, array( 'UpdateURI' => $u::URI, 'Template' => 'digitalisimo-theme' ) + $data, 'digitalisimo-theme-child', array() ), 'Un hijo no recibe oferta.' );
check( false === $u::uri_update( false, array( 'UpdateURI' => 'https://github.com/otro/tema', 'Template' => 'digitalisimo-theme' ) + $data, 'digitalisimo-theme', array() ), 'Un Update URI ajeno no recibe oferta.' );
check( false === $u::uri_update( false, array( 'Template' => 'otro-padre' ) + $data, 'digitalisimo-theme', array() ), 'Sólo como padre (Template igual a la carpeta).' );
$resolved = array( 'version' => '5.0' );
check( $resolved === $u::uri_update( $resolved, $data, 'digitalisimo-theme', array() ), 'Un $update ya resuelto no se toca.' );
$t = updates( (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.0', 'digitalisimo-theme-child' => '1.0.0' ) ) );
check( ! isset( $t->response['digitalisimo-theme-child'] ) && ! isset( $t->no_update['digitalisimo-theme-child'] ), 'check() no escribe nada para el hijo.' );
check( null === $u::check( null ) && 'x' === $u::check( 'x' ), 'Una caché ausente queda en manos de WordPress.' );
$half = (object) array( 'last_checked' => 1 );
check( $half === $u::check( $half ) && ! isset( $half->response ), 'Una caché sin checked tampoco se toca.' );

// 5. Índice válido sin `themes`: sin entrada y sin consultas a la API. Índice caído: API una vez, después LAST_KNOWN 2 min.
reset_state( array( 'manifest' => manifest( '1.0.1', array(), false ), 'api' => api() ) );
$t = updates();
check( ! isset( $t->response['digitalisimo-theme'] ) && ! isset( $t->no_update['digitalisimo-theme'] ), 'Tema sin publicar: sin entrada.' );
check( 1 === count( $GLOBALS['calls'] ) && 0 === count( preg_grep( '/api\.github\.com/', $GLOBALS['calls'] ) ), 'Tema sin publicar: ninguna consulta a api.github.com.' );
check( is_array( $GLOBALS['cache'][ $u::MANIFEST_CACHE ] ) && 600 === $GLOBALS['ttl'][ $u::MANIFEST_CACHE ], 'El índice válido se comparte 10 min.' );
reset_state( array( 'manifest' => array( 'code' => 404, 'body' => '' ), 'api' => api( '1.0.2' ) ) );
$item = updates()->response['digitalisimo-theme'] ?? null;
check( $item && '1.0.2' === $item['new_version'] && '6.8' === $item['requires'] && '7.4' === $item['requires_php'], 'Con el índice caído, la API ofrece la versión con los requisitos por defecto.' );
check( 1 === count( preg_grep( '/api\.github\.com/', $GLOBALS['calls'] ) ), 'La API se consultó una vez.' );
check( ! isset( $GLOBALS['cache'][ $u::MANIFEST_CACHE ] ), 'Un índice caído no se guarda.' );
updates();
check( 1 === count( preg_grep( '/api\.github\.com/', $GLOBALS['calls'] ) ), 'La siguiente consulta usa la caché propia.' );
$u::clear_own();
$GLOBALS['responses'] = array( 'manifest' => array( 'code' => 500, 'body' => '' ), 'api' => array( 'code' => 403, 'body' => '{"message":"API rate limit exceeded"}' ) );
$item                 = updates()->response['digitalisimo-theme'] ?? null;
check( $item && '1.0.2' === $item['new_version'] && 120 === $GLOBALS['ttl'][ $u::CACHE_KEY ], 'GitHub limitado: la última versión conocida, reintentando en 2 min.' );
reset_state( array( 'manifest' => array( 'code' => 200, 'body' => '{"plugins":[]}' ), 'api' => api( '1.0.3' ) ) );
check( '1.0.3' === ( updates()->response['digitalisimo-theme']['new_version'] ?? '' ), 'Un índice sin plugins no es válido: se usa la API.' );

// 6. Paquetes ajenos o mal formados.
$bad = array(
	'https://example.com/digitalisimo-theme-1.0.1.zip',
	'https://github.com/otro/repo/releases/download/v1/digitalisimo-theme-1.0.1.zip',
	'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-1.0.1.zip',
	'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-theme-1.0.2.zip',
	'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-theme-1.0.1.zip?x=1',
);
foreach ( $bad as $url ) {
	reset_state( array( 'manifest' => manifest( '1.0.1', array( 'url' => $url ) ), 'api' => api() ) );
	$t = updates();
	check( ! isset( $t->response['digitalisimo-theme'] ) && 1 === count( $GLOBALS['calls'] ), 'Paquete rechazado sin consultar la API: ' . $url );
}
reset_state( array( 'manifest' => manifest( '1.0.1', array( 'version' => '1.0.1-beta' ) ) ) );
check( ! isset( updates()->response['digitalisimo-theme'] ), 'Una versión mal formada se rechaza.' );
reset_state( array( 'manifest' => manifest( '1.0.1', array( 'requires' => 'seis', 'requires_php' => array( '8' ) ) ) ) );
$item = updates()->response['digitalisimo-theme'];
check( '6.8' === $item['requires'] && '7.4' === $item['requires_php'], 'Requisitos mal formados: 6.8 y 7.4.' );
$tampered                = json_decode( manifest( '1.0.1' )['body'], true );
$tampered['release_url'] = 'https://example.com/release';
reset_state( array( 'manifest' => array( 'code' => 200, 'body' => json_encode( $tampered ) ) ) );
check( 'https://github.com/danbutanda/digitalisimo-plugin/releases' === updates()->response['digitalisimo-theme']['url'], 'Una release_url ajena se sustituye por la raíz de las Releases.' );
$api_bad = json_decode( api( '1.0.5' )['body'], true );
$api_bad[0]['assets'][1]['browser_download_url'] = 'https://example.com/digitalisimo-theme-1.0.5.zip';
reset_state( array( 'manifest' => array( 'code' => 404, 'body' => '' ), 'api' => array( 'code' => 200, 'body' => json_encode( $api_bad ) ) ) );
check( ! isset( updates()->response['digitalisimo-theme'] ), 'La API tampoco acepta un ZIP fuera del repositorio.' );

// 7. keep_folder: conserva la carpeta real, valida el paquete y se detiene antes de tocar nada.
$tmp = sys_get_temp_dir() . '/digitalisimo-theme-updater-' . getmypid();
define( 'WP_CONTENT_DIR', $tmp . '/wp-content' );
function make_package( $dir, $header ) {
	@mkdir( $dir, 0777, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	file_put_contents( $dir . '/style.css', "/*\n * Theme Name: DIGITALÍSIMO\n * Version: 1.0.1\n" . $header . " */\n" );
	return $dir;
}
class Fake_Filesystem {
	public $moves  = array();
	public $fail   = false;
	public $remote = array();
	public $ok     = array();
	public function wp_content_dir() {
		return WP_CONTENT_DIR . '/';
	}
	public function move( $from, $to, $overwrite = false ) {
		$this->moves[] = array( $from, $to, $overwrite );
		return ! $this->fail && ( isset( $this->remote[ rtrim( $from, '/' ) ] ) || in_array( rtrim( $from, '/' ), $this->ok, true ) || rename( $from, $to ) );
	}
	public function get_contents( $file ) {
		return $this->remote[ dirname( $file ) ] ?? false;
	}
}
$good_header       = " * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme\n";
$GLOBALS['wp_filesystem'] = new Fake_Filesystem();
$GLOBALS['template']      = 'digitalisimo-theme-1.0.0';
foreach ( array( array( 'theme' => 'digitalisimo-theme-1.0.0', 'type' => 'theme' ), array( 'theme' => 'digitalisimo-theme-1.0.0' ) ) as $n => $extra ) {
	$source = make_package( WP_CONTENT_DIR . '/upgrade/pkg' . $n . '/digitalisimo-theme', $good_header ) . '/';
	$result = $u::keep_folder( $source, dirname( $source ), null, $extra );
	check( WP_CONTENT_DIR . '/upgrade/pkg' . $n . '/digitalisimo-theme-1.0.0/' === $result && is_file( $result . 'style.css' ) && ! is_dir( $source ), 'La versión nueva queda en la carpeta real (' . implode( ',', array_keys( $extra ) ) . ').' );
}
$source = make_package( WP_CONTENT_DIR . '/upgrade/pkg2/digitalisimo-theme', " * Update URI: https://github.com/otro/tema\n" ) . '/';
check( is_wp_error( $u::keep_folder( $source, '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) ) ) && is_dir( $source ), 'Otro Update URI: WP_Error sin mover nada.' );
$source = make_package( WP_CONTENT_DIR . '/upgrade/pkg3/digitalisimo-theme', $good_header . " * Template: digitalisimo-theme\n" ) . '/';
check( is_wp_error( $u::keep_folder( $source, '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) ) ), 'Un paquete hijo («Template:»): WP_Error.' );
$source = make_package( WP_CONTENT_DIR . '/upgrade/pkg4/otro', $good_header ) . '/';
check( $source === $u::keep_folder( $source, '', null, array( 'theme' => 'twentytwentyfive' ) ) && is_dir( $source ), 'Otro tema: no actúa.' );
check( $source === $u::keep_folder( $source, '', null, array( 'plugin' => 'x/x.php' ) ) && $source === $u::keep_folder( $source, '', null, 'nada' ), 'Un plugin o un hook_extra inválido: no actúa.' );
$error = new WP_Error( 'x' );
check( $error === $u::keep_folder( $error, '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) ), 'Un error previo pasa tal cual.' );
$GLOBALS['wp_filesystem']->fail = true;
$source = make_package( WP_CONTENT_DIR . '/upgrade/pkg5/digitalisimo-theme', $good_header ) . '/';
$result = $u::keep_folder( $source, '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) );
check( is_wp_error( $result ) && 'digitalisimo_theme_folder' === $result->code && is_dir( $source ), 'Si move() falla, WP_Error antes de que WordPress vacíe ninguna carpeta (RL-1).' );
$GLOBALS['wp_filesystem']->fail = false;
$GLOBALS['wp_filesystem']->moves = array();
$remote = '/ftp/root/wp-content/upgrade/pkg6/digitalisimo-theme';
$GLOBALS['wp_filesystem']->remote[ $remote ] = "/*\n * Theme Name: DIGITALÍSIMO\n" . $good_header . " */\n";
$result = $u::keep_folder( $remote . '/', '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) );
check( '/ftp/root/wp-content/upgrade/pkg6/digitalisimo-theme-1.0.0/' === $result && 1 === count( $GLOBALS['wp_filesystem']->moves ), 'Sin copia local, la cabecera se lee con get_contents() (RL-7).' );
$GLOBALS['wp_filesystem']->remote[ $remote ] = "/*\n * Theme Name: Otro\n * Update URI: https://github.com/otro/tema\n */\n";
check( is_wp_error( $u::keep_folder( $remote . '/', '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) ) ), 'Y también se valida.' );
$unreadable = '/ftp/root/wp-content/upgrade/pkg7/digitalisimo-theme';
$GLOBALS['wp_filesystem']->ok[] = $unreadable; // Aunque se pudiera mover, sin cabecera no se mueve.
check( is_wp_error( $u::keep_folder( $unreadable . '/', '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) ) ), 'Sin cabecera legible y con otra carpeta: WP_Error (no se arriesga a vaciar otra copia).' );
$GLOBALS['template'] = 'digitalisimo-theme';
check( $unreadable . '/' === $u::keep_folder( $unreadable . '/', '', null, array( 'theme' => 'digitalisimo-theme' ) ), 'Sin cabecera legible y con la carpeta en su nombre: se deja pasar, como check_package.' );
$source = make_package( WP_CONTENT_DIR . '/upgrade/pkg8/digitalisimo-theme', $good_header );
check( $source . '/' === $u::keep_folder( $source, '', null, array( 'theme' => 'digitalisimo-theme' ) ), 'Con la carpeta habitual no mueve nada.' );
$cleanup = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $cleanup as $entry ) {
	$entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
}
rmdir( $tmp );

// 8. Cachés.
function primed() {
	$GLOBALS['cache'] = array( Digitalisimo_Theme_Updater::CACHE_KEY => array( 'version' => '1.0.1' ), Digitalisimo_Theme_Updater::MANIFEST_CACHE => array( 'plugins' => array( 'x' => 1 ) ) );
}
function cleared() {
	return ! isset( $GLOBALS['cache'][ Digitalisimo_Theme_Updater::CACHE_KEY ] ) && ! isset( $GLOBALS['cache'][ Digitalisimo_Theme_Updater::MANIFEST_CACHE ] );
}
primed();
$u::clear_after_upgrade( null, array( 'action' => 'update', 'type' => 'theme', 'theme' => 'digitalisimo-theme' ) );
check( cleared(), 'Tras actualizar el tema (upgrade) se vacían las cachés.' );
primed();
$u::clear_after_upgrade( null, array( 'action' => 'update', 'type' => 'theme', 'bulk' => true, 'themes' => array( 'otro', 'digitalisimo-theme' ) ) );
check( cleared(), 'Tras bulk_upgrade también.' );
primed();
$u::clear_after_upgrade( null, array( 'action' => 'update', 'type' => 'theme', 'themes' => array( 'otro' ) ) );
$u::clear_after_upgrade( null, array( 'action' => 'install', 'type' => 'theme', 'theme' => 'digitalisimo-theme' ) );
check( ! cleared(), 'Otro tema o una instalación no vacían nada.' );
$_GET['force-check'] = '1';
$u::native_force_check();
check( cleared(), '«Buscar actualizaciones de nuevo» vacía la caché y el índice.' );
unset( $_GET['force-check'] );
primed();
$u::native_force_check();
check( ! cleared(), 'Sin force-check no se toca nada.' );
$u::clear_own();
check( ! isset( $GLOBALS['cache'][ $u::CACHE_KEY ] ) && isset( $GLOBALS['cache'][ $u::MANIFEST_CACHE ] ), 'clear_own() no borra el índice compartido.' );
$GLOBALS['hooks'] = array();
$u::init();
primed();
delete_site_transient( $u::MANIFEST_CACHE );
check( cleared(), 'Cuando otro plugin borra el índice, el tema olvida su versión en caché.' );

// 8b. En la petición que actualiza el propio tema la constante sigue con la versión anterior (1.0.0) y el style.css ya
// es el nuevo: la caché que se escribe después no puede volver a ofrecer la versión recién instalada.
reset_state( array( 'manifest' => manifest( '1.0.1' ) ) );
$GLOBALS['installed'] = '1.0.1';
$t                    = updates( (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.1' ) ) );
check( '1.0.0' === DIGITALISIMO_THEME_VERSION && ! isset( $t->response['digitalisimo-theme'] ) && '1.0.1' === $t->no_update['digitalisimo-theme']['new_version'] && '1.0.1' === $t->checked['digitalisimo-theme'], 'Tras actualizar el tema, la versión instalada (style.css) manda sobre la constante: al día, en no_update.' );
$u::clear_own();
$GLOBALS['responses'] = array( 'manifest' => manifest( '1.0.2' ) );
delete_site_transient( $u::MANIFEST_CACHE );
$t = updates( (object) array( 'checked' => array( 'digitalisimo-theme' => '1.0.1' ) ) );
check( '1.0.2' === $t->response['digitalisimo-theme']['new_version'] && '1.0.1' === $t->checked['digitalisimo-theme'], 'Y una versión más nueva sí se ofrece, comparada con la instalada.' );
$GLOBALS['installed'] = '';
reset_state( array( 'manifest' => manifest( '1.0.0' ) ) );
check( isset( updates()->no_update['digitalisimo-theme'] ) && '1.0.0' === updates()->checked['digitalisimo-theme'], 'Sin versión legible en style.css, la de la constante.' );
$GLOBALS['installed'] = '1.0.0';

// 9. Sin update_themes y fuera de cron, check() no consulta la red.
reset_state( array( 'manifest' => manifest( '1.0.1' ) ) );
$GLOBALS['can'] = false;
$t              = updates();
check( array() === $GLOBALS['calls'] && ! isset( $t->response ), 'Un visitante sin update_themes no dispara consultas.' );
$GLOBALS['cron'] = true;
check( isset( updates()->response['digitalisimo-theme'] ) && 1 === count( $GLOBALS['calls'] ), 'En cron sí.' );

echo "Tema DIGITALÍSIMO: actualizador validado (ofertas, requisitos, índice, API de respaldo, paquete, carpeta y cachés).\n";
