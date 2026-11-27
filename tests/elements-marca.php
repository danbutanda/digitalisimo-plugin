<?php
/**
 * Marca propia, fase 1 (docs/MARCA_PROPIA.md), sin WordPress (dobles mínimos):
 * - M1 mapa de marca: la salida renombra identificadores (tipos y taxonomías registrados, metas, recursos, herramientas,
 *   `action=elementor`, `page=elementor…`) y la entrada deshace sólo eso; nunca toca medios (`elementor-banner.jpg`),
 *   rutas de archivos, nombres propios (`digitalisimo_query`, `_digitalisimo_actions`) ni el contenido del cliente;
 * - M2 interruptor: apagado por defecto; sitio → red → apagado; «no disponible» sin el núcleo incluido; «bloqueada»
 *   con complementos de terceros salvo que se fuerce (D-5);
 * - M3 complementos: por cabecera (`Elementor tested up to`, `Requires Plugins: elementor`), por ganchos del motor (con
 *   el archivo de la función) y por el tema Hello; nunca los propios `digitalisimo*` ni el Elementor original;
 * - M4 alias de URL (D-3): la petición con alias se traduce a la vieja (siempre con el núcleo incluido, también
 *   `REQUEST_URI`), la vieja sigue igual; `url_out` sólo cambia URL del escritorio; las redirecciones y el script sólo con
 *   la marca propia en vigor;
 * - M5 identidad: el experimento se llama «Componentes atómicos DIGITALÍSIMO» (D-4) en su título y en las descripciones
 *   de PRO, en inglés y traducidas; el filtro de wp.i18n no se adjunta para un visitante del front;
 * - M6 «Rendimiento» → «Marca propia en el sitio»: estado, lista de complementos, dirección del MCP nuevo y del viejo.
 * Falla con el código anterior: no existe `modules/digitalisimo-marca/`.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	$tmp = sys_get_temp_dir() . '/digi-marca-' . getmypid();
	define( 'WP_PLUGIN_DIR', $tmp . '/plugins' );
	error_reporting( E_ALL );
	set_error_handler(
		static function ( $no, $str, $file, $line ) {
			echo "FALLA aviso de PHP: $str ($file:$line)\n";
			$GLOBALS['fails']++;
			return true;
		}
	);
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

	$GLOBALS['wp_filter']  = array();
	$GLOBALS['options']    = array();
	$GLOBALS['site']       = array();
	$GLOBALS['multisite']  = false;
	$GLOBALS['transients'] = array();
	$GLOBALS['done']       = array( 'init' => 1 );
	$GLOBALS['admin_bar']  = false;
	$GLOBALS['is_admin']   = true;
	$GLOBALS['redirects']  = array();
	function add_filter( $tag, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['wp_filter'][ $tag ][ $prio ][] = array( 'function' => $cb, 'accepted_args' => $args );
		return true;
	}
	function add_action( $tag, $cb, $prio = 10, $args = 1 ) {
		return add_filter( $tag, $cb, $prio, $args );
	}
	function apply_filters( $tag, $value, ...$rest ) {
		if ( empty( $GLOBALS['wp_filter'][ $tag ] ) ) {
			return $value;
		}
		ksort( $GLOBALS['wp_filter'][ $tag ] );
		foreach ( $GLOBALS['wp_filter'][ $tag ] as $list ) {
			foreach ( $list as $h ) {
				$value = call_user_func_array( $h['function'], array_slice( array_merge( array( $value ), $rest ), 0, max( 1, $h['accepted_args'] ) ) );
			}
		}
		return $value;
	}
	function did_action( $t ) {
		return $GLOBALS['done'][ $t ] ?? 0;
	}
	function has_action( $t ) {
		return ! empty( $GLOBALS['wp_filter'][ $t ] );
	}
	function is_admin() {
		return $GLOBALS['is_admin'];
	}
	$GLOBALS['option_reads'] = array();
	$GLOBALS['alloptions']   = null;
	$GLOBALS['doing_ajax']   = false;
	function get_option( $k, $d = false ) {
		$GLOBALS['option_reads'][ $k ] = ( $GLOBALS['option_reads'][ $k ] ?? 0 ) + 1;
		return array_key_exists( $k, $GLOBALS['options'] ) ? $GLOBALS['options'][ $k ] : $d;
	}
	/** Opciones con carga automática (null: el doble no las simula y Toggle pregunta a get_option). */
	function wp_load_alloptions() {
		return $GLOBALS['alloptions'];
	}
	function wp_doing_ajax() {
		return $GLOBALS['doing_ajax'];
	}
	function update_option( $k, $v, $a = null ) {
		$GLOBALS['options'][ $k ] = $v;
		return true;
	}
	function get_site_option( $k, $d = false ) {
		return array_key_exists( $k, $GLOBALS['site'] ) ? $GLOBALS['site'][ $k ] : $d;
	}
	function update_site_option( $k, $v ) {
		$GLOBALS['site'][ $k ] = $v;
		return true;
	}
	function is_multisite() {
		return $GLOBALS['multisite'];
	}
	function is_network_admin() {
		return false;
	}
	function get_transient( $k ) {
		return $GLOBALS['transients'][ $k ] ?? false;
	}
	function set_transient( $k, $v, $e = 0 ) {
		$GLOBALS['transients'][ $k ] = $v;
		return true;
	}
	function delete_transient( $k ) {
		unset( $GLOBALS['transients'][ $k ] );
		return true;
	}
	function wp_normalize_path( $p ) {
		return str_replace( '\\', '/', $p );
	}
	function get_file_data( $file, $headers, $context = '' ) {
		$text = (string) file_get_contents( $file );
		$out  = array();
		foreach ( $headers as $key => $name ) {
			$out[ $key ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $text, $m ) ? trim( $m[1] ) : '';
		}
		return $out;
	}
	$GLOBALS['theme'] = array( 'digitalisimo-theme', 'digitalisimo-theme' );
	function get_template() {
		return $GLOBALS['theme'][0];
	}
	function get_stylesheet() {
		return $GLOBALS['theme'][1];
	}
	function get_post_types() {
		return array( 'post' => 'post', 'page' => 'page', 'elementor_library' => 'elementor_library', 'elementor_snippet' => 'elementor_snippet', 'digitalisimo_query' => 'digitalisimo_query' );
	}
	function get_taxonomies() {
		return array( 'category' => 'category', 'elementor_library_type' => 'elementor_library_type', 'elementor_library_category' => 'elementor_library_category' );
	}
	function wp_json_encode( $v, $f = 0 ) {
		return json_encode( $v, $f );
	}
	function esc_html( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES );
	}
	function esc_attr( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES );
	}
	function esc_url( $t ) {
		return (string) $t;
	}
	function admin_url( $p = '' ) {
		return 'http://ensayo.test/wp-admin/' . ltrim( $p, '/' );
	}
	function network_admin_url( $p = '' ) {
		return 'http://ensayo.test/wp-admin/network/' . ltrim( $p, '/' );
	}
	function rest_url( $p = '' ) {
		return 'http://ensayo.test/wp-json/' . ltrim( $p, '/' );
	}
	function add_query_arg( $args, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
	}
	function wp_nonce_field( $a ) {
		echo '<input type="hidden" name="_wpnonce" value="n">';
	}
	function selected( $a, $b, $echo = true ) {
		return (string) $a === (string) $b ? ' selected="selected"' : '';
	}
	function checked( $a, $b, $echo = true ) {
		return $a == $b ? ' checked="checked"' : ''; // phpcs:ignore
	}
	function get_current_blog_id() {
		return 1;
	}
	function sanitize_key( $k ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $k ) );
	}
	function wp_unslash( $v ) {
		return $v;
	}
	function is_admin_bar_showing() {
		return $GLOBALS['admin_bar'];
	}
	function is_preview() {
		return false;
	}

	final class Digitalisimo_Elements_Core {
		public static $bundled = true;
		const EXTERNAL_PLUGINS = array( 'elementor/elementor.php', 'elementor-pro/elementor-pro.php' );
		public static function bundled() {
			return self::$bundled;
		}
	}

	$el = dirname( __DIR__ ) . '/digitalisimo-elements';
	if ( ! is_file( $el . '/modules/digitalisimo-marca/class-module.php' ) ) {
		echo "FALLA no existe modules/digitalisimo-marca/ (código anterior)\n";
		exit( 1 );
	}
	require $el . '/digitalisimo-identity.php';
	require $el . '/modules/digitalisimo-marca/class-module.php';
	require $el . '/modules/digitalisimo-mcp/class-brand-server.php';
	require $el . '/modules/digitalisimo-mcp/class-legacy-route.php';

	use Digitalisimo\Elements\Marca\Exports;
	use Digitalisimo\Elements\Marca\Addons;
	use Digitalisimo\Elements\Marca\Admin_Urls;
	use Digitalisimo\Elements\Marca\Map;
	use Digitalisimo\Elements\Marca\Toggle;

	/** Map::set_active() si existe (con el código anterior a la revisión no existe: las comprobaciones fallan solas). */
	function mp_set_active( $v ) {
		if ( method_exists( '\\Digitalisimo\\Elements\\Marca\\Map', 'set_active' ) ) {
			\Digitalisimo\Elements\Marca\Map::set_active( $v );
		}
	}

	// M1 -----------------------------------------------------------------------------------------------------------------
	Map::set_tools( array( 'create-page', 'list-posts', 'get-page-structure' ) );
	// Con la marca propia en vigor (los alias de URL sólo entonces; abajo, apagada).
	mp_set_active( true );
	$out = Map::out( 'Tipo elementor_library y elementor_library_type=popup; meta _elementor_data; ver elementor://global-variables y elementor/list-posts o elementor-create-page. Editar: post.php?post=3&action=elementor · admin.php?page=elementor-tools. Elementor Pro.' );
	check( 'Tipo digitalisimo_library y digitalisimo_library_type=popup; meta _dg_data; ver digitalisimo://global-variables y digitalisimo-list-posts o digitalisimo-create-page. Editar: post.php?post=3&action=digitalisimo · admin.php?page=digitalisimo-motor-tools. DIGITALÍSIMO Elements.' === $out, 'M1 la salida renombra los identificadores y el nombre', $out );
	$keep = 'https://ensayo.test/wp-content/uploads/2026/11/elementor-banner.jpg /wp-content/plugins/digitalisimo-elements/elementor/assets/x.css uploads/elementor/css/post-3.css .elementor-widget-heading elementor-heading-title digitalisimo_query _digitalisimo_actions elementor/frontend/init action=elementor_ajax';
	check( $keep === Map::ids( $keep ), 'M1 no toca medios, rutas de archivos, clases, ganchos, acciones AJAX ni nombres propios', Map::ids( $keep ) );
	check( 'Escrito en Elementor' === Map::ids( 'Escrito en Elementor' ), 'M1 ids() no cambia palabras del contenido' );
	$in = Map::in( $out );
	check( false !== strpos( $in, 'elementor_library_type=popup' ) && false !== strpos( $in, '_elementor_data' ) && false !== strpos( $in, 'elementor://global-variables' ) && false !== strpos( $in, 'action=elementor ' ) && false !== strpos( $in, 'page=elementor-tools' ), 'M1 la entrada deshace los identificadores', $in );
	check( 'digitalisimo_query _digitalisimo_actions digitalisimo-sales-create-page' === Map::in( 'digitalisimo_query _digitalisimo_actions digitalisimo-sales-create-page' ), 'M1 la entrada nunca toca nombres propios' );
	$data = Map::data_out( array( 'message' => 'Hecho con Elementor', 'title' => 'Elementor para principiantes', 'post_type' => 'elementor_library', '_elementor_edit_mode' => 'builder' ) );
	check( array( 'message' => 'Hecho con DIGITALÍSIMO Elements', 'title' => 'Elementor para principiantes', 'post_type' => 'digitalisimo_library', '_dg_edit_mode' => 'builder' ) === $data, 'M1 en los datos, la marca sólo en los textos del motor', $data );
	// I-2 de la revisión: apagada, los enlaces llevan los nombres de siempre (con el Elementor original activo, nadie
	// traduciría el alias de vuelta y `action=digitalisimo` abriría post.php con una acción desconocida).
	mp_set_active( false );
	$off = Map::out( 'Editar: https://ensayo.test/wp-admin/post.php?post=5&action=elementor · admin.php?page=elementor-tools' );
	check( 'Editar: https://ensayo.test/wp-admin/post.php?post=5&action=elementor · admin.php?page=elementor-tools' === $off, 'M1 (I-2) con la marca apagada, los enlaces salen con los nombres de siempre', $off );
	mp_set_active( null );
	$GLOBALS['options'] = array();
	Toggle::reset();
	check( false !== strpos( Map::out( 'post.php?post=5&action=elementor' ), 'action=elementor' ), 'M1 (I-2) sin decirlo la prueba, decide el interruptor (apagado de fábrica)' );
	update_option( Toggle::OPTION, array( 'mode' => 'on' ) );
	Toggle::reset();
	check( false !== strpos( Map::out( 'post.php?post=5&action=elementor' ), 'action=digitalisimo' ), 'M1 (I-2) encendido, con alias' );
	Digitalisimo_Elements_Core::$bundled = false;
	Toggle::reset();
	check( false !== strpos( Map::out( 'post.php?post=5&action=elementor' ), 'action=elementor' ), 'M1 (I-2) con el Elementor original activo, nunca el alias' );
	Digitalisimo_Elements_Core::$bundled = true;
	$GLOBALS['options'] = array();
	Toggle::reset();
	// M-2 de la revisión: `_dg_` es un prefijo común de terceros; sólo las metas del motor cambian de nombre.
	$s8 = Map::in( 'precio _dg_precio y _dg_data, _dg_global_classes y _dg_global_' );
	check( 'precio _dg_precio y _elementor_data, _elementor_global_classes y _dg_global_' === $s8, 'M1 (M-2) la entrada sólo devuelve al motor sus metas: _dg_precio de otro plugin queda igual', $s8 );
	check( '_elementor_precio _dg_page_settings' === Map::out( '_elementor_precio _elementor_page_settings' ), 'M1 (M-2) y una _elementor_<x> que no es del motor no sale renombrada (no habría cómo devolverla)' );
	$wxr = Exports::wxr_import( array( 'post_type' => 'digitalisimo_library', 'postmeta' => array( array( 'key' => '_dg_precio', 'value' => '9' ), array( 'key' => '_dg_edit_mode', 'value' => 'builder' ) ) ) );
	check( 'elementor_library' === $wxr['post_type'] && '_dg_precio' === $wxr['postmeta'][0]['key'] && '_elementor_edit_mode' === $wxr['postmeta'][1]['key'], 'M1 (M-2) el importador WXR no convierte una meta ajena _dg_* en una del motor', $wxr );
	$xml = Exports::wxr( '<wp:postmeta><wp:meta_key><![CDATA[_elementor_precio]]></wp:meta_key></wp:postmeta><wp:postmeta><wp:meta_key><![CDATA[_elementor_data]]></wp:meta_key></wp:postmeta>' );
	check( false !== strpos( $xml, '_elementor_precio' ) && false !== strpos( $xml, '_dg_data' ), 'M1 (M-2) el WXR exportado sólo renombra las metas del motor', $xml );
	// Toda meta `_elementor_*` escrita en el PHP del núcleo, de PRO y de los módulos está en Map::METAS (AGENTS.md).
	$missing = array();
	$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $el, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( '.php' !== substr( (string) $f, -4 ) || false !== strpos( (string) $f, '/vendor/' ) ) {
			continue;
		}
		if ( preg_match_all( '/[\'"]_elementor_([a-z0-9_]+)[\'"]/', (string) file_get_contents( (string) $f ), $mm ) ) {
			foreach ( $mm[1] as $name ) {
				// Un prefijo (termina en `_`) está en la lista tal cual; un nombre, por engine_meta().
				if ( ! defined( Map::class . '::METAS' ) || ! ( in_array( $name, Map::METAS, true ) || Map::engine_meta( $name ) ) ) {
					$missing[ $name ] = true;
				}
			}
		}
	}
	check( array() === $missing, 'M1 (M-2) Map::METAS cubre las metas _elementor_* del código (súmalas si una versión nueva del núcleo trae otra)', array_keys( $missing ) );

	// M2 -----------------------------------------------------------------------------------------------------------------
	@mkdir( WP_PLUGIN_DIR . '/addon-x', 0777, true );
	@mkdir( WP_PLUGIN_DIR . '/digitalisimo-seo', 0777, true );
	@mkdir( WP_PLUGIN_DIR . '/hook-addon', 0777, true );
	@mkdir( WP_PLUGIN_DIR . '/woocommerce', 0777, true );
	file_put_contents( WP_PLUGIN_DIR . '/addon-x/addon-x.php', "<?php\n/**\n * Plugin Name: Addons X para el constructor\n * Elementor tested up to: 3.30\n */\n" );
	file_put_contents( WP_PLUGIN_DIR . '/digitalisimo-seo/digitalisimo-seo.php', "<?php\n/**\n * Plugin Name: DIGITALÍSIMO SEO\n * Requires Plugins: elementor\n */\n" );
	file_put_contents( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php', "<?php\n/**\n * Plugin Name: WooCommerce\n */\n" );
	file_put_contents( WP_PLUGIN_DIR . '/hook-addon/hook-addon.php', "<?php\n/**\n * Plugin Name: Widgets sueltos\n */\nfunction hook_addon_widgets() {}\n" );
	require WP_PLUGIN_DIR . '/hook-addon/hook-addon.php';

	$GLOBALS['options']['active_plugins'] = array( 'woocommerce/woocommerce.php', 'digitalisimo-seo/digitalisimo-seo.php', 'elementor/elementor.php' );
	Toggle::reset();
	check( 'off' === Toggle::values()['mode'] && 'default' === Toggle::values()['origin'] && ! Toggle::active(), 'M2 apagada por defecto' );
	check( 'off' === Toggle::status( true )['state'] && array() === Toggle::status()['addons'], 'M2 sin complementos ajenos: los propios, WooCommerce y el Elementor original no cuentan', Toggle::status() );
	// M-3 de la revisión: un sitio que nunca guardó la opción no le pregunta a la base en cada petición (no está entre
	// las opciones con carga automática), y sin el núcleo incluido ni se mira.
	$GLOBALS['alloptions']   = array( 'siteurl' => 'http://ensayo.test' );
	$GLOBALS['option_reads'] = array();
	Toggle::reset();
	check( ! Toggle::active() && 'default' === Toggle::values()['origin'] && 0 === ( $GLOBALS['option_reads'][ Toggle::OPTION ] ?? 0 ), 'M2 (M-3) sin la opción guardada, ninguna lectura de la opción', $GLOBALS['option_reads'] );
	Digitalisimo_Elements_Core::$bundled = false;
	$GLOBALS['alloptions']               = null;
	$GLOBALS['option_reads']             = array();
	Toggle::reset();
	check( ! Toggle::active() && 0 === ( $GLOBALS['option_reads'][ Toggle::OPTION ] ?? 0 ), 'M2 (M-3) con el Elementor original activo, tampoco', $GLOBALS['option_reads'] );
	Digitalisimo_Elements_Core::$bundled = true;
	update_option( Toggle::OPTION, array( 'mode' => 'on', 'force' => false ) );
	$GLOBALS['alloptions'] = array( Toggle::OPTION => $GLOBALS['options'][ Toggle::OPTION ] );
	Toggle::reset();
	check( Toggle::active() && 'site' === Toggle::values()['origin'], 'M2 (M-3) guardada con carga automática, se lee' );
	$GLOBALS['alloptions'] = null;
	Toggle::reset();
	check( Toggle::active() && 'site' === Toggle::values()['origin'], 'M2 encendida en el sitio' );
	// I-4 de la revisión: antes de `init` y sin la lista de complementos en caché (sus ganchos aún no existen), apagada;
	// forzada o con la lista en caché, encendida. Y con el paquete, la respuesta es la que decidió el paquete.
	$GLOBALS['transients'] = array();
	unset( $GLOBALS['done']['init'] );
	Toggle::reset();
	check( ! Toggle::active(), 'M2 (I-4) antes de init sin la lista de complementos en caché: apagada (no se adivina)' );
	$GLOBALS['done']['init'] = 1;
	\Digitalisimo\Elements\Marca\Addons::detect( true );
	unset( $GLOBALS['done']['init'] );
	Toggle::reset();
	check( Toggle::active(), 'M2 (I-4) con la lista en caché, sí' );
	$GLOBALS['done']['init'] = 1;
	\Digitalisimo\Elements\Marca\Package::reset( null, 'on', true );
	Toggle::reset();
	check( ! Toggle::active(), 'M2 (I-4) con el paquete en su respaldo (faltan los originales), la marca no está en vigor' );
	\Digitalisimo\Elements\Marca\Package::reset( null, 'off' );
	Toggle::reset();
	check( ! Toggle::active(), 'M2 (I-4) con el paquete decidido «off», Toggle responde lo mismo aunque la opción diga «on»' );
	\Digitalisimo\Elements\Marca\Package::reset();
	Toggle::reset();
	Digitalisimo_Elements_Core::$bundled = false;
	Toggle::reset();
	check( ! Toggle::active() && 'unavailable' === Toggle::status()['state'], 'M2 no disponible con el Elementor original (sin núcleo incluido)' );
	Digitalisimo_Elements_Core::$bundled = true;
	Toggle::reset();

	// M3 -----------------------------------------------------------------------------------------------------------------
	$GLOBALS['options']['active_plugins'][] = 'addon-x/addon-x.php';
	$list = Addons::detect( true );
	check( 1 === count( $list ) && 'addon-x/addon-x.php' === $list[0]['file'] && false !== strpos( $list[0]['why'], 'cabecera' ), 'M3 un complemento con «Elementor tested up to» se detecta', $list );
	Toggle::reset();
	check( 'blocked' === Toggle::status( true )['state'] && ! Toggle::active(), 'M3 con un complemento, bloqueada por defecto (D-5)' );
	update_option( Toggle::OPTION, array( 'mode' => 'on', 'force' => true ) );
	Toggle::reset();
	check( Toggle::active() && 'on' === Toggle::status( true )['state'] && 1 === count( Toggle::status()['addons'] ), 'M3 forzada por el administrador: en vigor, y la lista se sigue mostrando' );
	$GLOBALS['options']['active_plugins'] = array( 'hook-addon/hook-addon.php' );
	add_action( 'elementor/widgets/register', 'hook_addon_widgets' );
	add_action( 'elementor/widgets/register', array( Map::class, 'tools' ) );
	$list = Addons::detect( true );
	check( 1 === count( $list ) && 'hook-addon/hook-addon.php' === $list[0]['file'] && 'Widgets sueltos' === $list[0]['name'] && false !== strpos( $list[0]['why'], 'elementor/widgets/register' ), 'M3 un plugin que se engancha a elementor/widgets/register se detecta (y una función propia no)', $list );
	$GLOBALS['options']['active_plugins'] = array();
	$GLOBALS['wp_filter']                 = array();
	$GLOBALS['theme']                     = array( 'hello-elementor', 'hello-theme-child-master' );
	$list                                 = Addons::detect( true );
	check( 1 === count( $list ) && 'themes/hello-elementor' === $list[0]['file'], 'M3 el tema Hello Elementor (padre de un hijo) se detecta', $list );
	$GLOBALS['theme'] = array( 'digitalisimo-theme', 'digitalisimo-theme' );
	check( array() === Addons::detect( true ), 'M3 con el tema DIGITALÍSIMO y sin complementos, nada' );
	$GLOBALS['multisite'] = true;
	$GLOBALS['options']   = array();
	update_site_option( Toggle::NETWORK_OPTION, array( 'mode' => 'on', 'force' => false ) );
	Toggle::reset();
	check( Toggle::active() && 'network' === Toggle::values()['origin'], 'M2 en una red, un sitio sin valor propio hereda la red' );
	update_option( Toggle::OPTION, array( 'mode' => 'off', 'force' => false, 'inherit' => false ) );
	Toggle::reset();
	check( ! Toggle::active() && 'site' === Toggle::values()['origin'], 'M2 un sitio con valor propio no hereda' );
	update_option( Toggle::OPTION, array( 'mode' => 'off', 'force' => false, 'inherit' => true ) );
	Toggle::reset();
	check( Toggle::active(), 'M2 «Heredar de la red» vuelve al valor de la red' );
	$GLOBALS['multisite'] = false;
	$GLOBALS['site']      = array();

	// M4 -----------------------------------------------------------------------------------------------------------------
	$args = Admin_Urls::translate_args( array( 'post' => '3', 'action' => 'digitalisimo' ), 'post.php' );
	check( array( 'post' => '3', 'action' => 'elementor' ) === $args, 'M4 post.php?action=digitalisimo abre el editor (action=elementor)', $args );
	check( array( 'action' => 'digitalisimo' ) === Admin_Urls::translate_args( array( 'action' => 'digitalisimo' ), 'admin-post.php' ), 'M4 action=digitalisimo sólo se traduce en post.php' );
	$args = Admin_Urls::translate_args( array( 'page' => 'digitalisimo-motor-settings' ) );
	check( array( 'page' => 'elementor-settings' ) === $args && array( 'page' => 'elementor' ) === Admin_Urls::translate_args( array( 'page' => 'digitalisimo-motor' ) ), 'M4 page=digitalisimo-motor… abre la pantalla del constructor' );
	$args = Admin_Urls::translate_args( array( 'post_type' => 'digitalisimo_library', 'digitalisimo_library_type' => 'popup', 'tabs_group' => 'library' ), 'edit.php' );
	check( array( 'post_type' => 'elementor_library', 'elementor_library_type' => 'popup', 'tabs_group' => 'library' ) === $args, 'M4 la lista de plantillas con alias (clave y valor)', $args );
	check( array( 'post_type' => 'elementor_library', 'page' => 'elementor-tools', 'action' => 'elementor' ) === Admin_Urls::translate_args( array( 'post_type' => 'elementor_library', 'page' => 'elementor-tools', 'action' => 'elementor' ), 'post.php' ), 'M4 la URL vieja sigue igual' );
	$GLOBALS['pagenow']    = 'edit.php';
	$_GET                  = array( 'post_type' => 'digitalisimo_library', 'digitalisimo_library_type' => 'popup' );
	$_REQUEST              = $_GET;
	$_POST                 = array();
	$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=digitalisimo_library&digitalisimo_library_type=popup';
	Admin_Urls::translate_request();
	$pro = (string) file_get_contents( $el . '/pro-elements.php' );
	check( false !== strpos( $pro, 'Admin_Urls::translate_request()' ) && strpos( $pro, 'Admin_Urls::translate_request()' ) > strpos( $pro, 'Digitalisimo_Elements_Core::load();' ) && strpos( $pro, 'Admin_Urls::translate_request()' ) < strpos( $pro, "add_action( 'plugins_loaded'" ), 'M4 la traducción corre al cargar Elements, antes de plugins_loaded (las guardas de widgets y la App del núcleo deciden ahí con la URL vieja)' );
	check( 'elementor_library' === $_GET['post_type'] && 'popup' === ( $_REQUEST['elementor_library_type'] ?? '' ) && '/wp-admin/edit.php?post_type=elementor_library&elementor_library_type=popup' === $_SERVER['REQUEST_URI'], 'M4 la petición se traduce antes de que WordPress la lea ($_GET, $_REQUEST, REQUEST_URI)', array( $_GET, $_SERVER['REQUEST_URI'] ) );
	Digitalisimo_Elements_Core::$bundled = false;
	$_GET                                = array( 'post_type' => 'digitalisimo_library' );
	Admin_Urls::$translated              = false;
	Admin_Urls::translate_request();
	check( 'digitalisimo_library' === $_GET['post_type'], 'M4 con el Elementor original activo no se traduce nada' );
	Digitalisimo_Elements_Core::$bundled = true;
	$u = 'http://ensayo.test/wp-admin/edit.php?post_type=elementor_library&elementor_library_type=popup#x';
	check( 'http://ensayo.test/wp-admin/edit.php?post_type=digitalisimo_library&digitalisimo_library_type=popup#x' === Admin_Urls::url_out( $u ), 'M4 url_out con alias', Admin_Urls::url_out( $u ) );
	check( 'post.php?post=3&action=digitalisimo' === Admin_Urls::url_out( 'post.php?post=3&action=elementor' ) && 'admin.php?page=digitalisimo-motor-app#/site-editor' === Admin_Urls::url_out( 'admin.php?page=elementor-app#/site-editor' ), 'M4 url_out del editor y de la App' );
	// M-1 de la revisión: el wp-admin de OTRO dominio no tiene nuestros alias.
	foreach ( array( 'https://otro.test/wp-admin/post.php?post=1&action=elementor', '//otro.test/wp-admin/admin.php?page=elementor', 'http://ensayo.test:8080/wp-admin/admin.php?page=elementor' ) as $other ) {
		check( $other === Admin_Urls::url_out( $other ), 'M4 (M-1) url_out no toca el escritorio de otro dominio: ' . $other, Admin_Urls::url_out( $other ) );
	}
	check( 'http://ENSAYO.test/wp-admin/admin.php?page=digitalisimo-motor' === Admin_Urls::url_out( 'http://ENSAYO.test/wp-admin/admin.php?page=elementor' ), 'M4 (M-1) el propio, sin importar mayúsculas' );
	check( 'post.php?post=7&#038;action=digitalisimo' === Admin_Urls::url_out( 'post.php?post=7&#038;action=elementor' ), 'M4 (M-5) un & escapado (&#038;) no es un ancla: el enlace sale con su alias', Admin_Urls::url_out( 'post.php?post=7&#038;action=elementor' ) );
	foreach ( array( 'http://ensayo.test/elementor-tips/', 'http://ensayo.test/wp-content/uploads/elementor/css/post-1.css', 'http://ensayo.test/?elementor_library=cabecera' ) as $front ) {
		check( $front === Admin_Urls::url_out( $front ), 'M4 url_out no toca el front: ' . $front );
	}
	$GLOBALS['options'] = array();
	Toggle::reset();
	check( 'admin.php?page=elementor-settings' === Admin_Urls::redirect( 'admin.php?page=elementor-settings' ), 'M4 sin la marca propia, las redirecciones no cambian' );
	ob_start();
	Admin_Urls::print_script();
	check( '' === ob_get_clean(), 'M4 sin la marca propia, no se imprime el script de alias' );
	update_option( Toggle::OPTION, array( 'mode' => 'on' ) );
	Toggle::reset();
	check( 'http://ensayo.test/wp-admin/admin.php?page=digitalisimo-motor-settings&settings-updated=1' === Admin_Urls::redirect( 'http://ensayo.test/wp-admin/admin.php?page=elementor-settings&settings-updated=1' ), 'M4 con la marca propia, las redirecciones salen con alias' );
	ob_start();
	Admin_Urls::print_script();
	$js = ob_get_clean();
	check( false !== strpos( $js, 'digitalisimo-marca-urls' ) && false !== strpos( $js, '"elementor_library":"digitalisimo_library"' ) && false !== strpos( $js, 'replaceState' ), 'M4 con la marca propia se imprime el script de alias con el mapa' );
	// El script en un DOM mínimo con Node: barra de direcciones, enlaces, formularios GET y history.pushState.
	file_put_contents( $tmp . '/urls.js', Admin_Urls::script() );
	$harness = <<<'JS'
const fs = require('fs');
const code = fs.readFileSync(process.argv[2], 'utf8');
const mk = (tag, attrs, kids = []) => ({ nodeType: 1, tagName: tag, attrs: { ...attrs }, kids, name: attrs.name, value: attrs.value,
  getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; }, setAttribute(k, v) { this.attrs[k] = v; },
  matches(sel) { return sel.split(',').some((s) => (s === 'a[href]' && this.tagName === 'A' && 'href' in this.attrs) || (s === 'form' && this.tagName === 'FORM')); },
  querySelectorAll(sel) { const out = []; const walk = (n) => n.kids.forEach((k) => { if (sel.startsWith('input') ? k.tagName === 'INPUT' : k.matches(sel)) out.push(k); walk(k); }); walk(this); return out; } });
const hidden = mk('INPUT', { type: 'hidden', name: 'post_type', value: 'elementor_library' });
const form = mk('FORM', { action: 'edit.php', method: 'get' }, [hidden]);
const links = [mk('A', { href: 'admin.php?page=elementor-settings' }), mk('A', { href: 'http://ensayo.test/wp-admin/post.php?post=3&action=elementor' }), mk('A', { href: 'https://otro.test/wp-admin/admin.php?page=elementor' }), mk('A', { href: 'http://ensayo.test/elementor-tips/' })];
const root = mk('HTML', {}, [...links, form]);
let href = 'http://ensayo.test/wp-admin/edit.php?post_type=elementor_library&elementor_library_type=popup';
global.location = { get href() { return href; }, origin: 'http://ensayo.test' };
global.history = { state: null, pushState(s, t, u) { href = new URL(u, href).href; }, replaceState(s, t, u) { href = new URL(u, href).href; } };
global.document = { readyState: 'complete', documentElement: root, addEventListener() {} };
global.window = global; global.addEventListener = () => {};
eval(code);
global.setTimeout(() => {
  const first = href;
  history.pushState(null, '', 'post.php?post=9&action=elementor');
  console.log(JSON.stringify({ first, pushed: href, links: links.map((l) => l.attrs.href), hidden: [hidden.name, hidden.value] }));
}, 20);
JS;
	file_put_contents( $tmp . '/harness.js', $harness );
	$res = json_decode( (string) shell_exec( 'node ' . escapeshellarg( $tmp . '/harness.js' ) . ' ' . escapeshellarg( $tmp . '/urls.js' ) . ' 2>&1' ), true );
	check( is_array( $res ) && 'http://ensayo.test/wp-admin/edit.php?post_type=digitalisimo_library&digitalisimo_library_type=popup' === $res['first'], 'M4 la barra de direcciones muestra el alias', $res );
	check( is_array( $res ) && 'http://ensayo.test/wp-admin/post.php?post=9&action=digitalisimo' === $res['pushed'], 'M4 history.pushState del editor también', $res );
	check( is_array( $res ) && array( 'http://ensayo.test/wp-admin/admin.php?page=digitalisimo-motor-settings', 'http://ensayo.test/wp-admin/post.php?post=3&action=digitalisimo', 'https://otro.test/wp-admin/admin.php?page=elementor', 'http://ensayo.test/elementor-tips/' ) === $res['links'], 'M4 los enlaces del escritorio con alias; los de otro sitio y del front, igual', $res );
	check( is_array( $res ) && array( 'post_type', 'digitalisimo_library' ) === $res['hidden'], 'M4 los formularios GET envían el alias', $res );
	@unlink( $tmp . '/urls.js' );
	@unlink( $tmp . '/harness.js' );

	// B-5: el valor de `<input name="action">` con alias también en admin-post.php (admin_post_elementor_<x>).
	add_action( 'admin_post_elementor_ensayo', 'hook_addon_widgets' );
	$GLOBALS['pagenow'] = 'admin-post.php';
	$_GET               = array();
	$_POST              = array( 'action' => 'digitalisimo_ensayo' );
	$_REQUEST           = $_POST;
	Admin_Urls::admin_action();
	check( 'elementor_ensayo' === $_POST['action'] && 'elementor_ensayo' === $_REQUEST['action'], 'M4 (B-5) action=digitalisimo_<x> llega a admin_post_elementor_<x>' );
	$_POST    = array( 'action' => 'digitalisimo_otra' );
	$_REQUEST = $_POST;
	Admin_Urls::admin_action();
	check( 'digitalisimo_otra' === $_POST['action'], 'M4 (B-5) sin acción del motor con ese nombre, no se toca' );
	$GLOBALS['pagenow'] = 'index.php';

	// M5 -----------------------------------------------------------------------------------------------------------------
	$id = 'Digitalisimo_Elements_Identity';
	check( 'Componentes atómicos DIGITALÍSIMO' === $id::gettext( 'Widgets atómicos', 'Atomic Widgets', 'elementor' ) && 'Componentes atómicos DIGITALÍSIMO' === $id::gettext( 'Atomic Widgets', 'Atomic Widgets', 'elementor' ), 'M5 el título del experimento (inglés y traducido)' );
	$pro = $id::gettext( 'Atomic form widgets. Note: This feature requires the "Atomic Widgets" experiment to be enabled.', 'Atomic form widgets. Note: This feature requires the "Atomic Widgets" experiment to be enabled.', 'elementor-pro' );
	check( false !== strpos( $pro, '"Componentes atómicos DIGITALÍSIMO"' ) && false === strpos( $pro, 'Atomic Widgets' ), 'M5 la descripción de PRO que cita el experimento', $pro );
	check( 'Requiere «Componentes atómicos DIGITALÍSIMO».' === $id::gettext( 'Requiere «Widgets atómicos».', 'x', 'elementor-pro' ) && 'Activa los widgets atómicos.' === $id::gettext( 'Activa los widgets atómicos.', 'Enable atomic widgets.', 'elementor' ), 'M5 traducido entre comillas sí; una frase suelta no' );
	check( 'Atomic Widgets' === $id::gettext( 'Atomic Widgets', 'Atomic Widgets', 'otro-plugin' ), 'M5 otros dominios no cambian' );
	$scripts = new class() {
		public $inline = array();
		public function add_inline_script( $h, $js ) {
			$this->inline[] = $h;
		}
	};
	function wp_scripts() {
		return $GLOBALS['scripts'];
	}
	$GLOBALS['scripts']   = $scripts;
	$GLOBALS['admin_bar'] = false;
	$id::attach_front_script_filters();
	check( array() === $scripts->inline, 'M5 un visitante del front no recibe el filtro de textos (sin «Elementor» en su página)' );
	// I-3 de la revisión: en admin-ajax y admin-post no se arma el registro de scripts (no pintan nada); y si aún no
	// existe, se espera a `wp_default_scripts` en vez de crearlo.
	$GLOBALS['doing_ajax'] = true;
	$GLOBALS['wp_filter']  = array();
	$id::attach_script_filters();
	$GLOBALS['doing_ajax'] = false;
	$GLOBALS['pagenow']    = 'admin-post.php';
	$id::attach_script_filters();
	check( array() === $scripts->inline && empty( $GLOBALS['wp_filter']['wp_default_scripts'] ), 'M5 (I-3) en admin-ajax y admin-post, nada (ni wp_scripts() ni el gancho)', $scripts->inline );
	$GLOBALS['pagenow'] = 'index.php';
	$id::attach_script_filters();
	check( array() === $scripts->inline && ! empty( $GLOBALS['wp_filter']['wp_default_scripts'] ), 'M5 (I-3) en el escritorio, sin registro de scripts todavía, se engancha a wp_default_scripts sin crearlo' );
	$GLOBALS['done']['wp_default_scripts'] = 1;
	$GLOBALS['admin_bar']                  = true;
	$id::attach_front_script_filters();
	$id::attach_script_filters();
	check( array( 'wp-i18n' ) === $scripts->inline, 'M5 con la barra de administración o en el escritorio sí (una sola vez)', $scripts->inline );

	// M6 -----------------------------------------------------------------------------------------------------------------
	$GLOBALS['options']['active_plugins'] = array( 'addon-x/addon-x.php' );
	update_option( Toggle::OPTION, array( 'mode' => 'on', 'force' => false ) );
	update_option( \Digitalisimo\Elements\Mcp\Legacy_Route::OPTION, array( 'last' => gmdate( 'Y-m-d' ), 'tools' => array( 'elementor-create-page' => 2 ), 'extended' => '' ) );
	Toggle::reset();
	delete_transient( Addons::CACHE );
	ob_start();
	\Digitalisimo\Elements\Marca\Settings_Section::render( false );
	$html = ob_get_clean();
	check( false !== strpos( $html, 'id="digitalisimo-marca"' ) && false !== strpos( $html, 'data-state="blocked"' ) && false !== strpos( $html, 'Addons X para el constructor' ) && false !== strpos( $html, '[force]' ), 'M6 «Rendimiento» muestra la marca propia bloqueada con la lista y la casilla para forzarla', $html );
	check( false !== strpos( $html, 'http://ensayo.test/wp-json/digitalisimo/mcp' ) && false !== strpos( $html, 'digitalisimo-marca-legacy' ) && false !== strpos( $html, '2027-01-31' ), 'M6 muestra el MCP nuevo y cuándo se usó el viejo', $html );
	check( false !== strpos( $html, 'action" value="digitalisimo_elements_marca_save"' ) && false !== strpos( $html, '_wpnonce' ), 'M6 formulario propio con acción y nonce' );

	array_map( 'unlink', glob( WP_PLUGIN_DIR . '/*/*.php' ) );
	array_map( 'rmdir', glob( WP_PLUGIN_DIR . '/*' ) );
	@rmdir( WP_PLUGIN_DIR );
	echo "\n$GLOBALS[fails] fallos de $GLOBALS[checks] comprobaciones\n";
	exit( $GLOBALS['fails'] ? 1 : 0 );
}
