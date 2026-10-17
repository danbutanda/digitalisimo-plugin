<?php
/**
 * DIGITALÍSIMO Elements sin rastro del proveedor del núcleo incluido: el escudo corta toda petición del constructor
 * hacia sus dominios (con respuestas válidas donde el núcleo las necesita), las cachés remotas se sirven vacías,
 * telemetría, IA y experimentos del proveedor quedan apagados, avisos y sugerencias se dan por vistos, y no quedan
 * rutas REST, redirecciones ni accesos de cuenta. Con el plugin Elementor original activo no se engancha nada.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __FILE__ ); // el «constructor» de la prueba es este directorio.
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	function check_guard( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['t'] = array( 'hooks' => array(), 'meta' => array(), 'options' => array() );
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['t']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['t']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function remove_filter() { return true; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function admin_url( $path = '' ) { return 'https://sitio.test/wp-admin/' . $path; }
	function get_user_meta( $id, $key, $single ) { return $GLOBALS['t']['meta'][ $key ] ?? ''; }
	final class WP_Error { public $code; public function __construct( $code = '', $message = '' ) { $this->code = $code; } }
	final class WP_REST_Response { public $data; public $status; public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; } }
	final class Digitalisimo_Elements_Core {
		public static $bundled = false;
		public static function bundled() { return self::$bundled; }
	}
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-vendor-free.php';
	$g = 'Digitalisimo_Elements_Vendor_Free';

	$g::init();
	check_guard( array() === $GLOBALS['t']['hooks'], 'Con el plugin Elementor original activo no se engancha nada.' );
	Digitalisimo_Elements_Core::$bundled = true;
	$g::init();
	foreach ( array( 'pre_http_request', 'pre_option_elementor_allow_tracking', 'pre_option_elementor_tracker_notice', 'get_user_option_elementor_enable_ai', 'get_user_metadata', 'rest_endpoints', 'rest_pre_dispatch', 'wp_redirect', 'elementor/editor/v2/packages', 'elementor/document/config', 'elementor/finder/categories' ) as $hook ) {
		check_guard( ! empty( $GLOBALS['t']['hooks'][ $hook ] ), 'Con el núcleo incluido se engancha ' . $hook . '.' );
	}
	foreach ( $g::REMOTE_CACHES as $option ) {
		check_guard( ! empty( $GLOBALS['t']['hooks'][ 'pre_option_' . $option ] ), 'La caché remota ' . $option . ' se sirve vacía.' );
	}
	foreach ( $g::EXPERIMENTS_OFF as $experiment ) {
		check_guard( 'inactive' === $g::inactive() && ! empty( $GLOBALS['t']['hooks'][ 'pre_option_elementor_experiment-' . $experiment ] ), 'Experimento del proveedor apagado: ' . $experiment . '.' );
	}
	check_guard( 'no' === $g::no() && '1' === $g::one() && '0' === $g::zero(), 'Sin telemetría, aviso de telemetría visto e IA del proveedor apagada.' );
	$cache = $g::empty_remote_cache();
	check_guard( $cache['timeout'] > time() + 365 * 86400 && '[]' === $cache['value'], 'La caché vacía está vigente: el núcleo no la vuelve a pedir.' );

	// Escudo: el constructor nunca habla con el proveedor.
	$blocked = array(
		'https://my.elementor.com/api/v2/info/'                                           => 200,
		'https://my.elementor.com/api/v1/templates/info/'                                 => 200,
		'https://my.elementor.com/api/connect/v1/library/templates'                       => 403,
		'https://my.elementor.com/api/v1/kits-library/kits'                               => 403,
		'https://my.elementor.com/api/v2/ai/text'                                         => 403,
		'https://cloud-library.prod.builder.elementor.red/api/v1/cloud-library/resources' => 403,
		'https://ms-8874.elementor.com/api/v1/kits'                                       => 403,
		'https://assets.elementor.com/home-screen/v1/home-screen.json'                    => 'error',
		'https://my.elementor.com/api/v1/tracker/'                                        => 'error',
		'https://planner.elementor.com/x'                                                 => 'error',
		'https://editor.elementor.com/v1/migrations/manifest.json'                        => 'error',
		'HTTPS://GO.ELEMENTOR.COM/x'                                                      => 'error',
		'https://api-eu.mixpanel.com/track'                                               => 'error',
		'https://cdn.mxpnl.com/libs/mixpanel.js'                                          => 'error',
		'https://proelements.org/x'                                                       => 'error',
	);
	foreach ( $blocked as $url => $expected ) {
		$r = $g::shield( false, array(), $url );
		if ( 'error' === $expected ) {
			check_guard( $r instanceof WP_Error, 'Bloquea con error: ' . $url );
		} else {
			check_guard( is_array( $r ) && $expected === $r['response']['code'] && is_string( $r['body'] ), 'Responde ' . $expected . ' sin salir: ' . $url );
		}
	}
	$info = $g::shield( false, array(), 'https://my.elementor.com/api/v1/templates/info/' );
	$body = json_decode( $info['body'], true );
	check_guard( array() === $body['types_data']['block']['categories'], 'La información de la biblioteca llega vacía y válida (con la lista de categorías que espera el listado): las plantillas locales siguen visibles.' );
	foreach ( array( 'https://notelementor.com/', 'https://elementor.com.example.org/', 'https://api.wordpress.org/plugins/update-check/1.1/', 'https://fonts.googleapis.com/css2', 'https://github.com/danbutanda/digitalisimo-plugin', 'https://player.vimeo.com/video/1', 'https://mixpanel.example.org/' ) as $url ) {
		check_guard( false === $g::shield( false, array(), $url ), 'Deja pasar lo que no es del proveedor: ' . $url );
	}
	$resolved = array( 'body' => 'x' );
	check_guard( $resolved === $g::shield( $resolved, array(), 'https://my.elementor.com/' ), 'Respeta una respuesta ya resuelta.' );
	// Un plugin del proveedor que el administrador instale por su cuenta no pasa por el escudo.
	$other = sys_get_temp_dir() . '/digi-otro-plugin-' . getmypid() . '.php';
	file_put_contents( $other, '<?php function digi_otro_plugin_request( $u ) { return Digitalisimo_Elements_Vendor_Free::shield( false, array(), $u ); }' );
	require $other;
	check_guard( false === digi_otro_plugin_request( 'https://my.elementor.com/api/v1/x' ), 'Sólo se corta lo que pide el constructor.' );
	unlink( $other );

	// Avisos y sugerencias del proveedor: vistos para todo usuario, sin perder los propios.
	$GLOBALS['t']['meta'] = array( 'elementor_admin_notices' => array( 'mio' => array( 'is_viewed' => true ) ), 'elementor_dismissed_editor_notices' => array( 'propio' ) );
	$notices = $g::user_meta( null, 1, 'elementor_admin_notices', true )[0];
	check_guard( isset( $notices['mio'] ) && true === $notices['rate_us_feedback']['is_viewed'] && true === $notices['plugin_image_optimization']['is_viewed'] && true === $notices['tracker']['is_viewed'], 'Avisos del proveedor vistos (valóranos, telemetría, Image Optimizer).' );
	$hints = $g::user_meta( null, 1, 'elementor_dismissed_editor_notices', true )[0];
	check_guard( in_array( 'propio', $hints, true ) && in_array( 'image_optimizer_hint', $hints, true ) && in_array( 'ally_heading_notice', $hints, true ), 'Sugerencias de productos del proveedor descartadas.' );
	check_guard( 'x' === $g::user_meta( 'x', 1, 'otra', true ), 'Las demás metas no cambian.' );

	// Rutas, redirecciones, menús y datos del editor.
	$routes = $g::rest_endpoints( array( '/elementor/v1/library/connect' => 1, '/elementor/v1/angie/consent' => 1, '/elementor-ai/v1/permissions' => 1, '/elementor-one/v1/top-bar/feedback' => 1, '/elementor/v1/globals' => 1, '/wp/v2/posts' => 1 ) );
	check_guard( array( '/elementor/v1/globals', '/wp/v2/posts' ) === array_keys( $routes ), 'Fuera las rutas de cuenta, Angie, IA y comentarios; las demás siguen.' );
	$notifications = new class() { public function get_route() { return '/elementor-one/v1/top-bar/notifications'; } };
	check_guard( $g::rest_pre_dispatch( null, null, $notifications ) instanceof WP_REST_Response, 'Las novedades de la barra superior se responden vacías sin pedirlas al proveedor.' );
	check_guard( 'https://sitio.test/wp-admin/admin.php?page=digitalisimo-elements-about' === $g::redirect( 'https://go.elementor.com/go-pro/' ) && 'https://sitio.test/x' === $g::redirect( 'https://sitio.test/x' ), 'Una redirección al proveedor lleva a la página propia.' );
	check_guard( in_array( 'elementor-app#/kit-library', $g::menu_slugs( array() ), true ), 'La biblioteca de kits del proveedor sale del menú.' );
	$settings = $g::editor_settings( array( 'user' => array( 'top_bar' => array( 'my_elementor_url' => 'https://go.elementor.com/x' ), 'name' => 'Ana' ), 'help' => 'https://go.elementor.com/docs', 'admin' => 'https://sitio.test/wp-admin/', 'icon' => 'eicon-elementor' ) );
	check_guard( array( 'my_elementor_url' => '' ) === $settings['user']['top_bar'] && 'Ana' === $settings['user']['name'] && '' === $settings['help'] && 'https://sitio.test/wp-admin/' === $settings['admin'] && 'eicon-elementor' === $settings['icon'], 'El editor no recibe accesos ni URL del proveedor (las claves que lee la barra superior siguen, vacías); lo propio y los identificadores siguen.' );
	$config = $g::document_config( array() );
	check_guard( 'templates/my-templates' === $config['remoteLibrary']['default_route'] && '' === $config['panel']['needHelpUrl'], 'La biblioteca abre en «Mis plantillas» y sin ayuda del proveedor.' );
	check_guard( array( 'editor-elements', 'editor-ui' ) === $g::editor_packages( array( 'editor-elements', 'editor-widget-creation', 'editor-ui' ) ), 'Sin el paquete de promoción de Angie.' );
	$finder = $g::finder( array( 'general' => array( 'items' => array( 'knowledge-base' => 1, 'kit-library' => 1, 'theme-builder' => 1 ) ), 'site' => array( 'items' => array( 'apps' => 1 ) ) ) );
	check_guard( array( 'theme-builder' ) === array_keys( $finder['general']['items'] ) && array() === $finder['site']['items'], 'El buscador del editor no ofrece documentación, apps ni kits del proveedor.' );
	$rows = $g::plugin_row_meta( array( '<a href="https://elementor.com/">Learn More</a>', 'Versión 1' ), 'elementor-safe-mode.php' );
	check_guard( array( 'Versión 1' ) === $rows, 'El mu-plugin de modo seguro no enlaza al proveedor.' );

	// Valores por defecto: sin vídeos del proveedor (atómicos) ni en el widget Vídeo.
	$prop = new class() { public $d = array( '$$type' => 'string', 'value' => 'https://www.youtube.com/watch?v=XHOmBV4js_E' ); public function get_default() { return $this->d; } public function default( $v ) { $this->d = array( '$$type' => 'string', 'value' => $v ); return $this; } };
	$keep = new class() { public function get_default() { return array( '$$type' => 'string', 'value' => 'https://www.youtube.com/watch?v=propio' ); } public function default( $v ) { throw new RuntimeException( 'no debe cambiar' ); } };
	$g::atomic_defaults( array( 'source' => $prop, 'otro' => $keep ) );
	check_guard( '' === $prop->d['value'], 'El YouTube atómico no trae el vídeo del proveedor por defecto.' );
	$video = new class() { public $updates = array(); public function update_control( $id, $args ) { $this->updates[ $id ] = $args; } };
	$g::video_defaults( $video );
	check_guard( array( 'youtube_url', 'vimeo_url', 'dailymotion_url', 'videopress_url' ) === array_keys( $video->updates ) && '' === $video->updates['youtube_url']['default'], 'El widget Vídeo no trae vídeos del proveedor por defecto.' );

	// Cobertura: toda caché remota del núcleo está servida vacía (una versión nueva del núcleo que añada otra falla aquí).
	$core = __DIR__ . '/../digitalisimo-elements/elementor';
	$missing = array();
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $core, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		if ( 'php' !== $file->getExtension() || false !== strpos( $file->getPathname(), '/vendor/' ) ) {
			continue;
		}
		$code = file_get_contents( $file->getPathname() );
		if ( false === strpos( $code, 'ASSETS_DATA_TRANSIENT_KEY =>' ) ) {
			continue;
		}
		preg_match_all( "/ASSETS_DATA_TRANSIENT_KEY\s*=>\s*([^,\n]+)/", $code, $m );
		foreach ( $m[1] as $expr ) {
			$expr = trim( $expr );
			if ( preg_match( "/^'([^']+)'$/", $expr, $literal ) ) {
				$key = $literal[1];
			} elseif ( preg_match( '/^(?:self|static)::([A-Z_]+)$/', $expr, $const ) && preg_match( "/const\s+" . $const[1] . "\s*=\s*'([^']+)'/", $code, $value ) ) {
				$key = $value[1];
			} else {
				continue; // claves dinámicas (textos del onboarding por idioma, apagado): las cubre el escudo.
			}
			if ( ! in_array( $key, $g::REMOTE_CACHES, true ) ) {
				$missing[] = $key;
			}
		}
	}
	check_guard( array() === $missing, 'Cachés remotas del núcleo sin servir vacías: ' . implode( ', ', $missing ) );
	echo "DIGITALÍSIMO Elements: sin peticiones, cuentas, telemetría ni accesos del proveedor del núcleo incluido.\n";
}
