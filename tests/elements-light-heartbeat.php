<?php
/**
 * DIGITALÍSIMO Elements: el latido del escritorio (heartbeat, cada 15-60 s por pestaña abierta) que no le pide nada al
 * constructor no carga el núcleo incluido ni sus opciones (auditoría del escritorio, segunda vuelta, 2026-11-20).
 *
 *  1. Un latido con sólo datos del núcleo de WordPress (sesión, bloqueo y nonces de la entrada, bloqueos de la lista) es
 *     liviano: Digitalisimo_Elements_Core::load() no carga elementor.php y la precarga de opciones no consulta.
 *  2. El del editor del constructor (`elementor_post_lock`), el autoguardado del editor clásico (`wp_autosave`), el de
 *     otro plugin, uno con datos que no son una lista, cualquier otra petición AJAX o del escritorio, un complemento del
 *     constructor activo (ruta con «elementor», en el sitio o en la red), la constante y el filtro lo cargan como siempre.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DOING_AJAX', true );

$GLOBALS['options'] = array( 'active_plugins' => array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php' ) );
$GLOBALS['site_options'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['primed'] = null;
$GLOBALS['ms'] = false;
function wp_doing_ajax() { return defined( 'DOING_AJAX' ) && DOING_AJAX; }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function get_site_option( $name, $default = false ) { return $GLOBALS['site_options'][ $name ] ?? $default; }
function is_multisite() { return $GLOBALS['ms']; }
function is_admin() { return true; }
function apply_filters( $hook, $value, ...$args ) { return isset( $GLOBALS['filters'][ $hook ] ) ? call_user_func( $GLOBALS['filters'][ $hook ], $value, ...$args ) : $value; }
function add_action() {} function add_filter() {}
function wp_using_ext_object_cache() { return false; }
function wp_installing() { return false; }
function wp_prime_option_caches( $names ) { $GLOBALS['primed'] = $names; }

$root = dirname( __DIR__ ) . '/digitalisimo-elements/';
require $root . 'digitalisimo-admin-load.php';
require $root . 'digitalisimo-core.php';

function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, 'FALLA: ' . $message . "\n" ); exit( 1 ); } }
$light = function ( array $request, $plugins = null ) { return Digitalisimo_Elements_Admin_Load::light_heartbeat( $request, $plugins ); };
$beat  = function ( array $data = array() ) { return array( 'action' => 'heartbeat', '_nonce' => 'n', 'screen_id' => 'dashboard', 'interval' => '60', 'has_focus' => 'true' ) + ( $data ? array( 'data' => $data ) : array() ); };

// 1. Con un latido liviano el núcleo no se carga y la precarga de opciones no consulta.
$GLOBALS['options']['digitalisimo_elements_option_prime'] = array( 'v' => '', 'names' => array( 'elementor_experiment-e_optimized_markup' ) );
$_REQUEST = $beat( array( 'wp-auth-check' => 'true' ) );
check( array() === Digitalisimo_Elements_Admin_Load::prime() && null === $GLOBALS['primed'], 'En el latido liviano no se precargan las opciones del constructor.' );
check( false === Digitalisimo_Elements_Core::load() && ! defined( 'ELEMENTOR_VERSION' ) && ! Digitalisimo_Elements_Core::bundled(), 'En el latido liviano el núcleo incluido no se carga.' );
check( Digitalisimo_Elements_Admin_Load::light_heartbeat(), 'La decisión se recuerda durante la petición.' );

// 2. Latidos del escritorio que el constructor no atiende.
check( $light( $beat() ), 'Un latido sin datos es liviano.' );
check( $light( $beat( array( 'wp-auth-check' => 'true' ) ) ), 'El latido del Escritorio (sólo la sesión) es liviano.' );
check( $light( $beat( array( 'wp-auth-check' => 'true', 'wp-refresh-post-lock' => array( 'post_id' => '7', 'lock' => '1:1' ), 'wp-refresh-post-nonces' => array( 'post_id' => '7' ) ) ) ), 'El latido del editor de WordPress (bloqueo y nonces de la entrada) es liviano.' );
check( $light( $beat( array( 'wp-check-locked-posts' => array( 'post-1' ) ) ) ), 'El de la lista de entradas (bloqueos) es liviano.' );

// 3. Los que lo cargan como siempre.
check( ! $light( $beat( array( 'elementor_post_lock' => array( 'post_ID' => '7' ) ) ) ), 'El latido del editor del constructor lo carga.' );
check( ! $light( $beat( array( 'wp-auth-check' => 'true', 'elementor_force_post_lock' => '1' ) ) ), 'Cualquier dato del constructor lo carga.' );
check( ! $light( $beat( array( 'wp_autosave' => array( 'post_id' => '7' ) ) ) ), 'El autoguardado del editor clásico lo carga (el constructor copia sus datos a la revisión).' );
check( ! $light( $beat( array( 'wp-auth-check' => 'true', 'otro-plugin' => '1' ) ) ), 'El latido con datos de otro plugin lo carga.' );
check( ! $light( array( 'action' => 'heartbeat', 'data' => 'wp-auth-check' ) ), 'Datos que no son una lista: se carga.' );
check( ! $light( array( 'action' => 'elementor_ajax' ) ) && ! $light( array( 'action' => 'query-attachments' ) ) && ! $light( array() ), 'Otra petición AJAX lo carga.' );
check( ! $light( $beat(), array( 'digitalisimo-elements/pro-elements.php', 'essential-addons-for-elementor-lite/essential_adons_elementor.php' ) ), 'Con un complemento del constructor activo se carga.' );
check( ! $light( $beat(), array( 'Header-Footer-Elementor/header-footer-elementor.php' ) ), 'La ruta se compara sin mayúsculas.' );
$GLOBALS['ms'] = true; $GLOBALS['site_options']['active_sitewide_plugins'] = array( 'ultimate-elementor/ultimate-elementor.php' => 1 );
check( ! $light( $beat() ), 'Un complemento del constructor activo en la red también lo carga.' );
$GLOBALS['ms'] = false; $GLOBALS['site_options'] = array();
check( $light( $beat() ), 'Los plugins activos se leen de active_plugins (sin complementos: liviano).' );
$GLOBALS['filters']['digitalisimo_elements_light_heartbeat'] = '__return_false';
function __return_false() { return false; }
check( ! $light( $beat() ), 'El filtro lo desactiva.' );
unset( $GLOBALS['filters']['digitalisimo_elements_light_heartbeat'] );

// 4. El orden en load(): el plugin original manda antes que nada; después, el latido liviano; después, el núcleo.
$src = (string) file_get_contents( $root . 'digitalisimo-core.php' );
$at  = strpos( $src, 'public static function load()' );
$body = substr( $src, $at, (int) strpos( $src, "require self::dir() . 'elementor.php';", $at ) - $at );
check( false !== strpos( $body, 'Digitalisimo_Elements_Admin_Load::light_heartbeat()' ) && strpos( $body, 'self::external_active()' ) < strpos( $body, 'light_heartbeat()' ), 'load() decide el latido liviano después de comprobar el plugin original y antes de cargar el núcleo.' );

echo "DIGITALÍSIMO Elements: el latido del escritorio que no pide nada al constructor no lo carga.\n";
