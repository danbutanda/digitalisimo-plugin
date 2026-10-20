<?php
/**
 * DIGITALÍSIMO Tools · Código PHP: guardián en subprocesos. Un E_COMPILE_ERROR no se puede atrapar, así que cada caso
 * corre en un PHP aparte (proc_open) con un WordPress mínimo de stubs y deja en un archivo JSON todo lo que escribió.
 *
 * En CI se usa PHP_BINARY. En php-wasm, PHP_BINARY es un guion de shell que llama a node sin reenviar los argumentos: se
 * usa la orden que contiene (child_command()), o la que se indique con DIGITALISIMO_PHP_CHILD="node …/php-wasm-cli". Se
 * juzga por la última línea («… OK»).
 */
namespace Digitalisimo\Tools\Php_Snippets {
	// Sólo en los casos de prueba: el contexto que devolvería Tester::claim() tras validar el token.
	if ( 0 === strpos( (string) ( $GLOBALS['argv'][2] ?? '' ), 'test_' ) ) {
		final class Tester {
			public static $context = null;
			public static function claim( $raw ) { return 'tok' === $raw ? self::$context : null; }
		}
	}
}

namespace {
	error_reporting( E_ALL );

	/* =================================================================== *
	 * Hijo
	 * =================================================================== */
	if ( 'child' === ( $argv[1] ?? '' ) ) {
		$scenario = (string) $argv[2];
		$root     = (string) $argv[3];
		define( 'ABSPATH', $root . '/' );
		define( 'WPINC', 'wp-includes' );
		$GLOBALS['state_file'] = $root . '/state-' . $scenario . '.json';
		$GLOBALS['state']      = array( 'rows' => array(), 'writes' => array(), 'transients' => array(), 'marks' => array(), 'notices' => array(), 'mails' => array(), 'events' => array() );
		$GLOBALS['w']          = array( 'multisite' => false, 'blog' => 1, 'stack' => array(), 'hooks' => array(), 'filters' => array(), 'opts' => array(), 'sitemeta' => array() );

		function save_state() { file_put_contents( $GLOBALS['state_file'], json_encode( $GLOBALS['state'] ) ); }
		function mark( $m ) { $GLOBALS['state']['marks'][] = $m; save_state(); }
		function write( $what ) { $GLOBALS['state']['writes'][] = $what; save_state(); }

		class WP_Error { public $m; public function __construct( $c = '', $m = '' ) { $this->m = $m; } }
		function is_wp_error( $v ) { return $v instanceof WP_Error; }
		final class Fake_WPDB {
			public function get_blog_prefix( $blog = null ) { $blog = null === $blog ? $GLOBALS['w']['blog'] : (int) $blog; return 1 === $blog ? 'wp_' : 'wp_' . $blog . '_'; }
			public function __get( $n ) { return 'options' === $n ? $this->get_blog_prefix() . 'options' : null; }
			public function prepare( $q, ...$args ) { return array( $q, $args ); }
			public function query( $p ) {
				list( $q, $args ) = $p;
				if ( 0 === strpos( $q, 'INSERT IGNORE INTO `' ) && preg_match( '/^INSERT IGNORE INTO `(\w+)`/', $q, $m ) ) {
					if ( isset( $GLOBALS['state']['rows'][ $m[1] ][ $args[0] ] ) ) return 0;
					$GLOBALS['state']['rows'][ $m[1] ][ $args[0] ] = array( 'v' => $args[1], 'a' => $args[2], 'blog' => $GLOBALS['w']['blog'], 'switched' => count( $GLOBALS['w']['stack'] ) );
					save_state();
					return 1;
				}
				write( 'sql: ' . $q );
				return 0;
			}
		}
		$GLOBALS['wpdb'] = new Fake_WPDB();
		function wp_cache_delete( $k, $g = '' ) { return true; }
		function wp_autoload_values_to_autoload() { return array( 'yes', 'on', 'auto-on', 'auto' ); }
		function wp_load_alloptions() { return $GLOBALS['w']['opts'][ $GLOBALS['w']['blog'] ] ?? array(); }
		function get_option( $k, $d = false ) { return $GLOBALS['w']['opts'][ $GLOBALS['w']['blog'] ][ $k ] ?? $d; }
		function get_site_option( $k, $d = false ) { return $GLOBALS['w']['sitemeta'][ $k ] ?? $d; }
		function update_option( $k ) { write( 'update_option ' . $k ); return true; }
		function delete_option( $k ) { write( 'delete_option ' . $k ); return true; }
		function update_site_option( $k ) { write( 'update_site_option ' . $k ); return true; }
		function set_transient( $k, $v ) { $GLOBALS['state']['transients'][ 'site:' . $k ] = $v; save_state(); return true; }
		function set_site_transient( $k, $v ) { $GLOBALS['state']['transients'][ 'network:' . $k ] = $v; save_state(); return true; }
		function wp_mail() { $GLOBALS['state']['mails'][] = 1; save_state(); return true; }
		function is_multisite() { return $GLOBALS['w']['multisite']; }
		function get_current_blog_id() { return $GLOBALS['w']['blog']; }
		function get_current_network_id() { return 1; }
		function get_main_site_id() { return 1; }
		function switch_to_blog( $id ) { $GLOBALS['w']['stack'][] = $GLOBALS['w']['blog']; $GLOBALS['w']['blog'] = (int) $id; return true; }
		function restore_current_blog() { if ( ! $GLOBALS['w']['stack'] ) return false; $GLOBALS['w']['blog'] = array_pop( $GLOBALS['w']['stack'] ); return true; }
		function ms_is_switched() { return ! empty( $GLOBALS['w']['stack'] ); }
		function add_filter( $tag, $cb, $priority = 10, $args = 1 ) { $GLOBALS['w']['hooks'][ $tag ][ $priority ][] = array( $cb, $args ); return true; }
		function add_action( $tag, $cb, $priority = 10, $args = 1 ) { return add_filter( $tag, $cb, $priority, $args ); }
		function apply_filters( $tag, $value, ...$rest ) {
			$hooks = $GLOBALS['w']['hooks'][ $tag ] ?? array();
			ksort( $hooks );
			$GLOBALS['w']['filters'][] = $tag;
			foreach ( $hooks as $list ) foreach ( $list as $h ) $value = call_user_func_array( $h[0], array_slice( array_merge( array( $value ), $rest ), 0, max( 1, $h[1] ) ) );
			array_pop( $GLOBALS['w']['filters'] );
			return $value;
		}
		function do_action( $tag, ...$args ) {
			if ( 'digitalisimo_tools_php_event' === $tag ) { $GLOBALS['state']['events'][] = $args[0]; save_state(); }
			apply_filters( $tag, ...( $args ?: array( null ) ) );
		}
		function current_filter() { return end( $GLOBALS['w']['filters'] ); }
		function wp_salt( $s ) { return 'sal-' . $s; }
		function wp_unslash( $v ) { return stripslashes( $v ); }
		function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
		function is_user_logged_in() { return false; }
		function get_current_user_id() { return 0; }
		function current_user_can() { return false; }
		function is_protected_endpoint() { return false; }
		function wp_convert_hr_to_bytes( $v ) { $n = (int) $v; $u = strtoupper( substr( (string) $v, -1 ) ); return 'G' === $u ? $n << 30 : ( 'M' === $u ? $n << 20 : ( 'K' === $u ? $n << 10 : $n ) ); }

		$base = getenv( 'DIGITALISIMO_TOOLS_SOURCE' ) ?: __DIR__ . '/../digitalisimo-tools';
		foreach ( array( 'class-store.php', 'class-stream.php', 'class-guard.php', 'class-runner.php' ) as $file ) require $base . '/modules/php-snippets/' . $file;
		$S = 'Digitalisimo\\Tools\\Php_Snippets\\Store';

		function item( $scope, $id, $code, $rev = 1 ) {
			$S    = 'Digitalisimo\\Tools\\Php_Snippets\\Store';
			$hash = hash( 'sha256', $code );
			$item = array( 'title' => 'F' . $id, 'code' => $code, 'hash' => $hash, 'sig' => $S::sign( $scope, $id, $hash, $rev ), 'at' => 1, 'by' => 1 );
			if ( 'network' === $scope ) $item['rev'] = $rev;
			return $item;
		}
		function site_run( array $items ) {
			$GLOBALS['w']['opts'][ $GLOBALS['w']['blog'] ]['digitalisimo_tools_php_on'] = '1';
			$GLOBALS['w']['opts'][ $GLOBALS['w']['blog'] ]['digitalisimo_tools_php_run'] = array( 'v' => 1, 'kid' => '', 'safe' => false, 'items' => $items, 'paused' => array() );
		}
		function go() { \Digitalisimo\Tools\Php_Snippets\Runner::run(); mark( 'loaded' ); }
		function path_of( $scope, $id, $code ) { return \Digitalisimo\Tools\Php_Snippets\Stream::path( $scope, $id, hash( 'sha256', $code ) ); }
		$GLOBALS['w']['opts'][1]['stylesheet'] = 'tema';
		$GLOBALS['w']['opts'][2]['stylesheet'] = 'tema';
		save_state();

		$dup = "<?php\nfunction digi_twice() {}\nfunction digi_twice() {}\n";
		switch ( $scenario ) {
			case 'compile':
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', $dup ) ) );
				go();
				break;
			case 'throwable':
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\n\nthrow new LogicException( 'al cargar' );\n" ), 'aaaa0002' => item( 'site', 'aaaa0002', "<?php\nmark( 'ran-2' );\n" ) ) );
				go();
				break;
			case 'memory':
				ini_set( 'memory_limit', '32M' );
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\n\$a = array();\nwhile ( true ) \$a[] = str_repeat( 'x', 100000 );\n" ) ) );
				go();
				break;
			case 'time':
				set_time_limit( 1 );
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\n\$t = microtime( true );\nwhile ( microtime( true ) - \$t < 4 ) { \$x = sqrt( 2 ); }\nmark( 'no-timeout' );\n" ) ) );
				go();
				break;
			case 'redeclare_fn':
			case 'redeclare_cond':
			case 'redeclare_class':
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\nfunction digi_dup() {}\nclass Digi_Dup {}\n" ) ) );
				go();
				include ABSPATH . 'wp-content/plugins/fake/' . substr( $scenario, 10 ) . '.php';
				break;
			case 'deferred_core':
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\nadd_action( 'init', static function () {\n\tdigi_core_throw();\n} );\n" ) ) );
				go();
				include ABSPATH . 'wp-includes/fake-core.php';
				do_action( 'init' );
				break;
			case 'deferred_core_via_plugin':
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\nadd_action( 'init', static function () {\n\tdigi_plugin_call( 'digi_core_throw' );\n} );\n" ) ) );
				go();
				include ABSPATH . 'wp-includes/fake-core.php';
				include ABSPATH . 'wp-content/plugins/fake/fake.php';
				do_action( 'init' );
				break;
			case 'deferred_plugin':
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\nadd_action( 'init', static function () {\n\tdigi_plugin_throw();\n} );\n" ) ) );
				go();
				include ABSPATH . 'wp-content/plugins/fake/fake.php';
				do_action( 'init' );
				break;
			case 'fake_path':
				$code = "<?php\nmark( 'cargado' );\n";
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', $code ) ) );
				go();
				include ABSPATH . 'wp-includes/fake-core.php';
				include ABSPATH . 'wp-content/plugins/fake/fake.php';
				$path = path_of( 'site', 'aaaa0001', $code );
				digi_plugin_call( static function () use ( $path ) { digi_core_throw( "en $path:2\nStack trace:\n#0 $path(2): falso()\n#1 {main}" ); } );
				break;
			case 'memory_outside':
				ini_set( 'memory_limit', '32M' );
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\nadd_action( 'init', static function () {\n\t\$a = array();\n\twhile ( true ) \$a[] = str_repeat( 'x', 100000 );\n} );\n" ) ) );
				go();
				do_action( 'init' );
				break;
			case 'old_h12':
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', "<?php\nmark( 'cargado' );\n" ) ) );
				go();
				// La versión anterior del mismo fragmento: su h12 no es el de la que corrió en esta petición.
				$old = \Digitalisimo\Tools\Php_Snippets\Stream::put( 'site', 'aaaa0001', $dup, hash( 'sha256', $dup ) );
				include $old;
				break;
			case 'network_switch':
				$GLOBALS['w']['multisite'] = true;
				$GLOBALS['w']['blog']      = 2;
				$GLOBALS['w']['sitemeta']['digitalisimo_tools_php_network_run'] = array( 'v' => 1, 'kid' => '', 'safe' => false, 'safe_sites' => false, 'items' => array( 'bbbb0001' => item( 'network', 'bbbb0001', "<?php\nadd_action( 'init', static function () {\n\tswitch_to_blog( 3 );\n\tswitch_to_blog( 1 );\n\tdigi_no_existe();\n} );\n", 4 ) ) );
				go();
				do_action( 'init' );
				break;
			case 'test_fatal':
			case 'test_ok':
				// Como en producción: con display_errors activo, un aviso pintado al cargarse también cuenta como salida.
				ini_set( 'display_errors', '0' );
				$active    = "<?php\nmark( 'activo' );\n";
				$candidate = 'test_fatal' === $scenario ? $dup : "<?php\n\$y = \$sin_definir;\nadd_action( 'wp_footer', static function () { mark( 'pie' ); } );\n";
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', $active ), 'aaaa0002' => item( 'site', 'aaaa0002', "<?php\nmark( 'version-activa' );\n" ) ) );
				$GLOBALS['w']['opts'][1][ $S::off_name( 'site', 'aaaa0002', hash( 'sha256', $candidate ) ) ] = 'a:0:{}';
				\Digitalisimo\Tools\Php_Snippets\Tester::$context = array( 'token' => hash( 'sha256', 'tok' ), 'kind' => 'front', 'store' => 'site', 'user' => 0, 'candidates' => array( array( 'scope' => 'site', 'id' => 'aaaa0002', 'title' => 'Candidato', 'code' => $candidate, 'hash' => hash( 'sha256', $candidate ) ) ) );
				$_GET['digitalisimo-php-test'] = 'tok';
				go();
				do_action( 'wp_footer' );
				apply_filters( 'wp_redirect', 'https://otro.test/destino', 301 );
				break;
			case 'die_handler':
				// Como el manejador del núcleo: se registra antes que cualquier plugin y un wp_die_handler ajeno hace exit.
				register_shutdown_function( static function () {
					$e = error_get_last();
					if ( $e && in_array( $e['type'], array( E_ERROR, E_COMPILE_ERROR, E_PARSE ), true ) ) { apply_filters( 'wp_php_error_message', '<p>Error</p>', $e ); mark( 'core-handler' ); exit; }
				} );
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', $dup ) ) );
				go();
				break;
			case 'stream_notices':
				set_error_handler( static function ( $no, $str, $file = '', $line = 0 ) { $GLOBALS['state']['notices'][] = "$no $str ($file:$line)"; save_state(); return true; } );
				$code = "<?php\n\$GLOBALS['f'] = __FILE__;\n\$GLOBALS['d'] = __DIR__;\n";
				site_run( array( 'aaaa0001' => item( 'site', 'aaaa0001', $code ), 'aaaa0002' => item( 'site', 'aaaa0002', "<?php\ninclude_once __FILE__;\n" ) ) );
				go();
				$GLOBALS['state']['marks'][] = file_exists( path_of( 'site', 'aaaa0001', $code ) ) ? 'legible' : 'olvidado';
				$GLOBALS['state']['marks'][] = $GLOBALS['f'] === path_of( 'site', 'aaaa0001', $code ) ? 'file' : 'otro';
				save_state();
				break;
		}
		mark( 'fin' );
		exit( 0 );
	}

	/* =================================================================== *
	 * Padre
	 * =================================================================== */
	function check( $ok, $message, $context = '' ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n$context\n" ); exit( 1 ); } }
	$root = sys_get_temp_dir() . '/digitalisimo-php-guard-' . bin2hex( random_bytes( 4 ) );
	mkdir( $root . '/wp-includes', 0700, true );
	mkdir( $root . '/wp-content/plugins/fake', 0700, true );
	file_put_contents( $root . '/wp-includes/fake-core.php', "<?php\nfunction digi_core_throw( \$m = 'fallo en el núcleo' ) {\n\tthrow new RuntimeException( \$m );\n}\n" );
	file_put_contents( $root . '/wp-content/plugins/fake/fake.php', "<?php\nfunction digi_plugin_throw() {\n\tthrow new RuntimeException( 'fallo del plugin' );\n}\nfunction digi_plugin_call( \$cb ) {\n\treturn \$cb();\n}\n" );
	file_put_contents( $root . '/wp-content/plugins/fake/fn.php', "<?php\nfunction digi_dup() {}\n" );
	file_put_contents( $root . '/wp-content/plugins/fake/cond.php', "<?php\nif ( true ) {\n\tfunction digi_dup() {}\n}\n" );
	file_put_contents( $root . '/wp-content/plugins/fake/class.php', "<?php\nclass Digi_Dup {}\n" );
	/**
	 * Orden del PHP hijo: DIGITALISIMO_PHP_CHILD, o PHP_BINARY. php-wasm-cli publica como PHP_BINARY un guion de shell que
	 * llama a node sin «$@» (se pierden los argumentos): en ese caso se usa la orden que contiene, que hereda la versión de PHP
	 * del entorno (PHP=7.4…).
	 */
	function child_command() {
		$env = getenv( 'DIGITALISIMO_PHP_CHILD' );
		if ( is_string( $env ) && '' !== $env ) return $env;
		$script = is_file( PHP_BINARY ) && filesize( PHP_BINARY ) < 4096 ? (string) file_get_contents( PHP_BINARY ) : '';
		if ( 0 === strpos( $script, '#!' ) && false === strpos( $script, '$@' ) && preg_match( '/^[ \t]*(\S*node\S*[ \t]+\S*php-wasm\S*)[ \t]*$/m', $script, $m ) ) return $m[1];
		return escapeshellarg( PHP_BINARY );
	}
	$child = child_command();

	/** Corre un caso en un PHP aparte y devuelve lo que dejó escrito. */
	function scenario( $name ) {
		global $root, $child;
		$cmd  = $child . ' ' . escapeshellarg( __FILE__ ) . ' child ' . escapeshellarg( $name ) . ' ' . escapeshellarg( $root );
		$proc = proc_open( $cmd, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		check( is_resource( $proc ), 'no se pudo abrir el subproceso ' . $name );
		fclose( $pipes[0] );
		$out = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
		proc_close( $proc );
		$state = json_decode( (string) @file_get_contents( $root . '/state-' . $name . '.json' ), true );
		check( is_array( $state ), 'el caso ' . $name . ' no dejó estado', $out );
		$state['out'] = $out;
		return $state;
	}
	function off_rows( $state, $table = 'wp_options' ) {
		return array_filter( $state['rows'][ $table ] ?? array(), function ( $name ) { return 0 === strpos( $name, 'digitalisimo_tools_php_off_' ); }, ARRAY_FILTER_USE_KEY );
	}
	function only_off( $state, $name, $id, $code ) {
		$rows = off_rows( $state );
		$key  = 'digitalisimo_tools_php_off_s_' . $id . '_' . substr( hash( 'sha256', $code ), 0, 12 );
		check( array( $key ) === array_keys( $rows ), $name . ': se escribe sólo la fila off del fragmento', $state['out'] . print_r( $state['rows'], true ) );
		check( 'on' === $rows[ $key ]['a'] || 'yes' === $rows[ $key ]['a'], $name . ': la fila off se carga con alloptions' );
		check( array() === $state['writes'] && array() === $state['transients'] && array() === $state['mails'] && array() === $state['events'], $name . ': nada más (ni opciones, ni transitorios, ni correos, ni eventos)', print_r( $state, true ) );
		return unserialize( $rows[ $key ]['v'] );
	}
	function no_culprit( $state, $name ) {
		check( array() === off_rows( $state ) && array() === $state['writes'], $name . ': sin culpable no se desactiva ni se anota nada', $state['out'] . print_r( $state['rows'], true ) );
		check( ! in_array( 'fin', $state['marks'], true ), $name . ': el caso terminó en un error fatal', $state['out'] );
	}
	$dup = "<?php\nfunction digi_twice() {}\nfunction digi_twice() {}\n";

	$s   = scenario( 'compile' );
	$row = only_off( $s, 'compilación al cargar', 'aaaa0001', $dup );
	check( 3 === $row['line'] && 64 === $row['type'] && 0 === strpos( $row['msg'], 'Cannot redeclare' ), 'compilación: tipo, línea y mensaje', print_r( $row, true ) );

	$s = scenario( 'throwable' );
	only_off( $s, 'Throwable al cargarse', 'aaaa0001', "<?php\n\nthrow new LogicException( 'al cargar' );\n" );
	check( in_array( 'ran-2', $s['marks'], true ) && in_array( 'fin', $s['marks'], true ), 'Throwable: sigue con el siguiente fragmento y la petición termina', $s['out'] );

	$s   = scenario( 'memory' );
	$row = only_off( $s, 'memoria al cargar', 'aaaa0001', "<?php\n\$a = array();\nwhile ( true ) \$a[] = str_repeat( 'x', 100000 );\n" );
	check( 0 === strpos( $row['msg'], 'Allowed memory size' ), 'memoria: la escritura termina tras agotar la memoria' );

	$s = scenario( 'time' );
	if ( in_array( 'no-timeout', $s['marks'], true ) ) {
		echo "(tiempo: este PHP no aplica max_execution_time; el caso se comprueba en CI)\n";
	} else {
		$row = only_off( $s, 'tiempo al cargar', 'aaaa0001', "<?php\n\$t = microtime( true );\nwhile ( microtime( true ) - \$t < 4 ) { \$x = sqrt( 2 ); }\nmark( 'no-timeout' );\n" );
		check( 0 === strpos( $row['msg'], 'Maximum execution time' ), 'tiempo: mensaje' );
	}

	$snippet = "<?php\nfunction digi_dup() {}\nclass Digi_Dup {}\n";
	foreach ( array( 'redeclare_fn' => 'función redeclarada (compilación)', 'redeclare_cond' => 'función redeclarada en un if (E_ERROR)', 'redeclare_class' => 'clase redeclarada' ) as $case => $label ) {
		$s   = scenario( $case );
		$row = only_off( $s, $label, 'aaaa0001', $snippet );
		check( ( 'redeclare_class' === $case ? 3 : 2 ) === $row['line'], $label . ': la línea sale de Reflection', print_r( $row, true ) );
		if ( 'redeclare_cond' === $case ) check( 1 === $row['type'], 'la redeclaración condicional es E_ERROR', print_r( $row, true ) );
	}

	$s   = scenario( 'deferred_core' );
	$row = only_off( $s, 'excepción diferida lanzada en el núcleo', 'aaaa0001', "<?php\nadd_action( 'init', static function () {\n\tdigi_core_throw();\n} );\n" );
	check( 3 === $row['line'] && 0 === strpos( $row['msg'], 'Uncaught RuntimeException' ) && false === strpos( $row['msg'], $root ), 'núcleo: decide el primer marco ajeno, y el mensaje va sin ABSPATH', print_r( $row, true ) );

	no_culprit( scenario( 'deferred_core_via_plugin' ), 'excepción del núcleo con un plugin como primer marco ajeno' );
	no_culprit( scenario( 'deferred_plugin' ), 'excepción lanzada en un plugin' );
	no_culprit( scenario( 'fake_path' ), 'mensaje con una ruta y una traza falsas' );
	no_culprit( scenario( 'memory_outside' ), 'memoria fuera de la carga' );
	no_culprit( scenario( 'old_h12' ), 'h12 viejo' );

	$s    = scenario( 'network_switch' );
	$rows = off_rows( $s, 'wp_2_options' );
	$key  = 'digitalisimo_tools_php_off_n_bbbb0001_' . substr( hash( 'sha256', "<?php\nadd_action( 'init', static function () {\n\tswitch_to_blog( 3 );\n\tswitch_to_blog( 1 );\n\tdigi_no_existe();\n} );\n" ), 0, 12 ) . '_4';
	check( array( $key ) === array_keys( $rows ) && array() === off_rows( $s ) && array() === off_rows( $s, 'wp_3_options' ), 'fragmento de red: off_n en el sitio de origen aunque haya un switch_to_blog() abierto', $s['out'] . print_r( $s['rows'], true ) );
	check( 2 === $rows[ $key ]['blog'] && 0 === $rows[ $key ]['switched'], 'se escribe ya de vuelta en el sitio de origen' );

	$s = scenario( 'test_fatal' );
	check( array() === off_rows( $s ) && array() === $s['writes'], 'modo prueba: no desactiva nada', $s['out'] . print_r( $s, true ) );
	$result = $s['transients'][ 'site:digitalisimo_tools_php_result_' . hash( 'sha256', 'tok' ) ] ?? null;
	check( is_array( $result ) && true === $result['fatal']['attributed'] && 'aaaa0002' === $result['fatal']['id'] && 3 === $result['fatal']['line'], 'modo prueba: sólo el transitorio, con el fatal atribuido al candidato', print_r( $s, true ) );
	check( array( 'aaaa0001' ) === array_column( $result['loaded'], 'id' ), 'modo prueba: lo activo antes del candidato se cargó', print_r( $result, true ) );

	$s      = scenario( 'test_ok' );
	$result = $s['transients'][ 'site:digitalisimo_tools_php_result_' . hash( 'sha256', 'tok' ) ] ?? null;
	$cand   = "<?php\n\$y = \$sin_definir;\nadd_action( 'wp_footer', static function () { mark( 'pie' ); } );\n";
	check( is_array( $result ) && null === $result['fatal'] && array() === off_rows( $s ), 'prueba sin errores', print_r( $s, true ) );
	check( array( 'aaaa0001', 'aaaa0002' ) === array_column( $result['loaded'], 'id' ) && hash( 'sha256', $cand ) === $result['loaded'][1]['hash'], 'el candidato sustituye a su versión (aunque tenga fila off) y figura en loaded con su hash', print_r( $result, true ) );
	check( ! in_array( 'version-activa', $s['marks'], true ) && in_array( 'activo', $s['marks'], true ), 'la versión activa del candidato no se carga en la prueba' );
	check( 1 === count( $result['notices'] ) && 0 === strpos( $result['notices'][0]['file'], 'digitalisimo-php://site-1/aaaa0002@' ) && 2 === $result['notices'][0]['line'], 'avisos de PHP del fragmento', print_r( $result['notices'], true ) );
	check( 'wp_footer' === $result['footer'] && 'https://otro.test/destino' === $result['redirect'] && 301 === $result['redirect_status'] && 0 === $result['output'] && 'front' === $result['kind'], 'pie de página, redirección y salida', print_r( $result, true ) );

	$s = scenario( 'die_handler' );
	only_off( $s, 'un wp_die_handler que hace exit', 'aaaa0001', $dup );
	check( in_array( 'core-handler', $s['marks'], true ), 'el filtro del núcleo ya escribió la fila antes del exit' );

	$s = scenario( 'stream_notices' );
	check( array() === $s['notices'], 'Stream sin avisos con E_ALL', print_r( $s['notices'], true ) . $s['out'] );
	check( array( 'loaded', 'olvidado', 'file', 'fin' ) === $s['marks'], 'el envoltorio sirve __FILE__ y olvida el código tras el include', print_r( $s['marks'], true ) );

	array_map( 'unlink', glob( $root . '/state-*.json' ) );
	foreach ( array( 'wp-includes/fake-core.php', 'wp-content/plugins/fake/fake.php', 'wp-content/plugins/fake/fn.php', 'wp-content/plugins/fake/cond.php', 'wp-content/plugins/fake/class.php' ) as $file ) unlink( $root . '/' . $file );
	foreach ( array( 'wp-content/plugins/fake', 'wp-content/plugins', 'wp-content', 'wp-includes', '' ) as $dir ) @rmdir( $root . '/' . $dir );
	echo "Tools · Código PHP (guardián): OK\n";
}
