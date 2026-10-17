<?php
/**
 * Diseño compartido de los plugins (digitalisimo-ui/digitalisimo-ui.php): con varias copias incluidas se registra una
 * sola hoja, la de la versión más alta (comparada como versión, no como texto), aunque la función que la registra sea
 * la de otra copia; se encola con la clase de body sólo en las pantallas ?page=digitalisimo*, y otras hojas pueden
 * depender de ella sin que WordPress las descarte.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_ui( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['t'] = array( 'hooks' => array(), 'registered' => array(), 'enqueued' => array(), 'init' => false );
	function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['t']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function add_filter( $hook, $callback, $priority = 10 ) { $GLOBALS['t']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function did_action( $hook ) { return 'init' === $hook && $GLOBALS['t']['init']; }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
	function wp_unslash( $value ) { return $value; }
	function plugins_url( $path, $file ) { return 'https://sitio.test' . dirname( $file ) . '/' . $path; }
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['t']['registered'][ $handle ] = array( $src, $ver ); }
	function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['t']['registered'][ $handle ] ) : in_array( $handle, $GLOBALS['t']['enqueued'], true ); }
	function wp_enqueue_style( $handle ) { $GLOBALS['t']['enqueued'][] = $handle; }

	$modules = array( 'digitalisimo-seo', 'digitalisimo.chatbot', 'digitalisimo-hosting', 'digitalisimo-backups', 'digitalisimo-tools', 'digitalisimo-elements', 'digitalisimo-migration' );
	foreach ( $modules as $module ) {
		require __DIR__ . '/../' . $module . '/digitalisimo-ui/digitalisimo-ui.php';
	}
	check_ui( 1 === count( $GLOBALS['t']['hooks']['admin_enqueue_scripts'] ) && 1 === count( $GLOBALS['t']['hooks']['admin_body_class'] ) && 5 === $GLOBALS['t']['hooks']['admin_enqueue_scripts'][0][1], 'Con los siete plugins activos, el estilo se engancha una sola vez y antes que las hojas propias.' );
	$version = array_key_last( $GLOBALS['digitalisimo_ui_copies'] );
	check_ui( 1 === count( $GLOBALS['digitalisimo_ui_copies'] ) && preg_match( '/^\d+\.\d+\.\d+$/', (string) $version ), 'Las siete copias anuncian la misma versión.' );

	// Sin init todavía, ninguna hoja propia declara la dependencia (WordPress la descartaría si no se registra).
	check_ui( array() === digitalisimo_ui_dependency(), 'Antes de init no se ofrece una dependencia sin registrar.' );

	// Un plugin actualizado trae una copia más nueva: gana aunque se haya incluido después y aunque 1.0.10 < 1.0.9 como texto.
	$GLOBALS['digitalisimo_ui_copies']['1.0.9']  = '/wp/plugins/viejo/digitalisimo-ui';
	$GLOBALS['digitalisimo_ui_copies']['1.0.10'] = '/wp/plugins/nuevo/digitalisimo-ui';
	check_ui( array( '1.0.10', '/wp/plugins/nuevo/digitalisimo-ui' ) === digitalisimo_ui_copy(), 'La copia de versión más alta es la que se sirve.' );

	$_GET['page'] = 'elementor';
	digitalisimo_ui_enqueue();
	check_ui( array( 'https://sitio.test/wp/plugins/nuevo/digitalisimo-ui/digitalisimo-ui.css', '1.0.10' ) === $GLOBALS['t']['registered']['digitalisimo-ui'] && array() === $GLOBALS['t']['enqueued'], 'En toda la administración queda registrada la copia más nueva, sin encolarla fuera de las pantallas propias.' );
	check_ui( 'wp-admin folded' === digitalisimo_ui_body_class( 'wp-admin folded' ), 'Fuera de las pantallas propias no cambia la clase de body.' );

	$_GET['page'] = 'digitalisimo-tools-elementor-templates';
	digitalisimo_ui_enqueue();
	check_ui( array( 'digitalisimo-ui' ) === $GLOBALS['t']['enqueued'], 'En las pantallas ?page=digitalisimo* se encola la hoja compartida.' );
	check_ui( 'wp-admin digitalisimo-admin' === digitalisimo_ui_body_class( 'wp-admin' ) && 'wp-admin digitalisimo-admin' === digitalisimo_ui_body_class( 'wp-admin digitalisimo-admin' ), 'La clase digitalisimo-admin se añade una sola vez.' );

	$GLOBALS['t']['init'] = true;
	check_ui( array( 'digitalisimo-ui' ) === digitalisimo_ui_dependency(), 'Después de init, las hojas propias dependen de la compartida.' );
	unset( $GLOBALS['t']['registered']['digitalisimo-ui'] );
	check_ui( array( 'digitalisimo-ui' ) === digitalisimo_ui_dependency() && isset( $GLOBALS['t']['registered']['digitalisimo-ui'] ), 'Pedir la dependencia registra la hoja si aún no lo estaba (editor, App).' );
	echo "Diseño compartido: una sola hoja, la copia más nueva, en las pantallas propias.\n";
}
