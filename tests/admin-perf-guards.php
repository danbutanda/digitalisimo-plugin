<?php
/**
 * Guardas de la auditoría admin-perf (2026-11-18) que necesitan un proceso propio, sin WordPress:
 *   1. SEO e IA Tools: el POST de metaboxes del editor de bloques (post.php?meta-box-loader=1) termina con un 200 vacío
 *      en lugar de seguir la redirección al editor completo; cualquier otra petición conserva su redirección;
 *   2. SEO: ensure_autoload() pasa a autoload la opción principal y el estado del favicon cuando se guardaron sin él,
 *      sólo los que existen y una vez;
 *   3. SEO: ninguna ruta vuelve a guardar la opción principal ni el estado del favicon sin autoload.
 * Uso: php tests/admin-perf-guards.php   (los casos 1 y 2 corren en procesos hijos: el 1 termina con exit).
 */
$root = dirname( __DIR__ );
$mode = $argv[1] ?? '';
if ( '' !== $mode ) {
	define( 'ABSPATH', __DIR__ );
	function is_admin() { return true; }
	function status_header( $code ) { echo "STATUS $code\n"; }
	function nocache_headers() {}
	if ( 'seo-metabox' === $mode || 'ia-metabox' === $mode ) {
		$class = 'seo-metabox' === $mode ? 'Digitalisimo_Integrations_SEO_Suite' : 'Digitalisimo_AI';
		require $root . ( 'seo-metabox' === $mode ? '/digitalisimo-seo/includes/class-seo-suite.php' : '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php' );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		if ( 'post.php?post=5&message=1' !== $class::metabox_saved( 'post.php?post=5&message=1' ) ) { echo "CAMBIA OTRA REDIRECCION\n"; exit( 3 ); }
		$_GET['meta-box-loader'] = '1'; $_SERVER['REQUEST_METHOD'] = 'GET';
		if ( 'x' !== $class::metabox_saved( 'x' ) ) { echo "CAMBIA UN GET\n"; exit( 3 ); }
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$GLOBALS['digi_waiting_exit'] = true;
		register_shutdown_function( function() { if ( ! empty( $GLOBALS['digi_waiting_exit'] ) ) echo "TERMINO\n"; } );
		$class::metabox_saved( 'post.php?post=5&action=edit&message=1' );
		$GLOBALS['digi_waiting_exit'] = false;
		echo "REDIRIGE\n"; exit( 3 );
	}
	if ( 'autoload' === $mode ) {
		$GLOBALS['options'] = array( 'digitalisimo_integrations_options' => array( 'enable_seo' => 1 ), 'digitalisimo_favicon_state' => array( 'version' => 'abc' ) );
		$GLOBALS['autoloaded'] = array();
		$GLOBALS['calls'] = array();
		function wp_load_alloptions() { return array_intersect_key( $GLOBALS['options'], array_flip( $GLOBALS['autoloaded'] ) ); }
		function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
		function wp_set_option_autoload_values( $values ) { $GLOBALS['calls'][] = $values; foreach ( $values as $k => $v ) if ( $v ) $GLOBALS['autoloaded'][] = $k; return array(); }
		require $root . '/digitalisimo-seo/includes/class-settings.php';
		Digitalisimo_Integrations_Settings::ensure_autoload();
		Digitalisimo_Integrations_Settings::ensure_autoload();
		echo json_encode( $GLOBALS['calls'] ) . "\n";
		unset( $GLOBALS['options']['digitalisimo_favicon_state'] ); $GLOBALS['autoloaded'] = array(); $GLOBALS['calls'] = array();
		Digitalisimo_Integrations_Settings::ensure_autoload();
		echo json_encode( $GLOBALS['calls'] ) . "\n";
		exit( 0 );
	}
	exit( 2 );
}

$failures = array();
$fail = function( $m ) use ( &$failures ) { $failures[] = $m; fwrite( STDERR, "FALLA: $m\n" ); };
$run = function( $mode ) { $out = array(); exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $mode ) . ' 2>&1', $out, $code ); return array( implode( "\n", $out ), $code ); };

// 1. Metaboxes del editor de bloques.
foreach ( array( 'seo-metabox' => 'SEO', 'ia-metabox' => 'IA Tools' ) as $mode => $label ) {
	list( $out, $code ) = $run( $mode );
	if ( 0 !== $code || false === strpos( $out, 'TERMINO' ) || false === strpos( $out, 'STATUS 200' ) ) $fail( "$label: el POST de metaboxes del editor de bloques debe terminar con un 200 vacío, sin redirigir al editor (salida: " . trim( $out ) . ", código $code)." );
	if ( false !== strpos( $out, 'CAMBIA' ) ) $fail( "$label: cambió una redirección que no es la de los metaboxes del editor de bloques: " . trim( $out ) );
}

// 2. ensure_autoload().
list( $out, $code ) = $run( 'autoload' );
$lines = explode( "\n", trim( $out ) );
if ( 0 !== $code || '[{"digitalisimo_integrations_options":true,"digitalisimo_favicon_state":true}]' !== ( $lines[0] ?? '' ) ) $fail( 'ensure_autoload() debe pasar a autoload, una sola vez, la opción principal y el estado del favicon guardados sin él: ' . trim( $out ) );
if ( '[{"digitalisimo_integrations_options":true}]' !== ( $lines[1] ?? '' ) ) $fail( 'ensure_autoload() sólo toca lo que existe: ' . ( $lines[1] ?? '' ) );

// 3. Ninguna ruta guarda la opción principal ni el estado del favicon sin autoload.
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/digitalisimo-seo/includes', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	if ( '.php' !== substr( (string) $file, -4 ) ) continue;
	$code = (string) file_get_contents( (string) $file );
	$self_main = (bool) preg_match( "/const\\s+OPTION\\s*=\\s*'digitalisimo_integrations_options'/", $code );
	if ( preg_match_all( '/update_option\(\s*(Digitalisimo_Integrations_Settings::OPTION|self::OPTION|self::STATE)\s*,[^;]*?,\s*false\s*\)\s*;/', $code, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			$main = 'Digitalisimo_Integrations_Settings::OPTION' === $hit[1] || ( 'self::OPTION' === $hit[1] && $self_main ) || ( 'self::STATE' === $hit[1] && false !== strpos( $code, "'digitalisimo_favicon_state'" ) );
			if ( $main ) $fail( basename( (string) $file ) . ': guarda la opción principal o el estado del favicon sin autoload (' . trim( substr( $hit[0], 0, 90 ) ) . ').' );
		}
	}
}
if ( $failures ) exit( 1 );
echo "admin-perf: metaboxes sin recargar el editor, autoload de la opción principal y del favicon, y ninguna ruta que lo quite.\n";
