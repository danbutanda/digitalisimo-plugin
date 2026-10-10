<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Traduce los ajustes que Element Pack guardó para un widget `bdt-*` a los del widget propio.
 *
 * Elementor no guarda los valores que coinciden con el default del control, así que primero se
 * completan los defaults de Element Pack; después se renombran claves (con sus variantes
 * responsivas y dinámicas), se convierten valores y se traducen los repetidores. Las claves
 * comunes de Elementor (márgenes, fondos, visibilidad, CSS propio…) pasan sin cambios.
 */
final class Translator {
	const MARKER  = '_digitalisimo_legacy';
	const DEVICES = array( '', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' );

	/** IDs de Element Pack: todos con prefijo `bdt-` salvo el calendario de FooEvents y Lightbox. */
	const ID_PATTERN = '/^(bdt-[a-z0-9-]+|fooevents-calendar|lightbox)$/';

	private static $maps = array();

	public static function is_legacy_id( $id ) {
		return 1 === preg_match( self::ID_PATTERN, (string) $id );
	}

	/** Element Pack conserva sus IDs mientras esté cargado; ni adaptadores ni migración compiten con él. */
	public static function element_pack_active() {
		return defined( 'BDTEP_VER' ) || class_exists( '\\ElementPack\\Element_Pack_Loader', false );
	}

	/** @return string[] IDs heredados con mapa disponible. */
	public static function ids() {
		$ids = array();
		foreach ( glob( __DIR__ . '/maps/*.php' ) ?: array() as $file ) {
			if ( false === strpos( basename( $file ), '.styles.' ) && self::is_legacy_id( basename( $file, '.php' ) ) ) {
				$ids[] = basename( $file, '.php' );
			}
		}
		sort( $ids );
		return $ids;
	}

	public static function map( $legacy_id ) {
		if ( ! array_key_exists( $legacy_id, self::$maps ) ) {
			$file                     = __DIR__ . '/maps/' . basename( (string) $legacy_id ) . '.php';
			self::$maps[ $legacy_id ] = self::is_legacy_id( $legacy_id ) && is_file( $file ) ? require $file : null;
		}
		return self::$maps[ $legacy_id ];
	}

	/** Controles de estilo de Element Pack con selectores ya reescritos al marcado propio. */
	public static function styles( $legacy_id ) {
		$file = __DIR__ . '/maps/' . basename( (string) $legacy_id ) . '.styles.php';
		return self::is_legacy_id( $legacy_id ) && is_file( $file ) ? (array) require $file : array();
	}

	public static function target( $legacy_id ) {
		$map = self::map( $legacy_id );
		return is_array( $map ) ? (string) $map['target'] : '';
	}

	/**
	 * Traduce una vez: un documento ya traducido y guardado desde el editor conserva lo editado.
	 */
	public static function translate( $legacy_id, array $settings ) {
		$map = self::map( $legacy_id );
		if ( ! is_array( $map ) || isset( $settings[ self::MARKER ] ) ) {
			return $settings;
		}
		$out = self::apply( $settings, $map );
		foreach ( $map['repeaters'] ?? array() as $key => $spec ) {
			$rows = array_key_exists( $key, $settings ) ? $settings[ $key ] : ( $spec['default_rows'] ?? array() );
			unset( $out[ $key ] );
			$items = array();
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( is_array( $row ) ) {
					$items[] = self::apply( $row, $spec );
				}
			}
			$out[ $spec['target'] ?? $key ] = $items;
		}
		if ( ! empty( $map['conditions'] ) ) {
			$out = self::active( $out, $map['conditions'] );
		}
		if ( isset( $map['filter'] ) && is_callable( $map['filter'] ) ) {
			$out = (array) call_user_func( $map['filter'], $out, $settings );
		}
		$out[ self::MARKER ] = $legacy_id;
		return $out;
	}

	/**
	 * Consulta de entradas de Element Pack (copia del control de PRO Elements con otros nombres) a
	 * los campos `posts_*` del widget `posts`. Element Pack filtra términos por `term_id` y PRO
	 * Elements por `term_taxonomy_id`.
	 */
	public static function posts_query( array $out ) {
		$sources = array( 'manual_selection' => 'by_id', '_related_post_type' => 'related' );
		$source  = (string) ( $out['posts_source'] ?? 'post' );
		$out['posts_post_type'] = $sources[ $source ] ?? ( '' !== $source ? $source : 'post' );
		$renames = array(
			'posts_selected_ids'       => 'posts_posts_ids',
			'posts_include_by'         => 'posts_include',
			'posts_include_author_ids' => 'posts_include_authors',
			'posts_exclude_by'         => 'posts_exclude',
			'posts_exclude_author_ids' => 'posts_exclude_authors',
			'query_id'                 => 'posts_query_id',
		);
		foreach ( $renames as $from => $to ) {
			if ( array_key_exists( $from, $out ) ) {
				$out[ $to ] = $out[ $from ];
				unset( $out[ $from ] );
			}
		}
		foreach ( array( 'posts_include_term_ids', 'posts_exclude_term_ids' ) as $key ) {
			if ( ! isset( $out[ $key ] ) ) {
				continue;
			}
			$ids = array();
			foreach ( (array) $out[ $key ] as $id ) {
				$term  = function_exists( 'get_term' ) ? get_term( (int) $id ) : null;
				$ids[] = is_object( $term ) && ! empty( $term->term_taxonomy_id ) ? (string) $term->term_taxonomy_id : (string) $id;
			}
			$out[ $key ] = $ids;
		}
		$orderby                = array( 'date' => 'post_date', 'title' => 'post_title', 'modified' => 'modified', 'comment_count' => 'comment_count', 'menu_order' => 'menu_order', 'rand' => 'rand' );
		$out['posts_orderby']   = $orderby[ (string) ( $out['posts_orderby'] ?? 'date' ) ] ?? 'post_date';
		$out['posts_order']     = 'asc' === strtolower( (string) ( $out['posts_order'] ?? 'desc' ) ) ? 'asc' : 'desc';
		unset( $out['posts_source'], $out['posts_only_with_featured_image'], $out['posts_source_description'], $out['posts_divider'] );
		return $out;
	}

	/**
	 * Presentación de una rejilla de entradas de Element Pack en la piel «classic» de `posts`.
	 * `$spec` nombra los ajustes de cada widget: per_page, columns, title, title_tag, author, date,
	 * comments, category, tags, excerpt, excerpt_length, read_more, read_more_text, image, pagination;
	 * cada uno es [clave, valor por defecto] o un valor fijo.
	 */
	public static function posts_classic( array $out, array $spec, array $content_keys ) {
		$get = static function ( $name, $fallback = '' ) use ( $out, $spec ) {
			if ( ! isset( $spec[ $name ] ) ) {
				return $fallback;
			}
			$rule = $spec[ $name ];
			return is_array( $rule ) ? ( $out[ $rule[0] ] ?? $rule[1] ) : $rule;
		};
		$on    = static function ( $name ) use ( $get ) {
			return 'yes' === $get( $name ) ? 'yes' : '';
		};
		$limit = $get( 'per_page', 6 );
		$set   = array(
			'_skin'                   => 'classic',
			'classic_posts_per_page'  => max( 1, (int) ( is_array( $limit ) ? ( $limit['size'] ?? 6 ) : $limit ) ),
			'classic_columns'         => (string) $get( 'columns', '3' ),
			'classic_thumbnail'       => 'yes' === $get( 'image', 'yes' ) ? 'top' : 'none',
			'classic_masonry'         => '',
			'classic_show_title'      => $on( 'title' ),
			'classic_title_tag'       => (string) $get( 'title_tag', 'h3' ),
			'classic_meta_data'       => array_keys( array_filter( array( 'author' => $on( 'author' ), 'date' => $on( 'date' ), 'comments' => $on( 'comments' ) ) ) ),
			'classic_meta_separator'  => '|',
			'classic_show_excerpt'    => $on( 'excerpt' ),
			'classic_excerpt_length'  => (int) $get( 'excerpt_length', 15 ),
			'classic_show_read_more'  => $on( 'read_more' ),
			'classic_read_more_text'  => (string) $get( 'read_more_text', 'Read More' ),
			'digitalisimo_post_terms' => array_keys( array_filter( array( 'category' => $on( 'category' ), 'post_tag' => $on( 'tags' ) ) ) ),
			'pagination_type'         => 'yes' === $get( 'pagination' ) ? 'numbers' : '',
		);
		foreach ( $content_keys as $key ) {
			unset( $out[ $key ] );
		}
		return $set + self::posts_query( $out );
	}

	/**
	 * `[tag clave="valor"]` como lo armaba Element Pack con los atributos de render de Elementor:
	 * valores escapados, listas separadas por espacios y lógicos como «1» o vacío.
	 */
	public static function shortcode( $tag, array $attributes ) {
		$parts = array();
		foreach ( $attributes as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ' ', array_map( 'strval', $value ) );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? '1' : '';
			}
			$parts[] = $key . '="' . esc_attr( (string) $value ) . '"';
		}
		return '[' . $tag . ' ' . implode( ' ', $parts ) . ']';
	}

	/**
	 * Un widget de Element Pack que sólo imprimía el shortcode de otro plugin pasa al widget
	 * Shortcode de Elementor: se quitan sus ajustes de contenido y se guarda el shortcode armado.
	 */
	public static function as_shortcode( array $out, $shortcode, array $content_keys ) {
		foreach ( $content_keys as $key ) {
			unset( $out[ $key ] );
		}
		$out['shortcode'] = (string) $shortcode;
		return $out;
	}

	/**
	 * Como `get_settings_for_display()`: un ajuste cuyo control no cumple su condición vale null.
	 * Se evalúa sobre los valores originales, con la comparación estricta de Elementor.
	 */
	public static function active( array $settings, array $conditions ) {
		$out = $settings;
		foreach ( $conditions as $key => $condition ) {
			if ( array_key_exists( $key, $settings ) && ! self::visible( $settings, (array) $condition ) ) {
				$out[ $key ] = null;
			}
		}
		return $out;
	}

	private static function visible( array $values, array $condition ) {
		foreach ( $condition as $condition_key => $condition_value ) {
			preg_match( '/([a-z_\-0-9]+)(?:\[([a-z_]+)])?(!?)$/i', (string) $condition_key, $parts );
			$key = $parts[1] ?? '';
			if ( ! isset( $values[ $key ] ) ) {
				return false;
			}
			$value = $values[ $key ];
			if ( ! empty( $parts[2] ) && is_array( $value ) ) {
				if ( ! isset( $value[ $parts[2] ] ) ) {
					return false;
				}
				$value = $value[ $parts[2] ];
			}
			if ( is_array( $condition_value ) && $condition_value ) {
				$contains = in_array( $value, $condition_value, true );
			} elseif ( is_array( $value ) && $value ) {
				$contains = in_array( $condition_value, $value, true );
			} else {
				$contains = $value === $condition_value;
			}
			if ( ! empty( $parts[3] ) === $contains ) {
				return false;
			}
		}
		return true;
	}

	/** Defaults, renombres, valores y fijos de un nivel (widget o fila de repetidor). */
	private static function apply( array $settings, array $spec ) {
		foreach ( $spec['defaults'] ?? array() as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			} elseif ( is_array( $value ) && is_array( $settings[ $key ] ) && $value && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
				// Como Elementor en los controles de valor múltiple (URL, imagen, icono, medidas):
				// las partes no guardadas toman el default.
				$settings[ $key ] = array_merge( $value, $settings[ $key ] );
			}
		}
		$out = $settings;
		foreach ( $spec['rename'] ?? array() as $from => $to ) {
			foreach ( self::DEVICES as $device ) {
				if ( array_key_exists( $from . $device, $settings ) ) {
					unset( $out[ $from . $device ] );
					$out[ $to . $device ] = $settings[ $from . $device ];
				}
			}
			if ( isset( $settings['__dynamic__'][ $from ] ) ) {
				unset( $out['__dynamic__'][ $from ] );
				$out['__dynamic__'][ $to ] = $settings['__dynamic__'][ $from ];
			}
		}
		foreach ( $spec['groups'] ?? array() as $from => $to ) {
			foreach ( $settings as $key => $value ) {
				if ( 0 === strpos( $key, $from . '_' ) ) {
					unset( $out[ $key ] );
					$out[ $to . substr( $key, strlen( $from ) ) ] = $value;
				}
			}
		}
		foreach ( $spec['values'] ?? array() as $key => $values ) {
			if ( ! array_key_exists( $key, $out ) ) {
				continue;
			}
			if ( is_callable( $values ) ) {
				$out[ $key ] = call_user_func( $values, $out[ $key ], $settings );
			} elseif ( is_scalar( $out[ $key ] ) && array_key_exists( (string) $out[ $key ], $values ) ) {
				$out[ $key ] = $values[ (string) $out[ $key ] ];
			}
		}
		// Textos que Element Pack aceptaba con HTML y el widget propio escapa.
		foreach ( $spec['strip'] ?? array() as $key ) {
			if ( isset( $out[ $key ] ) && is_string( $out[ $key ] ) ) {
				$out[ $key ] = trim( wp_strip_all_tags( $out[ $key ] ) );
			}
		}
		foreach ( $spec['set'] ?? array() as $key => $value ) {
			$out[ $key ] = $value;
		}
		foreach ( $spec['drop'] ?? array() as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	}
}
