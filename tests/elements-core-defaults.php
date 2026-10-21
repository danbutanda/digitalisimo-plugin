<?php
/**
 * F-front, frente C: valores por defecto del núcleo incluido. Los tres `default_option_` (font-display, caché de
 * elementos y carga optimizada de imágenes) existen sólo con su clave aplicada y leen el aplicado en cada llamada;
 * una fila guardada o el `pre_option_` de DIGITALÍSIMO SEO siguen ganando; el experimento de iconos en línea sólo
 * cambia su valor por defecto con estado vacío o `default`; con el plugin Elementor original no se añade nada; y las
 * hojas de Font Awesome se registran (sin encolar) sólo cuando los iconos en línea los puso Elements.
 */
namespace Elementor {
	class Plugin { public static $instance; public $experiments; public $widgets_manager; }
}
namespace Digitalisimo\Elements\Front {
	/** Doble del frente B con la firma de §7.2: el aplicado es la opción que escribe la transición. */
	final class Transition {
		const APPLIED = 'digitalisimo_elements_front_applied';
		public static $scheduled = 0;
		public static function applied(): array { $v = get_option( self::APPLIED, array() ); return is_array( $v ) ? $v : array(); }
		public static function schedule(): void { self::$scheduled++; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ELEMENTOR_ASSETS_URL', 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/' );
	function check_defaults( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	// Ganchos de WordPress con prioridades y número de argumentos.
	$GLOBALS['hooks'] = array();
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = array( $cb, $args ); return true; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
	function remove_filter( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['hooks'][ $hook ][ $priority ] ?? array() as $i => $entry ) { if ( $entry[0] === $cb ) { unset( $GLOBALS['hooks'][ $hook ][ $priority ][ $i ] ); return true; } }
		return false;
	}
	function has_filter( $hook, $cb = false ) {
		foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $priority => $entries ) { foreach ( $entries as $entry ) { if ( false === $cb || $entry[0] === $cb ) { return false === $cb ? true : $priority; } } }
		return false;
	}
	function has_action( $hook, $cb = false ) { return has_filter( $hook, $cb ); }
	function apply_filters( $hook, $value, ...$rest ) {
		$hooks = $GLOBALS['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) { foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $rest ), 0, $entry[1] ) ); } }
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$hooks = $GLOBALS['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) { foreach ( $entries as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } }
	}

	// Opciones con la semántica de get_option(): pre_option_ → fila → default_option_ (también con default propio).
	$GLOBALS['opts']   = array();
	$GLOBALS['writes'] = array();
	function get_option( $option, $default = false ) {
		$passed = func_num_args() > 1;
		$pre = apply_filters( "pre_option_{$option}", false, $option, $default );
		if ( false !== $pre ) { return $pre; }
		if ( array_key_exists( $option, $GLOBALS['opts'] ) ) { return $GLOBALS['opts'][ $option ]; }
		return apply_filters( "default_option_{$option}", $default, $option, $passed );
	}
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['writes'][] = $option;
		$exists = array_key_exists( $option, $GLOBALS['opts'] );
		$old = $exists ? $GLOBALS['opts'][ $option ] : false;
		$GLOBALS['opts'][ $option ] = $value;
		$exists ? do_action( "update_option_{$option}", $old, $value, $option ) : do_action( "add_option_{$option}", $option, $value );
		return true;
	}
	function delete_option( $option ) { $GLOBALS['writes'][] = $option; unset( $GLOBALS['opts'][ $option ] ); do_action( "delete_option_{$option}", $option ); return true; }

	// Hojas registradas y encoladas.
	$GLOBALS['styles'] = array( 'registered' => array(), 'queue' => array() );
	function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['styles']['registered'][ $handle ] ) : in_array( $handle, $GLOBALS['styles']['queue'], true ); }
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { if ( isset( $GLOBALS['styles']['registered'][ $handle ] ) ) { return false; } $GLOBALS['styles']['registered'][ $handle ] = (object) array( 'src' => $src, 'deps' => $deps, 'ver' => $ver ); return true; }
	function wp_enqueue_style( $handle ) { $GLOBALS['styles']['queue'][] = $handle; }
	function wp_styles() { return (object) array( 'registered' => $GLOBALS['styles']['registered'] ); }

	/** Cargador del núcleo: sólo importa si el núcleo incluido está cargado. */
	class Digitalisimo_Elements_Core { public static $bundled = false; public static function bundled() { return self::$bundled; } }

	/** Gestor de experimentos reducido a lo que usa el núcleo para e_font_icon_svg (manager.php:233, :316, :754). */
	class Fake_Experiments {
		public $features = array();
		public $default_calls = 0;
		public function __construct( $state, $default = 'inactive' ) { $this->features['e_font_icon_svg'] = array( 'name' => 'e_font_icon_svg', 'state' => $state, 'default' => $default ); }
		public function get_features( $name = null ) { return null === $name ? $this->features : ( $this->features[ $name ] ?? null ); }
		public function set_feature_default_state( $name, $state ) { $this->default_calls++; if ( isset( $this->features[ $name ] ) ) { $this->features[ $name ]['default'] = $state; } }
		public function is_feature_active( $name ) { $f = $this->features[ $name ] ?? null; if ( ! $f ) { return false; } $actual = ( ! empty( $f['state'] ) && 'default' !== $f['state'] ) ? $f['state'] : $f['default']; return 'active' === $actual; }
	}

	$dir = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-front/';
	require $dir . 'class-core-defaults.php';
	use Digitalisimo\Elements\Front\Core_Defaults;
	use Digitalisimo\Elements\Front\Icon_Scan;
	use Digitalisimo\Elements\Front\Transition;

	$cb       = array( Core_Defaults::class, 'default_value' );
	$font     = 'default_option_elementor_font_display';
	$cache    = 'default_option_elementor_element_cache_ttl';
	$images   = 'default_option_elementor_optimized_image_loading';
	$feature  = 'elementor/experiments/default-features-registered';
	$register = 'elementor/frontend/after_register_styles';
	$all      = array( 'font_swap' => true, 'svg_icons' => true, 'element_cache_off' => true, 'image_loading_off' => true );

	// Con el plugin Elementor original (núcleo incluido sin cargar), nada: ni filtros ni experimento ni hojas.
	$GLOBALS['opts'][ Transition::APPLIED ] = $all;
	Core_Defaults::init();
	check_defaults( ! Core_Defaults::active() && ! has_filter( $font ) && ! has_filter( $cache ) && ! has_filter( $images ) && ! has_action( $feature ) && ! has_action( $register ), 'Con el plugin original no se añade ningún valor por defecto.' );
	check_defaults( 'auto' === get_option( 'elementor_font_display', 'auto' ) && '' === get_option( 'elementor_element_cache_ttl', '' ) && '1' === get_option( 'elementor_optimized_image_loading', '1' ), 'Con el plugin original manda su configuración aunque el aplicado diga otra cosa.' );
	$plugin_mgr = new Fake_Experiments( '' );
	do_action( $feature, $plugin_mgr );
	check_defaults( 0 === $plugin_mgr->default_calls && ! $plugin_mgr->is_feature_active( 'e_font_icon_svg' ), 'Con el plugin original el experimento de iconos no se toca.' );
	check_defaults( ! class_exists( Icon_Scan::class, false ), 'Con el plugin original ni siquiera se carga el análisis de iconos.' );

	// Núcleo incluido sin nada aplicado: tampoco hay filtros (has_filter dice la verdad a DIGITALÍSIMO SEO).
	Digitalisimo_Elements_Core::$bundled = true;
	$GLOBALS['opts'][ Transition::APPLIED ] = array();
	Core_Defaults::init();
	check_defaults( Core_Defaults::active() && ! has_filter( $font ) && ! has_filter( $cache ) && ! has_filter( $images ), 'Sin claves aplicadas no hay ningún default_option_.' );
	check_defaults( has_action( $feature ) && has_action( $register ) && class_exists( Icon_Scan::class, false ) && has_action( Icon_Scan::HOOK ), 'Con el núcleo incluido se preparan el experimento, el registro de compatibilidad y el evento del análisis.' );
	check_defaults( 'auto' === get_option( 'elementor_font_display', 'auto' ) && '' === get_option( 'elementor_element_cache_ttl', '' ) && '1' === get_option( 'elementor_optimized_image_loading', '1' ), 'Sin aplicado, los valores del núcleo no cambian.' );
	Core_Defaults::init();
	check_defaults( 1 === count( $GLOBALS['hooks'][ $feature ][10] ?? array() ), 'Iniciar dos veces no duplica los ganchos.' );

	// La transición escribe el aplicado: el filtro aparece en la misma petición y sólo el de su clave.
	update_option( Transition::APPLIED, array( 'font_swap' => true ) );
	check_defaults( 10 === has_filter( $font, $cb ) && ! has_filter( $cache ) && ! has_filter( $images ), 'Con font_swap aplicado sólo existe el default_option_ de font-display.' );
	check_defaults( 'swap' === get_option( 'elementor_font_display', 'auto' ) && 'swap' === get_option( 'elementor_font_display' ), 'Sin fila, font-display vale swap (también si get_option recibe su propio valor por defecto).' );
	check_defaults( 'swap' === apply_filters( $font, 'auto', 'elementor_font_display', true ), 'DIGITALÍSIMO SEO lee el mismo valor con apply_filters.' );
	check_defaults( '' === get_option( 'elementor_element_cache_ttl', '' ) && '1' === get_option( 'elementor_optimized_image_loading', '1' ), 'Las otras dos claves no cambian.' );

	// El filtro vuelve a leer el aplicado en cada llamada (no un valor guardado al registrarse).
	$GLOBALS['opts'][ Transition::APPLIED ] = array();
	check_defaults( 'auto' === get_option( 'elementor_font_display', 'auto' ), 'Con el aplicado vacío el filtro devuelve el valor recibido.' );
	$GLOBALS['opts'][ Transition::APPLIED ] = array( 'font_swap' => true );

	update_option( Transition::APPLIED, array( 'font_swap' => true, 'element_cache_off' => true, 'image_loading_off' => true ) );
	check_defaults( 'swap' === get_option( 'elementor_font_display', 'auto' ) && 'disable' === get_option( 'elementor_element_cache_ttl', '' ) && '0' === get_option( 'elementor_optimized_image_loading', '1' ), 'Los tres valores por defecto: swap, disable y «0» (cadena, como compara el núcleo).' );
	check_defaults( 'disable' === apply_filters( $cache, '', 'elementor_element_cache_ttl', true ) && '0' === apply_filters( $images, '1', 'elementor_optimized_image_loading', true ), 'Cada filtro responde por su opción.' );
	check_defaults( 'otro' === Core_Defaults::default_value( 'otro', 'elementor_otra_opcion', true ), 'Una opción ajena pasa intacta.' );

	// Una fila guardada (elección del sitio) gana siempre: default_option_ sólo corre sin fila.
	$GLOBALS['opts']['elementor_font_display']       = 'optional';
	$GLOBALS['opts']['elementor_element_cache_ttl']  = '12';
	$GLOBALS['opts']['elementor_optimized_image_loading'] = '1';
	check_defaults( 'optional' === get_option( 'elementor_font_display', 'auto' ) && '12' === get_option( 'elementor_element_cache_ttl', '' ) && '1' === get_option( 'elementor_optimized_image_loading', '1' ), 'Lo que el sitio eligió en los ajustes del constructor se conserva.' );
	unset( $GLOBALS['opts']['elementor_font_display'], $GLOBALS['opts']['elementor_element_cache_ttl'], $GLOBALS['opts']['elementor_optimized_image_loading'] );

	// Convivencia con el pre_option_ de DIGITALÍSIMO SEO (perf_font_swap): encendido gana; apagado devuelve lo recibido.
	$GLOBALS['seo_swap'] = false;
	add_filter( 'pre_option_elementor_font_display', static function ( $pre ) { return $GLOBALS['seo_swap'] ? 'swap' : $pre; } );
	check_defaults( 'swap' === get_option( 'elementor_font_display', 'auto' ), 'SEO apagado no impide el valor por defecto de Elements.' );
	update_option( Transition::APPLIED, array( 'element_cache_off' => true ) );
	check_defaults( 'auto' === get_option( 'elementor_font_display', 'auto' ) && ! has_filter( $font ), 'Sin font_swap aplicado y con SEO apagado: auto.' );
	$GLOBALS['seo_swap'] = true;
	$GLOBALS['opts']['elementor_font_display'] = 'auto';
	check_defaults( 'swap' === get_option( 'elementor_font_display', 'auto' ), 'SEO encendido gana sobre la fila y sobre Elements.' );
	unset( $GLOBALS['opts']['elementor_font_display'] );
	check_defaults( false === has_filter( 'pre_option_elementor_font_display', $cb ), 'Elements nunca usa pre_option_: sólo da valores por defecto.' );
	$GLOBALS['seo_swap'] = false;

	// En una red cada sitio tiene su aplicado: al cambiar de sitio se vuelve a decidir qué filtros existen.
	update_option( Transition::APPLIED, array() );
	$GLOBALS['opts'][ Transition::APPLIED ] = array( 'font_swap' => true );
	do_action( 'switch_blog', 2, 1 );
	check_defaults( has_filter( $font ) && 'swap' === get_option( 'elementor_font_display', 'auto' ), 'Tras switch_blog, el sitio con font_swap aplicado tiene su valor por defecto.' );
	$GLOBALS['opts'][ Transition::APPLIED ] = array();
	do_action( 'switch_blog', 1, 2 );
	check_defaults( ! has_filter( $font ) && 'auto' === get_option( 'elementor_font_display', 'auto' ), 'Y al volver a un sitio sin él, el filtro desaparece.' );

	// Quitar el aplicado retira los filtros (también al borrar la opción).
	update_option( Transition::APPLIED, array( 'font_swap' => true, 'element_cache_off' => true, 'image_loading_off' => true ) );
	delete_option( Transition::APPLIED );
	check_defaults( ! has_filter( $font ) && ! has_filter( $cache ) && ! has_filter( $images ) && 'auto' === get_option( 'elementor_font_display', 'auto' ), 'Sin aplicado no queda ningún default_option_.' );

	// Experimento de iconos en línea: sólo con svg_icons aplicado y estado guardado vacío o «default».
	update_option( Transition::APPLIED, array( 'svg_icons' => true ) );
	foreach ( array( 'default', '' ) as $saved ) {
		$mgr = new Fake_Experiments( $saved );
		do_action( $feature, $mgr );
		check_defaults( 1 === $mgr->default_calls && $mgr->is_feature_active( 'e_font_icon_svg' ) && 'active' === $mgr->features['e_font_icon_svg']['default'], 'Estado «' . $saved . '»: el valor por defecto del experimento pasa a activo.' );
		check_defaults( $saved === $mgr->features['e_font_icon_svg']['state'], 'El estado guardado del experimento nunca cambia.' );
		check_defaults( 'inactive' === Core_Defaults::native_icons() && Core_Defaults::icons_forced(), 'Se recuerda el estado sin el valor propio (inactivo) y que lo puso Elements.' );
	}
	foreach ( array( 'inactive', 'active' ) as $saved ) {
		$mgr = new Fake_Experiments( $saved );
		do_action( $feature, $mgr );
		check_defaults( 0 === $mgr->default_calls && $saved === Core_Defaults::native_icons() && ! Core_Defaults::icons_forced(), 'Estado guardado «' . $saved . '»: manda la elección del sitio.' );
	}
	$mgr = new Fake_Experiments( '', 'active' );
	do_action( $feature, $mgr );
	check_defaults( 0 === $mgr->default_calls && 'active' === Core_Defaults::native_icons() && ! Core_Defaults::icons_forced(), 'Un sitio nuevo ya los trae activos: no hay nada que poner.' );
	update_option( Transition::APPLIED, array( 'font_swap' => true ) );
	$mgr = new Fake_Experiments( '' );
	do_action( $feature, $mgr );
	check_defaults( 0 === $mgr->default_calls && ! $mgr->is_feature_active( 'e_font_icon_svg' ) && 'inactive' === Core_Defaults::native_icons(), 'Sin svg_icons aplicado los iconos no se aplican solos.' );
	Core_Defaults::experiments( null );
	Core_Defaults::experiments( new stdClass() );
	check_defaults( ! Core_Defaults::icons_forced(), 'Un gestor inesperado no rompe nada.' );

	// Registro de compatibilidad de Font Awesome: sólo con los iconos puestos por Elements, sin encolar ni pisar.
	$fa = ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/';
	update_option( Transition::APPLIED, array( 'svg_icons' => true ) );
	$mgr = new Fake_Experiments( '' );
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = $mgr;
	do_action( $feature, $mgr );
	$GLOBALS['styles'] = array( 'registered' => array( 'elementor-icons-fa-brands' => (object) array( 'src' => 'https://otro.test/brands.css', 'deps' => array(), 'ver' => '1' ) ), 'queue' => array() );
	do_action( $register );
	$reg = $GLOBALS['styles']['registered'];
	check_defaults( isset( $reg['elementor-icons-shared-0'] ) && $fa . 'fontawesome.min.css' === $reg['elementor-icons-shared-0']->src && '5.15.3' === $reg['elementor-icons-shared-0']->ver, 'La hoja compartida de Font Awesome se registra con la URL del núcleo.' );
	foreach ( array( 'fa-solid' => 'solid', 'fa-regular' => 'regular' ) as $name => $file ) {
		check_defaults( $fa . $file . '.min.css' === $reg[ 'elementor-icons-' . $name ]->src && array( 'elementor-icons-shared-0' ) === $reg[ 'elementor-icons-' . $name ]->deps && '5.15.3' === $reg[ 'elementor-icons-' . $name ]->ver, 'elementor-icons-' . $name . ' con la URL, la dependencia y la versión del núcleo.' );
	}
	check_defaults( 'https://otro.test/brands.css' === $reg['elementor-icons-fa-brands']->src, 'Un handle ya registrado no se pisa.' );
	check_defaults( array() === $GLOBALS['styles']['queue'], 'Se registran sin encolar: sólo se imprimen si una hoja ajena depende de ellas.' );

	// Otra biblioteca ocupó el handle compartido: Font Awesome usa uno propio.
	$GLOBALS['styles'] = array( 'registered' => array( 'elementor-icons-shared-0' => (object) array( 'src' => 'https://otro.test/iconos.css', 'deps' => array(), 'ver' => '1' ) ), 'queue' => array() );
	do_action( $register );
	$reg = $GLOBALS['styles']['registered'];
	check_defaults( 'https://otro.test/iconos.css' === $reg['elementor-icons-shared-0']->src && $fa . 'fontawesome.min.css' === $reg[ Core_Defaults::FA_OWN_SHARED ]->src && array( Core_Defaults::FA_OWN_SHARED ) === $reg['elementor-icons-fa-solid']->deps, 'Con el handle compartido ocupado, Font Awesome depende de una hoja propia.' );

	// Sin los iconos puestos por Elements no se registra nada.
	foreach ( array( 'inactive', 'active' ) as $saved ) {
		$GLOBALS['styles'] = array( 'registered' => array(), 'queue' => array() );
		$mgr = new Fake_Experiments( $saved );
		\Elementor\Plugin::$instance->experiments = $mgr;
		do_action( $feature, $mgr );
		do_action( $register );
		check_defaults( array() === $GLOBALS['styles']['registered'], 'Estado guardado «' . $saved . '»: el núcleo decide sus hojas y no se registra nada.' );
	}
	$GLOBALS['styles'] = array( 'registered' => array(), 'queue' => array() );
	$mgr = new Fake_Experiments( '' );
	\Elementor\Plugin::$instance->experiments = $mgr;
	do_action( $feature, $mgr );
	update_option( Transition::APPLIED, array() );
	do_action( $register );
	check_defaults( array() === $GLOBALS['styles']['registered'], 'Si svg_icons deja de estar aplicado, tampoco.' );

	// Regla R15: el código de C nunca escribe un ajuste, un experimento ni una fila del núcleo.
	foreach ( array( 'class-core-defaults.php', 'class-icon-scan.php' ) as $file ) {
		$source = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', file_get_contents( $dir . $file ) );
		check_defaults( ! preg_match( '/\b(?:update|add|delete)_(?:site_)?option\s*\(\s*[\'"]_?elementor/i', $source ) && ! preg_match( '/\$wpdb\s*->\s*(?:insert|update|delete|replace|query)\s*\(/i', $source ) && ! preg_match( '/\b(?:update|add|delete)_post_meta\s*\(/', $source ), $file . ': ninguna escritura en opciones, metadatos ni tablas.' );
		check_defaults( ! preg_match( '/pre_option_|set_transient|update_site_option/', $source ), $file . ': sin pre_option_ ni otras escrituras.' );
		check_defaults( ! preg_match( '/element\s*pack|\bbdt-|\bep_/i', $source ), $file . ': no nombra Element Pack ni sus prefijos.' );
	}
	$scan = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', file_get_contents( $dir . 'class-icon-scan.php' ) );
	preg_match_all( '/\bupdate_option\s*\(\s*([^,]+),/', $scan, $updates );
	check_defaults( array( 'self::OPTION' ) === array_values( array_unique( array_map( 'trim', $updates[1] ) ) ), 'El análisis sólo escribe su propia opción.' );
	check_defaults( ! preg_match( '/\b(?:update_option|add_option|delete_option)\s*\(/', preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', file_get_contents( $dir . 'class-core-defaults.php' ) ) ), 'Core_Defaults no escribe ninguna opción.' );

	echo "DIGITALÍSIMO Elements: valores por defecto del núcleo validados (font-display, caché, imágenes, iconos y compatibilidad).\n";
}
