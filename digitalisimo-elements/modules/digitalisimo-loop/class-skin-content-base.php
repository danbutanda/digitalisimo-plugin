<?php
namespace Digitalisimo\Elements\Loop;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «Contenido completo» (rasgo común de Entradas y Entradas del archivo): repite
 * `modules/posts/skins/skin-content-base.php` de PRO (imagen opcional, separación entre filas, título, metadatos y el
 * contenido completo de cada entrada, con el del constructor si la entrada lo usa), con marcado y clases propios
 * (`digi-posts__content`). Evita la recursión (una entrada que se lista a sí misma) y respeta las contraseñas, como PRO.
 *
 * Derivado de PRO Elements (GPL): `modules/posts/skins/skin-content-base.php`.
 */
trait Skin_Content_Base {

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- método del núcleo.
		$widget_name = $this->parent->get_name();
		add_action( 'elementor/element/' . $widget_name . '/section_layout/before_section_end', array( $this, 'register_skin_controls' ) );

		if ( $this->parent instanceof \Digitalisimo\Elements\Loop\Widgets\Archive_Posts ) {
			add_action( 'elementor/element/' . $widget_name . '/section_layout/after_section_end', array( $this, 'register_style_sections' ) );
		} else {
			add_action( 'elementor/element/' . $widget_name . '/section_query/after_section_end', array( $this, 'register_style_sections' ) );
		}
	}

	public function get_title() {
		return esc_html__( 'Contenido completo', 'pro-elements' );
	}

	public function register_skin_controls( Widget_Base $widget ) {
		$this->parent = $widget;
		$this->register_post_count_control();
		$this->register_row_gap_control();
		$this->register_thumbnail_controls();
		$this->register_title_controls();
		$this->register_meta_data_controls();
		$this->register_link_controls();
	}

	public function register_thumbnail_controls() {
		$this->add_control(
			'thumbnail',
			array(
				'label'        => esc_html__( 'Mostrar la imagen', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'thumbnail',
				'prefix_class' => 'digi-posts--show-',
				'separator'    => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'         => 'thumbnail_size',
				'default'      => 'medium',
				'exclude'      => array( 'custom' ),
				'condition'    => array( $this->get_control_id( 'thumbnail!' ) => '' ),
				'prefix_class' => 'digi-posts--thumbnail-size-',
			)
		);

		$this->add_responsive_control(
			'item_ratio',
			array(
				'label'          => esc_html__( 'Proporción de la imagen', 'pro-elements' ),
				'type'           => Controls_Manager::SLIDER,
				'default'        => array( 'size' => 0.66 ),
				'tablet_default' => array( 'size' => '' ),
				'mobile_default' => array( 'size' => 0.5 ),
				'range'          => array(
					'px' => array(
						'min'  => 0.1,
						'max'  => 2,
						'step' => 0.01,
					),
				),
				'selectors'      => static::ratio_selectors(),
				'condition'      => array( $this->get_control_id( 'thumbnail!' ) => '' ),
			)
		);

		$this->add_responsive_control(
			'image_width',
			array(
				'label'          => esc_html__( 'Ancho de la imagen', 'pro-elements' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'          => array(
					'px'  => array( 'max' => 600 ),
					'em'  => array( 'max' => 6 ),
					'rem' => array( 'max' => 6 ),
				),
				'default'        => array(
					'size' => 100,
					'unit' => '%',
				),
				'tablet_default' => array(
					'size' => '',
					'unit' => '%',
				),
				'mobile_default' => array(
					'size' => 100,
					'unit' => '%',
				),
				'selectors'      => array( '{{WRAPPER}} .digi-posts__thumb-link' => 'width: {{SIZE}}{{UNIT}};' ),
				'condition'      => array( $this->get_control_id( 'thumbnail!' ) => '' ),
			)
		);
	}

	public function register_design_controls() {
		$this->register_additional_design_controls();
		$this->register_design_image_controls();
		$this->register_design_content_controls();
		$this->update_image_spacing_control();
	}

	public function register_row_gap_control() {
		$this->add_control(
			'row_gap',
			array(
				'label'              => esc_html__( 'Separación entre filas', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', 'em', 'rem', 'custom' ),
				'default'            => array( 'size' => 35 ),
				'range'              => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'frontend_available' => true,
				'selectors'          => array( '{{WRAPPER}} .digi-posts > .digi-posts__item' => 'margin-bottom: {{SIZE}}{{UNIT}}' ),
			)
		);
	}

	/** La separación de la imagen, sólo hacia abajo (como PRO). */
	public function update_image_spacing_control() {
		$this->update_control(
			'image_spacing',
			array(
				'selectors' => array(
					'{{WRAPPER}} .digi-posts--skin-full_content a.digi-posts__thumb-link'         => 'margin-bottom: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-posts--skin-archive_full_content a.digi-posts__thumb-link' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);
	}

	protected function render_thumbnail() {
		// En el editor se pinta siempre (la piel la oculta con CSS), como PRO.
		if ( empty( $this->get_instance_value( 'thumbnail' ) ) && ! self::is_edit_mode() ) {
			return;
		}

		$html = $this->get_thumbnail_html();

		if ( '' === $html ) {
			return;
		}

		printf(
			'<a class="digi-posts__thumb-link" href="%1$s" tabindex="-1" %2$s><div class="digi-posts__thumb">%3$s</div></a>',
			esc_url( $this->current_permalink ),
			$this->get_optional_link_attributes_html(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal.
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- `wp_get_attachment_image()`.
		);
	}

	/**
	 * Contenido de la entrada en curso: el del constructor si lo tiene (sin el filtro de contenido, para no entrar en
	 * recursión) o `the_content` con sus páginas.
	 *
	 * @param bool $with_wrapper Envolverlo en `.digi-posts__content`.
	 * @param bool $with_css     Imprimir en línea el CSS del contenido.
	 */
	public function render_post_content( $with_wrapper = false, $with_css = true ) {
		static $did_posts = array();
		static $level     = 0;

		$post = get_post();

		if ( ! $post ) {
			return;
		}

		if ( post_password_required( $post->ID ) ) {
			echo get_the_password_form( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formulario del núcleo.
			return;
		}

		// Sin recursión.
		if ( isset( $did_posts[ $post->ID ] ) ) {
			return;
		}

		++$level;
		$did_posts[ $post->ID ] = true;

		$elementor    = \Elementor\Plugin::$instance;
		$editor       = $elementor->editor;
		$is_edit_mode = $editor->is_edit_mode();

		if ( $elementor->preview->is_preview_mode( $post->ID ) ) {
			$content = $elementor->preview->builder_wrapper( '' );
		} else {
			// Plantilla del constructor de temas: se muestra el contenido de su vista previa.
			if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
				$document = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_document( $post->ID );
				if ( $document ) {
					$preview_type = (string) $document->get_settings( 'preview_type' );
					$preview_id   = $document->get_settings( 'preview_id' );

					if ( 0 === strpos( $preview_type, 'single' ) && ! empty( $preview_id ) ) {
						$post = get_post( $preview_id );

						if ( ! $post ) {
							--$level;
							return;
						}
					}
				}
			}

			$editor->set_edit_mode( false );

			$content = $elementor->frontend->get_builder_content( $post->ID, $with_css );

			$elementor->frontend->remove_content_filter();

			if ( empty( $content ) ) {
				setup_postdata( $post );

				/** Filtro documentado en wp-includes/post-template.php. */
				echo apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido de la entrada.

				wp_link_pages(
					array(
						// Texto visible: el msgid y el dominio de PRO («Pages:» o la traducción del paquete de idioma de PRO).
						'before'      => '<div class="page-links elementor-page-links"><span class="page-links-title elementor-page-links-title">' . esc_html__( 'Pages:', 'elementor-pro' ) . '</span>', // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- mismo texto que PRO (posts/skins/skin-content-base.php).
						'after'       => '</div>',
						'link_before' => '<span>',
						'link_after'  => '</span>',
						'pagelink'    => '<span class="screen-reader-text">' . esc_html__( 'Página', 'pro-elements' ) . ' </span>%',
						'separator'   => '<span class="screen-reader-text">, </span>',
					)
				);

				$elementor->frontend->add_content_filter();

				--$level;

				$editor->set_edit_mode( $is_edit_mode );

				return;
			}

			$elementor->frontend->remove_content_filters();
			$content = apply_filters( 'the_content', $content );
			$elementor->frontend->restore_content_filters();
		}

		$editor->set_edit_mode( $is_edit_mode );

		if ( $with_wrapper ) {
			echo '<div class="digi-posts__content">' . balanceTags( $content, true ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido de la entrada.
		} else {
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido de la entrada.
		}

		--$level;

		if ( 0 === $level ) {
			$did_posts = array();
		}
	}

	protected function render_post() {
		$this->render_post_header();
		$this->render_thumbnail();
		$this->render_text_header();
		$this->render_title();
		$this->render_meta_data();
		$this->render_post_content( true );
		$this->render_text_footer();
		$this->render_post_footer();
	}
}
