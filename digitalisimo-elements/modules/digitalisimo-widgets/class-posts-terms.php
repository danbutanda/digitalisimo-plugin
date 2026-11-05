<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Categorías y etiquetas en la piel «Clásica» del widget Entradas.
 *
 * Añade al widget dos controles (qué taxonomías mostrar y un texto antes de las etiquetas) y
 * sustituye la piel «classic» por una subclase que sólo pinta además esas taxonomías tras los
 * metadatos. Conserva el identificador `classic`, sus controles y su JavaScript: un widget que no
 * pide taxonomías se pinta exactamente igual que antes.
 */
final class Posts_Terms {
	const TERMS = 'digitalisimo_post_terms';
	const LABEL = 'digitalisimo_tags_label';

	public static function init() {
		add_action( 'elementor/widget/posts/skins_init', array( __CLASS__, 'replace_skin' ) );
		add_action( 'elementor/element/posts/section_layout/after_section_end', array( __CLASS__, 'register_controls' ) );
	}

	public static function replace_skin( $widget ) {
		if ( ! class_exists( '\\ElementorPro\\Modules\\Posts\\Skins\\Skin_Classic' ) ) {
			return;
		}
		require_once __DIR__ . '/class-posts-terms-skin.php';
		$widget->remove_skin( 'classic' );
		$widget->add_skin( new Posts_Terms_Skin( $widget ) );
	}

	public static function register_controls( $widget ) {
		$c = '\\Elementor\\Controls_Manager';
		$widget->start_controls_section( 'digitalisimo_post_terms_section', array( 'label' => 'Categorías y etiquetas', 'condition' => array( '_skin' => 'classic' ) ) );
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

	/** Taxonomías de la entrada en curso, con el mismo marcado que los metadatos de la piel. */
	public static function markup( array $settings ) {
		$show = array_intersect( array( 'category', 'post_tag' ), (array) ( $settings[ self::TERMS ] ?? array() ) );
		$out  = '';
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
		return '' !== $out ? '<div class="elementor-post__meta-data elementor-post__taxonomies">' . $out . '</div>' : '';
	}
}
