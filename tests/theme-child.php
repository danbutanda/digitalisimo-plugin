<?php
/**
 * Lo que aportaba un tema hijo de Hello (digitalisimo-theme/inc/child.php): lectura de su functions.php sin ejecutarlo
 * (la plantilla oficial del hijo y sus variantes, con lo que se trae y lo que no, en qué línea), archivos del hijo que
 * bloquean (plantillas, theme.json, PHP propio), encolado en el tema con el mismo handle, dirección, dependencias,
 * versión y prioridad (y nada si falta el archivo o no hay paridad), y su functions.php en Código PHP de Tools,
 * inactivo, sólo con bloqueos, y reconocido cuando ya está activo tal cual.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );

function check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
}

// ------------------------------------------------------------------ Stubs de WordPress
$GLOBALS['options'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['styles']  = array();
$GLOBALS['scripts'] = array();
$GLOBALS['queue']   = array();
$GLOBALS['parity']  = true;
$GLOBALS['filters'] = array();
$GLOBALS['root']    = sys_get_temp_dir() . '/digi-theme-child-' . getmypid();
function trailingslashit( $s ) {
	return rtrim( (string) $s, '/\\' ) . '/';
}
function untrailingslashit( $s ) {
	return rtrim( (string) $s, '/\\' );
}
function get_option( $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default;
}
function add_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['actions'][ $hook ][ $priority ][] = $callback;
}
function has_filter( $hook ) {
	return ! empty( $GLOBALS['filters'][ $hook ] );
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['filters'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}
function home_url( $path = '' ) {
	return 'https://sitio.test' . $path;
}
function get_posts( $args ) {
	return 'used.php' === $args['meta_value'] ? array( 7, 8 ) : array();
}
function get_theme_root( $stylesheet = '' ) {
	return $GLOBALS['root'];
}
function get_theme_root_uri( $stylesheet = '' ) {
	return 'https://sitio.test/wp-content/themes';
}
function wp_register_style( $handle, $src, $deps, $ver, $media ) {
	if ( ! isset( $GLOBALS['styles'][ $handle ] ) ) {
		$GLOBALS['styles'][ $handle ] = array( $src, $deps, $ver, $media );
	}
}
function wp_register_script( $handle, $src, $deps, $ver, $args ) {
	if ( ! isset( $GLOBALS['scripts'][ $handle ] ) ) {
		$GLOBALS['scripts'][ $handle ] = array( $src, $deps, $ver, $args );
	}
}
function wp_enqueue_style( $handle ) {
	$GLOBALS['queue'][] = 'style:' . $handle;
}
function wp_enqueue_script( $handle ) {
	$GLOBALS['queue'][] = 'script:' . $handle;
}
function digitalisimo_theme_parity() {
	return $GLOBALS['parity'];
}
class WP_Theme {
	public $data;
	public function __construct( array $data ) {
		$this->data = $data + array( 'parent' => null, 'templates' => array(), 'version' => '2.0.0', 'name' => 'Hijo' );
	}
	public function get_stylesheet() {
		return $this->data['stylesheet'];
	}
	public function get_template() {
		return $this->data['template'];
	}
	public function get_stylesheet_directory() {
		return $GLOBALS['root'] . '/' . $this->data['stylesheet'];
	}
	public function get( $key ) {
		return array( 'Version' => $this->data['version'], 'Name' => $this->data['name'] )[ $key ] ?? '';
	}
	public function parent() {
		return $this->data['parent'];
	}
	public function get_post_templates() {
		return $this->data['templates'];
	}
}

require __DIR__ . '/../digitalisimo-theme/inc/child.php';
check( ! has_action_hooked(), 'Cargar inc/child.php no engancha nada (Migración lo carga antes de cambiar de tema).' );
function has_action_hooked() {
	return array() !== $GLOBALS['actions'];
}

/** Lectura de un functions.php; los archivos del hijo existen salvo los marcados como «falta». */
function plan_of( $code ) {
	return digitalisimo_theme_child_functions_plan(
		$code,
		array(
			'version'        => '2.1.0',
			'parent_version' => '3.5.1',
			'exists'         => static function ( $where, $path ) {
				return false === strpos( $path, 'falta' );
			},
			'mtime'          => static function ( $path ) {
				return 'style.css' === $path ? '1700000000' : '';
			},
		)
	);
}
function whats( array $plan ) {
	return array_map( static function ( $b ) {
		return $b['line'] . ':' . $b['what'];
	}, $plan['blockers'] );
}

// ------------------------------------------------------------------ 1. La plantilla oficial del hijo de Hello (v2 y v1)
$official = <<<'PHP'
<?php
/**
 * Theme functions and definitions.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_elementor_child_scripts_styles() {

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		HELLO_ELEMENTOR_CHILD_VERSION
	);

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );
PHP;
$plan = plan_of( $official );
check( array() === $plan['blockers'], 'La plantilla oficial del hijo no bloquea: ' . implode( ' | ', whats( $plan ) ) );
check( array( array( 't' => 'style', 'h' => 'hello-elementor-child-style', 'w' => 'child', 's' => 'style.css', 'd' => array( 'hello-elementor-theme-style' ), 'v' => '2.0.0', 'm' => 'all', 'p' => 20, 'e' => true ) ) === $plan['assets'], 'Se trae su style.css con su handle, dependencia, versión y prioridad: ' . json_encode( $plan['assets'] ) );
$v1   = "<?php\ndefined( 'ABSPATH' ) || exit;\nfunction hello_elementor_child_enqueue_scripts() {\n\twp_enqueue_style( 'hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', [ 'hello-elementor-theme-style' ], '1.0.0' );\n}\nadd_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts' );\n";
$plan = plan_of( $v1 );
check( array() === $plan['blockers'] && 1 === count( $plan['assets'] ) && 10 === $plan['assets'][0]['p'] && '1.0.0' === $plan['assets'][0]['v'], 'La plantilla 1.x (guarda en una línea, prioridad por defecto 10) se trae.' );
check( array() === plan_of( "<?php\nif ( ! defined( 'ABSPATH' ) ) exit;\ndeclare( strict_types=1 );\n" )['blockers'], 'La guarda sin llaves y declare no bloquean.' );

// ------------------------------------------------------------------ 2. Variantes que también se traen
$variants = <<<'PHP'
<?php
const TP_VER = '3.0';
add_action( 'after_setup_theme', function () {
	load_child_theme_textdomain( 'hijo', get_stylesheet_directory() . '/languages' );
} );
add_action( 'wp_enqueue_scripts', static function () {
	$ver = wp_get_theme()->get( 'Version' );
	wp_register_style( 'tp-fonts', 'https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@400;700&display=swap', array(), null );
	wp_enqueue_style( 'tp-main', get_stylesheet_uri(), array( 'tp-fonts' ), filemtime( get_stylesheet_directory() . '/style.css' ) );
	wp_enqueue_style( 'tp-print', get_theme_file_uri( 'css/print.css' ), array(), $ver, 'print' );
	wp_enqueue_style( 'tp-parent', get_template_directory_uri() . '/assets/x.css', array(), HELLO_ELEMENTOR_VERSION );
	wp_enqueue_script( 'tp-js', get_stylesheet_directory_uri() . '/js/a.js', array( 'jquery' ), TP_VER, true );
	wp_enqueue_script( 'tp-defer', get_stylesheet_directory_uri() . "/js/b.js", [], 1, [ 'in_footer' => true, 'strategy' => 'defer' ] );
	wp_enqueue_style( 'tp-falta', get_stylesheet_directory_uri() . '/falta.css' );
	wp_enqueue_style( 'tp-fonts' );
	wp_register_style( 'tp-solo', get_stylesheet_directory_uri() . '/solo.css' );
}, 5 );
PHP;
$plan = plan_of( $variants );
check( array() === $plan['blockers'], 'Variantes legibles no bloquean: ' . implode( ' | ', whats( $plan ) ) );
$by = array();
foreach ( $plan['assets'] as $asset ) {
	$by[ $asset['h'] ] = $asset;
}
check( array( 'tp-fonts', 'tp-main', 'tp-print', 'tp-parent', 'tp-js', 'tp-defer', 'tp-solo' ) === array_keys( $by ), 'Siete entradas, en orden, sin el archivo que falta ni el encolado repetido: ' . implode( ',', array_keys( $by ) ) );
check( false === $by['tp-solo']['e'] && true === $by['tp-main']['e'], 'Lo sólo registrado queda registrado, no encolado.' );
check( 'url' === $by['tp-fonts']['w'] && null === $by['tp-fonts']['v'] && true === $by['tp-fonts']['e'] && 5 === $by['tp-fonts']['p'], 'La fuente de otra dirección, registrada y encolada después, se encola con versión null.' );
check( 'style.css' === $by['tp-main']['s'] && '1700000000' === $by['tp-main']['v'] && array( 'tp-fonts' ) === $by['tp-main']['d'], 'get_stylesheet_uri() y filemtime() del hijo.' );
check( 'css/print.css' === $by['tp-print']['s'] && 'child' === $by['tp-print']['w'] && 'print' === $by['tp-print']['m'] && '2.1.0' === $by['tp-print']['v'], 'get_theme_file_uri() del hijo, medio print y wp_get_theme()->get( \'Version\' ).' );
check( 'parent' === $by['tp-parent']['w'] && 'assets/x.css' === $by['tp-parent']['s'] && '3.5.1' === $by['tp-parent']['v'], 'Un archivo de Hello con HELLO_ELEMENTOR_VERSION.' );
check( 'script' === $by['tp-js']['t'] && true === $by['tp-js']['m'] && '3.0' === $by['tp-js']['v'] && array( 'in_footer' => true, 'strategy' => 'defer' ) === $by['tp-defer']['m'] && '1' === $by['tp-defer']['v'], 'Scripts con su pie de página o sus argumentos.' );

// ------------------------------------------------------------------ 3. Lo que no se trae (y en qué línea)
$plomero = <<<'PHP'
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );
function hello_elementor_child_scripts_styles() {
	wp_enqueue_style( 'hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', [ 'hello-elementor-theme-style' ], HELLO_ELEMENTOR_CHILD_VERSION );
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );
add_action('wp_footer', function () {
	echo '<script>1</script>';
});
add_action( 'wp_head', function () {
?>
<style>.x{color:red}</style>
<?php
} );
PHP;
$plan = plan_of( $plomero );
check( 1 === count( $plan['assets'] ) && 'hello-elementor-child-style' === $plan['assets'][0]['h'], 'Con PHP propio, lo que encolaba se trae igual.' );
check( array( "10:add_action( 'wp_footer' )", "13:add_action( 'wp_head' )" ) === whats( $plan ), 'wp_footer y wp_head, con su línea: ' . implode( ' | ', whats( $plan ) ) );
$bad = array(
	"add_filter( 'body_class', 'x' );"                                                     => "add_filter( 'body_class' )",
	"add_shortcode( 'tp', 'x' );"                                                          => "add_shortcode( 'tp' )",
	"require_once __DIR__ . '/inc/x.php';"                                                 => 'require_once',
	"function hello_elementor_scripts_styles() {}"                                         => 'define la función hello_elementor_scripts_styles()',
	"class X {}"                                                                           => 'class',
	"echo 'hola';"                                                                         => 'echo',
	"if ( is_admin() ) { add_action( 'wp_enqueue_scripts', 'f' ); }"                       => 'if',
	"add_action( 'wp_enqueue_scripts', fn() => wp_enqueue_style( 'a', 'https://x.test/a.css' ) );" => "add_action( 'wp_enqueue_scripts' )",
	"\$x = 1; add_action( 'wp_enqueue_scripts', function () use ( \$x ) { wp_enqueue_style( 'a', 'https://x.test/a.css' ); } );" => '$x',
	"add_action( 'wp_enqueue_scripts', 'no_existe' );"                                     => 'con una función que no está en functions.php',
	"add_action( 'wp_enqueue_scripts', function () { wp_dequeue_style( 'hello-elementor' ); } );" => "en la función de wp_enqueue_scripts: wp_dequeue_style( 'hello-elementor' )",
	"add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'a', 'css/a.css' ); } );"  => "wp_enqueue_style( 'a' )",
	"add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'a', get_stylesheet_directory_uri() . '/../b.css' ); } );" => "wp_enqueue_style( 'a' )",
	"add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'a', get_stylesheet_directory_uri() . \"/\$f.css\" ); } );" => "wp_enqueue_style( 'a' )",
	"add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'a', some_url() ); } );"   => "wp_enqueue_style( 'a' )",
	"add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'a', 'https://x.test/a.css', 'no-array' ); } );" => "wp_enqueue_style( 'a' )",
	"add_action( 'after_setup_theme', function () { add_theme_support( 'x' ); } );"         => "add_theme_support( 'x' )",
	"add_action( 'init', 'f' ); function f() {}"                                          => "add_action( 'init' )",
);
foreach ( $bad as $code => $expected ) {
	$plan = plan_of( "<?php\n" . $code . "\n" );
	check( $plan['blockers'] && false !== strpos( implode( ' | ', whats( $plan ) ), $expected ), "Bloquea «{$code}» como «{$expected}»: " . implode( ' | ', whats( $plan ) ) );
	check( array() === $plan['assets'] || "add_action( 'wp_enqueue_scripts', function () { wp_dequeue_style( 'hello-elementor' ); } );" === $code, "Nada que traer de «{$code}»." );
}
$plan = plan_of( "<p>HTML antes</p><?php\n" );
check( $plan['blockers'] && 'no se pudo leer como PHP' === $plan['blockers'][0]['what'], 'Un archivo que no empieza por PHP no se lee.' );
$plan = plan_of( "<?php\nfunction a() {}\n?>\n<p>Fuera de PHP</p>\n" );
check( false !== strpos( implode( ' | ', whats( $plan ) ), 'imprime HTML fuera de PHP' ), 'HTML fuera de PHP bloquea.' );
check( digitalisimo_theme_child_php_is_empty( "<?php\n// Silence is golden.\n" ) && digitalisimo_theme_child_php_is_empty( "<?php defined( 'ABSPATH' ) || exit;" ) && ! digitalisimo_theme_child_php_is_empty( "<?php echo 1;" ) && ! digitalisimo_theme_child_php_is_empty( 'hola' ), 'Un PHP vacío o con sólo la guarda no cuenta.' );
check( "functions.php, línea 12: add_action( 'wp_head' ); header.php: plantilla que sustituye la del tema; y 1 más" === digitalisimo_theme_child_blockers_text( array( array( 'file' => 'functions.php', 'line' => 12, 'what' => "add_action( 'wp_head' )" ), array( 'file' => 'header.php', 'line' => 0, 'what' => 'plantilla que sustituye la del tema' ), array( 'file' => 'x.php', 'line' => 0, 'what' => 'x' ) ), 2 ), 'Texto de los bloqueos.' );

// ------------------------------------------------------------------ 4. El hijo en disco: functions.php y otros archivos
function put( $path, $body ) {
	@mkdir( dirname( $GLOBALS['root'] . '/' . $path ), 0777, true );
	file_put_contents( $GLOBALS['root'] . '/' . $path, $body );
}
function cleanup( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $file ) {
		$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
	}
	rmdir( $dir );
}
cleanup( $GLOBALS['root'] );
register_shutdown_function( 'cleanup', $GLOBALS['root'] );
put( 'hello-elementor/style.css', '/* Theme Name: Hello */' );
put( 'hijo/style.css', "/*\nTheme Name: Hijo\nTemplate: hello-elementor\n*/\nbody{color:red}" );
put( 'hijo/functions.php', "\xEF\xBB\xBF" . str_replace( "\n", "\r\n", $official ) . "\n?>\n" );
put( 'hijo/index.php', "<?php\n// Silence is golden.\n" );
put( 'hijo/unused.php', "<?php\n/* Template Name: Sin uso */\nget_header();\n" );
$hello = new WP_Theme( array( 'stylesheet' => 'hello-elementor', 'template' => 'hello-elementor', 'version' => '3.5.1' ) );
$child = new WP_Theme( array( 'stylesheet' => 'hijo', 'template' => 'hello-elementor', 'parent' => $hello, 'templates' => array( 'page' => array( 'unused.php' => 'Sin uso' ) ) ) );
$plan  = digitalisimo_theme_child_plan( $child );
check( array() === $plan['blockers'] && 1 === count( $plan['assets'] ) && 'hijo' === $plan['child'] && 'hello-elementor' === $plan['parent'], 'Hijo típico en disco (BOM, CRLF y ?> final): trasladable: ' . json_encode( $plan['blockers'] ) );
$code = digitalisimo_theme_child_php_code( $child );
check( 0 === strpos( $code, '<?php' ) && false === strpos( $code, "\r" ) && "?>" !== substr( rtrim( $code ), -2 ) && hash( 'sha256', $code ) === $plan['hash'], 'functions.php normalizado como lo guarda Tools y su hash.' );
put( 'hijo/used.php', "<?php\n/* Template Name: Usada */\nget_header();\n" );
put( 'hijo/header.php', "<?php\nwp_head();\n" );
put( 'hijo/woocommerce/single-product.php', "<?php\necho 1;\n" );
put( 'hijo/theme.json', '{}' );
put( 'hijo/templates/index.html', '<!-- wp:x /-->' );
$child = new WP_Theme( array( 'stylesheet' => 'hijo', 'template' => 'hello-elementor', 'parent' => $hello, 'templates' => array( 'page' => array( 'unused.php' => 'Sin uso', 'used.php' => 'Usada' ) ) ) );
$files = array();
foreach ( digitalisimo_theme_child_plan( $child )['blockers'] as $blocker ) {
	$files[ $blocker['file'] ] = $blocker['what'];
}
ksort( $files );
check( array( 'header.php' => 'plantilla que sustituye la del tema', 'templates/index.html' => 'plantilla de bloques', 'theme.json' => 'estilos globales propios (theme.json)', 'used.php' => 'plantilla de página que usan 2 contenidos', 'woocommerce/single-product.php' => 'plantilla que sustituye la del tema' ) === $files, 'Plantillas, theme.json, plantillas de bloques y una plantilla de página en uso bloquean; la que nadie usa y el index vacío no: ' . json_encode( $files ) );

// ------------------------------------------------------------------ 5. En el tema activo: encolado desde la carpeta del hijo
$GLOBALS['options']['digitalisimo_theme_child'] = array(
	'v'      => 1,
	'child'  => 'hijo',
	'parent' => 'hello-elementor',
	'assets' => array(
		array( 't' => 'style', 'h' => 'hello-elementor-child-style', 'w' => 'child', 's' => 'style.css', 'd' => array( 'hello-elementor-theme-style' ), 'v' => '2.0.0', 'm' => 'all', 'p' => 20, 'e' => true ),
		array( 't' => 'style', 'h' => 'tp-fonts', 'w' => 'url', 's' => 'https://fonts.example.test/css?family=A', 'd' => array(), 'v' => null, 'm' => 'all', 'p' => 5, 'e' => true ),
		array( 't' => 'style', 'h' => 'tp-ido', 'w' => 'child', 's' => 'borrado.css', 'd' => array(), 'v' => false, 'm' => 'all', 'p' => 20, 'e' => true ),
		array( 't' => 'style', 'h' => 'tp-reg', 'w' => 'parent', 's' => 'style.css', 'd' => array(), 'v' => '1', 'm' => 'all', 'p' => 20, 'e' => false ),
		array( 't' => 'script', 'h' => 'tp-js', 'w' => 'child', 's' => 'style.css', 'd' => array( 'jquery' ), 'v' => '3', 'm' => true, 'p' => 20, 'e' => true ),
	),
);
digitalisimo_theme_child_hooks();
check( ! isset( $GLOBALS['actions']['wp_enqueue_scripts'] ), 'Sin «carry» en el registro del paso no se lee la lista del hijo ni se engancha nada.' );
function digitalisimo_theme_switch_record() {
	return $GLOBALS['options']['digitalisimo_theme_switch'] ?? array();
}
$GLOBALS['options']['digitalisimo_theme_switch'] = array( 'carry' => 1 );
digitalisimo_theme_child_hooks();
check( array( 20, 5 ) === array_keys( $GLOBALS['actions']['wp_enqueue_scripts'] ) && 1 === count( $GLOBALS['actions']['wp_enqueue_scripts'][20] ), 'Una función por prioridad del hijo en wp_enqueue_scripts (20 y 5).' );
$GLOBALS['parity'] = false;
$GLOBALS['actions']['wp_enqueue_scripts'][20][0]();
check( array() === $GLOBALS['queue'] && array() === $GLOBALS['styles'], 'Fuera de paridad no se encola nada del hijo.' );
$GLOBALS['parity'] = true;
wp_register_style( 'hello-elementor-child-style-otro', 'x', array(), '1', 'all' );
$GLOBALS['actions']['wp_enqueue_scripts'][5][0]();
check( array( 'style:tp-fonts' ) === $GLOBALS['queue'], 'En la prioridad 5 sólo la fuente: ' . implode( ',', $GLOBALS['queue'] ) );
check( array( 'https://sitio.test/wp-content/themes/hijo/style.css', array( 'hello-elementor-theme-style' ), '2.0.0', 'all' ) === $GLOBALS['styles']['hello-elementor-child-style'], 'El style.css del hijo, desde su carpeta, con su handle, dependencia y versión.' );
check( ! isset( $GLOBALS['styles']['tp-ido'] ), 'Un archivo que ya no está no se registra (sin enlaces rotos).' );
check( 'https://sitio.test/wp-content/themes/hello-elementor/style.css' === $GLOBALS['styles']['tp-reg'][0] && 'https://fonts.example.test/css?family=A' === $GLOBALS['styles']['tp-fonts'][0] && array( 'https://sitio.test/wp-content/themes/hijo/style.css', array( 'jquery' ), '3', true ) === $GLOBALS['scripts']['tp-js'], 'Archivo de Hello, dirección externa y script con sus datos.' );
$GLOBALS['actions']['wp_enqueue_scripts'][20][0]();
check( array( 'style:tp-fonts', 'style:hello-elementor-child-style', 'script:tp-js' ) === $GLOBALS['queue'], 'En la 20, el style.css y el script; lo sólo registrado no se encola: ' . implode( ',', $GLOBALS['queue'] ) );
$GLOBALS['options']['digitalisimo_theme_child']['assets'][0]['s'] = '../hello-elementor/style.css';
check( '' === digitalisimo_theme_child_asset_url( $GLOBALS['options']['digitalisimo_theme_child'], $GLOBALS['options']['digitalisimo_theme_child']['assets'][0] ), 'Una ruta con «..» guardada no se sirve.' );

// ------------------------------------------------------------------ 6. PHP del hijo en Código PHP de Tools
$GLOBALS['imports'] = array();
$child              = new WP_Theme( array( 'stylesheet' => 'hijo', 'template' => 'hello-elementor', 'parent' => $hello ) );
check( '' === digitalisimo_theme_child_import_php( $child, array( 'blockers' => array( array( 'file' => 'functions.php', 'line' => 3, 'what' => "add_action( 'wp_head' )" ) ), 'assets' => array() ) ), 'Sin Tools no se importa nada.' );
$GLOBALS['filters']['digitalisimo_tools_php_import'][] = static function ( $result, $items ) {
	$GLOBALS['imports'][] = $items;
	return array( 'digitalisimo-theme:hijo' => 'created' );
};
check( '' === digitalisimo_theme_child_import_php( $child, array( 'blockers' => array( array( 'file' => 'header.php', 'line' => 0, 'what' => 'x' ) ), 'assets' => array() ) ) && array() === $GLOBALS['imports'], 'Sin bloqueos en functions.php no se importa.' );
check( 'created' === digitalisimo_theme_child_import_php( $child, array( 'blockers' => array( array( 'file' => 'functions.php', 'line' => 3, 'what' => "add_action( 'wp_head' )" ) ), 'assets' => array( 1 ) ) ), 'Con bloqueos y Tools, se importa.' );
$item = $GLOBALS['imports'][0][0];
check( $code === $item['code'] && 'digitalisimo-theme' === $item['origin']['source'] && 'hijo' === $item['origin']['id'] && ! isset( $item['status'] ) && false !== strpos( $item['notes'][0], "functions.php, línea 3: add_action( 'wp_head' )" ) && 'child-' . hash( 'sha256', $code ) === $item['origin']['hash'], 'El fragmento lleva el functions.php normalizado, su origen y lo que no se trajo, sin estado (Tools lo deja inactivo).' );
check( ! digitalisimo_theme_child_php_covered( $child ), 'Sin fuentes de Tools no está cubierto.' );
$GLOBALS['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) use ( $code ) {
	return array_merge( $rows, array( array( 'active' => false, 'hash' => hash( 'sha256', $code ) ), array( 'active' => true, 'hash' => hash( 'sha256', $code . ' ' ) ) ) );
};
check( ! digitalisimo_theme_child_php_covered( $child ), 'Inactivo, o activo con otro código, no lo cubre.' );
$GLOBALS['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) use ( $code ) {
	return array_merge( $rows, array( array( 'active' => true, 'hash' => hash( 'sha256', $code ) ) ) );
};
check( digitalisimo_theme_child_php_covered( $child ), 'Activo y sin cambios en Tools: cubierto.' );

echo "Tema hijo de Hello: lectura del functions.php, bloqueos, archivos del hijo, encolado en el tema y PHP en Tools validados.\n";
