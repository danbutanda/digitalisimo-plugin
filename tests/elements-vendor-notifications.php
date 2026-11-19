<?php
/**
 * SDK de novedades del proveedor (wp-notifications-package, copia del núcleo incluido): Hello y otros productos del
 * proveedor lo instancian con la copia de DIGITALÍSIMO Elements y piden sus novedades al proveedor (api/v1/notifications) cada
 * 12 h en `admin_init`, al dejar el tema (`switch_theme`) y al desactivar un plugin (`deactivate_{plugin}`). Con el
 * núcleo incluido, Digitalisimo_Elements_Vendor_Free quita esas tres llamadas antes de que corran (sin intento de
 * petición ni clave de conexión nueva) y deja todo lo demás de esos ganchos. Origen: auditoría del escritorio
 * (2026-11-18), visto por la prueba completa de Migración con Hello activo.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', realpath( __DIR__ . '/../digitalisimo-elements/pro-elements.php' ) );
	$checks = 0;
	function check( $ok, $message ) {
		global $checks;
		if ( ! $ok ) {
			fwrite( STDERR, "FALLA: $message\n" );
			exit( 1 );
		}
		++$checks;
	}
	/** Ganchos de WordPress mínimos: prioridad → callbacks, como WP_Hook. */
	final class Fake_Hook {
		public $callbacks = array();
	}
	$GLOBALS['wp_filter'] = array();
	$GLOBALS['current']   = '';
	function add_action( $hook, $function, $priority = 10, $args = 1 ) {
		if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
			$GLOBALS['wp_filter'][ $hook ] = new Fake_Hook();
		}
		$GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][] = array( 'function' => $function, 'accepted_args' => $args );
		return true;
	}
	function add_filter( ...$a ) { return add_action( ...$a ); }
	function remove_action( $hook, $function, $priority = 10 ) {
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ] ?? array() as $i => $cb ) {
			if ( $cb['function'] === $function ) {
				unset( $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][ $i ] );
				return true;
			}
		}
		return false;
	}
	function current_filter() { return $GLOBALS['current']; }
	function register_deactivation_hook( $file, $function ) { add_action( 'deactivate_' . $file, $function ); }
	function sanitize_title( $t ) { return strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', (string) $t ) ); }
	function wp_normalize_path( $p ) { return str_replace( '\\', '/', (string) $p ); }
	final class Digitalisimo_Elements_Core {
		public static function bundled() { return true; }
	}
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-vendor-free.php';
	$g   = 'Digitalisimo_Elements_Vendor_Free';
	$sdk = 'Elementor\\WPNotificationsPackage\\V120\\Notifications';

	check( ! $g::bundled_notifications_sdk(), 'Sin el SDK cargado no hay nada que quitar.' );
	// La copia del núcleo incluido, como la carga Hello con ELEMENTOR_PATH . 'vendor/autoload.php'.
	require __DIR__ . '/../digitalisimo-elements/elementor/vendor/elementor/wp-notifications-package/src/V120/Notifications.php';
	check( $g::bundled_notifications_sdk(), 'La clase del SDK cargada es la copia del núcleo incluido.' );

	// Lo que hace Hello al cargarse (su Notificator) y lo que haría un plugin del proveedor, más ganchos ajenos.
	$other = static function () {};
	add_action( 'admin_init', $other );
	add_action( 'switch_theme', $other, 10, 3 );
	$hello  = new $sdk( array( 'app_name' => 'hello-elementor', 'app_version' => '3.5.1', 'short_app_name' => 'hello-elementor', 'app_data' => array( 'theme_name' => 'hello-elementor' ) ) );
	$plugin = new $sdk( array( 'app_name' => 'otro', 'app_version' => '1.0', 'app_data' => array( 'plugin_basename' => 'otro/otro.php' ) ) );
	$count  = static function ( $hook ) {
		return array_sum( array_map( 'count', $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() ) );
	};
	check( 3 === $count( 'admin_init' ) && 2 === $count( 'switch_theme' ) && 1 === $count( 'deactivate_otro/otro.php' ), 'El SDK engancha sus peticiones (antes de quitarlas).' );

	// admin_init: corre con prioridad 0 y quita sólo las del SDK; las ajenas siguen.
	$GLOBALS['current'] = 'admin_init';
	check( 2 === $g::drop_notifications(), 'En admin_init se quitan las dos peticiones de novedades del SDK (Hello y el plugin).' );
	check( 1 === $count( 'admin_init' ) && $other === reset( $GLOBALS['wp_filter']['admin_init']->callbacks[10] )['function'], 'El resto de admin_init sigue enganchado.' );
	check( 0 === $g::drop_notifications(), 'Una segunda pasada no quita nada más.' );

	// switch_theme: la baja de Hello al cambiar de tema.
	$GLOBALS['current'] = 'switch_theme';
	check( 1 === $g::drop_notifications() && 1 === $count( 'switch_theme' ), 'Al dejar Hello no se avisa al proveedor y lo demás de switch_theme sigue.' );

	// deactivate_plugin: la baja de un plugin con el SDK.
	check( 0 === $g::drop_plugin_notifications( 'ajeno/ajeno.php' ) && 1 === $g::drop_plugin_notifications( 'otro/otro.php' ) && 0 === $count( 'deactivate_otro/otro.php' ), 'Al desactivar un plugin con el SDK no se avisa al proveedor; otro plugin no se toca.' );

	// Un gancho que no es de novedades, o un método del mismo nombre de otra clase, no se toca.
	$GLOBALS['current'] = 'init';
	add_action( 'init', array( $hello, 'refresh_notifications' ) );
	check( 0 === $g::drop_notifications() && 1 === $count( 'init' ), 'Fuera de sus tres ganchos no se quita nada.' );

	// Engancha los tres puntos en init() (con el núcleo incluido), cada uno antes que el SDK (prioridad 0).
	$GLOBALS['wp_filter'] = array();
	$g::init();
	foreach ( array( 'admin_init', 'switch_theme', 'deactivate_plugin' ) as $hook ) {
		$hooked = false;
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks[0] ?? array() as $cb ) {
			$hooked = $hooked || ( is_array( $cb['function'] ) && $g === $cb['function'][0] && false !== strpos( $cb['function'][1], 'notifications' ) );
		}
		check( $hooked, "Con el núcleo incluido se engancha en $hook con prioridad 0." );
	}
	echo "DIGITALÍSIMO Elements: SDK de novedades del proveedor sin peticiones validado ($checks comprobaciones).\n";
}
