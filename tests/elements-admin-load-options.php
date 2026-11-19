<?php
/**
 * Carga del escritorio de DIGITALÍSIMO Elements con las reglas de las opciones de WordPress simuladas (caché de la
 * petición, `notoptions`, carga automática, consultas contadas) y un WP_Scripts de prueba: lo que la revisión del
 * 2026-11-19 pidió cubrir (tests/elements-admin-load.php prueba las piezas puras).
 *  - Una petición del escritorio anota las opciones del constructor que faltan y la siguiente las pide en una consulta.
 *  - update_option() y delete_option() tras la precarga, en la misma petición, se leen bien.
 *  - Una opción que se crea después de anotada sale de la lista; los nombres con ID no se anotan; el front no escribe.
 *  - Con caché de objetos persistente no se precarga ni se anota; dentro de un switch_to_blog() no se anota, y cada
 *    sitio de una red guarda su lista; al desactivar Elements la lista se borra.
 *  - entries() y el cargador del MCP del navegador: orden, datos en línea, direcciones y salida por
 *    wp_print_inline_script_tag().
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '9.9.9' );
	define( 'ELEMENTOR_VERSION', '4.3.5' );
	$checks = 0;
	function check( $ok, $message ) {
		global $checks;
		if ( ! $ok ) {
			fwrite( STDERR, "FALLA: $message\n" );
			exit( 1 );
		}
		++$checks;
	}
	// ------------------------------------------------------------------------------------- Opciones de WordPress
	$GLOBALS['w'] = array( 'blog' => 1, 'db' => array(), 'cache' => array(), 'not' => array(), 'queries' => 0, 'ext' => false, 'switched' => false, 'admin' => true, 'ajax' => false, 'cron' => false, 'printed' => array() );
	/** Nueva petición: caché vacía y opciones con carga automática ya leídas (wp_load_alloptions). */
	function new_request( $blog = 1 ) {
		$w = &$GLOBALS['w'];
		$w['blog']    = $blog;
		$w['queries'] = 0;
		if ( ! $w['ext'] ) {
			$w['cache'] = array();
			$w['not']   = array();
		}
		foreach ( $w['db'][ $blog ] ?? array() as $name => $row ) {
			if ( $row['auto'] ) {
				$w['cache'][ $blog ][ $name ] = $row['value'];
			}
		}
	}
	function get_option( $name, $default = false ) {
		$w = &$GLOBALS['w'];
		$b = $w['blog'];
		if ( array_key_exists( $name, $w['cache'][ $b ] ?? array() ) ) {
			return $w['cache'][ $b ][ $name ];
		}
		if ( isset( $w['not'][ $b ][ $name ] ) ) {
			return $default;
		}
		++$w['queries'];
		if ( isset( $w['db'][ $b ][ $name ] ) ) {
			return $w['cache'][ $b ][ $name ] = $w['db'][ $b ][ $name ]['value'];
		}
		$w['not'][ $b ][ $name ] = true;
		return $default;
	}
	function wp_prime_option_caches( $names ) {
		$w   = &$GLOBALS['w'];
		$b   = $w['blog'];
		$ask = array_filter( $names, static function ( $n ) use ( $w, $b ) {
			return ! array_key_exists( $n, $w['cache'][ $b ] ?? array() ) && ! isset( $w['not'][ $b ][ $n ] );
		} );
		if ( ! $ask ) {
			return;
		}
		++$w['queries'];
		foreach ( $ask as $n ) {
			if ( isset( $w['db'][ $b ][ $n ] ) ) {
				$w['cache'][ $b ][ $n ] = $w['db'][ $b ][ $n ]['value'];
			} else {
				$w['not'][ $b ][ $n ] = true;
			}
		}
	}
	function update_option( $name, $value, $autoload = null ) {
		$w = &$GLOBALS['w'];
		$b = $w['blog'];
		$w['db'][ $b ][ $name ]    = array( 'value' => $value, 'auto' => false !== $autoload );
		$w['cache'][ $b ][ $name ] = $value;
		unset( $w['not'][ $b ][ $name ] );
		++$w['queries'];
		return true;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) { return update_option( $name, $value, $autoload ); }
	function delete_option( $name ) {
		$w = &$GLOBALS['w'];
		$b = $w['blog'];
		unset( $w['db'][ $b ][ $name ], $w['cache'][ $b ][ $name ] );
		$w['not'][ $b ][ $name ] = true;
		return true;
	}
	function delete_blog_option( $blog, $name ) { unset( $GLOBALS['w']['db'][ $blog ][ $name ] ); return true; }
	function get_sites( $args = array() ) { return array_keys( $GLOBALS['w']['db'] ); }
	function is_multisite() { return true; }
	function get_site_option( $name, $default = false ) { return $default; }
	function wp_cache_get( $key, $group ) { return 'notoptions' === $key && 'options' === $group ? ( $GLOBALS['w']['not'][ $GLOBALS['w']['blog'] ] ?? array() ) : false; }
	function wp_using_ext_object_cache() { return $GLOBALS['w']['ext']; }
	function wp_installing() { return false; }
	function ms_is_switched() { return $GLOBALS['w']['switched']; }
	function is_admin() { return $GLOBALS['w']['admin']; }
	function wp_doing_ajax() { return $GLOBALS['w']['ajax']; }
	function wp_doing_cron() { return $GLOBALS['w']['cron']; }
	function add_action() { return true; }
	function apply_filters( $hook, $value ) { return $value; }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
	function wp_print_inline_script_tag( $js, $attributes = array() ) { $GLOBALS['w']['printed'][] = array( $js, $attributes ); }
	$GLOBALS['enqueued'] = array( 'elementor-v2-elementor-capabilities-mcp' => true );
	function wp_script_is( $handle, $list = 'enqueued' ) { return ! empty( $GLOBALS['enqueued'][ $handle ] ); }
	function wp_dequeue_script( $handle ) { unset( $GLOBALS['enqueued'][ $handle ] ); }
	final class Digitalisimo_Elements_Core {
		public static function bundled() { return true; }
	}
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-admin-load.php';
	$L = 'Digitalisimo_Elements_Admin_Load';

	/** Lo que el núcleo lee en cada petición: opciones que en este sitio no existen. */
	$core_reads = array( 'elementor_experiment-container', 'elementor_experiment-e_list', 'elementor_safe_mode', 'elementor_cpt_support', 'elementor_mcp_enabled' );
	$request    = static function ( $blog = 1, array $extra = array() ) use ( $L, $core_reads ) {
		new_request( $blog );
		$L::prime();
		$primed = $GLOBALS['w']['queries'];
		foreach ( array_merge( $core_reads, $extra ) as $name ) {
			get_option( $name );
		}
		$reads = $GLOBALS['w']['queries'];
		$L::learn();
		return array( 'prime' => $primed, 'total' => $reads );
	};
	$names = static function ( $blog = 1 ) use ( $L ) {
		return $GLOBALS['w']['db'][ $blog ][ $L::OPTION ]['value']['names'] ?? null;
	};

	// Primera petición del escritorio: una consulta por opción ausente, y se anotan (con carga automática).
	$first = $request( 1, array( 'elementor_atomic_styles_fonts-local-338-frontend', '_transient_doing_cron', 'can_compress_scripts' ) );
	check( 1 + 8 === $first['total'], 'Sin lista, cada opción ausente es una consulta (más la lectura de la lista): ' . $first['total'] );
	$list = $names();
	sort( $core_reads );
	check( $core_reads === $list && true === $GLOBALS['w']['db'][1][ $L::OPTION ]['auto'], 'Se anotan las opciones del constructor que faltaron, sin nombres con ID ni opciones ajenas, con carga automática.' );

	// Segunda petición: una sola consulta para todas, y ninguna escritura.
	$second = $request( 1 );
	check( 1 === $second['prime'] && 1 === $second['total'], 'Con la lista, las cinco opciones ausentes cuestan una consulta en total.' );
	$before = $GLOBALS['w']['db'][1][ $L::OPTION ];
	check( $before === $GLOBALS['w']['db'][1][ $L::OPTION ], 'Una petición igual no reescribe la lista.' );

	// update_option() y delete_option() tras la precarga, en la misma petición.
	new_request( 1 );
	$L::prime();
	update_option( 'elementor_safe_mode', 'yes' );
	check( 'yes' === get_option( 'elementor_safe_mode', 'no' ), 'Tras la precarga, una opción creada en la misma petición se lee con su valor.' );
	delete_option( 'elementor_safe_mode' );
	check( 'no' === get_option( 'elementor_safe_mode', 'no' ), 'Tras la precarga, una opción borrada en la misma petición se lee como ausente.' );

	// Una opción creada después de anotarse sale de la lista (y deja de pedirse en cada petición).
	new_request( 1 );
	update_option( 'elementor_cpt_support', array( 'page' ), false );
	$third = $request( 1 );
	check( ! in_array( 'elementor_cpt_support', $names(), true ) && 4 === count( $names() ), 'Una opción que ya existe sale de la lista.' );
	check( array( 'page' ) === get_option( 'elementor_cpt_support' ), 'Esa opción se sigue leyendo con su valor.' );

	// El front (visitas anónimas) no escribe; la administración, AJAX, REST y cron sí.
	$GLOBALS['w']['admin'] = false;
	$request( 1, array( 'elementor_nueva_del_front' ) );
	check( ! in_array( 'elementor_nueva_del_front', $names(), true ), 'Una visita del front no escribe la lista.' );
	$GLOBALS['w']['ajax'] = true;
	$request( 1, array( 'elementor_nueva_del_front' ) );
	check( in_array( 'elementor_nueva_del_front', $names(), true ), 'Una petición AJAX la anota.' );
	$GLOBALS['w']['ajax']  = false;
	$GLOBALS['w']['admin'] = true;
	$front = $request( 1 );
	check( 1 === $front['prime'] && 2 === $front['total'], 'Lo anotado sirve a cualquier petición: una consulta para las ausentes (más la de la opción que existe sin carga automática).' );

	// Red: cada sitio su lista; dentro de un switch_to_blog() sin restaurar no se anota nada.
	$request( 2 );
	check( is_array( $names( 2 ) ) && in_array( 'elementor_safe_mode', $names( 2 ), true ), 'El segundo sitio de la red guarda su propia lista.' );
	$GLOBALS['w']['switched'] = true;
	$request( 3 );
	check( null === $names( 3 ), 'Dentro de un switch_to_blog() no se escribe la lista de otro sitio.' );
	$GLOBALS['w']['switched'] = false;

	// Caché de objetos persistente: ni precarga ni anotación.
	$GLOBALS['w']['ext'] = true;
	unset( $GLOBALS['w']['db'][4] );
	$ext = $request( 4 );
	check( 0 === $ext['prime'] && null === $names( 4 ), 'Con caché persistente no se precarga ni se anota.' );
	$GLOBALS['w']['ext'] = false;

	// Al desactivar: la lista se borra en el sitio, o en toda la red.
	$GLOBALS['w']['blog'] = 1;
	$L::deactivate( false );
	check( ! isset( $GLOBALS['w']['db'][1][ $L::OPTION ] ) && isset( $GLOBALS['w']['db'][2][ $L::OPTION ] ), 'Al desactivar en un sitio se borra su lista.' );
	$request( 1 );
	$L::deactivate( true );
	check( ! isset( $GLOBALS['w']['db'][1][ $L::OPTION ] ) && ! isset( $GLOBALS['w']['db'][2][ $L::OPTION ] ), 'Al desactivar en la red se borra en todos los sitios.' );

	// --------------------------------------------------------------------------------- entries() y el cargador
	final class Fake_Scripts {
		public $registered = array();
		public $done = array( 'wp-polyfill' );
		public $queue = array( 'jquery' );
		public $base_url = 'https://sitio.test';
		public $content_url = 'https://sitio.test/wp-content';
		public $default_version = '7.1';
		public $inline = array();
		public $translations = array();
		public $extra = array();
		public function print_translations( $h, $display = true ) { return $this->translations[ $h ] ?? false; }
		public function print_extra_script( $h, $display = true ) { return $this->extra[ $h ] ?? ''; }
		public function get_inline_script_data( $h, $position = 'after' ) { return $this->inline[ $h ][ $position ] ?? ''; }
	}
	$s = new Fake_Scripts();
	$item = static function ( $src, $deps, $ver ) { return (object) array( 'src' => $src, 'deps' => $deps, 'ver' => $ver ); };
	$s->registered = array(
		'elementor-v2-elementor-capabilities-mcp' => $item( 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/js/packages/elementor-capabilities-mcp/elementor-capabilities-mcp.min.js', array( 'elementor-v2-editor-mcp', 'elementor-v2-elementor-mcp-common', 'elementor-v2-schema' ), '4.3.5' ),
		'elementor-v2-editor-mcp'                 => $item( 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/js/packages/editor-mcp/editor-mcp.min.js', array( 'elementor-v2-schema', 'wp-api-fetch' ), '4.3.5' ),
		'elementor-v2-elementor-mcp-common'       => $item( 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/js/packages/elementor-mcp-common/elementor-mcp-common.min.js', array(), '4.3.5' ),
		'elementor-v2-schema'                     => $item( 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/js/packages/schema/schema.min.js', array(), null ),
		'wp-api-fetch'                            => $item( '/wp-includes/js/dist/api-fetch.min.js', array( 'wp-polyfill', 'jquery' ), false ),
		'wp-polyfill'                             => $item( '/wp-includes/js/dist/vendor/wp-polyfill.min.js', array(), false ),
		'jquery'                                  => $item( false, array(), false ),
	);
	$s->translations['elementor-v2-schema'] = '( function( domain, translations ) { /* es_MX */ } )( "elementor", {} );';
	$s->inline['wp-api-fetch']['after']     = 'wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( "https://sitio.test/wp-json/" ) );';
	$s->extra['elementor-v2-editor-mcp']    = 'var elementorMcpData = {"x":1};';
	function wp_scripts() { return $GLOBALS['fake_scripts']; }
	$GLOBALS['fake_scripts'] = $s;
	$entries = $L::entries();
	check( array( 'elementor-v2-schema', 'wp-api-fetch', 'elementor-v2-editor-mcp', 'elementor-v2-elementor-mcp-common', 'elementor-v2-elementor-capabilities-mcp' ) === array_column( $entries, 'h' ), 'entries(): cada dependencia antes que quien la usa y sin lo ya impreso o encolado (wp-polyfill, jquery): ' . implode( ', ', array_column( $entries, 'h' ) ) );
	$by = array_column( $entries, null, 'h' );
	check( 'https://sitio.test/wp-includes/js/dist/api-fetch.min.js?ver=7.1' === $by['wp-api-fetch']['src'] && false === strpos( $by['elementor-v2-schema']['src'], 'ver=' ) && '' !== $by['elementor-v2-editor-mcp']['src'] && false !== strpos( $by['elementor-v2-editor-mcp']['src'], '?ver=4.3.5' ), 'entries(): direcciones como las imprime WordPress (relativas con la base, versión por defecto, sin versión si es null).' );
	check( false !== strpos( $by['elementor-v2-schema']['before'], 'es_MX' ) && false !== strpos( $by['elementor-v2-editor-mcp']['before'], 'elementorMcpData' ) && false !== strpos( $by['wp-api-fetch']['after'], 'createRootURLMiddleware' ), 'entries(): traducciones, datos localizados y código en línea viajan con su paquete.' );
	check( ! in_array( 'elementor-v2-schema', $s->done, true ), 'entries() no marca nada como impreso: sin WebMCP, otro script que lo necesite lo sigue recibiendo.' );

	// El cargador: retirar el paquete de la cola e imprimirlo por wp_print_inline_script_tag().
	$L::print_browser_mcp_loader();
	check( array() === $GLOBALS['w']['printed'], 'Sin retirar el paquete no se imprime ningún cargador.' );
	$L::defer_browser_mcp();
	check( empty( $GLOBALS['enqueued']['elementor-v2-elementor-capabilities-mcp'] ), 'El paquete sale de la cola.' );
	$L::print_browser_mcp_loader();
	$printed = $GLOBALS['w']['printed'][0] ?? array( '', array() );
	check( 1 === count( $GLOBALS['w']['printed'] ) && array( 'id' => 'digitalisimo-elements-browser-mcp' ) === $printed[1], 'El cargador sale por wp_print_inline_script_tag() con su id (un plugin de CSP puede ponerle nonce).' );
	check( false !== strpos( $printed[0], 'navigator.modelContext' ) && false !== strpos( $printed[0], 'api-fetch.min.js?ver=7.1' ) && strpos( $printed[0], 'elementor-v2-schema' ) < strpos( $printed[0], 'elementor-v2-elementor-capabilities-mcp' ) && false === stripos( $printed[0], '</script' ), 'El cargador lleva la cola en orden y su JSON no cierra la etiqueta.' );
	echo "DIGITALÍSIMO Elements: precarga de opciones y cargador del MCP con WordPress simulado validados ($checks comprobaciones).\n";
}
