<?php
/**
 * Librerías del widget Resaltado de código sin CDN y cargador diferido (frente D de F-front).
 *
 * - `assets/lib/prism/MANIFEST.json`: Prism 1.23.0 y clipboard.js 2.0.0 con la `integrity` fijada en
 *   `scripts/sync-prism.mjs`, el sha256 y el tamaño de cada archivo, nada más en la carpeta, sus LICENSE y la
 *   comparación única con cdnjs.
 * - `register_frontend_scripts()` registra los siete handles de antes, locales, con su versión, en el pie y con sus
 *   dependencias, más clipboard.js antes de copy-to-clipboard; ninguna otra URL remota en lo que se sirve.
 * - Cada valor del control `language` tiene su gramática y el cierre de sus `require`, comprobado con el mapa que
 *   lleva el autoloader distribuido (el que decide qué pide el navegador).
 * - Ninguna URL `cdnjs.cloudflare.com` en el PHP de Elements fuera de `elementor/`; el editor de Código personalizado
 *   encola el `jshint` de WordPress sin URL remota ni filtro de integridad.
 * - `get_script_depends()` de Lottie y Código por modo (apagado, front, instancia de registro, editor, vista previa,
 *   vista previa de WordPress, guardado, handle sin registrar) y el registro del cargador con `defer` y su
 *   configuración en `before`.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ELEMENTOR_PRO_URL', 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', '/srv/wp-content/plugins/digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.163' );

	final class LL_Scripts {
		public $registered      = array();
		public $base_url        = 'https://sitio.test';
		public $content_url     = 'https://sitio.test/wp-content';
		public $default_version = '7.1.3';
	}

	$GLOBALS['ll_scripts'] = new LL_Scripts();
	$GLOBALS['wp_query']   = new stdClass();
	$GLOBALS['ll']         = array();

	function ll_reset( array $context = array() ) {
		$GLOBALS['ll_scripts'] = new LL_Scripts();
		$GLOBALS['ll']         = $context + array( 'admin' => false, 'ajax' => false, 'preview' => false, 'customize' => false, 'enqueued' => array(), 'enqueue_args' => array(), 'inline' => array(), 'actions' => array(), 'filters' => array(), 'deregistered' => array(), 'src_filter' => null, 'did' => array( 'wp_enqueue_scripts' => 1 ) );
		unset( $_GET['elementor-preview'] );
	}
	ll_reset();

	function wp_scripts() { return $GLOBALS['ll_scripts']; }
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) {
		$GLOBALS['ll_scripts']->registered[ $handle ] = (object) array( 'handle' => $handle, 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args );
		return true;
	}
	function wp_script_is( $handle, $status = 'enqueued' ) {
		return 'registered' === $status ? isset( $GLOBALS['ll_scripts']->registered[ $handle ] ) : in_array( $handle, $GLOBALS['ll']['enqueued'], true );
	}
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['ll']['inline'][] = array( 'handle' => $handle, 'data' => $data, 'position' => $position );
		return true;
	}
	function wp_enqueue_script( $handle, ...$args ) {
		$GLOBALS['ll']['enqueued'][]     = $handle;
		$GLOBALS['ll']['enqueue_args'][] = array( $handle, $args );
	}
	function wp_deregister_script( $handle ) { $GLOBALS['ll']['deregistered'][] = $handle; }
	function did_action( $hook ) { return $GLOBALS['ll']['did'][ $hook ] ?? 0; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['ll']['actions'][] = array( $hook, $callback ); }
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['ll']['filters'][] = array( $hook, $callback ); }
	function apply_filters( $hook, $value, ...$args ) {
		return 'script_loader_src' === $hook && $GLOBALS['ll']['src_filter'] ? ( $GLOBALS['ll']['src_filter'] )( $value, ...$args ) : $value;
	}
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/' . ltrim( $path, '/' ); }
	function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
	function esc_url_raw( $url ) { return (string) $url; }
	function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
	function is_admin() { return $GLOBALS['ll']['admin']; }
	function wp_doing_ajax() { return $GLOBALS['ll']['ajax']; }
	function wp_doing_cron() { return false; }
	function is_customize_preview() { return $GLOBALS['ll']['customize']; }
	function is_preview() { return $GLOBALS['ll']['preview']; }
	function wp_enqueue_code_editor( $settings ) { $GLOBALS['ll']['code_editor'] = $settings; return array(); }
	function wp_get_code_editor_settings( $settings ) { return array(); }

	function check_local( $ok, $message ) {
		if ( ! $ok ) {
			throw new \RuntimeException( $message );
		}
	}
}

namespace Elementor {
	class Plugin {
		public static $instance;
		public $preview;
		public $editor;
	}
	class LL_Preview {
		public $on = false;
		public function is_preview_mode( $post_id = 0 ) { return $this->on; }
	}
	class LL_Editor {
		public $on = false;
		public function is_edit_mode( $post_id = null ) { return $this->on; }
	}
}

namespace ElementorPro {
	class Plugin {
		public static function elementor() { return \Elementor\Plugin::$instance; }
	}
}

namespace ElementorPro\Base {
	class Module_Base {
		public function __construct() {}
	}
	class Base_Widget {
		public $type_instance = false;
		public $settings      = array();
		public function is_type_instance() { return $this->type_instance; }
		public function get_settings_for_display( $key = null ) { return $this->settings; }
	}
}

namespace ElementorPro\Modules\AssetsManager\Classes {
	class Assets_Base {
		public function __construct() {}
	}
}

namespace Digitalisimo\Elements\Front {
	/** Doble de la transición (frente B): sólo `applied()`, que es lo único que lee el cargador. */
	final class Transition {
		public static $applied = array();
		public static function applied(): array { return self::$applied; }
	}
}

namespace {
	use Digitalisimo\Elements\Front\Lazy_Libs;
	use Digitalisimo\Elements\Front\Transition;

	$root   = realpath( __DIR__ . '/../digitalisimo-elements' );
	$lib    = $root . '/assets/lib/prism';
	$sync   = file_get_contents( __DIR__ . '/../scripts/sync-prism.mjs' );
	$module = $root . '/modules/code-highlight/module.php';
	$widget = $root . '/modules/code-highlight/widgets/code-highlight.php';
	require $module;
	require $widget;
	require $root . '/modules/lottie/widgets/lottie.php';
	require $root . '/modules/custom-code/custom-code-metabox.php';
	require $root . '/modules/digitalisimo-front/class-lazy-libs.php';

	// 1. Manifiesto: versiones, integridad fijada en el script de sincronización, sumas de cada archivo y nada más.
	$manifest = json_decode( (string) file_get_contents( $lib . '/MANIFEST.json' ), true );
	check_local( is_array( $manifest ), 'assets/lib/prism/MANIFEST.json es JSON válido.' );
	check_local( 'prismjs' === $manifest['package'] && '1.23.0' === $manifest['version'] && 'MIT' === $manifest['license'], 'El manifiesto describe prismjs 1.23.0 (MIT).' );
	preg_match_all( "/integrity: '(sha512-[A-Za-z0-9+\/=]+)'/", $sync, $pinned );
	check_local( 2 === count( $pinned[1] ), 'scripts/sync-prism.mjs fija la integridad de sus dos paquetes.' );
	check_local( $manifest['integrity'] === $pinned[1][0] && 'https://registry.npmjs.org/prismjs/-/prismjs-1.23.0.tgz' === $manifest['source'], 'La integridad y el origen de Prism son los fijados en el script.' );
	$clip = $manifest['companions']['clipboard'] ?? array();
	check_local( 'clipboard' === ( $clip['package'] ?? '' ) && '2.0.0' === ( $clip['version'] ?? '' ) && 'MIT' === ( $clip['license'] ?? '' ) && $pinned[1][1] === ( $clip['integrity'] ?? '' ), 'clipboard.js 2.0.0 (MIT) con la integridad fijada en el script.' );
	check_local( strpos( $sync, "version: '1.23.0'" ) && strpos( $sync, "version: '2.0.0'" ), 'El script fija Prism 1.23.0 y clipboard.js 2.0.0.' );
	$on_disk = array();
	$walker  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $lib, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $walker as $item ) {
		$on_disk[] = substr( $item->getPathname(), strlen( $lib ) + 1 );
	}
	sort( $on_disk );
	$listed = array_keys( $manifest['files'] );
	$expected_disk = array_merge( $listed, array( 'MANIFEST.json' ) );
	sort( $expected_disk );
	check_local( $on_disk === $expected_disk, 'En assets/lib/prism sólo están los archivos del manifiesto: ' . implode( ', ', array_diff( $on_disk, $expected_disk ) ) );
	foreach ( $manifest['files'] as $path => $sum ) {
		$content = file_get_contents( $lib . '/' . $path );
		check_local( hash( 'sha256', $content ) === $sum['sha256'] && strlen( $content ) === $sum['bytes'], "sha256 y tamaño de {$path} como en el manifiesto." );
		check_local( 'LICENSE' === basename( $path ) || preg_match( '/\.min\.js$/', $path ), "Sólo .min.js y LICENSE: {$path}" );
	}
	$license = (string) file_get_contents( $lib . '/LICENSE' );
	check_local( false !== strpos( $license, 'MIT' ) && false !== strpos( $license, 'Lea Verou' ), 'LICENSE de Prism (MIT, Lea Verou).' );
	$clip_license = (string) file_get_contents( $lib . '/clipboard/LICENSE' );
	check_local( false !== strpos( $clip_license, 'MIT License' ) && false !== strpos( $clip_license, 'Zeno Rocha' ), 'LICENSE de clipboard.js (MIT, Zeno Rocha).' );
	check_local( false !== strpos( (string) file_get_contents( $lib . '/clipboard/clipboard.min.js' ), 'Licensed MIT © Zeno Rocha' ), 'clipboard.min.js conserva su aviso.' );
	$js_files = array_values( array_filter( $listed, function ( $path ) { return 'LICENSE' !== basename( $path ); } ) );
	$cdn = $manifest['cdnjs'] ?? array();
	check_local( isset( $cdn['checked'], $cdn['files'], $cdn['identical'] ) && count( $js_files ) === $cdn['files'] && $cdn['files'] === $cdn['identical'], 'La comparación única con las sumas de cdnjs cubre todos los scripts y salió idéntica.' );
	// La misma guarda que el empaquetado: ningún archivo servido nombra Element Pack ni sus prefijos.
	foreach ( $listed as $path ) {
		check_local( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', (string) file_get_contents( $lib . '/' . $path ) ), "{$path} no dispara la guarda de build-packages.sh." );
	}

	// 2. Los handles del widget, locales y con su versión, pie y dependencias.
	$registrar = ( new ReflectionClass( 'ElementorPro\Modules\CodeHighlight\Module' ) )->newInstanceWithoutConstructor();
	$registrar->register_frontend_scripts();
	$expected = array(
		'prismjs_core'              => array( 'components/prism-core.min.js', array(), '1.23.0' ),
		'prismjs_loader'            => array( 'plugins/autoloader/prism-autoloader.min.js', array( 'prismjs_core' ), '1.23.0' ),
		'prismjs_normalize'         => array( 'plugins/normalize-whitespace/prism-normalize-whitespace.min.js', array( 'prismjs_core' ), '1.23.0' ),
		'prismjs_line_numbers'      => array( 'plugins/line-numbers/prism-line-numbers.min.js', array( 'prismjs_normalize' ), '1.23.0' ),
		'prismjs_line_highlight'    => array( 'plugins/line-highlight/prism-line-highlight.min.js', array( 'prismjs_normalize' ), '1.23.0' ),
		'prismjs_toolbar'           => array( 'plugins/toolbar/prism-toolbar.min.js', array( 'prismjs_normalize' ), '1.23.0' ),
		'prismjs_clipboard'         => array( 'clipboard/clipboard.min.js', array(), '2.0.0' ),
		'prismjs_copy_to_clipboard' => array( 'plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js', array( 'prismjs_toolbar', 'prismjs_clipboard' ), '1.23.0' ),
	);
	$registered = wp_scripts()->registered;
	check_local( array_keys( $registered ) === array_keys( $expected ), 'register_frontend_scripts() registra los siete handles de Prism y clipboard.js: ' . implode( ', ', array_keys( $registered ) ) );
	foreach ( $expected as $handle => list( $path, $deps, $version ) ) {
		$item = $registered[ $handle ];
		check_local( ELEMENTOR_PRO_URL . 'assets/lib/prism/' . $path === $item->src, "{$handle} se sirve desde el plugin: {$item->src}" );
		check_local( $deps === $item->deps && $version === $item->ver && true === $item->args, "{$handle}: dependencias, versión {$version} y pie." );
		check_local( isset( $manifest['files'][ $path ] ) && is_file( $lib . '/' . $path ) && ( $manifest['handles'][ $handle ] ?? '' ) === $path, "{$handle} apunta a un archivo del manifiesto." );
	}
	check_local( count( $manifest['handles'] ) === count( $expected ), 'El manifiesto lista los mismos handles que module.php.' );
	$source = (string) file_get_contents( $module );
	check_local( false === strpos( $source, 'https://' ) && false === strpos( $source, 'http://' ), 'module.php no nombra ninguna URL remota.' );
	// La única URL remota de lo servido es la de clipboard.js en copy-to-clipboard, que ya no se pide: clipboard.js va
	// antes como dependencia y define window.ClipboardJS.
	foreach ( $listed as $path ) {
		preg_match_all( '#https?://[^"\'\s)]+#', (string) file_get_contents( $lib . '/' . $path ), $urls );
		$remote = array_values( array_filter( $urls[0], function ( $url ) { return 0 !== strpos( $url, 'https://zenorocha.github.io/clipboard.js' ); } ) );
		$allowed = 'plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js' === $path ? array( 'https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.0/clipboard.min.js' ) : array();
		check_local( $remote === $allowed, "{$path} no pide nada fuera del plugin: " . implode( ', ', $remote ) );
	}
	check_local( false !== strpos( (string) file_get_contents( $lib . '/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js' ), 'window.ClipboardJS' ), 'copy-to-clipboard usa window.ClipboardJS si existe.' );

	// 3. Cada valor del control `language`: gramática y cierre de `require` según el autoloader distribuido.
	preg_match( '/\$language_option\s*=\s*\[(.*?)\];/s', (string) file_get_contents( $widget ), $control );
	preg_match_all( "/'([^']+)'\s*=>/", $control[1], $values );
	check_local( 37 === count( $values[1] ), 'El control language ofrece 37 valores (' . count( $values[1] ) . ').' );
	check_local( array_keys( $manifest['languages'] ) === $values[1], 'El manifiesto cubre exactamente los valores del control.' );
	$autoloader = (string) file_get_contents( $lib . '/plugins/autoloader/prism-autoloader.min.js' );
	$literal    = function ( $anchor ) use ( $autoloader ) {
		$start = strpos( $autoloader, $anchor );
		check_local( false !== $start, "El autoloader trae el mapa que empieza por {$anchor}." );
		$depth = 0;
		$quote = false;
		for ( $i = $start, $n = strlen( $autoloader ); $i < $n; $i++ ) {
			$char = $autoloader[ $i ];
			if ( $quote ) {
				if ( '\\' === $char ) {
					$i++;
				} elseif ( '"' === $char ) {
					$quote = false;
				}
				continue;
			}
			if ( '"' === $char ) {
				$quote = true;
			} elseif ( '{' === $char ) {
				$depth++;
			} elseif ( '}' === $char && 0 === --$depth ) {
				$map = json_decode( preg_replace( '/([{,])([A-Za-z_$][\w$]*):/', '$1"$2":', substr( $autoloader, $start, $i - $start + 1 ) ), true );
				check_local( is_array( $map ) && count( $map ) > 20, "El mapa {$anchor} del autoloader se lee como datos." );
				return $map;
			}
		}
		return array();
	};
	$dependencies = $literal( '{javascript:"clike"' );
	$aliases      = $literal( '{html:"markup"' );
	$closure      = function ( $lang, array &$out ) use ( &$closure, $dependencies ) {
		if ( in_array( $lang, $out, true ) ) {
			return;
		}
		foreach ( (array) ( $dependencies[ $lang ] ?? array() ) as $dep ) {
			$closure( $dep, $out );
		}
		$out[] = $lang;
	};
	foreach ( $values[1] as $value ) {
		check_local( (bool) preg_match( '/^[\w-]+/', $value, $name ), "Valor con nombre de gramática: {$value}" );
		$lang = strtolower( $name[0] );
		$need = array();
		$closure( $aliases[ $lang ] ?? $lang, $need );
		$have = $manifest['languages'][ $value ];
		sort( $need );
		sort( $have );
		check_local( $need === $have, "«{$value}» trae su gramática y el cierre de sus require: " . implode( ', ', $need ) );
		foreach ( $need as $grammar ) {
			check_local( isset( $manifest['files'][ "components/prism-{$grammar}.min.js" ] ) && is_file( "{$lib}/components/prism-{$grammar}.min.js" ), "Gramática {$grammar} servida desde el plugin." );
		}
	}
	check_local( array( 'markup' ) === $manifest['languages']['html'] && array( 'markup' ) === $manifest['languages']['xml'] && array( 'markup' ) === $manifest['languages']['svg'], 'html, xml y svg son alias de markup.' );
	check_local( in_array( 'visual-basic', $manifest['languages']['visual-basic,'], true ), '«visual-basic,» (con su coma de origen) resalta como visual-basic.' );
	check_local( array() === $manifest['excluded'], 'Ninguna gramática excluida por la guarda del empaquetado.' );

	// 4. Ninguna URL de cdnjs en el PHP de Elements fuera del núcleo incluido.
	$php = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		function ( $item ) use ( $root ) { return $root . '/elementor' !== $item->getPathname(); }
	) );
	$scanned = 0;
	foreach ( $php as $item ) {
		if ( 'php' !== $item->getExtension() ) {
			continue;
		}
		$scanned++;
		check_local( false === stripos( (string) file_get_contents( $item->getPathname() ), 'cdnjs.cloudflare.com' ), 'Sin cdnjs.cloudflare.com en ' . substr( $item->getPathname(), strlen( $root ) + 1 ) );
	}
	check_local( $scanned > 100, "Se revisó el PHP de Elements ({$scanned} archivos)." );

	// 5. Editor de Código personalizado: el jshint de WordPress, sin URL remota ni integridad.
	$metabox_source = (string) file_get_contents( $root . '/modules/custom-code/custom-code-metabox.php' );
	check_local( ! method_exists( 'ElementorPro\Modules\CustomCode\Custom_Code_Metabox', 'add_script_integrity' ), 'add_script_integrity() se retiró.' );
	check_local( false === strpos( $metabox_source, 'integrity' ) && false === strpos( $metabox_source, 'jshint.min.js' ) && false === strpos( $metabox_source, 'wp_deregister_script' ), 'El metabox no reemplaza jshint ni añade integridad.' );
	ll_reset( array( 'admin' => true ) );
	$metabox = ( new ReflectionClass( 'ElementorPro\Modules\CustomCode\Custom_Code_Metabox' ) )->newInstanceWithoutConstructor();
	$enqueue = new ReflectionMethod( $metabox, 'enqueue_code_editor_scripts' );
	$enqueue->invoke( $metabox, 'codigo' );
	check_local( array( 'htmlhint', 'csslint', 'jshint' ) === $GLOBALS['ll']['enqueued'], 'Se encolan htmlhint, csslint y el jshint registrado por WordPress: ' . implode( ', ', $GLOBALS['ll']['enqueued'] ) );
	foreach ( $GLOBALS['ll']['enqueue_args'] as $call ) {
		check_local( array() === $call[1], "{$call[0]} se encola por su handle, sin URL." );
	}
	check_local( array() === $GLOBALS['ll']['deregistered'] && array() === $GLOBALS['ll']['filters'], 'Sin wp_deregister_script ni filtro de etiqueta.' );
	check_local( isset( $GLOBALS['ll']['code_editor'] ), 'El editor de código de WordPress se inicia como antes.' );

	// 6. Cargador: registro con defer y configuración en `before`, sólo con lazy_libs aplicado.
	$prism_registry = function () use ( $registrar ) {
		$registrar->register_frontend_scripts();
		wp_register_script( 'lottie', ELEMENTOR_PRO_URL . 'assets/lib/lottie/lottie.min.js', array( 'jquery' ), '5.6.6', true );
	};
	$on = array( 'pro_conditional' => true, 'font_swap' => true, 'svg_icons' => false, 'lazy_libs' => true, 'element_cache_off' => false, 'image_loading_off' => false, 'icons' => 'n/a', 'hash' => 'x', 'time' => 1 );
	$off = array( 'lazy_libs' => false ) + $on;
	check_local( is_file( $root . '/' . Lazy_Libs::SCRIPT ), 'El script del cargador existe.' );
	check_local( 'digitalisimo-elements-lazy-libs' === Lazy_Libs::HANDLE, 'Handle del contrato §7.2.' );
	Lazy_Libs::init();
	check_local( array( array( 'elementor/frontend/after_register_scripts', array( Lazy_Libs::class, 'register' ) ) ) === $GLOBALS['ll']['actions'], 'init() sólo engancha el registro tras los scripts del front.' );
	foreach ( array( array(), $off ) as $applied ) {
		ll_reset();
		Transition::$applied = $applied;
		$prism_registry();
		Lazy_Libs::register();
		check_local( ! wp_script_is( Lazy_Libs::HANDLE, 'registered' ) && array() === $GLOBALS['ll']['inline'], 'Sin lazy_libs aplicado, el cargador no se registra.' );
		check_local( ! Lazy_Libs::active(), 'Sin lazy_libs aplicado, active() es falso.' );
	}
	ll_reset();
	Transition::$applied = $on;
	$prism_registry();
	$GLOBALS['ll']['src_filter'] = function ( $src, $handle ) { return 'prismjs_core' === $handle ? $src . '&cdn=propio' : $src; };
	Lazy_Libs::register();
	$loader = wp_scripts()->registered[ Lazy_Libs::HANDLE ] ?? null;
	check_local( null !== $loader, 'Con lazy_libs aplicado, el cargador se registra.' );
	check_local( 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . Lazy_Libs::SCRIPT === $loader->src && array( 'elementor-frontend' ) === $loader->deps && DIGITALISIMO_ELEMENTS_VERSION === $loader->ver, 'Desde el plugin, con dependencia elementor-frontend y la versión de Elements.' );
	check_local( array( 'in_footer' => true, 'strategy' => 'defer' ) === $loader->args, 'El cargador se registra con defer y en el pie.' );
	check_local( 1 === count( $GLOBALS['ll']['inline'] ) && Lazy_Libs::HANDLE === $GLOBALS['ll']['inline'][0]['handle'] && 'before' === $GLOBALS['ll']['inline'][0]['position'], 'La configuración va en before de su propio handle (no afecta a su defer).' );
	check_local( (bool) preg_match( '/^window\.digitalisimoLazyLibs=(\{.*\});$/s', $GLOBALS['ll']['inline'][0]['data'], $json ), 'La configuración es window.digitalisimoLazyLibs.' );
	check_local( false === strpos( $json[1], '</' ), 'La configuración no puede cerrar la etiqueta script.' );
	$config = json_decode( $json[1], true );
	check_local( array( 'src' => ELEMENTOR_PRO_URL . 'assets/lib/lottie/lottie.min.js?ver=5.6.6', 'ver' => '5.6.6' ) === $config['lottie'], 'Lottie: URL y versión leídas de su registro.' );
	check_local( Lazy_Libs::PRISM === array_column( $config['prism'], 'handle' ), 'Prism: los ocho handles en orden de ejecución.' );
	foreach ( $config['prism'] as $entry ) {
		$item = wp_scripts()->registered[ $entry['handle'] ];
		$want = $item->src . '?ver=' . $item->ver . ( 'prismjs_core' === $entry['handle'] ? '&cdn=propio' : '' );
		check_local( $want === $entry['src'] && $item->ver === $entry['ver'], "{$entry['handle']}: la URL que imprimiría WordPress, con script_loader_src." );
	}
	// El script pide Prism por posición: 0-2 siempre, 3 números de línea, 4 líneas resaltadas, 5-7 copiar.
	$script = (string) file_get_contents( $root . '/' . Lazy_Libs::SCRIPT );
	check_local( false !== strpos( $script, 'l=[0,1,2]' ) && false !== strpos( $script, "has('pre.line-numbers')&&l.push(3)" ) && false !== strpos( $script, 'has(\'pre[data-line]:not([data-line=""])\')&&l.push(4)' ) && false !== strpos( $script, "has('.copy-to-clipboard')&&l.push(5,6,7)" ), 'Las posiciones del script son las de Lazy_Libs::PRISM.' );
	check_local( array( 'prismjs_core', 'prismjs_loader', 'prismjs_normalize' ) === array_slice( Lazy_Libs::PRISM, 0, 3 ) && 'prismjs_line_numbers' === Lazy_Libs::PRISM[3] && 'prismjs_line_highlight' === Lazy_Libs::PRISM[4] && array( 'prismjs_toolbar', 'prismjs_clipboard', 'prismjs_copy_to_clipboard' ) === array_slice( Lazy_Libs::PRISM, 5 ), 'Orden de Lazy_Libs::PRISM.' );
	check_local( array_keys( $expected ) === Lazy_Libs::PRISM, 'El cargador conoce todos los handles que registra module.php.' );
	// Un registro ausente: el cargador no se registra y los widgets vuelven a sus librerías.
	ll_reset();
	$prism_registry();
	unset( wp_scripts()->registered['prismjs_clipboard'] );
	Lazy_Libs::register();
	$alias = wp_scripts()->registered[ Lazy_Libs::HANDLE ] ?? null;
	check_local( null !== $alias && false === $alias->src && array() === $GLOBALS['ll']['inline'], 'Si falta un handle de Prism, el cargador no se registra: queda su alias sin script.' );
	check_local( array_values( array_diff( array_merge( array( 'lottie' ), Lazy_Libs::PRISM ), array( 'prismjs_clipboard' ) ) ) === $alias->deps, 'El alias depende de las librerías que existen, en orden.' );
	check_local( ! Lazy_Libs::active(), 'Con el alias, active() es falso.' );
	ll_reset();
	$prism_registry();
	unset( wp_scripts()->registered['lottie'] );
	Lazy_Libs::register();
	$alias = wp_scripts()->registered[ Lazy_Libs::HANDLE ] ?? null;
	check_local( null !== $alias && false === $alias->src && Lazy_Libs::PRISM === $alias->deps, 'Si falta lottie, el cargador no se registra: el alias trae Prism.' );

	// 7. get_script_depends() de Lottie y Código por modo.
	$code = ( new ReflectionClass( 'ElementorPro\Modules\CodeHighlight\Widgets\Code_Highlight' ) )->newInstanceWithoutConstructor();
	$lottie = ( new ReflectionClass( 'ElementorPro\Modules\Lottie\Widgets\Lottie' ) )->newInstanceWithoutConstructor();
	$code->settings = array( 'line_numbers' => 'line-numbers', 'highlight_lines' => '', 'copy_to_clipboard' => 'copy-to-clipboard' );
	$today_code = array( 'prismjs_core', 'prismjs_loader', 'prismjs_normalize', 'highlight_handler', 'prismjs_line_numbers', 'prismjs_copy_to_clipboard' );
	$preview_code = array( 'prismjs_core', 'prismjs_loader', 'prismjs_normalize', 'highlight_handler', 'prismjs_line_numbers', 'prismjs_line_highlight', 'prismjs_copy_to_clipboard' );
	$lazy = array( Lazy_Libs::HANDLE );
	$mode = function ( array $applied, array $context = array(), bool $registered = true, bool $preview = false, bool $editor = false, bool $type = false ) use ( $prism_registry, $code, $lottie ) {
		ll_reset( $context );
		Transition::$applied = $applied;
		$prism_registry();
		Lazy_Libs::register();
		if ( ! $registered ) {
			unset( wp_scripts()->registered[ Lazy_Libs::HANDLE ] );
		}
		$elementor          = new \Elementor\Plugin();
		$elementor->preview = new \Elementor\LL_Preview();
		$elementor->editor  = new \Elementor\LL_Editor();
		$elementor->preview->on = $preview;
		$elementor->editor->on  = $editor;
		\Elementor\Plugin::$instance = $elementor;
		$code->type_instance   = $type;
		$lottie->type_instance = $type;
		return array( $code->get_script_depends(), $lottie->get_script_depends() );
	};
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( array() ), 'Sin nada aplicado: las librerías de siempre.' );
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( $off ), 'lazy_libs apagado: las librerías de siempre.' );
	check_local( array( $lazy, $lazy ) === $mode( $on ), 'lazy_libs aplicado en el front: sólo el cargador.' );
	check_local( Lazy_Libs::active(), 'active() en el front con lazy_libs aplicado.' );
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( $on, array(), true, false, false, true ), 'La instancia de registro declara siempre las librerías.' );
	check_local( array( $preview_code, array( 'lottie' ) ) === $mode( $on, array(), true, true ), 'En la vista previa del constructor, las librerías de siempre.' );
	check_local( ! Lazy_Libs::active(), 'active() es falso en la vista previa del constructor.' );
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( $on, array(), true, false, true ), 'En el editor, las librerías de siempre.' );
	check_local( ! Lazy_Libs::active(), 'active() es falso en el editor.' );
	ll_reset();
	$_GET['elementor-preview'] = '7';
	Transition::$applied = $on;
	$prism_registry();
	Lazy_Libs::register();
	\Elementor\Plugin::$instance->preview->on = false;
	\Elementor\Plugin::$instance->editor->on  = false;
	$code->type_instance   = false;
	$lottie->type_instance = false;
	check_local( array( 'lottie' ) === $lottie->get_script_depends() && ! Lazy_Libs::active(), 'Con ?elementor-preview, las librerías de siempre.' );
	unset( $_GET['elementor-preview'] );
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( $on, array( 'preview' => true ) ), 'En la vista previa de WordPress, las librerías de siempre.' );
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( $on, array( 'customize' => true ) ), 'En el Personalizador, las librerías de siempre.' );
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( $on, array(), false ), 'En el front sin el cargador registrado, las librerías de siempre.' );
	check_local( ! Lazy_Libs::active(), 'active() es falso sin el handle registrado.' );
	// Tema de bloques: el contenido se pinta antes de wp_enqueue_scripts, cuando aún no hay nada registrado.
	check_local( array( $lazy, $lazy ) === $mode( $on, array( 'did' => array() ), false ), 'En el front antes de registrar los scripts (tema de bloques), se declara el cargador.' );
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( $off, array( 'did' => array() ), false ), 'Antes de registrar los scripts y sin lazy_libs, las librerías de siempre.' );
	ll_reset( array( 'did' => array() ) );
	Transition::$applied = $on;
	$prism_registry();
	unset( wp_scripts()->registered['prismjs_toolbar'] );
	check_local( array( $lazy ) === array( $lottie->get_script_depends() ), 'Un widget pintado antes del registro declara el cargador.' );
	$GLOBALS['ll']['did'] = array( 'wp_enqueue_scripts' => 1 );
	Lazy_Libs::register();
	check_local( false === wp_scripts()->registered[ Lazy_Libs::HANDLE ]->src && in_array( 'lottie', wp_scripts()->registered[ Lazy_Libs::HANDLE ]->deps, true ), 'Si el registro falla después, el alias carga Lottie para ese widget.' );
	check_local( array( 'lottie' ) === $lottie->get_script_depends() && ! Lazy_Libs::active(), 'Con el alias, los widgets que se pintan después declaran sus librerías.' );
	check_local( array( $lazy, $lazy ) === $mode( $on, array( 'admin' => true, 'ajax' => true ), false ), 'Al guardar (AJAX de administración) se declara el cargador, para que _elementor_page_assets pida lo mismo que el front.' );
	check_local( ! Lazy_Libs::active(), 'active() es falso fuera del front.' );
	check_local( array( $today_code, array( 'lottie' ) ) === $mode( $off, array( 'admin' => true, 'ajax' => true ), false ), 'Al guardar sin lazy_libs, las librerías de siempre.' );
	$code->settings = array( 'line_numbers' => '', 'highlight_lines' => '1, 3-6', 'copy_to_clipboard' => '' );
	check_local( array( array( 'prismjs_core', 'prismjs_loader', 'prismjs_normalize', 'highlight_handler', 'prismjs_line_highlight' ), array( 'lottie' ) ) === $mode( $off ), 'Apagado, Código sigue quitando los plugins que no usa.' );

	echo 'DIGITALÍSIMO Elements: Prism ' . $manifest['version'] . ' y clipboard.js ' . $clip['version'] . ' locales (' . count( $js_files ) . ' scripts, ' . count( $values[1] ) . " valores de language), jshint de WordPress y cargador diferido por modo en verde.\n";
}
