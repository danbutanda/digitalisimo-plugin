<?php
/**
 * Marca propia, fase 2, opción A (docs/MARCA_PROPIA.md): el paquete transformado al empaquetar y sus originales.
 * Arma un plugin mínimo en una carpeta temporal, lo empaqueta con scripts/marca-empaquetar.mjs (Node) y comprueba, con
 * las clases reales copiadas a esa carpeta:
 * - K1 el paquete: `elementor/` → `motor/`, copias transformadas y renombradas, documentación fuera, originales en
 *   `marca/originales.php` (empieza con `<?php exit;`), `marca/indice.php`, `marca/reglas.php` y `marca/arbol.php`;
 *   ningún archivo no PHP con «elementor» en la ruta o el contenido;
 * - K2 los originales se leen del archivo con su sha256;
 * - K3 marca apagada: se despliegan en `uploads/digitalisimo-elements/base-<versión>/` (el árbol del núcleo sin PHP con
 *   los originales, y `p/` con los de PRO y módulos, con sus url() relativos apuntando al plugin) y las URL del núcleo y
 *   de cada archivo cambiado apuntan ahí;
 * - K4 marca encendida: las URL registradas con el nombre original salen con el publicado;
 * - K5 la huella del árbol: igual tras empaquetar, distinta si alguien cambia un archivo; borrar los originales.
 * El despliegue nunca copia a uploads algo que un servidor pueda ejecutar (`.PHP`, `.phtml`, `.php7`, `.phar`…).
 * Falla con el código anterior: no existen scripts/marca-empaquetar.mjs ni class-package.php.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DIGITALISIMO_ELEMENTS_VERSION', '9.9.9' );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['fails']  = 0;
$GLOBALS['checks'] = 0;
function check( $ok, $what, $detail = '' ) {
	$GLOBALS['checks']++;
	if ( $ok ) {
		echo "ok · $what\n";
	} else {
		$GLOBALS['fails']++;
		echo "FALLA · $what" . ( '' !== $detail ? ' · ' . substr( is_string( $detail ) ? $detail : json_encode( $detail, JSON_UNESCAPED_SLASHES ), 0, 900 ) : '' ) . "\n";
	}
}
$repo = dirname( __DIR__ );
if ( ! is_file( $repo . '/scripts/marca-empaquetar.mjs' ) || ! is_file( $repo . '/digitalisimo-elements/modules/digitalisimo-marca/class-package.php' ) ) {
	echo "FALLA no existe el empaquetado de la marca propia (código anterior)\n";
	exit( 1 );
}
$tmp = sys_get_temp_dir() . '/digi-mp-pkg-' . getmypid();
define( 'WP_CONTENT_DIR', $tmp . '/wp-content' );
$pl  = WP_CONTENT_DIR . '/plugins/digitalisimo-elements';
$put = static function ( $rel, $data ) use ( $pl ) {
	@mkdir( dirname( $pl . '/' . $rel ), 0777, true );
	file_put_contents( $pl . '/' . $rel, $data );
};
$put( 'pro-elements.php', "<?php\n// stub\n" );
$put( 'marca/reglas.json', file_get_contents( $repo . '/digitalisimo-elements/marca/reglas.json' ) );
$put( 'elementor/elementor.php', "<?php\n// núcleo\n" );
$put( 'elementor/digitalisimo-core.json', '{"version":"4.3.5","source":"elementor.4.3.5.zip"}' );
$put( 'elementor/readme.txt', "=== Elementor ===\n" );
$put( 'elementor/assets/css/frontend.min.css', '.elementor-widget{color:red}.elementor-kit-1 a{background:url(../images/x.png)}' );
$put( 'elementor/assets/lib/eicons/css/elementor-icons.min.css', '.eicon-elementor:before{content:"x";font-family:eicons;src:url(../fonts/eicons.woff2)}' );
$put( 'elementor/assets/lib/eicons/fonts/eicons.woff2', "binario" );
$put( 'elementor/assets/js/frontend.min.js', 'window.elementorFrontend={};document.querySelector(".elementor-element");' );
$put( 'elementor/assets/images/x.png', 'png' );
$put( 'elementor/assets/js/frontend.js.map', '{"sources":["elementor"]}' );
$put( 'elementor/assets/js/frontend.js', "window.elementorFrontend = {};\n" );
$put( 'elementor/assets/js/solo.js', 'console.log("sin min");' );
$put( 'assets/css/widget-x.min.css', '.elementor-x{background:url(../images/y.png)}' );
$put( 'assets/images/y.png', 'png' );
$put( 'assets/js/ok.js', 'console.log(1);' );
// Lo que el servidor lee para pintar, la licencia y una fuente con «elementor» en los nombres de glifos.
$put( 'elementor/assets/shapes/wave.svg', '<svg><path class="elementor-shape-fill" d="M0"/></svg>' );
$put( 'elementor/modules/atomic-widgets/elements/x/x.html.twig', "{% import 'elementor/macros' as m %}{# elementor #}<div class=\"elementor-x {{ m.c('elementor-element') }}\"></div>" );
$put( 'elementor/modules/mcp/static-resources/abilities/a.md', "Usa elementor-create-page con Elementor.\n" );
$put( 'modules/digitalisimo-sales/data/k.json', '{"template":"elementor_canvas"}' );
$put( 'elementor/license.txt', "Elementor es GPL.\n" );
$put( 'elementor/assets/lib/eicons/fonts/eicons.ttf', file_get_contents( $repo . '/digitalisimo-elements/elementor/assets/lib/eicons/fonts/eicons.ttf' ) );
foreach ( glob( $repo . '/digitalisimo-elements/modules/digitalisimo-marca/*.php' ) as $f ) {
	$put( 'modules/digitalisimo-marca/' . basename( $f ), file_get_contents( $f ) );
}
$out = shell_exec( 'node ' . escapeshellarg( $repo . '/scripts/marca-empaquetar.mjs' ) . ' ' . escapeshellarg( $pl ) . ' --json 2>&1' );
$rep = json_decode( (string) $out, true );
check( is_array( $rep ), 'K1 el empaquetado corre', $out );

// K1 -------------------------------------------------------------------------------------------------------------------
check( ! is_dir( $pl . '/elementor' ) && is_file( $pl . '/motor/elementor.php' ) && is_file( $pl . '/motor/digitalisimo-core.php' ) && ! is_file( $pl . '/motor/digitalisimo-core.json' ), 'K1 elementor/ → motor/ y el manifiesto como PHP' );
check( is_file( $pl . '/motor/assets/lib/eicons/css/dg-icons.min.css' ) && ! is_file( $pl . '/motor/assets/lib/eicons/css/elementor-icons.min.css' ), 'K1 el archivo con «elementor» en el nombre se publica renombrado' );
check( '.dg-widget{color:red}.dg-kit-1 a{background:url(../images/x.png)}' === file_get_contents( $pl . '/motor/assets/css/frontend.min.css' ), 'K1 el CSS transformado en su lugar, con su url() intacto', file_get_contents( $pl . '/motor/assets/css/frontend.min.css' ) );
check( ! is_file( $pl . '/motor/readme.txt' ) && ! is_file( $pl . '/motor/assets/js/frontend.js.map' ), 'K1 readme y mapas no se publican' );
check( 0 === strpos( (string) file_get_contents( $pl . '/marca/originales.php' ), '<?php exit;' ) && is_file( $pl . '/marca/indice.php' ) && is_file( $pl . '/marca/reglas.php' ) && ! is_file( $pl . '/marca/reglas.json' ) && is_file( $pl . '/marca/arbol.php' ), 'K1 originales, índice, reglas y huella como PHP' );
$trace = array();
$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $pl, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	$rel = substr( (string) $f, strlen( $pl ) + 1 );
	if ( ! preg_match( '/\.php$/', $rel ) && ( preg_match( '/elementor/i', $rel ) || preg_match( '/elementor/i', (string) file_get_contents( (string) $f ) ) ) ) {
		$trace[] = $rel;
	}
}
check( array() === $trace, 'K1 ningún archivo que se pueda pedir por URL lleva «elementor»', $trace );

// Clases reales desde la copia empaquetada.
function apply_filters( $t, $v ) {
	return isset( $GLOBALS['filters'][ $t ] ) ? $GLOBALS['filters'][ $t ] : $v;
}
function get_transient( $k ) {
	return false;
}
function set_transient() {
	return true;
}
function get_file_data() {
	return array();
}
function get_template() {
	return 'digitalisimo-theme';
}
function get_stylesheet() {
	return 'digitalisimo-theme';
}
function add_filter() {}
function add_action() {}
function did_action( $t ) {
	return 1;
}
function get_option( $k, $d = false ) {
	if ( ! empty( $GLOBALS['multisite'] ) ) {
		return $GLOBALS['blogs'][ $GLOBALS['blog'] ?? 1 ][ $k ] ?? $d;
	}
	return $GLOBALS['options'][ $k ] ?? $d;
}
function is_multisite() {
	return ! empty( $GLOBALS['multisite'] );
}
function get_site_option( $k, $d = false ) {
	return $d;
}
function get_sites( $a = array() ) {
	return array_keys( $GLOBALS['blogs'] );
}
function switch_to_blog( $id ) {
	$GLOBALS['blog'] = $id;
}
function restore_current_blog() {
	$GLOBALS['blog'] = 1;
}
function update_option( $k, $v, $a = null ) {
	$GLOBALS['options'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['options'][ $k ] );
	return true;
}
$GLOBALS['scheduled'] = array();
function wp_next_scheduled( $h ) {
	return $GLOBALS['scheduled'][ $h ] ?? false;
}
function wp_schedule_single_event( $t, $h ) {
	$GLOBALS['scheduled'][ $h ] = $t;
	return true;
}
function content_url( $p = '' ) {
	return 'https://ensayo.test/wp-content/' . $p;
}
function plugins_url( $p = '', $f = '' ) {
	return 'https://ensayo.test/wp-content/plugins/digitalisimo-elements' . ( '' !== $p ? '/' . ltrim( $p, '/' ) : '' );
}
function trailingslashit( $s ) {
	return rtrim( $s, '/' ) . '/';
}
final class Digitalisimo_Elements_Core {
	public static function bundled() {
		return true;
	}
}
foreach ( array( 'class-map.php', 'class-addons.php', 'class-toggle.php', 'class-transform.php', 'class-package.php', 'class-front-layer.php' ) as $f ) {
	require $pl . '/modules/digitalisimo-marca/' . $f;
}
use Digitalisimo\Elements\Marca\Package;

// K2 -------------------------------------------------------------------------------------------------------------------
check( Package::transformed() && 'motor/assets/lib/eicons/css/dg-icons.min.css' === ( Package::index()['motor/assets/lib/eicons/css/elementor-icons.min.css'] ?? '' ) && null === Package::index()['motor/readme.txt'], 'K2 el índice dice qué se publicó y con qué nombre' );
check( '.elementor-widget{color:red}.elementor-kit-1 a{background:url(../images/x.png)}' === Package::original( 'motor/assets/css/frontend.min.css' ), 'K2 el original se lee del archivo de datos' );
check( "=== Elementor ===\n" === Package::original( 'motor/readme.txt' ) && null === Package::original( 'no/existe.css' ), 'K2 también lo que no se publica; lo que no está, null' );

// K3 -------------------------------------------------------------------------------------------------------------------
Package::reset( null, 'off' );
// Lo que un servidor podría ejecutar no se despliega en uploads, se llame como se llame (filtro sin distinguir mayúsculas).
$exec = array( 'motor/assets/a.PHP', 'motor/assets/b.phtml', 'motor/assets/c.php7', 'motor/assets/d.phar', 'motor/assets/e.Php5' );
foreach ( $exec as $rel ) {
	file_put_contents( $pl . '/' . $rel, '<?php echo 1;' );
}
check( Package::deploy() && Package::deployed(), 'K3 con la marca apagada, los originales se despliegan' );
$deployed_exec = array_values( array_filter( $exec, static function ( $rel ) { return is_file( Package::base()['dir'] . '/' . $rel ); } ) );
check( array() === $deployed_exec, 'K3 nada ejecutable (.PHP, .phtml, .php7, .phar…) se despliega en uploads', $deployed_exec );
$u0 = 'https://ensayo.test/wp-content/plugins/digitalisimo-elements/motor/assets/b.PHTML';
check( $u0 === Package::map_url( $u0 ), 'K3 y su URL nunca apunta a uploads' );
foreach ( $exec as $rel ) {
	unlink( $pl . '/' . $rel );
}
$cfg = 'var c = {"urls":{"assets":"https:\\/\\/ensayo.test\\/wp-content\\/plugins\\/digitalisimo-elements\\/motor\\/assets\\/"}};<script src="https://ensayo.test/wp-content/plugins/digitalisimo-elements/motor/assets/js/x.js">';
$off = Package::off_urls( $cfg );
check( false !== strpos( $off, 'uploads\\/digitalisimo-elements\\/base-9.9.9\\/motor\\/assets\\/' ) && false !== strpos( $off, 'uploads/digitalisimo-elements/base-9.9.9/motor/assets/js/x.js' ), 'K3 con la marca apagada, la configuración (urls.assets, también con \\/) apunta a los originales', $off );
$base = Package::base()['dir'];
check( false !== strpos( $base, '/uploads/digitalisimo-elements/base-9.9.9' ) && '.elementor-widget{color:red}.elementor-kit-1 a{background:url(../images/x.png)}' === @file_get_contents( $base . '/motor/assets/css/frontend.min.css' ) && is_file( $base . '/motor/assets/lib/eicons/css/elementor-icons.min.css' ) && is_file( $base . '/motor/assets/images/x.png' ) && is_file( $base . '/motor/assets/lib/eicons/fonts/eicons.woff2' ) && ! is_file( $base . '/motor/elementor.php' ), 'K3 el árbol del núcleo sin PHP, con los originales y lo que no cambió' );
check( '.elementor-x{background:url(https://ensayo.test/wp-content/plugins/digitalisimo-elements/assets/images/y.png)}' === @file_get_contents( $base . '/p/assets/css/widget-x.min.css' ), 'K3 los originales de PRO con sus url() hacia el plugin', @file_get_contents( $base . '/p/assets/css/widget-x.min.css' ) );
$u = 'https://ensayo.test/wp-content/plugins/digitalisimo-elements/';
check( 'https://ensayo.test/wp-content/uploads/digitalisimo-elements/base-9.9.9/motor/assets/lib/eicons/css/elementor-icons.min.css?ver=5' === Package::map_url( $u . 'motor/assets/lib/eicons/css/elementor-icons.min.css?ver=5' ), 'K3 una URL del núcleo apunta a los originales desplegados' );
check( 'https://ensayo.test/wp-content/uploads/digitalisimo-elements/base-9.9.9/p/assets/css/widget-x.min.css' === Package::map_url( $u . 'assets/css/widget-x.min.css' ) && $u . 'assets/js/ok.js' === Package::map_url( $u . 'assets/js/ok.js' ) && $u . 'motor/includes/x.php' === Package::map_url( $u . 'motor/includes/x.php' ), 'K3 lo cambiado de PRO también; lo que no cambió y el PHP, igual' );

check( '<svg><path class="elementor-shape-fill"/></svg>' === Package::off_urls( '<svg><path class="&#101;lementor-shape-fill"/></svg>' ), 'K3 (M-6) con la marca apagada, el HTML sale sin la palabra enmascarada (como el paquete de siempre)' );
// I-7 de la revisión: el búfer de la marca apagada no envuelve descargas, subidas ni admin-post.
$level = ob_get_level();
foreach ( array( 'admin-post.php', 'async-upload.php', 'export.php' ) as $screen ) {
	$GLOBALS['pagenow'] = $screen;
	Package::start_off_buffer();
}
check( $level === ob_get_level(), 'K3 (I-7) sin búfer en admin-post, async-upload ni export' );
$GLOBALS['pagenow'] = 'index.php';
Package::start_off_buffer();
check( $level + 1 === ob_get_level(), 'K3 (I-7) en las demás pantallas, sí' );
ob_end_clean();
check( ! Package::textual_response( array( 'Content-Type: application/octet-stream' ) ) && ! Package::textual_response( array( 'Content-Type: application/json', 'Content-Disposition: attachment; filename=dg-1.json' ) ) && Package::textual_response( array( 'Content-Type: application/json; charset=UTF-8' ) ) && Package::textual_response( array( 'Content-Type: text/html; charset=UTF-8' ) ) && Package::textual_response( array() ), 'K3 (I-7) sólo se reescriben respuestas de texto (HTML, JSON, JS, XML), nunca un adjunto ni un binario' );

// K4 -------------------------------------------------------------------------------------------------------------------
Package::reset( null, 'on' );
check( $u . 'motor/assets/lib/eicons/css/dg-icons.min.css?ver=5' === Package::map_url( $u . 'motor/assets/lib/eicons/css/elementor-icons.min.css?ver=5' ) && $u . 'motor/assets/css/frontend.min.css' === Package::map_url( $u . 'elementor/assets/css/frontend.min.css' ), 'K4 con la marca encendida, el nombre publicado' );
check( ! is_file( $pl . '/motor/assets/js/frontend.js' ) && is_file( $pl . '/motor/assets/js/solo.js' ) && $u . 'motor/assets/js/frontend.min.js?ver=1' === Package::map_url( $u . 'elementor/assets/js/frontend.js?ver=1' ), 'K4 el JS sin minificar que tiene su .min no se publica y su URL sirve el .min (SCRIPT_DEBUG)' );
check( '<link href="https://ensayo.test/wp-content/plugins/digitalisimo-elements/motor/assets/lib/eicons/css/dg-icons.min.css">' === \Digitalisimo\Elements\Marca\Transform::html( '<link href="https://ensayo.test/wp-content/plugins/digitalisimo-elements/motor/assets/lib/eicons/css/elementor-icons.min.css">' ), 'K4 la capa del front usa los nombres del paquete' );

// K6: con el paquete transformado, la marca no se enciende hasta que la capa del editor esté validada (filtro).
$GLOBALS['options']['digitalisimo_elements_marca'] = array( 'mode' => 'on', 'force' => true );
Package::reset();
\Digitalisimo\Elements\Marca\Toggle::reset();
check( 'unavailable' === \Digitalisimo\Elements\Marca\Toggle::status()['state'] && ! \Digitalisimo\Elements\Marca\Toggle::active(), 'K6 encendida pero sin la capa del editor validada: no disponible (los originales siguen)' );
$GLOBALS['filters']['digitalisimo_elements_marca_editor_ready'] = true;
$GLOBALS['filters']['digitalisimo_elements_marca_active']       = null;
\Digitalisimo\Elements\Marca\Toggle::reset();
check( 'on' === \Digitalisimo\Elements\Marca\Toggle::status()['state'], 'K6 con el filtro (Playground), se enciende' );

// K5 -------------------------------------------------------------------------------------------------------------------
check( true === Package::tree_ok(), 'K5 la huella del árbol coincide tras empaquetar' );
file_put_contents( $pl . '/assets/js/ok.js', 'console.log(2);' );
check( false === Package::tree_ok(), 'K5 un archivo cambiado después se detecta' );
Package::remove_originals();
check( is_dir( $base ) && is_file( $base . '/.retirar' ) && ! empty( $GLOBALS['scheduled'][ Package::CLEANUP_HOOK ] ), 'K5 (I-6) al encender la marca, los originales no se borran enseguida: quedan para retirar (páginas en caché)' );
Package::cleanup( time() + 3600 );
check( is_dir( $base ), 'K5 (I-6) antes de un día siguen' );
Package::cleanup( time() + Package::RETIRE_AFTER );
check( ! is_dir( $base ), 'K5 al día siguiente se borran' );

// K7: en una red la carpeta de originales es una para todos los sitios: se borra sólo si todos quedan encendidos.
$GLOBALS['multisite'] = true;
$GLOBALS['blogs']     = array(
	1 => array( \Digitalisimo\Elements\Marca\Toggle::OPTION => array( 'mode' => 'on' ) ),
	2 => array( \Digitalisimo\Elements\Marca\Toggle::OPTION => array( 'mode' => 'off' ) ),
);
check( Package::deploy() && false === Package::remove_originals() && ! is_file( $base . '/.retirar' ), 'K7 en una red con un sitio apagado, los originales se quedan' );
$GLOBALS['blogs'][2][ \Digitalisimo\Elements\Marca\Toggle::OPTION ] = array( 'mode' => 'on' );
$GLOBALS['filters']['digitalisimo_elements_marca_editor_ready'] = false;
check( false === Package::remove_originals() && ! is_file( $base . '/.retirar' ), 'K7 (I-5) con todos en «on» pero la marca no disponible (paquete sin el editor validado), se quedan' );
$GLOBALS['filters']['digitalisimo_elements_marca_editor_ready'] = true;
check( true === Package::remove_originals() && is_file( $base . '/.retirar' ), 'K7 con todos los sitios en vigor, quedan para retirar' );
Package::cleanup( time() + Package::RETIRE_AFTER );
check( ! is_dir( $base ), 'K7 y se borran al día siguiente' );
$GLOBALS['multisite'] = false;

// K8: lo que el servidor lee, publicado original y enmascarado; la licencia entera en .php; la fuente corregida.
$svg = (string) file_get_contents( $pl . '/motor/assets/shapes/wave.svg' );
check( '<svg><path class="&#101;lementor-shape-fill" d="M0"/></svg>' === $svg, 'K8 el separador SVG con la clase original enmascarada (entidad)', $svg );
$twig = (string) file_get_contents( $pl . '/motor/modules/atomic-widgets/elements/x/x.html.twig' );
check( "{% import ('elemen' ~ 'tor/macros') as m %}<div class=\"&#101;lementor-x {{ m.c(('elemen' ~ 'tor-element')) }}\"></div>" === $twig, 'K8 el twig con las cadenas partidas, sin el comentario y la clase con entidad', $twig );
$md = (string) file_get_contents( $pl . '/motor/modules/mcp/static-resources/abilities/a.md' );
check( "Usa elemen\u{200D}tor-create-page con Elemen\u{200D}tor.\n" === $md && "Usa elementor-create-page con Elementor.\n" === \Digitalisimo\Elements\Marca\Map::unmask( $md ), 'K8 la guía del MCP con el separador invisible, que el mapa quita', $md );
$kit = (string) file_get_contents( $pl . '/modules/digitalisimo-sales/data/k.json' );
check( false === stripos( $kit, 'elementor' ) && array( 'template' => 'elementor_canvas' ) === json_decode( $kit, true ), 'K8 el JSON del kit enmascarado y json_decode da lo de siempre', $kit );
check( ! is_file( $pl . '/motor/license.txt' ) && "<?php exit; ?>\nElementor es GPL.\n" === (string) @file_get_contents( $pl . '/motor/license.txt.php' ), 'K8 la licencia entera, en un .php que pedido por URL no devuelve nada' );
$ttf = (string) file_get_contents( $pl . '/motor/assets/lib/eicons/fonts/eicons.ttf' );
check( false === stripos( $ttf, 'elementor' ) && strlen( $ttf ) === filesize( $repo . '/digitalisimo-elements/elementor/assets/lib/eicons/fonts/eicons.ttf' ), 'K8 la fuente sin la palabra en los nombres de glifos y del mismo tamaño' );

// K9: revisión F2 (B-1, B-2, B-3). Marca apagada, sin originales desplegados.
$GLOBALS['options'] = array();
$GLOBALS['filters'] = array();
\Digitalisimo\Elements\Marca\Toggle::reset();
$up = dirname( $base );
// B-1: otra petición tiene el cerrojo (está desplegando): ésta espera unos segundos y, si no terminó, NO sirve el HTML
// original con los assets transformados: corre con las copias transformadas y la capa (coherentes), sin caché.
Package::reset( null, 'off' );
@mkdir( $base . '.lock', 0755, true );
$t0 = microtime( true );
Package::resolve();
$waited = microtime( true ) - $t0;
$css    = Package::map_url( $u . 'motor/assets/lib/eicons/css/elementor-icons.min.css' );
check( Package::fallback() && 'on' === Package::mode() && $u . 'motor/assets/lib/eicons/css/dg-icons.min.css' === $css && is_file( $pl . '/motor/assets/lib/eicons/css/dg-icons.min.css' ), 'K9 (B-1) con el cerrojo de otra petición: copias transformadas que existen, nunca el original renombrado (404) ni el HTML original', $css );
check( \Digitalisimo\Elements\Marca\Front_Layer::on() && ! \Digitalisimo\Elements\Marca\Toggle::active(), 'K9 (B-1/I-4) en ese respaldo la capa del front corre (HTML coherente con los assets), pero la marca no cuenta como en vigor' );
check( $waited >= 2.5 && $waited < 6, 'K9 (B-1) espera a la otra petición unos segundos antes del respaldo', round( $waited, 2 ) );
// Si la otra termina mientras ésta espera, usa los originales.
Package::reset( null, 'off' );
$bg = 'sleep 1; mkdir -p ' . escapeshellarg( $base ) . ' && echo 9.9.9 > ' . escapeshellarg( $base . '/.listo' );
exec( '(' . $bg . ') > /dev/null 2>&1 &' );
Package::resolve();
check( ! Package::fallback() && 'off' === Package::mode() && Package::deployed(), 'K9 (B-1) si la otra petición termina mientras espera, usa los originales desplegados' );
Package::rmdir( $base );
@rmdir( $base . '.lock' );
// B-2: cerrojo abandonado (> 10 min): se toma y se despliega; los temporales huérfanos se borran.
@mkdir( $base . '.lock', 0755, true );
touch( $base . '.lock', time() - 700 );
@mkdir( $up . '/base-9.9.9.tmp-99999/motor', 0755, true );
Package::reset( null, 'off' );
check( Package::deploy() && Package::deployed() && ! is_dir( $base . '.lock' ) && ! is_dir( $up . '/base-9.9.9.tmp-99999' ), 'K9 (B-2) un cerrojo abandonado se toma y el temporal huérfano se borra' );
Package::rmdir( $base );
// B-2: original dañado: falla en el primer error, sin restos, anotado con espera creciente y sin reintentar enseguida.
$orig  = $pl . '/marca/originales.php';
$bak   = file_get_contents( $orig );
$lines = explode( "\n", $bak, 3 );
$entries = json_decode( $lines[1], true );
foreach ( $entries as $i => $e ) {
	$entries[ $i ][4] = str_repeat( '0', 64 );
}
file_put_contents( $orig, $lines[0] . "\n" . json_encode( $entries ) . "\n" . $lines[2] );
Package::reset( null, 'off' );
$ok = Package::deploy();
$f  = Package::failure();
check( ! $ok && 1 === $f['fails'] && false !== strpos( $f['error'], 'original dañado' ) && array() === glob( $up . '/base-*.tmp-*' ) && ! is_dir( $base ), 'K9 (B-2) un original dañado: falla, queda anotado con el motivo y sin restos', $f );
Package::reset( null, 'off' );
Package::resolve();
check( Package::fallback() && 1 === Package::failure()['fails'] && Package::in_backoff(), 'K9 (B-2) la petición siguiente no reintenta (espera) y sirve el respaldo coherente' );
check( 300 === Package::backoff( 1 ) && 600 === Package::backoff( 2 ) && 21600 === Package::backoff( 12 ) && ! Package::in_backoff( time() + 301 ), 'K9 (B-2) espera creciente de 5 min hasta 6 h' );
file_put_contents( $orig, $bak );
// B-2: sin permiso de escritura en uploads: anotado, sin tormenta de reintentos.
delete_option( Package::FAIL_OPTION );
@mkdir( $up, 0755, true );
chmod( $up, 0555 );
Package::reset( null, 'off' );
$ok = Package::deploy();
check( ! $ok && false !== strpos( Package::failure()['error'], 'escribir' ), 'K9 (B-2) sin permiso de escritura queda anotado', Package::failure() );
chmod( $up, 0755 );
delete_option( Package::FAIL_OPTION );
// B-3: al actualizar corre el código viejo; se despliega la versión NUEVA del disco y la vieja queda para retirar.
Package::reset( null, 'off' );
check( Package::deploy() && is_dir( $up . '/base-9.9.9' ), 'K9 (B-3) la versión en uso desplegada' );
file_put_contents( $pl . '/pro-elements.php', "<?php\n/**\n * Plugin Name: DIGITALÍSIMO Elements\n * Version: 9.9.10\n */\n" );
Package::after_update( null, array( 'type' => 'plugin', 'action' => 'update', 'plugins' => array( 'otro/otro.php' ) ) );
check( ! is_dir( $up . '/base-9.9.10' ), 'K9 (B-3) la actualización de otro plugin no despliega nada' );
Package::after_update( null, array( 'type' => 'plugin', 'action' => 'update', 'plugins' => array( 'digitalisimo-elements/pro-elements.php' ) ) );
check( is_file( $up . '/base-9.9.10/.listo' ) && '9.9.10' === trim( (string) file_get_contents( $up . '/base-9.9.10/.listo' ) ) && is_file( $up . '/base-9.9.9/.retirar' ), 'K9 (B-3) se despliega la versión nueva (leída del disco) y la anterior queda para retirar' );
Package::cleanup( time() + Package::RETIRE_AFTER );
check( ! is_dir( $up . '/base-9.9.9' ) && is_dir( $up . '/base-9.9.10' ), 'K9 (B-3) un día después la anterior se borra; la nueva queda' );

exec( 'chmod -R u+w ' . escapeshellarg( $tmp ) . '; rm -rf ' . escapeshellarg( $tmp ) );
echo "\n$GLOBALS[fails] fallos de $GLOBALS[checks] comprobaciones\n";
exit( $GLOBALS['fails'] ? 1 : 0 );
