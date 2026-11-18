<?php
/**
 * Carga del escritorio de DIGITALÍSIMO Elements (digitalisimo-admin-load.php, auditoría del 2026-11-18):
 *  - las opciones del constructor que faltaron se anotan (sólo las de su prefijo, con tope y por versión) y se piden
 *    juntas con wp_prime_option_caches() antes de cargar el núcleo;
 *  - el MCP del navegador del núcleo sale de la cola con el núcleo incluido y lo trae un cargador sólo si el navegador
 *    expone WebMCP, con sus dependencias en orden y sin las ya impresas; con Angie activo (o el filtro) se carga siempre.
 * La medida antes/después está en tests/playground/admin-perf.sh.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '9.9.9' );
	$GLOBALS['opts']      = array();
	$GLOBALS['primed']    = array();
	$GLOBALS['installing'] = false;
	$GLOBALS['enqueued']  = array( 'elementor-v2-elementor-capabilities-mcp' => true );
	$GLOBALS['dequeued']  = array();
	$GLOBALS['filters']   = array();
	$GLOBALS['bundled']   = true;
	$GLOBALS['notoptions'] = array();
	$checks = 0;
	function check( $ok, $message ) {
		global $checks;
		if ( ! $ok ) {
			fwrite( STDERR, "FALLA: $message\n" );
			exit( 1 );
		}
		++$checks;
	}
	function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $name ] : $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['opts'][ $name ] = $value; $GLOBALS['autoload'][ $name ] = $autoload; return true; }
	function get_site_option( $name, $default = false ) { return $default; }
	function is_multisite() { return false; }
	function wp_prime_option_caches( $names ) { $GLOBALS['primed'][] = $names; }
	function wp_installing() { return $GLOBALS['installing']; }
	function add_action() { return true; }
	function apply_filters( $hook, $value ) { return array_key_exists( $hook, $GLOBALS['filters'] ) ? $GLOBALS['filters'][ $hook ] : $value; }
	function wp_script_is( $handle, $list = 'enqueued' ) { return ! empty( $GLOBALS['enqueued'][ $handle ] ); }
	function wp_dequeue_script( $handle ) { $GLOBALS['dequeued'][] = $handle; unset( $GLOBALS['enqueued'][ $handle ] ); }
	function wp_cache_get( $key, $group ) { return 'notoptions' === $key && 'options' === $group ? $GLOBALS['notoptions'] : false; }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	final class Digitalisimo_Elements_Core {
		public static function bundled() { return $GLOBALS['bundled']; }
	}
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-admin-load.php';
	$L = 'Digitalisimo_Elements_Admin_Load';

	// Nombres que se anotan: sólo los del constructor y de Elements.
	foreach ( array( 'elementor_experiment-container', 'elementor_cpt_support', 'elementor-pro_elementor_pro_updater_completed', '_elementor_pro_license_v2_data', '_elementor_installed_time', 'elementor_role-manager', 'digitalisimo_elements_front' ) as $name ) {
		check( $L::valid( $name ), "Se anota la opción del constructor $name." );
	}
	foreach ( array( '_transient_elementor_activation_redirect', '_site_transient_x', 'wp_user_roles', 'can_compress_scripts', 'uninstall_plugins', 'elementor', 'elementorx', 'elementor_' . str_repeat( 'a', 200 ), "elementor_x'; DROP", 123, null ) as $name ) {
		check( ! $L::valid( $name ), 'No se anota ' . var_export( $name, true ) . ': ni transitorios, ni opciones de WordPress u otros, ni nombres raros.' );
	}

	// merge(): une lo nuevo, ordena, ignora lo ajeno, no reescribe sin novedades, se rehace por versión y tiene tope.
	$missing = array( 'elementor_experiment-e_list' => true, 'can_compress_scripts' => true, 'elementor_cpt_support' => true, '_transient_x' => true );
	$first   = $L::merge( null, $missing, '1.0' );
	check( array( 'v' => '1.0', 'names' => array( 'elementor_cpt_support', 'elementor_experiment-e_list' ) ) === $first, 'La primera petición anota sólo las opciones del constructor que faltaron, ordenadas.' );
	check( null === $L::merge( $first, array( 'elementor_cpt_support' => true, 'wp_x' => true ), '1.0' ), 'Sin opciones nuevas del constructor no se guarda nada.' );
	check( null === $L::merge( $first, false, '1.0' ) && null === $L::merge( $first, 'x', '1.0' ), 'Sin caché de opciones ausentes no se guarda nada.' );
	$second = $L::merge( $first, array( 'elementor_safe_mode' => true, 'elementor_cpt_support' => true ), '1.0' );
	check( array( 'elementor_cpt_support', 'elementor_experiment-e_list', 'elementor_safe_mode' ) === $second['names'], 'Una opción nueva se suma a las ya anotadas.' );
	$other = $L::merge( $second, array( 'elementor_safe_mode' => true ), '2.0' );
	check( array( 'v' => '2.0', 'names' => array( 'elementor_safe_mode' ) ) === $other, 'Al cambiar de versión la lista empieza de nuevo.' );
	$many = array();
	for ( $i = 0; $i < 260; $i++ ) {
		$many[ sprintf( 'elementor_dyn_%03d', $i ) ] = true;
	}
	$capped = $L::merge( null, $many, '1.0' );
	check( $L::MAX === count( $capped['names'] ) && null === $L::merge( $capped, array( 'elementor_otra' => true ), '1.0' ), 'La lista tiene tope: opciones con nombres que dependen de un ID no la hacen crecer sin fin.' );

	// names()/prime(): sólo la lista de esta versión, en una sola llamada, nunca durante la instalación.
	$GLOBALS['opts'][ $L::OPTION ] = array( 'v' => '1.0', 'names' => array( 'elementor_cpt_support' ) );
	check( array() === $L::prime() && array() === $GLOBALS['primed'], 'Una lista de otra versión de Elements no se usa.' );
	$GLOBALS['opts'][ $L::OPTION ] = array( 'v' => '9.9.9', 'names' => array( 'elementor_cpt_support', 'wp_user_roles', 'elementor_safe_mode' ) );
	check( array( 'elementor_cpt_support', 'elementor_safe_mode' ) === $L::prime() && array( array( 'elementor_cpt_support', 'elementor_safe_mode' ) ) === $GLOBALS['primed'], 'Las opciones anotadas se piden juntas (y un nombre ajeno guardado a mano no se pide).' );
	$GLOBALS['installing'] = true;
	$GLOBALS['primed']     = array();
	$L::prime();
	check( array() === $GLOBALS['primed'], 'Durante la instalación no se piden opciones.' );
	$GLOBALS['installing'] = false;

	// learn(): sólo con el constructor cargado.
	$GLOBALS['opts']       = array();
	$GLOBALS['notoptions'] = array( 'elementor_safe_mode' => true );
	$L::learn();
	check( ! isset( $GLOBALS['opts'][ $L::OPTION ] ), 'Sin el constructor cargado no se anota nada.' );
	define( 'ELEMENTOR_VERSION', '4.3.5' );
	$L::learn();
	check( array( 'v' => '9.9.9', 'names' => array( 'elementor_safe_mode' ) ) === $GLOBALS['opts'][ $L::OPTION ] && true === $GLOBALS['autoload'][ $L::OPTION ], 'Con el constructor cargado se anota lo que faltó, con carga automática (se lee sin consulta).' );
	$GLOBALS['autoload'] = array();
	$L::learn();
	check( array() === $GLOBALS['autoload'], 'Una segunda petición igual no escribe.' );

	// queue(): dependencias primero, sin las ya impresas, sin ciclos ni manejadores desconocidos.
	$graph = array(
		'cap'    => array( 'mcp', 'common', 'schema' ),
		'mcp'    => array( 'schema', 'api' ),
		'common' => array(),
		'schema' => array(),
		'api'    => array( 'i18n', 'nope' ),
		'i18n'   => array( 'cap' ),
	);
	check( array( 'schema', 'i18n', 'api', 'mcp', 'common', 'cap' ) === $L::queue( $graph, array(), 'cap' ), 'El orden carga cada dependencia antes que quien la usa (un ciclo no se repite).' );
	check( array( 'mcp', 'common', 'cap' ) === $L::queue( $graph, array( 'api', 'schema' ), 'cap' ), 'Lo ya impreso en la página no se vuelve a cargar.' );
	check( array() === $L::queue( $graph, array(), 'otro' ) && array() === $L::queue( $graph, array( 'cap' ), 'cap' ), 'Un paquete desconocido o ya impreso no carga nada.' );

	// loader_js(): sólo con WebMCP, en orden, y el JSON no puede cerrar la etiqueta.
	$js = $L::loader_js( array( array( 'h' => 'a', 'src' => 'https://sitio.test/a.js?ver=1', 'before' => 'var x="</script><b>";', 'after' => '' ) ) );
	check( false !== strpos( $js, 'navigator.modelContext' ) && false !== strpos( $js, 'document.modelContext' ) && false === stripos( $js, '</script>' ) && false !== strpos( $js, 'https://sitio.test/a.js?ver=1' ), 'El cargador sólo actúa con WebMCP y su JSON no cierra la etiqueta.' );
	check( 0 === strpos( $js, '(function(q){if(!(' ), 'Sin WebMCP el cargador sale antes de crear nada.' );

	// defer_browser_mcp(): con el núcleo incluido sale de la cola; con el plugin original, con Angie o con el filtro, no.
	$L::defer_browser_mcp();
	check( array( 'elementor-v2-elementor-capabilities-mcp' ) === $GLOBALS['dequeued'], 'Con el núcleo incluido el MCP del navegador sale de la cola de la administración.' );
	$GLOBALS['enqueued'] = array( 'elementor-v2-elementor-capabilities-mcp' => true );
	$GLOBALS['dequeued'] = array();
	$GLOBALS['bundled']  = false;
	$L::defer_browser_mcp();
	check( array() === $GLOBALS['dequeued'], 'Con el plugin Elementor original manda él: el MCP se encola como siempre.' );
	$GLOBALS['bundled']                = true;
	$GLOBALS['opts']['active_plugins'] = array( 'angie/angie.php' );
	$L::defer_browser_mcp();
	check( array() === $GLOBALS['dequeued'], 'Con un plugin de Angie activo el MCP se carga siempre.' );
	$GLOBALS['opts']['active_plugins']                            = array();
	$GLOBALS['filters']['digitalisimo_elements_browser_mcp_eager'] = true;
	$L::defer_browser_mcp();
	check( array() === $GLOBALS['dequeued'], 'El filtro digitalisimo_elements_browser_mcp_eager lo carga siempre.' );
	unset( $GLOBALS['filters']['digitalisimo_elements_browser_mcp_eager'] );
	$GLOBALS['enqueued'] = array();
	$L::defer_browser_mcp();
	check( array() === $GLOBALS['dequeued'], 'Si el núcleo no lo encoló, no se toca nada.' );

	// El arranque: se carga antes que el núcleo, para que la consulta única llegue antes que las individuales.
	$main = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	check( false !== strpos( $main, "Digitalisimo_Elements_Admin_Load::init();" ) && strpos( $main, 'Digitalisimo_Elements_Admin_Load::init();' ) < strpos( $main, 'Digitalisimo_Elements_Core::load();' ), 'pro-elements.php arranca la carga del escritorio antes del núcleo incluido.' );
	echo "DIGITALÍSIMO Elements: carga del escritorio validada ($checks comprobaciones).\n";
}
