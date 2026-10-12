<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Lectura de los campos ACF que nombraban los widgets ACF de Element Pack. Se lee en cada render,
 * nunca al traducir ni al migrar: los datos siguen viviendo en ACF y el documento sólo guarda las
 * claves de los campos.
 */
final class Acf_Source {
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

	/** Como Element Pack: un grupo está en una página de opciones si alguna de ellas lo incluye. */
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

	/** Texto de un subcampo (los arrays de enlace o imagen no son texto). */
	public static function text( array $row, $name ) {
		$value = '' !== (string) $name ? ( $row[ $name ] ?? '' ) : '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Imagen de un subcampo en el formato de Elementor, sea cual sea el formato de retorno de ACF. */
	public static function image( $value ) {
		if ( is_array( $value ) ) {
			return array( 'id' => (int) ( $value['ID'] ?? $value['id'] ?? 0 ), 'url' => (string) ( $value['url'] ?? '' ), 'alt' => (string) ( $value['alt'] ?? '' ) );
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return array( 'id' => (int) $value, 'url' => (string) wp_get_attachment_url( (int) $value ), 'alt' => (string) get_post_meta( (int) $value, '_wp_attachment_image_alt', true ) );
		}
		return array( 'id' => 0, 'url' => is_string( $value ) ? $value : '', 'alt' => '' );
	}

	/** Enlace de un subcampo (URL, página o enlace de ACF) en el formato de Elementor. */
	public static function link( $value ) {
		if ( is_array( $value ) ) {
			return array( 'url' => (string) ( $value['url'] ?? '' ), 'is_external' => '_blank' === ( $value['target'] ?? '' ) ? 'on' : '', 'nofollow' => '' );
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return array( 'url' => (string) get_permalink( (int) $value ), 'is_external' => '', 'nofollow' => '' );
		}
		return array( 'url' => is_string( $value ) ? $value : '', 'is_external' => '', 'nofollow' => '' );
	}
}

/**
 * Adaptadores de los widgets ACF: conservan las claves de los campos como controles propios
 * (sección «Origen ACF») y, en cada render, convierten el valor de ACF en los elementos del
 * widget de destino antes de pintarlo. Cada clase define `ACF_FIELDS` (control => etiqueta) y
 * `acf_settings( array $settings )`, que devuelve los ajustes que sustituyen a los estáticos.
 */
trait Legacy_Acf {
	protected function register_adapter_controls() {
		$this->start_controls_section( 'digitalisimo_acf', array( 'label' => 'Origen ACF' ) );
		foreach ( static::ACF_FIELDS as $name => $label ) {
			$this->add_control( $name, array( 'label' => $label, 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true, 'description' => 'Clave o nombre del campo de ACF.' ) );
		}
		$this->end_controls_section();
	}

	protected function render() {
		foreach ( $this->acf_settings( $this->get_settings_for_display() ) as $key => $value ) {
			$this->set_settings( $key, $value );
		}
		if ( method_exists( $this, 'reset_render_state' ) ) {
			$this->reset_render_state();
		}
		parent::render();
	}

	/** ID estable de un elemento generado: Elementor lo usa en `elementor-repeater-item-*`. */
	protected static function acf_item_id( $index ) {
		return 'acf' . (int) $index;
	}
}
