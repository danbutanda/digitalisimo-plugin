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
check( array( 'tp-fonts', 'tp-main', 'tp-print', 'tp-parent', 'tp-js', 'tp-defer', 'tp-falta', 'tp-solo' ) === array_keys( $by ), 'Ocho entradas, en orden, con el archivo que falta (su handle existía con Hello) y sin el encolado repetido: ' . implode( ',', array_keys( $by ) ) );
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
	"add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'a', 'https://x.test/a.css' ) || error_log( 'x' ); } );" => "wp_enqueue_style( 'a' )",
);
foreach ( $bad as $code => $expected ) {
	$plan = plan_of( "<?php\n" . $code . "\n" );
	check( $plan['blockers'] && false !== strpos( implode( ' | ', whats( $plan ) ), $expected ), "Bloquea «{$code}» como «{$expected}»: " . implode( ' | ', whats( $plan ) ) );
	check( array() === $plan['assets'] || "add_action( 'wp_enqueue_scripts', function () { wp_dequeue_style( 'hello-elementor' ); } );" === $code, "Nada que traer de «{$code}»." );
}
// Un atributo de PHP 8 (`#[`) dentro de una función enganchada no desarma la lectura del resto del cuerpo.
if ( PHP_VERSION_ID >= 80000 ) {
	$plan = plan_of( "<?php\nfunction f() {\n\t\$a = #[X] fn() => 1;\n\twp_enqueue_style( 'a', 'https://x.test/a.css' );\n}\nadd_action( 'wp_enqueue_scripts', 'f' );\n" );
	check( array( 'a' ) === array_column( $plan['assets'], 'h' ) && 1 === count( $plan['blockers'] ) && 3 === $plan['blockers'][0]['line'], 'Con un atributo de PHP 8 en el cuerpo, la línea del atributo bloquea y el encolado de después se lee: ' . json_encode( $plan ) );
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
check( isset( $GLOBALS['styles']['tp-ido'] ) && false === $GLOBALS['styles']['tp-ido'][0], 'Un archivo que ya no está queda como alias sin archivo (sin enlace roto y sin perder lo que depende de él).' );
check( 'https://sitio.test/wp-content/themes/hello-elementor/style.css' === $GLOBALS['styles']['tp-reg'][0] && 'https://fonts.example.test/css?family=A' === $GLOBALS['styles']['tp-fonts'][0] && array( 'https://sitio.test/wp-content/themes/hijo/style.css', array( 'jquery' ), '3', true ) === $GLOBALS['scripts']['tp-js'], 'Archivo de Hello, dirección externa y script con sus datos.' );
$GLOBALS['actions']['wp_enqueue_scripts'][20][0]();
check( array( 'style:tp-fonts', 'style:hello-elementor-child-style', 'style:tp-ido', 'script:tp-js' ) === $GLOBALS['queue'], 'En la 20, el style.css y el script; lo sólo registrado no se encola: ' . implode( ',', $GLOBALS['queue'] ) );
$GLOBALS['options']['digitalisimo_theme_child']['assets'][0]['s'] = '../hello-elementor/style.css';
check( '' === digitalisimo_theme_child_asset_url( $GLOBALS['options']['digitalisimo_theme_child'], $GLOBALS['options']['digitalisimo_theme_child']['assets'][0] ), 'Una ruta con «..» guardada no se sirve.' );

// ------------------------------------------------------------------ 5b. Aviso en Temas y bitácora al borrar el hijo
$GLOBALS['screen'] = 'themes';
function get_current_screen() {
	return (object) array( 'id' => $GLOBALS['screen'] );
}
function current_user_can( $cap ) {
	return true;
}
function __( $text, $domain = '' ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function digitalisimo_theme_switch_log() {
	return $GLOBALS['options']['digitalisimo_theme_switch_log'] ?? array();
}
function digitalisimo_theme_merge_warnings( $previous, array $current ) {
	return array_merge( (array) $previous, $current );
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['options'][ $key ] = $value;
}
$GLOBALS['options']['digitalisimo_theme_child']['assets'][0]['s'] = 'style.css';
ob_start();
digitalisimo_theme_child_notice();
check( false !== strpos( ob_get_clean(), 'notice-info' ), 'En Temas, con el hijo en su carpeta: no lo borres.' );
digitalisimo_theme_child_deleted( 'otro', true );
check( ! isset( $GLOBALS['options']['digitalisimo_theme_switch_log'] ), 'Borrar otro tema no anota nada.' );
rename( $GLOBALS['root'] . '/hijo', $GLOBALS['root'] . '/hijo-movido' );
digitalisimo_theme_child_deleted( 'hijo', true );
check( 'child_deleted' === ( $GLOBALS['options']['digitalisimo_theme_switch_log']['warnings'][0]['code'] ?? '' ), 'Borrar el hijo cuyas hojas carga el tema queda en la bitácora.' );
ob_start();
digitalisimo_theme_child_notice();
check( false !== strpos( ob_get_clean(), 'notice-error' ), 'En Temas, sin el hijo: aviso de error.' );
$GLOBALS['screen'] = 'dashboard';
ob_start();
digitalisimo_theme_child_notice();
check( '' === ob_get_clean(), 'Fuera de Temas no avisa.' );
$GLOBALS['options']['digitalisimo_theme_switch'] = array();
$GLOBALS['screen'] = 'themes';
ob_start();
digitalisimo_theme_child_notice();
check( '' === ob_get_clean(), 'Sin «carry» no avisa.' );
rename( $GLOBALS['root'] . '/hijo-movido', $GLOBALS['root'] . '/hijo' );

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
$wrapped = digitalisimo_theme_child_wrap_php( $code, 'Hijo' );
check( $wrapped === $item['code'] && 'digitalisimo-theme' === $item['origin']['source'] && 'hijo' === $item['origin']['id'] && ! isset( $item['status'] ) && false !== strpos( $item['notes'][0], "functions.php, línea 3: add_action( 'wp_head' )" ) && 'child-' . hash( 'sha256', $wrapped ) === $item['origin']['hash'] && false !== strpos( $item['notes'][1], 'puedes activarlo antes de cambiar de tema' ), 'El fragmento lleva el functions.php normalizado dentro de la condición del tema propio, su origen y lo que no se trajo, sin estado (Tools lo deja inactivo).' );
// La condición: con el hijo cargado (sin el tema propio) no declara nada, así que no choca; con el tema propio, corre.
$dir = $GLOBALS['root'] . '/wrap';
@mkdir( $dir, 0777, true );
$sample = "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\nfunction tp_named() { return 'ok'; }\nadd_action( 'wp_head', 'tp_named' );\n";
file_put_contents( $dir . '/child.php', $sample );
file_put_contents( $dir . '/wrap.php', digitalisimo_theme_child_wrap_php( $sample, 'Hijo' ) );
$stub = "define( 'ABSPATH', '/' ); function add_action() {} ";
$run  = static function ( $php ) {
	exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $php ) . ' 2>&1', $out, $status );
	return array( $status, implode( "\n", $out ) );
};
list( $st, $out ) = $run( $stub . "require '{$dir}/child.php'; require '{$dir}/wrap.php'; echo function_exists( 'tp_named' ) ? 'hijo' : 'x';" );
check( 0 === $st && 'hijo' === $out, 'Con el hijo cargado, el fragmento con la condición no declara nada ni choca: ' . $out );
list( $st, $out ) = $run( $stub . "function digitalisimo_theme_parity() { return true; } require '{$dir}/wrap.php'; echo tp_named();" );
check( 0 === $st && 'ok' === $out, 'Con el tema propio, el fragmento corre: ' . $out );
list( $st, $out ) = $run( $stub . "require '{$dir}/child.php'; require '{$dir}/child.php';" );
check( 0 !== $st && false !== stripos( $out, 'redeclare' ), 'Sin la condición, cargarlo con el hijo choca (la falla que la condición evita).' );
foreach ( array( "<?php\nnamespace X;\nfunction a() {}\n", "<?php\nuse X\\Y;\n", "<?php\ndeclare( strict_types=1 );\n", "<?php\nconst A = 1;\n" ) as $no ) {
	check( '' === digitalisimo_theme_child_wrap_php( $no, 'Hijo' ), 'No admite la condición: ' . $no );
}
check( '' !== digitalisimo_theme_child_wrap_php( "<?php\nclass Z { const A = 1; }\n", 'Hijo' ), 'Una constante de clase no impide la condición.' );
$html = digitalisimo_theme_child_wrap_php( "<?php\nfunction b() {}\n?>\n<p>x</p>\n", 'Hijo' );
file_put_contents( $dir . '/html.php', $html );
list( $st, $out ) = $run( "require '{$dir}/html.php'; echo 'fin';" );
check( "<?php\n}\n" === substr( $html, -8 ) && 0 === $st && 'fin' === $out, 'Con HTML al final, la condición cierra en PHP y el archivo es válido: ' . $out );
check( ! digitalisimo_theme_child_php_covered( $child ), 'Sin fuentes de Tools no está cubierto.' );
$GLOBALS['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) use ( $code ) {
	return array_merge( $rows, array( array( 'active' => false, 'hash' => hash( 'sha256', $code ) ), array( 'active' => true, 'hash' => hash( 'sha256', $code . ' ' ) ) ) );
};
check( ! digitalisimo_theme_child_php_covered( $child ), 'Inactivo, o activo con otro código, no lo cubre.' );
$GLOBALS['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) use ( $code ) {
	return array_merge( $rows, array( array( 'active' => true, 'hash' => hash( 'sha256', $code ) ) ) );
};
check( digitalisimo_theme_child_php_covered( $child ), 'Activo y sin cambios en Tools: cubierto.' );

// Lo que dentro de la condición, con el tema propio, haría otra cosa: se marca (y el hijo se queda), con su línea.
$official = "<?php\ndefine( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );\nfunction hello_elementor_child_scripts_styles() {\n\twp_enqueue_style( 'hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', [ 'hello-elementor-theme-style' ], HELLO_ELEMENTOR_CHILD_VERSION );\n}\nadd_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );\n";
check( array() === plan_of( $official )['risks'] && array() === plan_of( $official . "add_action( 'wp_head', function () { echo '<style>.x{}</style>'; } );\n" )['risks'], 'La plantilla oficial (con su ruta del tema en lo que ya se trae y su propia constante) no tiene riesgos.' );
$risky = array(
	'constante de Hello'      => array( "add_action( 'wp_head', function () { echo HELLO_ELEMENTOR_VERSION; } );\n", '2:usa la constante HELLO_ELEMENTOR_VERSION de Hello' ),
	'constante de Hello leída' => array( "function a() {\n\twp_enqueue_style( 'x', get_stylesheet_directory_uri() . '/x.css', [], HELLO_ELEMENTOR_VERSION );\n}\nadd_action( 'wp_enqueue_scripts', 'a' );\n", '3:usa la constante HELLO_ELEMENTOR_VERSION de Hello' ),
	'función de Hello'        => array( "add_action( 'init', function () { hello_elementor_body_open(); } );\n", '2:llama a hello_elementor_body_open(), de Hello' ),
	'ruta del tema'           => array( "require get_stylesheet_directory() . '/inc/x.php';\n", '2:usa get_stylesheet_directory(), que con el tema DIGITALÍSIMO apunta a su carpeta' ),
	'ruta en un gancho'       => array( "add_action( 'wp_footer', function () {\n\techo '<img src=\"' . get_stylesheet_directory_uri() . '/a.png\">';\n} );\n", '3:usa get_stylesheet_directory_uri()' ),
	'ruta en un if'           => array( "function a() {\n\tif ( is_page() ) {\n\t\twp_enqueue_style( 'child-landing', get_stylesheet_directory_uri() . '/css/landing.css' );\n\t}\n}\nadd_action( 'wp_enqueue_scripts', 'a' );\n", '4:usa get_stylesheet_directory_uri()' ),
	'llamada antes'           => array( "child_boot();\nfunction child_boot() { add_action( 'wp_head', 'x' ); }\n", '2:llama a child_boot() antes de declararla' ),
	'clase antes'             => array( "class Hija extends Base {}\nclass Base {}\n", '2:la clase Hija extiende Base, declarada más abajo' ),
	'objeto antes'            => array( "\$o = new Cosa();\nclass Cosa {}\n", '2:crea un Cosa antes de declarar la clase' ),
	'global de primer nivel'  => array( "\$tp_opts = array( 1 );\nfunction tp() { global \$tp_opts; return \$tp_opts; }\nadd_action( 'wp_head', 'tp' );\n", '3:usa global $tp_opts' ),
);
foreach ( $risky as $label => $case ) {
	$found = array_map( static function ( $b ) {
		return $b['line'] . ':' . $b['what'];
	}, plan_of( "<?php\n" . $case[0] )['risks'] );
	check( 1 === count( $found ) && 0 === strpos( $found[0], $case[1] ), "Riesgo «{$label}»: " . json_encode( $found ) );
}
check( array() === plan_of( "<?php\nfunction child_boot() {}\nchild_boot();\nadd_action( 'init', function () { otra(); } );\nfunction otra() {}\nclass Base {}\nclass Hija extends Base {}\n" )['risks'], 'Declarada antes, o usada dentro de una función, no es riesgo.' );
// Por qué: dentro de la condición, una función usada antes de su línea todavía no existe (fuera de ella sí).
file_put_contents( $dir . '/early.php', digitalisimo_theme_child_wrap_php( "<?php\nchild_boot();\nfunction child_boot() {}\n", 'Hijo' ) );
list( $st, $out ) = $run( "function digitalisimo_theme_parity() {} require '{$dir}/early.php'; echo 'fin';" );
check( 0 !== $st && false !== stripos( $out, 'undefined function' ), 'Dentro de la condición, la llamada anterior a la declaración es un error fatal: ' . $out );
$GLOBALS['imports'] = array();
check( '' === digitalisimo_theme_child_import_php( $child, array( 'blockers' => array( array( 'file' => 'functions.php', 'line' => 3, 'what' => "add_action( 'wp_head' )" ) ), 'risks' => array( array( 'file' => 'functions.php', 'line' => 3, 'what' => 'x' ) ), 'assets' => array() ) ) && array() === $GLOBALS['imports'], 'Con riesgos no se importa nada.' );
// Un cierre de PHP en el nombre del tema no puede cerrar el comentario de la envoltura.
file_put_contents( $dir . '/name.php', digitalisimo_theme_child_wrap_php( "<?php\nfunction b2() {}\n", 'Mi tema ?> <b>x</b> <?php echo 1;' ) );
list( $st, $out ) = $run( "require '{$dir}/name.php'; echo 'fin';" );
check( 0 === $st && 'fin' === $out, 'Con un cierre de PHP en el nombre, la envoltura no imprime nada: ' . $out );

echo "Tema hijo de Hello: lectura del functions.php, bloqueos, archivos del hijo, encolado en el tema y PHP en Tools validados.\n";
