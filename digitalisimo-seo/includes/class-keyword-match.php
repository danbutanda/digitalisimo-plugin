<?php
/**
 * Comparación interna de keywords. Nunca altera el texto guardado ni publicado.
 */
defined( 'ABSPATH' ) || exit;

class Digitalisimo_Integrations_Keyword_Match {
	/** Convierte texto a una forma comparable sin perder el valor original en la interfaz. */
	public static function normalize( $value ) {
		$value = (string) $value;
		if ( class_exists( 'Normalizer' ) ) {
			$value = Normalizer::normalize( $value, Normalizer::FORM_KD );
			$value = preg_replace( '/\\p{Mn}+/u', '', $value );
		}
		$value = remove_accents( $value );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		return trim( preg_replace( '/\\s+/u', ' ', $value ) );
	}

	/** Indica si una keyword aparece en un texto usando la forma normalizada. */
	public static function contains( $value, $keyword ) {
		$needle = self::normalize( $keyword );
		if ( '' === $needle ) return false;
		$haystack = self::normalize( wp_strip_all_tags( (string) $value ) );
		return false !== strpos( $haystack, $needle );
	}

	/** Cuenta coincidencias normalizadas sin modificar el contenido original. */
	public static function count( $value, $keyword ) {
		$needle = self::normalize( $keyword );
		if ( '' === $needle ) return 0;
		return substr_count( self::normalize( wp_strip_all_tags( (string) $value ) ), $needle );
	}

	/** Elimina duplicados equivalentes y conserva el primer texto escrito por la persona. */
	public static function unique( $keywords ) {
		$unique = array();
		$seen = array();
		foreach ( (array) $keywords as $keyword ) {
			$keyword = trim( sanitize_text_field( (string) $keyword ) );
			$key = self::normalize( $keyword );
			if ( '' === $keyword || '' === $key || isset( $seen[ $key ] ) ) continue;
			$seen[ $key ] = true;
			$unique[] = $keyword;
		}
		return $unique;
	}

	/**
	 * Un slug corto es válido cuando contiene los términos centrales de alguna
	 * keyword objetivo (principal o secundaria), sin exigir conectores.
	 */
	public static function slug_matches( $url, $keywords ) {
		$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '' === $path ) return true;
		$slug = sanitize_title( rawurldecode( basename( $path ) ) );
		if ( '' === $slug ) return true;
		$slug_terms = array_values( array_filter( explode( '-', $slug ) ) );
		$ignored = array( 'a', 'al', 'de', 'del', 'el', 'en', 'la', 'las', 'los', 'para', 'por', 'un', 'una', 'y' );
		foreach ( self::unique( $keywords ) as $keyword ) {
			$target = sanitize_title( self::normalize( $keyword ) );
			if ( '' === $target || false !== strpos( $slug, $target ) ) return true;
			$terms = array_values( array_filter( explode( '-', $target ), function ( $term ) use ( $ignored ) {
				return strlen( $term ) > 2 && ! in_array( $term, $ignored, true );
			} ) );
			if ( ! $terms ) continue;
			$required = 1 === count( $terms ) ? 1 : max( 2, (int) ceil( count( $terms ) * 0.6 ) );
			if ( count( array_intersect( $terms, $slug_terms ) ) >= $required ) return true;
		}
		return false;
	}
}
