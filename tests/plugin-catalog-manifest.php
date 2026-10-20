<?php
/**
 * Catálogos «Plugins · Digitalisimo» de SEO y de Tools: leen el índice de versiones de la última Release (sin el
 * límite de la API de GitHub), aceptan por cada slug sólo su propio ZIP del repositorio oficial, usan la API como
 * respaldo sólo para los plugins sin entrada válida y, si GitHub no responde o limita las consultas, siguen ofreciendo
 * el último índice encontrado. Cuando otro plugin borra el índice compartido («Buscar actualizaciones») vacían su caché.
 * releases() es privado en ambos catálogos: se invoca por reflexión (ReflectionMethod::invoke) sin tocar las clases.
 */
define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/no-existe-plugins' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
$cache = array(); $site_options = array(); $calls = array(); $responses = array();
function add_filter() {} function add_action( $hook ) { $GLOBALS['actions'][] = $hook; }
function is_multisite() { return false; }
function is_network_admin() { return false; }
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['cache'][ $key ] = $value; return true; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); return true; }
function get_site_option( $key, $default = false ) { return $GLOBALS['site_options'][ $key ] ?? $default; }
function update_site_option( $key, $value ) { $GLOBALS['site_options'][ $key ] = $value; return true; }
function wp_remote_get( $url ) {
	$GLOBALS['calls'][] = $url;
	$kind = false !== strpos( $url, 'api.github.com' ) ? 'api' : 'manifest';
	return $GLOBALS['responses'][ $kind ] ?? array( 'code' => 404, 'body' => '' );
}
function is_wp_error() { return false; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function esc_url_raw( $url ) { return $url; }
// Pantalla de administración: los catálogos la registran, pero esta prueba sólo ejercita releases().
function add_menu_page() {} function add_submenu_page() {} function remove_submenu_page() {}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_url( $url ) { return $url; }
function current_user_can() { return true; }
function get_file_data() { return array( 'Version' => '' ); }
require __DIR__ . '/../digitalisimo-seo/includes/class-plugin-catalog.php';
require __DIR__ . '/../digitalisimo-tools/includes/class-plugin-catalog.php';

function check_catalog( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
$tools_main = file_get_contents( __DIR__ . '/../digitalisimo-tools/digitalisimo-tools.php' );
check_catalog( false !== strpos( $tools_main, "'includes/class-plugin-catalog.php'" ) && false !== strpos( $tools_main, 'Plugin_Catalog::init()' ), 'Tools debe cargar e iniciar su catálogo de plugins.' );
function releases( $class ) { $m = new ReflectionMethod( $class, 'releases' ); if ( PHP_VERSION_ID < 80100 ) { $m->setAccessible( true ); } return $m->invoke( null ); }
function api_calls() { return count( array_filter( $GLOBALS['calls'], function ( $url ) { return false !== strpos( $url, 'api.github.com' ); } ) ); }
function reset_all() { $GLOBALS['cache'] = array(); $GLOBALS['calls'] = array(); }
function download_url( $slug, $version, $tag = 'v2026.10.15.389' ) { return 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/' . $tag . '/' . $slug . '-' . $version . '.zip'; }
/** Entrada del tema en el índice (clave «themes»): los actualizadores de plugins sólo leen «plugins» y no deben cambiar con ella. */
function theme_index( $tag = 'v2026.10.15.389' ) { return array( 'digitalisimo-theme' => array( 'version' => '1.0.0', 'file' => 'digitalisimo-theme-1.0.0.zip', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/' . $tag . '/digitalisimo-theme-1.0.0.zip', 'requires' => '6.8', 'requires_php' => '7.4' ) ); }
function manifest( $plugins ) {
	$entries = array();
	foreach ( $plugins as $slug => $entry ) $entries[ $slug ] = is_array( $entry ) ? $entry : array( 'version' => $entry, 'url' => download_url( $slug, $entry ) );
	return array( 'code' => 200, 'body' => json_encode( array( 'tag' => 'v2026.10.15.389', 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.15.389', 'generated' => '2026-10-15T00:00:00Z', 'plugins' => $entries, 'themes' => theme_index() ) ) );
}
// La API responde con otras versiones para distinguir qué fuente alimentó el índice.
function api( $plugins ) {
	$assets = array();
	foreach ( $plugins as $slug => $version ) $assets[] = array( 'name' => $slug . '-' . $version . '.zip', 'browser_download_url' => download_url( $slug, $version, 'api' ) );
	return array( 'code' => 200, 'body' => json_encode( array( array( 'draft' => false, 'prerelease' => false, 'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/api', 'assets' => $assets ) ) ) );
}
$slugs = array( 'digitalisimo-seo' => '1.0.224', 'digitalisimo-ia-tools' => '1.0.54', 'digitalisimo-hosting' => '1.0.25', 'digitalisimo-backups' => '1.0.52', 'digitalisimo-tools' => '1.0.28', 'digitalisimo-elements' => '4.3.0.142', 'digitalisimo-migration' => '1.0.8' );
$api_versions = array_map( function ( $version ) { return $version . '.9'; }, $slugs );
$catalogs = array(
	'Digitalisimo_Plugin_Catalog' => array( Digitalisimo_Plugin_Catalog::CACHE_KEY, Digitalisimo_Plugin_Catalog::LAST_KNOWN ),
	'Digitalisimo\\Tools\\Plugin_Catalog' => array( \Digitalisimo\Tools\Plugin_Catalog::CACHE, \Digitalisimo\Tools\Plugin_Catalog::LAST_KNOWN ),
);

foreach ( $catalogs as $class => list( $cache_key, $last_known ) ) {
	$label = $class . '::releases()';
	// 1. (a) El índice de la última Release basta: los siete plugins sin ninguna consulta a la API.
	reset_all(); $site_options = array(); $responses = array( 'manifest' => manifest( $slugs ), 'api' => api( $api_versions ) );
	$index = releases( $class );
	check_catalog( count( $index ) === count( $slugs ) && ! isset( $index['digitalisimo-theme'] ), $label . ': el índice debe contener los siete plugins, sin el tema de «themes».' );
	foreach ( $slugs as $slug => $version ) check_catalog( ( $index[ $slug ]['version'] ?? '' ) === $version && ( $index[ $slug ]['package'] ?? '' ) === download_url( $slug, $version ), $label . ': ' . $slug . ' debe salir del índice de la Release.' );
	check_catalog( 1 === count( $calls ) && false !== strpos( $calls[0], '/releases/latest/download/digitalisimo-plugins.json' ) && 0 === api_calls(), $label . ': sin consultar la API de GitHub.' );
	check_catalog( ( $site_options[ $last_known ] ?? array() ) === $index, $label . ': debe recordar el último índice encontrado.' );
	// 2. La caché propia y el índice compartido evitan nuevas descargas.
	check_catalog( releases( $class ) === $index && 1 === count( $calls ), $label . ': la caché propia evita descargas.' );
	delete_site_transient( $cache_key );
	check_catalog( releases( $class ) === $index && 1 === count( $calls ), $label . ': el índice compartido en caché sirve a la siguiente consulta.' );
	// 3. (b) Una entrada ajena (otro dominio, otro repositorio, otro slug, otra versión) se descarta sin perder el resto del índice; la API completa sólo ese plugin.
	foreach ( array( 'https://example.com/digitalisimo-seo-1.0.224.zip', 'https://github.com/otro/repo/releases/download/v1/digitalisimo-seo-1.0.224.zip', download_url( 'digitalisimo-tools', '1.0.224' ), download_url( 'digitalisimo-seo', '1.0.225' ) ) as $bad ) {
		reset_all(); $tampered = $slugs; $tampered['digitalisimo-seo'] = array( 'version' => '1.0.224', 'url' => $bad );
		$responses = array( 'manifest' => manifest( $tampered ), 'api' => api( $api_versions ) );
		$index = releases( $class );
		check_catalog( $api_versions['digitalisimo-seo'] === ( $index['digitalisimo-seo']['version'] ?? '' ) && download_url( 'digitalisimo-seo', $api_versions['digitalisimo-seo'], 'api' ) === ( $index['digitalisimo-seo']['package'] ?? '' ) && count( $index ) === count( $slugs ) && '1.0.28' === ( $index['digitalisimo-tools']['version'] ?? '' ) && 1 === api_calls(), $label . ': enlace ajeno rechazado (' . $bad . '); la API completa ese plugin sin descartar el resto del índice.' );
	}
	reset_all(); $tampered = $slugs; $tampered['digitalisimo-seo'] = '1.0.224-beta'; $tampered['digitalisimo-hosting'] = 'texto';
	$responses = array( 'manifest' => manifest( $tampered ), 'api' => api( $api_versions ) );
	$index = releases( $class );
	check_catalog( $api_versions['digitalisimo-seo'] === ( $index['digitalisimo-seo']['version'] ?? '' ) && $api_versions['digitalisimo-hosting'] === ( $index['digitalisimo-hosting']['version'] ?? '' ) && '1.0.54' === ( $index['digitalisimo-ia-tools']['version'] ?? '' ) && count( $index ) === count( $slugs ) && 1 === api_calls(), $label . ': las versiones que no son numéricas se rechazan y la API completa sólo esos plugins.' );
	// 3b. Si todos los plugins del catálogo tienen entrada válida, una entrada sobrante inválida no provoca ninguna consulta a la API.
	reset_all(); $extra = $slugs; $extra['digitalisimo-migration'] = array( 'version' => '1.0.8', 'url' => 'https://example.com/digitalisimo-migration-1.0.8.zip' );
	$responses = array( 'manifest' => manifest( $extra ), 'api' => api( $api_versions ) );
	$index = releases( $class );
	check_catalog( ! isset( $index['digitalisimo-migration'] ) && count( $index ) === count( $slugs ) - 1 && 0 === api_calls(), $label . ': una entrada ajena de un plugin fuera del catálogo no obliga a consultar la API.' );
	// 4. (b) Si ninguna entrada sirve (todo el índice es ajeno) se cae a la API.
	reset_all(); $foreign = array();
	foreach ( $slugs as $slug => $version ) $foreign[ $slug ] = array( 'version' => $version, 'url' => 'https://github.com/otro/repo/releases/download/v1/' . $slug . '-' . $version . '.zip' );
	$responses = array( 'manifest' => manifest( $foreign ), 'api' => api( $api_versions ) );
	$index = releases( $class );
	check_catalog( count( $index ) === count( $slugs ) && $api_versions['digitalisimo-seo'] === ( $index['digitalisimo-seo']['version'] ?? '' ) && download_url( 'digitalisimo-seo', $api_versions['digitalisimo-seo'], 'api' ) === ( $index['digitalisimo-seo']['package'] ?? '' ) && 1 === api_calls() && 2 === count( $calls ), $label . ': con todo el índice ajeno la API queda de respaldo.' );
	// 5. Una respuesta sin «plugins» (la forma de la lista de la API) tampoco sirve como índice.
	reset_all(); $responses = array( 'manifest' => api( $api_versions ), 'api' => api( $api_versions ) );
	$index = releases( $class );
	check_catalog( $api_versions['digitalisimo-tools'] === ( $index['digitalisimo-tools']['version'] ?? '' ) && 1 === api_calls(), $label . ': un archivo sin «plugins» se descarta y se usa la API.' );
	// 6. (c) Índice ausente y API limitada: se mantiene el último índice encontrado y se cachea poco para reintentar pronto.
	reset_all(); $responses = array( 'manifest' => array( 'code' => 404, 'body' => '' ), 'api' => array( 'code' => 403, 'body' => '{"message":"API rate limit exceeded"}' ) );
	$index = releases( $class );
	check_catalog( count( $index ) === count( $slugs ) && $api_versions['digitalisimo-seo'] === ( $index['digitalisimo-seo']['version'] ?? '' ) && 2 === count( $calls ), $label . ': con GitHub limitado sigue a la vista el último índice encontrado.' );
	check_catalog( releases( $class ) === $index && 2 === count( $calls ), $label . ': la última conocida queda en caché en lugar de martillear GitHub.' );
	// 7. Sin historial ni GitHub el catálogo queda vacío en lugar de romperse.
	reset_all(); $site_options = array();
	check_catalog( array() === releases( $class ), $label . ': sin historial ni GitHub devuelve un índice vacío.' );
	// 8. Cuando otro plugin borra el índice compartido (un «Buscar actualizaciones»), el catálogo vacía su caché y se rehace con el índice nuevo.
	$GLOBALS['actions'] = array(); $class::init();
	check_catalog( in_array( 'delete_site_transient_digitalisimo_plugins_manifest', $GLOBALS['actions'], true ), $label . ': debe escuchar el borrado del índice compartido.' );
	reset_all(); $responses = array( 'manifest' => manifest( $slugs ), 'api' => api( $api_versions ) );
	$index = releases( $class );
	$class::clear();
	check_catalog( ! isset( $cache[ $cache_key ] ) && isset( $cache['digitalisimo_plugins_manifest'] ), $label . ': clear() vacía la caché propia sin tocar el índice compartido.' );
	delete_site_transient( 'digitalisimo_plugins_manifest' ); $class::clear(); $responses = array( 'manifest' => manifest( array_merge( $slugs, array( 'digitalisimo-seo' => '1.0.230' ) ) ), 'api' => api( $api_versions ) );
	check_catalog( '1.0.230' === ( releases( $class )['digitalisimo-seo']['version'] ?? '' ) && 2 === count( $calls ) && 0 === api_calls(), $label . ': tras vaciar la caché se descarga el índice nuevo.' );
}
// 8. El índice se comparte entre plugins: lo que descargó el catálogo de SEO sirve al de Tools sin otra descarga.
reset_all(); $site_options = array(); $responses = array( 'manifest' => manifest( $slugs ), 'api' => api( $api_versions ) );
releases( 'Digitalisimo_Plugin_Catalog' );
$index = releases( 'Digitalisimo\\Tools\\Plugin_Catalog' );
check_catalog( '4.3.0.142' === ( $index['digitalisimo-elements']['version'] ?? '' ) && 1 === count( $calls ), 'El índice descargado por el catálogo de SEO sirve al de Tools sin otra descarga.' );
check_catalog( theme_index() === ( $cache['digitalisimo_plugins_manifest']['themes'] ?? null ), 'El índice compartido conserva «themes» para quien lo lea después.' );
// 9. El tema no es un plugin del catálogo: ninguno de los dos lo ofrece para instalar como plugin.
foreach ( array( 'Digitalisimo_Plugin_Catalog' => 'plugins', 'Digitalisimo\\Tools\\Plugin_Catalog' => 'catalog' ) as $class => $method ) {
	$list = new ReflectionMethod( $class, $method ); if ( PHP_VERSION_ID < 80100 ) { $list->setAccessible( true ); }
	check_catalog( ! isset( $list->invoke( null )['digitalisimo-theme'] ), $class . ': el tema no figura en la lista de plugins.' );
}
echo "Catálogos de plugins: índice de la Release, respaldo por API y última conocida validados en SEO y Tools.\n";
