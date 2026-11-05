<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Origen ACF para el Acordeón, las Pestañas, el Deslizador, la Lista de iconos y la Galería.
 *
 * Cada widget gana una sección «Origen ACF» con la clave del campo (repetidor o galería) y de sus
 * subcampos. Si la clave está rellena, en cada render los elementos salen del valor de ACF de la
 * entrada en curso (o de las opciones, si su grupo está en una página de opciones); el documento
 * sólo guarda las claves. Sin ACF activo, o con la clave vacía, el widget pinta sus elementos propios.
 */
final class Acf_Source {
	const FIELDS = array(
		'digitalisimo-accordion'    => array( 'section_items', array( 'field' => 'Campo repetidor', 'title' => 'Subcampo del título', 'content' => 'Subcampo del contenido' ) ),
		'digitalisimo-fancy-tabs'   => array( 'section_tabs', array( 'field' => 'Campo repetidor', 'title' => 'Subcampo del título', 'sub_title' => 'Subcampo del subtítulo', 'content' => 'Subcampo del contenido' ) ),
		'digitalisimo-fancy-slider' => array( 'section_slides', array( 'field' => 'Campo repetidor', 'title' => 'Subcampo del título', 'image' => 'Subcampo de la imagen', 'content' => 'Subcampo del contenido', 'link' => 'Subcampo del enlace', 'button_text' => 'Texto del botón' ) ),
		'icon-list'                 => array( 'section_icon', array( 'field' => 'Campo repetidor', 'title' => 'Subcampo del título', 'text' => 'Subcampo del texto', 'link' => 'Subcampo del enlace' ) ),
		'image-gallery'             => array( 'section_gallery', array( 'field' => 'Campo galería' ) ),
	);

	public static function init() {
		foreach ( self::FIELDS as $widget => $place ) {
			add_action( 'elementor/element/' . $widget . '/' . $place[0] . '/after_section_end', array( __CLASS__, 'register_controls' ) );
		}
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render' ) );
	}

	public static function register_controls( $widget ) {
		$c      = '\\Elementor\\Controls_Manager';
		$fields = self::FIELDS[ $widget->get_name() ][1] ?? array();
		$widget->start_controls_section( 'digitalisimo_acf', array( 'label' => 'Origen ACF' ) );
		foreach ( $fields as $name => $label ) {
			$widget->add_control( 'digitalisimo_acf_' . $name, array( 'label' => $label, 'type' => $c::TEXT, 'label_block' => true, 'description' => 'button_text' === $name ? '' : 'Clave o nombre del campo de ACF.' ) );
		}
		if ( 'icon-list' === $widget->get_name() ) {
			$widget->add_control( 'digitalisimo_acf_icon', array( 'label' => 'Icono de cada elemento', 'type' => $c::ICONS ) );
		}
		$widget->end_controls_section();
	}

	/** Valor formateado de un campo: el de la entrada en curso o, si su grupo está en una página de opciones, el de las opciones. */
	public static function value( $key ) {
		$key = trim( (string) $key );
		if ( '' === $key || ! function_exists( 'get_field_object' ) ) {
			return null;
		}
		$object = get_field_object( $key );
		if ( is_array( $object ) && self::in_options_page( $object['parent'] ?? 0 ) ) {
			$object = get_field_object( $key, 'option' );
		}
		return is_array( $object ) ? ( $object['value'] ?? null ) : null;
	}

	private static function in_options_page( $parent ) {
		if ( ! $parent || ! function_exists( 'acf_options_page' ) || ! function_exists( 'acf_get_field_groups' ) ) {
			return false;
		}
		foreach ( array_keys( (array) acf_options_page()->get_pages() ) as $slug ) {
			foreach ( acf_get_field_groups( array( 'options_page' => $slug ) ) as $group ) {
				if ( (int) ( $group['ID'] ?? 0 ) === (int) $parent ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Filas de un repetidor como listas de valores por nombre de subcampo. */
	public static function rows( $key ) {
		$value = self::value( $key );
		return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
	}

	public static function text( array $row, $name ) {
		$value = '' !== (string) $name ? ( $row[ $name ] ?? '' ) : '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Imagen en el formato de Elementor, sea cual sea el formato de retorno de ACF. */
	public static function image( $value ) {
		if ( is_array( $value ) ) {
			return array( 'id' => (int) ( $value['ID'] ?? $value['id'] ?? 0 ), 'url' => (string) ( $value['url'] ?? '' ), 'alt' => (string) ( $value['alt'] ?? '' ) );
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return array( 'id' => (int) $value, 'url' => (string) wp_get_attachment_url( (int) $value ), 'alt' => (string) get_post_meta( (int) $value, '_wp_attachment_image_alt', true ) );
		}
		return array( 'id' => 0, 'url' => is_string( $value ) ? $value : '', 'alt' => '' );
	}

	/** Enlace (URL, página o enlace de ACF) en el formato de Elementor. */
	public static function link( $value ) {
		if ( is_array( $value ) ) {
			return array( 'url' => (string) ( $value['url'] ?? '' ), 'is_external' => '_blank' === ( $value['target'] ?? '' ) ? 'on' : '', 'nofollow' => '' );
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return array( 'url' => (string) get_permalink( (int) $value ), 'is_external' => '', 'nofollow' => '' );
		}
		return array( 'url' => is_string( $value ) ? $value : '', 'is_external' => '', 'nofollow' => '' );
	}

	/** Ajustes que sustituyen a los elementos propios del widget, o null si no usa ACF. */
	public static function settings( $widget_name, array $s ) {
		if ( '' === trim( (string) ( $s['digitalisimo_acf_field'] ?? '' ) ) || ! isset( self::FIELDS[ $widget_name ] ) ) {
			return null;
		}
		$sub = static function ( $name ) use ( $s ) {
			return (string) ( $s[ 'digitalisimo_acf_' . $name ] ?? '' );
		};
		$id  = static function ( $index ) {
			return 'acf' . (int) $index;
		};
		$out = array();
		if ( 'image-gallery' === $widget_name ) {
			foreach ( (array) self::value( $s['digitalisimo_acf_field'] ) as $value ) {
				$image = self::image( $value );
				if ( $image['id'] || '' !== $image['url'] ) {
					$out[] = array( 'id' => $image['id'], 'url' => $image['url'] );
				}
			}
			return array( 'wp_gallery' => $out );
		}
		foreach ( self::rows( $s['digitalisimo_acf_field'] ) as $i => $row ) {
			$title = self::text( $row, $sub( 'title' ) );
			switch ( $widget_name ) {
				case 'digitalisimo-accordion':
					$content = self::text( $row, $sub( 'content' ) );
					if ( '' !== $title || '' !== $content ) {
						$out[] = array( '_id' => $id( $i ), 'tab_title' => $title, 'source' => 'custom', 'tab_content' => $content );
					}
					break;
				case 'digitalisimo-fancy-tabs':
					if ( '' !== $title ) {
						$out[] = array( '_id' => $id( $i ), 'icon_type' => 'none', 'tab_title' => $title, 'tab_sub_title' => self::text( $row, $sub( 'sub_title' ) ), 'tab_content' => self::text( $row, $sub( 'content' ) ) );
					}
					break;
				case 'digitalisimo-fancy-slider':
					$out[] = array(
						'_id'          => $id( $i ),
						'title'        => trim( wp_strip_all_tags( $title ) ),
						'description'  => self::text( $row, $sub( 'content' ) ),
						'slide_image'  => '' !== $sub( 'image' ) ? self::image( $row[ $sub( 'image' ) ] ?? '' ) : array( 'url' => '' ),
						'slide_button' => $sub( 'button_text' ),
						'button_link'  => '' !== $sub( 'link' ) ? self::link( $row[ $sub( 'link' ) ] ?? '' ) : array( 'url' => '' ),
					);
					break;
				case 'icon-list':
					// El título en negrita y el texto a continuación.
					$plain = trim( wp_strip_all_tags( $title ) );
					$body  = trim( wp_strip_all_tags( self::text( $row, $sub( 'text' ) ) ) );
					$text  = trim( ( '' !== $plain ? '<strong>' . esc_html( $plain ) . '</strong> ' : '' ) . esc_html( $body ) );
					if ( '' !== $text ) {
						$out[] = array( '_id' => $id( $i ), 'text' => $text, 'selected_icon' => is_array( $s['digitalisimo_acf_icon'] ?? null ) ? $s['digitalisimo_acf_icon'] : array( 'value' => '', 'library' => '' ), 'link' => '' !== $sub( 'link' ) ? self::link( $row[ $sub( 'link' ) ] ?? '' ) : array( 'url' => '' ) );
					}
					break;
			}
		}
		$keys = array( 'digitalisimo-accordion' => 'tabs', 'digitalisimo-fancy-tabs' => 'tabs', 'digitalisimo-fancy-slider' => 'slides', 'icon-list' => 'icon_list' );
		return array( $keys[ $widget_name ] => $out );
	}

	/** Antes de pintar el widget, sustituye sus elementos por los de ACF. */
	public static function before_render( $element ) {
		$name = $element->get_name();
		if ( ! isset( self::FIELDS[ $name ] ) ) {
			return;
		}
		$settings = self::settings( $name, (array) $element->get_settings_for_display() );
		if ( null === $settings ) {
			return;
		}
		foreach ( $settings as $key => $value ) {
			$element->set_settings( $key, $value );
		}
		if ( method_exists( $element, 'reset_render_state' ) ) {
			$element->reset_render_state();
		}
	}
}
