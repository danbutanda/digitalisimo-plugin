<?php
namespace Digitalisimo\Elements\Design\Backgrounds;

use Digitalisimo\Elements\Design\Css;

defined( 'ABSPATH' ) || exit;

/**
 * Biblioteca de máscaras y teselas, y SVG propios del sitio (F7a, frente G; V.5 de la especificación).
 *
 * - **Biblioteca propia** (`library.php`, generada por `scripts/design-library.mjs`): 12 máscaras y 12 teselas de
 *   geometría propia, ya codificadas en porcentaje. En ejecución no se lee ningún archivo.
 * - **Una variable por slug y documento** (R-I13, V-m18): los `selectors_dictionary` llevan cada slug a
 *   `var(--digi-mask-<slug>)` o `var(--digi-tile-<slug>)`; `define()` añade a la hoja del documento, una sola vez,
 *   `:root{--digi-mask-<slug>:url("data:…")}`. En la vista previa del editor, `preview_styles()` define todas.
 * - **SVG del sitio** (R-I8): al guardar, el adjunto SVG elegido se sanea (`Css::svg_clean()`, con la lista de rechazo
 *   y el tope de 8 KB) y se copia a `uploads/digitalisimo-elements/masks/<sha8>.svg`; el control oculto `…_file`
 *   guarda la ruta relativa `digitalisimo-elements/masks/<sha8>.svg`, que la parte `files` de K exporta, sanea y
 *   escribe con el mismo nombre. El CSS lee sólo esa copia, comprobada con `realpath`, nunca el adjunto ni una URL.
 *   Durante una importación de kits no se crea ninguna copia: sólo se reutiliza la que ya exista. La escribe la parte
 *   `files` de K (los mismos bytes: el saneador del núcleo deja igual la salida de `Css::svg_clean()`), que la anota
 *   en la sesión, así que «Revertir» la borra; una copia creada aquí la daría por reutilizada y quedaría huérfana.
 * - **Valores cerrados**: `guard_selectors()` descarta la regla de un control propio cuyo valor no es uno de los
 *   suyos (un slug desconocido llegado de un kit, o una cadena con CSS), antes de que el núcleo la escriba.
 */
final class Library {
	/** Máscaras: slug → etiqueta, en el orden del selector. */
	const MASKS = array(
		'circulo'               => 'Círculo',
		'elipse'                => 'Elipse',
		'rombo'                 => 'Rombo',
		'hexagono'              => 'Hexágono',
		'octogono'              => 'Octógono',
		'triangulo'             => 'Triángulo',
		'estrella'              => 'Estrella',
		'cruz'                  => 'Cruz',
		'escudo'                => 'Escudo',
		'gota'                  => 'Gota',
		'corazon'               => 'Corazón',
		'rectangulo-redondeado' => 'Rectángulo redondeado',
	);

	/** Teselas: slug → etiqueta. */
	const TILES = array(
		'puntos'          => 'Puntos',
		'rejilla'         => 'Rejilla',
		'rayas'           => 'Rayas',
		'tablero'         => 'Tablero',
		'rombos'          => 'Rombos',
		'anillos'         => 'Anillos',
		'signos-mas'      => 'Signos más',
		'ladrillos'       => 'Ladrillos',
		'puntos-alternos' => 'Puntos alternos',
		'lineas-cruzadas' => 'Líneas cruzadas',
		'semicirculos'    => 'Semicírculos',
		'cuadros'         => 'Cuadros',
	);

	/** Opción del selector que usa el SVG de Medios. */
	const MEDIA = 'media';

	/** Carpeta de las copias saneadas, relativa a `uploads` (la que transporta la parte `files` de K). */
	const FOLDER = 'digitalisimo-elements/masks';

	/** Ruta relativa válida de una copia: el nombre es el de K (`Parts\Files::SLUG`), que admite `<sha8>`. */
	const FILE_PATTERN = '#^digitalisimo-elements/masks/([a-z0-9]+(?:-[a-z0-9]+)*)\.svg$#';

	/** Controles con SVG de Medios: selector, MEDIA y oculto con la ruta de la copia. */
	const MEDIA_CONTROLS = array(
		array( 'digitalisimo_mask_shape', 'digitalisimo_mask_media', 'digitalisimo_mask_file' ),
		array( 'digitalisimo_pattern_tile', 'digitalisimo_pattern_media', 'digitalisimo_pattern_file' ),
	);

	/** @var array|null Contenido de `library.php`. */
	private static $data = null;

	/** @var array<string,array|null> Copias ya leídas en esta petición. */
	private static $files = array();

	/** @var bool */
	private static $started = false;

	/** Ganchos comunes de la máscara y el patrón; los llaman sus `init()` cuando pasan sus guardas. Idempotente. */
	public static function init(): void {
		if ( self::$started ) {
			return;
		}
		self::$started = true;
		add_filter( 'elementor/document/save/data', array( __CLASS__, 'save_data' ), 10, 2 );
		add_filter( 'elementor/files/css/selectors', array( __CLASS__, 'guard_selectors' ), 10, 3 );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'preview_styles' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( Gradient::class, 'editor_script' ) );
	}

	// ── Biblioteca ─────────────────────────────────────────────────────────────────────────────────────────────

	/** `url("data:…")` de una máscara de la biblioteca, o `null`. */
	public static function mask( string $slug ): ?string {
		$data = self::data();
		return isset( self::MASKS[ $slug ], $data['masks'][ $slug ] ) ? (string) $data['masks'][ $slug ] : null;
	}

	/** `url("data:…")` de una tesela de la biblioteca, o `null`. */
	public static function tile( string $slug ): ?string {
		$data = self::data();
		return isset( self::TILES[ $slug ], $data['patterns'][ $slug ] ) ? (string) $data['patterns'][ $slug ] : null;
	}

	/**
	 * Slugs de un tipo (`masks` o `patterns`), en el orden del selector.
	 *
	 * @return string[]
	 */
	public static function slugs( string $kind ): array {
		return array_keys( 'masks' === $kind ? self::MASKS : ( 'patterns' === $kind ? self::TILES : array() ) );
	}

	/** Opciones del selector: la biblioteca y «Archivo SVG». */
	public static function options( string $kind ): array {
		$labels = 'masks' === $kind ? self::MASKS : self::TILES;
		return $labels + array( self::MEDIA => 'Archivo SVG de Medios' );
	}

	/** Diccionario del selector: slug → `var(--digi-…-<slug>)`; `media` → '' (la regla la escribe `define_file()`). */
	public static function dictionary( string $kind ): array {
		$out = array();
		foreach ( self::slugs( $kind ) as $slug ) {
			$out[ $slug ] = 'var(' . self::variable( $kind, $slug ) . ')';
		}
		$out[ self::MEDIA ] = '';
		return $out;
	}

	/** Nombre de la variable de un slug de la biblioteca. */
	public static function variable( string $kind, string $slug ): string {
		return ( 'masks' === $kind ? '--digi-mask-' : '--digi-tile-' ) . $slug;
	}

	/** Contenido de `library.php`, una vez por petición. */
	private static function data(): array {
		if ( null === self::$data ) {
			$data       = is_file( __DIR__ . '/library.php' ) ? require __DIR__ . '/library.php' : array();
			self::$data = is_array( $data ) ? $data : array();
		}
		return self::$data;
	}

	// ── CSS del documento ──────────────────────────────────────────────────────────────────────────────────────

	/** Archivo CSS de un documento, sin el dinámico. */
	public static function is_post_css( $post_css ): bool {
		return $post_css instanceof \Elementor\Core\Files\CSS\Post
			&& ! $post_css instanceof \Elementor\Core\DynamicTags\Dynamic_CSS;
	}

	/**
	 * Escribe en la hoja del documento lo que necesita un selector de máscara o de tesela con su valor:
	 * - un slug de la biblioteca: su variable en `:root`, una sola vez por documento (el `Stylesheet` fusiona por
	 *   selector y por propiedad);
	 * - `media`: la variable de la copia saneada y la regla `-webkit-mask-image`/`mask-image` en los selectores del
	 *   control (los mismos que usa el núcleo para el diccionario). Sin copia válida no se escribe nada.
	 *
	 * @param \Elementor\Core\Files\CSS\Post $post_css Archivo CSS.
	 * @param \Elementor\Controls_Stack       $element  Elemento.
	 * @param string                          $kind     `masks` o `patterns`.
	 * @param string                          $choice   Control del selector.
	 * @param string                          $file     Control oculto con la ruta de la copia.
	 */
	public static function define( $post_css, $element, string $kind, string $choice, string $file ): void {
		$settings = $element->get_settings();
		$value    = isset( $settings[ $choice ] ) && is_string( $settings[ $choice ] ) ? $settings[ $choice ] : '';
		$sheet    = $post_css->get_stylesheet();
		if ( self::MEDIA !== $value ) {
			$uri = 'masks' === $kind ? self::mask( $value ) : self::tile( $value );
			if ( null !== $uri ) {
				$sheet->add_rules( ':root', array( self::variable( $kind, $value ) => $uri ) );
			}
			return;
		}
		$copy = self::file_uri( isset( $settings[ $file ] ) ? $settings[ $file ] : '' );
		if ( null === $copy ) {
			return;
		}
		$variable = ( 'masks' === $kind ? '--digi-mask-file-' : '--digi-tile-file-' ) . $copy['name'];
		$selectors = Css::core_selectors(
			$element,
			$choice,
			array(
				'{{WRAPPER}}' => $post_css->get_element_unique_selector( $element ),
				'{{ID}}'      => (string) $element->get_id(),
			)
		);
		if ( ! $selectors ) {
			return;
		}
		$sheet->add_rules( ':root', array( $variable => $copy['uri'] ) );
		foreach ( $selectors as $selector ) {
			$sheet->add_rules(
				$selector,
				array(
					'-webkit-mask-image' => 'var(' . $variable . ')',
					'mask-image'         => 'var(' . $variable . ')',
				)
			);
		}
	}

	/**
	 * `elementor/files/css/selectors`: un control propio de máscara o patrón con un valor que no es suyo se queda sin
	 * selectores y el núcleo no escribe su regla. El valor llega ya pasado por el diccionario.
	 *
	 * @param array $control Control.
	 * @param mixed $value   Valor de estilo.
	 * @return array
	 */
	public static function guard_selectors( $control, $value = null ) {
		if ( ! is_array( $control ) || empty( $control['name'] ) || ! is_string( $control['name'] ) || empty( $control['selectors'] ) ) {
			return $control;
		}
		$name = $control['name'];
		if ( 0 !== strpos( $name, 'digitalisimo_mask' ) && 0 !== strpos( $name, 'digitalisimo_pattern' ) ) {
			return $control;
		}
		if ( ! self::allowed( $control, $value ) ) {
			$control['selectors'] = array();
		}
		return $control;
	}

	/** El valor de estilo de un control propio es uno de los suyos. */
	private static function allowed( array $control, $value ): bool {
		if ( ! empty( $control['selectors_dictionary'] ) && is_array( $control['selectors_dictionary'] ) ) {
			return is_string( $value ) && in_array( $value, array_map( 'strval', $control['selectors_dictionary'] ), true );
		}
		$type = isset( $control['type'] ) ? (string) $control['type'] : '';
		if ( 'slider' === $type ) {
			return is_array( $value ) && isset( $value['size'] ) && is_numeric( $value['size'] );
		}
		if ( 'color' === $type ) {
			return is_array( $value ) || null !== Css::color( $value );
		}
		if ( in_array( $type, array( 'switcher', 'popover_toggle' ), true ) ) {
			return 'yes' === $value;
		}
		return true;
	}

	/** `elementor/preview/enqueue_styles`: las 24 variables de la biblioteca, una vez, sólo en la vista previa. */
	public static function preview_styles(): void {
		if ( ! function_exists( 'wp_register_style' ) ) {
			return;
		}
		wp_register_style( 'digitalisimo-design-library', false, array(), defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false );
		wp_enqueue_style( 'digitalisimo-design-library' );
		wp_add_inline_style( 'digitalisimo-design-library', self::root_css() );
	}

	/** `:root{…}` con todas las variables de la biblioteca. */
	public static function root_css(): string {
		$rules = array();
		foreach ( array( 'masks', 'patterns' ) as $kind ) {
			foreach ( self::slugs( $kind ) as $slug ) {
				$uri = 'masks' === $kind ? self::mask( $slug ) : self::tile( $slug );
				if ( null !== $uri ) {
					$rules[] = self::variable( $kind, $slug ) . ':' . $uri;
				}
			}
		}
		return $rules ? ':root{' . implode( ';', $rules ) . '}' : '';
	}

	// ── SVG del sitio ──────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * `elementor/document/save/data`: para cada máscara o patrón con `media`, copia saneado el adjunto elegido y anota
	 * la ruta en su control oculto. Un adjunto que no es un SVG de este sitio (un ID que un kit dejó sin traducir)
	 * conserva la copia que ya hubiera; uno hostil la vacía. Con otra forma, el control oculto se quita.
	 *
	 * @param array $data     Datos del documento.
	 * @param mixed $document Documento.
	 * @return array
	 */
	public static function save_data( $data, $document = null ) {
		if ( ! is_array( $data ) || empty( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return $data;
		}
		if ( is_object( $document ) && method_exists( $document, 'is_editable_by_current_user' ) && ! $document->is_editable_by_current_user() ) {
			return $data;
		}
		$data['elements'] = self::walk( $data['elements'] );
		return $data;
	}

	/** Recorre el árbol de elementos. */
	private static function walk( array $elements ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$element['settings'] = self::element_files( $element['settings'] );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk( $element['elements'] );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/** Ajustes de un elemento con sus controles ocultos al día. */
	private static function element_files( array $settings ): array {
		foreach ( self::MEDIA_CONTROLS as $controls ) {
			list( $choice, $media, $file ) = $controls;
			if ( ! isset( $settings[ $choice ] ) || self::MEDIA !== $settings[ $choice ] ) {
				unset( $settings[ $file ] );
				continue;
			}
			$value = isset( $settings[ $media ] ) && is_array( $settings[ $media ] ) ? $settings[ $media ] : array();
			$id    = isset( $value['id'] ) && is_numeric( $value['id'] ) ? (int) $value['id'] : 0;
			$url   = isset( $value['url'] ) && is_string( $value['url'] ) ? $value['url'] : '';
			if ( $id <= 0 && '' === $url ) {
				$settings[ $file ] = '';
				continue;
			}
			$relative = $id > 0 ? self::store( $id, $url ) : null;
			if ( null !== $relative ) {
				$settings[ $file ] = $relative;
			}
		}
		return $settings;
	}

	/**
	 * Copia saneada de un adjunto SVG del sitio actual en `uploads/digitalisimo-elements/masks/<sha8>.svg`.
	 *
	 * @param int    $attachment_id Adjunto.
	 * @param string $url           URL guardada en el control: su nombre de archivo debe ser el del adjunto, para que un
	 *                              ID de otro sitio (kit, red) nunca elija otro archivo.
	 * @return string|null Ruta relativa; '' si el SVG no supera el saneado o no se pudo copiar; `null` si no es un SVG
	 *                     de este sitio, o si durante una importación de kits la copia aún no existe (se conserva lo
	 *                     que hubiera: la ruta del kit, que K traduce).
	 */
	public static function store( int $attachment_id, string $url = '' ): ?string {
		if ( $attachment_id <= 0 || ! function_exists( 'get_post' ) || ! function_exists( 'get_attached_file' ) ) {
			return null;
		}
		$post = get_post( $attachment_id );
		if ( ! is_object( $post ) || 'attachment' !== $post->post_type || 'image/svg+xml' !== get_post_mime_type( $post ) ) {
			return null;
		}
		$uploads = self::uploads_dir();
		$file    = get_attached_file( $attachment_id );
		$real    = is_string( $file ) && '' !== $file ? realpath( $file ) : false;
		if ( '' === $uploads || false === $real || 0 !== strpos( self::slash( $real ), $uploads . '/' ) || ! is_file( $real ) ) {
			return null;
		}
		if ( '' !== $url ) {
			$path = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url, PHP_URL_PATH ) : parse_url( $url, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			if ( ! is_string( $path ) || basename( $path ) !== basename( $real ) ) {
				return null;
			}
		}
		$bytes = filesize( $real );
		if ( false === $bytes || $bytes > Css::SVG_RAW_MAX ) {
			return '';
		}
		$clean = Css::svg_clean( (string) file_get_contents( $real ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return null === $clean ? '' : self::write( $clean );
	}

	/**
	 * Escribe la copia (o reutiliza la que ya hay con el mismo contenido) y devuelve su ruta relativa, o ''. Durante
	 * una importación de kits sólo reutiliza: sin copia, `null` (la escribe la parte `files` de K, que la revierte).
	 */
	private static function write( string $clean ): ?string {
		$importing = self::importing_kit();
		$dir       = self::folder( ! $importing );
		if ( '' === $dir ) {
			return $importing ? null : '';
		}
		$hash = hash( 'sha256', $clean );
		foreach ( array( 8, 16, 64 ) as $length ) {
			$name   = substr( $hash, 0, $length );
			$target = $dir . $name . '.svg';
			if ( is_file( $target ) ) {
				if ( hash_equals( $hash, (string) hash_file( 'sha256', $target ) ) ) {
					return self::FOLDER . '/' . $name . '.svg';
				}
				continue;
			}
			if ( $importing ) {
				return null;
			}
			return false !== file_put_contents( $target, $clean, LOCK_EX ) ? self::FOLDER . '/' . $name . '.svg' : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $importing ? null : '';
	}

	/** Hay una importación de kits en curso (K activo en esta petición, por la App, la pantalla «Kits» o WP-CLI). */
	private static function importing_kit(): bool {
		$context = '\\Digitalisimo\\Elements\\Import\\Kits\\Context';
		return class_exists( $context, false ) && method_exists( $context, 'active' ) && (bool) $context::active();
	}

	/**
	 * `[ 'name' => <nombre sin .svg>, 'uri' => url("data:…") ]` de una copia, o `null` si la ruta no es una copia válida
	 * de este sitio o el archivo ya no supera el saneado.
	 *
	 * @param mixed $relative Ruta relativa guardada.
	 */
	public static function file_uri( $relative ): ?array {
		if ( ! is_string( $relative ) || 1 !== preg_match( self::FILE_PATTERN, $relative, $match ) ) {
			return null;
		}
		if ( array_key_exists( $relative, self::$files ) ) {
			return self::$files[ $relative ];
		}
		$dir  = self::folder( false );
		$real = '' === $dir ? false : realpath( $dir . $match[1] . '.svg' );
		$out  = null;
		if ( false !== $real && 0 === strpos( self::slash( $real ), $dir ) && is_file( $real ) ) {
			$bytes = filesize( $real );
			$uri   = false === $bytes || $bytes > Css::SVG_RAW_MAX ? null : Css::svg_data_uri( (string) file_get_contents( $real ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$out   = null === $uri ? null : array(
				'name' => $match[1],
				'uri'  => $uri,
			);
		}
		self::$files[ $relative ] = $out;
		return $out;
	}

	/** Carpeta real de `uploads` del sitio actual, sin barra final, o ''. */
	private static function uploads_dir(): string {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		$real = realpath( (string) $uploads['basedir'] );
		return false === $real ? '' : rtrim( self::slash( $real ), '/' );
	}

	/** Carpeta real de las copias, con barra final, comprobada dentro de `uploads`; la crea si se pide. */
	private static function folder( bool $create ): string {
		$uploads = self::uploads_dir();
		if ( '' === $uploads ) {
			return '';
		}
		$path = $uploads . '/' . self::FOLDER;
		if ( $create && ! is_dir( $path ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $path );
			} else {
				@mkdir( $path, 0755, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			}
		}
		$real = realpath( $path );
		if ( false === $real || self::slash( $real ) !== $path || is_link( $path ) ) {
			return '';
		}
		return $path . '/';
	}

	/** Barras normalizadas. */
	private static function slash( string $path ): string {
		return str_replace( '\\', '/', $path );
	}

	/** Olvida la caché de la petición (pruebas). */
	public static function reset(): void {
		self::$files = array();
		self::$data  = null;
	}
}
