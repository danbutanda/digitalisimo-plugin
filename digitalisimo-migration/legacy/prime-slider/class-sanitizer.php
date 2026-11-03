<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

defined( 'ABSPATH' ) || exit;

/**
 * Limpieza del proveedor (§3.E.0.3), **antes** de traducir: nada de lo convertido conserva rastro de Prime Slider ni
 * contenido de demostración que el sitio no eligió.
 *
 * - Filas de `social_link_list` cuyo enlace es un perfil del proveedor (`facebook|twitter|x|instagram|linkedin|…` con
 *   `/bdthemes`, en cualquier subdominio) → fuera.
 * - Vídeos y mapas de demostración → vacío. Se reconocen por la URL completa del valor por defecto de su control (lo
 *   que da `Defaults`) y, además, por la huella de los conocidos (`DEMO`), por si el valor por defecto no se conoce.
 * - Cualquier enlace a un dominio del proveedor (`VENDOR_HOSTS`, los de `tests/playground/brand-audit/vendors.json`)
 *   → vacío.
 * - `button_link` con `#` (el valor por defecto) → sin enlace: el botón sólo se pinta con texto y enlace.
 * - Imagen por defecto inexistente de `flexure` (`images/slide/item-N.jpg`, ya rota en origen) → vacía.
 * - Las demás URL bajo la carpeta del plugin (`/plugins/bdthemes-prime-slider(-lite)?/`) se quedan: las resuelve
 *   `Demo_Media` (§3.E.5).
 *
 * `$removed` cuenta lo retirado en la última llamada, para el informe (`ps.vendor_removed`).
 */
final class Sanitizer {
	/** Redes en las que el proveedor publica sus perfiles de ejemplo. */
	const SOCIAL = '(?:facebook|twitter|x|instagram|linkedin|youtube|pinterest|github|dribbble|behance|tiktok|vimeo|tumblr|reddit)';

	/** Nombre del proveedor en sus perfiles. */
	const VENDOR = 'bdthemes';

	/** Dominios del proveedor (los de `vendors.json` que son suyos). */
	const VENDOR_HOSTS = array( 'bdthemes.com', 'bdthemes.co', 'elementpack.pro', 'primeslider.pro' );

	/** Huellas de los vídeos y el mapa de demostración de los valores por defecto (`research-ps.md` §6.3). */
	const DEMO = array( 'YE7VzlLtp-4', 'test-videos.co.uk/vids/bigbuckbunny/', 'vimeo.com/1084537', '0x3f9d24afe84a0263' );

	/** Controles de vídeo, lightbox y mapa: un valor igual al de su control por defecto es de demostración. */
	const DEMO_KEYS = array( 'video_link', 'youtube_link', 'lightbox_link', 'image_link_video', 'image_link_vimeo', 'image_link_google_map' );

	/** Repetidores de redes sociales. */
	const SOCIAL_LISTS = array( 'social_link_list' );

	/** @var int Retirado en la última llamada. */
	public static $removed = 0;

	/** Ajustes rellenados y limpios. */
	public static function clean( string $id, array $settings ): array {
		self::$removed = 0;
		$widget        = Maps::widget( $id );
		foreach ( self::SOCIAL_LISTS as $list ) {
			if ( ! isset( $settings[ $list ] ) || ! is_array( $settings[ $list ] ) ) {
				continue;
			}
			$rows = array();
			foreach ( $settings[ $list ] as $row ) {
				if ( is_array( $row ) && self::vendor_profile( self::url_of( $row['social_icon_link'] ?? '' ) ) ) {
					++self::$removed;
					continue;
				}
				$rows[] = $row;
			}
			$settings[ $list ] = $rows;
		}
		return self::walk( $settings, $widget, '' );
	}

	/** Recorre los ajustes (y las filas de los repetidores) vaciando lo que no puede quedar. */
	private static function walk( array $values, string $widget, string $repeater ): array {
		foreach ( $values as $key => $value ) {
			$key = (string) $key;
			if ( is_array( $value ) && self::is_list( $value ) && $value && is_array( reset( $value ) ) ) {
				// Repetidor: cada fila con el nombre del repetidor (para comparar con los valores por defecto de sus campos).
				foreach ( $value as $index => $row ) {
					if ( is_array( $row ) ) {
						$value[ $index ] = self::walk( $row, $widget, '' === $repeater ? $key : $repeater );
					}
				}
				$values[ $key ] = $value;
				continue;
			}
			$url = self::url_of( $value );
			if ( '' === $url ) {
				if ( is_array( $value ) && ! isset( $value['url'] ) ) {
					$values[ $key ] = self::walk( $value, $widget, $repeater );
				}
				continue;
			}
			if ( 'button_link' === $key && '#' === trim( $url ) ) {
				$values[ $key ] = self::empty_like( $value );
				continue;
			}
			if ( self::is_demo( $url, $widget, $repeater, $key ) || self::vendor_host( $url ) || self::broken_default( $url, $widget ) ) {
				$values[ $key ] = self::empty_like( $value );
				++self::$removed;
			}
		}
		return $values;
	}

	/** URL de un valor: la cadena si parece una URL, o la parte `url` de un enlace o una imagen. */
	public static function url_of( $value ): string {
		if ( is_array( $value ) ) {
			return isset( $value['url'] ) && is_string( $value['url'] ) ? $value['url'] : '';
		}
		if ( is_string( $value ) && 1 === preg_match( '#^\s*(?:https?:)?//[^\s"<>]+\s*$#i', $value ) ) {
			return trim( $value );
		}
		return '';
	}

	/** El mismo tipo de valor, vacío. */
	private static function empty_like( $value ) {
		if ( is_array( $value ) ) {
			$value['url'] = '';
			if ( array_key_exists( 'id', $value ) ) {
				$value['id'] = '';
			}
			return $value;
		}
		return '';
	}

	/** ¿Perfil del proveedor en una red social? */
	public static function vendor_profile( string $url ): bool {
		$parts = self::parse( $url );
		if ( ! $parts ) {
			return false;
		}
		if ( self::vendor_host( $url ) ) {
			return true;
		}
		return 1 === preg_match( '/(^|\.)' . self::SOCIAL . '\.com$/i', $parts['host'] )
			&& 1 === preg_match( '#^/(?:company/|in/|@)?' . self::VENDOR . '(?:[/?\#._-]|$)#i', $parts['path'] );
	}

	/** ¿Dominio del proveedor (o un subdominio)? */
	public static function vendor_host( string $url ): bool {
		$parts = self::parse( $url );
		if ( ! $parts ) {
			return false;
		}
		foreach ( self::VENDOR_HOSTS as $host ) {
			if ( $parts['host'] === $host || substr( $parts['host'], -strlen( '.' . $host ) ) === '.' . $host ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Vídeo o mapa de demostración? Por la URL completa del valor por defecto o por una huella conocida. */
	public static function is_demo( string $url, string $widget = '', string $repeater = '', string $key = '' ): bool {
		foreach ( self::DEMO as $print ) {
			if ( false !== stripos( $url, $print ) ) {
				return true;
			}
		}
		if ( '' === $widget || ! in_array( $key, self::DEMO_KEYS, true ) ) {
			return false;
		}
		$default = '' !== $repeater ? Defaults::field( $widget, $repeater, $key ) : Defaults::control( $widget, $key );
		$default = self::url_of( $default );
		return '' !== $default && self::same_url( $default, $url );
	}

	/** Imagen por defecto de `flexure` que no existe en el plugin (`images/slide/item-N.jpg`). */
	private static function broken_default( string $url, string $widget ): bool {
		return 'prime-slider-flexure' === $widget && 1 === preg_match( '#/plugins/[^/]+/assets/images/slide/item-\d+\.jpg$#i', (string) strtok( $url, '?#' ) );
	}

	/** Dos URL iguales sin contar el esquema ni `www.`. */
	private static function same_url( string $a, string $b ): bool {
		$norm = static function ( $url ) {
			$url = strtolower( trim( $url ) );
			$url = (string) preg_replace( '#^(?:https?:)?//(?:www\.)?#', '', $url );
			return rtrim( $url, '/' );
		};
		return $norm( $a ) === $norm( $b );
	}

	/** Host (en minúsculas, sin `www.`) y ruta de una URL. */
	private static function parse( string $url ): ?array {
		$url = trim( $url );
		if ( '' === $url ) {
			return null;
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return null;
		}
		$host = strtolower( (string) $parts['host'] );
		$host = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
		return array( 'host' => $host, 'path' => (string) ( $parts['path'] ?? '/' ) );
	}

	private static function is_list( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
