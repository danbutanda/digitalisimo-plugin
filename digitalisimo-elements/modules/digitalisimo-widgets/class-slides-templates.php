<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Plantilla de Elementor como contenido de una diapositiva en el widget Diapositivas.
 *
 * Añade a cada diapositiva el campo «Plantilla en lugar del texto»: si se elige una plantilla
 * publicada, su contenido ocupa el lugar de la descripción. Una diapositiva sin plantilla se pinta
 * igual que antes.
 */
final class Slides_Templates {
	const FIELD = 'digitalisimo_template_id';

	private static $depth = 0;
	private static $options = null;

	public static function init() {
		add_action( 'elementor/element/slides/section_slides/before_section_end', array( __CLASS__, 'register_field' ) );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render' ) );
	}

	private static function options() {
		if ( null !== self::$options ) {
			return self::$options;
		}
		self::$options = array();
		if ( ! is_admin() || ! post_type_exists( 'elementor_library' ) ) {
			return self::$options;
		}
		foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) as $post ) {
			self::$options[ $post->ID ] = get_the_title( $post->ID );
		}
		return self::$options;
	}

	public static function register_field( $widget ) {
		$control = $widget->get_controls( 'slides' );
		if ( ! is_array( $control ) || empty( $control['fields'] ) || isset( $control['fields'][ self::FIELD ] ) ) {
			return;
		}
		$fields                = $control['fields'];
		$fields[ self::FIELD ] = array(
			'name'        => self::FIELD,
			'label'       => 'Plantilla en lugar del texto',
			'type'        => \Elementor\Controls_Manager::SELECT2,
			'options'     => self::options(),
			'label_block' => true,
			'default'     => '',
		);
		$widget->update_control( 'slides', array( 'fields' => $fields ) );
	}

	public static function before_render( $element ) {
		if ( ! $element instanceof \ElementorPro\Modules\Slides\Widgets\Slides || self::$depth > 1 ) {
			return;
		}
		$slides  = $element->get_settings( 'slides' );
		$changed = false;
		if ( ! is_array( $slides ) ) {
			return;
		}
		++self::$depth;
		foreach ( $slides as $index => $slide ) {
			$id = absint( $slide[ self::FIELD ] ?? 0 );
			if ( $id && 'publish' === get_post_status( $id ) ) {
				$slides[ $index ]['description'] = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true );
				$changed                         = true;
			}
		}
		--self::$depth;
		if ( $changed ) {
			$element->set_settings( 'slides', $slides );
			if ( method_exists( $element, 'reset_render_state' ) ) {
				$element->reset_render_state();
			}
		}
	}
}
