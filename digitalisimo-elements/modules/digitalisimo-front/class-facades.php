<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Fachadas (`facades`, Fase 3 de `docs/ELEMENTS_FRONT_OBJECTIVE.md`): el Reproductor de video, el Slider de
 * dispositivos, el Panel lateral, el Mapa y el Visor de documentos pintan una portada con un botón en lugar del iframe
 * del servicio. Hasta que el visitante pulsa, la página no pide nada al dominio del vídeo, del mapa ni del visor: ni el
 * iframe, ni la preconexión, ni la miniatura (la sirve el sitio, ver `Facade_Images`).
 *
 * Apagado, nada cambia: los widgets pintan byte a byte su HTML de siempre. Aplicado por la transición (vacía las
 * cachés del constructor al entrar o salir), y sólo en el front (nunca en el editor ni en su vista previa):
 *
 * - `markup()` devuelve `div.digi-facade[data-digi-facade]` con las clases del iframe que sustituye (así lo dimensionan
 *   las hojas de cada widget), la imagen si existe, un `<button>` nativo con nombre accesible que lleva en `data-*` los
 *   atributos del iframe, y el iframe original en `<noscript>` (sin JavaScript se ve como siempre; con él, el navegador
 *   no lo pide).
 * - `assets/facade.js` (≤ 1 KB, `defer`, sin dependencias) escucha los clics en el documento, crea el iframe con esos
 *   atributos, sustituye la fachada y le pasa el foco, así el teclado y el lector de pantalla siguen en el contenido.
 *   Un vídeo arranca solo al pulsar (`autoplay=1`), como hace el reproductor de la lista.
 * - La hoja y el script se declaran como dependencias del widget (`depends()`): sólo los carga la página que pinta
 *   una fachada.
 * - Mientras la imagen de una fachada está pendiente, el widget no entra en la caché de elementos
 *   (`elementor/element/is_dynamic_content`): la visita siguiente a la descarga ya la ve.
 */
final class Facades {
	const HANDLE = 'digitalisimo-elements-facade';

	const SCRIPT = 'modules/digitalisimo-front/assets/facade.js';

	const STYLE = 'modules/digitalisimo-front/assets/facade.css';

	/** `allow` de los reproductores de vídeo (el de los widgets). */
	const ALLOW_VIDEO = 'autoplay; encrypted-media; picture-in-picture; fullscreen';

	/** Prefijo del nombre accesible del botón de un vídeo. */
	const PLAY = 'Reproducir el vídeo: ';

	/** Widgets con fachada y la clase que dice qué trabajos de imagen necesitan sus ajustes (`facade_jobs()`). */
	const WIDGETS = array(
		'digitalisimo-video-player'   => '\Digitalisimo\Elements\Video_Player_Widget',
		'digitalisimo-device-slider'  => '\Digitalisimo\Elements\Device_Slider_Widget',
		'digitalisimo-offcanvas'      => '\Digitalisimo\Elements\Offcanvas_Widget',
		'digitalisimo-map'            => '\Digitalisimo\Elements\Map_Widget',
	);

	private static $initialized = false;

	/** @var bool|null Estado de esta petición. */
	private static $active = null;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register' ) );
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'dynamic' ), 10, 3 );
		Facade_Images::init();
	}

	/** Aplicado en este sitio, en el front y fuera del editor. Se decide una vez por petición. */
	public static function active(): bool {
		if ( null === self::$active ) {
			self::$active = self::applied() && Lazy_Videos::front() && ! Lazy_Videos::editing();
		}
		return self::$active;
	}

	/** Para las pruebas: olvida lo decidido en esta petición. */
	public static function reset(): void {
		self::$active = null;
	}

	/** Registra la hoja y el script (sólo con el interruptor aplicado: apagado, ningún handle nuevo). */
	public static function register(): void {
		if ( ! self::applied() || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			wp_register_style( self::HANDLE, plugins_url( self::STYLE, DIGITALISIMO_ELEMENTS_FILE ), array(), $version );
		}
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script( self::HANDLE, plugins_url( self::SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}

	/** Handle de la hoja o del script para `get_style_depends()`/`get_script_depends()`: sólo si el widget pinta una fachada. */
	public static function depends( bool $uses ): array {
		return $uses && self::active() ? array( self::HANDLE ) : array();
	}

	/**
	 * Fachada de un vídeo de un servicio: portada guardada en el sitio (si ya está), botón «Reproducir el vídeo: …» y el
	 * iframe con `autoplay` al pulsar.
	 *
	 * @param string $src   Dirección del reproductor (la del iframe de siempre).
	 * @param string $title Nombre accesible del vídeo (`title` del iframe).
	 * @param string $class Clases del iframe.
	 * @param array  $job   Trabajo de la portada (`Facade_Images::video()`), o [] sin portada.
	 * @param string $allow `allow` del iframe.
	 */
	public static function video( string $src, string $title, string $class, array $job, string $allow = self::ALLOW_VIDEO ): string {
		return self::markup(
			'video',
			array( 'src' => self::autoplay( $src ), 'plain' => $src, 'title' => $title, 'class' => $class, 'allow' => $allow, 'fullscreen' => true ),
			array( 'name' => self::PLAY . $title, 'image' => $job ? Facade_Images::url( $job ) : '' )
		);
	}

	/**
	 * Marcado de una fachada.
	 *
	 * @param string $kind  `video`, `map`, `document` o `embed` (modificador de clase).
	 * @param array  $frame Iframe que sustituye: `src` (HTTPS; con `autoplay` si es un vídeo), `plain` (el del
	 *                      `<noscript>`, por defecto `src`), `title`, `class`, `allow`, `referrer`, `fullscreen`.
	 * @param array  $face  `name` (nombre accesible del botón), `label` (texto visible; sin él, el icono de reproducir),
	 *                      `image` (URL de la imagen del sitio), `pin` (marcador en el centro), `note` (HTML ya escapado).
	 */
	public static function markup( string $kind, array $frame, array $face ): string {
		$src = (string) ( $frame['src'] ?? '' );
		if ( 'https' !== strtolower( (string) wp_parse_url( $src, PHP_URL_SCHEME ) ) ) {
			return '';
		}
		$plain      = (string) ( $frame['plain'] ?? $src );
		$title      = (string) ( $frame['title'] ?? '' );
		$class      = trim( (string) ( $frame['class'] ?? '' ) );
		$allow      = (string) ( $frame['allow'] ?? '' );
		$referrer   = (string) ( $frame['referrer'] ?? '' );
		$fullscreen = ! empty( $frame['fullscreen'] );
		$image      = (string) ( $face['image'] ?? '' );
		$label      = (string) ( $face['label'] ?? '' );
		$data       = ' data-digi-facade-src="' . esc_url( $src ) . '" data-digi-facade-title="' . esc_attr( $title ) . '"'
			. ( '' !== $class ? ' data-digi-facade-class="' . esc_attr( $class ) . '"' : '' )
			. ( '' !== $allow ? ' data-digi-facade-allow="' . esc_attr( $allow ) . '"' : '' )
			. ( '' !== $referrer ? ' data-digi-facade-referrer="' . esc_attr( $referrer ) . '"' : '' )
			. ( $fullscreen ? ' data-digi-facade-fullscreen' : '' );
		$iframe     = '<iframe' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' src="' . esc_url( $plain ) . '" title="' . esc_attr( $title ) . '"'
			. ( '' !== $allow ? ' allow="' . esc_attr( $allow ) . '"' : '' ) . ( $fullscreen ? ' allowfullscreen' : '' )
			. ( '' !== $referrer ? ' referrerpolicy="' . esc_attr( $referrer ) . '"' : '' ) . '></iframe>';
		$out  = '<div class="digi-facade digi-facade--' . esc_attr( $kind ) . ( '' !== $image ? ' digi-facade--image' : '' ) . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '" data-digi-facade>';
		$out .= '' !== $image ? '<img class="digi-facade__image" src="' . esc_url( $image ) . '" alt="" width="1280" height="720" loading="lazy" decoding="async">' : '';
		$out .= '' !== $image && ! empty( $face['pin'] ) ? '<span class="digi-facade__pin" aria-hidden="true"></span>' : '';
		$out .= '<button type="button" class="digi-facade__button" aria-label="' . esc_attr( (string) ( $face['name'] ?? $label ) ) . '"' . $data . '>';
		$out .= '' !== $label ? '<span class="digi-facade__label">' . esc_html( $label ) . '</span>' : '<span class="digi-facade__play" aria-hidden="true"></span>';
		$out .= '</button>';
		$out .= ! empty( $face['note'] ) ? '<span class="digi-facade__note">' . $face['note'] . '</span>' : '';
		return $out . '<noscript>' . $iframe . '</noscript></div>';
	}

	/**
	 * Dirección con reproducción automática al pulsar: `autoplay=1` (YouTube, Vimeo y Dailymotion) y, en Wistia,
	 * `autoPlay=true`. Si ya la pide, no cambia.
	 */
	public static function autoplay( string $src ): string {
		if ( preg_match( '/[?&]autoplay=/i', $src ) ) {
			return $src;
		}
		$params = false !== stripos( (string) wp_parse_url( $src, PHP_URL_HOST ), 'wistia' ) ? 'autoPlay=true' : 'autoplay=1';
		return $src . ( false === strpos( $src, '?' ) ? '?' : '&' ) . $params;
	}

	/**
	 * `elementor/element/is_dynamic_content`: un widget con una fachada cuya imagen aún no está no se guarda en la caché
	 * de elementos (la descarga corre en cola; la visita siguiente ya la ve). Sin fachadas, no cambia nada.
	 */
	public static function dynamic( $is_dynamic, $raw = array(), $element = null ) {
		if ( $is_dynamic || ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || ! self::active() ) {
			return $is_dynamic;
		}
		$class = self::WIDGETS[ (string) $element->get_name() ] ?? '';
		if ( '' === $class || ! class_exists( $class ) || ! method_exists( $class, 'facade_jobs' ) ) {
			return $is_dynamic;
		}
		$settings = is_array( $raw ) && isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array();
		foreach ( (array) $class::facade_jobs( $settings ) as $job ) {
			if ( is_array( $job ) && Facade_Images::pending( $job ) ) {
				return true;
			}
		}
		return $is_dynamic;
	}

	private static function applied(): bool {
		$transition = __NAMESPACE__ . '\\Transition';
		if ( ! class_exists( $transition ) || ! method_exists( $transition, 'applied' ) ) {
			return false;
		}
		$applied = $transition::applied();
		return is_array( $applied ) && isset( $applied['facades'] ) && true === $applied['facades'];
	}
}
