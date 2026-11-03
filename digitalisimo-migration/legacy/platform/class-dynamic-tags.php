<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Etiquetas dinámicas de Element Pack (`element-pack-*`, 67 en 10.0.1) en `__dynamic__` (§3.D.9).
 *
 * Sólo las que tienen una equivalente **exacta** del constructor se reescriben, y sólo con los ajustes propios en su
 * valor por defecto (entrada actual, sin límite de palabras…): los textos «antes», «después» y «alternativo» pasan
 * tal cual. Las demás se quitan de `__dynamic__` y queda el valor fijo del campo (`plat.tags.dropped`, a «Pendiente de
 * revisar» en un documento publicado). Una etiqueta ilegible también se quita (fallar cerrado). Puro: no escribe nada.
 *
 * La tabla sale de `includes/dynamic-content/tags/` de Element Pack 10.0.1 (nombre, controles y salida de cada una)
 * frente a `modules/dynamic-tags/tags/` del destino.
 */
final class Dynamic_Tags {
	const PREFIX = 'element-pack-';

	/**
	 * Equivalentes exactas: `nombre de origen => [ destino, ajustes propios => valores admitidos (el primero, el que
	 * se da por omitido), ajustes que pasan => nombre en el destino ]`.
	 */
	const MAP = array(
		'element-pack-post-title'          => array( 'post-title', array( 'ep_post_type' => array( '', 'current' ), 'ep_word_limit' => array( '', '0' ) ), array( 'before' => 'before', 'after' => 'after', 'fallback' => 'fallback' ) ),
		'element-pack-site-title'          => array( 'site-title', array( 'ep_word_limit' => array( '', '0' ) ), array( 'before' => 'before', 'after' => 'after', 'fallback' => 'fallback' ) ),
		'element-pack-site-tagline'        => array( 'site-tagline', array( 'ep_word_limit' => array( '', '0' ) ), array( 'before' => 'before', 'after' => 'after', 'fallback' => 'fallback' ) ),
		'element-pack-archive-title'       => array( 'archive-title', array( 'ep_include_context' => array( 'yes' ), 'ep_word_limit' => array( '', '0' ) ), array( 'before' => 'before', 'after' => 'after', 'fallback' => 'fallback' ) ),
		'element-pack-archive-description' => array( 'archive-description', array( 'ep_word_limit' => array( '', '0' ) ), array( 'before' => 'before', 'after' => 'after', 'fallback' => 'fallback' ) ),
		'element-pack-post-id'             => array( 'post-id', array( 'ep_post_type' => array( '', 'current' ) ), array( 'before' => 'before', 'after' => 'after', 'fallback' => 'fallback' ) ),
		'element-pack-post-author'         => array( 'author-name', array( 'ep_post_type' => array( '', 'current' ), 'ep_author_data_type' => array( 'display_name' ), 'ep_word_limit' => array( '', '0' ) ), array( 'before' => 'before', 'after' => 'after', 'fallback' => 'fallback' ) ),
		'element-pack-post-url'            => array( 'post-url', array( 'ep_post_type' => array( '', 'current' ) ), array() ),
		'element-pack-site-url'            => array( 'site-url', array( 'ep_url_type' => array( 'home' ) ), array() ),
		'element-pack-archive-url'         => array( 'archive-url', array(), array() ),
		'element-pack-post-featured-image' => array( 'post-featured-image', array( 'ep_post_type' => array( '', 'current' ) ), array( 'fallback' => 'fallback' ) ),
		'element-pack-site-logo'           => array( 'site-logo', array( 'fallback' => array( '' ) ), array() ),
	);

	/**
	 * `__dynamic__` de los ajustes de un elemento (sólo los suyos y los de sus repetidores; los hijos los recorre quien
	 * llama). Informa por documento si hay uno en curso.
	 */
	public static function rewrite( array $element ): array {
		if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
			return $element;
		}
		$element['settings'] = self::rewrite_settings( $element['settings'], (string) ( $element['widgetType'] ?? $element['elType'] ?? '' ) );
		return $element;
	}

	/** Lo mismo en un árbol de elementos. */
	public static function rewrite_elements( array $elements ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$element = self::rewrite( $element );
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::rewrite_elements( $element['elements'] );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/** Lo mismo en ajustes sueltos (página, kit). */
	public static function rewrite_settings( array $settings, string $element = '' ): array {
		if ( false === strpos( (string) wp_json_encode( $settings ), self::PREFIX ) ) {
			return $settings;
		}
		$stats    = array( 'converted' => 0, 'dropped' => 0, 'names' => array() );
		$settings = self::walk( $settings, $stats );
		$post_id  = Inventory::context_post();
		if ( $post_id > 0 ) {
			if ( $stats['converted'] ) {
				Inventory::doc_code( $post_id, 'plat.tags.converted', array( 'count' => $stats['converted'] ) );
			}
			if ( $stats['dropped'] ) {
				Inventory::doc_code( $post_id, 'plat.tags.dropped', array( 'count' => $stats['dropped'], 'note' => Inventory::plain( implode( ', ', array_unique( $stats['names'] ) ), 200 ) ) + ( '' !== $element ? array( 'element' => $element ) : array() ) );
			}
		}
		return $settings;
	}

	private static function walk( array $value, array &$stats ): array {
		foreach ( $value as $key => $item ) {
			if ( '__dynamic__' === $key && is_array( $item ) ) {
				$value[ $key ] = self::dynamic( $item, $stats );
				if ( ! $value[ $key ] ) {
					unset( $value[ $key ] );
				}
			} elseif ( is_array( $item ) ) {
				$value[ $key ] = self::walk( $item, $stats );
			}
		}
		return $value;
	}

	/** Las entradas de un `__dynamic__`: reescritas, quitadas o intactas (las que no son de Element Pack). */
	private static function dynamic( array $dynamic, array &$stats ): array {
		foreach ( $dynamic as $control => $text ) {
			if ( ! is_string( $text ) || false === strpos( $text, 'name="' . self::PREFIX ) ) {
				continue;
			}
			$tag = self::parse( $text );
			$new = $tag ? self::translate( $tag ) : null;
			if ( null === $new ) {
				unset( $dynamic[ $control ] );
				++$stats['dropped'];
				$stats['names'][] = $tag ? substr( $tag['name'], strlen( self::PREFIX ) ) : '?';
				continue;
			}
			$dynamic[ $control ] = $new;
			++$stats['converted'];
		}
		return $dynamic;
	}

	/** Etiqueta única y bien formada (`[elementor-tag id="…" name="…" settings="…"]`), o null. */
	public static function parse( string $text ): ?array {
		if ( ! preg_match( '/^\[elementor-tag id="([A-Za-z0-9]{0,20})" name="([a-z0-9-]{1,80})" settings="([^"\]]*)"\]$/', trim( $text ), $m ) ) {
			return null;
		}
		$settings = '' === $m[3] ? array() : json_decode( urldecode( $m[3] ), true );
		return is_array( $settings ) ? array( 'id' => $m[1], 'name' => $m[2], 'settings' => $settings ) : null;
	}

	/** Texto de la etiqueta equivalente, o null si no la hay exacta. */
	public static function translate( array $tag ): ?string {
		$spec = self::MAP[ $tag['name'] ] ?? null;
		if ( null === $spec ) {
			return null;
		}
		list( $target, $defaults, $pass ) = $spec;
		$out = array();
		foreach ( $tag['settings'] as $key => $value ) {
			if ( isset( $pass[ $key ] ) ) {
				if ( is_scalar( $value ) && '' !== (string) $value ) {
					$out[ $pass[ $key ] ] = (string) $value;
				} elseif ( is_array( $value ) && ( ! empty( $value['id'] ) || ! empty( $value['url'] ) ) ) {
					$out[ $pass[ $key ] ] = array( 'id' => (string) ( $value['id'] ?? '' ), 'url' => (string) ( $value['url'] ?? '' ) );
				}
				continue;
			}
			if ( isset( $defaults[ $key ] ) ) {
				$plain = is_scalar( $value ) ? (string) $value : ( is_array( $value ) && empty( $value['id'] ) && empty( $value['url'] ) ? '' : null );
				if ( null === $plain || ! in_array( $plain, $defaults[ $key ], true ) ) {
					return null;
				}
				continue;
			}
			// Un ajuste que la tabla no conoce: no es exacta.
			if ( is_array( $value ) ? (bool) array_filter( $value ) : '' !== (string) $value ) {
				return null;
			}
		}
		// Un ajuste propio sin guardar vale su valor por defecto, que sólo es admisible si es el primero de la lista.
		foreach ( $defaults as $key => $allowed ) {
			if ( ! array_key_exists( $key, $tag['settings'] ) && ! in_array( self::origin_default( $tag['name'], $key ), $allowed, true ) ) {
				return null;
			}
		}
		$id = '' !== $tag['id'] ? $tag['id'] : substr( md5( $tag['name'] . wp_json_encode( $tag['settings'] ) ), 0, 7 );
		return sprintf( '[elementor-tag id="%1$s" name="%2$s" settings="%3$s"]', $id, $target, urlencode( (string) wp_json_encode( $out, JSON_FORCE_OBJECT ) ) );
	}

	/** Valor por defecto de un ajuste propio en el origen (10.0.1). */
	private static function origin_default( string $name, string $key ): string {
		$defaults = array(
			'ep_post_type'        => 'current',
			'ep_word_limit'       => '0',
			'ep_include_context'  => 'yes',
			'ep_author_data_type' => 'display_name',
			'ep_url_type'         => 'home',
			'fallback'            => '',
		);
		return $defaults[ $key ] ?? '';
	}

	/** Análisis: recuento de etiquetas del origen en unos elementos (convertibles y no). */
	public static function describe( array $elements ): array {
		$out  = array( 'converted' => 0, 'dropped' => 0 );
		$json = (string) wp_json_encode( $elements );
		if ( false === strpos( $json, self::PREFIX ) ) {
			return $out;
		}
		preg_match_all( '/\[elementor-tag id=\\\\"[A-Za-z0-9]*\\\\" name=\\\\"' . preg_quote( self::PREFIX, '/' ) . '[a-z0-9-]+\\\\" settings=\\\\"[^"\\\\\]]*\\\\"\]/', $json, $m );
		foreach ( $m[0] as $text ) {
			$tag = self::parse( stripslashes( $text ) );
			if ( $tag && null !== self::translate( $tag ) ) {
				++$out['converted'];
			} else {
				++$out['dropped'];
			}
		}
		return $out;
	}

	public static function analyze(): array {
		return array();
	}

	public static function prepare( array &$cursor, float $deadline ): bool {
		return true;
	}

	public static function finish( array &$cursor, float $deadline ): bool {
		return true;
	}

	/** El contenido vuelve con la copia de cada documento (frente A). */
	public static function revert( bool $force = false ): array {
		return array();
	}
}
