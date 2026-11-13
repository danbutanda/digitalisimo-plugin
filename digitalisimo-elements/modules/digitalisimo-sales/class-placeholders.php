<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Los [placeholders] de una plantilla del kit: dónde están, de qué tipo son y cómo se llenan.
 *
 * Un placeholder es un tramo entre corchetes de primer nivel: «[Nombre del bonus 1: «[cantidad] [recurso] para
 * [tu X]»]» es uno solo (los de dentro son la fórmula que lo explica). Puro, sin WordPress.
 *
 * Tipos (de más a menos estricto):
 * - `cierre`: la fecha de cierre (`CLOSE`); sólo se llena con `close_date`, nunca con un reemplazo.
 * - `enlace`: está en un campo de enlace (video, grupo, redes): se pone en el editor, nunca con un reemplazo.
 * - `precio`: va detrás de `$` (la plantilla ya escribe `$` y `MXN`): sólo una cifra.
 * - `dato_real`: un testimonio, un nombre, una cifra, una captura o un logo reales (D-0023): se puede llenar con lo que
 *   dio el usuario y una persona lo revisa antes de publicar.
 * - `texto`: lo demás.
 */
final class Placeholders {
	const CLOSE = array( '[día de cierre]', '[mes de cierre]', '[hora de cierre]', '[fecha y hora de cierre]' );

	const KINDS = array( 'cierre', 'enlace', 'precio', 'dato_real', 'texto' );

	/** Los enlaces de WhatsApp del kit: `https://wa.me/<número>?text=<mensaje con placeholders>`. */
	const WHATSAPP = 'https://wa.me/';

	/** Número de muestra de las plantillas. */
	const WHATSAPP_SAMPLE = '52XXXXXXXXXX';

	/** Un placeholder que pide un dato real (D-0023). */
	const REAL_DATA = '/\b(real(es)?|permiso|testimonios?|reseñas?|capturas?|logos?|credencial(es)?|cifras?|caso|reconocidas)\b|sus palabras/iu';

	/** Campos con HTML (se sanea con `wp_kses_post()`): widget → ruta del ajuste sin índices. */
	const RICH = array(
		'text-editor' => array( 'editor' ),
		'heading'     => array( 'title' ),
		'accordion'   => array( 'tabs.tab_content' ),
		'icon-list'   => array( 'icon_list.text' ),
	);

	/** Campos de enlace (la última parte de la ruta del ajuste). */
	const URL_KEYS = array( 'url', 'youtube_url', 'vimeo_url', 'external_url', 'redirect_to' );

	/**
	 * Tramos de primer nivel de una cadena: lista de [posición, longitud, texto]. Un corchete sin cerrar no abre nada.
	 */
	public static function spans( string $text ): array {
		$out   = array();
		$depth = 0;
		$start = 0;
		$len   = strlen( $text );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $text[ $i ];
			if ( '[' === $c ) {
				if ( 0 === $depth ) {
					$start = $i;
				}
				++$depth;
			} elseif ( ']' === $c && $depth > 0 ) {
				--$depth;
				if ( 0 === $depth ) {
					$out[] = array( $start, $i - $start + 1, substr( $text, $start, $i - $start + 1 ) );
				}
			}
		}
		return $out;
	}

	/**
	 * Qué es un ajuste: `plain` (texto plano), `rich` (HTML), `url` (enlace), `whatsapp` (enlace de WhatsApp, cuyo
	 * mensaje es texto) o `date` (la fecha del contador).
	 */
	public static function field( string $owner, string $path, string $value ): string {
		if ( 'due_date' === $path ) {
			return 'date';
		}
		$last = substr( (string) strrchr( '.' . $path, '.' ), 1 );
		if ( in_array( $last, self::URL_KEYS, true ) ) {
			return 0 === strpos( $value, self::WHATSAPP ) ? 'whatsapp' : 'url';
		}
		return isset( self::RICH[ $owner ] ) && in_array( $path, self::RICH[ $owner ], true ) ? 'rich' : 'plain';
	}

	/**
	 * Recorre los ajustes de texto de un árbol de elementos y llama a `$visit( $owner, $path, $value )` con cada cadena,
	 * que devuelve la cadena nueva (o la misma). Devuelve el árbol con las cadenas cambiadas.
	 */
	public static function map_strings( array $elements, callable $visit ): array {
		foreach ( $elements as $i => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$owner = (string) ( $element['widgetType'] ?? $element['elType'] ?? '' );
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$element['settings'] = self::map_settings( $element['settings'], '', $owner, $visit );
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::map_strings( $element['elements'], $visit );
			}
			$elements[ $i ] = $element;
		}
		return $elements;
	}

	private static function map_settings( array $settings, string $prefix, string $owner, callable $visit ): array {
		foreach ( $settings as $key => $value ) {
			$path = is_int( $key ) ? $prefix : ( '' === $prefix ? (string) $key : $prefix . '.' . $key );
			if ( is_array( $value ) ) {
				$settings[ $key ] = self::map_settings( $value, $path, $owner, $visit );
			} elseif ( is_string( $value ) ) {
				$settings[ $key ] = $visit( $owner, $path, $value );
			}
		}
		return $settings;
	}

	/**
	 * Los placeholders de un árbol, en el orden del documento: texto → [count, kind]. Los de un enlace de WhatsApp son
	 * los de su mensaje (texto); el número de muestra no es un placeholder (se informa aparte).
	 */
	public static function summary( array $elements ): array {
		$found = array();
		self::map_strings(
			$elements,
			static function ( $owner, $path, $value ) use ( &$found ) {
				if ( false === strpos( $value, '[' ) ) {
					return $value;
				}
				$field = self::field( $owner, $path, $value );
				$text  = $value;
				if ( 'whatsapp' === $field ) {
					$query = (string) parse_url( $value, PHP_URL_QUERY ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
					$text  = 0 === strpos( $query, 'text=' ) ? rawurldecode( substr( $query, 5 ) ) : '';
				}
				foreach ( self::spans( $text ) as $span ) {
					$key = $span[2];
					if ( ! isset( $found[ $key ] ) ) {
						$found[ $key ] = array(
							'count' => 0,
							'url'   => false,
							'price' => false,
						);
					}
					++$found[ $key ]['count'];
					$found[ $key ]['url']   = $found[ $key ]['url'] || 'url' === $field;
					$found[ $key ]['price'] = $found[ $key ]['price'] || ( $span[0] > 0 && '$' === $text[ $span[0] - 1 ] );
				}
				return $value;
			}
		);
		$out = array();
		foreach ( $found as $key => $info ) {
			$out[ $key ] = array(
				'count' => $info['count'],
				'kind'  => self::kind( (string) $key, $info['url'], $info['price'] ),
			);
		}
		return $out;
	}

	public static function kind( string $text, bool $in_url, bool $price ): string {
		if ( in_array( $text, self::CLOSE, true ) ) {
			return 'cierre';
		}
		if ( $in_url ) {
			return 'enlace';
		}
		if ( $price ) {
			return 'precio';
		}
		return preg_match( self::REAL_DATA, $text ) ? 'dato_real' : 'texto';
	}

	/** Cambia cada tramo de primer nivel por lo que devuelve `$resolve( $texto, $es_precio )` (null lo deja). */
	public static function fill( string $text, callable $resolve ): string {
		$spans = self::spans( $text );
		if ( ! $spans ) {
			return $text;
		}
		$out = '';
		$at  = 0;
		foreach ( $spans as $span ) {
			list( $pos, $len, $key ) = $span;
			$value                   = $resolve( $key, $pos > 0 && '$' === $text[ $pos - 1 ] );
			$out                    .= substr( $text, $at, $pos - $at ) . ( null === $value ? $key : (string) $value );
			$at                      = $pos + $len;
		}
		return $out . substr( $text, $at );
	}
}
