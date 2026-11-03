<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * Extensiones de los ajustes del documento (`_elementor_page_settings`) (F8, §3.C.2 y V.9).
 *
 * - grid-line → `custom_css` del documento con el mismo CSS (`body::before`/`::after` y variables propias), los
 *   puntos de corte del sitio y sin líneas al imprimir. En cabecera y pie, como en Element Pack, las variables del
 *   documento no llegan a `body` y se dibujan los valores por defecto en todo el sitio.
 * - custom-js → un borrador de Código personalizado por campo (`Platform\Snippets::draft()`, frente H) con la
 *   condición de ese documento: nunca se ejecuta solo (`ext.review.customjs`). Sólo en documentos singulares (en
 *   plantillas, cabeceras, pies y popups nunca se ejecutaba: `ext.customjs.inert`) y sólo si quien lanzó la tarea
 *   tiene `unfiltered_html` (si no, `ext.customjs.not_created`). En el editor o el análisis no se crea nada.
 * - hash-link, content-protector y adblock-detector (descartes): sus claves se retiran; `Dropped` las informa
 *   leyendo `_digitalisimo_ep_backup_page`.
 *
 * El kit lo trata la plataforma (frente H): aquí se devuelve tal cual. A escribe `_digitalisimo_ep_backup_page`
 * antes de llamar a `convert()`.
 */
final class Page {
	/** Convierte los ajustes de un documento y anota el resultado. */
	public static function convert( array $page_settings, int $post_id ): array {
		list( $settings, $items ) = self::run( $page_settings, $post_id, false );
		$report = 'Digitalisimo\\Elements\\Legacy\\Report';
		if ( $items && class_exists( $report ) ) {
			foreach ( $items as $item ) {
				$report::add( $post_id, $item[0], $item[1] );
			}
		}
		return $settings;
	}

	/** Lo que la conversión anotaría, sin escribir nada: código => recuento. */
	public static function describe( array $page_settings, int $post_id ): array {
		list( , $items ) = self::run( $page_settings, $post_id, true );
		$out = array();
		foreach ( $items as $item ) {
			$out[ $item[0] ] = ( $out[ $item[0] ] ?? 0 ) + 1;
		}
		ksort( $out );
		return $out;
	}

	/** ¿Hay en los ajustes alguna clave de las extensiones de página? */
	public static function detect( array $page_settings ): bool {
		foreach ( Dispatcher::keys() as $spec ) {
			if ( 'page' !== ( $spec['scope'][0] ?? '' ) ) {
				continue;
			}
			foreach ( array_keys( $page_settings ) as $key ) {
				foreach ( (array) $spec['prefixes'] as $prefix ) {
					if ( 0 === strpos( (string) $key, $prefix ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * @return array{0:array,1:array} ajustes y anotaciones [código, detalle].
	 */
	private static function run( array $s, int $post_id, bool $dry ): array {
		$items = array();
		if ( ! self::detect( $s ) || 'kit' === self::template_type( $post_id ) ) {
			return array( $s, $items );
		}
		$context = 'Digitalisimo\\Elements\\Legacy\\Context';
		$pure    = $dry || ( class_exists( $context, false ) && $context::pure() );
		$where   = array( 'element' => __( 'Ajustes de la página', 'digitalisimo-migration' ) );
		$css     = array();
		foreach ( Dispatcher::keys() as $slug => $spec ) {
			if ( 'page' !== ( $spec['scope'][0] ?? '' ) ) {
				continue;
			}
			$present = false;
			$used    = false;
			foreach ( array_keys( $s ) as $key ) {
				foreach ( (array) $spec['prefixes'] as $prefix ) {
					$present = $present || 0 === strpos( (string) $key, $prefix );
				}
			}
			if ( ! $present ) {
				continue;
			}
			foreach ( (array) $spec['switch'] as $switch ) {
				$used = $used || ( array_key_exists( $switch, $s ) && Values::used( $s[ $switch ] ) );
			}
			if ( $used && 'dropped' !== $spec['treatment'] ) {
				if ( ! Activation::enabled( $slug ) ) {
					$items[] = array( 'ext.disabled', $where + array( 'type' => Dispatcher::label( $slug ) ) );
				} elseif ( 'grid-line' === $slug ) {
					$block = self::grid_line( $s );
					if ( '' !== $block && Css_Value::block( $block ) ) {
						$css[]   = $block;
						$items[] = array( 'ext.converted.grid_line', $where );
					} else {
						$items[] = array( 'ext.css_rejected', $where + array( 'type' => Dispatcher::label( $slug ) ) );
					}
				} elseif ( 'custom-js' === $slug ) {
					if ( $pure && ! $dry ) {
						// En el editor nunca se crean fragmentos: los ajustes quedan como estaban para la tarea.
						continue;
					}
					$items = array_merge( $items, self::custom_js( $s, $post_id, $dry ) );
				}
			}
			foreach ( array_keys( $s ) as $key ) {
				foreach ( (array) $spec['prefixes'] as $prefix ) {
					if ( 0 === strpos( (string) $key, $prefix ) ) {
						unset( $s[ $key ] );
					}
				}
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				if ( isset( $s[ $bag ] ) && is_array( $s[ $bag ] ) ) {
					foreach ( array_keys( $s[ $bag ] ) as $key ) {
						foreach ( (array) $spec['prefixes'] as $prefix ) {
							if ( 0 === strpos( (string) $key, $prefix ) ) {
								unset( $s[ $bag ][ $key ] );
							}
						}
					}
					if ( ! $s[ $bag ] ) {
						unset( $s[ $bag ] );
					}
				}
			}
		}
		if ( $css ) {
			$s['custom_css'] = trim( ( isset( $s['custom_css'] ) && is_string( $s['custom_css'] ) && '' !== trim( $s['custom_css'] ) ? $s['custom_css'] . "\n" : '' ) . implode( "\n", $css ) );
		}
		return array( $s, $items );
	}

	/** Tipo de plantilla del documento (`_elementor_template_type`). */
	private static function template_type( int $post_id ): string {
		return $post_id > 0 && function_exists( 'get_metadata' ) ? (string) get_metadata( 'post', $post_id, '_elementor_template_type', true ) : '';
	}

	/* ---------------------------------------------------------------- grid-line */

	/** CSS de las líneas de cuadrícula (`EP/modules/grid-line/module.php:40-286`) con variables propias. */
	public static function grid_line( array $s ): string {
		$vars = array();
		$line = Values::color( $s, 'ep_grid_line_line_color' );
		if ( null !== $line ) {
			$vars[''][] = '--digi-grid-color:' . $line;
		}
		$column = Values::color( $s, 'ep_grid_line_column_color' );
		if ( null !== $column ) {
			$vars[''][] = '--digi-grid-column-color:' . $column;
		}
		$devices = array_intersect( array( '', 'tablet', 'mobile' ), array_merge( array( '' ), array_keys( Values::breakpoints() ) ) );
		foreach ( $devices as $device ) {
			$columns = Css_Value::number( Values::get( $s, Values::device_key( 'ep_grid_line_columns', $device ), '' ), 1, 12 );
			if ( null !== $columns ) {
				$vars[ $device ][] = '--digi-grid-columns:' . (int) $columns;
			}
			$max = Css_Value::size( Values::get( $s, Values::device_key( 'ep_grid_line_max_width', $device ), '' ), array( 'px', 'em', '%' ) );
			if ( null !== $max ) {
				$vars[ $device ][] = '--digi-grid-max-width:' . $max;
			}
			$width = Css_Value::size( Values::get( $s, Values::device_key( 'ep_grid_line_line_width', $device ), '' ), array( 'px', 'em', '%' ) );
			if ( null !== $width ) {
				$vars[ $device ][] = '--digi-grid-width:' . $width;
			}
		}
		$direction = Values::size_of( $s, 'ep_grid_line_direction' );
		if ( null !== $direction && null !== Css_Value::number( $direction, -360, 360 ) ) {
			$vars[''][] = '--digi-grid-direction:' . Css_Value::format( $direction ) . 'deg';
		}
		$z = Css_Value::number( Values::get( $s, 'ep_grid_line_z_index', '' ), -9999, 9999 );
		if ( null !== $z ) {
			$vars[''][] = '--digi-grid-z-index:' . (int) $z;
		}
		$cell = 'calc((100% / var(--digi-grid-columns, 12)) - var(--digi-grid-width, 1px))';
		$full = 'calc(100% / var(--digi-grid-columns, 12))';
		$box  = 'content:"";position:absolute;inset:0;margin-inline:auto;pointer-events:none;z-index:var(--digi-grid-z-index,0);min-height:100vh;width:100%;max-width:var(--digi-grid-max-width,100%);background-size:calc(100% + var(--digi-grid-width,1px)) 100%;';
		$css  = Out::responsive( 'selector', $vars )
			. 'body{position:relative}'
			. 'body::before{' . $box . 'background-image:repeating-linear-gradient(var(--digi-grid-direction,90deg),var(--digi-grid-column-color,transparent),var(--digi-grid-column-color,transparent) ' . $cell . ',var(--digi-grid-color,#eee) ' . $cell . ',var(--digi-grid-color,#eee) ' . $full . ')}';
		if ( 'yes' === Values::get( $s, 'ep_grid_line_outline' ) ) {
			$css .= 'body::before{outline:var(--digi-grid-width,1px) solid var(--digi-grid-color,#eee)}';
		}
		if ( 'yes' === Values::get( $s, 'ep_grid_line_horizontal' ) ) {
			$css .= 'body::after{' . $box . 'background-image:repeating-linear-gradient(0deg,transparent,transparent ' . $cell . ',var(--digi-grid-color,#eee) ' . $cell . ',var(--digi-grid-color,#eee) ' . $full . ')}';
		}
		return $css . '@media print{body::before,body::after{display:none}}';
	}

	/* ---------------------------------------------------------------- custom-js */

	/**
	 * Un borrador de Código personalizado por campo con código, con la condición del documento. Nunca se publica.
	 *
	 * @return array anotaciones.
	 */
	private static function custom_js( array $s, int $post_id, bool $dry ): array {
		$items  = array();
		$fields = array(
			'ep_custom_header_script' => array( 'elementor_head', __( 'cabecera', 'digitalisimo-migration' ) ),
			'ep_custom_footer_script' => array( 'elementor_body_end', __( 'pie', 'digitalisimo-migration' ) ),
		);
		$post      = $post_id > 0 && function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		$post_type = is_object( $post ) ? (string) $post->post_type : '';
		if ( 'revision' === $post_type && function_exists( 'wp_is_post_revision' ) ) {
			$parent    = (int) wp_is_post_revision( $post_id );
			$post      = $parent ? get_post( $parent ) : null;
			$post_type = is_object( $post ) ? (string) $post->post_type : '';
			$post_id   = $parent ? $parent : $post_id;
		}
		$singular = '' !== $post_type && 'elementor_library' !== $post_type && preg_match( '/^[a-z0-9_-]{1,20}$/', $post_type );
		foreach ( $fields as $key => $spec ) {
			$code = isset( $s[ $key ] ) && is_string( $s[ $key ] ) ? trim( $s[ $key ] ) : '';
			if ( '' === $code ) {
				continue;
			}
			$where = array( 'element' => __( 'Ajustes de la página', 'digitalisimo-migration' ), 'name' => $spec[1] );
			if ( ! $singular ) {
				$items[] = array( 'ext.customjs.inert', $where );
				continue;
			}
			if ( $dry ) {
				$items[] = array( 'ext.review.customjs', $where );
				continue;
			}
			$id       = 0;
			$snippets = 'Digitalisimo\\Elements\\Legacy\\Platform\\Snippets';
			if ( class_exists( $snippets ) && method_exists( $snippets, 'draft' ) ) {
				$title = function_exists( 'get_the_title' ) ? wp_strip_all_tags_text( (string) get_the_title( $post_id ) ) : '';
				$title = sprintf( __( 'JavaScript heredado · %1$s (%2$s) · revisar', 'digitalisimo-migration' ), '' !== trim( $title ) ? $title : '#' . $post_id, $spec[1] );
				$id    = (int) $snippets::draft( $title, $code, $spec[0], array( 'include/singular/' . $post_type . '/' . $post_id ), array( 'source' => 'migration', 'kind' => 'custom-js', 'post' => $post_id, 'place' => $spec[0] ) );
			}
			$items[] = $id > 0 ? array( 'ext.review.customjs', $where + array( 'id' => $id ) ) : array( 'ext.customjs.not_created', $where );
		}
		return $items;
	}
}
