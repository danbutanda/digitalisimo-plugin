<?php
namespace Digitalisimo\Elements\F7b\Overrides;

defined( 'ABSPATH' ) || exit;

/**
 * Sobrescrituras del widget Plantilla: forma de las filas y sustitución en los datos (F7b, frente E; V.2).
 *
 * Funciones puras, sin ganchos: `Render` las llama desde `elementor/frontend/builder_content_data` y `Ajax` las usa
 * para listar las claves de una plantilla y contar las instancias que sobrescriben cada una.
 *
 * - **Clave** (`_digitalisimo_slot`): `[a-z0-9_-]{1,40}` en un widget de `TARGETS`. Una clave con otra forma, o en otro
 *   tipo de elemento, nunca coincide.
 * - **Fila** (`digitalisimo_overrides.*`): `slot`, `kind` (`KINDS`) y su valor. El saneado se limita a la forma
 *   (V.2): lista cerrada de `kind`, URL con `esc_url_raw()`, ID de adjunto entero y texto escapado con `esc_html()`
 *   (un `text` es texto, no HTML). El HTML de `html` se confía como en el núcleo: el documento ya pasó por
 *   `Utils::kses_post_deep()` al guardar si su autor no tiene `unfiltered_html` (`elementor/core/base/document.php:856-858`);
 *   nunca kses al pintar (C1-24). Un `link` sólo lleva `url`, `is_external` y `nofollow`: los `custom_attributes`
 *   de la fila se ignoran y se conservan los del elemento de la plantilla (C2-31 b).
 * - **Sustitución:** cada fila cambia el ajuste que `TARGETS` declara para el tipo del elemento y quita su etiqueta
 *   dinámica (`__dynamic__`), que si no taparía el valor; `hide` quita el elemento (y sus hijos) de los datos. Un
 *   `kind` que el tipo no declara se ignora. Los IDs de los elementos no cambian (C1-7).
 */
final class Apply {
	/** Clave de cada elemento de la plantilla. */
	const SLOT = '_digitalisimo_slot';

	/** Repetidor del widget Plantilla. */
	const REPEATER = 'digitalisimo_overrides';

	const SLOT_PATTERN = '/^[a-z0-9_-]{1,40}$/';

	const KINDS = array( 'text', 'html', 'image', 'link', 'hide' );

	/** `kind` de una fila guardada sin él: el editor no guarda el valor por defecto del control (`removeDataDefaults`). */
	const DEFAULT_KIND = 'text';

	/** Filas que se leen por instancia y claves que se listan por plantilla. */
	const MAX_ROWS  = 50;
	const MAX_SLOTS = 200;

	/**
	 * Ajuste que cambia cada `kind` en cada tipo de elemento. `hide` vale para todos. `video_url` es la URL de la
	 * fuente elegida (`video_type`); `link` en Imagen pasa `link_to` a `custom` y `text` en Imagen es el pie
	 * (`caption_source` = `custom`).
	 */
	const TARGETS = array(
		'heading'     => array(
			'text' => 'title',
			'html' => 'title',
			'link' => 'link',
		),
		'text-editor' => array(
			'text' => 'editor',
			'html' => 'editor',
		),
		'image'       => array(
			'text'  => 'caption',
			'image' => 'image',
			'link'  => 'link',
		),
		'button'      => array(
			'text' => 'text',
			'link' => 'link',
		),
		'icon'        => array(
			'link' => 'link',
		),
		'video'       => array(
			'image' => 'image_overlay',
			'link'  => 'video_url',
		),
		'icon-box'    => array(
			'text' => 'title_text',
			'html' => 'description_text',
			'link' => 'link',
		),
	);

	/** Fuentes del Vídeo con una URL de texto propia (`<fuente>_url`). */
	const VIDEO_SOURCES = array( 'youtube', 'vimeo', 'dailymotion', 'videopress' );

	/** Ajustes de los que sale el extracto que el editor muestra junto a cada clave. */
	const LABEL_SETTINGS = array(
		'heading'     => 'title',
		'text-editor' => 'editor',
		'image'       => 'caption',
		'button'      => 'text',
		'icon-box'    => 'title_text',
	);

	/** ¿Tiene forma de clave? */
	public static function valid_slot( $slot ): bool {
		return is_string( $slot ) && 1 === preg_match( self::SLOT_PATTERN, $slot );
	}

	/** `kind` que admite un tipo de elemento (los de `TARGETS` y `hide`), o ninguno si el tipo no tiene clave. */
	public static function kinds_for( string $type ): array {
		if ( ! isset( self::TARGETS[ $type ] ) ) {
			return array();
		}
		return array_merge( array_keys( self::TARGETS[ $type ] ), array( 'hide' ) );
	}

	/** Mapa tipo => `kind` admitidos, para el script del editor. */
	public static function kinds_map(): array {
		$map = array();
		foreach ( array_keys( self::TARGETS ) as $type ) {
			$map[ $type ] = self::kinds_for( $type );
		}
		return $map;
	}

	/**
	 * Clave válida de un elemento de los datos, o `''`.
	 *
	 * @param mixed $element Datos de un elemento (`elType`, `widgetType`, `settings`…).
	 */
	public static function slot_of( $element ): string {
		if ( ! is_array( $element ) || 'widget' !== ( $element['elType'] ?? '' ) ) {
			return '';
		}
		$type = $element['widgetType'] ?? '';
		if ( ! is_string( $type ) || ! isset( self::TARGETS[ $type ] ) ) {
			return '';
		}
		$slot = $element['settings'][ self::SLOT ] ?? '';
		return self::valid_slot( $slot ) ? $slot : '';
	}

	/**
	 * Filas válidas de un repetidor guardado, con su valor ya con forma: `[ [ slot, kind, value ], … ]`.
	 *
	 * Se descarta en silencio una fila sin clave válida, con un `kind` fuera de la lista o sin valor (texto vacío,
	 * imagen sin ID ni URL, enlace sin URL). Como mucho `MAX_ROWS` filas.
	 *
	 * @param mixed $raw Valor de `digitalisimo_overrides`.
	 */
	public static function rows( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$rows = array();
		foreach ( $raw as $row ) {
			if ( count( $rows ) >= self::MAX_ROWS ) {
				break;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slot = $row['slot'] ?? '';
			$kind = array_key_exists( 'kind', $row ) ? $row['kind'] : self::DEFAULT_KIND;
			if ( ! self::valid_slot( $slot ) || ! is_string( $kind ) || ! in_array( $kind, self::KINDS, true ) ) {
				continue;
			}
			$value = self::value( $kind, $row );
			if ( null === $value ) {
				continue;
			}
			$rows[] = array(
				'slot'  => $slot,
				'kind'  => $kind,
				'value' => $value,
			);
		}
		return $rows;
	}

	/**
	 * Valor de una fila según su `kind`, con forma, o `null` si no hay nada que aplicar.
	 *
	 * @return mixed
	 */
	private static function value( string $kind, array $row ) {
		switch ( $kind ) {
			case 'text':
			case 'html':
				$text = $row[ $kind ] ?? '';
				if ( ! is_scalar( $text ) || '' === trim( (string) $text ) ) {
					return null;
				}
				return 'text' === $kind ? esc_html( (string) $text ) : (string) $text;
			case 'image':
				$image = is_array( $row['image'] ?? null ) ? $row['image'] : array();
				$id    = isset( $image['id'] ) && is_scalar( $image['id'] ) ? absint( $image['id'] ) : 0;
				$url   = isset( $image['url'] ) && is_string( $image['url'] ) ? esc_url_raw( trim( $image['url'] ) ) : '';
				if ( ! $id && '' === $url ) {
					return null;
				}
				return array(
					'id'  => $id ? $id : '',
					'url' => $url,
				);
			case 'link':
				$link = is_array( $row['link'] ?? null ) ? $row['link'] : array();
				$url  = isset( $link['url'] ) && is_string( $link['url'] ) ? esc_url_raw( trim( $link['url'] ) ) : '';
				if ( '' === $url ) {
					return null;
				}
				return array(
					'url'         => $url,
					'is_external' => self::on( $link['is_external'] ?? '' ),
					'nofollow'    => self::on( $link['nofollow'] ?? '' ),
				);
			case 'hide':
				return true;
		}
		return null;
	}

	/** Casilla del control URL: `on` o vacío. */
	private static function on( $value ): string {
		return ( 'on' === $value || 'yes' === $value || true === $value ) ? 'on' : '';
	}

	/**
	 * Aplica las filas a los datos de una plantilla y devuelve los datos nuevos. Sin filas, los datos tal cual.
	 *
	 * @param array $elements Datos de elementos (`get_elements_data()`).
	 * @param array $rows     Filas de `rows()`.
	 */
	public static function apply( array $elements, array $rows ): array {
		if ( ! $rows ) {
			return $elements;
		}
		$by_slot = array();
		foreach ( $rows as $row ) {
			$by_slot[ $row['slot'] ][] = $row;
		}
		return self::walk( $elements, $by_slot );
	}

	private static function walk( array $elements, array $by_slot ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				$out[] = $element;
				continue;
			}
			$slot = self::slot_of( $element );
			if ( '' !== $slot && isset( $by_slot[ $slot ] ) ) {
				$hidden = false;
				foreach ( $by_slot[ $slot ] as $row ) {
					if ( 'hide' === $row['kind'] ) {
						$hidden = true;
						break;
					}
					$settings            = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
					$element['settings'] = self::set( (string) $element['widgetType'], $settings, $row['kind'], $row['value'] );
				}
				if ( $hidden ) {
					continue;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk( $element['elements'], $by_slot );
			}
			$out[] = $element;
		}
		return $out;
	}

	/**
	 * Cambia en `$settings` el ajuste que corresponde a `$kind` en este tipo.
	 *
	 * @param mixed $value Valor con forma de `rows()`.
	 */
	public static function set( string $type, array $settings, string $kind, $value ): array {
		$key = self::TARGETS[ $type ][ $kind ] ?? '';
		if ( '' === $key ) {
			return $settings;
		}
		switch ( $kind ) {
			case 'text':
			case 'html':
				if ( 'caption' === $key ) {
					$settings['caption_source'] = 'custom';
				}
				$settings[ $key ] = $value;
				return self::static_value( $settings, $key );
			case 'image':
				$settings[ $key ] = $value;
				return self::static_value( $settings, $key );
			case 'link':
				if ( 'video_url' === $key ) {
					return self::video_url( $settings, $value['url'] );
				}
				$link = is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array();
				$link['url']         = $value['url'];
				$link['is_external'] = $value['is_external'];
				$link['nofollow']    = $value['nofollow'];
				$settings[ $key ]    = $link;
				if ( 'image' === $type ) {
					$settings['link_to'] = 'custom';
				}
				return self::static_value( $settings, $key );
		}
		return $settings;
	}

	/** La URL de la fuente del Vídeo (`video_type`, `youtube` si falta). */
	private static function video_url( array $settings, string $url ): array {
		$source = isset( $settings['video_type'] ) && is_string( $settings['video_type'] ) && '' !== $settings['video_type'] ? $settings['video_type'] : 'youtube';
		if ( in_array( $source, self::VIDEO_SOURCES, true ) ) {
			$settings[ $source . '_url' ] = $url;
			return self::static_value( $settings, $source . '_url' );
		}
		if ( 'hosted' === $source ) {
			$external                 = is_array( $settings['external_url'] ?? null ) ? $settings['external_url'] : array();
			$external['url']          = $url;
			$settings['external_url'] = $external;
			$settings['insert_url']   = 'yes';
			return self::static_value( self::static_value( $settings, 'external_url' ), 'hosted_url' );
		}
		return $settings;
	}

	/** Quita la etiqueta dinámica de un ajuste: si no, taparía el valor sobrescrito. */
	private static function static_value( array $settings, string $key ): array {
		if ( isset( $settings['__dynamic__'] ) && is_array( $settings['__dynamic__'] ) && array_key_exists( $key, $settings['__dynamic__'] ) ) {
			unset( $settings['__dynamic__'][ $key ] );
		}
		return $settings;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Lectura para el editor.

	/**
	 * Claves de una plantilla: `[ [ key, type, count, label ], … ]` en el orden en que aparecen, como mucho `MAX_SLOTS`.
	 * `count` > 1 es una clave repetida (D12: el editor de la plantilla lo avisa).
	 */
	public static function slots( array $elements ): array {
		$found = array();
		self::collect( $elements, $found );
		return array_values( $found );
	}

	private static function collect( array $elements, array &$found ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$slot = self::slot_of( $element );
			if ( '' !== $slot ) {
				if ( isset( $found[ $slot ] ) ) {
					++$found[ $slot ]['count'];
				} elseif ( count( $found ) < self::MAX_SLOTS ) {
					$type           = (string) $element['widgetType'];
					$found[ $slot ] = array(
						'key'   => $slot,
						'type'  => $type,
						'count' => 1,
						'label' => self::label( $type, $element['settings'] ),
					);
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect( $element['elements'], $found );
			}
		}
	}

	/** Extracto de texto plano (≤ 40 caracteres) del contenido del elemento, para reconocerlo en la lista. */
	private static function label( string $type, array $settings ): string {
		$key  = self::LABEL_SETTINGS[ $type ] ?? '';
		$text = '' !== $key && isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ? $settings[ $key ] : '';
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > 40 ) {
			return rtrim( mb_substr( $text, 0, 39, 'UTF-8' ) ) . '…';
		}
		return strlen( $text ) > 40 ? rtrim( substr( $text, 0, 39 ) ) . '…' : $text;
	}

	/**
	 * Suma a `$counts` (clave => instancias) las instancias del widget Plantilla de estos datos que apuntan a
	 * `$template` y sobrescriben cada clave. Una instancia cuenta una vez por clave.
	 */
	public static function count_instances( array $elements, int $template, array &$counts ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( 'widget' === ( $element['elType'] ?? '' ) && in_array( $element['widgetType'] ?? '', array( 'template', 'digitalisimo-template' ), true ) ) {
				$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
				$target   = isset( $settings['template_id'] ) && is_scalar( $settings['template_id'] ) ? absint( $settings['template_id'] ) : 0;
				if ( $target === $template ) {
					$slots = array();
					foreach ( self::rows( $settings[ self::REPEATER ] ?? array() ) as $row ) {
						$slots[ $row['slot'] ] = true;
					}
					foreach ( array_keys( $slots ) as $slot ) {
						$counts[ $slot ] = ( $counts[ $slot ] ?? 0 ) + 1;
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::count_instances( $element['elements'], $template, $counts );
			}
		}
	}
}
