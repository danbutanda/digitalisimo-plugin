<?php
namespace Digitalisimo\Elements\Loop;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «Clásica» de Entradas: repite `modules/posts/skins/skin-classic.php` de PRO (la sección «Caja» con sus
 * controles), con los selectores propios (`.digi-posts__item`, `.digi-posts__text`). Como la piel clásica de Entradas
 * en Elements (`digitalisimo-widgets/class-posts-terms.php`), pinta tras los metadatos las categorías y etiquetas que
 * pide `digitalisimo_post_terms` (lo que DIGITALÍSIMO Migración lleva desde las rejillas de entradas).
 *
 * Derivado de PRO Elements (GPL): `modules/posts/skins/skin-classic.php`.
 */
class Skin_Classic extends Skin_Base {

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- método del núcleo.
		parent::_register_controls_actions();

		add_action( 'elementor/element/' . $this->parent->get_name() . '/classic_section_design_layout/after_section_end', array( $this, 'register_additional_design_controls' ) );
	}

	public function get_id() {
		return 'classic';
	}

	public function get_title() {
		return esc_html__( 'Clásica', 'pro-elements' );
	}

	public function register_additional_design_controls() {
		$this->start_controls_section(
			'section_design_box',
			array(
				'label' => esc_html__( 'Caja', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'box_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__item' => 'border-style: solid; border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' ),
			)
		);

		$this->add_responsive_control(
			'box_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 200 ),
					'em'  => array( 'max' => 20 ),
					'rem' => array( 'max' => 20 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__item' => 'border-radius: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_responsive_control(
			'box_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array( 'px' => array( 'max' => 50 ) ),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' ),
			)
		);

		$this->add_responsive_control(
			'content_padding',
			array(
				'label'      => esc_html__( 'Relleno del contenido', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' ),
				'separator'  => 'after',
			)
		);

		$this->start_controls_tabs( 'bg_effects_tabs' );

		$this->start_controls_tab( 'classic_style_normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .digi-posts__item',
			)
		);

		$this->add_control(
			'box_bg_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__item' => 'background-color: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'box_border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__item' => 'border-color: {{VALUE}}' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'classic_style_hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow_hover',
				'selector' => '{{WRAPPER}} .digi-posts__item:hover',
			)
		);

		$this->add_control(
			'box_bg_color_hover',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__item:hover' => 'background-color: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'box_border_color_hover',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__item:hover' => 'border-color: {{VALUE}}' ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/** Metadatos y, si se piden, las categorías y etiquetas de la entrada (`digitalisimo_post_terms`). */
	protected function render_meta_data() {
		parent::render_meta_data();
		echo self::terms_markup( (array) $this->parent->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
	}

	/** Categorías y etiquetas de la entrada en curso (mismas reglas que `Posts_Terms::markup()`, con clases propias). */
	public static function terms_markup( array $settings ): string {
		$show = array_intersect( array( 'category', 'post_tag' ), (array) ( $settings['digitalisimo_post_terms'] ?? array() ) );
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
			$label = 'post_tag' === $taxonomy ? trim( (string) ( $settings['digitalisimo_tags_label'] ?? '' ) ) : '';
			$out  .= '<span class="digi-posts__terms digi-posts__terms--' . esc_attr( $taxonomy ) . '">' . ( '' !== $label ? esc_html( $label ) . ' ' : '' ) . implode( ', ', $links ) . '</span>';
		}
		return '' !== $out ? '<div class="digi-posts__meta digi-posts__taxonomies">' . $out . '</div>' : '';
	}
}
