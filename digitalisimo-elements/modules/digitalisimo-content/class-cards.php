<?php
namespace Digitalisimo\Elements\Content;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Digitalisimo\\Elements\\Image_Markup', false ) && is_file( dirname( __DIR__ ) . '/digitalisimo-widgets/class-image-markup.php' ) ) {
	require_once dirname( __DIR__ ) . '/digitalisimo-widgets/class-image-markup.php';
}

/**
 * Piezas comunes de las tarjetas propias de C2 (frente B: Llamada a la acción con imagen, Tarjeta giratoria, Lista de
 * precios y Tabla de precios). Sin dependencias de PRO: sólo clases genéricas del núcleo (`Icons_Manager`,
 * `Group_Control_Image_Size`, `Utils`) y el generador de imágenes propio (`Image_Markup`).
 *
 * - `image()`: `<img>` de un control MEDIA con el tamaño de su grupo `Group_Control_Image_Size` (`<prefijo>_size`):
 *   `wp_get_attachment_image()` con `srcset` y las decisiones de carga de WordPress; con «Personalizado» o sin adjunto,
 *   la URL del núcleo con sus medidas.
 * - `icon()`: el icono elegido (SVG o fuente que el sitio ya usa), decorativo, también el FA4 heredado (`fa4compatibility`).
 * - `ribbon()`: la cinta (`digi-ribbon`, posición izquierda o derecha), común a la llamada a la acción y la tabla de precios;
 *   su hoja va en la de cada widget.
 * - `tag()`: etiqueta HTML de la lista permitida o la de respaldo; `link()`: atributos de un control URL con
 *   `esc_url()` (un protocolo no permitido no enlaza), `target` y `rel`.
 * - `inline()`: la edición en línea del lienzo del editor, como `add_inline_editing_attributes()` del núcleo, con las
 *   mismas claves y barras que el widget de PRO (también lo usa la Lista de videos).
 */
final class Cards {
	const TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	/** Etiqueta permitida o la de respaldo. */
	public static function tag( $tag, string $fallback = 'div' ): string {
		$tag = strtolower( (string) $tag );
		return in_array( $tag, self::TAGS, true ) ? $tag : $fallback;
	}

	/** Atributos HTML escapados (`nombre="valor"`), en orden; un valor `null` se omite. */
	public static function attributes( array $attrs ): string {
		$out = array();
		foreach ( $attrs as $name => $value ) {
			if ( null === $value ) {
				continue;
			}
			$value = is_array( $value ) ? implode( ' ', array_filter( array_map( 'strval', $value ), 'strlen' ) ) : (string) $value;
			$out[] = $name . '="' . esc_attr( $value ) . '"';
		}
		return implode( ' ', $out );
	}

	/**
	 * Atributos de un enlace (control URL): `href` con `esc_url()`, `target` y `rel="nofollow"` (como
	 * `add_link_attributes()` del núcleo) y los atributos personalizados permitidos por el núcleo. Sin URL válida, array vacío.
	 */
	public static function link( $link ): array {
		$link = is_array( $link ) ? $link : array();
		$url  = trim( (string) ( $link['url'] ?? '' ) );
		$href = '' === $url ? '' : esc_url( $url );
		if ( '' === $href ) {
			return array();
		}
		$attrs = array( 'href' => html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ) );
		$rel   = array();
		if ( ! empty( $link['is_external'] ) ) {
			$attrs['target'] = '_blank';
		}
		if ( ! empty( $link['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}
		if ( $rel ) {
			$attrs['rel'] = implode( ' ', $rel );
		}
		if ( ! empty( $link['custom_attributes'] ) && class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'parse_custom_attributes' ) ) {
			foreach ( \Elementor\Utils::parse_custom_attributes( (string) $link['custom_attributes'] ) as $name => $value ) {
				$name = strtolower( (string) $name );
				if ( ! isset( $attrs[ $name ] ) && preg_match( '/^[a-z][a-z0-9_.:-]*$/', $name ) && 0 !== strpos( $name, 'on' ) && 'style' !== $name ) {
					$attrs[ $name ] = (string) $value;
				}
			}
		}
		return $attrs;
	}

	/**
	 * `<img>` de un control MEDIA con el tamaño de su grupo de resolución (`$prefix . '_size'` en `$settings`).
	 *
	 * @param array  $image    Valor del control (`id`, `url`, `alt`).
	 * @param array  $settings Ajustes del widget (tamaño y medidas personalizadas).
	 * @param string $prefix   Nombre del grupo `Group_Control_Image_Size`.
	 * @param array  $args     `class`, `alt` (respaldo tras el de Medios), `decorative`, `sizes`.
	 */
	public static function image( array $image, array $settings, string $prefix, array $args = array() ): string {
		$id   = absint( $image['id'] ?? 0 );
		$size = (string) ( $settings[ $prefix . '_size' ] ?? 'full' );
		$size = '' === $size ? 'full' : $size;
		if ( 'custom' === $size ) {
			$url = '';
			if ( $id && class_exists( '\\Elementor\\Group_Control_Image_Size' ) ) {
				$url = (string) \Elementor\Group_Control_Image_Size::get_attachment_image_src( $id, $prefix, $settings );
			}
			$dim   = (array) ( $settings[ $prefix . '_custom_dimension' ] ?? array() );
			$image = array(
				'id'     => 0,
				'url'    => '' !== $url ? $url : (string) ( $image['url'] ?? '' ),
				'alt'    => '' !== (string) ( $image['alt'] ?? '' ) ? $image['alt'] : ( $id && class_exists( '\\Digitalisimo\\Elements\\Image_Markup' ) ? \Digitalisimo\Elements\Image_Markup::alt( array( 'id' => $id ) ) : '' ),
				'width'  => absint( $dim['width'] ?? 0 ),
				'height' => absint( $dim['height'] ?? 0 ),
			);
			$size  = 'full';
		}
		if ( ! class_exists( '\\Digitalisimo\\Elements\\Image_Markup' ) ) {
			return '';
		}
		return \Digitalisimo\Elements\Image_Markup::img( $image, array( 'size' => $size ) + $args );
	}

	/**
	 * Icono decorativo de un control ICONS (`$key`), o el FA4 heredado (`$fa4`) que guardaban las versiones antiguas.
	 */
	public static function icon( array $settings, string $key, string $fa4 = 'icon' ): string {
		$legacy   = (string) ( $settings[ $fa4 ] ?? '' );
		$migrated = isset( $settings['__fa4_migrated'][ $key ] );
		if ( '' !== $legacy && ! $migrated ) {
			return '<i class="' . esc_attr( $legacy ) . '" aria-hidden="true"></i>';
		}
		$icon = $settings[ $key ] ?? array();
		if ( empty( $icon['value'] ) || ! class_exists( '\\Elementor\\Icons_Manager' ) ) {
			return '';
		}
		ob_start();
		\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}

	/** Cinta (`ribbon_title`, `ribbon_horizontal_position`); '' sin título. Su texto se edita en el lienzo, como en PRO. */
	public static function ribbon( array $settings ): string {
		$title = (string) ( $settings['ribbon_title'] ?? '' );
		if ( '' === trim( $title ) ) {
			return '';
		}
		$position = (string) ( $settings['ribbon_horizontal_position'] ?? '' );
		$class    = 'digi-ribbon' . ( in_array( $position, array( 'left', 'right' ), true ) ? ' digi-ribbon--' . $position : '' );
		return '<div class="' . $class . '"><div ' . self::attributes( self::inline( array( 'class' => 'digi-ribbon__inner' ), 'ribbon_title' ) ) . '>' . wp_kses_post( $title ) . '</div></div>';
	}

	/** ¿Se pinta para el lienzo del editor? (`is_edit_mode()` del núcleo: también en el pintado remoto del editor). */
	public static function edit_mode(): bool {
		$plugin = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		return is_object( $plugin ) && isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode();
	}

	/**
	 * Atributos de un texto con la edición en línea del lienzo, como `add_inline_editing_attributes()` del núcleo: sólo en
	 * el editor, la clase `elementor-inline-editing`, `data-elementor-setting-key` (en un repetidor,
	 * `<repetidor>.<índice>.<campo>`) y, salvo con la barra básica, `data-elementor-inline-editing-toolbar` (`none`,
	 * `advanced`). El comportamiento del núcleo (`InlineEditing`) actúa sobre ellos también con el pintado remoto. Fuera
	 * del editor devuelve los atributos tal cual: el front no cambia.
	 */
	public static function inline( array $attrs, string $key, string $toolbar = 'basic' ): array {
		if ( ! self::edit_mode() ) {
			return $attrs;
		}
		$class          = $attrs['class'] ?? '';
		$attrs['class'] = trim( ( is_array( $class ) ? implode( ' ', $class ) : (string) $class ) . ' elementor-inline-editing' );
		$attrs['data-elementor-setting-key'] = $key;
		if ( 'basic' !== $toolbar ) {
			$attrs['data-elementor-inline-editing-toolbar'] = $toolbar;
		}
		return $attrs;
	}

	/** `inline()` como cadena de atributos con un espacio delante ('' sin atributos), para el marcado que se concatena. */
	public static function inline_attributes( array $attrs, string $key, string $toolbar = 'basic' ): string {
		$out = self::attributes( self::inline( $attrs, $key, $toolbar ) );
		return '' === $out ? '' : ' ' . $out;
	}

	/** Tamaños del botón del núcleo (`elementor-size-*`). */
	public static function button_size( $size, string $fallback ): string {
		return in_array( (string) $size, array( 'xs', 'sm', 'md', 'lg', 'xl' ), true ) ? (string) $size : $fallback;
	}

	/** Texto llano para el Markdown (como `Utils::html_to_plain_text()` del núcleo). */
	public static function plain( $html ): string {
		if ( class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'html_to_plain_text' ) ) {
			return (string) \Elementor\Utils::html_to_plain_text( (string) $html );
		}
		return trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/** Encabezado Markdown con el nivel de su etiqueta (h1–h6; otra, nivel 2). */
	public static function markdown_heading( string $text, string $tag ): string {
		$level = preg_match( '/^h([1-6])$/', $tag, $m ) ? (int) $m[1] : 2;
		return str_repeat( '#', $level ) . ' ' . $text;
	}

	/** Botón Markdown: texto en negrita, enlazado si hay URL. */
	public static function markdown_button( string $text, string $url = '' ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}
		return '' !== $url ? '[**' . $text . '**](' . esc_url( $url ) . ')' : '**' . $text . '**';
	}

	/** Imagen Markdown de un control MEDIA como la de PRO: la URL guardada o la del adjunto, y su texto alternativo o «image». */
	public static function markdown_image( array $media ): string {
		$url = (string) ( $media['url'] ?? '' );
		$id  = absint( $media['id'] ?? 0 );
		if ( '' === $url && $id && function_exists( 'wp_get_attachment_image_src' ) ) {
			$src = wp_get_attachment_image_src( $id, 'full' );
			$url = (string) ( $src[0] ?? '' );
		}
		$url = esc_url( $url );
		if ( '' === $url ) {
			return '';
		}
		$alt = $id && class_exists( '\\Digitalisimo\\Elements\\Image_Markup' ) ? \Digitalisimo\Elements\Image_Markup::alt( array( 'id' => $id ), '', true ) : '';
		$alt = trim( str_replace( array( '[', ']' ), '', $alt ) );
		return '![' . ( '' !== $alt ? $alt : 'image' ) . '](' . $url . ')';
	}
}
