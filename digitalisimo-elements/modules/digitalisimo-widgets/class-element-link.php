<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Enlace que cubre todo el elemento (widget, sección, columna o contenedor).
 *
 * Inserta un enlace vacío superpuesto con nombre accesible al principio del envoltorio, en vez de
 * anidar el contenido dentro de un `<a>`, para no crear enlaces anidados ni romper el HTML del widget.
 */
final class Element_Link {
	const KEY   = 'digitalisimo_element_link';
	const STYLE = 'digitalisimo-element-link';

	private static $links = array();

	public static function init() {
		foreach ( array( 'common/_section_style', 'section/section_advanced', 'container/section_layout', 'column/section_advanced' ) as $place ) {
			add_action( 'elementor/element/' . $place . '/after_section_end', array( __CLASS__, 'register_controls' ) );
		}
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/after_render', array( __CLASS__, 'after_render' ) );
	}

	public static function register_controls( $element ) {
		$c = '\\Elementor\\Controls_Manager';
		$element->start_controls_section( 'digitalisimo_element_link_section', array( 'label' => 'Enlace del elemento', 'tab' => $c::TAB_ADVANCED ) );
		$element->add_control( self::KEY, array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ), 'placeholder' => 'https://', 'description' => 'Todo el elemento lleva a esta dirección.' ) );
		$element->add_control( 'digitalisimo_element_link_label', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT, 'default' => 'Abrir enlace' ) );
		$element->end_controls_section();
	}

	private static function link( $element ) {
		$link = $element->get_settings_for_display( self::KEY );
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			return null;
		}
		$link['url'] = esc_url( (string) $link['url'], array( 'http', 'https', 'tel', 'mailto', 'sms' ) );
		return '' === $link['url'] ? null : $link;
	}

	public static function before_render( $element ) {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}
		$link = self::link( $element );
		if ( $link ) {
			$label                                 = trim( (string) $element->get_settings_for_display( 'digitalisimo_element_link_label' ) );
			$link['label']                         = '' !== $label ? $label : 'Abrir enlace';
			self::$links[ spl_object_id( $element ) ] = $link;
			ob_start();
		}
	}

	public static function after_render( $element ) {
		$id = spl_object_id( $element );
		if ( ! isset( self::$links[ $id ] ) ) {
			return;
		}
		$link = self::$links[ $id ];
		unset( self::$links[ $id ] );
		$markup = (string) ob_get_clean();
		$rel    = array_filter( array( ! empty( $link['is_external'] ) ? 'noopener noreferrer' : '', ! empty( $link['nofollow'] ) ? 'nofollow' : '' ) );
		$anchor = '<a class="digi-element-link" href="' . $link['url'] . '" aria-label="' . esc_attr( $link['label'] ) . '"'
			. ( ! empty( $link['is_external'] ) ? ' target="_blank"' : '' ) . ( $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '' ) . '></a>';
		if ( preg_match( '/<[a-zA-Z][^>]*>/', $markup, $match, PREG_OFFSET_CAPTURE ) ) {
			$tag    = $match[0][0];
			$new    = preg_replace( '/^<([a-zA-Z][\w-]*)/', '<$1 data-digi-element-link', $tag );
			$at     = $match[0][1] + strlen( $tag );
			$markup = substr( $markup, 0, $match[0][1] ) . $new . $anchor . substr( $markup, $at );
			if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
				wp_register_style( self::STYLE, false, array(), DIGITALISIMO_ELEMENTS_VERSION );
				wp_add_inline_style( self::STYLE, '[data-digi-element-link]{position:relative}.digi-element-link{position:absolute;inset:0;z-index:1}.digi-element-link:focus-visible{outline:2px solid currentColor;outline-offset:2px}' );
			}
			wp_enqueue_style( self::STYLE );
		}
		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ya renderizado por Elementor más un enlace escapado.
	}
}
