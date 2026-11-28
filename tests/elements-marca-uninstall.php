<?php
/**
 * Marca propia (N-5 de la segunda revisión, docs/MARCA_PROPIA.md): al desinstalar DIGITALÍSIMO Elements se borran sus
 * opciones (la del sitio, la de la red y la cuenta del MCP viejo), el transitorio de complementos y lo que deja el paquete
 * transformado (registro de despliegue y tarea de limpieza), en un sitio suelto y en cada sitio de una red; lo demás del
 * constructor se conserva. Corre `digitalisimo-elements/uninstall.php` en un proceso hijo por caso, sin WordPress.
 * Falla con el código anterior: Elements no trae uninstall.php.
 */
$GLOBALS['fails']  = 0;
$GLOBALS['checks'] = 0;
function check( $ok, $what, $detail = '' ) {
	$GLOBALS['checks']++;
	if ( $ok ) {
		echo "ok · $what\n";
	} else {
		$GLOBALS['fails']++;
		echo "FALLA · $what" . ( '' !== $detail ? ' · ' . substr( is_string( $detail ) ? $detail : json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 0, 900 ) : '' ) . "\n";
	}
}

$file = dirname( __DIR__ ) . '/digitalisimo-elements/uninstall.php';

// Proceso hijo: dobles de WordPress, el uninstall.php y el estado final en JSON.
if ( isset( $argv[1] ) && in_array( $argv[1], array( '--sitio', '--red', '--sin-constante' ), true ) ) {
	if ( '--sin-constante' !== $argv[1] ) {
		define( 'WP_UNINSTALL_PLUGIN', 'digitalisimo-elements/pro-elements.php' );
	}
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['ms']   = '--red' === $argv[1];
	$GLOBALS['blog'] = 1;
	$keep            = array( 'elementor_cpt_support' => array( 'page' ), 'digitalisimo_elements_front' => array( 'svg_icons' => true ) );
	$mine            = array(
		'digitalisimo_elements_marca'             => array( 'mode' => 'on' ),
		'digitalisimo_elements_mcp_legacy'        => array( 'last' => '2026-11-27' ),
		'digitalisimo_elements_marca_despliegue'  => array( 'fails' => 1 ),
		'_transient_digitalisimo_elements_marca_addons' => array( 'list' => array() ),
	);
	$GLOBALS['db']    = array(
		1 => $keep + $mine,
		2 => $keep + $mine,
	);
	$GLOBALS['site']  = array(
		'digitalisimo_elements_marca_network' => array( 'mode' => 'on' ),
		'digitalisimo_elements_front_defaults' => array(),
	);
	$GLOBALS['crons'] = array( 1 => array( 'digitalisimo_elements_marca_limpiar' => 1, 'otra' => 1 ), 2 => array( 'digitalisimo_elements_marca_limpiar' => 1 ) );
	function is_multisite() {
		return $GLOBALS['ms'];
	}
	function get_sites( $a = array() ) {
		return array( '1', '2' );
	}
	function switch_to_blog( $id ) {
		$GLOBALS['blog'] = $id;
	}
	function restore_current_blog() {
		$GLOBALS['blog'] = 1;
	}
	function delete_option( $k ) {
		unset( $GLOBALS['db'][ $GLOBALS['blog'] ][ $k ] );
		return true;
	}
	function delete_transient( $k ) {
		return delete_option( '_transient_' . $k );
	}
	function delete_site_option( $k ) {
		unset( $GLOBALS['site'][ $k ] );
		return true;
	}
	function wp_clear_scheduled_hook( $h ) {
		unset( $GLOBALS['crons'][ $GLOBALS['blog'] ][ $h ] );
		return 1;
	}
	// Al terminar (también si uninstall.php sale antes con exit), el estado en JSON.
	register_shutdown_function(
		static function () {
			echo json_encode(
				array(
					'db'    => array_map( 'array_keys', $GLOBALS['db'] ),
					'site'  => array_keys( $GLOBALS['site'] ),
					'crons' => array_map( 'array_keys', $GLOBALS['crons'] ),
				)
			) . "\n";
		}
	);
	include $file;
	exit( 0 );
}

if ( ! is_file( $file ) ) {
	echo "FALLA no existe digitalisimo-elements/uninstall.php (código anterior)\n";
	exit( 1 );
}
$run = static function ( $mode ) {
	$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . $mode . ' 2>&1' );
	$row = json_decode( trim( (string) $out ), true );
	return is_array( $row ) ? $row : (string) $out;
};
$keep = array( 'elementor_cpt_support', 'digitalisimo_elements_front' );

$solo = $run( '--sitio' );
check( is_array( $solo ) && $keep === $solo['db'][1], 'U1 en un sitio suelto se borran la marca, la cuenta del MCP viejo, el despliegue y el transitorio; lo demás se conserva', $solo );
check( is_array( $solo ) && array( 'otra' ) === $solo['crons'][1], 'U1 y la tarea de limpieza de la marca (no las demás)', $solo );

$red = $run( '--red' );
check( is_array( $red ) && $keep === $red['db'][1] && $keep === $red['db'][2], 'U2 en una red, en cada sitio', $red );
check( is_array( $red ) && array( 'digitalisimo_elements_front_defaults' ) === $red['site'], 'U2 y la opción de red de la marca (la de otros ajustes de red, no)', $red );
check( is_array( $red ) && array() === $red['crons'][2], 'U2 y la tarea de limpieza en cada sitio', $red );

$none = $run( '--sin-constante' );
check( is_array( $none ) && count( $none['db'][1] ) === 6, 'U3 sin WP_UNINSTALL_PLUGIN (pedido por URL) no borra nada', $none );

echo "\n$GLOBALS[fails] fallos de $GLOBALS[checks] comprobaciones\n";
exit( $GLOBALS['fails'] ? 1 : 0 );
