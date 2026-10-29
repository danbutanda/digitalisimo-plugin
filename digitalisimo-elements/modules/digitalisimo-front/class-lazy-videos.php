<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Carga diferida de los vídeos alojados con reproducción automática (`lazy_videos`, Fase 3 de
 * `docs/ELEMENTS_FRONT_OBJECTIVE.md`): el vídeo de fondo de contenedores y secciones, y el widget Vídeo del núcleo con
 * un archivo alojado y «Reproducción automática». Apagado, nada cambia. Aplicado por la transición, y sólo en el front
 * (nunca en el editor, su vista previa, REST, AJAX, cron ni WP-CLI):
 *
 * 1. `elementor/frontend/before_render` abre un búfer sólo para los elementos que lo necesitan (fondo de vídeo con un
 *    archivo, no YouTube ni Vimeo; widget Vídeo alojado con reproducción automática y sin imagen de portada) y
 *    `elementor/frontend/after_render` reescribe su `<video>`: fuera `autoplay`, `preload="none"` y la marca
 *    `data-digi-lazy-video`. El resto del HTML sale byte a byte igual, y un elemento sin vídeo no pasa por el búfer.
 * 2. El manejador del núcleo sigue igual: pone el `src` del vídeo de fondo al cargar la página, pero con `preload="none"`
 *    y sin `autoplay` el navegador no lo descarga. `assets/lazy-videos.js` (≤ 1 KB, `defer`) observa esos vídeos y, a
 *    una pantalla de distancia, les devuelve `autoplay` y `preload="auto"` y los reproduce; `changeVideoSize()` del
 *    núcleo, que espera `canplay`, ajusta el tamaño cuando llega. Sin `IntersectionObserver`, se reproducen al cargar.
 *
 * Los vídeos de YouTube y Vimeo no cambian: el widget Vídeo ya tiene «Lazy Load» para ellos y el fondo con esos
 * servicios lo crea la API del proveedor, que este interruptor no toca. Cambiar `lazy_videos` pasa por la transición,
 * que vacía las cachés del constructor: ninguna página cacheada mezcla los dos marcados.
 */
final class Lazy_Videos {
	const HANDLE = 'digitalisimo-elements-lazy-videos';

	const SCRIPT = 'modules/digitalisimo-front/assets/lazy-videos.js';

	const MARK = 'data-digi-lazy-video';

	private static $initialized = false;

	/** @var bool|null Estado de esta petición. */
	private static $active = null;

	/** @var string[] Elementos con un búfer abierto, de fuera hacia dentro. */
	private static $open = array();

	private static $enqueued = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register' ) );
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/after_render', array( __CLASS__, 'after_render' ) );
	}

	/** Aplicado en este sitio, en el front y fuera del editor. Se decide una vez por petición. */
	public static function active(): bool {
		if ( null === self::$active ) {
			self::$active = self::applied() && self::front() && ! self::editing();
		}
		return self::$active;
	}

	/** Para las pruebas: olvida lo decidido en esta petición. */
	public static function reset(): void {
		self::$active   = null;
		self::$open     = array();
		self::$enqueued = false;
	}

	public static function register(): void {
		if ( ! self::applied() || wp_script_is( self::HANDLE, 'registered' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_register_script( self::HANDLE, plugins_url( self::SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** `elementor/frontend/before_render`: abre el búfer sólo para un elemento con vídeo alojado que arranca solo. */
	public static function before_render( $element ): void {
		if ( ! self::active() || ! self::targets( $element ) ) {
			return;
		}
		self::$open[] = self::id( $element );
		ob_start();
	}

	/** `elementor/frontend/after_render`: cierra el búfer de ese elemento y reescribe sus `<video>`. */
	public static function after_render( $element ): void {
		if ( ! self::$open || end( self::$open ) !== self::id( $element ) ) {
			return;
		}
		array_pop( self::$open );
		$html = (string) ob_get_clean();
		echo self::rewrite( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Es el HTML que el constructor acababa de imprimir, con tres atributos cambiados.
		if ( ! self::$enqueued && function_exists( 'wp_enqueue_script' ) ) {
			self::$enqueued = true;
			wp_enqueue_script( self::HANDLE );
		}
	}

	/**
	 * ¿Imprime este elemento un vídeo alojado que arranca solo? Fondo de vídeo de contenedor o sección con un archivo
	 * (no un servicio), o el widget Vídeo del núcleo con «Alojado», «Reproducción automática» y sin imagen de portada.
	 */
	public static function targets( $element ): bool {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings_for_display' ) || ! method_exists( $element, 'get_name' ) ) {
			return false;
		}
		$name = (string) $element->get_name();
		if ( 'container' === $name || 'section' === $name ) {
			$link = (string) $element->get_settings_for_display( 'background_video_link' );
			return 'video' === $element->get_settings_for_display( 'background_background' ) && '' !== $link && ! self::is_embed( $link );
		}
		if ( 'video' === $name ) {
			return 'hosted' === $element->get_settings_for_display( 'video_type' )
				&& 'yes' === $element->get_settings_for_display( 'autoplay' )
				&& 'yes' !== $element->get_settings_for_display( 'show_image_overlay' );
		}
		return false;
	}

	/**
	 * Fuera `autoplay`, `preload="none"` y la marca, sólo en los `<video>` del núcleo que arrancan solos: el de fondo
	 * (`elementor-background-video-hosted`) y el del widget Vídeo (`elementor-video`). Lo demás no cambia.
	 */
	public static function rewrite( string $html ): string {
		return (string) preg_replace_callback(
			'/<video\b([^>]*\bclass="(?:elementor-background-video-hosted|elementor-video)"[^>]*)>/',
			static function ( array $m ): string {
				$attributes = $m[1];
				if ( ! preg_match( '/\sautoplay(?:=(?:"[^"]*"|\'[^\']*\'|[^\s>]*))?(?=\s|$)/', $attributes ) ) {
					return $m[0];
				}
				$attributes = (string) preg_replace( '/\sautoplay(?:=(?:"[^"]*"|\'[^\']*\'|[^\s>]*))?(?=\s|$)/', '', $attributes, 1 );
				$attributes = (string) preg_replace( '/\spreload=(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/', '', $attributes );
				return '<video' . rtrim( $attributes ) . ' preload="none" ' . self::MARK . '>';
			},
			$html
		);
	}

	private static function is_embed( string $link ): bool {
		$embed = '\Elementor\Embed';
		if ( class_exists( $embed ) && method_exists( $embed, 'is_embed_video' ) ) {
			return (bool) $embed::is_embed_video( $link );
		}
		return (bool) preg_match( '#(youtube\.com|youtu\.be|vimeo\.com|dailymotion\.com|videopress\.com)#i', $link );
	}

	private static function id( $element ): string {
		return method_exists( $element, 'get_id' ) ? (string) $element->get_id() : spl_object_hash( $element );
	}

	private static function applied(): bool {
		$transition = __NAMESPACE__ . '\\Transition';
		if ( ! class_exists( $transition ) || ! method_exists( $transition, 'applied' ) ) {
			return false;
		}
		$applied = $transition::applied();
		return is_array( $applied ) && isset( $applied['lazy_videos'] ) && true === $applied['lazy_videos'];
	}

	private static function front(): bool {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return false;
		}
		return ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI );
	}

	private static function editing(): bool {
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sólo decide si se reescribe el marcado.
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
