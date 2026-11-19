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
	function is_admin() { return $GLOBALS['is_admin'] ?? true; }
	function apply_filters( $hook, $value ) { return array_key_exists( $hook, $GLOBALS['filters'] ) ? $GLOBALS['filters'][ $hook ] : $value; }
	function wp_script_is( $handle, $list = 'enqueued' ) { return ! empty( $GLOBALS['enqueued'][ $handle ] ); }
	function wp_dequeue_script( $handle ) { $GLOBALS['dequeued'][] = $handle; unset( $GLOBALS['enqueued'][ $handle ] ); }
	function wp_cache_get( $key, $group ) { return 'notoptions' === $key && 'options' === $group ? $GLOBALS['notoptions'] : false; }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	$GLOBALS['styles'] = array( 'elementor-one-admin-fonts' => true, 'elementor-admin-top-bar-fonts' => true, 'elementor-admin' => true );
	function wp_style_is( $handle, $list = 'enqueued' ) { return ! empty( $GLOBALS['styles'][ $handle ] ); }
	function wp_dequeue_style( $handle ) { unset( $GLOBALS['styles'][ $handle ] ); }
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
	check( null === $L::merge( $first, array( 'elementor_cpt_support' => true, 'elementor_experiment-e_list' => true, 'wp_x' => true ), '1.0' ), 'Sin opciones nuevas del constructor no se guarda nada.' );
	check( null === $L::merge( $first, 'x', '1.0' ), 'Con una caché de opciones ausentes inválida no se guarda nada.' );
	$second = $L::merge( $first, array( 'elementor_safe_mode' => true, 'elementor_cpt_support' => true, 'elementor_experiment-e_list' => true ), '1.0' );
	check( array( 'elementor_cpt_support', 'elementor_experiment-e_list', 'elementor_safe_mode' ) === $second['names'], 'Una opción nueva se suma a las ya anotadas.' );
	$other = $L::merge( $second, array( 'elementor_safe_mode' => true ), '2.0' );
	check( array( 'v' => '2.0', 'names' => array( 'elementor_safe_mode' ) ) === $other, 'Al cambiar de versión la lista empieza de nuevo.' );
	$many = array();
	for ( $i = 0; $i < 260; $i++ ) {
		$many[ 'elementor_dyn_' . chr( 97 + intdiv( $i, 26 ) ) . chr( 97 + $i % 26 ) ] = true;
	}
	$capped = $L::merge( null, $many, '1.0' );
	check( $L::MAX === count( $capped['names'] ) && null === $L::merge( $capped, array_merge( array_fill_keys( $capped['names'], true ), array( 'elementor_otra' => true ) ), '1.0' ), 'La lista tiene tope: con la lista llena, un nombre nuevo no desplaza a los anotados.' );
	check( array( 'v' => '1.0', 'names' => array( 'elementor_otra' ) ) === $L::merge( null, array( 'elementor_atomic_styles_fonts-local-338-frontend' => true, 'elementor_otra' => true, 'elementor_x_12' => true ), '1.0' ), 'Los nombres con un ID (dos o más cifras) no se anotan.' );
	check( null === $L::merge( $first, false, '1.0' ), 'Sin caché de opciones ausentes la lista no cambia.' );

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

	// Fuentes remotas del núcleo: fuera con el núcleo incluido; el resto de hojas sigue; con el plugin original, nada.
	$GLOBALS['bundled'] = false;
	check( array() === $L::drop_remote_fonts() && 3 === count( $GLOBALS['styles'] ), 'Con el plugin Elementor original no se quita ninguna hoja.' );
	$GLOBALS['bundled'] = true;
	check( array( 'elementor-one-admin-fonts', 'elementor-admin-top-bar-fonts' ) === $L::drop_remote_fonts() && array( 'elementor-admin' => true ) === $GLOBALS['styles'], 'Con el núcleo incluido no se piden las fuentes de Google en la administración; las demás hojas siguen.' );

	// Capturas de plantillas (derivado de PRO): sin utf8_decode() directo, obsoleta desde PHP 8.2 (un aviso en cada
	// captura del editor), y la conversión a Latin-1 de mbstring da lo mismo que la antigua.
	$shots = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/screenshots/module.php' );
	check( false !== strpos( $shots, "mb_convert_encoding( (string) \$url, 'ISO-8859-1', 'UTF-8' )" ) && 1 === substr_count( $shots, 'utf8_decode( $url )' ) && false !== strpos( $shots, "function_exists( 'mb_convert_encoding' ) ?" ), 'El proxy de capturas convierte la URL con mbstring y sólo usa utf8_decode() sin mbstring.' );
	if ( function_exists( 'mb_convert_encoding' ) && PHP_VERSION_ID < 80200 ) {
		$u = 'https://sitio.test/fuente-ñandú.css?a=1';
		check( utf8_decode( $u ) === mb_convert_encoding( $u, 'ISO-8859-1', 'UTF-8' ), 'mbstring convierte la URL igual que utf8_decode().' );
	}

	// El arranque: se carga antes que el núcleo, para que la consulta única llegue antes que las individuales.
	$main = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	check( false !== strpos( $main, "Digitalisimo_Elements_Admin_Load::init();" ) && strpos( $main, 'Digitalisimo_Elements_Admin_Load::init();' ) < strpos( $main, 'Digitalisimo_Elements_Core::load();' ), 'pro-elements.php arranca la carga del escritorio antes del núcleo incluido.' );
	echo "DIGITALÍSIMO Elements: carga del escritorio validada ($checks comprobaciones).\n";
}
