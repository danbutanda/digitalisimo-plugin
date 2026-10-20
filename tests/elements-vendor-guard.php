<?php
/**
 * DIGITALÍSIMO Elements sin rastro del proveedor del núcleo incluido: el escudo corta toda petición del constructor
 * hacia sus dominios (con respuestas válidas donde el núcleo las necesita), las cachés remotas se sirven vacías,
 * telemetría, IA y experimentos del proveedor quedan apagados, avisos y sugerencias se dan por vistos, y no quedan
 * rutas REST, redirecciones ni accesos de cuenta. Con el plugin Elementor original activo no se engancha nada.
 */
namespace Elementor {
	// Página de ajustes del núcleo: sus pestañas son privadas, como en includes/settings/settings-page.php.
	abstract class Settings_Page {
		private $tabs = array( 'general' => array( 'sections' => array( 'tools' => array( 'fields' => array( 'clear_cache' => array(), 'reset_api_data' => array() ) ) ) ), 'versions' => array() );
		public function tabs() { return $this->tabs; }
	}
	class Tools extends Settings_Page {}
	// Instancia del núcleo: App (con componentes), App común (plantillas) y administración.
	class Plugin { public static $instance; }
}
namespace Elementor\Core\Common {
	// Lista privada de plantillas, como en core/common/app.php (add_template() + print_templates()).
	class App {
		private $templates = array();
		public function add_template( $template ) { $this->templates[] = $template; }
		public function print_templates() { return implode( '', $this->templates ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __FILE__ ); // el «constructor» de la prueba es este directorio.
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	function check_guard( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['t'] = array( 'hooks' => array(), 'meta' => array(), 'options' => array() );
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['t']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['t']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function remove_filter() { return true; }
	function remove_action( $hook, $callback ) { $GLOBALS['t']['removed'][] = array( $hook, $callback ); return true; }
	// wp_scripts(): datos localizados por handle (get_data/add_data de WP_Dependencies).
	function wp_scripts() {
		static $scripts = null;
		if ( null === $scripts ) {
			$scripts = new class() {
				public $data = array();
				public function get_data( $handle, $key ) { return $this->data[ $handle ][ $key ] ?? false; }
				public function add_data( $handle, $key, $value ) { $this->data[ $handle ][ $key ] = $value; return true; }
			};
		}
		return $scripts;
	}
	// Objeto del núcleo con ajustes (Base_Object: get_settings/set_settings) y componentes (Base App).
	final class Digi_Fake_Settings {
		private $settings;
		private $components;
		public function __construct( array $settings, array $components = array() ) { $this->settings = $settings; $this->components = $components; }
		public function get_settings() { return $this->settings; }
		public function set_settings( $key, $value = null ) { if ( is_array( $key ) ) { $this->settings = $key; } else { $this->settings[ $key ] = $value; } }
		public function get_components() { return $this->components; }
	}
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
	function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
	function admin_url( $path = '' ) { return 'https://sitio.test/wp-admin/' . $path; }
	// Dirección del sitio en curso (la prueba la cambia para un sitio alojado en un subdominio del proveedor).
	function home_url( $path = '' ) { return ( $GLOBALS['t']['home'] ?? 'https://sitio.test' ) . $path; }
	function get_user_meta( $id, $key, $single ) { return $GLOBALS['t']['meta'][ $key ] ?? ''; }
	// Scripts encolados: handle → src, dependencias, pie y código en línea previo.
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	function wp_script_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? false : isset( $GLOBALS['t']['scripts'][ $handle ] ); }
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) { $GLOBALS['t']['scripts'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'footer' => $footer, 'before' => '' ); }
	function wp_add_inline_script( $handle, $js, $position = 'after' ) { $GLOBALS['t']['scripts'][ $handle ][ $position ] = $js; }
	function did_action( $hook ) { return 0; }
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
	// La de otro plugin (un enlace de afiliado enmascarado, en el frontal o donde sea) llega a su destino.
	$other = sys_get_temp_dir() . '/digi-otro-redirect-' . getmypid() . '.php';
	file_put_contents( $other, '<?php function digi_otro_plugin_redirect( $u ) { return Digitalisimo_Elements_Vendor_Free::redirect( $u ); }' );
	require $other;
	check_guard( 'https://be.elementor.com/visit/?bta=1' === digi_otro_plugin_redirect( 'https://be.elementor.com/visit/?bta=1' ), 'Sólo se desvía la redirección del constructor; la de otro plugin no cambia.' );
	check_guard( 'https://sitio.test/wp-admin/admin.php?page=elementor' === digi_otro_plugin_redirect( 'https://sitio.test/wp-admin/admin.php?page=elementor-app#onboarding' ), 'El asistente del núcleo nunca se abre, lo pida quien lo pida.' );
	unlink( $other );
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
	// Herramientas sin «Control de versiones» (revertir pediría a wordpress.org e instalaría el plugin original) ni «Sincronizar biblioteca».
	foreach ( array( 'elementor/admin/after_create_settings/elementor-tools', 'admin_post_elementor_rollback', 'wp_ajax_elementor_element_manager_get_admin_app_data' ) as $hook ) {
		check_guard( ! empty( $GLOBALS['t']['hooks'][ $hook ] ), 'Con el núcleo incluido se engancha ' . $hook . '.' );
	}
	check_guard( 0 === $GLOBALS['t']['hooks']['admin_post_elementor_rollback'][0][1], 'La guarda de revertir corre antes que el núcleo.' );
	$tools = new \Elementor\Tools();
	$g::tools_tabs( $tools );
	$tabs = $tools->tabs();
	check_guard( ! isset( $tabs['versions'] ) && ! isset( $tabs['general']['sections']['tools']['fields']['reset_api_data'] ) && isset( $tabs['general']['sections']['tools']['fields']['clear_cache'] ), 'Herramientas pierde sólo «Control de versiones» y «Sincronizar biblioteca».' );
	check_guard( array() === $g::no_versions(), 'La lista de versiones para revertir se sirve vacía, sin pedirla.' );
	// Administrador de elementos: fuera los enlaces de mejora del proveedor; el resto de la respuesta, intacto.
	$response = json_encode( array( 'success' => true, 'data' => array( 'promotion_data' => array( 'element_manager' => array( 'text' => 'Upgrade Now', 'url' => 'https://go.elementor.com/go-pro-element-manager/' ) ), 'widgets' => array( array( 'title' => 'https://elementor.com/en-mi-contenido' ) ) ) ) );
	$clean = json_decode( $g::strip_promotion_urls( $response ), true );
	check_guard( '' === $clean['data']['promotion_data']['element_manager']['url'] && 'Upgrade Now' === $clean['data']['promotion_data']['element_manager']['text'] && 'https://elementor.com/en-mi-contenido' === $clean['data']['widgets'][0]['title'], 'El Administrador de elementos no recibe enlaces de mejora y el resto no se toca.' );
	check_guard( 'no-json' === $g::strip_promotion_urls( 'no-json' ), 'Una respuesta que no es JSON pasa intacta.' );
	// Ganchos de los datos localizados, la App, las plantillas y el programa beta.
	$priority = static function ( $hook, $method ) {
		foreach ( $GLOBALS['t']['hooks'][ $hook ] ?? array() as $entry ) {
			if ( array( 'Digitalisimo_Elements_Vendor_Free', $method ) === $entry[0] ) {
				return $entry[1];
			}
		}
		return null;
	};
	check_guard( 99 === $priority( 'elementor/common/localize_settings', 'common_settings' ) && 99 === $priority( 'elementor/admin/localize_settings', 'admin_settings' ), 'La configuración común y la de la administración se limpian después de los módulos del núcleo.' );
	check_guard( 1 === $priority( 'elementor/common/after_register_scripts', 'app_config' ), 'La configuración de la App se limpia antes de que su cargador la imprima (prioridad 10).' );
	foreach ( array( 'elementor/editor/footer', 'admin_footer', 'wp_footer' ) as $hook ) {
		check_guard( 9 === $priority( $hook, 'templates' ), 'Las plantillas se limpian antes de imprimirse en ' . $hook . '.' );
	}
	check_guard( null !== $priority( 'pre_option_elementor_beta', 'no' ) && 20 === $priority( 'current_screen', 'no_beta_tester' ), 'Programa beta apagado y sin su ventana.' );

	// Asistente de configuración del núcleo (cuenta, planes y tema del proveedor): ni tras activar ni desde un enlace.
	foreach ( array( 'https://sitio.test/wp-admin/admin.php?page=elementor-app#onboarding', 'https://sitio.test/wp-admin/admin.php?page=elementor-app&ver=4.3.5#/onboarding/uploadAndInstallPro?mode=popup' ) as $url ) {
		check_guard( 'https://sitio.test/wp-admin/admin.php?page=elementor' === $g::redirect( $url ), 'El asistente del núcleo lleva al Inicio rápido propio: ' . $url );
	}
	foreach ( array( 'https://sitio.test/wp-admin/admin.php?page=elementor-app#/import', 'https://sitio.test/wp-admin/admin.php?page=elementor-apps#onboarding', 'https://sitio.test/onboarding/' ) as $url ) {
		check_guard( $url === $g::redirect( $url ), 'Las demás redirecciones no cambian: ' . $url );
	}
	$js = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/digitalisimo/vendor-free.js' );
	$php = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/digitalisimo-vendor-free.php' );
	check_guard( false !== strpos( $js, 'if ( host === window.location.hostname.toLowerCase() ) {' ), 'vendor-free.js nunca desactiva un enlace del propio sitio (aunque esté en un subdominio del proveedor).' );
	check_guard( false !== strpos( $js, '/^#\/?onboarding\b/' ) && false !== strpos( $js, 'window.location.replace( config.home )' ) && false !== strpos( $php, "'home'    => admin_url( 'admin.php?page=elementor' )" ), 'La guarda de la App lleva #/onboarding (y #onboarding) al Inicio rápido propio.' );

	// Los dos scripts propios: la guarda temprana en la cabecera, sin dependencias, y vendor-free.js en el pie, con la
	// misma configuración. La guarda nunca asigna a imágenes, vídeos ni marcos una dirección del proveedor.
	$GLOBALS['t']['scripts'] = array();
	$g::admin_script();
	$early = $GLOBALS['t']['scripts'][ $g::EARLY ] ?? null;
	$late  = $GLOBALS['t']['scripts'][ $g::SCRIPT ] ?? null;
	check_guard( $early && false === $early['footer'] && array() === $early['deps'] && false !== strpos( $early['src'], 'assets/digitalisimo/vendor-early.js' ) && $late && true === $late['footer'] && $early['before'] === $late['before'] && false !== strpos( $early['before'], '"hosts":["elementor.com"' ) && false !== strpos( $early['before'], '"context":"admin"' ), 'La guarda temprana va en la cabecera, sin dependencias, con la configuración de vendor-free.js.' );
	$g::admin_script();
	check_guard( 2 === count( $GLOBALS['t']['scripts'] ), 'Una segunda llamada no vuelve a encolar los scripts.' );
	$early_js = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/digitalisimo/vendor-early.js' );
	check_guard( false !== strpos( $early_js, 'Element.prototype.setAttribute = function' ) && false !== strpos( $early_js, "'HTMLImageElement'" ) && false !== strpos( $early_js, "'HTMLMediaElement'" ) && false !== strpos( $early_js, "/^(src|srcset|poster)$/i" ) && false !== strpos( $early_js, 'if ( host === window.location.hostname.toLowerCase() ) {' ) && false !== strpos( $early_js, "'data-digitalisimo-vendor', 'off'" ), 'vendor-early.js corta src, srcset y poster del proveedor (atributo y propiedad), nunca los del propio sitio.' );
	$GLOBALS['t']['scripts'] = array();

	// HTML del núcleo: un «Learn more» sale con su texto; un enlace dentro de una frase deja su texto.
	$markup = array(
		'Stretch the section to the full width of the page using JS. <a href="https://go.elementor.com/stretch-section/" target="_blank">Learn more</a>' => 'Stretch the section to the full width of the page using JS.',
		'Create layouts with <a target="_blank" href="https://go.elementor.com/wp-dash-flex-container/">Flexbox</a> and <a href="https://go.elementor.com/grid/">Grid</a> containers.' => 'Create layouts with Flexbox and Grid containers.',
		'Sin punto final <a href="https://go.elementor.com/x/">Learn more</a>' => 'Sin punto final',
		'Ver <a href="https://ejemplo.test/docs/">documentación propia</a>.' => 'Ver <a href="https://ejemplo.test/docs/">documentación propia</a>.',
		"var u = 'https://go.elementor.com/go-pro-cloud-templates-save-to-free-badge/';" => "var u = '';",
		'<a href="https://my.elementor.com/upgrade-subscription/?utm_source=x&#038;utm_medium=y">Upgrade</a>' => '',
		'Texto sin direcciones' => 'Texto sin direcciones',
	);
	foreach ( $markup as $in => $out ) {
		check_guard( $out === $g::strip_vendor_markup( $in ), 'Marcado del núcleo: «' . $in . '» → «' . $g::strip_vendor_markup( $in ) . '».' );
	}

	// Configuración común: planes y mejoras del proveedor fuera, lo demás igual.
	$common = $g::common_settings( array( 'library_connect' => array( 'subscription_plans' => array( 'essential' => array( 'label' => 'Pro', 'promotion_url' => 'https://my.elementor.com/upgrade-subscription/?utm_source=template-library&utm_medium=wp-dash&utm_campaign=gopro', 'color' => '#245eff' ) ), 'is_connected' => false ), 'urls' => array( 'rest' => 'https://sitio.test/wp-json/' ) ) );
	check_guard( '' === $common['library_connect']['subscription_plans']['essential']['promotion_url'] && 'Pro' === $common['library_connect']['subscription_plans']['essential']['label'] && 'https://sitio.test/wp-json/' === $common['urls']['rest'], 'La configuración común no lleva planes del proveedor (elementor-common-js-before).' );

	// Administración: los avisos de los experimentos sin su «Learn more» (elementor-admin-js-before).
	$admin = $g::admin_settings( array( 'experiments' => array( 'container' => array( 'title' => 'Container', 'messages' => array( 'on_deactivate' => 'Container-based content will be hidden from your site and may not be recoverable in all cases. <a target="_blank" href="https://go.elementor.com/wp-dash-deactivate-container/">Learn more</a>' ) ) ), 'home_url' => 'https://sitio.test' ) );
	check_guard( 'Container-based content will be hidden from your site and may not be recoverable in all cases.' === $admin['experiments']['container']['messages']['on_deactivate'] && 'https://sitio.test' === $admin['home_url'], 'Los avisos de experimentos llegan sin enlaces del proveedor.' );

	// Editor: fuera direcciones, «Learn more» y vídeos de ejemplo del proveedor; el contenido y los ajustes del usuario intactos.
	$editor = $g::editor_settings( array(
		'initial_document' => array(
			'elements' => array( array( 'id' => 'a1', 'settings' => array( 'link' => array( 'url' => 'https://elementor.com/mi-enlace/' ), 'youtube_url' => 'https://www.youtube.com/watch?v=XHOmBV4js_E', 'text' => 'Más en <a href="https://go.elementor.com/x/">su web</a>. <a href="https://elementor.com/y/">Learn more</a>' ) ) ),
			'settings' => array( 'controls' => array( 'x' => array( 'description' => 'Ayuda. <a href="https://go.elementor.com/page-settings/">Learn more</a>' ) ), 'settings' => array( 'custom_link' => 'https://go.elementor.com/de-la-pagina/' ) ),
			// Popup: sus disparadores y reglas viajan en displaySettings y el editor los reenvía al guardar.
			'displaySettings' => array(
				'timing'   => array( 'controls' => array( 'url' => array( 'description' => 'Regla. <a href="https://go.elementor.com/popup-url/">Learn more</a>' ) ), 'settings' => array( 'url' => 'yes', 'url_url' => 'https://be.elementor.com/visit/', 'url_action' => 'show' ) ),
				'triggers' => array( 'controls' => array(), 'settings' => array( 'page_load' => 'yes', 'nota' => 'Desde <a href="https://elementor.com/z/">aquí</a>' ) ),
			),
			'theme_builder' => array( 'settings' => array( 'conditions' => array( 'include/general', 'https://elementor.com/condicion/' ) ), 'help' => 'https://go.elementor.com/theme-builder/' ),
			'widgets'  => array( 'video' => array( 'controls' => array(
				'youtube_url' => array( 'default' => 'https://www.youtube.com/watch?v=XHOmBV4js_E' ),
				'vimeo_url'   => array( 'default' => 'https://vimeo.com/235215203' ),
				'preload'     => array( 'description' => 'Preload attribute lets you specify how the video should be loaded when the page loads. <a target="_blank" href="https://go.elementor.com/preload-video/">Learn more</a>' ),
				'propio'      => array( 'default' => 'https://www.youtube.com/watch?v=propio' ),
				'imagen'      => array( 'default' => 'https://sitio.test/wp-content/uploads/235215203.jpg' ),
			) ) ),
		),
		'settings' => array( 'editorPreferences' => array( 'settings' => array( 'favorito' => 'https://elementor.com/favorito/' ) ) ),
		'elements' => array( 'section' => array( 'controls' => array( 'background_video_link' => array( 'placeholder' => 'https://www.youtube.com/watch?v=XHOmBV4js_E' ) ) ) ),
		'controls' => array( 'url' => array( 'custom_attributes_description' => 'Separate key-value pairs with a comma. <a target="_blank" href="https://go.elementor.com/panel-link-custom-attributes/">Learn more</a>' ) ),
		'icons'    => array( 'goProURL' => 'https://go.elementor.com/go-pro-icon-library/' ),
	) );
	$doc = $editor['initial_document'];
	check_guard( 'https://elementor.com/mi-enlace/' === $doc['elements'][0]['settings']['link']['url'] && 'https://www.youtube.com/watch?v=XHOmBV4js_E' === $doc['elements'][0]['settings']['youtube_url'] && false !== strpos( $doc['elements'][0]['settings']['text'], 'https://elementor.com/y/' ), 'El contenido del usuario llega al editor tal cual (sus enlaces y vídeos son suyos).' );
	check_guard( 'https://go.elementor.com/de-la-pagina/' === $doc['settings']['settings']['custom_link'] && 'https://elementor.com/favorito/' === $editor['settings']['editorPreferences']['settings']['favorito'], 'Los ajustes de la página y las preferencias del usuario no se tocan.' );
	check_guard( 'Ayuda.' === $doc['settings']['controls']['x']['description'], 'Los controles de la página sí pierden su «Learn more».' );
	check_guard( 'https://be.elementor.com/visit/' === $doc['displaySettings']['timing']['settings']['url_url'] && false !== strpos( $doc['displaySettings']['triggers']['settings']['nota'], 'https://elementor.com/z/' ) && 'https://elementor.com/condicion/' === $doc['theme_builder']['settings']['conditions'][1], 'Las reglas y disparadores de un popup y las condiciones del Theme Builder no se vacían (se perderían al guardar).' );
	check_guard( 'Regla.' === $doc['displaySettings']['timing']['controls']['url']['description'] && '' === $doc['theme_builder']['help'], 'Los controles del popup y la ayuda del Theme Builder sí pierden lo del proveedor.' );
	$video = $doc['widgets']['video']['controls'];
	check_guard( '' === $video['youtube_url']['default'] && '' === $video['vimeo_url']['default'] && 'Preload attribute lets you specify how the video should be loaded when the page loads.' === $video['preload']['description'], 'El widget Vídeo llega al editor sin vídeos de ejemplo ni «Learn more» del proveedor.' );
	check_guard( 'https://www.youtube.com/watch?v=propio' === $video['propio']['default'] && 'https://sitio.test/wp-content/uploads/235215203.jpg' === $video['imagen']['default'], 'Sólo los vídeos de ejemplo del proveedor, y sólo en un host de vídeo.' );
	check_guard( '' === $editor['elements']['section']['controls']['background_video_link']['placeholder'] && 'Separate key-value pairs with a comma.' === $editor['controls']['url']['custom_attributes_description'] && '' === $editor['icons']['goProURL'], 'Fondos con vídeo, atributos de enlace y bibliotecas de iconos sin direcciones del proveedor (elementor-editor-js-before).' );

	// App: mejora del Theme Builder, planes de kits y asistente del núcleo fuera; el historial de kits del usuario intacto.
	$session    = array( 'kit_title' => 'Mi kit', 'kit_thumbnail' => 'https://assets.elementor.com/kits/mi-kit.png' );
	$customize  = new Digi_Fake_Settings( array( 'exportURL' => 'https://sitio.test/wp-admin/admin.php?page=elementor-app', 'importSessions' => array( $session ), 'lastImportedSession' => $session, 'help' => 'https://go.elementor.com/app-import-export/' ) );
	$app        = new Digi_Fake_Settings(
		array(
			'promotion'   => array( 'upgrade_url' => 'https://go.elementor.com/go-pro-theme-builder/' ),
			'menu_url'    => 'https://sitio.test/wp-admin/admin.php?page=elementor-app#/site-editor',
			'kit-library' => array( 'subscription_plans' => array( 'essential' => array( 'label' => 'Pro', 'promotion_url' => 'https://my.elementor.com/upgrade-subscription/?utm_source=kit-library&#038;utm_medium=wp-dash' ) ) ),
			'onboarding'  => array( 'urls' => array( 'sitePlanner' => 'https://planner.elementor.com/onboarding.html?type=editor&siteUrl=https://sitio.test', 'upgrade' => 'https://go.elementor.com/go-pro-onboarding-wizard-upgrade/', 'createNewPage' => 'https://sitio.test/wp-admin/edit.php?action=elementor_new_post' ) ),
		),
		array( 'import-export-customization' => $customize, 'site-editor' => new Digi_Fake_Settings( array() ) )
	);
	\Elementor\Plugin::$instance = (object) array( 'app' => $app );
	$g::app_config();
	$config = $app->get_settings();
	check_guard( '' === $config['promotion']['upgrade_url'] && '' === $config['kit-library']['subscription_plans']['essential']['promotion_url'] && 'Pro' === $config['kit-library']['subscription_plans']['essential']['label'], 'La App y su cargador no llevan la mejora del Theme Builder ni los planes de kits (elementor-app-loader-js-before).' );
	check_guard( '' === $config['onboarding']['urls']['sitePlanner'] && '' === $config['onboarding']['urls']['upgrade'] && 'https://sitio.test/wp-admin/edit.php?action=elementor_new_post' === $config['onboarding']['urls']['createNewPage'] && 'https://sitio.test/wp-admin/admin.php?page=elementor-app#/site-editor' === $config['menu_url'], 'El asistente del núcleo no lleva Site Planner ni planes; las direcciones propias siguen (elementor-app-js-before).' );
	$parts = $customize->get_settings();
	check_guard( '' === $parts['help'] && 'https://sitio.test/wp-admin/admin.php?page=elementor-app' === $parts['exportURL'] && array( $session ) === $parts['importSessions'] && $session === $parts['lastImportedSession'], 'Cada componente pierde las direcciones del proveedor; el historial de kits importados del usuario no se toca.' );

	// Datos localizados sin filtro propio (wp_localize_script): se reescriben con la misma forma (objetos y listas).
	$promotions = 'var elementorPromotionsData = ' . json_encode( array( 'animated-headline' => array( 'image' => '', 'title' => 'Bring Headlines to Life', 'description' => array( 'Uno', '</script><b>' ), 'upgrade_url' => 'https://go.elementor.com/go-pro-heading-widget-control/' ), 'vacio' => new stdClass(), 'lista' => array() ), JSON_HEX_TAG ) . ';';
	wp_scripts()->add_data( 'e-react-promotions', 'data', $promotions );
	wp_scripts()->add_data( 'editor-one-sidebar-navigation', 'data', 'var editorOneSidebarConfig = ' . json_encode( array( 'menuItems' => array( array( 'url' => 'https://sitio.test/wp-admin/admin.php?page=elementor' ) ), 'upgradeUrl' => 'https://go.elementor.com/go-pro-upgrade-wp-editor-inner-menu/', 'upgradeText' => 'Upgrade plan' ) ) . ';' );
	wp_scripts()->add_data( 'otro-plugin', 'data', 'var otro = {"url":"https:\/\/elementor.com\/x"};' );
	$g::localized_data();
	$clean = json_decode( substr( wp_scripts()->get_data( 'e-react-promotions', 'data' ), strlen( 'var elementorPromotionsData = ' ), -1 ) );
	check_guard( '' === $clean->{'animated-headline'}->upgrade_url && 'Bring Headlines to Life' === $clean->{'animated-headline'}->title && $clean->vacio instanceof stdClass && array() === $clean->lista && 0 === strpos( wp_scripts()->get_data( 'e-react-promotions', 'data' ), 'var elementorPromotionsData = {' ), 'Las promociones de los widgets Pro llegan sin sus mejoras (e-react-promotions-js-extra), con la misma forma.' );
	check_guard( false === stripos( wp_scripts()->get_data( 'e-react-promotions', 'data' ), '</script' ) && false !== strpos( wp_scripts()->get_data( 'e-react-promotions', 'data' ), '"\\u003C/script\\u003E\\u003Cb\\u003E"' ) && array( 'Uno', '</script><b>' ) === $clean->{'animated-headline'}->description, 'Los datos reescritos se codifican con JSON_HEX_TAG: nunca un «</script>» literal.' );
	$sidebar = json_decode( substr( wp_scripts()->get_data( 'editor-one-sidebar-navigation', 'data' ), strlen( 'var editorOneSidebarConfig = ' ), -1 ), true );
	check_guard( '' === $sidebar['upgradeUrl'] && 'https://sitio.test/wp-admin/admin.php?page=elementor' === $sidebar['menuItems'][0]['url'], 'La barra lateral no lleva la mejora del proveedor (editor-one-sidebar-navigation-js-extra).' );
	check_guard( 'var otro = {"url":"https:\/\/elementor.com\/x"};' === wp_scripts()->get_data( 'otro-plugin', 'data' ), 'Los datos de otros scripts no se tocan.' );

	// Plantillas: sólo las del núcleo de TEMPLATES, y sin enlaces ni direcciones del proveedor.
	$templates = new \Elementor\Core\Common\App();
	$templates->add_template( '<script type="text/template" id="tmpl-elementor-template-library-save-template">'
		. "<# const goLink = ok ? 'https://go.elementor.com/go-pro-cloud-templates-save-to-100-usage-badge' : 'https://go.elementor.com/go-pro-cloud-templates-save-to-free-badge/'; #>"
		. '<span class="upgrade-badge"><a href="{{{ goLink }}}" target="_blank">Upgrade</a></span>'
		. '<div class="elementor-template-library-blank-footer">Learn more about the <a class="elementor-template-library-blank-footer-link" href="https://go.elementor.com/docs-library/" target="_blank">Template Library</a></div></script>'
		. '<script type="text/template" id="tmpl-elementor-panel-global"><div class="elementor-nerd-box-message">All areas will be editable from one single place.</div>'
		. "\n\t\t" . '<a class="elementor-button go-pro" target="_blank" href="https://go.elementor.com/pro-global/">Upgrade Now</a></div></script>'
		. '<script id="tmpl-elementor-global-styles-dialog" type="text/template">Add the template’s classes and variables to my site. <a href="https://elementor.com/help/variables-manager/" target="_blank" rel="noopener noreferrer">Learn more</a></script>'
		. '<script type="text/template" id="tmpl-de-otro-plugin"><a href="https://elementor.com/x/">Enlace de otro plugin</a></script>' );
	$GLOBALS['t']['removed'] = array();
	\Elementor\Plugin::$instance = (object) array( 'common' => $templates, 'admin' => $admin_object = new stdClass() );
	$g::templates();
	$printed = $templates->print_templates();
	check_guard( false === strpos( $printed, 'go.elementor.com' ) && false === strpos( $printed, 'variables-manager' ) && false !== strpos( $printed, "const goLink = ok ? '' : '';" ) && false !== strpos( $printed, 'Learn more about the Template Library</div>' ) && false === strpos( $printed, 'Upgrade Now' ) && false !== strpos( $printed, 'to my site.</script>' ), 'Las plantillas del núcleo se imprimen sin enlaces, insignias ni direcciones del proveedor.' );
	check_guard( false !== strpos( $printed, '<a href="https://elementor.com/x/">Enlace de otro plugin</a>' ), 'Una plantilla que no es del núcleo no se toca.' );
	$g::no_beta_tester();
	check_guard( array( array( 'admin_head', array( $admin_object, 'add_beta_tester_template' ) ), array( 'admin_enqueue_scripts', array( $admin_object, 'enqueue_beta_tester_scripts' ) ) ) === $GLOBALS['t']['removed'], 'La ventana del programa beta (boletín del proveedor) no se engancha.' );
	\Elementor\Plugin::$instance = null;
	$g::app_config();
	$g::templates();
	$g::no_beta_tester();

	// Un sitio alojado en un subdominio del proveedor: sus direcciones son del usuario (enlaces, redirecciones, datos).
	$GLOBALS['t']['home'] = 'https://mi-sitio.elementor.cloud';
	check_guard( ! $g::vendor_url( 'https://mi-sitio.elementor.cloud/wp-admin/admin.php?page=elementor' ) && $g::vendor_url( 'https://go.elementor.com/x/' ) && $g::vendor_url( 'https://otro.elementor.cloud/' ), 'El host del propio sitio nunca es del proveedor; los demás sí.' );
	check_guard( 'https://mi-sitio.elementor.cloud/wp-admin/edit.php' === $g::redirect( 'https://mi-sitio.elementor.cloud/wp-admin/edit.php' ), 'Una redirección dentro del propio sitio no cambia.' );
	$own = $g::common_settings( array( 'urls' => array( 'rest' => 'https://mi-sitio.elementor.cloud/wp-json/' ), 'plan' => 'https://my.elementor.com/upgrade-subscription/' ) );
	check_guard( 'https://mi-sitio.elementor.cloud/wp-json/' === $own['urls']['rest'] && '' === $own['plan'], 'Las direcciones del propio sitio se conservan en la configuración.' );
	unset( $GLOBALS['t']['home'] );

	// Cobertura: toda plantilla del núcleo con una dirección del proveedor está en TEMPLATES, y toda entrada de TEMPLATES
	// existe en el núcleo (una versión nueva que añada o quite una falla aquí).
	$uncovered = array();
	$present   = array();
	$sources   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $core, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $sources as $file ) {
		$name = $file->getPathname();
		if ( 'php' !== $file->getExtension() || false !== strpos( $name, '/vendor/' ) || false !== strpos( $name, '/languages/' ) ) {
			continue;
		}
		$code = file_get_contents( $name );
		if ( false === strpos( $code, 'text/template' ) ) {
			continue;
		}
		preg_match_all( '#<script\b([^>]*)>(.*?)</script>#is', $code, $blocks, PREG_SET_ORDER );
		foreach ( $blocks as $block ) {
			if ( ! preg_match( '#type\s*=\s*"text/template"#', $block[1] ) || ! preg_match( '#\bid\s*=\s*"([^"]+)"#', $block[1], $id ) ) {
				continue;
			}
			$present[ $id[1] ] = true;
			preg_match_all( '#(?:https?:)?//[^\s"\'<>(){}\\\\]+#i', $block[2], $urls );
			foreach ( $urls[0] as $url ) {
				if ( $g::vendor_link( $url ) && ! in_array( $id[1], $g::TEMPLATES, true ) ) {
					$uncovered[] = $id[1] . ' (' . $url . ')';
				}
			}
		}
	}
	check_guard( array() === $uncovered, 'Plantillas del núcleo con direcciones del proveedor sin limpiar: ' . implode( ', ', array_unique( $uncovered ) ) );
	check_guard( array() === array_diff( $g::TEMPLATES, array_keys( $present ) ), 'Plantillas de TEMPLATES que el núcleo ya no tiene: ' . implode( ', ', array_diff( $g::TEMPLATES, array_keys( $present ) ) ) );
	echo "DIGITALÍSIMO Elements: sin peticiones, cuentas, telemetría, accesos ni direcciones latentes del proveedor del núcleo incluido.\n";
}
