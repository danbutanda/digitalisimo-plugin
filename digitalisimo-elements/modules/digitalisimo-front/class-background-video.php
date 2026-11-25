<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Vídeos de fondo de contenedores y secciones: peso y celular (auditoría de digitalisimo.lat, 2026-11-24: la cabecera
 * cargaba un MP4 de 8,1 MB también en el celular, con «Reproducir en móvil» encendido; la portada pesaba 9,5 MB y sacaba
 * 50/100 en Lighthouse móvil). Nada de `elementor/` se edita.
 *
 * Cómo lo hace hoy el núcleo (verificado en su código): el `<video class="elementor-background-video-hosted">` sale sin
 * `src`, y el manejador `background-video` se lo pone al cargar la página salvo que el elemento no tenga «Reproducir en
 * móvil» (`background_play_on_mobile`, apagado por defecto) y el punto de corte sea `mobile`; en ese caso el contenedor
 * del vídeo lleva `elementor-hidden-mobile` y se ve la «Imagen de respaldo» (`background_video_fallback`, el fondo del
 * elemento). Es decir: sin `src`, sin descarga y con el póster. Lo que faltaba:
 *
 * 1. **Ajuste del sitio** «Vídeos de fondo en el celular» (pantalla «Rendimiento», por sitio y por red): `element`
 *    (predeterminado: lo decide cada elemento con «Reproducir en móvil», como siempre: no cambia nada en ningún sitio),
 *    `never` (nunca en el celular, aunque el elemento lo pida: sin `src` ni descarga, con su imagen de respaldo) y
 *    `always` (también en el celular, aunque el elemento no lo pida). Se aplica en el front (nunca en el editor ni en su
 *    vista previa) antes de que el elemento calcule su marcado, con `set_settings( 'background_play_on_mobile' )`, así
 *    que la clase `elementor-hidden-mobile` y los `data-settings` que lee el manejador salen ya con lo decidido. Cambiarlo
 *    vacía los archivos generados y la caché de elementos (`files_manager->clear_cache()`).
 * 2. **Aviso de peso en el editor**: al abrir el fondo de un contenedor o sección con un vídeo alojado en el sitio, el
 *    panel pide su tamaño (`HEAD`, mismo origen) y, si pasa del umbral del sitio (por defecto 2 MB), lo dice con el peso,
 *    si se descarga también en el celular y si falta la imagen de respaldo (`assets/background-video-weight.js`). Es
 *    sólo un aviso: no cambia ni guarda nada en el elemento.
 *
 * Resolución `sitio → red → predeterminado`, con «Heredar de la red» en cada campo del sitio de una red (por defecto
 * hereda), como el resto de la pantalla.
 */
final class Background_Video {
	const OPTION         = 'digitalisimo_elements_background_video';
	const NETWORK_OPTION = 'digitalisimo_elements_background_video_defaults';
	const FIELDS         = array( 'mobile', 'max_mb' );
	const MODES          = array(
		'element' => 'Lo decide cada elemento («Reproducir en móvil»)',
		'never'   => 'Nunca en el celular: se muestra la imagen de respaldo',
		'always'  => 'También en el celular, en todos los elementos',
	);
	const DEFAULTS       = array(
		'mobile' => 'element',
		'max_mb' => 2.0,
	);
	const MIN_MB         = 0.1;
	const MAX_MB         = 1024.0;
	const HANDLE         = 'digitalisimo-elements-background-video-weight';
	const SCRIPT         = 'modules/digitalisimo-front/assets/background-video-weight.js';

	/** @var bool */
	private static $initialized = false;

	/** @var array|null Valores resueltos de esta petición. */
	private static $values = null;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ), PHP_INT_MIN );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_editor' ) );
		add_action( 'elementor/element/container/section_background/before_section_end', array( __CLASS__, 'add_notice_control' ), 10, 1 );
		add_action( 'elementor/element/section/section_background/before_section_end', array( __CLASS__, 'add_notice_control' ), 10, 1 );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$values      = null;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Front.

	/**
	 * `elementor/frontend/before_render`, primero que nadie: con `never` o `always`, el vídeo de fondo alojado de un
	 * contenedor o sección sigue la política del sitio en el celular. Con `element` (el predeterminado) no hace nada.
	 */
	public static function before_render( $element ): void {
		$mode = self::get( 'mobile' );
		if ( 'element' === $mode || ! self::targets( $element ) || ! Lazy_Videos::front() || Lazy_Videos::editing() ) {
			return;
		}
		$want = 'always' === $mode ? 'yes' : '';
		if ( (string) self::setting( $element, 'background_play_on_mobile' ) === $want ) {
			return;
		}
		$element->set_settings( 'background_play_on_mobile', $want );
		if ( method_exists( $element, 'reset_render_state' ) ) {
			$element->reset_render_state();
		}
	}

	/** ¿Es un contenedor o sección con fondo de vídeo alojado (no YouTube ni Vimeo)? */
	public static function targets( $element ): bool {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || ! method_exists( $element, 'get_settings' ) || ! method_exists( $element, 'set_settings' ) ) {
			return false;
		}
		if ( ! in_array( (string) $element->get_name(), array( 'container', 'section' ), true ) ) {
			return false;
		}
		$link = (string) self::setting( $element, 'background_video_link' );
		return 'video' === self::setting( $element, 'background_background' ) && '' !== $link && ! self::is_embed( $link );
	}

	/**
	 * El valor que pintará el elemento: con las etiquetas dinámicas resueltas (un enlace de vídeo que viene de un campo
	 * personalizado o de la biblioteca), como lo verá el manejador. Tras `set_settings()`, `reset_render_state()` hace
	 * que se vuelva a calcular con lo cambiado.
	 */
	private static function setting( $element, string $key ) {
		$value = method_exists( $element, 'get_settings_for_display' ) ? $element->get_settings_for_display( $key ) : $element->get_settings( $key );
		return is_scalar( $value ) ? $value : '';
	}

	private static function is_embed( string $link ): bool {
		$embed = '\Elementor\Embed';
		if ( class_exists( $embed ) && method_exists( $embed, 'is_embed_video' ) ) {
			return (bool) $embed::is_embed_video( $link );
		}
		return (bool) preg_match( '#(youtube\.com|youtu\.be|vimeo\.com|dailymotion\.com|videopress\.com)#i', $link );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Editor.

	/**
	 * `elementor/element/{container,section}/section_background/before_section_end`: un control informativo (no guarda
	 * nada) donde el script del editor escribe el aviso de peso. Sólo se ve con «Vídeo» como fondo.
	 */
	public static function add_notice_control( $element ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'add_control' ) || ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}
		$element->add_control(
			'digitalisimo_background_video_weight',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => '<div class="digitalisimo-bg-video-weight" role="status" aria-live="polite"></div>',
				'content_classes' => 'digitalisimo-bg-video-weight-control',
				'condition'       => array( 'background_background' => 'video' ),
			)
		);
	}

	/** El script del aviso de peso, con el umbral y la política del sitio. */
	public static function enqueue_editor(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array(), $version, true );
		wp_add_inline_script( self::HANDLE, 'window.digitalisimoBackgroundVideo=' . wp_json_encode( self::editor_config() ) . ';', 'before' );
	}

	/** Lo que necesita el script del editor. */
	public static function editor_config(): array {
		return array(
			'maxBytes' => (int) round( self::get( 'max_mb' ) * 1048576 ),
			'maxMb'    => self::get( 'max_mb' ),
			'mobile'   => self::get( 'mobile' ),
			'origin'   => function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_HOST ) : '',
		);
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Ajustes: sitio → red → predeterminado.

	/** Valor resuelto de un campo (`mobile`: texto de `MODES`; `max_mb`: número). */
	public static function get( string $field ) {
		if ( null === self::$values ) {
			$network = self::network_values();
			$site    = self::stored();
			$values  = $network;
			foreach ( self::FIELDS as $key ) {
				if ( array_key_exists( $key, $site ) && ( ! self::multisite() || in_array( $key, $site['own'], true ) ) ) {
					$values[ $key ] = $site[ $key ];
				}
			}
			self::$values = $values;
		}
		return self::$values[ $field ] ?? ( self::DEFAULTS[ $field ] ?? null );
	}

	/** De dónde sale el valor: `site`, `network` o `default`. */
	public static function origin( string $field ): string {
		$site = self::stored();
		if ( array_key_exists( $field, $site ) && ( ! self::multisite() || in_array( $field, $site['own'], true ) ) ) {
			return 'site';
		}
		return array_key_exists( $field, self::network_stored() ) ? 'network' : 'default';
	}

	public static function inherits_network( string $field ): bool {
		return self::multisite() && ! in_array( $field, self::stored()['own'], true );
	}

	/** Un campo validado, o `null` si no vale (`mobile`: una de `MODES`; `max_mb`: número entre 0,1 y 1024). */
	public static function clean( string $field, $value ) {
		if ( 'mobile' === $field ) {
			return is_string( $value ) && isset( self::MODES[ $value ] ) ? $value : null;
		}
		if ( 'max_mb' === $field ) {
			if ( is_string( $value ) ) {
				$value = str_replace( ',', '.', trim( $value ) );
			}
			if ( ! is_numeric( $value ) ) {
				return null;
			}
			$value = round( (float) $value, 1 );
			return $value >= self::MIN_MB && $value <= self::MAX_MB ? $value : null;
		}
		return null;
	}

	/**
	 * Envío del sitio: cada campo validado (uno inválido conserva el guardado) y `own` (en una red, los campos con
	 * «Heredar de la red» desmarcado; en un sitio único, todos).
	 */
	public static function sanitize( array $input ): array {
		$stored = self::stored();
		$out    = array();
		foreach ( self::FIELDS as $field ) {
			$value = self::clean( $field, $input[ $field ] ?? null );
			if ( null !== $value ) {
				$out[ $field ] = $value;
			} elseif ( array_key_exists( $field, $stored ) ) {
				$out[ $field ] = $stored[ $field ];
			}
		}
		$own = array();
		if ( ! self::multisite() ) {
			$own = self::FIELDS;
		} elseif ( isset( $input['inherit'] ) && is_array( $input['inherit'] ) ) {
			foreach ( self::FIELDS as $field ) {
				if ( array_key_exists( $field, $input['inherit'] ) && empty( $input['inherit'][ $field ] ) ) {
					$own[] = $field;
				}
			}
		}
		$out['own'] = $own;
		return $out;
	}

	/** Guarda el envío del sitio. Devuelve si cambió la política del celular (hay que vaciar cachés). */
	public static function save_site( array $input ): bool {
		$before = self::get( 'mobile' );
		update_option( self::OPTION, self::sanitize( $input ), true );
		self::$values = null;
		return self::get( 'mobile' ) !== $before;
	}

	/** Envío de red: los mismos campos con la misma validación; uno inválido conserva el guardado. */
	public static function save_network( array $input ): array {
		$out = self::network_stored();
		foreach ( self::FIELDS as $field ) {
			$value = self::clean( $field, $input[ $field ] ?? null );
			if ( null !== $value ) {
				$out[ $field ] = $value;
			}
		}
		if ( self::multisite() && function_exists( 'update_site_option' ) ) {
			update_site_option( self::NETWORK_OPTION, $out );
		}
		self::$values = null;
		return $out;
	}

	/** Lo guardado en el sitio, validado, con `own`. */
	public static function stored(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( self::FIELDS as $field ) {
			$value = self::clean( $field, $raw[ $field ] ?? null );
			if ( null !== $value ) {
				$out[ $field ] = $value;
			}
		}
		$out['own'] = isset( $raw['own'] ) && is_array( $raw['own'] ) ? array_values( array_intersect( self::FIELDS, $raw['own'] ) ) : array();
		return $out;
	}

	public static function network_stored(): array {
		if ( ! self::multisite() || ! function_exists( 'get_site_option' ) ) {
			return array();
		}
		$raw = get_site_option( self::NETWORK_OPTION, array() );
		$out = array();
		foreach ( self::FIELDS as $field ) {
			$value = self::clean( $field, is_array( $raw ) ? ( $raw[ $field ] ?? null ) : null );
			if ( null !== $value ) {
				$out[ $field ] = $value;
			}
		}
		return $out;
	}

	public static function network_values(): array {
		return array_merge( self::DEFAULTS, self::network_stored() );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Pantalla «Rendimiento» (la pintan `Settings_Page::render()` y `render_network()` dentro de su formulario).

	/** Sección «Vídeos de fondo»: en el sitio, con «Heredar de la red» en una red; en la red, sin él. */
	public static function render_fields( bool $network ): void {
		$inherit_ui = ! $network && self::multisite();
		$name       = $network ? self::NETWORK_OPTION : self::OPTION;
		$values     = $network ? self::network_values() : array( 'mobile' => self::get( 'mobile' ), 'max_mb' => self::get( 'max_mb' ) );
		echo '<h2 id="digitalisimo-front-background-video">' . esc_html( 'Vídeos de fondo' ) . '</h2>';
		echo '<p>' . esc_html( 'Los vídeos MP4 de fondo de contenedores y secciones son lo que más pesa en una página. En el celular, un vídeo que no se reproduce no se descarga: se ve su «Imagen de respaldo» (en el fondo del elemento).' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$id = 'digitalisimo-front-background-video-mobile';
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( 'Vídeos de fondo en el celular' ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name . '[mobile]' ) . '">';
		foreach ( self::MODES as $mode => $label ) {
			echo '<option value="' . esc_attr( $mode ) . '"' . ( $mode === $values['mobile'] ? ' selected="selected"' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html( '«Nunca en el celular» quita el vídeo en el punto de corte del celular aunque el elemento tenga «Reproducir en móvil»: no se descarga. Pon una «Imagen de respaldo» en cada vídeo de fondo para que el celular muestre algo.' ) . '</p>';
		self::origin_row( 'mobile', $inherit_ui, $network, $id );
		echo '</td></tr>';
		$id = 'digitalisimo-front-background-video-max-mb';
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( 'Avisar en el editor si el vídeo pesa más de (MB)' ) . '</label></th><td>';
		echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name . '[max_mb]' ) . '" value="' . esc_attr( (string) $values['max_mb'] ) . '" min="' . esc_attr( (string) self::MIN_MB ) . '" max="' . esc_attr( (string) self::MAX_MB ) . '" step="0.1" class="small-text">';
		echo '<p class="description">' . esc_html( 'Al elegir un vídeo de fondo alojado en el sitio, el editor muestra su peso y avisa si pasa de este límite. Sólo avisa: no cambia el vídeo.' ) . '</p>';
		self::origin_row( 'max_mb', $inherit_ui, $network, $id );
		echo '</td></tr>';
		echo '</tbody></table>';
	}

	private static function origin_row( string $field, bool $inherit_ui, bool $network, string $id ): void {
		if ( $network ) {
			if ( ! array_key_exists( $field, self::network_stored() ) ) {
				echo '<p class="description digitalisimo-default-note">' . esc_html( 'Valor predeterminado del plugin.' ) . '</p>';
			}
			return;
		}
		$origin = self::origin( $field );
		if ( ! $inherit_ui ) {
			if ( 'default' === $origin ) {
				echo '<p class="description digitalisimo-default-note">' . esc_html( 'Valor predeterminado del plugin.' ) . '</p>';
			}
			return;
		}
		$inherit = self::inherits_network( $field );
		$net     = self::network_values()[ $field ];
		$shown   = 'mobile' === $field ? self::MODES[ $net ] : $net . ' MB';
		$input   = self::OPTION . '[inherit][' . $field . ']';
		echo '<p class="digitalisimo-inheritance"><input type="hidden" name="' . esc_attr( $input ) . '" value="0">';
		echo '<label><input type="checkbox" class="digitalisimo-front-inherit" data-field="' . esc_attr( $id ) . '" name="' . esc_attr( $input ) . '" value="1"' . ( $inherit ? ' checked="checked"' : '' ) . '> ' . esc_html( 'Heredar de la red' ) . '</label><br>';
		echo '<span class="description">' . esc_html( 'Valor de la red: ' . $shown ) . '</span></p>';
	}

	private static function multisite(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}
}
