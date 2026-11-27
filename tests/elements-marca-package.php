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
foreach ( array( 'class-map.php', 'class-addons.php', 'class-toggle.php', 'class-transform.php', 'class-package.php' ) as $f ) {
	require $pl . '/modules/digitalisimo-marca/' . $f;
}
use Digitalisimo\Elements\Marca\Package;

// K2 -------------------------------------------------------------------------------------------------------------------
check( Package::transformed() && 'motor/assets/lib/eicons/css/dg-icons.min.css' === ( Package::index()['motor/assets/lib/eicons/css/elementor-icons.min.css'] ?? '' ) && null === Package::index()['motor/readme.txt'], 'K2 el índice dice qué se publicó y con qué nombre' );
check( '.elementor-widget{color:red}.elementor-kit-1 a{background:url(../images/x.png)}' === Package::original( 'motor/assets/css/frontend.min.css' ), 'K2 el original se lee del archivo de datos' );
check( "=== Elementor ===\n" === Package::original( 'motor/readme.txt' ) && null === Package::original( 'no/existe.css' ), 'K2 también lo que no se publica; lo que no está, null' );

// K3 -------------------------------------------------------------------------------------------------------------------
Package::reset( null, 'off' );
check( Package::deploy() && Package::deployed(), 'K3 con la marca apagada, los originales se despliegan' );
$base = Package::base()['dir'];
check( false !== strpos( $base, '/uploads/digitalisimo-elements/base-9.9.9' ) && '.elementor-widget{color:red}.elementor-kit-1 a{background:url(../images/x.png)}' === @file_get_contents( $base . '/motor/assets/css/frontend.min.css' ) && is_file( $base . '/motor/assets/lib/eicons/css/elementor-icons.min.css' ) && is_file( $base . '/motor/assets/images/x.png' ) && is_file( $base . '/motor/assets/lib/eicons/fonts/eicons.woff2' ) && ! is_file( $base . '/motor/elementor.php' ), 'K3 el árbol del núcleo sin PHP, con los originales y lo que no cambió' );
check( '.elementor-x{background:url(https://ensayo.test/wp-content/plugins/digitalisimo-elements/assets/images/y.png)}' === @file_get_contents( $base . '/p/assets/css/widget-x.min.css' ), 'K3 los originales de PRO con sus url() hacia el plugin', @file_get_contents( $base . '/p/assets/css/widget-x.min.css' ) );
$u = 'https://ensayo.test/wp-content/plugins/digitalisimo-elements/';
check( 'https://ensayo.test/wp-content/uploads/digitalisimo-elements/base-9.9.9/motor/assets/lib/eicons/css/elementor-icons.min.css?ver=5' === Package::map_url( $u . 'motor/assets/lib/eicons/css/elementor-icons.min.css?ver=5' ), 'K3 una URL del núcleo apunta a los originales desplegados' );
check( 'https://ensayo.test/wp-content/uploads/digitalisimo-elements/base-9.9.9/p/assets/css/widget-x.min.css' === Package::map_url( $u . 'assets/css/widget-x.min.css' ) && $u . 'assets/js/ok.js' === Package::map_url( $u . 'assets/js/ok.js' ) && $u . 'motor/includes/x.php' === Package::map_url( $u . 'motor/includes/x.php' ), 'K3 lo cambiado de PRO también; lo que no cambió y el PHP, igual' );

// K4 -------------------------------------------------------------------------------------------------------------------
Package::reset( null, 'on' );
check( $u . 'motor/assets/lib/eicons/css/dg-icons.min.css?ver=5' === Package::map_url( $u . 'motor/assets/lib/eicons/css/elementor-icons.min.css?ver=5' ) && $u . 'motor/assets/css/frontend.min.css' === Package::map_url( $u . 'elementor/assets/css/frontend.min.css' ), 'K4 con la marca encendida, el nombre publicado' );
check( ! is_file( $pl . '/motor/assets/js/frontend.js' ) && is_file( $pl . '/motor/assets/js/solo.js' ) && $u . 'motor/assets/js/frontend.min.js?ver=1' === Package::map_url( $u . 'elementor/assets/js/frontend.js?ver=1' ), 'K4 el JS sin minificar que tiene su .min no se publica y su URL sirve el .min (SCRIPT_DEBUG)' );
check( '<link href="https://ensayo.test/wp-content/plugins/digitalisimo-elements/motor/assets/lib/eicons/css/dg-icons.min.css">' === \Digitalisimo\Elements\Marca\Transform::html( '<link href="https://ensayo.test/wp-content/plugins/digitalisimo-elements/motor/assets/lib/eicons/css/elementor-icons.min.css">' ), 'K4 la capa del front usa los nombres del paquete' );

// K6: con el paquete transformado, la marca no se enciende hasta que la capa del editor esté validada (filtro).
$GLOBALS['options']['digitalisimo_elements_marca'] = array( 'mode' => 'on', 'force' => true );
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
check( ! is_dir( $base ), 'K5 al encender la marca en un sitio individual, los originales desplegados se borran' );

// K7: en una red la carpeta de originales es una para todos los sitios: se borra sólo si todos quedan encendidos.
$GLOBALS['multisite'] = true;
$GLOBALS['blogs']     = array(
	1 => array( \Digitalisimo\Elements\Marca\Toggle::OPTION => array( 'mode' => 'on' ) ),
	2 => array( \Digitalisimo\Elements\Marca\Toggle::OPTION => array( 'mode' => 'off' ) ),
);
check( Package::deploy() && false === Package::remove_originals() && is_dir( $base ), 'K7 en una red con un sitio apagado, los originales se quedan' );
$GLOBALS['blogs'][2][ \Digitalisimo\Elements\Marca\Toggle::OPTION ] = array( 'mode' => 'on' );
check( true === Package::remove_originals() && ! is_dir( $base ), 'K7 con todos los sitios encendidos, se borran' );
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

exec( 'rm -rf ' . escapeshellarg( $tmp ) );
echo "\n$GLOBALS[fails] fallos de $GLOBALS[checks] comprobaciones\n";
exit( $GLOBALS['fails'] ? 1 : 0 );
