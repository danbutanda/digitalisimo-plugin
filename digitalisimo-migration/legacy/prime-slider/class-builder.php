<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

use Digitalisimo\Elements\Legacy\Native;

defined( 'ABSPATH' ) || exit;

/**
 * Piezas de los destinos de Prime Slider (V.10.2, §3.E.2): el slider anidado de F7b con sus contenedores hijos, el
 * respaldo `slides`, los widgets nativos y la lista blanca de claves comunes. Lo usan las familias de E
 * (`Static_Families`) y las de F (`woohotspot`, contrato C15). Ninguna pieza escribe nada: sólo construye arrays.
 *
 * - **IDs** deterministas (`id()`): siete cifras hexadecimales derivadas del ID del widget original y de un contador,
 *   así que convertir dos veces da lo mismo y dos widgets del mismo documento nunca chocan. El elemento principal
 *   conserva el ID del original (sus anclas y su CSS siguen valiendo).
 * - **Contenedores hijos** con `isInner: true` y, dentro del slider, `isLocked: true`: es lo que guarda el editor
 *   (`ELE/elementor/assets/js/editor.js:17720-17721` marca `isInner` en el contenedor de un elemento anidado, y
 *   `ELE/elementor/assets/js/nested-elements.js:392-397,665-670` crea los hijos con `isLocked`). Sin `isInner`, el
 *   núcleo pinta `e-parent` en lugar de `e-child` (`ELE/elementor/includes/elements/container.php:121`).
 * - **Lista blanca** (`common()`): sólo las claves de `COMMON`/`COMMON_EXACT` del widget original (con sus variantes
 *   responsivas y sus `__globals__`/`__dynamic__`); `_element_id` y `_css_classes` sólo en el principal; nunca `_skin`,
 *   `_title` ni `_digitalisimo_legacy` (C1-19).
 */
final class Builder {
	const NESTED   = 'digitalisimo-nested-slider';
	const FALLBACK = 'slides';

	/** Prefijos de la lista blanca de claves comunes (V.10.2). */
	const COMMON = '/^(?:_margin|_padding|_z_index|_element_width|_element_custom_width|_flex_|_offset_|_background_|_border_|_box_shadow_|_animation|_transform_|_mask_|motion_fx_|sticky)/';

	/** Claves exactas de la lista blanca. */
	const COMMON_EXACT = array( '_position', 'animation_duration', 'hide_desktop', 'hide_tablet', 'hide_mobile', '_attributes', 'custom_css' );

	/** Sólo en el elemento principal de un fragmento. */
	const MAIN_ONLY = array( '_element_id', '_css_classes' );

	/** Nunca, ni aunque casen con un prefijo. */
	const NEVER = array( '_skin', '_title', '_digitalisimo_legacy' );

	/** @var array<string, bool> Disponibilidad simulada (sólo pruebas): `widget` o `container` => bool. */
	public static $simulate = array();

	/** @var string */
	private static $seed = 'x';
	/** @var int */
	private static $count = 0;

	/** Empieza los IDs de un widget original. */
	public static function seed( string $seed ): void {
		self::$seed  = '' !== $seed ? $seed : 'x';
		self::$count = 0;
	}

	/** ID nuevo, determinista. */
	public static function id(): string {
		return substr( md5( self::$seed . '|' . ( ++self::$count ) ), 0, 7 );
	}

	/* ---------------------------------------------------------------- Disponibilidad */

	/**
	 * ¿Puede usarse el destino en este sitio? true/false, o null si no se puede saber (sin Elementor cargado: las
	 * pruebas; cuenta como disponible). `container` es el experimento de contenedores; el slider anidado exige además
	 * `F7b\Nested\Module::ready()` (el experimento de anidados y la base del núcleo).
	 */
	public static function ready( string $name ): ?bool {
		if ( array_key_exists( $name, self::$simulate ) ) {
			return (bool) self::$simulate[ $name ];
		}
		if ( 'container' === $name ) {
			return self::feature( 'container' );
		}
		if ( self::NESTED === $name ) {
			$containers = self::ready( 'container' );
			if ( false === $containers ) {
				return false;
			}
			$module = '\\Digitalisimo\\Elements\\F7b\\Nested\\Module';
			if ( class_exists( $module ) && method_exists( $module, 'ready' ) && ! $module::ready() ) {
				return false;
			}
		}
		if ( class_exists( Native::class, false ) && method_exists( Native::class, 'target_ready' ) ) {
			return Native::target_ready( $name );
		}
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance->widgets_manager ) || ! is_object( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return null;
		}
		return (bool) \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $name );
	}

	/** ¿Disponible o no se sabe? */
	public static function usable( string $name ): bool {
		return false !== self::ready( $name );
	}

	/** Experimento del núcleo (null sin el gestor de experimentos). */
	private static function feature( string $feature ): ?bool {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return null;
		}
		$experiments = \Elementor\Plugin::$instance->experiments ?? null;
		if ( ! is_object( $experiments ) || ! method_exists( $experiments, 'is_feature_active' ) ) {
			return null;
		}
		return (bool) $experiments->is_feature_active( $feature );
	}

	/* ---------------------------------------------------------------- Piezas */

	/** Slider anidado con un contenedor hijo por diapositiva (y el mismo número de filas en `slides`). */
	public static function nested_slider( array $settings, array $children ): array {
		$rows     = array();
		$elements = array();
		$i        = 0;
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			++$i;
			$child['elType']   = 'container';
			$child['isInner']  = true;
			$child['isLocked'] = true;
			if ( empty( $child['settings']['_title'] ) ) {
				$child['settings']['_title'] = self::slide_title( $i );
			}
			$elements[] = $child;
			$title      = isset( $settings['slides'][ $i - 1 ]['slide_title'] ) ? (string) $settings['slides'][ $i - 1 ]['slide_title'] : '';
			$rows[]     = array(
				'_id'         => self::id(),
				'slide_title' => '' !== trim( $title ) ? $title : self::slide_title( $i ),
			);
		}
		$settings['slides'] = $rows;
		return array(
			'id'         => self::id(),
			'elType'     => 'widget',
			'widgetType' => self::NESTED,
			'settings'   => $settings,
			'elements'   => $elements,
		);
	}

	/** «Diapositiva n.º i» (traducible). */
	public static function slide_title( int $i ): string {
		/* translators: %d: número de la diapositiva. */
		return sprintf( __( 'Diapositiva n.º %d', 'digitalisimo-migration' ), $i );
	}

	/** Contenedor anidado (`isInner`) con sus widgets. */
	public static function container( array $settings, array $widgets ): array {
		return array(
			'id'       => self::id(),
			'elType'   => 'container',
			'settings' => $settings,
			'elements' => array_values( array_filter( $widgets, 'is_array' ) ),
			'isInner'  => true,
		);
	}

	/** Widget nativo o propio. */
	public static function widget( string $type, array $settings ): array {
		return array(
			'id'         => self::id(),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * Claves comunes del widget original que pasan al destino (lista blanca, V.10.2).
	 *
	 * @param array $from Ajustes guardados del widget original.
	 * @param bool  $main Elemento principal del fragmento (`_element_id` y `_css_classes` sólo en él).
	 */
	public static function common( array $from, bool $main ): array {
		$out = array();
		foreach ( $from as $key => $value ) {
			$key = (string) $key;
			if ( self::is_common( $key, $main ) ) {
				$out[ $key ] = $value;
			}
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			if ( empty( $from[ $bag ] ) || ! is_array( $from[ $bag ] ) ) {
				continue;
			}
			$kept = array();
			foreach ( $from[ $bag ] as $key => $value ) {
				if ( self::is_common( (string) $key, $main ) ) {
					$kept[ $key ] = $value;
				}
			}
			if ( $kept ) {
				$out[ $bag ] = $kept;
			}
		}
		return $out;
	}

	/** ¿Clave de la lista blanca (con su variante responsiva)? */
	public static function is_common( string $key, bool $main = true ): bool {
		if ( in_array( $key, self::NEVER, true ) ) {
			return false;
		}
		if ( in_array( $key, self::MAIN_ONLY, true ) ) {
			return $main;
		}
		if ( 1 === preg_match( self::COMMON, $key ) ) {
			return true;
		}
		return in_array( $key, self::COMMON_EXACT, true );
	}

	/**
	 * Respaldo `slides` de PRO (con plantillas por el rasgo `digitalisimo_template_id`) donde los contenedores o los
	 * elementos anidados no están activos.
	 *
	 * @param array $settings Ajustes del slider ya calculados (`navigation`, `autoplay`, `autoplay_delay`, `loop`,
	 *                        `pause_on_hover`, `effect`, `speed`, `slider_height*`, `title_tag`, `align`).
	 * @param array $rows     Filas normalizadas (`sub_title`, `title`, `text`, `button_text`, `button_link`, `image`,
	 *                        `color`, `kenburns`, `overlay`, `overlay_color`, `template_id`).
	 */
	public static function slides_fallback( array $settings, array $rows ): array {
		$slides    = array();
		$templates = false;
		foreach ( $rows as $row ) {
			$image = is_array( $row['image'] ?? null ) ? $row['image'] : array();
			$link  = is_array( $row['button_link'] ?? null ) ? $row['button_link'] : array( 'url' => '' );
			$text  = '' !== trim( (string) ( $row['sub_title'] ?? '' ) ) ? '<p>' . esc_html( (string) $row['sub_title'] ) . '</p>' : '';
			$text .= (string) ( $row['text'] ?? '' );
			$slide = array(
				'_id'              => self::id(),
				'heading'          => (string) ( $row['title'] ?? '' ),
				'description'      => $text,
				'button_text'      => '' !== trim( (string) ( $row['button_text'] ?? '' ) ) && ! empty( $link['url'] ) ? (string) $row['button_text'] : '',
				'link'             => ! empty( $link['url'] ) ? $link : array( 'url' => '' ),
				'link_click'       => 'button',
				'background_color' => (string) ( $row['color'] ?? '' ),
				'background_image' => ! empty( $image['url'] ) ? array( 'id' => $image['id'] ?? '', 'url' => $image['url'] ) : array( 'url' => '' ),
				'background_size'  => 'cover',
			);
			if ( ! empty( $row['kenburns'] ) && ! empty( $image['url'] ) ) {
				$slide['background_ken_burns'] = 'yes';
				$slide['zoom_direction']       = 'out' === ( $row['kenburns'] ?? '' ) ? 'out' : 'in';
			}
			if ( ! empty( $row['overlay_color'] ) ) {
				$slide['background_overlay']       = 'yes';
				$slide['background_overlay_color'] = (string) $row['overlay_color'];
			}
			if ( ! empty( $row['template_id'] ) ) {
				$slide['digitalisimo_template_id'] = absint( $row['template_id'] );
				$slide['description']              = '';
				$templates                         = true;
			}
			$slides[] = $slide;
		}
		$navigation = (string) ( $settings['navigation'] ?? 'both' );
		$out        = array(
			'slides'                 => $slides,
			'navigation'             => in_array( $navigation, array( 'both', 'arrows', 'dots' ), true ) ? $navigation : 'dots',
			'autoplay'               => 'yes' === ( $settings['autoplay'] ?? '' ) ? 'yes' : '',
			'autoplay_speed'         => (int) ( $settings['autoplay_delay'] ?? 5000 ),
			'pause_on_hover'         => 'yes' === ( $settings['pause_on_hover'] ?? '' ) ? 'yes' : '',
			'infinite'               => 'yes' === ( $settings['loop'] ?? '' ) ? 'yes' : '',
			'transition'             => 'fade' === ( $settings['effect'] ?? '' ) || 'scale' === ( $settings['effect'] ?? '' ) ? 'fade' : 'slide',
			'slides_title_tag'       => (string) ( $settings['title_tag'] ?? 'h2' ),
			'slides_description_tag' => 'div',
			'content_animation'      => '',
			'slides_text_align'      => array( 'left' => 'left', 'right' => 'right' )[ (string) ( $settings['align'] ?? '' ) ] ?? 'center',
		);
		if ( isset( $settings['speed'] ) && '' !== (string) $settings['speed'] ) {
			$out['transition_speed'] = (int) $settings['speed'];
		}
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			if ( isset( $settings[ 'slider_height' . $device ] ) ) {
				$out[ 'slides_height' . $device ] = $settings[ 'slider_height' . $device ];
			}
		}
		if ( $templates ) {
			$out['digitalisimo_legacy_templates'] = 'yes';
		}
		return self::widget( self::FALLBACK, $out );
	}

	/** Para las pruebas: sin simulaciones y con la semilla inicial. */
	public static function reset(): void {
		self::$simulate = array();
		self::seed( 'x' );
	}
}
