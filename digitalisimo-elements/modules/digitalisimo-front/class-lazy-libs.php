<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Cargador opt-in de Lottie y Prism (`lazy_libs`, frente D de F-front; `docs/ELEMENTS_FRONT_OBJECTIVE.md`, Fase 2).
 *
 * Apagado (el valor por defecto en todos los sitios) no cambia nada: los widgets Lottie y Resaltado de código declaran
 * sus librerías como siempre y el navegador las descubre al analizar el HTML. Con `lazy_libs` aplicado por la
 * transición (`Transition::applied()`), los dos widgets declaran sólo `HANDLE` (`assets/lazy-libs.js`, `defer`, en el
 * pie, con `elementor-frontend` como dependencia). El script envuelve `elementorFrontend.elementsHandler.addHandler`
 * sólo para `lottie.default` y `code-highlight.default`: si la librería ya está, llama al original; si no, espera a
 * que el elemento esté a una pantalla de distancia (`IntersectionObserver`; sin él, en el momento), carga la librería
 * una sola vez y llama al original con los mismos argumentos. Los handlers de PRO se instancian sin cambios y ningún
 * paquete compilado se edita. La API es pública y existe también con el plugin Elementor original.
 *
 * Prism se carga como hoy: `Prism.manual = true` antes del core, luego autoloader, normalize y los plugins que piden
 * las marcas de los widgets de código de la página (`pre.line-numbers`, `pre[data-line]` no vacío, envoltorio
 * `.copy-to-clipboard`; toolbar y clipboard.js antes de copy), y `Prism.highlightAll()` una vez: el mismo resultado
 * que el arranque automático de antes, también con varios widgets de opciones distintas. Un widget que llegue después
 * con otra marca sólo añade su plugin. Todo se inserta con `async = false`: se descarga en paralelo y se ejecuta en
 * orden. Si una carga falla, el handler no se llama y la consola lo avisa.
 *
 * La configuración (`window.digitalisimoLazyLibs`) va en posición `before` del propio handle, que no afecta a su
 * `defer`: la URL y la versión de `lottie` y de los ocho handles de Prism (los siete de antes y clipboard.js), leídas
 * de sus registros y construidas como las imprime WordPress (base, `ver` y el filtro `script_loader_src`). Si falta
 * alguna, el cargador no se registra y los widgets declaran sus librerías como siempre.
 *
 * Un tema de bloques pinta el contenido antes de `wp_head`, cuando el núcleo aún no registró los scripts del front
 * (`wp_enqueue_scripts`, 5): esos widgets declaran el cargador igual, porque se registra antes de imprimirse. Si al
 * registrarse falta algún handle, `HANDLE` queda como alias sin script de las librerías que sí existen (como siempre,
 * sin diferir) y los widgets que se pinten después vuelven a declarar las suyas.
 *
 * Cambiar `lazy_libs` pasa por la transición, que vacía las cachés del constructor: ninguna entrada cacheada queda
 * con la lista de scripts del otro modo.
 */
final class Lazy_Libs {
	const HANDLE = 'digitalisimo-elements-lazy-libs';

	/** Librería de Lottie. */
	const LOTTIE = 'lottie';

	/**
	 * Handles de Prism en el orden en que se ejecutan; el script los pide por su posición (0-2 siempre, 3 con
	 * `pre.line-numbers`, 4 con `pre[data-line]` no vacío, 5-7 con `.copy-to-clipboard`). `prismjs_clipboard` es
	 * clipboard.js, que copy-to-clipboard necesita antes de ejecutarse para no pedirlo a un CDN.
	 */
	const PRISM = array( 'prismjs_core', 'prismjs_loader', 'prismjs_normalize', 'prismjs_line_numbers', 'prismjs_line_highlight', 'prismjs_toolbar', 'prismjs_clipboard', 'prismjs_copy_to_clipboard' );

	/** Script del cargador, relativo a la carpeta del plugin. */
	const SCRIPT = 'modules/digitalisimo-front/assets/lazy-libs.js';

	/** @var bool */
	private static $initialized = false;

	/** @var bool `HANDLE` es el alias de respaldo (faltaba un registro), no el cargador. */
	private static $fallback = false;

	/** Registra el cargador después de los scripts del front (los de Lottie y Prism ya están registrados). */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register' ) );
	}

	/**
	 * ¿Rige el cargador en esta petición? `lazy_libs` aplicado, el front (ni administración, AJAX, REST, cron ni CLI),
	 * fuera del editor y de toda vista previa, y con el cargador registrado (no el alias de respaldo).
	 */
	public static function active(): bool {
		return self::applied() && self::front() && ! self::editing() && self::loader();
	}

	/**
	 * ¿Declara este widget el cargador en lugar de su librería? Nunca la instancia de registro (con la que el editor
	 * encola los scripts de cada tipo), el editor ni la vista previa. En el front, si el cargador está registrado o
	 * todavía no se registraron los scripts del front (un tema de bloques pinta el contenido antes de `wp_head`; si el
	 * registro falla, `HANDLE` es el alias de las librerías). Fuera del front (el guardado del documento, que calcula
	 * `_elementor_page_assets`), con el valor aplicado, para que los recursos guardados de la página pidan lo mismo que
	 * el front; si el front no tuviera el handle, ese nombre no imprime nada y el widget vuelve a declarar su librería
	 * al pintarse.
	 *
	 * @param object|null $element Instancia del widget.
	 */
	public static function declares( $element = null ): bool {
		if ( is_object( $element ) && method_exists( $element, 'is_type_instance' ) && $element->is_type_instance() ) {
			return false;
		}
		if ( ! self::applied() || self::editing() ) {
			return false;
		}
		return self::front() ? ( self::loader() || ! did_action( 'wp_enqueue_scripts' ) ) : true;
	}

	/**
	 * Registra el cargador y su configuración, sólo con `lazy_libs` aplicado. Si falta un registro, `HANDLE` queda como
	 * alias sin script de las librerías que existen: los widgets que ya lo declararon (un tema de bloques, o los
	 * recursos guardados de la página) las cargan como siempre.
	 */
	public static function register(): void {
		if ( ! self::applied() || wp_script_is( self::HANDLE, 'registered' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$config = self::config();
		if ( ! $config ) {
			$scripts        = wp_scripts();
			$deps           = array_values( array_filter( array_merge( array( self::LOTTIE ), self::PRISM ), function ( $handle ) use ( $scripts ) {
				return isset( $scripts->registered[ $handle ] );
			} ) );
			self::$fallback = wp_register_script( self::HANDLE, false, $deps, false, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			return;
		}
		self::$fallback = false;
		$version        = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_register_script( self::HANDLE, plugins_url( self::SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array( 'elementor-frontend' ), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script( self::HANDLE, 'window.digitalisimoLazyLibs=' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
	}

	/**
	 * Configuración del script: `lottie` y `prism` (lista en el orden de `PRISM`), cada uno con su URL final y su
	 * versión. `[]` si falta alguno de los ocho registros.
	 */
	public static function config(): array {
		$scripts = wp_scripts();
		$lottie  = self::source( $scripts, self::LOTTIE );
		if ( ! $lottie ) {
			return array();
		}
		$prism = array();
		foreach ( self::PRISM as $handle ) {
			$source = self::source( $scripts, $handle );
			if ( ! $source ) {
				return array();
			}
			$prism[] = array( 'handle' => $handle ) + $source;
		}
		return array(
			'lottie' => $lottie,
			'prism'  => $prism,
		);
	}

	/**
	 * URL con la que WordPress imprimiría un handle registrado: base del sitio para rutas relativas, `ver` (la
	 * predeterminada si se registró con `false`, ninguna con `null`) y el filtro `script_loader_src`.
	 *
	 * @param \WP_Scripts $scripts Registro de scripts.
	 * @return array{src:string,ver:string}|null
	 */
	private static function source( $scripts, string $handle ): ?array {
		if ( ! is_object( $scripts ) || empty( $scripts->registered[ $handle ] ) || empty( $scripts->registered[ $handle ]->src ) || ! is_string( $scripts->registered[ $handle ]->src ) ) {
			return null;
		}
		$item = $scripts->registered[ $handle ];
		$src  = $item->src;
		if ( ! preg_match( '|^(https?:)?//|', $src ) && ! ( ! empty( $scripts->content_url ) && 0 === strpos( $src, $scripts->content_url ) ) ) {
			$src = $scripts->base_url . $src;
		}
		$ver = null === $item->ver ? '' : (string) ( $item->ver ? $item->ver : $scripts->default_version );
		if ( '' !== $ver ) {
			$src = add_query_arg( 'ver', $ver, $src );
		}
		$src = esc_url_raw( apply_filters( 'script_loader_src', $src, $handle ) );
		return '' === $src ? null : array(
			'src' => $src,
			'ver' => $ver,
		);
	}

	/** El cargador, no el alias de respaldo, está registrado. */
	private static function loader(): bool {
		return wp_script_is( self::HANDLE, 'registered' ) && ! self::$fallback;
	}

	/** `lazy_libs` aplicado por la transición. Sin ella (o sin nada aplicado), apagado. */
	private static function applied(): bool {
		$transition = __NAMESPACE__ . '\\Transition';
		if ( ! class_exists( $transition ) || ! method_exists( $transition, 'applied' ) ) {
			return false;
		}
		$applied = $transition::applied();
		return is_array( $applied ) && isset( $applied['lazy_libs'] ) && true === $applied['lazy_libs'];
	}

	/** Petición del front: ni administración, AJAX, REST, cron ni CLI. */
	private static function front(): bool {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return false;
		}
		return ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/** Editor del constructor, su vista previa, la vista previa de WordPress o el Personalizador. */
	private static function editing(): bool {
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sólo decide qué script se declara.
			return true;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}
		if ( isset( $GLOBALS['wp_query'] ) && function_exists( 'is_preview' ) && is_preview() ) {
			return true;
		}
		$elementor = class_exists( 'Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
		if ( ! is_object( $elementor ) ) {
			return false;
		}
		if ( isset( $elementor->preview ) && is_object( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
			return true;
		}
		return isset( $elementor->editor ) && is_object( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode();
	}
}
