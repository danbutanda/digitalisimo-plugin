<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Arma los elementos de una página a partir de una plantilla del kit y de la entrada cerrada de `create-page`.
 *
 * Candados (D-0023, D-0024 y el brief del kit):
 * - sólo cambia lo que se pide: un placeholder sin dato se queda tal cual y se informa como pendiente;
 * - los placeholders de cierre sólo se llenan con `close_date` (una fecha real y futura); sin ella se quitan los
 *   bloques que sólo sirven con un cierre real (los del manifiesto: la barra, el aviso, el contador del carrito);
 * - un enlace (video, grupo, redes) nunca se llena: se pone en el editor;
 * - ningún valor lleva una URL de otro sitio (sólo `wa.me` y el propio sitio); un precio es sólo una cifra (la
 *   plantilla ya escribe `$` y `MXN`);
 * - cada valor se sanea según su campo: texto plano (`sanitize_text_field()`) o HTML (`wp_kses_post()`);
 * - todos los identificadores (elementos y elementos de listas) se regeneran: ninguno repite uno de la plantilla;
 * - `omit_blocks` quita bloques enteros del manifiesto (nunca el pie) y nunca reordena: lo que queda sigue en el orden
 *   de la plantilla, el de la guía.
 * No toca la base de datos: lo guarda `Pages`.
 */
final class Builder {
	const MAX_KEYS   = 400;
	const MAX_VALUES = 100;
	const MAX_LENGTH = 3000;

	/** Un precio: 1997, 1,997, 1997.50 o 1,997.50 (la plantilla escribe `$` y `MXN`). */
	const PRICE = '/^(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)(?:\.[0-9]{1,2})?$/';

	/** Dominios de primer nivel que delatan una dirección escrita sin `https://`. */
	const TLDS = 'com|net|org|mx|lat|io|co|info|biz|app|dev|me|tv|store|shop|online|site|xyz|ly|gl|be|us|es|link|page';

	const MONTHS = array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );

	/**
	 * @param array $template Plantilla (formato 0.4, con `content`).
	 * @param array $meta     Su entrada del manifiesto (`urgency`, `has_countdown`).
	 * @param array $input    `replacements`, `whatsapp` y `close_date` de la entrada.
	 * @param array $env      `site_host` (host del sitio), `timezone` (\DateTimeZone del sitio) y `now` (marca de tiempo).
	 * @return array|\WP_Error `elements` y el informe.
	 */
	public static function build( array $template, array $meta, array $input, array $env ) {
		$site    = strtolower( (string) ( $env['site_host'] ?? '' ) );
		$summary = Placeholders::summary( $template['content'] );

		// Reemplazos: clave «[placeholder]» → texto, o lista de textos (uno por aparición, en orden).
		$replacements = $input['replacements'] ?? array();
		if ( ! is_array( $replacements ) ) {
			return self::error( 'digitalisimo_sales_replacements', 'replacements debe ser un objeto «[placeholder]» → texto (o lista de textos).' );
		}
		if ( count( $replacements ) > self::MAX_KEYS ) {
			return self::error( 'digitalisimo_sales_replacements', sprintf( 'Demasiados reemplazos (%d como mucho).', self::MAX_KEYS ) );
		}
		$plan    = array();
		$ignored = array();
		$real    = array();
		foreach ( $replacements as $key => $value ) {
			$key = trim( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( '[' !== $key[0] ) {
				$key = '[' . $key . ']';
			}
			$list   = is_array( $value );
			$values = $list ? array_values( $value ) : array( $value );
			if ( count( $values ) > self::MAX_VALUES ) {
				return self::error( 'digitalisimo_sales_replacements', sprintf( '«%s»: %d valores como mucho.', $key, self::MAX_VALUES ) );
			}
			foreach ( $values as $i => $one ) {
				if ( is_int( $one ) || is_float( $one ) ) {
					$one = (string) $one;
				}
				if ( ! is_string( $one ) ) {
					return self::error( 'digitalisimo_sales_replacements', sprintf( '«%s»: cada valor debe ser texto.', $key ) );
				}
				if ( strlen( $one ) > self::MAX_LENGTH ) {
					return self::error( 'digitalisimo_sales_replacements', sprintf( '«%s»: %d caracteres como mucho por valor.', $key, self::MAX_LENGTH ) );
				}
				$values[ $i ] = trim( $one );
			}
			if ( ! isset( $summary[ $key ] ) ) {
				$ignored[] = array(
					'placeholder' => $key,
					'reason'      => 'No está en esta plantilla (list-templates con template da la lista exacta).',
				);
				continue;
			}
			$kind = $summary[ $key ]['kind'];
			if ( 'cierre' === $kind ) {
				return self::error( 'digitalisimo_sales_close_placeholder', sprintf( '«%s» es la fecha de cierre: se llena sólo con close_date, y sólo si el cierre es real.', $key ) );
			}
			if ( 'enlace' === $kind ) {
				return self::error( 'digitalisimo_sales_link_placeholder', sprintf( '«%s» es un enlace (video, grupo o redes): se pone en el editor. Ninguna URL de otro sitio entra por aquí.', $key ) );
			}
			foreach ( $values as $one ) {
				$url = self::external_url( $one, $site );
				if ( '' !== $url ) {
					return self::error( 'digitalisimo_sales_external_url', sprintf( '«%s» lleva una URL de otro sitio (%s). Los enlaces del kit son del propio sitio; el WhatsApp va en whatsapp.', $key, $url ) );
				}
				if ( 'precio' === $kind && '' !== $one && ! preg_match( self::PRICE, $one ) ) {
					return self::error( 'digitalisimo_sales_price', sprintf( '«%s» es un precio en pesos: sólo la cifra (p. ej. 1,997 o 1997.50); la plantilla ya escribe $ y MXN. Recibido: %s', $key, $one ) );
				}
			}
			$values = array_values(
				array_filter(
					$values,
					static function ( $one ) {
						return '' !== $one;
					}
				)
			);
			if ( ! $values ) {
				$ignored[] = array(
					'placeholder' => $key,
					'reason'      => 'Valor vacío: el placeholder se queda.',
				);
				continue;
			}
			$plan[ $key ] = array(
				'values' => $values,
				'list'   => $list,
				'next'   => 0,
				'used'   => 0,
			);
			if ( 'dato_real' === $kind ) {
				$real[] = $key;
			}
		}

		// WhatsApp: sólo dígitos, con la clave del país.
		$whatsapp = '';
		if ( isset( $input['whatsapp'] ) && '' !== $input['whatsapp'] ) {
			$whatsapp = is_string( $input['whatsapp'] ) || is_int( $input['whatsapp'] ) ? (string) $input['whatsapp'] : '';
			if ( ! preg_match( '/^[0-9]{8,15}$/', $whatsapp ) ) {
				return self::error( 'digitalisimo_sales_whatsapp', 'whatsapp: sólo dígitos (8 a 15), con la clave del país y sin «+», espacios ni guiones; p. ej. 5218711234567.' );
			}
		}

		// Fecha de cierre: real y futura, en la zona del sitio; sólo en plantillas con contador.
		$close = null;
		if ( isset( $input['close_date'] ) && '' !== $input['close_date'] ) {
			if ( empty( $meta['has_countdown'] ) ) {
				return self::error( 'digitalisimo_sales_close_date', 'Esta plantilla no lleva fecha de cierre: quita close_date.' );
			}
			$close = self::close_date( $input['close_date'], $env );
			if ( is_wp_error( $close ) ) {
				return $close;
			}
		}

		// Bloques que se quitan: claves del manifiesto, nunca el pie; lo demás queda en su orden.
		$omit = $input['omit_blocks'] ?? array();
		if ( ! is_array( $omit ) || count( $omit ) > 40 ) {
			return self::error( 'digitalisimo_sales_omit_blocks', 'omit_blocks debe ser una lista de claves de bloque (list-templates con template).' );
		}
		$blocks = array();
		foreach ( (array) ( $meta['blocks'] ?? array() ) as $block ) {
			$blocks[ $block['key'] ] = $block;
		}
		$omit_ids = array();
		$omitted  = array();
		foreach ( $omit as $key ) {
			if ( ! is_string( $key ) || ! isset( $blocks[ $key ] ) ) {
				return self::error( 'digitalisimo_sales_omit_blocks', sprintf( 'omit_blocks: «%s» no es un bloque de esta plantilla. Bloques, en orden: %s.', is_scalar( $key ) ? (string) $key : '?', implode( ', ', array_keys( $blocks ) ) ) );
			}
			if ( ! empty( $blocks[ $key ]['required'] ) ) {
				return self::error( 'digitalisimo_sales_omit_blocks', sprintf( 'El bloque «%s» no se quita: lleva los avisos legales.', $key ) );
			}
			if ( ! in_array( $key, $omitted, true ) ) {
				$omitted[] = $key;
				$omit_ids  = array_merge( $omit_ids, (array) $blocks[ $key ]['sections'] );
			}
		}

		$content = $template['content'];
		$removed = array();
		if ( $omit_ids ) {
			$dropped = array();
			$content = self::remove( $content, $omit_ids, $dropped );
		}
		if ( null === $close && ! empty( $meta['urgency'] ) ) {
			$content = self::remove( $content, (array) $meta['urgency'], $removed );
		}

		// Reemplazos, WhatsApp y cierre, campo por campo.
		$filled   = 0;
		$close_ph = $close ? array(
			'[día de cierre]'  => $close->format( 'j' ),
			'[mes de cierre]'  => self::MONTHS[ (int) $close->format( 'n' ) - 1 ],
			'[hora de cierre]' => $close->format( 'H:i' ),
		) : array();
		// La fecha de los contadores: la plantilla trae una ya pasada (el widget no admite un placeholder).
		$due = $close ? $close->format( 'Y-m-d H:i' ) : null;
		$resolver = static function ( string $field ) use ( &$plan, &$filled, $close_ph ) {
			return static function ( $key ) use ( &$plan, &$filled, $close_ph, $field ) {
				if ( isset( $close_ph[ $key ] ) ) {
					return $close_ph[ $key ];
				}
				if ( ! isset( $plan[ $key ] ) ) {
					return null;
				}
				$entry =& $plan[ $key ];
				if ( $entry['list'] ) {
					if ( $entry['next'] >= count( $entry['values'] ) ) {
						return null;
					}
					$raw = $entry['values'][ $entry['next'] ];
					++$entry['next'];
				} else {
					$raw = $entry['values'][0];
				}
				$clean = 'rich' === $field ? wp_kses_post( $raw ) : sanitize_text_field( $raw );
				if ( '' === trim( (string) $clean ) ) {
					return null;
				}
				++$entry['used'];
				++$filled;
				return $clean;
			};
		};
		$content = Placeholders::map_strings(
			$content,
			static function ( $owner, $path, $value ) use ( $resolver, $whatsapp, $due ) {
				$field = Placeholders::field( $owner, $path, $value );
				if ( 'date' === $field ) {
					return 'countdown' === $owner && null !== $due ? $due : $value;
				}
				if ( 'url' === $field ) {
					return $value; // Un enlace del kit (ruta del sitio o placeholder de enlace) no se toca.
				}
				if ( 'whatsapp' === $field ) {
					return self::whatsapp_url( $value, $whatsapp, $resolver( 'plain' ) );
				}
				return false === strpos( $value, '[' ) ? $value : Placeholders::fill( $value, $resolver( $field ) );
			}
		);

		$ids     = array();
		$content = self::regenerate_ids( $content, self::template_ids( $template['content'] ), $ids );

		$pending = array();
		$total   = 0;
		foreach ( Placeholders::summary( $content ) as $key => $info ) {
			$pending[] = array(
				'placeholder' => (string) $key,
				'count'       => $info['count'],
				'kind'        => $info['kind'],
			);
			$total    += $info['count'];
		}
		$used = array();
		foreach ( $plan as $key => $entry ) {
			if ( $entry['used'] > 0 ) {
				$used[] = (string) $key;
			}
		}
		// Anclas (#oferta, #historias…) a las que un botón lleva y que ya no están en la página.
		$targets = array();
		$anchors = array();
		Placeholders::map_strings(
			$content,
			static function ( $owner, $path, $value ) use ( &$targets, &$anchors ) {
				if ( '_element_id' === $path ) {
					$targets[ $value ] = true;
				} elseif ( 'url' === Placeholders::field( $owner, $path, $value ) && preg_match( '/^#([A-Za-z][A-Za-z0-9_-]*)$/', $value, $m ) ) {
					$anchors[ $m[1] ] = true;
				}
				return $value;
			}
		);
		$broken = array_values( array_map( 'strval', array_keys( array_diff_key( $anchors, $targets ) ) ) );
		sort( $broken );

		return array(
			'elements'         => $content,
			'omitted_blocks'   => $omitted,
			'broken_anchors'   => $broken,
			'filled'           => $filled,
			'filled_keys'      => $used,
			'ignored'          => $ignored,
			'real_data'        => array_values( array_intersect( $real, $used ) ),
			'pending'          => $pending,
			'pending_count'    => $total,
			'whatsapp_pending' => self::contains( $content, self::sample() ),
			'close_date'       => $close ? $close->format( 'Y-m-d H:i' ) : null,
			'removed_urgency'  => $removed,
		);
	}

	private static function sample(): string {
		return Placeholders::WHATSAPP . Placeholders::WHATSAPP_SAMPLE;
	}

	/** ¿Alguna cadena del árbol contiene `$needle`? */
	private static function contains( array $tree, string $needle ): bool {
		foreach ( $tree as $value ) {
			if ( is_array( $value ) ? self::contains( $value, $needle ) : ( is_string( $value ) && false !== strpos( $value, $needle ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * La URL o la dirección de otro sitio que lleva un valor (con o sin `https://`, `www.` o un esquema peligroso), o
	 * ''. Se permiten `wa.me` y el propio sitio; un correo (`hola@sitio.com`) no es una URL.
	 */
	public static function external_url( string $value, string $site_host ): string {
		$site = strtolower( $site_host );
		$ok   = static function ( string $host ) use ( $site ): bool {
			$host = strtolower( rtrim( $host, '.' ) );
			return 'wa.me' === $host || ( '' !== $site && ( $host === $site || 'www.' . $site === $host ) );
		};
		if ( preg_match( '/\b(?:javascript|data|vbscript|file)\s*:/i', $value, $m ) ) {
			return $m[0];
		}
		if ( preg_match_all( '~(?:[a-z][a-z0-9+.\-]*:)?//([^\s/?#"\'<>]+)[^\s"\'<>]*~i', $value, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				$host = preg_replace( '/:[0-9]+$/', '', (string) preg_replace( '/^[^@]*@/', '', $m[1] ) );
				if ( ! $ok( (string) $host ) ) {
					return $m[0];
				}
			}
		}
		if ( preg_match_all( '~(?<![@\w.\-/])((?:[a-z0-9\-]+\.)+(?:' . self::TLDS . '))(?![a-z0-9\-])(/[^\s"\'<>]*)?~i', $value, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				if ( ! $ok( $m[1] ) ) {
					return $m[0];
				}
			}
		}
		return '';
	}

	/** `close_date` («AAAA-MM-DD HH:MM», hora del sitio): una fecha válida, futura y a menos de dos años. */
	private static function close_date( $value, array $env ) {
		$tz  = isset( $env['timezone'] ) && $env['timezone'] instanceof \DateTimeZone ? $env['timezone'] : new \DateTimeZone( 'UTC' );
		$now = isset( $env['now'] ) ? (int) $env['now'] : time();
		$raw = is_string( $value ) ? trim( $value ) : '';
		$at  = preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/', $raw ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $raw, $tz ) : false;
		if ( ! $at || $at->format( 'Y-m-d H:i' ) !== $raw ) {
			return self::error( 'digitalisimo_sales_close_date', 'close_date: «AAAA-MM-DD HH:MM» en la hora del sitio (p. ej. 2026-12-15 23:59).' );
		}
		if ( $at->getTimestamp() <= $now ) {
			return self::error( 'digitalisimo_sales_close_date', 'close_date ya pasó: la urgencia sólo con un cierre real y futuro.' );
		}
		if ( $at->getTimestamp() > $now + 2 * 365 * 86400 ) {
			return self::error( 'digitalisimo_sales_close_date', 'close_date está a más de dos años: revisa la fecha.' );
		}
		return $at;
	}

	/** El enlace de WhatsApp con el número pedido y su mensaje llenado (texto plano, codificado). */
	private static function whatsapp_url( string $url, string $digits, callable $resolve ): string {
		$rest  = substr( $url, strlen( Placeholders::WHATSAPP ) );
		$parts = explode( '?', $rest, 2 );
		$num   = '' !== $digits ? $digits : $parts[0];
		$query = $parts[1] ?? null;
		if ( null !== $query && 0 === strpos( $query, 'text=' ) && false !== strpos( $query, '[' ) ) {
			$text   = rawurldecode( substr( $query, 5 ) );
			$filled = Placeholders::fill( $text, $resolve );
			if ( $filled !== $text ) {
				$query = 'text=' . str_replace( array( '%5B', '%5D' ), array( '[', ']' ), rawurlencode( $filled ) );
			}
		}
		return Placeholders::WHATSAPP . $num . ( null !== $query ? '?' . $query : '' );
	}

	/** Quita los elementos con esos IDs (y lo que contienen); anota su ID y su nombre en el Navegador. */
	private static function remove( array $elements, array $ids, array &$removed ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['id'] ) && in_array( $element['id'], $ids, true ) ) {
				$removed[] = array(
					'id'    => (string) $element['id'],
					'title' => (string) ( $element['settings']['_title'] ?? ( $element['widgetType'] ?? $element['elType'] ?? '' ) ),
				);
				continue;
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::remove( $element['elements'], $ids, $removed );
			}
			$out[] = $element;
		}
		return $out;
	}

	/** IDs de la plantilla (elementos y `_id` de listas). */
	private static function template_ids( array $elements, array &$ids = array() ): array {
		foreach ( $elements as $key => $value ) {
			if ( is_array( $value ) ) {
				self::template_ids( $value, $ids );
			} elseif ( ( 'id' === $key || '_id' === $key ) && is_string( $value ) ) {
				$ids[ $value ] = true;
			}
		}
		return $ids;
	}

	/** Un ID nuevo de siete hexadecimales que no está en la plantilla ni se repite en la página. */
	private static function new_id( array $template_ids, array &$used ): string {
		do {
			$id = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
		} while ( isset( $template_ids[ $id ] ) || isset( $used[ $id ] ) );
		$used[ $id ] = true;
		return $id;
	}

	private static function regenerate_ids( array $elements, array $template_ids, array &$used ): array {
		foreach ( $elements as $i => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$element['id'] = self::new_id( $template_ids, $used );
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$element['settings'] = self::regenerate_item_ids( $element['settings'], $template_ids, $used );
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::regenerate_ids( $element['elements'], $template_ids, $used );
			}
			$elements[ $i ] = $element;
		}
		return $elements;
	}

	private static function regenerate_item_ids( array $settings, array $template_ids, array &$used ): array {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$settings[ $key ] = self::regenerate_item_ids( $value, $template_ids, $used );
			} elseif ( '_id' === $key ) {
				$settings[ $key ] = self::new_id( $template_ids, $used );
			}
		}
		return $settings;
	}

	private static function error( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
