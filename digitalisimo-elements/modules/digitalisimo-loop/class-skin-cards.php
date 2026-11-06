<?php
namespace Digitalisimo\Elements\Loop;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «Tarjetas» de Entradas: repite `modules/posts/skins/skin-cards.php` de PRO (insignia de taxonomía, avatar del
 * autor, sección «Tarjeta» y los cambios de PRO en la imagen y el contenido), con marcado propio:
 *
 *     <article class="digi-posts__item …" role="listitem"><div class="digi-posts__card">
 *       <a class="digi-posts__thumb-link" …><div class="digi-posts__thumb"><img></div></a>
 *       <div class="digi-posts__badge">Categoría</div><div class="digi-posts__avatar"><img class="avatar"></div>
 *       <div class="digi-posts__text">título, extracto, leer más</div>
 *       <div class="digi-posts__meta">…</div>
 *     </div></article>
 *
 * Las clases de PRO del envoltorio pasan a las propias: `elementor-card-shadow-yes` → `digi-posts--shadow-yes`,
 * `elementor-posts__hover-gradient` → `digi-posts--hover-gradient`, `elementor-posts--show-avatar` →
 * `digi-posts--show-avatar`.
 *
 * Derivado de PRO Elements (GPL): `modules/posts/skins/skin-cards.php`.
 */
class Skin_Cards extends Skin_Base {

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- método del núcleo.
		parent::_register_controls_actions();

		add_action( 'elementor/element/' . $this->parent->get_name() . '/cards_section_design_image/before_section_end', array( $this, 'register_additional_design_image_controls' ) );
	}

	public function get_id() {
		return 'cards';
	}

	public function get_title() {
		return esc_html__( 'Tarjetas', 'pro-elements' );
	}

	public function start_controls_tab( $id, $args ) {
		$args['condition']['_skin'] = $this->get_id();
		$this->parent->start_controls_tab( $this->get_control_id( $id ), $args );
	}

	public function end_controls_tab() {
		$this->parent->end_controls_tab();
	}

	public function start_controls_tabs( $id ) {
		$this->parent->start_controls_tabs( $this->get_control_id( $id ) );
	}

	public function end_controls_tabs() {
		$this->parent->end_controls_tabs();
	}

	public function register_controls( Widget_Base $widget ) {
		$this->parent = $widget;

		$this->register_columns_controls();
		$this->register_post_count_control();
		$this->register_thumbnail_controls();
		$this->register_title_controls();
		$this->register_excerpt_controls();
		$this->register_meta_data_controls();
		$this->register_read_more_controls();
		$this->register_link_controls();
		$this->register_badge_controls();
		$this->register_avatar_controls();
	}

	public function register_design_controls() {
		$this->register_design_layout_controls();
		$this->register_design_card_controls();
		$this->register_design_image_controls();
		$this->register_design_content_controls();
	}

	protected function register_thumbnail_controls() {
		parent::register_thumbnail_controls();
		$this->remove_responsive_control( 'image_width' );
		$this->update_control(
			'thumbnail',
			array(
				'label'       => esc_html__( 'Mostrar la imagen', 'pro-elements' ),
				'options'     => array(
					'top'  => esc_html__( 'Sí', 'pro-elements' ),
					'none' => esc_html__( 'No', 'pro-elements' ),
				),
				'render_type' => 'template',
			)
		);
	}

	protected function register_meta_data_controls() {
		parent::register_meta_data_controls();
		$this->update_control( 'meta_separator', array( 'default' => '•' ) );
	}

	public function register_additional_design_image_controls() {
		$this->update_control(
			'image_spacing',
			array(
				'selectors' => array( '{{WRAPPER}} .digi-posts__text' => 'margin-top: {{SIZE}}{{UNIT}}' ),
				'condition' => array( $this->get_control_id( 'thumbnail!' ) => 'none' ),
			)
		);

		$this->remove_control( 'img_border_radius' );

		$this->add_control(
			'heading_badge_style',
			array(
				'label'     => esc_html__( 'Insignia', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);

		$this->add_control(
			'badge_position',
			array(
				'label'     => esc_html__( 'Posición de la insignia', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'  => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default'   => 'right',
				'selectors' => array( '{{WRAPPER}} .digi-posts__badge' => '{{VALUE}}: 0' ),
				'condition' => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);

		$this->add_control(
			'badge_bg_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__card .digi-posts__badge' => 'background-color: {{VALUE}};' ),
				'global'    => array( 'default' => 'globals/colors?id=accent' ),
				'condition' => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);

		$this->add_control(
			'badge_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__card .digi-posts__badge' => 'color: {{VALUE}};' ),
				'condition' => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);

		$this->add_control(
			'badge_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__card .digi-posts__badge' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);

		$this->add_control(
			'badge_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 5,
						'max' => 50,
					),
					'em'  => array(
						'min' => 0.5,
						'max' => 5,
					),
					'rem' => array(
						'min' => 0.5,
						'max' => 5,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__card .digi-posts__badge' => 'font-size: {{SIZE}}{{UNIT}}' ),
				'condition'  => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);

		$this->add_control(
			'badge_margin',
			array(
				'label'      => esc_html__( 'Margen', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'default'    => array( 'size' => 20 ),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__card .digi-posts__badge' => 'margin: {{SIZE}}{{UNIT}}' ),
				'condition'  => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'badge_typography',
				'global'    => array( 'default' => 'globals/typography?id=accent' ),
				'selector'  => '{{WRAPPER}} .digi-posts__card .digi-posts__badge',
				'exclude'   => array( 'font_size', 'line-height' ),
				'condition' => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);

		$this->add_control(
			'heading_avatar_style',
			array(
				'label'     => esc_html__( 'Avatar', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					$this->get_control_id( 'thumbnail!' ) => 'none',
					$this->get_control_id( 'show_avatar' ) => 'show-avatar',
				),
			)
		);

		$this->add_control(
			'avatar_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-posts__avatar'      => 'top: calc(-{{SIZE}}{{UNIT}} / 2);',
					'{{WRAPPER}} .digi-posts__avatar img'  => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .digi-posts__thumb-link'  => 'margin-bottom: calc({{SIZE}}{{UNIT}} / 2)',
				),
				'condition'  => array( $this->get_control_id( 'show_avatar' ) => 'show-avatar' ),
			)
		);
	}

	public function register_badge_controls() {
		$this->add_control(
			'show_badge',
			array(
				'label'     => esc_html__( 'Insignia', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'badge_taxonomy',
			array(
				'label'       => esc_html__( 'Taxonomía de la insignia', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'default'     => 'category',
				'options'     => $this->get_taxonomies(),
				'condition'   => array( $this->get_control_id( 'show_badge' ) => 'yes' ),
			)
		);
	}

	public function register_avatar_controls() {
		$this->add_control(
			'show_avatar',
			array(
				'label'        => esc_html__( 'Avatar', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off'    => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'show-avatar',
				'default'      => 'show-avatar',
				'separator'    => 'before',
				'prefix_class' => 'digi-posts--',
				'render_type'  => 'template',
				'condition'    => array( $this->get_control_id( 'thumbnail!' ) => 'none' ),
			)
		);
	}

	public function register_design_card_controls() {
		$this->start_controls_section(
			'section_design_card',
			array(
				'label' => esc_html__( 'Tarjeta', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_bg_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__card' => 'background-color: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'card_border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__card' => 'border-color: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'card_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 20 ),
					'em'  => array( 'max' => 2 ),
					'rem' => array( 'max' => 2 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__card' => 'border-width: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 200 ),
					'em'  => array( 'max' => 20 ),
					'rem' => array( 'max' => 20 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__card' => 'border-radius: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_control(
			'card_padding',
			array(
				'label'      => esc_html__( 'Relleno horizontal', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-posts__text'   => 'padding: 0 {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-posts__meta'   => 'padding: 10px {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-posts__avatar' => 'padding-right: {{SIZE}}{{UNIT}}; padding-left: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'card_vertical_padding',
			array(
				'label'      => esc_html__( 'Relleno vertical', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__card' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_control(
			'box_shadow_box_shadow_type', // Mismo nombre que PRO (pensado para ampliarse a la sombra de grupo).
			array(
				'label'        => esc_html__( 'Sombra', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'digi-posts--shadow-',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'hover_effect',
			array(
				'label'        => esc_html__( 'Efecto al pasar el cursor', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'label_block'  => false,
				'options'      => array(
					'none'     => esc_html__( 'Ninguno', 'pro-elements' ),
					'gradient' => esc_html__( 'Degradado', 'pro-elements' ),
				),
				'default'      => 'gradient',
				'separator'    => 'before',
				'prefix_class' => 'digi-posts--hover-',
			)
		);

		$this->add_control(
			'meta_border_color',
			array(
				'label'     => esc_html__( 'Color del borde de los metadatos', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( '{{WRAPPER}} .digi-posts__card .digi-posts__meta' => 'border-top-color: {{VALUE}}' ),
				'condition' => array( $this->get_control_id( 'meta_data!' ) => array() ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_design_content_controls() {
		parent::register_design_content_controls();

		$this->remove_control( 'meta_spacing' );

		$this->update_control(
			'read_more_spacing',
			array( 'selectors' => array( '{{WRAPPER}} .digi-posts__more' => 'margin-bottom: {{SIZE}}{{UNIT}};' ) ),
			array( 'recursive' => true )
		);
	}

	protected function get_taxonomies() {
		$options = array( '' => '' );
		if ( function_exists( 'get_taxonomies' ) ) {
			foreach ( get_taxonomies( array( 'show_in_nav_menus' => true ), 'objects' ) as $taxonomy ) {
				$options[ $taxonomy->name ] = $taxonomy->label;
			}
		}
		return $options;
	}

	protected function render_post_header() {
		parent::render_post_header();
		echo '<div class="digi-posts__card">';
	}

	protected function render_post_footer() {
		echo '</div>';
		parent::render_post_footer();
	}

	protected function render_avatar() {
		echo '<div class="digi-posts__avatar">' . get_avatar( get_the_author_meta( 'ID' ), 128, '', get_the_author_meta( 'display_name' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- `get_avatar()`.
	}

	protected function render_badge() {
		$taxonomy = $this->get_instance_value( 'badge_taxonomy' );
		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$terms = get_the_terms( get_the_ID(), $taxonomy );
		if ( empty( $terms[0] ) ) {
			return;
		}

		echo '<div class="digi-posts__badge">' . esc_html( $terms[0]->name ) . '</div>';
	}

	protected function render_thumbnail() {
		if ( 'none' === $this->get_instance_value( 'thumbnail' ) ) {
			return;
		}

		$html = $this->get_thumbnail_html();

		if ( '' === $html ) {
			return;
		}

		printf(
			'<a class="digi-posts__thumb-link" href="%1$s" tabindex="-1" %2$s><div class="digi-posts__thumb">%3$s</div></a>',
			esc_url( get_permalink() ),
			$this->get_optional_link_attributes_html(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal.
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- `wp_get_attachment_image()`.
		);

		if ( $this->get_instance_value( 'show_badge' ) ) {
			$this->render_badge();
		}

		if ( $this->get_instance_value( 'show_avatar' ) ) {
			$this->render_avatar();
		}
	}

	protected function render_post() {
		$this->render_post_header();
		$this->render_thumbnail();
		$this->render_text_header();
		$this->render_title();
		$this->render_excerpt();
		$this->render_read_more();
		$this->render_text_footer();
		$this->render_meta_data();
		$this->render_post_footer();
	}
}
