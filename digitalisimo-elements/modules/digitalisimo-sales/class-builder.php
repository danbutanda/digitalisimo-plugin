<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Arma los elementos de una página a partir de una plantilla del kit y de la entrada cerrada de `create-page`.
 *
 * Candados (D-0023, D-0024 y el brief del kit):
 * - sólo cambia lo que se pide: un placeholder sin dato se queda tal cual y se informa como pendiente;
 * - los placeholders de cierre sólo se llenan con `close_date` (una fecha real y futura), que admiten las plantillas
 *   con contador o con textos de cierre (01, 02 y 05); sin ella se quitan los bloques que sólo sirven con un cierre
 *   real (los del manifiesto: la barra, el aviso, el contador del carrito) y los textos de cierre quedan pendientes;
 * - un enlace (video, grupo, redes) nunca se llena: se pone en el editor;
 * - un valor sólo lleva texto y formato en línea sin atributos (`strong`, `em`, `b`, `i`, `br`): ningún enlace, imagen,
 *   formulario ni atributo; los enlaces sólo existen en la plantilla (rutas del sitio y `wa.me`);
 * - ningún valor lleva una URL de otro sitio (sólo `wa.me` y el propio sitio), tampoco escrita con entidades, barras
 *   invertidas, usuario (`…@sitio`) o sin `//`; un precio es sólo una cifra (la plantilla ya escribe `$` y `MXN`);
 * - cada valor se sanea según su campo: texto plano (`sanitize_text_field()`) o HTML (`wp_kses_post()`);
 * - todos los identificadores (elementos y elementos de listas) se regeneran: ninguno repite uno de la plantilla;
 * - `omit_blocks` quita bloques enteros del manifiesto y nunca reordena: lo que queda sigue en el orden de la
 *   plantilla, el de la guía. Nunca quita un bloque obligatorio (`required`, decisión 5 del operador): la promesa, la
 *   oferta, la garantía, las preguntas frecuentes, WhatsApp (el último enlace `wa.me` de la página) y el pie, en las
 *   plantillas que los tienen, y el formulario (la captación y el carrito); las pruebas sociales sí se quitan;
 * - un valor con precio (`$`, `MXN`, pesos, una cifra con centavos) sólo cae en un bloque que lo admite
 *   (`price_values`: la oferta, el cierre que la repite, los caminos, «Cuánto vale para ti», el carrito y la oferta
 *   única): el precio se revela tarde;
 * - los iconos de redes (04 y 07) llevan su logo y un enlace de muestra del sitio (`#[enlace de tu perfil]`) que se pone
 *   en el editor: se informa cuántos quedan (`social_pending`);
 * - una lista de reemplazos va por aparición en la plantilla entera (el total y el orden de list-templates): se llena
 *   antes de quitar bloques, así que ningún valor se corre a otro bloque; los que caen en un bloque quitado y los que
 *   sobran se informan en `ignored`.
 * No toca la base de datos: lo guarda `Pages`.
 */
final class Builder {
	const MAX_KEYS   = 400;
	const MAX_VALUES = 100;
	const MAX_LENGTH = 3000;

	/** Un precio: 1997, 1,997, 1997.50 o 1,997.50 (la plantilla escribe `$` y `MXN`). */
	const PRICE = '/^(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)(?:\.[0-9]{1,2})?$/';

	const MONTHS = array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );

	/**
	 * Lo que `omit_blocks` no quita, con su nombre (decisión 5 del operador, 2026-11-13; `required_kinds` del
	 * manifiesto, que la prueba compara): un bloque que lleva algo de esto es `required` en el manifiesto.
	 */
	const REQUIRED = array(
		'promesa'              => 'la promesa',
		'oferta'               => 'la oferta',
		'garantia'             => 'la garantía',
		'preguntas-frecuentes' => 'las preguntas frecuentes',
		'whatsapp'             => 'WhatsApp',
		'pie'                  => 'el pie (avisos legales)',
		'formulario'           => 'el formulario',
	);

	/**
	 * Un valor con precio (revisión M2): `$`, `MXN`, «peso(s)», una cifra con centavos (`1997.50`, `1,997.00`, `49,90`) o
	 * una cifra justo después de «precio» o «cuesta». Una cifra suelta con separador de miles («1,250 alumnos») no
	 * cuenta: puede ser un dato real de otro tipo.
	 */
	const PRICE_TEXT = '/\$|\bMXN\b|\bpesos?\b|\b[0-9]+[.,][0-9]{2}\b(?![.,]?[0-9])|\b(?:precio|cuestan?)\s+(?:de\s+)?\$?[0-9]/iu';

	/** El título en el Navegador del logo del evento de la barra de cierre (kit_paginas.py, `logo_barra()`). */
	const EVENT_LOGO_TITLE = 'Logo del evento, si aplica';

	/** El enlace de muestra de los iconos de redes de la 04 y la 07: se pone en el editor (kit_lib.py, PERFIL_URL). */
	const PROFILE_SAMPLE = '#[enlace de tu perfil]';

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
				$tag = self::markup_error( $one );
				if ( '' !== $tag ) {
					return self::error( 'digitalisimo_sales_markup', sprintf( '«%s» lleva HTML que no se admite (%s): sólo texto y formato en línea sin atributos (strong, em, b, i, br). Los enlaces e imágenes van en la plantilla o en el editor.', $key, $tag ) );
				}
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
				'gone'   => array(),
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

		// Fecha de cierre: real y futura, en la zona del sitio; en las plantillas con contador o con textos de cierre
		// (la venta corta nombra el cierre sin contador).
		$close = null;
		if ( isset( $input['close_date'] ) && '' !== $input['close_date'] ) {
			if ( ! self::takes_close_date( $meta, $summary ) ) {
				return self::error( 'digitalisimo_sales_close_date', 'Esta plantilla no lleva fecha de cierre (ni contador ni textos de cierre): quita close_date.' );
			}
			$close = self::close_date( $input['close_date'], $env );
			if ( is_wp_error( $close ) ) {
				return $close;
			}
		}

		// Bloques que se quitan: claves del manifiesto, nunca uno obligatorio (la promesa, la oferta, la garantía, las
		// preguntas frecuentes, WhatsApp o el pie); lo demás queda en su orden. Un solo error nombra todos los
		// obligatorios pedidos.
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
		$kept     = array(); // Obligatorios pedidos: se quedan.
		foreach ( $omit as $key ) {
			if ( ! is_string( $key ) || ! isset( $blocks[ $key ] ) ) {
				return self::error( 'digitalisimo_sales_omit_blocks', sprintf( 'omit_blocks: «%s» no es un bloque de esta plantilla. Bloques, en orden: %s.', is_scalar( $key ) ? (string) $key : '?', implode( ', ', array_keys( $blocks ) ) ) );
			}
			if ( ! empty( $blocks[ $key ]['required'] ) && ! in_array( $key, $kept, true ) ) {
				$kept[] = $key;
			}
		}
		if ( $kept ) {
			return self::error( 'digitalisimo_sales_omit_blocks', self::required_message( $kept, $blocks ) );
		}
		foreach ( $omit as $key ) {
			if ( ! in_array( $key, $omitted, true ) ) {
				$omitted[] = $key;
				$omit_ids  = array_merge( $omit_ids, (array) $blocks[ $key ]['sections'] );
			}
		}

		// Lo que se quita: los bloques omitidos y, sin un cierre real, los de urgencia. Se quita DESPUÉS de llenar: una
		// lista va por aparición en la plantilla entera (el orden y el total de list-templates), así que el valor de
		// cada aparición es el mismo con o sin bloques quitados; los de un bloque quitado se descartan y se informan.
		$content = $template['content'];
		$urgency = null === $close && ! empty( $meta['urgency'] ) ? (array) $meta['urgency'] : array();
		$gone    = self::subtree_ids( $content, array_merge( $omit_ids, $urgency ) );

		// El precio se revela tarde (v4): un valor con precio sólo cae en los bloques que lo admiten (`price_values` del
		// manifiesto: la oferta, el cierre que la repite, los caminos, «Cuánto vale para ti», el carrito y la oferta
		// única). Bloque de cada elemento, por sus secciones de primer nivel.
		$block_of = array();
		$price_ok = array();
		foreach ( $blocks as $key => $block ) {
			$price_ok[ $key ] = ! array_key_exists( 'price_values', $block ) || ! empty( $block['price_values'] );
			foreach ( self::subtree_ids( $content, (array) ( $block['sections'] ?? array() ) ) as $id => $unused ) {
				$block_of[ $id ] = (string) $key;
			}
		}
		$where       = ''; // El bloque del elemento que se está llenando.
		$price_error = null;

		// Reemplazos, WhatsApp y cierre, campo por campo.
		$filled   = 0;
		$here     = false; // ¿El elemento que se está llenando se quita después?
		$close_ph = $close ? array(
			'[día de cierre]'  => $close->format( 'j' ),
			'[mes de cierre]'  => self::MONTHS[ (int) $close->format( 'n' ) - 1 ],
			'[hora de cierre]' => $close->format( 'H:i' ),
		) : array();
		// La fecha de los contadores: la plantilla trae una ya pasada (el widget no admite un placeholder).
		$due = $close ? $close->format( 'Y-m-d H:i' ) : null;
		$resolver = static function ( string $field ) use ( &$plan, &$filled, &$here, &$where, &$price_error, $price_ok, $close_ph ) {
			return static function ( $key ) use ( &$plan, &$filled, &$here, &$where, &$price_error, $price_ok, $close_ph, $field ) {
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
					$index = $entry['next'];
					++$entry['next'];
				} else {
					$index = 0;
				}
				if ( $here ) {
					$entry['gone'][ $index ] = true;
					return null;
				}
				$raw = $entry['values'][ $index ];
				if ( null === $price_error && '' !== $where && empty( $price_ok[ $where ] ) && self::looks_like_price( $raw ) ) {
					$price_error = array( (string) $key, $raw, $where );
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
			static function ( $owner, $path, $value, $id = '' ) use ( $resolver, $whatsapp, $due, $gone, $block_of, &$here, &$where ) {
				$here  = isset( $gone[ $id ] );
				$where = $block_of[ $id ] ?? '';
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
		if ( null !== $price_error ) {
			list( $key, $raw, $block ) = $price_error;
			return self::error( 'digitalisimo_sales_price_outside_offer', sprintf( '«%s» lleva un precio o una cifra en pesos («%s») y cae en el bloque «%s», que no lleva precio: el precio se revela tarde, sólo en la oferta (y en el cierre que la repite, los caminos, «Cuánto vale para ti», el carrito y la oferta única; price: true en list-templates). Pon el precio en los placeholders de precio de la oferta, sólo la cifra.', $key, function_exists( 'mb_substr' ) ? mb_substr( $raw, 0, 80 ) : substr( $raw, 0, 80 ), $block ) );
		}
		$removed = array();
		if ( $omit_ids ) {
			$dropped = array();
			$content = self::remove( $content, $omit_ids, $dropped );
		}
		if ( $urgency ) {
			$content = self::remove( $content, $urgency, $removed );
		}
		// Valores que no llegaron a la página: los de un bloque quitado y los que sobran.
		foreach ( $plan as $key => $entry ) {
			$gone_at = array_keys( $entry['gone'] );
			if ( $entry['list'] ) {
				if ( $gone_at ) {
					$ignored[] = array(
						'placeholder' => (string) $key,
						'reason'      => sprintf( 'Los valores %s iban en bloques que se quitaron (omit_blocks, o la urgencia sin close_date): no están en la página. Una lista va por aparición en la plantilla entera, en el orden de list-templates.', self::positions( $gone_at ) ),
						'values'      => self::numbers( $gone_at ),
					);
				}
				if ( $entry['next'] < count( $entry['values'] ) ) {
					$extra     = range( $entry['next'], count( $entry['values'] ) - 1 );
					$ignored[] = array(
						'placeholder' => (string) $key,
						'reason'      => sprintf( 'Sobran %d de %d valores: «%s» aparece %d veces en la plantilla (list-templates con template). Los valores %s no se usaron.', count( $extra ), count( $entry['values'] ), $key, $summary[ $key ]['count'], self::positions( $extra ) ),
						'values'      => self::numbers( $extra ),
					);
				}
			} elseif ( $gone_at && 0 === $entry['used'] ) {
				$ignored[] = array(
					'placeholder' => (string) $key,
					'reason'      => 'Sólo aparece en bloques que se quitaron (omit_blocks, o la urgencia sin close_date): no está en la página.',
				);
			}
		}

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
		// Y los iconos de redes (04 y 07) que siguen con el enlace de muestra: el de cada perfil se pone en el editor.
		$targets = array();
		$anchors = array();
		$social  = 0;
		Placeholders::map_strings(
			$content,
			static function ( $owner, $path, $value ) use ( &$targets, &$anchors, &$social ) {
				if ( '_element_id' === $path ) {
					$targets[ $value ] = true;
				} elseif ( 'url' === Placeholders::field( $owner, $path, $value ) && preg_match( '/^#([A-Za-z][A-Za-z0-9_-]*)$/', $value, $m ) ) {
					$anchors[ $m[1] ] = true;
				}
				if ( 'social-icons' === $owner && 'social_icon_list.link.url' === $path && self::PROFILE_SAMPLE === $value ) {
					++$social;
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
			'social_pending'   => $social,
			'event_logo'       => self::event_logo_sample( $content ),
		);
	}

	/**
	 * ¿La plantilla admite `close_date`? Si lleva contador o algún placeholder de cierre (`[día de cierre]`…).
	 *
	 * @param array $meta    Su entrada del manifiesto.
	 * @param array $summary `Placeholders::summary()` de la plantilla.
	 */
	public static function takes_close_date( array $meta, array $summary ): bool {
		return ! empty( $meta['has_countdown'] ) || (bool) array_intersect_key( $summary, array_flip( Placeholders::CLOSE ) );
	}

	/**
	 * El error de `omit_blocks` cuando pide quitar bloques obligatorios: cuáles, qué llevan y todos los que no se quitan
	 * en esa plantilla.
	 *
	 * @param string[] $asked  Claves obligatorias pedidas.
	 * @param array    $blocks Bloques de la plantilla por clave (manifiesto).
	 */
	private static function required_message( array $asked, array $blocks ): string {
		$parts = array();
		foreach ( $asked as $key ) {
			$what = array();
			foreach ( (array) ( $blocks[ $key ]['required_for'] ?? array( 'pie' ) ) as $kind ) {
				$what[] = self::REQUIRED[ $kind ] ?? (string) $kind;
			}
			$parts[] = sprintf( '«%s» (lleva %s)', $key, self::enumerate( $what, ' y ' ) );
		}
		$all = array();
		foreach ( $blocks as $key => $block ) {
			if ( ! empty( $block['required'] ) ) {
				$all[] = (string) $key;
			}
		}
		return sprintf(
			'omit_blocks: no se puede quitar %s. En esta plantilla no se quitan: %s (required: true en list-templates): la promesa, la oferta, la garantía, las preguntas frecuentes, el bloque whatsapp, el pie y el formulario no faltan nunca (decisión del operador). Los demás bloques sí se quitan, también las pruebas sociales; el orden no se cambia.',
			self::enumerate( $parts, ' ni ' ),
			implode( ', ', $all )
		);
	}

	/** «a, b y c» (o «a, b ni c»). */
	private static function enumerate( array $items, string $last ): string {
		$tail = array_pop( $items );
		return $items ? implode( ', ', $items ) . $last . $tail : (string) $tail;
	}

	/** ¿Un valor lleva un precio o una cifra en pesos? (`PRICE_TEXT`). */
	public static function looks_like_price( string $value ): bool {
		return 1 === preg_match( self::PRICE_TEXT, html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/** ¿Queda el logo del evento de la barra con la imagen de muestra (sin una imagen elegida)? */
	private static function event_logo_sample( array $elements ): bool {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = (array) ( $element['settings'] ?? array() );
			if ( 'image' === ( $element['widgetType'] ?? '' ) && self::EVENT_LOGO_TITLE === ( $settings['_title'] ?? '' ) && empty( $settings['image']['url'] ) ) {
				return true;
			}
			if ( self::event_logo_sample( (array) ( $element['elements'] ?? array() ) ) ) {
				return true;
			}
		}
		return false;
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
	 * La primera etiqueta que un valor no puede llevar, o ''. Sólo se admiten `<strong>`, `<em>`, `<b>`, `<i>` (con su
	 * cierre) y `<br>`, sin atributos: nada de `<a>`, `<img>`, `<form>`, `<iframe>`, `<span style>`…
	 */
	public static function markup_error( string $value ): string {
		$rest = (string) preg_replace( '#</?(?:strong|em|b|i)>|<br\s*/?>#i', '', $value );
		return preg_match( '#<\s*[a-z!/?][^>]*>?#i', $rest, $m ) ? substr( $m[0], 0, 60 ) : '';
	}

	/**
	 * La URL de otro sitio que lleva un valor, o ''. Se lee como la leería un navegador: con las entidades decodificadas
	 * (`&#47;` es `/`), `\` como `/` y sin tabuladores ni saltos de línea. Sólo cuenta lo que un navegador toma por
	 * dirección: un esquema pegado a su contenido (`https:otro`, `http://otro…`, también con usuario, `…@sitio`;
	 * `javascript:…`, `data:…`, `vbscript:…`, `file:…`), `//otro…` y `www.otro…`. Se permiten `wa.me` y el propio sitio.
	 * Un texto como «Curso de JavaScript: de cero», «ASP.NET», «Socket.io», «hoy.Es» o un correo no es una dirección:
	 * pasa (un valor no puede llevar enlaces: ver `markup_error()`).
	 */
	public static function external_url( string $value, string $site_host ): string {
		$site = strtolower( $site_host );
		$ok   = static function ( string $host ) use ( $site ): bool {
			$host = strtolower( rtrim( $host, '.' ) );
			return 'wa.me' === $host || ( '' !== $site && ( $host === $site || 'www.' . $site === $host ) );
		};
		$text = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( array( '\\', "\t", "\n", "\r" ), array( '/', '', '', '' ), $text );
		// Un esquema peligroso pegado a su contenido («JavaScript: de cero» lleva un espacio: no es una dirección).
		if ( preg_match( '/(?<![a-z0-9+.\-])(?:javascript|data|vbscript|file):(?=\S)/i', $text, $m ) ) {
			return $m[0];
		}
		// Con esquema: siempre `//`, sin usuario, y el host del sitio o `wa.me`.
		if ( preg_match_all( '~(?<![a-z0-9+.\-])(?:https?|ftp):(?=\S)([^\s"\'<>]*)~i', $text, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				$rest = $m[1];
				if ( 0 !== strpos( $rest, '//' ) ) {
					return $m[0];
				}
				$auth = (string) preg_split( '~[/?#]~', substr( $rest, 2 ) )[0];
				if ( '' === $auth || false !== strpos( $auth, '@' ) || ! $ok( (string) preg_replace( '/:[0-9]*$/', '', $auth ) ) ) {
					return $m[0];
				}
			}
		}
		// Sin esquema: `//otro…`.
		if ( preg_match_all( '~(?<![:/\w])//([^\s/?#"\'<>]+)~', $text, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				if ( false !== strpos( $m[1], '@' ) || ! $ok( (string) preg_replace( '/:[0-9]*$/', '', $m[1] ) ) ) {
					return $m[0];
				}
			}
		}
		// `www.otro…`.
		if ( preg_match_all( '~(?<![\w.\-@/])www\.[^\s/?#"\'<>]*~i', $text, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				if ( false !== strpos( $m[0], '@' ) || ! $ok( (string) preg_replace( '/:[0-9]*$/', '', $m[0] ) ) ) {
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

	/** Los IDs de los elementos con esos IDs y de todo lo que contienen (ID → true). */
	private static function subtree_ids( array $elements, array $ids, bool $inside = false, array &$out = array() ): array {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$in = $inside || ( isset( $element['id'] ) && in_array( $element['id'], $ids, true ) );
			if ( $in && isset( $element['id'] ) ) {
				$out[ (string) $element['id'] ] = true;
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::subtree_ids( $element['elements'], $ids, $in, $out );
			}
		}
		return $out;
	}

	/** Posiciones (desde 1) de los índices de una lista. */
	private static function numbers( array $indexes ): array {
		return array_map(
			static function ( $i ) {
				return (int) $i + 1;
			},
			array_values( $indexes )
		);
	}

	/** «1, 2 y 3» (posiciones desde 1). */
	private static function positions( array $indexes ): string {
		$n    = self::numbers( $indexes );
		$last = array_pop( $n );
		return $n ? implode( ', ', $n ) . ' y ' . $last : (string) $last;
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
