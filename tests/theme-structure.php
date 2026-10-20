<?php
/**
 * Tema DIGITALÍSIMO: árbol cerrado, cabecera, ganchos de las plantillas, carga de functions.php, ubicaciones del
 * constructor, guardas, estáticas por tokens en el tema y los siete plugins, base.css, hojas de paridad, cadenas,
 * traducciones, dominios de origen, puente con los filtros de Hello, alias de sus hojas y destino del salto.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );

$root  = dirname( __DIR__ );
$theme = $root . '/digitalisimo-theme';

function check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
}

/** Archivos del tema (rutas relativas, ordenadas). */
function theme_files( $theme ) {
	$files = array();
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( $file->isFile() ) {
			$files[] = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $theme ) + 1 ) );
		}
	}
	sort( $files );
	return $files;
}

/** Tokens sin espacios ni comentarios. */
function code_tokens( $source ) {
	$out = array();
	foreach ( token_get_all( $source ) as $token ) {
		if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		$out[] = $token;
	}
	return $out;
}

function token_text( $token ) {
	return is_array( $token ) ? $token[1] : $token;
}

/** Valor de un literal de cadena ('x' o "x" sin variables), o null. */
function literal( $token ) {
	if ( ! is_array( $token ) || T_CONSTANT_ENCAPSED_STRING !== $token[0] ) {
		return null;
	}
	return eval( 'return ' . $token[1] . ';' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- sólo literales del propio repositorio.
}

/** Tokens de código con la función que los contiene ('' fuera de funciones con nombre). */
function owned_tokens( $source ) {
	$tokens  = code_tokens( $source );
	$out     = array();
	$stack   = array(); // [ nombre, profundidad de llaves ]
	$depth   = 0;
	$pending = null;
	foreach ( $tokens as $i => $token ) {
		if ( is_array( $token ) && T_FUNCTION === $token[0] && isset( $tokens[ $i + 1 ] ) && is_array( $tokens[ $i + 1 ] ) && T_STRING === $tokens[ $i + 1 ][0] ) {
			$pending = $tokens[ $i + 1 ][1];
		}
		if ( '{' === $token || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
			$depth++;
			if ( null !== $pending && '{' === $token ) {
				$stack[] = array( $pending, $depth );
				$pending = null;
			}
		} elseif ( '}' === $token ) {
			if ( $stack && end( $stack )[1] === $depth ) {
				array_pop( $stack );
			}
			$depth--;
		} elseif ( ';' === $token ) {
			$pending = null; // Declaración abstracta o de interfaz.
		}
		$out[] = array( $token, $stack ? end( $stack )[0] : '' );
	}
	return $out;
}

/**
 * Llamadas a funciones: array( name, line, args (lista de listas de tokens), function (función que la contiene) ).
 * No cuenta definiciones (`function x(`) ni métodos (`->x(`, `::x(`).
 */
function function_calls( $source ) {
	$owned = owned_tokens( $source );
	$calls = array();
	$count = count( $owned );
	$names = array( T_STRING, defined( 'T_NAME_FULLY_QUALIFIED' ) ? T_NAME_FULLY_QUALIFIED : -1 );
	$skip  = array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ? T_NULLSAFE_OBJECT_OPERATOR : -1 );
	for ( $i = 0; $i < $count; $i++ ) {
		list( $token, $owner ) = $owned[ $i ];
		if ( ! is_array( $token ) || ! in_array( $token[0], $names, true ) || ! isset( $owned[ $i + 1 ] ) || '(' !== $owned[ $i + 1 ][0] ) {
			continue;
		}
		$prev = $owned[ $i - 1 ][0] ?? null;
		if ( is_array( $prev ) && in_array( $prev[0], $skip, true ) ) {
			continue;
		}
		$args  = array( array() );
		$level = 0;
		for ( $j = $i + 2; $j < $count; $j++ ) {
			$t = $owned[ $j ][0];
			if ( in_array( $t, array( '(', '[', '{' ), true ) ) {
				$level++;
			} elseif ( in_array( $t, array( ')', ']', '}' ), true ) ) {
				if ( 0 === $level ) {
					break;
				}
				$level--;
			} elseif ( ',' === $t && 0 === $level ) {
				$args[] = array();
				continue;
			}
			$args[ count( $args ) - 1 ][] = $t;
		}
		$calls[] = array(
			'name'     => ltrim( $token[1], '\\' ),
			'line'     => $token[2],
			'args'     => $args,
			'function' => $owner,
		);
	}
	return $calls;
}

/** Primer argumento de una llamada si es un literal, o null. */
function first_literal( array $call ) {
	$arg = $call['args'][0] ?? array();
	return 1 === count( $arg ) ? literal( $arg[0] ) : null;
}

// ---------------------------------------------------------------------------------------------------------------
// 1. Árbol de §7.1: está completo y no hay nada más.
$expected = array(
	'style.css', 'theme.json', 'functions.php', 'header.php', 'footer.php', 'index.php', 'comments.php', 'sidebar.php',
	'screenshot.png', 'readme.txt', 'license.txt',
	'inc/setup.php', 'inc/template-tags.php', 'inc/elements.php', 'inc/assets.php', 'inc/fallbacks.php', 'inc/switch.php', 'inc/cli.php', 'inc/class-updater.php',
	'template-parts/header.php', 'template-parts/footer.php', 'template-parts/single.php', 'template-parts/archive.php', 'template-parts/search.php', 'template-parts/404.php',
	'fallback/canvas.php', 'fallback/full-width.php',
	'assets/css/base.css', 'assets/css/parity/reset.css', 'assets/css/parity/theme.css', 'assets/css/parity/header-footer.css',
	'languages/digitalisimo-theme.pot', 'languages/es_MX.l10n.php', 'languages/es_ES.l10n.php',
);
sort( $expected );
$files = array_values( array_filter( theme_files( $theme ), function ( $file ) {
	return '.DS_Store' !== basename( $file );
} ) );
check( array() === array_diff( $expected, $files ), 'Faltan archivos del tema: ' . implode( ', ', array_diff( $expected, $files ) ) );
check( array() === array_diff( $files, $expected ), 'Archivos fuera del árbol del tema: ' . implode( ', ', array_diff( $files, $expected ) ) );
foreach ( $files as $file ) {
	check( ! preg_match( '/\.js$/i', $file ), 'El tema no lleva JavaScript: ' . $file );
	check( ! preg_match( '#(^|/)(woocommerce|templates|parts|block-templates|block-template-parts)/#', $file ), 'Carpeta de plantillas prohibida: ' . $file );
	check( ! preg_match( '#^sidebar-[^/]*\.php$#', $file ), 'Barra lateral prohibida: ' . $file );
	if ( false === strpos( $file, '/' ) && '.php' === substr( $file, -4 ) ) {
		check( in_array( $file, array( 'index.php', 'header.php', 'footer.php', 'comments.php', 'functions.php', 'sidebar.php' ), true ), 'PHP prohibido en la raíz: ' . $file );
	}
}
foreach ( array( 'page.php', 'single.php', 'singular.php', 'archive.php', 'search.php', 'home.php', 'front-page.php', '404.php', 'woocommerce.php' ) as $forbidden ) {
	check( ! file_exists( $theme . '/' . $forbidden ), 'La raíz no puede tener ' . $forbidden . ': index.php despacha todo.' );
}
ob_start();
include $theme . '/sidebar.php';
check( '' === ob_get_clean(), 'sidebar.php no imprime nada.' );

// 2. Cabecera de style.css (líneas « * Clave: valor»), sin «Template:».
$style   = file_get_contents( $theme . '/style.css' );
$headers = array();
preg_match_all( '/^ \* ([A-Za-z ]+): (.+)$/m', $style, $found, PREG_SET_ORDER );
foreach ( $found as $row ) {
	$headers[ $row[1] ] = trim( $row[2] );
}
check( ! preg_match( '/^\s*\*?\s*Template\s*:/mi', $style ), 'style.css no declara «Template:»: el tema nunca es hijo.' );
check( 'DIGITALÍSIMO' === ( $headers['Theme Name'] ?? '' ) && 'GPL-3.0-or-later' === ( $headers['License'] ?? '' ) && 'digitalisimo-theme' === ( $headers['Text Domain'] ?? '' ), 'Nombre, licencia y dominio del tema.' );
check( 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme' === ( $headers['Update URI'] ?? '' ), 'Update URI del tema.' );
check( '6.8' === ( $headers['Requires at least'] ?? '' ) && '7.4' === ( $headers['Requires PHP'] ?? '' ) && '/languages' === ( $headers['Domain Path'] ?? '' ), 'Requisitos y carpeta de traducciones.' );
check( false === strpos( preg_replace( '#^/\*.*?\*/#s', '', $style ), '{' ), 'style.css sólo lleva la cabecera.' );
preg_match( "/define\( 'DIGITALISIMO_THEME_VERSION', '([^']+)' \)/", file_get_contents( $theme . '/functions.php' ), $constant );
check( isset( $headers['Version'], $constant[1] ) && $headers['Version'] === $constant[1], 'DIGITALISIMO_THEME_VERSION coincide con « * Version:».' );

// 3. Un wp_head(), un wp_body_open() y un wp_footer() por documento; sin <title> ni get_stylesheet_uri.
$documents = array(
	'header.php + footer.php' => file_get_contents( $theme . '/header.php' ) . file_get_contents( $theme . '/footer.php' ),
	'fallback/canvas.php'     => file_get_contents( $theme . '/fallback/canvas.php' ),
);
foreach ( $documents as $label => $source ) {
	$names = array_count_values( array_column( function_calls( $source ), 'name' ) );
	foreach ( array( 'wp_head', 'wp_body_open', 'wp_footer' ) as $hook ) {
		check( 1 === ( $names[ $hook ] ?? 0 ), "{$label}: exactamente un {$hook}()." );
	}
}
foreach ( $files as $file ) {
	if ( '.php' !== substr( $file, -4 ) ) {
		continue;
	}
	$source = file_get_contents( $theme . '/' . $file );
	check( false === stripos( $source, '<title' ), "{$file} no imprime <title>: lo pone title-tag." );
	check( ! in_array( 'get_stylesheet_uri', array_column( function_calls( $source ), 'name' ), true ), "{$file} no usa get_stylesheet_uri()." );
}
$full_width = array_column( function_calls( file_get_contents( $theme . '/fallback/full-width.php' ) ), 'name' );
check( in_array( 'get_header', $full_width, true ) && in_array( 'get_footer', $full_width, true ), 'El respaldo de ancho completo usa la cabecera y el pie del tema.' );

// 4. Los 12 ganchos de §4.7, cada uno una vez por archivo y en su sitio.
$hook_map = array(
	'header.php'                  => array( 'digitalisimo_before_header', 'digitalisimo_header', 'digitalisimo_after_header' ),
	'footer.php'                  => array( 'digitalisimo_before_footer', 'digitalisimo_footer', 'digitalisimo_after_footer' ),
	'template-parts/single.php'   => array( 'digitalisimo_before_main_content', 'digitalisimo_before_entry_title', 'digitalisimo_after_entry_title', 'digitalisimo_after_entry_content', 'digitalisimo_before_comments', 'digitalisimo_after_main_content' ),
	'template-parts/archive.php'  => array( 'digitalisimo_before_main_content', 'digitalisimo_before_entry_title', 'digitalisimo_after_entry_title', 'digitalisimo_after_main_content' ),
	'template-parts/search.php'   => array( 'digitalisimo_before_main_content', 'digitalisimo_before_entry_title', 'digitalisimo_after_entry_title', 'digitalisimo_after_main_content' ),
	'template-parts/404.php'      => array( 'digitalisimo_before_main_content', 'digitalisimo_after_main_content' ),
);
$all_hooks = array();
foreach ( $files as $file ) {
	if ( '.php' !== substr( $file, -4 ) ) {
		continue;
	}
	$fired = array();
	foreach ( function_calls( file_get_contents( $theme . '/' . $file ) ) as $call ) {
		$hook = 'do_action' === $call['name'] ? first_literal( $call ) : null;
		if ( is_string( $hook ) && 0 === strpos( $hook, 'digitalisimo_' ) && 0 !== strpos( $hook, 'digitalisimo_theme_' ) ) {
			$fired[] = $hook;
		}
	}
	$expected_hooks = $hook_map[ $file ] ?? array();
	$counts         = array_count_values( $fired );
	check( array() === array_diff( $expected_hooks, $fired ) && array() === array_diff( $fired, $expected_hooks ), "{$file}: ganchos " . implode( ', ', $fired ) . ' (se esperaban ' . implode( ', ', $expected_hooks ) . ').' );
	foreach ( $counts as $hook => $times ) {
		check( 1 === $times, "{$file}: {$hook} aparece {$times} veces." );
	}
	$all_hooks = array_merge( $all_hooks, $fired );
}
check( 12 === count( array_unique( $all_hooks ) ), 'Existen los 12 ganchos de la API (' . count( array_unique( $all_hooks ) ) . ').' );

// 5. functions.php con stubs: prioridades y el actualizador fuera del front.
$GLOBALS['hooks']   = array();
$GLOBALS['options'] = array();
$GLOBALS['did']     = array();
$GLOBALS['styles']  = array();
$GLOBALS['printed'] = '';
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = array( 'cb' => $callback, 'priority' => $priority, 'args' => $args );
	return true;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $hook, $callback, $priority, $args );
}
function sorted_hooks( $hook ) {
	$list = $GLOBALS['hooks'][ $hook ] ?? array();
	usort( $list, function ( $a, $b ) {
		return $a['priority'] <=> $b['priority'];
	} );
	return $list;
}
function apply_filters( $hook, $value, ...$rest ) {
	foreach ( sorted_hooks( $hook ) as $h ) {
		$value = call_user_func_array( $h['cb'], array_slice( array_merge( array( $value ), $rest ), 0, $h['args'] ) );
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['did'][ $hook ] = ( $GLOBALS['did'][ $hook ] ?? 0 ) + 1;
	foreach ( sorted_hooks( $hook ) as $h ) {
		call_user_func_array( $h['cb'], array_slice( $args, 0, $h['args'] ) );
	}
}
function did_action( $hook ) {
	return $GLOBALS['did'][ $hook ] ?? 0;
}
function hooked( $hook, $callback ) {
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $h ) {
		if ( $h['cb'] === $callback ) {
			return $h['priority'];
		}
	}
	return null;
}
function __return_false() {
	return false;
}
function __return_true() {
	return true;
}
function get_template_directory() {
	return $GLOBALS['theme'];
}
function get_template_directory_uri() {
	return 'https://sitio.test/wp-content/themes/digitalisimo-theme';
}
function is_admin() {
	return false;
}
function wp_doing_cron() {
	return false;
}
function get_option( $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default;
}
function is_singular() {
	return false;
}
function esc_url( $url ) {
	return $url;
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_html__( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function __( $text ) {
	return $text;
}
function wp_style_is( $handle, $list = 'enqueued' ) {
	$style = $GLOBALS['styles'][ $handle ] ?? null;
	return $style && ( 'registered' === $list || ! empty( $style['enqueued'] ) );
}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	$GLOBALS['styles'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'enqueued' => true );
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['styles'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'enqueued' => false );
	return true;
}
function wp_add_inline_style( $handle, $css ) {
	$GLOBALS['styles'][ $handle ]['inline'][] = $css;
	return true;
}
require $theme . '/functions.php';
check( '1.0.0' === DIGITALISIMO_THEME_VERSION, 'Versión inicial del tema.' );
check( 10 === hooked( 'digitalisimo_header', 'digitalisimo_theme_header' ) && 10 === hooked( 'digitalisimo_footer', 'digitalisimo_theme_footer' ), 'Cabecera y pie de respaldo en la prioridad 10.' );
check( 10 === hooked( 'elementor/theme/register_locations', 'digitalisimo_theme_register_locations' ), 'Ubicaciones en la prioridad 10 (antes del 99 del constructor).' );
check( 10 === hooked( 'wp_enqueue_scripts', 'digitalisimo_theme_enqueue_styles' ), 'Estilos en la prioridad 10.' );
check( 20 === hooked( 'after_switch_theme', 'digitalisimo_theme_after_switch' ), 'Paso desde el tema anterior en after_switch_theme 20.' );
check( 11 === hooked( 'template_include', 'digitalisimo_theme_fallback_template' ), 'Respaldos de plantilla en template_include 11.' );
check( 0 === hooked( 'after_setup_theme', 'digitalisimo_theme_content_width' ) && 10 === hooked( 'after_setup_theme', 'digitalisimo_theme_setup' ), 'Ancho del contenido en 0 y soportes en 10.' );
check( ! class_exists( 'Digitalisimo_Theme_Updater', false ) && ! class_exists( 'Digitalisimo_Theme_CLI', false ), 'Sin is_admin(), cron ni WP-CLI no se cargan el actualizador ni la CLI.' );
$functions_calls = function_calls( file_get_contents( $theme . '/functions.php' ) );
check( (bool) preg_match( "/if \( is_admin\(\) \|\| wp_doing_cron\(\) \|\| \( defined\( 'WP_CLI' \) && WP_CLI \) \) \{\s*require get_template_directory\(\) \. '\/inc\/class-updater\.php';\s*Digitalisimo_Theme_Updater::init\(\);/", file_get_contents( $theme . '/functions.php' ) ), 'El actualizador se carga en la administración, el cron y WP-CLI.' );

// 6. Ubicaciones con un gestor falso.
class Fake_Locations_Manager {
	public $calls = array();
	public function register_core_location( $location, $args = array() ) {
		$this->calls[] = array( 'core', $location, $args );
	}
	public function register_location( $location, $args = array() ) {
		$this->calls[] = array( 'location', $location, $args );
	}
}
$manager = new Fake_Locations_Manager();
digitalisimo_theme_register_locations( $manager );
check( array( 'core', 'header', array( 'hook' => 'digitalisimo_header', 'remove_hooks' => array( 'digitalisimo_theme_header' ) ) ) === $manager->calls[0], 'Cabecera en el gancho del tema, retirando el respaldo.' );
check( array( 'core', 'footer', array( 'hook' => 'digitalisimo_footer', 'remove_hooks' => array( 'digitalisimo_theme_footer' ) ) ) === $manager->calls[1], 'Pie en el gancho del tema, retirando el respaldo.' );
check( array( 'core', 'archive', array() ) === $manager->calls[2] && array( 'core', 'single', array() ) === $manager->calls[3], 'Archivo y entrada como ubicaciones del núcleo.' );
$hook_locations = array_slice( $manager->calls, 4 );
check( 10 === count( $hook_locations ), '10 ubicaciones por gancho (' . count( $hook_locations ) . ').' );
foreach ( $hook_locations as $call ) {
	check( 'location' === $call[0] && 0 === strpos( $call[1], 'digitalisimo_' ) && $call[1] === $call[2]['hook'] && true === $call[2]['public'] && true === $call[2]['multiple'] && false === $call[2]['edit_in_content'] && '' !== $call[2]['label'], 'Ubicación por gancho ' . $call[1] . ' pública, múltiple y en su gancho.' );
}
check( array_keys( digitalisimo_theme_hook_locations() ) === array_column( $hook_locations, 1 ), 'Las ubicaciones por gancho son las de digitalisimo_theme_hook_locations().' );
add_filter( 'digitalisimo_theme_hook_locations', function ( $list ) {
	return $list + array( 'otro_gancho' => 'Ajeno' );
} );
$manager = new Fake_Locations_Manager();
digitalisimo_theme_register_locations( $manager );
check( 14 === count( $manager->calls ), 'Un gancho sin el prefijo digitalisimo_ no se registra como ubicación.' );
add_filter( 'digitalisimo_theme_register_locations', '__return_false' );
$manager = new Fake_Locations_Manager();
digitalisimo_theme_register_locations( $manager );
check( array() === $manager->calls, 'digitalisimo_theme_register_locations en false no registra nada.' );
$GLOBALS['hooks']['digitalisimo_theme_register_locations'] = array();
$GLOBALS['hooks']['digitalisimo_theme_hook_locations']     = array();
check( 10 === hooked( 'digitalisimo_conditions_theme_locations', 'digitalisimo_theme_condition_locations' ), 'El tema amplía las ubicaciones de las condiciones de visitante.' );
check( array( 'header', 'footer', 'popup', 'digitalisimo_before_header', 'digitalisimo_after_header', 'digitalisimo_before_footer', 'digitalisimo_after_footer' ) === digitalisimo_theme_condition_locations( array( 'header', 'footer', 'popup' ) ), 'Con la cabecera, sus cuatro ganchos.' );
$content_hooks = digitalisimo_theme_condition_locations( array( 'single' ) );
check( 7 === count( $content_hooks ) && in_array( 'digitalisimo_before_comments', $content_hooks, true ) && ! in_array( 'digitalisimo_before_header', $content_hooks, true ), 'Con single, los seis ganchos del contenido.' );
check( array() === digitalisimo_theme_condition_locations( array() ), 'Sin ubicaciones (lienzo), ninguna.' );
check( 12 === count( digitalisimo_theme_condition_locations( array( 'header', 'archive', 'header' ) ) ), 'Sin duplicados (2 + 4 + 6).' );

// 7. Toda llamada al constructor está protegida con function_exists, did_action o class_exists.
$builder_refs = 0;
foreach ( $files as $file ) {
	if ( '.php' !== substr( $file, -4 ) || 0 === strpos( $file, 'languages/' ) ) {
		continue;
	}
	$source = file_get_contents( $theme . '/' . $file );
	$calls  = function_calls( $source );
	$guards = array();
	foreach ( $calls as $call ) {
		$arg = first_literal( $call );
		if ( in_array( $call['name'], array( 'function_exists', 'did_action', 'class_exists' ), true ) && is_string( $arg ) && false !== stripos( $arg, 'elementor' ) ) {
			$guards[ $call['function'] ] = true;
		}
	}
	foreach ( $calls as $call ) {
		if ( 0 === stripos( $call['name'], 'elementor_' ) ) {
			check( '' !== $call['function'] && ! empty( $guards[ $call['function'] ] ), "{$file}:{$call['line']} llama a {$call['name']}() sin comprobar antes que el constructor está cargado." );
			$builder_refs++;
		}
	}
	foreach ( owned_tokens( $source ) as $owned ) {
		list( $token, $owner ) = $owned;
		$name = is_array( $token ) ? ltrim( $token[1], '\\' ) : '';
		if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING !== $token[0] && ( 0 === strpos( $name, 'Elementor\\' ) || 0 === strpos( $name, 'ElementorPro\\' ) ) ) {
			check( '' !== $owner && ! empty( $guards[ $owner ] ), "{$file}:{$token[2]} usa {$name} sin comprobar antes que el constructor está cargado." );
			$builder_refs++;
		}
	}
}

check( $builder_refs >= 2, 'La comprobación de guardas encontró las llamadas al constructor (' . $builder_refs . ').' );

// 8. Estáticas por tokens sobre el tema y los siete plugins (sin el núcleo incluido).
$scan_roots = array( 'digitalisimo-theme', 'digitalisimo-seo', 'digitalisimo-ia-tools', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements', 'digitalisimo-migration' );
$switch     = array();
$scanned    = 0;
foreach ( $scan_roots as $dir ) {
	check( is_dir( $root . '/' . $dir ), 'Falta la carpeta ' . $dir );
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$path = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
		if ( 'php' !== $file->getExtension() || preg_match( '#^digitalisimo-elements/(elementor|bdthemes-element-pack)/|/node_modules/#', $path ) ) {
			continue;
		}
		$source = file_get_contents( $file->getPathname() );
		if ( ! preg_match( '/switch_theme|delete_theme|allowedthemes|auto_update_theme|add_option/', $source ) ) {
			continue;
		}
		$scanned++;
		foreach ( function_calls( $source ) as $call ) {
			$where = $path . ':' . $call['line'];
			$arg   = first_literal( $call );
			if ( 'switch_theme' === $call['name'] ) {
				$switch[] = $where . ' (' . $call['function'] . ')';
				check( 'digitalisimo-theme/inc/switch.php' === $path && 'digitalisimo_theme_revert' === $call['function'], "Sólo digitalisimo_theme_revert() cambia de tema: {$where}." );
			}
			check( 'delete_theme' !== $call['name'], "Nadie borra temas: {$where}." );
			if ( in_array( $call['name'], array( 'update_option', 'update_site_option', 'add_option', 'add_site_option' ), true ) ) {
				$first = implode( '', array_map( 'token_text', $call['args'][0] ?? array() ) );
				check( false === stripos( $first, 'allowedthemes' ), "Nadie habilita temas en la red ni en un sitio: {$where}." );
			}
			if ( 'add_filter' === $call['name'] ) {
				check( 'auto_update_theme' !== $arg, "Nadie fuerza la actualización automática de temas: {$where}." );
			}
			if ( 'add_option' === $call['name'] ) {
				check( ! is_string( $arg ) || ! preg_match( '/_lock$/', $arg ), "Un cerrojo nunca se toma con add_option (no es atómico): {$where}." );
			}
		}
	}
}
check( 1 === count( $switch ), 'Exactamente un switch_theme( fuera del núcleo incluido: ' . implode( ', ', $switch ) );
check( $scanned > 0, 'La estática recorrió archivos.' );
foreach ( function_calls( file_get_contents( $theme . '/inc/switch.php' ) ) as $call ) {
	if ( 'add_option' === $call['name'] ) {
		throw new RuntimeException( 'El tema no usa add_option.' );
	}
}
check( false !== strpos( file_get_contents( $theme . '/inc/switch.php' ), 'INSERT IGNORE INTO' ), 'El cerrojo del tema usa INSERT IGNORE.' );
foreach ( array( 'inc/class-updater.php', 'inc/switch.php' ) as $file ) {
	foreach ( token_get_all( file_get_contents( $theme . '/' . $file ) ) as $token ) {
		if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			check( false === strpos( $token[1], 'switch_theme(' ), "{$file}: ningún comentario contiene «switch_theme(»." );
		}
	}
}
check( false === strpos( file_get_contents( $theme . '/inc/class-updater.php' ), "'autoupdate'" ), 'Ninguna oferta del tema lleva autoupdate.' );

// 9. base.css: techo de Hello, todo en :where(), sin términos prohibidos, colores del sistema y propiedades lógicas.
$base = file_get_contents( $theme . '/assets/css/base.css' );
check( strlen( $base ) <= 5827, 'base.css pesa ' . strlen( $base ) . ' B (techo 5 827 B).' );
check( strlen( gzencode( $base, 9 ) ) <= 1399, 'base.css pesa ' . strlen( gzencode( $base, 9 ) ) . ' B con gzip -9 (techo 1 399 B).' );
$plain = preg_replace( '#/\*.*?\*/#s', '', $base );
foreach ( array( '--e-global', '.elementor', '.e-con', '.swiper', '@font-face', 'url(', '@import', '@media' ) as $term ) {
	check( false === stripos( $plain, $term ), 'base.css no contiene ' . $term );
}
preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $plain, $rules, PREG_SET_ORDER );
check( count( $rules ) > 20 && count( $rules ) === substr_count( $plain, '}' ), 'base.css son reglas planas.' );
foreach ( $rules as $rule ) {
	$selector = trim( $rule[1] );
	check( 0 === strpos( $selector, ':where(' ) && ')' === substr( $selector, -1 ), 'Selector fuera de :where(): ' . $selector );
	$depth = 0;
	for ( $i = strlen( ':where' ); $i < strlen( $selector ); $i++ ) {
		$depth += '(' === $selector[ $i ] ? 1 : ( ')' === $selector[ $i ] ? -1 : 0 );
		check( $depth > 0 || strlen( $selector ) - 1 === $i, 'Todo el selector va dentro de un único :where(): ' . $selector );
	}
	foreach ( array_filter( array_map( 'trim', explode( ';', $rule[2] ) ) ) as $declaration ) {
		list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );
		check( ! preg_match( '/^(margin|padding|border)-(left|right|top|bottom)|^(left|right|top|bottom)$/', $property ), 'Propiedad física en base.css: ' . $declaration );
		check( ! preg_match( '/^(float|text-align|clear)$/', $property ) || ! preg_match( '/\b(left|right)\b/', $value ), 'Valor físico en base.css: ' . $declaration );
		if ( preg_match( '/(^|-)color$|^background$|^(fill|stroke)$/', $property ) ) {
			check( in_array( strtolower( $value ), array( 'canvas', 'canvastext', 'transparent', 'inherit', 'currentcolor', 'none' ), true ), 'Color fuera de Canvas/CanvasText: ' . $declaration );
		}
		check( ! preg_match( '/#[0-9a-f]{3,8}\b|\b(rgb|rgba|hsl|hsla|oklch|lab|lch|color-mix)\(/i', $value ), 'Color literal en base.css: ' . $declaration );
	}
}
check( false !== strpos( $base, ':where(.pagination){display:flex;justify-content:space-between}' ), 'La paginación de los respaldos va en fila.' );
// El enlace de salto se ve al recibir el foco aunque el constructor cargue su `.screen-reader-text` (especificidad
// 0,1,0: clip, top:-10000em, 1 px y padding 0), que gana a cualquier regla dentro de :where(): lo que lo oculta va
// con !important.
preg_match( '/:where\(\.screen-reader-text:focus\)\{([^}]*)\}/', $plain, $focus_rule );
check( isset( $focus_rule[1] ), 'base.css tiene la regla de foco del enlace de salto.' );
foreach ( array( 'clip:auto!important', 'clip-path:none!important', 'height:auto!important', 'width:auto!important', 'inset-block-start:5px!important', 'inset-inline-start:5px!important', 'padding:12px 24px!important', 'margin:0!important' ) as $declaration ) {
	check( false !== strpos( $focus_rule[1], $declaration ), 'El foco del enlace de salto debe llevar ' . $declaration . ' (el .screen-reader-text del constructor le gana a :where()).' );
}
preg_match( '/:where\(body\)\{([^}]*)\}/', $plain, $body_rule );
preg_match( '/:where\(html\)\{([^}]*)\}/', $plain, $html_rule );
check( isset( $body_rule[1] ) && false !== strpos( $body_rule[1], 'overflow-x:clip' ) && ! preg_match( '/overflow(-x)?:(hidden|auto)/', $body_rule[1] ), 'overflow-x:clip en body, nunca hidden ni auto.' );
check( isset( $html_rule[1] ) && false === strpos( $html_rule[1], 'overflow' ), 'Ningún overflow en html.' );

// 10. Hojas de paridad: copia byte a byte de Hello 3.5.1.
$parity = array(
	'reset.css'         => '72eaeaf7ea81956ddf733ceac890269ab63e0aec77558bfaf045a5facd0bb6d4',
	'theme.css'         => 'd5c91a4cfdd98dba2870eeb3699e5ae57ad54203eef5fbe46accaee6b678d7df',
	'header-footer.css' => 'd4428ab6d474bf08e6d95cb1beafc2ebc48ee97d229b7d07295c4e89be053b93',
);
foreach ( $parity as $file => $sha ) {
	check( $sha === hash_file( 'sha256', $theme . '/assets/css/parity/' . $file ), 'parity/' . $file . ' es la hoja original sin cambios.' );
}
check( '3972dc9744f6499f0f9b2dbf76696f2ae7ad8af9b23dde66d6af86c9dfb36986' === hash_file( 'sha256', $theme . '/license.txt' ), 'license.txt es el texto de la GPL-3.0.' );
$theme_json = json_decode( file_get_contents( $theme . '/theme.json' ), true );
check( 3 === ( $theme_json['version'] ?? 0 ) && '800px' === ( $theme_json['settings']['layout']['contentSize'] ?? '' ) && '1200px' === ( $theme_json['settings']['layout']['wideSize'] ?? '' ) && true === ( $theme_json['settings']['appearanceTools'] ?? false ), 'theme.json v3, 800/1200 y appearanceTools.' );
$png = getimagesize( $theme . '/screenshot.png' );
check( 1200 === $png[0] && 900 === $png[1] && 'image/png' === $png['mime'], 'screenshot.png de 1200×900.' );

// 11 y 13. Cadenas traducibles: dominio propio, sin nombres de origen, todas en el .pot y en las dos traducciones.
$translatable = array( '__' => 's', 'esc_html__' => 's', 'esc_attr__' => 's', 'esc_html_e' => 's', 'esc_attr_e' => 's', '_e' => 's', '_x' => 'c', 'esc_html_x' => 'c', 'esc_attr_x' => 'c', '_ex' => 'c', '_n' => 'p', '_nx' => 'pc' );
$used         = array();
foreach ( $files as $file ) {
	if ( '.php' !== substr( $file, -4 ) || 0 === strpos( $file, 'languages/' ) ) {
		continue;
	}
	foreach ( function_calls( file_get_contents( $theme . '/' . $file ) ) as $call ) {
		if ( ! isset( $translatable[ $call['name'] ] ) ) {
			continue;
		}
		$args   = array_map( function ( $arg ) {
			return 1 === count( $arg ) ? literal( $arg[0] ) : null;
		}, $call['args'] );
		$shape  = $translatable[ $call['name'] ];
		$domain = array( 's' => 1, 'c' => 2, 'p' => 3, 'pc' => 4 )[ $shape ];
		check( 'digitalisimo-theme' === ( $args[ $domain ] ?? null ), "{$file}:{$call['line']} usa el dominio digitalisimo-theme." );
		check( is_string( $args[0] ), "{$file}:{$call['line']} traduce un literal." );
		$key = ( 'c' === $shape ? $args[1] . "\4" : ( 'pc' === $shape ? $args[3] . "\4" : '' ) ) . $args[0] . ( 'p' === $shape || 'pc' === $shape ? "\0" . $args[1] : '' );
		check( ! preg_match( '/hello|elementor/i', $key ), "{$file}:{$call['line']} no nombra productos de origen: {$args[0]}" );
		$used[ $key ] = true;
	}
}
$pot     = file_get_contents( $theme . '/languages/digitalisimo-theme.pot' );
$entries = array();
foreach ( preg_split( '/\n\s*\n/', $pot ) as $block ) {
	$fields = array();
	foreach ( array( 'msgctxt', 'msgid', 'msgid_plural' ) as $field ) {
		if ( preg_match( '/^' . $field . ' (".*")$/m', $block, $match ) ) {
			$fields[ $field ] = json_decode( $match[1] );
		}
	}
	if ( isset( $fields['msgid'] ) && '' !== $fields['msgid'] ) {
		$entries[ ( isset( $fields['msgctxt'] ) ? $fields['msgctxt'] . "\4" : '' ) . $fields['msgid'] . ( isset( $fields['msgid_plural'] ) ? "\0" . $fields['msgid_plural'] : '' ) ] = true;
	}
}
check( ! preg_match( '/hello|elementor/i', preg_replace( '/^#.*$/m', '', $pot ) ), 'El .pot no nombra productos de origen.' );
check( array() === array_diff_key( $used, $entries ) && array() === array_diff_key( $entries, $used ), 'El .pot tiene exactamente las cadenas del tema (faltan: ' . implode( ' | ', array_keys( array_diff_key( $used, $entries ) ) ) . '; sobran: ' . implode( ' | ', array_keys( array_diff_key( $entries, $used ) ) ) . ').' );
foreach ( array( 'Skip to content', 'Home', 'Canvas', 'Full width', "comments title\4One Response", "comments title\4%s Response\0%s Responses", 'Tagged ', '%s Previous', 'Next %s', 'Before header', 'After footer' ) as $required ) {
	check( isset( $entries[ $required ] ), 'El .pot incluye ' . str_replace( array( "\4", "\0" ), array( ' | ', ' / ' ), $required ) );
}
foreach ( array( 'es_MX', 'es_ES' ) as $locale ) {
	$l10n = include $theme . '/languages/' . $locale . '.l10n.php';
	check( 'digitalisimo-theme' === $l10n['domain'] && $locale === $l10n['language'] && 'nplurals=2; plural=n != 1;' === $l10n['plural-forms'], "{$locale}: cabeceras de la traducción." );
	foreach ( array_keys( $entries ) as $key ) {
		check( isset( $l10n['messages'][ $key ] ) && '' !== trim( (string) $l10n['messages'][ $key ] ), "{$locale} traduce " . str_replace( array( "\4", "\0" ), array( ' | ', ' / ' ), $key ) );
		check( ( false !== strpos( $key, "\0" ) ) === ( false !== strpos( $l10n['messages'][ $key ], "\0" ) ), "{$locale}: el plural de {$key} tiene sus dos formas." );
	}
	check( count( $l10n['messages'] ) === count( $entries ), "{$locale} no tiene cadenas sobrantes." );
}
$readme = file_get_contents( $theme . '/readme.txt' );
$parts  = preg_split( '/^== Créditos ==$/m', $readme );
check( 2 === count( $parts ) && false !== strpos( $readme, '== Changelog ==' ), 'readme.txt con Changelog y Créditos.' );
check( ! preg_match( '/hello|elementor/i', $parts[0] ) && (bool) preg_match( '/Hello Elementor 3\.5\.1 \(GPL-3\.0-or-later, © Elementor Ltd\.\)/', $parts[1] ), 'readme.txt sólo nombra el tema de origen en los créditos.' );
check( ! preg_match( '#https?://#', $parts[1] ), 'Los créditos no llevan enlaces.' );

// 12. Ningún dominio de los productos de origen en el tema (la misma expresión que scripts/build-packages.sh).
$vendors = json_decode( file_get_contents( $root . '/tests/playground/brand-audit/vendors.json' ), true );
$hosts   = implode( '|', array_map( function ( $host ) {
	return preg_quote( $host, '/' );
}, array_merge( $vendors['hosts'], $vendors['future'] ?? array() ) ) );
check( '' !== $hosts, 'vendors.json tiene dominios.' );
foreach ( $files as $file ) {
	check( ! preg_match( '/(^|[^a-z0-9-])([a-z0-9-]+\.)*(' . $hosts . ')([^a-z0-9-]|$)/i', file_get_contents( $theme . '/' . $file ) ), 'Dominio de un producto de origen en ' . $file );
}

// 14. Puente con los filtros de Hello (FR-9): sólo en paridad, y el filtro propio siempre al final.
function set_record( $record ) {
	$GLOBALS['options']['digitalisimo_theme_switch'] = $record;
}
set_record( array( 'origin' => array( 'family' => 'hello' ) ) );
check( digitalisimo_theme_parity() && digitalisimo_theme_show_title(), 'En paridad el título se muestra por defecto.' );
add_filter( 'hello_elementor_page_title', '__return_false' );
check( ! digitalisimo_theme_show_title(), 'En paridad, hello_elementor_page_title en false apaga el título.' );
add_filter( 'digitalisimo_theme_page_title', '__return_true' );
check( digitalisimo_theme_show_title(), 'El filtro propio tiene la última palabra.' );
$GLOBALS['hooks']['digitalisimo_theme_page_title'] = array();
set_record( array( 'origin' => array( 'family' => 'other' ) ) );
check( ! digitalisimo_theme_parity() && digitalisimo_theme_show_title(), 'Fuera de paridad los filtros de Hello no influyen.' );
set_record( array( 'origin' => array( 'family' => 'hello' ), 'reverted' => 123 ) );
check( ! digitalisimo_theme_parity(), 'Revertido, sin paridad.' );
$GLOBALS['hooks']['hello_elementor_page_title'] = array();
set_record( array( 'origin' => array( 'family' => 'hello' ), 'hello' => array( 'page_title' => true ) ) );
check( ! digitalisimo_theme_show_title(), 'El ajuste «título apagado» de Hello se respeta en paridad.' );
$GLOBALS['options']['digitalisimo_theme_switch']['origin']['family'] = 'other';
check( digitalisimo_theme_show_title(), 'Fuera de paridad el ajuste guardado de Hello no influye.' );

set_record( array( 'origin' => array( 'family' => 'hello' ), 'hello' => array( 'skip_link' => true ) ) );
ob_start();
digitalisimo_theme_skip_link();
check( '' === ob_get_clean(), 'Con el enlace de salto apagado en Hello no se imprime.' );
set_record( array( 'origin' => array( 'family' => 'hello' ) ) );
ob_start();
digitalisimo_theme_skip_link();
check( '<a class="skip-link screen-reader-text" href="#content">Skip to content</a>' . "\n" === ob_get_clean(), 'Enlace de salto a #content.' );
add_filter( 'hello_elementor_skip_link_url', function () {
	return '#main';
} );
check( '#main' === digitalisimo_theme_skip_link_url(), 'En paridad, hello_elementor_skip_link_url cambia el destino.' );
add_filter( 'hello_elementor_enable_skip_link', '__return_false' );
check( ! digitalisimo_theme_skip_link_enabled(), 'En paridad, hello_elementor_enable_skip_link en false lo apaga.' );
$GLOBALS['hooks']['hello_elementor_skip_link_url']    = array();
$GLOBALS['hooks']['hello_elementor_enable_skip_link'] = array();
add_filter( 'hello_elementor_viewport_content', function () {
	return 'width=device-width';
} );
ob_start();
digitalisimo_theme_viewport();
check( '<meta name="viewport" content="width=device-width">' . "\n" === ob_get_clean(), 'En paridad, hello_elementor_viewport_content cambia el viewport.' );
$GLOBALS['hooks']['hello_elementor_viewport_content'] = array();
add_filter( 'hello_elementor_content_width', function () {
	return 1140;
} );
digitalisimo_theme_content_width();
check( 1140 === $GLOBALS['content_width'], 'En paridad, hello_elementor_content_width cambia el ancho.' );
$GLOBALS['options']['digitalisimo_theme_switch']['origin']['family'] = 'other';
digitalisimo_theme_content_width();
check( 800 === $GLOBALS['content_width'], 'Fuera de paridad, 800.' );
$GLOBALS['hooks']['hello_elementor_content_width'] = array();

set_record( array( 'origin' => array( 'family' => 'hello' ), 'header_footer' => array( 'header' => 'hidden', 'footer' => 'dynamic' ) ) );
check( digitalisimo_theme_show_header_footer( 'header' ), 'Sin el constructor cargado la cabecera oculta de Hello se pinta (Hello también la pintaba).' );
$GLOBALS['did']['elementor/loaded'] = 1;
check( ! digitalisimo_theme_show_header_footer( 'header' ) && digitalisimo_theme_show_header_footer( 'footer' ), 'header_footer.header = hidden oculta sólo la cabecera.' );
set_record( array( 'origin' => array( 'family' => 'hello' ), 'header_footer' => array( 'header' => 'dynamic', 'footer' => 'hidden' ) ) );
check( digitalisimo_theme_show_header_footer( 'header' ) && digitalisimo_theme_show_header_footer( 'footer' ), 'Un pie «hidden» (registro anterior) no oculta el pie: Hello lo imprimía vacío.' );
set_record( array( 'origin' => array( 'family' => 'hello' ), 'header_footer' => array( 'header' => 'hidden', 'footer' => 'dynamic' ) ) );
$GLOBALS['options']['digitalisimo_theme_switch']['origin']['family'] = 'other';
check( digitalisimo_theme_show_header_footer( 'header' ), 'Fuera de paridad, hidden no influye.' );
set_record( array( 'origin' => array( 'family' => 'hello' ), 'hello' => array( 'header_footer' => true ) ) );
check( ! digitalisimo_theme_show_header_footer( 'header' ) && ! digitalisimo_theme_show_header_footer( 'footer' ), 'Cabecera y pie apagados en Hello.' );
set_record( array( 'origin' => array( 'family' => 'hello' ) ) );
add_filter( 'hello_elementor_header_footer', '__return_false' );
check( ! digitalisimo_theme_show_header_footer( 'footer' ), 'En paridad, hello_elementor_header_footer en false los apaga.' );
$GLOBALS['hooks']['hello_elementor_header_footer'] = array();
add_filter( 'digitalisimo_theme_header_footer', function ( $show, $part ) {
	return 'footer' !== $part;
}, 10, 2 );
check( digitalisimo_theme_show_header_footer( 'header' ) && ! digitalisimo_theme_show_header_footer( 'footer' ), 'digitalisimo_theme_header_footer recibe la parte.' );
$GLOBALS['hooks']['digitalisimo_theme_header_footer'] = array();
unset( $GLOBALS['did']['elementor/loaded'] );

// 15. Hojas y alias de Hello (P-4): en paridad, alias sin archivo; fuera, sólo base.css.
set_record( array( 'origin' => array( 'family' => 'other' ) ) );
$GLOBALS['styles'] = array();
digitalisimo_theme_enqueue_styles();
check( array( 'digitalisimo-theme' ) === array_keys( $GLOBALS['styles'] ) && '/assets/css/base.css' === substr( $GLOBALS['styles']['digitalisimo-theme']['src'], -20 ) && '1.0.0' === $GLOBALS['styles']['digitalisimo-theme']['ver'], 'Fuera de paridad sólo base.css, con la versión del tema.' );
add_filter( 'digitalisimo_theme_enqueue_style', '__return_false' );
$GLOBALS['styles'] = array();
digitalisimo_theme_enqueue_styles();
check( array() === $GLOBALS['styles'], 'digitalisimo_theme_enqueue_style en false no encola base.css.' );
$GLOBALS['hooks']['digitalisimo_theme_enqueue_style'] = array();
set_record( array( 'origin' => array( 'family' => 'hello' ) ) );
$GLOBALS['styles'] = array();
digitalisimo_theme_enqueue_styles();
foreach ( array( 'digitalisimo-theme-parity-reset' => 'parity/reset.css', 'digitalisimo-theme-parity' => 'parity/theme.css', 'digitalisimo-theme-parity-header-footer' => 'parity/header-footer.css' ) as $handle => $sheet ) {
	check( wp_style_is( $handle ) && substr( $GLOBALS['styles'][ $handle ]['src'], -strlen( $sheet ) ) === $sheet, 'En paridad se encola ' . $sheet );
}
check( ! isset( $GLOBALS['styles']['digitalisimo-theme'] ), 'En paridad no se encola base.css.' );
foreach ( array( 'hello-elementor' => 'digitalisimo-theme-parity-reset', 'hello-elementor-theme-style' => 'digitalisimo-theme-parity', 'hello-elementor-header-footer' => 'digitalisimo-theme-parity-header-footer' ) as $alias => $handle ) {
	check( wp_style_is( $alias, 'registered' ) && ! wp_style_is( $alias ) && false === $GLOBALS['styles'][ $alias ]['src'] && array( $handle ) === $GLOBALS['styles'][ $alias ]['deps'], "Alias {$alias} registrado sin archivo y dependiente de {$handle}." );
}
check( empty( $GLOBALS['styles']['digitalisimo-theme-parity']['inline'] ), 'Sin cabecera generada, sin la regla de altura mínima.' );
set_record( array( 'origin' => array( 'family' => 'hello' ), 'hello' => array( 'hello_style' => true ), 'generated' => array( 'header' => array( 'id' => 7, 'hash' => 'x' ) ) ) );
$GLOBALS['styles'] = array();
digitalisimo_theme_enqueue_styles();
check( ! wp_style_is( 'digitalisimo-theme-parity-reset' ) && array() === $GLOBALS['styles']['hello-elementor']['deps'], 'Con el reset apagado en Hello, su alias no depende de nada.' );
check( array( '.elementor-location-header:has(>.dynamic-header)+.elementor{min-height:calc(100vh - 320px)}' ) === ( $GLOBALS['styles']['digitalisimo-theme-parity']['inline'] ?? array() ), 'Con la cabecera generada, la última regla de theme.css adaptada.' );
$GLOBALS['options']['digitalisimo_theme_switch']['generated']['header']['retired'] = 'trash';
$GLOBALS['styles'] = array();
digitalisimo_theme_enqueue_styles();
check( empty( $GLOBALS['styles']['digitalisimo-theme-parity']['inline'] ), 'Con la cabecera generada retirada, sin la regla.' );
add_filter( 'hello_elementor_enqueue_theme_style', '__return_false' );
$GLOBALS['styles'] = array();
digitalisimo_theme_enqueue_styles();
check( ! wp_style_is( 'digitalisimo-theme-parity' ) && wp_style_is( 'digitalisimo-theme-parity-header-footer' ), 'En paridad, hello_elementor_enqueue_theme_style en false retira theme.css.' );
$GLOBALS['hooks']['hello_elementor_enqueue_theme_style'] = array();

// CSS del kit en paridad con la cabecera y el pie configurables de Hello (Hello lo cargaba en todas las páginas).
eval( 'namespace Elementor; class Plugin { public static $instance; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
class Fake_Kits_Manager {
	public $calls = 0;
	public function frontend_before_enqueue_styles() {
		$this->calls++;
	}
}
\Elementor\Plugin::$instance = (object) array( 'kits_manager' => new Fake_Kits_Manager() );
$kits                         = \Elementor\Plugin::$instance->kits_manager;
set_record( array( 'origin' => array( 'family' => 'hello' ), 'header_footer' => array( 'experiment' => true, 'header' => 'hidden', 'footer' => 'dynamic' ) ) );
digitalisimo_theme_enqueue_styles();
check( 0 === $kits->calls, 'Sin el constructor cargado no se toca el kit.' );
$GLOBALS['did']['elementor/loaded'] = 1;
digitalisimo_theme_enqueue_styles();
check( 1 === $kits->calls, 'Con la cabecera configurable de Hello, el CSS del kit se carga en todas las páginas.' );
$GLOBALS['options']['digitalisimo_theme_switch']['header_footer']['experiment'] = false;
digitalisimo_theme_enqueue_styles();
$GLOBALS['options']['digitalisimo_theme_switch']['header_footer']['experiment'] = true;
$GLOBALS['options']['digitalisimo_theme_switch']['hello']['header_footer']      = true;
digitalisimo_theme_enqueue_styles();
unset( $GLOBALS['options']['digitalisimo_theme_switch']['hello'] );
$GLOBALS['options']['digitalisimo_theme_switch']['origin']['family'] = 'other';
digitalisimo_theme_enqueue_styles();
$GLOBALS['options']['digitalisimo_theme_switch']['origin']['family'] = 'hello';
add_filter( 'digitalisimo_theme_enqueue_style', function ( $load, $handle ) {
	return 'elementor-kit' === $handle ? false : $load;
}, 10, 2 );
digitalisimo_theme_enqueue_styles();
check( 1 === $kits->calls, 'Sin el experimento, con cabecera y pie apagados en Hello, fuera de paridad o con el filtro propio en false, no.' );
$GLOBALS['hooks']['digitalisimo_theme_enqueue_style'] = array();
unset( $GLOBALS['did']['elementor/loaded'] );

// 16. Destino del salto (FR-7): sólo el primer envoltorio tras la marca, sólo sin id y una sola vez por petición.
set_record( array( 'origin' => array( 'family' => 'hello' ) ) );
check( array( 'class' => 'elementor' ) === digitalisimo_theme_skip_target_attributes( array( 'class' => 'elementor' ) ), 'Sin marca, el envoltorio no cambia.' );
do_action( 'elementor/theme/before_do_single' );
check( array( 'class' => 'elementor', 'id' => 'content' ) === apply_filters( 'elementor/document/wrapper_attributes', array( 'class' => 'elementor' ) ), 'El primer envoltorio tras la marca recibe id="content".' );
check( array( 'class' => 'elementor' ) === apply_filters( 'elementor/document/wrapper_attributes', array( 'class' => 'elementor' ) ), 'La marca se consume: el siguiente envoltorio no cambia.' );
check( digitalisimo_theme_skip_target_printed(), 'La petición ya tiene destino.' );
do_action( 'elementor/theme/before_do_archive' );
check( array( 'class' => 'x' ) === apply_filters( 'elementor/document/wrapper_attributes', array( 'class' => 'x' ) ), 'Nunca dos #content en la misma petición.' );
digitalisimo_theme_skip_state( 'printed', false );
do_action( 'elementor/page_templates/header-footer/before_content' );
check( array( 'id' => 'propio' ) === apply_filters( 'elementor/document/wrapper_attributes', array( 'id' => 'propio' ) ) && ! digitalisimo_theme_skip_state( 'armed' ), 'Un envoltorio con id propio no cambia y consume la marca.' );
do_action( 'elementor/theme/before_do_single' );
do_action( 'elementor/theme/after_do_single' );
check( array( 'class' => 'pie' ) === apply_filters( 'elementor/document/wrapper_attributes', array( 'class' => 'pie' ) ), 'Si la ubicación no tuvo envoltorio, la marca no pasa al pie.' );
add_filter( 'digitalisimo_theme_skip_link_url', function () {
	return '#otro';
} );
do_action( 'elementor/theme/before_do_single' );
check( array() === apply_filters( 'elementor/document/wrapper_attributes', array() ), 'Con otra URL de salto no se añade id.' );
$GLOBALS['hooks']['digitalisimo_theme_skip_link_url'] = array();
ob_start();
do_action( 'woocommerce_before_main_content' );
check( '<span id="content"></span>' === ob_get_clean() && 5 === hooked( 'woocommerce_before_main_content', 'digitalisimo_theme_woocommerce_skip_target' ), 'WooCommerce recibe el destino antes de su <main> (prioridad 5).' );
ob_start();
do_action( 'woocommerce_before_main_content' );
check( '' === ob_get_clean(), 'Y sólo una vez.' );
foreach ( array( 'template-parts/single.php', 'template-parts/archive.php', 'template-parts/search.php', 'template-parts/404.php', 'fallback/canvas.php', 'fallback/full-width.php' ) as $file ) {
	$source = file_get_contents( $theme . '/' . $file );
	check( 1 === substr_count( $source, 'id="content"' ) && false !== strpos( $source, 'digitalisimo_theme_skip_target_printed( true )' ), "{$file}: un <main id=\"content\"> que marca el destino." );
}

// Respaldos sin constructor (§7.7).
function get_page_template_slug() {
	return $GLOBALS['page_template'];
}
function get_queried_object_id() {
	return 5;
}
$GLOBALS['page_template'] = 'elementor_canvas';
check( array( 'a' => 'A', 'elementor_canvas' => 'Canvas', 'elementor_header_footer' => 'Full width' ) === digitalisimo_theme_fallback_templates( array( 'a' => 'A' ), null, (object) array( 'ID' => 5 ) ), 'Sin constructor, la página conserva sus plantillas del constructor.' );
$GLOBALS['page_template'] = 'default';
check( array( 'a' => 'A' ) === digitalisimo_theme_fallback_templates( array( 'a' => 'A' ), null, (object) array( 'ID' => 5 ) ), 'Otras páginas no ven esas plantillas.' );
$GLOBALS['did']['elementor/loaded'] = 1;
$GLOBALS['page_template']           = 'elementor_canvas';
check( 'x.php' === digitalisimo_theme_fallback_template( 'x.php' ), 'Con el constructor, sus plantillas son suyas.' );

echo 'Tema DIGITALÍSIMO: árbol, cabecera, ganchos, ubicaciones, estáticas (' . $scanned . ' archivos), base.css, paridad, cadenas, puente y destino del salto validados.' . "\n";
