<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

use Digitalisimo\Elements\Legacy\Context;
use Digitalisimo\Elements\Legacy\Report;

defined( 'ABSPATH' ) || exit;

/**
 * Plantilla «Loop Item» generada para los carruseles de entradas, productos y eventos de Prime Slider (§3.E.4, frente F).
 *
 * El Loop Carousel de PRO sólo pinta plantillas de tipo `loop-item`; Migración hasta ahora sólo **copiaba** una
 * existente (`Native::loop_item()`). Aquí se **crea**: un contenedor con la imagen destacada de fondo (etiqueta
 * dinámica), los términos, el título, los metadatos, el extracto y el botón (o, para productos, título, precio y
 * añadir al carrito de PRO), según las partes que mostraba el widget.
 *
 * - **Tipo:** documento `loop-item` (`_elementor_template_type` y término `loop-item`), `_elementor_source` y `source`
 *   en `_elementor_page_settings` (`ELE/modules/loop-builder/documents/loop.php:84-85,351-361`). Los eventos usan
 *   `post`: `tribe_events` no es un origen de Loop Item; el tipo lo filtra la consulta del carrusel (C1-29).
 * - **Creación:** con `Platform\Documents::create()` (C6: siempre borrador, `wp_slash()` y comprobación del md5
 *   releído) y, sólo si la huella coincide, se publica, como el precedente `Native::loop_item()`. Si no coincide, se
 *   queda en borrador y no se usa: nunca un bucle vacío publicado.
 * - **Reutilización:** por la huella de las partes (`key()`), registrada en `Platform\Registry` (`kind = loop`, fila
 *   `pending` antes de crear y `done` después) y en la meta `_digitalisimo_migration_loop` de la plantilla.
 * - **Pureza:** con `Context::pure()` (editor y análisis) nunca crea nada: devuelve la ya registrada o 0 (V.7).
 * - **Falla cerrado:** sin contenedores, sin el documento `loop-item` o sin los widgets y etiquetas que usa, `ready()`
 *   es falso y `ensure()` devuelve 0; `Dynamic` elige el respaldo o deja el marcador.
 * - **Reversión:** `revert()` (`Runner::REVERT_EXTRA`, V.8) lleva a la papelera (o a borrador con
 *   `EMPTY_TRASH_DAYS = 0`) cada plantilla que no cambió desde que se creó y olvida su fila; lo editado se omite.
 *
 * Los textos (título de la plantilla) no nombran a ningún proveedor.
 */
final class Loop_Item {
	const KIND      = 'loop';
	const VERSION   = 1;
	const META      = '_digitalisimo_migration_loop';
	const REGISTRY  = '\\Digitalisimo\\Elements\\Legacy\\Platform\\Registry';
	const DOCUMENTS = '\\Digitalisimo\\Elements\\Legacy\\Platform\\Documents';
	const CSS_VALUE = '\\Digitalisimo\\Elements\\Legacy\\Effects\\Css_Value';
	const CONTEXT   = '\\Digitalisimo\\Elements\\Legacy\\Context';
	const REPORT    = '\\Digitalisimo\\Elements\\Legacy\\Report';

	/** Origen del Loop Item por tipo de contenido (`tribe_events` → `post`). */
	const SOURCES = array( 'post' => 'post', 'product' => 'product', 'tribe_events' => 'post' );

	/** Etiquetas de título admitidas. */
	const TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	/** Metadatos de `post-info` en su orden. */
	const META_TYPES = array( 'author', 'date', 'comments' );

	/** Unidades de la altura mínima del contenedor. */
	const UNITS = array( 'px', 'vh', 'em', 'rem' );

	/** Dispositivos de los valores responsivos. */
	const DEVICES = array( '', '_tablet', '_mobile' );

	/** @var array<string, int> huella → plantilla, en esta petición. */
	private static $done = array();

	/** @var int Creadas en esta petición. */
	public static $created = 0;

	/* ---------------------------------------------------------------- API (C15) */

	/**
	 * Loop Item de `$source` (`post`, `product` o `tribe_events`) con estas partes: el ya creado con la misma huella o
	 * uno nuevo. 0 si no existe y la conversión es pura, si el sitio no puede pintarlo o si falló la creación.
	 */
	public static function ensure( string $source, array $parts ): int {
		if ( ! isset( self::SOURCES[ $source ] ) ) {
			return 0;
		}
		$parts = self::normalize( $source, $parts );
		$key   = self::key( $source, $parts );
		$found = self::find( $key );
		if ( $found ) {
			return $found;
		}
		if ( self::pure() || ! self::ready( $source, $parts ) ) {
			return 0;
		}
		return self::create( $source, $parts, $key );
	}

	/** Huella estable de un Loop Item (origen, versión del formato y partes normalizadas). */
	public static function key( string $source, array $parts ): string {
		$parts = self::normalize( $source, $parts );
		return substr( hash( 'sha256', (string) json_encode( array( self::VERSION, self::SOURCES[ $source ] ?? 'post', $parts ) ) ), 0, 40 );
	}

	/** Plantilla publicada con esa huella (registro o meta), sólo lectura. 0 si no hay. */
	public static function find( string $key ): int {
		if ( isset( self::$done[ $key ] ) && self::usable( self::$done[ $key ] ) ) {
			return self::$done[ $key ];
		}
		$row = self::registry( 'get', $key );
		if ( is_array( $row ) && 'done' === ( $row['state'] ?? '' ) && ! empty( $row['dest'] ) && self::usable( (int) $row['dest'] ) ) {
			return self::$done[ $key ] = (int) $row['dest'];
		}
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => 'elementor_library',
				'post_status'      => 'publish',
				'meta_key'         => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- una vez por huella y petición.
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		$id = is_array( $ids ) && $ids ? (int) reset( $ids ) : 0;
		if ( $id && self::usable( $id ) ) {
			return self::$done[ $key ] = $id;
		}
		return 0;
	}

	/**
	 * ¿Puede el sitio pintar este Loop Item? Contenedores activos, el documento `loop-item` registrado y los widgets y
	 * etiquetas dinámicas que usa. Sin Elementor cargado (pruebas) cuenta como disponible, como `target_ready() === null`.
	 */
	public static function ready( string $source, array $parts = array() ): bool {
		if ( ! isset( self::SOURCES[ $source ] ) ) {
			return false;
		}
		$parts = self::normalize( $source, $parts );
		if ( false === Dynamic::feature( 'container' ) || false === Dynamic::feature( 'loop-item' ) ) {
			return false;
		}
		if ( 'product' === self::SOURCES[ $source ] && false === Dynamic::woocommerce() ) {
			return false;
		}
		foreach ( self::widgets( $source, $parts ) as $widget ) {
			if ( false === Dynamic::ready( $widget ) ) {
				return false;
			}
		}
		foreach ( self::tags( $source, $parts ) as $tag ) {
			if ( false === Dynamic::tag( $tag ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * `--revert` (`Runner::REVERT_EXTRA`, V.8): cada Loop Item creado que no cambió va a la papelera si
	 * `EMPTY_TRASH_DAYS` > 0, o a borrador si no (nunca se borra para siempre); lo editado después se omite salvo con
	 * `$force`. La fila del registro se olvida en los dos casos tratados.
	 *
	 * @return array<int, array{0:string, 1:array}> códigos del informe.
	 */
	public static function revert( bool $force = false ): array {
		$class = self::REGISTRY;
		$out   = array();
		if ( ! class_exists( $class ) || ! method_exists( $class, 'all' ) ) {
			return $out;
		}
		$trash = defined( 'EMPTY_TRASH_DAYS' ) && EMPTY_TRASH_DAYS > 0 && function_exists( 'wp_trash_post' );
		foreach ( (array) $class::all( self::KIND ) as $source => $row ) {
			$id   = is_array( $row ) ? (int) ( $row['dest'] ?? 0 ) : 0;
			$post = $id && function_exists( 'get_post' ) ? get_post( $id ) : null;
			if ( is_object( $post ) && 'trash' !== ( $post->post_status ?? '' ) && self::is_ours( $id, (string) $source ) ) {
				$same = self::hash( $id ) === (string) ( $row['hash'] ?? '' );
				if ( ! $same && ! $force ) {
					$out[] = array( 'ps.dyn.revert_skipped', array( 'id' => $id, 'type' => 'loop-item' ) );
					continue;
				}
				if ( $trash ) {
					wp_trash_post( $id );
				} elseif ( function_exists( 'wp_update_post' ) ) {
					wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
				}
				$out[] = array( 'ps.dyn.revert_loop', array( 'id' => $id ) );
			}
			if ( method_exists( $class, 'forget' ) ) {
				$class::forget( self::KIND, (string) $source );
			}
			unset( self::$done[ (string) $source ] );
		}
		return $out;
	}

	/** Olvida lo hecho en esta petición (pruebas). */
	public static function reset(): void {
		self::$done    = array();
		self::$created = 0;
	}

	/* ---------------------------------------------------------------- Partes */

	/**
	 * Partes normalizadas y validadas (las únicas claves que existen; los valores de una lista cerrada o comprobados):
	 *
	 * - `title`: etiqueta (`h1`…`p`) o '' (sin título);
	 * - `excerpt`: -1 sin extracto, 0 sin límite, n palabras;
	 * - `terms`: taxonomía de los términos o '';
	 * - `meta`: subconjunto ordenado de `author`, `date` y `comments`;
	 * - `button` y `button_url`: texto del botón ('' sin botón) y enlace fijo ('' = el de la entrada);
	 * - `price` y `cart`: sólo productos;
	 * - `align`: '', `left`, `center` o `right`;
	 * - `height`: altura mínima por dispositivo (`{unit, size}`) y `ratio` (`W/H`);
	 * - `overlay`: `{color, opacity}` o vacío; `colors`: `title`, `text`, `meta`, `button`, `button_bg`.
	 */
	public static function normalize( string $source, array $parts ): array {
		$product = 'product' === ( self::SOURCES[ $source ] ?? '' );
		$tag     = (string) ( $parts['title'] ?? '' );
		$out     = array(
			'title'      => in_array( $tag, self::TAGS, true ) ? $tag : '',
			'excerpt'    => isset( $parts['excerpt'] ) && is_numeric( $parts['excerpt'] ) && (int) $parts['excerpt'] >= 0 ? min( 500, (int) $parts['excerpt'] ) : -1,
			'terms'      => is_string( $parts['terms'] ?? null ) && 1 === preg_match( '/^[a-z0-9_-]{1,32}$/', $parts['terms'] ) ? $parts['terms'] : '',
			'meta'       => $product ? array() : array_values( array_intersect( self::META_TYPES, array_map( 'strval', (array) ( $parts['meta'] ?? array() ) ) ) ),
			'button'     => self::text( $parts['button'] ?? '' ),
			'button_url' => self::url( $parts['button_url'] ?? '' ),
			'price'      => $product && ! empty( $parts['price'] ),
			'cart'       => $product && ! empty( $parts['cart'] ),
			'align'      => in_array( $parts['align'] ?? '', array( 'left', 'center', 'right' ), true ) ? (string) $parts['align'] : '',
			'height'     => array(),
			'ratio'      => '',
			'overlay'    => array(),
			'colors'     => array(),
		);
		if ( '' === $out['button'] ) {
			$out['button_url'] = '';
		}
		foreach ( self::DEVICES as $device ) {
			$h = $parts['height'][ $device ] ?? null;
			if ( is_array( $h ) && is_numeric( $h['size'] ?? null ) && in_array( $h['unit'] ?? 'px', self::UNITS, true ) && (float) $h['size'] > 0 && (float) $h['size'] <= 2000 ) {
				$out['height'][ $device ] = array( 'unit' => (string) ( $h['unit'] ?? 'px' ), 'size' => 0 + $h['size'] );
			}
		}
		if ( is_string( $parts['ratio'] ?? null ) && preg_match( '#^(\d{1,3})/(\d{1,3})$#', $parts['ratio'], $m ) && (int) $m[1] > 0 && (int) $m[2] > 0 ) {
			$out['ratio'] = (int) $m[1] . '/' . (int) $m[2];
		}
		if ( is_array( $parts['overlay'] ?? null ) && $parts['overlay'] ) {
			$color = self::color( $parts['overlay']['color'] ?? '#000000' );
			$alpha = is_numeric( $parts['overlay']['opacity'] ?? null ) ? max( 0, min( 1, (float) $parts['overlay']['opacity'] ) ) : 0.5;
			if ( null !== $color ) {
				$out['overlay'] = array( 'color' => $color, 'opacity' => round( $alpha, 2 ) );
			}
		}
		foreach ( array( 'title', 'text', 'meta', 'button', 'button_bg' ) as $name ) {
			$color = self::color( $parts['colors'][ $name ] ?? null );
			if ( null !== $color ) {
				$out['colors'][ $name ] = $color;
			}
		}
		return $out;
	}

	/** Widgets que usa el Loop Item. */
	public static function widgets( string $source, array $parts ): array {
		$parts   = self::normalize( $source, $parts );
		$product = 'product' === ( self::SOURCES[ $source ] ?? '' );
		$out     = array();
		if ( '' !== $parts['terms'] || $parts['meta'] ) {
			$out[] = 'post-info';
		}
		if ( '' !== $parts['title'] ) {
			$out[] = $product ? 'woocommerce-product-title' : 'theme-post-title';
		}
		if ( $parts['excerpt'] >= 0 ) {
			$out[] = 'theme-post-excerpt';
		}
		if ( $parts['price'] ) {
			$out[] = 'woocommerce-product-price';
		}
		if ( $parts['cart'] ) {
			$out[] = 'woocommerce-product-add-to-cart';
		}
		if ( '' !== $parts['button'] ) {
			$out[] = 'button';
		}
		return array_values( array_unique( $out ) );
	}

	/** Etiquetas dinámicas que usa el Loop Item. */
	public static function tags( string $source, array $parts ): array {
		$parts   = self::normalize( $source, $parts );
		$product = 'product' === ( self::SOURCES[ $source ] ?? '' );
		$out     = array( 'post-featured-image' );
		if ( '' !== $parts['title'] ) {
			$out[] = $product ? 'woocommerce-product-title-tag' : 'post-title';
			$out[] = 'post-url';
		}
		if ( $parts['excerpt'] >= 0 ) {
			$out[] = 'post-excerpt';
		}
		if ( '' !== $parts['button'] && '' === $parts['button_url'] ) {
			$out[] = 'post-url';
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Árbol del Loop Item: un contenedor (`isInner` falso) con la imagen destacada de fondo y los widgets de las partes.
	 * IDs deterministas a partir de la huella.
	 */
	public static function elements( string $source, array $parts ): array {
		$parts   = self::normalize( $source, $parts );
		$key     = self::key( $source, $parts );
		$product = 'product' === ( self::SOURCES[ $source ] ?? '' );
		$n       = 0;
		$id      = static function () use ( $key, &$n ) {
			return substr( md5( $key . ':' . ( $n++ ) ), 0, 7 );
		};
		$tag = static function ( string $name, array $settings = array() ) use ( $id ) {
			return '[elementor-tag id="' . $id() . '" name="' . $name . '" settings="' . urlencode( (string) json_encode( (object) $settings ) ) . '"]';
		};
		$align   = array( 'left' => 'start', 'center' => 'center', 'right' => 'end' )[ $parts['align'] ] ?? '';
		$colors  = $parts['colors'] + array( 'title' => '#FFFFFF', 'text' => '#FFFFFF', 'meta' => '#FFFFFF' );
		$box_id  = $id();
		$box     = array(
			'content_width'         => 'full',
			'flex_direction'        => 'column',
			'flex_justify_content'  => 'center',
			'flex_gap'              => array( 'column' => '12', 'row' => '12', 'unit' => 'px', 'isLinked' => true, 'size' => 12 ),
			'padding'               => array( 'unit' => 'px', 'top' => '40', 'right' => '40', 'bottom' => '40', 'left' => '40', 'isLinked' => true ),
			'background_background' => 'classic',
			'background_color'      => '#222222',
			'background_image'      => array( 'url' => '', 'id' => '', 'size' => '' ),
			'background_position'   => 'center center',
			'background_size'       => 'cover',
			'__dynamic__'           => array( 'background_image' => $tag( 'post-featured-image' ) ),
		);
		if ( '' !== $parts['align'] ) {
			$box['flex_align_items'] = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' )[ $parts['align'] ];
		}
		foreach ( $parts['height'] as $device => $height ) {
			$box[ 'min_height' . $device ] = $height + array( 'sizes' => array() );
		}
		if ( '' !== $parts['ratio'] ) {
			$css = 'selector{aspect-ratio:' . $parts['ratio'] . '}';
			if ( self::css_ok( $css ) ) {
				$box['custom_css'] = $css;
			}
		}
		if ( $parts['overlay'] ) {
			$box['background_overlay_background'] = 'classic';
			$box['background_overlay_color']      = $parts['overlay']['color'];
			$box['background_overlay_opacity']    = array( 'unit' => 'px', 'size' => $parts['overlay']['opacity'], 'sizes' => array() );
		}
		$children = array();
		if ( '' !== $parts['terms'] ) {
			$children[] = self::widget( $id(), 'post-info', array(
				'icon_list'  => array( array( '_id' => $id(), 'type' => 'terms', 'taxonomy' => array( $parts['terms'] ), 'show_icon' => 'none', 'link' => 'yes' ) ),
				'text_color' => $colors['meta'],
			) + ( '' !== $parts['align'] ? array( 'icon_align' => $parts['align'] ) : array() ) );
		}
		if ( '' !== $parts['title'] ) {
			$title = array(
				'header_size' => $parts['title'],
				'title_color' => $colors['title'],
				'__dynamic__' => array(
					'title' => $tag( $product ? 'woocommerce-product-title-tag' : 'post-title' ),
					'link'  => $tag( 'post-url' ),
				),
			);
			if ( '' !== $align ) {
				$title['align'] = $align;
			}
			$children[] = self::widget( $id(), $product ? 'woocommerce-product-title' : 'theme-post-title', $title );
		}
		if ( $parts['meta'] ) {
			$rows = array();
			foreach ( $parts['meta'] as $type ) {
				$rows[] = array( '_id' => $id(), 'type' => $type, 'show_icon' => 'none' );
			}
			$children[] = self::widget( $id(), 'post-info', array( 'icon_list' => $rows, 'text_color' => $colors['meta'] ) + ( '' !== $parts['align'] ? array( 'icon_align' => $parts['align'] ) : array() ) );
		}
		if ( $parts['excerpt'] >= 0 ) {
			$excerpt = array(
				'title_color' => $colors['text'],
				'__dynamic__' => array( 'excerpt' => $tag( 'post-excerpt', array( 'max_length' => $parts['excerpt'] ? $parts['excerpt'] : null, 'apply_to_post_content' => 'yes' ) ) ),
			);
			if ( '' !== $align ) {
				$excerpt['align'] = $align;
			}
			$children[] = self::widget( $id(), 'theme-post-excerpt', $excerpt );
		}
		if ( $parts['price'] ) {
			$children[] = self::widget( $id(), 'woocommerce-product-price', array( 'price_color' => $colors['text'] ) + ( '' !== $parts['align'] ? array( 'text_align' => $parts['align'] ) : array() ) );
		}
		if ( $parts['cart'] ) {
			$children[] = self::widget( $id(), 'woocommerce-product-add-to-cart', array() );
		}
		if ( '' !== $parts['button'] ) {
			$button = array( 'text' => $parts['button'] );
			if ( '' !== $parts['button_url'] ) {
				$button['link'] = array( 'url' => $parts['button_url'], 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' );
			} else {
				$button['link']        = array( 'url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' );
				$button['__dynamic__'] = array( 'link' => $tag( 'post-url' ) );
			}
			if ( '' !== $parts['align'] ) {
				$button['align'] = $parts['align'];
			}
			if ( isset( $parts['colors']['button'] ) ) {
				$button['button_text_color'] = $parts['colors']['button'];
			}
			if ( isset( $parts['colors']['button_bg'] ) ) {
				$button['background_background'] = 'classic';
				$button['background_color']      = $parts['colors']['button_bg'];
			}
			$children[] = self::widget( $id(), 'button', $button );
		}
		return array(
			array(
				'id'       => $box_id,
				'elType'   => 'container',
				'isInner'  => false,
				'settings' => $box,
				'elements' => $children,
			),
		);
	}

	/* ---------------------------------------------------------------- Creación */

	private static function create( string $source, array $parts, string $key ): int {
		$elements = self::elements( $source, $parts );
		$json     = (string) json_encode( $elements );
		$doc_src  = self::SOURCES[ $source ];
		$title    = sprintf(
			/* translators: 1: tipo de contenido (entradas, productos, eventos), 2: huella corta. */
			__( 'Elemento de bucle de %1$s (migrado) · %2$s', 'digitalisimo-migration' ),
			array( 'post' => __( 'entradas', 'digitalisimo-migration' ), 'product' => __( 'productos', 'digitalisimo-migration' ), 'tribe_events' => __( 'eventos', 'digitalisimo-migration' ) )[ $source ],
			substr( $key, 0, 6 )
		);
		$meta = array(
			'_elementor_data'          => $json,
			'_elementor_edit_mode'     => 'builder',
			'_elementor_template_type' => 'loop-item',
			'_elementor_source'        => $doc_src,
			'_elementor_page_settings' => array( 'source' => $doc_src ),
			self::META                 => $key,
		);
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$meta['_elementor_version'] = ELEMENTOR_VERSION;
		}
		self::registry( 'put', $key, array( 'dest' => 0, 'hash' => '', 'state' => 'pending', 'extra' => array( 'source' => $source ) ) );
		$id = self::insert( $title, $meta, $key );
		if ( ! $id ) {
			return 0;
		}
		if ( function_exists( 'wp_set_object_terms' ) ) {
			wp_set_object_terms( $id, 'loop-item', 'elementor_library_type' );
		}
		$hash = self::hash( $id );
		if ( md5( $json ) !== $hash ) {
			// La huella releída no coincide: la plantilla se queda en borrador y no se usa.
			self::registry( 'put', $key, array( 'dest' => $id, 'hash' => $hash, 'state' => 'draft', 'extra' => array( 'source' => $source ) ) );
			self::report( 'ps.dyn.loop_failed', array( 'id' => $id ) );
			return 0;
		}
		if ( function_exists( 'wp_update_post' ) ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		}
		self::registry( 'put', $key, array( 'dest' => $id, 'hash' => $hash, 'state' => 'done', 'extra' => array( 'source' => $source ) ) );
		self::$done[ $key ] = $id;
		++self::$created;
		self::report( 'ps.dyn.loop_created', array( 'id' => $id, 'type' => $source ) );
		return $id;
	}

	/** Inserta el borrador con `Documents::create()` (A, C6) o, sin él, como `Native::loop_item()`. */
	private static function insert( string $title, array $meta, string $key ): int {
		$documents = self::DOCUMENTS;
		$post      = array( 'post_type' => 'elementor_library', 'post_status' => 'draft', 'post_title' => $title );
		if ( class_exists( $documents ) && method_exists( $documents, 'create' ) ) {
			return (int) $documents::create( 'loop-item', $post, $meta, array( 'source' => 'migration', 'kind' => self::KIND, 'key' => $key ) );
		}
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return 0;
		}
		$id = wp_insert_post( $post );
		if ( ! $id || ( function_exists( 'is_wp_error' ) && is_wp_error( $id ) ) ) {
			return 0;
		}
		foreach ( $meta as $name => $value ) {
			update_post_meta( (int) $id, $name, function_exists( 'wp_slash' ) ? wp_slash( $value ) : $value );
		}
		return (int) $id;
	}

	/* ---------------------------------------------------------------- Auxiliares */

	/** md5 de `_elementor_data` releído (con `Documents::hash()` si existe). */
	private static function hash( int $id ): string {
		$documents = self::DOCUMENTS;
		if ( class_exists( $documents ) && method_exists( $documents, 'hash' ) ) {
			return (string) $documents::hash( $id );
		}
		$data = function_exists( 'get_post_meta' ) ? get_post_meta( $id, '_elementor_data', true ) : '';
		return is_string( $data ) && '' !== $data ? md5( $data ) : '';
	}

	/** ¿Una plantilla publicada de tipo `loop-item`? */
	private static function usable( int $id ): bool {
		$post = $id && function_exists( 'get_post' ) ? get_post( $id ) : null;
		return is_object( $post ) && 'elementor_library' === ( $post->post_type ?? '' ) && 'publish' === ( $post->post_status ?? '' )
			&& 'loop-item' === ( function_exists( 'get_post_meta' ) ? get_post_meta( $id, '_elementor_template_type', true ) : '' );
	}

	/** ¿La creó Migración con esta huella? (la meta propia; nunca se revierte una plantilla ajena). */
	private static function is_ours( int $id, string $key ): bool {
		return function_exists( 'get_post_meta' ) && $key === (string) get_post_meta( $id, self::META, true );
	}

	private static function pure(): bool {
		$context = self::CONTEXT;
		return class_exists( $context, false ) && $context::pure();
	}

	/** Llamada protegida al registro de A (C6). Nunca escribe en modo puro. */
	private static function registry( string $method, string $source, array $row = array() ) {
		$class = self::REGISTRY;
		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			return null;
		}
		if ( 'get' === $method ) {
			return $class::get( self::KIND, $source );
		}
		if ( self::pure() ) {
			return null;
		}
		return $class::$method( self::KIND, $source, $row );
	}

	/** Código del sitio en el informe (A, C2), si existe. */
	private static function report( string $code, array $detail ): void {
		$report = self::REPORT;
		if ( class_exists( $report, false ) && method_exists( $report, 'site' ) ) {
			$report::site( $code, $detail );
		}
	}

	/** Color validado con `Effects\Css_Value::color()` (C) o, sin él, con la misma expresión. */
	private static function color( $value ): ?string {
		$class = self::CSS_VALUE;
		if ( class_exists( $class ) && method_exists( $class, 'color' ) ) {
			return $class::color( $value );
		}
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		return 1 === preg_match( '/^(#[0-9a-fA-F]{3,8}|(rgb|hsl)a?\([0-9.,\s%deg]+\)|var\(--[a-zA-Z0-9_-]+\)|currentColor|transparent)$/', $value ) ? $value : null;
	}

	/** Comprobación final de un bloque CSS generado (`Css_Value::block()`, V.7). */
	private static function css_ok( string $css ): bool {
		$class = self::CSS_VALUE;
		if ( class_exists( $class ) && method_exists( $class, 'block' ) ) {
			return $class::block( $css );
		}
		return 1 === preg_match( '#^selector\{aspect-ratio:\d{1,3}/\d{1,3}\}$#', $css );
	}

	/** Texto plano corto (sin etiquetas ni caracteres de control). */
	private static function text( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value ) : strip_tags( $value );
		$value = trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 80, 'UTF-8' ) : substr( $value, 0, 80 );
	}

	/** Enlace fijo: http(s), relativo o ancla; nada de `javascript:` ni `#` vacío. */
	private static function url( $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value || '#' === $value || 1 !== preg_match( '#^(https?://|/|\#[A-Za-z])[^\s"<>]*$#i', $value ) ) {
			return '';
		}
		return function_exists( 'esc_url_raw' ) ? (string) esc_url_raw( $value ) : $value;
	}

	private static function widget( string $id, string $type, array $settings ): array {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'settings'   => $settings,
			'elements'   => array(),
			'widgetType' => $type,
		);
	}
}
