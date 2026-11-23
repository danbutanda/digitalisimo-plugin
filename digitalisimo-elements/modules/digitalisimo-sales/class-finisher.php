<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Termina una página del kit para que nunca se vea a medias (`create-page` con `finish`, lo normal):
 *
 * - llena con datos del sitio lo que el sitio sabe: `[Marca]` (su nombre) y `[año]`;
 * - los enlaces a rutas del kit (`/aviso-de-privacidad/`, `/carrito/`…) van a la página REAL del sitio para eso
 *   (`Site::read()['links']`); si el sitio no la tiene, el enlace se quita (y el botón que lo llevaba) y se informa:
 *   nunca lleva a una dirección que no existe;
 * - sin WhatsApp (ni dado ni en el sitio), los botones al número de muestra van a la página de contacto real o se
 *   quitan, y los textos dejan de mandar a WhatsApp: nunca queda el número de muestra;
 * - las imágenes de muestra del logo (portada, barra y pie) llevan el logo del sitio, o se quitan; las demás imágenes de
 *   muestra se quitan;
 * - ningún `[placeholder]` queda a la vista: un bloque opcional con un dato que falta se quita entero; en uno
 *   obligatorio se quita el elemento de la lista que lo lleva o, si no, el widget (una columna que se queda sin
 *   contenido, sólo títulos, divisores o botones, también) y lo que falta se informa como pendiente, con el bloque y el
 *   campo;
 * - un botón que lleva a un bloque que ya no está se quita.
 *
 * Puro: recibe la página (IDs de la plantilla) y lo que se sabe del sitio; no toca la base de datos.
 */
final class Finisher {
	/** Ajustes que no se ven en la página (el Navegador, CSS, anclas): no cuentan como placeholders visibles. */
	const HIDDEN = '/^(_title|_element_id|_css_classes|custom_css|_attributes|css_classes)$|_css$/';

	/** Widgets que solos no dicen nada: una columna o un bloque opcional con sólo esto se quita. */
	const DECOR = array( 'heading', 'divider', 'spacer', 'button' );

	/** Los bloques cuya imagen de muestra es el logo (la portada, la barra de cierre y el pie). */
	const LOGO_BLOCKS = array( 'promesa', 'barra-de-cierre', 'pie' );

	/** @var array */
	private $site;

	/** @var string */
	private $whatsapp;

	/** @var array<string, string> ID de primer nivel → clave del bloque. */
	private $top = array();

	/** @var array<string, bool> Claves de bloque obligatorias. */
	private $required = array();

	/** @var array<string, bool> Bloques con algún placeholder sin dato (uno opcional se quita entero). */
	private $dirty = array();

	/** @var array */
	private $report = array(
		'filled'        => array(),
		'pending'       => array(),
		'removed'       => array(),
		'links'         => array(),
		'missing_pages' => array(),
		'logo'          => null,
		'whatsapp'      => null,
	);

	/**
	 * @param array  $elements La página (después de llenar y de quitar bloques; IDs de la plantilla).
	 * @param array  $blocks   Bloques del manifiesto por clave (`sections`, `required`).
	 * @param array  $site     `Site::read()` (o lo que dé la prueba).
	 * @param string $whatsapp Los dígitos con los que quedaron los botones de WhatsApp ('' si ninguno).
	 * @param int    $year     El año para `[año]`.
	 * @return array `elements` y el informe (`filled`, `pending`, `removed`, `links`, `missing_pages`, `logo`, `whatsapp`).
	 */
	public static function finish( array $elements, array $blocks, array $site, string $whatsapp, int $year ): array {
		$self           = new self();
		$self->site     = $site;
		$self->whatsapp = $whatsapp;
		foreach ( $blocks as $key => $block ) {
			foreach ( (array) ( $block['sections'] ?? array() ) as $id ) {
				$self->top[ (string) $id ] = (string) $key;
			}
			$self->required[ (string) $key ] = ! empty( $block['required'] );
		}
		$elements = $self->site_values( $elements, $year );
		$out      = array();
		foreach ( $elements as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$block   = $self->top[ (string) ( $section['id'] ?? '' ) ] ?? '';
			$section = $self->walk( $section, $block, 0 );
			if ( null === $section ) {
				continue;
			}
			if ( '' !== $block && empty( $self->required[ $block ] ) && ( ! empty( $self->dirty[ $block ] ) || ( $self->lost_something( $section ) && ! self::has_content( $section ) ) ) ) {
				$self->removed( $block, 'el bloque entero: sin datos reales para llenarlo' );
				continue;
			}
			$out[] = $section;
		}
		$out                        = $self->anchors( $out );
		$self->report['pending']    = array_values( array_unique( $self->report['pending'], SORT_REGULAR ) );
		$self->report['missing_pages'] = array_values( array_unique( $self->report['missing_pages'] ) );
		return array( 'elements' => $out ) + $self->report;
	}

	/** `[Marca]` y `[año]` con los datos del sitio. */
	private function site_values( array $elements, int $year ): array {
		$values = array( '[año]' => (string) $year );
		if ( '' !== trim( (string) ( $this->site['name'] ?? '' ) ) ) {
			$values['[Marca]'] = trim( (string) $this->site['name'] );
		}
		$used = array();
		$elements = Placeholders::map_strings(
			$elements,
			static function ( $owner, $path, $value ) use ( $values, &$used ) {
				if ( false === strpos( $value, '[' ) || 'url' === Placeholders::field( $owner, $path, $value ) ) {
					return $value;
				}
				return Placeholders::fill(
					$value,
					static function ( $key ) use ( $values, &$used ) {
						if ( ! isset( $values[ $key ] ) ) {
							return null;
						}
						$used[ $key ] = true;
						return esc_html_safe( $values[ $key ] );
					}
				);
			}
		);
		foreach ( array_keys( $used ) as $key ) {
			$this->report['filled'][] = array(
				'field'  => $key,
				'source' => '[año]' === $key ? 'el año en curso' : 'el nombre del sitio',
			);
		}
		return $elements;
	}

	/** Un elemento terminado, o null si se quita. */
	private function walk( array $element, string $block, int $depth ): ?array {
		if ( 'widget' === ( $element['elType'] ?? '' ) ) {
			return $this->widget( $element, $block );
		}
		if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) || ! $element['elements'] ) {
			return $element;
		}
		$before = count( $element['elements'] );
		$kept   = array();
		$lost   = false;
		foreach ( $element['elements'] as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$had   = 'widget' !== ( $child['elType'] ?? '' ) && ! empty( $child['elements'] );
			$child = $this->walk( $child, $block, $depth + 1 );
			if ( null === $child ) {
				$lost = true;
				continue;
			}
			// Una columna o un contenedor interior que se quedó sin contenido (sólo títulos o botones) se quita.
			if ( $had && 'widget' !== ( $child['elType'] ?? '' ) && empty( $this->required[ $block ] ) && $this->lost_something( $child ) && ! self::has_content( $child ) ) {
				$this->removed( $block, 'una columna sin datos reales' );
				$lost = true;
				continue;
			}
			$kept[] = $child;
		}
		$element['elements'] = $kept;
		if ( $lost ) {
			$element['_digi_lost'] = true;
		}
		if ( $lost && $kept && count( $kept ) < $before ) {
			$element = self::rebalance( $element );
		}
		if ( ! $kept && $before > 0 ) {
			return $depth > 0 || '' === $block || empty( $this->required[ $block ] ) ? null : $element;
		}
		return $element;
	}

	/** ¿Se le quitó algo a este elemento o a algo de dentro? */
	private function lost_something( array $element ): bool {
		if ( ! empty( $element['_digi_lost'] ) ) {
			return true;
		}
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			if ( is_array( $child ) && $this->lost_something( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Lleva algún widget que diga algo por sí mismo (no sólo títulos, divisores o botones)? */
	public static function has_content( array $element ): bool {
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			if ( 'widget' === ( $child['elType'] ?? '' ) ) {
				if ( ! in_array( (string) ( $child['widgetType'] ?? '' ), self::DECOR, true ) ) {
					return true;
				}
			} elseif ( self::has_content( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/** Las columnas que quedan se reparten el ancho (secciones) o las de una fila (contenedores). */
	public static function rebalance( array $element ): array {
		$cols = array();
		foreach ( $element['elements'] as $i => $child ) {
			if ( 'column' === ( $child['elType'] ?? '' ) || ( 'container' === ( $child['elType'] ?? '' ) && isset( $child['settings']['width']['size'] ) && '%' === ( $child['settings']['width']['unit'] ?? '' ) ) ) {
				$cols[] = $i;
			}
		}
		$row = 'column' === ( $element['elements'][ $cols[0] ?? 0 ]['elType'] ?? '' ) || 'row' === ( $element['settings']['flex_direction'] ?? '' );
		if ( ! $cols || ! $row ) {
			return $element;
		}
		$size = round( 100 / count( $cols ), 4 );
		foreach ( $cols as $i ) {
			$settings =& $element['elements'][ $i ]['settings'];
			if ( 'column' === $element['elements'][ $i ]['elType'] ) {
				$settings['_column_size'] = (int) round( $size );
				$settings['_inline_size'] = $size;
			} else {
				$settings['width']['size'] = $size;
			}
			unset( $settings );
		}
		return $element;
	}

	/** Un widget terminado, o null si se quita. */
	private function widget( array $widget, string $block ): ?array {
		$type     = (string) ( $widget['widgetType'] ?? '' );
		$settings = (array) ( $widget['settings'] ?? array() );

		// Imágenes de muestra: el logo del sitio en la portada, la barra y el pie; las demás se quitan.
		if ( 'image' === $type && empty( $settings['image']['url'] ) ) {
			$logo = $this->site['logo'] ?? null;
			if ( ( in_array( $block, self::LOGO_BLOCKS, true ) || Builder::EVENT_LOGO_TITLE === ( $settings['_title'] ?? '' ) ) && is_array( $logo ) && ! empty( $logo['url'] ) ) {
				$settings['image']          = array(
					'url'    => (string) $logo['url'],
					'id'     => (int) ( $logo['id'] ?? 0 ),
					'alt'    => (string) ( $this->site['name'] ?? '' ),
					'source' => 'library',
				);
				$settings['caption_source'] = '';
				$widget['settings']         = $settings;
				$this->report['logo']       = 'site';
				return $widget;
			}
			if ( in_array( $block, self::LOGO_BLOCKS, true ) && null === $this->report['logo'] ) {
				$this->report['logo'] = 'removed';
			}
			$this->removed( $block, 'una imagen de muestra' );
			return null;
		}
		if ( 'testimonial-carousel' === $type && isset( $settings['slides'] ) && is_array( $settings['slides'] ) ) {
			foreach ( $settings['slides'] as $i => $slide ) {
				if ( is_array( $slide ) && empty( $slide['image']['url'] ) ) {
					$settings['slides'][ $i ]['image'] = array(
						'url' => '',
						'id'  => '',
					);
				}
			}
		}

		// Enlaces del kit, WhatsApp sin número, placeholders: ajuste por ajuste; una lista pierde sólo sus elementos.
		$drop     = false;
		$settings = $this->settings( $settings, $type, $block, '', $drop );
		if ( $drop ) {
			return null;
		}
		$widget['settings'] = $settings;
		return $widget;
	}

	/**
	 * Recorre los ajustes de un widget. Una lista (elementos con `_id`) pierde los elementos que no se pueden terminar;
	 * cualquier otro ajuste así marca `$drop` (el widget se quita).
	 */
	private function settings( array $settings, string $type, string $block, string $prefix, bool &$drop ): array {
		$override = array(); // Ajustes que ya cambió un enlace (el texto y el icono del botón): no se vuelven a tocar.
		foreach ( $settings as $key => $value ) {
			if ( isset( $override[ $key ] ) ) {
				continue;
			}
			$path = is_int( $key ) ? $prefix : ( '' === $prefix ? (string) $key : $prefix . '.' . $key );
			if ( is_array( $value ) ) {
				if ( self::is_list( $value ) ) {
					$items = array();
					foreach ( $value as $item ) {
						$gone = false;
						$item = $this->settings( $item, $type, $block, $path, $gone );
						if ( ! $gone ) {
							$items[] = $item;
						}
					}
					if ( $value && ! $items ) {
						$drop = true;
						return $settings;
					}
					$settings[ $key ] = $items;
					continue;
				}
				if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
					$value = $this->link( $value, $type, $block, $settings, $drop );
					if ( $drop ) {
						return $settings;
					}
					if ( isset( $value['__text'] ) ) {
						$settings['text'] = $value['__text'];
						$override['text'] = true;
						unset( $value['__text'] );
					}
					if ( isset( $value['__icon'] ) ) {
						$settings['selected_icon'] = $value['__icon'];
						$override['selected_icon'] = true;
						unset( $value['__icon'] );
					}
				}
				$settings[ $key ] = $this->settings( $value, $type, $block, $path, $drop );
				if ( $drop ) {
					return $settings;
				}
				continue;
			}
			if ( ! is_string( $value ) || preg_match( self::HIDDEN, (string) $key ) ) {
				continue;
			}
			$last = (string) $key;
			if ( in_array( $last, Placeholders::URL_KEYS, true ) ) {
				if ( false !== strpos( $value, '[' ) ) {
					$this->pending( $block, $type, $path, 'un enlace (video, grupo o perfil) que se pone en el editor' );
					$drop = true;
					return $settings;
				}
				continue;
			}
			$value = $this->text( $value, $block );
			if ( Placeholders::spans( wp_strip_tags_keep( $value ) ) ) {
				foreach ( Placeholders::spans( wp_strip_tags_keep( $value ) ) as $span ) {
					$this->pending( $block, $type, $path, $span[2] );
				}
				$drop = true;
				return $settings;
			}
			if ( '' === trim( wp_strip_tags_keep( $value ) ) && '' !== trim( wp_strip_tags_keep( (string) $settings[ $key ] ) ) ) {
				$drop = true;
				return $settings;
			}
			$settings[ $key ] = $value;
		}
		return $settings;
	}

	/** ¿Una lista de elementos de un repetidor (arreglos con `_id`)? */
	private static function is_list( array $value ): bool {
		if ( ! $value || array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			return false;
		}
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['_id'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Un enlace (`['url' => …]`): rutas del kit a la página real, WhatsApp de muestra a contacto. Marca `$drop` si no
	 * hay a dónde llevarlo. Puede devolver `__text`/`__icon` para cambiar el texto y el icono del botón.
	 */
	private function link( array $link, string $type, string $block, array $settings, bool &$drop ): array {
		$url = (string) $link['url'];
		if ( 0 === strpos( $url, Placeholders::WHATSAPP . Placeholders::WHATSAPP_SAMPLE ) ) {
			$contact = (string) ( $this->site['contact_url'] ?? '' );
			if ( 'button' === $type && '' !== $contact ) {
				$link['url']         = $contact;
				$link['is_external'] = '';
				$link['__text']      = 'Contáctanos';
				$link['__icon']      = array(
					'value'   => 'fas fa-envelope',
					'library' => 'fa-solid',
				);
				$this->report['whatsapp'] = 'contact';
				return $link;
			}
			if ( null === $this->report['whatsapp'] ) {
				$this->report['whatsapp'] = 'removed';
			}
			$this->removed( $block, 'un botón al número de muestra (el sitio no publica un número)' );
			$drop = true;
			return $link;
		}
		if ( 0 === strpos( $url, Placeholders::WHATSAPP ) && false !== strpos( $url, '[' ) ) {
			// El mensaje de muestra con datos sin llenar: el botón abre el chat sin mensaje.
			$link['url'] = strtok( $url, '?' );
		}
		if ( '' !== $url && '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			$real = $this->real_page( $url );
			if ( '' === $real ) {
				$this->pending( $block, $type, 'link.url', sprintf( 'la página %s, que el sitio no tiene', $url ), false );
				$drop = true;
				return $link;
			}
			$link['url'] = $real;
		}
		return $link;
	}

	/** La URL real de una ruta del kit, o ''. Anota qué se resolvió y qué falta. */
	private function real_page( string $path ): string {
		$real = (string) ( $this->site['links'][ $path ] ?? '' );
		if ( '' === $real ) {
			$this->report['missing_pages'][] = $path;
		} else {
			$this->report['links'][ $path ] = $real;
		}
		return $real;
	}

	/** Un texto: sus enlaces del kit a páginas reales (o sin enlace) y, sin WhatsApp, sin mandar a WhatsApp. */
	private function text( string $value, string $block ): string {
		if ( false !== strpos( $value, 'href=' ) ) {
			$value = (string) preg_replace_callback(
				'#<a\s+href="(/[^"/][^"]*|/)"[^>]*>(.*?)</a>#is',
				function ( $m ) {
					$real = $this->real_page( $m[1] );
					return '' === $real ? "\x01" : '<a href="' . esc_attr_safe( $real ) . '">' . $m[2] . '</a>';
				},
				$value
			);
			if ( false !== strpos( $value, "\x01" ) ) {
				// Sin la página: fuera el enlace y su separador (« · »).
				$value = (string) preg_replace( array( '/\s*·\s*\x01/u', '/\x01\s*·\s*/u', '/\x01/' ), '', $value );
			}
		}
		if ( '' === $this->whatsapp && false !== stripos( $value, 'whatsapp' ) ) {
			$contact = '' !== (string) ( $this->site['contact_url'] ?? '' );
			$value   = (string) preg_replace(
				array( '/\s+por WhatsApp\b/u', '/\bsoporte por WhatsApp\b/iu' ),
				$contact ? array( ' desde nuestra página de contacto', 'soporte' ) : array( '', 'soporte' ),
				$value
			);
		}
		return $value;
	}

	/** Botones a un bloque (`#ancla`) que ya no está: se quitan. */
	private function anchors( array $elements ): array {
		$targets = array();
		Placeholders::map_strings(
			$elements,
			static function ( $owner, $path, $value ) use ( &$targets ) {
				if ( '_element_id' === $path ) {
					$targets[ $value ] = true;
				}
				return $value;
			}
		);
		return $this->drop_anchors( $elements, $targets, '' );
	}

	private function drop_anchors( array $elements, array $targets, string $block ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$here = '' === $block ? ( $this->top[ (string) ( $element['id'] ?? '' ) ] ?? '' ) : $block;
			$url  = (string) ( $element['settings']['link']['url'] ?? '' );
			if ( 'widget' === ( $element['elType'] ?? '' ) && preg_match( '/^#([A-Za-z][A-Za-z0-9_-]*)$/', $url, $m ) && ! isset( $targets[ $m[1] ] ) ) {
				$this->removed( $here, sprintf( 'un botón a #%s, un bloque que ya no está', $m[1] ) );
				continue;
			}
			unset( $element['_digi_lost'] );
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = $this->drop_anchors( $element['elements'], $targets, $here );
			}
			$out[] = $element;
		}
		return $out;
	}

	private function pending( string $block, string $widget, string $field, string $what, bool $dirty = true ): void {
		if ( $dirty ) {
			$this->dirty[ $block ] = true;
		}
		if ( '' !== $block && empty( $this->required[ $block ] ) ) {
			return; // Un bloque opcional con un dato que falta se quita entero: no queda nada pendiente de él.
		}
		$this->report['pending'][] = array(
			'block'    => '' === $block ? '(sin bloque)' : $block,
			'required' => ! empty( $this->required[ $block ] ),
			'widget'   => $widget,
			'field'    => $field,
			'missing'  => $what,
		);
	}

	private function removed( string $block, string $what ): void {
		$this->report['removed'][] = array(
			'block' => '' === $block ? '(sin bloque)' : $block,
			'what'  => $what,
		);
	}
}

/** `esc_html()` si existe. */
function esc_html_safe( string $text ): string {
	return function_exists( 'esc_html' ) ? (string) esc_html( $text ) : htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/** `esc_attr()` si existe. */
function esc_attr_safe( string $text ): string {
	return function_exists( 'esc_attr' ) ? (string) esc_attr( $text ) : htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/** El texto visible de un valor (sin etiquetas), para buscar placeholders sin confundir atributos. */
function wp_strip_tags_keep( string $value ): string {
	return html_entity_decode( strip_tags( $value ), ENT_QUOTES, 'UTF-8' );
}
