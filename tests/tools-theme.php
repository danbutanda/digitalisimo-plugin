<?php
/**
 * DIGITALÍSIMO Tools · tema DIGITALÍSIMO: cobertura de actualizaciones de las copias que el actualizador del tema no
 * atiende (entradas array sin `autoupdate`, reescritura de las no cargadas, respaldo de la cargada), carpeta conservada
 * al actualizar, sección «Tema» del catálogo en sitio individual, red y subsitio, handlers con nonce por copia y
 * contexto de red, actualización con bulk_upgrade(), ficha de «Ver detalles» e inventario «Antes de cambiar de tema»
 * de sólo lectura. Sin WordPress: cada función del núcleo que se usa es un stub de esta prueba.
 */
$tmp = rtrim( sys_get_temp_dir(), '/' ) . '/digitalisimo-tools-theme-' . getmypid();
register_shutdown_function( static function () use ( $tmp ) {
	if ( ! is_dir( $tmp ) ) return;
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $entry ) $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
	rmdir( $tmp );
} );
// ABSPATH falso con los archivos que incluyen los handlers (vacíos: las clases del upgrader son stubs de esta prueba).
foreach ( array( 'wp-admin/includes/class-wp-upgrader.php', 'wp-admin/includes/file.php', 'wp-admin/includes/theme.php', 'wp-includes/update.php' ) as $stub_file ) {
	@mkdir( dirname( "$tmp/$stub_file" ), 0777, true );
	file_put_contents( "$tmp/$stub_file", "<?php\n" );
}
define( 'ABSPATH', $tmp . '/' );
define( 'WP_CONTENT_DIR', $tmp . '/wp-content' );
define( 'WP_PLUGIN_DIR', $tmp . '/wp-content/plugins' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MB_IN_BYTES', 1048576 );
@mkdir( WP_CONTENT_DIR . '/themes', 0777, true );

final class Died extends Exception {}
final class Redirected extends Exception {}
final class Done extends Exception {}
class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

/* Ganchos: registro con prioridad, do_action y apply_filters reales. */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = array( $callback, $priority, $args ); return true; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
function has_action( $hook ) { return ! empty( $GLOBALS['hooks'][ $hook ] ); }
function hooked( $hook ) { $list = $GLOBALS['hooks'][ $hook ] ?? array(); usort( $list, function ( $a, $b ) { return $a[1] <=> $b[1]; } ); return $list; }
function apply_filters( $hook, $value, ...$rest ) { foreach ( hooked( $hook ) as $h ) $value = call_user_func_array( $h[0], array_slice( array_merge( array( $value ), $rest ), 0, $h[2] ) ); return $value; }
function do_action( $hook, ...$args ) { $GLOBALS['fired'][] = array( $hook, $args ); foreach ( hooked( $hook ) as $h ) call_user_func_array( $h[0], array_slice( $args, 0, $h[2] ) ); }

/* Contexto: sitio individual, red o subsitio. */
function is_multisite() { return ! empty( $GLOBALS['ctx']['multisite'] ); }
function is_network_admin() { return ! empty( $GLOBALS['ctx']['network'] ); }
function get_current_blog_id() { return $GLOBALS['ctx']['blog'] ?? 1; }
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'], true ); }
function wp_doing_cron() { return ! empty( $GLOBALS['cron'] ); }
function admin_url( $path = '' ) { return ( is_multisite() && ! is_network_admin() ? 'https://sub.red.test' : 'https://sitio.test' ) . '/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://sitio.test/wp-admin/network/' . $path; }
function self_admin_url( $path = '' ) { return is_network_admin() ? network_admin_url( $path ) : admin_url( $path ); }
function get_template() { return $GLOBALS['template']; }
function get_stylesheet() { return $GLOBALS['stylesheet']; }
function is_wp_version_compatible( $required ) { return version_compare( '7.1', (string) $required, '>=' ); }
function is_php_version_compatible( $required ) { return version_compare( PHP_VERSION, (string) $required, '>=' ); }

/* Opciones, transitorios de red (con sus filtros, como WordPress) y escrituras registradas. */
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['writes'][] = 'option:' . $key; $GLOBALS['options'][ $key ] = $value; return true; }
function add_option( $key, $value = '' ) { $GLOBALS['writes'][] = 'add_option:' . $key; return true; }
function delete_option( $key ) { $GLOBALS['writes'][] = 'delete_option:' . $key; return true; }
function get_site_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['site_options'] ) ? $GLOBALS['site_options'][ $key ] : $default; }
function update_site_option( $key, $value ) { $GLOBALS['writes'][] = 'site_option:' . $key; $GLOBALS['site_options'][ $key ] = $value; return true; }
function get_site_transient( $key ) { return apply_filters( 'site_transient_' . $key, $GLOBALS['cache'][ $key ] ?? false, $key ); }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['writes'][] = 'transient:' . $key; $GLOBALS['ttl'][ $key ] = $ttl; $GLOBALS['cache'][ $key ] = apply_filters( 'pre_set_site_transient_' . $key, $value, $ttl, $key ); return true; }
function delete_site_transient( $key ) { do_action( 'delete_site_transient_' . $key, $key ); unset( $GLOBALS['cache'][ $key ] ); return true; }
function set_theme_mod( $key, $value ) { $GLOBALS['writes'][] = 'mod:' . $key; }

/* Red: índice de la Release y API de GitHub. */
function wp_remote_get( $url ) {
	$GLOBALS['calls'][] = $url;
	return $GLOBALS['responses'][ false !== strpos( $url, 'api.github.com' ) ? 'api' : 'manifest' ] ?? array( 'code' => 404, 'body' => '' );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function api_calls() { return count( array_filter( $GLOBALS['calls'], function ( $url ) { return false !== strpos( $url, 'api.github.com' ); } ) ); }

/* Formato, escape y URLs. */
function esc_url_raw( $url ) { return $url; }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function trailingslashit( $path ) { return rtrim( (string) $path, '/\\' ) . '/'; }
function untrailingslashit( $path ) { return rtrim( (string) $path, '/\\' ); }
function wp_normalize_path( $path ) { return preg_replace( '#/+#', '/', str_replace( '\\', '/', (string) $path ) ); }
function number_format_i18n( $number ) { return number_format( (float) $number ); }
function wp_date( $format, $time ) { return gmdate( $format, $time ); }
function add_query_arg( $args, $url = '', $third = null ) {
	if ( ! is_array( $args ) ) { $args = array( $args => $url ); $url = (string) $third; }
	$fragment = '';
	if ( false !== ( $hash = strpos( $url, '#' ) ) ) { $fragment = substr( $url, $hash ); $url = substr( $url, 0, $hash ); }
	$pairs = array(); foreach ( $args as $key => $value ) $pairs[] = $key . '=' . $value;
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs ) . $fragment;
}
function nonce( $action ) { return 'n' . substr( md5( 'nonce|' . $action ), 0, 10 ); }
function wp_nonce_url( $url, $action = -1 ) { return add_query_arg( '_wpnonce', nonce( $action ), $url ); }
function check_admin_referer( $action ) { if ( ( $_REQUEST['_wpnonce'] ?? '' ) !== nonce( $action ) ) throw new Died( 'nonce' ); return 1; }
function wp_die( $message = '' ) { throw new Died( (string) $message ); }
function wp_safe_redirect( $url ) { throw new Redirected( (string) $url ); }
function wp_get_referer() { return $GLOBALS['referer'] ?? false; }
function add_thickbox() { $GLOBALS['log'][] = 'thickbox'; }

/* Pantallas, diseño compartido e iframe de «Ver detalles». */
function set_current_screen( $id ) { $GLOBALS['screen'] = $id; $GLOBALS['log'][] = 'screen:' . $id; }
function digitalisimo_ui_register() { $GLOBALS['log'][] = 'ui-register'; }
function wp_enqueue_style( $handle ) { $GLOBALS['log'][] = 'style:' . $handle; }
function iframe_header( $title = '' ) {
	$GLOBALS['log'][] = 'iframe_header';
	if ( empty( $GLOBALS['screen'] ) || empty( $GLOBALS['hook_suffix'] ) ) throw new RuntimeException( 'iframe_header() sin pantalla ni $hook_suffix: avisos de PHP.' );
	echo '<!doctype html><html><head><title>' . $title . '</title></head><body class="wp-admin iframe ' . esc_attr( apply_filters( 'admin_body_class', '' ) ) . '">';
}
function iframe_footer() { echo '</body></html>'; $GLOBALS['log'][] = 'iframe_footer'; throw new Done(); }

/* Temas. */
class WP_Theme {
	public $stylesheet; public $headers; public $parent_theme; public $dir; public $templates; public $found;
	public function __construct( $stylesheet, array $headers = array(), $parent = null, $dir = '', array $templates = array(), $found = true ) {
		$this->stylesheet = $stylesheet; $this->headers = $headers; $this->parent_theme = $parent; $this->dir = $dir; $this->templates = $templates; $this->found = $found;
	}
	public function get( $key ) { return $this->headers[ $key ] ?? ''; }
	public function get_stylesheet() { return $this->stylesheet; }
	public function get_template() { return '' !== ( $this->headers['Template'] ?? '' ) ? $this->headers['Template'] : $this->stylesheet; }
	public function exists() { return $this->found; }
	public function parent() { return $this->parent_theme ? $this->parent_theme : false; }
	public function get_stylesheet_directory() { return $this->dir; }
	public function is_allowed( $check = 'both', $blog_id = null ) {
		if ( ! is_multisite() ) return true;
		$network = in_array( $this->stylesheet, $GLOBALS['allowed_network'], true );
		$site    = in_array( $this->stylesheet, $GLOBALS['allowed_site'][ (int) $blog_id ] ?? array(), true );
		return 'network' === $check ? $network : ( 'site' === $check ? $site : ( $network || $site ) );
	}
	public function get_files( $type = null, $depth = 0 ) {
		$files = array();
		if ( '' === $this->dir || ! is_dir( $this->dir ) ) return $files;
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) if ( 'php' === $file->getExtension() ) $files[ ltrim( substr( wp_normalize_path( $file->getPathname() ), strlen( wp_normalize_path( $this->dir ) ) ), '/' ) ] = $file->getPathname();
		return $files;
	}
	// Como WordPress: las plantillas de un hijo incluyen las de su padre.
	public function get_post_templates() {
		$templates = $this->templates;
		if ( $this->parent_theme ) foreach ( $this->parent_theme->get_post_templates() as $type => $list ) $templates[ $type ] = ( $templates[ $type ] ?? array() ) + $list;
		return $templates;
	}
}
function wp_get_themes() { return $GLOBALS['themes']; }
function wp_get_theme( $stylesheet = null ) { $stylesheet = null === $stylesheet ? get_stylesheet() : $stylesheet; return $GLOBALS['themes'][ $stylesheet ] ?? $GLOBALS['extra_themes'][ $stylesheet ] ?? new WP_Theme( (string) $stylesheet, array(), null, '', array(), false ); }
function get_theme_root() { return WP_CONTENT_DIR . '/themes'; }
function get_filesystem_method() { return $GLOBALS['fs']; }
function wp_update_themes() { $GLOBALS['log'][] = 'wp_update_themes'; }
function delete_theme( $stylesheet ) { $GLOBALS['log'][] = 'delete_theme'; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ] ?? false; }
function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function get_file_data() { return array( 'Version' => '' ); }
function add_menu_page() {} function add_submenu_page() {} function remove_submenu_page() {}

/* Upgrader: registra cada llamada. */
class Automatic_Upgrader_Skin {}
class Theme_Upgrader {
	public static $calls = array(); public static $bulk = array(); public static $install = true; public static $info = null;
	public function __construct( $skin = null ) {}
	public function install( $package, $args = array() ) { self::$calls[] = array( 'install', $package, $args ); return self::$install; }
	public function upgrade( $theme, $args = array() ) { self::$calls[] = array( 'upgrade', $theme ); return true; }
	public function bulk_upgrade( $themes, $args = array() ) { self::$calls[] = array( 'bulk_upgrade', $themes ); return self::$bulk; }
	public function theme_info() { return self::$info; }
}
/** Contenidos con una plantilla de página: sólo responde a la consulta de sólo lectura del inventario. */
function get_posts( array $args ) {
	$GLOBALS['log'][] = 'get_posts';
	if ( 'any' !== ( $args['post_status'] ?? '' ) || 'ids' !== ( $args['fields'] ?? '' ) || '_wp_page_template' !== ( $args['meta_key'] ?? '' ) ) throw new RuntimeException( 'Consulta de plantillas inesperada.' );
	$count = $GLOBALS['template_uses'][ $args['meta_value'] ?? '' ] ?? 0;
	return $count ? range( 1, $count ) : array();
}
/** Sistema de archivos de WordPress (directo o, con `remote`, una ruta FTP que no existe en local). */
final class Filesystem_Stub {
	public $fail = false; public $remote = ''; public $moves = array(); public $reads = array(); public $contents = null;
	public function wp_content_dir() { return '' !== $this->remote ? $this->remote : trailingslashit( WP_CONTENT_DIR ); }
	public function move( $from, $to, $overwrite = false ) { $this->moves[] = array( $from, $to ); if ( $this->fail ) return false; return '' !== $this->remote ? true : rename( $from, $to ); }
	public function get_contents( $file ) { $this->reads[] = $file; return null !== $this->contents ? $this->contents : ( is_readable( $file ) ? file_get_contents( $file ) : false ); }
	public function exists( $file ) { return file_exists( $file ); }
}

require __DIR__ . '/../digitalisimo-tools/includes/class-theme-updates.php';
require __DIR__ . '/../digitalisimo-tools/includes/class-theme-catalog.php';
require __DIR__ . '/../digitalisimo-tools/includes/class-plugin-catalog.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-plugin-catalog.php';
use Digitalisimo\Tools\Theme_Updates as U;
use Digitalisimo\Tools\Theme_Catalog as C;

function ok( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
const URI = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme';
function theme_zip( $version, $tag = 'v2026.10.17.500' ) { return 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/' . $tag . '/digitalisimo-theme-' . $version . '.zip'; }
function manifest( $theme = '1.0.1', array $extra = array() ) {
	$plugins = array();
	foreach ( array( 'digitalisimo-seo' => '1.0.229', 'digitalisimo-ia-tools' => '1.0.58', 'digitalisimo-hosting' => '1.0.29', 'digitalisimo-backups' => '1.0.57', 'digitalisimo-tools' => '1.0.34', 'digitalisimo-elements' => '4.3.0.158', 'digitalisimo-migration' => '1.0.20' ) as $slug => $version ) $plugins[ $slug ] = array( 'version' => $version, 'file' => "$slug-$version.zip", 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.17.500/' . $slug . '-' . $version . '.zip' );
	$body = array( 'tag' => 'v2026.10.17.500', 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.17.500', 'generated' => '2026-10-17T12:00:00.000Z', 'plugins' => $plugins );
	if ( null !== $theme ) $body['themes'] = array( 'digitalisimo-theme' => array_merge( array( 'version' => $theme, 'file' => "digitalisimo-theme-$theme.zip", 'url' => theme_zip( $theme ), 'requires' => '6.8', 'requires_php' => '7.4' ), $extra ) );
	return array( 'code' => 200, 'body' => json_encode( $body ) );
}
function own_theme( $stylesheet, $version = '1.0.0', array $headers = array() ) { return new WP_Theme( $stylesheet, array_merge( array( 'Name' => 'DIGITALÍSIMO', 'Version' => $version, 'UpdateURI' => URI ), $headers ) ); }
/** Estado limpio: contexto, temas instalados, capacidades e índice; registra los ganchos de Tools. */
function scenario( array $ctx = array(), array $caps = array( 'update_themes', 'install_themes', 'switch_themes' ), $response = null ) {
	$GLOBALS['ctx'] = $ctx; $GLOBALS['caps'] = $caps; $GLOBALS['hooks'] = array(); $GLOBALS['fired'] = array(); $GLOBALS['log'] = array();
	$GLOBALS['cache'] = array(); $GLOBALS['ttl'] = array(); $GLOBALS['calls'] = array(); $GLOBALS['writes'] = array(); $GLOBALS['site_options'] = array(); $GLOBALS['options'] = array();
	$GLOBALS['responses'] = array( 'manifest' => $response ?? manifest() ); $GLOBALS['cron'] = false; $GLOBALS['fs'] = 'direct'; $GLOBALS['referer'] = false;
	$GLOBALS['template'] = 'twentytwentyfive'; $GLOBALS['stylesheet'] = 'twentytwentyfive'; $GLOBALS['allowed_network'] = array(); $GLOBALS['allowed_site'] = array();
	$GLOBALS['themes'] = array(
		'digitalisimo-theme'       => own_theme( 'digitalisimo-theme' ),
		'digitalisimo-theme-1.0.0' => own_theme( 'digitalisimo-theme-1.0.0' ),
		// Un hijo con el mismo «Update URI» y otro tema Digitalisimo no son copias del tema.
		'digitalisimo-theme-child' => own_theme( 'digitalisimo-theme-child', '1.0.0', array( 'Template' => 'digitalisimo-theme' ) ),
		'otro'                     => new WP_Theme( 'otro', array( 'Name' => 'Otro', 'Version' => '2.0', 'UpdateURI' => 'https://github.com/danbutanda/digitalisimo-plugin/otro' ) ),
		'twentytwentyfive'         => new WP_Theme( 'twentytwentyfive', array( 'Name' => 'Twenty Twenty-Five', 'Version' => '1.3' ) ),
	);
	$GLOBALS['extra_themes'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['template_uses'] = array(); $GLOBALS['wp_filter'] = array();
	$_GET = array(); $_REQUEST = array(); $GLOBALS['screen'] = null; unset( $GLOBALS['hook_suffix'] );
	Theme_Upgrader::$calls = array(); Theme_Upgrader::$bulk = array(); Theme_Upgrader::$install = true; Theme_Upgrader::$info = null;
	U::forget(); U::init(); C::init();
}
function transient( array $response = array(), array $no_update = array() ) {
	$checked = array(); foreach ( $GLOBALS['themes'] as $stylesheet => $theme ) $checked[ $stylesheet ] = $theme->get( 'Version' );
	return (object) array( 'last_checked' => time(), 'checked' => $checked, 'response' => $response, 'no_update' => $no_update );
}
function render( $context ) { ob_start(); do_action( 'digitalisimo_plugin_catalog_after', $context ); return (string) ob_get_clean(); }
function links( $html ) {
	preg_match_all( '#<a\s([^>]*)href="([^"]*)"([^>]*)>(.*?)</a>#s', $html, $m, PREG_SET_ORDER );
	$out = array(); foreach ( $m as $link ) { $url = html_entity_decode( $link[2], ENT_QUOTES ); parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query ); $out[] = array( 'text' => trim( strip_tags( $link[4] ) ), 'url' => $url, 'query' => $query, 'attrs' => $link[1] . $link[3] ); }
	return $out;
}
function find_links( $html, $text ) { return array_values( array_filter( links( $html ), function ( $link ) use ( $text ) { return $link['text'] === $text; } ) ); }
/** Simula el clic en un enlace del catálogo: su consulta pasa a $_GET y $_REQUEST. */
function click( array $query ) { $_GET = $query; $_REQUEST = $query; }
function expect( $callback, $exception ) { try { $callback(); } catch ( Exception $e ) { if ( $e instanceof $exception ) return $e; throw $e; } throw new RuntimeException( 'Se esperaba ' . $exception . '.' ); }
function theme_table( $html ) { return preg_match( '#<table class="widefat striped digitalisimo-theme-catalog">.*?</table>#s', $html, $m ) ? $m[0] : ''; }
function rows( $html ) { return substr_count( theme_table( $html ), '<tr><td>' ); }
/** Texto que un usuario ve fuera de <code>: la auditoría de marca no permite el nombre del constructor original. */
function visible( $html ) { return html_entity_decode( strip_tags( preg_replace( '#<code>.*?</code>#s', '', $html ) ), ENT_QUOTES ); }

/* ================================================================================================================
 * 0. Arranque: Tools carga, inicia y registra cada gancho en su prioridad.
 * ============================================================================================================== */
$main = file_get_contents( __DIR__ . '/../digitalisimo-tools/digitalisimo-tools.php' );
ok( false !== strpos( $main, "'includes/class-theme-updates.php'" ) && false !== strpos( $main, "'includes/class-theme-catalog.php'" ), 'digitalisimo-tools.php debe requerir las dos clases del tema.' );
$boot = file_get_contents( __DIR__ . '/../digitalisimo-tools/includes/class-plugin.php' );
ok( (bool) preg_match( '/function boot\(\)\s*\{[^}]*Theme_Updates::init\(\);[^}]*Theme_Catalog::init\(\);/', $boot ), 'Plugin::boot() debe iniciar Theme_Updates y Theme_Catalog.' );
scenario();
$priority = function ( $hook, $method ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $h ) if ( is_array( $h[0] ) && $method === $h[0][1] ) return array( $h[1], $h[2] ); return null; };
ok( array( 31, 4 ) === $priority( 'update_themes_github.com', 'uri_update' ), 'update_themes_github.com en 31 con 4 argumentos (después del tema, en 30).' );
ok( array( 31, 1 ) === $priority( 'pre_set_site_transient_update_themes', 'check' ) && array( 31, 1 ) === $priority( 'site_transient_update_themes', 'check' ), 'check() en pre_set y en la lectura de update_themes, en 31.' );
ok( array( 11, 4 ) === $priority( 'upgrader_source_selection', 'keep_folder' ), 'keep_folder en 11, detrás del filtro del tema (10).' );
ok( null !== $priority( 'upgrader_process_complete', 'clear_after_upgrade' ) && null !== $priority( 'delete_site_transient_digitalisimo_plugins_manifest', 'clear_own' ), 'Limpieza tras actualizar y al borrar el índice compartido.' );
ok( null !== $priority( 'switch_theme', 'forget' ) && null !== $priority( 'deleted_theme', 'forget' ), 'Las copias se vuelven a leer al cambiar o borrar un tema.' );
ok( null !== $priority( 'admin_post_digitalisimo_theme_details', 'details' ) && null !== $priority( 'admin_post_digitalisimo_tools_theme_check', 'force_check' ), 'Handlers de la ficha y de «Buscar actualizaciones».' );
ok( null !== $priority( 'admin_post_digitalisimo_tools_theme_install', 'install' ) && null !== $priority( 'admin_post_digitalisimo_tools_theme_update', 'update' ) && null !== $priority( 'digitalisimo_plugin_catalog_after', 'render' ), 'Handlers de instalar y actualizar y la sección del catálogo.' );
ok( null !== $priority( 'admin_notices', 'notices' ) && null !== $priority( 'network_admin_notices', 'notices' ), 'Avisos en sitio y red.' );
ok( null === $priority( 'theme_action_links', 'theme_action_links' ), 'Sin red no hay filas de Temas de la red que enlazar.' );
// En un subsitio se registran igual (un `wp --url=<subsitio>` rehace el inventario de toda la red).
scenario( array( 'multisite' => true, 'blog' => 3 ) );
ok( array( 31, 1 ) === $priority( 'pre_set_site_transient_update_themes', 'check' ) && array( 31, 4 ) === $priority( 'update_themes_github.com', 'uri_update' ) && null !== $priority( 'theme_action_links', 'theme_action_links' ), 'Los filtros se registran también en un subsitio de la red.' );

/* ================================================================================================================
 * 5. Cobertura de las copias.
 * ============================================================================================================== */
scenario();
ok( array( 'digitalisimo-theme', 'digitalisimo-theme-1.0.0' ) === array_keys( U::installed() ), 'Copias: los temas padre con el «Update URI» del tema, sin el hijo ni otros temas.' );
// RL-2: una entrada anterior de Tools (1.0.0, ya al día) con la Release en 1.0.1 pasa a «response»; una de wordpress.org con el mismo nombre se sustituye.
$wporg = array( 'theme' => 'digitalisimo-theme-1.0.0', 'new_version' => '9.9', 'url' => 'https://wordpress.org/themes/x/', 'package' => 'https://downloads.wordpress.org/theme/x.9.9.zip' );
$old   = array( 'theme' => 'digitalisimo-theme', 'new_version' => '1.0.0', 'url' => 'x', 'package' => '', 'requires' => '6.8', 'requires_php' => '7.4', 'id' => URI );
$t     = U::check( transient( array( 'digitalisimo-theme-1.0.0' => $wporg ), array( 'digitalisimo-theme' => $old ) ) );
foreach ( array( 'digitalisimo-theme', 'digitalisimo-theme-1.0.0' ) as $copy ) {
	$item = $t->response[ $copy ] ?? null;
	ok( is_array( $item ) && '1.0.1' === $item['new_version'] && theme_zip( '1.0.1' ) === $item['package'] && $copy === $item['theme'] && URI === $item['id'], "$copy: la versión 1.0.1 de la Release, como array." );
	ok( ! array_key_exists( 'autoupdate', $item ) && ! array_key_exists( 'disable_autoupdate', $item ) && '6.8' === $item['requires'] && '7.4' === $item['requires_php'], "$copy: sin autoupdate (decide auto_update_themes) y con los requisitos del índice." );
	ok( ! isset( $t->no_update[ $copy ] ) && '1.0.0' === $t->checked[ $copy ], "$copy: fuera de no_update y con la versión instalada en checked." );
	parse_str( (string) parse_url( $item['url'], PHP_URL_QUERY ), $q );
	ok( 0 === strpos( $item['url'], 'https://sitio.test/wp-admin/admin-post.php?' ) && 'digitalisimo_theme_details' === $q['action'] && $copy === $q['theme'], "$copy: «Ver detalles» abre la ficha de Tools." );
}
ok( ! isset( $t->response['digitalisimo-theme-child'] ) && ! isset( $t->no_update['digitalisimo-theme-child'] ) && ! isset( $t->response['otro'] ), 'El hijo y los temas ajenos no reciben entrada.' );
ok( 1 === count( $calls ) && 0 === api_calls(), 'Un índice con tema no consulta la API de GitHub.' );
$t = U::check( transient() );
ok( 1 === count( $calls ), 'La caché propia y el índice compartido evitan otra descarga.' );
// uri_update: array sin autoupdate; respeta una respuesta previa y no responde por copias ajenas.
$uri = U::uri_update( false, array( 'UpdateURI' => URI, 'Template' => 'digitalisimo-theme-1.0.0' ), 'digitalisimo-theme-1.0.0', array() );
ok( is_array( $uri ) && '1.0.1' === $uri['version'] && '1.0.1' === $uri['new_version'] && ! array_key_exists( 'autoupdate', $uri ) && 'digitalisimo-theme-1.0.0' === $uri['theme'], 'update_themes_github.com: array con version, sin autoupdate.' );
ok( array( 'version' => '9' ) === U::uri_update( array( 'version' => '9' ), array( 'UpdateURI' => URI ), 'digitalisimo-theme-1.0.0', array() ), 'Una respuesta ya resuelta no se toca.' );
ok( false === U::uri_update( false, array( 'UpdateURI' => URI ), 'digitalisimo-theme-child', array() ) && false === U::uri_update( false, array( 'UpdateURI' => 'https://example.com/x' ), 'digitalisimo-theme', array() ), 'Ni el hijo ni otro «Update URI» reciben respuesta.' );
// RL-9: una versión que pide otro WordPress se ofrece, pero no se instala sola.
scenario( array(), array( 'update_themes' ), manifest( '1.0.1', array( 'requires' => '99.0' ) ) );
$item = U::check( transient() )->response['digitalisimo-theme'];
ok( true === ( $item['disable_autoupdate'] ?? null ) && '99.0' === $item['requires'], 'Requisito incompatible: disable_autoupdate.' );
scenario( array(), array( 'update_themes' ), manifest( '1.0.1', array( 'requires' => '6.x', 'requires_php' => '' ) ) );
$item = U::check( transient() )->response['digitalisimo-theme'];
ok( '6.8' === $item['requires'] && '7.4' === $item['requires_php'], 'Requisitos mal formados: se usan 6.8 y 7.4.' );
// Al día: no_update con el paquete vacío.
scenario( array(), array( 'update_themes' ), manifest( '1.0.0' ) );
$t = U::check( transient() );
ok( empty( $t->response ) && '' === $t->no_update['digitalisimo-theme']['package'] && '1.0.0' === $t->no_update['digitalisimo-theme']['new_version'], 'Con la versión instalada al día la entrada va a no_update.' );
// Sin capacidad y fuera del cron no se consulta la red ni se toca la caché de WordPress.
scenario( array(), array() );
$before = transient( array( 'digitalisimo-theme' => $wporg ) );
$after  = U::check( clone $before );
ok( $after == $before && 0 === count( $calls ), 'Sin update_themes ni cron, check() no consulta la red.' );
$GLOBALS['cron'] = true;
ok( isset( U::check( transient() )->response['digitalisimo-theme'] ), 'En el cron sí cubre las copias.' );
// Un índice válido sin «themes» es «tema sin publicar»: sin entrada y sin consultar la API.
scenario( array(), array( 'update_themes' ), manifest( null ) );
$t = U::check( transient() );
ok( empty( $t->response ) && empty( $t->no_update ) && 0 === api_calls(), 'Índice sin tema: sin entrada y sin consultas a api.github.com.' );
// Entradas ajenas en el índice (otro repositorio, otro slug, otra versión): se rechazan sin ir a la API.
foreach ( array( 'https://github.com/otro/repo/releases/download/v1/digitalisimo-theme-1.0.1.zip', 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-tools-1.0.1.zip', theme_zip( '1.0.2' ), 'https://example.com/digitalisimo-theme-1.0.1.zip' ) as $bad ) {
	scenario( array(), array( 'update_themes' ), manifest( '1.0.1', array( 'url' => $bad ) ) );
	$t = U::check( transient() );
	ok( empty( $t->response ) && 0 === api_calls(), 'Paquete ajeno rechazado: ' . $bad );
}
// Índice caído: la API una sola vez; después, la última versión conocida con 2 minutos de caché.
scenario( array(), array( 'update_themes' ), array( 'code' => 404, 'body' => '' ) );
$responses['api'] = array( 'code' => 200, 'body' => json_encode( array( array( 'draft' => false, 'prerelease' => false, 'tag_name' => 'v9', 'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v9', 'assets' => array( array( 'name' => 'digitalisimo-theme-1.0.3.zip', 'browser_download_url' => theme_zip( '1.0.3', 'v9' ) ), array( 'name' => 'digitalisimo-theme-1.0.9.zip', 'browser_download_url' => 'https://example.com/digitalisimo-theme-1.0.9.zip' ) ) ) ) ) );
$t = U::check( transient() );
ok( '1.0.3' === ( $t->response['digitalisimo-theme']['new_version'] ?? '' ) && 1 === api_calls() && '6.8' === $t->response['digitalisimo-theme']['requires'], 'Sin índice, la API una vez (y sólo un ZIP del repositorio oficial).' );
U::clear_own(); $responses['api'] = array( 'code' => 403, 'body' => '{"message":"API rate limit exceeded"}' );
$t = U::check( transient() );
ok( '1.0.3' === ( $t->response['digitalisimo-theme']['new_version'] ?? '' ) && 120 === $ttl[ U::CACHE ], 'GitHub limitado: la última versión conocida, con 2 minutos de caché.' );
// «Buscar actualizaciones» de otro plugin borra el índice compartido: se vacía la caché propia, no al revés.
scenario();
U::release();
delete_site_transient( 'digitalisimo_plugins_manifest' );
ok( ! isset( $cache[ U::CACHE ] ), 'Al borrar el índice compartido se vacía la caché del tema.' );
U::release(); U::clear_own();
ok( isset( $cache['digitalisimo_plugins_manifest'] ) && ! isset( $cache[ U::CACHE ] ), 'clear_own() no borra el índice compartido.' );
// RL-3: la copia cargada (su actualizador existe y es la plantilla activa) sólo se cubre si nadie escribió su entrada.
if ( ! class_exists( 'Digitalisimo_Theme_Updater', false ) ) { final class Digitalisimo_Theme_Updater {} }
scenario( array(), array( 'update_themes' ) );
$GLOBALS['template'] = $GLOBALS['stylesheet'] = 'digitalisimo-theme';
ok( 'digitalisimo-theme' === U::loaded(), 'La copia cargada es la plantilla activa con su actualizador presente.' );
$by_theme = array( 'theme' => 'digitalisimo-theme', 'new_version' => '1.0.1', 'url' => 'del-tema', 'package' => theme_zip( '1.0.1' ), 'requires' => '6.8', 'requires_php' => '7.4', 'id' => URI );
$t = U::check( transient( array( 'digitalisimo-theme' => $by_theme ) ) );
ok( 'del-tema' === $t->response['digitalisimo-theme']['url'] && 'digitalisimo-theme-1.0.0' === $t->response['digitalisimo-theme-1.0.0']['theme'], 'La entrada del actualizador del tema manda; la otra copia se cubre igual.' );
$t = U::check( transient( array(), array( 'digitalisimo-theme' => array( 'id' => URI, 'new_version' => '1.0.1', 'url' => 'del-tema' ) ) ) );
ok( 'del-tema' === $t->no_update['digitalisimo-theme']['url'] && ! isset( $t->response['digitalisimo-theme'] ), 'Tampoco se toca una entrada propia en no_update.' );
$t = U::check( transient() );
ok( '1.0.1' === $t->response['digitalisimo-theme']['new_version'] && false !== strpos( $t->response['digitalisimo-theme']['url'], 'digitalisimo_theme_details' ), 'Sin entrada del tema (actualizador roto), Tools escribe la de respaldo.' );
$t = U::check( transient( array( 'digitalisimo-theme' => $wporg ) ) );
ok( URI === $t->response['digitalisimo-theme']['id'] && theme_zip( '1.0.1' ) === $t->response['digitalisimo-theme']['package'], 'Una entrada de wordpress.org para la copia cargada también se sustituye.' );

/* ================================================================================================================
 * 6. Carpeta conservada al actualizar (keep_folder, prioridad 11).
 * ============================================================================================================== */
scenario();
$header = "/*\n * Theme Name: DIGITALÍSIMO\n * Version: 1.0.1\n * Update URI: " . URI . "\n */\n";
$package = function ( $name, $style ) use ( $tmp ) { static $n = 0; $dir = WP_CONTENT_DIR . '/upgrade/pkg' . ( ++$n ) . '/' . $name; @mkdir( $dir, 0777, true ); if ( null !== $style ) file_put_contents( $dir . '/style.css', $style ); return $dir . '/'; };
$wp_filesystem = new Filesystem_Stub();
foreach ( array( array( 'theme' => 'digitalisimo-theme-1.0.0', 'type' => 'theme', 'action' => 'update' ), array( 'theme' => 'digitalisimo-theme-1.0.0', 'temp_backup' => array( 'slug' => 'digitalisimo-theme-1.0.0' ) ) ) as $extra ) {
	$src = $package( 'digitalisimo-theme', $header );
	$out = U::keep_folder( $src, dirname( $src ), null, $extra );
	ok( is_string( $out ) && dirname( rtrim( $src, '/' ) ) . '/digitalisimo-theme-1.0.0/' === $out && is_file( $out . 'style.css' ), 'La versión nueva queda en la carpeta de la copia (' . implode( ',', array_keys( $extra ) ) . ').' );
	// Idempotente: el siguiente filtro (o el del tema antes) recibe la carpeta ya movida.
	$moves = count( $wp_filesystem->moves );
	ok( $out === U::keep_folder( rtrim( $out, '/' ), '', null, $extra ) && count( $wp_filesystem->moves ) === $moves, 'Con la carpeta ya en su nombre no se mueve nada.' );
}
$src = $package( 'digitalisimo-theme', $header );
$wp_filesystem->fail = true;
$out = U::keep_folder( $src, '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) );
ok( is_wp_error( $out ) && 'digitalisimo_theme_folder' === $out->get_error_code() && is_file( $src . 'style.css' ), 'RL-1: si move() falla, WP_Error antes de que WordPress vacíe otra carpeta.' );
$wp_filesystem->fail = false;
foreach ( array( "/*\n * Theme Name: Otro\n * Update URI: https://example.com/x\n */", $header . "/*\n * Template: digitalisimo-theme\n */" ) as $foreign ) {
	$out = U::keep_folder( $package( 'digitalisimo-theme', str_replace( " */\n/*\n", '', $foreign ) ), '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) );
	ok( is_wp_error( $out ) && 'digitalisimo_theme_package' === $out->get_error_code(), 'Un paquete con otro «Update URI» o con «Template:» se rechaza.' );
}
$src = $package( 'digitalisimo-theme', $header );
ok( $src === U::keep_folder( $src, '', null, array( 'theme' => 'twentytwentyfive' ) ) && $src === U::keep_folder( $src, '', null, array( 'type' => 'theme', 'action' => 'install' ) ), 'Otro tema o una instalación: no actúa.' );
$error = new WP_Error( 'x' );
ok( $error === U::keep_folder( $error, '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) ), 'Un error anterior pasa tal cual.' );
// RL-7: sin copia local (FTP), la cabecera se lee con get_contents().
$wp_filesystem = new Filesystem_Stub(); $wp_filesystem->remote = '/ftp/wp-content/'; $wp_filesystem->contents = $header;
$out = U::keep_folder( '/ftp/wp-content/upgrade/x/digitalisimo-theme/', '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) );
ok( '/ftp/wp-content/upgrade/x/digitalisimo-theme-1.0.0/' === $out && array( '/ftp/wp-content/upgrade/x/digitalisimo-theme/style.css' ) === $wp_filesystem->reads, 'Sin copia local se lee con $wp_filesystem->get_contents() y se mueve.' );
$wp_filesystem->contents = false;
ok( '/ftp/wp-content/upgrade/y/digitalisimo-theme-1.0.0/' === U::keep_folder( '/ftp/wp-content/upgrade/y/digitalisimo-theme-1.0.0', '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) ), 'Cabecera ilegible con la carpeta ya en su nombre: pasa (como check_package).' );
$out = U::keep_folder( '/ftp/wp-content/upgrade/z/digitalisimo-theme/', '', null, array( 'theme' => 'digitalisimo-theme-1.0.0' ) );
ok( is_wp_error( $out ) && 'digitalisimo_theme_folder' === $out->get_error_code(), 'Cabecera ilegible con otro nombre: WP_Error (no se adivina).' );
unset( $GLOBALS['wp_filesystem'] );
// clear_after_upgrade: con `theme` (upgrade) y con `themes` (bulk) se olvidan las copias y se vacía la caché propia.
foreach ( array( array( 'theme' => 'digitalisimo-theme-1.0.0' ), array( 'themes' => array( 'digitalisimo-theme-1.0.0' ), 'bulk' => true ) ) as $upgrade_options ) {
	scenario(); U::release();
	U::clear_after_upgrade( null, $upgrade_options + array( 'action' => 'update', 'type' => 'theme' ) );
	ok( ! isset( $cache[ U::CACHE ] ), 'Tras actualizar una copia se vuelve a leer la Release.' );
}
scenario(); U::release(); U::clear_after_upgrade( null, array( 'action' => 'update', 'type' => 'theme', 'themes' => array( 'twentytwentyfive' ) ) );
ok( isset( $cache[ U::CACHE ] ), 'Actualizar otro tema no vacía la caché.' );

/* ================================================================================================================
 * 1, 4 y 8. Sección «Tema» del catálogo en cada contexto.
 * ============================================================================================================== */
// Sitio individual: una fila por copia, incluida la cargada (P-12); Actualizar, Buscar actualizaciones, Ver detalles e Ir a Temas.
scenario();
$GLOBALS['template'] = $GLOBALS['stylesheet'] = 'digitalisimo-theme';
$site_options['auto_update_themes'] = array( 'digitalisimo-theme' );
$html = render( 'site' );
ok( 2 === rows( $html ), 'Sitio: una fila por copia, incluida la activa.' );
ok( false !== strpos( $html, 'Activo' ) && false !== strpos( $html, 'Inactivo' ) && false !== strpos( $html, '(carpeta digitalisimo-theme-1.0.0)' ) && 2 === substr_count( $html, 'Actualización disponible' ), 'Sitio: estado activo/inactivo, carpeta distinta y actualización disponible.' );
ok( 1 === substr_count( $html, 'Actualización automática activada' ) && 1 === substr_count( $html, 'Actualización automática desactivada' ), 'Sitio: estado de la actualización automática según auto_update_themes.' );
$update_links = find_links( $html, 'Actualizar' );
ok( 2 === count( $update_links ) && 'digitalisimo_tools_theme_update' === $update_links[0]['query']['action'] && 'site' === $update_links[0]['query']['digitalisimo_context'], 'Sitio: «Actualizar» por copia con el contexto del sitio.' );
ok( nonce( 'digitalisimo_tools_theme_update_digitalisimo-theme-1.0.0' ) === $update_links[1]['query']['_wpnonce'] && 'digitalisimo-theme-1.0.0' === $update_links[1]['query']['theme'], 'Sitio: nonce propio de cada copia.' );
$check_links = find_links( $html, 'Buscar actualizaciones' );
ok( 2 === count( $check_links ) && nonce( 'digitalisimo_tools_theme_check_digitalisimo-theme' ) === $check_links[0]['query']['_wpnonce'], 'Sitio: «Buscar actualizaciones» por copia.' );
$details = find_links( $html, 'Ver detalles' );
ok( 2 === count( $details ) && false !== strpos( $details[0]['attrs'], 'thickbox' ) && 'true' === $details[0]['query']['TB_iframe'] && 'digitalisimo_theme_details' === $details[0]['query']['action'] && in_array( 'thickbox', $log, true ), 'Sitio: «Ver detalles» en Thickbox.' );
ok( 2 === count( find_links( $html, 'Ir a Temas' ) ) && 'https://sitio.test/wp-admin/themes.php' === find_links( $html, 'Ir a Temas' )[0]['url'], 'Sitio: «Ir a Temas».' );
$never = function ( $html, $where ) {
	foreach ( links( $html ) as $link ) {
		ok( ! preg_match( '/^(activar|habilitar|activate|enable)/i', $link['text'] ) && ! in_array( $link['query']['action'] ?? '', array( 'activate', 'enable', 'network-enable' ), true ) && false === strpos( $link['url'], 'themes.php?action=' ), $where . ': nunca «Activar» ni «Habilitar» (' . $link['text'] . ').' );
	}
};
$never( $html, 'Sitio' );
ok( false === strpos( $html, 'Antes de cambiar de tema' ), 'Sin Hello activo no hay inventario previo.' );
// Sin copias: una fila «No instalado» con «Instalar».
scenario();
$GLOBALS['themes'] = array( 'twentytwentyfive' => new WP_Theme( 'twentytwentyfive', array( 'Version' => '1.3' ) ) );
$html = render( 'site' );
$install = find_links( $html, 'Instalar' );
ok( 1 === rows( $html ) && false !== strpos( $html, 'No instalado' ) && 1 === count( $install ) && 'digitalisimo-theme' === $install[0]['query']['theme'] && nonce( 'digitalisimo_tools_theme_install_digitalisimo-theme' ) === $install[0]['query']['_wpnonce'], 'Sin copias: una fila «No instalado» con «Instalar».' );
$never( $html, 'Sin copias' );
// Versión incompatible: se informa y no hay botón.
scenario( array(), array( 'update_themes', 'install_themes', 'switch_themes' ), manifest( '1.0.1', array( 'requires_php' => '99.0' ) ) );
$html = render( 'site' );
ok( 2 === substr_count( $html, 'Incompatible: la versión 1.0.1 requiere WordPress 6.8 y PHP 99.0' ) && ! find_links( $html, 'Actualizar' ), 'Incompatible: estado y sin «Actualizar».' );
// 4. Sistema de archivos que pide credenciales: sin botones, con enlace a Actualizaciones.
scenario();
$GLOBALS['fs'] = 'ftpext';
$html = render( 'site' );
ok( ! array_filter( links( $html ), function ( $l ) { return in_array( $l['query']['action'] ?? '', array( 'digitalisimo_tools_theme_update', 'digitalisimo_tools_theme_install' ), true ); } ) && find_links( $html, 'Ir a Actualizaciones' ) && false !== strpos( $html, 'wp-admin/update-core.php' ), 'FS no directo: sin botones de instalar ni actualizar y con enlace a update-core.php.' );
// Red: lo mismo con el contexto de red, «Habilitado en la red» y «Temas de la red»; nunca habilita.
scenario( array( 'multisite' => true, 'network' => true ), array( 'manage_network_themes', 'update_themes', 'install_themes', 'switch_themes' ) );
$GLOBALS['allowed_network'] = array( 'digitalisimo-theme' );
$GLOBALS['template'] = $GLOBALS['stylesheet'] = 'hello-elementor';
$html = render( 'network' );
ok( 2 === rows( $html ) && 1 === substr_count( $html, 'No habilitado en la red' ) && 1 === substr_count( $html, 'Habilitado en la red' ), 'Red: una fila por copia con su estado de habilitación.' );
$update_links = find_links( $html, 'Actualizar' );
ok( 2 === count( $update_links ) && 'network' === $update_links[0]['query']['digitalisimo_context'] && 'network' === find_links( $html, 'Buscar actualizaciones' )[0]['query']['digitalisimo_context'], 'Red: acciones con el contexto de red.' );
ok( 'https://sitio.test/wp-admin/network/themes.php' === ( find_links( $html, 'Temas de la red' )[0]['url'] ?? '' ), 'Red: enlace a Temas de la red.' );
ok( false === strpos( $html, 'Antes de cambiar de tema' ), 'Red: el inventario previo es de cada sitio, no de la red.' );
$never( $html, 'Red' );
// Subsitio: sólo estado; el administrador de la red instala y actualiza.
scenario( array( 'multisite' => true, 'blog' => 3 ), array( 'switch_themes' ) );
$GLOBALS['allowed_site'] = array( 3 => array( 'digitalisimo-theme-1.0.0' ) );
$GLOBALS['template'] = $GLOBALS['stylesheet'] = 'digitalisimo-theme-1.0.0';
$html = render( 'site' );
ok( 2 === rows( $html ) && 1 === substr_count( $html, 'No disponible en este sitio' ) && 1 === substr_count( $html, 'Disponible en este sitio' ) && 1 === substr_count( $html, 'Activo aquí' ) && false !== strpos( $html, 'La instala y actualiza el administrador de la red' ), 'Subsitio: disponible, activo aquí y quién lo instala.' );
ok( ! array_filter( links( $html ), function ( $l ) { return 0 === strpos( (string) ( $l['query']['action'] ?? '' ), 'digitalisimo_tools_theme_' ); } ), 'Subsitio: sin instalar, actualizar ni buscar actualizaciones.' );
ok( ! find_links( $html, 'Abrir en la red' ), 'Subsitio: sin manage_network_themes no hay enlace a la red.' );
$GLOBALS['caps'][] = 'manage_network_themes';
ok( 'https://sitio.test/wp-admin/network/admin.php?page=digitalisimo-plugins' === ( find_links( render( 'site' ), 'Abrir en la red' )[0]['url'] ?? '' ), 'Subsitio: un superadministrador ve el enlace a la red.' );
$never( $html, 'Subsitio' );
// 8. La sección sólo lee `themes`: una entrada de plugin con el nombre del tema no la alimenta.
$fake = json_decode( manifest( null )['body'], true );
$fake['plugins']['digitalisimo-theme'] = array( 'version' => '1.0.5', 'url' => theme_zip( '1.0.5' ) );
scenario( array(), array( 'update_themes', 'install_themes', 'switch_themes' ), array( 'code' => 200, 'body' => json_encode( $fake ) ) );
$html = render( 'site' );
ok( false !== strpos( $html, 'Sin publicar' ) && false === strpos( $html, '1.0.5' ) && ! find_links( $html, 'Actualizar' ), 'La sección sólo lee manifest[themes].' );
// 8. Los dos catálogos disparan la acción con su contexto, entre la tabla de plugins y el cierre del contenedor.
foreach ( array( 'Digitalisimo\\Tools\\Plugin_Catalog', 'Digitalisimo_Plugin_Catalog' ) as $catalog ) {
	foreach ( array( 'site' => array(), 'network' => array( 'multisite' => true, 'network' => true ) ) as $context => $ctx ) {
		scenario( $ctx, array( 'install_plugins', 'manage_network_plugins', 'manage_options', 'manage_network_options', 'update_themes', 'install_themes', 'manage_network_themes', 'switch_themes' ) );
		ob_start(); $catalog::page(); $page = (string) ob_get_clean();
		$fired = array_values( array_filter( $GLOBALS['fired'], function ( $f ) { return 'digitalisimo_plugin_catalog_after' === $f[0]; } ) );
		ok( 1 === count( $fired ) && array( $context ) === $fired[0][1], "$catalog: dispara digitalisimo_plugin_catalog_after con «{$context}»." );
		$section = strpos( $page, '<h2 id="digitalisimo-theme">Tema</h2>' );
		ok( false !== $section && $section > strpos( $page, '</table>' ) && '</div>' === substr( rtrim( $page ), -6 ), "$catalog ({$context}): la sección «Tema» va tras la tabla de plugins, dentro del contenedor." );
	}
}

/* ================================================================================================================
 * 2 y 3. Handlers: subsitio, copia exacta, nonce por copia, capacidades y bulk_upgrade().
 * ============================================================================================================== */
$handlers = array( 'install' => array( 'Digitalisimo\\Tools\\Theme_Catalog', 'install' ), 'update' => array( 'Digitalisimo\\Tools\\Theme_Catalog', 'update' ), 'check' => array( 'Digitalisimo\\Tools\\Theme_Updates', 'force_check' ) );
$action_of = array( 'install' => 'digitalisimo_tools_theme_install', 'update' => 'digitalisimo_tools_theme_update', 'check' => 'digitalisimo_tools_theme_check' );
$request = function ( $kind, $theme, $context, $nonce_theme = null ) use ( $action_of ) {
	click( array( 'action' => $action_of[ $kind ], 'theme' => $theme, 'digitalisimo_context' => $context, '_wpnonce' => nonce( $action_of[ $kind ] . '_' . ( null === $nonce_theme ? $theme : $nonce_theme ) ) ) );
};
$all_caps = array( 'manage_network_themes', 'update_themes', 'install_themes', 'switch_themes' );
foreach ( $handlers as $kind => $handler ) {
	// En la administración de un subsitio (contexto «site» en una red) terminan en wp_die, aun con nonce válido.
	scenario( array( 'multisite' => true, 'blog' => 3 ), $all_caps );
	if ( 'install' === $kind ) unset( $GLOBALS['themes']['digitalisimo-theme'] );
	$request( $kind, 'install' === $kind ? 'digitalisimo-theme' : 'digitalisimo-theme-1.0.0', 'site' );
	expect( $handler, 'Died' );
	ok( ! Theme_Upgrader::$calls && ! in_array( 'transient:update_themes', $writes, true ), "$kind: en un subsitio termina en wp_die sin tocar nada." );
	// Una carpeta que no coincide exactamente con una copia, o el nonce de otra copia, también.
	foreach ( 'install' === $kind ? array( 'digitalisimo-theme-1.0.0', 'Digitalisimo-Theme', 'digitalisimo-theme ', 'otro' ) : array( 'digitalisimo-theme-1.0.0 ', 'DIGITALISIMO-THEME-1.0.0', 'digitalisimo-theme-child', 'digitalisimo-theme-1.0.0/../otro', 'twentytwentyfive' ) as $theme ) {
		scenario( array( 'multisite' => true, 'network' => true ), $all_caps );
		$request( $kind, $theme, 'network' );
		expect( $handler, 'Died' );
		ok( ! Theme_Upgrader::$calls, "$kind: «{$theme}» no es una copia exacta: wp_die." );
	}
	if ( 'install' !== $kind ) {
		scenario( array( 'multisite' => true, 'network' => true ), $all_caps );
		$request( $kind, 'digitalisimo-theme-1.0.0', 'network', 'digitalisimo-theme' );
		ok( 'nonce' === expect( $handler, 'Died' )->getMessage() && ! Theme_Upgrader::$calls, "$kind: el nonce de otra copia no sirve." );
	}
	scenario( array( 'multisite' => true, 'network' => true ), array( 'switch_themes' ) );
	if ( 'install' === $kind ) unset( $GLOBALS['themes']['digitalisimo-theme'] );
	$request( $kind, 'install' === $kind ? 'digitalisimo-theme' : 'digitalisimo-theme-1.0.0', 'network' );
	expect( $handler, 'Died' );
	ok( ! Theme_Upgrader::$calls, "$kind: sin la capacidad, wp_die." );
}
// install: el tema ya instalado en su carpeta no se reinstala.
scenario( array(), $all_caps );
$request( 'install', 'digitalisimo-theme', 'site' );
expect( $handlers['install'], 'Died' );
ok( ! Theme_Upgrader::$calls, 'install: con digitalisimo-theme ya instalado, wp_die.' );
// 3. update (red): rehace la entrada y llama a bulk_upgrade( [ copia ] ); nunca a upgrade() ni a install( …, overwrite_package ).
scenario( array( 'multisite' => true, 'network' => true ), $all_caps );
$GLOBALS['cache']['update_themes'] = transient( array(), array( 'digitalisimo-theme-1.0.0' => array( 'id' => URI, 'new_version' => '1.0.0', 'package' => '' ) ) );
U::release(); $before = count( $calls );
Theme_Upgrader::$bulk = array( 'digitalisimo-theme-1.0.0' => array( 'destination_name' => 'digitalisimo-theme-1.0.0' ) );
$request( 'update', 'digitalisimo-theme-1.0.0', 'network' );
$redirect = expect( $handlers['update'], 'Redirected' )->getMessage();
ok( array( array( 'bulk_upgrade', array( 'digitalisimo-theme-1.0.0' ) ) ) === Theme_Upgrader::$calls, 'update: una sola llamada, bulk_upgrade( [ copia ] ) (RL-5).' );
ok( '1.0.1' === ( $cache['update_themes']->response['digitalisimo-theme-1.0.0']['new_version'] ?? '' ) && count( $calls ) === $before + 1, 'update: antes se rehízo la entrada con el índice nuevo.' );
ok( 'https://sitio.test/wp-admin/network/admin.php?page=digitalisimo-plugins&digitalisimo-theme-notice=updated#digitalisimo-theme' === $redirect, 'update: vuelve al catálogo de la red con aviso.' );
foreach ( array( 'up_to_date' => true, 'update_failed' => new WP_Error( 'x' ) ) as $code => $result ) {
	scenario( array(), $all_caps );
	Theme_Upgrader::$bulk = array( 'digitalisimo-theme' => $result );
	$request( 'update', 'digitalisimo-theme', 'site' );
	ok( false !== strpos( expect( $handlers['update'], 'Redirected' )->getMessage(), 'digitalisimo-theme-notice=' . $code ), "update: resultado «{$code}»." );
}
scenario( array(), $all_caps, manifest( '1.0.1', array( 'requires' => '99.0' ) ) );
$request( 'update', 'digitalisimo-theme', 'site' );
ok( false !== strpos( expect( $handlers['update'], 'Redirected' )->getMessage(), 'notice=incompatible' ) && ! Theme_Upgrader::$calls, 'update: con requisitos incompatibles no actualiza.' );
scenario( array(), $all_caps ); $GLOBALS['fs'] = 'ftpext';
$request( 'update', 'digitalisimo-theme', 'site' );
ok( false !== strpos( expect( $handlers['update'], 'Redirected' )->getMessage(), 'notice=filesystem' ) && ! Theme_Upgrader::$calls, 'update: con FS no directo no actualiza.' );
// install (sitio individual): instala el paquete de la Release y exige el «Update URI» del tema; si no, informa sin borrar nada.
foreach ( array( 'installed' => URI, 'install_foreign' => 'https://example.com/x' ) as $code => $uri ) {
	scenario( array(), $all_caps );
	unset( $GLOBALS['themes']['digitalisimo-theme'] );
	Theme_Upgrader::$info = new WP_Theme( 'digitalisimo-theme', array( 'UpdateURI' => $uri ) );
	$request( 'install', 'digitalisimo-theme', 'site' );
	$redirect = expect( $handlers['install'], 'Redirected' )->getMessage();
	ok( array( array( 'install', theme_zip( '1.0.1' ), array() ) ) === Theme_Upgrader::$calls && 'https://sitio.test/wp-admin/admin.php?page=digitalisimo-plugins&digitalisimo-theme-notice=' . $code . '#digitalisimo-theme' === $redirect, "install: «{$code}»." );
	ok( ! in_array( 'delete_theme', $log, true ), "install ({$code}): nunca borra un tema." );
}
// check (sitio individual): rehace la entrada de esa copia y vuelve con el aviso.
scenario( array(), $all_caps );
$GLOBALS['cache']['update_themes'] = transient( array(), array( 'digitalisimo-theme-1.0.0' => array( 'id' => URI, 'new_version' => '1.0.0', 'package' => '' ) ) );
$GLOBALS['referer'] = 'https://sitio.test/wp-admin/admin.php?page=digitalisimo-plugins';
$request( 'check', 'digitalisimo-theme-1.0.0', 'site' );
$redirect = expect( $handlers['check'], 'Redirected' )->getMessage();
ok( 'https://sitio.test/wp-admin/admin.php?page=digitalisimo-plugins&digitalisimo-theme-checked=1' === $redirect && '1.0.1' === ( $cache['update_themes']->response['digitalisimo-theme-1.0.0']['new_version'] ?? '' ), 'check: entrada rehecha y vuelta con aviso.' );
ob_start(); C::notices(); $notice = (string) ob_get_clean();
$_GET = array( 'digitalisimo-theme-checked' => '1' ); ob_start(); C::notices(); $notice = (string) ob_get_clean();
ok( false !== strpos( $notice, 'El tema DIGITALÍSIMO 1.0.1 está disponible para actualizar.' ), 'check: el aviso dice si hay versión nueva.' );
// «Buscar actualizaciones» en Temas de la red: sólo en la red, por copia y con update_themes.
scenario( array( 'multisite' => true, 'network' => true ), $all_caps );
$row = U::theme_action_links( array( 'enable' => 'x' ), $GLOBALS['themes']['digitalisimo-theme-1.0.0'], 'all' );
$link = links( $row['digitalisimo-check'] ?? '' )[0] ?? null;
ok( $link && 'digitalisimo_tools_theme_check' === $link['query']['action'] && 'network' === $link['query']['digitalisimo_context'] && nonce( 'digitalisimo_tools_theme_check_digitalisimo-theme-1.0.0' ) === $link['query']['_wpnonce'] && 'x' === $row['enable'], 'Temas de la red: «Buscar actualizaciones» con nonce de la copia.' );
ok( array( 'a' ) === U::theme_action_links( array( 'a' ), $GLOBALS['themes']['twentytwentyfive'], 'all' ) && array( 'a' ) === U::theme_action_links( array( 'a' ), $GLOBALS['themes']['digitalisimo-theme-child'], 'all' ), 'Temas de la red: nada en otros temas ni en el hijo.' );

/* ================================================================================================================
 * 7. Ficha de «Ver detalles».
 * ============================================================================================================== */
scenario( array(), array( 'update_themes' ) );
click( array( 'action' => 'digitalisimo_theme_details', 'theme' => 'digitalisimo-theme' ) );
expect( array( 'Digitalisimo\\Tools\\Theme_Updates', 'details' ), 'Died' );
ok( ! in_array( 'iframe_header', $log, true ), 'Ficha: pide switch_themes.' );
scenario( array(), array( 'switch_themes' ) );
click( array( 'theme' => 'twentytwentyfive' ) );
expect( array( 'Digitalisimo\\Tools\\Theme_Updates', 'details' ), 'Died' );
scenario( array(), array( 'switch_themes' ) );
$GLOBALS['themes']['digitalisimo-theme-<b>x</b>'] = own_theme( 'digitalisimo-theme-<b>x</b>' );
click( array( 'theme' => 'digitalisimo-theme-<b>x</b>' ) );
ob_start();
try { U::details(); } catch ( Done $e ) { /* iframe_footer() */ }
$page = (string) ob_get_clean();
$order = array_values( array_intersect( $log, array( 'screen:digitalisimo-theme-details', 'ui-register', 'style:digitalisimo-ui', 'iframe_header', 'iframe_footer' ) ) );
ok( array( 'screen:digitalisimo-theme-details', 'ui-register', 'style:digitalisimo-ui', 'iframe_header', 'iframe_footer' ) === $order && 'digitalisimo-theme-details' === ( $GLOBALS['hook_suffix'] ?? '' ), 'Ficha: pantalla y $hook_suffix antes de iframe_header(), con la hoja compartida.' );
ok( (bool) preg_match( '/<body class="[^"]*\bdigitalisimo-admin\b/', $page ), 'Ficha: body con digitalisimo-admin.' );
ok( false !== strpos( $page, 'carpeta digitalisimo-theme-&lt;b&gt;x&lt;/b&gt;' ) && false === strpos( $page, '<b>x</b>' ), 'Ficha: todo escapado.' );
ok( false !== strpos( $page, '1.0.0' ) && false !== strpos( $page, '1.0.1' ) && false !== strpos( $page, 'v2026.10.17.500' ) && false !== strpos( $page, '2026-10-17' ) && false !== strpos( $page, 'WordPress 6.8 o superior: se cumple' ), 'Ficha: instalada, publicada, etiqueta, fecha y requisitos.' );
ok( false !== strpos( $page, 'No se actualiza solo: la actualización automática sólo se aplica si la activas en Temas.' ), 'Ficha: explica que no se actualiza solo.' );
$release_link = find_links( $page, 'Ver la Release en GitHub' )[0] ?? null;
ok( $release_link && 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.17.500' === $release_link['url'] && false !== strpos( $release_link['attrs'], 'target="_blank"' ) && false !== strpos( $release_link['attrs'], 'rel="noopener noreferrer"' ), 'Ficha: enlace a la Release con noopener.' );
ok( false === stripos( $page, '<form' ) && false === stripos( $page, 'class="button' ), 'Ficha: sin formularios ni botones.' );
// Sin copias, la ficha del tema publicado también se puede abrir.
scenario( array(), array( 'switch_themes' ) );
$GLOBALS['themes'] = array();
click( array( 'theme' => 'digitalisimo-theme' ) );
ob_start(); try { U::details(); } catch ( Done $e ) {} $page = (string) ob_get_clean();
ok( false !== strpos( $page, 'No instalado' ), 'Ficha: el tema sin instalar muestra la versión publicada.' );

/* ================================================================================================================
 * 9. Inventario «Antes de cambiar de tema» (sólo lectura).
 * ============================================================================================================== */
$themes_dir = WP_CONTENT_DIR . '/themes';
$hello_dir  = $themes_dir . '/hello-elementor';
$child_dir  = $themes_dir . '/hello-child';
foreach ( array( $hello_dir, $child_dir . '/inc', $child_dir . '/templates' ) as $dir ) @mkdir( $dir, 0777, true );
file_put_contents( $hello_dir . '/fixture-callbacks.php', "<?php\nfunction hello_fixture_own_callback( \$v ) { return \$v; }\n" );
file_put_contents( $child_dir . '/style.css', "/*\n Theme Name: Hello Child\n Template: hello-elementor\n*/\n\n.site-header{padding:0}\n.site-branding a{color:red}\n.site-headerless{}\n.page-x{background:url(img/a.png)}\n" );
file_put_contents( $child_dir . '/functions.php', "<?php\nadd_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'hijo', get_stylesheet_uri(), array( 'hello-elementor-theme-style' ) ); } );\nfunction hello_child_fixture_callback( \$v ) { return \$v; }\n" );
file_put_contents( $child_dir . '/inc/extra.php', "<?php\n" );
file_put_contents( $child_dir . '/templates/landing.php', "<?php\n/* Template Name: Landing */\n" );
require $hello_dir . '/fixture-callbacks.php';
require $child_dir . '/functions.php';
$hook = function ( array $functions ) { $callbacks = array(); foreach ( $functions as $i => $function ) $callbacks[10][ 'cb' . $i ] = array( 'function' => $function, 'accepted_args' => 1 ); return (object) array( 'callbacks' => $callbacks ); };
$inventory_fixture = function () use ( $hello_dir, $child_dir, $hook ) {
	$parent = new WP_Theme( 'hello-elementor', array( 'Name' => 'Hello', 'Version' => '3.5.1' ), null, $hello_dir );
	$GLOBALS['themes']['hello-elementor'] = $parent;
	$GLOBALS['themes']['hello-child']     = new WP_Theme( 'hello-child', array( 'Name' => 'Hello Child', 'Version' => '1.0', 'Template' => 'hello-elementor' ), $parent, $child_dir, array( 'page' => array( 'templates/landing.php' => 'Landing' ) ) );
	$GLOBALS['template'] = 'hello-elementor'; $GLOBALS['stylesheet'] = 'hello-child';
	$GLOBALS['template_uses'] = array( 'templates/landing.php' => 3 );
	$GLOBALS['options'] = array(
		'hello_elementor_settings_skip_link' => 'true', 'hello_elementor_settings_page_title' => '',
		'elementor_experiment-hello-theme-header-footer' => 'default', 'elementor_install_history' => array( '3.20.1' => 1, '3.29.0' => 2 ),
		'elementor_active_kit' => 7, 'elementor_pro_theme_builder_conditions' => array( 'footer' => array( 50 => array( 'include/general', 'exclude/singular/page/3' ), 51 => array( 'include/general' ) ) ),
	);
	// La plantilla 51 está en borrador: no cuenta.
	$GLOBALS['posts'] = array( 50 => 'publish', 51 => 'draft' );
	$GLOBALS['meta']  = array( 7 => array( '_elementor_page_settings' => array( 'hello_header_logo_display' => '', 'hello_header_tagline_display' => '', 'hello_header_menu_display' => '' ) ), 50 => array( '_elementor_conditions' => array( 'include/general', 'exclude/singular/page/3' ) ) );
	$GLOBALS['wp_filter'] = array(
		'hello_elementor_page_title'  => $hook( array( function ( $v ) { return $v; }, 'hello_fixture_own_callback', 'hello_child_fixture_callback' ) ),
		'hello-plus-theme/settings'   => $hook( array( '__return_false_fixture' ) ),
		'hello_elementor_viewport_content' => $hook( array() ),
		'the_content'                 => $hook( array( 'strtoupper' ) ),
	);
};
function __return_false_fixture() { return false; }
// T6 y T10 del tema: Tools los duplica a propósito (ningún módulo exige otro) y deben dar lo mismo con la misma entrada.
require __DIR__ . '/../digitalisimo-theme/inc/switch.php';
foreach ( array( 'digitalisimo_theme_header_footer_modes', 'digitalisimo_theme_child_inventory', 'digitalisimo_theme_hello_filters' ) as $theme_function ) ok( function_exists( $theme_function ), "El tema debe definir {$theme_function}() (T6/T10) para compararla con Tools." );
scenario( array(), array( 'install_themes', 'update_themes', 'switch_themes' ) );
$inventory_fixture();
$writes = array();
$inventory = C::inventory();
ok( array( 'family' => 'hello', 'stylesheet' => 'hello-child', 'template' => 'hello-elementor', 'version' => '3.5.1', 'child' => true ) === $inventory['origin'], 'Inventario: origen Hello, con hijo y la versión del padre (FR-13).' );
ok( array( 'description_meta_tag' => false, 'skip_link' => true, 'header_footer' => false, 'page_title' => false, 'hello_style' => false, 'hello_theme' => false ) === $inventory['hello'], 'Inventario: los seis ajustes de Hello (T5).' );
ok( array( 'experiment' => true, 'header' => 'hidden', 'footer' => 'tb_partial' ) === $inventory['header_footer'], 'Inventario: cabecera oculta (todos los interruptores en vacío) y pie con exclusiones.' );
ok( array( 'functions.php', 'inc/extra.php', 'templates/landing.php' ) === $inventory['child']['php_files'] && array( '.site-header', '.site-branding' ) === $inventory['child']['selectors'] && true === $inventory['child']['enqueues_hello_deps'], 'Inventario: archivos PHP del hijo, selectores de Hello y dependencia de sus hojas.' );
ok( strlen( ".site-header{padding:0}\n.site-branding a{color:red}\n.site-headerless{}\n.page-x{background:url(img/a.png)}" ) === $inventory['child']['style_bytes'] && array( array( 'template' => 'templates/landing.php', 'pages' => 3 ) ) === $inventory['child']['page_templates'], 'Inventario: bytes del style.css sin cabecera y plantillas propias con su uso.' );
ok( array( 'hello-plus-theme/settings' => 1, 'hello_elementor_page_title' => 2 ) === $inventory['filters'] && array( 'hello_elementor_page_title' => 1 ) === $inventory['child_filters'], 'Inventario: callbacks de los filtros de Hello, sin los del propio Hello; los del hijo aparte.' );
ok( array( 'hello_printed' => true, 'seo_prints' => false ) === $inventory['description'], 'Inventario: Hello imprime la descripción y SEO no está activo.' );
$theme_modes = digitalisimo_theme_header_footer_modes( $inventory['hello'] );
ok( array( 'experiment' => $theme_modes['experiment'], 'header' => $theme_modes['header'], 'footer' => $theme_modes['footer'] ) === $inventory['header_footer'], 'Inventario = T6 del tema con los mismos datos.' );
ok( digitalisimo_theme_child_inventory( $GLOBALS['themes']['hello-child'] ) === $inventory['child'], 'Inventario del hijo = T10 del tema.' );
// Una subcarpeta ilegible del hijo no lanza UnexpectedValueException (CATCH_GET_CHILD): se salta, en Tools y en el tema.
@mkdir( $child_dir . '/privado', 0777, true );
file_put_contents( $child_dir . '/privado/oculto.php', "<?php\n" );
chmod( $child_dir . '/privado', 0 );
clearstatcache();
if ( ! is_readable( $child_dir . '/privado' ) ) {
	$locked = C::child_inventory( $GLOBALS['themes']['hello-child'] );
	ok( $inventory['child']['php_files'] === $locked['php_files'] && digitalisimo_theme_child_inventory( $GLOBALS['themes']['hello-child'] ) === $locked, 'Con una subcarpeta ilegible, Tools y el tema la saltan y dan el mismo inventario.' );
} else {
	echo "Aviso: chmod 0 no quita la lectura en este sistema (¿root?); se omite el caso de la subcarpeta ilegible.\n";
}
chmod( $child_dir . '/privado', 0777 );
unlink( $child_dir . '/privado/oculto.php' );
rmdir( $child_dir . '/privado' );
// Los filtros: lo que el tema registrará después del cambio (sin los callbacks del propio Hello, que ya no se cargan).
$before_switch = $GLOBALS['wp_filter'];
$GLOBALS['wp_filter']['hello_elementor_page_title'] = $hook( array( function ( $v ) { return $v; }, 'hello_child_fixture_callback' ) );
ok( digitalisimo_theme_hello_filters() === $inventory['filters'], 'Filtros del inventario = los que registra el tema tras el cambio.' );
$GLOBALS['wp_filter'] = $before_switch;
ob_start(); C::render_inventory(); $html = (string) ob_get_clean();
ok( array() === $writes, 'Inventario: cero escrituras.' );
ok( false !== strpos( $html, 'Antes de cambiar de tema' ) && false !== strpos( $html, '<code>hello-child</code>' ) && false !== strpos( $html, '<code>hello_elementor_page_title</code>' ) && false !== strpos( $html, 'después del cambio no se imprimirá' ) && false !== strpos( $html, 'El código PHP del hijo no se carga con el tema DIGITALÍSIMO' ), 'Inventario: hijo, filtros, descripción perdida y aviso del PHP del hijo.' );
ok( false === stripos( visible( $html ), 'elementor' ), 'Inventario: el nombre del constructor original sólo aparece en identificadores dentro de <code>.' );
// Con SEO imprimiendo la descripción, el aviso cambia.
if ( ! class_exists( 'Digitalisimo_Integrations_Settings', false ) ) { final class Digitalisimo_Integrations_Settings { public static function get( $key = null ) { return 'enable_seo' === $key ? 1 : null; } } }
ok( true === C::inventory()['description']['seo_prints'], 'Inventario: SEO activo con enable_seo imprime la descripción.' );
// T6 en todos sus modos.
$mode = function ( $part, array $options, array $hello = array(), array $posts = array(), array $meta = array() ) {
	$GLOBALS['options'] = $options; $GLOBALS['posts'] = $posts; $GLOBALS['meta'] = $meta;
	$hello = array_merge( array_fill_keys( C::HELLO_SETTINGS, false ), $hello );
	$tools = C::header_footer_mode( $part, $hello, C::header_footer_experiment() );
	$theme = digitalisimo_theme_header_footer_modes( $hello );
	ok( $theme[ $part ] === $tools && $theme['experiment'] === C::header_footer_experiment(), "T6 de Tools ({$tools}) distinto del tema ({$theme[ $part ]})." );
	return $tools;
};
$general = array( 'elementor_pro_theme_builder_conditions' => array( 'header' => array( 9 => array( 'include/general' ) ) ) );
ok( 'disabled' === $mode( 'header', $general, array( 'header_footer' => true ), array( 9 => 'publish' ) ), 'T6: disabled manda sobre todo.' );
ok( 'tb' === $mode( 'header', $general, array(), array( 9 => 'publish' ), array( 9 => array( '_elementor_conditions' => array( 'include/general' ) ) ) ), 'T6: tb con include/general publicado.' );
ok( 'tb_partial' === $mode( 'header', $general, array(), array( 9 => 'publish' ), array( 9 => array( '_elementor_conditions' => array( 'include/general', 'exclude/archive/category/2' ) ) ) ), 'T6: tb_partial con exclusiones.' );
ok( 'classic' === $mode( 'header', $general, array(), array( 9 => 'draft' ) ), 'T6: una plantilla en borrador no cuenta.' );
ok( 'dynamic' === $mode( 'footer', array( 'elementor_experiment-hello-theme-header-footer' => 'active' ) ), 'T6: dynamic con el experimento activo.' );
ok( 'dynamic' === $mode( 'header', array( 'elementor_experiment-hello-theme-header-footer' => '', 'elementor_install_history' => array( '3.3.0' => 1 ) ) ), 'T6: dynamic por defecto con instalación desde 3.3.0.' );
ok( 'classic' === $mode( 'header', array( 'elementor_install_history' => array( '3.2.9' => 1, '3.30.0' => 2 ) ) ), 'T6: classic con la primera instalación anterior a 3.3.0.' );
ok( 'classic' === $mode( 'header', array( 'elementor_experiment-hello-theme-header-footer' => 'inactive', 'elementor_install_history' => array( '3.25.0' => 1 ) ) ), 'T6: classic con el experimento inactivo.' );
$kit_off = array( 7 => array( '_elementor_page_settings' => array( 'hello_footer_logo_display' => '', 'hello_footer_tagline_display' => '', 'hello_footer_menu_display' => '' ) ) );
ok( 'dynamic' === $mode( 'footer', array( 'elementor_experiment-hello-theme-header-footer' => 'active', 'elementor_active_kit' => 7 ), array(), array(), $kit_off ), 'T6: el pie con el copyright ausente (vale yes) sigue siendo dynamic.' );
$kit_off[7]['_elementor_page_settings']['hello_footer_copyright_display'] = '';
ok( 'dynamic' === $mode( 'footer', array( 'elementor_experiment-hello-theme-header-footer' => 'active', 'elementor_active_kit' => 7 ), array(), array(), $kit_off ), 'T6: con los cuatro interruptores del pie en vacío sigue siendo dynamic (Hello imprime el pie vacío; sólo la cabecera se oculta).' );
// La pantalla lo explica aparte: el pie se verá vacío, con su fondo y su relleno.
$inventory_fixture();
$GLOBALS['options']['elementor_pro_theme_builder_conditions'] = array();
$GLOBALS['options']['elementor_experiment-hello-theme-header-footer'] = 'active';
$GLOBALS['meta'][7]['_elementor_page_settings'] = $kit_off[7]['_elementor_page_settings'];
ok( 'dynamic' === C::inventory()['header_footer']['footer'], 'Inventario: el pie con todo oculto es dynamic, como en el tema.' );
ob_start(); C::render_inventory(); $html = (string) ob_get_clean();
ok( false !== strpos( $html, 'El pie se configura en los ajustes del sitio y tiene todos sus elementos ocultos: como en Hello, se pinta su contenedor vacío' ) && false === strpos( $html, 'seguirá sin pintarse' ), 'Inventario: el pie con todo oculto se explica como un contenedor vacío, no como algo que no se pinta.' );
// Con otro tema activo no hay inventario.
scenario();
ob_start(); C::render_inventory(); ok( array() === C::inventory() && '' === ob_get_clean(), 'Sin Hello activo no hay inventario.' );

/* ================================================================================================================
 * 10. Estáticas: Tools nunca cambia de tema, ni lo borra, ni lo habilita, ni fuerza su actualización automática.
 * ============================================================================================================== */
$tools_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../digitalisimo-tools', FilesystemIterator::SKIP_DOTS ) );
foreach ( $tools_files as $file ) {
	if ( 'php' !== $file->getExtension() ) continue;
	$tokens = array_values( array_filter( token_get_all( file_get_contents( $file->getPathname() ) ), function ( $t ) { return ! is_array( $t ) || ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ); } ) );
	$name   = substr( $file->getPathname(), strlen( __DIR__ . '/../' ) );
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) ) continue;
		$next = $tokens[ $i + 1 ] ?? null;
		if ( T_STRING === $token[0] && in_array( strtolower( $token[1] ), array( 'switch_theme', 'delete_theme' ), true ) && '(' === $next ) {
			$prev = $tokens[ $i - 1 ] ?? null;
			ok( is_array( $prev ) && T_FUNCTION === $prev[0], "$name: llama a {$token[1]}()." );
		}
		if ( T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
			$literal = trim( $token[1], '\'"' );
			ok( 'allowedthemes' !== $literal, "$name: escribe allowedthemes." );
			ok( 'autoupdate' !== $literal, "$name: una entrada con autoupdate." );
			ok( 'overwrite_package' !== $literal, "$name: install( …, overwrite_package ) para actualizar." );
			if ( 'auto_update_theme' === $literal ) {
				$prev2 = $tokens[ $i - 2 ] ?? null;
				ok( ! ( is_array( $prev2 ) && in_array( strtolower( $prev2[1] ), array( 'add_filter', 'add_action' ), true ) ), "$name: filtra auto_update_theme." );
			}
		}
		if ( T_OBJECT_OPERATOR === $token[0] && is_array( $next ) && 'upgrade' === strtolower( $next[1] ) ) ok( false, "$name: Theme_Upgrader::upgrade() (debe ser bulk_upgrade)." );
	}
}
echo "DIGITALÍSIMO Tools: cobertura del tema, sección «Tema», handlers, ficha e inventario previo validados.\n";
