<?php
/**
 * F7a · frente A: arranque del módulo de diseño, utilidades CSS y cerrojo compartido.
 *
 * - `Css::color()`, `global_color()`, `length()` y `number()` con valores válidos e inválidos (`expression(`, `;`,
 *   `}`, `url(`…).
 * - `Css::svg_data_uri()` en porcentaje (V.5): sin `;`, `#`, llaves ni `//`; cada caso de la lista de rechazo; el tope
 *   de 8 KB; y la regla pasa por el `Stylesheet` real del núcleo y por la partición de `addRules` del editor.
 * - `Css::core_selectors()` con y sin marcado optimizado y con la capa de efectos de movimiento, sobre los controles
 *   reales del núcleo (arnés `tests/elements-design/core.php`).
 * - `Module`: sin submódulos no registra ganchos; arranca sólo los que existen; guardas de símbolos y de versión con
 *   el aviso del editor sólo para quien administra; autocarga acotada.
 * - `Lock`: `INSERT IGNORE` con token, sin reentrar, robo pasado el plazo y sólo si nadie lo cambió, nunca suelta el
 *   de otra petición.
 * - `pro-elements.php` arranca el módulo justo detrás de `Front\Module::init()`.
 */
require __DIR__ . '/elements-design/core.php';
require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-design/class-module.php';
require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-design/class-css.php';
require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-design/class-lock.php';

use Digitalisimo\Elements\Design\Css;
use Digitalisimo\Elements\Design\Module;
use Digitalisimo\Elements\Lock;

$check = 'check_design';

// ── Css::color() ─────────────────────────────────────────────────────────────────────────────────────────────────
$valid = array(
	'#fff'                          => '#fff',
	'#FFFA'                         => '#FFFA',
	' #a1b2c3 '                     => '#a1b2c3',
	'#a1b2c3d4'                     => '#a1b2c3d4',
	'rgb(1,2,3)'                    => 'rgb(1,2,3)',
	'rgba(255, 0, 0, .5)'           => 'rgba(255, 0, 0, .5)',
	'rgb(1 2 3 / 50%)'              => 'rgb(1 2 3 / 50%)',
	'hsl(120deg 50% 50%)'           => 'hsl(120deg 50% 50%)',
	'hsla(120, 50%, 50%, 0.3)'      => 'hsla(120, 50%, 50%, 0.3)',
	'transparent'                   => 'transparent',
	'CurrentColor'                  => 'currentColor',
	'var(--e-global-color-primary)' => 'var(--e-global-color-primary)',
	'var(--e-global-color-a1b2c3d)' => 'var(--e-global-color-a1b2c3d)',
);
foreach ( $valid as $raw => $expected ) {
	$check( $expected === Css::color( $raw ), 'Color válido rechazado o cambiado: ' . $raw );
}
$invalid = array( '', '#ff', '#fffff', '#ggg', 'red', 'expression(alert(1))', '#fff;', '#fff}', 'url(x.png)', 'rgb(1,2,3);color:red', 'rgb(1,2)', 'var(--x)', 'var(--e-global-color-A1)', 'var(--e-global-color-a);', 'rgba(1,2,3,url(x))', str_repeat( '#', 70 ) );
foreach ( $invalid as $raw ) {
	$check( null === Css::color( $raw ), 'Color inválido aceptado: ' . $raw );
}
$check( null === Css::color( null ) && null === Css::color( array( '#fff' ) ) && null === Css::color( 12 ), 'Un color que no es cadena se rechaza.' );

// ── Css::global_color() ──────────────────────────────────────────────────────────────────────────────────────────
$globals = array( 'title_color' => 'globals/colors?id=primary', 'bad' => 'globals/colors?id=a;b', 'type' => 'globals/typography?id=primary' );
$check( 'var(--e-global-color-primary)' === Css::global_color( $globals, 'title_color' ), 'El global de color debe dar su variable.' );
$check( 'var(--e-global-color-primary)' === Css::global_color( array( '__globals__' => $globals ), 'title_color' ), 'También desde los ajustes completos.' );
$check( null === Css::global_color( $globals, 'bad' ) && null === Css::global_color( $globals, 'type' ) && null === Css::global_color( $globals, 'none' ), 'Un global inválido, de tipografía o ausente no da color.' );

// ── Css::length() y number() ─────────────────────────────────────────────────────────────────────────────────────
$units = array( 'px', 'em', '%', 'custom' );
$lengths = array(
	array( array( 'size' => 10, 'unit' => 'px' ), '10px' ),
	array( array( 'size' => '1.50', 'unit' => 'em' ), '1.5em' ),
	array( array( 'size' => -5, 'unit' => '%' ), '-5%' ),
	array( array( 'size' => 0, 'unit' => 'px' ), '0px' ),
	array( array( 'size' => 'calc(100% - 2rem)', 'unit' => 'custom' ), 'calc(100% - 2rem)' ),
	array( array( 'size' => 'clamp(1rem, 2vw, 3rem)', 'unit' => 'custom' ), 'clamp(1rem, 2vw, 3rem)' ),
	array( '12px', '12px' ),
	array( '1e3px', '1000px' ),
);
foreach ( $lengths as $case ) {
	$check( $case[1] === Css::length( $case[0], $units ), 'Longitud válida rechazada: ' . json_encode( $case[0] ) . ' → ' . var_export( Css::length( $case[0], $units ), true ) );
}
$bad_lengths = array( array( 'size' => '', 'unit' => 'px' ), array( 'size' => 10, 'unit' => 'vh' ), array( 'size' => 'x', 'unit' => 'px' ), array( 'size' => 'url(x)', 'unit' => 'custom' ), array( 'size' => 'calc(1px)//x', 'unit' => 'custom' ), array( 'size' => 'expression(1)', 'unit' => 'custom' ), array( 'size' => 'calc(1px);color:red', 'unit' => 'custom' ), '10vh', '10px;', 'auto', 10 );
foreach ( $bad_lengths as $case ) {
	$check( null === Css::length( $case, $units ), 'Longitud inválida aceptada: ' . json_encode( $case ) );
}
$check( '10' === Css::length( 10, array( '' ) ) && '2.5' === Css::length( '2.5', array( '' ) ), 'Un número sin unidad vale si el control admite "".' );
$check( '0' === Css::number( -0.0000001 ) && '0.333333' === Css::number( 1 / 3 ) && null === Css::number( 'abc' ) && null === Css::number( true ) && null === Css::number( 1e12 ), 'number() normaliza como en JS y acota el valor.' );

// ── Css::svg_data_uri() ──────────────────────────────────────────────────────────────────────────────────────────
$svg = '<?xml version="1.0"?>' . "\n" . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">' . "\n  " . '<circle cx="5" cy="5" r="5" style="fill:#000;stroke:none"/>' . "\n" . '</svg>';
$uri = Css::svg_data_uri( $svg );
$check( is_string( $uri ) && 0 === strpos( $uri, 'url("data:image/svg+xml,' ) && '")' === substr( $uri, -2 ), 'El SVG debe salir como url("data:image/svg+xml,…").' );
$inner = substr( $uri, strlen( 'url("data:image/svg+xml,' ), -2 );
foreach ( array( ';', '#', '{', '}', '"', "'", '//', ':', ' ', '<', 'base64' ) as $needle ) {
	$check( false === strpos( $inner, $needle ), 'El SVG codificado no puede llevar «' . $needle . '»: ' . $inner );
}
$decoded = rawurldecode( $inner );
$check( 0 === strpos( $decoded, '<svg' ) && false !== strpos( $decoded, "style='fill:#000;stroke:none'" ) && false === strpos( $decoded, '"' ) && false === strpos( $decoded, "\n" ), 'Se decodifica en el SVG saneado, minimizado y con comillas simples: ' . $decoded );
$check( rawurlencode( $decoded ) === $inner, 'La codificación es rawurlencode() (la que repite design-library.mjs).' );

// La regla pasa entera por el analizador del núcleo (PHP) y por la partición de `addRules` del editor (JS).
$sheet = new \Elementor\Stylesheet();
$sheet->add_rules( '.m', '-webkit-mask-image:' . $uri . ';mask-image:' . $uri );
$sheet->add_rules( '.n', array( 'mask-image' => $uri ) );
$printed = (string) $sheet;
$check( false !== strpos( $printed, '.m{-webkit-mask-image:' . $uri . ';mask-image:' . $uri . ';}' ) && false !== strpos( $printed, '.n{mask-image:' . $uri . ';}' ), 'Stylesheet::add_rules() debe conservar la regla con el data: (cadena y array): ' . $printed );
foreach ( explode( ';', '-webkit-mask-image:' . $uri . ';mask-image:' . $uri ) as $chunk ) {
	$check( 2 === count( explode( ':', $chunk, 2 ) ), 'Cada trozo de la regla tiene propiedad y valor, como exige addRules del editor.' );
}

// Lista de rechazo, antes y después del saneado, y topes.
$hostile = array(
	'script'         => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
	'on*'            => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>',
	'on* sin comilla' => '<svg xmlns="http://www.w3.org/2000/svg"><rect onclick=alert(1) width="1"/></svg>',
	'href externo'   => '<svg xmlns="http://www.w3.org/2000/svg"><a href="https://x.test/"><rect width="1"/></a></svg>',
	'xlink externo'  => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="data:image/svg+xml;base64,AA"/></svg>',
	'foreignObject'  => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div>x</div></foreignObject></svg>',
	'image'          => '<svg xmlns="http://www.w3.org/2000/svg"><image href="#a"/></svg>',
	'@import'        => '<svg xmlns="http://www.w3.org/2000/svg"><style>@import "x.css";</style></svg>',
	'url('           => '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(#g)" width="1"/></svg>',
	'entidad'        => '<!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg"><rect width="1"/></svg>',
	'php'            => '<svg xmlns="http://www.w3.org/2000/svg"><?php echo 1; ?><rect width="1"/></svg>',
	'no es svg'      => '<html><body>x</body></html>',
	'vacío'          => '   ',
);
foreach ( $hostile as $name => $raw ) {
	$check( null === Css::svg_data_uri( $raw ), 'SVG hostil aceptado (' . $name . ').' );
}
$check( ! Css::svg_rejected( '<svg><use href="#a"/><use xlink:href="#b"/></svg>' ) && Css::svg_rejected( '<svg><use href=""/></svg>' ), 'Un href interno (#) no se rechaza; uno vacío sí.' );
$path = '<path d="' . str_repeat( 'M0 0L1 1', 1100 ) . '"/>';
$check( null === Css::svg_data_uri( '<svg xmlns="http://www.w3.org/2000/svg">' . $path . '</svg>' ), 'Un SVG de más de 8 KB saneado se rechaza.' );
$check( null === Css::svg_data_uri( '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat( ' ', Css::SVG_RAW_MAX ) . '</svg>' ), 'Un SVG recibido de más de 64 KB se rechaza sin sanearlo.' );
$spaced = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1">' . str_repeat( "\n", 9000 ) . '<rect width="1" height="1"/></svg>';
$check( null !== Css::svg_data_uri( $spaced ), 'El tope de 8 KB se mide tras minimizar los espacios.' );

// ── Css::core_selectors() sobre los controles reales ─────────────────────────────────────────────────────────────
design_boot();
$heading = design_element( array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading' ) );
$selectors = Css::core_selectors( $heading, '_background_color' );
$check( array( '{{WRAPPER}} > .elementor-widget-container' ) === $selectors, 'Sin marcado optimizado, el fondo del widget va en el contenedor interno: ' . json_encode( $selectors ) );
$check( array( '.x > .elementor-widget-container' ) === Css::core_selectors( $heading, '_background_color', '.x' ), 'Con una cadena se sustituye {{WRAPPER}}.' );
$check( array() === Css::core_selectors( $heading, 'title' ) && array() === Css::core_selectors( $heading, 'no_existe' ), 'Un control sin selectores (o inexistente) no da selectores.' );
$check( false === \Elementor\Core\Frontend\Performance::is_use_style_controls(), 'core_selectors() restaura set_use_style_controls().' );
$offset = Css::core_selectors( $heading, '_offset_x', array( '{{WRAPPER}}' => '.w', '{{ID}}' => 'h1' ) );
$check( array( 'body:not(.rtl) .w', 'body.rtl .w' ) === $offset, 'Los selectores que empiezan antes de {{WRAPPER}} se conservan: ' . json_encode( $offset ) );
$container = design_element( array( 'id' => 'c1', 'elType' => 'container' ) );
$check( array( '{{WRAPPER}}:not(.elementor-motion-effects-element-type-background), {{WRAPPER}} > .elementor-motion-effects-container > .elementor-motion-effects-layer' ) === Css::core_selectors( $container, 'background_color' ), 'El fondo del contenedor incluye la capa de efectos de movimiento.' );

design_boot( array( 'optimized' => true ) );
$optimized = design_element( array( 'id' => 'h2', 'elType' => 'widget', 'widgetType' => 'heading' ) );
$check( array( '{{WRAPPER}}' ) === Css::core_selectors( $optimized, '_background_color' ), 'Con marcado optimizado, el fondo del widget va en {{WRAPPER}}: ' . json_encode( Css::core_selectors( $optimized, '_background_color' ) ) );

$previous = \Elementor\Core\Frontend\Performance::is_use_style_controls();
try {
	Css::with_style_controls(
		function () {
			throw new RuntimeException( 'x' );
		}
	);
} catch ( RuntimeException $error ) {
	unset( $error );
}
$check( $previous === \Elementor\Core\Frontend\Performance::is_use_style_controls(), 'with_style_controls() restaura el valor aunque falle.' );

// ── Module: arranque, autocarga y guardas ────────────────────────────────────────────────────────────────────────
design_wp_reset();
$empty = sys_get_temp_dir() . '/digitalisimo-design-empty-' . getmypid();
@mkdir( $empty, 0777, true );
$check( array() === Module::load( $empty ) && array() === design_hooks(), 'Sin submódulos, el módulo no registra ningún gancho.' );
$check( '' === Module::file_for( 'Digitalisimo\Elements\Design\Presets\Store' ), 'Sin archivo, la autocarga no carga nada.' );

$fake = sys_get_temp_dir() . '/digitalisimo-design-fake-' . getmypid();
@mkdir( $fake . '/sticky', 0777, true );
file_put_contents( $fake . '/sticky/class-sticky-state.php', '<?php namespace Digitalisimo\Elements\Design\Sticky; final class Sticky_State { public static $n = 0; public static function init(): void { ++self::$n; add_action( "x/fake", "__return_true" ); } }' );
$started = Module::load( $fake );
$check( array( 'Sticky\\Sticky_State' ) === $started && 1 === \Digitalisimo\Elements\Design\Sticky\Sticky_State::$n && array( 'x/fake' => 1 ) === design_hooks(), 'Sólo arrancan los submódulos cuyo archivo existe: ' . json_encode( $started ) );
$check( $fake . '/sticky/class-sticky-state.php' === Module::file_for( '\Digitalisimo\Elements\Design\Sticky\Sticky_State' ), 'Design\Sticky\Sticky_State → sticky/class-sticky-state.php.' );
foreach ( array( 'Digitalisimo\Elements\Front\Module', 'Digitalisimo\Elements\Design\..\x', 'Digitalisimo\Elements\Design\A\B\C', 'Digitalisimo\Elements\Design\Sticky\\' ) as $outside ) {
	$check( '' === Module::file_for( $outside ), 'Un nombre fuera del módulo nunca llega al disco: ' . $outside );
}

design_wp_reset();
$check( Module::requires( 'presets', array( 'Elementor\Controls_Stack', 'Elementor\Controls_Stack::get_controls', 'Elementor\Core\Files\CSS\Post', 'add_action()' ) ) && array() === design_hooks(), 'Con todos los símbolos, la guarda pasa sin registrar nada.' );
$check( ! Module::requires( 'backgrounds', array( 'Elementor\No_Existe', 'Elementor\Controls_Stack::no_existe', 'no_existe()' ) ), 'Sin un símbolo, la guarda falla.' );
$check( array( 'backgrounds' => array( 'Elementor\No_Existe', 'Elementor\Controls_Stack::no_existe', 'no_existe()' ) ) === Module::missing(), 'Se anotan los símbolos que faltan.' );
$check( array( 'elementor/editor/after_enqueue_scripts' => 1 ) === design_hooks(), 'El aviso del editor se engancha una sola vez.' );
Module::requires( 'sticky', array( 'Otro\Que\Falta' ) );
$check( array( 'elementor/editor/after_enqueue_scripts' => 1 ) === design_hooks(), 'Una segunda guarda fallida no duplica el aviso.' );
$GLOBALS['dh']['users'][1]['caps'] = array( 'edit_posts' );
Module::editor_notice();
$check( array() === $GLOBALS['dh']['inline'], 'Quien no administra no ve el aviso.' );
$GLOBALS['dh']['users'][1]['caps'] = array( 'manage_options' );
Module::editor_notice();
$notice = $GLOBALS['dh']['inline'][0] ?? array( '', '' );
$check( 'elementor-editor' === $notice[0] && false !== strpos( $notice[1], 'backgrounds, sticky' ) && false !== strpos( $notice[1], 'showToast' ), 'Quien administra ve el aviso en el editor: ' . json_encode( $notice ) );
preg_match( '/\}\)\((".*")\);$/', $notice[1], $message );
$check( isset( $message[1] ) && false === stripos( json_decode( $message[1] ), 'elementor' ), 'El texto visible del aviso no nombra el constructor original.' );
$check( Module::core_supported() && Module::version_supported( '4.3.0-beta1' ) && Module::version_supported( '5.0' ) && ! Module::version_supported( '4.2.9' ) && ! Module::version_supported( '3.35' ) && ! Module::version_supported( 'x' ), 'La guarda de versión compara mayor y menor (≥ 4.3).' );

// ── Lock ─────────────────────────────────────────────────────────────────────────────────────────────────────────
design_wp_reset();
$name = Lock::PREFIX . 'prueba';
$check( Lock::acquire( $name, 30 ) && Lock::held( $name ), 'El cerrojo libre se toma.' );
$row = $GLOBALS['dh']['options'][ $name ] ?? null;
$check( is_array( $row ) && 'off' === $row['autoload'] && preg_match( '/^\d+\|[A-Za-z0-9]{20}$/', $row['value'] ), 'Se guarda <time>|<token> sin carga automática: ' . json_encode( $row ) );
$check( ! Lock::acquire( $name, 30 ), 'Una petición no reentra en su propio cerrojo.' );
Lock::release( $name );
$check( ! isset( $GLOBALS['dh']['options'][ $name ] ) && ! Lock::held( $name ), 'release() borra su fila.' );

$GLOBALS['dh']['options'][ $name ] = array( 'value' => time() . '|ajeno', 'autoload' => 'off' );
$check( ! Lock::acquire( $name, 30 ), 'Un cerrojo ajeno reciente no se roba.' );
Lock::release( $name );
$check( time() . '|ajeno' === $GLOBALS['dh']['options'][ $name ]['value'], 'Nunca se suelta el cerrojo de otra petición.' );
$GLOBALS['dh']['options'][ $name ] = array( 'value' => ( time() - 31 ) . '|ajeno', 'autoload' => 'off' );
$check( Lock::acquire( $name, 30 ) && false === strpos( $GLOBALS['dh']['options'][ $name ]['value'], 'ajeno' ), 'Pasado el plazo, el cerrojo abandonado se roba.' );
Lock::release( $name );

// El robo sólo vale si nadie cambió la fila entre la lectura y el `UPDATE`.
$GLOBALS['dh']['options'][ $name ] = array( 'value' => ( time() - 31 ) . '|ajeno', 'autoload' => 'off' );
$GLOBALS['wpdb'] = new class() extends Design_WPDB {
	public function get_var( $sql ) {
		$value = parent::get_var( $sql );
		$GLOBALS['dh']['options'][ Lock::PREFIX . 'prueba' ]['value'] = time() . '|otro';
		return $value;
	}
};
$check( ! Lock::acquire( $name, 30 ), 'Si otra petición lo robó antes, el UPDATE no coincide y no se toma.' );
$GLOBALS['wpdb'] = new Design_WPDB();
unset( $GLOBALS['dh']['options'][ $name ] );

$ran = Lock::run(
	$name,
	function () use ( $name ) {
		return Lock::held( $name ) ? 'dentro' : 'fuera';
	}
);
$check( 'dentro' === $ran && ! isset( $GLOBALS['dh']['options'][ $name ] ), 'run() ejecuta bajo el cerrojo y lo suelta.' );
$GLOBALS['dh']['options'][ $name ] = array( 'value' => time() . '|ajeno', 'autoload' => 'off' );
$check( 'ocupado' === Lock::run( $name, '__return_true', 30, 'ocupado' ), 'run() devuelve el valor de ocupado si el cerrojo está tomado.' );
unset( $GLOBALS['dh']['options'][ $name ] );
Lock::acquire( $name );
Lock::acquire( Lock::PREFIX . 'otro' );
Lock::release_all();
$check( ! isset( $GLOBALS['dh']['options'][ $name ] ) && ! isset( $GLOBALS['dh']['options'][ Lock::PREFIX . 'otro' ] ), 'release_all() suelta todos los cerrojos propios.' );

// ── Arranque en pro-elements.php ─────────────────────────────────────────────────────────────────────────────────
$boot = file_get_contents( __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
$front = strpos( $boot, '\Digitalisimo\Elements\Front\Module::init();' );
$design = strpos( $boot, "require_once __DIR__ . '/modules/digitalisimo-design/class-module.php';" );
$init = strpos( $boot, '\Digitalisimo\Elements\Design\Module::init();' );
$check( false !== $front && false !== $design && false !== $init && $front < $design && $design < $init && 1 === substr_count( $boot, 'Design\Module::init()' ), 'pro-elements.php arranca el módulo de diseño una vez, justo detrás de Front\Module::init().' );
$between = substr( $boot, $front + strlen( '\Digitalisimo\Elements\Front\Module::init();' ), $design - $front - strlen( '\Digitalisimo\Elements\Front\Module::init();' ) );
$check( '' === trim( preg_replace( '#^\s*//.*$#m', '', $between ) ), 'Entre el arranque de Front y el de diseño sólo hay un comentario.' );
foreach ( array( 'class-module.php', 'class-css.php', 'class-lock.php' ) as $file ) {
	$source = file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-design/' . $file );
	$check( false !== strpos( $source, "defined( 'ABSPATH' ) || exit;" ), $file . ' debe salir si se abre directamente.' );
	$check( ! preg_match( '/\b(str_contains|str_starts_with|str_ends_with|match\s*\()/', $source ), $file . ' debe ser compatible con PHP 7.4.' );
}

echo "F7a · frente A: utilidades CSS (colores, longitudes, SVG en porcentaje por el Stylesheet real), selectores del núcleo, arranque sin submódulos, guardas, cerrojo y arranque en verde.\n";
