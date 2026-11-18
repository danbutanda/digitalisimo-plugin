<?php
/**
 * «Instalar todos» en los catálogos «Plugins · Digitalisimo» de SEO y de Tools (pedido del propietario del 2026-11-18:
 * «agrega un botón de instalar todos los plugins Digitalisimo para no estar uno por uno»).
 * Cada paso instala un solo plugin (cada petición con su propio tiempo de ejecución) y pasa al siguiente con el mismo
 * nonce; Elements va antes que Migración; un paquete que falla no detiene a los demás y se informa al final; la cadena
 * siempre termina; sin permiso no se instala nada; el botón sólo aparece si falta algo y para quien puede instalar.
 */
$root = sys_get_temp_dir() . '/digi-install-all-' . getmypid();
@mkdir( $root . '/wp-admin/includes', 0777, true );
@mkdir( $root . '/plugins', 0777, true );
file_put_contents( $root . '/wp-admin/includes/class-wp-upgrader.php', '<?php' );
file_put_contents( $root . '/wp-admin/includes/file.php', '<?php' );
define( 'ABSPATH', $root . '/' );
define( 'WP_PLUGIN_DIR', $root . '/plugins' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
register_shutdown_function( function () use ( $root ) { exec( 'rm -rf ' . escapeshellarg( $root ) ); } );

class Redirected extends Exception {}
class Died extends Exception {}
$GLOBALS['actions'] = array(); $GLOBALS['cache'] = array(); $GLOBALS['site_options'] = array();
$GLOBALS['multisite'] = false; $GLOBALS['network_admin'] = false; $GLOBALS['can'] = true;
$GLOBALS['referers'] = array(); $GLOBALS['installs'] = array(); $GLOBALS['fail'] = array();
function add_filter() {} function do_action() {}
function add_action( $hook, $callback = null ) { $GLOBALS['actions'][] = $hook; }
function is_multisite() { return $GLOBALS['multisite']; }
function is_network_admin() { return $GLOBALS['network_admin']; }
function current_user_can() { return $GLOBALS['can']; }
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['cache'][ $key ] = $value; return true; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); return true; }
function get_site_option( $key, $default = false ) { return $GLOBALS['site_options'][ $key ] ?? $default; }
function update_site_option( $key, $value ) { $GLOBALS['site_options'][ $key ] = $value; return true; }
function is_wp_error( $thing ) { return false; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function esc_url_raw( $url ) { return $url; }
function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html__( $text ) { return esc_html( $text ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_unslash( $value ) { return $value; }
function add_menu_page() {} function add_submenu_page() {} function remove_submenu_page() {}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://example.test/wp-admin/network/' . $path; }
function wp_create_nonce( $action ) { return 'nonce-' . md5( $action ); }
function wp_nonce_url( $url, $action ) { return add_query_arg( '_wpnonce', wp_create_nonce( $action ), $url ); }
function check_admin_referer( $action ) {
	$GLOBALS['referers'][] = $action;
	if ( ( $_GET['_wpnonce'] ?? '' ) !== wp_create_nonce( $action ) ) throw new Died( 'nonce' );
	return 1;
}
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) { $add = $args[0]; $url = $args[1]; } else { $add = array( $args[0] => $args[1] ); $url = $args[2]; }
	$parts = explode( '?', $url, 2 ); $query = array(); if ( isset( $parts[1] ) ) parse_str( $parts[1], $query );
	return $parts[0] . '?' . http_build_query( array_merge( $query, $add ) );
}
function wp_safe_redirect( $url ) { throw new Redirected( $url ); }
function wp_die( $message = '' ) { throw new Died( (string) $message ); }
function get_file_data( $path ) { return array( 'Version' => file_exists( $path ) ? '1.0.0' : '' ); }
$GLOBALS['versions'] = array( 'digitalisimo-seo' => '1.0.254', 'digitalisimo-ia-tools' => '1.0.60', 'digitalisimo-hosting' => '1.0.30', 'digitalisimo-backups' => '1.0.81', 'digitalisimo-tools' => '1.0.38', 'digitalisimo-elements' => '4.3.0.187', 'digitalisimo-migration' => '1.0.28' );
function wp_remote_get( $url ) {
	$plugins = array();
	foreach ( $GLOBALS['versions'] as $slug => $version ) $plugins[ $slug ] = array( 'version' => $version, 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.18.491/' . $slug . '-' . $version . '.zip' );
	return array( 'code' => 200, 'body' => json_encode( array( 'tag' => 'v2026.11.18.491', 'plugins' => $plugins ) ) );
}
class Automatic_Upgrader_Skin {}
/** Instala de verdad en el directorio de plugins de la prueba: crea el archivo principal del plugin del paquete. */
class Plugin_Upgrader {
	public function __construct( $skin = null ) {}
	public function install( $package ) {
		preg_match( '#/(digitalisimo-[a-z-]+)-[0-9.]+\.zip$#', $package, $match ); $slug = $match[1] ?? '';
		$GLOBALS['installs'][] = $slug;
		if ( in_array( $slug, $GLOBALS['fail'], true ) ) return false;
		$file = WP_PLUGIN_DIR . '/' . $GLOBALS['files'][ $slug ];
		@mkdir( dirname( $file ), 0777, true ); file_put_contents( $file, '<?php' );
		return true;
	}
}
require __DIR__ . '/../digitalisimo-seo/includes/class-plugin-catalog.php';
require __DIR__ . '/../digitalisimo-tools/includes/class-plugin-catalog.php';

$failures = 0;
function check( $ok, $message ) { global $failures; if ( ! $ok ) { $failures++; fwrite( STDERR, "FALLA: $message\n" ); } }
function invoke( $class, $method, ...$args ) { $m = new ReflectionMethod( $class, $method ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invoke( null, ...$args ); }
$tools_files = array(); foreach ( invoke( 'Digitalisimo\\Tools\\Plugin_Catalog', 'catalog' ) as $slug => $plugin ) $tools_files[ $slug ] = $plugin['file'];
$seo_files = array(); foreach ( invoke( 'Digitalisimo_Plugin_Catalog', 'plugins' ) as $slug => $plugin ) $seo_files[ $slug ] = $plugin[1];
$GLOBALS['files'] = $tools_files;
check( $tools_files == $seo_files, 'Los dos catálogos deben ofrecer los mismos plugins con los mismos archivos.' );

function reset_site( $installed ) {
	exec( 'rm -rf ' . escapeshellarg( WP_PLUGIN_DIR ) ); mkdir( WP_PLUGIN_DIR, 0777, true );
	foreach ( $installed as $slug ) { $file = WP_PLUGIN_DIR . '/' . $GLOBALS['files'][ $slug ]; @mkdir( dirname( $file ), 0777, true ); file_put_contents( $file, '<?php' ); }
	$GLOBALS['installs'] = array(); $GLOBALS['referers'] = array(); $GLOBALS['fail'] = array(); $GLOBALS['cache'] = array(); $_GET = array();
}
function query_of( $url ) { $query = array(); parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query ); return $query; }
/** Sigue la cadena de redirecciones de «Instalar todos» desde la URL del botón; devuelve la URL final (o la excepción si murió). */
function run_chain( $class, $start_url, &$steps ) {
	$url = $start_url; $steps = 0;
	while ( false !== strpos( $url, 'admin-post.php' ) ) {
		if ( ++$steps > 12 ) return 'BUCLE';
		$_GET = query_of( $url ); $_REQUEST = $_GET;
		try { $class::install_all(); return 'SIN REDIRECCIÓN'; }
		catch ( Redirected $redirect ) { $url = $redirect->getMessage(); }
	}
	return $url;
}
function render_page( $class, $get = array() ) { $_GET = $get; ob_start(); $class::page(); return ob_get_clean(); }
function button_url( $html ) { return preg_match( '#<a class="button button-primary button-hero" href="([^"]+)"#', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : ''; }

$classes = array(
	'Digitalisimo_Plugin_Catalog' => Digitalisimo_Plugin_Catalog::class,
	'Digitalisimo\\Tools\\Plugin_Catalog' => \Digitalisimo\Tools\Plugin_Catalog::class,
);
foreach ( $classes as $label => $class ) {
	$action = defined( $class . '::INSTALL_ALL_ACTION' ) ? constant( $class . '::INSTALL_ALL_ACTION' ) : ( defined( $class . '::INSTALL_ALL' ) ? constant( $class . '::INSTALL_ALL' ) : '' );
	check( '' !== $action, "$label: debe definir la acción de «Instalar todos»." );
	if ( '' === $action ) continue;
	$GLOBALS['actions'] = array(); $GLOBALS['multisite'] = false; $GLOBALS['network_admin'] = false; $GLOBALS['can'] = true;
	// init() registra el paso de «Instalar todos» en admin-post.
	if ( property_exists( $class, 'initialized' ) ) { $p = new ReflectionProperty( $class, 'initialized' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( null, false ); }
	$class::init();
	check( in_array( 'admin_post_' . $action, $GLOBALS['actions'], true ), "$label: init() debe registrar admin_post_$action." );

	// 1. Con Tools y SEO instalados faltan cinco: el botón lo dice y la cadena los instala en el orden del catálogo, uno por paso.
	reset_site( array( 'digitalisimo-tools', 'digitalisimo-seo' ) );
	$html = render_page( $class );
	check( false !== strpos( $html, 'Instalar todos (5)' ), "$label: el botón debe decir «Instalar todos (5)»." );
	$start = button_url( $html ); $start_query = query_of( $start );
	check( false !== strpos( $start, 'admin-post.php' ) && $action === ( $start_query['action'] ?? '' ) && wp_create_nonce( $action ) === ( $start_query['_wpnonce'] ?? '' ) && 'site' === ( $start_query['digitalisimo_context'] ?? '' ), "$label: el botón debe llevar a admin-post con su acción, su nonce y el contexto." );
	$final = run_chain( $class, $start, $steps );
	$expected = array_values( array_diff( array_keys( $tools_files ), array( 'digitalisimo-tools', 'digitalisimo-seo' ) ) );
	$order = array_keys( $label === 'Digitalisimo_Plugin_Catalog' ? $seo_files : $tools_files );
	$expected_order = array_values( array_intersect( $order, $expected ) );
	check( $GLOBALS['installs'] === $expected_order, "$label: debe instalar los cinco que faltan en el orden del catálogo, uno por paso: " . implode( ',', $GLOBALS['installs'] ) );
	check( array_search( 'digitalisimo-elements', $GLOBALS['installs'], true ) < array_search( 'digitalisimo-migration', $GLOBALS['installs'], true ), "$label: Elements antes que Migración." );
	check( 6 === $steps, "$label: cinco pasos que instalan y uno que cierra (fueron $steps)." );
	check( count( $GLOBALS['referers'] ) === $steps && array( $action ) === array_values( array_unique( $GLOBALS['referers'] ) ), "$label: cada paso comprueba el nonce de «Instalar todos»." );
	$final_query = query_of( $final );
	check( 0 === strpos( $final, 'https://example.test/wp-admin/admin.php?' ) && 'digitalisimo-plugins' === ( $final_query['page'] ?? '' ) && 'done' === ( $final_query['digitalisimo_install_all'] ?? '' ), "$label: al terminar vuelve al catálogo con el resumen ($final)." );
	$html = render_page( $class, $final_query );
	check( false !== strpos( $html, 'notice-success' ) && false !== strpos( $html, 'DIGITALÍSIMO Elements' ) && false !== strpos( $html, 'Actívalos en Plugins' ) && false === strpos( $html, 'notice-error' ), "$label: el resumen dice qué se instaló y que falta activarlos." );
	check( '' === button_url( $html ), "$label: sin plugins por instalar no hay botón." );

	// 2. Un paquete que falla no detiene a los demás, se intenta una sola vez y se informa.
	reset_site( array( 'digitalisimo-tools' ) ); $GLOBALS['fail'] = array( 'digitalisimo-hosting' );
	$final = run_chain( $class, button_url( render_page( $class ) ), $steps );
	check( 6 === count( $GLOBALS['installs'] ) && 1 === count( array_keys( $GLOBALS['installs'], 'digitalisimo-hosting', true ) ), "$label: con Hosting fallando intenta los seis que faltan, Hosting una sola vez: " . implode( ',', $GLOBALS['installs'] ) );
	$html = render_page( $class, query_of( $final ) );
	check( false !== strpos( $html, 'notice-error' ) && false !== strpos( $html, 'No se pudo instalar: DIGITALÍSIMO Hosting' ) && false !== strpos( $html, 'notice-success' ), "$label: el resumen separa lo instalado de lo que falló." );
	check( false !== strpos( $html, 'Instalar el plugin que falta' ), "$label: con uno solo pendiente el botón lo dice en singular." );

	// 3. Sin permiso para instalar plugins: ni botón ni instalación.
	reset_site( array() ); $GLOBALS['can'] = false;
	check( '' === button_url( render_page( $class ) ), "$label: sin permiso no hay botón." );
	$_GET = query_of( $start ); $died = false;
	try { $class::install_all(); } catch ( Died $e ) { $died = true; } catch ( Redirected $e ) {}
	check( $died && array() === $GLOBALS['installs'], "$label: sin permiso install_all() se detiene sin instalar nada." );
	$GLOBALS['can'] = true;

	// 4. Sin nonce válido no se instala nada.
	reset_site( array() ); $bad = query_of( $start ); $bad['_wpnonce'] = 'otro'; $_GET = $bad; $died = false;
	try { $class::install_all(); } catch ( Died $e ) { $died = true; } catch ( Redirected $e ) {}
	check( $died && array() === $GLOBALS['installs'], "$label: sin nonce válido no se instala nada." );

	// 5. «tried» sólo acepta slugs del catálogo, sin repetir, y con todos intentados la cadena cierra sin instalar.
	reset_site( array() ); $all = query_of( $start ); $all['tried'] = implode( ',', array_keys( $tools_files ) ) . ',digitalisimo-ajeno,<script>,digitalisimo-seo'; $_GET = $all; $final = '';
	try { $class::install_all(); } catch ( Redirected $e ) { $final = $e->getMessage(); }
	check( array() === $GLOBALS['installs'] && implode( ',', array_keys( $tools_files ) ) === ( query_of( $final )['tried'] ?? '' ), "$label: con todo intentado cierra sin instalar y el resumen sólo lleva slugs del catálogo." );

	// 6. En la red: el botón lleva el contexto de red y la cadena vuelve al catálogo de la red.
	reset_site( array( 'digitalisimo-tools', 'digitalisimo-seo', 'digitalisimo-backups', 'digitalisimo-hosting', 'digitalisimo-ia-tools' ) );
	$GLOBALS['multisite'] = true; $GLOBALS['network_admin'] = true;
	$start = button_url( render_page( $class ) );
	check( 'network' === ( query_of( $start )['digitalisimo_context'] ?? '' ), "$label: en la red el botón lleva el contexto de red." );
	$final = run_chain( $class, $start, $steps );
	check( array( 'digitalisimo-elements', 'digitalisimo-migration' ) === $GLOBALS['installs'] && 0 === strpos( $final, 'https://example.test/wp-admin/network/admin.php?' ), "$label: en la red instala lo que falta y vuelve al catálogo de la red ($final)." );
	$GLOBALS['multisite'] = false; $GLOBALS['network_admin'] = false;
}

if ( $failures ) { fwrite( STDERR, "$failures comprobaciones fallaron.\n" ); exit( 1 ); }
echo "Instalar todos en los catálogos de SEO y Tools: correcto.\n";
