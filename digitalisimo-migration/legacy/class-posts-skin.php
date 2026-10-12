<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «classic» de `posts` para los adaptadores de entradas de Element Pack.
 *
 * Las pieles de PRO Elements registran sus controles con ganchos fijados al nombre `posts`; esta
 * los registra con el nombre del adaptador, con los mismos IDs de control (`classic_*`), y añade
 * tras los metadatos las categorías y etiquetas que mostraban las rejillas de Element Pack.
 */
class Posts_Skin extends \ElementorPro\Modules\Posts\Skins\Skin_Classic {
	const TERMS = 'digitalisimo_post_terms';
	const LABEL = 'digitalisimo_tags_label';

	protected function _register_controls_actions() {
		$name = $this->parent->get_name();
		add_action( 'elementor/element/' . $name . '/section_layout/before_section_end', array( $this, 'register_controls' ) );
		add_action( 'elementor/element/' . $name . '/section_query/after_section_end', array( $this, 'register_style_sections' ) );
		add_action( 'elementor/element/' . $name . '/classic_section_design_layout/after_section_end', array( $this, 'register_additional_design_controls' ) );
	}

	/** Controles de las taxonomías, en el widget (no en la piel) para que el traductor los nombre igual. */
	public static function register_term_controls( \Elementor\Widget_Base $widget ) {
		$c = '\\Elementor\\Controls_Manager';
		$widget->start_controls_section( 'digitalisimo_legacy_terms', array( 'label' => 'Categorías y etiquetas' ) );
		$widget->add_control( self::TERMS, array(
			'label'    => 'Mostrar',
			'type'     => $c::SELECT2,
			'multiple' => true,
			'default'  => array(),
			'options'  => array( 'category' => 'Categorías', 'post_tag' => 'Etiquetas' ),
		) );
		$widget->add_control( self::LABEL, array( 'label' => 'Texto antes de las etiquetas', 'type' => $c::TEXT, 'default' => '' ) );
		$widget->end_controls_section();
	}

	protected function render_meta_data() {
		parent::render_meta_data();
		$settings = $this->parent->get_settings_for_display();
		$show     = array_intersect( array( 'category', 'post_tag' ), (array) ( $settings[ self::TERMS ] ?? array() ) );
		if ( ! $show ) {
			return;
		}
		$out = '';
		foreach ( $show as $taxonomy ) {
			$terms = get_the_terms( get_the_ID(), $taxonomy );
			if ( ! is_array( $terms ) || ! $terms ) {
				continue;
			}
			$links = array();
			foreach ( $terms as $term ) {
				$url = get_term_link( $term );
				if ( ! is_wp_error( $url ) ) {
					$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
				}
			}
			if ( ! $links ) {
				continue;
			}
			$label = 'post_tag' === $taxonomy ? trim( (string) ( $settings[ self::LABEL ] ?? '' ) ) : '';
			$out  .= '<span class="elementor-post__terms elementor-post__terms--' . esc_attr( $taxonomy ) . '">' . ( '' !== $label ? esc_html( $label ) . ' ' : '' ) . implode( ', ', $links ) . '</span>';
		}
		if ( '' !== $out ) {
			echo '<div class="elementor-post__meta-data elementor-post__taxonomies">' . $out . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
		}
	}
}
