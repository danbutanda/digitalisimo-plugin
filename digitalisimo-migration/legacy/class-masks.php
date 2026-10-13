<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';

/**
 * Máscaras de imagen y separadores de forma que Element Pack añadía a Elementor.
 *
 * Element Pack registraba sus figuras (`shape-1`…`shape-31` en la máscara nativa y `ep-*` en el
 * separador de forma) y copiaba los SVG dentro de la carpeta de Elementor; el contenido sólo guarda el
 * nombre. Además, «Image Masking» enmascaraba la imagen de Caja de imagen y Carrusel de imágenes, y sus
 * widgets con máscara la aplicaban a su propia imagen. Sin Element Pack los nombres se quedan sin
 * archivo: una máscara cuyo SVG no carga oculta la imagen entera y un separador sin archivo no se pinta.
 *
 * Aquí se copian (saneados) los SVG que el contenido usa desde la instalación de Element Pack, o desde
 * las copias que dejó en Elementor, a `uploads/digitalisimo-elements/` del sitio. Mientras dure la
 * migración se pintan desde esa copia; al convertir, la máscara nativa pasa a «Personalizada» con esa
 * imagen, la de los widgets va al CSS personalizado del elemento y el separador pasa al separador propio
 * del sitio que registra DIGITALÍSIMO Elements (`digi-*`).
 */
final class Masks {
	const UPLOADS = 'digitalisimo-elements';

	/** Widgets nativos con «Image Masking»: imagen enmascarada y caja de la superposición de color ('' = el widget). */
	const NATIVE = array(
		'image'          => array( 'img', '' ),
		'image-box'      => array( '.elementor-image-box-img img', '.elementor-image-box-img' ),
		'image-carousel' => array( '.swiper-slide-inner img', '.swiper-slide-inner' ),
	);

	/** Imagen atómica (editor v4): Element Pack aplicaba su máscara con estilos en línea al pintar; no se traslada. */
	const ATOMIC = 'e-image';

	/** Imagen equivalente a la que Element Pack enmascaraba, en cada widget de destino. */
	const TARGETS = array(
		'digitalisimo-custom-gallery' => '.digi-custom-gallery__image',
		'digitalisimo-fancy-card'     => '.digi-fancy-card__image',
		'digitalisimo-fancy-slider'   => '.digi-fancy-slider__image',
		'digitalisimo-featured-box'   => '.digi-featured-box__image',
		'digitalisimo-logo-carousel'  => '.digi-logo-carousel__image',
		'digitalisimo-logo-grid'      => '.digi-logo-grid__image',
		'digitalisimo-product-grid'   => '.digi-product-grid__image',
		'image-gallery'               => '.gallery-item img',
		'media-carousel'              => '.elementor-carousel-image',
		'posts'                       => '.elementor-post__thumbnail img',
		'reviews'                     => '.elementor-testimonial__image img',
	);

	/** Separadores de Element Pack → nombre del separador propio (`digi-<nombre>`). */
	const DIVIDERS = array(
		'audio-waveform'  => 'onda-sonora',
		'christmas'       => 'navidad',
		'christmas-santa' => 'papa-noel',
		'festival'        => 'festival',
		'helloween'       => 'halloween',
		'hill'            => 'colinas',
		'mosque'          => 'mezquita',
		'water-splash'    => 'salpicadura',
	);

	const POSITIONS = array(
		'center-center' => 'center center',
		'center-left'   => 'center left',
		'center-right'  => 'center right',
		'top-center'    => 'top center',
		'top-left'      => 'top left',
		'top-right'     => 'top right',
		'bottom-center' => 'bottom center',
		'bottom-left'   => 'bottom left',
		'bottom-right'  => 'bottom right',
	);

	const REPEATS = array( 'repeat', 'repeat-x', 'repeat-y', 'space', 'round', 'no-repeat', 'repeat-space', 'round-space', 'no-repeat-round' );

	/** Ajustes originales de los widgets adaptados, por ID: el adaptador sólo conserva los traducidos. */
	private static $raw = array();

	public static function init() {
		if ( Translator::element_pack_active() ) {
			return;
		}
		add_filter( 'elementor/shapes/additional_shapes', array( __CLASS__, 'legacy_dividers' ) );
		add_action( 'elementor/element/parse_css', array( __CLASS__, 'parse_css' ), 10, 2 );
	}

	/* ---------------------------------------------------------------- Archivos */

	/** Carpeta y URL propias del sitio actual para un tipo de figura (`masks` o `shapes`). */
	private static function place( $kind ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		return array(
			'dir' => trailingslashit( $uploads['basedir'] ) . self::UPLOADS . '/' . $kind . '/',
			'url' => trailingslashit( $uploads['baseurl'] ) . self::UPLOADS . '/' . $kind . '/',
		);
	}

	/** Archivos de Element Pack (completo o lite) y las copias que dejó en Elementor (plugin o núcleo incluido). */
	private static function sources( $pack, $core ) {
		$files = glob( WP_PLUGIN_DIR . '/bdthemes-element-pack*/assets/images/' . $pack ) ?: array();
		$dirs  = array( defined( 'ELEMENTOR_PATH' ) ? (string) ELEMENTOR_PATH : '', WP_PLUGIN_DIR . '/elementor/' );
		foreach ( array_unique( array_filter( array_map( 'trailingslashit', array_filter( $dirs ) ) ) ) as $dir ) {
			$files[] = $dir . 'assets/' . $core;
		}
		return $files;
	}

	private static function sanitize( $svg ) {
		if ( class_exists( '\\Elementor\\Core\\Utils\\Svg\\Svg_Sanitizer' ) ) {
			$clean = ( new \Elementor\Core\Utils\Svg\Svg_Sanitizer() )->sanitize( $svg );
			return is_string( $clean ) ? $clean : '';
		}
		// Sin el saneador de Elementor sólo se acepta un SVG sin scripts ni manejadores de eventos.
		return false !== stripos( $svg, '<svg' ) && ! preg_match( '/<script|\son[a-z]+\s*=|javascript:/i', $svg ) ? $svg : '';
	}

	/**
	 * URL de la copia propia (la crea la primera vez desde el primer origen legible), o null. Con
	 * `$write` en false sólo comprueba que exista la copia o algún origen, sin escribir nada.
	 */
	private static function store( $kind, $file, array $sources, $write = true ) {
		$place = self::place( $kind );
		if ( ! $place ) {
			return null;
		}
		if ( is_file( $place['dir'] . $file ) ) {
			return $place['url'] . $file;
		}
		foreach ( $sources as $source ) {
			if ( ! is_file( $source ) || ! is_readable( $source ) ) {
				continue;
			}
			if ( ! $write ) {
				return $place['url'] . $file;
			}
			$svg = self::sanitize( (string) file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local.
			if ( '' === $svg || ! wp_mkdir_p( $place['dir'] ) ) {
				continue;
			}
			if ( false !== file_put_contents( $place['dir'] . $file, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- copia en los archivos del sitio.
				return $place['url'] . $file;
			}
		}
		return null;
	}

	/** URL de la copia propia de una máscara (`shape-7`, `color-shape-21`), o null si no hay archivo. */
	public static function mask( $name, $write = true ) {
		if ( ! preg_match( '/^(color-)?shape-(\d+)$/', (string) $name, $m ) ) {
			return null;
		}
		$file = 'mask-' . $m[2] . ( '' !== $m[1] ? '-color' : '' ) . '.svg';
		return self::store( 'masks', $file, self::sources( 'mask/' . $name . '.svg', 'mask-shapes/' . $name . '.svg' ), $write );
	}

	/** Nombre propio (`digi-*`) de un separador de Element Pack, con su copia hecha, o null si no hay archivo. */
	public static function divider( $name, $write = true ) {
		if ( ! preg_match( '/^ep-([a-z0-9-]+)$/', (string) $name, $m ) ) {
			return null;
		}
		$slug = self::DIVIDERS[ $m[1] ] ?? $m[1];
		if ( null === self::store( 'shapes', $slug . '.svg', self::sources( 'shape-divider/' . $m[1] . '.svg', 'shapes/ep-' . $m[1] . '.svg' ), $write ) ) {
			return null;
		}
		if ( $write ) {
			self::store( 'shapes', $slug . '-negative.svg', self::sources( 'shape-divider/' . $m[1] . '-negative.svg', 'shapes/ep-' . $m[1] . '-negative.svg' ) );
		}
		return 'digi-' . $slug;
	}

	/** URL pública de un archivo de la carpeta de plugins o de los archivos del sitio, o '' si no se conoce. */
	private static function url_of( $file ) {
		$plugins = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
		$file    = wp_normalize_path( $file );
		if ( 0 === strpos( $file, $plugins ) ) {
			return plugins_url( substr( $file, strlen( $plugins ) ) );
		}
		$uploads = wp_upload_dir( null, false );
		$base    = trailingslashit( wp_normalize_path( (string) ( $uploads['basedir'] ?? '' ) ) );
		return '/' !== $base && 0 === strpos( $file, $base ) ? trailingslashit( (string) $uploads['baseurl'] ) . substr( $file, strlen( $base ) ) : '';
	}

	/** Mientras dura la migración los separadores `ep-*` se siguen pintando: desde la copia propia o desde el original. */
	public static function legacy_dividers( $shapes ) {
		$place = self::place( 'shapes' );
		foreach ( self::DIVIDERS as $name => $slug ) {
			$files = array();
			foreach ( array( '', '-negative' ) as $variant ) {
				$own = $place ? array( $place['dir'] . $slug . $variant . '.svg' ) : array();
				foreach ( array_merge( $own, self::sources( 'shape-divider/' . $name . $variant . '.svg', 'shapes/ep-' . $name . $variant . '.svg' ) ) as $file ) {
					if ( is_file( $file ) ) {
						$files[ $variant ] = $file;
						break;
					}
				}
			}
			if ( ! isset( $files[''] ) ) {
				continue;
			}
			// Elementor busca la versión negativa junto a la principal: sólo se ofrece si están en la misma carpeta.
			$negative = isset( $files['-negative'] ) && dirname( $files['-negative'] ) === dirname( $files[''] ) && basename( $files['-negative'], '.svg' ) === basename( $files[''], '.svg' ) . '-negative';
			$shapes[ 'ep-' . $name ] = array(
				'title'        => ucfirst( str_replace( '-', ' ', $slug ) ) . ' (Element Pack)',
				'path'         => $files[''],
				'url'          => self::url_of( $files[''] ),
				'has_flip'     => true,
				'has_negative' => $negative,
			);
		}
		return (array) $shapes;
	}

	/* ---------------------------------------------------------------- CSS */

	/** Consultas de medios de Elementor por sufijo de dispositivo, de mayor a menor alcance. */
	private static function media_queries() {
		$queries = array();
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) ) {
			$max = array();
			$min = array();
			foreach ( (array) \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() as $name => $breakpoint ) {
				if ( 'min' === $breakpoint->get_direction() ) {
					$min[ $name ] = (int) $breakpoint->get_value();
				} else {
					$max[ $name ] = (int) $breakpoint->get_value();
				}
			}
			arsort( $max );
			foreach ( $max as $name => $value ) {
				$queries[ '_' . $name ] = '@media(max-width:' . $value . 'px)';
			}
			foreach ( $min as $name => $value ) {
				$queries[ '_' . $name ] = '@media(min-width:' . $value . 'px)';
			}
		}
		return $queries;
	}

	private static function size( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['size'] ) || '' === $value['size'] || ! is_numeric( $value['size'] ) ) {
			return '';
		}
		$unit = in_array( $value['unit'] ?? 'px', array( 'px', 'em', '%', 'vw' ), true ) ? ( $value['unit'] ?? 'px' ) : 'px';
		return ( 0 + $value['size'] ) . $unit;
	}

	/**
	 * CSS de «Image Masking» con los valores de Element Pack (y sus valores por defecto).
	 *
	 * @param array       $s     Ajustes originales del elemento.
	 * @param string      $image Imagen enmascarada, relativa al elemento ('img', '.clase img'…).
	 * @param string|null $box   Caja de la superposición de color ('' = el propio elemento); null sin superposición.
	 * @return string CSS con `selector` en lugar del elemento.
	 */
	public static function css( array $s, $image, $box = null ) {
		if ( 'yes' !== ( $s['image_mask_popover'] ?? '' ) ) {
			return '';
		}
		$overlay = null;
		if ( 'custom' === ( $s['image_mask_shape'] ?? 'default' ) ) {
			$url = (string) ( $s['image_mask_shape_custom']['url'] ?? '' );
		} else {
			$shape = (string) ( $s['image_mask_shape_default'] ?? 'shape-1' );
			$url   = (string) self::mask( '' !== $shape ? $shape : 'shape-1' );
			// Las figuras con versión en color la superponían a la imagen (sólo en los widgets nativos).
			$overlay = null !== $box ? self::mask( 'color-' . $shape ) : null;
		}
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return '';
		}
		$target   = 'selector' . ( '' !== $image ? ' ' . $image : '' );
		$position = self::POSITIONS[ (string) ( $s['image_mask_shape_position'] ?? 'center-center' ) ] ?? 'center center';
		$repeat   = (string) ( $s['image_mask_shape_repeat'] ?? 'no-repeat' );
		$repeat   = in_array( $repeat, self::REPEATS, true ) ? str_replace( '-space', ' space', str_replace( '-round', ' round', $repeat ) ) : 'no-repeat';
		$size     = (string) ( $s['image_mask_shape_size'] ?? 'contain' );
		$rules    = '-webkit-mask-image:url("' . $url . '");mask-image:url("' . $url . '");-webkit-mask-position:' . $position . ';mask-position:' . $position . ';-webkit-mask-repeat:' . $repeat . ';mask-repeat:' . $repeat . ';';
		$extra    = '';
		if ( 'initial' === $size ) {
			// Tamaño propio por dispositivo, como el control responsivo de Element Pack (100 % por defecto).
			$base   = self::size( $s['image_mask_shape_custom_size'] ?? array( 'size' => 100, 'unit' => '%' ) );
			$rules .= '' !== $base ? '-webkit-mask-size:' . $base . ';mask-size:' . $base . ';' : '';
			foreach ( self::media_queries() as $suffix => $query ) {
				$value = self::size( $s[ 'image_mask_shape_custom_size' . $suffix ] ?? null );
				if ( '' !== $value ) {
					$extra .= $query . '{' . $target . '{-webkit-mask-size:' . $value . ';mask-size:' . $value . '}}';
				}
			}
		} elseif ( in_array( $size, array( 'auto', 'cover', 'contain' ), true ) ) {
			$rules .= '-webkit-mask-size:' . $size . ';mask-size:' . $size . ';';
		}
		$css = $target . '{' . rtrim( $rules, ';' ) . '}' . $extra;
		if ( $overlay ) {
			$wrap = 'selector' . ( '' !== $box ? ' ' . $box : '' );
			$css .= $wrap . '{position:relative}' . $wrap . '::before{content:"";position:absolute;inset:0;z-index:1;pointer-events:none;background:url("' . esc_url_raw( $overlay ) . '") center center/contain no-repeat}';
		}
		return $css;
	}

	/** Selectores de la máscara nativa de un widget, con `selector` en lugar del elemento. */
	private static function native_mask_selector( $element ) {
		$inner = ! is_object( $element ) || ! method_exists( $element, 'has_widget_inner_wrapper' ) || $element->has_widget_inner_wrapper();
		return $inner
			? 'selector:not(.elementor-widget-image) .elementor-widget-container,selector.elementor-widget-image .elementor-widget-container img'
			: 'selector:not(.elementor-widget-image),selector.elementor-widget-image img';
	}

	/** El adaptador guarda aquí los ajustes que recibió, antes de traducirlos. */
	public static function remember( $id, array $settings ) {
		if ( 'yes' === ( $settings['image_mask_popover'] ?? '' ) ) {
			self::$raw[ (string) $id ] = $settings;
		}
	}

	/** Mientras dura la migración: el CSS que Element Pack generaba para máscaras, con los archivos propios. */
	public static function parse_css( $post_css, $element ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || 'widget' !== $element->get_type() ) {
			return;
		}
		$type     = (string) $element->get_name();
		$settings = self::$raw[ (string) $element->get_id() ] ?? (array) $element->get_data( 'settings' );
		$css      = '';
		if ( ! empty( $settings['_mask_switch'] ) && preg_match( '/^shape-\d+$/', (string) ( $settings['_mask_shape'] ?? '' ) ) ) {
			$url = self::mask( (string) $settings['_mask_shape'] );
			if ( $url ) {
				$css .= self::native_mask_selector( $element ) . '{-webkit-mask-image:url("' . esc_url_raw( $url ) . '")}';
			}
		}
		if ( isset( self::NATIVE[ $type ] ) ) {
			$css .= self::css( $settings, self::NATIVE[ $type ][0], self::NATIVE[ $type ][1] );
		} elseif ( Translator::is_legacy_id( $type ) ) {
			$css .= self::widget_css( Translator::target( $type ), $settings );
		}
		if ( '' !== $css ) {
			$post_css->get_stylesheet()->add_raw_css( str_replace( 'selector', $post_css->get_element_unique_selector( $element ), $css ) );
		}
	}

	/** CSS de la máscara de un widget de Element Pack sobre la imagen de su widget de destino. */
	public static function widget_css( $target, array $settings ) {
		return isset( self::TARGETS[ $target ] ) ? self::css( $settings, self::TARGETS[ $target ] ) : '';
	}

	/* ---------------------------------------------------------------- Conversión */

	/** ¿El elemento usa una figura de Element Pack (máscara nativa, separador o «Image Masking»)? */
	public static function is_legacy( array $element ) {
		$s = (array) ( $element['settings'] ?? array() );
		if ( preg_match( '/^shape-\d+$/', (string) ( $s['_mask_shape'] ?? '' ) ) ) {
			return true;
		}
		foreach ( array( 'top', 'bottom' ) as $side ) {
			if ( 0 === strpos( (string) ( $s[ 'shape_divider_' . $side ] ?? '' ), 'ep-' ) ) {
				return true;
			}
		}
		$type = (string) ( $element['widgetType'] ?? '' );
		if ( isset( self::NATIVE[ $type ] ) || self::ATOMIC === $type ) {
			foreach ( array_keys( $s ) as $key ) {
				if ( 0 === strpos( (string) $key, 'image_mask_' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Figuras usadas que ya no tienen archivo en ningún origen ni copia propia. */
	public static function missing( array $element ) {
		$s       = (array) ( $element['settings'] ?? array() );
		$missing = array();
		$shapes  = array();
		if ( ! empty( $s['_mask_switch'] ) && preg_match( '/^shape-\d+$/', (string) ( $s['_mask_shape'] ?? '' ) ) ) {
			$shapes[] = (string) $s['_mask_shape'];
		}
		if ( isset( self::NATIVE[ (string) ( $element['widgetType'] ?? '' ) ] ) && 'yes' === ( $s['image_mask_popover'] ?? '' ) && 'custom' !== ( $s['image_mask_shape'] ?? 'default' ) ) {
			$shapes[] = (string) ( $s['image_mask_shape_default'] ?? 'shape-1' );
		}
		foreach ( $shapes as $shape ) {
			if ( null === self::mask( $shape, false ) ) {
				$missing[] = $shape;
			}
		}
		foreach ( array( 'top', 'bottom' ) as $side ) {
			$name = (string) ( $s[ 'shape_divider_' . $side ] ?? '' );
			if ( 0 === strpos( $name, 'ep-' ) && null === self::divider( $name, false ) ) {
				$missing[] = $name;
			}
		}
		return $missing;
	}

	/**
	 * Ajustes del elemento con figuras propias: máscara «Personalizada» con la copia, separador `digi-*`
	 * e «Image Masking» en el CSS personalizado. Una figura sin archivo se quita (y se anota) para que la
	 * imagen siga visible en lugar de ocultarse tras una máscara que no carga.
	 *
	 * @param array    $element Elemento (con `settings`, `elType` y `widgetType`).
	 * @param string[] $errors  Avisos de la conversión.
	 */
	public static function convert( array $element, array &$errors ) {
		$s = (array) ( $element['settings'] ?? array() );
		if ( preg_match( '/^shape-\d+$/', (string) ( $s['_mask_shape'] ?? '' ) ) ) {
			$url = ! empty( $s['_mask_switch'] ) ? self::mask( (string) $s['_mask_shape'] ) : null;
			if ( $url ) {
				$s['_mask_shape'] = 'custom';
				$s['_mask_image'] = array( 'url' => $url, 'id' => '', 'size' => '', 'alt' => '', 'source' => 'library' );
			} else {
				if ( ! empty( $s['_mask_switch'] ) ) {
					$errors[] = 'Máscara sin archivo, retirada: ' . $s['_mask_shape'];
					$s['_mask_switch'] = '';
				}
				unset( $s['_mask_shape'] );
			}
		}
		foreach ( array( 'top', 'bottom' ) as $side ) {
			$key  = 'shape_divider_' . $side;
			$name = (string) ( $s[ $key ] ?? '' );
			if ( 0 !== strpos( $name, 'ep-' ) ) {
				continue;
			}
			$own = self::divider( $name );
			if ( null === $own ) {
				$errors[] = 'Separador sin archivo, retirado: ' . $name;
			}
			$s[ $key ] = (string) $own;
		}
		$type = (string) ( $element['widgetType'] ?? '' );
		if ( isset( self::NATIVE[ $type ] ) || self::ATOMIC === $type ) {
			$css = isset( self::NATIVE[ $type ] ) ? self::css( $s, self::NATIVE[ $type ][0], self::NATIVE[ $type ][1] ) : '';
			if ( self::ATOMIC === $type ) {
				$errors[] = 'Máscara de la imagen atómica no trasladada: la imagen se ve sin máscara';
			} elseif ( 'yes' === ( $s['image_mask_popover'] ?? '' ) && '' === $css ) {
				$errors[] = 'Máscara de imagen sin archivo, retirada: ' . ( $s['image_mask_shape_default'] ?? 'shape-1' );
			}
			foreach ( array_keys( $s ) as $key ) {
				if ( 0 === strpos( (string) $key, 'image_mask_' ) ) {
					unset( $s[ $key ] );
				}
			}
			foreach ( array( '__dynamic__', '__globals__' ) as $bag ) {
				if ( isset( $s[ $bag ] ) && is_array( $s[ $bag ] ) ) {
					foreach ( array_keys( $s[ $bag ] ) as $key ) {
						if ( 0 === strpos( (string) $key, 'image_mask_' ) ) {
							unset( $s[ $bag ][ $key ] );
						}
					}
					if ( ! $s[ $bag ] ) {
						unset( $s[ $bag ] );
					}
				}
			}
			if ( '' !== $css ) {
				$s['custom_css'] = trim( ( isset( $s['custom_css'] ) ? (string) $s['custom_css'] . "\n" : '' ) . $css );
			}
		}
		return $s;
	}
}
