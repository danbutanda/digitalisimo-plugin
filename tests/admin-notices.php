<?php
/**
 * Avisos de administración de DIGITALÍSIMO (includes/class-digitalisimo-notices.php, copia idéntica en los siete plugins;
 * en Elements, digitalisimo-notices.php).
 *
 * Queja del propietario (2026-11-22): «se están mostrando alertas así y son molestas, no poder quitarlas; mándalas a las
 * alertas globales de WP pero no las pongas en el contenido». La captura era el aviso verde de Tools
 * (`Cache_Notice`, «DIGITALÍSIMO agregó una sal propia para la caché de objetos… No hace falta hacer nada…») sin X.
 *
 *  - Estático: las siete copias son idénticas y cada plugin carga la suya; ningún aviso propio que WordPress lleva a la
 *    zona global (`<div class="notice…">` sin `inline`) se pinta sin la X; cada pantalla propia lleva
 *    `<hr class="wp-header-end">` justo tras su título, para que el JavaScript común de WordPress ponga ahí los avisos
 *    (sin la marca los llevaba tras el primer h1/h2 de `.wrap`, que podía caer dentro del contenido).
 *  - Unidad: el aviso lleva la X y su id; cerrado no vuelve para ese usuario mientras la huella no cambie; un éxito no
 *    vence, una advertencia vuelve a los 7 días y un error al día; el cierre por AJAX exige el nonce del aviso (id,
 *    huella y capacidad) y la capacidad; en una red el cierre vale igual en la administración de la red y en cada sitio.
 *  - El aviso real de la captura (`Cache_Notice::notice()`) con la X, cerrado ya no sale y vuelve si cambia la sal.
 *
 * Uso: php tests/admin-notices.php   (AN_ROOT=<otra copia> la prueba contra ese código)
 */
error_reporting( E_ALL );
$root     = getenv( 'AN_ROOT' ) ?: dirname( __DIR__ );
$problems = array();
function an_check( $ok, $message ) {
	if ( ! $ok ) $GLOBALS['problems'][] = $message;
}
function an_done() {
	if ( $GLOBALS['problems'] ) {
		echo "FALLA admin-notices\n  - " . implode( "\n  - ", $GLOBALS['problems'] ) . "\n";
		exit( 1 );
	}
	echo "OK admin-notices\n";
	exit( 0 );
}

/* ------------------------------------------------------------------ Estático: copias y cargadores */

$copies = array(
	'digitalisimo-seo/includes/class-digitalisimo-notices.php',
	'digitalisimo-ia-tools/includes/class-digitalisimo-notices.php',
	'digitalisimo-hosting/includes/class-digitalisimo-notices.php',
	'digitalisimo-backups/includes/class-digitalisimo-notices.php',
	'digitalisimo-tools/includes/class-digitalisimo-notices.php',
	'digitalisimo-elements/digitalisimo-notices.php',
	'digitalisimo-migration/includes/class-digitalisimo-notices.php',
);
$texts = array();
foreach ( $copies as $copy ) {
	an_check( is_file( $root . '/' . $copy ), "Falta la copia del ayudante de avisos: {$copy}." );
	$texts[ $copy ] = is_file( $root . '/' . $copy ) ? (string) file_get_contents( $root . '/' . $copy ) : '';
}
an_check( 1 === count( array_unique( $texts ) ), 'Las siete copias del ayudante de avisos deben ser idénticas.' );
$loaders = array(
	'digitalisimo-seo/digitalisimo-integrations.php'    => "includes/class-digitalisimo-notices.php",
	'digitalisimo-ia-tools/digitalisimo-chatbot.php'    => "includes/class-digitalisimo-notices.php",
	'digitalisimo-hosting/digitalisimo-hosting.php'     => "includes/class-digitalisimo-notices.php",
	'digitalisimo-backups/digitalisimo-backups.php'     => "includes/class-digitalisimo-notices.php",
	'digitalisimo-backups/includes/class-backups.php'   => "class-digitalisimo-notices.php",
	'digitalisimo-tools/digitalisimo-tools.php'         => "includes/class-digitalisimo-notices.php",
	'digitalisimo-elements/pro-elements.php'            => "digitalisimo-notices.php",
	'digitalisimo-migration/digitalisimo-migration.php' => "includes/class-digitalisimo-notices.php",
);
foreach ( $loaders as $file => $needle ) an_check( false !== strpos( (string) @file_get_contents( $root . '/' . $file ), $needle ), "{$file} debe cargar el ayudante de avisos." );
$elements = (string) @file_get_contents( $root . '/digitalisimo-elements/pro-elements.php' );
an_check( false !== strpos( $elements, 'digitalisimo-notices.php' ) && strpos( $elements, 'digitalisimo-notices.php' ) < strpos( $elements, "digitalisimo-core.php'" ), 'Elements carga el ayudante antes del núcleo (sus avisos de carga lo usan aunque Elements no llegue a cargar).' );

/* ------------------------------------------------------------------ Estático: avisos con la X y wp-header-end */

$own  = array( 'digitalisimo-seo', 'digitalisimo-ia-tools', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements', 'digitalisimo-migration', 'digitalisimo-theme', 'digitalisimo-elements-installer' );
$skip = array( '/digitalisimo-elements/elementor/', '/vendor/', '/node_modules/' );
// Avisos que siguen sin X, a propósito: la franja de estado de la pantalla de Backups (el lugar donde esa información
// queda siempre a la vista, como «Salud del sitio») y el error que sustituye a esa pantalla cuando el almacén no se puede
// usar (es todo su contenido).
$allowed = array(
	'digitalisimo-backups/includes/trait-backups-screen.php' => array( 'digitalisimo-backups-strip', '<h1>Backups · Digitalisimo</h1><hr class="wp-header-end"><div class="notice notice-error">' ),
);
// `</h1>` que no son el título de una pantalla de administración: expresiones regulares, plantillas del front y del
// correo, la página de rescate de Código PHP y la página suelta de la clave de migración (sin escritorio).
$not_screens = array( 'digitalisimo-ia-tools/includes/class-digitalisimo-ai-content-playbook.php', 'digitalisimo-ia-tools/includes/class-digitalisimo-mcp-articles.php', 'digitalisimo-ia-tools/includes/class-digitalisimo-ai-content-publisher.php', 'digitalisimo-seo/includes/class-seo-ai-audit-tools.php#preg', 'digitalisimo-elements/modules/notes/notifications/views/email.php', 'digitalisimo-tools/modules/php-snippets/class-runner.php', 'digitalisimo-backups/includes/class-backups-migrate.php' );
$bare = array();
$heads = array();
$files = 0;
foreach ( $own as $dir ) {
	if ( ! is_dir( $root . '/' . $dir ) ) continue;
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = str_replace( '\\', '/', (string) $file );
		if ( '.php' !== substr( $path, -4 ) ) continue;
		foreach ( $skip as $part ) if ( false !== strpos( $path, $part ) ) continue 2;
		$rel  = substr( $path, strlen( $root ) + 1 );
		$text = (string) file_get_contents( $path );
		$files++;
		// Un aviso que WordPress lleva a la zona global: `<div class="notice…">`, `"error"` o `"updated"` sin `inline`.
		if ( preg_match_all( '/<div class=(\\\\?["\'])(?:notice|error|updated)\b/', $text, $found, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found[0] as $hit ) {
				$segment = substr( $text, $hit[1], 400 );
				$end     = preg_match( '/"\s*>|\\\\">|\'>/', substr( $segment, 12 ), $close, PREG_OFFSET_CAPTURE ) ? 12 + $close[0][1] : 300;
				$class   = substr( $segment, 0, $end );
				if ( false !== strpos( $class, 'inline' ) || false !== strpos( $class, 'is-dismissible' ) ) continue;
				$ok = false;
				foreach ( $allowed[ $rel ] ?? array() as $needle ) $ok = $ok || false !== strpos( substr( $text, max( 0, $hit[1] - 80 ), 480 ), $needle );
				if ( ! $ok ) $bare[] = $rel . ':' . ( substr_count( substr( $text, 0, $hit[1] ), "\n" ) + 1 );
			}
		}
		// Títulos de pantalla: `</h1>` seguido de la marca (en el mismo eco o en la línea siguiente).
		if ( 0 === strpos( $rel, 'digitalisimo-theme/template-parts/' ) || in_array( $rel, $not_screens, true ) ) continue;
		if ( preg_match_all( '#</h1>#', $text, $found, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found[0] as $hit ) {
				$before = substr( $text, max( 0, $hit[1] - 40 ), 40 );
				if ( false !== strpos( $before, '.*?' ) || false !== strpos( $before, '[^>]*>' ) ) continue; // Dentro de una expresión regular.
				$heads[] = $rel;
				if ( ! preg_match( '#^</h1>(?:\'\s*\)\s*;\s*\?>|\'\s*;|\s|\?>)*(?:echo\s*\')?\s*<hr class=\\\\?"wp-header-end\\\\?">#', substr( $text, $hit[1], 160 ) ) ) $bare[] = $rel . ':' . ( substr_count( substr( $text, 0, $hit[1] ), "\n" ) + 1 ) . ' (título sin <hr class="wp-header-end">)';
			}
		}
	}
}
an_check( $files > 500, "Se revisaron pocos archivos ({$files}): ¿cambió la estructura?" );
an_check( count( $heads ) >= 40, 'Se encontraron pocos títulos de pantalla (' . count( $heads ) . ').' );
an_check( ! $bare, 'Avisos sin la X o títulos sin wp-header-end (queja del propietario del 2026-11-22): ' . implode( ', ', $bare ) );
// El Asistente IA no tiene título: la marca va antes de su raíz.
an_check( false !== strpos( (string) @file_get_contents( $root . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant.php' ), '<hr class="wp-header-end"><div id="digitalisimo-assistant-root"' ), 'El Asistente IA lleva la marca antes de su raíz.' );
// El tema y el instalador no llevan JavaScript propio: usan el ayudante si un plugin lo cargó y, si no, la X sola.
an_check( false !== strpos( (string) @file_get_contents( $root . '/digitalisimo-theme/inc/child.php' ), "class_exists( 'Digitalisimo_Notices', false )" ), 'El tema usa el ayudante de avisos si un plugin lo cargó.' );
an_check( false !== strpos( (string) @file_get_contents( $root . '/digitalisimo-elements-installer/digitalisimo-elements-installer.php' ), "class_exists( 'Digitalisimo_Notices', false )" ), 'El instalador usa el ayudante de avisos si un plugin lo cargó.' );

$helper = $root . '/digitalisimo-tools/includes/class-digitalisimo-notices.php';
if ( ! is_file( $helper ) ) {
	$problems[] = 'Sin el ayudante de avisos no se pueden probar el cierre ni el aviso de la caché.';
	an_done();
}

/* ------------------------------------------------------------------ Unidad: el ayudante */

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['an'] = array( 'user' => 7, 'meta' => array(), 'caps' => array( 'read' => true, 'manage_options' => true, 'manage_network_options' => false ), 'actions' => array(), 'multi' => false, 'network_admin' => false, 'screen' => 'dashboard' );
class AN_Json extends Exception { public $data; public $status; public $ok; public function __construct( $ok, $data, $status ) { parent::__construct( $ok ? 'success' : 'error' ); $this->ok = $ok; $this->data = $data; $this->status = $status; } }
function is_admin() { return true; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['an']['actions'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { return add_action( $hook, $cb, $prio, $args ); }
function get_current_user_id() { return $GLOBALS['an']['user']; }
function get_user_meta( $user, $key, $single = false ) { return $GLOBALS['an']['meta'][ $user ][ $key ] ?? ''; }
function update_user_meta( $user, $key, $value ) { $GLOBALS['an']['meta'][ $user ][ $key ] = $value; return true; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['an']['caps'][ $cap ] ); }
function wp_create_nonce( $action ) { return substr( md5( 'nonce|' . $GLOBALS['an']['user'] . '|' . $action ), 0, 10 ); }
function check_ajax_referer( $action, $field, $die = true ) { $value = $_REQUEST[ $field ] ?? ''; return is_string( $value ) && '' !== $value && $value === wp_create_nonce( $action ) ? 1 : false; }
function wp_send_json_success( $data = null, $status = null ) { throw new AN_Json( true, $data, $status ); }
function wp_send_json_error( $data = null, $status = null ) { throw new AN_Json( false, $data, $status ); }
function wp_unslash( $value ) { return $value; }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function admin_url( $path = '' ) { return 'https://plomerito.test/wp-admin/' . $path; }
function is_multisite() { return $GLOBALS['an']['multi']; }
function is_network_admin() { return $GLOBALS['an']['network_admin']; }
function get_current_screen() { return (object) array( 'id' => $GLOBALS['an']['screen'] ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
require $helper;

an_check( isset( $GLOBALS['an']['actions']['wp_ajax_digitalisimo_notice_dismiss'] ) && ! isset( $GLOBALS['an']['actions']['wp_ajax_nopriv_digitalisimo_notice_dismiss'] ), 'El cierre va por wp_ajax (sesión iniciada), nunca anónimo.' );
// Una segunda copia no vuelve a declarar la clase ni a registrar el AJAX.
if ( is_file( $root . '/digitalisimo-seo/includes/class-digitalisimo-notices.php' ) ) require $root . '/digitalisimo-seo/includes/class-digitalisimo-notices.php';
an_check( 1 === count( $GLOBALS['an']['actions']['wp_ajax_digitalisimo_notice_dismiss'] ?? array() ), 'Con dos plugins, el cierre por AJAX se registra una vez.' );

$attr = function ( $html, $name ) { return preg_match( '/' . preg_quote( $name, '/' ) . '="([^"]*)"/', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : ''; };
$ajax = function ( array $post ) {
	$_POST = $post; $_REQUEST = $post;
	try { Digitalisimo_Notices::ajax(); } catch ( AN_Json $e ) { return $e; }
	return null;
};

$html = Digitalisimo_Notices::html( 'prueba', 'success', '<p>Se agregó algo. No hace falta hacer nada.</p>' );
an_check( false !== strpos( $html, 'class="notice notice-success is-dismissible"' ), 'El aviso lleva la X de WordPress: ' . $html );
an_check( 'prueba' === $attr( $html, 'data-digitalisimo-notice' ) && 1 === preg_match( '/^[0-9a-f]{12}$/', $attr( $html, 'data-digitalisimo-notice-hash' ) ) && 'read' === $attr( $html, 'data-digitalisimo-notice-cap' ) && '' !== $attr( $html, 'data-digitalisimo-notice-nonce' ), 'El aviso lleva su id, su huella, la capacidad y el nonce del cierre.' );
an_check( isset( $GLOBALS['an']['actions']['admin_print_footer_scripts'] ), 'Con un aviso que recordar, se imprime el script del cierre.' );
ob_start(); Digitalisimo_Notices::print_script(); $script = ob_get_clean();
an_check( false !== strpos( $script, 'digitalisimo_notice_dismiss' ) && false !== strpos( $script, '.notice-dismiss' ) && false !== strpos( $script, 'admin-ajax.php' ) && false !== strpos( $script, ',true)' ), 'El script manda el cierre al pulsar la X (en captura, antes de que WordPress quite el aviso).' );
$once = Digitalisimo_Notices::html( '', 'error', '<p>No se guardó.</p>', array( 'persist' => false ) );
an_check( '<div class="notice notice-error is-dismissible"><p>No se guardó.</p></div>' === $once, 'Un resultado de formulario lleva la X sin recordar el cierre: ' . $once );

// AJAX: sin nonce, con otro nonce, con otra huella o sin la capacidad, no se guarda nada.
$hash  = $attr( $html, 'data-digitalisimo-notice-hash' );
$nonce = $attr( $html, 'data-digitalisimo-notice-nonce' );
$r = $ajax( array( 'action' => 'digitalisimo_notice_dismiss', 'id' => 'prueba', 'hash' => $hash, 'cap' => 'read' ) );
an_check( $r && ! $r->ok && 403 === $r->status && empty( $GLOBALS['an']['meta'] ), 'Sin nonce, el cierre se rechaza.' );
$r = $ajax( array( 'id' => 'prueba', 'hash' => $hash, 'cap' => 'read', 'nonce' => 'abcdef0123' ) );
an_check( $r && ! $r->ok && empty( $GLOBALS['an']['meta'] ), 'Con un nonce falso, el cierre se rechaza.' );
$r = $ajax( array( 'id' => 'prueba', 'hash' => 'ffffffffffff', 'cap' => 'read', 'nonce' => $nonce ) );
an_check( $r && ! $r->ok && empty( $GLOBALS['an']['meta'] ), 'El nonce está atado a la huella: con otra, se rechaza.' );
$r = $ajax( array( 'id' => 'otro', 'hash' => $hash, 'cap' => 'read', 'nonce' => $nonce ) );
an_check( $r && ! $r->ok && empty( $GLOBALS['an']['meta'] ), 'El nonce está atado al id: con otro, se rechaza.' );
$r = $ajax( array( 'id' => array( 'x' ), 'hash' => $hash, 'cap' => 'read', 'nonce' => $nonce ) );
an_check( $r && ! $r->ok && empty( $GLOBALS['an']['meta'] ), 'Un campo que no es texto se rechaza sin errores de PHP.' );
$admin = Digitalisimo_Notices::html( 'solo-admin', 'warning', '<p>Revisa esto.</p>', array( 'cap' => 'manage_network_options' ) );
$r = $ajax( array( 'id' => 'solo-admin', 'hash' => $attr( $admin, 'data-digitalisimo-notice-hash' ), 'cap' => 'read', 'nonce' => $attr( $admin, 'data-digitalisimo-notice-nonce' ) ) );
an_check( $r && ! $r->ok && empty( $GLOBALS['an']['meta'] ), 'El nonce está atado a la capacidad: pedir una menor se rechaza.' );
$r = $ajax( array( 'id' => 'solo-admin', 'hash' => $attr( $admin, 'data-digitalisimo-notice-hash' ), 'cap' => 'manage_network_options', 'nonce' => $attr( $admin, 'data-digitalisimo-notice-nonce' ) ) );
an_check( $r && ! $r->ok && 403 === $r->status && empty( $GLOBALS['an']['meta'] ), 'Sin la capacidad del aviso, el cierre se rechaza.' );
$GLOBALS['an']['user'] = 8;
$r = $ajax( array( 'id' => 'prueba', 'hash' => $hash, 'cap' => 'read', 'nonce' => $nonce ) );
an_check( $r && ! $r->ok && empty( $GLOBALS['an']['meta'] ), 'El nonce de un usuario no vale para otro.' );
$GLOBALS['an']['user'] = 7;

// Con nonce y capacidad: se guarda y el aviso ya no sale para ese usuario; otro usuario lo sigue viendo.
$r = $ajax( array( 'id' => 'prueba', 'hash' => $hash, 'cap' => 'read', 'nonce' => $nonce ) );
an_check( $r && $r->ok, 'Con nonce y capacidad, el cierre se guarda.' );
an_check( $hash === ( $GLOBALS['an']['meta'][7][ Digitalisimo_Notices::META ]['prueba']['h'] ?? '' ), 'El cierre queda en el user meta con la huella.' );
an_check( '' === Digitalisimo_Notices::html( 'prueba', 'success', '<p>Se agregó algo. No hace falta hacer nada.</p>' ), 'Cerrado, el aviso ya no sale.' );
ob_start(); $shown = Digitalisimo_Notices::show( 'prueba', 'success', '<p>Se agregó algo. No hace falta hacer nada.</p>' ); $out = ob_get_clean();
an_check( false === $shown && '' === $out, 'show() tampoco lo pinta.' );
$GLOBALS['an']['meta'][7][ Digitalisimo_Notices::META ]['prueba']['t'] = time() - 400 * DAY_IN_SECONDS;
an_check( '' === Digitalisimo_Notices::html( 'prueba', 'success', '<p>Se agregó algo. No hace falta hacer nada.</p>' ), 'Un éxito cerrado no vuelve con el tiempo.' );
an_check( '' !== Digitalisimo_Notices::html( 'prueba', 'success', '<p>Se agregó otra cosa.</p>' ), 'Si cambia la situación (otra huella), vuelve a salir.' );
$GLOBALS['an']['user'] = 9;
an_check( '' !== Digitalisimo_Notices::html( 'prueba', 'success', '<p>Se agregó algo. No hace falta hacer nada.</p>' ), 'Otro usuario lo sigue viendo.' );
$GLOBALS['an']['user'] = 7;
an_check( Digitalisimo_Notices::fingerprint( '<input type="hidden" name="_wpnonce" value="0123456789">' ) === Digitalisimo_Notices::fingerprint( '<input type="hidden" name="_wpnonce" value="9876543210">' ), 'La huella por defecto no cambia con el nonce de un formulario.' );

// Plazos: advertencia, 7 días; error, 1 día.
foreach ( array( 'warning' => 7, 'error' => 1 ) as $type => $days ) {
	$h = Digitalisimo_Notices::html( 'plazo-' . $type, $type, '<p>Pide algo.</p>' );
	Digitalisimo_Notices::dismiss( 7, 'plazo-' . $type, $attr( $h, 'data-digitalisimo-notice-hash' ) );
	an_check( '' === Digitalisimo_Notices::html( 'plazo-' . $type, $type, '<p>Pide algo.</p>' ), "Una {$type} cerrada no sale enseguida." );
	$GLOBALS['an']['meta'][7][ Digitalisimo_Notices::META ][ 'plazo-' . $type ]['t'] = time() - ( $days * DAY_IN_SECONDS - 60 );
	an_check( '' === Digitalisimo_Notices::html( 'plazo-' . $type, $type, '<p>Pide algo.</p>' ), "Una {$type} cerrada sigue cerrada antes de {$days} día(s)." );
	$GLOBALS['an']['meta'][7][ Digitalisimo_Notices::META ][ 'plazo-' . $type ]['t'] = time() - ( $days * DAY_IN_SECONDS + 60 );
	an_check( '' !== Digitalisimo_Notices::html( 'plazo-' . $type, $type, '<p>Pide algo.</p>' ), "Una {$type} vuelve a los {$days} día(s) si el problema sigue." );
}
an_check( 7 * DAY_IN_SECONDS === Digitalisimo_Notices::snooze_for( 'warning' ) && DAY_IN_SECONDS === Digitalisimo_Notices::snooze_for( 'error' ) && 0 === Digitalisimo_Notices::snooze_for( 'info' ) && 0 === Digitalisimo_Notices::snooze_for( 'success' ), 'Plazos por tipo.' );

// Límite de cierres guardados por usuario: los más viejos salen.
for ( $i = 0; $i < Digitalisimo_Notices::LIMIT + 5; $i++ ) Digitalisimo_Notices::dismiss( 11, 'lote-' . $i, 'aaaaaaaaaaaa' );
an_check( Digitalisimo_Notices::LIMIT === count( $GLOBALS['an']['meta'][11][ Digitalisimo_Notices::META ] ) && isset( $GLOBALS['an']['meta'][11][ Digitalisimo_Notices::META ][ 'lote-' . ( Digitalisimo_Notices::LIMIT + 4 ) ] ), 'Como mucho LIMIT cierres por usuario, sin perder el último.' );

// Red: el cierre en la administración de la red vale en cada sitio (user meta, no opción del sitio).
$GLOBALS['an']['multi'] = true; $GLOBALS['an']['network_admin'] = true; $GLOBALS['an']['caps']['manage_network_options'] = true;
$net = Digitalisimo_Notices::html( 'red', 'warning', '<p>Algo de la red.</p>', array( 'cap' => 'manage_network_options' ) );
$r   = $ajax( array( 'id' => 'red', 'hash' => $attr( $net, 'data-digitalisimo-notice-hash' ), 'cap' => 'manage_network_options', 'nonce' => $attr( $net, 'data-digitalisimo-notice-nonce' ) ) );
an_check( $r && $r->ok, 'En la red, quien administra la red lo cierra.' );
$GLOBALS['an']['network_admin'] = false;
an_check( '' === Digitalisimo_Notices::html( 'red', 'warning', '<p>Algo de la red.</p>', array( 'cap' => 'manage_network_options' ) ), 'Cerrado en la red, tampoco sale en un sitio.' );
$GLOBALS['an']['multi'] = false; $GLOBALS['an']['caps']['manage_network_options'] = false;

/* ------------------------------------------------------------------ El aviso de la captura: Cache_Notice */

$notice_file = $root . '/digitalisimo-tools/includes/class-cache-notice.php';
final class Digitalisimo_Same_Site {
	public static $check = array( 'ok' => true, 'message' => '' );
	public static function check( $force = true ) { return self::$check; }
	public static function external() { return true; }
	public static function isolated() { return true; }
}
final class Digitalisimo_Cache_Salt {
	const SHOW = 86400;
	public static $result = array();
	public static function result() { return self::$result; }
	public static function isolated() { return true; }
}
require $notice_file;
Digitalisimo_Cache_Salt::$result = array( 'state' => 'active', 'salt' => 'dgs0_1a2b3c4d5e6f:', 'file' => '/srv/wp/wp-config.php', 'target' => 'config', 'done_at' => time() - 60 );
$cache = function () { ob_start(); \Digitalisimo\Tools\Cache_Notice::notice(); return ob_get_clean(); };
$html  = $cache();
an_check( false !== strpos( $html, 'DIGITALÍSIMO agregó una sal propia para la caché de objetos' ) && false !== strpos( $html, 'No hace falta hacer nada.' ), 'Se ve el aviso de la captura: ' . substr( strip_tags( $html ), 0, 120 ) );
an_check( false !== strpos( $html, 'notice-success' ) && false !== strpos( $html, 'is-dismissible' ), 'El aviso de la sal lleva la X (antes no se podía quitar).' );
an_check( 'manage_options' === $attr( $html, 'data-digitalisimo-notice-cap' ), 'Sólo quien gestiona el sitio lo cierra.' );
$r = $ajax( array( 'id' => $attr( $html, 'data-digitalisimo-notice' ), 'hash' => $attr( $html, 'data-digitalisimo-notice-hash' ), 'cap' => 'manage_options', 'nonce' => $attr( $html, 'data-digitalisimo-notice-nonce' ) ) );
an_check( $r && $r->ok, 'La X del aviso de la sal guarda el cierre.' );
an_check( '' === $cache(), 'Cerrado, el aviso de la sal ya no sale en la siguiente carga.' );
Digitalisimo_Cache_Salt::$result['state'] = 'written'; Digitalisimo_Cache_Salt::$result['at'] = time();
an_check( '' === $cache(), '«Vale desde la siguiente carga» y «ya está en vigor» son el mismo aviso: cerrado uno, no sale el otro.' );
Digitalisimo_Cache_Salt::$result = array( 'state' => 'active', 'salt' => 'dgs1_1a2b3c4d5e6f:', 'file' => '/srv/wp/wp-config.php', 'target' => 'config', 'done_at' => time() - 60 );
an_check( '' !== $cache(), 'Con otra sal (rotada o repuesta), vuelve a salir.' );
// «foreign» (la caché da datos de otro sitio): error que vuelve al día si sigue.
Digitalisimo_Cache_Salt::$result = array();
Digitalisimo_Same_Site::$check = array( 'ok' => false, 'message' => 'la caché de objetos de este servidor está devolviendo datos de otro sitio (otro.test)' );
$html = $cache();
an_check( false !== strpos( $html, 'notice-error' ) && false !== strpos( $html, 'is-dismissible' ), 'El error de la caché ajena también lleva la X.' );
Digitalisimo_Notices::dismiss( 7, $attr( $html, 'data-digitalisimo-notice' ), $attr( $html, 'data-digitalisimo-notice-hash' ) );
an_check( '' === $cache(), 'Cerrado, el error no sale enseguida.' );
$GLOBALS['an']['meta'][7][ Digitalisimo_Notices::META ][ $attr( $html, 'data-digitalisimo-notice' ) ]['t'] = time() - DAY_IN_SECONDS - 60;
an_check( '' !== $cache(), 'Al día, si la caché sigue dando datos de otro sitio, el error vuelve.' );
// Lo importante queda siempre en «Salud del sitio».
an_check( 'critical' === \Digitalisimo\Tools\Cache_Notice::health()['status'], 'La caché ajena sigue en «Salud del sitio» aunque se haya cerrado el aviso.' );
$GLOBALS['an']['screen'] = 'edit-post';
an_check( '' === $cache(), 'Fuera del Escritorio, Plugins y las pantallas DIGITALÍSIMO no sale.' );

an_done();
